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

#include "../cl/cl.h"

#include "context_descriptor.h"
#include "output.h"
#include "options.h"



/**
 * Resets left context scope of a ContextDescriptor to default (25 chars).
 */
static void
context_descriptor_reset_left_context(ContextDescriptor *cd)
{
  if (cd) {
    cd->left_width = 25;
    cd->left_type = CHAR_CONTEXT;
    cl_free(cd->left_structure_name);
  }
}

/**
 * Resets right context scope of a ContextDescriptor to default (25 chars).
 */
static void
context_descriptor_reset_right_context(ContextDescriptor *cd)
{
  if (cd) {
    cd->right_width = 25;
    cd->right_type = CHAR_CONTEXT;
    cl_free(cd->right_structure_name);
  }
}

/**
 * Verify the current context settings against the current corpus:
 * check whether structures are still valid, and reset them to
 * defaults if not. returns 1 if all keeps the same, 0 otherwise. The
 * string fields in CD are supposed to be malloced and freed.
 */
int
verify_context_descriptor(Corpus *corpus,
                          ContextDescriptor *cd,
                          int remove_illegal_entries)
{
  int result = 1;

  if (cd == NULL) {
    fprintf(stderr, "verify_context_descriptor(): WARNING: Context Descriptor empty!\n");
    result = 0;
  }
  else if (corpus == NULL) {
    fprintf(stderr, "verify_context_descriptor(): WARNING: Corpus Descriptor empty!\n");
    context_descriptor_reset_left_context(cd);
    context_descriptor_reset_right_context(cd);
    cd->attributes = NULL;
    result = 0;
  }
  else {
    /* check left attribute */
    if (cd->left_type == STRUC_CONTEXT) {
      if (cd->left_structure_name == NULL) {
          context_descriptor_reset_left_context(cd);
        result = 0;
      }
      else {
        /* find (structural) attribute */
        if (!(cd->left_structure = cl_new_attribute(corpus, cd->left_structure_name, ATT_STRUC))) {
          /* not defined -> try alignment attribute */
          if (!(cd->left_structure = cl_new_attribute(corpus, cd->left_structure_name, ATT_ALIGN))) {
            /* error -> reset to default context */
            context_descriptor_reset_left_context(cd);
            result = 0;
          }
          else {
            /* alignment attribute found -> change context type to ALIGN_CONTEXT */
            cd->left_type = ALIGN_CONTEXT;
            if (cd->left_width != 1) {
              cqpmessage(Warning,
                         "Left Context '%d %s' changed to '1 %s' (alignment attribute).",
                         cd->left_width,
                         cd->left_structure_name,
                         cd->left_structure_name);
              cd->left_width = 1;
            }
          }
        }
      }
    }
    if (cd->left_width < 0) {
      fprintf(stderr, "concordance.o/verify_context_descriptor: WARNING: lwidth < 0\n");
      cd->left_width = -cd->left_width;
      result = 0;
    }

    /* check right attribute */
    if (cd->right_type == STRUC_CONTEXT) {
      if (cd->right_structure_name == NULL) {
          context_descriptor_reset_right_context(cd);
        result = 0;
      }
      else {
        /* find (structural) attribute */
        if (!(cd->right_structure = cl_new_attribute(corpus, cd->right_structure_name, ATT_STRUC))) {
          /* not defined -> try alignment attribute */
          if (!(cd->right_structure = cl_new_attribute(corpus, cd->right_structure_name, ATT_ALIGN))) {
            /* error -> reset to default context */
            context_descriptor_reset_right_context(cd);
            result = 0;
          }
          else {
            /* alignment attribute found -> change context type to ALIGN_CONTEXT */
            cd->right_type = ALIGN_CONTEXT;
            if (cd->right_width != 1) {
              cqpmessage(Warning,
                         "Right Context '%d %s' changed to '1 %s' (alignment attribute).",
                         cd->right_width,
                         cd->right_structure_name,
                         cd->right_structure_name);
              cd->right_width = 1;
            }
          }
        }
      }
    }
    if (cd->right_width < 0) {
      fprintf(stderr, "concordance.o/verify_context_descriptor: WARNING: lwidth < 0\n");
      cd->right_width = -cd->right_width;
      result = 0;
    }

    /* cd->print_cpos = 0; */

    VerifyList(cd->attributes, corpus, remove_illegal_entries);
    if (cd->attributes && cd->attributes->list == NULL)
      DestroyAttributeList(&(cd->attributes));

    VerifyList(cd->strucAttributes, corpus, remove_illegal_entries);
    if (cd->strucAttributes && cd->strucAttributes->list == NULL)
      DestroyAttributeList(&(cd->strucAttributes));

    VerifyList(cd->printStructureTags, corpus, remove_illegal_entries);
    if (cd->printStructureTags && cd->printStructureTags->list == NULL)
      DestroyAttributeList(&(cd->printStructureTags));

    VerifyList(cd->alignedCorpora, corpus, remove_illegal_entries);
    if (cd->alignedCorpora && cd->alignedCorpora->list == NULL)
      DestroyAttributeList(&(cd->alignedCorpora));
  }

  return result;
}

