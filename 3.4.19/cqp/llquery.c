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

/**
 * @file
 *
 * This file contains the main function for CQP (interactive loop)
 *
 * In addition, it contains the functions that interactive CQP will need
 * if command-line editing with GNU Readline is enabled.
 */

#include <stdio.h>
#include <signal.h>

#include "../cl/cl.h"

#include "cqp.h"
#include "eval.h"
#include "options.h"
#include "macro.h"
#include "variables.h"
#include "concordance.h"
#include "output.h"
#include "ascii-print.h"


#ifdef USE_READLINE
#include <readline/readline.h>
#include <readline/history.h>
#endif /* USE_READLINE */



#ifdef USE_READLINE

/* The implementation of custom completion was largely dictated by the Editline library used
 * as a replacement for GNU Readline:
 *  "unlike GNU Readline, Editline expects to get the full list of possible completions
 *   from the custom completion function; therefore, we need some helper routines which
 *   can build NULL-terminated string lists of arbitrary size; note that the newly allocated
 *   list is returned to Editline, which should take care of freeing all strings and the list vector;
 *   all functions & static variables use the namespace prefix cc_... (for 'custom completion')"
 * We keep this architecture for the backport to GNU Readline, using rl_attempted_completion_function
 * instead of the more common rl_completion_entry_function / rl_completion_matches() approach.
*/
char **cc_compl_list = NULL;          /**< readline autocomplete: list of possible completions */
int cc_compl_list_size = 0;           /**< readline autocomplete: number of completions in list */
int cc_compl_list_allocated = 0;      /**< readline autocomplete: number of entries allocated for list (incl. NULL terminator) */
#define CC_COMPL_LIST_ALLOC_BLOCK 256 /**< readline autocomplete: how many list cells to allocate at a time */
#define RL_DEBUG 0

/* initialise new completion list (size 0) without freeing previous list (which was deallocated by readline library) */
void
cc_compl_list_init(void)
{
  cc_compl_list = (char **) cl_malloc(CC_COMPL_LIST_ALLOC_BLOCK * sizeof(char *));
  cc_compl_list_allocated = CC_COMPL_LIST_ALLOC_BLOCK;
  cc_compl_list_size = 1;
  cc_compl_list[0] = NULL; /* dummy entry for longest common prefix (computed by cc_compl_list_sort_uniq()) */
  cc_compl_list[1] = NULL; /* end-of-list marker */
}

/* add string (must be alloc'ed by caller) to completion list */
void
cc_compl_list_add(char *string)
{
  if (cc_compl_list_size >= cc_compl_list_allocated - 1) {
    /* extend list if necessary (NB: need to leave room for NULL marker at end of list) */
    cc_compl_list_allocated += CC_COMPL_LIST_ALLOC_BLOCK;
    cc_compl_list = (char **) cl_realloc(cc_compl_list, cc_compl_list_allocated * sizeof(char *));
  }
  cc_compl_list[cc_compl_list_size++] = string;
  cc_compl_list[cc_compl_list_size] = NULL;
}

/* internal function for sorting list of completions */
static int
cc_compl_list_sort(const void *p1, const void *p2)
{
  char *name1 = *((char **) p1);
  char *name2 = *((char **) p2);
  int result = strcmp(name1, name2);
  return result;
}

/* sort list and remove (& free) duplicates; returns pointer to list */
char **
cc_compl_list_sort_uniq(void)
{
  int mark, point;
  char *lcp, *new_string;

  if (cc_compl_list_size <= 1) { /* empty list (only containing dummy entry) */
    /* at least some versions of GNU readline are broken and don't accept an empty list */
    rl_attempted_completion_over = 1; /* so readline doesn't fall back to filename completion */
    cl_free(cc_compl_list);
#if RL_DEBUG
    printf("\nRETURNING 0 COMPLETIONS.\n");
#endif
    return NULL;
  }

  /* sort list entries, then go through sorted list and remove duplicates, computing LCP on the fly */
  qsort(cc_compl_list+1, cc_compl_list_size-1, sizeof(char *), cc_compl_list_sort); /* don't sort dummy entry at start of list */
  cc_compl_list[0] = cl_strdup(cc_compl_list[1]); /* LCP = longest common prefix must be prefix of first possible completion */
  mark = 1;
  point = 2;                    /* we always keep the first list element */
  while (point < cc_compl_list_size) {
    if (strcmp(cc_compl_list[mark], cc_compl_list[point]) == 0) {
      cl_free(cc_compl_list[point]);       /* duplicate -> free, don't advance mark */
    }
    else {
      mark++;                           /* new string -> advance mark & copy there */
      cc_compl_list[mark] = cc_compl_list[point];
      lcp = cc_compl_list[0];   /* adjust LCP */
      new_string = cc_compl_list[mark];
      while (*lcp && *lcp == *new_string) {
        lcp++; new_string++;
      }
      *lcp = '\0'; /* shorten LCP to common prefix with new string */
    }
    point++;
  }
  cc_compl_list_size = mark + 1;
  cc_compl_list[cc_compl_list_size] = NULL;
#if RL_DEBUG
  printf("\nRETURNING %d COMPLETIONS:\n", cc_compl_list_size);
  for (mark=0; cc_compl_list[mark]; mark++) {
    printf(" - %s\n", cc_compl_list[mark]);
  }
#endif
  return cc_compl_list;
}

