package CWB::Encoder;
# -*-cperl-*-

use strict;
use warnings;

use CWB;

=head1 NAME

  CWB::Encoder - Perl tools for encoding and indexing CWB corpora

=head1 SYNOPSIS

  use CWB::Encoder;


  $bnc = new CWB::Indexer "BNC";
  $bnc = new CWB::Indexer "/path/to/registry:BNC";

  $bnc->group("corpora");     # optional: group and access
  $bnc->perm("640");          # permissions for newly created files

  $bnc->memory(400);          # use up to 400 MB of RAM (default: 75)
  $bnc->validate(0);          # disable validation for faster indexing
  $bnc->debug(1);             # enable debugging output

  $bnc->make("word", "pos");  # build index & compress
  $bnc->makeall;              # process all p-attributes


  $bnc = new CWB::Encoder "BNC";

  $bnc->registry("/path/to/registry");  # will try to guess otherwise
  $bnc->dir("/path/to/data/directory"); # directory for corpus data files
  $bnc->overwrite(1);         # may overwrite existing files / directories
  
  $bnc->longname("British National Corpus"); # optional
  $bnc->info("Line1.\nLine2.\n...");    # optional multi-line info text
  $bnc->charset("latin1");    # defaults to latin1
  $bnc->language("en");       # defaults to ??
  
  $bnc->group("corpora");     # optional: group and access permissions
  $bnc->perm("640");          # for newly created files & directories

  $bnc->p_attributes("word"); # declare postional atts (no default!)
  $bnc->p_attributes(qw<pos lemma>);  # may be called repeatedly
  $bnc->null_attributes("teiHeader"); # declare null atts (ignored)
  $bnc->s_attributes("s");    # s-attributes in cwb-encode syntax
  $bnc->s_attributes(qw<div0* div1*>);# * = store annotations (-V)
  $bnc->s_attributes("bncDoc:0+id");  # recursion & XML attributes

  $bnc->decode_entities(0);        # don't decode XML entities (with -x flag)
  $bnc->undef_symbol("__UNDEF__"); # mark missing values like cwb-encode

  $bnc->memory(400);          # use up to 400 MB of RAM (default: 75)
  $bnc->validate(0);          # disable validation for faster indexing
  $bnc->encode_options("-C"); # pass arbitrary options to cwb-encode

  $bnc->verbose(1);           # print some progress information
  $bnc->debug(1);             # enable debugging output

  $bnc->encode(@files);       # encoding, indexing, and compression

  $pipe = $bnc->encode_pipe;  # can also feed input text from Perl script
  while (...) {
    print $pipe "$line\n";
  }
  $bnc->close_pipe;

=head1 DESCRIPTION

This package contains modules for the automatic encoding and indexing
of CWB corpora. 

B<CWB::Indexer> builds indices for some or all positional attributes
of an existing corpus (using the B<cwb-makeall> tool). In addition,
these attributes are automatically compressed (using the
B<cwb-huffcode> and B<cwb-compress-rdx> tools). Compression and
indexing is interleaved to minimise the required amount of temporary
disk space, and a B<make>-like system ensures that old index files are
automatically updated.

B<CWB::Encoder> automates all steps necessary to encode a CWB corpus
(which includes cleaning up old files, running B<cwb-encode>, editing
the registry entry, indexing & compressing positional attributes, and
setting access permissions). Both modules can be set up with a few
simple method calls. Full descriptions are given separately in the
following sections. 

=cut

## ======================================================================
##  automatic creation, compression and updating of CWB index files (for p-attributes)
## ======================================================================

package CWB::Indexer;

use CWB;
use Carp;

