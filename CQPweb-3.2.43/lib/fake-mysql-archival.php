<?php

/**
 * @file
 * 
 * This file contains functions that simulate those in the mysql_ extension.
 * 
 * It is for use only in environments where PHP has been compiled without the
 * mysql extension but with the mysqli extension (expected to be increasingly
 * common).
 * 
 * Only the subset of mysql_* functions that are used by CQPweb are simulated.
 * 
 * 
 * This code is now archival, as no currently suppoerted PHP version lacks mysqli,
 * or indeed has mysql.
 * 
 * As of 3.2.33, CQPweb has been rewritten to use all-mysqli, all the time.
 */


// archived components of the mysqli => mysql shim.



if  (extension_loaded('mysql'))
	return;



/* functions for the mysql_link resource (actually a mysqli object masquerading as a mysql_link resource) */

	
	
	


/**
 * Module-internal utility function. Returns Boolean (whether the link was successfully made).
 */
function mysql_fake_force_link_set(&$link_identifier)
{
	if ($link_identifier !== NULL)
		return true;
	
	/* "If the link identifier is not specified, the last link opened by mysql_connect() is assumed" */
	global $mysql_fake_connect_last_opened_link_identifier;
	$link_identifier = $mysql_fake_connect_last_opened_link_identifier;
	
	/* "If no such link is found, it will try to create one as if mysql_connect() was called with no arguments" */
	if (!is_object($link_identifier))
		$link_identifier = mysql_connect();
	else
		return true;
	
	/* "If no connection is found or established, an E_WARNING level error is generated" */
	if (!is_object($link_identifier))
	{
		trigger_error('Could not find or create a link to MySQL', E_USER_WARNING);
		return false;
	}
	else 
		return true;
}


	
	
	



/**
 * Fake MySQL connect function using MySQLi.
 * 
 * Note this only supports the first three arguments of the original function.
 */
function mysql_connect($server = NULL, $username = NULL, $password = NULL)
{
	if ($server === NULL)
		$server   = ini_get("mysqli.default_host");
	if ($username === NULL)
		$username = ini_get("mysqli.default_user");
	if ($server === NULL)
		$password = ini_get("mysqli.default_pw");
	
	$obj = mysqli_connect($server, $username, $password);
	
	if (mysqli_connect_error() === NULL)
	{
		global $mysql_fake_connect_last_opened_link_identifier;
		$mysql_fake_connect_last_opened_link_identifier = $obj;
		return $mysql_fake_connect_last_opened_link_identifier;
	}
	else
		return false; 
}







/**
 * Fake MySQL schema-setter function using MySQLi.
 */
function mysql_select_db($database_name, $link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;
	
	return mysqli_select_db($link_identifier, $database_name);	
}




/**
 * False MySQL charset set function using MySQLi.
 */
function mysql_set_charset($charset, $link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;
	
	return mysqli_set_charset($link_identifier, $charset);
}







/**
 * Fake MySQL close-connection function using MySQLi.
 */
function mysql_close($link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;

	/* if mysqli_connect returned non-object, don't attempt to close it. */
	if (is_bool($link_identifier))
		return false;
	
	return mysqli_close($link_identifier);	
}




/**
 * Fake MySQL version-string-getter using MySQLi.
 */
function mysql_get_server_info($link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;
	
	return mysqli_get_server_info($link_identifier);
}





/**
 * Fake MySQL client-info-getter using MySQLi.
 */
function mysql_get_client_info($link_identifier = NULL)
{
	if (! mysql_fake_force_link_set($link_identifier))
		return false;
	
	return mysqli_get_client_info($link_identifier);
}





/**
 * Fake MySQL real-escape-string function using MySQLi.
 */
function mysql_real_escape_string($unescaped_string, $link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;
	
	$string = mysqli_real_escape_string($link_identifier, $unescaped_string);
	
	if (!mysqli_errno($link_identifier) )
		return $string;
	else
		return false;
}



/**
 * Fake MySQL get-insert-id function using MySQLi
 */
