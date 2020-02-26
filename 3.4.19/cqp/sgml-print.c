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
#include <unistd.h>
#include <stdlib.h>
#include <sys/types.h>

#include <sys/time.h>
#include <time.h>

#ifndef __MINGW__
#include <pwd.h>
#endif

#include "../cl/cl.h"

#include "cqp.h"
#include "options.h"
#include "corpmanag.h"
#include "concordance.h"
#include "attlist.h"
#include "print_align.h"

#include "sgml-print.h"

/* ---------------------------------------------------------------------- */

/* ---------------------------------------------------------------------- */

/* -- seems to be unused (purpose unclear) --
#define NR_FIELD_NAMES 3
static char *field_names[] = { "collocate",
                               "keyword",
                               "tab"
};
*/

/* ---------------------------------------------------------------------- */

char *sgml_convert_string(char *s);
char *sgml_print_field(FieldType field, int start);


PrintDescriptionRecord
SGMLPrintDescriptionRecord = {
  "<MATCHNUM>%d</MATCHNUM>",    /* CPOSPrintFormat */

  "<STRUCS>",                   /* BeforePrintStructures */
  " ",                          /* PrintStructureSeparator */
  "</STRUCS>",                  /* AfterPrintStructures */

  "&lt;",                       /* StructureBeginPrefix */
  "&gt;",                       /* StructureBeginSuffix */

  " ",                          /* StructureSeparator */

  "&lt;/",                      /* StructureEndPrefix */
  "&gt;",                       /* StructureEndSuffix */

  "<TOKEN>",                    /* BeforeToken */
  " ",                          /* TokenSeparator */
  "/",                          /* AttributeSeparator */
  "</TOKEN>",                   /* AfterToken */

  "<CONTENT>",                  /* BeforeField */
  NULL,                         /* FieldSeparator */
  "</CONTENT>",                 /* AfterField */

  "<LINE>",                     /* BeforeLine */
  "</LINE>\n",                  /* AfterLine */

  "<CONCORDANCE>\n",            /* BeforeConcordance */
  "</CONCORDANCE>\n",           /* AfterConcordance */

  sgml_convert_string,          /* printToken */
  sgml_print_field
};

/* ---------------------------------------------------------------------- */

#define SUBST_NONE 0
#define SUBST_LT   1
#define SUBST_GT   2
#define SUBST_AMP  4
#define SUBST_QUOT 8
#define SUBST_ALL  (SUBST_LT | SUBST_GT | SUBST_AMP | SUBST_QUOT)

char *
sgml_print_field(FieldType field, int at_end)
{
  switch (field) {

  case NoField:
    if (at_end)
      return "</CONTEXT>";
    else
      return "<CONTEXT>";
    break;

  case MatchField:
    if (at_end)
      return "</MATCH>";
    else
      return "<MATCH>";
    break;

  case KeywordField:
    if (at_end)
      return "</KEYWORD>";
    else
      return "<KEYWORD>";
    break;

  case TargetField:
    if (at_end)
      return "</COLLOCATE>";
    else
      return "<COLLOCATE>";
    break;

  default:
    return NULL;
    break;
  }
}

char *
sgml_convert_string(char *s)
{
  static char sgml_s[CL_MAX_LINE_LENGTH*2];
  int p;

  if (!s || strlen(s) >(CL_MAX_LINE_LENGTH))
    return NULL;

  for (p = 0; *s; s++) {
    switch (*s) {

    case '<':
      sgml_s[p++] = '&';
      sgml_s[p++] = 'l';
      sgml_s[p++] = 't';
      sgml_s[p++] = ';';
      break;

    case '>':
      sgml_s[p++] = '&';
      sgml_s[p++] = 'g';
      sgml_s[p++] = 't';
      sgml_s[p++] = ';';
      break;

    case '&':
      sgml_s[p++] = '&';
      sgml_s[p++] = 'a';
      sgml_s[p++] = 'm';
      sgml_s[p++] = 'p';
      sgml_s[p++] = ';';
      break;

    default:
      sgml_s[p++] = *s;
      break;
    }
  }
  sgml_s[p] = '\0';

  return sgml_s;
}

