# -*-cperl-*-
## Test CWB::CEQL::Parser with examples from manual page / tutorial

use Test::More tests => 26;
use CWB::CEQL::Parser;

our $grammar = new SimpleQuery;
isa_ok($grammar, "SimpleQuery"); # T1

check_query("good",             # T2
            '"good"%l');
check_query("good old friend",
            '"good"%l "old"%l "friend"%l'); 
check_query("good old friend",
            '"good"%l "old"%l "friend"%l', "wordform_sequence");
check_query("good | old friend",
            '"good"%l "|"%l "old"%l "friend"%l', "wordform_sequence");
check_query("imp?ss?ble",
            '"imp.ss.ble"');
check_query("super*istic+ous",
            '"super.*istic.+ous"');
check_query("( good | bad )   result*",
            '("good"%l | "bad"%l) "result.*"');
check_query("from ( ( /an?/ | the ) ( very | /happ(y|i(er|est))/ | +ly )* )? time",
            '"from"%l (("an?" | "the"%l) ("very"%l | "happ(y|i(er|est))" | ".+ly")*)? "time"%l');

check_error("/good",            # T10
            qr/regular expression/);
check_error("a good/ man ",     # check for automatic formatting in traceback
            qr{<code>.*</code><b>&lt;==});
check_error('a " happy " time',
            qr/must not contain.*double quotes/);
check_error("good old friend",
            qr/must not contain.*whitespace/, "wordform_pattern");
check_error("good | bad result",
            qr/alternatives.*must be enclosed/);
check_error("from ( ( /an?/ | the ) ( very | /happ(y|i(er|est))/ | +ly )* )? )+ time",
            qr/too many closing delimiters/);
check_error("from ( ( /an?/ | the ( very | /happ(y|i(er|est))/ | +ly )* )? time",
            qr/too many opening delimiters/);

## replace $grammar with Arithmetic parser for remaining tests
$grammar = new Arithmetic;
isa_ok($grammar, "Arithmetic");    # T17

check_query('1+1',                 # T18
            'add(1, 1)');
check_query('4-3 + 2 -1',
            'sub(add(sub(4, 3), 2), 1)');
check_query('42 - (9 - 6)',
            'sub(42, sub(9, 6))');
check_query('(40 + 2) - (9 - 6)',
            'sub(add(40, 2), sub(9, 6))');
check_error('5 + 3 * 2',
            qr/invalid element/);
check_error('(40 + 2 - (9 - 6)',
            qr/bracketing.*not balanced.*opening.*subexpression/);
check_error('2 + - 3',
            qr/syntax error/);
check_error('- 4 - 10',
            qr/syntax error/);
check_error('42 +',
            qr/syntax error/);

# next test is T27

## helper routines for testing automatic translation results
sub check_query {
  my ($query, $expected, $rule) = @_;
  my $result = undef;
  my $msg = "parse string ``$query''";
  if (defined $rule) {
    $result = $grammar->Parse($query, $rule);
    $msg .= " as $rule";
  }
  else {
    $result = $grammar->Parse($query);
  }
  if (defined $result) {
    is($result, $expected, $msg);
  }
  else {
    fail($msg);
    foreach ($grammar->ErrorMessage) { diag($_) };
  }
}

sub check_error {
  my ($query, $err_regexp, $rule) = @_;
  my $result = undef;
  my $msg = "find syntax error in string ``$query''";
  if (defined $rule) {
    $result = $grammar->Parse($query, $rule);
    $msg .= " as $rule";
  }
  else {
    $result = $grammar->Parse($query);
  }
  if (defined $result) {
    fail($msg);
  }
  else {
    like($grammar->HtmlErrorMessage, $err_regexp, $msg);
  }
}


########## BEGIN 'SimpleQuery' grammar (from CWB::CEQL::Parser manpage)

package SimpleQuery;
use base 'CWB::CEQL::Parser';

sub wildcard_expression {
  my ($self, $input) = @_;
  return _wildcard_to_regexp($input);
}

# note leading underscore for internal subroutine (this is not a method!)
sub _wildcard_to_regexp {
  my $s = quotemeta(shift);
  $s =~ s/\\[?]/./g;  # wildcards will also have been escaped with a backslash
  $s =~ s/\\([*+])/.$1/g;  # works for wildcards * and +
  return $s;
}

