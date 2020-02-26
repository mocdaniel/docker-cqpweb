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


#include <stdio.h>
#include <stdlib.h>
#include <assert.h>
#include <string.h>

#include "feature_maps.h"
#include "barlib.h"

/** the top of the range of char_map's outputs @see char_map */
int char_map_range = 0;
/**
 * A character map for computing n-gram features
 *
 * After initialisation, this array maps character codes which are to be included
 * in n-grams to a position index without gaps, and all other codes to the index 1.
 *
 * Basically, when all is said and done, all possible bytes map to a number that
 * represents position in the (unaccented, caseless) Latin alphabet, where
 * where (a|A) => 2, and (any punctuation or non-letter) => 1.
 *
 * This includes, incidentally, UTF-8 component bytes in the upper half of the 8 bit space.
 * So all such component bytes count as "just punctuation" in the character n-gram comparisons.
 * As a consequence, n-gram features are next to useless with non-latin alphabets.
 */
unsigned char char_map[256];

/** initialises char_map, @see char_map for details */
void
init_char_map()
{
  int i;
  unsigned char *map = char_map;
  /* the outputs of the map are initialised to 0 */
  for (i = 0; i < 256; i++)
    map[i] = 0;

  /* lowercase letters map to themselves */
  for(i = 'a'; i <= 'z'; i++)
    map[i] = i;
  /* uppercase letters map to the corresponding lowercase */
  /* this isn't really needed any more since we apply %cd folding beforehand, but what the heck */
  for(i = 'A'; i <= 'Z'; i++)
    map[i] = i + 0x20;

  for(i = 1; i < 256; i++) {
    /* anything which HAS been assigned an output has (0x61 - 2) = 95 subtracted from it,
     * so the map output now = the alphabet offset where 'a' -> 2, 'b' -> 3, etc. */
    if(map[i] > 0)
      map[i] -= 0x5f;
    /* anything which HASN'T got an output yet (i.e. all non-letters) is mapped to 1 */
    else
      map[i] = 1;

    /* increase char_map_range from its start value of zero to the highest possible value.
     * Note this is deterministic. */
    if (map[i] >= char_map_range) {
      char_map_range = map[i] + 1;
    }
  }
}




/**
 *
 * Methods for the FMS class. Here is how it works:
 *
 * FMS = create_feature_maps(config, config_lines, source, target, source_s, target_s);
 *
 * Input:  feature map configuration (ASCII, parsed into separate items)
 *         word (or lemma) p-attributes of source and target corpus
 *         s-attributes for sentence boundaries in both corpora (source_s, target_s)
 *
 * Output: set of relevant features
 *         mapping from lexicon IDs to feature sets
 *         wrapped in FMS struct returned from the function
 *
 * In order to ensure a maximally compact encoding, feature sets are generated with
 * a two-pass algorithm:
 *
 *  1. identify relevant features + number of active features for each lexicon ID
 *  2. generate the actual feature sets
 */

/**
 * Creates feature maps for a source/target corpus pair.
 *
 * This is the constructor function for the FMS class.
 *
 * Example usage:
 *
 * FMS = create_feature_maps(config_data, nr_of_config_lines, source_word, target_word, source_s, target_s);
 *
 * @param config              array of strings representing the feature map configuration.
 * @param config_lines        the number of configuration items stored in config.
 * @param w_attr1             The p-attribute in the first corpus to link.
 * @param w_attr2             The p-attribute in the second corpus to link.
 * @param s_attr1             The s-attribute in the first corpus to link.
 * @param s_attr2             The s-attribute in the second corpus to link.
 * @return                    the new FMS object.
 */
