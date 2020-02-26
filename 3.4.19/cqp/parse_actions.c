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

#include "parse_actions.h"

#include <stdlib.h>
#include <sys/time.h>
#ifndef __MINGW__
#include <sys/resource.h>
#else
#include <windows.h>
#endif
#include <stdio.h>
#include <string.h>
#include <assert.h>
#include <stdarg.h>
#include <unistd.h>

#include "../cl/globals.h"
#include "../cl/special-chars.h"
#include "../cl/attributes.h"
#include "../cl/ui-helpers.h"

#include "cqp.h"
#include "options.h"
#include "ranges.h"
#include "symtab.h"
#include "treemacros.h"
#include "tree.h"
#include "eval.h"
#include "corpmanag.h"
#include "regex2dfa.h"
#include "builtins.h"
#include "groups.h"
#include "targets.h"
#include "attlist.h"
#include "concordance.h"
#include "output.h"
#include "print-modes.h"
#include "variables.h"

/* ======================================== GLOBAL PARSER VARIABLES */

/**
 * TODO would be very useful to have a desc for this
 *
 * A boolean; seems to be some kind of error-indicator (set to true
 * if a query worked, false if it didn't, things like that).
 *
 * When it is false, many actions simply have no effect, because they are
 * set to only actually do anything "if (generate_code)".
 *
 * Some functions will set it to 0 when an action works to block later actions.
 *
 * In some cases, setting this to  0 is linked with "YYABORT" in comments.
 */
int generate_code;
int within_gc;                   /**< TODO would be very useful to have a desc for this ;
                                      seems to be about whether or not we are within a global constraint */

CYCtype last_cyc;                /**< type of last corpus yielding command */

/** The corpus (or subcorpus) which is "active" in the sense that the query will be executed within it. */
CorpusList *query_corpus = NULL;
/** Used for preserving former values of query_corpus (@see query_corpus), so it can be reset to a former value). */
CorpusList *old_query_corpus = NULL;

int catch_unknown_ids = 0;


/**
 * This is used by the parser in response to CQP's "expand" operator,
 * which incorporates context around the query hit into the match itself.
 * Functions involved in carrying this out utilise info stored here by the parser.
 */
Context expansion;
/**
 * Buffer for storing regex strings. As it says on the tin.
 *
 * TODO Doesn't seem currently to be in use anywhere, except in one func which itself is not used.
 */
char regex_string[CL_MAX_LINE_LENGTH];
/** index into the regex string buffer, storing a current position. @ see regex_string_pos */
int regex_string_pos;
/** length of search string: is written to by evaltree2searchstr() but then seems never to be read.  TODO . */
int sslen;

/* ======================================== predeclared functions */

static char *mval_string_conversion(char *s);



/* ======================================== PARSER ACTIONS */

/* ======================================== Syntax rule: line -> command */

/**
 * Add a line of CQP input to the history file.
 *
 * Supports parser rule: line -> command
 *
 * The line that is added comes from QueryBuffer; the file it is
 * written to is that named in cqp_history_file.
 *
 * @see QueryBuffer
 * @see cqp_history_file
 */
void
addHistoryLine(void)
{
  FILE *dst;
  if (cqp_history_file != NULL    &&
      cqp_history_file[0] != '\0' &&
      write_history_file          &&
      !silent                     &&
      !reading_cqprc) {
    if (QueryBuffer[0] != '\0') {
      if (!(dst = cl_open_stream(cqp_history_file, CL_STREAM_APPEND, CL_STREAM_FILE)))
        cqpmessage(Error, "Can't open history file %s\n", cqp_history_file);
      else {
        fputs(QueryBuffer, dst);
        fputc('\n', dst);
        cl_close_stream(dst);
      }
    }
  }
}

/**
 * Empties the query buffer and sets to 0 the pointer.
 *
 * Supports parser rule: line -> command
 *
 * @see QueryBuffer
 * @see QueryBufferP
 */
void
resetQueryBuffer(void)
{
  /*   fprintf(stderr, "+ Resetting Query Buffer\n"); */
  QueryBufferP = 0;
  QueryBuffer[0] = '\0';
  QueryBufferOverflow = 0;
}

void
RaiseError(void)
{
  generate_code = 0;
  resetQueryBuffer();
}

void
prepare_parse(void)
{
  if (old_query_corpus != NULL) {
    query_corpus = old_query_corpus;
    old_query_corpus = NULL;
    cqpmessage(Warning, "Query corpus reset");
  }
  generate_code = 1;
}

/* ======================================== Syntax rule: command -> CorpusCommand ';' */

CorpusList *
in_CorpusCommand(char *id, CorpusList *cl)
{
  if (cl == NULL)
    return NULL;
  else if (is_qualified(id)) {
    cqpmessage(Warning, "You can't use a qualified corpus name on the\nleft hand side of an assignment (result in \"Last\")");
    return NULL;
  }
  else if (cl->type == SYSTEM) {
    cqpmessage(Warning, "System corpora can't be duplicated.");
    return NULL;
  }
  else {
    duplicate_corpus(cl, id, True);
    last_cyc = Assignment;
    return current_corpus;
  }
}

/**
 * Set the current corpus and
 * do the output if it was a query
 */
void
after_CorpusCommand(CorpusList *cl)
{
#if defined(DEBUG_QB)
  if (QueryBufferOverflow)
    fprintf(stderr, "+ Query Buffer overflow.\n");
  else if (QueryBuffer[0] == '\0')
    fprintf(stderr, "+ Query Buffer is empty.\n");
  else
    fprintf(stderr, "Query buffer: >>%s<<\n", QueryBuffer);
#endif

  switch (last_cyc) {

  case Query:
    if (cl) {
      if (auto_subquery)
        set_current_corpus(cl, 0);
      if (autoshow && (cl->size > 0))
        catalog_corpus(cl, NULL, 0, -1, GlobalPrintMode);
      else if (!silent)
        printf("%d matches.%s\n", cl->size, (cl->size > 0 ? " Use 'cat' to show." : ""));
    }
    query_corpus = NULL;
    break;

  case Activation:
    if (cl)
      set_current_corpus(cl, 0);
    break;

  case SetOperation:
    if (cl) {
      if (auto_subquery)
        set_current_corpus(cl, 0);
      if (autoshow && cl->size > 0)
        catalog_corpus(cl, NULL, 0, -1, GlobalPrintMode);
      else if (!silent)
        printf("%d matches.%s\n", cl->size, (cl->size > 0 ? " Use 'cat' to show." : "") );
    }
    break;

  default:
    break;
  }

  if (auto_save && cl && cl->type == SUB && !cl->saved)
    save_subcorpus(cl, NULL);

  LastExpression = last_cyc;
  last_cyc = NoExpression;
}

/* ======================================== UnnamedCorpusCommand -> CYCommand ReStructure */

/**
 * This function is called after an UnnamedCorpusCommand rule is parsed.
 *
 * Seems to be a tidying-up function.
 *
 * @param cl  The result of the corpus-yielding command (first component of this syntax rule).
 * @return    Modified valuse of cl. May be NULL.
 */
CorpusList *
in_UnnamedCorpusCommand(CorpusList *cl)
{
  CorpusList *res = NULL;

  cqpmessage(Message, "Command: UnnamedCorpusCommand");

  if (cl) {
    switch (last_cyc) {

    case Query:
      /* the last command was a query */
      assert(cl->type == TEMP); /* should be true since the last command was a query! */

      if (generate_code) {
        expand_dataspace(cl);

        do_timing("Query result computed"); /* timer must be started by each query execution command */

        /* set the "corpus" created by the query to be the default "Last" subcorpus. */
        res = assign_temp_to_sub(cl, "Last");
      }
      else
        res = NULL;

      drop_temp_corpora();
      break;

    case Activation:
      /* Last command was not a query, that is, it was a corpus activation.
       * We only have to copy if we want to expand the beast.
       */
      if (expansion.size > 0) {
        if (cl->type == SYSTEM) {
          cqpmessage(Warning, "System corpora can't be expanded (only subcorpora)");
          res = cl;
        }
        else {
          res = make_temp_corpus(cl, "RHS");
          expand_dataspace(res);
          res = assign_temp_to_sub(res, "Last");
        }
      }
      else
        /* a simple activation without restructuring */
        res = cl;
      break;

    case SetOperation:
      assert(cl->type == TEMP);

      expand_dataspace(cl);

      res = assign_temp_to_sub(cl, "Last");

      drop_temp_corpora();
      break;

    default:
      cqpmessage(Warning, "Unknown CYC type: %d\n", last_cyc);
      res = NULL;
      break;
    }
  }

  free_environments();
  return res;
}

/* ======================================== Corpus Yielding Commands */

CorpusList *
ActivateCorpus(CorpusList *cl)
{
  cqpmessage(Message, "CorpusActivate: %s", cl);

  if (inhibit_activation) {
    fprintf(stderr, "Activation prohibited\n");
    exit(1); /* hard way! */
  }
  else {
    query_corpus = cl;

    if (query_corpus) {
      if (!next_environment()) {
        cqpmessage(Error, "Can't allocate another evaluation environment");
        generate_code = 0;
        query_corpus = NULL;
      }
      else
        CurEnv->query_corpus = query_corpus;
    }
    last_cyc = Activation;
  }
  return cl;
}

CorpusList *
after_CorpusSetExpr(CorpusList *cl)
{
  last_cyc = SetOperation;

  if (!next_environment()) {
    cqpmessage(Error, "Can't allocate another evaluation environment");
    generate_code = 0;
    CurEnv->query_corpus = NULL;
  }
  else
    CurEnv->query_corpus = cl;

  return cl;
}

/**
 * This function sets things up to run a query.
 *
 * It is called as an "action" before any detected Query in the parser.
 *
 * [AH 2010/8/2: I have added the code checking input character encoding.
 * Anything that is not part of a query should be plain ASCII - if not,
 * then the lexer/parser should pick it up as bad. Filenames, etc. are
 * obvious exceptions - but we can't check the encoding of those, because
 * there's no guarantee it will be the same as that of the corpus, which
 * is the only thing whose encoding we know. So it's up to the user to
 * type filenames in an encoding their OS will accept!
 * Canonicalisation is done within the CL_Regex, not here.]
 */
void
prepare_Query()
{
  generate_code = 1;

  /* check whether we've got a corpus loaded */
  if (current_corpus == NULL) {
    cqpmessage(Error, "No corpus activated");
    generate_code = 0;
  }
  else if (!access_corpus(current_corpus)) {
    cqpmessage(Error, "Current corpus can't be accessed");
    generate_code = 0;
  }

  if (generate_code) {
    assert(current_corpus->corpus != NULL);
    assert(searchstr == NULL);
    assert(eep == -1);

    /* validate character encoding according to that corpus, now we know it's loaded */
    if (!cl_string_validate_encoding(QueryBuffer, current_corpus->corpus->charset, 0)) {
      cqpmessage(Error, "Query includes a character or character sequence that is invalid\n"
                        "in the encoding specified for this corpus");
      generate_code = 0;
    }

    if (!next_environment()) {
      cqpmessage(Error, "Can't allocate another evaluation environment");
      generate_code = 0;
      query_corpus = NULL;
    }
    else {
      int before, after;

      assert(eep == 0);
      assert(CurEnv == &(Environment[0]));

      query_corpus = make_temp_corpus(current_corpus, "RHS");
      CurEnv->query_corpus = query_corpus;

      /* subqueries don't work properly if the mother corpus has overlapping regions -> delete and warn */
      before = query_corpus->size;
      apply_range_set_operation(query_corpus, RNonOverlapping, NULL, NULL);
      after = query_corpus->size;
      if (after < before)
        cqpmessage(Warning,
                   "Overlapping matches in %s:%s deleted for subquery execution.",
                   query_corpus->mother_name, query_corpus->name);
    }
  }
  within_gc = 0;
}

