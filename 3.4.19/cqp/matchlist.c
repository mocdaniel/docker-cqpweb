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

#include "../cl/cl.h"
#include "../cl/attributes.h"
#include "../cl/cdaccess.h"

#include "matchlist.h"
#include "output.h"
#include "eval.h"



/*
 * Functions for manipulation of matching lists
 */

/**
 * Initialise the memebrs of the given Matchlist object.
 */
void
init_matchlist(Matchlist *matchlist)
{
  matchlist->start = NULL;
  matchlist->end =   NULL;
  matchlist->target_positions =  NULL;
  matchlist->keyword_positions = NULL;
  matchlist->tabsize = 0;
  matchlist->matches_whole_corpus = 0;
  matchlist->is_inverted = 0;
}

/**
 * Prints the entire contents of a Matchlist to stdout.
 */
void
show_matchlist(Matchlist matchlist)
{
  int i;

  fprintf(stderr, "Matchlist (size: %d, %sinverted):\n", matchlist.tabsize, matchlist.is_inverted ? "" : "not ");

  for (i = 0; i < matchlist.tabsize; i++)
    fprintf(stderr, "ml[%d] = [%d, %d] @:%d @9:%d\n",
            i,
            matchlist.start[i],
            matchlist.end[i],
            matchlist.target_positions ? matchlist.target_positions[i] : -1,
            matchlist.keyword_positions ? matchlist.keyword_positions[i] : -1
            );
}

/**
 * Prints the first few elements of a matchlist to stdout.
 *
 * "First few" equals up to 1000.
 *
 * Only the start positions are printed.
 */
void
show_matchlist_firstelements(Matchlist matchlist)
{
  int i;
  int n = (matchlist.tabsize >= 1000 ? 1000 : matchlist.tabsize % 1000);

  fprintf(stderr, "the first (max 1000) elements of the matchlist (size: %d) are:\n", matchlist.tabsize);
  for (i = 0; i < n; i++)
    fprintf(stderr, "ml[%d] = [%d,...]\n", i, matchlist.start[i]);
}


/**
 * Frees all the memory used within a matchlist, and re-initialises all its variables */
void
free_matchlist(Matchlist *matchlist)
{
  cl_free(matchlist->start);
  cl_free(matchlist->end);
  cl_free(matchlist->target_positions);
  cl_free(matchlist->keyword_positions);

  init_matchlist(matchlist);
}

/**
 * Perform "operation" on the two match lists (can be initial).
 *
 * The result is assigned to list1.
 *
 *
 * this whole code is WRONG when one of the matchlists is inverted
 * TODO!
 *
 * Also TODO: give it a better name.
 *
 * This contains, by far, most of the code in the Matchlist module.
 */
