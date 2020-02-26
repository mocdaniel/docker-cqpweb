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
#include "ngram-hash.h"

#include <math.h>


/** Defines the default number of buckets in an n-gram hash. */
#define DEFAULT_NR_OF_BUCKETS 250000

/** Default parameters for auto-growing the table of buckets (@see cl_ngram_hash_auto_grow_fillrate for details). */
#define DEFAULT_FILLRATE_LIMIT(n) 5.0
#define DEFAULT_FILLRATE_TARGET(n) 1.0 /**< keep memory overhead for bucket table below 50% */

/* -- fill rate limits could also be adapted to n-gram size, but this doesn't seem to improve speed much --
#define DEFAULT_FILLRATE_LIMIT(n) ((n >=5) ? 3.0 : 5.0)
#define DEFAULT_FILLRATE_TARGET(n) ((n >= 5) ? 0.5 : 1.0)
*/

/** Maximum number of buckets n-gram hash will try to allocate when auto-growing. */
#define MAX_BUCKETS 1000000007  /**< 1 billion (incremented to next prime number) */

/** Maximum number of entries that can be stored in the n-gram hash */
#define MAX_ENTRIES 2147483647  /**< 2^31 - 1 */


/*
 * basic utility functions
 */

/* find_prime() has been imported from "lexhash.h" */

/** Computes 32bit hash value for n-gram */
unsigned int
hash_ngram(int N, int *tuple)
{
  unsigned int result = 5381; /* seed value from DJB2, seems slightly better than original seed 0 */
  unsigned char *buffer = (unsigned char *)tuple;
  int i;

  /* hash function is designed for byte sequence and has poor distribution if applied directly to ints */
  for(i = 0 ; i < N * sizeof(int); i++)
    result = (result * 33) ^ (result >> 27) ^ buffer[i];
  return result;
}


/** TODO: consider alternative hash functions (see cl/lexhash.h) */


/*
 * cl_ngram_hash / cl_ngram_hash_entry  object definition
 */

/* cl_ngram_hash_entry is in <cl.h> */

/* typedef struct _cl_ngram_hash *cl_ngram_hash; in <cl.h> */


/**
 * Underlying structure for the cl_ngram_hash object.
 *
 * A cl_ngram_hash contains a number of buckets. Each bucket is
 * a linked-list of cl_ngram_hash_entry objects.
 *
 */
struct _cl_ngram_hash {
  cl_ngram_hash_entry *table;   /**< table of buckets; each "bucket" is a pointer to the list of entries that make up that bucket */
  unsigned int buckets;         /**< number of buckets in the hash table */
  int N;                        /**< n-gram size */
  int entries;                  /**< current number of entries in this hash */
  int auto_grow;                /**< boolean: whether to expand this hash automatically; true by default */
  double fillrate_limit;        /**< fillrate limit that triggers expansion of bucket table (with auto_grow) */
  double fillrate_target;       /**< target fillrate after expansion of bucket table (with auto_grow) */
  int iter_bucket;              /**< bucket currently processed by the single iterator of the hash table */
  cl_ngram_hash_entry iter_point;   /**< next entry to be returned by the iterator (NULL -> go to next bucket) */
};


/*
 * cl_ngram_hash methods
 */

/**
 * Creates a new cl_ngram_hash object.
 *
 * @param N          N-gram size
 * @param buckets    The number of buckets in the newly-created cl_ngram_hash;
 *                   set to 0 to use the default number of buckets.
 * @return           The new cl_ngram_hash.
 */
cl_ngram_hash
cl_new_ngram_hash(int N, int buckets)
{
  cl_ngram_hash hash;

  assert(N >= 1 && "cl_new_ngram_hash(): invalid N-gram size");
  if (buckets <= 0)
    buckets = DEFAULT_NR_OF_BUCKETS;

  hash = (cl_ngram_hash) cl_malloc(sizeof(struct _cl_ngram_hash));
  hash->N = N;
  hash->buckets = find_prime(buckets);
  hash->table = cl_calloc(hash->buckets, sizeof(cl_ngram_hash_entry));
  hash->entries = 0;
  hash->auto_grow = 1;
  hash->fillrate_limit = DEFAULT_FILLRATE_LIMIT(N);
  hash->fillrate_target = DEFAULT_FILLRATE_TARGET(N);
  hash->iter_bucket = -1;
  hash->iter_point = NULL;
  return hash;
}


/**
 * Deletes a cl_ngram_hash object.
 *
 * This deletes all the entries in all the buckets in the ngram_hash,
 * plus the cl_ngram_hash itself.
 *
 * @param hash  The cl_ngram_hash to delete.
 */
