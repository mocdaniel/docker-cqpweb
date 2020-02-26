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

#ifndef __MINGW__
#include <sys/types.h>     /* required on Darwin */
#include <sys/socket.h>    /* required on Darwin */
#include <netinet/in.h>
#else
#include <winsock2.h>
#endif

void add_user_to_list(char *user, char *passwd);
void add_host_to_list(char *ipaddr);               /* e.g. "141.58.127.243"; NULL to accept connections from all hosts */
void add_hosts_in_subnet_to_list(char *ipsubnet);  /* e.g. "141.58.127." */
void add_grant_to_last_user(char *corpus);

/** returns true if host is in list of allowed hosts */
int check_host(struct in_addr host);

/** returns true if (user, passwd) pair is in list of allowed users */
int authenticate_user(char *user, char *passwd);

/** returns true if user may access corpus */
int check_grant(char *user, char *corpus);

/** for debugging only */
void show_grants(void);
