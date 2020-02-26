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

#include <stdarg.h>
#include <math.h>
#include <ctype.h>
#include <sys/types.h>
#include <errno.h>

#include "globals.h"

#include "endian.h"
#include "attributes.h"
#include "special-chars.h"
#include "bitio.h"
#include "compression.h"
#include "regopt.h"

/**
 * If COMPRESS_DEBUG is set to a positive integer, cl_cpos2id() will
 * print debugging messages. (2 prints more than 1!)
 */
#define COMPRESS_DEBUG 0

/**
 * Error number for CL: is set after access to any of various corpus-data-access functions.
 */
int cl_errno = CDA_OK;

/**
 * Macro to test for one of the two component state values that boil down to
 * "the data is avaiable".
 */
#define compstate_data_available(st) ((st)==ComponentLoaded||(st)==ComponentUnloaded)


/**
 * Checks an Attribute passed as a function argument for usability in that function.
 *
 * (a) arg must not be NULL.
 * (b) arg type has to be the type specified in atyp.
 *
 * If these conditions are not fulfilled, the current function returns rval, and
 * cl_errno is set.
 */
#define check_arg(arg,atyp,rval) \
if (arg == NULL) { \
  cl_errno = CDA_ENULLATT; return rval; \
} \
else if (arg->type != atyp) { \
  cl_errno = CDA_EATTTYPE; return rval; \
}

/**
 * CL's string comparison function.
 */
int
cl_strcmp(const char *s1, const char *s2)
{
  /* we compare signed chars; this is declared & typecast explicitly for the sake of systems
   * where char with no modifier is unsigned. */

  register signed char* c1 = (signed char *)s1;
  register signed char* c2 = (signed char *)s2;

  for ( ; *c1 == *c2; c1++, c2++)
    if (*c1 == '\0')
      return 0;

  return *c1 - *c2;
}

/**
 * Gets a string describing the error identified by an error number.
 *
 * The string is a pointer to an internal constant string, i.e.,
 * do not modify or free it!
 *
 * @param error_num  Error number integer (a CDA_* constant as defined in cl.h)
 */
char *
cl_error_string(int error_num)
{
  char *s;

  switch (error_num) {
  case CDA_OK:
    s = "CL: No error";
    break;
  case CDA_ENULLATT:
    s = "CL: NULL passed as attribute argument of function";
    break;
  case CDA_EATTTYPE:
    s = "CL: function called with illegal attribute type";
    break;
  case CDA_EIDORNG:
    s = "CL: id is out of range";
    break;
  case CDA_EPOSORNG:
    s = "CL: position is out of range";
    break;
  case CDA_EIDXORNG:
    s = "CL: index is out of range";
    break;
  case CDA_ENOSTRING:
    s = "CL: no such string encoded";
    break;
  case CDA_EPATTERN:
    s = "CL: illegal regular expression/illegal pattern";
    break;
  case CDA_ESTRUC:
    s = "CL: no structure defined for this position";
    break;
  case CDA_EALIGN:
    s = "CL: no alignment defined for this position";
    break;
  case CDA_EREMOTE:
    s = "CL: error during access of remote data";
    break;
  case CDA_ENODATA:
    s = "CL: can't load and/or create necessary data";
    break;
  case CDA_EARGS:
    s = "CL: error in arguments of dynamic call or CL function";
    break;
  case CDA_ENOMEM:
    s = "CL: not enough memory";
    break;
  case CDA_EOTHER:
    s = "CL: unspecified error";
    break;
  case CDA_ENYI:
    s = "CL: unimplemented feature/not yet implemented";
    break;
  case CDA_EBADREGEX:
    s = "CL: bad regular expression";
    break;
  case CDA_EFSETINV:
    s = "CL: invalid feature set (syntax error)";
    break;
  case CDA_EBUFFER:
    s = "CL: internal buffer overflow";
    break;
  case CDA_EINTERNAL:
    s = "CL: internal data inconsistency";
    break;
  case CDA_EACCESS:
    s = "CL: insufficient access permissions";
    break;
  case CDA_EPOSIX:
    s = strerror(errno);
    break;
  default:
    s = "CL: ILLEGAL ERROR NUMBER";
    break;
  }
  return s;
}

/**
 * Prints an error message, together with a string identifying the current error number.
 */
void
cl_error(char *message)
{
  if (message)
    fprintf(stderr, "%s: %s\n", cl_error_string(cl_errno), message);
  else
    fprintf(stderr, "%s\n", cl_error_string(cl_errno));
}





/* ================================================== POSITIONAL ATTRIBUTES */

/* ==================== the mapping between strings and their ids */

/**
 * Gets the string that corresponds to the specified item on the given P-attribute.
 *
 * @param attribute  The Attribute to look the item up on
 * @param id         Identifier of an item on this attribute.
 * @return           The string (pointer to actual data within the
 *                   attribute, DO NOT FREE!), or NULL if there is an error.
 */
char *
cl_id2str(Attribute *attribute, int id)
{
  Component *lex;
  Component *lexidx;

  check_arg(attribute, ATT_POS, NULL);

  lex = ensure_component(attribute, CompLexicon, 0);
  lexidx = ensure_component(attribute, CompLexiconIdx, 0);

  if ((lex == NULL) || (lexidx == NULL)) {
    cl_errno = CDA_ENODATA;
    return NULL;
  }
  else if ((id < 0) || (id >= lexidx->size)) {
    cl_errno = CDA_EIDORNG;
    return NULL;
  }
  else {
    cl_errno = CDA_OK;
    return ((char *)lex->data.data + ntohl(lexidx->data.data[id]));
  }

  assert("Not reached" && 0);
  return NULL;
}


/**
 * Gets the ID code that corresponds to the specified string on the given P-attribute.
 *
 * @param attribute  The (positional) Attribute to look the string up on
 * @param id_string  The string of an item on this attribute
 * @return           Either the integer ID of the item, or an error code (if less than 0).
 *                   In the latter case, the error code will also be written to cl_errno.
 */
int
cl_str2id(Attribute *attribute, char *id_string)
{
  int low, high, nr, mid, comp;

  Component *lexidx;
  Component *lexsrt;
  Component *lex;

  char *str2;

  check_arg(attribute, ATT_POS, cl_errno);

  lexidx = ensure_component(attribute, CompLexiconIdx, 0);
  lexsrt = ensure_component(attribute, CompLexiconSrt, 0);
  lex    = ensure_component(attribute, CompLexicon,    0);

  if (!(lexidx && lexsrt && lex))
    return cl_errno = CDA_ENODATA;

  low = 0;
  high = lexidx->size;

  /* simple binary search: any item-not-found condition returns the function */
  for(nr = 0; ; nr++) {
    if (nr >= 1000000) {
      fprintf(stderr, "cl_str2id: too many comparisons with %s\n", id_string);
      return cl_errno = CDA_EOTHER;
    }

    mid = low + (high - low)/2;

    str2 = (char *)(lex->data.data) + ntohl(lexidx->data.data[ntohl(lexsrt->data.data[mid])]);

    comp = cl_strcmp(id_string, str2);

    if (comp == 0)
      break;   /* found it! */

    if (mid == low)
      return cl_errno = CDA_ENOSTRING;

    if (comp > 0)
      low = mid;
    else
      high = mid;
  }

  /* we broke the loop above having found the string (whose ID is addresses by "mid") */
  cl_errno = CDA_OK;
  return ntohl(lexsrt->data.data[mid]);
}

/**
 * Calculates the length of the string that corresponds to the specified
 * item on the given P-attribute.
 *
 * @param attribute  The (positional) Attribute to look up the item on
 * @param id         Identifier of an item on this attribute.
 * @return           The length of the string, or a CDA_ error code
 */
int
cl_id2strlen(Attribute *attribute, int id)
{
  Component *lexidx;
  char *s;

  check_arg(attribute, ATT_POS, cl_errno);

  lexidx = ensure_component(attribute, CompLexiconIdx, 0);

  if (lexidx == NULL)
    return cl_errno = CDA_ENODATA;

  if ((id < 0) || (id >= lexidx->size))
    return cl_errno = CDA_EIDORNG;

  if ((id + 1) == lexidx->size) {
    /* last item */
    s = cl_id2str(attribute, id);

    if (s) {
      cl_errno = CDA_OK;
      return strlen(s);
    }
    else
      return CL_ALL_OK() ? CDA_EOTHER : cl_errno;
  }
  else {
    /* any other item */
    cl_errno = CDA_OK;
    return (ntohl(lexidx->data.data[id+1]) - ntohl(lexidx->data.data[id])) - 1;
  }

  assert("Not reached" && 0);
  return 0;
}


/**
 * Gets the ID code of the item at the specified position in the Attribute's sorted wordlist index.
 *
 * That is, given a sort-order position, the actual ID of the corresponding item is generated.
 *
 * @see get_sortidxpos_of_id
 * @param attribute            The (positional) Attribute whose index is to be searched.
 * @param sort_index_position  The offset in the index where the ID code is to be found.
 * @return                     Either the integer ID, or an error code (if less than 0)
 */
