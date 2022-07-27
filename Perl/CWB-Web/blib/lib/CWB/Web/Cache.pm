package CWB::Web::Cache;
# -*-cperl-*-
$VERSION = 'v3.4.0';

##
## Short overview of module and cache architecture:
##
## The CWB::Web::Cache module uses a cache directory to keep selected named queries
## persistent between CQP sessions.  It is mainly intended for use in simple Web front-ends.
## Therefore, it is neither completely safe in heavy-duty CGI applications (where race
## conditions may occur) nor optimised for speed.  First, a CWB::Web::Cache object is created
## for an existing CQP process (using the CWB::CQP module) and must be initialised with settings
## for the cache directory path and caching strategy.  In order to make a named query result
## (of the running CQP process) persistent, it is stored in the disk cache directory, and a unique
## identifier is returned to the calling program.  This identifier can then be used to recover
## the persistent named query in a subsequent session (unless the result has already expired from
## the cache, which case must be handled by the caller).
##
## The CWB::Web::Cache module can also execute simple CQP queries and make their results
## persistent.  The query results are identified by corpus, query string, and an optional sort
## (stored as metadata) rather than a single unique identifier, and can be shared among different
## processes using the same cache directory.  When a persistent query result has expired from the
## cache, it is re-created in a way transparent to the calling program (by re-executing the query
## expression in the CQP process).
##
## The cache directory contains two subdirectories and an optional CONFIG file:
##   index/  ...  text files as 'markers' for cached queries (may contain 'metadata' about the cached query)
##   data/   ...  named query results stored in CQP's internal format
## A persistent named query is stored in a file with the name <corpus>:<query_name> in the data/
## subdirectory (e.g. DICKENS:ResultA-1121, where the numerical suffix is used to create a unique filename
## if necessary).  A text file with the same name is created in the index/ directory and may hold
## meta-information about the cached query result.  Storing a named query proceeds in the following steps:
##   1. create cache directory and subdirectories if they do not exist
##   1a recover cache settings (size, expiration time, ...) from CONFIG file unless initialised by program (OPTIONAL)
##   2. if cache directory size exceeds allowed maximum (checked with "du -k"), delete oldest query results
##      from cache until size has been reduced sufficiently (index file is always deleted first to keep other
##      processes from trying to read data while it is being erased);  if expiration time is set, all files
##      older than the specified limit are removed first
##   3. extend query name with numerical suffix to unique filename if necessary (checking files in index directory)
##   4. create empty file with unique name in index directory to "lock" data file for other processes
##   5. set CQP DataDirectory to data subdirectory, copy named query to unique name and save to disk
##   6. overwrite index file with meta-information (OPTIONAL)
## Recovering the named query result requires the following steps:
##   1. if cache directory and subdirectories do not exist, return "expired" status
##   2. check index directory for unique filename specified by caller, return "expired" if not found
##   3. touch index file so it won't be deleted by another process cleaning up the cache while we're reading it;
##      this also ensures that frequently accessed query results do not expire from the cache (or makes it
##      very unlikely, at least)
##   4. set CQP DataDirectory to data subdirectory and load cached query results from disk into CQP process
##      (force loading with "size <unique_name>;" command)
##   5. return internal name of restored query result to calling process
##
## Running a persistent query involves the following steps:
##   1. create a numeric hash key from the query expression (perhaps also the optional sort clause)
##   2. find all index files that match the given corpus and the hash key (possibly extended with numerical suffix)
##   3. check metadata stored in index files for exact query expression and sort clause
##   4. if found, recover query result with the retrieve() method (which touches the index file)
##      and return unique query name to caller (which may copy it to a simple name if desired)
##   5. otherwise, execute the specified query in the CQP process;  if an index file matching the query
##      string but not the sort clause has been found, the corresponding query result may be loaded instead,
##      which is presumably faster than re-running the query (OPTIONAL, unless sort clause included in hash key)
##   6. sort the query result according to the specified sort clause (otherwise, unsort the result)
##   7. use the store() method to create unique filename and cache query result, storing query expression
##      and sort clause as metadata in index file;  note that it is important to create an empty index file first,
##      to keep other processes (executing the same query) from loading the data file while it is being written
##   8. return name of query result to caller (need not be the unique identifier)
##
## Note that there is no support for a user-defined error handler, as all error conditions are serious
## internal faults and deserve to die (or rather croak).  A named query that has expired from the cache
## is a normal result rather than an error condition and must be expected by the caller.

