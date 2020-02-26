CC = gcc -O2 -Wall
LIBS = -leditline -lncurses

OBJS = completion_test
SRCS = completion_test.c


all:	$(OBJS)

clean:
	-rm -f *.o *~ $(OBJS)

completion_test:	completion_test.c
	$(CC) -o completion_test completion_test.c $(LIBS)

test:	completion_test
	./completion_test
