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
#include "../cl/endian.h"
#include "../cl/fileutils.h"

/** The corpus we are working on */
Corpus *corpus;

/** Name of this program */
char *progname = NULL;


/**
 * Checks whether a component has already been created.
 *
 * @param attr  The attribute of the component to check.
 * @param cid   The component ID of the component to check.
 * @return      TRUE iff the component has already been created.
 */
int
component_ok(Attribute *attr, ComponentID cid)
{
  ComponentState state;

  state = component_state(attr, cid);
  if (state == ComponentLoaded || state == ComponentUnloaded)
    return 1;

  if (state != ComponentDefined) {
    fprintf(stderr, "Internal Error: Illegal state %d/component ID %d ???\n", state, cid);
    exit(1);
  }

  return 0;
}


/**
 * Creates a component for the specified attribute.
 *
 * This function will create the component if it doesn't already exist;
 * it aborts on error.
 *
 * @see create_component
 *
 * @param attr  The attribute of the component to create.
 * @param cid   The component ID of the component to create.
 */
void
makeall_make_component(Attribute *attr, ComponentID cid)
{
  int state;

  if (!component_ok(attr, cid)) {
    printf(" + creating %s ... ", cid_name(cid));
    fflush(stdout);
    create_component(attr, cid);

    state = component_state(attr, cid);
    if ( !(state == ComponentLoaded || state == ComponentUnloaded) ) {
      printf("FAILED\n");
      fprintf(stderr, "ERROR. Aborted.\n");
      exit(1);
    }

    printf("OK\n");
  }
}



/**
 * Validates the REVCORP component of the given attribute.
 *
 * This function validates a REVCORP (i.e. an uncompressed index).
 * It assumes that a lexicon, frequencies and (compressed or
 * uncompressed) token stream are available for CL access for the
 * given attribute.
 *
 * @param attr  The attribute whose REVCORP should be checked.
 * @return      True for all OK, false for a problem.
 */
int
validate_revcorp(Attribute *attr)
{
  Component *revcorp = ensure_component(attr, CompRevCorpus, 0);
  int *ptab;                        /* table of index offsets for each lexicon entry */
  int lexsize, corpsize;
  int i, offset, cpos, id;

  printf(" ? validating %s ... ", cid_name(CompRevCorpus));
  fflush(stdout);

  if (!revcorp) {
    printf("FAILED (no data)\n");
    return 0;
  }
  lexsize = cl_max_id(attr);
  corpsize = cl_max_cpos(attr);
  if (lexsize <= 0 || corpsize <= 0) {
    printf("FAILED (corpus access error)\n");
    return 0;
  }
  if (revcorp->size != corpsize) {
    printf("FAILED (wrong size)\n");
    return 0;
  }

  /* init offsets by calculating REVIDX component from token frequencies */
  ptab = (int *)cl_calloc(lexsize, sizeof(int));
  offset = 0;
  for (i = 0; i < lexsize; i++) {
    ptab[i] = offset;
    offset += cl_id2freq(attr, i);
  }

  /* now read token stream, check each token id against REVCORP, and increment its pointer */
  for (cpos = 0; cpos < corpsize; cpos++) {
    id = cl_cpos2id(attr, cpos);
    if ((id < 0) || (id >= lexsize)) {
      printf("FAILED (inconsistency in token stream)\n");
      cl_free(ptab);
      return 0;
    }
    if (ntohl(revcorp->data.data[ptab[id]]) != cpos) {
      printf("FAILED\n");
      cl_free(ptab);
      return 0;
    }
    ptab[id]++;
  }

  /* validate frequencies by comparing final offsets against those calculated from token frequencies */
  offset = 0;
  for (i = 0; i < lexsize; i++) {
    offset += cl_id2freq(attr, i);
    if (ptab[i] != offset) {
      printf("FAILED (token frequencies incorrect)\n");
      cl_free(ptab);
      return 0;
    }
  }

  cl_free(ptab);
  printf("OK\n");
  return 1;
}

/**
 * Create a given component (or all components) for an attribute.
 *
 * @param attr      The attribute to work on.
 * @param cid       If this is CompLast, all components will be created.
 *                  Otherwise, it specifies the single component that will
 *                  be created.
 * @param validate  boolean - if true, validate_revcorp is called to check
 *                  the resulting revcorp.
 */
