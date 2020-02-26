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


#include <sys/stat.h>
#include <fcntl.h>

#include <glib.h>

#include "globals.h"
#include "fileutils.h"


/**
 * Gets the size of the specified file; returns EOF for error.
 *
 * @param filename  The file to size up.
 * @return          Size of file in bytes (or EOF if call to stat() failed)
 */
off_t
file_length(char *filename)
{
  struct stat stat_buf;
  if (EOF == stat(filename, &stat_buf))
    return EOF;
  else
    return stat_buf.st_size;
}

/**
 * Gets the size of the specified file; returns EOF for error.
 *
 * As file_length, but the file is specified by file handle, not name.
 *
 * @see file_length
 * @param fh  The file to size up.
 * @return    Size of file in bytes.
 */
off_t
fh_file_length(FILE *fh)
{
  struct stat stat_buf;
  if (EOF == fstat(fileno(fh), &stat_buf))
    return EOF;
  else
    return stat_buf.st_size;
}

/**
 * Gets the size of the specified file; returns EOF for error.
 *
 * As file_length, but the file is specified by number, not name.
 *
 * @see file_length
 * @param fd    The file to size up.
 * @return      Size of file in bytes.
 */
off_t
fd_file_length(int fd)
{
  struct stat stat_buf;
  if (EOF == fstat(fd, &stat_buf))
    return EOF ;
  else
    return stat_buf.st_size;
}

/**
 * Gets the size of the specified file; returns EOF for error.
 *
 * Duplicates functionality of file_length, but return is long
 * instead of off_t.
 *
 * @see file_length
 * @param fname  The file to size up.
 * @return       Size of file in bytes.
 */
long
fprobe(char *fname)
{
  struct stat stat_buf;
  if (EOF == stat(fname, &stat_buf))
    return (long) EOF;
  else
    return stat_buf.st_size;
}


/**
 * Checks whether the specified path indicates a directory.
 *
 * @param path  Path to check.
 * @return      Boolean. (Also false if there's an error.)
 */
int
is_directory(char *path)
{
  struct stat stat_buf;
  if (0 > stat(path, &stat_buf))
    return 0;
  else
    return S_ISDIR(stat_buf.st_mode) ;
}

/**
 * Checks whether the specified path indicates a regular file.
 *
 * @param path  Path to check.
 * @return      Boolean. (Also false if there's an error.)
 */
int
is_file(char *path)
{
  struct stat stat_buf;
  if (0 > stat(path, &stat_buf))
    return 0;
  else
    return S_ISREG(stat_buf.st_mode);
}

/**
 * Checks whether the specified path indicates a link.
 *
 * Note this function always returns false in Windows, because Windows
 * doesn't have Unix-style links. (.lnk files don't count.)
 *
 * TODO, check, has this changed in more recent WIN versiosn than when I wrote the above (ah, 2019)
 *
 * @param path  Path to check.
 * @return      Boolean. (Also false if there's an error.)
 */
int
is_link(char *path)
{
#ifndef __MINGW__
  struct stat stat_buf;
  if (0 > stat(path, &stat_buf))
    return 0;
  else
    return S_ISLNK(stat_buf.st_mode);
#else
  return 0;
#endif
}

/**
 * Implementation of automagic I/O streams.
 *
 * In order to make streams completely transparent to the caller
 * and return them as standard FILE* objects, the CL keeps a list
 * of all open streams with the necessary metadata information.
 */
CLStream open_streams;

/**
 * SIGPIPE handler and global status variable
 */
int cl_broken_pipe = 0;

static void
cl_handle_sigpipe(int signum)
{
#ifndef __MINGW__
  cl_broken_pipe = 1;
  /* fprintf(stderr, "Handle broken pipe signal\n"); */

  if (SIG_ERR == signal(SIGPIPE, cl_handle_sigpipe))
    perror("CL: Can't reinstall SIGPIPE handler (ignored)"); /* Is this still necessary on modern platforms? */
#endif
}

/** check whether stream type involves a pipe */
#define STREAM_IS_PIPE(type) (type == CL_STREAM_PIPE || type == CL_STREAM_GZIP || type == CL_STREAM_BZIP2)

/**
 * Open stream of specified (or guessed) type for reading or writing
 *
 * I/O streams opened with this function must always be closed with cl_close_stream()!
 *
 * @param filename  Filename or shell command
 * @param mode      Open for reading (CL_STREAM_READ) or writing (CL_STREAM_WRITE)
 * @param type      Type of stream (see above), or guess automagically from <filename> (CL_STREAM_MAGIC)
 *
 * @return          Standard C stream, or NULL on error (with details from <cl_errno> or cl_error())
 */
