package CWB::CEQL;
use base 'CWB::CEQL::Parser';

use warnings;
use strict;

use Carp;
use CWB::CEQL::String;

=head1 NAME

CWB::CEQL - The Common Elementary Query Language for CQP front-ends

=head1 SYNOPSIS

  # end users: see section "CEQL SYNTAX" below for an overview of CEQL notation

  use CWB::CEQL;
  our $CEQL = new CWB::CEQL;

  # configuration settings (see METHODS section for details and default values)
  $CEQL->SetParam("pos_attribute", "tags");         # p-attribute for POS tags
  $CEQL->SetParam("lemma_attribute", "hw");         # p-attribute for lemmas
  $CEQL->SetParam("simple_pos", \%lookup_table);    # lookup table for simple POS
  $self->NewParam("simple_pos_attribute", "class"); # p-attribute for simple POS
  $self->NewParam("s_attributes", {"s" => 1});      # s-attributes allowed in CEQL queries
  $self->NewParam("default_ignore_case", 1);        # if 1, default to case-folded search
  $self->NewParam("default_ignore_diac", 0);        # if 1, default to accent-folded search

  $cqp_query = $CEQL->Parse($ceql_query);
  if (not defined $cqp_query) {
    @error_msg = $CEQL->ErrorMessage;
    $html_msg = $CEQL->HtmlErrorMessage;
  }
  # $cqp_query can now be sent to CQP backend (e.g. with CWB::CQP module)

  #### extend or modify standard CEQL grammar by subclassing ####
  package BNCWEB::CEQL;
  use base 'CWB::CEQL';

  sub lemma {
    ## overwrite 'lemma' rule here (e.g. to allow for BNCweb's ``{bucket/N}'' notation)
    my $orig_result = $self->SUPER::lemma($string); # call original rule if needed
  }

  ## you can now use BNCWEB::CEQL in the same way as CWB::CEQL

=head1

=head1 DESCRIPTION

This module implements the core syntax of the B<Common Elementary Query Language> (B<CEQL>) as a B<DPP> grammar (see L<CWB::CEQL::Parser> for details).
It can either be used directly, adjusting configuration settings with the B<SetParam> method as required, or subclass B<CWB::CEQL> in order to modify and/or extend the grammar.  In the latter case, you are strongly advised not to change the meaning of core CEQL features, so that end-users can rely on the same familiar syntax in all CEQL-based Web interfaces.

A complete specification of the core CEQL syntax can be found in section L</"CEQL SYNTAX"> below.  This is the most important part of the documentation for end users and can also be found online at L<http://cwb.sf.net/ceql.php>.

Application developers can find an overview of relevant API methods and the available configuration parameters (CWB attributes for different linguistic annotations, default case/accent-folding, etc.) in section L</"METHODS">.

Section L</"EXTENDING CEQL"> explains how to extend or customise CEQL syntax by subclassing B<CWB::CEQL>.  It is highly recommended to read the technical documentation in section L</"STANDARD CEQL RULES"> and the source code of the B<CWB::CEQL> module.  Extended rules are most conveniently implemented as modified copies of the methods defined there.


=head1 METHODS

The following API methods are inherited from B<CWB::CEQL::Parser>.  The explanations below focus on their application in a CEQL simple query frontend.  The documentation of B<SetParam> includes a complete listing of available configuration parameters as well as their usage and default values.

=over 4

=item I<$CEQL> = B<new> CWB::CEQL;

Create parser object for CEQL queries.  Use the B<Parse> method of I<$CEQL>
to translate a CEQL query into CQP code.

=cut

sub new {
  my $class = shift;
  my $self = new CWB::CEQL::Parser;
  $self->NewParam("pos_attribute", "pos");
  $self->NewParam("lemma_attribute", "lemma");
  $self->NewParam("simple_pos", undef);
  $self->NewParam("simple_pos_attribute", undef);
  $self->NewParam("s_attributes", { "s" => 1 });
  $self->NewParam("default_ignore_case", 1);
  $self->NewParam("default_ignore_diac", 0);
  return bless($self, $class);
}

=item I<$cqp_query> = I<$CEQL>->B<Parse>(I<$simple_query>);

Parses simple query in CEQL syntax and returns equivalent CQP code.  If there
is a syntax error in I<$simple_query> or parsing fails for some other reason,
an B<undef>ined value is returned.

