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


/* ---------------------------------------------------------------------- */

#include <stdio.h>
#include <string.h>
#include <sys/types.h>
#include <sys/time.h>
#include <time.h>
#include <unistd.h>

#ifndef __MINGW__
#include <pwd.h>
#endif

/* ---------------------------------------------------------------------- */

#include "../cl/corpus.h"
#include "../cl/attributes.h"
#include "../cl/cdaccess.h"

#include "cqp.h"
#include "options.h"
#include "corpmanag.h"
#include "concordance.h"
#include "attlist.h"
#include "print_align.h"
#include "latex-print.h"

/* ---------------------------------------------------------------------- */

char *latex_print_field(FieldType field, int start);

PrintDescriptionRecord
LaTeXPrintDescriptionRecord = {
  "{\\em %d:\\/} ",                /* CPOSPrintFormat */

  "{\\sf ",                        /* BeforePrintStructures */
  " ",                                /* PrintStructureSeparator */
  "} ",                                /* AfterPrintStructures */

  "$<$",                        /* StructureBeginPrefix */
  "$>$",                        /* StructureBeginSuffix */

  " ",                                /* StructureSeparator */

  "$<$/",                        /* StructureEndPrefix */
  "$>$",                        /* StructureEndSuffix */

  "",                                /* BeforeToken */
  " ",                                /* TokenSeparator */
  "/",                                /* AttributeSeparator */
  "",                                /* AfterToken */

  "",                                /* BeforeField */
  "",                                /* FieldSeparator */
  "",                                /* AfterField */

  "\\item ",                        /* BeforeLine */
  "\n",                                /* AfterLine */

  "\\begin{itemize}\n",                /* BeforeConcordance */
  "\\end{itemize}\n",                /* AfterConcordance */

  latex_convert_string,
  latex_print_field
};

PrintDescriptionRecord
LaTeXTabularPrintDescriptionRecord = {
  "{\\em %d:\\/} & ",                /* CPOSPrintFormat */

  "{\\sf ",                        /* BeforePrintStructures */
  " ",                                /* PrintStructureSeparator */
  "} & ",                        /* AfterPrintStructures */

  "$<$",                        /* StructureBeginPrefix */
  "$>$",                        /* StructureBeginSuffix */

  " ",                                /* StructureSeparator */

  "$<$/",                        /* StructureEndPrefix */
  "$>$",                        /* StructureEndSuffix */

  "",                                /* BeforeToken */
  " ",                                /* TokenSeparator */
  "/",                                /* AttributeSeparator */
  "",                                /* AfterToken */

  "",                                /* BeforeField */
  "",                                /* FieldSeparator */
  "",                                /* AfterField */

  "",                                /* BeforeLine */
  " \\\\\n",                        /* AfterLine */

  "\\begin{tabular}\n",                /* BeforeConcordance */
  "\\end{tabular}\n",        /* AfterConcordance */

  latex_convert_string,
  latex_print_field
};

/* ---------------------------------------------------------------------- */

char *
latex_print_field(FieldType field, int at_end)
{
  switch (field) {

  case NoField:
    return NULL;
    break;

  case MatchField:
    if (GlobalPrintOptions.print_tabular) {
      if (at_end)
        return "} & ";
      else
        return " & {\\bf ";
    }
    else {
      if (at_end)
        return "} ";
      else
        return " {\\bf ";
    }
    break;

  case KeywordField:
    if (at_end)
      return "}";
    else
      return "{\\sl ";
    break;

  case TargetField:
    if (at_end)
      return "\\/}";
    else
      return "{\\em ";
    break;

  default:
    return NULL;
    break;
  }
}

char *
latex_convert_string(char *s)
{
  static char latex_s[CL_MAX_LINE_LENGTH*2];
  int p;

  if (!s || strlen(s) >(CL_MAX_LINE_LENGTH))
    return NULL;

  for (p = 0; *s; s++) {
    switch (*s) {

    case '<':
      latex_s[p++] = '$';
      latex_s[p++] = '<';
      latex_s[p++] = '$';
      break;

    case '$':
      latex_s[p++] = '\\';
      latex_s[p++] = '$';
      break;

    case '>':
      latex_s[p++] = '$';
      latex_s[p++] = '>';
      latex_s[p++] = '$';
      break;

    case '&':
      latex_s[p++] = '\\';
      latex_s[p++] = '&';
      break;

    case '_':
      latex_s[p++] = '\\';
      latex_s[p++] = '_';
      break;

    case '\\':
      latex_s[p++] = '\\';
      latex_s[p++] = '\\';
      break;

    default:
      latex_s[p++] = *s;
      break;
    }
  }
  latex_s[p] = '\0';

  return latex_s;
}

/**
 * Does nothing: we don't support printing alignment data in LaTeX mode.
 *
 * @param stream          Destination for the output.
 * @param attribute_name  The name of the aligned corpus.
 * @param line            Character data of the line of aligned-corpus data to print.
 */
void
latex_print_aligned_line(FILE *stream, char *attribute_name, char *line)
{
  /* NOP */
}

