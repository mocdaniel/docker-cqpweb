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
#include <string.h>
#include <math.h>

#include "../cl/cl.h"
#include "../cl/ui-helpers.h"

#include "corpmanag.h"

#include "eval.h"
/* NB  the definition of eval_bool was changed to improve label and target handling
   all instances of eval_bool() in this file now pass NULL, i.e. a dummy reference table,
   as second argument */

#include "options.h"
#include "output.h"
#include "ranges.h"
#include "cqp.h"

#include "targets.h"

SearchStrategy
string_to_strategy(char *s)
{
  if (s == NULL)
    return SearchNone;

  else if (strcasecmp(s, "leftmost") == 0)
    return SearchLeftmost;

  else if (strcasecmp(s, "rightmost") == 0)
    return SearchRightmost;

  else if (strcasecmp(s, "nearest") == 0)
    return SearchNearest;

  else if (strcasecmp(s, "farthest") == 0)
    return SearchFarthest;

  else {
    cqpmessage(Warning, "Illegal search strategy specification ``%s''", s);
    return SearchNone;
  }
}


/* Handling of target, match, keyword. Tue Feb 28 16:02:03 1995 (oli) */

/* target can be any field except NoField (-> CQP dies),
   source can be NoField, which deletes the target field (unless that's match or matchend) */
int
set_target(CorpusList *corp, FieldType t_id, FieldType s_id)
{
  int i;

  if (t_id == s_id) {
    cqpmessage(Error, "Fields are identical.");
    return 0;
  }

  if (corp->size == 0) {
    cqpmessage(Error, "Corpus is empty, nothing to be done.");
    return 0;
  }
  assert(corp->range);

  switch (s_id) {
  case NoField:
    switch (t_id) {
    case MatchField:
    case MatchEndField:
      cqpmessage(Error, "Can't delete match or matchend field from %s\n", corp->name);
      break;

    case TargetField:
      cl_free(corp->targets);
      break;

    case KeywordField:
      cl_free(corp->keywords);
      break;

    case NoField:
    default:
      assert(0 && "Can't be");
      break;
    }

    break;


  case KeywordField:
    if (!corp->keywords)
      cqpmessage(Error, "No keyword defined for %s\n", corp->name);
    else {

      switch (t_id) {
      case MatchField:
        if (!corp->range) {
          cqpmessage(Error, "Internal error: match ranges not allocated. Abort.");
          return 0;
        }

        for (i = 0; i < corp->size; i++) {
          if (corp->keywords[i] >= 0)
            corp->range[i].start = corp->keywords[i];
          if (corp->range[i].start > corp->range[i].end)
            corp->range[i].start = corp->range[i].end;
        }
        break;

      case MatchEndField:
        if (!corp->range) {
          cqpmessage(Error, "Internal error: match ranges not allocated. Abort.");
          return 0;
        }

        for (i = 0; i < corp->size; i++) {
          if (corp->keywords[i] >= 0)
            corp->range[i].end   = corp->keywords[i];
          if (corp->range[i].end < corp->range[i].start)
            corp->range[i].end = corp->range[i].start;
        }
        break;

      case TargetField:
        if (!corp->targets)
          corp->targets = (int *)cl_malloc(corp->size * sizeof(int));
        /* bcopy(corp->keywords, corp->targets, corp->size * sizeof(int)); */
        memcpy(corp->targets, corp->keywords, corp->size * sizeof(int));
        break;

      case NoField:
      default:
        assert(0 && "Can't be");
        break;
      }
    }
    break;


  case TargetField:
    if (!corp->targets)
      cqpmessage(Error, "No collocates / targets defined for %s\n", corp->name);

    else {
      switch (t_id) {
      case MatchField:
        if (!corp->range) {
          cqpmessage(Error, "Internal error: match ranges not allocated. Abort.");
          return 0;
        }
        for (i = 0; i < corp->size; i++) {
          if (corp->targets[i] >= 0)
            corp->range[i].start = corp->targets[i];
          if (corp->range[i].start > corp->range[i].end)
            corp->range[i].start = corp->range[i].end;
        }
        break;

      case MatchEndField:
        if (corp->range == NULL) {
          cqpmessage(Error, "Internal error: match ranges not allocated. Abort.");
          return 0;
        }
        for (i = 0; i < corp->size; i++) {
          if (corp->targets[i] >= 0)
            corp->range[i].end   = corp->targets[i];
          if (corp->range[i].end < corp->range[i].start)
            corp->range[i].end = corp->range[i].start;
        }
        break;

      case KeywordField:
        if (corp->keywords == NULL)
          corp->keywords = (int *)cl_malloc(corp->size * sizeof(int));
        /* bcopy(corp->targets, corp->keywords, corp->size * sizeof(int)); */
        memcpy(corp->keywords, corp->targets, corp->size * sizeof(int));
        break;

      case NoField:
      default:
        assert(0 && "Can't be");
        break;
      }
    }
    break;


  case MatchField:
    switch (t_id) {
    case MatchEndField:
      for (i = 0; i < corp->size; i++)
        corp->range[i].end = corp->range[i].start;
      break;

    case KeywordField:
      if (corp->keywords == NULL)
        corp->keywords = (int *)cl_malloc(corp->size * sizeof(int));
      for (i = 0; i < corp->size; i++)
        corp->keywords[i] = corp->range[i].start;
      break;

    case TargetField:
      if (corp->targets == NULL)
        corp->targets = (int *)cl_malloc(corp->size * sizeof(int));
      for (i = 0; i < corp->size; i++)
        corp->targets[i] = corp->range[i].start;
      break;

    case NoField:
    default:
      assert(0 && "Can't be");
      break;
    }
    break;


  case MatchEndField:
    switch (t_id) {
    case MatchField:
      for (i = 0; i < corp->size; i++)
        corp->range[i].start = corp->range[i].end;
      break;

    case KeywordField:
      if (corp->keywords == NULL)
        corp->keywords = (int *)cl_malloc(corp->size * sizeof(int));
      for (i = 0; i < corp->size; i++)
        corp->keywords[i] = corp->range[i].end;
      break;

    case TargetField:
      if (corp->targets == NULL)
        corp->targets = (int *)cl_malloc(corp->size * sizeof(int));
      for (i = 0; i < corp->size; i++)
        corp->targets[i] = corp->range[i].end;
      break;

    case NoField:
    default:
      assert(0 && "Can't be");
      break;
    }
    break;


  default:
    assert(0 && "Can't be");
    break;
  }

  if (t_id == MatchField || t_id == MatchEndField)
    RangeSort(corp, 0);
    /* re-sort corpus if match regions were modified */

  touch_corpus(corp);

  return 1;
}

