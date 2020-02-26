#!/usr/bin/perl -w
# -*-cperl-*-
## A simple GUI front-end for CQP based on Perl/Tk and CQi

## Copyright (C) 1999-2001 IMS Stuttgart & Andreas Voegele


# Wichtige Routinen:
#
# create_main_window - Erzeugt das Hauptfenster.
# create_query_area - Erzeugt die Widgets fuer den Eingabebereich
# create_output_area - Erzeugt die Auswahlliste und den Kontextausgabebereich.
# create_freq_dialog - Erzeugt das Haeufigkeitsfenster.
# execute_query - Wird aufgerufen, wenn "Start query" betaetigt wird.
# show_matches - Gibt die gefundenen Textstellen im Auswahlfenster aus.
# switch_corpus - Wird aufgerufen, wenn ein Corpus ausgewaehlt wird.
# copy_match_to_work_area - Wird nach der Auswahl eines Datensates aufgerufen.
# set_status_message - Gibt einen Text in der Statuszeile aus.
# clear_query_area - Loescht den Inhalt des Eingabebereichs.
# clear_output_area -  Loescht den Inhalt des Ausgabebereichs.
# get_frequency_single - Ermittelt die Haeufigkeit.
# get_frequency_pair - Ermittelt die Haeufigkeit.
# get_frequency_grouped - Ermittelt die Haeufigkeit.

use strict;
use warnings;

use Tk;
require Tk::Adjuster;
require Tk::Balloon;
require Tk::Dialog;
require Tk::ItemStyle;
require Tk::Tree;
require Tk::ROText;

package Tkwic;

use CWB::CQI;
use CWB::CQI::Client;
use CWB::CQI::Server;
use File::Basename;

my $Corpora = [];

my $InfoAttr =
  {
   "DICKENS" => [[qw(novel_title s)], [qw(chapter_num s)]],
   "BUNDESTAG" => [[qw(sitzung_datum s)], [qw(redner_name s)]],
   "BROWN" => [[qw(cat_name s)], [qw(file_id s)]],
   "BNC" => [[qw(text_id s)], [qw(text_genre s)]],
  };
foreach my $lang (qw(EN DE FR ES IT NL)) {
  $InfoAttr->{"EUROPARL-$lang"} = [[qw(text_date s)], [qw(speaker_name s)], [qw(speaker_language s)]];
}

my $NumberOfQueryEntries = 1;
my $QueryEntryHeight = 6;
my $ContextWidgetContextSize = 2;
my $ContextWidgetHeight = 8;
my $MatchWidgetContextSize = 0; # number of _additional_ sentences shown around match
my $MatchWidgetHeight = 17;
my $MatchWidgetWidth = 80;
my $NumberOfDisplayedMatches = 50;
my $ManageNounChunksButton = 0;
my $SortCorporaAlphabetically = 1;
my $CorpusDialog_ListWidth = 36;
my $CorpusDialog_ListHeight = 24;
my $DefaultCutoff = 1;
my $FreqDistribDialog_MaxNumberOfResults = 10000;
my $FreqDistribDialog_ListWidth = 48;
my $FreqDistribDialog_ListHeight = 24;
my $TagHelpWidgetWidth = 40;
my $TagHelpWidgetHeight = 24;
my $MaxHistorySize = 20;
my $BackgroundColor = 'white';
my $SelectedQueryColor = 'light yellow';
my $ArgumentColor = 'red';
my $MatchColor = 'blue';
my $POSColor = 'dim gray';
my $LemmaColor = 'dim gray';
my $NCColor = 'dark green';
my $InfoColor = 'dark green';
my $Font = '-*-helvetica-medium-r-normal--14-140-*-*-*-*-iso8859-1';

my $FileTypes = [["Text Files", ['.txt', '.text']],
                 ["All Files", "*"]];

# if (@ARGV > 0) {
#     $Font = '-*-helvetica-medium-r-normal--20-*-*-*-*-*-iso8859-1';
#     $MatchWidgetHeight = 14;
#     $MatchWidgetWidth = 70;
#     $ContextWidgetHeight = 6;
#     $NumberOfQueryEntries = 3;
# }

my $Apptitle;
my $UseTextWidget = ($QueryEntryHeight > 1);
my $NormalBackgroundColor;
my $NormalForegroundColor;

sub new {
    my ($class, %args) = @_;
    
    my $self = {};
    bless $self, $class;

    $self->{show_word} = 1;
    $self->{show_pos} = 0;
    $self->{show_lemma} = 0;
    $self->{show_noun_chunks} = 1;
    $self->{connected} = 0;
    $self->{corpus} = '';
    $self->{positional_attributes} = [];
    $self->{structural_attributes} = [];
    $self->{query_history} = [];

    my $main_window = $self->create_main_window();

    $main_window->bind('<Visibility>', [\&_visibility_cb, $self]);

    return $self;
}

sub _visibility_cb {
    my ($widget, $self) = @_;

    my $main_window = $self->{main_window};
    $main_window->bind('<Visibility>', '');
    $main_window->afterIdle([\&connect, $self]);
}

sub busy {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    $main_window->Busy(-recurse => 1);
    my @kids = $main_window->children();
    foreach (@kids) {
        if ($_->class() eq 'Toplevel') {
            # Wenn man hier Busy() verwendet, kann es passieren, dass
            # einige Fenster trotz Unbusy() gesperrt bleiben. Damit
            # der Benutzer trotzdem ein Feedback bekommt, wird eine
            # Uhr als Mauszeiger verwendet.
            #$_->Busy(-recurse => 1);
            $_->configure(-cursor => 'watch');
        }
    }
}

sub unbusy {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    my @kids = $main_window->children();
    foreach (@kids) {
        if ($_->class() eq 'Toplevel') {
            #$_->Unbusy();
            $_->configure(-cursor => 'left_ptr');
        }
    }
    $main_window->Unbusy();
}

sub bell {
    my ($self) = @_;

    $self->{main_window}->bell();
}

sub connect {
    my ($self) = @_;

    $self->busy();
    $self->set_status_message("Connecting to server");
    my ($host, $port, $user, $passwd);
    if (@ARGV) {
        unless (@ARGV >=2 and @ARGV <= 4) {
          print STDERR "Usage:  Tkwic.perl [<user> <password> [<host> [<port>]]]\n";
          exit 1;
        }
        $user = shift @ARGV;
        $passwd = shift @ARGV;
        $host = (@ARGV) ? shift @ARGV : "localhost";
        $port = (@ARGV) ? shift @ARGV : $CWB::CQI::PORT;
        $self->set_status_message("Connecting to $host on port $port");
        cqi_connect($user, $passwd, $host, $port);
    }
    else {
        # if $CQI_HOST is not set, start local server
        unless (cqi_server_available()) {
          print STDERR "CQPserver binary is not available on local machine, please specify login details for remote server.\n";
          print STDERR "Usage:  Tkwic.perl <user> <password> [<host> [<port>]]\n";
          exit 1;
        }
        ($user, $passwd, $host, $port) = cqi_server();
        $self->set_status_message("Connecting to $host on port $port");
        cqi_connect($user, $passwd, $host, $port);
    }
    $self->{connected} = 1;
    $Corpora = $self->get_corpora();
    # Use the first corpus by default.
    $self->{corpus} = $Corpora->[0];
    $self->switch_corpus();
    $self->set_status_message("Connected to $host on port $port");
    $self->unbusy();
}

sub disconnect {
    my ($self) = @_;

    $self->busy();
    if ($self->{connected}) {
        $self->set_status_message("Disconnecting");
        cqi_bye();
        $self->{connected} = 0;
    }
    $self->unbusy();
}

