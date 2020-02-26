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
 * cwb-s-encode adds an s-attribute to an existing corpus.
 *
 * Input:  a list of regions (on stdin or in the file specified in the first argument
 *         to the program name) with lines in the following format:
 *
 * start TAB end [ TAB annotation ]
 *
 * start      = corpus position of first token in region (integer as text)
 * end        = corpus position of last token in region (integer as text)
 * annotation = annotation text (only if s-attribute was specified with -V)
 *
 * Output: file att.rng (plus att.avs, att.avx for -V attributes) where att is the
 * specified attribute name.
 */

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <errno.h>
#include <string.h>
#include <assert.h>
#include <limits.h>


#include "../cl/cl.h"
#include "../cl/cwb-globals.h"
#include "../cl/endian.h"       /* for byte order conversion functions */
#include "../cl/storage.h"      /* for NwriteInt() */

/* ---------------------------------------------------------------------- */

#define UMASK              0644

/** printf format string for path of file storing ranges of given structural attribute */
#define RNG_RNG "%s" SUBDIR_SEP_STRING "%s.rng"

/** printf format string for path of attribute value index of a given structural attribute */
#define RNG_AVX "%s" SUBDIR_SEP_STRING "%s.avx"

/** printf format string for path of attribute values of a given structural attribute */
#define RNG_AVS "%s" SUBDIR_SEP_STRING "%s.avs"


/* ---------------------------------------------------------------------- */

/* configuration variables & command-line switches */
int debug = 0;                  /**< debug mode on/off */
int silent = 0;                 /**< avoid messages in -M / -a modes */
int strip_blanks_in_values = 0; /* Wow, this is unused :o) */
int set_syntax_strict = 0;      /**< check that set attributes are always given in the same syntax */
int in_memory = 0;              /**< create list of regions in memory (allowing non-linear input), then write to disk */
int add_to_existing = 0;        /**< add to existing attribute: implies in_memory; existing regions are automatically
                                     inserted at startup */
FILE *text_fd = NULL;           /**< stream handle for file to read from. */

/* global variables */
unsigned long input_line = 0;   /**< input line number (used for error messages) */
Corpus *corpus = NULL;          /**< corpus we're working on; at the moment, this is only required for add_to_existing */
CorpusCharset encoding_charset; /**< a charset object; will be the same as that of corpus if we are adding to an existing corpus,
                                     otherwise, should be declared. */
const char *encoding_charset_name = "latin1";
                                 /**< character set label from the -c option. Value = pre-4.0 default of latin1. */
int clean_strings = 0;           /**< clean up input strings by replacing invalid bytes with '?' (except for UTF8 encoding)*/
                                 /* hack for v3.5 for backward compatibility: never clean strings. */

/* TODO this would be useful as a general tool , non? */
enum {
  set_none, set_any, set_regular, set_whitespace
} set_att = set_none;           /**< feature-set attributes: type of. Initial value: not a feature set.
                                 *   Changes to set_any once we know we are dealing with a feature set.
                                 *   Changes to set_regular or set_whitespace once we know which format of f.s. it is. */


/* ---------------------------------------------------------------------- */

/**
 * mini_s_builder object - distinct from, and simpler than the similar object in cwb-encode.
 *
 */
typedef struct {
  char *dir;                    /**< directory where this s-attribute is stored */
  char *name;                   /**< s-attribute name */

  int store_values;             /**< flag indicating whether to store values */

  int ready;                    /**< flag indicates whether sencode_range_open() has already been called */
  FILE *fd;                     /**< fd of x.rng (bin mode) */
  FILE *avx;                    /**< the attribute value index (bin mode)*/
  FILE *avs;                    /**< the attribute value strings (text mode) */

  int last_cpos;                /**< end of last region (consistency checking) */
  int num;                      /**< the next will be the num-th structure */
  int offset;                   /**< string offset for next string */
} mini_s_builder;


/**
 * Global (and only) instance of the cwb-s-encode mini_s_builder object.
 *
 * Contains information on the new s-attribute being coded.
 */
