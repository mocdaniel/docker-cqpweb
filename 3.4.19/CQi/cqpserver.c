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

#include "server.h"
#include "auth.h"
#include "cqi.h"

#include <unistd.h>
#include <stdlib.h>
#include <stdio.h>
#include <math.h>

#include "../cl/cl.h"

#include "../cqp/cqp.h"
#include "../cqp/options.h"
#include "../cqp/corpmanag.h"
#include "../cqp/groups.h"


/** String containing the username sent by the currently-connect CQi client */
char *user = "";
/** String containing the password sent by the currently-connect CQi client */
char *passwd = "";


/**
 *  Prints the CQi server welcome and copyright message.
 */
void
cqiserver_welcome(void)
{
  printf("** CQPserver v" CWB_VERSION "\n");
  printf("** implementing version %d.%d of the CQi\n", CQI_MAJOR_VERSION, CQI_MINOR_VERSION);
  printf("\n");
}


/*
 *
 *  Some common error messages
 *
 */

/**
 * Shuts down the server with an "unknown CQi command" error condition.
 *
 * @param cmd  The integer representing the unknown command received from the client.
 */
void
cqiserver_unknown_command_error(int cmd)
{
  fprintf(stderr, "CQPserver: unknown CQi command 0x%04X.\n", cmd);
  exit(1);
}

/**
 * Shuts down the server with an "CQi command not allowed here" error condition.
 *
 * @param cmd  The integer representing the wrong command received from the client.
 */
void
cqiserver_wrong_command_error(int cmd)
{
  fprintf(stderr, "CQPserver: command 0x%04X not allowed in this context.\n", cmd);
  exit(1);
}

/**
 * Shuts down the server with an "internal error" condition.
 *
 * Both parameters will be printed as part of the shutdown error message.
 *
 * @param function  String: should be name of the calling function, that is,
 *                  the point where the error was raised.
 * @param reason    String containing any other explanatory details about the error.
 */
void
cqiserver_internal_error(char *function, char *reason)
{
  fprintf(stderr, "CQPserver: internal error in %s()\n", function);
  fprintf(stderr, "CQPserver: ''%s''\n", reason);
  exit(1);
}


/*
 *
 *  CL and CQP error messages
 *
 */

/**
 * Sends the current CL error value to the client.
 *
 * This function takes the current contents of of the CL library's global
 * cl_errno error value and sends it to the client.
 *
 * It takes the CL error consant and translates it into the corresponding
 * CQI_CL_ERROR_* constant.
 *
 * NB: This function shuts down the server with an error condition if cl_errno
 * does not actually contain an error condition.
 *
 * @see cl_errno
 */
void
send_cl_error(void)
{
  int cmd;

  switch (cl_errno) {
  case CDA_EATTTYPE:
    cmd = CQI_CL_ERROR_WRONG_ATTRIBUTE_TYPE;
    break;
  case CDA_EIDORNG:
  case CDA_EIDXORNG:
  case CDA_EPOSORNG:
    cmd = CQI_CL_ERROR_OUT_OF_RANGE;
    break;
  case CDA_EPATTERN:
  case CDA_EBADREGEX:
    cmd = CQI_CL_ERROR_REGEX;
    break;
  case CDA_ENODATA:
    cmd = CQI_CL_ERROR_CORPUS_ACCESS;
    break;
  case CDA_ENOMEM:
    cmd = CQI_CL_ERROR_OUT_OF_MEMORY;
    break;
  case CDA_EOTHER:
  case CDA_ENYI:
    cmd = CQI_CL_ERROR_INTERNAL;
    break;
  case CDA_OK:
    fprintf(stderr, "CQPserver: send_cl_error() called with cderrno == CDA_OK\n");
    exit(1);
  default:
    fprintf(stderr, "CQPserver: send_cl_error() unknown value in cderrno\n");
    exit(1);
  }
  if (server_debug)
    fprintf(stderr, "CQi: CL error, returning 0x%04X\n", cmd);
  cqi_command(cmd);
  return;
}


/*
 *
 *  CQi commands  (called from interpreter loop)
 *
 */

void
do_cqi_corpus_list_corpora(void)
{
  CorpusList *cl;
  int n = 0;

  if (server_debug)
    fprintf(stderr, "CQi: CQI_CORPUS_LIST_CORPORA()\n");
  /* ugly, but it's easiest ... first count corpora, then return names one by one */
  for (cl = FirstCorpusFromList(); cl != NULL; cl = NextCorpusFromList(cl)) {
    if (cl->type == SYSTEM)
      n++;
  }
  cqi_send_word(CQI_DATA_STRING_LIST);
  cqi_send_int(n);
  for (cl = FirstCorpusFromList(); cl != NULL; cl = NextCorpusFromList(cl)) {
    if (cl->type == SYSTEM)
      cqi_send_string(cl->name);
  }
  cqi_flush();
}

void
do_cqi_corpus_charset(void)
{
  char *c;
  CorpusList *cl;
  c = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CORPUS_CHARSET('%s')\n", c);

  cl = findcorpus(c, SYSTEM, 0);
  if (cl == NULL || !access_corpus(cl))
    cqi_command(CQI_CQP_ERROR_NO_SUCH_CORPUS);
  else
    cqi_data_string(cl_charset_name(cl->corpus->charset));
  cl_free(c);
}

void
do_cqi_corpus_properties(void)
{
  char *c;

  c = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CORPUS_PROPERTIES('%s')\n", c);
  /* this is a dummy until we've implemented the registry extensions */
  cqi_data_string_list(NULL, 0);
  cl_free(c);
}

