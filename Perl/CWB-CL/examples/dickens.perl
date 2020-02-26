#!/usr/bin/perl
## -*-cperl-*-
## This is an old test script for the Perl API to the CL library, which operates on the demo corpus DICKENS.
## It illustrates general usage of the CWB::CL module and is a good starting point for programmers.

use warnings;

BEGIN { $| = 1; print "Running tests for CWB::CL:\n"; }

# step 1: load the CL library
END {print "!! can't load CWB::CL\n" unless $loaded;}
use CWB::CL;
$loaded = 1;
print " - use CWB::CL ok\n";

# step 2: where is our registry?
if ($CWB::CL::Registry) {
  print " - registry: $CWB::CL::Registry\n";
}
else {
  print "!! no registry set\n";
  exit 1;
}

# step 3: open corpus
$C = new CWB::CL::Corpus "DICKENS";
if (defined $C) {
  print " - corpus DICKENS opened\n";
}
else {
  print "!! can't open corpus DICKENS\n";
  exit 1;
}

# step 4: open word attribute (should always be defined!)
$Word = $C->attribute("word", 'p');		# 'p' = p-attribute
if (not defined $Word) {
  print "!! can't open p-attribute DICKENS.word\n";
  exit 1;
}
print " - default attribute DICKENS.word opened\n";

# step 5: corpus and lexicon size
$corpus_size = $Word->max_cpos;
if (not (defined $corpus_size and $corpus_size > 0)) {
  print "!! error in max_cpos() method\n";
  exit 1;
}
print " - corpus size: $corpus_size tokens\n";

$lex_size = $Word->max_id;
if (not (defined $lex_size and $lex_size > 0)) {
  print "!! error in max_id() method\n";
  exit 1;
}
print " - word form lexicon: $lex_size entries\n";

# step 6: look up words (or other token-level annotations) and their frequencies
$id = $Word->str2id("internet");
if (defined $id) {				# 'internet' should not occur in corpus
  print "!! error in str2id() method\n";
  exit 1;
}
print " - word form 'internet' not found in corpus\n";

$id = $Word->str2id("interest");
if (not (defined $id and $id >= 0)) {		# 'interest' should occur, and have freq > 0
  print "!! error in str2id() method\n";
  exit 1;
}
$f = $Word->id2freq($id);
if (not (defined $f and $f > 0)) {
  print "!! error in id2freq() method\n";
  exit 1;
}
print " - word form 'interest' (id=$id) occurs $f times\n";

# step 7: match regular expressions against words (or other token-level annotations)
$regex = ".wi(s|l)[a-z]";
@id = $Word->regex2id($regex, 'cd');		# same as ``".wi(s|l)[a-z]" %cd;'' in CQP
if ((@id < 3) or (@id > 9)) {
  print "!! error in regex2id() method\n";
  exit 1;
}
@words = $Word->id2str(@id);
if ((@id != @words) or (grep {not $_} @words)) {
  print "!! error in id2str() method\n";
  exit 1;
}
if (grep {not /^$regex$/i} @words) {		# Perl cannot do the %d flag, but fortunately there are no diacritics in DICKENS
  print "!! error in regex2id() method\n";
  exit 1;
}
$N = @id;
print " - regexp /$regex/\%cd matches $N word types:\n";
print "   ";
for ($i = 0; $i < $N; $i++) {
  print $words[$i]," (",$id[$i],")  ";
}
print "\n";

# step 8: look up words in index
$f_lex = 0;
foreach $id (@id) {				# count occurrences by adding lexicon frequencies
  $f_lex += $Word->id2freq($id);
}
$f_freqs = $Word->idlist2freq(@id);		# let the CL add up the frequencies
if ($f_lex != $f_freqs) {
  print "!! error in idlist2freq() method\n";
  exit 1;
}
@cpos = $Word->idlist2cpos(@id);		# this method _always_ takes a list of IDs, and returns a list of corpus positions, of course
$f_index = @cpos;
if ($f_lex != $f_index) {
  print "!! error in idlist2cpos() method\n";
  exit 1;
}
print " - total no. of occurrences = $f_index\n";
foreach $cpos (@cpos) {				# check index against token stream
  $w = $Word->cpos2str($cpos);
  if (not ($w =~ /^$regex$/i)) {
    print "!! error in regex2id() method (inconsistency with cpos2str())\n";
    exit 1;
  }
}
@first10 = @cpos[0..9];	 # for easy printing (there should be much more than 10 occurrences)
print "   ", join(", ", @first10), ", ...\n";

