package CWB::CL::Strict;

# this is just a convenient wrapper to load CWB::CL with "strict" mode enabled
use CWB::CL;
CWB::CL::strict(1);

1;

=head1 NAME

CWB::CL::Strict - Load Perl/CL interface in strict mode

=head1 SYNOPSIS

  use CWB::CL::Strict;
  # standard CWB::CL functionality available now

=head1 DESCRIPTION

See L<CWB::CL> for further information.

=head1 COPYRIGHT

Copyright (C) 1999-2008 by Stefan Evert (http://purl.org/stefan.evert).

IMS Open Corpus Workbench (CWB)
copyright (C) 1993-2006 by IMS, University of Stuttgart;
copyright (C) 2007-today by the CWB open-source community
(see L<http://cwb.sf.net/>).

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the library, redistribute and
modify it under the same terms as Perl itself.

IN NO EVENT WILL THE AUTHOR BE LIABLE TO YOU FOR ANY CONSEQUENTIAL,
INCIDENTAL OR SPECIAL DAMAGES, INCLUDING ANY LOST PROFITS OR LOST
SAVINGS, EVEN IF AN IMS REPRESENTATIVE HAS BEEN ADVISED OF THE
POSSIBILITY OF SUCH DAMAGES, OR FOR ANY CLAIM BY ANY THIRD PARTY.
