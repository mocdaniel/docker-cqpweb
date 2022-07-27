# -*-cperl-*-
## Test new features in the CWB::CEQL grammar

use Test::More tests => 131;
use CWB::CEQL;
use CWB;

our $grammar = new CWB::CEQL;
isa_ok($grammar, "CWB::CEQL"); # T1


## case/diacritic flags are now available for all attributes
## (i.e. also for pos, simple_pos and XML tags)
$grammar->SetParam("simple_pos", { # -- example from CEQL manpage
                                  "N" => "NN.*", # common nouns
                                  "V" => "V.*",  # any verb forms
                                  "A" => "JJ.*", # adjectives
                                 });
$grammar->SetParam("s_attributes", {"s" => 1, "ne" => 1});
$grammar->SetParam("default_ignore_case", 1); # with old-style defaults
$grammar->SetParam("default_ignore_diac", 0);

check_flags('under*%s', "word_or_lemma_constraint", 1, 0, # T2
            'word="under.*"%s');
check_flags('{under*}%s', "word_or_lemma_constraint", 1, 0, # T11
            'lemma="under.*"%s');
check_flags('VV+%s', "pos_constraint", 0, 0, # T20
            'pos="VV.+"%s');
check_flags('{A}%s', "pos_constraint", 0, 0, # T29
            'pos="JJ.*"%s');
check_flags('<ne=PERSON%s>', "xml_tag", 0, 0, # T38
            '<ne="PERSON"%s>');

$grammar->SetParam("ignore_case", { qw(word_attribute 0  lemma_attribute 0  pos_attribute 1  simple_pos_attribute 1  s_attributes 0) }); # with per-attribute defaults
$grammar->SetParam("ignore_diac", { qw(word_attribute 1  lemma_attribute 0  pos_attribute 0  simple_pos_attribute 1  s_attributes 1) });

check_flags('under*%s', "word_or_lemma_constraint", 0, 1, # T47
            'word="under.*"%s');
check_flags('{under*}%s', "word_or_lemma_constraint", 0, 0, # T56
            'lemma="under.*"%s');
check_flags('VV+%s', "pos_constraint", 1, 0, # T65
            'pos="VV.+"%s');
check_flags('{A}%s', "pos_constraint", 1, 1, # T74
            'pos="JJ.*"%s');
check_flags('<ne=PERSON%s>', "xml_tag", 0, 1, # T83
            '<ne="PERSON"%s>');

$grammar->SetParam("ignore_case", {}); # return to old-style defaults
$grammar->SetParam("ignore_diac", {});

check_query('{under*}:d_N+:c', "token_expression", # check multiple flags in full token expression
            '[lemma="under.*"%cd & pos="N.+"%c]'); # T92
check_query('{under*}:C_N+:d', "token_expression",
            '[lemma="under.*" & pos="N.+"%d]');
check_query('under*:cD_{A}:c', "token_expression",
            '[word="under.*"%c & pos="JJ.*"%c]');
check_query('<s=\D:c> but:C', "phrase_query",
            '<s="\pN+"%c> [word="but"]');
check_error('{bath}:c_NN:u', "token_expression",
            qr/invalid flag/);
check_error('{bath}:u_{A}:c', "token_expression",
            qr/invalid flag/);
check_error('<s=\D:cu> +', "phrase_query",
            qr/invalid flag/);


## all wildcard constraints can be negated with leading "!"
check_query('{![be,have]}_{V}', "token_expression", # T99
            '[lemma!="(be|have)"%c & pos="V.*"]');  # NB: still default to case-insensitive matching
check_query('can_!MD', "token_expression",
            '[word="can"%c & pos!="MD"]');
check_query('{light}:C_{!A}', "token_expression",
            '[lemma="light" & pos!="JJ.*"]');
check_query('{!}', "token_expression", # backward compatibiliy
            '[lemma="!"%c]');
check_query('!!!:C', "token_expression", # weird boundary cases
            '[word!="!!"]');
check_query('{!?}', "token_expression",
            '[lemma!="."%c]');
check_query('<ne=!PERS*>', "xml_tag",
            '<ne!="PERS.*">');
check_query('<ne=!PERS*:cd>', "xml_tag",
            '<ne!="PERS.*"%cd>');
check_error('!{negation}_{N}', "token_expression",
            qr/curly brace/);
