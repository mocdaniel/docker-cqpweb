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

#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include <limits.h>
#include <string.h>
#include <ctype.h>
#include <unistd.h>

#include "../cl/cl.h"

#include "corpmanag.h"
#include "eval.h"
#include "output.h"
#include "matchlist.h"
#include "options.h"

#include "ranges.h"

#define SORT_DEBUG 0

/**
 * Delete a single cpos-pair (a corpus zone or single query match)
 * from a query-generated subcorpus.
 *
 * This function is not currently in use.
 *
 * @param cp  The CorpusList indicating the query to delete from.
 * @param nr  The index of the interval to delete (by setting its
 *            start and end values to -1).
 * @return    Boolean: true for success, false for failure.
 */
int
delete_interval(CorpusList *cp, int nr)
{
  int result;

  if (!cp ||
      cp->type != SUB ||
      cp->size <= 0 ||
      nr < 0 ||
      nr >= cp->size)
    return 0;
  else {
    cl_free(cp->sortidx);

    cp->range[nr].start = -1;
    cp->range[nr].end = -1;
    result = apply_range_set_operation(cp, RReduce, NULL, NULL);
    return result;
  }
}

/**
 * Delete a whole bunch of concordance hits from a query-generated subcorpus.
 *
 * @param cp         The CorpusList indicating the query to delete from.
 * @param intervals  A Bitfield containing a bit for each query hit, which
 *                   is true if the hit is "selected", false if not.
 * @param mode       ALL_LINES, SELECTED_LINES or UNSELECTED_LINES (indicating
 *                   which lines to delete).
 * @return           Boolean: true for success, false for failure.
 */
int
delete_intervals(CorpusList *cp, Bitfield intervals, int mode)
{
  int i;
  int result;    /* boolean to return at the end */
  int modified;  /* count of the number of lines deleted */
  int bit;       /* temp storage for value retrieved from bitfield */

  if (!cp || !(cp->type == SUB || cp->type == TEMP) || cp->size <= 0)
    return 0;
  else {
    assert(intervals && (intervals->elements == cp->size));

    modified = 0;

    switch (mode) {

    case ALL_LINES:
      modified = cp->size;
      break;

    case SELECTED_LINES:
    case UNSELECTED_LINES:
      /* for each hit, check whether it is "selected"
       * then delete, or not, depending on mode */
      for (i = 0; i < cp->size; i++) {
        bit = get_bit(intervals, i);
        if ( (mode == SELECTED_LINES && bit) || (mode == UNSELECTED_LINES && !bit) ) {
          modified++;
          cp->range[i].start = -1;
          cp->range[i].end = -1;
        }
      }
      break;

    default:
      assert(0 && "Unsupported deletion mode");
      break;
    }

    if (modified) {
      /* if all hits were deleted... */
      if (modified == cp->size) {
        cl_free(cp->sortidx);
        cl_free(cp->keywords);
        cl_free(cp->targets);
        cl_free(cp->range);
        cp->size = 0;
      }
      else {
        /* not all hits were deleted */

        /* Vorerst, bis mir ein guter Algorithmus zur
         * Erhaltung der Sortierung einfaellt TODO TODO
         */

        cl_free(cp->sortidx);

        result = apply_range_set_operation(cp, RReduce, NULL, NULL);
      }
      /* since at least one hit was modified, touch the
       * corpuslist that represents the query */
      touch_corpus(cp);

      if (auto_save && cp->type == SUB)
        save_subcorpus(cp, NULL);

      result = 1;// TODO why is the result from RangeSetop thrown away????
    }
    else
      result = 1;

    return result;
  }
}

/**
 * Copy concordance hits from a query-generated subcorpus to a
 * (new or existing) subcorpus.
 *
 * This function is not currently in use.
 *
 * @param cp           The CorpusList indicating the query to copy from.
 * @param intervals    A Bitfield containing a bit for each query hit, which
 *                     is true if the hit is "selected", false if not.
 * @param mode         ALL_LINES, SELECTED_LINES or UNSELECTED_LINES (indicating
 *                     which lines to copy).
 * @param subcorpname  Name for the subcorpus to which the lines are to be
 *                     copied.
 * @return             Boolean: true for success, false for failure.
 */
int
copy_intervals(CorpusList *cp,
               Bitfield intervals,
               int mode,
               char *subcorpname)
{
  CorpusList *new_sub;
  int result;
  int i;

  /* cp must be a query-generated subcorpus containing at least 1 hit */
  if ((!cp) || (cp->type != SUB) || (cp->size <= 0))
    return 0;
  else {

    assert(intervals && (intervals->elements == cp->size));

    new_sub = findcorpus(subcorpname, UNDEF, 0);

    if (new_sub == NULL) {
      /*
       * No corpus of this name exists already! So we can create it.
       * First, we copy the source corpus to a new corpus.
       * If that was ok, we delete the lines in intervals with
       * the inverse mode.
       */
      new_sub = duplicate_corpus(cp, subcorpname, 0);

      if (new_sub == NULL) {
        cqpmessage(Error,
                   "Can't copy intervals from %s to %s (corpus creation failed)\n",
                   cp->name, subcorpname);
        return 0;
      }

      switch (mode) {

      case SELECTED_LINES:
        result = delete_intervals(new_sub, intervals, UNSELECTED_LINES);
        break;

      case UNSELECTED_LINES:
        result = delete_intervals(new_sub, intervals, SELECTED_LINES);
        break;

      default:
        cqpmessage(Error, "Illegal copy_intervals mode %d\n", mode);
        dropcorpus(new_sub);
        result = 0;
        break;
      }
    }
    else if (new_sub == cp) {
      cqpmessage(Error, "Can't add/copy to myself!");
      result = 0;
    }
    else if (new_sub->type == SYSTEM) {
      cqpmessage(Error, "Can't add/copy intervals to a system corpus");
      result = 0;
    }
    else if (strcmp(new_sub->mother_name, cp->mother_name) != 0) {
      cqpmessage(Error,
                 "Underlying corpus of source (%s) and\n"
                 "underlying corpus of target (%s)\n"
                 "differ",
                 cp->mother_name, new_sub->mother_name);
      result = 0;
    }
    else {
      /* if we're here, then we are copying to an existing subcorpus
       * that it is OK to copy to - so, try to do it. */
      if (mode == UNSELECTED_LINES)
        for (i = 0; i < cp->size; i++)
          toggle_bit(intervals, i);
      result = apply_range_set_operation(new_sub, RUnion, cp, intervals);
    }

    if (result && auto_save && new_sub && new_sub->type == SUB && new_sub->saved == False)
      save_subcorpus(new_sub, NULL);

    return result;
  } /* end else (query exists and has at least one hit */
}


int
calculate_ranges(CorpusList *cl, int cpos, Context spc, int *left, int *right)
{
  int corpsize;
  int rng_s, rng_e, rng_n, nrng_s, nrng_e, r1, r2, nr_rngs, d;

  switch(spc.space_type) {

  case word:
    d = spc.size;

    if (d < 0)
      return 0;

    corpsize = cl->mother_size;
    assert(corpsize > 0);

    *left  = MAX(0, cpos - d);
    d = MIN(d, (corpsize - 1) - cpos); /* avoid 32-bit wrap-around for very very large corpora (close to CL_MAX_CORPUS_SIZE) */
    *right = cpos + d;
    break;

  case structure:
    d = spc.size - 1;

    if (d < 0)
      return 0;

    assert(spc.attrib);

    if (!cl_cpos2struc2cpos(spc.attrib, cpos, &rng_s, &rng_e))
      return 0;

//    if (!get_num_of_struc(spc.attrib, cpos, &rng_n))
    if (0 > (rng_n = cl_cpos2struc(spc.attrib, cpos)))
      return 0;

    /* determine the lower range number */
    r1 = MAX(0, rng_n - d);

    if (!cl_struc2cpos(spc.attrib, r1, &nrng_s, &nrng_e))
      return 0;
    *left = nrng_s;


    /* Ssame procedja as last yea */


    /* determine the upper range number */
//    if (!get_nr_of_strucs(spc.attrib, &nr_rngs))
    if (0 > (nr_rngs = cl_max_struc(spc.attrib)))
      return 0;

    r2 = MIN(nr_rngs-1, rng_n + d);

    if (!cl_struc2cpos(spc.attrib, r2, &nrng_s, &nrng_e))
      return 0;
    *right = nrng_e;

    break;

  default:
    fprintf(stderr, "calculate_ranges: undefined space type %d detected\n", spc.space_type);
    exit(1);
    break;
  }
  return 1;
}


