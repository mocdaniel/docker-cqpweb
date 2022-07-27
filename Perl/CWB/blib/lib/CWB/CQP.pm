package CWB::CQP;
# -*-cperl-*-

=head1 NAME

CWB::CQP - Interact with a CQP process running in the background

=head1 SYNOPSIS

  use CWB::CQP;

  # start CQP server process in the background
  $cqp = new CWB::CQP;
  $cqp = new CWB::CQP("-r /corpora/registry", "-I /global/init.cqp");

  # check for specified or newer CQP version
  $ok = $cqp->check_version($major, $minor, $beta);

  # activate corpus in managed mode (automatic character encoding conversion)
  $cqp->activate($corpus);

  # execute CQP command (blocking mode) and check for error
  @lines = $cqp->exec($my_cmd);
  unless ($cqp->ok) {
    @cqp_error_message = $cqp->error_message;
    my_error_handler();
  }

  # it's easier to use an automatic error handler
  $cqp->set_error_handler(\&my_error_handler); # user-defined
  $cqp->set_error_handler('die'); # built-in, useful for one-off scripts

  # read TAB-delimited table from count, group, tabulate, ...
  @table = $cqp->exec_rows($my_cmd);

  # run CQP command in background (non-blocking mode)
  $cqp->run($my_cmd);
  if ($cqp->ready) {  # specify optional timeout in seconds
    my $line = $cqp->getline;
    my @fields = $cqp->getrow; # TAB-delimited output
  }
  @lines = $cqp->getlines(10); # reads 10 lines, blocking if necessary

  # execute in query lock mode (to improve security of CGI scripts)
  $cqp->begin_query;
    # execute untrusted CQP queries
  $cqp->end_query;
  
  @lines = $cqp->exec_query($untrusted_query); # convenience wrapper
  
  # dump/undump a named query into/from a table of corpus positions
  @matches = $cqp->dump("Last" [, $from, $to]);
  $cqp->undump("Copy", @matches);  # produces copy of "Last"

  # safely quote regular expressions and literal strings for CQP queries
  $query = $cqp->quote('[0-9]+"-[a-z-]+');      # picks single or double quotes
  $query = $cqp->quote($cqp->quotemeta($word)); # escape all metacharacters

  # activate CQP progress messages during query execution
  $cqp->progress_on;
  $status = $cqp->progress; # after starting CQP command with run()
  ($total, $pass, $n_passes, $msg, $percent) = $cqp->progress_info;
  $cqp->progress_off;

  $cqp->set_progress_handler(\&my_progress_handler); # user-defined handler

  # shut down CQP server (exits gracefully)
  undef $cqp;

=head1 DESCRIPTION

A B<CWB::CQP> object represents an instance of the corpus query processor CQP
running as a background process.  By calling suitable methods on this object,
arbitrary CQP commands can be executed and their output can be captured.
The C<STDERR> stream of the CQP process is monitored for error messages,
which can automatically trigger an error handler.

Every B<CWB::CQP> object has its own CQP background process and communication is
fully asynchronous.  This enables scripts to perform other actions while a long
CQP command is executing, or to run multiple CQP instances in parallel.

In managed mode (enabled with the B<activate> method), the API works consistently
with Perl Unicode strings, which are automatically translated to the character
encoding of the CWB corpus in the background.

=cut

use warnings;
use strict;

use sigtrap qw(die PIPE);       # catch write errors to background CQP process

use CWB;
use Carp;
use FileHandle;
use IPC::Open3;
use IO::Select;
use Encode;

use POSIX ":sys_wait_h";         # for non-blocking waitpid
                                 # TODO potential win32 problem?

## package global variables
our @CQP_options = "-c";         # always run CQP in child mode
our $CQP_version = "3.0.0";      # required version of CQP (checked at startup)

our %Child = ();                 # keep track of running CQP processes

sub SIGCHLD_handler {
  foreach my $child_pid (keys %Child) {
    my $reaped = waitpid($child_pid, WNOHANG);
    die "CWB::CQP: Child process #$child_pid terminated unexpectedly -- not safe to continue\n"
      if $reaped > 0;
  }
  return; # allow other signal handlers to reap the SIGCHLD
}
$SIG{CHLD} = \&SIGCHLD_handler;

=head1 METHODS

The following methods are available:

=over 4

=item I<$cqp> = B<new> CWB::CQP;

=item I<$cqp> = B<new> CWB::CQP '-r /corpora/registry', '-l /data/cqpresults';

Spawn new CQP background process.  The object I<$cqp> can then be used to communicate with 
this CQP instance.  Optional arguments of the B<new> method are passed as command-line
options to CQP.  Use at your own risk.

=cut

