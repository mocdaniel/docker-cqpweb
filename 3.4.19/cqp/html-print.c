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

#include "html-print.h"

#include <stdio.h>
#include <string.h>
#include "../cl/globals.h"
#include "../cl/corpus.h"
#include "../cl/attributes.h"
#include "../cl/cdaccess.h"

#include <sys/types.h>
#include <unistd.h>
#include <stdlib.h>

#include "cqp.h"
#include "options.h"
#include "corpmanag.h"
#include "concordance.h"
#include "attlist.h"
#include "print_align.h"

/* ---------------------------------------------------------------------- */

#include <sys/types.h>
#include <sys/time.h>
#include <time.h>

#ifndef __MINGW__
#include <pwd.h>
#endif

/* ---------------------------------------------------------------------- */

/* -- seems to be unused (purpose unclear) --
#define NR_FIELD_NAMES 3
static char *field_names[] = { "b",
                               "em",
                               "sl"
};
*/

/* ------------------------------------------------------ */

char *html_print_field(FieldType field, int start);

/* ------------------------------------------------------- */

PrintDescriptionRecord
HTMLPrintDescriptionRecord = {
  "<EM>%d:</EM>",               /* CPOSPrintFormat */

  "<EM>",                       /* BeforePrintStructures */
  " ",                          /* PrintStructureSeparator */
  "</EM>",                      /* AfterPrintStructures */

  "&lt;",                       /* StructureBeginPrefix */
  "&gt;",                       /* StructureBeginSuffix */

  " ",                          /* StructureSeparator */
  "&lt;/",                      /* StructureEndPrefix */
  "&gt;",                       /* StructureEndSuffix */

  NULL,                         /* BeforeToken */
  " ",                          /* TokenSeparator */
  "/",                          /* AttributeSeparator */
  NULL,                         /* AfterToken */

  NULL,                         /* BeforeField */
  NULL,                         /* FieldSeparator */
  NULL,                         /* AfterField */

  "<LI>",                       /* BeforeLine */
  "\n",                         /* AfterLine */

  "<HR>\n<UL>\n",               /* BeforeConcordance */
  "</UL>\n<HR>\n",              /* AfterConcordance */

  html_convert_string,
  html_print_field
};

PrintDescriptionRecord
HTMLTabularPrintDescriptionRecord = {
  "<TD ALIGN=RIGHT>%d:</TD>",   /* CPOSPrintFormat */

  "<TD><EM>",                   /* BeforePrintStructures */
  " ",                          /* PrintStructureSeparator */
  "</EM></TD>",                 /* AfterPrintStructures */

  "&lt;",                       /* StructureBeginPrefix */
  "&gt;",                       /* StructureBeginSuffix */

  " ",                          /* StructureSeparator */
  "&lt;/",                      /* StructureEndPrefix */
  "&gt;",                       /* StructureEndSuffix */

  NULL,                         /* BeforeToken */
  " ",                          /* TokenSeparator */
  "/",                          /* AttributeSeparator */
  NULL,                         /* AfterToken */

  "<TD ALIGN=RIGHT> ",          /* BeforeField */
  NULL,                         /* FieldSeparator */
  "</TD>",                      /* AfterField */

  "<TR>",                       /* BeforeLine */
  "</TR>\n",                    /* AfterLine */

  "<HR>\n<TABLE>\n",            /* BeforeConcordance */
  "</TABLE>\n<HR>\n",           /* AfterConcordance */
  html_convert_string,
  html_print_field
};

