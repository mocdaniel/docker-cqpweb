# -*-cperl-*-
## Test support for encoding and decoding CoNLL-style file formats

use Test::More;

use CWB;
use CWB::Encoder;
use File::Compare qw(compare compare_text);
use DirHandle;

if ($CWB::CWBVersion >= 3.004_029) {
  plan tests => 5;
}
else {
  plan skip_all => "need CWB 3.4.29 or newer for CoNLL-style input format";
}

our $reg_dir = "tmp/registry";
our $data_dir = "tmp/conll_u";
our $vrt_file = "data/vrt/conll_u.vrt";
mkdir $reg_dir unless -d $reg_dir;

our $enc = new CWB::Encoder "CONLL_U";
isa_ok($enc, CWB::Encoder, "create CWB::Encoder object"); # T1

$enc->registry($reg_dir);       # set up paths and allow encoder to overwrite existing files
$enc->dir($data_dir);
$enc->overwrite(1);

$enc->longname("CoNLL-U format examples"); # set up basic information
$enc->info("Info file for corpus CONLL_U\n");
$enc->charset("utf8");          # don't set language because it's actually mixed

$enc->perm("640");              # set non-standard access permissions (but not group)

our @patt = qw(word lemma upos xpos feats/ head deprel deps/ misc/);
$enc->p_attributes(@patt); # declare attributes
$enc->undef_symbol("_");        # CoNLL-style notation for missing values
$enc->decode_entities(0);       # input is not XML-encoded
$enc->encode_options(qw(-N id -L s)); # enable CoNLL-style input format

$enc->memory(100);              # corpus is very small and should use little memory
$enc->validate(1);              # validate all generated files
$enc->verbose(0);               # don't show any progress messages when running as self test
$enc->debug(0);

eval { $enc->encode($vrt_file) };
ok(! $@, "corpus encoding and indexing from CoNLL-style input"); # T2

## now compare all created data files against reference corpus
our $ref_dir = "data/conll_u";

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
ok($ok, "validation of created data files"); # T3

## decode into CoNLL-style format and compare with reference
our $output_file = "tmp/conll_u.vrt";
our $output_ref = "data/vrt/conll_u_decode.vrt";

map { s{/$}{}; } @patt; # remove feature set markers
my $err = CWB::Shell::Cmd([$CWB::Decode, "-r", $reg_dir, "-C", "-b", "s", "CONLL_U", "-P", "id", map { ("-P", $_) } @patt], $output_file);
ok($err == 0, "decode into CoNLL-style format"); # T4
ok(compare($output_file, $output_ref) == 0, "validate representation and output of CoNLL format"); # T5
