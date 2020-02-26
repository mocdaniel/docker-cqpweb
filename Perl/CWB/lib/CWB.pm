package CWB;
# -*-cperl-*-
$VERSION = 3.000_003; # 3.0.3 in standard Perl format

use strict;
use warnings;

use FileHandle;
use Carp;
use CWB::Config;

=head1 NAME

CWB - Perl toolbox for the IMS Corpus Workbench

=head1 SYNOPSIS

  use CWB;

  # full pathnames of CQP and the CWB tools
  $CWB::CQP;             # cqp
  $CWB::Config;          # cwb-config
  $CWB::Encode;          # cwb-encode
  $CWB::Makeall;         # cwb-makeall
  $CWB::Decode;          # cwb-decode
  $CWB::Lexdecode;       # cwb-lexdecode
  $CWB::DescribeCorpus;  # cwb-describe-corpus
  $CWB::Huffcode;        # cwb-huffcode
  $CWB::CompressRdx;     # cwb-compress-rdx
  $CWB::Itoa;            # cwb-itoa
  $CWB::Atoi;            # cwb-atoi
  $CWB::SEncode;         # cwb-s-encode
  $CWB::SDecode;         # cwb-s-decode
  $CWB::ScanCorpus;      # cwb-scan-corpus
  $CWB::Align;           # cwb-align
  $CWB::AlignEncode;     # cwb-align-encode
  $CWB::CQPserver;       # cqpserver

  # default registry directory and effective registry setting
  $CWB::DefaultRegistry;
  @dirs = CWB::RegistryDirectory(); # may return multiple directories

  # open filehandle for reading or writing
  # automagically compresses/decompresses files and dies on error
  $fh = CWB::OpenFile("> my_file.gz");
  $fh = CWB::OpenFile(">", "my_file.gz"); # as in 3-argument open() call

  # temporary file objects (disk files are automatically removed)
  $t1 = new CWB::TempFile;             # picks a unique filename
  $t2 = new CWB::TempFile "mytemp";    # extends prefix to unique name
  $t3 = new CWB::TempFile "mytemp.gz"; # compressed temporary file
  $filename = $t1->name;        # full pathname of temporary file
  $t1->write(...);              # works like $fh->print()
  $t1->finish;                  # stop writing file
  print $t1->status, "\n";      # WRITING/FINISHED/READING/DELETED
  # main program can read or overwrite file <$filename> now
  $line = $t1->read;            # read one line, like $fh->getline()
  $t1->rewind;                  # re-read from beginning of file
  $line = $t1->read;            # (reads first line again)
  $t1->close;                   # stop reading and delete temporary file
  # other files will be deleted when objects $t2 and $t3 are destroyed

  # execute shell command with automatic error detection
  $cmd = "ls -l";
  $errlevel = CWB::Shell::Cmd($cmd);   # dies with error message if not ok
  # $errlevel: 0 (ok), 1 (minor problems), ..., 6 (fatal error)
  @lines = ();
  CWB::Shell::Cmd($cmd, \@lines);      # capture standard output in array
  CWB::Shell::Cmd($cmd, "files.txt");  # ... or in file (for large amounts of data)
  $CWB::Shell::Paranoid = 1;    # more paranoid checks (-1 for less paranoid)

  $quoted = CWB::Shell::Quote($string); # quote arbitrary string as shell argument
  CWB::Shell::Cmd([$prog, $arg, ...], \@lines); # auto-quotes individual arguments

  # read / modify / write registry files (must be in canonical format)
  $reg = new CWB::RegistryFile; # create new registry file
  $reg = new CWB::RegistryFile "/corpora/c1/registry/dickens";  # load file
  die "failed" unless defined $reg;    # will fail if not in canonical format

  $reg = new CWB::RegistryFile "dickens";       # search in standard registry
  $filename = $reg->filename;                   # retrieve full pathname

  # edit standard fields
  $name = $reg->name;           # read NAME field
  $reg->name("Charles Dickens");# modify NAME field
  $corpus_id = $reg->id;        # same for ID, HOME, INFO
  $home_dir = $reg->home;
  $info_file = $reg->info;
  $reg->delete_info;            # INFO line is optional and may be deleted

  # edit corpus properties
  @properties = $reg->list_properties;
  $value = $reg->property("language");  # get property value
  $reg->property("language", "en");     # set / add property
  $reg->delete_property("language");

  # edit attributes ('p'=positional, 's'=structural, 'a'=alignment)
  @attr = $reg->list_attributes;        # list all attributes
  @s_attr = $reg->list_attributes('a'); # list alignment attributes
  $type = $reg->attribute("word");      # 'p'/'s'/'a' or undef
  $reg->delete_attribute("np");
  $reg->add_attribute("np", 's');       # specify type when adding attribute
  $dir = $reg->attribute_path("lemma"); # may be stored in different directory
  $reg->attribute_path("lemma", $dir);  # set attribute path
  $reg->delete_attribute_path;          # default location is HOME directory

  # comment lines (preceding field/declaration) and inline comments use keys:
  #   ":NAME", ":ID", ... "::$property", ... "$attribute", ...
  @lines = $reg->comments(":HOME");     # comment lines before HOME field
  $reg->set_comments(":INFO", @lines);  # overwrite existing comments
  $reg->add_comments("::language", "", "comment for language property", "");
  $reg->set_comments("::language");     # delete comments before property
  $comment = $reg->line_comment("np");  # inline comment of np attribute
  $reg->line_comment("word", "the required word attribute");  # set comment
  $reg->delete_line_comment("word");    # delete inline comment

  # (over)write registry file (requires full pathname)
  $reg->write("/corpora/c1/registry/dickens");

=head1 DESCRIPTION

