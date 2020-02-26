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
#include <glib.h>

#include "../cl/cl.h"

#include "concordance.h"
#include "attlist.h"
#include "options.h"

/** maximum length of a KWIC line (bytes). */
#define MAXKWICLINELEN 65535



/**
 * Reverses the argument string (destructively, that is, in situ).
 *
 * Cf. the non-standard (microsoft) function strrev.
 *
 * This does not respect UTF-8, so anything reversed *must* be re-reversed
 * before output or there will be invalid byte sequences.
 *
 * @param s  The string to modify.
 * @return   A pointer to the modified string (same memory area as
 *           the argument string).
 */
static char *
srev(char *s)
{
  register char buf;
  register int i, l;

  l = strlen(s);

  for (i = 0; i < l/2; i++) {
    buf = s[l-i-1];
    s[l-i-1] = s[i];
    s[i] = buf;
  }

  return s;
}




/* ============================== get_print_attribute_values() */

/**
 * Prints s-attribute values into a ClAutoString, for use in a printed concordance line.
 *
 * (Note, the function is called "attribute values" but it very specifically means s-attribvutes.)
 *
 * The s-attributes that will be printed are determined by the contents of the ContextDescriptor
 * object; the PrintDescriptionRecord determines what they look like.
 *
 * @param cd                   Print settings (context size/type; atts to print)
 * @param position             The CPOS to be used in the position at the start
 * @param s                    Results will be concatenated onto this string.
 * @param sp                   Depracated argument: not used by func
 * @param max_sp               Depracated argument: not used by func
 * @param add_position_number  Boolean: whether or not to make  a position number (start of concordance line)
 * @param pdr                  Print settings (of main mode) to use
 */
void
get_print_attribute_values(ContextDescriptor *cd,
                           int position,
                           ClAutoString s,
                           int *sp,  /* not used TODO remove */
                           int max_sp, /* not used TODO remove */
                           int add_position_number,
                           PrintDescriptionRecord *pdr)
{
  if (add_position_number && pdr->CPOSPrintFormat) {
    static char rendered_cpos[CL_MAX_LINE_LENGTH];  /* another 'Oli': this was num[16], definitely not enough for HTML output */

    sprintf(rendered_cpos, pdr->CPOSPrintFormat, position);
    cl_autostring_concat(s, rendered_cpos);
  }

  if (cd->printStructureTags) {
    AttributeInfo *ai;
    int pref_printed = 0; /* boolean: has the prefix been printed? */

    for (ai = cd->printStructureTags->list; ai; ai = ai->next) {
      char *v;

      if (ai->status) {
        assert(ai->attribute);

        if (!pref_printed) {
          cl_autostring_concat(s, pdr->BeforePrintStructures);
          pref_printed++;
        }

        cl_autostring_concat(s, pdr->StructureBeginPrefix);
        cl_autostring_concat(s,  pdr->printToken  ?  pdr->printToken(ai->attribute->any.name)  :  ai->attribute->any.name);

        /* print value */
        v = cl_cpos2struc2str(ai->attribute, position);
        if (v && pdr->printToken)
          v = pdr->printToken(v);

        if (v) {
          cl_autostring_concat(s, pdr->PrintStructureSeparator);
          cl_autostring_concat(s, v);
        }

        cl_autostring_concat(s, pdr->StructureBeginSuffix);
      }
    }

    if (pref_printed)
      cl_autostring_concat(s, pdr->AfterPrintStructures);
  }
}



/* ============================== helpers for: get_position_values() */
/* the following code is borrowed from <utils/decode.c> and ensures that XML tags in the kwic output always nest properly */
/* TODO avoid duplication of code!! */

#define MAX_S_ATTRS 1024        /* max. number of s-attribute; same as MAX_ATTRS in <utils/decode.c> and MAXRANGES in <utils/encode.c>  */
typedef struct {
  char *name;                   /* name of the s-attribute */
  int start;
  int end;
  char *annot;                  /* NULL if there is no annotation */
} SAttRegion;
SAttRegion s_att_regions[MAX_S_ATTRS];
int sar_sort_index[MAX_S_ATTRS]; /* index used for bubble-sorting list of regions */
int N_sar = 0;                   /* number of regions currently in list (may change for each token printed) */