/* this part sends attributes of a certain type as a STRING[] to the client */
void
send_cqi_corpus_attributes(Corpus *c, int type)
{
  Attribute *a;
  int len;

  cqi_send_word(CQI_DATA_STRING_LIST);
  len = 0;
  for (a = first_corpus_attribute(c); a != NULL; a = next_corpus_attribute())
    if (a->type == type)
      len++;
  cqi_send_int(len);

  for (a = first_corpus_attribute(c); a != NULL; a = next_corpus_attribute())
    if (a->type == type)
      cqi_send_string(a->any.name);
  cqi_flush();
}

void
do_cqi_corpus_attributes(int type)
{
  char *c, *typename;
  CorpusList *cl;

  c = cqi_read_string();
  if (server_debug) {
    switch (type) {
    case ATT_POS:
      typename = "POSITIONAL";
      break;
    case ATT_STRUC:
      typename = "STRUCTURAL";
      break;
    case ATT_ALIGN:
      typename = "ALIGNMENT";
      break;
    default:
      cqi_general_error("INTERNAL ERROR: do_cqi_corpus_attributes(): unknown attribute type");
      return;
    }
    fprintf(stderr, "CQi: CQI_CORPUS_%s_ATTRIBUTES('%s')\n", typename, c);
  }

  cl = findcorpus(c, SYSTEM, 0);
  if (cl == NULL || !access_corpus(cl))
    cqi_command(CQI_CQP_ERROR_NO_SUCH_CORPUS);
  else
    send_cqi_corpus_attributes(cl->corpus, type);

  cl_free(c);
}

void
do_cqi_corpus_full_name(void)
{
  char *c;
  CorpusList *cl;

  c = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CORPUS_FULL_NAME('%s')\n", c);

  cl = findcorpus(c, SYSTEM, 0);
  if (cl == NULL || !access_corpus(cl))
    cqi_command(CQI_CQP_ERROR_NO_SUCH_CORPUS);
  else
    cqi_data_string(cl->corpus->name);

  cl_free(c);
}

void
do_cqi_corpus_structural_attribute_has_values(void) {
  char *a;
  Attribute *attribute;

  a = cqi_read_string();        /* need to try all possible attribute types */
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CORPUS_STRUCTURAL_ATTRIBUTE_HAS_VALUES('%s')\n", a);

  attribute = cqi_lookup_attribute(a, ATT_STRUC);
  if (attribute != NULL)
    cqi_data_bool(cl_struc_values(attribute));
  else
    cqi_command(cqi_errno);
  cl_free(a);
}

void
do_cqi_cl_attribute_size(void)
{
  char *a;
  Attribute *attribute;
  int size;

  a = cqi_read_string();        /* need to try all possible attribute types */
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CL_ATTRIBUTE_SIZE('%s')\n", a);
  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute != NULL) {
    size = cl_max_cpos(attribute);
    if (size < 0)
      send_cl_error();
    else
      cqi_data_int(size);
  }
  else {
    attribute = cqi_lookup_attribute(a, ATT_STRUC);
    if (attribute != NULL) {
      size = cl_max_struc(attribute);
      if (size < 0)
        /*      send_cl_error(); */
        /* current version of CL considers 0 regions a data access error condition, but we want to allow that */
        cqi_data_int(0);
      else
        cqi_data_int(size);
    }
    else {
      attribute = cqi_lookup_attribute(a, ATT_ALIGN);
      if (attribute != NULL) {
        size = cl_max_alg(attribute);
        if (size < 0)
          send_cl_error();
        else
          cqi_data_int(size);
      }
      else
        cqi_command(cqi_errno); /* return errno from the last lookup */
    }
  }
  cl_free(a);
}

void
do_cqi_cl_lexicon_size(void)
{
  char *a;
  Attribute *attribute;
  int size;

  a = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CL_LEXICON_SIZE('%s')\n", a);
  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute != NULL) {
    size = cl_max_id(attribute);
    if (size < 0) {
      send_cl_error();
    }
    else {
      cqi_data_int(size);
    }
  }
  else {
    cqi_command(cqi_errno);     /* cqi_errno set by lookup() */
  }
  cl_free(a);
}

void
do_cqi_cl_drop_attribute(void)
{
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CL_DROP_ATTRIBUTE()  --  not implemented\n");
 cqi_general_error("CQI_CL_DROP_ATTRIBUTE not implemented.");
}

/* one might wish to add extensive error checking to all the CL functions,
   but that will need a LOT of code! */
