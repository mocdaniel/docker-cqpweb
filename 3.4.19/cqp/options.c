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
#include <string.h>
#include <stdlib.h>
#include <unistd.h>
#include <ctype.h>

#include <dirent.h>

#include "../cl/cl.h"
#include "../cl/cwb-globals.h"
#include "../cl/ui-helpers.h"
#include "../cl/attributes.h"

#include "options.h"
#include "print-modes.h"
#include "output.h"
#include "corpmanag.h"
#include "concordance.h"

/* these boolean constants SHOULD NOT be used. Present only to make sure old code doesn't break. */
#ifndef True
#define True 1
#endif
#ifndef False
#define False 0
#endif

#define DEFAULT_EXTERNAL_SORTING_COMMAND \
   "sort -k 2 -k 1n "

#define DEFAULT_EXTERNAL_GROUPING_COMMAND \
   "sort %s -k 1,1n -k 2,2n | uniq -c | sort -k 1,1nr -k 2,2n -k 3,3n"

/**
 * Global array of option defintions for CQP.
 */
CQPOption cqpoptions[] = {

  /* Abbr  VariableName           Type        Where to store           String-       Int- Environ Side-  Option
   *                                                                   Default       Def. VarName Effect Flags
   * ----------------------------------------------------------------------------------------------------------*/

  /* debugging options (anything that controls whether or not a message of some kind is printed) */
  { NULL, "VerboseParser",        OptBoolean, &verbose_parser,         NULL,         0,   NULL,   0,     0 },
  { NULL, "ShowSymtab",           OptBoolean, &show_symtab,            NULL,         0,   NULL,   0,     0 },
  { NULL, "ShowGconstraints",     OptBoolean, &show_gconstraints,      NULL,         0,   NULL,   0,     0 },
  { NULL, "ShowEvaltree",         OptBoolean, &show_evaltree,          NULL,         0,   NULL,   0,     0 },
  { NULL, "ShowPatlist",          OptBoolean, &show_patlist,           NULL,         0,   NULL,   0,     0 },
  { NULL, "ShowDFA",              OptBoolean, &show_dfa,               NULL,         0,   NULL,   0,     0 },
  { NULL, "ShowCompDFA",          OptBoolean, &show_compdfa,           NULL,         0,   NULL,   0,     0 },
  { NULL, "SymtabDebug",          OptBoolean, &symtab_debug,           NULL,         0,   NULL,   0,     0 },
  { NULL, "ParserDebug",          OptBoolean, &parser_debug,           NULL,         0,   NULL,   0,     0 },
  { NULL, "TreeDebug",            OptBoolean, &tree_debug,             NULL,         0,   NULL,   0,     0 },
  { NULL, "EvalDebug",            OptBoolean, &eval_debug,             NULL,         0,   NULL,   0,     0 },
  { NULL, "InitialMatchlistDebug",OptBoolean, &initial_matchlist_debug,NULL,         0,   NULL,   0,     0 },
  { NULL, "DebugSimulation",      OptBoolean, &debug_simulation,       NULL,         0,   NULL,   0,     0 },
  { NULL, "SearchDebug",          OptBoolean, &search_debug,           NULL,         0,   NULL,   0,     0 },
  { NULL, "ServerLog",            OptBoolean, &server_log,             NULL,         1,   NULL,   0,     0 },
  { NULL, "ServerDebug",          OptBoolean, &server_debug,           NULL,         0,   NULL,   0,     0 },
  { NULL, "Snoop",                OptBoolean, &snoop,                  NULL,         0,   NULL,   0,     0 },
  { NULL, "ParseOnly",            OptBoolean, &parseonly,              NULL,         0,   NULL,   0,     0 },
  { NULL, "Silent",               OptBoolean, &silent,                 NULL,         0,   NULL,   0,     0 },
  { NULL, "ChildProcess",         OptBoolean, &child_process,          NULL,         0,   NULL,   0,     0 },
  { NULL, "MacroDebug",           OptBoolean, &macro_debug,            NULL,         0,   NULL,   0,     0 },
  { NULL, "CLDebug",              OptBoolean, &activate_cl_debug,      NULL,         0,   NULL,   4,     0 },

  /* "secret" internal options */
  { NULL, "PrintNrMatches",       OptInteger, &printNrMatches,         NULL,         0,   NULL,   0,     0 },

  { "eg", "ExternalGroup",        OptBoolean, &UseExternalGrouping,    NULL,         0,   NULL,   0,     0 },
  { "egc","ExternalGroupCommand", OptString,  &ExternalGroupingCommand,NULL,         0,   NULL,   0,     0 },
  { "lcv", "LessCharsetVariable", OptString,  &less_charset_variable,  "LESSCHARSET",0,   NULL,   0,     0 },

  /* options set by command-line flags */
  { NULL, "Readline",             OptBoolean, &use_readline,           NULL,         0,   NULL,   0,     0 },
  { "dc", "DefaultCorpus",        OptString,  &default_corpus,         NULL,         0,   NULL,   0,     0 },
  { "hb", "HardBoundary",         OptInteger, &hard_boundary,          NULL,         DEFAULT_HARDBOUNDARY,
                                                                                          NULL,   0,     0 },
  { "hc", "HardCut",              OptInteger, &hard_cut,               NULL,         0,   NULL,   0,     0 },
  { "ql", "QueryLock",            OptInteger, &query_lock,             NULL,         0,   NULL,   0,     0 },
  { "m",  "Macros",               OptBoolean, &enable_macros,          NULL,         1,   NULL,   0,     0 },
  { NULL, "UserLevel",            OptInteger, &user_level,             NULL,         0,   NULL,   0,     0 },

  /* Abbr  VariableName           Type        Where to store           String-       Int- Environ Side-  Option
   *                                                                   Default       Def. VarName Effect Flags
   * ----------------------------------------------------------------------------------------------------------*/

  /* now called DataDirectory, but we keep the old name (secretly) for compatibility */
  { "lcd","LocalCorpusDirectory", OptString,  &data_directory,         NULL,         0,   NULL,   2,     0 },

  /* user options: assumed to be settable via the interface (thus the use of the OPTION_VISIBLE_IN_CQP flag that makes them visible). */
  { "r",  "Registry",             OptString,  &registry,               NULL,         0,   CWB_REGISTRY_ENVVAR,
                                                                                                  1,     OPTION_VISIBLE_IN_CQP },
  { "dd", "DataDirectory",        OptString,  &data_directory,         NULL,         0,   DEFAULT_LOCAL_PATH_ENV_VAR,
                                                                                                  2,     OPTION_VISIBLE_IN_CQP },
  { "hf", "HistoryFile",          OptString,  &cqp_history_file,       NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "wh", "WriteHistory",         OptBoolean, &write_history_file,     NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "ms", "MatchingStrategy",     OptString,  &matching_strategy_name, "standard",   0,   NULL,   9,     OPTION_VISIBLE_IN_CQP },
  { "sr", "StrictRegions",        OptBoolean, &strict_regions,         NULL,         1,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "p",  "Paging",               OptBoolean, &paging,                 NULL,         1,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
#ifndef __MINGW__
  { "pg", "Pager",                OptString,  &pager,                  "less -FRX -+S",0, "CQP_PAGER",0, OPTION_VISIBLE_IN_CQP },
  { "h",  "Highlighting",         OptBoolean, &highlighting,           NULL,         1,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
#else
  /* use more as default pager under Windows (because it exists whereas less may not :-P ) */
  { "pg", "Pager",                OptString,  &pager,                  "more",       0,   "CQP_PAGER",0, OPTION_VISIBLE_IN_CQP},
  /* this implies that the default value for highlighting must be "off" under Windows */
  { "h",  "Highlighting",         OptBoolean, &highlighting,           NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
#endif
  { "col","Colour",               OptBoolean, &use_colour,             NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "pb", "ProgressBar",          OptBoolean, &progress_bar,           NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "pp", "PrettyPrint",          OptBoolean, &pretty_print,           NULL,         1,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "c",  "Context",              OptContext, &CD,                     NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "lc", "LeftContext",          OptContext, &CD,                     NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "rc", "RightContext",         OptContext, &CD,                     NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "ld", "LeftKWICDelim",        OptString,  &left_delimiter,         "<",          0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "rd", "RightKWICDelim",       OptString,  &right_delimiter,        ">",          0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "sep","AttributeSeparator",   OptString,  &attribute_separator,    "",           0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "po", "PrintOptions",         OptString,  &printModeOptions,       NULL,         0,   NULL,   8,     OPTION_VISIBLE_IN_CQP },
  { "ps", "PrintStructures",      OptString,  &printStructure,         NULL,         0,   NULL,   7,     OPTION_VISIBLE_IN_CQP },
  { "sta","ShowTagAttributes",    OptBoolean, &show_tag_attributes,    NULL,         1,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "st", "ShowTargets",          OptBoolean, &show_targets,           NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "as", "AutoShow",             OptBoolean, &autoshow,               NULL,         1,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { NULL, "Timing",               OptBoolean, &timing,                 NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "o",  "Optimize",             OptBoolean, &query_optimize,         NULL,         0,   NULL,   3,     OPTION_VISIBLE_IN_CQP },
  { "ant","AnchorNumberTarget",   OptInteger, &anchor_number_target,   NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "ank","AnchorNumberKeyword",  OptInteger, &anchor_number_keyword,  NULL,         1,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "es", "ExternalSort",         OptBoolean, &UseExternalSorting,     NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "esc","ExternalSortCommand",  OptString,  &ExternalSortingCommand, NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "da", "DefaultNonbrackAttr",  OptString,  &def_unbr_attr,          CWB_DEFAULT_ATT_NAME,0,NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { "sub","AutoSubquery",         OptBoolean, &auto_subquery,          NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { NULL, "AutoSave",             OptBoolean, &auto_save,              NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },
  { NULL, "SaveOnExit",           OptBoolean, &save_on_exit,           NULL,         0,   NULL,   0,     OPTION_VISIBLE_IN_CQP },

  /* empty option to terminate array */
  { NULL, NULL,                   OptString,  NULL,                    NULL,         0,   NULL,   0,     0}
};

/**
 * The return value is a newly-allocated string.
 */
char *
expand_filename(char *fname)
{
  char fn[CL_MAX_FILENAME_LENGTH];
  char *home;
  int s, t;

  s = 0;
  t = 0;

  for (s = 0; fname[s]; ) {

    if (fname[s] == '~' && (home = getenv("HOME")) != NULL) {
      int k;

      for (k = 0; home[k]; k++) {
        fn[t] = home[k];
        t++;
      }
      s++;
    }
    else if (fname[s] == '$') {

      /*  reference to the name of another component. */

      int rpos;
      char rname[CL_MAX_LINE_LENGTH];
      char *reference;

      s++;                        /* skip the $ */

      rpos = 0;
      while (isalnum(fname[s]) || fname[s] == '_') {
        rname[rpos++] = fname[s];
        s++;
      }
      rname[rpos] = '\0';

      reference = getenv(rname);

      if (reference == NULL) {
        fprintf(stderr, "options: can't get value of environment variable ``%s''\n", rname);

        fn[t++] = '$';
        reference = &rname[0];
      }

      for (rpos = 0; reference[rpos]; rpos++) {
        fn[t] = reference[rpos];
        t++;
      }
    }
    else {
      fn[t] = fname[s];
      t++; s++;
    }
  }

  fn[t] = '\0';

  return cl_strdup(fn);

}

/**
 * Prints the usage message for the different CQP applications
 * to standard error and then shuts down the program with exit status 1.
 */
void
cqp_usage(void)
{
  switch (which_app) {
  case cqpserver:
    fprintf(stderr, "Usage: %s [options] [<user>:<password> ...]\n", progname);
    break;
  case cqpcl:
    fprintf(stderr, "Usage: %s [options] '<query>'\n", progname);
    break;
  case cqp:
    fprintf(stderr, "Usage: %s [options]\n", progname);
    break;
  default:
    fprintf(stderr, "??? Unknown application ???\n");
    exit(1);
  }
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "    -h           help\n");
  fprintf(stderr, "    -v           version and copyright information\n");
  fprintf(stderr, "    -r dir       use <dir> as default registry\n");
  fprintf(stderr, "    -l dir       store/load subcorpora in <dir>\n");
  fprintf(stderr, "    -I file      read <file> as init file\n");
  fprintf(stderr, "    -M file      read macro definitions from <file>\n");
  fprintf(stderr, "    -m           disable macro expansion\n");
  if (which_app == cqpcl)
    fprintf(stderr, "    -E variable  execute query in $(<variable>)\n");
  if (which_app == cqp) {
    fprintf(stderr, "    -e           enable input line editing\n");
#ifndef __MINGW__
    fprintf(stderr, "    -C           enable ANSI colours (experimental)\n");
#endif
    fprintf(stderr, "    -f filename  execute commands from file (batch mode)\n");
    fprintf(stderr, "    -p           turn pager off\n");
    fprintf(stderr, "    -P pager     use program <pager> to display query results\n");
  }
  if (which_app != cqpserver) {
    fprintf(stderr, "    -s           auto auto_subquery mode\n");
    fprintf(stderr, "    -c           child process mode\n");
    fprintf(stderr, "    -i           print matching ranges only (binary output)\n");
    fprintf(stderr, "    -W num       show <num> chars to the left & right of match\n");
    fprintf(stderr, "    -L num       show <num> chars to the left of match\n");
    fprintf(stderr, "    -R num       show <num> chars to the right of match\n");
  }
  fprintf(stderr, "    -D corpus    set default corpus to <corpus>\n");
  fprintf(stderr, "    -b num       set hard boundary for kleene star to <num> tokens\n");
  fprintf(stderr, "    -S           SIG_PIPE handler toggle\n");
  fprintf(stderr, "    -x           insecure mode (when run SETUID)\n");
  if (which_app == cqpserver) {
    fprintf(stderr, "    -1           single client server (exits after 1 connection)\n");
    fprintf(stderr, "    -P  port     listen on port #<port> [default=CQI_PORT]\n");
    fprintf(stderr, "    -L           accept connections from localhost only (loopback)\n");
    fprintf(stderr, "    -q           fork() and quit before accepting connections\n");
  }
  fprintf(stderr, "    -d mode      activate/deactivate debug mode, where <mode> is one of: \n");
  fprintf(stderr, "       [ ShowSymtab, ShowPatList, ShowEvaltree, ShowDFA, ShowCompDFA,   ]\n");
  fprintf(stderr, "       [ ShowGConstraints, SymtabDebug, TreeDebug, CLDebug,             ]\n");
  fprintf(stderr, "       [ EvalDebug, InitialMatchlistDebug, DebugSimulation,             ]\n");
  fprintf(stderr, "       [ VerboseParser, ParserDebug, ParseOnly, SearchDebug, MacroDebug ]\n");
  if (which_app == cqpserver) {
    fprintf(stderr, "       [ ServerLog [on], ServerDebug, Snoop (log all network traffic)   ]\n");
  }
  fprintf(stderr, "       [ ALL (activate all modes except ParseOnly)                      ]\n");
  fprintf(stderr, "\n");
  exit(1);
}

/**
 * Print the value of a CQP option to stdout.
 * @param opt  Index in the global array of the option to print.
 */
void
print_option_value(int opt)
{
  int show_lc_rc = 0;                /* "set context;" should also display left and right context settings */

  if (cqpoptions[opt].opt_abbrev != NULL)
    printf("[%s]\t", cqpoptions[opt].opt_abbrev);
  else
    printf("\t");
  printf("%-22s", cqpoptions[opt].opt_name);

  if (cqpoptions[opt].address != NULL) {

    printf("=  ");
    switch (cqpoptions[opt].type) {
    case OptString:
      if (strcasecmp(cqpoptions[opt].opt_name, "PrintOptions") == 0) {
        printf("%ctbl %chdr %cwrap %cbdr %cnum",
               GlobalPrintOptions.print_tabular ? '+' : '-',
               GlobalPrintOptions.print_header  ? '+' : '-',
               GlobalPrintOptions.print_wrap    ? '+' : '-',
               GlobalPrintOptions.print_border  ? '+' : '-',
               GlobalPrintOptions.number_lines  ? '+' : '-');
      }
      else if (*((char **)cqpoptions[opt].address)) {
        if (strcasecmp(cqpoptions[opt].opt_name, "AttributeSeparator") == 0 && **((char **)cqpoptions[opt].address) == '\0')
          printf("<default>");
        else
          printf("%s", *((char **)cqpoptions[opt].address));
      }
      else
        printf("<no value>");
      break;

    case OptBoolean:
      printf((*((int *)cqpoptions[opt].address)) ? "yes" : "no");
      break;

    case OptInteger:
      printf("%d", *((int *)cqpoptions[opt].address));
      break;

    case OptContext:
      if (strcasecmp(cqpoptions[opt].opt_name, "Context") == 0) {
        printf("(see below)");
        show_lc_rc = 1;
      }
      else if (strcasecmp(cqpoptions[opt].opt_name, "LeftContext") == 0) {
        printf("%d ", ((ContextDescriptor *)cqpoptions[opt].address)->left_width);

        switch (((ContextDescriptor *)cqpoptions[opt].address)->left_type) {
        case STRUC_CONTEXT:
        case ALIGN_CONTEXT:
          printf("%s",
                 ((ContextDescriptor *)cqpoptions[opt].address)->left_structure_name ?
                 ((ContextDescriptor *)cqpoptions[opt].address)->left_structure_name :
                 "(empty?)");
          break;

        case CHAR_CONTEXT:
          printf("characters");
          break;

        case WORD_CONTEXT:
          printf("words");
          break;

        default:
          assert(0 && "Can't be");
        }
      }
      else if (strcasecmp(cqpoptions[opt].opt_name, "RightContext") == 0) {
        printf("%d ", ((ContextDescriptor *)cqpoptions[opt].address)->right_width);

        switch (((ContextDescriptor *)cqpoptions[opt].address)->right_type) {
        case STRUC_CONTEXT:
        case ALIGN_CONTEXT:
          printf("%s",
                 ((ContextDescriptor *)cqpoptions[opt].address)->right_structure_name ?
                 ((ContextDescriptor *)cqpoptions[opt].address)->right_structure_name :
                 "(empty?)");
          break;

        case CHAR_CONTEXT:
          printf("characters");
          break;

        case WORD_CONTEXT:
          printf("words");
          break;

        default:
          assert(0 && "Can't be");
        }
      }
      else {
        assert (0 && "Unknown option of type OptContext ???");
      }
      break;

    default:
      printf("WARNING: Illegal Option Type!");
      break;
    }

  }
  else
    /* no address given for option -> this is only LeftContext and RightContext in
       normal mode, so refer people to the Context option */
    printf("<not bound to variable>");

  printf("\n");

  if (show_lc_rc) {
    print_option_value(find_option("LeftContext"));
    print_option_value(find_option("RightContext"));
  }
}

/**
 * Prints out the values of all the CQP configuration options.
 */
void
print_option_values()
{
  int opt;
  int lc_opt = find_option("LeftContext"); /* left and right context are automatically shown together with context option */
  int rc_opt = find_option("RightContext");

  if (!silent)
    printf("Variable settings:\n");

  for (opt = 0; cqpoptions[opt].opt_name; opt++)
    if ( (cqpoptions[opt].flags & OPTION_VISIBLE_IN_CQP) || user_level >= 1)
      if (opt != lc_opt && opt != rc_opt)
        print_option_value(opt);
}

/**
 * Sets all the CQP options to their default values.
 */
void
set_default_option_values(void)
{
  int i;
  char *env;

  /* 6502 Assembler was not that bad compared to this ... */

  for (i = 0; cqpoptions[i].opt_name != NULL; i++) {

    if (cqpoptions[i].address) {

      switch(cqpoptions[i].type) {
      case OptString:

        *((char **)cqpoptions[i].address) = NULL;
        /* try environment variable first */
        if (cqpoptions[i].envvar != NULL) {
          env = getenv(cqpoptions[i].envvar);
          if (env != NULL)
            *((char **)cqpoptions[i].address) = cl_strdup((char *)getenv(cqpoptions[i].envvar));
        }
        /* otherwise, use internal default if specified */
        if (*((char **)cqpoptions[i].address) == NULL) {
          if (cqpoptions[i].cdefault)
            *((char **)cqpoptions[i].address) = cl_strdup(cqpoptions[i].cdefault);
          else
            *((char **)cqpoptions[i].address) = NULL;
        }
        break;

      case OptInteger:
      case OptBoolean:

        if (cqpoptions[i].envvar != NULL)
          *((int *)cqpoptions[i].address) = (getenv(cqpoptions[i].envvar) == NULL)
            ? cqpoptions[i].idefault
            : atoi(getenv(cqpoptions[i].envvar));
        else
          *((int *)cqpoptions[i].address) = cqpoptions[i].idefault;
        break;

      default:
        break;
      }
    }
  }

  query_string = NULL;
  cqp_init_file = NULL;
  macro_init_file = 0;
  inhibit_activation = 0;
  handle_sigpipe = 1;

  initialize_context_descriptor(&CD);
  CD.left_width = DEFAULT_CONTEXT;
  CD.left_type  = CHAR_CONTEXT;
  CD.right_width = DEFAULT_CONTEXT;
  CD.right_type  = CHAR_CONTEXT;

  CD.print_cpos = 1;

  /* TODO: should the following be scrubbed at some point? */
  ExternalSortingCommand  = cl_strdup(DEFAULT_EXTERNAL_SORTING_COMMAND);
  ExternalGroupingCommand = cl_strdup(DEFAULT_EXTERNAL_GROUPING_COMMAND);

  /* CQPserver options */
  private_server = 0;
  server_port = 0;
  server_quit = 0;
  localhost = 0;

  matching_strategy = standard_match;  /* unfortunately, this is not automatically derived from the defaults */

  tested_pager = NULL;                 /* this will be set to the PAGER command if that can be successfully run */


  /* execute some side effects for default values */
  cl_set_debug_level(activate_cl_debug);
  cl_set_optimize(query_optimize);
}


/**
 * Look up matching strategy by name.
 *
 * Returns the appropriate enum code for the selected matching strategy or -1 if the argument is invalid.
 *
 * @param s  Name of the desired matching strategy (case insensitive)
 * @return   Code of the matching strategy, or -1 if s isn't a recognized matching strategy name.
 *           The return value can be assigned to enum type matching_strategy unless it is -1.
 */
int
find_matching_strategy(const char *s)
{
  if (strcasecmp(s, "traditional") == 0)
    return traditional;

  else if (strcasecmp(s, "shortest") == 0)
    return shortest_match;

  else if (strcasecmp(s, "standard") == 0)
    return standard_match;

  else if (strcasecmp(s, "longest") == 0)
    return longest_match;

  else {
    printf("invalid matching strategy: %s\n", s);
    return -1;
  }
}

/**
 * Finds the index of an option.
 *
 * Return the index in the global options array of the option with name
 * s. This should be never called from outside.
 *
 * @see      cqpoptions
 * @param s  Name of the option to find (or abbreviation); matched case-insensitively.
 * @return   Index of element in cqpoptions corresponding to the name s,
 *           or -1 if no corresponding element was found.
 */
int find_option(char *s)
{
  int i;

  for (i = 0; cqpoptions[i].opt_name != NULL; i++)
    if (strcasecmp(cqpoptions[i].opt_name, s) == 0)
      return i;

  /* if no option of given name was found, try abbrevs */
  for (i = 0; cqpoptions[i].opt_name != NULL; i++)
    if ((cqpoptions[i].opt_abbrev != NULL) && (strcasecmp(cqpoptions[i].opt_abbrev, s) == 0))
      return i;

  return -1;
}


/**
 * Carries out any "side effects" of setting an option.
 *
 * @param opt  The option that has just been set (index into the cqpoptions array).
 */
void
execute_side_effects(int opt)
{
  int code;

  switch (cqpoptions[opt].side_effect) {

  case 0:  /* <no side effect> */
    break;
  case 1:  /* set Registry "..."; */
    check_available_corpora(SYSTEM);
    break;
  case 2:  /* set DataDirectory "..."; */
    check_available_corpora(SUB);
    /* this is why setting DataDirectory makes the active corpus be de-activated. */
    break;
  case 3:  /* set Optimize (on | off); */
    cl_set_optimize(query_optimize); /* enable / disable CL optimisations, too */
    break;
  case 4:  /* set CLDebug (on | off); */
    cl_set_debug_level(activate_cl_debug); /* enable / disable CL debugging */
    break;

    /* slot 5 is free */

  case 6:  /* set PrintMode (ascii | sgml | html | latex); */
    if (printModeString == NULL || strcasecmp(printModeString, "ascii") == 0)
      GlobalPrintMode = PrintASCII;
    else if (strcasecmp(printModeString, "sgml") == 0)
      GlobalPrintMode = PrintSGML;
    else if (strcasecmp(printModeString, "html") == 0)
      GlobalPrintMode = PrintHTML;
    else if (strcasecmp(printModeString, "latex") == 0)
      GlobalPrintMode = PrintLATEX;
    else {
      cqpmessage(Error, "USAGE: set PrintMode (ascii | sgml | html | latex);");
      GlobalPrintMode = PrintASCII;
      cl_free(printModeString);
      printModeString = cl_strdup("ascii");
    }
    break;

  case 7:  /* set PrintStructures "..."; */
    if (CD.printStructureTags) {
      DestroyAttributeList(&CD.printStructureTags);
    }
    CD.printStructureTags = ComputePrintStructures(current_corpus);
    break;

  case 8:  /* set PrintOptions "...."; */
    ParsePrintOptions();
    break;

  case 9:  /* set MatchingStrategy ( traditional | shortest | standard | longest ); */
    code = find_matching_strategy(matching_strategy_name);
    if (code < 0) {
      cqpmessage(Error, "USAGE: set MatchingStrategy (traditional | shortest | standard | longest);");
      matching_strategy = standard_match;
      cl_free(matching_strategy_name);
      matching_strategy_name = strdup("standard");
    }
    else
      matching_strategy = code;
    break;

  default:
    fprintf(stderr, "Unknown side-effect #%d invoked by option %s.\n", cqpoptions[opt].side_effect, cqpoptions[opt].opt_name);
    assert(0 && "Aborted. Please contact technical support.");
  }
}

static int
validate_integer_option_value(int opt, int value)
{
  char *optname = cqpoptions[opt].opt_name;
  int is_ant = (strcmp(optname, "AnchorNumberTarget") == 0);
  int is_ank = (strcmp(optname, "AnchorNumberKeyword") == 0);
  int tmp;

  if (is_ant || is_ank) {
    if (value < 0 || value > 9) {
      cqpmessage(Warning, "set %s must be integer in range 0 .. 9", optname);
      return 0;
    }
    tmp = is_ant ? anchor_number_keyword : anchor_number_target;
    if (value == tmp) {
      cqpmessage(Warning, "set %s must be different from %s (= %d)", optname, (is_ant ? "AnchorNumberKeyword" : "AnchorNumberTarget"), tmp);
      return 0;
    }
  }
  return 1;
}


static int
validate_string_option_value(int opt, char *value)
{
#ifdef __NEVER__
  switch (opt) {
  case 1:                 /* registry */
    /* test whether the directory exists and is readable */
    {
      DIR *dp;

      fprintf(stderr, "Validating ... %s\n", value);

      if ((dp = opendir(value)) != NULL) {
        closedir(dp);
        return 1;
      }
      else {
        perror(value);
        return 0;
      }
    }
    break;

  case 2:                 /* localcorpusdirectory */
    /* test whether the directory exists and is readable */
    {
      DIR *dp;

      if ((dp = opendir(value)) != NULL) {
        closedir(dp);
        return 1;
      }
      else {
        perror(value);
        return 0;
      }
    }
    break;

  default:
    return 1;
  }
#endif

  return 1;
}

/**
 * Sets a string-valued option.
 *
 * An error string (function-internal constant, do NOT free)
 * is returned if the type of the option does not correspond to
 * the function which is called. Upon success, NULL is returned.
 *
 * set_string_option_value does NOT strdup the value!
 *
 * @param opt_name  The name of the option to set.
 * @param value     Its new value.
 * @return          NULL if all OK; otherwise a string describing the problem.
 */
char *
set_string_option_value(char *opt_name, char *value)
{
  int opt = find_option(opt_name);

  if (opt < 0)
    return "No such option";
  else if (cqpoptions[opt].type == OptContext)
    return set_context_option_value(opt_name, value, 1);
  else if (cqpoptions[opt].type != OptString)
    return "Wrong option type (tried to set integer-valued variable to string value)";
  else if (validate_string_option_value(opt, value)) {

    /* free the old value */
    if (*((char **)cqpoptions[opt].address))
      cl_free(*((char **)cqpoptions[opt].address));

    if (CL_STREQ(cqpoptions[opt].opt_name, "Registry") || CL_STREQ(cqpoptions[opt].opt_name, "LocalCorpusDirectory")) {
      *((char **)cqpoptions[opt].address) = expand_filename(value);
      cl_free(value);
    }
    else
      *((char **)cqpoptions[opt].address) = value;

    execute_side_effects(opt);
    return NULL;
  }
  else
    return "Illegal value for this option";
}


/**
 * Sets an integer-valued option.
 *
 * An error string (function-internal constant, do NOT free)
 * is returned if the type of the option does not correspond to
 * the function which is called. Upon success, NULL is returned.
 *
 * @param opt_name  The name of the option to set.
 * @param value     Its new value.
 * @return          NULL if all OK; otherwise a string describing the problem.
 */
char *
set_integer_option_value(char *opt_name, int value)
{
  int opt = find_option(opt_name);

  if (opt < 0)
    return "No such option";

  else if (cqpoptions[opt].type == OptContext)
    return set_context_option_value(opt_name, NULL, value);

  else if ((cqpoptions[opt].type != OptInteger) && (cqpoptions[opt].type != OptBoolean))
    return "Wrong option type (tried to set string-valued variable to integer value)";

  else if (validate_integer_option_value(opt, value)) {
    *((int *)cqpoptions[opt].address) = value;
    execute_side_effects(opt);
    return NULL;
  }

  else
    return "Illegal value for this option";
}


/* TODO following docblock doesn't actually match the function!!! */
/* these two set integer or string-valued options. An error string
 * is returned if the type of the option does not correspond to
 * the function which is called. Upon success, NULL is returned.
 *
 * @param opt_name  The name of the option to set.
 * @param sval      String value.
 * @param ival      Integer value.
 * @return          NULL if all OK; otherwise a string describing the problem.
 */
char *
set_context_option_value(char *opt_name, char *sval, int ival)
{
  int context_type;

  int opt = find_option(opt_name);

  if (opt < 0)
    return "No such option";

  else if (cqpoptions[opt].type == OptContext) {
    if (sval == NULL ||
        strcasecmp(sval, "character") == 0 ||
        strcasecmp(sval, "char") == 0 ||
        strcasecmp(sval, "chars") == 0 ||
        strcasecmp(sval, "characters") == 0)
      context_type = CHAR_CONTEXT;
    else if (strcasecmp(sval, "word") == 0 ||
             strcasecmp(sval, "words") == 0)
      context_type = WORD_CONTEXT;
    else
      context_type = STRUC_CONTEXT;

    if ((strcasecmp(opt_name, "LeftContext") == 0)
        || (strcasecmp(opt_name, "lc") == 0)) {

      CD.left_structure = NULL;
      CD.left_type = context_type;
      CD.left_width = ival;
      cl_free(CD.left_structure_name);
      if (context_type == STRUC_CONTEXT) {
        CD.left_structure_name = cl_strdup(sval);
      }
    }
    else if ((strcasecmp(opt_name, "RightContext") == 0)
             || (strcasecmp(opt_name, "rc") == 0)) {

      CD.right_structure = NULL;
      CD.right_type = context_type;
      CD.right_width = ival;
      cl_free(CD.right_structure_name);
      if (context_type == STRUC_CONTEXT) {
        CD.right_structure_name = cl_strdup(sval);
      }
    }
    else if ((strcasecmp(opt_name, "Context") == 0)
             || (strcasecmp(opt_name, "c") == 0)) {

      CD.left_structure = NULL;
      CD.left_type = context_type;
      CD.left_width = ival;
      cl_free(CD.left_structure_name);
      if (context_type == STRUC_CONTEXT) {
        CD.left_structure_name = cl_strdup(sval);
      }

      CD.right_structure = NULL;
      CD.right_type = context_type;
      CD.right_width = ival;
      cl_free(CD.right_structure_name);
      if (context_type == STRUC_CONTEXT) {
        CD.right_structure_name = cl_strdup(sval);
      }
    }
    else
      return "Illegal value for this option/??";

    execute_side_effects(opt);

    return NULL;
  }
  else
   return "Illegal value for this option";
}

/**
 * Parses program options and sets their default values.
 *
 * @param ac  The program's argc.
 * @param av  The program's argv.
 */
void
parse_options(int ac, char *av[])
{
  extern char *optarg;
  /* extern optind and opterr unused, so don't declare them to keep gcc from complaining */

  int c;
  int opt;
  char *valid_options = "";        /* set these depending on application */

  insecure = 0;

  progname = av[0];
  licensee =
    "\n"
    "The IMS Open Corpus Workbench (CWB)\n"
    "\n"
    "Copyright (C) 1993-2006 by IMS, University of Stuttgart\n"
    "Original developer:       Oliver Christ\n"
    "    with contributions by Bruno Maximilian Schulze\n"
    "Version 3.0 developed by: Stefan Evert\n"
    "    with contributions by Arne Fitschen\n"
    "\n"
    "Copyright (C) 2007-today by the CWB open-source community\n"
    "    individual contributors are listed in source file AUTHORS\n"
    "\n"
    "Download and contact: http://cwb.sourceforge.net/\n"
#ifdef COMPILE_DATE
    "\nCompiled:  " COMPILE_DATE
#endif
#ifdef CWB_VERSION
    "\nVersion:   " CWB_VERSION
#endif
    "\n";

  set_default_option_values();
  switch (which_app) {
  case cqp:
    valid_options = "+b:cCd:D:ef:FhiI:l:L:mM:pP:r:R:sSvW:x";
    break;
  case cqpcl:
    valid_options = "+b:cd:D:E:FhiI:l:L:mM:r:R:sSvW:x";
    break;
  case cqpserver:
    valid_options = "+1b:d:D:FhI:l:LmM:P:qr:Svx";
    break;
  default:
    cqp_usage();
    /* this will display the 'unknown application' message */
  }

  while (EOF != (c = getopt(ac, av, valid_options)))
    switch (c) {

    case '1':
      private_server = 1;
      break;

    case 'q':
      server_quit = 1;
      break;

    case 'x':
      insecure++;
      break;

    case 'C':
      use_colour++;
      break;

    case 'p':
      paging = 0;
      break;

    case 'D':
      default_corpus = cl_strdup(optarg);
      break;

    case 'E':
      if (!(query_string = getenv(optarg))) {
        fprintf(stderr, "Environment variable %s has no value, exiting\n", optarg);
        exit(1);
      }
      break;

    case 'r':
      registry = cl_strdup(optarg);
      break;

    case 'l':
      data_directory = cl_strdup(optarg);
      break;

    case 'F':
      inhibit_activation++;
      break;

    case 'I':
      cqp_init_file = optarg;
      break;

    case 'm':
      enable_macros = 0;        /* -m = DISABLE macros */
      break;

    case 'M':
      macro_init_file = optarg;
      break;

    case 'P':
      if (which_app == cqpserver)        /* this option used in different ways by cqp & cqpserver */
        server_port = atoi(optarg);
      else
        pager = cl_strdup(optarg);
      break;

    case 'd':
      if (!silent) {
        opt = find_option(optarg);

        if ((opt >= 0) && (cqpoptions[opt].type == OptBoolean)) {
          /* TOGGLE the default value */
          *((int *)cqpoptions[opt].address) = cqpoptions[opt].idefault ? 0 : 1;
          execute_side_effects(opt);
        }
        else if (strcmp(optarg, "ALL") == 0) {
          /* set the debug values */
          verbose_parser = show_symtab = show_gconstraints =
            show_evaltree = show_patlist = show_dfa = show_compdfa =
            symtab_debug = parser_debug = eval_debug =
            initial_matchlist_debug = debug_simulation =
            search_debug = macro_debug = activate_cl_debug =
            server_debug = server_log = snoop = True;
          /* execute side effect for CLDebug option */
          cl_set_debug_level(activate_cl_debug);
        }
        else {
          fprintf(stderr, "Invalid debug mode: -d %s\nType '%s -h' for more information.\n", optarg, progname);
          exit(1);
        }
      }
      break;

    case 'h':
      cqp_usage();
      break;

    case 'v':
      printf("%s\n", licensee);
      exit(0);

    case 's':
      auto_subquery = 1;
      break;

    case 'S':
      if (handle_sigpipe)
        handle_sigpipe = 0;
      else
        handle_sigpipe++;
      break;

    case 'W':
      CD.left_width = CD.right_width = atoi(optarg);
      execute_side_effects(3);
      break;

    case 'L':
      if (which_app == cqpserver)        /* used in different ways by cqpserver & cqp/cqpcl */
        localhost++;                        /* takes no arg with cqpserver */
      else
        CD.left_width = atoi(optarg);
      break;

    case 'R':
      CD.right_width = atoi(optarg);
      break;

    case 'b':
      hard_boundary = atoi(optarg);
      break;

    case 'i':
      silent = rangeoutput = True;
      verbose_parser = show_symtab
        = show_gconstraints = show_evaltree
        = show_patlist = symtab_debug
        = parser_debug = eval_debug
        = search_debug = False;
      /* cf. options.h; there are more debug vars than this now, should they all be set to false? */
      break;

    case 'c':
      silent = child_process = True;
      paging = highlighting = False;
      autoshow = auto_save = False;
      progress_bar_child_mode(1);
      /* TODO: would it be useful to set PrettyPrint automatically to "off" in child mode? */
      break;

    case 'e':
      use_readline = True;
      break;

    case 'f':
      silent = batchmode = True;
      verbose_parser = show_symtab = show_gconstraints =
        show_dfa = show_compdfa =
        show_evaltree = show_patlist =
        symtab_debug = parser_debug = eval_debug = search_debug = False;
      /* note that cl_open_stream() handles the case where the filename is "-" for stdin */
      if (!(batchfh = cl_open_stream(optarg, CL_STREAM_READ, CL_STREAM_MAGIC))) {
        perror(optarg);
        exit(1);
      }
      break;

    default:
      fprintf(stderr, "Invalid option. Type '%s -h' for more information.\n", progname);
      exit(1);
      break;
    }
}
