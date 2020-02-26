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

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <assert.h>

#include "../cl/cl.h"
#include "../cl/ui-helpers.h"

#include "macro.h"
#include "options.h"
#include "output.h"
#include "cqp.h"
#include "hash.h"
#include "eval.h"
#include "ranges.h"
#include "targets.h"
#include "corpmanag.h"

#include "parser.tab.h"


#define MACRO_HASH_BUCKETS 8000

#define MACRO_MAX_ARGS 10


extern FILE *yyin;                /**< flex input when parsing file */
extern int yylex(void);           /**< flex caller rfubnction */
extern char *yytext;

/**
 * Buffer to store input strings while
 */
typedef struct _InputBuffer {
  char *data;                      /**< the actual buffered input string */
  int position;                    /**< current read position */
  struct _MacroEntry *macro;       /**< pointer to the macro which created this buffer */
  struct _InputBuffer *next;       /**< next buffer (will be read when current buffer has been completed) */
} *InputBuffer;

/** head of linked list of active input buffers (for the macro system) */
InputBuffer InputBufferList = NULL;


/*
 *  macro definitions & lookup hash
 */

/**
 * The macro replacement string is stored as a sequence of segments:
 *
 *   literal text ...  string = "...",  arg = -1
 *   argument ...      string = NULL,   arg = 0 .. 9
 *   pseudo arg ...    string = NULL,   arg = -1
 *
 * A linked list of these defines the macro's replacement.
 */
typedef struct _MacroSegment {
  char *string;
  int arg;
  struct _MacroSegment *next;
} *MacroSegment;

/** An entry in the macro database */
typedef struct _MacroEntry {
  char *name;                      /**< Name of this macro; note the macro hash key is (name, #args) to allow overloading */
  int args;                        /**< This macro's  number of arguments */
  char *argnames[MACRO_MAX_ARGS];  /**< Array of strings for optional argument names (for information only) */
  MacroSegment replacement;        /**< Head of linked list of macro replacement segments */
  int active;                      /**< Boolean: is this macro active?  -- used to detect recursive macros */
  struct _MacroEntry *next;        /**< Pointer to next macro in the linked list of the present bucket */
} *MacroEntry;

/**
 * The MacroHash is the object which stores the macro database.
 *
 * This hash implementation can hold multiple macro entries in a single bucket
 * in order to avoid cache overflow when _very_ many macros are defined. Since
 * we don't need to store any other information in the buckets, a bucket is
 * simply a pointer to a list of macro entries, i.e. a MacroEntry.
 */
typedef struct _MacroHashTable {
  MacroEntry *hash;          /**< Array of buckets. */
  int size;                  /**< Number of buckets. */
} *MacroHashTable;

MacroHashTable MacroHash = NULL;        /* our macro database */


/** indent macro expansion debugging output (according to # of active input buffers) */
static void
macro_debug_newline_indent(void)
{
  InputBuffer buf = InputBufferList;

  fprintf(stderr, "\n");
  while (buf != NULL) {
    fprintf(stderr, "  ");
    buf = buf->next;
  }
}

/** push new input buffer (which can hold string of length <size>) onto the top of buffer list */
static InputBuffer
PushInputBuffer(int size)
{
  InputBuffer new_buffer;

  new_buffer = (InputBuffer)cl_malloc(sizeof(struct _InputBuffer));
  new_buffer->data = (char *)cl_malloc(size + 1);
  new_buffer->position = 0;
  new_buffer->data[0] = '\0';        /* <data> initialised to empty string */
  new_buffer->macro = NULL;

  new_buffer->next = InputBufferList;
  InputBufferList = new_buffer;

  return new_buffer;
}

/** delete input buffer from top of list */
static void
PopInputBuffer(void)
{
  InputBuffer temp;

  if (InputBufferList) {
    temp = InputBufferList;
    InputBufferList = InputBufferList->next;
    cl_free(temp->data);
    if (temp->macro)
      temp->macro->active = 0;        /* reset macro to inactive state */
    cl_free(temp);
  }
}

/** Initialises the global macro hash; see init_macros() for how it's used */
static void
InitialiseMacroHash(int size)
{
  int bytes;

  MacroHash = (MacroHashTable)cl_malloc(sizeof(struct _MacroHashTable));

  MacroHash->size = find_prime(size);
  bytes = sizeof(MacroEntry) * MacroHash->size;
  MacroHash->hash = (MacroEntry *)cl_malloc(bytes);
  memset(MacroHash->hash, 0, bytes); /* initialise to NULL pointers */
}

/** find hash entry .. returns MacroEntry or NULL if not in hash */
static MacroEntry
MacroHashLookup(char *str, int args)
{
  MacroEntry p;
  int offset;

  if (MacroHash == NULL) {
    cqpmessage(Error, "Macro hash not initialised.");
    return NULL;
  }

  offset = hash_macro(str, args) % MacroHash->size;

  for (p = MacroHash->hash[offset] ; p ; p = p->next)
    if (p->args == args && CL_STREQ(p->name, str))
      break;

  return p;
}

/**
 * Add a macro to the hash.
 *
 * NB if you add a macro key that is already in the hash, the new entry will
 * hide the old one, in accordance with the macro redefinition strategy.
 *
 * (Macro keys are defined deterministically from the macro's name and number of arguments.)
 *
 * @param str  The string for the macro's name.
 * @param args The number of arguments the macro has.
 * @return     The new MacroEntry.
 */
