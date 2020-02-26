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
#include <sys/types.h>
#include <sys/time.h>
#include <time.h>

#ifndef __MINGW__
#include <pwd.h>
#endif

#ifdef USE_TERMCAP
#include <curses.h>
#include <term.h>
#endif /* USE_TERMCAP */


#include "../cl/cl.h"

#include "ascii-print.h"

#include "cqp.h"
#include "output.h"
#include "print_align.h"
#include "options.h"
#include "corpmanag.h"
#include "concordance.h"
#include "attlist.h"

#include "print-modes.h"


/* ---------------------------------------------------------------------- */

/**
 * Convert string function for ASCII mode.
 *
 * This is used for the "printToken" function in the relevant PDR.
 *
 * @param s  The string to convert.
 * @return   s (ie no change).
 */
char *
ascii_convert_string(char *s)
{
  return s;
}

static char *ascii_print_field(FieldType field, int at_end);

/* ---------------------------------------------------------------------- */

/**
 * Print description record for ASCII print mode.
 */
PrintDescriptionRecord ASCIIPrintDescriptionRecord = {
  "%9d: ",                            /* CPOSPrintFormat */

  NULL,                               /* BeforePrintStructures */
  " ",                                /* PrintStructureSeparator */
  ": ",                               /* AfterPrintStructures */

  "<",                                /* StructureBeginPrefix */
  ">",                                /* StructureBeginSuffix */
  " ",                                /* StructureSeparator */
  "</",                               /* StructureEndPrefix */
  ">",                                /* StructureEndSuffix */

  NULL,                               /* BeforeToken */
  " ",                                /* TokenSeparator */
  "/",                                /* AttributeSeparator */
  NULL,                               /* AfterToken */

  NULL,                               /* BeforeField */
  NULL,                               /* FieldSeparator */
  NULL,                               /* AfterField */

  NULL,                               /* BeforeLine */
  "\n",                               /* AfterLine */

  NULL,                               /* BeforeConcordance */
  NULL,                               /* AfterConcordance */
  ascii_convert_string,               /* printToken */
  NULL                                /* don't highlight anchor points */
};

/**
 * Print description record for Highlighted-ASCII print mode.
 */
PrintDescriptionRecord ASCIIHighlightedPrintDescriptionRecord = {
  "%9d: ",                            /* CPOSPrintFormat */

  NULL,                               /* BeforePrintStructures */
  " ",                                /* PrintStructureSeparator */
  ": ",                               /* AfterPrintStructures */

  "<",                                /* StructureBeginPrefix */
  ">",                                /* StructureBeginSuffix */
  " ",                                /* StructureSeparator */
  "</",                               /* StructureEndPrefix */
  ">",                                /* StructureEndSuffix */

  NULL,                               /* BeforeToken */
  " ",                                /* TokenSeparator */
  "/",                                /* AttributeSeparator */
  NULL,                               /* AfterToken */

  NULL,                               /* BeforeField */
  NULL,                               /* FieldSeparator */
  NULL,                               /* AfterField */

  NULL,                               /* BeforeLine */
  "\n",                               /* AfterLine */

  NULL,                               /* BeforeConcordance */
  NULL,                               /* AfterConcordance */
  ascii_convert_string,
  ascii_print_field                   /* printField -> highlighting of anchor points */
};

/* ---------------------------------------------------------------------- */

/**
 * Boolean: have escapes been initialised?
 */
static int escapes_initialized = 0;

static char
  *sc_s_in,                           /**< Enter standout (highlighted) mode */
  *sc_s_out,                          /**< Exit standout mode */
  *sc_u_in,                           /**< Enter underline mode */
  *sc_u_out,                          /**< Exit underline mode */
  *sc_b_in,                           /**< Enter bold mode */
  *sc_b_out,                          /**< Exit bold mode (doesn't exist; this code turns off _all_ attributes) */
  *sc_bl_in,                          /**< Enter blink mode */
  *sc_bl_out,                         /**< Exit blink mode */
  *sc_all_out;                        /**< Turn off all display attributes */

/* flags for current display attributes */
int sc_s_mode = 0;                    /**< Boolean: following tokens will be shown in standout mode */
int sc_u_mode = 0;                    /**< Boolean: following tokens will be shown in underline mode */
int sc_b_mode = 0;                    /**< Boolean: following tokens will be shown in bold mode */


#ifndef USE_TERMCAP

