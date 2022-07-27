package CWB::CL;
# -*-cperl-*-

use strict;
use warnings;

use Carp qw<croak confess>;

use base qw(DynaLoader);
our $VERSION = 'v3.4.33';

## load object library
bootstrap CWB::CL $VERSION;


=head1 NAME

CWB::CL - Perl interface to the low-level C API of the IMS Open Corpus Workbench

=head1 SYNOPSIS

  use CWB::CL;

  print "Registry path = ", $CWB::CL::Registry, "\n";
  $CWB::CL::Registry .= ":/home/my_registry";    # add your own registry directory

  # "strict" mode aborts if any error occurs (convenient in one-off scripts)
  CWB::CL::strict(1);                            # or simply load CWB::CL::Strict module
  CWB::CL::set_debug_level('some');              # 'some', 'all' or 'none' (default)
  CWB::CL::set_optimizer(1);                     # enable experimental optimizations in CL (if any)

  CWB::CL::error_message();                      # error message for last method call (or "")

  # CWB::CL::Corpus objects
  $corpus = new CWB::CL::Corpus "EUROPARL-EN";   # name of corpus can be upper or lower case
  die "Error: can't access corpus EUROPARL-EN"   # all error conditions return undef
    unless defined $corpus;                      #   (checks are not needed in "strict" mode)
  undef $corpus;                                 # currently a no-op (CL implementation is buggy)

  $charset = $corpus->charset;                   # declared character encoding of the corpus
  $folded = $corpus->normalize("cd", $string);   # CWB-compatible case- and diacritic-folding
                                                 # (use "n" for UTF-8 strings from external sources)

  # CWB::CL::PosAttrib objects (positional attributes)
  $lemma = $corpus->attribute("lemma", 'p');     # returns CWB::CL::PosAttrib object
  $corpus_length = $lemma->max_cpos;             # valid cpos values are 0 .. $corpus_length-1
  $lexicon_size = $lemma->max_id;                # valid id values are 0 .. $lexicon_size-1

  $id  = $lemma->str2id($string);                # lookup lexicon ID of type $string
  @idlist = $lemma->str2id(@strlist);            # (all scalar functions map to lists in list context)
  $str = $lemma->id2str($id);                    # type with lexicon ID $id
  $len = $lemma->id2strlen($id);                 # string length of type with ID $id
  $f   = $lemma->id2freq($id);                   # corpus frequency of type with ID $id
  $id  = $lemma->cpos2id($cpos);                 # lexicon ID of value at corpus position $cpos
  $str = $lemma->cpos2str($cpos);                # type annotated at corpus position $cpos

  @idlist = $lemma->regex2id($re);               # find all lexicon IDs matching regular expression
  @idlist = $lemma->regex2id($re, 'cd');         #   with optional flags 'n', 'c', 'd'
  @cpos = $lemma->idlist2cpos(@idlist);          # occurrences of all types in @idlist
  $total_freq = $lemma->idlist2freq(@idlist);    # total corpus frequency of @idlist (w/o decoding index)


  # CWB::CL::StrucAttrib objects (structural attributes)
  $chapter = $corpus->attribute("chapter", 's'); # returns CWB::CL::StrucAttrib object
  $number_of_regions = $chapter->max_struc;      # valid region numbers are 0 .. $number_of_regions-1
  $has_values = $chapter->struc_values;          # are regions annotated with strings?

  $struc = $chapter->cpos2struc($cpos);          # number of <chapter> region containing $cpos (or undef)
  ($start, $end) = $chapter->struc2cpos($struc); # start and end of region number $struc
  @pairs = $chapter->struc2cpos(@struc_list);    # returns flat list ($s1, $e1, $s2, $e2, ...)
  $str  = $chapter->struc2str($struc);           # annotation string for region number $struc (or undef)
  $str  = $chapter->cpos2str($cpos);             # annotation string for region around $cpos (or undef)

  ($s, $e) = $chapter->cpos2struc2cpos($cpos);   # start/end of <chapter> region around $cpos
  @pairs = $chapter->cpos2struc2cpos(@cpos_list);# returns 2 * N values for N arguments (cf. above)

  # check whether corpus position is at boundary (l, r, lr) or inside/outside (i/o) of region
  if ($chapter->cpos2boundary($cpos) & $CWB::CL::Boundary{'l'}) { ... }
  if ($chapter->cpos2is_boundary('l', $cpos)) { ... }


  # CWB::CL::AlignAttrib objects (alignment attributes)
  $ger = $corpus->attribute("europarl-de", 'a'); # returns CWB::CL::AlignAttrib object
  $nr_of_beads = $ger->max_alg;                  # alignment bead numbers are 0 .. $nr_of_beads-1
  if ($ger->has_extended_alignment) { ... }      # extended alignment allows gaps & crossing alignments
  
  $bead = $ger->cpos2alg($cpos);                 # alignment bead containing $cpos (or undef)
  ($src_start, $src_end, $tgt_start, $tgt_end)   # aligned spans in source and target corpus
      = $ger->alg2cpos($bead);
  @quads = $ger->alg2cpos(@bead_list);           # flat list of quadruplets (one for each alignment bead)
  @quads = $ger->cpos2alg2cpos(@cpos_list);      # find alignments (source/target spans) for corpus position(s)


  # Feature sets (can be used as values of positional and structural attributes)
  $np_f = $corpus->attribute("np_feat", 's');    # p- and s-attributes can store feature sets
  $fs_string = $np_f->cpos2str($cpos);           # feature sets are encoded as strings
  $fs  = CWB::CL::set2hash($fs_string);          # expand feature set into hash (returns hashref)
  if (exists $fs->{"paren"}) { ... }
  $fs1 = CWB::CL::make_set("|proper|nogen|");    # validate feature set (reorders values)
  $fs2 = CWB::CL::make_set("paren nogen proper", 'split'); # or construct from blank-delimited string
  $fs3 = CWB::CL::make_set($fs);                           # or from hash reference
  $fs  = CWB::CL::set_intersection($fs1, $fs2);  # intersection of feature set values
  $n   = CWB::CL::set_size($fs);                 # size of feature set