## CWB::CQP object constructor
sub new {
  my $class = shift;            # class name
  my $self = {};                # namespace for new CQP class object
  my @options = @_;             # CQP command-line options (use at your own risk)
  # split options with values, e.g. "-r /my/registry" => "-r", "/my/registry" (doesn't work for multiple options in one string)
  @options = map { (/^(--?[A-Za-z0-9]+)\s+(.+)$/) ? ($1, $2) : $_ } @options;

  ## run CQP server in the background
  my $in = $self->{'in'} = new FileHandle;   # stdin of CQP
  my $out = $self->{'out'} = new FileHandle; # stdout of CQP
  my $err = $self->{'err'} = new FileHandle; # stderr of CQP
  my $pid = open3($in, $out, $err, $CWB::CQP, @CQP_options, @options);
  $self->{'pid'} = $pid; # child process ID (so process can be killed if necessary)
  $Child{$pid} = 1;      # a weak ref to $self might be better here
  $in->autoflush(1);     # make sure that commands sent to CQP are always flushed immediately

  $self->{'encoder'} = undef; # in managed mode, an Encode object for character encoding conversion
  binmode($in, ":raw");
  binmode($out, ":raw");
  binmode($err, ":raw");

  my ($need_major, $need_minor, $need_beta) = split /\./, $CQP_version; # required CQP version
  $need_beta = 0 unless $need_beta;

  my $version_string = $out->getline; # child mode (-c) should print version on startup
  chomp $version_string;
  croak "ERROR: CQP backend startup failed ('$CWB::CQP @CQP_options @options')\n"
    unless $version_string =~ /^CQP\s+(?:\w+\s+)*([0-9]+)\.([0-9]+)(?:\.b?([0-9]+))?(?:\s+(.*))?$/;
  $self->{'major_version'} = $1;
  $self->{'minor_version'} = $2;
  $self->{'beta_version'} = $3 || 0;
  $self->{'compile_date'} = $4 || "unknown";
  croak "ERROR: CQP version too old, need at least v$CQP_version ($version_string)\n"
    unless ($1 > $need_major or
            $1 == $need_major
            and ($2 > $need_minor or
                 ($2 == $need_minor and $3 >= $need_beta)));

  ## command execution
  $self->{'command'} = undef; # CQP command string that is currently being processed (undef = last command has been completed)
  $self->{'lines'} = [];      # array of output lines read from CQP process
  $self->{'buffer'} = "";     # read buffer for standard output from CQP process
  $self->{'block_size'} = 256;  # block size for reading from CQP's output and error streams
  $self->{'query_lock'} = undef;# holds random key while query lock mode is active

  ## error handling (messages on stderr)
  $self->{'error_handler'} = undef; # set to subref for user-defined error handler
  $self->{'status'} = 'ok';         # status of last executed command ('ok' or 'error')
  $self->{'error_message'} = [];    # arrayref to array containing message produced by last command (if any)

  ## handling of CQP progress messages
  $self->{'progress'} = 0;             # whether progress messages are activated
  $self->{'progress_handler'} = undef; # optional callback for progress messages
  $self->{'progress_info'} = [];       # contains last available progress information: [$total_percent, $pass, $n_passes, $message, $percent]

  ## debugging (prints more or less everything on stdout)
  $self->{'debug'} = 0;

  ## select vectors for CQP output (stdout, stderr, stdout|stderr)
  $self->{'select_err'} = new IO::Select($err);
  $self->{'select_out'} = new IO::Select($out);
  $self->{'select_any'} = new IO::Select($err, $out);

  ## CQP object setup complete
  bless($self, $class);

  ## the following command will collect and ignore any output which may have been produced during startup
  $self->exec("set PrettyPrint off"); # pretty-printing should be turned off for non-interactive use

  return $self;
}

=item B<undef> I<$cqp>;

Exit CQP background process gracefully by issuing an C<exit;> command.
This is done automatically when the variable I<$cqp> goes out of scope.
Note that there may be a slight delay while B<CWB::CQP> waits for the CQP
process to terminate.

B<Do NOT> send an C<exit;> command to CQP explicitly (with B<exec> or B<run>).
This looks like a program crash to B<CWB::CQP> and will result in immediate
termination of the Perl script.

=cut

sub DESTROY {
  my $self = shift;
  my $pid = $self->{'pid'};
  my $alive = delete $Child{$pid}; # remove from list of children so no longer caught by signal handler
  
  if ($alive && $self->{'command'}) {
    while ($self->_update) {} # read pending output from active command
  }
  my $out = $self->{'out'};
  if (defined $out) {
    $out->print("exit")         # exit CQP backend
      if $alive;
    $out->close;
  }
  my $in = $self->{'in'};
  if (defined $in) {
    $in->close;
  }
  waitpid $pid, 0  # wait for CQP to exit and reap background process
}

=item I<$ok> = I<$cqp>->B<check_version>(I<$major>, I<$minor>, I<$beta>);

Check for minimum required CQP version, i.e. the background process has
to be CQP version I<$major>.I<$minor>.I<$beta> or newer.
I<$minor> and I<$beta> may be omitted, in which case they default to 0.
Note that the B<CWB::CQP> module automatically checks whether the CQP version
is compatible with its own requirements when a new object is created.
The B<check_version> method can subsequently be used to check for a more
recent release that provides functionality needed by the Perl script.