/**
 * Returns -1 if there is no rightboundary found.
 */
int
calculate_rightboundary(CorpusList *cl, int cpos, Context spc)
{
  int left, right;
  return (calculate_ranges(cl, cpos, spc, &left, &right)? right : -1);
}


/**
 * Returns -1 if there is no leftboundary found.
 */
int
calculate_leftboundary(CorpusList *cl, int cpos, Context spc)
{
  int left, right;
  return (calculate_ranges(cl, cpos, spc, &left, &right)? left : -1);
}

/** this is a rather specialised utility function for the UNION part of RangeSetop()
   (copies range + keyword/target (if defined) in corpus into temporary lists)*/
static void
rs_cp_range(Range *rng, int *target, int *keyword, int ins, CorpusList *corpus, int j)
{
  rng[ins].start = corpus->range[j].start;
  rng[ins].end = corpus->range[j].end;
  if (target != NULL) {         /* target/keyword vectors may be undefined */
    if (corpus->targets)
      target[ins] = corpus->targets[j];
    else
      target[ins] = -1;
  }
  if (keyword != NULL) {
    if (corpus->keywords)
      keyword[ins] = corpus->keywords[j];
    else
      keyword[ins] = -1;
  }
}

/** Variable used by _RS_compare_ranges; global so data can be passed in
 * without going through that function's parameter list! */
static Range *_RS_range = NULL;

/** qsort() helper function for RangeSort() below */
static int
_RS_compare_ranges (const void *pa, const void *pb)
{
  Range *a = _RS_range + *((int *) pa); /* compare ranges #a and #b */
  Range *b = _RS_range + *((int *) pb);
  if (a->start < b->start)      /* start(a) < start(b) */
    return -1;
  else if (a->start > b->start) /* start(a) > start(b) */
    return 1;
  else if (a->end > b->end)     /* start(a) == start(b) -> larger match first */
    return -1;
  else if (a->end < b->end)
    return 1;
  else                          /* ranges are identical */
    return 0;
}

/**
 * Make sure that ranges are sorted in 'natural' order (i.e. by start and end cpos).
 *
 * This function has to be called when matching ranges are modified and may be needed
 * when loading a query result (with "undump") that is not sorted in ascending order;
 * with optional "mk_sortidx" flag, a sortidx corresponding to the original ordering
 * is created.
 *
 * @param c           The corpus (ie subcorpus/query) whose intervals ('ranges') are
 *                    to be sorted.
 * @param mk_sortidx  Boolean flag: if true a sortidx is created.
 */
void
RangeSort(CorpusList *c, int mk_sortidx)
{
  Range *new_range = NULL;
  int *new_targets = NULL, *new_keywords = NULL;
  int *new_sortidx = NULL;
  int *index = NULL;            /* sort index for qsort() function */
  int size, i;

  if (c->type != SUB && c->type != TEMP) {
    /* function only works for named queries (= subcorpora) */
    cqpmessage(Error, "Argument to internal function RangeSort() is not a named query result.");
    return;
  }
  if (c->sortidx) {
    /* sortidx will now longer be valid after operation and is deleted */
    cqpmessage(Warning,
               "Sort ordering of named query %s is out of date and has been deleted.\n"
               "\tMatching ranges are now sorted in ascending corpus order.",
               c->name);
    cl_free(c->sortidx);
  }

  size = c->size;                        /* size of query result */
  index = cl_malloc(size * sizeof(int)); /* allocate and initialise qsort() index */

  for (i = 0; i < size; i++)
    index[i] = i;

  /* intialise global data for callback and run qsort()  */
  _RS_range = c->range;
  qsort(index, size, sizeof(int), _RS_compare_ranges);

/*     printf("Resort index is:\n"); */
/*     for (i = 0; i < size; i++) */
/*       printf("\t%4d => [%d,%d]\n", index[i], c->range[index[i]].start, c->range[index[i]].end); */

  /* allocate new range vector and fill it with sorted ranges */
  new_range = cl_malloc(size * sizeof(Range));
  for (i = 0; i < size; i++)
    new_range[i] = c->range[index[i]];

  /* then free old vector and replace it with sorted one */
  cl_free(c->range);
  c->range = new_range;

  if (c->targets) {             /* same for targets (if present) */
    new_targets = cl_malloc(size * sizeof(int));
    for (i = 0; i < size; i++)
      new_targets[i] = c->targets[index[i]];
    cl_free(c->targets);
    c->targets = new_targets;
  }
  if (c->keywords) {            /* and keywords (if present) */
    new_keywords = cl_malloc(size * sizeof(int));
    for (i = 0; i < size; i++)
      new_keywords[i] = c->keywords[index[i]];
    cl_free(c->keywords);
    c->keywords = new_keywords;
  }

  if (mk_sortidx) {
    /* create new sortidx so that user still sees previous ordering of the matches (used with "undump") */
    new_sortidx = cl_malloc(size * sizeof(int));
    if (mk_sortidx)
      for (i = 0; i < size; i++)
        new_sortidx[index[i]] = i;
    c->sortidx = new_sortidx;
  }

  /* free temporary qsort() index vector */
  cl_free(index);
}

/**
 * Carries out one of a set of operations on corpus1.
 *
 * The operations that can be carried out are as follows:
 *
 * RUnion - copy intervals from corpus2 to corpus1 (no duplicates);
 * RIntersection - remove from corpus1 any intervals that are not also in corpus2;
 * RDiff
 * RMaximalMatches - remove spurious matches according to "longest" strategy;
 * RMinimalMatches - remove spurious matches according to "shortest" strategy;
 * RLeftMaximalMatches - remove spurious matches according to "standard" strategy;
 * RNonOverlapping
 * RUniq - remove duplicate intervals from corpus1;
 * RReduce - remove intervals marked for deletion (by having the start memebr set to -1).
 *
 * TODO to avopid confusion with the object, a better name for this function would be do_RangeSetOp
 *
 * @param corpus1     The corpus to be changed.
 * @param operation   Specifies which operation is to be carried out.
 * @param corpus2     The corpus that is the second argument for this operation.
 *                    Can be NULL if no corpus2 is required for operation.
 * @param restrictor  Specifies which intervals in corpus2 are to be taken notice of
 *                    versus ignored. Can be NULL.
 * @return            Boolean, true for all OK, otherwise false.
 */
