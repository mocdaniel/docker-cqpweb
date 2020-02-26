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


# **********************************************************
# * Edit this file to configure CWB for your system *
# **********************************************************

# 
# PLATFORM-SPECIFIC CONFIGURATION (OS and CPU type)
#
# Pre-defined platform configuration files:
#       unix          generic Unix / GCC configuration (should work on most Unix platforms)
#       linux         i386-Linux (generic)
#         linux-64       - configuration for 64-bit CPUs
#         linux-opteron  - with optimimzation for AMD Opteron processor
#       darwin        Mac OS X / Darwin [use one of the more specific entries below]
#         darwin-brew       - 64-bit, natively tuned, prerequisites installed with HomeBrew (recommended)
#         darwin-universal  - universal build on Mac OS X 10.6 and newer
#         darwin-64         - 64-bit build on Mac OS X 10.6 and newer
#         darwin-port-core2  - universal build optimized for Core2 CPUs, prerequisites installed with MacPorts (deprecated)
#       solaris       SUN Solaris 8 for SPARC CPU
#       cygwin        Win32 build using Cygwin emulation layer (experimental)
#       mingw-cross   Cross-compile for Win32-on-i586 from a *nix system with MinGW installed (experimental)
#       mingw-native  Build natively on Win32 using MinGW (new, at research stage only, does not work yet)
#
ifndef PLATFORM
PLATFORM=darwin-brew
endif
include $(TOP)/config/platform/$(PLATFORM)

#
# SITE-SPECIFIC CONFIGURATION (installation path and other local settings)
#
# Pre-defined site configuration files:
#       standard        standard configuration (installation in /usr/local tree)
#         beta-install    ... install into separate tree /usr/local/cwb-<VERSION> (unless CWB_LIVE_DANGEROUSLY is set)
#       classic         "classic" configuration (CWB v2.2, uses /corpora/c1/registry)
#       osx-fink        Mac OS X installation in Fink's /sw tree
#       binary-release  Build binary package for release (static if possible, use with "make release")
#         osx-release     ... for Mac OS X
#         linux-release   ... for i386 Linux
#         solaris-release ... for SUN Solaris 2.x
#         linux-rpm       ... build binary RPM package on Linux (together with rpm-linux.spec)
#         windows-release ... for Windows binaries cross-compiled with MinGW; use with "mingw" platform
#       cygwin          Win32 / Cygwin configuration (experimental)
#       
ifndef SITE
SITE=beta-install
endif
include $(TOP)/config/site/$(SITE)

#
# MANUAL CONFIGURATION (override individual platform and site settings)
#
# Manual configuration should only be used for testing or for one-off installation.
# If you intend to install further CWB releases on the same machine, it is recommended
# that you write your own configuration files (which can be stored outside the CWB
# source tree).  See INSTALL for more information.
#
# To override individual settings, uncomment and edit one or more of the assignments
# below.  The values shown are the "typical" defaults, but may be changed in the 
# platform and site configuration files you selected.
#


## SPECIAL: enable full output
## ---------------------------
## Normally, the complex output from the build process is hidden.
## But if you would like to see ALL the output, you can uncomment the line below.
# FULL_MESSAGES = 1




## Directory tree for software installation
# PREFIX = /usr/local

## Individual installation paths can be overridden
# BININSTDIR = $(PREFIX)/bin
# LIBINSTDIR = $(PREFIX)/lib
# INCINSTDIR = $(PREFIX)/include
# MANINSTDIR = $(PREFIX)/share/man

## Access permissions for installed files, optionally owner and group settings
# INST_PERM = 644
# INST_USER = ???
# INST_GROUP = ???

## Default registry for corpus declarations
# DEFAULT_REGISTRY = $(PREFIX)/share/cwb/registry

## CPU architecture and operating system for naming binary releases
# RELEASE_ARCH = ???  # e.g. i386 or x86_64
# RELEASE_OS = ???    # e.g. linux-2.6 or osx-10.4

## C compiler to use (GCC is highly recommended, others may not work)
# CC = gcc

## Override options for C compiler and linker (complete override)
# CFLAGS = -O2 -Wall
# LDFLAGS = -lm

## Include debugging information in binaries (for developers only, not enabled by default)
# DEBUG_FLAGS = -g

## Side-specific options are added to the standard CFLAGS and LDFLAGS (e.g. additional paths)
# SITE_CFLAGS =
# SITE_LDFLAGS =


#
# The following settings will only need to be changed in very rare cases.  If necessary, 
# they are usually set in the platform configuration file to work around OS deficiencies.
#
# When (cross-)compiling for Windows with MinGW, most of these settings are ignored
# and unconditionally replaced by hard-coded defaults. See file INSTALL-WIN for details.
#

## Some platforms require special libraries for socket/internet functionality 
# NETWORK_LIBS =

## CQP requires the termcap or ncurses library for text highlighting (setting TERMCAP_LIBS activates highlighting)
# TERMCAP_LIBS =
# TERMCAP_DEFINES = 

## GNU Readline library for command-line editing (optional)
# READLINE_LIBS = -L<path_to_readline_libs> -lreadline -lhistory
# READLINE_DEFINES = -I<path_to_readline_headers>

## GLIB2 for platform-independent support functions
# GLIB_LIBS = -L<path_to_glib_libs> -lglib-2.0
# GLIB_DEFINES = -I<path_to_glib_headers>

## PCRE regular expression library (v8.20 or newer strongly recommended)
# PCRE_LIBS = -L<path_to_pcre_libs> -lpcre
# PCRE_DEFINES = -I<path_to_pcre_headers>

## CWB uses Flex/Bison for parsing registry files and CQP commands
# YACC = bison -d -t
# LEX = flex -8

## GNU-compatible install program (defaults to included shell script)
# INSTALL = $(TOP)/instutils/install.sh

## Sometimes, extra install flags are needed for files or directories (e.g. preserve modification time on OS X)
# INSTFLAGS_FILE = ???
# INSTFLAGS_DIR = ???

## Make index of symbols in source code for Emacs editor (usually etags or ctags -e)
# ETAGS = ???

## Update dependencies between source code files (flags depend on C compiler being used)
# DEPEND = gcc -MM -MG

## In the unlikely event that "date" does not work properly, or if you want to lie about the date
# COMPILE_DATE = $(shell date)


#
# WINDOWS-ONLY CONFIGURATION
#
# If you are building a Windows release, then the make system needs to know where to find
# the library DLLs to add to the release. If you use the auto-build script, it will insert
# a "guess" as to where they might be. Define the following variables if (a) you want to
# override the guess or (b) you want to make for Windows without using the auto-build script.
#
## Library/Include/DLL/PKG-config files for the cross compiler are to be found beneath this folder
# MINGW_CROSS_HOME =
## The mingw-cross config file will ATTEMPT to set this by asking the cross compiler program to tell us. 
## If its attempt is no good, you can overrride the setting  directly above. 
#
## Path to the directory containing libpcre-0.dll
# LIBPCRE_DLL_PATH = 
## Path to the directory containing libglib-2.0-0.dll
# LIBGLIB_DLL_PATH =
## If they are in the same place, just define this variable (overrides the preceding two)
# LIB_DLL_PATH =
# If no LIB_DLL variables are set, they are assumed to be in $MINGW_CROSS_HOME/bin.



#
# ***** Do NOT edit anything below this point! *****
#

# load standard makefile settings (don't edit this)
include $(TOP)/definitions.mk
