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
 * This file contains the API for the CWB "Corpus Library" (CL).
 *
 * If you are programming against the CL, you should #include ONLY this header file,
 * and make use of ONLY the functions declared here.
 *
 * Other functions in the CL should ONLY be used within the CWB itself by CWB developers.
 *
 * The header file is laid out in such a way as to semi-document the API, i.e. function
 * prototypes are given with brief notes on usage, parameters, and return values. You may
 * also wish to refer to CWB's automatically-generated HTML code documentation (created
 * using the Doxygen system; if you're reading this text in a web browser, then the
 * auto-generated documentation is almost certainly what you're looking at). However,
 * please note that the auto-generated documentation ALSO covers (a) functions internal to
 * the CL which should NOT be used when programming against it; (b) functions from the CWB
 * utilities and from the CQP program - neither of which are part of the CL. There is also
 * no distinction in that more extensive documentation between information that is
 * relevant to programming against the CL API and information that is relevant to
 * developers working on the CL itself. Caveat lector.
 *
 * Note that many functions have two names -- one that follows the standardised format
 * "cl_do_something()", and another that follows no particular pattern. The former
 * are the "new API" (in v3.0.0 or higher of CWB) and the latter are the "old-style" API
 * (deprecated, but supported for backward compatibility). The old-style function names
 * SHOULD NOT be used in newly-written code. Such double names mostly exist for the core
 * data-access functions (i.e. for the Corpus and (especially) Attribute objects).
 *
 * In v3.0 and v3.1 of CWB, the new API was implemented as macros to the old API.
 * As of v3.2, the old API is implemented as macros to the new API.
 *
 * In a very few cases, the parameter list or return behaviour of a function also changed.
 * In this case, a function with the "old" parameter list is preserved (but depracated)
 * and has the same name as the new function but with the suffix "_oldstyle". The old
 * names are then re-implemented as macros to the _oldstyle functions. But, as should be
 * obvious, while these functions and the macros to them will remain in the public API
 * for backwards-compatibility, they should not be used in new code, and are most
 * definitely deprecated!
 *
 * The CL header is organised to reflect the conceptual structure of the library. While
 * it is not fully "object-oriented" in style most of the functions are organised around
 * a small number of data objects that represent real entities in a CWB-encoded corpus.
 * Each object is defined as an opaque type (usually a structure whose members are
 * PRIVATE and should only be accessed via the functions provided in the CL API).
 *
 * CONTENTS LIST FOR THIS HEADER FILE:
 *
 * SECTION 1          CL UTILITIES
 *
 *   1.1                ERROR HANDLING
 *
 *   1.2                MEMORY MANAGEMENT
 *
 *   1.3                DATA LIST CLASSES: cl_string_list AND cl_int_list
 *
 *   1.4                INTERNAL RANDOM NUMBER GENERATOR
 *
 *   1.5                SETTING CL CONFIG VARIABLES
 *
 *   1.6                CONSTANTS
 *
 *   1.7                MISCELLANEOUS UTILITIES
 *
 * SECTION 2          THE CORE CL LIBRARY (DATA ACCESS)
 *
 *   2.1                THE Corpus OBJECT
 *
 *   2.2                THE Attribute OBJECT
 *
 *   2.3                THE PositionStream OBJECT
 *
 * SECTION 3          SUPPORT CLASSES
 *
 *   3.1                THE CorpusProperty OBJECT
 *
 *   3.2                THE CorpusCharset OBJECT
 *
 *   3.3                THE CL_Regex OBJECT
 *
 *   3.4                THE cl_lexhash OBJECT
 *
 *   3.5                THE cl_ngram_hash OBJECT
 *
 * SECTION 4          THE OLD CL API
 *
 * (If you're looking at the auto-generated HTML documentation, this contents list,
 * which describes the structure of the actual "cl.h" header file, is wrong for you -
 * instead, use the index of links (above) to find the object or function
 * you are interested in.)
 *
 * We hope you enjoy using the CL!
 *
 * best regards from
 *
 * The CWB Development Team
 *
 * <a href="http://cwb.sourceforge.net">http://cwb.sourceforge.net</a>
 *
 */




/* The actual code of the header file begins here. */

#ifndef _cwb_cl_h
#define _cwb_cl_h

#include <stdlib.h>                 /* for size_t */
#include <stdio.h>                  /* for FILE * */


/*
 *
 * SECTION 1 -- CL UTILITIES
 *
 */

/*
 *
 * SECTION 1.1 -- ERROR HANDLING
 *
 * (error values and related functions)
 *
 */

/* Error Codes. Note that "CDA" stands for "CL data access". */
#define CDA_OK           0        /**< Error code: everything is fine; actual error values are all less than 0 */
#define CDA_ENULLATT    -1        /**< Error code: NULL passed as attribute argument */
#define CDA_EATTTYPE    -2        /**< Error code: function was called on illegal attribute */
#define CDA_EIDORNG     -3        /**< Error code: id out of range */
#define CDA_EPOSORNG    -4        /**< Error code: position out of range */
#define CDA_EIDXORNG    -5        /**< Error code: index out of range */
#define CDA_ENOSTRING   -6        /**< Error code: no such string encoded */
#define CDA_EPATTERN    -7        /**< Error code: illegal pattern */
#define CDA_ESTRUC      -8        /**< Error code: no structure at position */
#define CDA_EALIGN      -9        /**< Error code: no alignment at position */
#define CDA_EREMOTE     -10       /**< Error code: error in remote access */
#define CDA_ENODATA     -11       /**< Error code: can't load/create necessary data */
#define CDA_EARGS       -12       /**< Error code: error in arguments for dynamic call or CL function */
#define CDA_ENOMEM      -13       /**< Error code: memory fault [unused] */
#define CDA_EOTHER      -14       /**< Error code: other error */
#define CDA_ENYI        -15       /**< Error code: not yet implemented */
#define CDA_EBADREGEX   -16       /**< Error code: bad regular expression */
#define CDA_EFSETINV    -17       /**< Error code: invalid feature set format */
#define CDA_EBUFFER     -18       /**< Error code: buffer overflow (hard-coded internal buffer sizes) */
#define CDA_EINTERNAL   -19       /**< Error code: internal data consistency error (really bad) */
#define CDA_EACCESS     -20       /**< Error code: insufficient access permissions */
#define CDA_EPOSIX      -21       /**< Error code: POSIX-level error: check errno or perror() */
#define CDA_CPOSUNDEF   INT_MIN   /**< Error code: undefined corpus position (use this code to avoid ambiguity with negative cpos) */

/* a global variable which will always be set to one of the above constants! */
extern int cl_errno;

