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

#include <math.h>

#include "../cl/cl.h"
#include "../cl/cwb-globals.h"
#include "../cl/corpus.h"
#include "../cl/attributes.h"
#include "../cl/storage.h"
#include "../cl/bitio.h"
#include "../cl/compression.h"

/* doesn't seem to exist outside Solaris, so we define it here */
//#define log2(x) (log(x)/log(2.0))
/* TODO actually log2 is now a math.h function in C99 and in POSIX... */

/* ---------------------------------------------------------------------- */

/** Name of the program */
char *progname = NULL;

/** CWB id of the corpus we are working on */
char *corpus_id = NULL;
/** Record for the corpus we are working on */
Corpus *corpus;

void compressrdx_usage(char *msg, int error_code);
void compressrdx_cleanup(int error_code);

/** debug level */
int debug = 0;
/** where debug messages are to be sent to (stderr) */
FILE *debug_output; /* " = stderr;" init moved to main() for Gnuwin32 compatibility */

/** stores current position in a bit-write-file */
int codepos = 0;

#if 0

/* ------------- THIS VARIANT OF THE COMPRESSION CODE NOT USED !! ------- */

/* ALISTAIR MOFFAT'S COMMENTS WORKED IN ------------------------------ */

void write_golomb_code_am(int x, int b, BFile *bf)
{
  int q, res, lb, ub, nr_sc, nr_lc;
  int r, lr;

  int i;
  double ldb;

  unsigned char bit1 = '\1';
  unsigned char bit0 = '\0';

  q = x / b;
  res = x - q * b;

  ldb = log2(b * 1.0);

  ub = nint(ceil(ldb));
  lb = ub - 1;

  /* write the unary part q */

  for (i = 0; i < q; i++)
    BFwrite(bit1, 1, bf);
  BFwrite(bit0, 1, bf);


  /* write the binary part */

  nr_sc = (1 << ub) - b;

  if (debug)
    fprintf(debug_output, " res=%5d CL [%3d/%3d] #sc %4d "
            "writing %5d/%d\n",
            res, lb, ub, nr_sc,
            (res < nr_sc) ? res : res + nr_sc,
            (res < nr_sc) ? lb : ub);

  if (res < nr_sc) {
    BFwriteWord((unsigned int)res, lb, bf);
  }
  else {
    BFwriteWord((unsigned int)(res + nr_sc), ub, bf);
    if (res + nr_sc >= (1 << ub))
      fprintf(stderr, "Warning: can't encode %d in %d bits\n",
              res + nr_sc, ub);
  }

}

int read_golomb_code_am(int b, BFile *bf)
{
  int q, i, nr_sc, lb, ub;

  unsigned int r;
  unsigned char bit;

  double ldb;

  ldb = log2(b * 1.0);
  ub = nint(ceil(ldb));
  lb = ub - 1;

  /* read unary part */

  q = 0;
  do {
    BFread(&bit, 1, bf);
    if (bit)
      q++;
  } while (bit);

  nr_sc = (1 << ub) - b;

  /* read binary part, bitwise */

  r = 0;
  for (i = 0; i < lb; i++) {
    r <<= 1;
    BFread(&bit, 1, bf);
    r |= bit;
  }

  if (debug)
    fprintf(debug_output, "%8d:  Read r=%5d [%3d/%3d]  #sc=%4d, ",
            codepos, r, lb, ub, nr_sc);

  if (r >= nr_sc) {
    r <<= 1;
    BFread(&bit, 1, bf);
    r |= bit;
    r -= nr_sc;
  }

  if (debug)
    fprintf(debug_output, "final r=%d\tgap=%d\n",
            r, r+q*b);

  return r + q * b;
}

#endif

/* -------------- END OF UNUSED CODE ------------------------------------ */



/* ================================================== COMPRESSION */

/**
 * Compresses the reversed index of a p-attribute.
 *
 * @param attr      The attribute to compress the index of.
 * @param output_fn Base name for the compressed RDX files to be written
 *                  (if this is null, filenames will be taken from the
 *                  attribute).
 */
