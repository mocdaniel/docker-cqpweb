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
 * "Databases" are MySQL tables created on the fly that store some subset of 
 * p-attribute information for a query. Queries on these tables are then used
 * to implement several of the analysis tools (sort, distribution, collocation,
 * and frequency breakdown.)
 * 
 * This file contains functions for dealing with the creation and management of
 * these databases.
 */


/**
 * Object containing variables specifying a database type.
 * 
 * Each type also enfolds the "extra" variables that need to be passed to the database
 * creation function - to avoid that function having to make use of global variables.
 * 
 * SETUP: call new DbType() with the first argument being the appropriate DB_TYPE constant.
 * 
 * If there are additional needed arguments, then add those after that. The only case that
 * currently exists is when the type is COLLOC. In that case, the call is:
 * 
 * $t = new DbType(DB_TYPE_COLLOC, $colloc_atts, $colloc_range);
 * 
 * where $colloc_atts is string containing a '~'-delimited list of p-attributes 
 * (annotation handles).
 */
class DbType
{
	/** Contains one of the type constants: DB_TYPE_SORT and friends. */
	public $type;
	/* longterm note: it would make sense to use these in the DB rather than the little strings currently used. */
	
	/** contains the little string which is used in some cases to represent the type constant. */
	public $str;
	
	/* Specs only used for colloc */
	
	public $colloc_atts  = NULL;
	public $colloc_range = NULL;
	
	
	/**
	 * An array of valid type constants.
	 */
	private static $valid_types = array(DB_TYPE_DIST, DB_TYPE_COLLOC, DB_TYPE_SORT, DB_TYPE_CATQUERY, DB_TYPE_PARLINK);
	
	
	/**
	 * Constructor. 
	 * 
	 * Parameters after the first are variable; see documentation of the class.
	 * 
	 * @param int  $type   One of the type constants, e.g. DB_TYPE_SORT.
	 */
	public function __construct($type) 
	{
		$this->type = $type;
		switch ($type)
		{
		case DB_TYPE_DIST:
			$this->str = 'dist';
			break;
		
		case DB_TYPE_COLLOC:
			$this->str = 'colloc';
			/* PHP 5.6+ allows use of "..." with variable arguments, but 
			 * that would actually be less clear than the current code. */
			if (3 != func_num_args())
				exiterror("Invalid DB type setup for collocation: correct arguments not supplied.");
			$this->colloc_atts  = func_get_arg(1);
			$this->colloc_range = func_get_arg(2);
			break;
		
		case DB_TYPE_SORT:
			$this->str = 'sort';
			break;

		case DB_TYPE_CATQUERY:
			$this->str = 'catquery';
			break;
			
		case DB_TYPE_PARLINK:
			$this->str = 'parlink';
			break;
			
		default:
			exiterror("Code was reached that should never be reached! (In DbType)");
		}
		
		/* If, at any point, the other database types come to need extra info, 
		   add code in the switch above to set up the appropriate variables. */
	}
	
	/**
	 * Asserts that a given variable is, or type-juggles to, a valid DB_TYPE integer.
	 * Exits the program if not.
	 * 
	 * @param int    $var             Variable to check.
	 * @param string $assert_message  Optional: error message to use if the assertion fails.
	 */
	public static function assert($var, $assert_message = '')
	{
		if (empty($assert_message))
			$assert_message = 'DbType: assertion of valid type failed.';
		if (! self::is_valid($var))
			exiterror($assert_message);
	}
	
	/**
	 * Checks whether a given value is, or type-juggles to, a valid DB_TYPE integer.
	 * 
	 * @param  int $var  Variable to check.
	 * @return bool      True if it's valid, false if not.
	 */	
	public static function is_valid($var)
	{
		return in_array((int) $var, self::$valid_types);
	}
}


/** makes sure that the name you are about to give to a db is unique */
function dbname_unique($dbname)
{
	while (1)
	{
		$sql = 'select dbname from saved_dbs where dbname = \''. escape_sql($dbname) . '\' limit 1';
	
		$result = do_sql_query($sql);

		if (0 == mysqli_num_rows($result))
			break;
		else
			$dbname .= chr(random_int(0x41,0x5a));
	}
	return $dbname;	
}





