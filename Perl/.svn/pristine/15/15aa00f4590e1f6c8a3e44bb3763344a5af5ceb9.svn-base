# -*-cperl-*-
## Test whether paths to CWB tools are correct and programs can be run

use Test::More tests => 17;

use CWB;

## check that CQP and CWB tools can be executed and return correct version information
our $cwb_version = `'$CWB::Config' --version`;
chomp($cwb_version);
like($cwb_version, qr/^[0-9]+\.[0-9]+(\.b?[0-9]+)?$/, "cwb-config"); # T1
check_version("'$CWB::CQP' -v", $cwb_version, "cqp"); # T2
check_version("'$CWB::CQPserver' -v", $cwb_version, "cqpserver");
check_version("'$CWB::Encode' -h", $cwb_version, "cwb-encode");
check_version("'$CWB::Makeall' -h", $cwb_version, "cwb-makeall");
check_version("'$CWB::Decode' -h", $cwb_version, "cwb-decode");
check_version("'$CWB::Lexdecode' -h", $cwb_version, "cwb-lexdecode");
check_version("'$CWB::DescribeCorpus' -h", $cwb_version, "cwb-describe-corpus");
check_version("'$CWB::Huffcode' -h", $cwb_version, "cwb-huffcode");
check_version("'$CWB::CompressRdx' -h", $cwb_version, "cwb-compress-rdx");
check_version("'$CWB::Itoa' -h", $cwb_version, "cwb-itoa");
check_version("'$CWB::Atoi' -h", $cwb_version, "cwb-atoi");
check_version("'$CWB::SEncode' -h", $cwb_version, "cwb-s-encode");
check_version("'$CWB::SDecode' -h", $cwb_version, "cwb-s-decode");
check_version("'$CWB::ScanCorpus' -h", $cwb_version, "cwb-scan-corpus");
check_version("'$CWB::Align' -h", $cwb_version, "cwb-align");
check_version("'$CWB::AlignEncode' -h", $cwb_version, "cwb-align-encode"); # T17

## try to read version information from CWB tools
sub check_version {
  my ($cmd, $version, $name) = @_;
  my $output = `$cmd 2>\&1`;
  if (defined $output) {
    foreach (split /\n/, $output) {
      if (/(Version:\s+|\s+v)([0-9]+\.[0-9]+(\.b?[0-9]+)?)/) {
	my $cmd_version = $2;
	is($cmd_version, $version, $name);
	return
      }
    }
    diag("cannot get version information from '$cmd'");
    fail($name);
  }
  else {
    diag("cannot execute '$cmd'");
    fail($name);
  }
}
