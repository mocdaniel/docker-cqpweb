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


#ifndef _cqp_tree_h_
#define _cqp_tree_h_

#include "eval.h"

#define MAKE_IDLIST_BOUND 1000

char *evaltree2searchstr(Evaltree etptr, int *length);

void print_evaltree(int envidx, Evaltree, int);

void free_evaltree(Evaltree *);

void init_booltree(Constrainttree *);

void print_booltree(Constrainttree, int);

void free_booltree(Constrainttree);

void show_patternlist(int eidx);

Constraint *try_optimization(Constraint *tree);

#endif
