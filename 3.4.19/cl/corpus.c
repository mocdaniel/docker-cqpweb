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

#include <ctype.h>
#include <sys/types.h>
#ifndef __MINGW__
#include <sys/utsname.h>
#include <pwd.h>
#include <grp.h>
#endif

#include "globals.h"

#include "attributes.h"
#include "registry.tab.h"       /* the bison parser table */

#include "corpus.h"


/* ---------------------------------Interface to registry parser */

/**
 * File pointer for loading corpus registry.
 * (External variable, defined in the output from parsing registry.y)
 */
extern FILE *cregin;
/**
 * Pointer to a corpus object that is used when loading from the registry.
 * (External variable, defined in the output from parsing registry.y)
 */
extern Corpus *cregcorpus;

/**
 * Restarts the registry file parse;
 * function created in output from parsing registry.y
 */
extern void cregrestart(FILE *file);

/**
 * Parse a corpus registry file.
 *
 * The file to be parsed is specified by global variables
 * cregin_path and cregin_name.
 *
 * Function created in output from parsing registry.y
 */
int cregparse();
/**
 * Full path of the registry file currently being parsed (for registry parser error messages)
 *
 * @see cregparse
 */
char *cregin_path = "";
/**
 * The name of registry file currently being parsed (for registry parser error messages)
 *
 * @see cregparse
 */
char *cregin_name = "";


/**
 * Buffer for an error message. {Used in registry parser???}
 */
char errmsg[CL_MAX_LINE_LENGTH];




/* ---------------------------------------------------------------------- */

/**
 * Head of a linked list of loaded corpus handles (for memory manager).
 */
Corpus *loaded_corpora = NULL;

/* ---------------------------------------------------------------------- */




/**
 * The default registry directory.
 *
 * It is initialised when the function that reads it is first called.
 *
 * @see cl_standard_registry
 * TODO: would this be better as a static variable WITHIN that function?
 * That way there is no change of anyone acessing the regdir variable
 * except via that function...
 *
 */
static char *regdir = NULL;

/**
 * Gets a string containing the path of the default registry directory.
 *
 * Note this is a pointer to an internal string, and therefore must not
 * be altered or freed.
 *
 * @return  The value of the corpus-module-internal variable regdir,
 *          which is initialised from the environment variable
 *          REGISTRY_ENVVAR or, failing that, the macro
 *          REGISTRY_DEFAULT_PATH.
 * @see     REGISTRY_ENVVAR
 * @see     REGISTRY_DEFAULT_PATH
 */
char *
cl_standard_registry()
{
  if (regdir == NULL)
    regdir = getenv(CWB_REGISTRY_ENVVAR);
  if (regdir == NULL)
    regdir = CWB_REGISTRY_DEFAULT_PATH;
  return regdir;
}




/* ---------------------------------------------------------------------- */


/**
 * Gets a pointer to the Corpus object with the specified CWB-name and registry location.
 *
 * (Works by searching the loaded_corpora global linked list.)
 *
 * @param  registry_dir       The registry directory.
 * @param  registry_name      The CWB name of the corpus.
 * @return                    The Corpus, or NULL if it wasn't found.
 */
Corpus *
find_corpus(char *registry_dir, char *registry_name)
{
  Corpus *c;
  char *mark;

  if (registry_dir == NULL)
    registry_dir = cl_standard_registry();

  for (c = loaded_corpora; c != NULL; c = c->next) {
    int l_dir = strlen(c->registry_dir);
    if (CL_STREQ(registry_name, c->registry_name) &&      /* corpus ID must be the same */
      (mark = strstr(registry_dir, c->registry_dir)) &&   /* find registry dir of <c> as substring of list <registry_dir>
                                                           * now we must check that the substring corresponds
                                                           * to a full component of the list */
      (mark == registry_dir || mark[-1] == PATH_SEPARATOR) && /* must start at beginning of string or after ':' separator */
      (mark[l_dir] == '\0' || mark[l_dir] == PATH_SEPARATOR)  /* must end at end of string or before ':' separator */
    ) {
      break;
    }
  }

  return c; /* either return matching corpus object or NULL at end of list */
}