static MacroEntry
MacroHashAdd(char *str, int args)
{
  MacroEntry new_macro;
  int offset, i;

  if (MacroHash == NULL) {
    cqpmessage(Error, "Macro hash not initialised.");
    return NULL;
  }

  /* Put the macro into the correct bucket based on its hash. */
  offset = hash_macro(str, args) % MacroHash->size;

  new_macro = (MacroEntry) cl_malloc(sizeof(struct _MacroEntry));
  new_macro->name = cl_strdup(str);
  new_macro->args = args;
  for (i = 0; i < MACRO_MAX_ARGS; i++)
    new_macro->argnames[i] = NULL;
  new_macro->replacement = NULL;
  new_macro->active = 0;

  new_macro->next = MacroHash->hash[offset];
  MacroHash->hash[offset] = new_macro;

  return new_macro;
}

/* remove macro from hash ... this is worse than you'd think at first */
static void
MacroHashDelete(MacroEntry macro)
{
  MacroEntry p;
  MacroSegment seg;
  int offset, i;

  offset = hash_macro(macro->name, macro->args) % MacroHash->size; /* find the macro's bucket */
  p = MacroHash->hash[offset];

  if (p == macro)
    MacroHash->hash[offset] = macro->next;
  else {                        /* find macro's predecessor in this bucket */
    for ( ; p ; p = p->next )
      if (p->next == macro)
        break;
    if (p == NULL) {                /* this REALLY shouldn't happen */
      cqpmessage(Error, "MacroHashDelete: MacroEntry not found in hash ???");
      exit(1);
    }
    p->next = macro->next;        /* cut macro from list */
  }
  /* free macro's name, argument names, segments, and the macro struct itself */
  while (macro->replacement != NULL) {
    seg = macro->replacement;
    macro->replacement = seg->next;
    cl_free(seg->string);
    cl_free(seg);
  }
  for (i = 0; i < MACRO_MAX_ARGS; i++)
    cl_free(macro->argnames[i]);
  cl_free(macro->name);
  cl_free(macro);
}


/**
 * append segment to macro replacement string,
 * returns pointer to the new, initialised segment
 */
static MacroSegment
MacroAddSegment(MacroEntry macro)
{
  MacroSegment seg, new_seg;

  assert(macro);

  /* allocate & initialise new segment */
  new_seg = (MacroSegment)cl_malloc(sizeof(struct _MacroSegment));
  new_seg->string = NULL;
  new_seg->arg = -1;
  new_seg->next = NULL;

  /* append new segment to macro's replacement list */
  if (macro->replacement == NULL)
    macro->replacement = new_seg; /* first list element */

  else {
    /* find last segment in list */
    for (seg = macro->replacement; seg->next != NULL; seg = seg->next)
      /* nothing */
      ;
    seg->next = new_seg;
  }

  return new_seg;
}




/**
 * Get a character for the lexer/parser.
 *
 * This function reads one character (byte) of input from
 *   InputBufferList[top], ... , InputBufferList[bottom], cqp_input_string, yyin
 * in that order of precedence.
 *
 * It adds the character to query buffer, and returns it.
 *
 * Used by the lexer to get its characters; see parser.l where YY_INPUT() is defined
 * to call this function.
 *
 * This function is in this file because it accesses the variables associated
 * with CQP-macro input buffering.
 */
int
yy_input_char(void)
{
  InputBuffer buf;
  int character;

  buf = InputBufferList;        /* read from buffer on top of input buffer list */
  if (buf != NULL) {
    if (buf->data[buf->position] == '\0') {
      /* end of buffer reached -> pop it and try again */
      PopInputBuffer();
      if (macro_debug)
        macro_debug_newline_indent(); /* start new line & indent in debugging output */
      return yy_input_char();        /* will be added to query buffer in embedded call */
    }
    else
      character = (unsigned char) buf->data[buf->position++];
  }
  else {
    /* read cl_input_string if it's defined; otherwise read from the yyin file handle. */
    if (cqp_input_string != NULL)
      character = (unsigned char) cqp_input_string[cqp_input_string_ix++];
    else
      character = getc(yyin);

    /* add character to query buffer (turned off while expanding macros)
       (also turned off while reading ~/.cqprc and ~/.cqpmacros; see addHistoryLine() <parse_actions.c>)
       (not turned off in silent mode (e.g. in child mode), so we get the location of parse errors!) */
    if (!reading_cqprc && !QueryBufferOverflow && (character >= 0)) {
      if ((QueryBufferP + 1) < QUERY_BUFFER_SIZE) {
        QueryBuffer[QueryBufferP++] = character;
        QueryBuffer[QueryBufferP] = '\0';
      }
      else {
        if (write_history_file)
          cqpmessage(Warning, "Query buffer overflow: Last statement will not be added to query history.");
        QueryBufferOverflow++;
        QueryBufferP = 0;
        QueryBuffer[0] = '\0';
      }
    }
  }

  if (macro_debug && InputBufferList)
    fprintf(stderr, "%c", character);
  return character;
}

/** checks if input is being read from macro expansion */
int
yy_input_from_macro(void)
{
  return (InputBufferList != NULL);
}

/**
 * Initialises the macro database: creates the storage hash and defines built-in macros.
 */
