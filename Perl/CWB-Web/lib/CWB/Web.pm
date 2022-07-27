package CWB::Web;
# -*-cperl-*-

## This is just a dummy package to get the CWB::Web namespace indexed on CPAN.
## It doubles as a convenience to load all CWB::Web::* submodules, which don't export any symbols.

$VERSION = 'v3.4.1';

use CWB::Web::Cache;
use CWB::Web::Query;
use CWB::Web::Search;

return 1;

__END__


=head1 NAME

  CWB::Web - Some utilities for Web GUIs based on CWB

=head1 SYNOPSIS

  use CWB::Web;

  # same as:
  use CWB::Web::Cache;
  use CWB::Web::Query;
  use CWB::Web::Search;

=head1 DESCRIPTION

See documentation of the individual submodules for further information:

=over 4

=item *

L<B<CWB::Web::Cache>>

=item *

L<B<CWB::Web::Query>>

=item *

L<B<CWB::Web::Search>>

=back


=head1 COPYRIGHT

Copyright (C) 1999-2020 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
