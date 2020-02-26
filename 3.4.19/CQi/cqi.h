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

/*   CQi v0.1 (Corpus Query Interface)                                   */

/**
 * @file
 *
 * This file contains definitions of messages that can be passed via the
 * Corpus Query Interface.
 *
 * They are broken down into (a) CQi responses that can be sent by the server
 * (b) CQi requests that the clienyt can send to the server (c) some other
 * bits and pieces.
 *
 * This file should be #included into any program that wishes to use CQi
 * but note that as there are no functions defined for a CQi client program,
 * there are no accompanying source, object or library files.
 */


/* default port for CQi services                                         */
#define CQI_PORT 4877


/*  ***                                                                  */
/*  ***   padding                                                        */
/*  ***                                                                  */

#define CQI_PAD 0x00



/*  ***                                                                  */
/*  ***   CQi responses                                                  */
/*  ***                                                                  */

#define CQI_STATUS 0x01

#define CQI_STATUS_OK 0x0101
#define CQI_STATUS_CONNECT_OK 0x0102
#define CQI_STATUS_BYE_OK 0x0103
#define CQI_STATUS_PING_OK 0x0104


#define CQI_ERROR 0x02

#define CQI_ERROR_GENERAL_ERROR 0x0201
#define CQI_ERROR_CONNECT_REFUSED 0x0202
#define CQI_ERROR_USER_ABORT 0x0203
#define CQI_ERROR_SYNTAX_ERROR 0x0204
/* includes corpus/attribute/subcorpus specifier syntax                  */

#define CQI_DATA 0x03

#define CQI_DATA_BYTE 0x0301
#define CQI_DATA_BOOL 0x0302
#define CQI_DATA_INT 0x0303
#define CQI_DATA_STRING 0x0304
#define CQI_DATA_BYTE_LIST 0x0305
#define CQI_DATA_BOOL_LIST 0x0306
#define CQI_DATA_INT_LIST 0x0307
#define CQI_DATA_STRING_LIST 0x0308
#define CQI_DATA_INT_INT 0x0309
#define CQI_DATA_INT_INT_INT_INT 0x030A
#define CQI_DATA_INT_TABLE 0x030B

#define CQI_CL_ERROR 0x04

#define CQI_CL_ERROR_NO_SUCH_ATTRIBUTE 0x0401
/* returned if CQi server couldn't open attribute                        */

#define CQI_CL_ERROR_WRONG_ATTRIBUTE_TYPE 0x0402
/* CDA_EATTTYPE                                                          */

#define CQI_CL_ERROR_OUT_OF_RANGE 0x0403
/* CDA_EIDORNG, CDA_EIDXORNG, CDA_EPOSORNG                               */

#define CQI_CL_ERROR_REGEX 0x0404
/* CDA_EPATTERN (not used), CDA_EBADREGEX                                */

#define CQI_CL_ERROR_CORPUS_ACCESS 0x0405
/* CDA_ENODATA                                                           */

#define CQI_CL_ERROR_OUT_OF_MEMORY 0x0406
/* CDA_ENOMEM                                                            */
/* this means the CQi server has run out of memory;                      */
/* try discarding some other corpora and/or subcorpora                   */

#define CQI_CL_ERROR_INTERNAL 0x0407
/* CDA_EOTHER, CDA_ENYI                                                  */
/* this is the classical 'please contact technical support' error        */


#define CQI_CQP_ERROR 0x05
/* CQP error messages yet to be defined                                  */

#define CQI_CQP_ERROR_GENERAL 0x0501
#define CQI_CQP_ERROR_NO_SUCH_CORPUS 0x0502
#define CQI_CQP_ERROR_INVALID_FIELD 0x0503
#define CQI_CQP_ERROR_OUT_OF_RANGE 0x0504
/* various cases where a number is out of range                          */




/*  ***                                                                  */
/*  ***   CQi commands                                                   */
/*  ***                                                                  */

