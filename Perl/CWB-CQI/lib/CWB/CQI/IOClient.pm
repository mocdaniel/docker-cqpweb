package CWB::CQI::IOClient;
# -*-cperl-*-

use strict;
use warnings;

use CWB::CQI;
use IO::Socket;
use FileHandle;
use Carp;

# export CQi client functions
use base qw(Exporter);
our @EXPORT = (
           qw<cqi_connect cqi_bye cqi_ask_feature>,
           qw<cqi_list_corpora cqi_charset cqi_properties cqi_attributes cqi_structural_attribute_has_values cqi_full_name cqi_corpus_info cqi_drop_corpus>,
           qw<cqi_attribute_size cqi_lexicon_size cqi_drop_attribute cqi_str2id cqi_id2str cqi_id2freq cqi_cpos2id cqi_cpos2str cqi_cpos2struc cqi_cpos2lbound cqi_cpos2rbound cqi_cpos2alg cqi_struc2str cqi_id2cpos cqi_idlist2cpos cqi_regex2id cqi_struc2cpos cqi_alg2cpos>,
           qw<cqi_query cqi_list_subcorpora cqi_subcorpus_size cqi_subcorpus_has_field cqi_dump_subcorpus cqi_drop_subcorpus cqi_fdist>,
          );

=head1 NAME

CWB::CQI::IOClient - Alternative CQi client library (based on IO::Socket)


=head1 DESCRIPTION

This is an alternative version of the B<CWB::CQI::Client> library, which uses the object-oriented
B<IO::Socket> module instead of calling low-level socket functions.  It is a direct replacement and
presents exactly the same API, so one can simply change

    use CWB::CQI::Client;

to

    use CWB::CQI::IOClient;

in any CQi client script.  See the L<CWB::CQI::Client> manpage for more information and a description
of available CQi functions.

=cut


our $conn = new FileHandle;

#
#
#  Error Handling
#
#

our $LastCmd = "<none>";            # keep track of last command in case we receive an error code

sub CqiError (@) {
  foreach (@_) {
    print STDERR "CQi ERROR: $_\n";
  }
  croak "CQI::Client -- connection aborted.";
  exit 1;                       # Perl/Tk seems to catch the croak ... 
}

sub CqiErrorCode ($) {
  my $errcode = shift;
  my $group = $errcode >> 8;
  my $command = $errcode & 0xff;
  my $errhex = sprintf "%02X:%02X", $group, $command;
  my $name = $CWB::CQI::CommandName{$errcode};
  
  if ($name =~ /ERROR/) {
    CqiError "Received $name  [$errhex]  in response to", "$LastCmd";
  }
  else {
    CqiError "Unexpected response $name  [$errhex]  to", "$LastCmd";
  }
}

sub CqiCheckResponse ($@) {
  my $response = shift;
  my %expect = map { $_ => 1 } @_;
  
  CqiErrorCode $response
    unless defined $expect{$response};
}


#
#
#  Connect to CQi server / Disconnect
#
#

sub cqi_connect {
  my $user = shift;
  my $passwd = shift;
  my $host = shift;             # optional
  my $port = shift;             # optional

  $host = 'localhost'
    unless defined $host;
  $port = $CWB::CQI::PORT
    unless defined $port;

  croak "USAGE: cqi_connect(username, password, [, remotehost [, port]]);"
    unless defined $user and defined $passwd;
  $LastCmd = "CQI_CTRL_CONNECT($user, '$passwd', $host, $port)";

  my $ipaddr = inet_aton($host);
  my $sockaddr = sockaddr_in($port, $ipaddr);
  my $protocol = getprotobyname('tcp');

  $conn = new IO::Socket 'Domain' => AF_INET, 'Type' => SOCK_STREAM, 'Proto' => "tcp", 'PeerHost' => $host, 'PeerPort' => $port
    or do { croak "cqi_connect(): $!", exit 1};
  $conn->autoflush(0);

  cqi_send_word($CWB::CQI::CTRL_CONNECT);
  cqi_send_string($user);
  cqi_send_string($passwd);
  cqi_flush();

  my $response = cqi_read_word();
  CqiCheckResponse $response, $CWB::CQI::STATUS_CONNECT_OK;
}

