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

#ifndef _cqp_h_
#define _cqp_h_

#include <stdio.h>

/** Default filename for the CQP settings file (expected in home folder). */
#define CQPRC_NAME      ".cqprc"
/** Default filename for the CQP macros file (expected in home folder). */
#define CQPMACROS_NAME  ".cqpmacros"

/** The number of file handles CQP can store in its file-array (ie max number of nested files) @see cqp_parse_file */
#define CQP_INPUT_STACK_SIZE 20

/** Size of the CQP query buffer. */
#define QUERY_BUFFER_SIZE 2048

/** Line printed when a parse error is encountered in child mode (easily spotted by parent) */
#define CQP_PARSE_ERROR_LINE "PARSE ERROR\n"


/** DEPRACATED means of storing a Boolean value  */
typedef int Boolean;

/** DEPRACATED macros for Boolean true and false */
#define True 1
/** DEPRACATED macros for Boolean true and false */
#define False 0
/* TODO In CWB 4 we should change both the above, and the more recent "int/1/0" convention, to C11-style bool/true/false */

/**
 * MatchingStrategy type : represents one of the possible matching strategies for regular-expression queries in CQP.
 *
 * Affects the action of ?, * and + operators.
 */
typedef enum _matching_strategy {
  traditional,        /**< "traditional" strategy */
  shortest_match,     /**< match shortest possible token sequences */
  standard_match,     /**< match optional elements at the start but not at the end of the token sequences */
  longest_match       /**< find longest possible token sequences */
} MatchingStrategy;

/**
 * The "corpus yielding command type" type.
 *
 * Each possible value of the enumeration represents a particular "type"
 * of command that may potentially yield a (sub)corpus.
 */
typedef enum _cyctype {
  NoExpression,
  Query,                  /**< A query (yielding a query-result subcorpus) */
  Activation,             /**< A corpus-activation command. */
  SetOperation,
  Assignment
} CYCtype;

/** Global variable indicating type (CYC) of last expression */
CYCtype LastExpression;

extern int reading_cqprc;

extern int cqp_error_status;

/* ======================================== Query Buffer Interface */

/* ========== see parser.l:extendQueryBuffer() for details */
/* ========== initialization done in parse_actions.c:prepare_parse() */

extern char QueryBuffer[QUERY_BUFFER_SIZE];
extern int  QueryBufferP;
extern int  QueryBufferOverflow;

/* ======================================== Other global variables */

extern char *searchstr;         /* needs to be global, unfortunately */
int exit_cqp;                   /**< 1 iff exit-command was issued while parsing */


extern char *cqp_input_string;
extern int cqp_input_string_ix;

int initialize_cqp(int argc, char **argv);

int cqp_parse_file(FILE *src, int exit_on_parse_errors);

int cqp_parse_string(char *s);

/* ====================================================================== */

/**
 * Interrupt callback functions are of this type.
 */
typedef void (*InterruptCheckProc)(void);

/**
 * Boolean indicating that an interruptible process is currently running.
 *
 * The process in question is one that may be expected to be non-instantaneous.
 * This variable is turned off by the Ctrl+C interrupt handler.
 *
 * @see sigINT_signal_handler
 */
int EvaluationIsRunning;

int setInterruptCallback(InterruptCheckProc f);

void CheckForInterrupts(void);

int signal_handler_is_installed;

void install_signal_handler(void);


/* ====================================================================== */

#endif
