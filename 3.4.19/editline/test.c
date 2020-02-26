
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
/*                 Author: Richard Caley                                */
/*                   Date: July 1999                                    */
/************************************************************************/

#include <stdio.h>
#include "editline.h"

extern void free(void *);


int main()

{
  char *line;

  read_history("TEST_HISTORY");

  while ((line = readline("editline> ")) != NULL)
    {
      printf("Input was '%s'\n", line);
      add_history(line);
      free(line);
    }

  write_history("TEST_HISTORY");

  putchar('\n');
  
  return 0;
}


