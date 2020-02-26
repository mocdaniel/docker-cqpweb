/* 
 *  IMS Open Corpus Workbench (CWB)
 *  Copyright (C) 1993-2006 by IMS, University of Stuttgart
 *  Copyright (C) 2007-     by the respective contributers (see file AUTHORS)
 * 
 *  This program is free software; you can redistribute it and/or modify it
 *  under the terms of the GNU General Public License as published by the
 *  Free Software Foundation; either version 2, or (at your option) any later
 *  version.
 * 
 *  This program is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 *  Public License for more details (in the file "COPYING", or available via
 *  WWW at http://www.gnu.org/copyleft/gpl.html).
 */

#include "../CQi/auth.h"
#include "output.h"

/**
 * @file
 *
 * All functions in this module are dummy implementations which just print an error message.
 */

void 
add_user_to_list(char *user, char *passwd) {
  cqpmessage(Error, "'user' command only available in CQPserver");
}

void 
add_grant_to_last_user(char *corpus) {
  /* do nothing: add_user_to_list() prints error message */
}

void 
add_host_to_list(char *ipaddr) {
  cqpmessage(Error, "'host' command only available in CQPserver");
}

void 
add_hosts_in_subnet_to_list(char *ipsubnet) {
  cqpmessage(Error, "'host' command only available in CQPserver");
}

/* the following functions aren't used by CQP, so just return false */
int 
check_host(struct in_addr host_addr) {
  return 0;
}

int 
authenticate_user(char *username, char *passwd) {
  return 0;
}

int 
check_grant(char *username, char *corpus) {
  return 0;
}

void
show_grants(void) {
  /* void */
}