# step 9: read token stream (word forms)
@first10 = $Word->cpos2str(0 .. 9);
if ((@first10 != 10) or (grep {not $_} @first10)) {
  print "!! error in cpos2str() method\n";
  exit 1;
}

# step 10: open s-attributes (with and without annotations)
$S = $C->attribute("s", 's');			#  's'= s-attribute
$NPh = $C->attribute("np_h", 's');
if ((not defined $S) or (not defined $NPh)) {
  print "!! can't open s-attributes DICKENS.s and DICKENS.np_h\n";
  exit 1;
}
if ($S->struc_values or not $NPh->struc_values) {
  print "!! error in struc_values() method\n";
  exit 1;
}
print " - opened s-attributes DICKENS.s and DICKENS.np_h [A]\n";

# step 11: number of regions
$N_S = $S->max_struc;
$N_NPh = $NPh->max_struc;
if (not (defined $N_S and defined $N_NPh and $N_S > 100_000 and $N_NPh > 300_000 and $N_NPh < 1_000_000)) {
  print "!! error in max_struc() method\n";
}
print " - $N_S <s> regions and $N_NPh <np_h> regions found\n";

# step 12: finding region containing a given token
$id = $Word->str2id("life");
if (not (defined $id and $id >= 0)) {		# if $id is undefined, it evaluates to 0 in numeric context
  print "!! error in str2id() method\n";
  exit 1;
}
$f = $Word->id2freq($id);
@cpos = $Word->idlist2cpos($id);		# it's called idlist2cpos even if there's only a single ID
if ($f != @cpos) {
  print "!! error in idlist2cpos() method\n";
  exit 1;
}
print " - $f occurrences of 'life' found\n";
$count = 0;					# count 'short' sentences (<= 10 tokens for Dickens :o) containing "life"
$headcount = 0;					# count NPs containing "life" as head
foreach $cpos (@cpos) {
  $num = $S->cpos2struc($cpos);
  if (not (defined $num and $num >= 0)) {
    print "!! error in cpos2struc() method\n";
  }
  ($start, $end) = $S->struc2cpos($num);
  if (not ($start <= $cpos and $cpos <= $end)) {
    print "!! error in struc2cpos() method\n";
    exit 1;
  }
  if (($end - $start) < 10) {
    $count++;
  }
  $num = $NPh->cpos2struc($cpos);
  if (defined $num) {
    $head = $NPh->struc2str($num);
    if (not $head) {
      print "!! error in struc2str() method\n";
      exit 1;
    }
    if ($head eq "life") {
      $headcount++;
    }
  }
}
if ($count <= 0 or $count >= $f) {		# some, but not all occurrences of "life" should be in short sentences
  print "!! error counting short <s> regions\n";
  exit 1;
}
if ($headcount <= 0 or $headcount >= $f) {	# same for NPs where "life" is the head
  print "!! error counting <np_h> regions\n";
}
print "   ... $count in short sentences (at most 10 tokens)\n";
print "   ... $headcount as head of their <np>\n";

# step 13: testing feature sets 
$fs1a = CWB::CL::make_set("aaa Ba 0 BAa a", 'split');
$fs1b = CWB::CL::make_set("|0|a|BAa|Ba|aaa|");
$fs1  = "|0|BAa|Ba|a|aaa|";			# this is the CL feature set format, with lexically ordered vectors
$fs2a = CWB::CL::make_set("aaa ההה ßa 0", 'split');
$fs2  = "|ßa|ההה|0|aaa|";			# CL ordering uses signed char comparison
if ($fs1a ne $fs1 or $fs1b ne $fs1) {
  print "!! error in CL::make_set() function\n";
  exit 1;
}
if ($fs2a ne $fs2) {
  print "!! wrong feature set ordering in CL::make_set()\n";
  exit 1;
}
$fs = CWB::CL::set_intersection($fs1, $fs2);
if ($fs ne "|0|aaa|") {
  print "!! error in CL::set_intersection() function\n";
  exit 1;
}
print " - CL::set_intersection($fs1, $fs2) = $fs\n";
$size = CWB::CL::set_size($fs);
if ($size != 2) {
  print "!! error in CL::set_size() function\n";
  exit 1;
}
print " - CL::set_size($fs) = $size\n";


# that's it -- we've passed the test
print "Congratulations. All tests passed.\n";
exit 0;
