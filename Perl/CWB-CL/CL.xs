#include "EXTERN.h"
#include "perl.h"
#include "XSUB.h"

/* #include "ppport.h" */

#include <cwb/cl.h>
#include <stdio.h>

/*
  ********* C segment (preamble) **********
*/ 

typedef Attribute * PosAttrib;  /* allows XS to check that attribute pointer belongs to approriate class */
typedef Attribute * StrucAttrib;
typedef Attribute * AlignAttrib;

int last_cl_error = CDA_OK; /* keep track of last error (cl_errno variable) in vectorised functions */
#define CWB_CL_INVALID_ARG 1 /* internal error codes used by the CWB::CL module */

int strict_mode = 0; /* in strict mode, every CL or argument error will cause the interface to croak() */

const char*
cwb_cl_error_message(int error_code) {
  if (error_code == CWB_CL_INVALID_ARG) { 
    return "CWB::CL: invalid argument encountered";
  }
  else {
    return cl_error_string(error_code);
  }
}

void
croak_on_error(int error_code) {
  croak("%s (aborted)", cwb_cl_error_message(error_code));
}

static int
not_here(s)
char *s;
{
  croak("%s not implemented on this architecture", s);
  return -1;
}

static double
constant(name)
char *name;
{
  errno = 0;
  switch (*name) {
  case 'A':
    if (strEQ(name, "ATTAT_FLOAT"))
      return ATTAT_FLOAT;
    if (strEQ(name, "ATTAT_INT"))
      return ATTAT_INT;
    if (strEQ(name, "ATTAT_NONE"))
      return ATTAT_NONE;
    if (strEQ(name, "ATTAT_PAREF"))
      return ATTAT_PAREF;
    if (strEQ(name, "ATTAT_POS"))
      return ATTAT_POS;
    if (strEQ(name, "ATTAT_STRING"))
      return ATTAT_STRING;
    if (strEQ(name, "ATTAT_VAR"))
      return ATTAT_VAR;
    if (strEQ(name, "ATT_ALIGN"))
      return ATT_ALIGN;
    if (strEQ(name, "ATT_ALL"))
      return ATT_ALL;
    if (strEQ(name, "ATT_DYN"))
      return ATT_DYN;
    if (strEQ(name, "ATT_NONE"))
      return ATT_NONE;
    if (strEQ(name, "ATT_POS"))
      return ATT_POS;
    if (strEQ(name, "ATT_REAL"))
      return ATT_REAL;
    if (strEQ(name, "ATT_STRUC"))
      return ATT_STRUC;
    break;
  case 'C':
    if (strEQ(name, "CDA_EALIGN"))
      return CDA_EALIGN;
    if (strEQ(name, "CDA_EARGS"))
      return CDA_EARGS;
    if (strEQ(name, "CDA_EATTTYPE"))
      return CDA_EATTTYPE;
    if (strEQ(name, "CDA_EBADREGEX"))
      return CDA_EBADREGEX;
    if (strEQ(name, "CDA_EBUFFER"))
      return CDA_EBUFFER;
    if (strEQ(name, "CDA_EFSETINV"))
      return CDA_EFSETINV;
    if (strEQ(name, "CDA_EIDORNG"))
      return CDA_EIDORNG;
    if (strEQ(name, "CDA_EIDXORNG"))
      return CDA_EIDXORNG;
    if (strEQ(name, "CDA_ENODATA"))
      return CDA_ENODATA;
    if (strEQ(name, "CDA_ENOMEM"))
      return CDA_ENOMEM;
    if (strEQ(name, "CDA_ENOSTRING"))
      return CDA_ENOSTRING;
    if (strEQ(name, "CDA_ENULLATT"))
      return CDA_ENULLATT;
    if (strEQ(name, "CDA_ENYI"))
      return CDA_ENYI;
    if (strEQ(name, "CDA_EOTHER"))
      return CDA_EOTHER;
    if (strEQ(name, "CDA_EPATTERN"))
      return CDA_EPATTERN;
    if (strEQ(name, "CDA_EPOSORNG"))
      return CDA_EPOSORNG;
    if (strEQ(name, "CDA_EREMOTE"))
      return CDA_EREMOTE;
    if (strEQ(name, "CDA_ESTRUC"))
      return CDA_ESTRUC;
    if (strEQ(name, "CDA_EINTERNAL"))
      return CDA_EINTERNAL;
    if (strEQ(name, "CDA_OK"))
      return CDA_OK;
    if (strEQ(name, "CL_STRING_CANONICAL_STRDUP"))
      return CL_STRING_CANONICAL_STRDUP;
    break;
  case 'I':
    if (strEQ(name, "IGNORE_CASE")) /* regexp flags */
      return IGNORE_CASE;
    if (strEQ(name, "IGNORE_DIAC")) 

      return IGNORE_DIAC;
    if (strEQ(name, "IGNORE_REGEX"))
      return IGNORE_REGEX;
    break;
  case 'R':
    if (strEQ(name, "REQUIRE_NFC"))
      return REQUIRE_NFC;
    break;
  case 'S':
    if (strEQ(name, "STRUC_INSIDE")) /* s-attribute region boundaries */
      return STRUC_INSIDE;
    if (strEQ(name, "STRUC_LBOUND")) 
      return STRUC_LBOUND;
    if (strEQ(name, "STRUC_RBOUND")) 
      return STRUC_RBOUND;
    break;
  }

  errno = EINVAL; /* name matches none of the known constants */
  return 0;
}

