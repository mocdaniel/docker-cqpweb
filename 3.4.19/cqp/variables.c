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
#include <string.h>
#include <assert.h>

#include "../cl/cl.h"
//#include "../cl/corpus.h"
//#include "../cl/attributes.h"
//#include "../cl/cdaccess.h"

#include "variables.h"

#include "output.h"
#include "options.h"


/* TODO: shrink malloced buffers as necessary - for VarSpace as well
   as for items in single vars */

/* ---------------------------------------------------------------------- */

/** How many pointers to allocate space for at one time to a Variable's  ->items array*/
#define ITEM_REALLOC 8

/** How many pointers to allocate space for at one time to the global array VariableSpace */
#define VARIABLE_REALLOC 16

/** Number of variables in VariableArray (exported)*/
int nr_variables = 0;

/** Global array of Variables  (exported) */
Variable *VariableSpace = NULL;


/* ---------------------------------------------------------------------- */

/**
 * Finds the Variable object of the given name, if it
 * exists in VariableSpace.
 *
 * @param varname  The name of the variable required.
 * @return         The Variable requested, or NULL if no Variable by that
 *                 name could be found.
 */
Variable
FindVariable(const char *varname)
{
  int i;

  for (i = 0; i < nr_variables; i++)
    if (VariableSpace[i] && CL_STREQ(VariableSpace[i]->my_name, varname))
      return VariableSpace[i];

  return NULL;
}

/**
 * Tests whether a given string exists within the variable.
 *
 * @return  Boolean: true if the variable contains the string.
 */
int
VariableItemMember(Variable v, const char *item)
{
  int i;

  for (i = 0; i < v->nr_items; i++)
    if (!v->items[i].free && CL_STREQ(v->items[i].sval, item))
      return 1;

  return 0;
}

/**
 * Adds a string to the variable.
 *
 * @return  Always 1.
 */
int
VariableAddItem(Variable v, const char *item)
{
  int i;

  if (!VariableItemMember(v, item)) {

    /* Since the contents of the variable changes here, it will no longer be valid against
     * any corpus / attrribute it has previously been checked against. */
    v->valid = 0;

    for (i = 0; i < v->nr_items; i++)
      if (v->items[i].free) {
        v->items[i].free = 0;
        v->items[i].sval = cl_strdup(item);
        v->items[i].ival = -1;
        break;
      }

    if (i >= v->nr_items) {
      /* no space in list. malloc. */
      v->nr_items += ITEM_REALLOC;
      if (v->items == NULL)
        v->items = (VariableItem *)cl_malloc(sizeof(VariableItem) * v->nr_items);
      else
        v->items = (VariableItem *)cl_realloc(v->items, sizeof(VariableItem) * v->nr_items);

      /* insert the new item into the FIRST newly allocated item; then set the REST as empty. */
      v->items[i].sval = cl_strdup(item);
      v->items[i].free = 0;
      v->items[i].ival = -1;

      for (i++ ; i < v->nr_items ; i++) {
        v->items[i].sval = NULL;
        v->items[i].free = 1;
        v->items[i].ival = -1;
      }
    }
  }
  return 1;
}

/**
 * Remove a string from a defined Variable.
 *
 * Identification of the string to remove is by bytewise identity.
 *
 * @param v     The variable.
 * @param item  The string to take out of the variable.
 * @return     Always 1
 */
int
VariableSubtractItem(Variable v, const char *item)
{
  int i;

  /* by altering the list, it is automatically no longer valid:
   * it will need to be rechecked if it has already been checked. */
  v->valid = 0;

  for (i = 0; i < v->nr_items; i++) {
    /* if this item is the string we want to remove, free the string, set the integer value to -1, and flag as free.
     * IE everything in the item gets scrubbed. */
    if (!v->items[i].free && v->items[i].sval != NULL && CL_STREQ(v->items[i].sval, item)) {
      cl_free(v->items[i].sval);
      v->items[i].ival = -1;
      v->items[i].free++;
    }
  }

  return 1;
}

