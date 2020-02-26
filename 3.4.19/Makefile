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


##  This is the main Makefile for the CWB. 


#
#  Welcome to the IMS Open Corpus Workbench.  See "INSTALL" for detailed
#  installation instructions.  For a quick start, select appropriate PLATFORM
#  and SITE configurations in "config.mk", then build with "make all".
#  
#  The following make targets are available:
#
#  all          compile the Corpus Library, CQP, command-line utilities and man pages
#  install      install CL, CQP, utilities and man pages
# [uninstall    uninstall everything from specified locations] -- currently not available
#  release      create a release file (binaries and documentation)
#  clean        clean up in all subdirectories 
#  realclean    also delete automatically generated parsers, dependencies, etc.
#
#  size         check size of sourcecode (in lines)
#  depend       update dependencies (only necessary after major changes)
#  tags         create an emacs ETAGS file
#
#  cl           to compile the Corpus Library only
#  cqp          to compile CQP only
#  utils        to compile the utilities only
#  man          to update the manpages from their .pod sources
#  doxygen      to update the HTML code documentation (requires doxygen to be installed)
#  

TOP = $(shell pwd)
include $(TOP)/config.mk

# targets for external libraries; currently not used (Glib and PCRE assumed to be on the system already)
ifdef __MINGW__
#EXTERNALS = 
else
EXTERNALS = 
endif
SUBDIRS = cl cqp utils man instutils	# subdirectories that have their own makefiles
SRCDIRS = cl cqp utils CQi	# subdirectories containing C source code


.PHONY: clean realclean depend all test install uninstall release mingw-libgnurx-2.5.1 cl cqp utils man instutils tags size

default:
	@$(ECHO) "Please type one of the following:"
	@$(ECHO) ""
	@$(ECHO) "  make clean     remove binaries and temporary files"
	@$(ECHO) "  make realclean deletes all automatically generated files"
	@$(ECHO) "  make depend    updated dependencies"
	@$(ECHO) "  make all       build CWB libaries, programs and man pages"
	@$(ECHO) "  make install   install CWB into chosen location"
	@$(ECHO) "  make release   create binary release in build/ directory"
	@$(ECHO) ""
	@$(ECHO) "  make cl        build low-level corpus library (CL)"
	@$(ECHO) "  make cqp       build CQP query processor"
	@$(ECHO) "  make utils     build command-line utilities"
	@$(ECHO) "  make man       update manpages from POD sources"
	@$(ECHO) "  make doxygen   update HTML code documentation"
	@$(ECHO) "  make instutils configure installation scripts"
	@$(ECHO) "  make tags      generate symbol index for GNU Emacs"
	@$(ECHO) "  make size      check total size of CWB source code"   

all:  $(EXTERNALS)
	@$(ECHO) "--------------------------------- BUILDING CORPUS LIBRARY (CL)"
	$(MAKE) -C cl
	@$(ECHO) "--------------------------------- BUILDING CQP"
	$(MAKE) -C cqp
	@$(ECHO) "--------------------------------- BUILDING COMMAND-LINE UTILITIES"
	$(MAKE) -C utils
	@$(ECHO) "--------------------------------- BUILDING MANPAGES"
	$(MAKE) -C man

cl:
	@$(ECHO) "--------------------------------- BUILDING CORPUS LIBRARY (CL)"
	$(MAKE) -C cl 

cqp:
	@$(ECHO) "--------------------------------- BUILDING CQP"
	$(MAKE) -C cqp 

utils:
	@$(ECHO) "--------------------------------- BUILDING COMMAND-LINE UTILITIES"
	$(MAKE) -C utils 

man:
	@$(ECHO) "--------------------------------- BUILDING MANPAGES"
	$(MAKE) -C man 