function mysql_insert_id($link_identifier = NULL) 
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;	
	
	return mysqli_insert_id($link_identifier);
}






/**
 * Fake MySQL count-affected-rows function using MySQLi.
 */
function mysql_affected_rows($link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;

	return mysqli_affected_rows($link_identifier);
}





/**
 * Fake MySQL error-number-getter function using MySQLi.
 */
function mysql_errno($link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return 0;
	
	return mysqli_errno($link_identifier);
}



/**
 * Fake MySQL error-string-getter function using MySQLi.
 */
function mysql_error($link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return '';
	
	return mysqli_error($link_identifier);
}





/**
 * Fake MySQL query function, with UNBUFFERED MODE, using MySQLi.
 */
function mysql_unbuffered_query($query, $link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;
		
	return mysqli_query($link_identifier, $query, MYSQLI_USE_RESULT);
}


	


/**
 * Fake MySQL query function using MySQLi.
 */
function mysql_query($query, $link_identifier = NULL)
{
	if (!mysql_fake_force_link_set($link_identifier))
		return false;
		
	return mysqli_query($link_identifier, $query);
}







/* 
 * functions for the mysql result object/resource 
 */






/* there are 3 constants asociated with mysql_fetch_array */
define('MYSQL_NUM',   1);
define('MYSQL_ASSOC', 2);
define('MYSQL_BOTH',  3);    /* NB this is intentionally = MYSQL_NUM | MYSQL_ASSOC */






/**
 * Fake MySQL-result table-row-fetching function.
 */
function mysql_fetch_array($result, $result_type = MYSQL_BOTH)
{
	switch($result_type)
	{
		case MYSQL_ASSOC:	return mysql_fetch_assoc($result);
		case MYSQL_NUM:		return mysql_fetch_row($result);
		case MYSQL_BOTH:
			$array = mysql_fetch_assoc($result);
			if (false === $array)
				return false;
			$array2 = array();
			foreach($array as $a)
				$array2[] = $a;
			return array_merge($array, $array2);
	}
}

/**
 * Fake MySQL-result table-row-fetching function.
 */
function mysql_fetch_assoc($result)
{
	$ret = mysqli_fetch_assoc($result);
	if ($ret === NULL)
		return false;
	else
		return $ret;
}

/**
 * Fake MySQL-result table-row-fetching function.
 */
function mysql_fetch_row($result)
{
	$ret = mysqli_fetch_row($result);
	if ($ret === NULL)
		return false;
	else
		return $ret;
}

/**
 * Fake MySQL-result table-row-fetching function.
 */
function mysql_fetch_object($result, $class_name = 'stdClass', $params = NULL)
{
	if ($params === NULL)
		$ret = mysqli_fetch_object($result, $class_name);
	else
		$ret = mysqli_fetch_object($result, $class_name, $params);
		
	if ($ret === NULL)
		return false;
	else
		return $ret;
}

/**
 * Fake MySQL-result table-info function.
 */
function mysql_num_rows($result)
{
	$ret = mysqli_num_rows($result);
	if ($ret === NULL)
		return false;
	else
		return $ret;
}

/**
 * Fake MySQL-result table-info function.
 */
function mysql_num_fields($result)
{
	$ret = mysqli_num_fields($result);
	if ($ret === NULL)
		return false;
	else
		return $ret;
}



/**
 * Fake MySQL-result table-info function.
 */
function mysql_field_name($result, $field_offset)
{
	$info = mysqli_fetch_field_direct($result, $field_offset);
	if (false !== $info)
		return $info->name;
	else
		return false;
}

/**
 * Fake MySQL-result move-row-pointer function.
 */
function mysql_data_seek($result, $row_number)
{
	return mysqli_data_seek($result, $row_number);
}


/**
 * Fake MySQL-result free-resultset-memory function.
 * 
 * (Needed for unbuffered results, must be called before the server can be used again;
 * can optionally be used with buffered results but usually doesn't need to be.)
 */
function mysql_free_result($result)
{
	mysqli_free_result($result);
	return true;
}




