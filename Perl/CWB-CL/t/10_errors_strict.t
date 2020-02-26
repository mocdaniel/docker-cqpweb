use Test::More tests => 45;
## test errors thrown by CWB::CL in strict mode

use CWB::CL::Strict;
use strict;
use warnings;

$CWB::CL::Registry = "data/registry"; # use local copy of VSS corpus

pass("dummy test to display diagnostic messages");
diag("");
diag("----- testing error conditions ----- (expect to see error messages below)");

eval { new CWB::CL::Corpus "POTTER" };
like($@, qr/access corpus.*POTTER/, "catch error on non-existent corpus"); # T2

our $C = new CWB::CL::Corpus "VSS";
isa_ok($C, "CWB::CL::Corpus", "VSS corpus")
  or BAIL_OUT("failed to access test corpus VSS");

eval { $C->attribute("woertle", "p") };
like($@, qr/access p-attribute.*VSS\.woertle/, "catch error on undefined p-attribute"); # T4
eval { $C->attribute("saetzle", "s") };
like($@, qr/access s-attribute.*VSS\.saetzle/, "catch error on undefined s-attribute");
eval { $C->attribute("uebersetzuengle", "a") };
like($@, qr/access a-attribute.*VSS\.uebersetzuengle/, "catch error on undefined s-attribute");

our $Word = $C->attribute("word", "p");
isa_ok($Word, "CWB::CL::PosAttrib", "VSS.word attribute") # T7
  or BAIL_OUT("failed to access attribute VSS.word");
our $NoData = $C->attribute("no_data", "p");
isa_ok($NoData, "CWB::CL::PosAttrib", "attribute object for VSS.no_data")
  or BAIL_OUT("failed to access attribute VSS.no_data");
our $S = $C->attribute("s", "s");
isa_ok($S, "CWB::CL::StrucAttrib", "attribute object for VSS.s")
  or BAIL_OUT("failed to access attribute VSS.s");
our $Chapter = $C->attribute("chapter_num", "s") # not a test
  or BAIL_OUT("failed to access attribute VSS.chapter_num");
our $Align = $C->attribute("vss", "a") # not a test
  or BAIL_OUT("failed to access attribute VSS.vss (self-alignment)");

