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
 * Changes storage format of all the tables that we can (from MyISAM) to InnoDB. 
 *
 * CQPweb was first created back in the days when MyISAM was the default. Now, InnoDB is default,
 * and we need to enforce its use in order to make sure that the code can do optimisation-thingies
 * under the assumption that we are using InnoDB. (This applies, for isntance, to splitting the DB 
 * across multiple disk drives for performance reasons..... )
 * 
 * WHEN TO RUN THIS SCRIPT:
 * 
 * - If you have an old CQPweb installation, dating from the days when MyISAMK was the recommended format,
 *   and you want to switch to the now-recommended InnoDB.
 * 
 * - If you have just switched innodb_file_per_table on and wish to force existing tables into their own files.
 *   (Although note this will leave the innodb central tablespace still using all its disk space! 
 *   recovering that is something CQPweb cannot do for you.)
 */


require('../lib/environment.php');
require('../lib/metadata-lib.php');
require('../lib/general-lib.php');
require('../lib/sql-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');
require('../bin/cli-lib.php');

$Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_CLI);



/* tables that should not be upgraded, because they use FULLTEXT INDEX, which earlier versions of InnoDB do not support */
$dont_innodbise = array(
		'saved_queries'
);


/* if the MySQL server version is high enough, we don't need to hold tables with fulltext indexes back as MyISAM. */
$mysqli_link = get_global_sql_link();
list($major, $minor, $rest) = explode('.', mysqli_get_server_info($mysqli_link), 3);

// TODO we could probably use the get_server_version here to use integer comparison. 
if (preg_match('/-(\d+)\.(\d+)\.(\d+)\.-mariadb-/i', $rest, $m))
{
	/* mariadb: need 10.0.5 */
	list(, $major, $minor, $incr) = $m;
	if ( $major > 10 || ($major == 10 && $minor >= 1) || ($major == 10 && $incr >= 5) )
		$dont_innodbise = array();
}
else
{
	/* mysql: need 5.6 */
	if ($major > 5 || ($major == 5 && $minor >= 6))
		$dont_innodbise = array();
}


/* recommended way to run is with CQPweb "switched off" */
if ( ! ($Config->cqpweb_no_internet || !$Config->cqpweb_switched_off || in_array('--while-switched-on', $argv)) )
{
	echo "Program aborts: CQPweb is still switched on to external users!";
	echo "It is recommended that you set the \$cqpweb_switched_off config option to *true* before running this script.\n";
	echo "If you're sure you want to run it with CQPweb switched on, re-run this script as follows: \n";
	echo "\n\tphp force-innodb.php --while-switched-on\n\nThanks!\n";
	exit(0);
}


/* report how many tables are already InnoDB: */
$result = do_sql_query("select table_name from information_schema.tables where table_schema = '{$Config->mysql_schema}'  and engine  = 'InnoDB'");
echo number_format(mysqli_num_rows($result)), " in your database already use the InnoDB engine.\n";
$result = do_sql_query("select table_name from information_schema.tables where table_schema = '{$Config->mysql_schema}'  and engine <> 'InnoDB'");
echo number_format(mysqli_num_rows($result)), " in your database use the older MyISAM engine.\n";


$upgraded_to_idb = 0;
$held_back = 0;


/* the pointy end! */
$result = do_sql_query("select table_name from information_schema.tables where table_schema = '{$Config->mysql_schema}' and engine <> 'InnoDB'");
while ($o = mysqli_fetch_row($result))
{
	if (! in_array($o[0], $dont_innodbise))
	{
		do_sql_query("alter table `{$o[0]}` ENGINE=InnoDB");
		$upgraded_to_idb++;
		if (0 == $upgraded_to_idb % 100)
			echo number_format($upgraded_to_idb), " tables have had the InnoDB engine enforced; continuing...\n";
	}
	else
		$held_back++;
}

echo "Force-InnoDB process complete! ", number_format($upgraded_to_idb), " tables were adjusted and $held_back were held back.\n";

cqpweb_shutdown_environment();