sub get_corpora {
    my ($self) = @_;
    my @list;

    foreach (cqi_list_corpora()) {
        push @list, $_;
    }
    if ($SortCorporaAlphabetically) {
        @list = sort @list;
    }
    return \@list;
}

sub get_query {
    my ($self) = @_;

    my $query;
    if ($UseTextWidget) {
        $query = $self->{current_query_entry}->get('1.0', 'end');
    } else {
        $query = $self->{current_query_entry}->get();
    }
    # Strip spaces from the begin and end of the query.
    $query =~ s/^\s*//;
    $query =~ s/\s*$//;
    return $query;
}

sub add_query_to_history {
    my ($self, $query) = @_;
    my ($history, $i);
    
    $history = $self->{query_history};
    $i = scalar @$history;
    while ($i > 0) {
        $i--;
        if ($history->[$i] eq $query) {
            splice @$history, $i, 1;
            last;
        }
    }
    if (scalar @$history >= $MaxHistorySize) {
        pop @$history;
    }
    unshift @$history, ($query);
    $self->update_history_menu();
}

sub execute_query {
    my ($self) = @_;
    my ($corpus, $query, $status, $size, @match, @matchend);

    $self->busy();
    $corpus = $self->{corpus};
    $query = $self->{query} = $self->get_query();
    if ($query) {
        $self->set_status_message("Executing query");
        $status = cqi_query($corpus, "A", $query);
        if ($status == $CWB::CQI::STATUS_OK) {
            $self->add_query_to_history($query);
            $self->clear_output_area();
            $size = $self->{query_size} = cqi_subcorpus_size("$corpus:A");
            if ($size > 0) {
                @match = cqi_dump_subcorpus("$corpus:A", 'match', 0, $size-1);
                @matchend = cqi_dump_subcorpus("$corpus:A", 'matchend', 0, $size-1);
                $self->{query_matchref} = \@match;
                $self->{query_matchendref} = \@matchend;
                $self->show_matches(1);
                $self->set_status_message("Done");
            } else {
                $self->set_status_message("No match");
            }
            cqi_drop_subcorpus("$corpus:A");
        } else {
            $self->set_status_message("Query failed", 'ERROR');
        }
    } else {
        $self->set_status_message("Cannot execute empty query", 'ERROR');
    }
    $self->unbusy();
}

sub print_kwic_line {
    my ($self, $match, $matchend) = @_;
    my ($hlist, $lb, $rb, $list_ref, $lists);

    $hlist = $self->{output_list};
    ($lb, $rb) = $self->get_boundaries($match, $matchend,
                                       $MatchWidgetContextSize);
    my $e = $hlist->addchild('', -data => [$match, $matchend]);

    # get the left context
    $list_ref = $self->get_data('word', $lb .. $match-1);
    $hlist->itemCreate($e, 0, -text => join(' ', @$list_ref),
                       -style => $self->{style_justify_right});

    # show match with selected attributes -> collect lists into table and
    # 'transpose' it
    $lists = [];
    push @$lists, $self->get_data('word', $match .. $matchend)
      if $self->{show_word};
    push @$lists, $self->get_data('pos', $match .. $matchend)
      if $self->{show_pos};
    push @$lists, $self->get_data('lemma', $match .. $matchend)
      if $self->{show_lemma};
    $lists = $self->transpose_table($lists);
    $list_ref = [map {join("/", @$_)} @$lists];
    $hlist->itemCreate($e, 1, -text => join(' ', @$list_ref),
                       -style => $self->{style_match});

    # get the right context
    $list_ref = $self->get_data('word', $matchend+1 .. $rb);
    $hlist->itemCreate($e, 2, -text => join(' ', @$list_ref));
}

# transpose table (= reference to list of listrefs)
sub transpose_table {
    my ($self, $table) = @_;
    if (@$table == 0) {
        return [];              # empty table
    }
    my @trans = ();              # build transposed table 
    my $tlines = @{$table->[0]}; # no of lines of transp. table == no of cols of original table
    for (my $i = 0; $i < $tlines; $i++) {
        my @tline = ();         # line of the transposed table
        foreach my $line (@$table) {
            push @tline, $line->[$i];
        }
        push @trans, [@tline];
    }
    return [@trans];
}

sub center_list {
    my ($self) = @_;
    my ($hlist, $hlist_width, $lc_width, $mat_width, $rc_width, $entry_width,
        $fraction);

    $hlist = $self->{output_list};
    $hlist_width = $hlist->width();
    $lc_width = $hlist->columnWidth(0);
    $mat_width = $hlist->columnWidth(1);
    $rc_width = $hlist->columnWidth(2);
    $entry_width = $lc_width + $mat_width + $rc_width;
    if ($entry_width == 0) {
        $fraction = 0;
    } else {
        $fraction = ($lc_width - ($hlist_width - $mat_width) / 2)
          / $entry_width;
    }
    $hlist->update();
    $hlist->xview(moveto => $fraction);
}

sub show_matches {
    my ($self, $first) = @_;
    my ($size, $last, $matchref, $matchendref);

    if ($first < 1) {
        $first = 1;
    }
    $size = $self->{query_size};
    $last = $first + $NumberOfDisplayedMatches - 1;
    if ($last > $size) {
        $last = $size;
    }
    # always re-create kwic display (in case selected attributes were changed)
    #    if ($first ne $self->{query_first} || $last ne $self->{query_last}) {
        $self->clear_output_list();
        $self->{query_first} = $first;
        $self->{query_last} = $last;
        my $prev_state = 'disabled';
        my $next_state = 'disabled';
        if ($first > 1) { $prev_state = 'normal'; }
        if ($last < $size) { $next_state = 'normal'; }
        $self->{show_prev_matches_button}->configure(-state => $prev_state);
        $self->{show_next_matches_button}->configure(-state => $next_state);
        #$self->clear_output_text();
        $self->{main_window}->update();
        $matchref = $self->{query_matchref};
        $matchendref = $self->{query_matchendref};
        for (my $i = $first - 1; $i < $last; $i++) {
            $self->print_kwic_line($matchref->[$i], $matchendref->[$i]);
        }
        $self->center_list();
    #    }
    
    # no selected kwic line -> erase context window
    $self->copy_match_to_work_area();
}

sub show_prev_matches {
    my ($self) = @_;

    $self->busy();
    $self->show_matches($self->{query_first} - $NumberOfDisplayedMatches);
    $self->unbusy();
}

sub show_next_matches {
    my ($self) = @_;

    $self->busy();
    $self->show_matches($self->{query_first} + $NumberOfDisplayedMatches);
    $self->unbusy();
}

sub create_main_window {
    my ($self) = @_;

    my $main_window = $self->{main_window} = MainWindow->new();
    $Apptitle = $main_window->title();
    $main_window->protocol('WM_DELETE_WINDOW', [\&file_exit, $self]);
    $main_window->optionAdd('*Entry.background' => $BackgroundColor);
    $main_window->optionAdd('*Text.background' => $BackgroundColor);
    $main_window->optionAdd('*ROText.background' => $BackgroundColor);
    $main_window->optionAdd('*HList.background' => $BackgroundColor);
    $main_window->optionAdd('*font' => $Font);

    $self->{balloon} = $main_window->Balloon(-state => 'balloon',
                                             -initwait => 500);
    my $menu_bar = $self->create_menu_bar();
    my $corpus_area = $self->create_corpus_area();
    my $query_area = $self->create_query_area();
    my $output_area = $self->create_output_area();
    my $status_area = $self->create_status_area();
    $menu_bar->grid(-sticky => 'ew');
    $corpus_area->grid(-sticky => 'ew');
    $query_area->grid(-sticky => 'ew');
    $output_area->grid(-sticky => 'nsew');
    $status_area->grid(-sticky => 'ew');
    $main_window->gridColumnconfigure(0, -weight => 1);
    $main_window->gridRowconfigure(3, -weight => 1);
    return $main_window;
}