void
makeall_do_attribute(Attribute *attr, ComponentID cid, int validate)
{
  assert(attr);

  if (cid == CompLast) {
    printf("ATTRIBUTE %s\n", attr->any.name);
    /* automatically create all necessary components */

    /* check whether directory for data files exists (may be misspelt in registry) */
    if (! is_directory(attr->any.path)) {
      fprintf(stderr, "WARNING. I cannot find the data directory of the '%s' attribute.\n", attr->any.name);
      fprintf(stderr, "WARNING  Directory: %s/ \n", attr->any.path);
      fprintf(stderr, "WARNING  Perhaps you misspelt the directory name in the registry file?\n");
    }

    /* lexicon and lexicon offsets must have been created by encode */
    if (! (component_ok(attr, CompLexicon) && component_ok(attr, CompLexiconIdx))) {
      /* if none of the components exists, we assume that the attribute will be created later & skip it */
      if (!component_ok(attr, CompLexicon)      &&
          !component_ok(attr, CompLexiconIdx)   &&
          !component_ok(attr, CompLexiconSrt)   &&
          !component_ok(attr, CompCorpus)       &&
          !component_ok(attr, CompCorpusFreqs)  &&
          !component_ok(attr, CompHuffSeq)      &&
          !component_ok(attr, CompHuffCodes)    &&
          !component_ok(attr, CompHuffSync)     &&
          !component_ok(attr, CompRevCorpus)    &&
          !component_ok(attr, CompRevCorpusIdx) &&
          !component_ok(attr, CompCompRF)       &&
          !component_ok(attr, CompCompRFX)
          )  {
        /* issue a warning message & return */
        printf(" ! attribute not created yet (skipped)\n");
        if (CL_STREQ(attr->any.name, "word"))
          fprintf(stderr, "WARNING. The 'word' attribute must be created before using CQP on this corpus!\n");
        return;
      }
      else {
        fprintf(stderr, "ERROR. Lexicon is missing. You must use the 'encode' tool first!\n");
        exit(1);
      }
    }
    else {
      /* may need to create "alphabetically" sorted lexicon */
      makeall_make_component(attr, CompLexiconSrt);
      printf(" - lexicon      OK\n");
    }

    /* create token frequencies if necessary (must be able to do so if they aren't already there) */
    makeall_make_component(attr, CompCorpusFreqs);
    printf(" - frequencies  OK\n");

    /* check if token sequence has been compressed, otherwise create CompCorpus (if necessary) */
    if (component_ok(attr, CompHuffSeq) && component_ok(attr, CompHuffCodes) && component_ok(attr, CompHuffSync))
      printf(" - token stream OK (COMPRESSED)\n");
    else {
      makeall_make_component(attr, CompCorpus);
      printf(" - token stream OK\n");
    }

    /* same for index (check if compressed, otherwise create if not already there) */
    if (component_ok(attr, CompCompRF) && component_ok(attr, CompCompRFX))
      printf(" - index        OK (COMPRESSED)\n");
    else {
      makeall_make_component(attr, CompRevCorpusIdx);
      if (!component_ok(attr, CompRevCorpus)) { /* need this check to avoid validation of existing revcorp  */
        makeall_make_component(attr, CompRevCorpus);
        if (validate) {
          /* validate the index, i.e. the REVCORP component we just created */
          if (!validate_revcorp(attr)) {
            fprintf(stderr, "ERROR. Validation failed.\n");
            exit(1);
          }
        }
      }
      printf(" - index        OK\n");
    }
  }
  else {
    /* cid != CompLast; so, create requested component only */
    printf("Processing component %s of ATTRIBUTE %s\n", cid_name(cid), attr->any.name);
    makeall_make_component(attr, cid);
    if (validate && cid == CompRevCorpus) {
      /* validates even if REVCORP already existed -> useful trick for validating later */
      if (!validate_revcorp(attr)) {
        fprintf(stderr, "ERROR. Validation failed.\n");
        exit(1);
      }
    }
  }

}

/**
 * Prints a usage message and exits the program.
 */