/**
 * Dummy function
 */
char *
get_colour_escape(char colour, int foreground)
{
  return "";
}

/**
 * Dummy function
 */
char *
get_typeface_escape(char typeface)
{
  return "";
}

/**
 * Dummy function
 */
void
get_screen_escapes(void)
{
  sc_s_in = NULL;
  sc_s_out = NULL;
  sc_u_in = NULL;
  sc_u_out = NULL;
  sc_b_in = NULL;
  sc_b_out = NULL;
  sc_bl_in = NULL;
  sc_bl_out = NULL;
  sc_all_out = NULL;

  escapes_initialized++;
}

#else /* USE_TERMCAP */


void
get_screen_escapes(void)
{
  int status, l;
  char *term;

  sc_s_in = "";
  sc_s_out = "";
  sc_u_in = "";
  sc_u_out = "";
  sc_b_in = "";
  sc_b_out = "";
  sc_bl_in = "";
  sc_bl_out = "";

  if ((term = getenv("TERM")) == NULL)
    return;

  if ((setupterm(term, 1, &status) == ERR) || (status != 1)) {
    return;
  }

  /* turn off all attributes */
  sc_all_out = tigetstr("sgr0");
  if (sc_all_out == NULL) sc_all_out = "";

  /* Linux terminfo bug? fix: tigetstr("sgr0") returns an extra ^O (\x0f) character appended to the escape sequence
     (this may be some code used internally by the ncurses library).
     Since we printf() the escape sequences directly, we have to remove the extra character or 'less -R' will get confused. */
  l = strlen(sc_all_out);
  if ((l > 0) && (sc_all_out[l-1] == '\x0f')) {
    sc_all_out = cl_strdup(sc_all_out);
    sc_all_out[l-1] = 0;        /* just chop of the offending character */
  }


  /* standout mode */
  sc_s_in = tigetstr("smso");
  if (sc_s_in == NULL) sc_s_in = "";
  sc_s_out = tigetstr("rmso");
  if (sc_s_out == NULL) sc_s_out = "";

  /* underline */
  sc_u_in = tigetstr("smul");
  if (sc_u_in == NULL) sc_u_in = sc_s_in;
  sc_u_out = tigetstr("rmul");
  if (sc_u_out == NULL) sc_u_out = sc_s_out;

  /* bold */
  sc_b_in = tigetstr("bold");
  if (sc_b_in == NULL) {
    sc_b_in = sc_s_in;
    sc_b_out = sc_s_out;
  }
  else {
    sc_b_out = tigetstr("sgr0"); /* can't turn off bold explicitly */
    if (sc_b_out == NULL) sc_b_out = "";
  }

  /* blink */
  sc_bl_in = tigetstr("blink");
  if (sc_bl_in == NULL) {
    sc_bl_in = sc_s_in;
    sc_bl_out = sc_s_out;
  }
  else {
    sc_bl_out = sc_all_out;      /* can't turn off blinking mode explicitly */
  }

  escapes_initialized++;

  /* in highlighted mode, switch off display attributes at end of line (to be on the safe side) */
  ASCIIHighlightedPrintDescriptionRecord.AfterLine = cl_malloc(strlen(sc_all_out) + 2);
  sprintf(ASCIIHighlightedPrintDescriptionRecord.AfterLine,
          "%s\n", sc_all_out);

  /* print cpos in blue, "print structures" in pink if we're in coloured mode */
  if (use_colour) {
    char *blue = get_colour_escape('b', 1);
    char *pink = get_colour_escape('p', 1);
    char *normal = get_typeface_escape('n');
    char *bold = get_typeface_escape('b');

    ASCIIHighlightedPrintDescriptionRecord.CPOSPrintFormat = cl_malloc(strlen(blue) + strlen(normal) + 8);
    sprintf(ASCIIHighlightedPrintDescriptionRecord.CPOSPrintFormat,
            "%s%c9d:%s ", blue, '%', normal);
    ASCIIHighlightedPrintDescriptionRecord.BeforePrintStructures = cl_malloc(strlen(pink) + strlen(bold) + 4);
    sprintf(ASCIIHighlightedPrintDescriptionRecord.BeforePrintStructures,
            "%s%s", pink, bold);
    ASCIIHighlightedPrintDescriptionRecord.AfterPrintStructures = cl_malloc(strlen(normal) + 6);
    sprintf(ASCIIHighlightedPrintDescriptionRecord.AfterPrintStructures,
            ":%s ", normal);
  }
}