void
sort_s_att_regions(void) {
  int i, temp, modified;

  for (i = 0; i < N_sar; i++)   /* initialise sort index */
    sar_sort_index[i] = i;

  modified = 1;                 /* repeat 'bubble' loop until no more modifications are made */
  while (modified) {
    modified = 0;
    for (i = 0; i < (N_sar-1); i++) {
      SAttRegion *a = &(s_att_regions[sar_sort_index[i]]); /* compare *a and *b */
      SAttRegion *b = &(s_att_regions[sar_sort_index[i+1]]);

      if ( (a->end < b->end) ||
           ((a->end == b->end) && (a->start > b->start)) ) {
        temp = sar_sort_index[i]; /* swap sar_sort_index[i] and sar_sort_index[i+1] */
        sar_sort_index[i] = sar_sort_index[i+1];
        sar_sort_index[i+1] = temp;
        modified = 1;           /* modified ordering, so we need another loop iteration */
      }
    }
  }

  return;
}


/**
 * Get values at the given corpus position.
 */
void
get_position_values(ContextDescriptor *cd,
                    int position,
                    ClAutoString s,
                    int *sp,
                    int max_sp,
                    int add_position_number,
                    ConcLineLayout orientation,
                    PrintDescriptionRecord *pdr,
                    int nr_mappings,
                    Mapping *mappings)
{
  AttributeInfo *ai;
  int id, i;
  int nr_attrs = 0;
  const char *word;
  char *att_sep = pdr->AttributeSeparator;
  if (*attribute_separator != '\0') att_sep = attribute_separator;

  cl_autostring_truncate(s, 0);

  /* insert all s-attribute regions which start or end at the current token into s_att_regions[],
     then sort them to ensure proper nesting, and print from the list */
  N_sar = 0;
  if (cd->strucAttributes) {
    for (ai = cd->strucAttributes->list; ai; ai = ai->next)
      if (ai->status) {
        int s_start, s_end, snum;

        if ( ((snum = cl_cpos2struc(ai->attribute, position)) >= 0) &&
             (cl_struc2cpos(ai->attribute, snum, &s_start, &s_end)) &&
             ((position == s_start) || (position == s_end)) ) {

          s_att_regions[N_sar].name = ai->attribute->any.name;
          s_att_regions[N_sar].start = s_start;
          s_att_regions[N_sar].end = s_end;
          if (cl_struc_values(ai->attribute))
            s_att_regions[N_sar].annot = cl_struc2str(ai->attribute, snum);
          else
            s_att_regions[N_sar].annot = NULL;
          N_sar++;
          }

        }
    sort_s_att_regions();
  } /* else N_sar == 0 */


  /* ==================== first, add starting structures */

  if (add_position_number && orientation == ConcLineHorizontal) {

    char num[CL_MAX_LINE_LENGTH];

    sprintf(num, pdr->CPOSPrintFormat, position);
    cl_autostring_concat(s, num);
  }

  /* print open tags from s_att_regions[] (ascending) */
  if (cd->strucAttributes) {
    SAttRegion *region;
    int do_lb = 0;

    for (i = 0; i < N_sar; i++) {
      region = &(s_att_regions[sar_sort_index[i]]);
      if (region->start == position) {
        /* add start tag to s */
        static char body[CL_MAX_LINE_LENGTH]; /* 'body' of the start tag, may include annotation  */
        if (show_tag_attributes && (region->annot != NULL)) {
          sprintf(body, "%s %s", region->name, region->annot);
        }
        else {
          strcpy(body, region->name);
        }

        cl_autostring_concat(s, pdr->StructureBeginPrefix);
        cl_autostring_concat(s, (pdr->printToken) ? pdr->printToken(body) : body);
        cl_autostring_concat(s, pdr->StructureBeginSuffix);
        do_lb++;
      }
    }

    if (do_lb && (orientation == ConcLineVertical))
      cl_autostring_concat(s, pdr->AfterLine);
  }

  if (add_position_number && orientation == ConcLineVertical) {
    char num[CL_MAX_LINE_LENGTH];

    sprintf(num, pdr->CPOSPrintFormat, position);
    cl_autostring_concat(s, num);
  }


  /* ==================== then, add positional attribute values */

  /* TODO: apply mappings -- ????? */

  for (ai = cd->attributes->list; ai; ai = ai->next) {

    if (ai->attribute && ai->status > 0) {
      if (nr_attrs > 0)
        cl_autostring_concat(s, att_sep);

      word = NULL;

      if (nr_mappings > 0) {
        /* unused */
        int mp = 0;
        int class = 0;

        for (mp = 0; mp < nr_mappings ; mp++)
          if (ai->attribute == get_attribute_of_mapping(mappings[mp]))
            break;

        if (mp < nr_mappings) {
          id = cl_cpos2id(ai->attribute, position);
          if (CL_ALL_OK()) {
            class = map_id_to_class_number(mappings[mp], id);
            if (class >= 0)
//              word = mappings[mp]->classes[class].class_name;
              word = name_of_mapping_class(get_class_array_of_mapping(mappings[mp]) + class);
          }
        }
      }

      if (!word)
        word = cl_cpos2str(ai->attribute, position);

      if (word)
        cl_autostring_concat(s,  pdr->printToken ? pdr->printToken((char *)word) : word);

      nr_attrs++;
    }
  }

  /* ==================== finally, add ending structures */

  /* print close tags from s_att_regions[] (descending) */
  if (cd->strucAttributes) {
    SAttRegion *region;
    int lb = 0;                 /* line break done? */

    for (i = N_sar - 1; i >= 0; i--) {
      region = &(s_att_regions[sar_sort_index[i]]);
      if (region->end == position) {
        if (orientation == ConcLineVertical && !lb) {
          cl_autostring_concat(s, pdr->AfterLine);
          lb++;
        }

        /* add end tag to s */
        cl_autostring_concat(s, pdr->StructureEndPrefix);
        cl_autostring_concat(s, (pdr->printToken) ? pdr->printToken(region->name) : region->name);
        cl_autostring_concat(s, pdr->StructureEndSuffix);
      }
    }
  }

  /* ==================== finish and exit */


#if 0
  fprintf(stderr, "get_position_values() at pos %d: ``%s''\n", position, s->data);
#endif
}

