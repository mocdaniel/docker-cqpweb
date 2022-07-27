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
 * This file contains the script for actions on queries saved by the user,
 * incl. categorised/saved/etc. (Collectively, "owned" queries.)
 * 
 */


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');


/* include defaults and settings */
require('../lib/environment.php');


/* include function files */
require('../lib/html-lib.php');
require('../lib/cqp.inc.php');
require('../lib/cache-lib.php');
require('../lib/query-lib.php');
require('../lib/db-lib.php');
require('../lib/xml-lib.php');
require('../lib/scope-lib.php');
require('../lib/concordance-lib.php');
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');





/* declare global variables */
$Corpus = $User = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP );


// TODO - change so as ot use camel case for sqAction


if (!isset($_GET['sqAction']))
	$script_action = 'get_save_name';
else
	$script_action = $_GET['sqAction'];



$qname = safe_qname_from_get();

if (!check_cached_query($qname))
	$script_action = 'query_name_error';



/* set a default "next" location..." */
$next_location = ""; /* IE, to the index page, since what's covered here is VERY various. */
/* cases are allowed to change this */

switch ($script_action)
{




case 'ready_to_save':

	if(!isset($_GET['saveScriptSaveName']))
		exiterror('No save name was specified!');
	
	$savename = $_GET['saveScriptSaveName'];

	if (preg_match('/\W/', $savename) || HANDLE_MAX_SAVENAME < strlen($savename))
	{
		$next_location = 'savequery.php?sqAction=save_name_error&qname=' . $qname;
		break;
	}
	/* check if a saved query with this savename exists */
	if (save_name_in_use($savename))
	{
		$next_location = 'savequery.php?sqAction=save_name_error&saveScriptNameExists='. $savename . '&qname=' . $qname; 
		break;
	}
	
	$newqname = qname_unique($Config->instance_name);

	if (false === ($new_query = copy_cached_query($qname, $newqname)))
		exiterror("Unable to copy query data for new saved query!");
	
	$new_query->user = $User->username;
	$new_query->save_name = $savename;
	$new_query->saved = CACHE_STATUS_SAVED_BY_USER;
	$new_query->set_time_to_now();
	$new_query->save();

	/* return to display of the query before "save" was selected. */
	$next_location = 'concordance.php?qname=' . $qname . '&'
		. url_printget(array(['theData', ''], ['redirect', ''], ['saveScriptSaveName', ''], ['sqAction', ''], ['qname',''], ['saveScriptNameExists','']));
	// what we want to pass through: page number, per page, program, concBerakdownAt.... maybe more ..... 
	/* TODO
	 * what we really want is to "catch" the kwic v line, per page and page number parameters from the initial pass.  (which would be specifided in the savequery-ui form. )
	 * and pass them on. Maybe, look for them in GET, and stick them in a "params" array before the switch? And add to the URL params everything from that array?
	 * So that in  savequery they ALWAYS get passed on. 
	 * (Anything else?)
	 */
	/* delete theData cos god knows how often it's been passed around; delete all the parameters to do with redirect.php and savequery.php */
	
	break;




case 'rename_saved':

	if(!isset($_GET['saveScriptSaveReplacementName']))
		exiterror('No replacement save name was specified!');

	$replacename = $_GET['saveScriptSaveReplacementName'];

	if (preg_match('/\W/', $replacename) || HANDLE_MAX_SAVENAME < strlen($replacename))
	{
		$next_location = 'savequery.php?sqAction=rename_error&qname=' . $qname; 
		break;
	}
	if (save_name_in_use($replacename))
	{
		$next_location = 'savequery.php?sqAction=rename_error&saveScriptNameExists='. $replacename . '&qname=' . $qname; 
		break;
	}
	
	if (false !== ($record = QueryRecord::new_from_qname($qname)))
	{
		$record->save_name = (string)$replacename;
		$record->save();
	}
	else
		exiterror("Cache record for the specified saved query (# $qname) could not be found.");

	$next_location = 'index.php?ui=savedQs';
	
	break;





case 'delete_saved':
	
	delete_cached_query($qname);
	
	/* this is a bit clumsy; should it apply across the board ? */
	$back_to = isset($_GET['backTo']) ? $_GET['backTo'] : 'index';
	switch ($back_to)
	{
	case 'cached' :
		$next_location = 'index.php?ui=cachedQueries';
		break;
	case 'saved' :
		$next_location = 'index.php?ui=savedQs';
		break;
	case 'index':
	default:
		$next_location = 'index.php';
		break;
	}
	
	break;









case 'binary_export':
	
	if (false === ($record = QueryRecord::new_from_qname($qname)))
		exiterror("No record could be found of the query you specified.");
	if (CACHE_STATUS_SAVED_BY_USER != $record->saved || ( ! $User->is_admin() && $User->username != $record->user) )
		exiterror("You can only download your own saved queries.");
	if (! $User->has_cqp_binary_privilege())
		exiterror("You do not have permission to access CQP binary files.");
	
	/* work out the binary filename to read from */
	if (false === ($path = cqp_file_path($qname)))
		exiterror("The requested CQP binary file could not be found on the system. ");
	
	/* out binary filename to send as ($corpus.$savename.cqpquery) */
	$send_name = $Corpus->name . '.' . $record->save_name . '.cqpquery';

	/* Send the file to browser */
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $send_name . '"');
	
	readfile($path);
	
	unset($next_location);
	
	break;






/* catquwery options, moved. */

case 'enterNewValue':
//shonky redirect
	$next_location = 'savequery.php?sqAction=enterNewValue&qname=' . $qname;
	break;