#define CQI_CTRL 0x11

#define CQI_CTRL_CONNECT 0x1101
/* INPUT: (STRING username, STRING password)                             */
/* OUTPUT: CQI_STATUS_CONNECT_OK, CQI_ERROR_CONNECT_REFUSED              */

#define CQI_CTRL_BYE 0x1102
/* INPUT: ()                                                             */
/* OUTPUT: CQI_STATUS_BYE_OK                                             */

#define CQI_CTRL_USER_ABORT 0x1103
/* INPUT: ()                                                             */
/* OUTPUT:                                                               */

#define CQI_CTRL_PING 0x1104
/* INPUT: ()                                                             */
/* OUTPUT: CQI_CTRL_PING_OK                                              */

#define CQI_CTRL_LAST_GENERAL_ERROR 0x1105
/* INPUT: ()                                                             */
/* OUTPUT: CQI_DATA_STRING                                               */
/* full-text error message for the last general error reported by        */
/* the CQi server                                                        */



#define CQI_ASK_FEATURE 0x12

#define CQI_ASK_FEATURE_CQI_1_0 0x1201
/* INPUT: ()                                                             */
/* OUTPUT: CQI_DATA_BOOL                                                 */

#define CQI_ASK_FEATURE_CL_2_3 0x1202
/* INPUT: ()                                                             */
/* OUTPUT: CQI_DATA_BOOL                                                 */

#define CQI_ASK_FEATURE_CQP_2_3 0x1203
/* INPUT: ()                                                             */
/* OUTPUT: CQI_DATA_BOOL                                                 */



#define CQI_CORPUS 0x13

#define CQI_CORPUS_LIST_CORPORA 0x1301
/* INPUT:  ()                                                            */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */

#define CQI_CORPUS_CHARSET 0x1303
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_DATA_STRING                                               */

#define CQI_CORPUS_PROPERTIES 0x1304
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */

#define CQI_CORPUS_POSITIONAL_ATTRIBUTES 0x1305
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */

#define CQI_CORPUS_STRUCTURAL_ATTRIBUTES 0x1306
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */

#define CQI_CORPUS_STRUCTURAL_ATTRIBUTE_HAS_VALUES 0x1307
/* INPUT:  (STRING attribute)                                            */
/* OUTPUT: CQI_DATA_BOOL                                                 */

#define CQI_CORPUS_ALIGNMENT_ATTRIBUTES 0x1308
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */

#define CQI_CORPUS_FULL_NAME 0x1309
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_DATA_STRING                                               */
/* the full name of <corpus> as specified in its registry entry          */

#define CQI_CORPUS_INFO 0x130A
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */
/* returns the contents of the .info file of <corpus> as a list of lines */

#define CQI_CORPUS_DROP_CORPUS 0x130B
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_STATUS_OK                                                 */
/* try to unload a corpus and all its attributes from memory             */



#define CQI_CL 0x14
/* low-level corpus access (CL functions)                                */

#define CQI_CL_ATTRIBUTE_SIZE 0x1401
/* INPUT:  (STRING attribute)                                            */
/* OUTPUT: CQI_DATA_INT                                                  */
/* returns the size of <attribute>:                                      */
/*     number of tokens        (positional)                              */
/*     number of regions       (structural)                              */
/*     number of alignments    (alignment)                               */

#define CQI_CL_LEXICON_SIZE 0x1402
/* INPUT:  (STRING attribute)                                            */
/* OUTPUT: CQI_DATA_INT                                                  */
/* returns the number of entries in the lexicon of a positional attribute; */
/* valid lexicon IDs range from 0 .. (lexicon_size - 1)                  */

#define CQI_CL_DROP_ATTRIBUTE 0x1403
/* INPUT:  (STRING attribute)                                            */
/* OUTPUT: CQI_STATUS_OK                                                 */
/* unload attribute from memory                                          */