FMS
create_feature_maps(char **config,
                    int config_lines,
                    Attribute *w_attr1,
                    Attribute *w_attr2,
                    Attribute *s_attr1,
                    Attribute *s_attr2
                    )
{
  FMS r;

  unsigned int *fcount1, *fcount2;    /* arrays for types in the lexicons of the source
                                       * & target corpora, respectively, counting how often each is used
                                       * in a feature */

  int config_pointer;

  char *b, command[CL_MAX_LINE_LENGTH], dummy[CL_MAX_LINE_LENGTH];
  char word1[2 * CL_MAX_LINE_LENGTH], word2[2 * CL_MAX_LINE_LENGTH];/* buffers for case/accent-folded strings (might be longer than input with UTF-8 */

  int current_feature;
  int weight;                         /* holds the weight assigned to the feature(s) we're working on */
  int need_to_abort;                  /* boolean used during pointer check */

  /* after we have counted up features, these will become arrays of ints, with one entry per feature */
  int *fs1, *fs2;

  int i;
  int nw1;  /* number of types on the word-attribute of the source corpus */
  int nw2;  /* number of types on the word-attribute of the target corpus */

  /* one last variable: we need to know the character set of the two corpora for assorted purposes */
  CorpusCharset charset;
  charset = cl_corpus_charset(cl_attribute_mother_corpus(w_attr1));

  /* first, create the FMS object. */
  r = (FMS) malloc(sizeof(feature_maps_t));
  assert(r);

  /* copy in the attribute pointers */
  r->att1 = w_attr1;
  r->att2 = w_attr2;
  r->s1 = s_attr1;
  r->s2 = s_attr2;

  init_char_map();

  /* find out how many different word-types occur on each of the p-attributes */
  nw1 = cl_max_id(w_attr1);
  if (nw1 <= 0) {
    fprintf(stderr, "ERROR: can't access lexicon of source corpus\n");
    exit(1);
  }
  nw2 = cl_max_id(w_attr2);
  if (nw2 <= 0) {
    fprintf(stderr, "ERROR: can't access lexicon of target corpus\n");
    exit(1);
  }

  printf("LEXICON SIZE: %d / %d\n", nw1, nw2);

  fcount1 = (unsigned int*) calloc(nw1 + 1, sizeof(unsigned int));
  fcount2 = (unsigned int*) calloc(nw2 + 1, sizeof(unsigned int));

  /* initialise feature counts: character count ("primary feature") is always present, but weight 0 if not specified */
  r->n_features = 1;
  for (i = 0; i < nw1; i++)
    fcount1[i]++;
  for (i = 0; i < nw2; i++)
    fcount2[i]++;


  /* NOTE there are two passes through the creation of feature maps - two sets of nearly identical code!
   * First pass to see how many things we need to count, second pass to count them. */

  /* process feature map configuration: first pass */
  for (config_pointer = 0; config_pointer < config_lines; config_pointer++) {

    /* strip newline and comments */
    if ( (b = strpbrk(config[config_pointer],"\n#")) )
      *b = 0;

    if (sscanf(config[config_pointer], "%s", command) > 0) {
      if(command[0] == '-') {
        /*
         * These are the FIRST PASS options for the different config lines.
         *
         * Possible config commands: -S -W -C -1 -2 -3 -4
         */
        switch(command[1]) {
        /* -S : the "shared words" type of feature */
        case 'S': {
          int i1, i2; /* i1 and i2 are temporary indexes into the lexicons of the two corpora */
          int f1, f2; /* f1 and f2 are temporary storage for frequencies from the corpus lexicons */
          float threshold;
          int n_shared = 0; /* number of shared words - only calculated for the purpose of printing it */

          if(sscanf(config[config_pointer],"%2s:%d:%f %s",command,&weight,&threshold,dummy) != 3) {
            fprintf(stderr,"ERROR: wrong # of args: %s\n",config[config_pointer]);
            fprintf(stderr,"Usage: -S:<weight>:<threshold>\n");
            fprintf(stderr,"  Shared words with freq. ratios f1/(f1+f2) and f2/(f1+f2) >= <threshold>.\n");
            exit(1);
          }
          else {
            printf("FEATURE: Shared words, threshold=%4.1f%c, weight=%d ... ",threshold * 100, '\%', weight);
            fflush(stdout);

            /* for each type in target corpus, get its frequency, and the corresponding id and frequency
             * from the target corpus, then test whether it meets the criteria for use as a feature. */
            for (i1 = 0; i1 < nw1; i1++) {
              f1 = cl_id2freq(w_attr1, i1);
              i2 = cl_str2id(w_attr2, cl_id2str(w_attr1, i1));
              if (i2 >= 0){
                f2 = cl_id2freq(w_attr2, i2);
                /* if it will be used as a feature, increment counts of features in various places */
                if ( (f1 / (0.0+f1+f2)) >= threshold && (f2 / (0.0+f1+f2)) >= threshold){
                  fcount1[i1]++;
                  fcount2[i2]++;
                  n_shared++;
                  r->n_features++;
                }
              }
            }
            printf("[%d]\n", n_shared);
          }
          break;
        }
        /* -1 to -4 : shared character sequences (of 1 letter to 4 letters in length) as features */
        case '1':
        case '2':
        case '3':
        case '4': {
          int n; /* length of the n-gram, obviously */

          if (sscanf(config[config_pointer], "%1s%d:%d %s", command, &n, &weight, dummy) !=3 ) {
            fprintf(stderr,"ERROR: wrong # of args: %s\n",config[config_pointer]);
            fprintf(stderr,"Usage: -<n>:<weight>  (n = 1..4)\n");
            fprintf(stderr,"  Shared <n>-grams (single characters, bigrams, trigrams, 4-grams).\n");
            exit(1);
          }
          else if(n <= 0 || n > 4) {
            /* this shouldn't happen anyway */
            fprintf(stderr,"ERROR: cannot handle %d-grams: %s\n", n, config[config_pointer]);
            exit(1);
          }
          else {
            int i,f,l; /* temp storage for lexicon index, n of possible features and word length */

            printf("FEATURE: %d-grams, weight=%d ... ", n, weight);
            fflush(stdout);

            /* for each entry in source-corpus lexicon, add all possible n-grams contained in this word
             * to its feature count; note that we have to apply case/accent-folding first to obtain accurate counts */
            for(i = 0; i < nw1; i++) {
              cl_strcpy(word1, cl_id2str(w_attr1, i));
              cl_string_canonical(word1, charset, IGNORE_CASE | IGNORE_DIAC, sizeof(word1));
              l = strlen(word1);
              fcount1[i] += (l >= n) ? l - n + 1 : 0;
            }
            /* same for target corpus */
            for(i = 0; i < nw2; i++) {
              cl_strcpy(word2, cl_id2str(w_attr2, i));
              cl_string_canonical(word2, charset, IGNORE_CASE | IGNORE_DIAC, sizeof(word2));
              l = strlen(word2);
              fcount2[i] += (l >= n) ? l - n + 1 : 0;
            }
            /* set f to number of possible features (= number of possible characters to the power of n) */
            f = 1;
            for(i = 0 ; i < n; i++)
              f *= char_map_range;
            /* and add that to our total number of features! */
            r->n_features += f;
            printf("[%d]\n", f);
          }
          break;
        }
        /* -W: the word-translation-equivalence type of feature */
        case 'W': {
          char filename[CL_MAX_LINE_LENGTH],
            word1[CL_MAX_LINE_LENGTH],
            word2[CL_MAX_LINE_LENGTH];
          FILE *wordlist;
          int nw;      /* number of words scanned from an input line */
          int nl = 0;  /* counter for the number of lines in the wordlist file we have gone through */
          int i1,i2;   /* lexicon ids in source and target corpora */
          int n_matched = 0;  /* counter for n of lines in input file that can be used as a feature. */

          if(sscanf(config[config_pointer],"%2s:%d:%s %s",command,&weight,filename,dummy)!=3) {
            fprintf(stderr, "ERROR: wrong # of args: %s\n",config[config_pointer]);
            fprintf(stderr, "Usage: -W:<weight>:<filename>\n");
            fprintf(stderr, "  Word list (read from file <filename>).\n");
            exit(1);
          }
          else if(!(wordlist = fopen(filename,"r"))) {
            fprintf(stderr,"ERROR: Cannot read word list file %s.\n", filename);
            exit(-1);
          }
          else {
            printf("FEATURE: word list %s, weight=%d ... ", filename, weight);
            fflush(stdout);
            /* TODO: (in v 3.9). The bilingual lexicon file should use a tab as the divider,
             * so that words with a space within them - allowed in a p-attribute - can be specified here. */
            while(0 < (nw = fscanf(wordlist,"%s %s",word1,word2))) {
              /* on first line of file, skip UTF8 byte-order-mark if present */
              if (nl == 0 && charset == utf8 && strlen(word1) > 3)
                if (word1[0] == (char)0xEF && word1[1] == (char)0xBB && word1[2] == (char)0xBF)
                   cl_strcpy(word1, (word1 + 3));
              nl++;
              /* check that both word 1 and word 2 are valid for the encoding of the corpora */
              if (! (cl_string_validate_encoding(word1, charset, 0)
                  && cl_string_validate_encoding(word2, charset, 0)) ) {
                fprintf(stderr, "ERROR: character encoding error in the word-list input file with the input word list.\n");
                fprintf(stderr, "       (The error occurs on line %d.)\n", nl);
                exit(1);
              }
              if (nw != 2)
                fprintf(stderr,"WARNING: Line %d in word list '%s' contains %d words, ignored.\n",nl,filename,nw);
              else {
                /* if word1 and word2 both occur in their respective corpora, this is a feature. */
                if(   (i1 = cl_str2id(w_attr1, word1)) >= 0
                   && (i2 = cl_str2id(w_attr2, word2)) >= 0 ) {
                  fcount1[i1]++;
                  fcount2[i2]++;
                  n_matched++;
                  r->n_features++;
                }
              }
            }
            fclose(wordlist);
            printf("[%d]\n", n_matched);
          }
          break;
        }
        /* -C: the character count type of feature.
         * This feature exists for EVERY word type. */
        case 'C':
          if(sscanf(config[config_pointer],"%2s:%d %s",command,&weight,dummy)!=2) {
            fprintf(stderr, "ERROR: wrong # of args: %s\n",config[config_pointer]);
            fprintf(stderr, "Usage: -C:<weight>\n");
            fprintf(stderr, "  Character count [primary feature].\n");
            exit(1);
          }
          else {
            /* primary feature -> don't create additional features */
            /* first entry in a token's feature list is always the character count */
            printf("FEATURE: character count, weight=%d ... [1]\n", weight);
          }
          break;
        default:
          fprintf(stderr, "ERROR: unknown feature: %s\n", config[config_pointer]);
          exit(1);
          break;
        }
      }
      else {
        fprintf(stderr, "ERROR: feature parse error: %s\n", config[config_pointer]);
        exit(1);
      }
    }
  }

  printf("[%d features allocated]\n",r->n_features);


  /*
   * So, as a result of the above, we know how many features there are for which
   * feature maps need to be created. We are, therefore, ready to allocate memory,
   * then basically repeat all the above - but instead of COUNTING features, actually DO them.
   */


  /* turn the for-each-type feature count arrays into CUMULATIVE feature count arrays. */
  for(i=1; i<=nw1; i++)
    fcount1[i] += fcount1[i-1];
  for(i=1; i<=nw2; i++)
    fcount2[i] += fcount2[i-1];

  printf("[%d entries in source text feature map]\n", fcount1[nw1]);
  printf("[%d entries in target text feature map]\n", fcount2[nw2]);


  /* now we know how much memory we need, let's allocate it. */
  fs1 = (int *)malloc(sizeof(int) * fcount1[nw1]);
  assert(fs1);
  fs2 = (int *)malloc(sizeof(int) * fcount2[nw2]);
  assert(fs2);

  r->w2f1=(int **)malloc(sizeof(unsigned int *)*(nw1+1));
  assert(r->w2f1);
  r->w2f2=(int **)malloc(sizeof(unsigned int *)*(nw2+1));
  assert(r->w2f2);

  /* set up word-to-feature maps. In these maps, the integer index = the lexicon id of the word,
   * and the value mapped to = a pointer into the fs1 or fs2 array that goes to theplace
   * in that cell where the features "belonging" to that word-type begin. */
  for(i = 0; i <= nw1; i++)
    r->w2f1[i] = fs1 + fcount1[i];
  for(i = 0; i <= nw2; i++)
    r->w2f2[i] = fs2 + fcount2[i];

  r->fweight = (int*)calloc(r->n_features, sizeof(int));
  assert(r->fweight);

  r->vstack = NULL;


  /* process feature map configuration: second pass */
  current_feature = 1;
  for (config_pointer = 0; config_pointer < config_lines; config_pointer++) {

    if ( (b = strpbrk(config[config_pointer],"\n#")) )
      *b = 0;
    if(sscanf(config[config_pointer], "%s", command)>0) {
      if(command[0]=='-') {
        switch(command[1]) {
        /* -S : the "shared words" type of feature */
        case 'S': {
          int i1, i2, f1, f2;
          float threshold;

          if (sscanf(config[config_pointer],"%2s:%d:%f %s",command,&weight,&threshold,dummy) == 3) {
            printf("PASS 2: Processing shared words (th=%4.1f%c).\n", threshold * 100, '\%');
            /* for each word in the lexicon of the source corpus.... check it exists, get
             * corresponding word in target corpus. As before.
             * BUT this time, IF the criterion is met, we don't just count it, we assign
             * the "current_feature" number to the value pointed to in the word-to-feature maps.*/
            for(i1=0; i1<nw1;i1++) {
              f1 = cl_id2freq(w_attr1,i1);
              i2 = cl_str2id(w_attr2, cl_id2str(w_attr1, i1));
              if(i2 >= 0){
                f2 = cl_id2freq(w_attr2,i2);
                if(f1/(0.0+f1+f2)>=threshold && f2/(0.0+f1+f2)>=threshold){
                  *(--r->w2f1[i1]) = *(--r->w2f2[i2]) = current_feature;
                  r->fweight[current_feature] = weight;
                  current_feature++;
                }
              }
            }
          }
          break;
        }
        /* -1 to -4 : shared character sequences (of 1 letter to 4 letters in length) as features */
        case '1':
        case '2':
        case '3':
        case '4': {
          int n;

          if (
              (sscanf(config[config_pointer], "%1s%d:%d %s", command, &n, &weight, dummy) == 3)
              && ( n >= 1 && n <= 4 )
          ) {
            int i, f, ng, l;
            unsigned char *s;

            printf("PASS 2: Processing %d-grams.\n",n);

            f = 1;
            for(i = 0; i < n; i++)
              f *= char_map_range; /* so, as before, f = number of possible n-grams for this n */

            /* add a feature weight for each of the possible n-grams */
            for (i = current_feature; i < current_feature + f; i++)
              r->fweight[i] = weight;

            /* for each word in the SOURCE lexicon, acquire the possible n-gram features */
            for (i = 0; i < nw1; i++) {
              cl_strcpy(word1, cl_id2str(w_attr1, i));
              cl_string_canonical(word1, charset, IGNORE_CASE | IGNORE_DIAC, sizeof(word1));
              ng = 0;
              l = 0;
              s = (unsigned char *)word1;
              while (*s) {
                /* read and process 1 character */
                ng = ((ng * char_map_range) + char_map[*s]) % f;
                l++;
                s++;
                /* begin setting features as soon as we've accumulated the first N-gram */
                if (l >= n)
                  *(--r->w2f1[i]) = current_feature + ng;
              }
            }

            /* same again for words in the TARGET lexicon */
            for (i = 0; i < nw2; i++) {
              cl_strcpy(word2, cl_id2str(w_attr2, i));
              cl_string_canonical(word2, charset, IGNORE_CASE | IGNORE_DIAC, sizeof(word2));
              ng = 0;
              l = 0;
              s = (unsigned char *)word2;
              while (*s) {
                /* read and process 1 character */
                ng = ((ng * char_map_range) + char_map[*s]) % f;
                l++;
                s++;
                /* begin setting features as soon as we've accumulated the first N-gram */
                if (l >= n)
                  *(--r->w2f2[i]) = current_feature + ng;
              }
            }

            current_feature += f;
          }
          break;
        }
        /* -W: the word-translation-equivalence type of feature */
        case 'W': {
          char filename[CL_MAX_LINE_LENGTH],
            word1[CL_MAX_LINE_LENGTH],
            word2[CL_MAX_LINE_LENGTH];
          FILE *wordlist;
          int nw, nl = 0, i1 ,i2;

          /* note that we RESCAN the wordlist file, this time adding weights, pointers etc. */
          if (sscanf(config[config_pointer], "%2s:%d:%s %s", command, &weight, filename, dummy) == 3) {
            if (!(wordlist = fopen(filename,"r")))
              exit(-1);
            printf("PASS 2: Processing word list %s\n", filename);
            while((nw = fscanf(wordlist, "%s %s", word1, word2))>0) {
              /* skip utf-8 prefix if present */
              if (nl == 0 && charset == utf8 && strlen(word1) > 3)
                if (word1[0] == (char)0xEF && word1[1] == (char)0xBB && word1[2] == (char)0xBF)
                   cl_strcpy(word1, (word1 + 3));
              nl++;
              if (nw !=2 ) {
                /* skip */
              }
              else {
                if((i1 = cl_str2id(w_attr1,word1))>=0
                   && (i2 = cl_str2id(w_attr2,word2)) >=0) {
                  *(--r->w2f1[i1])=*(--r->w2f2[i2])=current_feature;
                  r->fweight[current_feature]=weight;
                  current_feature++;
                }
              }
            }
            fclose(wordlist);
          }
          break;
        }
        case 'C':
          if (sscanf(config[config_pointer],"%2s:%d %s",command,&weight,dummy) == 2) {
            printf("PASS 2: Setting character count weight.\n");
            if (r->fweight[0] != 0) {
              fprintf(stderr, "WARNING: Character count weight redefined (new value is %d)\n", weight);
            }
            /* primary feature */
            r->fweight[0] = weight;
          }
          break;
        default: ;
        }
      }
    }
  }

  printf("PASS 2: Creating character counts.\n");
  for(i=0; i<nw1; i++) {
    *(--r->w2f1[i]) = cl_id2strlen(w_attr1, i);
  }
  for(i=0; i<nw2; i++) {
    *(--r->w2f2[i]) = cl_id2strlen(w_attr2, i);
  }

  printf("[checking pointers]\n");

  need_to_abort = 0;
  for(i=1;i<nw1;i++) {
    if(r->w2f1[i+1]-r->w2f1[i]!=fcount1[i]-fcount1[i-1]) {
      fprintf(stderr,"ERROR: fcount1[%d]=%d r->w2f1[%d]-r->w2f1[%d]=%ld w=``%s''\n",
              i,fcount1[i]-fcount1[i-1], i+1, i,(long int)(r->w2f1[i+1]-r->w2f1[i]),
              cl_id2str(w_attr1,i));
      need_to_abort = 1;
    }
  }

  for(i=1;i<nw2;i++) {
    if(r->w2f2[i+1]-r->w2f2[i]!=fcount2[i]-fcount2[i-1]) {
      fprintf(stderr,"ERROR: fcount2[%d]=%d r->w2f2[%d]-r->w2f2[%d]=%ld w=``%s''\n",
              i,fcount2[i]-fcount2[i-1], i+1, i,(long int)(r->w2f2[i+1]-r->w2f2[i]),
              cl_id2str(w_attr2,i));
      need_to_abort = 1;
    }
  }

  if(need_to_abort)
    exit(-1);

  /* we no longer need the counts of features per types, since all that info has now been copied into
   * the FMS object, so we can free memory then return the object */
  cl_free(fcount1);
  cl_free(fcount2);

  return(r);
}


