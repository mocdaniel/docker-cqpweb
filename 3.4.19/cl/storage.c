/*
 *  IMS Open Corpus Workbench (CWB)
 *  Copyright (C) 1993-2006 by IMS, University of Stuttgart
 *  Copyright (C) 2007-     by the respective contributers (see file AUTHORS)
 *
 *  This program is free software; you can redistribute it and/or modify it
 *  under the terms of the GNU General Public License as published by the
 *  Free Software Foundation; either version 2, or (at your option) any later
 *  version.
 *
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 *  Public License for more details (in the file "COPYING", or available via
 *  WWW at http://www.gnu.org/copyleft/gpl.html).
 */

#include <sys/types.h>
#ifndef __MINGW__
#include <sys/mman.h>
#else
#include "windows-mmap.h"
#endif
#include <sys/stat.h>
#include <fcntl.h>
#include <errno.h>

#include "globals.h"
#include "endian.h"
#include "storage.h"


/**
 * Flags used in calls to mmap.
 * TODO: actually, only used when calling mmap() for read: should be renamed to indicate this.
 * @see mmapfile
 */
#if defined(__FreeBSD__)
#define MMAPFLAGS  MAP_FILE | MAP_PRIVATE
#elif defined(__svr4__)
#define MMAPFLAGS  MAP_PRIVATE | MAP_NORESERVE
#else
#define MMAPFLAGS  MAP_PRIVATE
#endif

#define MMAP_EMPTY_LEN 8    /* can't mmap() 0 bytes, so mmap() MMAP_EMPTY_LEN bytes beyond end-of-file for empty files */


/* ============================================================ */

/* TODO Nwrite / Nread belong elsewhere surely? */

/**
 * Writes a 32-bit integer to file, converting to network byte order.
 *
 * Other than the byte order conversion, amd the fact that it exits
 * the program upon error (so the user of this function can assume
 * success), this is the same as
 *
 * fwrite(&val, sizeof(int), 1, fd) .
 *
 * @param val  The integer to write.
 * @param fd   File handle to write to.
 */
void
NwriteInt(int val, FILE *fd)
{
  int word;
  word = htonl(val);
  if (1 != fwrite(&word, sizeof(int), 1, fd)) {
    perror("File write error");
    exit(1);
  }
}

/**
 * Reads a 32-bit integer from file, converting from network byte order.
 *
 * This function does all the error checking for you, and will abort
 * the program if the int cannot be read.
 *
 * @param val  Location to put the resulting int.
 * @param fd   File handle to read from
 */
void
NreadInt(int *val, FILE *fd)
{
  int word;
  if (1 != fread(&word, sizeof(int), 1, fd)) {
    perror("File read error");
    exit(1);
  }
  *val = ntohl(word);
}


/**
 * Writes an array of 32-bit integers to file, converting to network byte order.
 *
 * Other than the byte order conversion, this is the same as
 * fwrite(vals, sizeof(int), nr_vals, fd) .
 *
 * @param vals     Pointer to the location of the block of integers to write.
 * @param nr_vals  Number of integers to write.
 * @param fd       File handle to write to.
 */
void
NwriteInts(int *vals, int nr_vals, FILE *fd)
{
  int word, k;

  /* I strongly believe in buffered IO (;-) */
  for (k = 0; k < nr_vals; k++) {
    word = htonl(vals[k]);
    if (1 != fwrite(&word, sizeof(int), 1, fd)) {
      perror("File write error");
      exit(1);
    }
  }
}

/**
 * Reads an array of 32-bit integers from file, converting from network byte order.
 *
 * This function does all the error checking for you, and will abort
 * the program if the requested number of ints cannot be read.
 *
 * @param vals     Pointer to location to put the resulting array of ints.
 *                 (This memory must have been allocated by the caller.)
 * @param nr_vals  Number of integers to read.
 * @param fd       File handle to read from
 */
void
NreadInts(int *vals, int nr_vals, FILE *fd)
{
  int word, k;

  /* I strongly believe in buffered IO (;-) */
  for (k = 0; k < nr_vals; k++) {
    if (1 != fread(&word, sizeof(int), 1, fd)) {
      perror("File read error");
      exit(1);
    }
    vals[k] = ntohl(word);
  }
}


/* ============================================================ */


