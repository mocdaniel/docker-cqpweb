Summary: The IMS Open Corpus Workbench
Name: cwb
Version: 3.0
Release: 1
Copyright: GPL
Group: Applications/Databases
Source: cwb-3.0-src.tgz
BuildRoot: /tmp/%{name}-build

%description
The IMS Open Corpus Workbench (CWB) is a highly specialised read-only
database for large text corpora with linguistic annotations.  It uses
a compressed and platform-independent proprietary format to store 
corpus data with token-level annotations and shallow structural markup.
Its central component is the corpus query processor CQP, a terminal
application designed for interactive work.  In addition to CQP, the
distribution includes a number of command-line utilities for encoding,
decoding and extracting frequency information.  Low-level access to 
the corpus data is possible through the included C libary (CL).  Perl
bindings for the library and the query processor are available separately.

The IMS Open Corpus Workbench is distributed under the GNU Public License,
version 2.  Source and binary downloads as well as additional documentation
can be found on the CWB homepage at http://cwb.sourceforge.net/

%prep
%setup -n cwb-3.0
# 'patch' source code by inserting correct PLATFORM and SITE into Makefile.inc
perl -i -pe '$_="include \$(TOP)/config/platform/linux\n" if /^\s*include\s+.*config\/platform/ and not /linux/; $_="include \$(TOP)/config/site/linux-rpm\n" if /^\s*include\s+.*config\/site/;' config.mk

%build
make clean depend all

%install
rm -rf $RPM_BUILD_ROOT
make install

%clean
rm -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root)
/usr/local/bin
/usr/local/share/man/man1
/usr/local/share/cwb/registry
/usr/local/include/cwb/cl.h
/usr/local/lib/libcl.a
