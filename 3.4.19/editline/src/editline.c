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
/*  library and Scheme-in-one-defun in particularm though these changes     */
/*  have a much more general use that just us.  All modifications to        */
/*  to this work are continued with the same copyright above.  That is      */
/*  this version of editline does not have the the "no commercial use"      */
/*  restriction that some of the rest of the EST library may have           */
/*  awb Dec 30 1998                                                         */
/*                                                                          */
/*  Specific additions (there are other smaller ones too, all marked):      */
/*    some ansificiation and prototypes added                               */
/*    storage and retrieval of history over sessions                        */
/*    user definable history completion                                     */
/*    possibles listing in completion                                       */
/*    reverse incremental search                                            */
/*    lines longer than window width (mostly)                               */
/*    reasonable support for 8 bit chars in languages other than English    */
/*                                                                          */
/****************************************************************************/

/*  $Revision: 1.19 $
**
**  Main editing routines for editline library.
*/
#include "editlineP.h"

#ifdef SYSTEM_IS_UNIX
#    include <unistd.h>
#endif

#ifdef SYSTEM_IS_WIN32
#   include <io.h>
/* And no doubt some others */
#else
extern int kill(int,int);
#endif

#include <ctype.h>

/*
**  Manifest constants.
*/
#define SCREEN_WIDTH	80
#define SCREEN_ROWS	24
#define NO_ARG		(-1)
#define DEL		127
#define TAB		'\t'
#define ESC		0x1b
#define CTL(x)		(char)((x) & 0x1F)
#define ISCTL(x)	((x) && (x) < ' ')
#define UNCTL(x)	(char)((x) + 64)
#define META(x)		(char)((x) | 0x80)
#define ISMETA(x)	((x) & 0x80)
#define UNMETA(x)	(char)((x) & 0x7F)
#define MAPSIZE		33
#define METAMAPSIZE	64

  /* modified by awb to allow speicifcation of history size at run time  */
/* (though only once)                                                  */
int editline_histsize=256;
char *editline_history_file;
/* If this is defined it'll be called for completion first, before the */
/* internal file name completion will be                               */
EL_USER_COMPLETION_FUNCTION_TYPE*el_user_completion_function = NULL;

/*
**  The type of case-changing to perform.
*/
typedef enum _CASE {
    TOupper, TOlower, TOcapitalize
} CASE;

/*
**  Key to command mapping.
*/
typedef struct _KEYMAP {
    CHAR	Key;
    CHAR	Active;
    EL_STATUS	(*Function)();
} KEYMAP;

/*
**  Command history structure.
*/
typedef struct _HISTORY {
    int		Size;
    int		Pos;
    CHAR	**Lines;
} HISTORY;

/*
**  Globals.
*/
int		el_eof;
int		el_erase;
int		el_intr;
int		el_kill;
int		el_quit;
#if	defined(DO_SIGTSTP)
int		el_susp;
#endif	/* defined(DO_SIGTSTP) */

CHAR		el_NIL[] = "";
extern EL_CONST CHAR	*el_Input;
STATIC CHAR		*Line = NULL;
STATIC EL_CONST char	*Prompt = NULL;
STATIC CHAR		*Yanked = NULL;
STATIC char		*Screen = NULL;
/* STATIC char		NEWLINE[]= CRLF; */
STATIC HISTORY		H;
STATIC int		Repeat;
STATIC int		End;
STATIC int		Mark;
STATIC int		OldPoint;
STATIC int		Point;
extern int		el_PushBack;
extern int		el_Pushed;
STATIC int		Signal;
FORWARD KEYMAP		Map[MAPSIZE];
FORWARD KEYMAP		MetaMap[METAMAPSIZE];
STATIC SIZE_T		Length;
STATIC SIZE_T		ScreenCount;
STATIC SIZE_T		ScreenSize;
STATIC CHAR		*backspace = NULL;
STATIC CHAR		*upline = NULL;
STATIC CHAR		*clrpage = NULL;
STATIC CHAR		*downline = NULL;
STATIC CHAR		*move_right = NULL;
STATIC CHAR             *newline = NULL;
STATIC CHAR             *bol = NULL;
STATIC CHAR             *nextline = NULL;
STATIC int		TTYwidth;
STATIC int		TTYrows;
STATIC int              RequireNLforWrap = 1;
STATIC int              el_intr_pending = 0;
int                     el_no_echo = 0;  /* e.g under emacs */

/* A little ansification with prototypes -- awb */
extern void TTYflush();
STATIC void TTYput(EL_CONST CHAR c);
STATIC void TTYputs(EL_CONST CHAR *p);
STATIC void TTYshow(CHAR c);
STATIC void TTYstring(CHAR *p);
extern unsigned int TTYget();
STATIC void TTYinfo();
STATIC void print_columns(int ac, char **av);
STATIC void reposition(int reset);
STATIC void left(EL_STATUS Change);
STATIC void right(EL_STATUS Change);
STATIC EL_STATUS ring_bell();
#if 0
STATIC EL_STATUS do_macro(unsigned int c);
#endif
STATIC EL_STATUS do_forward(EL_STATUS move);
STATIC EL_STATUS do_case(CHAR type);
STATIC EL_STATUS case_down_word();
STATIC EL_STATUS case_up_word();
STATIC void ceol();
STATIC void clear_line();
STATIC EL_STATUS insert_string(CHAR *p);
STATIC CHAR *next_hist();
STATIC CHAR *prev_hist();
STATIC EL_STATUS do_insert_hist(CHAR *p);
STATIC EL_STATUS do_hist(CHAR *(*move)());
STATIC EL_STATUS h_next();
STATIC EL_STATUS h_prev();
STATIC EL_STATUS h_first();
STATIC EL_STATUS h_last();
STATIC int substrcmp(char *text, char *pat, int len);
STATIC CHAR *search_hist(CHAR *search, CHAR *(*move)());
STATIC EL_STATUS h_search();
STATIC EL_STATUS fd_char();
STATIC void save_yank(int begin, int i);
STATIC EL_STATUS delete_string(int count);
STATIC EL_STATUS bk_char();
STATIC EL_STATUS bk_del_char();
STATIC EL_STATUS redisplay();
STATIC EL_STATUS kill_line();
STATIC char *rsearch_hist(char *patt, int *lpos,int *cpos);
STATIC EL_STATUS h_risearch();
STATIC EL_STATUS insert_char(int c);
STATIC EL_STATUS meta();
STATIC EL_STATUS emacs(unsigned int c);
STATIC EL_STATUS TTYspecial(unsigned int c);
STATIC CHAR *editinput();
STATIC void hist_add(CHAR *p);
STATIC EL_STATUS beg_line();
STATIC EL_STATUS del_char();
STATIC EL_STATUS end_line();
STATIC CHAR *find_word();
STATIC EL_STATUS c_complete();
STATIC EL_STATUS c_possible();
STATIC EL_STATUS accept_line();
STATIC EL_STATUS transpose();
STATIC EL_STATUS quote();
STATIC EL_STATUS wipe();
STATIC EL_STATUS mk_set();
STATIC EL_STATUS exchange();
STATIC EL_STATUS yank();
STATIC EL_STATUS copy_region();
STATIC EL_STATUS move_to_char();
STATIC EL_STATUS fd_word();
STATIC EL_STATUS fd_kill_word();
STATIC EL_STATUS bk_word();
STATIC EL_STATUS bk_kill_word();
STATIC int argify(CHAR *line, CHAR ***avp);
STATIC EL_STATUS last_argument();