int
cl_sort2id(Attribute *attribute, int sort_index_position)
{
  Component *srtidx;

  check_arg(attribute, ATT_POS, cl_errno);

  if (!(srtidx = ensure_component(attribute, CompLexiconSrt, 0)))
    return cl_errno = CDA_ENODATA;

  if (!(sort_index_position >= 0 && sort_index_position < srtidx->size))
    return cl_errno = CDA_EIDXORNG;

  cl_errno = CDA_OK;
  return ntohl(srtidx->data.data[sort_index_position]);
}

/**
 * Gets the position in the Attribute's sorted wordlist index of the item
 * with the specified ID code.
 *
 * This function is NOT YET IMPLEMENTED.
 *
 * @see get_id_from_sortidx
 * @param attribute  The (positional) Attribute whose index is to be searched
 * @param id         Identifier of an item on this attribute.
 * @return           The offset of that item in the sorted wordlist index, or an error code.
 */
int
cl_id2sort(Attribute *attribute, int id)
{
  Component *srtidx;
  int offset;

  /* not yet implemented!!! */
  cl_errno = CDA_ENYI;
  return CDA_ENYI;

  check_arg(attribute, ATT_POS, cl_errno);

  if (!(srtidx = ensure_component(attribute, CompLexiconSrt, 0)))
    return cl_errno = CDA_ENODATA;

  if (!(id >= 0 && id < srtidx->size))
    return cl_errno = CDA_EIDORNG;

  /* TODO below is probably the worst possible way to do it. But it fills the function in. */

  /* find sort index entry containing the id we were given. */
  for (offset = 0 ; offset < srtidx->size ; offset++)
    if (id == ntohl(srtidx->data.data[offset]))
      break;

  /* this ought not to be possible */
  if (offset >= srtidx->size)
    return cl_errno = CDA_EINTERNAL;

  /* TODO a proper implementation of the abonve so the "ENYI" can be removed */
  cl_errno = CDA_OK;
  return offset;

}


/* ==================== information about the corpus */

/**
 * Checks whether the item sequence of the given P-attribute should be
 * accessed via its compressed data.
 *
 * See comments in body of function for what counts as "compressed".
 *
 * @return  Boolean. True if the compressed data is available
 *          and should be used; false if the corpus hasn't been compressed
 *          or the full data has been loaded anyway.
 *          Or, negative CL error code if a non-p-attribute is passed.
 */
int
cl_sequence_compressed(Attribute *attribute)
{
  ComponentState state;

  check_arg(attribute, ATT_POS, cl_errno);

  /* The item sequence is compressed iff all three components
   * (CompHuffSeq, CompHuffCodes, CompHuffSync) are available
   * OR when the code description block is already initialized. */

  /* further, the CompCorpus component shouldn't be in memory. This is a trick,
   * so that uncompressed access can be enforced if the CompCorpus is ensure'd. */

  /* if the HCD has been set up, use compressed data */
  if (attribute->pos.hc)
    return 1;

  /* if CompCorpus is in memory, we do not access the compressed sequence. */
  if (ComponentLoaded == component_state(attribute, CompCorpus))
    return 0;

  /* If we don't have all the following 3 componenents either loaded or loadable,
   * we do not access the compressed sequence.
   */
  state = component_state(attribute, CompHuffSeq);
  if (!compstate_data_available(state))
    return 0;

  state = component_state(attribute, CompHuffCodes);
  if (!compstate_data_available(state))
    return 0;

  state = component_state(attribute, CompHuffSync);
  if (!compstate_data_available(state))
    return 0;

  /* having run that gauntlet, we can return true. */
  return 1;
}



/**
 * Check whether the reverse-corpus index (inverted file) of the given P-attribute
 * should be accessed via its compressed data.
 *
 * See comments in body of function for what counts as "compressed".
 *
 * @return  Boolean. True if the compressed data is available
 *          and should be used; false if the index hasn't been compressed
 *          or the full data has been loaded anyway.
 *          Or, negative CL error code if a non-p-attribute is passed.
 */
int
cl_index_compressed(Attribute *attribute)
{
  ComponentState state;

  check_arg(attribute, ATT_POS, cl_errno);

  /* The inverted file is compressed iff both components
   * (CompCompRF, CompCompRFX) are available */

  /* as per the equivalent sequence function:
   *   - when CompRevCorpus and CompRevCorpusIdx are already
   *     in memory, we do not use the compressed inverted file.
   */
  if (
      ComponentLoaded == component_state(attribute, CompRevCorpus)
      &&
      ComponentLoaded == component_state(attribute, CompRevCorpusIdx)
     )
    return 0;

  state = component_state(attribute, CompCompRF);
  if (!compstate_data_available(state))
    return 0;

  state = component_state(attribute, CompCompRFX);
  if (!compstate_data_available(state))
    return 0;

  return 1;
}




/**
 * Gets the maximum position on this P-attribute (ie the size of the
 * attribute).
 *
 * The result of this function is equal to the number of tokens
 * in the attribute.
 *
 * If the attribute's item sequence is compressed, this is read from
 * the attribute's Huffman code descriptor block.
 *
 * Otherwise, it is read from the size member of the Attribute's
 * CompCorpus component.
 *
 * @return  The maximum corpus position, or an error code (if less than 0)
 */
int
cl_max_cpos(Attribute *attribute)
{
  Component *corpus;

  check_arg(attribute, ATT_POS, cl_errno);

  if (cl_sequence_compressed(attribute)) {
    ensure_component(attribute, CompHuffCodes, 0);
    if (attribute->pos.hc == NULL)
      return cl_errno = CDA_ENODATA;
    cl_errno = CDA_OK;
    return attribute->pos.hc->length;
  }

  /* but if it's not compressed... */
  if (!(corpus = ensure_component(attribute, CompCorpus, 0)))
    return cl_errno = CDA_ENODATA;
  cl_errno = CDA_OK;
  return corpus->size;
}

/**
 * Gets the maximum id on this P-attribute (ie the range of the attribute's ID codes).
 *
 * The result of this function is equal to the number of types in this attribute.
 *
 * @see get_attribute_size
 *
 * @return  The maximum Id, or an error code (if less than 0)
 */
int
cl_max_id(Attribute *attribute)
{
  Component *comp;

  check_arg(attribute, ATT_POS, cl_errno);

  if (!(comp = ensure_component(attribute, CompLexiconIdx, 0)))
    return cl_errno = CDA_ENODATA;

  cl_errno = CDA_OK;
  return comp->size;
}



/* ==================== the relation between ids and the corpus */

/**
 * Gets the frequency of an item on this attribute.
 *
 * @param attribute  The P-attribute to look on
 * @param id         Identifier of an item on this attribute.
 * @return           The frequency count of the item specified
 *                   by id, or an error code (if less than 0)
 */
int
cl_id2freq(Attribute *attribute, int id)
{
  Component *freqs;

  check_arg(attribute, ATT_POS, cl_errno);

  freqs = ensure_component(attribute, CompCorpusFreqs, 0);

  if (freqs == NULL)
    return cl_errno = CDA_ENODATA;

  if (id >= 0 && id < freqs->size) {
    cl_errno = CDA_OK;
    return ntohl(freqs->data.data[id]);
  }

  return cl_errno = CDA_EIDXORNG;
}

/* ============================================================ */


/**
 * Gets all the corpus positions where the specified item is
 * found on the given P-attribute.
 *
 * The restrictor list is a set of ranges in which instances of
 * the item MUST occur to be collected by this function. If no
 * restrictor list is specified (i.e. restrictor_list is NULL),
 * then ALL corpus positions where the item occurs are returned.
 *
 * This restrictor list has the form of a list of ranges {start,end}
 * of size restrictor_list_size, that is, the number of ints in
 * this area is 2 * restrictor_list_size.
 *
 * This function is "oldstyle" because in the "newstyle" function,
 * there is no restrictor list. (And in fact, the newstyle
 * function is implemented as a macro to this one with the last
 * two arguments NULL and 0.) It might be better called "restricted".
 *
 * @see cl_id2cpos
 *
 * @param attribute             The P-attribute to look on.
 * @param id                    The id of the item to look for.
 * @param freq                  The frequency of the specified
 *                              item is written here. This will
 *                              be 0 in the case of errors.
 * @param restrictor_list       A list of pairs of integers
 *                              specifying ranges {start,end}
 *                              in the corpus
 * @param restrictor_list_size  The number of PAIRS of ints in
 *                              the restrictor list.
 * @return                      Pointer to the list of corpus
 *                              positions; or NULL in case of
 *                              error.
 */