/* typeface = b=bold, u=underlined, s=standout, n=normal */
char *
get_typeface_escape(char typeface)
{
  if (!escapes_initialized)
    get_screen_escapes();
  if (!escapes_initialized)
    return "";                        /* initialisation failed */
  switch (typeface) {
  case 'b': return sc_b_in;
  case 'u': return sc_u_in;
  case 's': return sc_s_in;
  case 'n': return sc_all_out;        /* also switches off colour */
  default:
    fprintf(stderr, "Internal error: unknown typeface '%c'.\n", typeface);
    return "";
  }
}


/* interface to the terminal formatting escape sequences (with dummy replacements if USE_TERMCAP is not set) */
/* colour: r=red g=green b=blue, p=pink, y=yellow, c=cyan */
char *
get_colour_escape(char colour, int foreground) {
  if (use_colour) {
    if (*(get_typeface_escape('n')) == 0)
      return "";                /* don't try colour if terminal doesn't support typefaces */
    if (foreground) {
      switch(colour) {
      case 'r': return "\x1B[0;31m";
      case 'g': return "\x1B[0;32m";
      case 'y': return "\x1B[0;33m";
      case 'b': return "\x1B[0;34m";
      case 'p': return "\x1B[0;35m";
      case 'c': return "\x1B[0;36m";
      default:
        fprintf(stderr, "Internal error: unknown colour '%c'.\n", colour);
        return "\x1B[0m";
      }
    }
    else {
      switch(colour) {
      case 'r': return "\x1B[0;41m";
      case 'g': return "\x1B[0;42m";
      case 'y': return "\x1B[0;43m";
      case 'b': return "\x1B[0;44m";
      case 'p': return "\x1B[0;45m";
      case 'c': return "\x1B[0;46m";
      default:
        fprintf(stderr, "Internal error: unknown colour '%c'.\n", colour);
        return "\x1B[0m";
      }
    }
  }
  else {
    return "";
  }
}


#endif /* USE_TERMCAP */



/*
 * ======================================================================
 * Print Concordance Line
 * ======================================================================
 */

/**< 'static' return value of ascii_print_field() */
char sc_before_token[256];

/**
 * Print the string required before or after the representation of a token that is
 * at one of the anchor points (match, matchend, target, keyword).
 */
static char *
ascii_print_field(FieldType field, int at_end)
{

  sc_before_token[0] = '\0';                /* sets sc_before_token to "" */

  /* if targets are shown, print target number at end of target/keyword fields */
  if (show_targets && at_end && (field==TargetField || field==KeywordField)) {
    char *red = get_colour_escape('r', 1);
    /* if colours are activated & seem to work, print target number in red, otherwise print in parens */
    if (*red != 0) {
      /* must set colour first, then all other current attributes */
      sprintf(sc_before_token + strlen(sc_before_token),
              "%s%s%s%s%s%d",
              sc_all_out,
              red,
              (sc_s_mode) ? sc_s_in : "",
              (sc_u_mode) ? sc_u_in : "",
              (sc_b_mode) ? sc_b_in : "",
              field - TargetField);       /* should yield 0 .. 9  */
    }
    else {
      sprintf(sc_before_token + strlen(sc_before_token),
               "(%d)", field - TargetField /* should yield 0 .. 9 */
        );
    }
  }

  /* set the display attribute flags */
  switch (field) {

  case MatchField:
    if (at_end)
      sc_s_mode = 0;
    else
      sc_s_mode = 1;
    break;

  case KeywordField:
    if (at_end)
      sc_u_mode = 0;
    else
      sc_u_mode = 1;
    break;

  case TargetField:
    if (at_end)
      sc_b_mode = 0;
    else
      sc_b_mode = 1;
    break;

  case NoField:
  default:
    break;
  }

  /* now compose escape sequence which has to be sent to the terminal (setting _all_ attributes to their current values) */
  sprintf(sc_before_token + strlen(sc_before_token),
          "%s%s%s%s",
          sc_all_out,                /* first switch off all attributes, then set the active ones in order standout, underline, bold */
          (sc_s_mode) ? sc_s_in : "",
          (sc_u_mode) ? sc_u_in : "",
          (sc_b_mode) ? sc_b_in : "");

  return sc_before_token;
}