CorpusList *
after_Query(CorpusList *cl)
{
  last_cyc = Query;

  within_gc = 0;

  if (generate_code) {
    if (cl) {
      cl_free(cl->query_text);
      cl_free(cl->query_corpus);

      if (query_corpus)
        cl->query_corpus = cl_strdup(query_corpus->name);

      /* this is probably where we want to auto-execute the reduce to maximal stuff */

      if (QueryBuffer[0] != '\0' &&
          QueryBufferP > 0 &&
          !QueryBufferOverflow) {
        cl->query_text = cl_strdup(QueryBuffer);
      }
    }
    return cl;
  }
  else
    return NULL;
}

/* ======================================== ``interactive'' commands */

void
do_cat(CorpusList *cl, struct Redir *r, int first, int last)
{
  if (cl) {
    cqpmessage(Message, "cat command: (%s)", cl->name);
    catalog_corpus(cl, r, first, last, GlobalPrintMode);
  }
}

void
do_echo(char *s, struct Redir *rd)
{
  char *r, *w;
  if (!open_stream(rd, unknown_charset)) {
    cqpmessage(Error, "Can't redirect output to file or pipe\n");
    return;
  }
  /* make copy of s to interpret \t, \r and \n escapes */
  s = cl_strdup(s);
  r = w = s;
  while (*r) {
    if (*r == '\\' && *(r + 1)) {
      if (*(r + 1) == 't') {
        *w++ = '\t';
        r += 2;
      }
      else if (*(r + 1) == 'r') {
        *w++ = '\r';
        r += 2;
      }
      else if (*(r + 1) == 'n') {
        *w++ = '\n';
        r += 2;
      }
      else {
        *w++ = *r++; /* pass through all other escaped symbols */
        *w++ = *r++;
      }
    }
    else {
      *w++ = *r++;
    }
  }
  *w = '\0'; /* terminate modified string */

  fprintf(rd->stream, "%s", s);
  cl_free(s);

  close_stream(rd);
}

void
do_save(CorpusList *cl, struct Redir *r)
{
  if (cl) {
    if (!data_directory)
      cqpmessage(Warning, "Can't save subcorpus ``%s'' (you haven't set the DataDirectory option)", cl->name);
    else {
      cqpmessage(Message, "save command: %s to %s", cl->name, r->name);
      save_subcorpus(cl, r->name);
    }
  }
}

/* ======================================== show attribute */

void
do_attribute_show(char *name, int status)
{
  AttributeInfo *ai;

  if (strcasecmp(name, "cpos") == 0 &&
      current_corpus &&
      current_corpus->corpus &&
      !cl_new_attribute(current_corpus->corpus, name, ATT_STRUC)) {
    CD.print_cpos = status;
  }
  else if ((strcasecmp(name, "targets") == 0) &&
           current_corpus &&
           current_corpus->corpus &&
           !cl_new_attribute(current_corpus->corpus, name, ATT_STRUC)) {
    show_targets = status;
  }
  else if (CD.attributes || CD.alignedCorpora) {
    if (name) {
      if ((ai = FindInAL(CD.attributes, name)))
        ai->status = status;
      else if ((ai = FindInAL(CD.alignedCorpora, name)))
        ai->status = status;
      else if ((ai = FindInAL(CD.strucAttributes, name)))
        ai->status = status;
      else {
        cqpmessage(Error, "No such attribute: %s", name);
        generate_code = 0;
      }
    }
    else {
      for (ai = CD.attributes->list; ai; ai = ai->next)
        ai->status = status;

      if (!status)
        if (NULL != (ai = FindInAL(CD.attributes, CWB_DEFAULT_ATT_NAME)))
          ai->status = 1;
    }
  }
}

CorpusList *
do_translate(CorpusList *source, char *target_name)
{
  CorpusList *res, *target;
  Attribute *alignment;
  int i, n, bead;
  int s1, s2, t1, t2;

  if (generate_code) {
    assert(source != NULL);

    if (!(target = findcorpus(target_name, SYSTEM, 0))) {
      cqpmessage(Warning, "System corpus ``%s'' doesn't exist", target_name);
      generate_code = 0;
      return NULL;
    }

    if (!(alignment = cl_new_attribute(source->corpus, target->corpus->registry_name, ATT_ALIGN))) {
      cqpmessage(Error, "Corpus ``%s'' is not aligned to corpus ``%s''", source->mother_name, target->mother_name);
      generate_code = 0;
      return NULL;
    }

    /* allocate temporary NQR for the translated ranges */
    res = make_temp_corpus(target, "RHS");
    res->size = n = source->size;
    cl_free(res->range);     /* allocate ranges for mapped regions */
    res->range = (Range *)cl_calloc(n, sizeof(Range));
    cl_free(res->targets);   /* make sure there are no spurious target / keywords vectors */
    cl_free(res->keywords);

    /* translate each matching range into target bead */
    for (i = 0; i < n; i++) {
      bead = cl_cpos2alg(alignment, source->range[i].start);
      if (bead < 0 ||
          !cl_alg2cpos(alignment, bead, &s1, &s2, &t1, &t2) ||
          !CL_ALL_OK()) {
        res->range[i].start = -1;
      }
      else {
        res->range[i].start = t1;
        res->range[i].end = t2;
      }
    }

    /* remove unaligned items (but not duplicates) */
    apply_range_set_operation(res, RReduce, NULL, NULL);

    /* make sure target ranges are sorted (preserving original order with sortidx) */
    RangeSort(res, 1);

    return res;
  }
  else
    return NULL;
}


CorpusList *
do_setop(RangeSetOp op, CorpusList *c1, CorpusList *c2)
{
  CorpusList *res;

  res = NULL;

  cqpmessage(Message, "Set Expr");

  if (c1 && c2) {
    if (c1->corpus != c2->corpus)
      cqpmessage(Warning,
                 "Original corpora of %s (%s) and %s (%s) differ.\n",
                 c1->name, c1->mother_name, c2->name, c2->mother_name);
    else {
      res = make_temp_corpus(c1, "RHS");
      apply_range_set_operation(res, op, c2, NULL);
    }
  }
  return res;
}

void
prepare_do_subset(CorpusList *cl, FieldType field)
{
  int field_exists = 0;

  if (cl == NULL || cl->type != SUB) {
    cqpmessage(Error, "The subset operator can only be applied to subcorpora.");
    generate_code = 0;
    return;
  }
  else if (cl->size == 0) {
    cqpmessage(Warning, "The subcorpus is empty; the subset operation therefore has no effect.");
    return;
  }

  switch (field) {

  case MatchField:
  case MatchEndField:
    field_exists = cl->size > 0;
    break;

  case KeywordField:
    field_exists = cl->size > 0 && cl->keywords != NULL;
    break;

  case TargetField:
    field_exists = cl->size > 0 && cl->targets != NULL;
    break;

  default:
    field_exists = 0;
    break;
  }

  if (!field_exists) {
    cqpmessage(Error, "The <%s> anchor is not defined for this subcorpus.", field_type_to_name(field));
    generate_code = 0;
    return;
  }

  if (progress_bar) {
    progress_bar_clear_line();
    progress_bar_message(1, 1, "    preparing");
  }

  /* now we can finally get going */
  query_corpus = make_temp_corpus(cl, "RHS");
  generate_code = 1;
}

CorpusList *
do_subset(FieldType field, Constrainttree boolt)
{
  if (generate_code)
    evaluate_subset(query_corpus, field, boolt);

  if (boolt)
    free_booltree(boolt);

  if (progress_bar)
    progress_bar_clear_line();

  if (generate_code)
    return query_corpus;
  else
    return NULL;
}

void
do_set_target(CorpusList *cl, FieldType goal, FieldType source)
{
  if (cl != NULL && goal != NoField)
    set_target(cl, goal, source);
}

void
do_set_complex_target(CorpusList *cl,
                      FieldType field_to_set,
                      SearchStrategy strategy,
                      Constrainttree boolt,
                      enum ctxtdir direction,
                      int number,
                      char *id,
                      FieldType field,
                      int inclusive)
{
  if (generate_code && cl != NULL) {
    /* query_corpus has been saved in old_query_corpus and set to cl by parser */
    evaluate_target(cl,
                    field_to_set,
                    field,
                    inclusive,
                    strategy,
                    boolt, direction, number, id);
    query_corpus = old_query_corpus; /* reset query_corpus to previous value */
    old_query_corpus = NULL;
  }

  /* clean up */
  if (boolt)
    free_booltree(boolt);
}


/**
 * Puts the program to sleep.
 *
 * A wrapper round the standard sleep() function (or Sleep() in Windows).
 *
 * @param duration  How many seconds to sleep for.
 */
void
do_sleep(int duration)
{
  if (duration > 0) {
#ifndef __MINGW__
    sleep(duration);       /* sleep in number of seconds (normal POSIX function) */
#else
    Sleep(duration*1000);  /* sleep in number of milliseconds (Windows "equivalent") */
#endif
  }
}

/**
 * Execute the commands contained within a specified text file.
 */
void
do_exec(char *fname)
{
  FILE *src;

  cqpmessage(Message, "exec cmd: %s\n", fname);

  if (1) {
    cqpmessage(Error, "The exec statement is not yet supported");
    generate_code = 0;
  }
  else {
    if (NULL != (src = cl_open_stream(fname, CL_STREAM_READ, CL_STREAM_MAGIC_NOPIPE))) {
      /* cease reading exec'ed file on parse error within file */
      if (!cqp_parse_file(src, 1)) {
        cqpmessage(Error, "Errors in exec'ed file %s\n", fname);
        generate_code = 0;
      }
    }
    else {
      cqpmessage(Error, "File %s is not accessible and has not been executed.\n", fname);
      generate_code = 0;
    }
  }
}

void
do_delete_lines_num(CorpusList *cl, int start, int end)
{
  if (cl == NULL || cl->type != SUB) {
    cqpmessage(Error, "The delete operator can only be applied to subcorpora.");
    generate_code = 0;
    return;
  }
  else if (start <= end) {
    Bitfield lines = create_bitfield(cl->size);
    assert(lines);

    for ( ; start <= end && start < cl->size; start++)
      set_bit(lines, start);

    if (nr_bits_set(lines) > 0)
      delete_intervals(cl, lines, SELECTED_LINES);

    destroy_bitfield(&lines);
  }
}

void
do_delete_lines(CorpusList *cl, FieldType f, int mode)
{
  if (cl == NULL || cl->type != SUB) {
    cqpmessage(Error, "The delete operator can only be applied to subcorpora.");
    generate_code = 0;
    return;
  }
  else if (f != NoField) {
    int *positions = NULL;

    switch (f) {

    case MatchField:
    case MatchEndField:
      cqpmessage(Warning, "\"delete ... with[out] match/matchend\" does not make sense.");
      break;

    case KeywordField:
      if ((positions = cl->keywords) == NULL)
        cqpmessage(Warning, "No keywords set for this subcorpus");
      break;

    case TargetField:
      if ((positions = cl->targets) == NULL)
        cqpmessage(Warning, "No collocates set for this subcorpus");
      break;

    default:
      assert(0 && "Can't (well, shouldn't) be.");
      break;
    }

    if (positions) {
      int i;
      Bitfield lines = create_bitfield(cl->size);
      assert(lines);

      for (i = 0; i < cl->size; i++)
        if (positions[i] >= 0)
          set_bit(lines, i);

      delete_intervals(cl, lines, mode);
      destroy_bitfield(&lines);
    }
  }
}

