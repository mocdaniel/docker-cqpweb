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

#include "../cl/cl.h"

#include "attlist.h"

/**
 * Creates a new AttributeList.
 *
 * @param element_type  What type of attribute is this? ATT_POS, ATT_STRUC, etc.
 * @return              Pointer to the new AttributeList object.
 */
AttributeList *
NewAttributeList(int element_type)
{
  AttributeList *list;

  list = (AttributeList *)cl_malloc(sizeof(AttributeList));

  list->list = NULL;
  list->element_type = element_type;
  list->list_valid = 0;

  return list;
}

/**
 * Deletes an AttributeList object.
 *
 * @param list  Address of the pointer to the list to delete.
 * @return      Always 1.
 */
int
DestroyAttributeList(AttributeList **list)
{
  AttributeInfo *ai;

  /* first, deallocate all members of the list */

  ai = (*list)->list;
  while (ai) {
    AttributeInfo *ai2 = ai;
    ai = ai->next;
    cl_free(ai2->name);
    cl_free(ai2);
  }
// am prety sure this is unnecessary
//  (*list)->list = NULL;
//  (*list)->list_valid = 0;
//  (*list)->element_type = 0;

  cl_free(*list);

  return 1;
}


/**
 * Adds a new AttributeInfo to an AttributeList object.
 *
 * @param list            The list to add to.
 * @param name            The name of the Attribute that this AttributeInfo refers to.
 * @param initial_status  Initial setting for the status member of the new AttributeInfo.
 * @param position        If this is 1, the new AttributeInfo is added at the beginning
 *                        of the list. If it is 0, it is added at the end of the list.
 *                        Otherwise, this specifies a particular insertion position
 *                        (the given number of steps down the linked list).
 * @return                A pointer to the new AttributeInfo, or NULL for error.
 */
AttributeInfo
*AddNameToAL(AttributeList *list,
             char *name,
             int initial_status,
             int position)
{
  if (MemberAL(list, name))
    return NULL;
  else {
    AttributeInfo *ai = (AttributeInfo *)cl_malloc(sizeof(AttributeInfo));

    ai->status = initial_status;
    ai->name = cl_strdup(name);
    ai->attribute = NULL;
    ai->next = NULL;
    ai->prev = NULL;

    if (list->list == NULL)
      list->list = ai;
    else {
      if (position == 1) {
        /* insertion at beginning */
        ai->next = list->list;
        list->list = ai;
      }
      else if (position == 0) {
        /* insert new element at end of list */
        AttributeInfo *prev = list->list;

        while (prev->next)
          prev = prev->next;

        ai->prev = prev;
        prev->next = ai;
      }
      else {
        /* insert new element at certain position */
        AttributeInfo *prev = list->list;

        while (prev->next && position > 2) {
          prev = prev->next;
          position--;
        }

        ai->prev = prev;
        ai->next = prev->next;

        prev->next->prev = ai;
        prev->next = ai;
      }
    }

    /* return the new element */
    list->list_valid = 0;
    return ai;
  }
}


/**
 * Deletes an AttributeInfo from the AttributeList.
 *
 * @param list   The list from which to delete.
 * @param name   The name of the AttributeInfo to delete.
 * @return       True if the attribute info was found and deleted;
 *               otherwise false.
 */
int
RemoveNameFromAL(AttributeList *list, char *name)
{
  AttributeInfo *curr, *prev;

  if (list->list) {
    prev = NULL;
    curr = list->list;

    while (curr && !CL_STREQ(curr->name, name)) {
      prev = curr;
      curr = curr->next;
    }

    if (curr) {
      /* curr now points to the member with the given name.  unchain it! */
      if (prev == NULL) {
        /* this is first element of attribute list */
        list->list = curr->next;
        if (curr->next)
            curr->next->prev = list->list;
      }
      else {
        prev->next = curr->next;
        if (curr->next)
            curr->next->prev = prev;
      }

      cl_free(curr->name);
      cl_free(curr);
      return 1;
    }
    else
      /* not a member of the list */
      return 0;
  }
  else
    /* list is empty, nothing to do. */
    return 0;
}


/**
 * Deletes an AttributeInfo from the AttributeList.
 *
 * Unlike RemoveNameFromAL(), this function deletes an AttributeInfo
 * known by its pointer, rather than one known by its name.
 *
 * @see RemoveNameFromAL
 * @param list      The list from which to delete.
 * @param delendum  The address (pointer) of the AttributeInfo to delete.
 * @return          True if the attribute info was found and deleted;
 *                  otherwise false.
 */
