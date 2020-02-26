# -*- mode: cperl; buffer-file-coding-system: latin-1-unix; -*-
## Test the BNCweb extension of the CEQL grammar
## !! This file must be stored in ISO-8859-1 (Latin-1) encoding !!

use warnings;
use strict;

use Test::More tests => 180;
use Time::HiRes qw(time);

our $CEQL = new BNCweb::CEQL;
isa_ok($CEQL, "BNCweb::CEQL"); # T1

## tests for error conditions (format: <query>, <test regexp>, blank line)
our $ErrorTests = << 'STOP';
,
/alternatives separator.*within brackets/

(
/bracketing.*not balanced.*too many opening/

show[,]
/empty list.*alternatives/

{kick/V} <<s>> (the (_{A})* bucket)
/expected distance operator/
STOP

## BNCweb CEQL query tests (format: <query>, <expected CQP code>, blank line)
our $QueryTests = << 'STOP';
loveliest
[word="loveliest"%c]

birds of a feather
[word="birds"%c] [word="of"%c] [word="a"%c] [word="feather"%c]

?
[word="."%c]

\,
[word=","%c]

\?
[word="\?"%c]

will \, wo n't he \?
[word="will"%c] [word=","%c] [word="wo"%c] [word="n't"%c] [word="he"%c] [word="\?"%c]

I said to him \, " What 's up \, Sam \? "
[word="I"%c] [word="said"%c] [word="to"%c] [word="him"%c] [word=","%c] [word="&(lsquo|rsquo);"%c] [word="What"%c] [word="'s"%c] [word="up"%c] [word=","%c] [word="Sam"%c] [word="\?"%c] [word="&(lsquo|rsquo);"%c]

bath
[word="bath"%c]

Bath
[word="Bath"%c]

BATH
[word="BATH"%c]

Bath:C
[word="Bath"]

from Sun:C and
[word="from"%c] [word="Sun"] [word="and"%c]

fiancee:d
[word="fiancee"%cd]

mere:d
[word="mere"%cd]

deja:d vu
[word="deja"%cd] [word="vu"%c]

Fiancee:Cd
[word="Fiancee"%d]

s?ng
[word="s.ng"%c]

sing*
[word="sing.*"%c]

sing+
[word="sing.+"%c]

imp?ss?ble
[word="imp.ss.ble"%c]

super*
[word="super.*"%c]

super+listic+ous
[word="super.+listic.+ous"%c]

neighb[our,or]
[word="neighb(our|or)"%c]

neighb[ou,o]r
[word="neighb(ou|o)r"%c]

neighbo[u,]r
[word="neighbo(u)?r"%c]

neighbo[ur,r]
[word="neighbo(ur|r)"%c]

show[s,ed,n,ing,]
[word="show(s|ed|n|ing)?"%c]

art[e,i]fact
[word="art(e|i)fact"%c]

art[e,i]fact?+
[word="art(e|i)fact..+"%c]

????
[word="...."%c]

*lier
[word=".*lier"%c]

*liest
[word=".*liest"%c]

more *ly
[word="more"%c] [word=".*ly"%c]

most *ly
[word="most"%c] [word=".*ly"%c]

??+lier
[word="...+lier"%c]

??+liest
[word="...+liest"%c]

more *ly_AJ0
[word="more"%c] [word=".*ly"%c & pos="AJ0"]

most *ly_AJ0
[word="most"%c] [word=".*ly"%c & pos="AJ0"]

more *ly_{A}
[word="more"%c] [word=".*ly"%c & class="ADJ"]

most *ly_{A}
[word="most"%c] [word=".*ly"%c & class="ADJ"]

*lier_{A}
[word=".*lier"%c & class="ADJ"]

*liest_{A}
[word=".*liest"%c & class="ADJ"]

*[lier,liest]_{A}
[word=".*(lier|liest)"%c & class="ADJ"]

*li[er,est]_{A}
[word=".*li(er|est)"%c & class="ADJ"]

*lie[r,st]_{A}
[word=".*lie(r|st)"%c & class="ADJ"]

[*lier,*liest]_{A}
[word="(.*lier|.*liest)"%c & class="ADJ"]

(more *ly_{A} | most *ly_{A})
([word="more"%c] [word=".*ly"%c & class="ADJ"] | [word="most"%c] [word=".*ly"%c & class="ADJ"])

(more | most) *ly_{A}
([word="more"%c] | [word="most"%c]) [word=".*ly"%c & class="ADJ"]

m[ore,ost] *ly_{A}
[word="m(ore|ost)"%c] [word=".*ly"%c & class="ADJ"]

_AJS
[pos="AJS"]

_[AJC,AJS]
[pos="(AJC|AJS)"]

_AJ[C,S]
[pos="AJ(C|S)"]

(more | most) _AJ0
([word="more"%c] | [word="most"%c]) [pos="AJ0"]

love[ly,lier,liest]
[word="love(ly|lier|liest)"%c]

[lovely,lovelier,loveliest]
[word="(lovely|lovelier|loveliest)"%c]

{lovely}
[hw="lovely"%c]

{kindly/ADJ}
[lemma="(kindly)_ADJ"%c]

{kindly/A}
[lemma="(kindly)_ADJ"%c]

{kindly}_[AJC,AJS]
[hw="kindly"%c & pos="(AJC|AJS)"]

{un*ly}_[AJC,AJS]
[hw="un.*ly"%c & pos="(AJC|AJS)"]

(more | most) un*ly_AJ0
([word="more"%c] | [word="most"%c]) [word="un.*ly"%c & pos="AJ0"]

*[rr+rr,ss+ss,tt+tt]*y
[word=".*(rr.+rr|ss.+ss|tt.+tt).*y"%c]

\a-grade
[word="\pL-grade"%c]

\u\L:C
[word="\p{Lu}\p{Ll}+"]

fianc\a\a:d
[word="fianc\pL\pL"%cd]

\d-\D-\D-\d
[word="\pN-\pN+-\pN+-\pN"%c]

\D\:\D
[word="\pN+:\pN+"%c]

*\u\L\u\L*:C
[word=".*\p{Lu}\p{Ll}+\p{Lu}\p{Ll}+.*"]

\u\W-\W:C
[word="\p{Lu}[\pL\pN'-]+-[\pL\pN'-]+"]

\u\A-\A:C
[word="\p{Lu}\pL+-\pL+"]

anti\A
[word="anti\pL+"%c]

\u\L:C \u\L:C
[word="\p{Lu}\p{Ll}+"] [word="\p{Lu}\p{Ll}+"]

\U-\U:C
[word="\p{Lu}+-\p{Lu}+"]

\D-fold
[word="\pN+-fold"%c]

anti\W
[word="anti[\pL\pN'-]+"%c]

black*white
[word="black.*white"%c]

\u\L-\L-\u\L:C
[word="\p{Lu}\p{Ll}+-\p{Ll}+-\p{Lu}\p{Ll}+"]

can_NN1
[word="can"%c & pos="NN1"]

beer can_NN1
[word="beer"%c] [word="can"%c & pos="NN1"]

+ice_NN+
[word=".+ice"%c & pos="NN.+"]

can_NN1*
[word="can"%c & pos="NN1.*"]

she 's_VB*
[word="she"%c] [word="'s"%c & pos="VB.*"]

she 's_VH*
[word="she"%c] [word="'s"%c & pos="VH.*"]

_NN*
[pos="NN.*"]

*_NN*
[pos="NN.*"]

_V?Z
[pos="V.Z"]

*_V?Z
[pos="V.Z"]

_V?Z*
[pos="V.Z.*"]

_VH* _V?N
[pos="VH.*"] [pos="V.N"]

can_{N}
[word="can"%c & class="SUBST"]

_{PREP} _{ART} _{A} _{N}
[class="PREP"] [class="ART"] [class="ADJ"] [class="SUBST"]

{goose}
[hw="goose"%c]

{show}
[hw="show"%c]

{show}_{V}
[hw="show"%c & class="VERB"]

{show/VERB}
[lemma="(show)_VERB"%c]

{show/V}
[lemma="(show)_VERB"%c]

{show/SUBST}
[lemma="(show)_SUBST"%c]

{show/N}
[lemma="(show)_SUBST"%c]

{separate/ADJ}
[lemma="(separate)_ADJ"%c]

{separate/VERB}
[lemma="(separate)_VERB"%c]

{separate/SUBST}
[lemma="(separate)_SUBST"%c]

{sautee*}:d_V*
[hw="sautee.*"%cd & pos="V.*"]

[proved,proven]_VVN 
[word="(proved|proven)"%c & pos="VVN"]

(proved_VVN | proven_VVN)
([word="proved"%c & pos="VVN"] | [word="proven"%c & pos="VVN"])

_VBN
[pos="VBN"]

_{PREP} ( _{ART} )? ( _{A} | _{ADV} )* _{N}
[class="PREP"] ([class="ART"])? ([class="ADJ"] | [class="ADV"])* [class="SUBST"]

_{PREP} (_{ART})? (_{A}|_{ADV})* _{N}
[class="PREP"] ([class="ART"])? ([class="ADJ"] | [class="ADV"])* [class="SUBST"]

[can,might]_{N}
[word="(can|might)"%c & class="SUBST"]

(can_{N} | might_{N})
([word="can"%c & class="SUBST"] | [word="might"%c & class="SUBST"])

(into | out of)
([word="into"%c] | [word="out"%c] [word="of"%c])

_{PREP} (_{ART})? (_{A}){3,5} _{N}
[class="PREP"] ([class="ART"])? ([class="ADJ"]){3,5} [class="SUBST"]

_{PREP} (_{ART})? _{A} ((\,|and|or)? _{A}){2,4} _{N}
[class="PREP"] ([class="ART"])? [class="ADJ"] (([word=","%c] | [word="and"%c] | [word="or"%c])? [class="ADJ"]){2,4} [class="SUBST"]

_VH* (_{PREP} (_{ART})? _{A} ((\,|and|or)?  _{A}){2,4} _{N})? _V?N 
[pos="VH.*"] ([class="PREP"] ([class="ART"])? [class="ADJ"] (([word=","%c] | [word="and"%c] | [word="or"%c])? [class="ADJ"]){2,4} [class="SUBST"])? [pos="V.N"]

(black*white | black * white)
([word="black.*white"%c] | [word="black"%c] []? [word="white"%c])

<s> but
<s> [word="but"%c]

<s> \L:C
<s> [word="\p{Ll}+"]

it {be} <hi> _{PRON} _{N}
[word="it"%c] [hw="be"%c] <hi> [class="PRON"] [class="SUBST"]

it {be} <hi> _{PRON} </hi> _{N}
[word="it"%c] [hw="be"%c] <hi> [class="PRON"] </hi> [class="SUBST"]

<quote> (+)+ </quote>
<quote> ([])+ </quote>

<mw> (+)+ </mw>
<mw> ([])+ </mw>

(_{ART})? (_{A})* (_{N})+
([class="ART"])? ([class="ADJ"])* ([class="SUBST"])+

kick <<s>> bucket
MU(meet [word="kick"%c] [word="bucket"%c] s)

{kick/V} <<s>> {bucket/N}
MU(meet [lemma="(kick)_VERB"%c] [lemma="(bucket)_SUBST"%c] s)

When:C <<3>> question
MU(meet [word="When"] [word="question"%c] -3 3)

kick >>5>> bucket
MU(meet [word="kick"%c] [word="bucket"%c] 1 5)

kick <<5<< bucket
MU(meet [word="kick"%c] [word="bucket"%c] -5 -1)

{waste/V} <<s>> time <<s>> money
MU(meet (meet [lemma="(waste)_VERB"%c] [word="time"%c] s) [word="money"%c] s)

{waste/V} <<s>> (time <<3>> money)
MU(meet [lemma="(waste)_VERB"%c] (meet [word="time"%c] [word="money"%c] -3 3) s)

{waste/V} <<s>> (time and money)
MU(meet [lemma="(waste)_VERB"%c] (meet (meet [word="time"%c] [word="and"%c] 1 1) [word="money"%c] 2 2) s)

{waste/V} <<s>> (time >>1>> and >>2>> money)
MU(meet [lemma="(waste)_VERB"%c] (meet (meet [word="time"%c] [word="and"%c] 1 1) [word="money"%c] 1 2) s)

{waste/V} <<s>> (time >>1>> (and >>1>> money))
MU(meet [lemma="(waste)_VERB"%c] (meet [word="time"%c] (meet [word="and"%c] [word="money"%c] 1 1) 1 1) s)

and <<1<< time >>1>> money
MU(meet (meet [word="and"%c] [word="time"%c] -1 -1) [word="money"%c] 1 1)

fiancée
[word="fiancée"%c]

déjà vu
[word="déjà"%c] [word="vu"%c]

école
[word="école"%c]

ecole:d
[word="ecole"%cd]

d&eacute;j&agrave; vu
[word="déjà"%c] [word="vu"%c]

£
[word="£"%c]

&pound;
[word="£"%c]

&alpha;-particles
[word="&alpha;-particles"%c]

&hearts;
[word="&hearts;"%c]

&delta;T
[word="&delta;T"%c]

*helli*
[word=".*helli.*"%c]

\<+\>
[word="&lt;.+&gt;"%c]

glitterati
[word="glitterati"%c]

*able
[word=".*able"%c]

+able
[word=".+able"%c]

??+able
[word="...+able"%c]

*oo+oo*
[word=".*oo.+oo.*"%c]

??+[able,ability]
[word="...+(able|ability)"%c]

bath
[word="bath"%c]

Bath
[word="Bath"%c]

BATH
[word="BATH"%c]

Bath:C
[word="Bath"]

lights_NN2
[word="lights"%c & pos="NN2"]

super+_V*
[word="super.+"%c & pos="V.*"]

_PNX
[pos="PNX"]

super+_{VERB}
[word="super.+"%c & class="VERB"]

{light/V}
[lemma="(light)_VERB"%c]

{light/N}
[lemma="(light)_SUBST"%c]

{light/A}
[lemma="(light)_ADJ"%c]

talk of the town
[word="talk"%c] [word="of"%c] [word="the"%c] [word="town"%c]

{number/N} of _{A} _NN2
[lemma="(number)_SUBST"%c] [word="of"%c] [class="ADJ"] [pos="NN2"]

{eat} * up
[hw="eat"%c] []? [word="up"%c]

{eat} + up
[hw="eat"%c] [] [word="up"%c]

{eat} ++* up
[hw="eat"%c] []{2,3} [word="up"%c]

the (most _AJ0 | _AJS) {man}
[word="the"%c] ([word="most"%c] [pos="AJ0"] | [pos="AJS"]) [hw="man"%c]

the (most (_AV0)? _AJ0 | (_AV0)? _AJS) {man}
[word="the"%c] ([word="most"%c] ([pos="AV0"])? [pos="AJ0"] | ([pos="AV0"])? [pos="AJS"]) [hw="man"%c]

_{PREP} (_{ART})? ((_{ADV})? _{A})* _{N}
[class="PREP"] ([class="ART"])? (([class="ADV"])? [class="ADJ"])* [class="SUBST"]

_{ART} </s> 
[class="ART"] </s>

day <<3>> night 
MU(meet [word="day"%c] [word="night"%c] -3 3)

day >>3>> night 
MU(meet [word="day"%c] [word="night"%c] 1 3)

day <<3<< night 
MU(meet [word="day"%c] [word="night"%c] -3 -1)

{day} <<5>> {month} <<5>> {year}
MU(meet (meet [hw="day"%c] [hw="month"%c] -5 5) [hw="year"%c] -5 5)
STOP

## test CEQL queries against expected results
our @QueryTests = split /\n/, $QueryTests;
my $T0 = time;
my $n_queries = 0;
while (@QueryTests) {
  my $query = shift @QueryTests;
  my $expected = shift @QueryTests;
  if (@QueryTests) {
    my $blank = shift @QueryTests;
    die "Shucks! Syntax error in list of query tests (expected blank line)."
      unless $blank =~ /^\s*$/;
  }
  my $msg = "query ``$query''";
  my $result = $CEQL->Parse($query);
  if (defined $result) {
    is($result, $expected, $msg);
  }
  else {
    fail($msg);
    foreach ($CEQL->ErrorMessage) { diag($_) };
  }
  $n_queries++;
}
my $dT = time - $T0;
pass("CEQL benchmark");
diag(sprintf "%.0f ms for %d queries = %.2f ms / query", 1000 * $dT, $n_queries, 1000 * ($dT / $n_queries));

## test whether syntax errors are recognised by the CEQL parser
our @ErrorTests = split /\n/, $ErrorTests;
while (@ErrorTests) {
  my $query = shift @ErrorTests;
  my $regexp = shift @ErrorTests;
  if (@ErrorTests) {
    my $blank = shift @ErrorTests;
    die "Shucks! Syntax error in list of error tests (expected blank line)."
      unless $blank =~ /^\s*$/;
  }
  my $msg = "find syntax error in ``$query''";
  my $result = $CEQL->Parse($query);
  if (defined $result) {
    fail($msg);
  }
  else {
    like($CEQL->HtmlErrorMessage, $regexp, $msg);
  }
}


########## BEGIN 'BNCweb::CEQL' grammar

package BNCweb::CEQL;
use base 'CWB::CEQL';

use Encode;
use HTML::Entities; # real BNCweb implementation uses this module to decode/encode HTML entities

# constructor: set up attribute names and define simplified POS tags
sub new {
  my $class = shift;
  my $self = new CWB::CEQL;
  $self->SetParam("lemma_attribute", "hw"); # corresponds to lemma in standard CEQL grammar
  my $table = { # define lookup table for simple POS tags (refer to class attribute)
               "A" => "ADJ",
               "ADJ" => "ADJ",
               "N" => "SUBST",
               "SUBST" => "SUBST",
               "V" => "VERB",
               "VERB" => "VERB",
               "ADV" => "ADV",
               "ART" => "ART",
               "CONJ" => "CONJ",
               "INT" => "INTERJ",
               "INTERJ" => "INTERJ",
               "PREP" => "PREP",
               "PRON" => "PRON",
               '$' => "STOP",
               "STOP" => "STOP",
               "UNC" => "UNC",
              };
  $self->SetParam("simple_pos", $table);
  $self->SetParam("simple_pos_attribute", "class");
  my %xml_tags = map { $_ => 1 } # list of s-attribute regions in the BNC version used by BNCweb
    (qw(text u div head quote sp speaker stage lg l list label item note bibl corr hi trunc p s mw), # from CWB registry file
     # nested attributes are accepted, but should perhaps better be inserted automagically
     qw(div1 div2 div3 quote1 list1 list2 item1 item2 hi1 p1 p2));
  $self->SetParam("s_attributes", \%xml_tags);
  return bless($self, $class);
}

# BNCweb::CEQL expects its input to be in the canonical BNCweb encoding, i.e. Latin-1 + HTML entities;
# the "default" rule first converts the input to a Perl Unicode string, and then re-encodes the resulting CQP query in Latin-1
sub default {
  my ($self, $input) = @_;
  my $unicode = decode("iso-8859-1", $input);
  ##-- # the real implementation uses the HTML::Entities module to decode HTML entities
  ##-- decode_entities($unicode);
  # here, dummy rules covering all entities in the test suite help us to avoid a dependency on the non-standard HTML::Entities module
  $unicode =~ s/\&eacute;/\x{E9}/g;
  $unicode =~ s/\&agrave;/\x{E0}/g;
  $unicode =~ s/\&pound;/\x{A3}/g;
  $unicode =~ s/\&alpha;/\x{03B1}/g;
  $unicode =~ s/\&hearts;/\x{2665}/g;
  $unicode =~ s/\&delta;/\x{03B4}/g;
  # end of dummy rules
  my $cqp_unicode = $self->Call("ceql_query", $unicode);
  return encode("iso-8859-1", $cqp_unicode, Encode::FB_CROAK);
}

# override literal_string rule to insert HTML entities (for non-Latin-1 characters and special treatment of ")
sub literal_string {
  my ($self, $input) = @_;
  $input =~ s/\\//g; # remove backslashes (used to escape CEQL metacharacters)
  ##-- # the real implementation uses the HTML::Entities module to insert HTML entities
  ##-- encode_entities($input, '<>&');            # unsafe characters <, >, & are HTML entities in the canonical BNCweb encoding
  ##-- encode_entities($input, '^\x{00}-\x{FF}'); # encode non-Latin-1 characters as HTML entities (but keep $input in Unicode for now)
  # here, dummy rules covering all entities in the test suite help us to avoid a dependency on the non-standard HTML::Entities module
  $input =~ s/&/&amp;/g;
  $input =~ s/</&lt;/g;
  $input =~ s/>/&gt;/g;
  $input =~ s/\x{03B1}/\&alpha;/g;
  $input =~ s/\x{2665}/\&hearts;/g;
  $input =~ s/\x{03B4}/\&delta;/g;
  # end of dummy rules
  $input =~ s/([.?*+|(){}\[\]\^\$])/\\$1/g;  # escape CQP regexp metacharacters (" is treated separately below)
  $input =~ s/"/&(lsquo|rsquo);/g;           # special handling of " to match both left and right quotes
  return $input;
}

# override lemma_pattern rule to provide support for {book/V} notation
sub lemma_pattern {
  my ($self, $lemma) = @_;
  my $simple_pos = $self->GetParam("simple_pos");
  die "simplified POST tags are not available (internal error)\n"
    unless ref($simple_pos) eq "HASH";
  # split lemma into headword pattern and optional simple POS constraint
  my ($hw, $tag, $extra) = split /(?<!\\)\//, $lemma;
  die "only a single ''/'' separator is allowed between headword and simplified POS in lemma constraint\n"
    if defined $extra;
  die "missing headword in lemma constraint (did you mean ''_{$tag}''?)\n"
    if $hw eq "";
  # translate wildcard pattern for headword and look up simple POS if specified
  my $regexp = $self->Call("wildcard_pattern", $hw);
  if (defined $tag) {
    # simple POS specified => look up in $simple_pos an combine with $regexp
    my $tag_regexp = $simple_pos->{$tag};
    if (not defined $tag_regexp) {
      my @valid_tags = sort keys %$simple_pos;
      die "'' $tag '' is not a valid simple part-of-speech tag (available tags: '' @valid_tags '')\n";
    }
    $regexp =~ s/^"//; $regexp =~ s/"$//; # remove double quotes around regexp so it can be combined with POS constraint
    return "lemma=\"($regexp)_${tag_regexp}\"";
  }
  else {
    # no simple POS specified => match hw attribute instead of lemma
    return "hw=$regexp";
  }
}


########## END   'BNCweb::CEQL' grammar

package main;

