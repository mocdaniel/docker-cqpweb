# -*-cperl-*-
## Test correct handling of CR/LF line breaks by cwb-encode

use Test::More;

use CWB;
use CWB::Encoder;
use File::Compare qw(compare compare_text);
use DirHandle;

if ($CWB::CWBVersion >= 3.004_014) {
  plan tests => 5;
}
else {
  plan skip_all => "need CWB 3.4.14 or newer for CRLF support";
}

our $reg_dir = "tmp/registry";
our $data_dir = "tmp/vss_crlf";
our $vrt_file = "data/vrt/vss_crlf.vrt";
our $sencode_file = "data/vrt/vss_story_title_crlf.txt";
mkdir $reg_dir unless -d $reg_dir;

our $enc = new CWB::Encoder "VSS_CRLF";
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

eval { $enc->encode($vrt_file) };
ok(! $@, "corpus encoding and indexing from CRLF file"); # T2

## now compare all created data files against reference corpus
our $ref_dir = "data/vss";

our $dh = new DirHandle $ref_dir;
my $ok = 1;
my $old_huffcode = 0;
while (defined (my $filename = $dh->read)) {
  my $ref_file = "$ref_dir/$filename";
  my $new_file = "$data_dir/$filename";
  next unless -f $ref_file;     # skip directories and extra files
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
$dh->close;
ok($ok, "validation of created data files (without CR)"); # T3

## test cwb-s-encode with CRLF input
my $err = CWB::Shell::Cmd([$CWB::SEncode, "-d", $data_dir, "-f", $sencode_file, "-V", "story_title"]);
ok($err == 0, "cwb-s-encode from CRLF file"); # T4

$ok = 1;
for my $filename (qw(story_title.rng story_title.avs story_title.avx)) {
  my $ref_file = "$ref_dir/$filename";
  my $new_file = "$data_dir/$filename";
  if (compare($new_file, $ref_file) != 0) {
    diag("data file '$filename' is corrput");
    $ok = 0;
  }
}
ok($ok, "validation of cwb-s-encoded data files (without CR)"); # T5