mini_s_builder new_satt;

/* ---------------------------------------------------------------------- */

char *progname = NULL;

/* ---------------------------------------------------------------------- */


/**
 * The "structure list" data type is used for 'adding' regions (-a).
 *
 * In this case, all existing regions are read into an ordered, bidirectional list;
 * new regions are inserted into that list (overlaps are automatically resolved
 * in favour of the 'earlier' region; if start point is identical, the longer
 * region is retained). Only once the entire input has been read is the data
 * actually encoded and stored on disk.
 */
typedef struct s_region {
  int start;                    /**< start of region */
  int end;                      /**< end of region */
  char *annot;                  /**< annotated string */
  struct s_region *prev;
  struct s_region *next;
} *s_region;

s_region StructureList = NULL;        /**< (single) global list of s-att regions */
s_region SL_Point = NULL;             /**< pointer into global list; NULL = start of list; linear search starts from SL_Point */

/* s_region functions:
 *  item = SL_seek(cpos);           (find region containing (or preceding) cpos; NULL = start of list; sets SL_Point to returned value)
 *  item = SL_insert_after_point(start, end, annot);   (insert region [start, end, annot] after SL_Point; no overlap/position checking)
 *  SL_delete(item);                (delete region from list; updates SL_Point if it happened to point at item)
 *  SL_insert(start, end, annot);   (user function: combines SL_seek(), SL_insert_at_point() and ambiguity resolution)
 *  SL_rewind();                    (reset SL_Point to start of list)
 *  item = SL_next();               (returns item marked by point, then advances point to next item; NULL at end of list)
 */

/**
 * Rewind the index-pointer to the start of the global structure list.
 */
void
SL_rewind(void)
{
  SL_Point = StructureList;
}

/**
 * Gets a pointer to the next available structure on the global structure list.
 *
 * Returns NULL if we're at the end of the list.
 */
s_region
SL_next(void)
{
  s_region item = SL_Point;
  if (SL_Point)
    SL_Point = SL_Point->next;
  return item;
}

/**
 * Find region containing (or preceding) cpos; NULL = start of list; sets SL_Point to returned value.
 */
s_region
SL_seek(int cpos)
{
  if (!SL_Point)          /* start-of-list case */
    SL_Point = StructureList;

  while (SL_Point != NULL) {
    if ((SL_Point->start <= cpos) && (cpos <= SL_Point->end))
      return SL_Point;
      /* found region containing cpos */

    if ((cpos < SL_Point->start))
      SL_Point = SL_Point->prev;
      /* try previous region: SL_Point may become NULL = start of list */
    else if ((cpos > SL_Point->end) && (SL_Point->next != NULL) && (SL_Point->next->start <= cpos))
      SL_Point = SL_Point->next;
      /* try next region, but only if it isn't _behind_ cpos */
    else
      return SL_Point;
      /* can't do better than that */
  }
  return NULL;
}

/**
 * Insert region [start, end, annot] after SL_Point; no overlap/position checking
 */
s_region
SL_insert_after_point(int start, int end, char *annot)
{
  /* allocate and initialise new item to insert into list */
  s_region item = (s_region)cl_malloc(sizeof(struct s_region));
  item->start = start;
  item->end = end;
  if (annot)
    item->annot = cl_strdup(annot);
  else
    item->annot = NULL;
  item->prev = NULL;
  item->next = NULL;

  /* this function has to handle a number of special cases ... */
  if (SL_Point == NULL) {          /* insert at start of list */
    if (StructureList == NULL) {   /* empty list */
      SL_Point = StructureList = item;
    }
    else {
      item->next = StructureList;
      StructureList->prev = item;
      SL_Point = StructureList = item;
    }
  }
  else if (SL_Point->next == NULL) { /* insert at end of list */
    item->prev = SL_Point;
    SL_Point = SL_Point->next = item;
  }
  else {                         /* insert somewhere inside list */
    item->next = SL_Point->next; /* links between new item and following item */
    SL_Point->next->prev = item;
    SL_Point->next = item;       /* links between point and new item */
    item->prev = SL_Point;
    SL_Point = item;
  }
  return SL_Point;
}