FILE *
cl_open_stream(const char *filename, int mode, int type)
{
  char *point, *mode_spec;
  int l = strlen(filename);
  FILE *handle;
  CLStream stream;
  char command[2 * CL_MAX_FILENAME_LENGTH]; /* may be longer than CL_MAX_FILENAME_LENGTH */
  char expanded_filename[2 * CL_MAX_FILENAME_LENGTH];

  if (l > CL_MAX_FILENAME_LENGTH) {
    fprintf(stderr, "CL: filename '%s' too long (limit: %d bytes)\n", filename, CL_MAX_FILENAME_LENGTH);
    cl_errno = CDA_EBUFFER;
    return NULL;
  }

  /* validate read/write/append mode */
  switch (mode) {
  case CL_STREAM_READ:
    mode_spec = "r";
    break;
  case CL_STREAM_WRITE:
    mode_spec = "w";
    break;
  case CL_STREAM_APPEND:
    mode_spec = "a";
    break;
#ifdef __MINGW__
  /* Binary modes only take effect on Windows.
   * Their only effect is to add "b" to the spec,
   * thereafter, they collapse back to the
   * same thing as the constants without _BIN.
   */
  case CL_STREAM_READ_BIN:
    mode_spec = "rb";
    mode = CL_STREAM_READ;
    break;
  case CL_STREAM_WRITE_BIN:
    mode_spec = "wb";
    mode = CL_STREAM_WRITE;
    break;
  case CL_STREAM_APPEND_BIN:
    mode_spec = "ab";
    mode = CL_STREAM_APPEND;
    break;
#endif
  default:
    fprintf(stderr, "CL: invalid I/O stream mode = %d\n", mode);
    cl_errno = CDA_EARGS;
    return NULL;
  }

  /* apply magic */
  if (type == CL_STREAM_MAGIC || type == CL_STREAM_MAGIC_NOPIPE) {
    /* expand ~/ or $HOME/ */
    if (0 == strncmp(filename, "~/", 2) || 0 == strncasecmp(filename, "$home/", 6)) {
      char *home = getenv("HOME");
      if (home && home[0] != '\0') {
        filename = (filename[0] == '~') ? filename + 2 : filename + 6;
        snprintf(expanded_filename, 2 * CL_MAX_FILENAME_LENGTH, "%s/%s", home, filename);
        filename = expanded_filename;
        l = strlen(filename); /* don't forget to update string length */
      }
    }
    /* guess type of stream */
    type = CL_STREAM_FILE; /* default */

    /* "-" = STDIN or STDOUT */
    if (CL_STREQ(filename, "-"))
      type = CL_STREAM_STDIO;
    else {
      point = (char *) filename + strspn(filename, " \t");

      /* " | ..." = read or write pipe to shell command */
      if (*point == '|') {
        if (type == CL_STREAM_MAGIC_NOPIPE) {
          cl_errno = CDA_EACCESS;
          return NULL;
        }
        type = CL_STREAM_PIPE;
        point++;
        filename = point + strspn(point, " \t");
      }

      /* *.gz = gzip-compressed file */
      else if (l > 3 && 0 == strcasecmp(filename + l - 3, ".gz"))
        type = CL_STREAM_GZIP;

      /* *.bz2 = bzip2-compressed file */
      else if (l > 4 && 0 == strcasecmp(filename + l - 4, ".bz2"))
        type = CL_STREAM_BZIP2;
    }
  }

  /* file access errors are delayed when reading/writing through pipe to gzip or bzip2,
   * so check first that we can access the file in the appropriate mode */
  if (type == CL_STREAM_GZIP || type == CL_STREAM_BZIP2) {
    handle = fopen(filename, mode_spec);
    if (handle == NULL) {
      cl_errno = CDA_EPOSIX;
      return NULL;
    }
    fclose(handle);
    handle = NULL;
  }

  /* open file or pipe */
  switch (type) {
  case CL_STREAM_FILE:
    handle = fopen(filename, mode_spec);
    break;
  case CL_STREAM_GZIP:
    point = g_shell_quote(filename);
    if (mode == CL_STREAM_APPEND) {
      sprintf(command, "gzip >> %s", point);
      mode_spec = (mode_spec[1] == 'b' ? "wb" : "w");
    }
    else if (mode == CL_STREAM_WRITE)
      sprintf(command, "gzip > %s", point);
    else
      sprintf(command, "gzip -cd %s", point);
    handle = popen(command, mode_spec);
    g_free(point);
    break;
  case CL_STREAM_BZIP2:
    point = g_shell_quote(filename);
    if (mode == CL_STREAM_APPEND) {
      sprintf(command, "bzip2 >> %s", point);
      mode_spec = (mode_spec[1] == 'b' ? "wb" : "w");
    }
    else if (mode == CL_STREAM_WRITE)
      sprintf(command, "bzip2 > %s", point);
    else
      sprintf(command, "bzip2 -cd %s", point);
    handle = popen(command, mode_spec);
    g_free(point);
    break;
  case CL_STREAM_PIPE:
    if (mode == CL_STREAM_APPEND)
      mode_spec = (mode_spec[1] == 'b' ? "wb" : "w");
    handle = popen(filename, mode_spec);
    break;
  case CL_STREAM_STDIO:
    handle = (mode == CL_STREAM_READ) ? stdin : stdout;
    break;
  default:
    fprintf(stderr, "CL: invalid I/O stream type = %d\n", type);
    cl_errno = CDA_EARGS;
    return NULL;
  }
  if (handle == NULL) {
    cl_errno = CDA_EPOSIX;
    return NULL;
  }

  /* add to list of managed streams */
  stream = (CLStream) cl_malloc(sizeof(struct _CLStream));
  stream->handle = handle;
  stream->mode = mode;
  stream->type = type;
  stream->next = open_streams;
  open_streams = stream;

  /* install SIGPIPE handler if opening a pipe stream */
#ifndef __MINGW__
  if (STREAM_IS_PIPE(type))
    if (SIG_ERR == signal(SIGPIPE, cl_handle_sigpipe))
      perror("CL: can't install SIGPIPE handler (ignored)");
#endif

  cl_broken_pipe = 0;
  cl_errno = CDA_OK;
  return handle;
}