PrintDescriptionRecord
HTMLTabularNowrapPrintDescriptionRecord = {
  "<TD ALIGN=RIGHT nowrap>%d:</TD>", /* CPOSPrintFormat */

  "<TD nowrap><EM>",            /* BeforePrintStructures */
  " ",                          /* PrintStructureSeparator */
  "</EM></TD>",                 /* AfterPrintStructures */

  "&lt;",                       /* StructureBeginPrefix */
  "&gt;",                       /* StructureBeginSuffix */

  " ",                          /* StructureSeparator */
  "&lt;/",                      /* StructureEndPrefix */
  "&gt;",                       /* StructureEndSuffix */

  NULL,                         /* BeforeToken */
  " ",                          /* TokenSeparator */
  "/",                          /* AttributeSeparator */
  NULL,                         /* AfterToken */

  "<TD nowrap ALIGN=RIGHT> ",   /* BeforeField */
  NULL,                         /* FieldSeparator */
  "</TD>",                      /* AfterField */

  "<TR>",                       /* BeforeLine */
  "</TR>\n",                    /* AfterLine */

  "<HR>\n<TABLE>\n",            /* BeforeConcordance */
  "</TABLE>\n<HR>\n",           /* AfterConcordance */
  html_convert_string,
  html_print_field
};

/* ---------------------------------------------------------------------- */

void
html_puts(FILE *dst, char *s, int flags)
{
  if (!s)
    s = "(null)";

  if (flags) {
    while (*s) {
      if (*s == '<' && (flags & SUBST_LT))
        fputs("&lt;", dst);
      else if (*s == '>' && (flags & SUBST_GT))
        fputs("&gt;", dst);
      else if (*s == '&' && (flags & SUBST_AMP))
        fputs("&amp;", dst);
      else if (*s == '"' && (flags & SUBST_QUOT))
        fputs("&quot;", dst);
      else
        fputc(*s, dst);
      s++;
    }
  }
  else
    fputs(s, dst);
}

char *
html_print_field(FieldType field, int at_end)
{
  switch (field) {

  case NoField:
    if (GlobalPrintOptions.print_tabular)
      return (at_end ? "</TD>" : "<TD>");
    else
      return NULL;
    break;

  case MatchField:
    if (GlobalPrintOptions.print_tabular) {
      if (at_end) {
        if (GlobalPrintOptions.print_wrap)
          return "</B></TD><TD>";
        else
          return "</B></TD><TD nowrap>";
      }
      return (GlobalPrintOptions.print_wrap ? "</TD><TD><B>" : "</TD><TD nowrap><B>");
    }
    else
      return (at_end ? "</B>" : "<B>");
    break;

  case KeywordField:
    return (at_end ? "</U>" : "<U>");

  case TargetField:
    return (at_end ? "</EM>" : "<EM>");

  default:
    return NULL;
  }
}

/**
 * Dis-entititifies lt, gt and amp entities in a string supplierd.
 * @return   Pointer into an internal static buffer
 */
char *
html_convert_string(char *s)
{
  static char html_s[CL_MAX_LINE_LENGTH*2];
  int p; /* "pointer" into array */

  if (!s || strlen(s) > CL_MAX_LINE_LENGTH)
    return NULL;

  for (p = 0; *s; s++) {
    switch (*s) {
    case '<':
      html_s[p++] = '&';
      html_s[p++] = 'l';
      html_s[p++] = 't';
      html_s[p++] = ';';
      break;

    case '>':
      html_s[p++] = '&';
      html_s[p++] = 'g';
      html_s[p++] = 't';
      html_s[p++] = ';';
      break;

    case '&':
      html_s[p++] = '&';
      html_s[p++] = 'a';
      html_s[p++] = 'm';
      html_s[p++] = 'p';
      html_s[p++] = ';';
      break;

    default:
      html_s[p++] = *s;
      break;
    }
  }
  html_s[p] = '\0';

  return html_s;
}

/* ---------------------------------------------------------------------- */

/**
 * Prints a line of text (which will have been previously extracted from a corpus
 * linked to the present corpus by an a-attribute) within HTML markup.
 *
 * @param dst             Destination for the output.
 * @param attribute_name  The name of the aligned corpus: printed in the leading indicator within the HTML.
 * @param line            Character data of the line of aligned-corpus data to print. This is treated as opaque.
 */
