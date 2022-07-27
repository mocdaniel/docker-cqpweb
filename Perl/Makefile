# save some typing for the standard installation procedure for the Perl modules.
# designed for use by devs. normal users probably shouldn't.

COMPONENTS = CWB CWB-CL CWB-CQI CWB-Web
CWB_PREFIX =
PERL = perl
CONFIG_FLAGS = 

# devs may want to override some of the above configs when testing
-include local_config.mk

ifdef CWB_PREFIX
  CONFIG_FLAGS += --config=$(CWB_PREFIX)/bin/cwb-config
endif

default: prep build test

prep:
	for d in $(COMPONENTS) ; do (cd $$d ; $(PERL) Makefile.PL $(CONFIG_FLAGS)); done

build: prep
	for d in $(COMPONENTS) ; do make -C $$d ; done

test: build
	for d in $(COMPONENTS) ; do make -C $$d test ; done

install: build
#	if [ $$(id -u) -neq 0 ] ; then SUDO=sudo ;  echo "WARNING: you are not root. Installation may fail." ; else SUDO="" ; fi
#	for d in $(COMPONENTS) ; do $$SUDO make -C $$d install ; done
	for d in $(COMPONENTS) ; do make -C $$d install ; done

clean:
	for d in $(COMPONENTS) ; do make -C $$d distclean ; done