void
do_cqi_cl_str2id(void)
{
  char **strlist;
  int len, i, id;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_string_list(&strlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_STR2ID('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "'%s' ", strlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_INT_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_INT_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      id = cl_str2id(attribute, strlist[i]);
      if (id < 0)
        id = -1;                /* -1 => string not found in lexicon */
      cqi_send_int(id);
    }
  }
  cqi_flush();
  cl_free(strlist);              /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_id2str(void)
{
  int *idlist;
  int len, i;
  char *a, *str;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&idlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_ID2STR('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", idlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_STRING_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_STRING_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      str = cl_id2str(attribute, idlist[i]);
      cqi_send_string(str);     /* sends "" if str == NULL (ID out of range) */
    }
  }
  cqi_flush();
  cl_free(idlist);               /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_id2freq(void)
{
  int *idlist;
  int len, i, f;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&idlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_ID2FREQ('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", idlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_INT_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_INT_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      f = cl_id2freq(attribute, idlist[i]);
      if (f < 0)
        f = 0;                  /* return 0 if ID is out of range */
      cqi_send_int(f);
    }
  }
  cqi_flush();
  cl_free(idlist);               /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_cpos2str(void)
{
  int *cposlist;
  int len, i;
  char *a, *str;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&cposlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_CPOS2STR('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", cposlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_STRING_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_STRING_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      str = cl_cpos2str(attribute, cposlist[i]);
      cqi_send_string(str);     /* sends "" if str == NULL (cpos out of range) */
    }
  }
  cqi_flush();
  cl_free(cposlist);             /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_cpos2id(void)
{
  int *cposlist;
  int len, i, id;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&cposlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_CPOS2ID('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", cposlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_INT_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_INT_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      id = cl_cpos2id(attribute, cposlist[i]);
      if (id < 0)
        id = -1;                        /* return -1 if cpos is out of range */
      cqi_send_int(id);
    }
  }
  cqi_flush();
  cl_free(cposlist);                     /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_cpos2struc(void)
{
  int *cposlist;
  int len, i, struc;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&cposlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_CPOS2STRUC('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", cposlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_STRUC);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_INT_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_INT_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      struc = cl_cpos2struc(attribute, cposlist[i]);
      if (struc < 0)
        struc = -1;                     /* return -1 if cpos is out of range */
      cqi_send_int(struc);
    }
  }
  cqi_flush();
  cl_free(cposlist);                    /* don't forget to free allocated memory */
  cl_free(a);
}

/* cqi_cl_cpos2lbound() and cqi_cl_cpos2rbound() are currently temporary functions
   for the Euralex2000 tutorial; they will probably become part of the CQi specification,
   and should be improved with a caching model to avoid the frequent cpos2struc lookup;
   perhaps make them CL functions with an intelligent caching algorithm? */
void
do_cqi_cl_cpos2lbound(void)
{
  int *cposlist;
  int len, i, struc, lb, rb;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&cposlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_CPOS2LBOUND('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", cposlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_STRUC);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_INT_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_INT_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      struc = cl_cpos2struc(attribute, cposlist[i]);
      if (struc < 0) {
        cqi_send_int(-1);                       /* return -1 if cpos is not in region */
      }
      else {
        if (cl_struc2cpos(attribute, struc, &lb, &rb))
          cqi_send_int(lb);
        else
          cqi_send_int(-1);     /* cannot return error within list, so send -1 */
      }
    }
  }
  cqi_flush();
  cl_free(cposlist);                    /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_cpos2rbound(void)
{
  int *cposlist;
  int len, i, struc, lb, rb;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&cposlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_CPOS2RBOUND('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", cposlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_STRUC);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_INT_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_INT_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      struc = cl_cpos2struc(attribute, cposlist[i]);
      if (struc < 0)
        cqi_send_int(-1);                       /* return -1 if cpos is not in region */
      else {
        if (cl_struc2cpos(attribute, struc, &lb, &rb))
          cqi_send_int(rb);
        else
          cqi_send_int(-1);     /* cannot return error within list, so send -1 */
      }
    }
  }
  cqi_flush();
  cl_free(cposlist);                    /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_cpos2alg(void)
{
  int *cposlist;
  int len, i, alg;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&cposlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_CPOS2ALG('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", cposlist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_ALIGN);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_INT_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_INT_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      alg = cl_cpos2alg(attribute, cposlist[i]);
      if (alg < 0)
        alg = -1;                       /* return -1 if cpos is out of range */
      cqi_send_int(alg);
    }
  }
  cqi_flush();
  cl_free(cposlist);                     /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_struc2str(void)
{
  int *struclist;
  int len, i;
  char *a, *str;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&struclist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_STRUC2STR('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", struclist[i]);
    fprintf(stderr, "])\n");
  }

  attribute = cqi_lookup_attribute(a, ATT_STRUC);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    /* we assemble the CQI_DATA_STRING_LIST() return command by hand,
       so we don't have to allocate a temporary list */
    cqi_send_word(CQI_DATA_STRING_LIST);
    cqi_send_int(len);          /* list size */
    for (i=0; i<len; i++) {
      str = cl_struc2str(attribute, struclist[i]);
      cqi_send_string(str);     /* sends "" if str == NULL (wrong alignment number) */
    }
  }
  cqi_flush();
  cl_free(struclist);                    /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_id2cpos(void)
{
  int *cposlist;
  int len, id;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  id = cqi_read_int();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CL_ID2CPOS('%s', %d)\n", a, id);

  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    cposlist = cl_id2cpos(attribute, id, &len);
    if (cposlist == NULL)
      send_cl_error();
    else {
      cqi_data_int_list(cposlist, len);
      cl_free(cposlist);
    }
  }
  cl_free(a);                      /* don't forget to free allocated space */
}

void
do_cqi_cl_idlist2cpos(void)
{
  int *idlist, *cposlist;
  int i, len, cposlen;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  len = cqi_read_int_list(&idlist);
  if (server_debug) {
    fprintf(stderr, "CQi: CQI_CL_IDLIST2CPOS('%s', [", a);
    for (i=0; i<len; i++)
      fprintf(stderr, "%d ", idlist[i]);
    fprintf(stderr, "])\n");
  }
  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute == NULL) {
    cqi_command(cqi_errno);
  }
  else {
    cposlist = cl_idlist2cpos(attribute, idlist, len, 1, &cposlen);
    if (cposlist == NULL)
      send_cl_error();
    else {
      cqi_data_int_list(cposlist, cposlen);
      cl_free(cposlist);
    }
  }
  cqi_flush();
  cl_free(idlist);               /* don't forget to free allocated memory */
  cl_free(a);
}