use Carp;
use DirHandle;
use CWB;
use CWB::CQP;

## CWB::Web::Cache object struct:
##   'cqp'    =>  CWB::CQP object
##   'dir'    =>  cache directory
##   'index'  =>  index subdirectory
##   'data'   =>  data subdirectory
##   'size'   =>  maximum size of data directory (in MBytes, default = 5MB)
##   'expire' =>  time after which query results expire from cache (in hours, default = 24h)

## $cache = new CWB::Web::Cache -cqp => $cqp, -cachedir => $dir, [-cachesize => $cache_size,] [-cachetime => $expiration_time];
## object constructor, must be initialised with CQP object and cache directory (optional parameters have default values)
sub new {
  my $class = shift;
  my %arg = (-cqp => undef, -cachedir => undef, -cachesize => 5, -cachetime => 24);
  # default cache size is 5 MB, default expiration time is 24 hours
  croak 'Usage:  $cache = new CWB::Web::Cache -cqp => $cqp, -cachedir => $dir, [-cachesize => $cache_size,] [-cachetime => $expiration_time];'
    unless named_args(\%arg, @_);
  my $self = {};
  my $cqp = $arg{-cqp};
  croak 'Error: argument -cqp must be CWB::CQP object.'
    unless ref $cqp eq "CWB::CQP";
  $self->{'cqp'} = $cqp;
  my $dir = $arg{-cachedir};
  $self->{'dir'} = $dir;
  $self->{'index'} = "$dir/index";
  $self->{'data'} = "$dir/data";
  my $size = $arg{-cachesize};
  croak 'Error: maximal cache size must be valid number > 0.'
    unless $size + 0 > 0;
  $self->{'size'} = $size;
  my $expire = $arg{-cachetime};
  croak 'Error: expiration time must be valid number > 0.'
    unless $expire + 0 > 0;
  $self->{'expire'} = $expire;
  return bless($self, $class);
}

## use default object destructor (no cleaning up to be done)

## INTERNAL:  $ok = named_args(\%arghash, @pairs);
## process named args (-name => $value) from list @pairs; carps about wrong or missing arguments and returns false in this case;
## values are stored in %arghash which must be initialised with acceptable parameters and their default values (undef for required parameter)
sub named_args {
  my $arg = shift;
  if ((@pairs % 2) != 0) {
    carp "Odd number of elements in named argument list (@_).";
    return 0;
  }
  while (@_) {
    my $name = shift;
    my $value = shift;
    if (not exists $arg->{$name}) {
      carp "Illegal named argument: $name => '$value'.";
      return 0;
    }
    $arg->{$name} = $value;
  }
  my @missing = grep { not defined $arg->{$_} } keys %$arg;
  if (@missing) {
    carp "Required argument(s) [@missing] not specified.";
    return 0;
  }
  return 1;
}

## INTERNAL:  @files = list_directory($dir);
## get directory listing from DirHandle module (local file names only!), skipping anything that doesn't look like an identifier
sub list_directory {
  my $dir = shift;
  my $dh = new DirHandle $dir;
  return ()
    unless defined $dh;
  my @files = grep {/^[A-Z0-9_-]+:/} $dh->read;
  undef $dh;
  return @files;
}

## INTERNAL:  $kbytes = directory_size($dir);
## determine size of directory with "du -ks" (returns size in kbytes)
sub directory_size {
  my $dir = shift;
  my @output = ();
  CWB::Shell::Cmd("du -ks $dir", \@output);
  croak "Can't parse output of 'du -ks $dir' (@output)"
    unless @output == 1 and $output[0] =~ /^([0-9]+)\s+\S+/;
  my $size = $1;
  return $size;
}

## INTERNAL:  delete_files(@files);
## unlink list of files and croak() in case of an error
sub delete_files {
  foreach my $file (@_) {
    croak "Error: can't unlink file $file ($!)"
      unless 1 == unlink $file;
  }
}