/**
 * delete region from list; updates SL_Point if it happened to point at item
 */
void
SL_delete(s_region item)
{
  /* unlink item ... we have to handle a few special cases again */
  if (item->prev == NULL) {     /* delete first list element */
    StructureList = item->next;
    if (item->next != NULL)
      item->next->prev = NULL;
    if (item == SL_Point)
      SL_Point = item->next;    /* if SL_Point was positioned at this item, set it to following one */
  }
  else {
    item->prev->next = item->next; /* link preceding item to following item (which may be NULL) */
    if (item->next != NULL)
      item->next->prev = item->prev;
    if (item == SL_Point)
      SL_Point = item->prev;    /* default is to set SL_Point to preceding item (which we now know to exist) */
  }
  /* free item object */
  cl_free(item->annot);
  cl_free(item);
}

/**
 * Inserts an item into the global structure list.
 *
 * It adds a new region to the list: its start point, its end point, its annotation.
 *
 * Combines SL_seek(), SL_insert_at_point() and ambiguity resolution.
 */
void
SL_insert(int start, int end, char *annot)
{
  s_region point, item;

  point = SL_seek(start);
  if (point == NULL) {
    item = SL_insert_after_point(start, end, annot); /* insert item at start of list */
  }
  else if ((point->start <= start) && (start <= point->end)) {
    /* start is within the previous region stored in point */
    if ((point->start < start) || (point->end > end)) {
      /* overlap: don't insert
       * because either the start points don't match, or the old region is longer */
      return;
    }
    else {
      /* point->start == start && point->end <= end -->
       * overlap: overwrite previous entry
       * because the start points match, and the new region is as long or longer */
      item = SL_insert_after_point(start, end, annot);
      SL_delete(point); /* this re-establishes list ordering */
    }
  }
  else {
    /* non-overlapping: simply insert new region after point */
    item = SL_insert_after_point(start, end, annot);
  }

  /* new item may overlap one or more of the following ones, which we must delete */
  point = item->next;
  while ((point != NULL) && (point->start <= item->end)) { /* (point->start > item->start) implied by insertion above */
    SL_delete(point);
    point = item->next;
  }
}





/**
 * Parses an input line into cwb-s-encode.
 *
 * Usage:
 *
 * ok = sencode_parse_line(char *line, int *start, int *end, char **annot);
 *
 * Expects standard TAB-separated format; first two fields must be numbers,
 * optional third field is returned in annot - if not present, annot is
 * set to NULL.
 *
 * @param line   The line to be parsed (without trailing line break).
 * @param start  Out-parameter: Location to put the start cpos.
 * @param end    Out-parameter: Location to put the end cos.
 * @param annot  Out-parameter: Location to put the annotation string.
 * @return       Boolean; true for all OK, false for error.
 */
int
sencode_parse_line(char *line, int *start, int *end, char **annot)
{
  char *field, *field_end;
  char *line_copy = cl_strdup(line); /* work on copy to retain original for error messages */
  int has_annotation = 1; /* Boolean: are there 3 colums on this line? */

  /* first field: INT range_start */
  field = line_copy;
  field_end = strchr(field, '\t');
  if (field_end == NULL)
    return 0;
  else {
    *field_end = 0;
    errno = 0;
    *start = atoi(field);
    if (errno != 0 || *start < 0)
      return 0;
    field = field_end + 1;
  }

  /* second field: INT range_end */
  field_end = strchr(field, '\t');
  if (field_end == NULL) {
    has_annotation = 0;
    field_end = field + strlen(field) - 1;
    if (field_end <= field)
      return 0;
  }
  else
    *field_end = 0;

  errno = 0;
  *end = atoi(field);
  if (errno != 0 || *end < 0)
    return 0;
  field = field_end + 1;

  /* optional third field: STRING annotation */
  if (has_annotation) {
    field_end = strchr(field, '\t');
    if (field_end != NULL)
      return 0;                 /* return parse error if there are extra fields */
    else {
      /* nb re clean_strings: it is OK to modify field inplace, because we will free it before we leave the function! */
      if (!cl_string_validate_encoding(field, encoding_charset, clean_strings)) {
        fprintf(stderr,
            "Encoding error on line #%ld: an invalid byte or byte sequence for charset \"%s\" was encountered.\n",
            input_line,
            encoding_charset_name);
        exit(1);
      }
      /* Duplicate into annot; normalize UTF8 to precomposed form, otherwise just duplicate normally. */
      if (encoding_charset == utf8)
        *annot = cl_string_canonical(field, utf8, REQUIRE_NFC, CL_STRING_CANONICAL_STRDUP);
      else
        *annot = cl_strdup(field);
      /* finally, get rid of C0 controls iff the user asked us to clean up strings */
      if (clean_strings)
        cl_string_zap_controls(*annot, encoding_charset, '?', 1, 1);
    }
  }
  else
    *annot = NULL;

  cl_free(line_copy);
  return 1;
}