/** 
 * Creates a db for the named query of the specified type & returns its name.
 * 
 * Note that although LOTS of the parameters come from a query record, we don't pass
 * in a QueryRecord object: see cache-lib.php for why (it's to support certain patterns
 * of postprocessed-query creation).
 * 
 * @param  DbType $db_type      Type of database to create, as a specifier object.
 * @param  string $qname        Name of the cached query from which to create the DB.
 * @param  string $cqp_query    The cached query's actual CQP info (as in the query record)
 * @param  string $query_scope  The cached query's original query scope, serialised (as in the query record).
 * @param  string $postprocess  The cached query's postprocess string (as in the query record).
 * @return string               The database "name" (ie the dbname field from the saved_dbs table).
 */
function create_db(DbType $db_type, $qname, $cqp_query, $query_scope, $postprocess)
{
	global $Config;
	global $User;
	global $Corpus;
	
	
	$cqp = get_global_cqp();

	$cqp->execute("set Context 0");


	/* create a name for the database */
	$dbname = dbname_unique('db_' . $db_type->str . '_' . $Config->instance_name);
	
	/* register this script as working to create a DB, after checking there is room for it */
	if ( !check_db_max_processes($db_type->str) )
		exiterror_toomanydbprocesses($db_type->str);
	
	register_db_process($dbname, $db_type->str);


	/* double-check that no table of this name exists */
	do_sql_query("DROP TABLE IF EXISTS $dbname");
	

	/* call a function to delete dbs if they are taking up too much space*/
	delete_db_overflow();


	/* get this user's distribution db size limit from their username details */
	$table_max = get_user_setting($User->username, 'max_dbsize');
// TODO replace with a call to the $User object that interrogates privileges.


	$num_of_rows = $cqp->querysize($qname);


	if ($db_type->type == DB_TYPE_COLLOC || DB_TYPE_PARLINK)
		$num_of_rows *= $Config->colloc_db_premium;


	if ($num_of_rows > $table_max)
	{
		unregister_db_process();
		exiterror("The action you have requested uses up a lot of diskspace.\n"
			. "Your limit is currently set to $table_max instances.\n"
			. "Please contact your system administrator if you need access to the information you requested."
			);
	}


	/* name for a file containing table with result of tabulation command*/
	$tabfile = "{$Config->dir->cache}/tab_{$db_type->str}_{$qname}";
	/* name for a file containing the awk script */
	$awkfile = "{$Config->dir->cache}/awk_{$db_type->str}_{$qname}";

	if (is_file($tabfile))
		unlink($tabfile);
	if (is_file($awkfile))
		unlink($awkfile);

	/* get the tabulate, awk, and create table commands for this type of database */
	$commands = db_commands($dbname, $db_type, $qname);
	
	if ($commands['awk'])
	{
		/* if an awk script to intervene between cqp and mysql has been returned,
		 * create an awk script file ... */
		file_put_contents($awkfile, $commands['awk']);
		$tabulate_dest = "\"| {$Config->path_to_gnu}awk -f '$awkfile' > '$tabfile'\"";
	}
	else
		$tabulate_dest = "'$tabfile'";



	/* create the empty table */
	do_sql_query($commands['create']);


	/* create the tabulation */
	$cqp->execute("{$commands['tabulate']} > $tabulate_dest");

	/* We need to check if the CorpusCharset is other than ASCII/UTF8. 
	 * If it is, we need to call the library function that runs over it with iconv. */
	if ('utf8' != ($corpus_charset = $cqp->get_corpus_charset()))
	{
		$utf8_filename = $tabfile .'.utf8.tmp';

		change_file_encoding($tabfile, 
		                     $utf8_filename, 
		                     CQP::translate_corpus_charset_to_iconv($corpus_charset), 
		                     CQP::translate_corpus_charset_to_iconv('utf8') . '//TRANSLIT'
		                     );
		unlink($tabfile);
		rename($utf8_filename, $tabfile);
		/* so now, either way, we need to work further on $tabfile. */
	}

	do_sql_query("alter table $dbname disable keys");

	do_sql_infile_query($dbname, $tabfile, true);

	do_sql_query("alter table $dbname enable keys");


	/* and delete the file from which the table was created, plus the awk-script if there was one */
	if (is_file($tabfile))
		unlink($tabfile);
	if (is_file($awkfile))
		unlink($awkfile);


	/* now create a record of the db */

	$sql = "INSERT INTO saved_dbs (
			dbname, 
			user,
			create_time, 
			cqp_query,
			query_scope,
			postprocess,
			colloc_atts,
			colloc_range,
			" . /*sort_position,*/ "
			corpus,
			db_type,
			db_size
		) VALUES (
			'$dbname', 
			'{$User->username}',
			" . time() . ",
			'" . escape_sql($cqp_query)   . "',
			'" . escape_sql($query_scope) . "',
			'" . escape_sql($postprocess) . "',
			'" . ($db_type->type == DB_TYPE_COLLOC ? escape_sql($db_type->colloc_atts) : '') . "',
			"  . ($db_type->type == DB_TYPE_COLLOC ? $db_type->colloc_range : '0') . ",
			"  . /*($db_type->type == DB_TYPE_SORT ? $sort_position : '0') . ", */ "
			'{$Corpus->name}',
			'{$db_type->str}',
			" . get_db_size($dbname) . "
		)";
		/* note: sort position doesn't currently get used, so I have commented it out: sort databases are in corpus order */

	do_sql_query($sql);

	unregister_db_process();

	return $dbname;
}