#define CQI_CL_STR2ID 0x1404
/* INPUT:  (STRING attribute, STRING_LIST strings)                       */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns -1 for every string in <strings> that is not found in the lexicon */

#define CQI_CL_ID2STR 0x1405
/* INPUT:  (STRING attribute, INT_LIST id)                               */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */
/* returns "" for every ID in <id> that is out of range                  */

#define CQI_CL_ID2FREQ 0x1406
/* INPUT:  (STRING attribute, INT_LIST id)                               */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns 0 for every ID in <id> that is out of range                   */

#define CQI_CL_CPOS2ID 0x1407
/* INPUT:  (STRING attribute, INT_LIST cpos)                             */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns -1 for every corpus position in <cpos> that is out of range   */

#define CQI_CL_CPOS2STR 0x1408
/* INPUT:  (STRING attribute, INT_LIST cpos)                             */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */
/* returns "" for every corpus position in <cpos> that is out of range   */

#define CQI_CL_CPOS2STRUC 0x1409
/* INPUT:  (STRING attribute, INT_LIST cpos)                             */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns -1 for every corpus position not inside a structure region    */

/* temporary addition for the Euralex2000 tutorial, but should probably be included in CQi specs */
#define CQI_CL_CPOS2LBOUND 0x1420
/* INPUT:  (STRING attribute, INT_LIST cpos)                             */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns left boundary of s-attribute region enclosing cpos, -1 if not in region */

#define CQI_CL_CPOS2RBOUND 0x1421
/* INPUT:  (STRING attribute, INT_LIST cpos)                             */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns right boundary of s-attribute region enclosing cpos, -1 if not in region */

#define CQI_CL_CPOS2ALG 0x140A
/* INPUT:  (STRING attribute, INT_LIST cpos)                             */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns -1 for every corpus position not inside an alignment          */

#define CQI_CL_STRUC2STR 0x140B
/* INPUT:  (STRING attribute, INT_LIST strucs)                           */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */
/* returns annotated string values of structure regions in <strucs>; "" if out of range */
/* check CQI_CORPUS_STRUCTURAL_ATTRIBUTE_HAS_VALUES(<attribute>) first   */

#define CQI_CL_ID2CPOS 0x140C
/* INPUT: (STRING attribute, INT id)                                     */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns all corpus positions where the given token occurs             */

#define CQI_CL_IDLIST2CPOS 0x140D
/* INPUT:  (STRING attribute, INT_LIST id_list)                          */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns all corpus positions where one of the tokens in <id_list>     */
/* occurs; the returned list is sorted as a whole, not per token id      */

#define CQI_CL_REGEX2ID 0x140E
/* INPUT: (STRING attribute, STRING regex)                               */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns lexicon IDs of all tokens that match <regex>; the returned    */
/* list may be empty (size 0);                                           */

#define CQI_CL_STRUC2CPOS 0x140F
/* INPUT: (STRING attribute, INT struc)                                  */
/* OUTPUT: CQI_DATA_INT_INT                                              */
/* returns start and end corpus positions of structure region <struc>    */

#define CQI_CL_ALG2CPOS 0x1410
/* INPUT: (STRING attribute, INT alg)                                    */
/* OUTPUT: CQI_DATA_INT_INT_INT_INT                                      */
/* returns (src_start, src_end, target_start, target_end)                */



#define CQI_CQP 0x15

#define CQI_CQP_QUERY 0x1501
/* INPUT:  (STRING mother_corpus, STRING subcorpus_name, STRING query)   */
/* OUTPUT: CQI_STATUS_OK                                                 */
/* <query> must include the ';' character terminating the query.         */

#define CQI_CQP_LIST_SUBCORPORA 0x1502
/* INPUT:  (STRING corpus)                                               */
/* OUTPUT: CQI_DATA_STRING_LIST                                          */