=item @text_lines = I<$CEQL>->B<ErrorMessage>;

=item $html_code = I<$CEQL>->B<HtmlErrorMessage>;

If the last CEQL query failed to parse, these methods return an error message
either as a list of text lines (B<ErrorMessage>) or as pre-formatted HTML code
that can be used directly by a Web interface (B<HtmlErrorMessage>).  The error
message includes a backtrace of the internal call stack in order to help users
identify the precise location of the problem.

=item I<$CEQL>->B<SetParam>(I<$name>, I<$value>);

Change parameters of the CEQL grammar.  Currently, the following parameters
are available:

=over 4

=item C<pos_attribute>

The p-attribute used to store part-of-speech tags in the CWB corpus (default:
C<pos>).  CEQL queries should not be used for corpora without POS tagging,
which we consider to be a minimal level of annotation.

=item C<lemma_attribute>

The p-attribute used to store lemmata (base forms) in the CWB corpus (default:
C<lemma>).  Set to B<undef> if the corpus has not been lemmatised.

=item C<simple_pos>

Lookup table for simple part-of-speech tags (in CEQL constructions like
C<run_{N}>).  Must be a hashref with simple POS tags as keys and CQP regular
expressions matching an appropriate set of standard POS tags as the
corresponding values.  The default value is B<undef>, indicating that no
simple POS tags have been defined.  A very basic setup for the Penn
Treebank tag set might look like this:

  $CEQL->SetParam("simple_pos", {
      "N" => "NN.*",   # common nouns
      "V" => "V.*",    # any verb forms
      "A" => "JJ.*",   # adjectives
    });

=item C<simple_pos_attribute>

Simple POS tags may use a different p-attribute than standard POS tags,
specified by the C<simple_pos_attribute> parameter.  If it is set to B<undef>
(default), the C<pos_attribute> will be used for simplified POS tags as well.

=item C<s_attributes>

Lookup table indicating which s-attributes in the CWB corpus may be accessed
in CEQL queries (using the XML tag notation, e.g. C<< <s> >> or C<< </s> >>,
or as a distance operator in proximity queries, e.g. C<<< <<s>> >>>).  The
main purpose of this table is to keep the CEQL parser from passing through
arbitrary tags to the CQP code, which might generate confusing error messages.
Must be a hashref with the names of valid s-attributes as keys mapped to TRUE
values.  The default setting only allows sentences or s-unit, which should be
annotated in every corpus:

  $CEQL->SetParam("s_attributes", { "s" => 1 });

=item C<default_ignore_case>

Indicates whether CEQL queries should perform case-insensitive matching for
word forms and lemmas (C<:c> modifier), which can be overridden with an
explicit C<:C> modifier.  By default, case-insensitive matching is activated,
i.e. C<default_ignore_case> is set to 1.

=item C<default_ignore_diac>

Indicates whether CEQL queries should ignore accents (I<diacritics>) for word
forms and lemmas (C<:d> modifier), which can be overridden with an explicit
C<:D> modifier.  By default, matching does I<not> ignore accents,
i.e. C<default_ignore_diac> is set to 0.

=back

=back

See the L<CWB::CEQL::Parser> manpage for more detailed information and further methods.


=head1 CEQL SYNTAX

B<** TODO **>


=head1 EXTENDING CEQL

B<** TODO **>: How to extend the standard CEQL grammar by subclassing. Note that the grammar is split into many small rules, so
it is easy to modify by overriding individual rules completely (without having to call the original rule in between or having to
replicate complicated functionality).

See L<CWB::CEQL::Parser> for details on how to write grammar rules. You should always have a copy of the B<CWB::CEQL> source code
file at hand when writing your extensions. All rules of the standard CEQL grammar are listed below with short descriptions of their function and purpose.


=head1 STANDARD CEQL RULES

=over 4

=item C<ceql_query>

=item C<default>

The default rule of B<CWB::CEQL> is C<ceql_query>.  After sanitising
whitespace, it uses a heuristic to determine whether the input string is a
B<phrase query> or a B<proximity query> and delegates parsing to the
appropriate rule (C<phrase_query> or C<proximity_query>).

=cut

sub default {
  return ceql_query(@_); # pass through directly to ceql_query(), without explicit Call()
}