sub _get_menu_title {
    my ($self, $text) = @_;

    my $underline = index $text, '_';
    if ($underline >= 0) {
        $text =~ s/_//o;
    }
    return ($text, $underline);
}

sub _add_menu_item {
    my ($self, $menutitle, $itemtitle, $accelerator, $command) = @_;

    my $menu = $self->{menu_widgets}{$menutitle};
    if ($itemtitle eq '-') {
        $menu->separator();
    } else {
        my ($label, $underline) = $self->_get_menu_title($itemtitle);
        $menu->command(-label => $label, -underline => $underline,
                       -accelerator => $accelerator, -command => $command);
    }
}

sub create_menu_bar {
    my ($self) = @_;

    my @menus = ({title => "_File",
                  items => [{title => "Save _Matches...",
                             command => \&file_save_matches},
                            {title => "Save Matches With _Context...",
                             command => \&file_save_matches_with_context},
                            {title => '-'},
                            {title => "_Quit",
                             command => \&file_exit}]},
                 {title => "_Edit",
                  items => [{title => "Clear Query",
                             command => \&clear_query}]},
                 {title => "_Tools",
                  items => [{title => "_Frequency Distributions...",
                             command => \&tools_frequency_distributions}]},
                 {title => "_Help",
                  items => [{title => "Available _Tags...",
                             command => \&help_available_tags},
                            {title => "Inf_o...",
                             command => \&help_about}]});
    my $main_window = $self->{main_window};
    my $menu_bar = $main_window->Frame(-relief => 'raised',
                                       -borderwidth => '2');
    for (my $i = 0, my $last = $#menus; $i <= $last; $i++) {
        my $menutitle = $menus[$i]->{title};
        my ($text, $underline) = $self->_get_menu_title($menutitle);
        my $menu = $menu_bar->Menubutton(-text => $text,
                                         -underline => $underline);
        $menu->pack(-side => ($i == $last) ? 'right' : 'left');
        $self->{menu_widgets}{$text} = $menu;
        my $itemsref = $menus[$i]->{items};
        foreach my $item (@$itemsref) {
            my $itemtitle = $item->{title};
            my $method = $item->{command};
            $self->_add_menu_item($text, $itemtitle, undef, [$method, $self]);
        }
    }
    return $menu_bar;
}

sub create_corpus_area {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    my $hbox = $main_window->Frame();
    my $corpus_label = $hbox->Label(-text => "Corpus:");
    my $corpus = $hbox->Label(-textvariable => \$self->{corpus},
                              -relief => 'sunken', -pady => 4,
                              -anchor => 'w');
    my $corpus_button = $hbox->Button(-text => "Select corpus...",
                                      -command => [\&show_corpus_dialog,
                                                   $self]);
    $corpus_label->grid($corpus, $corpus_button, -sticky => 'ew');
    $hbox->gridColumnconfigure(1, -weight => 1);
    return $hbox;
}

sub highlight_text {
    my ($self, $entry, $query, $pattern, $tag_name) = @_;
    my ($i, $j);

    while ($query =~ m/$pattern/g) {
        $j = pos($query);
        $i = $j - length $&;
        $entry->tagAdd($tag_name, "1.0+${i}chars", "1.0+${j}chars");
    }
}

sub set_query {
    my ($self, $entry, $query) = @_;

    if ($UseTextWidget) {
        $entry->delete('1.0', 'end');
        $entry->insert('end', $query);
        $self->highlight_text($entry, $query, '\$[0-9]', 'argument');
        $self->highlight_text($entry, $query, '@|</?[a-zA-Z0-9_]+>', 'info');
    } else {
        $entry->delete(0, 'end');
        $entry->insert('end', $query);
    }
}

sub get_positional_attributes {
    my ($self)  = @_;
    my ($corpus, @list);

    $corpus = $self->{corpus};
    @list = cqi_attributes($corpus, 'p');
    $self->{positional_attributes} = \@list;
}

sub has_positional_attribute {
    my ($self, $attribute)  = @_;
    return (0 < grep {$_ eq $attribute} @{$self->{positional_attributes}});
}

sub get_structural_attributes {
    my ($self)  = @_;
    my ($corpus, @list);

    $corpus = $self->{corpus};
    @list = cqi_attributes($corpus, 's');
    $self->{structural_attributes} = \@list;
}

sub has_structural_attribute {
    my ($self, $attribute)  = @_;
    return (0 < grep {$_ eq $attribute} @{$self->{structural_attributes}});
}

sub check_for_noun_chunks_attribute {
    my ($self)  = @_;

    my $button = $self->{noun_chunks_button};
    $button->configure(-state => $self->has_structural_attribute('nc')
                       ? 'normal' : 'disabled');
}

sub switch_corpus {
    my ($self)  = @_;
            
    #$self->clear_query_area();
    $self->clear_output_area();
    $self->get_positional_attributes();
    $self->get_structural_attributes();
    $self->check_for_noun_chunks_attribute();
    $self->update_freq_dialog();
    $self->update_tag_dialog();
    $self->set_status_message("Selected corpus ". $self->{corpus});
}

sub clear_query {
    my ($self) = @_;

    my $entry_widget = $self->{current_query_entry};
    $self->set_query($entry_widget, '');
    $entry_widget->focus();
};

sub copy_query {
    my ($self, $query_text) = @_;

    my $entry_widget = $self->{current_query_entry};
    $self->set_query($entry_widget, $query_text);
    $entry_widget->focus();
}

sub get_macros_from_file {
    my ($self, $filename) = @_;
    my @macros;

    if (open FILE, $filename) {
        my $state = 'none';

        while (<FILE>) {
            # Strip comments.  Lines that contain a quoted sharp are not
            # handled, so don't put comments into these lines.
            s/\#.*$// if !/([\'\"]).*\#.*\1/o;
            # Strip leading and trailing spaces.
            s/^\s*//o;
            s/\s*$//o;
            # Empty lines are skipped.
            next if !$_;
            if ($state eq 'macro') {
                # The end of the macro is reached if the line ends with a
                # semicolon.
                if (/;$/o) {
                    chop;
                    $state = 'none';
                }
                push(@{$macros[-1]->{body}}, $_) if $_;
            } else {
                my @list = /MACRO\s+(.*)\((.*)\)/o;
                if (@list) {
                    push @macros, {name => $list[0], body => []};
                    $state = 'macro';
                } else {
                    my $p = basename($0);
                    print STDERR "$p:$filename:$.: Syntax error near to `$_´\n";
                }
            }
        }
        close FILE;
    } else {
        my $p = basename($0);
        print STDERR "$p:$filename: Cannot open file\n";
    }
    return \@macros;
}

sub update_history_menu {
    my ($self) = @_;
    my ($menu, $history, $label);

    $menu = $self->{history_menu};
    $history = $self->{query_history};
    $menu->delete(1, 'end');
    foreach my $query (@$history) {
        $label = substr($query, 0, 40);
        $label =~ s/\n/ /go; 
        $menu->add('command', -label => $label,
                    -command => [\&copy_query, $self, $query]);
    } 
}

