<?php
/*
 * CQPweb: a user-friendly interface to the IMS Corpus Query Processor
 * Copyright (C) 2008-10 Andrew Hardie and contributors
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
 * This file contains a library of broadly useful functions. 
 */





/** Converts an integer to a (by default noless than) 10-char string in base 36. 
 *  Used to create string representations of IDs (esp those from the DB). */
function int_to_base_36($i, $length = 10)
{
	return str_pad(base_convert(dechex($i), 16, 36), $length, "0", STR_PAD_LEFT);
}




// stub func for old name of this function ; delete once no longer used. 
function get_global_sqllink()
{
	return get_global_sql_link();
}






function disconnect_browser_and_continue($send_json_ok_status = false)
{
	$bytes = 0 ;

	if ($send_json_ok_status)
		$bytes = strlen($back = '{"status":"ok"}');
	
	ignore_user_abort(true);
	
	if (!headers_sent())
	{
		if($send_json_ok_status)
			header("Content-Type: application/json");
		header("Connection: close");
		header("Content-Encoding: none");
		header("Content-Length: $bytes");
	}
	
	if ($send_json_ok_status)
		echo $back;
	
	ob_flush();
	flush();
	
	/* this *ought* to work....  esp as cqpweb_startup_environment()
	 * has already called ignore_user_abort() and ob_implicit_flush()! 
	 * Note that the ob_flush() call seems to be necessary. Not sure why.
	 */
	global $Config;
	$Config->client_is_disconnected = true;
}





/** 
 * This function removes any existing start/end anchors from a regex
 * and adds new ones.
 * TODO rename : add_regex_anchors or, add_anchors_to_regex
 */
function regex_add_anchors($s)
{
	$s = preg_replace('/^\^/',     '', $s);
	$s = preg_replace('/^\\A/',    '', $s);
	$s = preg_replace('/\$$/',     '', $s);
	$s = preg_replace('/\\[Zz]$/', '', $s);
	return "^$s\$";
}



/**
 * Wrapper for htmlspecialchars($string, ENT_COMPAT, 'UTF-8', false) 
 * -- these being the settings we want almost everywhere in CQPweb.
 */
function escape_html($string, $double_encode = false)
{
	return htmlspecialchars($string, ENT_COMPAT, 'UTF-8', $double_encode);
} 

/**
 * Undoes escape_html() for links with addresses. 
 * 
 * @param  string $escaped_string    Input string (processed by escape_html() )
 * @return string                    The partially-unescaped data.
 */
function unescape_escaped_a_href($escaped_string)
{
	return preg_replace(
			'|&lt;a\s+href=&quot;(.*?)&quot;\s*&gt;(.*?)&lt;/a&gt;|', 
			'<a href="$1" target="_blank">$2</a>', 
			$escaped_string); 
}



/**
 * Removes any nonhandle characters from a string.
 * 
 * A "handle" can only contain ascii letters, numbers, and underscore.
 * 
 * If removing the nonhandle characters reduces it to an
 * empty string, then it will be converted to "__HANDLE".
 * 
 * (Other code must be responsible for making sure the handle is unique
 * where necessary.)
 * 
 * A maximum length can also be enforced if the second parameter
 * is set to greater than 0.
 */
function cqpweb_handle_enforce($string, $maxlength = -1)
{
	$handle = preg_replace('/[^a-zA-Z0-9_]/', '', $string);
	if (empty($handle))
		$handle = '__HANDLE';
	return ($maxlength < 1 ? $handle : substr($handle, 0, $maxlength) );
}
//TODO stop this func being used. Too much scope for bad juju.


/**
 * Checks a string for validity as a handle.
 * 
 * Returns true iff the argument string is OK as a handle,
 * that is, iff it matches the correct regex constants. 
 * 
 * A maximum length can also be checked if the second parameter
 * is set to greater than 0. It defaults to HANDLE_MAX_ITEM_ID. 
 */
function cqpweb_handle_check($string, $maxlength = HANDLE_MAX_ITEM_ID)
{
	return (
			0 < preg_match('/^' . CQPWEB_HANDLE_BYTE_REGEX . '{1' . (1 > $maxlength ? '' : ",$maxlength") . '}$/', (string)$string)
			);
}


/**
 * Function which performs standard safety checks on a qname parameter in
 * the global $_GET array, and exits the program if it is either (a) not present
 * or (b) not a word-character-only string.
 * 
 * The return value is then safe from XSS if embedded into HTML output;
 * and is also safe for embedding into MySQL queries.
 * 
 * A named index into $_GET can be supplied; if none is, "qname" is assumed.
 */
function safe_qname_from_get($index = 'qname')
{
	if (!isset($_GET[$index]))
		exiterror('No query ID was specified!');
	else
		$qname = $_GET[$index];
	if (! cqpweb_handle_check($qname))
		exiterror('The specified query ID is badly formed!');
	return $qname;
}


