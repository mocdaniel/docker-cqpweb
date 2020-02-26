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


#include "globals.h"
#include "class-mapping.h"

/**
 * The token that identifies a "name" line in a mapping file.
 */
#define NAME_TOKEN "#name "

/* ---------------------------------------- prototypes */

int drop_single_mapping(SingleMapping *smap);

/* ---------------------------------------- functions */

/**
 * increment for memory reallocation of classes in a SingleMapping
 */
#define CLASS_REALLOC_THRESHOLD 16
/**
 * increment for memory reallocation of tokens in a SingleMapping
 */
#define TOKEN_REALLOC_THRESHOLD 16



/* note this DUPLICATES intcompare in cdaccess.c where it is also static... */
static int
intcompare(const void *i, const void *j)
{
  return(*(int *)i - *(int *)j);
}


/**
 * Creates a Mapping from a file.
 *
 * Each line in the file results in a SingleMapping (unless it begins in #,
 * in which case it either indicates the name of the mapping or is a comment).
 *
 * Within a single line, the first white-space delimited token represents
 * the name of the class, and the other tokens are attribute values.
 *
 * Any parse failure in the file will stop the entire Mapping-creation process
 * and result in NULL being returned.
 *
 * @param corpus        The corpus for which the Mapping is valid (pointer).
 * @param attr_name     String naming the attribute for which the mapping is valid.
 * @param file_name     The filename of the map spec.
 * @param error_string  A char * (not char[]), which is set to an error string,
 *                      or to NULL if all is OK.
 * @return              The resulting Mapping object, or NULL in case of error.
 */
Mapping
read_mapping(Corpus *corpus, char *attr_name, char *file_name,  char **error_string)
{
  FILE *src;
  Attribute *attr;
  Mapping m = NULL;
  char s[CL_MAX_LINE_LENGTH];

  if (!corpus) {
    *error_string = "corpus argument missing";
    return NULL;
  }

  if (!attr_name) {
    *error_string = "attribute name argument missing";
    return NULL;
  }

  if (!(attr = cl_new_attribute(corpus, attr_name, ATT_POS))) {
    *error_string = "no such attribute in corpus";
    return NULL;
  }

  if (!(src = fopen(file_name, "r"))) {
    *error_string = "Can't open mapping file";
    return NULL;
  }

  m = cl_malloc(sizeof(MappingRecord));

  m->corpus = corpus;
  m->mapping_name = NULL;
  m->attribute = attr;
  m->nr_classes = 0;
  m->classes = NULL;

  *error_string = "Not yet implemented";

  if (!m->attribute) {
    *error_string = "no such attribute for corpus";
    drop_mapping(&m);
  }

  while ( m  &&  NULL != fgets(s, CL_MAX_LINE_LENGTH, src) ) {

    if (s[0] && s[strlen(s)-1] == '\n')
      s[strlen(s)-1] = '\0';

    /* NB. The following if-else takes up all the rest of this while-loop. */
    if (s[0] == '#') {
      /* lines beginning with # */

      /* if this line begins with the NAME_TOKEN... */
      if (0 == strncasecmp(s, NAME_TOKEN, strlen(NAME_TOKEN))) {

        /* set the name */

        if (m->mapping_name) {
          *error_string = "Multiple mapping names declared";
          drop_mapping(&m);
        }
        else if (!s[strlen(NAME_TOKEN)]) {
          *error_string = "Error in #NAME declaration";
          drop_mapping(&m);
        }
        else
          m->mapping_name = cl_strdup(s + strlen(NAME_TOKEN));
      }
      /* everything else beginning with # is a comment  (and can thus be ignored) */

    }
    else if (s[0]) {
      /* lines NOT beginning with # */

      /* make new single mapping */
      char *token;
      SingleMappingRecord *this_class = NULL;

      token = strtok(s, " \t\n");

      if (token) {
        /* first token is class name, rest are attribute values */

        /* test: class 'token' already defined? */
        if (find_mapping(m, token) != NULL) {
          *error_string = "Class defined twice";
          drop_mapping(&m);
          break;
        }

        /* create new class */
        if (m->nr_classes == 0)
          m->classes = (SingleMappingRecord *)cl_malloc(sizeof(SingleMappingRecord) * CLASS_REALLOC_THRESHOLD);
        else if (m->nr_classes % CLASS_REALLOC_THRESHOLD == 0)
          m->classes = (SingleMappingRecord *)cl_realloc(m->classes, sizeof(SingleMappingRecord) * (m->nr_classes + CLASS_REALLOC_THRESHOLD));
        /* else there is enough memory for this new class already! */

        if (m->classes == NULL) {
          *error_string = "Memory allocation failure";
          drop_mapping(&m);
        }
        else {
          m->classes[m->nr_classes].class_name = cl_strdup(token);
          m->classes[m->nr_classes].nr_tokens = 0;
          m->classes[m->nr_classes].tokens = NULL;

          this_class = &(m->classes[m->nr_classes]);
        }

        /* create single mappings : loop through remaining tokens on this line */
        while (m && (token = strtok(NULL, " \t\n"))) {
          /* test: token member of attribute values of my attribute? */
          int id = cl_str2id(attr, token);

          if (id < 0 || !CL_ALL_OK()) {
            *error_string = "token not member of attribute";
            drop_mapping(&m);
            break;
          }

          /* test: token already member of any class? */
          if (map_token_to_class(m, token) != NULL) {
            *error_string = "token member of several classes";
            drop_mapping(&m);
            break;
          }
          else if (this_class->tokens) {
            int i;
            for (i = 0; i < this_class->nr_tokens; i++)
              if (this_class->tokens[i] == id) {
                *error_string = "token member of several classes";
                drop_mapping(&m);
                break;
              }
          }

          /* having passed all the tests, put token id into this mapping */
          if (m) {
            if (this_class->nr_tokens == 0)
              this_class->tokens = (int *)cl_malloc(sizeof(int) * TOKEN_REALLOC_THRESHOLD);
            else if (this_class->nr_tokens % TOKEN_REALLOC_THRESHOLD == 0)
              this_class->tokens = (int *)cl_realloc(this_class->tokens, sizeof(int) * (this_class->nr_tokens + TOKEN_REALLOC_THRESHOLD));

            if (this_class->tokens == NULL) {
              *error_string = "Memory allocation failure";
              drop_mapping(&m);
            }
            else {
              this_class->tokens[this_class->nr_tokens] = id;
              this_class->nr_tokens++;
            }
          }
        } /* endwhile (loop for each token on a line) */

        if (m) {
          m->nr_classes++;
          /* sort token IDs in increasing order */
          qsort(this_class->tokens,
                this_class->nr_tokens,
                sizeof(int),
                intcompare);
        }
      }
    }
  } /* endwhile (main loop for each line in the mapping file */

  fclose(src);
  return m;
}


