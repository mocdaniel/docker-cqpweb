# -*-cperl-*-
## Test automatic corpus encoding and indexing with CWB::Encoder

use Test::More;

use CWB::CQP;

if ($CWB::CWBVersion >= 3.004_014) {
  plan tests => 9;
}
else {
  plan skip_all => "need CWB 3.4.14 or newer for CRLF support";
}

our $cqp = new CWB::CQP "-r data/registry", "-I data/files/init.cqp";
isa_ok($cqp, "CWB::CQP"); # T1

$cqp->set_error_handler('die'); # any CQP errors in this script are major problems and should result in test failure

$cqp->exec("VSS");
ok($cqp->ok, "activate VSS corpus"); # T2

## test whether word lists in CRLF encoding are read correctly
$cqp->exec('define $words < "data/files/wordlist.txt"');
$cqp->exec('A = [lemma = $words]');
my ($N) = $cqp->exec("size A");
is($N, 42, "wordlist (LF) matches correctly"); # T3

$cqp->exec('define $crlf < "data/files/wordlist_crlf.txt"');
$cqp->exec('B = [lemma = $crlf]');
($N) = $cqp->exec("size B");
is($N, 42, "wordlist (CRLF) matches correctly"); # T4

$cqp->exec('C = [lemma = RE($crlf)]');
($N) = $cqp->exec("size C");
is($N, 42, "wordlist (CRLF) matches correctly with RE()"); # T5

## test whether CQP scripts in CRLF encoding are executed correctly
my @out = ();
$CWB::Shell::Paranoid = -1;

my $ok = CWB::Shell::Cmd([$CWB::CQP, "-f", "data/files/cqp_script.txt"], \@out);
ok($ok == 0, "CQP script (LF) executes"); # T6
is_deeply(\@out, ["14"], "CQP script (LF) produces correct output"); # T7

$ok = CWB::Shell::Cmd([$CWB::CQP, "-f", "data/files/cqp_script_crlf.txt"], \@out);
ok($ok == 0, "CQP script (CRLF) executes"); # T8
is_deeply(\@out, ["14"], "CQP script (CRLF) produces correct output"); # T9
