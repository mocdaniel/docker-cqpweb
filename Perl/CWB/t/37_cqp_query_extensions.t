# -*-cperl-*-
## Tests for recent extensions and bug fixes in the CQP query language

use Test::More tests => 58;
  
use CWB::CQP;

our $cqp = new CWB::CQP "-r data/registry", "-I data/files/init.cqp";
isa_ok($cqp, "CWB::CQP"); # T1

$cqp->set_error_handler('die'); # any CQP errors in this script are major problems and should result in test failure

$cqp->exec("VSS");
ok($cqp->ok, "activate VSS corpus"); # T2

# negated meet constraint (new in CQP v3.4.30) T3-T4
SKIP: {
  skip "MU(meet ... not ...) only available in CQP v3.4.30 and newer", 2 unless $cqp->check_version(3, 4, 30);

  $cqp->exec("Meet3n_MU = MU(meet 'of' not 'the' -3 3)");
  $cqp->exec("Meet3n_CQL = 'of'");
  $cqp->exec("set Meet3n_CQL target nearest 'the' within 3 words");
  $cqp->exec("delete Meet3n_CQL with target");
  ok(matches_eq("Meet3n_MU", "Meet3n_CQL"), "MU(meet A not B -3 3) corresponds to basic query");

  $cqp->exec("MeetElephantN_MU = MU(union (meet 'elephant' not 'the'\%c -1 -1) 'elephants')");
  $cqp->exec("MeetElephantN_CQL = [word != 'the'\%c] 'elephant' | 'elephants'");
  $cqp->exec("set MeetElephantN_CQL match matchend"); # remove !'the' from match
  ok(matches_eq("MeetElephantN_MU", "MeetElephantN_CQL"), "complex MU query corresponds to basic query");
}

# regression tests for buggy TAB implementation (fixed in CQP v3.4.30) T5-T6
SKIP: {
  skip "bugs in TAB query evaluation before CQP v3.4.30", 2 unless $cqp->check_version(3, 4, 30);

  $cqp->exec('Boolean_TAB = TAB [lemma != "be" & lemma != "have"] [pos="VBN"]');
  $cqp->exec('Boolean_CQL = [lemma != "be" & lemma != "have"] [pos="VBN"]');
  ok(matches_eq("Boolean_TAB", "Boolean_CQL"), "TAB query evaluates AND clause in token pattern");

  $cqp->exec('Bool2_TAB = TAB "day|shower" {1} [lemma="have" & (pos="VBG" & !(word = "[A-Z].*"))]');
  $cqp->exec('Bool2_CQL = "day|shower" [] [lemma="have" & (pos="VBG" & !(word = "[A-Z].*"))]');
  ok(matches_eq("Bool2_TAB", "Bool2_CQL"), "TAB query evaluates complex Boolean token patterns");
}