check_error('negation_!{N}', "token_expression",
            qr/curly brace/);
check_error('<ne!=PERSON>', "phrase_query",
            qr/invalid XML tag/);

## numbered target markers
foreach my $n (0 .. 9) { # T110
  check_query("the:C \@old:C \@$n:dog:C", "phrase_query",
              "[word=\"the\"] \@[word=\"old\"] \@$n:[word=\"dog\"]");
}

## TAB query optimisation
$grammar->SetParam("default_ignore_case", 0); # simplify queries
$grammar->SetParam("tab_optimisation", 1);

check_query('coffee', "ceql_query", # T120
            'TAB [word="coffee"]');
check_query('_NN', "ceql_query",
            'TAB [pos="NN"]');
check_query('{cat} + {dog}', "ceql_query",
            'TAB [lemma="cat"] {1} [lemma="dog"]');
check_query('{cat} * {dog}', "ceql_query",
            'TAB [lemma="cat"] ? [lemma="dog"]');
check_query('{cat} ++ {dog}', "ceql_query",
            'TAB [lemma="cat"] {2} [lemma="dog"]');
check_query('{cat} ++**** {dog}', "ceql_query", # T125
            'TAB [lemma="cat"] {2,6} [lemma="dog"]');
check_query('the ** _JJ* * {dog}_NN', "ceql_query",
            'TAB [word="the"] {0,2} [pos="JJ.*"] ? [lemma="dog" & pos="NN"]');
check_query('+ coffee', "ceql_query",
            '[] [word="coffee"]', "TAB query cannot start with gap");
check_query('coffee *', "ceql_query",
            '[word="coffee"] []?', "TAB query cannot end with gap");
check_query('{cat} + * {dog}', "ceql_query",
            '[lemma="cat"] [] []? [lemma="dog"]', "TAB query cannot have multiple consecutive gaps");
check_query('(?longest) {cat} *** {dog}', "ceql_query", # T130
            '(?longest) [lemma="cat"] []{0,3} [lemma="dog"]', "TAB query cannot have (?longest) modifier");
check_query('(?standard) {cat} *** {dog}', "ceql_query",
            'TAB [lemma="cat"] {0,3} [lemma="dog"]', "but (?standard) modifier allowed in TAB query");

# -- 131 tests


## helper routines for testing automatic translation results (runs 9 tests)
##  - $query_pattern and $expected_pattern are sprintf patterns with a single
##    %s placeholder where the CEQL or CQP flags will be inserted
sub check_flags {
  my ($query_pattern, $rule, $dflt_case, $dflt_diac, $expected_pattern) = @_;
  foreach my $flag_c ("", "c", "C") {
    my $case = $dflt_case;
    $case = 1 if $flag_c eq "c";
    $case = 0 if $flag_c eq "C";
    foreach my $flag_d ("", "d", "D") {
      my $diac = $dflt_diac;
      $diac = 1 if $flag_d eq "d";
      $diac = 0 if $flag_d eq "D";
      my $ceql_flags = ($flag_c || $flag_d) ? ":".$flag_c.$flag_d : "";
      my $cqp_flags = ($case || $diac) ? '%'.($case ? "c" : "").($diac ? "d" : "") : "";
      my $query = sprintf $query_pattern, $ceql_flags;
      my $expected = sprintf $expected_pattern, $cqp_flags;
      my $msg = sprintf "parse ``%s'' as %s (default: case=%d diac=%d)", $query, $rule, $dflt_case, $dflt_diac;
      check_query($query, $rule, $expected, $msg);
    }
  }
}

sub check_query {
  my ($query, $rule, $expected, $msg) = @_;
  $msg = "parse ``$query'' as $rule" unless defined $msg;
  my $result = $grammar->Parse($query, $rule);
  if (defined $result) {
    is($result, $expected, $msg);
  }
  else {
    fail($msg);
    foreach ($grammar->ErrorMessage) { diag($_) };
  }
}

sub check_error {
  my ($query, $rule, $err_regexp) = @_;
  my $msg = "find syntax error in ``$query'' as $rule";
  my $result = $grammar->Parse($query, $rule);
  if (defined $result) {
    fail($msg);
  }
  else {
    like(join("\n",$grammar->ErrorMessage), $err_regexp, $msg);
  }
}
