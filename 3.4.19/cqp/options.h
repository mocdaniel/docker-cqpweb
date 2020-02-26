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

#ifndef _cqp_options_h_
#define _cqp_options_h_

#include "../cl/globals.h"
#include "concordance.h"

/** Flag for CQP configuration options: is this visible interactively in CQP? */
#define OPTION_VISIBLE_IN_CQP    1

/** Default value for the HardBoundary configuration option. */
#define DEFAULT_HARDBOUNDARY 500

/** Default value for the context scope configuration option (counted in characters) */
#define DEFAULT_CONTEXT 25

#define DEFAULT_LOCAL_PATH_ENV_VAR "CQP_LOCAL_CORP_DIR"

#define CQP_FALLBACK_PAGER "more"



enum _which_app { undef, cqp, cqpcl, cqpserver} which_app;
/* this variable is set in the binaries' main() functions */


/* the insecure/inhibit_activation/inhibit_interactives options aren't really needed any more;
 * CGI scripts should use the new query lock mode instead.
 * [ insecure and inhibit_activation are kept for compatibility; inhibit_interactives has been removed. ]
 */
int insecure;                     /**< Boolean: != 0 means we should not allow pipes etc. (For example, in CGI.) */
int inhibit_activation;           /**< Boolean: inhibit corpus activations in parser */


/* debugging options */
int parseonly;                    /**< if true, queries are only parsed, not evaluated. */
int verbose_parser;               /**< if true, absolutely all messages from the parser get printed (inc Message-level). */
int show_symtab;                  /**< Doesn't seem to be used anywhere; should show_environment use it? if not, remove? TODO  */
int show_gconstraints;            /**< if true, the tree of global contraints is printed when an EvalEnvironment is displayed */
int show_evaltree;                /**< if true, the evaluation tree is printed when an EvalEnvironment is displayed */
int show_patlist;                 /**< if true, the pattern list is printed when an EvalEnvironment is displayed */
int show_compdfa;                 /**< if true, the complete DFA is printed when an EvalEnvironment is displayed */
int show_dfa;                     /**< if true, the regex2dfa module will print out the states of the DFA after it is parsed. */
  /* TODO rename the above variable because it is NOT the same as the other show_* variables. regex2dfa_debug? dfa_debug? */
int symtab_debug;                 /**< if this AND debug_simulation are true, print extra messages relating to eval
                                   *   environment labels when simulating an NFA. */
int parser_debug;                 /**< if true, the parser's internal Bison-generated debug setting is turned on. */
int tree_debug;                   /**< if true, extra messages are embedded when an evaluation tree is pretty-printed */
int eval_debug;                   /**< if true, assorted debug messages related to query evaluation are printed */
int search_debug;                 /**< if true, the evaltree of a pattern is pretty-printed before the DFA is created. */
int initial_matchlist_debug;      /**< if true, debug messages relating to the initial set of candidate matches are printed. */
int debug_simulation;             /**< if true, debug messages are printed when simulating an NFA. @see simulate */
int activate_cl_debug;            /**< if true, the CL's debug message setting is set to On. */

/* CQPserver options */
int server_log;                   /**< cqpserver option: logging (print log messages to standard output) */
int server_debug;                 /**< cqpserver option: debugging output (print debug messages to standard error) */
int snoop;                        /**< cqpserver option: monitor CQi network communication */
int private_server;               /**< cqpserver option: makes CQPserver accept a single connection only */
int server_port;                  /**< cqpserver option: CQPserver's listening port (if 0, listens on CQI_PORT) */
int localhost;                    /**< cqpserver option: accept local connections (loopback) only */
int server_quit;                  /**< cqpserver option: spawn server and return to caller (for CQI::Server.pm) */

int query_lock;                   /**< cqpserver option: safe mode for network/HTTP servers (allow query execution only) */
int query_lock_violation;         /**< cqpserver option: set for CQPserver's sake to detect attempted query lock violation */


/* macro options */
int enable_macros;                /**< enable macros only at user request in case they introduce compatibility problems */
int macro_debug;                  /**< enable debugging of macros (and print macro hash stats on shutdown). */

/* query options */
int hard_boundary;                /**< Query option: use implicit 'within' clause (unless overridden by explicit spec) */
int hard_cut;                     /**< Query option: use hard cut value for all queries (cannot be changed) */
int auto_subquery;                /**< Query option: use auto-subquery mode */
char *def_unbr_attr;              /**< Query option: unbracketed attribute (attribute matched by "..." patterns) */
int query_optimize;               /**< Query option: use query optimisation (untested and expensive optimisations) */
int anchor_number_target;         /**< Query option: which marker @0 ... @9 will be mapped to the target anchor */
int anchor_number_keyword;        /**< Query option: which marker @0 ... @9 will be mapped to the keyword anchor */
MatchingStrategy matching_strategy; /**< Query option: which matching strategy to use */

char *matching_strategy_name;     /**< The matching strategy option is implemented as this string option with side-effect of setting the actual MatchingStrategy*/
int strict_regions;               /**< boolean: expression between {s} ... {/s} tags is constrained to single {s} region  */