/**
 * Creates (and initialises) a ContextDescriptor object.
 */
ContextDescriptor *
NewContextDescriptor(void)
{
  ContextDescriptor *cd;

  cd = (ContextDescriptor *)cl_malloc(sizeof(ContextDescriptor));
  initialize_context_descriptor(cd);

  return cd;
}


/**
 * Initialises the member variables of a ContextDescriptor object to zero.
 *
 * Initial settings are: no attributes for printing, no right context,
 * no left context, no cpos printing.
 *
 * This can be called on either a statically or dynamically allocated CD.
 *
 * @see ContextDescriptor
 * @param  cd      The settings container to
 * @return         Always 1.
 */
int
initialize_context_descriptor(ContextDescriptor *cd)
{
  cd->left_width = 0;
  cd->left_type  = CHAR_CONTEXT;
  cd->left_structure = NULL;
  cd->left_structure_name = NULL;

  cd->right_width = 0;
  cd->right_type  = CHAR_CONTEXT;
  cd->right_structure = NULL;
  cd->right_structure_name = NULL;

  cd->print_cpos = 0;

  cd->attributes = NULL;
  cd->strucAttributes = NULL;
  cd->printStructureTags = NULL;
  cd->alignedCorpora = NULL;

  return 1;
}

/**
 * Imports lists of attributes (p-, s-, and a-) from a Corpus record
 * into a ContextDescriptor record (where they can then be used
 * as concordance display info).
 *
 * @param  corpus  The source of the settings.
 * @param  cd      The destination of the settings.
 * @return         Always 1.
 */
int
update_context_descriptor(Corpus *corpus, ContextDescriptor *cd)
{
  AttributeInfo *ai;

  if (!cd->attributes) {
    cd->attributes = NewAttributeList(ATT_POS);
    /* cd->print_cpos = 0; */
  }
  cd->attributes = RecomputeAL(cd->attributes, corpus, 0);

  if (!cd->strucAttributes)
    cd->strucAttributes = NewAttributeList(ATT_STRUC);
  cd->strucAttributes = RecomputeAL(cd->strucAttributes, corpus, 0);

  if (!cd->printStructureTags)
    cd->printStructureTags = NewAttributeList(ATT_STRUC);
  cd->printStructureTags = RecomputeAL(cd->printStructureTags, corpus, 0);

  if (!cd->alignedCorpora)
    cd->alignedCorpora = NewAttributeList(ATT_ALIGN);
  cd->alignedCorpora = RecomputeAL(cd->alignedCorpora, corpus, 0);

  /* remove s-attributes without annotation from printStructureTags */
  ai = cd->printStructureTags->list;
  while (ai) {
    Attribute *attr;
    AttributeInfo *next_ai = ai->next; /* removing the offending s-attribute invalidates ai, so need to remember ai->next now */

    attr = (cd->printStructureTags->list_valid) ? ai->attribute : cl_new_attribute(corpus, ai->name, ATT_STRUC);
    if (!attr || !cl_struc_values(attr))
      RemoveNameFromAL(cd->printStructureTags, ai->name);

    ai = next_ai;
  }

  return 1;
}


/**
 * This function implements the printing of a block of p
 * or s or a attributes used when a ContextDescriptor is
 * printed and pretty-print is active ("show cd").
 *
 * It is a non-exported function.
 *
 * The output to the stream is human readably, but not
 * easily parseable; the non-pretty version is what you
 * want on that front.
 *
 * @param fd             Stream to write to.
 * @param header         String with the header for the block of attributes.
 * @param al             The attribute list we are going to print out.
 * @param show_if_annot  Boolean; set to 1 if we are printing s-attributes
 *                       (to place "[A]" as a flag next to annotated s-atts).
 */
