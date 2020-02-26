use Test::More tests => 62;
## test error handling in standard "lax" mode

use CWB::CL;
use strict;
use warnings;

is(CWB::CL::strict(), 0, "CWB::CL defaults to 'lax' error handling"); # T1
CWB::CL::strict(0); # ensure that these tests are run in 'lax' mode
$CWB::CL::Registry = "data/registry"; # use local copy of VSS corpus

diag("");
diag("----- testing error conditions ----- (expect to see error messages below)");

our $value = 42;
our @values = ();

our $no_C = 42;
eval { $no_C = new CWB::CL::Corpus "POTTER" };
is($@, "", "don't croak on non-existent corpus"); # T2
ok(!defined $no_C, "non-existent corpus returns undef");

our $C = new CWB::CL::Corpus "VSS"
  or BAIL_OUT("failed to access test corpus VSS");

our $no_Att = 42;
eval { $no_Att = $C->attribute("woertle", "p") };
is($@, "", "don't croak on non-existent p-attribute"); # T4
ok(!defined $no_Att, "non-existent p-attribute returns undef");
$no_Att = 42;
eval { $no_Att = $C->attribute("saetzle", "s") };
is($@, "", "don't croak on non-existent s-attribute");
ok(!defined $no_Att, "non-existent s-attribute returns undef");
$no_Att = 42;
eval { $no_Att = $C->attribute("uebersetzuengle", "a") };
is($@, "", "don't croak on non-existent a-attribute");
ok(!defined $no_Att, "non-existent a-attribute returns undef");

our $Word = $C->attribute("word", "p")
  or BAIL_OUT("failed to access attribute VSS.word");
our $NoData = $C->attribute("no_data", "p")
  or BAIL_OUT("failed to access attribute VSS.no_data");
our $S = $C->attribute("s", "s")
  or BAIL_OUT("failed to access attribute VSS.s");
our $Chapter = $C->attribute("chapter_num", "s")
  or BAIL_OUT("failed to access attribute VSS.chapter_num");
our $Align = $C->attribute("vss", "a")
  or BAIL_OUT("failed to access attribute VSS.vss (self-alignment)");

$value = 42;
eval { $value = $NoData->max_cpos };
is($@, "", "don't croak on data access error"); # T10
ok(!defined $value, "data access error returns undef");
our $size = $Word->max_cpos;
ok($size > 7000, "plausible corpus size")
  or BAIL_OUT("something wrong with test corpus VSS");

$value = 42;
eval { $value = $Word->cpos2id(-42) };
is($@, "", "don't croak on range error (cpos2id)"); # T13
ok(!defined $value, "range error returns undef (cpos2id)");
$value = 42;
eval { $value = $Word->cpos2str($size) };
is($@, "", "don't croak on range error (cpos2str)");
ok(!defined $value, "range error returns undef (cpos2str)");
@values = ();
eval { @values = $Word->cpos2str(0, 1, -1, 42) };
is($@, "", "don't croak on range error (vectorised cpos2str)");
is_deeply(\@values, ["The", "constant", undef, "alarm-clock"], "range error returns undef (vectorised cpos2str)");

@values = ();
eval { @values = $Word->cpos2id(-1, 40, undef, 2) };
is($@, "", "don't croak on invalid arguments (cpos2id)"); # T19
is_deeply(\@values, [undef, 0, undef, 2], "invalid arguments return undef (cpos2id)");
@values = ();
eval { @values = $Word->cpos2str(undef, 40, undef, 2) };
is($@, "", "don't croak on invalid arguments (cpos2str)");
is_deeply(\@values, [undef, "The", undef, "hum"], "invalid arguments return undef (cpos2str)");

@values = (42);
eval { @values = $Word->regex2id('(.*]') };
is($@, "", "don't croak on regexp syntax error (regex2id)"); # T23
ok(@values == 0, "regexp syntax error returns empty list (regex2id)");
@values = (42);
eval { @values = $Word->regex2id('a{400000}') };
is($@, "", "don't croak on regexp syntax error (regex2id)");
ok(@values == 0, "regexp syntax error returns empty list (regex2id)");
eval { $Word->regex2id('elephant', "i") };
like($@, qr/Usage/, "invalid regexp flags always croaks (regex2id)");

$value = 42;
eval { $value = $Word->idlist2freq(-10, 1, 2, undef, 5) };
is($@, "", "don't croak on invalid ID arguments (idlist2freq)"); # T28
ok(!defined $value, "invalid ID arguments return undef (idlist2freq)");
@values = (42);
eval { @values = $Word->idlist2cpos(0, -10, 2, undef, 4, 5) };
is($@, "", "don't croak on invalid ID arguments (idlist2cpos)");
ok(@values == 0, "invalid ID arguments return emptry list (idlist2cpos)");