/**
 * Deletes and frees up all memory associated with the strings contained by this variable.
 *
 * The variable continues to exist, but is now empty.
 */
int
VariableDeleteItems(Variable v)
{
  int i;

  /* first, free the strings. */
  for (i = 0; i < v->nr_items; i++)
    cl_free(v->items[i].sval);

  v->valid = 0;
  v->nr_items = 0;
  v->nr_valid_items = 0;
  v->nr_invalid_items = 0;
  cl_free(v->items);
  return 1;
}

/**
 * Deletes all the memory associated with a given variable.
 * If the Variable is in VariableSpace, that slot in VariableSpace is emptied out.
 *
 * @param vp  Note that this function takes a POINTER
 *            to a Variable object, not a Variable itself (even though
 *            Variable IS a pointer type)... allowing the object to be set
 *            to NULL once emptied out.
 * @return    Always 1.
 */
int
DropVariable(Variable *vp)
{
  int i;
  Variable v = *vp;

  VariableDeleteItems(v);

  cl_free(v->my_name);
  cl_free(v->my_corpus);
  cl_free(v->my_attribute);

  for (i = 0; i < nr_variables; i++)
    if (VariableSpace[i] == v) {
      VariableSpace[i] = NULL;
      break;
    }

  /* triggered if the variable object supplied is NOT in VariableSpace, which all Variables should be. */
  if (i >= nr_variables)
    fprintf(stderr, "Error #5 in variable logic. Please contact developer.\n");


  *vp = NULL;

  return 1;
}

/**
 * Creates a new Variable (set of strings) with the specified name within the global VariableSpace.
 *
 * Returns NULL only if the variable string name was NULL.
 */
Variable
NewVariable(const char *varname)
{
  Variable v;
  int i;

  /* the caller may or may not have checked this. */
  if (varname == NULL)
    return NULL;

  v = (Variable)cl_malloc(sizeof(VariableBuffer));
  v->valid = 0;
  v->my_name = cl_strdup(varname);
  v->my_corpus = NULL;
  v->my_attribute = NULL;
  v->nr_items = 0;
  v->items = NULL;

  for (i = 0; i < nr_variables; i++) {
    if (VariableSpace[i] == NULL) {
      VariableSpace[i] = v;
      break;
    }
  }

  if (i >= nr_variables) {
    /* not inserted, malloc */

    nr_variables += VARIABLE_REALLOC;

    if (VariableSpace == NULL)
      VariableSpace = (Variable *)cl_malloc(nr_variables * sizeof(Variable));
    else
      VariableSpace = (Variable *)cl_realloc(VariableSpace, nr_variables * sizeof(Variable));

    VariableSpace[i++] = v;

    for ( ; i < nr_variables; i++)
      VariableSpace[i] = NULL;
  }

  return v;
}

/**
 * Alters the value of a Variable according to one of four possible operations.
 *
 * @param var_name  Variable to act on.
 * @param operator  One of + (add), - (remove), = (set ie overwrite), or < (add from file).
 * @param values    Whitespace delimited list of items to set/add/remove;
 *                  or, a filename from which to read items, one per line.
 *                  The string storage may be destructively modified.
 * @return          True for success, false for problem.
 */
