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

/**
 * boolean: is the byte-order little-endian?
 *
 * CWB default format is 4-byte big-endian = network
 */
int little_endian = 0;


/**
 * Reads from a stream one integer-representing string per line,
 * and writes the corresponding integer to STDOUT.
 *
  * @param stream  The file handle.
 */
void
atoi_process_stream(FILE *stream)
{
  char buf[CL_MAX_LINE_LENGTH];
  int i;

  while(fgets(buf, CL_MAX_LINE_LENGTH, stream)) {
    i = htonl(atoi(buf));
    if (little_endian)
      i = cl_bswap32(i);        /* explicit conversion */
    fwrite(&i, 4, 1, stdout);   /* always write 4 bytes ! */
  }
}



/**
 * Main function for cwb-atoi.
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

  /* default case: we are reading from stdin */
  src = stdin;

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
        fprintf(stderr, "Reads one integer per line from ASCII file <file> or from standard input\n");
        fprintf(stderr, "and writes values to standard output as 32-bit integers in network format\n");
        fprintf(stderr, "(the format used by CWB binary data files).\n");
        fprintf(stderr, "Options:\n");
        fprintf(stderr, "  -n  convert to network format [default]\n");
        fprintf(stderr, "  -l  convert to little endian format\n");
        fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");
        exit(1);
      }
    }
    else if (!(src = fopen(argv[i], "rb"))) {
      fprintf(stderr, "%s: Couldn't open %s\n", progname, argv[i]);
      exit(1);
    }
  }

  /* now process either input file or stdin */
  atoi_process_stream(src);
  return 0;
}