int *
cl_id2cpos_oldstyle(Attribute *attribute, int id, int *freq, int *restrictor_list, int restrictor_list_size)
{
  Component *revcorp, *revcidx;
  int *buffer;
  int size, range, i;

  check_arg(attribute, ATT_POS, NULL);

  size = cl_max_cpos(attribute);
  if (size <= 0 || !CL_ALL_OK()) {
    /*       fprintf(stderr, "Cannot determine size of PA %s\n", attribute->any.name); */
      return NULL;
  }

  range  = cl_max_id(attribute);
  if (range <= 0 || !CL_ALL_OK()) {
    /*       fprintf(stderr, "Cannot determine ID range of PA %s\n", attribute->any.name); */
    return NULL;
  }

  if (id < 0 || id >= range) {
    cl_errno = CDA_EIDORNG;
    /*       fprintf(stderr, "ID %d out of range of PA %s\n", id, attribute->any.name); */
    *freq = 0;
    return NULL;
  }

  *freq = cl_id2freq(attribute, id);
  if (*freq < 0 || !CL_ALL_OK()) {
    /*       fprintf(stderr, "Frequency %d of ID %d illegal (PA %s)\n", *freq, id, attribute->any.name); */
    return NULL;
  }

  /* there are no items in a P-Attribute with freq 0 - we don't have to catch that special case. */

  buffer = (int *)cl_malloc(*freq * sizeof(int));

  if (cl_index_compressed(attribute)) {
    BStream bs;
    unsigned int i, b, last_pos, gap, offset, ins_ptr, res_ptr;

    revcorp = ensure_component(attribute, CompCompRF, 0);
    revcidx = ensure_component(attribute, CompCompRFX, 0);

    if (!revcorp || !revcidx) {
      cl_errno = CDA_ENODATA;
      *freq = 0;
      return NULL;
    }

    b = compute_ba(*freq, size);

    offset = ntohl(revcidx->data.data[id]); /* byte offset in RFC */

    BSopen((unsigned char *)revcorp->data.data, "r", &bs);
    BSseek(&bs, offset);

    last_pos = 0;
    ins_ptr = 0;
    res_ptr = 0;

    for (i = 0; i < *freq; i++) {
      gap = read_golomb_code_bs(b, &bs);
      last_pos += gap;

      /* TODO : when the end of the restrictor list is reached, we can also leave the for loop above */

      if (restrictor_list && restrictor_list_size > 0) {
        while (res_ptr < restrictor_list_size && last_pos > restrictor_list[res_ptr * 2 + 1])
          /* beyond last restricting range */
          res_ptr++;

        if (res_ptr < restrictor_list_size &&
            last_pos >= restrictor_list[res_ptr * 2] &&
            last_pos <= restrictor_list[res_ptr * 2 + 1]) {
          buffer[ins_ptr++] = last_pos;
        }
      }
      else
        /* no restrictor list: copy */
        buffer[ins_ptr++] = last_pos;
    }

    BSclose(&bs);

    /* reduce, if possible */

    if (ins_ptr < *freq && ins_ptr != *freq) {
      if (ins_ptr == 0) {
        assert(buffer);
        cl_free(buffer);
      }
      else
        buffer = cl_realloc(buffer, ins_ptr * sizeof(int));
      *freq = ins_ptr;
    }

  } /* endif cl_index_compressed */

  else {
    revcorp = ensure_component(attribute, CompRevCorpus, 0);
    revcidx = ensure_component(attribute, CompRevCorpusIdx, 0);

    if (!(revcorp && revcidx)) {
      cl_errno = CDA_ENODATA;
      /*        fprintf(stderr, "Cannot load REVCORP or REVCIDX component of %s\n", attribute->any.name); */
      *freq = 0;
      return NULL;
    }

    memcpy(buffer, revcorp->data.data + ntohl(revcidx->data.data[id]), *freq * sizeof(int));

    /* convert network byte order to native integers */
    for (i = 0; i < *freq; i++)
      buffer[i] = ntohl(buffer[i]);

    if (restrictor_list != NULL && restrictor_list_size > 0) {
      /* force all items to be within the restrictor's ranges */
      int res_ptr = 0, buf_ptr = 0, ins_ptr = 0;

      while (buf_ptr < *freq && res_ptr < restrictor_list_size) {
        if (buffer[buf_ptr] < restrictor_list[res_ptr*2])
          /* before start */
          buf_ptr++;

        else if (buffer[buf_ptr] > restrictor_list[res_ptr*2+1])
          /* beyond end */
          res_ptr++;

        else
          /* within range */
          buffer[ins_ptr++] = buffer[buf_ptr++];
      }

      if (ins_ptr < *freq && ins_ptr != *freq) {
        if (ins_ptr == 0)
          cl_free(buffer);
        else
          buffer = cl_realloc(buffer, ins_ptr * sizeof(int));
        *freq = ins_ptr;
      }
    }
  }

  cl_errno = CDA_OK;
  return buffer;
}








/* ---------------------------------------- stream-like reading */


/**
 * Underlying structure for the PositionStream object.
 *
 * PositionStreams are used for accessing Attributes.
 * Each one represents a stream of corpus positions,
 * representing positions where a given item occurs.
 *
 */
typedef struct _position_stream_rec_ {
  Attribute *attribute;         /**< The Attribute on which this PositionStream has been opened. */
  int id;                       /**< The item whose positions this PositionStream will read */
  int id_freq;                  /**< id frequency (ie frequency of the item in question);
                                     maximum number of positions that can be read */
  int nr_items;                 /**< how many items delivered so far */

  int is_compressed;            /**< Boolean: attribute REVCORP is compressed? */

  /** for compressed streams, the stream is a BStream object rather than just a pointer. */
  BStream bs;
  int b;                        /**< relevent for compressed streams */
  int last_pos;                 /**< relevent for compressed streams */

  /** pointer to base of stream for uncompressed streams. */
  int *base;

} PositionStreamRecord;



/**
 * Creates a new PositionStream object.
 *
 * @param attribute  The P-attribute to open the position stream on
 * @param id         The id that the new PositionStream will have.
 *                   This the id of an item on the specified attribute.
 * @return           The new object, or NULL in case of problem.
 */
PositionStream
cl_new_stream(Attribute *attribute, int id)
{
  Component *revcorp, *revcidx;
  int size, freq, range;

  PositionStream ps = NULL;

  check_arg(attribute, ATT_POS, NULL);

  size = cl_max_cpos(attribute);
  if (size <= 0 || !CL_ALL_OK())
    return NULL;

  range = cl_max_id(attribute);
  if (range <= 0 || !CL_ALL_OK())
    return NULL;

  if (id < 0 || id >= range) {
    cl_errno = CDA_EIDORNG;
    return NULL;
  }

  freq = cl_id2freq(attribute, id);
  if (freq < 0 || !CL_ALL_OK())
    return NULL;

  ps = (PositionStream)cl_malloc(sizeof(PositionStreamRecord));
  ps->attribute = attribute;
  ps->id = id;
  ps->id_freq = freq;
  ps->nr_items = 0;
  ps->is_compressed = 0;
  ps->b = 0; ps->last_pos = 0;
  ps->base = NULL;

  if (cl_index_compressed(attribute)) {
    int offset;
    ps->is_compressed = 1;

    revcorp = ensure_component(attribute, CompCompRF, 0);
    revcidx = ensure_component(attribute, CompCompRFX, 0);

    if (revcorp == NULL || revcidx == NULL) {
      cl_errno = CDA_ENODATA;
      cl_free(ps);
      return NULL;
    }

    ps->b = compute_ba(ps->id_freq, size);

    offset = ntohl(revcidx->data.data[id]); /* byte offset in RFC */

    BSopen((unsigned char *)revcorp->data.data, "r", &(ps->bs));
    BSseek(&(ps->bs), offset);

    ps->last_pos = 0;
  }
  else {
    ps->is_compressed = 0;

    revcorp = ensure_component(attribute, CompRevCorpus, 0);
    revcidx = ensure_component(attribute, CompRevCorpusIdx, 0);

    if (revcorp == NULL || revcidx == NULL) {
      cl_errno = CDA_ENODATA;
      cl_free(ps);
      return NULL;
    }

    ps->base = revcorp->data.data + ntohl(revcidx->data.data[ps->id]);
  }

  return ps;
}

/**
 * Deletes a PositionStream object.
 */
int
cl_delete_stream(PositionStream *ps)
{
  assert(ps && *ps);

  (*ps)->attribute = NULL;
  (*ps)->id = -1;
  (*ps)->id_freq = -1;
  (*ps)->nr_items = -1;
  (*ps)->is_compressed = 0;

  if ((*ps)->is_compressed) {
    BSclose(&((*ps)->bs));
    (*ps)->b = 0;
    (*ps)->last_pos = 0;
  }
  else
    (*ps)->base = NULL;

  cl_free(*ps);

  return 1;
}

/**
 * Reads corpus positions from a position stream to a buffer.
 *
 * @param ps           The position stream to read.
 * @param buffer       Location to put the resulting item positions.
 * @param buffer_size  Maximum number of item positions to read.
 *                     (Fewer will be read if fewer are available).
 * @return             The number of item positions that have been
 *                     read. This may be less than buffer_size (and
 *                     will be 0 if there are no instances of this item left).
 */
int
cl_read_stream(PositionStream ps, int *buffer, int buffer_size)
{
  int items_to_read, i;

  assert(ps);
  assert(buffer);

  /* return 0 if we have already read >= freq items */
  if (ps->nr_items >= ps->id_freq)
    return 0;

  if (ps->nr_items + buffer_size > ps->id_freq)
    items_to_read = ps->id_freq - ps->nr_items;
  else
    items_to_read = buffer_size;

  assert(items_to_read >= 0);

  if (items_to_read == 0)
    return 0;

  if (ps->is_compressed) {
    int gap;

    for (i = 0; i < items_to_read; i++, ps->nr_items++) {
      gap = read_golomb_code_bs(ps->b, &(ps->bs));
      ps->last_pos += gap;

      *buffer = ps->last_pos;
      buffer++;
    }
  }
  else {
    memcpy(buffer, ps->base + ps->nr_items, items_to_read * sizeof(int));

    ps->nr_items += items_to_read;

    /* convert network byte order to native integers */
    for (i = 0; i < items_to_read; i++)
      buffer[i] = ntohl(buffer[i]);
  }

  return items_to_read;
}








