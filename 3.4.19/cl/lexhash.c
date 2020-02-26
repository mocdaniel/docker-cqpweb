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


#include "globals.h"
#include "lexhash.h"

#include <math.h>


/** Defines the default number of buckets in a lexhash. */
#define DEFAULT_NR_OF_BUCKETS 250000

/** Default parameters for auto-growing the table of buckets (@see cl_lexhash_auto_grow_fillrate for details). */
#define DEFAULT_FILLRATE_LIMIT 2.0
#define DEFAULT_FILLRATE_TARGET 0.4

/** Maximum number of buckets lexhash will try to allocate when auto-growing. */
#define MAX_BUCKETS 1000000007  /* 1 billion (incremented to next prime number) */


/*
 * basic utility functions
 */

/** Returns True iff n is a prime */
int
is_prime(int n) {
  int i;
  for(i = 2; i*i <= n; i++)
    if ((n % i) == 0)
      return 0;
  return 1;
}

/** Returns smallest prime >= n */
int
find_prime(int n) {
  for( ; n > 0 ; n++)           /* loop will break on signed int overflow */
    if (is_prime(n))
      return n;
  return 0;
}

/** Computes 32bit hash value for string */
unsigned int
hash_string(char *string) {
  unsigned char *s = (unsigned char *)string;
  unsigned int result = 0;   /* TODO: 5381 as proposed in DJB2? */
  for( ; *s; s++)
    result = (result * 33) ^ (result >> 27) ^ *s;
  return result;
}

/** TODO: consider alternative hash functions

The hash function above appears to have been purloined from some version of the Perl source code.
This claim cannot be confirmed, though. Perl5 has used various hash functions over time, but older
versions (at least up to Perl 5.8.1) implement the simple DJB2 algorithm (see below).

According to

    http://burtleburtle.net/bob/hash/

the algorithm is recommended in Don Knuth's "Art of Computer Programming" (Vol. 3, Sec. 6.4),
but we haven't been able to find the actual reference there.

The URL above also discusses properties of hash functions at length and suggests a number of better algorithms.

Prime-number-sized hash tables are required by Knuth's algorithm, but make hashing more expensive.
Growing a hash from 2^n to 2^(n+1) also gives a highly predicatble redistribution of buckets.
Good hash functions should not require division by prime number in order to achieve good distribution.

DJB2:
unsigned long hash = 5381;
int c;

while (c = *str++)
    hash = ((hash << 5) + hash) + c; // hash * 33 + c

DJB2a:
    hash = hash * 33 ^ str[i]

MurmurHash:
see http://en.wikipedia.org/wiki/MurmurHash

Experimental comparison:
http://programmers.stackexchange.com/questions/49550/which-hashing-algorithm-is-best-for-uniqueness-and-speed

***/


/*
 * cl_lexhash / cl_lexhash_entry  object definition
 */


/* cl_lexhash_entry is in <cl.h> */

/**
 * A function pointer type defining functions that can be used as the "cleanup" for a deleted cl_lexhash_entry.
 * @see cl_lexhash_set_cleanup_function
 */
typedef void (*cl_lexhash_cleanup_func)(cl_lexhash_entry);

/* typedef struct _cl_lexhash *cl_lexhash; in <cl.h> */


/**
 * Underlying structure for the cl_lexhash object.
 *
 * A cl_lexhash contains a number of buckets. Each bucket is
 * a linked-list of cl_lexhash_entry objects.
 *
 */
struct _cl_lexhash {
  cl_lexhash_entry *table;      /**< table of buckets; each "bucket" is a pointer to the list of entries that make up that bucket */
  unsigned int buckets;         /**< number of buckets in the hash table */
  int next_id;                  /**< ID that will be assigned to next new entry */
  int entries;                  /**< current number of entries in this hash */
  cl_lexhash_cleanup_func cleanup_func; /**< callback function used when deleting entries (see cl.h) */
  int auto_grow;                /**< boolean: whether to expand this hash automatically; true by default */
  double fillrate_limit;        /**< fillrate limit that triggers expansion of bucket table (with auto_grow) */
  double fillrate_target;       /**< target fillrate after expansion of bucket table (with auto_grow) */
  int iter_bucket;              /**< bucket currently processed by the single iterator of the hash table */
  cl_lexhash_entry iter_point;  /**< next entry to be returned by the iterator (NULL -> go to next bucket) */
};


/*
 * cl_lexhash methods
 */

