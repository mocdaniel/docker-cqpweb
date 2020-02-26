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


#include "../cl/cl.h"
#include "../cl/cwb-globals.h"
#include "../cl/corpus.h"
#include "../cl/attributes.h"
#include "../cl/storage.h"
#include "../cl/bitio.h"

/** Level of progress-info (inc compression protocol) message output: 0 = none, 4 = everything. */
int the_truth_is_out_there = 0;
/** File handle for this program's progress-info output: note, it is always stdout */
FILE *protocol;

/* ---------------------------------------------------------------------- */

char *progname;

Corpus *corpus;
char *corpus_id = NULL;

int debug = 0;

void huffcode_usage(char *msg, int error_code);

/* ---------------------------------------------------------------------- */

/**
 * Prints, to the specified stream, a string containing
 * a binary representation of an integer.
 *
 * @param i       Integer to print.
 * @param width   Number of bits in the integer.
 * @param stream  Where to print to.
 */
void
bprintf(unsigned int i, int width, FILE *stream)
{
  putc((width <= 31) ? ' ' : (i & 1<<31 ? '1' : '0'), stream);
  putc((width <= 30) ? ' ' : (i & 1<<30 ? '1' : '0'), stream);
  putc((width <= 29) ? ' ' : (i & 1<<29 ? '1' : '0'), stream);
  putc((width <= 28) ? ' ' : (i & 1<<28 ? '1' : '0'), stream);
  putc((width <= 27) ? ' ' : (i & 1<<27 ? '1' : '0'), stream);
  putc((width <= 26) ? ' ' : (i & 1<<26 ? '1' : '0'), stream);
  putc((width <= 25) ? ' ' : (i & 1<<25 ? '1' : '0'), stream);
  putc((width <= 24) ? ' ' : (i & 1<<24 ? '1' : '0'), stream);
  putc('.', stream);
  putc((width <= 23) ? ' ' : (i & 1<<23 ? '1' : '0'), stream);
  putc((width <= 22) ? ' ' : (i & 1<<22 ? '1' : '0'), stream);
  putc((width <= 21) ? ' ' : (i & 1<<21 ? '1' : '0'), stream);
  putc((width <= 20) ? ' ' : (i & 1<<20 ? '1' : '0'), stream);
  putc((width <= 19) ? ' ' : (i & 1<<19 ? '1' : '0'), stream);
  putc((width <= 18) ? ' ' : (i & 1<<18 ? '1' : '0'), stream);
  putc((width <= 17) ? ' ' : (i & 1<<17 ? '1' : '0'), stream);
  putc((width <= 16) ? ' ' : (i & 1<<16 ? '1' : '0'), stream);
  putc('.', stream);
  putc((width <= 15) ? ' ' : (i & 1<<15 ? '1' : '0'), stream);
  putc((width <= 14) ? ' ' : (i & 1<<14 ? '1' : '0'), stream);
  putc((width <= 13) ? ' ' : (i & 1<<13 ? '1' : '0'), stream);
  putc((width <= 12) ? ' ' : (i & 1<<12 ? '1' : '0'), stream);
  putc((width <= 11) ? ' ' : (i & 1<<11 ? '1' : '0'), stream);
  putc((width <= 10) ? ' ' : (i & 1<<10 ? '1' : '0'), stream);
  putc((width <=  9) ? ' ' : (i & 1<<9  ? '1' : '0'), stream);
  putc((width <=  8) ? ' ' : (i & 1<<8  ? '1' : '0'), stream);
  putc('.', stream);
  putc((width <=  7) ? ' ' : (i & 1<<7  ? '1' : '0'), stream);
  putc((width <=  6) ? ' ' : (i & 1<<6  ? '1' : '0'), stream);
  putc((width <=  5) ? ' ' : (i & 1<<5  ? '1' : '0'), stream);
  putc((width <=  4) ? ' ' : (i & 1<<4  ? '1' : '0'), stream);
  putc((width <=  3) ? ' ' : (i & 1<<3  ? '1' : '0'), stream);
  putc((width <=  2) ? ' ' : (i & 1<<2  ? '1' : '0'), stream);
  putc((width <=  1) ? ' ' : (i & 1<<1  ? '1' : '0'), stream);
  putc((width <=  0) ? ' ' : (i & 1     ? '1' : '0'), stream);
}