/**
 * Deletes a SingleMapping object.
 *
 * @param smap  Address of the object to delete.
 * @return      Always 1.
 */
int
drop_single_mapping(SingleMapping *smap)
{
  cl_free((*smap)->class_name);
  cl_free((*smap)->tokens);
  cl_free(*smap);

  return 1;
}


/**
 * Deletes a Mapping object.
 *
 * @param map  Address of the object to delete.
 * @return     Always 1.
 */
int
drop_mapping(Mapping *map)
{
  (*map)->corpus = NULL;
  (*map)->attribute = NULL;

  cl_free((*map)->mapping_name);
  cl_free((*map)->classes);
  cl_free(*map);

  return 1;
}

/**
 * Returns the Attribute member.
 */
Attribute *
get_attribute_of_mapping(Mapping map)
{
  return map ? map->attribute : NULL;
}

/**
 * Returns the first entry in the "classes" member,
 * that is, the array of data.
 */
SingleMapping
get_class_array_of_mapping(Mapping map)
{
  return map ? map->classes : NULL;
}

/**
 * Writes a description of a Mapping object to STDERR.
 */
void
print_mapping(Mapping map)
{
  int cp, tp;

  fprintf(stderr, "---------------------------------------- Mapping: \n");

  fprintf(stderr, "Name:  %s\n", map->mapping_name);
  fprintf(stderr, "Valid: %s/%s\n", map->corpus->registry_name, map->attribute->any.name);
  fprintf(stderr, "NrCls: %d\n", map->nr_classes);

  for (cp = 0; cp < map->nr_classes; cp++) {
    fprintf(stderr, "%5d/%s with %d members: \n", cp, map->classes[cp].class_name, map->classes[cp].nr_tokens);
    for (tp = 0; tp < map->classes[cp].nr_tokens; tp++)
      fprintf(stderr, "\t%d/%s", map->classes[cp].tokens[tp], cl_id2str(map->attribute, map->classes[cp].tokens[tp]));
    fprintf(stderr, "\n");
  }

  fprintf(stderr, "------------------------------------------------- \n");

}