eval { $NoData->max_cpos };
like($@, qr/can't.*data/, "catch data access error"); # T10
our $size = $Word->max_cpos;
ok($size > 7000, "plausible corpus size")
  or BAIL_OUT("something wrong with test corpus VSS");

eval { $Word->cpos2id(-42) };
like($@, qr/position.*out of range/, "catch out of range error (cpos2id)"); # T12
eval { $Word->cpos2str($size) };
like($@, qr/position.*out of range/, "catch out of range error (cpos2str)");
eval { $Word->cpos2str(0, 1, -1, 42) };
like($@, qr/position.*out of range/, "catch out of range error (cpos2str)");

eval { $Word->cpos2id(1, 2, undef, 5) };
like($@, qr/invalid.*argument/, "catch undefined argument (cpos2id)"); # T15
eval { $Word->cpos2str(1, 2, undef, 5) };
like($@, qr/invalid.*argument/, "catch invalid argument (cpos2str)");
eval { $Word->cpos2id(1, 2, undef, 5, $size) };
like($@, qr/position.*out of range/, "catch last of multiple errors (cpos2id)");

eval { $Word->regex2id('(.*]') };
like($@, qr/bad reg.*exp/, "catch regexp syntax error (regex2id)"); # T18
eval { $Word->regex2id('a{400000}') };
like($@, qr/bad reg.*exp/, "catch regexp syntax error (regex2id)");
eval { $Word->regex2id('elephant', "i") };
like($@, qr/Usage/, "catch invalid regexp flags (regex2id)");

eval { $Word->idlist2freq(0, 1, 2, undef, 4, 5) };
like($@, qr/invalid.*argument/, "catch undefined argument (idlist2freq)"); # T21
eval { $Word->idlist2freq(0, 1, 2, -3, 4, 5) };
like($@, qr/index.*out of range/, "catch ID out of range error (idlist2freq)");
eval { $Word->idlist2cpos(0, 1, 2, undef, 4, 5) };
like($@, qr/invalid.*argument/, "catch undefined argument (idlist2cpos)");
eval { $Word->idlist2cpos(0, 1, 2, -3, 4, 5) };
like($@, qr/index.*out of range/, "catch ID out of range error (idlist2cpos)");

eval { $S->cpos2struc(1, 100, undef, 1000, -1) }; # CL doesn't catch cpos out of range errors so far
like($@, qr/invalid.*argument/, "catch undefined argument (cpos2struc)"); # T25
eval { $S->cpos2struc2cpos(1, 100, undef, 1000, -1) }; # nor here, of course
like($@, qr/invalid.*argument/, "catch undefined argument (cpos2struc2cpos)");
our @struc = ();
eval { @struc = $Chapter->cpos2struc(1100, 2200, 4400, 5500, 7000) };
is($@, "", "tokens outside <chapter> region must not throw errors (cpos2struc)");
is_deeply(\@struc, [0, 2, undef, undef, 6], "tokens outside <chapter> region return undef (cpos2struc)");

eval { $Chapter->cpos2str(1, 100, undef, 1000, -1) }; # same for cpos2str on s-attribute
like($@, qr/invalid.*argument/, "catch undefined argument (cpos2str on s-attribute)"); # T29
our @str = ();
eval { @str = $Chapter->cpos2str(1100, 2200, 4400, 5500, 7000) };
is($@, "", "tokens outside <chapter> region must not throw errors (cpos2str on s-attribute)");
is_deeply(\@str, ["1", "3", undef, undef, "1"], "tokens outside <chapter> region return undef (cpos2str on s-attribute)");

eval { $Chapter->struc2str(1 .. 6, 10, 1 .. 5) };
like($@, qr/index.*out of range/, "catch region index out of range error (struc2str)"); # T32
eval { $Chapter->struc2cpos(1 .. 6, 10, 1 .. 5) };
like($@, qr/index.*out of range/, "catch region index out of range error (struc2cpos)");
eval { $Chapter->cpos2boundary(1, 100, undef, 1000, -1) };
like($@, qr/invalid.*argument/, "catch undefined argument (cpos2boundary)");
eval { @str = $Chapter->cpos2is_boundary("inside", 1100, 2200, 4400, 5500, 7000) };
is($@, "", "tokens outside <chapter> region must not throw errors (cpos2is_boundary)");
is_deeply(\@str, [1, 1, 0, 0, 1], "tokens outside <chapter> region return FALSE (cpos2is_boundary)");

eval { $Align->cpos2alg(2725, undef, 4242, -10, 7777) }; # CL doesn't catch out of range errors here
like($@, qr/invalid.*argument/, "catch undefined argument (cpos2alg)"); # T37
eval { $Align->alg2cpos(90, 500, 299, 10) };
like($@, qr/index.*out of range/, "catch alignment index out of range error (alg2cpos)");
our @beads = ();
eval { @beads = $Align->cpos2alg2cpos(4242, 7777) };
is($@, "", "unaligned tokens must not throw errors (cpos2alg2cpos)");
is_deeply(\@beads, [undef, undef, undef, undef, 7777, 7792, 7760, 7776], "unaligned tokens return undef beads (cpos2alg2cpos)");

eval { CWB::CL::make_set("ab c def", "WHITESPACE") }; # utility functions for feature sets
like($@, qr/Usage:.*make_set/, "catch invalid split flag (make_set)"); # T41
eval { CWB::CL::make_set("def|ab|c") };
like($@, qr/invalid feature set/, "catch malformed feature set (make_set)");
eval { CWB::CL::set_intersection("|a|b|c|", "b c") };
like($@, qr/invalid feature set/, "catch malformed feature set (set_intersection)");
eval { CWB::CL::set_intersection("|a|b|c|", "|".("a|" x 50_000)) };
like($@, qr/buffer overflow/, "catch buffer overflow (set_intersection)");
eval { CWB::CL::set2hash("|a|b|c") };
like($@, qr/invalid feature set/, "catch malformed feature set (set2hash)");

diag("----- end of error tests ----- (further messages are real errors)");

# total: 45 tests