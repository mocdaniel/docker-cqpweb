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

#ifndef __attributes_h
#define __attributes_h

#include "globals.h"

#include "storage.h"                /* gets sys/types.h, so we don't need it here */
#include "corpus.h"



/* attribute allocation classes */
#define ATTS_NONE 0
#define ATTS_LOCAL 1



/* ================================================== Arguments for dynamic attrs */

/**
 * The DynArg object contains an argument for a dynamic attribute.
 */
typedef struct _DynArg {
  int type;
  struct _DynArg *next;
} DynArg;

DynArg *makearg(char *type_id);

/* ================================================== Huffman compressed item seq */

/** The number of integers in the p-attribute Huffmann code decompression block. */
#define SYNCHRONIZATION 128
/** The maximum length of a single code, which is also the number of possible code lengths */
#define MAXCODELEN 32

/**
 * A Huffman Code Descriptor block (HCD) for Huffman compressed sequences.
 */
typedef struct _huffman_code_descriptor {

  int size;                       /**< the id range of the item sequence */
  int length;                     /**< the number of items in the sequence */

  int min_codelen;                /**< minimal code length */
  int max_codelen;                /**< maximal code length */

  int lcount[MAXCODELEN];         /**< number of codes of length i */
  int symindex[MAXCODELEN];       /**< starting point of codes of length i in symbols */
  int min_code[MAXCODELEN];       /**< minimal code of length i */

  int *symbols;                   /**< the code->id mapping table */
} HCD;




/* ================================================== ATTRIBUTE COMPONENTS */

/**
 * ComponentID: indices for the array of components in each Attribute object.
 */
typedef enum wattr_components {
  /* note that the enum name "wattr_components" is misleading, as all att types are covered here */

  /* all attributes have this component */
  CompDirectory,                /**< the directory where an attribute is stored  */

  /* components that make up a positional attribute */
  CompCorpus,                   /**< the sequence of type IDs */
  CompRevCorpus,                /**< reversed file of corpus */
  CompRevCorpusIdx,             /**< index to reversed file  */
  CompCorpusFreqs,              /**< absolute frequencies of types in corpus */
  CompLexicon,                  /**< type lexicon */
  CompLexiconIdx,               /**< index to type lexicon */
  CompLexiconSrt,               /**< sorted index to type lexicon */

  /* components for alignment attributes (each a-attribute has one of these) */
  CompAlignData,                /**< data of alignment attribute */
  CompXAlignData,               /**< data of extended-type alignment attribute */

  /* components that make up a structural attribute */
  CompStrucData,                /**< structure data */
  CompStrucAVS,                 /**< structure attribute values */
  CompStrucAVX,                 /**< structure attribute value index */

  /* compressed components involving Huffman coding (for a positional attribute) */
  CompHuffSeq,                  /**< Huffman compressed item sequence */
  CompHuffCodes,                /**< Code descriptor data for Compressed Item Seq */
  CompHuffSync,                 /**< Synchronisation for a Compressed Item Seq */

  /* compressed components for the reversed-index (for a positional attribute) */
  CompCompRF,                   /**< compressed reversed file (CompRevCorpus) */
  CompCompRFX,                  /**< index for CompCompRF (substitute for CompRevCorpusIdx) */

  CompLast                      /**< MUST BE THE LAST ELEMENT OF THIS ENUM
                                     -- it is used for limiting loops on component arrays
                                     and for the [size] in the declaration of such arrays */
} ComponentID;

/**
 * Possible states for an attribute component
 */
typedef enum component_states {
  ComponentLoaded,            /**< valid and loaded  */
  ComponentUnloaded,          /**< valid and on disk */
  ComponentDefined,           /**< valid but not yet created */
  ComponentUndefined          /**< invalid */
} ComponentState;

/**
 * The Component object.
 *
 * A "component" is one of the data-chunks on disk that make up a CWB corpus.
 * Each corpus attribute (of whatever kind) consists of an array (vector) of
 * components, along with some other fields dependent on what type of attribute
 * it is.
 *
 * @see ComponentID
 * @see Attribute
 * @see _Attribute
 */
