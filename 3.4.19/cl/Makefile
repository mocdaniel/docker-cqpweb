##   -*-Makefile-*-
## 
##  IMS Open Corpus Workbench (CWB)
##  Copyright (C) 1993-2006 by IMS, University of Stuttgart
##  Copyright (C) 2007-     by the respective contributers (see file AUTHORS)
## 
##  This program is free software; you can redistribute it and/or modify it
##  under the terms of the GNU General Public License as published by the
##  Free Software Foundation; either version 2, or (at your option) any later
##  version.
## 
##  This program is distributed in the hope that it will be useful, but
##  WITHOUT ANY WARRANTY; without even the implied warranty of
##  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
##  Public License for more details (in the file "COPYING", or available via
##  WWW at http://www.gnu.org/copyleft/gpl.html).


##  This is the Makefile for the CWB Corpus Library. 



## Read configuration settings and standard definitions
TOP = $(shell pwd)/..
include $(TOP)/config.mk

## The following targets are available:
#
#  all          create archive libcl.a
#  clean        remove object files and libcl.a
#  realclean    also deleted automatically generated parsers and dependencies
#  depend       update dependencies
#  install      install libcl.a and CL headers
#  uninstall    uninstall libcl.a and headers from chosen locations (currently not supported)
#  release      install to binary release dir
#  size         print size of source code (line counts)
#

.PHONY: all clean realclean depend install uninstall size

## Use bison/flex to compile parser for registry entries
PARSEH  = registry.tab.h
PARSES  = registry.tab.c lex.creg.c
PARSEG  = registry.l registry.y
PARSEO  = registry.tab.o lex.creg.o

## Compile and link-in windows-mmap functions only if we are in MinGW
ifdef __MINGW__
MMAPH = windows-mmap.h
MMAPS = windows-mmap.c
MMAPO = windows-mmap.o
else
MMAPH =
MMAPS =
MMAPO =
endif 

## cl.h header file will be installed in subdirectory cwb/
REALINCINSTDIR = $(INCINSTDIR)/cwb

## ----------------------------------------------------------------------
## CORPUS LIBRARY  headers / sources / objects 

HDRS = globals.h cwb-globals.h ui-helpers.h \
       list.h lexhash.h ngram-hash.h \
       bitfields.h storage.h fileutils.h \
       special-chars.h regopt.h \
       corpus.h attributes.h makecomps.h \
       $(PARSEH) \
       bitio.h \
       endian.h \
       compression.h \
       binsert.h \
       class-mapping.h \
       $(MMAPH)

SRCS = globals.c macros.c ui-helpers.c \
       list.c lexhash.c ngram-hash.c \
       bitfields.c storage.c fileutils.c \
       special-chars.c regopt.c \
       corpus.c attributes.c makecomps.c \
       $(PARSES) \
       cdaccess.c \
       bitio.c \
       endian.c \
       compression.c \
       binsert.c \
       class-mapping.c \
       $(MMAPS)
 
OBJS = globals.o macros.o ui-helpers.o \
       list.o lexhash.o ngram-hash.o \
       bitfields.o storage.o fileutils.o \
       special-chars.o regopt.o \
       corpus.o attributes.o \
       $(PARSEO) \
       makecomps.o \
       cdaccess.o \
       bitio.o \
       endian.o \
       compression.o \
       binsert.o \
       class-mapping.o \
       $(MMAPO)

## Extra objects (will be compiled, but not included in libcl.a) 
#   - dl_stub.o is a dummy replacement for libdl.a, which is missing in Solaris
EXTRA_OBJS = dl_stub.o

# ----------------------------------------------------------------------

all: libcl.a $(EXTRA_OBJS)

registry.tab.c registry.tab.h: registry.y corpus.h macros.h attributes.h
	@$(ECHO) "    .... compile registry parser from Bison grammar"
	$(YACC) -p creg -v registry.y
#   NB: see comments in registry.y on use of "-p creg".

lex.creg.c: registry.l registry.y registry.tab.h corpus.h macros.h attributes.h
	@$(ECHO) "    .... compile registry lexer from Flex grammar"
	$(LEX) registry.l

libcl.a: $(OBJS)
	@$(ECHO) "    .... generate static library file for CL"
	$(RM) $@
	$(AR) $@ $^
	$(RANLIB) $@

install: libcl.a
ifndef __MINGW__
	@$(ECHO) "    .... install the CL static library file"
	if [ ! -d "$(LIBINSTDIR)" ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(LIBINSTDIR)" ; fi;
	$(INSTALL) $(INST_FLAGS_DATA) libcl.a "$(LIBINSTDIR)"
	@$(ECHO) "    .... install cwb/cl.h header file"
	if [ ! -d "$(REALINCINSTDIR)" ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(REALINCINSTDIR)" ; fi;
	$(INSTALL) $(INST_FLAGS_DATA) cl.h "$(REALINCINSTDIR)"
else
	@$(ECHO) "ERROR: install operation is not supported for Windows binaries!"
endif

release: libcl.a
	@$(ECHO) "    .... add to release: libcl static library file"
	if [ ! -d "$(RELEASE_DIR)/lib" ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(RELEASE_DIR)/lib"; fi;
	$(INSTALL) $(INST_FLAGS_DATA) libcl.a "$(RELEASE_DIR)/lib"
	@$(ECHO) "    .... add to release: cwb/cl.h header file"
	if [ ! -d "$(RELEASE_DIR)/include/cwb" ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(RELEASE_DIR)/include/cwb"; fi;
	$(INSTALL) $(INST_FLAGS_DATA) cl.h "$(RELEASE_DIR)/include/cwb"


uninstall:
	@$(ECHO) "ERROR: uninstall operation is currently not supported!"
#	$(RM) $(LIBINSTDIR)/libcl.a
#	(cd $(REALINCINSTDIR); $(RM) cl.h)

depend:
	@$(ECHO) "    .... generate the CL's make dependencies"
	-$(RM) depend.mk
	$(MAKE) depend.mk

# be sure to create bison/flex C code before making dependencies
depend.mk:	$(PARSEH) $(PARSES) $(SRCS)
	-$(RM) depend.mk
	$(DEPEND) $(DEPEND_CFLAGS_ALL) $(SRCS) > depend.mk

clean:
	@$(ECHO) "    .... cleanup compiled CL library code"
	-$(RM) *.o libcl.a registry.output *~

realclean: clean
	@$(ECHO) "    .... cleanup CL registry parser's automatic files"
	-$(RM) registry.tab.c registry.tab.c lex.creg.c registry.tab.h
	-$(RM) depend.mk

size: $(SRCS) $(HDRS)
	@$(ECHO) "    .... count up lines of code in CL"
# I am not sure if the following line will work to make these print even with .SILENT turned on ...
	@$(ECHO) `$(WC) $(SRCS) $(HDRS)` 
	$(WC) $(SRCS) $(HDRS) 


# -------- dependencies --------
include depend.mk