/**
 * Clears all fields in a MemBlob, regardless of their usage,
 * and puts the blob back to its virginal state.
 *
 * Note that it doesn't free blob->data - just sets it to NULL.
 */
void
init_mblob(MemBlob *blob)
{
  assert((blob != NULL) && "CL Memblob: init_mblob(): You can't pass a NULL blob!");

  blob->data = NULL;
  blob->size = 0;
  blob->item_size = 0;
  blob->nr_items = 0;
  blob->allocation_method = UNALLOCATED;
  blob->writeable = 0;
  blob->changed = 0;
  blob->fname = NULL;
  blob->fsize = 0;
  blob->offset = 0;
}

/**
 * Allocates memory for a blob of the requested size, using malloc(2).
 *
 * A block of memory holding nr_items of size item_size is created
 * in the specified MemBlob.
 *
 * @param blob        The MemBlob in which to place the memory.
 * @param nr_items    The number of items the MemBlob is to hold as data.
 * @param item_size   The size of one item.
 * @param clear_blob  boolean: if true, all bytes in the data space will be initialised to 0
 * @return            boolean: true 1 if OK, false on error
 */
int
alloc_mblob(MemBlob *blob, int nr_items, int item_size, int clear_blob)
{
  int size;

  assert( (blob != NULL)   &&  "CL MemBlob: alloc_mblob(): You can't pass a NULL blob!");
  assert( (item_size >= 0) &&  "CL MemBlob: alloc_mblob(): item_size must be >= 0!");
  assert( (nr_items > 0)   &&  "CL MemBlob: alloc_mblob(): nr_items must be > 0!");

  blob->item_size = item_size;
  blob->nr_items = nr_items;
  if (item_size == SIZE_BIT) {
    size = nr_items / 8;
    if (size * 8 < nr_items) {
      size++;                        /* make room for 'extra' bits */
    }
  }
  else {
    size = nr_items * item_size;
  }
  blob->size = size;
  if (clear_blob) {
    blob->data = (int *) cl_calloc(size, 1);
  }
  else {
    blob->data = (int *) cl_malloc(size);
  }
  blob->allocation_method = MALLOCED;
  blob->writeable = 1;
  blob->changed = 0;
  blob->fname = NULL;
  blob->fsize = 0;
  blob->offset = 0;

  return 1;
}


/**
 * Frees the memory used by a MemBlob.
 *
 * This works regardless of the method used to allocate the blob.
 */
void
mfree(MemBlob *blob)
{
  unsigned int map_len;

  assert((blob != NULL) && "You can't pass a NULL blob to mfree");

  if (blob->data != NULL) {
    switch (blob->allocation_method) {
    case UNALLOCATED:
      fprintf(stderr, "CL MemBlob:mfree():  Blob flag is UNALLOCATED, but data present -- no free\n");
      break;
    case MMAPPED:
      map_len = (blob->size > 0) ? blob->size : MMAP_EMPTY_LEN;
      if (munmap((void *)blob->data, map_len) < 0)
        perror("CL MemBlob:munmap()");
      break;
    case MALLOCED:
      cl_free(blob->data);
      break;
    default:
      assert("CL MemBlob:mfree(): Illegal memory storage class" && 0);
      break;
    }
    cl_free(blob->fname);
    init_mblob(blob);
  }
  else {
    if (blob->allocation_method != UNALLOCATED)
      fprintf(stderr, "CL MemBlob:mfree():  No data, but MemBlob flag isn't UNALLOCATED\n");
  }
}






/**
 * Maps a file into memory in either read or write mode.
 *
 * @param filename  Name of the file to map.
 * @param len_ptr   The number of bytes the returned pointer points to.
 * @param mode      Can be either "r", "w", "rb" or "wb". (a "+" can be used too, but is ignored.)
 *                  If mode is "r", the amount of memory allocated (size of file) is placed in len_ptr.
 *                  If mode is "w", len_ptr is taken as an input parameter (*len_ptr bytes
 *                  are allocated).
 * @return          The contents of file in filename as a pointer to a memory area.
 */