int
evaluate_target(CorpusList *corp,          /* the corpus */
                FieldType t_id,            /* the field to set */
                FieldType base,            /* where to start the search */
                int inclusive,             /* including or excluding the base */
                SearchStrategy strategy,   /* disambiguation rule: which item */
                Constrainttree constr,     /* the constraint */
                enum ctxtdir direction,    /* context direction */
                int units,                 /* number of units */
                char *attr_name)           /* name of unit */
{
  Attribute *attr;
  int *table;
  Context context;
  int i, line, lbound, rbound;
  int excl_start, excl_end;
  int nr_evals;
  int percentage, new_percentage; /* for ProgressBar */

  /* ------------------------------------------------------------ */

  assert(corp);

  /* consistency check */
  assert(t_id == TargetField || t_id == KeywordField || t_id == MatchField || t_id == MatchEndField);

  if (!constr) {
    cqpmessage(Error, "Constraint pattern missing in 'set target' command.");
    return 0;
  }

  if (corp->size <= 0) {
    cqpmessage(Error, "Corpus is empty.");
    return 0;
  }

  /* check whether the base field specification is ok */
  switch(base) {

  case MatchField:
  case MatchEndField:
    if (corp->range == NULL) {
      cqpmessage(Error, "No ranges for start of search");
      return 0;
    }
    break;

  case TargetField:
    if (corp->targets == NULL) {
      cqpmessage(Error, "Can't start from base TARGET, none defined");
      return 0;
    }
    break;

  case KeywordField:
    if (corp->keywords == NULL) {
      cqpmessage(Error, "Can't start from base KEYWORD, none defined");
      return 0;
    }
    break;

  default:
    cqpmessage(Error, "Illegal base field (#%d) in 'set target' command.", base);
    return 0;
  }

  if (units <= 0) {
    cqpmessage(Error, "Invalid search space (%d units) in 'set target' command.", units);
    return 0;
  }

  /* THIS SHOULD BE UNNECESSARY, BECAUSE THE GRAMMAR MAKES SURE THE SUBCORPUS EXISTS & IS LOADED */
  /*   if (!access_corpus(corp)) { */
  /*     cqpmessage(Error, "Can't access named query %s.", corp->name); */
  /*     return 0; */
  /*   } */

  context.size = units;
  context.direction = direction;

  if (strcasecmp(attr_name, "word") == 0 || strcasecmp(attr_name, "words") == 0) {
    attr = cl_new_attribute(corp->corpus, CWB_DEFAULT_ATT_NAME, ATT_POS);
    context.space_type = word;
    context.attrib = NULL;
  }
  else {
    attr = cl_new_attribute(corp->corpus, attr_name, ATT_STRUC);
    context.space_type = structure;
    context.attrib = attr;
  }

  if (attr == NULL) {
    cqpmessage(Error, "Can't find attribute %s.%s", corp->mother_name, attr_name);
    return 0;
  }

  if (progress_bar) {
    progress_bar_clear_line();
    progress_bar_message(1, 1, "    preparing");
  }

  table = (int *)cl_calloc(corp->size, sizeof(int));

  EvaluationIsRunning = 1;
  nr_evals = 0;
  percentage = -1;

  for (line = 0; line < corp->size && EvaluationIsRunning; line++) {

    if (progress_bar) {
      new_percentage = floor(0.5 + (100.0 * line) / corp->size);
      if (new_percentage > percentage) {
        percentage = new_percentage;
        progress_bar_percentage(0, 0, percentage);
      }
    }

    table[line] = -1;

    switch(base) {

    case MatchField:
      excl_start = corp->range[line].start;
      excl_end   = corp->range[line].end;

      if ((corp->range[line].start == corp->range[line].end) || inclusive) {

        if (!calculate_ranges(corp,
                              corp->range[line].start, context,
                              &lbound, &rbound)) {

          fprintf(stderr, "Can't compute boundaries for range #%d", line);
          lbound = rbound = -1;
        }
      }
      else {
        int dummy;

        if (!calculate_ranges(corp,
                             corp->range[line].start, context,
                             &lbound, &dummy)) {

          fprintf(stderr, "Can't compute left search space boundary match #%d", line);
          lbound = rbound = -1;
        }
        else if (!calculate_ranges(corp,
                                  corp->range[line].end, context,
                                  &dummy, &rbound)) {

          fprintf(stderr, "Can't compute right search space boundary match #%d", line);
          lbound = rbound = -1;
        }
      }
      break;

    case MatchEndField:
      excl_start = excl_end = corp->range[line].end;

      if (excl_start >= 0) {
        if (!calculate_ranges(corp,
                              corp->range[line].end, context,
                              &lbound, &rbound)) {

          fprintf(stderr, "Can't compute search space boundaries for match #%d", line);
          lbound = rbound = -1;
        }
      }
      else
        lbound = rbound = -1;
      break;

    case TargetField:
      excl_start = excl_end = corp->targets[line];

      if (excl_start >= 0) {
        if (!calculate_ranges(corp,
                              corp->targets[line], context,
                              &lbound, &rbound)) {

          fprintf(stderr, "Can't compute search space boundaries for match #%d", line);
          lbound = rbound = -1;
        }
      }
      else
        lbound = rbound = -1;
      break;

    case KeywordField:
      excl_start = excl_end = corp->keywords[line];

      if (excl_start >= 0) {
        if (!calculate_ranges(corp,
                              corp->keywords[line], context,
                              &lbound, &rbound)) {

          fprintf(stderr, "Can't compute search space boundaries for match #%d", line);
          lbound = rbound = -1;
        }
      }
      else
        lbound = rbound = -1;
      break;

    default:
      assert(0 && "Can't be");
      return 0;
    }

    if (lbound >= 0 && rbound >= 0) {
      int dist, maxdist;

      if (direction == ctxtdir_left) {
        rbound = excl_start;
        if (strategy == SearchNearest)
          strategy = SearchRightmost;
        else if (strategy == SearchFarthest)
          strategy = SearchLeftmost;
      }
      else if (direction == ctxtdir_right) {
        lbound = excl_start;
        if (strategy == SearchNearest)
          strategy = SearchLeftmost;
        else if (strategy == SearchFarthest)
          strategy = SearchRightmost;
      }

      switch (strategy) {

      case SearchFarthest:
        maxdist = MAX(excl_start - lbound, rbound - excl_start);
        assert(maxdist >= 0);

        for (dist = maxdist; dist >= 0; dist--) {

          i = excl_start - dist;
          if (i >= lbound && (inclusive || i < excl_start) )
            if (eval_bool(constr, NULL, i)) {
              table[line] = i;
              break;
            }

          i = excl_start + dist;
          if (i <= rbound && (inclusive || i > excl_end))
            if (eval_bool(constr, NULL, i)) {
              table[line] = i;
              break;
            }

          if (++nr_evals == 1000) {
            CheckForInterrupts();
            nr_evals = 0;
          }
        }
        break;

      case SearchNearest:
        maxdist = MAX(excl_start - lbound, rbound - excl_start);
        assert(maxdist >= 0);

        for (dist = 0; dist <= maxdist; dist++) {

          i = excl_start - dist;

          if (i >= lbound && (inclusive || (i < excl_start)))
            if (eval_bool(constr, NULL, i)) {
              table[line] = i;
              break;
            }

          i = excl_start + dist;

          if (i <= rbound && (inclusive || (i > excl_end)))
            if (eval_bool(constr, NULL, i)) {
              table[line] = i;
              break;
            }

          if (++nr_evals == 1000) {
            CheckForInterrupts();
            nr_evals = 0;
          }
        }
        break;

      case SearchLeftmost:
        for (i = lbound; i <= rbound; i++)
          if (inclusive || (i < excl_start) || (i > excl_end)) {
            if (eval_bool(constr, NULL, i)) {
              table[line] = i;
              break;
            }

            if (++nr_evals == 1000) {
              CheckForInterrupts();
              nr_evals = 0;
            }
          }
        break;

      case SearchRightmost:
        for (i = rbound; i >= lbound; i--)
          if (inclusive || (i < excl_start) || (i > excl_end)) {
            if (eval_bool(constr, NULL, i)) {
              table[line] = i;
              break;
            }

            if (++nr_evals == 1000) {
              CheckForInterrupts();
              nr_evals = 0;
            }
          }
        break;

      default:
        break;
      }
    }
  }

  if (progress_bar)
    progress_bar_message(1, 1, "  cleaning up");

  switch (t_id) {
  case MatchField:
    for (i = 0; i < corp->size; i++) {
      if (table[i] >= 0)
        corp->range[i].start = table[i];
      if (corp->range[i].start > corp->range[i].end)
        corp->range[i].start = corp->range[i].end;
    }
    cl_free(table);
    break;

  case MatchEndField:
    for (i = 0; i < corp->size; i++) {
      if (table[i] >= 0)
        corp->range[i].end = table[i];
      if (corp->range[i].end < corp->range[i].start)
        corp->range[i].end = corp->range[i].start;
    }
    cl_free(table);
    break;

  case TargetField:
    cl_free(corp->targets);
    corp->targets = table;
    break;

  case KeywordField:
    cl_free(corp->keywords);
    corp->keywords = table;
    break;

  default:
    assert(0 && "Can't be");
    break;
  }

  if (progress_bar)
    progress_bar_clear_line();

  /* re-sort corpus if match regions were modified */
  if (t_id == MatchField || t_id == MatchEndField)
    RangeSort(corp, 0);

  touch_corpus(corp);

  if (!EvaluationIsRunning) {
    cqpmessage(Warning, "Evaluation interruted: results may be incomplete.");
    if (which_app == cqp)
      install_signal_handler();
  }
  EvaluationIsRunning = 0;

  return 1;
}

