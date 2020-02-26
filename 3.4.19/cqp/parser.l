%{
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

#include <string.h>
#include <stdio.h>
#include <stdlib.h>
#include <ctype.h>
#include <assert.h>

#include "../cl/globals.h"
/* #include "../cl/macros.h"      seems no longer to be needed */

#include "cqp.h"
#include "corpmanag.h"
#include "eval.h"
#include "tree.h"
#include "options.h"
#include "output.h"
#include "symtab.h"
#include "ranges.h"
#include "macro.h"
#include "targets.h"

#include "parser.tab.h"

/* By default, flex uses block-reads rather than character-reads from the *
 * input stream. Since there are at least two different input streams in  *
 * this program (the stream which flex uses and the input stream of cmds) *
 * you have to redefine YY_INPUT as follows:                              */
#undef YY_INPUT

/* replace YY_INPUT macro with call to yy_input_char() <macro.c> for
   macro expansion  --  this version suffers from the 0xff2k-Bug anyway */
/*
#define YY_INPUT(buf,result,max_size) { \
     int c = (cqp_input_string ? cqp_input_string[cqp_input_string_ix++] : getc(yyin)); \
     result = ( (c == EOF) || (c == 0)) ? 0 : (buf[0] = c, 1); \
   } 
*/

#define YY_INPUT(buf, result, max_size) { \
     int c = yy_input_char(); \
     result = ((c == EOF) || (c == 0)) ? 0 : (buf[0] = c, 1); \
   }

/* new_string = strip_quotes(string); */
/* Removes single or double quotes around string as well as doubling escapes inside (e.g. 'it''s' => it's), 
   while keeping track of backslash escapes. */
char *
strip_quotes(char *s) {
  char *t;
  int i, k, len;
  char delim = s[0];
  len = strlen(s);
  assert(len >= 2 && (delim == '"' || delim == '\'') && s[len-1] == delim); /* should be guaranteed by grammar rule */

  t = (char *) cl_malloc(len - 1); /* at most l-2 characters + NUL */
  i = 1; k = 0;
  while (i < len-1) {
    char c = s[i];
    t[k++] = s[i++]; /* copy this character, then check what to do with next character */
    if (c == '\\') {
      assert(i < len-1); /* no stranded backslash at end of string (should be guaranteed by grammar rule) */
      t[k++] = s[i++]; /* copy backslash and following character */
    }
    else if (c == delim && s[i] == delim) 
      i++; /* delete doubled delimiter */
  }
  t[k] = 0; /* NUL-terminate new string */
  return t;
}

%}



/* with this option set, we shouldn't need libfl.a */
%option noyywrap

/* unused functions input() and yyunput(): stop them being created in the lexer. */ 
%option noinput
%option nounput


digits          [0-9]+
exponent        [eE][+-]?{digits}
floatconst      [+-]?{digits}[\.]{digits}{exponent}?
letter          [a-zA-Z_\x80-\xff]
xletter         [a-zA-Z0-9_\-\.\x80-\xff]
idletter        [a-zA-Z_]
xidletter       [a-zA-Z0-9_\-\.]
var             \${letter}{xletter}*
/* old identifiers:
id              {letter}{xletter}*(:{xletter}+)?(^{xletter}+)?
id              {idletter}{xidletter}*(:{xidletter}+)?(^{xidletter}+)?
*/
/* ID (corpus name, attribute name, macro name, etc.) */
id              {idletter}{xidletter}*
/* specifier for named query result, with mother name and/or implicit expansion */
nqrid           {idletter}{xidletter}*:{idletter}{xidletter}*(^{xidletter}+)?|{idletter}{xidletter}*^{xidletter}+
flags           %[a-z]+

/* -- no idea why accents should be matched explicitly here, so just remove the rule */
/* accents         \\\"|\\,|\\\'|\\`|\\\^ */
/* -- strings are enclosed in single or double quotes, which can be escaped inside by doubling (automatically removed) */
/* -- a backslash escapes any following character (except newline), but is passed through together with the character */
string          \"(\\[^\r\n]|\"\"|[^"\r\n])*\"|\'(\\[^\r\n]|\'\'|[^'\r\n])*\'
whitespaces     [ \t\r\n]+
emptyconstr     \[[ \t]*\]
emptylaconstr   \[:[ \t]*:\]