void
compress_reversed_index(Attribute *attr, char *output_fn)
{
  char *s;
  char data_fname[CL_MAX_FILENAME_LENGTH];
  char index_fname[CL_MAX_FILENAME_LENGTH];

  int nr_elements;
  int element_freq;
  int corpus_size;
  int last_pos, gap, fpos;

  int b;

  int i, k;

  BFile data_file;
  FILE *index_file = NULL;

  PositionStream PStream;
  int new_pos;


  printf("COMPRESSING INDEX of %s.%s\n", corpus_id, attr->any.name);

  /* ensure that we do NOT use the compressed index while building the
   * compressed index (yeah, a nasty thing that). That is, load the
   * .corpus.rev and .corpus.rdx components in order to force
   * subsequent CL calls to use the uncompressed data.
   */
  {
    Component *comp;

    if ((comp = ensure_component(attr, CompRevCorpus, 0)) == NULL) {
      fprintf(stderr, "Index compression requires the REVCORP component\n");
      compressrdx_cleanup(1);
    }

    if ((comp = ensure_component(attr, CompRevCorpusIdx, 0)) == NULL) {
      fprintf(stderr, "Index compression requires the REVCIDX component\n");
      compressrdx_cleanup(1);
    }
  }

  nr_elements = cl_max_id(attr);
  if ((nr_elements <= 0) || (cl_errno != CDA_OK)) {
    cl_error("(aborting) cl_max_id() failed");
    compressrdx_cleanup(1);
  }

  corpus_size = cl_max_cpos(attr);
  if ((corpus_size <= 0) || (cl_errno != CDA_OK)) {
    cl_error("(aborting) cl_max_cpos() failed");
    compressrdx_cleanup(1);
  }

  if (output_fn) {
    sprintf(data_fname, "%s.crc", output_fn);
    sprintf(index_fname, "%s.crx", output_fn);
  }
  else {
    s = component_full_name(attr, CompCompRF, NULL);
    assert(s && (cl_errno == CDA_OK));
    strcpy(data_fname, s);

    s = component_full_name(attr, CompCompRFX, NULL);
    assert(s && (cl_errno == CDA_OK));
    strcpy(index_fname, s);
  }

  if (! BFopen(data_fname, "w", &data_file)) {
    fprintf(stderr, "ERROR: can't create file %s\n", data_fname);
    perror(data_fname);
    compressrdx_cleanup(1);
  }
  printf("- writing compressed index to %s\n", data_fname);

  if ((index_file = fopen(index_fname, "wb")) == NULL) {
    fprintf(stderr, "ERROR: can't create file %s\n", index_fname);
    perror(index_fname);
    compressrdx_cleanup(1);
  }
  printf("- writing compressed index offsets to %s\n", index_fname);

  for (i = 0; i < nr_elements; i++) {

    element_freq = cl_id2freq(attr, i);
    if ((element_freq == 0) || (cl_errno != CDA_OK)) {
      cl_error("(aborting) token frequency == 0\n");
      compressrdx_cleanup(1);
    }

    PStream = cl_new_stream(attr, i);
    if ((PStream == NULL) || (cl_errno != CDA_OK)) {
      cl_error("(aborting) index read error");
      compressrdx_cleanup(1);
    }

    b = compute_ba(element_freq, corpus_size);

    fpos = BFposition(&data_file);
    NwriteInt(fpos, index_file);

    if (debug)
      fprintf(debug_output, "------------------------------ ID %d (f: %d, b: %d)\n",
              i, element_freq, b);

    last_pos = 0;
    for (k = 0; k < element_freq; k++) {
      if (1 != cl_read_stream(PStream, &new_pos, 1)) {
        cl_error("(aborting) index read error\n");
        compressrdx_cleanup(1);
      }

      gap = new_pos - last_pos;
      last_pos = new_pos;

      if (debug)
        fprintf(debug_output, "%8d:  gap=%4d, b=%4d\n", codepos, gap, b);

      write_golomb_code(gap, b, &data_file);
      codepos++;
    }

    cl_delete_stream(&PStream);
    BFflush(&data_file);
  }

  fclose(index_file);
  BFclose(&data_file);

  return;
}


/* ================================================== DECOMPRESSION & ERROR CHECKING */


/**
 * Checks a compressed reversed index for errors by decompressing it.
 *
 * This function this assumes that compress_reversed_index() has been called
 * beforehand and made sure that the _uncompressed_ index is usable by CL
 * access functions.
 *
 * @param attr      The attribute to check the index of.
 * @param output_fn Base name for the compressed RDX files to be read
 *                  (if this is null, filenames will be taken from the
 *                  attribute).
 */