/**
 * Gets a file handle for the registry file of the corpus with the specified CWB-name and registry location.
 *
 * The registry file is opened for text-mode read.
 *
 * @param  registry_dir       The registry directory.
 * @param  registry_name      The CWB name of the corpus.
 * @param  real_registry_dir  This will be set to a pointer to the "real"
 *                            name of the directory derived from the
 *                            registry_dir parameter.
 * @return                    A file handle, or NULL in case of error.
 */
FILE *
find_corpus_registry(char *registry_dir, char *registry_name, char **real_registry_dir)
{
  char full_name[CL_MAX_LINE_LENGTH];

  int re_p, ins_p, p, start_of_entry, end_of_entry;

  FILE *fd;

  re_p = 0;

  while (1) {
    if (registry_dir[re_p] == '\0') {
      *real_registry_dir = NULL;
      return NULL;
    }
    else {
      if (registry_dir[re_p] == '?' && registry_dir[re_p + 1] != '\0'
          && registry_dir[re_p + 1] != PATH_SEPARATOR)
        re_p++; /* this is an optional registry directory, which will not cause warnings if it is not mounted */

      ins_p = 0;
      start_of_entry = re_p;

      do {
        full_name[ins_p++] = registry_dir[re_p++];
      } while ((registry_dir[re_p] != PATH_SEPARATOR) && (registry_dir[re_p] != '\0'));

      end_of_entry = re_p;

      if (full_name[ins_p - 1] != SUBDIR_SEPARATOR)
        full_name[ins_p++] = SUBDIR_SEPARATOR;

      for (p = 0; registry_name[p]; p++)
        full_name[ins_p++] = registry_name[p];

      full_name[ins_p] = '\0';

      if ((fd = fopen(full_name, "r")) != NULL) {
        (*real_registry_dir) = (char *) cl_malloc(end_of_entry - start_of_entry
            + 1);
        strncpy(*real_registry_dir, registry_dir + start_of_entry, end_of_entry
            - start_of_entry);
        (*real_registry_dir)[end_of_entry - start_of_entry] = '\0';
        return fd;
      }
      else if (registry_dir[re_p] == PATH_SEPARATOR)
        re_p++;
    }
  }

  /* never reached */
  assert(0 && "Not reached");
  return NULL;
}

/**
 * Checks whether the corpus can be accessed.
 *
 * If this corpus has access restriction in the form of a list
 * of users, then this function checks if the current user is
 * on that list.
 *
 * Then ditto for the list of groups and current group;
 * then ditto for the list of hosts and current host.
 *
 * Note that this is currently disabled for users/groups.
 * So, if either Corpus::userAccessList or Corpus::groupAccessList
 * are changed from their initial (NULL) setting, this function
 * will return false.
 *
 * On the other hand, the function does work with hosts. If a list
 * of allows hosts is set, this function will return true iff
 * the current host is on that list.
 *
 * Finally note: if compiled for Windows, this function returns
 * true without actually checking anything, because windows
 * does not support the POSIX user/group functionality on which
 * all of this relies. Under Windows, access policies have to
 * managed by the cqpserver rather than by the CL simply reading from
 * the registry.
 *
 * @param corpus   The corpus.
 * @param verbose  A boolean. Currently ignored.
 * @return         A boolean: true if access is OK, else false.
 */
