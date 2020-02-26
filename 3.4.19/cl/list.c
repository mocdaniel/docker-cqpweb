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

#include "globals.h"
#include "list.h"



/**
 * Default size of steps (lumps) by which auto-growing lists
 * are extended.
 *
 * List functions automatically extend lists in steps of at
 * least LUMPSIZE cells (to avoid too frequent reallocs).
 *
 * This can be configured on a per-list-object basis
 * with cl_int_list_lumpsize() and cl_string_list_lumpsize().
 *
 * @see cl_int_list_lumpsize
 * @see cl_string_list_lumpsize
 * @see _cl_int_list::lumpsize
 * @see _cl_string_list::lumpsize
 */
#define LUMPSIZE 64


/* ===================================================================  INT LIST FUNCTIONS */

/**
 * Creates a new cl_int_list object.
 */
cl_int_list
cl_new_int_list(void) {
  cl_int_list l = cl_malloc(sizeof(struct _cl_int_list));
  l->data = cl_calloc(LUMPSIZE, sizeof(int));
  l->allocated = LUMPSIZE;
  l->size = 0;
  l->lumpsize = LUMPSIZE;
  return l;
}

/**
 * Deletes a cl_int_list object.
 */
void
cl_delete_int_list(cl_int_list l) {
  cl_free(l->data);
  cl_free(l);
}

/**
 * Sets the lumpsize of a cl_int_list object.
 *
 * @see LUMPSIZE
 * @param l  The cl_int_list.
 * @param s  The new lumpsize.
 */
void
cl_int_list_lumpsize(cl_int_list l, int s) {
  if (s >= LUMPSIZE) {          /* lumpsize may not be smaller than default */
    l->lumpsize = s;
  }
}

/**
 * Gets the current size of a cl_int_list object (number of elements on the list).
 */
int
cl_int_list_size(cl_int_list l) {
  return l->size;
}

/**
 * Retrieves an element from a cl_int_list object.
 *
 * @param l  The list to search.
 * @param n  The element to retrieve.
 * @return   The n'th integer on the list, or 0 if there
 *           is no n'th integer.
 */
int
cl_int_list_get(cl_int_list l, int n) {
  if (n < 0 || n >= l->size) {
    return 0;
  }
  else {
    return l->data[n];
  }
}

/**
 * Sets an integer on a cl_int_list object.
 *
 * The n'th element on the list is set to val, and the
 * list is auto-extended if necessary.
 */
void
cl_int_list_set(cl_int_list l, int n, int val) {
  int newalloc, i;

  if (n < 0) {
    return;
  }
  else {
    if (n >= l->size) {
      l->size = n+1;
      /* auto-extend list if necessary */
      if (l->size > l->allocated) {
        newalloc = l->size;
        if ((newalloc - l->allocated) < l->lumpsize) {
          newalloc = l->allocated + l->lumpsize;
        }
        l->data = cl_realloc(l->data, newalloc * sizeof(int));
        for (i = l->allocated; i < newalloc; i++) {
          l->data[i] = 0;
        }
        l->allocated = newalloc;
      }
    }
    /* now we can safely set the desired list element */
    l->data[n] = val;
  }
}

/**
 * Appends an integer to the end of a cl_int_list object.
 */
void
cl_int_list_append(cl_int_list l, int val) {
  cl_int_list_set(l, l->size, val);
}

/** comparison function for int list sort : non-exported */
int
cl_int_list_intcmp(const void *a, const void *b) {
  return (*(int *)a - *(int *)b);
}

/**
 * Sorts a cl_int_list object.
 *
 * The list of integers are sorted into ascending order.
 */
void
cl_int_list_qsort(cl_int_list l) {
  qsort(l->data, l->size, sizeof(int), cl_int_list_intcmp);
}


/* ===================================================================  STRING LIST FUNCTIONS */

/**
 * Creates a new cl_string_list object.
 */
cl_string_list
cl_new_string_list(void) {
  cl_string_list l = cl_malloc(sizeof(struct _cl_string_list));
  l->data = cl_calloc(LUMPSIZE, sizeof(char *));
  l->allocated = LUMPSIZE;
  l->size = 0;
  l->lumpsize = LUMPSIZE;
  return l;
}

/**
 * Deletes a cl_string_list object.
 */
void
cl_delete_string_list(cl_string_list l) {
  cl_free(l->data);
  cl_free(l);
}

/**
 * Frees all the strings in the cl_string_list object.
 */
void
cl_free_string_list(cl_string_list l) {
  int i;

  for (i = 0; i < l->size; i++) {
    cl_free(l->data[i]);        /* cl_free() checks if pointer is NULL */
  }
}

/**
 * Sets the lumpsize of a cl_string_list object.
 *
 * @see LUMPSIZE
 * @param l  The cl_string_list.
 * @param s  The new lumpsize.
 */
void
cl_string_list_lumpsize(cl_string_list l, int s) {
  if (s >= LUMPSIZE) {          /* lumpsize may not be smaller than default */
    l->lumpsize = s;
  }
}

/**
 * Gets the current size of a cl_string_list object (number of elements on the list).
 */
int
cl_string_list_size(cl_string_list l) {
  return l->size;
}

/**
 * Retrieves an element from a cl_string_list object.
 *
 * @param l  The list to search.
 * @param n  The element to retrieve.
 * @return   The n'th string on the list, or NULL if there
 *           is no n'th string. Note that the returned pointer
 *           references the ACTUAL DATA in the list - not a
 *           copy, if you want a copy you must make one yourself.
 */
char *
cl_string_list_get(cl_string_list l, int n) {
  if (n < 0 || n >= l->size) {
    return NULL;
  }
  else {
    return l->data[n];
  }
}

/**
 * Sets a string pointer on a cl_string_list object.
 *
 * The n'th element on the list is set to val, and the
 * list is auto-extended if necessary.
 */
void
cl_string_list_set(cl_string_list l, int n, char *val) {
  int newalloc, i;

  if (n < 0) {
    return;
  }
  else {
    if (n >= l->size) {
      l->size = n+1;
      /* auto-extend list if necessary */
      if (l->size > l->allocated) {
        newalloc = l->size;
        if ((newalloc - l->allocated) < l->lumpsize) {
          newalloc = l->allocated + l->lumpsize;
      }
        l->data = cl_realloc(l->data, newalloc * sizeof(char *));
        for (i = l->allocated; i < newalloc; i++) {
          l->data[i] = NULL;
        }
        l->allocated = newalloc;
      }
    }
    /* now we can safely set the desired list element */
    l->data[n] = val;
  }
}

/**
 * Appends a string pointer to the end of a cl_string_list object.
 */
void
cl_string_list_append(cl_string_list l, char *val) {
  cl_string_list_set(l, l->size, val);
}

/** comparison function for string list sort : non-exported */
int
cl_string_list_strcmp(const void *a, const void *b) {
  return cl_strcmp(*(char **)a, *(char **)b); /*, I think. */
}

/**
 * Sorts a cl_string_list object.
 *
 * The list of strings is sorted using cl_strcmp().
 *
 * @see cl_strcmp
 */
void
cl_string_list_qsort(cl_string_list l) {
  qsort(l->data, l->size, sizeof(char *), cl_string_list_strcmp);
}