int
SetVariableValue(const char *varname, char operator, char *values)
{
  Variable v;
  char *item;
  FILE *src;
  char s[CL_MAX_LINE_LENGTH];

  if (!(v = FindVariable(varname))) {
    if (!(v = NewVariable(varname))) {
      cqpmessage(Error, "Bad variable name supplied!");
      return 0;
    }
  }

  switch (operator) {

  case '+':                        /* += operator: extend */
    item = strtok(values, " \t\n");
    while (item) {
      VariableAddItem(v, item);
      item = strtok(NULL, " \t\n");
    }
    break;

  case '-':                        /* -= operator: substract */
    item = strtok(values, " \t\n");
    while (item) {
      VariableSubtractItem(v, item);
      item = strtok(NULL, " \t\n");
    }
    break;

  case '=':                        /* = operator: absolute setting */
    VariableDeleteItems(v);
    item = strtok(values, " \t\n");
    while (item) {
      VariableAddItem(v, item);
      item = strtok(NULL, " \t\n");
    }
    break;

  case '<':                        /* < operator: extend with values from file */
    VariableDeleteItems(v);
    if (!(src = cl_open_stream(values, CL_STREAM_READ, (insecure) ? CL_STREAM_MAGIC_NOPIPE : CL_STREAM_MAGIC))) {
      cqpmessage(Warning, "Can't open %s: no such file or directory", values);
      return 0;
    }
    while (fgets(s, CL_MAX_LINE_LENGTH, src)) {
      /* remove trailing line break (LF or CR-LF) */
      cl_string_chomp(s);
      if (0 < strlen(s))
        VariableAddItem(v, s);
    }
    cl_close_stream(src);
    break;

  default:
    return 0;
  }

  return 1;
}


/*
 *  variables iterator: one non-exported integer and two functions.
 */
int variables_iterator_idx;

/**
 * Resets the global variables iterator to the beginning of the global VariableSpace array.
 */
void
variables_iterator_new(void)
{
  variables_iterator_idx = 0;
}

/**
 * Gets the next Variable object from the variables iterator.
 *
 * Returns NULL if the iterator has reached the end of the global VariableSpace array.
 *
 * @see VariableSpace
 * @return             The next Variable object from the iterator.
 */
Variable
variables_iterator_next(void)
{
  if (variables_iterator_idx < nr_variables)
    return VariableSpace[variables_iterator_idx++];
  else
    return NULL;
}


/**
 * Verify a variable for use with a given p-attribute of a given corpus.
 *
 * This associates the variable with the supplied corpus/attribute,
 * plus checks the variable's strings against the relevant attribute lexicon.
 *
 * @return  Boolean: true for all OK, false if something went wrong (problem with
 *          the arguments, or if none of the variable's strings match the
 *          lexicon). Same as the value of the Variable's "valid" flag after this
 *          function has run.
 */
int
VerifyVariable(Variable v, Corpus *corpus, Attribute *attribute)
{
  int i;
  char *str;

  /* nr valid = n of strings in the var that are also in the corpus lexicon. */
  int nr_valid, nr_invalid;

  /* only verify the variable if (a) it is not already verified,
   * or (b) it is verified, but for another corpus. */
  if ( (! v->valid) ||
      v->my_corpus == NULL || v->my_attribute == NULL ||
      strcmp(v->my_corpus, corpus->registry_name) != 0 ||
      strcmp(v->my_attribute, attribute->any.name) != 0) {

    v->valid = 0;
    cl_free(v->my_corpus);
    cl_free(v->my_attribute);

    if (attribute->any.type != ATT_POS)
      return 0;

    v->my_corpus    = cl_strdup(corpus->registry_name);
    v->my_attribute = cl_strdup(attribute->any.name);

    nr_valid = 0;
    nr_invalid = 0;

    for (i = 0; i < v->nr_items; i++) {
      /* check each string against the lexicon: store matching lexicon ID, if there is one. */
      if (!v->items[i].free) {
        if (v->items[i].sval == NULL) {
          /* string shouldn't be NULL if free has been set to True */
          fprintf(stderr, "Error #1 in variable logic. Contact developer.\n");
          v->items[i].ival = -1;
        }
        else {
          /* Variable strings are not verified on load - so we now need to check against the corpus charset. */
          if (!cl_string_validate_encoding(v->items[i].sval, corpus->charset, 0))
            cqpmessage(Error,
                "Variable $%s includes one or more strings with characters that are invalid\n"
                "in the encoding specified for corpus [%s]", v->my_name, v->my_corpus);
          /* In utf8: lookup against canonicalised string. Otherwise: lookup against the string as-is. */
          if (utf8 == corpus->charset) {
            str = cl_string_canonical(v->items[i].sval, corpus->charset, REQUIRE_NFC, CL_STRING_CANONICAL_STRDUP);
            v->items[i].ival = cl_str2id(attribute, str);
            cl_free(str);
          }
          else
            v->items[i].ival = cl_str2id(attribute, v->items[i].sval);
        }

        if (v->items[i].ival < 0)
          nr_invalid++;
        else
          nr_valid++;
      }
    }

    v->nr_valid_items   = nr_valid;
    v->nr_invalid_items = nr_invalid;

    if (nr_valid > 0)
      v->valid = 1;
    else
      v->valid = 0;
  }

  return v->valid;
}