=head1 DESCRIPTION

This module provides an interface to the low-level B<Corpus Library> for accessing CWB-indexed corpora. It follows the Corpus Library API closely, except for an object-oriented design with simplified method names and the addition of a few convenience methods.

All scalar access methods - usually named C<xxx2yyy> - are vectorized: they automatically map to multiple input arguments and return a flat list of results.  Vectorization is implemented in C code, ensuring high performance. 

All errors and out-of-bounds accesses are turned into undefined values (B<undef>) unless B<strict mode> is enabled (e.g. with C<use CWB::CL::strict;>).  If an item is not found - e.g. a given type string is not in the lexicon of a p-attribute, or a given corpus position is not within an s-attribute region - the method will also return B<undef>.  Vectorized method calls may return a mixture of defined and undefined values.


=head1 CWB3 DATA MODEL

CWB is based on a B<tabular data> model, which represents a corpus as a sequence of tokens annotated with one or more string values in the form of an annotation table.

      word    pos     lemma
      ----    ---     -----
  0   Dogs    NNS     dog
  1   like    VBP     like
  2   cats    NNS     cat
  3   .       SENT    .
  4   Cats    NNS     cat
  5   do      VBP     do
  6   n't     RB      not
  7   like    VB      like
  8   dogs    NNS     dog
  9   .       SENT    .

Tokens are identified by their row number starting from 0, which is known as B<corpus position> (or B<cpos>) for short.  The first column of the table, always labelled I<word>, contains the surface forms of the tokens.  Further columns, which can be labelled with arbitrary ASCII identifiers, contain token-level annotations (in this case part-of-speech tags (I<pos>) and lemmatization (I<lemma>)).  Each table column forms a separate B<positional attribute> (or B<p-attribute> for short) in the CWB data model.  The token sequence itself is thus a regular p-attribute with the special name I<word>.

For the sake of efficiency and data compression, p-attributes use a numeric indexing scheme based on a B<lexicon> of all distinct annotation strings (B<types>), which are assigned numeric B<ID>s starting from 0.  Each p-attribute has its own lexicon, e.g. for I<pos> with the types 0 = NNS, 1 = VBP, 2 = SENT, 3 = RB and 4 = VB.

B<CWB::CL> provides methods for mapping between corpus positions, lexicon IDs, type strings and type frequencies.

XML tags in CWB input files are stored as B<structural attributes> (or B<s-attributes> for short).  Each s-attribute indexes a sequence of non-overlapping, non-nested regions corresponding to XML elements of the same name.  Consider this example:

      <text title="All about dogs">
      <s n="1" words="3">
  0   Dogs    NNS     dog
  1   like    VBP     like
  2   cats    NNS     cat
  3   .       SENT    .
      </s>
      <s n="2" words="4">
  4   Cats    NNS     cat
  5   do      VBP     do
  6   n't     RB      not
  7   like    VB      like
  8   dogs    NNS     dog
  9   .       SENT    .
      </s>
      </text>

Note that there are no separate corpus positions assigned to XML tags, which are positioned at boundaries between tokens.  The single C<< <text> >> region is stored in an s-attribute named I<text>; the two C<< <s> >> regions are stored in an s-attributed named I<s>.  Attribute-value pairs in XML start tags are converted to additional s-attributes I<text_title>, I<s_n> and I<s_words>.

Each s-attribute region is represented by its start and end corpus position, e.g. S<(0, 3)> for the first sentence and S<(4, 9)> for the second sentence above.  The regions are numbered starting from 0; such region numbers are referred to as C<struc> in method names.

If an s-attribute represents annotation in XML start tags, its regions are annotated with string values (e.g. "3" and "4" for the two regions of s-attribute I<s_words>).  These strings are not indexed with the help of a lexicon, so access is much less efficient than for p-attributes.

B<CWB::CL> provides methods to access the span and annotation of an s-attribute region, to find the region number containing a given cpos and to test for the start or end of a region.

Sentence-level alignment between two different corpora is represented by B<alignment attributes> (or B<a-attributes> for short).  The name of an alignment attribute corresponds to the CWB ID of the target corpus in lowercase; as a consequence, there can only be a single alignment for each pair of source and target corpus.  An a-attribute indexes a sequence of B<alignment beads> that connect a token span C<(src_start, src_end)> in the source corpus with a span C<(tgt_start, tgt_end)> in the target corpus.  These spans need not correspond to sentence regions.

Alignment beads are numbered starting from 0, in the order of their positions in the source corpus.  Both the source spans and the target spans must be non-overlapping and must not be nested.  Most alignment attributes will use this new-style "extended" format. Only some legacy corpora may contain old-style a-attributes, which do not allow for crossing alignments or gaps between beads.

B<CWB::CL> provides methods to access the source and target spans of an alignment bead, and to find the bead number containing a given corpus position in the source corpus.

=cut


#
#  ------------  initialisation code  ------------
#

## subroutine used to extract constant definitions from <cwb/cl.h> and put them into hash
sub get_constant_values {
  my @hash = ();                # build list that can be used to initialise hash
  my $symbol;

  foreach $symbol (@_) {
    my $val = constant($symbol);
    if ($! != 0) {              # indicates lookup failure
      croak "ERROR Constant '$symbol' not in <cwb/cl.h>";
    }
    push @hash, $symbol => $val;
  }
  return @hash;
}

## CL constants are packed into package hashes
# attribute types
our %AttType = get_constant_values(
                               qw(ATT_ALIGN ATT_ALL ATT_DYN ATT_NONE ATT_POS ATT_REAL ATT_STRUC)
                               );
# argument types
our %ArgType = get_constant_values(
                               qw(ATTAT_FLOAT ATTAT_INT ATTAT_NONE ATTAT_PAREF ATTAT_POS ATTAT_STRING ATTAT_VAR)
                              );