/**
 * Get three commands : one for CQP (a tabulate command); one for an optional awk pipe to filter the tabulate output on 
 * its way to MySQL; and one for creation of the MySQL table that will receive the data.
 * 
 * @param  string  $dbname   Database unique name of DB (will be used as name of the MySQL table).
 * @param  DbType  $db_type  DB type as defined by an object w/ additional variables if necessary.
 * @param  string  $qname    Query name from which the DB is to be created.
 * @return array            Associative array with 3 entries. Keys are 'tabulate', 'awk', and 'create'; values are the 
 *                          commands needed for DB creation, or false if absent (e.g. there is not always an awk filter).
 */
function db_commands($dbname, DbType $db_type, $qname)
{
	global $Corpus;
	
	switch($db_type->type)
	{
	case DB_TYPE_DIST:
		/* do we need to add columns for XML IDLINK? */
		$idlink_fields = array();
		foreach(get_all_xml_info($Corpus->name) as $xml)
			if (METADATA_TYPE_IDLINK == $xml->datatype)
				$idlink_fields[] = $xml->handle;
		
		$tabulate_command = "tabulate $qname match text_id";
		foreach($idlink_fields as $if)
			$tabulate_command .= ", match $if";
 		$tabulate_command .= ', match, matchend';
		
		$awk_script = false;
		
		$extra_sql_fields = '';
		$extra_sql_keys   = '';
		foreach($idlink_fields as $if)
		{
			$extra_sql_fields .= ", `$if` varchar(255) NOT NULL"; /*nb, should prob be 200, but this is to match text id. */
			$extra_sql_keys   .= ", key(`$if`)";
		}
		
		/* WHAT REFNUMBER MEANS HERE:
		 * Refers to a specific hit in the dist table, numbered in order of intake from CQP. */
//TODO text_id need charset/collate
		$create_statement = "CREATE TABLE $dbname (
			text_id varchar(255) NOT NULL $extra_sql_fields,
			beginPosition int unsigned NOT NULL,
			endPosition int unsigned NOT NULL,
			refnumber mediumint unsigned NOT NULL AUTO_INCREMENT,
			
			primary key(refnumber),
			key(text_id) $extra_sql_keys
			
			) CHARACTER SET utf8 COLLATE utf8_bin";
			/* 
			 * note the use of a binary collation for distribution DBs, since
			 * they always contain handle IDs, not word or tag material.
			 */
		break;
	
	
	case DB_TYPE_COLLOC:
		$att_array = ( empty($db_type->colloc_atts) ? array() : explode('~', $db_type->colloc_atts) );
		/* empty array so that foreach loops have valid reference, but don't loop. */
// 		$num_of_atts = count($att_array) + 1;	/* count returns 0 if $att_array is NULL */
		
		/* create tabulate command */
		$tabulate_command = "tabulate $qname match, matchend, match text_id";

		for ($c = -$db_type->colloc_range ; $c <= -1 ; $c++)
		{
			$tabulate_command .= ", match[$c] word";
			foreach ($att_array as $att)
				$tabulate_command .= ", match[$c] $att";
		}
		for ($c = 1 ; $c <= $db_type->colloc_range ; $c++)
		{
			$tabulate_command .= ", matchend[$c] word";
			foreach ($att_array as $att)
				$tabulate_command .= ", matchend[$c] $att";
		}
	
		/* create awk field variables */
		$awkvar_match    = '$1';
		$awkvar_matchend = '$2';
		$awkvar_text_id  = '$3';
		$corpus_size = $Corpus->size_tokens;
		/* the next field after the pre-sets above: $f increments after every use */
		$f = 4;

		/* and now use them to write the awk script that converts one row to many */
		$awk_script = 'BEGIN{ OFS = FS = "\t" }' . "\n";
		
		for ($i = -$db_type->colloc_range ; $i <= $db_type->colloc_range ; $i++)
		{
			/* skip 0'th position, because there is no data in the tabulate output for $i == 0  */
			if ($i == 0)
				continue;
			/* before the output code: add the condition that prevents out-of-bounds tokens being included in the DB..... */
			$awk_script .= "($awkvar_match + $i >= 0 && $awkvar_matchend + $i < $corpus_size) ";
			/* now, the actual command to print the mysql input fields:
			 *                      text_id          beginPosition  endPosition       refnumber dist word */
			$awk_script .= "{ print $awkvar_text_id, $awkvar_match, $awkvar_matchend, NR-1, $i, \${$f}";
			$f++;	/* increment $f after each field is set */
			foreach($att_array as $att)
			{
				$awk_script .= ", \${$f}";
				$f++;
			}
			$awk_script .= " }\n";
		}
		
		/* WHAT REFNUMBER MEANS HERE:
		 * Refers to a specific hit - but this measn it is NOT unique within this table. Ergo not primary. */
//TODO text_id, word need c harset/collate
		$create_statement = "CREATE TABLE $dbname (
			text_id varchar(255) NOT NULL,
			beginPosition int unsigned NOT NULL,
			endPosition int unsigned NOT NULL,
			refnumber bigint unsigned NOT NULL,
			dist smallint NOT NULL,
			word varchar(40) NOT NULL
			";
		/* add a field for each positional attribute */
		if ($att_array !== NULL)
			foreach ($att_array as $att)
				$create_statement .= ",
					`$att` varchar(40) NOT NULL";
		$create_statement .= ",
			key (refnumber)
			) CHARACTER SET utf8 COLLATE {$Corpus->sql_collation}";
		
		break;


	case DB_TYPE_SORT:
		$att = $Corpus->primary_annotation;
		$no_att = empty($att);

		$tabulate_command = "tabulate $qname "
		    . "match[-5] word, match[-4] word, match[-3] word, match[-2] word, match[-1] word, "
		    . "matchend[1] word, matchend[2] word, matchend[3] word, matchend[4] word, matchend[5] word, "
		    . ($no_att ? '' : "match[-5] $att, match[-4] $att, match[-3] $att, match[-2] $att, match[-1] $att, ")
		    . ($no_att ? '' : "matchend[1] $att, matchend[2] $att, matchend[3] $att, matchend[4] $att, matchend[5] $att, ")
		    . "match .. matchend word, "
			. ($no_att ? '' : "match .. matchend $att, ")
		    . "match text_id, match, matchend"
		    ;
		
		$awk_script = false;
		
		$att_fields = $no_att ? '' : "
			tagbefore5 varchar(40) NOT NULL,
			tagbefore4 varchar(40) NOT NULL,
			tagbefore3 varchar(40) NOT NULL,
			tagbefore2 varchar(40) NOT NULL,
			tagbefore1 varchar(40) NOT NULL,
			tagafter1 varchar(40) NOT NULL,
			tagafter2 varchar(40) NOT NULL,
			tagafter3 varchar(40) NOT NULL,
			tagafter4 varchar(40) NOT NULL,
			tagafter5 varchar(40) NOT NULL,";
		$att_nodefield = $no_att ? '' : "tagnode varchar(200) NOT NULL,";
		