/**
 * Close I/O stream.
 *
 * This function can only be used for FILE* objects opened with cl_open_stream()!
 *
 * @param handle    An I/O stream that has been opened with cl_open_stream()
 * @return          0 on success, otherwise the error code returned by fclose() or pclose()
 */
int
cl_close_stream(FILE *handle)
{
  CLStream stream, point;
  int result = 0, was_pipe = 0;

  for (stream = open_streams ; stream ; stream = stream->next)
    if (stream->handle == handle)
      break;

  if (!stream) {
    fprintf(stderr, "CL: attempt to close non-managed I/O stream with cl_close_stream() [ignored]\n");
    return CDA_EATTTYPE;
  }

  /* close stream appropriately, depending on type */
  switch (stream->type) {
  case CL_STREAM_STDIO:
    break;
  case CL_STREAM_FILE:
    result = fclose(stream->handle);
    break;
  case CL_STREAM_GZIP:
  case CL_STREAM_BZIP2:
  case CL_STREAM_PIPE:
    result = pclose(stream->handle);
    was_pipe = 1;
    break;
  default:
    fprintf(stderr, "CL: internal error, managed I/O stream has invalid type = %d\n", stream->type);
    exit(1);
  }

  /* remove stream from list */
  if (stream == open_streams)
    open_streams = stream->next;
  else {
    for (point = open_streams; point->next != stream; point = point->next)
      /* pass */ ;
    point->next = stream->next;
  }
  cl_free(stream);

  /* if stream was a pipe, check whether we can uninstall the SIGPIPE handler */
#ifndef __MINGW__
  if (was_pipe) {
    int any_pipe = 0;

    for (point = open_streams; point; point = point->next)
      if (STREAM_IS_PIPE(point->type))
        any_pipe = 1;

    /* if last pipe stream closed, uninstall SIGPIPE handler */
    if (!any_pipe)
      if (signal(SIGPIPE, SIG_IGN) == SIG_ERR)
        perror("CL: can't uninstall SIGPIPE handler (ignored)");
  }
#endif

  cl_broken_pipe = 0;
  cl_errno = result ? CDA_EPOSIX : CDA_OK;

  return result;
}



/**
 * Tests whether a file handle has been opened via cl_test_stream.
 *
 * @param handle  The FILE pointer to check.
 * @return        Boolean. True iff the "cl_stream" knows about this handle.
 */
int
cl_test_stream(FILE *handle)
{
  CLStream stream;
  for (stream = open_streams ; stream ; stream = stream->next)
    if (stream->handle == handle)
      return 1;
  return 0;;
}