int
check_access_conditions(Corpus *corpus, int verbose)
{
  int access_ok = 1;

#ifndef __MINGW__

  struct passwd *pwd = NULL;

  /* get password data only if we have user / group access restrictions */
  if (corpus->userAccessList || corpus->groupAccessList) {
    /*     pwd = getpwuid(getuid()); */
    fprintf(stderr, "CL Error: Sorry, user/group access restrictions are disabled due to incompatibilities.\n");
    if (pwd == NULL) {
      perror("getpwuid(getuid()): can't get user information");
      access_ok = 0;
    }
  }

  if (access_ok && corpus->userAccessList) {
    if (pwd == NULL) {
      perror("getpwuid(getuid()): can't get user information");
      access_ok = 0;
    }
    else if (!memberIDList(pwd->pw_name, corpus->userAccessList)) {
      /* user is not a member of the user access list */
      access_ok = 0;
    }
  }

  if (access_ok && corpus->groupAccessList) {
    gid_t gidset[16];
    int nr_groups;

    if ((nr_groups = getgroups(16, gidset)) < 0) {
      perror("getgroups(2): cant' get group information");
      access_ok = 0;
    }
    else {
      int i;
      struct group *grpent = NULL;

      for (i = 0; i < nr_groups; i++) {

        /*      grpent = getgrgid(gidset[i]); */
        fprintf(stderr, "CL Error: Sorry, user/group access restrictions are disabled due to incompatibilities.\n");

        if (grpent == NULL) {
          perror("getgrgid(2): ");
          fprintf(stderr, "Can't get group information for gid %d\n", (int) gidset[i]);
          access_ok = 0;
        }
        else if (memberIDList(grpent->gr_name, corpus->groupAccessList)) {
          access_ok = 1;
          break;
        }
      }
    }
  }

  if (access_ok && corpus->hostAccessList) {
    struct utsname hostinfo;

    if (uname(&hostinfo) < 0) {
      perror("uname(2):");
      access_ok = 0;
    }
    else if (!memberIDList(hostinfo.nodename, corpus->hostAccessList)) {
#if 0
      IDList l;

      fprintf(stderr,
          "The corpus ``%s'' may be used on the following systems only:\n",
          corpus->id ? corpus->id : "(unknown)");

      for (l = corpus->hostAccessList; l; l = l->next) {
        fprintf(stderr, "\t%s\n", l->string ? l->string : "(null)");
      }
#endif
    }
    else
      access_ok = 1;
  }

  if (!access_ok)
    fprintf(stderr,
            "User ``%s'' is not authorized to access corpus ``%s''\n",
            (pwd && pwd->pw_name) ? pwd->pw_name : "(unknown)", corpus->name);
#endif

  return access_ok;
}

/**
 * Creates a Corpus object to represent a given indexed corpus, located in
 * a given directory accessible to the program.
 *
 * @param registry_dir   Path to the CWB registry directory from which the corpus is to be loaded.
 *                       This may be NULL, in which case the default registry directory is used.
 * @param registry_name  The CWB-name of the indexed corpus to load (in the all-lowercase form)
 * @return               Pointer to the resulting Corpus object.
 */
Corpus *
cl_new_corpus(char *registry_dir, char *registry_name)
{
  char *real_registry_name;
  static char *canonical_name = NULL;
  Corpus *corpus;

  /* corpus name must be all lowercase at this level
   * -> canonicalise (standard) uppercase and (deprecated) mixed-case forms */
  cl_free(canonical_name); /* if necessary, free buffer allocated in previous call to setup_corpus() */

  canonical_name = cl_strdup(registry_name);
  cl_id_tolower(canonical_name);
  if (!cl_id_validate(canonical_name))
    fprintf(stderr, "cl_new_corpus: <%s> is not a valid corpus name\n", registry_name);

  /* ------------------------------------------------------------------ */

  if (NULL != (corpus = find_corpus(registry_dir, canonical_name))) {
    /* we already have the beast loaded, so just increment the references */
    corpus->nr_of_loads++;
  }
  else {
    /* it's not yet in memory, so create and load it */

    if (registry_dir == NULL)
      registry_dir = cl_standard_registry();

    if (!(cregin = find_corpus_registry(registry_dir, canonical_name, &real_registry_name)))
      fprintf(stderr, "cl_new_corpus: can't locate <%s> in %s\n", registry_name, registry_dir);
    else {
      cregrestart(cregin);
      cregin_path = real_registry_name;
      cregin_name = canonical_name;
      if (cregparse() == 0) { /* OK */
        if (check_access_conditions(cregcorpus, 0)) {
          corpus = cregcorpus;
          corpus->registry_dir = real_registry_name;
          corpus->registry_name = cl_strdup(canonical_name);
          corpus->next = loaded_corpora;
          loaded_corpora = corpus;
          /* check whether ID field corresponds to name of registry file */
          if (corpus->id && (strcmp(corpus->id, canonical_name) != 0)) {
            fprintf(
                stderr,
                "CL warning: ID field '%s' does not match name of registry file %s/%s\n",
                corpus->id, real_registry_name, canonical_name);
          }
        }
        else
          cl_delete_corpus(cregcorpus);
      }
      cregin_path = "";
      cregin_name = "";
      cregcorpus = NULL;
      fclose(cregin);
    }
  }

  return corpus;
}

