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


#include <string.h>

#include "cl.h"
#include "ui-helpers.h"



/* default configuration of indented lists */

#define ILIST_INDENT      4
#define ILIST_TAB        12
#define ILIST_LINEWIDTH  72  /* NB: total linewidth needed is ILIST_INDENT + ILIST_LINEWIDTH */



/**
 * @file
 *
 * This file contains helpers for commandline user interaction (utilities, CQP):
 *
 * - printing of indented lists
 * - progress bar printing.
 */



/*
 *  display progress bar in terminal window
 */

/* non-exported global variables for progress bar */
static int progress_bar_pass = 1;
static int progress_bar_total = 1;
static int progress_bar_simple = 0;

/**
 * Activates or deactivates child (simple) mode for progress_bar.
 *
 * @param on_off  The new setting for the progress bar mode,
 *                where 1 = simple messages ON STDOUT,
 *                0 = pretty-printed messages with carriage returns ON STDERR
 */
void
progress_bar_child_mode(int on_off)
{
  progress_bar_simple = on_off;
}

/**
 * Clears the progress bar currently displayed on the terminal.
 *
 * Note: assumes line width of 60 characters.
 */
void
progress_bar_clear_line(void) {
  /* messages are on separated lines, so do nothing unless "simple" is switched off. */
  if (!progress_bar_simple) {
    /* clear the contents of the bottom terminal line */
    fprintf(stderr, "                                                            \r");
    fflush(stderr);
  }
}

/**
 * Prints a new progress bar (passes-plus-message format).
 *
 * The progress bar printed is as follows:
 *
 * [pass {pass} of {total}: {message}]
 *
 * If total is equal to zero, the function uses the pass
 * and total values from the last call of this function.
 *
 */
void
progress_bar_message(int pass, int total, char *message)
{
  /* [pass <pass> of <total>: <message>]   (uses pass and total values from last call if total == 0)*/
  if (total <= 0) {
    pass = progress_bar_pass;
    total = progress_bar_total;
  }
  else {
    progress_bar_pass = pass;
    progress_bar_total = total;
  }
  if (progress_bar_simple) {
    fprintf(stdout, "-::-PROGRESS-::-\t%d\t%d\t%s\n", pass, total, message);
    fflush(stdout);
  }
  else {
    fprintf(stderr, "[");
    fprintf(stderr, "pass %d of %d: ", pass, total);
    fprintf(stderr, "%s]     \r", message);
    fflush(stderr);
  }
}


/**
 * Prints a new progress bar (passes-plus-percentage-done format).
 *
 * The progress bar printed is as follows:
 *
 * [pass {pass} of {total}: {percentage}% complete]
 *
 * If total is equal to zero, the function uses the pass
 * and total values from the last call of this function.
 */
void
progress_bar_percentage(int pass, int total, int percentage)
{
  /* [pass <pass> of <total>: <percentage>% complete]  (uses progress_bar_message) */
  char message[20];
  sprintf(message, "%3d%c complete", percentage, '%');
  progress_bar_message(pass, total, message);
}


/*
 *  ILIST: print indented 'tabularised' lists.
 *
 *  Note that use of global variables makes this non-reentrant.
 */

/* ilist status variables (non-exported globals) */
static int ilist_cursor;         /**< the 'cursor' (column where next item will be printed) */
static int ilist_linewidth;      /**< width of the lines as specified for current ilist. */
static int ilist_tab;            /**< n chars per tab for current ilist */
static int ilist_indent;         /**< n chars left-indent for current ilist */

/* internal function: print <n> blanks */
static void
ilist_print_blanks(int n)
{
  for ( ; n > 0 ; n--)
    printf(" ");
}

/**
 * Begins the printing of a line in an indented 'tabularised' list.
 *
 * If any of the three parameters are zero, this function uses the internal default value
 * for that parameter instead (ILIST macro constants).
 *
 * @param linewidth  Width of the line (in characters)
 * @param tabsize    Tabulator steps (in characters)
 * @param indent     Indentation of the list from left margin (in characters)
 */
void
ilist_start(int linewidth, int tabsize, int indent)
{
  /* set status variables */
  ilist_linewidth = (linewidth > 0) ? linewidth : ILIST_LINEWIDTH;
  ilist_tab       = (tabsize   > 0) ? tabsize   : ILIST_TAB;
  ilist_indent    = (indent    > 0) ? indent    : ILIST_INDENT;
  ilist_cursor    = 0;
  /* indent from left margin */
  ilist_print_blanks(ilist_indent);
}

/**
 * Starts a new line in an indented 'tabularised' list.
 *
 * Used when a line break is needed within an indented list; this function
 * starts a new line (as <br> in HTML), an showing optional label in indentation.
 *
 * @param label  The optional label, if this is NULL, no label is used; if it is
 *               a string, then the string appears on the far left hand side.
 */
void
ilist_print_break(char *label)
{
  int llen = (label != NULL) ? strlen(label) : 0;

  if (ilist_cursor != 0)
    printf("\n");
  else
    printf("\r");

  if (llen <= 0)
    ilist_print_blanks(ilist_indent);
  else {
    printf("%s", label);
    ilist_print_blanks(ilist_indent - llen);
  }
  ilist_cursor = 0;
}

/**
 * Prints an item into an ongoing indented list.
 *
 * @param string  The string to print as a list item.
 */
void
ilist_print_item(char *string)
{
  int len;

  if (string) {
    len = strlen(string);
    if ((ilist_cursor + len) > ilist_linewidth)
      ilist_print_break("");
    printf("%s", string);
    ilist_cursor += len;
    /* advance cursor to next tabstop */
    if (ilist_cursor < ilist_linewidth) {
      printf(" ");
      ilist_cursor++;
    }
    while ((ilist_cursor < ilist_linewidth) && ((ilist_cursor % ilist_tab) != 0)) {
      printf(" ");
      ilist_cursor++;
    }
  }
}

/**
 * Ends the printing of a line in an indented 'tabularised' list.
 */
void
ilist_end(void)
{
  if (ilist_cursor == 0)
    printf("\r");        /* no output on last line (just indention) -> erase indention */
  else
    printf("\n");
  ilist_cursor = 0;
  fflush(stdout);
}