sub create_query_area {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    my $vbox = $main_window->Frame(-relief => 'groove', -borderwidth => 2);
    my $popup = $self->{query_popup} =
        $vbox->Menu(-menuitems => [['command' => "Clear query",
                                    -command => [\&clear_query, $self]]],
                    -tearoff => 0);
    my $query_entry;
    for (my $i = 0; $i < $NumberOfQueryEntries; $i++) {
        if ($UseTextWidget) {
            my $scrolled = $vbox->Scrolled('Text', -scrollbars => 'osoe',
                                           -height => $QueryEntryHeight,
                                           -wrap => 'word');
            $query_entry = $scrolled->Subwidget('scrolled');
            $query_entry->tagConfigure('argument', -foreground => $ArgumentColor);
            $query_entry->tagConfigure('info', -foreground => $InfoColor);
            $query_entry->bind('Tk::Text', '<3>', '');
            $scrolled->pack(-fill => 'x', -expand => 1);
        } else {
            $query_entry = $vbox->Entry();
            $query_entry->pack(-fill => 'x', -expand => 1);
        }
        $query_entry->bind('<FocusIn>', [ \&query_focus_in, $self, $i ]);
        $query_entry->bind('<Button-3>',
                           sub { $popup->Popup(-popover => 'cursor', -popanchor => 'nw'); });
        $self->{query_entry}[$i] = $query_entry;
    }
    $self->{query_entry}[0]->focus();
    my $hbox = $vbox->Frame();
    my $history_button =
        $hbox->Menubutton(-text => "History", -indicatoron => 1,
                          -relief => 'raised', -pady => 5);
    $self->{history_menu} = $history_button->menu;
    my $execute_query_button = $hbox->Button(-text => "Start query",
                                          -command => [\&execute_query, $self]);
    $history_button->grid($execute_query_button, -sticky => 'ew');
    $hbox->gridColumnconfigure(1, -weight => 1);
    $hbox->pack(-fill => 'x', -expand => 1);
    return $vbox;
}

sub query_focus_in {
    my ($widget, $self, $n) = @_;

    for (my $i = 0; $i < $NumberOfQueryEntries; $i++) {
        if ($i != $n) {
            $self->{query_entry}[$i]->configure(-background =>
                                                $BackgroundColor);
        }
    }
    $widget->configure(-background => $SelectedQueryColor);
    $self->{current_query_entry} = $widget;
}

sub clear_query_area {
    my ($self) = @_;
    for (my $i = 0; $i < $NumberOfQueryEntries; $i++) {
        $self->set_query($self->{query_entry}[$i], '');
    }
}

sub create_output_area {
    my ($self) = @_;

    my $main_window = $self->{main_window};

    my $vbox = $main_window->Frame(-relief => 'groove', -borderwidth => 2);

    # Word, POS etc. buttons
    my $hbox1 = $vbox->Frame(-relief => 'groove', -borderwidth => 2);
    my $command = [sub {$self->show_matches($self->{query_first});}];
    my $word_button = $hbox1->Checkbutton(-text => "Word",
                                          -variable => \$self->{show_word},
                                          -command => $command);
    my $pos_button = $hbox1->Checkbutton(-text => "POS",
                                         -variable => \$self->{show_pos},
                                         -command => $command);
    my $lemma_button = $hbox1->Checkbutton(-text => "Lemma",
                                           -variable => \$self->{show_lemma},
                                           -command => $command);
    my $noun_chunks_button = $hbox1->Checkbutton(-text => "Noun chunks",
                                        -variable => \$self->{show_noun_chunks},
                                        -command => $command);
    $self->{noun_chunks_button} = $noun_chunks_button;
    if ($ManageNounChunksButton) {
        $word_button->grid($pos_button, $lemma_button, $noun_chunks_button,
                           -sticky => 'ew');
    } else {
        $word_button->grid($pos_button, $lemma_button, -sticky => 'ew');
    }
    my ($columns, $rows) = $hbox1->gridSize();
    for (my $i = 0; $i < $columns; $i++) {
        $hbox1->gridColumnconfigure($i, -weight => 1);
    }
    $self->{balloon}->attach($hbox1, -balloonmsg =>
                             "Context settings");

    # List area
    my $scrolled1 = $vbox->Scrolled('HList', -scrollbars => 'osoe',
                                    -exportselection => 0,
                                    -itemtype => 'text',
                                    -columns => 3,
                                    -width => $MatchWidgetWidth,
                                    -height => $MatchWidgetHeight,
                                    -browsecmd => [\&copy_match_to_work_area, $self]);
    my $hlist = $self->{output_list} = $scrolled1->Subwidget('scrolled');
    my $bg = $hlist->cget('-background');
    $self->{style_justify_right} =
      $hlist->ItemStyle('text', -background => $bg, -anchor => 'e');
    $self->{style_match} =
      $hlist->ItemStyle('text', -background => $bg,
                        -foreground => $MatchColor);

    # Number of matches, navigation
    my $hbox2 = $vbox->Frame(-relief => 'groove', -borderwidth => 2);
    my $matches_label = $hbox2->Label(-text => "Matches:");
    my $matches = $hbox2->Label(-textvariable => \$self->{query_size},
                                -width => 7, -relief => 'sunken',
                                -pady => 4, -anchor => 'e');
    my $from_label = $hbox2->Label(-text => "Showing from:");
    my $from = $hbox2->Label(-textvariable => \$self->{query_first},
                             -width => 7, -relief => 'sunken', -pady => 4,
                             -anchor => 'e');
    my $to_label = $hbox2->Label(-text => "to:");
    my $to = $hbox2->Label(-textvariable => \$self->{query_last},
                           -width => 7, -relief => 'sunken', -pady => 4,
                           -anchor => 'e');
    my $prev = $hbox2->Button(-text => 'Previous matches',
                              -state => 'disabled',
                              -width => 16,
                              -command => [ \&show_prev_matches, $self ]);
    my $next = $hbox2->Button(-text => 'Next matches',
                              -state => 'disabled',
                              -width => 16,
                              -command => [ \&show_next_matches, $self ]);
    $self->{show_prev_matches_button} = $prev;
    $self->{show_next_matches_button} = $next;
    $matches_label->grid($matches, $from_label, $from, $to_label, $to, $prev,
                         $next, -sticky => 'ew');
    $hbox2->gridColumnconfigure(6, -weight => 1);
    $hbox2->gridColumnconfigure(7, -weight => 1);
    
    # Work area
    my $scrolled2 = $vbox->Scrolled('ROText', -scrollbars => 'osoe',
                                     -height => $ContextWidgetHeight,
                                     -wrap => 'word');
    my $text = $self->{output_text} = $scrolled2->Subwidget('scrolled');
    $text->bind('Tk::ROText', '<3>', '');

    # Text tags
    $text->tagConfigure('match', -foreground => $MatchColor);
    $text->tagConfigure('pos', -foreground => $POSColor);
    $text->tagConfigure('lemma', -foreground => $LemmaColor);
    $text->tagConfigure('nc', -foreground => $NCColor);

    # Geometry management
    $hbox1->pack(-fill => 'x');
    $scrolled1->pack(-fill => 'both', -expand => 1);
    $vbox->Adjuster()->packAfter($scrolled1);
    $hbox2->pack(-fill => 'x');
    $scrolled2->pack(-fill => 'both', -expand => 1);

    # Initialize the output area
    $self->clear_output_area();

    return $vbox;
}