/**
 * Dumps the specified heap of memory to the program's STDOUT.
 *
 * @see protocol
 * @param heap       Location of the heap to dump.
 * @param heap_size  Number of nodes in the heap.
 * @param node       Heap at which to begin dumping.
 * @param indent     How many tabs to indent the start of each line.
 *
 */
void
dump_heap(int *heap, int heap_size, int node, int indent)
{
  int i;

  if (node <= heap_size) {
    for (i = 0; i < indent * 3; i++)
      putc((i % 3) == 0 ? '|' : ' ', protocol);

    fprintf(protocol, "Node %d (p: %d, f: %d)\n",
            node,
            heap[node-1],
            heap[heap[node-1]]);

    dump_heap(heap, heap_size, 2 * node,     indent + 1);
    dump_heap(heap, heap_size, 2 * node + 1, indent + 1);
  }
}



/**
 * Prints a description of the specified heap of memory to the program's STDOUT.
 *
 * @see protocol
 * @param heap       Location of the heap to print.
 * @param heap_size  Number of nodes in the heap.
 * @param title      Title of the heap to print.
 */
void
print_heap(int *heap, int heap_size, char *title)
{
  fprintf(protocol, "\nDump of %s (size %d)\n\n", title, heap_size);
  dump_heap(heap, heap_size, 1, 0);
  fprintf(protocol, "\x0c");
}



/**
 * Sifts the heap into order.
 *
 * @param heap       Location of the heap to sift. TODO, could it take unsigned int, which is what the heap it's used on is????
 * @param heap_size  Number of nodes in the heap.
 * @param node       Node at which to begin sifting.
 * @return           Number of swaps needed during the sifting.
 */
static int
sift(int *heap, int heap_size, int node)
{
  register int tmp;
  register int child = node * 2;
  int swaps = 0;

  /* we address the heap in the following manner: when we start
   * indices at 1, the left child is at 2i, and the right child is at 2i+1.
   * So we maintain this scheme and decrement just before addressing the array.
   *
   * left child in 2*node, right child in 2*node + 1, parent in node
   */

  while (child <= heap_size) {
    if (child < heap_size && heap[heap[child]] < heap[heap[child-1]])
      /* select right branch in heap[child+1-1] */
      child++;

    if (heap[heap[node-1]] > heap[heap[child-1]]) {
      /* root is larger than selected child, so we have to swap and recurse down */
      swaps++;
      tmp = heap[child-1];
      heap[child-1] = heap[node-1];
      heap[node-1] = tmp;

      /* recurse downwards */
      node = child;
      child = node * 2;
    }
    else
      break;
      /* heap constraints seem to be in order */
  }

  return swaps;
}



/**
 * Writes a Huffman code descriptor to file.
 *
 * @param filename  Path to file where descriptor is to be saved.
 * @param hc        Pointer to the descriptor block to save.
 * @return          Boolean: true for all OK, false for error.
 */
int
WriteHCD(char *filename, HCD *hc)
{
  FILE *dst;

  if (!(dst = fopen(filename, "wb"))) {
    perror(filename);
    return 0;
  }

  NwriteInt(hc->size, dst);
  NwriteInt(hc->length, dst);
  NwriteInt(hc->min_codelen, dst);
  NwriteInt(hc->max_codelen, dst);

  NwriteInts(hc->lcount, MAXCODELEN, dst);
  NwriteInts(hc->symindex, MAXCODELEN, dst);
  NwriteInts(hc->min_code, MAXCODELEN, dst);

  assert(hc->symbols);
  NwriteInts(hc->symbols, hc->size, dst);

  fclose(dst);
  return 1;
}



/**
 * Reads a Huffman Code Descriptor from file.
 *
 * @param filename  Path to file where descriptor is saved.
 * @param hc        Pointer to location where the descriptor block will be loaded to.
 * @return          Boolean: true for all OK, false for error.
 */