sub ceql_query {
  my ($self, $input) = @_;
  $input =~ s/\s+/ /g;          # change all whitespace to single blanks
  $input =~ s/^\s+//; $input =~ s/\s+$//; # remove leading/trailing whitespace
  # check whether there's something in the query that looks like a distance operator (same regexp as used in proximity_query rule)
  if ($input =~ /(?<!\\)((<<|>>)[^<>\\ ]*(<<|>>))/) {
    return $self->Call("proximity_query", $input);
  }
  else {
    return $self->Call("phrase_query", $input);
  }
}

=back


=head2 Phrase Query

=over 4

=item C<phrase_query>

A phrase query is the standard form of CEQL syntax.  It matches a single token
described by constraints on word form, lemma and/or part-of-speech tag, a
sequence of such tokens, or a complex lexico-grammatical pattern.  The
C<phrase_query> rule splits its input into whitespace-separated token
expressions, XML tags and metacharacters such as C<(>, C<)> and C<|>.  Then it
applies the C<phrase_element> rule to each item in turn, and concatenates the
results into the complete CQP query.  The phrase query may start with an embedded
modifier such as C<(?longest)> to change the matching strategy.

=cut

sub phrase_query {
  my ($self, $input) = @_;
  my $modifier = "";
  if ($input =~ s/^\s*\(\?\s*(\w+)\s*\)\s*//) {
    $modifier = $1;
    die "invalid modifier (?$modifier) -- specify (?longest), (?shortest) or (?standard)\n"
      unless $modifier =~ /^(longest|shortest|standard|traditional)$/i;
    $modifier = "(?$modifier) ";
  }

  # insert whitespace around phrase-level metacharacters
  $input =~ s{(?<!\\)(</?[A-Za-z0-9_-]+>)}{ $1 }g; # XML tags (only standard CWB attribute names)
  $input =~ s{(?<!\\)([(|])}{ $1 }g; # opening parenthesis ( and alternative marker |
  $input =~ s{(?<!\\)([)][*+?{},0-9]*)}{ $1 }g; # closing parenthesis with optional quantifier (gobbles up all relevant characters to catch syntax errors)
  # strip leading and trailing blanks, then split on whitespace
  $input =~ s/^\s+//;
  $input =~ s/\s+$//;
  my @items = split " ", $input;
  # apply shift-reduce parser to item sequence
  my @cqp_code = $self->Apply("phrase_element", @items);
  return $modifier."@cqp_code";
}

=item C<phrase_element>

A phrase element is either a token expression (delegated to rule
C<token_expression>), a XML tag for matching structure boundaries (delegated
to rule C<xml_tag>), sequences of arbitrary (C<+>) or skipped (C<*>) tokens,
or a phrase-level metacharacter (the latter two are handled by the
C<phrase_element> rule itself).  Proper nesting of parenthesised groups is
automatically ensured by the parser.

Token expressions can be preceded by C<@> to set a target marker.

=cut

sub phrase_element {
  my ($self, $item) = @_;
  if ($item eq "(") {
    $self->BeginGroup("(...)"); # use named group to generate meaningful error messages
    return "";
  }
  elsif ($item eq "|") {
    die "alternatives separator (''|'') may only be used within parentheses ''( .. )''\n"
      unless $self->NestingLevel > 0;
    return "|";
  }
  elsif ($item =~ /^\)/) {
    my @parts = $self->EndGroup("(...)");
    die "groups ''( ... )'' must not be empty\n"
      unless @parts > 0;
    my ($has_empty_alternative) = $self->_remove_empty_alternatives(@parts);
    die "empty alternatives not allowed in phrase query\n"
      if $has_empty_alternative;
    if ($item eq ")") {
      return "(@parts)";
    }
    elsif ($item =~ /^\)([?*+]|[{][0-9]+(,[0-9]*)?[}])$/) {
      return "(@parts)$1";
    }
    else {
      $item =~ s/^\)//;
      die "invalid quantifier '' $item '' on closing parenthesis\n";
    }
  }
  elsif ($item =~ /^<.*>$/) {
    return $self->Call("xml_tag", $item);
  }
  elsif ($item =~ /^[*+]+$/) {
    return "[]?" if $item eq "*";  # special cases make CQP query more natural
    return "[]" if $item eq "+";
    my $n_plus = $item =~ tr/+/+/; # count number of + and * characters
    my $n_ast = $item =~ tr/*/*/;
    my $min_count = $n_plus;
    my $max_count = $n_plus + $n_ast;
    return "[]{$min_count,$max_count}";
  }
  else {
    my $target = ($item =~ s/^@//) ? "@" : "";
    return $target.$self->Call("token_expression", $item);
  }
}

