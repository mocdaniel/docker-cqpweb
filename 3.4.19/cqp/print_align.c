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

#include "../cl/cl.h"

#include "concordance.h"

#include "cqp.h"
#include "options.h"
#include "corpmanag.h"
#include "print-modes.h"
#include "context_descriptor.h"

#include "ascii-print.h"
#include "html-print.h"
#include "sgml-print.h"
#include "latex-print.h"

/**
 * Flag for whether the "print_align" module has been initialised yet or not.
 */
static int module_init = 0;




/**
 * Global context descriptor used solely for the
 * printing of corresponding strings from an
 * aligned corpus.
 */
static ContextDescriptor AlignedCorpusCD;



/**
 * Initialises the alignment-printing module by setting up the
 * static global ContextDescriptor (internal to print_align module).
 *
 * @see AlignedCorpusCD
 */
void
init_align_module()
{
  if (module_init == 0) {
    initialize_context_descriptor(&AlignedCorpusCD);
    AlignedCorpusCD.left_type = WORD_CONTEXT;
    AlignedCorpusCD.right_type = WORD_CONTEXT;
    module_init++;
  }
}




/* TODO
 * THIS SHOULD REALLY BE REWRITTTEN SO THAT THE ALIGNED CORPUS, ITS ATTRIBUTES, AND
 * THE (ALIGNED) CD ARE ONLY INITIALISED ONCE (FOR EACH CAT COMMAND)
 */
/**
 * For a given query result, prints the corresponding section of the
 * aligned corpus (if any).
 *
 * This function is the business-end of the "print_align" module.
 * @param sourceCorpus  The corpus the query was run on.
 * @param cd            ContextDescriptor containing data on how the concordance is to be printed.
 * @param begin_target  Starting cpos of the result being printed.
 * @param end_target    Ending cpos of the result being printed.
 * @param highlighting  Boolean: Iff true, highlighting will be used
 *                      (applies only in ASCII print mode; see "ascii_print_aligned_line").
 * @param stream        Output destination stream (will be printed to).
 */
