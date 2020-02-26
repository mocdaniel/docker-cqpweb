use Test::More tests => 35;
## test basic CWB::CL functions using the small included VSS corpus

use CWB::CL;
use strict;
use warnings;

ok($CWB::CL::Registry, "check registry directory"); # T1
diag("");
diag("- registry directory: $CWB::CL::Registry");

$CWB::CL::Registry = "data/registry"; # use local copy of VSS corpus

our $C = new CWB::CL::Corpus "VSS"; # -- access corpus and word attribute
isa_ok($C, "CWB::CL::Corpus", "corpus object for VSS corpus") # T2
  or BAIL_OUT("failed to access test corpus VSS");

our $Word = $C->attribute("word", "p");
isa_ok($Word, "CWB::CL::PosAttrib", "attribute object for VSS.word") # T3
  or BAIL_OUT("failed to access word attribute of corpus VSS");

our $S = $C->attribute("s", "s");
isa_ok($S, "CWB::CL::StrucAttrib", "attribute object for VSS.s") # T4
  or BAIL_OUT("failed to access <s> attribute of corpus VSS");

our $StoryTitle = $C->attribute("story_title", "s");
isa_ok($S, "CWB::CL::StrucAttrib", "attribute object for VSS.story_title") # T5
  or BAIL_OUT("failed to access <story title=''> annotations");
ok($StoryTitle->struc_values, "attribute <story title=''> contains annotations"); 

our $Chapter = $C->attribute("chapter", "s") # no need for another test
  or BAIL_OUT("failed to access <chapter> attribute of corpus VSS");

our $Align = $C->attribute("vss", "a") # self-alignment for testing purposes
  or BAIL_OUT("failed to access self-alignment (VSS.vss attribute)");

our $corpus_size = $Word->max_cpos; # -- basic corpus statistics
is($corpus_size, 8043, "corpus size (tokens)"); # T7
our $lex_size = $Word->max_id;
is($lex_size, 2111, "lexicon size (types)");
diag("- VSS corpus has $corpus_size word form tokens and $lex_size types");

our $id = $Word->str2id("elephant"); # -- look up known lexicon entry
is($id, 1977, "look up 'elephant' in word form lexicon"); # T9
ok((not defined $Word->str2id("corpus")), "unknown word type returns undef");
is($Word->id2str($id), "elephant", "look up word form 'elephant' from ID");

our $f = $Word->id2freq($id); # -- frequency information for word type
is($f, 14, "frequency of 'elephant'"); # T12
diag("- 'elephant' occurs $f times in VSS corpus");

our $regex = "[a-z]+(ally|ily)"; # -- search lexicon with regular expression
our $perl_regex = qr/^(?:${regex})$/i; # compile Perl regular expression for validation 
our @id = $Word->regex2id($regex, "c"); # same as `` "[a-z]+(ally|ily)" %c; '' in CQP
our $n_types = @id;
is($n_types, 24, "match regular expressions against lexicon"); # T13
our @words = $Word->id2str(@id);
ok(@words == @id, "map matching IDs to words");
our @errors = grep {not /$perl_regex/} @words; # validate against Perl regexp
ok(@errors == 0, "regular expression matches are correct");
diag("- these words should not have matched: @errors")
  if @errors;

our $total_f = $Word->idlist2freq(@id); # -- compute total frequency of matches
our $sum_f = 0; # alternatively, compute by summing up individual frequencies
foreach my $f ($Word->id2freq(@id)) { $sum_f += $f };
is($total_f, $sum_f, "total frequency counts are consistent"); # T16
is($total_f, 37, "total frequency of matching words");
diag("- regexp \"$regex\"\%c matches $n_types types, $total_f tokens");

our @cpos = $Word->idlist2cpos(@id); # -- look up corpus positions in index
is(@cpos+0, $total_f, "index lookup returns correct number of corpus positions"); # T18
@errors = grep {$Word->cpos2str($_) !~ /$perl_regex/} @cpos; # validate returned corpus positions
ok(@errors == 0, "index entries are correct");
diag("- these corpus positions should not have been in the index: @errors")
  if @errors;