void
cl_delete_ngram_hash(cl_ngram_hash hash)
{
  int i;
  cl_ngram_hash_entry entry, temp;

  if (hash != NULL && hash->table != NULL) {
    for (i = 0; i < hash->buckets; i++) {
      entry = hash->table[i];
      while (entry != NULL) {
        temp = entry;
        entry = entry->next;
        /* cl_free(entry); -- changed to line below cos otherwise mem does not get freed. */
        cl_free(temp);
      }
    }
  }
  cl_free(hash->table);
  cl_free(hash);
}


/**
 * Turns a cl_ngram_hash's ability to auto-grow on or off.
 *
 * When this setting is switched on, the ngram_hash will grow
 * automatically to avoid performance degradation.
 *
 * Note the default value for this setting is SWITCHED ON.
 *
 * @see         cl_ngram_hash_auto_grow_fillrate, cl_ngram_hash_check_grow
 * @param hash  The hash that will be affected.
 * @param flag  New value for autogrow setting: boolean where
 *              true is on and false is off.
 */
void
cl_ngram_hash_auto_grow(cl_ngram_hash hash, int flag)
{
  if (hash)
    hash->auto_grow = flag;
}

/**
 * Configure auto-grow parameters.
 *
 * These settings are only relevant if auto-growing is enabled.
 *
 * The decision to expand the bucket table of a ngram_hash is based
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
 * The two fill rate parameters represent a trade-off between memory
 * overhead (8 bytes for each bucket) and performance (average number
 * of entries that have been checked for each hash access), which
 * depends crucially on the value of N (i.e. n-gram size).
 *
 * For N=1, a bucket table with low fill rate incurs a substantial
 * memory overhead, which may even exceed the storage required for the
 * entries themselves.  For large N, the relative memory overhead is
 * much smaller, while checking the list of entries in a bucket becomes
 * more expensive (N integer comparisons for each item).
 *
 * Note that the ratio limit / target determines how often the bucket
 * table has to be reallocated; it should not be smaller than 4.0.
 *
 * A reasonable values for the fill rate limit seems to be around 5.0;
 * if speed is crucial, N is relatively large, and memory footprint
 * isn't a concern, smaller values down to 2.0 might be chosen.
 * The target fill rate should not be set too low for small N.
 * If N=1, a target fill rate of 0.5 results in 100% memory overhead
 * after expansion of the bucket table (16 bytes per entry vs. 8 bytes
 * each for twice as many buckets as there are entries).
 *
 * When working on very large data sets, it is recommended to disable
 * auto-grow and initialise the n-gram hash with a sufficiently large
 * number of buckets.
 *
 * @see          cl_ngram_hash_auto_grow, cl_ngram_hash_check_grow
 * @param hash   The hash that will be affected.
 * @param limit  Fill rate limit, which triggers expansion of the n-gram hash
 * @param target Target fill rate after expansion (determines new number of buckets)
 */
void
cl_ngram_hash_auto_grow_fillrate(cl_ngram_hash hash, double limit, double target)
{
  if (hash) {
    /* set parameters with basic sanity checks */
    hash->fillrate_target = (target > 0.01) ? target : 0.01;
    hash->fillrate_limit  = (limit > 2 * hash->fillrate_target) ? limit : 2 * hash->fillrate_target;
  }
}



/**
 * Grows a ngram_hash table, increasing the number of buckets, if necessary.
 *
 * This functions is called after inserting a new entry into the n-gram hash.
 * If checks whether the current fill rate exceeds the specified limit.
 * If this is the case, and auto_grow is enabled, then the hash is expanded
 * by increasing the number of buckets, such that the new average fill rate
 * corresponds to the specified target value.  This gives the
 * hash better performance and makes it capable of absorbing more keys.
 *
 * If the bucket table would be expanded to more than MAX_BUCKETS entries,
 * auto-grow is automatically disabled for this ngram_hash.
 *
 * Note: this function also implements the hashing algorithm and must be
 * consistent with cl_ngram_hash_find_i().
 *
 * Usage: expanded = cl_ngram_hash_check_grow(cl_ngram_hash hash);
 *
 * This is a non-exported function.
 *
 * @see         cl_ngram_hash_auto_grow, cl_ngram_hash_auto_grow_fillrate
 * @param hash  The cl_ngram_hash to autogrow.
 * @return      Always 0.
 */