/**
 * Adds the asserted number of positions (nr_positions) specified by this_token_start and this_token_end
 * to an array of integers in returned_positions
 */
void
remember_this_position(int position,
                       int this_token_start, int this_token_end,
                       int *position_list,
                       int nr_positions,
                       int *returned_positions)
{
  int p;

  if (nr_positions > 0) {
    assert(position_list);

    for (p = 0; p < nr_positions; p++) {
      if (position_list[p] == position) {
        returned_positions[p * 2] = this_token_start;
        returned_positions[(p * 2)+1] = this_token_end;
      }
    }
  }
}


/**
 * Scratch string used by get_field_separators only.
 * Global so that Kwic cleanup can catch it!
 */
static ClAutoString scratch = NULL;

/**
 * This oddly-named function prints a series of separators for "fields"
 * to an internal buffer.
 *
 * "Field" in this poition means one of the 4 anchor points (begin, end, target, keyword).
 *
 * @param position   The corpus position (cpos) whose field-sepaators we want.
 * @param fields     Pointer to array of ConcLineFields object (each of which specifies one of the 4 anchors).
 * @param nr_fields  Number of items in the "fields" array.
 * @param at_end     Boolean: if true, we get the end-separators for the fields at this cpos;
 *                   if false, we get the beginning-separators for the fields at this cpos.
 * @param pdr        The PDR for the current concordance printout.
 * @return           A pointer to a module-internal static string buffer containing
 *                   the requested string. Do not free it or alter it.
 *                   The buffer's content will change when this function is called again.
 *                   The function will return NULL if the requested string would have
 *                   been zero-length.
 */
char *
get_field_separators(int position,
                     ConcLineField *fields,
                     int nr_fields,
                     int at_end,
                     PrintDescriptionRecord *pdr)
{
  int i;

  /* start with an empty string ... */
  if (NULL == scratch)
    scratch = cl_autostring_new(NULL, 0);
  else
    cl_autostring_truncate(scratch, 0);

  if (fields && nr_fields > 0 && position >= 0 && pdr && pdr->printField) {
    if (at_end) {
      for (i = nr_fields; i > 0; i--) {
        if (position == fields[i-1].end_position) {
          /* note call to the "business end" which is the func pointer in the PDR */
          cl_autostring_concat(scratch, pdr->printField(fields[i-1].type, at_end));
        }
      }
    }
    else {
      /* if not at_end then... */
      for (i = 0; i < nr_fields; i++) {
        if (position == fields[i].start_position) {
          /* note call to the "business end" which is the func pointer in the PDR */
          cl_autostring_concat(scratch, pdr->printField(fields[i].type, at_end));
        }
      }
    }

    if (scratch->len > 0)
      return scratch->data;
    else
      return NULL;
  }
  else
    return NULL;
}