/*
  ********* XS segment (preamble) **********
*/ 

MODULE = CWB::CL        PACKAGE = CWB::CL       

PROTOTYPES: ENABLE

const char *
cwb_cl_error_message(error_code)
    int error_code

const char *
error_message() 
  PREINIT:
    int error_code;
  CODE:
    /* return string with last CL error encountered in last method call ("" if last call was successful) */
    error_code = (last_cl_error != CDA_OK) ? last_cl_error : cl_errno; /* after simple function invocation, use CL library error status */
    if (error_code == CDA_OK) {
      RETVAL = "";
    }
    else {
      RETVAL = cwb_cl_error_message(error_code); /* returns pointer to string constant */
    }
  OUTPUT:
    RETVAL

void
set_strict_mode(on_off)
    int on_off
  CODE:
    strict_mode = on_off;

int
get_strict_mode()
  CODE:
    RETVAL = strict_mode;
  OUTPUT:
    RETVAL

double
constant(name)
    char *  name

Corpus *
cl_new_corpus(registry_dir, registry_name)
    char *  registry_dir
    char *  registry_name
  INIT:
    last_cl_error = CDA_OK;

const char *
cl_corpus_charset_name(corpus)
    Corpus *    corpus
  CODE:
    RETVAL = cl_charset_name(cl_corpus_charset(corpus));
  OUTPUT:
    RETVAL

int
cl_delete_corpus(corpus)
    Corpus *    corpus

char *
cl_standard_registry()
  INIT:
    last_cl_error = CDA_OK;

void
cl_set_debug_level(level)
    int   level
  INIT:
    last_cl_error = CDA_OK;

void
cl_set_optimize(state)
    int   state
  INIT:
    last_cl_error = CDA_OK;

void
cl_set_memory_limit(megabytes)
    int   megabytes
  INIT:
    last_cl_error = CDA_OK;

char *
cl_make_set(s, split="")
    char *  s
    char *  split
  PREINIT:
    char *set;
    int split_mode;
  PPCODE:
    last_cl_error = CDA_OK;
    if (split == NULL || (split[0] != '\0' && split[0] != 's'))
      croak("Usage:  $feature_set = CWB::CL::make_set($string [, 'split' | 's']);");
    split_mode = (split[0] == 's');
    set = cl_make_set(s, split_mode);
    if (set != NULL) {
      XPUSHs(sv_2mortal(newSVpv(set, 0)));  /* create Perl string (let Perl compute length) */
      free(set);  /* <set> was allocated by cl_make_set, so free it again */
    }   
    else {
      last_cl_error = cl_errno;
      if (strict_mode)
        croak_on_error(last_cl_error);
      XSRETURN_UNDEF;  /* else return undefined value */
    }

char *
cl_set_intersection(s1, s2)
    char *  s1
    char *  s2
  PREINIT:
    static char result[CL_DYN_STRING_SIZE];  /* static buffer for results string */
    int ok;
  PPCODE:
    last_cl_error = CDA_OK;
    ok = cl_set_intersection(result, s1, s2);
    if (ok) {
      XPUSHs(sv_2mortal(newSVpv(result, 0)));  /* create Perl string (let Perl compute length) */
    }
    else {
      last_cl_error = cl_errno;
      if (strict_mode)
        croak_on_error(last_cl_error);
      XSRETURN_UNDEF;  /* return undefined value */
    }

