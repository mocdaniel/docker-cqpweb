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

#include "globals.h"

#include "endian.h"
#include "corpus.h"
#include "fileutils.h"
#include "makecomps.h"
#include "list.h"

#include "attributes.h"


/*
 *******************************************************************
 * FLAGS controlling how ensure_component() behaves.
 *******************************************************************
 */

/* TODO: these should be either
 * (a) dynamic - set at runtime or
 * (b) bound to a setting in config.mk or definitions.mk
 * changing these settings should not require hacking the source! */

/**
 * if CL_ENSURE_COMPONENT_EXITS is defined, ensure_component will exit
 * when the component can't be created or loaded.
 */
#if 0
#define CL_ENSURE_COMPONENT_EXITS
#endif

/**
 * if CL_ENSURE_COMPONENT_ALLOW_CREATION is defined, components may be created
 * on the fly by ensure_component.
 */
#if 0
#define CL_ENSURE_COMPONENT_ALLOW_CREATION
#endif

/**
 * if KEEP_SILENT is defined, ensure_component won't complain about
 * non-accessible data.
 *
 * @see ensure_component
 */
#define CL_ENSURE_COMPONENT_KEEP_SILENT



/*******************************************************************/

/**
 * The component_field_spec data type.
 *
 * @see Component_Field_Specs
 */
typedef struct component_field_spec {
  ComponentID id;        /**< the specifier for what kind of blob of info this component is; also used
                              as the index for this component in its Attribute's component array. */
  char *name;            /**< String used as the label for this component  (abbreviation of the
                              relevant label from in the ComponentID enumeration). */
  int using_atts;        /**< The attribute type of the Attributes that use this component */
  char *default_path;    /**< The default location of the file corresponding to this component;
                              can contain variables ($DIR=directory, $ANAME=attribute name) */
} component_field_spec;

/**
 * Global object in the "attributes" module, giving specifications
 * for each component in the array of components that each Attribute
 * object contains.
 */
static struct component_field_spec Component_Field_Specs[] =
{
  { CompDirectory,    "DIR",     ATT_ALL,    "$APATH"},

  { CompCorpus,       "CORPUS",  ATT_POS,    "$DIR" SUBDIR_SEP_STRING "$ANAME.corpus"},
  { CompRevCorpus,    "REVCORP", ATT_POS,    "$CORPUS.rev"},
  { CompRevCorpusIdx, "REVCIDX", ATT_POS,    "$CORPUS.rdx"},
  { CompCorpusFreqs,  "FREQS",   ATT_POS,    "$CORPUS.cnt"},
  { CompLexicon,      "LEXICON", ATT_POS,    "$DIR" SUBDIR_SEP_STRING "$ANAME.lexicon"},
  { CompLexiconIdx,   "LEXIDX",  ATT_POS,    "$LEXICON.idx"},
  { CompLexiconSrt,   "LEXSRT",  ATT_POS,    "$LEXICON.srt"},


  { CompAlignData,    "ALIGN",   ATT_ALIGN,  "$DIR" SUBDIR_SEP_STRING "$ANAME.alg"},
  { CompXAlignData,   "XALIGN",  ATT_ALIGN,  "$DIR" SUBDIR_SEP_STRING "$ANAME.alx"},

  { CompStrucData,    "STRUC",   ATT_STRUC,  "$DIR" SUBDIR_SEP_STRING "$ANAME.rng"},
  { CompStrucAVS,     "STRAVS",  ATT_STRUC,  "$DIR" SUBDIR_SEP_STRING "$ANAME.avs"},
  { CompStrucAVX,     "STRAVX",  ATT_STRUC,  "$DIR" SUBDIR_SEP_STRING "$ANAME.avx"},

  { CompHuffSeq,      "CIS",     ATT_POS,    "$DIR" SUBDIR_SEP_STRING "$ANAME.huf"},
  { CompHuffCodes,    "CISCODE", ATT_POS,    "$DIR" SUBDIR_SEP_STRING "$ANAME.hcd"},
  { CompHuffSync,     "CISSYNC", ATT_POS,    "$CIS.syn"},

  { CompCompRF,       "CRC",     ATT_POS,    "$DIR" SUBDIR_SEP_STRING "$ANAME.crc"},
  { CompCompRFX,      "CRCIDX",  ATT_POS,    "$DIR" SUBDIR_SEP_STRING "$ANAME.crx"},

  { CompLast,         "INVALID", 0,          "INVALID"}
};



/* ---------------------------------------------------------------------- */

static ComponentState work_out_component_state(Component *component);
static int comp_drop_component(Component *component);

/* ---------------------------------------------------------------------- */



/**
 * Gets the specification for the named component field.
 *
 * This function returns a pointer to an element of the global, static
 * Component_Field_Specs array.
 *
 * @param name  A string that identifies the component field to be looked up.
 * @return      Pointer to the desired specification, or NULL if not found.
 * @see         Component_Field_Specs
 */
struct component_field_spec *
find_cid_name(char *name)
{
  int i;

  for (i = 0; i < CompLast; i++) {
    if (strcmp(Component_Field_Specs[i].name, name) == 0)
      return &Component_Field_Specs[i];
  }
  return NULL;
}

/**
 * Gets the specification for the identified component field.
 *
 * This function returns a pointer to an element of the global, static
 * Component_Field_Specs array.
 *
 * @param id    The ComponentID for the component field to be looked up.
 * @return      Pointer to the desired specification, or NULL if not found.
 * @see         Component_Field_Specs
 */