//TODO all word/tag befotre/after need a charset / a colate. 
		/* WHAT REFNUMBER MEANS HERE:
		 * Refers to a specific hit in the underlying concordance. */
		$create_statement = "CREATE TABLE $dbname (
			before5 varchar(40) NOT NULL,
			before4 varchar(40) NOT NULL,
			before3 varchar(40) NOT NULL,
			before2 varchar(40) NOT NULL,
			before1 varchar(40) NOT NULL,
			after1 varchar(40) NOT NULL,
			after2 varchar(40) NOT NULL,
			after3 varchar(40) NOT NULL,
			after4 varchar(40) NOT NULL,
			after5 varchar(40) NOT NULL,
			$att_fields
			node varchar(200) NOT NULL,
			$att_nodefield
			text_id varchar(255) NOT NULL,
			beginPosition int unsigned NOT NULL,
			endPosition int unsigned NOT NULL,
			refnumber mediumint unsigned NOT NULL AUTO_INCREMENT,
			primary key(refnumber)
			) CHARACTER SET utf8 COLLATE {$Corpus->sql_collation}";

		break;


	case DB_TYPE_CATQUERY:
		
		$tabulate_command = "tabulate $qname match, matchend";
		$awk_script = false;
		/* WHAT REFNUMBER MEANS HERE:
		 * Refers to a specific hit in the concordance being categorised. */#