=item C<xml_tag>

A start or end tag matching the boundary of an s-attribute region. The
C<xml_tag> rule performs validation, in particularly ensuring that the
region name is listed as an allowed s-attribute in the parameter
C<s_attributes>, then passes the tag through to the CQP query.

For a start tag, an optional wildcard pattern constraint may be specified
in the form C<<< <I<tag>=I<pattern>> >>>. The parser does not check whether
the selected s-attribute in fact has annotations. Note that case- and
diacritic-insensitive matching is not supported (not even as a default option).

=cut

sub xml_tag {
  my ($self, $tag) = @_;
  my ($name, $closing, $value);
  if ($tag =~ /^<\/([^\/<>=]+)>$/) {
    $name = $1;
    $closing = "/";
    $value = undef;
  }
  elsif ($tag =~ /^<([^\/<>=]+)(=([^\/<>]+))?>$/) {
    $name = $1;
    $closing = "";
    $value = $3;
  }
  else {
    die "syntax error in XML tag '' $tag ''\n";
  }
  my $is_valid_tag = $self->GetParam("s_attributes");
  if (ref($is_valid_tag) eq "HASH") {
    unless ($is_valid_tag->{$name}) {
      my @valid_tags = map {"<$_>"} sort keys %$is_valid_tag;
      die "invalid XML tag '' $tag '' (allowed tags: ''@valid_tags'')\n";
    }
  }
  else {
    die "XML tags are not allowed in this corpus\n";
  }

  if (defined $value) {
    my $regex = $self->Call("wildcard_pattern", $value);
    return "<$name = $regex>";
  }
  else {
    return "<$closing$name>";
  }
}

=back


=head2 Proximity Query

=over 4

=item C<proximity_query>

A proximity query searches for combinations of words within a certain distance
of each other, specified either as a number of tokens (I<numeric distance>) or
as co-occurrence within an s-attribute region (I<structural distance>).  The
C<proximity_query> rule splits its input into a sequence of token patterns,
distance operators and parentheses used for grouping.  Shorthand notation for
word sequences is expanded (e.g. C<as long as> into C<<< as >>1>> long >>2>>
as >>>), and then the C<proximity_expression> rule is applied to each item in
turn.  A shift-reduce algorithm in C<proximity_expression> reduces the
resulting list into a single CQP query (using the undocumented "MU" notation).

=cut

sub proximity_query {
  my ($self, $input) = @_;
  $input =~ s/(?<!\\)([()])/\t$1\t/g; # separate parentheses and distance operators with TABs
  $input =~ s/(?<!\\)((<<|>>)[^<>\\ ]*(<<|>>))/\t$1\t/g;
  $input =~ s/^\s+//; $input =~ s/\s+$//; # strip leading/trailing whitespace
  my @items = split /\s*\t\s*/, $input; # split on TABs into proximity operators, parentheses, token expressions (removes extra whitespace)
  # pre-process shorthand notation for word sequences (such as "as long as")
  @items = map {
    if (/\s/) {
      my @shorthand = split " ";
      my @expanded =  ("(", $shorthand[0]);
      foreach my $i (1 .. $#shorthand) {
        push @expanded, ">>$i,$i>>", $shorthand[$i];
      }
      push @expanded, ")";
      @expanded;
    }
    else {
      $_; # single token expressions, distance operators and parentheses are passed through
    }
  } @items;
  # now apply proximity_expression rule to each item, which should eventually return a single term
  my @query = $self->Apply("proximity_expression", @items);
  die "incomplete proximity query: expected another term after distance operator\n"
    if @query == 2 and $query[1]->type eq "Op";
  confess "shift-reduce parsing with **proximity_expression** failed to return a single term"
    unless @query == 1 and $query[0]->type eq "Term"; # better safe than sorry ...
  return "MU$query[0]";
}

=item C<proximity_expression>