# error codes
our %ErrorCode = get_constant_values(
                                 qw(CDA_OK CDA_EALIGN CDA_EARGS CDA_EATTTYPE CDA_EBADREGEX CDA_EBUFFER),
                                 qw(CDA_EFSETINV CDA_EIDORNG CDA_EIDXORNG CDA_EINTERNAL),
                                 qw(CDA_ENODATA CDA_ENOMEM CDA_ENOSTRING CDA_ENULLATT CDA_ENYI CDA_EOTHER),
                                 qw(CDA_EPATTERN CDA_EPOSORNG CDA_EREMOTE CDA_ESTRUC),
                                );

# error symbols (indexed by <negative> error code) 
our @ErrorSymbol = sort {(-$ErrorCode{$a}) <=> (-$ErrorCode{$b})} keys %ErrorCode;

# regex flags (for cl_regex2id())
our %RegexFlags = (
               '' => 0,
               'c' => constant('IGNORE_CASE'),   # ignore case
               'd' => constant('IGNORE_DIAC'),   # ignore diacritics
               'cd' => constant('IGNORE_CASE') | constant('IGNORE_DIAC'),
               'n' => constant('REQUIRE_NFC'), # NFC normalization
               'nc' => constant('REQUIRE_NFC') | constant('IGNORE_CASE'),
               'nd' => constant('REQUIRE_NFC') | constant('IGNORE_DIAC'),
               'ncd' => constant('REQUIRE_NFC') | constant('IGNORE_CASE') | constant('IGNORE_DIAC'),
              );

# structure boundary flags
our %Boundary = (
    'inside' => constant('STRUC_INSIDE'),
    'left' => constant('STRUC_LBOUND'),
    'right' => constant('STRUC_RBOUND'),
    'outside' => 0,  # for completeness
    'i' => constant('STRUC_INSIDE'),
    'l' => constant('STRUC_LBOUND'),
    'r' => constant('STRUC_RBOUND'),
    'o' => 0,
    'lr' => constant('STRUC_LBOUND') | constant('STRUC_RBOUND'), # these are all reasonable flag combinations
    'rl' => constant('STRUC_LBOUND') | constant('STRUC_RBOUND'),
    'leftright' => constant('STRUC_LBOUND') | constant('STRUC_RBOUND'),
    'rightleft' => constant('STRUC_LBOUND') | constant('STRUC_RBOUND'),
  );


#
#  ------------  CWB::CL global variables  ------------
#

=head1 Global Configuration and Utilities

=over 4

=item $CWB::CL::Registry

Path to CWB registry directory, or multiple paths separated by colons (C<:>).  This variable can be modified to change the registry in which corpora will be searched.  It does not affect B<CWB::CL::Corpus> objects that have already been created.

=cut

# registry directory
our $Registry = cl_standard_registry();

#
#  ------------  CWB::CL package functions  ------------
#

=item $error = CWB::CL::error_message();

Human-readable error message for an error encountered during the last method call. If the call was successful, an empty string is returned.

=cut

# return error message for last error encountered during last method call (or "" if last call was successful)
# -- CWB::CL::error_message(); [exported by XS code]

# access error messages for CL (and internal) error codes
# -- CWB::CL::cwb_cl_error_message($code); [exported by XS code]

=item CWB::CL::strict(1);

Enable B<strict mode>, so that the Perl script will immediately be terminated if there is any error or invalid access (instead of returning B<undef> values).  Strict mode can also be enabled by importing the module as C<use CWB::CL::Strict;>).

Strict mode is a convenience feature for one-off scripts and command-line tools run by end users.  Production software should keep strict mode disabled and check all return values instead.

=cut

# set strictness (in strict mode, every CL or argument error aborts the script with croak())
sub strict ( ; $ ) {
  my $current_mode = get_strict_mode();
  if (@_) {
    my $on_off = shift;
    set_strict_mode($on_off ? 1 : 0);
  }
  return $current_mode;
}

=item CWB::CL::set_debug_level(I<$lvl>)

Set the amount of debugging information printed on C<stderr> by the Corpus Library.  Admissible values for I<$lvl> are 0 or C<none> (no output), 1 or C<some> (some messages), 2 or C<all> (all messages).

=cut

# set CL debugging level (0=no, 1=some, 2=all debugging messages)
sub set_debug_level ( $ ) {
  my $lvl = shift;
  $lvl = 0 if (lc $lvl) eq "none";
  $lvl = 1 if (lc $lvl) eq "some";
  $lvl = 2 if (lc $lvl) eq "all";
  croak "Usage:  CWB::CL::set_debug_level('none' | 'some' | 'all');"
    unless $lvl =~ /^[012]$/;
  CWB::CL::cl_set_debug_level($lvl);
}

=item CWB::CL::set_optimize(1);

Enable experimental optimizations in the Corpus Library.  In the CWB 3.4 beta series leading up to CWB 3.5, the only optimization provides a minor speed-up for certain simple regular expressions.

Stable releases do not contain any experimental optimizations, so this option has no effect.

=cut

# enable or disable experimental optimizations
sub set_optimize ( $ ) {
  my $on_off = (shift) ? 1 : 0;
  CWB::CL::cl_set_optimize($on_off);
}

# set CL memory limit (used only by makeall so far, so no point in setting it here)
sub set_memory_limit ( $ ) {
  my $mb = shift;
  croak "Usage:  CWB::CL::set_memory_limit(\$megabytes);"
    unless $mb =~ /^[0-9]+$/;
  croak "CWB::CL: invalid memory limit ${mb} MB (must be >= 42 MB)"
    unless $mb >= 42;
  CWB::CL::cl_set_memory_limit($mb);
}

=back

=head2 Feature Sets

Feature set annotation uses a special string notation for sets of feature values.  The individual values in the set are sorted in CWB order, separated by pipe characters (C<|>) and enclosed in pipe characters.  For example, the set S<{I<small>, I<medium>, I<big>}> is represented by the string

  |big|medium|small|

and the empty set by

  |

