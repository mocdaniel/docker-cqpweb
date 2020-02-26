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
#include <assert.h>

#include "../cl/cl.h"

#include "corpmanag.h"
#include "attlist.h"
#include "output.h"
#include "options.h"

/** String containing print structure separators */
#define PRINT_STRUC_SEP ": ,"

/* ---------------------------------------------------------------------- */

/** Global print-mode setting. */
PrintMode GlobalPrintMode = PrintASCII;

/** Global print-options: all booleans initially set to false */
PrintOptions GlobalPrintOptions = { 0, 0, 0, 0, 0 };

/* ---------------------------------------------------------------------- */


/**
 * Computes a list of s-attributes to print from the PrintStructure global option setting.
 *
 * PrintStructure is itself updated.
 *
 * @param cl  The corpus from which to find the attributes.
 * @return    An attribute list containing the attributes to be printed.
 */
AttributeList *
ComputePrintStructures(CorpusList *cl)
{
  char *token, *p;
  AttributeList *al;
  AttributeInfo *ai;
  Attribute *struc;

  if (printStructure == NULL || printStructure[0] == '\0' || cl == NULL)
    return NULL;

  al = NULL;
  struc = NULL;

  token = strtok(printStructure, PRINT_STRUC_SEP);
  if (!token)
    return NULL;

  while (token) {
    if (!(struc = cl_new_attribute(cl->corpus, token, ATT_STRUC)))
      cqpmessage(Warning,
                 "Structure ``%s'' not declared for corpus ``%s''.",
                 token, cl->corpus->registry_name);
    else if (!cl_struc_values(struc)) {
      cqpmessage(Warning, "Structure ``%s'' does not have any values.",token);
      struc = NULL;
    }

    if (struc) {
      if (al == NULL)
        al = NewAttributeList(ATT_STRUC);
      AddNameToAL(al, token, 1, 0);
    }
    token = strtok(NULL, PRINT_STRUC_SEP);
  }

  if (al) {
    if (!VerifyList(al, cl->corpus, 1)) {
      cqpmessage(Error, "Problems while computing print structure list");
      DestroyAttributeList(&al);
      al = NULL;
    }
    else if (!al->list)
      DestroyAttributeList(&al);
  }

  /* rebuild printStructure string to show only valid attributes */
  p = printStructure;
  *p = '\0';
  ai = (al) ? al->list : NULL;
  while (ai != NULL) {
    if (p != printStructure)
      *p++ = ' ';                /* insert blank between attributes */
    sprintf(p, "%s", ai->attribute->any.name);
    p += strlen(p);
    ai = ai->next;
  }

  return al;
}

/**
 * This function doesn't do anything yet.
 */
void
ResetPrintOptions(void)
{
  /* ??? */
}


/**
 * Reads the global string printModeOptions and parses it to update the GlobalPrintOptions.
 *
 * @see printModeOptions
 * @see GlobalPrintOptions
 */
void
ParsePrintOptions(void)
{
  if (printModeOptions) {

    char *token;
    char s[CL_MAX_LINE_LENGTH];
    int value;

    /* we must not destructively modify the global variable, as strtok would do */

    cl_strcpy(s, printModeOptions);

    token = strtok(s, " \t\n,.");
    while (token) {

      if (strncasecmp(token, "no", 2) == 0) {
        value = 0;
        token += 2;
      }
      else
        value = 1;

      if (strcasecmp(token, "wrap") == 0)
        GlobalPrintOptions.print_wrap = value;

      else if ((strcasecmp(token, "table") == 0) || (strcasecmp(token, "tbl") == 0))
        GlobalPrintOptions.print_tabular = value;

      else if ((strcasecmp(token, "header") == 0) || (strcasecmp(token, "hdr") == 0))
        GlobalPrintOptions.print_header = value;

      else if ((strcasecmp(token, "border") == 0) || (strcasecmp(token, "bdr") == 0))
        GlobalPrintOptions.print_border = value;

      else if ((strcasecmp(token, "number") == 0) || (strcasecmp(token, "num") == 0))
        GlobalPrintOptions.number_lines = value;

      else if (!silent)
        fprintf(stderr, "Warning: %s: unknown print option\n", token);

      token = strtok(NULL, " \t\n,.");
    }
  }
}

/**
 * Copies a PrintOptions object.
 *
 * @param target  The PrintOptions object to be overwritten.
 * @param source  The PrintOptions object to copy.
 */
void
CopyPrintOptions(PrintOptions *target, PrintOptions *source)
{
  if (target && source)
    memcpy((void *)target, (void *)source, sizeof(PrintOptions));
}