=cut

sub check_version {
  my $self = shift;
  my ($major, $minor, $beta) = @_;
  $minor = 0 unless defined $minor;
  $beta = 0 unless defined $beta;

  my $maj = $self->{'major_version'};
  my $min = $self->{'minor_version'};
  my $bet = $self->{'beta_version'};
  if ($maj > $major or
      ($maj == $major
       and ($min > $minor or
            ($min == $minor and $bet >= $beta)))
     ) {
    return 1;
  }
  else {
    return 0;
  }
}

=item I<$version_string> = I<$cqp>->B<version>;

Returns formatted version string for the CQP background process, e.g. C<2.2.99> or C<3.0>.

=cut

sub version {
  my $self = shift;
  my $version = $self->{'major_version'}.".".$self->{'minor_version'};
  my $beta = $self->{'beta_version'};
  $version .= ".$beta"
    if $beta > 0;
  return $version;
}

=item I<$cqp>->B<activate>(I<$corpus>);

Activate I<$corpus> and enable B<managed mode>, i.e. automatic conversion between Perl
Unicode strings and the character encoding of the CWB corpus.  Conversion works in
both directions, so CQP commands and queries must be passed as Perl Unicode strings and
all return values are guaranteed to be Perl Unicode strings.

Managed mode simplifies interaction with CWB corpora in different encodings and ensures
that Perl string operations are carried out correctly with Unicode character semantics
(string length, case conversion for non-ASCII letters, Unicode character classes in
regular expressions, etc.).

Possible reasons for using non-managed (raw) mode are: (i) that Latin1-encoded corpora
can be processed faster as raw byte sequences; and (ii) that arbitrary byte values can
be handled, even if they are not valid Latin1 code points.

B<NB:> Once managed mode has been enabled, make sure always to use B<activate> to switch
to a different corpus.  If the corpus is activated with a plain B<exec> commmand, the
I<$cqp> object will not be notified of changes in character encoding.

Pass B<undef> to disable managed mode and change back to raw byte semantics.

=cut

sub activate {
  croak 'USAGE:  $cqp->activate($corpus);'
    unless @_ == 2;
  my $self = shift;
  my $corpus = shift;
  my $in = $self->{'in'};
  my $out = $self->{'out'};
  my $err = $self->{'err'};
  if (not defined $corpus) {
    $self->{'encoder'} = undef;
  }
  else {
    $corpus = uc($corpus) unless $corpus =~ /:/; # enforce uppercase unless a subcorpus is activated
    $self->exec($corpus); # activate corpus (will raise an error in the usual way if corpus cannot be activated)
    if ($self->ok) {
      my @info = $self->exec("info");
      my $charset = undef;
      foreach (@info) {
        if (/^Charset:\s+(\S+)$/) {
          $charset = $1;
          last;
        }
      }
      my $encoder = (defined $charset) ? find_encoding($charset) : undef;
      if (defined $encoder) {
        $self->{'encoder'} = $encoder;
      }
      else {
        $self->error("Corpus $corpus does not declare a known character encoding.  Switching to non-managed mode.");
        $self->{'encoder'} = undef;
      }
    }
  }
}

## INTERNAL:
##    $lines_read = $self->_update([$timeout]);
## This is the main "workhorse" of the CWB::CQP module.  It checks for output from CQP process
## (stdout and stderr), updates progress status, fills internal buffers, and calls error and
## progress handlers if necessary.  The optional $timeout specifies how many seconds to wait for
## output; the default is 0 seconds, i.e. non-blocking mode, while a negative value blocks.
## NB: $lines_read includes the .EOL. terminator line, so it is safe to keep calling _update()
## until a non-zero value is returned (even if a CQP command fails with an error message).
sub _update {
  my $self = shift;
  my $timeout = shift || 0;
  $timeout = undef
    if $timeout < 0;
  my $stderr_buffer = "";
  my $lines = 0; # how many lines have successfully been read from stdout
  my $encoder = $self->{'encoder'};

  while ($self->{'select_any'}->can_read($timeout)) {
    ## STDERR -- read all available output on stderr first
    if ($self->{'select_err'}->can_read(0)) {
      sysread $self->{'err'}, $stderr_buffer, $self->{'block_size'}, length($stderr_buffer); # append to $stderr_buffer
    }

    ## STDOUT -- if there is no more data on stderr, we should be able to read from stdout
    elsif ($self->{'select_out'}->can_read(0)) {
      sysread $self->{'out'}, $self->{'buffer'}, $self->{'block_size'}, length($self->{'buffer'}); # append to object's input buffer
      if ($self->{'buffer'} =~ /\n/) {
        ## if there's a complete line in the input buffer, split off all lines
        my @new_lines = split /\n/, $self->{'buffer'}, -1; # make sure that last line is handled correctly if buffer ends in \n
        $self->{'buffer'} = pop @new_lines; # last entry is incomplete line ("" if buffer ended in \n) => return to input buffer
        foreach my $line (@new_lines) {
          ## skip blank line printed after each CQP command
          next if $line eq "";
          ## handle progress messages if ProgressBar has been activated
          if ($self->{'progress'} and $line =~ /^-::-PROGRESS-::-/) {
              my ($pass, $n_passes, $message); 
              (undef, $pass, $n_passes, $message) = split /\t/, $line;
              my $percent = ($message =~ /([0-9]+)\%\s*complete/) ? $1+0 : undef; # extract progress percentage, if present
              my $total_percent = (100 * ($pass - 1) + ($percent || 0)) / $n_passes; # estimate total progress ($percent assumed to be 0% if not given)
              $self->{'progress_info'} = [$total_percent, $pass, $n_passes, $message, $percent];
              my $handler = $self->{'progress_handler'};
              if (ref($handler) eq 'CODE') {
                $handler->($total_percent, $pass, $n_passes, $message, $percent); # call user-defined progress handler
              }
          }
          ## regular output lines are collected in object's line buffer
          else {
            $line = $encoder->decode($line) if defined $encoder;
            push @{$self->{'lines'}}, $line;
            $lines++;
          }
        }
      }
      last if $lines > 0;       # if we have read a line and there is no output on stderr, return from function
    }

    ## ERROR -- we should never reach this point
    else {
      die "CWB::CQP: INTERNAL ERROR in _update() -- no data on stdout or stderr of CQP child process";
    }
  }

  if ($stderr_buffer ne "") {
    $self->{'status'} = 'error'; # any output on stderr indicates that something went wrong
    my @lines = split /\n/, $stderr_buffer;
    @lines = map {$encoder->decode($_)} @lines
      if defined $encoder;
    $self->error(@lines); # may call error handler and abort, or print message and continue 
    # note that error() method automatically adds lines to internal error_message buffer
  }
  return $lines;
}