/* custom completion function: complete corpus/subcorpus names */
char **
cqp_custom_completion(const char *text, int start, int end)
{
  /* <line> is the complete input line; <text> to be completed is the substring from <start> to <end> */
  char *line = rl_line_buffer;
  int text_len = end - start; /* length of <text> */
  int point, k;
  Variable var;
  CorpusList *cl;
  char *prototype, *prefix;
  char mother[CL_MAX_LINE_LENGTH];
  char *real_name, *colon;
  int mother_len, real_len, prefix_len;
  char *completion;

#if RL_DEBUG
  printf("\n>> COMPLETING TEXT '%s'\n", text);
#endif

  /*
   *  (A) file name completion (triggered by '> "', '>> "', and '< "' patterns before start)
   */

  /* must check for file name completion first because absolute path would be mistaken for a macro invocation */
  point = start;
  if ((--point >= 0) && (line[point] == '"' || line[point] == '\'')) {
    while ((--point >= 0) && (line[point] == ' ')) {
      /* nop */
    }
    if ((point >= 0) && ((line[point] == '>') || (line[point] == '<'))) {
      /* looks like a redirection (more or less ...), so return NULL and let readline handle filename completion */
      return NULL;
    }
    /* a string within a "set <option> ..." command may also be a filename */
    if (strncmp(line, "set ", 4) == 0) {
      return NULL;
    }
  }

  /*
   *  (B) variable name completion (triggered by '$' character)
   */
  if (text[0] == '$') {
    cc_compl_list_init();       /* init list only if custom completion has been triggered */
    variables_iterator_new();
    prefix = (char *) text + 1;
    prefix_len = text_len - 1;
    var = variables_iterator_next();
    while (var != NULL) {
#if RL_DEBUG
      printf("Comparing variable $%s with prefix $%s\n", var->my_name, prefix);
#endif
      if (strncmp(prefix, var->my_name, prefix_len) == 0) { /* found variable matching prefix -> format and add */
        completion = cl_malloc(strlen(var->my_name) + 2);
        sprintf(completion, "$%s", var->my_name);
        cc_compl_list_add(completion);
      }
      var = variables_iterator_next();
    }
    return cc_compl_list_sort_uniq();
  }

  /*
   *  (C) macro name completion (triggered by '/' character)
   */
  if (text[0] == '/') {
    cc_compl_list_init();
    macro_iterator_reset();
    /* find macro name matching current prefix (i.e. characters 1 .. text_len-1 of text[]) */
    prefix = cl_strdup(text + 1);
    prefix[text_len - 1] = '\0'; /* cut prefix[] to text_len - 1 characters */
    prototype = macro_iterator_next_prototype(prefix);
    while (prototype != NULL) {
      /* since the iterator ignores partially complete argument lists, we have to check that <prototype> really extends <text> */
      if (strncmp(text, prototype, text_len) == 0) {
        cc_compl_list_add(prototype);
      }
      else {
        cl_free(prototype);        /* if prototype isn't accepted, we have to free it */
      }
      prototype = macro_iterator_next_prototype(prefix);
    }
    cl_free(prefix);
    return cc_compl_list_sort_uniq();
  }

  /* at the moment, everything else triggers (sub)corpus name completion */
  cc_compl_list_init(); /* init completion list now to be built up in steps (D) and (E) */

  /*
   *  (D) After "set ..." we expect either a subcorpus name or an option name or abbreviation
   *      We handle option names here and then fall through to subcorpus completion (E)
   */
  if (strncmp(line, "set ", 4) == 0) {
    point = start;
    while((--point >= 0) && (line[point] == ' ')) {
      /* nop */
    }
    if (point == 2) {
      /* we're completing first word after "set", so trigger option name completion */
      k = 0;
      while (cqpoptions[k].opt_name != NULL) {
        if (cqpoptions[k].flags & OPTION_VISIBLE_IN_CQP) {
          completion = cqpoptions[k].opt_name;
          if (strncasecmp(completion, text, text_len) == 0)
            cc_compl_list_add(cl_strdup(completion));

          /* we could also complete abbreviations, but their use is discourage with completion available */
          /*
          completion = cqpoptions[k].opt_abbrev;
          if (completion && strncasecmp(completion, text, text_len) == 0)
            cc_compl_list_add(cl_strdup(completion));
           */
        }
        k++;
      }
    }
  }

  /*
   *  (E) (sub)corpus name completion (should be triggered by uppercase letter)
   */
  colon = strchr(text, ':');
  if ((colon != NULL) && ((mother_len = colon - text) < CL_MAX_LINE_LENGTH)) {
    /* full subcorpus specifier: ''HGC:Last'' */
    strncpy(mother, text, mother_len);
    mother[mother_len] = '\0';
    real_name = colon + 1;
    real_len = text_len - (colon - text + 1); /* compute length of subcorpus part of name */
  }
  else {
    mother_len = 0;
    real_name = (char *) text;
    real_len = text_len;
  }

  /* run throgh corpus/subcorpus list and collect matches */
  cl = FirstCorpusFromList();
  while (cl != NULL) {
    if ((cl->type == SYSTEM) || (cl->type == SUB)) /* don't show subcorpora with status TEMP */
    {
      int handled = 0;
      /* token must be prefix of corpus name (if mother name is given, consider only subcorpora) */
      if ((strncmp(cl->name, real_name, real_len) == 0)
          && (!mother_len || (cl->type == SUB))) {
        /* if mother name is given, that has to match also; same if we're looking at a subcorpus */
        if (cl->type == SUB) {
          char *expected_mother;
          if (mother_len) {
            expected_mother = mother;
          }
          else if (current_corpus) {
            expected_mother = (current_corpus->type == SUB) ? current_corpus->mother_name : current_corpus->name;
          }
          else {
            expected_mother = cl->mother_name; /* a neat little trick: don't try mother name if no corpus is activated */
          }
          if (strcmp(cl->mother_name, expected_mother) == 0) {
            if (mother_len) {
              /* we must allocate a string of sufficient length and build a full subcorpus specifier */
              completion = (char *) cl_malloc(mother_len + 1 + strlen(cl->name) + 1);
              sprintf(completion, "%s:%s", mother, cl->name);
              cc_compl_list_add(completion);
            }
            else {
              cc_compl_list_add(cl_strdup(cl->name));
            }
            handled = 1;
          }
        }
        else {
          cc_compl_list_add(cl_strdup(cl->name));
          handled = 1;
        }
      }
      if (!handled) {
        /* other possibility: current token is prefix of mother part of a subcorpus */
        if ((cl->type == SUB) && (!mother_len) && cl->mother_name &&
            (strncmp(cl->mother_name, real_name, real_len) == 0))  {
          /* requires special handling: return ''<mother>:'' */
          char *completion = (char *) cl_malloc(strlen(cl->mother_name) + 2);
          /* just show there are subcorpora as well; user must type ':' to see subcorpora completions */
          sprintf(completion, "%s:", cl->mother_name);
          /* note that this will return the same string over and over again if there are multiple subcorpora;
             fortunately, readline sorts and uniqs the list of completions, so we don't have to worry */
          cc_compl_list_add(completion);
        }
      }
    }
    cl = NextCorpusFromList(cl);
  }
  return cc_compl_list_sort_uniq();
}