# new query feature <<NQR>> or <<region>> (new in CQP v3.4.31) T7-T37
SKIP: {
  skip "<<NQR>> and <<region>> only available in CQP v3.4.31 and newer", 31 unless $cqp->check_version(3, 4, 31);

  ## basic use of <<region>> and <<NQR>> in the middle of a query
  $cqp->exec('MyS = <s> []* </s>');  
  $cqp->exec('ColonS = ":" <s> []* </s>');
  $cqp->exec('ColonS_region = ":" <<s>>');
  $cqp->exec('ColonS_NQR = ":" <<MyS>>');
  ok(matches_eq("ColonS", "ColonS_region"), '":" <<s>> -- works'); # T7
  ok(matches_eq("ColonS", "ColonS_NQR"), '":" <<MyS>> -- works');

  $cqp->exec('NP = (?longest) [pos="DT"]? [pos="JJ.*"]* [pos="NNS?"]+');
  $cqp->exec('PP = (?longest) [pos="IN|TO"] [pos="DT"]? [pos="JJ.*"]* [pos="NNS?"]+');
  $cqp->exec('PPorNP = union NP PP');
  $cqp->exec('PrepNP = [pos="IN|TO"] <<NP>>');
  ok(matches_eq("PP", "PrepNP"), '[pos="IN|TO"] <<NP>> -- works'); # T9
  $cqp->exec('OfNP_Pron = "of" [pos="DT"]? [pos="JJ.*"]* [pos="NNS?"]+ [pos="P.*"]');
  $cqp->exec('OfNP_Pron_NQR = "of" <<NP>> [pos="P.*"]');
  ok(matches_eq("OfNP_Pron", "OfNP_Pron_NQR"), '"of"%c <<NP>> [pos="P.*"] -- works');

  $cqp->exec('V_PPorNP = (?longest) [pos="V.*"] [pos="IN|TO"]? [pos="DT"]? [pos="JJ.*"]* [pos="NNS?"]+');
  $cqp->exec('V_PPorNP_NQR = [pos="V.*"] (<<PP>> | <<NP>>)');
  ok(matches_eq("V_PPorNP", "V_PPorNP_NQR"), '[pos="V.*"] (<<PP>> | <<NP>>) -- works'); # T11
  $cqp->exec('V_PPorNP_NQR2 = [pos="V.*"] <<PPorNP>>');
  ok(matches_eq("V_PPorNP", "V_PPorNP_NQR2"), '[pos="V.*"] <<PPorNP>>) -- works');

  ## basic use of <<region>> and <<NQR>> at the beginning of a query
  $cqp->exec('SLower = <s> []* </s> "[a-z].*"');
  $cqp->exec('SLower_region = <<s>> "[a-z].*"');
  $cqp->exec('SLower_NQR = <<MyS>> "[a-z].*"');
  ok(matches_eq("SLower", "SLower_region"), '<<s>> "[a-z].*" -- works'); # T13
  ok(matches_eq("SLower", "SLower_NQR"), '<<MyS>> "[a-z].*" -- works');

  ## multiple ranges with the same start point 
  $cqp->exec('N2 = [pos="NNS?"]{2}');
  $cqp->exec('N3 = [pos="NNS?"]{3}');
  $cqp->exec('N23 = union N2 N3'); # NB same as: (?traditional) [pos="NNS?"]{2} | [pos="NNS?"]{3}
  $cqp->exec('N23_NQR = (?traditional )<<N2>> | <<N3>>'); # T15
  ok(matches_eq("N23", "N23_NQR"), '<<N2>> | <<N3>> -- works');
  $cqp->exec('TheN23 = [pos="DT"] [pos="NNS?"]{2,3} [:pos!="N.*":]');
  $cqp->exec('TheN23_NQR = [pos="DT"] <<N23>> [:pos!="N.*":]');
  $cqp->exec('TheN23_NQR2 = [pos="DT"] (<<N2>> | <<N3>>) [:pos!="N.*":]');
  ok(matches_eq("TheN23", "TheN23_NQR"), '[pos="DT"] <<N23>> [:pos!="N.*":] -- ranges with same start point work'); # T16
  ok(matches_eq("TheN23", "TheN23_NQR2"), '[pos="DT"] (<<N2>> | <<N3>>) [:pos!="N.*":] -- ranges with same start point work in alternative branches');

  ## region element can end at search boundary
  $cqp->exec('SendP = "!" <s> []+ </s> </p> [: word="[A-Z].*" :] within p');
  $cqp->exec('SendP_region = "!" <<s>> </p> [: word="[A-Z].*" :] within p');
  $cqp->exec('SendP_NQR = "!" <<MyS>> </p> [: word="[A-Z].*" :] within p');
  ok(matches_eq("SendP", "SendP_region"), '"!" <<s>> </p> [:...:] within p -- matches at search boundary'); # T18
  ok(matches_eq("SendP", "SendP_NQR"), '"!" <<MyS>> </p> [:...:] within p -- matches at search boundary');
  
  ## targets and labels are preserved across region element
  $cqp->exec('Anchors = @0:"." (@1:".." | ".") <s> []+ </s> <s>');
  $cqp->exec('Anchors_region = @0:"." (@1:".." | ".") <<s>> <s>');
  $cqp->exec('Anchors_NQR = @0:"." (@1:".." | ".") <<MyS>> <s>');
  ok(nqr_eq("Anchors", "Anchors_region"), '@0:"." (@1:".." | ".") <<s>> -- anchors preserved across region'); # T20
  ok(nqr_eq("Anchors", "Anchors_NQR"), '@0:"." (@1:".." | ".") <<MyS>> -- anchors preserved across region');

  $cqp->exec('Labels = a:"." (b:".." | ".") <s> []+ </s> <s> :: a.word="!" & b');
  $cqp->exec('Labels_region = a:"." (b:".." | ".") <<s>> <s> :: a.word="!" & b');
  $cqp->exec('Labels_NQR = a:"." (b:".." | ".") <<MyS>> <s> :: a.word="!" & b');
  ok(matches_eq("Labels", "Labels_region"), 'a:"." (b:".." | ".") <<s>> -- labels preserved across region'); # T22
  ok(matches_eq("Labels", "Labels_NQR"), 'a:"." (b:".." | ".") <<MyS>> -- labels preserved across region');

  ## set targets and labels in region elements
  $cqp->exec('SetAnchors = "." @0:[::] <s> []* @1:[] </s>');
  $cqp->exec('SetAnchors_region = "." <<@0 s @1>>');
  $cqp->exec('SetAnchors_NQR = "." <<@0 MyS @1>>');
  ok(nqr_eq("SetAnchors", "SetAnchors_region"), '"." <<@0 s @1>> -- anchors can be set'); # T24
  ok(nqr_eq("SetAnchors", "SetAnchors_NQR"), '"." <<@0 MyS @1>> -- anchors can be set');

  $cqp->exec('SetLabels = "." a:[::] <s> []* b:[] </s> :: strlen(a.word) = strlen(b.word)');
  $cqp->exec('SetLabels_region = "." <<a: s b:>> :: strlen(a.word) = strlen(b.word)');
  $cqp->exec('SetLabels_NQR = "." <<a: MyS b:>> :: strlen(a.word) = strlen(b.word)');
  ok(matches_eq("SetLabels", "SetLabels_region"), '"." <<a: s b:>> -- labels can be set'); # T26
  ok(matches_eq("SetLabels", "SetLabels_NQR"), '"." <<a: MyS b:>> -- labels can be set');

  $cqp->exec('SetBoth = "." @:a:[::] <s> []* @1b:[] </s> :: strlen(a.word) = strlen(b.word)');
  $cqp->exec('SetBoth_region = "." <<@:a: s @1b:>> :: strlen(a.word) = strlen(b.word)');
  $cqp->exec('SetBoth_NQR = "." <<@:a: MyS @1b:>> :: strlen(a.word) = strlen(b.word)');
  ok(nqr_eq("SetBoth", "SetBoth_region"), '"." <<a: s b:>> -- both labels and anchors can be set'); # T28
  ok(nqr_eq("SetBoth", "SetBoth_NQR"), '"." <<a: MyS b:>> -- both labels and anchors can be set');

  ## set targets and labels at beginning of query
  $cqp->exec('InitSetBoth = <s> []* @1b:[] </s> :: strlen(match.word) = strlen(b.word)');
  $cqp->exec('set InitSetBoth target match'); # need this trick without <<s>>
  $cqp->exec('InitSetBoth_region = <<@:a: s @1b:>> :: strlen(a.word) = strlen(b.word)');
  $cqp->exec('InitSetBoth_NQR = <<@:a: MyS @1b:>> :: strlen(a.word) = strlen(b.word)');
  ok(nqr_eq("InitSetBoth", "InitSetBoth_region"), '<<a: s b:>> -- both labels and anchors can be set'); # T30
  ok(nqr_eq("InitSetBoth", "InitSetBoth_NQR"), '<<a: MyS b:>> -- both labels and anchors can be set');

  ## duplicate start points with different matching strategies
  $cqp->activate("N3");
  $cqp->exec('Tmp = <match> []{2}'); # first two nouns of three-noun sequence
  $cqp->activate("VSS");
  $cqp->exec('N3dup = union N3 Tmp');
  my ($size_N3dup) = $cqp->exec('size N3dup');
  is($size_N3dup, 15, 'make test data with duplicate start points'); # T32
  $cqp->exec('N3dup_NQR = (?longest) <<N3dup>>');
  ok(matches_eq("N3", "N3dup_NQR"), '(?longest) <<N3dup>> -- accepts matches with same start point');
  $cqp->exec('N3dupPrep_NQR = <<N3dup>> [] [pos="IN"]'); # [] can be the third NNS? or some other token
  $cqp->exec('N3dupPrep = [pos="NNS?"]{3} []? [pos="IN"]');
  ok(matches_eq("N3dupPrep", "N3dupPrep_NQR"), '<<N3dup>> [] [pos="IN"] -- all matches with same start point are considered');

  ## duplicate end and start points (adjectives preceding nouns)
  $cqp->exec('AdjN = (?traditional) [pos="JJ.*"] []{0,3} [pos="NNS?"] within s');
  $cqp->exec('CommaAdjN = "," @0:[pos="JJ.*"] []{0,3} @1:[pos="NNS?"] within s');
  $cqp->exec('CommaAdjN_NQR = a:"," <<@0 AdjN @1>> :: a.word="," within s');
  ok(nqr_eq("CommaAdjN", "CommaAdjN_NQR"), '"," <<@0 AdjN @1>> within s -- duplicate start and end points work'); # T35
  $cqp->exec('CommaAdjN2 = (?longest) "," @0:[pos="JJ.*"] [pos!="NNS?"]{0,3} @1:[pos="NNS?"] within s');
  $cqp->exec('CommaAdjN2_NQR = (?longest) "," <<@0 AdjN @1>> within s');
  ok(nqr_eq("CommaAdjN2", "CommaAdjN2_NQR"), '(?longest) <<@0 AdjN @1>> within s -- duplicate start and end points work');

  $cqp->exec('ANprepAN = [pos="JJ.*"] []{0,3} [pos="NNS?"] @[pos="IN"] @1:[pos="JJ.*"] []{0,3} [pos="NNS?"] within s');
  $cqp->exec('ANprepAN_NQR = <<AdjN>> @[pos="IN"] <<@1 AdjN>> within s');
  ok(nqr_eq("ANprepAN", "ANprepAN_NQR"), '<<AdjN> [pos="IN"] <<AdjN>> within s -- combinations of duplicate start and end points');
}