/**
 * Gets the integer ID of the item at the specified
 * position on the given p-attribute.
 *
 * @param attribute  The P-attribute to look on.
 * @param position   The corpus position to look at.
 * @return           The id of the item at that position
 *                   on this attribute, OR a negative value
 *                   if there is an error.
 */
int
cl_cpos2id(Attribute *attribute, int position)
{
  Component *corpus;

  check_arg(attribute, ATT_POS, cl_errno);

  /* note, we need to test against 1 because of possibility of error code */
  if (cl_sequence_compressed(attribute) == 1) {
    /*
     * we can use the compressed data
     */
    Component *cis;
    Component *cis_sync;
    Component *cis_map;
    BStream bs;

    unsigned char bit;
    int item;
    unsigned int block, rest, offset, max, v, l, i;

    if (COMPRESS_DEBUG > 1)
      fprintf(stderr, "Accessing position %d of %s via compressed item sequence\n", position, attribute->any.name);

    cis      = ensure_component(attribute, CompHuffSeq, 0);
    cis_map  = ensure_component(attribute, CompHuffCodes, 0);
    cis_sync = ensure_component(attribute, CompHuffSync, 0);

    if ((cis == NULL) || (cis_map == NULL) || (cis_sync == NULL)) {
      cl_errno = CDA_ENODATA;
      return CDA_ENODATA;
    }

    if ((position >= 0) && (position < attribute->pos.hc->length)) {

      block = position / SYNCHRONIZATION;
      rest  = position % SYNCHRONIZATION;

      if (attribute->pos.this_block_nr != block) {

        /* the current block in the decompression buffer is not the
         * block we need. So we read the proper block into the buffer
         * and hope that we'll get a cache hit next time. */

        if (COMPRESS_DEBUG > 0)
          fprintf(stderr, "Block miss: have %d, want %d\n", attribute->pos.this_block_nr, block);

        /* is the block we read the last block of the corpus? Then, we
         * cannot read SYNC items, but only as much as there are left.
         * */

        max = attribute->pos.hc->length - block * SYNCHRONIZATION;
        if (max > SYNCHRONIZATION)
          max = SYNCHRONIZATION;

        attribute->pos.this_block_nr = block;

        offset = ntohl(cis_sync->data.data[block]);

        if (COMPRESS_DEBUG > 1)
          fprintf(stderr, "-> Block %d, rest %d, offset %d\n", block, rest, offset);

        BSopen((unsigned char *)cis->data.data, "r", &bs);
        BSseek(&bs, offset);

        for (i = 0; i < max; i++) {
          if (!BSread(&bit, 1, &bs)) {
            fprintf(stderr, "cdaccess:decompressed read: Read error/1\n");
            return cl_errno = CDA_ENODATA;
          }

          v = (bit ? 1 : 0);
          l = 1;

          while (v < attribute->pos.hc->min_code[l]) {
            if (!BSread(&bit, 1, &bs)) {
              fprintf(stderr, "cdaccess:decompressed read: Read error/2\n");
              return cl_errno = CDA_ENODATA;
            }

            v <<= 1;
            if (bit)
              v++;
            l++;
          }

          /* we now have the item - store it in the decompression block */
          item = ntohl(attribute->pos.hc->symbols[attribute->pos.hc->symindex[l] + v - attribute->pos.hc->min_code[l]]);
          attribute->pos.this_block[i] = item;
        }

        BSclose(&bs);

      }
      else if (COMPRESS_DEBUG > 0)
        fprintf(stderr, "Block hit: block[%d,%d]\n", block, rest);

      assert(rest < SYNCHRONIZATION);

      cl_errno = CDA_OK;         /* hi 'Oli' ! */
      return attribute->pos.this_block[rest];
    }
    else
      return cl_errno = CDA_EPOSORNG;
  }
  else {
    /*
     * we can't or shouldn't use the compressed data
     */
    if (!(corpus = ensure_component(attribute, CompCorpus, 0)))
      return cl_errno = CDA_ENODATA;

    if (!(position >= 0 && position < corpus->size) )
      return cl_errno = CDA_EPOSORNG;

    cl_errno = CDA_OK;
    return ntohl(corpus->data.data[position]);
  }
}


/**
 * Gets the string of the item at the specified
 * position on the given p-attribute.
 *
 * @param attribute  The P-attribute to look on.
 * @param position   The corpus position to look at.
 * @return           The string of the item at that position
 *                   on this attribute (pointer to actual data within the
 *                   attribute, DO NOT FREE!), or NULL
 *                   if there is an error.
 */
char *
cl_cpos2str(Attribute *attribute, int position)
{
  int id;

  check_arg(attribute, ATT_POS, NULL);

  if (0 > (id = cl_cpos2id(attribute, position)) || !CL_ALL_OK())
    return NULL;

  return cl_id2str(attribute, id);
}





/* ========== some high-level constructs */

/**
 * Gets the string of the item with the specified
 * ID on the given p-attribute.
 *
 * As well as returning the string, other information
 * about the item is inserted into locations specified
 * by other parameters.
 *
 * @param attribute  The P-attribute to look on.
 * @param index      The ID of the item to look at.
 * @param freq       Will be set to the frequency of the item.
 * @param slen       Will be set to the string-length of the item.
 * @return           The string of the item at that position
 *                   on this attribute, OR NULL
 *                   if there is an error.
 */
char *
cl_id2all(Attribute *attribute, int index, int *freq, int *slen)
{
  check_arg(attribute, ATT_POS, NULL);

  *freq = cl_id2freq(attribute, index);
  if (*freq < 0 || !CL_ALL_OK())
    return NULL;

  *slen = cl_id2strlen(attribute, index);
  if (*slen < 0 || !CL_ALL_OK())
    return NULL;

  return cl_id2str(attribute, index);
}



/**
 * Gets a list of the ids of those items on a given Attribute that
 * match a particular regular-expression pattern.
 *
 * The pattern is interpreted internally with the CL regex engine, q.v.
 *
 * The function returns a pointer to a sequence of ints of size number_of_matches. The list
 * is allocated with malloc(), so do a cl_free() when you don't need it any more.
 *
 * @see cl_new_regex
 * @param attribute          The p-attribute to look on.
 * @param pattern            String containing the pattern against which to match each item on the attribute.
 *                           Note: this pattern is a regular expression, but it is passed as a string, not
 *                           a CL_Regex object. The CL_Regex object is created internally.
 * @param flags              Flags for the regular expression system via cl_new_regex.
 * @param number_of_matches  This is set to the number of item ids found, i.e. the size of the returned buffer.
 * @return                   A pointer to the list of item ids. Will be NULL if nothing was found.
 */
