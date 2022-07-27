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
 * 
 * @file
 * 
 * admin-home-ui.php: this file contains the code that structures the HTML of the admin control panel.
 * TODO rename to above
 * 
 */

/* first, process the various "actions" that the admin interface may be asked to perform */
require('../lib/admin-execute.php');
/* 
 * Note that the execute actions are zero-HTML: they call execute.php 
 * which builds an environment, then calls a function, then sets a Location header, then exits. 
 * 
 * If there is some abort, then they fall through to here, so that no action is taken and 
 * instead we just go back to this script's normal render of the interface. 
 */

require('../lib/environment.php');


/* include function library files */
require('../lib/general-lib.php');
require('../lib/sql-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');
require('../lib/cache-lib.php');
require('../lib/ceql-lib.php');
require('../lib/cqp.inc.php');
require('../lib/xml-lib.php');
require('../lib/upload-lib.php');
require('../lib/plugin-lib.php');
require('../lib/useracct-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/template-lib.php');

/* and include, especially, the interface forms for this screen */
require('../lib/admin-home-forms.php');
require('../lib/admin-cachecontrol-forms.php');


/* declare global variables */
$Config = NULL;


cqpweb_startup_environment(
	CQPWEB_STARTUP_DONT_CONNECT_CQP | CQPWEB_STARTUP_CHECK_ADMIN_USER, 
	RUN_LOCATION_ADM
	);



/* what ui: the user interface page to be displayed on the right-hand-side. */
$what_ui = ( isset($_GET["ui"]) ? $_GET["ui"] : 'showCorpora' );



echo print_html_header('CQPweb Admin Control Panel', 
                       $Config->css_path, 
                       array('cword', 'adminhome', 'corpus-name-highlight', 'attribute-embiggen', 'metadata-embiggen', 'user-quicksearch'));

?>

<table class="concordtable fullwidth">
	<tr>
		<td valign="top">

<?php



/* ******************* *
 * PRINT SIDE BAR MENU *
 * ******************* */

?>
<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">Menu</a></th>
	</tr>
</table>

<table class="concordtable fullwidth">

<?php
echo print_menurow_heading('Corpora');
echo print_menurow_index('showCorpora', 'Show corpora', $what_ui);
echo print_menurow_index('showUserCorpora', 'Show user corpora', $what_ui);
echo print_menurow_index('installCorpus', 'Install new corpus', $what_ui);
echo print_menurow_index('uploadArea', 'View upload area', $what_ui);
echo print_menurow_index('manageCorpusCategories', 'Manage corpus categories', $what_ui);

echo print_menurow_heading('Templates');
echo print_menurow_index('annotationTemplates', 'Annotation templates', $what_ui);
echo print_menurow_index('metadataTemplates', 'Metadata templates', $what_ui);
echo print_menurow_index('xmlTemplates', 'XML templates', $what_ui);
echo print_menurow_index('visualisationTemplates', 'Visualisation templates', $what_ui);

// echo print_menurow_heading('Uploads');
// echo print_menurow_index('newUpload', 'Upload a file', $what_ui);

echo print_menurow_heading('Users and privileges');
echo print_menurow_index('userAdmin', 'Manage users', $what_ui);
echo print_menurow_index('groupAdmin', 'Manage groups', $what_ui);
echo print_menurow_index('groupMembership', 'Manage group membership', $what_ui);
echo print_menurow_index('privilegeAdmin', 'Manage privileges', $what_ui);
echo print_menurow_index('userGrants', 'Manage user grants', $what_ui);
echo print_menurow_index('groupGrants', 'Manage group grants', $what_ui);

echo print_menurow_heading('Plugins');
echo print_menurow_index('pluginAdmin', 'Manage plugins', $what_ui);
echo print_menurow_index('viewInstallers', 'Show installer jobs', $what_ui);


echo print_menurow_heading('Frontend interface');
echo print_menurow_index('systemMessages', 'System messages', $what_ui);
echo print_menurow_index('skins', 'Skins and colours', $what_ui);
echo print_menurow_index('mappingTables', 'Mapping tables', $what_ui);
echo print_menurow_index('manageEmbeds', 'Embedded pages', $what_ui);

echo print_menurow_heading('Cache control');
echo print_menurow_index('queryCacheControl', 'Query cache', $what_ui);
echo print_menurow_index('dbCacheControl', 'Database cache', $what_ui);
echo print_menurow_index('restrictionCacheControl', 'Restriction cache', $what_ui);
echo print_menurow_index('subcorpusCacheControl', 'Subcorpus file cache', $what_ui);
echo print_menurow_index('freqtableCacheControl', 'Frequency table cache', $what_ui);
echo print_menurow_index('tempCacheControl', 'Temporary data', $what_ui);
echo print_menurow_index('fragmentCacheControl', 'Fragmentation check', $what_ui);

echo print_menurow_heading('Backend system');
echo print_menurow_index('manageProcesses', 'Manage SQL processes', $what_ui);
echo print_menurow_index('tableView', 'View an SQL table', $what_ui);
echo print_menurow_index('phpConfig', 'PHP configuration', $what_ui);
echo print_menurow_index('opcodeCache', 'PHP opcode cache', $what_ui);
echo print_menurow_index('publicTables', 'Public frequency lists', $what_ui);
echo print_menurow_index('systemSnapshots', 'System snapshots', $what_ui);
echo print_menurow_index('systemDiagnostics', 'System diagnostics', $what_ui);