void
makeall_usage(void)
{
  fprintf(stderr, "\n");
  fprintf(stderr, "Usage:  %s [options] <corpus> [<attribute> ...] \n", progname);
  fprintf(stderr, "\n");
  fprintf(stderr, "Creates a lexicon and index for each p-attribute of an encoded CWB corpus.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "  -D        debug mode\n");
  fprintf(stderr, "  -r <dir>  use registry directory <dir>\n");
  fprintf(stderr, "  -c <comp> create component <comp> only\n");
  fprintf(stderr, "  -P <att>  work on attribute <att> [default: ALL attributes]\n");
  fprintf(stderr, "  -M <size> limit memory usage to approx. <size> MBytes\n");
  fprintf(stderr, "  -V        validate index after creating it\n");
  fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");
  exit(2);
}
/* TODO  it is a but confusing that there is both a -P option for attributes, AND you can list attributes
 * after the corpus name. (Or is there a difference between these two ways of specifying attributes? Either
 * way, it needs to be documented in the usage message, and in the manfile too.
 * NB the normally parallel huffcode does not have attributes after the corpus!
 */


/* *************** *
 *      MAIN()     *
 * *************** */

/**
 * Main function for cwb-makeall.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  char *attr_name = NULL;
  Attribute *attribute;

  char *registry_directory = NULL;
  char *corpus_id = NULL;

  extern int optind;
  extern char *optarg;
  int c;

  int validate = 0;

  char *component = NULL;

  ComponentID cid;
  int i = 0;

  cl_startup();
  progname = argv[0];

  /* ------------------------------------------------- PARSE ARGUMENTS */

  while ((c = getopt(argc, argv, "+r:c:P:hDM:V")) != EOF) {
    switch (c) {

    /* r: registry directory */
    case 'r':
      if (!registry_directory)
        registry_directory = optarg;
      else {
        fprintf(stderr, "%s: -r option used twice\n", progname);
        exit(2);
      }
      break;

    case 'P':
      if (!attr_name)
        attr_name = optarg;
      else {
        fprintf(stderr, "%s: -P option used twice\n", progname);
        exit(2);
      }
      break;

    case 'c':
      if (!component)
        component = optarg;
      else {
        fprintf(stderr, "%s: -c option used twice\n", progname);
        exit(2);
      }
      break;

    case 'D':
      cl_set_debug_level(1);
      break;

    case 'M':
      i = atoi(optarg);
      cl_set_memory_limit(i);
      break;

    case 'V':
      validate++;
      break;

    case 'h':
    default:
      makeall_usage();
    }
  }

  if (optind >= argc) {
    fprintf(stderr, "Missing argument, try \"%s -h\" for more information.\n", progname);
    exit(1);
  }

  /* first argument: corpus id */
  corpus_id = argv[optind++];

  if (component) {
    cid = component_id(component);
    if (cid == CompLast) {
      fprintf(stderr, "Illegal component name: ``%s''\n", component);
      exit(1);
    }
  }
  else
    cid = CompLast;

  if (!(corpus = cl_new_corpus(registry_directory, corpus_id))) {
    fprintf(stderr, "Corpus %s not found in registry %s . Aborted.\n",
            corpus_id,
            registry_directory ? registry_directory : cl_standard_registry());
    exit(1);
  }

  printf("=== Makeall: processing corpus %s ===\n", corpus_id);
  printf("Registry directory: %s\n", corpus->registry_dir);

  if (optind < argc) {
    /* process each specified atttribute (at the end of the invocation) */
        /* TODO why do we not need to drop components here, when the for-loop below needs to?? */
    for (i = optind; i < argc; i++) {
      if ( NULL != (attribute = cl_new_attribute(corpus, argv[i], ATT_POS)) )
        makeall_do_attribute(attribute, cid, validate);
      else {
        fprintf(stderr, "p-attribute %s.%s not defined. Aborted.\n", corpus_id, argv[i]);
        exit(1);
      }
    }
  }
  else if (attr_name != NULL) {
    /* process a specified attribute (via the -P option) */
    if ( NULL != (attribute = cl_new_attribute(corpus, attr_name, ATT_POS)) )
      makeall_do_attribute(attribute, cid, validate);
    else {
      fprintf(stderr, "p-attribute %s.%s not defined. Aborted.\n", corpus_id, attr_name);
      exit(1);
    }
  }
  else {
    /* process each p-attribute of the corpus in turn */
    for (attribute = corpus->attributes; attribute; attribute = attribute->any.next)
      if (attribute->type == ATT_POS) {
        ComponentID my_cid;
        makeall_do_attribute(attribute, cid, validate);
        /* now destoy all components; this makes the attribute unusable,
           but it is currently the only way to free allocated and memory-mapped data */
        for (my_cid = CompDirectory; my_cid < CompLast; my_cid++)
          drop_component(attribute, my_cid);
          /* ordering gleaned from attributes.h */
      }
  }

  printf("========================================\n");
  exit(0);
}




