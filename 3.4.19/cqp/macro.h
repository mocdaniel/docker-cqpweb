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
 * The maximum length of a line in a macro definition file.
 *
 * As of 3.2.x, this has been modified to be the same as CL_MAX_LINE_LENGTH
 * (for sake of simplicity).
 */
#define MACRO_FILE_MAX_LINE_LENGTH CL_MAX_LINE_LENGTH

int yy_input_char(void);

int yy_input_from_macro(void);

void init_macros(void);

int define_macro(char *name, int args, char *argstr, char *definition);

void load_macro_file(char *name);


int expand_macro(char *name);

/* delete active input buffers created by macro expansion; returns # of buffers deleted
 * used when synchronizing after a parse error
 * (if <trace> is true, prints stack trace on STDERR)
 */
int delete_macro_buffers(int trace);

/* macro iterator functions (iterate through all macros in hash) for command-line completion */
void macro_iterator_reset(void);
char *macro_iterator_next(char *prefix, int *nargs);
char *macro_iterator_next_prototype(char *prefix);

/* list all defined macros on stdout;
 * if <prefix> is not NULL, list only macros beginning with <prefix> */
void list_macros(char *prefix);

/* print definition of macro on stdout */
void print_macro_definition(char *name, int args);

void macro_statistics(void);