struct component_field_spec *
find_cid_id(ComponentID id)
{
  if (id < CompLast)
    return &Component_Field_Specs[id];
  else
    return NULL;
}

/**
 * Gets a string containing the name of the attribute component
 * with the specified ID-code.
 */
char *
cid_name(ComponentID id)
{
  component_field_spec *spec = find_cid_id(id);
  return (spec == NULL ? "((NULL))" : spec->name);
}

/**
 * Gets the identifier of the attribute component with the specified name.
 */
ComponentID
component_id(char *name)
{
  component_field_spec *spec = find_cid_name(name);
  return (spec == NULL ? CompLast : spec->id);
}


/* TODO this function does not appear to be used anywhere ??*/
/**
 * Checks whether a particular Attribute type can possess
 * the specified component field.
 *
 * @return  Boolean.
 */
int
MayHaveComponent(int attr_type, ComponentID cid)
{
  component_field_spec *spec;

  spec = find_cid_id(cid);

  if (spec && (spec->id != CompLast))
    return (spec->using_atts & attr_type) ? 1 : 0;
  else
    return 0;
}

/**
 * Gets a string containing a description of the specified attribute type.
 *
 * Non-exported function.
 *
 * @param i  The attribute-type whose name is required.
 *           (Should be one of the values of the constants
 *           defined in cl.h.)
 * @return   String (pointer to internal constant string,
 *           do not change or free).
 */
static char *
aid_name(int i)
{
  switch (i) {
  case ATT_NONE:  return "NONE (ILLEGAL)"; break;
  case ATT_POS:   return "Positional Attribute"; break;
  case ATT_STRUC: return "Structural Attribute"; break;
  case ATT_ALIGN: return "Alignment Attribute"; break;
  case ATT_DYN:   return "Dynamic Attribute"; break;
  default:        return "ILLEGAL ATTRIBUTE TYPE"; break;
  }
  /* NOTREACHED */
  return NULL;
}

/**
 * Gets a string containing a description of the specified dynamic attribute argument type.
 *
 * Non-exported function.
 *
 * @param i  The argument-type whose name is required.
 *           (Should be one of the values of the constants
 *           defined in cl.h.)
 * @return   String (pointer to internal constant string,
 *           do not change or free).
 */
static char *
argid_name(int i)
{
  switch (i) {
  case ATTAT_NONE:   return "NONE(ILLEGAL)"; break;
  case ATTAT_POS:    return "CorpusPosition"; break;
  case ATTAT_STRING: return "String"; break;
  case ATTAT_VAR:    return "Variable[StringList]"; break;
  case ATTAT_INT:    return "Integer"; break;
  case ATTAT_FLOAT:  return "Float"; break;
  case ATTAT_PAREF:  return "PARef"; break;
  default:           return "ILLEGAL*ARGUMENT*TYPE"; break;
  }
  /* NOTREACHED */
  return NULL;
}




/**
 * Creates a DynArg object.
 *
 * The object created is a dynamic argument of the type specified by the argument type_id,
 * with its "next" pointer set to NULL.
 *
 * @see            DynArg
 * @param type_id  String specifying the type of argument required; choose from:
 *                 STRING, POS, INT, VARARG, FLOAT
 * @return         Pointer to the new DynArg object, or NULL in case of an invalid
 *                 type_id.
 */
DynArg *
makearg(char *type_id)
{
  DynArg *arg = (DynArg *)cl_malloc(sizeof(DynArg));

  arg->next = NULL;

  /* TODO this would be a lot neater with integer constants, not strigns. */
  if (CL_STREQ(type_id, "STRING"))
    arg->type = ATTAT_STRING;
  else if (CL_STREQ(type_id, "POS"))
    arg->type = ATTAT_POS;
  else if (CL_STREQ(type_id, "INT"))
    arg->type = ATTAT_INT;
  else if (CL_STREQ(type_id, "VARARG"))
    arg->type = ATTAT_VAR;
  else if (CL_STREQ(type_id, "FLOAT"))
    arg->type = ATTAT_FLOAT;
  else
    cl_free(arg);

  return arg;
}

/* ---------------------------------------------------------------------- */


/**
 * Sets up a corpus attribute.
 *
 * NEVER CALL THIS!! ONLY USED WHILE PARSING A REGISTRY ENTRY!!!!
 *
 * @param corpus          The corpus this attribute belongs to.
 * @param attribute_name  The name of the attribute (i.e. the handle it has in the registry file).
 * @param type            Type of attribute to be created.
 * @param data            Unused. It can just be NULL.
 */