void
do_reduce(CorpusList *cl, int number, int percent)
{
  if (cl == NULL || cl->type != SUB) {
    cqpmessage(Error, "The reduce operator can only be applied to named query results.");
    generate_code = 0;
    return;
  }
  else if (cl->size == 0) {
    cqpmessage(Warning, "Zero matches - no reduction applicable\n");
    return;
  }

  if (percent) {
    if (number <= 0 || number >= 100) {
      cqpmessage(Error, "The \"reduce to n percent\" operation\nrequires a number between 0 and 100 (exclusive)");
      generate_code = 0;
      return;
    }
    number = (cl->size * number) / 100;
  }
  else {
    if (number <= 0 || number >= cl->size)
      /* nothing to be done -- don't squeal (a general "reduce Last to 50" without checking size is quite useful) */
      /*       cqpmessage(Warning, "The \"reduce to n lines\" operation\nrequires a number between 0 and the subcorpus' size (exclusive)"); */
      return;
  }

  {
    unsigned int to_select, size;
    Bitfield lines = create_bitfield(cl->size);
    assert(lines);

    /* the algorithm below uses a continuously updated selection probability
       in order to select a random sample of size <number> without replacement */
    size = cl->size;                /* how many matches remain to be processed  */
    to_select = number;         /* how many of these should be selected */
    while (size > 0) {
      /* select current line with this probability */
      double prob = ((double) to_select) / ((double) size);
      if (cl_runif() <= prob) {
        set_bit(lines, size-1); /* current line number is size-1 */
        to_select--;
      }
      size--;
    }

    delete_intervals(cl, lines, UNSELECTED_LINES);
    destroy_bitfield(&lines);
  }
}

void
do_cut(CorpusList *cl, int first, int last) {
  int n_matches, i;

  if (cl == NULL || cl->type != SUB) {
    cqpmessage(Error, "The cut operator can only be applied to named query results.");
    generate_code = 0;
    return;
  }
  n_matches = cl->size;
  if (n_matches == 0) {
    cqpmessage(Warning, "Named query result is empty - can't cut\n");
    return;
  }

  assert(first >= 0); /* first < 0 is now disallowed by the parser */
  if (last >= n_matches)
    last = n_matches - 1; /* must be >= 0 because n_matches > 1 has been checked */
  if (first >= n_matches)
    first = n_matches;    /* so the loop below cannot overflow */

  if (last < first) {
    cqpmessage(Warning, "Cut operator applied with empty range %d .. %d, so result is empty.", first, last);
    first = last = n_matches;        /* delete all matches, ensuring that index does not run out of bounds */
  }

  /* CQP Tutorial documents cut to respect sort order of NQR (Sec. 3.6: Random subsets)
   * Since it is considered authoritative documentation on CQP, the implementation here has been adjusted in CQP v3.4.15.
   */
  if (cl->sortidx) {
    for (i = 0; i < first; i++)  {
      int j = cl->sortidx[i];
      cl->range[j].start = -1;        /* delete all matches before <first> according to current sort order */
      cl->range[j].end = -1;
    }
    for (i = last + 1; i < n_matches; i++)  {
      int j = cl->sortidx[i];
      cl->range[j].start = -1;        /* delete all matches after <last> according to current sort order */
      cl->range[j].end = -1;
    }
  }
  else {
    for (i = 0; i < first; i++)  {
      cl->range[i].start = -1;        /* delete all matches before <first> */
      cl->range[i].end = -1;
    }
    for (i = last + 1; i < n_matches; i++)  {
      cl->range[i].start = -1;        /* delete all matches after <last> */
      cl->range[i].end = -1;
    }
  }

  apply_range_set_operation(cl, RReduce, NULL, NULL); /* remove matches marked for deletion */
  touch_corpus(cl);
}

void
do_info(CorpusList *cl)
{
  if (cl)
    corpus_info(cl);
}

void
do_group(CorpusList *cl,
         FieldType target, int target_offset, char *t_att,
         FieldType source, int source_offset, char *s_att,
         int cut, int expand, int is_grouped, struct Redir *redir)
{
  Group *group;

  do_start_timer();
  group = compute_grouping(cl, source, source_offset, s_att, target, target_offset, t_att, cut, is_grouped);
  do_timing("Grouping computed");
  if (group) {
    print_group(group, expand, redir);
    free_group(&group);
  }
}

/** Like do_group, but with no source */
void
do_group2(CorpusList *cl,
          FieldType target, int target_offset, char *t_att,
          int cut, int expand, struct Redir *r)
{
  Group *group;

  do_start_timer();
  group = compute_grouping(cl, NoField, 0, NULL, target, target_offset, t_att, cut, 0);
  do_timing("Grouping computed");
  if (group) {
    print_group(group, expand, r);
    free_group(&group);
  }
}

CorpusList *
do_StandardQuery(int cut_value, int keep_flag, char *modifier)
{
  CorpusList *res;
  res = NULL;

  cqpmessage(Message, "Query");

  /* embedded modifier (?<modifier>) at start of query */
  if (modifier != NULL) {
    /* currently, modifiers can only be used to set the matching strategy */
    int code = find_matching_strategy(modifier);
    if (code < 0) {
      cqpmessage(Error, "embedded modifier (?%s) not recognized;\n"
                 "\tuse (?longest), (?shortest), (?standard) or (?traditional) to set matching strategy temporarily",
                 modifier);
      generate_code = 0;
    }
    else
      Environment[0].matching_strategy = code;
    cl_free(modifier); /* allocated by lexer */
  }

  if (parseonly || (generate_code == 0))
    res = NULL;
  else if (Environment[0].evaltree != NULL) {
    debug_output();
    do_start_timer();

    if (keep_flag == 1 && current_corpus->type != SUB) {
      cqpmessage(Warning, "``Keep Ranges'' only allowed when querying subcorpora (ignored)");
      keep_flag = 0;
    }

    cqp_run_query(cut_value, keep_flag);

    res = Environment[0].query_corpus;

    /* the new matching strategies require post-processing of the query result */
    switch (Environment[0].matching_strategy) {

    case shortest_match:
      apply_range_set_operation(res, RMinimalMatches, NULL, NULL);         /* select shortest from several nested matches */
      break;

    case standard_match:
      apply_range_set_operation(res, RLeftMaximalMatches, NULL, NULL);     /* reduce multiple matches created by optional query prefix */
      break;

    case longest_match:
      apply_range_set_operation(res, RMaximalMatches, NULL, NULL);         /* select longest from several nested matches */
      break;

    case traditional:
    default:
      /* nothing to do here */
      break;
    }

    /* if there's a cut_value, we may need to reduce the result to <cut_value> matches */
    if (cut_value > 0) {
      /* if there is more than 1 initial pattern in the query, it may have returned more than <cut_value> matches */
      if (res->size > cut_value) {
        Bitfield lines = create_bitfield(res->size);
        int i;
        for (i = 0; i < cut_value; i++)
          set_bit(lines, i);
        if (!delete_intervals(res, lines, UNSELECTED_LINES)) {
          cqpmessage(Error, "Couldn't reduce query result to first %d matches.\n", cut_value);
        }
        destroy_bitfield(&lines);
      }
    }

  }

  cl_free(searchstr);

  return res;
}

CorpusList *
do_MUQuery(Evaltree evalt, int keep_flag, int cut_value)
{
  CorpusList *res;

  cqpmessage(Message, "Meet/Union Query");

  if (parseonly || (generate_code == 0))
    res = NULL;
  else if (evalt != NULL) {

    assert(CurEnv == &Environment[0]);

    CurEnv->evaltree = evalt;

    assert((evalt->type == meet_union) ||
           (evalt->type == leaf));

    debug_output();
    do_start_timer();

    if (keep_flag == 1 && current_corpus->type != SUB) {
      cqpmessage(Warning, "``Keep Ranges'' only allowed when \n"
                 "querying subcorpora");
      keep_flag = 0;
    }

    cqp_run_mu_query(keep_flag, cut_value);

    res = Environment[0].query_corpus;
  }
  else
    res = NULL;

  return res;
}

void
do_SearchPattern(Evaltree expr, /* $1 */
                 Constrainttree constraint) /* $3 */
{
  cqpmessage(Message, "SearchPattern");

  if (generate_code) {
    CurEnv->evaltree = expr;
    CurEnv->gconstraint = constraint;

    if (!check_labels(CurEnv->labels)) {
      cqpmessage(Error, "Illegal use of labels, not evaluated.");
      generate_code = 0;
    }
    else {

      searchstr = (char *)evaltree2searchstr(CurEnv->evaltree,
                                             &sslen);
      if (search_debug) {
        printf("Evaltree: \n");
        print_evaltree(eep, CurEnv->evaltree, 0);
        printf("Search String: ``%s''\n", searchstr);
      }

      if (searchstr && (strspn(searchstr, " ") < strlen(searchstr))) { /* i.e. searchstr does not match /^\s*$/ */
        regex2dfa(searchstr, &(CurEnv->dfa));
      }
      else {
        cqpmessage(Error, "Query is vacuous, not evaluated.");
        generate_code = 0;
      }
      cl_free(searchstr);
    }
  } /* endif generate_code */
}

/* ======================================== Regular Expressions */

Evaltree
reg_disj(Evaltree left, Evaltree right)
{
  Evaltree ev;
  if (generate_code) {
    NEW_EVALNODE(ev, re_disj, left, right, repeat_none, repeat_none);
    return ev;
  }
  else
    return NULL;
}

Evaltree
reg_seq(Evaltree left, Evaltree right)
{
  Evaltree ev;

  if (generate_code) {
    NEW_EVALNODE(ev, re_od_concat, left, right, repeat_none, repeat_none);
    return ev;
  }
  else
    return NULL;
}

int
do_AnchorPoint(FieldType field, int is_closing)
{
  int res = -1;

  cqpmessage(Message, "Anchor: <%s%s>", ((is_closing) ? "/" : ""), field_type_to_name(field));

  if (generate_code) {
    if (CurEnv->MaxPatIndex == MAXPATTERNS) {
      cqpmessage(Error, "Too many patterns (max is %d)", MAXPATTERNS);
      generate_code = 0; /* YYABORT; */
    }
  }

  if (generate_code) {
    /* check that <target> or <keyword> anchor is defined in query_corpus */
    switch (field) {

    case MatchField:
    case MatchEndField:
      break;                        /* ok (if query_corpus->size == 0, subquery will simply return no matches) */

    case TargetField:
      if (query_corpus->targets == NULL) {
        cqpmessage(Error, "<target> anchor not defined in %s", query_corpus->name);
        generate_code = 0;
      }
      break;

    case KeywordField:
      if (query_corpus->keywords == NULL) {
        cqpmessage(Error, "<keyword> anchor not defined in %s", query_corpus->name);
        generate_code = 0;
      }
      break;

    default:
      /* should not be reachable */
      assert("Internal error in do_AnchorPoint()" && 0);
    }
  }

  if (generate_code) {
    CurEnv->MaxPatIndex++;
    CurEnv->patternlist[CurEnv->MaxPatIndex].type = Anchor;
    CurEnv->patternlist[CurEnv->MaxPatIndex].anchor.is_closing = is_closing;
    CurEnv->patternlist[CurEnv->MaxPatIndex].anchor.field = field;

    res = CurEnv->MaxPatIndex;
  }

  if (!generate_code)
    res = -1;

  return res;
}


