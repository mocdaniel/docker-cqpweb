#!/usr/bin/perl -w
# -*-cperl-*-
## A simple command-line front-end for CQP using the CQi client-server protocol

## Copyright (C) 1999-2001 by Stefan Evert
$| = 1;

use strict;
use warnings;

use CWB::CQI;
use CWB::CQI::Client;
use CWB::CQI::Server;

print "MicroCQP v0.1\n";

our ($user, $passwd, $host, $port);
if (@ARGV) {
  die "Usage:  MicroCQP.perl [<user> <password> [<host> [<port>]]]\n"
    unless @ARGV >=2 and @ARGV <= 4;
  $user = shift @ARGV;
  $passwd = shift @ARGV;
  $host = (@ARGV) ? shift @ARGV : "localhost";
  $port = (@ARGV) ? shift @ARGV : $CWB::CQI::PORT;
}
else {
  die "CQPserver binary is not available on local machine, please specify login details for remote server.\n",
      "Usage:  MicroCQP.perl <user> <password> [<host> [<port>]]\n"
    unless cqi_server_available();
    ($user, $passwd, $host, $port) = cqi_server();  # start our own local server
}

print "[connecting to $host on port $port]\n";
print "[user = '$user', password = '$passwd']\n";
cqi_connect($user, $passwd, $host, $port);
our %corpora = map {$_ => 1} cqi_list_corpora(); # corpus name lookup hash
print "Connected to server --- enter query or corpus change command:\n"; 

our $corpus = "";
our $HaveNCAtt = 0;
while (1) {
  if ($corpus) {
    print "$corpus> ";
  }
  else {
    print "[no corpus]> ";
  }
  my $query = <STDIN>;
  last unless defined $query;   # I _think_ that happens on Ctrl-D
  chomp $query;

  # quit command
  if ($query =~ /^\s*(exit|quit)\s*;?\s*$/) {
    last;
  }
  # show available corpora
  elsif ($query =~ /^\s*show\s*;?\s*$/) {
    print "System Corpora:\n";
    foreach my $c (cqi_list_corpora()) {
      print "\t$c\n";
    }
  }
  # corpus change command
  elsif ($query =~ /^\s*([A-Z_][A-Z0-9_-]*)\s*;?\s*$/) {
    $corpus = $1;
    print "Changing corpus to '$corpus' ... ";
    if ($corpora{$corpus}) {
      print "ok\n";
      # if there are <nc>..</nc> regions in the corpus, display them
      $HaveNCAtt = 0 < grep {$_ eq "np"} cqi_attributes($corpus, 's');
      print "Note: noun chunks (<np>) will be shown as brackets [...]\n";
    }
    else {
      $corpus = "";
      print "NO SUCH CORPUS!\n";
    }
  }
  # CQP query
  else {
    if (not $corpus) {
      print "Please set corpus first!\n";
      next;
    }
    print "Executing CQP query ... ";
    my $starttime = time;
    
    my $status = cqi_query($corpus, "A", $query);
    if ($status != $CWB::CQI::STATUS_OK) {
      print "failed [$CWB::CQI::CommandName{$status}]\n";
      next;
    }
    my $time = time - $starttime;
    print "ok [$time seconds elapsed]\n";
    my $size = cqi_subcorpus_size("$corpus:A");
    if ($size > 0) {
      my @match = cqi_dump_subcorpus("$corpus:A", 'match', 0, $size-1);
      my @matchend = cqi_dump_subcorpus("$corpus:A", 'matchend', 0, $size-1);
      for (my $i = 0; $i < $size; $i++) {
        print_kwic_line($match[$i], $matchend[$i]);
      }
    }
    print "$size matches.\n";
    cqi_drop_subcorpus("$corpus:A");
  }
}

print "Logging out ... ";
cqi_bye();
print "disconnected.\n";



sub print_kwic_line {
  my $match = shift;
  my $matchend = shift;
  my ($lb, $rb, $sentence, $dummy);
  my ($a, @lc, @mat, @rc);

  $a = "$corpus.s";
  $sentence = cqi_cpos2struc($a, $match);
  ($lb, $dummy) = cqi_struc2cpos($a, $sentence);
  $lb = $match if $sentence < 0; # no context if not in <s> region
  $sentence = cqi_cpos2struc($a, $matchend);
  ($dummy, $rb) = cqi_struc2cpos($a, $sentence);
  $rb = $matchend if $sentence < 0; # no context if not in <s> region
  
  printf "%10d:  ", $match;
  @lc = range_to_string($corpus, $lb .. $match-1);
  @mat = range_to_string($corpus, $match .. $matchend);
  @rc = range_to_string($corpus, $matchend+1 .. $rb);
  print "@lc <<@mat>> @rc\n";
}

# convert range of IDs to list of tokens with <nc>..</nc> tags inserted if available
# @tokens = range_to_string($corpus, @cpos);
sub range_to_string {
  my $corpus = shift;
  my @cpos = @_;
  my @word = cqi_cpos2str("$corpus.word", @cpos);
  if ($HaveNCAtt) {
    my $nc = "$corpus.np";
    my @lb = cqi_cpos2lbound($nc, @_);
    for (my $i = 0; $i < @word; $i++) {
      $word[$i] = "[".$word[$i]
        if $_[$i] == $lb[$i];
    }
    my @rb = cqi_cpos2rbound($nc, @_);
    for (my $i = 0; $i < @word; $i++) {
      $word[$i] = $word[$i]."]"
        if $cpos[$i] == $rb[$i];
    }
  }
  return @word;
}