/** A macro which collapses cl_errno's values to a bool: is everything OK or not? true/false respectively. TODO should be cl_all_ok() for consistenmcy a la cl_free */
#define CL_ALL_OK() (cl_errno == CDA_OK)

/* error handling functions */
void cl_error(char *message);
char *cl_error_string(int error_num);




/*
 *
 * SECTION 1.2 -- MEMORY MANAGEMENT
 *
 */

/*
 * easy memory management functions
 *
 * use the following memory allocation functions instead of malloc(), calloc(), realloc(), strdup()
 * in your own programs to invoke the CL's memory manager when necessary
 */
void *cl_malloc(size_t bytes);
void *cl_calloc(size_t nr_of_elements, size_t element_size);
void *cl_realloc(void *block, size_t bytes);
char *cl_strdup(const char *string);
/**
 * Safely frees memory. Has no effect if called on a  NULL pointer.
 *
 * @see cl_malloc
 * @param p  Pointer to memory to be freed.
 */
#define cl_free(p) do { if ((p) != NULL) { free(p); p = NULL; } } while (0)
/* the do {...} while (0) should be safe in 'bare' if..then..else blocks */




/*
 *
 * SECTION 1.3 -- DATA LIST CLASSES: cl_string_list AND cl_int_list
 *
 */

/**
 * Automatically growing list of integers (just what you always need ...)
 */
typedef struct _cl_int_list    *cl_int_list;
/* the cl_int_list object API ...*/
cl_int_list cl_new_int_list(void);                           /* create int list object */
void cl_delete_int_list(cl_int_list l);                      /* delete int list object */
void cl_int_list_lumpsize(cl_int_list l, int s);             /* memory for the list is allocated in "lumps", default size is 64 entries */
int cl_int_list_size(cl_int_list l);                         /* current size of list */
int cl_int_list_get(cl_int_list l, int n);                   /* get value of n-th element in list (0 if out of range) */
void cl_int_list_set(cl_int_list l, int n, int val);         /* set n-th element (automatically extends list) */
void cl_int_list_append(cl_int_list l, int val);             /* append element to list */
void cl_int_list_qsort(cl_int_list l);                       /* sort list (ascending order) */

/**
 * Automatically growing list of strings (just what you always need ...)
 */
typedef struct _cl_string_list *cl_string_list;
/* the cl_string_list object API ...*/
cl_string_list cl_new_string_list(void);                     /* create string list object */
void cl_delete_string_list(cl_string_list l);                /* delete string list object */
void cl_free_string_list(cl_string_list l);                  /* free() all strings in list (use with care!) */
void cl_string_list_lumpsize(cl_string_list l, int s);       /* memory for the list is allocated in "lumps", default size is 64 entries */
int cl_string_list_size(cl_string_list l);                   /* current size of list */
char *cl_string_list_get(cl_string_list l, int n);           /* get value of n-th element in list (NULL if out of range) */
void cl_string_list_set(cl_string_list l, int n, char *val); /* set n-th element (does NOT make copy of string!) */
void cl_string_list_append(cl_string_list l, char *val);     /* append element to list */
void cl_string_list_qsort(cl_string_list l);                 /* sort list (using cl_strcmp()) */



/*
 *
 * SECTION 1.4 -- INTERNAL RANDOM NUMBER GENERATOR
 *
 */

/* built-in random number generator (RNG) */
void cl_set_seed(unsigned int seed);
void cl_randomize(void);
void cl_get_rng_state(unsigned int *i1, unsigned int *i2);
void cl_set_rng_state(unsigned int i1, unsigned int i2);
unsigned int cl_random(void);
double cl_runif(void);




/*
 *
 * SECTION 1.5 -- SETTING CL CONFIG VARIABLES
 *
 */

/*
 *  Functions for setting/getting global CL configuration options
 */
void cl_set_debug_level(int level);       /* 0 = none (default), 1 = some, 2 = all */
int cl_get_debug_level(void);
void cl_set_optimize(int state);          /* 0 = off, 1 = on */
int cl_get_optimize(void);
void cl_set_memory_limit(int megabytes);  /* 0 or less turns limit off */
int cl_get_memory_limit(void);



/*
 *
 * SECTION 1.6 -- CONSTANTS
 *
 */

/*
 *  various constants describing size limits in CWB
 */

/**
 * Maximum size of a CWB corpus.
 *
 * This is the upper limit on the size of a CWB corpus on 64-bit platforms;
 * for 32-bit versions of CWB, much tighter limits apply.
 * cwb-encode will abort once this limit has been reaching, discarding any
 * further input data. The precise value of the limit is 2^32 - 1 tokens,
 * i.e. hex 0x7FFFFFFF and decimal 2147483647.
 *
 * Note that the largest valid cpos (2^32 - 1) would allow a theoretical corpus
 * size of 2^32 tokens. But in some places, the corpus size itself is stored
 * in a signed 32-bit integer variable, hence the lower limit.
 */
#define CL_MAX_CORPUS_SIZE 2147483647

/**
 * General string buffer size constant.
 *
 * This constant is used to determine the maximum length (in bytes)
 * of a line in a CWB input file. It therefore follows that no s-attribute
 * or p-attribute can ever be longer than this. It's also the normal constant
 * to use for (a) a local or global declaration of a character array (b)
 * dynamic memory allocation of a string buffer. The associated function
 * cl_strcpy() will copy this many bytes at most.
 */
#define CL_MAX_LINE_LENGTH 4096

/**
 * String buffer size constant (for filenames).
 *
 * This constant can be used for declaring character arrays that will
 * only contain a filename (or path). It is expected that this will
 * be shorter than CL_MAX_LINE_LENGTH.
 */
#define CL_MAX_FILENAME_LENGTH 1024




/*
 *
 * SECTION 1.7 -- MISCELLANEOUS UTILITIES
 *
 */

/*
 *  misc CL utility objects/functions
 */

/* all programs that use the CL should call this on startup (sets the locale and other boring stuff) */
void cl_startup(void);


/* Make sure that the MIN and MAX macros exist (defined as in Glib and commonly) */

#ifndef MIN
/** Evaluates to the smaller of two numeric arguments. */
#define MIN(a,b) ((a)<(b) ? (a) : (b))
#endif

#ifndef MAX
/** Evaluates to the larger of two numberic arguments.*/
#define MAX(a,b) ((a)>(b) ? (a) : (b))
#endif



/**
 * Underlying structure for the ClAutoString object.
 *
 * (Its members are not hidden, but you are advised not to tinker with
 * them directly unless you really know what you are doing; reading from
 * them is usually safe but changing them other than via the object methods
 * is not safe at all.)
 */
