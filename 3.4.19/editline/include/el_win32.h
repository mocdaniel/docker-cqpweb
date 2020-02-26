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
/*  This version editline does not have the the "no commercial use"         */
/*  restriction that some of the rest of the EST library may have           */
/*  awb Dec 30 1998                                                         */
/*                                                                          */
/****************************************************************************/
/*  $Revision: 1.1 $
**
**  Editline system header file for Unix.
*/

#define CRLF		"\r\n"
#define FORWARD		STATIC

#include <sys/types.h>
#include <sys/stat.h>

#if defined(SYSTEM_IS_WIN32)
#else

/* Copied from autoconf documentation. Claims to cope with lots of cases */
#  if HAVE_DIRENT_H
#   include <dirent.h>
#   define NAMLEN(dirent) strlen((dirent)->d_name)
#  else
#   define dirent direct
#   define NAMLEN(dirent) (dirent)->d_namlen
#   if HAVE_SYS_NDIR_H
#    include <sys/ndir.h>
#   endif
#   if HAVE_SYS_DIR_H
#    include <sys/dir.h>
#   endif
#   if HAVE_NDIR_H
#    include <ndir.h>
#   endif
#  endif
    typedef struct dirent	DIRENTRY;
#endif 

#if	!defined(S_ISDIR)
#define S_ISDIR(m)		(((m) & S_IFMT) == S_IFDIR)
#endif	