// TODO category needs charset/collate
		$create_statement = "CREATE TABLE $dbname (
			beginPosition int unsigned NOT NULL,
			endPosition int unsigned NOT NULL,
			refnumber MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
			category varchar(40),
			primary key(refnumber),
			key(category)
			) CHARACTER SET utf8 COLLATE {$Corpus->sql_collation}";
		
		break;
		
	case DB_TYPE_PARLINK:
		
		
		break;


	default:
		exiterror("db_commands was called with database type [{$db_type->type}]. This is not a recognised type of database!");
		break;
	}

	/* and, we're done! */
	return array(
		'tabulate'	=> $tabulate_command, 
		'awk' 		=> $awk_script, 
		'create' 	=> $create_statement
		);
}







/** does nothing to the specified db, but refreshes its create_time to = now */
function touch_db($dbname)
{
	$dbname = escape_sql($dbname);

	$time_now = time();

	do_sql_query("update saved_dbs set create_time = $time_now where dbname = '$dbname'");
}




/** Returns total size in bytes of the MySQL data/index structures of the specified database. */
function get_db_size($dbname)
{
	$dbname = escape_sql($dbname);

	/* this forces an update of the sizes of table available by show-table-status */
	do_sql_query("analyze table `$dbname`");

	$info = mysqli_fetch_assoc(do_sql_query("SHOW TABLE STATUS LIKE '$dbname'"));

//$rr = $info['Data_length'] + $info['Index_length'];
//squawk("Calcualting size for $dbname: from data length {$info['Data_length']} and index length {$info['Index_length']}, with result $rr!");
	return $info['Data_length'] + $info['Index_length'];
}

/** */
function repair_all_db_sizes()
{
	global $User;

	if (!$User->is_admin())
		return;

	$result = do_sql_query("select dbname, db_size from saved_dbs");

	$n_changes = 0;
	$unchanged = 0;

	while ($o = mysqli_fetch_object($result))
	{
		$inner = do_sql_query("SHOW TABLE STATUS LIKE '{$o->dbname}'");
		if (!($r = mysqli_fetch_assoc($inner)))
			continue;

		$realsize = $r['Data_length'] + $r['Index_length']; 

		if ($realsize != $o->db_size)
		{
			++$n_changes;
			squawk("Table {$o->dbname}: updating from {$o->db_size} to $realsize...");
			do_sql_query("update saved_dbs set db_size=$realsize where dbname='{$o->dbname}'");
		}
		else
			++$unchanged;
	}
	
	squawk("Total of $n_changes DB sizes repaired. $unchanged were fine and have been left as-is.");
}




// nonurgent TODO use objects for dbrecords instead of hashes. 

/**
 * Returns AN ASSOCIATIVE ARRAY for the located db's record,
 * or false if it could not be found.
 */
function check_dblist_dbname($dbname)
{
	$sql = "SELECT * from saved_dbs where dbname = '" . escape_sql($dbname) . "' limit 1";
	
	$result = do_sql_query($sql);
	
	if (0 == mysqli_num_rows($result))
		return false;
	
	return mysqli_fetch_assoc($result);
}

/**
 * Returns an object for the located DB's record in SQL,
 * or false if it could not be found.
 */
function get_db_info($dbname)
{
	return  get_sql_object("SELECT * from saved_dbs where dbname = '" . escape_sql($dbname) . "' limit 1");
}