sub get_boundaries {
    my ($self, $match, $matchend, $context_size) = @_;
    my ($corpus, $a, $sentence_number, $last_sentence, $lb, $rb, $dummy);

    $corpus = $self->{corpus};
    $a = "$corpus.s";
    $last_sentence = cqi_attribute_size($a) - 1;
    $sentence_number = cqi_cpos2struc($a, $match);
    if ($sentence_number >= 0) {
        $sentence_number -= $context_size;
        $sentence_number = 0 if $sentence_number < 0;
        ($lb, $dummy) = cqi_struc2cpos($a, $sentence_number);
    }
    else {
        $lb = $match; # no context if not in <s> region
    }
    $sentence_number = cqi_cpos2struc($a, $matchend);
    if ($sentence_number >= 0) {
        $sentence_number += $context_size;
        $sentence_number = $last_sentence 
          if $sentence_number > $last_sentence;
        ($dummy, $rb) = cqi_struc2cpos($a, $sentence_number);
    }
    else {
        $rb = $matchend;
    }
    return ($lb, $rb);
}

sub get_data {
    # type can be 'word', 'pos' or 'lemma'
    my ($self, $type, @cpos) = @_;

    my $corpus = $self->{corpus};
    my $a = "$corpus.$type";
    my @list = cqi_cpos2str($a, @cpos);
    return \@list;
}

sub get_noun_chunks {
    my ($self, @cpos) = @_;
    my ($corpus, @lb, @rb);

    $corpus = $self->{corpus};
    if ($self->has_structural_attribute('nc')) {
        @lb = cqi_cpos2lbound("$corpus.nc", @cpos);
        @rb = cqi_cpos2rbound("$corpus.nc", @cpos);
        return (\@lb, \@rb);
    }
    return (undef, undef);
}

sub get_info_attrs {
    my ($self, $match, $matchend) = @_;
    my ($corpus, $info, $info_attr_list, $info_struc, $name, $value);

    $corpus = $self->{corpus};
    $info = '';
    $info_attr_list = $InfoAttr->{$corpus};
    if (defined $info_attr_list) {
        foreach my $info_attr_pair (@$info_attr_list) {
            my ($info_attr, $type) = @$info_attr_pair;
            $value = '';
            if ($type eq 's') {
                $info_struc = cqi_cpos2struc("$corpus.$info_attr", $match);
                if ($info_struc >= 0) {
                    $value = cqi_struc2str("$corpus.$info_attr", $info_struc);
                    $value =~ s/(.*)attr="(.*)"/$1$2/o;
                }
            }
            elsif ($type eq 'p') {
                $value = cqi_cpos2str("$corpus.$info_attr", $match);
            }
            else {
                $value = "ERROR: INVALID TYPE '$type'";
            }
            $name = $info_attr;
            $name = ucfirst($name) if length($name) > 3;
            $info .= ", " if $info;
            $info .= $name . ": " . $value;
        }
    }
    return $info;
}

sub insert_lists {
    my ($self, $tagname, $cpos_ref, $word_ref, $pos_ref, $lemma_ref,
        $noun_chunks_lb_ref, $noun_chunks_rb_ref) = @_;

    my $text_widget = $self->{output_text};
    my $size = 0;
    if ($word_ref) {
        $size = scalar @$word_ref;
    } elsif ($pos_ref) {
        $size = scalar @$pos_ref;
    } elsif ($lemma_ref) {
        $size = scalar @$lemma_ref;
    }
    my $n = 0;
    while ($n < $size) {
        if ($n > 0) {
            $text_widget->insert('end', ' ');
        }
        if ($noun_chunks_lb_ref && $cpos_ref->[$n] == $noun_chunks_lb_ref->[$n]) {
            $text_widget->insert('end', "[", [ 'nc' ]);
        }
        if ($word_ref) {
            $text_widget->insert('end', $word_ref->[$n], [ $tagname ]);
        }
        if ($pos_ref) {
            if ($word_ref) {
                $text_widget->insert('end', '/');
            }
            $text_widget->insert('end', $pos_ref->[$n], [ 'pos' ]);
        }
        if ($lemma_ref) {
            if ($word_ref || $pos_ref) {
                $text_widget->insert('end', '/');
            }
            $text_widget->insert('end', $lemma_ref->[$n], [ 'lemma' ]);
        }
        if ($noun_chunks_rb_ref && $cpos_ref->[$n] == $noun_chunks_rb_ref->[$n]) {
            $text_widget->insert('end', "]", [ 'nc' ]);
        }
        $n++;
    }
    return $n;
}

sub output_context {
    my ($self, $type, @cpos) = @_;
    my ($word_ref, $pos_ref, $lemma_ref, $noun_chunks_lb_ref, $noun_chunks_rb_ref);

    if ($self->{show_word}) {
        $word_ref = $self->get_data('word', @cpos);
    }
    if ($self->{show_pos}) {
        $pos_ref = $self->get_data('pos', @cpos);
    }
    if ($self->{show_lemma}) {
        $lemma_ref = $self->get_data('lemma', @cpos);
    }
    if ($self->{show_noun_chunks}) {
        ($noun_chunks_lb_ref, $noun_chunks_rb_ref) = $self->get_noun_chunks(@cpos);
    }
    return $self->insert_lists($type, \@cpos, $word_ref, $pos_ref, $lemma_ref,
                               $noun_chunks_lb_ref, $noun_chunks_rb_ref);
}

sub copy_match_to_work_area {
    my ($self, $e) = @_;
    my ($hlist, $list_ref, $text_widget, $match, $matchend, $lb, $rb, $n);

    # call without 'selected line' ($e) to clear context window

    $self->busy();
    $hlist = $self->{output_list};
    $text_widget = $self->{output_text};

    if (not defined $e) {
        $self->clear_output_text();
        $self->unbusy();
        return;
    }

    $list_ref = $hlist->info('data', $e);
    $match = $list_ref->[0];
    $matchend = $list_ref->[1];
    ($lb, $rb) = $self->get_boundaries($match, $matchend,
                                       $ContextWidgetContextSize);
    $self->clear_output_text();
    $n = $self->output_context('context', $lb .. $match-1);
    if ($n > 0) { $text_widget->insert('end', ' '); }
    $n = $self->output_context('match', $match .. $matchend);
    if ($n > 0) { $text_widget->insert('end', ' '); }
    $n = $self->output_context('context', $matchend+1 .. $rb);
    $text_widget->see('match.first');
    $self->set_status_message($self->get_info_attrs($match, $matchend),
                              'INFO_ATTR');
    $self->unbusy();
}

sub clear_output_list {
    my ($self) = @_;

    $self->{output_list}->delete('all');
}

sub clear_output_text {
    my ($self) = @_;

    $self->{output_text}->delete('1.0', 'end');
}

sub clear_output_area {
    my ($self) = @_;

    $self->clear_output_list();
    $self->{query_size} = '';
    $self->{query_first} = '';
    $self->{query_last} = '';
    $self->{show_prev_matches_button}->configure(-state => 'disabled');
    $self->{show_next_matches_button}->configure(-state => 'disabled');
    $self->clear_output_text();
}

sub create_status_area {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    my $status_widget = $self->{status_widget} =
        $main_window->Label(-relief => 'sunken', -anchor => 'w');
    $NormalBackgroundColor = $status_widget->cget('-background');
    $NormalForegroundColor = $status_widget->cget('-foreground');
    return $status_widget;
}

sub set_status_message {
    my ($self, $msg, $code) = @_;
    if (!defined $code) { $code = '' };

    my $status_widget = $self->{status_widget};
    if ($code eq 'INFO_ATTR') {
        $status_widget->configure(-background => $BackgroundColor,
                                  -foreground => $InfoColor);
    } else {
        $status_widget->configure(-background => $NormalBackgroundColor,
                                  -foreground => $NormalForegroundColor);
    }
    $status_widget->configure(-text => $msg);
    $self->{main_window}->update();
    if ($code eq 'ERROR') {
        $self->bell();
    }
}