doxygen:
	@$(ECHO) "--------------------------------- BUILDING CODE DOCUMENTATION"
	-$(RM) -rf doc/html/*
	doxygen doc/doxygen-config
	(cd doc && perl textile2html.perl)

instutils:
	@$(ECHO) "--------------------------------- CONFIGURING INSTUTILS"
	$(MAKE) -C instutils

test:
	@$(ECHO) "ERROR: no self tests available at the moment."
#	$(MAKE) -C test

size:
	@$(ECHO) "--------------------------------- MEASURING EXTENT OF THE CWB CODE"
	for i in $(SUBDIRS) ;\
	do \
	@$(ECHO) "--------------------------------- $$i"; \
	$(MAKE) -C $$i size; \
	done;

depend:
	@$(ECHO) "--------------------------------- UPDATING ALL DEPENDENCIES"
	for i in $(SUBDIRS) ;\
	do \
	$(MAKE) -C $$i depend; \
	done;

clean:
	@$(ECHO) "--------------------------------- PERFORMING TYPICAL CLEANUP"
	for i in $(SUBDIRS) $(EXTERNALS); do if [ -f "$$i/Makefile" ]; then $(MAKE) -C $$i clean; fi; done;
	-$(RM) *~ config/*/*~ CQi/*~ CQi/*.o technical/*~ TAGS
	-$(RM) -rf build

realclean:	clean
	@$(ECHO) "--------------------------------- PERFORMING EXTRA CLEANUP"
	for i in $(SUBDIRS) ; do $(MAKE) -C $$i realclean; done;
ifdef __MINGW__
	-$(RM) -rf mingw-libgnurx-2.5.1/config.log mingw-libgnurx-2.5.1/config.status mingw-libgnurx-2.5.1/Makefile
endif

install:
ifdef __MINGW__
	@$(ECHO) "ERROR: install operation is not supported for Windows binaries!"
else
	@$(ECHO) "--------------------------------- INSTALLING CORPUS WORKBENCH"
	$(MAKE) -C cl install   # not in for loop to make sure we abort on error
	$(MAKE) -C cqp install
	$(MAKE) -C utils install
	$(MAKE) -C man install
	$(MAKE) -C instutils all
	$(MAKE) -C instutils install
endif

release:
	@$(ECHO) "--------------------------------- BUILDING RELEASE BUNDLE"
	if [ -d "$(RELEASE_DIR)" ]; then $(RM) -rf "$(RELEASE_DIR)"; fi
	$(INSTALL) $(INST_FLAGS_DIR) "$(RELEASE_DIR)"
	$(MAKE) -C cl release
	$(MAKE) -C cqp release
	$(MAKE) -C utils release
	$(MAKE) -C man release
	$(MAKE) -C instutils all
	$(MAKE) -C instutils release
ifdef __MINGW__
# we also need DLL file(s)
	for i in $(DLLS_TO_INSTALL); do $(INSTALL) $(INST_FLAGS_DATA) $$i "$(RELEASE_DIR)/bin"; done
endif
	if [ -f "$(RELEASE_COMPRESSED_FILENAME)" ]; then $(RM) "$(RELEASE_COMPRESSED_FILENAME)"; fi
	(cd build/ && $(COMPRESS_COMMAND) "$(RELEASE_COMPRESSED_FILENAME)" "$(RELEASE_NAME)")
	@$(ECHO) "==> CREATED BINARY RELEASE build/$(RELEASE_COMPRESSED_FILENAME)"

uninstall:
	@$(ECHO) "ERROR: uninstall operation is currently not supported!"
# 	for i in $(SUBDIRS) ;\
# 	do \
# 	$(MAKE) -C $$i uninstall; \
# 	done;

tags:
	@$(ECHO) "--------------------------------- GENERATING 'TAGS' FILES"
	rm -f TAGS
	for i in $(SRCDIRS) ;\
	do \
	$(ETAGS) -a -o TAGS $$i/*.[chly]; \
	done
# NB "-a" option is required because we build the TAGS file incrementally
