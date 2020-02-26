# -*-cperl-*-
## Test expected syntax errors in CQP queries

use Test::More tests => 29;

use CWB::CQP;

our $cqp = new CWB::CQP "-r data/registry", "-I data/files/init.cqp";
isa_ok($cqp, "CWB::CQP") # T1
  or BAIL_OUT("failed to start up CQP backend");

$cqp->set_error_handler('ignore'); # so we can explicitly check errors

$cqp->exec("VSS");
ok($cqp->ok, "activate VSS corpus") # T2
  or BAIL_OUT("failed to activate VSS corpus");

# syntax_error($query, $label, [qr/error template/]);
sub syntax_error {
  my ($query, $label, $regex) = @_;
  $cqp->exec($query);
  my $ok = !$cqp->ok;
  if ($ok && $regex) {
    $ok = 0 unless join(" ", $cqp->error_message) =~ $regex;
  }
  ok($ok, $label);
}

# some basic syntax errors
syntax_error("A = 'the' ()* 'elephant'", "empty parentheses", qr/syntax error/i); # T3
syntax_error("A = 'the' [pos='NNS?']?? 'elephant'", "double quantifier", qr/syntax error/i);
syntax_error("A = 'the' (?: [pos='NNS?'])* 'elephant'", "non-capturing group", qr/syntax error/i);

# invalid repetition counts should raise errors (rather than give wrong results or crash CQP)
for my $R (-1, -2, -42) { # T6–T8
  syntax_error("A = 'the' []{$R} 'elephant'", "negative repetition count {$R}", qr/non-negative/i);
}

for my $R (-1, -2) { # T9–T10
  syntax_error("A = 'the' []{0,$R} 'elephant'", "invalid endpoint of repetition range {0,$R}", qr/non-negative/i);
}

for my $R (1, 2, 3) { # T11–T13
  my $R1 = $R - 1;
  syntax_error("A = 'the' []{$R,$R1} 'elephant'", "invalid repetition range {$R,$R1}", qr/invalid.*range/i);
}

# correspondingly for TAB queries
for my $R (-1, -2, -42) { # T14–T16
  syntax_error("A = TAB 'the' []{$R} 'elephant'", "negative distance {$R} in TAB query", qr/non-negative/i);
}

for my $R (-1, -2) { # T17–T18
  syntax_error("A = TAB 'the' []{0,$R} 'elephant'", "invalid endpoint of distance range {0,$R} in TAB query", qr/non-negative/i);
}

for my $R (-1, -2) { # T19–T20
  syntax_error("A = TAB 'the' []{,$R} 'elephant'", "invalid endpoint of distance range {,$R} in TAB query", qr/non-negative|invalid.*range/i);
}

for my $R (1, 2, 3) { # T21–T23
  my $R1 = $R - 1;
  syntax_error("A = TAB 'the' []{$R,$R1} 'elephant'", "invalid distance range ($R,$R1) in TAB query", qr/invalid.*range/i);
}

# unsupported inequality comparisons for strings and regexp raise errors in CQP v3.4.17+
SKIP: {
  skip "unsupported inequality comparisons are only caught in CQP v3.4.17 and newer", 6 unless $cqp->check_version(3, 4, 17);
  syntax_error("A = [lemma >= 'elephant']", "inequality comparison >= for p-attribute", qr/inequality/i); # T24
  syntax_error("A = [lemma < 'elephant']", "inequality comparison < for p-attribute", qr/inequality/i);
  syntax_error("A = [lemma > 'el.*' \%c]", "inequality comparison > for regular expression", qr/inequality/i);
  syntax_error("A = [lemma = 'elephant'] :: match.story_title <= 'ACME'", "inequality comparison <= for s-attribute", qr/inequality/i);
  syntax_error("A = [lemma < word]", "inequality comparison < between p-attributes", qr/inequality/i);
  syntax_error("A = [normalize(lemma, 'c') >= normalize(word, 'c')]", "inequality comparison >= between strings", qr/inequality/i); # T29
}