package CWB::Web::Query;
## -*-cperl-*-

use Carp;
use CWB::CQP;
use CWB::CL;
use HTML::Entities;


# global registry setting for new CWB::Web::Query objects
# changing this will set the registry for all objects created afterwards
$Registry = "";

# error handler: print to STDERR and exit :or: user-defined callback
sub error {
  my $self = shift;
  if (defined $self->{'error_handler'}) {
    &{$self->{'error_handler'}}(@_);
    exit 1;                     # user-defined error handler should exit
  }
  else {
    print STDERR "\nCWB::Web::Query Error:\n";
    grep { chomp; print STDERR "\t$_\n"; } @_;
    exit 1;
  }
}

# execute CQP command with error checking & abort on error (used internally)
# usage: $self->exec("HGC;", "Can't activate corpus:");
sub exec {
  my $self = shift;
  my $cmd = shift;
  my $error = shift;
  my $cqp = $self->{'cqp'};
  $cqp->exec($cmd);
  $self->error("$error because:", $cqp->error_message)
    unless $cqp->ok;
}

# CWB::Web::Query object struct:
# {
#   'cqp'        => CWB::CQP object
#   'error_handler' => user-defined error handler (standard fallback if undefined)
#   'corpus'     => name of query corpus
#   'ch'         => CWB::CL::Corpus object
#   'attributes' => list of attributes to show
#   'aligned'    => list of aligned corpora to show
#   'structures' => s-attributes whose values will be returned (separately) 
#                   {
#                     <attribute> => CL::Attribute object,
#                     ...
#                   }
# }
# object constructor: spawns CQP server & initialises variables
sub new {
  croak "Usage: \$q = new CWB::Web::Query 'HGC';"
    unless @_ == 2;
  my $class = shift;
  my $corpus = uc shift;
  my $self = {};
  bless($self, $class);         # so we can use object methods for initialisation
  $self->{'error_handler'} = undef;
  # spawn CQP server process
  my $cqp = new CWB::CQP;            
  $self->error("Can't spawn CQP server process.")
    unless defined $cqp;
  $cqp->set_error_handler('ignore'); # ignore CQP error messages (handled by $self->exec())
  $self->{'cqp'} = $cqp;
  # init some variables to default values
  $self->{'cut'} = 0;              # 0 means: don't cut/reduce
  $self->{'reduce'} = 0;
  $self->{'attributes'} = ['word']; # CQP default setting
  $self->{'structures'} = {};
  $self->{'aligned'} = [];      
  # if CQP generated any error messages during startup, the first command executed
  # (i.e. the corpus activation) will fail; to avoid that, execute a dummy command
  # now, so the CQP module reads all pending error messages
  # [this is a hack for Verbmobil because they're having trouble with the new
  #  Apache HTTP server]
  $cqp->exec("show");
  # if $Registry is set, change corpus registry before we activate the query corpus
  if (defined $Registry and $Registry ne "") {
    $self->exec("set registry '$Registry';", "Can't change to user-defined registry");
    $CWB::CL::Registry = $Registry;  # we'll also access the corpus through the CL
  }
  # activate query corpus
  $self->exec("$corpus;", "Can't activate corpus $corpus");
  $self->{'corpus'} = $corpus;
  my $ch = new CWB::CL::Corpus $corpus; # get CL corpus handle
  $self->error("Can't open corpus $corpus (CL)")
    unless defined $ch;
  $self->{'ch'} = $ch;
  return $self;
}

# object destructor: undef backends
sub DESTROY {
  my $self = shift;
  delete $self->{'cqp'};
  delete $self->{'structures'}; # undef's attribute handles
  delete $self->{'ch'};
}

# error handler
sub on_error {
  my $self = shift;
  my $handler = shift;
  croak 'Usage: $q->on_error(\&my_error_handler);'
    unless (not defined $handler or ref $handler eq 'CODE') and @_ == 0;
  $self->{'error_handler'} = $handler;
}

# CQP query settings
sub cut {
  my $self = shift;
  croak 'Usage: $q->cut($n);'
    unless @_ == 1;
  $self->{'cut'} = shift;
}

sub reduce {
  my $self = shift;
  croak 'Usage: $q->reduce($n);'
    unless @_ == 1;
  $self->{'reduce'} = shift;
}

# CQP output settings
sub context {
  my $self = shift;
  croak 'Usage: $q->context($left, $right);'
    unless @_ == 2;
  my ($lc, $rc) = @_;
  $self->exec("set LeftContext $lc;", "Can't set left context to '$lc'");
  $self->exec("set RightContext $rc;", "Can't set right context to '$rc'");
}

sub attributes {
  my $self = shift;
  croak 'Usage: $q->attributes("word", "pos", "s");'
    unless @_;
  # hide all previously shown attributes
  my @attributes = @{$self->{'attributes'}};
  foreach my $att (@attributes) {
    $self->exec("show -$att;", "Internal error");
  }
  # show requested attributes
  @attributes = @_;
  foreach $att (@attributes) {
    $self->exec("show +$att", "Can't show attribute '$att'");
  }
  $self->{'attributes'} = \@attributes;
}

