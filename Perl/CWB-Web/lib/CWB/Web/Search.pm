package CWB::Web::Search;
## -*-cperl-*-

use Carp;
use CWB::CL;
use HTML::Entities;


#
#  CWB::Web::Search::MatchList objects (used internally)
#

package CWB::Web::Search::MatchList;
use Carp;
use HTML::Entities;
use CWB::CL;

# CWB::Web::Search::MatchList object = reference to list of structs:
# $ml->[$i]->{'center'}     = center of match (mean of keywords)
# $ml->[$i]->{'variance'}   = variance of match (-> quality)
# $ml->[$i]->{'n'}          = number of keywords in this match
# $ml->[$i]->{'first'}      = first corpus position of minimal match
# $ml->[$i]->{'last'}       = last corpus position of minimal match
# $ml->[$i]->{'keyword'}->{$cpos}  = code of keyword at c.p. $cpos
# $ml->[$i]->{'quality'}    = relevance of match (must be explicitly recomputed)

sub new {
  my $class = shift;
  my $self = [];
  bless $self, $class;
  $self->add(@_);               # optional initialisation
  return $self;
}

# find insertion point by 'center' value (this _would_ be correct
# if the match list were ordered); returns index into list
sub find_point {
  my $self = shift;
  my $center = shift;           # center of element to insert
  my $i = shift;                # optional arg: start index
  $i = 0 unless defined $i;
  my $size = @$self;
  $i++
    while ($i < $size and $center > $self->[$i]->{'center'});
  $i--
    while ($i > 0 and $center < $self->[$i-1]->{'center'});
  return $i;
}

# add new matches to list, initialised to size 1, variance 0
# Usage: $self->add($keyword, @cpos);
sub add {
  my $self = shift;
  my $keyword = shift;          # code of keyword
  my $i = 0;
  foreach my $center (@_) {
    $i = $self->find_point($center, $i);        # should be fast if cpos list is ordered
    splice @$self, $i, 0, {
                           'center' => $center,
                           'variance' => 0,
                           'n' => 1,
                           'first' => $center,
                           'last' => $center,
                           'keyword' => { $center => $keyword },
                           'quality' => 0,
                           };
  }
}

# returns matchlist size
sub size {
  my $self = shift;
  return scalar @$self;
}

# retrieve single match: returns
# (first, last, center, variance, quality, %keyword [as cpos => keycode pairs])
sub match {
  my $self = shift;
  my $n = shift;
  my $m = $self->[$n];
  return ($m->{'first'}, $m->{'last'}, $m->{'center'}, $m->{'variance'}, $m->{'quality'}, %{$m->{'keyword'}});
}

# summarize (single) match  (maximal length defaults to 160 words)
# Usage: $summary = $ml->summarize($n, $word_att, $start_tag, $end_tag [, $maximal_length]);
sub summarize {
  my $self = shift;
  my $n = shift;
  my $word_att = shift;
  my $start_tag = shift;
  my $end_tag = shift;
  my $maximal_length = shift;
  $maximal_length = 160 unless defined $maximal_length;
  $maximal_length = int ($maximal_length / 2) + 1; # summary is symmetric to center of match
  my $match = $self->[$n];
  my $length = int(sqrt($match->{'variance'})*2 + 15); # default summary extends 2 * s.d. + 15 tokens to each side from center
  $length = $maximal_length if $length > $maximal_length;
  $length = 5 if $length < 5;   # minimum size is 11 words
  my @words = ();               # summary as word list
  # summary must be contained in context, i.e. use this matches 'first' and 'last' as hard boundaries
  my $first = $match->{'center'} - $length;
  my $pre_ell = "... ";         # ellipsis before summary if not start of document
  if ($first < $match->{'first'}) {
    $first = $match->{'first'};
    $pre_ell = "";
  }
  my $last = $match->{'center'} + $length;
  my $post_ell = " ...";        # ellipsis after summary if not end of document
  if ($last > $match->{'last'}) {
    $last = $match->{'last'};
    $post_ell = "";
  }

  foreach my $cpos ($first .. $last) {
    my $token = encode_entities($word_att->cpos2str($cpos));
    $token = $start_tag.$token.$end_tag
      if (defined $match->{'keyword'}->{$cpos});
    push @words, $token;
  }
  return $pre_ell."@words".$post_ell;
}