typedef struct TComponent {
  char *path;                   /**< the full filename of this component */
  Corpus *corpus;               /**< the corpus this component belongs to */
  Attribute *attribute;         /**< the attribute this component belongs to */
  ComponentID id;               /**< the type of this component */
  int size;                     /**< a copy of the number of items in the structure */
  MemBlob data;                 /**< the actual contents of this component */
} Component;


char *cid_name(ComponentID cid);

ComponentID component_id(char *name);

int MayHaveComponent(int attr_type, ComponentID cid);



/* ============================================================ ATTRIBUTES */


/**
 * Members found in ALL the different types of Attribute object.
 *
 * NOTE: NO SEMICOLON AFTER LAST FIELD!! So when used, follow with ;
 */
#define COMMON_ATTR_FIELDS  \
int type;                  /**< the attribute type. MUST BE THE FIRST FIELD. */\
char *name;                /**< the attribute name or multi-purpose field */   \
union _Attribute *next;    /**< the next member of the attr chain */           \
int attr_number;           /**< a number, unique in this corpus, 0 for word */ \
char *path;                /**< path to attribute data files */                \
 \
struct TCorpus *mother;              /**< corpus this att is assigned to */    \
Component *components[CompLast]      /**< the component vector (array of pointers) of the attribute */ \

/* endof COMMON_ATTR_FIELDS */


typedef struct {
  COMMON_ATTR_FIELDS;
} Any_Attribute;

typedef struct {
  COMMON_ATTR_FIELDS;
  HCD *hc;                          /**< positional attribute may have a huffman code descriptor block */
  int this_block_nr;                /**< number of the current decompression block */
  int this_block[SYNCHRONIZATION];  /**< the decompression block proper */
} POS_Attribute;

typedef struct {
  COMMON_ATTR_FIELDS;
  int has_attribute_values;         /**< boolean: whether or not instances of this s-attribute can have values
                                         @see structure_has_values */
} Struc_Attribute;

typedef struct {
  COMMON_ATTR_FIELDS;
} Alg_Attribute;

typedef struct {
  COMMON_ATTR_FIELDS;
  char *call;
  int res_type;
  DynArg *arglist;
} Dynamic_Attribute;




/**
 * The Attribute object (typedef in cl.h, union/stuct defs in attributes.h).
 *
 * The Attribute object is a union of structures, one for each of the various
 * kinds of attribute (positional (P), structural (S), alignment (A), dynamic).
 *
 * The "any" member allows the shared fields to be accessed even if it's not
 * known what the type of the Attribute you're working with is.
 *
 * @see COMMON_ATTR_FIELDS
 */
union _Attribute {
  int type;
  Any_Attribute any;
  POS_Attribute pos;
  Struc_Attribute struc;
  Alg_Attribute align;
  Dynamic_Attribute dyn;
};



/* ============================================================ ATTRIBUTE FUNCTIONS */

/* function exported for the use of the registry parser. don't call it. */
Attribute *setup_attribute(Corpus *corpus, char *attribute_name, int type, char *data);


/* ======================================== COMPONENT FUNCTIONS */

Component *load_component(Attribute *attribute, ComponentID component);

int drop_component(Attribute *attribute, ComponentID component);

Component *create_component(Attribute *attribute, ComponentID component);

Component *find_component(Attribute *attribute, ComponentID component);

Component *ensure_component(Attribute *attribute, ComponentID component, int try_creation);

Component *declare_component(Attribute *attribute, ComponentID cid, char *path);

void declare_default_components(Attribute *attribute);

ComponentState component_state(Attribute *attribute, ComponentID component);

char *component_full_name(Attribute *attribute, ComponentID component, char *path);

/* =============================================== LOOP THROUGH ATTRIBUTES */

Attribute *first_corpus_attribute(Corpus *corpus);

Attribute *next_corpus_attribute(void);

/* =============================================== INTERACTIVE FUNCTIONS */

void describe_attribute(Attribute *attribute);

void describe_component(Component *component);


#endif