Attribute *
setup_attribute(Corpus *corpus, char *attribute_name, int type, char *data)
{
  ComponentID cid;
  Attribute *attr = NULL;
  Attribute *prev;

  /* count of attributes that the corpus possesses already, including the default;
   * used to calculate this attribute's attr_number value. */
  int a_num;

  if (NULL != cl_new_attribute(corpus, attribute_name, type)) {
    fprintf(stderr, "attributes:setup_attribute(): Warning: \n"
            "  Attribute %s of type %s already defined in corpus %s\n",
            attribute_name, aid_name(type), corpus->id);
    return NULL;
  }

  attr = (Attribute *)cl_malloc(sizeof(Attribute));
  attr->type = type;
  attr->any.mother = corpus;
  attr->any.name = attribute_name;

  for (cid = CompDirectory; cid < CompLast; cid++)
    attr->any.components[cid] = NULL;

  /* if we're setting up "word", the attr_number will be 0; otherwise, start counting at 1 */
  a_num = (CL_STREQ(attribute_name, CWB_DEFAULT_ATT_NAME) && type == ATTAT_POS) ? 0 : 1;

  /* insert at end of attribute list */
  attr->any.next = NULL;
  if (corpus->attributes == NULL)
    corpus->attributes = attr;
  else {
    for (prev = corpus->attributes ; prev->any.next ; prev = prev->any.next)
      a_num++;
    prev->any.next = attr;
  }
  attr->any.attr_number = a_num;
  attr->any.path = NULL;

  /* ======================================== type specific initialization */

  switch (attr->type) {
  case ATT_POS:
    attr->pos.hc = NULL;
    attr->pos.this_block_nr = -1;
    break;

  case ATT_STRUC:
    attr->struc.has_attribute_values = -1; /* not yet known */
    break;

  default:
    break;
  }

  return attr;
}



/**
 * Finds an attribute that matches the specified parameters, if one exists,
 * for the given corpus.
 *
 * Note that although this is a cl_new_* function, and it is the canonical way
 * that we get an Attribute to call Attribute-functions on, it doesn't actually
 * create any kind of object. The Attribute exists already as one of the dependents
 * of the Corpus object; this function simply locates it and returns a pointer
 * to it.
 *
 * @see                   cl_new_attribute_oldstyle
 *
 * @param corpus          The corpus in which to search for the attribute.
 * @param attribute_name  The name of the attribute (i.e. the handle it has in the registry file).
 * @param type            Type of attribute to be searched for.
 *
 * @return                Pointer to Attribute object, or NULL if not found.
 */
Attribute *
cl_new_attribute(Corpus *corpus, const char *attribute_name, int type)
{
  Attribute *attr = NULL;

  if (!corpus)
    fprintf(stderr, "attributes:cl_new_attribute(): called with NULL corpus\n");
  else
    for (attr = corpus->attributes ; attr ; attr = attr->any.next)
      if (type == attr->type && CL_STREQ(attr->any.name, attribute_name))
        break;

  return attr;
}


/**
 * Deletes the specified Attribute object.
 *
 * The function also appropriately amends the Corpus object of which this
 * Attribute is a dependent. This means you can call it repreatedly on the first
 * element of a Corpus's Attribute list (as the linked list is automatically
 * adjusted).
 *
 * @return   Boolean: true for all OK, false for a problem.
 */
int
cl_delete_attribute(Attribute *attribute)
{
  Attribute *prev = NULL;
  DynArg *arg;
  Corpus *corpus;
  ComponentID cid;

  if (!attribute)
    return 0;

  corpus = attribute->any.mother;
  assert("NULL corpus in attribute" && (corpus != NULL));

  /* remove attribute from corpus attribute list */
  if (attribute == corpus->attributes)
    corpus->attributes = attribute->any.next;
  else {
    for (prev = corpus->attributes ; prev ; prev = prev->any.next)
      if (prev->any.next == attribute)
        break;

    if (prev == NULL)
      fprintf(stderr, "attributes:cl_delete_attribute():\n  Warning: Attribute %s not in list of corpus attributes\n", attribute->any.name);
    else {
      assert("Error in attribute chain" && prev->any.next == attribute);
      prev->any.next = attribute->any.next;
    }
  }

  /* get rid of components */
  for (cid = CompDirectory; cid < CompLast; cid++)
    if (attribute->any.components[cid])
      comp_drop_component(attribute->any.components[cid]);

  cl_free(attribute->any.name);
  cl_free(attribute->any.path);

  /* get rid of special fields */
  switch (attribute->type) {
  case ATT_POS:
    cl_free(attribute->pos.hc);
    break;
  case ATT_DYN:
    cl_free(attribute->dyn.call);
    while (attribute->dyn.arglist != NULL) {
      arg = attribute->dyn.arglist;
      attribute->dyn.arglist = arg->next;
      cl_free(arg);
    }
    break;
  default:
    break;
  }

  cl_free(attribute);
  return 1;
}

/**
 * Accessor function to get the mother corpus of the attribute.
 */
Corpus *
cl_attribute_mother_corpus(Attribute *attribute)
{
  return attribute->any.mother;
}



/**
 * Sets up a component for the given attribute.
 *
 * If the component of the specified ComponentID does not already exist,
 * a new Component object is created, set up, and assigned to the attribute's
 * component array. Finally, the component path is initialised using the
 * path argument.
 *
 * @see component_full_name
 * @param attribute The Attribute for which to create this component.
 * @param cid       The ID of the component to create.
 * @param path      Path to be passed to component_full_name. Can be NULL.
 * @return          The new component if all is OK. If a component with the
 *                  specified ID already exists, it is returned and no new
 *                  component is created (and a warning message is printed
 *                  to STDERR). If the attribute is NULL, return is NULL
 *                  (and a warning is printed).
 */
Component *
declare_component(Attribute *attribute, ComponentID cid, char *path)
{
  Component *component;

  if (attribute == NULL) {
    fprintf(stderr, "attributes:declare_component(): \n  NULL attribute passed in declaration of %s component\n", cid_name(cid));
    return NULL;
  }

  if (!(component = attribute->any.components[cid])) {
    component = (Component *)cl_malloc(sizeof(Component));
    component->id = cid;
    component->corpus = attribute->any.mother;
    component->attribute = attribute;
    component->path = NULL;

    init_mblob(&(component->data));
    attribute->any.components[cid] = component;

    /* we can then initialize the component path within the attribute */
    component_full_name(attribute, cid, path);
  }
  else
    fprintf(stderr, "attributes:declare_component(): Warning:\n  Component %s of %s declared twice\n", cid_name(cid), attribute->any.name);

  return component;
}


