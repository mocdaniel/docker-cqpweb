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

#ifndef _REGOPT_H_
#define _REGOPT_H_
#include "globals.h"

/* include external regular expression library */
#include <pcre.h>

/**
 * Maximum number of grains of optimisation.
 *
 * There's no point in scanning for too many grains, but some regexps used to be bloody inefficient.
 */
#define MAX_GRAINS 12

/**
 * Underlying structure for CL_Regex object.
 *
 * TODO: change structure name as it breaks rules for ANSI reserved-words (uscore followed by uppercase)
 *
 * @see regopt.c
 */
struct _CL_Regex {
  pcre *needle;                      /**< buffer for the actual regex object (PCRE) */
  pcre_extra *extra;                 /**< buffer for PCRE's internal optimisation data */
  CorpusCharset charset;             /**< the character set in use for this regex */
  int icase;                         /**< whether IGNORE_CASE flag was set for this regex (needs special processing) */
  int idiac;                         /**< whether IGNORE_DIAC flag was set for this regex */
  char *haystack_buf;                /**< a buffer of size CL_MAX_LINE_LENGTH used for accent folding by cl_regex_match(),
                                          allocated only if IGNORE_DIAC was specified */
  char *haystack_casefold;           /**< additional, larger (2 * CL_MAX_LINE_LENGTH) buffer for a case-folded version,
                                          allocated only if optimizer is active and IGNORE_CASE was specified */
  /* Note: these buffers are for the string being tested, NOT for the regular expression.
   * They are allocated once here to avoid frequent small allocation and deallocations in cl_regex_match(). */

  /* data from optimiser (see global variables in regopt.c for comments) */
  int grains;                        /**< number of grains (0 = not optimised). @see cl_regopt_grains */
  int grain_len;                     /**< @see cl_regopt_grain_len */
  char *grain[MAX_GRAINS];           /**< @see cl_regopt_grain */
  int anchor_start;                  /**< @see cl_regopt_anchor_start */
  int anchor_end;                    /**< @see cl_regopt_anchor_end */
  int jumptable[256];                /**< @see cl_regopt_jumptable @see make_jump_table */
};


/* interface function prototypes are in <cl.h>; internal functions declared here */

void regopt_data_copy_to_regex_object(CL_Regex rx);
int cl_regopt_analyse(char *regex);

#endif