## INTERNAL:  @filenames = $cache->index_files;
## INTERNAL:  @matching_files = $cache->index_files($substring);
sub index_files {
  my $self = shift;
  my @files = list_directory($self->{'index'});
  if (@_) {
    my $sub = shift;
    @files = grep { index($_, $sub) >= 0 } @files;
  }
  return @files;
}

## INTERNAL:  $cache->make_dirs;
## ensure that cache directory and subdirectories exist, otherwise create them
sub make_dirs {
  my $self = shift;
  my $dir = $self->{'dir'};
  my $index = $self->{'index'};
  my $data = $self->{'data'};
  unless (-d $dir) {
    CWB::Shell::Cmd("mkdir $dir");
    CWB::Shell::Cmd("chmod 777 $dir");
  }
  unless (-d $index) {
    CWB::Shell::Cmd("mkdir $index");
    CWB::Shell::Cmd("chmod 777 $index");
  }
  unless (-d $data) {
    CWB::Shell::Cmd("mkdir $data");
    CWB::Shell::Cmd("chmod 777 $data");
  }
}

## INTERNAL:  $cache->sweep_cache;
## check size of cache data directory and delete old files when it exceeds the limit
sub sweep_cache {
  my $self = shift;
  my $force = shift;
  $force = (defined $force and $force eq "-force") ? 1 : 0;
  my $index = $self->{'index'};
  my $data = $self->{'data'};
  my $max_size = $self->{'size'} * 1024;        # maximal allowed size in kBytes
  my $expire = $self->{'expire'} / 24;          # maximal storage time in days
  my $size = directory_size($data);
  return                                        # return if cache size is below threshold (unless called with "-force")
    unless $force or $size > $max_size;
  # remove 'dangling' data files where index is missing (if there are any)
  my @files = grep { not -f "$index/$_" } list_directory($data);
  if (@files) {
    delete_files(map { "$index/$_"} @files);
  }
  # delete all files that are older than the maximal expiration time
  @files = grep { -M "$index/$_" > $expire } list_directory($index);
  if (@files) {
    delete_files(map { "$index/$_"} @files);
    delete_files(map { "$data/$_"} @files);
  }
  $size = directory_size($data);                # return if these steps have already reduced the cache size
  return unless $size > $max_size;
  # finally, sort remaining files by age (oldest first) and find out how many must be removed to reduce cache size below limit
  @files =
    sort { $b->[1] <=> $a->[1] }
      map { [$_, (-M "$index/$_"), (-s "$data/$_" || 0) / 1024] }
        list_directory($index);
  my $req_size = $size - 0.8 * $max_size;       # reduce to 80% of maximal cache size to reduce number of relatively slow cache sweeps
  my $cum_size = 0;
  while (@files and $cum_size < $req_size) {
    my $f = shift @files;
    my $file = $f->[0];
    unless (-M "$index/$file" < $f->[1]) {      # just to be sure that index file hasn't been touched in the meantime
      delete_files("$index/$file");
      if (-f "$data/$file") {
        delete_files("$data/$file");
      }
      $cum_size += $f->[2];
    }
  }
  $size = directory_size($data);                # check that cache sweep was successful
  croak 'Error: cache sweep failed for directory '.$self->{'dir'}
    if $size > $max_size;
}

## @lines = $cache->get_metadata($unique_name);
## read metadata for unique filename (read from index file), returns () if index file does not exist
sub get_metadata {
  my $self = shift;
  my $file = shift;
  my $index = $self->{'index'};
  my @lines = ();
  if (-f "$index/$file") {
    my $fh = CWB::OpenFile "$index/$file";
    while (<$fh>) {
      chomp;
      push @lines, $_;
    }
    $fh->close;
  }
  return @lines;
}