void
latex_print_context(ContextDescriptor *cd, FILE *stream)
{
  fputs("{\\em Left display context:\\/}  & ", stream);

  switch(cd->left_type) {
  case CHAR_CONTEXT:
    fprintf(stream, "%d characters", cd->left_width);
    break;
  case WORD_CONTEXT:
    fprintf(stream, "%d tokens", cd->left_width);
    break;
  case STRUC_CONTEXT:
    fprintf(stream, "%d %s", cd->left_width, cd->left_structure_name ? cd->left_structure_name : "???");
    break;
  default:
    break;
  }

  fputs("\\\\\n", stream);

  fputs("{\\em Right display context:\\/}  & ", stream);

  switch(cd->right_type) {
  case CHAR_CONTEXT:
    fprintf(stream, "%d characters", cd->right_width);
    break;
  case WORD_CONTEXT:
    fprintf(stream, "%d tokens", cd->right_width);
    break;
  case STRUC_CONTEXT:
    fprintf(stream, "%d %s", cd->right_width, cd->right_structure_name ? cd->right_structure_name : "???");
    break;
  default:
    break;
  }

  fputs("\\\\\n", stream);
}

void
latex_print_corpus_header(CorpusList *cl,
                          FILE *stream)
{
  time_t now;
#ifndef __MINGW__
  struct passwd *pwd = NULL;
#endif

  time(&now);
  /*   pwd = getpwuid(geteuid()); */
  /* disabled because of incompatibilities between different Linux versions */

  fprintf(stream,
          "{\\em This concordance was generated by:\\/}\n"
          "\\begin{quote}\\begin{tabular}{ll}\n"
          "{\\em User:\\/}      & %s (%s) \\\\\n"
          "{\\em Date:\\/}      & %s \\\\\n"
          "{\\em Corpus:\\/}    & %s \\\\\n"
          "                     & %s \\\\\n"
          "{\\em Subcorpus:\\/} & %s:%s \\\\\n"
          "{\\em Number of Matches:\\/} & %d \\\\\n",

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

  latex_print_context(&CD, stream);

  fputs("\\end{tabular}\\end{quote}\n", stream);
}

void latex_print_output(CorpusList *cl,
                        FILE *stream,
                        int interactive,
                        ContextDescriptor *cd,
                        int first, int last)
{
  int line, real_line;
  ConcLineField clf[NoField];        /* NoField is largest field code (not used by us) */
  PrintDescriptionRecord *pdr;

  ParsePrintOptions();

  if (GlobalPrintOptions.print_tabular) {

    char latex_tab_format[20];

    if (GlobalPrintOptions.print_border) {
      latex_tab_format[0] = '|';
      latex_tab_format[1] = '\0';
    }
    else
      latex_tab_format[0] = '\0';

    pdr = &LaTeXTabularPrintDescriptionRecord;

    if (cd->print_cpos)
      strcat(latex_tab_format, GlobalPrintOptions.print_border ? "r|" : "r");

    if (cd->printStructureTags) {
      AttributeInfo *l;
      int v = 0;
      for (l = cd->printStructureTags->list; l; l = l->next)
        if (l->status) {
          v++;
          break;
        }
      if (v)
        strcat(latex_tab_format, GlobalPrintOptions.print_border ? "l|" : "l");
    }

    strcat(latex_tab_format, GlobalPrintOptions.print_border ? "r|l|l|" : "rll");

    fprintf(stream, "\\begin{tabular}{%s}\n", latex_tab_format);

    if (GlobalPrintOptions.print_border)
      fprintf(stream, "\\hline\n");
  }
  else {
    pdr = &LaTeXPrintDescriptionRecord;
    fputs(pdr->BeforeConcordance, stream);
  }

  if (first < 0)
    first = 0;
  if ((last >= cl->size) || (last < 0))
    last = cl->size - 1;

  for (line = first; (line <= last) && !cl_broken_pipe; line++) {
    if (cl->sortidx)
      real_line = cl->sortidx[line];
    else
      real_line = line;

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


    fputs(pdr->BeforeLine, stream);

    {
      char *outstr;
      int dummy;

      outstr = compose_kwic_line(cl->corpus,
                                 cl->range[real_line].start,
                                 cl->range[real_line].end,
                                 &CD,
                                 &dummy,
                                 &dummy, &dummy,
                                 NULL, NULL,
                                 NULL, 0, NULL,
                                 clf, NoField, /* NoField = # of entries in clf[] */
                                 ConcLineHorizontal,
                                 pdr,
                                 0, NULL);
      fputs(outstr, stream);
      cl_free(outstr);
    }

    fputs(pdr->AfterLine, stream);

    if (CD.alignedCorpora != NULL)
      printAlignedStrings(cl->corpus,
                          &CD,
                          cl->range[real_line].start,
                          cl->range[real_line].end,
                          0,        /* ASCII print mode only */
                          stream);

    if (GlobalPrintOptions.print_tabular && GlobalPrintOptions.print_border)
      fprintf(stream, "\\hline\n");
  }

  fputs(pdr->AfterConcordance, stream);
}

void
latex_print_group(Group *group, int expand, FILE *fd)
{
  int source_id, target_id, count;

  char *target_s = "(null)";

  int cell, last_source_id;
  int nr_targets;

  /* na ja... */
  last_source_id = -999;
  nr_targets = 0;

  fprintf(fd, "\\begin{tabular}{llr}\n");

  for (cell = 0; (cell < group->nr_cells) && !cl_broken_pipe; cell++) {
    source_id = group->count_cells[cell].s;

    if (source_id != last_source_id) {
      last_source_id = source_id;
      fputs(latex_convert_string(Group_id2str(group, source_id, 0)), fd);
      nr_targets = 0;
    }

    target_id = group->count_cells[cell].t;
    target_s  = Group_id2str(group, target_id, 1);
    count     = group->count_cells[cell].freq;

    fprintf(fd, " & %s & %d \\\\\n", latex_convert_string(target_s), count);

    nr_targets++;
  }

  fprintf(fd, "\\end{tabular}\n");
}