A proximity expression is either a token expression (delegated to
C<token_expression>), a distance operator (delegated to C<distance_operator>)
or a parenthesis for grouping subexpressions (handled directly).  At each
step, the current result list is examined to check whether the respective type
of proximity expression is valid here.  When 3 elements have been collected in
the result list (term, operator, term), they are reduced to a single term.
This ensures that the B<Apply> method in C<proximity_query> returns only a
single string containing the (almost) complete CQP query.

=cut

sub proximity_expression {
  my ($self, $item) = @_;
  my $result_list = $self->currentGroup;
  my $n_results = @$result_list; # current position in result list
  my $new_term = undef;
  # handle different types of proximity expressions
  if ($item eq "(") {
    die "cannot start subexpression at this point, expected distance operator\n"
      unless $n_results == 0 or $n_results == 2;
    $self->BeginGroup("(...)"); # named group makes error messages more meaningful
    return "";
  }
  elsif ($item eq ")") {
    my @subexp = $self->EndGroup("(...)");
    die "empty subexpression not allowed in proximity query\n"
      if @subexp == 0;
    die "incomplete subexpression in proximity query: expected another term after distance operator\n"
      if @subexp == 2 and $subexp[1]->type eq "Op";
    confess "shift-reduce parsing of subexpression in **proximity_expression** failed to return a single term"
      unless @subexp == 1 and $subexp[0]->type eq "Term"; # better safe than sorry ...
    $new_term = $subexp[0];
    $result_list = $self->currentGroup; # EndGroup() has moved back to the parent result list, so update local variables
    $n_results = @$result_list;
  }
  elsif ($item =~ /^(<<|>>).*(<<|>>)$/) {
    die "distance operator not allowed at this point, expected token expression or parenthesis\n"
      unless $n_results == 1;
    $new_term = $self->Call("distance_expression", $item);
  }
  elsif ($item =~ /^[*+]+$/) {
    die "optional/skipped tokens ''$item'' not allowed in proximity query\n";
  }
  else {
    die "token expression not allowed at this point, expected distance operator\n"
      unless $n_results == 0 or $n_results == 2;
    my $token_exp = $self->Call("token_expression", $item);
    $new_term = new CWB::CEQL::String $token_exp, "Term";
  }
  # if new term is third element on result list, reduce "Term Op Term" to "Term"
  confess "invalid state of result list with $n_results + 1 elements (internal error)"
    if $n_results > 2;
  if ($n_results == 2) {
    my $term = shift @$result_list; # pop elements from result list for reduce operation
    my $op = shift @$result_list;
    my @types = map { $_->type } ($term, $op, $new_term);
    confess "invalid state ''@types'' of result list (internal error)"
      unless "@types" eq "Term Op Term";
    return new CWB::CEQL::String "(meet $term $new_term $op)", "Term";
  }
  else {
    return $new_term;
  }
}

=item C<distance_operator>

A distance operator specifies the allowed distance between two tokens or
subexpressions in a proximity query.  Numeric distances are given as a number
of tokens and can be two-sided (C<<< <<n>> >>>) or one-sided (C<<< <<n<< >>>
to find the second term to the left of the first, or C<<< >>n>> >>> to find it
to the right).  Structural distances are always two-sided and specifies an
s-attribute region, in which both items must co-occur (e.g. C<<< <<s>> >>>).

=cut

sub distance_expression {
  my ($self, $op) = @_;
  $op =~ /^(<<|>>)(.+)(<<|>>)$/
    or die "syntax error in distance operator '' $op ''\n";
  my $type = "$1$3";
  my $distance = $2;
  die "invalid distance type ''>>..<<'' in distance operator '' $op ''\n"
    if $type eq ">><<";
  if ($distance =~ /^(?:([1-9][0-9]*),)?([1-9][0-9]*)$/) {
    # numeric distance
    my ($min, $max) = ($1, $2);
    die "maximum distance must be greater than or equal to minimum distance in '' $op ''\n"
      if $min and not $max >= $min;
    die "distance range ''$distance'' not allowed for two-sided distance '' $op ''\n"
      if $min and $type eq "<<>>";
    $min = 1 unless $min;
    if ($type eq "<<>>")    { return new CWB::CEQL::String "-$max $max", "Op" }
    elsif ($type eq "<<<<") { return new CWB::CEQL::String "-$max -$min", "Op" }
    elsif ($type eq ">>>>") { return new CWB::CEQL::String "$min $max", "Op" }
    else { confess "This can't happen." }
  }
  else {
    # structural distance
    my $is_valid_region = $self->GetParam("s_attributes") || {};
    if ($is_valid_region->{$distance}) {
      die "structural distance must be two-sided (''<<..>>'')\n"
        unless $type eq "<<>>";
      return new CWB::CEQL::String $distance, "Op";
    }
    else {
      my @valid_ops = map {"<<$_>>"} sort keys %$is_valid_region;
      die "'' $op '' is neither a numeric distance nor a valid structural distance (supported structures: ''@valid_ops'')\n";
    }
  }
}