void
html_print_aligned_line(FILE *dst, char *attribute_name, char *line)
{
  fputc('\n', dst);

  if (GlobalPrintOptions.print_tabular)
    fprintf(dst, "<TR><TD colspan=4%s><EM><B><EM>--&gt;", GlobalPrintOptions.print_wrap ? "" : " nowrap");
  else
    html_puts(dst, "<P><B><EM>--&gt;", 0);

  html_puts(dst, attribute_name, SUBST_ALL);
  html_puts(dst, ":</EM></B>&nbsp;&nbsp;", 0);
  html_puts(dst, line, SUBST_NONE); /* entities have already been escaped */

  if (GlobalPrintOptions.print_tabular)
    fprintf(dst, "</TR>\n");
  else
    fputc('\n', dst);
}

void
html_print_context(ContextDescriptor *cd, FILE *dst)
{
  fputs("<tr><td nowrap><em>Left display context:</em></td><td nowrap>", dst);

  switch(cd->left_type) {
  case CHAR_CONTEXT:
    fprintf(dst, "%d characters", cd->left_width);
    break;
  case WORD_CONTEXT:
    fprintf(dst, "%d tokens", cd->left_width);
    break;
  case STRUC_CONTEXT:
    fprintf(dst, "%d %s", cd->left_width,
            cd->left_structure_name ? cd->left_structure_name : "???");
    break;
  default:
    break;
  }

  fputs("</td></tr>\n", dst);
  fputs("<tr><td nowrap><em>Right display context:</em></td><td nowrap>", dst);

  switch(cd->right_type) {
  case CHAR_CONTEXT:
    fprintf(dst, "%d characters", cd->right_width);
    break;
  case WORD_CONTEXT:
    fprintf(dst, "%d tokens", cd->right_width);
    break;
  case STRUC_CONTEXT:
    fprintf(dst, "%d %s", cd->right_width,
            cd->right_structure_name ? cd->right_structure_name : "???");
    break;
  default:
    break;
  }

  fputs("</td></tr>\n", dst);
}

void
html_print_corpus_header(CorpusList *cl, FILE *dst)
{
  time_t now;

#ifndef __MINGW__
  struct passwd *pwd = NULL;
#endif

  if (GlobalPrintOptions.print_header) {

    (void) time(&now);
    /*   pwd = getpwuid(geteuid()); */
    /* disabled because of incompatibilities between different Linux versions */

    fprintf(dst,
            "<em><b>This concordance was generated by:</b></em><p>\n"
            "<table>\n"
            "<tr><td nowrap><em>User:</em></td><td nowrap>%s (%s)</td></tr>\n"
            "<tr><td nowrap><em>Date:</em></td><td nowrap>%s</td></tr>\n"
            "<tr><td nowrap><em>Corpus:</em></td><td nowrap>%s</td></tr>\n"
            "<tr><td nowrap> </td><td nowrap>%s</td></tr>\n"
            "<tr><td nowrap><em>Subcorpus:</em></td><td nowrap>%s:%s</td></tr>\n"
            "<tr><td nowrap><em>Number of Matches:</em></td><td nowrap>%d</td></tr>\n",
#ifndef __MINGW__
            (pwd ? pwd->pw_name : "unknown"),
            (pwd ? pwd->pw_gecos  : "unknown"),
#else
            "<unknown>",
            "<unknown>",
#endif
            ctime(&now),
            (cl->corpus && cl->corpus->registry_name ? cl->corpus->registry_name : "unknown"),
            (cl->corpus && cl->corpus->name ? cl->corpus->name : "unknown"),
            cl->mother_name, cl->name,
            cl->size);

    html_print_context(&CD, dst);

    fputs("</table>\n", dst);

    if (cl->query_corpus && cl->query_text)
      fprintf(dst, "<P><EM>Query text:</EM> <BR>\n<BLOCKQUOTE><CODE>\n%s; %s\n</CODE></BLOCKQUOTE>\n",
              cl->query_corpus, cl->query_text);

    fputs("<p>\n", dst);
  }

}