This module offers basic support for using the IMS Open Corpus Workbench
(L<http://cwb.sourceforge.net/>) from Perl scripts.
Several additional functions are included to perform tasks
that are often needed by corpus-related scripts.


=head1 CWB PATHNAMES

Package variables give the full pathnames of B<CQP> and the B<CWB tools>, 
so they can be used in shell commands even when they are not
installed in the user's search path. The following variables are available:

  $CWB::CQP;             # cqp
  $CWB::Config;          # cwb-config
  $CWB::Encode;          # cwb-encode
  $CWB::Makeall;         # cwb-makeall
  $CWB::Decode;          # cwb-decode
  $CWB::Lexdecode;       # cwb-lexdecode
  $CWB::DescribeCorpus;  # cwb-describe-corpus
  $CWB::Huffcode;        # cwb-huffcode
  $CWB::CompressRdx;     # cwb-compress-rdx
  $CWB::Itoa;            # cwb-itoa
  $CWB::Atoi;            # cwb-atoi
  $CWB::SEncode;         # cwb-s-encode
  $CWB::SDecode;         # cwb-s-decode
  $CWB::ScanCorpus;      # cwb-scan-corpus
  $CWB::Align;           # cwb-align
  $CWB::AlignEncode;     # cwb-align-encode
  $CWB::CQPserver;       # cqpserver

Other configuration information includes the general installation prefix,
the directory containing CWB binaries (which might be used to install additional
software related to the CWB), and the default registry directory.  B<NB:>
individual install paths may have overridden the general prefix, so the
package variable I<$CWB::Prefix> does not have much practical importance.
Use the B<cwb-config> program to find out the precise installation paths.

  $CWB::Prefix;          # general installation prefix
  $CWB::BinDir;          # directory for CWB binaries (executable programs)
  $CWB::DefaultRegistry; # compiled-in default registry directory
  $CWB::CWBVersion;      # release version of the CWB binaries (Perl-style)

Note that I<$CWB::CWBVersion> refers to the release verison of the CWB binaries
rather than the Perl module (I<$CWB::VERSION>).  All version numbers are encoded
in Perl numeric style (e.g. C<3.004_001> for CWB v3.4.1), so specific version
requirements can easily be checked by numeric comparison.

=cut

# make package configuration variables available
our $Prefix = $CWB::Config::Prefix; # this doesn't say much, as individual install directories may have been overwritten
our $BinDir = $CWB::Config::BinDir;
our $DefaultRegistry = $CWB::Config::Registry;
our $CWBVersion = $CWB::Config::Version;

# global variables: full paths to CWB tools
our $Config = "$BinDir/cwb-config";
our $SEncode = "$BinDir/cwb-s-encode";
our $SDecode = "$BinDir/cwb-s-decode";
our $Encode = "$BinDir/cwb-encode";
our $Decode = "$BinDir/cwb-decode";
our $Lexdecode = "$BinDir/cwb-lexdecode";
our $Makeall = "$BinDir/cwb-makeall";
our $DescribeCorpus = "$BinDir/cwb-describe-corpus";
our $Itoa = "$BinDir/cwb-itoa";
our $Atoi = "$BinDir/cwb-atoi";
our $CompressRdx = "$BinDir/cwb-compress-rdx";
our $Huffcode = "$BinDir/cwb-huffcode";
our $ScanCorpus = "$BinDir/cwb-scan-corpus";
our $Align = "$BinDir/cwb-align";
our $AlignEncode = "$BinDir/cwb-align-encode";
our $CQP = "$BinDir/cqp";
our $CQPserver = "$BinDir/cqpserver";

## ======================================================================
##  some general utility functions
## ======================================================================

=head1 MISCELLANEOUS FUNCTIONS

=over 4

=item @dirs = CWB::RegistryDirectory();

The function B<CWB::RegistryDirectory> can be used to determine the I<effective>
registry directory (either the compiled-in default registry or a setting made
in the I<CORPUS_REGISTRY> environment variable). It is possible to specify multiple
registry directories, so B<CWB::RegistryDirectory> returns a list of strings.

=cut

sub RegistryDirectory {
  my $registry = $ENV{'CORPUS_REGISTRY'} || $DefaultRegistry;
  my @dirs = split /:/, $registry;
  foreach (@dirs) { s/^\?// }; # remove '?' marking optional registry directories
  return wantarray ? @dirs : shift @dirs;
}

=item $fh = CWB::OpenFile($name);

=item $fh = CWB::OpenFile($mode, $name);

Open file I<$name> for reading, writing, or appending. Returns B<FileHandle>
object if successful, otherwise it B<die>s with an error message. It is thus
never necessary to check whether I<$fh> is defined.

If B<CWB::OpenFile> is called with two arguments, I<$mode> indicates the file
access mode: C<E<lt>> for reading, C<E<gt>> for writing, C<E<gt>E<gt>> for
appending, C<|-> for a write pipe and C<-|> for a read pipe (see
L<perlfunc/"open"> for details).  In this form, I/O layers can be appended
to the access mode.  For example, to read a C<.gz> file in ISO-8859-1 encoding,
you can use the command

  $fh = CWB::OpenFile("<:encoding(latin1)", $filename);

In the one-argument form, B<CWB::OpenFile> examines the file name for an
embedded access mode specifier. If I<$name> starts with C<E<gt>> the file is
opened for writing (an existing file will be overwritten), if it starts
with C<E<gt>E<gt>> the file is opened for appending. The default is to open
the file for reading, which can optionally be made explicit by a leading C<E<lt>>. 
A C<|> at the start or end of I<$name> opens a write or read pipe, respectively.

Files with extension C<.Z>, C<.gz>, C<.bz2> or C<.xz> are automatically
compressed and decompressed, provided that the necessary programs are installed.
It is also possible to append to C<.gz> and C<.bz2> files.

=cut

# open file for reading or writing, with 'magical' compression/decompression
# returns FileHandle object; if open fails, exits with error message
sub OpenFile ( $;$ ) {
  my $mode = (@_ > 1) ? shift : undef;
  my $name = shift;
  my $fh = undef;

  ## if called with single argument, examine $name for indication of file access mode
  if (not defined $mode) {
    if ($name =~ s/^\s*\|\s*//) {
      $mode = "|-";
    }
    elsif ($name =~ s/\s*\|\s*$//) {
      $mode = "-|";
    }
    elsif ($name =~ s/^\s*(>>?)\s*//) {
      $mode = $1;
    }
    else {
      $name =~ s/^\s*<\s*//;
      $mode = "<";
    }
  }

  ## check that file access mode is valid
  croak "CWB::OpenFile: Unsupported file access mode '$mode'"
    unless $mode =~ /^(\|-|-\||>>?|<)(:.+)?$/;
  my $sys_mode = $1;
  my $layers = ($2) ? $2 : "";
  my $is_pipe = ($sys_mode =~ /\|/) ? 1 : 0;

  ## special case for STDIN / STDOUT
  if ($name eq "-" and $sys_mode =~ /^(<|>>?)$/) {
    my $spec = ($sys_mode eq "<") ? "-" : ">-";
    open $fh, $spec
      or croak "CWB::OpenFile: Can't open STDIN/STDOUT (???)";
    return $fh;
  }

  ## automagic compression/decompression in non-pipe modes
  my $spec = $name;            # in pipe mode, we just pass $name
  if (not $is_pipe) {
    my $write_mode = ($sys_mode eq "<") ? 0 : 1; # TRUE in write or append mode
    if ($name =~ /\.(gz|bz2|xz|Z)$/i) {
      my $type = lc($1);
      my $shell_name = CWB::Shell::Quote($name); # escape filename for use in shell command
      if ($type eq "gz") {
        $spec = ($write_mode) ? "gzip -c ${sys_mode}${shell_name}" : "gzip -cd ${shell_name}";
      } elsif ($type eq "bz2") {
        $spec = ($write_mode) ? "bzip2 -c ${sys_mode}${shell_name}" : "bzip2 -cd ${shell_name}";
      } elsif ($type eq "xz") {
        $spec = ($write_mode) ? "xz -c ${sys_mode}${shell_name}" : "xz -cd ${shell_name}";
      } elsif ($type eq "z") {
        $spec = ($write_mode) ? "compress -c ${sys_mode}${shell_name}" : "uncompress -c ${shell_name}";
      }
      if ($mode =~ /^</ and not -f $name) {
        croak "CWB::OpenFile: File '$name' does not exist."; # opening read pipe would not fail immediately
      }
      $sys_mode = ($write_mode) ? "|-" : "-|"; # change read/write mode to pipe automagically
    }
  }

  open $fh, $sys_mode.$layers, $spec
    or croak "CWB::OpenFile: Can't open file/pipe '$name' in mode '$mode': $!";
  return $fh;
}

=back

=cut

## ======================================================================
##  temporary file objects
## ======================================================================

package CWB::TempFile;

use Carp;

=head1 TEMPORARY FILES

Temporary files (implemented by B<CWB::TempFile> objects) are created with 
a unique name and are automatically deleted when the script exits. The
life cycle of a temporary file consists of four stages: B<create>, 
B<write>, B<read> (possibly B<re-read>), B<delete>. This cycle corresponds
to the following method calls:

  $tf = new CWB::TempFile;  # create new temporary file in /tmp dir
  $tf->write(...);     # write cycle (buffered output, like print function)
  $tf->finish;         # complete write cycle (flushes buffer)
  $line = $tf->read;   # read cycle (like getline method for FileHandle)
 [$tf->rewind;         # optional: start re-reading temporary file ]
 [$line = $tf->read;                                               ]
  $tf->close;          # delete temporary file

Once the temporary file has been read from, it cannot be re-written; a
new B<CWB::TempFile> object has to be created for the next cycle. When
the write stage is completed (but before reading has started, i.e. after
calling the B<finish> method), the temporary file can be accessed 
and/or overwritten by external programs. Use the B<name> method to
obtain its full pathname. If no direct access to the temporary file is
required, the B<finish> method is optional. The write cycle will
automatically be completed before the first B<read> method call.

=over 4

=item $tf = new CWB::TempFile [ $prefix ];

Creates temporary file in F</tmp> directory. If the optional I<$prefix>
is specified, the filename will begin with I<$prefix> and be extended
to a unique name. If I<$prefix> contains a C</> character, it is interpreted
as an absolute or relative path, and the temporary file will not be created
in the F</tmp> directory. To create a temporary file in the current
working directory, use F<./MyPrefix>. 

You can add the extension C<.Z>, C<.gz>, or C<.bz2> to I<$prefix> in order
to create a compressed temporary file. The actual filename (returned by the
B<name> method) will have the same extension in this case. 

The temporary file is immediately created and opened for writing.

=cut

# $tf = new CWB::TempFile;                (chooses name automatically)
# $tf = new CWB::TempFile "NP-Chunks";    (uses name beginning with "NP-Chunks")
# $tf = new CWB::TempFile "NP-Chunks.gz"; (tell module to write gzipped tempfile, using OpenFile magic)
sub new {
  my $class = shift;
  my $prefix = shift;
  $prefix = "CWB_TempFile" unless defined $prefix;
  my $suffix = "";
  if ($prefix =~ s/\.(gz|bz2|Z)$//) {
    $suffix = $&;
  }
  my $self = {};
  my $basedir = ($prefix =~ /\//) ? "" : "/tmp/";  # if $prefix isn't absolute or relative path, create temp file in /tmp directory
  my $name = $basedir.$prefix.".$$".$suffix;
  my $num = 1;
  while (-e $name) {            # choose unique name in case file already exists
    $name = $basedir.$prefix.".$$-".$num.$suffix;
    $num++;
  }
  my $fh = CWB::OpenFile "> $name";
  $self->{NAME} = $name;
  $self->{FH} = $fh;
  $self->{STATUS} = "W";        # W = writing, F = finished, R = reading, D = deleted
  return bless($self, $class);
}

sub DESTROY {
  my $self = shift;
  if ($self->{STATUS} ne "D") {
    $self->close;
  }
}

=item $tf->close;

Closes all open file handles and deletes the temporary file. This will be done
automatically when the B<CWB::TempFile> object is destroyed. Use B<close> to
free disk space immediately.  

=cut

sub close {
  my $self = shift;
  my $status = $self->{STATUS};
  my $name = $self->{NAME};
  my $fh = $self->{FH};
  if (($status eq "W" or $status eq "R") and defined $fh) {
    $fh->close
      or carp "CWB::TempFile: Error writing/reading tempfile $name ($!)";
  }
  if (-f $name) {
    carp "CWB::TempFile: Could not unlink tempfile $name ($!)"
      unless unlink $name;
  }
  $self->{STATUS} = "D";
}

=item $filename = $tf->name;

Returns the real filename of a temporary file. B<NB:> direct access to this
file (e.g. by external programs) is I<only> allowed after calling B<finish>, 
and before the first B<read>.

=cut

sub name {
  my $self = shift;
  return $self->{NAME};
}

=item $status = $tf->status;

Returns the current status of the temporary file, i.e. the stage in its
life cycle.  The return value is one of the strings
C<WRITING> (initial state),
C<FINISHED> (immediately after B<finish>, before first read),
C<READING> (while reading or after B<rewind>) or
C<DELETED> (after B<close>).

=cut

sub status {
  my $self = shift;
  return {
          "W" => "WRITING",
          "F" => "FINISHED",
          "R" => "READING",
          "D" => "DELETED",
         }->{$self->{STATUS}};
}

=item $tf->write(...);

Write data to the temporary file. All arguments are passed to Perl's 
built-in B<print> function. Like B<print>, this method does not automatically
add newlines.

=cut

sub write {
  my $self = shift;
  croak "CWB::TempFile: Can't write to tempfile ".$self->name." with status ".$self->status
    unless $self->{STATUS} eq "W";
  $self->{FH}->print(@_)
    or croak "CWB::TempFile: Error writing tempfile ".$self->name." ($!?)";
}

=item $tf->finish;

Stop writing to the temporary file, flush the output buffer, and close 
the associated file handle. Afer B<finish> has been called, the temporary
file can be accessed directly by the script or external programs, and may
be overwritten by them. In order to automatically delete a file created by
an external program, B<finish> the temporary file immediately after its
creation and then allow the external tool to overwrite it:

  $tf = new CWB::TempFile;
  $tf->finish;  # temporary file has size of 0 bytes now
  $filename = $tf->name;
  system "$my_shell_command > $filename";

=cut

sub finish {
  my $self = shift;
  croak "CWB::TempFile: Can't finish tempfile ".$self->name." with status ".$self->status
    unless $self->{STATUS} eq "W";
  $self->{FH}->close
    or croak "CWB::TempFile:: Error writing tempfile ".$self->name." ($!)";
  $self->{FH} = undef;
  $self->{STATUS} = "F";
}

=item $line = $tf->read;

Read one line from temporary file (same as B<getline> method on B<FileHandle>). 
Automatically invokes B<finish> if called immediately after write cycle.

=cut

sub read {
  my $self = shift;
  my $status = $self->{STATUS};
  croak "CWB::TempFile: Can't read from tempfile ".$self->name.", already ".$self->status
    if $status eq "D";
  if ($status eq "W") {
    $self->finish;
  }
  if ($status ne "R") {
    $self->{FH} = CWB::OpenFile $self->{NAME};
    $self->{STATUS} = "R";
  }
  return $self->{FH}->getline;
}

=item $tf->rewind;

Allows the script to re-read a temporary file. The next B<read> call will return
the first line of the temporary file. Internally this is achieved by closing
and re-opening the associated file handle. 

=cut

sub rewind {
  my $self = shift;
  my $status = $self->{STATUS};
  croak "CWB::TempFile: Can't rewind tempfile ".$self->name." with status ".$self->status
    if $status eq "D" or $status eq "W";
  if ($status ne "R") {
    # if rewind is called before first read, it does nothing
  }
  else {
    $self->{FH}->close
      or croak "CWB::TempFile:: Error writing tempfile ".$self->name." ($!)";
    $self->{FH} = CWB::OpenFile $self->{NAME};
  }
}

=back

=cut

## ======================================================================
##  execute shell command with thorough error checks
## ======================================================================

package CWB::Shell;

use Carp;

=head1 SHELL COMMANDS

The B<CWB::>B<Shell::Cmd()> function provides a convenient replacement
for the built-in B<system> command. Standard output and error messages
produced by the invoked shell command are captured to avoid screen
clutter, and the former is available to the Perl script (similar to
the backtick operator C<`$shell_cmd`>). B<CWB::>B<Shell::Cmd()> also checks
for a variety of error conditions and returns an error level value ranging
from 0 (successful) to 6 (fatal error):

  Error Level  Description
    6          command execution failed (system error)
    5          non-zero exit value or error message on STDERR
    4          -- reserved for future use --
    3          warning message on STDERR
    2          any output on STDERR
    1          error message on STDOUT

Depending on the value of I<$CWB::Shell::Paranoid>, a warning message will
be issued or the function will B<die> with an error message.

=over 4

=item $CWB::Shell::Paranoid = 0;

With the default setting of 0, B<CWB::>B<Shell::Cmd()> will B<die> if the
error level is 5 or greater. In the B<extra paranoid> setting (+1), it
will almost always B<die> (error level 2 or greater). In the B<less paranoid>
setting (-1) only an error level of 6 (i.e. failure to execute the shell
command) will cause the script to abort.

=cut

our $Paranoid = 0;

# use global variables and sub to handle warn/die situations
our $return_status = 0;
our $current_cmd = "";

# internal function: raise error (according to current Paranoid setting)
#   Error $errlevel, $message [, $message ...];
# error levels are:
# LVL  <less p.>   <normal>   <extra-p.>
#  6 :  fatal      fatal       fatal 
#  5 :  warn       fatal       fatal
#  4 :  warn       warn        fatal
#  3 :  nothing    warn        fatal
#  2 :  nothing    nothing     fatal
#  1 :  nothing    nothing     warn
#  0 :  nothing    nothing     nothing
sub Error ( $@ ) {
  my $errlevel = shift;
  my @message = @_;
  # $action is: 0=fatal, 1=warn, 2=nothing 
  my $action;
  
  if ($errlevel >= $return_status) {  
    $return_status = $errlevel;

    if ($Paranoid < 0) {
      $action = [qw<2 2 2 2 1 1 0>]->[$errlevel];
    }
    elsif ($Paranoid == 0) {
      $action = [qw<2 2 2 1 1 0 0>]->[$errlevel];
    }
    else {
      $action = [qw<2 1 0 0 0 0 0>]->[$errlevel];
    }
  }
  else {
    $action = 2;                # don't report this error if a more serious one has already occurred
  }

  if ($action == 0) {
    croak
      "\nSHELL CMD '$current_cmd' FAILED:\n",
      map {chomp; ">> $_\n"} @message;
  }
  elsif ($action == 1) {
    print "\nWARNING (SHELL CMD '$current_cmd'):\n";
    map {chomp; print "-> $_\n"} @message;
  }
  else {
    # nothing :o)
  }
}


=item $errlvl = CWB::Shell::Cmd($cmd);

=item $errlvl = CWB::Shell::Cmd($cmd, $filename);

=item $errlvl = CWB::Shell::Cmd($cmd, \@lines);

The first form executes I<$cmd> as a shell command (through the built-in
B<system> function) and returns an error level value. With the default
setting of I<$CWB::Shell::Paranoid>, serious errors are usually detected and
cause the script to B<die>, so it is not necessary to check I<$errlvl>. 

The second form stores the standard output of the shell command in
file I<$filename>. It can then be processed with external programs or
read in by the Perl script. B<NB:> Compressed files are not supported!
It is recommended to use an uncompressed temporary file (B<CWB::TempFile> object).

The third form requires an array reference as its second argument. It splits
the standard output of the shell command into B<chomp>ed lines and stores them
in I<@lines>. If there is a large amount of standard ouput, it is more efficient
to use the second form.

=item $errlvl = CWB::Shell::Cmd([$prog, $arg, ...], ...);

In each form of B<CWB::Shell::Cmd>, the string I<$cmd> can be replaced by an array reference
containing the program to be called and its individual arguments.  The arguments will
automatically be quoted in a way that is safe at least in B<bash> and B<tcsh> shells.  Note that
simple option flags with values must be passed as two separate arguments in this case,
e.g. C<< [$CWB::DescribeCorpus, "-r", $registry, "DICKENS"] >>.

If you want to execute a multi-command pipeline or use other shell metacharacters
in your command, you have to use the B<CWB::Shell::Quote> function to quote literal arguments yourself.

=cut

sub Cmd {
  my $cmd = shift;
  my $outfile = shift;
  defined $outfile or $outfile = "";

  if (ref($cmd) eq 'ARRAY') {
    $cmd = join(" ", Quote(@$cmd));
  }

  # create arrays into which stdout / stderr will be read
  # if second argument is arrayref, store stdout in that array, else create private anonymous array
  my $stdout = [];
  if ((ref $outfile) eq 'ARRAY') {
    $stdout = $outfile;
    $outfile = "";              # so we'll create a temporary file
  }
  my $stderr = [];

  my $stdout_tmp = undef;       # create temporary files for capturing stdout and stderr
  my $stderr_tmp = new CWB::TempFile "CWB-Shell-Cmd-STDERR";

  my $stdout_file = $outfile;
  if (not $outfile) {
    $stdout_tmp = new CWB::TempFile "CWB-Shell-Cmd-STDOUT";
    $stdout_tmp->finish;        # now we're allowed to access the file directly
    $stdout_file = $stdout_tmp->name;
  }
  $stderr_tmp->finish;
  my $stderr_file = $stderr_tmp->name;

  my $status = system "($cmd) 1>$stdout_file 2>$stderr_file";
  my $syscode = $status & 0xff;
  my $exitval = $status >> 8;

  my $fh = CWB::OpenFile $stderr_file;
  @$stderr = <$fh>;
  map {chomp;} @$stderr;
  $fh->close;
  if ($outfile) {
    @$stdout = ();              # don't check STDOUT if caller wants it in file
  }
  else {
    $fh = CWB::OpenFile $stdout_file;
    @$stdout = <$fh>;
    map {chomp;} @$stdout;
    $fh->close;
  }

  $current_cmd = $cmd;          # Error() may want to report the command that failed
  $return_status = 0;           # error level will be increased (but not decreased) by Error() function

  Error 6, "System error: $!", @$stderr
    if $syscode != 0;
  Error 5, "Non-zero exit value $exitval.", @$stderr
    if $exitval != 0;
  Error 5, "Error message on stderr:", @$stderr
    if grep { /error|fail|abnormal|abort/i  } @$stderr;
  Error 3, "Warning on stderr:", @$stderr
    if grep { /warn|problem/i } @$stderr;
  Error 2, "Stderr output:", @$stderr
    if @$stderr;
  Error 1, "Error message on stdout:", @$stdout
    if grep { /error|fail|abnormal|abort/i } @$stdout;

  # return highest error status set by one of the previous commands
  return $return_status;
}

=item $safe = CWB::Shell::Quote($argument);

Safely quote I<$argument> as a command-line argument in B<bash> and B<tcsh> shells.
Simple strings that consist only of ASCII letters and digits, C<_>, C<->, C<.> and C</>
are passed through without quotes.  The B<CWB::Shell::Quote> function is vectorised, 
so multiple argument strings can be passed in a single call.

=cut

sub Quote {
  my @quoted = ();
  while (@_) {
    my $s = shift;
    if ($s =~ /^[A-Za-z0-9._\-\/]+$/) {
      push @quoted, $s;  # no need for quotes
    }
    else {
      $s =~ s/'/'"'"'/g;     # this trick safely escapes ' inside single quotes
      push @quoted, "'$s'";  # single quotes pass all characters verbatim except '
    }
  }
  return (wantarray) ? @quoted : shift @quoted;
}

=back 

=cut

## ======================================================================
##  parse, modify and create registry entries (in canonical format)
## ======================================================================

package CWB::RegistryFile;

use Carp;

=head1 REGISTRY FILE EDITING

Registry files in B<canonical format> can be loaded into B<CWB::RegistryFile> objects,
edited using the various access methods detailed below, and written back to disk. It
is also possible to create a registry entry from scratch and save it to a disk file.

Canonical registry files consist of a B<header> and a B<body>. The
B<header> begins with a NAME, ID, PATH, and optional INFO field

  NAME "long descriptive name"
  ID   my-corpus
  PATH /path/to/data/directory
  INFO /path/to/info/file.txt

followed by optional B<corpus property> definitions

  ##:: property1 = "value1"
  ##:: property2 = "value2"

The B<body> declares B<positional>, B<structural>, and B<alignment> attributes in
arbitrary order, using the following keywords

  ATTRIBUTE  word     # positional attribute
  STRUCTURE  np       # structural attribute
  ALIGNED    corpus2  # alignment attribute (CORPUS2 is target corpus)

Each attribute declaration may be followed by an alternative directory path on
the same line, if the attribute data is not stored in the HOME directory of the
corpus:

  ATTRIBUTE  lemma  /path/to/other/data/directory

The header fields, corpus properties, and attribute declarations are jointly
referred to as B<content lines>. Each content line may be preceded by an arbitrary
number of B<comment lines> (starting with a C<#> character) and B<blank lines>.
Trailing comments and blank lines (i.e. after the last content line in a registry
file) are allowed but will be ignored by B<CWB::RegistryFile>. Besides, each 
content line may include an B<in-line comment> which extends from the first C<#>
character to the end of the line (see examples above). Note that lines starting
with the special symbol C<##::> are interpreted as corpus property definitions
rather than comments.

=cut

# internal method: "return $self->error();" prints error message and returns undef
sub error {
  my ($self, @msg) = @_;
  print STDERR "CWB::RegistryFile:\n";
  foreach my $line (@msg) {
    print STDERR "  Error: $line\n";
  }
  return undef;
}

# internal function: read (and parse) one content line from filehandle $fh, including preceding comment lines
#   ($line, @comments) = read_segment($fh);
# $line is "" at end of file (so trailing commments might be salvaged)
sub read_segment {
  my $fh = shift;
  my @comments = ();
  while (<$fh>) {
    chomp;
    s/^\s+//;
    s/\s+$//;
    next if $_ eq ("#".("=" x 72)."#"); # header separator line -- ignore
    last unless /^$/ or (/^\#/ and not /^\#\#::/);
    s/^\#//;                                    # remove (first) comment marker from line
    push @comments, $_;
  }
  if ($_) {
    return ($_, @comments);
  }
  else { 
    return ("", @comments);
  }
}

# internal functions: map between attribute types (p, s, a) and keywords (ATTRIBUTE, STRUCTURE, ALIGNED)
sub type2keyword {
  my $type = shift;
  my %mapping = qw<p ATTRIBUTE  s STRUCTURE  a ALIGNED>;
  die "Internal error: type2keyword('$type') undefined."
    unless defined $mapping{$type};
  return $mapping{$type};
}

sub keyword2type {
  my $key = shift;
  my %mapping = qw<ATTRIBUTE p  STRUCTURE s  ALIGNED a>;
  die "Internal error: keyword2type('$key') undefined."
    unless defined $mapping{$key};
  return $mapping{$key};
}

=over 4

=item $reg = new CWB::RegistryFile;

=item $reg = new CWB::RegistryFile $filename;

The first form of the B<CWB::RegistryFile> constructor creates a new, 
empty registry entry. The mandatory fields have to be filled in by the
Perl script before the I<$reg> object can be saved to disk. It is also highly
advisable to declare at least the C<word> attribute. :-)

The second form attempts to read and parse the registry file I<$filename>. If
successful, a B<CWB::RegistryFile> object storing all relevant information is
returned.  If I<$filename> does not contain the character C</> and cannot be
found in the current directory, the constructor will automatically search the
standard registry directories for it.  The full pathname of the registry file
can later be determined with the B<filename> method.

If the load operation failed (i.e. the file does not exist or is not in the
canonical registry file format), an error message is printed and an undefined
value returned (so this module can be used e.g. to write a robust graphical
registry editor). Always check the return value of the constructor before
proceeding.

=cut

sub new {
  my $class = shift;
  my $filename = shift;
  my $self =                                    # create and initialise object
    {
     NAME => "",                                # name of corpus (defaults to empty string)
     ID => undef,                               # corpus ID (required)
     HOME => undef,                             # home directory (required)
     INFO => undef,                             # info file (optional, but highly recommended)
     PROPERTIES => [],                          # corpus properties ([property, value] pairs)
     ATT => {},                                 # attributes (att => 'p' / 's' / 'a')
     ATT_PATH => {},                            # data paths for attributs
     SERIALIZE => [],                           # order in which attributes are listed in the registry entry
     COMMENTS =>  {},                           # comments and/or blank lines preceding each content line
                                                # (att => [comment1, comment2, ...], ':NAME' => [...], '::property' => ...)
     LINECOMMENT => {},                         # line comments on content lines (att => comment, ':NAME' => comment, ...)
     FILENAME => undef,                         # filename of registry file (if loaded from file)
    };
  bless($self, $class);

  # if filename was specified, try loading registry entry (searches in registry directories if necessary)
  if (defined $filename) {
    if ($filename !~ /\// and not -f $filename) {
      my @dirs = CWB::RegistryDirectory();
      my @files = grep { -f $_ } map { "$_/".lc($filename) } @dirs; # corpus ID may be specified in uppercase
      return $self->error("Found multiple registry entries for corpus ".uc($filename).":", @files)
        if @files > 1;
      $filename = shift @files
        if @files;
    }
    return $self->error("Can't access registry file or corpus $filename")
      unless -r $filename;
    my $fh = CWB::OpenFile $filename;
    $self->{FILENAME} = $filename;
    # NAME (required)
    my ($l, @c) = read_segment($fh);
    return $self->error("Missing or misplaced NAME line in registry file $filename",
                        "  >> $l <<")
      unless $l =~ /^NAME\s+/;
    return $self->error("Syntax error in registry file $filename:",
                        "  >> $l <<",
                        "(expected >> NAME \" ... \" <<)")
      unless $l =~ /^NAME\s+\"((?:[^\"]|\\\\|\\\")*)\"\s*(\#.*)?$/;
    my ($v, $lc) = ($1, $2);
    $lc =~ s/^\#// if $lc;
    $self->{NAME} = $v;
    $self->{COMMENTS}->{':NAME'} = [@c];
    $self->{LINECOMMENT}->{':NAME'} = $lc
      if $lc;
    # ID (required)
    ($l, @c) = read_segment($fh);
    return $self->error("Missing or misplaced ID line in registry file $filename",
                        "  >> $l <<")
      unless $l =~ /^ID\s+/;
    return $self->error("Syntax error in registry file $filename:",
                        "  >> $l <<",
                        "(expected >> ID lowercase-name <<)")
      unless $l =~ /^ID\s+([a-z_][a-z0-9_-]*)\s*(\#.*)?$/;
    ($v, $lc) = ($1, $2);
    $lc =~ s/^\#// if $lc;
    $self->{ID} = $v;
    $self->{COMMENTS}->{':ID'} = [@c];
    $self->{LINECOMMENT}->{':ID'} = $lc
      if $lc;
    # HOME (required)
    ($l, @c) = read_segment($fh);
    return $self->error("Missing or misplaced HOME line in registry file $filename",
                        "  >> $l <<")
      unless $l =~ /^HOME\s+/;
    return $self->error("Syntax error in registry file $filename:",
                        "  >> $l <<",
                        "(expected >> HOME directory <<)")
      unless $l =~ /^HOME\s+(\S+|".+")\s*(\#.*)?$/;  # Can't really check whether path is valid
    ($v, $lc) = ($1, $2);
    $lc =~ s/^\#// if $lc;
    $v =~ s/^"|"$//g; # remove string delimiters if PATH is double-quoted string
    $self->{HOME} = $v;
    $self->{COMMENTS}->{':HOME'} = [@c];
    $self->{LINECOMMENT}->{':HOME'} = $lc
      if $lc;
    ($l, @c) = read_segment($fh);
    if ($l =~ /^INFO/) {
      # INFO (optional)
      return $self->error("Syntax error in registry file $filename:",
                          "  >> $l <<",
                          "(expected >> INFO filename <<)")
        unless $l =~ /^INFO\s+(\S+|".+")\s*(\#.*)?$/;        # Can't really check whether pathname is valid
      ($v, $lc) = ($1, $2);
      $lc =~ s/^\#// if $lc;
      $v =~ s/^"|"$//g; # remove string delimiters if INFO is double-quoted string
      $self->{INFO} = $v;
      $self->{COMMENTS}->{':INFO'} = [@c];
      $self->{LINECOMMENT}->{':INFO'} = $lc
        if $lc;
      ($l, @c) = read_segment($fh);
    }
    my %prop = ();                              # check for duplicate property entries
    while ($l =~ /^\#\#::/) {
      # Corpus Properties (optional)
      return $self->error("Syntax error in registry file $filename:",
                          "  >> $l <<",
                          "(expected >> ##:: property = \"...\" <<)")
        unless $l =~ /^\#\#::\s+([a-zA-Z_][a-zA-Z0-9_-]*)\s*=\s*(\"[^\"]*\"|[a-zA-Z_][a-zA-Z0-9_-]*)\s*(\#.*)?$/;
      my $p = $1;
      ($v, $lc) = ($2, $3);
      $lc =~ s/^\#// if $lc;
      $v =~ s/^\"//; $v =~ s/\"$//;             # if value is double-quoted string, remove quotes
      return $self->error("Corpus property $p listed twice in registry file $filename:",
                          "  >> $l <<",
                          "(previously set to $p = \"".$prop{$p}."\")")
        if exists $prop{$p};
      $prop{$p} = $v;
      push @{$self->{PROPERTIES}}, [$p => $v];
      $self->{COMMENTS}->{"::$p"} = [@c];
      $self->{LINECOMMENT}->{"::$p"} = $lc
        if $lc;
      ($l, @c) = read_segment($fh);
    }
    # Attributes (p-ATT, s-ATT, a-ATT, may be mixed)
    while ($l) {
      return $self->error("Syntax error in registry file $filename:",
                          "  >> $l <<",
                          "(expected ATTRIBUTE or STRUCTURE or ALIGNED line)")
        unless $l =~ s/^(ATTRIBUTE|STRUCTURE|ALIGNED)\s+//;
      my $key = $1;
      return $self->error("Syntax error in registry file $filename:",
                          "  >> $key $l <<",
                          "(expected >> $key name [ opt_path ] <<)")
        unless $l =~ /^([a-z_][a-zA-Z0-9_-]*)(\s+(\S+))?\s*(\#.*)?$/; # now more lenient to allow mixed case starting with lowercase letter
      my ($v, $p, $lc) = ($1, $3, $4);
      $lc =~ s/^\#// if $lc;
      return $self->error("Attribute $v declared twice in registry file $filename:",
                          "  >> $key $l <<",
                          "(previous declaration as ".type2keyword($self->{ATT}->{$v}).")")
        if exists $self->{ATT}->{$v};
      $self->{ATT}->{$v} = keyword2type($key);
      $self->{ATT_PATH}->{$v} = $p
        if $p;
      $self->{COMMENTS}->{$v} = [@c];
      $self->{LINECOMMENT}->{$v} = $lc
        if $lc;
      push @{$self->{SERIALIZE}}, $v;
      ($l, @c) = read_segment($fh);
    }
    # for the time being, we ignore trailing comments
    $fh->close;
  }

  return $self;
}

# DESTROY: no magic when the object is destroyed

=item $filename = $reg->filename;

Get the full pathname of the registry file represented by I<$reg>.  This value
is undefined if I<$reg> was created as a new (empty) registry entry.

=cut

sub filename ( $ ) {
  my $self = shift;
  return $self->{FILENAME};
}

=item $name = $reg->name;

=item $id   = $reg->id;

=item $home = $reg->home;

=item $info = $reg->info;

Get the values of the NAME, ID, HOME, and INFO fields from the registry file
header. Since the INFO field is optional, the B<info()> method may return an
undefined value.

=item $reg->name($value);

=item $reg->id($value);

=item $reg->home($value);

=item $reg->info($value);

=item $reg->delete_info;

Modify the NAME, ID, HOME, and INFO fields. The INFO field is optional and
may be deleted. 

=cut

sub name ( $;$ ) {
  my ($self, $newval) = @_;
  my $val = $self->{NAME};
  $self->{NAME} = $newval
    if defined $newval;
  return $val;
}

sub id ( $;$ ) {
  my ($self, $newval) = @_;
  my $val = $self->{ID};
  $self->{ID} = $newval
    if defined $newval;
  return $val;
}

sub home ( $;$ ) {
  my ($self, $newval) = @_;
  my $val = $self->{HOME};
  $self->{HOME} = $newval
    if defined $newval;
  return $val;
}

sub info ( $;$ ) {
  my ($self, $newval) = @_;
  my $val = $self->{INFO};
  $self->{INFO} = $newval
    if defined $newval;
  return $val;
}

# INFO field is optional, so allow user to delete it (associated comments will be lost)
sub delete_info ( $ ) {
  my $self = shift;
  $self->delete_line_comment(":INFO");
  $self->set_comments(":INFO");
  return delete $self->{INFO};
}

=item @properties = $reg->list_properties;

=item $value = $reg->property($property);

Corpus properties are key / value pairs. The B<list_properties()> method
returns a list of the keys, i.e. the names of defined properties. Use the
B<property()> method to obtain the value of a single property I<$property>.

=item $reg->property($property, $value);

=item $reg->delete_property($property);

You can also use the B<property()> method to set the value of a property
by passing a second argument. This will add a new corpus property if
I<$property> isn't already defined. Use B<delete_property()> to remove
a corpus property.

=cut

sub list_properties ( $ ) {
  my $self = shift;
  return map {$_->[0]} @{$self->{PROPERTIES}};
}

sub delete_property ( $$ ) {
  my ($self, $p) = @_;
  my $PROP = $self->{PROPERTIES};
  my $N = @$PROP;
  for (my $i = 0; $i < $N; $i++) {
    if ($PROP->[$i]->[0] eq $p) {
      splice(@$PROP, $i, 1);                    # remove this entry
      $self->delete_line_comment("::$p");
      $self->set_comments("::$p");
      last;
    }
  }
}

sub property ( $$;$ ) {
  my ($self, $p, $v) = @_;
  my $PROP = $self->{PROPERTIES};
  my $N = @$PROP;
  my $found = 0;
  my $previous = undef;
  for (my $i = 0; $i < $N; $i++) {
    if ($PROP->[$i]->[0] eq $p) {
      $found = 1;
      $previous = $PROP->[$i]->[1];
      $PROP->[$i]->[1] = $v
        if defined $v;
      last;
    }
  }
  if (defined $v and not $found) {
    push @$PROP, [$p => $v];
  }
  return $previous;
}

=item @attr = $reg->list_attributes;

=item @attr_of_type = $reg->list_attributes($type);

=item $type = $reg->attribute($att_name);

B<list_attributes()> returns the names of all declared attributes. The
B<attribute()> method returns the type of the specified attribute, or an
undefined value if the attribute is not declared. I<$type> is one of
C<'p'> (B<positional>), C<'s'> (B<structural>), or C<'a'> (B<alignment>). 
Passing one of these type codes to B<list_attributes()> will return
attributes of the selected type only. 

=cut

sub list_attributes ( $;$ ) {
  my ($self, $type) = @_;
  my @list = @{$self->{SERIALIZE}};
  if (defined $type) {
    $type = lc $type;
    @list = grep {$self->{ATT}->{$_} eq $type} @list;
  }
  return @list;
}

sub attribute ( $$ ) {
  my ($self, $name) = @_;
  return $self->{ATT}->{$name};
}

=item $reg->add_attribute($att_name, $type);

=item $reg->delete_attribute($att_name);

B<add_attribute()> adds an attribute of type I<$type> (B<p>, B<s>, or
B<a>, see above). The duplicate declaration of an attribute with the
same type is silently ignored. Re-declaration with a different type is
a fatal error. Use B<delete_attribute()> to remove an attribute of the
specified name, regardless of its type.

=cut

sub delete_attribute ( $$ ) {
  my ($self, $name) = @_;
  if (exists $self->{ATT}->{$name}) {
    @{$self->{SERIALIZE}} = grep {$_ ne $name} @{$self->{SERIALIZE}}; # remove attribute from serialization
    $self->delete_line_comment($name);
    $self->set_comments($name);
    return delete $self->{ATT}->{$name};
  }
  else {
    return undef;
  }
}

sub add_attribute( $$$ ) {
  my ($self, $name, $type) = @_;
  die "CWB::RegistryFile: invalid attribute type '$type' for attribute $name\n"
      unless $type =~ /^[PpSsAa]$/;
  $type = lc $type;
  my $previous = $self->{ATT}->{$name};         # check if attribute is already defined
  if (defined $previous) {
    die "CWB::RegistryFile: can't add $type-attribute $name, already declared as $previous-attribute\n"
      unless $previous eq $type;
    # nothing to do if attribute is already defined
  }
  else {
    $self->{ATT}->{$name} = $type;
    push @{$self->{SERIALIZE}}, $name;
  }
}

=item $directory = $reg->attribute_path($att_name);

=item $reg->attribute_path($att_name, $directory);

=item $reg->delete_attribute_path;

Use the B<attribute_path()> method to get and set the alternative
data path of attribute I<$att_name>. If no alternative path is
specified in the registry entry, an undefined value is returned.
When an alternative path is deleted with B<delete_attribute_path()>, 
the attribute will look for its data files in the HOME directory
of the corpus.

=cut

sub attribute_path ( $$;$ ) {
  my ($self, $name, $dir) = @_;
  if (defined $dir) {
    die "CWB::RegistryFile: can't set path $dir for undeclared attribute $name\n"
      unless exists $self->{ATT}->{$name};
    my $previous = $self->{ATT_PATH}->{$name};
    $self->{ATT_PATH}->{$name} = $dir;
    return $previous;
  }
  else {
    return $self->{ATT_PATH}->{$name};
  }
}

sub delete_attribute_path ( $$ ) {
  my ($self, $name) = @_;
  print STDERR "CWB::RegistryFile: WARNING: delete_attribute_path() for undeclared attribute $name (ignored)\n"
    unless exists $self->{ATT}->{$name};
  return delete $self->{ATT_PATH}->{$name};
}

# internal function: check format of comment location key (see documentation below for valid keys)
sub check_key ( $$ ) {
  my ($self, $key) = @_;
  if ($key =~ /^:(NAME|ID|HOME|INFO)$/) {
    return 1;                                   # key for one of the standard field
  }
  elsif ($key =~ /^::(.+)$/) {
    my $property = $1;
    die "CWB::RegistryFile: invalid comment key '$key' -- property $property not declared\n"
      unless grep {$_->[0] eq $property} @{$self->{PROPERTIES}};
    return 1;                                   # key for a corpus property
  }
  else {
    die "CWB::RegistryFile: invalid comment key '$key' -- attribute $key not declared\n"
      unless exists $self->{ATT}->{$key};
    return 1;
  }
  die "CWB::RegistryFile: malformed comment key '$key'\n";
}

=item @lines = $reg->comments($key);

=item $reg->add_comments($key, @lines);

=item $reg->set_comments($key, @lines);

=item $reg->set_comments($key);

Comment lines in a registry file are associated with the first content
line following the comments. They are available through the
B<comments()> method as a list of B<chomp>ed lines with the initial
C<#> character removed. Since comment lines may precede any kind of
content line, a special key I<$key> is used to identify the desired
content line. 

  $key = ":NAME";       header field (same for ":ID", ":HOME", ":INFO")
  $key = "::$property"; definition of corpus property $property
  $key = $att_name;     declaration of attribute $att_name

Use B<add_comments()> to add I<@lines> to the existing comments for 
I<$key>. The new comments are always inserted immediately before the
content line. The B<set_comments()> method overwrites existing comments
with I<@lines>. The second form deletes all comments for I<$key>
(replacing them with zero new comment lines). Note that C<""> represents
a blank line and C<"#..."> a comment line beginning with two sharps
C<##>. 

=cut

sub comments ( $$ ) {
  my ($self, $key) = @_;
  $self->check_key($key);
  my $comm_ref = $self->{COMMENTS}->{$key};
  if (defined $comm_ref) {
    return @$comm_ref;
  }
  else {
    return ();
  }
}

sub set_comments ( $$@ ) {
  my ($self, $key, @lines) = @_;
  $self->check_key($key);
  $self->{COMMENTS}->{$key} = [@lines];
}

sub add_comments ( $$@ ) {
  my ($self, $key, @lines) = @_;
  $self->check_key($key);
  $self->set_comments($key)                     # make sure we have an entry we can append to
    unless exists $self->{COMMENTS}->{$key};
  push @{$self->{COMMENTS}->{$key}}, @lines;
}

=item $comment = $reg->line_comment($key);

=item $reg->line_comment($key, $comment);

=item $reg->delete_line_comment($key);

Inline comments use the same I<$key> identifiers as comment lines.
Just as with the INFO field, the B<line_comment()> method allows you
to get and set inline comments, and B<delete_line_comment()> removes
an inline comment. 

=cut

sub line_comment ( $$;$ ) {
  my ($self, $key, $newval) = @_;
  $self->check_key($key);
  my $val = $self->{LINECOMMENT}->{$key};
  $self->{LINECOMMENT}->{$key} = $newval
    if defined $newval;
  return (defined $val) ? $val : "";
}

sub delete_line_comment ( $$ ) {
  my ($self, $key) = @_;
  $self->check_key($key);
  return delete $self->{LINECOMMENT}->{$key};
}

# internal function: helper methods for writing comments to disk file
sub write_comments ( $$$ ) {                    # $self->write_comments($fh, $key);
  my ($self, $fh, $key) = @_;
  my $comm = $self->{COMMENTS}->{$key};
  if (defined $comm) {
    foreach my $line (@$comm) {
      if ($line eq "") {
        print $fh "\n";                         # blank line
      }
      else {
        my $comm = $line;                       # make a copy so we don't modify original data
        $comm = " ".$comm
          unless $comm =~ /^(\#|\s)/;
        print $fh "#$comm\n";                   # begin line with comment marker '#'
      }
    }
  }
}

# internal function: helper method for writing inline comments to disk file
sub write_line_comment ( $$$ ) {                # $self->write_line_comment($fh, $key);   always writes newline
  my ($self, $fh, $key) = @_;
  my $comm = $self->{LINECOMMENT}->{$key};
  if (defined $comm) {
    $comm = " ".$comm
      unless $comm =~ /^(\#|\s)/;
    print $fh "\t#$comm";
  }
  print $fh "\n";
}

# helper function: write HOME or INFO path as double-quoted string if it is not a simple ID
sub _quote_path ( $ ) {
  my $path = shift;
  if ($path !~ /^[A-Za-z0-9_\-\/][A-Za-z0-9_\.\-\/]+$/) {
    $path =~ s/"/\\"/g; # escape all literal double quotes
    $path = "\"$path\"";
  }
  return $path;
}

=item $reg->write($filename);

Write registry file to disk in canonical format. I<$filename> has to be a full
absolute or relative path.  For safety reasons, the B<write()> method does
I<not> automatically save a file in the default registry directory.  Make sure
that the filename is all lowercase and identical to the corpus ID, or the CWB
tools and CQP will not be able to read the registry file.

If I<$reg> was initialised from a registry file, I<$filename> can be omitted.
In this case, the original file will automatically be overwritten.

=cut

sub write ( $;$ ) {
  my ($self, $filename) = @_;
  $filename = $self->filename
    unless defined $filename;
  die "CWB::RegistryFile: filename not specified for write() method\n"
    unless defined $filename;
  # check that required fields are defined before creating file
  die "CWB::RegistryFile: can't write $filename -- ID not set\n"
    unless defined $self->id;
  die "CWB::RegistryFile: can't write $filename -- HOME not set\n"
    unless defined $self->home;
  my $fh = CWB::OpenFile "> $filename";
  # write standard fields: NAME, ID, HOME [, INFO]
  $self->write_comments($fh, ":NAME");
  my $n = $self->name;
  $n =~ s/\\/\\\\/g;
  $n =~ s/"/\\"/g;
  print $fh "NAME \"$n\"";
  $self->write_line_comment($fh, ":NAME");
  $self->write_comments($fh, ":ID");
  print $fh "ID   ",$self->id;
  $self->write_line_comment($fh, ":ID");
  $self->write_comments($fh, ":HOME");
  print $fh "HOME ",_quote_path($self->home);
  $self->write_line_comment($fh, ":HOME");
  if (defined $self->info) {
    $self->write_comments($fh, ":INFO");
    print $fh "INFO ",_quote_path($self->info);
    $self->write_line_comment($fh, ":INFO");
  }
  # write corpus properties
  foreach my $pair (@{$self->{PROPERTIES}}) {
    my ($p, $v) = @$pair;
    $self->write_comments($fh, "::$p");
    print $fh "##:: $p = \"$v\"";
    $self->write_line_comment($fh, "::$p");
  }
  print $fh "#", "=" x 72, "#\n"; # header separator bar -- must be ignored when reading in
  # write attributes (in the order given in SERIALIZE) 
  foreach my $att (@{$self->{SERIALIZE}}) {
    $self->write_comments($fh, $att);
    my $type = $self->attribute($att);
    print $fh type2keyword($type), " $att";
    my $path = $self->attribute_path($att);
    print $fh "\t", _quote_path($path)
      if $path;
    $self->write_line_comment($fh, $att);
  }
  $fh->close;
}

=back

=cut

## ======================================================================

package CWB;

1;

__END__

=head1 COPYRIGHT

Copyright (C) 1999-2014 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