# makefile-like rules for creating / updating components
#   TRIGGER .. update component when one of these comps exists & is newer
#   NEEDED  .. componentes required by command below
#   CREATES .. these files will be created by COMMAND
#   COMMAND .. shell command to create this component 
#              interpolates '#C' (corpus id), '#A' (attribute name), '#R' (registry flag), 
#                           '#M' (memory limit), '#T' (no validate), '#V' (validate)
#              (issues "can't create" error message if COMMAND starts with "ERROR")
#   DELETE  .. delete these components when target exist or has been created
our %RULES =
  (
   DIR => {
           TRIGGER => [],
           NEEDED  => [],
           CREATES => [],
           COMMAND => "ERROR: Corpus data directory must be created manually.",
           DELETE  => [],
          },
   CORPUS => {
           TRIGGER => [],
           NEEDED  => [],
           CREATES => [],
           COMMAND => "ERROR: You must run the cwb-encode tool first.",
           DELETE  => [],
          },
   LEXICON => {
           TRIGGER => [],
           NEEDED  => [],
           CREATES => [],
           COMMAND => "ERROR: You must run the cwb-encode tool first.",
           DELETE  => [],
          },
   LEXIDX => {
           TRIGGER => [],
           NEEDED  => [],
           CREATES => [],
           COMMAND => "ERROR: You must run the cwb-encode tool first.",
           DELETE  => [],
          },
   FREQS => {
           TRIGGER => [qw<CORPUS LEXICON LEXIDX>],
           NEEDED  => [qw<CORPUS LEXICON LEXIDX>],
           CREATES => [qw<FREQS>],
           COMMAND => CWB::Shell::Quote($CWB::Makeall)." #R -c FREQS -P #A #C",
           DELETE  => [],
          },
   LEXSRT => {
           TRIGGER => [qw<CORPUS LEXICON LEXIDX>],
           NEEDED  => [qw<LEXICON LEXIDX>],
           CREATES => [qw<LEXSRT>],
           COMMAND => CWB::Shell::Quote($CWB::Makeall)." #R -c LEXSRT -P #A #C",
           DELETE  => [],
          },
   CIS => {
           TRIGGER => [qw<CORPUS LEXICON LEXIDX FREQS>],
           NEEDED  => [qw<CORPUS LEXICON LEXIDX FREQS>],
           CREATES => [qw<CIS CISCODE CISSYNC>],
           COMMAND => CWB::Shell::Quote($CWB::Huffcode)." #R #T -P #A #C",
           DELETE  => [qw<CORPUS>],
          },
   CISCODE => {
           TRIGGER => [qw<CORPUS LEXICON LEXIDX FREQS>],
           NEEDED  => [qw<CORPUS LEXICON LEXIDX FREQS>],
           CREATES => [qw<CIS CISCODE CISSYNC>],
           COMMAND => CWB::Shell::Quote($CWB::Huffcode)." #R #T -P #A #C",
           DELETE  => [qw<CORPUS>],
          },
   CISSYNC => {
           TRIGGER => [qw<CORPUS LEXICON LEXIDX FREQS>],
           NEEDED  => [qw<CORPUS LEXICON LEXIDX FREQS>],
           CREATES => [qw<CIS CISCODE CISSYNC>],
           COMMAND => CWB::Shell::Quote($CWB::Huffcode)." #R #T -P #A #C",
           DELETE  => [qw<CORPUS>],
          },
   REVCORP => {
           TRIGGER => [qw<CORPUS CIS CISCODE CISSYNC LEXICON LEXIDX FREQS LEXSRT>],
           NEEDED  => [qw<CIS CISCODE CISSYNC LEXICON LEXIDX FREQS LEXSRT>],
           CREATES => [qw<REVCORP REVCIDX>],
           COMMAND => CWB::Shell::Quote($CWB::Makeall)." #R #M #V -P #A #C",
           DELETE  => [],
          },
   REVCIDX => {
           TRIGGER => [qw<CORPUS CIS CISCODE CISSYNC LEXICON LEXIDX FREQS LEXSRT>],
           NEEDED  => [qw<CIS CISCODE CISSYNC LEXICON LEXIDX FREQS LEXSRT>],
           CREATES => [qw<REVCORP REVCIDX>],
           COMMAND => CWB::Shell::Quote($CWB::Makeall)." #R #M #V -P #A #C",
           DELETE  => [],
          },
   CRC => {
           TRIGGER => [qw<CORPUS CIS CISCODE CISSYNC REVCORP REVCIDX LEXICON LEXIDX FREQS LEXSRT>],
           NEEDED  => [qw<REVCORP REVCIDX LEXICON LEXIDX FREQS LEXSRT>],
           CREATES => [qw<CRC CRCIDX>],
           COMMAND => CWB::Shell::Quote($CWB::CompressRdx)." #R #T -P #A #C",
           DELETE  => [qw<REVCORP REVCIDX>],
          },
   CRCIDX => {
           TRIGGER => [qw<CORPUS CIS CISCODE CISSYNC REVCORP REVCIDX LEXICON LEXIDX FREQS LEXSRT>],
           NEEDED  => [qw<REVCORP REVCIDX LEXICON LEXIDX FREQS LEXSRT>],
           CREATES => [qw<CRC CRCIDX>],
           COMMAND => CWB::Shell::Quote($CWB::CompressRdx)." #R #T -P #A #C",
           DELETE  => [qw<REVCORP REVCIDX>],
          },
  );

# components that must exist or be created by make() in the specified order
# (note that prerequisites are created recursively, so there mustn't be loops in the rules!)
# (CISCODE, CISSYNC, and CRCIDX should be created automatically by previous rules)
our @NEEDED = qw<LEXICON LEXIDX FREQS LEXSRT CIS CISCODE CISSYNC CRC CRCIDX>;

=head1 CWB::Indexer METHODS

=over 4

=item $idx = new CWB::Indexer $corpus;

=item $idx = new CWB::Indexer "$registry_path:$corpus";

Create a new B<CWB::Indexer> object for the specified corpus. If
I<$corpus> is not registered in the default registry path (the built-in 
default or the C<CORPUS_REGISTRY> environment variable), the registry
directory has to be specified explicitly, separated from the corpus name
by a C<:> character. I<$registry_path> may contain multiple directories
separated by C<:> characters.

=cut