# (re-)compute quality values of matches
# Usage: $ml->compute_quality($no_of_keywords [, @preferred ]);
sub compute_quality {
  my $self = shift;
  my $no_of_keywords = shift;
  my @preferred = @_;           # list of s-attribute handles for preferred regions -> higher quality score
  # each keyword present in the match scores 1 point; proximity of the
  # keywords scores up to $proximity_points points (scaled by standard deviation)
  my $proximity_points =                       # we'll probably want to make that configurable
    ($no_of_keywords > 2) ? 2 : 1;
  my $preferred_points = (@preferred) ?        # ... and that as well
    (($no_of_keywords > 3) ? 2 : 1)   : 0; 
  my $total_points = ($no_of_keywords - 1) + $proximity_points + $preferred_points;
  
  foreach my $match (@$self) {
    my $variance = $match->{'variance'};
    $variance = 4 if $variance < 4; # scale proximity score to 0 .. $proximity_points range
    my $sd = sqrt($variance/4);
    my $cnt_preferred = 0; # a match gets higher score, if one or more of its keywords fall into preferred environments
    foreach my $keyword (keys %{$match->{'keyword'}}) {
      $cnt_preferred++ 
        if grep { defined $_->cpos2struc($keyword) } @preferred;
    }
    my $quality = 
      (($match->{'n'} - 1) + ($proximity_points / $sd) + ($preferred_points * $cnt_preferred / $no_of_keywords)) 
       / $total_points;
    $match->{'quality'} = int ($quality*100 + 0.5); # rounded percentage
  }
}

# sort matchlist by one the fields
#   'first'  ...  sort by first position in match
#   'quality' ... sort by quality values (make sure you've called compute_quality() beforehand!)
sub sort {
  my $self = shift;
  my $method = shift;
  my %sort = (
              'first' => sub {return sort {$a->{'first'} <=> $b->{'first'}} @_},
              'quality' => sub {return sort {$b->{'quality'} <=> $a->{'quality'}} @_},
              );
  croak "Usage:  \$ml->sort('".join("' | '", keys %sort)."');"
    unless defined $sort{$method};
  @$self = &{$sort{$method}}(@$self);
}

# remove overlapping matches ("cull" the one with highest quality rating)
# NB don't forget to $ml->compute_quality; $ml->sort('first') before!
sub cull {
  my $self = shift;
 I_LOOP:
  for (my $i = 0; $i < @$self; $i++) {
    my $i_first = $self->[$i]->{'first'};
    my $i_last = $self->[$i]->{'last'};
  J_LOOP:
    for (my $j = $i+1; $j < @$self; $j++) {
      last unless $j < @$self;  # check again for 'redo'
      my $j_first = $self->[$j]->{'first'};
      my $j_last = $self->[$j]->{'last'};
      # compare i-th and j-th element of match list
      last J_LOOP # assume ml is ordered by 'first' -> no more overlaps possible after this point
        if $j_first > $i_last; 
      if ($i_last >= $j_first) {
        if ($self->[$i]->{'quality'} >= $self->[$j]->{'quality'}) {
          # i-th match is better -> keep it, delete j-th match
          splice @$self, $j, 1;
          redo J_LOOP;          # must re-consider j-th match
        }
        else {
          # j-th match is better -> delete i-th match and restart outer loop
          splice @$self, $i, 1;
          redo I_LOOP;
        }
      }
    }
  }
  # meself had better be culled nicely now ...
}


# **
# cleanup: to be done
# find overlapping matches & select the one with higher quality
# **


# expand to desired context setting
# template for the context_expansion_func() function:
#    ($first, $last) = context_expansion_func($first, $last);
sub expand {
  my $self = shift;
  my $context_expansion = shift;
  croak 'Usage: $ml->expand(\&context_expansion_func);'
    unless defined $context_expansion and ref $context_expansion eq 'CODE';

  foreach my $match (@$self) {
    ($match->{'first'}, $match->{'last'}) = 
      &{$context_expansion}($match->{'first'}, $match->{'last'});
  }
}

# this is the big one: check positive/negative constraint on matches; or join keyword list
# template for the check_match_size() function:
#    $ok = check_match_size($first, $last);
sub constraint {
  my $self = shift;
  my $mode = lc shift;          # mode is positive, negative, or join
  croak "Usage: \$ml->constraint('positive'|'negative'|'optional'|'join', \$keyword, \\&check_match_size, \@cpos_list);"
    unless $mode =~ /^((posi|nega)tive|join|optional)$/ and @_ >= 2;
  my $keyword = shift;          # code of keyword (unused for negative constraint)
  my $check_match_size = shift; # pointer to function used for checking match size
  # @_ now contains the list of corpus positions for the constraint

  my %used = ();                # in join mode, remember which positions from list have been used
  
  # apply constraint to all matches in list
  my ($i, $j) = (0, 0);
  while ($i < @$self) {         # list size may changed (negative constraint)

    my $match = $self->[$i];
    $j = find_closest(\@_, $match->{'center'}, $j);
    # $first .. $last is minimal match region with the new keyword included
    my $ok = (defined $j) ? 1 : 0; # if @_ == 0, can't find closest -> not ok -> proceed as if check_match_size() had returned false
    my ($new_position, $first, $last);
    if ($ok) {
      $new_position = $_[$j];
      ($first, $last) = new_boundaries($match->{'first'}, $match->{'last'}, $new_position);
      $ok = &{$check_match_size}($first, $last);
    }
    if ($ok) {
      if ($mode eq 'negative') {
        # negative constraint -> remove match if keyword is within range
        splice @$self, $i, 1;
        next;                   # don't increment $i when we've removed a match
      }
      else {
        # positive constraint / join -> adjoin new keyword to match
        $match->{'first'} = $first;
        $match->{'last'} = $last;
        my $n = $match->{'n'}; # adjust center and variance values
        my $center = $match->{'center'};
        my $variance = $match->{'variance'};
        # I believe that the equations below are correct ... ;o)
        $match->{'center'} = ($n * $center + $new_position) / ($n+1);
        $match->{'variance'} = ($n/($n+1)) * 
          ($variance + (1/($n+1)) * ($new_position - $center)**2);
        $match->{'n'} = $n + 1;
        $match->{'keyword'}->{$new_position} = $keyword;

        # in join mode, don't add keyword later if it was 'used up' now
        $used{$new_position} = 1;
      }
    }
    else {
      # no keyword within range -> remove match if positive constraint
      if ($mode eq 'positive') {
        splice @$self, $i, 1;
        next;                   # don't increment $i after removing match
      }
    }
    
    $i++;                       # go to next match in list
  }
  
  # in join mode, add unused keywords as new matches to list 
  if ($mode eq 'join') {
    foreach my $cpos (grep {not defined $used{$_}} @_) {
      $self->add($keyword, $cpos) # check match size (e.g. 'header') for new single-keyword matches
        if &{$check_match_size}($cpos, $cpos);
    }
  }
}