Keep in mind that there must not be any duplicate values in a set.  Features sets can be used as annotation values for p-attributes and s-attributes.  The Corpus Query Processor (CQP) provides special operators C<contains> and C<matches> for searching feature sets with regular expressions, as well as functions for computing set size (C<ambiguity()>) and set intersection (C<unify()>).

B<CWB::CL> offers some convenience functions for creating and manipulating feature sets.  These functions are implemented in C code for efficiency.

=over 4

=item I<$fs> = CWB::CL::make_set(I<$values> [, 's']);

Create a feature set from I<$values>, which is either a string in feature set notation or a hashref.  In the first case, correct notation is checked and the values are sorted if necessary (CWB v3.4.29 and newer are more lenient and will automatically add the surrounding delimiters).  In the second case, a feature set is constructed from the keys of the hash I<%$values>.

If a second argument C<s> (or C<split>) is passed, the string I<$value> is split on whitespace.

=cut

# convert '|'-delimited string into proper (sorted) feature set value
# (if 's' or 'split' is given, splits string on whitespace; returns undef if there is a syntax error)
sub make_set {
  my $set = shift;
  my $type = ref $set;
  if ($type eq "HASH") {
    ## feature set specified as hash ('split' argument is ignored)
    cl_make_set("|".join("", map {"$_|"} keys %$set));
  }
  elsif ($type eq "") {
    ## feature set specified as string
    cl_make_set($set, @_);
  }
  else {
    croak "CWB::CL::make_set: feature set must be string or hashref";
  }
}

=item I<$fs> = CWB::CL::set_intersection(I<$fs1>, I<$fs2>);

Compute the intersection of two feature sets I<$fs1> and I<$fs2>, i.e. a feature set containing all shared values.  This function only works correctly if both arguments are sorted and use valid feature set notation.  It correspond to the C<unify()> function in CQP.

=cut

# compute intersection of two feature sets (CQP's 'unify()' function)
# (returns undef if there is a syntax error)
*set_intersection = \&cl_set_intersection;

=item I<$n> = CWB::CL::set_size(I<$fs>);

Return the cardinality of a feature set I<$fs>, i.e. the number of elements.  This function only works correctly if I<$fs> uses valid feature set notation.  It corresponds to the C<ambiguity()> function in CQP.

=cut

# compute cardinality of feature set (= "size", i.e. number of elements)
# (returns undef if there is a syntax error)
*set_size = \&cl_set_size;

=item I<$values> = CWB::CL::set2hash(I<$fs>);

Expand feature set I<$fs> in CWB notation into a hash, with elements as keys and values set to 1.  Returns a hashref I<$values>.

=cut

# convert feature set value into hashref
sub set2hash ( $ ) {
  my $set = shift;
  my $is_ok = defined set_size($set); # easy & fast way of validating feature set format
  if ($is_ok) {
    my @items = split /\|/, $set; # returns empty field before leading |
    shift @items;
    return { map {$_ => 1} @items };
  }
  else {
    return undef;
  }
}

=back

=cut


#
#  ------------  CWB::CL::Corpus objects  ------------
#

=head1 Corpora (CWB::CL::Corpus)

Each CWB corpus is represented by a B<CWB::CL::Corpus> object.  The object constructor locates a suitable registry file and accesses the corpus.  Attribute handles are then obtained with the B<attribute> method.

=over 4

=cut

# $corpus = new CWB::CL::Corpus "name";
# $lemma = $corpus->attribute("lemma", 'p');     # returns CWB::CL::Attribute object (positional attribute)
# $article = $corpus->attribute("article", 's'); # returns CWB::CL::AttStruc object  (structural attribute)
# $french = $corpus->attribute("name-french", 'a');  # returns CWB::CL::AttAlign     (alignment attribute)
# undef $corpus;                                 # delete corpus from memory

package CWB::CL::Corpus;
use Carp;

=item I<$corpus> = new CWB::CL::Corpus $ID;

Access corpus with CWB ID I<$ID>, usually specified in uppercase letters.  The constructor looks for a registry file in the path(s) specified by I<$CWB::CL::Registry>.  Returns a corpus handle, i.e. an object of class B<CWB::CL::Corpus>, or B<undef> if the corpus cannot be found (unless strict mode is enabled).

=cut

sub new {
  my $class = shift;
  my $corpusname = shift;
  my $self = {};
  croak('Usage: $C = new CWB::CL::Corpus $name;')
    unless $corpusname;

  # try to open corpus (corpus name needs to be all lowercase)
  $corpusname = uc($corpusname); # 'official' notation is all uppercase ...
  my $ptr = CWB::CL::cl_new_corpus(
      (defined $CWB::CL::Registry) ? $CWB::CL::Registry : CWB::CL::cl_standard_registry(),
      lc($corpusname)  # ... but CL API requires corpus name in lowercase
    );
  unless (defined $ptr) {
    croak("Can't access corpus $corpusname (aborted)")
      if CWB::CL::strict(); # CL library doesn't set error code in cl_new_corpus() function
    return undef;
  }
  $self->{'ptr'} = $ptr;
  $self->{'name'} = $corpusname;
  return bless($self, $class);
}

sub DESTROY {
  my $self = shift;

  # disabled because of buggy nature of CL interface
  #   CWB::CL::cl_delete_corpus($self->{'ptr'});
}

=item I<$att> = I<$corpus>->attribute(I<$name>, I<$type>);

Obtain attribute handle for the attribute with name I<$name> and type I<$type> (C<p> = positional, C<s> = structural, C<a> = alignment).  Note that legacy corpora may contain attributes of different types with the same name, even though this has been deprecated.  Returns B<undef> if the attribute does not exist (unless strict mode is enabled).

Classes for handles of different attribute types and their access methods are described below.

=cut

sub attribute {
  my $self = shift;
  my $name = shift;
  my $type = shift;

  if ($type eq 'p') {
    return CWB::CL::PosAttrib->new($self, $name);
  }
  elsif ($type eq 's') {
    return CWB::CL::StrucAttrib->new($self, $name);
  }
  elsif ($type eq 'a') {
    return CWB::CL::AlignAttrib->new($self, $name);
  }
  else {
    croak "USAGE: \$corpus->attribute(\$name, 'p' | 's' | 'a');";
  }
}