/**
 * Sets the location field in the HTTP response
 * to an absolute location based on the supplied relative URL,
 * iff the headers have not yet been sent.
 * 
 * If, on the other hand, the headers have been sent, 
 * the function does nothing.
 * 
 * The function DOES NOT exit. Instead, it returns the
 * value it itself got from the headers_sent() function.
 * This allows the caller to check whether it needs to
 * do something alternative.
 * 
 * Note that in consequence, the "success" boolean 
 * return is effectively backwards. 
 */
function set_next_absolute_location($relative_url)
{
	/* in API mode, pretend it worked. */
	global $Config;
	if ($Config->Api)
		return false;
	
	if (!headers_sent())
	{
		header('Location: ' . url_absolutify($relative_url));
		return false;
	}
	return true;
}




/**
 * This function creates absolute URLs from relative ones by adding the relative
 * URL argument $u to the real URL of the directory in which the script is running.
 * 
 * The URL of the currently-running script's containing directory is worked out  
 * in one of two ways. If the global configuration variable "$cqpweb_root_url" is
 * set, this address is taken, and the corpus handle (SQL version, IE lowercase, which 
 * is the same as the subdirectory that accesses the corpus) is added. If no SQL
 * corpus handle exists, the current script's containing directory is added to 
 * $cqpweb_root_url.
 * 
 * $u will be treated as a relative address  (as explained above) if it does not 
 * begin with "http:" or "https:" and as an absolute address if it does.
 * 
 * Note, this "absolute" in the sense of having a server specified at the start, 
 * it can still contain relativising elements such as '/../' etc.
 */