int
do_XMLTag(char *s_name, int is_closing, int op, char *regex, int flags)
{
  Attribute *attr = NULL;
  int op_type = op & OP_NOT_MASK;
  int negated = op & OP_NOT;
  int res = -1;

  cqpmessage(Message, "StructureDescr: <%s%s>", (is_closing ? "/" : ""), s_name);

  if (generate_code) {
    if (CurEnv->MaxPatIndex == MAXPATTERNS) {
      cqpmessage(Error, "Too many patterns (max is %d)", MAXPATTERNS);
      generate_code = 0; /* YYABORT; */
    }
  }

  if (generate_code) {
    attr = cl_new_attribute(query_corpus->corpus, s_name, ATT_STRUC);
    if (attr == NULL) {
      cqpmessage(Error, "Structural attribute %s.%s does not exist.", query_corpus->name, s_name);
      generate_code = 0; /* YYABORT; */
    }
    else {
      if (regex && !cl_struc_values(attr)) {
        cqpmessage(Error, "Structural attribute %s.%s does not have annotated values.", query_corpus->name, s_name);
        generate_code = 0;
      }
    }
  }

  if (generate_code && ((op_type == OP_MATCHES) || (op_type == OP_CONTAINS)) && (flags == IGNORE_REGEX)) {
    cqpmessage(Error, "Can't use literal strings with 'contains' and 'matches' operators.");
    generate_code = 0;
  }

  if (generate_code) {
    CurEnv->MaxPatIndex++;

    CurEnv->patternlist[CurEnv->MaxPatIndex].type = Tag;
    CurEnv->patternlist[CurEnv->MaxPatIndex].tag.attr = attr;
    CurEnv->patternlist[CurEnv->MaxPatIndex].tag.is_closing = is_closing;
    CurEnv->patternlist[CurEnv->MaxPatIndex].tag.constraint = NULL;
    CurEnv->patternlist[CurEnv->MaxPatIndex].tag.flags = 0;
    CurEnv->patternlist[CurEnv->MaxPatIndex].tag.rx = NULL;
    CurEnv->patternlist[CurEnv->MaxPatIndex].tag.negated = 0;
    CurEnv->patternlist[CurEnv->MaxPatIndex].tag.right_boundary = (LabelEntry) NULL;

    /* start tag may have regex constraint on annotated values */
    if ((!is_closing) && regex) {
      cl_string_latex2iso(regex, regex, strlen(regex));        /* interpret latex escapes */

      if (flags == IGNORE_REGEX || ((strcspn(regex, "[](){}.*+|?\\") == strlen(regex)) && (flags == 0) && (op_type == OP_EQUAL))) {
        /* match as literal string -> don't compile regex */
      }
      else {
        int safe_regex = !(strchr(regex, '|') || strchr(regex, '\\')); /* see below */
        char *conv_regex;        /* OP_CONTAINS and OP_MATCHES */
        char *pattern;
        CL_Regex rx;

        if ((op_type == OP_CONTAINS) || (op_type == OP_MATCHES)) {
          conv_regex = mval_string_conversion(regex);
          pattern = cl_malloc(strlen(conv_regex) + 42); /* leave some room for the regexp wrapper */
          if (op_type == OP_CONTAINS)
            sprintf(pattern, ".*\\|(%s)\\|.*", conv_regex);
          else {                /* op_type == OP_MATCHES */
            if (safe_regex)        /* inner regexp is 'safe' so we can omit the parentheses and thus enable optimisation */
              sprintf(pattern, "\\|(%s\\|)+", conv_regex);
            else
              sprintf(pattern, "\\|((%s)\\|)+", conv_regex);
          }
          cl_free(conv_regex);
        }
        else if (op_type == OP_EQUAL)
          pattern = cl_strdup(regex);
        else
          /* undefined operator */
          assert(0 && "do_mval_string(): illegal opcode (internal error)");

        rx = cl_new_regex(pattern, flags, query_corpus->corpus->charset);
        if (rx == NULL) {
          cqpmessage(Error, "Illegal regular expression: %s", regex);
          generate_code = 0;
        }
        else
          CurEnv->patternlist[CurEnv->MaxPatIndex].tag.rx = rx;

        cl_free(pattern);
      }
      CurEnv->patternlist[CurEnv->MaxPatIndex].tag.constraint = regex;
      CurEnv->patternlist[CurEnv->MaxPatIndex].tag.flags      = flags;
      CurEnv->patternlist[CurEnv->MaxPatIndex].tag.negated    = negated;
    }
  }

  if (generate_code && strict_regions) {
    /* label is 'defined' by first open tag and 'used' by following close tag -> in this case, it is activated */
    LabelEntry label;
    if (!is_closing) {                /* open tag -> 'define' label */
      label = labellookup(CurEnv->labels, s_name, LAB_DEFINED|LAB_RDAT, 1);
      CurEnv->patternlist[CurEnv->MaxPatIndex].tag.right_boundary = label;
    }
    else {                        /* close tag -> if label is already defined, it is 'used', i.e. activated */
      label = findlabel(CurEnv->labels, s_name, LAB_RDAT);
      if ((label != NULL) && (label->flags & LAB_DEFINED)) {
        label->flags |= LAB_USED; /* activate this label for strict regions */
        CurEnv->patternlist[CurEnv->MaxPatIndex].tag.right_boundary = label;
      }
      else {
        /* end tag doesn't check or reset the label if it isn't preceded by a corresponding open tag */
        /*           label = labellookup(CurEnv->labels, s_name+offset, LAB_RDAT, 1); */
      }
    }
  }

  if (generate_code)
    res = CurEnv->MaxPatIndex;
  else {
    res = -1;
    cl_free(regex);
  }

  return res;
}

int
do_NamedWfPattern(target_nature is_target, char *label, int pat_idx) {
  /* is_target = 0 (no marker), 1 (marked as target), 2 (marked as keyword) */
  int res;
  LabelEntry lab;

  res = -1;

  cqpmessage(Message, "NamedWfPattern");
  assert(is_target == IsNotTarget || is_target == IsTarget || is_target == IsKeyword);

  if (generate_code) {
    if (label != NULL) {
      /* lookup or create label */
      lab = labellookup(CurEnv->labels, label, LAB_DEFINED, 1);
      /* user isn't allowed to set special label */
      if (lab->flags & LAB_SPECIAL) {
        cqpmessage(Error, "Can't set special label %s", label);
        generate_code = 0;
        return 0;
      }
    }
    else
      lab = NULL;

    switch (CurEnv->patternlist[pat_idx].type) {

    case Pattern:
      CurEnv->patternlist[pat_idx].con.label = lab;
      CurEnv->patternlist[pat_idx].con.is_target = is_target;
      break;

    case MatchAll:
      CurEnv->patternlist[pat_idx].matchall.label = lab;
      CurEnv->patternlist[pat_idx].matchall.is_target = is_target;
      break;

    default:
      assert("Can't be" && 0);
      break;
    }

    if (is_target == IsTarget) {
      CurEnv->has_target_indicator = 1;
      CurEnv->target_label = labellookup(CurEnv->labels, "target", LAB_DEFINED|LAB_USED, 1);
      /* the special "target" label is never formally ``used'' in the construction of the
         NFA, so we declare it as both DEFINED and USED (which it will be in <eval.h>) */
    }
    if (is_target == IsKeyword) {
      CurEnv->has_keyword_indicator = 1;
      CurEnv->keyword_label = labellookup(CurEnv->labels, "keyword", LAB_DEFINED|LAB_USED, 1);
    }

    res = pat_idx;
  }
  else
    res = 0;

  return res;
}

int
do_WordformPattern(Constrainttree boolt, int lookahead) {
  int res;

  if (generate_code) {
    if (CurEnv->MaxPatIndex == MAXPATTERNS) {
      cqpmessage(Error, "Too many patterns (max is %d)", MAXPATTERNS);
      generate_code = 0;
    }
  }

  if (generate_code) {
    CurEnv->MaxPatIndex++;

    if ((boolt->type == cnode) && (boolt->constnode.val == 1)) {
      /* matchall */

      cl_free(boolt);

      CurEnv->patternlist[CurEnv->MaxPatIndex].type = MatchAll;
      CurEnv->patternlist[CurEnv->MaxPatIndex].matchall.label = NULL;
      CurEnv->patternlist[CurEnv->MaxPatIndex].matchall.is_target = IsNotTarget;
      CurEnv->patternlist[CurEnv->MaxPatIndex].matchall.lookahead = lookahead;
    }
    else {
      CurEnv->patternlist[CurEnv->MaxPatIndex].type = Pattern;

/* the assertion below is utter bollocks; that pattern may have had a different type
   in the previous query, so the assertion will only be true if the particular bit of memory
   storing the pointer has been initialised to zeroes */
/*        assert(CurEnv->patternlist[CurEnv->MaxPatIndex].con.constraint == NULL); */
      CurEnv->patternlist[CurEnv->MaxPatIndex].con.constraint = boolt;
      CurEnv->patternlist[CurEnv->MaxPatIndex].con.label      = NULL;
      CurEnv->patternlist[CurEnv->MaxPatIndex].con.is_target = IsNotTarget;
      CurEnv->patternlist[CurEnv->MaxPatIndex].con.lookahead  = lookahead;
    }
    res = CurEnv->MaxPatIndex;
  }
  else
    res = -1;

  return res;
}