int
apply_range_set_operation(CorpusList *corpus1,
           RangeSetOp operation,
           CorpusList *corpus2,
           Bitfield restrictor)
{
  int i, j, ins;
  int intervals_to_copy;

  Range *tmp;
  int *tmp_target, *tmp_keyword;
  int tmp_size;

  /* switch across the different members of RangeSetOp... */
  switch (operation) {

  case RUnion:

    /*
     * -------------------- UNION
     */

    if (corpus2 == NULL || corpus2->size == 0) {
      /* the result is corpus1, so just return */
      return 1;
    }
    else {
      if (restrictor) {
        /* count how many intervals are to be copied to corpus1 */
        intervals_to_copy = 0;
        for (i = 0; i < corpus2->size; i++)
          if (get_bit(restrictor, i))
            intervals_to_copy++;
      }
      else
        /* we have to copy all the intervals from corpus2 */
        intervals_to_copy = corpus2->size;

      /* allocate a blob of memory big enough to hold all the Ranges in the union'ed corpus */
      tmp_size = corpus1->size + intervals_to_copy;
      tmp = (Range *)cl_malloc(sizeof(Range) * tmp_size);

      /* allocate targets / keywords if they're present in one of the arguments */
      if ((corpus1->targets != NULL) || (corpus2->targets != NULL))
        tmp_target = (int *)cl_malloc(sizeof(int) * tmp_size);
      else
        tmp_target = NULL;

      if ((corpus1->keywords != NULL) || (corpus2->keywords != NULL))
        tmp_keyword = (int *)cl_malloc(sizeof(int) * tmp_size);
      else
        tmp_keyword = NULL;

      i = 0;                    /* the position in corpus1 */
      j = 0;                    /* the position in corpus2 */
      ins = 0;                  /* the insertion point in the (unified) result list */

      /* loop through the intervals in corpus1 and corpus2 to unify them */
      while ( i < corpus1->size || j < corpus2->size ) {
        /* while there are intervals left in either corpus... */

        if (j >= corpus2->size ||
            (i < corpus1->size && (corpus1->range[i].start < corpus2->range[j].start))
            ) {
          /* if we have run out of intervals in corpus2, or if the next interval in
           * corpus2 comes later on than the next interval in corpus1,
           * copy an item from corpus1 */
          rs_cp_range(tmp, tmp_target, tmp_keyword, ins, corpus1, i);
          ins++;
          /* increment i since we have copied from corpus 1 */
          i++;
        }
        else if (i >= corpus1->size ||
                           /* NB j < corpus2->size assured in this branch */
                 (corpus1->range[i].start > corpus2->range[j].start)) {
          /* if we have run out of intervals in corpus1, or if the next interval in
           * corpus1 comes later on than the next interval in corpus2,
           * copy an interval from corpus2 (allowing for the restrictor if necessry) */
          if (restrictor == NULL || get_bit(restrictor, j)) {
            rs_cp_range(tmp, tmp_target, tmp_keyword, ins, corpus2, j);
            ins++;
          }
          /* increment j since we have copied from corpus 2 */
          j++;
        }
        else {
          /* both start positions are identical. Now check whether the end positions are also the same...
           *
           * => the ranges are identical and we'll copy target/keyword from corpus1
           * => the range from corpus1 ends sooner, so we copy that one
           * => the range from corpus2 ends sooner, so we copy that one, depending on the restrictor
           *
           * (for real duplicates, both i and j are incremented; otherwise,
           * only the one copied is incremented, so the other is still on the
           * pile of intervals to be whiled through.) */

          if (corpus1->range[i].end == corpus2->range[j].end) {

            rs_cp_range(tmp, tmp_target, tmp_keyword, ins, corpus1, i);
            i++;
            j++;                /* skip the corresponding range in corpus2 */
            ins++;
          }
          else if (corpus1->range[i].end < corpus2->range[j].end) {

            rs_cp_range(tmp, tmp_target, tmp_keyword, ins, corpus1, i);
            i++;
            ins++;
          }
          else {
            if (restrictor == NULL || get_bit(restrictor, j)) {
              rs_cp_range(tmp, tmp_target, tmp_keyword, ins, corpus2, j);
              ins++;
            }
            j++;
          }
        }
      } /* endwhile there are intervals left in either corpus */

      assert(ins <= tmp_size);

      /* we did not eliminate any duplicates if ins == tmp.size => don't bother to realloc */
      if (ins < tmp_size) {
        tmp = (Range *)cl_realloc((char *)tmp, sizeof(Range) * ins);
        if (tmp_target)
          tmp_target = (int *)cl_realloc((char *)tmp_target, sizeof(int) * ins);
        if (tmp_keyword)
          tmp_keyword = (int *)cl_realloc((char *)tmp_keyword, sizeof(int) * ins);
      }

      /* replace corpus1's fields with temporary vectors */
      cl_free(corpus1->range);
      cl_free(corpus1->targets);
      cl_free(corpus1->keywords);

      corpus1->range = tmp;
      corpus1->targets = tmp_target; /* may be NULL */
      corpus1->keywords = tmp_keyword; /* may be NULL */
      corpus1->size = ins;

      touch_corpus(corpus1);
      return 1;
    } /* endif "corpus2 and corpus1 are not the same" */

    break;


  case RIntersection:

    /*
     * -------------------- INTERSECTION
     * targets / keywords are copied from _left_ operand
     */

    i = 0;                      /* the position in corpus1 */
    j = 0;                      /* the position in corpus2 */

    while (i < corpus1->size && j < corpus2->size) {

      /* compare start positions; if not equal, advance subcorpus where start position is smaller */
      if (corpus1->range[i].start < corpus2->range[j].start) {
        corpus1->range[i].start = -1;   /* not found -> mark for deletion & advance in corpus1 */
        i++;
      }
      else if (corpus1->range[i].start > corpus2->range[j].start)
        j++;
      else {

        /* both start positions are identical. Now check whether the end positions are also the same */
        if (corpus1->range[i].end == corpus2->range[j].end) {
          /* this range is in both subcorpora -> keep & advance both pointers */
          i++;
          j++;
        }
        else {

          /* end positions are not the same -> advance subcorpus where end position is smaller */
          if (corpus1->range[i].end < corpus2->range[j].end) {
            corpus1->range[i].start = -1;       /* not found -> mark for deletion & advance in corpus1 */
            i++;
          }
          else
            j++;
        }
      }
    }

    /* remove remaining intervals from corpus1 (if corpus2 reached end first) */
    while (i < corpus1->size) {
      corpus1->range[i].start = -1;
      i++;
    }

    /* remove ranges marked for deletion */
    apply_range_set_operation(corpus1, RReduce, NULL, NULL);
    touch_corpus(corpus1);

    break;


  case RDiff:

    /*
     * -------------------- DIFFERENCE
     * (implementation virtually identical to intersection)
     */

    i = 0;                      /* the position in corpus1 */
    j = 0;                      /* the position in corpus2 */

    while (i < corpus1->size && j < corpus2->size)

      /* compare start positions; if not equal, advance subcorpus where start position is smaller */
      if (corpus1->range[i].start < corpus2->range[j].start)
        i++;
      else if (corpus1->range[i].start > corpus2->range[j].start)
        j++;
      else {

        /* both start positions are identical. Now check whether the end positions are also the same */
        if (corpus1->range[i].end == corpus2->range[j].end) {
          /* only ranges found in both subcorpora are deleted */
          corpus1->range[i].start = -1;
          i++;
          j++;
        }
        else {

          /* end positions are not the same -> advance subcorpus where end position is smaller */
          if (corpus1->range[i].end < corpus2->range[j].end)
            i++;
          else
            j++;
        }
      }

    /* remove ranges marked for deletion */
    apply_range_set_operation(corpus1, RReduce, NULL, NULL);
    touch_corpus(corpus1);

    break;


    /* The current DFA evaluation strategy often produces several spurious matches for each real match.
     *
     * To delete the extra matches we need three variants of the RUniq operator.
     *  (a) matching strategy 'standard' -> RLeftMaximalMatches
     *    use the match with the leftmost start and end points;
     *    since we cannot know which ranges belong to the same real match, we have to select the first
     *    and shortest range, thus deleting any potential overlapping matches in the process
     *    (might lead to strange effects occasionally -- evaluation strategy should be reimplemented in CWB-4.0)
     *  (b) matching strategy 'longest' -> RMaximalMatches
     *    use the match with the leftmost start point and rightmost end point,
     *    which is implemented by deleting all matches contained in another, longer interval;
     *    (which does _not_ automatically delete overlapping matches!)
     *  (c) matching strategy 'shortest' -> RMinimalMatches
     *    opposite of (b): use the match with the rightmost start point and leftmost end point,
     *    which is implemented by deleting all matches that contain another, shorter interval;
     *    (which, again, does _not_ automatically delete overlapping matches!)
     */

  case RMinimalMatches:

    /*
     * -------------------- MINIMAL MATCHES
     * is a cousin of UNIQ ... it removes all intervals from corpus1 which contain another (shorter) interval
     */

    for (i = 0; i < corpus1->size; i++) {
      /* i is point */
      if (corpus1->range[i].start != -1) {
        /* skip intervals we've already deleted */
        int start = corpus1->range[i].start;
        j = i+1;
        /* i becomes mark, j is now point */

        while ((j < corpus1->size) && (corpus1->range[j].start <= corpus1->range[i].end)) {
          if (corpus1->range[j].end <= corpus1->range[i].end) { /* j.start >= i.start implied by j > i */
            corpus1->range[i].start = -1; /* delete i if j is fully contained in it */
            break;
            /* no need to continue the inner loop if interval i is already deleted */
          }
          else {
          /* we may have multiple matches with the same starting point; because of the ordering used,
             we must forward delete in this case (the first match in the row is the shortest) */
            if (start == corpus1->range[j].start)
              corpus1->range[j].start = -1; /* delete i if i is contained in j */
            j++;
          }
        }
      }
    }

    /* remove ranges marked for deletion */
    apply_range_set_operation(corpus1, RReduce, NULL, NULL);
    touch_corpus(corpus1);

    break;


  case RMaximalMatches:

    /*
     * -------------------- MAXIMAL MATCHES
     * is a cousin of UNIQ ... it removes all intervals from corpus1 which are contained in another (longer) interval
     */

    i = 0;                      /* point */
    for (i = 0; i < corpus1->size; i++) {
      if (corpus1->range[i].start != -1) { /* skip intervals we've already deleted */
        int start = corpus1->range[i].start;
        j = i+1;                /* i becomes mark, j is now point */
        while ((j < corpus1->size) &&
               (corpus1->range[j].start <= corpus1->range[i].end))
          {
            if (corpus1->range[j].end <= corpus1->range[i].end) /* j.start >= i.start implied by j > i */
              corpus1->range[j].start = -1; /* delete j if j is contained in i */
            else
            /* we may have multiple matches with the same starting point; because of the ordering used,
               we must backward delete in this case (the last match will be the longest) */
              if (start == corpus1->range[j].start)
                corpus1->range[i].start = -1; /* delete i if i is contained in j */
            j++;
          }
      }
    }

    /* remove ranges marked for deletion */
    apply_range_set_operation(corpus1, RReduce, NULL, NULL);
    touch_corpus(corpus1);

    break;


  case RLeftMaximalMatches:

    /*
     * -------------------- LEFT MAXIMAL MATCHES
     * used for the shortest match strategy ... delete additional matches inserted by our multi-pass strategy
     * [keep shortest match from all matches with same start point, then keep longest one from all matches with same end point]
     */

    i = 0;                      /* point */
    for (i = 0; i < corpus1->size; i++) {
      if (corpus1->range[i].start != -1) { /* skip intervals we've already deleted */
        j = i+1;                /* i becomes mark, j is now point */
        /* delete all ranges overlapping with the current mark */
        while ((j < corpus1->size) && (corpus1->range[j].start == corpus1->range[i].start)) {
          corpus1->range[j].start = -1; /* delete all matches j with the same start point as i (-> i is shorter) */
          j++;
        }
      }
    }
    apply_range_set_operation(corpus1, RReduce, NULL, NULL); /* remove matches deleted in first pass */

    i = 0;                      /* point */
    for (i = 0; i < corpus1->size; i++) {
      if (corpus1->range[i].start != -1) { /* skip intervals we've already deleted */
        j = i+1;                /* i becomes mark, j is now point */
        while ((j < corpus1->size) && (corpus1->range[j].start <= corpus1->range[i].end)) {
          if (corpus1->range[j].end == corpus1->range[i].end)   /* j.start >= i.start implied by j > i */
            corpus1->range[j].start = -1; /* delete all matches j with the same end point as i (-> j is shorter) */
          j++;
        }
      }
    }
    apply_range_set_operation(corpus1, RReduce, NULL, NULL); /* remove matches deleted in second pass */
    touch_corpus(corpus1);

    break;


  case RNonOverlapping:

    /*
     * -------------------- NON-OVERLAPPING MATCHES
     * delete overlapping matches before executing subquery (chooses earliest match)
     */

    i = 0;                      /* point */
    for (i = 0; i < corpus1->size; i++) {
      if (corpus1->range[i].start != -1) { /* skip intervals we've already deleted */
        j = i+1;                /* i becomes mark, j is now point */
        /* delete all ranges overlapping with match i */
        while ((j < corpus1->size) &&
               (corpus1->range[j].start <= corpus1->range[i].end))
          {
            corpus1->range[j].start = -1;
            j++;
          }
      }
    }

    /* remove ranges marked for deletion */
    apply_range_set_operation(corpus1, RReduce, NULL, NULL);
    touch_corpus(corpus1);

    break;


  case RUniq:

    /*
     * -------------------- UNIQ
     * remove duplicate intervals from corpus1 (working destructively on it);
     * targets/keywords taken from first occurrence
     */

    /* Why delete intervals where start points are identical but end points different?
       It doesn't solve the problem of overlapping ranges in subqueries, so what was
       Oli's intention? Simplest algorithm he could think of?  */
    /* complete rewrite follows: */

    i = 0;                      /* point */
    for (i = 0; i < corpus1->size; i++) {
      if (corpus1->range[i].start != -1) { /* skip intervals we've already deleted */
        j = i+1;                /* i becomes mark, j is now point */
        while ((j < corpus1->size) &&
               (corpus1->range[i].start == corpus1->range[j].start) &&
               (corpus1->range[i].end   == corpus1->range[j].end))
          {
            corpus1->range[j].start = -1; /* delete all but the first */
            j++;
          }
      }
    }

    /* remove ranges marked for deletion */
    apply_range_set_operation(corpus1, RReduce, NULL, NULL);
    touch_corpus(corpus1);

    break;


  case RReduce:

    /*
     * -------------------- REDUCE
     * remove intervals marked for deletion (-1) from corpus1;
     * adjust target & keyword vectors
     */

    if (corpus1->range && (corpus1->size > 0)) {

      ins = 0;                  /* the insertion point */

      for (i = 0; i < corpus1->size; i++) {
        if (corpus1->range[i].start < 0 || corpus1->range[i].end < 0) {
          /* if this interval is marked as -1 in either member, do nothing */
        }
        else {
          /* otherwise, copy this to the insertion point */
          if (i != ins) {
            corpus1->range[ins].start = corpus1->range[i].start;
            corpus1->range[ins].end = corpus1->range[i].end;

            if (corpus1->targets)
              corpus1->targets[ins] = corpus1->targets[i];

            if (corpus1->keywords)
              corpus1->keywords[ins] = corpus1->keywords[i];

          }
          ins++;
          /* if i IS the same as the insertion point, we don't need to copy -
           * we haven't encountered any deletables yet, so just keep scrolling
           * both i and ins. */
        }
      } /* endfor each interval in the corpus */

      if (ins != corpus1->size) {

        /* no elements were deleted from the list when ins == size. So
         * we do not have to do anything then.
         * Otherwise, the list was used destructively. Free up used space.
         */

        corpus1->range = (Range *)cl_realloc(corpus1->range, sizeof(Range) * ins);

        if (corpus1->targets)
          corpus1->targets = (int *)cl_realloc(corpus1->targets, sizeof(int) * ins);

        if (corpus1->keywords)
          corpus1->keywords = (int *)cl_realloc(corpus1->keywords, sizeof(int) * ins);

        corpus1->size = ins;

        cl_free(corpus1->sortidx); /* the sort index is no longer valid in this case -> make sure it is deallocated and set to NULL */

        touch_corpus(corpus1);
      }
    }

    break;

  default:
    fprintf(stderr, "Operation was %d, ranges from %d to %d\n", operation, RUnion, RReduce);
    assert("Illegal operator in RangeSetOp" && 0);
    return 0;
    break;
  } /* end of big switch across all the various operations */

  return 1;
}


