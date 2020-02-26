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

#ifndef _cqp_output_h_
#define _cqp_output_h_

#include <stdio.h>
#include "corpmanag.h"
#include "context_descriptor.h"
#include "print-modes.h"

/* definition of the redirection data structures (used in the parser) */

/**
 * The Redir structure: contains information about
 * redirecting output to a file or pipe.
 *
 * NB this oufght really to be "OutputRedir" as it is the
 * distaff counterpart of InputRedir...
 */
struct Redir {
  char *name;     /**< file name for redirection; if NULL, stdout is used */
  char *mode;     /**< mode for redirection ("w" or "a") */
  FILE *stream;   /**< the actual FILE object to write to. */
  int is_paging;  /**< true iff piping into default pager */
};

/**
 * The InputRedir structure: contains information about
 * redirecting input so it reads from a file or pipe.
 */
struct InputRedir {
  char *name;     /**< file name for redirection */
  FILE *stream;   /**< the actual FILE object to read. */
};


/**
 * TabulationItem object: contains the data structures needed by
 * CQP's "tabulate" command. Each TabulationItem defines a single
 * column in the tabulation output. A since global linked-list of
 * TabulationItems, whose head is stored as TabulationList, is
 * used to hold the tabulation specification requested by the user.
 *
 * Note that TabulationItem is typedefed as a pointer-to-structure.
 *
 * @see TabulationList
 */
typedef struct _TabulationItem {
  char *attribute_name;                 /**< attribute (name) */
  Attribute *attribute;                 /**< handle of said named attribute */
  int attribute_type;                   /**< ATT_NONE = cpos, ATT_POS, ATT_STRUC */
  int flags;                            /**< normalization flags (%c and %d) */
  FieldType anchor1;                    /**< start of token sequence to be tabulated */
  int offset1;                          /**< first cpos offset (from the anchor: e.g. match[-1], etc.  */
  FieldType anchor2;                    /**< end of token sequence (may be identical to start) */
  int offset2;                          /**< second cpos offset (from the anchor: e.g. match[5], etc.  */
  struct _TabulationItem *next;         /**< next tabulation item */
} *TabulationItem;

extern TabulationItem TabulationList;

/* ---------------------------------------------------------------------- */

FILE *open_temporary_file(char *tmp_name_buffer);

//FILE *open_file(char *name, char *mode);

int open_stream(struct Redir *rd, CorpusCharset charset);

int close_stream(struct Redir *rd);

int open_input_stream(struct InputRedir *rd);

int close_input_stream(struct InputRedir *rd);

void catalog_corpus(CorpusList *cl,
                    struct Redir *rd,
                    int first,
                    int last,
                    PrintMode mode);

void print_output(CorpusList *cl,
                  FILE *fd,
                  int interactive,
                  ContextDescriptor *cd,
                  int first, int last,
                  PrintMode mode);

void corpus_info(CorpusList *cl);


/** Enumeration specifying different types of redirectable (error) messages & warnings */
typedef enum _msgtype {
  Error,                        /**< error message (always displayed) */
  Warning,                      /**< warning (not shown in silent mode) */
  Message,                      /**< used for "-d VerboseParser" output only */
  Info                          /**< user information (not shown in silent mode) */
} MessageType;

void cqpmessage(MessageType type, const char *format, ...);

void print_corpus_info_header(CorpusList *cl,
                              FILE *stream,
                              PrintMode mode,
                              int force);

/* ---------------------------------------------------------------------- */

void free_tabulation_list(void);

TabulationItem new_tabulation_item(void);

void append_tabulation_item(TabulationItem item);

int print_tabulation(CorpusList *cl, int first, int last, struct Redir *rd);

#endif