int
ReadHCD(char *filename, HCD *hc)
{
  FILE *src;

  if (!(src = fopen(filename, "rb"))) {
    perror(filename);
    return 0;
  }

  NreadInt(&hc->size, src);
  NreadInt(&hc->length, src);
  NreadInt(&hc->min_codelen, src);
  NreadInt(&hc->max_codelen, src);
  NreadInts(hc->lcount, MAXCODELEN, src);
  NreadInts(hc->symindex, MAXCODELEN, src);
  NreadInts(hc->min_code, MAXCODELEN, src);

  hc->symbols = (int *)cl_malloc(sizeof(int) * hc->size);
  NreadInts(hc->symbols, hc->size, src);

  fclose(src);
  return 1;
}
/* TODO: should these two functions perhaps be in cl/attributes.h? (prototype of HCD is in attributes.h)
 *       or all HCD object in separate module? */




/* ================================================== COMPRESSION */

/**
 * Compresses the token stream of a p-attribute.
 *
 * Three files are created: the compressed token stream, the descriptor block,
 * and a sync file.
 *
 * @param attr  The attribute to compress.
 * @param hc    Location for the resulting Huffmann code descriptor block.
 * @param fname Base filename for the resulting files.
 */
int
compute_code_lengths(Attribute *attr, HCD *hc, char *fname)
{
  int id, i, h;
//  int nr_codes = 0; // not used - get rid to suppress gcc warning

  /* note that heap functions typically need int * not unsigned int *,
   * so there will be plenty of typecasting about ...  */
  unsigned int *heap = NULL;
  /* must be unsigned: because of add-1 trick below to avoid codes longer than 32 bits,
     cumulative frequencies in heap may exceed 2G limit */
  unsigned int *codelength = NULL;

  int issued_codes[MAXCODELEN];
  int next_code[MAXCODELEN];

  long sum_bits;

  Component *comp;

  printf("COMPRESSING TOKEN STREAM of %s.%s\n", corpus_id, attr->any.name);

  /* We need the following components:
   * - CompCorpus
   * - CompLexicon
   * - CompLexiconIdx
   * - CompCorpusFreqs
   * and want to force the CL to use them rather than compressed data.
   */
  if (!(comp = ensure_component(attr, CompCorpus, 0))) {
    fprintf(stderr, "Computation of huffman codes needs the CORPUS component\n");
    exit(1);
  }
  if (!(comp = ensure_component(attr, CompLexicon, 0))) {
    fprintf(stderr, "Computation of huffman codes needs the LEXION component\n");
    exit(1);
  }
  if (!(comp = ensure_component(attr, CompLexiconIdx, 0))) {
    fprintf(stderr, "Computation of huffman codes needs the LEXIDX component\n");
    exit(1);
  }
  if (!(comp = ensure_component(attr, CompCorpusFreqs, 0))) {
    fprintf(stderr, "Computation of huffman codes needs the FREQS component.\n"
            "Run 'makeall -r %s -c FREQS %s %s' in order to create it.\n",
            corpus->registry_dir, corpus->registry_name, attr->any.name);
    exit(1);
  }


  /*
   * closely follows Witten/Moffat/Bell: ``Managing Gigabytes'', pp. 335ff.
   */
  hc->size = cl_max_id(attr);                /* the size of the attribute (nr of items) */
  if ((hc->size <= 0) || !CL_ALL_OK()) {
    cl_error("(aborting) cl_max_id() failed");
    exit(1);
  }

  hc->length = cl_max_cpos(attr); /* the length of the attribute (nr of tokens) */
  if ((hc->length <= 0) || !CL_ALL_OK()) {
    cl_error("(aborting) cl_max_cpos() failed");
    exit(1);
  }

  hc->symbols = NULL;
  hc->min_codelen = 100;
  hc->max_codelen = 0;

  memset((char *)hc->lcount,   '\0', MAXCODELEN * sizeof(int));
  memset((char *)hc->min_code, '\0', MAXCODELEN * sizeof(int));
  memset((char *)hc->symindex, '\0', MAXCODELEN * sizeof(int));
  memset((char *)issued_codes, '\0', MAXCODELEN * sizeof(int));

  codelength = (unsigned int *)cl_calloc(hc->size, sizeof(unsigned));


  /* =========================================== make & initialize the heap */

  heap = (unsigned int *)cl_malloc(hc->size * 2 * sizeof(int));
  /* The heap initially consists of two consecutive arrays:
   * a) an array of pointers into the second array (given as offsets on heap[])
   * b) an array of frequency counts for the leaves of the Huffman tree
   * In the algorithm below, inner nodes of the tree are created by merging the two least frequent nodes.
   * The corresponding frequency counts, which are no longer needed, are replaced by pointers
   * to the parent node.
   */

  for (i = 0; i < hc->size; i++) {
    heap[i] = hc->size + i;
    heap[hc->size+i] = cl_id2freq(attr, i) + 1;
    /* add-one trick needed to avoid unsupported Huffman codes > 31 bits for very large corpora of ca. 2 billion words:
     * theoretical optimal code length for hapax legomena in such corpora is ca. 31 bits, and the Huffman algorithm
     * sometimes generates 32-bit codes; with add-one trick, the theoretical optimal code length is always <= 30 bits
     * NB: this means that cumulative frequency counts may exceed the corpus size and hence the 2G limit (-> unsigned int)
     */
  }

  /* ============================== PROTOCOL ============================== */
  if (the_truth_is_out_there > 0) {
    fprintf(protocol, "Allocated heap with %d cells for %d items\n\n", hc->size * 2, hc->size);
    if (the_truth_is_out_there > 2)
      print_heap((int *)heap, hc->size, "After Initialization");
  }
  /* ============================== PROTOCOL ============================== */



  /* ================================================== Phase 1 */

  h = hc->size;

  /*
   * we address the heap in the following manner: when we start array
   * indices at 1, the left child is at 2i, and the right child is at
   * 2i+1. So we maintain this scheme and decrement just before
   * addressing the array.
   */

  /*
   * construct the initial min-heap;
   *
   * do:
   * bottom up, left to right,
   * for each root of each subtree, sift if necessary
   */
  for (i = hc->size/2; i > 0; i--)
    sift((int *)heap, h, i);

  /* ============================== PROTOCOL ============================== */
  if (the_truth_is_out_there > 2) {
    print_heap((int *)heap, hc->size, "Initial Min-Heap");
    fprintf(protocol, "\n");
  }
  /* ============================== PROTOCOL ============================== */



  /* ================================================== Phase 2 */

  /* smallest item at top of heap now, remove the two smallest items
   * and sift, find second smallest by removing top and sifting, as
   * long as we have more than one root */
  while (h > 1) {
    int pos[2];

    for (i = 0; i < 2; i++) {
      /* remove topmost (i.e. smallest) item */
      pos[i] = heap[0];
      /* remove and sift, to reobtain heap integrity: move ``last'' item to top of heap and sift */
      heap[0] = heap[--h];
      sift((int *)heap, h, 1);
    }

    /* ============================== PROTOCOL ============================== */
    if (the_truth_is_out_there > 3) {
      fprintf(protocol, "Removed     smallest item %d with freq %d\n", pos[0], heap[pos[0]]);
      fprintf(protocol, "Removed 2nd smallest item %d with freq %d\n", pos[1], heap[pos[1]]);
    }
    /* ============================== PROTOCOL ============================== */

    /*
     * pos[0] and pos[1] contain pointers to the two smallest items now.
     * Since h was decremented twice, h and h+1 are now empty and
     * become the accumulated freq of pos[i].
     * The individual frequencies are not needed any more,
     * so pointers to h+1 (the acc freq) are stored there instead
     * (tricky, since freq cell becomes pointer cell).
     * So, what happens here, is to include a new element in the heap.
     */
    heap[h] = h+1;
    heap[h+1] = heap[pos[0]] + heap[pos[1]]; /* accumulated freq */
    heap[pos[0]] = heap[pos[1]] = h+1; /* pointers! */
    h++;

    /* we put a new element into heap; now, swap it up until we reobtain heap integrity */
    {
      register int parent, current;
      current = h;
      parent = current >> 1;

      while ((parent > 0) && (heap[heap[parent-1]] > heap[heap[current-1]])) {
        int tmp = heap[parent-1];
        heap[parent-1] = heap[current-1];
        heap[current-1] = tmp;

        current = parent;
        parent = current >> 1;
      }
    }
  }

  /* ============================== PROTOCOL ============================== */
  if (the_truth_is_out_there > 3)
    fprintf(protocol, "\n");
  /* ============================== PROTOCOL ============================== */



  /* ================================================== Phase 3 */

  /* compute the code lengths. We don't have any freqs in heap any more, only pointers to parents */

  heap[0] = -1U;

  /* root has a depth of 0 */
  heap[1] = 0;

  /* we trust in what they say on p. 345 */

  for (i = 2; i < hc->size * 2; i++)
    heap[i] = heap[heap[i]]+1;

  /* collect the lengths */
  sum_bits = 0L;
  for (i = 0; i < hc->size; i++) {
    int code_len = heap[i+hc->size];
    if (code_len == 0) {
      assert((hc->size == 1) && "Major error: code length of 0 bits should only happen for lexicon size = 1");
      code_len = 1; /* special case: if lexicon contains only a single type, generate 1-bit code '0' */
    }

    sum_bits += code_len * cl_id2freq(attr, i);
    codelength[i] = code_len;

    if (code_len > hc->max_codelen)
      hc->max_codelen = code_len;
    if (code_len < hc->min_codelen)
      hc->min_codelen = code_len;

    hc->lcount[code_len]++;
  }

  /* ============================== PROTOCOL ============================== */
  if (the_truth_is_out_there > 0) {
    fprintf(protocol, "Minimal code length: %3d\n", hc->min_codelen);
    fprintf(protocol, "Maximal code length: %3d\n", hc->max_codelen);
    fprintf(protocol, "Compressed code len: %10ld bits, %10ld (+1) bytes\n\n\n", sum_bits, sum_bits/8);
  }
  /* ============================== PROTOCOL ============================== */

  if (hc->max_codelen >= MAXCODELEN) {
    fprintf(stderr, "Error: Huffman codes too long (%d bits, current maximum is %d bits).\n", hc->max_codelen, MAXCODELEN-1);
    fprintf(stderr, "       Please contact the CWB development team for assistance.\n");
    exit(1);
  }

  if (hc->max_codelen == 0 && hc->min_codelen == 100)
    fprintf(stderr, "Problem: No output generated -- no items?\n");
  else {
    hc->min_code[hc->max_codelen] = 0;

    for (i = hc->max_codelen-1; i > 0; i--)
      hc->min_code[i] = (hc->min_code[i+1] + hc->lcount[i+1]) >> 1;

    hc->symindex[hc->min_codelen] = 0;
    for (i = hc->min_codelen+1; i <= hc->max_codelen; i++)
      hc->symindex[i] = hc->symindex[i-1] + hc->lcount[i-1];


    /* ============================== PROTOCOL ============================== */
    if (the_truth_is_out_there > 0) {
      int sum_codes = 0;
      fprintf(protocol, " CL  #codes  MinCode   SymIdx\n");
      fprintf(protocol, "----------------------------------------\n");

      for (i = hc->min_codelen; i <= hc->max_codelen; i++) {
        sum_codes += hc->lcount[i];
        fprintf(protocol, "%3d %7d  %7d  %7d\n", i, hc->lcount[i], hc->min_code[i], hc->symindex[i]);
      }
      fprintf(protocol, "----------------------------------------\n");
      fprintf(protocol, "    %7d\n", sum_codes);
    }
    /* ============================== PROTOCOL ============================== */


    for (i = 0; i < MAXCODELEN; i++)
      next_code[i] = hc->min_code[i];

    /* ============================== PROTOCOL ============================== */
    if (the_truth_is_out_there > 1) {
      fprintf(protocol, "\n");
      fprintf(protocol, "   Item   f(item)  CL      Bits     Code, String\n");
      fprintf(protocol, "------------------------------------------------------------------------\n");
    }
    /* ============================== PROTOCOL ============================== */

    /* compute and issue codes */

    hc->symbols = (int *)heap + hc->size;

    for (i = 0; i < hc->size; i++) {
      /* we store the code for item i in heap[i] */
      heap[i] = next_code[codelength[i]];
      next_code[codelength[i]]++;

      /* ============================== PROTOCOL ============================== */
      if (the_truth_is_out_there > 1) {
        int freq = cl_id2freq(attr, i);
        fprintf(protocol, "%7d  %7d  %3d  %10d ",
                i,
                freq,
                codelength[i],
                codelength[i] * freq
                );
        bprintf(heap[i], codelength[i], protocol);
        fprintf(protocol, "  %7d  %s\n", heap[i], cl_id2str(attr, i));
      }
      /* ============================== PROTOCOL ============================== */

      /* and put the item itself in the second half of the table */
      heap[hc->size+hc->symindex[codelength[i]]+issued_codes[codelength[i]]] = i;
      issued_codes[codelength[i]]++;
    }

    /* ============================== PROTOCOL ============================== */
    if (the_truth_is_out_there > 1)
      fprintf(protocol, "------------------------------------------------------------------------\n");
    /* ============================== PROTOCOL ============================== */


    /* The work itself -- encode the attribute data */
    {
      char *path;

      char hcd_path[CL_MAX_LINE_LENGTH];
      char huf_path[CL_MAX_LINE_LENGTH];
      char sync_path[CL_MAX_LINE_LENGTH];

      Component *corp;

      BFile bfd;
      FILE *sync;

      int cl, code, pos;

      corp = ensure_component(attr, CompCorpus, 0);
      assert(corp);

      if (fname) {
        path = fname;
        sprintf(hcd_path, "%s.hcd", path);
        sprintf(huf_path, "%s.huf", path);
        sprintf(sync_path, "%s.huf.syn", path);
      }
      else {
        path = component_full_name(attr, CompHuffSeq, NULL);
        assert(path); /* additonal condition (cderrno == CDA_OK) removed, since component_full_name doesn't (re)set cderrno (also on next two) */
        strcpy(huf_path, path);

        path = component_full_name(attr, CompHuffCodes, NULL);
        assert(path);
        strcpy(hcd_path, path);

        path = component_full_name(attr, CompHuffSync, NULL);
        assert(path);
        strcpy(sync_path, path);
      }

      printf("- writing code descriptor block to %s\n",  hcd_path);
      if (!WriteHCD(hcd_path, hc)) {
        fprintf(stderr, "ERROR: writing %s failed. Aborted.\n", hcd_path);
        exit(1);
      }

      printf("- writing compressed item sequence to %s\n", huf_path);

      if (!BFopen(huf_path, "w", &bfd)) {
        fprintf(stderr, "ERROR: can't create file %s\n", huf_path);
        perror(huf_path);
        exit(1);
      }

      printf("- writing sync (every %d tokens) to %s\n", SYNCHRONIZATION, sync_path);

      if ((sync = fopen(sync_path, "w")) == NULL) {
        fprintf(stderr, "ERROR: can't create file %s\n", sync_path);
        perror(sync_path);
        exit(1);
      }

      for (i = 0; i < hc->length; i++) {

        /* SYNCHRONIZE */
        if ((i % SYNCHRONIZATION) == 0) {
          if (i > 0)
            BFflush(&bfd);
          pos = BFposition(&bfd);
          NwriteInt(pos, sync);
        }

        id = cl_cpos2id(attr, i);
        if (id < 0 || !CL_ALL_OK()) {
          cl_error("(aborting) cl_cpos2id() failed");
          exit(1);
        }
        else {
          assert((id >= 0) && (id < hc->size) && "Internal Error");

          cl = codelength[id];
          code = heap[id];

          if (!BFwriteWord((unsigned int)code, cl, &bfd)) {
            fprintf(stderr, "Error writing code for ID %d (%d, %d bits) at position %d. Aborted.\n", id, code, cl, i);
            exit(1);
          }
        }
      }

      fclose(sync);
      BFclose(&bfd);
    }
  }

  cl_free(codelength);
  cl_free(heap);

  return 1;
}