=item I<@names> = I<$corpus>->list_attributes([I<$type>]);

Returns the names of all attributes defined for I<$corpus>.  Attribute names will be listed in the same order as in the registry file.

If I<$type> is specified, only list attributes of the selected type (C<p>, C<s> or C<a>).

=cut

sub list_attributes {
  my $self = shift;
  my $type = (@_) ? shift : "";
  croak "USAGE: \$corpus->list_attributes(['p' | 's' | 'a']);"
    unless $type =~ /^[psa]?$/;
  if ($type eq "p")    { $type = $AttType{ATT_POS}; }
  elsif ($type eq "s") { $type = $AttType{ATT_STRUC}; }
  elsif ($type eq "a") { $type = $AttType{ATT_ALIGN}; }
  else                 { $type = $AttType{ATT_ALL}; }
  return CWB::CL::cl_list_attributes($self->{'ptr'}, $type);
}

=item I<$folded> = I<$corpus>->normalize(I<$flags>, I<$string>);

=item I<@folded> = I<$corpus>->normalize(I<$flags>, I<@strings>);

Normalize one or more strings according to I<$flags>, which is any combination of the flags below in the specified order.

  n   normalize UTF-8 strings to CWB canonical form (NFC)
  c   fold strings to lowercase
  d   remove all diacritics (combining marks)

Admissible values for I<$type> are thus C<c>, C<d>, C<cd>, C<n>, C<nc>, C<nd> and C<ncd>.  Note that B<normalize> is a method because it depends on the character encoding of the corpus.

=cut

sub normalize ( $$;@ ) {
  my $self = shift;
  my $flags = shift;

  croak "Usage:  \$corpus->normalize(('[n][c][d]'), \$string, ...);"
    unless $flags =~ /^n?c?d?$/;

  return CWB::CL::cl_normalize($self->{'ptr'}, $CWB::CL::RegexFlags{$flags}, @_);
}

=item I<$charset> = I<$corpus>->charset;

Character encoding of I<$corpus> (using CWB notation, same as in registry files). Typical values are C<utf8>, C<latin1> and C<ascii>.

=cut

sub charset ( $ ) {
  my $self = shift;
  return CWB::CL::cl_corpus_charset_name($self->{'ptr'});
}

=back

=cut


#
#  ------------  CWB::CL::PosAttrib objects  ------------
#

=head1 Positional Attributes (CWB::CL::PosAttrib)

Handles for p-attributes are represented by objects of class B<CWB::CL::PosAttrib>.  They should never be constructed directly, but rather obtained from the B<attribute> method of a corpus handle.

=over 4

=cut

package CWB::CL::PosAttrib;
use Carp;

sub new {
  my $class = shift;
  my $corpus = shift;           # corpus object  (provided by CWB::CL::Corpus->attribute)
  my $name = shift;             # attribute name (provided by CWB::CL::Corpus->attribute)
  my $self = {};

  my $corpusPtr = $corpus->{'ptr'};
  my $ptr = CWB::CL::cl_new_attribute($corpusPtr, $name, $CWB::CL::AttType{'ATT_POS'});
  unless (defined $ptr) {
    my $corpusName = $corpus->{'name'};
    local($Carp::CarpLevel) = 1; # call has been delegated from attribute() method of CWB::CL::Corpus object
    croak("Can't access p-attribute $corpusName.$name (aborted)")
      if CWB::CL::strict(); # CL library doesn't set error code in cl_new_attribute() function
    return undef;
  }
  return bless($ptr, $class);   # objects are just opaque containers for (Attribute *) pointers
}

sub DESTROY {
  my $self = shift;

  # disabled because of buggy nature of CL interface!
  #  CWB::CL::cl_delete_attribute($self);
}

=item I<$N> = I<$att>->max_cpos;

Returns the number of tokens in the corpus (which is technically a property of each p-attribute).  Note that the name of the function is misleading: valid corpus positions range from 0 to I<$N>-1.

=cut

sub max_cpos ( $ ) {
  my $self = shift;

  my $size = CWB::CL::cl_max_cpos($self);
  if ($size < 0) {
    croak CWB::CL::error_message()." (aborted)"
      if CWB::CL::strict();
    return undef;
  }
  return $size;
}

=item I<$V> = I<$att>->max_id;

Returns the number of distinct types in the lexicon of the p-attribute.  Note that the name of the function is misleading: valid type IDs range from 0 to I<$V>-1.

=cut

sub max_id ( $ ) {
  my $self = shift;

  my $size = CWB::CL::cl_max_id($self);
  if ($size < 0) {
    croak CWB::CL::error_message()." (aborted)"
      if CWB::CL::strict();
    return undef;
  }
  return $size;
}

=item I<$type> = I<$att>->id2str(I<$id>);

=item I<@types> = I<$att>->id2str(I<@ids>);

Find type (string) corresponding to numerical lexicon I<$id>.  Returns B<undef> for lexicon IDs that are out of range and all other errors.

=cut

*id2str = \&CWB::CL::cl_id2str;

=item I<$len> = I<$att>->id2strlen(I<$id>);

=item I<@lens> = I<$att>->id2strlen(I<@ids>);

Returns length of type string corresponding to numerical lexicon I<$id>, measured in bytes.  This method is provided for consistency with the Corpus Library API, where it determines string length efficiently without having to scan the string.  Its Perl complement has no speed benefit and the B<id2str> method should be preferred.

=cut

*id2strlen = \&CWB::CL::cl_id2strlen;

=item I<$f> = I<$att>->id2freq(I<$id>);

=item I<@fs> = I<$att>->id2freq(I<@ids>);

Returns corpus frequency of the type with numerical lexicon ID I<$id> (B<undef> for lexicon IDs that are out of range and all other errors).

=cut

