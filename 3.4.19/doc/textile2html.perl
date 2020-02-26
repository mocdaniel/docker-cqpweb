#!/usr/bin/perl
# -*-cperl-*-
## Creates HTML versions of all Textile documents in the current directory.
$| = 1;

use warnings;
use strict;

use Text::Textile qw(textile);
use File::Slurp;

our @Infiles = glob "*.textile";
die "Error: no .textile files found in current working directory.\n"
  unless @Infiles;

foreach my $infile (@Infiles) {
  my $outfile = $infile;
  $outfile =~ s/\.textile$/.html/
    or die "Internal error: can't generate .html filename for '$infile'.\n";
  print "$infile ";
  if (-f $outfile) {
    if (-M $outfile <= -M $infile) {
      print "skipped (.html is up to date)\n";
      next;
    }
    unlink($outfile);
  }
  my $text = read_file($infile) 
    or die "ERROR reading file '$infile': $!";
  my $html = textile($text);
  write_file($outfile, $html)
    or die "ERROR writing file '$outfile': $!";
  print "--> $outfile\n";
}