/**
 * Sets up a default set of components on the given attribute.
 *
 * Note that in each case, a call is made to declare_component
 * with the path as NULL.
 *
 * @see declare_component
 */
void
declare_default_components(Attribute *attribute)
{
  int i;

  if (attribute == NULL)
    fprintf(stderr, "attributes:declare_default_components(): \n  NULL attribute passed -- can't create defaults\n");
  else
    for (i = CompDirectory; i < CompLast; i++)
      if (0 != (Component_Field_Specs[i].using_atts & attribute->type) && NULL == attribute->any.components[i])
        declare_component(attribute, i, NULL);
}



/**
 * Works out and returns the state of the component.
 * For public interface see component_state().
 */
static ComponentState
work_out_component_state(Component *comp)
{
  assert(comp);

  if (comp->data.data != NULL)
    return ComponentLoaded;
  else if (comp->id == CompDirectory)
    return ComponentDefined;
  else if (comp->path == NULL)
    return ComponentUndefined;
  else if (file_length(comp->path) < 0) /* access error == EOF -> assume file doesn't exist */
    return ComponentDefined;
  else
    return ComponentUnloaded;
}


/**
 * Gets the state of a specified component on the given attribute.
 *
 * @param attribute  The attribute to look at.
 * @param cid        The component whose state to get.
 * @return           The return value in case the component is not
 *                   found is ComponentUndefined. Otherwise, some
 *                   other value of ComponentState.
 */
ComponentState
component_state(Attribute *attribute, ComponentID cid)
{
  if (attribute && cid < CompLast) {
    Component *comp = attribute->any.components[cid];
    if (comp == NULL)
      return ComponentUndefined;
    else
      return work_out_component_state(comp);
  }
  else
    return ComponentUndefined;
}


/**
 * Initializes the path of an attribute Component.
 *
 * This function starts with the path it is passed, and then evaluates variables
 * in the form $UPPERCASE. The resulting path is assigned to the specified
 * entry in the component array for the given Attribute.
 *
 * Note that if it is called for a Component that does not yet exist, this function
 * creates the component by calling declare_component().
 *
 * @see declare_component
 * @see Component_Field_Specs
 * @param attribute            The Attribute object to work with.
 * @param cid                  The identifier of the Component to which the path is to
 *                             be added.
 * @param path                 The path to assign to the component. Can be NULL,
 *                             in which case, the default path from Component_Field_Specs
 *                             is used.
 * @return                     Pointer to this function's static buffer for creating the
 *                             path (NB: NOT to the path in the actual component! which is a copy).
 *                             If a path already exists, a pointer to that path.
 *                             Either way, don't muck about with the buffer content.
 *                             NULL in case of error in Component_Field_Specs.
 */
char *
component_full_name(Attribute *attribute, ComponentID cid, char *path)
{
  component_field_spec *compspec;
  Component *component;

  static char buf[CL_MAX_LINE_LENGTH] = { '\0' };
  char rname[CL_MAX_LINE_LENGTH] = { '\0' };
  char *reference;
  char c;
  /* index into strings "path", "buf", "reference"/"rname" */
  int ppos = 0, bpos = 0, rpos = 0;

  /*  did we do the job before? */
  if ((component = attribute->any.components[cid]) != NULL && component->path != NULL)
    return component->path;

  /* component is so far undeclared. So try to guess the name: */
  compspec = NULL;
  if (path == NULL) {
    if (!(compspec = find_cid_id(cid))) {
      fprintf(stderr, "attributes:component_full_name(): Warning:\n"
              "  can't find component table entry for Component #%d\n", cid);
      return NULL;
    }
    path = compspec->default_path;
  }

//  buf[bpos] = '\0';

  while ((c = path[ppos]) != '\0') {
    if (c == '$') {
      /* the $ is a reference to the name of another component. */
      rpos = 0;
      c = path[++ppos];         /* first skip the '$' */
      while (isupper(c)) {
        rname[rpos++] = c;
        c = path[++ppos];       /* now, move over the reference while copying its name */
      }
      rname[rpos] = '\0';

      /* ppos now points to the first character after the reference;
       * rname holds the UPPERCASE name of the referenced component  */

      reference = NULL;

      if (CL_STREQ(rname, "HOME"))
        reference = getenv(rname);
      else if (CL_STREQ(rname, "APATH"))
        reference = (attribute->any.path ? attribute->any.path : attribute->any.mother->path);
      else if (CL_STREQ(rname, "ANAME"))
        reference = attribute->any.name;
      else if ((compspec = find_cid_name(rname)) != NULL)
        reference = component_full_name(attribute, compspec->id, NULL);

      if (reference == NULL) {
        fprintf(stderr, "attributes:component_full_name(): Warning:\n  Can't reference to the value of %s -- copying\n", rname);
        reference = rname;
      }

      /* the reference is copied into buf */
      for (rpos = 0; reference[rpos] != '\0'; rpos++, bpos++)
        buf[bpos] = reference[rpos];
    }
    else {
      /* just copy the character to the buffer, and advance */
      buf[bpos] = c;
      bpos++;
      ppos++;
    }
  }
  buf[bpos] = '\0';

  if (component != NULL)
    component->path = cl_strdup(buf);
  else
    declare_component(attribute, cid, buf);

  /*  and return it */
  return &buf[0];
  /* ?? why is buf returned instead of component->path, as earlier in the function? -- AH 16/9/09 */
}