void
do_cqi_cl_regex2id(void)
{
  int *idlist;
  int len;
  char *a, *regex;
  Attribute *attribute;

  a = cqi_read_string();
  regex = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CL_REGEX2ID('%s', '%s')\n", a, regex);

  attribute = cqi_lookup_attribute(a, ATT_POS);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    idlist = cl_regex2id(attribute, regex, 0, &len);
    if (idlist == NULL) {
      if (cl_errno != CDA_OK)
        send_cl_error();
      else
        cqi_data_int_list(NULL, 0); /* no matches -> zero size list */
    }
    else {
      cqi_data_int_list(idlist, len);
      cl_free(idlist);
    }
  }
  cl_free(regex);
  cl_free(a);                      /* don't forget to free allocated space */
}

void
do_cqi_cl_struc2cpos(void)
{
  int struc, start, end;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  struc = cqi_read_int();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CL_STRUC2CPOS('%s', %d)\n", a, struc);

  attribute = cqi_lookup_attribute(a, ATT_STRUC);
  if (attribute == NULL)
    cqi_command(cqi_errno);
  else {
    if (cl_struc2cpos(attribute, struc, &start, &end))
      cqi_data_int_int(start, end);
    else
      send_cl_error();
  }
  cl_free(a);                      /* don't forget to free allocated space */
}

void
do_cqi_cl_alg2cpos(void)
{
  int alg, s1, s2, t1, t2;
  char *a;
  Attribute *attribute;

  a = cqi_read_string();
  alg = cqi_read_int();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CL_ALG2CPOS('%s', %d)\n", a, alg);
  attribute = cqi_lookup_attribute(a, ATT_ALIGN);
  if (attribute == NULL) {
    cqi_command(cqi_errno);
  }
  else {
    if (cl_alg2cpos(attribute, alg, &s1, &s2, &t1, &t2))
      cqi_data_int_int_int_int(s1, s2, t1, t2);
    else
      send_cl_error();
  }
  cl_free(a);                      /* don't forget to free allocated space */
}

void
do_cqi_cqp_list_subcorpora(void)
{
  char *corpus;
  CorpusList *cl, *mother;
  int n = 0;

  corpus = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CQP_LIST_SUBCORPORA(%s)\n", corpus);
  mother = cqi_find_corpus(corpus);
  if (!check_corpus_name(corpus) || mother == NULL)
    cqi_command(cqi_errno);
  else {

    /* ugly, but it's easiest ... first count corpora, then return names one by one */
    for (cl = FirstCorpusFromList(); cl != NULL; cl = NextCorpusFromList(cl)) {
      if (cl->type == SUB && cl->corpus == mother->corpus)
        n++;
    }
    cqi_send_word(CQI_DATA_STRING_LIST);
    cqi_send_int(n);
    for (cl = FirstCorpusFromList(); cl != NULL; cl = NextCorpusFromList(cl)) {
      if (cl->type == SUB && cl->corpus == mother->corpus)
        cqi_send_string(cl->name);
    }
    cqi_flush();

  }
  cl_free(corpus);
}

/**
 * Tests whether or nto the final non-blank character in a string is a semicolon.
 *
 * CQP queries must be terminated with a single semicolon;
 * multiple semicolons will produce an error to occur -- so we
 * have to check and add a semicolon if necessary.
 *
 * @return  Boolean: true iff the final non-blank character is a semicolon.
 */
int
query_has_semicolon(char *query)
{
  char *p;

  if (query == NULL || *query == 0)
    return 0;
  p = query + strlen(query);
  while (--p > query)           /* stop at first non-blank char or at first string character */
    if (!(*p == ' ' || *p == '\t'))
      break;
  return (*p == ';') ? 1 : 0;
}

void
do_cqi_cqp_query(void)
{
  char *child, *mother, *query, *c, *sc;

  mother = cqi_read_string();
  child = cqi_read_string();
  query = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CQP_QUERY('%s', '%s', '%s')\n", mother, child, query);
  if (!split_subcorpus_spec(mother, &c, &sc)) {
    cqi_command(cqi_errno);
  }
  else {
    char *cqp_query;
    int len = strlen(child) + strlen(query) + 10;

    cqp_query = (char *) cl_malloc(len);
    if (!check_subcorpus_name(child) || !cqi_activate_corpus(mother)) {
      cqi_command(cqi_errno);
    }
    else {
      query_lock = floor(1e9 * cl_runif()) + 1; /* activate query lock mode with random key */

      printf("CQPSERVER: query_lock = %d\n", query_lock);
      if (query_has_semicolon(query))
        sprintf(cqp_query, "%s = %s", child, query);
      else
        sprintf(cqp_query, "%s = %s;", child, query);
      if (!cqp_parse_string(cqp_query))
        cqi_command(CQI_CQP_ERROR_GENERAL); /* should be changed to detailed error messages */
      else {
        char *full_child;
        CorpusList *childcl;

        full_child = combine_subcorpus_spec(c, child); /* c is the 'physical' part of the mother corpus */
        childcl = cqi_find_corpus(full_child);
        if ((childcl) == NULL)
          cqi_command(CQI_CQP_ERROR_GENERAL);
        else {
          if (server_log) {
            printf("'%s' ran the following query on %s\n", user, mother);
            printf("\t%s\n", cqp_query);
            printf("and got %d matches.\n", childcl->size);
          }
          cqi_command(CQI_STATUS_OK);

        }
        cl_free(full_child);
      }

      query_lock = 0;           /* deactivate query lock mode */
    }
    cl_free(cqp_query);
  }
  cl_free(c);
  cl_free(sc);
}