# internal function: modify boundaries so that $pos is included in $start .. $end interval
# Usage: ($start, $end) = new_boundaries($start, $end, $pos);
sub new_boundaries {
  my ($start, $end, $pos) = @_;
  $start = ($pos < $start) ? $pos : $start;
  $end = ($pos > $end) ? $pos : $end;
  return ($start, $end);
}


# internal function: find element in sorted list closest to given value; returns index
# Usage: $index = find_closest(\@list, $val [, $index]);  
sub find_closest {
  my $listref = shift;          # reference to list
  my $value = shift;            # comparison value
  my $i = shift;                # optional: start index (may speed up search)
  $i = 0 unless defined $i;
  my $size = @$listref;
  $i = $size - 1 if $i >= $size;
  return undef if $size == 0;   # can't find anything in empty list
  while (1) {
    if ($i > 0 and $i < $size 
        and abs($listref->[$i-1] - $value) < abs($listref->[$i] - $value)) {
      $i--;
    }
    elsif ($i < ($size - 1) 
           and abs($listref->[$i+1] - $value) < abs($listref->[$i] - $value)) {
      $i++;
    }
    else {
      last;                     # found local minimum
    }
  }
  return $i;
}

# debugging ...
sub print {
  my $self = shift;
  my $match;
  for (my $i = 0; $i < @$self; $i++) {
    $match = $self->[$i];
    print "MatchList entry #",($i+1),"   [",$match->{'n'}," keywords]\n";
    print "  center:   ",(int $match->{'center'}),"\n";
    print "  variance: ",(int $match->{'variance'}),"\n";
    print "  first:    ",$match->{'first'},"\n";
    print "  last:     ",$match->{'last'},"\n";
    my @keys = map {$_."(".$match->{'keyword'}->{$_}.")"} sort keys %{$match->{'keyword'}};
    print "  keys:     @keys\n";
    print "  quality:  ",$match->{'quality'},"%\n";
  }
}



#
#  the main CWB::Web::Search package
#
package CWB::Web::Search;

# error handler: print to STDERR and exit :or: user-defined callback
sub error {
  my $self = shift;
  if (defined $self->{'error_handler'}) {
    &{$self->{'error_handler'}}(@_);
    exit 1;                     # user-defined error handler should exit
  }
  else {
    print STDERR "\nCWB::Web::Search Error:\n";
    grep { chomp(my $l=$_); print STDERR "\t$l\n"; } @_;
    exit 1;
  }
}