sub cqi_bye {
  $LastCmd = "CQI_CTRL_BYE()";
  cqi_send_word($CWB::CQI::CTRL_BYE);
  cqi_flush();
  my $response = cqi_read_word();
  CqiCheckResponse $response, $CWB::CQI::STATUS_BYE_OK;
  $conn->close;
  $conn = undef;
}

sub cqi_ping {
  $LastCmd = "CQI_CTRL_PING()";
  cqi_send_word($CWB::CQI::CTRL_PING);
  cqi_flush();
  CqiCheckResponse cqi_read_word(), $CWB::CQI::STATUS_PING_OK;
}


#
#
#  CQi Commands
#
#

sub cqi_ask_feature {
  my $feature = lc shift;
  my %features = (
                  "cqi1.0" => $CWB::CQI::ASK_FEATURE_CQI_1_0,
                  "cl2.3"  => $CWB::CQI::ASK_FEATURE_CL_2_3,
                  "cqp2.3" => $CWB::CQI::ASK_FEATURE_CQP_2_3,
                  );
  croak "USAGE: \$supported = cqi_ask_feature('cqi1.0' | 'cl2.3' | 'cqp2.3');"
    unless defined $features{$feature};
  $LastCmd = $CWB::CQI::CommandName{$features{$feature}} . "()";
  cqi_send_word($features{$feature});
  cqi_flush();
  return cqi_expect_bool();
}

sub cqi_list_corpora {
  $LastCmd = "CQI_CORPUS_LIST_CORPORA()";
  croak "USAGE: \@corpora = cqi_list_corpora();"
    unless @_ == 0;
  cqi_send_word($CWB::CQI::CORPUS_LIST_CORPORA);
  cqi_flush();
  return cqi_expect_string_list();
}

sub cqi_charset {
  my $corpus = shift;
  $LastCmd = "CQI_CORPUS_CHARSET($corpus)";
  cqi_send_word($CWB::CQI::CORPUS_CHARSET);
  cqi_send_string($corpus);
  cqi_flush();
  return cqi_expect_string();
}

sub cqi_properties {
  my $corpus = shift;
  $LastCmd = "CQI_CORPUS_PROPERTIES($corpus)";
  cqi_send_word($CWB::CQI::CORPUS_PROPERTIES);
  cqi_send_string($corpus);
  cqi_flush();
  return cqi_expect_string_list();
}

sub cqi_attributes {
  my $corpus = shift;
  my $type = shift;
  my %types = (
               'p' => $CWB::CQI::CORPUS_POSITIONAL_ATTRIBUTES,
               's' => $CWB::CQI::CORPUS_STRUCTURAL_ATTRIBUTES,
               'a' => $CWB::CQI::CORPUS_ALIGNMENT_ATTRIBUTES,
              );
  croak "USAGE: \@attributes = cqi_attributes(\$corpus, ('p'|'s'|'a'));"
    unless defined $types{$type};
  $LastCmd = $CWB::CQI::CommandName{$types{$type}} . "($corpus)";
  cqi_send_word($types{$type});
  cqi_send_string($corpus);
  cqi_flush();
  return cqi_expect_string_list();
}

sub cqi_structural_attribute_has_values {
  my $attribute = shift;
  $LastCmd = "CQI_CORPUS_STRUCTURAL_ATTRIBUTE_HAS_VALUES($attribute)";
  cqi_send_word($CWB::CQI::CORPUS_STRUCTURAL_ATTRIBUTE_HAS_VALUES);
  cqi_send_string($attribute);
  cqi_flush();
  return cqi_expect_bool();
}

sub cqi_full_name {
  my $corpus = shift;
  $LastCmd = "CQI_CORPUS_FULL_NAME($corpus)";
  cqi_send_word($CWB::CQI::CORPUS_FULL_NAME);
  cqi_send_string($corpus);
  cqi_flush();
  return cqi_expect_string();
}

sub cqi_corpus_info {
  my $corpus = shift;
  $LastCmd = "CQI_CORPUS_INFO($corpus)";
  cqi_send_word($CWB::CQI::CORPUS_INFO);
  cqi_send_string($corpus);
  cqi_flush();
  return cqi_expect_string_list();
}

sub cqi_drop_corpus {
  my $corpus = shift;
  $LastCmd = "CQI_CORPUS_DROP_CORPUS($corpus)";
  cqi_send_word($CWB::CQI::CORPUS_DROP_CORPUS);
  cqi_send_string($corpus);
  cqi_flush();
  cqi_expect_status($CWB::CQI::STATUS_OK);
}

