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
/*  $Revision: 1.13 $
**
**  Public header file for editline library.
**
*/

#if !defined(INCLUDE_EDITLINE_H)
#define INCLUDE_EDITLINE_H 1

#if	!defined(EL_CONST)
#if	defined(__STDC__)
#define EL_CONST	const
#else
#define EL_CONST
#endif	/* defined(__STDC__) */
#endif	/* !defined(EL_CONST) */

/*
**  Command status codes (moved from editlineP.h).
*/
typedef enum _EL_STATUS {
    CSdone, CSeof, CSmove, CSdispatch, CSstay, CSsignal
} EL_STATUS;

typedef EL_STATUS (*El_Keymap_Function)();


/* Added prototypes for available functions in editline -- awb */
char * readline(EL_CONST char* prompt);

void add_history(EL_CONST char *p);
void read_history(EL_CONST char *history_file);
void write_history(EL_CONST char *history_file);

char *el_current_sym();
void el_redisplay();
void el_bind_key_in_metamap(char c, El_Keymap_Function func);

typedef char **EL_USER_COMPLETION_FUNCTION_TYPE(char *text,int start, int end);
extern EL_USER_COMPLETION_FUNCTION_TYPE*el_user_completion_function;

/* Publicly available variables - rjc */

extern int	el_user_intr;  /* with SIGINT if non-zero */


#endif