@values = ();
eval { @values = $S->cpos2struc(1, 100, undef, 1000, -1) };
is($@, "", "don't croak on range error (cpos2struc)"); # T32
is_deeply(\@values, [0, 5, undef, 44, undef], "range errors return undef (cpos2struc)");
@values = ();
eval { @values = $S->cpos2struc2cpos(1, 100, undef, 1000, -1) };
is($@, "", "don't croak on range error (cpos2struc2cpos)");
is_deeply(\@values, [0, 15, 74, 103, undef, undef, 978, 1008, undef, undef], "range errors return undef (cpos2struc2cpos)");

@values = ();
eval { @values = $Chapter->cpos2str(1100, undef, 2200, 4400, 5500, 7000, -10) };
is($@, "", "don't croak on range errors and tokens outside <chapter> region (cpos2str)"); # T36
is_deeply(\@values, ["1", undef, "3", undef, undef, "1", undef], "range errors and tokens outside <chapter> region return undef (cpos2str)");

@values = (); 
eval { @values = $Chapter->struc2str(-1, undef, 0, 10, 4) };
is($@, "", "don't croak on invalid region index (struc2str)"); # T38
is_deeply(\@values, [undef, undef, "1", undef, "5"], "invalid region index returns undef (struc2str)");
@values = ();
eval { @values = $Chapter->struc2cpos(-1, undef, 0, 10, 4) };
is($@, "", "don't croak on invalid region index (struc2cpos)");
is_deeply(\@values, [undef, undef, undef, undef, 0, 1117, undef, undef, 3189, 3759], "invalid region index returns undef (struc2cpos)");
@values = ();
eval { @values = $Chapter->cpos2boundary(0, 4400, undef, 7219, -1) };
is($@, "", "don't croak on range errors and other invalid arguments (cpos2boundary)");
our $f_lb = $CWB::CL::Boundary{"left"} | $CWB::CL::Boundary{"inside"};
our $f_rb = $CWB::CL::Boundary{"right"} | $CWB::CL::Boundary{"inside"};
our $f_o = 0; # currently, CL also returns 0 if cpos is out of range (only invalid arguments are errors)
is_deeply(\@values, [$f_lb, $f_o, undef, $f_rb, 0], "invalid arguments return undef, range errors 0 (cpos2boundary)");
@values = ();
eval { @values = $Chapter->cpos2is_boundary("inside", 1100, undef, 2200, 100000, 4400, 5500, 7000, -1) };
is($@, "", "don't croak on range errors and other invalid arguments (cpos2is_boundary)");
is_deeply(\@values, [1, undef, 1, 0, 0, 0, 1, 0], "invalid arguments return undef, range errors FALSE (cpos2is_boundary)");

@values = ();
eval { @values = $Align->cpos2alg(2725, undef, 4242, -10, 7777, 100000) };
is($@, "", "don't croak on range errors and other invalid arguments (cpos2alg)"); # T46
is_deeply(\@values, [90, undef, undef, undef, 299, undef], "range errors and other invalid arguments return undef (cpos2alg)");
@values = ();
eval { @values = $Align->alg2cpos(100000, -10, undef) };
is($@, "", "don't croak on invalid alignment beads (alg2cpos)");
ok((not grep {defined} @values), "invalid alignment beads return undef (alg2cpos)");
ok(@values == 12, "invalid alignment beads return undef (alg2cpos)");
@values = ();
eval { @values = $Align->cpos2alg2cpos(4242, -1, undef) };
is($@, "", "don't croak on range errors and other invalid arguments (cpos2alg2cpos)");
ok((not grep {defined} @values), "range errors and other invalid arguments return undef (cpos2alg2cpos)");
ok(@values == 12, "range errors and other invalid arguments return undef (cpos2alg2cpos))");

eval { CWB::CL::make_set("ab c def", "WHITESPACE") };
like($@, qr/Usage:.*make_set/, "invalid split flag always croaks (make_set)"); # T54
$value = 42;
eval { $value = CWB::CL::make_set("def|ab|c") };
is($@, "", "don't croak on malformed feature set (make_set)");
ok(!defined $value, "malformed feature set returns undef (make_set)");
$value = 42;
eval { $value = CWB::CL::set_intersection("|a|b|c|", "b c") };
is($@, "", "don't croak on malformed feature set (set_intersection)");
ok(!defined $value, "malformed feature set returns undef (set_intersection)");
$value = 42;
eval { $value = CWB::CL::set_intersection("|a|b|c|", "|".("a|" x 50_000)) };
is($@, "", "don't croak on buffer overflow (set_intersection)");
ok(!defined $value, "buffer overflow returns undef (set_intersection)");
$value = 42;
eval { $value = CWB::CL::set2hash("|a|b|c") };
is($@, "", "don't croak on malformed feature set (set2hash)");
ok(!defined $value, "malformed feature set returns undef (set2hash)");

diag("----- end of error tests ----- (further messages are real errors)");

# total: 62 tests