/* -------------------------------------------------- SORTING and COUNTING */

/* static data for sort function callbacks (shared by external sorting);
 * note much of this replicates the contents of a SortClause object, q.v. */
static CorpusList *srt_cl;              /**< The CorpusList object representing a query to be sorted. */
static Attribute *srt_attribute;        /**< The )p-)Attribute on which a query is to be sorted. */
static int *srt_start;                  /**< When sorting a query, this contains start positions of intervals to be sorted */
static int *srt_end;                    /**< When sorting a query, this contains end positions of intervals to be sorted */
static FieldType srt_anchor1;           /**< In a query sort, indicates the field type of the start of sort region */
static int srt_offset1;                 /**< In a query sort, indicates the offset of the start of sort region */
static FieldType srt_anchor2;           /**< In a query sort, indicates the field type of the end of sort region */
static int srt_offset2;                 /**< In a query sort, indicates the offset of the end of sort region */
static int srt_flags;                   /**< Whether to use the %c and/or %d flags when sorting a query. */
static int srt_ascending;               /**< boolean: sort query into ascending order or not */
static int srt_reverse;                 /**< boolean: sort query on reversed-character-sequence strings
                                             (and reversed sequences OF strings) or not */
static int text_size;                   /**< When sorting a query - this represents the size of the corpus the query belongs to */
static int break_ties;                  /**< whether to break ties (by comparison without %cd flags,
                                             and by line number in the last instance) */
