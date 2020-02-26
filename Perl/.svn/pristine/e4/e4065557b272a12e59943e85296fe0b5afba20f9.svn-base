package CWB::CQI::Server;
# -*-cperl-*-

use strict;
use warnings;

use CWB::CQI;
use Carp;
use FileHandle;

# export CQi server startup functions
use base qw(Exporter);
our @EXPORT = qw(cqi_server cqi_server_available);

=head1 NAME

CWB::CQI::Server - launch private CQPserver on local machine


=head1 SYNOPSIS

  use CWB::CQI::Server;
  use CWB::CQI::Client;

  if (cqi_server_available()) {
    my @details = cqi_server();
    cqi_connect(@details);
    ...
  }

=head1 DESCRIPTION

The B<CWB::CQI::Server> module can be used to launch a private CQPserver
on the local machine, which B<CWB::CQI::Client> can then connect to.

Note that this is only possible if a suitable version of the IMS Open Corpus Workbench
and the B<CWB> Perl module have been installed.  Availability must therefore be
checked with the B<cqi_server_available> function before calling B<cqi_server>.


=head1 FUNCTIONS

=over 4

=cut

our $CQPserver = undef;
if (eval 'use CWB 3.000_000; 1') {
  $CQPserver = $CWB::CQPserver
    if -x $CWB::CQPserver;
}

=item I<$ok> = B<cqi_server_available>();

Returns a B<true> value if a suitable CQPserver binary is installed on the local machine and
can be started with the B<cqi_server> function.

=cut

sub cqi_server_available {
  return (defined $CQPserver) ? 1 : 0;
}

=item (I<$user>, I<$passwd>, I<$host>, I<$port>) = B<cqi_server>();

=item I<@details> = B<cqi_server>(I<$flags>);

C<cqi_server()> searches for a free port on the local machine, then  
launches a single-user B<CQPserver> process and returns the connection details
required by the B<cqi_connect> function from B<CWB::CQI::Client> (in the appropriate order).
The simplest way to establish a connection with a private, local CQPserver is

    cqi_connect(cqi_server());

Be sure to check with B<cqi_server_available> whether the required C<cqpserver>
command-line program is available first.

An optional argument to B<cqi_server> is appended to the C<cqpserver> command-line flags
and can be used to specify further start-up options (e.g. to read a macro definition file).
Keep in mind that arguments containing shell metacharacters need to be quoted appropriately.

B<WARNING:> Since B<CQPserver> runs as a separate process in the background, it is 
important to establish a connection B<as soon as possible>. If the user's
program aborts before B<cqi_connect> is called and contacts the new CQPserver,
this process will accept further connections from other users (on the local machine),
which might compromise confidential data. 

=cut

#
#
#  Start CQPserver in the background and return (host, port, user, passwd) list for cqi_connect()
#  An init file is generated which adds a random user/passwd to the server's user list,
#  so you can connect to the newly created server with the user/passwd combination returned
#  by cqi_server() only. (NB uses '-I' at the moment, so .cqprc won't be read) 
#
#
sub cqi_server {
  my $user = "cqi_server_$$";
  my $passwd = "pass" . int rand(42000);
  my $flags = "-1 -L -q ";      # single-client server, localhost only (for security reasons)
  $flags .= "@_" if @_;         # append optional command-line flags

  croak "CQPserver is not installed on this machine"
    unless cqi_server_available();

  # generate temporary user list file for CQPserver
  my $passfile = "/tmp/CQI::Server.$$";
  my $fh = new FileHandle "> $passfile";
  croak "Can't create temporary user list file. Aborting."
    unless defined $fh;
  print $fh "user $user \"$passwd\";\n";
  $fh->close;
  chmod 0600, $passfile;        # so no one can spy on us

  # scan for free port (using rand() so two servers invoked at the same time won't collide)
  my $port = 10000 + int rand(2000);
  my %in_use = 
    map {$_ => 1}
      map {(/\*\.([0-9]+)/) ? $1 : 0}
        `netstat -a -n | grep LISTEN`;
  while ($port < 60000 and $in_use{$port}) {
    $port += rand(20);          # jump randomly to avoid collisions
  }
  croak "Can't find free port for CQPserver. Abort."
    unless $port < 60000;

  # now start CQPserver on this port
  croak "CQPserver failed to launch: $!\n" 
    if system "$CQPserver $flags -P $port -I $passfile >/dev/null 2>&1" or $? != 0;

  # delete user list file
  unlink $passfile;

  # return connection information suitable for cqi_connect()
  return $user, $passwd, "localhost", $port;
}


1;

__END__

=back

=head1 COPYRIGHT

Copyright (C) 1999-2010 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