=back


=head2 Token Expression

=over 4

=item C<token_expression>

Evaluate complete token expression with word form (or lemma) constraint and or
part-of-speech (or simple POS) constraint.  The two parts of the token
expression are passed on to C<word_or_lemma_constraint> and C<pos_constraint>,
respectively.  This rule returns a CQP token expression enclosed in square
brackets.

=cut

sub token_expression {
  my ($self, $input) = @_;
  my @parts = split /(?<!\\)_/, $input, -1; # split input on unescaped underscores
  die "only a single ''_'' separator allowed between word form and POS constraint (use ''\\_'' to match literal underscore)\n"
    if @parts > 2;
  my ($word, $pos) = @parts;
  $word = "" unless defined $word;
  $pos = "" unless defined $pos;
  my ($cqp_word, $cqp_pos) = (undef, undef);
  if ($word ne "" and           # optimise *_ITJ to _ITJ (to avoid word form constraint matching all words)
      not ($word =~ /^[+*]$/ and $pos ne "")) {
    $cqp_word = $self->Call("word_or_lemma_constraint", $word);
  }
  if ($pos ne "") {
    $cqp_pos = $self->Call("pos_constraint", $pos);
  }

  if (defined $cqp_word and defined $cqp_pos) {
    return "[$cqp_word \& $cqp_pos]";
  }
  elsif (defined $cqp_word) {
    return "[$cqp_word]";
  }
  elsif (defined $cqp_pos) {
    return "[$cqp_pos]";
  }
  else {
    die "neither word form nor part-of-speech constraint in token expression '' $input ''\n";
  }
}

=back

=head2 Word Form / Lemma

=over 4

=item C<word_or_lemma_constraint>

Evaluate complete word form or lemma constraint, including case/diacritics
flags, and return suitable CQP code to be included in a token expression

=cut

sub word_or_lemma_constraint {
  my ($self, $input) = @_;
  my $ignore_case = ($self->GetParam("default_ignore_case")) ? 1 : 0;
  my $ignore_diac = ($self->GetParam("default_ignore_diac")) ? 1 : 0;
  if ($input =~ s/(?<!\\):([A-Za-z]+)$//) {
    my $flags = $1;
    foreach my $flag (split //, $flags) {
      if ($flag eq "c")    { $ignore_case = 1 }
      elsif ($flag eq "C") { $ignore_case = 0 }
      elsif ($flag eq "d") { $ignore_diac = 1 }
      elsif ($flag eq "D") { $ignore_diac = 0 }
      else { die "invalid flag ''$flag'' in modifier '':$flags''\n" }
    }
  }
  my $cqp_code = $self->Call("word_or_lemma", $input);
  if ($ignore_case or $ignore_diac) {
    $cqp_code .= '%';
    $cqp_code .= "c" if $ignore_case;
    $cqp_code .= "d" if $ignore_diac;
  }
  return $cqp_code;
}

=item C<word_or_lemma>

Evaluate word form (without curly braces) or lemma constraint (in curly braces,
or with alternative C<%> marker appended) and return a single CQP constraint,
to which C<%c> and C<%d> flags can then be added.

=cut

sub word_or_lemma {
  my ($self, $input) = @_;
  if ($input =~ /^\{(.+)\}$/) {
    return $self->Call("lemma_pattern", $1);
  }
  elsif ($input =~ /^\{/ or $input =~ /(?<!\\)\}$/) {
    die "lonely curly brace (''{'' or ''}'') at start/end of word form pattern -- did you intend to search by lemma as in ''{be}''?\n";
  }
  elsif ($input =~ s/(?<!\\)%$//) {
    return $self->Call("lemma_pattern", $input);
  }
  else {
    return $self->Call("wordform_pattern", $input);
  }
}