static unsigned int *random_sort_keys;  /**< random keys for randomized sort order (ties are broken by cpos of matches) */


/* static data for count function callbacks */
static int *group_first;        /**< first match for each group of identical (or equivalent) sort strings */
static int *group_size;         /**< number of matches for each group of identical (or equivalent) sort strings */
static int *current_sortidx;    /**< alias to newly created sortidx, so it can be accessed by the callback function */

/**
 * Use an external program to sort a query.
 *
 * No parameters - the assumption is that everything is set up
 * already by the SortSubCorpus function which calls this one.
 */
int
SortExternally(void)
{
  /* uses settings from static srt_* variables */
  char temporary_name[TEMP_FILENAME_BUFSIZE];
  FILE *tmp;
  FILE *pipe;
  char sort_call[CL_MAX_LINE_LENGTH];

  if (NULL != (tmp = open_temporary_file(temporary_name))) {
    int line, p1start, p1end, plen, step, token, l;

    line = -1;                  /* will indicate sort failure below if text_size == 0 */
    if (text_size > 0) {
      for (line = 0; line < srt_cl->size; line++) {
        fprintf(tmp, "%d ", line);

        /* determine start and end position of sort interval for this match */
        switch (srt_anchor1) {
        case MatchField:
            p1start = srt_cl->range[line].start + srt_offset1;
            break;
        case MatchEndField:
          p1start = srt_cl->range[line].end + srt_offset1;
          break;
        case KeywordField:
          p1start = srt_cl->keywords[line] + srt_offset1;
          break;
        case TargetField:
          p1start = srt_cl->targets[line] + srt_offset1;
          break;
        default:
          assert(0 && "Oopsie -- illegal first anchor in SortExternally()");
          break;
        }

        switch (srt_anchor2) {
        case MatchField:
          p1end = srt_cl->range[line].start + srt_offset2;
          break;
        case MatchEndField:
          p1end = srt_cl->range[line].end + srt_offset2;
          break;
        case KeywordField:
          p1end = srt_cl->keywords[line] + srt_offset2;
          break;
        case TargetField:
          p1end = srt_cl->targets[line] + srt_offset2;
          break;
        default:
          assert(0 && "Oopsie -- illegal second anchor in SortExternally()");
          break;
        }

        /* adjust sort boundaries at start and end of corpus */
        if (p1start < 0)
          p1start = 0;
        else if (p1start >= text_size)
          p1start = text_size - 1;

        if (p1end < 0)
          p1end = 0;
        else if (p1end >= text_size)
          p1end = text_size - 1;

        /* swap start and end of interval for reverse sorting */
        if (srt_reverse) {
          int temp;
          temp = p1start;
          p1start = p1end;
          p1end = temp;
        }

        /* determine sort direction */
        step = (p1end >= p1start) ? 1 : -1;

        /* how many tokens to print */
        plen = abs(p1end - p1start) + 1;


        /* when using flags, print normalised token sequence first (after applying cl_string_canonical) */
        if (srt_flags) {
          token = p1start;
          for (l=1 ; l <= plen ; l++) {
            char *value = cl_cpos2str(srt_attribute, token);
            int del_value = 0;

            if (value) {
              int i, p = strlen((char *) value);
              if (srt_flags) {
                value = cl_string_canonical(value, srt_cl->corpus->charset, srt_flags, CL_STRING_CANONICAL_STRDUP);
                del_value = 1;
              }
              if (srt_reverse) {
                char *newvalue = cl_string_reverse(value, srt_cl->corpus->charset);
                if (del_value)
                  cl_free(value);
                value = newvalue;
                del_value = 1;
              }

              for (i = 0; i < p; i++)
                fputc((unsigned char)value[i], tmp);
              fputc(' ', tmp);
              if (del_value)
                cl_free(value);
            }
            token += step;
          }
          fprintf(tmp, "\t");
        }

        /* print sequence of tokens in sort interval */
        token = p1start;
        for (l = 1 ; l <= plen ; l++) {
          char *value = cl_cpos2str(srt_attribute, token);
          int del_value = 0;

          if (value) {
            int i, p = strlen((char *) value);

            if (srt_reverse) {
              del_value = 1;
              value = cl_string_reverse(value, srt_cl->corpus->charset);
            }
            for (i = 0; i < p; i++)
              fputc((unsigned char) value[i], tmp);
            fputc(' ', tmp);
            if (del_value)
              cl_free(value);
          }
          token += step;
        } /* end for each token */
        fprintf(tmp, "\n");
      }

      fclose(tmp);

      /* now, execute the external sort command on the temporary file */
      sprintf(sort_call, "%s %s %s | gawk '{print $1}'", ExternalSortingCommand, (srt_ascending ? "" : "-r"), temporary_name);
      if (SORT_DEBUG)
        fprintf(stderr, "Running sort: \n\t%s\n", sort_call);

      /* run sort cmd and read from pipe */
      line = -1;                /* will indicate failure of external sort command  */
      if ((pipe = popen(sort_call, "r")) == NULL) {
        perror("Failure opening sort pipe");
        cqpmessage(Error, "Can't execute external sort:\n\t%s\n"
                   "Disable external sorting with 'set UseExternalSorting off;'",
                   sort_call);
      }
      else {
        if (! srt_cl->sortidx)
          srt_cl->sortidx = (int *)cl_malloc(srt_cl->size * sizeof(int));
        for (line = 0; line < srt_cl->size; line++)
          srt_cl->sortidx[line] = -1;

        line = 0;
        while (fgets(sort_call, CL_MAX_LINE_LENGTH, pipe)) {
          if (line < srt_cl->size) {
            int num = atoi(sort_call);
            if (num < 0 || num >= srt_cl->size) {
              fprintf(stderr, "Error in externally sorted file - line number #%d out of range\n", num);
              break;            /* abort */
            }
            srt_cl->sortidx[line] = num;
            line++;
          }
          else
            fprintf(stderr, "Warning: too many lines from external sort command (ignored).\n");
        }
        pclose(pipe);
      }
    }

    if (unlink(temporary_name)) {
      perror(temporary_name);
      cqpmessage(Warning, "Couldn't remove temporary file %s (ignored)\n\tPlease remove the file manually.", temporary_name);
    }

    /* now we should have read exactly cl->size lines; otherwise something went wrong */
    if (line == srt_cl->size)
      return 1;
    else {
      cqpmessage(Error, "External sort failed (reset to default ordering).");
      cl_free(srt_cl->sortidx);
      return 0;
    }
  }
  else {
    perror("Can't create temporary file");
    cqpmessage(Error, "Couldn't create temporary file for external sort (aborted).");
    return 0;
  }
}

