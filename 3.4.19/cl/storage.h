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

#ifndef __storage_h
#define __storage_h

#include <sys/types.h>
#include <stddef.h>

//#include "globals.h"

#ifdef __MINGW__
#include "windows-mmap.h"
#endif

/* some old non-POSIX unixes may lack this constant... */
#ifndef MAP_FAILED
#define MAP_FAILED ((void *) -1)
#endif

/* data allocation methods */

#define UNALLOCATED 0 /**< Flag: no memory has been allocated */
#define MMAPPED  1    /**< Flag: indicates use of mmap() to allocate memory  in a MemBlob*/
#define MALLOCED 2    /**< Flag: indicates use of malloc() to allocate memory */

/* TODO use these new, clearer macros in future */
#define CL_MEMBLOB_UNALLOCATED 0 /**< Flag: indicates no memory has been allocated */
#define CL_MEMBLOB_MMAPPED  1    /**< Flag: indicates use of mmap() to allocate memory in a MemBlob */
#define CL_MEMBLOB_MALLOCED 2    /**< Flag: indicates use of malloc() to allocate memory in a MemBlob  */


/* macros defining the size of individual items within a MemBlob. */
#define SIZE_BIT   0
#define SIZE_BYTE  sizeof(char)
#define SIZE_SHINT sizeof(short)
#define SIZE_INT   sizeof(int)
#define SIZE_LONG  sizeof(long)


/**
 * The MemBlob object.
 *
 * This object, unsurprisingly, represents a blob of memory.
 */
typedef struct TMblob {
  size_t size;                  /**< the number of allocated bytes */

  int item_size;                /**< the size of one item */
  unsigned int nr_items;        /**< the number of items represented */

  int *data;                    /**< pointer to the data */
  int allocation_method;        /**< the allocation method */

  int writeable;                /**< can we write to the data? */
  int changed;                  /**< needs update? (not yet in use) */

  /* fields for paged memory -- not yet used */
  char *fname;
  off_t fsize, offset;
} MemBlob;



/* ---------------------------------------------------------------------- */



void NwriteInt(int val, FILE *fd);
void NreadInt(int *val, FILE *fd);



void NwriteInts(int *vals, int nr_vals, FILE *fd);
void NreadInts(int *vals, int nr_vals, FILE *fd);


/* ---------------------------------------------------------------------- */



void mfree(MemBlob *blob);

void init_mblob(MemBlob *blob);

int alloc_mblob(MemBlob *blob, int nr_items, int item_size, int clear_blob);




/* ================================================================ FILE IO */



int read_file_into_blob(char *filename,
                        int allocation_method,
                        int item_size,
                        MemBlob *blob);




int write_file_from_blob(char *filename,
                         MemBlob *blob,
                         int convert_to_nbo);

/* ======================================================= ACCESS FUNCTIONS */

/* not yet implemented */

/* ==================================================== LOW LEVEL FUNCTIONS */

void *mmapfile(char *filename, size_t *len_ptr, char *mode);
void *mallocfile(char *filename, size_t *len_ptr, char *mode);

/* a new-style API for MemBlobs */
/* TODO argument orders for the read/write functions are wrong... */
#define memblob_read_from_file read_file_into_blob
#define memblob_write_to_file  write_file_from_blob
#define memblob_free           mfree
#define memblob_clear          init_mblob
#define memblob_allocate       alloc_mblob


#endif
