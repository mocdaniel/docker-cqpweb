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


#ifndef _BITIO_H_
#define _BITIO_H_

#include <sys/types.h>

#include "globals.h"

/**
 * File buffer for bit input / output.
 */
typedef struct _bfilebuf {
  FILE *fd;
  char mode;
  unsigned char buf;
  int bits_in_buf;
  off_t position;
} BFile;

/**
 * Stream buffer for bit input / output.
 */
typedef struct _bstreambuf {
  unsigned char *base;
  char mode;
  unsigned char buf;
  int bits_in_buf;
  off_t position;
} BStream;



int BFopen(char *filename, char *type, BFile *bf);
int BFclose(BFile *stream);

int BSopen(unsigned char *base, char *type, BStream *bf);
int BSclose(BStream *stream);

int BFflush(BFile *stream);
int BSflush(BStream *stream);

int BFwrite(unsigned char data, int nbits, BFile *stream);
int BSwrite(unsigned char data, int nbits, BStream *stream);

int BFread(unsigned char *data, int nbits, BFile *stream);
int BSread(unsigned char *data, int nbits, BStream *stream);

int BFwriteWord(unsigned int data, int nbits, BFile *stream);

int BFreadWord(unsigned int *data, int nbits, BFile *stream);

int BFposition(BFile *stream);
int BSposition(BStream *stream);

int BSseek(BStream *stream, off_t offset);

#endif
