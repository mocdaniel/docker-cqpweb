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
 * @file  Functions for dealing with uploading of files.
 */



/**
 * Aborts the script with an error message iff the file upload was unsuccessful for any reason.
 * 
 * @param string $file_input_name  Key into PHP's _FILES array that corresponds to this file 
 *                                 (same as the name of the file-input element in the form that 
 *                                 generated the upload).
 */
function assert_successful_upload($file_input_name)
{
	/* Check for upload errors; convert back to int: execute.php may have turned it to a string */
	switch ((int)$_FILES[$file_input_name]['error'])
	{
	case UPLOAD_ERR_OK:
		return;
	case UPLOAD_ERR_INI_SIZE:
		exiterror('That file is too big to upload due to system settings! Contact your system administrator.');
	case UPLOAD_ERR_FORM_SIZE:
		exiterror('That file is too big to upload due to webpage settings! Contact your system administrator.');
	case UPLOAD_ERR_PARTIAL:
		exiterror('Only part of the file you tried to upload was received! Please try again.');
	case UPLOAD_ERR_NO_FILE:
		exiterror('No file was uploaded! Please try again.');
	case UPLOAD_ERR_NO_TMP_DIR:
		exiterror('Could not find temporary folder for the upload! Contact your system administrator.');
	case UPLOAD_ERR_CANT_WRITE:
		exiterror('Writing to disk failed during upload! Please try again.');
	default:
		exiterror('The file did not upload correctly (for an unknown reason)! Please try again.');
	}
}


/**
 * Aborts the script with an error message iff the file upload is too big for the current user's permissions.
 * 
 * @param string $file_input_name  Key into PHP's _FILES array that corresponds to this file 
 *                                 (same as the name of the file-input element in the form that 
 *                                 generated the upload).
 */
function assert_upload_within_user_permission($file_input_name)
{
	global $User;
	
	if (!$User->is_admin())
	{
		/* check the file is not too big for this user to upload. */
		if ((int)$_FILES[$file_input_name]['size'] > ($max = $User->max_upload_file()))
			exiterror(
				'You do not have the necessary permissions to upload a file of this size to CQPweb;' 
				. 'your maximum is ' . number_format($max/(1024.0*1024.0), 2) . ' MB.')
				;
		
		/* check that their upload area is not full */
		if ((int)$_FILES[$file_input_name]['size'] >  ($space = user_upload_space_remaining($User->username)))
			exiterror(
				'You do not have enough space left in your upload area for this file;' 
				. 'you only have ' . number_format($space/(1024.0*1024.0), 2) . ' MB left; you need to free up space!')
				;
	}
}

/**
 * Returns the amount of space remaining in the user's upload area (in bytes).
 * 
 * This can be a negative number if they've overflowed.
 * 
 * @param  string $username       Name of the user to look at.
 * @return int                    Amount of disk space, in bytes.
 */
function user_upload_space_remaining($username)
{
	$bytes_used    = user_upload_space_usage($username);
	$bytes_allowed = user_upload_space_limit($username);
	
	return $bytes_allowed - $bytes_used;
}



/**
 * Returns the current size in bytes of this user's upload area.
 *  
 * @param  string $username       Name of the user to look at.
 * @return int                    Amount of disk space, in bytes.
 */
function user_upload_space_usage($username)
{
	$total = 0;
	if ($path = get_user_upload_area($username))
		foreach(scandir($path) as $f)
			if (is_file("$path/$f"))
				$total += filesize("$path/$f");
	return $total;
}



/**
 * Returns the limit - in bytes - of this user's upload area.
 * 
 * @param  string $username       Name of the user to look at.
 * @return int                    Amount of disk space, in bytes.
 */
function user_upload_space_limit($username)
{
	global $User;
	
	/* if it's the logged in user, we can do this without reaccessing the DB */
 	if ($User->username == $username)
 		return $User->max_upload_disk_space();
 	else 
 	{
 		if (!$User->is_admin())
 			exiterror("You do not have access to that information.");
 		else 
			// TODO . Dev value: 10 MB. need ot get max privilege here.
			// will need ot get all granted PRIVILEGE_TYPE_DISK_FOR_UPLOADSft-joining (i think) the privilege and grant tble, pain in the neck!
			return 1024 * 1024 * 10;
			// anmd this will invovle le
 	}
}