Constrainttree
OptimizeStringConstraint(Constrainttree left, enum b_ops op, Constrainttree right)
{
  Constrainttree c = NULL;

  if (right->type == cnode) {
    cl_free(left);
    c = right;
    right = NULL;
    if (op == cmp_neq)
      c->constnode.val = !c->constnode.val;
  }
  else {
    NEW_BNODE(c);

    if (right->leaf.pat_type == REGEXP) {

      int range = cl_max_id(left->pa_ref.attr);

      /* optimise regular expressions to idlists for categorical attributes (at most MAKE_IDLIST_BOUND lexicon entries) */
      if ((range > 0) && (range < MAKE_IDLIST_BOUND)) {
        int *items;
        int nr_items;

        items = cl_regex2id(left->pa_ref.attr, right->leaf.ctype.sconst, right->leaf.canon, &nr_items);

        if (!CL_ALL_OK()) {
          cqpmessage(Error, "Error while collecting matching IDs of %s\n(%s)\n",
                     right->leaf.ctype.sconst,
                     cl_error_string(cl_errno));
          generate_code = 0;

          c->type = cnode;
          c->constnode.val = 0;
        }
        else if (nr_items == 0) {
          cl_free(items);
          c->type = cnode;
          c->constnode.val = (op == cmp_eq ? 0 : 1);
        }
        else if (nr_items == range) {
          cl_free(items);
          c->type = cnode;
          c->constnode.val = (op == cmp_eq ? 1 : 0);
        }
        else {
          c->type = id_list;
          c->idlist.attr = left->pa_ref.attr;
          c->idlist.label = left->pa_ref.label;
          c->idlist.delete = left->pa_ref.delete;

          c->idlist.nr_items = nr_items;
          c->idlist.items = items;
          c->idlist.negated = (op == cmp_eq ? 0 : 1);

          /* if more than half of all IDs match, the ID list can be processed more
             efficiently when it is inverted (exchanging == for != in the comparison
             where it is used, and vice versa); however, for sparse attributes (where
             the most frequent type is "no value") the inverted list might match almost
             all tokens in the corpus, which can be catastrophic when the ID list is
             used for index lookup (in query-initial position); therefore, the decision
             for or against negation should be based on whether the ID list matches
             more than half of all tokens; for "normal" attributes, the two criteria
             will lead to very similar decisions anyway */

          /* previous condition removed: */
          /*           if (nr_items > range/2) { */

          if (cl_idlist2freq(left->pa_ref.attr, items, nr_items) > cl_max_cpos(left->pa_ref.attr) / 2) {
            int i, k, pos, last_id;
            int *ids;

            ids = (int *)cl_malloc((range - nr_items) * sizeof(int));
            pos = 0;
            last_id = -1;

            for (i = 0; i < nr_items; i++) {
              if (last_id < 0) {
                for (k = 0; k < items[i]; k++)
                  ids[pos++] = k;
              }
              else {
                for (k = last_id + 1; k < items[i]; k++)
                  ids[pos++] = k;
              }
              last_id = items[i];
            }
            for (k = last_id + 1; k < range; k++)
              ids[pos++] = k;

            assert(pos == range - nr_items);

            c->idlist.nr_items = range - nr_items;
            c->idlist.items = ids;
            c->idlist.negated = !c->idlist.negated;

            cl_free(items);
          }
        }

        cl_free(left);
        cl_free(right);
      }
      else {
        c->type = bnode;
        c->node.op_id = op;
        c->node.left = left;
        c->node.right = right;
      }
    }
    else {

      int id;

      assert(right->leaf.pat_type == NORMAL);

      id = cl_str2id(left->pa_ref.attr, right->leaf.ctype.sconst);

      if (id < 0) {
        if (catch_unknown_ids) {
          /* nb effectively if (0) since catch_unknown_ids is initialised to 0 and no code changes it -- AH*/
          cqpmessage(Error, "The string ``%s'' is not in the value space of ``%s''\n",
                     right->leaf.ctype.sconst, left->pa_ref.attr->any.name);
          generate_code = 0;
        }

        cl_free(right);
        cl_free(left);
        c->type = cnode;
        c->constnode.val = (op == cmp_eq ? 0 : 1);
      }
      else {
        c->type = bnode;
        c->node.op_id = op;
        c->node.left = left;
        c->node.right = right;

        cl_free(right->leaf.ctype.sconst);

        right->leaf.pat_type = CID;
        right->leaf.ctype.cidconst = id;
      }
    }
  }

  return c;
}

Constrainttree
do_StringConstraint(char *s, int flags)
{
  Constrainttree c, left, right;
  Attribute *attr = NULL;

  c = NULL; left = NULL; right = NULL;

  if (generate_code) {

    if (!(attr = cl_new_attribute(query_corpus->corpus, def_unbr_attr, ATT_POS))) {
      cqpmessage(Error,
                 "``%s'' attribute not defined for corpus ``%s'',\nusing ``%s''",
                 def_unbr_attr, query_corpus->name, CWB_DEFAULT_ATT_NAME);

      set_string_option_value("DefaultNonbrackAttr", CWB_DEFAULT_ATT_NAME);

      if (!(attr = cl_new_attribute(query_corpus->corpus, CWB_DEFAULT_ATT_NAME, ATT_POS))) {
        cqpmessage(Error,
                   "``%s'' attribute not defined for corpus ``%s''",
                   CWB_DEFAULT_ATT_NAME, query_corpus->name);

        generate_code = 0;
      }
    }
  }

  if (generate_code) {

    if (!(right = do_flagged_string(s, flags)))
      generate_code = 0;
    else if (right->type == cnode) {
      c = right;
      right = NULL;
    }
    else {

      /* make a new leaf node which holds the attribute */

      NEW_BNODE(left);
      left->type = pa_ref;
      left->pa_ref.attr = attr;
      left->pa_ref.label = NULL;
      left->pa_ref.delete = 0;

      c = OptimizeStringConstraint(left, cmp_eq, right);
    }
  }

  if (generate_code)
    return c;
  else
    return NULL;
}

Constrainttree
Varref2IDList(Attribute *attr, enum b_ops op, char *varName)
{
  Constrainttree node;

  node = NULL;

  if (generate_code) {

    Variable v;

    if ((v = FindVariable(varName)) != NULL) {

      NEW_BNODE(node);

      node->type = id_list;
      node->idlist.attr = attr;
      node->idlist.label = NULL;
      node->idlist.delete = 0;
      node->idlist.negated = (op == cmp_eq ? 0 : 1);
      node->idlist.items = GetVariableItems(v,
                                            query_corpus->corpus,
                                            attr,
                                            &(node->idlist.nr_items));

      if (node->idlist.nr_items == 0) {        /* optimise: empty ID list -> constant */
        node->type = cnode;
        node->constnode.val = (op == cmp_eq ? 0 : 1); /* always FALSE for '=', always TRUE for '!=' */
        /* NB: no need to free idlist.items, because the list is empty (NULL pointer) */
      }

    }
    else {
      cqpmessage(Error, "%s: no such variable.");
      generate_code = 0;
    }
  }

  return node;
}

Constrainttree
do_SimpleVariableReference(char *varName)
{
  Attribute *attr = NULL;

  if (generate_code) {
    if (!(attr = cl_new_attribute(query_corpus->corpus, def_unbr_attr, ATT_POS))) {
      cqpmessage(Error,
                 "``%s'' attribute not defined for corpus ``%s'',"
                 "\nusing ``%s''",
                 def_unbr_attr, query_corpus->name,
                 CWB_DEFAULT_ATT_NAME);

      set_string_option_value("DefaultNonbrackAttr", CWB_DEFAULT_ATT_NAME);

      if (!(attr = cl_new_attribute(query_corpus->corpus, CWB_DEFAULT_ATT_NAME, ATT_POS))) {
        cqpmessage(Error,
                   "``%s'' attribute not defined for corpus ``%s''",
                   CWB_DEFAULT_ATT_NAME, query_corpus->name);

        generate_code = 0;
      }
    }
  }

  if (generate_code)
    return Varref2IDList(attr, cmp_eq, varName);
  else
    return NULL;
}

void
prepare_AlignmentConstraints(char *id)
{
  Attribute *algattr;
  CorpusList *cl;

  if ((cl = findcorpus(id, SYSTEM, 0)) == NULL) {
    cqpmessage(Warning, "System corpus ``%s'' is undefined", id);
    generate_code = 0;
  }
  else if (!access_corpus(cl)) {
    cqpmessage(Warning, "Corpus ``%s'' can't be accessed", id);
    generate_code = 0;
  }
  else if (!(algattr = cl_new_attribute(Environment[0].query_corpus->corpus, cl->corpus->registry_name, ATT_ALIGN))) {
    cqpmessage(Error, "Corpus ``%s'' is not aligned to corpus ``%s''", Environment[0].query_corpus->mother_name, id);
    generate_code = 0;
  }
  else if (!next_environment()) {
    cqpmessage(Error, "Can't allocate another evaluation environment (too many alignments)");
    generate_code = 0;
    query_corpus = NULL;
  }
  else {
    CurEnv->aligned = algattr;
    CurEnv->query_corpus = cl;
    query_corpus = cl;
  }
}

/* ======================================== BOOLEAN OPS */

Constrainttree
bool_or(Constrainttree left, Constrainttree right)
{
  Constrainttree res = NULL;

  if (generate_code) {
    if (left->node.type == cnode) {
      if (left->constnode.val == 0) {
        res = right;
        free_booltree(left);
      }
      else {
        res = left;
        free_booltree(right);
      }
    }
    else if (right->node.type == cnode) {
      if (right->constnode.val == 0) {
        res = left;
        free_booltree(right);
      }
      else {
        res = right;
        free_booltree(left);
      }
    }
    else {
      NEW_BNODE(res);
      res->node.type = bnode;
      res->node.op_id = b_or;
      res->node.left = left;
      res->node.right = right;

      res = try_optimization(res);
    }
  }

  return res;
}

Constrainttree
bool_implies(Constrainttree left, Constrainttree right)
{
  Constrainttree res = NULL;

  if (generate_code) {
    if (left->node.type == cnode) {
      if (left->constnode.val == 0) { /* LHS is FALSE -> implication always TRUE */
        res = left;
        res->constnode.val = 1;
        free_booltree(right);
      }
      else {                        /* LHS is TRUE -> implication == RHS */
        res = right;
        free_booltree(left);
      }
    }
    else if (right->node.type == cnode) {
      if (right->constnode.val == 0) { /* RHS is FALSE -> implication == !(LHS) */
        res = bool_not(left);
        free_booltree(right);
      }
      else {                        /* RHS is TRUE -> implication always TRUE */
        res = right;
        free_booltree(left);
      }
    }
    else {
      NEW_BNODE(res);
      res->node.type = bnode;
      res->node.op_id = b_implies;
      res->node.left = left;
      res->node.right = right;

      res = try_optimization(res);
    }
  }
  else
    res = NULL;

  return res;
}

Constrainttree
bool_and(Constrainttree left, Constrainttree right)
{
  Constrainttree res;
  res = NULL;

  if (generate_code) {
    if (left->node.type == cnode) {
      if (left->constnode.val == 0) {
        res = left;
        free_booltree(right);
      }
      else {
        res = right;
        free_booltree(left);
      }
    }
    else if (right->node.type == cnode) {
      if (right->constnode.val == 0) {
        res = right;
        free_booltree(left);
      }
      else {
        res = left;
        free_booltree(right);
      }
    }
    else {
      NEW_BNODE(res);
      res->node.type = bnode;
      res->node.op_id = b_and;
      res->node.left = left;
      res->node.right = right;
    }
  }
  else
    res = NULL;

  return res;
}

Constrainttree
bool_not(Constrainttree left)
{
  Constrainttree res;
  res = NULL;

  if (generate_code) {
    if (left->node.type == cnode) {
      left->constnode.val = !(left->constnode.val);
      res = left;
    }
    else if (left->type == id_list) {
      left->idlist.negated = !left->idlist.negated;
      res = left;
    }
    else if (left->type == bnode &&
             left->node.op_id == b_not &&
             left->node.right == NULL) {
      res = left->node.left;
      left->node.left = NULL;
      free_booltree(left);
    }
    else {
      NEW_BNODE(res);
      res->node.type = bnode;
      res->node.op_id = b_not;
      res->node.left = left;
      res->node.right = NULL;
    }
  }
  else
    res = NULL;

  return res;
}

Constrainttree
do_RelExpr(Constrainttree left,
           enum b_ops op,
           Constrainttree right)
{
  Constrainttree res;

  res = NULL;

  if (generate_code) {

    if (right->type == var_ref) {

      if (left->type == pa_ref) {

        res = Varref2IDList(left->pa_ref.attr, op, right->varref.varName);

        /* be careful: res might be of type cnode, when an empty id_list has been optimised away */
        if (res && res->type == id_list && generate_code) {
          res->idlist.label = left->pa_ref.label;
          res->idlist.delete = left->pa_ref.delete;
        }
      }
      else {
        cqpmessage(Error,
                   "LHS of variable reference must be the name of "
                   "a positional attribute");


        generate_code = 0;
      }

      free_booltree(left);
      free_booltree(right);

    }
    else if ((left->type == pa_ref) && (right->type == string_leaf)) {
      if (op == cmp_eq || op == cmp_neq) {
        res = OptimizeStringConstraint(left, op, right);
      }
      else {
        cqpmessage(Error,
                   "Inequality comparisons (<, <=, >, >=) are not allowed for strings and regular expressions");
        generate_code = 0;
      }
    }
    else {

      NEW_BNODE(res);

      res->type = bnode;
      res->node.op_id = op;
      res->node.left = left;
      res->node.right = right;

      res = try_optimization(res);

    }
  }
  else
    res = NULL;

  return res;
}