/* check that line ends in semicolon, otherwise append one to the string
   (returns either same pointer or re-allocated and modified string) */
char *
ensure_semicolon (char *line)
{
  int i, l;

  if (line) {
    l = strlen(line);
    if (l > 0) {
      i = l-1;
      while ((i >= 0) && (line[i] == ' ' || line[i] == '\t' || line[i] == '\n'))
        i--;
      if (i < 0)
        *line = 0;              /* line contains only whitespace -> replace by empty string */
      else {
        if (line[i] != ';') {   /* this is the problematic case: last non-ws character is not a ';' */
          if (i+1 < l) {        /* have some whitespace at end of string that we can overwrite */
            line[i+1] = ';';
            line[i+2] = 0;
          }
          else {                /* need to reallocate string to make room for ';' */
            line = cl_realloc(line, l+2);
            line[l] = ';';
            line[l+1] = 0;
          }
        }
      }
    }
  }
  return (line);                /* return pointer to line (may have been modified and reallocated */
}



/** this function replaces cqp_parse_file(stdin) if we're using GNU Readline */
void
readline_main(void)
{
  char prompt[CL_MAX_LINE_LENGTH];
  char *input = NULL;

  /* activate CQP's custom completion function */
  rl_attempted_completion_function = cqp_custom_completion;
  /* configuration: don't break tokens on $, so word lists work correctly (everything else corresponds to readline defaults) */
  rl_completer_word_break_characters = " \t\n\"\\'`@><=;|&{(";
  /* if CQP history file is specified, read history from file */
  if (cqp_history_file != NULL) {
    /* ignore errors; it's probably just that the history file doesn't exist yet */
    read_history(cqp_history_file);
  }

  /* == the line input loop == */
  while (!exit_cqp) {

    if (input != NULL)
      cl_free(input);

    if (highlighting) {
      printf("%s", get_typeface_escape('n')); /* work around 'bug' in less which may not switch off display attributes when user exits */
      fflush(stdout);
    }

    if (silent)
      input = readline(NULL);
    else {
      if (current_corpus != NULL) {
        /* don't use terminal colours for the prompt because they mess up readline's formatting */
        if (CL_STREQ(current_corpus->name, current_corpus->mother_name))
          sprintf(prompt, "%s> ", current_corpus->name);
        else
          sprintf(prompt, "%s:%s[%d]> ",
                  current_corpus->mother_name,
                  current_corpus->name,
                  current_corpus->size);
      }
      else
        sprintf(prompt, "[no corpus]> ");

      input = readline(prompt);
    }

    if (input != NULL) {
      /* add semicolon at end of line if missing (also replaces ws-only lines by "") */
      input = ensure_semicolon(input);

      /* add input line to history (unless it's an empty line) */
      if (*input)
        add_history(input);

      /* parse & execute query */
      cqp_parse_string(input);
    }
    else
      exit_cqp = True;                 /* NULL means we've had an EOF character */

    /* reinstall signal handler if necessary */
    if (!signal_handler_is_installed)
      install_signal_handler();
  }

  if (save_on_exit)
    save_unsaved_subcorpora();

  if (!silent)
    printf("\nDone. Share and enjoy!\n");

}
#endif /* USE_READLINE */