/* CQP user interface options */
int use_readline;                 /**< UI option: use GNU Readline for input line editing if available */
int highlighting;                 /**< UI option: highlight match / fields in terminal output? (default = yes) */
int paging;                       /**< UI option: activate/deactivate paging of query results */
char *pager;                      /**< UI option: pager program to used for paged kwic display */
char *tested_pager;               /**< UI option: CQP tests if selected pager works & will fall back to "more" if it doesn't */
char *less_charset_variable;      /**< UI option: name of environment variable for controlling less charset (usually LESSCHARSET) */
int use_colour;                   /**< UI option: use colours for terminal output (experimental) */
int progress_bar;                 /**< UI option: show progress bar during query execution */
int pretty_print;                 /**< UI option: pretty-print most of CQP's output (turn off to simplify parsing of CQP output) */
int autoshow;                     /**< UI option: show query results after evaluation (otherwise, just print number of matches) */
int timing;                       /**< UI option: time queries (printed after execution) */

/* kwic display options */
int show_tag_attributes;          /**< kwic option: show values of s-attributes as SGML tag attributes in kwic lines */
int show_targets;                 /**< kwic option: show numbers of target anchors in brackets */
char *printModeString;            /**< kwic option: string of current printmode */
char *printModeOptions;           /**< kwic option: some printing options */
int printNrMatches;               /**< kwic option: -> 'cat' prints number of matches in first line (do we need this?) */
char *printStructure;             /**< kwic option: show annotations of structures containing match */
char *left_delimiter;             /**< kwic option: the match start prefix (defaults to '<') */
char *right_delimiter;            /**< kwic option: the match end suffix   (defaults to '>') */
char *attribute_separator;        /**< kwic option: override standard attribute separator '/' (if not empty string "") */

/* files and directories */
char *registry;                   /**< registry directory */
char *data_directory;            /**< directory where subcorpora are stored (saved & loaded) */
int auto_save;                    /**< automatically save subcorpora */
int save_on_exit;                 /**< save unsaved subcorpora upon exit */
char *cqp_init_file;              /**< changed from 'init_file' because of clash with a # define in {term.h} */
char *macro_init_file;            /**< secondary init file for loading macro definitions (not read if macros are disabled) */
char *cqp_history_file;           /**< filename where CQP command history will be saved */
int write_history_file;           /**< Controls whether CQP command history is written to file */

/* options for non-interactive use */
int batchmode;                    /**< set by -f {file} option (don't read ~/.cqprc, then process input from {file}) */
int silent;                       /**< Disables some messages & warnings (used rather inconsistently).
                                   *   NEW: suppresses cqpmessage() unless it is an error. */
char *default_corpus;             /**< corpus specified with -D {corpus} */
char *query_string;               /**< query specified on command line (-E {string}, cqpcl only) */

/* options which just shouldn't exist */
int UseExternalSorting;           /**< (option which should not exist) use external sorting algorithm */
char *ExternalSortingCommand;     /**< (option which should not exist) external sort command to use */
int UseExternalGrouping;          /**< (option which should not exist) use external grouping algorithm */
char *ExternalGroupingCommand;    /**< (option which should not exist) external group command to use */
int user_level;                   /**< (option which should not exist) user level: 0 == normal, 1 == advanced, 2 == expert) */
int rangeoutput;                  /**< (option which should not exist) */


/**
 * Child process mode (used by Perl interface (CQP.pm) and by CQPweb (cqp.php))
 *  - don't automatically read in user's .cqprc and .cqpmacros
 *  - print CQP version on startup
 *  - now: output blank line after each command -> SHOULD BE CHANGED
 *  - command ".EOL.;" prints special line (``-::-EOL-::-''), which parent can use to recognise end of output
 *  - print message CQP_PARSE_ERROR_LINE i.e. "PARSE ERROR" on STDERR when a parse error occurs (which parent can easily recognise)
 *
 * This global variable is a Boolean: child process mode on or off.
 */
int child_process;






/* some global variables */

/** The global context descriptor. TODO: Is this the only place a hard-allocated, rather than malloc'd, CD is used?*/
ContextDescriptor CD;

int handle_sigpipe;

char *progname;
char *licensee;

FILE *batchfh;

/**
 * Labels for the types of CQP option.
 */
typedef enum _opttype {
  OptInteger, OptString, OptBoolean, OptContext
} OptType;

/**
 * A CQPOption represents a single configuration option for CQP.
 *
 * It does not actually contain the config-option itself; that is held as
 * a global variable somewhere. Instead, it holds metadata about the
 * config-option, including a pointer to the actual variable.
 *
 * Note it's possible to have two CQPOption objects referring to the same
 * actual variable - in this case the two option names in question
 * would be synonymous.
 *
 */
typedef struct _cqpoption {
  char    *opt_abbrev;           /**< Short version of this option's name. */
  char    *opt_name;             /**< Name of this option as referred to in the interactive control syntax */
  OptType  type;                 /**< Data type of this configuration option. */
  void    *address;              /**< Pointer to the actual variable that contains this config option. */
  char    *cdefault;             /**< Default value for this option (string value) */
  int      idefault;             /**< Default value for this option (integer/boolean value) */
  char    *envvar;               /**< The environment variable from which CQP will take a value for this option */
  int      side_effect;          /**< Ref number of the side effect that changing this option has. @see execute_side_effects */
  int      flags;                /**< Flags for this option: the only one currently used is OPTION_VISIBLE_IN_CQP @see OPTION_VISIBLE_IN_CQP */
} CQPOption;


extern CQPOption cqpoptions[];


int find_matching_strategy(const char *s);

int find_option(char *s);

char *set_string_option_value(char *opt_name, char *value);

char *set_integer_option_value(char *opt_name, int value);

char *set_context_option_value(char *opt_name, char *sval, int ival);

void print_option_values();

void print_option_value(int opt);

void parse_options(int argc, char **argv);


#endif
