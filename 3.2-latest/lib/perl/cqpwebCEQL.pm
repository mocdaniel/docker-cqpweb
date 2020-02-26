# CQPweb: a user-friendly interface to the IMS Corpus Query Processor
# Copyright (C) 2008-today Andrew Hardie and contributors
#
# See http://cwb.sourceforge.net/cqpweb.php
#
# This file is part of CQPweb.
# 
# CQPweb is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 
# CQPweb is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

## CQPweb extension of the CEQL grammar

use warnings;
use strict;

package cqpwebCEQL;
use base 'CWB::CEQL';

use Encode;
use HTML::Entities;

=head1 NAME

cqpwebCEQL - CQPweb extension of the Common Elementary Query Language (CEQL)

=head1 SYNOPSIS

  use cqpwebCEQL;
  our $CEQL = new cqpwebCEQL;

  $CEQL->SetParam("default_ignore_case", 0); # case-sensitive query mode
  
  # You must tell CEQL what the CWB attribute-names of your annotations are for the 
  # relevant queries to work. If any of the following are left undef, those bits of
  # CEQL syntax will cause an error.
  
  $CEQL->SetParam("pos_attribute", "PRIMARY_ANNOTATION");            # _XXX
  $CEQL->SetParam("lemma_attribute", "SECONDARY_ANNOTATION");        # {XXX}
  $CEQL->SetParam("simple_pos_attribute", "TERTIARY_ANNOTATION");    # _{XXX}
  # to use a tertiary annotation you also require a mapping table
  $CEQL->SetParam("simple_pos", HASH_TABLE_OF_ALIASES_TO_REGEX);
  $CEQL->SetParam("combo_attribute", "COMBO_ANNOTATION");            # {XXX/YYY}
  
  # You can also set a list of XML elements allowed within queries
  $self->SetParam("s_attributes", HASH_TABLE_OF_S_ATTRIBUTES);
  
  # As CQPweb does not currently have terribly good XML support, a default
  # "allow only s" option is preset. Future versions of cqpwebCEQL may remove this. 

  # $ceql_query must be in utf-8
  $cqp_query = $CEQL->Parse($ceql_query);    # returns CQP query

  if (not defined $cqp_query) {
    $html_msg = $CEQL->HtmlErrorMessage;     # ready-made HTML error message
    print "<html><body>$html_msg</body></html>\n";
    exit 0;
  }

=cut

# constructor: the CQPweb version of this sets up UNDEFINED parameters.
# Everything therefore MUST be set by the calling function. Or it won't work.
# Exception: ignore_case is set off, and ignore_diac is set off.
# Exception: <s> is allowed. If anything else is allowed, reset this parameter.
sub new {
  my $class = shift;
  my $self = new CWB::CEQL;


  $self->NewParam("combo_attribute", undef);


  $self->SetParam("pos_attribute", undef);
  $self->SetParam("lemma_attribute", undef);
  $self->SetParam("simple_pos", undef);
  $self->SetParam("simple_pos_attribute", undef);
  $self->SetParam("s_attributes", { "s" => 1 });
  $self->SetParam("default_ignore_case", 0);
  $self->SetParam("default_ignore_diac", 0);

  return bless($self, $class);
}



# override lemma_pattern rule to provide support for {book/V} notation
sub lemma_pattern {
  my ($self, $lemma) = @_;
  
  # split lemma into headword pattern and optional simple POS constraint
  my ($hw, $tag, $extra) = split /(?<!\\)\//, $lemma;
  die "Only a single ''/'' separator is allowed between the first and second search terms in a {.../...} search.\n"
    if defined $extra;
  die "Missing first search term (nothing before the / in {.../...} ); did you mean ''_{$tag}''?\n"
    if $hw eq "";
    
  # translate wildcard pattern for headword and look up simple POS if specified
  my $regexp = $self->Call("wildcard_pattern", $hw);
  
  if (defined $tag) {
    # simple POS specified => look up in $simple_pos and combine with $regexp
    
    # before looking up the simple POS, we must check that the mapping table is defined
    my $simple_pos = $self->GetParam("simple_pos");
    die "Searches of the form _{...}  and {.../...} are not available.\n"
      unless ref($simple_pos) eq "HASH";
    
    my $tag_regexp = $simple_pos->{$tag};
    if (not defined $tag_regexp) {
      my @valid_tags = sort keys %$simple_pos;
      die "'' $tag '' is not a valid tag in this position (available tags: '' @valid_tags '')\n";
    }
    
    my $attr = $self->GetParam("combo_attribute");
    if (defined $attr) {
      # remove double quotes around regexp so it can be combined with POS constraint
      $regexp =~ s/^"//; 
      $regexp =~ s/"$//; 
      return "$attr=\"($regexp)_${tag_regexp}\"";
    }
    else {
      my $first_attr = $self->GetParam("lemma_attribute")
        or die "Searches of the form {.../...} are not available.\n";
      my $second_attr = $self->GetParam("simple_pos_attribute")
        or die "Searches of the form {.../...} are not available.\n";
      # return "($first_attr=$regexp & $second_attr=\"${tag_regexp}\")";
      return "$second_attr=\"${tag_regexp}\" & $first_attr=$regexp";
      # Note here: we are using a loophole in the CEQL internals.
      # the  caller here adds a '%c' after the return value of this function.
      # For the fallback that needs to apply to the secondary but not the tertiary
      # (for consistencey with how they are treated elsewhere). So, the Secondary
      # constraint MUST be at the end, and we can't parenthesise the two constraints.
      # We know CEQL will not add further constraints, except perhaps a redundant
      # Primary annotation constraint with &, so the naked & here will still work OK.
    }
  }
  else {
    # no simple POS specified => match the normal lemma attribute.
    my $attr = $self->GetParam("lemma_attribute")
      or die "Searches of the form {...} are not available.\n";
    return "$attr=$regexp";
  }
}

=head1 COPYRIGHT

Copyright (C) 1999-2008 Stefan Evert [http::/purl.org/stefan.evert]
(modified by Andrew Hardie for CQPweb)

=cut

1;
