# -*-cperl-*-
## Test auto-null option in cwb-encode (automatically ignore unknown XML tags)

use Test::More;

use CWB;
use CWB::Encoder;
use File::Compare qw(compare compare_text);
use DirHandle;
use Time::HiRes qw(time);

if ($CWB::CWBVersion >= 3.004_021) {
  plan tests => 3;
}
else {
  plan skip_all => "only available in CWB v3.4.21 or newer";
}

our $reg_dir = "tmp/registry";
our $data_dir = "tmp/vss_an";
our $vrt_file = "data/vrt/VeryShortStories.vrt";
mkdir $reg_dir unless -d $reg_dir;

our $enc = new CWB::Encoder "VSS_AN";
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
$enc->s_attributes(qw(chapter:0+num s:0));
$enc->auto_null(1);             # auto-declare null attributes for unknown XML tags
$enc->encode_options("-q");     # silence messages on skipped XML tags

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
our $ref_regfile = "data/registry/vss";

our $dh = new DirHandle $ref_dir;
my $ok = 1;
my $old_huffcode = 0;
while (defined (my $filename = $dh->read)) {
  my $ref_file = "$ref_dir/$filename";
  my $new_file = "$data_dir/$filename";
  next unless -f $new_file && $filename !~ /^\./; # skip directories & info file
  if ($filename =~ /^(word|pos|lemma|collection|chapter(_num)?|s)\./) {
    ## these files should be identical to the reference corpus (in particular, no extra tokens for unknown XML tags)
    if (-f $new_file) {
      if (compare($new_file, $ref_file) != 0) {
        diag("data file '$filename' is corrupt");
        $ok = 0;
      }
    }
    else {
      diag("failed to create data file '$filename'");
      $ok = 0;
    }
  }
  else {
    ## these files for auto-null s-attributes should not be created
    if (-f $new_file) {
      diag("file '$filename' should not have been created");
      $ok = 0;
    }
  }
}
$dh->close;
ok($ok, "validation of created data files"); # T3