/* Display print 8-bit chars as `M-x' or as the actual 8-bit char? */
int		el_meta_chars = 0;

/*
**  Declarations.
*/
STATIC CHAR	*editinput();
#if	defined(USE_TERMCAP)
extern char	*getenv();
extern char	*tgetstr();
extern int	tgetent();
extern int	tgetnum();
#endif	/* defined(USE_TERMCAP) */

/*
**  TTY input/output functions.
*/

void TTYflush()
{
    if (ScreenCount) {
	if (el_no_echo == 0)
	    (void)write(1, Screen, ScreenCount);
	ScreenCount = 0;
    }
}

STATIC void TTYput(EL_CONST CHAR c)
{
    Screen[ScreenCount] = c;
    if (++ScreenCount >= ScreenSize - 1) {
	ScreenSize += SCREEN_INC;
	RENEW(Screen, char, ScreenSize);
    }
}

STATIC void TTYputs(EL_CONST CHAR *p)
{
    while (*p)
	TTYput(*p++);
}

STATIC void TTYshow(CHAR c)
{
    if (c == DEL) {
	TTYput('^');
	TTYput('?');
    }
    else if (c == TAB) {
	TTYput('^');
	TTYput('I');
    }
    else if (ISCTL(c)) {
	TTYput('^');
	TTYput(UNCTL(c));
    }
    else if (el_meta_chars && ISMETA(c)) {
	TTYput('M');
	TTYput('-');
	TTYput(UNMETA(c));
    }
    else
	TTYput(c);
}

STATIC void TTYstring(CHAR *p)
{
    while (*p)
	TTYshow(*p++);
}

#if 0
/* Old one line version */
#define TTYback()	(backspace ? TTYputs((CHAR *)backspace) : TTYput('\b'))
#endif

STATIC int printlen(EL_CONST char *p)
{
    int len = 0;

    for (len=0; *p; p++)
	if ((*p == DEL) || (ISCTL(*p)))
	    len += 2;
	else if (el_meta_chars && ISMETA(*p))
	    len += 3;
	else
	    len += 1;

    return len;
}

STATIC int screen_pos()
{
    /* Returns the number of characters printed from begining of line   */
    /* include sthe size of the prompt and and meta/ctl char expansions */
    int p = strlen(Prompt);
    int i;
    
    for (i=0; i < Point; i++)
	if ((Line[i] == DEL) ||
	    (ISCTL(Line[i])))
	    p += 2;
	else if (el_meta_chars && ISMETA(Line[i]))
	    p += 3;
	else
	    p += 1;

    return p;
}

STATIC void TTYback()
{
    /* awb: added upline (if supported) when back goes over line boundary */
    int i;
    int sp = screen_pos();

    if (upline && sp && (sp%TTYwidth == 0))
    {   /* move up a line and move to the end */
	TTYputs(upline);
	TTYputs(bol);
	for (i=0; i < TTYwidth; i++)
	    TTYputs(move_right);
    }
    else if (backspace)
	TTYputs((CHAR *)backspace);
    else
	TTYput('\b');
}

STATIC void TTYinfo()
{
    static int		init;
#if	defined(USE_TERMCAP)
    char		*term;
    char		*buff;
    char		*buff2;
    char		*bp;
    char		*p;
#endif	/* defined(USE_TERMCAP) */
#if	defined(TIOCGWINSZ)
    struct winsize	W;
#endif	/* defined(TIOCGWINSZ) */

    if (init) {
#if	defined(TIOCGWINSZ)
	/* Perhaps we got resized. */
	if (ioctl(0, TIOCGWINSZ, &W) >= 0
	 && W.ws_col > 0 && W.ws_row > 0) {
	    TTYwidth = (int)W.ws_col;
	    TTYrows = (int)W.ws_row;
	}
#endif	/* defined(TIOCGWINSZ) */
	return;
    }
    init++;

    TTYwidth = TTYrows = 0;
#if	defined(USE_TERMCAP)
    buff = NEW(char,2048);
    buff2 = NEW(char,2048);
    bp = &buff2[0];
    if ((term = getenv("TERM")) == NULL)
	term = "dumb";
    if (tgetent(buff, term) < 0) {
       TTYwidth = SCREEN_WIDTH;
       TTYrows = SCREEN_ROWS;
       return;
    }
    p = tgetstr("le", &bp);
    backspace = (CHAR *)(p ? strdup(p) : NULL);
    backspace = (CHAR *)tgetstr("le", &bp);
    upline = (CHAR *)tgetstr("up", &bp);
    clrpage = (CHAR *)tgetstr("cl", &bp);
    nextline = (CHAR *)tgetstr("nl", &bp);
    if (nextline==NULL)
      nextline = (CHAR *)"\n";
    if (strncmp(term, "pcansi", 6)==0 || strncmp(term, "cygwin", 6)==0)
    {
	bol = (CHAR *)"\033[0G";
	RequireNLforWrap = 0; /* doesn't require nl to get to next line */
    }
    else
	bol = (CHAR *)tgetstr("cr", &bp);
    if (bol==NULL)
      bol = (CHAR *)"\r";

    newline= NEW(CHAR, 20);
    strcpy((char *)newline,(char *)bol);
    strcat((char *)newline,(char *)nextline);

    downline = (CHAR *)newline;
    move_right = (CHAR *)tgetstr("nd", &bp);
    if (!move_right || !downline) 
	upline = NULL;  /* terminal doesn't support enough so fall back */
    TTYwidth = tgetnum("co");
    TTYrows = tgetnum("li");
#endif	/* defined(USE_TERMCAP) */

#if	defined(TIOCGWINSZ)
    if (ioctl(0, TIOCGWINSZ, &W) >= 0) {
	TTYwidth = (int)W.ws_col;
	TTYrows = (int)W.ws_row;
    }
#endif	/* defined(TIOCGWINSZ) */

    if (TTYwidth <= 0 || TTYrows <= 0) {
	TTYwidth = SCREEN_WIDTH;
	TTYrows = SCREEN_ROWS;
    }
}