*id2freq = \&CWB::CL::cl_id2freq;

=item I<$id> = I<$att>->str2id(I<$type>);

=item I<@ids> = I<$att>->str2id(I<@types>);

Search I<$type> (string) in lexicon and return its ID if successful.  Returns B<undef> for all types not found in the lexicon and for all errors.  An out-of-vocabulary I<$type> is not an error and will return B<undef> even in strict mode.

=cut

*str2id = \&CWB::CL::cl_str2id;

=item I<$id> = I<$att>->cpos2id(I<$cpos>);

=item I<@ids> = I<$att>->cpos2id(I<@cpos>);

Returns the lexicon ID of the annotation at corpus position I<$cpos> (B<undef> if I<$cpos> is out of range and all other errors).

=cut

*cpos2id = \&CWB::CL::cl_cpos2id;

=item I<$type> = I<$att>->cpos2str(I<$cpos>);

=item I<@types> = I<$att>->cpos2str(I<@cpos>);

Returns the type string annotated at corpus position I<$cpos> (B<undef> if I<$cpos> is out of range and all other errors).

This method is equivalent to

  @types = $att->id2str($att->cpos2id(@cpos));

but faster and it does not have to allocate memory for the intermediate result.  It is very convenient for displaying parts of the corpus text.

=cut

*cpos2str = \&CWB::CL::cl_cpos2str;

=item I<@ids> = I<$att>->regex2id(I<$rx>[, I<$flags>]);

Scan lexicon of I<$att> with regular expression I<$rx> and return the lexicon IDs of all matching types. I<$rx> always has to match the full type string; start and end anchors are not required.  The Corpus Library uses L<PCRE regular expressions|http://www.pcre.org/current/doc/html/>, so the two lines below are mostly equivalent:

  @types = $att->id2str($att->regex2id($rx));

  @types = grep { /^($rx)$/ } $att->id2str(0 .. ($att->max_id - 1));

However, there will be differences in some corner cases, e.g. case-insensitive matching for non-ASCII characters.

The optional argument I<$flags> consists of any combination of the flags below in the specified order.

  n   normalize $rx to CWB canonical form (NFC)
  c   case-insensitive
  d   ignore diacritics (combining marks)

Admissible values for I<$type> are thus C<c>, C<d>, C<cd>, C<n>, C<nc>, C<nd> and C<ncd>.  The C<n> flag is highly-recommended for regular expressions provided by users.

B<regex2id> returns an empty list if I<$rx> does not match any types or if there are any errors, in particular in case of an invalid regular expression.  Unless strict modes is enabled, Perl scripts need to check B<CWB::CL::error_message()> in order to catch syntax errors in I<$rx>.

=cut

sub regex2id ( $$;$ ) {
  my $self = shift;
  my $regex = shift;
  my $flags = (@_) ? shift : '';

  croak "Usage:  \$att->regex2id(\$regex [, '[n][c][d]' ]);"
    unless defined $regex and $flags =~ /^n?c?d?$/;

  return CWB::CL::cl_regex2id($self, $regex, $CWB::CL::RegexFlags{$flags});
}

=item I<$f> = I<$att>->idlist2freq(I<@ids>);

Returns the total corpus frequency of all type IDs in the list I<@ids> (B<undef> if any of the lexicon IDs is out of range or another error occurs).  Equivalent to

  use List::Util qw(sum);
  $f = sum($att->id2freq(@ids));

but much faster because the summation is carried out in C code.

=cut

*idlist2freq = \&CWB::CL::cl_idlist2freq;

=item I<@cpos> = I<$att>->idlist2cpos(@ids);

Look up all corpus positions annotated with one of the type IDs in I<@ids>, merged into a single numerically sorted list.  Returns an empty list if there is any error.

There is no separate method for the occurrences of a single type I<$id>, but B<idlist2cpos> recognises this special case and uses more efficient code (because the occurrences can be looked up directly in the inverted index).  The undocumented method B<id2cpos> is simply an alias for B<idlist2cpos>.

=cut

*idlist2cpos = \&CWB::CL::cl_idlist2cpos;

*id2cpos = \&CWB::CL::cl_idlist2cpos;  # simpler alias (may becomd standard name in future CL releases)

=back

=cut


#
#  ------------  CWB::CL::StrucAttrib objects  ------------
#

=head1 Structural Attributes (CWB::CL::StrucAttrib)

Handles for s-attributes are represented by objects of class B<CWB::CL::StrucAttrib>.  They should never be constructed directly, but rather obtained from the B<attribute> method of a corpus handle.

=over 4

=cut

package CWB::CL::StrucAttrib;
use Carp;

sub new {
  my $class = shift;
  my $corpus = shift;           # corpus object  (provided by CWB::CL::Corpus->attribute)
  my $name = shift;             # attribute name (provided by CWB::CL::Corpus->attribute)

  my $corpusPtr = $corpus->{'ptr'};
  my $ptr = CWB::CL::cl_new_attribute($corpusPtr, $name, $CWB::CL::AttType{'ATT_STRUC'});
  unless (defined $ptr) {
    my $corpusName = $corpus->{'name'};
    local($Carp::CarpLevel) = 1; # call has been delegated from attribute() method of CWB::CL::Corpus object
    croak("Can't access s-attribute $corpusName.$name (aborted)")
      if CWB::CL::strict(); # CL library doesn't set error code in cl_new_attribute() function
    return undef;
  }
  return bless($ptr, $class);
}

sub DESTROY {
  my $self = shift;

  # disabled because of buggy nature of CL interface!
  #  CWB::CL::cl_delete_attribute($self);
}

=item I<$n> = I<$att>->max_struc;

Returns the total number of regions for the s-attribute.  Note that the name of the function is misleading: valid region numbers range from 0 to I<$n>-1.

=cut

sub max_struc ( $ ) {
  my $self = shift;

  my $size = CWB::CL::cl_max_struc($self);
  if ($size < 0) {
    croak CWB::CL::error_message()." (aborted)"
      if CWB::CL::strict();
    return undef;
  }
  return $size;
}

