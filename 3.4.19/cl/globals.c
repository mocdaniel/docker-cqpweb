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
 * This contains accessor functions for the CL global variables which are
 * defined as "extern" in the include-it-everywhere "globals.h" header.
 *
 * This allows CQP/CWB-utils to access these variables without
 * including the extern declarations; while the CL can access them
 * *qua* variables.
 *
 * It also contains the must-always-be-called cl_startup() function and
 * a couple of other utilities.
 */

#include <locale.h>

#include "globals.h"

/**
 *  Global configuration variable: debug level.
 *
 *  Controls how many debug messages are printed.
 *  0 = none (default), 1 = some, 2 = heavy
 */
int cl_debug = 0;

/**
 *  Global configuration variable: optimisation.
 *  0 = off, 1 = on (untested / expensive optimisations)
 */
int cl_optimize = 0;

/**
 *  Global configuration variable: memory limit.
 *
 *  In megabytes; some functions will try to keep to this limit;
 *  0 turns the limit off.
 *
 *  (ensure memory limit > 2GB is correctly converted to byte size or number of ints)
 */
size_t cl_memory_limit = 0;


/**
 * Startup function for the CL. All programs that use CL should call this before
 * doing anything else.
 *
 * Currently, all it does is make sure that string case-insensitive comparison
 * will work the same everywhere regardless of the locale set by the system or user.
 */
void
cl_startup(void)
{
  /* setting the locale to C makes the use of locale-sensitive Glib functions
   * behave as if they are locale insensitive; result is constant behaviour. */
  setlocale(LC_ALL, "C");
}



/**
 * Safely add an offset to a corpus position.
 *
 * Return CDA_EPOSORNG if cpos + offset is outside the corpus (clamp = 0),
 * or clamps the return value to the valid range (clamp = 1).
 * Particular care is taken to avoid integer overflow if cpos is close to INT_MAX.
 *
 * @param cpos         corpus position (must be in valid range)
 * @param offset       positive or negative offset from corpus position
 * @param corpus_size  corpus size as returned by cl_max_cpos()
 * @param clamp        boolean: whether to clamp return value (1) or return an error (0)
 *
 * @return             cpos + offset (possibly clamped) or CDA_EPOSORNG
 */
int
cl_cpos_offset(int cpos, int offset, int corpus_size, int clamp) {
  if (offset > 0) {
    if ((corpus_size - cpos) <= offset)
      return(clamp ? corpus_size - 1 : CDA_EPOSORNG);
    else
      return cpos + offset;
  }
  else if (offset < 0) {
    if (corpus_size + offset < 0)
      return(clamp ? 0 : CDA_EPOSORNG);
    else
      return cpos + offset;
  }
  else
    return cpos;
}



/**
 * Sets the debug level configuration variable.
 *
 * @see cl_debug
 */
void
cl_set_debug_level(int level)
{
  if (level < 0 || level > 2)
    fprintf(stderr, "cl_set_debug_level(): non-existent level #%d (ignored)\n", level);
  else
    cl_debug = level;
}

/**
 * Turns optimization on or off.
 *
 * @see cl_optimize
 * @param state  Boolean (true turns it on, false turns it off).
 */
void
cl_set_optimize(int state)
{
  cl_optimize = state ? 1 : 0;
}

/**
 * Sets the memory limit respected by some CL functions.
 *
 * @param megabytes   The new limit. Zero or less means no limit.
 *
 * @see cl_memory_limit
 */
void
cl_set_memory_limit(int megabytes)
{
  if (megabytes <= 0)
    cl_memory_limit = 0;
  else
    cl_memory_limit = (size_t)megabytes;
}


int
cl_get_debug_level(void)
{
  return cl_debug;
}

int
cl_get_optimize(void)
{
  return cl_optimize;
}

int
cl_get_memory_limit(void)
{
  return (int)cl_memory_limit;
}