/*
**  Print an array of words in columns.
*/
STATIC void print_columns(int ac, char **av)
{
    CHAR	*p;
    int		i,c;
    int		j;
    int		k;
    int		len;
    int		skip;
    int		longest;
    int		cols;
    char info1[1024];

    if (ac > 99)
    {
	TTYputs((EL_CONST CHAR *)newline);
	sprintf(info1,"There are %d possibilities.  Do you really \n",ac);
	TTYputs((EL_CONST CHAR *)info1);
	TTYputs((EL_CONST CHAR *)"want to see them all (y/n) ? ");
	while (((c = TTYget()) != EOF) && ((strchr("YyNn ",c) == NULL)))
	    ring_bell();
	if (strchr("Nn",c) != NULL)
	{
	    TTYputs((EL_CONST CHAR *)newline);
	    return;
	}
    }

    /* Find longest name, determine column count from that. */
    for (longest = 0, i = 0; i < ac; i++)
	if ((j = strlen((char *)av[i])) > longest)
	    longest = j;
    cols = TTYwidth / (longest + 3);
    if (cols < 1) cols = 1;

    TTYputs((EL_CONST CHAR *)newline);
    for (skip = ac / cols + 1, i = 0; i < skip; i++) {
	for (j = i; j < ac; j += skip) {
	    for (p = (CHAR *)av[j], len = strlen((char *)p), k = len; 
		 --k >= 0; p++)
		TTYput(*p);
	    if (j + skip < ac)
		while (++len < longest + 3)
		    TTYput(' ');
	}
	TTYputs((EL_CONST CHAR *)newline);
    }
}

STATIC void reposition(int reset)
{
    int		i,PPoint;
    int pos;
    char ppp[2];

    if (reset)
    {
	TTYputs(bol);
	for (i=screen_pos()/TTYwidth; i > 0; i--)
	    if (upline) TTYputs(upline);
    }
    TTYputs((EL_CONST CHAR *)Prompt);
    pos = printlen(Prompt);
    ppp[1] = '\0';
    for (i = 0; i < End; i++)
    {
	ppp[0] = Line[i];
	TTYshow(Line[i]);
	pos += printlen(ppp);
	if ((pos%TTYwidth) == 0)
	    if (RequireNLforWrap && downline) TTYputs(downline);
    }
    PPoint = Point;
    for (Point = End; 
	 Point > PPoint; 
	 Point--)
    {
	if (el_meta_chars && ISMETA(Line[Point]))
	{
	    TTYback();
	    TTYback();
	}
	else if (ISCTL(Line[Point]))
	    TTYback();
	TTYback();
    }
    Point = PPoint;
}

STATIC void left(EL_STATUS Change)
{
    CHAR c;

    TTYback();
    if (Point) {
        c = Line[Point - 1];

	if (c == TAB) {
	    TTYback();
	}
	else if (ISCTL(c))
	    TTYback();
        else if (el_meta_chars && ISMETA(c)) {
	    TTYback();
	    TTYback();
	}
    }
    if (Change == CSmove)
	Point--;
}

STATIC void right(EL_STATUS Change)
{
    TTYshow(Line[Point]);
    if (Change == CSmove)
	Point++;
    if ((screen_pos())%TTYwidth == 0)
	if (downline && RequireNLforWrap) TTYputs(downline);    
}

STATIC EL_STATUS ring_bell()
{
    TTYput('\07');
    TTYflush();
    return CSstay;
}

#if 0 
STATIC EL_STATUS do_macro(unsigned int c)
{
    CHAR		name[4];

    name[0] = '_';
    name[1] = c;
    name[2] = '_';
    name[3] = '\0';

    if ((el_Input = (CHAR *)getenv((char *)name)) == NULL) {
	el_Input = el_NIL;
	return ring_bell();
    }
    return CSstay;
}
#endif

STATIC EL_STATUS do_forward(EL_STATUS move)
{
    int		i;
    CHAR	*p;
    (void) move;

    i = 0;
    do {
	p = &Line[Point];
	for ( ; Point < End && (*p == ' ' || !isalnum(*p)); p++)
	    right(CSmove);

	for (; Point < End && isalnum(*p); p++)
	    right(CSmove);

	if (Point == End)
	    break;
    } while (++i < Repeat);

    return CSstay;
}

STATIC EL_STATUS do_case(CHAR type)
{
    int		i;
    int		end;
    int		count;
    CHAR	*p;
    int OP;

    OP = Point;
    (void)do_forward(CSstay);
    if (OP != Point) {
	if ((count = Point - OP) < 0)
	    count = -count;
	for ( ; Point > OP; Point --)
	    TTYback();
	if ((end = Point + count) > End)
	    end = End;
	for (i = Point, p = &Line[Point]; Point < end; p++) {
	    if ((type == TOupper) ||
		((type == TOcapitalize) && (Point == i)))
	    {
		if (islower(*p))
		    *p = toupper(*p);
	    }
	    else if (isupper(*p))
		*p = tolower(*p);
	    right(CSmove);
	}
    }
    return CSstay;
}

STATIC EL_STATUS case_down_word()
{
    return do_case(TOlower);
}

STATIC EL_STATUS case_up_word()
{
    return do_case(TOupper);
}

STATIC EL_STATUS case_cap_word()
{
    return do_case(TOcapitalize);
}

STATIC void ceol()
{
    int		extras;
    int		i, PPoint;
    CHAR	*p;

    PPoint = Point;
    for (extras = 0, i = Point, p = &Line[i]; i < End; i++, p++) {
	Point++;
	TTYput(' ');

	if (*p == TAB) {
	    TTYput(' ');
	    extras++;
	}
	else if (ISCTL(*p)) {
	    TTYput(' ');
	    extras++;
	}
	else if (el_meta_chars && ISMETA(*p)) {
	    TTYput(' ');
	    TTYput(' ');
	    extras += 2;
	}
	else if ((screen_pos())%TTYwidth == 0)
	    if (downline && RequireNLforWrap) TTYputs(downline);
    }

    Point = End;
    for (Point = End; 
	 Point > PPoint; 
	 Point--)
    {
	if (el_meta_chars && ISMETA(Line[Point-1]))
	{
	    TTYback();
	    TTYback();
	}
	else if (ISCTL(Line[Point-1]))
	    TTYback();
	TTYback();
    }
    Point = PPoint;

}

STATIC void clear_line()
{
    int i;
    TTYputs(bol);
    for (i=screen_pos()/TTYwidth; i > 0; i--)
	if (upline) TTYputs(upline);
    for (i=0; i < strlen(Prompt); i++)
	TTYput(' ');
    Point = 0;
    ceol();
    TTYputs(bol);
    /* In case the prompt is more than one line long */
    for (i=screen_pos()/TTYwidth; i > 0; i--)
	if (upline) TTYputs(upline);
    Point = 0;
    End = 0;
    Line[0] = '\0';
}

STATIC EL_STATUS insert_string(CHAR *p)
{
    SIZE_T	len;
    int		i,pos0,pos1;
    CHAR	*new;
    CHAR	*q;

    len = strlen((char *)p);
    if (End + len >= Length) {
	if ((new = NEW(CHAR, Length + len + MEM_INC)) == NULL)
	    return CSstay;
	if (Length) {
	    COPYFROMTO(new, Line, Length);
	    DISPOSE(Line);
	}
	Line = new;
	Length += len + MEM_INC;
    }

    for (q = &Line[Point], i = End - Point; --i >= 0; )
	q[len + i] = q[i];
    COPYFROMTO(&Line[Point], p, len);
    End += len;
    Line[End] = '\0';
    pos0 = screen_pos();
    pos1 = printlen((char *)&Line[Point]);
    TTYstring(&Line[Point]);
    Point += len;
    if ((pos0+pos1)%TTYwidth == 0)
	if (downline && RequireNLforWrap) TTYputs(downline);
    /* if the line is longer than TTYwidth this may put the cursor   */
    /* on the next line and confuse some other parts, so put it back */ 
    /* at Point                                                      */
    if (upline && (Point != End))
    {
	pos0 = screen_pos();
	pos1 = printlen((char *)&Line[Point]);
	for (i=((pos0%TTYwidth)+pos1)/TTYwidth; i > 0; i--)
	    if (upline) TTYputs(upline);
	TTYputs(bol);
	for (i=0 ; i < (pos0%TTYwidth); i++)
	    TTYputs(move_right);
    }

    return Point == End ? CSstay : CSmove;
}