struct ClAutoString {
  char *data;                /**< The actual character data (null-terminated string). */
  size_t len;                /**< Length of the string, strlen-style (count of bytes not including the final zero byte). */
  size_t bytes_allocated;    /**< Amount of memory currently allocated at the location pointed to by data. */
  size_t increment;          /**< When the data buffer is too small, it will be increased by the lowest sufficient multiple
                                  of the increment value (specified at object creation time; can be reset later; defaults to CL_MAX_LINE_LENGTH). */
};
/**
 * A single-string object whose memory allocation grows automatically.
 */
typedef struct ClAutoString *ClAutoString;
/* the ClAutoString object API */
ClAutoString cl_autostring_new(const char *data, size_t init_bytes);
void cl_autostring_delete(ClAutoString string);
void cl_autostring_set_increment(ClAutoString string, size_t new_increment);
char *cl_autostring_ptr(ClAutoString string);
size_t cl_autostring_len(ClAutoString string);
void cl_autostring_reclaim_mem(ClAutoString string);
void cl_autostring_copy(ClAutoString dst, const char *src);
void cl_autostring_concat(ClAutoString dst, const char *src);
void cl_autostring_truncate(ClAutoString string, int new_length);
void cl_autostring_dump(ClAutoString string);


/*
 * I/O streams with magic for compressed files (.gz, .bz2) and pipes
 *
 * These functions can be used to open input and output FILE* streams to compressed files, pipes, and stdin/stdout.
 * The type of stream is either specified directly with one of the constants below, or automagically guessed from
 * the filename, according to the following rules:
 *   - if filename is "-", the stream reads from stdin or writes to stdout (depending on the mode)
 *   - if filename starts with "|", it is interpreted as a pipe to/from a shell command (the pipe symbol must always at the start, even when reading from a pipe)
 *   - if filename ends in ".gz" or ".bz2", it is read/written as a compressed file (through a pipe to external gzip and bzip2 utilities)
 *   - otherwise it is read/written as a plain uncompressed file
 *   - if filename starts with "~/" or "$HOME/", the prefix is expanded to the current user's home directory
 *
 * Unless automagic type guessing is explicitly enabled, filenames will always be used literally without any normalization.
 * Read or write mode is controlled by a separate flag and cannot be set automatically (unlike Perl's "redirect"-style notation).
 *
 * Note that automagic opening of pipes to shell commands is a security risk if <filename> comes from an untrusted source.
 * Use stream type CL_STREAM_MAGIC_NOPIPE to disallow pipes; opening the I/O stream will fail in this case.
 *
 * While a stream pipe is active (even if implicitly by reading or writing a compressed file), a signal handler is installed
 * on supported platforms to catch and ignore SIGPIPE, which sets the global variable <cl_broken_pipe> to True.
 * Callers writing to a stream might want to check this variable in order to avoid stalling on a broken pipe,
 * even if they did not explicitly open a pipe stream.
 */

/* Mode and type flags for I/O streams (NB: these are mutually exclusive and must not be combined with "|") */

/** open in read mode */
#define CL_STREAM_READ       0
/** open in binary read mode (on *nix, a synonym for the normal read) */
#define CL_STREAM_READ_BIN   0

/** open in write mode */
#define CL_STREAM_WRITE      1
/** open in binary write mode (on *nix, a synonym for the normal write) */
#define CL_STREAM_WRITE_BIN  1

/** open in append mode (except for pipe) */
#define CL_STREAM_APPEND     2
/** open in binary append mode (on *nix, a synonym for the normal append) */
#define CL_STREAM_APPEND_BIN 2

#ifdef __MINGW__
/* only on Windows are the binary flags any different ... */
#define CL_STREAM_READ_BIN   4
#define CL_STREAM_WRITE_BIN  5
#define CL_STREAM_APPEND_BIN 6
/* the binary flag is the third lowest bit, so (XX & 4) == XX_BIN */
#endif


/** enable automagic recognition of stream type */
#define CL_STREAM_MAGIC        0
/** enable automagic, but fail on attempt to open pipe (safe mode for filenames from external sources) */
#define CL_STREAM_MAGIC_NOPIPE 1
/** read/write plain uncompressed file */
#define CL_STREAM_FILE         2
/** read/write gzip-compressed file */
#define CL_STREAM_GZIP         3
/** read/write bzip2-compressed file */
#define CL_STREAM_BZIP2        4
/** read/write pipe to shell command */
#define CL_STREAM_PIPE         5
/** read from stdin or write to stdout (<filename> is ignored) */
#define CL_STREAM_STDIO        6


/**
 * This variable will be set to True if a SIGPIPE has been caught and ignored.
 * It is reset to False whenever a stream is opened or closed, so it is safe to check while writing to a plain file stream.
 * If multiple pipes are active, there is no way to indicate which one caused the SIGPIPE.
 */
extern int cl_broken_pipe;

/* Open an I/O stream wiht the assorted auto-magic described above; return must be closed with cl_close_stream()! */
FILE *cl_open_stream(const char *filename, int mode, int type);

/* Close an I/O stream originally opened with cl_open_stream(); returns 0 on success or an fclose/pclose error code.  */
int cl_close_stream(FILE *handle);

/* Determine whether an I/O stream (FILE* handle) was opened with cl_open_stream -- returns true if so. */
int cl_test_stream(FILE *handle);

/* CL-specific version of strcpy. Don't use unless you know what you're doing. */
char *cl_strcpy(char *buf, const char *src);

int cl_strcmp(const char *s1, const char *s2);



/**
 * Tests two strings for equality.
 *
 * This macro evaluates to 1 if the strings are equal, 0 otherwise.
 * Be careful: strings are considered equal if they are both NULL,
 * they are considered non-equal when one of the two is NULL.
 *
 * The underlying function used is cl_strcmp (which does signed
 * char comparison).
 *
 * @see       cl_strcmp
 * @param  a  the first string
 * @param  b  the second string
 * @return    Boolean
 * TODO, for consistency function-like macro should eb lowercase a la cl_free)
 */
#define CL_STREQ(a,b) (((a) == (b)) || (NULL!=(a) && (b) && (0==cl_strcmp((a), (b)))))


char *cl_string_latex2iso(char *str, char *result, int target_len);
/* <result> points to buffer of appropriate size; auto-allocated if NULL;
   str == result is explicitly allowed; conveniently returns <result> */
extern int cl_allow_latex2iso; /* cl_string_latex2iso will only change a string if this is true, it is false by default*/