int
cl_set_size(s)
    char *  s
  PREINIT:
    int size;
  CODE:
    last_cl_error = CDA_OK;
    size = cl_set_size(s);
    if (size >= 0) {
      RETVAL = size;
    }
    else {
      last_cl_error = cl_errno;
      if (strict_mode)
        croak_on_error(last_cl_error);
      XSRETURN_UNDEF;  /* return undefined value */
    }
  OUTPUT:
    RETVAL

void
cl_normalize(corpus, flags, ...)
    Corpus*   corpus
    int       flags
  PREINIT:
    int i, id, size;
// NB 2017-07-02: commented out bits were amended for the new calling convention for cl_string_canonical().
// They can be deleted once we're sure it's working correctly.
    //char *s_orig, *s_norm;
    char *s_norm;
    SV *s_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 2;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        s_arg = ST(i+2);
        if (!SvOK(s_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef ID arguments return undef */
        }
        else {
          // s_orig = (char *) SvPV_nolen(s_arg);
          // s_norm = cl_malloc(2 * strlen(s_orig) + 1); /* need larger buffer if case-folding lengthens string */
          // strcpy(s_norm, s_orig);
          // cl_string_canonical(s_norm, cl_corpus_charset(corpus), flags);
          s_norm = cl_string_canonical((char *) SvPV_nolen(s_arg), cl_corpus_charset(corpus), flags, CL_STRING_CANONICAL_STRDUP);
          PUSHs(sv_2mortal(newSVpv(s_norm, 0)));
          cl_free(s_norm);
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

char *
cl_list_attributes(corpus, type)
    Corpus* corpus 
    int     type
  PREINIT:
    cl_string_list names;
    int i, size;
  PPCODE:
    last_cl_error = CDA_OK;
    names = cl_corpus_list_attributes(corpus, type);
    size = cl_string_list_size(names);
    /* never sets an error condition */
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        PUSHs(sv_2mortal(newSVpv(cl_string_list_get(names, i), 0)));
      }
    }
    cl_free_string_list(names);

Attribute *
cl_new_attribute(corpus, attribute_name, type)
    Corpus *    corpus
    char *  attribute_name
    int   type
  INIT:
    last_cl_error = CDA_OK;

int
cl_delete_attribute(attribute)
    Attribute *   attribute
  INIT:
    last_cl_error = CDA_OK;

int
cl_max_cpos(attribute)
    PosAttrib   attribute
  INIT:
    last_cl_error = CDA_OK;

int
cl_max_id(attribute)
    PosAttrib   attribute
  INIT:
    last_cl_error = CDA_OK;

