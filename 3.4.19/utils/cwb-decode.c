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

#include <ctype.h>

#include "../cl/cl.h"
#include "../cl/cwb-globals.h"
#include "../cl/corpus.h"
#include "../cl/attributes.h"

char *progname = NULL;
char *registry_directory = NULL;
char *corpus_id = NULL;
Corpus *corpus = NULL;

/* ---------------------------------------- */

/** Maximum number of attributes that can be printed. */
#define MAX_ATTRS 1024

Attribute *print_list[MAX_ATTRS];    /**< array of attributes selected by user for printing */
int print_list_index = 0;            /**< Number of atts added to print_list (so far);
                                      *   used with less-than, = top limit for scrolling that array */

/**
 * Represents a single s-attribuite region and its annotation.
 *
 * Before a token is printed, all regions of s-attributes from print_list[]
 * which contain that token are copied to s_att_regions[],
 * bubble-sorted (to enforce proper nesting while retaining
 * the specified order as far as possible), and printed from s_att_regions[] .
 */
typedef struct {
  char *name;                   /**< name of the s-attribute */
  int start;
  int end;
  char *annot;                  /**< NULL if there is no annotation; otherwise the content of the annotation */
} SAttRegion;
SAttRegion s_att_regions[MAX_ATTRS];
int sar_sort_index[MAX_ATTRS];  /**< index used for bubble-sorting list of regions */
int N_sar = 0;                  /**< number of regions currently in list (may change for each token printed) */

/* ---------------------------------------- */

/* the following are used only in matchlist mode: */

/** Maximum number of attributes whose "surrounding values" can be printed in matchlist mode. */
#define MAX_PRINT_VALUES 1024

Attribute *printValues[MAX_PRINT_VALUES];   /**< List of s-attributes whose values are to be printed */
int printValuesIndex = 0;                   /**< Number of atts added to printValues (so far);
                                             *   used with less-than, = top limit for scrolling that array */

/* ---------------------------------------- */

int first_token;            /**< cpos of token to begin output at */
int last_token;             /**< cpos of token to end output at (inclusive; ie this one gets printed!) */
int maxlast;                /**< maximum ending cpos + 1 (deduced from size of p-attribute);  */
int printnum = 0;           /**< whether or not token numbers are to be printed (-n option) */


typedef enum _output_modes {
  StandardMode, LispMode, EncodeMode, ConclineMode, XMLMode
} OutputMode;

OutputMode mode = StandardMode;  /**< global variable for overall output mode */
int xml_compatible = 0;          /**< xml-style, for (cwb-encode -x ...); EncodeMode only, selected by -Cx */


/* not really necessary, but we'll keep it for now -- it's cleaner anyway :o) */
/**
 * Cleans up memory prior to an error-prompted exit.
 *
 * @param error_code  Value to be returned by the program when it exits.
 */
void
decode_cleanup(int error_code)
{
  if (corpus != NULL)
    cl_delete_corpus(corpus);
  exit(error_code);
}

/**
 * Prints a usage message and exits the program.
 *
 * @param exit_code  Value to be returned by the program when it exits.
 */
