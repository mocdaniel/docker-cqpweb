# -*-cperl-*-
## Test basic CWB::CQP functionality

use Test::More tests => 21;

use CWB::CQP;

our $cqp = new CWB::CQP "-r data/registry";
isa_ok($cqp, "CWB::CQP"); # T1

ok($cqp->check_version(3,0,0), "CWB version must be 3.0.0 or newer");
ok(!$cqp->check_version(42,0), "fail version check for release 42.0"); # T3

$cqp->set_error_handler('ignore'); # silently ignore errors so we can test them explicitly

# string and regexp quoting functions
is($cqp->quote("'em"), "\"'em\"", "quote with double quotes");
is($cqp->quote('12" screen'), "'12\" screen'", "quote with single quotes");
is($cqp->quote("2\" 15' angle"), "\"2\"\" 15' angle\"", "escape delimiter by doubling");
is($cqp->quote("2\\\" 15' angle"), "\"2\\\" 15' angle\"", "delimiter already backslash-escaped");
is($cqp->quote("'\\'\\\\\\\"\""), "\"'\\'\\\\\\\"\"\"\"", "pathological quotes");
$cqp->quote("invalid\\"); # single backslash at end of string cannot be quoted
ok(!$cqp->ok, "cannot quote string ending in stranded backslash"); # T9

is($cqp->quotemeta("!?"), "!\\?", "escape regexp metacharacters");
is($cqp->quotemeta("([a-z]|\$){2,5}"), "\\(\\[a-z\\]\\|\\\$\\)\\{2,5\\}", "complex regexp escapes");
is($cqp->quotemeta("^[^0-9+*]"), "[^]\\[[^]0-9\\+\\*\\]", "use [^] to avoid conflict with CQP latex escapes"); # T12

# registry listing and corpus attributes
my @corpora = sort $cqp->exec("show corpora");
is_deeply(\@corpora, [qw(CONLL_U DICKENS GOETHE_LATIN1 GOETHE_UTF8 VSS)], "show corpora;");
$cqp->exec("NOT_INSTALLED");
ok(!$cqp->ok, "attempt to activate nonexistent corpus");
$cqp->exec("VSS");
ok($cqp->ok, "activate VSS corpus");
my @attributes = $cqp->exec("show cd");
my @found = sort map {s/-Att\s+/:/; s/\s+((-V)?)(\t\S*)?$/$1/; m/^(p:word|p:pos|p:lemma|s:p|s:story_title-V)$/} @attributes;
is_deeply(\@found, [sort qw(p:word p:pos p:lemma s:p s:story_title-V)], "show cd;"); # T16

# catch CQP error message with explicit check or custom handler
$cqp->exec("show nix");
like(join(" ", $cqp->error_message), qr/show what/, "catch CQP error message"); # T17
my $err_msg = "";
$cqp->set_error_handler(sub { $err_msg = join(" ", @_) });
$cqp->exec("'('");
like($err_msg, qr/illegal regular expression/i, "catch CQP error message with custom handler"); # T18
$cqp->set_error_handler('ignore');

# detect query lock violation
my @nothing = $cqp->exec_query("show cd");
like(join(" ", $cqp->error_message), qr/query lock violation/i, "detect query lock violation"); # T19
ok(@nothing == 0, "block interactive command in query lock mode"); # T20

# accept multiple commands (though deprecated)
@nothing = $cqp->exec(";;;;;;");
ok(@nothing == 0, "accept multiple empty commands without output"); # T21

