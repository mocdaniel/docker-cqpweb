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

#include <stdio.h>
#include <stdlib.h>

#include <sys/types.h>

#include "../cl/cl.h"
#include "../cl/cwb-globals.h"
/* byte order handling taken from Corpus Library */
#include "../cl/endian.h"

/* LONG and SHORT modes removed. Mon Mar 23 19:25:35 MET 1998 (evert) */


int little_endian = 0;  /* boolean; CWB default format is 4-byte big-endian = network */

int buf[CL_MAX_LINE_LENGTH];

/**
 * Reads one integer at a time from a stream and prints a decimal representation
 * of it on STDOUT; strings representing ints are split by newlines.
 *
 * @param stream  The file handle.
 */
void
itoa_process_stream(FILE *stream)
{
  int N, k, i;

  do {
    /* currently only works on systems with 32bit ints.
       should really be fixed some time */
    N = fread(&buf[0], sizeof(int), CL_MAX_LINE_LENGTH, stream);

    for (k = 0; k < N; k++) {
      i = ntohl(buf[k]);        /* convert from CWB to internal format */
      if (little_endian)
        i = cl_bswap32(i);      /* explicit conversion */
      fprintf(stdout, "%d\n", i);
    }
  } while (N == CL_MAX_LINE_LENGTH);
}



/**
 * Main function for cwb-itoa.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  FILE *src;
  int i;
  char *progname = argv[0];

  cl_startup();

  src = stdin;  /* initialisation removed from declaration for Gnuwin32 compatibility */

  for (i = 1; i < argc; i++) {
    if (argv[i][0] == '-') {
      switch (argv[i][1]) {
      case 'n':
        little_endian = 0;
        break;
      case 'l':
        little_endian = 1;
        break;
      case 'h':
      default:
        fprintf(stderr, "\n");
        fprintf(stderr, "Usage:  %s [options] [file]\n", argv[0]);
        fprintf(stderr, "Reads 32bit integers in network format from CWB binary data file <file>\n");
        fprintf(stderr, "or from standard input and prints the values as ASCII numbers on standard\n");
        fprintf(stderr, "output (one number per line).\n");
        fprintf(stderr, "Options:\n");
        fprintf(stderr, "  -n  read integers in network format [default]\n");
        fprintf(stderr, "  -l  read integers in little endian format\n");
        fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");
        exit(1);
      }
    }
    else if ((src = fopen(argv[i], "rb")) == NULL) {
      fprintf(stderr, "%s: Couldn't open %s\n", progname, argv[i]);
      exit(1);
    }
  }

  /* now process either input file or stdin */
  itoa_process_stream(src);
  return 0;
}
