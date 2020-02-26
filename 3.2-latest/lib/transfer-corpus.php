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
 * This file contains functions for exportIng and importing a corpus,
 * allowing a corpus set up on one installation of CQPweb to be moved to 
 * another without needing to reindex.
 */


///TODO TODO TODO nothing in this file is finished, or even apporximates finished.



// TODO should these functions call exiterror instead of returning false?
// yes, probably. return value not necessary.




// TODO use cqpweb_dump_targzip / cqpweb_dump_untargzip








/**
 * Export an indexed corpus into a single file that can be moved to another CQPweb installation and reimported.
 * 
 * Corpus transfer files should be given the extension ".cqpwebdata", but this is subject to the user's determination;
 * the output file will be placed in $filepath.
 * 
 * NOTE - the $Corpus->name will be embedded in multiple places. So a corpus can only be imported under
 * the same name it had in the previous system.
 * 
 * (This is a limitation which will be removed in a later version.) 
 * 
 * @see                      import_cqpweb_corpus()
 * 
 * @param  string $corpus    The identifier of the corpus to export.
 * @param  string $filepath  Path of the file to write. Note that if this file exists, it will be overwritten.
 * @return bool              Boolean: true for success, false for failure.
 *
 */
function export_cqpweb_corpus($corpus, $filepath)
{
	global $Config;
	
	/* check that $corpus really is a corpus */
	if (! in_array($corpus, list_corpora()))
		return false;
	
	/* create a directory in the temp space to build the structure of the .cqpwebdata file. */
	mkdir($d = "{$Config->dir->cache}/export.$corpus");
	mkdir("$d/mysql");
	mkdir("$d/reg");
	mkdir("$d/data");
	mkdir("$d/php");
	
	/* MySQL entries: */ 
	/* there are two types of things here: commands to recreate, and tables for import */
	$recreate_commands = array();
	
		/* fixed metadata */
		$fixed = mysqli_fetch_assoc(do_sql_query("select * from corpus_info where corpus='$corpus'"));
		$recreate_commands[] = "insert into corpus_info (corpus) values ('$corpus')";
		foreach($fixed as $k => $v)
		{
			switch ($k)
			{
			case 'corpus_cat':
				/* re-set to uncategorised for new system */
				$v = 1;
				break;
			case 'visible':
			case 'cwb_external':
				/* numeric / boolean entries: do nothing */
				if (is_null($v))
					$v = "NULL";
				else
					;
				break;
			default:
				/* string entries: escape */
				if (is_null($v))
					$v = "NULL";
				else
					$v = "'" . escape_sql($v) . "'";
				break;
			}
			$recreate_commands[] = "update corpus_info set $k = $v where corpus = '$corpus'";
		}

		/* variable metadata */
		$result = do_sql_query("select attribute, value from corpus_metadata_variable where corpus = '$corpus'");
		while ($o = mysqli_fetch_object($result))
		{
			$o->attribute = escape_sql($o->attribute);
			$o->value = escape_sql($o->value);
			$recreate_commands[] = "insert into corpus_metadata_variable (corpus, attribute, value) values ('$corpus','{$o->attribute}','{$o->value}')";
		}
		
		// Annotation metadata
		$result = do_sql_query("select * from annotation_metadata where corpus = '$corpus'");
		while ($o = mysqli_fetch_object($result))
		{
			//TODO make a rerearte ciommand.
			$recreate_commands[] = 'something'; 
		}
		
		
		// Any related XML settings and/or visualisations
		
		// corpus metadata table && its create_table
	
	/* write out our collected recreate-commands */
	file_put_contents("$d/mysql/recreate-commands", implode("\n", $recreate_commands)."\n");
	
	
	/* CWB data: */
	
		/* registry file */
		copy("{$Config->dir->registry}/$corpus", "$d/reg/$corpus");
		
		/* index folder */
		if (! get_corpus_metadata($corpus, "cwb_external"))
			recursive_copy_directory("{$Config->dir->index}/$corpus", "$d/data/index");
		else
		{
// temp code
			// this doesn't work for now, since my test space does not have any.
			exiterror("You called export_cqpweb_corpus() on a corpus with an external index!!!!!");
// end temp code
			// TODO find the path from the registry file (would be useful to have an interface to the registry)
			$reg_content = file_get_contents("{$Config->dir->registry}/$corpus");
			
			$src = "TODO";
			recursive_copy_directory($src, "$d/data/index");
		}

	/* NOT INCLUDED: the ___freq corpus, and any MySQL freq tables for the corpus (they can be rebuilt on import) */

	
	
	// TODO use the functions from admin lib ?
	/* tar and gzip it all */
	exec("{$Config->path_to_gnu}tar -cf $d.tar $d");
	exec("{$Config->path_to_gnu}gzip $d.tar");
	/// TODO use ZipArchive? Or gzopen and firends?
	// or zlib.compress?
//http://php.net/manual/en/zlib.installation.php
	
	/* delete entire working directory that was in temp space... */
	recursive_delete_directory($d);
	
	/* rename the tar.gz to the second argument. */
	return rename("$d.tar.gz", $filepath);
}