function url_absolutify($u, $special_subdir = NULL)
{
	global $Config;
	global $Corpus;

	
	/* outside a corpus, extract the immediate containing directory from REQUEST_URI (e.g. 'adm') */
	if ( (empty($special_subdir) && (empty($Corpus) || !$Corpus->specified)) && ! empty($_SERVER['REQUEST_URI']))
	{
		preg_match('|\A.*/(\w+)/[^/]*\z|', $_SERVER['REQUEST_URI'], $m);
		$special_subdir = $m[1];
	}

	if (preg_match('/\Ahttps?:/', $u))
		/* address is already absolute */
		return $u;
	else
	{
		/* 
		 * make address absolute by adding server of this script plus folder path of this URI;
		 * this may not be foolproof, because it assumes that the path will always lead to the 
		 * folder in which the current php script is located -- but should work for most cases 
		 */
		if (empty($Config->cqpweb_root_url))
		{
			/* if _SERVER is missing either "chunk", give up and return $u. */
			if ( empty($_SERVER['HTTP_HOST']) || empty($_SERVER['REQUEST_URI']) )
				return $u;
			$url = (isset ($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https://' : 'http://')
				  /* host name */
				. $_SERVER['HTTP_HOST']
				  /* path from request URI excluding filename */ 
				. preg_replace('|/[^/]*\z|', '/', $_SERVER['REQUEST_URI'])
				  /* target path relative to current folder */ 
				. $u;
		}
		else
			$url = $Config->cqpweb_root_url 
				. ( 
					(!empty($Corpus->name)) 
					/* within a corpus, use the root + the corpus sql name */
					? $Corpus->name  
					: $special_subdir
				)
				. '/' . $u
		;
		
		/* attempt to resolve ../ if present */
		$url = preg_replace('|/[^\./]+/\.\./|', '/', $url);
		
		return $url; 
	}
}




/**
 * Pulls out a list of values which are all specified with the same "name" from 
 * an HTTP query string (usually $_SERVER['QUERY_STRING']).
 * 
 * @param  string $param_name     Parameter name to look for.
 * @param  string $query_string   The HTTP query string.
 * @return array                  Flat array of strings.
 */
function list_parameter_values_from_http_query($param_name, $query_string)
{
// TODO change \b at start to lookbehind like   the lookagherad at the start. 
	if ( 0 < preg_match_all("/\b$param_name=([^&]*)(?=&|$)/", $_SERVER['QUERY_STRING'], $m, PREG_PATTERN_ORDER))
		return array_map('urldecode', $m[1]);
	else
		return array();
}


// produces a list of parameters that should ALWAYS be scrubbed out of what is pass3ed. 
function list_things_always_to_ignore_for_print_input_funcs()
{

return [
't', 'del','concBreakdownAt','progToChart', 'distOver',
];

}



// TODO replace with better function that makes use of keys.
// There is a building PHP fucniton -- http_build_query.
// better not to blindly pass things from GET to a url that gets spat out!
/**
 * Returns a string of "var=val&var=val&var=val".
 * 
 * $changes = array of arrays, 
 * where each array consists of [0] a field name  
 *                            & [1] the new value.
 * 
 * If [1] is an empty string, that pair is not included.
 * 
 * WARNING: adds values that weren't already there at the START of the string.
 * 
 */
function url_printget($changes = "Nope!", $file = '', $line = '')
{
	static $always = NULL;
	if(empty($always))
		$always = list_things_always_to_ignore_for_print_input_funcs();



$d = debug_backtrace();
$file = basename($d[0]['file']);
$line = $d[0]['line'];
	global $User;
	$change_me = is_array($changes);

	$string = '';
// if ($User->is_admin()) echo "<pre>These are the things being passed-thru by GET (to URL)  this time...</pre>";
	foreach ($_GET as $key => $val)
	{
if (in_array($key, $always)) continue;
		if (!empty($string))
			$string .= '&';
// if ($User->is_admin()) echo "<pre>Passing through $key ($val)</pre>";

		if ($change_me)
		{
			$newval = $val;

			foreach ($changes as &$c)
				if ($key == $c[0])
				{
					$newval = $c[1];
					$c[0] = '';
				}
			/* only add the new value if the change array DID NOT contain a zero-length string */
			/* otherwise remove the last-added & */
			if ($newval != "")
			{

if ($User->is_admin()) 
{
//	if (!in_array($key, $always))
//		echo "<pre>URL PRINTGET:::Passing through $key ($val).......................................     AND USING IT. ($file, $line) </pre>"; 
}
				$string .= $key . '=' . urlencode($newval);
			}
			else
				$string = preg_replace('/&\z/', '', $string);
		
		}
		else
			$string .= $key . '=' . urlencode($val);
		/* urlencode needed here since $_GET appears to be un-makesafed automatically */
	}
	if ($change_me)
	{
		$extra = '';
		foreach ($changes as &$c)
			if ($c[0] != '' && $c[1] != '')
				$extra .= $c[0] . '=' . $c[1] . '&';
		$string = $extra . $string;
	}
	
	return $string;
}



// TODO; print_hidden_inputs is the function to use instead.
/**
 * Returns a string of "&lt;input type="hidden" name="key" value="value" /&gt;..."
 * 
 * $changes = array of arrays, 
 * where each array consists of [0] a field name  
 *                            & [1] the new value.
 * 
 * If [1] is an empty string, that pair is not included.
 *  
 * WARNING: adds values that weren't there at the START of the string.
 */
function url_printinputs($changes = "Nope!", $file = '', $line = '')
{
	static $always = NULL;
	if(empty($always))
		$always = list_things_always_to_ignore_for_print_input_funcs();

$d = debug_backtrace();
$file = basename($d[0]['file']);
$line = $d[0]['line'];
	global $User;
	$change_me = is_array($changes);

	$string = '';
// if ($User->is_admin()) echo "<pre>These are the things being passed-thru by GET (to HIDDENINPUT)  this time...</pre>";
	foreach ($_GET as $key => $val)
	{

if (in_array($key, $always)) continue;
		if ($change_me)
		{
			$newval = $val;
			foreach ($changes as &$c)
				if ($key == $c[0])
				{
					$newval = $c[1];
					$c[0] = '';
				}
			/* only add the new value if the change array DID NOT contain a zero-length string */
			if ($newval !== '')
			{
// TODO; print_hidden_inputs is the function to use instead.
				$string .= '<input type="hidden" name="' . $key . '" value="' . escape_html($newval) . '" >';

if ($User->is_admin()) 
{
//	if (!in_array($key, $always))
//		echo "<pre>URL PRINTGET:::Passing through $key ($val).......................................     AND USING IT. ($file, $line) </pre>"; 
}
			}
		}
		else
			$string .= '<input type="hidden" name="' . $key . '" value="' . escape_html($val) . '" >';
	}

	if ($change_me)
	{
		$extra = '';
		foreach ($changes as &$c)
			if ($c[0] !== '' && $c[1] !== '')
				$extra .= '<input type="hidden" name="' . $c[0] . '" value="' . escape_html($c[1]) . '" >';
		$string = $extra . $string;
	}
	return $string;
}



/**
 * Gets an integer variable indicating how many "entries" (of whatever)
 * are to be shown per page.
 * 
 * In the concordance display, can also return "all" or "count" as these 
 * are special codes for that display.
 * 
 * If an invalid value is given as $pp, this will cause CQPweb to default 
 * back to $Config->default_per_page.
 * 
 * @param  string $pp  A "per page" value from $_GET to be validated.
 * @return int         Validated value (integer, or some special string in concordance.php)  
 */
function prepare_per_page($pp)
{
	global $Config;
	
	if ( is_string($pp) )
		$pp = strtolower($pp);
		/* in order to accept 'ALL' and 'COUNT'. */
	
	switch($pp)
	{
	/* extra values valid in concordance.php */
	case 'count':
	case 'all':
		if (false === strpos($_SERVER['PHP_SELF'], 'concordance.php'))
			$pp = $Config->default_per_page;
		break;

	default:
		/* must be positive */
		$pp = (int)$pp;
		if (1 > $pp)
			$pp = $Config->default_per_page;
			/* this also catches the case where the parameter is NULL or an unset var */
		break;
	}
	return $pp;
}


function prepare_page_no($n)
{
	return max((int)$n, 1);
}


/**
 * Returns either an input or a URL fragment (per $as) 
 * for passing the current "per page" through a link/form. 
 * 
 * If the pp value is the same as the default - nothing will be passed.
 * Ergo an empy string is returned.
 * 
 * @param  string $as             (input|url)
 * @param  int    $pp             Number of hits to show per page in a concordance.
 * @return string                 A bit of URL or a hidden input. If a URL, DOES NOT
 *                                include the & or ?.
 */
function print_per_page_for_reinsertion($as, $pp)
{
	global $Config;
//show_var($pp);
//show_var ($Config->default_per_page);

	if ($pp != $Config->default_per_page)
	{
		if ('input' == $as)
			return '<input type="hidden" name="pp" value="'.$pp.'">';
		if ('url' == $as)
			return 'pp='.$pp;
	}
	return '';
}

/**
 * Returns either an input or a URL fragment (per $as) 
 * for passing the current "page no. " through a link/form. 
 * 
 * If the pp value is the same as the default - nothing will be passed.
 * Ergo an empy string is returned.
 * 
 * @param  string $as             (input|url)
 * @param  int     $page_no            Page number of a concordance.
 * @return string                 A bit of URL or a hidden input.$this
 */
function print_page_no_for_reinsertion($as, $page_no)
{
	$page_no = (int)$page_no;
	if ($page_no >= 1)
	{
		if ('input' == $as)
			return '<input type="hidden" name="pageNo" value="'.$page_no.'">';
		if ('url' == $as)
			return 'pageNo='.$page_no;
	}
	return '';
}


/**
 * Returns a bool: is the specified user a username?
 */
function user_is_superuser($username)
{
	return in_array($username, list_superusers());
}


/**
 * Returns an array of superuser usernames.
 */
function list_superusers()
{
	/* superusers are determined in the config file */
	global $Config;
	
	static $a = NULL;
	
	if (empty($a))
		$a = explode('|', $Config->superuser_username);
	
	return $a;
}



/**
 * Change the character encoding of a specified text file. 
 * 
 * The re-coded file is saved to the path of $outfile.
 * 
 * Infile and outfile paths cannot be the same.
 *
 * No return value; aborts if something wrong.
 *
 * @param string $infile
 * @param string $outfile
 * @param string $source_charset_for_iconv
 * @param string $dest_charset_for_iconv 
 */
function change_file_encoding($infile, $outfile, $source_charset_for_iconv, $dest_charset_for_iconv)
{
	if (! is_readable($infile) )
		exiterror("The file ``$infile'' is not readable.");

	$source = fopen($infile, 'r');

	if (! is_writeable(dirname($outfile)) )
		exiterror("The file ``$outfile'' is not writeable.");

	$dest = fopen($outfile,  'w');

	while (false !== ($line = fgets($source)) )
		fputs($dest, iconv($source_charset_for_iconv, $dest_charset_for_iconv, $line));

	fclose($source);
	fclose($dest);
}



/** Function to check if we have a given amount of spare RAM. Allows tidy shutdown under control of caller. */
function lookahead_for_memory_abort($headroom)
{
	static $limit = NULL;
	if (NULL === $limit)
		$limit = ini_get('memory_limit');
	
	$in_use = memory_get_usage();
	
	return ($in_use + $headroom + 4096 < $limit);  /* note -- 4kb margin */
}
// designed to stop export-corpus running out of memory, by checking between loops but I'm really not sure that it will actually work.






/**
 * change the execute time setting by a given integer number of seconds from its present value; 
 * negative shifts allowed; 0 for unlimited.
 */
function php_execute_time_add($offset)
{
	if (0 == $offset)
		set_time_limit(0);
	$l = (int)ini_get('max_execution_time');
	set_time_limit($l + (int) $offset);
}



function php_execute_time_unlimit($switch_to_unlimited = true)
{
	static $orig_limit;

	if ($switch_to_unlimited)
	{
		$orig_limit = (int)ini_get('max_execution_time');
		set_time_limit(0);
	}
	else
		set_time_limit($orig_limit);
}

function php_execute_time_relimit()
{
	php_execute_time_unlimit(false);
}


function archive_file_get_type($path)
{
	if (preg_match('/\.(tar\.gz|tgz|tar\.bz2|tbz|zip)$/i', $path, $m))
	{
		switch(strtolower($m[1]))
		{
		case 'tar.gz':
		case 'tgz':
			return ARCHIVE_TYPE_TAR_GZ;
		case 'tar.bz2':
		case 'tbz':
			return ARCHIVE_TYPE_TAR_BZ2;
		case 'zip':
			return ARCHIVE_TYPE_ZIP;
		default:
			break;
		}
	}
	
	return ARCHIVE_TYPE_UNKNOWN;
}



/**
 * Convenience function to delete a specified directory, plus everything in it.
 */
function recursive_delete_directory($path)
{
	if (!is_dir($path))
		return;

	foreach(scandir($path) as $f)
	{
		/* delete directories; unlink regualr files and symlinks. */
		if ($f == '.' || $f == '..')
			;
		else if (is_dir("$path/$f") && !is_link("$path/$f"))
			recursive_delete_directory("$path/$f");
		else
			unlink("$path/$f");
	}
	rmdir($path);
}

/**
 * Convenience function to count the size of all files in a directory, recursively.
 * 
 * @param  string $path   Path to the directory.
 * @return int            The size in bytes.
 */
function recursive_sizeof_directory($path)
{
	if (!is_dir($path))
		return 0;

	$size = 0;

	foreach(scandir($path) as $f)
	{
		if ($f == '.' || $f == '..')
			;
		else if (is_dir("$path/$f"))
			$size += recursive_sizeof_directory("$path/$f");
		else
			$size += filesize("$path/$f");
	}

	return $size;
}


/**
 * Convenience function to recursively copy a directory.
 * 
 * Both $from and $to should be directory paths. 
 * 
 * If $from is a file or symlink rather than a directory, 
 * we default back to the behaviour
 * of php's builtin copy() function.
 * 
 * If $to already exists, it will be overwritten.
 */
function recursive_copy_directory($from, $to)
{
	if (is_dir($from))
	{
		recursive_delete_directory($to);
		mkdir($to);
		
		foreach(scandir($from) as $f)
		{
			if ($f == '.' || $f == '..')
				;
			else if (is_dir("$from/$f"))
				recursive_copy_directory("$from/$f", "$to/$f");
			else
				copy("$from/$f", "$to/$f");
		}
	}
	else
		copy($from, $to);
}


function recursive_flatten_directory($path, $target = NULL)
{
	if (!is_dir($path))
		return;	
	
	if (is_null($target))
		$target = $path;
	
	foreach(scandir($path) as $f)
	{
		/* delete directories; unlink regualr files and symlinks. */
		if ($f == '.' || $f == '..')
			;
		else if (is_dir("$path/$f") && !is_link("$path/$f"))
		{
			recursive_flatten_directory("$path/$f", $target);
			rmdir("$path/$f");
		}
		else if ($path != $target)
			rename("$path/$f", "$target/$f");
	}
}

/**
 * This function stores values in a table that would be too big to send via GET.
 *
 * Instead, they are referenced in the web form by their id code (which is passed 
 * by GET) and retrieved by the script that processes the user input.
 * 
 * The return value is the id code that you should use in the web form.
 * 
 * Things stored in the longvalues table are deleted when they are 5 days old.
 * 
 * The retrieval function is longvalue_retrieve().
 *  
 */
function longvalue_store($value)
{
	$value = escape_sql($value);
	
	
	if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
	{
		static $ids_used = NULL;
		
		global $Config;
		
		/* this is how we guarantee the uniqueness of the ID even if there is more than one longvalue stored per CQPweb run.
		 * (Originally, just the instance name was used. This allows for multiple longvalues per instance.) */
	
		$id = $Config->instance_name;
		
		if (is_null($ids_used))
			$ids_used = array();
		else
		{
			/* add an extra fill-one-place char if we've used up all possibilities. Which we most likely won't. */
			for($c = count($ids_used); $c > 25; $c -= 26)
				$id .= 'X';
			
			/* change the last character */
			do
			{
				$id = substr($id, 0, -1) . chr(rand (65, 90));
			} while (in_array($id, $ids_used));
		}
		
		$ids_used[] = $id;

		/* clear out old longvalues */
		do_sql_query("delete from system_longvalues where `timestamp` < DATE_SUB(NOW(), INTERVAL 5 DAY)");
		do_sql_query("insert into system_longvalues (id, value) values ('$id', '$value')");
		return $id;
	}
	else
	{
		/* clear out old longvalues */
		do_sql_query("delete from system_longvalues where `date_of_storing` < DATE_SUB(NOW(), INTERVAL 5 DAY)");
		do_sql_query("insert into system_longvalues set `value` = '$value'");
		return get_sql_insert_id();
	}
}


/**
 * Retrieval function for values stored with longvalue_store.
 */
function longvalue_retrieve($id)
{	
	if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
		return get_sql_value("select `value` from system_longvalues where id = '" . escape_sql($id) . "'");
	else
		return get_sql_value("select `value` from system_longvalues where id = " . (int)$id);
}

function longvalue_delete($id)
{
	if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
		do_sql_query("delete from system_longvalues where id = '" . escape_sql($id) . "'");
	else
		do_sql_query("delete from system_longvalues where id = " . (int)$id);
}


function get_embed_info($id)
{
	return get_sql_object("select * from embedded_pages where id=" . (int)$id);
}

function get_all_embeds_info()
{
	return get_all_sql_objects("select * from embedded_pages order by id");
}



function add_new_embed($title, $file_path)
{
	$title = escape_sql($title);
	$file_path = escape_sql($file_path);

	do_sql_query("insert into embedded_pages (title, file_path) VALUES ('$title', '$file_path')");

}

function delete_embed($id)
{
	do_sql_query("delete from embedded_pages where id=" . (int)$id);
}








/**
 * Send an email with appropriate CQPweb boilerplate, plus error checking. 
 * 
 * @param string $address_to     The "send" email address. Can be a raw address or a name plus address in < ... >.
 * @param string $mail_subject   Subject line.
 * @param string $mail_content   The email body. Its line breaks will be made into \r\n.
 * @param array  $extra_headers  Array of extra header lines (one per entry, no line breaks). If these DO NOT
 *                               include From: / Reply To:, then (if available) the system's email address
 *                               (specified in config file) will be used instead.
 * @return bool                  True if email sent, otherwise false.
 */
function send_cqpweb_email($address_to, $mail_subject, $mail_content, $extra_headers = array())
{
	global $Config;
	
	if ($Config->cqpweb_no_internet)
		return false;

// 	$mail_content = preg_replace("/[\r\n]{1,2}/", "\r\n", $mail_content);
	$mail_content = str_replace("\n", "\r\n", $mail_content);
	$mail_content = str_replace("\r\r", "\r", $mail_content);
	
	if (!empty($Config->cqpweb_root_url))
		$mail_content .= "\r\n" . $Config->cqpweb_root_url . "\r\n";
	
	if (!empty($Config->cqpweb_email_from_address))
	{
		$add_from = true;
		$add_reply_to = true;
		
		foreach($extra_headers as $h)
		{
			$lch = strtolower($h);
			if (substr($lch,0,5) == 'from:')
				$add_from = false;
			if (substr($lch,0,9) == 'reply-to:')
				$add_reply_to = false;
		}
		
		if ($add_from)
			$extra_headers[] = "From: {$Config->cqpweb_email_from_address}";
		if ($add_reply_to)
			$extra_headers[] = "Reply-To: {$Config->cqpweb_email_from_address}";
	}

	return (bool)mail($address_to, $mail_subject, $mail_content, implode("\r\n", $extra_headers));	
}



/* =============================== *
 * A COUPLE OF MISC STAT FUNCTIONS *
 * =============================== */




/**
 * Perform Bonferroni or Šidák correction.
 * 
 * NB this file may not be a good place to do have this function, long-run.
 */ 
function correct_alpha_for_familywise($alpha, $n_comparisons, $type = 'Bonferroni')
{
	/* any empty value signifies don't correct */
	if (empty($type))
		return $alpha;
	
	/* note that there should never be $n_comparisons = 0. Or negative.
	 * but if such a weird thing should happen (and I've seen it in Apache logs!!)
	 * we should return alpha, so that it stays as-is. */

	$n_comparisons = (int)$n_comparisons;
	if (1 >= $n_comparisons)
		return $alpha;
	
	switch($type)
	{
		case 'bonferroni':
		case 'Bonferroni':
			return $alpha/$n_comparisons;
			
		case 'šidák':
		case 'sidak':
		case 'Šidák':
		case 'Sidak':
			return 1.0 - pow((1.0 - $alpha), 1.0/$n_comparisons);
			
		default:
			exiterror("Unrecognised correction for multiple comparisons.");
	}

}



/**
 * Calculates a Z-unit to be used as the offset for the LR confidence interval.
 * 
 * @param  float $alpha   The alpha to use. Caller should adjust for familywise if necessary. Defaults to 0.05.
 * @param  RFace $r       An R Face object to use. Defaults to NULL, in which case the function starts an R slave itself.  
 * @return float          The Z unit (to use, for instance, embedded into an SQL query). 
 */
function calculate_Z_for_LR_confinterval($alpha = 0.05 , $r = NULL)
{
	switch ($alpha)
	{
	/* optimise for some frequently-used cases
	 * (see calculate_LL_threshold() for more notes on this!)
	 * 
	 * alpha here changes to the CI width (0.05 = 2.5% each way...) 
	 */
	case (float)'0.05':			$Z_unit =  1.959964;		break; /* 95% CI; in R: qnorm(0.025, lower.tail=FALSE) evals to 1.959964 */
	case (float)'0.01':			$Z_unit =  2.575829;		break; /* 99% CI; etc. */
	case (float)'0.001':		$Z_unit =  3.290527;		break;
	case (float)'0.0001':		$Z_unit =  3.890592;		break;
	case (float)'0.00001':		$Z_unit =  4.417173;		break;
	case (float)'0.000001':		$Z_unit =  4.891638;		break;
	case (float)'0.0000001':	$Z_unit =  5.326724;		break;
	
	default:
		global $Config;
		$internal_r = is_null($r);
		if ($internal_r)
			$r = new RFace($Config->path_to_r);
		
		list($Z_unit) = $r->read_execute(sprintf("qnorm(%E, lower.tail=FALSE)", $alpha/2.0));
		
		if ($internal_r)
			unset($r);
		
		break;
	}

	return $Z_unit;
}



/**
 * Calculates an LL threshold corresponding to a particular cut-off p-value (alpha).
 * 
 * @param  float $alpha   The alpha to use. Caller should adjust for familywise if necessary. Defaults to 0.05.
 * @param  RFace $r       An R Face object to use. Defaults to NULL, in which case the function starts an R slave itself. 
 * @return float          The Log Likelihood threshold value. 
 */
function calculate_LL_threshold($alpha = 0.05, $r = NULL)
{
	switch ($alpha)
	{
	/* Optimise for some frequently-used cases.
	 * 
	 * Normally, doing == comparison with floats is a Bad Thing.
	 * But, since the case statements have the same origin as
	 * the input values (casting a string to float), we should be OK.
	 * And if cosmic rays strike, then we just call R anyway -
	 * we do not get an incorrect answer.
	 * 
	 * The number of sig figs is based on the default in R. 
	 */
	case (float)'0.05':			$threshold =  3.841459;		break;
	case (float)'0.01':			$threshold =  6.634897;		break;
	case (float)'0.001':		$threshold =  10.82757;		break;
	case (float)'0.0001':		$threshold =  15.13671;		break;
	case (float)'0.00001':		$threshold =  19.51142;		break;
	case (float)'0.000001':		$threshold =  23.92813;		break;
	case (float)'0.0000001':	$threshold =  28.37399;		break;
	case (float)'1.0':			$threshold =  0.0;			break;
	
	default:
		global $Config;
		$internal_r = is_null($r);
		if ($internal_r)
			$r = new RFace($Config->path_to_r);

		/* R code example: qchisq(0.05, df=1, lower.tail=FALSE) */
		list($threshold) = $r->read_execute(sprintf("qchisq(%E, df=1, lower.tail=FALSE)", $alpha));

		if ($internal_r)
			unset($r);
	
		break;
	}
	
	return $threshold;
}

// qchisq(%E, df=1, lower.tail=FALSE)


// TODO this is a frickken awful way to accomplish this. 

/*
 * REFLECTION functions. This is not reflection in the technical sense, 
 * but it's similar, so we can use the name to make these functions distinctive.
 * 
 * Each of these funcs returns some blob of info about the composition of the system
 * - e.g. a list of required code files from some part of the system.
 * 
 * The functions can be used by any part of CQPweb that needs to inspect its own content
 * for whatever reason. 
 */

/**
 * Return array of builtin javascript files in the ../jsc directory
 * (to distinguish such code files from any manually-added javascript files
 * which might be used in XML visualisation; or in future, perhaps, for other purposes). 
 */
function get_jsc_reflection_list()
{
	return array (
			'always.js',          
			'analyse-md.js',      
			'attribute-embiggen.js',  
			'colloc-options.js',
			'corpus-name-highlight.js',
			'cword.js',           
			'distribution.js',   
			'dispersion.js',   
			'jquery.js',              
			'keywords.js',              
			'metadata-embiggen.js',   
			'misc.js',
			'queryhome.js',       
			'textmeta.js',            
			'tooltip.js',
			'user-quicksearch.js',
			'wordcloud2.js',
		);
	/* note the above needs to be updated whenever anything in the ../jsc code is reorganised / extended. */
	//TODO better than this would be to have jse follder for extra, and only use jsc for intertnal.
}

/**
 * Check whether a given JSC script-name is one that actually exists.
 * 
 * @param  string $name  Name (with no  .js after)
 * @return bool
 */
function is_true_jsc_builtin($name)
{
	static $valid = NULL;
	if (is_null($valid))
		$valid = get_jsc_reflection_list();
	return in_array("$name.js", $valid);
}

/**
 * Return array of .css files in the ../css directory that have filenames 
 * that were created by CQPweb rather than being manually added by the admin.
 * //TODO - use a diff location for 'extra' css.
 */
function get_css_reflection_list()
{
	return array (
			'CQPweb-0system.css',
			'CQPweb-blue.css',
			'CQPweb.css',
			'CQPweb-aqua.css',
			'CQPweb-brown.css',
			'CQPweb-dusk.css',
			'CQPweb-gold.css',
			'CQPweb-green.css',
			'CQPweb-lime.css',
			'CQPweb-navy.css',
			'CQPweb-neon.css',
			'CQPweb-purple.css',
			'CQPweb-red.css',
			'CQPweb-rose.css',
			'CQPweb-teal.css',
			'CQPweb-yellow.css',
			'CQPweb-user-monochrome.css',
		);
	/* note the above needs to be updated whenever new builtin colours are added. */
}





/*
 * ======================
 * system message control 
 * ====================== 
 */

/**
 * Create a system message that will appear below the main "Standard Query"
 * box (and also on the homepage). 
 * 
 * @param  string $header    Headline / subject line for message. 
 * @param  string $content   Body of message.
 * @return int               Returns auto-allocated integer ID.
 */
function add_system_message($header, $content)
{
	global $User;
	if (!$User->is_admin())
		return;
	$sql = "insert into system_messages set 
		header     = '" . escape_sql($header)  . "', 
		content    = '" . escape_sql($content) . "'
		";
	/* timestamp is defaulted */
	do_sql_query($sql);
	return get_sql_insert_id();
}

/**
 * Delete the system message associated with a particular message_id.
 *
 * The message_id is the id column of the message DB trable. 
 * 
 * @param int $message_id  Integer ID of message to delete. 
 */
function delete_system_message($message_id)
{
	global $User;
	if (!$User->is_admin())
		return;
	$message_id =(int) $message_id;
	do_sql_query("delete from system_messages where `id` = $message_id");
}








/*
 * ===========================================
 * code file self-awareness and opcode caching
 * =========================================== 
 */

/**
 * Returns a list of realpaths for the PHP files that make up
 * the online CQPweb system. Offline "bin" scripts are excluded.
 * 
 * @param  string $limit 'code' or 'stub' to get just one type of file. 
 *                       'all' (or leave unspecified) to get all types.
 * @return array         A flat array of relative filenames.
 */
function list_php_files($limit = 'all')
{
	global $User;
	
	if (!$User->is_admin())
		return;
	
	$r = array();
	
	if ($limit == 'all' || $limit == 'stub')
	{
		/* add stubs */
		$r = array_merge($r, array('../index.php'));
		foreach(array('adm', 'rss', 'usr', 'exe') as $c)
			$r = array_merge($r, glob("../$c/*.php"));
	}
	
	if ($limit == 'all' || $limit == 'code')
		/* add lib + plugins */
		$r = array_merge($r, glob('../lib/*.php'), glob('../lib/plugins/*.php'));;
	
	return array_map('realpath', $r); 
}

/**
 * Detects which of the three opcache extensions is loaded, if any.
 * 
 * Returns a string (same as the internal extension label, all lowercase)
 * or false if none of them is available.
 */
function detect_php_opcaching()
{
	global $User;
	
	if (!$User->is_admin())
		return;
	
	switch (true)
	{
// 	/* old name and new name  for this extension .... */
// 	case extension_loaded('opcache')|| extension_loaded('Zend OPcache'):
	case ini_get('opcache.enable'):
		return 'opcache';
	case ini_get('wincache.ocenabled'):
		return 'wincache';
	/* note: in php 5.5+, apc is disabled in favour of Zend opcache. 
	 * Only "apcu" (apc user cache with no opcode cache) is included.
	 * The "apc.enabled" test below  will return TRUE even if we only have "apcu". 
	 * So, we ALSO have to check for the existence of one of the actual
	 * opcode-cache (not user-cache!) functions.
	 */
	case ini_get('apc.enabled') && function_exists('apc_compile_file'):
		return 'apc';
	default:
		return false;
	}
}

/**
 * Loads a code file into whatever opcode cache is in use. 
 * @param string $file  Path to the code file that is to be cached.
 */
function do_opcache_load_file($file)
{
	global $User;
	
	if (!$User->is_admin())
		return;
	
	switch (detect_php_opcaching())
	{
	case 'apc':
		apc_compile_file(realpath($file));
		break;
	case 'opcache':
		opcache_compile_file(realpath($file));
		break;
	case 'wincache':
		/* note, we don't have an "load" in this case. So, refresh instead. */
		wincache_refresh_if_changed(array(realpath($file)));
		break;	/* default do nothing */
	}
}

/**
 * Unloads a code file from whatever opcode cache is in use.
 * @param string $file  Path to the code file that is to be uncached.
 */
function do_opcache_unload_file($file)
{
	global $User;
	
	if (!$User->is_admin())
		return;
	
	switch (detect_php_opcaching())
	{
	case 'apc':
		apc_delete_file($file);
		break;
	case 'opcache':
		opcache_invalidate($file, true);
		break;
	case 'wincache':
		/* note, we don't have an "unload" in this case. So, refresh instead. */
		wincache_refresh_if_changed(array(realpath($file)));
		break;
	/* default do nothing */	
	}
}

/**
 * Loads ALL code files to opcode cache.
 * 
 * @param string $limit   Accepts same "limit" strings as list_cqpweb_php_files().
 */
function do_opcache_full_load($limit = 'all')
{
	array_map('do_opcache_load_file', list_php_files($limit));
}

/** 
 * Unloads ALL code files from opcode cache.
 * 
 * @param string $limit   Accepts same "limit" strings as list_cqpweb_php_files(). 
 */
function do_opcache_full_unload($limit = 'all')
{
	global $User;
	
	if (!$User->is_admin())
		return;
	
	switch(detect_php_opcaching())
	{
	case 'opcache':
		foreach(list_php_files($limit) as $f)
			opcache_invalidate($f, true);
		break;
	case 'wincache':
		wincache_refresh_if_changed(list_php_files($limit));
		break;
	case 'apc':
		apc_delete_file(list_php_files($limit));
		break;
	/* default do nothing */
	}
}