void
init_macros(void)
{
  InitialiseMacroHash(MACRO_HASH_BUCKETS);

  /* standard built-in macros */
  if (! (
         /* unify feature vectors */
         define_macro("unify", 2, "$0=Att $1=Label",
                      "$1.$0") &&
         define_macro("unify", 3, "$0=Att $1=Lab1 $2=Lab2",
                      "unify($1.$0, /unify($0,'$2'))") &&
         define_macro("unify", 4, "$0=Att $1=Lab1 $2=Lab2 $3=Lab3",
                      "unify($1.$0, /unify($0,'$2','$3'))") &&
         define_macro("unify", 5, "$0=Att $1=Lab1 $2=Lab2 $3=Lab3 $4=Lab4",
                      "unify($1.$0, /unify($0,'$2','$3','$4'))") &&
         define_macro("unify", 6, "$0=Att $1=Lab1 $2=Lab2 $3=Lab3 $4=Lab4 $5=Lab5",
                      "unify($1.$0, /unify($0,'$2','$3','$4','$5'))") &&
         define_macro("unify", 7, "$0=Att $1=Lab1 $2=Lab2 $3=Lab3 $4=Lab4 $5=Lab5 $6=Lab6",
                      "unify($1.$0, /unify($0,'$2','$3','$4','$5','$6'))") &&
         define_macro("unify", 8, "$0=Att $1=Lab1 $2=Lab2 $3=Lab3 $4=Lab4 $5=Lab5 $6=Lab6 $7=Lab7",
                      "unify($1.$0, /unify($0,'$2','$3','$4','$5','$6','$7'))") &&
         define_macro("unify", 9, "$0=Att $1=Lab1 $2=Lab2 $3=Lab3 $4=Lab4 $5=Lab5 $6=Lab6 $7=Lab7 $8=Lab8",
                      "unify($1.$0, /unify($0,'$2','$3','$4','$5','$6','$7','$8'))") &&
         define_macro("unify",10, "$0=Att $1=Lab1 $2=Lab2 $3=Lab3 $4=Lab4 $5=Lab5 $6=Lab6 $7=Lab7 $8=Lab8 $9=Lab9",
                      "unify($1.$0, /unify($0,'$2','$3','$4','$5','$6','$7','$8','$9'))") &&
         /* match a single region (of a structural attribute) -- e.g. pre-parsed chunks */
         define_macro("region", 1, "$0=Tag",
                      "(<$0> []* </$0>)") &&
         define_macro("region", 2, "$0=Tag $1=Label",
                      "(<$0> $1:[] []* </$0>)") &&
         /* match a region containing a particular token; the full pattern matching the required token must be specified */
         define_macro("in_region", 2, "$0=Tag $1=Pattern",
                      "(<$0> []* ($1) []* </$0>)") &&
         define_macro("in_region", 3, "$0=Tag $1=Pattern1 $2=Pattern2",
                      "(<$0> []* ( ($1) []* ($2) | ($2) []* ($1) ) []* </$0>)") &&
         /* undefines Label(s) & returns true */
         define_macro("undef", 1, "$0=Label",
                      "(ignore(~$0))") &&
         define_macro("undef", 2, "$0=Lab1 $1=Lab2",
                      "(/undef($0) & /undef($1))") &&
         define_macro("undef", 3, "$0=Lab1 $1=Lab2 $2=Lab3",
                      "(/undef($0) & /undef($1,$2))") &&
         define_macro("undef", 4, "$0=Lab1 $1=Lab2 $2=Lab3 $3=Lab4",
                      "(/undef($0) & /undef($1,$2,$3))") &&
         define_macro("undef", 5, "$0=Lab1 $1=Lab2 $2=Lab3 $3=Lab4 $4=Lab5",
                      "(/undef($0) & /undef($1,$2,$3,$4))") &&
         define_macro("undef", 6, "$0=Lab1 $1=Lab2 $2=Lab3 $3=Lab4 $4=Lab5 $5=Lab6",
                      "(/undef($0) & /undef($1,$2,$3,$4,$5))") &&
         define_macro("undef", 7, "$0=Lab1 $1=Lab2 $2=Lab3 $3=Lab4 $4=Lab5 $5=Lab6 $6=Lab7",
                      "(/undef($0) & /undef($1,$2,$3,$4,$5,$6))") &&
         define_macro("undef", 8, "$0=Lab1 $1=Lab2 $2=Lab3 $3=Lab4 $4=Lab5 $5=Lab6 $6=Lab7 $7=Lab8",
                      "(/undef($0) & /undef($1,$2,$3,$4,$5,$6,$7))") &&
         define_macro("undef", 9, "$0=Lab1 $1=Lab2 $2=Lab3 $3=Lab4 $4=Lab5 $5=Lab6 $6=Lab7 $7=Lab8 $8=Lab9",
                      "(/undef($0) & /undef($1,$2,$3,$4,$5,$6,$7,$8))") &&
         define_macro("undef",10, "$0=Lab1 $1=Lab2 $2=Lab3 $3=Lab4 $4=Lab5 $5=Lab6 $6=Lab7 $7=Lab8 $8=Lab9 $9=Lab10",
                      "(/undef($0) & /undef($1,$2,$3,$4,$5,$6,$7,$8,$9))") &&
         /* distribution of attribute values for a given word form (generally, tokens with particular attribute value) -- sometimes called colligates */
         define_macro("codist", 2, "$0=Word $1=Att2",
                      "/codist[word, '$0', $1]") &&
         define_macro("codist", 3, "$0=Att1 $1=Word $2=Att2",
                      "_Codist_Results = [$0 = '$1']; group _Codist_Results match $2 group by match $0; discard _Codist_Results;") &&
         1
         ))
    {
      cqpmessage(Error, "Error in definition of built-in macros. Contact support :o)");
    }
}