sub apply_corpus {
    my ($self, $close_window, $dialog, $hlist) = @_;

    my $e = $hlist->info('anchor');
    if ($e eq '') {
        $self->bell();
    } else {
        my $corpus = $hlist->info('data', $e);
        if ($close_window)  {
            $dialog->destroy();
        }
        if ($corpus ne $self->{corpus}) {
            $self->busy();
            $self->{corpus} = $corpus;
            $self->switch_corpus();
            $self->unbusy();
        }
    }
}

sub create_corpus_dialog {
    my ($self) = @_;

    my ($columns, $rows);
    my $main_window = $self->{main_window};
    my $dialog = $self->{corpus_dialog} = $main_window->Toplevel();
    $dialog->title("Corpora");
    my $scrolled = $dialog->Scrolled('HList', -scrollbars => 'osoe',
                                     -itemtype => 'text',
                                     -width => $CorpusDialog_ListWidth,
                                     -height => $CorpusDialog_ListHeight);
    my $hlist = $scrolled->Subwidget('scrolled');
    my @sorted_list = sort @$Corpora;
    foreach (@sorted_list) {
        my $e = $hlist->addchild('', -data => $_);
        $hlist->itemCreate($e, 0, -text => $_);
        if ($_ eq $self->{corpus}) {
            $hlist->anchorSet($e);
            $hlist->see($e);
        }
    }
    $hlist->configure(-command => [\&apply_corpus, $self, 1, $dialog, $hlist]);
    my $hbox = $dialog->Frame();
    my $ok = $hbox->Button(-text => "Ok", -command =>
                           [\&apply_corpus, $self, 1, $dialog, $hlist]);
    my $apply = $hbox->Button(-text => "Apply", -command =>
                              [\&apply_corpus, $self, 0, $dialog, $hlist]);
    my $close = $hbox->Button(-text => "Close",
                              -command => sub { $dialog->destroy(); });
    $ok->grid($apply, $close, -sticky => 'ew');
    ($columns, $rows) = $hbox->gridSize();
    for (my $i = 0; $i < $columns; $i++) {
        $hbox->gridColumnconfigure($i, -weight => 1);
    }
    $scrolled->grid(-sticky => 'nsew');
    $hbox->grid(-sticky => 'ew');
    $dialog->gridColumnconfigure(0, -weight => 1);
    $dialog->gridRowconfigure(0, -weight => 1);
    return $dialog;
}

sub show_corpus_dialog {
    my ($self) = @_;

    $self->busy();
    my $corpus_dialog = $self->{corpus_dialog};
    if (Tk::Exists($corpus_dialog)) {
        $corpus_dialog->deiconify();
        $corpus_dialog->raise();
    } else {
        $self->create_corpus_dialog();
    }
    $self->unbusy();
}

sub get_frequency_single {
    my ($self, $corpus, $subcorpus) = @_;

    my $ok = 0;
    my $hlist = $self->{freq_list};
    my $cutoff_freq = $self->{cutoff_freq};
    my $field1 = $self->{field1};
    my $attr1 = $self->{attr1};
    my $style1 = $self->{freq_list_style_freq};
    if (cqi_subcorpus_has_field($subcorpus, $field1)) {
        my @table = cqi_fdist($subcorpus, $cutoff_freq, "$field1.$attr1");
        foreach my $line (@table) {
            my ($id, $f) = @$line;
            my $str = cqi_id2str("$corpus.$attr1", $id);
            my $e = $hlist->addchild('');
            $hlist->itemCreate($e, 0, -text => $str);
            $hlist->itemCreate($e, 2, -text => $f, -style => $style1);
        }
        $ok = 1;
    } else {
        $self->set_status_message("Field '$field1' does not exist in subcorpus", 'ERROR');
    }
    return $ok;
}

sub get_frequency_pair {
    my ($self, $corpus, $subcorpus) = @_;

    my $ok = 0;
    my $hlist = $self->{freq_list};
    my $cutoff_freq = $self->{cutoff_freq};
    my $field1 = $self->{field1};
    my $attr1 = $self->{attr1};
    my $field2 = $self->{field2};
    my $attr2 = $self->{attr2};
    my $style1 = $self->{freq_list_style_freq};
    if (cqi_subcorpus_has_field($subcorpus, $field1)) {
        if (cqi_subcorpus_has_field($subcorpus, $field2)) {
            my @table = cqi_fdist($subcorpus, $cutoff_freq, "$field1.$attr1",
                                  "$field2.$attr2");
            foreach my $line (@table) {
                my ($id1, $id2, $f) = @$line;
                my $str1 = cqi_id2str("$corpus.$attr1", $id1);
                my $str2 = cqi_id2str("$corpus.$attr2", $id2);
                my $e = $hlist->addchild('');
                $hlist->itemCreate($e, 0, -text => $str1);
                $hlist->itemCreate($e, 1, -text => $str2);
                $hlist->itemCreate($e, 2, -text => $f, -style => $style1);
            }
            $ok = 1;
        } else {
            $self->set_status_message("Field '$field1' does not exist in subcorpus", 'ERROR');
        }
    } else {
        $self->set_status_message("Field '$field2' does not exist in subcorpus", 'ERROR');
    }
    return $ok;
}

sub get_frequency_grouped {
    my ($self, $corpus, $subcorpus) = @_;

    my $ok = 0;
    my $hlist = $self->{freq_list};
    my $cutoff_freq = $self->{cutoff_freq};
    my $field1 = $self->{field1};
    my $attr1 = $self->{attr1};
    my $field2 = $self->{field2};
    my $attr2 = $self->{attr2};
    my $style1 = $self->{freq_list_style_freq};
    if (cqi_subcorpus_has_field($subcorpus, $field1)) {
        if (cqi_subcorpus_has_field($subcorpus, $field2)) {
            my @table = cqi_fdist($subcorpus, 1, "$field1.$attr1", "$field2.$attr2");
            my %joint_f = ();           # $joint_f{$id1}{$id2} = $freq
            my %marg_f = ();            # $marg_f{$id1} = $marginal_freq
            foreach my $line (@table) {
                my ($id1, $id2, $f) = @$line;
                $joint_f{$id1}{$id2} = $f;
            }
            foreach my $id1 (keys %joint_f) {
                my $href = $joint_f{$id1};
                my $f = 0;
                foreach my $id2 (keys %$href) { $f += $joint_f{$id1}{$id2}; }
                $marg_f{$id1} = $f;
            }
            # bei Ausgabe den cutoff einsetzen: $cutoff_freq fuer marginal
            # freq. und 1 fuer joint freq.
            my @id1 = sort {$marg_f{$b} <=> $marg_f{$a}} grep {$marg_f{$_} >= $cutoff_freq} keys %marg_f;
            foreach my $id1 (@id1) {
                my $e = $hlist->addchild('');
                my $str = cqi_id2str("$corpus.$attr1", $id1);
                my $f = $marg_f{$id1};
                $hlist->itemCreate($e, 0, -text => $str);
                $hlist->itemCreate($e, 2, -text => $f, -style => $style1);
                my $href = $joint_f{$id1};      # schnellerer Zugriff auf verschachtelten Hash
                my @id2 = sort {$href->{$b} <=> $href->{$a}} grep {$href->{$_} >= 1} keys %$href;
                #printf "%-50s %6d\n", $str, $f;
                foreach my $id2 (@id2) {
                    my $e = $hlist->addchild('');
                    $str = cqi_id2str("$corpus.$attr2", $id2);
                    $f = $href->{$id2};
                    $hlist->itemCreate($e, 1, -text => $str);
                    $hlist->itemCreate($e, 2, -text => $f, -style => $style1);
                    #printf "%7s + %-40s %6d\n", '', $str, $f;
                }
            }
            $ok = 1;
        } else {
            $self->set_status_message("Field '$field1' does not exist in subcorpus", 'ERROR');
        }
    } else {
        $self->set_status_message("Field '$field2' does not exist in subcorpus", 'ERROR');
    }
    return $ok;
}

