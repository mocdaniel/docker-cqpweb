# -*-cperl-*-
## Test automatic character recoding with CWB::CQP

use utf8;
use Test::More tests => 18;

use CWB::CQP;
use Encode;

our $cqp = new CWB::CQP "-r data/registry";
isa_ok($cqp, "CWB::CQP");

$cqp->set_error_handler('ignore'); # silently ignore errors so we can test them explicitly
my ($res, $n);

# traditional mode: input/output are octet sequences
$cqp->exec("GOETHE_UTF8");
ok($cqp->ok, "activate corpus GOETHE_UTF8 (raw mode)");
$cqp->exec('La = "La.*"');
($res) = $cqp->exec("tabulate La match word");
is($res, encode("UTF-8", "Laß"), "read UTF-8 data as octet stream");
$cqp->exec(encode("UTF-8", 'Lass = "Laß"'));
($n) = $cqp->exec("size Lass");
ok($n == 1, "execute query as UTF-8 octet stream");

$cqp->exec("GOETHE_LATIN1");
ok($cqp->ok, "activate corpus GOETHE_LATIN1 (raw mode)");
$cqp->exec('La = "La.*"');
($res) = $cqp->exec("tabulate La match word");
is($res, encode("ISO-8859-1", "Laß"), "read Latin1 data as octet stream");
$cqp->exec(encode("ISO-8859-1", 'Lass = "Laß"'));
($n) = $cqp->exec("size Lass");
ok($n == 1, "execute query as Latin1 octet stream");

# managed mode: input/output are Perl Unicode strings
$cqp->activate("GOETHE_UTF8");
ok($cqp->ok, "activate corpus GOETHE_UTF8 (managed mode)");
($res) = $cqp->exec("tabulate La match word");
is($res, "Laß", "read UTF-8 data as Perl Unicode string");
$cqp->exec('Lass2 = "Laß"');
($n) = $cqp->exec("size Lass2");
ok($n == 1, "execute query as Perl Unicode string");

$cqp->activate("GOETHE_LATIN1");
ok($cqp->ok, "activate corpus GOETHE_LATIN1 (managed mode)");
($res) = $cqp->exec("tabulate La match word");
is($res, "Laß", "read Latin1 data as Perl Unicode string");
$cqp->exec('Lass2 = "Laß"');
($n) = $cqp->exec("size Lass2");
ok($n == 1, "execute query as Perl Unicode string");

# invalid characters should be converted to hex codes
$cqp->exec('AO = "αω"');
ok(!$cqp->ok, "invalid characters are sent as hex escapes to CQP");
like(join(" ", $cqp->error_message), qr/\\x\{03b1\}/i, "invalid characters are sent as hex escapes to CQP");

# bad things happen if you switch corpus encoding without telling managed mode about it
$cqp->exec("GOETHE_UTF8");
($res) = $cqp->exec("tabulate La match word");
is($res, "La\x{c3}\x{9f}", "incorrect string decoding after changing corpus without activate()");

$cqp->exec('Lass3 = "Laß"');
($n) = $cqp->exec("size Lass3");
ok($n == 0, "incorrect string encoding after changing corpus without activate()");

# disable managed mode to return to byte semantics
$cqp->activate(undef);
($res) = $cqp->exec("tabulate La match word");
is($res, encode("UTF-8", "Laß"), "return to byte semantics");




