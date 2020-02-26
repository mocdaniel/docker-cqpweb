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

#ifndef _cqp_table_h_
#define _cqp_table_h_

#include "corpmanag.h"


/**
 * The table object.
 *
 * Tables are used to store query results, i.e. subcorpora with optional target fields.
 *
 * All fields (Match, MatchEnd, and the various Target fields) are treated uniformly as
 * the columns of a table: {table.field[MatchField]}, {table.field[MatchEndField]}, ...
 * {table.size} is the number of rows (i.e. the number of cells in each column). Each
 * column may be undefined, i.e. NULL. Rows where the MatchField column equals -1 are
 * understood as 'deleted' rows. To allow tables to grow automatically, the number of
 * allocated rows {table.allocated} may be greater than {table.size}. All columns must
 * be allocated to the same size.
 */
typedef struct _table {
  int *(field[NoField]);        /**< table made of #NoField columns = individually allocated integer lists */
  int *sortidx;                 /**< optional sort index (honoured by all access functions) */
  unsigned size;                /**< all fields have the same size (or are NULL) */
  unsigned allocated;           /**< number of cells allocated for all non-NULL fields */
} table;


/** create and initialise new table (setting size and allocation to zero) */
table new_table(void);

/** initialise new table inserting {list} as match column (sets table.size = table.allocated = size) */
table new_table_from_list(int *list, unsigned size);

/** extend allocated space to exactly {size} rows (must be >= table.size) */
void table_allocate(table t, int size);

/** destroy table object (deallocate all data) */
void delete_table(table t);

/** make exact copy of table t */
table table_duplicate(table t);

/** current size of table; encapsulates variable table.size */
unsigned table_size(table t);

/** returns True if {fld} column is defined (i.e. != NULL) in table {t} */
int table_defined_field(table t, FieldType fld);

/** get value of {row}th entry in {fld} column; returns -1 if column is NULL or row is out of range */
int table_get(table t, FieldType fld, unsigned row);

/** Sets {row}th entry in {fld} column to {value}; automatically allocates and extends columns */
void table_set(table t, FieldType fld, unsigned row, int value);

/** Gets a pointer to int vector representing column {fld} for direct access; note that sortidx is ignored! */
int *table_get_vector(table t, FieldType fld);

/** Gets a pointer to sort index as int vector; returns NULL if table is unsorted */
int *table_get_sortidx(table t);

/* define additional sort functions here, especially those implementing the sort command */

/** delete rows where match=-1 or matchend=-1, as well as duplicates; then sort by corpus position;
 * returns True iff successful; note that both the MatchField and the MatchEndField column must be defined */
int table_normalise(table t);

/**
 * The TableOp enumeration: specifies an operation to apply to a table.
 *
 * These are used as parameters to the table_setp() function.
 *
 * We may want to change these ops and/or their exact semantics; however, for a smooth migration
 * of the query evaluation component, it is advantageous to provide precise equivalents of
 * the "traditional" operations
 */
typedef enum _table_ops {
  TReduce,                       /**< (t1, TReduce, NULL) -> expunge 'deleted' rows from {t1} */
  /* union, difference & intersection operate on Match and MatchEnd columns, which must be defined;
     other columns are copied to result; if different in {t1} and {t2}, value from {t1} is used */
  TUnion,                        /**< (t1, TUnion, t2)    -> compute set union of tables */
  TIntersection,                 /**< (t1, TIntersection, t2) -> compute set intersection of tables */
  TDifference,                   /**< (t1, TDifference, t2)   -> compute set difference of tables */
  /* the following operations work on the Match and MatchEnd column of a single table */
  TMaximalMatches,               /**< (t1, TMaximalMatches, NULL) -> used by longest_match strategy */
  TLeftMaximalMatches,           /**< (t1, TLeftMaximalMatches, NULL) -> used by shortest_match strategy */
  TNonOverlapping,               /**< (t1, TNonOverlapping, NULL) -> delete overlapping matches (subqueries) */
  TUniq,                         /**< (t1, TUniq, NULL)   -> sort table & remove duplicates(by Match, MatchEnd) */
} TableOp;


/** execute unary or binary operation {op}; result is stored in {t1}; returns True iff successful */
int table_setop(table t1, TableOp op, table t2);

/* it may turn out useful at some point to have some support for chains of tables, e.g. for
   subsets of matches with incremental query processing; it should be possible to combine the
   elements of a chain into a single table without repeatedly (re-)allocating memory */

#endif