/**
 * Defined if a sort cache is to be used in sorting concordance lines.
 *
 * The sort cache (caching the lexicon IDs of the first two tokens to be compared in each line)
 * is indispensable for the internal sorting algorithm since random accesses to a compressed corpus
 * are painfully slow; when sorting variable length matches such as German NPs on word forms,
 * the current implementation has a hit rate of around 99%.
 */
#define USE_SORT_CACHE
/* another tweaking option would be memoization of comparisons results, requiring 2 ints + 1 bit
 * per comp. for storage, probably in a hash; I have no idea how much that would improve the
 * performance, esp. with a fixed hash size; other options are a hash table for identical lines
 * or an incremental sort (sort on first token, group lines for which compare identical, then
 * sort each group on second token, etc.); note that an incremental sort would probably enforce
 * the slightly incorrect semantics of case-/diacritic-insensitive in the current implementation;
 * it might be possible to reuse a custom qsort algorithm implemented for the fdist command (but
 * perhaps I can just fall back on the standard qsort there);
 * external sorting isn't such a bad idea after all :-) */

#ifdef USE_SORT_CACHE
/** Pointer to the sort cache @see USE_SORT_CACHE */
static int *sort_id_cache = NULL;
#endif

/**
 * Compare two matches according to current sort settings in static variables
 * (qsort callback used in query result sorting).
 *
 * This is the primary query-hit-comparison function. It wraps cl_string_qsort_compare
 * for string comparison, but does much more as well, because we are not just comparing
 * individual strings but rather, potentially, whole bundles of strings from various
 * different positions.
 *
 * @param vidx1  Pointer to the integer index of the first of the intervals to be
 *               compared (ie an index into an array of start/end positions).
 * @param vidx2  Pointer to the integer index of the second of the intervals to be
 *               compared.
 * @return       Usual returns for qsort callbacks.
 */
static int
i2compare(const void *vidx1, const void *vidx2)
{
  const int *idx1 = vidx1, *idx2 = vidx2;

  int p1start, p1end;           /* boundaries of sort intervals */
  int p2start, p2end;
  int step1, step2;             /* direction of comparison (might actually be different for the two matches *ouch*) */

  int pos1, pos2;
  int len1, len2, minlen;
  int pass, i;

  int comp;                     /* the comparison result */

  if (! EvaluationIsRunning)
    return 0;                   /* user interrupt (Ctrl-C) => force qsort to finish quickly */

  if (*idx1 == *idx2)
    return 0;                   /* self-comparison: return equality */

  /* determine start and end position of sort interval for both matches */
  p1start = srt_start[*idx1];
  p1end =   srt_end[*idx1];
  p2start = srt_start[*idx2];
  p2end =   srt_end[*idx2];

  /* direction of comparison
     (might be different if inconsistently set targets are used, but that's the user's fault) */
  step1 = (p1end < p1start) ? -1 : 1;
  step2 = (p2end < p2start) ? -1 : 1;

  /*
   * now compare the interval [p1start, p1end] with the interval [p2start, p2end],
   * incrementing the cpos after each comparison by step1 and step2, respectively
   * (similar to the standard comparison algorithm in cl_string_qsort_compare() above)
   */
  if (SORT_DEBUG)
    fprintf(stderr, "Comparing [%d,%d](%+d) with [%d,%d](%+d)\n",
            p1start, p1end, step1, p2start, p2end, step2);

  len1 = abs(p1end - p1start) + 1;
  len2 = abs(p2end - p2start) + 1;
  minlen = MIN(len1, len2);
  comp = 0;

  /* first pass does case-/diacritic-insensitive comparison (may be skipped), second pass does plain comparison */
  for (pass = (srt_flags) ? 1 : 2 ; pass <= 2 && comp == 0 ; pass++) {
    pos1 = p1start;
    pos2 = p2start;

    for (i = 1; (i <= minlen) && (comp == 0); i++) {
      int id1, id2;
      unsigned char *s1, *s2;

#ifdef USE_SORT_CACHE
      /* use cache for first comparison only (to avoid repeated overwriting) */
      if (i == 1) {
        id1 = sort_id_cache[2*(*idx1)];
        id2 = sort_id_cache[2*(*idx2)];
      }
      else if (i == 2) {
        id1 = sort_id_cache[2*(*idx1)+1];
        id2 = sort_id_cache[2*(*idx2)+1];
      }
      else {
        id1 = cl_cpos2id(srt_attribute, pos1);
        id2 = cl_cpos2id(srt_attribute, pos2);
      }
#else
      id1 = cl_cpos2id(srt_attribute, pos1);
      id2 = cl_cpos2id(srt_attribute, pos2);
#endif

      if (id1 != id2) {         /* same lexicon IDs always compare equal */
        s1 = (unsigned char *)cl_id2str(srt_attribute, id1);
        s2 = (unsigned char *)cl_id2str(srt_attribute, id2);

        if (pass == 1)
          /* compare normalised strings in first pass (srt_flags are set in this case) */
          comp = cl_string_qsort_compare((char *)s1, (char *)s2, srt_cl->corpus->charset, srt_flags, srt_reverse);
        else
          /* in pass 2, compare without flags. */
          comp = cl_string_qsort_compare((char *)s1, (char *)s2, srt_cl->corpus->charset, 0,         srt_reverse);
      }

      pos1 += step1;
      pos2 += step2;
    }

    if (comp == 0) {            /* intervals compared equal up to the length of the shorter one -> compare lengths */
      if (len1 > len2)
        comp = 1;
      else if (len1 < len2)
        comp = -1;
    }
    /* may try without %cd flags to break ties, but only if that variable is set */
    if (! break_ties)
      break;
  } /* endfor each of the two passes */

  if (comp == 0 && break_ties) {
    if (idx1 > idx2)            /* break ties in order of original matchlist */
      comp = 1;
    else
      comp = -1;
  }

  if (!srt_ascending)          /* adjust sort order for descending sort */
    comp = -comp;

  return comp;
}