sub new {
  my $class = shift;
  my $self = {
              NAME => undef,    # name of the corpus (CWB corpus ID)
              REGISTRY => "",   # -r flag for non-default registry
              FILES => {},      # lookup hash for component filenames
              # $self->{FILES}->{$att}->{$comp} = $pathname;
              TYPES => {},      # attribute types: P / S
              GROUP => undef,   # optional: set group for new files
              PERM => undef,    # optional: set permissions for new files
              MEMORY => 75,     # memory limit for index creation
              VALIDATE => 1,    # enable/disable validation
              DEBUG => 0,       # enable/disable debugging output
             };
  croak 'USAGE:  $c = new CWB::Indexer $corpus_id;'
    unless @_ == 1;
  my $name = shift;
  if ($name =~ /^\s*(.+)\s*:\s*([^:]+)$/) {
    $self->{REGISTRY} = "-r ".CWB::Shell::Quote($1);
    $name = $2;
  }
  $self->{NAME} = $name;

  # use cwb-describe-corpus to find out component pathnames
  my @lines = ();
  my $registry = $self->{REGISTRY};
  my $cmd = CWB::Shell::Quote($CWB::DescribeCorpus)." $registry -d $name";
  CWB::Shell::Cmd($cmd, \@lines);

  my $comp = "";                # component name
  my $attr = "";                # attribute name
  foreach (@lines) {
    if (/Component\s+([A-Z]+):/) {
      $comp = $1;
    }
    elsif (/Attribute:\s+(\S+)/ or /Attribute\s+(\S+):/) {
      $attr = $1;
    }
    elsif (/Path\/Value:\s+(\S(.*\S)?)/) {
      croak "CWB::Indexer: Can't find component name for file $1 (aborted).\n"
        unless $comp;
      croak "CWB::Indexer: Can't find attribute name for file $1 (aborted).\n"
        unless $attr;
      $self->{FILES}->{$attr}->{$comp} = $1;
      $comp = $attr = "";       # reset to check for syntax errors
    }
    elsif (/Type:\s+([A-Z])/) {
      carp "CWB::Indexer: Missing attribute name in output of cwb-describe-corpus $name (skipped).\n"
        unless $attr;
      $self->{TYPES}->{$attr} = $1;
    }
    # all other lines are ignored
  }

  return bless($self, $class);
}

=item $idx->group($group);

=item $idx->perm($permission);

Optional group membership and access permissions for newly created
files (otherwise, neither B<chgrp> nor B<chmod> will be called). Note
that I<$permission> must be a string rather than an octal number (as
for the built-in B<chmod> function). Indexing will fail if the
specified group and/or permissions cannot be set.

=cut

sub group {
  my ($self, $group) = @_;
  $self->{GROUP} = $group;
}

sub perm {
  my ($self, $perm) = @_;
  $self->{PERM} = $perm;
}

=item $idx->memory($mbytes);

Set approximate memory limit for B<cwb-makeall> command, in MBytes.
The memory limit defaults to 75 MB, which is a reasonable value for
systems with at least 128 MB of RAM. 

=cut

sub memory {
  my ($self, $mem) = @_;
  croak "CWB::Indexer:  memory limit ($mem) must be positive integer number (aborted).\n"
    unless $mem =~ /^[1-9][0-9]*$/;
  $self->{MEMORY} = $mem;
}

=item $idx->validate(0);

Turn off validation of index and compressed files, which may give 
substantial speed improvements for larger corpora.

=cut

sub validate {
  my ($self, $yesno) = @_;
  $self->{VALIDATE} = $yesno;
}

=item $idx->debug(1);

Activate debugging output (on STDERR). 

=cut

sub debug {
  my ($self, $yesno) = @_;
  $self->{DEBUG} = $yesno;
}

# internal method: get full pathname of a component file
sub filename {
  my ($self, $att, $comp) = @_;
  my $path = $self->{FILES}->{$att}->{$comp};
  croak "CWB::Indexer: can't determine filename for component $att/$comp (aborted).\n"
    unless defined $path;
  return $path;
}

