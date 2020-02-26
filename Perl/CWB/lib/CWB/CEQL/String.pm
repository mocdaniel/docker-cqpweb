package CWB::CEQL::String;

use warnings;
use strict;

use Carp;

use overload
  '""' => \&value,
  '.=' => \&append,
  '~' => \&type,
  'cmp' => \&cmp,
  ;

=head1 NAME

CWB::CEQL::String - Typed strings with annotations (return values of DPP rules)

=head1 SYNOPSIS

  use CWB::CEQL::String;

  $op = new CWB::CEQL::String ">=";
  $op->type("Operator");
  ## SAME AS: $op = new CWB::CEQL::String ">=", "Operator";

  print "42 $op 0\n"; # prints "42 >= 0"
  if ($op->type eq "Operator") { ... }

  $string = new CWB::CEQL::String "my string", "String";
  $string .= " is beautiful";       # changes string, but not its type
  $string->value("another string"); # $string = "..."; would replace with ordinary string
  print $string->value, "\n";       # access string value explicitly

  $string->attribute("charset", "ascii"); # declare and/or set user-defined attribute
  if ($string->attribute("charset") eq "utf8") { ... }

  $new_string = $string->copy;      # $new_string = $string; would point to same object


=head1 DESCRIPTION

This module implements a class of typed, string-like objects that are used as return values of DPP grammar rules (e.g. to distinguish between different categories of symbols (IDs, operators, etc.).

In appropriate contexts, a B<CWB::CEQL::String> object behaves like an ordinary string, whose type can be determined with the B<type> method.  Optional further annotations can be added and retrieved with the B<attribute> method.

B<Important note:> automatic conversion of B<CWB::CEQL::String> objects to a number in numerical context usually does not work.  Use the B<value> method explicitly in this case.


=head1 METHODS

=over 4

=item I<$obj> = B<new> CWB::CEQL::String I<$string> [, I<$type>];

Returns new C<CWB::CEQL::String> object I<$obj> holding string value
I<$string>.  If I<$type> is given, I<$obj> is assigned to the specified type.

=cut

sub new {
  my ($class, $value, $type) = @_;
  my $self = {
              VALUE => $value,
              TYPE => $type,  # undef if not specified
              ATTRIBUTE => {},
             };
  return bless($self, $class);
}

=item I<$string> = I<$obj>->B<value>;

=item I<$string> = "I<$obj>";

Return string value of B<CWB::CEQL::String> object I<$obj>.  Overloading
ensures that this value is accessed automatically if I<$obj> is used in a
string context (such as interpolation).

=item I<$obj>->B<value>(I<$string>);

Change string value of I<$obj>.  Note that a simple assignment C<$obj =
$string> would overwrite I<$obj> with a plain string.

=cut

sub value {
  my ($self, $new_val) = @_;
  if (defined $new_val) {
    $self->{VALUE} = $new_val;
  }
  return $self->{VALUE};
}

=item I<$obj>->B<append>(I<$string>);

=item I<$obj> .= I<$string>;

Append I<$string> to string value of I<$obj>.

=cut

sub append {
  my ($self, $value) = @_;
  croak 'Usage:  $obj->append($string);'
    unless defined $value;
  $self->{VALUE} .= $value;
  return $self; # indicates to overloaded operator that object has been modified in place
}

=item I<$obj>->B<type>(I<$type>);

Set or change type of I<$obj> (returns previous value).

=item I<$type> = I<$obj>->B<type>;

=item I<$type> = ~I<$obj>;

Return type of the B<CWB::CEQL::String> object.  The returned value may be B<undef> if
I<$obj> hasn't been assigned to a type.

=cut

sub type {
  my ($self, $new_type) = @_;
  if (defined $new_type) {
    $self->{TYPE} = $new_type;
  }
  return $self->{TYPE};
}

=item I<$obj>->B<attribute>(I<$name>, I<$value>);

Define new user attribute I<$name> with value I<$value>, or change value
of existing attribute.

=item I<$value> = I<$obj>->B<attribute>(I<$name>);

Returns value of user attribute I<$name>.  It is an error to read an attribute
that has not been defined before.

=cut

sub attribute {
  my ($self, $name, $new_val) = @_;
  if (defined $new_val) {
    $self->{ATTRIBUTE}->{$name} = $new_val;
  }
  else {
    croak "CWB::CEQL::String: user attribute '$name' has not been defined"
      unless exists $self->{ATTRIBUTE}->{$name};
  }
  return $self->{ATTRIBUTE}->{$name};
}

=item I<$new_obj> = I<$obj>->B<copy>;

Returns a copy of the B<CWB::CEQL::String> object I<$obj>.  Note that after a
simple assignment C<$new_obj = $obj>, the two variables would contain the same
object (so changing one of them would also modify the other).

The B<copy> method makes a flat copy of the internal hash of user attributes.
Therefore, complex data structures used as attribute values will be shared
between I<$new_obj> and I<$obj>.

=cut

sub copy {
  my $self = shift;
  my $new_self = {
                  VALUE => $self->{VALUE},
                  TYPE => $self->{TYPE},
                  ATTRIBUTE => { %{$self->{ATTRIBUTE}} },
                 };
  return bless($new_self, ref $self);
}

=item I<$result> = I<$obj>->B<cmp>(I<$obj2> [, I<$reverse>]);

The B<cmp> method implements string comparison operators for
B<CWB::CEQL::String> objects.  The second operand I<$obj2> must either be a
plain string or another B<CWB::CEQL::String> object.  If the optional argument
I<$reverse> is TRUE, the comparison is reversed (so a string as first operand
can be compared with a B<CWB::CEQL::String> object).

=cut

sub cmp {
  my ($self, $other, $reverse) = @_;
  my $type = ref $other;
  my $other_value = (ref($other) eq "CWB::CEQL::String") ? $other->value : "$other";
  my $result = $self->value cmp $other_value;
  return(($reverse) ? -$result : $result);
}

=back


=head1 COPYRIGHT

Copyright (C) 2005-2013 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut

1;