char *cl_xml_entity_decode(char *s); /* removes the four default XML entities from the string, in situ */
/**
 * For a given character, say whether it is legal for an XML name.
 *
 * TODO: Currently, anything in the upper half of the 8-bit range is
 * allowed (in the old Latin1 days this was anything from 0xa0 to
 * 0xff). This will work with any non-ascii character set, but
 * is almost certainly too lax.
 *
 * @param c  Character to check. (It is expected to be a char,
 *           so is typecast to unsigned char for comparison with
 *           upper-128 hex values.)
 */
#define cl_xml_is_name_char(c)  ( ( c >= 'A'  && c <= 'Z')  ||       \
                                  ( c >= 'a'  && c <= 'z')  ||       \
                                  ( c >= '0'  && c <= '9')  ||       \
                                  (    (unsigned char) c >= 0x80     \
                                  /* && (unsigned char) c <= 0xff */ \
                                  ) ||                               \
                                  ( c == '-') ||                     \
                                  ( c == '_')                        \
                                 )

/* functions that do things with paths */
void cl_path_adjust_os(char *path);  /* normalises a path to Windowslike or Unixlike, depending on the build; string changed in place. */
void cl_path_adjust_independent(char *path); /* makes a path Unixlike, regardless of the OS; string changed in place. */

char *cl_path_registry_quote(char *path); /* adds registry-format quotes and slashes to a path where necessary;
                                             a newly-allocated string is returned. */

char *cl_path_get_component(char *s); /* tokeniser for string containing many paths separated by : or ; */

/* validate and manipulate strings that are (sub)corpus identifiers */
int cl_id_validate(char *s);
void cl_id_toupper(char *s);
void cl_id_tolower(char *s);

/* built-in support for handling feature set attributes */
char *cl_make_set(char *s, int split);
int cl_set_size(char *s);
int cl_set_intersection(char *result, const char *s1, const char *s2);

/* safely add offset to corpus position (either clamped to corpus, or returns CDA_EPOSORNG if outside) */
int cl_cpos_offset(int cpos, int offset, int corpus_size, int clamp);



/*
 *
 * SECTION 2 -- THE CORE CL LIBRARY (DATA ACCESS)
 *
 * These are the central CL corpus and attribute 'methods'.
 *
 */

/*
 *
 * SECTION 2.1 -- THE Corpus OBJECT
 *
 */

/**
 * The Corpus object: contains information on a loaded corpus,
 * including all its attributes.
 */
typedef struct TCorpus Corpus;

/* corpus access functions */
Corpus *cl_new_corpus(char *registry_dir, char *registry_name);
int cl_delete_corpus(Corpus *corpus);
char *cl_standard_registry();
cl_string_list cl_corpus_list_attributes(Corpus *corpus, int attribute_type);




/*
 *
 * SECTION 2.2 -- THE Attribute OBJECT
 *
 */

/* TODO ... wouldn't it be nice if the Attribute methods returned int/string list objects instead of
 * char ** and int *?  But that would involve re-engineering EVERYTHING.  */
/**
 * The Attribute object: an entire segment of a corpus, such as an
 * annotation field, an XML structure, or a set
 *
 * The attribute can be of any flavour (s, p etc); this information
 * is specified internally.
 *
 * Note that each Attribute object is associated with a particular
 * corpus. They aren't abstract, i.e. every corpus has a "word"
 * p-attribute but any Attribute object for a "word" refers to the
 * "word" of a specific corpus, not to "word" attributes in general.
 */
typedef union _Attribute Attribute;

/* constants indicating attribute types */

/** No type of attribute */
#define ATT_NONE       0
/** Positional attributes, ie streams of word tokens, word tags - any "column" that has a value at every corpus position. */
#define ATT_POS        (1<<0)
/** Structural attributes, ie a set of SGML/XML-ish "regions" in the corpus delimited by the same SGML/XML tag */
#define ATT_STRUC      (1<<1)
/** Alignment attributes, ie a set of zones of alignment between a source and target corpus */
#define ATT_ALIGN      (1<<2)
/** Dynamic attributes, ie a depracated feature, but its datatypes are still used for some CQP function parameters/returns */
#define ATT_DYN        (1<<6)

/** shorthand for "any / all types of attribute" */
#define ATT_ALL        ( ATT_POS | ATT_STRUC | ATT_ALIGN | ATT_DYN )
/** shorthand for "any / all types of attribute except dynamic" */
#define ATT_REAL       ( ATT_POS | ATT_STRUC | ATT_ALIGN )


/* there are a huge number of Attribute "methods" accessing different
 * kinds of Attribute in different ways... */

/* attribute access functions: general Attribute methods */

/**
 * Finds an attribute that matches the specified parameters, if one exists,
 * for the given corpus.
 *
 * Note that although this is a cl_new_* function, and it is the canonical way
 * that we get an Attribute to call Attribute-functions on, it doesn't actually
 * create any kind of object. The Attribute exists already as one of the dependents
 * of the Corpus object; this function simply locates it and returns a pointer
 * to it.
 *
 * This is reproduction of the old type of call, using a macro to the new type.
 *
 * @see                   cl_new_attribute
 *
 * @param corpus          The corpus in which to search for the attribute.
 * @param name            The name of the attribute (i.e. the handle it has in the registry file).
 * @param type            Type of attribute to be searched for.
 * @param data            *** UNUSED ***.
 *
 * @return                Pointer to Attribute object, or NULL if not found.
 */
#define cl_new_attribute_oldstyle(corpus, name, type, data) cl_new_attribute(corpus, name, type)

Attribute *cl_new_attribute(Corpus *corpus, const char *attribute_name, int type);
int cl_delete_attribute(Attribute *attribute);
int cl_sequence_compressed(Attribute *attribute);
int cl_index_compressed(Attribute *attribute);

/* get the Corpus object of which the Attribute is a daughter */
Corpus *cl_attribute_mother_corpus(Attribute *attribute);

/* attribute access functions: lexicon access (positional attributes) */
char *cl_id2str(Attribute *attribute, int id);
int cl_str2id(Attribute *attribute, char *id_string);
int cl_id2strlen(Attribute *attribute, int id);
int cl_sort2id(Attribute *attribute, int sort_index_position);
int cl_id2sort(Attribute *attribute, int id);

/* attribute access functions: size (positional attributes) */
int cl_max_cpos(Attribute *attribute);
int cl_max_id(Attribute *attribute);

/* attribute access functions: token sequence & index (positional attributes) */
int cl_id2freq(Attribute *attribute, int id);

/**
 * Gets all the corpus positions where the specified item is found on the given P-attribute.
 * @see         cl_id2cpos_oldstyle
 * @param a     The P-attribute to look on.
 * @param id    The id of the item to look for.
 * @param freq  The frequency of the specified item is written here.
 *              This will be 0 in the case of errors.
 * @return      Integer pointer (to the list of corpus positions); NULL in case of error.
 */
