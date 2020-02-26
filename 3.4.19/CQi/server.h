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
#include "../cqp/corpmanag.h"   /* for CorpusList */

typedef unsigned char cqi_byte;

/* global errno variable; the error code is a CQi error message */
/* utility functions such as cqi_lookup_attribute() set cqi_errno, so
   the calling function can just do cqi_command(cqi_errno) when a utility
   function call fails */
extern int cqi_errno;

/* CQi general error handling:
   cqi_general_error(s) sends a CQI_ERROR_GENERAL_ERROR command and sets
   copies <s> into <cqi_error_string>. The CQI_CTRL_LAST_GENERAL_ERROR()
   function will then return <cqi_error_string> as a STRING */
extern char cqi_error_string[];
void cqi_general_error(char *errstring);

/* accept_connection returns the SOCKSTREAM connection file descriptor,
   or a negative value if an error occurred
   port  ...  bind to this port; uses CQI_PORT if port==0 */
int accept_connection(int port);

/* CQi network primitives (no auto-flush) */
int cqi_flush(void);
int cqi_send_byte(int n, int nosnoop);
int cqi_send_word(int n);
int cqi_send_int(int n);
int cqi_send_string(const char *str);	/* NULL pointer sends "" */
int cqi_send_byte_list(cqi_byte *list, int length);
int cqi_send_int_list(int *list, int length);
int cqi_send_string_list(char **list, int length);

/* send a CQi command (auto-flush) [exit on error] */
void cqi_command(int command);        /* simple command (no args) */
void cqi_data_byte(int n);
void cqi_data_bool(int n);
void cqi_data_int(int n);
void cqi_data_string(const char *str);
void cqi_data_byte_list(cqi_byte *list, int length);
void cqi_data_bool_list(cqi_byte *list, int length);
void cqi_data_int_list(int *list, int length);
void cqi_data_string_list(char **list, int length);
void cqi_data_int_int(int n1, int n2);
void cqi_data_int_int_int_int(int n1, int n2, int n3, int n4);

/* receive data from client */
int cqi_recv_bytes(cqi_byte *buf, int n); /* receive exactly n bytes */
int cqi_recv_byte(void);             /* receive 1 byte from client (returns EOF on error*/

/* advanced functions which read chunks of data [exit on error] */
int cqi_read_byte(void);
int cqi_read_bool(void);
int cqi_read_word(void);
int cqi_read_int(void);
char *cqi_read_string(void);	/* allocates string */
int cqi_read_byte_list(cqi_byte **list); /* allocates list, returns no. of elements */
int cqi_read_bool_list(cqi_byte **list); /* .. */
int cqi_read_int_list(int **list);	 /* .. */
int cqi_read_string_list(char ***list);  /* allocates list & individual strings */
int cqi_read_command(void);	/* reads a word, skipping CQI_PAD bytes if necessary */

/* naming conventions */
int check_corpus_name(char *name);       /* make sure identifiers conform to naming conventions */
int check_attribute_name(char *name);    /* return 0 & set cqi_errno if <name> does not conform */
int check_subcorpus_name(char *name);

/* splitting/combining full attribute & subcorpus specifiers */
/* these functions return 0 & set cqi_errno if format is invalid */
int split_attribute_spec(char *spec, char **corpus_name, char **attribute_name);
/* if <spec> denotes a root corpus, <subcorpus_name> is set to NULL */
int split_subcorpus_spec(char *spec, char **corpus_name, char **subcorpus_name);
char *combine_subcorpus_spec(char *corpus_name, char *subcorpus_name);


/* attribute hashing */
void make_attribute_hash(int size);      /* used internally */
void free_attribute_hash(void);
Attribute *cqi_lookup_attribute(char *name, int type);
/* don't use cqi_drop_attribute() ... the attribute will be completely invisible
   to the CL after that */
int cqi_drop_attribute(char *name);      /* returns True/False */

/* CQP internal function wrappers (set cqi_errno on error) */
CorpusList *cqi_find_corpus(char *name); /* either root corpus or subcorpus */
int cqi_activate_corpus(char *name);

