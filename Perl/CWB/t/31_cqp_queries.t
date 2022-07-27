# -*-cperl-*-
## Test basic CWB::CQP functionality and CQP query features

use Test::More tests => 56;

use CWB::CQP;

our $cqp = new CWB::CQP "-r data/registry", "-I data/files/init.cqp";
isa_ok($cqp, "CWB::CQP"); # T1

$cqp->set_error_handler('die'); # any CQP errors in this script are major problems and should result in test failure

$cqp->exec("VSS");
ok($cqp->ok, "activate VSS corpus"); # T2

# repeatedly execute macro and save named queries to disk
my @expected_res = qw(0 1293 224 19 2 0);
foreach my $i (1 .. 5) {
  $cqp->exec("Res$i = /sequence_of_NP_PP[$i]");
  my ($n_matches) = $cqp->exec("size Res$i");
  is($n_matches, $expected_res[$i], "/sequence_of_NP_PP[$i] finds $expected_res[$i] matches"); # T3 .. T7
}

foreach my $i (1 .. 5) {
  $cqp->exec("save Res$i");
}
pass("save named queries to disk"); # T8

my @disk_files = glob "tmp/VSS:Res?";
is(@disk_files, 5, "all disk files have been created"); # T9

# exit and re-start CQP
undef $cqp;
$cqp = new CWB::CQP "-r data/registry", "-I data/files/init.cqp";
$cqp->set_error_handler('die');
$cqp->exec("VSS");
pass("terminate and restart CQP process"); # T10

# check that saved queries are visible in new CQP session
my @named = $cqp->exec("show named");
is((grep {/-d-\s+VSS:Res[0-9]/} @named), 5, "saved query results available in new CQP process"); # T11

# re-load query results and check number of matches
foreach my $i (1 .. 5) {
  my ($n_matches) = $cqp->exec("size Res$i");
  is($n_matches, $expected_res[$i], "saved query result Res$i loaded correctly"); # T12 .. T16
}

# KWIC display of the two instances of 4 NPs / PPs in a row
$cqp->exec("set Context 1 word");
$cqp->exec("set LeftKWICDelim '***'");
$cqp->exec("set RightKWICDelim '***'");
$cqp->exec("show -cpos");
my @kwic = $cqp->exec("cat Res4");
is_deeply(\@kwic, ["pointed ***a finger at a placard to the left of the entrance*** and", ", ***heralds of a glimpse of light in the hallway*** ."], "simple KWIC output"); # T17

@kwic = $cqp->exec("tabulate Res4 match .. matchend lemma");
is_deeply(\@kwic, ["a finger at a placard to the left of the entrance", "herald of a glimpse of light in the hallway"], "KWIC output from tabulate"); # T18

# distribution count with "group"
my @rows = $cqp->exec_rows("group Res1 match story_title");
is(@rows, 6, "correct number of entries in distribution count"); # T19
my %expected_counts = ("264" => 688, "Waiting" => 188, "An Example of Idiomatic English" => 159, "A Thrilling Experience" => 105, "The Garden" => 103, "How To Swim" => 50);
is_deeply(rows2hash(\@rows), \%expected_counts, "correct frequency counts for distribution across stories"); # T20

# set target and keyword + subsetting
$cqp->exec("NP_PP = /np[] @[::] /pp[]");
my ($n_matches) = $cqp->exec("size NP_PP");
my ($n_targets) = $cqp->exec("size NP_PP target");
my ($n_keywords) = $cqp->exec("size NP_PP keyword");
is($n_matches, 214, "find 214 instances of NP followed by PP"); # T21
is($n_targets, $n_matches, "all target markers have been set"); # T22
is($n_keywords, 0, "no keywords are set"); # T23

$cqp->exec("NP_PP = subset NP_PP where target: [word != 'to' \%c]");
@rows = $cqp->exec_rows("group NP_PP target pos");
is_deeply(\@rows, [["IN", 201]], "subset on target worked correctly"); # T24

$cqp->exec("set NP_PP keyword nearest [pos = 'V.*'] within left 1 word from match");
($n_keywords) = $cqp->exec("size NP_PP keyword");
is($n_keywords, 70, "set keyword [...] successful"); # T25
$cqp->exec("delete NP_PP without keyword");
($n_matches) = $cqp->exec("size NP_PP");
is($n_matches, $n_keywords, "deleted matches without keyword anchor"); # T26

@rows = $cqp->exec_rows("count NP_PP by lemma on keyword");
is(@rows, 54, "count by lemma produces correct number of items"); # T27
%expected_counts = qw(be 7  have 5  watch 3  make 2);
is_deeply(rows2hash([@rows[0 .. 3]], 2, 0), \%expected_counts, "count by lemma produces correct frequency counts"); # T28

# advanced group and count
$cqp->exec("PP = /pp[]");
($n_matches) = $cqp->exec("size PP");
ok($n_matches > 500, "simple PP query"); # T29

@rows = $cqp->exec_rows("count PP by lemma cut 3");
%expected_counts = ("in front" => 4, "on the screen" => 3);
is_deeply(rows2hash(\@rows, 2, 0), \%expected_counts, "count PPs by lemma with cut"); # T30