/**
 * @param cl            the corpus
 * @param the_field     the field to scan
 * @param constr
 * @return
 */
int
evaluate_subset(CorpusList *cl, FieldType the_field, Constrainttree constr)
{
  int line, position;
  int percentage, new_percentage; /* for ProgressBar */

  assert(cl && constr);
  assert(cl->type == SUB || cl->type == TEMP);

  percentage = -1;

  for (EvaluationIsRunning = 1, line = 0; line < cl->size && EvaluationIsRunning; line++) {

    if (progress_bar) {
      new_percentage = floor(0.5 + (100.0 * line) / cl->size);
      if (new_percentage > percentage) {
        percentage = new_percentage;
        progress_bar_percentage(0, 0, percentage);
      }
    }

    switch (the_field) {

    case MatchField:
      position = cl->range[line].start;
      break;

    case MatchEndField:
      position = cl->range[line].end;
      break;

    case KeywordField:
      assert(cl->keywords);
      position = cl->keywords[line];
      break;

    case TargetField:
      assert(cl->targets);
      position = cl->targets[line];
      break;

    case NoField:
    default:
      position = -1;
      break;
    }

    if (position < 0 || (!eval_bool(constr, NULL, position))) {
      cl->range[line].start = -1;
      cl->range[line].end   = -1;
    }
  }

  /* if interrupted, delete part of temporary query result which hasn't been filtered;
     so that the result is incomplete but at least contains only correct matches */
  while (line < cl->size) {
    cl->range[line].start = -1;
    cl->range[line].end   = -1;
    line++;
  }

  if (!EvaluationIsRunning) {
    cqpmessage(Warning, "Evaluation interruted: results may be incomplete.");
    if (which_app == cqp)
      install_signal_handler();
  }
  EvaluationIsRunning = 0;

  if (progress_bar)
    progress_bar_message(0, 0, "  cleaning up");

  apply_range_set_operation(cl, RReduce, NULL, NULL);

  return 1;
}




