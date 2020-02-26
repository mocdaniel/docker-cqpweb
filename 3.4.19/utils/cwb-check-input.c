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
 *
 *
 * This was a temporary, experuimental "fiddle" with unicode programming.
 *
 *
 *
 */






/* includes */

#include <glib.h>
#include "../cl/globals.h"
#include "../cl/list.h"



/* defines */

/** Input buffer size: copied from cwb-encode */
#define MAX_INPUT_LINE_LENGTH  65536



/* cwb-check-input global variables */

int line_no = 0;                        /**< line number of the line in the input file currently being checked; first == 1 */
int established_number_of_p_atts = 0;   /**< first p-att line established number of tags; anything that deviates then counts as an error */
int silent = 0;                         /**< hide messages */
int verbose = 0;                        /**< show messages about fixable errors in repair mode */
int print_fixable_errors = 0;           /**< deduced from mode, silent & verbose */
int print_unfixable_errors = 0;         /**< deduced from mode, silent & verbose */
int errors_detected = 0;                /**< number of errors found so far */
int xml_aware = 0;                      /**< ignore <? and <! lines */
int skip_empty_lines = 0;               /**< check for empty lines */
int strip_blanks = 0;                   /**< check for leading and trailing blanks in input and token annotations? */
int check_nesting = 0;                  /**< check perfect nesting of XML? */
FILE *input_fd = NULL;                  /**< file handle for the input file */
char *input_file = NULL;                /**< filename of the input file */
FILE *output_fd = NULL;                 /**< file handle for the output file; also used for boolean tests on whether we are repairing or not */
char *output_file = NULL;               /**< filename of the output file */
char *charset_label = "ascii";          /**< label of character set used for checking encoding */
CorpusCharset charset;                  /**< character set used for checking encoding */
cl_string_list hierarchy = NULL;        /**< string list for keeping track of the XML hierarchy */

/** name of the currently running program */
char *progname = NULL;









/* program-specific functions */

/**
 * convenience function with which to abort the program if file-write fails.
 */
void
cwbci_file_write_abort(void)
{
  fprintf(stderr, "Error writing to output file <%s>, program aborts.\n", output_file);
  fclose(output_fd);
  fclose(input_fd);
  exit(1);
}

/**
 * checks whether the encoding of a given string is OK.
 * (Maybe move to the CL later?? in which case the charset should be a parameter,
 * as a global variable cannot be assumed in all programs.)
 * Returns boolean.
 */
int
cwbci_encoding_ok(char *str)
{
  switch(charset){
  case ascii:
    for ( ; *str != 0 ; str++ )
      /* something to check: will hex values work here, given string is signed character array variable? */
      if (*str > 0x7f)
        return 0;
    break;
  case latin1:
    if (*str > 0x7f && *str < 0xa0)
      return 0;
    break;
  case utf8:
    return g_utf8_validate((gchar *)str, -1, NULL);
  default:
    /* TODO -- dunno what to do here, esp. about latin[2-9] */
    break;
  }

  /* if no errors detected above, return true */

  return 1;
}

int
cwbci_is_wordchar(char c)
{
  if (c >= 'a' && c <= 'z')
    return 1;
  if (c == '_')
    return 1;
  if (c >= 'A' && c <= 'Z')
    return 1;
  return 0;
}

/**
 * Function for inner-loop in cwbci_check_lin().
 *
 * IMPORTANT NOTE: if to be used elsewhere will need adapting,
 * because it assumes all utf8 is well-validated and that
 * blanks will be deleted from the line, starting with the
 * first character.
 */
int
cwbci_begins_with_blank(char *str)
{
  gunichar uc;

  if (charset != utf8) {
    /* iso-8859-x all agree on no-break-space (xa0) and ascii whitespace */
    uc = (gunichar)str[0];
  }
  else {
    uc = g_utf8_get_char_validated(str, -1);
    if (uc < 0)
      /* the string is invalid utf8, so return 1
       * (we are halfway through erasing a blank
       * with a high unicode value e.g. 20xx) */
      return 1;
  }

  return g_unichar_isspace(uc);
}


void
cwbci_report_error_fixable(char *msg)
{
  errors_detected++;
  if (print_fixable_errors)
    fprintf(stderr, "%s (at line %d)\n", msg, line_no);
}
void
cwbci_report_error_unfixable(char *msg)
{
  errors_detected++;
  if (print_unfixable_errors)
    fprintf(stderr, "%s(at line %d)\n", msg, line_no);
}