/**
 * Creates a new cl_lexhash object.
 *
 * @param buckets    The number of buckets in the newly-created cl_lexhash;
 *                   set to 0 to use the default number of buckets.
 * @return           The new cl_lexhash.
 */
cl_lexhash
cl_new_lexhash(int buckets)
{
  cl_lexhash hash;

  if (buckets <= 0)
    buckets = DEFAULT_NR_OF_BUCKETS;
  hash = (cl_lexhash) cl_malloc(sizeof(struct _cl_lexhash));
  hash->buckets = find_prime(buckets);
  hash->table = cl_calloc(hash->buckets, sizeof(cl_lexhash_entry));
  hash->next_id = 0;
  hash->entries = 0;
  hash->cleanup_func = NULL;
  hash->auto_grow = 1;
  hash->fillrate_limit = DEFAULT_FILLRATE_LIMIT;
  hash->fillrate_target = DEFAULT_FILLRATE_TARGET;
  hash->iter_bucket = -1;
  hash->iter_point = NULL;
  return hash;
}


/**
 * Deallocates a cl_lexhash_entry object and its key string.
 *
 * Also, the cleanup function is run on the entry.
 *
 * Usage: cl_delete_lexhash_entry(lexhash, entry);
 *
 * This is a non-exported function.
 *
 * @see          cl_lexhash_set_cleanup_function
 * @param hash   The lexhash this entry belongs to (needed to
 *               locate the cleanup function, if any).
 * @param entry  The entry to delete.
 */
void
cl_delete_lexhash_entry(cl_lexhash hash, cl_lexhash_entry entry)
{
  if (hash != NULL) {
    /* if necessary, let cleanup callback delete objects associated with the data field */
    if (hash->cleanup_func != NULL) {
      (*(hash->cleanup_func))(entry);
    }
    /* key is embedded in struct, so it mustn't be deallocated separately */
    cl_free(entry);
  }
}

/**
 * Deletes a cl_lexhash object.
 *
 * This deletes all the entries in all the buckets in the lexhash,
 * plus the cl_lexhash itself.
 *
 * @param hash  The cl_lexhash to delete.
 */
void
cl_delete_lexhash(cl_lexhash hash)
{
  int i;
  cl_lexhash_entry entry, temp;

  if (hash != NULL && hash->table != NULL) {
    for (i = 0; i < hash->buckets; i++) {
      entry = hash->table[i];
      while (entry != NULL) {
        temp = entry;
        entry = entry->next;
        cl_delete_lexhash_entry(hash, temp);
      }
    }
  }
  cl_free(hash->table);
  cl_free(hash);
}


/**
 * Sets the cleanup function for a cl_lexhash.
 *
 * The cleanup function is called with a cl_lexhash_entry argument;
 * it should delete any objects assocated with the entry's data field.
 *
 * The cleanup function is initially set to NULL, i.e. run no function.
 *
 * @param hash  The cl_lexhash to work with.
 * @param func  Pointer to the function to use for cleanup.
 */
void
cl_lexhash_set_cleanup_function(cl_lexhash hash, cl_lexhash_cleanup_func func)
{
  if (hash != NULL)
    hash->cleanup_func = func;
}

/**
 * Turns a cl_lexhash's ability to auto-grow on or off.
 *
 * When this setting is switched on, the lexhash will grow
 * automatically to avoid performance degradation.
 *
 * Note the default value for this setting is SWITCHED ON.
 *
 * @see         cl_lexhash_check_grow
 * @param hash  The hash that will be affected.
 * @param flag  New value for autogrow setting: boolean where
 *              true is on and false is off.
 */
void
cl_lexhash_auto_grow(cl_lexhash hash, int flag)
{
  if (hash != NULL)
    hash->auto_grow = flag;
}

/**
 * Configure auto-grow parameters.
 *
 * These settings are only relevant if auto-growing is enabled.
 *
 * The decision to expand the bucket table of a lexhash is based
 * on its fill rate, i.e. the average number of entries in each
 * bucket. Under normal circumstances, this value corresponds to
 * the average number of comparisons required to insert a new
 * entry into the hash (locating an existing value should require
 * roughly half as many comparisons).
 *
 * Auto-growing is triggered if the fill rate exceeds a specified
 * limit.  The new number of buckets is chosen so that the fill
 * rate after expansion corresponds to the specified target value.
 *
 * The limit should not be set too low in order to reduce memory
 * overhead and avoid frequent reallocation due to expansion in
 * small increments.  Good values seem to be in the range 2.0-5.0;
 * depending on whether speed or memory efficiency is more important.
 * A reasonable value for the target fill rate is 0.4, which corresponds
 * to a 42% overhead over the storage required for entry data structures
 * (48 bytes per entry vs. 8 bytes for each bucket).
 *
 * @see          cl_lexhash_auto_grow, cl_lexhash_check_grow
 * @param hash   The hash that will be affected.
 * @param limit  Fill rate limit, which triggers expansion of the lexhash
 * @param target Target fill rate after expansion (determines new number of buckets)
 */