our @first5 = $Word->cpos2str(@cpos[0 .. 4]);
diag("- index entries: ".join(", ", @first5, "..."));

our $n_sentences = $S->max_struc; # -- number of regions (s-attributes)
our $n_stories = $StoryTitle->max_struc;
is($n_sentences, 459, "number of sentences"); # T20
is($n_stories, 6, "number of stories");
diag("- VSS contains $n_stories stories with a total of $n_sentences sentences");

our $sent_num = $S->cpos2struc(7300); # -- find region and annotated value for given corpus position
is($sent_num, 430, "sentence number at corpus position 7300"); # T22
our ($start, $end) = $S->struc2cpos($sent_num);
our @sentence = $Word->cpos2str($start .. $end);
is("@sentence", "It was an elephant .", "full sentence containing corpus position 7300");
our $title = $StoryTitle->cpos2str(7300);
is($title, "The Garden", "value of <story_title> region at cpos 7300");
diag("- found sentence '@sentence' in story '$title'");

our @cpos_pairs = $S->struc2cpos(1, 3, 4, 0); # -- find & test region boundaries
is_deeply(\@cpos_pairs, [16, 39, 59, 69, 70, 73, 0, 15], "vectorised struc2cpos() method"); # T25
@cpos_pairs = $S->cpos2struc2cpos(20, 60, -10, 70, 15); # invalid cpos should return (undef, undef) pair
is_deeply(\@cpos_pairs, [16, 39, 59, 69, undef, undef, 70, 73, 0, 15], "vectorised cpos2struc2cpos() method");
our $f_i = $CWB::CL::Boundary{"inside"};
our $f_o = $CWB::CL::Boundary{"outside"};
our $f_l = $CWB::CL::Boundary{"left"}  | $CWB::CL::Boundary{"inside"}; # "inside" has to be set if "left" is set
our $f_r = $CWB::CL::Boundary{"right"} | $CWB::CL::Boundary{"inside"};
our $f_lr = $CWB::CL::Boundary{"leftright"} | $CWB::CL::Boundary{"inside"};
our @flags = $Chapter->cpos2boundary(2000, 1117, 1118, 5000, 7009, 6000);
is_deeply(\@flags, [$f_i, $f_r, $f_l, $f_o, $f_r, $f_o], "test region boundaries (cpos2boundary)");
@flags = $S->cpos2boundary(2701, 4000, 4380, 5000, 6250);
is_deeply(\@flags, [$f_l, $f_r, $f_lr, $f_i, $f_lr], "test region boundaries of single-token region");
@flags = $Chapter->cpos2is_boundary("inside", 2000, 1117, 1118, 5000, 7009, 6000);
is_deeply(\@flags, [1, 1, 1, 0, 1, 0], "test whether inside region (cpos2is_boundary)"); # T29
@flags = $Chapter->cpos2is_boundary("outside", 2000, 1117, 1118, 5000, 7009, 6000);
is_deeply(\@flags, [0, 0, 0, 1, 0, 1], "test whether outside region (cpos2is_boundary)");
@flags = $Chapter->cpos2is_boundary("right", 2000, 1117, 1118, 5000, 7009, 6000);
is_deeply(\@flags, [0, 1, 0, 0, 1, 0], "test for right boundary (cpos2is_boundary)");
@flags = $S->cpos2is_boundary("leftright", 2701, 4000, 4380, 5000, 6250);
is_deeply(\@flags, [0, 0, 1, 0, 1], "test for double boundary (cpos2is_boundary)");

our @align = $Align->cpos2alg(2725, 4242, 7777); # -- sentence alignment (self-alignment of VSS corpus)
is_deeply(\@align, [90, undef, 299], "find alignment beads (cpos2alg)"); # T33
our @beads = $Align->alg2cpos(90, 299);
is_deeply(\@beads, [2723, 2727, 2728, 2732, 7777, 7792, 7760, 7776], "expand alignment beads (alg2cpos)");
@beads = $Align->cpos2alg2cpos(2725, 4242, 7777);
is_deeply(\@beads, [2723, 2727, 2728, 2732, undef, undef, undef, undef, 7777, 7792, 7760, 7776], "expand alignment beads directly (cpos2alg2cpos)");

# total: 35 tests