# internal method: make single component (recursively builds dependencies)
sub make_comp {
  my ($self, $att, $comp) = @_;
  my $rule = $RULES{$comp};
  croak "CWB::Indexer:  no rule found for component $comp (aborted).\n"
    unless defined $rule;
  my ($trigger, $needed, $creates, $command, $delete) =
    @$rule{qw<TRIGGER NEEDED CREATES COMMAND DELETE>};

  my $update = 0;               # check whether component needs to be created / updated
  my $file = $self->filename($att, $comp);
  if (not -f $file) {
    print STDERR "CWB::Indexer: component $att/$comp does not exist -> create\n"
      if $self->{DEBUG};
    $update = 1;                # file does not exist -> create
  }
  else {
    my $age = -M $file;
    foreach my $t (@$trigger) { # check for triggers that are newer than target
      my $t_file = $self->filename($att, $t);
      if (-f $t_file) {
        my $t_age = -M $t_file;
        if ($t_age < $age) {
          $update = 1;          # trigger is newer -> update
          print STDERR "CWB::Indexer: component $att/$t is newer than $att/$comp -> update\n"
            if $self->{DEBUG};
        }
      }
    }
  }

  if ($update) {                # (re-)create component if necessary
    print STDERR
      "CWB::Indexer: make_comp($att, $comp)\n",
      "CWB::Indexer:   creating component file $file\n"
        if $self->{DEBUG};

    foreach my $c (@$creates) { # delete old target files (first, to make room for intermediate files)
      my $f = $self->filename($att, $c);
      if (-f $f) {
        unlink $f;
        croak "CWB::Indexer: Can't delete file $f (aborted).\n"
          if -e $f;
        print STDERR "CWB::Indexer:   deleting file $f\n"
          if $self->{DEBUG};
      }
    }

    foreach my $c (@$needed) {  # recursively create/update prerequisites
      $self->make_comp($att, $c);
    }

    if ($command =~ s/^\s*ERROR\s*(:\s*)?//) {
      croak
        "CWB::Indexer: Can't create component $att/$comp ($file)\n",
        "              $command\n";
    }

    $command =~ s/\#C/$self->{NAME}/g; # substitute variables in $command
    $command =~ s/\#A/$att/g;
    $command =~ s/\#R/$self->{REGISTRY}/g;
    $command =~ s/\#M/-M $self->{MEMORY}/g;
    $command =~ s/\#T/($self->{VALIDATE}) ? "" : "-T"/eg;
    $command =~ s/\#V/($self->{VALIDATE}) ? "-V" : ""/eg;
    
    print STDERR "CWB::Indexer:   exec: $command\n"
      if $self->{DEBUG};
    CWB::Shell::Cmd $command;   # execute creation command

    my $perm = $self->{PERM};   # check that target file(s) exist and set permissions
    my $group = $self->{GROUP};
    foreach my $c (@$creates) { 
      my $f = $self->filename($att, $c);
      croak "CWB::Indexer: Creation of component $att/$c ($f) failed (aborted).\n"
        unless -s $f;
      if ($perm) {
        my $cmd = "chmod $perm '$f'";
        print STDERR "CWB::Indexer:   exec: $cmd\n"
          if $self->{DEBUG};
        CWB::Shell::Cmd $cmd;
      } 
      if ($group) {
        my $cmd = "chgrp $group '$f'";
        print STDERR "CWB::Indexer:   exec: $cmd\n"
          if $self->{DEBUG};
        CWB::Shell::Cmd $cmd;
      } 
    }

    print STDERR "CWB::Indexer: component $att/$comp has been created successfully\n"
      if $self->{DEBUG};
  }

  # always run the cleanup so that unneccessary files are automatically deleted
  foreach my $c (@$delete) {    # delete intermediate components that are no longer needed
    my $f = $self->filename($att, $c);
    if (-f $f) {
      print STDERR "CWB::Indexer:   deleting file $f\n"
        if $self->{DEBUG};
      unlink $f;
      croak "CWB::Indexer: Can't delete intermediate file $f (aborted).\n"
        if -f $f;
    }
  }
}

=item $idx->make($att1, $att2, ...);

Process one or more positional attributes. An index is built for each
attribute and the data files are compressed. Missing files are
re-created (if possible) and old files are updated automatically.

=cut

sub make {
  my $self = shift;
  my $corpus = $self->{NAME};
  foreach my $att (@_) {
    my $type = $self->{TYPES}->{$att};
    croak "CWB::Indexer:  $corpus.$att is not a positional attribute (aborted).\n"
      unless $type and $type eq "P";
    print STDERR "CWB::Indexer: make($corpus.$att)\n"
      if $self->{DEBUG};
    foreach my $comp (@NEEDED) {
      $self->make_comp($att, $comp);
    }
    print STDERR "CWB::Indexer: attribute $corpus.$att was indexed successfully\n"
      if $self->{DEBUG};
  }
}

=item $idx->makeall;

Process all positional attributes of the corpus.

=cut

sub makeall {
  my $self = shift;
  foreach my $att (keys %{$self->{TYPES}}) {
    $self->make($att)
      if $self->{TYPES}->{$att} eq "P";
  }
}

=back

=cut

## ======================================================================
##  automatic encoding, indexing, and compression of corpora
## ======================================================================

package CWB::Encoder;

use CWB;
use Carp;
use DirHandle;

=head1 CWB::Encoder METHODS

=over 4

=item $enc = new CWB::Encoder $corpus;

Create a new B<CWB::Encoder> object for the specified corpus. Note
that the registry directory cannot be passed directly to the
constructor (use the B<registry> method instead).

=cut

sub new {
  my $class = shift;
  my $self = {                  # create and initialise object
              NAME => undef,    # name of corpus (CWB corpus ID)
              LONGNAME => "",   # long descriptive name
              INFO => "Indexed with CWB::Encoder.", # contents of .info file
              CHARSET => "latin1", # character set (corpus property)
              LANG => "??",     # language (corpus property)
              REGISTRY => undef, # registry directory (will be automatically chosen if possible)
              DIR => undef,     # data directory
              PATT => [],       # positional attributes
              SATT => [],       # structural attributes (cwb-encode syntax for recursion and XML atts)
              NATT => [],       # null attributes (tags are ignored)
              GROUP => undef,   # optional: group and access
              PERM => undef,    # permissions for created files
              OVERWRITE => undef, # can I overwrite existing files?
              MEMORY => 75,     # passed to CWB::Indexer
              VALIDATE => 1,    # passed to CWB::Indexer
              ENTITIES => 1,    # whether to decode XML entities (and skip comments etc.)
              UNDEF_SYMBOL => "", # string to insert for missing values of p-attributes
              OPTIONS => "",    # arbitrary further options can be passed to cwb-encode
              VERBOSE => 0,     # print some progress information (stdout)
              DEBUG => 0,
              PIPE => undef,    # pipe to cwb-encode (for encode_pipe() method)
             };
  bless($self, $class);
  $self->name(shift)
    if @_;
  return $self;
}