void
cl_lexhash_auto_grow_fillrate(cl_lexhash hash, double limit, double target)
{
  if (hash != NULL) {
    /* set parameters with basic sanity checks */
    hash->fillrate_target = (target > 0.01) ? target : 0.01;
    hash->fillrate_limit = (limit > 2 * hash->fillrate_target) ? limit : 2 * hash->fillrate_target;
  }
}



/**
 * Grows a lexhash table, increasing the number of buckets, if necessary.
 *
 * This functions is called after inserting a new entry into the lexhash.
 * If checks whether the current fill rate exceeds the specified limit.
 * If this is the case, and auto_grow is enabled, then the hash is expanded
 * by increasing the number of buckets, such that the new average fill rate
 * corresponds to the specified target value.  This gives the
 * hash better performance and makes it capable of absorbing more keys.
 *
 * If the bucket table would be expanded to more than MAX_BUCKETS entries,
 * auto-grow is automatically disabled for this lexhash.
 *
 * Note: this function also implements the hashing algorithm and must be
 * consistent with cl_lexhash_find_i().
 *
 * Usage: expanded = cl_lexhash_check_grow(cl_lexhash hash);
 *
 * This is a non-exported function.
 *
 * @see         cl_lexhash_auto_grow, cl_lexhash_auto_grow_fillrate
 * @param hash  The lexhash to autogrow.
 * @return      Always 0.
 */
int
cl_lexhash_check_grow(cl_lexhash hash)
{
  double fill_rate, target_size;
  cl_lexhash temp;
  cl_lexhash_entry entry, next;
  int idx, offset, old_buckets, new_buckets;

  old_buckets = hash->buckets;
  fill_rate = ((double) hash->entries) / old_buckets;
  if (hash->auto_grow && (fill_rate > hash->fillrate_limit)) {
    /* auto-grow is triggered */
    target_size = floor(((double) hash->entries) / hash->fillrate_target);
    if (target_size > MAX_BUCKETS) {
      if (cl_debug) {
        fprintf(stderr, "[lexhash autogrow: size limit %f exceeded by new target size %f, auto-growing will be disabled]\n",
                (double) MAX_BUCKETS, target_size);
      }
      hash->auto_grow = 0; /* disable auto-grow to avoid further unnecessary attempts */
      /* grow lexhash to maximum size, but not if this would extend bucket vector by less than 2x (to avoid large reallocation for little benefit) */
      if (old_buckets > target_size / 2.0) {
        return 0;
      }
      else {
        target_size = MAX_BUCKETS;
      }
    }
    /* now grow bucket table from old_buckets entries to new_buckets entries */
    new_buckets = (int) target_size;
    old_buckets = hash->buckets;
    if (cl_debug) {
      fprintf(stderr, "[lexhash autogrow: triggered by fill rate = %3.1f (%d/%d)]\n",
              fill_rate, hash->entries, old_buckets);
    }
    temp = cl_new_lexhash(new_buckets); /* create new hash with target fill rate */
    new_buckets = temp->buckets; /* the actual number of entries (next prime number) */
    /* move all entries from hash to the appropriate bucket in temp */
    for (idx = 0; idx < old_buckets; idx++) {
      entry = hash->table[idx];
      while (entry != NULL) {
        next = entry->next;     /* remember pointer to next entry */
        offset = hash_string(entry->key) % new_buckets;
        entry->next = temp->table[offset]; /* insert entry into its bucket in temp (most buckets should contain only 1 entry, as long as hash->fillrate_target is less than 1) */
        temp->table[offset] = entry;
        temp->entries++;
        entry = next;           /* continue while loop */
      }
    }
    assert((temp->entries == hash->entries) && "lexhash.c: inconsistency during hash expansion");
    cl_free(hash->table);               /* old hash table should be empty and can be deallocated */
    hash->table = temp->table;          /* update hash from temp (copy hash table and its size) */
    hash->buckets = temp->buckets;
    cl_free(temp);                      /* we can simply deallocate temp now, having stolen its hash table */
    if (cl_debug) {
      fill_rate = ((double) hash->entries) / hash->buckets;
      fprintf(stderr, "[lexhash autogrow: new fill rate = %3.1f (%d/%d)]\n",
              fill_rate, hash->entries, hash->buckets);
    }
    return 1;
  }
  return 0;
}