/**
 * Loads the specified component for this attribute.
 *
 * "Loading" means that the file specified by the component's "path" member
 * is read into the "data" member.
 *
 * If the component is CompHuffCodes, part of the data is also copied to the
 * attribute's pos.hc member (that is, the beginning of the file).
 *
 * Note that the action of this function is dependent on the component's state.
 * If the component's state is ComponentUnloaded, the component is loaded.
 * If the component's state is ComponentDefined, the size is set to 0 and
 * nothing else is done.
 *
 * @param attribute  The Attribute object to work with.
 * @param cid        The identifier of the Component to load.
 * @return           Pointer to the component. This will be NULL if the
 *                   component has not been declared (i.e. created).
 */
Component *
load_component(Attribute *attribute, ComponentID cid)
{
  Component *comp;
  ComponentState state;

  assert(attribute != NULL && "Null attribute passed to load_component");

  if (NULL == (comp = attribute->any.components[cid])) {
    fprintf(stderr, "attributes:load_component(): Warning:\n  Component %s is not declared for %s attribute\n", cid_name(cid), aid_name(attribute->type));
    return NULL;
  }

  state = work_out_component_state(comp);

  if (ComponentUnloaded == state) {
    assert(comp->path != NULL);

    if (cid == CompHuffCodes) {
      if (cl_sequence_compressed(attribute)) {
        if (read_file_into_blob(comp->path, MMAPPED, sizeof(int), &(comp->data)) == 0)
          fprintf(stderr, "attributes:load_component(): Warning:\n  Data of %s component of attribute %s can't be loaded\n", cid_name(cid), attribute->any.name);
        else {
          int i;
          if (attribute->pos.hc != NULL)
            fprintf(stderr, "attributes:load_component: WARNING:\n\tHCD block already loaded, overwritten.\n");
          attribute->pos.hc = (HCD *)cl_malloc(sizeof(HCD));
          memcpy(attribute->pos.hc, comp->data.data, sizeof(HCD));

          /* convert network byte order to native integers */
          attribute->pos.hc->size = ntohl(attribute->pos.hc->size);
          attribute->pos.hc->length = ntohl(attribute->pos.hc->length);
          attribute->pos.hc->min_codelen = ntohl(attribute->pos.hc->min_codelen);
          attribute->pos.hc->max_codelen = ntohl(attribute->pos.hc->max_codelen);
          for (i = 0; i < MAXCODELEN; i++) {
            attribute->pos.hc->lcount[i] = ntohl(attribute->pos.hc->lcount[i]);
            attribute->pos.hc->symindex[i] = ntohl(attribute->pos.hc->symindex[i]);
            attribute->pos.hc->min_code[i] = ntohl(attribute->pos.hc->min_code[i]);
          }
          attribute->pos.hc->symbols = comp->data.data + (4+3*MAXCODELEN);

          comp->size = attribute->pos.hc->length;
          assert(work_out_component_state(comp) == ComponentLoaded);
        }
      }
      else
        fprintf(stderr, "attributes/load_component: missing files of compressed PA,\n\tcomponent CompHuffCodes not loaded\n");
    }
    else if (cid > CompDirectory && cid < CompLast) {
      /* i.e. any ComponentID value except CompDirectory / CompLast and CompHuffCodes */
      if (!read_file_into_blob(comp->path, MMAPPED, sizeof(int), &(comp->data)))
        fprintf(stderr, "attributes:load_component(): Warning:\n  Data of %s component of attribute %s can't be loaded\n",
                cid_name(cid), attribute->any.name);
      else {
        comp->size = comp->data.nr_items;
        assert(work_out_component_state(comp) == ComponentLoaded);
      }
    }
  }
  else if (ComponentDefined == state)
    comp->size = 0;

  return comp;
}




/**
 * Creates the specified component for the given Attribute.
 *
 * This function only works for the following components:
 * CompRevCorpus, CompRevCorpusIdx, CompLexiconSrt, CompCorpusFreqs.
 * Also, it only works if the state of the component is
 * ComponentDefined.
 *
 * "Create" here means create the CWB data files.  This is accomplished by
 * calling one of the "creat_*" functions, of which there is one for each
 * of the four available component types. These are defined in makecomps.c.
 *
 * Each of these functions reads in the data it needs, processes it, and then
 * writes a new file.
 *
 * @param attribute  The Attribute object to work with.
 * @param cid        The identifier of the Component to create.
 *
 * @return           Pointer to the component created, or NULL in case of
 *                   error (e.g. if an invalid/undefined component was requested).
 *
 */