/* ================================================== DECOMPRESSION & ERROR CHECKING */

/**
 * Checks a huffcoded attribute for errors by decompressing it.
 *
 * This function assumes that compute_code_lengths() has been called
 * beforehand and made sure that the _uncompressed_ token sequence is
 * used by CL access functions.
 *
 * It exits on error, so there's no return value.
 *
 * @param attr  The attribute to check.
 * @param fname Base filename to use for the three compressed-attribute files.
 *              Can be NULL, in which case the filenames in the attribute are used.
 */
void
decode_check_huff(Attribute *attr, char *fname)
{
  BFile bfd;
  FILE *sync;
  HCD hc;

  int pos, size, sync_offset, offset;

  int l, v;
  int item, true_item;

  unsigned char bit;

  char hcd_path[CL_MAX_LINE_LENGTH];
  char huf_path[CL_MAX_LINE_LENGTH];
  char sync_path[CL_MAX_LINE_LENGTH];


  printf("VALIDATING %s.%s\n", corpus_id, attr->any.name);

  if (fname) {
    sprintf(hcd_path, "%s.hcd", fname);
    sprintf(huf_path, "%s.huf", fname);
    sprintf(sync_path, "%s.huf.syn", fname);
  }
  else {
    char *path;

    path = component_full_name(attr, CompHuffSeq, NULL);
    assert(path && CL_ALL_OK());
    cl_strcpy(huf_path, path);

    path = component_full_name(attr, CompHuffCodes, NULL);
    assert(path && CL_ALL_OK());
    cl_strcpy(hcd_path, path);

    path = component_full_name(attr, CompHuffSync, NULL);
    assert(path && CL_ALL_OK());
    cl_strcpy(sync_path, path);
  }

  printf("- reading code descriptor block from %s\n", hcd_path);
  if (!ReadHCD(hcd_path, &hc)) {
    fprintf(stderr, "ERROR: reading %s failed. Aborted.\n",  hcd_path);
    exit(1);
  }

  printf("- reading compressed item sequence from %s\n", huf_path);
  if (!BFopen(huf_path, "r", &bfd)) {
    fprintf(stderr, "ERROR: can't open file %s. Aborted.\n", huf_path);
    perror(huf_path);
    exit(1);
  }

  printf("- reading sync (mod %d) from %s\n", SYNCHRONIZATION, sync_path);
  if (!(sync = fopen(sync_path, "r"))) {
    fprintf(stderr, "ERROR: can't open file %s. Aborted.\n", sync_path);
    perror(sync_path);
    exit(1);
  }

  size = cl_max_cpos(attr);
  if (size != hc.length) {
    fprintf(stderr, "ERROR: wrong corpus size (%d tokens) in %s (correct size: %d)\n",
            hc.length, hcd_path, size);
    exit(1);
  }

  for (pos = 0; pos < hc.length; pos++) {

    if (0 == (pos % SYNCHRONIZATION)) {
      offset = BFposition(&bfd); /* need to get offset before flushing (because flushing fills the bit buffer and advances offset to the following byte!) */
      if (pos > 0)
        BFflush(&bfd);
      sync_offset = -1;                /* make sure we get an error if read below fails */
      NreadInt(&sync_offset, sync);
      if (offset != sync_offset) {
        fprintf(stderr, "ERROR: wrong sync offset %d (true offset %d) at cpos %d. Aborted.\n",sync_offset, offset, pos);
        exit(1);
      }
    }

    if (!BFread(&bit, 1, &bfd)) {
      fprintf(stderr, "ERROR reading file %s. Aborted.\n", huf_path);
      exit(1);
    }

    v = (bit ? 1 : 0);
    l = 1;
    while (v < hc.min_code[l]) {
      if (!BFread(&bit, 1, &bfd)) {
        fprintf(stderr, "ERROR reading file %s. Aborted.\n", huf_path);
        return;
      }
      v <<= 1;
      if (bit)
        v++;
      l++;
    }

    item = hc.symbols[hc.symindex[l] + v - hc.min_code[l]];
    true_item = cl_cpos2id(attr, pos);
    if (item != true_item)
      fprintf(stderr, "ERROR: wrong token (id=%d) at cpos %d (correct id=%d). Aborted.\n", item, pos, true_item);
  }
  fclose(sync);
  BFclose(&bfd);

  /* tell the user it's safe to delete the uncompressed CORPUS component now */
  printf("!! You can delete the file <%s> now.\n", component_full_name(attr, CompCorpus, NULL));
}