void
cl_id2str(attribute, ...)
    PosAttrib   attribute
  PREINIT:
    int i, id, size;
    char *s;
    SV *id_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        id_arg = ST(i+1);
        if (!SvOK(id_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef ID arguments return undef */
        }
        else {
          id = (int) SvIV(id_arg);
          s = cl_id2str(attribute, id);
          if (s) {
            PUSHs(sv_2mortal(newSVpv(s, 0)));
          }
          else {
            last_cl_error = cl_errno;
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_str2id(attribute, ...)
    PosAttrib   attribute
  PREINIT:
    int i, id, size;
    char *s;
    SV *s_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        s_arg = ST(i+1);
        if (!SvOK(s_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef ID arguments return undef */
        }
        else {
          s = (char *) SvPV_nolen(s_arg);
          id = cl_str2id(attribute, s);
          if (id >= 0) {
            PUSHs(sv_2mortal(newSViv(id)));
          }
          else {
            if (cl_errno != CDA_ENOSTRING)
              last_cl_error = cl_errno; /* CDA_ENOSTRING indicates that string is not in lexicon (no error) */
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_id2strlen(attribute, ...)
    PosAttrib   attribute
  PREINIT:
    int i, id, len, size;
    SV *id_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        id_arg = ST(i+1);
        if (!SvOK(id_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef ID arguments return undef */
        }
        else {
          id = (int) SvIV(id_arg);
          len = cl_id2strlen(attribute, id);
          if (len >= 0) {
            PUSHs(sv_2mortal(newSViv(len)));
          }
          else {
            last_cl_error = cl_errno;
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_id2freq(attribute, ...)
    PosAttrib   attribute
  PREINIT:
    int i, id, f, size;
    SV *id_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        id_arg = ST(i+1);
        if (!SvOK(id_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef ID arguments return undef */
        }
        else {
          id = (int) SvIV(id_arg);
          f = cl_id2freq(attribute, id);
          if (f >= 0) {
            PUSHs(sv_2mortal(newSViv(f)));
          }
          else {
            last_cl_error = cl_errno;
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_cpos2id(attribute, ...)
    PosAttrib   attribute
  PREINIT:
    int i, cpos, id, size;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+1);
        if (!SvOK(cpos_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef cpos arguments return undef */
        }
        else {
          cpos = (int) SvIV(cpos_arg);
          id = cl_cpos2id(attribute, cpos);
          if (id >= 0) {
            PUSHs(sv_2mortal(newSViv(id)));
          }
          else {
            last_cl_error = cl_errno;
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_cpos2str(attribute, ...)
    PosAttrib   attribute
  PREINIT:
    int i, cpos, size;
    char *s;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+1);
        if (!SvOK(cpos_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef cpos arguments return undef */
        }
        else {
          cpos = (int) SvIV(cpos_arg);
          s = cl_cpos2str(attribute, cpos);
          if (s) {
            PUSHs(sv_2mortal(newSVpv(s, 0)));
          }
          else {
            last_cl_error = cl_errno;
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_regex2id(attribute, pattern, canonicalize)
    PosAttrib   attribute
    char *  pattern
    int   canonicalize
  PREINIT:
    int number_of_matches = 0;
    int *idlist;
    int i;
  PPCODE:
    last_cl_error = CDA_OK;
    idlist = cl_regex2id(attribute, pattern, canonicalize, &number_of_matches);
    if (idlist != NULL) {
      EXTEND(sp, number_of_matches); /* push IDs on result stack */
      for (i=0; i < number_of_matches; i++)
        PUSHs(sv_2mortal(newSViv(idlist[i])));
      free(idlist);
    }
    else {
      if (strict_mode && cl_errno != CDA_OK)
        croak_on_error(cl_errno);
    }
    /* else return empty list */ 

int
cl_idlist2freq(attribute, ...)
    PosAttrib   attribute
  PREINIT:
    int i, size, errors;
    int *list;
  CODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      Newx(list, size, int); /* convert argument list to list of integer IDs */
      if (!list)
        croak("Can't allocate temporary array for %d integers", size);
      errors = 0;
      for (i = 0; i < size; i++) {
        if (SvOK(ST(i+1)))
          list[i] = (int) SvIV(ST(i+1));
        else
          errors++;
      }
      if (errors) {
        RETVAL = -1;
        last_cl_error = CWB_CL_INVALID_ARG;
      }
      else {
        RETVAL = cl_idlist2freq(attribute, list, size);
        if (RETVAL < 0)
          last_cl_error = cl_errno;
      }
      Safefree(list);
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
      if (RETVAL < 0)
        XSRETURN_UNDEF;
    }
    else {
      RETVAL = 0;
    }
  OUTPUT:
    RETVAL

void
cl_idlist2cpos(attribute, ...)
    PosAttrib   attribute
  PREINIT:
    int i, id, idlist_size, size, errors;
    int *idlist, *list;
  PPCODE:
    last_cl_error = CDA_OK;
    idlist_size = items - 1;
    if (idlist_size > 0) {
      Newx(idlist, idlist_size, int); /* convert argument list to list of integer IDs */
      if (!idlist)
        croak("Can't allocate temporary array of size %d in idlist2cpos() method\n", idlist_size);
      for (i = 0; i < idlist_size; i++) {
        if (SvOK(ST(i+1)))
          idlist[i] = (int) SvIV(ST(i+1));
        else {
          last_cl_error = CWB_CL_INVALID_ARG;
          break;
        }
      }
      if (last_cl_error != CDA_OK) {
        Safefree(idlist);
        if (strict_mode)
          croak_on_error(last_cl_error);
        /* else return empty list to indicate error condition (valid IDs would never return empty list) */
      }
      else {
        if (idlist_size > 1)
          list = cl_idlist2cpos(attribute, idlist, idlist_size, /* sorted */ 1, &size);
        else
          list = cl_id2cpos(attribute, idlist[0], &size); /* should be more efficient for single ID */
        Safefree(idlist);
        if (list) {
          EXTEND(sp, size);
          for (i=0; i < size; i++)
            PUSHs(sv_2mortal(newSViv(list[i])));
          free(list);
        }
        else {
          last_cl_error = cl_errno;
          if (strict_mode)
            croak_on_error(last_cl_error);
          /* else return empty list to indicate error condition */
        }
      }
    }
    /* else return empty list */


int
cl_struc_values(attribute)
    Attribute *   attribute
  INIT:
    last_cl_error = CDA_OK;

int
cl_max_struc(attribute)
    Attribute *   attribute
  INIT:
    last_cl_error = CDA_OK;

void
cl_cpos2struc(attribute, ...)
    StrucAttrib   attribute
  PREINIT:
    int i, cpos, struc, size;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+1);
        if (!SvOK(cpos_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef cpos arguments return undef */
        }
        else {
          cpos = (int) SvIV(cpos_arg);
          struc = cl_cpos2struc(attribute, cpos);
          if (struc >= 0) {
            PUSHs(sv_2mortal(newSViv(struc)));
          }
          else {
            if (cl_errno != CDA_ESTRUC)
              last_cl_error = cl_errno; /* CDA_ESTRUC indicates that cpos is not in attribute region (no error) */
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_cpos2struc2str(attribute, ...)
    StrucAttrib   attribute
  PREINIT:
    int i, cpos, size;
    char *s;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+1);
        if (!SvOK(cpos_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef cpos arguments return undef */
        }
        else {
          cpos = (int) SvIV(cpos_arg);
          s = cl_cpos2struc2str(attribute, cpos);
          if (s) {
            PUSHs(sv_2mortal(newSVpv(s, 0)));
          }
          else {
            if (cl_errno != CDA_ESTRUC)
              last_cl_error = cl_errno; /* CDA_ESTRUC indicates that cpos is not in attribute region (no error) */
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_struc2str(attribute, ...)
    StrucAttrib   attribute
  PREINIT:
    int i, struc, size;
    char *s;
    SV *struc_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        struc_arg = ST(i+1);
        if (!SvOK(struc_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef struc arguments return undef */
        }
        else {
          struc = (int) SvIV(struc_arg);
          s = cl_struc2str(attribute, struc);
          if (s) {
            PUSHs(sv_2mortal(newSVpv(s, 0)));
          }
          else {
            last_cl_error = cl_errno;
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_struc2cpos(attribute, ...)
    StrucAttrib   attribute
  PREINIT:
    int i, struc, size, start, end;
    int *arguments;
    SV *struc_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      /* Return values on stack overwrite function arguments, starting from ST(0).  This works in most
       * vectorised functions since we push one return value for each argument, i.e. we store the result
       * for ST(i+1) in ST(i).  Because cl_struc2cpos() returns two values for each argument, we have
       * to store all arguments in a locally allocated array first.
       */
      Newx(arguments, size, int); /* allocate temporary array to hold arguments (converted to C ints) */
      if (!arguments)
        croak("Can't allocate temporary array for %d integers", size);
      for (i = 0; i < size; i++) {
        struc_arg = ST(i+1);
        if (SvOK(struc_arg)) {
          arguments[i] = (int) SvIV(struc_arg);
        }
        else {
          last_cl_error = CWB_CL_INVALID_ARG;
          arguments[i] = -4242; /* so negative arguments will usually generate CDA_EIDXORNG */
        }
      }
      EXTEND(sp, 2 * size); /* now make sure stack has enough space for all return values */
      for (i = 0; i < size; i++) {
        struc = arguments[i];
        if (struc == -4242) {
          PUSHs(sv_newmortal()); /* invalid arguments return (undef, undef) pairs */
          PUSHs(sv_newmortal());
        }
        else {
          if (cl_struc2cpos(attribute, struc, &start, &end)) {
            PUSHs(sv_2mortal(newSViv(start))); /* push (start, end) pair on return stack */
            PUSHs(sv_2mortal(newSViv(end))); 
          }
          else {
            last_cl_error = cl_errno;
            PUSHs(sv_newmortal()); /* all errors are turned into (undef, undef) pairs */
            PUSHs(sv_newmortal());
          }
        }
      }
      Safefree(arguments);
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */
      
void
cl_cpos2struc2cpos(attribute, ...)
    StrucAttrib   attribute
  PREINIT:
    int i, cpos, size, start, end;
    int *arguments;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      /* see above why we need to store arguments in a temporary array of C ints */
      Newx(arguments, size, int); /* allocate temporary array to hold arguments (converted to C ints) */
      if (!arguments)
        croak("Can't allocate temporary array for %d integers", size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+1);
        if (SvOK(cpos_arg)) {
          arguments[i] = (int) SvIV(cpos_arg);
        }
        else {
          last_cl_error = CWB_CL_INVALID_ARG;
          arguments[i] = -4242; /* so negative arguments will usually generate CDA_EIDXORNG */
        }
      }
      EXTEND(sp, 2 * size); /* now make sure stack has enough space for all return values */
      for (i = 0; i < size; i++) {
        cpos = arguments[i];
        if (cpos == -4242) {
          PUSHs(sv_newmortal()); /* invalid arguments return (undef, undef) pairs */
          PUSHs(sv_newmortal());
        }
        else {
          if (cl_cpos2struc2cpos(attribute, cpos, &start, &end)) {
            PUSHs(sv_2mortal(newSViv(start))); /* push (start, end) pair on return stack */
            PUSHs(sv_2mortal(newSViv(end))); 
          }
          else {
            if (cl_errno != CDA_ESTRUC)
              last_cl_error = cl_errno; /* CDA_ESTRUC indicates that cpos is not in attribute region (no error) */
            PUSHs(sv_newmortal()); /* all errors are turned into (undef, undef) pairs */
            PUSHs(sv_newmortal());
          }
        }
      }
      Safefree(arguments);
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_cpos2boundary(attribute, ...)
    StrucAttrib   attribute
  PREINIT:
    int i, cpos, flags, size;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+1);
        if (!SvOK(cpos_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef cpos arguments return undef */
        }
        else {
          cpos = (int) SvIV(cpos_arg);
          flags = cl_cpos2boundary(attribute, cpos);
          if (flags >= 0) {
            PUSHs(sv_2mortal(newSViv(flags)));
          }
          else {
            last_cl_error = cl_errno; /* CDA_ESTRUC cannot occur here (simply returns flags=0) */
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_cpos2is_boundary(attribute, test_flags, ...)
    StrucAttrib   attribute
    int           test_flags
  PREINIT:
    int i, cpos, flags, is_boundary, size;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 2;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+2);
        if (!SvOK(cpos_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef cpos arguments return undef */
        }
        else {
          cpos = (int) SvIV(cpos_arg);
          flags = cl_cpos2boundary(attribute, cpos);
          if (flags >= 0) {
            if (test_flags) {
              is_boundary = ((flags & test_flags) == test_flags) ? 1 : 0;
            }
            else {
              is_boundary = (flags == 0) ? 1 : 0; /* special case: test whether token is outside region */
            }
            PUSHs(sv_2mortal(newSViv(is_boundary)));
          }
          else {
            last_cl_error = cl_errno; /* CDA_ESTRUC cannot occur here (simply returns flags=0) */
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

int
cl_has_extended_alignment(attribute)
    AlignAttrib   attribute

int
cl_max_alg(attribute)
    AlignAttrib   attribute

void
cl_cpos2alg(attribute, ...)
    AlignAttrib   attribute
  PREINIT:
    int i, cpos, alg, size;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      EXTEND(sp, size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+1);
        if (!SvOK(cpos_arg)) {
          last_cl_error = CWB_CL_INVALID_ARG;
          PUSHs(sv_newmortal()); /* undef cpos arguments return undef */
        }
        else {
          cpos = (int) SvIV(cpos_arg);
          alg = cl_cpos2alg(attribute, cpos);
          if (alg >= 0) {
            PUSHs(sv_2mortal(newSViv(alg)));
          }
          else {
            if (cl_errno != CDA_EALIGN)
              last_cl_error = cl_errno; /* CDA_EALIGN indicates that cpos is not in alignment bead (no error) */
            PUSHs(sv_newmortal()); /* all errors are turned into undefs */
          }
        }
      }
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_alg2cpos(attribute, ...)
    AlignAttrib   attribute
  PREINIT:
    int i, alg, size;
    int source_start, source_end, target_start, target_end;
    int *arguments;
    SV *alg_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      /* Return values on stack overwrite function arguments, starting from ST(0).  This works in most
       * vectorised functions since we push one return value for each argument, i.e. we store the result
       * for ST(i+1) in ST(i).  Because cl_alg2cpos() returns four values for each argument, we have
       * to store all results in a locally allocated array first.
       */
      Newx(arguments, size, int); /* allocate temporary array to hold arguments (converted to C ints) */
      if (!arguments)
        croak("Can't allocate temporary array for %d integers", size);
      for (i = 0; i < size; i++) {
        alg_arg = ST(i+1);
        if (SvOK(alg_arg)) {
          arguments[i] = (int) SvIV(alg_arg);
        }
        else {
          last_cl_error = CWB_CL_INVALID_ARG;
          arguments[i] = -4242; /* so negative arguments will usually generate CDA_EIDXORNG */
        }
      }
      EXTEND(sp, 4 * size); /* now make sure stack has enough space for all return values */
      for (i = 0; i < size; i++) {
        alg = arguments[i];
        if (alg == -4242) {
          PUSHs(sv_newmortal()); /* invalid arguments return (undef, undef, undef, undef) beads */
          PUSHs(sv_newmortal());
          PUSHs(sv_newmortal());
          PUSHs(sv_newmortal());
        }
        else {
          if (cl_alg2cpos(attribute, alg, &source_start, &source_end, &target_start, &target_end)) {
            PUSHs(sv_2mortal(newSViv(source_start))); /* push alignment bead on return stack */
            PUSHs(sv_2mortal(newSViv(source_end)));
            PUSHs(sv_2mortal(newSViv(target_start)));
            PUSHs(sv_2mortal(newSViv(target_end)));
          }   
          else {
            last_cl_error = cl_errno;
            PUSHs(sv_newmortal()); /* all errors are turned into (undef, undef, undef, undef) beads */
            PUSHs(sv_newmortal());
            PUSHs(sv_newmortal());
            PUSHs(sv_newmortal());
          }
        }
      }
      Safefree(arguments);
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */

void
cl_cpos2alg2cpos(attribute, ...)
    AlignAttrib   attribute
  PREINIT:
    int i, ok, cpos, alg, size;
    int source_start, source_end, target_start, target_end;
    int *arguments;
    SV *cpos_arg;
  PPCODE:
    last_cl_error = CDA_OK;
    size = items - 1;
    if (size > 0) {
      /* Return values on stack overwrite function arguments, starting from ST(0).  This works in most
       * vectorised functions since we push one return value for each argument, i.e. we store the result
       * for ST(i+1) in ST(i).  Because cl_cpos2alg2cpos() returns four values for each argument, we have
       * to store all results in a locally allocated array first.
       */
      Newx(arguments, size, int); /* allocate temporary array to hold arguments (converted to C ints) */
      if (!arguments)
        croak("Can't allocate temporary array for %d integers", size);
      for (i = 0; i < size; i++) {
        cpos_arg = ST(i+1);
        if (SvOK(cpos_arg)) {
          arguments[i] = (int) SvIV(cpos_arg);
        }
        else {
          last_cl_error = CWB_CL_INVALID_ARG;
          arguments[i] = -4242; /* so negative arguments will usually trigger standard CL errors */
        }
      }
      EXTEND(sp, 4 * size); /* now make sure stack has enough space for all return values */
      for (i = 0; i < size; i++) {
        cpos = arguments[i];
        ok = 0;
        if (cpos != -4242) {
          alg = cl_cpos2alg(attribute, cpos);
          if ((alg >= 0) && 
              cl_alg2cpos(attribute, alg, &source_start, &source_end, &target_start, &target_end)) {
            PUSHs(sv_2mortal(newSViv(source_start))); /* push alignment bead on return stack */
            PUSHs(sv_2mortal(newSViv(source_end)));
            PUSHs(sv_2mortal(newSViv(target_start)));
            PUSHs(sv_2mortal(newSViv(target_end)));
            ok = 1;
          }   
          else {
            if (cl_errno != CDA_EALIGN)
              last_cl_error = cl_errno; /* CDA_EALIGN is not an error condition (no alignment found) */
          }
        }
        if (!ok) {
          PUSHs(sv_newmortal()); /* push (undef, undef, undef, undef) bead if no valid alignment was found */
          PUSHs(sv_newmortal());
          PUSHs(sv_newmortal());
          PUSHs(sv_newmortal());
        }
      }
      Safefree(arguments);
      if (strict_mode && last_cl_error != CDA_OK)
        croak_on_error(last_cl_error);
    }
    /* else return empty list */