# CWB::Web::Search object struct:
# {
#   'error_handler' => user-defined error handler (standard fallback if undefined)
#   'corpus'     => name of query corpus
#   'ch'         => CWB::CL::Corpus object
#   'word'       => CWB::CL::Attribute object (word attribute)
#   'lemma'      => CWB::CL::Attribute object (lemma attribute)
#   'check_size' => reference to subroutine 
#                   that checks whether all keywords fall within specified range
#   'expand'     => reference to subroutine
#                   that expands match to full context (textual representation/HTML)
#                   with keywords highlighted
#   'cull'       => remove overlapping matches 'never', 'before' or 'after' context expansion
#   'hlstart'    => HTML tag (with attributes) used for keyword highlighting (default: <B>)
#   'hlend'      => closing tag for keyword highlighting (default: </B>)
#   'data'       => s-attributes whose values will be returned (separately) 
#                   {
#                     <attribute> => CL::Attribute object,
#                     ...
#                   }
#   'ignore_case' => 'always', 'auto', 'never'  (ignore case)
#   'ignore_diac' => 'always', 'auto', 'never'  (ignore diacritics)
#   'preferred'  => [ CL::Attribute object, ... ]  (list of preferred environments,
#                   such as <title> or <header>, which increase match relevance)
#   'ml'         => CWB::Web::Search::MatchList object
# }
# object constructor:  opens CL corpus/attributes & initialises variables
sub new {
  croak "Usage: \$s = new CWB::Web::Search 'HGC';"
    unless @_ == 2;
  my $class = shift;
  my $corpus = lc shift;
  my $self = {};
  bless($self, $class);         # so we can use object methods for initialisation
  $self->{'error_handler'} = undef;
  # open specified corpus
  my $ch = new CWB::CL::Corpus $corpus;              
  $self->error("Can't open corpus '$corpus'.")
    unless defined $ch;
  $self->{'ch'} = $ch;
  # open word & lemma attributes
  my $word = $ch->attribute("word", 'p');
  $self->error("Can't access 'word' attribute of corpus '$corpus'.")
    unless defined $word;
  $self->{'word'} = $word;
  my $lemma = $ch->attribute("lemma", 'p');
  $self->error("Can't access 'lemma' attribute of corpus '$corpus'.")
    unless defined $lemma;
  $self->{'lemma'} = $lemma;
  # init some variables to default values
  $self->{'check_size'} = $self->make_check_size_sub("30 tokens");
  $self->{'expand'} = $self->make_expand_sub("50 tokens");
  $self->{'data'} = {};         # by default, no data is returned
  $self->{'ignore_case'} = 'auto'; # ignore case/diacritics set to automatic by default
  $self->{'ignore_diac'} = 'auto';
  $self->{'ml'} = new CWB::Web::Search::MatchList; # empty match list
  $self->{'cull'} = 'never';    # don't cull() by default
  $self->highlight("<B>");      # default highlighting is <B> .. </B>
  $self->preferred();           # preferred environments depend on specific corpus

  return $self;
}

# object destructor: undef backends
sub DESTROY {
  my $self = shift;
  delete $self->{'data'};       # undef's attribute handles
  delete $self->{'word'};
  delete $self->{'lemma'};
  delete $self->{'check_size'};
  delete $self->{'expand'};
  delete $self->{'ch'};
  delete $self->{'ml'};
}

# error handler
sub on_error {
  my $self = shift;
  my $handler = shift;
  croak 'Usage: $s->on_error(\&my_error_handler);'
    unless (not defined $handler or ref $handler eq 'CODE') and @_ == 0;
  $self->{'error_handler'} = $handler;
}

# create match size checking subroutine
# Usage:  $coderef = make_check_size_sub("2 s");
#         ok() if &$coderef($first, $last); 
sub make_check_size_sub {
  my ($self, $context) = @_;
  if ($context =~ /^\s*([0-9]+)\s*(words?|tokens?)?\s*$/) {
    my $words = $1;
    return sub {
      my ($first, $last) = @_;
      return ($last-$first) <= $words;
    }
  }
  elsif ($context =~ /^\s*([0-9]*)\s*([A-Za-z\xc0-\xff_-]+)\s*$/) {
    my ($nr, $env) = ($1, $2);  # size is $nr <$env>...</$env> regions
    $nr = 1 if $nr eq "";       # "s" means "1 s"
    my $att = $self->{'ch'}->attribute($env, 's');
    $self->error("Invalid match size '$context'.", "(no <$env> regions in corpus)")
      unless defined $att;
    return sub {
      my ($first, $last) = $att->cpos2struc(@_);
      return (defined $first and defined $last and ($last-$first) < $nr);
    }
  }
  else {
    $self->error("Invalid match size '$context'.");
  }
}

# create context expansion subroutine
# Usage:  $coderef = make_expand_sub("s");
#         ($from, $to) = &$coderef($first, $last);
sub make_expand_sub {
  my ($self, $context) = @_;
  if ($context =~ /^\s*([0-9]+)\s*(words?|tokens?)?\s*$/) {
    my $words = $1;
    my $size = $self->{'word'}->max_cpos;
    return sub {
      my ($first, $last) = @_;
      $first -= $words; $last += $words;
      $first = 0 if $first < 0;
      $last = $size - 1 if $last >= $size;
      return ($first, $last);
    }
  }
  elsif ($context =~ /^\s*([0-9]*)\s*([A-Za-z\xc0-\xff_-]+)\s*$/) {
    my ($nr, $env) = ($1, $2);  # size is $nr <$env>...</$env> regions
    $nr = 1 if $nr eq "";       # "s" means "1 s"
    my $att = $self->{'ch'}->attribute($env, 's');
    my $size = $att->max_struc;
    $self->error("Invalid context size '$context'.", "(no <$env> regions in corpus)")
      unless defined $att;
    return sub {
      my ($first, $last) = @_;
      my $dummy;
      my $struc = $att->cpos2struc($first);
      if (defined $struc) {
        $struc -= $nr - 1;
        $struc = 0 if $struc < 0;
        ($first, $dummy) = $att->struc2cpos($struc);
      }
      $struc = $att->cpos2struc($last);
      if (defined $struc) {
        $struc += $nr - 1;
        $struc = $size - 1 if $struc >= $size;
        ($dummy, $last) = $att->struc2cpos($struc); 
      }
      return ($first, $last);
    }
  }
  else {
    $self->error("Invalid context size '$context'.");
  }
}