void *
mmapfile(char *filename, size_t *len_ptr, char *mode)
{
  struct stat stat_buf;
  int fd;
  int binflag = 0; /* set to O_BINARY if we want to use the binary flag with open() */
  void *space;
  size_t map_len; /* should probably be off_t (for file sizes) rather than size_t (for size of objects), according to SUS */

  /* allow for: r+b, w+b, rb+, wb+ */
  binflag = (mode[1] == 'b' || (mode[1] == '+' && mode[2] == 'b') ) ? O_BINARY : 0;

  space = NULL;

  switch(mode[0]) {
  case 'r':
    fd = open(filename, O_RDONLY|binflag);

    if (fd == EOF) {
      fprintf(stderr, "CL MemBlob:mmapfile(): Can't open file %s ... \n\tReason: ", filename);
      perror(NULL);
    }
    else if(fstat(fd, &stat_buf) == EOF) {
      fprintf(stderr, "CL MemBlob:mmapfile(): Can't fstat() file %s ... \n\tReason: ", filename);
      perror(NULL);
    }
    else {
      *len_ptr = stat_buf.st_size;
      /* if file is empty, mmap() beyond end of file, but report empty size to CL */
      map_len = (stat_buf.st_size > 0) ? stat_buf.st_size : MMAP_EMPTY_LEN;

      space = mmap(NULL, map_len, PROT_READ, MMAPFLAGS, fd, 0);
    }

    if (fd != EOF)
      close(fd);
    break;

  case 'w':
    /* if the file does not exist, create; then mmap it, unless both open/create were unsuccessful. */
    if ((fd = open(filename, O_RDWR|O_CREAT|binflag, 0666)) == EOF)
      fd = creat(filename, 0666);

    if (fd == EOF) {
      fprintf(stderr, "CL MemBlob:mmapfile(): Can't create file %s ...\n\tReason: ", filename);
      perror(NULL);
    }
    else {
      /* scroll to the len_ptr'th byte, then overwrite the last integer with a random integer (why? not sure  -- AH),
       * then rewind file */
      lseek(fd, *len_ptr - sizeof(int), SEEK_SET);
      write(fd, &fd, sizeof(int));
      lseek(fd, 0, SEEK_SET);

      space = mmap(NULL, *len_ptr, PROT_READ|PROT_WRITE, MAP_SHARED, fd, 0);
    }

    if (fd != EOF)
      close(fd);
    break;

  default:
    fprintf(stderr, "CL MemBlob:mmapfile(): Mode '%s' is not supported ...\n", mode);
  }

  if (space == MAP_FAILED) {
    fprintf(stderr, "CL MemBlob:mmapfile(): Can't mmap() file %s ...\n"
            "\tYou have probably run out of memory / address space!\n"
            "\tError Message: ",
            filename);
    perror(NULL);
    space = NULL;
  }

  return space;
}


/**
 * Maps a file into memory.
 *
 * This function does virtually the same as mmapfile (same parameters, same return
 * value), but the memory is taken with malloc(3), not with mmap(2).
 *
 * @see mmapfile
 */
void *
mallocfile(char *filename, size_t *len_ptr, char *mode)
{
  struct stat stat_buf;
  int fd;
  int binflag = 0; /* set to O_BINARY if we want to use the binary flag with open() */
  void *space;

  /* allow for: r+b, w+b, rb+, wb+, rb, wb */
  binflag = (mode[1] == 'b' || (mode[1] == '+' && mode[2] == 'b') ) ? O_BINARY : 0;

  space = NULL;

  switch(mode[0]) {
  case 'r':
    fd = open(filename, O_RDONLY|binflag);

    if (fd == EOF) {
      fprintf(stderr, "CL MemBlob:mallocfile():  can't open %s -- ", filename);
      perror(NULL);
    }
    else if(fstat(fd, &stat_buf) == EOF) {
      fprintf(stderr, "CL MemBlob:mallocfile():  can't stat %s -- ", filename);
      perror(NULL);
    }
    else {
      *len_ptr = stat_buf.st_size;

      space = (void *)cl_malloc(*len_ptr);

      if (read(fd, space, *len_ptr) != *len_ptr) {
        fprintf(stderr, "CL MemBlob:mallocfile():  couldn't read file contents -- ");
        perror(NULL);
        cl_free(space);
        space = NULL;
      }
    }
    if (fd != EOF)
      close(fd);
    break;

  case 'w':
    if ((fd = open(filename, O_RDWR|O_CREAT|binflag, 0666)) == EOF)
      fd = creat(filename, 0666);

    if (fd == EOF) {
      fprintf(stderr, "CL MemBlob:mallocfile():  can't open/create %s for writing -- ", filename);
      perror(NULL);
    }
    else {
      space = cl_malloc(*len_ptr);

      if (write(fd, space, *len_ptr) != *len_ptr) {
        fprintf(stderr, "CL MemBlob:mallocfile():  couldn't write file -- ");
        perror(NULL);
        cl_free(space);
        space = NULL;
      }
    }
    if (fd != EOF)
      close(fd);
    break;

  default:
    fprintf(stderr, "CL MemBlob:mallocfile():  mode %s is not supported\n", mode);
  }

  return space;
}