void
do_cqi_cqp_subcorpus_size(void)
{
  char *subcorpus;
  CorpusList *cl;

  subcorpus = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CQP_SUBCORPUS_SIZE('%s')\n", subcorpus);
  cl = cqi_find_corpus(subcorpus);
  if (cl == NULL)
    cqi_command(cqi_errno);
  else
    cqi_data_int(cl->size);

  cl_free(subcorpus);
}

/**
 * Returns string representations of CQI_CONST_FIELD_ values.
 *
 * Utility function, used for debugging output & to check valid fields in subroutines below.
 *
 * TODO as a utiltiy, shouldn't this be in the cqi library (server.c?)
 */
char *
cqi_field_name(cqi_byte field) {
  switch (field) {
  case CQI_CONST_FIELD_MATCH:
    return "MATCH";
  case CQI_CONST_FIELD_MATCHEND:
    return "MATCHEND";
  case CQI_CONST_FIELD_TARGET:
    return "TARGET";
  case CQI_CONST_FIELD_KEYWORD:
    return "KEYWORD";
  default:
    return NULL;                /* invalid field */
  }
}

void
do_cqi_cqp_subcorpus_has_field(void)
{
  char *subcorpus;
  CorpusList *cl;
  cqi_byte field;
  char *fieldname;
  int field_ok = 1;             /* field valid? */

  subcorpus = cqi_read_string();
  field = cqi_read_byte();

  fieldname = cqi_field_name(field);
  if (fieldname == NULL) {
    fieldname = "<invalid field>";
    field_ok = 0;
  }
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CQP_SUBCORPUS_HAS_FIELD('%s', %s)\n", subcorpus, fieldname);

  cl = cqi_find_corpus(subcorpus);
  if (cl == NULL)
    cqi_command(cqi_errno);
  else if (!field_ok)
    cqi_command(CQI_CQP_ERROR_INVALID_FIELD);
  else {
    switch (field) {
    case CQI_CONST_FIELD_MATCH:
      cqi_data_bool(CQI_CONST_YES);
      break;
    case CQI_CONST_FIELD_MATCHEND:
      cqi_data_bool(CQI_CONST_YES);
      break;
    case CQI_CONST_FIELD_TARGET:
      if (cl->targets == NULL)
        cqi_data_bool(CQI_CONST_NO);
      else
        cqi_data_bool(CQI_CONST_YES);
      break;
    case CQI_CONST_FIELD_KEYWORD:
      if (cl->keywords == NULL)
        cqi_data_bool(CQI_CONST_NO);
      else
        cqi_data_bool(CQI_CONST_YES);
      break;
    default:
      cqiserver_internal_error("do_cqi_cqp_subcorpus_has_field", "Can't identify requested field.");
    }
    cqi_flush();
  }

  cl_free(subcorpus);
}

/**
 * Sends n instances of integer -1 to the client.
 *
 * Utility function for do_cqi_cqp_dump_subcorpus().
 *
 * This is the error condition of the CQI_CQP_DUMP_SUBCORPUS command:
 * it returns a list of (-1) values if requested field is not set.
 *
 * It is assumed that the length of the lsit has already been sent.
 *
 * @param n  Length of list to send.
 */
void
do_cqi_send_minus_one_list(int n)
{
  while (n--)
    cqi_send_int(-1);
}

void
do_cqi_cqp_dump_subcorpus(void)
{
  char *subcorpus;
  CorpusList *cl;
  cqi_byte field;
  int i, first, last, size;
  char *fieldname;
  int field_ok = 1;             /* field valid? */

  subcorpus = cqi_read_string();
  field = cqi_read_byte();
  first = cqi_read_int();
  last = cqi_read_int();

  fieldname = cqi_field_name(field);
  if (fieldname == NULL) {
    fieldname = "<invalid field>";
    field_ok = 0;
  }
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CQP_DUMP_SUBCORPUS('%s', %s, %d, %d)\n", subcorpus, fieldname, first, last);

  cl = cqi_find_corpus(subcorpus);
  if (cl == NULL)
    cqi_command(cqi_errno);
  else if (!field_ok)
    cqi_command(CQI_CQP_ERROR_INVALID_FIELD);
  else if ((last < first) || (first < 0) || (last >= cl->size))
    cqi_command(CQI_CQP_ERROR_OUT_OF_RANGE);
  else {
      cqi_send_word(CQI_DATA_INT_LIST); /* assemble by hand, so we don't have to allocate a temporary list */
      size = last - first + 1;
      cqi_send_int(size);
      switch (field) {
      case CQI_CONST_FIELD_MATCH:
        for (i=first; i<=last; i++)
          cqi_send_int(cl->range[i].start);
        break;
      case CQI_CONST_FIELD_MATCHEND:
        for (i=first; i<=last; i++)
          cqi_send_int(cl->range[i].end);
        break;
      case CQI_CONST_FIELD_TARGET:
        if (cl->targets == NULL)
          do_cqi_send_minus_one_list(size);
        else
          for (i=first; i<=last; i++)
            cqi_send_int(cl->targets[i]);
        break;
      case CQI_CONST_FIELD_KEYWORD:
        if (cl->keywords == NULL)
          do_cqi_send_minus_one_list(size);
        else
          for (i=first; i<=last; i++)
            cqi_send_int(cl->keywords[i]);
        break;
      default:
        cqiserver_internal_error("do_cqi_cqp_dump_subcorpus", "No handler for requested field.");
      }
      cqi_flush();
  }

  cl_free(subcorpus);
}