void
printAlignedStrings(Corpus *sourceCorpus,
                    ContextDescriptor *cd,
                    int begin_target,
                    int end_target,
                    int highlighting,
                    FILE *stream)
{
  AttributeInfo *ai, *Sai, *Tai;
  Attribute *alat;
  Corpus *alignedCorpus;

  int dummy;

  if (!module_init)
    init_align_module();

  if (cd->alignedCorpora == NULL || cd->alignedCorpora->list == NULL)
    return;

  for (ai = cd->alignedCorpora->list; ai && ai->name; ai = ai->next) {

    /* TODO add a comment here explaining why we check ai->status */
    if (ai->status) {

      /* get the corpus positions for the aligned  */
      if ((alat = cl_new_attribute(sourceCorpus, ai->name, ATT_ALIGN))
          &&
          /* if it isn't already there, load it (cl_new_corpus() will just increment the refcount, if the corpus is already loaded) */
          (alignedCorpus = cl_new_corpus(registry, ai->name))
          ) {

        int alg1, alg2, alg_start, alg_end;
        char *s = NULL;
        int sanitise_aligned_data = 0; /* bool: if true, allow only ascii chars in aligned output */

        /* Do we need to recode contents of the aligned corpus? */
        {
          /* the "comparison" charset is never ascii, this is to make sure that utf8/ascii corpora are interchangeable both ways */
          /* -- NB: if output is via a pager, LESSCHARSET will be set to UTF8 if the source corpus is ascii; vide cqp/output.c */
          CorpusCharset compare = (sourceCorpus->charset == ascii ? utf8 : sourceCorpus->charset);
          if (alignedCorpus->charset != ascii && alignedCorpus->charset != compare)
            sanitise_aligned_data = 1;
        }

        alg1 = cl_cpos2alg(alat, begin_target);
        alg2 = cl_cpos2alg(alat, end_target);

        if (alg1 < 0 ||
            alg2 < 0 ||
            !cl_alg2cpos(alat, alg1, &dummy, &dummy, &alg_start, &dummy) ||
            !cl_alg2cpos(alat, alg2, &dummy, &dummy, &dummy, &alg_end)  ||
            alg_end < alg_start
            ) {
          s = cl_strdup("(no alignment found)");
          /* so after here, s != NULL signifies error or no alignment found;
           * this is checked before attempting to build alignment strings below. */
        }

        /* For some obscure reason, the AlignedCorpusCD sometimes gets corrupted
         * from outside; so we re-initialize for every aligned corpus we print. */
        initialize_context_descriptor(&AlignedCorpusCD);
        update_context_descriptor(alignedCorpus, &AlignedCorpusCD);

        /* How about this: Try to show the same attributes in this corpus
           as in the aligned corpus */
        if (cd->attributes)
          /* positional attributes */
          for (Sai = cd->attributes->list; Sai && Sai->name; Sai = Sai->next)
            if ((Tai = FindInAL(AlignedCorpusCD.attributes, Sai->name)))
              Tai->status = Sai->status;

        if (cd->strucAttributes)
          /* structural attributes */
          for (Sai = cd->strucAttributes->list; Sai && Sai->name; Sai = Sai->next)
            if ((Tai = FindInAL(AlignedCorpusCD.strucAttributes, Sai->name)))
              Tai->status = Sai->status;

        /* printing structural attribute values in the aligned regions doesn't
         * seem to make a lot of sense, so we stick with the first two options */

        switch (GlobalPrintMode) {
        case PrintASCII:
        case PrintUNKNOWN:
          if (s == NULL) {
            s = compose_kwic_line(alignedCorpus,
                                  alg_start, alg_end,
                                  &AlignedCorpusCD,
                                  &dummy,
                                  &dummy, &dummy,
                                  NULL, NULL,
                                  NULL, 0, NULL,
                                  NULL, 0,
                                  ConcLineHorizontal,
                                  &ASCIIPrintDescriptionRecord,
                                  0, NULL);
            if (s && sanitise_aligned_data)
              cl_string_validate_encoding(s, ascii, 1);
          }
          ascii_print_aligned_line(stream, highlighting, ai->name, s ? s : "(null)");
          break;

        case PrintSGML:
          if (s == NULL) {
            s = compose_kwic_line(alignedCorpus,
                                  alg_start, alg_end,
                                  &AlignedCorpusCD,
                                  &dummy,
                                  &dummy, &dummy,
                                  NULL, NULL,
                                  NULL, 0, NULL,
                                  NULL, 0,
                                  ConcLineHorizontal,
                                  &SGMLPrintDescriptionRecord,
                                  0, NULL);
            if (s && sanitise_aligned_data)
              cl_string_validate_encoding(s, ascii, 1);
          }
          sgml_print_aligned_line(stream, ai->name, s ? s : "(null)");
          break;

        case PrintHTML:
          if (s == NULL) {
            s = compose_kwic_line(alignedCorpus,
                                  alg_start, alg_end,
                                  &AlignedCorpusCD,
                                  &dummy,
                                  &dummy, &dummy,
                                  NULL, NULL,
                                  NULL, 0, NULL,
                                  NULL, 0,
                                  ConcLineHorizontal,
                                  &HTMLPrintDescriptionRecord,
                                  0, NULL);
            if (s && sanitise_aligned_data)
              cl_string_validate_encoding(s, ascii, 1);
          }
          html_print_aligned_line(stream, ai->name, s ? s : "(null)");
          break;

        case PrintLATEX:
          if (s == NULL) {
            s = compose_kwic_line(alignedCorpus,
                                  alg_start, alg_end,
                                  &AlignedCorpusCD,
                                  &dummy,
                                  &dummy, &dummy,
                                  NULL, NULL,
                                  NULL, 0, NULL,
                                  NULL, 0,
                                  ConcLineHorizontal,
                                  &LaTeXPrintDescriptionRecord,
                                  0, NULL);
            if (s && sanitise_aligned_data)
              cl_string_validate_encoding(s, ascii, 1);
          }
          latex_print_aligned_line(stream, ai->name, s ? s : "(null)");
          break;

        case PrintBINARY:
          /* don't display anything */
          break;

        default:
          assert(0 && "Unknown print mode");
          break;
        }

        cl_free(s);
        /* don't drop the aligned corpus even if we're the only one using it;
         * this may waste some memory, but otherwise we'd keep loading
         * and unloading the thing */
      }
    }
  }
}