#define cl_id2cpos(a, id, freq) cl_id2cpos_oldstyle(a, id, freq, NULL, 0)

/* depracated */
int *cl_id2cpos_oldstyle(Attribute *attribute,
                         int id,
                         int *freq,
                         int *restrictor_list,
                         int restrictor_list_size);
int cl_cpos2id(Attribute *attribute, int position);
char *cl_cpos2str(Attribute *attribute, int position);

/* ========== some high-level constructs */

char *cl_id2all(Attribute *attribute, int index, int *freq, int *slen);

int *cl_regex2id(Attribute *attribute,
                 char *pattern,
                 int flags,
                 int *number_of_matches);

int cl_idlist2freq(Attribute *attribute,
                   int *ids,
                   int number_of_ids);

/**
 * Gets a list of corpus positions matching a list of ids.
 * @see cl_idlist2cpos_oldstyle
 * @param a            The P-attribute we are looking in
 * @param idlist       A list of item ids (i.e. id codes for items on this attribute).
 * @param idlist_size  The length of this list.
 * @param sort         boolean: return sorted list?
 * @param size         The size of the allocated table will be placed here.
 * @return             Pointer to the list of corpus positions. NULL in case of error.
 */
#define cl_idlist2cpos(a, idlist, idlist_size, sort, size) cl_idlist2cpos_oldstyle(a, idlist, idlist_size, sort, size, NULL, 0)

/* depracated */
int *cl_idlist2cpos_oldstyle(Attribute *attribute,
                             int *ids,
                             int number_of_ids,
                             int sort,
                             int *size_of_table,
                             int *restrictor_list,
                             int restrictor_list_size);

/* attribute access functions: structural attributes */
/* note that "struc", in these function names, abbreviates "number identifiying
 * one structure instance on this s-attribute" */

int cl_cpos2struc2cpos(Attribute *attribute,
                       int position,
                       int *struc_start,
                       int *struc_end);
int cl_cpos2struc(Attribute *a, int cpos);

/* depracated */
int cl_cpos2struc_oldstyle(Attribute *attribute,
                           int position,
                           int *struc_num);

/* flags set in return values of cl_cpos2boundary() function */
#define STRUC_INSIDE 1  /**< cl_cpos2boundary() return flag: specified position is WITHIN a region of this s-attribute */
#define STRUC_LBOUND 2  /**< cl_cpos2boundary() return flag: specified position is AT THE START BOUNDARY OF a region of this s-attribute */
#define STRUC_RBOUND 4  /**< cl_cpos2boundary() return flag: specified position is AT THE END BOUNDARY OF a region of this s-attribute */
int cl_cpos2boundary(Attribute *a, int cpos);  /* convenience function: within region or at boundary? */

int cl_struc2cpos(Attribute *attribute,
                  int struc_num,
                  int *struc_start,
                  int *struc_end);
int cl_max_struc(Attribute *a);
int cl_max_struc_oldstyle(Attribute *attribute, int *nr_strucs);         /* depracated */
int cl_struc_values(Attribute *attribute);
char *cl_struc2str(Attribute *attribute, int struc_num);
char *cl_cpos2struc2str(Attribute *attribute, int position);

/* attribute access functions: extended alignment attributes (with fallback to old alignment) */
int cl_has_extended_alignment(Attribute *attribute);
int cl_max_alg(Attribute *attribute);
int cl_cpos2alg(Attribute *attribute, int cpos);
int cl_alg2cpos(Attribute *attribute, int alg,
                int *source_region_start, int *source_region_end,
                int *target_region_start, int *target_region_end);

/* attribute access functions: alignment attributes (old style) -- DEPRACATED */
int cl_cpos2alg2cpos_oldstyle(Attribute *attribute,
                              int position,
                              int *aligned_start,
                              int *aligned_end,
                              int *aligned_start2,
                              int *aligned_end2);

/* attribute access functions: dynamic attributes (N/A)
 *
 * NOTE that dynamic attributes are not currently supported.
 * Most of the code has been thrown out.
 *
 * Before we can prototype these, we need the DynCallResult datatype
 * This is properly an object on its own, but it is not separate
 * enough from the Attribute to merit its own heading.*/

/**
 *  maximum size of 'dynamic' strings
 */
#define CL_DYN_STRING_SIZE 2048

/**
 *  The DynCallResult object (needed to allocate space for dynamic function arguments)
 */
typedef struct _DCR {
  int type;              /**< Type of DynCallResult, indicated by one of the ATTAT_x macro constants*/
  union {
    int intres;
    char *charres;
    double floatres;
    struct {
      Attribute *attr;
      int token_id;
    } parefres;
  } value;               /**< value of the result: can be int, string, float, or p-attribute reference */
  /**
   * buffer for dynamic strings returned by function calls
   * NB: this imposes a hard limit on the size of dynamic strings !!
   * @see CL_DYN_STRING_SIZE
   */
  char dynamic_string_buffer[CL_DYN_STRING_SIZE];
  /* TODO - use a ClAutoString instead? */
} DynCallResult;

/* result and argument types of CQP functions (historically: dynamic attributes); ATTAT = attribute argument type */
#define ATTAT_NONE    0                /**< CQP function argument type: none */
#define ATTAT_POS     1                /**< CQP function argument type: corpus position */
#define ATTAT_STRING  2                /**< CQP function argument type: string */
#define ATTAT_INT     3                /**< CQP function argument type: integer */
#define ATTAT_VAR     4                /**< CQP function argument type: variable number of string arguments (only in arglist) */
#define ATTAT_FLOAT   5                /**< CQP function argument type: floating point */
#define ATTAT_PAREF   6                /**< CQP function argument type: p-attribute reference */

/* and now the functions:
 *
 * ...: parameters (of *int or *char) and structure
 * which gets the result (*int or *char)
 */
int cl_dynamic_call(Attribute *attribute, DynCallResult *dcr, DynCallResult *args, int nr_args);
int cl_dynamic_numargs(Attribute *attribute);




/*
 *
 * SECTION 2.3 -- THE PositionStream OBJECT
 *
 */

/**
 * The PositionStream object: gives stream-like reading of an Attribute.
 */
typedef struct _position_stream_rec_ *PositionStream;

/* Functions for attribute access using a position stream */
PositionStream cl_new_stream(Attribute *attribute, int id);
int cl_delete_stream(PositionStream *ps);
int cl_read_stream(PositionStream ps, int *buffer, int buffer_size);




/*
 *
 * SECTION 3 -- SUPPORT CLASSES
 *
 */

/*
 *
 * SECTION 3.1 -- THE CorpusProperty OBJECT
 *
 */