void
do_cqi_cqp_drop_subcorpus(void)
{
  char *subcorpus;
  CorpusList *cl;
  char *c, *sc;

  subcorpus = cqi_read_string();
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CQP_DROP_SUBCORPUS('%s')\n", subcorpus);

  /* make sure it is a subcorpus, not a root corpus */
  if (!split_subcorpus_spec(subcorpus, &c, &sc))
    cqi_command(cqi_errno);
  else if (sc == NULL) {
    cl_free(c);
    cqi_command(CQI_ERROR_SYNTAX_ERROR);
  }
  else {
    cl_free(c);
    cl_free(sc);
    cl = cqi_find_corpus(subcorpus);
    if (cl == NULL)
      cqi_command(cqi_errno);
    else {
      dropcorpus(cl);
      cqi_command(CQI_STATUS_OK);
    }
  }

  cl_free(subcorpus);
}

/* temporary functions for CQI_CQP_FDIST_1() and CQI_CQP_FDIST_2() */
void
do_cqi_cqp_fdist_1(void)
{
  char *subcorpus;
  CorpusList *cl;
  int cutoff;
  cqi_byte field;
  char *att;
  Group *table;
  int i, size;
  char *fieldname;
  FieldType fieldtype = NoField;
  int field_ok = 1;             /* field valid? */

  subcorpus = cqi_read_string();
  cutoff = cqi_read_int();
  field = cqi_read_byte();
  att = cqi_read_string();

  /* not exactly the fastest way to do it ... */
  fieldname = cqi_field_name(field);
  if (fieldname == NULL) {
    fieldname = "<invalid field>";
    field_ok = 0;
  }
  else
    fieldtype = field_name_to_type(fieldname);

  if (server_debug)
    fprintf(stderr, "CQi: CQI_CQP_FDIST_1('%s', %d, %s, %s)\n", subcorpus, cutoff, fieldname, att);

  cl = cqi_find_corpus(subcorpus);
  if (cl == NULL)
    cqi_command(cqi_errno);
  else if (!field_ok)
    cqi_command(CQI_CQP_ERROR_INVALID_FIELD);
  else {
    /* compute_grouping() returns tokens with f > cutoff, but CQi specifies f >= cutoff */
    cutoff = (cutoff > 0) ? cutoff - 1 : 0;
    table = compute_grouping(cl, NoField, 0, NULL, fieldtype, 0, att, cutoff, 0);
    if (table == NULL) {
      cqi_command(CQI_CQP_ERROR_GENERAL);
    }
    else {
      size = table->nr_cells;
      cqi_send_word(CQI_DATA_INT_TABLE);        /* return table with 2 columns & <size> rows */
      cqi_send_int(size);
      cqi_send_int(2);
      for (i=0; i < size; i++) {
        cqi_send_int(table->count_cells[i].t);
        cqi_send_int(table->count_cells[i].freq);
      }
      cqi_flush();
      free_group(&table);
    }
  }

  cl_free(subcorpus);
  cl_free(att);
}


void
do_cqi_cqp_fdist_2(void)
{
  char *subcorpus;
  CorpusList *cl;
  int cutoff;
  cqi_byte field1, field2;
  char *att1, *att2;
  Group *table;
  int i, size;
  char *fieldname1, *fieldname2;
  FieldType fieldtype1 = NoField, fieldtype2 = NoField;
  int fields_ok = 1;            /* (both) fields valid? */

  subcorpus = cqi_read_string();
  cutoff = cqi_read_int();
  field1 = cqi_read_byte();
  att1 = cqi_read_string();
  field2 = cqi_read_byte();
  att2 = cqi_read_string();

  /* not exactly the fastest way to do it ... */
  fieldname1 = cqi_field_name(field1);
  if (fieldname1 == NULL) {
    fieldname1 = "<invalid field>";
    fields_ok = 0;
  }
  else {
    fieldtype1 = field_name_to_type(fieldname1);
  }
  fieldname2 = cqi_field_name(field2);
  if (fieldname2 == NULL) {
    fieldname2 = "<invalid field>";
    fields_ok = 0;
  }
  else {
    fieldtype2 = field_name_to_type(fieldname2);
  }
  if (server_debug)
    fprintf(stderr, "CQi: CQI_CQP_FDIST_2('%s', %d, %s, %s, %s, %s)\n",
            subcorpus, cutoff, fieldname1, att1, fieldname2, att2);

  cl = cqi_find_corpus(subcorpus);
  if (cl == NULL)
    cqi_command(cqi_errno);
  else if (!fields_ok)
    cqi_command(CQI_CQP_ERROR_INVALID_FIELD);
  else {
    /* compute_grouping() returns tokens with f > cutoff, but CQi specifies f >= cutoff */
    cutoff = (cutoff > 0) ? cutoff - 1 : 0;
    table = compute_grouping(cl, fieldtype1, 0, att1, fieldtype2, 0, att2, cutoff, 0);
    if (table == NULL) {
      cqi_command(CQI_CQP_ERROR_GENERAL);
    }
    else {
      size = table->nr_cells;
      cqi_send_word(CQI_DATA_INT_TABLE);        /* return table with 3 columns & <size> rows */
      cqi_send_int(size);
      cqi_send_int(3);
      for (i=0; i < size; i++) {
        cqi_send_int(table->count_cells[i].s);
        cqi_send_int(table->count_cells[i].t);
        cqi_send_int(table->count_cells[i].freq);
      }
      cqi_flush();
      free_group(&table);
    }
  }

  cl_free(subcorpus);
  cl_free(att1);
  cl_free(att2);
}


