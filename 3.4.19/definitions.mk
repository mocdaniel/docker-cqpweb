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


#
# ***** This file should NOT be edited! *****
#

#
# CWB version
#
VERSION = 3.4.19
# 3.4.x = beta versions leading up to new stable 3.5.0

#
# Check that required configuration variables are set
#

ifndef DEFAULT_REGISTRY
$(error Configuration variable DEFAULT_REGISTRY is not set (default registry directory))
endif

ifndef PREFIX 
$(error Configuration variable PREFIX is not set (software installation tree))
endif

ifndef INST_PERM
$(error Configuration variable INST_PERM is not set (access permissions for installed files))
endif

ifndef CC
$(error Configuration variable CC is not set (C compiler to use))
endif

## these variables may be empty if no special flags or libraries are needed
# ifndef CFLAGS
# $(error Configuration variable CFLAGS is not set (C compiler options))
# endif

# ifndef LDFLAGS
# $(error Configuration variable LDFLAGS is not set (linker options))
# endif

ifndef YACC
$(error Configuration variable YACC is not set (yacc or bison parser))
endif

ifndef LEX
$(error Configuration variable LEX is not set (lex or flex scanner))
endif

ifndef AR
$(error Configuration variable AR is not set (for building archive from .o files))
endif

ifndef RANLIB
$(error Configuration variable RANLIB is not set (make table of contents for .a files))
endif


#
# Variables that are normally set here but may have to be overridden in special situations
#

ifndef ETAGS
ETAGS = $(error Cannot build TAGS file, no ETAGS program given in configuration)
endif

ifndef DEPEND
DEPEND = $(error Cannot update dependencies, no DEPEND program call given in configuration)
endif

ifndef DEPEND_CFLAGS
DEPEND_CFLAGS = $(CFLAGS)
endif

# many systems have a GNU-compatible install that may be faster than the included script
ifndef INSTALL
INSTALL = $(TOP)/instutils/install.sh -c
endif

# must be set by platform config if `date` doesn't work (or if one wants to lie)
ifndef COMPILE_DATE
COMPILE_DATE = "$(shell date)"
endif


## other configuration settings that should almost never need to be changed
ifndef CHMOD
CHMOD = chmod
endif
ifndef CP
CP = cp
endif
ifndef ECHO
ECHO = echo
endif
ifndef RM
RM = rm -f
endif
ifndef WC
WC = wc -l
endif
ifndef TAR
TAR = tar
endif
## note the above assume cross-compilation for Win; different solutions will be needed for native compilation. 
## only needed by "make release" for Windows
ifndef ZIP
ZIP = zip -r
endif

#
# Command-line flags for (GNU-compatible) install program
#

INST_FLAGS = 
ifdef INST_USER
INST_FLAGS += -o $(INST_USER)
endif
ifdef INST_GROUP
INST_FLAGS += -g $(INST_GROUP)
endif
INST_PERM_DATA = $(subst 7,6,$(subst 5,4,$(subst 3,2,$(subst 1,0,$(INST_PERM)))))
INST_PERM_BIN = $(subst 6,7,$(subst 4,5,$(subst 2,3,$(subst 0,0,$(INST_PERM_DATA)))))

INST_FLAGS_DATA = $(INSTFLAGS_FILE) -m $(INST_PERM_DATA) $(INST_FLAGS)
INST_FLAGS_BIN  = $(INSTFLAGS_FILE) -m $(INST_PERM_BIN) $(INST_FLAGS)
INST_FLAGS_DIR  = $(INSTFLAGS_DIR)  -m $(INST_PERM_BIN) $(INST_FLAGS) -d


#
# Installation directory tree (default locations under PREFIX)
#

ifndef BININSTDIR
BININSTDIR = $(PREFIX)/bin
endif
ifndef MANINSTDIR
MANINSTDIR = $(PREFIX)/share/man
endif
ifndef LIBINSTDIR
LIBINSTDIR = $(PREFIX)/lib
endif
ifndef INCINSTDIR
INCINSTDIR = $(PREFIX)/include
endif

#
# CPU architecture and operating system (only used for naming binary releases)
#

ifndef RELEASE_ARCH
RELEASE_ARCH = $(shell uname -m)
endif
ifndef RELEASE_OS
RELEASE_OS = $(shell uname -s)-$(shell uname -r)
endif

RELEASE_NAME = cwb-$(VERSION)-$(RELEASE_OS)-$(RELEASE_ARCH)
RELEASE_DIR = $(TOP)/build/$(RELEASE_NAME)

## commands / filenames used by make release
ifndef __MINGW__
RELEASE_COMPRESSED_FILENAME = "$(RELEASE_NAME).tar.gz"
COMPRESS_COMMAND = $(TAR) cfz
else
RELEASE_COMPRESSED_FILENAME = "$(RELEASE_NAME).zip"
COMPRESS_COMMAND = $(ZIP) 
endif

#
# Set up compiler and linker flags
#

CFLAGS += $(DEBUG_FLAGS) $(SITE_CFLAGS)
LDFLAGS += $(DEBUG_FLAGS) $(SITE_LDFLAGS)

# termcap/curses/readline DISALLOWED under MinGW, even if set elsewhere
# (because cmd.exe gives us commandline editing anyway).
ifdef __MINGW__
READLINE_LIBS = 
TERMCAP_LIBS =
READLINE_DEFINES =
TERMCAP_DEFINES =
endif

# termcap/curses support is activated by setting TERMCAP_LIBS
ifdef TERMCAP_LIBS
CFLAGS += -DUSE_TERMCAP
endif

