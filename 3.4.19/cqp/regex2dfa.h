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

#ifndef _cqp_regex2dfa_h_
#define _cqp_regex2dfa_h_

#include "cqp.h"

/**
 * The DFA object.
 *
 * A Deterministic Finite Automaton: into which a regular expression can be converted.
 *
 * (Note this is regular expression across tokens, not single-string regexes, which
 * are dealt with by functions in the corpus library.)
 *
 * TODO: rename the functions and make this more object-oriented.
 * Ideally, this should be a cleanly separated module, with "in" and "out" only
 * via the methods declared here. Currently it's not like that - info
 * is passed in via global variables, most blatantly searchstr.
 */
typedef struct dfa {
  int Max_States;         /**< max number of states of the current dfa;
                               state no. 0 is the initial state.             */
  int Max_Input;          /**< max number of input chars of the current dfa. */
  int **TransTable;       /**< state transition table of the current dfa.    */
  Boolean *Final;         /**< set of final states.                          */
  int E_State;            /**< Error State -- it is introduced in order to
                           *   make the dfa complete, so the state transition
                           *   is a total mapping. The value of this variable
                           *   is Max_States.
                           */
} DFA;

void regex2dfa(char *rxs, DFA *dfa);
void init_dfa(DFA *dfa);
void free_dfa(DFA *dfa);
void show_complete_dfa(DFA dfa);

#endif
