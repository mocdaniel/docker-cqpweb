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

#ifndef _PRINT_MODES_H_
#define _PRINT_MODES_H_

#include "corpmanag.h"
#include "attlist.h"

/**
 * Print mode: specifies output formats for CQP.
 */
typedef enum {
  PrintASCII,
  PrintSGML,
  PrintHTML,
  PrintLATEX,
  PrintBINARY,
  PrintUNKNOWN
} PrintMode;

/**
 * PrintOptions: records a set of print options.
 *
 * All members starting in print_ are Boolean, to be interpreted as
 * print_XX --> "XX is to be printed"
 */
typedef struct {
  int print_header;                /**< Can be: header/hdr,noheader */
  int print_tabular;               /**< Can be: table/tbl,notable */
  int print_wrap;                  /**< Can be: wrap, nowrap */
  int print_border;                /**< Can be: border/bdr,noborder */
  int number_lines;                /**< number of lines */
} PrintOptions;

/**
 * The PrintDescriptionRecord object.
 *
 * Contains strings / function pointers that control the printing
 * mode (esp. the format of an individual concordance line).
 *
 * Note that currently it is not possible for a new PDR to be defined at
 * runtime. It must be done at compile-time.
 *
 * Copncordance formatting is configured acroass this object and
 * the ContextDescriptor object, qv.
 *
 * @see ContextDescriptor
 */
typedef struct {
  char *CPOSPrintFormat;              /**< printf()-Formatting String for display of a corpus position
                                           (needs a %d or %x or similar in it somewhere)               */

  char *BeforePrintStructures;        /**< to print before PS */
  char *PrintStructureSeparator;      /**< to print as separator */
  char *AfterPrintStructures;         /**< to print after PS */

  char *StructureBeginPrefix;         /**< prefix of structure start tag */
  char *StructureBeginSuffix;         /**< suffix of structure start tag*/

  char *StructureSeparator;           /**< separator of structures */

  char *StructureEndPrefix;           /**< prefix of structure end tag */
  char *StructureEndSuffix;           /**< suffix of structure end tag */

  char *BeforeToken;                  /**< what to print before a token */
  char *TokenSeparator;               /**< what to print between tokens */
  char *AttributeSeparator;           /**< what to print as p-att separator within tokens */
  char *AfterToken;                   /**< what to print after a token */

  char *BeforeField;                  /**< what to print before a field */
  char *FieldSeparator;               /**< what to print between fields */
  char *AfterField;                   /**< what to print after fields */

  char *BeforeLine;                   /**< what to print before a line */
  char *AfterLine;                    /**< what to print after a line */

  char *BeforeConcordance;            /**< what to print before the concordance */
  char *AfterConcordance;             /**< what to print after the concordance */

  char *(*printToken)(char *);        /**< function pointer for printing a token */
  char *(*printField)(FieldType, int); /**< function pointer for printing a field,
                                        *   i.e. for highlighting a token that is one of the 4 anchor points;
                                        *   if NULL, these aren't printed. */
} PrintDescriptionRecord;

typedef char * (*TokenEscapeFunction)(char *);

extern PrintMode GlobalPrintMode;

extern PrintOptions GlobalPrintOptions;

AttributeList *ComputePrintStructures(CorpusList *cl);

void ParsePrintOptions(void);

void CopyPrintOptions(PrintOptions *target, PrintOptions *source);

#endif