# same for GNU Readline library support
ifdef READLINE_LIBS
CFLAGS += -DUSE_READLINE
endif

# Glib and PCRE header file info (added to CFLAGS_ALL below)
ifndef __MINGW__
ifndef PCRE_DEFINES
PCRE_DEFINES := $(shell pcre-config --cflags)
endif
ifndef GLIB_DEFINES
GLIB_DEFINES := $(shell pkg-config  --cflags glib-2.0)
endif
else
# Library/Include/DLL/PKG-config files for the cross compiler are to be found beneath this folder
ifndef MINGW_CROSS_HOME
MINGW_CROSS_HOME := $(subst install: ,,$(shell $(CC) --print-search-dirs | grep ^install))
# The above will usually produce the correct result - usually something like
# /usr/lib/gcc/i586-mingw32msvc/4.2.1-sjlj.  If necessary, override in config.mk
endif
PCRE_DEFINES := $(shell $(MINGW_CROSS_HOME)/bin/pcre-config --cflags)
GLIB_DEFINES := $(shell export PKG_CONFIG_PATH=$(MINGW_CROSS_HOME)/lib/pkgconfig ; pkg-config --cflags glib-2.0) $(shell pkg-config  --cflags glib-2.0)
endif

# define macro variables for some global settings
INTERNAL_DEFINES = -DCWB_REGISTRY_DEFAULT_PATH=\""$(DEFAULT_REGISTRY)"\" -DCOMPILE_DATE=\"$(COMPILE_DATE)\" -DCWB_VERSION=\"$(VERSION)\"

# path to locally compiled CL library and linker command
LIBCL_PATH = $(TOP)/cl/libcl.a
CL_LIBS = $(LIBCL_PATH) 

# paths to DLL files that need to be installed along with CWB binaries (win only)
ifdef __MINGW__
ifdef LIB_DLL_PATH
# This general variable, if set (should only be set by user!), overrrides (and makes unnecessary) both the specific variables.
LIBGLIB_DLL_PATH = $(LIB_DLL_PATH)
LIBPCRE_DLL_PATH = $(LIB_DLL_PATH)
endif
ifndef LIBGLIB_DLL_PATH
#$(error Configuration variable LIBGLIB_DLL_PATH is not set (directory containing MinGW-compiled libglib-2.0-0.dll))
LIBGLIB_DLL_PATH = $(MINGW_CROSS_HOME)/bin
endif
ifndef LIBPCRE_DLL_PATH
LIBPCRE_DLL_PATH = $(MINGW_CROSS_HOME)/bin
#$(error Configuration variable LIBPCRE_DLL_PATH is not set (directory containing MinGW-compiled libpcre-0.dll))
endif
DLLS_TO_INSTALL =                            \
    $(LIBPCRE_DLL_PATH)/libpcre-1.dll        \
    $(LIBPCRE_DLL_PATH)/libpcreposix-0.dll   \
    $(LIBGLIB_DLL_PATH)/libglib-2.0-0.dll    
else # i.e. if ! def __MINGW__
DLLS_TO_INSTALL = 
endif 

# Linker flags for libraries used by the CL (to be added to linking commands for all programs)
ifndef __MINGW__
ifndef PCRE_LIBS
PCRE_LIBS := $(shell pcre-config --libs)
endif
ifndef GLIB_LIBS
GLIB_LIBS := $(shell pkg-config --libs glib-2.0)
endif
LDFLAGS_LIBS = $(PCRE_LIBS) $(GLIB_LIBS)  
else
LDFLAGS_LIBS := -L$(MINGW_CROSS_HOME)/lib  -lpcre -lpcre.dll -lglib-2.0               \
    $(shell $(MINGW_CROSS_HOME)/bin/pcre-config --libs)   \
    $(shell export PKG_CONFIG_PATH=$(MINGW_CROSS_HOME)/lib/pkgconfig ; pkg-config --libs glib-2.0)
endif 

# complete sets of compiler and linker flags (allows easy specification of specific build rules)
CFLAGS_ALL = $(CFLAGS) $(INTERNAL_DEFINES) $(GLIB_DEFINES) $(PCRE_DEFINES) $(READLINE_DEFINES) $(TERMCAP_DEFINES)
DEPEND_CFLAGS_ALL = $(DEPEND_CLAGS) $(INTERNAL_DEFINES) $(GLIB_DEFINES) $(PCRE_DEFINES) $(READLINE_DEFINES) $(TERMCAP_DEFINES)
LDFLAGS_ALL = $(LDFLAGS) $(LDFLAGS_LIBS)

# readline and termcap libraries are only needed for building CQP
LDFLAGS_CQP =  $(READLINE_LIBS) $(TERMCAP_LIBS)


# 
# A gesture in the direction of user-friendliness:
# 
# Use .SILENT to turn on or off the grizzly bits (see above).
# Ultimately depends on FULL_MESSAGES which can be set in the config file or the command line. 
#
ifdef FULL_MESSAGES
FULL_OUTPUT_VAR = .no_silence_allowed
else
FULL_OUTPUT_VAR =
endif

# the .SILENT target is added to every makefile by this mechanism.
$(FULL_OUTPUT_VAR).SILENT :


#
# Chapter for manpages (only for command-line tools)
#

MANEXT = 1

#
# General .c -> .o build rule
#

%.o : %.c
	@$(ECHO) "    .... compile source file" $< 
	$(RM) $@
	$(CC) -c  -o $@ $(CFLAGS_ALL) $<
