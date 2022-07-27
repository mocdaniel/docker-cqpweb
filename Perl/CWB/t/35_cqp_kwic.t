# -*-cperl-*-
## Test CQP kwic output features

use Test::More tests => 19;

use CWB::CQP;

our $cqp = new CWB::CQP "-r data/registry", "-I data/files/init.cqp";
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

  $cqp->exec("set AttributeSeparator ''");
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^<\w+/\w+/\w+>$}, "reset default attribute separator (set AttributeSeparator '';)"); # T7
}

# user-defined separators for tokens in kwic display (new in CQP v3.4.24) T8-12
SKIP: {
  skip "TokenSeparator option only available in CQP v3.4.24 and newer", 5 unless $cqp->check_version(3, 4, 24);

  $cqp->exec("set Context 1 word");
  $cqp->exec("show -pos -lemma");
  my ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^\w+ <\w+> \w+$}, "default token separator is space"); # T8

  $cqp->exec("set TokenSeparator '_'");
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^\w+_<\w+>_\w+$}, "change token separator to _"); # T9

  $cqp->exec("set TokenSeparator '(^.^)'");
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^\w+\(\^.\^\)<\w+>\(\^.\^\)\w+$}, "multi-character token separator"); # T10

  $cqp->exec("set TokenSeparator '\x07'"); # rings a bell
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^\w+\x07<\w+>\x07\w+$}, "funky token separator (BEL)"); # T11

  $cqp->exec("set TokenSeparator ''");
  ($line) = $cqp->exec("cat A 0 0");
  like($line, qr{^\w+ <\w+> \w+$}, "reset default token separator (set TokenSeparator '';)"); # T12
}

# finally, the user-settable structure delimiter. (new in CQP v3.4.25) T13-T19
SKIP: {
  skip "StructureDelimiter option only available in CQP v3.4.25 and newer", 7 unless $cqp->check_version(3, 4, 25);

  $cqp->exec("set Context 2 words");
  $cqp->exec("show +p +story_author");
  $cqp->exec("B = 'constant'");
  my ($line) = $cqp->exec("cat B 0 0");
  like($line, qr{^<story_author [^>]+><p>\w+ <\w+>}, "default structure delimiter is <empty>"); # T13

  $cqp->exec("set StructureDelimiter '_'");
  ($line) = $cqp->exec("cat B 0 0");
  like($line, qr{^_<story_author [^>]+>__<p>_\w+ <\w+>}, "change structure delimiter to _"); # T14

  $cqp->exec("set StructureDelimiter '(^.^)'"); #\(\^\.\^\)
  ($line) = $cqp->exec("cat B 0 0");
  like($line, qr{^\(\^\.\^\)<story_author [^>]+>\(\^\.\^\)\(\^\.\^\)<p>\(\^\.\^\)\w+ <\w+>}, "multi-character structure delimiter"); # T15

  $cqp->exec("set StructureDelimiter '\x07'");
  ($line) = $cqp->exec("cat B 0 0");
  like($line, qr{^\x07<story_author [^>]+>\x07\x07<p>\x07\w+ <\w+>}, "funky structure delimiter (BEL)"); # T16

  $cqp->exec("set StructureDelimiter ''");
  ($line) = $cqp->exec("cat B 0 0");
  like($line, qr{^<story_author [^>]+><p>\w+ <\w+>}, "reset default structure delimiter (set StructureDelimiter '';)"); # T17

  # we repeat the empty / BEL test for a later bit of VSS that has an end tag
  $cqp->exec("C = 'temperature' 'of' 'the' 'water'");
  ($line) = $cqp->exec("cat C 0 0");
  like($line, qr{^\w+ \w+ <\w+ \w+ \w+ \w+> .</p> <p>\w\w}, "default structure delimiter is <empty>"); # T18

  $cqp->exec("set StructureDelimiter '\x07'");
  ($line) = $cqp->exec("cat C 0 0");
  $cqp->exec("set StructureDelimiter ''");
  like($line, qr{^\w+ \w+ <\w+ \w+ \w+ \w+> .\x07</p>\x07 \x07<p>\x07\w\w}, "funky structure delimiter (BEL)"); # T19
}