Component *
create_component(Attribute *attribute, ComponentID cid)
{
  Component *comp = attribute->any.components[cid];

  if (cl_debug)
    fprintf(stderr, "Creating %s\n", cid_name(cid));

  if (component_state(attribute, cid) != ComponentDefined)
    return NULL;
  else {
    assert(comp != NULL);
    assert(comp->data.data == NULL);
    assert(comp->path != NULL);

    switch (cid) {

    case CompLast:
    case CompDirectory:
      /*  cannot create these */
      break;

    case CompCorpus:
    case CompLexicon:
    case CompLexiconIdx:
      fprintf(stderr, "attributes:create_component(): Warning:\n"
              "  Can't create the '%s' component. Use 'cwb-encode' to create it out of a text file\n",cid_name(cid));
      return NULL;

    case CompHuffSeq:
    case CompHuffCodes:
    case CompHuffSync:
      fprintf(stderr, "attributes:create_component(): Warning:\n"
              "  Can't create the '%s' component. Use 'cwb-huffcode' to create it out of an item sequence file\n",cid_name(cid));
      return NULL;

    case CompCompRF:
    case CompCompRFX:
      fprintf(stderr, "attributes:create_component(): Warning:\n"
              "  Can't create the '%s' component. Use 'cwb-compress-rdx' to create it out of the reversed file index\n",cid_name(cid));
      return NULL;

    case CompRevCorpus:
      creat_rev_corpus(comp);
      break;

    case CompRevCorpusIdx:
      creat_rev_corpus_idx(comp);
      break;

    case CompLexiconSrt:
      creat_sort_lexicon(comp);
      break;

    case CompCorpusFreqs:
      creat_freqs(comp);
      break;

    case CompAlignData:
    case CompXAlignData:
    case CompStrucData:
    case CompStrucAVS:
    case CompStrucAVX:
      fprintf(stderr, "attributes:create_component(): Warning:\n"
              "  Can't create the '%s' component of %s attribute %s.\nUse the appropriate external tool to create it.\n",
              cid_name(cid), aid_name(attribute->type), attribute->any.name);
      return NULL;


    default:
      comp = NULL;
      fprintf(stderr, "attributes:create_component(): Unknown cid: %d\n", cid);
      assert(0);
      break;
    }
    return comp;
  }
}



/**
 * Ensures that a component is loaded and ready.
 *
 * The state of the component specified should be ComponentLoaded
 * once this function has run (assuming all is well). If the
 * component is unloaded, the function will try to load it. If the
 * component is defined, the function MAY try to create it. If the
 * component is undefined, nothing will be done.
 *
 * There are flags in attributes.c that control the behaviour of
 * this function (e.g. if failure to ensure causes the program
 * to abort).
 *
 * @see CL_ENSURE_COMPONENT_KEEP_SILENT
 * @see CL_ENSURE_COMPONENT_EXITS
 * @see CL_ENSURE_COMPONENT_ALLOW_CREATION
 *
 * @param attribute     The Attribute object to work with.
 * @param cid           The identifier of the Component to "ensure".
 * @param try_creation  Boolean. True = attempt to create a
 *                      component that does not exist. False = don't.
 *                      This behaviour only applies when
 *                      CL_ENSURE_COMPONENT_ALLOW_CREATION is defined;
 *                      otherwise component creation will never be attempted.
 * @return              A pointer to the specified component (or NULL
 *                      if the component cannot be "ensured").
 */
Component *
ensure_component(Attribute *attribute, ComponentID cid, int try_creation)
{
  Component *comp = NULL;

  if (!(comp = attribute->any.components[cid])) {
    /* component is undeclared */
    fprintf(stderr, "attributes:ensure_component(): Warning:\n  Undeclared component: %s\n", cid_name(cid));
#ifdef CL_ENSURE_COMPONENT_EXITS
    exit(1);
#endif
    return NULL;
  }

  else {
    /* component IS declared, so let's see if we can ensure it. */
    switch (work_out_component_state(comp)) {

    case ComponentLoaded:
      /* already here, so do nothing */
      break;

    case ComponentUnloaded:
      /* try to load the component */
      load_component(attribute, cid);
      if (work_out_component_state(comp) != ComponentLoaded) {
#ifndef CL_ENSURE_COMPONENT_KEEP_SILENT
        fprintf(stderr, "attributes:ensure_component(): Warning:\n  Can't load %s component of %s\n", cid_name(cid), attribute->any.name);
#endif

#ifdef CL_ENSURE_COMPONENT_EXITS
        exit(1);
#endif
        return NULL;
      }
      break;

    case ComponentDefined:
      /* doesn't exist; try to create if the #defines are set up to allow that and if the caller wants */
      if (try_creation) {
#ifdef CL_ENSURE_COMPONENT_ALLOW_CREATION
        /* try to create the component */
        create_component(attribute, cid);
        if (work_out_component_state(comp) != ComponentLoaded) {
# ifndef CL_ENSURE_COMPONENT_KEEP_SILENT
          fprintf(stderr, "attributes:ensure_component(): Warning:\n  Can't load or create %s component of %s\n", cid_name(cid), attribute->any.name);
# endif

# ifdef CL_ENSURE_COMPONENT_EXITS
          exit(1);
# endif
          return NULL;
        }
#else
        /* this is the only alert-message NOT subject to the KEEP_SILENT definition */
        fprintf(stderr, "Sorry, but this program is not set up to allow the creation of corpus components.\n"
                        "Please refer to the manuals or use the ''cwb-makeall'' tool.\n");
#ifdef CL_ENSURE_COMPONENT_EXITS
        exit(1);
#endif
        return NULL;
#endif
      }
      else {
        /* !try_creation implies we should return the standard "not ensured" value (NULL). */
#ifndef CL_ENSURE_COMPONENT_KEEP_SILENT
        fprintf(stderr, "attributes:ensure_component(): Warning:\n  I'm not allowed to create %s component of %s\n", cid_name(cid), attribute->any.name);
#endif
#ifdef CL_ENSURE_COMPONENT_EXITS
        exit(1);
#endif
        return NULL;
      }
      break;

    case ComponentUndefined:
      /*  don't have this, -> error */
      fprintf(stderr, "attributes:ensure_component(): Warning:\n  Can't ensure undefined/illegal %s component of %s\n", cid_name(cid), attribute->any.name);
#ifdef CL_ENSURE_COMPONENT_EXITS
      exit(1);
#endif
      break;

    default:
      fprintf(stderr, "attributes:ensure_component(): Warning:\n  Illegal state of  %s component of %s\n",cid_name(cid), attribute->any.name);
#ifdef CL_ENSURE_COMPONENT_EXITS
      exit(1);
#endif
      break;
    }
  }
  return comp;
}



