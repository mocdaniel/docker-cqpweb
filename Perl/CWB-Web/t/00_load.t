# -*-cperl-*-
## At least test whether all the CWB::Web modules can be loaded

use Test::More tests => 3;

BEGIN {
  use_ok('CWB::Web::Cache');
  use_ok('CWB::Web::Query');
  use_ok('CWB::Web::Search');
};
