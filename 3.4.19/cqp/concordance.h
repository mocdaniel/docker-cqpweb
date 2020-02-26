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

#ifndef _concordance_h_
#define _concordance_h_

#include "../cl/class-mapping.h" /* for the Mapping object type. NOTE: the code in concordance.c no longer grovels around in the Mapping data structure, all is done via functions */

#include "context_descriptor.h"
#include "print-modes.h"

/** ConcLineLayout enum represents the possible layout modes (horizontal/vertical) */
typedef enum _conclinelayout {
  ConcLineHorizontal,
  ConcLineVertical
} ConcLineLayout;

/**
 * ConcLineField :  a concordance line "field" is one of the four "anchors":
 * that is, match, matchend, target, keyword. This object contains a record
 * of the location of one such anchor point and its type. This can be passed to
 * a "field-printing" function to perform special rendering of tokens in the
 * "anchor" within a concordance line.
 */
typedef struct _ConcLineField {
  int start_position;
  int end_position;
  int type;
} ConcLineField;




char *compose_kwic_line(Corpus *corpus,
                        int match_start,
                        int match_end,
                        ContextDescriptor *context,
                        int *length,
                        int *string_match_begin,
                        int *string_match_end,
                        char *left_marker,
                        char *right_marker,
                        int *position_list,
                        int nr_positions,
                        int *returned_positions,
                        ConcLineField *fields,
                        int nr_fields,
                        ConcLineLayout orientation,
                        PrintDescriptionRecord *pdr,
                        int nr_mappings,
                        Mapping *mappings);


void cleanup_kwic_line_memory(void);

#endif