sub freq_size_warning {
    my ($self, $size) = @_;

    my $main_window = $self->{freq_dialog};
    my $text = "It will take a long time to load $size records?\n\nDo you want to continue?";
    my $continue = "Continue";
    my $cancel = "Cancel";
    my $dialog = $main_window->Dialog(-title => "Warning",
                                      -text => $text,
                                      -buttons => [ $continue, $cancel ],
                                      -default_button => $cancel);
    my $answer = $dialog->Show();
    $dialog->destroy();
    return ($answer eq $continue);
}

sub get_frequency {
    my ($self) = @_;

    $self->busy();
    my $hlist = $self->{freq_list};
    my $corpus = $self->{corpus};
    my $query = $self->get_query();
    if ($query) {
        my $status = cqi_query($corpus, "A", $query);
        if ($status == $CWB::CQI::STATUS_OK) {
            my $subcorpus = "$corpus:A";
            my $size = cqi_subcorpus_size($subcorpus);
            my $ok = 1;
            if ($size > $FreqDistribDialog_MaxNumberOfResults) {
                $ok = $self->freq_size_warning($size);
            }
            if ($ok) {
                $hlist->delete('all');
                my $type_number = $self->{type_number};
                if ($type_number == 0) {
                    $ok = $self->get_frequency_single($corpus, $subcorpus);
                } elsif ($type_number == 1) {
                    $ok = $self->get_frequency_pair($corpus, $subcorpus);
                } elsif ($type_number == 2) {
                    $ok = $self->get_frequency_grouped($corpus, $subcorpus);
                }
                cqi_drop_subcorpus($subcorpus);
                if ($ok) {
                    $self->set_status_message("Done");
                }
            }
        } else {
            $self->set_status_message("Query failed", 'ERROR');
        }
    } else {
        $self->set_status_message("Cannot execute empty query", 'ERROR');
    }
    $self->unbusy();
};

sub set_frequency_type {
    my ($self, $type_number) = @_;

    my $state;
    if ($type_number == 0) {
        $state = 'disabled';
    } else {
        $state = 'normal';
    }
    $self->{field2_menu}->configure(-state => $state);
    $self->{attr2_menu}->configure(-state => $state);
}

sub update_freq_dialog {
    my ($self) = @_;
    my ($freq_dialog, $attributes, $default_attribute, $attribute);

    $attributes = $self->{positional_attributes};
    $default_attribute = $attributes->[0];

    $freq_dialog = $self->{freq_dialog};
    if (Tk::Exists($freq_dialog)) {
        # If the corpus doesn't support the currently selected attributes the
        # option menus are reset.
        $attribute = $self->{attr1};
        if (!grep {$_ eq $attribute} @$attributes) {
            $self->{attr1} = $default_attribute;
        }
        $attribute = $self->{attr2};
        if (!grep {$_ eq $attribute} @$attributes) {
            $self->{attr2} = $default_attribute;
        }
        $self->{attr1_menu}->configure(-options => $attributes);
        $self->{attr2_menu}->configure(-options => $attributes);
    }
}

sub create_freq_dialog {
    my ($self) = @_;
    my ($attributes, $default_attribute);

    $attributes = $self->{positional_attributes};
    $default_attribute = $attributes->[0];

    my $main_window = $self->{main_window};
    my $dialog = $self->{freq_dialog} = $main_window->Toplevel();
    $dialog->title("Frequency distributions");

    my $box = $dialog->Frame(-relief => 'groove', -borderwidth => 2);
    my $type_label = $box->Label(-text => "Type:");
    my $display_var = "Single";
    $self->{type_number} = 0;
    my $type_menu = $box->Optionmenu(-options => [["Single", 0],
                                                  ["Pair", 1],
                                                  ["Grouped", 2]],
                                     -textvariable => \$display_var,
                                     -variable => \$self->{type_number},
                                     -command => [\&set_frequency_type,
                                                  $self]);
    $self->{cutoff_freq} = $DefaultCutoff;
    my $cutoff_label = $box->Label(-text => "Cut off:");
    my $cutoff_entry = $box->Entry(-textvariable => \$self->{cutoff_freq},
                                   -width => 5,
                                   -justify => 'right');
    $self->{cutoff_entry} = $cutoff_entry;
    $type_label->grid($type_menu, $cutoff_label, $cutoff_entry,
                      -sticky => 'w');

    $self->{field1} = "match";
    $self->{attr1} = $default_attribute;
    my $field1_label = $box->Label(-text => "Field 1:");
    my $field1_menu = $box->Optionmenu(-options => ["match",
                                                    "target",
                                                    "matchend"],
                                       -variable => \$self->{field1});
    my $attr1_label = $box->Label(-text => "Attribute 1:");
    my $attr1_menu = $box->Optionmenu(-options => $attributes,
                                      -variable => \$self->{attr1});
    $field1_label->grid($field1_menu, $attr1_label, $attr1_menu, -sticky => 'w');
    $self->{field1_menu} = $field1_menu;
    $self->{attr1_menu} = $attr1_menu;

    $self->{field2} = "matchend";
    $self->{attr2} = $default_attribute;
    my $field2_label = $box->Label(-text => "Field 2:");
    my $field2_menu = $box->Optionmenu(-options => ["match",
                                                    "target",
                                                    "matchend"],
                                       -variable => \$self->{field2});
    my $attr2_label = $box->Label(-text => "Attribute 2:");
    my @list;
    my $attr2_menu = $box->Optionmenu(-options => $attributes,
                                      -variable => \$self->{attr2});
    $field2_label->grid($field2_menu, $attr2_label, $attr2_menu, -sticky => 'w');
    $self->{field2_menu} = $field2_menu;
    $self->{attr2_menu} = $attr2_menu;

    my $start = $dialog->Button(-text => "Start",
                                -command => [\&get_frequency, $self]);

    my $scrolled = $dialog->Scrolled('HList', -scrollbars => 'osoe',
                                     -exportselection => 0,
                                     -itemtype => 'text',
                                     -width => $FreqDistribDialog_ListWidth,
                                     -height => $FreqDistribDialog_ListHeight,
                                     -header => 1,
                                     -columns => 3);
    my $hlist = $self->{freq_list} = $scrolled->Subwidget('scrolled');
    $hlist->headerCreate(0, -text => "Field 1");
    $hlist->headerCreate(1, -text => "Field 2");
    $hlist->headerCreate(2, -text => "Freq.");
    $hlist->columnWidth(2, -char => 7);
    my $bg = $hlist->cget('-background');
    $self->{freq_list_style_freq} =
      $hlist->ItemStyle('text', -background => $bg, -foreground => $InfoColor,
                        -anchor => 'e');

    my $close = $dialog->Button(-text => "Close",
                                -command => sub { $dialog->destroy(); });

    $box->grid(-sticky => 'ew');
    $start->grid(-sticky => 'ew');
    $scrolled->grid(-sticky => 'nsew');
    $close->grid(-sticky => 'ew');
    $dialog->gridColumnconfigure(0, -weight => 1);
    $dialog->gridRowconfigure(2, -weight => 1);

    $self->set_frequency_type(0);

    return $dialog;
}

