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

#ifndef _cqp_ranges_h_
#define _cqp_ranges_h_

#include "../cl/bitfields.h"  /* for the Bitfield object */

#include "eval.h"
#include "corpmanag.h"
#include "output.h"

#define SORT_FROM_START 0
#define SORT_FROM_END 1
#define SORT_RESET 2

/* -------------------------------------------------- sorting */

/**
 * The SortClause object (and underlying SortClauseBuffer).
 *
 * Contains information about a sort to be applied to a query.
 */
typedef struct _sort_clause {
  char *attribute_name;             /**< attribute on which to sort */
  int flags;                        /**< constants indicating the %cd flags, if present */
  FieldType anchor1;                /**< Field type of the start of sort region */
  int offset1;                      /**< Offset of the start of sort region */
  FieldType anchor2;                /**< Field type of the end of sort region */
  int offset2;                      /**< Offset of the end of sort region */
  int sort_ascending;               /**< Boolean: sort direction (ascending true/descending false) */
  int sort_reverse;                 /**< Boolean: reverse sort? (sort reversed character sequences) */
  /* struct _sort_clause *next; */  /* used to support multiple sort clauses in a linked list */
} SortClauseBuffer, *SortClause;

/* -------------------------------------------------- ranges */

/**
 * RangeSetOp object: indicates a specific type of operation that can be
 * applied when operating on sets of corpus position pairs making up a subcorpus.
 *
 * RUnion, RDiff, and RIntersection operate on two corpora; the others operate
 * on only one.
 */
typedef enum rng_setops {
  RUnion,                        /**< unify two subcorpora (add to first the nonoverlapping intervals from second) */
  RIntersection,                 /**< take the intersection of two subcorpora (remove from first any intervals
                                      that don't also occur in the second) */
  RDiff,                         /**< take the diff of two subcorpora (remove from first any intervals that also
                                      occur in the second) */
  /* RIdentity removed (only used by RUnion -- now rewritten to handle targets+keywords) */
  RMaximalMatches,               /**< used by longest_match strategy */
  RMinimalMatches,               /**< used by shortest_match strategy */
  RLeftMaximalMatches,           /**< used by standard_match strategy */
  RNonOverlapping,               /**< delete overlapping matches (for subqueries) */
  RUniq,                         /**< make unique lists (= ordered sets) of ranges */
  RReduce                        /**< remove intervals marked for deletion and reallocate now-spare memory. */
} RangeSetOp;

/* line operation modes (for deletions) ------------------------------------- */

#define ALL_LINES        1       /**< delete all lines */
#define SELECTED_LINES   2       /**< delete the selected lines */
#define UNSELECTED_LINES 3       /**< delete all but the selected lines */


/* excluded from header cos currently not in use, we may delete later -- int delete_interval(CorpusList *cp, int interval_number); */

int delete_intervals(CorpusList *cp, Bitfield which_intervals, int mode);

int copy_intervals(CorpusList *cp,
                   Bitfield which_intervals,
                   int mode,
                   char *subcorpname);

int calculate_ranges(CorpusList *cl, int cpos, Context spc, int *left, int *right);

int calculate_rightboundary(CorpusList *cl, int cpos, Context spc);

int calculate_leftboundary(CorpusList *cl, int cpos, Context spc);

int apply_range_set_operation(CorpusList *list1,
               RangeSetOp operation,
               CorpusList *list2,
               Bitfield restrictor);

void RangeSort(CorpusList *c, int mk_sortidx);

int SortSubcorpus(CorpusList *cl, SortClause sc, int count_mode, struct Redir *redir);

int SortSubcorpusRandomize(CorpusList *cl, int seed);

void FreeSortClause(SortClause sc);

#endif
