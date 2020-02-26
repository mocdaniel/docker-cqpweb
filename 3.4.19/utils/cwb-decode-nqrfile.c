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

/**
 * @file
 *
 * See cqp/corpmanag.c for the file format that this utility decodes.
 *
 * Note, some of the code is repeated across CQP's load-file functions
 * and here. In the long term, we'll aim to remove this duplication.
 * TODO!
 */

#include <stdio.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <unistd.h>
#include <stdlib.h>
#include <string.h>

#include "../cl/cl.h"
#include "../cl/cwb-globals.h"

/**
 * Gets the size of the file.
 * The ponderous name is to avoid clashing with a function in the CL.
 *
 * @param file_handle  File handle.
 * @return             The size of the file, from stat().
 */
int
file_length_from_handle(FILE *file_handle)
{
  struct stat stat_buf;
  if(fstat(fileno(file_handle), &stat_buf) == EOF)
    return EOF;
  else
    return stat_buf.st_size;
}

/**
 * Reads a subcorpus file and prints information about it to STDOUT.
 *
 * "Subcorpus file" here means (a) it begins with the subcorpus magic number;
 * (b) then there is a "registry" area terminated by one or more zero bytes;
 * (c) then there may be the size of the subcorpus;
 * (d) then there are a whole load of
 * start-end range integer pairs, to the end of the file.
 *
 * The registry is printed iff print_header. The start-end pairs
 * are printed on tab-delimited lines, one line per pair.
 *
 * @param src           File pointer.
 * @param print_header  Boolean: controls whether a "registry" header
 *                      in the subcorpus file gets printed or not
 * @return              Boolean: true for all OK, false for problem.
 */
int
nqrfile_print_info(FILE *src, int print_header)
{
  char  *field;
  char  *p;

  struct range_t {
    int start;
    int end;
  };
  /* thus we can use a pointer to the above as an array fo cpos pairs */
  struct range_t *range_array;

  char *registry, *o_name;
  int size;
  int magic;

  int len, j;

  len = file_length_from_handle(src);

  if (len <= 0) {
    perror("ERROR: File length of subcorpus is <= 0");
    return 0;
  }

  /* the subcorpus is treated as a byte array */
  field = (char *)cl_malloc(len);

  /* read the subcorpus */

  if (len != fread(field, 1, len, src)) {
    fprintf(stderr, "Read error while reading in data from subcorpus file\n");
    return 0;
  }

  magic = *((int *)field);

  if (magic == CWB_SUBCORPMAGIC_ORIG || magic == CWB_SUBCORPMAGIC_NEW) {
    /* set p to point to first byte after end of the magic number */
    p = ((char *)field) + sizeof(int);

    registry = p;

    /* registry name ends in null; scroll plast, plus skip the '\0' character */
    while (*p)
      p++;
    p++;

    o_name = (char *)p;

    /* advance p to the end of the 2nd string, plus skip the '\0' character */
    while (*p)
      p++;
    p++;

    /* the length of the head of the file is divisible by 4 -- advance p over the additional '\0' characters */
    while ((p - field) % 4)
      p++;

    if (magic == CWB_SUBCORPMAGIC_ORIG)
      size = (len - (p - field)) / (2 * sizeof(int));
    else {
      memcpy(&size, p, sizeof(int));
      p += sizeof(int);
      fprintf(stderr, "Note: new subcorpus format\n");
    }

    if (print_header) {
      printf("REGISTRY %s\n", registry);
      printf("O_NAME   %s\n", o_name);
      printf("SIZE     %d\n", size);
    }

    range_array = (struct range_t *) p;

    for (j = 0; j < size; j++)
      printf("%d\t%d\n", range_array[j].start, range_array[j].end);
  }
  else {
    fprintf(stderr, "Error: Magic number incorrect in subcorpus file!\n");
    return 0;
  }

  cl_free(field);
  p = NULL;

  return 1;
}




/**
 * Main function for cwb-decode-nqrfile.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  int i;
  FILE *src;
  int header_required = 1;

  cl_startup();

  for (i = 1; i < argc; i++) {
    /* TODO would be useful to change this to +/-H (in v4.0), so that -h is "available" for
     * a _usage function, if we ever decide we need one.
     */
    if (CL_STREQ(argv[i], "-h"))
      header_required = 0;

    else if (CL_STREQ(argv[i], "+h"))
      header_required = 1;

    else {
      /* treat arg as sc-name */
      if (CL_STREQ(argv[i], "-")) {
        if (!nqrfile_print_info(stdin, header_required))
          exit(1);
      }
      else {
        if (!(src = fopen(argv[i], "rb"))) {
          perror(argv[i]);
          exit(1);
        }
        if (!nqrfile_print_info(src, header_required))
          exit(1);
        fclose(src);
      }
    }
  }
  return 0;
}