int *
cl_regex2id(Attribute *attribute, char *pattern, int flags, int *number_of_matches)
{
/* 2019-08-15 : cleared up an old to-do: "might move bitmap to static variable and re-allocate only when necessary";
 * in case I've mucked it up, it is only conditionally implemented; when following line is commented, it will switch off - AH */
#define STATIC_BITMAP_IN_USE

  /* the lexicon of the attribute we are searching on. */
  Component *lex;

  /* convenience variables: copied out of "lex" for sake of easy reference. */
  Component *lexidx;
  int *lexidx_data;
  char *lex_data;
  int lexsize;

  CL_Regex rx;
  int /*regex_result,*/ idx/*, len*/; // only need one index now.
  int optimised/*, grain_match*/;     // we don't track grain matching now.

  /* results are stored as a a bitmap: one bit per lexicon item; this reduce memory footprint and avoids frequent realloc();
   * it would also be possible to use a Bitfield, see <bitfield.h>, but this custom implementation is somewhat more efficient */
#ifdef STATIC_BITMAP_IN_USE
  static unsigned char *bitmap = NULL;
  static int bitmap_size = -1;  /* size of allocated bitmap in bytes; initial -1 flags that the bitmap has not been allocated. */
#else
  unsigned char *bitmap = NULL;
  int bitmap_size;              /* size of allocated bitmap in bytes */
#endif
  int bitmap_offset;            /* current bitmap offset (in bytes) */
  unsigned char bitmap_mask;    /* current bitmap offset (within-byte part, as bit mask) */

  int *table = NULL;            /* list of matching IDs */
  int match_count = 0;          /* count matches in local variable while scanning */

  /*char *word, *preprocessed_string;   Formerly used ot set up the argument passed to the regx func. */

  check_arg(attribute, ATT_POS, NULL);

  lexidx = ensure_component(attribute, CompLexiconIdx, 0);
  lex    = ensure_component(attribute, CompLexicon, 0);

  if (!(lexidx && lex)) {
    cl_errno = CDA_ENODATA;
    return NULL;
  }

  lexsize     = lexidx->size;
  lexidx_data = (int *)lexidx->data.data;
  lex_data    = (char *)lex->data.data;
  match_count = 0;

  if (!(rx = cl_new_regex(pattern, flags, attribute->pos.mother->charset))) {
    fprintf(stderr, "Regex Compile Error: %s\n", cl_regex_error);
    cl_errno = CDA_EBADREGEX;
    return NULL;
  }
  optimised = cl_regex_optimised(rx);

#ifdef STATIC_BITMAP_IN_USE
  if (-1 == bitmap_size) {
    /* the static bitmap to record the matching IDs has not been allocated. So, do that! */
    bitmap_size = (lexsize + 7) / 8;  /* the exact number of bytes needed */
    bitmap = (unsigned char *)cl_calloc(bitmap_size, sizeof(unsigned char));
    /* note use of calloc so that the whole mem block is set to 0 at the outset (== "no matches") */
  }
  else {
    /* we only need to reallocate if the buffer no longer matches the calcualtion based on the lexsize */
    int calc = (lexsize + 7) / 8;
    if (calc != bitmap_size)
      bitmap = (unsigned char *)cl_realloc(bitmap, (bitmap_size = calc) * sizeof(unsigned char));
    /* either way, we need to scrub all memory to zero. */
    memset(bitmap, 0, bitmap_size);
  }
#else
  /* allocate bitmap for matching IDs */
  bitmap_size = (lexsize + 7) / 8;  /* this is the exact number of bytes needed, I hope */
  bitmap = (unsigned char *)cl_calloc(bitmap_size, sizeof(unsigned char)); /* initialise: no bits set */
#endif

  bitmap_offset = 0;
  bitmap_mask = 0x80;           /* start with MSB of first byte */

  cl_regopt_count_reset();      /* report how often we have a grain match when using optimised search */

  /* for each index in the lexicon... */
  for (idx = 0; idx < lexsize; idx++) {
    /*int off_start, off_end;*/     /* start and end offset of current lexicon entry */

    /* compute start offset and length of current lexicon entry from lexidx, if possible)
     *  -- no longer necessary because we can't pass len to cl_regex_match
     *  -- although 'twould be a good optimisation if possible to avoid calling strlen
     *  -- pass in via a global variable cl_regopt_haystack_strlenin?
     *  ... so present code only calculates the offset (directly, at the point it's passed into cl_regex_match).
     *  ... this whole middle bit is commented out.
     */
    /*
    off_start = ntohl(lexidx_data[idx]);
    word = lex_data + off_start;
    if (idx < lexsize-1) {
      / * off_end = ntohl(lexidx_data[idx + 1]) - 1; * /
      / * len = off_end - off_start; * /
    }
    else {
      / * len = strlen(word); * /
    }
    */

    /*if (cl_regex_match(rx, word, 0)) { */   /* regex match (old version with word var which is no longer reused */
    if (cl_regex_match(rx, lex_data + ntohl(lexidx_data[idx]), 0)) {
      /* we have a regex match ! so set the bit that corresponds to the lexicon ID stored in idx. */
      bitmap[bitmap_offset] |= bitmap_mask;
      match_count++;
    }
    /* match or no match, shift bitmask along one bit; roll back to 0x80 and increment the offset if we've done all bits of this byte. */
    bitmap_mask >>= 1;
    if (0 == bitmap_mask) {
      bitmap_offset++;
      bitmap_mask = 0x80;
    }
  }

  if (cl_debug && optimised)
    fprintf(stderr, "CL: regexp optimiser avoided calling regex engine for %d candidates out of %d strings\n"
                    "    (%d matching strings in total) \n",
                    cl_regopt_count_get(), lexsize, match_count);

  /* table was initialised to NULL, and will stay NULL till returned if there were zero matches;
     but if there WERE matches, we put into it a list of matching IDs from the bitmap. */
  if (match_count) {
    int lex_id;
    table = (int *)cl_malloc(match_count * sizeof(int));
    bitmap_offset = 0;
    bitmap_mask = 0x80;

    /* in the following loop, idx is the index into the table;
     * lex_id is both the lexicon-ID and the index into the bitmap. */
    for (idx = 0, lex_id = 0; lex_id < lexsize; lex_id++) {
      if (bitmap[bitmap_offset] & bitmap_mask)
        table[idx++] = lex_id;
      bitmap_mask >>= 1;
      if (bitmap_mask == 0) {
        bitmap_offset++;
        bitmap_mask = 0x80;
      }
    }
    assert(idx == match_count && "cl_regex2id(): bitmap inconsistency");
  }

  assert (number_of_matches && "cl_regex2id(): lacking out-parameter for return size (number_of_matches)");
  *number_of_matches = match_count;

#ifndef STATIC_BITMAP_IN_USE
  cl_free(bitmap);
#endif
  cl_delete_regex(rx);
  cl_errno = CDA_OK;

  return table;
}


/**
 * Calculates the total frequency of all items on a list of item IDs.
 *
 * This function returns the sum of the word frequencies of words,
 * which is an array of word_ids with length number_of_words.
 *
 * The result is therefore the number of corpus positions which
 * match one of the words.
 *
 * @param attribute        P-attribute on which these items are found.
 * @param word_ids         An array of item IDs.
 * @param number_of_words  Length of the word_ids array.
 * @return                 Sum of all the frequencies; less than 0 for an error.
 */

int
cl_idlist2freq(Attribute *attribute, int *word_ids, int number_of_words)
{
  int k, sum;

  check_arg(attribute, ATT_POS, cl_errno);

  if (!word_ids)
    return cl_errno = CDA_ENODATA;

  for (sum = 0, k = 0; k < number_of_words; k++) {
    sum += cl_id2freq(attribute, word_ids[k]);
    if (!CL_ALL_OK())
      return cl_errno;
  }

  cl_errno = CDA_OK;
  return sum;
}

/* this is the way qsort(..) is meant to be used: give it void * args
   and cast them to the actual type in the compare function;
   this definition conforms to ANSI and POSIX standards according to the LDP */
/** internal function for use with qsort */
static int intcompare(const void *i, const void *j)
{
  return(*(int *)i - *(int *)j);
}
/* this is used in the following function, thus why it's here. */


/**
 * Gets a list of corpus positions matching a list of ids.
 *
 * This function returns an (ordered) list of all corpus positions which
 * match one of the ids given in the list of ids. The table is allocated
 * with malloc, so free it when you don't need any more.
 *
 * The list itself is returned; its size is placed in size_of_table.
 * This size is, of course, the same as the cumulative id frequency
 * of the ids (because each corpus position matching one of the ids
 * is added into the list).
 *
 * BEWARE: when the id list is rather big or there are highly-frequent
 * ids in the id list (for example, after a call to collect_matching_ids
 * with the pattern ".*") this will give a copy of the corpus -- for
 * which you probably don't have enough memory!!! It is therefore a good
 * idea to call cumulative_id_frequency before and to introduce some
 * kind of bias.
 *
 * This function is DEPRACATED in favour of cl_idlist2cpos().
 *
 * This function is "oldstyle" because it has the "restrictor list"
 * parameters, which are not available through the "newstyle" function
 * cl_idlist2cpos() (which is currently just a macro to this).
 *
 * A note on the last two parameters, which are currently unused:
 * restrictor_list is a list of integer pairs [a,b] which means that
 * the returned value only contains positions which fall within at
 * least one of these intervals. The list must be sorted by the start
 * positions, and secondarily by b. restrictor_list_size is the number of
 * integers in this list, NOT THE NUMBER OF PAIRS.
 * WARNING: CURRENTLY UNIMPLEMENTED
 * {NB -- this description of restrictor_list_size DOESN'T MATCH
 * the one for get_positions(), which this function calls...
 *
 * REMEMBER: this monster returns a list of corpus indices, not a list
 * of ids.
 *
 * @see collect_matching_ids
 * @see get_positions
 * @see cl_idlist2cpos
 *
 * @param attribute             The P-attribute we are looking in
 * @param word_ids              A list of item ids (i.e. id codes for
 *                              items on this attribute).
 * @param number_of_words       The length of this list.
 * @param sort                  boolean: return sorted list?
 * @param size_of_table         The size of the allocated table will be
 *                              placed here.
 * @param restrictor_list       See function description.
 * @param restrictor_list_size  See function description.
 * @return                      Pointer to the list of corpus positions.
 *                              NULL in case of error.
 */
int *
cl_idlist2cpos_oldstyle(Attribute *attribute,
                        int *word_ids,
                        int number_of_words,
                        int sort,
                        int *size_of_table,
                        int *restrictor_list,
                        int restrictor_list_size)
{
  int size, k, p, word_id, freq;
  int *table, *start;
  Component *lexidx;

  check_arg(attribute, ATT_POS, NULL);

  *size_of_table = 0;

  lexidx = ensure_component(attribute, CompLexiconIdx, 0);

  if (!(lexidx && word_ids)) {
    cl_errno = CDA_ENODATA;
    return NULL;
  }

  size = cl_idlist2freq(attribute, word_ids, number_of_words);
  if (size < 0 || !CL_ALL_OK())
    return NULL;

  if (size > 0) {
    table = (int *)cl_malloc(size * sizeof(int));
    p = 0;

    for (k = 0; k < number_of_words; k++) {
      word_id = word_ids[k];

      if ((word_id < 0) || (word_id >= lexidx->size)) {
        cl_errno = CDA_EIDORNG;
        cl_free(table);
        return NULL;
      }

      start = cl_id2cpos(attribute, word_id, &freq);
      if ((freq < 0) || !CL_ALL_OK()) {
        cl_free(table);
        return NULL;
      }

      /* let's hack: */
      memcpy(&table[p], start, freq * sizeof(int));
      p += freq;

      cl_free(start);
    }

    assert(p == size);

    if (sort)
      qsort(table, size, sizeof(int), intcompare);

    *size_of_table = size;
    cl_errno = CDA_OK;
    return table;
  }
  else {
    *size_of_table = 0;
    cl_errno = CDA_OK;
    return NULL;
  }

  assert("Not reached" && 0);
  return NULL;
}



