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
#include <unistd.h>

#include "../cl/cl.h"
#include "../cl/cwb-globals.h"
#include "../cl/storage.h"           /* for NwriteInt() function */
#include "../cl/attributes.h"        /* for component_full_name() function */

/* global variables */

/** Name of the program (from the shell) */
char *progname = "";

int compatibility = 0;                /**< create .alg file for backward compatibility ? */
char *registry_dir = NULL;            /**< CL registry directory */
int reverse = 0;                      /**< encode inverse alignment? */
char *data_dir = NULL;                /**< where to store encoded alignment attribute */
int data_dir_from_corpus = 0;         /**< determine data directory from registry entry? */
int verbose = 0;                      /**< print some information about what files are created */


/**
 * Prints a message describing how to use the program to STDERR and then exits.
 */
void
alignencode_usage(void)
{
  fprintf(stderr, "\n");
  fprintf(stderr, "Usage: %s [options] <alignment_file>\n\n", progname);
  fprintf(stderr, "\n");
  fprintf(stderr, "Adds an alignment attribute to an existing CWB corpus\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "  -d <dir> write data file(s) to directory <dir>\n");
  fprintf(stderr, "  -D       write files to corpus data directory\n");
  fprintf(stderr, "  -C       compatibility mode (creates .alg file)\n");
  fprintf(stderr, "  -R       reverse alignment (target -> source)\n");
  fprintf(stderr, "           [only works if there are no crossing beads]\n");
  fprintf(stderr, "  -r <reg> use registry directory <reg>\n");
  fprintf(stderr, "  -v       verbose mode\n");
  fprintf(stderr, "  -h       this help page\n\n");
  fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");
  exit(1);
}
/* note: must specify either -d or -D option */



/**
 * Parses the program's commandline arguments.
 *
 * Usage:
 *
 * optindex = alignencode_parse_args(argc, argv, required_arguments);
 *
 * @param ac        The program's argc
 * @param av        The program's argv
 * @param min_args  Minimum number of arguments to be parsed.
 * @return          The value of optind after parsing,
 *                  ie the index of the first argument in argv[]
 */
int
alignencode_parse_args(int ac, char *av[], int min_args)
{
  extern int optind;                  /* getopt() interface */
  extern char *optarg;                /* getopt() interface */
  int c;

  while ((c = getopt(ac, av, "hd:DCRr:v")) != EOF)
    switch (c) {
      /* -d: data directory */
    case 'd':
      if (data_dir == NULL)
        data_dir = optarg;
      else {
        fprintf(stderr, "%s: -d option used twice\n", progname);
        exit(2);
      }
      break;
      /* -D: use data directory of source corpus */
    case 'D':
      data_dir_from_corpus = 1;
      break;
      /* -C: compatibility mode */
    case 'C':
      compatibility = 1;
      break;
      /* -R: reverse alignment */
    case 'R':
      reverse = 1;
      break;
      /* -r: registry directory */
    case 'r':
      if (registry_dir == NULL)
        registry_dir = optarg;
      else {
        fprintf(stderr, "%s: -r option used twice\n", progname);
        exit(2);
      }
      break;
      /* -v: verbose */
    case 'v':
      verbose = 1;
      break;
      /* -h : help page = usage */
    case 'h':
      /* unknown option: print usage */
    default:
      alignencode_usage();
      break;
    }

  if (ac - optind != min_args)
    alignencode_usage();                /* no optional arguments in this case */

  if ((data_dir == NULL) && (! data_dir_from_corpus)) {
    fprintf(stderr, "%s: either -d or -D must be specified\n", progname);
    fprintf(stderr, "Type \"%s -h\" for more information.\n", progname);
    exit(1);
  }

  if ((data_dir != NULL) && data_dir_from_corpus) {
    fprintf(stderr, "%s: -d and -D flags cannot be used at the same time\n", progname);
    fprintf(stderr, "Type \"%s -h\" for more information.\n", progname);
    exit(1);
  }

  return(optind);                /* return index of first argument in argv[] */
}




/* *************** *\
 *      MAIN()     *
\* *************** */

/**
 * Main function for cwb-align-encode.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char *argv[])
{
  int argindex;                         /* index of first argument in argv[] */

  char *align_name = NULL;              /* name of the .align file */
  FILE *af = NULL;                      /* alignment file handle */
  char alx_name[CL_MAX_LINE_LENGTH];    /* full pathname of .alx file */
  char alg_name[CL_MAX_LINE_LENGTH];    /* full pathname of optional .alg file */
  FILE *alx=NULL, *alg=NULL;            /* file handles for .alx and optional .alg file */

  char line[CL_MAX_LINE_LENGTH];        /* one line of input from <infile> */

  char corpus1_name[CL_MAX_FILENAME_LENGTH];
  char corpus2_name[CL_MAX_FILENAME_LENGTH];
  char s1_name[CL_MAX_FILENAME_LENGTH];
  char s2_name[CL_MAX_FILENAME_LENGTH];
  Corpus *corpus1, *corpus2;            /* corpus handles */
  Attribute *w1, *w2;                   /* attribute handles for 'word' attributes; used to determine corpus size */
  int size1, size2;                     /* size of source & target corpus */

  Corpus *source_corpus;                /* encode alignment in this corpus (depends on -R flag, important for -D option) */
  char *source_corpus_name;             /* just for error messages */
  char *attribute_name;                 /* name of alignment attribute (depends on -R flag, must be lowercase) */

  int f1,l1,f2,l2;                      /* alignment regions */
  int current1, current2;
  int mark, n_0_1, n_1_0;

  cl_startup();
  progname = argv[0];

  /* parse command line and read arguments */
  argindex = alignencode_parse_args(argc, argv, 1);
  align_name = argv[argindex];

  /* open alignment file and parse header; .gz files are automatically decompressed */
  af = cl_open_stream(align_name, CL_STREAM_READ, CL_STREAM_MAGIC);
  if (af == NULL) {
    perror(align_name);
    fprintf(stderr, "%s: can't read file %s\n", progname, align_name);
    exit(1);
  }

  /* read header = first line */
  fgets(line, CL_MAX_LINE_LENGTH, af);
  if (4 != sscanf(line, "%s %s %s %s", corpus1_name, s1_name, corpus2_name, s2_name)) {
    fprintf(stderr, "%s: %s not in .align format\n", progname, align_name);
    fprintf(stderr, "wrong header: %s", line);
    exit(1);
  }
  if (verbose) {
    if (reverse)
      printf("Encoding alignment for [%s, %s] from file %s\n", corpus2_name, corpus1_name, align_name);
    else
      printf("Encoding alignment for [%s, %s] from file %s\n", corpus1_name, corpus2_name, align_name);
  }

  /* open corpora and determine their sizes (for validity checks and compatibility mode) */
  if (NULL == (corpus1 = cl_new_corpus(registry_dir, corpus1_name))) {
    fprintf(stderr, "%s: can't open corpus %s\n", progname, corpus1_name);
    exit(1);
  }
  if (NULL == (corpus2 = cl_new_corpus(registry_dir, corpus2_name))) {
    fprintf(stderr, "%s: can't open corpus %s\n", progname, corpus2_name);
    exit(1);
  }
  if (NULL == (w1 = cl_new_attribute(corpus1, "word", ATT_POS))) {
    fprintf(stderr, "%s: can't open p-attribute %s.word\n", progname, corpus1_name);
    exit(1);
  }
  if (NULL == (w2 = cl_new_attribute(corpus2, "word", ATT_POS))) {
    fprintf(stderr, "%s: can't open p-attribute %s.word\n", progname, corpus2_name);
    exit(1);
  }

  size1 = cl_max_cpos(w1);
  if (size1 <= 0) {
    fprintf(stderr, "%s: data access error (%s.word)\n", progname, corpus1_name);
    exit(1);
  }
  size2 = cl_max_cpos(w2);
  if (size2 <= 0) {
    fprintf(stderr, "%s: data access error (%s.word)\n", progname, corpus2_name);
    exit(1);
  }

  /* now work out the actual source corpus and the alignment attribute name (depending on -R flag) */
  source_corpus = (reverse) ? corpus2 : corpus1;
  source_corpus_name = (reverse) ? corpus2_name : corpus1_name;
  attribute_name = cl_strdup((reverse) ? corpus1_name : corpus2_name);
  cl_id_tolower(attribute_name); /* fold attribute name to lowercase */

  /* with -D option, determine data file name(s) from actual source corpus;
     otherwise use directory specified with -d and the usual naming conventions */
  if (data_dir_from_corpus) {
    Attribute *alignment = cl_new_attribute(source_corpus, attribute_name, ATT_ALIGN);
    char *comp_pathname;

    if (alignment == NULL) {
      fprintf(stderr, "%s: alignment attribute %s.%s not declared in registry file\n",
              progname, source_corpus_name, attribute_name);
      exit(1);
    }
    comp_pathname = component_full_name(alignment, CompXAlignData, NULL);
    if (comp_pathname == NULL) {
      fprintf(stderr, "%s: can't determine pathname for .alx file (internal error)\n", progname);
      exit(1);
    }
    strcpy(alx_name, comp_pathname);
    /* need to strcpy because component_full_name() returns pointer to internal buffer */
    if (compatibility) {
      comp_pathname = component_full_name(alignment, CompAlignData, NULL);
      if (comp_pathname == NULL) {
        fprintf(stderr, "%s: can't determine pathname for .alg file (internal error)\n", progname);
        exit(1);
      }
      strcpy(alg_name, comp_pathname);
    }
  }
  else {
    sprintf(alx_name, "%s" SUBDIR_SEP_STRING "%s.alx", data_dir, attribute_name);
    if (compatibility)
      sprintf(alg_name, "%s" SUBDIR_SEP_STRING "%s.alg", data_dir, attribute_name);
  }

  /* now open output file(s) */
  alx = fopen(alx_name, "wb");
  if (alx == NULL) {
    perror(alx_name);
    fprintf(stderr, "%s: can't write file %s\n", progname, alx_name);
    exit(1);
  }
  if (verbose)
    printf("Writing file %s ...\n", alx_name);

  if (compatibility) {
    alg = fopen(alg_name, "wb");
    if (alg == NULL) {
      perror(alg_name);
      fprintf(stderr, "%s: can't write file %s\n", progname, alg_name);
      exit(1);
    }

    if (verbose)
      printf("Writing file %s ...\n", alg_name);
  }

  /* main encoding loop */
  f1 = f2 = l1 = l2 = 0;
  mark = -1;                        /* check that regions occur in ascending order */
  current1 = current2 = -1;         /* for compatibility mode */
  n_0_1 = n_1_0 = 0;                /* number of 0:1 and 1:0 alignments, which are skipped */
  while (! feof(af)) {
    if (NULL == fgets(line, CL_MAX_LINE_LENGTH, af))
      break;                        /* end of file (or read error, which we choose to ignore) */
    if (4 != sscanf(line, "%d %d %d %d", &f1, &l1, &f2, &l2)) {
      fprintf(stderr, "%s: input format error: %s", progname, line);
      exit(1);
    }

    /* skip 0:1 and 1:0 alignments */
    if (l1 < f1) {
      n_0_1++; continue;
    }
    if (l2 < f2) {
      n_1_0++; continue;
    }

    /* check that source regions are non-overlapping and in ascending order */
    if (((reverse) ? f2 : f1) <= mark) {
      fprintf(stderr, "%s: source regions of alignment must be in ascending order\n", progname);
      fprintf(stderr, "Last region was [*, %d]; current is [%d, %d].\n", mark, f1, l1);
      fprintf(stderr, "Aborted.\n");
      exit(1);
    }
    mark = (reverse) ? l2 : l1;

    /* write alignment region to .alx file */
    if (reverse) {
      NwriteInt(f2, alx); NwriteInt(l2, alx);
      NwriteInt(f1, alx); NwriteInt(l1, alx);
    }
    else {
      NwriteInt(f1, alx); NwriteInt(l1, alx);
      NwriteInt(f2, alx); NwriteInt(l2, alx);
    }

    if (compatibility) {
      /* source and target regions of .alg file must be contiguous; store start points only;
       * hence we must collapse crossing alignments into one larger region (I know that's bullshit) */
      if ((f1 > current1) && (f2 > current2)) {
        if (reverse) {
          NwriteInt(f2, alg); NwriteInt(f1, alg);
        }
        else {
          NwriteInt(f1, alg); NwriteInt(f2, alg);
        }
        current1 = f1;
        current2 = f2;
      }
    }
  }
  if (compatibility) {
    if (reverse) {
      NwriteInt(size2, alg); NwriteInt(size1, alg); /* end of corpus alignment point*/
    }
    else {
      NwriteInt(size1, alg); NwriteInt(size2, alg); /* end of corpus alignment point*/
    }
  }

  if (verbose) {
    printf("I skipped %d 0:1 alignments and %d 1:0 alignments.\n", n_0_1, n_1_0);
  }

  /* that's it; close file handles */
  fclose(alx);
  if (compatibility)
    fclose(alg);

  cl_close_stream(af);

  return 0;
}



