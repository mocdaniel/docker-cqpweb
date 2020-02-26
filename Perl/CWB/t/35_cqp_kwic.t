# -*-cperl-*-
## Test CQP kwic output features

use Test::More tests => 7;

use CWB::CQP;

our $cqp = new CWB::CQP "-r data/registry";
isa_ok($cqp, "CWB::CQP"); # T1

$cqp->set_error_handler('die'); # any CQP errors in this script are major problems and should result in test failure

# user-defined separators for p-attributes in kwic display (new in CQP v3.4.18) T2-T7
SKIP: {
  skip "AttributeSeparator option only available in CQP v3.4.18 and newer", 6 unless $cqp->check_version(3, 4, 18);
  $cqp->exec("VSS");
  $cqp->exec("A = [lemma = 'elephant']");
  my ($n) = $cqp->exec("size A");
  ok($n == 16, "there must be 16 elephants in VSS"); # T2

  $cqp->exec("set Context 0");
  $cqp->exec("show -cpos");
  $cqp->exec("show +pos +lemma");
  my ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^<\w+/\w+/\w+>$}, "default attribute separator is /"); # T3

  $cqp->exec("set AttributeSeparator '_'");
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^<\w+_\w+_\w+>$}, "change attribute separator to _"); # T4

  $cqp->exec("set AttributeSeparator '(^.^)'");
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^<\w+\(\^.\^\)\w+\(\^.\^\)\w+>$}, "multi-character attribute separator"); # T5

  $cqp->exec("set AttributeSeparator '\x07'"); # rings a bell
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^<\w+\x07\w+\x07\w+>$}, "funky attribute separator (BEL)"); # T6

  $cqp->exec("set sep ''");
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^<\w+/\w+/\w+>$}, "reset default attribute separator (set sep '';)"); # T7
}