/**
 * Compute similarity measure for a pair of regions, source and target, specified by
 * the corpus positions of the first and last sentences in each region.
 *
 * (And by "sentences" we mean "instances of whatever it is this s-attribute represents".)
 *
 * This is, basically, the "apply me" method for the FMS object.
 *
 * Usage:
 *
 * Sim = feature_match(FMS, source_first, source_last, target_first, target_last);
 *
 * Note that the best_path() function simply passes through the FMS to this
 * function. That function makes the decisions about what is the best sequence of
 * alignments - given the results it has got back from this sentence.
 *
 * @param fms  The feature map (which contains the s-attributes in question)
 * @param f1   Index of first "sentence" (i.e. entry on the s-attribute) of the region to analyse in the source.
 * @param l1   Index of last "sentence" of the region to analyse in the source.
 * @param f2   Index of first "sentence" (i.e. entry on the s-attribute) of the region to analyse in the target.
 * @param l2   Index of last "sentence" of the region to analyse in the target.
 * @return     The similarity measurement for the pair of refgions.
 */
int
feature_match(FMS fms,
              int f1,
              int l1,
              int f2,
              int l2)
{

  int *fcount;
  int match, j, i, id, *f;
  int cc1 = 0, cc2 = 0;         /* character count */
  int from, to;                 /* sentence boundaries (as cpos) */


  /* get a feature vector from the vstack */
  fcount = get_fvector(fms);

  for (j = f1; j <= l1; j++) {  /* count features in source region */
    if (cl_struc2cpos(fms->s1, j, &from, &to)) {
      for (i = from; i <= to; i++) {    /* process sentence */
        id = cl_cpos2id(fms->att1, i);
        if (id >= 0) {
          f = fms->w2f1[id];
          cc1 += *(f++);                /* character count */
          for( ; f < fms->w2f1[id+1]; f++)
            fcount[*f]++;
        }
      }
    }
  }

  match = 0;                      /* sum up similarity measure */

  for (j = f2; j <= l2; j++) {  /* compare to features in target region */
    if (cl_struc2cpos(fms->s2, j, &from, &to)) {
      for(i=from; i<= to; i++) {        /* process sentence */
        id = cl_cpos2id(fms->att2, i);
        if (id >= 0) {
          f = fms->w2f2[id];
          cc2 += *(f++);                /* character count */
          for( ; f < fms->w2f2[id+1]; f++) {
            if(fcount[*f]>0) {
              fcount[*f]--;
              match += fms->fweight[*f];
            }
          }
        }
      }
    }
  }

  /* add character count value to match quality */
  match += fms->fweight[0] * ((cc1 <= cc2) ? cc1 : cc2);

  /* we have now checked every feature in the FMS ! */


  /* clear feature count vector (selectively) */

  for (j = f1; j <= l1; j++) {
    if (cl_struc2cpos(fms->s1, j, &from, &to))
      for(i = from; i <= to; i++) {
        id = cl_cpos2id(fms->att1,i);
        if (id >= 0) {
          for(f = fms->w2f1[id]+1; f < fms->w2f1[id+1]; f++)
            fcount[*f]=0;
        }
      }
  }

  /* put our feature vector back on the vstack */
  release_fvector(fcount, fms);

  return match;
}


