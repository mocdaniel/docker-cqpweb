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


/*
 * Important note: this is non-secure, so is blocked from being run from anywhere but the command line
 * only use this script if you know what you are doing!
 * it was originally invented to allow the setup process access to library functions.
 */


// TODO: get the first superuser username and set it as username by popping it
// into the right place for defaults.php to find it (as if it had come via the web)
// actually shouldn't all CLI scripts do this?
// no: they only need to if they call one of the main pages.

/* refuse to run unless we are in CLI mode */

if (php_sapi_name() != 'cli')
	exit("Offline script must be run in CLI mode!");


if (!isset($argv[1]))
{
	echo "Usage: cd path/to/a/corpus/directory && php ../bin/execute-cli.php function arg1 arg2 ...\n\n";
	exit(1);
}

$_GET = array();

$_GET['function'] = $argv[1];
unset($argv[0],$argv[1]);
if (!empty($argv))
	$_GET['args'] = implode('#', $argv); // TODO, I think we can now just pass in an array to execute, can't we???
unset($argc, $argv);

$_cqpweb_execute_cli_is_running = true;

require('../lib/execute.php');



//TODO use output buffering to capture the results, strip html and print?

// TODO make it possible for a corpus to be set here && passed through?
/*
/// on the cmdline, need some way to pass in a parameter corpus for $Corpus init.  MAYBE: $extra = NULL as 3rd parameter? (hash of extra vars.) and a use_parameter_corpus but macro?
cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CHECK_URLTEST, RUN_LOCATION_CORPUS);

// CLI needs a "be_user" option to pass through to env setup, as well as a be_corpus)


OR ... maybe an admin user can “be” as method call to $User?     eg

global $User;
$User->be("andrew");
$User->unbe();

 * 
 * 
 * 
 */