STATIC CHAR *next_hist()
{
    return H.Pos >= H.Size - 1 ? NULL : H.Lines[++H.Pos];
}

STATIC CHAR *prev_hist()
{
    return H.Pos == 0 ? NULL : H.Lines[--H.Pos];
}

STATIC EL_STATUS do_insert_hist(CHAR *p)
{
    int i;
    if (p == NULL)
	return ring_bell();
    for (i=screen_pos()/TTYwidth; i > 0; i--)
	if (upline) TTYputs(upline);
    Point = 0;
    reposition(1);
    ceol();
    End = 0;
    return insert_string(p);
}

STATIC EL_STATUS do_hist(CHAR *(*move)())
{
    CHAR	*p;
    int		i;

    i = 0;
    do {
	if ((p = (*move)()) == NULL)
	    return ring_bell();
    } while (++i < Repeat);
    return do_insert_hist(p);
}

STATIC EL_STATUS h_next()
{
    return do_hist(next_hist);
}

STATIC EL_STATUS h_prev()
{
    return do_hist(prev_hist);
}

STATIC EL_STATUS h_first()
{
    return do_insert_hist(H.Lines[H.Pos = 0]);
}

STATIC EL_STATUS h_last()
{
    return do_insert_hist(H.Lines[H.Pos = H.Size - 1]);
}

/*
**  Return zero if pat appears as a substring in text.
*/
STATIC int substrcmp(char *text, char *pat, int len)
{
    CHAR	c;

    if ((c = *pat) == '\0')
        return *text == '\0';
    for ( ; *text; text++)
        if (*text == c && strncmp(text, pat, len) == 0)
            return 0;
    return 1;
}

STATIC CHAR *search_hist(CHAR *search, CHAR *(*move)())
{
    static CHAR	*old_search;
    int		len;
    int		pos;
    int		(*match)();
    char	*pat;

    /* Save or get remembered search pattern. */
    if (search && *search) {
	if (old_search)
	    DISPOSE(old_search);
	old_search = (CHAR *)STRDUP((const char *)search);
    }
    else {
	if (old_search == NULL || *old_search == '\0')
            return NULL;
	search = old_search;
    }

    /* Set up pattern-finder. */
    if (*search == '^') {
	match = strncmp;
	pat = (char *)(search + 1);
    }
    else {
	match = substrcmp;
	pat = (char *)search;
    }
    len = strlen(pat);

    for (pos = H.Pos; (*move)() != NULL; )
	if ((*match)((char *)H.Lines[H.Pos], pat, len) == 0)
            return H.Lines[H.Pos];
    H.Pos = pos;
    return NULL;
}

STATIC EL_STATUS h_search()
{
    static int	Searching;
    EL_CONST char	*old_prompt;
    CHAR	*(*move)();
    CHAR	*p;

    if (Searching)
	return ring_bell();
    Searching = 1;

    clear_line();
    old_prompt = Prompt;
    Prompt = "Search: ";
    TTYputs((EL_CONST CHAR *)Prompt);
    move = Repeat == NO_ARG ? prev_hist : next_hist;
    p = search_hist(editinput(), move);
    clear_line();
    Prompt = old_prompt;
    TTYputs((EL_CONST CHAR *)Prompt);

    Searching = 0;
    return do_insert_hist(p);
}

STATIC EL_STATUS fd_char()
{
    int		i;

    i = 0;
    do {
	if (Point >= End)
	    break;
	right(CSmove);
    } while (++i < Repeat);
    return CSstay;
}

STATIC void save_yank(int begin, int i)
{
    if (Yanked) {
	DISPOSE(Yanked);
	Yanked = NULL;
    }

    if (i < 1)
	return;

    if ((Yanked = NEW(CHAR, (SIZE_T)i + 1)) != NULL) {
	COPYFROMTO(Yanked, &Line[begin], i);
	Yanked[i] = '\0';
    }
}

STATIC EL_STATUS delete_string(int count)
{
    int		i;
    int pos0,pos1,q;
    char	*tLine;

    if (count <= 0 || End == Point)
	return ring_bell();

    if (Point + count > End && (count = End - Point) <= 0)
	return CSstay;

    if (count > 1)
	save_yank(Point, count);

    tLine = STRDUP((char *)Line);
    ceol();
    for (q = Point, i = End - (Point + count) + 1; --i >= 0; q++)
	Line[q] = tLine[q+count];
    DISPOSE(tLine);
    End -= count;
    pos0 = screen_pos();
    pos1 = printlen((char *)&Line[Point]);
    TTYstring(&Line[Point]);
    if ((pos1 > 0) && (pos0+pos1)%TTYwidth == 0)
	if (downline && RequireNLforWrap) TTYputs(downline);
    /* if the line is longer than TTYwidth this may put the cursor   */
    /* on the next line and confuse some other parts, so put it back */ 
    /* at Point                                                      */
    if (upline)
    {
	for (i=((pos0%TTYwidth)+pos1)/TTYwidth; i > 0; i--)
	    if (upline) TTYputs(upline);
	TTYputs(bol);
	for (i=0 ; i < (pos0%TTYwidth); i++)
	    TTYputs(move_right);
    }

    return CSmove;
}

STATIC EL_STATUS bk_char()
{
    int		i;

    i = 0;
    do {
	if (Point == 0)
	    break;
	left(CSmove);
    } while (++i < Repeat);

    return CSstay;
}

STATIC EL_STATUS bk_del_char()
{
    int		i;

    i = 0;
    do {
	if (Point == 0)
	    break;
	left(CSmove);
    } while (++i < Repeat);

    return delete_string(i);
}

STATIC EL_STATUS redisplay()
{
    if (clrpage) TTYputs(clrpage);
    else
	TTYputs((CHAR *)newline);
/*    TTYputs((CHAR *)Prompt);
    TTYstring(Line); */
    return CSmove;
}

STATIC EL_STATUS redisplay_no_nl()
{
    TTYputs((CHAR *)bol);
    TTYputs((CHAR *)Prompt);
    TTYstring(Line);
    return CSmove;
}

STATIC EL_STATUS
toggle_meta_mode()
{
    el_meta_chars = !el_meta_chars;
    return redisplay();
}
  