@rows = $cqp->exec_rows("count PP by lemma on match[1] .. matchend cut 6");
%expected_counts = ("the girl" => 7, "the screen" => 6);
is_deeply(rows2hash(\@rows, 2, 0), \%expected_counts, "count PPs by lemma without first token"); # T31

@rows = $cqp->exec_rows("group PP matchend lemma by match word cut 3");
%expected_counts = ("in front" => 4, "on screen" => 4, "of window" => 4, "at time" => 3);
@rows = map { [$_->[0]." ".$_->[1], $_->[2]] } @rows;
is_deeply(rows2hash(\@rows), \%expected_counts, "group prep:noun pairs from PPs with cut"); # T32

SKIP: {
  skip "group ... within only supported by CQP v3.4.26 and newer", 2 unless $cqp->check_version(3, 4, 26);
  @rows = $cqp->exec_rows("group PP matchend lemma by match word within s cut 3");
  @rows = map { [$_->[0]." ".$_->[1], $_->[2]] } @rows;
  is_deeply(rows2hash(\@rows), \%expected_counts, "group prep:noun pairs from PPs within s"); # T33
  @rows = $cqp->exec_rows("group PP matchend lemma by match word within story cut 3");
  @rows = map { [$_->[0]." ".$_->[1], $_->[2]] } @rows;
  is_deeply(rows2hash(\@rows), {"at time" => 3}, "group prep:noun pairs from PPs within story");
}

# dump/undump and subqueries
$cqp->exec("NP = /np[]");
($n_matches) = $cqp->exec("size NP");
ok($n_matches > 1200, "simple NP query"); # T35

$cqp->exec("sort NP by word \%c");
@rows = $cqp->exec("tabulate NP 10 20 match lemma");
ok((not grep { $_ ne "a" } @rows), "alphabetical sort (plausibility check)"); # T36

my @dump = map { [$_->[0], $_->[1]] } $cqp->dump("NP");
$cqp->undump("NP_copy", @dump);
my @rows1 = $cqp->exec("tabulate NP match .. matchend lemma");
my @rows2 = $cqp->exec("tabulate NP_copy match .. matchend lemma");
is_deeply(\@rows1, \@rows2, "undump preserves sort order"); # T37

@dump = map {
  my ($s, $e) = @$_;
  $s = ($s > 0) ? $s - 1 : $s;  # expand matches by one token on the left
  [$s, $e];
} @dump;
$cqp->undump("NP_mod", @dump);
$cqp->exec("NP_mod"); # subquery on modified undump
$cqp->exec("PP_subquery = <match> [pos='IN|TO'] []* [pos='NN.*'] </match>");
$cqp->exec("VSS");

$cqp->exec("Diff1 = diff PP PP_subquery"); # check that query results are identical
$cqp->exec("Diff2 = diff PP_subquery PP");
my ($n1) = $cqp->exec("size Diff1");
my ($n2) = $cqp->exec("size Diff2");
ok($n1 == 0 && $n2 == 0, "modified undump + subquery gives expected result"); # T38

# asynchronous execution with run() / getline()
$cqp->run("tabulate NP match .. matchend lemma");
@rows = ();
while (my $row = $cqp->getline) {
  push @rows, $row;
}
is_deeply(\@rows, \@rows1, "asynchronous execution (tabulate command)"); # T39
ok((not defined $cqp->ready), "asychronous execution has completed"); # T40

# progress bar handler
my @progress_data = ();
$cqp->set_progress_handler(sub { my $perc = shift; push @progress_data, $perc if $perc > 0 });
$cqp->progress_on;
$cqp->exec("Temp = [pos='IN'] /np[]");
is_deeply(\@progress_data, [ 1 .. 100 ], "progress handler works correctly"); # T41
$cqp->progress_off;

# matching strategy modifier (CQP v3.4.12 and newer) T42–T43
SKIP: {
  skip "matching strategy modifiers only supported by CQP v3.4.12 and newer", 2 unless $cqp->check_version(3, 4, 12);
  my $query = "[pos='JJ.*'] [pos='NNS?']+";
  $cqp->exec("JN0 = $query");
  $cqp->exec("JN1 = (?longest) $query");
  $cqp->exec("set MatchingStrategy longest");
  $cqp->exec("JN2 = $query");
  $cqp->exec("set MatchingStrategy standard");
  my @JN1 = $cqp->dump("JN1");
  my @JN2 = $cqp->dump("JN2");
  $cqp->exec("JDiff = diff JN2 JN0");
  my ($n_diff) = $cqp->exec("size JDiff");
  is_deeply(\@JN1, \@JN2, "matching strategy modifier works (?longest)");
  ok($n_diff > 0, "confirm that matching strategy makes a difference");
}