/**
 * The CorpusProperty object.
 *
 * The underlying structure takes the form of a linked-list entry.
 *
 * Note that unlike most CL objects, the underlying structure is
 * exposed in the public API.
 *
 * Each Corpus object has, as one of its members, the head entry
 * on a list of CorpusProperties.
 */
typedef struct TCorpusProperty {
  /** A string specifying the property in question. */
  char *property;
  /** A string containing the value of the property in question. */
  char *value;
  /** Pointer to the next entry in the linked list. */
  struct TCorpusProperty *next;
} *CorpusProperty;

/* ... and ... the CorpusProperty API */
CorpusProperty cl_first_corpus_property(Corpus *corpus);
CorpusProperty cl_next_corpus_property(CorpusProperty p);
char *cl_corpus_property(Corpus *corpus, char *property);




/*
 *
 * SECTION 3.2 -- THE CorpusCharset OBJECT
 *
 */

/**
 * The CorpusCharset object:
 * an identifier for one of the character sets supported by CWB.
 *
 * (Note on adding new character sets: add them immediately before
 * unknown_charset. Do not change the order of existing charsets.
 * Remember to update the special-chars module if you do so.)
 */
typedef enum ECorpusCharset {
  ascii = 0,

  /* As of v3.2.7, all charsets listed below are supported. */

  /* latin1 = 8859-1, latin2 = 8859-2, latin3 = 8859-3, latin4 = 8859-4, cyrillic = 8859-5,
     arabic = 8859-6, greek = 8859-7, hebrew = 8859-8, latin5 = 8859-9, latin6 = 8859-10,
     latin7 = 8859-13, latin8 = 8859-14, latin9 = 8859-15 */
  latin1, latin2, latin3, latin4, cyrillic,
  arabic, greek,  hebrew, latin5, latin6,
  latin7, latin8, latin9,
  utf8,
  /* everything else is 'unknown' */
  unknown_charset
} CorpusCharset;

/* ... and related functions */
CorpusCharset cl_corpus_charset(Corpus *corpus);
const char *cl_charset_name(CorpusCharset id);
CorpusCharset cl_charset_from_name(const char *name);
const char *cl_charset_name_canonical(char *name_to_check);
size_t cl_charset_strlen(CorpusCharset charset, char *s);

/* the main functions for which CorpusCharset "matters" are the following... */

/* the case/diacritic string normalization features used by CL regexes and CQP (modify input string, unless final arg < 1) */
char *cl_string_canonical(char *s, CorpusCharset charset, int flags, int inplace_bufsize);
/* modifies string <s> in place if a buffersize given, otherwise returns a new string;
 * flags are IGNORE_CASE, IGNORE_DIAC, REQUIRE_NFC (i.e. same flags as for regex) */
/** Convenience calling-constant that forces cl_string_canonical to return a newly-allocated buffer. @see cl_string_canonical */
#define CL_STRING_CANONICAL_STRDUP -1

/* remove or overwrite C0 control characters in a string (modify input string!) */
int cl_string_zap_controls(char *s, CorpusCharset charset, char replace, int zap_tabs, int zap_newlines);

/* boolean function, is a given byte a UTF-8 continuation byte? */
int cl_string_utf8_continuation_byte(unsigned char byte);

/* boolean function, returns is string valid?; can repair (in-place edit) 8-bit encoding by replacing invalid chars with '?' */
int cl_string_validate_encoding(char *s, CorpusCharset charset, int repair);

/* reverse string (for reverse sorting) */
char *cl_string_reverse(const char *s, CorpusCharset charset); /* creates a new string */

/* string comparison suitable as qsort() callback */
int cl_string_qsort_compare(const char *s1, const char *s2, CorpusCharset charset, int flags, int reverse);

/* remove any trailing LF (\n) and CR (\r) characters from string (modified in-place, similar to Perl's chomp operator); */
/* the main purpose of this function is to help CWB read text files in Windows (CR-LF) format correctly; */
/* note that charset doesn't have to be specified because LF and CR have the same byte codes in all supported encodings */
void cl_string_chomp(char *s);


/**
 * "Dummy" charset macro for calling cl_string_canonical
 *
 * We have a problem - CorpusCharsets are attached to corpora. So what charset do we use with
 * cl_string_canonical if we are calling it on a string that does not (yet) have a corpus?
 *
 * The answer: CHARSET_FOR_IDENTIFIERS. This should only be used as the 2nd argument to
 * cl_string_canonical when the string is an identifier for a corpus, attribute, or whatever.
 *
 * Note it is Ascii in v3.2.x+, breaking backwards compatibility with 2.2.x where Latin1 was
 * allowed for identifiers.
 */
#define CHARSET_FOR_IDENTIFIERS ascii





/*
 *
 * SECTION 3.3 -- THE CL_Regex OBJECT
 *
 */

/**
 * The CL_Regex object: an optimised regular expression.
 *
 * The CL regex engine wraps around another regex library (v3.1.x: POSIX, will be PCRE
 * in v3.2.0+) to implement CL semantics. These are: (a) the engine always
 * matches the entire string; (b) there is support for case-/diacritic-insensitive matching;
 * (c) certain optimisations are implemented.
 *
 * Associated with the CL regular expression engine are macros for three flags: IGNORE_CASE,
 * IGNORE_DIAC and IGNORE_REGEX. All three are used by the related cl_regex2id(), but only
 * the first two are used by the CL_Regex object (since it does not support non-regexp search).
 *
 * @see cl_regex2id
 */
typedef struct _CL_Regex *CL_Regex;

/** Flag: ignore-case in regular expression engine; fold case in cl_string_canonical. */
#define IGNORE_CASE  1
/** Flag ignore-diacritics in regular expression engine; fold diacritics in cl_string_canonical */
#define IGNORE_DIAC  2
/** Flag for: don't use regular expression engine - match as a literal string. */
#define IGNORE_REGEX 4
/**
 * Flag for: string requires enforcement of pre-composed normal form (NFC), which is standard in CWB indexed corpora;
 * applies only to UTF-8; all UTF-8 strings passed in from external sources need to be normalised in this way;
 * applies to subject string when used with regex engine, to sole argument string when used with cl_string_canonical;
 */
#define REQUIRE_NFC  8


/* ... and the regex API ... */
CL_Regex cl_new_regex(char *regex, int flags, CorpusCharset charset);
int cl_regex_optimised(CL_Regex rx); /* 0 = not optimised; otherwise, value indicates level of optimisation */
int cl_regex_match(CL_Regex rx, char *str, int normalize_utf8);
void cl_delete_regex(CL_Regex rx);
extern char cl_regex_error[];