Constrainttree
do_RelExExpr(Constrainttree left)
{
  Constrainttree res;
  res = NULL;

  if (generate_code) {
    NEW_BNODE(res);
    res->type = bnode;
    res->node.op_id = cmp_ex;
    res->node.left = left;
    res->node.right = NULL;

    res = try_optimization(res);
  }
  else
    res = NULL;
  return res;
}

Constrainttree
do_LabelReference(char *label_name, int auto_delete)
{
  Constrainttree res= NULL;
  Attribute *attr= NULL;
  LabelEntry lab= NULL;
  char *hack = NULL;

  if (CurEnv == NULL) {
    cqpmessage(Error, "No label references allowed");
    generate_code = 0;
  }
  else {
    /* find the dot in the qualified name */
    hack = strchr(label_name, '.');
    if (hack == NULL) {
      cqpmessage(Error, "``%s'' is not a valid label reference.", label_name);
      generate_code = 0;
    }
  }

  if (generate_code) {
    *hack = '\0';
    hack++;
    /* now, label_name keeps the label, hack points to the attribute */

    lab = labellookup(CurEnv->labels, label_name, LAB_USED, 0);
    /*     if (!(lab->flags & LAB_SPECIAL) && !(lab->flags & LAB_DEFINED)) { */
    if (lab == NULL) {                /* this is more like what we want: label hasn't been defined yet ('this' label is implicitly defined) */
      cqpmessage(Error, "Label ``%s'' used before it was defined", label_name);
      generate_code = 0;
    }
    else if (lab->flags & LAB_SPECIAL) {
      if (auto_delete) {
        cqpmessage(Warning, "Cannot auto-delete special label '%s' [ignored].", label_name);
        auto_delete = 0;
      }
    }
  }

  if (generate_code) {
    if (NULL != (attr = cl_new_attribute(query_corpus->corpus, hack, ATT_POS))) {
      /* reference to positional attribute at label */
      NEW_BNODE(res);
      res->type = pa_ref;
      res->pa_ref.attr = attr;
      res->pa_ref.label = lab;
      res->pa_ref.delete = auto_delete;
    }
    else if (!(attr = cl_new_attribute(query_corpus->corpus, hack, ATT_STRUC))) {
      cqpmessage(Error, "Attribute ``%s'' is not defined for corpus", hack);
      generate_code = 0;
    }
    else {
      /* reference to (value of) structural attribute at label */
      if (!cl_struc_values(attr)) {
        cqpmessage(Error, "Need attribute with values (``%s'' has no values)", hack);
        generate_code = 0;
      }
      else {
        NEW_BNODE(res);
        res->type = sa_ref;
        res->sa_ref.attr = attr;
        res->sa_ref.label = lab;
        res->sa_ref.delete = auto_delete;
      }
    }
  }

  cl_free(label_name);

  if (!generate_code)
    res = NULL;

  return res;
}

Constrainttree
do_IDReference(char *id_name, int auto_delete)  /* auto_delete may only be set if this ID is a bare label */
{
  Constrainttree res;
  Attribute *attr;
  LabelEntry lab;

  res = NULL; lab = NULL; attr = NULL;

  if (generate_code) {
    if (!within_gc && (attr = cl_new_attribute(query_corpus->corpus, id_name, ATT_POS))) {
      NEW_BNODE(res);
      res->type = pa_ref;
      res->pa_ref.attr = attr;
      res->pa_ref.label = NULL;
      res->pa_ref.delete = 0;
    }
    else if (NULL != (lab = labellookup(CurEnv->labels, id_name, LAB_USED, 0))) {
      NEW_BNODE(res);
      res->type = pa_ref;
      res->pa_ref.attr = NULL;
      res->pa_ref.label = lab;
      if ((lab->flags & LAB_SPECIAL) && auto_delete) {
        cqpmessage(Warning, "Cannot auto-delete special label '%s' [ignored].", id_name);
        auto_delete = 0;
      }
      res->pa_ref.delete = auto_delete;
      auto_delete = 0;                /* we'll check that below */
    }
    else if (NULL != (attr = cl_new_attribute(query_corpus->corpus, id_name, ATT_STRUC))) {
      /* Well I was wondering myself ... this is needed for references
         to structural attributes in function calls. The semantics of say
         's' is to return an INT value of
           1 ... if current position is the start of a region
           2 ... if it's the end of a region
           0 ... otherwise
         If the current position is not within an 's' region, the whole
         boolean expression where the reference occurred evals to False */

      NEW_BNODE(res);
      res->type = sa_ref;
      res->sa_ref.attr = attr;
      /* Need to set label to NULL now that we put sa_ref's to better use.
         A label's sa_ref now returns the value of the enclosing region */
      res->sa_ref.label = NULL;
      res->sa_ref.delete = 0;
    }
    else {
      if (within_gc)
        cqpmessage(Error, "``%s'' is not a (qualified) label reference", id_name);
      else
        cqpmessage(Error, "``%s'' is neither a positional/structural attribute nor a label reference", id_name);

      generate_code = 0;
      auto_delete = 0;                /* so we won't raise another error */
      res = NULL;
    }
  }

  /* if auto_delete is still set, it was set on an attribute -> error */
  if (auto_delete) {
    cqpmessage(Error, "Auto-delete expression '~%s' not allowed ('%s' is not a label)", id_name, id_name);
    generate_code = 0;
    res = NULL;
  }

  cl_free(id_name);
  return res;
}

/**
 * Implements expansion of a variable within the RE() operator.
 */
Constrainttree
do_flagged_re_variable(char *varname, int flags)
{
  Constrainttree tree;
  Variable var;
  char *s, *mark, **items;
  int length, i, l, N_strings;

  tree = NULL;
  if (flags == IGNORE_REGEX) {
    cqpmessage(Warning, "%c%c flag doesn't make sense with RE($%s) (ignored)", '%', 'l', varname);
    flags = 0;
  }

  var = FindVariable(varname);
  if (var != NULL) {
    items = GetVariableStrings(var, &N_strings);
    if (items == NULL || N_strings == 0) {
      cqpmessage(Error, "Variable $%s is empty.", varname);
      generate_code = 0;
    }
    else {
      /* compute length of interpolated regular expression */
      length = 1;
      for (i = 0; i < N_strings; i++)
        length += strlen(items[i]) + 1;
      s = cl_malloc(length);
      l = sprintf(s, "%s", items[0]);
      mark = s + l;        /* <mark> points to the trailing null byte */
      for (i = 1; i < N_strings; i++) {
        l = sprintf(mark, "|%s", items[i]);
        mark += l;
      }
      cl_free(items);
      /* now <s> contains the disjunction over all REs stored in <var> */

      /* since the var strings were loaded without charset checking,
       * we need to now check the regex for the present corpus's encoding. */
      if (! cl_string_validate_encoding(s, query_corpus->corpus->charset, 0)){
        cqpmessage(Error, "Variable $%s used with RE() includes one or more strings with characters that are invalid\n"
                "in the encoding specified for corpus [%s]", varname, query_corpus->corpus->name);
        generate_code = 0;
        cl_free(s);
      }
      else
        tree = do_flagged_string(s, flags);
        /* note that <s> is inserted into the constraint tree and mustn't be freed here */
    }
  }
  else {
    cqpmessage(Error, "Variable $%s is not defined.", varname);
    generate_code = 0;
  }

  cl_free(varname);
  return tree;
}


Constrainttree
do_flagged_string(char *s, int flags)
{
  Constrainttree res = NULL;

  if (generate_code) {
    NEW_BNODE(res);

    /* This gets in the way with some other functions were we'd like to
       keep it as a regexp ... it isn't very useful anyway, so ... */
/*    if ((strcmp(s, ".*") == 0 ||
         strcmp(s, ".+") == 0) &&
        flags != IGNORE_REGEX) {
      res->type = cnode;
      res->constnode.val = 1;
    }
    else { */

    res->type = string_leaf;
    res->leaf.canon = flags;

    cl_string_latex2iso(s, s, strlen(s));

    if (flags == IGNORE_REGEX || (strcspn(s, "[](){}.*+|?\\") == strlen(s) && flags == 0)) {
      res->leaf.ctype.sconst = s;
      res->leaf.pat_type = NORMAL;
    }
    else {
      /* Sonderzeichen oder flags != 0/IGNORE_REGEX */

      res->leaf.pat_type = REGEXP;
      res->leaf.ctype.sconst = s;
      res->leaf.rx = cl_new_regex(s, flags, query_corpus->corpus->charset);
      if (res->leaf.rx == NULL) {
        cqpmessage(Error, "Illegal regular expression: %s", s);
        res->leaf.pat_type = NORMAL;
        generate_code = 0;
      }
    }
    /*  } */
  }

  if (!generate_code)
    res = NULL;

  return res;
}

/* in an mval_string regexp, matchall dots ('.') need to be converted to '[^|]'
   in order to give intuitive results (otherwise, '.*' might gobble up all the following
   separator bars ('|')) */
static char *
mval_string_conversion(char *s)
{
  char *result, *p;
  int cnt = 0;

  for (p = s; *p; p++)                /* count dots in <s> */
    if (*p == '.') cnt++;

  result = cl_malloc(strlen(s) + 3*cnt + 1);        /* every '.'->'[^|]' replacement adds three characters */
  p = result;
  while (*s) {
    if (*s == '\\') {                /* copy escaped character verbatim */
      *p++ = *s++;
      if (!(*s)) {
        cqpmessage(Error, "mval_string_conversion(): RegExp '%s' ends with escape", s);
        generate_code = 0;
        cl_free(result);
        return NULL;
      }
      *p++ = *s++;
    }
    else if (*s == '.') {
      s++;
      *p++ = '['; *p++ = '^'; *p++ = '|'; *p++ = ']';
    }
    else {
      *p++ = *s++;
    }
  }
  *p = 0;                        /* end of string */
  return result;
}


/* do_mval_string() replaces do_flagged_string() for 'contains' and 'matches' operators
   that operate on multi-valued attributes */
Constrainttree
do_mval_string(char *s, int op, int flags)
{
  Constrainttree res = NULL;
  char *pattern;  /* regexp that simulates the multi-value operator */
  char *converted_s;
  int safe_regexp;

  if (generate_code) {
    if (flags == IGNORE_REGEX) {
      cqpmessage(Error, "Can't use literal strings with 'contains' and 'matches' operators.");
      generate_code = 0;
      return NULL;
    }
    safe_regexp = !(strchr(s, '|') || strchr(s, '\\')); /* see below */
    converted_s = mval_string_conversion(s);
    if (!converted_s) return NULL; /* generate_code already set to 0 in subroutine */
    pattern = cl_malloc(strlen(converted_s) + 42); /* leave some room for the regexp wrapper */

    switch (op & OP_NOT_MASK) {
    case OP_CONTAINS:
      sprintf(pattern, ".*\\|(%s)\\|.*", converted_s);
      break;
    case OP_MATCHES:
      if (safe_regexp)                /* inner regexp is 'safe' so we can omit the parentheses and thus enable optimisation */
        sprintf(pattern, "\\|(%s\\|)+", converted_s);
      else
        sprintf(pattern, "\\|((%s)\\|)+", converted_s);
      break;
    default:
      /* undefined operator */
      assert(0 && "do_mval_string(): illegal opcode (internal error)");
    }

    res = do_flagged_string(pattern, flags);
    cl_free(converted_s);
    if (!res)
      cl_free(pattern);      /* the pattern is inserted into the RegExp node, so don't free it unless do_flagged_string() failed */
  }

  return res;
}