# corpus position lookup (new in CQP v3.4.17) T44-T45
SKIP: {
  skip "corpus position lookup only available in CQP v3.4.17 and newer", 2 unless $cqp->check_version(3, 4, 17);
  $cqp->exec("CP1 = [_ = 666] []{2}");
  my @result = $cqp->dump("CP1");
  is_deeply(\@result, [[666, 668, -1, -1]], "corpus position lookup with [_ = 666]");
  $cqp->exec("CP2 = [lemma = 'elephant'] []{0,10} [_ >= 8038]"); # should also work in earlier versions
  @result = $cqp->dump("CP2");
  is_deeply(\@result, [[8031, 8038, -1, -1]], "corpus position test with ... [_ >= 8038]");
}

# strlen() built-in function (new in CQP v3.4.17) T46-T50
SKIP: {
  skip "strlen() built-in only available in CQP v3.4.17 and newer", 5 unless $cqp->check_version(3, 4, 17);
  for my $corpus (qw(GOETHE_LATIN1 GOETHE_UTF8)) {
    $cqp->exec($corpus); 
    $cqp->exec("G1 = [word = '.*chen' & strlen(word) = 7]");
    $cqp->exec("G2 = [word = '.*chen' & strlen(word) >= 8]");
    my ($n1) = $cqp->exec("size G1");
    my ($n2) = $cqp->exec("size G2");
    ok($n1 == 1, "strlen() test works for corpus $corpus");
    ok($n2 == 0, "negative strlen() test works for corpus $corpus");
  }
  $cqp->exec("VSS");
  $cqp->exec("G3 = [lemma = 'time'] :: strlen(match.story_title) <= 5");
  my ($n) = $cqp->exec("size G3");
  ok($n == 10, "strlen() test works for s-attribute annotation");
}

# validate MU Queries against corresponding finite-state queries T51-T54
$cqp->exec("Union_MU = MU(union [lemma = 'coffee'] [lemma = 'elephant'])");
$cqp->exec("Union_CQL = ([lemma = 'coffee'] | [lemma = 'elephant'])");
ok(matches_eq("Union_MU", "Union_CQL"), "MU(union ...) corresponds to basic query");

$cqp->exec("Meet3_MU = MU(meet 'of' 'the' -3 3)");
$cqp->exec("Meet3_CQL = 'of'");
$cqp->exec("set Meet3_CQL target nearest 'the' within 3 words");
$cqp->exec("delete Meet3_CQL without target");
ok(matches_eq("Meet3_MU", "Meet3_CQL"), "MU(meet ... -3 3) corresponds to basic query");

$cqp->exec("MeetS_MU = MU(meet 'elephant' 'garden' s)");
$cqp->exec("MeetS_CQL = 'elephant'");
$cqp->exec("set MeetS_CQL target nearest 'garden' within s");
$cqp->exec("delete MeetS_CQL without target");
ok(matches_eq("MeetS_MU", "MeetS_CQL"), "MU(meet ... s) corresponds to basic query");

$cqp->exec("MeetElephant_MU = MU(union (meet 'elephant' 'the'\%c -1 -1) 'elephants')");
$cqp->exec("MeetElephant_CQL = 'the'\%c 'elephant' | 'elephants'");
$cqp->exec("set MeetElephant_CQL match matchend"); # remove 'the' from match
ok(matches_eq("MeetElephant_MU", "MeetElephant_CQL"), "complex MU query corresponds to basic query");

# validate TAB queries against corresponding finite-state queries T55-T56
$cqp->exec('Garden_TAB = TAB "in"%c "the"%c "garden"%c');
$cqp->exec('Garden_CQL = "in"%c "the"%c "garden"%c');
ok(matches_eq("Garden_TAB", "Garden_CQL"), "n-gram TAB query corresponds to basic query");

$cqp->exec('Elephant_TAB = TAB [lemma="elephant"] ? "my" ? [lemma="garden"]');
$cqp->exec('Elephant_CQL = [lemma="elephant"] []? "my" []? [lemma="garden"]');
ok(matches_eq("Elephant_TAB", "Elephant_CQL"), "concgram TAB query corresponds to basic query");

exit 0;

# check that two named query results are identical
# returns TRUE / FALSE
sub matches_eq {
  my $A = shift;
  my $B = shift;
  $cqp->exec("_Tmp = diff $A $B");
  my ($n_AmB) = $cqp->exec("size _Tmp");
  $cqp->exec("_Tmp = diff $B $A");
  my ($n_BmA) = $cqp->exec("size _Tmp");
  $cqp->exec("_Tmp = intersect $B $A");
  my ($n_AB) = $cqp->exec("size _Tmp");
  $cqp->exec("discard _Tmp");
  if ($n_AmB > 0 || $n_BmA > 0) {
    diag("Result sets A=$A and B=$B differ: (A: $n_AmB ( $n_AB ) $n_BmA :B)");
    return 0;
  }
  return 1;
}

# convert list of result rows into hash for robust comparison of frequency counts
# usage: $hashref = rows2hash($rows, $key_col=0, $val_col=1);
sub rows2hash {
  my $rows = shift;
  my $key_col = (@_) ? shift : 0;
  my $val_col = (@_) ? shift : 1;
  my $hash = {};
  foreach my $row (@$rows) {
    my $key = $row->[$key_col];
    diag("key error: '$key' has multiple entries in frequency table")
      if exists $hash->{$key};
    $hash->{$key} = $row->[$val_col];
  }
  return $hash;
}

