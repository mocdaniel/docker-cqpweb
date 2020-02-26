# -*-cperl-*-
## Test support for temporary file

use Test::More tests => 9;

use CWB;
use FileHandle;

our $t = new CWB::TempFile ("MyFile.txt");
isa_ok($t, "CWB::TempFile", "create CWB::TempFile object"); # T1

our $filename = $t->name;
like($filename, qr/\.txt/, "correct filename extension"); # T2

our @testdata = (0 .. 9999);
foreach my $i (@testdata) {
  $t->write("$i\n");
}
$t->finish;
ok(-s $filename > 2 * @testdata, "file has been created"); # T3

my $fh = new FileHandle $filename; # direct access to temporary file after finish()
ok(defined($fh), "direct access to temporary file"); # T4

our $ok = 1;
foreach $i (@testdata) {
  my $line = $fh->getline;
  if ($line ne "$i\n") {
    $ok = 0;
    diag("temporary file data corrupt");
    last;
  }
}
if (not $fh->eof) {
  $ok = 0;
  diag("too much data in temporary file");
}
$fh->close;
ok($ok, "temporary file contents (direct access)"); # T5

$ok = 1;
foreach $i (@testdata) {
  my $line = $t->read;
  if ($line ne "$i\n") {
    $ok = 0;
    diag("temporary file data corrupt");
    last;
  }
}
if ($t->read) {
  $ok = 0;
  diag("too much data in temporary file");
}
ok($ok, "temporary file contents (read() method)"); # T6

$t->rewind; # rewind file -> contents can be read again
$ok = 1;
foreach $i (@testdata) {
  my $line = $t->read;
  if ($line ne "$i\n") {
    $ok = 0;
    diag("data error when re-reading temporary file");
    last;
  }
}
ok($ok, "re-reading temporary file after rewind()"); # T7

## make sure that temporary file isn't removed before $t is destroyed (or close()) called
ok(-f $filename, "temporary file removed prematurely"); # T8
undef $t;
ok(! -f $filename, "failed to remove temporary file automatically"); # T9