int
Unchain(AttributeList *list, AttributeInfo *delendum)
{
  AttributeInfo *curr, *prev;
  curr = delendum;

  if (list && list->list && curr) {
    if (curr == list->list) {
      list->list = curr->next;
      if (list->list)
        list->list->prev = NULL;
    }
    else {
      prev = list->list;
      while (prev && prev->next != curr)
        prev = prev->next;

      if (prev) {
        prev->next = curr->next;
        if (prev->next)
          prev->next->prev = prev;
      }
      else
        curr = NULL;
    }

    if (curr) {
      cl_free(curr->name);
      cl_free(curr);
      return 1;
    }
    else
      return 0;
  }
  else
    return 0;
}

/**
 * Gets the number of entries in an AttributeList.
 *
 * @param list  The list to size up.
 * @return      The number of elements in the list.
 */
int
NrOfElementsAL(AttributeList *list)
{
  int n = 0;
  AttributeInfo *curr= list->list;

  while (curr) {
    n++;
    curr = curr->next;
  }

  return n;
}


/**
 * Checks whether the AttributeList contains an entry with the given attribute name.
 *
 * @param list  The list to search.
 * @param name  The name of the element to search for.
 * @return      Boolean: true if the element is found as a member of the list.
 */
int
MemberAL(AttributeList *list, char *name)
{
  return (FindInAL(list, name) ? 1 : 0);
}


/**
 * Finds an entry in an AttribvuteList.
 *
 * @param list  The list to search.
 * @param name  The name of the element to search for.
 * @return      Pointer to the AttributeInfo with the matching name,
 *              or NULL if the name was not found.
 */
AttributeInfo *FindInAL(AttributeList *list, char *name)
{
  AttributeInfo *curr;

  if (list && list->list) {
    curr = list->list;
    while (curr && strcmp(curr->name, name) != 0)
      curr = curr->next;
    return curr;
  }
  else
    return NULL;
}


/**
 * Goes through an AttributeList, deletes entries for attributes
 * that don't exist, and adds entries for those that do.
 *
 * Note that all AttributeInfo entries are linked to the actual Attribute objects by this function.
 *
 * As of CQP v3.4.18, this function re-creates the AttributeList from
 * scratch and copies the current display status of attributes with the
 * same name from the previous list. In this way it ensures that attributes
 * are always displayed in the same order in which they are listed in
 * the registry file.
 *
 * @param list         The AttributeList to recompute.
 * @param corpus       The corpus in which these attributes are found.
 * @param init_status  Not currently used.
 * @return             The updated AttributeList (which will have been re-allocated)
 */
AttributeList *
RecomputeAL(AttributeList *list, Corpus *corpus, int init_status)
{
  /* silly implementation, but usually short lists. so what... */

  Attribute *attr;
  AttributeInfo *ai, *prev;
  AttributeList *addition = NewAttributeList(list->element_type);

  if (corpus) {
    /* fill new AttributeList with suitable attribute type */
    for (attr = corpus->attributes; attr; attr = attr->any.next)
      if (attr->type == list->element_type) {
        ai = AddNameToAL(addition, attr->any.name, 0, 0);
        assert(ai); /* should never fail */
        ai->attribute = attr;
        if ((prev = FindInAL(list, ai->name)))
          ai->status = prev->status;
      }
  }

  addition->list_valid = 1; /* we have valid Attribute objects for all entries */
  DestroyAttributeList(&list);

  return addition;
}


/**
 * Verifies an AttributeList.
 *
 * Note that all AttributeInfo entries are linked to the actual Attribute objects by this function.
 *
 * If the relevant attribute cannot be found, the entry is deleted iff remove_illegal_entries.
 *
 * @param list                    The list to verify.
 * @param corpus                  The corpus for which this list should be valid.
 * @param remove_illegal_entries  Boolean: see function description.
 * @return                        Boolean: true for all OK, false for error.
 */
int
VerifyList(AttributeList *list,
           Corpus *corpus,
           int remove_illegal_entries)
{
  int result = 1;
  AttributeInfo *ai, *prev, *curr;

  if (!list)
    return 0;

  prev = NULL;
  ai = list->list;

  while (ai) {
    ai->attribute = cl_new_attribute(corpus, ai->name, list->element_type);
    curr = ai;
    ai = ai->next;

    if (curr->attribute == NULL) {
      if (remove_illegal_entries) {
        if (prev == NULL) {
          /* delete first element */
          list->list = ai;
          if (ai)
            ai->prev = NULL;
        }
        else {
          /* unchain this element */
          prev->next = ai;
          if (ai)
            ai->prev = prev;
        }

        cl_free(curr->name);
        cl_free(curr);
      }
      else
        result = 0;
    }
    else
      prev = curr;
  }

  list->list_valid = result;

  return result;
}