/**
 * Prints a line of text (which will have been previously exrtracted from a corfpus
 * linked to the present corpus by an a-attribute) with a brief character-mode
 * start-of-line flag ("-->$att_name: ").
 *
 * @param stream          Destination for the output.
 * @param highlighting    Boolean: if true, use colour/bold highlighting for the leading indicator on the line.
 * @param attribute_name  The name of the aligned corpus: printed in the leading indicator
 * @param line            Character data of the line of aligned-corpus data to print. This is treated as opaque.
 */
void
ascii_print_aligned_line(FILE *stream,
                         int highlighting,
                         char *attribute_name,
                         char *line)
{
  if (highlighting) {
    char *red = get_colour_escape('r', 1);
    char *bold = get_typeface_escape('b');
    char *normal = get_typeface_escape('n');
    fprintf(stream, "%s%s-->%s:%s %s\n",
            red, bold,
            attribute_name,
            normal,
            line);
  }
  else
    fprintf(stream, "-->%s: %s\n", attribute_name, line);
}


/* print the concordance line for the target_word on the screen */
/**
 * Prints a concordance line.
 * (documentation not complete)_
 *
 *
 */
void
print_concordance_line(FILE *outfd,
                       CorpusList *cl,
                       int element,
                       int apply_highlighting,
                       AttributeList *strucs)
{
  char *outstr;
  int length, string_match_begin_pos, string_match_end_pos;
  ConcLineField clf[NoField];        /* NoField is largest field code (not used by us) */
  PrintDescriptionRecord *pdr;

  if ((cl == NULL) || (outfd == NULL)) {
    cqpmessage(Error, "Empty corpus or empty output file");
    return;
  }

  if (element < 0 || element >= cl->size) {
    cqpmessage(Error, "Illegal element in print_concordance_line");
    return;
  }

  if (escapes_initialized == 0)
    get_screen_escapes();

  sc_s_mode = 0;                /* reset display flags */
  sc_u_mode = 0;
  sc_b_mode = 0;

  /* ---------------------------------------- concordance fields */

  clf[MatchField].type = MatchField;
  clf[MatchField].start_position = cl->range[element].start;
  clf[MatchField].end_position = cl->range[element].end;

  clf[MatchEndField].type = MatchEndField; /* unused, because we use MatchField for the entire match */
  clf[MatchEndField].start_position = -1;
  clf[MatchEndField].end_position = -1;

  clf[KeywordField].type = KeywordField;
  if (cl->keywords) {
    clf[KeywordField].start_position = cl->keywords[element];
    clf[KeywordField].end_position = cl->keywords[element];
  }
  else {
    clf[KeywordField].start_position = -1;
    clf[KeywordField].end_position = -1;
  }

  clf[TargetField].type = TargetField;
  if (cl->targets) {
    clf[TargetField].start_position = cl->targets[element];
    clf[TargetField].end_position = cl->targets[element];
  }
  else {
    clf[TargetField].start_position = -1;
    clf[TargetField].end_position = -1;
  }

  if (apply_highlighting)
    pdr = &ASCIIHighlightedPrintDescriptionRecord;
  else
    pdr = &ASCIIPrintDescriptionRecord;

  outstr = compose_kwic_line(cl->corpus,
                             cl->range[element].start, cl->range[element].end,
                             &CD,
                             &length,
                             &string_match_begin_pos, &string_match_end_pos,
                             left_delimiter, right_delimiter,
                             NULL, 0, NULL,
                             clf, NoField, /* NoField = # of entries in clf[] */
                             ConcLineHorizontal,
                             pdr,
                             0, NULL);

  fputs(outstr, outfd);
  cl_free(outstr);

  if (pdr->AfterLine)
    fputs(pdr->AfterLine, outfd);

  if (CD.alignedCorpora != NULL)
    printAlignedStrings(cl->corpus,
                        &CD,
                        cl->range[element].start, cl->range[element].end,
                        apply_highlighting,
                        outfd);
}


/**
 * Prints a header for a "cat" command.
 *
 * Note that the "corpus" here refers to a subcorpus IE query result.
 */