/*
 * TWO GLOBAL VARIABLES for concordance line construction, used only within compose_kwic_line();
 * they are global so that, once their buffers auto-expand, they will stay expanded for the
 * remainder of the CQP session -- no sense freeing memory when a user has already shown they
 * are inclined to make use of it and will probably request an equally long string soon after !
 */

/** Used to build the concordance line (main line buffer); @see compose_kwic_line */
static ClAutoString line  = NULL;
/** Used to build the concordance line (token buffer);     @see compose_kwic_line */
static ClAutoString token = NULL;

/**
 * Initialises the two empty auto-growing strings used for concordance line concstruction.
 */
void
setup_kwic_line_memory(void)
{
  if (NULL == line)
    line  = cl_autostring_new(NULL, 0);
  else
    cl_autostring_truncate(line, 0);

  if (NULL == token)
    token = cl_autostring_new(NULL, 0);
  else
    cl_autostring_truncate(line, 0);
}

/**
 * Frees the memory used for building a KWIC line for display.
 *
 * Best used when CQP shuts down.
 */
void
cleanup_kwic_line_memory(void)
{
  cl_autostring_delete(line);
  cl_autostring_delete(token);
  cl_autostring_delete(scratch);
}

/**
 * Builds a string for a concordance output line.
 *
 * 'position_list' is a list of (corpus) positions. The string
 * start and beginning positions for these corpus positions
 * are written into returned_positions, which must be exactly
 * two times as large as the position list. The number of
 * positions must be in nr_positions.
 *
 * @param match_start  A corpus position
 * @param match_end    A corpus position
 *
 * @param fields       Array of ConcLineFields object (each of which specifies one of the 4 anchors).
 * @param nr_fields    Number of items in the "fields" array.
 *
 * roughnotes: I THINK returned_positions is just a blob of memory that the func is being allowd to use.
 * @return             String containing the output line.
 */