/**
 * Prints a usage message and exits the program.
 *
 * @param msg         A message about the error.
 * @param error_code  Value to be returned by the program when it exits.
 */
void
huffcode_usage(char *msg, int error_code)
{
  if (msg)
    fprintf(stderr, "Usage error: %s\n", msg);
  fprintf(stderr, "\n");
  fprintf(stderr, "Usage:  %s [options] <corpus>\n\n", progname);
  fprintf(stderr, "Compress the token sequence of a positional attribute. Creates .huf, .hcd,\n");
  fprintf(stderr, "and .huf.syn files, which replace the corresponding .corpus files. After\n");
  fprintf(stderr, "running this tool successfully, the .corpus files can be deleted.\n");
  fprintf(stderr, "\n");
  fprintf(stderr, "Options:\n");
  fprintf(stderr, "  -P <att>  compress attribute <att> [default: word]\n");
  fprintf(stderr, "  -A        compress all positional attributes\n");
  fprintf(stderr, "  -r <dir>  set registry directory\n");
  fprintf(stderr, "  -f <file> set output file prefix (creates <file>.huf, ...)\n");
  fprintf(stderr, "  -v        increase verbosity (prints protocol); \n"
                  "            may be repeated up to 4 times\n");
/*   fprintf(stderr, "  -d        debug mode (not implemented)\n"); *//* TODO -d / -D distinct as in cwb-compress-rdx? */
  fprintf(stderr, "  -T        skip validation pass ('I trust you')\n");
  fprintf(stderr, "  -h        this help page\n\n");
  fprintf(stderr, "Part of the IMS Open Corpus Workbench v" CWB_VERSION "\n\n");

  if (corpus)
    cl_delete_corpus(corpus);

  exit(error_code);
}

