<?php
/*
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-today Andrew Hardie and contributors
 *
 * See http://cwb.sourceforge.net/cqpweb.php
 *
 * This file is part of CQPweb.
 * 
 * CQPweb is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * CQPweb is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


/**
 * @file
 * 
 * This file contains the code that renders 
 * various search screens and other front-page stuff 
 * (basically everything you access from the mainpage side-menu).
 *
 *
 * The main GET paramater for forms/URLs that access this script:
 *
 * ui - specify the type of query you want to pop up
 * 
 * Each different ui effectively runs a separate interface.
 * Some require additional GET parameters.
 */


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');


/* include function library files */
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/html-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/cache-lib.php');
require('../lib/scope-lib.php');
require('../lib/db-lib.php');
require('../lib/cqp.inc.php');
require('../lib/ceql-lib.php');
require('../lib/annotation-lib.php');
require('../lib/corpus-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/metadata-lib.php');
require('../lib/query-lib.php');
require('../lib/collocation-lib.php');
require('../lib/xml-lib.php');
require('../lib/multivariate-lib.php');
require('../lib/lgcurve-lib.php');

/* especially, include the functions for each type of query */
require('../lib/query-forms.php');
require('../lib/savedata-forms.php');
require('../lib/subcorpus-forms.php');
require('../lib/info-forms.php');


/* ------------ *
 * BEGIN SCRIPT *
 * ------------ */


$Corpus = $Config = $User = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);


if ($User->is_admin())
{
	require('../lib/template-lib.php');
	require('../lib/corpus-admin-forms.php');
}
else if (RUN_LOCATION_USERCORPUS == $Config->run_location)
	require('../lib/corpus-admin-forms.php');




/* =============================== *
 * initialise variables from $_GET *
 * =============================== */

/* ui: the query-type whose interface page is to be displayed on the right-hand-side. */
$what_ui = ( isset($_GET["ui"]) ? $_GET["ui"] : 'search' );
/* in the case of the index page, no UI parameter implies the default (search, i.e. standard query) */

/* NOTE: some particular do_ui_.* functions will demand other $_GET variables */




/* strip tags of the header, cos HTML is allowed here... */
echo print_html_header(strip_tags($Corpus->title . $Config->searchpage_corpus_name_suffix), 
                       $Config->css_path, 
                       array('cword', 'queryhome', 'metadata-embiggen'));

?>

<table class="concordtable fullwidth">
	<tr>
		<td valign="top">

<?php

/* =================== *
 * PRINT SIDE BAR MENU *
 * =================== */

?>
<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">Menu</a></th>
	</tr>
</table>

<table class="concordtable fullwidth">

<?php
echo print_menurow_heading('Corpus queries');
echo print_menurow_index('search', 'Standard query', $what_ui);
echo print_menurow_index('restrict', 'Restricted query', $what_ui);
echo print_menurow_index('lookup', 'Word lookup', $what_ui);
echo print_menurow_index('freqList', 'Frequency lists', $what_ui);
echo print_menurow_index('keywords', 'Keywords', $what_ui);
echo print_menurow_index('analyseCorpus', 'Analyse corpus', $what_ui);
if ($Corpus->access_level == PRIVILEGE_TYPE_CORPUS_FULL)
	echo print_menurow_index('export', 'Export corpus', $what_ui);

echo print_menurow_heading('Saved query data');
echo print_menurow_index('history', 'Query history', $what_ui);
echo print_menurow_index('savedQs', 'Saved queries', $what_ui);
echo print_menurow_index('categorisedQs', 'Categorised queries', $what_ui);
echo print_menurow_index('uploadQ', 'Upload a query', $what_ui);
echo print_menurow_index('subcorpus', 'Create/edit subcorpora', $what_ui);

echo print_menurow_heading('Corpus info');

/* note that most of this section is links-out, so we can't use the print-row function */

//TODO HEREDOCs might be neater??

/* SHOW CORPUS METADATA */
echo "<tr>\n\t<td class=\"";
if ($what_ui != "corpusMetadata")
	echo "concordgeneral\">\n\t\t<a id=\"menuCorpusMetadata\" class=\"menuItem hasToolTip\" href=\"index.php?ui=corpusMetadata\" "
		, "data-tooltip=\"View CQPweb's database of information about this corpus\">"
		;