/**
 * Gets the (real)path of the user's upload directory. (Creates a directory for the user if one does not already exist.) 
 * 
 * @param  string $username   Username; if empty, the current user is used.
 * @return string             Realpath to the directory. False if no username given or currently logged in.
 *                            Path DOES NOT ends in '/'.
 */
function get_user_upload_area($username = false)
{
	/* cached because of the multiple checks on the filesystem used in asserting the directory. */
	static $cache = [];
	
	if (isset($cache[$username]))
		return $cache[$username];
	
	global $Config;
	
	if (empty($username))
	{
		global $User;
		if (!$User->logged_in)
			return false;
		else
			$username = $User->username;
	}
	
	$dir = $Config->dir->upload . '/usr/';
	
	/* first, assert that the general usr upload tree exists */ 
	if (!is_dir($dir))
		mkdir($dir, 0775);
	
	/* in case there are too many users, we separate them by hex code of BYTE 1 of username. */
	$dir .= sprintf("%02x/", ord($username[0]));
	if (!is_dir($dir))
		mkdir($dir, 0775);
	
	/* and now assert the directory itself. */
	$dir .= $username . '/';
	if (!is_dir($dir))
		mkdir($dir, 0775);
		
	return ( $cache[$username] = realpath($dir) );
}


/**
 * Get the real path of an uploaded file based just on its filename.
 * 
 * @param  string $file            Name of the file. 
 * @param  bool   $in_admin_area   True: this is an admin upload. False: it's a user upload.
 * @param  string $username        Username of file owner. If empty, current user is assumed. Only used if NOT $in_admin_area
 * @return string                  File path, or false if the file is not there or the path could not be worked out. 
 */
function uploaded_file_realpath($file, $in_admin_area, $username = false)
{
	global $Config;
	
	if ($in_admin_area)
		$path = realpath($Config->dir->upload);
	else
	{
		if(empty($username))
		{
			global $User;
			$username = $User->username;
		}
		if (false === ($path = get_user_upload_area($username)))
			return false;
	}

	return (is_file($r = "$path/$file") ? $r : false); 
}




/**
 * Checks an array of upload-area filenames for existence and returns realpaths.
 * 
 * Useful for an advance check on files to be used in a corpus install.
 * 
 *  If check is failed, the system exits.
 * 
 * @param  array  $array_of_files  Array of filenames (of entries in an upload area).
 * @param  string $username        Username of owner of the corpus, or blank if this is a system corpus.
 *                                 Defaults to empty. 
 * @return array                   Flat array of realpaths.
 */
function convert_uploaded_files_to_realpaths($array_of_files, $username = '')
{
	if (empty($username))
	{
		global $User;
		if (!$User->is_admin())
			exiterror("That function is only accessible to superusers.");
		$get_path = function ($x) {return uploaded_file_realpath($x, true);};
	}
	else
		$get_path = function ($x) use ($username) {return uploaded_file_realpath($x, false, $username);};
	
	$files = array_map($get_path, $array_of_files);
	/* files now contains false for any path that isn't real. */ 
// 	foreach($files as $f)
// 		if (false === $f)
	if (in_array(false, $files, true))
			exiterror("One of the files specified seems to have been deleted.");
	return $files;
}





/**
 * Puts an uploaded file (of whatever kind...) into the upload area.
 * 
 * Some of the parameters are not used, but are passed through in case later changes need them. 
 * 
 * Returns an absolute path to the new file. The name of the new file may have been extended by "_"
 * if necessary to avoid a clash with an existing file.
 * 
 * @param  string $file_input_name  Key into PHP's _FILES array that corresponds to this file 
 *                                  (same as the name of the file-input element in the form that 
 *                                  generated the upload).
 * @param  bool   $user_upload      Default false; if true, the file goes into the present user's upload folder
 *                                  rather than the main folder (which is sysadmin only).
 * @return string                   The realpath of the new file. 
 */