Constrainttree
FunctionCall(char *f_name, ActualParamList *apl)
{
  Constrainttree res;
  int len, predef;
  ActualParamList *p;
  Attribute *attr;

  res = NULL;

  cqpmessage(Message, "FunctionCall: %s(...)", f_name);

  if (generate_code) {

    /* I'd like to check here whether the function
     * gets the correct parameters. TODO
     */

    len = 0;
    for (p = apl; p; p = p->next)
      len++;

    predef = find_predefined(f_name);

    if (predef >= 0) {

      if (len != builtin_function[predef].nr_args) {
        generate_code = 0;
        cqpmessage(Error,
                   "Illegal number of arguments for %s (need %d, got %d)",
                   f_name, builtin_function[predef].nr_args, len);
      }
      else {
        NEW_BNODE(res);
        res->type = func;
        res->func.predef = predef;
        res->func.dynattr = NULL;
        res->func.args = apl;
        res->func.nr_args = len;
      }
    }
    else if (NULL != (attr = cl_new_attribute(query_corpus->corpus, f_name, ATT_DYN))) {
      NEW_BNODE(res);
      res->type = func;
      res->func.predef = -1;
      res->func.dynattr = attr;
      res->func.args = apl;
      res->func.nr_args = len;
    }
    else {
      cqpmessage(Error, "Function ``%s'' is not defined", f_name);
      generate_code = 0;
    }
  }

  if (!generate_code)
    res = NULL;

  return res;
}


void
do_Description(Context *context, int nr, char *name)
{
  context->space_type = word;
  context->attrib = NULL;
  context->size = 0;

  if (generate_code) {

    if (nr < 0) {
      cqpmessage(Error,
                 "Can't expand to negative size: %d", nr);
      generate_code = 0;
    }
    else if (Environment[0].query_corpus) {

      context->size = nr;

      if ((name == NULL) ||
          (strcmp(name, "word") == 0) ||
          (strcmp(name, "words") == 0)) {
        context->space_type = word;
        context->attrib = NULL;
      }
      else {
        if (!(context->attrib = cl_new_attribute(Environment[0].query_corpus->corpus, name, ATT_STRUC))) {
          cqpmessage(Error,
                     "Structure ``%s'' is not defined for corpus ``%s''",
                     name, Environment[0].query_corpus->name);
          generate_code = 0;
        }
        else
          context->space_type = structure;
      }
    }
    else {
      cqpmessage(Error,
                 "No query corpus yielded and/or accessible");
      generate_code = 0;
    }
  }
}

Evaltree do_MeetStatement(Evaltree left, Evaltree right, Context *context)
{
  Evaltree ev;

  ev = NULL;

  if (generate_code) {
    ev = (Evaltree)cl_malloc(sizeof(union e_tree));

    ev->type = meet_union;
    ev->cooc.op_id = cooc_meet;

    ev->cooc.left = left;
    ev->cooc.right = right;

    ev->cooc.lw = context->size;
    ev->cooc.rw = context->size2;
    ev->cooc.struc = context->attrib;
  }

  return ev;
}

Evaltree
do_UnionStatement(Evaltree left, Evaltree right)
{
  Evaltree ev;

  ev = NULL;

  if (generate_code) {
    ev = (Evaltree)cl_malloc(sizeof(union e_tree));

    ev->type = meet_union;
    ev->cooc.op_id = cooc_union;
    ev->cooc.left = left;
    ev->cooc.right = right;
    ev->cooc.lw = 0;
    ev->cooc.rw = 0;
  }

  return ev;
}

void
do_StructuralContext(Context *context, char *name)
{
  context->space_type = word;
  context->attrib = NULL;
  context->size  = 1;
  context->size2 = 1;

  if (query_corpus) {
    context->size = 1;
    context->size2 = 1;

    if (!(context->attrib = cl_new_attribute(query_corpus->corpus, name, ATT_STRUC))) {
      cqpmessage(Error, "Structure ``%s'' is not defined for corpus ``%s''", name, query_corpus->corpus->id);
      generate_code = 0;
    }
    else
      context->space_type = structure;
  }
  else {
    context->size = 0;
    generate_code = 0;
  }
}

CorpusList *
do_TABQuery(Evaltree patterns)
{
  CorpusList *cl;

  cl = NULL;

  if (parseonly || (generate_code == 0))
    cl = NULL;
  else if (patterns != NULL) {

    assert(CurEnv == &Environment[0]);

    CurEnv->evaltree = patterns;

    assert(patterns->type == tabular);

    debug_output();
    do_start_timer();

    cqp_run_tab_query();

    cl = Environment[0].query_corpus;
  }

  return cl;
}

Evaltree
make_first_tabular_pattern(int pattern_index, Evaltree next)
{
  union e_tree *node;

  node = NULL;

  if (generate_code) {
    node = (union e_tree *)cl_malloc(sizeof(union e_tree));
    node->type = tabular;
    node->tab_el.patindex = pattern_index;
    node->tab_el.min_dist = 0;
    node->tab_el.max_dist = 0;
    node->tab_el.next = next;
  }
  return node;
}

Evaltree
add_tabular_pattern(Evaltree patterns, Context *context, int pattern_index)
{
  union e_tree *node, *k;

  node = NULL;

  if (generate_code) {
    node = (union e_tree *)cl_malloc(sizeof(union e_tree));
    node->type = tabular;
    node->tab_el.patindex = pattern_index;
    node->tab_el.min_dist = context->size;
    node->tab_el.max_dist = context->size2;
    node->tab_el.next = NULL;

    if (patterns) {
      for (k = patterns; k->tab_el.next; k = k->tab_el.next)
        ;
      k->tab_el.next = node;
      node = patterns;
    }
  }

  return node;
}

void
do_OptDistance(Context *context, int l_bound, int u_bound)
{
  if (l_bound < 0) {
    cqpmessage(Warning, "Left/Min. distance must be >= 0 (reset to 0)");
    l_bound = 0;
  }

  if (u_bound < 0 && u_bound != repeat_inf) {
    cqpmessage(Warning, "Right/Max. distance must be >= 0 (reset to 0)");
    u_bound = 0;
  }

  if (u_bound < l_bound && u_bound != repeat_inf) {
    cqpmessage(Warning, "Right/Max. distance must be >= Left/Max. distance");
    u_bound = l_bound;
  }

  context->space_type = word;
  context->size = l_bound;
  context->size2 = u_bound;
  context->attrib = NULL;
}

/* ======================================== Variable Settings */

/**
 * Prints the setting of a single Variable as an indented list.
 */
void
printSingleVariableValue(Variable v, int max_items)
{
  int i;

  if (v) {
    printf("$%s = \n", v->my_name);
    if (max_items <= 0)
      max_items = v->nr_items;

    ilist_start(0, 0, 0);
    for (i = 0; i < v->nr_items; i++) {
      if (i >= max_items) {
        ilist_print_item("...");
        break;
      }
      if (!v->items[i].free) {
        ilist_print_item(v->items[i].sval);
      }
    }
    ilist_end();
  }
}

void
do_PrintAllVariables()
{
  Variable v;

  variables_iterator_new();
  while (NULL != (v = variables_iterator_next())) {
    printSingleVariableValue(v, 44); /* show at most 44 words from each variable in overview */
  }
}

void
do_PrintVariableValue(char *varName)
{
  Variable v;

  if ((v = FindVariable(varName)) != NULL)
    printSingleVariableValue(v, 0);
  else
    cqpmessage(Error, "%s: no such variable", varName);
}

void
do_printVariableSize(char *varName)
{
  Variable v = FindVariable(varName);

  if (v) {
    int i, size = 0;

    for (i = 0; i < v->nr_items; i++) {
      if (!v->items[i].free)
        size++;
    }
    printf("$%s has %d entries\n", v->my_name, size);
  }
  else
    cqpmessage(Error, "%s: no such variable", varName);
}

void
do_SetVariableValue(char *varName, char operator, char *varValues)
{
  Variable v;

  if ((v = FindVariable(varName)) == NULL)
    v = NewVariable(varName);

  if (v != NULL) {

    if (operator != '<') {
      cl_string_latex2iso(varValues, varValues, strlen(varValues));
    }

    if (!SetVariableValue(varName, operator, varValues))
      cqpmessage(Error, "Error in variable value definition.");
  }
  else
    cqpmessage(Warning, "Can't create variable, probably fatal (bad variable name?)");
}

void
do_AddSubVariables(char *var1Name, int add, char *var2Name)
{
  Variable v1, v2;
  char **items;
  int i, N;

  if ((v1 = FindVariable(var1Name)) == NULL) {
    cqpmessage(Error, "Variable $%s not defined.", var1Name);
  }
  else if ((v2 = FindVariable(var2Name)) == NULL) {
    cqpmessage(Error, "Variable $%s not defined.", var2Name);
  }
  else {
    items = GetVariableStrings(v2, &N);
    if (items != NULL) {
      for (i = 0; i < N; i++) {
        if (add)
          VariableAddItem(v1, items[i]);
        else
          VariableSubtractItem(v1, items[i]);
      }
      cl_free(items);                /* the actual strings point into the variable's internal representation, so don't free them */
    }
    else {
      /* v2 is empty, so do nothing */
    }
  }
}

/* ======================================== PARSER UTILS */

/**
 * Get ready to parse a command.
 *
 * This function is called before the processing of each parsed line that is
 * recognised as a command.
 *
 * Mostly it involves setting the global variables to their starting-state
 * values.
 */
void
prepare_input(void)
{
  regex_string_pos = 0;
  free_environments();

  generate_code = 1;
  searchstr = NULL;
  last_cyc = NoExpression;
  LastExpression = NoExpression;
}

/**
 * Expand the dataspace of a subcorpus.
 *
 * This is done, e.g., by the CQP-syntax "expand" command, to include context
 * into the matches found by a query.
 *
 * Each corpus interval stored in the CorpusList is extended by an amount, and in a direction,
 * dependant on the information in the global variable "expansion", a Context object
 * (information which has been put there by the parser).
 *
 * @see       expansion
 * @param cl  The subcorpus to expand.
 */
void
expand_dataspace(CorpusList *cl)
{
  int i, res;

  if (cl == NULL)
    cqpmessage(Warning, "The selected corpus is empty.");
  else if (cl->type == SYSTEM)
    cqpmessage(Warning, "You can only expand subcorpora, not system corpora (nothing has been changed)");
  else if (expansion.size > 0) {

    for (i = 0; i < cl->size; i++) {
      if (expansion.direction == ctxtdir_left || expansion.direction == ctxtdir_leftright) {
        res = calculate_leftboundary(cl,
                                     cl->range[i].start,
                                     expansion);
        if (res >= 0)
          cl->range[i].start = res;
        else
          cqpmessage(Warning, "'expand' statement failed (while expanding corpus interval leftwards).\n");
        /* when the expansion fails, the interval in the subcorpus is left as-is. */
      }
      if (expansion.direction == ctxtdir_right || expansion.direction == ctxtdir_leftright) {
        res = calculate_rightboundary(cl,
                                      cl->range[i].end,
                                      expansion);
        if (res >= 0)
          cl->range[i].end = res;
        else
          cqpmessage(Warning, "'expand' statement failed (while expanding corpus interval rightwards).\n");
        /* as per above: when the expansion fails, the interval in the subcorpus is left as-is. */
      }
    }

    apply_range_set_operation(cl, RUniq, NULL, NULL);

    /* the subcorpus is now unsaved, even if it previously was saved */
    cl->needs_update = True;
    cl->saved = False;
  }
}

