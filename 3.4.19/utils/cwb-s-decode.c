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
#include <string.h>
#include <unistd.h>


#include "../cl/cl.h"
#include "../cl/cwb-globals.h"



/** Name of the program (from the shell) */
char *progname;

/**
 * Prints a message describing how to use the program to STDERR and then exits.
 */
void
sdecode_usage(void)
{
  fprintf(stderr,
          "\n"
          "Usage: %s [options] corpus-id -S <att>\n\n"
          "Outputs a list of the given s-attribute, with begin and end positions\n\n"
          "Options:\n"
          "  -r <reg>  use registry directory <reg>\n"
          "  -n        do not show corpus positions\n"
          "  -v        do not show annotated values\n"
          "  -h        print this usage text.\n"
          "Output line format:\n"
          "   <region_start> TAB <region_end> [ TAB <annotation> ]\n"
          "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n"
          , progname);
  exit(1);
}




/**
 * Main function for cwb-s-decode.
 *
 * Prints information about each region in a given s-attribute in a
 * specified corpus to STDOUT, optionally with their annotation values.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  char *registry_directory = NULL;
  char *corpus_id = NULL;
  char *attr_name = NULL;
  Corpus *corpus = NULL;
  Attribute *att = NULL;
  int show_values = 1;
  int show_regions = 1;

  int has_values, att_size, n, start, end;
  char *annot;

  extern int optind;
  extern char *optarg;
  int c;

  cl_startup();
  progname = argv[0];

  /* ------------------------------------------------- PARSE ARGUMENTS */

  while (EOF != (c = getopt(argc, argv, "+r:nvh"))) {
    switch (c) {

    /* r: registry directory */
    case 'r':
      if (registry_directory == NULL) registry_directory = optarg;
      else {
        fprintf(stderr, "%s: -r option used twice\n", progname);
        exit(2);
      }
      break;

    /* n: do not show corpus positions */
    case 'n':
      show_regions = 0;
      break;

    /* v: do not show annotated values */
    case 'v':
      show_values = 0;
      break;

    default:
    case 'h':
      sdecode_usage();
      break;
    }
  } /* endwhile: options */

  /* expect three arguments: <corpus> -S <attribute> */
  if (argc <= (optind + 2))
    sdecode_usage();

  if (!show_regions && !show_values) {
    fprintf(stderr, "Error: options -n and -v cannot be combined (would print nothing)\n");
    exit(1);
  }

  /* first argument: corpus id */
  corpus_id = argv[optind++];
  if (!(corpus = cl_new_corpus(registry_directory, corpus_id))) {
    fprintf(stderr, "%s: Corpus <%s> not registered in %s\n",
            progname,
            corpus_id,
            registry_directory ? registry_directory : cl_standard_registry());
    exit(1);
  }

  /* second argument: -S */
  if (strcmp(argv[optind++], "-S") != 0)
    sdecode_usage();

  /* third argument: attribute name */
  attr_name = argv[optind];
  if (!(att = cl_new_attribute(corpus, attr_name, ATT_STRUC))) {
    fprintf(stderr, "%s: Can't access s-attribute <%s.%s>\n", progname, corpus_id, attr_name);
    exit(1);
  }

  /* check if attribute has annotations */
  has_values = cl_struc_values(att);
  if (!has_values)
    show_values = 0;
  if (!show_regions && !has_values) {
    fprintf(stderr, "Error: option -n can only be used if s-attribute has annotated values\n");
    exit(1);
  }

  /* attribute size, i.e. number of regions */
  att_size = cl_max_struc(att);

  /* print all regions on STDOUT */
  for (n = 0; n < att_size; n++) {
    if (!cl_struc2cpos(att, n, &start, &end)) {
      cl_error("Can't find region boundaries");
      exit(1);
    }
    if (show_regions) {
      printf("%d\t%d", start, end);
      if (show_values)
        printf("\t");
    }
    if (show_values) {
      if (!(annot = cl_struc2str(att, n)))
        printf("<no annotation>");
      else
        printf("%s", annot);
    }
    printf("\n");
  }

  /* that was all ...  */
  return 0;
}