void
cwbci_check_line(char *line)
{
  void *ptr;
  int l;
  int i;
  int last_idx, p_att_count;
  /* TODO use a proper macro number here, not a 65K macro! */
  char element[MAX_INPUT_LINE_LENGTH];

  l = strlen(line);

  /* check encoding */
  if (!cwbci_encoding_ok(line)) {
    cwbci_report_error_unfixable("Bad character encoding!!");
    /* we can't really do anything with this line other than report it */
  }



  /* check for empty line */
  if (l == 0) {
    cwbci_report_error_fixable("Empty line");
    if (skip_empty_lines){
      /* don't write anything; and we can hop over any further checks */
      return;
    }
  }


  /* check for \r\n at end of line */
  if (line[l-1] == '\r') {
    cwbci_report_error_fixable("Windows-style line break (CR-LF)");
    line[l-1] = '\0';
    l--;
  }


  if (strip_blanks) {
    /* check for other blanks at the end of the line * /
    while (1){
    }
    / * check for blanks at the start of the line */
    while (cwbci_begins_with_blank(line)) {
      cwbci_report_error_fixable("Space character at start of line");
      strcpy(line, line+1);
    }
    l = strlen(line);
  }


  /* now, divide up into checks for s-att lines versus p-att lines */
  if (line[0] == '<') {
    /* s-att line specific checks */

    /* check that last character is '>' */
    if (line[l-1] != '>')
      cwbci_report_error_unfixable("S-attribute line which does not end in >!");

    if (xml_aware && (line[1] == '!' || line[1] == '?') )
      /* this is an xml element which we can ignore */
      ;
    else {
      /* check for perfect nesting */
      if (check_nesting){
        if (line[1] == '/') {
          /* closing element: check it matches the previous element on the stack, and delete that */
          strcpy(element, line+2);
          for (i = 0 ; i < l-2; i++) {
            if (! cwbci_is_wordchar(element[i]) ) {
              element[i] = '\0';
              break;
            }
          }
          last_idx = cl_string_list_size(hierarchy)-1;
          if (strcmp(element, cl_string_list_get(hierarchy, last_idx)) == 0){
            ptr = cl_string_list_get(hierarchy, last_idx);
            cl_free(ptr);
            hierarchy->size -= 1;
          }
          else {
            cwbci_report_error_unfixable("Breach in perfect nesting");
          }
        }
        else {
          /* opening element: add an element to the stack */
          strcpy(element, line+1);
          for (i = 0 ; i < l-1; i++) {
            if (! cwbci_is_wordchar(element[i]) ) {
              element[i] = '\0';
              break;
            }
          }
          cl_string_list_append(hierarchy, cl_strdup(element));
        }
      }
    }
  }
  else {
    /* p-att line specific checks */

    p_att_count = 1;
    for (i = 0 ; i < l ; i++)
      if (line[i] == '\t')
        p_att_count++;
    if (established_number_of_p_atts > 0 ) {
      if (p_att_count != established_number_of_p_atts)
        cwbci_report_error_unfixable("Line contains wrong number of p-attributes.");
    }
    else
      established_number_of_p_atts = p_att_count;
  }




  /* OK, all checks are done, and no fatal errors encountered.
   * So write the line, if necessary. Then follow with '\n'.
   */
  if (output_fd) {
    if (EOF == fputs(line, output_fd)) {
      cwbci_file_write_abort();
    }
    if (EOF == fputc('\n', output_fd)) {
      cwbci_file_write_abort();
    }
  }
}