/** comparison function for qsort() of id_list returned by GetVariableItems */
static
int intcompare(const void *i, const void *j)
{
  return(*(int *)i - *(int *)j);
}

/**
 * Get lexicon IDs of those of the Varaible's strings that are found in the lexicon of the given corpus.attribute.
 *
 * @param v         The Variable object.
 * @param corpus    The corpus we are working with.
 * @param attribute The attribute against which to verify the Variable's items
 * @param nr_items  This will be set to the number of integers in the returned array
 *                  (or 0 if the return value is NULL).
 * @return          Pointer to sorted block of integers based on valid items from the variable;
 *                  NULL if there were no valid items (i.e. items found in the attribute's lexicon).
 */
int *
GetVariableItems(Variable v,
                 Corpus *corpus,
                 Attribute *attribute,
                 int *nr_items)
{
  int *items;
  int i, ip; /* ip = index into the new "items" array, whereas i indexes the v->items array */

  *nr_items = 0;

  if (!VerifyVariable(v, corpus, attribute))
    return NULL;

  if (0 >= v->nr_valid_items)
    return NULL;

  items = (int *)cl_malloc(v->nr_valid_items * sizeof(int));
  *nr_items = v->nr_valid_items;

  ip = 0;
  for (i = 0; i < v->nr_items; i++)
    if (!v->items[i].free && v->items[i].ival >= 0) {
      assert (ip < v->nr_valid_items && "Error #2 in variable logic. Please contact developer.\n");
      items[ip] = v->items[i].ival;
      ip++;
    }

  assert (ip == v->nr_valid_items && "Error #3 in variable logic. Please contact developer.\n");

  /* eval_bool() expects a sorted list of IDs (for binary search) */
  qsort(items, *nr_items, sizeof(int), intcompare);
  return items;
}


/**
 * Returns an array of pointers to a Variable's strings.
 *
 * Return value is NULL if there were no strings stored in the variable.
 * The number of strings that were found is inserted into nr_items.
 *
 * The array that is returned must be freed by the caller.
 *
 * @param v         The Variable whose strings you want.
 * @param nr_items  The number of strings found will be put here.
 * @return          Table of pointers to the Variable's strings.
 *                  Or NULL if there weren't any. The table is
 *                  newly allocated but the string data isn't.
 */
char **
GetVariableStrings(Variable v, int *nr_items)
{
  char **result;
  int i, j, n_items_found;

  /* count number of items (strings) stored in variable, i.e. non-empty slots */
  n_items_found = 0;
  for (i = 0; i < v->nr_items; i++)
    if (!v->items[i].free)
      n_items_found++;

  *nr_items = n_items_found;

  if (n_items_found == 0)
    return NULL;

  /* allocate pointer table which will be returned */
  result = cl_malloc(n_items_found * sizeof(char *));

  /* copy pointers into result table;
     i = index into the variable, j = index into our table */
  j = 0;
  for (i = 0; i < v->nr_items; i++)
    if (!v->items[i].free)
      result[j++] = v->items[i].sval;

  return result;
}