void
sgml_puts(FILE *fd, char *s, int flags)
{
  if (flags) {
    while (*s) {
      if (*s == '<' && (flags & SUBST_LT))
        fputs("&lt;", fd);
      else if (*s == '>' && (flags & SUBST_GT))
        fputs("&gt;", fd);
      else if (*s == '&' && (flags & SUBST_AMP))
        fputs("&amp;", fd);
      else if (*s == '"' && (flags & SUBST_QUOT))
        fputs("&quot;", fd);
      else
        fputc(*s, fd);
      s++;
    }
  }
  else
    fputs(s, fd);
}

/**
 * Prints a line of text (which will have been previously exrtracted from a corfpus
 * linked to the present corpus by an a-attribute) following an SGML open-tag.
 *
 * The structure is <align name="$att_name">$line_data
 * And the whole thing is terminated by EOL (note, no close tag as we;'d have in XML!)
 *
 * @param stream          Destination for the output.
 * @param attribute_name  The name of the aligned corpus: printed in the "align" tag as an SGML attribute.
 * @param line            Character data of the line of aligned-corpus data to print. This is treated as opaque.
 */
void
sgml_print_aligned_line(FILE *stream, char *attribute_name, char *line)
{
  sgml_puts(stream, "<align name=\"", 0);
  sgml_puts(stream, attribute_name, 0);
  sgml_puts(stream, "\">", 0);
  sgml_puts(stream, line, SUBST_NONE); /* entities have already been escaped */

  fputc('\n', stream);
}

void sgml_print_context(ContextDescriptor *cd, FILE *stream)
{
  char *s;

  switch(cd->left_type) {
  case CHAR_CONTEXT:
    s = "characters";
    break;
  case WORD_CONTEXT:
    s = "tokens";
    break;
  case STRUC_CONTEXT:
    s = cd->left_structure_name ? cd->left_structure_name : "???";
    break;
  default:
    s = "error";
    break;
  }
  fprintf(stream, "<leftContext size=%d base=\"%s\">\n",
          cd->left_width, s);


  switch(cd->right_type) {
  case CHAR_CONTEXT:
    s = "characters";
    break;
  case WORD_CONTEXT:
    s = "tokens";
    break;
  case STRUC_CONTEXT:
    s = cd->right_structure_name ? cd->right_structure_name : "???";
    break;
  default:
    s = "error";
    break;
  }
  fprintf(stream, "<rightContext size=%d base=\"%s\">\n",
          cd->right_width, s);

}

void
sgml_print_corpus_header(CorpusList *cl, FILE *stream)
{
  time_t now;

#ifndef __MINGW__
  struct passwd *pwd = NULL;
#endif

  time(&now);

  /*   pwd = getpwuid(geteuid()); */
  /* disabled because of incompatibilities between different Linux versions */

  fprintf(stream,
          "<concordanceInfo>\n"
          "<user><userID>%s</userID><userName>%s</userName></user>\n"
          "<date>%s</date>\n"
          "<corpusInfo><corpusID>%s</corpusID><corpusName>%s</corpusName></corpusInfo>\n"
          "<subcorpusInfo size=%d>\n"
          "<name>%s:%s</name>\n"
          "</subcorpusInfo>\n",
#ifndef __MINGW__
          (pwd ? pwd->pw_name : "unknown"),
          (pwd ? pwd->pw_gecos  : "unknown"),
#else
          "unknown",
          "unknown",
#endif
          ctime(&now),
          ( (cl->corpus && cl->corpus->registry_name) ? cl->corpus->registry_name : "unknown"),
          ( (cl->corpus && cl->corpus->name) ? cl->corpus->name : "unknown"),
          cl->size,
          cl->mother_name, cl->name);

  sgml_print_context(&CD, stream);

  fputs("</concordanceInfo>\n", stream);
}