/**
 * Gets a pointer to the specified component for the given Attribute.
 */
Component *
find_component(Attribute *attribute, ComponentID cid)
{
  return attribute->any.components[cid];
}



/**
 * Delete a Component object (backend for drop_component).
 *
 * The argument component object, and all memory associated with it, is freed.
 *
 * @return Always 1.
 */
static int
comp_drop_component(Component *comp)
{
  assert(comp && "NULL component passed to attributes:comp_drop_component");
  assert(comp->attribute);

  if (comp->attribute->any.components[comp->id] != comp)
    assert(0 && "comp is not member of that attr");

  comp->attribute->any.components[comp->id] = NULL;

  /* Delete Huffcode data (which may or may not have been loaded by the point the component is freed) */
  if (comp->id == CompHuffCodes)
    cl_free(comp->attribute->pos.hc);

  mfree(&(comp->data));
  cl_free(comp->path);
  comp->corpus = NULL;
  comp->attribute = NULL;
  comp->id = CompLast;

  cl_free(comp);

  return 1;
}


/**
 * Drops the specified component for the given Attribute.
 *
 * @see                 comp_drop_component
 * @param attribute     The Attribute object to work with.
 * @param cid           The identifier of the Component to drop.
 * @return              Always 1.
 */
int
drop_component(Attribute *attribute, ComponentID cid)
{
  Component *comp = attribute->any.components[cid];
  if (comp)
    comp_drop_component(comp);
  return 1;
}



/* =============================================== LOOP THROUGH ATTRIUBTES */

/**
 * Non-exported variable: accessed via the attribute-looping functions.
 *
 * @see first_corpus_attribute
 * @see next_corpus_attribute
 */
static Attribute *loop_ptr;

/**
 * Get a pointer to the head entry in the specified corpus's list of attributes.
 *
 * @return NULL if the corpus parameter is NULL; otherwise a pointer to Attribute.
 */
Attribute *
first_corpus_attribute(Corpus *corpus)
{
  if (corpus)
    loop_ptr = corpus->attributes;
  else
    loop_ptr = NULL;

  return loop_ptr;
}

/**
 * Get a pointer to the next attribute on the list currently being processed.
 */
Attribute *
next_corpus_attribute(void)
{
  if (loop_ptr)
    loop_ptr = loop_ptr->any.next;
  return loop_ptr;
}



/* =============================================== INTERACTIVE FUNCTIONS */

/**
 * Prints a description of the attribute (inc.components) to STDOUT.
 */
void
describe_attribute(Attribute *attribute)
{
  DynArg *arg;
  ComponentID cid;

  printf("Attribute %s:\n", attribute->any.name);
  printf("  Type:        %s\n", aid_name(attribute->any.type));

  /* print type dependent additional data */

  if (attribute->type == ATT_DYN) {
    printf("  Arguments:   (");
    for (arg = attribute->dyn.arglist; arg; arg = arg->next) {
      printf("%s", argid_name(arg->type));
      if (arg->next != NULL)
        printf(", ");
    }
    printf("):%s\n"
           "               by \"%s\"\n",
           argid_name(attribute->dyn.res_type),
           attribute->dyn.call);
  }
  printf("\n");
  for (cid = CompDirectory; cid < CompLast; cid++)
    if (attribute->any.components[cid])
      describe_component(attribute->any.components[cid]);

  printf("\n\n");
}


/**
 * Prints a description of the component to STDOUT.
 */
void
describe_component(Component *component)
{
  printf("  Component %s:\n", cid_name(component->id));
  printf("    Attribute:   %s\n", component->attribute->any.name);
  printf("    Path/Value:  %s\n", component->path);
  printf("    State:       ");

  switch (work_out_component_state(component)) {
  case ComponentLoaded:
    printf("loaded");
    break;
  case ComponentUnloaded:
    printf("unloaded (valid & on disk)");
    break;
  case ComponentDefined:
    printf("defined  (valid, but not on disk)");
    break;
  case ComponentUndefined:
    printf("undefined (not valid)");
    break;
  default:
    printf("ILLEGAL! (Illegal component state %d)", work_out_component_state(component));
    break;
  }
  printf("\n\n");
}



/* =============================================== FEATURE-SET P-ATTRIBUTES */

/* TODO the feature set functions don't really seem to belong in this file */

/**
 * Generates a feature-set attribute value.
 *
 * @param s      The input string.
 * @param split  Boolean; if True, s is split on whitespace.
 *               If False, the function expects input in '|'-delimited format.
 * @return       The set attribute value in standard syntax ('|' delimited, sorted with cl_strcmp).
 *               If there is any syntax error, cl_make_set() returns NULL.
 */