char *
compose_kwic_line(Corpus *corpus,
                  int match_start,
                  int match_end,
                  ContextDescriptor *cd,
                  int *length,
                  int *s_mb,
                  int *s_me,
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
                  Mapping *mappings)
{
  // int acc_len;  /* Accurate length - for counting context in characters */
  /* TODO replace the single variable above with the following pair of variables */

  /* length of the string assembled, in bytes */
  unsigned length_bytes;
  /* length of the string assembled, in characters; == length_bytes in all cases other than charset==utf8 */
  unsigned length_characters;

  int old_len;  /* Old length - for tracking the n bytes added by a concatenate operation */

  /* total N of tokens on the attribute (first on the list of attributes-to-print);
   * used as a maximum for sanity-check */
  int text_size;

  int start, end, index;

  /* dummies: because the arguments of some other functions require an int to be passed... */
  int line_p;
  int token_p;

  char *word;
  char *separator;

  int add_cpos_to_lines;

  int rng_s, rng_e, rng_n, nr_ranges;

  int this_token_start, this_token_end;
  int token_length_characters;

  int el_c = 0;

  int enough_context = 0;       /* Boolean: should we keep adding or not? */

  int nr_selected_attributes = 0;

  AttributeList *default_list = NULL;
  AttributeInfo *ai;

  /* set up our two buffers as empty strings ... */
  setup_kwic_line_memory();

  /* set the separator */
  if (orientation == ConcLineHorizontal) {
    separator = " ";
    add_cpos_to_lines = 0;
  }
  else {
    separator = "\n";
    add_cpos_to_lines = cd->print_cpos;
  }

  /* make a dummy attribute list (with default p-attribute as its only member) in case we don't yet have one. */

  if (cd->attributes == NULL || cd->attributes->list == NULL) {
    default_list = NewAttributeList(ATT_POS);
    AddNameToAL(default_list, CWB_DEFAULT_ATT_NAME, 1, 0);
    cd->attributes = default_list;
  }

  if (!cd->attributes->list_valid)
    VerifyList(cd->attributes, corpus, 1);

  for (ai = cd->attributes->list; ai; ai = ai->next)
    if (ai->status > 0)
      nr_selected_attributes++;

  if (nr_selected_attributes == 0) {
    ai = FindInAL(cd->attributes, CWB_DEFAULT_ATT_NAME);
    if (ai) {
      ai->status = 1;
      nr_selected_attributes++;
    }
    else {
      fprintf(stderr, "ERROR: Can't select default attribute in attribute list\n");
      return NULL;
    }
  }

  assert(cd->attributes->list->attribute);

  text_size = cl_max_cpos(cd->attributes->list->attribute);

  /* assert sane values for match_start and match_end */
  assert(match_start >= 0 && match_start < text_size);
  assert(match_end >= 0 && match_end < text_size && match_end >= match_start);


  /*
   * WE ARE NOW READY TO START BUILDING US A KWIC LINE !!! Hurray!
   */

  get_print_attribute_values(cd, match_start,
                             line, &line_p,
                             MAXKWICLINELEN,
                             cd->print_cpos && (orientation == ConcLineHorizontal),
                             pdr);

  cl_autostring_concat(line, pdr->BeforeField);

  /* ============================== clear array of returned positions */

  if (position_list && (nr_positions > 0)) {
    assert(returned_positions);
    for (el_c = 0; el_c < nr_positions * 2; el_c++)
      returned_positions[el_c] = -1;
  }


  /*
   * ==========================================================================
   * first big job: use designated method to put together the left-hand co-text
   * ==========================================================================
   */


  switch(cd->left_type) {
  case CHAR_CONTEXT:

    /* we have 0 characters so far */
    length_bytes = 0;
    length_characters = 0;
    enough_context = 0;

    /* make a note of the old starting point, for purposes of srev() */
    index = line->len;

    /* NUR linken Kontext ohne MatchToken berechnen */

    for (start = match_start - 1; (start >= 0 && !enough_context); start--) {
      if (length_characters >= cd->left_width)
        enough_context++;
      else {
        /* we do not yet have enough context, so get position values into (blank) string object token */
        get_position_values(cd,
                            start,
                            token, &token_p,
                            MAXKWICLINELEN,
                            add_cpos_to_lines,
                            orientation,
                            pdr,
                            nr_mappings,
                            mappings);
        token_length_characters = cl_charset_strlen(corpus->charset, token->data);
        if (token_length_characters > 0) {

          if (line->len > index) {   /* no blank before first token to the left of match */
            old_len = line->len;
            cl_autostring_concat(line, pdr->TokenSeparator);
            length_bytes += line->len - old_len;
            length_characters += cl_charset_strlen(corpus->charset, pdr->TokenSeparator);
          }

          this_token_start = line->len;

          /* wir fügen erstmal ganz normal ein und drehen nachher um */

          if ((word = get_field_separators(start, fields, nr_fields, 0, pdr)))
            cl_autostring_concat(line, word);

          cl_autostring_concat(line, pdr->BeforeToken);

          if (token_length_characters + length_characters < cd->left_width) {
            cl_autostring_concat(line, token->data);
            length_bytes += token->len;
            length_characters += token_length_characters;
          }
          else {
            /* not enough space for the whole token */
            char *mark;
            if (corpus->charset != utf8) {
              /* ... so only copy its last several bytes */
              mark = token->data + (token->len - (cd->left_width - length_characters));
            }
            else {
              /* ... so only copy its last several characters */
              mark = token->data;
              while (token_length_characters + length_characters > cd->left_width) {
                mark = g_utf8_next_char(mark);
                --token_length_characters;
              }
            }
            old_len = line->len;
            cl_autostring_concat(line, mark);
            length_bytes += line->len - old_len;
            length_characters += cl_charset_strlen(corpus->charset, mark);
          }

          cl_autostring_concat(line, pdr->AfterToken);

          if ((word = get_field_separators(start, fields, nr_fields, 1, pdr)))
            cl_autostring_concat(line, word);

          srev(line->data + this_token_start);

          this_token_end = line->len;

          if (this_token_start != this_token_end)
            remember_this_position(start,
                                   this_token_start, this_token_end,
                                   position_list, nr_positions,
                                   returned_positions);
        }
        else
          enough_context = 1;
        /* that is, if calling get_position_values() did not generate any more context,
         * then simply declare that we have enough context!
         */
      }
    } /* endfor */

    /* pad with blanks until we reach the designated width for left co-text */
    while (length_characters < cd->left_width) {
      cl_autostring_concat(line, " ");
      ++length_bytes, ++length_characters;
    }

    /* Now we need to reverse order of characters of the left co-text assembled in the preceding code;
     * but we DON'T flip any printStructures */

#if 0
    fprintf(stderr, "line before srev(): >>%s<<\n", line + index);
#endif

    srev(line->data + index);

#if 0
    fprintf(stderr, "line after srev(): >>%s<<\n", line + index);
#endif

    /* now the fun bit: because of the srev() call abnove, we now need to align the list of returned_positions ... */

    if (position_list && (nr_positions > 0)) {

      int old_start, new_start, old_end, new_end;

      for (el_c = 0; el_c < nr_positions; el_c++) {
        if (returned_positions[el_c * 2] >= 0) {
          old_start = returned_positions[el_c * 2] - index;
          old_end   = returned_positions[(el_c * 2)+1] - index;

          new_start = line->len - 1 - old_end;
          new_end   = line->len - 1 - old_start;

#if 0
          fprintf(stderr, "Patching [%d,%d] to [%d,%d]\n", old_start, old_end, new_start, new_end);
#endif

          returned_positions[el_c * 2]     = new_start + 1;
          returned_positions[(el_c * 2)+1] = new_end + 1;
        }
      }
    }

    break; /* endcase CHAR_CONTEXT */


  case WORD_CONTEXT:

    start = match_start - cd->left_width;
    if (start < 0)
      start = 0;

    for ( ; start < match_start; start++) {
      cl_autostring_truncate(token, 0);
      get_position_values(cd,
                          start,
                          token, &token_p,
                          MAXKWICLINELEN,
                          add_cpos_to_lines,
                          orientation,
                          pdr,
                          nr_mappings, mappings);
      /* Trennzeichen einfügen, falls schon tokens in line drin sind */
      if (line->len > 0)
        cl_autostring_concat(line, pdr->TokenSeparator);

      this_token_start = line->len;

      if ((word = get_field_separators(start, fields, nr_fields, 0, pdr)))
        cl_autostring_concat(line, word);

      cl_autostring_concat(line, pdr->BeforeToken);
      cl_autostring_concat(line, token->data);
      cl_autostring_concat(line, pdr->AfterToken);

      if ((word = get_field_separators(start, fields, nr_fields, 1, pdr)))
        cl_autostring_concat(line, word);

      this_token_end = line->len;

      remember_this_position(start,
                             this_token_start, this_token_end,
                             position_list, nr_positions,
                             returned_positions);
    }

    break;


  case STRUC_CONTEXT:
  case ALIGN_CONTEXT:

    if (!cd->left_structure) {
      fprintf(stderr, "concordance.o/compose_kwic_line: lcontext attribute pointer is NULL\n");
      start = match_start - 20;
    }
    else {
      if (cd->left_type == ALIGN_CONTEXT) {
        /* context == alignment block */
        if (0 > (rng_n = cl_cpos2alg(cd->left_structure, match_start)))
          start = match_start;
        else {
          assert(cd->left_width == 1);

          /* get start of source corpus alignment block */
          if (!cl_alg2cpos(cd->left_structure,
                           rng_n,
                           &rng_s, &rng_e, &rng_e, &rng_e))
            start = match_start;
          else
            start = rng_s;
        }
      }
      else {
        /* context == structural region(s) */
        if (0 > (rng_n = cl_cpos2struc(cd->left_structure, match_start)))
          start = match_start - 20;
        else {
          assert(cd->left_width >= 0);

          /* determine the lower range number */
          rng_n = MAX(0, rng_n - cd->left_width + 1);
          if (!cl_struc2cpos(cd->left_structure, rng_n, &rng_s, &rng_e))
            start = match_start - 20;
          else
            start = rng_s;
        }
      }
    }

    if (start < 0)
      start = 0;

    for ( ; start < match_start; start++) {
      cl_autostring_truncate(token, 0);
      get_position_values(cd,
                          start,
                          token, &token_p,
                          MAXKWICLINELEN,
                          add_cpos_to_lines,
                          orientation,
                          pdr,
                          nr_mappings, mappings);
      /* Insert a separator iff there are already 1+ tokens in the line */
      if (line->len > 0)
        cl_autostring_concat(line, pdr->TokenSeparator);

      this_token_start = line->len;

      /* jetzt den Feldstart */
      if ((word = get_field_separators(start, fields, nr_fields, 0, pdr)))
        cl_autostring_concat(line, word);

      cl_autostring_concat(line, pdr->BeforeToken);
      cl_autostring_concat(line, token->data);
      cl_autostring_concat(line, pdr->AfterToken);

      /* jetzt das Feldende */
      if ((word = get_field_separators(start, fields, nr_fields, 1, pdr)))
        cl_autostring_concat(line, word);

      this_token_end = line->len;

      remember_this_position(start,
                             this_token_start, this_token_end,
                             position_list, nr_positions,
                             returned_positions);
    }

    break;
  }


  /*
   * =======================================================================
   * Left-hand co-text is now fully built, so now we insert the match tokens
   * =======================================================================
   */

  /* Trennzeichen einfügen, falls schon tokens in line drin sind */
  if (line->len > 0)
    cl_autostring_concat(line, pdr->TokenSeparator);

  /* out parameter: byte offset of begin-point of the hit */
  *s_mb = line->len;

  cl_autostring_concat(line, left_marker);

  for (start = match_start; start <= match_end; start++) {
    cl_autostring_truncate(token, 0);
    get_position_values(cd,
                        start,
                        token, &token_p,
                        MAXKWICLINELEN,
                        add_cpos_to_lines,
                        orientation,
                        pdr,
                        nr_mappings, mappings);
    this_token_start = line->len;

    if ((word = get_field_separators(start, fields, nr_fields, 0, pdr)))
      cl_autostring_concat(line, word);

    cl_autostring_concat(line, pdr->BeforeToken);
    cl_autostring_concat(line, token->data);
    cl_autostring_concat(line, pdr->AfterToken);

    if ((word = get_field_separators(start, fields, nr_fields, 1, pdr)))
      cl_autostring_concat(line, word);

    this_token_end = line->len;

    remember_this_position(start,
                           this_token_start, this_token_end,
                           position_list, nr_positions,
                           returned_positions);

    if (start != match_end)
      if (line->len > 0)
        cl_autostring_concat(line, separator);
  }

  cl_autostring_concat(line, right_marker);

  /* out parameter: byte offset of begin-point of waht follows the hit */
  *s_me = line->len;



  /*
   * ======================================================
   * now all we have to do is add on the right-hand co-text
   * ======================================================
   */


  switch(cd->right_type) {

  case CHAR_CONTEXT:
    /* we have 0 characters so far */
    length_bytes = 0;
    length_characters = 0;
    enough_context = 0;

    /* nun rechten Kontext ohne MatchToken berechnen */

    for (start = match_end + 1; (start < text_size && !enough_context); start++) {
      cl_autostring_truncate(token, 0);
      if (length_characters >= cd->right_width)
        enough_context++; /* stop if the requested number of characters have been generated */
      else {
        get_position_values(cd,
                            start,
                            token, &token_p,
                            MAXKWICLINELEN,
                            add_cpos_to_lines,
                            orientation,
                            pdr,
                            nr_mappings, mappings);

        if (line->len > 0) {
          old_len = line->len;
          cl_autostring_concat(line, pdr->TokenSeparator);
          length_bytes += line->len - old_len;
          length_characters += cl_charset_strlen(corpus->charset, pdr->TokenSeparator);
        }

        this_token_start = line->len;

        /* now the beginning-of-field separators */
        if ((word = get_field_separators(start, fields, nr_fields, 0, pdr)))
          cl_autostring_concat(line, word);

        cl_autostring_concat(line, pdr->BeforeToken);

        old_len = line->len;
        cl_autostring_concat(line, token->data);
        length_bytes += line->len - old_len;
        length_characters += cl_charset_strlen(corpus->charset, token->data);
        if (length_characters > cd->right_width) {
          if (corpus->charset != utf8) {
            /* simple truncation by count of bytes */
            cl_autostring_truncate(line, line->len - (length_characters - cd->right_width));
            length_characters = length_bytes = cd->right_width;
          }
          else {
            /* rewind through the string until we have removed the correct n of characters */
            char *mark = line->data + (line->len - 1);
            while (length_characters > cd->right_width) {
              while (cl_string_utf8_continuation_byte(*mark))
                --mark;
              --length_characters, --mark;
            }
            cl_autostring_truncate(line, (mark - line->data + 1));
          }
        }

        cl_autostring_concat(line, pdr->AfterToken);

        /* jetzt das Feldende */
        if ((word = get_field_separators(start, fields, nr_fields, 1, pdr)))
          cl_autostring_concat(line, word);

        this_token_end = line->len;

        if (this_token_start != this_token_end)
          remember_this_position(start,
                                 this_token_start, this_token_end,
                                 position_list, nr_positions,
                                 returned_positions);
      }
    }
    break;


  case WORD_CONTEXT:

    for (start = 1 ; start <= cd->right_width && match_end + start < text_size ; start++) {
      cl_autostring_truncate(token, 0);
      get_position_values(cd,
                          match_end + start,
                          token, &token_p,
                          MAXKWICLINELEN,
                          add_cpos_to_lines,
                          orientation,
                          pdr,
                          nr_mappings, mappings);

      /* Trennzeichen einfügen, falls schon tokens in line drin sind */
      if (line->len > 0)
        cl_autostring_concat(line, pdr->TokenSeparator);

      this_token_start = line->len;

      if ((word = get_field_separators(match_end + start, fields, nr_fields, 0, pdr)))
        cl_autostring_concat(line, word);

      cl_autostring_concat(line, pdr->BeforeToken);
      cl_autostring_concat(line, token->data);
      cl_autostring_concat(line, pdr->AfterToken);

      if ((word = get_field_separators(match_end + start, fields, nr_fields, 1, pdr)))
        cl_autostring_concat(line, word);

      this_token_end = line->len;

      remember_this_position(match_end + start,
                             this_token_start, this_token_end,
                             position_list, nr_positions,
                             returned_positions);
    }
    break;


  case STRUC_CONTEXT:
  case ALIGN_CONTEXT:

    if (!cd->right_structure) {
      fprintf(stderr, "concordance.o/compose_kwic_line: rcontext attribute pointer is NULL\n");
      end = match_end + 20;
    }
    else {
      if (cd->right_type == ALIGN_CONTEXT) {
        /* context == alignment block */
        if (0 > (rng_n = cl_cpos2alg(cd->right_structure, match_end)))
          end = match_end;
        else {
          assert(cd->right_width == 1);

          /* get end of source corpus alignment block */
          if (!cl_alg2cpos(cd->right_structure,
                           rng_n,
                           &rng_s, &rng_e, &rng_s, &rng_s))
            end = match_end;
          else
            end = rng_e;
        }
      }
      else {
        /* context == structural region(s) */
        if (0 > (rng_n = cl_cpos2struc(cd->right_structure, match_end)))
          end = match_end + 20;
        else {
          assert(cd->right_width >= 0);

          /* determine the upper range number */
          if (0> (nr_ranges = cl_max_struc(cd->right_structure)))
            end = match_end + 20;
          else {
            rng_n = MIN(nr_ranges-1, rng_n + cd->right_width - 1);
            if (!cl_struc2cpos(cd->right_structure,
                               rng_n,
                               &rng_s, &rng_e))
              end = match_end + 20;
            else
              end = rng_e;
          }
        }
      }
    }

    if (match_end >= text_size)
      match_end = text_size - 1;

    for (start = match_end + 1; start <= end; start++) {
      cl_autostring_truncate(token, 0);
      get_position_values(cd,
                          start,
                          token, &token_p,
                          MAXKWICLINELEN,
                          add_cpos_to_lines,
                          orientation,
                          pdr,
                          nr_mappings, mappings);

      /* Trennzeichen einfügen, falls schon tokens in line drin sind */
      if ( (this_token_start = line->len) > 0) {
        cl_autostring_concat(line, separator);
        this_token_start++;
      }

      if ((word = get_field_separators(start, fields, nr_fields, 0, pdr)))
        cl_autostring_concat(line, word);

      cl_autostring_concat(line, pdr->BeforeToken);
      cl_autostring_concat(line, token->data);
      cl_autostring_concat(line, pdr->AfterToken);

      if ((word = get_field_separators(start, fields, nr_fields, 1, pdr)))
        cl_autostring_concat(line, word);

      this_token_end = line->len;

      remember_this_position(start,
                             this_token_start, this_token_end,
                             position_list, nr_positions,
                             returned_positions);
    }
    break;
  }

  cl_autostring_concat(line, pdr->AfterField);

  /*
   * OK, the line is complete!
   */

  *length = line->len;

  return cl_strdup(cl_autostring_ptr(line));

  /* TODO: returned_positions richtig setzen */
}

