use Test::More tests => 97;
## run some tests on (very small) UTF-8 encoded corpus

use utf8;
use strict;
use warnings;

use Encode;
use CWB::CL;

$CWB::CL::Registry = "data/registry"; # use local copy of HOLMES-DE corpus

our $C = new CWB::CL::Corpus "HOLMES-DE";      # -- access corpus and word attribute
isa_ok($C, "CWB::CL::Corpus", "corpus object for HOLMES-DE corpus") # T1
  or BAIL_OUT("failed to access corpus HOLMES-DE");
our $C2 = new CWB::CL::Corpus "HOLMES-LATIN1";
isa_ok($C2, "CWB::CL::Corpus", "corpus object for HOLMES-LATIN1 corpus") # T2
  or BAIL_OUT("failed to access corpus HOLMES-LATIN1");

our $Word = $C->attribute("word", "p");
isa_ok($Word, "CWB::CL::PosAttrib", "attribute object for HOLMES-DE.word") # T3
  or BAIL_OUT("failed to access attribute HOLMES-DE.word");
our $Word2 = $C2->attribute("word", "p");
isa_ok($Word2, "CWB::CL::PosAttrib", "attribute object for HOLMES-LATIN1.word") # T4
  or BAIL_OUT("failed to access attribute HOLMES-LATIN1.word");

our $n_types = $Word->max_id;
diag("HOLMES-DE contains $n_types distinct word forms");
our $n_types_2 = $Word2->max_id;
ok($n_types = $n_types_2, "UTF-8 and Latin1 encodings are consistent"); # T5

our @wordlist = map {decode("utf8", $_)} $Word->id2str(0 .. $n_types-1);

## validate PCRE regexp in CWB against same regexp in Perl
##  - each function call executes 2 tests, with and without cl_optimize
##  - if a third argument is passed (and has a true value), the results are compared
##    with a Latin1 version of the corpus (executing two additional tests)
sub validate_regexp {
  my $regexp = shift;
  my $casefold = shift || "";
  my $do_latin1 = shift || 0;
  my $perl_flags = ($casefold) ? "(?i)" : "";
  my $cwb_flags = ($casefold) ? "c" : "";

  my $perl_regexp = qr/^${perl_flags}(?:${regexp})$/;
  our @perl_id = grep { $wordlist[$_] =~ $perl_regexp } 0 .. $#wordlist;
  # -- enable diag() in order to identify discrepancies
  # diag("PERL:    ", join(" ", map {encode("utf8", $_)} @wordlist[@perl_id]), "\n");
  
  foreach my $enc (qw(UTF-8 Latin1)) {
    my $Att = ($enc eq "Latin1") ? $Word2 : $Word;
    next if ($enc eq "Latin1" && !$do_latin1);
    foreach my $optimize (0, 1) {
      CWB::CL::set_optimize($optimize);
      my $opt_status = $optimize ? "+opt" : "";
      my @cwb_id = $Att->regex2id(encode($enc, $regexp), $cwb_flags);
      # -- enable diag() in order to identify discrepancies
      # diag(": ", join(" ", $Word->id2str(@cwb_id)), "\n");
      is_deeply(\@cwb_id, \@perl_id, "validate regexp /$regexp/ $casefold ($enc) $opt_status");
    }
  }
  CWB::CL::set_optimize(0);
}

validate_regexp('[a-z]+', '', 1); # T6 (each of the following lines runs 4 tests)
validate_regexp('\PL+', '', 1);
validate_regexp('\p{Lu}\p{Ll}+', '', 1);
validate_regexp('.*[aeiouäöü]{2}.*', '', 1);
validate_regexp('.*(\pL).*\1.*\1.*', '', 1);
validate_regexp('z\w+', '', 1); # make sure that \w is set up to use Unicode props in PCRE

## test case-insensitive matching (which was broken after switching to PCRE in CWB v3.4.x)
validate_regexp('[a-z]+', '%c', 1); # T30
validate_regexp('[A-Z]+', '%c', 1); 
validate_regexp('[a-z]+che[nr]', '%c', 1);
validate_regexp('\pL+', '%c', 1); # CWB's old case-folding would turn \pL into \pl (syntax error)
validate_regexp('\PL+', '%c', 1); 
validate_regexp('sa.', '%c', 1);  # CWB's old case-folding turned German ß into ss, so /sa./ doesn't match "saß"
validate_regexp('sa..', '%c', 1); # this shouldn't match anything
validate_regexp('da.', '%c', 1);  # should match "das" and "daß" (not in corpus), but not "dass"
validate_regexp('(?!dass)daß', '%c', 1);  # Perl identifies ß and ss in case-insensitive mode, but PCRE doesn't
validate_regexp('(?!dass)daß?', '%c', 1); # CWB used to match "dass" and "das" instead of "da"

## test validity of regexp optimizer (must not produce false negatives)
validate_regexp('\w*stück\w*', '%c', 1); # T70
validate_regexp('\p{Lu}\pL+stück[a-z]+', '', 1); # "Frühstückstisch"
validate_regexp('\p{Ll}+stück[a-z]+', '', 1); # should not match


## case-folding doesn't currently work for legacy encodings
SKIP: {
  skip "because case-folding non-ASCII characters only works in UTF-8", 8;
  validate_regexp('[ÄÖÜ].*', '', 1); # T82
  validate_regexp('.*[ÄÖÜ].*', '%c', 1);
}

## discrepancies between Perl and PCRE
SKIP: {
  skip "because there are known differences between PCRE and Perl regexp", 8;
  validate_regexp('\p{Ll}+stück[A-Z]+', '%c', 1); # T90: doesn't match "Frühstückstisch" in PCRE
  validate_regexp('daß', '%c', 1);  # Perl identifies ß and ss in case-insensitive mode, but PCRE doesn't  
}