function uploaded_file_to_upload_area($file_input_name, $user_upload = false)
{
	global $Config;

	/* check the directory exists for user-uploaded files */
	if ($user_upload)
	{
		if (! ($target_dir = get_user_upload_area()))
			exiterror("No user logged in; can't perform user upload.");
	}
	else
		$target_dir = $Config->dir->upload;
	
	$target_dir .= '/';
	
	/* find a new name - a file that does not exist */
	for ($filename = $basic_filename = basename($_FILES[$file_input_name]['name']), $i = 0; true ; $filename = $basic_filename . '.' . ++$i)
		if ( !file_exists(($new_path = $target_dir . $filename)) )
			break;
	
	if (move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $new_path))
		chmod($new_path, 0664);
	else
		exiterror("The file could not be processed! Possible file upload attack.");
	
	return $new_path;
}

/**
 * Change linebreaks in the named file in the upload area to Unix-style
 * (or, to Windows style iff the global "cqpweb_running_on_windows" variable is true).
 * 
 * Original file is overwritten.
 * 
 * @param string $filename
 * @param bool $in_admin_area
 */
function uploaded_file_fix_linebreaks($filename, $in_admin_area = false)
{
	global $Config;

	if (! ($path = uploaded_file_realpath($filename, $in_admin_area)))
		exiterror('Your request could not be completed - that file does not exist.');
	
	$intermed_path = "{$Config->dir->upload}/____...upload_fix_linebreaks_" . $Config->instance_name;
	
	if ($Config->cqpweb_running_on_windows)
		$eol = "\r\n";
	else
		$eol = "\n";
	
	$source = fopen($path, 'r');
	$dest = fopen($intermed_path, 'w');
	
	/* check for initial UTF8-BOM */
	$first = rtrim(fgets($source), "\r\n") . $eol;
	if ("\xef\xbb\xbf" == substr($first, 0, 3))
		$first = substr($first, 3);
	fputs($dest, $first);
	
	while (false !== ($line = fgets($source)))
		fputs($dest, rtrim($line, "\r\n").$eol);
	
	fclose($source);
	fclose($dest);
	
	unlink($path);
	rename($intermed_path, $path);
	chmod($path, 0664);
}

/**
 * Delete a file from the upload area. 
 * 
 * @param  string $filename        Name of file to delete (file only, no directory).
 * @param  bool   $in_admin_area   True: this is an admin upload. False: it's a user upload.
 * @param  string $owner           If this is empty, it's assumed to be the current user.
 *                                 If not, username of file owner. Users can only delete their own files
 *                                 (except admin of course)
 * @return bool                    Result of the unlink() call. 
 */
function uploaded_file_delete($filename, $in_admin_area = false, $owner = '')
{
	global $User;
	
	if ($User->is_admin())
	{
		if ($in_admin_area)
			$path = uploaded_file_realpath($filename, true);
		else
			$path = uploaded_file_realpath($filename, false, $owner);
	}
	else
	{
		if ($owner == $User->username)
			$path = uploaded_file_realpath($filename, false, $owner);
		else
			$path = false;
		/* the 'false' here raises an error below. 
		   Message does not leak whether or not a non-owned file exists. */ 
	}
	
	if (!$path)
		exiterror('The request could not be completed - that file does not exist.');

	return unlink($path);
}

/**
 * 
 * @param string $filename
 * @param bool $in_admin_area
 */
function uploaded_file_gzip($filename, $in_admin_area = false)
{
	if (! ($path = uploaded_file_realpath($filename, $in_admin_area)))
		exiterror('Your request could not be completed - that file does not exist.');

	$zip_path = $path . '.gz';
	
	$source = fopen($path, "rb");
	if (!($dest = gzopen ($zip_path, "wb")))
		exiterror('Your request could not be completed - compressed file could not be opened.');

	php_execute_time_unlimit();
	while (false !== ($buffer = fgets($source, 4096)))
		gzwrite($dest, $buffer);
	php_execute_time_relimit();

	fclose ($source);
	gzclose ($dest);
	
	unlink($path);
	chmod($zip_path, 0664);
}


/**
 * 
 * @param string $filename
 * @param bool   $in_admin_area
 */