int
Setop(Matchlist *list1, MLSetOp operation, Matchlist *list2)
{
  int i, j, k, t, ins;
  Matchlist tmp;
  Attribute *attr;

  switch (operation) {

  case Union:

    /*
     * -------------------- UNION
     */

    /*
     * TODO:
     * optimize in case
     *   (list1->matches_whole_corpus && list2->matches_whole_corpus)
     */

    if (list2->start == NULL)

      if (list2->is_inverted) {
        /* l2 is empty, but inverted, so the result is the whole corpus,
         * as in l2. */
        return Setop(list1, Identity, list2);
      }
      else
        /* the result is list1, so just return */
        return 1;

    else if (list1->start == NULL)

      if (list1->is_inverted)
        /* empty, but inverted --> whole corpus, l1 */
        return 1;
      else
        /* the result is in list2, so return a copy */
        return Setop(list1, Identity, list2);

    else if (list1->is_inverted && list2->is_inverted) {

      /* union of 2 inverted lists is the inverted intersection */

      list1->is_inverted = 0;
      list2->is_inverted = 0;
      Setop(list1, Intersection, list2);
      list1->is_inverted = 1;

    }
    else {

      if (list1->is_inverted) {
        list1->is_inverted = 0;
        Setop(list1, Complement, NULL);
      }
      if (list2->is_inverted) {
        list2->is_inverted = 0;
        Setop(list2, Complement, NULL);
      }

      tmp.tabsize = list1->tabsize + list2->tabsize;

      tmp.start = (int *)cl_malloc(sizeof(int) * tmp.tabsize);

      if (list1->end && list2->end)
        tmp.end   = (int *)cl_malloc(sizeof(int) * tmp.tabsize);
      else
        tmp.end = NULL;

      if (list1->target_positions && list2->target_positions)
        tmp.target_positions = (int *)cl_malloc(sizeof(int) * tmp.tabsize);
      else
        tmp.target_positions = NULL;
      if (list1->keyword_positions && list2->keyword_positions)
        tmp.keyword_positions = (int *)cl_malloc(sizeof(int) * tmp.tabsize);
      else
        tmp.keyword_positions = NULL;





      i = 0;                        /* the position in list1 */
      j = 0;                        /* the position in list2 */
      k = 0;                        /* the insertion point in the result list `tmp' */


      while ((i < list1->tabsize) || (j < list2->tabsize))

        if ((i < list1->tabsize) && (list1->start[i] == -1))
          i++;
        else if ((j < list2->tabsize) && (list2->start[j] == -1))
          j++;
        else if ((j >= list2->tabsize) || ((i < list1->tabsize) && (list1->start[i] < list2->start[j]))) {

          /* copy (remaining) item from list1 */
          tmp.start[k] = list1->start[i];

          if (tmp.end)
            tmp.end[k] = list1->end[i];

          if (tmp.target_positions)
            tmp.target_positions[k] = list1->target_positions[i];
          if (tmp.keyword_positions)
            tmp.keyword_positions[k] = list1->keyword_positions[i];

          k++;
          i++;

        }
        else if ((i >= list1->tabsize) || ((j < list2->tabsize) && (list1->start[i] > list2->start[j]))) {

          /* copy (remaining) item from list2 */
          tmp.start[k] = list2->start[j];

          if (tmp.end)
            tmp.end[k] = list2->end[j];

          if (tmp.target_positions)
            tmp.target_positions[k] = list2->target_positions[j];
          if (tmp.keyword_positions)
            tmp.keyword_positions[k] = list2->keyword_positions[j];

          k++;
          j++;

        }
        else {
          /* both start positions are identical. Now check whether the end
           * positions are also the same => the ranges are identical and
           * the duplicate is to be eliminated.
           */
          tmp.start[k] = list1->start[i];

          if ((tmp.end == NULL) || (list1->end[i] == list2->end[j])) {

            /* real duplicate, copy once */
            if (tmp.end)
              tmp.end[k]   = list1->end[i];

            if (tmp.target_positions)
              tmp.target_positions[k]  = list1->target_positions[i];
            if (tmp.keyword_positions)
              tmp.keyword_positions[k] = list1->keyword_positions[i];

            i++;
            j++;

          }
          else {

            /* we have existing, non-equal end positions. copy the smaller one. */

            if (list1->end[i] < list2->end[j]) {
              tmp.end[k]   = list1->end[i];

              if (tmp.target_positions)
                tmp.target_positions[k] = list1->target_positions[i];
              if (tmp.keyword_positions)
                tmp.keyword_positions[k] = list1->keyword_positions[i];

              i++;
            }
            else {
              tmp.end[k]   = list2->end[j];

              if (tmp.target_positions)
                tmp.target_positions[k] = list2->target_positions[j];
              if (tmp.keyword_positions)
                tmp.keyword_positions[k] = list2->keyword_positions[j];

              j++;
            }

          }
          k++;
        }

      assert(k <= tmp.tabsize);

      /* we did not eliminate any duplicates if k==tmp.tabsize.
       * So, in that case, we do not have to bother with reallocs.
       */

      if (k < tmp.tabsize) {
        tmp.start = (int *)cl_realloc((char *)tmp.start, sizeof(int) * k);
        if (tmp.end)
          tmp.end = (int *)cl_realloc((char *)tmp.end, sizeof(int) * k);
        if (tmp.target_positions)
          tmp.target_positions = (int *)cl_realloc((char *)tmp.target_positions, sizeof(int) * k);
        if (tmp.keyword_positions)
          tmp.keyword_positions = (int *)cl_realloc((char *)tmp.keyword_positions, sizeof(int) * k);
      }

      cl_free(list1->start);
      cl_free(list1->end);
      cl_free(list1->target_positions);
      cl_free(list1->keyword_positions);

      list1->start = tmp.start;
      tmp.start = NULL;
      list1->end   = tmp.end;
      tmp.end = NULL;
      list1->target_positions  = tmp.target_positions;
      tmp.target_positions = NULL;
      list1->keyword_positions = tmp.keyword_positions;
      tmp.keyword_positions = NULL;
      list1->tabsize = k;
      list1->matches_whole_corpus = 0;
      list1->is_inverted = 0;
    }

    break;

  case Intersection:

    /*
     * -------------------- INTERSECTION
     */

    if (list1->tabsize == 0 && list1->is_inverted)

      /* l1 matches whole corpus, so intersection is equal to l2 */
      return Setop(list1, Identity, list2);

    else if (list2->tabsize == 0 && list2->is_inverted)
      /* l2 matches whole corpus, so intersection is equal to l1 */
      return 1;

    else if ((list1->tabsize == 0) || (list2->tabsize == 0)) {

      /*
       * Bingo. one of the two is empty AND NOT INVERTED. So
       * the intersection is also empty.
       */

      cl_free(list1->start);
      cl_free(list1->end);
      cl_free(list1->target_positions);
      cl_free(list1->keyword_positions);
      list1->tabsize = 0;
      list1->matches_whole_corpus = 0;
      list1->is_inverted = 0;

    }
    else if (list1->is_inverted && list2->is_inverted) {

      /* intersection of 2 inverted lists is the inverted union */

      list1->is_inverted = 0;
      list2->is_inverted = 0;
      Setop(list1, Union, list2);
      list1->is_inverted = 1;

    }
    else {

      /*
       * Two non-empty lists. ONE of both may be inverted.
       * We have to do some work then
       */

      if (list1->is_inverted)
        tmp.tabsize = list2->tabsize;
      else if (list2->is_inverted)
        tmp.tabsize = list1->tabsize;
      else
        tmp.tabsize = MIN(list1->tabsize, list2->tabsize);

      tmp.start = (int *)cl_malloc(sizeof(int) * tmp.tabsize);

      if (list1->end && list2->end)
        tmp.end   = (int *)cl_malloc(sizeof(int) * tmp.tabsize);
      else
        tmp.end = NULL;

      if (list1->target_positions && list2->target_positions)
        tmp.target_positions = (int *)cl_malloc(sizeof(int) * tmp.tabsize);
      else
        tmp.target_positions = NULL;
      if (list1->keyword_positions && list2->keyword_positions)
        tmp.keyword_positions = (int *)cl_malloc(sizeof(int) * tmp.tabsize);
      else
        tmp.keyword_positions = NULL;

      i = 0;                        /* the position in list1 */
      j = 0;                        /* the position in list2 */
      k = 0;                        /* the insertion point in the result list */

      while ((i < list1->tabsize) && (j < list2->tabsize))

        if (list1->start[i] < list2->start[j])
          i++;
        else if (list1->start[i] > list2->start[j])
          j++;
        else {

          /* both start positions are identical. Now check whether the end
           * positions are also the same => the ranges are identical and
           * one version is to be copied.
           */

          if ((tmp.end == NULL) || (list1->end[i] == list2->end[j])) {

            /* real duplicate, copy once */

            tmp.start[k] = list1->start[i];

            if (tmp.end)
              tmp.end[k]   = list1->end[i];

            if (tmp.target_positions)
              tmp.target_positions[k]   = list1->target_positions[i];
            if (tmp.keyword_positions)
              tmp.keyword_positions[k]   = list1->keyword_positions[i];

            i++;
            j++;
            k++;
          }
          else {

            /*
             * we have existing, non-equal end positions. Advance on
             * list with the smaller element.
             */

            if (list1->end[i] < list2->end[j])
              i++;
            else
              j++;
          }
        }

      assert(k <= tmp.tabsize);

      if (k == 0) {
        /* we did not copy anything. result is empty. */
        cl_free(tmp.start);
        tmp.start = NULL;
        cl_free(tmp.end);
        tmp.end = NULL;
        cl_free(tmp.target_positions);
        tmp.target_positions = NULL;
        cl_free(tmp.keyword_positions);
        tmp.keyword_positions = NULL;
      }
      else if (k < tmp.tabsize) {

        /* we did not eliminate any duplicates if k==tmp.tabsize.
         * So, in that case, we do not have to bother with reallocs.
         */

        tmp.start = (int *)cl_realloc((char *)tmp.start, sizeof(int) * k);
        if (tmp.end)
          tmp.end = (int *)cl_realloc((char *)tmp.end, sizeof(int) * k);
        if (tmp.target_positions)
          tmp.target_positions = (int *)cl_realloc((char *)tmp.target_positions, sizeof(int) * k);
        if (tmp.keyword_positions)
          tmp.keyword_positions = (int *)cl_realloc((char *)tmp.keyword_positions, sizeof(int) * k);
      }

      cl_free(list1->start);
      cl_free(list1->end);
      cl_free(list1->target_positions);
      cl_free(list1->keyword_positions);

      list1->start = tmp.start;
      tmp.start = NULL;
      list1->end = tmp.end;
      tmp.end = NULL;

      list1->target_positions   = tmp.target_positions;
      tmp.target_positions = NULL;
      list1->keyword_positions  = tmp.keyword_positions;
      tmp.keyword_positions = NULL;

      list1->tabsize = k;
      list1->matches_whole_corpus = 0;
      list1->is_inverted = 0;
    }

    break;

  case Complement:

    /*
     * -------------------- COMPLEMENT
     * in that case. ML2 should be empty. We suppose it is.
     */

    /*
     * what the hell is the complement of a non-initial matchlist?
     * I simply do not know. so do it only for initial ones.
     */

    if (list1->end) {
      fprintf(stderr, "Can't calculate complement for non-initial matchlist.\n");
      return 0;
    }

    /* we could always make the complement by toggling the inversion flag,
     * but we only do that in case the list is inverted, otherwise we would
     * need another function to physically make the complement
     */

    if (list1->is_inverted) {
      list1->is_inverted = 0;
      return 1;
    }

    if (!evalenv) {
      fprintf(stderr, "Can't calculate complement with NULL eval env\n");
      return 0;
    }

    if (!evalenv->query_corpus) {
      fprintf(stderr, "Can't calculate complement with NULL query_corpus.\n");
      return 0;
    }

    if (!access_corpus(evalenv->query_corpus)) {
      fprintf(stderr, "Complement: can't access current corpus.\n");
      return 0;
    }

    /*
     * OK. The tests went by. Now, the size of the new ML is the
     * size of the corpus MINUS the size of the current matchlist.
     */

    if (!(attr = cl_new_attribute(evalenv->query_corpus->corpus, CWB_DEFAULT_ATT_NAME, ATT_POS))) {
      fprintf(stderr, "Complement: can't find %s attribute of current corpus\n", CWB_DEFAULT_ATT_NAME);
      return 0;
    }

    i = cl_max_cpos(attr);
    if (cl_errno != CDA_OK) {
      fprintf(stderr, "Complement: can't get attribute size\n");
      return 0;
    }

    tmp.tabsize = i - list1->tabsize;

    if (tmp.tabsize == 0) {

      /*
       * Best case. Result is empty.
       */

      cl_free(list1->start);
      cl_free(list1->end);
      cl_free(list1->target_positions);
      cl_free(list1->keyword_positions);
      list1->matches_whole_corpus = 0;
      list1->tabsize = 0;
      list1->is_inverted = 0;
    }
    else if (tmp.tabsize == i) {

      /*
       * Worst case.
       * result is a copy of the corpus.
       *
       * TODO: This is not true if we have -1 elements in the source list.
       *
       */

      cl_free(list1->start);
      cl_free(list1->end);
      cl_free(list1->target_positions);
      cl_free(list1->keyword_positions);

      list1->start = (int *)cl_malloc(sizeof(int) * tmp.tabsize);
      list1->tabsize = tmp.tabsize;
      list1->matches_whole_corpus = 1;
      list1->is_inverted = 0;

      for (i = 0; i < tmp.tabsize; i++)
        list1->start[i] = i;
    }
    else {

      /*
       * in between.
       */

      tmp.start = (int *)cl_malloc(sizeof(int) * tmp.tabsize);
      tmp.end = NULL;
      tmp.target_positions = NULL;
      tmp.keyword_positions = NULL;
      tmp.matches_whole_corpus = 0;

      j = 0;                        /* index in source list */
      t = 0;                        /* index in target list */
      for (k = 0; k < i; k++) {
        if ((j >= list1->tabsize) || (k < list1->start[j])) {
          tmp.start[t] = k;
          t++;
        }
        else if (k == list1->start[j]) {
          j++;
        }
        else /* (k > list1->start[j]) */ {
          assert("Error in Complement calculation routine" && 0);
        }
      }
      assert(t == tmp.tabsize);

      cl_free(list1->start);
      cl_free(list1->end);
      cl_free(list1->target_positions);
      cl_free(list1->keyword_positions);

      list1->start = tmp.start;
      tmp.start = NULL;
      list1->end   = tmp.end;
      tmp.end = NULL;
      list1->tabsize = tmp.tabsize;
      list1->matches_whole_corpus = 0;
      list1->is_inverted = 0;
    }


    break;

  case Identity:

    /*
     * -------------------- IDENTITY
     * create a copy of ML2 into ML1
     */

    free_matchlist(list1);

    list1->tabsize = list2->tabsize;
    list1->matches_whole_corpus = list2->matches_whole_corpus;
    list1->is_inverted = list2->is_inverted;

    if (list2->start) {
      list1->start = (int *)cl_malloc(sizeof(int) * list2->tabsize);
      memcpy((char *)list1->start, (char *)list2->start, sizeof(int) * list2->tabsize);
    }

    if (list2->end) {
      list1->end = (int *)cl_malloc(sizeof(int) * list2->tabsize);
      memcpy((char *)list1->end, (char *)list2->end, sizeof(int) * list2->tabsize);
    }

    if (list2->target_positions) {
      list1->target_positions = (int *)cl_malloc(sizeof(int) * list2->tabsize);
      memcpy((char *)list1->target_positions,
             (char *)list2->target_positions, sizeof(int) * list2->tabsize);
    }

    if (list2->keyword_positions) {
      list1->keyword_positions = (int *)cl_malloc(sizeof(int) * list2->tabsize);
      memcpy((char *)list1->keyword_positions,
             (char *)list2->keyword_positions, sizeof(int) * list2->tabsize);
    }

    break;

  case Uniq:

    /*
     * -------------------- UNIQ
     * create a unique version of ML1
     * working destructively on list1
     */

    if (list1->start && (list1->tabsize > 0)) {

      ins = 0;                        /* the insertion point */

      if (list1->end)

        for (i = 0; i < list1->tabsize; i++) {

          if ((ins == 0) ||
              ((list1->start[i] != list1->start[ins-1]) ||
               (list1->end[i] != list1->end[ins-1]))) {

            /* copy the data from the current position
             * down to the insertion point.
             */

            list1->start[ins] = list1->start[i];
            list1->end[ins]   = list1->end[i];
            if (list1->target_positions)
              list1->target_positions[ins]   = list1->target_positions[i];
            if (list1->keyword_positions)
              list1->keyword_positions[ins]  = list1->keyword_positions[i];
            ins++;
          }
        }
      else
        for (i = 0; i < list1->tabsize; i++) {
          if ((ins == 0) || (list1->start[i] != list1->start[ins-1])) {

            /* copy the data from the current position
             * down to the insertion point.
             */

            list1->start[ins] = list1->start[i];
            if (list1->target_positions)
              list1->target_positions[ins]   = list1->target_positions[i];
            if (list1->keyword_positions)
              list1->keyword_positions[ins]  = list1->keyword_positions[i];
            ins++;
          }
        }

      if (ins != list1->tabsize) {

        /*
         * no elements were deleted from the list when ins==tabsize. So
         * we do not have to do anything then.
         * Otherwise, the list was used destructively. Free up used space.
         */

        list1->start = (int *)cl_realloc(list1->start, sizeof(int) * ins);
        if (list1->end)
          list1->end = (int *)cl_realloc(list1->end,   sizeof(int) * ins);
        if (list1->target_positions)
          list1->target_positions = (int *)cl_realloc(list1->target_positions,   sizeof(int) * ins);
        if (list1->keyword_positions)
          list1->keyword_positions = (int *)cl_realloc(list1->keyword_positions, sizeof(int) * ins);
        list1->tabsize = ins;
        list1->matches_whole_corpus = 0;
        list1->is_inverted = 0;
      }
    }

    break;

  case Reduce:

    if ((list1->start) && (list1->tabsize > 0)) {

      ins = 0;

      /* for the sake of efficiency, we distinguish here between
       * initial matchlists and non-initial matchlists. Two almost
       * identical loops are performed, but we do the test for initial
       * mls instead of inside the loop here */

      if (list1->end)

        for (i = 0; i < list1->tabsize; i++) {

          if (list1->start[i] != -1) {

            /* copy the data from the current position
             * down to the insertion point.
             */
            if (i != ins) {
              list1->start[ins] = list1->start[i];
              list1->end[ins]   = list1->end[i];
              if (list1->target_positions)
                list1->target_positions[ins]   = list1->target_positions[i];
              if (list1->keyword_positions)
                list1->keyword_positions[ins]  = list1->keyword_positions[i];
            }
            ins++;
          }
        }
      else
        for (i = 0; i < list1->tabsize; i++) {

          if (list1->start[i] != -1) {

            /* copy the data from the current position
             * down to the insertion point.
             */
            if (i != ins)
              list1->start[ins] = list1->start[i];
            if (list1->target_positions)
              list1->target_positions[ins]   = list1->target_positions[i];
            if (list1->keyword_positions)
              list1->keyword_positions[ins]  = list1->keyword_positions[i];
            ins++;
          }
        }

      if (ins == 0) {

        /*
         * all elements have been deleted. So free the used space.
         */
        cl_free(list1->start);
        cl_free(list1->end);
        cl_free(list1->target_positions);
        cl_free(list1->keyword_positions);
        list1->tabsize = 0;
        list1->matches_whole_corpus = 0;
        list1->is_inverted = 0;
      }
      else if (ins != list1->tabsize) {

        /*
         * no elements were deleted from the list when ins==tabsize. So
         * we do not have to do anything then.
         * Otherwise, the list was used destructively. Free up used space.
         */
        list1->start = (int *)cl_realloc(list1->start, sizeof(int) * ins);
        if (list1->end)
          list1->end = (int *)cl_realloc(list1->end,   sizeof(int) * ins);
        if (list1->target_positions)
          list1->target_positions = (int *)cl_realloc(list1->target_positions, sizeof(int) * ins);
        if (list1->keyword_positions)
          list1->keyword_positions = (int *)cl_realloc(list1->keyword_positions, sizeof(int) * ins);
        list1->tabsize = ins;
        list1->matches_whole_corpus = 0;
        list1->is_inverted = 0;
      }
    }
    break;

  default:
    assert("Illegal operator in Setop" && 0);
    return 0;
    break;
  }

  return 1;
}
