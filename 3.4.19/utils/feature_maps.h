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


#include "../cl/cl.h"


#define MAXBLOCKS 10 

/** Data structure for the vstack member of the FMS object. @see FMS */
typedef struct vstack_t {
  int *fcount;
  struct vstack_t *next;
} vstack_t;


/** Underlying structure for the FMS object. */
typedef struct feature_maps_t {
  Attribute *att1;              /**< word attribute of source corpus */
  Attribute *att2;              /**< word attribute of target corpus */
  Attribute *s1;                /**< sentence regions of source corpus */
  Attribute *s2;                /**< sentence regions of target corpus */
  int n_features;               /**< number of allocated features */
  int **w2f1;                   /**< feature map 1 */
  int **w2f2;                   /**< feature map 2 */
  int *fweight;                 /**< array of feature weights */

  vstack_t *vstack;             /**< a stack (implemented as linked list) of integer vectors,
                                     each containing <n_features> integers. */

} feature_maps_t;

/**
 * The FMS object: contains memory space for a feature map between two attributes,
 * used in aligning corpora.
 *
 * The "feature map" is a very large and complex data structure of all the different features
 * we can look at, together with weights.
 *
 * Basically, it is a "compiled" version of the features defined by the cwb-align configuration
 * flags *AS APPLIED TO THIS SPECIFIC CORPUS* - a massive list of "things to look for"
 * when comparing any two potentially-corresponding regions from a source/target corpus pair.
 */
typedef feature_maps_t *FMS;


FMS create_feature_maps(char **config, int config_lines,
                        Attribute *w_attr1, Attribute *w_attr2,
                        Attribute *s_attr1, Attribute *s_attr2
                        );


int *get_fvector(FMS fms);

void release_fvector(int *fvector, FMS fms);

void check_fvectors(FMS fms);



int feature_match(FMS fms, int f1, int l1, int f2, int l2);


void show_features(FMS fms, int which, char *word);



void best_path(FMS fms,
               int f1, int l1,
               int f2, int l2,
               int beam_width,       /* beam search */
               int verbose,          /* echo progress info on stdout ? */
               /* output */
               int *steps,
               int **out1,
               int **out2,
               int **out_quality);