# search engine settings: search window
sub window {
  my $self = shift;
  croak 'Usage: $s->window($window_size);'
    unless @_ == 1;
  my $window = shift;
  $self->{'check_size'} = $self->make_check_size_sub($window);
}

# search engine settings: context returned
sub context {
  my $self = shift;
  croak 'Usage: $s->context($context_size);'
    unless @_ == 1;
  my $context = shift;
  $self->{'expand'} = $self->make_expand_sub($context);
}

# regex flags settings: ignore case / ignore diacritics
sub ignore_case {
  my $self = shift;
  my $val = lc shift;
  if ($val =~ /^[01]$/) {
    carp "Usage is now: \$s->ignore_case('always'|'auto'|'never');";
    $val = ($val) ? 'always' : 'never';
  }
  croak "Usage: \$s->ignore_case('always'|'auto'|'never');"
    unless $val =~ /^(always|auto|never)$/;
  $self->{'ignore_case'} = $val;
}

sub ignore_diacritics {
  my $self = shift;
  my $val = lc shift;
  if ($val =~ /^[01]$/) {
    carp "Usage is now: \$s->ignore_diacritics('always'|'auto'|'never');";
    $val = ($val) ? 'always' : 'never';
  }
  croak "Usage: \$s->ignore_diacritics('always'|'auto'|'never');"
    unless $val =~ /^(always|auto|never)$/;
  $self->{'ignore_diac'} = $val;
}

# select culling strategy
sub cull {
  my $self = shift;
  my $method = shift;
  croak "Usage: \$search->cull('never' | 'before' | 'after');"
    unless defined $method and ($method = lc $method) =~ /^(never|before|after)$/;
  $self->{'cull'} = $method;
}

# set preferred environments
sub preferred {
  my $self = shift;
  my $ch = $self->{'ch'};
  my @env = @_;
  my @att = ();                 # compile list of s-attribute handles
  foreach my $env (@env) {
    croak "Usage: \$search->preferred(\$region_name, ... );"
      unless $env =~ /^[A-Za-z0-9_-]+$/;
    my $att = $ch->attribute($env, 's');
    $self->error("Preferred environment: no <$env>..</$env> regions in corpus.")
      unless defined $att;
    push @att, $att;
  }
  $self->{'preferred'} = [@att];
}

# HTML tag used for keyword highlighting (open tag, with optional attributes)
sub highlight {
  my $self = shift;
  my $tag = shift;
  croak 'Usage: $search->highlight($open_tag);'
    unless defined $tag and $tag =~ /^<([A-Za-z0-9_-]+)(\s.*>|>)$/;
  my $name = $1;
  $self->{'hlstart'} = $tag;
  $self->{'hlend'} = "</$name>";
}

# search engine settings: data (region values)
sub data {
  my $self = shift;
  croak 'Usage: $search->data($att1, ...);'
    unless @_ > 0;
  my %atts = ();                # will be stored in $self->{'data'} field
  foreach my $att (@_) {
    my $att_handle = $self->{'ch'}->attribute($att, 's');
    $self->error("Data attribute '$att' does not exist.")
      unless defined $att_handle;
    $self->error("Data attribute '$att' has no annotated values.")
      unless $att_handle->struc_values;
    $atts{$att} = $att_handle;
  }
  $self->{'data'} = \%atts;
}

# return number of matches found
sub size {
  my $self = shift;
  return $self->{'ml'}->size;
}

# return data for match (returns result struct)
sub match {
  my $self = shift;
  my $n = shift;
  my $flag = shift;
  croak "Usage: \$search->match(\$n [, 'context']);"
    if defined $flag and (lc $flag ne 'context');

  my $word_att = $self->{'word'};
  my $start_tag = $self->{'hlstart'};
  my $end_tag = $self->{'hlend'};
  my $ml = $self->{'ml'};
  my $size = $ml->size;
  return undef
    unless $n >= 0 and $n < $size;

  my $result = {};              # build result struct
  my ($first, $last, $center, $variance, $quality, %keyword) = $ml->match($n);
  $result->{'cpos'} = int ($center + 0.5);
  $result->{'quality'} = $quality;
  $result->{'summary'} = $ml->summarize($n, $word_att, $start_tag, $end_tag);
  if (defined $flag) {
    # build full match text (including context)
    my @words = ();
    foreach my $cpos ($first .. $last) {
      my $token = encode_entities($word_att->cpos2str($cpos));
      $token = $start_tag.$token.$end_tag
        if defined $keyword{$cpos};
      push @words, $token;
    }
    $result->{'context'} = "@words";
  }
  foreach my $att (keys %{$self->{'data'}}) {
    my $data = $self->{'data'}->{$att}->cpos2struc2str($center);
    $result->{'data'}->{$att} = (defined $data) ? $data : "";
  }

  return $result;
}