sub alignments {
  my $self = shift;
  # unshow previous alignments
  my @aligned = @{$self->{'aligned'}};
  foreach my $att (@aligned) {
    $self->exec("show -$att", "Internal error");
  }
  # now show requested alignments
  @aligned = map lc, @_;
  foreach $att (@aligned) {
    $self->exec("show +$att", "No alignment to '$att' found");
  }
  $self->{'aligned'} = \@aligned;
}

# return ''structure values'' in 'data' field of $line struct
sub structures {
  my $self = shift;
  my %structures = ();
  foreach my $att (@_) {
    my $ah = $self->{'ch'}->attribute($att, 's');
    $self->error("Structural attribute '$att' not found in corpus ".$self->{'corpus'})
      unless defined $ah;
    $self->error("Structural attribute '$att' has no values!")
      unless $ah->struc_values;
    $structures{$att} = $ah;
  }
  delete $self->{'structures'};
  $self->{'structures'} = \%structures;
}


# execute query & post-process results; returns list of $line structs
sub query {
  croak 'Usage: $q->query($command);'
    unless @_ == 2;
  my $self = shift;
  my $query = shift;
  if ($self->{'cut'} > 0) {
    $query =~ s/;\s*$//;         # remove trailing ';' if present
    $query = "$query cut ".$self->{'cut'};
  }
  $self->exec("show +cpos");     # corpus position will be parsed
  $self->exec("set ld '-::-::- ';"); # use left/right match delimiter for splitting
  $self->exec("set rd ' -::-::-';");
  my $cqp = $self->{'cqp'};      # $query is assumed to be tainted, so we use the CQP->query() method
  $cqp->exec_query($query);
  $self->error("Query execution failed because:", $cqp->error_message)
    unless $cqp->ok;
  my ($size) = $cqp->exec("size Last");
  $self->error($cqp->error_message)
    unless $cqp->ok;
  if ($size == 0) {
    return ();                  # query produced no results
  }
  else {
    $cqp->exec("reduce Last to ".$self->{'reduce'})
      if $self->{'reduce'} > 0;
    my @kwic = $cqp->exec("cat Last");
    my @lines = ();             # collect result lines here
    while (@kwic) {
      my $kwic = shift @kwic;   # kwic line currently being processed
      $kwic =~ /^\s*([0-9]+):/
        or $self->error("Can't parse CQP kwic output [step A], line:", $kwic); 
      my $cpos = $1; $kwic = $';
      my @parts = split /-::-::-/, $kwic, -1; # this should split line into left context, matcht, right context
      if (@parts != 3) {
        $self->error("Can't parse CQP kwic output [step B], line:", $kwic); 
      }
      my ($left, $match, $right) = @parts;
      map {s/^\s+//; s/\s+$//;} ($left, $match, $right);   # strip whitespace
      encode_entities($left);   # encode special chars as HTML entities
      encode_entities($match);
      encode_entities($right);
      my $line = {};            # build result line structure
      $line->{'cpos'} = $cpos;
      $line->{'kwic'}->{'left'} = $left;
      $line->{'kwic'}->{'match'} = $match;
      $line->{'kwic'}->{'right'} = $right;
      foreach my $att (keys %{$self->{'structures'}}) {
        my $value = $self->{'structures'}->{$att}->cpos2struc2str($cpos);
        if (defined $value) {
          encode_entities($value);
          $line->{'data'}->{$att} = $value;
        }
        else {
          $line->{'data'}->{$att} = "";
        }
      }
      # read alignment lines; $att is a dummy since CQP might provide aligned lines in an order
      # different from that specified in the 'aligned' field -- but we need to know how many
      # alignment lines to expect
      foreach $att (@{$self->{'aligned'}}) {
        my $kwic = shift @kwic;
        $self->error("Can't parse CQP kwic output [alignment], line:", "$kwic")
          unless $kwic =~ /^-->(.*?):/;
        my $aligned_corpus = $1;
        $kwic = $';
        $kwic =~ s/^\s+//; $kwic =~ s/\s+$//; # strip whitespace
        encode_entities($kwic);
        $line->{$aligned_corpus} = $kwic;
      }
      push @lines, $line;
    }

    return @lines;
  }
}

return 1;

__END__


=head1 NAME

  CWB::Web::Query - A simple CQP front-end for CGI scripts

=head1 SYNOPSIS

  use CWB::Web::Query;

  # typically, a query object is used for a single query only
  $query = new CWB::Web::Query 'HANSARD-E';

  # install HTML-producing error handler
  $query->on_error(sub{grep {print "<h2>$_</h2>\n"} @_});

  # result output settings
  $query->context('1 s', '1 s');    # left & right context
  $query->attributes('word', 'pos', 's'); # show which attributes
  $query->alignments('hansard-f');  # aligned corpora to show
  $query->structures('sitting');    # return annotated values of regions
  $query->reduce(10);               # return at most 10 matches

  # run query - returns list of result structs
  @matches = $query->query("[pos='JJ'] [pos='NN' & lemma='dog']");
  $nr_matches = @matches;

  # typical result processing loop
  for ($i = 0; $i < $nr_matches; $i++) {
    $nr = $i + 1;               # match number
    $m = $matches[$i];          # result struct
    $m->{'cpos'};               # corpus position of match
    $m->{'kwic'}->{'left'};     # left context (HTML encoded)
    $m->{'kwic'}->{'match'};    # match        ( ~      ~   )
    $m->{'kwic'}->{'right'};    # right context( ~      ~   )
    $m->{'hansard-f'};          # aligned region
    $m->{'data'}->{'sitting'};  # annotation of structural region
  }

  # closes down CQP server & deallocates memory
  undef $query;


=head1 DESCRIPTION

The I<CWB::Web::Query> module is a simplified CQP front-end intended for
use in CGI scripts. Typically, a CGI script will create a
I<CWB::Web::Query> object for a single query. It is possible to reuse
query objects for further queries on the same corpus, though.

=head1 ERRORS

If the I<CWB::Web::Query> module encounters an error condition, an error
message is printed on C<STDERR> and the program is terminated. A user-defined
error handler can be installed with the I<on_error()> method. In this case,
the error callback function is passed the error message generated by the module
as a list of strings.

=head1 CORPUS REGISTRY

If you need to use a registry other than the default corpus registry,
set the variable

  $CWB::Web::Query::Registry = "/path/to/my/registry";

This will affect all new I<CWB::Web::Query> objects.

=head1 RESULT STRUCTURE

The query module's I<query()> method returns a list of I<result structs> 
corresponding to the matches of the query. A CGI script will usually
iterate through the list with a loop similar to this:

    @result_list = $query->query(...);
    foreach $m (@result_list) {
      # code for processing match data in result struct $m 
    }

A I<result struct> $m has the following fields:

=over 4

=item $m->{'cpos'}

I<Corpus position> of the first token in this match.

=item $m->{'kwic'}

I<Left context>, I<match>, and I<right context> are returned
in the subfields

   $m->{'kwic'}->{'left'}
   $m->{'kwic'}->{'match'}
   $m->{'kwic'}->{'right'}

as HTML-encoded text. Neither the match nor I<keyword> or I<target> 
fields specified in the query are highlighted.

=item $m->{$aligned_corpus}

For each aligned corpus C<$aligned_corpus> passed to the I<alignments()> method,
the field C<$m->{$aligend_corpus}> contains the region aligned to the match
as HTML-encoded text.

=item $m->{'data'}

The annotated values of structural attributes specified with the 
I<structures()> method are returned in accordingly named subfields
of the 'data' field. The returned values are I<not> HTML-encoded.

=back


=head1 METHODS

=over 4

=item $query = new CWB::Web::Query $corpus;

Create I<CWB::Web::Query> object for CQP queries on corpus $corpus.

=item $query->on_error(\&error_handler);

Install error callback function. C<error_handler()> is a user-defined
subroutine, which will usually generate an HTML document from the
error message passed by the I<CWB::Web::Query> module. A typical error
callback might look like this:

    sub error_handler {
      my @msg = @_;  # @msg holds the lines of the error message
      print "<html><body><h1>ERROR</h1>\n";
      grep { print "$_<br>\n" } @msg;  # print @msg as individual lines
      print "</body></html>\n";
    } 

=item $query->context($left, $right);

Left and right context returned by the I<query()> method. $left and
$right are passed to CQP for processing and hence must be specified
in CQP format. Typical values are

    $query->context("10 words", "10 words");

for fixed number of tokens and

    $query->context("1 s", "1 s");

to retrieve entire sentences.

=item $query->attributes($att1, $att2, ...);

Select attributes to display. Can include I<both> positional and
structural attributes. 

=item $query->alignments($corpus, ...);

Specifiy one or more aligned corpora. Aligned regions in those 
corpora will be returned as HTML-encoded strings in the fields

     $m->{$corpus};
     ...

of a result struct $m.

=item $query->structures($att1, $att2, ...);

Specify structural attributes with annotated values. The annotated
value of the $att1 region containing the match will be returned
in 

    $m->{'data'}->{$att1}

as plain text for further processing etc. 

=item $query->reduce($n);

Return at most $n matches randomly selected from corpus (hence repeated
execution of the same query will produce different results). Deactivate
with

    $query->reduce(0);

This method uses CQP's I<reduce> command. 

=item $query->cut($n);

Similar to the I<reduce()> method, this returns the I<first> $n matches
found in the corpus. The I<cut()> method uses CQP's I<cut> operator and 
is faster on slow machines. However, I<reduce()> will usually yield more
balanced results. Sometimes a combination of both can be useful, such as

    $query->cut(1000);     # stop after first 1000 matches,
    $query->reduce(50);    # but return only 50 of them 


=item @results = $query->query($cqp_query);

Executes CQP query and returns a list of matches. See L<"RESULT STRUCTURE"> 
for the format of the @results list. 

=back


=head1 COPYRIGHT

Copyright (C) 1999-2010 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