/**
 * Deletes a Corpus object from memory.
 *
 * A Corpus object keeps track of how many times it has been requested
 * via cl_new_corpus(). When cl_delete_corpus() is called, the object is only
 * actually deleted when there is just one outstanding request.
 * Otherwise, the variable tracking the number of requests is decremented.
 *
 * @param corpus  The Corpus to delete.
 * @return        Always 1.
 */
int
cl_delete_corpus(Corpus *corpus)
{
  Corpus *prev;

  assert(corpus != NULL);
  assert(corpus->nr_of_loads > 0);

  /* decrement the number of references to this corpus */
  corpus->nr_of_loads--;

  /* delete it physically iff nobody wants to have it any more */
  if (0 == corpus->nr_of_loads) {
    /* remove it from the linked list */
    if (corpus == loaded_corpora)
      loaded_corpora = corpus->next;
    else {
      for (prev = loaded_corpora ; prev && (prev->next != corpus) ; prev = prev->next)
        ;
      if (prev == NULL) {
        if (corpus != cregcorpus)
          assert("Error in list of loaded corpora" && 0);
      }
      else {
        assert(prev->next == corpus);
        prev->next = corpus->next;
      }
    }

    /* now, time to actually get rid of it! */
    while (NULL != corpus->attributes)
      cl_delete_attribute(corpus->attributes);

    corpus->attributes = NULL;
    corpus->next = NULL;

    cl_free(corpus->id);
    cl_free(corpus->name);
    cl_free(corpus->path);
    cl_free(corpus->info_file);
    cl_free(corpus->registry_dir);
    cl_free(corpus->registry_name);

    cl_free(corpus->admin);

    if (corpus->groupAccessList)
      FreeIDList(&(corpus->groupAccessList));

    if (corpus->userAccessList)
      FreeIDList(&(corpus->userAccessList));

    if (corpus->hostAccessList)
      FreeIDList(&(corpus->hostAccessList));

    cl_free(corpus);
  }

  return 1;
}

/* ---------------------------------------------------------------------- */

/**
 * Prints a description of the corpus to STDOUT.
 *
 * TODO might be nice to have this function offer an option of XML-style output.
 * TODO might also be nice to have it return a string, or send to a parameter stream,
 * so that the caller can decide what to do with it: direct UI formatting does not really
 * belong in the low-level CL.
 */
void
describe_corpus(Corpus *corpus)
{
  Attribute *attr;

  assert(corpus != NULL);

  printf("\n\n-------------------- CORPUS SETUP ---------------------\n\n");

  printf("ID:\t%s\n", corpus->id ? corpus->id : "(null)");
  printf("Name:\t%s\n", corpus->name ? corpus->name : "(null)");
  printf("Path:\t%s\n", corpus->path ? corpus->path : "(null)");
  printf("Info:\t%s\n", corpus->info_file ? corpus->info_file : "(null)");

  printf("\nRegistry Directory:\t%s\n",
      corpus->registry_dir ? corpus->registry_dir : "(null)");
  printf("Registry Name:     \t%s\n\n",
      corpus->registry_name ? corpus->registry_name : "(null)");

  printf("Attributes:\n");
  for (attr = (Attribute *) (corpus->attributes); attr != NULL; attr
      = (Attribute *) (attr->any.next))
    describe_attribute(attr);

  printf("\n\n------------------------- END -------------------------\n\n");
}

