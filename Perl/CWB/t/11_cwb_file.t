# -*-cperl-*-
## Test CWB::OpenFile function with automagic compression/decompression

use Test::More tests => 28;

use CWB;

our $tempfile = undef;

END {
  unlink $tempfile		# clean up if file read / write tests failed
    if $tempfile and -f $tempfile;
}

## try reading plain text file with explicit and implicit read mode specifiers
test_read_file("data/files/ok.txt", "read plain file (implicit read mode)"); # T1
test_read_file("< data/files/ok.txt", "read plain file (explicit read mode, 1-argument form)");
test_read_file("<", "data/files/ok.txt", "read plain file (explicit read mode, 2-argument form)");

## check which compression formats are available
our $have_Z = is_available("(echo ok | compress | uncompress)");
our $have_gz = is_available("gzip -cd data/files/ok.txt.gz");
our $have_bz2 = is_available("bzip2 -cd data/files/ok.txt.bz2");

## try reading legacy compressed file (.Z) if "compress" and "uncompress" programs are available
SKIP: {
  skip "compress program not installed", 3 unless $have_Z;
  test_read_file("data/files/ok.txt.Z", "read .Z file (implicit read mode)"); # T4
  test_read_file("< data/files/ok.txt.Z", "read .Z file (explicit read mode, 1-argument form)");
  test_read_file("<", "data/files/ok.txt.Z", "read .Z file (explicit read mode, 2-argument form)");
}

## try reading GZip compressed file (.gz) if "gzip" program is available
SKIP: {
  skip "gzip program not installed", 3 unless $have_gz;
  test_read_file("data/files/ok.txt.gz", "read .gz file (implicit read mode)"); # T7
  test_read_file("< data/files/ok.txt.gz", "read .gz file (explicit read mode, 1-argument form)");
  test_read_file("<", "data/files/ok.txt.gz", "read .gz file (explicit read mode, 2-argument form)");
}

## try reading BZip2 compressed file (.bz2) if "bzip2" program is available
SKIP: {
  skip "bzip2 program not installed", 3 unless $have_bz2;
  test_read_file("data/files/ok.txt.bz2", "read .bz2 file (implicit read mode)"); # T10
  test_read_file("< data/files/ok.txt.bz2", "read .bz2 file (explicit read mode, 1-argument form)");
  test_read_file("<", "data/files/ok.txt.bz2", "read .bz2 file (explicit read mode, 2-argument form)");
}

## try writing and then reading uncompressed and compressed files, with 1-argument and 2-argument forms
test_read_write_file(".txt", 0); # 1-argument form, T13
test_read_write_file(".txt", 1); # 2-argument form
SKIP: {
  skip "compress program not installed", 2 unless $have_Z;
  test_read_write_file(".Z", 0); # 1-argument form, T15
  test_read_write_file(".Z", 1); # 2-argument form
}
SKIP: {
  skip "gzip program not installed", 2 unless $have_gz;
  test_read_write_file(".gz", 0); # 1-argument form, T17
  test_read_write_file(".gz", 1); # 2-argument form
}
SKIP: {
  skip "bzip2 program not installed", 2 unless $have_bz2;
  test_read_write_file(".bz2", 0); # 1-argument form, T19
  test_read_write_file(".bz2", 1); # 2-argument form
}

## test I/O layers (encoding for input files)
my $textU = test_load_file("<:encoding(UTF-8)", "data/files/words_utf8.txt", "load plain file in UTF-8 encoding"); # T21
my $textL = test_load_file("<:encoding(ISO-8859-1)", "data/files/words_latin1.txt", "load plain file in ISO-8859-1 encoding");
ok($textU eq $textL, "character encoding I/O layer works");
ok(uc($textU) eq uc($textL) && uc($textU) ne $textU, "character encoding I/O layer produces Unicode strings");
SKIP: {
  skip "gzip program not installed", 3 unless $have_gz;
  my $textUgz = test_load_file("<:encoding(UTF-8)", "data/files/words_utf8.txt.gz", "load .gz file in UTF-8 encoding"); # T25
  my $textLgz = test_load_file("<:encoding(ISO-8859-1)", "data/files/words_latin1.txt.gz", "load .gz file in ISO-8859-1 encoding");
  ok($textUgz eq $textU && $textLgz eq $textL, "character encoding I/O layer works with .gz files");
}

## test that reading non-existent compressed file fails immediately
eval { CWB::OpenFile("data/files/does_not_exist.gz") };
like($@, qr/does not exist/, "error condition when opening non-existent .gz file"); # T28

## check if specified tool is available in user's path
sub is_available {
  my $cmd = shift;
  my $ok = system "$cmd 2>/dev/null | grep ok >/dev/null";
  return $ok == 0;
}

## try to read a known compressed or uncompressed file
sub test_read_file {
  my $mode = (@_ > 2) ? shift : "";
  my $filename = shift;
  my $name = shift;;
  my $fh;
  if ($mode) {
    $fh = CWB::OpenFile($mode, $filename);
  }
  else {
    $fh = CWB::OpenFile($filename);
  }
  my $ok = 0;
  if ($fh) {
    my $line = <$fh>;
    if ($line) {
      $ok = ($line =~ /^ok\.$/) ? 1 : 0;
    }
    else {
      diag("couldn't read from file '$filename'");
    }
    $fh->close;
  }
  else {
    diag("couldn't open file '$filename'");
  }
  ok($ok, $name);
}

## read a file with optional I/O layers; returns file contents as single string or ""
sub test_load_file {
  my ($mode, $filename, $name) = @_;
  my $ok = 1;
  my $text = "";
  my $fh = CWB::OpenFile($mode, $filename);
  if ($fh) {
    $text = join("", <$fh>);
    $ok = 0 unless $text;
    $fh->close
      or $ok = 0;
  }
  else {
    $ok = 0;
  }
  ok($ok, $name);
  return $text;
}

## try to write & read compressed and uncompressed files
sub test_read_write_file {
  my ($ext, $two_arg) = @_;
  $tempfile = "/tmp/test_CWB_$$.$ext"; # set global variable for END{} cleanup
  my $name = "write/read .$ext file (".(($two_arg) ? 1 : 2)."-argument form)";
  my $fh = undef;
  my $ok = 0;
  if ($two_arg) {
    $fh = CWB::OpenFile ">", $tempfile;
  }
  else {
    $fh = CWB::OpenFile "> $tempfile";
  }
  if ($fh) {
    my @data = (1, 42, 7, -1001);
    map {print $fh "$_\n"} @data;
    $fh->close;
    if (-s $tempfile >= 10) {
      if ($two_arg) {
	$fh = CWB::OpenFile "<", $tempfile;
      }
      else {
	$fh = CWB::OpenFile $tempfile;
      }
      if ($fh) {
	my @lines = <$fh>;
	$fh->close;
	if (@lines == @data and not grep {$lines[$_] != $data[$_]."\n"} (0 .. $#data)) {
	  $ok = 1;
	}
	else {
	  diag("file data corrupt in '$tempfile'");
	}
      }
      else {
	diag("couldn't open file '$tempfile' for reading");
      }
    }
    else {
      diag("writing '$tempfile' failed (file too small)");
    }
  }
  else {
    diag("couldn't open file '$tempfile' for writing");
  }
  unlink $tempfile;
  ok($ok, $name);
}