/**
 * Feature count vector handling (used internally by feature_match).
 *
 * If the vstack of the FMS (head of linked list) does not yet contain anything,
 * then a new integer array is created and a pointer to it is returned.
 *
 * If the vstack is already set, then the fcount from the element at the top
 * of the linked list is returned, and its record vstack_t is deleted from the
 * linked list stack.
 *
 * IN OTHER WORDS, a vector of feature counts is provided EITHER  by using the top
 * one off the stack, OR by getting a new one.
 *
 * @param   fms   The FMS to get a feature vector for.
 * @return        Pointer to array of integers (feature counts) big enough to
 *                hold th
 */
int *
get_fvector(FMS fms){
  int *res;
  vstack_t *next;

  if(!fms->vstack) {
    return ((int*)calloc(fms->n_features, sizeof(int)));
  }
  else {
    res  = fms->vstack->fcount;
    next = fms->vstack->next;
    cl_free(fms->vstack);
    fms->vstack = next;
    return(res);
  }

};

/**
 * Inserts a new vstack_t at the start of the vstack member of the given FMS.
 *
 * {That's what it looks like it does, not sure how the function name fits with that... ???? - AH}
 */
void
release_fvector(int *fvector, FMS fms)
{
  vstack_t *new;

  new = (vstack_t*)malloc(sizeof(vstack_t));
  assert(new);
  new->fcount = fvector;
  new->next = fms->vstack;
  fms->vstack = new;
}