/* ---------------------------------------------------------------------- */

/**
 * Gets a list of the named attributes that this corpus posesses.
 *
 * This function creates a list of strings containing the names of all and only
 * those Attributes in this corpus whose type matches that specified in the
 * second parameter.
 *
 * @param corpus          The corpus whose attributes are to be listed.
 * @param attribute_type  The type of attributes to be listed. This must be one
 *                        of the attribute type macros: ATT_POS, ATT_STRUC etc.
 *                        For all attributes, specify ATT_ALL (natuerlich).
 * @return                String list containing names of all the corpus's
 *                        attributes that have the desired type. All the
 *                        actual character buffers have been newly
 *                        allocated, so it is safe to call cl_free_string_list
 *                        on the returned cl_string_list object once you're
 *                        done with it.
 */
cl_string_list
cl_corpus_list_attributes(Corpus *corpus, int attribute_type)
{
  Attribute *attr;
  cl_string_list attnames = cl_new_string_list();

  /* foreach att, if any of the bits set on the parameter match its type bit, add to the list!  */
  for (attr = corpus->attributes ; attr != NULL ; attr = (Attribute *)attr->any.next)
    if (0 != (attr->any.type & attribute_type))
      cl_string_list_append(attnames, cl_strdup(attr->any.name));
  /* (that check allows ATT_ANY and ATT_REAL to be passed, and we will get the right result */

  return attnames;
}

/* ---------------------------------------------------------------------- */

/**
 * Deletes an IDList object, and sets the argument pointer to NULL.
 *
 * @param list  IDList to delete.
 */
void
FreeIDList(IDList *list)
{
  IDList l;
  while (*list) {
    l = *list;
    *list = (*list)->next;
    cl_free(l->string);
    cl_free(l);
  }
  *list = NULL;
}


/**
 * Checks whether the specified string occurs in the given IDList.
 *
 * @param  s  The username, groupname, or hostname to look for.
 * @param  l  The IDList to search.
 * @return    Boolean: true if s is a member of the list, else false.
 */
int
memberIDList(char *s, IDList l)
{
  for ( ; l ; l = l->next)
    if (CL_STREQ(s, l->string))
      return 1;
  return 0;
}

/* ---------------------------------------------------------------------- */

/*
 * corpus properties
 */

/**
 * Gets the first entry in this corpus's list of properties.
 *
 * (The corpus properties iterator / property datatype is public.)
 *
 * @param corpus    Pointer to the Corpus object.
 * @return          The first property.
 */
CorpusProperty
cl_first_corpus_property(Corpus *corpus)
{
  return corpus->properties;
}

/**
 * Gets the next corpus property on the list of properties.
 *
 * (The corpus properties iterator / property datatype is public.)
 *
 * @param prop      The current property.
 * @return          The next property on the list, or NULL if there isn't one.
 *
 */
CorpusProperty
cl_next_corpus_property(CorpusProperty prop)
{
  if (prop == NULL)
    return NULL;
  else
    return prop->next;
}

/**
 * Gets the value of the specified corpus property.
 *
 * @param corpus    Pointer to the Corpus object.
 * @param property  Name of the property to retrieve.
 * @return          Pointer to string that contains the
 *                  value of the property, or NULL if
 *                  the specified property is undefined
 *                  for this Corpus object.
 */
char *
cl_corpus_property(Corpus *corpus, char *property)
{
  CorpusProperty p = cl_first_corpus_property(corpus);

  while (p != NULL && strcmp(property, p->property))
    p = cl_next_corpus_property(p);
  if (p != NULL)
    return p->value;
  else
    return NULL;
}





/**
 * Retrieves the special 'charset' property from a Corpus object.
 *
 * @param corpus  The corpus object from which to retrieve the charset
 * @return        The character set (as a CorpusCharset object).
 */
CorpusCharset
cl_corpus_charset(Corpus *corpus)
{
  return corpus->charset;
}

/** structure for the global list of charset names @see charset_names */
typedef struct {
  CorpusCharset id;
  char *name;
} charset_spec;

