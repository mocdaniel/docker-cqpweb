

 /************************************************************************/
 /*                                                                      */
 /*                Centre for Speech Technology Research                 */
 /*                     University of Edinburgh, UK                      */
 /*                       Copyright (c) 1996,1997                        */
 /*                        All Rights Reserved.                          */
 /*                                                                      */
 /*  Permission to use, copy, modify, distribute this software and its   */
 /*  documentation for research, educational and individual use only, is */
 /*  hereby granted without fee, subject to the following conditions:    */
 /*   1. The code must retain the above copyright notice, this list of   */
 /*      conditions and the following disclaimer.                        */
 /*   2. Any modifications must be clearly marked as such.               */
 /*   3. Original authors' names are not deleted.                        */
 /*  This software may not be used for commercial purposes without       */
 /*  specific prior written permission from the authors.                 */
 /*                                                                      */
 /*  THE UNIVERSITY OF EDINBURGH AND THE CONTRIBUTORS TO THIS WORK       */
 /*  DISCLAIM ALL WARRANTIES WITH REGARD TO THIS SOFTWARE, INCLUDING     */
 /*  ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS, IN NO EVENT  */
 /*  SHALL THE UNIVERSITY OF EDINBURGH NOR THE CONTRIBUTORS BE LIABLE    */
 /*  FOR ANY SPECIAL, INDIRECT OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES   */
 /*  WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN  */
 /*  AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION,         */
 /*  ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF      */
 /*  THIS SOFTWARE.                                                      */
 /*                                                                      */
 /************************************************************************/
 /*		A Hand crafted config for Visual C                       */
 /* ---------------------------------------------------------------------*/
 /*                 Author: Richard Caley                                */
 /************************************************************************/
 
/* Define if the closedir function returns void instead of int.  */
/* #undef CLOSEDIR_VOID */

/* Define if you have the ANSI C header files.  */
#define STDC_HEADERS 1

/* Are we compiling under windows */
#define SYSTEM_IS_WIN32 1

/* How do we control the tty */
#define HAVE_TCGETATTR 1

/* Make Arrow keys work */
#define ANSI_ARROWS 1

/* Make local functions static */
#define HIDE 1

/* Define if you have the <dirent.h> header file.  */
#define HAVE_DIRENT_H 1

/* Define if you have the <ndir.h> header file.  */
/* #undef HAVE_NDIR_H */

/* Define if you have the <sys/dir.h> header file.  */
/* #undef HAVE_SYS_DIR_H */

/* Define if you have the <sys/ndir.h> header file.  */
/* #undef HAVE_SYS_NDIR_H */

/* Define if you have the <walloc.h> header file.  */
/* #undef HAVE_WALLOC_H */

/* We don't have termcap of course, but we have some
 * functions which pretend to be termcap
 */
#define HAVE_LIBTERMCAP 1