/**
 *
 *  The CQP server's command interpreter loop.
 *
 *  The loops starts running when this function is called, and when the
 *  exit command is reveived (CQI_CTRL_BYE)
 *  (returns on exit)
 *
 */
void
interpreter(void)
{
  int cmd;
  int cmd_group;

  while (42) {
    cmd = cqi_read_command();
    cmd_group = cmd >> 8;

    switch (cmd_group) {

      /* GROUP CQI_CTRL_* */
    case CQI_CTRL:
      switch (cmd) {
      case CQI_CTRL_CONNECT:
        cqiserver_wrong_command_error(cmd);
        break;
      case CQI_CTRL_BYE:
        if (server_debug)
          fprintf(stderr, "CQi: CQI_CTRL_BYE()\n");
        cqi_command(CQI_STATUS_BYE_OK);
        return;                 /* exit CQi command interpreter */
      case CQI_CTRL_USER_ABORT:
        if (server_debug)
          fprintf(stderr, "CQi: CQI_CTRL_ABORT signal ... ignored\n");
        break;
      case CQI_CTRL_PING:
        if (server_debug)
          fprintf(stderr, "CQi: CQI_CTRL_PING()\n");
        cqi_command(CQI_STATUS_PING_OK);
        break;
      case CQI_CTRL_LAST_GENERAL_ERROR:
        if (server_debug)
          fprintf(stderr, "CQi: CQI_CTRL_LAST_GENERAL_ERROR() => '%s'", cqi_error_string);
        cqi_data_string(cqi_error_string);
        break;
      default:
        cqiserver_unknown_command_error(cmd);
      }
      break;

      /* GROUP CQI_ASK_FEATURE_* */
    case CQI_ASK_FEATURE:
      switch (cmd) {
      case CQI_ASK_FEATURE_CQI_1_0:
        if (server_debug)
          fprintf(stderr, "CQi: CQI_ASK_FEATURE_CQI_1_0 ... CQi v1.0 ok\n");
        cqi_data_bool(CQI_CONST_YES);
        break;
      case CQI_ASK_FEATURE_CL_2_3:
        if (server_debug)
          fprintf(stderr, "CQi: CQI_ASK_FEATURE_CL_2_3 ... CL v2.3 ok\n");
        cqi_data_bool(CQI_CONST_YES);
        break;
      case CQI_ASK_FEATURE_CQP_2_3:
        if (server_debug)
          fprintf(stderr, "CQi: CQI_ASK_FEATURE_CQP_2_3 ... CQP v2.3 ok\n");
        cqi_data_bool(CQI_CONST_YES);
        break;
      default:
        if (server_debug)
          fprintf(stderr, "CQi: CQI_ASK_FEATURE_* ... <unknown feature> not supported\n");
        cqi_data_bool(CQI_CONST_NO);
      }
      break;

      /* GROUP CQI_CORPUS_* */
    case CQI_CORPUS:
      switch (cmd) {
      case CQI_CORPUS_LIST_CORPORA:
        do_cqi_corpus_list_corpora();
        break;
      case CQI_CORPUS_CHARSET:
        do_cqi_corpus_charset();
        break;
      case CQI_CORPUS_PROPERTIES:
        do_cqi_corpus_properties();
        break;
      case CQI_CORPUS_POSITIONAL_ATTRIBUTES:
        do_cqi_corpus_attributes(ATT_POS);
        break;
      case CQI_CORPUS_STRUCTURAL_ATTRIBUTES:
        do_cqi_corpus_attributes(ATT_STRUC);
        break;
      case CQI_CORPUS_STRUCTURAL_ATTRIBUTE_HAS_VALUES:
        do_cqi_corpus_structural_attribute_has_values();
        break;
      case CQI_CORPUS_ALIGNMENT_ATTRIBUTES:
        do_cqi_corpus_attributes(ATT_ALIGN);
        break;
      case CQI_CORPUS_FULL_NAME:
        do_cqi_corpus_full_name();
        break;
      default:
        cqiserver_unknown_command_error(cmd);
      }
      break;

      /* GROUP CQI_CL_* */
    case CQI_CL:
      switch (cmd) {
      case CQI_CL_ATTRIBUTE_SIZE:
        do_cqi_cl_attribute_size();
        break;
      case CQI_CL_LEXICON_SIZE:
        do_cqi_cl_lexicon_size();
        break;
      case CQI_CL_DROP_ATTRIBUTE:
        do_cqi_cl_drop_attribute();
        break;
      case CQI_CL_STR2ID:
        do_cqi_cl_str2id();
        break;
      case CQI_CL_ID2STR:
        do_cqi_cl_id2str();
        break;
      case CQI_CL_ID2FREQ:
        do_cqi_cl_id2freq();
        break;
      case CQI_CL_CPOS2ID:
        do_cqi_cl_cpos2id();
        break;
      case CQI_CL_CPOS2STR:
        do_cqi_cl_cpos2str();
        break;
      case CQI_CL_CPOS2STRUC:
        do_cqi_cl_cpos2struc();
        break;
      case CQI_CL_CPOS2LBOUND:
        do_cqi_cl_cpos2lbound();
        break;
      case CQI_CL_CPOS2RBOUND:
        do_cqi_cl_cpos2rbound();
        break;
      case CQI_CL_CPOS2ALG:
        do_cqi_cl_cpos2alg();
        break;
      case CQI_CL_STRUC2STR:
        do_cqi_cl_struc2str();
        break;
      case CQI_CL_ID2CPOS:
        do_cqi_cl_id2cpos();
        break;
      case CQI_CL_IDLIST2CPOS:
        do_cqi_cl_idlist2cpos();
        break;
      case CQI_CL_REGEX2ID:
        do_cqi_cl_regex2id();
        break;
      case CQI_CL_STRUC2CPOS:
        do_cqi_cl_struc2cpos();
        break;
      case CQI_CL_ALG2CPOS:
        do_cqi_cl_alg2cpos();
        break;
      default:
        cqiserver_unknown_command_error(cmd);
      }
      break;

      /* GROUP CQI_CQP_* */
    case CQI_CQP:
      switch (cmd) {
      case CQI_CQP_QUERY:
        do_cqi_cqp_query();
        break;
      case CQI_CQP_LIST_SUBCORPORA:
        do_cqi_cqp_list_subcorpora();
        break;
      case CQI_CQP_SUBCORPUS_SIZE:
        do_cqi_cqp_subcorpus_size();
        break;
      case CQI_CQP_SUBCORPUS_HAS_FIELD:
        do_cqi_cqp_subcorpus_has_field();
        break;
      case CQI_CQP_DUMP_SUBCORPUS:
        do_cqi_cqp_dump_subcorpus();
        break;
      case CQI_CQP_DROP_SUBCORPUS:
        do_cqi_cqp_drop_subcorpus();
        break;
      case CQI_CQP_FDIST_1:
        do_cqi_cqp_fdist_1();
        break;
      case CQI_CQP_FDIST_2:
        do_cqi_cqp_fdist_2();
        break;
      default:
        cqiserver_unknown_command_error(cmd);
      }
      break;

    default:
      cqiserver_unknown_command_error(cmd);

    } /* end outer switch */

  } /* end while 42 */

}



