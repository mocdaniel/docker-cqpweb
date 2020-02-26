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
#  all          create man pages from POD documentation
#  install      install man pages
#  uninstall    uninstall man pages from chosen location (currently not supported)
#  relase       install to binary release dir
#  pdf          create PDF files from man pages
#  clean        delete PostScript files and other temporary files
#  realclean    also delete generated man pages
#  depend       <dummy target>
#  size         <dummy target>

MANS = 	cqp.man cqpcl.man cqpserver.man \
	cwb-align.man cwb-align-show.man cwb-align-encode.man cwb-align-decode.man \
	cwb-atoi.man cwb-itoa.man \
	cwb-config.man \
	cwb-encode.man cwb-decode.man cwb-lexdecode.man cwb-decode-nqrfile.man \
	cwb-describe-corpus.man \
	cwb-makeall.man cwb-compress-rdx.man cwb-huffcode.man \
	cwb-s-encode.man cwb-s-decode.man \
	cwb-scan-corpus.man

PDFMANS = $(MANS:.man=.pdf)

BASENAMES = $(MANS:.man=)

all:	$(MANS)

%.man:	%.pod
	@$(ECHO) "    .... compile manual file" $@
	pod2man --section=1 --center="IMS Open Corpus Workbench" --release=$(VERSION) $< > $@

pdf:	$(PDFMANS)

%.pdf:	%.man
	@$(ECHO) "    .... convert manual file" $< "to pdf document" $@
	groff -man -Tps $< | ps2pdf - $@

install: $(MANS)
	@$(ECHO) "    .... install all manual files"
	if [ ! -d $(MANINSTDIR)/man$(MANEXT) ];	then  $(INSTALL) $(INST_FLAGS_DIR) $(MANINSTDIR)/man$(MANEXT); fi
	for i in $(BASENAMES); do $(INSTALL) $(INST_FLAGS_DATA) $$i.man $(MANINSTDIR)/man$(MANEXT)/$$i.$(MANEXT) ; done

ifdef __MINGW__
release: $(PDFMANS)
	@$(ECHO) "    .... add PDF document" $@ "to the release bundle"
	if [ ! -d "$(RELEASE_DIR)/man" ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(RELEASE_DIR)/man"; fi;
	for i in $(PDFMANS); do $(INSTALL) $(INST_FLAGS_DATA) $$i "$(RELEASE_DIR)/man/$$i"; done
else
release: $(MANS)
	@$(ECHO) "    .... add manual file" $@ "to the release bundle"
	if [ ! -d "$(RELEASE_DIR)/man/man$(MANEXT)" ]; then $(INSTALL) $(INST_FLAGS_DIR) "$(RELEASE_DIR)/man/man$(MANEXT)"; fi;
	for i in $(BASENAMES); do $(INSTALL) $(INST_FLAGS_DATA) $$i.man "$(RELEASE_DIR)/man/man$(MANEXT)/$$i.$(MANEXT)"; done
endif

uninstall:
	@$(ECHO) "ERROR: uninstall operation is currently not supported!"

clean:
	@$(ECHO) "    .... cleanup PDF files"
	-rm -f $(PDFMANS) *~

realclean:
	@$(ECHO) "    .... cleanup all manual files"
	-rm -f $(PDFMANS) $(MANS) *~

depend:
	@$(ECHO) "         Nothing to be done for 'make depend' in directory 'man'"

size:
	@$(ECHO) "         Nothing to be done for 'make size' in directory 'man'"