## $unique_name = $cache->store($named_query [, @metadata]);
## make named query result $named_query (which must be fully qualified with corpus name) persistent,
## returning unique name for retrieval; optional metadata is s(tored in index file when the data file has been created
sub store {
  my $self = shift;
  my $name = shift;
  my $index = $self->{'index'};
  my $data = $self->{'data'};
  my $cqp = $self->{'cqp'};
  croak "Error: query name $name illegal or not fully specified."
    unless $name =~ /^([A-Z0-9_-]+):([A-Za-z0-9_-]+)$/;
  my $corpus = $1;
  my $localname = $2;
  # create cache directory and subdirectories if necessary
  $self->make_dirs;
  # check size of cache directory and remove old entries if necessary
  $self->sweep_cache;
  # extend $name to unique filename (in index directory)
  my %index_file = map {$_ => 1} $self->index_files("$name-");
  my $ext = 1;
  while (exists $index_file{"$name-$ext"}) {
    $ext++;
  }
  my $unique = "$name-$ext";
  my $unique_local = "$localname-$ext";
  # now create empty index file to "lock" the unique name from other processes
  my $fh = CWB::OpenFile "> $index/$unique";
  $fh->close;
  CWB::Shell::Cmd("chmod 666 $index/$unique");
  # set DataDirectory in CQP session and re-activate base corpus
  $cqp->exec("set DataDirectory '$data'");
  $cqp->exec("$corpus");
  # copy named query to unique name and store it in data directory
  $cqp->exec("$unique_local = $name");
  $cqp->exec("save $unique");
  CWB::Shell::Cmd("chmod 666 $data/$unique");
  # re-write index file if caller has passed metadata
  if (@_) {
    $fh = CWB::OpenFile "> $index/$unique";
    while (@_) {
      my $line = shift;
      chomp $line;                              # normalize whitespace in metadata (esp. linebreaks)
      $line =~ s/\s+/ /g;
      print $fh "$line\n";
    }
    $fh->close;
  }
  return $unique;
}

## $size = $cache->retrieve($unique_name);
## load persistent query result identified by $unique_name into CQP process,
## returning number of matches (>= 0) or 'undef' if the query result has expired from the cache
sub retrieve {
  my $self = shift;
  my $unique = shift;
  my $index = $self->{'index'};
  my $data = $self->{'data'};
  my $cqp = $self->{'cqp'};
  my $expire = $self->{'expire'} / 24;          # maximal storage time in days
  croak "Error: illegal unique query name $unique"
    unless $unique =~ /^([A-Z0-9_-]+):([A-Za-z0-9_-]+)$/;
  my $corpus = $1;
  my $unique_local = $2;
  return undef                                  # may have expired from cache during sweep ...
    unless -f "$index/$unique";
  if (-M "$index/$unique" > $expire) {          # ... or because it exceeded the maximal storage time
    $self->sweep_cache("-force");               # delete expired files so they won't hide exact matches
    return undef;
  }
  CWB::Shell::Cmd("touch $index/$unique");      # touch index file to mark last access time
  # set DataDirectory in CQP session and re-activate base corpus
  $cqp->exec("set DataDirectory '$data'");
  $cqp->exec("$corpus");
  # force loading of named query result in CQP session
  my ($size) = $cqp->exec("size $unique");
  return $size;
}

## ($unique_name [, $matching_lines]) = $cache->retrieve_matching($prefix, ["-partial",] @metadata);
## load persistent query result which is a unique extension of $prefix and matches the specified metadata;
## if flag -partial is specified, a query result matching only the first N lines of @metadata will also
## be accepted; N is returned as $matching_lines and must be checked by the caller
sub retrieve_matching {
  my $self = shift;
  my $prefix = shift;
  my $partial = 0;
  if (@_ and lc($_[0]) eq "-partial") {
    $partial = 1;
    shift @_;
  }
  my $index = $self->{'index'};
  my $data = $self->{'data'};
  my $cqp = $self->{'cqp'};
  my $expire = $self->{'expire'} / 24;          # maximal storage time in days
  croak "Error: query name prefix $prefix is illegal or not fully specified."
    unless $prefix =~ /^([A-Z0-9_-]+):([A-Za-z0-9_-]+)$/;
  my $corpus = $1;
  my $prefix_local = $2;
  my @unique = $self->index_files($prefix);     # find matching unique names in index directory
  # normalise specified metadata and compare to each matching index file in turn
  my @metadata = ();
  while (@_) {
    my $line = shift;
    chomp $line;                                # normalize whitespace in metadata (esp. linebreaks)
    $line =~ s/\s+/ /g;
    push @metadata, $line;
  }
  my $N = @metadata;
  my $best_N = 0;                               # find best match for metadata among matching filenames
  my $best_unique = "";
  foreach my $unique (@unique) {
    my @md_unique = $self->get_metadata($unique);
    my $i = 0;
    while ($i < $N and $i < @md_unique) {
      last unless $metadata[$i] eq $md_unique[$i];
      $i++;
    }
    # $i now holds the number of matching lines of metadata
    if ($i > $best_N) {
      $best_N = $i;
      $best_unique = $unique;
    }
  }
  if ($partial) {
    return ($best_unique, $best_N)              # retrieve query result if (partial) match has been found, return unique name and matching lines
      if $best_N > 0 and defined $self->retrieve($best_unique);
  }
  else {
    return $best_unique                         # retrieve query result if match has been found and return unique name if successful
      if $best_N == $N and defined $self->retrieve($best_unique);
  }
  return undef;                                 # return 'undef' if there was no match (or the match could not be retrieved from the cache)
}

