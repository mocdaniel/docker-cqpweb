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

#include "cqp.h"
#include "options.h"


/**
 * Main function for cqpcl.
 *
 * Exists only to call other functions, with silent set to true.
 *
 * Note that cqpcl is DEPRACATED.
 *
 * @see silent
 * @param argc  Number of commandline arguments.
 * @param argv  Pointer to array of commandline arguments.
 * @return      0 for all OK, other value for error.
 */
int
main(int argc, char *argv[])
{
  int i;
  extern int optind;

  which_app = cqpcl;

  if (!initialize_cqp(argc, argv)) {
    fprintf(stderr, "Can't initialize CQP\n");
    exit(1);
  }

  paging = 0;
  silent = 1;

  if (query_string) {
    if (!cqp_parse_string(query_string)) {
      fprintf(stderr, "Syntax error in %s, exiting\n", query_string);
      exit(1);
    }
  }
  else {
    for (i = optind; i < argc; i++)
      if (!cqp_parse_string(argv[i])) {
        fprintf(stderr, "Syntax error, exiting\n");
        exit(1);
      }
  }

  return 0;
}