/* ================================================== UTILITY FUNCTION FOR S-ATTS */

/**
 * Gets a pointer to the location where a structure is stored.
 *
 * The structure (instance of an s-attribute) that is found
 * is the one in which the specified corpus position occurs.
 *
 * @param data      "data.data" member of an s-attribute
 * @param size      "size" member of the same s-attribute
 * @param position  The corpus position to look for.
 * @return          Pointer to the integers in data where
 *                  the start point of the structure at this
 *                  corpus position can be found. NULL if
 *                  not found.
 */
static int *
get_previous_mark(int *data, int size, int position)
{
  int nr = 0;
  int mid, comp;
  int max = size/2;
  int low = 0, high = max - 1;

  while (low <= high) {
    if (++nr > 100000) {
      fprintf(stderr, "Binary search in get_surrounding_positions failed\n");
      return NULL;
    }

    mid = (low + high)/2;

    comp = position - ntohl(data[mid*2]);

    if (comp == 0)
      return &data[mid*2];
    else if (comp > 0) {
      if (position <= ntohl(data[mid*2+1]))
        return &data[mid*2];
      else
        low = mid + 1;
    }
    else if (mid == low) {
      /* fail; */
      return NULL;
    }
    else /* comp < 0 */
      high = mid - 1;
  }
  return NULL;
}



/* ================================================== STRUCTURAL ATTRIBUTES */

/* first, some new style functions with normalised behaviour */

/**
 * Gets the ID number of a structure (instance of an s-attribute)
 * that is found at the given corpus position.
 *
 * This is a wrapper of the "old" function cl_cpos2struc_oldstyle() that
 * normalises it to standard return value behaviour.
 *
 * @param a      The s-attribute on which to search.
 * @param cpos   The corpus position to look for.
 * @return       The number of the structure that is found.
 *               Or, a negative number for an error code.
 */
int
cl_cpos2struc(Attribute *a, int cpos)
{
  int struc = -1;
  if (cl_cpos2struc_oldstyle(a, cpos, &struc))
    return struc;
  else
    return cl_errno;
}

/**
 * Compares the location of a corpus position to
 * the regions of an s-attribute.
 *
 * This determines whether the specified corpus position
 * is within a region (i.e. a structure, an instance of
 * that s-attribute) on the given s-attribute; and/or on
 * a boundary; or outside a region.
 *
 * @see STRUC_INSIDE
 * @see STRUC_LBOUND
 * @see STRUC_RBOUND
 *
 * @param a      The s-attribute on which to search.
 * @param cpos   The corpus position to look for.
 * @return       0 if this position is outside a region;
 *               some combination of flags if it is within
 *               a region or on a bound; or a negative
 *               number (error code) in case of error.
 */
int
cl_cpos2boundary(Attribute *a, int cpos)
{
  /* convenience function: within region or at boundary? */
  int start = -1, end = -1;

  if (cl_cpos2struc2cpos(a, cpos, &start, &end)) {
    int flags = STRUC_INSIDE;
    if (cpos == start)
      flags |= STRUC_LBOUND;
    if (cpos == end)
      flags |= STRUC_RBOUND;
    return flags;
  }

  if (cl_errno == CDA_ESTRUC)
    return 0; /* outside region */

  return cl_errno; /* some error occurred */
}


/**
 * Gets the maximum for this S-attribute (ie the size of the
 * S-attribute).
 *
 * The result of this function is equal to the number of instances
 * of this s-attribute in the corpus.
 *
 * This function works as a wrapper round cl_max_struc_oldstyle that
 * normalises it to standard return value behaviour.
 *
 * @a       The s-attribute to evaluate.
 * @return  The maximum corpus position, or an error code (if less than 0)
 */
int
cl_max_struc(Attribute *a)
{
  int nr = -1;
  if (cl_max_struc_oldstyle(a, &nr))
    return nr;
  else
    return cl_errno;
}


/**
 * Gets the start and end positions of the instance of the given S-attribute
 * found at the specified corpus position.
 *
 * This function finds one particular instance of the S-attribute, and assigns
 * its start and end points to the locations given as arguments.
 *
 * @param attribute    The s-attribute to search.
 * @param position     The corpus position to search for.
 * @param struc_start  Location for the start position of the instance.
 * @param struc_end    Location for the end position of the instance.
 *
 * @return             Boolean: true for all OK, false for error.
 *
 */
int
cl_cpos2struc2cpos(Attribute *attribute, int position, int *struc_start, int *struc_end)
{
  Component *struc_data;
  int *val;

  check_arg(attribute, ATT_STRUC, cl_errno);

  *struc_start = 0;
  *struc_end   = 0;

  struc_data = ensure_component(attribute, CompStrucData, 0);

  if (!struc_data) {
    cl_errno = CDA_ENODATA;
    return 0;
  }

  val = get_previous_mark(struc_data->data.data, struc_data->size, position);

  if (!val) {
    cl_errno = CDA_ESTRUC;
    return 0;
  }

  *struc_start = ntohl(*val);
  *struc_end   = ntohl(*(val + 1));
  cl_errno = CDA_OK;
  return 1;
}

/**
 * Gets the ID number of a structure (instance of an s-attribute)
 * that is found at the given corpus position.
 *
 * Depracated function: use cl_cpos2struc.
 *
 * @see cl_cpos2struc
 *
 * @param attribute  The s-attribute on which to search.
 * @param position   The corpus position to look for.
 * @param struc_num  Location where the number of the structure that
 *                   is found will be put.
 * @return           Boolean: true for all OK, false for error.
 */
int
cl_cpos2struc_oldstyle(Attribute *attribute, int position, int *struc_num)
{
  Component *struc_data;
  int *val;

  check_arg(attribute, ATT_STRUC, cl_errno);

  struc_data = ensure_component(attribute, CompStrucData, 0);

  if (!struc_data) {
    cl_errno = CDA_ENODATA;
    return 0;
  }

  val = get_previous_mark(struc_data->data.data, struc_data->size, position);

  if (!val) {
    cl_errno = CDA_ESTRUC;
    return 0;
  }

  *struc_num = (val - struc_data->data.data)/2;
  cl_errno = CDA_OK;
  return 1;
}

/**
 * Retrieves the start-and-end corpus positions of a specified structure
 * of the given s-attribute type.
 *
 * @param attribute    An s-attribute.
 * @param struc_num    The instance of that s-attribute to retrieve
 *                     (i.e. the struc_num'th instance of this s-attribute
 *                     in the corpus).
 * @param struc_start  Location to put the starting corpus position.
 * @param struc_end    Location to put the ending corpus position.
 * @return             boolean: true for all OK, 0 for problem
 */
int
cl_struc2cpos(Attribute *attribute, int struc_num, int *struc_start, int *struc_end)
{
  Component *struc_data;

  check_arg(attribute, ATT_STRUC, cl_errno);

  struc_data = ensure_component(attribute, CompStrucData, 0);

  if (!struc_data) {
    cl_errno = CDA_ENODATA;
    return 0;
  }

  if (struc_num < 0 || struc_num >= (struc_data->size / 2)) {
    cl_errno = CDA_EIDXORNG;
    return 0;
  }

  *struc_start = ntohl(struc_data->data.data[struc_num * 2]);
  *struc_end   = ntohl(struc_data->data.data[(struc_num * 2)+1]);
  cl_errno = CDA_OK;
  return 1;
}


/**
 * Gets the number of instances of an s-attribute in the corpus.
 *
 * Depracated: use cl_max_struc instead.
 *
 * @see cl_max_struc.
 *
 * @param attribute    The s-attribute to count.
 * @param nr_strucs    The number of instances is put here.
 * @return             boolean: true for all OK, false for problem.
 */
int
cl_max_struc_oldstyle(Attribute *attribute, int *nr_strucs)
{
  Component *struc_data;

  check_arg(attribute, ATT_STRUC, cl_errno);

  struc_data = ensure_component(attribute, CompStrucData, 0);

  if (!struc_data) {
    cl_errno = CDA_ENODATA;
    return 0;
  }

  *nr_strucs = struc_data->size / 2;
  cl_errno = CDA_OK;
  return 1;
}

/**
 * Checks whether this s-attribute has attribute values.
 *
 * @return Boolean.
 */
int
cl_struc_values(Attribute *attribute)
{
  check_arg(attribute, ATT_STRUC, cl_errno);

  if (attribute->struc.has_attribute_values < 0) {
    /* if h_a_v < 0 then we didn't yet test whether it has values or not */
    ComponentState avs_state, avx_state;
    avs_state = component_state(attribute, CompStrucAVS);
    avx_state = component_state(attribute, CompStrucAVX);
    attribute->struc.has_attribute_values = (compstate_data_available(avs_state) && compstate_data_available(avx_state));
  }

  cl_errno = CDA_OK;
  return attribute->struc.has_attribute_values;
}



/**
 * A non-exported function used by cl_struc2str
 */
static int
s_v_comp(const void *v1, const void *v2)
{
  return ntohl(*((int *)v1)) - ntohl(*((int *)v2));
}


