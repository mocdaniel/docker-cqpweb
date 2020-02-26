# -*-cperl-*-
## Test CWB::CEQL::String (strings with type & annotations)

use Test::More tests => 27;
use CWB::CEQL::String;

our $ge = new CWB::CEQL::String ">=", "Operator";
isa_ok($ge, "CWB::CEQL::String"); # T1

is($ge->value, ">=", "obtain string value"); # T2
is($ge, ">=", "implicit stringification");
is("1 $ge 0", "1 >= 0", "string interpolation");
is("1 ".$ge." 0", "1 >= 0", "string concatenation");

is($ge->type, "Operator", "obtain type information"); # T6
is(~$ge, "Operator", "using type operator '~'");
$ge->type("MathOp");
is(~$ge, "MathOp", "change type");

$ge->attribute("class", "comparison");
is($ge->attribute("class"), "comparison", "user-defined attribute"); # T9

$ge->value("<=");
is($ge, "<=", "change string value"); # T10
is(~$ge, "MathOp", "type not affected by value change");

our $cmp = $ge->copy;
$cmp->append(">");
is($cmp, "<=>", "append to string value"); # T12
is($ge, "<=", "changing copy does not affect original object");
$ge .= ">";
is($ge, "<=>", "append with .= operator"); 
is(~$ge, "MathOp", "type not affected by .= operator");

$cmp->type("PerlMathOp");
$cmp->attribute("class", "numerical_comparison");
is(~$cmp, "PerlMathOp", "changing type of copy does not affect original object"); # T16
is(~$ge, "MathOp", "changing type of copy does not affect original object");
is($cmp->attribute("class"), "numerical_comparison", "changing user attribute of copy does not affect original object");
is($ge->attribute("class"), "comparison", "changing user attribute of copy does not affect original object");

our $two = new CWB::CEQL::String 2;
ok(! defined ~$two, "create CWB::CEQL::String without type information"); # T20
is(int($two) + 1, 3, "explicit conversion to number");
is($two->value + 1, 3, "explicit conversion to number");

our $berlin = new CWB::CEQL::String "Berlin", "City";
our $brussels = new CWB::CEQL::String "Brussels", "City";
ok($berlin eq "Berlin", "string comparison with implicit conversion"); # T23
ok("Berlin" eq $berlin, "string comparison (reverse)");
ok($berlin ne $brussels, "string comparison of two CWB::CEQL::String objects");
ok($berlin le $brussels, "string inequality (two objects)");
ok("Berline" le $brussels, "string inequality (string and object)");