=item C<wordform_pattern>

Translate wildcard pattern for word form into CQP constraint (using the
default C<word> attribute).

=cut

sub wordform_pattern {
  my ($self, $wf) = @_;
  my $regexp = $self->Call("wildcard_pattern", $wf);
  return "word=$regexp";
}

=item C<lemma_pattern>

Translate wildcard pattern for lemma into CQP constraint, using the
appropriate p-attribute for base forms (given by the parameter
C<lemma_attribute>).

=cut

sub lemma_pattern {
  my ($self, $lemma) = @_;
  my $attr = $self->GetParam("lemma_attribute")
    or die "lemmatisation is not available for this corpus\n";
  my $regexp = $self->Call("wildcard_pattern", $lemma);
  return "$attr=$regexp";
}

=back


=head2 Parts of Speech

=over 4

=item C<pos_constraint>

Evaluate a part-of-speech constraint (either a C<pos_tag> or C<simple_pos>),
returning suitable CQP code to be included in a token expression.

=cut

sub pos_constraint {
  my ($self, $input) = @_;
  if ($input =~ /^\{(.+)\}$/) {
    return $self->Call("simple_pos", $1);
  }
  elsif ($input =~ /^\{/ or $input =~ /(?<!\\)\}$/) {
    die "lonely curly brace (''{'' or ''}'') at start/end of part-of-speech constraint -- did you intend to use a simple POS tag such as ''_{N}''?\n";
  }
  elsif ($input =~ s/(?<!\\)%$//) {
    return $self->Call("simple_pos", $input);
  }
  else {
    return $self->Call("pos_tag", $input);
  }
}

=item C<pos_tag>

Translate wildcard pattern for part-of-speech tag into CQP constraint, using
the appropriate p-attribute for POS tags (given by the parameter
C<pos_attribute>).

=cut

sub pos_tag {
  my ($self, $tag) = @_;
  my $attr = $self->GetParam("pos_attribute")
    or die "no attribute defined for part-of-speech tags (internal error)\n";
  my $regexp = $self->Call("wildcard_pattern", $tag);
  return "$attr=$regexp";
}

=item C<simple_pos>

Translate simple part-of-speech tag into CQP constraint.  The specified tag is
looked up in the hash provided by the C<simple_pos> parameter, and replaced by
the regular expression listed there.  If the tag cannot be found, or if no simple
tags have been defined, a helpful error message is generated.

=cut

sub simple_pos {
  my ($self, $tag) = @_;
  my $attr = $self->GetParam("simple_pos_attribute") || $self->GetParam("pos_attribute")
    or die "no attribute defined for part-of-speech tags (internal error)\n";
  my $lookup = $self->GetParam("simple_pos");
  die "no simple part-of-speech tags are available for this corpus\n"
    unless ref($lookup) eq "HASH";
  my $regexp = $lookup->{$tag};
  if (not defined $regexp) {
    my @valid_tags = sort keys %$lookup;
    die "'' $tag '' is not a valid simple part-of-speech tag (available tags: '' @valid_tags '')\n";
  }
  return "$attr=\"$regexp\"";
}

=back


=head2 Wildcard Patterns

=over 4

=item C<wildcard_pattern>

Translate string containing wildcards into regular expression, which is
enclosed in double quotes so it can directly be interpolated into a CQP query.

Internally, the input string is split into wildcards and literal substrings,
which are then processed one item at a time with the C<wildcard_item>
rule.

=cut

sub wildcard_pattern {
  my ($self, $input) = @_;
  die "literal backslash ''\\\\'' is not allowed in wildcard pattern '' $input '')\n"
    if $input =~ /\\\\/; # / (temporary workaround: TextMate is confused by the regexp)
  die "wildcard pattern must not end in a backslash ('' $input '')\n"
    if $input =~ /\\$/;
  ## add whitespace around (unescaped) wildcard metacharacters
  $input =~ s/(?<!\\)([?*+\[,\]])/ $1 /g;
  $input =~ s/(\\[aAlLuUdDwW])/ $1 /g;
  ## trim whitespace, then split wildcard pattern on whitespace into items
  $input =~ s/^\s+//;
  $input =~ s/\s+$//;
  my @items = split " ", $input;
  die "empty wildcard pattern '' $_[1] '' is not allowed\n"
    unless @items > 0;
  my @regexp_comps = $self->Apply("wildcard_item", @items);
  return '"'.join("", @regexp_comps).'"'; # string must be double-quoted!
}

