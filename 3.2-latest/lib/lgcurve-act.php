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
 * This file contains the script for actions affecting lexical growth curves: currenlty create, delete, download, 
 * but eventually this will involve generatign graphs direclty in R for display!
 * 
 * Most wokr is actually done by the clcourve.inc.php librayr funcs; this file just interprets the fomr & calls it.
 */
 
/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');


/* include all function files */
require('../lib/useracct-lib.php');
require('../lib/cqp.inc.php');
require('../lib/exiterror-lib.php');
require('../lib/html-lib.php');
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');
require('../lib/lgcurve-lib.php');


/* declare global variables */
$Corpus = NULL;

cqpweb_startup_environment( CQPWEB_STARTUP_DONT_CONNECT_CQP , RUN_LOCATION_CORPUS );




/* set a default "next" location..." */
$next_location = "index.php?ui=lgcurve";
/* cases are allowed to change this */

$script_action = isset($_GET['lgAction']) ? $_GET['lgAction'] : false; 


switch ($script_action)
{
	/*
	 * ================
	 * CLOCURVE ACTIONS
	 * ================
	 */


case 'generate':
	
	if (! isset($_GET['annotation'], $_GET['intervalWidth']))
		exiterror("Cannot generate lexical growth curve data: one or more critical parameters are missing.");
	$interval_width = (int) $_GET['intervalWidth'];
	
	if ( 'word' == $_GET['annotation'] || array_key_exists($_GET['annotation'], list_corpus_annotations($Corpus->name)) )
		$annotation = $_GET['annotation'];
	else
		exiterror("Cannot generate lexical growth curve data for the annotation specified, as it does not exist.");
	
	create_lgcurve($Corpus->name, $annotation, $interval_width);	
	
	break;
	
	
case 'download':

	if (isset($_GET['lgcurve']))
	{
		$lgc = get_lgcurve_info((int)$_GET['lgcurve']);
		if (empty($lgc))
			exiterror("Cannot download a nonexistent set of datapoints!");
		if ($Corpus->name != $lgc->corpus)
			exiterror("Cannot download a lexical growth curve from a different corpus from this interface!");
		
		download_lgcurve($lgc->id);
		
		/* and as this is a file download, we don't want a next location. */
		unset($next_location);
	}
	else
		exiterror('No lexical growth curve data specified: cannot download.');	
	break;


case 'delete':

	if (isset($_GET['lgcToDelete']))
	{
		$lgc = get_lgcurve_info((int)$_GET['lgcToDelete']);
		if (empty($lgc))
			exiterror("Cannot delete a nonexistent set of datapoints!");
		if ($Corpus->name != $lgc->corpus)
			exiterror("Cannot delete a lexical growth curve from a different corpuis from this interface!");
		
		delete_lgcurve($lgc->id);
	}
	else
		exiterror('No lexical growth curve data specified to delete!');	
	break;


default:

	exiterror("No valid action specified for lexical growth curve access.");
	break;


} /* end the main switch */



if (isset($next_location))
	set_next_absolute_location($next_location);

cqpweb_shutdown_environment();

exit(0);


/* ------------- *
 * END OF SCRIPT *
 * ------------- */
	
