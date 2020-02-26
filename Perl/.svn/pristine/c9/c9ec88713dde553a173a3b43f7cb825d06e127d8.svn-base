#!/usr/bin/perl
## -*-cperl-*-
## This script can be used to benchmark old CL modules against new vectorised CWB::CL
## Prerequisites:
##   - new CWB::CL interface must be installed
##   - old CL interface also has to be installed (NOT the compatibility package)
##   - demo corpus DICKENS must be installed
$| = 1;

use warnings;
use strict;

use Time::HiRes qw(time);
use CL;
use CWB::CL;

our $STEP = 1000; # distance between (start positions of) chunks
our $CHUNK = 200; # chunk size

print "Accessing corpus DICKENS ... ";
our $oldC = new CL::Corpus "DICKENS"
  or die "Can't access corpus DICKENS [CL]\n";
our $oldW = $oldC->attribute("word", "p")
  or die "Can't access attribute DICKENS.word [CL]\n";
our $oldSize = $oldW->max_cpos
  or die "Data error: no tokens in corpus [CL]\n";

our $newC = new CWB::CL::Corpus "DICKENS"
  or die "Can't access corpus DICKENS [CWB::CL]\n";
our $newW = $newC->attribute("word", "p")
  or die "Can't access attribute DICKENS.word [CWB::CL]\n";
our $newSize = $newW->max_cpos
  or die "Data error: no tokens in corpus [CWB::CL]\n";

die "Data error: inconsistent corpus sizes $oldSize [CL] vs. $newSize [CWB::CL]\n"
  unless $oldSize == $newSize;
print "ok\n";

my ($old_data, $new_data, $time, $tokens, $speed);
print "Warming cache ... ";
($speed, $time, $tokens, $old_data) = benchmark($oldW, $oldSize);
print "CL ";
($speed, $time, $tokens, $new_data) = benchmark($newW, $newSize);
print "CWB::CL\n";

print "Validating ... ";
my $old_items = @$old_data;
my $new_items = @$new_data;
die "ERROR: sample sizes differ ($old_items [CL] vs. $new_items [CWB::CL])\n"
  unless $new_items == $old_items;
foreach my $i (0 .. $old_items-1) {
  die "ERROR: $i-th sample items differ\n"
    unless $old_data->[$i] == $new_data->[$i];
}
print "ok\n";

print "CL:     ";
($speed, $time, $tokens) = benchmark($oldW, $oldSize);
printf "%7.1fk tokens/s  (%.0fk tokens in %.2f s)\n", ($tokens / 1024) / $time, $tokens / 1024, $time;

print "CWB::CL:";
($speed, $time, $tokens) = benchmark($newW, $newSize);
printf "%7.1fk tokens/s  (%.0fk tokens in %.2f s)\n", ($tokens / 1024) / $time, $tokens / 1024, $time;



## ($speed, $time, $items, $sample_data) = benchmark($attribute, $corpus_size);
sub benchmark {
  my ($attribute, $corpus_size) = @_;
  my @sample_data = ();
  my $T0 = time;
  my $tokens = 0;
  for (my $start = 0; $start < $corpus_size - $CHUNK; $start += $STEP) {
    my @id = $attribute->cpos2id($start .. $start+$CHUNK-1);
    push @sample_data, $id[$CHUNK / 2]; # sample middle token from each chunk
    $tokens += $CHUNK;
  }
  my $dT = time - $T0;
  return $tokens / $dT, $dT, $tokens, \@sample_data;
}