/** Compares two groups of equivalent matches by group sizes (descending), breaking ties through i2compare. */
static int
group2compare(const void *vidx1, const void *vidx2)
{
  const int *idx1 = vidx1, *idx2 = vidx2;
  int s1, s2;

  if (! EvaluationIsRunning)
    return 0;
  if (*idx1 == *idx2)
    return 0;
  s1 = group_size[*idx1];
  s2 = group_size[*idx2];
  if (s1 > s2)                  /* descending sort order */
    return -1;
  else if (s1 < s2)
    return 1;
  else
    return i2compare(&(current_sortidx[group_first[*idx1]]), &(current_sortidx[group_first[*idx2]]));
}

/* simulate Perl's spaceship operator A <=> B */
#define spaceship(A,B) ((A) > (B)) ? 1 : ((A) < (B)) ? -1 : 0

/**
 * Sorts hits in random order by comparing random numbers in the vector random_sort_keys[],
 * breaking ties by start and end positions of matches (from *srt_cl) in order to ensure stable sorting.
 *
 * This is another qsort callback function.
 */
static int
random_compare(const void *vidx1, const void *vidx2)
{
  int idx1 = *((int *) vidx1), idx2 = *((int *) vidx2);
  int result = spaceship(random_sort_keys[idx1], random_sort_keys[idx2]);
  if (result == 0)
    result = spaceship(srt_cl->range[idx1].start, srt_cl->range[idx2].start);
  if (result == 0)
    result = -spaceship(srt_cl->range[idx1].end, srt_cl->range[idx2].end);
  return result;
}

/**
 * Sorts a query result in random order.
 *
 * If seed > 0, a reproducible and stable ordering is generated
 * based on the start and end corpus positions of matches
 * (i.e. two given matches will always be sorted in the same
 * order).
 *
 * @param cl    Corpus-list object representing the query to sort.
 * @param seed  Seed for the randomiser; should ideally be a prime number
 *              (2^31 is a particularly bad choice); if it is 0, then
 *              the internal RNG's standard random order is used.
 */
int
SortSubcorpusRandomize(CorpusList *cl, int seed)
{
  int n_matches, i, ok;

  if (cl == NULL) {
    cqpmessage(Error, "No query result specified for sorting.");
    return 0;
  }
  if (cl->size <= 0) {
    cqpmessage(Info, "Nothing to sort (ignored),");
    return 0;
  }
  if (!access_corpus(cl)) {
    cqpmessage(Error, "Can't access query result %s (aborted).", cl->name);
    return 0;
  }
  srt_cl = cl; /* has been validated, so it can safely be used by the callback function */
  n_matches = cl->size;

  /* initialise random sort keys (using supplied seed) */
  if (random_sort_keys != NULL)
    /* in case it's still allocated from last call... */
    cl_free(random_sort_keys);
  random_sort_keys = (unsigned int *) cl_malloc(n_matches * sizeof(unsigned int));
  if (seed) {
    /* stable randomized order (calculated from match range by RNG transformation) */
    for (i = 0; i < n_matches; i++) {
      /* completely arbitrary */
      cl_set_rng_state(cl->range[i].start + seed,
                       (seed * (cl->range[i].end - cl->range[i].start)) ^ cl->range[i].end
                       );
      /* apply RNG transformation 3 times to destroy systematic patterns in initialisation */
      cl_random();
      cl_random();
      random_sort_keys[i] = cl_random();
    }
  }
  else {
    /* standard randomized order (using internal RNG) */
    for (i = 0; i < n_matches; i++)
      random_sort_keys[i] = cl_random();
  }

  /* allocate and initialise sorted index */
  if (cl->sortidx == NULL)
    cl->sortidx = (int *) cl_malloc(n_matches * sizeof(int));
  for (i = 0; i < n_matches; i++)
    cl->sortidx[i] = i;

  EvaluationIsRunning = 1;
  ok = 1;
  qsort(cl->sortidx, cl->size, sizeof(int), random_compare);
  if (! EvaluationIsRunning) {
    cqpmessage(Warning, "Sort/count operation aborted by user (reset to default ordering).");
    if (which_app == cqp)
      install_signal_handler();
    cl_free(cl->sortidx);
    ok = 0;
  }
  EvaluationIsRunning = 0;

  /* clean up and return status */
  cl_free(random_sort_keys);
  touch_corpus(cl);
  return ok;
}

/**
 * Sort the (query) subcorpus specified by cl, or count frequencies of matching strings.
 *
 * (Note that frequency counting and query result sorting are done via the same sorting
 * algorithm.)
 *
 * If the sort was not performed successfully, the sort index is reset to the default
 * sort order, and the function returns false.
 *
 * @param cl          Subcorpus designating the query to sort.
 * @param sc          A sort clause. sc = NULL resets the sort index to the default sort
 *                    order (i.e. sorted by corpus position).
 * @param count_mode  Boolean: run the function in count frequency mode?
 * @param redir       Redir object for where the output of string-counting is to be
 *                    displayed.
 * @return            Boolean: true for successful sort, false for unsuccessful.
 */