STATIC EL_STATUS kill_line()
{
    int		i;

    if (Repeat != NO_ARG) {
	if (Repeat < Point) {
	    i = Point;
	    Point = Repeat;
	    reposition(1);
	    (void)delete_string(i - Point);
	}
	else if (Repeat > Point) {
	    right(CSmove);
	    (void)delete_string(Repeat - Point - 1);
	}
	return CSmove;
    }

    save_yank(Point, End - Point);
    ceol();
    Line[Point] = '\0';
    End = Point;
    return CSstay;
}

STATIC char *rsearch_hist(char *patt, int *lpos,int *cpos)
{
    /* Extention by awb to do reverse incremental searches */

    for (; *lpos > 0; (*lpos)--)
    {
	for ( ; (*cpos) >= 0 ; (*cpos)--)
	{
/*	    fprintf(stderr,"comparing %d %s %s\n",*lpos,patt,H.Lines[*lpos]+*cpos); */
	    if (strncmp(patt,(char *)H.Lines[*lpos]+*cpos,strlen(patt)) == 0)
	    {   /* found a match */
		return (char *)H.Lines[*lpos];
	    }
	}
	if ((*lpos) > 0)
	    *cpos = strlen((char *)H.Lines[(*lpos)-1]);
    }
    return NULL;  /* no match found */
}

STATIC EL_STATUS h_risearch()
{
    EL_STATUS	s;
    EL_CONST char	*old_prompt;
    char *pat, *hist, *nhist;
    char *nprompt;
    int patsize, patend, i;
    CHAR	c;
    int lpos,cpos;

    old_prompt = Prompt;

    nprompt = NEW(char,80+160);
    pat = NEW(char,80);
    patend=0;
    patsize=80;
    pat[0] = '\0';
    hist = "";
    lpos = H.Pos;   /* where the search has to start from */
    cpos = strlen((char *)H.Lines[lpos]);
    do 
    {
	sprintf(nprompt,"(reverse-i-search)`%s': ",pat);
	Prompt = nprompt;
	kill_line();
	do_insert_hist((CHAR *)hist);
	if (patend != 0)
	    for (i=strlen((char *)H.Lines[lpos]); i>cpos; i--) bk_char();
	c = TTYget();
	if ((c >= ' ') || (c == CTL('R')))
	{
	    if (c == CTL('R'))
		cpos--;
	    else if (patend < 79)
	    {
		pat[patend]=c;
		patend++;
		pat[patend]='\0';
	    }
	    else  /* too long */
	    {
		ring_bell();
		continue;
	    }
	    nhist = rsearch_hist(pat,&lpos,&cpos);
	    if (nhist != NULL)
	    {
		hist = nhist;
		H.Pos = lpos;
	    }
	    else
	    {   /* oops, no match */
		ring_bell();
		if (c != CTL('R'))
		{
		    patend--;
		    pat[patend] = '\0';
		}
	    }
	}
    } while ((c >= ' ') || (c == CTL('R')));
    
    /* Tidy up */
    clear_line();
    Prompt = old_prompt;
    TTYputs((CHAR *)Prompt);
    DISPOSE(nprompt);

    kill_line();
    s = do_insert_hist((CHAR *)hist);
    if (patend != 0)
	for (i=strlen((char *)H.Lines[lpos]); i>cpos; i--) s = bk_char();
    if (c != ESC)
	return emacs(c);
    else
	return s;
}

STATIC EL_STATUS insert_char(int c)
{
    EL_STATUS	s;
    CHAR	buff[2];
    CHAR	*p;
    CHAR	*q;
    int		i;

    if (Repeat == NO_ARG || Repeat < 2) {
	buff[0] = c;
	buff[1] = '\0';
	return insert_string(buff);
    }

    if ((p = NEW(CHAR, Repeat + 1)) == NULL)
	return CSstay;
    for (i = Repeat, q = p; --i >= 0; )
	*q++ = c;
    *q = '\0';
    Repeat = 0;
    s = insert_string(p);
    DISPOSE(p);
    return s;
}

STATIC EL_STATUS meta()
{
    unsigned int	c;
    KEYMAP		*kp;

    if ((c = TTYget()) == EOF)
	return CSeof;
#if	defined(ANSI_ARROWS)
    /* Also include VT-100 arrows. */
    if (c == '[' || c == 'O')
	switch ((int)(c = TTYget())) {
	default:	return ring_bell();
	case EOF:	return CSeof;
	case 'A':	return h_prev();
	case 'B':	return h_next();
	case 'C':	return fd_char();
	case 'D':	return bk_char();
	}
#endif	/* defined(ANSI_ARROWS) */

    if (isdigit(c)) {
	for (Repeat = c - '0'; (c = TTYget()) != EOF && isdigit(c); )
	    Repeat = Repeat * 10 + c - '0';
	el_Pushed = 1;
	el_PushBack = c;
	return CSstay;
    }

/*    if (isupper(c))
         return do_macro(c); */
    for (OldPoint = Point, kp = MetaMap; kp < &MetaMap[METAMAPSIZE]; kp++)
	if (kp->Key == c && kp->Active)
	    return (*kp->Function)();
    if (el_meta_chars == 0)
    {
	insert_char(META(c));
	return CSmove;
    }

    return ring_bell();
}

STATIC EL_STATUS emacs(unsigned int c)
{
    EL_STATUS		s;
    KEYMAP		*kp;

    /* never defined ... want to be 8-bit clean so we can handle ISO-Latin-* input! (use ESC f instead of M-f) */
#ifdef _NOT_8_BIT_CLEAN
    if (ISMETA(c)) {
	el_Pushed = 1;
	el_PushBack = UNMETA(c);
	return meta();
    }
#endif

    for (kp = Map; kp < &Map[MAPSIZE]; kp++)
	if (kp->Key == c && kp->Active)
	    break;
    s = kp < &Map[MAPSIZE] ? (*kp->Function)() : insert_char((int)c);
    if (!el_Pushed)
	/* No pushback means no repeat count; hacky, but true. */
	Repeat = NO_ARG;
    return s;
}

STATIC EL_STATUS TTYspecial(unsigned int c)
{
    int i;
    
    if (el_meta_chars && ISMETA(c))
	return CSdispatch;

    if (c == el_erase || c == DEL)
	return bk_del_char();
    if (c == el_kill) {
	if (Point != 0) {
	  for (i=screen_pos()/TTYwidth; i > 0; i--)
		if (upline) TTYputs(upline);
	    Point = 0;
	    reposition(1);
	}
	Repeat = NO_ARG;
	return kill_line();
    }
    if (c == el_eof && Point == 0 && End == 0)
	return CSeof;
    if (c == el_intr) {
	Point = End = 0;
	Line[0] = '\0';
	el_intr_pending = 1;
	return CSdone;
    }
    if (c == el_quit) {
      Point = End = 0;
      Line[0] = '\0';
      return redisplay();
    }
#if	defined(DO_SIGTSTP)
    if (c == el_susp) {
      Point = End = 0;
      Line[0] = '\0';
      Signal = SIGTSTP;
      return CSSignal;
    }
#endif	/* defined(DO_SIGTSTP) */
    return CSdispatch;
}