%%
<<EOF>>         { return(0); }
exit            { return(EXIT_SYM); }
cat             { return(CAT_SYM); }
def(ine)?       { return(DEFINE_SYM); }
diff(erence)?   { return(DIFF_SYM); }
discard         { return(DISCARD_SYM); }
inter(sect(ion)?)? { return(INTER_SYM); }
join            { return(JOIN_SYM); }
subset          { return(SUBSET_SYM); }
left            { return(LEFT_SYM); }
right           { return(RIGHT_SYM); }
save            { return(SAVE_SYM); }
show            { return(SHOW_SYM); }
cd              { return(CD_SYM); } /* show context descriptor */
group           { return(GROUP_SYM); }
where           { return(WHERE_SYM); }

within          { return(WITHIN_SYM); }
with            { return(WITH_SYM); }
without         { return(WITHOUT_SYM); }
delete          { return(DELETE_SYM); }
expand          { return(EXPAND_SYM); }
to              { return(TO_SYM); }
set             { return(SET_SYM); }

source          { return(EXEC_SYM); }
cut             { return(CUT_SYM); }

info            { return(INFO_SYM); }

meet            { return(MEET_SYM); }
union           { return(UNION_SYM); }
MU              { return(MU_SYM); }
TAB             { return(TAB_SYM); }

sort            { return(SORT_SYM); }
count           { return(COUNT_SYM); }
by              { return(BY_SYM); }
foreach         { return(FOREACH_SYM); }
on              { return(ON_SYM); }
yes             { return(YES_SYM); }
off             { return(OFF_SYM); }
no              { return(NO_SYM); }
asc(ending)?    { return(ASC_SYM);  }
desc(ending)?   { return(DESC_SYM); }
reverse         { return(REVERSE_SYM); }
sleep           { return(SLEEP_SYM); }

reduce          { return(REDUCE_SYM); }
maximal         { return(MAXIMAL_SYM); }  /* "reduce to maximal" */

size            { return(SIZE_SYM); }
dump            { return(DUMP_SYM); }
undump          { return(UNDUMP_SYM); }
tabulate        { return(TABULATE_SYM); }

not             { return(NOT_SYM); }
contains        { return(CONTAINS_SYM); }
matches         { return(MATCHES_SYM); }

unlock          { return(UNLOCK_SYM); }

user            { return(USER_SYM); }     /* for CQPserver user authentication file */
host            { return(HOST_SYM); }

macro           { return(MACRO_SYM); }

randomize       { return(RANDOMIZE_SYM); } 

from            { return(FROM_SYM); }

inclusive       { return(INCLUSIVE_SYM); }
exclusive       { return(EXCLUSIVE_SYM); }

NULL            { return(NULL_SYM); }

match           { yylval.field = MatchField; return(FIELD); }    /* the fields (or 'anchor points') of a subcorpus */
matchend        { yylval.field = MatchEndField; return(FIELD); }
target          { yylval.field = TargetField; return(FIELD); }
collocate       { yylval.field = TargetField; return(FIELD); }
keyword         { yylval.field = KeywordField; return(FIELD); }

match:          { yylval.field = MatchField; return(FIELDLABEL); }    /* labels referring to fields (in "subset" command, probably in future as well in "sort", "fdist", ...) */
matchend:       { yylval.field = MatchEndField; return(FIELDLABEL); }
target:         { yylval.field = TargetField; return(FIELDLABEL); }
collocate:      { yylval.field = TargetField; return(FIELDLABEL); }
keyword:        { yylval.field = KeywordField; return(FIELDLABEL); }

\<match\>          { yylval.field = MatchField; return(ANCHORTAG); }    /* anchor point (start) tags for subqueries */
\<matchend\>       { yylval.field = MatchEndField; return(ANCHORTAG); }
\<target\>         { yylval.field = TargetField; return(ANCHORTAG); }
\<collocate\>      { yylval.field = TargetField; return(ANCHORTAG); }
\<keyword\>        { yylval.field = KeywordField; return(ANCHORTAG); }

\<\/match\>        { yylval.field = MatchEndField; return(ANCHORENDTAG); }   /* anchor point (end) tags for subqueries */
\<\/matchend\>       { yylval.field = MatchEndField; return(ANCHORENDTAG); } /* special feature: </match> marks end of entire match -> </match> == </matchend> */
\<\/target\>         { yylval.field = TargetField; return(ANCHORENDTAG); }
\<\/collocate\>      { yylval.field = TargetField; return(ANCHORENDTAG); }
\<\/keyword\>        { yylval.field = KeywordField; return(ANCHORENDTAG); }

