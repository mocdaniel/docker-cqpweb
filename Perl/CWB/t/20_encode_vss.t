# -*-cperl-*-
## Test automatic corpus encoding and indexing with CWB::Encoder

use Test::More tests => 6;

use CWB;
use CWB::Encoder;
use File::Compare qw(compare compare_text);
use DirHandle;
use Time::HiRes qw(time);

our $reg_dir = "tmp/registry";
our $data_dir = "tmp/vss";
our $vrt_file = "data/vrt/VeryShortStories.vrt";
mkdir $reg_dir unless -d $reg_dir;

our $enc = new CWB::Encoder "VSS";
isa_ok($enc, CWB::Encoder, "create CWB::Encoder object"); # T1

$enc->registry($reg_dir);       # set up paths and allow encoder to overwrite existing files
$enc->dir($data_dir);
$enc->overwrite(1);

$enc->longname("Very Short Stories"); # set up basic information
$enc->info("Info file for corpus VSS (Very Short Stories)\n");
$enc->charset("latin1");
$enc->language("en");

$enc->perm("640");              # set non-standard access permissions (but not group)

$enc->p_attributes(qw(word pos lemma)); # declare attributes
$enc->null_attributes("collection");
$enc->s_attributes(qw(story:0+num+title+author+year chapter:0+num p:0 s:0));

$enc->memory(100);              # corpus is very small and should use little memory
$enc->validate(1);              # validate all generated files
$enc->verbose(0);               # don't show any progress messages when running as self test
$enc->debug(0);

our $T0 = time;
eval { $enc->encode($vrt_file) };
ok(! $@, "corpus encoding and indexing"); # T2
our $elapsed = time - $T0;
diag(sprintf "VSS corpus encoded in %.1f seconds", $elapsed);

## now compare all created data files against reference corpus
our $ref_dir = "data/vss";
our $ref_old_dir = "data/vss_old_huffcode"; # CWB versions prior to 3.0.1 / 3.4.2
our $ref_regfile = "data/registry/vss";

our $dh = new DirHandle $ref_dir;
my $ok = 1;
my $old_huffcode = 0;
while (defined (my $filename = $dh->read)) {
  my $ref_file = "$ref_dir/$filename";
  my $ref_old_file = "$ref_old_dir/$filename";
  my $new_file = "$data_dir/$filename";
  next unless -f $ref_file;     # skip directories
  if (-f $new_file) {
    if (compare($new_file, $ref_file) != 0) {
      if (-f $ref_old_file and compare($new_file, $ref_old_file) == 0) {
        $old_huffcode = 1;
      }
      else {
        diag("data file '$filename' is corrupt");
        $ok = 0;
      }
    }
  }
  else {
    diag("failed to create data file '$filename'");
    $ok = 0;
  }
}
$dh->close;
diag("NOTE: You are using an old version of cwb-huffcode that is now deprecated. We recommend that you upgrade to CWB 3.0.1 / 3.4.2 or newer")
  if $old_huffcode;
ok($ok, "validation of created data files"); # T3

## compare generated registry entry against reference
our $my_cmp = sub {
  map { s{(tmp|data)/vss}{*/vss}g } @_; # ignore different data paths
  my $cmp = $_[0] cmp $_[1];
  if ($cmp) {
    diag("Difference detected in registry entry:\nNEW = $_[0]REF = $_[1]");
  }
  return $cmp;
};
$ok = (compare_text("$reg_dir/vss", $ref_regfile, $my_cmp) == 0);
ok($ok, "validation of generated registry entry"); # T4

## check file permissions and contents of .info file
my $mode;
(undef, undef, $mode) = stat "$data_dir/word.huf";
is((sprintf "%04o", ($mode & 07777)), "0640", "correct file access permissions (word.huf)"); # T5

my $fh = CWB::OpenFile "$data_dir/.info";
my $line = <$fh>;
like($line, qr/Very Short Stories/, "contents of .info file"); # T6
$fh->close;


