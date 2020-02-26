## 
##  Perl makefile for the official CWB/Perl interface (Perl part)
##

use strict;
use warnings;

use ExtUtils::MakeMaker;
use Getopt::Long;
use FileHandle;

our $config_util = "cwb-config";  # path to cwb-config utility (if not in search path)
our $help = 0;                    # display help page

GetOptions(
  "config=s" => \$config_util,
  "help|h" => \$help,
) or die "\nType 'perl Makefile.PL --help' for usage information.\n";

if ($help) {
  print "Usage:  perl Makefile.PL [--config=/path/to/cwb-config]\n\n";
  print "    --config <path>  full path to 'cwb-config' program (if not in standard search path)\n";
  print "\n";
  exit 2;
}

## required CWB version for this module (3.0.0)
our $rMajor = 3;
our $rMinor = 0;
our $rBeta = 0;

## run cwb-config to check installed CWB version
my $version = `'$config_util' --version 2>/dev/null`;
die 
  "Can't run cwb-config program.\n",
  "Please make sure that an up-to-date version of the IMS Open Corpus Workbench has been installed and/or use the --config option to specify the full path to the cwb-config program.\n"
  unless defined $version and $version =~ /^([0-9])\.([0-9]{1,2})(\.b?([0-9]+))?$/;

our $MajorVersion = $1;
our $MinorVersion = $2;
our $BetaVersion = $4 || 0;
chomp($version);
our $version_numeric = sprintf("%d.%03d%03d", $MajorVersion, $MinorVersion, $BetaVersion);

die "Error: need CWB version ", version_string($rMajor, $rMinor, $rBeta), " or newer, but only $version is installed.\n"
  unless $MajorVersion > $rMajor 
    or ($MajorVersion == $rMajor and
          ($MinorVersion > $rMinor or $MinorVersion == $rMinor and $BetaVersion >= $rBeta));

## obtain relevant configuration options from cwb-config
our $prefix = `'$config_util' --prefix`;
our $bindir = `'$config_util' --bindir`;
our $default_registry = `'$config_util' --default-registry`;
chomp($prefix, $bindir, $default_registry);

print "IMS Open Corpus Workbench v$version found in $prefix tree.\n";
die "Error: can't handle directory paths with apostrophe yet. Please install the CWB in a different directory.\n"
  if "$prefix$bindir$default_registry" =~ /'/;

## write configuration information to CWB::Config module
my $config_file = "lib/CWB/Config.pm";
my $fh = new FileHandle "> $config_file"
  or die "Can't create file '$config_file': $!";
print $fh <<"STOP" or die "Error writing file '$config_file': $!";
package CWB::Config;

our \$Prefix = '$prefix';
our \$BinDir = '$bindir';
our \$Registry = '$default_registry';
our \$Version = '$version_numeric';
our \$VersionString = '$version';

1;
STOP
$fh->close
  or die "Error writing '$config_file': $!";
print "Configuration saved to file $config_file\n";

## now we can let MakeMaker generate the makefile
WriteMakefile(
  NAME         => "CWB",
  VERSION_FROM => "lib/CWB.pm",
  EXE_FILES    => [qw(script/cwb-make script/cwb-regedit script/cwb-align-import script/cwb-align-export script/cwb-convert-to-utf8 script/cwb-split-vrt script/cwb-align-tmx2beads)],
  clean        => {FILES => "tmp/* lib/CWB/Config.pm"},
  LICENSE      => 'perl',
  META_MERGE   => {
    resources => {
      homepage => 'http://cwb.sourceforge.net/',
      repository => 'http://svn.code.sf.net/p/cwb/code/perl/trunk',
    },
  },
);


##
##  Subroutines
##

sub version_string {
  my ($major, $minor, $beta) = @_;
  my $s = "$major.$minor";
  if ($beta > 0) {
    $s .= ".$beta"; # in future, "beta" versions are simply listed as releases, without "b" marker
  }
  return $s;
}