/**
 * Changes an annotation string to standard set attribute syntax.
 *
 * On first call, the function checks whether annotations are already given in standard
 * '|'-delimited form; otherwise we assume we are using whitespace to split.
 *
 * The return string may have been newly allocated
 * (i.e. caller must use & free the returned value).
 *
 * If there are syntax errors, returns NULL.
 *
 * @param annot  The annotation string to check.
 * @return       The standardised string, or NULL if there was an
 *               error in the call to cl_make_set().
 */
char *
sencode_check_set(char *annot)
{
  char *set;

  if (set_att == set_none || annot == NULL)
    return annot;
    /* no modification needed */
  else if ((!set_syntax_strict) || set_att == set_any) {
    /* we work out the set mode on the first item analysed, or on
     * every item iff we are using non-strict set syntax */
    if (annot[0] == '|')
      set_att = set_regular;
    else
      set_att = set_whitespace;
  }

  set = cl_make_set(annot, (set_att == set_whitespace));
  cl_free(annot);
  return set;
}



/**
 * print usage message and exit
 */
void
sencode_usage(void)
{
  fprintf(stderr, "\n");
  fprintf(stderr, "Usage:  %s [options] (-S <att> | -V <att>)\n", progname);
  fprintf(stderr, "\n");
  fprintf(stderr, "Adds s-attributes with computed start and end points to a corpus\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "  -B        strip leading/trailing blanks from annotations\n");
  fprintf(stderr, "  -d <dir>  directory for output files\n");
  fprintf(stderr, "  -f <file> read input from <file> [default: stdin]\n");
  fprintf(stderr, "  -M        create list of regions in memory (resolving overlaps)\n");
  fprintf(stderr, "  -r <dir>  set registry directory <dir>\n");
  fprintf(stderr, "  -C <id>   work on corpus <id> (with -a option)\n");
  fprintf(stderr, "  -a        add to existing annotation (resolving overlaps, implies -M)\n");
  fprintf(stderr, "  -m        treat annotations as feature set (or 'multi-value') attribute\n");
  fprintf(stderr, "  -s        (with -m) check that format of set annotations is consistent\n");
  fprintf(stderr, "  -q        silent mode ('be quiet')\n");
  fprintf(stderr, "  -D        debug mode\n");
  fprintf(stderr, "  -S <att>  generate s-attribute <att>\n");
  fprintf(stderr, "  -V <att>  generate s-attribute <att> with annotations\n");
  fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");
  exit(2);
}



/**
 * Initialises the "new_satt" variable for the s-attribute to be encoded,
 * and sets name/directory
 */
void
sencode_declare_new_satt(char *name, char *directory, int store_values)
{
  new_satt.name = cl_strdup(name);
  new_satt.dir = cl_strdup(directory);

  new_satt.num = 0;
  new_satt.offset = 0;
  new_satt.store_values = store_values;
  new_satt.last_cpos = -1;

  new_satt.ready = 0;
  new_satt.fd = NULL;
  new_satt.avs = NULL;
  new_satt.avx = NULL;
}

/** Open disk files for the s-attribute being encoded (must have been declared first). */
void
sencode_open_files(void)
{
  char buf[CL_MAX_LINE_LENGTH];

  sprintf(buf, RNG_RNG, new_satt.dir, new_satt.name);
  if ((new_satt.fd = fopen(buf, "wb")) == NULL) {
    perror(buf);
    exit(1);
  }

  if (new_satt.store_values) {
    sprintf(buf, RNG_AVS, new_satt.dir, new_satt.name);
    if ((new_satt.avs = fopen(buf, "w")) == NULL) {
      perror(buf);
      exit(1);
    }

    sprintf(buf, RNG_AVX, new_satt.dir, new_satt.name);
    if ((new_satt.avx = fopen(buf, "wb")) == NULL) {
      perror(buf);
      exit(1);
    }
  }

  new_satt.ready = 1;
}

/** Close the disk files for the s-attribute being encoded. */
void
sencode_close_files(void)
{
  if (new_satt.ready) {
    if (EOF == fclose(new_satt.fd)) {
      perror("Error writing RNG file");
      exit(1);
    }

    if (new_satt.avs) {
      if (EOF == fclose(new_satt.avs)) {
        perror("Error writing AVS file");
        exit(1);
      }
    }

    if (new_satt.avx) {
      if (EOF == fclose(new_satt.avx)) {
        perror("Error writing AVX file");
        exit(1);
      }
    }

    new_satt.ready = 0;
  }
}


/**
 * Parse options and set global variables
 */
void
sencode_parse_options(int argc, char **argv)
{
  int c;
  extern char *optarg;
  extern int optind;

  /* by default, output files are written to current directory */
  char *directory = ".";
  /* may need to set registry if source corpus is specified */
  char *registry = NULL;
  /* source corpus _may_ be set with the -C switch */
  char *corpus_name = NULL;

  /* if text_fd is unspecified, stdin will be used */
  text_fd = NULL;
  /* make sure either -S or -V is used: reset new_satt.name now & check after getopt */
  new_satt.name = NULL;

  while((c = getopt(argc, argv, "+qBd:f:msDS:V:r:c:C:Mah")) != EOF)
    switch(c) {

      /* q: be silent (quiet) */
    case 'q':
      silent++;
      break;

      /* B: strip blanks */
    case 'B':
      strip_blanks_in_values++;
      break;

      /* d: directory for generated data files */
    case 'd':
      directory = optarg;
      break;

      /* f: read input from file */
    case 'f':
      if (text_fd) {
        fprintf(stderr, "Error: -f option used twice\n\n");
        exit(1);
      }
      text_fd = cl_open_stream(optarg, CL_STREAM_READ, CL_STREAM_MAGIC);
      if (text_fd == NULL) {
        cl_error("Can't open input file");
        exit(1);
      }
      break;

      /* M: compile list in memory, then write to disk */
    case 'M':
      in_memory++;
      break;

      /* a: add to existing attribute (implies -M) */
    case 'a':
      add_to_existing++;
      in_memory++;
      break;

      /* r: registry directory */
    case 'r':
      registry = optarg;
      break;

      /* c: character set for annotations */
    case 'c':
      encoding_charset_name = cl_charset_name_canonical(optarg);
      if (encoding_charset_name == NULL) {
        fprintf(stderr, "Invalid character set specified with the -c flag!");
        exit(1);
      }
      encoding_charset = cl_charset_from_name(encoding_charset_name);
      break;

      /* C: source corpus */
      /*
       * TODO in version 3.9 / 4.0
       * We should really use the -C flag for annotation cleanup, as per cwb-encode
       * to accompany the -c option. for now, let's assume cleanup is never enabled,
       * to preserve backward compatibility.
       *
       * We could use -e or -E for the corpus name (short for "existing").
       */
    case 'C':
      corpus_name = optarg;
      break;

      /* m: set ('multi-value') attribute */
    case 'm':
      set_att = set_any;        /* don't know yet whether it's '|'-delimited or "split on whitespace" */
      break;

      /* s: strict syntax checks on set attribute */
    case 's':
      set_syntax_strict++;
      break;

      /* D: debug mode */
    case 'D':
      debug++;
      break;

      /* S: s-attribute without annotations */
    case 'S':
      sencode_declare_new_satt(optarg, directory, 0);
      if (optind < argc) {
        fprintf(stderr, "Error: -S <att> must be last flag on command line.\n\n");
        exit(1);
      }
      break;

      /* V: s-attribute with annotations */
    case 'V':
      sencode_declare_new_satt(optarg, directory, 1);
      if (optind < argc) {
        fprintf(stderr, "Error: -V <att> must be last flag on command line.\n\n");
        exit(1);
      }
      break;

    /* default or -h: error */
    case 'h':
    default:
      sencode_usage();
      break;
    }

  /* now, check the default and obligatory values */
  if (!text_fd)
    text_fd = cl_open_stream("", CL_STREAM_READ, CL_STREAM_STDIO); /* open STDIN as a CL stream */
  if (new_satt.name == NULL) {
    fprintf(stderr, "Error: either -S or -V flag must be specified.\n\n");
    exit(1);
  }
  if (optind < argc) {
    fprintf(stderr, "Error: extra arguments.\n\n");
    exit(1);
  }

  /* if -C <corpus> was specified, open source corpus, and override any specified character set. */
  if (corpus_name != NULL) {
    corpus = cl_new_corpus(registry, corpus_name);
    if (corpus == NULL) {
      fprintf(stderr, "Error: Can't find corpus <%s>!\n", corpus_name);
      exit(1);
    }
    encoding_charset = cl_corpus_charset(corpus);
  }

}


/* ======================================== */

/** Lexhash used when writing regions, to avoid multiple copies of annotations (-m mode) */
cl_lexhash LH = NULL;

/**
 * Write data about a region to disk files (as defined in global variable new_satt).
 */
void
sencode_write_region(int start, int end, char *annot)
{
  if (!new_satt.ready)
    sencode_open_files();
  if (new_satt.store_values && NULL == LH)
    LH = cl_new_lexhash(0);

  /* write start & end positions of region */
  NwriteInt(start, new_satt.fd);
  NwriteInt(end, new_satt.fd);

  /* store annotation for -V attribute */
  if (new_satt.store_values) {
    cl_lexhash_entry entry = cl_lexhash_find(LH, annot);

    if (!entry) {
      /* must add string to hash and to avs file */
      entry = cl_lexhash_add(LH, annot);
      entry->data.integer = new_satt.offset;
      new_satt.offset += strlen(annot) + 1; /* increment range offset */
      /* integer overflow check */
      if (new_satt.offset < 0) {
        fprintf(stderr, "Too many annotation values for <%s> regions (lexicon size > %d bytes)\n", new_satt.name, INT_MAX);
        exit(1);
      }
      if (0 > fprintf(new_satt.avs, "%s%c", annot, 0)) {
        perror("Error writing to AVS file");
        exit(1);
      }
    }

    NwriteInt(new_satt.num, new_satt.avx);
    NwriteInt(entry->data.integer, new_satt.avx);
  }

  new_satt.num++;   /* increment region number */
  new_satt.last_cpos = end;
}



/* *************** *
 *      MAIN()     *
 * *************** */





/**
 * Main function for cwb-s-encode.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  int start, end;
  char *annot;
  char buf[CL_MAX_LINE_LENGTH];
  Attribute *att;              /* the existing s-attribute to add to. */
  int V_switch,                /* boolean: was -V supplied to the program? */
      values,                  /* boolean: does the existing s-attribute have values? */
      S_annotations_dropped;   /* counter of n of annotations ignored from input because we are encoding a -S */
  int i, N;

  cl_startup();
  progname = argv[0];
  sencode_parse_options(argc, argv);

  /* -a mode: read existing regions into memory */
  if (add_to_existing) {
    if (corpus == NULL) {
      fprintf(stderr, "Error: You have to specify source corpus (-C <corpus>) for -a switch.\n");
      exit(1);
    }
    att = cl_new_attribute(corpus, new_satt.name, ATT_STRUC);
    if ((att != NULL) && (cl_max_struc(att) > 0)) {
      V_switch = new_satt.store_values;
      values = cl_struc_values(att);
      if (V_switch && (!values)) {
        fprintf(stderr, "Error: The existing regions of an attribute specified as -V have no annotations.\n");
        exit(1);
      }
      else if ((!V_switch) && values) {
        fprintf(stderr, "Error: The existing regions of an attribute specified as -S have annotations.\n");
        exit(1);
      }
      if (!silent)
        printf("[Loading previous <%s> regions]\n", new_satt.name);

      N = cl_max_struc(att);
      for (i = 0; i < N; i++) {
        cl_struc2cpos(att, i, &start, &end);
        annot = cl_struc2str(att, i);
        SL_insert(start, end, annot);
      }
    }
    else {
      if (!silent)
        printf("[No <%s> regions defined (skipped)]\n", new_satt.name);
    }
  }

  /* loop reading input (stdin or -f <file>) */
  if (in_memory && (!silent))
    printf("[Reading input data]\n");
  S_annotations_dropped = 0;
  while (fgets(buf, CL_MAX_LINE_LENGTH, text_fd)) {
    input_line++;

    /* check for buffer overflow */
    if (strlen(buf) >= (CL_MAX_LINE_LENGTH - 1)) {
      fprintf(stderr, "BUFFER OVERFLOW, input line #%ld is too long:\n>> %s", input_line, buf);
      exit(1);
    }

    /* remove trailing line break (LF or CR-LF) */
    cl_string_chomp(buf);

    if (! sencode_parse_line(buf, &start, &end, &annot)) {
      fprintf(stderr, "FORMAT ERROR on line #%ld:\n>> %s", input_line, buf);
      exit(1);
    }
    if (new_satt.store_values && (annot == NULL)) {
      fprintf(stderr, "MISSING ANNOTATION on line #%ld:\n>> %s", input_line, buf);
      exit(1);
    }
    if ((!new_satt.store_values) && (annot != NULL)) {
      if (! S_annotations_dropped)
        fprintf(stderr, "WARNING: Annotation for -S attribute ignored on line #%ld (warning issued only once):\n>> %s", input_line, buf);
      S_annotations_dropped++;
    }
    if ((start <= new_satt.last_cpos) || (end < start)) {
      fprintf(stderr, "RANGE INCONSISTENCY on line #%ld:\n>> %s(end of previous region was %d)\n", input_line, buf, new_satt.last_cpos);
      exit(1);
    }
    if (annot != NULL && set_att != set_none) {
      /* convert set annotation into standard syntax */
      annot = sencode_check_set(annot);
      if (annot == NULL) {
        fprintf(stderr, "SET ANNOTATION SYNTAX ERROR on line #%ld:\n>> %s", input_line, buf);
        exit(1);
      }
    }

    /* debugging output */
    if (debug) {
      fprintf(stderr, "[%d, %d]", start, end);
      if (annot != NULL)
        fprintf(stderr, " <%s>", annot);
      fprintf(stderr, "\n");
    }

    /* in -M mode, store this region in memory; otherwise write it to the disk files */
    if (in_memory)
      SL_insert(start, end, annot);
    else
      sencode_write_region(start, end, annot);

    cl_free(annot);
  }

  /* in -M mode, write data to disk now that we have finished looping across input data */
  if (in_memory) {
    s_region item;

    if (!silent)
      printf("[Creating encoded disk file(s)]\n");
    SL_rewind();
    while ((item = SL_next()) != NULL)
      sencode_write_region(item->start, item->end, item->annot);
  }

  /* close files */
  sencode_close_files();

  if (S_annotations_dropped > 0)
    fprintf(stderr, "Warning: %d annotation values dropped for -S attribute '%s'.\n", S_annotations_dropped, new_satt.name);

  exit(0);
}