/**
 * Finds the entry corresponding to a particular string in a cl_lexhash.
 *
 * This function is the same as cl_lexhash_find(), but *ret_offset is set to
 * the hashtable offset computed for token (i.e. the index of the bucket within
 * the hashtable), unless *ret_offset == NULL.
 *
 * Note that this function hides the hashing algorithm details from the
 * rest of the lexhash implementation (except cl_lexhash_check_grow, which
 * re-implements the hashing algorithm for performance reasons).
 *
 * Usage: entry = cl_lexhash_find_i(cl_lexhash hash, char *token, unsigned int *ret_offset);
 *
 * This is a non-exported function.
 *
 * @param hash        The hash to search.
 * @param token       The key-string to look for.
 * @param ret_offset  This integer address will be filled with the token's
 *                    hashtable offset (can be NULL, in which case, ignored).
 * @return            The entry that is found (or NULL if the string is not
 *                    in the hash).
 */
cl_lexhash_entry
cl_lexhash_find_i(cl_lexhash hash, char *token, unsigned int *ret_offset)
{
  unsigned int offset;
  cl_lexhash_entry entry;

  assert((hash != NULL && hash->table != NULL && hash->buckets > 0) && "cl_lexhash object was not properly initialised");

  /* get the offset of the bucket to look in by computing the hash of the string */
  offset = hash_string(token) % hash->buckets;
  if (ret_offset != NULL)
    *ret_offset = offset;
  /* check all entries in this bucket against the specified key */
  entry = hash->table[offset];
  while (entry != NULL && strcmp(entry->key, token) != 0)
    entry = entry->next;
  return entry;
}


/**
 * Finds the entry corresponding to a particular string within a cl_lexhash.
 *
 * This function is basically a wrapper around the internal function cl_lexhash_find_i.
 *
 * @see               cl_lexhash_find_i
 * @param hash        The hash to search.
 * @param token       The key-string to look for.
 * @return            The entry that is found (or NULL if the string is not
 *                    in the hash).
 */
cl_lexhash_entry
cl_lexhash_find(cl_lexhash hash, char *token)
{
  return cl_lexhash_find_i(hash, token, NULL);
}



/**
 * Adds a token to a cl_lexhash table.
 *
 * If the string is already in the hash, its frequency count
 * is increased by 1.
 *
 * Otherwise, a new entry is created, with an auto-assigned ID;
 * note that the string is duplicated, so the original string
 * that is passed to this function does not need to be kept in
 * memory.
 *
 * @param hash   The hash table to add to.
 * @param token  The string to add.
 * @return       A pointer to a (new or existing) entry
 */
cl_lexhash_entry
cl_lexhash_add(cl_lexhash hash, char *token)
{
  cl_lexhash_entry entry, insert_point;
  unsigned int offset;          /* this will be set to the index of the bucket this token should go in
                                   by the call to cl_lexhash_find_i                                     */

  entry = cl_lexhash_find_i(hash, token, &offset);

  if (entry != NULL) {
    /* token already in hash -> increment frequency count */
    entry->freq++;
  }
  else {
    /* token not in hash -> add new entry for this token */
    int keylen = strlen(token);
    /* allocate enough space for key string appended to the struct */
    entry = (cl_lexhash_entry) cl_malloc(sizeof(struct _cl_lexhash_entry) + keylen);
    strcpy(entry->key, token); /* embed copy of key in struct */
    entry->freq = 1;
    entry->id = (hash->next_id)++;
    entry->data.integer = 0;            /* initialise data fields to zero values */
    entry->data.numeric = 0.0;
    entry->data.pointer = NULL;
    entry->next = NULL;

    /* insert entry into its bucket in the hash table */
    insert_point = hash->table[offset];
    if (insert_point == NULL) {
      hash->table[offset] = entry;      /* only entry in this bucket so far */
    }
    else {
      /* always insert a new entry as the last entry in its bucket (because of Zipf's Law:
       * frequent lexemes tend to occur early in the corpus and should be first in their buckets for faster access) */
      while (insert_point->next != NULL)
        insert_point = insert_point->next;
      insert_point->next = entry;
    }
    hash->entries++;

    /* check whether hash needs to auto-grow */
    if (hash->auto_grow && hash->entries > (hash->fillrate_limit * hash->buckets))
      cl_lexhash_check_grow(hash);
  }
  return entry;
}

