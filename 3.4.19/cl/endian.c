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


/**
 * @file
 *
 * Provides the definition of the cl_bswap32() function, which was used as a portable implementation of the ntohl()
 * and htonl() macros in earlier CWB versions; still needed for explicit conversion to little-endian format
 */


#include "globals.h"
#include "endian.h"


/**
 * Swaps the byte order of a integer.
 *
 * This function is a portable bswap implementation allowing explicit
 * conversion to little-endian format (by a combination of cl_bswap32() and
 * htonl())
 *
 * Note that this function will work correctly with 32bit and larger
 * int data types.
 *
 * @param x  The integer whose bytes are to be reordered.
 * @return   The reordered integer.
 */
int cl_bswap32(int x) {
  register int y;
  y = x & 0xff;
  /* let the compiler worry about optimisation */
  y = (y << 8) + ((x >> 8) & 0xff);
  y = (y << 8) + ((x >> 16) & 0xff);
  y = (y << 8) + ((x >> 24) & 0xff);
  return y;
}

