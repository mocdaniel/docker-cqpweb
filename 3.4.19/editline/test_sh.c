/*  $Revision: 1.2 $
**
**  A "micro-shell" to test editline library.
**  If given any arguments, commands aren't executed.
*/

/* Modified by RJC.
 * Include editline public header and config header (the latter to
 * get the results of the autocof configuration).
 * 
 * Saves history between runs in TEST_SH_HISTORY in current directory.
 * This may lead to odd behaviour if you change directory before
 * exiting of course, but it is only supposed to be a demo.
 */

#include "editline.h"
#include "editline_config.h"

#include <stdio.h>
#if     defined(HAVE_STDLIB)
#include <stdlib.h>
#endif  /* defined(HAVE_STDLIB) */

#if     !defined(HAVE_STDLIB)
extern int      chdir();
extern int      free();
extern int      strncmp();
extern int      system();
extern void     exit();
extern char     *getenv();
#endif  /* !defined(HAVE_STDLIB) */


#if     defined(NEED_PERROR)
void
perror(s)
    char        *s;
{
    extern int  errno;

    (voidf)printf(stderr, "%s: error %d\n", s, errno);
}
#endif  /* defined(NEED_PERROR) */


/* ARGSUSED1 */
int
main(ac, av)
    int         ac;
    char        *av[];
{
    char        *prompt;
    char        *p;
    int         doit;

    read_history("TEST_SH_HISTORY");

    doit = ac == 1;
    if ((prompt = getenv("TESTPROMPT")) == NULL)
        prompt = "test_sh>  ";

    while ((p = readline(prompt)) != NULL) {
        (void)printf("\t\t\t|%s|\n", p);
        if (doit) {
            if (strncmp(p, "cd ", 3) == 0) {
                if (chdir(&p[3]) < 0)
                    perror(&p[3]);
            }
            else if (system(p) != 0) {
                perror(p);
            }
        }
        add_history(p);
        free(p);
    }

    write_history("TEST_SH_HISTORY");
    exit(0);
    /* NOTREACHED */
}