/**
 * Main function for the cqpserver app.
 */
int
main(int argc, char *argv[])
{
  int cmd;

  which_app = cqpserver;

  /* TODO: shouldn't these come AFTER initialize_cqp(), as that function may overwrite these values with defaults?
   * or maybe I've missed some subtlety here....*/
  silent = 1;
  paging = autoshow = auto_save = 0;

  if (!initialize_cqp(argc, argv)) {
    fprintf(stderr, "CQPserver: ERROR Couldn't initialise CQP engine.\n");
    exit(1);
  }
  while (optind < argc) {
    /* remaining command-line arguments are <user>:<password> specifications */
    char *sep = strchr(argv[optind], ':');
    if (sep != NULL) {
      if (sep == argv[optind]) {
        fprintf(stderr, "CQPserver: Invalid account specification '%s' (username must not be empty)\n", argv[optind]);
        exit(1);
      }
      else {
        *sep = '\0';
        add_user_to_list(argv[optind], sep + 1);
      }
    }
    else {
      fprintf(stderr, "CQPserver: Invalid account specification '%s' (password missing)\n", argv[optind]);
      exit(1);
    }
    optind++;
  }

  cqiserver_welcome();

  if (localhost) {
    add_host_to_list("127.0.0.1"); /* in -L mode, connections from localhost are automatically accepted  */
  }

  if (0 < accept_connection(server_port)) {
    if (server_log)
      printf("CQPserver: Connected. Waiting for CONNECT request.\n");
  }
  else {
    fprintf(stderr, "CQPserver: ERROR Connection failed.\n");
    exit(1);
  }

  /* establish CQi connection: wait for CONNECT request */
  cmd = cqi_read_command();
  if (cmd != CQI_CTRL_CONNECT) {
    if (server_log)
      printf("CQPserver: Connection refused.\n");
    cqiserver_wrong_command_error(cmd);
  }
  user = cqi_read_string();
  passwd = cqi_read_string();
  if (server_log)
    printf("CQPserver: CONNECT  user = '%s'  passwd = '%s'  pid = %d\n", user, passwd, (int)getpid());

  /* check password here (always required !!) */
  if (!authenticate_user(user, passwd)) {
    printf("CQPserver: Wrong username or password. Connection refused.\n"); /* TODO shouldn't this be to stderr as it is not conditional on server_log? */
    cqi_command(CQI_ERROR_CONNECT_REFUSED);
  }
  else {
    cqi_command(CQI_STATUS_CONNECT_OK);

    /* re-randomize for query lock key generation */
    cl_randomize();

    /* check which corpora the user is granted access to */
    {
      CorpusList *cl = FirstCorpusFromList();
      while (cl != NULL) {
        if (!check_grant(user, cl->name))
          dropcorpus(cl);
        cl = NextCorpusFromList(cl);
      }
    }

    /* start command interpreter loop */
    interpreter();

    if (server_log)
      printf("CQPserver: User '%s' has logged off.\n", user);
  }

  /* connection terminated; clean up and exit */
  printf("CQPserver: Exit. (pid = %d)\n", (int)getpid());

  /* TODO should we check cqp_error_status as in the main cqp app? */
  return 0;
}