/** macro args are stored in a global array so we can always free() the memory */
char *(macro_arg[MACRO_MAX_ARGS]) = {NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL};   /* 10 NULLs  @see MACRO_MAX_ARGS */
unsigned int pseudo_arg_counter = 0; /**< unique number for pseudo argument in each macro call */




/**
 * Expand a macro.
 *
 * This function:
 *   - reads the macro argument list using yylex();
 *   - looks up the macro by its <name> and number of arguments;
 *   - pushes an input buffer containing the subsituted string
 *     onto the top of the input buffer list.
 *
 * @param name  The name of the macro to be looked up.
 * @return      Boolean true for OK, false  if macro is not defined
 *              or if there is a syntax error in the argument list
 */
int
expand_macro(char *name)
{
  MacroEntry macro;
  MacroSegment seg;
  int args = 0;
  int token = 0;
  int i, len;
  char *buf, *part;
  InputBuffer buffer;
  char pseudo_arg[20];


  /* free() macro args allocated during last macro expansion */
  for (i = 0; i < MACRO_MAX_ARGS; i++)
    cl_free(macro_arg[i]);

  /* set pseudo argument */
  sprintf(pseudo_arg, "_pseudo_%u", pseudo_arg_counter++);

  /* some rather daring circular usage of the lexical analyzer ... but it _should_ work */
  /* the '(' is part of the macro token recognised by the lexical analyzer now */
  for (token = yylex(); token != ')' && token != ']'; token = yylex()) {
    switch (token) {
    case INTEGER:
      macro_arg[args++] = cl_strdup(yytext);        /* yytext should still contain the string representation of the integer value */
      break;
    case ID:
    case STRING:
      macro_arg[args++] = yylval.strval; /* already strdup()ed by lexical analyzer */
      break;
    default:
      cqpmessage(Error, "Invalid macro argument type (%d).", token);
      return 0;
    }
    if (args > MACRO_MAX_ARGS) {
      cqpmessage(Error, "Too many arguments in macro call.");
      return 0;
    }

    token = yylex();
    /* token following an argument must be either ',' or ')'/']' */
    if (token == ')' || token ==  ']')
      break;
    if (token != ',') {
      cqpmessage(Error, "Macro syntax error : expected comma or closing bracket after macro argument.");
      return 0;
    }
    /* so here we know it was definitely ',' */
  }

  macro = MacroHashLookup(name, args);

  if (!macro) {
    cqpmessage(Error, "Macro %s(%d) is not defined.", name, args);
    return 0;
  }
  else {
    if (macro->active) {
      cqpmessage(Error, "Recursion in definition of macro %s(%d).", name, args);
      return 0;
    }

    if (macro_debug) {
      if (InputBufferList == NULL) {
        fprintf(stderr, "EXPAND MACRO %s(", name);
        for (i = 0; i < args; i++) {
          fprintf(stderr, "%s", macro_arg[i]);
          if (i < (args-1)) fprintf(stderr, ", ");
        }
        fprintf(stderr, ")");
      }
    }

    len = 0;                        /* calculate length of replacement string first */
    for (seg = macro->replacement; seg != NULL; seg = seg->next) {
      if (seg->arg >= 0)
        len += strlen(macro_arg[seg->arg]);
      else if (seg->string != NULL)
        len += strlen(seg->string);
      else
        len += strlen(pseudo_arg);
    }
    buffer = PushInputBuffer(len); /* allocate & fill buffer with macro replacement */
    if (macro_debug) {
      fprintf(stderr, " ==>");            /* symbolises macro expansion into next line */
      macro_debug_newline_indent(); /* if debugging macro expansion, start new line & indent */
    }
    buffer->macro = macro;
    macro->active = 1;                /* macro is activated now */
    for (seg = macro->replacement, buf = buffer->data; seg != NULL; seg = seg->next) {
      if (seg->arg >= 0)
        part = macro_arg[seg->arg];
      else if (seg->string != NULL)
        part = seg->string;
      else
        part = pseudo_arg;
      cl_strcpy(buf, part);                /* insert segment into replacement string */
      buf += strlen(part);
    }
    *buf = '\0';
  }

  return 1;
}


/**
 * Define a new macro.
 *
 * @param name       macro name
 * @param args        # of arguments (0 .. 9); alternatively, specify argstr.
 * @param argstr      macro argument string (e.g. ``$0=name $1=label'')
 *                    (pass NULL to use args)
 * @param definition  The macro definition ... this string is substituted for /<name>(...);
 *                    $0 .. $9 refer to the macro's arguments and CANNOT be escaped
 * @return            Boolean: true if macro definition was successful, false  on syntax error
 */