/**
 * Gets the value that is associated with the specified instance
 * of the given s-attribute.
 *
 * @param attribute  An S-attribute.
 * @param struc_num  ID of the structure whose value is wanted
 *                   (ie, function gets value of struc_num'th
 *                   instance of this s-attribute)
 * @return           A string; or NULL in case of error. Note that
 *                   this string is a pointer to the depths of the
 *                   Attribute object itself, as this function does
 *                   not strdup() its result -- so don't free this
 *                   return value!
 *
 */
char *
cl_struc2str(Attribute *attribute, int struc_num)
{
  /* local structure */
  typedef struct _idx_el {
    int id;
    int offset;
  } IndexElement;

  Component *avs;
  Component *avx;

  IndexElement key, *idx;

  int offset;

  check_arg(attribute, ATT_STRUC, NULL);

  if (!(cl_struc_values(attribute) && CL_ALL_OK()))
    return NULL;

  avs = ensure_component(attribute, CompStrucAVS, 0);
  avx = ensure_component(attribute, CompStrucAVX, 0);

  if (!(avs && avx)) {
    cl_errno = CDA_ENODATA;
    return NULL;
  }

  key.id = htonl(struc_num);

  /* current redundant file format allows regions without annotations, so the index file (avx)
   * consists of (region index, ptr) pairs, where ptr is an offset into the lexicon file (avs)
   */
  idx = (IndexElement *)bsearch(&key, avx->data.data, avx->size / 2,  2 * sizeof(int), s_v_comp);
  if (!idx) {
    /* we don't allow regions with missing annotations, so this must be an index error */
    cl_errno = CDA_EIDXORNG;
    return NULL;
  }

  offset = ntohl(idx->offset);

  if (!(offset >= 0 && offset < avs->data.size)) {
    cl_errno = CDA_EINTERNAL; /* this is a bad data inconsistency! */
    return NULL;
  }

  cl_errno = CDA_OK;
  return (char *)(avs->data.data) + offset;
}


/**
 * Gets the value associated with the instance of the given s-attribute
 * that occurs at the specified corpus position.
 *
 * @param struc     The s-attribute to search through.
 * @param position  The corpus position being queried.
 * @return          The value of the instance of the s-attribute,
 *                  or NULL for error.
 */
char *
cl_cpos2struc2str(Attribute *struc, int position)
{
  int snum;

  if (!struc || CDA_OK > (snum = cl_cpos2struc(struc, position)))
    return NULL;

  return cl_struc2str(struc, snum);
}





/* ================================================== ALIGNMENT ATTRIBUTES */

/**
 * Gets the id number of the alignment at the specified corpus position.
 *
 * For use with non-extended alignments.
 *
 * Requires members of the ALIGN component as arguments.
 *
 * @see cl_cpos2alg
 * @see get_extended_alignment
 *
 * @param data      The data member of a CompAlignData component.
 * @param size      The size member of the same CompAlignData component.
 * @param position  The corpus position to look at.
 * @return          The id of the alignment at this corpus position,
 *                  or -1 for error.
 */
static int
get_alignment(int *data, int size, int position)
{
  int nr;
  int mid, high, low, comp;
  int max = size/2;

  /* organisation of ALIGN component is
       source boundary #1
       target boundary #1
       source boundary #2
       target boundary #2
       ...
  */

  low = 0;
  nr = 0;
  high = max - 1;

  while (low <= high) {
    nr++;
    if (nr > 100000) {
      fprintf(stderr, "Binary search in get_alignment failed\n");
      return -1;
    }

    mid = (low + high)/2;

    comp = position - ntohl(data[mid*2]);
    if (comp == 0)
      return mid;
    else if (comp > 0) {
      if ((mid*2 < size) && (position < ntohl(data[(mid+1)*2])))
        return mid;
      else
        low = mid + 1;
    }
    else if (mid == low) {
      /* fail; */
      return -1;
    }
    else /* comp < 0 */
      high = mid - 1;
  }
  return -1;
}

/**
 * Gets the id number of the alignment at the specified corpus position.
 *
 * For use with extended alignments.
 *
 * Requires members of the XALIGN component as arguments.
 *
 * @see cl_cpos2alg
 * @see get_alignment
 *
 * @param data      The data member of a CompXAlignData component.
 * @param size      The size member of the same CompXAlignData component.
 * @param position  The corpus position to look at.
 * @return          The id of the alignment at this corpus position,
 *                  or -1 for error.
 */
static int
get_extended_alignment(int *data, int size, int position)
{
  int nr;
  int mid, high, low, start, end;
  int max = size/4;

  /* organisation of XALIGN component is
       source region #1 start
       source region #1 end
       target region #1 start
       target region #1 end
       source region #2 start
       ...
  */

  low = 0;
  nr = 0;
  high = max - 1;

  while (low <= high) {
    nr++;
    if (nr > 100000) {
      fprintf(stderr, "Binary search in get_extended_alignment failed\n");
      return -1;
    }

    mid = (low + high)/2;

    start = ntohl(data[mid*4]);
    end = ntohl(data[mid*4 + 1]);
    if (start <= position) {
      if (position <= end) {
        return mid;             /* return nr of alignment region */
      }
      else {
        low = mid + 1;
      }
    }
    else {
      high = mid - 1;
    }

  }
  return CDA_EALIGN;    /* high < low --> search failed  */
}


/**
 * Gets the corpus positions of an alignment on the given align-attribute.
 *
 * This is for old-style alignments only: it doesn't (can't) deal with
 * extended alignments. Depracated: use cl_alg2cpos instead (but note its
 * parameters are not identical).
 *
 * @see cl_alg2cpos.
 *
 * @param attribute             The align-attribute to look on.
 * @param position              The corpus position {??} of the alignment whose positions are wanted.
 * @param source_corpus_start   Location to put source corpus start position.
 * @param source_corpus_end     Location to put source corpus end position.
 * @param aligned_corpus_start  Location to put target corpus start position.
 * @param aligned_corpus_end    Location to put target corpus end position.
 * @return                      Boolean: true = all OK, false = problem.
 */
int
cl_cpos2alg2cpos_oldstyle(Attribute *attribute,
                          int position,
                          int *source_corpus_start,
                          int *source_corpus_end,
                          int *aligned_corpus_start,
                          int *aligned_corpus_end)
{
  int *val;
  int alg;                      /* nr of alignment region */

  Component *align_data;

  check_arg(attribute, ATT_ALIGN, cl_errno);

  *source_corpus_start = -1;
  *aligned_corpus_start = -1;
  *source_corpus_end = -1;
  *aligned_corpus_end = -1;

  align_data = ensure_component(attribute, CompAlignData, 0);

  if (align_data == NULL) {
    cl_errno = CDA_ENODATA;
    return 0;
  }

  alg = get_alignment(align_data->data.data,
                      align_data->size,
                      position);
  if (alg >= 0) {
    val = align_data->data.data + (alg * 2);
    *source_corpus_start  = ntohl(val[0]);
    *aligned_corpus_start = ntohl(val[1]);

    if (val + 3 - align_data->data.data >= align_data->size) {
      *source_corpus_end = -1;
      *aligned_corpus_end = -1;
    }
    else {
      *source_corpus_end  = ntohl(val[2])-1;
      *aligned_corpus_end = ntohl(val[3])-1;
    }

    cl_errno = CDA_OK;
    return 1;
  }

  /* if we are here: alg was less than 0 (cpos out of range) */
  cl_errno = CDA_EPOSORNG;
  return 0;
}

/**
 * Checks whether an attribute's XALIGN component exists,
 * that is, whether or not it has extended alignment.
 *
 * @param attribute  An align-attribute.
 * @return           Boolean.
 */
int
cl_has_extended_alignment(Attribute *attribute)
{
  ComponentState xalign;

  check_arg(attribute, ATT_ALIGN, cl_errno);

  xalign = component_state(attribute, CompXAlignData);

  /* Does XALIGN component exist? */
  return compstate_data_available(xalign);
}


/**
 * Gets the number of alignments on this align-attribute
 *
 * This is equal to the maximum alignment on this attribute.
 *
 * @param attribute  An align-attribute.
 * @return           The number of alignments on this attribute.
 */
int
cl_max_alg(Attribute *attribute)
{
  Component *align_data;

  /* call to cl_has_extended_alignment subsumes check_arg() */

  if (!cl_has_extended_alignment(attribute)) {
    /* using the non-extended component */
    align_data = ensure_component(attribute, CompAlignData, 0);

    if (align_data == NULL)
      return cl_errno = CDA_ENODATA;

    cl_errno = CDA_OK;
    return (align_data->size / 2) - 1; /* last alignment boundary doesn't correspond to region */
  }
  else {
    /* using the extended component */
    align_data = ensure_component(attribute, CompXAlignData, 0);

    if (align_data == NULL)
      return cl_errno = CDA_ENODATA;

    cl_errno = CDA_OK;
    return (align_data->size / 4);
  }
}


/**
 * Gets the id number of the alignment at the specified corpus position.
 *
 * @param attribute  The align-attribute to look on.
 * @param cpos       The corpus position to look at.
 * @return           The id number of the alignment at this position,
 *                   or a negative int error code.
 */
