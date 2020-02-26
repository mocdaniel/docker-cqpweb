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


#ifndef _TARGET_H_
#define _TARGET_H_

#include "eval.h"
#include "corpmanag.h"

typedef enum _search_strategy {
  SearchLeftmost, SearchRightmost, SearchNearest, SearchFarthest, SearchNone
} SearchStrategy;

/* will usually be provided as SEARCH_STRATEGY token by flex */
SearchStrategy string_to_strategy(char *s);

int set_target(CorpusList *corp, FieldType goal, FieldType source);

int evaluate_target(CorpusList *corp,          /* the corpus */
                    FieldType goal,            /* the field to set */
                    FieldType base,            /* where to start the search */
                    int inclusive,             /* including or excluding the base */
                    SearchStrategy strategy,   /* disambiguation rule: which item */
                    Constrainttree constr,     /* the constraint */
                    enum ctxtdir direction,    /* context direction */
                    int units,                 /* number of units */
                    char *attr_name);          /* name of unit */



/* destructively modifies corp */

int evaluate_subset(CorpusList *corp,          /* the corpus */
                    FieldType the_field,       /* the field to scan */
                    Constrainttree constr);    /* the constraint proper */

#endif
