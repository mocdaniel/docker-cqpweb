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

#ifndef _MATCHLIST_H_
#define _MATCHLIST_H_


/* 
 * MATCH LISTS AND SET OPS ON THEM 
 */


/**
 * The Matchlist object.
 *
 * This is a table of integers describing matches to a query.
 */
typedef struct _Matchlist
{
  int *start;                       /**< Table of match start anchors (corpus posiitons) */
  int *end;                         /**< Table of match end anchors (corpus positions) */
  int *target_positions;            /**< Table of target anchors (corpus positions) */
  int *keyword_positions;           /**< Table of keyword anchors (corpus positions) */
  int  tabsize;                     /**< Number of integers in each of the three arrays */
  int  matches_whole_corpus;        /**< Boolean: if true, every position in the cirpus matches.
                                         In this case, we avoid copying.*/
  int is_inverted;                  /**< Boolean: if true, this matchilist contains ``inverted''
                                         positions, that is,positions which do NOT match */
} Matchlist;



void init_matchlist(Matchlist *matchlist);

void show_matchlist(Matchlist matchlist);

void show_matchlist_firstelements(Matchlist matchlist);

void free_matchlist(Matchlist *matchlist);

/**
 * Set operations which can be performed on (initial) matchlists.
 */
typedef enum ml_setops {
  Union,
  Intersection,
  Complement,
  Identity,                     /**< create a copy */
  Uniq,                         /**< make unique lists (also called "sets" :-)) */
  Reduce                        /**< delete -1 items */
} MLSetOp;

int Setop(Matchlist *list1, MLSetOp operation, Matchlist *list2);



#endif