=item $enc->name($corpus);

Change the CWB name of a corpus after the encoder object I<$enc> has been created.
Has to be used if the constructor was called without arguments.

=cut

sub name {
  my ($self, $name) = @_;
  $self->{NAME} = lc($name);
}

=item $enc->longname($descriptive_name);

Optional long, descriptive name for a corpus (single line).

=cut

sub longname {
  my ($self, $longname) = @_;
  carp "CWB::Encoder: long name ($longname) must not contain \" and \\ characters (removed).\n"
    if $longname =~ tr/\"\\//d;
  $self->{LONGNAME} = $longname;
}

=item $enc->info($multiline_text);

Multi-line text that will be written to the C<.info> file of the
corpus.

=cut

sub info {
  my ($self, $info) = @_;
  $self->{INFO} = $info;
}

=item $enc->charset($code);

Set corpus character set (as a corpus property in the registry entry).
In CWB release 3.0, only C<latin1> is fully supported, but character sets
C<latin2>, ..., C<latin9> and C<utf8> can also be declared.
In CWB release 3.5, the following character sets are supported:
C<ascii>, C<latin1>, ..., C<latin9>, C<arabic>, C<greek>, C<hebrew> and C<utf8>.
Any other I<$code> will raise a warning.

=cut

sub charset {
  my ($self, $charset) = @_;
  if ($CWB::Config::Version >= 3.004) {
    carp "CWB::Encoder: character set $charset not supported by CWB v${CWB::Config::VersionString}.\n(valid character sets: ascii, latin1, ..., latin9, arabic, greek, hebrew, utf8)\n"
      unless $charset =~ /^(latin[1-9]|utf8|ascii|arabic|greek|hebrew)$/;
  }
  else {
    carp "CWB::Encoder: character set $charset not supported by CWB v${CWB::Config::VersionString}.\n(valid character sets: latin1, ..., latin9, utf8)\n"
      unless $charset =~ /^(latin[1-9]|utf8)$/;    
  }
  $self->{CHARSET} = $charset;
}

=item $enc->language($code);

Set corpus language (as an informational corpus property in the
registry entry). Use of a two-letter ISO code (C<de>, C<en>, C<fr>,
...) is recommended, and any other formats will raise a warning.

=cut

sub language {
  my ($self, $lang) = @_;
  carp "CWB::Encoder: language ($lang) should be two-letter ISO code.\n"
    unless $lang =~ /^[a-z]{2}$/;
  $self->{LANG} = $lang;
}

=item $enc->registry($registry_dir);

Specify registry directory I<$registry_dir>, which must be a single
directory rather than a path. If the registry directory is not set
explicitly, B<CWB::Encoder> attempts to determine the standard
registry directory, and will fail if there is no unique match
(e.g. when the C<CORPUS_REGISTRY> environment variable specifies
multiple directories).

=cut

sub registry {
  my ($self, $registry) = @_;
  $self->{REGISTRY} = $registry;
}

=item $enc->dir($data_dir);

Specify directory I<$data_dir> for corpus data files. The directory is
automatically created if it does not exist.

=cut

sub dir {
  my ($self, $dir) = @_;
  $self->{DIR} = $dir;
}

=item $enc->p_attributes($att1, $att2, ...);

Declare one or more B<positional attributes>. This method can be
called repeatedly with additional attributes. Note that I<all> 
positional attributes, including C<word>, have to be declared
explicitly.

=cut

sub p_attributes {
  my $self = shift;
  push @{$self->{PATT}}, @_;
}

=item $enc->s_attributes($att1, $att2, ...);

Declare one or more B<structural attributes>. I<$att1> etc. are either
simple attribute names or complex declarations using the syntax of the
C<-S> and C<-V> flags in B<cwb-encode>. See the I<CWB Corpus Encoding
Tutorial> for details on the attribute declaration syntax for nesting
depth and XML tag attributes. By default, structural attributes are
encoded without annotation strings (C<-S> flag). In order to store
annotations (C<-V> flag), append an asterisk (C<*>) to the attribute
name or declaration. The I<CWB Corpus Encoding Tutorial> explains when
to use C<-S> and when to use C<-V>. The B<s_attributes> method can
be called repeatedly to add further attributes.

=cut

sub s_attributes {
  my $self = shift;
  push @{$self->{SATT}}, @_;
}

=item $enc->null_attributes($att1, $att2, ...);

Declare one or more B<null attributes>.  XML start and end tags
with these names will be ignored (and not inserted as C<word>
tokens). This method can be called repeatedly.

=cut

sub null_attributes {
  my $self = shift;
  push @{$self->{NATT}}, @_;
}

=item $enc->group($group);

=item $enc->perm($permission);

Optional group membership and access permissions for newly created
files (otherwise, neither B<chgrp> nor B<chmod> will be called). Note
that I<$permission> must be a string rather than an octal number (as
for the built-in B<chmod> function). Encoding will fail if the
specified group and/or permissions cannot be set. If the data
directory has to be created, its access permissions and group
membership are set accordingly.

=cut

sub group {
  my ($self, $group) = @_;
  $self->{GROUP} = $group;
}

sub perm {
  my ($self, $perm) = @_;
  $self->{PERM} = $perm;
}

=item $enc->overwrite(1);