/**
 * Prints a message about the vector stack of the given FMS.
 *
 * If it finds a non-zero-count, it prints a message to STDERR.
 * If it doesn't, it prints a message to STDOUT with the count of feature vectors.
 *
 * @param fms  The FMS to check.
 */
void
check_fvectors(FMS fms)
{
  int i, n;
  vstack_t * agenda;

  n=0;
  agenda=fms->vstack;

  while(agenda) {
    n++;
    for(i=0; i<fms->n_features; i++)
      if(agenda->fcount[i]!=0) {
        fprintf(stderr,"WARNING: non-zero count detected\n");
        return;
      }

    agenda=agenda->next;
  }

  printf("[check_fvectors: All %d feature vectors empty]\n",n);
}


/**
 * Prints the features in an FMS, as applied to a specific lexicon entry, to STDOUT.
 *
 * Usage: show_features(FMS, 1/2, "word");
 *2 * CL_MAX_LINE_LENGTH
 * This will print all features listed in FMS for the token "word"; "word" is looked up in the
 * source corpus if the 2nd argument == 1, and in the target corpus otherwise.
 *
 * @param fms    The FMS to print from.
 * @param which  Which corpus to look up? (See description)
 * @param word   The word-type to look up.
 */
void
show_features(FMS fms, int which, char *word)
{
  int id, *f;
  Attribute *att;
  int **w2f;      /* the word-to-feature mapper that we're using here */

  att = (which==1) ? (fms->att1) : (fms->att2);
  w2f = (which==1) ? (fms->w2f1) : (fms->w2f2);

  id = cl_str2id(att, word);

  printf("FEATURES of '%s', id=%d :\n", word, id);
  printf("+ len=%2d  weight=%3d\n", *w2f[id], fms->fweight[0]);
  for(f = w2f[id] + 1; f < w2f[id+1]; f++)
    printf("+ %6d  weight=%3d\n", *f, fms->fweight[*f]);
}