int
SortSubcorpus(CorpusList *cl, SortClause sc, int count_mode, struct Redir *redir)
{
  int i, k, ok;
  char *srt_att_name;

  if (cl == NULL) {
    cqpmessage(Error, "No query result specified for sorting.");
    return 0;
  }
  if (cl->size <= 0) {
    cqpmessage(Info, "Nothing to sort (ignored),");
    return 0;
  }
  if (!access_corpus(cl)) {
    cqpmessage(Error, "Can't access query result %s (aborted).", cl->name);
    return 0;
  }
  if (sc == NULL) {             /* sort by corpus position, i.e. delete sortidx */
    if (count_mode) {
      cqpmessage(Error, "Count what? (e.g. 'by word')");
      return 0;
    }
    else {
      cl_free(cl->sortidx);
      touch_corpus(cl);
      return 1;
    }
  }

  /* set up static attributes for sort callback functions */
  srt_att_name = (sc->attribute_name) ? sc->attribute_name : CWB_DEFAULT_ATT_NAME;
  if (!(srt_attribute = cl_new_attribute(cl->corpus, srt_att_name, ATT_POS))) {
    cqpmessage(Error, "Can't find %s attribute for sorting (aborted).", srt_att_name);
    return 0;
  }
  text_size = cl_max_cpos(srt_attribute);
  srt_cl = cl;
  srt_ascending = sc->sort_ascending;
  srt_reverse = sc->sort_reverse;
  break_ties = 1;

  srt_flags = sc->flags;

  /* test whether anchors for sort interval are defined */
  srt_anchor1 = sc->anchor1;
  srt_offset1 = sc->offset1;
  /* this test will be simplified and made more robust when we have a full match table implementation */
  if ((srt_anchor1 == KeywordField) && (cl->keywords == NULL)) {
    cqpmessage(Error, "No keyword anchors defined (aborted).");
    return 0;
  }
  if ((srt_anchor1 == TargetField) && (cl->targets == NULL)) {
    cqpmessage(Error, "No target anchors defined (aborted).");
    return 0;
  }
  assert(srt_anchor1 != NoField);

  srt_anchor2 = sc->anchor2;
  srt_offset2 = sc->offset2;
  if ((srt_anchor2 == KeywordField) && (cl->keywords == NULL)) {
    cqpmessage(Error, "No keyword anchors defined (aborted).");
    return 0;
  }
  if ((srt_anchor2 == TargetField) && (cl->targets == NULL)) {
    cqpmessage(Error, "No target anchors defined (aborted).");
    return 0;
  }
  assert(srt_anchor2 != NoField);

  ok = 1;
  if (UseExternalSorting && !insecure && !count_mode) {
    ok = SortExternally();
  }
  else {
    /* precompute tables for start and end position of sort interval */
    srt_start = cl_malloc(cl->size * sizeof(int));
    srt_end = cl_malloc(cl->size * sizeof(int));

    switch (srt_anchor1) {
    case MatchField:
      for (i = 0; i < cl->size; i++)
        srt_start[i] = srt_cl->range[i].start + srt_offset1;
      break;
    case MatchEndField:
      for (i = 0; i < cl->size; i++)
        srt_start[i] = srt_cl->range[i].end + srt_offset1;
      break;
    case KeywordField:
      for (i = 0; i < cl->size; i++)
        srt_start[i] = srt_cl->keywords[i] + srt_offset1;
      break;
    case TargetField:
      for (i = 0; i < cl->size; i++)
        srt_start[i] = srt_cl->targets[i] + srt_offset1;
      break;
    default:
      assert(0 && "Oopsie -- illegal first anchor in SortSubcorpus()");
      break;
    }
    for (i = 0; i < cl->size; i++) {
      if (srt_start[i] < 0)
        srt_start[i] = 0;
      else if (srt_start[i] >= text_size)
        srt_start[i] = text_size - 1;
    }

    switch (srt_anchor2) {
    case MatchField:
      for (i = 0; i < cl->size; i++)
        srt_end[i] = srt_cl->range[i].start + srt_offset2;
      break;
    case MatchEndField:
      for (i = 0; i < cl->size; i++)
        srt_end[i] = srt_cl->range[i].end + srt_offset2;
      break;
    case KeywordField:
      for (i = 0; i < cl->size; i++)
        srt_end[i] = srt_cl->keywords[i] + srt_offset2;
      break;
    case TargetField:
      for (i = 0; i < cl->size; i++)
        srt_end[i] = srt_cl->targets[i] + srt_offset2;
      break;
    default:
      assert(0 && "Critical error -- illegal first anchor in SortSubcorpus()");
      break;
    }
    for (i = 0; i < cl->size; i++) {
      if (srt_end[i] < 0)
        srt_end[i] = 0;
      else if (srt_end[i] >= text_size)
        srt_end[i] = text_size - 1;
    }
    /* ok, so now the positions have been moved from the srt_cl to the
     * global sorting-variables. */

    /* swap start and end positions in reverse sort */
    if (srt_reverse) {
      int *temp;
      temp = srt_start; srt_start = srt_end; srt_end = temp;
    }

    /* allocate and initialise sorted index */
    if (cl->sortidx == NULL)
      cl->sortidx = (int *)cl_malloc(cl->size * sizeof(int));
    for (i = 0; i < cl->size; i++)
      cl->sortidx[i] = i;

#ifdef USE_SORT_CACHE
    /* load up the sort cache.... */
    sort_id_cache = (int *)cl_malloc(cl->size * 2 * sizeof(int));
    for (i = 0; i < cl->size; i++) {
      int cpos1 = srt_start[i];
      int cpos2 = srt_end[i];
      int len = abs(cpos2 - cpos1) + 1;
      int step = (cpos2 >= cpos1) ? 1 : -1;
      sort_id_cache[2*i] = cl_cpos2id(srt_attribute, cpos1);
      if (len > 1)
        sort_id_cache[2*i+1] = cl_cpos2id(srt_attribute, cpos1 + step);
      else
        sort_id_cache[2*i+1] = 0;
    }
#endif

    /* the business end... the sorting happens here! */
    EvaluationIsRunning = 1;
    qsort(cl->sortidx, cl->size, sizeof(int), i2compare);
    if (! EvaluationIsRunning) {
      cqpmessage(Warning, "Sort/count operation aborted by user (reset to default ordering).");
      if (which_app == cqp) install_signal_handler();
      cl_free(cl->sortidx);
      ok = 0;
    }
    EvaluationIsRunning = 0;
    /* note that, unless we are in count mode, this is more or less the end of it.... */

    /* in count mode, group identical (or equivalent) sort strings, then sort by group sizes */
    if (ok && count_mode) {
      int *groupidx = NULL;
      int n_groups, first;
      current_sortidx = cl->sortidx;

      group_first = cl_malloc(cl->size * sizeof(int)); /* worst case: cl->size groups with f = 1 */
      group_size = cl_malloc(cl->size * sizeof(int));

      break_ties = 0;           /* don't break ties for grouping */
      n_groups = 0;
      first = group_first[n_groups] = 0;

      EvaluationIsRunning = 1;
      /* collect equivalent matches into groups */
      for (i = 0; (i < cl->size) && EvaluationIsRunning; i++) {
        if (i > 0) {
          if (i2compare(current_sortidx + first, current_sortidx + i)) {
            group_size[n_groups] = i - first;
            first = group_first[++n_groups] = i;
          }
        }
      }
      group_size[n_groups++] = i - first;
      /* sort groups by their size (= frequency of sort string) in descending order */
      if (EvaluationIsRunning) {
        groupidx = cl_malloc(n_groups * sizeof(int));
        for (i = 0; i < n_groups; i++) groupidx[i] = i;
        qsort(groupidx, n_groups, sizeof(int), group2compare);
      }
      if (! EvaluationIsRunning) {
        cqpmessage(Warning, "Count operation aborted by user.");
        if (which_app == cqp) install_signal_handler();
        ok = 0;
      }
      EvaluationIsRunning = 0;

      /* if successful, display groups with their frequencies */
      if (open_stream(redir, cl->corpus->charset)) {
        for (i = 0; (i < n_groups) && !cl_broken_pipe; i++) {
          int first = group_first[groupidx[i]];
          int size = group_size[groupidx[i]];
          if (size >= count_mode) {
            int start = srt_start[current_sortidx[first]]; /* cpos range of sort string */
            int end = srt_end[current_sortidx[first]];
            int len = abs(end - start) + 1;
            int step = (end >= start) ? 1 : -1;

            fprintf(redir->stream, "%d\t", size);
            if (!pretty_print) /* without pretty-printing: show first match in second column, for automatic processing */
              fprintf(redir->stream, "%d\t", first);
            for (k = 0; k < len; k++) {
              int cpos = start + step * k;
              char *token_readonly = cl_cpos2str(srt_attribute, cpos);
              /* normalise token if %cd was given */
              char *token = cl_string_canonical(token_readonly, cl->corpus->charset, sc->flags, CL_STRING_CANONICAL_STRDUP);
              if (srt_reverse) {
                /* reverse the token */
                char *temp = cl_string_reverse(token, cl->corpus->charset);
                cl_free(token);
                token = temp;
              }
              if (k > 0)
                fprintf(redir->stream, " ");
              fprintf(redir->stream, "%s", token);
              cl_free(token);
            }
            if (pretty_print) { /* with pretty-printing: append range of matches belonging to group (in sorted corpus) */
              if (size > 1)
                fprintf(redir->stream, "  [#%d-#%d]",  first, first + size - 1);
              else
                fprintf(redir->stream, "  [#%d]",  first);
            }
            fprintf(redir->stream, "\n");
            fflush(redir->stream);
          }
        }
        close_stream(redir);
      }

      if (groupidx)
        cl_free(groupidx);
      cl_free(group_first);
      cl_free(group_size);
    } /* endif "we are in count mode!" */

#ifdef USE_SORT_CACHE
    cl_free(sort_id_cache);
#endif

    cl_free(srt_start);
    cl_free(srt_end);
  } /* end of "if not external sorting" */

  touch_corpus(cl);
  return ok;
}

/**
 * Frees a SortClause object.
 */
void
FreeSortClause(SortClause sc)
{
  if (sc) {
    cl_free(sc->attribute_name);
    cl_free(sc);
  }
}