int
define_macro(char *name, int args, char *argstr, char *definition)
{
  MacroEntry macro;
  MacroSegment seg;
  char *point, *mark;                /* remember emacs? ;-) */
  char *s;
  int argument, len;

  if (argstr) {                /* <argstr> overrides <args> */
    args = 0; /* -> count number of args specified in argstr */
    point = argstr;
    while (*point != '\0') {
      if (*point == '$') {
        point++;
        if ((*point >= '0') && (*point <= '9')) {
          int n = (*point - '0') + 1;
          if (n > args)
            args = n;
        }
      }
      point++;
    }
  }

  if (args < 0 || args > MACRO_MAX_ARGS) {
    cqpmessage(Error, "Invalid number of arguments in macro definition: %s(%d)\n", name, args);
    return 0;
  }
  if (NULL != (macro = MacroHashLookup(name, args))) {
    if (!silent)
      fprintf(stderr, "WARNING Macro %s(%d) redefined\n", name, args);
    MacroHashDelete(macro);
  }

  macro = MacroHashAdd(name, args);
  if (argstr) {                  /* if <argstr> was specified, extract argument names & check syntax */
    point = argstr;              /* leading whitespace is not allowed! */
    argument = 0;                /* next expected argument */
    while (*point != 0) {
      if (*point == '$') {
        point++;
        if (*point == ('0' + argument)) {
          point++;
          if (*point == '=') {
            point++;
            mark = point;
            while ((*point >= 'A' && *point <= 'Z') || /* valid identifier characters */
                   (*point >= 'a' && *point <= 'z') ||
                   (*point >= '0' && *point <= '9') ||
                   (*point == '_') || (*point == '-')) {
              point++;
            }
            len = (point - mark); /* string length of argument name */
            if (macro->argnames[argument] != NULL) {
              cqpmessage(Error, "Argument $%d defined twice in prototype %s(%s)", argument, name, argstr);
              MacroHashDelete(macro);
              return 0;
            }
            macro->argnames[argument] = (char *) cl_malloc(len + 1);
            strncpy(macro->argnames[argument], mark, len);
            (macro->argnames[argument])[len] = 0;
            argument++;
          }
          else {
            cqpmessage(Error, "Missing '=' in macro prototype %s(%s)", name, argstr);
            MacroHashDelete(macro);
            return 0;
          }
        }
        else {
          cqpmessage(Error, "Invalid argument $%c in macro prototype %s(%s)", *point, name, argstr);
          MacroHashDelete(macro);
          return 0;
        }
      }
      else {
        cqpmessage(Error, "Syntax error in macro prototype %s(%s)", name, argstr);
        MacroHashDelete(macro);
        return 0;
      }
      /* skip whitespace between arguments in prototype */
      while (*point == ' ' || *point == '\t')
        point++;
    }
  } /* if (argstr != NULL) ... */


  /* Chunk macro definition into segments. We assume the following structure
   *    <string>, <arg>, <string>, <arg>, ..., <string>, EOS
   * where each of the strings may be empty.
   */
  point = definition;
  while (*point != '\0') {
    /* scan string segment (ended by \000 or by $<n>) */
    for (mark = point; *point != '\0'; point++)
      if (*point == '$' &&
          (((*(point+1) >= '0') && (*(point+1) <= '9')) || *(point+1) == '$'))
        break;
    /* append string segment to replacement list unless it's empty */
    if (point > mark) {
      seg = MacroAddSegment(macro);
      seg->string = (char *) cl_malloc(point - mark + 1);
      for (s = seg->string; mark < point; mark++, s++)
        *s = *mark;
      *s = '\0';
    }
    /* append argument segment if point is over '$' character */
    if (*point == '$') {
      if (*(point+1) == '$') {
        seg = MacroAddSegment(macro); /* pseudo argument */
        seg->arg = -1;
        seg->string = NULL;
      }
      else {
        argument = *(point+1) - '0';
        if (argument >= args) {
          cqpmessage(Error, "Invalid argument $%d in macro %s(%d).", argument, name, args);
          MacroHashDelete(macro);
          return 0;
        }
        seg = MacroAddSegment(macro);
        seg->arg = argument;
      }
      point += 2;                /* advance point past argument */
    }
  }
  return 1;
}

/** internal function for load_macro_file(): remove whitespace & comments from input line */
static char *
preprocess_input_line(char *line)
{
  char *p;
  char quote;                /* 0 -> not in string, ' -> single-quoted string, " -> double-quoted string */

  /* remove trailing linebreak (LF or CRLF) */
  cl_string_chomp(line);

  /* skip leading whitespace */
  while ((*line == ' ') || (*line == '\t'))
    line++;

  /* now parse quoted strings (quotes must balance) & remove comments */
  quote = 0;
  for (p = line; *p; p++) {
    if (*p == '\\') {
      p++;
      if (*p == '\0')
        return NULL;                /* backslash at end of line -> parse error */
    }
    else if (quote) {                /* if we're in a string, look for end-of-string quote */
      if (*p == quote) {
        quote = 0;
      }
    }
    else if ((*p == '\'') || (*p == '"')) { /* otherwise, a quote starts a new string */
      quote = *p;
    }
    else if (*p == '#') {        /* '#' char outside of string -> comment */
      *p = '\0';                 /* strip comment */
      break;
    }
  }
  if (quote) return NULL;        /* unbalanced quotes -> parse error */

  /* remove trailing whitespace */
  p = line + strlen(line);
  while ((--p >= line) && ((*p == ' ') || (*p == '\t')))
    *p = '\0';

  return line;
}