void
decompress_check_reversed_index(Attribute *attr, char *output_fn)
{
  char *s;
  char data_fname[CL_MAX_FILENAME_LENGTH];
  char index_fname[CL_MAX_FILENAME_LENGTH];

  int nr_elements;
  int element_freq;
  int corpus_size;
  int pos, gap;

  int b;
  int i, k;

  BFile data_file;
  FILE *index_file;

  PositionStream PStream;
  int true_pos;


  printf("VALIDATING %s.%s\n", corpus_id, attr->any.name);

  nr_elements = cl_max_id(attr);
  if ((nr_elements <= 0) || (cl_errno != CDA_OK)) {
    cl_error("(aborting) cl_max_id() failed");
    compressrdx_cleanup(1);
  }

  corpus_size = cl_max_cpos(attr);
  if ((corpus_size <= 0) || (cl_errno != CDA_OK)) {
    cl_error("(aborting) cl_max_cpos() failed");
    compressrdx_cleanup(1);
  }

  if (output_fn) {
    sprintf(data_fname, "%s.crc", output_fn);
    sprintf(index_fname, "%s.crx", output_fn);
  }
  else {
    s = component_full_name(attr, CompCompRF, NULL);
    assert(s && (cl_errno == CDA_OK));
    strcpy(data_fname, s);

    s = component_full_name(attr, CompCompRFX, NULL);
    assert(s && (cl_errno == CDA_OK));
    strcpy(index_fname, s);
  }

  if (! BFopen(data_fname, "r", &data_file)) {
    fprintf(stderr, "ERROR: can't open file %s\n", data_fname);
    perror(data_fname);
    compressrdx_cleanup(1);
  }
  printf("- reading compressed index from %s\n", data_fname);

  if ((index_file = fopen(index_fname, "r")) == NULL) {
    fprintf(stderr, "ERROR: can't open file %s\n", index_fname);
    perror(index_fname);
    compressrdx_cleanup(1);
  }
  printf("- reading compressed index offsets from %s\n", index_fname);


  for (i = 0; i < nr_elements; i++) {

    element_freq = cl_id2freq(attr, i);
    if ((element_freq == 0) || (cl_errno != CDA_OK)) {
      cl_error("(aborting) token frequency == 0\n");
      compressrdx_cleanup(1);
    }

    PStream = cl_new_stream(attr, i);
    if ((PStream == NULL) || (cl_errno != CDA_OK)) {
      cl_error("(aborting) index read error");
      compressrdx_cleanup(1);
    }

    b = compute_ba(element_freq, corpus_size);

    if (debug)
      fprintf(debug_output, "------------------------------ ID %d (f: %d, b: %d)\n",
              i, element_freq, b);

    pos = 0;
    for (k = 0; k < element_freq; k++) {

      gap = read_golomb_code_bf(b, &data_file);
      pos += gap;

      if (1 != cl_read_stream(PStream, &true_pos, 1)) {
        cl_error("(aborting) index read error\n");
        compressrdx_cleanup(1);
      }
      if (pos != true_pos) {
        fprintf(stderr, "ERROR: wrong occurrence of type #%d at cpos %d (correct cpos: %d) (on attribute: %s). Aborted.\n",
                i, pos, true_pos, attr->any.name);
        compressrdx_cleanup(1);
      }

    }

    cl_delete_stream(&PStream);
    BFflush(&data_file);
  }

  fclose(index_file);
  BFclose(&data_file);

  /* tell the user it's safe to delete the REVCORP and REVCIDX components now */
  printf("!! You can delete the file <%s> now.\n",
         component_full_name(attr, CompRevCorpus, NULL));
  printf("!! You can delete the file <%s> now.\n",
         component_full_name(attr, CompRevCorpusIdx, NULL));

  return;
}

/* ---------------------------------------------------------------------- */


/**
 * Prints a usage message and exits the program.
 *
 * @param msg         A message about the error.
 * @param error_code  Value to be returned by the program when it exits.
 */