static int
cl_ngram_hash_check_grow(cl_ngram_hash hash)
{
  double fill_rate, target_size;
  cl_ngram_hash temp;
  cl_ngram_hash_entry entry, next;
  int idx, offset, old_buckets, new_buckets, N;

  old_buckets = hash->buckets;
  fill_rate = ((double) hash->entries) / old_buckets;
  if (hash->auto_grow && (fill_rate > hash->fillrate_limit)) {
    /* auto-grow is triggered */
    target_size = floor(((double) hash->entries) / hash->fillrate_target);
    if (target_size > MAX_BUCKETS) {
      if (cl_debug) {
        fprintf(stderr, "[n-gram hash autogrow: size limit %f exceeded by new target size %f, auto-growing will be disabled]\n",
                (double) MAX_BUCKETS, target_size);
      }
      hash->auto_grow = 0; /* disable auto-grow to avoid further unnecessary attempts */
      /* grow ngram_hash to maximum size, but not if this would extend bucket vector by less than 2x (to avoid large reallocation for little benefit) */
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
      fprintf(stderr, "[n-gram hash autogrow: triggered by fill rate = %3.1f (%d/%d)]\n",
              fill_rate, hash->entries, old_buckets);
      if (cl_debug >= 2)
        cl_ngram_hash_print_stats(hash, 12);
    }
    N = hash->N;
    temp = cl_new_ngram_hash(N, new_buckets); /* create new hash with target fill rate */
    new_buckets = temp->buckets; /* the actual number of entries (next prime number) */
    /* move all entries from hash to the appropriate bucket in temp */
    for (idx = 0; idx < old_buckets; idx++) {
      entry = hash->table[idx];
      while (entry != NULL) {
        next = entry->next;     /* remember pointer to next entry */
        offset = hash_ngram(N, entry->ngram) % new_buckets;
        entry->next = temp->table[offset]; /* insert entry into its bucket in temp (most buckets should contain only 1 entry, as long as hash->fillrate_target is less than 1) */
        temp->table[offset] = entry;
        temp->entries++;
        entry = next;           /* continue while loop */
      }
    }
    assert((temp->entries == hash->entries) && "ngram-hash.c: inconsistency during hash expansion");
    cl_free(hash->table);               /* old hash table should be empty and can be deallocated */
    hash->table = temp->table;          /* update hash from temp (copy hash table and its size) */
    hash->buckets = temp->buckets;
    cl_free(temp);                      /* we can simply deallocate temp now, having stolen its hash table */
    if (cl_debug) {
      fill_rate = ((double) hash->entries) / hash->buckets;
      fprintf(stderr, "[n-gram hash autogrow: new fill rate = %3.1f (%d/%d)]\n",
              fill_rate, hash->entries, hash->buckets);
    }
    return 1;
  }
  return 0;
}



/**
 * Finds the entry corresponding to a particular n-gram in a cl_ngram_hash.
 *
 * This function is the same as cl_ngram_hash_find(), but *ret_offset is set to
 * the hashtable offset computed for token (i.e. the index of the bucket within
 * the hashtable), unless *ret_offset == NULL.
 *
 * Note that this function hides the hashing algorithm details from the
 * rest of the n-gram hash implementation (except cl_ngram_hash_check_grow, which
 * re-implements the hashing algorithm for performance reasons).
 *
 * Usage: entry = cl_ngram_hash_find_i(cl_ngram_hash hash, char *token, unsigned int *ret_offset);
 *
 * This is a non-exported function.
 *
 * @param hash        The hash to search.
 * @param ngram       The ngram to look for.
 * @param ret_offset  This integer address will be filled with the token's
 *                    hashtable offset (can be NULL, in which case, ignored).
 * @return            The entry that is found (or NULL if the string is not
 *                    in the hash).
 */
static cl_ngram_hash_entry
cl_ngram_hash_find_i(cl_ngram_hash hash, int *ngram, unsigned int *ret_offset)
{
  unsigned int offset;
  int N;
  cl_ngram_hash_entry entry;

  assert((hash != NULL && hash->table != NULL && hash->buckets > 0) && "cl_ngram_hash object was not properly initialised");
  N = hash->N;

  /* get the offset of the bucket to look in by computing the hash of the string */
  offset = hash_ngram(N, ngram) % hash->buckets;
  if (ret_offset != NULL)
    *ret_offset = offset;
  /* check all entries in this bucket against the specified key */
  entry = hash->table[offset];
  while (entry != NULL && memcmp(entry->ngram, ngram, N * sizeof(int)) != 0)
    entry = entry->next;
  return entry;
}