function uploaded_file_gunzip($filename, $in_admin_area = false)
{
	if (! ($path = uploaded_file_realpath($filename, $in_admin_area)))
		exiterror('Your request could not be completed - that file does not exist.');
	
	if (!preg_match('/\.gz$/', $filename))
		exiterror('Your request could not be completed - that file does not appear to be compressed.');

	$unzip_path = substr($path, -3);
	
	if (!($source = gzopen($path, "rb")))
		exiterror('Your request could not be completed - compressed file could not be opened.');
	$dest = fopen($unzip_path, "wb");

	php_execute_time_unlimit();
	while ('' !== ($buffer = gzread($source, 4096))) 
		fwrite($dest, $buffer);
	php_execute_time_relimit();

	gzclose($source);
	fclose ($dest);
			
	unlink($path);
	chmod($unzip_path, 0664);
}



// maybe, move to HTML-lib?
/**
 * Sends to browser a plain HTML file with the first (configured number) of bytes in a given upload file.
 * No CSS, no scripts.  
 *  
 * @param string $filename
 * @param bool   $in_admin_area
 */
function do_uploaded_file_view($filename, $in_admin_area = false)
{
	global $Config;
	
	if (! ($path = uploaded_file_realpath($filename, $in_admin_area)))
		exiterror('Your request could not be completed - that file does not exist.');

	$fh = fopen($path, 'r');
	
	$bytes_counted = 0;
	$data = '';
	
	while ((!feof($fh)) && $bytes_counted <= $Config->uploaded_file_bytes_to_show)
	{
		$line = fgets($fh, 4096);
		$data .= $line;
		$bytes_counted += strlen($line);
	}

	fclose($fh);
	
	$data = escape_html($data);
	
	$kb = round($Config->uploaded_file_bytes_to_show / 1024.0, 0);
	
	if ($in_admin_area)
	{
		/*
		 * Note, it is purposeful that we are not using the html-lib function(s),
		 * because the idea is to keep the HTML very simple (no JavaScript, etc.). 
		 */
		if (! headers_sent())
			header('Content-Type: text/html; charset=utf-8');
		?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<title>CQPweb: viewing uploaded file</title>
	</head>
	<body>

		<?php
	}

	?>

		<h1>Viewing uploaded file <i><?php echo $filename;?></i></h1>
		<p>NB: for very long files only the first <?php echo $kb;?> K is shown</p>
		<hr>
		<pre>
		<?php echo "\n" , $data; ?>
		</pre>
		
	<?php
	
	if ($in_admin_area)
	{
		?>

	</body>
</html>

		<?php
	}
}

/**
 * Test a file (usually one uploaded by a user, thus the name) for compatibility with dumpfile format.
 * Writes the resulting file to a specified location (i.e. a second path).
 * 
 * Also corrects line breaks (skips empty lines, deals with CR/LF) while we're at it. 
 * Thus why we write while reading!
 * 
 * If there is an error, false is returned, and (a) the error line number is written to the 3rd argument,
 * (b) the content of the error line is written to the fourth argument. 
 * 
 * In this case, the part-complete output file is deleted.
 * 
 * @param  string $path_from   Full path of the file to read from.
 * @param  string $path_to     Full path of the file to write to. Overwrites without checking.
 * @param  int    $err_line_n  Out-parameter: Number of the line where an error is encountered,
 *                             if one is (otherwise not overwritten).
 * @param  string $err_line_s  Out-parameter: Content of the line where an error is encountered,
 *                             if one is (otherwise not overwritten).
 * @return int                 Number of valid lines in the file; OR, boolean false
 *                             if a non-valid line was encountered.
 */
function uploaded_file_guarantee_dump($path_from, $path_to, &$err_line_n = NULL, &$err_line_s = NULL)
{
	$source = fopen($path_from, 'r');
	$dest   = fopen($path_to,   'w');
	$count  = 0;
	$hits   = 0;

	/* incremetally copy the file and check its format: every line two \d+ with tabs */
	while (false !== ($line = fgets($source)))
	{
		$count++;
		
		/* do what tidyup we can, to reduce errors */
		$line = rtrim($line);
		if (empty($line))
			continue;
		
		if ( ! preg_match('/\A\d+\t\d+\z/', $line) ) 
		{
			/* error detected */
			fclose($source);
			fclose($dest);
			unlink($path_to);
			$err_line_n = $count;
			$err_line_s = $line;
			return false;
		}
		
		/* target the native line break format (which is what this computer's CWB will expect.) */
		fputs($dest, $line . PHP_EOL);
		$hits++;
	}

	fclose($source);
	fclose($dest);
	
	return $hits;
}
