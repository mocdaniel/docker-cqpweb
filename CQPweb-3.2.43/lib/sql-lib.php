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
 * This file contains CQPweb's database layer. These functions wrap around 
 * the various mysqli functions to ease the process of working with MySQL/MariaDB.
 */




/**
 * Wrapper for mysqli_real_escape_string().
 */
function escape_sql($string)
{
	return mysqli_real_escape_string(get_global_sql_link(), $string);
}


/**
 * Locates the general-use mysqli object and returns an object handle. 
 * 
 * @return mysqli   Object containig the link to the SQL database. 
 */
function get_global_sql_link()
{
	global $Config;
	return $Config->get_slave_link('sql');
}




/**
 * Create a mysqli link object for the CQPweb relational database, using the settings in the config file.
 * 
 * This connection will be reused, and only reconnected when necessary. 
 * 
 * @return mysqli    A MySQL connection as mysqli object.
 */
function create_sql_link()
{
	global $Config;
	// TODO: the 4 config vals should be sql_host, sql_user, sql_password, sql_schema
	
	$link = mysqli_init();
	
	/* setup some options ... first, enable LOAD LOCAL INFILE. (If L-D-L
	 * is deactivated at the mysqld end, e.g. by my.cnf, this won't help, but 
	 * won't hurt either.)   */
	mysqli_options($link, MYSQLI_OPT_LOCAL_INFILE, true);
	
	/* Let's have our floats and integers made into the right type of value. */
	if (NULL !== constant('MYSQLI_OPT_INT_AND_FLOAT_NATIVE'))
		mysqli_options($link, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
	
	/* above is only possible if we are using mysqlnd rather than libmysql for the connection;
	 * this const only exists if we have mysqlnd. Usefully, mysqli is always mysqlnd in PHP 7. */
	
	/* force flag for persistent connection */
	$host = ('p' == $Config->mysql_server[0] && ':' == $Config->mysql_server[1]) ? $Config->mysql_server : "p:{$Config->mysql_server}";
	/* unlikely to improve performance very much... but it can't hurt. */
	
	/* now we can connect! */
	if (!mysqli_real_connect($link, $host, $Config->mysql_webuser, $Config->mysql_webpass, $Config->mysql_schema))
		exiterror('CQPweb could not connect to the database engine - please try again later!');
	
	
	/* utf-8 setting is dependent on a variable defined in config.inc.php */
	if ($Config->mysql_utf8_set_required)
		if (!mysqli_set_charset($link, "utf8")) // TODO we will soon change to utf8mb4
			exiterror('Could not set character set for database connection - critical error!');
	
	/* the newer default for "sql_mode" in v 5.7 + of MySQL creates problems for various bits of CQPweb.
	 * In particular, we NEED zeros in dates: a zero date indicates "has never happened", and so on. */ 
	mysqli_query($link, "set sql_mode = \"\"");
	
	/* 
	 * TODO: in future, strict-SQL in the form of STRICT_TRANS_TABLES will continue to be default, 
	 * and NO_ZERO_DATE will again be rolled into STRICT. 
	 * We want to comply with this, longterm. So, we need to fix these issues. 
	 * First, by finding places in the code where zero dates occur and getting rid 
	 * (replace with Unix epoch date, '1970-01-01 00:00:01' as an alternative "empty" value for dates?)
	 * Second, by running with STRICT switched on until all the problems are filtered out.
	 */
	
	return $link;
}



function get_sql_cqpwebdb_version()
{
	return get_sql_value('select value from system_info where setting_name = "db_version"');
}




/* 
 * ===================
 * SQL query functions
 * ===================
 */

/**
 * Should never be called except by do_sql_query or equivalent function.
 * 
 * It inserts some additional info about what the query is, where it originated,
 * and which user is responsible and embeds it into the query as a MySQL comment.
 * 
 * @param  string  $sql   An SQL query string.
 * @return string         SQL query with an added comment for system monitoring.
 */
function append_sql_comment($sql)
{
	global $User;
	
	$u = (isset($User->username) ? $User->username : '???');
	$d = date(CQPWEB_UI_DATE_FORMAT);
	
	$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	
	/* this loop skips over the backtrace of the functions that are part of the SQL-lib such as do_sql_query();
	 * so far, the ones that are skipworthy all begin in do/delete/get/get_all/list/set. */
	for (
			$i = 1, $n = count($bt) 
			; 
			$i < $n && preg_match('/^(do|delete|get(_all)?|list|set)_(my)?sql_\w+$/', isset($bt[$i]['function']) ? $bt[$i]['function'] : '') 
			; 
			$i++
	)
		;   /* SKIP THIS LEVEL OF THE BACKTRACE*/
	
	if ($i == $n)
		$i--; /* we ran out of backtrace; go back one, even if it's an sql-lib function. */
	
	/* we've hit the caller function. Print it, if defined; if not, we want the file location one frame down. */
	if (isset($bt[$i]['function']))
		$fdesc = "Function: {$bt[$i]['function']}()" ;
	else if (isset($bt[$i-1]['file']))
		$fdesc = "In file: {$bt[$i-1]['file']} @ line {$bt[$i-1]['line']}" ;
	else if (isset($_SERVER['SCRIPT_NAME']))
		$fdesc = "Within script: ". basename($_SERVER['SCRIPT_NAME']);
	else
		$fdesc = "(from unknown point in the CQPweb code)";
	
	return "$sql \n\t/* from User: $u | $fdesc | $d */";

// 	$f = (isset($bt[$i]['function']) ? $bt[$i]['function'] : (isset($bt[$i-1]['function']) ? $bt[$i-1]['function'] :'???'));
}



/** Old-function-name wrapper for do_sql_query. */
function do_mysql_query($sql, $errs_are_fatal = true)
{
	return do_sql_query($sql, $errs_are_fatal);
}

/**
 * Does an SQL query on the CQPweb database, with error checking.
 * 
 * Auto-connects to the database if necessary.
 * 
 * Note - this function should replace all direct calls to mysqli_query,
 * thus avoiding duplication of error-checking code.
 * 
 * Returns the result resource.
 * 
 * @param  string              $sql             The SQL to run.
 * @param  bool                $errs_are_fatal  If true, CQPweb will abort on query error (this is the norm and the default). 
                                                If false, the caller is allowed to deal with the error, indicated by false!
 * @return mysqli_result|bool                   If the query yields a result, then the mysqli_result object is returned.
 *                                              Otherwise, boolean true. It's not usually false, because if it would be false, the function aborts.
 *                                              However, if the second param (errors are fatal) is set to false, then you CAN get a false return.
 */
function do_sql_query($sql, $errs_are_fatal = true)
{
	$link = get_global_sql_link();
	
	$sql = append_sql_comment($sql);
	
	squawk("About to run SQL:\n\t$sql");
	
	$start_time = microtime(true);
	
	$result = mysqli_query($link, $sql);
	
	if (!$result) 
	{
		if ($errs_are_fatal)
			exiterror_sqlquery(mysqli_errno($link), mysqli_error($link), $sql);
		else
			return false;
	}
	
	squawk("SQL ran successfully in " . number_format(microtime(true) - $start_time, 3) . " seconds.");
	
	return $result;
}







/**
 * Does an SQL query and puts the result into an output file.
 * 
 * This works regardless of whether the server program (mysqld)
 * is allowed to write files or not.
 * 
 * The sql $query should be of the form "select [something] FROM [table] [other conditions]" 
 * -- that is, it MUST NOT contain "into outfile $filename", and the FROM must be in capitals. 
 * 
 * The output file is specified by $filename - this must be a full ABSOLUTE path.
 * (Use realpath() to make sure of this!)
 * 
 * This function can be used to create a dump file (new format post CWB 2.2.101)
 * for input to CQP e.g. in the creation of a postprocessed query. 
 * 
 * Its return value is the number of rows written to file. In case of problem,
 * exiterror_* is called here.
 */
function do_sql_outfile_query($sql, $filename)
{
	global $Config;
	
	$link = get_global_sql_link();
	
	$sql = append_sql_comment($sql);
	
	if ($Config->mysql_has_file_access)
	{
		/* We should use INTO OUTFILE */
		
		$into_outfile = 'INTO OUTFILE "' . escape_sql($filename) . '" FROM ';
		$replaced = 0;
		$sql = str_replace("FROM ", $into_outfile, $sql, $replaced);
		
		if (1 != $replaced)
			exiterror("This outfile query either does not contain FROM, or contains multiple instances of FROM:\n\t$sql");
		
		squawk("About to run SQL:\n\t$sql\n");
		$result = mysqli_query($link, $sql);
		if ($result == false)
			exiterror_sqlquery(mysqli_errno($link), mysqli_error($link), $sql);
		else
		{
			squawk("The query ran successfully.\n");
			return mysqli_affected_rows($link);
		}
	}
	else 
	{
		/* we cannot use INTO OUTFILE, so run the query, and write to file ourselves */
		
		squawk("About to run SQL:\n\t$sql\n");
// 		$result = mysql_unbuffered_query($query, $mysql_link); /* avoid memory overhead for large result sets */
		$result = mysqli_query($link, $sql, MYSQLI_USE_RESULT);  /* avoid memory overhead for large result sets */
		if (!$result)
			exiterror_sqlquery(mysqli_errno($link), mysqli_error($link), $sql);
		squawk("SQL ran successfully.\n");
		
		if (!($fh = fopen($filename, 'w'))) 
			exiterror("MySQL outfile query: Could not open file for write ( $filename )");
		
		$rowcount = 0;
		
		while ($row = mysqli_fetch_row($result)) 
		{
			fputs($fh, implode("\t", $row) . "\n");
			$rowcount++;
		}
		
		fclose($fh);
		
		return $rowcount;
	}
}



/**
 * Loads a specified text file into the given MySQL table.
 * 
 * Note: this is done EITHER with LOAD DATA (LOCAL) INFILE, OR
 * with a loop across the lines of the file.
 * 
 * The latter is EXTREMELY inefficient, but necessary if we're 
 * working on a box where LOAD DATA (LOCAL) INFILE has been 
 * disabled.
 * 
 * 
 * @param  string $table       Table to load to.
 * @param  string $filepath    Path to file to load from.
 * @param  bool   $no_escapes  If $no_escapes is true, "FIELDS ESCAPED BY" behaviour is 
 *                             set to an empty string (otherwise it is not specified).
 *                             This defaults to true. 
 * @return bool                The boolean returned by the (last) update/import query, if
 *                             all went well; false in case of error. 
 */
function do_sql_infile_query($table, $filepath, $no_escapes = true)
{
	global $Config;
	
	if (!is_file($filepath))
		return false;
	
	$table = escape_sql($table);
	
	/* massive if/else: overall two branches. */
	
	if (!$Config->mysql_infile_disabled)
	{
		/* the normal sensible way */
		
		$filepath = escape_sql(realpath($filepath)); /* because "infile" requries an absolute path when non-LOCAL */
		
		$sql = "{$Config->mysql_LOAD_DATA_INFILE_command} '$filepath' INTO TABLE `$table`";
		
		if ($no_escapes)
			$sql .= ' FIELDS ESCAPED BY \'\'';
		
		return do_sql_query($sql);
	}
	else
	{
		/* the nasty hacky workaround way */
		
		/* first we need to find out about the table ... */
		$fields = array();
		
		/* note: we currently allow for char, varchar, and text as "quote-needed"
		 * types, because those are the ones CQPweb uses. There are, of course,
		 * others. See the MySQL manual. */
		
		$result = do_sql_query("describe `$table`");
		
		while ($f = mysqli_fetch_object($result))
		{
			/* format of "describe" is such that "Field" contains the fieldname,
			 * and "Type" its type. All types should be lowercase, but let's make sure */
			$f->Type = strtolower($f->Type);
			$quoteme =    /* quoteme equals the truth of the following long condition. */
				(
					substr($f->Type, 0, 7) == 'varchar'
					||
					$f->Type == 'text'
					||
					substr($f->Type, 0, 4) == 'char'
				);
			$fields[] = array('field' => $f->Field, 'quoteme' => $quoteme);	
		}
		
		$source = fopen($filepath, 'r');
		
		/* loop across lines in input file */
		while (false !== ($line = fgets($source)))
		{
			// escaping is now done per-value.
//			/* necessary for security, but might possibly lead to data being
//			 * escaped where we don't want it; if so, tant pis */
//			$line = escape_sql($line);
			$data = explode("\t", rtrim($line, "\r\n"));
			
			$blob1 = $blob2 = '';
			
			for ( $i = 0 ; true ; $i++ )
			{
				/* require both a field and data; otherwise break */
				if (!isset($data[$i], $fields[$i]))
					break;
				$blob1 .= ",`{$fields[$i]['field']}`";
				
				if ( (! $no_escapes) && $data[$i] == '\\N' )
					/* data for this field is NULL, so type doesn't matter */
					$blob2 .= ', NULL';
				else 
				{
					if ( $fields[$i]['quoteme'] )
						/* data for this field needs quoting (string) */
						$blob2 .= ",'" . escape_sql($data[$i]) . "'";
					else
						/* data for this field is an integer or like type */
						$blob2 .= "," . (int)$data[$i];
				}
			}
			
			$blob1 = ltrim($blob1, ', ');
			$blob2 = ltrim($blob2, ', ');
			
			$result = do_sql_query("insert into `$table` ($blob1) values ($blob2)");
		}
		
		fclose($source);
		
		return $result;
	}
	/* end of massive if/else that branches this function */
}




/**
 * Dumps out a reasonably-nicely-formatted representation of an
 * arbitrary MySQL query result.
 * 
 * For debug purposes, or for when we have not yet written the code for a nicer layout.
 * 
 * @param mysqli_result $result  A result returned by do_sql_query().  
 */ 
function print_sql_result_dump(mysqli_result $result) 
{
	/* print column headers */
	$table = "\n\n<!-- SQL RESULT DUMP -->\n\n" . '<table class="concordtable fullwidth"><tr>';
	for ( $i = 0 ; $i < mysqli_num_fields($result) ; $i++ )
		$table .= "<th class='concordtable'>" . mysqli_fetch_field_direct($result, $i)->name . "</th>";
	$table .= '</tr>';
	
	/* print rows */
	while ($row = mysqli_fetch_row($result)) 
	{
		$table .= "<tr>";
		foreach ($row as $r)
			$table .= "<td class='concordgeneral' align='center'>$r</td>\n";
		$table .= "</tr>\n";
		/* stop REALLY BIG tables from exhausting PHP's allowed memory:
		 * allow this string to be 5MB long max (Even that's a push!) */
		if (5242880 <= strlen($table))
		{
			$table .= '</table>
					<table class="concordtable fullwidth"><tr><th class="concordtable">Table is too big to show, TRUNCATED!</td></tr>
					';
			break;
		}
	}
	
	return $table . "</table>\n\n";
}

/*
 * The above function and the below do the same thing. And yes, I don't know which is the right one. 
 * The above has truncation wheereas the below doesn;t.
 */

/**
 * Render contents of a query result as an HTRML table
 * (to be examined ad hoc). 
 * 
 * @param  mysqli_result $result  Query result to dump. 
 * @return string                 HTML of table. 
 */
function dump_sql_result(mysqli_result $result)
{
	$s = '<table class="concordtable"><tr>';
	$n = mysqli_num_fields($result);
	for ( $i = 0 ; $i < $n ; $i++ )
		$s .= "<th class='concordtable'>" 
			. mysqli_fetch_field_direct($result, $i)->name
			. "</th>"
			;
	$s .=  '</tr>
		';
	
	while ($r = mysqli_fetch_row($result))
	{
		$s .= '<tr>';
		foreach($r as $c)
			$s .= "<td class='concordgeneral'>$c</td>\n";
		$s .= '</tr>
			';
	}
	$s .= "</table>\n";
	
	return $s;
}


/** This function chops off the seconds from an SQL timestamp, creating something easier on the eyes. */
function print_sql_datetime($timestamp)
{// TODO, change to "render"?
	return substr($timestamp, 0, -3);
}



/**
 * Performs an SQL query and returns the first value in the first record that results.
 * A simplification of do_sql_query() for SELECt statemetns that we know will return just one value. 
 * 
 * @param  string  $sql  The SQL to run.
 * @return mixed         The single value (or Boolean false for no results, or Boolean true/false if
 *                       the query wasn't a SELECT).
 *                       Test as strict comparison ( === false); SQL bools are in integer columns,
 *                       so this will always detect errors. 
 */
function get_sql_value($sql)
{
	$result = do_sql_query($sql);

	if (is_bool($result))
		return $result;

	switch (mysqli_num_rows($result))
	{
	case 0:
		return false;

	default:
		trigger_error("SQL statement `$sql` ashould return a single value (in get_sql_value())", E_USER_WARNING); // unify w/squawek?
		/* intentional fall-through! after the error, return the first cell of the first row. */

	case 1:
		return mysqli_fetch_row($result)[0];
	}
}

/**
 * Performs an SQL query and returns an array the value of the first column per record.
 * 
 * @param  string $sql      The SQL to run.
 * @return array            The array of simplex values (string/int/float/NULL), or Boolean true/false if
 *                          the query wasn't a SELECT). 
 */
function list_sql_values($sql)
{
	if (is_bool($result = do_sql_query($sql)))
		return $result;
	
	$a = [];
	
	while ($r = mysqli_fetch_row($result))
		$a[] = $r[0];
	
	mysqli_free_result($result);
	
	return $a;
}

/**
 * Creates a hash (or map) from the result of an SQL query. 
 * 
 * @param  string $sql       The SQL to run.
 * @param  string $keyfield  Field in the result to use as array keys.
 *                           It should be an integer or string field,
 *                           with unique values (unless you *want* to overwrite).
 * @param  string $valfield  Field in the result to use as array values.
 * @return array             Associative array (hash/map) mapping the 
 *                           key column to the value column. 
 */
function list_sql_values_as_map($sql, $keyfield, $valfield)
{
	if (is_bool($result = do_sql_query($sql)))
		return $result;
	
	$aa = [];
	
	while ($o = mysqli_fetch_object($result))
		$aa[$o->$keyfield] = $o->$valfield;
	
	mysqli_free_result($result);
	
	return $aa;
}


/**
 * Performs an SQL query and returns an object of the first row of the result.
 * 
 * @param  string  $sql     The SQL to run.
 * @param  string  $class   String containing the name of a class to pass to mysqli_fetch_object().
 *                          If NULL (default), then stdClass.
 * @return object           Object, possibly of the class specified. Or false in case of error.
 */
function get_sql_object($sql, $class = NULL)
{
	if (is_bool($result = do_sql_query($sql)))
		return $result;
	return is_null($class) ? mysqli_fetch_object($result) : mysqli_fetch_object($result, $class);
}


/**
 * Performs an SQL query and returns an array of objects (one object per row; extra object setup available
 * via the $process_object() function.
 * 
 * @param  string     $sql                The SQL to run.
 * @param  string     $keyfield           If set, then the column of this name (if present) will be used for 
 *                                        the keys of the return array. Otherwise, keys are auto-allocated integers.
 * @param  string     $class              String containing the name of a class to pass to mysqli_fetch_object().
 *                                        If NULL (the default), then stdClass.
 * @param  callable   $process_object     Function, taking an object of class $class, and returning the same object.
 *                                        (It's a function to process each object, not an object representing a process!)
 * @return array|bool                     Array of objects, in order determined by the SQL query's sort. 
 *                                        Or Boolean true/false if the query wasn't a SELECT). 
 */
function get_all_sql_objects($sql, $keyfield = NULL, $class = NULL, callable $process_object = NULL)
{
	if (is_null($class))
		$class = 'stdClass';
	
	$result = do_sql_query($sql);
	
	if (is_bool($result))
		return $result;
	
	$a = [];
	
	while ($o = mysqli_fetch_object($result, $class))
	{
		if (is_callable($process_object))
			$o = $process_object($o);
		if ($keyfield)
			$a[$o->$keyfield] = $o;
		else
			$a[] = $o;
	}
	
	mysqli_free_result($result);
	
	return $a;
}


/**
 * Wrapper around mysqli_insert_id, to keep all the process-resource access in this library.
 */
function get_sql_insert_id()
{
	return mysqli_insert_id(get_global_sql_link());
}


/**
 * Wrapper around mysqli_affected_rows, to keep all the process-resource access in this library.
 */
function get_sql_affected_rows()
{
	return mysqli_affected_rows(get_global_sql_link());
}


/**
 * Gets the size in bytes of data plus indexes for a named MySQL table. 
 * 
 * (Plus a high-end estimate of 16 KB per table to allow for the .frm file, 
 * which we can't directly measure. It seems like recent MySQL has got rid of
 * .frm files in version 8, but MariaDB seems to still use them (as a cache.)
 * 
 * If $table contains a "%", then the sum of the sizes of all matching tables
 * are returned (the "like" operator is used rather than "=" or "regexp"). 
 * 
 * Note that every table size is checked using "analyze table `table`".
 * 
 * @param  string $table  Name of table (or a pattern for use with SQL LIKE).
 * @return int            Size in bytes used by table (as known to the SQL server).
 *                        Zero is returned if no tables exist matching the argument.
 */
function get_sql_table_size($table)
{
	global $Config;
	
	$table = escape_sql($table);
	
	/* update the size information using "analyze". */
	$analyse_list = '';
	$result = do_sql_query("show tables like '$table'");
	if (1 > mysqli_num_rows($result))
		return 0;
	while ($r = mysqli_fetch_row($result))
		$analyse_list .= ", `{$r[0]}`";
	mysqli_free_result($result);
	$analyse_list = substr($analyse_list, 2);
	
	do_sql_query("analyze NO_WRITE_TO_BINLOG table $analyse_list");
	/* note that without the above step, out-of-date numbers might be reported for the data length. */
	
	$sql = "select count(*) as n_tables, sum(INDEX_LENGTH) as bytes_i, sum(DATA_LENGTH) as bytes_d 
				from information_schema.TABLES 
				where TABLE_SCHEMA='{$Config->mysql_schema}' and TABLE_NAME like '$table'";
	$o = mysqli_fetch_object(do_sql_query($sql));
	
	/* guestimate: 16K per table, plus sum of INDEX_LENGTH, plus sum of DATA_LENGTH. */
	return ($o->n_tables * 16384) + $o->bytes_i + $o->bytes_d;
}


/**
 * Gets the real, on-disk size of a MySQL table by assessing its tablespace file info. 
 * 
 * (Plus a high-end estimate of 16 KB to allow for the .frm file, 
 * which we can't directly measure.)
 * 
 * @param  string $table    Name of table.
 * @return int              Size in bytes, or false for error. 
 */
function get_sql_table_real_size($table)
{
	global $Config;
	
	if (!can_get_sql_real_size())
		return false;
	
	$inno_table = get_sql_innodb_tablespaces_name();
	$table = escape_sql($table);
	
	/* confusingly, ALLOCATED_SIZE is the actual on-disk-size. FILE_SIZE is some abstract concept of how big it seems/ought to be. */
	$o = mysqli_fetch_object(do_sql_query("select FILE_SIZE, ALLOCATED_SIZE from INFORMATION_SCHEMA.`$inno_table` where NAME = '{$Config->mysql_schema}/$table'"));
// show_var($o);
	
	return 16384 + $o->ALLOCATED_SIZE;
}

/**
 * Gets the real, on-disk size of an SQL table as indicated by the filesystem. 
 * 
 * This will only work if the current user has permission to get the filesize 
 * within the server's data-directory. (Normally, in the context of a CLI script,
 * which can thus be run as root.) It also requires InnoDB file-per-table mode.
 * If there is just one single ibdata file, then this function will only measure
 * the .frm file (if any).
 * 
 * @param  string $table    Name of table.
 * @return int              Size in bytes, or false for error. 
 */
function get_sql_table_filesystem_size($table)
{
	/* a note from the manual: 

	   ===================================================================
	   INFORMATION_SCHEMA and Privileges
	   ---------------------------------
	   Each MySQL user has the right to access these tables, but can see 
           only the rows in the tables that correspond to objects for which 
           the user has the proper access privileges.
	   ===================================================================

           So, it IS possible to use information_schema to get information 
           on the file location, as long as we are in file-per-table mode.

	   Note, though, that to access INFORMATION_SCHEMA.INNODB_DATAFILES
	   we need the PROCESS privilege. 
	 */

	/* our stating point. */
	$fullsize = 0;

	/* InnoDB: look up .ibd file location. */
	if ('innodb' == get_sql_table_engine($table))
	{
		/* if the db is in one tablespace, there's nothing we can do here. */
		if (!is_sql_file_per_table())
			return false;

		/* this is the .ibd file */
		if (false !== ($filepath = get_sql_table_filesystem_path($table)))
			if (false !== ($ibd_size = @filesize($filepath)))
				$fullsize += $ibd_size;
		/* there might be no .ibd file if the table is empty. In which case, size so far is 0. */
	}

	/* Now: look in schema standard location for little files and/or myisam files (myd/myi) */
	$db_dir = get_sql_schema_directory();

	/* if possible, add the size of the .frm file, the .isl if there is one, and the .myd/myi (for myisam)  */
	if (is_readable($db_dir))
		foreach (glob("$db_dir/$table.*") as $f)
			if (preg_match('~\.(isl|frm|my[di])$~i', $f) && false !== ($add = @filesize($f)))
				$fullsize += $add;

	return $fullsize;
}

function get_sql_schema_directory($schema = NULL)
{
	if (is_null($schema))
	{
		global $Config;
		$schema = $Config->mysql_schema;
	}
	$schema_dir = rtrim(get_sql_datadir(), "\\/") . '/' . $schema;
	return $schema_dir;
}

/** 
 * Ascertains the engine (InnoDB/MyISAM) used for a given table. 
 * @return string             Either "innodb" or "myisam" (CQPweb only creates such tables).
 */
function get_sql_table_engine($table)
{
	$table = escape_sql($table);
	$result = do_sql_query("show table status like '$table'");
	if (1 > mysqli_num_rows($result))
		exiterror("Requested engine of a non-existent SQL table!");
	return strtolower(mysqli_fetch_assoc($result)['Engine']);
}


/**
 * This function works best on innodb tables. If $table is not InnoDB,
 * then the path to the MYD file is returned. 
 */
function get_sql_table_filesystem_path($table)
{
	global $Config;

	$full_table_name_sql = $Config->mysql_schema . '/' . escape_sql($table);

	$filepath = get_sql_value("select PATH 
					from `information_schema`.`INNODB_SYS_TABLES`         as t 
					LEFT JOIN `information_schema`.`INNODB_SYS_DATAFILES` as d 
					on t.`SPACE`=d.`SPACE` 
					where `NAME` = '$full_table_name_sql'"
					);

	/* we don't have the process privilege... so we just have to guess. */
	if (false === $filepath)
	{
		if ('innodb' == get_sql_table_engine($table))
			return rtrim(get_sql_datadir(), "\\/") . '/' . $Config->mysql_schema . '/' . $table . '.ibd';
		else 
			return rtrim(get_sql_datadir(), "\\/") . '/' . $Config->mysql_schema . '/' . $table . '.MYD';
	}

	/* relative path within standard datadir (the usual case); a full path = external tablspace */
	if ('.' == $filepath[0] && '/' == $filepath[1])
		$filepath = rtrim(get_sql_datadir(), "\\/") . substr($filepath, 1);

	return $filepath;
}


/**
 * More efficient than repeated calls to get_sql_table_(real)size().
 * @return array   Hash mapping table name to stdClass with file_size, allocated_size, size. 
 */
function get_all_sql_table_sizes($order = 'ratio')
{
	global $Config;
	
	if (!can_get_sql_real_size())
		return false;
	
	$order_sql = '';
	if ('ratio' == $order)
		$order_sql = 'ORDER BY ratio desc';
	if ('name'  == $order)
		$order_sql = 'ORDER BY t_name asc';
	if ('size'  == $order)
		$order_sql = 'ORDER BY size desc';
	
	$inno_table   = '`information_schema`.`' . get_sql_innodb_tablespaces_name() . '`';
	$schema_table = '`information_schema`.`TABLES`';
	
	$result = do_sql_query("select 
									$schema_table.`TABLE_NAME`               as t_name, 
									16384 + $inno_table.`FILE_SIZE`          as file_size, 
									16384 + $inno_table.`ALLOCATED_SIZE`     as allocated_size, 
									16384 
									      + $schema_table.`INDEX_LENGTH` 
									      + $schema_table.`DATA_LENGTH`      as size,
									(CAST(size AS FLOAT)/CAST(allocated_size AS FLOAT)) 
									                                         as ratio
								from $inno_table join $schema_table 
									on $inno_table.`NAME`, '{$Config->mysql_schema}/', '')
									= $schema_table.`TABLE_NAME`
									$order_sql ");

	$list = array();
	while ($o = mysqli_fetch_object($result))
		$list[$o->t_name] = $o;

	return $list;
}


/**
 * NB: a "real_size" means one derived from the tablespace stats. 
 */
function can_get_sql_real_size()
{
	static $has_allocated_size   = false;
	static $has_file_size        = false;
	static $grant_to_read_given  = false;
	static $inno_table           = NULL;
	
	if (!is_sql_file_per_table())
		return false;
	
	/* check conditions for knowing the real file size. */
	if (empty($inno_table))
	{
		$inno_table = '`INFORMATION_SCHEMA`.`' . get_sql_innodb_tablespaces_name() . '`';
		
		$result = do_sql_query("select `NAME` from $inno_table limit 1");
		if (!($grant_to_read_given = (0 < mysqli_num_rows($result))))
			return false;
		/* NB, it is apparently the "PROCESS" privilege that is needed to be able to query this table. 
		 * This little test query determines whether we have it. */
		
		$result = do_sql_query("show columns from $inno_table LIKE 'ALLOCATED_SIZE'");
		if (!($has_allocated_size = (0 < mysqli_num_rows($result))))
			return false;
		
		$result = do_sql_query("show columns from $inno_table LIKE 'FILE_SIZE'");
		if (!($has_file_size = (0 < mysqli_num_rows($result))))
			return false;
		
		return true;
	}
	
	/* check cached conditions. */
	return ( $has_allocated_size && $has_file_size && $grant_to_read_given );
}

/**
 * The table in the INFORMATION_SCHEMA DB that contains file sizes has two different names.
 * It could be INNODB_TABLESPACES, or INNODB_SYS_TABLESPACES.
 * This function checks the server version so that we use the correct name. 
 * The change was made, I believe, in MySQL v8.0. MariaDB hasn't done it yet.
 * 
 * @return  string   Name of a table within INFORMATION_SCHEMA.
 */
function get_sql_innodb_tablespaces_name()
{
	$inno_table = 'INNODB_TABLESPACES';
	list($major_v) = explode('.', $server = mysqli_get_server_info(get_global_sql_link()));
	
	/* less than version 8, or any version (so far) of MariaDB: use old name */
	if (8 > (int)$major_v || preg_match('/MariaDB/i', $server))
		$inno_table = 'INNODB_SYS_TABLESPACES';
	
	return $inno_table;
}
// TODO -- all three of INNODB_TABLES, INNODB_TABLESPACES, AND INNODB_DATAFILES 
// (A) need the PROCESS privilege (GRANT PROCESS on *.* TO ...)
// (b) have old names: INNODB_SYS_TABLES, INNODB_SYS_TABLESPACES, AND INNODB_SYS_DATAFILES 



/**
 * Ascertains whether InnoDB is in file-per-table mode. 
 * 
 * @return bool
 */
function is_sql_file_per_table()
{
	return 'ON' == mysqli_fetch_row(do_sql_query("show variables like 'innodb_file_per_table'"))[1];
}


function optimise_sql_table($table)
{
	$table = escape_sql($table);
	return do_sql_query("optimize NO_WRITE_TO_BINLOG table `$table`");
}

function optimise_all_sql_tables()
{
	$result = do_sql_query("show tables");
	while ($r = mysqli_fetch_row($result))
	{
		squawk(date(DATE_RFC2822) . "\tAbout to optimise {$r[0]}...\n");
		optimise_sql_table($r[0]);
	}
}


/**
 * Get the path to the data directory of the SQL server. 
 */
function get_sql_datadir()
{
	return get_sql_value("select @@datadir");
}



// // can prob get rid of this. 
// /**
//  * 
//  * @param  string   $table  Name of table
//  * @param  string   $type   'ibd' or 'frm'
//  * @return int              Size in bytes of that file.
//  */
// function get_sql_table_filesize($table, $type)
// {
// 	global $Config;
	
// 	if ('ibd' != $type && 'frm' != $type)
// 		exiterror("Bad mysql table file extension.");
	
// 	$path = get_mysql_datadir() . '/' . $Config->mysql_schema . '/' . $table . '.' . $type;
// 	if (is_readable($path))
// 		return filesize($path); // TODO, assumes that the MySQL server is on the same machine. 
// 	else
// 	{
// $d="path $path is not readable";
// show_var($d);
// 		return false;
// 	}
// }





/**
 * Deletes an SQL table, as long as it is not one of the system tables.
 * 
 * This function is intended to allow cleanup of stray tables.
 * 
 * Better than using "drop table if exists" in the raw. 
 * 
 * @param string $table  Table name.
 */
function delete_sql_table($table)
{
	$table = escape_sql($table);
	
	$not_allowed = get_sql_create_tables();
	
	if (isset($not_allowed[$table]))
		return;
	
	do_sql_query("drop table if exists `$table`");
}






/**
 * Function to set the SQL database setup to its initialised form.
 */
function do_sql_total_reset()
{
	global $User;
	if (!$User->is_admin())
		return;
	
	/* just in case, we DO NOT drop tables we know nothing about */
	foreach (array( 'db_', 
					'freq_corpus_', 
					'freq_sc_',
					'freq_text_index_',
					'text_metadata_for_',
					'__tempfreq_', 
					'__freqmake_temptable'
					)
			as $prefix)
	{
		$result = do_sql_query("show tables like '$prefix%'");
		while ($r = mysqli_fetch_row($result)) 
			do_sql_query("drop table if exists {$r[0]}");
	}
	
	/* we need to set the session timezone to UTC in order to get
	 * zero timestamps in table definitions to behave themselves. */
	do_sql_query("set time_zone = '+00:00'");
	
	foreach (get_sql_create_tables() as $name => $statement)
	{
		do_sql_query("drop table if exists `$name`");
		do_sql_query($statement);
	}
	
	foreach (get_sql_recreate_extras() as $statement)
		do_sql_query($statement);

	do_sql_query("set time_zone = 'SYSTEM'");
	/* note it's only the session timezone we've touched. 
	 * so no real problem if the above is not the original setting. */
}



/* 
 * The next two functions are really just for convenience.
 * 
 * Note also, they apparently have no effect on InnoDB tables
 * (which nowadays, unlike in the early days of CQPweb, are default).
 */

/** Turn off indexing for a given database table. */
function disable_sql_table_keys($table)
{
	do_sql_query("alter table `" . escape_sql($table) . "` disable keys");
}
/** Turn on indexing for a given database table. */
function enable_sql_table_keys($table)
{
	do_sql_query("alter table `" . escape_sql($table) . "` enable keys");
}


/** 
 * Tests whether an SQL timestamp contains a zero-ish value
 * (i.e., timestamp never got set to the present).
 * 
 * @param  string $timestamp   An SQL timestamp.
 * @return bool
 */
function sql_timestamp_is_zero($timestamp)
{
	$test = substr($timestamp, 0, 4);
	return ('0000' == $test || '1970' == $test);
}


/**
 * Gets the string identifier of the collation to be used by dynamic databases
 * built for a particular corpus.
 * 
 * Argument is a corpus object (from the DB).
 */
function deduce_corpus_sql_collation($corpus_info)
{
	/* this encapsulates collation setup, so when we use more than just these 2,
	 * we can simply set matters up here, and everything else should cascade.
	 */
	return $corpus_info->uses_case_sensitivity ? 'utf8_bin' : 'utf8_general_ci' ;
	//TODO mb4, mb4
}

function get_sql_handle_collation($fuzzy = false)
{
	return ($fuzzy ? 'ascii_general_ci' : 'ascii_bin');
}


//============================================================mb4 stuff follows.

/**
 * Returns a list of the collations available on your SQL server for utfbmb4.
 * 
 * @return array    Array of SqlMb4Collation objects.
 */
function available_sql_collations()
{
	$list = [];
	
	foreach(list_sql_values("show collation where Charset = 'utf8mb4'") as $s)
		$list[] = new SqlMb4Collation($s);
	
	return $list;
}


// TODO note this func does not work yet.
// as illustrated by ....
//./cqpweb expound_best_sql_collations 0
/**
 * Returns the name of the best available collation.
 * If the server is old enough, accent sensitivity might not be
 * matched. If this is a concern, check the out-param. 
 * 
 * @param bool $case_sensitive         Collation must be case sensitive.
 * @param bool $accent_sensitive       Collation must be accent sensitive.
 * @param bool $match_found            Optional out-param: set to true if both 
 *                                     criteria were matched, otherwise to false.
 *                                     If a criterion is unmatched, it will be 
 *                                     accent sensitivity; case sensitivity is 
 *                                     always matchable. 
 */
function best_sql_collation($case_sensitive, $accent_sensitive, &$match_found = NULL)
{
	$list = available_sql_collations();
	
	foreach ($list as $k => $o)
		if ($o->case_sensitive !== $case_sensitive || $o->accent_sensitive != $accent_sensitive)
			unset($list[$k]);
	
	/* nothing left? We must be working with really old SQL server. */
	if (empty($list))
	{
		$match_found = false;
		return $case_sensitive ? 'utf8mb4_bin' : 'utf8mb4_unicode_ci';
	}
	
	/* otherwise: we want the best available of the options herein. */
	$chosen = most_recent_unicode_sql_collation($list);
	
	$match_found = true;
	
	return $chosen->collation_id;
}

/**
 * Given a set of MySQL collations, all assumed to be the same, but different unicode versions, 
 * this function returns the most recent.
 * 
 * @param  array           $set   Array of SqlMbCollation objects
 * @return SqlMb4Collation        The element in the array with the most recent Unicode version.
 *                                Or bool false if $set was empty.
 */
function most_recent_unicode_sql_collation($set)
{
	if (empty($set))
		return false;

	usort($set, 'SqlMb4Collation::sort_by_version');
	
	return array_pop($set);
}

/**
 * Use on the command line to find out what the "best" collations of your SQL server are.
 *  
 * @param  bool    $return_outcome   If true, informative text is returned; else it's printed.
 * @return string                    Monospace-printable table of info.
 */
function expound_best_sql_collations($return_outcome = true)
{
	$best_as_cs = best_sql_collation(true , true );
	$best_as_ci = best_sql_collation(true , false);
	$best_ai_cs = best_sql_collation(false, true );
	$best_ai_ci = best_sql_collation(false, false);
	
	$report = <<<END_TEXT

	================================================================
	Diac-s'tive | Case-s'tive | Flag | Best available collation
	================================================================
	YES         | YES         |      | (as_cs)  $best_as_cs
	YES         | NO          | %c   | (as_ci)  $best_as_ci
	NO          | YES         | %d   | (ai_cs)  $best_ai_cs
	NO          | NO          | %cd  | (ai_ci)  $best_ai_ci
	================================================================


END_TEXT;

	if ($return_outcome)
		return $report;
	else 
		echo $report;
}




class SqlMb4Collation
{
	public $collation_id;
	public $language; /**< empty string if not a specific lang. No attempt to standardise language names/codes. */
	public $unicode_version_string;
	public $unicode_version_sort;
	public $accent_sensitive;
	public $case_sensitive;
	public $is_general;  /**< ie, it's utf8mb4_general_ci rather than utf8mb4_unicode_ci or a language. */
	
	/** mapper from elements of the collation string to the unicode version
	 *  they imply; the target element is a tuple of versions tring and sortable integer. */
	public static $unicode_version_map = [
			/*
			 * From the MySQL manual:
			 * ======================
			 * Unicode collations based on UCA versions higher than 4.0.0 include the 
			 * version in the collation name. Examples:
			 *  - utf8mb4_unicode_520_ci is based on UCA 5.2.0 weight key
			 *  - utf8mb4_0900_ai_ci is based on UCA 9.0.0 weight keys
			 */
			''     => ['4.0.0', 400],
			'520'  => ['5.2.0', 520],
			'0900' => ['9.0.0', 900],
	];
	/**
	 * Create a collation analysis.
	 * @param  string $string  A COLLATION identifier string from the SQL DB.
	 * @return bool            False in case of error.
	 */
	public function __construct($string)
	{
		$string = strtolower($string);
		$this->collation_id = $string;
		
		/* chop the "kana" sensitive flag if present */
		if (preg_match('/^(utf8mb4_.*?)_ks$/', $string, $m))
			$string = $m[1];
		
		if (!preg_match('/^(utf8mb4_.*?)_(bin|cs|ci)$/', $string, $m))
			return false;
		switch ($m[2])
		{
		case 'bin': /* for our purposes here, bin == as_cs */
		case 'cs':
			$this->case_sensitive = true;
			$this->accent_sensitive = true; /* cs implies as ... */
			break;
		case 'ci':
			$this->case_sensitive = false;
			$this->accent_sensitive = false; /* ci could be ai or as; if not stated, then ai. */
			break;
		}
		$string = $m[1];
		
		if (preg_match('/^(utf8mb4.*?)_(as|ai)$/', $string, $m))
		{
			if ('as' == $m[2])
				$this->accent_sensitive = true;
			else if('ai' == $m[2])
				$this->accent_sensitive = false;
			/* if not specified - use default from above. */
			$string = $m[1];
		}
		
		/* next bit MAY be a unicode version */
		if (preg_match('/^(utf8mb4.*?)_(\d+)$/', $string, $m))
		{
			$string = $m[1];
			$key = $m[2];
		}
		else 
			$key = '';
		list($this->unicode_version_string, $this->unicode_version_sort) = self::$unicode_version_map[$key];

		/* what's left? language, or "unicode", or "general", or just "" - which like "unicode" means "no specific lang". */
		$this->language = substr($string, 8);
		if ('unicode' == $this->language)
			$this->language = '';
		else if ('general' == $this->language)
		{
			$this->is_general = true;
			$this->language = '';
		}
		else 
			$this->is_general = false;
	}
	
	public static function sort_by_version(SqlMb4Collation $a, SqlMb4Collation $b)
	{

		if ($a->unicode_version_sort > $b->unicode_version_sort)
			return 1;
		if ($a->unicode_version_sort < $b->unicode_version_sort)
			return -1;
		return 0;
	}
}