void
decode_usage(int exit_code)
{
  fprintf(stderr, "\n");
  fprintf(stderr, "Usage:  %s [options] <corpus> [declarations]\n\n", progname);
  fprintf(stderr, "Decodes CWB corpus as plain text (or in various other text formats).\n");
  fprintf(stderr, "In normal mode, the entire corpus (or a segment specified with the\n");
  fprintf(stderr, "-s and -e options) is printed on stdout. In matchlist mode (-p or -f),\n");
  fprintf(stderr, "(pairs of) corpus positions are read from stdin (or a file specified\n");
  fprintf(stderr, "with -f), and the corresponding tokens or ranges are displayed. The\n");
  fprintf(stderr, "[declarations] determine which attributes to display (-ALL for all attributes).\n\n");
  fprintf(stderr, "See list of options for available output modes.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "  -L        Lisp output mode\n");
  fprintf(stderr, "  -H        concordance line ('horizontal') output mode\n");
  fprintf(stderr, "  -C        compact output mode (suitable for cwb-encode)\n");
  fprintf(stderr, "  -Cx       XML-compatible compact output (for \"cwb-encode -x ...\")\n");
  fprintf(stderr, "  -X        XML output mode\n");
  fprintf(stderr, "  -n        show corpus position ('numbers')\n");
  fprintf(stderr, "  -s <n>    first token to print (at corpus position <n>)\n");
  fprintf(stderr, "  -e <n>    last token to print (at corpus position <n>)\n");
  fprintf(stderr, "  -r <dir>  set registry directory\n");
  fprintf(stderr, "  -p        matchlist mode (input from stdin)\n");
  fprintf(stderr, "  -f <file> matchlist mode (input from <file>)\n");
  fprintf(stderr, "  -Sp, -Sf  subcorpus mode (output all XML tags, but only selected tokens)\n");
  fprintf(stderr, "  -h        this help page\n\n");
  fprintf(stderr, "Attribute declarations:\n");
  fprintf(stderr, "  -P <att>  print p-attribute <att>\n");
  fprintf(stderr, "  -S <att>  print s-attribute <att> (possibly including annotations)\n");
  fprintf(stderr, "  -V <att>  show s-attribute annotation for each range in matchlist mode\n");
  fprintf(stderr, "  -A <att>  print alignment attribute <att>\n");
  fprintf(stderr, "  -ALL      print all p-attributes and s-attributes\n");
  fprintf(stderr, "  -c <att>  expand ranges to full <att> region (matchlist mode)\n\n");
  fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");

  decode_cleanup(exit_code);
}

/**
 * Check whether a string represents a number.
 *
 * @param s  The string to check.
 * @return   Boolean: true iff s contains only digits.
 */
int
is_num(char *s)
{
  int i;

  for (i = 0; s[i]; i++)
    if (!isdigit((unsigned char)s[i]))
      return 0;

  return 1;
}


/**
 * Escapes a string according to the currently active global mode.
 *
 * In XMLMode, this function converts the string to an encoded XML string;
 * all 'critical' characters are replaced by entity references,
 * and C0 control characters are replaced with blanks. (This also happens
 * in other modes - i.e. compact - if the global xml_compatible variable is true.)
 *
 * In LispMode, it converts the string to a Lisp string with the required escapes (probably!)
 *
 * In any other mode, it does nothing, and just returns the argument pointer.
 *
 * It is safe to use this function without checking for a NULL argument,
 * as NULLs will just be returned as NULLs.
 *
 * Warning: returns pointer to static internal buffer of fixed size;
 * in particular, don't use it twice in a single argument list!
 *
 * @see      EncodeMode
 * @param s  String to encode.
 * @return   Pointer to encoded string in static internal buffer; or,
 *           the argument s iff the mode is not one that requires any
 *           encoding. If the argument is NULL, NULL is returned.
 */
const char *
decode_string_escape(const char *s)
{
  int i, t = 0;
  static char coded_s[CL_MAX_LINE_LENGTH];

  if (s == NULL)
    return NULL;

  if (mode == XMLMode || xml_compatible) {
    for (i = 0; s[i]; i++) {
      if (s[i] == '"') {
        sprintf(coded_s+t, "&quot;");
        t += strlen(coded_s+t);
      }
      else if (s[i] == '\'') {
        sprintf(coded_s+t, "&apos;");
        t += strlen(coded_s+t);
      }
      else if (s[i] == '<') {
        sprintf(coded_s+t, "&lt;");
        t += strlen(coded_s+t);
      }
      else if (s[i] == '>') {
        sprintf(coded_s+t, "&gt;");
        t += strlen(coded_s+t);
      }
      else if (s[i] == '&') {
        sprintf(coded_s+t, "&amp;");
        t += strlen(coded_s+t);
      }
      else if ((s[i] > 0) && (s[i] < 32)) {
        /* C0 controls are invalid -> substitute blanks */
        coded_s[t++] = ' ';
      }
      else {
        coded_s[t++] = s[i];
      }
    }
    /* terminate converted string and return it */
    coded_s[t] = '\0';
    return coded_s;
  }
  else if (mode == LispMode) {
    for (i = 0; s[i]; i++) {
      if ((s[i] == '"') || (s[i] == '\\')) {
        coded_s[t++] = '\\';
        coded_s[t++] = '\\';
        coded_s[t++] = '\\';
      }
      coded_s[t++] = s[i];
    }
    coded_s[t] = '\0';
    /* terminate converted string and return it */
    return coded_s;
  }
  else
    /* all other modes : do nothing */
    return s;
}

/**
 * Prints an XML declaration, using character set specification
 * obtained from the global corpus variable.
 */
void
decode_print_xml_declaration(void)
{
  CorpusCharset charset = unknown_charset;
  if (corpus)
    charset = cl_corpus_charset(corpus);

  printf("<?xml version=\"1.0\" encoding=\"");
  switch (charset) {
  case latin1:
    printf("ISO-8859-1");
    break;
  case latin2:
    printf("ISO-8859-2");
    break;
  case latin3:
    printf("ISO-8859-3");
    break;
  case latin4:
    printf("ISO-8859-4");
    break;
  case cyrillic:
    printf("ISO-8859-5");
    break;
  case arabic:
    printf("ISO-8859-6");
    break;
  case greek:
    printf("ISO-8859-7");
    break;
  case hebrew:
    printf("ISO-8859-8");
    break;
  case latin5:
    printf("ISO-8859-9");
    break;
  case latin6:
    printf("ISO-8859-10");
    break;
  case latin7:
    printf("ISO-8859-13");
    break;
  case latin8:
    printf("ISO-8859-14");
    break;
  case latin9:
    printf("ISO-8859-15");
    break;
  case utf8:
    printf("UTF-8");
    break;
  case unknown_charset:
  default:
    printf("ISO-8859-1");       /* at least the parser isn't going to break down that way. probably. */
    break;
  }
  printf("\" standalone=\"yes\" ?>\n");
}


/**
 * Sorts s_att_regions[MAX_ATTRS] in ascending 'nested' order,
 * using sar_sort_index[] (which is automatically initialised).
 *
 * Since only regions which begin or end at the current token are
 * considered, such an ordering is always possible;
 * without knowing the current token, we sort by end position descending,
 * then by start position ascending, which gives us:
 *
 *  - first the regions corresponding to start tags, beginning with the 'largest' region
 *
 *  - then the regions corresponding to end tags, again beginning with the 'largest' region
 *
 * The function uses bubble sort in order to retain the existing order of identical regions.
 *
 */
void
decode_sort_s_att_regions(void)
{
  int i, modified;

  for (i = 0; i < N_sar; i++)   /* initialise sort index */
    sar_sort_index[i] = i;

  /* repeat 'bubble' loop until no more modifications are made */
  modified = 1;

  while (modified) {
    modified = 0;
    for (i = 0; i < (N_sar-1); i++) {
      SAttRegion *a = &(s_att_regions[sar_sort_index[i]]); /* compare *a and *b */
      SAttRegion *b = &(s_att_regions[sar_sort_index[i+1]]);

      if ( a->end < b->end || (a->end == b->end && a->start > b->start) ) {
        int temp = sar_sort_index[i];
        /* swap sar_sort_index[i] and sar_sort_index[i+1] */
        sar_sort_index[i] = sar_sort_index[i+1];
        sar_sort_index[i+1] = temp;
        modified = 1;
        /* modified ordering, so we need another loop iteration */
      }
    }
  }
}

/**
 * Determines whether or not a given Attribute is in an array of Attributes.
 *
 * @param attr           The attribute to look for.
 * @param att_list       Pointer to the first member of the array (i.e. array name).
 * @param att_list_size  Upper bound of the array (the last member the function checks is attlist[attlist_size-1]).
 * @return               Boolean.
 */
int
decode_attribute_is_in_list(Attribute *attr, Attribute **att_list, int att_list_size)
{
  int i;
  for (i = 0; i < att_list_size; i++)
    if (att_list[i] == attr)
      return 1;
  return 0;
}

/**
 * Adds a specified Attribute to the global print_list array. Aborts the program
 * if that array is already full.
 *
 * @return Boolean.
 */
int
decode_add_attribute(Attribute *attr)
{
  if (print_list_index < MAX_ATTRS) {
    if (decode_attribute_is_in_list(attr, print_list, print_list_index)) {
      fprintf(stderr, "Attribute %s.%s added twice to print list (ignored)\n", corpus_id, attr->any.name);
      return 0;
    }

    print_list[print_list_index++] = attr;
    return 1;
  }
  else {
    fprintf(stderr, "Too many attributes (maximum is %d). Aborted.\n", MAX_ATTRS);
    decode_cleanup(2);
    return 0;
  }
}

/**
 * Check the context of the global printValues array, to check that no s-attribute in
 * it is declared more in the main print_list_index as well.
 *
 * If an attribute is found to be declared in nboth, a warning is printed.
 */
void
decode_verify_print_value_list(void)
{
  int i;
  for (i = 0; i < printValuesIndex; i++)
    if (decode_attribute_is_in_list(printValues[i], print_list, print_list_index))
      fprintf(stderr, "Warning: s-attribute %s.%s used with both -S and -V !\n", corpus_id, printValues[i]->any.name);
}

/**
 * Prints a starting tag for each s-attribute.
 */
void
decode_print_surrounding_s_att_values(int position)
{
  int i;
  char *tagname;

  for (i = 0; i < printValuesIndex; i++) {
    if (printValues[i]) {
      const char *sval;
      int snum = cl_cpos2struc(printValues[i], position);

      /* don't print tag if start position is not in region */
      if (snum >= 0) {
        /* if it is a p- or a- attribute, snum is a CL error (less than 0) */
        sval = decode_string_escape(cl_struc2str(printValues[i], snum));
        tagname = printValues[i]->any.name;

        switch (mode) {
        case ConclineMode:
          printf("<%s %s>: ", tagname, sval);
          break;

        case LispMode:
          printf("(VALUE %s \"%s\")\n", tagname, sval);
          break;

        case XMLMode:
          printf("<element name=\"%s\" value=\"%s\"/>\n", tagname, sval);
          break;

        case EncodeMode:
          /* pretends to be a comment, but has to be stripped before feeding output to encode */
          printf("# %s=%s\n", tagname, sval);
          break;

        case StandardMode:
        default:
          printf("<%s %s>\n", tagname, sval);
          break;
        }
      }
    }
  }
}

/**
 * Expand range <start> .. <end> to encompass full regions of s-attribute <context>.
 *
 * Function arguments are overwritten with the new values.
 */
void
decode_expand_context(int *start, int *end, Attribute *context)
{
  int dummy;
  int start_as_passed = *start, end_as_passed = *end;

  assert(end_as_passed >= start_as_passed);

  if (!cl_cpos2struc2cpos(context, start_as_passed, start, &dummy))
    *start = start_as_passed;
  if (!cl_cpos2struc2cpos(context, end_as_passed, &dummy, end))
    *end = end_as_passed;
}

/**
 * Prints out the requested attributes for a sequence of tokens
 * (or a single token if end_position == -1, which also indicates that we're not in matchlist mode).
 *
 * If the -c flag was used (and, thus, the context parameter is not NULL),
 * then the sequence is extended to the entire s-attribute region (intended for matchlist mode).
 *
 * If the additional skip_token parameter is true, only XML tags before/after each token are printed,
 * but not the tokens themselves (for subcorpus mode).
 */
void
decode_print_token_sequence(int start_position, int end_position, Attribute *context, int skip_token)
{
  int alg, aligned_start, aligned_end, aligned_start2, aligned_end2, rng_start, rng_end, snum;
  int start_context, end_context;
  int i, w;
  int beg_of_line;

  /* pointer used for values of p-attributes */
  const char *wrd;

  start_context = start_position;
  end_context = (end_position >= 0) ? end_position : start_position;

  if (context != NULL) {
    /* expand the start_context end_context numbers to the start
     * and end points of the containing region of the context s-attribute */
    decode_expand_context(&start_context, &end_context, context);

    /* indicate that we're showing context */
    switch (mode) {
    case LispMode:
      printf("(TARGET %d\n", start_position);
      if (end_position >= 0)
        printf("(INTERVAL %d %d)\n", start_position, end_position);
      break;
    case EncodeMode:
    case ConclineMode:
      /* nothing here */
      break;
    case XMLMode:
      printf("<context start=\"%d\" end=\"%d\"/>\n", start_context, end_context);
      break;
    case StandardMode:
    default:
      if (end_position >= 0)
        printf("INTERVAL %d %d\n", start_position, end_position);
      else
        printf("TARGET %d\n", start_position);
      break;
    }
  }

  /* some extra information in -L and -H modes */
  if (mode == LispMode && end_position != -1)
    printf("(CONTEXT %d %d)\n", start_context, end_context);
  else if (mode == ConclineMode && printnum)
    printf("%8d: ", start_position);

  /* now print the token sequence (including context) with all requested attributes */
  for (w = start_context; w <= end_context; w++) {

    /* extract s-attribute regions for start and end tags into s_att_regions[] */
    N_sar = 0;                  /* counter and index */
    for (i = 0; i < print_list_index; i++) {
      if (print_list[i]->any.type == ATT_STRUC) {
        if (0 <= (snum = cl_cpos2struc(print_list[i], w)) &&
            cl_struc2cpos(print_list[i], snum, &rng_start, &rng_end)  &&
            (w == rng_start || w == rng_end)
            ) {
          s_att_regions[N_sar].name = print_list[i]->any.name;
          s_att_regions[N_sar].start = rng_start;
          s_att_regions[N_sar].end = rng_end;
          if (cl_struc_values(print_list[i]))
            s_att_regions[N_sar].annot = cl_struc2str(print_list[i], snum);
          else
            s_att_regions[N_sar].annot = NULL;
          N_sar++;
        }
      }
    }
    decode_sort_s_att_regions();       /* sort regions to ensure proper nesting of start and end tags */

    /* show corpus positions with -n option */
    if (printnum)
      switch (mode) {
      case LispMode:
        printf("(%d ", w);
        break;
      case EncodeMode:
        printf("%8d\t", w);
        break;
      case ConclineMode:
        /* nothing here (shown at start of line in -H mode) */
        break;
      case XMLMode:
        /* nothing here */
        break;
      case StandardMode:
      default:
        printf("%8d: ", w);
        break;
      }
    else if (mode == LispMode)
      printf("(");            /* entire match is parenthesised list in -L mode */

    /* print start tags (s- and a-attributes) with -C,-H,-X */
    if ((mode == EncodeMode) || (mode == ConclineMode) || (mode == XMLMode)) {

      /* print a-attributes from print_list[] */
      for (i = 0; i < print_list_index; i++) {
        switch (print_list[i]->any.type) {
        case ATT_ALIGN:
          if (
              ((alg = cl_cpos2alg(print_list[i], w)) >= 0)
              && (cl_alg2cpos(print_list[i], alg,
                              &aligned_start, &aligned_end,
                              &aligned_start2, &aligned_end2))
              && (w == aligned_start)
              ) {
            if (mode == XMLMode) {
              printf("<align type=\"start\" target=\"%s\"", print_list[i]->any.name);
              if (printnum)
                printf(" start=\"%d\" end=\"%d\"", aligned_start2, aligned_end2);
              printf("/>\n");
            }
            else {
              printf("<%s", print_list[i]->any.name);
              if (printnum)
                printf(" %d %d", aligned_start2, aligned_end2);
              printf(">%c", (mode == EncodeMode) ? '\n' : ' ');
            }
          }
          break;
        default:
          /* ignore all other attribute types */
          break;
        }
      }

      /* print s-attributes from s_att_regions[] (using sar_sort_index[]) */
      for (i = 0; i < N_sar; i++) {
        SAttRegion *region = &(s_att_regions[sar_sort_index[i]]);

        if (region->start == w) {
          if (mode == XMLMode) {
            printf("<tag type=\"start\" name=\"%s\"", region->name);
            if (printnum)
              printf(" cpos=\"%d\"", w);
            if (region->annot)
              printf(" value=\"%s\"", decode_string_escape(region->annot));
            printf("/>\n");
          }
          else
            printf("<%s%s%s>%c",
                   region->name,
                   region->annot ? " " : "",
                   region->annot ? region->annot : "",
                   (mode == ConclineMode ? ' ' : '\n'));
        }
      }
    }

    if (!skip_token) {
      /* now print token with its attribute values (p-attributes only for -C,-H,-X) */
      if (mode == XMLMode) {
        printf("<token");
        if (printnum)
          printf(" cpos=\"%d\"", w);
        printf(">");
      }

      beg_of_line = 1;
      /* Loop printing each attribute for this cpos (w) */
      for (i = 0; i < print_list_index; i++) {

        switch (print_list[i]->any.type) {
        case ATT_POS:
          if ((wrd = decode_string_escape(cl_cpos2str(print_list[i], w))) != NULL) {
            switch (mode) {
            case LispMode:
              printf("(%s \"%s\")", print_list[i]->any.name, wrd);
              break;

            case EncodeMode:
              if (beg_of_line) {
                printf("%s", wrd);
                beg_of_line = 0;
              }
              else
                printf("\t%s", wrd);
              break;

            case ConclineMode:
              if (beg_of_line) {
                printf("%s", wrd);
                beg_of_line = 0;
              }
              else
                printf("/%s", wrd);
              break;

            case XMLMode:
              printf(" <attr name=\"%s\">%s</attr>", print_list[i]->any.name, wrd);
              break;

            case StandardMode:
            default:
              printf("%s=%s\t", print_list[i]->any.name, wrd);
              break;
            }
          }
          else {
            cl_error("(aborting) cl_cpos2str() failed");
            decode_cleanup(1);
          }
          break;

        case ATT_ALIGN:
          /* do not print in encode, concline or xml modes because already done (above) */
          if (mode != EncodeMode && mode != ConclineMode && mode != XMLMode) {
            if (
                ((alg = cl_cpos2alg(print_list[i], w)) >= 0)
                && (cl_alg2cpos(print_list[i], alg,
                    &aligned_start, &aligned_end,
                    &aligned_start2, &aligned_end2))
            ) {
              if (mode == LispMode)
                printf("(ALG %d %d %d %d)", aligned_start, aligned_end, aligned_start2, aligned_end2);
              else {
                printf("%d-%d==>%s:%d-%d\t",
                    aligned_start, aligned_end, print_list[i]->any.name, aligned_start2, aligned_end2);
              }
            }
            else if (cl_errno != CDA_OK) {
              cl_error("(aborting) alignment error");
              decode_cleanup(1);
            }
          }
          break;

        case ATT_STRUC:
          /* do not print in encode, concline or xml modes because already done (above) */
          if ((mode != EncodeMode) && (mode != ConclineMode) && (mode != XMLMode)) {
            if (cl_cpos2struc2cpos(print_list[i], w, &rng_start, &rng_end)) {
              /* standard and -L mode don't show tag annotations */
              printf(mode == LispMode ? "(STRUC %s %d %d)" : "<%s>:%d-%d\t",
                  print_list[i]->any.name,
                  rng_start, rng_end);
            }
            else if (cl_errno != CDA_OK)
              cl_error("(aborting) cl_cpos2struc2cpos() failed");
          }
          break;

        case ATT_DYN:
          /* dynamic attributes aren't implemented */
        default:
          break;
        }
      }

      /* print token separator (or end of token in XML mode) */
      switch (mode) {
      case LispMode:
        printf(")\n");
        break;
      case ConclineMode:
        printf(" ");
        break;
      case XMLMode:
        printf(" </token>\n");
        break;
      case EncodeMode:
      case StandardMode:
      default:
        printf("\n");
        break;
      }
    }

    /* now, after printing all the positional attributes, print end tags with -H,-C,-X */
    if (mode == EncodeMode  || mode == ConclineMode || mode == XMLMode) {

      /* print s-attributes from s_att_regions[] (using sar_sort_index[] in reverse order) */
      for (i = N_sar - 1; i >= 0; i--) {
        SAttRegion *region = &(s_att_regions[sar_sort_index[i]]);

        if (region->end == w) {
          if (mode == XMLMode) {
            printf("<tag type=\"end\" name=\"%s\"", region->name);
            if (printnum)
              printf(" cpos=\"%d\"", w);
            printf("/>\n");
          }
          else {
            printf("</%s>%c", region->name, (mode == ConclineMode ? ' ' : '\n'));
          }
        }
      }

      /* print a-attributes from print_list[] */
      for (i = 0; i < print_list_index; i++) {
        switch (print_list[i]->any.type) {
        case ATT_ALIGN:
          if (
              ((alg = cl_cpos2alg(print_list[i], w)) >= 0)
              && (cl_alg2cpos(print_list[i], alg,
                              &aligned_start, &aligned_end,
                              &aligned_start2, &aligned_end2))
              && (w == aligned_end)
              ) {
            if (mode == XMLMode) {
              printf("<align type=\"end\" target=\"%s\"", print_list[i]->any.name);
              if (printnum)
                printf(" start=\"%d\" end=\"%d\"", aligned_start2, aligned_end2);
              printf("/>\n");
            }
            else {
              printf("</%s", print_list[i]->any.name);
              if (printnum)
                printf(" %d %d", aligned_start2, aligned_end2);
              printf(">%c", (mode == EncodeMode) ? '\n' : ' ');
            }
          }
          break;

        default:
          /* ignore all other attribute types */
          break;
        }
      }

    } /* end of print end tags */

  }  /* end of match range loop: for w from start_context to end_context */

  /* end of match (for matchlist mode in particular) */
  if ((context != NULL) && (mode == LispMode))
    printf(")\n");
  else if (mode == ConclineMode)
    printf("\n");

  return;
}


/* *************** *\
 *      MAIN()     *
\* *************** */

/**
 * Main function for cwb-decode.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  Attribute *attr;
  Attribute *context = NULL;

  int sp;  /* start position of a match */
  int ep;  /* end position of a match */

  int w, cnt, next_cpos;

  char s[CL_MAX_LINE_LENGTH];      /* buffer for strings read from file */
  char *token;

  char *input_filename = NULL;
  FILE *input_file = stdin;
  int read_pos_from_file = 0;
  int subcorpus_mode = 0;

  int c;
  extern char *optarg;
  extern int optind;

  cl_startup();
  progname = argv[0];

  /* ------------------------------------------------- PARSE ARGUMENTS */

  first_token = -1;
  last_token = -1;
  maxlast = -1;

  /* use getopt() to parse command-line options */
  while((c = getopt(argc, argv, "+s:e:r:nLHCxXf:pSh")) != EOF)
    switch(c) {

      /* s: start corpus position */
    case 's':
      first_token = atoi(optarg);
      break;

      /* e: end corpus position */
    case 'e':
      last_token = atoi(optarg);
      break;

      /* r: registry directory */
    case 'r':
      if (registry_directory == NULL)
        registry_directory = optarg;
      else {
        fprintf(stderr, "%s: -r option used twice\n", progname);
        exit(2);
      }
      break;

      /* n: show cpos in -H mode */
    case 'n':
      printnum++;
      break;

      /* x: XML-compatible output in -C mode (-Cx) */
    case 'x':
      xml_compatible++;
      break;

      /* L,H,C,X: Lisp, Horizontal, Compact, and XML modes */
    case 'L':
      mode = LispMode;
      break;
    case 'H':
      mode = ConclineMode;
      break;
    case 'C':
      mode = EncodeMode;
      break;
    case 'X':
      mode = XMLMode;
      break;

      /* f: matchlist mode / read corpus positions from file */
    case 'f':
      input_filename = optarg;
      read_pos_from_file++;
      break;

      /* p: matchlist mode / read corpus positions from stdin */
    case 'p':
      read_pos_from_file++; /* defaults to STDIN if input_filename is NULL */
      break;

    case 'S':
       subcorpus_mode++; /* subcorpus mode; ignored without -f / -p */
       break;

      /* h: help page */
    case 'h':
      decode_usage(2);
      break;

    default:
      fprintf(stderr, "Illegal option. Try \"%s -h\" for more information.\n", progname);
      fprintf(stderr, "[remember that options go before the corpus name, and attribute declarations after it!]\n");
      decode_cleanup(2);
    }

  /* required argument: corpus id */
  if (optind < argc) {
    corpus_id = argv[optind++];

    if (!(corpus = cl_new_corpus(registry_directory, corpus_id))) {
      fprintf(stderr, "Corpus %s not found in registry %s . Aborted.\n",
              corpus_id,
              (registry_directory ? registry_directory : cl_standard_registry() ) );
      decode_cleanup(1);
    }
  }
  else {
    fprintf(stderr, "Missing argument. Try \"%s -h\" for more information.\n", progname);
    decode_cleanup(2);
  }


  /* now parse output flags (-P, -S, ...) [cnt is our own argument counter] */
  for (cnt = optind; cnt < argc; cnt++) {
    if (CL_STREQ(argv[cnt], "-c")) {         /* -c: context */
      if (!(context = cl_new_attribute(corpus, argv[++cnt], ATT_STRUC))) {
        fprintf(stderr, "Can't open s-attribute %s.%s . Aborted.\n", corpus_id, argv[cnt]);
        decode_cleanup(1);
      }
    }
    else if (CL_STREQ(argv[cnt], "-P")) {    /* -P: positional attribute */
      if (!(attr = cl_new_attribute(corpus, argv[++cnt], ATT_POS))) {
        fprintf(stderr, "Can't open p-attribute %s.%s . Aborted.\n", corpus_id, argv[cnt]);
        decode_cleanup(1);
      }
      else {
        if (cl_max_cpos(attr) > 0) {
          decode_add_attribute(attr);
          if (maxlast < 0)
            maxlast = cl_max_cpos(attr); /* determines corpus size */
        }
        else {
          fprintf(stderr, "Attribute %s.%s is declared, but not accessible (missing data?). Aborted.\n", corpus_id, argv[cnt]);
          decode_cleanup(1);
        }
      }
    }

    else if (CL_STREQ(argv[cnt], "-ALL")) {  /* -ALL: all p-attributes and s-attributes */
      for (attr = corpus->attributes; attr; attr = attr->any.next)
        if (attr->any.type == ATT_POS) {
          decode_add_attribute(attr);
          if (maxlast < 0)
            maxlast = cl_max_cpos(attr);
        }
        else if (attr->any.type == ATT_STRUC)
          decode_add_attribute(attr);
    }

    else if (CL_STREQ(argv[cnt], "-D")) {    /* -D: dynamic attribute (not implemented) */
      fprintf(stderr, "Sorry, dynamic attributes are not implemented. Aborting.\n");
      decode_cleanup(2);
    }

    else if (CL_STREQ(argv[cnt], "-A")) {    /* -A: alignment attribute */
      if (!(attr = cl_new_attribute(corpus, argv[++cnt], ATT_ALIGN))) {
        fprintf(stderr, "Can't open a-attribute %s.%s . Aborted.\n", corpus_id, argv[cnt]);
        decode_cleanup(1);
      }
      else
        decode_add_attribute(attr);
    }

    else if (CL_STREQ(argv[cnt], "-S") ) {    /* -S: structural attribute (as tags) */
      if ((attr = cl_new_attribute(corpus, argv[++cnt], ATT_STRUC)) == NULL) {
        fprintf(stderr, "Can't open s-attribute %s.%s . Aborted.\n",
                corpus_id, argv[cnt]);
        decode_cleanup(1);
      }
      else
        decode_add_attribute(attr);
    }

    else if (CL_STREQ(argv[cnt], "-V")) {    /* -V: show structural attribute values (with -p or -f) */
      if ((attr = cl_new_attribute(corpus, argv[++cnt], ATT_STRUC)) == NULL) {
        fprintf(stderr, "Can't open s-attribute %s.%s . Aborted.\n",
                corpus_id, argv[cnt]);
        decode_cleanup(1);
      }
      else if (!cl_struc_values(attr)) {
        fprintf(stderr, "S-attribute %s.%s does not have annotations. Aborted.\n",
                corpus_id, argv[cnt]);
        decode_cleanup(1);
      }
      else if (printValuesIndex >= MAX_PRINT_VALUES) {
        fprintf(stderr, "Too many -V attributes, sorry. Aborted.\n");
        decode_cleanup(1);
      }
      else
        printValues[printValuesIndex++] = attr;
    }

    else {
      fprintf(stderr, "Unknown flag: %s\n", argv[cnt]);
      decode_cleanup(2);
    }
  }
  /* ---- end of parse attribute declarations ---- */

  if (read_pos_from_file) {
    if (input_filename == NULL)
      input_filename = "-"; /* -p: use STDIN */
    input_file = cl_open_stream(input_filename, CL_STREAM_READ, CL_STREAM_MAGIC);
    if (input_file == NULL) {
      cl_error("Can't read matchlist file (-f)");
      exit(1);
    }
  }

  decode_verify_print_value_list();

  /* ------------------------------------------------------------ DECODE CORPUS */

  if (! read_pos_from_file) {
    /*
     * normal mode: decode entire corpus or specified range
     */

    if (maxlast < 0) {
      fprintf(stderr, "Need at least one p-attribute (-P flag). Aborted.\n");
      decode_cleanup(2);
    }

    if (first_token < 0 || first_token >= maxlast)
      first_token = 0;

    if (last_token < 0 || last_token >= maxlast)
      last_token = maxlast - 1;

    if (last_token < first_token) {
      fprintf(stderr, "Warning: output range #%d..#%d is empty. No output.\n", first_token, last_token);
      decode_cleanup(2);
    }

    if ( (mode == XMLMode) ||  ((mode == EncodeMode) && xml_compatible) ) {
      decode_print_xml_declaration();
      printf("<corpus name=\"%s\" start=\"%d\" end=\"%d\">\n",corpus_id, first_token, last_token);
    }

    /* decode_print_surrounding_s_att_values(first_token); */ /* don't do that in "normal" mode, coz it doesn't make sense */

    for (w = first_token; w <= last_token; w++)
      decode_print_token_sequence(w, -1, context, 0);

    if ( (mode == XMLMode) || ((mode == EncodeMode) && xml_compatible) )
      printf("</corpus>\n");
  }
  else {
    /*
     * matchlist/subcorpus mode: read (pairs of) corpus positions from stdin or file
     */

    if ( (mode == XMLMode) || ((mode == EncodeMode) && xml_compatible) ) {
      decode_print_xml_declaration();
      printf("<%s corpus=\"%s\">\n", subcorpus_mode ? "subcorpus" : "matchlist", corpus_id);
    }

    cnt = 0;
    next_cpos = 0;
    while (fgets(s, CL_MAX_LINE_LENGTH, input_file) != NULL) {

      token = strtok(s, " \t\n");

      if ((token != NULL) && is_num(token)) {
        sp = atoi(token);
        if (sp < 0 || sp >= maxlast) {
          fprintf(stderr, "Corpus position #%d out of range. Aborted.\n", sp);
          decode_cleanup(1);
        }

        ep = -1;
        if ((token = strtok(NULL, " \t\n")) != NULL) {
          if (!is_num(token)) {
            fprintf(stderr, "Invalid corpus position #%s . Aborted.\n", token);
            decode_cleanup(1);
          }
          else
            ep = atoi(token);
          if (ep < 0 || ep >= maxlast) {
            fprintf(stderr, "Corpus position #%d out of range. Aborted.\n", sp);
            decode_cleanup(1);
          }
          if (ep < sp) {
            fprintf(stderr, "Invalid range #%d .. #%d. Aborted.\n", sp, ep);
            decode_cleanup(1);
          }
        }

        cnt++;                  /* count matches in matchlist  */
        if (subcorpus_mode) {
          /* subcorpus mode */

          if (context)
            decode_expand_context(&sp, &ep, context);

          if (sp < next_cpos) {
            fprintf(stderr, "Error: matches must be non-overlapping and sorted in corpus order in -Sf/-Sp mode (input line #%d)\n", cnt);
            decode_cleanup(1);
          }

          if (sp > next_cpos)
            decode_print_token_sequence(next_cpos, sp - 1, NULL, 1);

          decode_print_token_sequence(sp, ep, NULL, 0); /* note that we've already expanded the context if necessary */

          next_cpos = ep + 1;
        }
        else {
          /* matchlist mode */

          if (mode == XMLMode) {
            printf("<match nr=\"%d\"", cnt);
            if (printnum)
              printf(" start=\"%d\" end=\"%d\"", sp, (ep >= 0) ? ep : sp);
            printf(">\n");
          }
          /* if not XMLMode, then there is nothing shown before range */

          decode_print_surrounding_s_att_values(sp);
          decode_print_token_sequence(sp, ep, context, 0);

          if (mode == XMLMode)
            printf("</match>\n");
          else if (mode != ConclineMode)
            /* blank line, unless in -H or -S mode */
            printf("\n");
        }
      }
      else {
        fprintf(stderr, "Invalid corpus position #%s . Aborted.\n", s);
        decode_cleanup(1);
      }
    }

    if (subcorpus_mode && next_cpos < maxlast)
      decode_print_token_sequence(next_cpos, maxlast - 1, NULL, 1);

    cl_close_stream(input_file);

    if ( (mode == XMLMode) || ((mode == EncodeMode) && xml_compatible) ) {
      printf("</%s>\n", subcorpus_mode ? "subcorpus" : "matchlist");
    }
  }

  decode_cleanup(0);
  return 0;                     /* just to keep gcc from complaining */
}