/**
 * Import a corpus from a file created by the function export_cqpweb_corpus().
 * 
 * Note: the corpus name is taken from the internal structure of the 

 * @param string $filepath  Path of the file to import. Note that if this file exists, it will be overwritten.
 * @return bool             True for success, false for failure.
 */
function import_cqpweb_corpus($filepath)
{
// 	global $Config;
	
	/* check: does file exist? */
	if(!is_file($filepath))
		return false;
	
	// gunzip from parameter into tempspace
	
	// untarcorpus_sql_
	
	// delete tar
	
	// check corpus name does not already exist; if it does, delete the folder and abort
	
	
	// CWB data:
	
		// TODO
	
	
	// MySQL rebuild:
	
		// run all recreate commands
		
		// create metadata table and load data local infile....
	
	
	// PHP:
	
		// create web folder full of stubs
		
		// move settings file
	
	
	// recursive delete twemp untarred directory
	
	return true;
}

















/*
 * ======================================================================
 * functions for dumping part/all of the CQPweb system (for backup, etc.)
 * ======================================================================
 */







/** support function for the functions that create/read from dump files. */
function dumpable_dir_basename($dump_file_path)
{
	if (substr($dump_file_path,	-7) == '.tar.gz')
		return substr($dump_file_path, 0, -7);
	else
		return rtrim($dump_file_path, '/');
}

/** 
 * Support function for the functions that create/read from dump files. 
 * 
 * Parameter: a directory to turn into a .tar.gz (path, WITHOUT .tar.gz at end). 
 */
function cqpweb_dump_targzip($dirpath)
{
	global $Config;
	
	$dir = end(explode('/', $dirpath));
	
	$back_to = getcwd();
	
	chdir($dirpath);
	chdir('..');
	
	exec("{$Config->path_to_gnu}tar -cf $dir.tar $dir");
	exec("{$Config->path_to_gnu}gzip $dir.tar");
// TOOD, use copmpress.zlib  instead
// http://php.net/manual/en/wrappers.compression.php
	
	recursive_delete_directory($dirpath);

	chdir($back_to);
}

/** support function for the functions that create/read from dump files. 
 *  Parameter: a .tar.gz to turn into a directory, but does not delete the archive. */
function cqpweb_dump_untargzip($path)
{
	global $Config;
	
	$back_to = getcwd();
	
	chdir(dirname($path));
	
	$file = basename($path, '.tar.gz');
	
	exec("{$Config->path_to_gnu}gzip -d $file.tar.gz");
	exec("{$Config->path_to_gnu}tar -xf $file.tar");
	/* put the dump file back as it was */
	exec("{$Config->path_to_gnu}gzip $file.tar");
// TOOD, use copmpress.zlib  instead
// http://php.net/manual/en/wrappers.compression.php
// http://php.net/manual/en/intro.phar.php   // Phar does Tars too. and is builtin after php5.3
	chdir($back_to);
}

/**
 * A variant dump function which only dumps user-saved data.
 * 
 * This currently includes: 
 * (1) cached queries which are saved; 
 * (2) categorised queries and their database.
 * 
 * (possible additions: subcorpora, user CQP macros...)
 */