STATIC CHAR *editinput()
{
    unsigned int	c;

    Repeat = NO_ARG;
    OldPoint = Point = Mark = End = 0;
    Line[0] = '\0';

    Signal = -1;
    while ((c = TTYget()) != EOF)
      {
	switch (TTYspecial(c)) {
	case CSdone:
	    return Line;
	case CSeof:
	    return NULL;
	case CSsignal:
	    return el_NIL;
	case CSmove:
	    reposition(1);
	    break;
	case CSstay:
	    break;
	case CSdispatch:
	    switch (emacs(c)) {
	    case CSdone:
		return Line;
	    case CSeof:
		return NULL;
	    case CSsignal:
	        return el_NIL;
	    case CSmove:
		reposition(1);
		break;
	    case CSstay:
	    case CSdispatch:
		break;
	    }
	    break;
	}
      }
    return NULL;
}

STATIC void hist_add(CHAR *p)
{
    int		i;

    if ((p = (CHAR *)STRDUP((char *)p)) == NULL)
	return;
    if (H.Size < editline_histsize)
	H.Lines[H.Size++] = p;
    else {
	DISPOSE(H.Lines[0]);
	for (i = 0; i < editline_histsize - 1; i++)
	    H.Lines[i] = H.Lines[i + 1];
	H.Lines[i] = p;
    }
    H.Pos = H.Size - 1;
}

/* Added by awb 29/12/98 to get saved history file */
void write_history(EL_CONST char *history_file)
{
    FILE *fd;
    int i;

    if ((fd = fopen(history_file,"wb")) == NULL)
    {
	fprintf(stderr,"editline: can't access history file \"%s\"\n",
		history_file);
	return;
    }

    for (i=0; i < H.Size; i++)
	fprintf(fd,"%s\n",H.Lines[i]);
    fclose(fd);
}

void read_history(EL_CONST char *history_file)
{
    FILE *fd;
    char buff[2048];
    int c,i;

    H.Lines = NEW(CHAR *,editline_histsize);
    H.Size = 0;
    H.Pos = 0;
    
    if (history_file==NULL || (fd = fopen(history_file,"rb")) == NULL)
	return; /* doesn't have a history file yet */

    while ((c=getc(fd)) != EOF)
    {
	ungetc(c,fd);
	for (i=0; ((c=getc(fd)) != '\n') && (c != EOF); i++)
	    if (i < 2047)
		buff[i] = c;
	buff[i] = '\0';
	add_history(buff);
    }

    fclose(fd);
}

STATIC char *
read_redirected()
{
    int		size;
    char	*p;
    char	*line;
    char	*end;

    for (size = MEM_INC, p = line = NEW(char, size), end = p + size; ; p++) {
	if (p == end) {
	    size += MEM_INC;
	    p = line = realloc(line, size);
	    end = p + size;
	}
	if (read(0, p, 1) <= 0) {
	    /* Ignore "incomplete" lines at EOF, just like we do for a tty. */
	    free(line);
	    return NULL;
	}
	if (*p == '\n')
	    break;
    }
    *p = '\0';
    return line;
}

/*
**  For compatibility with FSF readline.
*/
/* ARGSUSED0 */
void
el_reset_terminal(char *p)
{
}

void
el_initialize()
{
}

int
el_insert(count, c)
    int		count;
    int		c;
{
    if (count > 0) {
	Repeat = count;
	(void)insert_char(c);
	(void)redisplay_no_nl();
    }
    return 0;
}

int (*el_event_hook)();

int
el_key_action(c, flag)
    int		c;
    char	flag;
{
    KEYMAP	*kp;
    int		size;

    if (ISMETA(c)) {
	kp = MetaMap;
	size = METAMAPSIZE;
    }
    else {
	kp = Map;
	size = MAPSIZE;
    }
    for ( ; --size >= 0; kp++)
	if (kp->Key == c) {
	    kp->Active = c ? 1 : 0;
	    return 1;
	}
    return -1;
}


char *readline(EL_CONST char *prompt)
{
    CHAR	*line;
    int		s;

    if (H.Lines == NULL)
      read_history(NULL);

    if (!isatty(0)) {
	TTYflush();
	return read_redirected();
    }

    if (Line == NULL) {
	Length = MEM_INC;
	if ((Line = NEW(CHAR, Length)) == NULL)
	    return NULL;
    }

    TTYinfo();
    el_ttyset(0);
    hist_add(el_NIL);
    ScreenSize = SCREEN_INC;
    Screen = NEW(char, ScreenSize);
    Prompt = prompt ? prompt : (char *)el_NIL;
    el_intr_pending = 0;
    if (el_no_echo == 1)
    {
	el_no_echo = 0;
	TTYputs((EL_CONST CHAR *)Prompt);
	TTYflush();
	el_no_echo = 1;
    }
    else
	TTYputs((CHAR *)Prompt);
    line = editinput();
    if (line != NULL) {
	line = (CHAR *)STRDUP((char *)line);
	TTYputs((EL_CONST CHAR *)newline);
	TTYflush();
    }
    el_ttyset(1);
    DISPOSE(Screen);
    DISPOSE(H.Lines[--H.Size]);
    if (el_intr_pending)
	do_user_intr();
    if (Signal > 0) {
	s = Signal;
	Signal = 0;
#if !defined(SYSTEM_IS_WIN32)
	(void)kill(getpid(), s);
#endif
    }
    return (char *)line;
}

void
add_history(EL_CONST char *p)
{
    if (p == NULL || *p == '\0')
	return;

#if	defined(UNIQUE_HISTORY)
    if (H.Size && strcmp(p, H.Lines[H.Pos - 1]) == 0)
        return;
#endif	/* defined(UNIQUE_HISTORY) */
    hist_add((CHAR *)p);
}


STATIC EL_STATUS beg_line()
{
    int i;
    if (Point) {
	for (i=screen_pos()/TTYwidth; i > 0; i--)
	    if (upline) TTYputs(upline);
	Point = 0;
	return CSmove;
    }
    return CSstay;
}

STATIC EL_STATUS del_char()
{
    return delete_string(Repeat == NO_ARG ? 1 : Repeat);
}

STATIC EL_STATUS end_line()
{
    if (Point != End) {
	while (Point < End)
	{
	    TTYput(Line[Point]);
	    Point++;
	}
	return CSmove;
    }
    return CSstay;
}

/*
**  Return allocated copy of word under cursor, moving cursor after the
**  word.
*/
STATIC CHAR *find_word()
{
    static char	SEPS[] = "\"#;&|^$=`'{}()<>\n\t ";
    CHAR	*p;
    CHAR	*new;
    SIZE_T	len;

    /* Move forward to end of word. */
    p = &Line[Point];
    for ( ; Point < End && strchr(SEPS, (char)*p) == NULL; Point++, p++)
      right(CSstay);

    /* Back up to beginning of word. */
    for (p = &Line[Point]; p > Line && strchr(SEPS, (char)p[-1]) == NULL; p--)
	continue;
    len = Point - (p - Line) + 1;
    if ((new = NEW(CHAR, len)) == NULL)
	return NULL;
    COPYFROMTO(new, p, len);
    new[len - 1] = '\0';
    return new;
}