Allow B<CWB::Encoder> to overwrite existing files. This is required
when either the registry entry or the data directory exists already.
When overwriting is enabled, the registry entry and all files in the 
data directory are deleted before encoding starts.

=cut

sub overwrite {
  my ($self, $yesno) = @_;
  $self->{OVERWRITE} = $yesno;
}

=item $enc->memory($mbytes);

Set approximate memory limit for B<cwb-makeall> command, in MBytes.
The memory limit defaults to 75 MB, which is a reasonable value for
systems with at least 128 MB of RAM. The memory setting is only used
when building indices for positional attributes, not during the
initial encoding process.

=cut

sub memory {
  my ($self, $mem) = @_;
  croak "CWB::Indexer: memory limit ($mem) must be positive integer number (aborted).\n"
    unless $mem =~ /^[1-9][0-9]*$/;
  $self->{MEMORY} = $mem;
}

=item $enc->validate(0);

Turn off validation of index and compressed files, which may give 
substantial speed improvements for larger corpora.

=cut

sub validate {
  my ($self, $yesno) = @_;
  $self->{VALIDATE} = $yesno;
}

=item $enc->decode_entities(0);

Whether B<cwb-encode> is allowed to decode XML entities and skip XML 
comments (with the C<-x> option).  Set this option to false if you
want an HTML-compatible encoding of the CWB corpus that does not need
to be converted before display in a Web browser.

=cut

sub decode_entities {
  my ($self, $yesno) = @_;
  $self->{ENTITIES} = $yesno;
}

=item $enc->undef_symbol("__UNDEF__");

Symbol inserted for missing values of positional attributes (either
because there are too few columns in the input or because attribute
values are explicit empty strings).  By default, no special symbol
is inserted (i.e. missing values are encoded as empty strings C<"">).
Use the command shown above to mimic the standard behaviour of
B<cwb-encode>.

=cut

