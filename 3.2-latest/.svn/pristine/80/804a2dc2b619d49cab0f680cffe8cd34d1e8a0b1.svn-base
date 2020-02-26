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
 * Action script for doings related to user corpora.
 */



/* include defaults and settings */
require('../lib/environment.php');


/* library files */
require('../lib/useracct-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/cache-lib.php');
require('../lib/cqp.inc.php');
require('../lib/ceql-lib.php');
require('../lib/corpus-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/metadata-lib.php');
require('../lib/plugin-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/scope-lib.php');
require('../lib/upload-lib.php');
require('../lib/template-lib.php');
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/xml-lib.php');




//TODO - note that the script doesn't know when it's been called by ajax
// so bad script action still streams badck html.
// so perhaps envronment start sshould check for a request miie type - if json requested, use exiterror_json for any exiterror() call.


$User = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_USR);


if (!$Config->user_corpora_enabled)
	exiterror("The system for installing and using users' own corpus data is not switched on.");


$script_action = isset($_GET['ucAction']) ? $_GET['ucAction'] : false;

$next_location = "index.php";

switch ($script_action)
{

case 'userCorpusInstall':
	
	/* CHECK USER CAN INSTALL */
	if ($User->has_exceeded_user_corpus_disk_limit() && !$User->is_admin())
		exiterror_json('You do not have enough disk space to install a corpus.');
	
	
	/* SEND AN EMAIL WHEN DONE? */
	$send_an_email = ((!$Config->cqpweb_no_internet) && isset($_GET['emailReq']) && (bool)$_GET['emailReq']);
	
	
	/* collect install settings from GET */
	$install_info = [];
	
	/* RIGHT-TO-LEFT */
	$install_info['r2l'] = (isset($_GET['corpus_scriptIsR2L']) ? (bool)$_GET['corpus_scriptIsR2L'] : false);
	
	
	/* COLOUR SCHEME */
	if (!isset($_GET['colourScheme']))
		$_GET['colourScheme'] = 'blue';
	$install_info['css_path'] = '../css/CQPweb-' . preg_replace('/\W/', '', $_GET['colourScheme']) . '.css';
	if (! file_exists($install_info['css_path']))
		$install_info['css_path'] = '../css/CQPweb-blue.css';
	
	
	/* TITLE */
	if (empty($_GET['corpus_description']))
		exiterror_json("The corpus can't be installed if you don't provide a descriptive title!\n");
	$install_info['title'] = $_GET['corpus_description'];
	
	
	/* INPUT FILES */
	if (isset($_GET['includeFileArray']))
	{
		/* files were passed new-style as an encoded array. */
		$_GET['includeFileArray'] = array_map('urldecode', $_GET['includeFileArray']);
		$install_info['input_files'] = convert_uploaded_files_to_realpaths($_GET['includeFileArray'], $User->username);
	}
	else if (isset($_GET['includeFile']))
	{
		/* files were passed the old fashioned way - as multiple params of the same name */
		$array_of_files = list_parameter_values_from_http_query('includeFile', $_SERVER['QUERY_STRING']);
		/* the following is needed becasue filenames are being yanked from QUERY_STRING. */
		$array_of_files = array_map('urldecode', $array_of_files);
		$install_info['input_files'] = convert_uploaded_files_to_realpaths($array_of_files, $User->username);
	}
	if (empty($install_info['input_files']))
		exiterror_json("You must specify at least one file to include in the corpus!");
	
	
	/* PLUGIN TO USE */
	if (!isset($_GET['installer']))
		exiterror_json("No corpus installer plugin was specified!");
	$install_info['plugin_reg_id'] = (int)$_GET['installer'];
	if (false === get_plugin_info($install_info['plugin_reg_id']))
		exiterror_json("An invalid corpus installer plugin was specified.");
	
	
	/* run the script async (this will work in theory) */
	unset($next_location);
	disconnect_browser_and_continue(true);
	
	/* run the installer. */
	$err = '';
	if (false === ($new_c_id = install_user_corpus($User->username, $install_info, $err)))
	{
		error_log($err, 4); /* this will hopefully end up in the Apache log! */   // TODO print_debug_meassages, in disconnectedmode, should send to log. 
		if ($send_an_email)
			send_user_corpus_install_email('error', $User->username, $err);
	}
	else
		if ($send_an_email)
			send_user_corpus_install_email('complete',  $User->username, $new_c_id);
squawk("c_id is ". false === $new_c_id ? "FALSE" : $new_c_id);
	
	
	/* at this point we're all done (potentially a ton of hours later) */
	
	break;
	
	
case 'userCorpusDelete':
	
	$next_location = 'index.php?ui=showCorpora';
	
	if ( ! (isset($_GET['sureyouwantto']) && $_GET['sureyouwantto'] == 'yes') )
		break;
	
	if (! ($c_info = get_corpus_info_by_id(get_corpus_from_GET())))
		exiterror("That corpus does not exist.");
	
	if (!$User->username == $c_info->owner)
		exiterror("You don't own that corpus; you can't delete it.");
	
	if (!delete_user_corpus($User->username, $c_info->corpus))
		exiterror("Corpus deletion failed for an unknown reason.");
	
	break;
	
	
case 'dismissUserProcessRecord':
	
	if (empty($_GET['job']) || false === ($job = get_installer_process_info($_GET['job'])) )
		exiterror("Critical parameter missing or incorrect: job ID.");
	
	if ( ! ($User->id == $job->user_id || $User->is_admin()) )
		exiterror("You can't dismiss a process you don't own!");

	if ( ! (INSTALLER_STATUS_ABORTED == $job->status || INSTALLER_STATUS_DONE == $job->status))
		exiterror("Only processes that have COMPLETE or ABORTED status can be dismissed.");
	
	delete_installer_process($job->id);
	
	$next_location = 'index.php?ui=installCorpus';
	
	break;
	
	
case 'userCorpusMakeGrant':
	
	if (! ($c_info = get_corpus_info_by_id(get_corpus_from_GET())))
		exiterror("That corpus does not exist.");
	
	if (!isset($_GET['whither']) || '' === $_GET['whither'])
		exiterror("No colleaguate was specified.");
	
	if (!$User->username == $c_info->owner)
		exiterror("You don't own that corpus; you can't control access to it.");
	
	if (!empty($_GET['grantMsg']))
		grant_user_corpus_access($c_info->id, (int)$_GET['whither'], $_GET['grantMsg']);
	else
		grant_user_corpus_access($c_info->id, (int)$_GET['whither']);
	
	$next_location = "../usr/index.php?ui=viewSharedCorpora";

	break;
	
	
case 'userCorpusWithdrawGrant':
	
	if (! ($c_info = get_corpus_info_by_id(get_corpus_from_GET())))
		exiterror("That corpus does not exist.");
	
	if (!isset($_GET['whence']) || '' === $_GET['whence'])
		exiterror("No colleaguate was specified.");
	
	if (!$User->username == $c_info->owner)
		exiterror("You don't own that corpus; you can't control access to it.");
	
	ungrant_user_corpus_access($c_info->id, (int)$_GET['whence']);
	
	$next_location = "../usr/index.php?ui=viewSharedCorpora";
	
	break;
	
	
case 'userCorpusRejectGrant':
	
	if (! ($c_info = get_corpus_info_by_id(get_corpus_from_GET())))
		exiterror("That corpus does not exist.");
	
	ungrant_user_corpus_access($c_info->id, $User->id);
	
	$next_location = "../usr/index.php?ui=viewSharedCorpora";
	
	break;
	
	
	
default:

	/* dodgy parameter: ERROR out. */
	exiterror("A badly-formed user corpus action was requested!"); 
	break;
}


if (isset($next_location))
	set_next_absolute_location($next_location);

cqpweb_shutdown_environment();

exit(0);


/* ------------- *
 * END OF SCRIPT *
 * ------------- */

function get_corpus_from_GET()
{
	if (!isset($_GET['cId']))
		exiterror("No corpus specified!");
	return (int)$_GET['cId'];
}