# consolidated copying of anchors, now with offsets (CQP v3.4.31 and newer) T38-T49
SKIP: {
  skip "set <anchor> <anchor>; functionality was consolidated in CQP v3.4.31", 12 unless $cqp->check_version(3, 4, 31);
  
  ## undump table of corpus positions for testing anchor updates directly
  my @data = (
    [ 40,    42,   -1,   -1],
    [ 200,  205,  204,   -1],
    [ 666,  777,   -1,  888],
    [ 999, 1001,   -1,  888],
    [2020, 2021, 2019, 2022],
    [4242, 4242, 4241,   -1],
    [8000, 8008,   -1,   -1],
  );
  $cqp->undump("A", @data);
  
  my @anchors = do_set("A", "keyword", "target");
  is_deeply(\@anchors, [-1, 204, 888, 888, 2019, 4241, -1], "set A keyword target -- soft update works"); # T38
  @anchors = do_set("A", "target", "keyword !");
  is_deeply(\@anchors, [-1, -1, 888, 888, 2022, -1, -1], "set A target keyword ! -- hard update works");

  @anchors = do_set("A", "target", "keyword[-11]");
  is_deeply(\@anchors, [-1, 204, 877, 877, 2011, 4241, -1], "set A target keyword[-11] -- soft update with offset"); # T40
  @anchors = do_set("A", "target", "target[1000]");
  is_deeply(\@anchors, [-1, 1204, -1, -1, 3019, 5241, -1], "set A target target[1000] -- self-update with offset");

  @anchors = do_set("A", "target", "target");
  is_deeply(\@anchors, [-1, 204, -1, -1, 2019, 4241, -1], "set A target target -- ignored with a warning"); # T42
  
  $cqp->exec("B = A");
  $cqp->exec("set B target NULL");
  my ($n) = $cqp->exec("size B target");
  ok($n == 0, "set A target NULL -- discard anchors"); # T43
  
  @anchors = do_set("A", "matchend", "target");
  is_deeply(\@anchors, [42, 204, 777, 1001, 2021, 4242, 8008], "set A matchend target -- soft update of matching ranges"); # T44
  @anchors = do_set("A", "matchend", "target !");
  is_deeply(\@anchors, [204], "set A matchend target ! -- hard update of matching ranges");
  @anchors = do_set("A", "match", "match[-999]"); # NB: matches will be reordered!
  is_deeply(\@anchors, [0, 40, 200, 666, 1021, 3243, 7001], "set A match match[-999] -- soft shift of match anchor");
  @anchors = do_set("A", "match", "match[-999] !");
  is_deeply(\@anchors, [0, 1021, 3243, 7001], "set A match match[-999] ! -- hard shift of match anchor");
  
  @anchors = do_set("A", "match", "match[5]");
  is_deeply(\@anchors, [40, 205, 671, 999, 2020, 4242, 8005], "set A match match[5] -- match after matchend detected"); # T48
  @anchors = do_set("A", "matchend", "matchend[100] !");
  is_deeply(\@anchors, [142, 305, 877, 1101, 2121, 4342], "set A matchend matchend[100] -- cut off at end of corpus"); 
}