=item I<$cqp>->B<run>(I<$cmd>);

Start a single CQP command I<$cmd> in the background.  This method returns immediately.
Command output can then be read with the B<getline>, B<getlines> and B<getrow> methods.
If asynchronous communication is desired, use B<ready> to check whether output is available.

It is an error to B<run> a new command before the output of the previous command has completely
been processed.

=cut

sub run {
  croak 'USAGE:  $cqp->run($cmd);'
    unless @_ == 2;
  my $self = shift;
  my $cmd = shift;
  my $debug = $self->{'debug'};
  my $encoder = $self->{'encoder'};

  $cmd =~ s/\n+/ /g;            # make sure there are no newline characters (to be on the safe side)
  $cmd =~ s/(;\s*)+$//;         # ";" will be added when $cmd is sent to CQP

  my $active_cmd = $self->{'command'};
  croak "Error: new CQP command issued while '$active_cmd' is still being processed"
    if $active_cmd;

  $self->{'command'} = "$cmd;";
  $self->{'status'} = 'ok';
  $self->{'buffer'} = "";
  $self->{'lines'} = [];
  $self->{'error_message'} = [];

  print "CQP << $cmd;\n"
    if $debug;
  $cmd = $encoder->encode($cmd, Encode::FB_PERLQQ) # turn invalid chars into hex escapes, so CQP can try to deal with them
    if defined $encoder;
  $self->{'in'}->print("$cmd;\n .EOL.;\n"); # append .EOL. command to mark end of CQP output
}

=item I<$num_of_lines> = I<$cqp>->B<ready>;

=item I<$num_of_lines> = I<$cqp>->B<ready>(I<$timeout>);

Check if output from current CQP command is available for reading with B<getline> etc.,
returning the number of lines currently held in the input buffer (possibly including an
end-of-output marker line that will not be returned by B<getline> etc.).  If there is no
active command, returns B<undef>.

The first form of the command returns immediately.  The second form waits up to I<$timeout>
seconds for CQP output to become available.  Use a negative I<$timeout> for blocking mode.

=cut

sub ready {
  my $self = shift;
  my $timeout = shift;

  my $lines = @{$self->{'lines'}};
  return $lines            # output has already been buffered => ready to read
    if $lines > 0;
  return undef             # no command active => undefined state
    unless $self->{'command'};
  return $self->_update($timeout); # try to read from CQP process & return number of lines available (NB: line buffer was empty before)
}

## INTERNAL: reset internal status after command has been completed, check that there is no extra output
sub _eol {
  my $self = shift;
  while ($self->_update > 0) { 1 } # check for any pending output from CQP process
  carp "CWB::CQP:  Unexpected CQP output after end of command:\n",
    map {" | $_\n"} @{$self->{'lines'}},
      "(command was: ".$self->{'command'}.")"
        if @{$self->{'lines'}} > 0;
  $self->{'lines'} = [];
  $self->{'command'} = undef; # no active command now
}

=item I<$line> = I<$cqp>->B<getline>;

Read one line of output from CQP process, blocking if necessary until output beomes available.
Returns B<undef> when all output from the current CQP command has been read.

=cut