## $query_name = $cache->query(-corpus => $corpus, -query => $query_expr, ...);
## execute CQP query (and optional subquery) on corpus $corpus and make its results persistent;
## if query has already been executed, the results are retrieved from the disk cache unless they're expired;
## results are automatically sorted with optional sort clause (e.g. 'by word %cd reverse') and stored
## in sort order; optional cut specifies maximum number of matches (so a single query cannot occupy the
## entire cache directory) and defaults to a value allowing at least 4 queries to be cached simultaneously
sub query {
  my $self = shift;
  my %arg = (-corpus => undef,
             -query => undef,
             -subquery => "",
             -sort => "", -keyword => "",
             -cut => int(($self->{'size'} * 1024 * 1024 / 4) / 16));
  # -cut defaults to average file size of at most 1/4 of cache size (in bytes, assuming approx. 16 bytes per match)
  croak 'Usage:  $cache->query(-corpus => $corpus, -query => $query, [-subquery => $subquery,] [-sort => $sort_clause,] [-keyword => $keyword_command,] [-cut => $max_matches]);'
    unless named_args(\%arg, @_);
  my $corpus = $arg{-corpus};
  my $query = $arg{-query};
  my $subquery = $arg{-subquery};
  my $sort = $arg{-sort};
  my $keyword = $arg{-keyword};
  my $cut = $arg{-cut};
  croak "Error: invalid corpus name $corpus"
    unless $corpus =~ /^[A-Z0-9_-]+$/;
  $query =~ s/(\s*\;\s*)+$//;                   # delete trailing ";" from query so we can append cut statement
  if ($subquery) {
    # don't limit first query if we want to run a subquery, because (i) we may be filtering out matches with the subquery
    # and (ii) the subquery might have more matches than the original, so a cut clause on the subquery is required
    if ($subquery =~ s/!\s*$//) {
      # if subquery is used as a filter, we need to move the "!" behind the "cut" clause
      $subquery .= "cut $cut !";
    }
    else {
      $subquery .= " cut $cut";
    }
  }
  else {
    $query .= " cut $cut";
  }

  # compute hash key from normalised query string
  my $hv = 0;
  my $normal = $query;
  $normal =~ s/\s+/ /g;
  for my $i (0 .. (length($normal) - 1)) {
    use integer;
    $hv = ($hv * 33) ^ ($hv >> 27) ^ ord(substr($normal, $i, 1));
  }
  $hv = abs($hv);
  my $name = "PQ$hv";

  # try to retrieve cached query result matching query string, sort clause, and set keyword command
  my ($unique, $matching_lines) = $self->retrieve_matching("$corpus:$name", "-partial", $query, $subquery, $keyword, $sort);
  if (defined $unique) {
    return $unique
      if $matching_lines == 4;                  # return exact match
  }
  else {
    $matching_lines = 0;
  }

  # if query result was not found in cache, run query and subquery, sort result, and execute set keyword command
  my $cqp = $self->{'cqp'};
  $cqp->exec("$corpus");
  if ($matching_lines >= 2) {                   # copy query result from partial match ...
    $cqp->exec("$name = $unique");
  }
  else {                                        # ... or execute query in query lock mode (highly recommended for CGI scripts)
    if ($subquery) {
      $cqp->exec_query("$name-TEMP = $query");       # run primary query, assign to temporary name, then run subquery
      my ($N) = $cqp->exec("size $name-TEMP");
      if ($N > 0) {
        $cqp->exec("$name-TEMP");
        $cqp->exec_query("$name = $subquery");
        $cqp->exec("$corpus");
      }
      else {                                    # don't run subquery if primary returns no matches
        $cqp->exec("$name = $name-TEMP");
      }
      $cqp->exec("discard $name-TEMP");
    }
    else {
      $cqp->exec_query("$name = $query");            # run primary query only
    }
  }
  my ($N) = $cqp->exec("size $name");
  if ($N > 0) {
    if ($matching_lines < 3) {                  # no match or partial match without appropriate keyword
      if ($keyword eq "") {
        $cqp->exec("set $name keyword NULL")    # if $keyword == "", make sure to delete keywords that may have been loaded from partial match
        if $matching_lines >= 2;
        ## but only if the result has been loaded from cache with a non-empty set keyword clause ($matching_lines >= 2)
        ## in order to avoid overwriting keyword anchors that have been set directly in the query (CQP v3.4.16 and newer)
      }
      else {
        $cqp->exec("set $name keyword $keyword"); # otherwise execute keyword command
      }
    }
    ## sort command cannot have matched in partial match -> execute
    $cqp->exec("sort $name $sort");           # "unsorts" (i.e. sorts in cpos order) if $sort == ""
  }

  # make query result persistent and return its unique name
  return $self->store("$corpus:$name", $query, $subquery, $keyword, $sort);
}