else 
	echo "concordgrey\">\n\t\t<a class=\"menuCurrentItem\">";
echo "View corpus metadata</a>\n\t</td>\n</tr>\n";


/* print a link to a corpus manual, if there is one */
if (empty($Corpus->external_url))
{
	/* this placeholder irrelevant for user coprora. */
	if (!$Corpus->is_user_owned())
		echo '<tr><td class="concordgeneral"><a class="menuCurrentItem"><em>No corpus documentation available</em></a></tr></td>';
}
else
	echo '<tr><td class="concordgeneral"><a id="menuExternalDoc" target="_blank" class="menuItem hasToolTip" href="'
		, $Corpus->external_url , '" data-tooltip="Info on ' , escape_html($Corpus->title)
		, " on the web\">Corpus documentation</a></td></tr>\n"
		;


/* print a link to each tagset for which an external_url is declared in metadata */
foreach (get_all_annotation_info($Corpus->name) as $obj)
	if (!empty($obj->external_url))
		echo '<tr><td class="concordgeneral"><a id="menuTagset:', $obj->handle, '" target="_blank" class="menuItem hasToolTip" href="'
			, $obj->external_url
			, '" data-tooltip="', escape_html($obj->description), ': view documentation">' 
			, escape_html($obj->tagset)
			, "</a></td></tr>\n"
			;

if (RUN_LOCATION_USERCORPUS == $Config->run_location)
{
	if( !$User->is_admin())
		echo print_menurow_index('corpusSettings', 'Corpus settings', $what_ui);
	echo print_menurow_index('shareCorpus', 'Share corpus', $what_ui);
}
/* normal users won't get this in the admin block below */

/* these are the super-user options */
if ($User->is_admin())
{
	$prefix = (RUN_LOCATION_USERCORPUS == $Config->run_location) ? '../../../' : '';

	echo print_menurow_heading('Admin tools');
	?>

	<tr>
		<td class="concordgeneral">
			<a class="menuItem" href="<?php echo $prefix; ?>../adm">Admin control panel</a>
		</td>
	</tr>

	<?php

	echo print_menurow_index('corpusSettings', 'Corpus settings', $what_ui);
	echo print_menurow_index('manageAccess', 'Manage access', $what_ui);
	echo print_menurow_index('manageMetadata', 'Manage text metadata', $what_ui);
	echo print_menurow_index('manageCategories', 'Manage text categories', $what_ui);
	echo print_menurow_index('manageXml', 'Manage corpus XML', $what_ui);
	echo print_menurow_index('manageAnnotation', 'Manage annotation', $what_ui);
	echo print_menurow_index('manageAlignment', 'Manage parallel alignment', $what_ui);
	echo print_menurow_index('manageFreqLists', 'Manage frequency lists', $what_ui);
	echo print_menurow_index('manageVisualisation', 'Manage visualisations', $what_ui);
	if (! $Config->hide_experimental_features)
		echo print_menurow_index('addToCorpus', 'Add corpus data', $what_ui);
	echo print_menurow_index('viewSetupNotes', 'Corpus setup notes', $what_ui);
	echo print_menurow_index('cachedQueries', 'Cached queries', $what_ui);
	echo print_menurow_index('cachedDatabases', 'Cached databases', $what_ui);
	echo print_menurow_index('cachedFrequencyLists', 'Cached frequency lists', $what_ui);

} /* end of "if user is a superuser" */

/* all the rest is encapsulated */
echo print_menu_aboutblock($what_ui);


?>
</table>

		</td>
		<td valign="top">

<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable">
			<a class="menuHeaderItem">
				<?php echo $Corpus->title , $Config->searchpage_corpus_name_suffix, "\n"; ?>
			</a>
		</th>
	</tr>
</table>



<?php




/* ============================== *
 * PRINT MAIN SEARCH FORM CONTENT *
 * ============================== */