=item I<$has_values> = I<$att>->struc_values;

Returns TRUE if regions of this s-attribute are annotated with string values.

=cut

sub struc_values ( $ ) {
  my $self = shift;

  my $yesno = CWB::CL::cl_struc_values($self);
  if ($yesno < 0) {
    # so far, CL library generates no errors in this function (just FALSE)
    croak CWB::CL::error_message()." (aborted)"
      if CWB::CL::strict();
    return undef;
  }
  return $yesno;
}

=item I<$struc> = I<$att>->cpos2struc(I<$cpos>);

=item I<@strucs> = I<$att>->cpos2struc(I<@cpos>);

Returns the number of the region containing corpus position I<$cpos>, or B<undef> if I<$cpos> is not inside a region of this s-attribute (and in case of any errors, including out-of-bounds I<$cpos>).

It is not an error for I<$cpos> to be outside a region, so B<undef> will be returned even in strict mode.

=cut

*cpos2struc = \&CWB::CL::cl_cpos2struc;

=item I<$value> = I<$att>->struc2str(I<$struc>);

=item I<@values> = I<$att>->struc2str(I<@strucs>);

Obtain the string value that region number I<$struc> is annotated with. Returns B<undef> in case of any error, in particular if C<< $att->struc_values >> is FALSE.

Note that there is no method to search regions for a particular annotation string or regular expression.  Scripts will have to loop over all regions in the s-attribute and carry out such tests in Perl code.

=cut

*struc2str = \&CWB::CL::cl_struc2str;

=item I<$value> = I<$att>->cpos2str(I<$cpos>);

=item I<@values> = I<$att>->cpos2str(I<@cpos>);

Obtain the string value annotation of the region containing corpus position I<$cpos>. Returns B<undef> if I<$cpos> is not inside any region of the s-attribute (and in case of any errors, in particular if C<< $att->struc_values >> is FALSE).  It is not an error for I<$cpos> to be outside a region, so B<undef> will be returned even in strict mode.

This method is fully equivalent to

  @values = $att->struc2str($att->cpos2struc(@cpos));

but is faster and more convenient if the region numbers are not needed otherwise.  An alias B<cpos2struc2str> is provided for consistency with the Corpus Library API, but B<cpos2str> is the preferred form.

=cut

*cpos2struc2str = \&CWB::CL::cl_cpos2struc2str;
*cpos2str = \&cpos2struc2str; # alias in anticipation of the new object-oriented CL interface specification

=item (I<$start>, I<$end>) = I<$att>->struc2cpos($struc);

=item I<@pairs> = I<$att>->struc2cpos(@strucs);

Returns start and end corpus position of region number I<$struc>, or C<(undef, undef)> if there is any error.

If multiple region numbers are supplied, a flast list of start/end pairs is returned (possibly containing pairs of B<undef>s).  For example, the call C<< @pairs = $att->struc2cpos($n1, $n2, $n3); >> returns

  @pairs = ($s1, $e1, $s2, $e2, $s3, $e3);

=cut

*struc2cpos = \&CWB::CL::cl_struc2cpos;

=item (I<$start>, I<$end>) = I<$att>->cpos2struc2cpos($cpos);

=item I<@pairs> = I<$att>->cpos2struc2cpos(@cpos);

Returns start and end corpus position of the region containing corpus position I<$cpos>, or C<(undef, undef)> if I<$cpos> is not within a region of the s-attribute (and for any error).  For multiple I<@cpos>, the method returns a flat list of start/end pairs like B<struc2cpos>.

It is not an error for I<$cpos> to be outside a region, so C<(undef, undef)> pairs will be returned even in strict mode.

=cut

*cpos2struc2cpos = \&CWB::CL::cl_cpos2struc2cpos;

=item if (I<$att>->cpos2is_boundary(I<$which>, I<$cpos>)) { ... }

=item I<@yesno> = I<$att>->cpos2is_boundary(I<$which>, I<@cpos>);

Test whether corpus position I<$cpos> is at the boundary of, inside or outside a region of s-attribute I<$att>. Returns TRUE if the test succeeds, FALSE otherwise, and B<undef> in case of an error.

The parameter I<$which> determines which test is carried out.  The following short and long codes are supported:

  i   inside      cpos is anywhere inside a region
  o   outside     cpos is not inside a region
  l   left        cpos is the first token in a region
  r   right       cpos is the last token in a region
  lr  leftright   cpos is a single-token region (first AND last)
  rl  rightleft   (same)

There is no single test for whether I<$cpos> is either the start or the end of a region.  For this and other complex tests, the method B<cpos2boundary> can be used.

=cut

sub cpos2is_boundary ( $$;@ ){
  my $self = shift;
  my $test = lc(shift);
  my $test_flags = $CWB::CL::Boundary{$test};
  
  croak "Usage:  \$att->cpos2is_boundary({'i'|'o'|'l'|'r'|'lr'}, \$cpos, ...);"
    unless defined $test_flags;
  return CWB::CL::cl_cpos2is_boundary($self, $test_flags, @_);
}

=item I<$flags> = I<$att>->cpos2boundary(I<$cpos>);

=item I<@flags> = I<$att>->cpos2boundary(I<@cpos>);

Returns an integer I<$flags> where several flag bits can be set indicating whether I<$cpos> is at the left/right boundary of, and/or inside a region.  Currently three bits are in use

  $CWB::CL::Boundary{"inside"}  set if $cpos is inside region
  $CWB::CL::Boundary{"left"}    set if $cpos is the first token of a region
  $CWB::CL::Boundary{"right"}   set if $cpos is the last token of a region

Use logical bit operators to test for individual flags or combinations of these flags.  For example, at the start of a region both C<inside> and C<left> bits will be set.  A I<$cpos> outside a region returns C<$flags = 0>.  And an "inner" token inside a region (which is neither the first nor last token) has only the C<inside> bit set.
(Note: The C<leftright> test in B<cpos2is_boundary> checks whether all three bits are set.)