# match selector returns only part of a query match (new in CQP v3.4.32) T50-T58
SKIP: {
  skip "match selectors only available in CQP v3.4.32 and newer", 9 unless $cqp->check_version(3, 4, 32);

  $cqp->exec('define macro pp_of(2) '.$cqp->quote('[pos="IN"] [pos="DT"]? $0 [::] [pos="JJ.*"]+ [pos="NNS?"] $1 "of" []{0,3} [pos="NNS?"] within s')); # execute this query setting either target markers or labels
  $cqp->exec('PPof = /pp_of["@0", "@1"]');
  $cqp->exec('PPof_Sel0 = /pp_of["@0", "@1"] show match .. matchend');
  ok(nqr_eq("PPof", "PPof_Sel0"), "trivial match selector works correctly"); # T50

  $cqp->exec('PPof_1 = PPof; set PPof_1 match match[-1]; set PPof_1 matchend matchend[1]');
  $cqp->exec('PPof_Sel1 = /pp_of["@0", "@1"] show match[-1] .. matchend[1]');
  ok(nqr_eq("PPof_1", "PPof_Sel1"), "extending match by one token on each side works");

  $cqp->exec('PPof_2 = PPof; set PPof_2 match target');
  $cqp->exec('PPof_Sel2 = /pp_of["@0 adj:", "@1"] show adj .. matchend');
  ok(nqr_eq("PPof_2", "PPof_Sel2"), "label as match selector works");

  $cqp->exec('PPof_3 = PPof_2; set PPof_3 matchend keyword[-1]');
  $cqp->exec('PPof_Sel3 = /pp_of["@0 adj:", "@1 of:"] show adj .. of[-1]');
  ok(nqr_eq("PPof_3", "PPof_Sel3"), "adjusted label as match selector also works");

  $cqp->exec('PPof_4 = PPof; set PPof_4 matchend keyword[-1]');
  $cqp->exec('PPof_Sel4 = /pp_of["@0", "@1 of:"] show match .. of[-1]');
  ok(nqr_eq("PPof_4", "PPof_Sel4"), "label as match selector works for matchend");  

  ## check that selections from overlapping original matches are extracted correctly and re-sorted
  $cqp->exec('OutOfOrder = a: [pos="JJ.*"] []* b: [pos="JJ.*"] [pos = "NNS?"] :: a.lemma = b.lemma show b .. matchend');
  my @lines = $cqp->dump("OutOfOrder");
  my $len_ok = ($lines[0][1] == $lines[0][0] + 1) ? 1 : 0;
  my $order_ok = 1;
  foreach my $i (1 .. @lines-1) {
    $len_ok = 0 unless $lines[$i][1] == $lines[$i][0] + 1;
    $order_ok = 0 unless ($lines[$i][0] <=> $lines[$i-1][0] || $lines[$i][1] <=> $lines[$i-1][1]) >= 0;
  }
  ok($len_ok, "match selector works for query OutOfOrder"); # T55
  ok($order_ok, "matches re-orderd correctly for query OutOfOrder");

  ## check that duplicate matches are always discarded
  $cqp->exec('Elephant = [lemma = "elephant"]');
  $cqp->exec('Elephant_Selector = [pos = "JJ.*"]* elephant: [lemma = "elephant"] show elephant .. matchend');
  $cqp->exec('Elephant_SelectorTraditional = (?traditional) [pos = "JJ.*"]* elephant: [lemma = "elephant"] show elephant .. matchend');
  ok(matches_eq("Elephant", "Elephant_Selector"), "match selector discards duplicates"); # T57
  ok(matches_eq("Elephant", "Elephant_SelectorTraditional"), "match selector discards duplicates in (?traditional) mode");
}

