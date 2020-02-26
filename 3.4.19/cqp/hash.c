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


/** Tests whether n is prime. */
int
is_prime(int n) {
  int i;
  for(i = 2; i*i <= n; i++)
    if ((n % i) == 0)
      return 0;
  return 1;
}

/** Finds the next prime above n. */
int
find_prime(int n)
{
  for( ; n > 0 ; n++)		/* will exit on int overflow */
    if (is_prime(n))
      return n;
  return 0;
}


static unsigned int
hash_backend(char *str, unsigned int result_init)
{
  unsigned char *s = (unsigned char *)str;
  unsigned int result = result_init;
  for( ; *s; s++)
    result = (result * 33 ) ^ (result >> 27) ^ *s;
  return result;
}


/** Creates a 32 bit hash value of a string. */
unsigned int
hash_string(char *string)
{
  return hash_backend(string, 0);
}

/**
 * Creates a 32 bit hash value of a CQP macro (using both the macro name and its number of arguments,
 * so that two macros with the same name but different parameter signatures will not hash the same).
 */
unsigned int
hash_macro(char *macro_name, unsigned int args)
{
  return hash_backend(macro_name, args);
}
