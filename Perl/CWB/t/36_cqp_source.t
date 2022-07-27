# -*-cperl-*-
## Test CQP "source" command to execute scripts

use Test::More tests => 4;

use CWB::CQP;

our $cqp = new CWB::CQP "-r data/registry", "-I data/files/init.cqp";
isa_ok($cqp, "CWB::CQP"); # T1

$cqp->set_error_handler('die'); # any CQP errors in this script are major problems and should result in test failure

# CQP "source" command (new in CQP v3.4.22) T2-T4
SKIP: {
  skip "'source' command only available in CQP v3.4.22 and newer", 3 unless $cqp->check_version(3, 4, 22);

  my ($output) = $cqp->exec("source 'data/files/cqp_script.txt'");
  like($output, qr{^14$}, "source CQP script file");

  ($output) = $cqp->exec("source 'data/files/cqp_script_crlf.txt'");
  like($output, qr{^14$}, "source CQP script file with CRLF line endings");

  my @output = $cqp->exec("source 'data/files/script_A.txt'");
  my @expected = qw([A1] [B] [A2] [B] [A3]);
  is_deeply(\@output, \@expected, "sourcing nested script files");
}