void el_redisplay()
{
    reposition(0);  /* redisplay assuming already on newline */
}

char *el_current_sym()
{
    /* Get current symbol at point -- awb*/
    char *symbol = NULL;
    int i,j;

    if (End == 0)
	return NULL;
    if (Point == End)
	i=Point-1;
    else
	i=Point;
	
    for ( ;
	 ((i >= 0) &&
	  (strchr("()' \t\n\r",Line[i]) != NULL));
	 i--);
    /* i will be on final or before final character */
    if (i < 0)
	return NULL;
    /* But if its not at the end of the current symbol move it there */
    for (; i < End; i++)
	if (strchr("()' \t\n\r\"",Line[i]) != NULL)
	    break;
    for (j=i-1; j >=0; j--)
	if (strchr("()' \t\n\r\"",Line[j]) != NULL)
	    break;

    symbol = NEW(char,i-j);
    strncpy(symbol,(char *)&Line[j+1],i-(j+1));
    symbol[i-(j+1)] = '\0';

    return symbol;
}

static char *completion_to_ambiguity(int index,char **possibles)
{
    /* Find the string that extends from index in possibles until an */
    /* ambiguity is found                           -- awb     */
    char *p;
    int e,i;
    int extending;

    extending = 1;
    e = index;

    for ( ; extending; e++)
    {
	for (i=0; possibles[i] != NULL; i++)
	    if (possibles[i][e] != possibles[0][e])
	    {
		extending = 0;
		e--;
		break;
	    }
    }

    if (e==index)
	return NULL;  /* already at ambiguity */
    else
    {
	p = NEW(char,(e-index)+1);
 	strncpy(p,possibles[0]+index,e-index);
	p[e-index] = '\0';
	return p;
    }
}

static char **el_file_completion_function(char * text, int start, int end)
{
    /* Interface to editline el_list_possib which looks up possibes */
    /* file name completion                                         */
    char *word;
    char **matches1;
    char **matches2;
    int ac,i;

    word = NEW(char,(end-start)+1);
    strncpy(word,text+start,end-start);
    word[end-start]='\0';

    ac = el_list_possib(word,&matches1);
    DISPOSE(word);
    if (ac == 0)
	return NULL;
    else
    {
	matches2 = NEW(char *,ac+1);
	for (i=0; i < ac; i++)
	    matches2[i] = matches1[i];
	matches2[i] = NULL;
	DISPOSE(matches1);
	return matches2;
    }
}

STATIC EL_STATUS c_complete()
{
    /* Modified by awb 30/12/98 to allow listing of possibles and */
    /* a user definable completion method                         */
    char	*p;
    char	*word;
    int		start;
    char        **possibles=NULL;
    int         possiblesc=0;
    int started_with_quote = 0;
    EL_STATUS	s;
    int i;

    for (start=Point; start > 0; start--)
	if (strchr("()' \t\n\r\"",Line[start-1]) != NULL)
	    break;
    word = NEW(char,(Point-start)+1);
    strncpy(word,(char *)(Line+start),Point-start);
    word[Point-start]='\0';
    if ((start > 0) && (Line[start-1] == '"'))
	started_with_quote = 1;

    if (el_user_completion_function)
	/* May need to look at previous char so pass in Line */
	possibles = el_user_completion_function((char *)Line,start,Point);
    if (possibles == NULL)
    {
	possibles = el_file_completion_function((char *)Line,start,Point);
	/* As filename completions only complete the final file name */
	/* not the full path we need to set a new start position     */
	for (start=Point; start > 0; start--)
	    if (strchr("()' \t\n\r\"/",Line[start-1]) != NULL)
		break;
    }
    if (possibles)
	for (possiblesc=0; possibles[possiblesc] != NULL; possiblesc++);
    
    if ((!possibles) || (possiblesc == 0)) /* none or none at all */
	s = ring_bell();
    else if (possiblesc == 1)  /* a single expansion */
    {
	p = NEW(char,strlen(possibles[0])-(Point-start)+2);
	sprintf(p,"%s ",possibles[0]+(Point-start));
	if ((strlen(p) > 1) && (p[strlen(p)-2] == '/'))
	    p[strlen(p)-1] = '\0';
	else if (started_with_quote)
	    p[strlen(p)-1] = '"';
	
	s = insert_string((CHAR *)p);
	DISPOSE(p);
    }
    else if ((p = completion_to_ambiguity(Point-start,possibles)) != NULL)
    {    /* an expansion to a later ambiguity */
	s = insert_string((CHAR *)p);
	DISPOSE(p);
	ring_bell();
    }
    else /* list of possibilities and we can't expand any further */
    {
	print_columns(possiblesc,possibles);  /* display options */
	reposition(0);  /* display whole line again */
	s = CSmove;
    }

    for (i=0; possibles && possibles[i] != NULL; i++)
	DISPOSE(possibles[i]);
    DISPOSE(possibles);
    DISPOSE(word);

    return s;
}

#if 0
/* Original version without automatic listing of possible completions */
STATIC EL_STATUS c_complete_old()
{
    CHAR	*p;
    CHAR	*word;
    int		unique;
    EL_STATUS	s;

    word = find_word();
    p = (CHAR *)el_complete((char *)word, &unique);
    if (word)
	DISPOSE(word);
    if (p && *p) {
	s = insert_string(p);
	if (!unique)
	    (void)ring_bell();
	DISPOSE(p);
	return s;
    }
    return ring_bell();
}
#endif

STATIC EL_STATUS c_possible()
{
    CHAR	**av;
    CHAR	*word;
    int		ac;

    word = find_word();
    ac = el_list_possib((char *)word, (char ***)&av);
    if (word)
	DISPOSE(word);
    if (ac) {
	print_columns(ac, (char **)av);
	reposition(0);
	while (--ac >= 0)
	    DISPOSE(av[ac]);
	DISPOSE(av);
	return CSmove;
    }
    return ring_bell();
}

STATIC EL_STATUS accept_line()
{
    Line[End] = '\0';
    return CSdone;
}

STATIC EL_STATUS end_of_input()
{
    Line[End] = '\0';
    return CSeof;
}

STATIC EL_STATUS transpose()
{
    CHAR	c;

    if (Point) {
	if (Point == End)
	    left(CSmove);
	c = Line[Point - 1];
	left(CSstay);
	Line[Point - 1] = Line[Point];
	TTYshow(Line[Point - 1]);
	Line[Point++] = c;
	TTYshow(c);
    }
    return CSstay;
}

STATIC EL_STATUS quote()
{
    unsigned int	c;

    return (c = TTYget()) == EOF ? CSeof : insert_char((int)c);
}

STATIC EL_STATUS wipe()
{
    int		i;

    if (Mark > End)
	return ring_bell();

    if (Point > Mark) {
	i = Point;
	Point = Mark;
	Mark = i;
	reposition(1);
    }

    return delete_string(Mark - Point);
}

STATIC EL_STATUS mk_set()
{
    Mark = Point;
    return CSstay;
}