echo print_menurow_heading('Usage Statistics');
echo print_menurow_index('corpusStatistics', 'Corpus statistics', $what_ui);
echo print_menurow_index('userStatistics', 'User statistics', $what_ui);
echo print_menurow_index('queryStatistics', 'Query statistics', $what_ui);
echo print_menurow_index('clearQueryHistory', 'Clear history', $what_ui);
echo print_menurow_heading('Exit');

?>

<tr>
	<td class="concordgeneral">
		<a id="linkExitHomepage" class="menuItem hasToolTip" href="../" data-tooltip="Go to a list of all corpora on the CQPweb system">
			Exit to CQPweb homepage
		</a>
	</td>
</tr>

</table>

		</td>
		<td valign="top">
		
<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable">
			CQPweb Admin Control Panel
		</th>
	</tr>
</table>

<?php




/* 
 * ==================
 * PRINT MAIN CONTENT
 * ==================
 */

switch($what_ui)
{
case 'showCorpora':
	do_adm_ui_showcorpora_system_admhome();
	break;
	
case 'showUserCorpora':
	do_adm_ui_showcorpora_user_admhome();
	break;
	
case 'installCorpus':
	do_adm_ui_installcorpus_unindexed();
	break;

case 'installCorpusIndexed':
	do_adm_ui_installcorpus_indexed();
	break;

case 'installCorpusDone':
	do_adm_ui_installcorpusdone();
	break;
	
case 'deleteCorpus':
	/* note - this never has a menu entry -- it must be triggered from showCorpora */
	do_adm_ui_deletecorpus();
	break;

case 'manageCorpusCategories':
	do_adm_ui_corpuscategories();
	break;
	
case 'annotationTemplates':
	do_adm_ui_annotationtemplates();
	break;
	
case 'metadataTemplates':
	do_adm_ui_metadatatemplates();
	break;
	
case 'xmlTemplates':
	do_adm_ui_xmltemplates();
	break;
	
case 'visualisationTemplates':
	do_adm_ui_visualisationtemplates();
	break;
		
case 'uploadArea':
	do_adm_ui_uploadarea();
	break;
	
case 'userAdmin':
	do_adm_ui_useradmin();
	break;

case 'userUnverified':
	do_adm_ui_usersearch_unverified();
	break;
	
case 'userSearch':
	do_adm_ui_usersearch_searchterm();
	break;

case 'userView':
	do_adm_ui_userview();
	break;
	
case 'userUploads':
	/* note - this never has a menu entry -- it must be triggered from one of the other user-admin pages */
	do_adm_ui_useruploads();
	break;
	
case 'userDelete':
	/* note - this never has a menu entry -- it must be triggered from one of the other user-admin pages */
	do_adm_ui_userdelete();
	break;
	
case 'groupAdmin':
	do_adm_ui_groupadmin();
	break;

case 'groupMembership':
	do_adm_ui_groupmembership();
	break;

case 'privilegeAdmin':
	do_adm_ui_privilegeadmin();
	break;

case 'editPrivilege':
	do_adm_ui_editprivilege();
	break;

case 'userGrants':
	do_adm_ui_usergrants();
	break;

case 'groupGrants':
	do_adm_ui_groupgrants();
	break;

case 'pluginAdmin':
	do_adm_ui_pluginadmin();
	break;

case 'viewInstallers':
	do_adm_ui_viewinstallers();
	break;

case 'systemMessages':
	do_adm_ui_systemannouncements();
	break;

case 'skins':
	do_adm_ui_skins();
	break;

case 'mappingTables':
	do_adm_ui_mappingtables();
	break;

case 'manageEmbeds':
	do_adm_ui_embeddedpages();
	break;

case 'queryCacheControl':
	printquery_querycachecontrol();
	break;
	
case 'dbCacheControl':
	printquery_dbcachecontrol();
	break;
	
case 'restrictionCacheControl':
	printquery_restrictioncachecontrol();
	break;
	
case 'subcorpusCacheControl':
	printquery_subcorpuscachecontrol();
	break;
	
case 'freqtableCacheControl':
	printquery_freqtablecachecontrol();
	break;
	
case 'tempCacheControl':
	printquery_tempcachecontrol();
	break;
	
case 'fragmentCacheControl':
	printquery_innodbfragmentation();
	break;
	
case 'manageProcesses':
	do_adm_ui_manageprocesses();
	break;
	
case 'tableView':
	do_adm_ui_tableview();
	break;
	
case 'phpConfig':
	do_adm_ui_phpconfig();
	break;

case 'opcodeCache':
	do_adm_ui_opcodecache();
	break;

case 'publicTables':
	do_adm_ui_publictables();
	break;

case 'systemSnapshots':
	do_adm_ui_systemsnapshots();
	break;

case 'systemDiagnostics':
	do_adm_ui_systemdiagnostics();
	break;

case 'corpusStatistics':
	do_adm_ui_statistic_corpus();
	break;

case 'userStatistics':
	do_adm_ui_statistic_user();
	break;

case 'queryStatistics':
	do_adm_ui_statistic_query();
	break;

case 'clearQueryHistory':
	do_adm_ui_historyclear();
	break;

/* special option for printing a message shown via GET */
case 'showMessage':
	do_ui_message();
	break;

default:
	?>
	
	<p class="spacer">&nbsp;</p>
	<p class="errormessage">
		Sorry, but that is not a valid menu option.
	</p>
	<p class="spacer">&nbsp;</p>
	
	<?php
	break;
}




/* finish off the page */

?>
		</td>
	</tr>
</table>
<?php

echo print_html_footer();

cqpweb_shutdown_environment();

/* ------------- * 
 * END OF SCRIPT * 
 * ------------- */