/** a list of charset names as strings paired with CorpusCharset ID values;
 *  where there are multiple possible names for one ID, the pair with the
 *  CWB-preferred name comes first in the array (and is the same as the
 *  identifier used for that charset in the CorpusCharset enumeration).
 *
 */
const charset_spec charset_names[] = {
    { ascii,    "ascii" },
    { latin1,   "latin1" },
    { latin1,   "iso-8859-1" },
    { latin2,   "latin2" },
    { latin2,   "iso-8859-2" },
    { latin3,   "latin3" },
    { latin3,   "iso-8859-3" },
    { latin4,   "latin4" },
    { latin4,   "iso-8859-4" },
    { cyrillic, "cyrillic" },
    { cyrillic, "iso-8859-5" },
    { arabic,   "arabic" },
    { arabic,   "iso-8859-6" },
    { greek,    "greek" },
    { greek,    "iso-8859-7" },
    { hebrew,   "hebrew" },
    { hebrew,   "iso-8859-8" },
    { latin5,   "latin5" },
    { latin5,   "iso-8859-9" },
    { latin6,   "latin6" },
    { latin6,   "iso-8859-10" },
    { latin7,   "latin7" },
    { latin7,   "iso-8859-13" },
    { latin8,   "latin8" },
    { latin8,   "iso-8859-14" },
    { latin9,   "latin9" },
    { latin9,   "iso-8859-15" },
    { utf8,     "utf8" },
    { unknown_charset, NULL }
    };

/**
 * Gets a string containing the name of the specified CorpusCharset character set object.
 *
 * Note that returned string cannot be modified.
 */
const char *
cl_charset_name(CorpusCharset id)
{
  int i;

  for (i = 0; charset_names[i].name; i++)
    if (id == charset_names[i].id)
      return charset_names[i].name;
  return "<unsupported>";
}

/**
 * Gets a CorpusCharset enumeration with the id code for the given string.
 */
CorpusCharset
cl_charset_from_name(const char *name)
{
  int i;
  CorpusCharset fallback = unknown_charset;
  for (i = 0; charset_names[i].name; i++)
    if (0 == strcasecmp(name, charset_names[i].name))
      return (charset_names[i].id);
  return fallback;
}

/**
 * Checks whether a string represents a valid charset, and returns a pointer to the name in
 * canonical form (ie lacking any non-standard case there may be in the input string).
 *
 * Note that the returned string cannot be modified.
 *
 * @param name_to_check  String containing the character set name to be checked
 * @return               Pointer to canonical-form string for that charset's name
 *                       or NULL if name_to_check cannot be linked to a valid charset.
 */
const char *
cl_charset_name_canonical(char *name_to_check)
{
  int i;
  for (i = 0; charset_names[i].name; i++)
    if (0 == strcasecmp(name_to_check, charset_names[i].name))
      return charset_names[i].name;
  return NULL;
}



/**
 * Adds a property to the list of corpus properties.
 * Use this function from registry.y only!
 *
 * If the property is already defined, ignore and warn.
 * If the property is 'charset', corpus charset is set as well.
 *
 * @param corpus    Corpus object to add property to.
 * @param property  Name of property to add.
 * @param value     Value of property to add.
 */
void
add_corpus_property(Corpus *corpus, char *property, char *value)
{
  CorpusProperty new_prop;

  if (cl_corpus_property(corpus, property) != NULL) {
    fprintf(stderr,
        "REGISTRY WARNING (%s/%s): re-defintion of property '%s' (ignored)\n",
        cregin_path, cregin_name, property);
  }
  else {
    new_prop = (CorpusProperty) cl_malloc(sizeof(struct TCorpusProperty));
    new_prop->property = property; /* use this function from registry.y only! */
    new_prop->value = value; /* property & value are strdup()ed in registry.l */
    new_prop->next = corpus->properties;
    corpus->properties = new_prop;

    /* if property=='charset', set corpus->charset accordingly */
    if (0 == strcmp(property, "charset"))
      corpus->charset = cl_charset_from_name(value);
  }
}