/* -------------------- token -> class */

/**
 * Gets the SingleMapping that contains a particular token in the given Mapping.
 *
 * @param map    The Mapping to look in.
 * @param token  The item to look for, identified by string.
 * @return       The SingleMapping representing the class that contains that
 *               token (or NULL if the token was not found).
 */
SingleMapping
map_token_to_class(Mapping map, char *token)
{
  int class_num;

  if ((class_num = map_token_to_class_number(map, token)) >= 0)
    return &(map->classes[class_num]);
  else
    return NULL;
}

/**
 * Gets the number of the class that contains a particular token in the given Mapping.
 *
 * @param map    The Mapping to look in.
 * @param token  The item to look for, identified by string.
 * @return       The "class number" of the class containing the item (i.e. an
 *               index in the Mapping's "array" of SingleMappings) or -1 if the
 *               item was not found.
 */
int
map_token_to_class_number(Mapping map, char *token)
{
  int id;

  id = cl_str2id(map->attribute, token);

  if (id >= 0 && CL_ALL_OK())
    return map_id_to_class_number(map, id);

  return -1;
}

/**
 * Gets the number of the class that contains a particular token in the given Mapping.
 *
 * @param map  The Mapping to look in.
 * @param id   The item to look for, identified by its integer ID.
 * @return     The "class number" of the class containing the item (i.e. an
 *             index in the Mapping's "array" of SingleMappings) or -1 if the
 *             item was not found.
 */
int map_id_to_class_number(Mapping map, int id)
{
  int smp;

  for (smp = 0; smp < map->nr_classes; smp++)
    if (member_of_class_i(map, &(map->classes[smp]), id))
      return smp;
  return -1;
}

/* -------------------- class -> {tokens} */


/**
 * Gets the location of the item IDs in this class.
 *
 * @param map        The SingleMapping representing the class in question.
 * @param nr_tokens  Address of an integer, which will be set
 *                   to the number of items in this class.
 * @return           A pointer to the item IDs of this class
 *                   (don't free this!!)
 */
int *
map_class_to_tokens(SingleMapping map, int *nr_tokens)
{
  *nr_tokens = map->nr_tokens;
  return map->tokens;
}



/* -------------------- utils */

/**
 * Gets the number of classes possessed by this Mapping.
 */
int
number_of_classes(Mapping map)
{
  return map->nr_classes;
}

/**
 * Find a class within this mapping.
 *
 * @param map   The Mapping to look in.
 * @param name  The class to look for.
 * @return      The SingleMapping containing the class
 *              which has the name "name", or NULL if not found.
 */
SingleMapping
find_mapping(Mapping map, char *name)
{
  int i;

  for (i = 0; i < map->nr_classes; i++)
    if (CL_STREQ(map->classes[i].class_name, name))
      return &(map->classes[i]);

  return NULL;
}

/**
 * Gets the number of tokens (really, "items") possessed by this Mapping.
 */
int
number_of_tokens(SingleMapping map)
{
  return map->nr_tokens;
}

/**
 * Gets the SingleMapping's name.
 */
const char *
name_of_mapping_class(SingleMapping map)
{
  return (const char *)map->class_name;
}


/* -------------------- predicates */

/**
 * Checks whether an item is a member of a class in a Mapping.
 *
 * @see member_of_class_i
 * @param map    The mapping to look in.
 * @param class  The class to check.
 * @param token  The item to look for (identified by its actual string).
 * @return       Boolean.
 */
int
member_of_class_s(Mapping map, SingleMapping class, char *item)
{
  int id = cl_str2id(map->attribute, item);

  if (id < 0 || !CL_ALL_OK())
    return 0;
  else
    return member_of_class_i(map, class, id);
}

/**
 * Checks whether a item is a member of a class in a Mapping.
 *
 * @see member_of_class_s
 * @param map    The mapping to look in.
 * @param class  The class to check.
 * @param id     The item to look for (identified by its integer ID).
 * @return       Boolean.
 */
int
member_of_class_i(Mapping map, SingleMapping class, int id)
{
  return (NULL != bsearch(&id, class->tokens, class->nr_tokens, sizeof(int), intcompare));
}