=cut

*cpos2boundary = \&CWB::CL::cl_cpos2boundary;

=back

=cut


#
#  ------------  CWB::CL::AlignAttrib objects  ------------
#

=head1 Alignment Attributes (CWB::CL::AlignAttrib)

Handles for a-attributes are represented by objects of class B<CWB::CL::AlignAttrib>.  They should never be constructed directly, but rather obtained from the B<attribute> method of a corpus handle.

=over 4

=cut

package CWB::CL::AlignAttrib;
use Carp;

sub new {
  my $class = shift;
  my $corpus = shift;           # corpus object  (provided by CWB::CL::Corpus->attribute)
  my $name = shift;             # attribute name (provided by CWB::CL::Corpus->attribute)
  my $self = {};

  my $corpusPtr = $corpus->{'ptr'};
  my $ptr = CWB::CL::cl_new_attribute($corpusPtr, $name, $CWB::CL::AttType{'ATT_ALIGN'});
  unless (defined $ptr) {
    my $corpusName = $corpus->{'name'};
    local($Carp::CarpLevel) = 1; # call has been delegated from attribute() method of CWB::CL::Corpus object
    croak("Can't access a-attribute $corpusName.$name (aborted)")
      if CWB::CL::strict(); # CL library doesn't set error code in cl_new_attribute() function
    return undef;
  }
  return bless($ptr, $class);
}

sub DESTROY {
  my $self = shift;

  # disabled because of buggy nature of CL interface
  #  CWB::CL::cl_delete_attribute($self->{'ptr'});
}

=item I<$n> = I<$att>->max_alg;

Returns the total number of alignment beads for the a-attribute.  Note that the name of the function is misleading: valid bead numbers range from 0 to I<$n>-1.

=cut

sub max_alg ( $ ) {
  my $self = shift;
  my $size = CWB::CL::cl_max_alg($self);
  if ($size < 0) {
    croak CWB::CL::error_message()." (aborted)"
      if CWB::CL::strict();
    return undef;
  }
  return $size;
}

=item I<$ok> = I<$att>->has_extended_alignment;

Returns TRUE if the a-attribute uses "extended" format.  There is no difference in access patterns, but the script has to expect crossing alignments and gaps between beads if I<$ok> is TRUE.  Most aligned corpora will be in extended format. 

=cut

sub has_extended_alignment ( $ ) {
  my $self = shift;
  my $yesno = CWB::CL::cl_has_extended_alignment($self);
  if ($yesno < 0) {
    croak CWB::CL::error_message()." (aborted)"
      if CWB::CL::strict();
    return undef;
  }
  return $yesno;
}

=item I<$bead> = I<$att>->cpos2alg(I<$cpos>);

=item I<@beads> = I<$att>->cpos2alg(I<@cpos>);

Returns the number of the alignment bead containing corpus position I<$cpos>, or B<undef> if I<$cpos> is not inside a bead of this a-attribute (and in case of any errors, including out-of-bounds I<$cpos>).

It is not an error for I<$cpos> to be outside a bead (provided that the a-attribute uses "extended" format), so B<undef> will be returned even in strict mode.

=cut

*cpos2alg = \&CWB::CL::cl_cpos2alg;

=item (I<$src_start>, I<$src_end>, I<$tgt_start>, I<$tgt_end>) = I<$att>->alg2cpos($bead);

=item I<@quads> = I<$att>->alg2cpos(@beads);

Returns the aligned spans in source and target corpus for alignment bead number I<$bead>, or C<(undef, undef, undef, undef)> if there is any error.

If multiple bead numbers are supplied, a flast list of quadruplets is returned (possibly containing quadruplets of B<undef>s).  For example, the call C<< @quads = $att->alg2cpos($A, $B); >> returns

  @quads = ($A_s1, $A_s2, $A_t1, $A_t2, $B_s1, $B_s2, $B_t1, $B_t2);

=cut

*alg2cpos = \&CWB::CL::cl_alg2cpos;

=item (I<$src_start>, I<$src_end>, I<$tgt_start>, I<$tgt_end>) = I<$att>->cpos2alg2cpos($cpos);

=item I<@quads> = I<$att>->cpos2alg2cpos(@cpos);

Returns the aligned source and target spans for the alignment bead containing corpus position I<$cpos>, or C<(undef, undef, undef, undef)> if I<$cpos> is not inside a bead of this a-attribute (and in case of any errors, including out-of-bounds I<$cpos>).

If multiple corpus positions are supplied, a flast list of quadruplets is returned in the same way as for B<alg2cpos>.

=cut

*cpos2alg2cpos = \&CWB::CL::cl_cpos2alg2cpos; # convenience function combines cpos2alg() and alg2cpos()

=back

=cut


package CWB::CL;                        # back to main package for autosplitter's sake
1;


=head1 EXAMPLE

The minimalistic example script below requires the C<DICKENS> demo corpus to be installed in the standard registry path.  It compiles a lemma frequency list for all C<< <title> >> regions in the corpus and prints the first 20 entries.  Note how it uses B<CWB::CL::Strict> to avoid checking return values for error conditions.

  use CWB::CL::Strict;
   
  my $C = new CWB::CL::Corpus "DICKENS";
  my $Lemma = $C->attribute("lemma", "p");
  my $Title = $C->attribute("title", "s");
   
  my $n_titles = $Title->max_struc;
  my %F = ();
   
  foreach my $i (0 .. ($n_titles - 1)) {
    my ($start, $end) = $Title->struc2cpos($i);
    foreach my $lemma ($Lemma->cpos2str($start .. $end)) {
      $F{$lemma}++;
    }
  }
   
  my @lemmas = sort {$F{$b} <=> $F{$a}} keys %F;
  foreach my $lemma (@lemmas[0 .. 19]) {
    printf "%8d %s\n", $F{$lemma}, $lemma;
  }


=head1 COPYRIGHT

Copyright (C) 1999-2022 by Stephanie Evert [https://purl.org/stephanie.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut

