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


/**
 * @file
 * Stub interface to dynamic linker routines
 * that SunOS uses but didn't ship with 4.1. (nor with Solaris 2.6 :oP )
 *
 * The C library routine wcstombs in SunOS 4.1 tries to dynamically
 * load some routines using the dlsym interface, described in dlsym(3x).
 * Unfortunately SunOS 4.1 does not include the necessary library, libdl.
 *
 * (borrowed from the MIT X11R5 distribution)
 */

/* Solaris 2.6 and 2.8 (required by libnsl.a) */
void *dlopen()
{
    return 0;
}

void *dlsym()
{
    return 0;
}

int dlclose()
{
    return -1;
}

char *dlerror()
{
    return 0;
}


/* Solaris 2.8 (required by libc.a) */
void *_dlopen()
{
    return 0;
}

void *_dlsym()
{
    return 0;
}

int _dlclose()
{
    return -1;
}

char *_dlerror()
{
    return 0;
}


