# -*-cperl-*-
## Test registry file editor

use Test::More tests => 18;

use CWB;
use File::Compare;

our $dickens = new CWB::RegistryFile "data/registry/dickens";
isa_ok($dickens, CWB::RegistryFile, "load registry entry into RegistryFile object"); # T1

is($dickens->id, "dickens", "ID field"); # T2
like($dickens->name, qr/Charles Dickens/, "NAME field");
is($dickens->home, "/corpora/Registry/DemoCorpus/data", "HOME field");
is($dickens->info, "/corpora/Corpus Data/DemoCorpus/data/.info", "INFO field");

our @properties = $dickens->list_properties;
$ok = (@properties == 2) && (grep {/^charset$/} @properties) && (grep {/^language$/} @properties);
ok($ok, "list of corpus properties"); # T6
is($dickens->property("charset"), "latin1", "'charset' property");
is($dickens->property("language"), "en", "'language' property");

our @p_attr = $dickens->list_attributes("p"); # positional attributes
our @s_attr = $dickens->list_attributes("s"); # structural attributes
our @a_attr = $dickens->list_attributes("a"); # alignment attributes
our $N_attr = $dickens->list_attributes;

ok($N_attr == @p_attr + @s_attr + @a_attr, "consistent attribute counts from list_attributes()"); # T9
is(@p_attr+0, 4, "4 positional attributes");
ok((grep {/^word$/} @p_attr), "default p-attribute (word) is listed");
is(@s_attr+0, 34, "34 structural attributes");
ok((grep {/^novel_title$/} @s_attr), "s-attribute 'novel_title' is listed");
is(@a_attr+0, 0, "no alignment attributes");

is($dickens->attribute("lemma"), "p", "attribute info for 'lemma' (p-attribute)"); # T15
is($dickens->attribute("np1"), "s", "attribute info for 'np1' (s-attribute)");
like($dickens->line_comment("np_h"), qr/annot/, "inline comment on 'np_h' attribute declaration");

## now perform various modifications on the registry entry and check resulting file
$dickens->delete_info;                            # delete INFO field
$dickens->id("test");                             # modify ID field
$dickens->line_comment(":ID", "name modified by CWB::RegistryFile module"); # add inline comment on ID field
$dickens->property("valid", "FALSE");                                       # add corpus property
$dickens->delete_attribute("nbc");                                          # delete p-attribute 'nbc'
foreach my $att (grep {/^(np|pp)/} $dickens->list_attributes('s')) {
  $dickens->delete_attribute($att);               # delete NP and PP regions (s-attributes)
}
foreach my $ext ("", 1..3) {
  $dickens->add_attribute("chunk$ext", 's');      # add s-attributes 'chunk' .. 'chunk3' with block comment
}
$dickens->add_comments("chunk", "", "some comments on this block of attributes", "   with indentation");
$dickens->add_attribute("test-fr", 'a');                                    # add alignment attribute
$dickens->line_comment("test-fr", "one alignment per corpus pair");         # with inline comments
$dickens->set_comments("word", "", "POSITIONAL ATTRIBUTES", "");            # modify block comment for 'word' attribute
$dickens->add_comments("p", "FURTHER COMMENTS ADDED BY CWB::RegistryFile"); # extend block comment for 'p' attribute

our $reg_file = "tmp/dickens.mod";
unlink($reg_file) if -f $reg_file;
$dickens->write($reg_file);             # save modified registry entry
$ok = (compare($reg_file, "data/registry/dickens.ref") == 0);
diag("check output file tmp/dickens.mod against reference data/registry/dickens.ref") unless $ok;
ok($ok, "modifications on registry entry (check against reference file)"); # T18
