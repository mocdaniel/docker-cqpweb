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

/*
 * This is the lexer definition for the registry parser.
 * 
 * The name prefix is set to creg (corpus registry)
 * so functions begin with that instead of yy.
 */
%option prefix="creg"

/* unused functions yywrap(), input() and yyunput(): stop them being created in the lexer. */
%option noyywrap noinput nounput  

%{
#include <ctype.h>

#undef NULL
#define NULL 0

#include "globals.h"
#include "corpus.h"

#include "registry.tab.h"

/**
 * remove \" and \\ escapes, but keep all other "literal" backslashes; modified <string> in place
 */
void
unescape_string (char *string) {
  char *p, *q;
  for (p = q = string; *p; p++) {
    char next = *(p + 1);
    if (*p == '\\' && (next == '"' || next == '\\')) {
      p++; /* skip this escape backslash */
      *q++ = next; /* but keep the escaped character */
    }
    else {
      *q++ = *p;
    }
  }
  *q = '\0';  
}

%}

id              [a-zA-Z0-9_\-/$][^ \t\n\r\(\)\:\,]*
path            [a-zA-Z0-9/$]+
number          [0-9]+

string          \"([^"\n\r]|\\\"|\\\\)*["\n\r]
whitespaces     [ \t\n\r]+

%%

NAME            { return(NAME_SYM); }
ID              { return(ID_SYM);   }
INFO            { return(INFO_SYM); }
HOME            { return(HOME_SYM); }

ATTRIBUTE       { return(ATTRIBUTE_SYM); }

MAPTABLE        { return(IGNORE_SYM); }   /* *** ignore MAPTABLEs and NGRAMs *** */
NGRAM           { return(IGNORE_SYM); }

DIR             { return(DIR_SYM); }
CORPUS          { return(CORPUS_SYM); }
REVCORP         { return(REVCORP_SYM); }
REVCIDX         { return(REVCIDX_SYM); }
FREQS           { return(FREQS_SYM); }
LEXICON         { return(LEXICON_SYM); }
LEXIDX          { return(LEXIDX_SYM); }
LEXSRT          { return(LEXSRT_SYM); }

STRUCTURE       { return(STRUCTURE_SYM); }
ALIGNED         { return(ALIGNED_SYM); }
DYNAMIC         { return(DYNAMIC_SYM); }


ADMIN           { return(ADMIN_SYM); }
ACCESS          { return(ACCESS_SYM); }
USER            { return(USER_SYM); }
GROUP           { return(GROUP_SYM); }
ASSERT          { return(ASSERT_SYM); }
HOST            { return(HOST_SYM); }


"..."           { return(DOTS_SYM); }

##::            { return(PROPERTY_SYM); }
#[^#].*         ; /* single line comment, skip; unless the comment begins with '##::' */
##[^:].*        ;
##:[^:].*       ;
#               ;

{whitespaces}   ;  /* skip */


{number}        { creglval.ival = atoi(cregtext);
                  return(NUMBER); 
                }
{id}            { creglval.strval = cl_strdup(cregtext);
                  return(IDENTIFIER); 
                }
{string}        { creglval.strval = cl_strdup(cregtext+1);
                  creglval.strval[yyleng - 2] = '\0';
                  unescape_string(creglval.strval);
                  return(STRING); 
                }

.               return cregtext[0];

%%

