/****************************************************************************/
/*                                                                          */
/* Copyright 1992 Simmule Turner and Rich Salz.  All rights reserved.       */
/*                                                                          */
/* This software is not subject to any license of the American Telephone    */
/* and Telegraph Company or of the Regents of the University of California. */
/*                                                                          */
/* Permission is granted to anyone to use this software for any purpose on  */
/* any computer system, and to alter it and redistribute it freely, subject */
/* to the following restrictions:                                           */
/* 1. The authors are not responsible for the consequences of use of this   */
/*    software, no matter how awful, even if they arise from flaws in it.   */
/* 2. The origin of this software must not be misrepresented, either by     */
/*    explicit claim or by omission.  Since few users ever read sources,    */
/*    credits must appear in the documentation.                             */
/* 3. Altered versions must be plainly marked as such, and must not be      */
/*    misrepresented as being the original software.  Since few users       */
/*    ever read sources, credits must appear in the documentation.          */
/* 4. This notice may not be removed or altered.                            */
/*                                                                          */
/****************************************************************************/
/*                                                                          */
/*  This is a line-editing library, it can be linked into almost any        */
/*  program to provide command-line editing and recall.                     */
/*                                                                          */
/*  Posted to comp.sources.misc Sun, 2 Aug 1992 03:05:27 GMT                */
/*      by rsalz@osf.org (Rich $alz)                                        */
/*                                                                          */
/****************************************************************************/
/*                                                                          */
/*  The version contained here has some modifications by awb@cstr.ed.ac.uk  */
/*  (Alan W Black) in order to integrate it with the Edinburgh Speech Tools */
/*  library and Scheme-in-one-defun in particular.  All modifications to    */
/*  to this work are continued with the same copyright above.  That is      */
/*  this version of editline does not have the the "no commercial use"      */
/*  restriction that some of the rest of the EST library may have           */
/*  awb Dec 30 1998                                                         */
/*                                                                          */
/****************************************************************************/
/*  $Revision: 1.3 $
**
**  Internal header file for editline library.
**
*/

#if !defined(INCLUDE_EDITLINEP_H)
#define INCLUDE_EDITLINEP_H 1

/* Include the public file first to check it is stand-alone. */

#include "editline.h"

/* if we are in an autoconf controled compilation
 * this was created by autoconf, otherwise it was hand crafted
 * for our situation (eg Visual C++ or inside EST
 */

#    include "editline_config.h"

/* I presume if we have it we want it
 */

#if defined(HAVE_LIBTERMCAP) || defined(HAVE_LIBNCURSES)
#    define USE_TERMCAP 1
#endif

#include <stdio.h>

#if	defined(STDC_HEADERS)
#include <stdlib.h>
#include <string.h>
#endif	/* defined(STDC_HEADERS) */

/*
 * Currently three flavours of system are understood.
 * Unix works, Win32 sort of does and OS/9 has probably been broken. 
 */

#if	defined(SYSTEM_IS_UNIX)
#include "el_unix.h"
#define SYSTEM_OK
#endif	/* defined(SYSTEM_IS_UNIX) */

#if	defined(SYSTEM_IS_WIN32)
#include "el_win32.h"
#define SYSTEM_OK
#endif	/* defined(SYSTEM_IS_WIN32) */

#if	defined(SYSTEM_IS_OS9)
#include "el_os9.h"
#define SYSTEM_OK
#endif	/* defined(SYSTEM_IS__OS9) */

#if !defined(SYSTEM_OK)
#    error "No support for this system"
#else
#    undef SYSTEM_OK
#endif

#if	!defined(SIZE_T)
#define SIZE_T	unsigned int
#endif	/* !defined(SIZE_T) */

typedef unsigned char	CHAR;

#if	defined(HIDE)
#define STATIC	static
#else
#define STATIC	/* NULL */
#endif	/* !defined(HIDE) */

#define MEM_INC		64
#define SCREEN_INC	256

#ifdef HAVE_WALLOC

/* CSTR EST replacements -- awb */
#include "EST_walloc.h"
#define DISPOSE(p) wfree(p)
#define NEW(T,c) walloc(T,c)
#define RENEW(p,T,c) (p = wrealloc(p,T,c))
#define STRDUP(X) wstrdup(X)

#else

#define DISPOSE(p)	free((char *)(p))
#define NEW(T, c)	\
	((T *)malloc((unsigned int)(sizeof (T) * (c))))
#define RENEW(p, T, c)	\
	(p = (T *)realloc((char *)(p), (unsigned int)(sizeof (T) * (c))))
#define STRDUP(X) strdup(X)

#endif

#define COPYFROMTO(new, p, len)	\
	(void)memcpy((char *)(new), (char *)(p), (int)(len))

/*
**  Variables and routines internal to this package.
*/
extern int	el_eof;
extern int	el_erase;
extern int	el_intr;
extern int	el_kill;
extern int	el_quit;
#if	defined(DO_SIGTSTP)
extern int	el_susp;
#endif	/* defined(DO_SIGTSTP) */
extern int      el_no_echo;    /* e.g under emacs, don't echo except prompt */
extern char	*el_complete(char *pathname, int *unique);
extern int	el_list_possib(char *pathname,char ***avp);
extern char *editline_history_file;
void el_ttyset(int Reset);
void el_add_slash(char *path,char *p);
int el_is_directory(char *path);
void do_user_intr();

#if	!defined(STDC_HEADERS)
extern char	*getenv();
extern char	*malloc();
extern char	*realloc();
extern char	*memcpy();
extern char	*strcat();
extern char	*strchr();
extern char	*strrchr();
extern char	*strcpy();
extern char	*strdup();
extern int	strcmp();
extern int	strlen();
extern int	strncmp();

#endif	/* !defined(STDC_HEADERS) */

#endif
