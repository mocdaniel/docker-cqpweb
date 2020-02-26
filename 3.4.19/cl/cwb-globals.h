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
 * This file contains variables, constants etc. that are defined inside the CL,
 * but are not part of the public interface in the cl.h - but they really belong to
 * all of CWB, not just the CL, and the utilities and CQP need them too.
 */

#ifndef _cwb_globals_h
#define _cwb_globals_h


/* The CWB_VERSION macro should be defined by the build environment.
 * If it isn't already defined, this definition ensures compilation of the CL,
 * and any programs that use it, won't fail (e.g. if you're test-compiling
 * a single file that contains CWB_VERSION).
 */
#ifndef CWB_VERSION
/**
 * The current version of CWB.
 */
#define CWB_VERSION " x.y.z "
#endif


/**
 * String used to identify the default attribute.
 *
 * It is "word".
 *
 * Don't change this or we'll all end up in hell !!! I MEAN IT !!!!
 */
#define CWB_DEFAULT_ATT_NAME "word"

/**
 * Default string used as value of p-attributes when a value is missing, ie if a tab-delimited field is empty.
 */
#define CWB_PA_UNDEF_VALUE "__UNDEF__"


/* default registry settings */
#ifndef CWB_REGISTRY_DEFAULT_PATH
# ifndef __MINGW__
   /** The default path assumed for the location of the corpus registry. */
#  define CWB_REGISTRY_DEFAULT_PATH  "/corpora/c1/registry"
# else
   /* note that the notion of a default path under Windows is fundamentally dodgy ... */
#  define CWB_REGISTRY_DEFAULT_PATH  "C:\\CWB\\Registry"
# endif
#endif


#ifndef CWB_REGISTRY_ENVVAR
/** The environment variable from which the value of the registry will be taken. */
#define CWB_REGISTRY_ENVVAR        "CORPUS_REGISTRY"
#endif


/* default filename of an info file */
#ifndef __MINGW__
#define CWB_INFOFILE_DEFAULT_NAME ".info"
#else
/* since ANYTHING can be specified manually in the reg file,
 * we might as well make the default filename one that Windows
 * will actually allow you to create! */
#define CWB_INFOFILE_DEFAULT_NAME "corpus-info.txt"
/* only used in cwb-encode, so here isn't really the place for it, but
 * for now let's keep it with other OS-path-control macros */
#endif


/** magic number for subcorpus (incl. query) file format: ORIGINAL version */
#define CWB_SUBCORPMAGIC_ORIG 36193928
/* the sum of the original programmers' birthdays: 15081963 (Max) + 21111965 (Oli) */

/** magic number for subcorpus (incl. query) file format: NEW version (== orig + 1) */
#define CWB_SUBCORPMAGIC_NEW 36193929
/* new format -- Mon Jul 31 17:19:27 1995 (oli) */




/*
 * Macros for path-handling: different between Unix and Windows.
 *
 * They are used across CWB, so are defined here.
 * Lack of prefix should not be a problem because CQP/utils *know* about these macros
 * and won't use their names for anything else.
 */
#ifndef __MINGW__
    /* Unix */
/** character used to separate different paths in a string variable */
#define PATH_SEPARATOR ':'
/** character used to delimit subdirectories in a path */
#define SUBDIR_SEPARATOR '/'
/** character from SUBDIR_SEPARATOR as a string for compile-time concatenation */
#define SUBDIR_SEP_STRING "/"
/** name of directory for temporary files (as string, absolute path) */
#define TEMPDIR_PATH "/tmp"
#else
    /* Windows */
#define PATH_SEPARATOR ';'
#define SUBDIR_SEPARATOR '\\'
#define SUBDIR_SEP_STRING "\\"
#define TEMPDIR_PATH "." /* A CQP user may not have access to C:\Temp, which is where they SHOULD go */
#endif
/*
 * NOTE:
 * When we move to Glib, it might be better to use G_DIR_SEPARATOR and G_SEARCHPATH_SEPARATOR
 * and delete these two macros.
 */



/**
 * size in bytes of string buffers capable of holding absolute paths
 * of temporary filenames; needs to be big enough for TEMPDIR_PATH plus
 * the result of a call to tempnam() plus the length of a process ID, at least.
 */
#define TEMP_FILENAME_BUFSIZE 128

/* this is also Win32 compatibility... extra flag for open() */
/* so that (x | O_BINARY) always == x under POSIX */
#ifndef O_BINARY
# ifdef _O_BINARY
#  define O_BINARY _O_BINARY
# else
#  define O_BINARY 0
# endif
#endif

#ifndef __MINGW__
/* for use with [fs]printf(), all decimal or floating-point conversions, as follows:
 * "%" COMMA_SEP_THOUSANDS_CONVSPEC "d" (or equivalent) */
#define COMMA_SEP_THOUSANDS_CONVSPEC "'"
#else
#define COMMA_SEP_THOUSANDS_CONVSPEC ""
/* this feature only supported on actual POSIX -- not on mingw for some reason */
#endif



#endif

