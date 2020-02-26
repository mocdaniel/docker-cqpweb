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

#ifndef _CONTEXT_DESCRIPTOR_H_
#define _CONTEXT_DESCRIPTOR_H_


#include "attlist.h"

/* The following constant define flags for the four different ways of measuring context-width:
 * we now have an enum, but we haven't yet removed thesemacros ; so, TODO */

#define CHAR_CONTEXT  -1
#define WORD_CONTEXT  -2
#define STRUC_CONTEXT -3
#define ALIGN_CONTEXT -4

typedef enum _context_type {
  /** Context width measured in characters */
  char_context = CHAR_CONTEXT,
  /** Context width measured in tokens */
  word_context = WORD_CONTEXT,
  /** Context width measured in terms of an s-attribute */
  s_att_context = STRUC_CONTEXT,
  /** Context width measured in terms of an a-attribute - that is, alignment blocks as the unit of context */
  a_att_context = ALIGN_CONTEXT

} context_type;

/**
 * ContextDescriptor class object: a bundle of CQP options
 * describing how a list of corpus positions is to be
 * displayed in a concordance: with left context,
 * with right context, with what attributes, etc.
 *
 * It is passed around between different print functions
 * so that they know what to do!
 *
 * Note that the options contained here are settable
 * by the user. This is in contrast to the "options"
 * held in the PrintDecriptionRecord, which are built-in
 * for each print style; the user can choose among modes
 * but cannot modify the settings individually.
 *
 * TODO This object is confusingly named, as it DOES NOT
 * merely specify the "Context" size; it also specifies
 * which attributes get printed, and so on.
 *
 * (It would be better called a "concordance line co-text
 * configuration object". Or even just a ConcordanceDescriptor.)
 *
 * @see PrintDescriptionRecord
 * @see PrintOptions
 *
 * TODO why is it necessary for concordance-printing
 * options to be spread across three separate objects
 * and multiple global variables to boot?
 */
typedef struct {

  /* ==================== left context scope description variables */

  int left_width;                    /**< Amount of context to show before the match, in units specified by left_type */
  context_type left_type;            /**< Unit in which context is measured;
                                          Set to one of the constants: CHAR_CONTEXT, WORD_CONTEXT, STRUC_CONTEXT, ALIGN_CONTEXT */
  /* TODO use an enum instead (change so far partially implemented) */
  /* TODO Is being able to have the left co-text measured in words and the right context in (say) paragraphs something we really are bovvered about? */

  char *left_structure_name;
  Attribute *left_structure;

  /* ==================== right context scope description variables */

  int right_width;                   /**< Amount of context to show after the match, in units specified by right_type */
  context_type right_type;           /**< Unit in which context is measured;
                                          Set to one of the constants: CHAR_CONTEXT, WORD_CONTEXT, STRUC_CONTEXT, ALIGN_CONTEXT */
  char *right_structure_name;
  Attribute *right_structure;

  /** Boolean flag: if true, print corpus position numbers */
  int print_cpos;

  /* ==================== lists of attributes of different types to print */

  AttributeList *attributes;         /**< positional attributes to print */
  AttributeList *strucAttributes;    /**< structural attributes to print */
  AttributeList *printStructureTags; /**< structure tag (values) to print */
  AttributeList *alignedCorpora;     /**< aligned corpora from which to print parallel data */

} ContextDescriptor;


/* ContextDescriptor class methods */

/* TODO not much naming convention stability among these descriptors! */

int verify_context_descriptor(Corpus *corpus,
                              ContextDescriptor *cd,
                              int remove_illegal_entries);

int initialize_context_descriptor(ContextDescriptor *cd);

int update_context_descriptor(Corpus *corpus, ContextDescriptor *cd);

ContextDescriptor *NewContextDescriptor(void);

void PrintContextDescriptor(ContextDescriptor *cdp);

#endif