/**
 * Finds the best alignment path for the given spans of s-attribute instances in the source and
 * target corpus.
 *
 * This function does a beamed dynamic programming search for the best path
 * aligning the sentence regions (f1,l1) in the source corpus and (f2,l2)
 * in the target corpus.
 *
 * Allowed alignments are 1:0 0:1 1:1 2:1 1:2.
 *
 * The results are returned in the vectors out1 and out2,
 * which each contain a number of valid entries (alignment points) equal to {steps}.
 *
 * Alignment points are given as sentence numbers and
 * correspond to the start points of the sentences. At the end-of-region alignment
 * point, sentence numbers will be l1 + 1 and l2 + 1, which must be considered by
 * the caller if l1 (or l2) is the last sentence in the corpus!
 *
 * The similarity measures of aligned regions are returned in the vector out_quality.
 *
 * Memory allocated for the return vectors (out1, out2, out_quality) is managed by best_path() and
 * must not be freed by the caller. Calling best_path()  overwrites
 * the results of the previous search.
 *
 * Example usage:
 *
 * best_path(FMS, f1, l1, f2, l2, beam_width, 0/1, &steps, &out1, &out2, &out_quality);
 *
 * @param fms          The FMS to use as comparison criteria.
 * @param f1           Index of first sentence in source region.
 * @param l1           Index of last sentence in source region
 * @param f2           Index of first sentence in target region.
 * @param l2           Index of last sentence in target region.
 * @param beam_width   Parameter for the beam search.
 * @param verbose      Boolean: iff true, prints progress messages on STDOUT.
 * @param steps        Put output here (see function description).
 * @param out1         Put output here (see function description).
 * @param out2         Put output here (see function description).
 * @param out_quality  Put output here (see function description).
 */