sub update_tag_dialog {
    my ($self) = @_;
    my ($tag_dialog, $corpus, $hlist, $n, @tags, $id, $freq);

    $corpus = $self->{corpus};

    $tag_dialog = $self->{tag_dialog};
    if (Tk::Exists($tag_dialog)) {
        $hlist = $self->{tag_list};
        $hlist->delete('all');
        $self->{tag_example}->configure(-text => '');

        if ($self->has_positional_attribute('pos')) {
            $n = cqi_lexicon_size("$corpus.pos");
            if ($n > 0) {
                @tags = cqi_id2str("$corpus.pos", 0..$n-1);
                @tags = sort @tags;
                foreach (@tags) {
                    $id  = cqi_str2id("$corpus.pos", $_);
                    $freq = cqi_id2freq("$corpus.pos", $id);
                    my $e = $hlist->addchild('', -data => ''); # Example
                    $hlist->itemCreate($e, 0, -text => $_);    # Tag
                    $hlist->itemCreate($e, 1, -text => "($freq)"); # Comment
                }
            }
        }
    }
}

sub copy_tag_example {
    my ($self, $e) = @_;

    my $hlist = $self->{tag_list};
    my $example = $hlist->info('data', $e);
    $self->{tag_example}->configure(-text => $example);
}

sub create_tag_dialog {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    my $dialog = $self->{tag_dialog} = $main_window->Toplevel();
    $dialog->title("Tags");
    my $scrolled = $dialog->Scrolled('HList', -scrollbars => 'osoe',
                                     -exportselection => 0,
                                     -itemtype => 'text',
                                     -columns => 2,
                                     -width => $TagHelpWidgetWidth,
                                     -height => $TagHelpWidgetHeight,
                                     -browsecmd => [\&copy_tag_example,
                                                    $self]);
    $self->{tag_list} = $scrolled->Subwidget('scrolled');
    my $vbox = $dialog->Frame();
    my $example_label = $vbox->Label(-text => "Examples:");
    my $example = $vbox->Label(-relief => 'sunken', -anchor => 'w',
                               -background => $BackgroundColor,
                               -foreground => $InfoColor);
    $self->{tag_example} = $example;
    $example_label->grid($example, -sticky => 'ew');
    $vbox->gridColumnconfigure(1, -weight => 1);
    my $close = $dialog->Button(-text => 'Close',
                                -command => sub { $dialog->destroy(); });
    $scrolled->grid(-sticky => 'nsew');
    #$vbox->grid(-sticky => 'ew');
    $close->grid(-sticky => 'ew');
    $dialog->gridColumnconfigure(0, -weight => 1);
    $dialog->gridRowconfigure(0, -weight => 1);
    $self->update_tag_dialog();
    return $dialog;
}

sub file_exit {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    $self->disconnect();
    $main_window->destroy();
}

sub save_matches {
    my ($self, $filename, $with_context) = @_;
    my ($match, $matchend, $lb, $rb, $list_ref, $lists, $line, $line_count);

    my $loginname = getlogin();
    my ($username) = split(',', (getpwnam($loginname))[6]);
    my $date = localtime(time);
    my $corpus = $self->{corpus};
    my $fullname = cqi_full_name($corpus);
    my $query = $self->{query};
    $query =~ s/\n/ /go;
    my $size = $self->{query_size};
    my $context_length = 0;
    if ($with_context) {
        $context_length = 25;
    }
    my $context_tokens = $context_length / 5;
    my $corpus_size = cqi_attribute_size("$corpus.word");

    my $matchref = $self->{query_matchref};
    my $matchendref = $self->{query_matchendref};

    my $ruler = '-' x 74;

    $self->busy();
    if (open TO, '>' . $filename) {
        print TO "#$ruler\n";
        print TO "#\n";
        print TO "# User:    $loginname ($username)\n";
        print TO "# Date:    $date\n";
        print TO "# Corpus:  $fullname\n";
        print TO "# Size:    $size intervals/matches\n";
        print TO "# Context: $context_length characters left, $context_length characters right\n";
        print TO "#\n";
        print TO "# Query: $corpus; $query;\n";
        print TO "#$ruler\n";
        $line_count = 0;
        for (my $i = 0; $i < $size; $i++) {
            $line = '';
            $match = $matchref->[$i];
            $matchend = $matchendref->[$i];

            # get the left context
            if ($with_context) {
                #($lb, $rb) = $self->get_boundaries($match, $matchend, 1);
                $lb = $match - $context_tokens;
                $rb = $matchend + $context_tokens;
                $lb = ($lb >= 0) ? $lb : 0;
                $rb = ($rb < $corpus_size) ? $rb : $corpus_size - 1;
                $list_ref = $self->get_data('word', $lb .. $match-1);
                my $left_context = substr(join(' ', @$list_ref),
                                          -$context_length, $context_length);
                if ($left_context) {
                    $line = $line . sprintf("%*s ", $context_length,
                                            $left_context);
                }
            }

            # show match with selected attributes -> collect lists into table
            # and 'transpose' it
            $lists = [];
            push @$lists, $self->get_data('word', $match .. $matchend)
                if $self->{show_word};
            push @$lists, $self->get_data('pos', $match .. $matchend)
                if $self->{show_pos};
            push @$lists, $self->get_data('lemma', $match .. $matchend)
                if $self->{show_lemma};
            $lists = $self->transpose_table($lists);
            $list_ref = [map {join("/", @$_)} @$lists];
            my $match_text = join(' ', @$list_ref);
            $line = $line . "<" . $match_text . ">";

            # get the right context
            if ($with_context) {
                $list_ref = $self->get_data('word', $matchend+1 .. $rb);
                my $right_context = substr(join(' ', @$list_ref), 0,
                                           $context_length);
                if ($right_context) {
                    $line = $line . " " . $right_context;
                }
            }
            printf TO "%9d: %s\n", $match, $line;
            if (($line_count % 100) == 0 && $line_count > 0) {
                $self->set_status_message("Wrote $line_count of $size matches");
            }
            ++$line_count;
        }
        close TO;
        SetFilePermissions($filename);
        $self->set_status_message("Done");
    } else {
        $self->set_status_message("Cannot save $filename");
        $self->bell();
    }
    $self->unbusy();
}

sub file_save_matches {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    my $new_filename = $main_window->getSaveFile(-defaultextension => '.txt',
                                                 -filetypes => $FileTypes);
    $self->save_matches($new_filename, 0) if $new_filename;
    return 1;
}

sub file_save_matches_with_context {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    my $new_filename = $main_window->getSaveFile(-defaultextension => '.txt',
                                                 -filetypes => $FileTypes);
    $self->save_matches($new_filename, 1) if $new_filename;
    return 1;
}

sub tools_frequency_distributions {
    my ($self) = @_;

    $self->busy();
    my $freq_dialog = $self->{freq_dialog};
    if (Tk::Exists($freq_dialog)) {
        $freq_dialog->deiconify();
        $freq_dialog->raise();
    } else {
        $self->create_freq_dialog();
        $self->update_freq_dialog();
    }
    $self->unbusy();
}

sub help_available_tags {
    my ($self) = @_;

    $self->busy();
    my $tag_dialog = $self->{tag_dialog};
    if (Tk::Exists($tag_dialog)) {
        $tag_dialog->deiconify();
        $tag_dialog->raise();
    } else {
        $self->create_tag_dialog();
    }
    $self->unbusy();
}

sub help_about {
    my ($self) = @_;

    my $main_window = $self->{main_window};
    my $dialog = $main_window->Dialog(-title => "Info about " . $Apptitle,
                                      -text => $Apptitle . " " .
                                      "Version 2.1\n" .
                                      "Copyright (C) 2000-2001 IMS\n" .
                                      "Universit\344t Stuttgart");
    $dialog->Show();
    $dialog->destroy();
}

package main;

my $app = Tkwic->new();

MainLoop;


# Local Variables: 
# mode: perl
# cperl-indent-level: 4
# End: 