void
compressrdx_usage(char *msg, int error_code)
{
  if (msg)
    fprintf(stderr, "Usage error: %s\n", msg);
  fprintf(stderr, "\n");
  fprintf(stderr, "Usage:  %s [options] <corpus>\n\n", progname);
  fprintf(stderr, "Compress the index of a positional attribute. Creates .crc and .crx files\n");
  fprintf(stderr, "which replace the corresponding .corpus.rev and .corpus.rdx files. After\n");
  fprintf(stderr, "running this tool successfully, the latter files can be deleted.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "  -P <att>  compress attribute <att> [default: word]\n");
  fprintf(stderr, "  -A        compress all positional attributes\n");
  fprintf(stderr, "  -r <dir>  set registry directory\n");
  fprintf(stderr, "  -f <file> set output file prefix (creates <file>.crc and <file>.crx)\n");
  fprintf(stderr, "  -d        debug mode (print messages on stderr)\n");
  fprintf(stderr, "  -D <file> debug mode (write messages to <file>)\n");
  fprintf(stderr, "  -T        skip validation pass ('I trust you')\n");
  fprintf(stderr, "  -h        this help page\n\n");
  fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");

  compressrdx_cleanup(error_code);
}

/**
 * Cleans up memory prior to an (error-prompted or normal) exit.
 *
 * @param error_code  Value to be returned by the program when it exits.
 */
void
compressrdx_cleanup(int error_code)
{
  if (corpus)
    cl_delete_corpus(corpus);

  if (debug_output != stderr)
    fclose(debug_output);

  exit(error_code);
}



/* *************** *\
 *      MAIN()     *
\* *************** */

/**
 * Main function for cwb-compress-rdx.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  char *registry_directory = NULL;
  char *attr_name = CWB_DEFAULT_ATT_NAME;
  Attribute *attr;

  char *output_fn = NULL;
  char *debug_fn = NULL;

  extern int optind;
  extern char *optarg;
  int c;

  int i_want_to_believe = 0;        /* skip error checks? */
  int all_attributes = 0;

  debug_output = stderr;        /* 'delayed' init (see top of file) */

  cl_startup();
  progname = argv[0];


  /* ------------------------------------------------- PARSE ARGUMENTS */

  while ((c = getopt(argc, argv, "+TP:r:f:dD:Ah")) != EOF) {

    switch (c) {
      /* T: skip decompression / error checking pass ("I trust you")  */
    case 'T':
      i_want_to_believe++;
      break;

      /* P: attribute to compress */
    case 'P':
      attr_name = optarg;
      break;

      /* r: registry directory */
    case 'r':
      if (registry_directory == NULL)
        registry_directory = optarg;
      else {
        fprintf(stderr, "%s: -r option used twice\n", progname);
        compressrdx_cleanup(2);
      }
      break;

      /* f: filename prefix for compressed data files */
    case 'f':
      output_fn = optarg;
      break;

      /* d: debug mode */
    case 'd':
      debug++;
      break;

      /* D: debug to file */
    case 'D':
      debug++;
      debug_fn = optarg;
      break;

      /* A: compress all attributes */
    case 'A':
      all_attributes++;
      break;

      /* h: help page */
    case 'h':
      compressrdx_usage(NULL, 2);
      break;

    default:
      compressrdx_usage("illegal option.", 2);
      break;
    }
  }

  if (debug_fn)  {
    if (strcmp(debug_fn, "-") == 0)
      debug_output = stdout;
    else if ((debug_output = fopen(debug_fn, "w")) == NULL) {
      fprintf(stderr, "Can't write debug output to file %s. Aborted.", debug_fn);
      perror(debug_fn);
      compressrdx_cleanup(1);
    }
  }

  /* single argument: corpus id */
  if (optind < argc) {
    corpus_id = argv[optind++];
  }
  else {
    compressrdx_usage("corpus not specified (missing argument)", 1);
  }

  if (optind < argc) {
    compressrdx_usage("Too many arguments", 1);
  }

  if ((corpus = cl_new_corpus(registry_directory, corpus_id)) == NULL) {
    fprintf(stderr, "Corpus %s not found in registry %s . Aborted.\n",
            corpus_id,
            (registry_directory ? registry_directory : cl_standard_registry()));
    compressrdx_cleanup(1);
  }

  if (all_attributes) {
    for (attr = corpus->attributes; attr; attr = attr->any.next)
      if (attr->any.type == ATT_POS) {
        compress_reversed_index(attr, output_fn);
        if (! i_want_to_believe)
          decompress_check_reversed_index(attr, output_fn);
      }
  }
  else {
    if ((attr = cl_new_attribute_oldstyle(corpus, attr_name, ATT_POS, NULL)) == NULL) {
      fprintf(stderr, "Attribute %s.%s doesn't exist. Aborted.\n", corpus_id, attr_name);
      compressrdx_cleanup(1);
    }
    compress_reversed_index(attr, output_fn);
    if (! i_want_to_believe)
      decompress_check_reversed_index(attr, output_fn);
  }

  compressrdx_cleanup(0);
  return 0;                        /* never reached; to keep gcc from complaining */
}