# front-end to the query function; parses query string entered by user
sub query_string {
  my $self = shift;
  croak "Usage \$search->query_string(\$string);"
    unless @_ == 1;
  my $query = shift;

  # double quotes ("...") mark multi-word keys (exact match)
  # -> convert spaces to underlines, so we don't split them when tokenising the string
  while ($query =~ /(^|[^\\])\"(.*?[^\\])\"/) {
    my ($pre, $multiword, $post) = ($`.$1, $2, $');
    #    print "SUBST '$query' -> ($pre | $multiword | $post)\n";
    # if either one of the quot
    $multiword =~ tr[ ][_];
    $query = $pre.$multiword.$post;
  }

  my @keywords = split " ", $query;
  
  # a single keyword is implicitly required -- make that explicit (because of the
  # special tricks for multi-word keys below)
  if (@keywords == 1 and $keywords[0] !~ /^[+-]/) {
    $keywords[0] = "+".$keywords[0];
  }

  # now convert _ to SPC in multi-word keys, with extra post-processing
  my @extra = ();
  foreach my $keyword (@keywords) {
    if ($keyword =~ /_/) {
      $keyword =~ tr[_][ ];
      my @components = split " ", $keyword;
      if ($keyword =~ /^\+/) {
        # only the last token in a multi-word key is added to the set of keywords
        # (for highlighting and match relevance); however, for required multi-word keys, 
        # we can add the other tokens as additional required keywords to the query
        pop @components;        # last token get's listed anyway
        push @keywords, map {(/^\+/) ? "$_" : "+$_"} @components; # add other tokens explicitly
      }
    }
  }

  # now we can run the query with the tokenised list of keywords
  return $self->query(@keywords);
}

# the search query function ... 
sub query {
  my $self = shift;
  $self->error("No search keywords specified.")
    unless @_;
  my @required_keywords = grep {/^\+/} @_; # +computer => required keyword
  my @excluded_keywords = grep {/^-/} @_;  # -windows => must _not_ be in match
  my @optional_keywords = grep {not /^[-+]/} @_; # the remaining keywords are optional
  map {s/^\+//;} @required_keywords; # remove '+' and '-' marks
  map {s/^-//;} @excluded_keywords;
  my $no_of_keywords = @required_keywords + @optional_keywords; # max. no of keywords in a match
  $self->error("Query containing only negative constraints not allowed.")
    unless $no_of_keywords > 0;
  
  # reset matchlist
  my $ml = $self->{'ml'} = new CWB::Web::Search::MatchList;
  my @list = ();                # list of current keyword's corpus positions
  my $keyword = "";             # keyword we're currently processing

  my $has_required_keywords = @required_keywords > 0;

  # start with required keywords (if there are any)
  if ($has_required_keywords) {
    $keyword = shift @required_keywords;
    $ml->constraint("join", $keyword, $self->{'check_size'},
                    $self->get_positions($keyword));    # initial matchlist (add to empty list)
    foreach $keyword (@required_keywords) {
      $ml->constraint('positive', $keyword, $self->{'check_size'}, 
                      $self->get_positions($keyword));
    }
  }
  # add optional keywords now
  foreach $keyword (@optional_keywords) {
    if ($has_required_keywords) {
      # if there were any required keywords, optional keywords can 
      # only be adjoined to the existing matches
      $ml->constraint('optional', $keyword, $self->{'check_size'}, 
                    $self->get_positions($keyword));
    }
    else {
      # otherwise, optional keywords may introduce new matches
      $ml->constraint('join', $keyword, $self->{'check_size'},
                      $self->get_positions($keyword));
    }
  }
  # finally, apply excluded keywords
  foreach $keyword (@excluded_keywords) {
    $ml->constraint('negative', $keyword, $self->{'check_size'}, 
                    $self->get_positions($keyword));
  }

  # now, compute quality, expand the match list & sort it
  $ml->compute_quality($no_of_keywords, @{$self->{'preferred'}});
  if ($self->{'cull'} eq 'before') {
    $ml->sort('first');
    $ml->cull;
  }
  $ml->expand($self->{'expand'});
  if ($self->{'cull'} eq 'after') {
    $ml->sort('first');
    $ml->cull;
  }
  $ml->sort('quality');

  # finally, return number of matches
  return $ml->size;
}

# internal method: get IDs (word OR lemma) matching given keyword
# returns ($attribute, @id_list)
sub get_IDs {
  my $self = shift;
  my $keyword = shift;
  
  my $word_att = $self->{'word'};
  my $lemma_att = $self->{'lemma'};
  my $flags = '';               # construct ignore case/diacritics flags for regex2id()
  if (($self->{'ignore_case'} eq 'always') or
      ($self->{'ignore_case'} eq 'auto' and $keyword !~ /[A-Z\xc0-\xd6\xd8-\xde]/)) {
    $flags .= 'c';
  }
  if (($self->{'ignore_diac'} eq 'always') or
      ($self->{'ignore_diac'} eq 'auto' and $keyword !~ /[\xc0-\xd6\xd8-\xf6\xf8-\xff]/)) {
    $flags .= 'd';
  }

  if (not $flags and $keyword =~ /^[A-za-z0-9\xc0-\xff_-]+$/) {
    # regular word -> look up in index (use lemma attribute if possible)
    my $lemma_id = $lemma_att->str2id($keyword);
    my $word_id = $word_att->str2id($keyword);
    my $lemma_freq = (defined $lemma_id) ? $lemma_att->id2freq($lemma_id) : 0;
    my $word_freq = (defined $word_id) ? $word_att->id2freq($word_id) : 0;
    if ($lemma_freq > $word_freq) {
      # user entered lemma form -> look up in lemma index
      return ($lemma_att, $lemma_id);
      # NB: $lemma_id must be defined because $lemma_freq > 0
    }
    else {
      if ($word_freq > 0) {
        # user entered an inflected form or incorrect lemma
        # the word form was found in the corpus, so let's try to lemmatise it
        my @cpos = $word_att->idlist2cpos($word_id);
        my %lemma_id = ();              # collect lemma ID's cooccuring with word form
        for (my $i = 0; $i < @cpos and $i < 100; $i++) { # try first 100 occurrences only
          $lemma_id{$lemma_att->cpos2id($cpos[$i])} = 1;
        }
        my @lemma_ids = grep {$lemma_att->id2str($_) ne "<unknown>"} keys %lemma_id;
        if (@lemma_ids) {
          # we've found one or more possible lemmatisations -> pick most frequent one
          ($lemma_id) = sort {$lemma_att->id2freq($b) <=> $lemma_att->id2freq($a)} @lemma_ids;
          $lemma_freq = $lemma_att->id2freq($lemma_id);
          # now check again if lemma is more 'general' than word form
          if ($lemma_freq > $word_freq) {
            return ($lemma_att, $lemma_id);
          }
        }
        # if lemmatisation attempt failed, fall back on word form
        return ($word_att, $word_id);
      }
      else {
        # not in corpus; return word attribute with empty ID list
        return ($word_att);
      }
    }            
  }
  else {
    # regular expression -> match on lemma or word forms
    # (if ignore case/diacritics was requested, we must always use regexps)
    my @lemma_ids = $lemma_att->regex2id($keyword, $flags);
    my @word_ids = $word_att->regex2id($keyword, $flags);
    my $lemma_freq = $lemma_att->idlist2freq(@lemma_ids);
    my $word_freq = $word_att->idlist2freq(@word_ids);
    if ($lemma_freq > $word_freq) {
      return ($lemma_att, @lemma_ids);
    }
    else {
      return ($word_att, @word_ids);
    }
  }
  

}

# internal method: get positions of a given keyword
sub get_positions {
  my $self = shift;
  my $keyword = shift;
  my ($attribute, @id_list);

  if ($keyword =~ /\s+/) {
    # keyword contains blanks -> multi-word string
    my (@keywords) = split /\s+/, $keyword;
    $keyword = shift @keywords; # match first keyword
    ($attribute, @id_list) = $self->get_IDs($keyword);
    my @cpos_list = $attribute->idlist2cpos(@id_list);
    foreach $keyword (@keywords) {
      ($attribute, @id_list) = $self->get_IDs($keyword);
      my %matches_keyword = map {($_ => 1)} @id_list; # make lookup hash for matching IDs
      foreach my $cpos (@cpos_list) {
        my $id = $attribute->cpos2id($cpos+1);
        if (defined $id and $matches_keyword{$id}) { 
          $cpos = $cpos + 1;    # tokens in multi-word string must be adjacent ...
        }
        else {
          $id = $attribute->cpos2id($cpos+2);
          if (defined $id and $matches_keyword{$id}) {
            $cpos = $cpos + 2;  # ... allowing at most 1 intervening corpus position
          }
          else {
            $cpos = -1;         # delete this candidate
          }
        }
      }
      @cpos_list = grep {$_ >= 0} @cpos_list; # remove deleted candidates from list
    }
    return @cpos_list;
  } 
  else {
    ($attribute, @id_list) = $self->get_IDs($keyword);
    return $attribute->idlist2cpos(@id_list);
  }
}



return 1;

__END__


=head1 NAME

  CWB::Web::Search - A WWW search style front-end to the CWB

=head1 SYNOPSIS

  use CWB::Web::Search;

  # typically, a search object is used for a single search only
  $search = new CWB::Web::Search 'WEB-SITE-INDEX';
  # here, 'web-site-index' is a CWB-encoded corpus containing the
  # textual content of an indexed WWW site

  $search->window("1 document"); # search window 
  $search->context("document");  # match context returned as HTML
  # window and context size are specified in CQP syntax
  $search->data("url", "date");  # values of s-attributes are returned
  # here, markup is <url http://...> and <date 13 Oct 1999>
  $search->ignore_case(1);       # case-insensitive search
  $search->ignore_diacritics(1); # search ignores diacritics
  $search->cull('after');        # remove duplicate documents (context)
  $search->highlight('<font color=red>'); # HTML highlighting tag

  # run query - returns number of matches (for convenience)
  $nr_matches = $search->query("+editor", "free", "GNU", "-Microsoft");
  # look for documents containing the word 'editor', preferably
  # 'free' or 'GNU' as well, and not containing 'Microsoft'
  $nr_matches = $search->size;  # same as number returned by query()

  # alternatively, let WebSearch::Search parse the query string
  $nr_matches = $search->query_string("+editor free GNU -Microsoft");

  # typical result processing loop
  for ($i = 0; $i < $nr_matches; $i++) {
    $nr = $i + 1;               # match number
    $m = $search->match($i);    # returns result struct without 'context'
    $m->{'cpos'};               # corpus position of match centre
    $m->{'quality'};            # relevance of this match
    $m->{'summary'};            # summary of match (HTML encoded)
    $m->{'data'}->{'url'};      # requested data values
    $m->{'data'}->{'date'};
    if ($want_context) {
      $m = $search->match($i, 'context');
      $m->{'context'};          # match with context (HTML encoded)
    }
  }

  undef $search;

=head1 DESCRIPTION

The I<CWB::Web::Search> module executes simple queries similar to
commercial Web search engines on CWB-encoded corpora. The I<query()> method
returns I<keywords> found in the corpus with the requested amount of
context in HTML format. Additionally, data stored in structural
attributes can be returned. Typically, a CGI script will create a
I<CWB::Web::Search> object for a single query.

=head1 ERRORS

If the I<CWB::Web::Search> module encounters an error condition, an error
message is printed on C<STDERR> and the program is terminated. A user-defined
error handler can be installed with the I<on_error()> method. In this case,
the error callback function is passed the error message generated by the module
as a list of strings.

=head1 CORPUS REGISTRY

If you need to use a registry other than the default corpus registry,
you should change the setting directly in the L<CWB::CL|CWB::CL> module.

  use CWB::CL;
  $CWB::CL::Registry = "/path/to/my/registry";

This will affect all new I<CWB::Web::Search> objects.

=head1 RESULT STRUCTURE

The search module's I<match()> method return a I<result struct> for
the n-th match of the last query executed. A CGI script will usually
iterate through all matches with a loop similar to this:

    $nr_matches = $search->query(...);
    for ($n = 0; $n < $nr_maches; $n++) {
      $m = $search->match($n);
      # code for processing match data in result struct $m 
    }

A I<result struct> $m has the following fields:

=over 4

=item $m->{'cpos'}

I<Corpus  position> of the I<centre>  of  this match (the I<centre> is
computed from the positions of all search I<keywords> in a match).

=item $m->{'quality'}

An estimate of the I<relevance> of this match. This ranking is given as a
percentage with 100% corresponding to a "perfect match". The matches found
by the I<query()> method are sorted according to their 'quality' value. 

=item $m->{'summary'}

A text segment from the corpus containing most of the <keywords> found
in this match (up to a reasonable maxium length). It is returned in
HTML format with the I<keywords> highlighted.

=item $m->{'context'}

The text segment from the corpus containing all <keywords> found in
this match, expanded according to the I<context()> setting. It is
returned in HTML format with the I<keywords> highlighted.

B<NB> The I<context> field is only included if the C<'context'> switch
was passed to the I<match()> method:

    $m = $search->match($n, 'context');

See the remarks on I<virtual context> in the description of the
I<cull()> method below.

=item $m->{'data'}

The values of the structural attributes requested by the I<data()> 
method are returned in the subfields of the 'data' field. A typical
CGI application will use the 'data' field to retrieve document URLs,
e.g.

    $match_url = $m->{'data'}->{'url'};

where the search corpus contains regions like the following

    <url http://www.ims.uni-stuttgart.de/> ... </url>

The values stored in the 'data' field are not HTML encoded.

=back


=head1 METHODS

=over 4

=item $search = new CWB::Web::Search $corpus;

Create I<CWB::Web::Search> object for WWW search queries on the
CWB corpus C<$corpus>.

=item @results = $search->query($key1, $key2, ... );

Searches corpus for the specified I<keywords> and returns a list
of matches sorted by (decreasing) relevance. 
 
See L<"RESULT STRUCTURE"> for the format of the C<@results> list. 

=back


=head1 COPYRIGHT

Copyright (C) 1999-2010 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