void
sgml_print_output(CorpusList *cl,
                  FILE *stream,
                  int interactive,
                  ContextDescriptor *cd,
                  int first,
                  int last)
{
  int line, real_line;
  ConcLineField clf[NoField];   /* NoField is largest field code (not used by us) */
  PrintDescriptionRecord *pdr = &SGMLPrintDescriptionRecord;

  AttributeInfo *ai;
  int anr = 0;

  fputs(pdr->BeforeConcordance, stream);

  /* print each p-attribute's name and number */
  for (ai = cd->attributes->list; ai; ai = ai->next)
    if (ai->attribute && ai->status > 0)
      fprintf(stream, "<attribute type=positional name=\"%s\" anr=%d>\n", ai->attribute->any.name, anr++);

  if (first < 0)
    first = 0;
  if (last >= cl->size || last < 0)
    last = cl->size - 1;

  for (line = first; line <= last && !cl_broken_pipe; line++) {
    if (cl->sortidx)
      real_line = cl->sortidx[line];
    else
      real_line = line;

    /* ---------------------------------------- concordance fields */

    clf[MatchField].type = MatchField;
    clf[MatchField].start_position = cl->range[real_line].start;
    clf[MatchField].end_position   = cl->range[real_line].end;

    clf[MatchEndField].type = MatchEndField; /* unused, because we use MatchField for the entire match */
    clf[MatchEndField].start_position = -1;
    clf[MatchEndField].end_position   = -1;

    clf[KeywordField].type = KeywordField;
    if (cl->keywords) {
      clf[KeywordField].start_position = cl->keywords[real_line];
      clf[KeywordField].end_position   = cl->keywords[real_line];
    }
    else {
      clf[KeywordField].start_position = -1;
      clf[KeywordField].end_position   = -1;
    }

    clf[TargetField].type = TargetField;
    if (cl->targets) {
      clf[TargetField].start_position = cl->targets[real_line];
      clf[TargetField].end_position   = cl->targets[real_line];
    }
    else {
      clf[TargetField].start_position = -1;
      clf[TargetField].end_position   = -1;
    }

    if (pdr->BeforeLine)
      fputs(pdr->BeforeLine, stream);

    {
      char *outstr;
      int dummy;

      outstr = compose_kwic_line(cl->corpus,
                                 cl->range[real_line].start,
                                 cl->range[real_line].end,
                                 &CD,
                                 &dummy,
                                 &dummy,
                                 &dummy,
                                 NULL, NULL,
                                 NULL, 0, NULL,
                                 clf, NoField, /* NoField = # of entries in clf[] */
                                 ConcLineHorizontal,
                                 pdr,
                                 0, NULL);
      fputs(outstr, stream);
      cl_free(outstr);
    }

    if (pdr->AfterLine)
      fputs(pdr->AfterLine, stream);

    if (CD.alignedCorpora != NULL)
      printAlignedStrings(cl->corpus,
                          &CD,
                          cl->range[real_line].start,
                          cl->range[real_line].end,
                          0,    /* ASCII print mode only */
                          stream);
  }

  fputs(pdr->AfterConcordance, stream);
}

void
sgml_print_group(Group *group, int expand, FILE *fd)
{
  int source_id, target_id, count;

  char *target_s = "(null)";

  int cell, last_source_id;
  int nr_targets;

  /* na ja... */
  last_source_id = -999;
  nr_targets = 0;

  fprintf(fd, "<TABLE>\n");

  for (cell = 0; cell < group->nr_cells && !cl_broken_pipe; cell++) {
    fprintf(fd, "<TR><TD>");

    source_id = group->count_cells[cell].s;

    if (source_id != last_source_id) {
      last_source_id = source_id;
      sgml_puts(fd, Group_id2str(group, source_id, 0), SUBST_ALL);
      nr_targets = 0;
    }
    else
      fprintf(fd, "&nbsp;");

    target_id = group->count_cells[cell].t;
    target_s  = Group_id2str(group, target_id, 1);
    count     = group->count_cells[cell].freq;

    fprintf(fd, "<TD>");
    sgml_puts(fd, target_s, SUBST_ALL);

    fprintf(fd, "<TD>%d</TR>\n", count);

    nr_targets++;
  }

  fprintf(fd, "</TABLE>\n");
}