void
PrintAttributesPretty(FILE *fd, char *header, AttributeList *al, int show_if_annot)
{
  int line = 0, i;
  AttributeInfo *current;

  if (al && al->list) {
    for (current = al->list; current; current = current->next) {
      if (line++ == 0)
        fprintf(fd, "%s", header);
      else
        for (i = strlen(header); i; i--)
          fprintf(fd, " ");
      if (current->status)
        fprintf(fd, "  * ");
      else
        fprintf(fd, "    ");
      /* structural attributes only;
       * note we DEPEND on show_if_annot only being true iff al is a list of struc attributes,
       * otherwise calling cl_struc_values will cause a cl_error */
      if (!show_if_annot || !cl_struc_values(current->attribute))
        fprintf(fd, "%s\n", current->attribute->any.name);
      else
        fprintf(fd, "%-20s [A]\n", current->attribute->any.name);
    }
  }
  else
    fprintf(fd, "%s    <none>\n", header);
}

/**
 * This function implements the printing of a block of p
 * or s or a attributes used when a ContextDescriptor is
 * printed in non-pretty-print mode ("show cd").
 *
 * It is a non-exported function.
 *
 * The output to the stream is actually TSV in 4 columns:
 * (1) a caller-specified type label, invariant per call;
 * (2) the identifier of the attribute;
 * (3) an indicator of whether an s-attribute has values ;
 *     "-V" if it does, "" if it doesn't; always empty for
 *     a and p-attributes;
 * (4) whether the ContextDescriptor is currently set to print
 *     this attribute ("*" if yes, "" if no; the newline follows directly).
 *
 * @param fd             Stream to write to.
 * @param type           String that will be printed per line to label the attribute type.
 * @param al             The attribute list we are going to print out.
 * @param show_if_annot  Boolean; set to 1 if we are printing s-attributes
 *                       (to get a "-V" or "" in col. 3)
 */
void

PrintAttributesUnpretty(FILE *fd, char *type, AttributeList *al, int show_if_annot)
{
  AttributeInfo *ai;

  if (al)
    for (ai = al->list; ai ; ai = ai->next)
      fprintf(fd, "%s\t%s\t%s\t%s\n",
          type,
          ai->attribute->any.name,
          ((show_if_annot && cl_struc_values(ai->attribute)) ? "-V" : ""),
          (ai->status ? "*" : "")
          );
}

/**
 * Prints the contents of a ContextDescriptor either to stdout or a pager
 * (NB this uses its own internal stream).
 *
 * @param cdp       Context descriptor to print.
 */
void
PrintContextDescriptor(ContextDescriptor *cdp)
{
  FILE *fd;
  struct Redir rd = { NULL, NULL, NULL, 0 };        /* for paging (with open_stream()) */
  int stream_ok;

  if (cdp) {
    stream_ok = open_stream(&rd, ascii);
    fd = (stream_ok) ? rd.stream : stdout; /* use pager, or simply print to stdout if it fails */

    if (pretty_print) {
      fprintf(fd, "===Context Descriptor=======================================\n");
      fprintf(fd, "\n");
      fprintf(fd, "left context:     %d ", cdp->left_width);

      switch (cdp->left_type) {
      case char_context:
        fprintf(fd, "characters\n");
        break;
      case word_context:
        fprintf(fd, "tokens\n");
        break;
      case s_att_context:
      case a_att_context:
        fprintf(fd, "%s\n", cdp->left_structure_name ? cdp->left_structure_name : "???");
        break;
      }

      fprintf(fd, "right context:    %d ", cdp->right_width);

      switch (cdp->right_type) {
      case char_context:
        fprintf(fd, "characters\n");
        break;
      case word_context:
        fprintf(fd, "tokens\n");
        break;
      case s_att_context:
      case a_att_context:
        fprintf(fd, "%s\n", cdp->right_structure_name ? cdp->right_structure_name : "???");
        break;
      }
      fprintf(fd, "corpus position:  %s\n", cdp->print_cpos ? "shown" : "not shown");
      fprintf(fd, "target anchors:   %s\n", show_targets    ? "shown" : "not shown");
      fprintf(fd, "\n");
      PrintAttributesPretty(fd, "Positional Attributes:", cdp->attributes, 0);
      fprintf(fd, "\n");
      PrintAttributesPretty(fd, "Structural Attributes:", cdp->strucAttributes, 1);
      fprintf(fd, "\n");
      /*     PrintAttributes(fd, "Structure Values:     ", cdp->printStructureTags); */
      /*     fprintf(fd, "\n"); */
      PrintAttributesPretty(fd, "Aligned Corpora:      ", cdp->alignedCorpora, 0);
      fprintf(fd, "\n");
      fprintf(fd, "============================================================\n");
    }
    else {
      PrintAttributesUnpretty(fd, "p-Att", cdp->attributes, 0);
      PrintAttributesUnpretty(fd, "s-Att", cdp->strucAttributes, 1);
      PrintAttributesUnpretty(fd, "a-Att", cdp->alignedCorpora, 0);
    }

    if (stream_ok)
      close_stream(&rd);        /* close pipe to pager if we were using it */
  }
}


