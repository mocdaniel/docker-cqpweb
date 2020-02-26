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
#include <string.h>
#include <stdlib.h>
#include <unistd.h>

#include "../cl/cl.h"
#include "../cl/cwb-globals.h"

/** Name of the program (from the shell) */
char *progname;

/**
 * Prints a message describing how to use the program to STDERR and then exits.
 */
void
aligndecode_usage(void)
{
  fprintf(stderr,
          "\n"
          "Usage: %s [options] <CORPUS> -A <att>\n\n"
          "Export list of alignment beads from the given a-attribute in CWB .align format.\n\n"
          "Options:\n"
          "  -r <reg>  use registry directory <reg>\n"
          "  -h        print this usage text.\n\n"
          "Output format:\n"
          "HEADER   <source_corpus> TAB s TAB <target_corpus> TAB s\n"
          "LINES    <source_start> TAB <source_end> TAB <target_start> TAB <target_end>\n\n"
          "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n"
          , progname);
  exit(1);
}


/* *************** *\
 *      MAIN()     *
\* *************** */

/**
 * Main function for cwb-align-decode.
 *
 * Prints .align file header, followed by start and end positions of alignment beads
 * of the specified sentence-level alignment to STDOUT,
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
  char *target_corpus_id = NULL;
  Corpus *corpus = NULL;
  Attribute *att = NULL;

  int start1, end1, start2, end2, n, att_size;

  extern int optind;
  extern char *optarg;
  int c;

  cl_startup();
  progname = argv[0];

  /* ------------------------------------------------- PARSE ARGUMENTS */

  while ((c = getopt(argc, argv, "+r:h")) != EOF) {
    switch (c) {

    /* r: registry directory */
    case 'r':
      if (registry_directory == NULL)
          registry_directory = optarg;
      else {
        fprintf(stderr, "%s: -r option used twice\n", progname);
        exit(2);
      }
      break;

    default:
    case 'h':
      aligndecode_usage();
      break;

    }
  } /* endwhile: options */

  /* expect three arguments: <corpus> -A <attribute> */
  if (argc <= (optind + 2))
    aligndecode_usage();

  /* first argument: corpus id */
  corpus_id = argv[optind++];
  cl_id_toupper(corpus_id);
  if (!(corpus = cl_new_corpus(registry_directory, corpus_id))) {
    fprintf(stderr, "%s: Corpus <%s> not registered in %s\n",
              progname,
              corpus_id,
              registry_directory ? registry_directory : cl_standard_registry());
    exit(1);
  }

  /* second argument: -A */
  if (strcmp(argv[optind++], "-A") != 0)
    aligndecode_usage();

  /* third argument: attribute name */
  attr_name = argv[optind];
  cl_id_tolower(attr_name);
  if (!(att = cl_new_attribute(corpus, attr_name, ATT_ALIGN))) {
    fprintf(stderr, "%s: Can't access a-attribute <%s.%s>\n", progname, corpus_id, attr_name);
    exit(1);
  }

  target_corpus_id = cl_strdup(attr_name);
  cl_id_toupper(target_corpus_id);

  /* print header on STDOUT */
  printf("%s\t%s\t%s\t%s\n", corpus_id, "s", target_corpus_id, "s");

  /* attribute size, i.e. number of regions */
  att_size = cl_max_alg(att);

  /* print all regions on STDOUT */
  for (n = 0; n < att_size; n++) {
    if (!cl_alg2cpos(att, n, &start1, &end1, &start2, &end2)) {
      cl_error("Can't find region boundaries for alignment bead");
      exit(1);
    }
    printf("%d\t%d\t%d\t%d\n", start1, end1, start2, end2);
  }

  /* that was all ...  */
  return 0;
}

