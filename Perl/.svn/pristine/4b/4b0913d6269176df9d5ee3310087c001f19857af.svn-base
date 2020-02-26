# -*-cperl-*-
## Test convenience function for shell command execution

use Test::More tests => 9;

use CWB;

our @lines = ();
our $errlvl = CWB::Shell::Cmd("ls -l", \@lines);
is($errlvl, 0, "successful shell command (ls -l)"); # T1

our $N = @lines;
our $ok = $N > 6 && grep {/Makefile\.PL/} @lines;
ok($ok, "capturing output of shell command (with plausibility checks)"); # T2

$errlvl = eval {
  CWB::Shell::Cmd("'$^X' -e 'print STDERR \"THIS LINE SHOULD BE SHOWN AS A WARNING.\\n\"'"); # $^X = perl binary
};
ok(defined $errlvl, "don't die from warning message"); # T3
is($errlvl, 3, "recognise warning message on STDERR (level 3 error)"); # T4

eval { CWB::Shell::Cmd("./data/does_not_exist -flag") };
like($@, qr/SHELL CMD '.*' FAILED/, "failed shell command (nonexistent program)"); # T5

is(CWB::Shell::Quote("/usr/local/bin/cwb-make.perl"), "/usr/local/bin/cwb-make.perl", "no shell quotes for simple strings");
is(CWB::Shell::Quote("they've got 'em"), "'they'\"'\"'ve got '\"'\"'em'", "escape trick for apostrophe");
is_deeply([CWB::Shell::Quote("my_prog", "-r", "^[A-Z]*\$")], [qw(my_prog -r '^[A-Z]*$')], "CWB::Shell::Quote is vectorised"); # T8

@lines = ();
CWB::Shell::Cmd(["echo", "they've got 'em", "!"], \@lines);
is_deeply(\@lines, ["they've got 'em !"], "shell quoting actually works"); # T9