/**
 * Add a character (in the sense of a byte) to the regex_string buffer.
 *
 * Doesn't seem to currently be in use.
 *
 * @see regex_string
 */
void
push_regchr(char c)
{
  if (regex_string_pos < CL_MAX_LINE_LENGTH) {
    regex_string[regex_string_pos] = c;
    regex_string_pos++;
    regex_string[regex_string_pos] = '\0';
  }
  else {
    cqpmessage(Warning, "Regex string overflow");
    regex_string[0] = '\0';
  }
}

/**
 * Prints out all the existing EvalEnvironments in the global array.
 *
 * @see Environment
 */
void
debug_output(void)
{
  int i;
  for (i = 0; i <= eep; i++)
    show_environment(i);
}


/** Global variable for timing functions; not exported. @see do_start_timer @see do_timing */
struct timeval timer_start_time;

/**
 * Starts the timer running.
 */
void
do_start_timer(void)
{
  if (timing)
    gettimeofday(&timer_start_time, NULL);
}

/**
 * Shows the period since the timer started running.
 *
 * @param msg  A message to print along with the reading from the timer.
 */
void
do_timing(char *msg)
{
  struct timeval t;
  long delta_s;
  long delta_ms;

  if (timing) {
    gettimeofday(&t, NULL);
    delta_s = t.tv_sec - timer_start_time.tv_sec;
    delta_ms = (t.tv_usec - timer_start_time.tv_usec) / 1000;
    if (delta_ms < 0) {
      delta_s--;
      delta_ms = delta_ms + 1000;
    }
    cqpmessage(Info, "%s in %ld.%.3ld seconds\n", msg, delta_s, delta_ms);
  }
}


/* ====================================== CQP Child mode:  Size & Dump */

void
do_size(CorpusList *cl, FieldType field)
{
  if (cl) {
    if (field != NoField) {
      if (field == TargetField) {
        int count = 0, i;
        if (cl->targets) {
          for (i = 0; i < cl->size; i++) {
            if (cl->targets[i] != -1)
              count++;
          }
        }
        printf("%d\n", count);
      }
      else if (field == KeywordField) {
        int count = 0, i;
        if (cl->keywords) {
          for (i = 0; i < cl->size; i++) {
            if (cl->keywords[i] != -1)
              count++;
          }
        }
        printf("%d\n", count);
      }
      else {                        /* must be Match or MatchEnd then */
        printf("%d\n", cl->size);
      }
    }
    else {
      printf("%d\n", cl->size);
    }
  }
  else {
    printf("0\n");                /* undefined corpus */
  }
}

/**
 * Dump query result (or part of it) as TAB-delimited table of corpus positions.
 *
 * @param cl       The result (as a subcorpus, naturally)
 * @param first    Where in the result to begin dumping (index of cl->range)
 * @param last     Where in the result to end dumping (index of cl->range)
 * @param rd       Pointer to a Redir structure which contains information about
 *                 where to dump to.
 */
void
do_dump(CorpusList *cl, int first, int last, struct Redir *rd)
{
  int i, j, f, l, target, keyword;
  Range *rg;

  if (cl) {
    if (! open_stream(rd, cl->corpus->charset)) {
      cqpmessage(Error, "Can't redirect output to file or pipe\n");
      return;
    }

    f = (first >= 0) ? first : 0;
    l = (last < cl->size) ? last : cl->size - 1;
    for (i = f; (i <= l) && !cl_broken_pipe; i++) {
      j = (cl->sortidx) ? cl->sortidx[i] : i;
      target  = (cl->targets)  ? cl->targets[j]  : -1;
      keyword = (cl->keywords) ? cl->keywords[j] : -1;
      rg = cl->range + j;
      fprintf(rd->stream, "%d\t%d\t%d\t%d\n", rg->start, rg->end, target, keyword);
    }

    close_stream(rd);
  }
}

/** read TAB-delimited table of corpus positions and create named query result from it.
 *
 * acceptable values for extension_fields and corresponding row formats:
 * 0 = match \t matchend
 * 1 = match \t matchend \t target
 * 2 = match \t matchend \t target \t keyword
 */
int
do_undump(char *corpname, int extension_fields, int sort_ranges, struct InputRedir *rd)
{
  int i, ok, size, match, matchend, target, keyword;
  int max_cpos, mark, abort;                /* for validity checks */
  char line[CL_MAX_LINE_LENGTH], junk[CL_MAX_LINE_LENGTH], mother[CL_MAX_LINE_LENGTH];
  CorpusList *cl = current_corpus, *new = NULL;

  assert(corpname != NULL);
  assert((extension_fields >= 0) && (extension_fields <= 2));

  if (! valid_subcorpus_name(corpname)) {
    cqpmessage(Error, "Argument %s is not a valid query name.", corpname);
    return 0;
  }

  if (is_qualified(corpname)) {        /* if <corpname> is qualified, use specified mother corpus */
    corpname = split_subcorpus_name(corpname, mother); /* reset <corpname> to point to local name, copy qualifier to <mother> */
    cl = findcorpus(mother, SYSTEM, 0);
    if (cl == NULL) {
      cqpmessage(Error, "Can't find system corpus %s. Undump aborted.\n", mother);
      return 0;
    }
  }
  else { /* otherwise, check for activated corpus for which named query result will be created */
    if (cl == NULL) {
      cqpmessage(Error, "No corpus activated. Can't perform undump.");
      return 0;
    }

    if (cl->type != SYSTEM) {        /* if a subcorpus is activated, find the corresponding system corpus */
      CorpusList *tmp;

      assert(cl->mother_name != NULL);
      tmp = findcorpus(cl->mother_name, SYSTEM, 0);
      if (tmp == NULL) {
        cqpmessage(Error, "Can't find implicitly activated corpus %s. Undump aborted.\n", cl->mother_name);
        return 0;
      }
      cl = tmp;
    } /* cl now points to the currently active system corpus */
  }

  new = make_temp_corpus(cl, "UNDUMP_TMP"); /* create temporary subcorpus that will hold the undump data */
  assert((new != NULL) && "failed to create temporary query result for undump");

  if (! open_input_stream(rd)) { /* open input file, input pipe, or read from stdin */
    /* error message should printed by open_input_stream() */
    drop_temp_corpora();
    return 0;
  }

  ok = 0; /* read undump table header = number of rows */
  if (fgets(line, CL_MAX_LINE_LENGTH, rd->stream)) {
    if (1 == sscanf(line, "%d %s", &size, junk)) {
      ok = 1;
    }
    else if (2 == sscanf(line, "%d %d", &match, &matchend)) {
      /* looks like undump file without line count => determine number of lines */
      if (rd->stream == stdin) {
        cqpmessage(Warning, "You must always provide an explicit line count if undump data is entered manually (i.e. read from STDIN)");
      }
      else {
        /* undump file without header: count lines, then reopen stream */
        size = 1; /* first line is already in buffer */
        while (fgets(line, CL_MAX_LINE_LENGTH, rd->stream))
          size++; /* dump files should not contain any long lines, so this count is correct */
        close_input_stream(rd);
        if (! open_input_stream(rd))
          cqpmessage(Warning, "Can't rewind undump file after counting lines: line count must be given explicitly");
        else
          ok = 1;
      }
    }
  }

  if (!ok) {
    cqpmessage(Error, "Format error in undump file: expecting number of rows on first line");
    close_input_stream(rd);
    drop_temp_corpora();
    return 0;
  }

  cl_free(new->range);                /* free previous match data (should only be one range for system corpus) */
  cl_free(new->sortidx);
  cl_free(new->targets);
  cl_free(new->keywords);

  new->size = size;                /* allocate space for required number of (match, matchend) pairs, targets, keywords */
  new->range = (Range *) cl_malloc(sizeof(Range) * size);
  if (extension_fields >= 1) new->targets = (int *) cl_malloc(sizeof(int) * size);
  if (extension_fields >= 2) new->keywords = (int *) cl_malloc(sizeof(int) * size);

  max_cpos = cl->mother_size - 1; /* check validity, ordering, and non-overlapping of match ranges */
  mark = -1;
  abort = 0;
  for (i = 0; (i < size) && !abort; i++) {        /* now read one data row at a time from the undump table */
    if (feof(rd->stream)
        || (!fgets(line, CL_MAX_LINE_LENGTH, rd->stream)) /* parse input line format */
        || (sscanf(line, "%d %d %d %d %s", &match, &matchend, &target, &keyword, junk)
            != (2 + extension_fields))
        ) {
      cqpmessage(Error, "Format error in undump file (row #%d)", i+1);
      abort = 1;
      break;
    }

    if (matchend < match) {        /* check validity of match .. matchend range */
      cqpmessage(Error, "match (%d) must be <= matchend (%d) on row #%d", match, matchend, i+1);
      abort = 1;
    }
    else if (match < 0 || matchend > max_cpos) {
      cqpmessage(Error, "match (%d .. %d) out of range (0 .. %d) on row #%d", match, matchend, max_cpos, i+1);
      abort = 1;
    }
    else if ((! sort_ranges) && (match <= mark)) { /* current range must start _after_ end of previous range (unless sort_ranges==1) */
      cqpmessage(Error, "matches must be sorted and non-overlapping\n\t"
                 "match (%d) on row #%d overlaps with previous matchend (%d)", match, i+1, mark);
      abort = 1;
    }
    else {
      new->range[i].start = match;
      new->range[i].end = matchend;
      mark = matchend;
    }

    if (extension_fields >= 1) { /* check validity of target position if specified */
      if (target < -1 || target > max_cpos) {
        cqpmessage(Error, "target (%d) out of range (0 .. %d) on row #%d", target, max_cpos, i+1);
        abort = 1;
      }
      else
        new->targets[i] = target;
    }

    if (extension_fields >= 2) { /* check validity of keyword position if specified */
      if (keyword < -1 || keyword > max_cpos) {
        cqpmessage(Error, "keyword (%d) out of range (0 .. %d) on row #%d", keyword, max_cpos, i+1);
        abort = 1;
      }
      else
        new->keywords[i] = keyword;
    }
  }

  if (abort) {                         /* when an error was detected, don't create the named query result */
    close_input_stream(rd);
    drop_temp_corpora();
    return 0;
  }

  if (! close_input_stream(rd)) { /* ignore trailing junk etc. */
    cqpmessage(Warning, "There may be errors in the undump data. Please check the named query result %s.\n", corpname);
  }

  if (sort_ranges) {        /* if ranges aren't sorted in natural order, they must be re-ordered so that CQP can work with them  */
    RangeSort(new, 1);      /* however, a sortidx is automatically constructed to reproduce the ordering of the input file */
  }
  new = assign_temp_to_sub(new, corpname); /* copy the temporary subcorpus to the requested query name */
  drop_temp_corpora();

  if (new == NULL) {
    cqpmessage(Error, "Couldn't assign undumped data to named query %s.\n");
    return 0;
  }
  return 1;
}