/**
 * Internal function for load_macro_file(): parse macro name & number of arguments.
 *
 * expected format: <whitespace> <identifier> '(' <digit> ')'       -> sets nr_of_args, prototype = NULL
 *              or: <whitespace> <identfifier> '(' <prototype> ')'  -> nr_of_args = 0,  prototype = copy of <prototype>
 *
 * @return   Pointer to copy of <identifier> or NULL on parse error
 */
static char *
parse_macro_name(char *text, int *nr_of_args, char **prototype)
{
  char *p, *mark;
  int len, len_p;

  /* init return variables */
  *nr_of_args = 0;
  *prototype = NULL;

  /* skip leading whitespace (at least 1 byte required) */
  for (p = text; (*p == ' ') || (*p == '\t'); p++) {}
  if (p == text) return NULL;

  /* expect identifier: [A-Za-z_][A-Za-z0-9_-]* */
  text = p;
  if (strspn(text, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_") < 1)
    return NULL;
  len = strspn(text, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_0123456789-");
  p = text + len;

  /* expect '(' <digit>=nr_of_args ')' '\0' at p */
  if (!(*p++ == '('))
    return NULL;
  if ( *p >= '0' && *p <= '9' ) {      /* format (a): '(' <digit> ')' */
    *nr_of_args = *p++ - '0';
    if (*nr_of_args == 1 && *p == '0') { /* special case: 10 arguments (because MACRO_MAX_ARGS==10) */
      *nr_of_args = 10;
      p++;
    }
    if (!(*p++ == ')'))
      return NULL;
    if (!(*p == 0))
      return NULL;
  }
  else {                                 /* format (b): '(' <prototype> ')' */
    mark = p;
    while (*p != ')') {
      if (*p == 0)
        return NULL; /* line ended before ')' */
      p++;
    }
    len_p = p - mark;
    if (*(++p) != 0)
      return NULL;
    *prototype = (char *)cl_malloc(len_p+1);
    strncpy(*prototype, mark, len_p);
    (*prototype)[len_p] = '\0';
  }

  /* line parsed OK -> return copy of macro name */
  p = (char *)cl_malloc(len+1);
  strncpy(p, text, len);
  p[len] = '\0';
  return p;
}

/**
 * Loads the content of a macro definition file
 * and creates the macros therein.
 *
 * FORMAT:
 *   MACRO <name>(<numargs>)
 *   <macro definition>
 *   ;
 *   ...
 * line comments starting with '#' are automatically removed,
 * as is any whitespace at the start or end of a line; blanks are inserted
 * between the lines of a multi-line macro definition
 *
 * @param filename   File to process.
 */
void
load_macro_file(char *filename)
{
  FILE *src;                     /* macro file handle */
  char input_line[MACRO_FILE_MAX_LINE_LENGTH + 3]; /* line input buffer */
  char *line;                    /* pointer to input line after preprocessing */
  char *macro_name;              /* name of macro currently being processed, NULL if not in definition body */
  int macro_args;                /* number of arguments for current macro */
  char *macro_prototype;         /* alternatively, a prototype might be given for the arguments */
  char *macro_body;              /* macro definition (re-allocated for multi-line definitions) */
  int line_number;               /* current line in macro file (for error messages) */
  char *import_file;             /* filename for an import file (macro file that is included with IMPORT command) */

  src = fopen(filename, "r");
  if (!src) {
    cqpmessage(Error, "Can't open macro definition file '%s'", filename);
    return;
  }

  line_number = 0;
  macro_name = NULL;
  macro_args = 0;
  macro_prototype = NULL;
  macro_body = NULL;

  while (!feof(src) && fgets(input_line, MACRO_FILE_MAX_LINE_LENGTH + 3, src)) {

    /* within this loop, any "break" == "stop processing file" */

    line_number++;
    if (strlen(input_line) > MACRO_FILE_MAX_LINE_LENGTH + 1) {
      cqpmessage(Error, "Line exceeds maximum length of %d characters (file '%s', line %d)\n",
                 MACRO_FILE_MAX_LINE_LENGTH, filename, line_number);
      break;
    }

    line = preprocess_input_line(input_line);

    if (!line) {
      cqpmessage(Error, "Unbalanced quotation marks (file '%s', line %d)\n", filename, line_number);
      break;
    }

    if (strncmp(line, "IMPORT", 6) == 0) {

      /* import another macro definition file */

      if (macro_name) {
        cqpmessage(Error, "IMPORT directive within MACRO %s(%d) block (file '%s', line %d)\n",
                   macro_name, macro_args, filename, line_number);
        break;
      }

      import_file = line + 6;

      /* skip whitespace after IMPORT keyword; trailing w.s. and comments have already been removed by preprocess_input_line() */
      while (*import_file == ' ' || *import_file == '\t')
        import_file++;
      if (strlen(import_file) == 0) {
        cqpmessage(Error, "No filename specified in IMPORT directive (file '%s', line %d)\n", filename, line_number);
        break;
      }

      /* now import the inner definition file by calling this function recursively */
      load_macro_file(import_file);
    }
    else if (strncmp(line, "MACRO", 5) == 0) {

      /* start of macro definition block */

      if (macro_name) {
        cqpmessage(Error, "New MACRO block before definition of MACRO %s(%d) ends (file '%s', line %d)\n",
                   macro_name, macro_args, filename, line_number);
        break;
      }

      macro_name = parse_macro_name(line+5, &macro_args, &macro_prototype);

      if (!macro_name) {
        cqpmessage(Error, "MACRO syntax error (file '%s', line %d)\n", filename, line_number);
        break;
      }
    }
    else if (CL_STREQ(line, ";")) {

      /* end of macro definition block */

      if (macro_name == NULL) {
        cqpmessage(Error, "No MACRO block to end here (file '%s', line %d)\n", filename, line_number);
        break;
      }
      if (macro_body == NULL) {
        cqpmessage(Error, "Definition of MACRO %s(%d) is empty (file '%s', line %d)\n",
                   macro_name, macro_args, filename, line_number);
        break;
      }
      /* everything is OK, so we can actually define the macro */
      if (!define_macro(macro_name, macro_args, macro_prototype, macro_body)) {
        cqpmessage(Error, "Syntax error in definition of MACRO %s(%d) (file '%s', definition ending on line %d)\n",
                   macro_name, macro_args, filename, line_number);
        break;
      }
      cl_free(macro_prototype);        /* may have been allocated by parse_macro_name */
      cl_free(macro_name);
      cl_free(macro_body);
    }
    else if (strlen(line) > 0) { /* skip empty lines */
      /* check that we're within macro definition & add this line to macro_body */
      if (macro_name == NULL) {
        cqpmessage(Error, "Missing MACRO block start (file '%s', line %d)\n", filename, line_number);
        break;                        /* stop processing file */
      }
      if (!macro_body)
        macro_body = cl_strdup(line);        /* first line -> duplicate */
      else {
        /* additional lines -> reallocate body & concatenate */
        macro_body = cl_realloc(macro_body, strlen(macro_body) + strlen(line) + 2);
        strcat(macro_body, " ");
        strcat(macro_body, line);
      }
    }
  }

  if (feof(src) && macro_name)
    cqpmessage(Error, "End of file while defining MACRO %s(%d) (file '%s')\n", macro_name, macro_args, filename);

  cl_free(macro_prototype);        /* may have been allocated by parse_macro_name */
  cl_free(macro_name);
  cl_free(macro_body);
  fclose(src);
}


/** delete active input buffers */
int
delete_macro_buffers(int trace) {
  int n = 0, i;

  if (trace && (InputBufferList != NULL)) {
    fprintf(stderr, "MACRO STACK TRACE:\n");
  }
  while (InputBufferList != NULL) {
    if (trace) {
      fprintf(stderr, "%s(%d): ", InputBufferList->macro->name, InputBufferList->macro->args);
      for (i = 0; i < InputBufferList->position; i++)
        fprintf(stderr, "%c", InputBufferList->data[i]);
      fprintf(stderr, " <--\n");
    }
    PopInputBuffer();
    n++;
  }
  return n;
}

/* macro iterator variables (stores bucket in the hash, plus direct pointer to current entry) */
int iterator_bucket = -1;           /**< current bucket in macro iterator */
MacroEntry iterator_entry = NULL;   /**< current entry in bucket (if NULL, go to next bucket) @see iterator_bucket */

/**
 * Resets the the macro database iterator to the beginning.
 *
 * It does this by setting module-global iterator_bucket and
 * iterator_entry variables to their initial values.
 * (so the next call to the macro iterator will return the _first_ macro).
 */
void
macro_iterator_reset(void)
{
  iterator_bucket = -1;
  iterator_entry = NULL;
}

/** internal iterator function used by macro_iterator_next() and macro_iterator_next_prototype() */
static MacroEntry
macro_iterator_next_macro(char *prefix)
{
  int prefix_length;

  if (enable_macros && MacroHash != NULL) {
    prefix_length = (prefix != NULL) ? strlen(prefix) : 0;

    /* ignore anything after first '[' char for macro name matching (to make macro completion easier) */
    if (prefix_length > 0) {
      char *first_paren = strchr(prefix, '[');
      if (first_paren != NULL)
        prefix_length = first_paren - prefix;
    }

    while (1) {
      /* advance iterator until we find a (matching) macro entry or reach the end of the hash table */

      if (iterator_entry)
        iterator_entry = iterator_entry->next;
      while (iterator_entry == NULL) {
        iterator_bucket++;
        if (iterator_bucket >= MacroHash->size)
          return NULL;
        else
          iterator_entry = MacroHash->hash[iterator_bucket];
      }
      if (!prefix_length || !strncmp(prefix, iterator_entry->name, prefix_length))
        return iterator_entry;

      /* else continue the loop */
    }
  }
  else
    return NULL;

  /* if macros are disabled or not initialised, iterator produces NULL */
}

/** returns next macro name (matching prefix if specified), and number of arguments; NULL at end of list */
char *
macro_iterator_next(char *prefix, int *nargs)
{
  MacroEntry macro = macro_iterator_next_macro(prefix);

  if (macro) {
    *nargs = iterator_entry->args;
    return iterator_entry->name;
  }
  else {
    *nargs = 0;
    return NULL;
  }
}


/** returns next macro (matching prefix if specified), as a formatted prototype (malloc'ed) */
char *
macro_iterator_next_prototype(char *prefix)
{
  char *pt;
  int i, len;

  MacroEntry macro = macro_iterator_next_macro(prefix);

  if (!macro)
    return NULL;

  else {
    /* first compute string length required for prototype */
    len = strlen(macro->name) + 4;

    for (i = 0; i < macro->args; i++) {
      if (macro->argnames[i] == NULL)
        len += 2;
      else
        len += strlen(macro->argnames[i]) + 1;
    }

    pt = (char *)cl_malloc(len);           /* allocate macro prototype string */
    sprintf(pt, "/%s[", macro->name);

    for (i = 0; i < macro->args; i++) {
      strcat(pt, macro->argnames[i] ? macro->argnames[i] : "_");

      /* append ',' unless this is the last argument */
      if ((i+1) < macro->args)
        strcat(pt, ",");
    }

    strcat(pt, "]");

    return pt;
  }
}



/** internal function for sorting the list of macros */
static int
list_macros_sort(const void *p1, const void *p2)
{
  char *name1 = *((char **) p1);
  char *name2 = *((char **) p2);
  return strcmp(name1, name2);
}


/** list all defined macros on stdout; if a prefix is passed, print only macros whose name begins with that prefix */
void
list_macros(char *prefix)
{
  int i, len, k, N_of_macros, l;
  MacroEntry p;
  char *macro_name, initial = ' ', label[4];

  /* list of macro names matching prefix (will be sorted alphabetically) */
  char **list;

  len = (prefix != NULL) ? strlen(prefix) : 0;

  if (enable_macros && (MacroHash != NULL)) {
    /* first count macros matching prefix before allocating list */
    N_of_macros = 0;
    for (i = MacroHash->size - 1; i >= 0; i--)
      for ( p = MacroHash->hash[i] ; p ; p = p->next )
        if (!prefix || strncasecmp(p->name, prefix, len) == 0)
          N_of_macros++;

    /* compile list of macro names for output */
    list = (char **)cl_malloc(N_of_macros * sizeof(char *));
    k = 0;
    for (i = MacroHash->size - 1; i >= 0; i--)
      for (p = MacroHash->hash[i] ; p ; p = p->next)
        if (!prefix || (strncasecmp(p->name, prefix, len) == 0)) {
          l = strlen(p->name) + 8;
          macro_name = (char *) cl_malloc(l);
          sprintf(macro_name, "/%s(%d)", p->name, p->args);
          list[k++] = macro_name;
        }

    qsort(list, N_of_macros, sizeof(char *), list_macros_sort); /* sort compiled list */

    /* now print sorted list (with pretty-printing if requested) */

    if (pretty_print)
      ilist_start(0,0,0);
    for (i = 0; i < N_of_macros; i++) {
      if (pretty_print) {
        if (list[i][1] != initial) {
          initial = list[i][1];
          sprintf(label, " %c:", initial);
          ilist_print_break(label);
        }
        ilist_print_item(list[i]);
      }
      else
        printf("\t%s\n", list[i]);
    }
    if (pretty_print)
      ilist_end();

    for (i = 0; i < N_of_macros; i++)        /* free allocated strings */
      cl_free(list[i]);
    cl_free(list);
  }
}

/** print definition of macro on stdout */
void
print_macro_definition(char *name, int args)
{
  MacroEntry macro;
  MacroSegment seg;
  int i;

  if (!enable_macros)
    cqpmessage(Error, "Macros not enabled.\n");
  else {
    if (!(macro = MacroHashLookup(name, args)))
      printf("Macro %s(%d) not defined.\n", name, args);
    else {
      printf("/%s[", name);
      for (i = 0; i < args; i++) {
        if (macro->argnames[i] != NULL)
          printf("<%s>", macro->argnames[i]);
        else
          printf("<%d>", i);
        if (i < (args-1))
          printf(", ");
      }
      printf("] = \n");
      for (seg = macro->replacement; seg != NULL; seg = seg->next) {
        if (seg->arg >= 0) {
          i = seg->arg;
          if (macro->argnames[i])
            printf("<%s>", macro->argnames[i]);
          else
            printf("<%d>", i);
        }
        else if (seg->string != NULL)
          printf("%s", seg->string);
        else
          printf("<$$>");
      }
      printf("\n");
    }
  }
}



/**
 * Prints macro hash statistics on stderr (called by CQP if MacroDebug is activated).
 */
void
macro_statistics(void)
{
  int stat[4] = {0, 0, 0, 0};        /* count buckets: empty / one macro / two macros / more than two */
  MacroEntry m;
  int i, count;

  if (MacroHash == NULL)
    fprintf(stderr, "Macro hash was not initialised.\n");
  else {
    for (i = 0; i < MacroHash->size; i++) {
      for (count = 0, m = MacroHash->hash[i] ; m ; m = m->next)
        count++;
      if (count > 3)
        count = 3;
      stat[count]++;
    }
    fprintf(stderr, "Macro hash statistics:\n");
    fprintf(stderr, "\t%-6d empty buckets\n", stat[0]);
    fprintf(stderr, "\t%-6d buckets hold 1 macro\n", stat[1]);
    fprintf(stderr, "\t%-6d buckets hold 2 macros\n", stat[2]);
    fprintf(stderr, "\t%-6d buckets hold 3 or more macros\n", stat[3]);
  }
}