void
best_path(FMS fms,
          int f1,
          int l1,
          int f2,
          int l2,
          int beam_width,       /* beam search */
          int verbose,          /* print progress info on stdout ? */
          /* output */
          int *steps,
          int **out1,
          int **out2,
          int **out_quality)
{

  BARdesc quality, next_x, next_y;  /* three arrays of ints, basically */

  static int max_out_pos = 0;
  static int *x_out = NULL;
  static int *y_out = NULL;
  static int *q_out = NULL;

  int ix, iy, iq, id, idmax, index, dx, dy, aux;
  int x_start, x_end, x_max, q_max;     /* beam search stuff */
  int half_beam_width = beam_width / 2;
  int x_ranges = l1 - f1 + 1, y_ranges = l2 - f2 + 1;

  /* allocate/enlarge output arrays if necessary.
   * If all alignments are 1:0 or 0:1 -> x_ranges+y_ranges + 1 pts */
  if (x_ranges + y_ranges + 1 > max_out_pos) {
    x_out = (int*)realloc(x_out, sizeof(int) * (x_ranges + y_ranges + 1));
    y_out = (int*)realloc(y_out, sizeof(int) * (x_ranges + y_ranges + 1));
    q_out = (int*)realloc(q_out, sizeof(int) * (x_ranges + y_ranges + 1));
    max_out_pos = x_ranges+y_ranges+1;
  }
  /* allocate data array for dynamic programming */
  quality = BAR_new(x_ranges+1, y_ranges+1, beam_width);
  next_x  = BAR_new(x_ranges+1, y_ranges+1, beam_width);
  next_y  = BAR_new(x_ranges+1, y_ranges+1, beam_width);

  /* init values at (0,0) position */
  BAR_write(quality, 0,0, 1);    /* this ensures we can't get lost, since any path connected to
                                  * the origin has at least a quality of 1 */
  BAR_write(next_x, 0,0, 0);
  BAR_write(next_y, 0,0, 0);
  x_max = 1;                     /* beam center init value */

  /* forward diagonal dynamic programming loop with beam search */
  idmax = x_ranges + y_ranges;
  for (id = 1; id <= idmax; id++) {

    x_start = x_max - half_beam_width;
    x_end = x_start + beam_width;
    x_max = x_start; q_max = 0; /* scan for best path on diagonal => new x_max value */

    for(ix = x_start; ix < x_end; ix++) {
      iy = id - ix;
      if ((iy < 0) || (iy > y_ranges) || (ix > x_ranges))
        continue;

      /* initialise to 1:0 or 0:1 alignment (whichever is better) */
      if (ix >= 1) {            /* 1:0 if possible */
        BAR_write(quality, ix,iy, BAR_read(quality, ix-1,iy));
        BAR_write(next_x, ix,iy, ix - 1);
        BAR_write(next_y, ix,iy, iy);
      }
      if(BAR_read(quality, ix,iy-1) > BAR_read(quality, ix,iy)) {
        /* 0:1 alignment, if that is an improvement */
        BAR_write(quality, ix,iy, BAR_read(quality, ix,iy-1));
        BAR_write(next_x, ix,iy, ix);
        BAR_write(next_y, ix,iy, iy-1);
      }

      /* scan through all possible alignment steps */
      for(dx = 1; dx <= 2; dx++) {
        for(dy = 1; dy <= 2; dy++) {
          /*      if ((dx == 2) && (dy == 2)) continue; */ /* 2:2 now allowed again */
          if ((ix - dx >= 0) && (iy - dy >= 0)) {
            aux = BAR_read(quality, ix-dx,iy-dy)
              + feature_match(fms,
                              f1 + ix - dx, f1 + ix - 1,
                              f2 + iy - dy, f2 + iy - 1);
            if (aux > BAR_read(quality, ix,iy)) {
              BAR_write(quality, ix,iy, aux);
              BAR_write(next_x, ix, iy, ix-dx);
              BAR_write(next_y, ix, iy, iy-dy);
            }
          }
        }
      }

      /* find best path on current diagonal */
      if (BAR_read(quality, ix,iy) > q_max) {
        x_max = ix;
        q_max = BAR_read(quality, ix, iy);
      }
    } /* end of x coordinate loop (diagonal parametrisation) */
    /* new x_max is predicted to be the same as x_max determined for current diagonal */
    if (verbose) {
      printf("BEST_PATH: scanning diagonal #%d of %d [max sim = %d]        \r",
             id, idmax, q_max);
      fflush(stdout);
    }
  } /* end of diagonal loop */
  /* end of DP loop */
  if (verbose)
    printf("\n");

  /* read best path from DP array (backward) */
  ix = x_ranges;
  iy = y_ranges;
  iq = BAR_read(quality, ix, iy);

  *steps = 0;
  index = max_out_pos - 1;
  while ((ix >= 0) && (iy >= 0)) { /* the while() condition is just a safety check */
    x_out[index] = ix + f1;
    y_out[index] = iy + f2;
    aux = BAR_read(quality, ix, iy);
    q_out[index] = iq - aux;
    iq = aux;
    (*steps)++;
    if ((ix <= 0) && (iy <= 0))
      break; /* exit point */
    aux = ix;                   /* next step */
    ix = BAR_read(next_x, aux, iy);
    iy = BAR_read(next_y, aux, iy);
    index--;
  }

  *out1 = x_out + index;
  *out2 = y_out + index;
  *out_quality = q_out + index;

  /* deallocate dynamic programming data */
  BAR_delete(quality);
  BAR_delete(next_x);
  BAR_delete(next_y);
}