sub getline {
  croak 'USAGE:  $line = $cqp->getline;'
    unless @_ == 1;
  my $self = shift;
  croak 'CWB::CQP:  $cqp->getline called without active CQP command'
    unless $self->{'command'};
  my $debug = $self->{'debug'};

  $self->_update(-1)            # fill line buffer if necessary (blocking mode)
    unless @{$self->{'lines'}} > 0;

  my $line = shift @{$self->{'lines'}};
  if ($line eq '-::-EOL-::-') { 
    ## special line printed by ".EOL.;" marks end of CQP output
    print "CQP ", "-" x 60, "\n"
      if $debug;
    $self->_eol;
    return undef;               # undef return value marks end of output
  }
  else {
    print "CQP >> $line\n"
      if $debug;
    return $line;
  }
}

=item I<@lines> = I<$cqp>->B<getlines>(I<$n>);

Read I<$n> lines of output from the CQP process, blocking as long as necessary.  An explicit B<undef> element is included at the end of the output of a CQP command.  Note that B<getlines> may return fewer than I<$n> lines if the end of output is reached.

Set C<I<$n> = 0> to read all complete lines currently held in the input buffer (as indicated by the B<ready> method), or specify a negative value to read the complete output of the active CQP command.

=cut

sub getlines {
  croak 'USAGE:  @lines = $cqp->getlines($n);'
    unless @_ == 2 and $_[1] =~ /^-?[0-9]+$/ and wantarray;
  my $self = shift;
  my $n_lines = shift;
  my @lines = ();
  if ($n_lines == 0) {
    while (my $line = shift @{$self->{'lines'}}) {
      if ($line eq '-::-EOL-::-') {
        $self->_eol;
        push @lines, undef;
      }
      else {
        push @lines, $line;
      }
    }
  }
  else {
    while ($n_lines != 0) {     # if $n_lines < 0, reads complete output of CQP command
      while ($n_lines != 0 and @{$self->{'lines'}} > 0) {
        my $line = shift @{$self->{'lines'}};
        if ($line eq '-::-EOL-::-') {
          $self->_eol;
          push @lines, undef;
          $n_lines = 0;
        }
        else {
          push @lines, $line;
          $n_lines--;
        }
      }
      $self->_update(-1)     # wait for CQP output to become available (in $self's input buffer)
        if $n_lines != 0;
    }
    return (wantarray) ? @lines : shift @lines;
  }
}

=item I<@lines> = I<$cqp>->B<exec>(I<$cmd>);

A convenience function that executes CQP command I<$cmd>, waits for it to complete, and returns all lines of
output from the command.

Fully equivalent to the following two commands, except that the trailing B<undef> returnd by B<getlines> is not included in the output:

  $cqp->run($cmd);
  @lines = $cqp->getlines(-1);

=cut

sub exec {
  croak 'USAGE:  $cqp->exec($cmd);'
    unless @_ == 2;
  my $self = shift;
  my $cmd = shift;
  my $debug = $self->{'debug'};

  $self->run($cmd);
  my @result = $self->getlines(-1);
  my $eol = pop @result;
  if (defined $eol) {
    die "CWB::CQP:  INTERNAL ERROR in _exec() -- missing 'undef' at end of command output (ignored)";
    push @result, $eol; # seems to be regular line, so push it back onto result list
  }
  return @result;
}

=item I<@fields> = I<$cqp>->B<getrow>;

=item I<@rows> = I<$cqp>->B<exec_rows>(I<$cmd>);

Convenience functions for reading TAB-delimited tables, which are generated by CQP commands such as B<count>, B<group>, B<tabulate> and B<show cd>.

B<getrow> returns a single row of output, split into TAB-delimited fields.  If the active CQP command has completed, it returns an empty list.

B<exec_rows> executes the CQP command I<$cmd>, waits for it to complete, and then returns the TAB-delimited table as an array of array references.  You can then use multiple indices to access a specific element of the table, e.g. C<I<@rows>[41][2]> for the third column of the 42nd row.

=cut

sub getrow {
  croak 'USAGE:  @fields = $cqp->getrow;'
    unless @_ == 1 and wantarray;
  my $self = shift;
  my $line = $self->getline;
  return ()
    unless defined $line;
  return split /\t/, $line;
}

sub exec_rows {
  croak 'USAGE:  @rows = $cqp->exec_rows($cmd);'
    unless @_ == 2 and wantarray;
  my $self = shift;
  my $cmd = shift;
  my @lines = $self->exec($cmd); ## **TODO** this function could be optimised to collect arrayrefs directly
  return map { [ split /\t/ ] } @lines;
}

=item I<$cqp>->B<begin_query>;

=item I<$cqp>->B<end_query>;

Enter/exit query lock mode for safe execution of CQP queries entered by an untrusted user (e.g. from a Web interface).  In query lock mode, all interactive CQP commands are temporarily disabled; in particular, it is impossible to access files or execute shell commands from CQP.

=cut