/**
 * Finds the entry corresponding to a particular n-gram within a cl_ngram_hash.
 *
 * This function is basically a wrapper around the internal function cl_ngram_hash_find_i.
 *
 * @see               cl_ngram_hash_find_i
 * @param hash        The hash to search.
 * @param n-gram      The n-gram to look for.
 * @return            The entry that is found (or NULL if the n-gram is not
 *                    in the hash).
 */
cl_ngram_hash_entry
cl_ngram_hash_find(cl_ngram_hash hash, int *ngram)
{
  return cl_ngram_hash_find_i(hash, ngram, NULL);
}



/**
 * Adds an n-gram to a cl_ngram_hash table.
 *
 * If the n-gram is already in the hash, its frequency count
 * is increased by the specified value f.
 *
 * Otherwise, a new entry is created and its frequency count
 * is set to f.  The n-gram is embedded in the new hash entry,
 * so the original array does not need to be kept in memory.
 *
 * @param hash   The hash table to add to.
 * @param ngram  The n-gram to add.
 * @param f      Frequency count of the n-gram.
 * @return       A pointer to a (new or existing) entry
 */
cl_ngram_hash_entry
cl_ngram_hash_add(cl_ngram_hash hash, int *ngram, unsigned int f)
{
  cl_ngram_hash_entry entry, insert_point;
  unsigned int offset;          /* this will be set to the index of the bucket this token should go in
                                   by the call to cl_ngram_hash_find_i                                     */
  int N;

  entry = cl_ngram_hash_find_i(hash, ngram, &offset);
  N = hash->N;

  if (entry != NULL) {
    /* token already in hash -> increment frequency count */
    entry->freq += f;
  }
  else {
    /* token not in hash -> add new entry for this token */
    assert((hash->entries < MAX_ENTRIES) && "ngram-hash.c: maximum capacity of n-gram hash exceeded -- program abort");

    /* allocate enough space for n-gram appended to the struct */
    entry = (cl_ngram_hash_entry) cl_malloc(sizeof(struct _cl_ngram_hash_entry) + (N - 1) * sizeof(int));
    memcpy(entry->ngram, ngram, N * sizeof(int)); /* embed copy of n-gram in struct */
    entry->freq = f;
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
      cl_ngram_hash_check_grow(hash);
  }
  return entry;
}

/**
 * Gets the frequency of a particular n-gram within a cl_ngram_hash.
 *
 * @param hash   The hash to look in.
 * @param ngram  The ngram to look for.
 * @return       The frequency of that n-gram, or 0 if it is not in the hash
 */
int
cl_ngram_hash_freq(cl_ngram_hash hash, int *ngram)
{
  cl_ngram_hash_entry entry = cl_ngram_hash_find_i(hash, ngram, NULL);
  return (entry != NULL) ? entry->freq : 0;
}


/**
 * Deletes an n-gram from a hash.
 *
 * The entry corresponding to the specified n-gram is
 * removed from the cl_ngram_hash. If the n-gram is not in the
 * hash to begin with, no action is taken.
 *
 * @param hash   The hash to alter.
 * @param ngram  The n-gram to remove.
 * @return       The frequency of the deleted entry (0 if not found).
 */
int
cl_ngram_hash_del(cl_ngram_hash hash, int *ngram)
{
  cl_ngram_hash_entry entry, previous;
  unsigned int offset, f;

  entry = cl_ngram_hash_find_i(hash, ngram, &offset);
  if (entry == NULL)
    return 0;    /* not in n-gram hash */
  else {
    f = entry->freq;
    if (hash->table[offset] == entry)
      hash->table[offset] = entry->next;
    else {
      previous = hash->table[offset];
      while (previous->next != entry)
        previous = previous->next;
      previous->next = entry->next;
    }
    cl_free(entry);
    hash->entries--;
    return f;
  }
}



/**
 * Gets the number of distinct n-grams stored in a cl_ngram_hash.
 *
 * This returns the total number of entries in all the
 * buckets in the whole hash table.
 *
 * @param hash  The hash to size up.
 */
int
cl_ngram_hash_size(cl_ngram_hash hash)
{
  return (hash != NULL) ? hash->entries : 0;
}


/**
 * Get an array of all entries in an n-gram hash.
 *
 * This function returns a newly allocated array of cl_ngram_hash_entry
 * pointers enumerating all entries of the hash in an unspecified order.
 *
 * @param hash      The n-gram hash to operate on.
 * @param ret_size  If not NULL, the number of entries in the returned
 *                  array will be stored in this location.
 */