case 'createCategorisedQuery':
	
	/* first: look for default number */
	if(isset($_GET['defaultCat']) )
		$default_num = (int)$_GET['defaultCat'];
	else
		$default_num = -1;

	/* check there is a savename for the catquery and it contains no badchars */
	if (empty($_GET['categoriseCreateName']))
	{
		$next_location = 'savequery.php?sqAction=enterCategories&categoriseProblem=no_name&qname=' . $qname . (0 > $default_num ? '' : "&defaultCat=$default_num");
		foreach ($_GET as $kk => $vv)
			if (preg_match('/^cat_(\d+)$/', $kk, $m))
				$next_location .= '&' . $kk . '=' . $vv;
		//do_ui_categorise_enter_categories('no_name');
		break;
	}
	
	$savename = $_GET['categoriseCreateName'];

	if ( (! cqpweb_handle_check($savename)) || 100 < strlen($savename))
	{
		$next_location = 'savequery.php?sqAction=enterCategories&categoriseProblem=bad_names&qname=' . $qname. '&categoriseCreateName='. $savename . (0 > $default_num ? '' : "&defaultCat=$default_num");
		foreach ($_GET as $kk => $vv)
			if (preg_match('/^cat_(\d+)$/', $kk, $m))
				$next_location .= '&' . $kk . '=' . $vv;
		break;
	}
	
	/* make sure no catquery of that name already exists */
	if (save_name_in_use($savename))
	{
		$next_location = 'savequery.php?sqAction=enterCategories&categoriseProblem=name_exists&qname=' . $qname. '&categoriseCreateName='. $savename . (0 > $default_num ? '' : "&defaultCat=$default_num");
		foreach ($_GET as $kk => $vv)
			if (preg_match('/^cat_(\d+)$/', $kk, $m))
				$next_location .= '&' . $kk . '=' . $vv;
		break;
	}


	$cats = [];
	$default_cat = '';

	foreach ($_GET as $k => $v)
	{
		if (!preg_match('/^cat_(\d+)$/', $k, $m))
			continue;
		if (empty($v))
			continue;
		
		$test = $v;
		
		/* make sure there are no non-word characters in the name of each category */
		if ( (! cqpweb_handle_check($test) ) || 99 < strlen($test))
		{
			$next_location = 'savequery.php?sqAction=enterCategories&categoriseProblem=bad_names&qname=' . $qname. '&categoriseCreateName='. $savename . (0 > $default_num ? '' : "&defaultCat=$default_num");
			foreach ($_GET as $kk => $vv)
				if (preg_match('/^cat_(\d+)$/', $kk, $m))
					$next_location .= '&' . $kk . '=' . $vv;
			break 2;
		}
		/* make sure there are no categories that are the same */
		if (in_array($test, $cats))
		{
			$next_location = 'savequery.php?sqAction=enterCategories&categoriseProblem=cat_repeated&qname=' . $qname. '&categoriseCreateName='. $savename . (0 > $default_num ? '' : "&defaultCat=$default_num");
			foreach ($_GET as $kk => $vv)
				if (preg_match('/^cat_(\d+)$/', $kk, $m))
					$next_location .= '&' . $kk . '=' . $vv;
			break 2;
		}
		
		$cats[] = $test;
		
		if ($default_num == (int)$m[1])
			$default_cat = $test;
	}
	
	/* make sure there actually exist some categories */
	if (0 == count($cats) )
	{
		$next_location = 'savequery.php?sqAction=enterCategories&categoriseProblem=cat_repeated&qname=' . $qname. '&categoriseCreateName='. $savename . (0 > $default_num ? '' : "&defaultCat=$default_num");
		foreach ($_GET as $kk => $vv)
			if (preg_match('/^cat_(\d+)$/', $kk, $m))
				$next_location .= '&' . $kk . '=' . $vv;
		break;
	}
	
	
	$newqname = catquery_create($qname, $savename, $cats, $default_cat);
	
	$next_location = "concordance.php?qname=$newqname&program=categorise";
	
	break;
	

	
case 'updateCategorisationAndLeave':
case 'updateCategorisationAndNextPage':

	$update_map = [];
	foreach ($_GET as $key => $val)
	{
		if ( ! preg_match('/^cat_(\d+)$/', $key, $m))
			continue;
		$update_map[$m[1]] = $val;
		unset($_GET[$key]);
	}
	
	catquery_update($qname, $update_map);
	
	if ('updateCategorisationAndLeave' == $script_action)
		$next_location = "index.php?ui=categorisedQs";
	else
	{
		$inputs = url_printget(array(
			array('sqAction', ''),
			array('pageNo', (string)(isset($_GET['pageNo']) ? (int)$_GET['pageNo'] + 1 : 2)) 
			));
		
		$next_location = "concordance.php?program=categorise&$inputs";
	}
	break;
	
	
case 'noUpdateNewQuery':
	$next_location = "index.php";
	break;	

	
case 'separateQuery':
	catquery_separate($qname);
	$next_location = "index.php?ui=savedQs";
	break;
	
	
case 'addNewValue':
	if (isset($_GET['newCategory']))
		$new_cat = escape_sql($_GET['newCategory']);
	else
		exiterror('Critical parameter "newCategory" was not defined!');
	catquery_add_new_value($qname, $new_cat);
	$next_location = "index.php?ui=categorisedQs";
	break;

	
case 'deleteCategorisedQuery':
	catquery_delete($qname);
	$next_location = "index.php?ui=categorisedQs";
	break;
	





default:
	exiterror('Unrecognised scriptmode ');


} /* end of switch */





if (!empty($next_location))
	set_next_absolute_location($next_location);

cqpweb_shutdown_environment();

exit(0);




/* ---------- *
 * END SCRIPT *
 * ---------- */