sub begin_query {
  my $self = shift;
  croak 'CWB::CQP:  $cqp->begin_query; has been called while query lock mode is already active'
    if $self->{'query_lock'};
  my $key = 1 + int rand(1_000_000); # make sure this is a TRUE value
  $self->exec("set QueryLock $key");
  $self->{'query_lock'} = $key;
}

sub end_query {
  my $self = shift;
  my $key = $self->{'query_lock'};
  if ($key) {
    $self->exec("unlock $key");
    $self->{'query_lock'} = undef;
  }
  else {
    carp 'CWB::CQP:  $cqp->end_query; has been called, but query lock  mode is not active (ignored)';
  }
}

=item I<@lines> = I<$cqp>->B<exec_query>(I<$query>);

Convenience function to execute a CQP query I<$query> in safe query lock mode, wait for it to complete, and return its output as a list of lines.

Fully equivalent to the following sequence:

  $cqp->begin_query;
  @lines = $cqp->exec($query);
  $cqp->end_query;

=cut

sub exec_query {
  my $self = shift;
  my $query = shift;
  my @result = ();              # store result from query exec() call
  my @errmsg = ();              # collect CQP error messages
  my $error = 0;                # keep track of errors (in case error handler is not set)

  $self->begin_query;
  if ($self->status ne 'ok') {
    push @errmsg, $self->error_message;
    $error = 1;
  }

  unless ($error) {
    @result = $self->exec($query); # query execution might not be safe if begin_query() failed
    if ($self->status ne 'ok') {
      push @errmsg, $self->error_message;
      $error = 1;
    }
  }

  $self->end_query;             # try to unlock even if query failed
  if ($self->status ne 'ok') {
    push @errmsg, $self->error_message;
    $error = 1;
  }

  # now set combined error status & error message
  $self->{'status'}  = ($error) ? 'error' : 'ok';
  $self->{'error_message'} = \@errmsg;
  return @result;
}

=item I<@table> = I<$cqp>->B<dump>(I<$named_query> [, I<$from>, I<$to>]);

Dump a named query result I<$named_query> (or a part of it ranging from line I<$from> to line I<$to>) into a table of corpus positions, where each row corresponds to one match of the query.  The table always has four columns for B<match>, B<matchend>, B<target> and B<keyword> positions, some of which may be C<-1> (undefined).

This function is a wrapper around the CQP command C<dump I<$named_query> I<$from> I<$to>;> provided for symmetry with the B<undump> command.

=cut

sub dump {
  my $self = shift;
  my $nqr = shift;
  my $from = shift;
  my $to = shift;
  my @matches = ();

  croak 'USAGE:  @rows = $cqp->dump($named_query [, $from, $to]);'
    if ((not defined $nqr) or
        (defined $from and not defined $to));
  $from = "" unless defined $from;
  $to = "" unless defined $to;

  return $self->exec_rows("dump $nqr $from $to");
}

=item I<$cqp>->B<undump>(I<$named_query>, I<@table>);

Upload a table of corpus positions to a named query result in CQP.  I<@table> must be an array of array references, with two, three or four columns (where the third and fourth column hold B<target> and B<keyword> anchors, respectively).  All rows in I<@table> must have the same number of columns.  Use C<-1> for undefined anchor values.

This method is not just a trivial wrapper around CQP's B<undump> command.  It stores the data in an appropriate format in a temporary disk file, and determines the correct form of the CQP command based on the number of columns in the table.

=cut

sub undump {
  croak 'USAGE:  $cqp->undump($named_query, @rows);'
    unless @_ >= 2;
  my $self = shift;
  my $nqr = shift;

  my $with = "";                             # undump with target and keyword?
  my $n_el = undef; # number of anchors for each match (will be determined from first row)
  my $n_matches = @_;              # number of matches (= remaining arguments)

  my $tf = new CWB::TempFile "undump.gz";       # need to read undump table from temporary file
  $tf->write("$n_matches\n");
  foreach my $row (@_) {
    my $row_el = @$row;
    if (not defined $n_el) {
      $n_el = $row_el;
      croak "CQP: row arrays in undump table must have between 2 and 4 elements (first row has $n_el)"
        if $n_el < 2 or $n_el > 4;
      $with = "with target"
        if $n_el >= 3;
      $with .= " keyword"
        if $n_el >= 4;
    }
    else {
      croak "CQP: all rows in undump table must have the same length (first row = $n_el, this row = $row_el)"
        unless $row_el == $n_el;
    }
    $tf->write(join("\t", @$row), "\n");
  }
  $tf->finish;

  # now send undump command with filename of temporary file
  my $tempfile = $tf->name;
  $self->exec("undump $nqr $with < 'gzip -cd $tempfile |'");

  $tf->close;                                   # delete temporary file

  return $self->ok;                             # return success status of undump command
}

=item I<$status> = I<$cqp>->B<status>;  # "ok" or "error"

=item I<$ok> = I<$cqp>->B<ok>;

=item I<@lines> = I<$cqp>->B<error_message>;

=item I<$cqp>->B<error>(I<@message>);

