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
 * This script finalises CQPweb setup once the config file has been created.
 */



require('../lib/environment.php');

/* include function library files */
require('../lib/general-lib.php');
require('../lib/sql-lib.php');
require('../lib/sql-definitions.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/ceql-lib.php');

require ('../bin/cli-lib.php');




/* BEGIN HERE */


/* refuse to run unless we are in CLI mode */
if (php_sapi_name() != 'cli')
	exit("Critical error: Cannot run CLI scripts over the web!\n");

echo "\nNow finalising setup for this installation of CQPweb....\n";

/* create partial environment */
$mysql_utf8_set_required = $mysql_schema = $mysql_webpass = $mysql_webuser = $mysql_server = NULL;
$superuser_username = $default_colloc_calc_stat = $default_colloc_minfreq = $default_colloc_range = $default_max_dbsize = $blowfish_cost = NULL;
include ('../lib/config.inc.php');
include ('../lib/defaults.php');
$Config = new NotAFullConfig();
$Config->Api                     = false;
$Config->print_debug_messages    = false;
$Config->debug_messages_textonly = true;
$Config->all_users_see_backtrace = false;
$Config->client_is_disconnected  = false;
$Config->mysql_utf8_set_required = (isset($mysql_utf8_set_required) && $mysql_utf8_set_required);
$Config->mysql_schema  = $mysql_schema;
$Config->mysql_webpass = $mysql_webpass;
$Config->mysql_webuser = $mysql_webuser;
$Config->mysql_server  = $mysql_server;

/* instead of cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP , RUN_LOCATION_CLI); ....... */
$Config->mysql_link = create_sql_link();

/* extend the partial environment! -- these are the values needed for user account creation; draw in from config/defaults */
$Config->default_colloc_calc_stat = $default_colloc_calc_stat;
$Config->default_colloc_minfreq   = $default_colloc_minfreq;
$Config->default_colloc_range     = $default_colloc_range;
$Config->default_max_dbsize       = $default_max_dbsize;
$Config->blowfish_cost            = $blowfish_cost;

/* create only the parts of $User needed to survive the security check */
class NotAFullUser { public function is_admin () { return true; } }
$User = new NotAFullUser();


echo "\nInstalling database structure; please wait.\n";

do_sql_total_reset();

echo "\nDatabase setup complete.\n";

echo "\nNow, we must set passwords for each user account specified as a superuser.\n";


foreach(explode('|', $superuser_username) as $super)
{
	$pw = get_variable_string("a password for user ``$super''");
	add_new_user($super, $pw, 'not-specified@nowhere.net', USER_STATUS_ACTIVE);
	echo "\nAccount setup complete for ``$super''\n";
}

echo "\n--- done.\n";


/* destroy partial environment */

$Config->mysql_link->close();
unset($Config);
unset($User);

/* with DB installed, we can now startup the environment.... */

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP , RUN_LOCATION_CLI);



echo "\nCreating built-in mapping tables....\n";

regenerate_builtin_mapping_tables();

echo "\n--- done.\n";



/*
 * If more setup actions come along, add them here
 * (e.g. annotation templates, xml templates...
 */

echo "\nAutosetup complete; you can now start using CQPweb.\n";

cqpweb_shutdown_environment();

exit(0);