#define CQI_CQP_SUBCORPUS_SIZE 0x1503
/* INPUT:  (STRING subcorpus)                                            */
/* OUTPUT: CQI_DATA_INT                                                  */

#define CQI_CQP_SUBCORPUS_HAS_FIELD 0x1504
/* INPUT:  (STRING subcorpus, BYTE field)                                */
/* OUTPUT: CQI_DATA_BOOL                                                 */

#define CQI_CQP_DUMP_SUBCORPUS 0x1505
/* INPUT:  (STRING subcorpus, BYTE field, INT first, INT last)           */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* Dump the values of <field> for match ranges <first> .. <last>         */
/* in <subcorpus>. <field> is one of the CQI_CONST_FIELD_* constants.    */

#define CQI_CQP_DROP_SUBCORPUS 0x1509
/* INPUT:  (STRING subcorpus)                                            */
/* OUTPUT: CQI_STATUS_OK                                                 */
/* delete a subcorpus from memory                                        */

/* The following two functions are temporarily included for the Euralex 2000 tutorial demo */
/* frequency distribution of single tokens                               */
#define CQI_CQP_FDIST_1 0x1510
/* INPUT:  (STRING subcorpus, INT cutoff, BYTE field, STRING attribute)  */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns <n> (id, frequency) pairs flattened into a list of size 2*<n> */
/* field is one of CQI_CONST_FIELD_MATCH, CQI_CONST_FIELD_TARGET, CQI_CONST_FIELD_KEYWORD */
/* NB: pairs are sorted by frequency desc.                               */

/* frequency distribution of pairs of tokens                             */
#define CQI_CQP_FDIST_2 0x1511
/* INPUT:  (STRING subcorpus, INT cutoff, BYTE field1, STRING attribute1, BYTE field2, STRING attribute2) */
/* OUTPUT: CQI_DATA_INT_LIST                                             */
/* returns <n> (id1, id2, frequency) pairs flattened into a list of size 3*<n> */
/* NB: triples are sorted by frequency desc.                             */



/*  ***                                                                  */
/*  ***   Constant Definitions                                           */
/*  ***                                                                  */

#define CQI_CONST_FALSE 0x00
#define CQI_CONST_NO 0x00

#define CQI_CONST_TRUE 0x01
#define CQI_CONST_YES 0x01

/* The following constants specify which field will be returned          */
/* by CQI_CQP_DUMP_SUBCORPUS and some other subcorpus commands.          */

#define CQI_CONST_FIELD_MATCH 0x10
#define CQI_CONST_FIELD_MATCHEND 0x11

/* The constants specifiying target0 .. target9 are guaranteed to        */
/* have the numerical values 0 .. 9, so clients do not need to look      */
/* up the constant values if they're handling arbitrary targets.         */
#define CQI_CONST_FIELD_TARGET_0 0x00
#define CQI_CONST_FIELD_TARGET_1 0x01
#define CQI_CONST_FIELD_TARGET_2 0x02
#define CQI_CONST_FIELD_TARGET_3 0x03
#define CQI_CONST_FIELD_TARGET_4 0x04
#define CQI_CONST_FIELD_TARGET_5 0x05
#define CQI_CONST_FIELD_TARGET_6 0x06
#define CQI_CONST_FIELD_TARGET_7 0x07
#define CQI_CONST_FIELD_TARGET_8 0x08
#define CQI_CONST_FIELD_TARGET_9 0x09

/* The following constants are provided for backward compatibility       */
/* with traditional CQP field names & while the generalised target       */
/* concept isn't yet implemented in the CQPserver.                       */
#define CQI_CONST_FIELD_TARGET  0x00
#define CQI_CONST_FIELD_KEYWORD 0x09


/* CQi version is CQI_MAJOR_VERSION.CQI_MINOR_VERSION                    */
#define CQI_MAJOR_VERSION 0x00
#define CQI_MINOR_VERSION 0x01