/**
 * Gets the ID of a particular string within a lexhash.
 *
 * Note this is the ID integer that identifies THAT
 * PARTICULAR STRING, not the hash value of that string -
 * which only identifies the bucket the string is
 * found in!
 *
 * @param hash   The hash to look in.
 * @param token  The string to look for.
 * @return       The ID code of that string, or -1
 *               if the string is not in the hash.
 */
int
cl_lexhash_id(cl_lexhash hash, char *token)
{
  cl_lexhash_entry entry;

  entry = cl_lexhash_find_i(hash, token, NULL);
  return (entry != NULL) ? entry->id : -1;
}


/**
 * Gets the frequency of a particular string within a lexhash.
 *
 * @param hash   The hash to look in.
 * @param token  The string to look for.
 * @return       The frequency of that string, or 0
 *               if the string is not in the hash
 *               (whgich is, of course, actually its frequency).
 */
int
cl_lexhash_freq(cl_lexhash hash, char *token)
{
  cl_lexhash_entry entry;

  entry = cl_lexhash_find_i(hash, token, NULL);
  return (entry != NULL) ? entry->freq : 0;
}


/**
 * Deletes a string from a hash.
 *
 * The entry corresponding to the specified string is
 * removed from the lexhash. If the string is not in the
 * lexhash to begin with, no action is taken.
 *
 * @param hash   The hash to alter.
 * @param token  The string to remove.
 * @return       The frequency of the deleted entry (0 if the string was not found in the hash).
 */
int
cl_lexhash_del(cl_lexhash hash, char *token)
{
  cl_lexhash_entry entry, previous;
  unsigned int offset, f;

  entry = cl_lexhash_find_i(hash, token, &offset);
  if (entry == NULL) {
    return 0;                   /* not in lexhash */
  }
  else {
    f = entry->freq;
    if (hash->table[offset] == entry) {
      hash->table[offset] = entry->next;
    }
    else {
      previous = hash->table[offset];
      while (previous->next != entry)
        previous = previous->next;
      previous->next = entry->next;
    }
    cl_delete_lexhash_entry(hash, entry);
    hash->entries--;
    return f;
  }
}



/**
 * Gets the number of different strings stored in a lexhash.
 *
 * This returns the total number of entries in all the
 * buckets in the whole hash table.
 *
 * @param hash  The hash to size up.
 */
int
cl_lexhash_size(cl_lexhash hash)
{
  return (hash != NULL) ? hash->entries : 0;
}


/**
 * Resets a lexhash's entry-iterator to the start of the hash.
 *
 * The iterator allows access over all entries in a lexhash.
 *
 * Note that there is only a single iterator for each cl_lexhash object,
 * so different parts of the application code must not try to iterate through
 * the hash at the same time.
 *
 * @param hash      The lexhash to iterate over.
 */
void
cl_lexhash_iterator_reset(cl_lexhash hash)
{
  assert((hash != NULL && hash->table != NULL && hash->buckets > 0) && "cl_lexhash object was not properly initialised");
  hash->iter_bucket = -1;
  hash->iter_point = NULL;
}

/**
 * Gets the next entry from the hash's entry-iterator.
 *
 * This function returns the next entry from the hash, or NULL if there are
 * no more entries. Keep in mind that the hash is traversed in an unspecified order.
 *
 * The iterator allows access over all the entries in a lexhash.
 *
 * Note that there is only a single iterator for each cl_lexhash object,
 * so different parts of the application code must not try to iterate through
 * the hash at the same time.
 *
 * @param hash      The lexhash to iterate over.
 */
cl_lexhash_entry
cl_lexhash_iterator_next(cl_lexhash hash)
{
  cl_lexhash_entry point;

  point = hash->iter_point;
  while (point == NULL) {
    hash->iter_bucket++;
    if (hash->iter_bucket >= hash->buckets)
      return NULL; /* we've reached the end of the hash */
    point = hash->table[hash->iter_bucket];
  }
  hash->iter_point = point->next;
  return point;
}