/*
 * This switch
 * (a) calls the appropriate interface function,
 * (b) sets the helplink to be the right thing.
 */
switch($what_ui)
{
case 'search':
	do_ui_search();
	display_system_messages();
	$helplink = 'standardq';
	break;

case 'restrict':
	do_ui_restricted();
	$helplink = 'restrictedq';
	break;

case 'lookup':
	do_ui_lookup();
	$helplink = false;
	break;

case 'freqList':
	do_ui_freqlist();
	$helplink = 'freqlist';
	break;

case 'keywords':
	do_ui_keywords();
	$helplink = 'keywords';
	break;

case 'analyseCorpus':
	do_ui_analysecorpus();
	$helplink = 'hello';
	break;

case 'lgcurve':
// TODO, note this is an unfinished UI issue... lgcurve should prob be wihtin Analyse Corpus but currently there's just a link. 
	do_ui_lgcurve();
	$helplink = 'hello';
	break;

case 'export':
	do_ui_export();
	$helplink = 'hello';
	break;

case 'history':
	do_ui_queryhistory();
	$helplink = 'queryhist';
	break;

case 'savedQs':
	do_ui_savedqueries();
	$helplink = 'savequery';
	break;

case 'categorisedQs':
	do_ui_catqueries();
	$helplink = 'categorise';
	break;

case 'uploadQ':
	do_ui_uploadquery();
	$helplink = 'qupload';
	break;

case 'subcorpus':
	do_ui_subcorpus();
	$helplink = 'subcorpora';
	break;

case 'corpusMetadata':
	do_ui_corpusmetadata();
	$helplink = 'hello';
	break;

case 'corpusSettings':
	do_ui_corpussettings();
	$helplink = 'hello';
	break;

case 'shareCorpus':
	do_ui_sharecorpus();
	$helplink = 'hello';
	break;

case 'manageAccess':
	do_ui_manageaccess();
	$helplink = 'hello';
	break;

case 'previewAccessStatement':
	do_ui_previewaccessstatement();
	$helplink = 'hello';
	break;

case 'manageMetadata':
	do_ui_managemeta();
	$helplink = 'hello';
	break;

case 'manageCategories':
	do_ui_managetextcats();
	$helplink = 'hello';
	break;

case 'manageXml':
	do_ui_managexml();
	$helplink = 'hello';
	break;

case 'manageAnnotation':
	do_ui_manageannotation();
	$helplink = 'hello';
	break;

case 'manageAlignment':
	do_ui_managealignment();
	$helplink = 'hello';
	break;

case 'manageFreqLists':
	do_ui_managefreqlists();
	$helplink = 'hello';
	break;

case 'manageVisualisation':
	do_ui_visualisation();
	do_ui_xmlvisualisation();
	// TODO each of these is a pretty heavy page. SPlit into 2 menu options.
	$helplink = 'hello';
	break;

case 'addToCorpus':
	do_ui_addtocorpus();
	$helplink = 'hello';
	break;

case 'viewSetupNotes':
	do_ui_viewsetupnotes();
	$helplink = 'hello';
	break;


case 'cachedQueries':
	do_ui_showquerycache();
	$helplink = 'hello';
	break;

case 'cachedDatabases':
	do_ui_showdbs();
	$helplink = 'hello';
	break;

case 'cachedFrequencyLists':
	do_ui_showfreqtables();
	$helplink = 'hello';
	break;

case 'who_the_hell':
	do_ui_credits();
	$helplink = 'hello';
	break;

case 'latest':
	do_ui_latest();
	$helplink = 'hello';
	break;

case 'bugs':
	do_ui_bugs();
	$helplink = 'hello';
	break;

case 'embed':
	do_ui_embed_page();
	$helplink = 'hello';
	break;
	


default:
	?>

	<p class="spacer">&nbsp;</p>
	<p class="errormessage">
		Sorry, but that is not a valid menu option.
	</p>
	<p class="spacer">&nbsp;</p>

	<?php
	$helplink = 'hello';
	break;
}



/* finish off the page */
?>

		</td>
	</tr>
</table>
<?php

echo print_html_footer($helplink);

cqpweb_shutdown_environment();