sub wordform_pattern {
  my ($self, $input) = @_;
  die "the wordform pattern ''$input'' must not contain whitespace or double quotes\n"
    if $input =~ /\s|\"/;
  if ($input =~ /^\/(.+)\/$/) {
    my $regexp = $1; # regular expression query: simply wrap in double quotes
    return "\"$regexp\"";
  }
  elsif ($input =~ /^\/|\/$/) {
    die "missing ''/'' at start/end of pattern: did you intend to use a regular expression?\n";
  }
  else {
    if ($input =~ /[?*+]/) {
      my $regexp = $self->Call("wildcard_expression", $input); # call subrule
      return "\"$regexp\"";
    }
    else {
      return "\"$input\"\%l";
    }
  }
}

sub wordform_sequence {
  my ($self, $input) = @_;
  my @items = split " ", $input;
  my @cqp_patterns = $self->Apply("wordform_pattern", @items);
  return "@cqp_patterns";
}

sub simple_query {
  my ($self, $input) = @_;
  my @items = split " ", $input;
  my @cqp_tokens = $self->Apply("simple_query_item", @items);
  return "@cqp_tokens";
}

# need to define single rule to parse all items of a list with nested bracketing
sub simple_query_item {
  my ($self, $item) = @_;
  # opening delimiter: (
  if ($item eq "(") {
    $self->BeginGroup();
    return "";  # opening delimiter should not become part of group output
  }
  # alternatives separator: | (only within nested group)
  elsif ($item eq "|") {
    die "a group of alternatives (|) must be enclosed in parentheses\n"
      unless $self->NestingLevel > 0; # | metacharacter is not allowed at top level
    return "|";
  }
  # closing delimiter: ) with optional quantifier
  elsif ($item =~ /^\)([?*+]?)$/) {
    my $quantifier = $1;
    my @cqp_tokens = $self->EndGroup();
    die "empty groups '( )' are not allowed\n"
      unless @cqp_tokens > 0;
    return "(@cqp_tokens)$quantifier";
  }
  # all other tokens should be wordform patterns
  else {
    return $self->Call("wordform_pattern", $item);
  }
}

sub default {
  my ($self, $input) = @_;
  $self->Call("simple_query", $input);
}

########## END   'SimpleQuery' grammar


########## BEGIN 'Arithmetic' grammar (from CWB::CEQL::Parser manpage)

package Arithmetic;
use base 'CWB::CEQL::Parser';
use CWB::CEQL::String;

sub default {
  my ($self, $input) = @_;
  return $self->Call("arithmetic_expression", $input);
}

sub arithmetic_expression {
  my ($self, $input) = @_;
  $input =~ s/([()+-])/ $1 /g;            # insert whitespace around metacharacters
  $input =~ s/^\s+//; $input =~ s/\s+$//; # strip leading/trailing whitespace
  my @items = split " ", $input;          # split on whitespace into items (numbers, operators, parentheses)
  my @terms_ops = $self->Apply("arithmetic_item", @items); # returns list of Term's and Op's
  return $self->_shift_reduce(@terms_ops);
}

sub arithmetic_item {
  my ($self, $item) = @_;
  if ($item eq "+")    { return new CWB::CEQL::String "add", "Op" }
  elsif ($item eq "-") { return new CWB::CEQL::String "sub", "Op" }
  elsif ($item eq "(") { $self->BeginGroup("subexpression"); return "" }
  elsif ($item eq ")") {
    my @terms_ops = $self->EndGroup("subexpression");
    return $self->_shift_reduce(@terms_ops);
  }
  elsif ($item =~ /^[0-9]+$/) { return new CWB::CEQL::String $item, "Term" }
  else { die "invalid element '' $item '' in arithmetic expression\n" }
}

sub _shift_reduce {
  my ($self, @terms_ops) = @_;
  while (@terms_ops >= 3) {
    # reduce first three items (which must be Term Op Term) to single Term
    my @types = map {$_->type} @terms_ops;
    die "syntax error in arithmetic expression\n"
      unless "@types" =~ /^Term Op Term/; # wrong sequence of terms and operators
    my $t1 = shift @terms_ops;
    my $op = shift @terms_ops;
    my $t2 = shift @terms_ops;
    my $new_term = new CWB::CEQL::String "$op($t1, $t2)", "Term";
    unshift @terms_ops, $new_term;
  }
  die "syntax error in arithmetic expression\n"
    unless @terms_ops == 1;     # wrong number of items
  return shift @terms_ops;
}

########## END   'SimpleQuery' grammar

package main;