return 1;

__END__


=head1 NAME

CWB::Web::Cache - A simple shared cache for CQP query results

=head1 SYNOPSIS

  use CWB::CQP;
  use CWB::Web::Cache;

  $cqp = new CWB::CQP;
  $cache = new CWB::Web::Cache -cqp => $cqp, -cachedir => $dir,
    [-cachesize => $cache_size,] [-cachetime => $expiration_time];

  # transparently execute and cache simple CQP queries
  $id = $cache->query(-corpus => "DICKENS", -query => '[pos="NN"] "of" "England"');
  ($size) = $cqp->exec("size $id");

  # optional features: sort clause, set keyword, subquery, and maximal number of matches
  $id = $cache->query(
    -corpus => "DICKENS", -query => $query,
    -sort => $sort_clause,
    -keyword => $set_keyword_command,
    -subquery => $subquery,
    -cut => $max_nr_of_matches  # resonable default calculated from cache size
  );


  ## The functions below are for internal use only and subject to change in future releases!
  $id = $cache->store("DICKENS:Query1");        # activates DICKENS corpus
  $id = $cache->store("DICKENS:Query1", "Metadata line #1", ...);

  $size = $cache->retrieve($id);                # (re-)activates DICKENS corpus
  die 'Sorry, named query has expired from the cache.'
    unless defined $size;
  $cqp->exec("Query1 = $id");                   # copy query result to desired name

  $id = $cache->retrieve("DICKENS:Query", "Metadata line #1", ...);
  die 'Sorry, no named query matching your metadata found in cache.'
    unless defined $id;
  $cqp->exec("Query = $id");


=head1 DESCRIPTION

The B<CWB::Web::Cache> module provides a simple shared caching meachnism
for CQP query results, making them persistent across multiple CQP sessions.
Old data files are automatically deleted when they pass the specified I<$expiration_time>, or
to keep the cache from growing beyond the specified I<$cache_size> limit.

Note that a B<CWB::Web::Cache> handle must be created with a pre-initialised CQP backend (i.e.
a B<CWB::CQP> object), which will be used to access the cache and (re-)run a query when necessary.

Most scripts will access the cache through the B<query()> method, which executes and caches CQP queries
in a fully transparent way (with optional C<sort> clause, C<set keyword> command, subquery,
and C<cut> to limit the maximal number of matches).  After successful execution, the query result is
loaded into the CQP backend, the appropriate corpus is activated, and the I<$id> of the named query is
returned.

Starting from version 3.4.15, the C<sort> clause is executed I<after> a C<set keyword> command
so that C<keyword> anchors can be used in sorting.

Direct access to cache entries is provided by the low-level methods B<store()> and B<retrieve()>.
Note that these are intended for internal use only and may change in future releases.


=head1 METHODS

B<TODO>


=head1 COPYRIGHT

Copyright (C) 1999-2020 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