/* two functions interface the optimiser system's reporting capabilities */
void cl_regopt_count_reset(void);
int cl_regopt_count_get(void);




/*
 *
 * SECTION 3.4 -- THE cl_lexhash OBJECT
 *
 */

/**
 *  The cl_lexhash class (lexicon hashes, with IDs and frequency counts).
 *
 *  A "lexicon hash" links strings to integers. Each cl_lexhash object
 *  represents an entire table of such things; individual string-to-int
 *  links are represented by cl_lexhash_entry objects.
 *
 *  Within the cl_lexhash, the entries are grouped into buckets. A
 *  bucket is the term for a "slot" on the hash table. The linked-list
 *  in a given bucket represent all the different string-keys that map
 *  to one particular index value.
 *
 *  Each entry contains the key itself (for search-and-retrieval),
 *  the frequency of that type (incremented when a token is added that
 *  is already in the lexhash), an ID integer, plus a bundle of "data"
 *  associated with that string.
 *
 *  These lexicon hashes are used, notably, in the encoding of corpora
 *  to CWB-index-format.
 *
 *  WARNING: cl_lexhash objects are intended for data sets ranging from
 *  a few dozen entries to several million entries. Do not try to store
 *  more than a billion (distinct) strings in a lexicon hash, otherwise
 *  bad (and unpredictable) things will happen. You have been warned!
 *
 */
typedef struct _cl_lexhash *cl_lexhash;
/**
 * Underlying structure for the cl_lexhash_entry class.
 * Unlike most underlying structures, this is public in the CL API.
 * This is done so that applications can access the embedded payload
 * directly (as entry->data->integer, ...).
 *
 * Such structures MUST NOT be allocated or copied directly by an
 * application! Neither may internal fields, esp. entry->key, be modified.
 * Only read and write access to the payload of entries returned by
 * cl_lexhash_find() and cl_lexhash_add() is allowed.
 */
typedef struct _cl_lexhash_entry {
  /**
   * Note that the fields of this structure have been re-ordered to
   * ensure proper alignment without any padding.
   */
  struct _cl_lexhash_entry *next;   /**< next entry on the linked-list (ie in the bucket) */
  unsigned int freq;                /**< frequency of this type */
  int id;                           /**< the id code of this type */
  /**
   * This entry's data fields, i.e. its payload.
   * Use as entry->data.integer, entry->data.numeric, ...
   * To improve the versatility of cl_lexhash, the payload is implemented
   * as a struct rather than a union, so it can store two numbers and a
   * pointer at the same time.  This design was inspired by Perl, whose
   * variables have multiple entries for scalar, array, hash, etc.
   */
  struct _cl_lexhash_entry_data {
    void *pointer;
    double numeric;
    int integer;
  } data;
  char key[1];                       /**< hash key == type (embedded in struct) */
} *cl_lexhash_entry;

/*
 * ... and ... its API!!
 */
cl_lexhash cl_new_lexhash(int buckets);
void cl_delete_lexhash(cl_lexhash lh);
void cl_lexhash_set_cleanup_function(cl_lexhash lh, void (*func)(cl_lexhash_entry));
void cl_lexhash_auto_grow(cl_lexhash lh, int flag);
void cl_lexhash_auto_grow_fillrate(cl_lexhash lh, double limit, double target);
cl_lexhash_entry cl_lexhash_add(cl_lexhash lh, char *token);
cl_lexhash_entry cl_lexhash_find(cl_lexhash lh, char *token);
int cl_lexhash_id(cl_lexhash lh, char *token);
int cl_lexhash_freq(cl_lexhash lh, char *token);
int cl_lexhash_del(cl_lexhash lh, char *token);
int cl_lexhash_size(cl_lexhash lh);

/*
 * Simple iterator for the entries of a lexhash. There is only a single
 * iterator for each cl_lexhash object. The iterator is invalidated by all
 * updates of the lexhash and will need to be reset afterwards.
 */
void cl_lexhash_iterator_reset(cl_lexhash hash);
cl_lexhash_entry cl_lexhash_iterator_next(cl_lexhash hash);



/*
 *
 * SECTION 3.5 -- THE cl_ngram_hash OBJECT
 *
 */

/**
 *  The cl_ngram_hash class (hash-based frequency counts for n-grams,
 *  represented by n-tuples of integer type IDs).
 *
 *  A "n-gram hash" is used to collect frequency counts for n-grams,
 *  which are represented by n-tuples of integer type IDs. The mapping
 *  between types and IDs is not part of a cl_ngram_hash object and
 *  must be provided externally.
 *
 *  N-gram hashes encapsulate a central aspect of the cwb-scan-corpus
 *  utility, making efficient n-gram frequency counts available to
 *  other applications.
 *
 *  The implementation of the cl_ngram_hash class is similar to
 *  cl_lexhash.  However, at the current time there is no mapping to
 *  unique n-gram IDs and no support for user data (a "payload").
 *  The sole purpose of the implementation is to enable fast and
 *  memory-efficient frequency counts for very large sets of n-grams.
 *
 *  WARNING: cl_ngram_hash objects cannot store more than 2^32 - 1
 *  entries. Bad things will happen if you try to do so!
 *
 */
typedef struct _cl_ngram_hash *cl_ngram_hash;

/**
 * Underlying structure for the cl_ngram_hash_entry class.
 * Unlike most underlying structures, this is public in the CL API,
 * so that applications can iterate through entries, sort them, etc.
 *
 * Access the frequency count with entry->freq, and the type IDs
 * of the tuple members with entry->ngram[0], entry->ngram[1], ...
 *
 * Entries MUST NOT be allocated, copied or modified directly by
 * an application!
 */
typedef struct _cl_ngram_hash_entry {
  struct _cl_ngram_hash_entry *next; /**< next entry on the linked-list (i.e. in the bucket) */
  unsigned int freq;                 /**< frequency of this type */
  int ngram[1];                      /**< ngram data embedded in struct */
} *cl_ngram_hash_entry;

/*
 * ... and its API ...
 */
cl_ngram_hash cl_new_ngram_hash(int N, int buckets);
void cl_delete_ngram_hash(cl_ngram_hash hash);
void cl_ngram_hash_auto_grow(cl_ngram_hash hash, int flag);
void cl_ngram_hash_auto_grow_fillrate(cl_ngram_hash hash, double limit, double target);
cl_ngram_hash_entry cl_ngram_hash_add(cl_ngram_hash hash, int *ngram, unsigned int f);
cl_ngram_hash_entry cl_ngram_hash_find(cl_ngram_hash hash, int *ngram);
int cl_ngram_hash_del(cl_ngram_hash hash, int *ngram);
int cl_ngram_hash_freq(cl_ngram_hash hash, int *ngram);
int cl_ngram_hash_size(cl_ngram_hash hash);
/**
 * Returns allocated vector of pointers to all entries of the n-gram hash.
 * Must be freed by the application and can be modified, e.g. for sorting.
 * Use cl_ngram_hash_size() to find out how many entries there are.
 */
