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
 * @file  This file manages user upload actions. 
 */


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');


require('../lib/environment.php');


/* include all function files */
require('../lib/sql-lib.php');
require('../lib/scope-lib.php');
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/useracct-lib.php');
require('../lib/cqp.inc.php');
require('../lib/cache-lib.php');
require('../lib/upload-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');




/* declare global variables */
$Corpus = $User = $Config = NULL;


/* file-view area under /usr. */
$next_location = 'index.php?ui=viewFiles';

/* because we've not used the startup function to map POST to GET, script-action needs to check both */ 
$script_action = isset($_GET['uplAction']) ? $_GET['uplAction'] : (isset($_POST['uplAction']) ? $_POST['uplAction'] : false); 


switch ($script_action)
{
case 'userFileUpload':
	
	cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_USR);

	if (empty($_FILES['uploadedFile']))
		exiterror("CQPweb did not receive the right information about your uploaded file.");
	
	assert_successful_upload('uploadedFile');
	assert_upload_within_user_permission('uploadedFile');
	
	uploaded_file_to_upload_area('uploadedFile', true);
	
	break;
	
	
case 'userFileDelete':
	
	cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_USR);
	
	if (empty($_GET['filename']))
		exiterror("Cannot delete file -- no filename specified.");
	
	uploaded_file_delete($_GET['filename'], false, $User->username);
	
	break;


case 'fileView':
	
	cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_USR);
	
	do_uploaded_file_view($_GET['filename']);
	
	$next_location = NULL;
	
	break;
	
	
case 'uploadQuery':

	cqpweb_startup_environment(CQPWEB_STARTUP_NO_FLAGS, RUN_LOCATION_CORPUS);
	
	$cqp = get_global_cqp();
	
	/* do we have the save name? */
	if (isset($_GET["uploadQuerySaveName"]))
		$save_name = $_GET["uploadQuerySaveName"];
	else
		exiterror('No save name was specified!');
	
	
	/* do we have the array of the uploaded file? */
	$filekey = "uploadQueryFile";
	if (! (isset($_FILES[$filekey]) && is_array($_FILES[$filekey])) )
		exiterror('Information on the uploaded file was not found!');
	
	/* did the upload actually work? */
	assert_successful_upload($filekey);
	assert_upload_within_user_permission($filekey);
	
	/* did the user attempt a binary upload? */
	$binary_upload = isset($_GET["uploadBinary"]) ? (bool) $_GET["uploadBinary"] : false;
	if ($binary_upload && ! $User->has_cqp_binary_privilege())
		exiterror('Your account lacks the necessary permissions to insert binary files into the system.');
	
	/* check the save name :is it a handle? */
	if (! cqpweb_handle_check($save_name) )
		exiterror(array(
						'Names for saved queries can only contain letters, numbers and the underscore character (&nbsp;_&nbsp;)!',
						'Please use the BACK-button of your browser and change your input accordingly.'
						) );
	
	/* Does a query by that name already exist for (this user + this corpus) ? */
	if ( save_name_in_use($save_name) )
		exiterror(array(
						/* note, it's safe to echo back without XSS risk, because we know it is handle at this point */
						"A saved query with the name ''$save_name'' already exists.",
						'Please use the BACK-button of your browser and change your input accordingly.'
						) );	
	
	/* we're satisfied: ergo, generate our qname for use below. */
	$qname = qname_unique($Config->instance_name);
	
	
	if (! $binary_upload)
	{
		/* It's a standard upload of the undump kind
		 * ========================================= */
		
		/* get the filepath of the uploaded file */
		$uploaded_file = $_FILES[$filekey]['tmp_name'];
		
		/* determine the filepath we want to put it in for undumping */
		$undump_file = get_user_upload_area($User->username) . '/___uploadQundmp' . $Config->instance_name;  
		while (file_exists($undump_file ))
			$undump_file .= '_';
		
		/* guarantee that the format is good */
		$err_line_no = $err_line_content = NULL;
		if (!($hits = uploaded_file_guarantee_dump($uploaded_file, $undump_file, $err_line_no, $err_line_content)))
			exiterror(array(
				'Your uploaded file has a format error.',
				'The file must only consist of two columns of numbers (separated by a tab-stop).',
				"The error was encountered at line # $err_line_no . The incorrect line is as follows:",
				"   $err_line_content   ",
				'Please amend your query file and retry the upload.'
			));
		
		/* undump to CQP and save */
		$cqp->execute("undump $qname < '$undump_file'");
		$cqp->execute("save $qname");
		
		/* delete the format-guaranteed uploaded file */
		unlink($undump_file);
	}
	else
	{
		/* It's a binary-data file reinsertion.
		 * ==================================== */
		
		/* apply a check for the correct CQP format. */
		if (!cqp_file_check_format($_FILES[$filekey]['tmp_name'], true, true, true))
			exiterror('The file you uploaded was not in the correct format for a CQP binary query data file on this system.');
		
		/* this is our target path... */
		$target_path = $Config->dir->cache . '/' . $Corpus->cqp_name . ':' . $qname; 
		if (file_exists($target_path))
			exiterror("Critical error - cannot overwrite existing cache file via binary reinsertion. ");
		
		/* move uploaded file to the cache directory under that new qname. */
		if (move_uploaded_file($_FILES[$filekey]['tmp_name'], $target_path)) 
			chmod($target_path, 0664);
		else
			exiterror("Critical error - reinserting binary file in CQPweb's data store failed.");
		
		/* CQP then needs to refresh the Datadir. */
		$cqp->refresh_data_directory();
		
		$hits = $cqp->querysize($qname);
		if (1 > $hits)
		{
			unlink($target_path);
			exiterror("CQP could not interpret your uploaded file.");
		}
	}
	
	/* Record creation actions, common to both types of upload: log query as saved Q in the cache. */
	
	$cache_record = QueryRecord::create(
			$qname, 
			$User->username, 
			$Corpus->name, 
			'uploaded', 
			'', 
			'', 
			QueryScope::new_by_unserialise(""),
			$hits, 
			count( $cqp->execute("group $qname match text_id") ), 
			"upload[{$Config->instance_name}]"
			);
	$cache_record->saved = CACHE_STATUS_SAVED_BY_USER;
	$cache_record->save_name = $save_name;
	$cache_record->save();

	/* all done! */
	
	$next_location = 'index.php?ui=savedQs';
	
	break;
	
	
	
// TODO: user subcorpus upload? factoring oput a ton of the above into fucntions, so that they can be re-used. */
	
	
default:

	exiterror("No valid action specified for upload action.");
	
	break;
}



if (isset($next_location))
	set_next_absolute_location($next_location);

cqpweb_shutdown_environment();

exit(0);

/*
 * =============
 * END OF SCRIPT
 * =============
 */