STATIC EL_STATUS exchange()
{
    unsigned int	c;

    if ((c = TTYget()) != CTL('X'))
	return c == EOF ? CSeof : ring_bell();

    if ((c = Mark) <= End) {
	Mark = Point;
	Point = c;
	return CSmove;
    }
    return CSstay;
}

STATIC EL_STATUS yank()
{
    if (Yanked && *Yanked)
	return insert_string(Yanked);
    return CSstay;
}

STATIC EL_STATUS copy_region()
{
    if (Mark > End)
	return ring_bell();

    if (Point > Mark)
	save_yank(Mark, Point - Mark);
    else
	save_yank(Point, Mark - Point);

    return CSstay;
}

STATIC EL_STATUS move_to_char()
{
    unsigned int	c;
    int			i;
    CHAR		*p;

    if ((c = TTYget()) == EOF)
	return CSeof;
    for (i = Point + 1, p = &Line[i]; i < End; i++, p++)
	if (*p == c) {
	    Point = i;
	    return CSmove;
	}
    return CSstay;
}

STATIC EL_STATUS fd_word()
{
    return do_forward(CSmove);
}

STATIC EL_STATUS fd_kill_word()
{
    int		i;
    int OP;

    OP = Point;
    (void)do_forward(CSmove);
    if (OP != Point) {
	i = Point - OP;
	for ( ; Point > OP; Point --)
	    TTYback();
	return delete_string(i);
    }
    return CSmove;
}

STATIC EL_STATUS bk_word()
{
    int		i;
    CHAR	*p;

    i = 0;
    do {
	for (p = &Line[Point]; p > Line && !isalnum(p[-1]); p--)
	    left(CSmove);

	for (; p > Line && p[-1] != ' ' && isalnum(p[-1]); p--)
	    left(CSmove);

	if (Point == 0)
	    break;
    } while (++i < Repeat);

    return CSstay;
}

STATIC EL_STATUS bk_kill_word()
{
    (void)bk_word();
    if (OldPoint != Point)
	return delete_string(OldPoint - Point);
    return CSstay;
}

STATIC int argify(CHAR *line, CHAR ***avp)
{
    CHAR	*c;
    CHAR	**p;
    CHAR	**new;
    int		ac;
    int		i;

    i = MEM_INC;
    if ((*avp = p = NEW(CHAR*, i))== NULL)
	 return 0;

    for (c = line; isspace(*c); c++)
	continue;
    if (*c == '\n' || *c == '\0')
	return 0;

    for (ac = 0, p[ac++] = c; *c && *c != '\n'; ) {
	if (isspace(*c)) {
	    *c++ = '\0';
	    if (*c && *c != '\n') {
		if (ac + 1 == i) {
		    new = NEW(CHAR*, i + MEM_INC);
		    if (new == NULL) {
			p[ac] = NULL;
			return ac;
		    }
		    COPYFROMTO(new, p, i * sizeof (char **));
		    i += MEM_INC;
		    DISPOSE(p);
		    *avp = p = new;
		}
		p[ac++] = c;
	    }
	}
	else
	    c++;
    }
    *c = '\0';
    p[ac] = NULL;
    return ac;
}

STATIC EL_STATUS last_argument()
{
    CHAR	**av;
    CHAR	*p;
    EL_STATUS	s;
    int		ac;

    if (H.Size == 1 || (p = H.Lines[H.Size - 2]) == NULL)
	return ring_bell();

    if ((p = (CHAR *)STRDUP((char *)p)) == NULL)
	return CSstay;
    ac = argify(p, &av);

    if (Repeat != NO_ARG)
	s = Repeat < ac ? insert_string(av[Repeat]) : ring_bell();
    else
	s = ac ? insert_string(av[ac - 1]) : CSstay;

    if (ac)
	DISPOSE(av);
    DISPOSE(p);
    return s;
}

STATIC KEYMAP	Map[MAPSIZE] = {
    {	CTL('@'),	1,	ring_bell	},
    {	CTL('A'),	1,	beg_line	},
    {	CTL('B'),	1,	bk_char		},
    {	CTL('D'),	1,	del_char	},
    {	CTL('E'),	1,	end_line	},
    {	CTL('F'),	1,	fd_char		},
    {	CTL('G'),	1,	ring_bell	},
    {	CTL('H'),	1,	bk_del_char	},
    {	CTL('I'),	1,	c_complete	},
    {	CTL('J'),	1,	accept_line	},
    {	CTL('K'),	1,	kill_line	},
    {	CTL('L'),	1,	redisplay	},
    {	CTL('M'),	1,	accept_line	},
    {	CTL('N'),	1,	h_next		},
    {	CTL('O'),	1,	ring_bell	},
    {	CTL('P'),	1,	h_prev		},
    {	CTL('Q'),	1,	ring_bell	},
    {	CTL('R'),	1,	h_risearch	},
    {	CTL('S'),	1,	h_search	},
    {	CTL('T'),	1,	transpose	},
    {	CTL('U'),	1,	ring_bell	},
    {	CTL('V'),	1,	quote		},
    {	CTL('W'),	1,	wipe		},
    {	CTL('X'),	1,	exchange	},
    {	CTL('Y'),	1,	yank		},
#ifdef SYSTEM_IS_WIN32
    {	CTL('Z'),	1,	end_of_input	},
#else
    {	CTL('Z'),	1,	ring_bell	},
#endif
    {	CTL('['),	1,	meta		},
    {	CTL(']'),	1,	move_to_char	},
    {	CTL('^'),	1,	ring_bell	},
    {	CTL('_'),	1,	ring_bell	},
    {	0,		1,	NULL		}
};

STATIC KEYMAP	MetaMap[METAMAPSIZE]= {
    {	CTL('H'),	1,	bk_kill_word	},
    {   CTL('['),       1,      c_possible      },
    {	DEL,		1,	bk_kill_word	},
    {	' ',		1,	mk_set		},
    {	'.',		1,	last_argument	},
    {	'<',		1,	h_first		},
    {	'>',		1,	h_last		},
    {	'?',		1,	c_possible	},
    {	'b',		1,	bk_word		},
    {	'c',		1,	case_cap_word	},
    {	'd',		1,	fd_kill_word	},
    {	'f',		1,	fd_word		},
    {	'l',		1,	case_down_word	},
    {	'm',		1,	toggle_meta_mode},
    {	'u',		1,	case_up_word	},
    {	'y',		1,	yank		},
    {	'w',		1,	copy_region	},
    {	0,		1,	NULL		}
};

void el_bind_key_in_metamap(char c, El_Keymap_Function func)
{
    /* Add given function to key map for META keys */
    int i;

    for (i=0; MetaMap[i].Key != 0; i++)
    {
	if (MetaMap[i].Key == c)
	{
	    MetaMap[i].Function = func;
	    return;
	}
    }

    /* A new key so have to add it to end */
    if (i == 63)
    {
	fprintf(stderr,"editline: MetaMap table full, requires increase\n");
	return;
    }
    
    MetaMap[i].Function = func;
    MetaMap[i].Key = c;
    MetaMap[i+1].Function = 0;  /* Zero the last location */
    MetaMap[i+1].Key = 0;       /* Zero the last location */

}