/**
 * Looks for a database identical to a set of parameters.
 * 
 * A database's "identity" is determined by the parameters involved in DB creation. 
 * That is, a DB is "identical" to a provided set of parameters iff:
 *  - they have the same DbType (including not only the type constant, but any ancillary variables,
 *    such as (for collocation) the attributes used and the range.
 *  - they are for the same fundamental query (CQP syntax)..
 *  - ... run in the same query scope.
 *  - ... in the same corpus (enforced automatically).
 *  - ... with the same sequence of postprocesses.
 * 
 * Returns AN ASSOCIATIVE ARRAY for the located db's record,
 * or false if no record could be found.
 * 
 * @param  DbType $db_type      DB type specifier object including extra varaibles where necessary 
 *                             (colloc atts, sort positon). As in create_db.
 * @param  string $cqp_query    As in create_db.
 * @param  string $query_scope  As in create_db.
 * @param  string $postprocess  As in create_db.
 * @return array                DB record array or Boolean false.
 */
function check_dblist_parameters($db_type, $cqp_query, $query_scope, $postprocess)
{
	global $Corpus;

	/* set up the options that are particular to certain types of db (currently only colloc) */
	
	$extra_conditions = '';
	
	switch ($db_type->type)
	{
	case DB_TYPE_DIST:
		break;
	
	case DB_TYPE_COLLOC:
		if ($db_type->colloc_range == 0)
			exiterror("The collocation range cannot be zero!");
		$extra_conditions = " and colloc_atts = '" . escape_sql($db_type->colloc_atts). "' and colloc_range = {$db_type->colloc_range}";
		break;
		
	case DB_TYPE_SORT:
		break;
		
	default:
		exiterror("check_dblist_parameters was called with database type # {$db_type->type} . This is not a recognised type of database!");
	}
	
	/* now look for a db that matches all of that! */
	$sql = "SELECT * from saved_dbs 
				where   db_type     = '{$db_type->str}' 
				    and corpus      = '{$Corpus->name}'
					and cqp_query   = '" . escape_sql($cqp_query)   . "'
					and query_scope = '" . escape_sql($query_scope) . "'
					and postprocess = '" . escape_sql($postprocess) . "'
					$extra_conditions
					limit 1";
	/* note: we ONLY match against colloc_atts / colloc_range when type is COLLOC; 
	 * and we NEVER match against sort_position, since it seems never to be used. */

	$result = do_sql_query($sql);

	if (0 == mysqli_num_rows($result))
		return false;
	
	return mysqli_fetch_assoc($result);
}



/**
 * Deletes a database from the system.
 */
function delete_db($dbname)
{
	$dbname = escape_sql($dbname);
	do_sql_query("DROP TABLE IF EXISTS `$dbname`");
	do_sql_query("delete from saved_dbs where dbname = '$dbname'");
}


/**
 * Deletes all databases that are associated with a given query-name.
 * 
 * This would typically be done if the query itself is being deleted
 * and we DON'T want to save associated DBs for a future similar query
 * e.g. if we're overwriting a separated-out catquery.
 */
function delete_dbs_of_query($qname)
{
	$qname = escape_sql($qname);
	
	$result = do_sql_query("select dbname from saved_dbs where dbname like 'db_%_$qname'");
	
	while ($r = mysqli_fetch_row($result))
		delete_db($r[0]);
}


/** 
 * note: this function works ACROSS CORPORA && across types of db (except catquery) 
 */
function delete_db_overflow()
{
	global $Config;
	
	/* step one: how many bytes in size is the db cache RIGHT NOW? */
	list($current_size) = mysqli_fetch_row(do_sql_query("select sum(db_size) from saved_dbs"));

	if ($current_size <= $Config->db_cache_size_limit)
		return;
	
	/* step 2 : get a list of deletable tables 
	 * note that catquery dbnames are excluded 
	 * they must be deleted via their special table 
	 * because otherwise entries are left in that table */
	$sql = "select dbname, db_size from saved_dbs 
		where saved = " . CACHE_STATUS_UNSAVED . " 
		and dbname not like 'db_catquery%'
		order by create_time asc";
	$result = do_sql_query($sql);

/* TODO: unused field & data duplication in saved_dbs
	An important note: the "saved" field in saved_dbs seems not to be used.
	It looks liek the intetnion was to make it flag "deletable / not deletable"
	but this was not actually done.
	Instead there is the "type" field, which is a string instead of  - as would be more comfortable - and integer cobnstant
	and the same data IS IMPLICITLY STORED IN THE TABLE PREFIX!!
	this is ridiculous duplication.
	
	Better design: make the tablename / dbname be just db_INSTANCE
	and then rely on a "type" integer constant in the database. 
	this would also speed the abiove query up by not using the LIKE operator.
*/
	

	while ($current_size > $Config->db_cache_size_limit)
	{
		if ( ! ($current_db_to_delete = mysqli_fetch_assoc($result)) )
			break;
		delete_db($current_db_to_delete['dbname']);
		$current_size -= $current_db_to_delete['db_size'];
	}
	
	/* if we weren't able to get current_size down far enough.... */
	if ($current_size > $Config->db_cache_size_limit)
		exiterror(array(
				"CRITICAL ERROR - DATABASE CACHE OVERLOAD!",
				"CQPweb tried to clear database cache space but failed!",
				"Please report this error to the system administrator."
		));
}
//TODO check against delete_cache_overflow ... is this up to  the minute....


