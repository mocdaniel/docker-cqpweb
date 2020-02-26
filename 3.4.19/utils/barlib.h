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
 * The BARdesc object: a BAR (Beamed Array) descriptor.
 *
 * The Beamed Array Library implements storage for the kind of sparse matrix required
 * by beam search methods in dynamic programming. A Beamed Array is a N-by-M matrix
 *
 *      A(x,y) ;  x = 0 ... N-1, y = 0 ... M-1
 *
 * of integer values, where each right/down diagonal
 *
 *      d_k := { (x,y) | x + y = k }
 *
 * contains a single contiguous block of at most W (potentially) nonzero elements. The
 * position of this block on a given diagonal is determined by the first write access to
 * that diagonal. It is assumed that the first element written is the leftmost one, i.e.
 * setting A(x,y) = 1 will define the block on d_(x+y) to be
 *
 *      {(x,y), (x+1,y-1), ... , (x+W-1, y+W-1)}
 *
 * Well done if you understand that......
 */
typedef struct _BARdesc {
  unsigned int x_size, y_size, d_size; /**< matrix dimensions: N, M, N+M */
  unsigned int beam_width;             /**< beam width W */
  int *d_block_start_x;                /**< vector of diagonal block start points (x coordinate) */
  int **d_block_data;                  /**< list of vectors containing diagonal block data */
  int *data;                           /**< pointer to data space */
  int vector_size;                     /**< size of allocated arrays */
  int data_size;                       /**< used by BAR_reinit() to know if it needs to reallocate memory */
} *BARdesc;

BARdesc BAR_new(int N, int M, int W);

void BAR_reinit(BARdesc BAR, int N, int M, int W);

void BAR_delete(BARdesc BAR);

void BAR_write(BARdesc BAR, int x, int y, int i);

int BAR_read(BARdesc BAR, int x, int y);