void
ascii_print_corpus_header(CorpusList *cl, FILE *stream)
{
  time_t now;

#ifndef __MINGW__
  struct passwd *pwd = NULL;
#endif

  int i;

  time(&now);
  /*   pwd = getpwuid(geteuid()); */
  /* disabled because of incompatibilities between different Linux versions */

  fputc('#', stream);
  for (i = 0; i < 75; i++)
    fputc('-', stream);
  fputc('\n', stream);

  fprintf(stream,
          "#\n"
          "# User:    %s (%s)\n"
          "# Date:    %s"
          "# Corpus:  %s (%s)\n"
          "# Name:    %s:%s\n"
          "# Size:    %d intervals/matches\n",
#ifndef __MINGW__
          (pwd ? pwd->pw_name : "<unknown>"),
          (pwd ? pwd->pw_gecos  : "<unknown>"),
#else
          "<unknown>",
          "<unknown>",
#endif
          ctime(&now),
          (cl->corpus && cl->corpus->registry_name ? cl->corpus->registry_name : "<Unknown Corpus>"),
          (cl->corpus && cl->corpus->name ? cl->corpus->name : "<Unknown Corpus>"),
          cl->mother_name, cl->name,
          cl->size);
  fprintf(stream,
          "# Context: %d %s left, %d %s right\n"
          "#\n",
          CD.left_width,
          (CD.left_type == CHAR_CONTEXT) ? "characters" :
          ((CD.left_type == WORD_CONTEXT) ? "words" :
           (CD.left_structure_name) ? CD.left_structure_name : "???"),
          CD.right_width,
          (CD.right_type == CHAR_CONTEXT) ? "characters" :
          ((CD.right_type == WORD_CONTEXT) ? "words" :
           (CD.right_structure_name) ? CD.right_structure_name : "???"));

  if (cl->query_corpus && cl->query_text)
    fprintf(stream, "# Query: %s; %s\n", cl->query_corpus, cl->query_text);


  fputc('#', stream);
  for (i = 0; i < 75; i++)
    fputc('-', stream);
  fputc('\n', stream);
}

/**
 * Prints out the body of a concordance, ASCII style.
 * @see print_output
 */
void
ascii_print_output(CorpusList *cl,
                   FILE *outfd,
                   int interactive,
                   ContextDescriptor *cd,
                   int first,
                   int last)
{
  int real_line, i;
  int output_line = 1;

  if (first < 0)
    first = 0;
  if ((last >= cl->size) || (last < 0))
    last = cl->size - 1;

  for (i = first; (i <= last) && !cl_broken_pipe; i++) {
    real_line = cl->sortidx ? cl->sortidx[i] : i;

    if (GlobalPrintOptions.number_lines) {
      fprintf(outfd, "%6d.\t", output_line);
      output_line++;
    }

    print_concordance_line(outfd, cl, real_line,
                           interactive && highlighting,
                           cd->printStructureTags);
  }
}

void
ascii_print_group(Group *group, int expand, FILE *fd)
{
  int source_id, target_id, count;
  int has_source = (group->source_attribute != NULL);

  char *source_s = "(null)";
  char *target_s = "(null)";

  int cell, last_source_id;
  int nr_targets;

  /* some pretty printing stuff left over from Oli */
  last_source_id = -666;
  nr_targets = 0;

  for (cell = 0; (cell < group->nr_cells) && !cl_broken_pipe; cell++) {

    source_id = group->count_cells[cell].s;
    source_s  = Group_id2str(group, source_id, 0);

    target_id = group->count_cells[cell].t;
    target_s  = Group_id2str(group, target_id, 1);
    count     = group->count_cells[cell].freq;

    if (pretty_print) {
      if (source_id != last_source_id) {
        last_source_id = source_id;
        nr_targets = 0;
      }

      /* separator bar between groups */
      if (cell == 0 || (group->is_grouped && nr_targets == 0))
        fprintf(fd, SEPARATOR);

      fprintf(fd, "%-28s  %-28s\t%6d\n", nr_targets == 0 ? source_s : " ", target_s, count);
    }
    else {
      if (source_id < 0)
        source_s = "";        /* don't print "(none)" or "(all)" in plain mode (just empty string) */
      if (target_id < 0)
        target_s = "";
      if (has_source)
        fprintf(fd, "%s\t%s\t%d\n", source_s, target_s, count);
      else
        fprintf(fd, "%s\t%d\n", target_s, count);
    }

    if (expand) {
      /* TODO comment below indicates something should be here -- AH 2019 */
      /* Ausgabe der entsprechenden Konkordanzzeilen??? */
    }

    nr_targets++;
  }
}