/**
 * dump all cached dbs from the database INCLUDING ones where the "saved" field is flagged.
 * 
 * Operates across users and across corpora.
 */
function clear_dbs($type = '__NOTYPE')
{
	$sql = "select dbname from saved_dbs";
	if ($type != '__NOTYPE')
		$sql .= " where db_type = '" . escape_sql($type) . "'";

	$result = do_sql_query($sql);

	while ($current_db_to_delete = mysqli_fetch_assoc($result))
		delete_db($current_db_to_delete['dbname']);
}




/**
 * Deletes a specified database from MySQL, unconditionally.
 * 
 * Note it only works on database tables!
 * 
 * If passed an array rather than a single table name, it will iterate across 
 * the array, deleting each specified table.
 * 
 * Designed for deleting bits database tables that have become unmoored from
 * the record in saved_dbs that would normally enable their deletion.
 */
function delete_stray_db_table($table)
{
	if (! is_array($table))
		$table = array($table);
	
	foreach ($table as $t)
		if (preg_match('/^db_/', $t))
			do_sql_query("drop table if exists `" . escape_sql($t) . "`");
}

/**
 * Deletes a db record from the cache table, unconditionally.
 * 
 * If passed an array rather than a single db name, it will iterate across 
 * the array, deleting each specified db record.
 */
function delete_stray_db_entry($dbname)
{
	if (!is_array($dbname))
		$dbname = array($dbname);
	
	foreach ($dbname as $db)
		do_sql_query("DELETE from saved_dbs where dbname = '" . escape_sql($db) . "'");
}








/* process functions */



/** 
 * Checks for maximum number of concurrent processes of the sepcified type;
 * returns true if there is space for another process, false if there is not.
 * 
 * @param  string $process_type  Short-string representation of the kind of process we are looking for.
 * @return bool
 */
function check_db_max_processes($process_type)
{
	global $Config;
	
	$sql = "select process_id from system_processes where process_type = '" . escape_sql($process_type) . "'";
	$result = do_sql_query($sql);
	
	$current_processes = mysqli_num_rows($result);

	if ($current_processes >= $Config->mysql_process_limit[$process_type])
	{
		/* check whether there are dead entries in the system_processes table */
		$dead_processes = 0 ;
		
		$os_pids = shell_exec( 'ps -e' ); // TODO Windows incompatability
		
		/* check each row of the result */
		while ($pidrow = mysqli_fetch_row($result))
		{
			if (preg_match("/\s{$pidrow[0]}\s/", $os_pids) == 0)
			{
				/* the pid was NOT found on the list from the OS */
				$dead_processes++;
				unregister_db_process($pidrow[0]);
			}
		}
		if ($dead_processes > 0)
			return true;            /* the list was full, but 1+ process was found to be dead */
		else
			return false;           /* the list was full, and no dead processes */
	}
	else
		return true;                /* the list was not full */
}



/** 
 * Adds the current instance of PHP's process-id to a list of concurrent db processes.
 */
function register_db_process($dbname, $process_type, $process_id = NULL)
{
	$dbname = escape_sql($dbname);
	$process_type = escape_sql($process_type);
	if (empty($process_id))
		$process_id = getmypid();
	else
		$process_id = escape_sql($process_id);
	$begin_time = time();
	$sql = "insert into system_processes (dbname, begin_time, process_type, process_id)
		values ('$dbname', $begin_time, '$process_type', '$process_id' )";
	do_sql_query($sql);
	//TODO maybe also record the MySQL connection-id?
}



/** Declares a process run by the current script complete; removes it from the list of db processes */
function unregister_db_process($process_id = false)
{
	if ($process_id === false)
		$process_id = getmypid();
	else
		$process_id = (int)$process_id;
	do_sql_query("delete from system_processes where process_id = '$process_id'");
}



