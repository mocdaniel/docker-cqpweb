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

#ifndef _LATEX_PRINT_H_
#define _LATEX_PRINT_H_

#include <stdio.h>
#include "../cl/bitfields.h"
#include "corpmanag.h"
#include "context_descriptor.h"
#include "print-modes.h"
#include "groups.h"

extern 
PrintDescriptionRecord LaTeXPrintDescriptionRecord;

char *
latex_convert_string(char *s);

void
latex_print_aligned_line(FILE *stream, char *attribute_name, char *line);

void 
latex_print_corpus_header(CorpusList *cl, 
			 FILE *outfd);

void 
latex_print_output(CorpusList *cl, 
		   FILE *outfd,
		   int interactive,
		   ContextDescriptor *cd,
		   int first, int last);

void 
latex_print_group(Group *group, int expand, FILE *fd);


#endif
