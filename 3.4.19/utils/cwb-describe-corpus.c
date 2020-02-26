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


#include "../cl/cl.h"
#include "../cl/cwb-globals.h"
#include "../cl/corpus.h"
#include "../cl/attributes.h"
#include "../cl/ui-helpers.h"

/** String set to the name of this program. */
char *progname = NULL;

/**
 * Prints a message describing how to use the program to STDERR and then exits.
 */
void
describecorpus_usage(void)
{
  fprintf(stderr, "\n");
  fprintf(stderr, "Usage:  %s [flags] <corpus> [<corpus> ...] \n", progname);
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "  -r <dir>  use registry directory <dir>\n");
  fprintf(stderr, "  -s        show statistics (attribute & lexicon size)\n");
  fprintf(stderr, "  -d        show details (about component files)\n");
  fprintf(stderr, "  -h        this help page\n");
  fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");
  exit(2);
}

/**
 * Prints the names of attributes in a corpus to STDOUT.
 *
 * Only one type of attribute is analysed.
 *
 * @param corpus  The corpus to analyse.
 * @param type    The type of attribute to show. This should be
 *                one of the constants in cl.h (ATT_POS etc.)
 */
void
describecorpus_show_attribute_names (Corpus *corpus, int type)
{
  Attribute *a;

  ilist_start(64, 16, 6); /* indent by 6 chars */
  for (a = corpus->attributes; a; a = a->any.next) {
    if (a->any.type == type) {
      ilist_print_item(a->any.name);
    }
  }
  /* don't ilist_end() because that might print "\r" */
  printf("\n\n");
}

/**
 * Prints basic information about a corpus to STDOUT.
 *
 * @param corpus                The corpus to report on.
 * @param with_attribute_names  Boolean: iff true, the counts of each type of attribute
 *                              are followed by a list of attribute names.
 *
 */
void
describecorpus_show_basic_info (Corpus *corpus, int with_attribute_names)
{
  Attribute *word, *a;
  int p_atts = 0, s_atts = 0, a_atts = 0;
  int size;
  char *colon = (with_attribute_names) ? ":" : "";

  printf("description:    %s\n", corpus->name);
  printf("registry file:  %s/%s\n", corpus->registry_dir, corpus->registry_name);
  printf("home directory: %s/\n", corpus->path);
  printf("info file:      %s\n", (corpus->info_file) ? corpus->info_file : "(none)");
  printf("encoding:       %s\n", cl_charset_name(corpus->charset));
  if ((word = cl_new_attribute(corpus, "word", ATT_POS)) == NULL) {
    fprintf(stderr, "ERROR: 'word' attribute is missing. Aborted.\n");
    exit(1);
  }
  size = cl_max_cpos(word);
  printf("size (tokens):  ");
  if (size >= 0)
    printf("%d\n", size);
  else
    printf("ERROR\n");
  printf("\n");

  for (a = corpus->attributes; a; a = a->any.next) {
    switch(a->any.type) {
    case ATT_POS:   p_atts++; break;
    case ATT_STRUC: s_atts++; break;
    case ATT_ALIGN: a_atts++; break;
    default: break;
    }
  }
  printf("%3d positional attributes%s\n", p_atts, colon);
  if (with_attribute_names)
    describecorpus_show_attribute_names(corpus, ATT_POS);
  printf("%3d structural attributes%s\n", s_atts, colon);
  if (with_attribute_names)
    describecorpus_show_attribute_names(corpus, ATT_STRUC);
  printf("%3d alignment  attributes%s\n", a_atts, colon);
  if (with_attribute_names)
    describecorpus_show_attribute_names(corpus, ATT_ALIGN);
  printf("\n");
}

/**
 * Prints statistical information about a corpus to STDOUT.
 *
 * Each corpus attribute gets info printed about it:
 * tokens and types for a P-attribute, number of instances
 * of regions for an S-attribute, number of alignment
 * blocks for an A-attribute.
 *
 * @param corpus  The corpus to analyse.
 */
void
describecorpus_show_statistics (Corpus *corpus)
{
  Attribute *a;
  int tokens, types, regions, blocks;

  for (a = corpus->attributes; a; a = a->any.next) {
    switch(a->any.type) {

    case ATT_POS:
      printf("p-ATT %-16s ", a->any.name);
      tokens = cl_max_cpos(a);
      types = cl_max_id(a);
      if ((tokens > 0) && (types > 0))
        printf("%10d tokens, %8d types", tokens, types);
      else
        printf("           NO DATA");
      break;

    case ATT_STRUC:
      printf("s-ATT %-16s ", a->any.name);
      regions = cl_max_struc(a);
      if (regions >= 0) {
        printf("%10d regions", regions);
        if (cl_struc_values(a))
          printf(" (with annotations)");
      }
      else
        printf("           NO DATA");
      break;

    case ATT_ALIGN:
      printf("a-ATT %-16s ", a->any.name);
      blocks = cl_max_alg(a);
      if (blocks >= 0) {
        printf("%10d alignment blocks", blocks);
        if (cl_has_extended_alignment(a))
          printf(" (extended)");
      }
      else
        printf("           NO DATA");
      break;

    default:
      printf("???   %-16s (unknown attribute type)", a->any.name);
      break;
    }

    printf("\n");
  }

  printf("\n");
}





/**
 * Main function for cwb-describe-corpus.
 *
 * Prints information about an indexed corpus to STDOUT.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  int i;
  Corpus *corpus;

  int c;
  extern char *optarg;
  extern int optind;

  int show_stats = 0;
  int show_details = 0;

  char *registry = NULL;

  cl_startup();
  progname = argv[0];

  while ((c = getopt(argc, argv, "+r:sdh")) != EOF) {
    switch(c) {

      /* -r <dir>: change registry directory */
    case 'r':
      if (registry == NULL)
        registry = optarg;
      else {
        fprintf(stderr, "%s: -r option used twice\n", progname);
        exit(2);
      }
      break;

      /* -s: show statistics */
    case 's':
      show_stats++;
      break;

      /* -d: show details */
    case 'd':
      show_details++;
      break;

      /* -h: help page */
    case 'h':
    default:
      describecorpus_usage();
      break;
    }
  }

  if (optind >= argc) {
    fprintf(stderr, "Missing argument, try \"%s -h\" for more information.\n", progname);
    exit(1);
  }

  for (i = optind; i < argc; i++) {
    if (!(corpus = cl_new_corpus(registry, argv[i]))) {
      fprintf(stderr, "ERROR. Can't access corpus %s !\n", argv[i]);
      exit(1);
    }

    printf("\n============================================================\n");
    printf("Corpus: %s\n", argv[i]);
    printf("============================================================\n\n");

    describecorpus_show_basic_info(corpus, !(show_stats || show_details));
    /* show attribute names only if no other options are selected */

    if (show_stats)
      describecorpus_show_statistics(corpus);

    if (show_details)
      describe_corpus(corpus);

    cl_delete_corpus(corpus);
  }

  return 0;
}
