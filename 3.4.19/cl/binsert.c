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
#include "binsert.h"

/**
 * Memory reallocation threshold for binserting.
 *
 * This threshold applies to "tables" manipulated with binsert_g(). When
 * the memory for these "tables" is allocated/reallocated, this is done
 * in increments of REALLOC_THRESHOLD elements. So in theory reallocation
 * will not be need more than once every REALLOC_THRESHOLD times the
 * binsert_g() function is called.
 *
 * @see binsert_g
 */
#define REALLOC_THRESHOLD 16



/*
 * DEFINITIONS BELOW SHOULD NOT BE NECESSARY ON MODERN UNIX SYSTEMS
 * Much better to use memmove, memcpy etc than the non-ANSI bincpy!
 */
/* #else /\* not __svr4__ *\/ */
/* #define memmove(dest,src,bytes) bcopy((char *)src, (char *)dest, (size_t) bytes) */
/* #extern void bcopy(char *b1, char *b2, int length); */
/* #endif /\* ifdef __svr4__ *\/ */



/**
 *
 * Inserts an element into the table of elements at base.
 *
 * If base is NULL, a new "table" is created, and a single
 * element copied into it.
 *
 * The memory of this table of elements will be reallocated
 * if necessary.
 *
 * How to call this function:
 *
 * binsert_g(&nr,
 *           (void **)&Table,
 *           &Nr_Elements,
 *           sizeof(int),
 *           intcompare);
 *
 * @param key     Pointer to the element to add
 * @param base    Location of pointer to the table
 * @param nel     Number of elements (will be incremented by this function)
 * @param size    The size of each element in the table.
 * @param compar  Comparison function (returns int, takes two pointers as arguments)
 * @return        Address of the (new) element
 */
void *
binsert_g(const void *key,
          void **base,
          size_t *nel,
          size_t size,
          int (*compar)(const  void  *,  const  void *))
{
  int low, high, found, mid, comp;

  if (*base == NULL) {
    *base = cl_malloc(size * REALLOC_THRESHOLD);
    memmove(*base, key, size);
    *nel = 1;
    return *base;
  }

  low = 0;
  high = *nel - 1;
  found = 0;
  mid = 0;
  comp = 0;

  while (low <= high && !found) {
    mid = (low + high)/2;
    comp = (*compar)(*base + (mid * size), key);

    if (comp < 0)
      low = mid + 1;
    else if (comp > 0)
      high = mid - 1;
    else
      found = 1;
  }

  if (found)
    return *base + (mid * size); /* address of element */
  else {

    int ins_pos;

    if (comp < 0)
      ins_pos = mid + 1;
    else
      ins_pos = mid;

    if (*nel % REALLOC_THRESHOLD == 0)
      (*base) = (void *)cl_realloc(*base, size * (*nel + REALLOC_THRESHOLD) );

    /* shift the elements from the insertion position to the right */
    if (ins_pos < *nel)
      memmove(*base + ((ins_pos+1) * size),
              *base + ((ins_pos)   * size),
              (*nel - ins_pos) * size);

    memmove(*base + (ins_pos * size), key, size);
    *nel = *nel + 1;

    return *base + (ins_pos * size); /* address of new element */
  }
}