leftmost        { yylval.search_strategy = SearchLeftmost; return(SEARCH_STRATEGY); }  /* search strategy in "set <field> ..." command */
rightmost       { yylval.search_strategy = SearchRightmost; return(SEARCH_STRATEGY); }
nearest         { yylval.search_strategy = SearchNearest; return(SEARCH_STRATEGY); }
farthest        { yylval.search_strategy = SearchFarthest; return(SEARCH_STRATEGY); }

RE\(                    { return(RE_PAREN); }  /* for [lemma = RE($var)] */

\.EOL\.                 { return(EOL_SYM); }   /* for child mode (prints special code on separate line) */


[+-]?{digits}              { 
                          yylval.ival = atoi(yytext); 
                          return(INTEGER); 
                        }
{digits}\.{digits}\.{digits}\.{digits}  {
                          yylval.strval = cl_strdup(yytext);
                          return(IPAddress);
                        }
{digits}\.{digits}\.{digits}\.\*  {
                          yylval.strval = cl_strdup(yytext);
                          yylval.strval[yyleng-1] = '\0';  /* cut off asterisk */
                          return(IPSubnet);
                        }
{floatconst}            { 
                          yylval.fval = atof(yytext);
                          return(DOUBLEFLOAT);
                        }   
\/{id}[\(\[]            { 
                          if (!enable_macros) { 
                            REJECT;   /* for absolute backward compatibility */
                          }
                          else {
                            /* strdup macro name (stripping the / and ( or [ chars) */
                            char *macro = cl_strdup(yytext+1);
                            macro[strlen(macro)-1] = '\0';
                            if (!expand_macro(macro)) {
                              cl_free(macro);
                              return(UNDEFINED_MACRO);
                            }
                            else
                              cl_free(macro);
                          }
                        }
{id}\.{id}              { 
                          yylval.strval = cl_strdup(yytext);
                          return(QID);
                        }
{id}:                   { 
                          yylval.strval = cl_strdup(yytext);
                          if (yylval.strval[yyleng-1] == ':')
                            yylval.strval[yyleng-1] = '\0';
                          return(LABEL);
                        }
{nqrid}                 { 
                          yylval.strval = cl_strdup(yytext);
                          return(NQRID);
                        }
\`{id}\`                {
                          /* quoted ID: must still be valid ID but can be a reserved word */
                          yylval.strval = cl_strdup(yytext + 1); /* strip `...` quotes */
                          yylval.strval[strlen(yylval.strval) - 1] = '\0';
                          return(ID);
                        }                       
{id}                    { 
                          yylval.strval = cl_strdup(yytext);
                          return(ID);
                        }
{var}                   { 
                          yylval.strval = cl_strdup(yytext + 1);
                          return(VARIABLE);
                        }
{flags}                 { 
                          yylval.strval = cl_strdup(yytext + 1);
                          return(FLAG);
                        }
{whitespaces}           ;
#.*                     ; /* single line comment */
{string}                {                         
                          /* cut off the leading "/' and the trailing "/' */
                          yylval.strval = strip_quotes(yytext);
                          return(STRING);
                        }
{emptyconstr}           { 
                          return(MATCHALL);
                        }
\<{id}                  { /* closing '>' not included to allow condition in the tag (nyi) */
                          
                          yylval.strval = cl_strdup(yytext+1);
                          return(TAGSTART);
                        }
\<\/{id}\>              { 
                          
                          yylval.strval = cl_strdup(yytext+2);
                          yylval.strval[yyleng-3] = '\0';  /* chop off trailing '>' */
                          return(TAGEND);
                        }

{emptylaconstr}         { return(LCMATCHALL); }  /* lookahead matchall [::] */
\[:                     { return(LCSTART); }     /* lookahead constraint pattern [: ... :] */
:\]                     { return(LCEND); }
::                      { return(GCDEL); }
\.\.\.                  { return(ELLIPSIS); }
\.\.                    { return(ELLIPSIS); }
\<=                     { return(LET); }
\>=                     { return(GET); }
!=                      { return(NEQ); }
\>\>                    { return APPEND; }
\+\=                    { return PLUSEQ; }
\-\=                    { return MINUSEQ; }
-\>                     { return IMPLIES; }
\(\?                    { return EXTENSION; } /* for standard query extensions of the form (?...) */
`                       { return ESCAPE_SYM; }
.                       { return yytext[0]; }

%%




