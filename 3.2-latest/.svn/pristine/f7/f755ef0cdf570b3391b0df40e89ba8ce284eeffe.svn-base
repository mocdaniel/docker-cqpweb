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
 * this is a utility script, which compares the different ways of measuring the
 * disk spce used by the different tables in the CQPweb RDB.
 */

require('../lib/environment.php');
include('../lib/general-lib.php');
include('../lib/html-lib.php');
include('../lib/sql-lib.php');
include('../lib/useracct-lib.php');
include('../lib/exiterror-lib.php');





cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_CLI);



$t_result = do_sql_query("show tables");


echo "TABLE\tNORM_SIZE\tREAL_SIZE\FSYS_SIZE\tFSYS_PATH\n";

while ($r = $t_result->fetch_row())
{
	$table = $r[0];
	
	// get size the usual way
	if (false === ($norm_size = get_sql_table_size($table)))
		$norm_size = 'n/a';
	
	// get "real" size
	if (false === ($real_size = get_sql_table_real_size($table)))
		$real_size = 'n/a';
	
	// get fs size 
	if (false === ($fsys_size = get_sql_table_filesystem_size($table)))
		$fsys_size = 'n/a';
	$fsys_path = get_sql_table_filesystem_path($table);
	
	echo "$table\t$norm_size\t$real_size\t$fsys_size\t$fsys_path\n";
}


$t_result->free();

if (!can_get_sql_real_size())
	echo "### WARNING: Can't get the SQL 'real size' - either your sql lacks the FILE_SIZE/ALLOCATED size columns for INNODB\n"
		, "###          or the CQPweb SQL-user does not have the PROCESS privilege to read it.\n" 
		;
if (!is_sql_file_per_table())
	echo "### WARNING: The database is not in InnoDB file-per0tale mode, so filesystem sizes could not be calculated. \n";
else if ('n/a' === $fsys_size)
	echo "### WARNING: Can't get the filesystem size - either this user acct doesn't have read access to the files,\n"
		, "###          or the CQPweb SQL-user does not have the PROCESS privilege to read the INNODB DATAFILES table.\n"
		;	






cqpweb_shutdown_environment();
