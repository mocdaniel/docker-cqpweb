# -*-cperl-*-
## Test performance of CWB::CEQL parser (load & parsing large query)

use Test::More tests => 2;
use Time::HiRes qw(time);

our ($T0, $T_load, $T_parse);

$T0 = time;
eval "use CWB::CEQL";
our $CEQL = eval "new CWB::CEQL";
$T_load = time - $T0;
isa_ok($CEQL, "CWB::CEQL"); # T1

our $ceql_query = <<'STOP';
I said to him \, " What 's up \, Sam \? " -- the (most (_AV0)? _AJ0 | (_AV0)? _AJS) {man}
STOP
our $correct_cqp_query = <<'STOP';
[word="I"%c] [word="said"%c] [word="to"%c] [word="him"%c] [word=","%c] [word=""""%c] [word="What"%c] [word="'s"%c] [word="up"%c] [word=","%c] [word="Sam"%c] [word="\?"%c] [word=""""%c] [word="--"%c] [word="the"%c] ([word="most"%c] ([pos="AV0"])? [pos="AJ0"] | ([pos="AV0"])? [pos="AJS"]) [lemma="man"%c]
STOP
chomp $ceql_query;
chomp $correct_cqp_query;
our $cqp_query;

$T0 = time;
foreach (1 .. 100) {
  $cqp_query = $CEQL->Parse($ceql_query, "phrase_query");
}
$T_parse = time - $T0;

if (not defined $cqp_query) {
  foreach ($CEQL->ErrorMessage) { diag($_) }
}

is($cqp_query, $correct_cqp_query, "transform CEQL query into CQP code");
diag(sprintf "CEQL benchmark: %.1f ms (load), %.2f ms (parse)", $T_load * 1000, ($T_parse / 100) * 1000);
