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

## Read configuration settings and standard definitions
TOP = $(shell pwd)/..
include $(TOP)/config.mk

## The following targets are available:
#
#  all         force rebuild of installation script and cwb-config
#  install     install cwb-config program
#  uninstall   <currently not supported>
#  release     copy installation script etc. to binary release dir
#  clean       <dummy target>
#  realclean   remove automatically generated files
#  depend      <dummy target>
#  size        <dummy target>

## These are the options for *nix
ifndef __MINGW__

all:
	if [ -f cwb-config ]; then rm -f cwb-config; fi
	if [ -f install-cwb.sh ]; then rm -f install-cwb.sh; fi
	$(MAKE) cwb-config install-cwb.sh

cwb-config:
	VERSION='$(VERSION)' PREFIX='$(PREFIX)' BINDIR='$(BININSTDIR)' LIBDIR='$(LIBINSTDIR)' INCDIR='$(INCINSTDIR)' MANDIR='$(MANINSTDIR)' CFLAGS='-I$(INCINSTDIR)' LDFLAGS='-L$(LIBINSTDIR) -lcl $(LIB_REGEX) $(LDFLAGS_ALL)' REGISTRY='$(DEFAULT_REGISTRY)' sh cwb-config.in > cwb-config
## NB: removed $(GLIB_DEFINES) from CFLAGS= above because Glib and PCRE headers shouldn't be needed to link against CL


install-cwb.sh:
	VERSION='$(VERSION)' PREFIX='$(PREFIX)' REGISTRY='$(DEFAULT_REGISTRY)' MANEXT='$(MANEXT)' sh install-cwb.sh.in > install-cwb.sh	

install:	cwb-config
	if [ ! -d $(BININSTDIR) ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(BININSTDIR)"; fi
	$(INSTALL) $(INST_FLAGS_BIN) cwb-config "$(BININSTDIR)/cwb-config"

release:	cwb-config install-cwb.sh
	if [ ! -d "$(RELEASE_DIR)/bin" ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(RELEASE_DIR)/bin"; fi;
	$(INSTALL) $(INST_FLAGS_BIN) cwb-config "$(RELEASE_DIR)/bin"
	if [ ! -d "$(RELEASE_DIR)/instutils" ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(RELEASE_DIR)/instutils"; fi;
	for i in cwb-config.in install.sh; do $(INSTALL) $(INST_FLAGS_BIN) $$i "$(RELEASE_DIR)/instutils"; done
	for i in INSTALL; do $(INSTALL) $(INST_FLAGS_DATA) $$i "$(RELEASE_DIR)"; done
	for i in README COPYING AUTHORS CHANGES; do $(INSTALL) $(INST_FLAGS_DATA) ../$$i "$(RELEASE_DIR)"; done
	for i in install-cwb.sh; do $(INSTALL) $(INST_FLAGS_BIN) $$i "$(RELEASE_DIR)"; done

clean:
	-rm -f cwb-config install-cwb.sh *~

## And these are the options for Windows.
else
##    Note especially that cwb-config doesn't exit in the Windows world (no easy way to update it 
##    once it is on the user's system to account for a manually-specified install path being used)
##    (it would have to be generated as a bat file at install time)

all:	install-cwb-win.bat

install:	install-cwb-win.bat
	$(ECHO) "Installation is not possible when cross-compiling for Windows. Try ''make release'' instead..."

install-cwb-win.bat:	install-cwb-win.bat.in
	VERSION='$(VERSION)' PREFIX='$(PREFIX)' REGISTRY='$(DEFAULT_REGISTRY)' MANEXT='$(MANEXT)' bash install-cwb-win.bat.in > install-cwb-win.bat	

release:	install-cwb-win.bat
	for i in INSTALL; do $(INSTALL) $(INST_FLAGS_DATA) $$i "$(RELEASE_DIR)/$$i.txt"; done
	for i in README COPYING AUTHORS CHANGES; do $(INSTALL) $(INST_FLAGS_DATA) ../$$i "$(RELEASE_DIR)/$$i.txt"; done
	## PCRE licence?
	for i in install-cwb-win.bat; do $(INSTALL) $(INST_FLAGS_BIN) $$i "$(RELEASE_DIR)"; done
ifdef UNIX2DOS
	for i in INSTALL README COPYING AUTHORS CHANGES; do  $(UNIX2DOS) "$(RELEASE_DIR)/$$i.txt"; done
	$(UNIX2DOS) "$(RELEASE_DIR)/install-cwb-win.bat"
endif

clean:
	-rm -f install-cwb-win.bat *~

endif


## These targets are the same for *nix and for Windows releases.

realclean:	clean

depend:
	@$(ECHO) "         Nothing to be done for 'make depend' in directory 'instutils'"

size:
	@$(ECHO) "         Nothing to be done for 'make size'"