function cqpweb_dump_userdata($dump_file_path)
{
	global $Config;
	
	php_execute_time_unlimit();
	
	$dir = dumpable_dir_basename($dump_file_path);
	
	if (is_dir($dir))				recursive_delete_directory($dir);
	if (is_file("$dir.tar"))		unlink("$dir.tar");
	if (is_file("$dir.tar.gz"))		unlink("$dir.tar.gz");
	
	mkdir($dir);
	
	/* note that the layout is different to a snapshot - we do not have 
	 * subdirectories or sub-contained tar.gz files */
	
	/* copy saved queries (status: saved or saved-for-cat) */
	$saved_queries_dest = fopen("$dir/__SAVED_QUERIES_LINES", 'w');
	$result = do_sql_query("select * from saved_queries where saved > 0");
	while ($row = mysqli_fetch_row($result))
	{
		/* copy any matching files to the location */
		foreach (glob("{$Config->dir->cache}/*:{$row[0]}") as $f)
			if (is_file($f))
				copy($f, "$dir/".basename($f));

		/* write this row of the saved_queries to file */
		foreach($row as &$v)
			if (is_null($v))
				$v = '\N';

		fwrite($saved_queries_dest, implode("\t", $row) . "\n");
	}
	fclose($saved_queries_dest);
	
	/* write the saved_catqueries table, plus each db named in it, to file */
	
	$tables_to_save = array('saved_catqueries');
	$result = do_sql_query("select dbname from saved_catqueries");
	while ($row = mysqli_fetch_row($result))
		$tables_to_save[] = $row[0];

	$create_tables_dest = fopen("$dir/__CREATE_TABLES_STATEMENTS", "w");
	foreach ($tables_to_save as $table)
	{
		$dest = fopen("$dir/$table", "w");
		$result = do_sql_query("select * from $table");
		while ($r = mysqli_fetch_row($result))
		{
			foreach($r as &$v)
				if (is_null($v))
					$v = '\N';
			fwrite($dest, implode("\t", $r) . "\n");
		}
		$result = do_sql_query("show create table $table");
		list(, $create) = mysqli_fetch_row(do_sql_query("show create table $table"));
		fwrite($create_tables_dest, $create ."\n\n~~~###~~~\n\n");
		
		fclose($dest);
	}
	fclose($create_tables_dest);

	cqpweb_dump_targzip($dir);

	php_execute_time_relimit();
}

/**
 * Undump a userdata snapshot.
 * 
 * TODO not tested yet
 */
function cqpweb_undump_userdata($dump_file_path)
{
	global $Config;
	
	php_execute_time_unlimit();

	$dir = dumpable_dir_basename($dump_file_path);
	
	cqpweb_dump_untargzip("$dir.tar.gz");
	
	/* copy cache files back where they came from */
	foreach (glob("/$dir/*:*") as $f)
		if (is_file($f))
			copy($f, $Config->dir->cache . '/' . basename($f));

	/* load back the mysql tables */
	foreach (explode('~~~###~~~', file_get_contents("$dir/__CREATE_TABLES_STATEMENTS")) as $create_statement)
	{
		if (1 > preg_match('/CREATE TABLE `([^`]*)`/', $create_statement, $m))
			continue;
		if ($m[1] == 'saved_catqueries')
			continue;
			/* see below for what we do with saved_catqueries */

		do_sql_query("drop table if exists {$m[1]}");
		do_sql_query($create_statement);
		do_sql_infile_query($m[1], $m[1]);
	}
	
	/* now, we need to load the data back into saved_queries  --
	 * but we need to check for the existence of like-named save-queries and delete them first. 
	 * Same deal for saved_catqueries. */
	foreach (file("$dir/__SAVED_QUERIES_LINES") as $line)
	{
		list($qname, /* skip! */ , $corpus) = explode("\t", $line);
		do_sql_query("delete from saved_queries where query_name = '$qname' and corpus = '$corpus'");
	}
	do_sql_infile_query('saved_queries', "$dir/__SAVED_QUERIES_LINES");

	foreach (file("$dir/saved_catqueries") as $line)
	{
		list($qname, /* skip! */ , $corpus) = explode("\t", $line);
		do_sql_query("delete from saved_catqueries where catquery_name = '$qname' and corpus = '$corpus'");
	}
	do_sql_infile_query('saved_catqueries', "$dir/saved_catqueries");

	recursive_delete_directory($dir);
	
	php_execute_time_relimit();

}

/**
 * Dump an entire snapshot of the CQPweb system.
 */
