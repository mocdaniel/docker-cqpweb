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

#ifndef _corpus_h
#define _corpus_h

#include "globals.h"




/**
 * The IDList class: entries in linked lists of identifier strings.
 *
 * These identifier strings can be usernames, groupnames or hostnames
 * and are used to restrict access to particular corpora.
 *
 * Note that IDLists are added to Corpus objects by the registry parser.
 *
 * NOT to be confused with "idlist" in the sense of cl_idlist2cpos().
 */
typedef struct _idbuf *IDList;

/** Underlying structure for the IDList class. */
typedef struct _idbuf {
  char *string; /**<the username, groupname or hostname */
  IDList next;  /**<link to next entry in the linked list */
} IDBuf;



void FreeIDList(IDList *list);
int memberIDList(char *s, IDList l);

/* a new-style API for idlists */
#define IDList_delete(l) FreeIDList(l)
#define IDList_check_member(l, s) memberIDList(s, l) /* parameter order standardised for objects */




/* ---------------------------------------------------------------------- */

/* typedef struct TCorpus Corpus; now in <cl.h> */

/** Underlying structure for the Corpus class. */
struct TCorpus {

  char *id;                        /**< a unique ID (i.e., the registry name identifying the corpus to the CWB) */
  char *name;                      /**< the full name of the corpus (descriptive, for information only) */
  char *path;                      /**< the ``home directory'' of the corpus  */
  char *info_file;                 /**< the path of the info file of the corpus */

  CorpusCharset charset;           /**< a special corpus property: specifies character set of the encoded text */
  CorpusProperty properties;       /**< head of a linked list of CorpusProperty object. */

  char *admin;                     /**< {doesn't seem to be used?} */

  IDList groupAccessList;          /**< List of groups allowed to access this corpus (can be NULL) */
  IDList userAccessList;           /**< List of users allowed to access this corpus (can be NULL) */
  IDList hostAccessList;           /**< List of host machines allowed to access this corpus (can be NULL) */
  
  char *registry_dir;              /**< Directory where this corpus's registry file is located */
  char *registry_name;             /**< the cwb-name of this corpus */

  int nr_of_loads;                 /**< the number of setup_corpus ops */

  union _Attribute *attributes;    /**< the list of attributes */
  
  struct TCorpus *next;            /**< next entry in a linked-list of loaded corpora */

};

/* ---------------------------------------------------------------------- */

/* external variable declarations: from the registry parser */

extern char *cregin_path;
extern char *cregin_name;

/* ---------------------------------------------------------------------- */

extern Corpus *loaded_corpora;

/* ---------------------------------------------------------------------- */

/* (most) function prototypes are now in <cl.h> */

void add_corpus_property(Corpus *corpus, char *property, char *value);
Corpus *find_corpus(char *registry_dir, char *registry_name);
void describe_corpus(Corpus *corpus);

#endif
