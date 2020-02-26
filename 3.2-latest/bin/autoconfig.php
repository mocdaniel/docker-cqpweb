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

require('../bin/cli-lib.php');


/* BEGIN HERE */

/* refuse to run unless we are in CLI mode */
if (php_sapi_name() != 'cli')
	exit("Critical error: CQPweb's auto-config script must be run in CLI mode!\n");

echo "\n\n/***********************/\n\n\n";

echo "This is the interactive configuration-file creator for CQPweb.\n\n\n";






/* get the options, one by one */


$superuser_username = '';
while (1)
{
	$superuser_username .=  '|' . get_variable_word('the username you want to use for the sysadmin account');
	
	while (1)
	{
		echo "Add another admin username?) [y/n]\n\n";
		$i = strtoupper(fgets(STDIN));
		if ($i[0] =='N')
			break 2;
		else if ($i[0] == 'Y')
			break 1;
	}
}



$superuser_username = trim($superuser_username, '|');


$cwb_datadir = get_variable_path("the path to the directory you wish to use for the CWB datafiles");
$cwb_registry = get_variable_path("the path to the directory you wish to use for CWB registry files");
$cqpweb_tempdir = get_variable_path("the path to the directory you wish to use for the CQPweb cache and other temp files");
$cqpweb_uploaddir = get_variable_path("the path to the directory you wish to store uploaded files in");


$mysql_webuser = get_variable_word("the MySQL username that you want CQPweb to use (do NOT use root)");
$mysql_webpass = get_variable_string("the password for this MySQL user");
$mysql_schema = get_variable_word("the name of the MySQL database to use for CQPweb tables");
$mysql_server = get_variable_string("the hostname of the MySQL server (typically ``localhost'', unless your MySQL server is a separate machine)");



/* END compulsory variables, ONTO optional variables */

// Ability to add optional variables has been removed for now. It may well be unnecessary.

//echo "You have now entered all compulsory configuration variables. Do you want to add any optional configuration?\n";
//echo "(Note, you can always add these later by manually editing config.inc.php - this is explained\n";
//echo "in the chapter of the sysadmin manual on the Configuration File.\n";
//
//if (ask_boolean_question("Add optional configuration variables?"))
//{
//	
//}




/* all variables now collected; write config file. */

$config_file = <<<END_OF_PHP
<?php


/* ----------------------------------- *
 * adminstrators' usernames, separated *
 * by | with no stray whitespace.      *
 * ----------------------------------- */

\$superuser_username = '$superuser_username';


/* -------------------------- *
 * database connection config *
 * -------------------------- */

\$mysql_webuser = '$mysql_webuser';
\$mysql_webpass = '$mysql_webpass';
\$mysql_schema  = '$mysql_schema';
\$mysql_server  = '$mysql_server';



/* ---------------------- *
 * server directory paths *
 * ---------------------- */

\$cqpweb_tempdir   = '$cqpweb_tempdir';
\$cqpweb_uploaddir = '$cqpweb_uploaddir';
\$cwb_datadir      = '$cwb_datadir';
\$cwb_registry     = '$cwb_registry';


END_OF_PHP;



if (!is_dir('../lib'))
	mkdir('../lib');
if (is_file('../lib/config.inc.php'))
{
	for ($i = 1 ; file_exists($newname = "../lib/config.inc.php.old.$i") ; $i++)
		;
	rename('../lib/config.inc.php', $newname);
	echo "'lib/config.inc.php' already existed; your former config file has been moved to $newname.\n\n";

}

echo "Saving new config file ...\n\n";

file_put_contents('../lib/config.inc.php', $config_file);

echo "Done! Now, you should run auto-setup to complete installation.\n\n";