=item C<wildcard_item>

Process an item of a wildcard pattern, which is either some metacharacter
(handled directly) or a literal substring (delegated to the C<literal_string>
rule).  Proper nesting of alternatives is ensured using the shift-reduce
parsing mechanism (with B<BeginGroup> and B<EndGroup> calls).

=cut

## internal lookup table for wildcard substitutions
our %_wildcard_table = (
                        "?" => ".",
                        "*" => ".*",
                        "+" => ".+",
                        "\\a" => "\\pL",
                        "\\A" => "\\pL+",
                        "\\l" => "\\p{Ll}",
                        "\\L" => "\\p{Ll}+",
                        "\\u" => "\\p{Lu}",
                        "\\U" => "\\p{Lu}+",
                        "\\d" => "\\pN",
                        "\\D" => "\\pN+",
                        "\\w" => "[\\pL\\pN'-]",
                        "\\W" => "[\\pL\\pN'-]+",
                      );

sub wildcard_item {
  my ($self, $item) = @_;
  if (exists $_wildcard_table{$item}) {
    return $_wildcard_table{$item};
  }
  elsif ($item eq "[") {
    $self->BeginGroup("[...]"); # group names make error messages more meaningful
    return "";
  }
  elsif ($item eq ",") {
    die "alternatives separator ('','') may only be used within brackets ''[ .. ]''\n"
      unless $self->NestingLevel > 0;
    return "|";
  }
  elsif ($item eq "]") {
    my @parts = $self->EndGroup("[...]");
    my ($has_empty_alternative, @filtered_parts) = $self->_remove_empty_alternatives(@parts);
    die "empty list of alternatives not allowed in wildcard pattern\n"
      unless @filtered_parts > 0;
    my $group = "(".join("", @filtered_parts).")";
    return(($has_empty_alternative) ? "$group?" : $group);
  }
  else {
    return $self->Call("literal_string", $item);
  }
}

=item C<literal_string>

Translate literal string into regular expression, escaping all metacharacters
with backslashes (backslashes in the input string are removed first).

Note that escaping of C<^> and C<"> isn't fully reliable because CQP might
interpret the resulting escape sequences as latex-style accents if they are
followed by certain letters.  Future versions of CQP should provide a safer
escaping mechanism and/or allow interpretation of latex-style accents to be
turned off.

=cut

sub literal_string {
  my ($self, $input) = @_;
  $input =~ s/\\//g; # remove backslashes (used to escape CEQL metacharacters)
  $input =~ s/([.?*+|(){}\[\]\^\$])/\\$1/g; # " is safely escaped by new doubling syntax (v3.0.0)
  $input =~ s/"/""/g; # NB: works correctly only if string is double-quoted in CQP query
  return $input;
}

=back


=head2 Internal Subroutines

=over 4

=item (I<$has_empty_alt>, I<@tokens>) = I<$self>->B<_remove_empty_alternatives>(I<@tokens>);

This internal method identifies and removes empty alternatives from a
tokenised group of alternatives (I<@tokens>), with alternatives separated by
C<|> tokens.  In particular, leading and trailing separator tokens are removed,
and multiple consecutive separators are collapsed to a single C<|>.  The first
return value (I<$has_empty_alt>) indicates whether one or more empty
alternatives were found; it is followed by the sanitised list of tokens.

=cut

sub _remove_empty_alternatives {
  my ($self, @tokens) = @_;
  my $after_separator = 1;    # when this is TRUE, a "|" token introduces an empty alternative
  my $has_empty_alternative = 0;
  my @filtered_tokens = ();
    while (@tokens) {
      my $t = shift @tokens;
      my $keep = 1;
      if ($t eq "|") {
        # a trailing "|" token also introduces an empty alternative (checked here)
        if ($after_separator or @tokens == 0) {
          $has_empty_alternative = 1;
          $keep = 0;
        }
        $after_separator = 1;
      }
      else {
        $after_separator = 0;
      }
      push @filtered_tokens, $t
        if $keep;
    }
  return $has_empty_alternative, @filtered_tokens;
}

=back


=head1 COPYRIGHT

Copyright (C) 2005-2013 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut

1;