/**
 * Reads the contents of a file into memory represented by blob.
 *
 * You can choose the memory allocation method - MMAPPED is faster, but
 * writeable areas of memory should be taken with care. MALLOCED is
 * slower (and far more space consuming), but writing data into malloced
 * memory is no problem.
 *
 * In Windows, the read is always binary-mode.
 *
 * @param filename           The file to read in.
 * @param allocation_method  MMAPPED or MALLOCED (see function description)
 * @param item_size          This is used for MemBlob access methods, it
 *                           is simply copied into the MemBlob data
 *                           structure.
 * @param blob               The MemBlob to read the file into. It must not
 *                           be in use -- the fields are overwritten.
 * @return                   0 on failure, 1 if everything went fine.
 */
int
read_file_into_blob(char *filename, int allocation_method, int item_size, MemBlob *blob)
{
  assert("CL MemBlob:read_file_into_blob(): You must not pass a NULL blob!" && blob != NULL);

  blob->item_size = item_size;
  blob->allocation_method = allocation_method;
  blob->writeable = 0;
  blob->changed = 0;

  if (allocation_method == MMAPPED)
    blob->data = (int *)mmapfile(filename, &(blob->size), "rb");
  else if (allocation_method == MALLOCED)
    blob->data = (int *)mallocfile(filename, &(blob->size), "rb");
  else {
    fprintf(stderr, "CL MemBlob:read_file_into_blob(): allocation method %d is not supported\n", allocation_method);
    return 0;
  }

  if (blob->data == NULL) {
    blob->nr_items = 0;
    blob->allocation_method = UNALLOCATED;
    return 0;
  }

  blob->nr_items = (item_size == SIZE_BIT) ? blob->size * 8 : blob->size / item_size;

  return 1;
}



/**
 * Writes the data stored in a blob to file.
 *
 * Note that here we are writing to a new file: we are *not*
 * writing back to the file the data came from, if MMAPPED.
 *
 * In Windows, the write is always binary-mode.
 *
 * @param filename           The file to write to.
 * @param blob               The MemBlob to write to file.
 * @param convert_to_nbo     boolean: if true, data is converted to
 *                           network byte order before it's written.
 * @return                   0 on failure, 1 if everything went fine.
 */
int
write_file_from_blob(char *filename, MemBlob *blob, int convert_to_nbo)
{
  FILE *dst;

  assert("CL MemBlob:write_file_from_blob(): You must not pass a NULL blob!" && (blob != NULL));

  blob->changed = 0;

  if (blob->data == NULL || (blob->size == 0)) {
    fprintf(stderr, "CL MemBlob:write_file_from_blob(): no data in blob, nothing to write...\n");
    return 0;
  }

  switch (blob->allocation_method) {
  case UNALLOCATED:
    fprintf(stderr, "CL MemBlob:write_file_from_blob(): tried to write unallocated blob...\n");
    return 0;

  case MMAPPED:
  case MALLOCED:
    if (!(dst = fopen(filename, "wb"))) {
      fprintf(stderr, "CL MemBlob:write_file_from_blob(): Can't open output file %s\n", filename);
      return 0;
    }
    if (convert_to_nbo)
      NwriteInts((int *)blob->data, blob->size/4, dst);
    else
      fwrite(blob->data, 1, blob->size, dst);
    fclose(dst);
    return 1;

  default:
    fprintf(stderr, "CL MemBlob:write_file_from_blob(): unsupported allocation method # %d...\n", blob->allocation_method);
    return 0;
  }

  return 0;
}