/* *************** *
 *      MAIN()     *
 * *************** */

/**
 * Main function for cwb-huffcode.
 *
 * @param argc   Number of command-line arguments.
 * @param argv   Command-line arguments.
 */
int
main(int argc, char **argv)
{
  char *registry_directory = NULL;
  char *output_fn = NULL;
  char *attr_name = CWB_DEFAULT_ATT_NAME;
  Attribute *attr;

  HCD hc;

  extern int optind;
  extern char *optarg;
  int c;

  int i_want_to_believe = 0;        /* skip error checks? */
  int all_attributes = 0;

  /* initialise the protocol (ie informational) stream. There is no mechanism to use anything other than stdout. */
  protocol = stdout;

  cl_startup();
  progname = argv[0];


  /* ------------------------------------------------- PARSE ARGUMENTS */

  while ((c = getopt(argc, argv, "+TvP:r:f:dAh")) != EOF) {
    switch (c) {

      /* T: skip decompression / error checking pass ("I trust you")  */
    case 'T':
      i_want_to_believe++;
      break;

      /* v: verbose -> displays protocol of compression process & other info on stdout */
    case 'v':
      the_truth_is_out_there++;
      break;

      /* P: attribute to compress */
    case 'P':
      attr_name = optarg;
      break;

      /* r: registry directory */
    case 'r':
      if (registry_directory == NULL)
        registry_directory = optarg;
      else {
        fprintf(stderr, "%s: -r option used twice\n", progname);
        exit(2);
      }
      break;

      /* f: filename prefix for compressed data files */
    case 'f':
      output_fn = optarg;
      break;

      /* d: debug mode  --- unused */
    case 'd':
      debug++;
      break;

      /* A: compress all attributes */
    case 'A':
      all_attributes++;
      break;

      /* h: help page */
    case 'h':
      huffcode_usage(NULL, 2);
      break;

    default:
      huffcode_usage("illegal option.", 2);
      break;
    }
  }

  /* single argument: corpus id */
  if (optind < argc)
    corpus_id = argv[optind++];
  else
    huffcode_usage("corpus not specified (missing argument)", 1);

  if (optind < argc)
    huffcode_usage("Too many arguments", 1);

  if (!(corpus = cl_new_corpus(registry_directory, corpus_id))) {
    fprintf(stderr, "Corpus %s not found in registry %s . Aborted.\n",
            corpus_id,
            (registry_directory ? registry_directory: cl_standard_registry()));
    exit(1);
  }

  if (all_attributes) {
    for (attr = corpus->attributes; attr; attr = attr->any.next)
      if (attr->any.type == ATT_POS) {
        compute_code_lengths(attr, &hc, output_fn);
        if (!i_want_to_believe)
          decode_check_huff(attr, output_fn);
      }
  }
  else {
    if ((attr = cl_new_attribute(corpus, attr_name, ATT_POS)) == NULL) {
      fprintf(stderr, "Attribute %s.%s doesn't exist. Aborted.\n", corpus_id, attr_name);
      exit(1);
    }
    compute_code_lengths(attr, &hc, output_fn);
    if (!i_want_to_believe)
      decode_check_huff(attr, output_fn);
  }

  cl_delete_corpus(corpus);

  return 0;
}
