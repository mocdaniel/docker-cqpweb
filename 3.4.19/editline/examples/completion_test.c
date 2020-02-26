
#include <stdio.h>
#include <stdlib.h>
#include <editline.h>

char **
completion_func(char *text, int start, int end) {
    int i;
    printf("\nCOMPLETE '%s'\n", text);
    for (i=-10; i<start; i++)
	printf(" ");
    printf("^");
    for (i=start+1; i<end; i++)
	printf(" ");
    printf("^\n");
    return NULL;		/* means: no completions found, try default */
    /* otherwise: return allocated, NULL-terminated list of allocated strings */
}



int main()

{
  char *line;

  read_history("TEST_HISTORY");

  el_user_completion_function = completion_func;

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