sub undef_symbol {
  my ($self, $symbol) = @_;
  $symbol = "" unless defined $symbol;
  croak "CWB::Indexer: symbol <$symbol> for missing values of p-attributes must not contain single quotes or control characters (aborted).\n"
    if $symbol =~ /[\x{00}-\x{1f}\']/;
  $self->{UNDEF_SYMBOL} = $symbol;
}

=item $enc->encode_options($string);

This options allows users to pass arbitrary further command-line
options to the B<cwb-encode> program. Use with caution!

=cut

sub encode_options {
  my ($self, $value) = @_;
  $value = "" unless defined $value;
  $self->{OPTIONS} = $value;
}

=item $enc->verbose(1);

Print some progress information (on STDOUT).

=cut

sub verbose {
  my ($self, $yesno) = @_;
  $self->{VERBOSE} = $yesno;
}

=item $enc->debug(1);

Activate debugging output (on STDERR).

=cut

sub debug {
  my ($self, $yesno) = @_;
  $self->{DEBUG} = $yesno;
  $self->{VERBOSE} = 1          # debugging also activates verbose output
    if $yesno;
}

# internal method: called _before_ running cwb-encode
sub prepare_encode {
  my $self = shift;
  my $overwrite = $self->{OVERWRITE};
  
  my $name = $self->{NAME};     # check that setup is complete
  croak "CWB::Encoder: Corpus ID hasn't been specified (with name() method)\n"
    unless $name;
  croak "CWB::Encoder: No positional attributes specified.\n"
    unless @{$self->{PATT}} > 0;

  my $reg = $self->{REGISTRY};
  if (not defined $reg) {
    $reg = CWB::RegistryDirectory(); # try to guess registry if not specified
    $self->{REGISTRY} = $reg;
  }
  croak "CWB::Encoder: Can't determine unique registry directory (path is $reg).\n"
    if $reg =~ /:/;
  croak "CWB::Encoder: Registry directory $reg does not exist.\n"
    unless -d $reg;
  print STDERR "CWB::Encoder: registry directory is $reg\n"
    if $self->{DEBUG};

  my $regfile = "$reg/$name";   # remove registry entry if it exists
  if (-f $regfile) {
    croak "CWB::Encoder: Registry file already exists (overwriting not enabled).\n"
      unless $overwrite;
    print "Removing registry file $reg/$name ...\n"
      if $self->{VERBOSE};
    unlink "$reg/$name";
    croak "CWB::Encoder: Can't delete registry file $reg/$name\n"
      if -f "$reg/$name";
    print STDERR "CWB::Encoder: deleting file $reg/$name\n"
      if $self->{DEBUG};
  }

  my $dir = $self->{DIR};       # check/create data directory
  croak "CWB::Encoder: Data directory has not been set.\n"
    unless $dir;
  if (-d $dir) {
    croak "CWB::Encoder: Data directory already exists (overwriting not enabled).\n"
      unless $overwrite;
    print "Cleaning up data directory $dir ...\n"
      if $self->{VERBOSE};
    my $dh = new DirHandle $dir;
    my @files = grep {-f $_} (glob("$dir/*"), glob("$dir/.*"));
    my ($file, $filename);
    while (defined($filename = $dh->read)) {
      $file = "$dir/$filename";
      next unless -f $file;     # skip subdirectories etc.
      unlink $file;
      carp "CWB::Encoder: Can't delete file $file (trying to continue).\n"
        if -f $file;
      print STDERR "CWB::Encoder: deleting file $file\n"
        if $self->{DEBUG};
    }
    $dh->close;
  }
  else {
    print "Creating data directory $dir ...\n"
      if $self->{VERBOSE};
    croak "CWB::Encoder: Can't create data directory $dir\n"
      unless mkdir $dir;
    my $perm = $self->{PERM};
    if ($perm) {
      $perm =~ tr[642][753];    # derive directory permissions
      CWB::Shell::Cmd(["chmod", $perm, $dir]);
      $perm = "(chmod $perm)";
    }
    else {
      $perm = "";
    }
    my $group = $self->{GROUP};
    if ($group) {
      CWB::Shell::Cmd(["chgrp", $group, $dir]);
      $group = "(chgrp $group)";
    }
    else {
      $group = "";
    }
    print STDERR "CWB::Encoder: created directory $dir $perm $group\n"
      if $self->{DEBUG};
  }

}

# internal method: used to construct a cwb-encode command line for the specified input files
sub make_encode_cmd {
  my $self = shift;
  my @files = @_;
  my %attr = ();                # check for duplicate attributes

  my @cmd = ($CWB::Encode, "-s");                           # build encode command (-xsB flags are always recommended!)
  push @cmd, "-B" unless $self->{UNDEF_SYMBOL} =~ /^\s*$/;  # assume that whitespace-only strings are allowed unless undef symbol is set
  push @cmd, "-x" if $self->{ENTITIES};                     # -x only if we're allowed to decode entities
  push @cmd, "-U", $self->{UNDEF_SYMBOL};
  push @cmd, "-R", $self->{REGISTRY}."/".$self->{NAME};     # has been set and checked by prepare_encode()
  push @cmd, "-d", $self->{DIR};
  push @cmd, "-c", $self->{CHARSET};                        # from version 2.2.101, cwb-encode can set character set in registry file
  push @cmd, "-v" if $self->{VERBOSE};                      # show progress in verbose mode
  push @cmd, $self->{OPTIONS} if $self->{OPTIONS} ne "";    # set additional user-defined options
  foreach my $file (@files) {   # check that all input files exist
    if (-d $file) {
      push @cmd, "-F", $file;
    }
    elsif (-f $file) {
      push @cmd, "-f", $file;      
    }
    else {
      croak "CWB::Encoder: Input file $file does not exist.\n";
    }
  }
  push @cmd, "-p", "-";                         # declare all p-attributes explicitly
  foreach my $att (@{$self->{PATT}}) {          # declare p-attributes
    croak "CWB::Encoder: Attribute $att declared twice!\n"
      if exists $attr{$att};
    push @cmd, "-P", $att;
    $attr{$att} = 1;
  }
  foreach my $att (@{$self->{NATT}}) {          # declare null attributes
    croak "CWB::Encoder: Attribute $att declared twice!\n"
      if exists $attr{$att};
    push @cmd, "-0", $att;
    $attr{$att} = 1;
  }
  foreach my $attspec (@{$self->{SATT}}) {      # declare s-attributes
    my $flag = ($attspec =~ s/\*$//) ? "-V" : "-S"; # '*' indicates -V (rather than -S)
    croak "CWB::Encoder: Invalid s-attribute specification '$attspec'\n"
      unless $attspec =~ /^\S+$/ and $attspec =~ /^[^:+]+(:[0-9]+)?(\+[^:+]+)*$/;
    my ($att) = split /:\+/, $attspec; # split attribute specification to get attribute name
    croak "CWB::Encoder: Attribute $att declared twice!\n"
      if exists $attr{$att};
    push @cmd, $flag, $att;
    $attr{$att} = 1;
  }

  return @cmd;
}

=item $enc->encode(@files_or_directories);

Encode one or more input files as a CWB corpus, using the parameter
settings of the I<$enc> object. The B<encode> method performs the full
encoding cycle, including indexing, compression, and setting access
permissions. All input files must be specified at once as subsequent
B<encode> calls would overwrite the new corpus. Input files may be
compressed with GZip (C<.gz>), as supported by B<cwb-encode>.

The argument list may also contain directories.  In this case, all files
with extensions C<.vrt> or C<.vrt.gz> in those directories will automatically
be added to the corpus.  Note that no recursive search of subdirectories is
performed: only files located in the specified directories will be included.

=cut

sub encode {
  my $self = shift;
  croak "CWB::Encoder: No input files specified.\n"
    unless @_ > 0;

  $self->prepare_encode;

  my @cmd = $self->make_encode_cmd(@_);
  print "Encoding corpus ".(uc $self->{NAME})." ...\n"
    if $self->{VERBOSE};
  print STDERR "CWB::Encoder: EXEC @cmd\n"
    if $self->{DEBUG};
  my $status = system @cmd; # don't use CWB::Shell::Cmd, which might treat input format warnings as a fatal 
  croak "CWB::Encoder: cwb-encode failed ($?)\n"
    unless $status == 0;

  $self->post_encode;

  print "Encoding complete.\n"
    if $self->{VERBOSE};
}

=item $pipe = $enc->encode_pipe;

Open a pipe to the B<cwb-encode> command and return its file handle.
This allows some pre-processing of the input by the Perl script
(perhaps reading from another pipe), which should B<print> to I<$pipe>
in one-word-per-line format. Note that the file handle I<$pipe> must
not be B<close>d by the Perl script (see the B<close_pipe> method
below).

=cut

sub encode_pipe {
  my $self = shift;

  $self->prepare_encode;

  my @cmd = CWB::Shell::Quote($self->make_encode_cmd(@_));
  print "Encoding corpus ".(uc $self->{NAME})." ...\n"
    if $self->{VERBOSE};
  print STDERR "CWB::Encoder: ... | @cmd\n"
    if $self->{DEBUG};
  my $pipe = CWB::OpenFile "| @cmd";

  $self->{PIPE} = $pipe;
  return $pipe;
}

=item $enc->close_pipe;

After opening an encode pipe with the B<encode_pipe> method and
B<print>ing the input text to this pipe, the B<close_pipe> method has
to be called to B<close> the pipe and trigger the post-encoding steps
(indexing, compression, and access permissions). When the
B<close_pipe> method returns, the corpus has been encoded
successfully.

=cut

sub close_pipe {
  my $self = shift;
  my $pipe = $self->{PIPE};
  croak "CWB::Encoder: close_pipe() method only allowed after encode_pipe().\n"
    unless $pipe;

  croak "CWB::Encoder: Error in cwb-encode pipe ($!).\n"
    unless $pipe->close;
  print STDERR "CWB::Encoder: pipe to cwb-encode program closed\n"
    if $self->{DEBUG};

  $self->post_encode;

  print "Encoding complete.\n"
    if $self->{VERBOSE};
}


# internal method: called _after_ running cwb-encode
sub post_encode {
  my $self = shift;
  my $perm = $self->{PERM};
  my $group = $self->{GROUP};
  my $dir = $self->{DIR};

  print "Setting access permissions ...\n" # set access permissions for created files
    if $self->{VERBOSE};
  foreach my $att (@{$self->{PATT}}) { # positional attributes
    my $pattern = CWB::Shell::Quote($dir)."/$att.*";
    print STDERR "CWB::Encoder: processing group $pattern\n"
      if $self->{DEBUG} and ($perm or $group); 
    CWB::Shell::Cmd("chmod $perm $pattern")
      if $perm;
    CWB::Shell::Cmd("chgrp $group $pattern")
      if $group;
  }
  foreach my $attspec (@{$self->{SATT}}) { # structural attributes
    my $temp = $attspec;        # don't modify original list
    my $rec = ($temp =~ s/:([0-9]+)//) ? $1 : 0;   # recursion depth
    my ($att, @xmlatts) = split /\+/, $temp;       # attribute name and XML tag attributes
    foreach my $n ("", 1 .. $rec) {                # indices of embedded regions
      foreach my $ext ("", map {"_$_"} @xmlatts) { # extensions for XML tag attributes
        my $pattern = CWB::Shell::Quote($dir)."/$att$ext$n.*";
        print STDERR "CWB::Encoder: processing group $pattern\n"
          if $self->{DEBUG} and ($perm or $group); 
        CWB::Shell::Cmd("chmod $perm $pattern")
          if $perm;
        CWB::Shell::Cmd("chgrp $group $pattern")
          if $group;
      }
    }
  }

  print "Writing .info file ...\n"     # write .info file
    if $self->{VERBOSE};
  my $infofile = "$dir/.info";
  my $fh = CWB::OpenFile "> $infofile";
  print $fh $self->{INFO}, "\n";
  $fh->close;
  CWB::Shell::Cmd(["chmod", $perm, $infofile])
    if $perm;
  CWB::Shell::Cmd(["chgrp", $group, $infofile])
    if $group;

  print "Editing registry entry ...\n" # edit registry file
    if $self->{VERBOSE};
  my $reg = $self->{REGISTRY};
  my $name = $self->{NAME};
  my $regfile = "$reg/$name";
  my $rf = new CWB::RegistryFile $regfile;
  croak "CWB::Encoder: Syntax error in registry entry $regfile\n"
    unless defined $rf;
  $rf->name($self->{LONGNAME});
  # $rf->property("charset", $self->{CHARSET}); # -- already set by cwb-encode (since v2.2.101)
  $rf->property("language", $self->{LANG});
  $rf->write($regfile);
  print STDERR "CWB::Encoder: registry entry $regfile has been edited\n"
    if $self->{DEBUG};
  print STDERR "CWB::Encoder: setting access permissions for $regfile\n"
    if $self->{DEBUG} and ($perm or $group);
  CWB::Shell::Cmd(["chmod", $perm, $regfile])
    if $perm;
  CWB::Shell::Cmd(["chgrp", $group, $regfile])
    if $group;

  my $idx = new CWB::Indexer "$reg:".(uc $name); # build indices and compress p-attributes
  $idx->group($group)
    if $group;
  $idx->perm($perm)
    if $perm;
  $idx->memory($self->{MEMORY});
  $idx->validate($self->{VALIDATE});
  $idx->debug($self->{DEBUG});
  print "Building indices and compressing p-attributes ...\n"
    if $self->{VERBOSE};
  $idx->makeall;

}

=back

=cut

## ======================================================================

1;

__END__

=head1 COPYRIGHT

Copyright (C) 2002-2013 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