void
cwbci_usage(void)
{
  fprintf(stderr, "\n");
  fprintf(stderr, "Usage:  %s -f <file> [options]\n\n", progname);
  fprintf(stderr, "Reads verticalised text from an input file with -f option and checks its\n");
  fprintf(stderr, "formatting. The following problems may be checked for, depending on what\n");
  fprintf(stderr, "options have been set:\n");
  fprintf(stderr, "  Character encoding - always checked.\n");
  fprintf(stderr, "  Use of Windows-style linebreaks - always checked. (R)\n");
  fprintf(stderr, "  Consistent number of p-attributes in each line - always checked.\n");
  fprintf(stderr, "  Whitespace at start / end of line - checked if -B is set. (R)\n");
  fprintf(stderr, "  Misplaced closing attribute on an XML line - always checked.\n");
  fprintf(stderr, "  S-attribute (XML) perfect nesting - checked if -n is set.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "(R) = can be fixed when running in repair mode.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "All error messages go to STDERR.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "If an output file is specified, problems will be fixed if possible; if not\n");
  fprintf(stderr, "possible, checking will be stopped. Problems that can be fixed will not be\n");
  fprintf(stderr, "reported, except if -v has been set.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "If no output file is specified, all problems will be reported, unless -q\n");
  fprintf(stderr, "has been set.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "These two modes are, respectively, \"repair mode\" and \"check mode\". In\n");
  fprintf(stderr, "either mode, when the program finishes, a count of errors will be reported.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "Note: it is entirely possible for the detection of one error on a line to block\n");
  fprintf(stderr, "the identification of other errors - so always run this program more than once.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "  -f <file> read input from <file>\n");
  fprintf(stderr, "  -o <file> write repaired output to <file>\n");
  fprintf(stderr, "  -c <charset> specify corpus character set (instead of the default ascii)\n");
  fprintf(stderr, "     * valid charsets: ascii ; latin1 to latin9 ; utf8\n");
  fprintf(stderr, "     * NB: cwb-check-input does NOT default to latin1, unlike cwb-encode!\n");
  fprintf(stderr, "  -B        check for leading/trailing blanks on input lines and p-atts\n");
  fprintf(stderr, "  -n        check for perfect nesting of XML elements\n");
  fprintf(stderr, "  -s        check for empty lines in input \n");
  fprintf(stderr, "  -w        do extra checks for CQPweb compatibility \n");
  fprintf(stderr, "  -x        XML-aware (ignore <!.. and <?..)\n");
  fprintf(stderr, "  -v        verbose (show warnings for fixable problems in repair mode)\n");
  fprintf(stderr, "  -q        quiet (suppresses all warnings in either mode)\n");
  fprintf(stderr, "  -h        show this help page\n\n");
  /* commented out for now cos I can't work out how to get it to compile like the others do */
/*  fprintf(stderr, "Part of the IMS Open Corpus Workbench v" VERSION "\n\n");*/
  exit(2);
}












/**
 * Parses commandline options for cwb-check-input and sets global variables accordingly.
 */
void
cwbci_parse_options(int argc, char **argv)
{
  int c;
  extern char *optarg;
  extern int optind;

  while((c = getopt(argc, argv, "f:o:c:Bnswxvqh")) != EOF)
    switch(c) {

    /* -f: input file */
    case 'f':
      input_file = cl_strdup(optarg);
      break;

    /* -o: input file */
    case 'o':
      output_file = cl_strdup(optarg);
      break;

    /* -c: specifies a character set */
    case 'c':
      charset_label = cl_charset_name_canonical(optarg);
      if (charset_label == NULL)
        fprintf(stderr, "Invalid character set specified with the -c flag! Program aborts.\n");
      break;

    /* -B: strip leading and trailing blanks from lines */
    case 'B':
      strip_blanks++;
      break;

    /* -n: check for perfect nesting of XML elements */
    case 'n':
      check_nesting++;
      break;

    /* -s: check for empty lines */
    case 's':
      skip_empty_lines++;
      break;

    /* -x: xml aware */
    case 'X':
      xml_aware++;
      break;

    /* -v: show progress messages */
    case 'v':
      verbose++;
      break;

    /* -q: suppress warnings (quiet mode) */
    case 'q':
      silent++;
      break;

    case 'h':
    default:
      cwbci_usage();
      break;

    } /* end switch */
  /* end while */

  /* checks on compulsory options */
  if (!input_file) {
    fprintf(stderr, "You MUST specify a file with the -f flag! Program aborts.\n");
  }

  /* deductions to be made from the options... */

  charset = cl_charset_from_name(charset_label);

  if (output_file)
    print_fixable_errors = verbose;
  else
    print_fixable_errors = !silent;

  print_unfixable_errors = !silent;

  if (check_nesting)
    hierarchy = cl_new_string_list();

}










/**
 * Main function for cwb-check-input
 */
int
main(int argc, char **argv)
{
  /* variables */

  char line[MAX_INPUT_LINE_LENGTH];
  char *curr;
  char file_done = 0;


  /* initialise global variables */
  cl_startup();
  progname = argv[0];

  cwbci_parse_options(argc, argv);    /* parse command-line options */

  /* The read file is opened as binary to allow checking for \r\n */

  /* open the input file */
  if ((input_fd = fopen(input_file, "rb")) == NULL) {
    fprintf(stderr, "Can't open input file <%s>.", input_file);
    exit(1);
  }
  /* open the output file */
  if (output_file) {
    if ((output_fd = fopen(input_file, "w")) == NULL) {
      fprintf(stderr, "Can't open output file <%s>.", output_file);
      exit(1);
    }
  }

  /* loop on all lines */
  while (1) {
    ++line_no;
    /* load everything to the next line break; remove the \n and null-terminate */
    curr = line;
    while (curr < line+MAX_INPUT_LINE_LENGTH){
      if ( 1 > fread(curr, sizeof(char), 1, input_fd) )
        file_done = 1;
      if (*curr == '\n' || file_done){
        *curr = 0;
        break;
      }
      curr++;
    }
    if (file_done && strlen(line) == 0)
      break;
    cwbci_check_line(line);
  }

  /* close the files */
  fclose(input_fd);
  if (output_fd)
    fclose(output_fd);

  /* the final report */
  fprintf(stderr, "%s detected %d errors in %s\n\n", progname, errors_detected, input_file);

  cl_free(input_file);
  cl_free(output_file);
  if (hierarchy) {
    cl_free_string_list(hierarchy);
    cl_delete_string_list(hierarchy);
  }

  exit(0);
}