function cqpweb_dump_snapshot($dump_file_path)
{
	global $Config;
	
	php_execute_time_unlimit();
	
	$dir = dumpable_dir_basename($dump_file_path);
	
	if (is_dir($dir))				recursive_delete_directory($dir);
	if (is_file("$dir.tar"))		unlink("$dir.tar");
	if (is_file("$dir.tar.gz"))		unlink("$dir.tar.gz");
	
	mkdir($dir);
	
	cqpweb_mysql_dump_data("$dir/__DUMPED_DATABASE.tar.gz");
	
	mkdir("$dir/cache");
	
	/* copy the cache */
	foreach(scandir($Config->dir->cache) as $f)
		if (is_file("{$Config->dir->cache}/$f"))
			copy("{$Config->dir->cache}/$f", "$dir/cache/$f");
	
	/* NOTE: we do not attempt to dump out CWB registry or data files. */
	
	cqpweb_dump_targzip($dir);
	
	php_execute_time_relimit();
}

function cqpweb_undump_snapshot($dump_file_path)
{
	global $Config;
	
	php_execute_time_unlimit();

	$dir = dumpable_dir_basename($dump_file_path);
	
	cqpweb_dump_untargzip("$dir.tar.gz");
	
	/* copy cache files back where they came from */
	foreach(scandir("$dir/cache") as $f)
		if (is_file("$dir/cache/$f"))
			copy("$dir/cache/$f", "{$Config->dir->cache}/$f");
	
	/* corpus settings: create the directory if necessary */
	foreach (scandir("$dir") as $sf)
	{
		if (!is_file($sf))
			continue;
		list($corpus) = explode('.', $sf);
		if (! is_dir("../$corpus"))
			mkdir("../$corpus");
		/* in case these were damaged or not yet created... */
		install_create_corpus_script_files("../$corpus");
	}
	
	/* call the MySQL undump function */
	cqpweb_mysql_undump_data("$dir/__DUMPED_DATABASE.tar.gz");

	recursive_delete_directory($dir);
	
	php_execute_time_relimit();
}


/**
 * Does a data dump of the current status of the mysql database.
 * 
 * The database is written to a collection of text files that are compressed
 * into a .tar.gz file (whose location should be specified as either
 * an absolute path or a path relative to the working directory of the script
 * that calls this function.)
 * 
 * Note that the path, minus the .tar.gz extension, will be created as an
 * intermediate directory during the dump process.
 * 
 * The form of the .tar is as follows: one text file per table in the database,
 * plus one text file containing create table statements as PHP code.
 * 
 * If the $dump_file_path argument does not end in ".tar.gz", then that 
 * extension will be added.
 * 
 * TODO not tested yet
 */
function cqpweb_mysql_dump_data($dump_file_path)
{
	$dir = dumpable_dir_basename($dump_file_path);
	
	if (is_dir($dir))				recursive_delete_directory($dir);
	if (is_file("$dir.tar"))		unlink("$dir.tar");
	if (is_file("$dir.tar.gz"))		unlink("$dir.tar.gz");
	
	mkdir($dir);
	
	$create_tables_dest = fopen("$dir/__CREATE_TABLES_STATEMENTS", "w");
	
	$list_tables_result = do_sql_query("show tables");
	while ($r = mysqli_fetch_row($list_tables_result))
	{
		list(, $create) = mysqli_fetch_row(do_sql_query("show create table {$r[0]}"));
		fwrite($create_tables_dest, $create ."\n\n~~~###~~~\n\n");
		
		$dest = fopen("$dir/{$r[0]}", "w");
		$result = do_sql_query("select * from {$r[0]}");
		while ($line_r = mysqli_fetch_row($result))
		{
			foreach($line_r as &$v)
				if (is_null($v))
					$v = '\N';
			fwrite($dest, implode("\t", $line_r) . "\n");
		}
		fclose($dest);
	}
	
	fclose($create_tables_dest);
	
	cqpweb_dump_targzip($dir);
}

/**
 * Undoes the dumping of the mysql directory.
 * 
 * Note that this overwrites any tables of the same name that are present.
 * 
 * TODO NOT TESTED YET.
 * 
 * If the $dump_file_path argument does not end in ".tar.gz", then that 
 * extension will be added.
 */
function cqpweb_mysql_undump_data($dump_file_path)
{
	$dir = dumpable_dir_basename($dump_file_path);
	
	cqpweb_dump_untargzip("$dir.tar.gz");
	
	foreach (explode('~~~###~~~', file_get_contents("$dir/__CREATE_TABLES_STATEMENTS")) as $create_statement)
	{
		if (1 > preg_match('/CREATE TABLE `([^`]*)`/', $create_statement, $m))
			continue;
		do_sql_query("drop table if exists {$m[1]}");
		do_sql_query($create_statement);
		do_sql_infile_query($m[1], $m[1]);
	}
	
	recursive_delete_directory($dir);
}