cl_ngram_hash_entry *
cl_ngram_hash_get_entries(cl_ngram_hash hash, int *ret_size)
{
  cl_ngram_hash_entry *result, entry;
  int size, point;
  unsigned int offset;

  assert((hash != NULL && hash->table != NULL && hash->buckets > 0) && "cl_ngram_hash object was not properly initialised");

  /* allocate memory for enumeration of entries */
  size = hash->entries;
  result = cl_malloc(size * sizeof(cl_ngram_hash_entry));
  if (ret_size != NULL)
    *ret_size = size;

  /* traverse hash and insert all entries into the array */
  point = 0;
  for (offset = 0; offset < hash->buckets; offset++) {
    entry = hash->table[offset];
    while (entry != NULL) {
      assert((point < size) && "ngram-hash.c: major internal inconsistency");
      result[point++] = entry;
      entry = entry->next;
    }
  }
  assert((point == size) && "ngram-hash.c: major internal inconsistency");

  return result;
}



/**
 * Iterate over all entries in an n-gram hash.
 *
 * Note that there is only a single iterator for each cl_ngram_hash object,
 * so different parts of the application code must not try to iterate through
 * the hash at the same time.
 *
 * This function resets the iterator to the start of the hash.
 *
 * @param hash      The n-gram hash to iterate over.
 */
void
cl_ngram_hash_iterator_reset(cl_ngram_hash hash)
{
  assert((hash != NULL && hash->table != NULL && hash->buckets > 0) && "cl_ngram_hash object was not properly initialised");
  hash->iter_bucket = -1;
  hash->iter_point = NULL;
}

/**
 * Iterate over all entries in an n-gram hash.
 *
 * Note that there is only a single iterator for each cl_ngram_hash object,
 * so different parts of the application code must not try to iterate through
 * the hash at the same time.
 *
 * This function returns the next entry from the hash, or NULL if there are
 * no more entries.  Keep in mind that the hash is traversed in an unspecified order.
 *
 * @param hash      The n-gram hash to iterate over.
 */
cl_ngram_hash_entry
cl_ngram_hash_iterator_next(cl_ngram_hash hash)
{
  cl_ngram_hash_entry point;

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

/**
 * Compute statistics on bucket fill rates (for debugging and optimization).
 *
 * This function returns an allocated integer array of length max_n + 1, whose
 * i-th entry specifies the number of buckets containing i keys.  For i == 0, this
 * is the number of empty buckets. The last entry (i == max_n) is the cumulative
 * number of buckets containing i or more entries.
 *
 * @param hash      The n-gram hash.
 * @param max_n     Count buckets with up to max_n entries.
 */
int *
cl_ngram_hash_stats(cl_ngram_hash hash, int max_n)
{
  int *stats;
  int i, n;
  cl_ngram_hash_entry point;

  assert(max_n > 0);
  assert((hash != NULL && hash->table != NULL && hash->buckets > 0) && "cl_ngram_hash object was not properly initialised");
  stats = cl_calloc(max_n + 1, sizeof(int));

  for (i = 0; i < hash->buckets; i++) {
    point = hash->table[i];
    n = 0;
    while (point) {
      point = point->next;
      n++;
    }
    if (n >= max_n)
      stats[max_n]++;
    else
      stats[n]++;
  }
  return stats;
}

/**
 * Display statistics on bucket fill rates (for debugging and optimization).
 *
 * This function prints a table showing the distribution of bucket sizes, i.e.
 * how many buckets contain a given number of keys.  The table will be printed
 * to STDERR, as all debugging output in CWB.
 *
 * @param hash      The n-gram hash.
 * @param max_n      Count buckets with up to max_n entries.
 */
void
cl_ngram_hash_print_stats(cl_ngram_hash hash, int max_n)
{
  int *stats = cl_ngram_hash_stats(hash, max_n);  /* also performs sanity checks */
  double rate, p;
  int i;

  rate = ((double) hash->entries) / hash->buckets;
  fprintf(stderr, "N-gram hash fill rate: %5.2f (%d entries in %d buckets)\n",
          rate, hash->entries, hash->buckets);
  fprintf(stderr, "# entries: ");
  for (i = 0; i <= max_n; i++)
    fprintf(stderr, "%8d", i);
  fprintf(stderr, "+\n");
  fprintf(stderr, "bucket cnt:");
  for (i = 0; i <= max_n; i++)
    fprintf(stderr, "%8d", stats[i]);
  fprintf(stderr, "\n");
  fprintf(stderr, "expected:  ");
  p = exp(-rate); /* expected number of entries for ideal hash function (Poisson distribution) */
  for (i = 0; i < max_n; i++) {
    fprintf(stderr, "%8.0f", p * hash->buckets);
    p *= rate / (i + 1);
  }
  fprintf(stderr, "\n");

  cl_free(stats);
}