Error handling functions.  B<status> returns the status of the last CQP command executed, which is either C<'ok'> or C<'error'>.  B<ok> returns B<true> or B<false>, depending on whether the last command was completed successfully (i.e., it is a simple convenience wrapper for the expression C<($cqp->status eq 'ok')>).  B<error_message> returns the error message (if any) generated by the last CQP command, as a list of B<chomp>ed lines.

B<error> is an internal function used to report CQP errors.  It may also be of interest to application programs if a suitable error handler has been defined (see below).

=cut

## query CQP object's status and error messages
sub status {
  my $self = shift;

  return $self->{'status'};
}

sub ok {
  my $self = shift;
  return ($self->status eq 'ok'); # convenient wrapper function to check for CQP errors
}

sub error_message {
  my $self = shift;
  my $aref = $self->{'error_message'};

  return @{$aref};
}

## throw CQP error (optionally through user-defined error handler)
sub error {
  my $self = shift;
  my @message = @_;

  $self->{'status'} = 'error';     # set status to error
  $self->{'error_message'} = [@message]; # and remember error message in case the handler ignores the error

  if (ref($self->{'error_handler'}) eq 'CODE') {
    $self->{'error_handler'}->(@message); # call error handler if a suitable subref has been installed
  }
  else {
    warn "\n", "=+===CWB::CQP ERROR=====\n", # default behaviour is to issue a warning on stderr
      (map {" | $_\n"} @message), "=+======================\n"; 
  }
}

=item I<$cqp>->B<set_error_handler>(I<&my_error_handler>);

=item I<$cqp>->B<set_error_handler>('die' | 'warn' | 'ignore');

The first form of the B<set_error_handler> method activates a user-defined error handler.  The argument is a reference to a named or anonymous subroutine, which will be called whenever a CQP error is detected (or an error is raised explicitly with the B<error> method).  The error message is passed to the handler as an array of B<chomp>ed lines.  If the error handler returns, the error condition will subsequently be ignored (but still be reported by B<status> and B<ok>).

The second form of the method activates one of the built-in error handlers:

=over 4

=item *

B<C<'die'>> aborts program execution with an error message; this handler is particularly convenient for one-off scripts or command-line utilities that do not need to recover from error conditions.

=item *

B<C<'warn'>> prints the error message on STDERR, but continues program execution.  This is the default error handler of a new B<CWB::CQP> object.

=item *

B<C<'ignore'>> silently ignores all errors.  The application script should check for error conditions after every CQP command, using the B<ok> or B<status> method.

=back 

=cut

## set user-defined error handler (or built-in handlers 'die', 'warn' [default], 'ignore')
sub set_error_handler {
  my $self = shift;
  my $handler = shift;

  if (defined $handler) {
    my $type = ref $handler;
    if ($type ne 'CODE') {
      $handler = lc($handler);
      croak 'USAGE:  $cqp->set_error_handler( \&my_error_handler | "die" | "warn" | "ignore" );'
        unless $handler =~ /^(die|warn|ignore)$/;
      if ($handler eq 'die') {
        $handler = \&_error_handler_die;
      }
      elsif ($handler eq 'warn') {
        $handler = undef;       # default behaviour if no error handler is specified
      }
      elsif ($handler eq 'ignore') {
        $handler = \&_error_handler_ignore;
      }
    }
  }
  $self->{'error_handler'} = $handler;
}

## INTERNAL: built in error handlers for 'die' and 'ignore' modes
sub _error_handler_die {
  croak "\n", "=+===CWB::CQP ERROR=====\n", (map {" | $_\n"} @_), "=+== occurred";
}

sub _error_handler_ignore {
  # do nothing
}

=item I<$query> = I<$cqp>->B<quote>(I<$regexp>);

=item I<$regexp> = I<$cqp>->B<quotemeta>(I<$string>);

Safely quotes regular expressions and literal strings for use in CQP queries and other commands.
The B<quote> method encloses I<$regexp> in single or double quotes, as appropriate, and escapes quote characters inside the string by doubling.
B<quotemeta> escapes all known regular expression metacharacters in I<$string> with backslashes (including the backslash itself).
It does not surround I<$string> with quotes, so if you want a CQP expression that searches I<$string> as a literal string, you have to combine them into C<< $cqp->quote($cqp->quotemeta($string)) >>.  Both methods are vectorised, so you can pass multiple arguments in one call.

=cut

sub quote {
  my $self = shift;
  my @quoted = ();
  $self->{'status'} = 'ok'; # method raises an error if string cannot be quoted
  while (@_) {
    my $r = shift;
    if ($r =~ /(\\+)$/) {
      $self->error("Cannot quote string '$r' ending in unescaped backslash.")
        if (length($1) % 2) != 0;
    }
    if ($r !~ /"/) {
      push @quoted, "\"$r\""; # quote with "..." if string doesn't containt double quote
    }
    elsif ($r !~ /'/) {
      push @quoted, "'$r'";   # else quote with '...' if string doesn't contain single quote (= apostrophe)
    }
    else {
      # use double quotes, but escape all inner double quotes by doubling (unless already escaped with backslash)
      $r =~ s/(\\*)"/ (length($1) % 2) ? $& : "$&\"" /ge;
      push @quoted, "\"$r\"";
    }
  }
  return (wantarray) ? @quoted : shift @quoted;  
}