exit 0;

# check that two named query results are identical
# returns TRUE / FALSE, printing diagnostics in the latter case
sub matches_eq {
  my $A = shift;
  my $B = shift;
  $cqp->exec("_Tmp = diff $A $B");
  my ($n_AmB) = $cqp->exec("size _Tmp");
  $cqp->exec("_Tmp = diff $B $A");
  my ($n_BmA) = $cqp->exec("size _Tmp");
  $cqp->exec("_Tmp = intersect $B $A");
  my ($n_AB) = $cqp->exec("size _Tmp");
  $cqp->exec("discard _Tmp");
  if ($n_AmB > 0 || $n_BmA > 0) {
    diag("Result sets A=$A and B=$B differ: (A: $n_AmB ( $n_AB ) $n_BmA :B)");
    return 0;
  }
  return 1;
}

# check that two named query results are identical including target and keyword anchors
# returns TRUE / FALSE, printing diagnostics in the latter case
sub nqr_eq {
  my $A = shift;
  my $B = shift;
  return 0 unless matches_eq($A, $B);
  my @dumpA = $cqp->dump($A);
  my @dumpB = $cqp->dump($B);
  my $ok = 1;
  $ok = 0 unless compare_anchors([map {$_->[2]} @dumpA], [map {$_->[2]} @dumpB], " Target anchors mismatch for A=$A and B=$B");
  $ok = 0 unless compare_anchors([map {$_->[3]} @dumpA], [map {$_->[3]} @dumpB], "Keyword anchors mismatch for A=$A and B=$B");
  return $ok;
}

