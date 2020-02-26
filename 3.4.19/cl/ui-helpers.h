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


#ifndef _ui_helpers_h_
#define _ui_helpers_h_


/*
 * This file only has in it the ilist and progress bar things.
 *
 * Those might eventually go into cl.h
 */

/*
 * display progress bar in terminal window (STDERR, child mode: STDOUT)
 */
void progress_bar_child_mode(int on_off);
void progress_bar_clear_line(void);
void progress_bar_message(int pass, int total, char *message);
void progress_bar_percentage(int pass, int total, int percentage);



/*
 *  print indented 'tabularised' lists
 */
void ilist_start(int linewidth, int tabsize, int indent);
void ilist_print_break(char *label);
void ilist_print_item(char *string);
void ilist_end(void);




#endif