int
cl_cpos2alg(Attribute *attribute, int cpos)
{
  int alg;
  Component *align_data;

  /* call to cl_has_extended_alignment subsumes check_arg() */
  if (! cl_has_extended_alignment(attribute)) {
    /* using the non-extended component */
    align_data = ensure_component(attribute, CompAlignData, 0);
    if (align_data == NULL)
      return cl_errno = CDA_ENODATA;

    alg = get_alignment(align_data->data.data, align_data->size, cpos);
    if (alg >= 0) {
      cl_errno = CDA_OK;
      return alg;
    }
    else
      return cl_errno = CDA_EPOSORNG; /* old alignment files don't allow gaps -> index error */
  }
  else {
    /* using the extended component */
    align_data = ensure_component(attribute, CompXAlignData, 0);
    if (align_data == NULL)
      return cl_errno = CDA_ENODATA;

    alg = get_extended_alignment(align_data->data.data, align_data->size, cpos);
    if (alg >= 0) {
      cl_errno = CDA_OK;
      return alg;
    }
    else {
      cl_errno = CDA_EALIGN;
      return alg;               /* not a real error (just an "exception" condition) */
    }
  }
}


/**
 * Gets the corpus positions of an alignment on the given align-attribute.
 *
 * Note that four corpus positions are retrieved, into the addresses
 * given as parameters.
 *
 * @param attribute            The align-attribute to look on.
 * @param alg                  The ID of the alignment whose positions are wanted.
 * @param source_region_start  Location to put source corpus start position.
 * @param source_region_end    Location to put source corpus end position.
 * @param target_region_start  Location to put target corpus start position.
 * @param target_region_end    Location to put target corpus end position.
 * @return                     Boolean: true = all OK, false = problem.
 */
int
cl_alg2cpos(Attribute *attribute,
            int alg,
            int *source_region_start,
            int *source_region_end,
            int *target_region_start,
            int *target_region_end)
{
  int *val, size;
  Component *align_data;

  *source_region_start = -1;
  *target_region_start = -1;
  *source_region_end = -1;
  *target_region_end = -1;

  /* call to cl_has_extended_alignment subsumes check_arg() */
  if (! cl_has_extended_alignment(attribute)) {
    align_data = ensure_component(attribute, CompAlignData, 0);
    if (align_data == NULL) {
      cl_errno = CDA_ENODATA;
      return 0;
    }
    size = (align_data->size / 2) - 1; /* last alignment boundary doesn't correspond to region */
    if (alg < 0 || alg >= size) {
      cl_errno = CDA_EIDXORNG;
      return 0;
    }

    val = align_data->data.data + (alg * 2);
    *source_region_start  = ntohl(val[0]);
    *target_region_start  = ntohl(val[1]);
    *source_region_end    = ntohl(val[2]) - 1;
    *target_region_end    = ntohl(val[3]) - 1;
    cl_errno = CDA_OK;
    return 1;
  }
  else  {
    align_data = ensure_component(attribute, CompXAlignData, 0);
    if (align_data == NULL) {
      cl_errno = CDA_ENODATA;
      return 0;
    }
    size = align_data->size / 4;
    if (alg < 0 || alg >= size) {
      cl_errno = CDA_EIDXORNG;
      return 0;
    }

    val = align_data->data.data + (alg * 4);
    *source_region_start  = ntohl(val[0]);
    *source_region_end    = ntohl(val[1]);
    *target_region_start  = ntohl(val[2]);
    *target_region_end    = ntohl(val[3]);
    cl_errno = CDA_OK;
    return 1;
  }
}



/* ================================================== DYNAMIC ATTRIBUTES */

/**
 * Calls a dynamic attribute.
 *
 * This is the attribute access function for dynamic attributes.
 *
 * @param attribute  The (dynamic) attribute in question.
 * @param dcr        Location for the result (*int or *char).
 * @param args       Location of the parameters (of *int or *char).
 * @param nr_args    Number of parameters.
 * @return           Boolean: True for all OK, false for error.
 */
int
cl_dynamic_call(Attribute *attribute,
                DynCallResult *dcr,
                DynCallResult *args,
                int nr_args)
{
  char call[CL_MAX_LINE_LENGTH];
  char istr[32];

  int i, k, ap, ins;

  FILE *pipe;
  int argnum, val;
  DynArg *p;
  char c;

  check_arg(attribute, ATT_DYN, cl_errno);

  if ((args == NULL) || (nr_args <= 0))
    goto error;

  p = attribute->dyn.arglist;
  argnum = 0;

  while (p && argnum < nr_args) {
    if (p->type == args[argnum].type || (p->type == ATTAT_POS && args[argnum].type == ATTAT_INT)) {
      p = p->next;
      argnum++;
    }
    else if (p->type == ATTAT_VAR)
      argnum++;
    else
      goto error;
  }

  if ( (p && argnum == nr_args) || (p && p->type == ATTAT_VAR) ) {
    /* everything OK , so build the call string */
    i = 0;
    ins = 0;

    while ('\0'!= (c = attribute->dyn.call[i])) {
      if (c == '$' && isdigit(attribute->dyn.call[i+1])) {
        /* reference */
        i++;
        val = 0;
        while (isdigit(attribute->dyn.call[i]))
          val = val * 10 + attribute->dyn.call[i++] - '0';

        /* find the corresponding argument in the definition of args */
        if (val > 0 && val <= nr_args) {
          p = attribute->dyn.arglist;
          k = val - 1;  /* 0 .. max. nr_args-1 */
          ap = 0;
          while (p && p->type != ATTAT_VAR && k > 0) {
            p = p->next;
            ap++;
            k--;
          }

          if (p != NULL) {
            assert(ap < nr_args);

            if (p->type == ATTAT_VAR) {

              /* put all args >= ap into the call string */
              for (; ap < nr_args; ap++)
                switch (args[ap].type) {
                case ATTAT_STRING:
                  for (k = 0; args[ap].value.charres[k]; k++)
                    call[ins++] = args[ap].value.charres[k];
                  break;

                case ATTAT_INT:
                case ATTAT_POS:
                  sprintf(istr, "%d", args[ap].value.intres);
                  for (k = 0; istr[k]; k++)
                    call[ins++] = istr[k];
                  break;

                case ATTAT_FLOAT:
                  sprintf(istr, "%f", args[ap].value.floatres);
                  for (k = 0; istr[k]; k++)
                    call[ins++] = istr[k];
                  break;

                case ATTAT_NONE:
                case ATTAT_VAR:
                case ATTAT_PAREF:
                default:
                  goto error;
                  break;
                }
            }
            else {
              /* just put arg ap into the call string */
              switch (args[ap].type) {
              case ATTAT_STRING:
                for (k = 0; args[ap].value.charres[k]; k++)
                  call[ins++] = args[ap].value.charres[k];
                break;

              case ATTAT_INT:
              case ATTAT_POS:
                sprintf(istr, "%d", args[ap].value.intres);
                for (k = 0; istr[k]; k++)
                  call[ins++] = istr[k];
                break;

              case ATTAT_FLOAT:
                sprintf(istr, "%f", args[ap].value.floatres);
                for (k = 0; istr[k]; k++)
                  call[ins++] = istr[k];
                break;

              case ATTAT_NONE:
              case ATTAT_VAR:
              case ATTAT_PAREF:
              default:
                goto error;
                break;
              }
            }
          }
          else
            goto error;
        }
        else
          goto error;
      }
      else {
        call[ins++] = c;
        call[ins] = '\0';       /* for debugging */
        i++;                    /* get next char */
      }
    }
    call[ins++] = '\0';

    if (cl_debug)
      fprintf(stderr, "Composed dynamic call: \"%s\"\n", call);

    if (NULL == (pipe = popen(call, "r")))
      goto error;

    dcr->type = attribute->dyn.res_type;

    switch (attribute->dyn.res_type) {
    case ATTAT_POS:             /* convert output to int */
    case ATTAT_INT:
      if (!fscanf(pipe, "%d", &(dcr->value.intres)))
        dcr->value.intres = -1;
      break;

    case ATTAT_STRING:          /* copy output */
      fgets(call, CL_MAX_LINE_LENGTH, pipe);
      dcr->value.charres = (char *)cl_strdup(call);
      break;

    case ATTAT_FLOAT:
      if (!fscanf(pipe, "%lf", &(dcr->value.floatres)))
        dcr->value.floatres = 0.0;
      break;

    case ATTAT_NONE:
    case ATTAT_VAR:             /* not possible */
    case ATTAT_PAREF:
    default:
      goto error;
      break;
    }

    pclose(pipe);
    cl_errno = CDA_OK;
    return 1;
  }
  else
    goto error;

 error:
  cl_errno = CDA_EARGS;
  dcr->type = ATTAT_NONE;
  return 0;
}

/**
 * Count the number of arguments on a dynamic attribute's argument list.
 *
 * @param attribute  pointer to the Attribute object to analyse; it must
 *                   be a dynamic attribute.
 * @return           integer specifying the number of arguments;
 *                   a negative integer is returned if for any argument
 *                   on dyn.arglist, the type is equal to ATTAT_VAR
 */
int
cl_dynamic_numargs(Attribute *attribute)
{
  int nr;
  DynArg *arg;

  check_arg(attribute, ATT_DYN, cl_errno);

  nr = 0;
  for (arg = attribute->dyn.arglist; arg != NULL; arg = arg->next)
    if (arg->type == ATTAT_VAR) {
      nr = -nr;
      break;
    }
    else
      nr++;

  cl_errno = CDA_OK;
  return nr;
}