/**
 * Main function for the interactive CQP program.
 *
 * Doesn't do much except call the initialisation function,
 * and then one of the loop-and-parse-input functions.
 *
 * @param argc  Number of commandline arguments.
 * @param argv  Pointer to commandline arguments.
 * @return      Return value to OS.
 */
int
main(int argc, char *argv[])
{

  which_app = cqp;

  if (!initialize_cqp(argc, argv)) {
    fprintf(stderr, "Can't initialize CQP\n");
    exit(1);
  }

  /* Test ANSI colours (if CQP was invoked with -C switch) */
  if (use_colour) {
#ifndef __MINGW__
    char *blue = get_colour_escape('b', 1);
    char *green = get_colour_escape('g', 1);
    char *red = get_colour_escape('r', 1);
    char *pink = get_colour_escape('p', 1);
    char *cyanBack = get_colour_escape('c', 0);
    char *greenBack = get_colour_escape('g', 0);
    char *yellowBack = get_colour_escape('y', 0);
    char *bold = get_typeface_escape('b');
    char *underline = get_typeface_escape('u');
    char *standout = get_typeface_escape('s');
    char *normal = get_typeface_escape('n');
    char sc_colour[256];
    int i, j;

    printf("%s%sWelcome%s to %s%sC%s%sQ%s%sP%s -- ", green, bold, normal, red, bold, pink, bold, blue, bold, normal);
    printf("the %s Colourful %s Query %s Processor %s.\n", yellowBack, greenBack, cyanBack, normal);

    for (i = 3; i <= 4; i++) {
      printf("[");
      for (j = 0; j < 8; j++) {
        sprintf(sc_colour, "\x1B[0;%d%dm", i,j);
        printf("%d%d: %sN%s%sB%s%sU%s%sS%s  ",
               i, j,
               sc_colour,
               sc_colour, bold,
               sc_colour, underline,
               sc_colour, standout,
               normal);
      }
      printf("]\n");
    }
#else
    fprintf(stderr, "We're sorry, CQP's Colourful Mode is not available under Windows.\n");
    fprintf(stderr, "CQP will continue as normal without it...\n");
    use_colour = 0;
#endif
  } /* endif use_colour */

  install_signal_handler();

  if (child_process) {
    printf("CQP version " CWB_VERSION "\n");
    fflush(stdout);
  }

  if (batchmode) {
    if (!batchfh)
      fprintf(stderr, "Can't open batch file\n");
    else
      cqp_parse_file(batchfh, 1);  /* batch mode: abort on parse error */
  }
  else {
#ifdef USE_READLINE
    if (use_readline)
      readline_main();
    else
#endif /* USE_READLINE */
      cqp_parse_file(stdin, 0);  /* interactive mode: don't abort on parse error */
  }

  if (macro_debug)
    macro_statistics();

  cleanup_kwic_line_memory();

  return (cqp_error_status != 0);
}



