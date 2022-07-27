#!/usr/bin/perl

## This script compiles a lemma frequency list of all lemmas
## occurring in <title> regions and prints the first 20 entries.

use strict;
use warnings;

use CWB::CL::Strict;

my $C = new CWB::CL::Corpus "DICKENS";
my $Lemma = $C->attribute("lemma", "p");
my $Title = $C->attribute("title", "s");

my $n_titles = $Title->max_struc;
my %F = ();

foreach my $i (0 .. ($n_titles - 1)) {
  my ($start, $end) = $Title->struc2cpos($i);
  foreach my $lemma ($Lemma->cpos2str($start .. $end)) {
    $F{$lemma}++;
  }
}

my @lemmas = sort {$F{$b} <=> $F{$a}} keys %F;
foreach my $lemma (@lemmas[0 .. 19]) {
  printf "%8d %s\n", $F{$lemma}, $lemma;
}