sub cqi_attribute_size {
  my $attribute = shift;
  $LastCmd = "CQI_CL_ATTRIBUTE_SIZE($attribute)";
  cqi_send_word($CWB::CQI::CL_ATTRIBUTE_SIZE);
  cqi_send_string($attribute);
  cqi_flush();
  return cqi_expect_int();
}

sub cqi_lexicon_size {
  my $attribute = shift;
  $LastCmd = "CQI_CL_LEXICON_SIZE($attribute)";
  cqi_send_word($CWB::CQI::CL_LEXICON_SIZE);
  cqi_send_string($attribute);
  cqi_flush();
  return cqi_expect_int();
}

sub cqi_drop_attribute {
  my $attribute = shift;
  $LastCmd = "CQI_CL_DROP_ATTRIBUTE($attribute)";
  cqi_send_word($CWB::CQI::CL_DROP_ATTRIBUTE);
  cqi_send_string($attribute);
  cqi_flush();
  cqi_expect_status($CWB::CQI::STATUS_OK);
}

# 'scalar' functions which map to lists in the CQi are wrapped
# in a scalar-safe client interface, so we CAN use them with simple
# scalars in CQI::Client. 
sub cqi_str2id {
  my $attribute = shift;
  $LastCmd = "CQI_CL_STR2ID($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_STR2ID);
  cqi_send_string($attribute);
  cqi_send_string_list(@_);
  cqi_flush();
  my @list = cqi_expect_int_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_id2str {
  my $attribute = shift;
  $LastCmd = "CQI_CL_ID2STR($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_ID2STR);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_string_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_id2freq {
  my $attribute = shift;
  $LastCmd = "CQI_CL_ID2FREQ($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_ID2FREQ);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_int_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_cpos2id {
  my $attribute = shift;
  $LastCmd = "CQI_CL_CPOS2ID($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_CPOS2ID);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_int_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_cpos2str {
  my $attribute = shift;
  $LastCmd = "CQI_CL_CPOS2STR($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_CPOS2STR);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_string_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_cpos2struc {
  my $attribute = shift;
  $LastCmd = "CQI_CL_CPOS2STRUC($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_CPOS2STRUC);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_int_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_cpos2lbound {
  my $attribute = shift;
  $LastCmd = "CQI_CL_CPOS2LBOUND($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_CPOS2LBOUND);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_int_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_cpos2rbound {
  my $attribute = shift;
  $LastCmd = "CQI_CL_CPOS2RBOUND($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_CPOS2RBOUND);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_int_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_cpos2alg {
  my $attribute = shift;
  $LastCmd = "CQI_CL_CPOS2ALG($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_CPOS2ALG);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_int_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_struc2str {
  my $attribute = shift;
  $LastCmd = "CQI_CL_STRUC2STR($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_STRUC2STR);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  my @list = cqi_expect_string_list();
  if (wantarray) {
    return @list;
  }
  else {
    croak "Called in scalar context with list argument." unless @list == 1;
    return $list[0];
  }
}

sub cqi_id2cpos {
  croak "USAGE: \@cposlist = cqi_id2cpos(\$attribute, \$id);"
    unless @_ == 2 and wantarray;
  my $attribute = shift;
  my $id = shift;

  $LastCmd = "CQI_CL_ID2CPOS($attribute, $id)";
  cqi_send_word($CWB::CQI::CL_ID2CPOS);
  cqi_send_string($attribute);
  cqi_send_int($id);
  cqi_flush();
  return cqi_expect_int_list();
}

sub cqi_idlist2cpos {
  my $attribute = shift;
  $LastCmd = "CQI_CL_IDLIST2CPOS($attribute, [@_])";
  cqi_send_word($CWB::CQI::CL_IDLIST2CPOS);
  cqi_send_string($attribute);
  cqi_send_int_list(@_);
  cqi_flush();
  return cqi_expect_int_list();
}

sub cqi_regex2id {
  croak "USAGE: \@idlist = cqi_regex2id(\$attribute, \$regex);"
    unless @_ == 2 and wantarray;
  my $attribute = shift;
  my $regex = shift;

  $LastCmd = "CQI_CL_REGEX2ID($attribute, $regex)";
  cqi_send_word($CWB::CQI::CL_REGEX2ID);
  cqi_send_string($attribute);
  cqi_send_string($regex);
  cqi_flush();
  return cqi_expect_int_list();
}

sub cqi_struc2cpos {
  croak "USAGE: (\$start, \$end) = cqi_struc2cpos(\$attribute, \$struc);"
    unless @_ == 2 and wantarray;
  my $attribute = shift;
  my $struc = shift;

  $LastCmd = "CQI_CL_STRUC2CPOS($attribute, $struc)";
  cqi_send_word($CWB::CQI::CL_STRUC2CPOS);
  cqi_send_string($attribute);
  cqi_send_int($struc);
  cqi_flush();
  return cqi_expect_int_int();
}

sub cqi_alg2cpos {
  croak "USAGE: (\$s1, \$s2, \$t1, \$t2) = cqi_alg2cpos(\$attribute, \$alg);"
    unless @_ == 2 and wantarray;
  my $attribute = shift;
  my $alg = shift;

  $LastCmd = "CQI_CL_ALG2CPOS($attribute, $alg)";
  cqi_send_word($CWB::CQI::CL_ALG2CPOS);
  cqi_send_string($attribute);
  cqi_send_int($alg);
  cqi_flush();
  return cqi_expect_int_int_int_int();
}

# cqi_query() returns a CQi response code (CQI_STATUS_OK or error).
# An error code usually indicates a mistake in the query syntax.
# It aborts the program unless one of the following responses is received:
#   CQI_STATUS_OK
#   CQI_ERROR_*
#   CQI_CQP_ERROR_*
sub cqi_query {
  my ($mother, $child, $query) = @_;
  croak "USAGE: \$ok = cqi_query(\$mother_corpus, \$subcorpus_name, \$query);"
    unless @_ == 3 and $mother =~ /^[A-Z0-9_-]+(:[A-Z_][A-Za-z0-9_-]*)?$/
      and $child =~ /^[A-Z_][A-Za-z0-9_-]*$/;
  $query .= ";"
    unless $query =~ /;\s*$/;
  
  $LastCmd = "CQI_CQP_QUERY($mother, $child, '$query')";
  cqi_send_word($CWB::CQI::CQP_QUERY);
  cqi_send_string($mother);
  cqi_send_string($child);
  cqi_send_string($query);
  cqi_flush();
  my $response = cqi_read_word();
  my $group = $response >> 8;
  CqiError $response
    unless $response == $CWB::CQI::STATUS_OK or $group == $CWB::CQI::ERROR or $group == $CWB::CQI::CQP_ERROR;
  return $response;
}

sub cqi_list_subcorpora {
  my $corpus = shift;
  $LastCmd = "CQI_CQP_LIST_SUBCORPORA($corpus)";
  cqi_send_word($CWB::CQI::CQP_LIST_SUBCORPORA);
  cqi_send_string($corpus);
  cqi_flush();
  return cqi_expect_string_list();
}

sub cqi_subcorpus_size {
  my $subcorpus = shift;
  $LastCmd = "CQI_CQP_SUBCORPUS_SIZE($subcorpus)";
  cqi_send_word($CWB::CQI::CQP_SUBCORPUS_SIZE);
  cqi_send_string($subcorpus);
  cqi_flush();
  return cqi_expect_int();
}

# used internally
sub cqi_get_field_key {
  my $field = uc shift;
  if ($field =~ /^(MATCH(END)?|TARGET|KEYWORD)$/) {
    return eval "\$CWB::CQI::CONST_FIELD_$field";
  }
  else {
    return undef;
  }
}

sub cqi_subcorpus_has_field {
  my ($subcorpus, $field) = @_;
  croak "USAGE: \$ok = cqi_subcorpus_has_field(\$subcorpus, 'match'|'matchend'|'target'|'keyword');"
    unless @_ == 2 and defined (my $field_key = cqi_get_field_key($field));
  $LastCmd = "CQI_CQP_SUBCORPUS_HAS_FIELD($subcorpus, CQI_CONST_FIELD_".(uc $field).")";
  cqi_send_word($CWB::CQI::CQP_SUBCORPUS_HAS_FIELD);
  cqi_send_string($subcorpus);
  cqi_send_byte($field_key);
  cqi_flush();
  return cqi_expect_bool();
}

sub cqi_dump_subcorpus {
  my ($subcorpus, $field, $first, $last) = @_;
  croak "USAGE: \@column = cqi_dump_subcorpus(\$subcorpus, 'match'|'matchend'|'target'|'keyword', \$from, \$to);"
    unless @_ == 4 and defined (my $field_key = cqi_get_field_key($field));
  $LastCmd = "CQI_CQP_DUMP_SUBCORPUS($subcorpus, CQI_CONST_FIELD_".(uc $field).", $first, $last)";
  cqi_send_word($CWB::CQI::CQP_DUMP_SUBCORPUS);
  cqi_send_string($subcorpus);
  cqi_send_byte($field_key);
  cqi_send_int($first);
  cqi_send_int($last);
  cqi_flush();
  return cqi_expect_int_list();
}

sub cqi_drop_subcorpus {
  my $subcorpus = shift;
  $LastCmd = "CQI_CQP_DROP_SUBCORPUS($subcorpus)";
  cqi_send_word($CWB::CQI::CQP_DROP_SUBCORPUS);
  cqi_send_string($subcorpus);
  cqi_flush();
  cqi_expect_status($CWB::CQI::STATUS_OK);
}

## cqi_fdist() subsumes both cqi_fdist_1() and cqi_fdist_2()
## returns list of (id, f) or (id1, id2, f) tuples as hashref's
sub cqi_fdist {
  my $subcorpus = shift;
  my $cutoff = shift;
  my $key1 = shift;
  my $key2 = shift;
  my ($field1, $field2, $att1, $att2, $tmp);
  ($tmp, $att1) = split /\./, $key1;
  $field1 = cqi_get_field_key($tmp);
  if (defined $key2) {
    ($tmp, $att2) = split /\./, $key2;
    $field2 = cqi_get_field_key($tmp);
  }
  else {
    $field2 = "";
    $att2 = "x";
  }
  croak "USAGE: \@table = cqi_fdist(\$subcorpus, \$cutoff, \$key1 [, \$key2]);"
    unless @_ == 0 and defined $field1 and defined $field2 and defined $att1 and defined $att2
      and $att1 =~ /^[a-z]+$/ and $att2 =~ /^[a-z]+$/ and $cutoff >= 0;
  if ($field2 ne "") {
    $LastCmd = "CQI_CQP_FDIST_2($subcorpus, $cutoff, $key1, $key2)";
    cqi_send_word($CWB::CQI::CQP_FDIST_2);
    cqi_send_string($subcorpus);
    cqi_send_int($cutoff);
    cqi_send_byte($field1);
    cqi_send_string($att1);
    cqi_send_byte($field2);
    cqi_send_string($att2);
    cqi_flush();
    return cqi_expect_int_table();
  }
  else {
    $LastCmd = "CQI_CQP_FDIST_1($subcorpus, $cutoff, $key1)";
    cqi_send_word($CWB::CQI::CQP_FDIST_1);
    cqi_send_string($subcorpus);
    cqi_send_int($cutoff);
    cqi_send_byte($field1);
    cqi_send_string($att1);
    cqi_flush();
    return cqi_expect_int_table();
  }
}


#
#
#  CQi expect response / data
#
#
sub cqi_expect_byte {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_BYTE;
  return cqi_read_byte();
}

sub cqi_expect_bool {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_BOOL;
  return cqi_read_byte();
}

sub cqi_expect_int {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_INT;
  return cqi_read_int();
}

sub cqi_expect_string {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_STRING;
  return cqi_read_string();
}

sub cqi_expect_byte_list {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_BYTE_LIST;
  return cqi_read_byte_list();
}

sub cqi_expect_bool_list {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_BOOL_LIST;
  return cqi_read_byte_list();
}

sub cqi_expect_int_list {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_INT_LIST;
  return cqi_read_int_list();
}

sub cqi_expect_string_list {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_STRING_LIST;
  return cqi_read_string_list();
}

sub cqi_expect_int_int {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_INT_INT;
  return cqi_read_int(), cqi_read_int();
}

sub cqi_expect_int_int_int_int {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_INT_INT_INT_INT;
  return cqi_read_int(), cqi_read_int(), cqi_read_int(), cqi_read_int();
}

sub cqi_expect_int_table {
  my $r = cqi_read_word();
  CqiCheckResponse $r, $CWB::CQI::DATA_INT_TABLE;
  return cqi_read_int_table();
}

sub cqi_expect_status {
  my @expected = @_;            # arguments are list of acceptable responses
  my $r = cqi_read_word();
  CqiCheckResponse $r, @expected;
  return $r;
}


#
#
#  Internal subroutines (read / write)
#
#
sub cqi_send_byte ($) {
  $conn->print((pack "C", shift))
    or croak "cqi_send_byte(): $!";
}

sub cqi_send_word ($) {
  $conn->print((pack "n", shift))
    or croak "cqi_send_word(): $!";
}

sub cqi_send_int ($) {
  my $number = shift;           # safely convert native int to 32bit value
  $number = unpack "L", (pack "l", $number); # pack 32bit signed, unpack unsigned -> uses type which can hold unsigned 32bit value
  $conn->print(pack("N", $number)) # 'N' packs unsigned 32bit integer
    or croak "cqi_send_int(): $!";
}

sub cqi_send_string ($) {
  my $str = shift;
  $conn->print((pack "n", length $str), $str)
    or croak "cqi_send_str(): $!";
}

sub cqi_send_byte_list (@) {
  cqi_send_int(scalar @_);
  map {cqi_send_byte($_)} @_;
}

sub cqi_send_word_list (@) {
  cqi_send_int(scalar @_);
  map {cqi_send_word($_)} @_;
}

sub cqi_send_int_list (@) {
  cqi_send_int(scalar @_);
  map {cqi_send_int($_)} @_;
}

sub cqi_send_string_list (@) {
  cqi_send_int(scalar @_);
  map {cqi_send_string($_)} @_;
}

sub cqi_flush () {
  $conn->flush
    or croak "cqi_flush(): $!";
}

sub cqi_read_byte () {
  my $msg = $conn->getc();
  croak "cqi_read_byte(): $!"
    unless defined $msg;
  return unpack "C", $msg;
}

sub cqi_read_word () {
  my $msg;
  my $bytes_read = $conn->read($msg, 2);
  croak "cqi_read_word(): $!"
    unless defined $bytes_read and $bytes_read == 2;
  return unpack "N", "\x00\x00$msg"; # this should safely unpack an unsigned short
}

sub cqi_read_int () {
  my $msg;
  my $number;
  
  my $bytes_read = $conn->read($msg, 4);
  croak "cqi_read_word(): $!"
    unless defined $bytes_read and $bytes_read == 4;
  $number = unpack "N", $msg;   # unpack seems to default to unsigned
  $number = unpack "l", (pack "L", $number); # convert unsigned 32bit to internal signed int *phew*
  return $number;
}

sub cqi_read_string () {
  my ($msg, $len, $bytes_read);
  $len = cqi_read_word();
  $bytes_read = $conn->read($msg, $len);
  croak "cqi_read_string(): $!"
    unless defined $bytes_read and $bytes_read == $len;
  return $msg;
}

sub cqi_read_byte_list() {
  my ($i, $len, @list);
  $len = cqi_read_int();
  for ($i = $len; $i > 0; $i--) {
    push @list, cqi_read_byte;
  }
  return @list;
}

sub cqi_read_word_list() {
  my ($i, $len, @list);
  $len = cqi_read_int();
  for ($i = $len; $i > 0; $i--) {
    push @list, cqi_read_word();
  }
  return @list;
}

sub cqi_read_int_list() {
  my ($i, $len, @list);
  $len = cqi_read_int();
  for ($i = $len; $i > 0; $i--) {
    push @list, cqi_read_int();
  }
  return @list;
}

sub cqi_read_string_list() {
  my ($i, $len, @list);
  $len = cqi_read_int();
  for ($i = $len; $i > 0; $i--) {
    push @list, cqi_read_string();
  }
  return @list;
}

sub cqi_read_int_table() {
  my $rows = cqi_read_int();
  my $columns = cqi_read_int();
  my @table = ();
  for (my $i = 0; $i < $rows; $i++) {
    my @line = ();
    for (my $j = 0; $j < $columns; $j++) {
      push @line, cqi_read_int();
    }
    push @table, [@line];
  }
  return @table;
}


1;

__END__


=head1 COPYRIGHT

Copyright (C) 1999-2010 Stefan Evert [http::/purl.org/stefan.evert]

This software is provided AS IS and the author makes no warranty as to
its use and performance. You may use the software, redistribute and
modify it under the same terms as Perl itself.

=cut