void
html_print_output(CorpusList *cl, FILE *dst, int interactive, ContextDescriptor *cd, int first, int last)
{
  int line, real_line;
  ConcLineField clf[NoField];
  /* NoField is largest field code (not used by us) */PrintDescriptionRecord *pdr;
  ParsePrintOptions();
  if (GlobalPrintOptions.print_tabular) {
    if (GlobalPrintOptions.print_wrap)
      pdr = &HTMLTabularPrintDescriptionRecord;
    else
      pdr = &HTMLTabularNowrapPrintDescriptionRecord;

    fprintf(dst, "<HR><TABLE%s>\n",GlobalPrintOptions.print_border ? " BORDER=1" : "");
  }
  else {
    pdr = &HTMLPrintDescriptionRecord;
    fputs("<HR><UL>\n", dst);
  }
  if (first < 0)
    first = 0;

  if (last >= cl->size || last < 0)
    last = cl->size - 1;

  for (line = first; (line <= last) && !cl_broken_pipe; line++) {
    real_line = (cl->sortidx ? cl->sortidx[line] : line);

    /* ---------------------------------------- concordance fields */

    clf[MatchField].type = MatchField;
    clf[MatchField].start_position = cl->range[real_line].start;
    clf[MatchField].end_position = cl->range[real_line].end;

    clf[MatchEndField].type = MatchEndField; /* unused, because we use MatchField for the entire match */
    clf[MatchEndField].start_position = -1;
    clf[MatchEndField].end_position = -1;

    clf[KeywordField].type = KeywordField;
    if (cl->keywords) {
      clf[KeywordField].start_position = cl->keywords[real_line];
      clf[KeywordField].end_position = cl->keywords[real_line];
    }
    else {
      clf[KeywordField].start_position = -1;
      clf[KeywordField].end_position = -1;
    }

    clf[TargetField].type = TargetField;
    if (cl->targets) {
      clf[TargetField].start_position = cl->targets[real_line];
      clf[TargetField].end_position = cl->targets[real_line];
    }
    else {
      clf[TargetField].start_position = -1;
      clf[TargetField].end_position = -1;
    }

    fputs(pdr->BeforeLine, dst);

    {
      char *outstr;
      int dummy;

      outstr = compose_kwic_line(cl->corpus, cl->range[real_line].start,
          cl->range[real_line].end, &CD, &dummy, &dummy, &dummy,
          NULL, NULL,
          NULL, 0, NULL, clf, NoField, /* NoField = # of entries in clf[] */
          ConcLineHorizontal, pdr, 0, NULL);

      fputs(outstr, dst);
      cl_free(outstr);
    }

    fputs(pdr->AfterLine, dst);

    if (CD.alignedCorpora != NULL)
      printAlignedStrings(cl->corpus, &CD, cl->range[real_line].start,
          cl->range[real_line].end, 0, /* ASCII print mode only */
          dst);

    /* fputc('\n', dst); */
  }
  fputs(pdr->AfterConcordance, dst);
}


void
html_print_group(Group *group, int expand, FILE *dst)
{
  int source_id, target_id, count;

  char *target_s = "(null)";

  int cell, last_source_id;
  int nr_targets;

  /* na ja... */
  last_source_id = -999;
  nr_targets = 0;

  fprintf(dst, "<BODY>\n<TABLE>\n");

  for (cell = 0; (cell < group->nr_cells) && !cl_broken_pipe; cell++) {
    fprintf(dst, "<TR><TD>");

    source_id = group->count_cells[cell].s;

    if (source_id != last_source_id) {
      last_source_id = source_id;
      html_puts(dst, Group_id2str(group, source_id, 0), SUBST_ALL);
      nr_targets = 0;
    }
    else
      fprintf(dst, "&nbsp;");

    target_id = group->count_cells[cell].t;
    target_s  = Group_id2str(group, target_id, 1);
    count     = group->count_cells[cell].freq;

    fprintf(dst, "<TD>");
    html_puts(dst, target_s, SUBST_ALL);
    fprintf(dst, "<TD>%d</TR>\n", count);

    nr_targets++;
  }

  fprintf(dst, "</TABLE>\n</BODY>\n");
}


