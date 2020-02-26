# -*-cperl-*-
## Same as 10_vss.t, but use object-oriented IO::Socket instead of raw socket calls

use Test::More;

use strict;
use warnings;

use CWB::CQI;
use CWB::CQI::IOClient;
use CWB::CQI::Server;

if (cqi_server_available()) {
  plan tests => 31;
}
else {
  plan skip_all => "CQPserver is not installed";
}

## -- connect to private CQPserver
cqi_connect(cqi_server("-r data/registry")); # this will forcibly exit if anything goes wrong
pass("connect to private CQPserver"); # T1

## -- check that necessary CQi features are supported
foreach my $feat (qw(cqi1.0 cl2.3 cqp2.3)) { # T2-T4
  ok(cqi_ask_feature($feat), "CQi feature $feat supported");
} 

## -- check for available corpora (only VSS in local registry)
is_deeply([cqi_list_corpora()], ["VSS"], "list available corpora (only VSS in local registry)"); # T5

## -- get corpus information
is(cqi_full_name("VSS"), "Very Short Stories", "long name of corpus"); # T6
is(cqi_attribute_size("VSS.word"), 8043, "corpus size");
is_deeply([sort(cqi_attributes("VSS", 'p'))], [qw(lemma pos word)], "list of p-attributes");
is(cqi_lexicon_size("VSS.pos"), 39, "lexicon size (types) of pos attribute"); # T9
is_deeply([sort(cqi_attributes("VSS", 's'))], [qw(chapter chapter_num p s story story_author story_num story_title story_year)], "list of s-attributes");
ok(cqi_structural_attribute_has_values("VSS.story_title"), "check for annotations of s-attribute"); 
ok(!cqi_structural_attribute_has_values("VSS.story"), "check for annotations of s-attribute");
is(cqi_attribute_size("VSS.story"), 6, "number of regions in s-attribute"); # T13

## -- direct corpus access
my $word = "VSS.word";
my $lemma = "VSS.lemma";
my $pos = "VSS.pos";
my $s = "VSS.s";
my $id = cqi_str2id($lemma, "tusk");
is($id, 1642, "look up lemma 'tusk' (id=1642)"); # T14
my @idlist = cqi_id2cpos($lemma, $id);
is_deeply(\@idlist, [7375, 7747], "index lookup (corpus positions)");

my @context_cpos = 7370 .. 7380;
my @context = cqi_cpos2str($word, @context_cpos);
is("@context", "of its long , curved tusks . Its large ears and", "match context (vectorised cpos2str())"); # T16
my @pos_context = cqi_cpos2str($pos, @context_cpos);
is("@pos_context", 'IN PP$ JJ , VBN NNS SENT PP$ JJ NNS CC', "match context (POS tags)");

my ($start, $end) = cqi_struc2cpos($s, cqi_cpos2struc($s, 7375)); # sentence context for first match
@context = cqi_cpos2str($lemma, $start .. $end);
is("@context", "the leathery skin of the pachyderm be of a dullish grey colour , form a harsh contrast to the ivory lustrum of its long , curve tusk .", "sentence context (lemma)"); # T18

@idlist = cqi_regex2id($word, "[a-z]+ment");
my %ment_nouns = qw(moment 5  apartment 3  comment 2  environment 2  government 2  excitement 1  embarrassment 1  disappointment 1  assignment 1  amusement 1  agreement 1); # expected atrings and frequencies
is(@idlist+0, 11, "regular expression search in lexicon (number of types)"); # T19
my @words = sort(cqi_id2str($word, @idlist));
is_deeply(\@words, [sort keys %ment_nouns], "regular expression search in lexicon (type list)");

my %F = map { cqi_id2str($word, $_) => cqi_id2freq($word, $_) } @idlist;
is_deeply(\%F, \%ment_nouns, "frequency information in lexcion"); # T21
%F = ();
my @cpos = cqi_idlist2cpos($word, @idlist);
foreach my $w (cqi_cpos2str($word, @cpos)) {
  $F{$w}++;
}
is_deeply(\%F, \%ment_nouns, "frequency counts from corpus"); # T22

## -- execute CQP query
my $query = '"small"%c (","? [pos="JJ.*"])* [pos="NN.*"]';
my $status = cqi_query("VSS", "Small", $query);
is($status, $CWB::CQI::STATUS_OK, "run CQP query (status ok)"); # T23
is_deeply([sort(cqi_list_subcorpora("VSS"))], [qw(Last Small)], "list named query results for VSS");
my $size = cqi_subcorpus_size("VSS:Small");
is($size, 6, "correct number of matches");
ok(cqi_subcorpus_has_field("VSS:Small", 'match'), "query result has match anchor");
ok(!cqi_subcorpus_has_field("VSS:Small", 'target'), "query result doesn't have target anchor"); # T27

my $expected_match    = [958, 1130, 2543, 4581, 7415, 8037];
my $expected_matchend = [959, 1131, 2544, 4582, 7418, 8038];
is_deeply([cqi_dump_subcorpus("VSS:Small", 'match', 0, $size-1)], $expected_match, "dump query result (match anchors)"); # T28
is_deeply([cqi_dump_subcorpus("VSS:Small", 'matchend', 0, $size-1)], $expected_matchend, "dump query result (matchend anchors)");

cqi_drop_subcorpus("VSS:Small");
is_deeply([sort(cqi_list_subcorpora("VSS"))], [qw(Last)], "discard named query result"); # T30

## -- disconnect from server
cqi_bye();
pass("disconnect from CQi server"); # T31