sub quotemeta {
  my $self = shift;
  my @quoted = ();
  while (@_) {
    my $s = shift;
    $s =~ s/([(){}\[\]|.?*+\$\\])/\\$1/g;
    $s =~ s/\^/[^]/g; # work around latex escapes like \^o in CQP
    push @quoted, $s;
  }
  return (wantarray) ? @quoted : shift @quoted;
}

=item I<$cqp>->B<debug>(1);

=item I<$cqp>->B<debug>(0);

Activate/deactivate debugging mode, which logs all executed commands and their complete output on STDOUT.  The B<debug> method returns the previous status for convenience.

=cut

sub debug {
  croak 'USAGE:  $prev_status = $cqp->debug( 1 | 0 ) ;'
    unless @_ == 2;
  my $self = shift;
  my $on = shift;
  my $prev = $self->{'debug'};
  $self->{'debug'} = $on;
  return $prev;
}

=item I<$cqp>->B<progress_on>;

=item I<$cqp>->B<progress_off>;

=item I<$message> = I<$cqp>->B<progress>;

=item (I<$total>, I<$pass>, I<$n_passes>, I<$msg>, I<$percent>) = I<$cqp>->B<progress_info>;

CQP progress messages can be activated and deactivated with the B<progress_on> and B<progress_off> methods (corresponding to C<set ProgressBar on|off;> in CQP).

If active, progress information can be obtained with the method B<progress>, which returns the last progress message received from CQP.  The B<progress_info> returns pre-parsed progress information, consisting of estimated total percentage of completion (I<$total>), the current pass (I<$pass>) and total number of passes (I<$n_passes>) for multi-pass operations, the information part (I<$msg>, either a percentage or a free-form progress message), and the completion percentage of the current pass (I<$percent>).

It is an error to call B<progress> or B<progress_info> without activating progress messages first.

=cut

## activate / deactivate CQP progress messages
sub progress_on {
  my $self = shift;
  if ($self->{'progress'}) {
    carp 'CWB::CQP:  Progress messages have already been activated (ignored)';
  }
  else {
    $self->exec("set ProgressBar on");
    $self->{'progress'} = 1;
  }
}

sub progress_off {
  my $self = shift;
  if ( $self->{'progress'}) {
    $self->exec("set ProgressBar off");
    $self->{'progress'} = 0;
  }
  else {
    carp 'CWB::CQP:  Progress messages have not been turned on yet (ignored)';
  }
}

## poll current progress status (estimated total percentage, or detailed information)
sub progress {
  my $self = shift;
  croak 'CWB::CQP:  No progress() information available, please call progress_on() first'
    unless $self->{'progress'};
  if (not $self->{'command'}) {
    carp 'CWB::CQP:  No active command, progress() does not make sense';
    return undef;
  }
  else {
    ## if input is already available, return corresponding progress state; otherwise check for new progress messages
    $self->_update
      unless @{$self->{'lines'}} > 0;
    return $self->{'progress_info'}->[0];
  }
}

sub progress_info {
  croak 'USAGE:  ($total_percent, $pass, $n_passes, $message, $percent) = $cqp->progress_info;'
    unless @_ == 1 and wantarray;
  my $self = shift;
  croak 'CWB::CQP:  No progress_info() available, please call progress_on() first'
    unless $self->{'progress'};
  if (not $self->{'command'}) {
    carp 'CWB::CQP:  No active command, progress() does not make sense';
    return undef;
  }
  else {
    ## if input is already available, return corresponding progress state; otherwise check for new progress messages
    $self->_update
      unless @{$self->{'lines'}} > 0;
    return @{$self->{'progress_info'}}
  }
}

=item I<$cqp>->B<set_progress_handler>(I<&my_progress_handler>);

Set a user-defined progress handler, which will be invoked whenever new progress information is received from CQP.  The argument must be a named or anonymous subroutine, which will be called with the information returned by B<progress_info>.  Note that setting a user-defined progress handler does I<not> automatically activate progress information: you still need to call B<progress_on> for this purpose.

Calling B<set_progress_handler> with B<undef> (or without an argument) disables the user-defined progress handler.

=cut

## set user-defined handler for CQP progress messages (does not automatically activate progress messages!)
sub set_progress_handler {
  my $self = shift;
  my $handler = shift;

  croak 'USAGE:  $cqp->set_progress_handler(\&my_progress_handler);'
    unless (not defined $handler) or (ref $handler eq 'CODE');
  if ($handler) {
    $self->{'progress_handler'} = $handler;
  }
  else {
    $self->{'progress_handler'} = undef;
  }
}

=back

=cut

return 1;

__END__

=head1 COPYRIGHT

Copyright (C) 2002-2022 Stephanie Evert [https://purl.org/stephanie.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