cl_ngram_hash_entry *cl_ngram_hash_get_entries(cl_ngram_hash hash, int *ret_size);
/**
 * Simple iterator for the entries of an n-gram hash. There is only a single
 * iterator for each cl_ngram_hash object. The iterator is invalidated by all
 * updates of the n-gram hash and will need to be reset afterwards.
 */
void cl_ngram_hash_iterator_reset(cl_ngram_hash hash);
cl_ngram_hash_entry cl_ngram_hash_iterator_next(cl_ngram_hash hash);
/**
 * Statistics on bucket fill rates for debugging purposes
 */
int *cl_ngram_hash_stats(cl_ngram_hash hash, int max_n);
void cl_ngram_hash_print_stats(cl_ngram_hash hash, int max_n);


/*
 * SECTION 4 -- THE OLD CL API
 *
 * compatibility macros : old names #defined to new names...
 */

/* The old-style names are being phased out in CWB itself; but these macros
 * will be preserved for backwards-compatibility with software programmed
 * against earlier versions of the CL API. They cover most, but not all, of
 * the core corpus-data-access functionality in the Corpus and Attribute
 * classes.
 *
 * The macros are given here in order of old name. The ones marked "unused"
 * are no longer in use in the CWB core itself. (That's all of them now.)
 *
 * As noted in the file intro, all these old function names are DEPRACATED
 * and will be removed completely in CWB v 3.9 and above.
 */
#define ClosePositionStream(ps) cl_delete_stream(ps) /* macro now unused */
#define OpenPositionStream(a, id) cl_new_stream(a, id) /* macro now unused */
#define ReadPositionStream(ps, buf, size) cl_read_stream(ps, buf, size) /* macro now unused */
#define attr_drop_attribute(a) cl_delete_attribute(a) /* macro now unused */
#define call_dynamic_attribute(a, dcr, args, nr_args) cl_dynamic_call(a, dcr, args, nr_args) /* macro now unused */
#define cderrno cl_errno /* macro now unused */
#define cdperror(message) cl_error(message) /* macro now unused */
#define cdperror_string(no) cl_error_string(no) /* macro now unused */
#define central_corpus_directory() cl_standard_registry() /* macro now unused */
#define collect_matches(a, idlist, idlist_size, sort, size, rl, rls) cl_idlist2cpos_oldstyle(a, idlist, idlist_size, sort, size, rl, rls) /* macro now unused */
#define collect_matching_ids(a, re, flags, size) cl_regex2id(a, re, flags, size) /* macro now unused */
#define cumulative_id_frequency(a, list, size) cl_idlist2freq(a, list, size) /* macro now unused */
#define drop_corpus(c) cl_delete_corpus(c) /* macro now unused */
#define find_attribute(c, name, type, data) cl_new_attribute(c, name, type) /* macro now unused */
#define get_alg_attribute(a, p, start1, end1, start2, end2) cl_cpos2alg2cpos_oldstyle(a, p, start1, end1, start2, end2) /* macro now unused */
#define get_attribute_size(a) cl_max_cpos(a) /* macro now unused */
#define get_bounds_of_nth_struc(a, struc, start, end) cl_struc2cpos(a, struc, start, end) /* macro now unused */
#define get_id_at_position(a, cpos) cl_cpos2id(a, cpos) /* macro now unused */
#define get_id_of_string(a, str) cl_str2id(a, str) /* macro now unused */
#define get_id_frequency(a, id) cl_id2freq(a, id) /* macro now unused */
#define get_id_from_sortidx(a, sid) cl_sort2id(a, sid) /* macro now unused */
#define get_id_info(a, sid, freq, len) cl_id2all(a, sid, freq, len) /* macro now unused */
#define get_id_range(a) cl_max_id(a) /* macro now unused */
#define get_id_string_len(a, id) cl_id2strlen(a, id) /* macro now unused */
#define get_nr_of_strucs(a, nr) cl_max_struc_oldstyle(a, nr)/* macro now unused */
#define get_num_of_struc(a, p, num) cl_cpos2struc_oldstyle(a, p, num) /* macro now unused */
#define get_positions(a, id, freq, rl, rls) cl_id2cpos_oldstyle(a, id, freq, rl, rls) /* macro now unused */
#define get_sortidxpos_of_id(a, id) cl_id2sort(a, id) /* macro now unused */
#define get_string_at_position(a, cpos) cl_cpos2str(a, cpos) /* macro now unused */
#define get_string_of_id(a, id) cl_id2str(a, id) /* macro now unused */
#define get_struc_attribute(a, cpos, start, end) cl_cpos2struc2cpos(a, cpos, start, end) /* macro now unused */
#define inverted_file_is_compressed(a) cl_index_compressed(a) /* macro now unused */
#define item_sequence_is_compressed(a) cl_sequence_compressed(a) /* macro now unused */
#define nr_of_arguments(a) cl_dynamic_numargs(a) /* macro now unused */
#define setup_corpus(reg, name) cl_new_corpus(reg, name) /* macro now unused */
#define structure_has_values(a) cl_struc_values(a) /* macro now unused */
#define structure_value(a, struc) cl_struc2str(a, struc) /* macro now unused */
#define structure_value_at_position(a, cpos) cl_cpos2struc2str(a, cpos) /* macro now unused */

/* formerly a CQP function, now in CL */
#define get_path_component cl_path_get_component /* macro now unused */

/*
 * Some "old" functions have gone altogether; they are not just depracated, but vanished!
 *
 * (So some compatibility with the pre-3.2.0 CL has been broken in a minor way. If you
 * REALLY need these functions, you can get them back by #including other headers from
 * the CL module - but as these are now "private methods" they are not guaranteed to
 * stay the same, or even to exist at all, in future revisions of the CL.)
 *
 *    cl_string_maptable()
 *
 * This is because it didn't make sense for v3.2.0 or higher, where UTF8 strings are not
 * only possible but likely and we need to be a bit more sophisticated all around about
 * how we deal with inter-character mappings.
 *
 *    describe_corpus()
 *
 * This was not really "at home" in a low-level API; it's been hidden away, and may later
 * move out of the CL altogether and into the CWB utilities, where it fits better.
 *
 *    find_corpus()
 *
 * This should never have been a public function in the first place - it's an internal
 * function called by cl_new_corpus().
 *
 */

#endif /* ifndef _cwb_cl_h_ */