char *
cl_make_set(char *s, int split)
{
  char *copy = cl_strdup(s);               /* work on copy of <s> */
  cl_string_list l = cl_new_string_list(); /* list of set elements */
  int ok = 0;                              /* for split and element check */

  char *p, *mark, *set;
  int i, sl, length;

  cl_errno = CDA_OK;

  /* (1) split input string into set elements */
  if (split) {
    /* split on whitespace */
    p = copy;
    while (*p != 0) {
      /* scan past whitespace then place mark */
      while (*p == ' ' || *p == '\t' || *p == '\n')
        p++;
      mark = p;
      /* scan to end of word (next ws/null) */
      while (*p != 0 && *p != ' ' && *p != '\t' && *p != '\n')
        p++;

      /* mark end of substring */
      if (*p != 0) {
        *p = 0;
        p++;
      }
      else {
        /* p points to end of string; since it hasn't been advanced, the while loop will terminate */
      }
      if (p != mark)
        cl_string_list_append(l, mark);
    }
    ok = 1;
    /* split on whitespace can't really fail */
  }
  else {
    /* check and split '|'-delimited syntax */
    if (copy[0] == '|') {
      mark = p = copy+1;
      while (*p != 0) {
        if (*p == '|') {
          *p = 0;
          cl_string_list_append(l, mark);
          mark = p = p+1;
        }
        else
          p++;
      }
      if (p == mark)           /* otherwise, there was no trailing '|' */
        ok = 1;
    }
  }

  /* (2) check set elements: must not contain '|' character */
  length = cl_string_list_size(l);
  for (i = 0; i < length; i++)
    if (strchr(cl_string_list_get(l, i), '|') != NULL)
      ok = 0;

  /* (3) abort if there was any error */
  if (!ok) {
    cl_delete_string_list(l);
    cl_free(copy);
    cl_errno = CDA_EFSETINV;
    return NULL;
  }

  /* (4) sort set elements (for unify() function) */
  cl_string_list_qsort(l);

  /* (5) combine elements into set attribute string */
  sl = 2;                       /* compute length of string */
  for (i = 0; i < length; i++)
    sl += strlen(cl_string_list_get(l, i)) + 1;

  set = cl_malloc(sl);          /* allocate string of exact size */
  p = set;
  *p++ = '|';
  for (i = 0; i < length; i++) {
    cl_strcpy(p, cl_string_list_get(l, i));
    p += strlen(cl_string_list_get(l, i));
    *p++ = '|';                 /* overwrites EOS mark inserted by strcpy() */
  }
  *p = 0;                       /* EOS */

  /* (6) free intermediate data and return the set string */
  cl_delete_string_list(l);
  cl_free(copy);
  return set;
}



/**
 * Counts the number of elements in a set attribute value.
 *
 * This function counts the number of elements in a set attribute value
 * (using '|'-delimited standard syntax);
 *
 * @return  -1 on error (in particular, if set is malformed)
 */
int
cl_set_size(char *s)
{
  int count = 0;

  cl_errno = CDA_OK;

  if (*s++ != '|') {
    cl_errno = CDA_EFSETINV;
    return -1;
  }

  while (*s)
    if (*s++ == '|')
      count++;

  if (s[-1] != '|') {
    cl_errno = CDA_EFSETINV;
    return -1;
  }

  return count;
}




/**
 * Computes the intersection of two set attribute values.
 *
 * Compute intersection of two set attribute values (in standard syntax, i.e. sorted and '|'-delimited);
 * memory for the result string must be allocated by the caller.
 *
 * @return         Boolean. 0 on error, 1 otherwise
*/

int
cl_set_intersection(char *result, const char *s1, const char *s2)
{
  static char f1[CL_DYN_STRING_SIZE], f2[CL_DYN_STRING_SIZE];   /* static feature buffers (hold current feature) */
  char *p;
  int comparison;

  cl_errno = CDA_OK;

  if ((*s1++ != '|') || (*s2++ != '|')) {
    cl_errno = CDA_EFSETINV;
    return 0;
  }
  if (strlen(s1) >= CL_DYN_STRING_SIZE || strlen(s2) >= CL_DYN_STRING_SIZE) {
    cl_errno = CDA_EBUFFER;
    return 0;
  }

  *result++ = '|';              /* Initialise result */

  while (*s1 && *s2) {
    /* while a feature is active, *s_i points to the '|' separator at its end;
       when the feature is used up, *s_i is advanced and we read the next feature */
    if (*s1 != '|') {
      for (p = f1; *s1 != '|'; s1++) {
        if (!*s1) {
          cl_errno = CDA_EFSETINV;
          return 0;     /* unexpected end of string */
        }
        *p++ = *s1;
        /* should check for buffer overflow here! */
      }
      *p = 0;                   /* terminate feature string */
    }
    if (*s2 != '|') {
      for (p = f2; *s2 != '|'; s2++) {
        if (!*s2) {
          cl_errno = CDA_EFSETINV;
          return 0;     /* unexpected end of string */
        }
        *p++ = *s2;
        /* should check for buffer overflow here! */
      }
      *p = 0;                   /* terminate feature string */
    }

    /* now compare the two active features (uses cl_strcmp to ensure standard behaviour) */
    comparison = cl_strcmp(f1,f2);
    if (comparison == 0) {
      /* common feature -> copy to result vector */
      for (p = f1; *p; p++)
        *result++ = *p;
      *result++ = '|';
      /* both features are used up now */
      s1++; s2++;
    }
    else if (comparison < 0)
      /* advance s1 */
      s1++;
    else
      /* advance s2 */
      s2++;
  } /* ends: while (*s1 && *s2) */

  /* computation complete: terminate result string */
  *result = 0;
  return 1;
}