# compare two lists of target/keyword markers, which must have the same length
# returns TRUE / FALSE, printing diagnostics if there are any differences
sub compare_anchors {
  my $x = shift;
  my $y = shift;
  my $label = (@_) ? shift : "Anchor positions differ";
  my $n = @$x;
  if ($n != @$y) {
    diag("$label: vectors of anchor positions have different lengths");
    return 0;
  }
  my ($n_notX, $n_notY, $n_diff) = (0, 0, 0);
  foreach my $i (0 .. $n - 1) {
    if ($x->[$i] < 0) {
      $n_notX++ if $y->[$i] >= 0;
    } 
    elsif ($y->[$i] < 0) {
      $n_notY++ if $x->[$i] >= 0;
    }
    else {
      $n_diff++ if $x->[$i] != $y->[$i];
    }
  }
  return 1 if ($n_notX + $n_notY + $n_diff) == 0;
  diag(sprintf "%s: %d missing in A, %d missing in B, %d different, %d correct",
               $label, $n_notX, $n_notY, $n_diff, $n - $n_notX - $n_notY - $n_diff);
  return 0;
}

# execute set <anchor> <anchor> command and return the modified anchor positions
# @new_target = do_set("A", "target", "matchend[-1]");
sub do_set {
  my ($nqr, $dest, $src) = @_;
  $cqp->exec("_Set = $nqr");
  $cqp->exec("set _Set $dest $src");
  my @res = $cqp->exec("tabulate _Set $dest");
  $cqp->exec("discard _Set");
  return @res;
}
