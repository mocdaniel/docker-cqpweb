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
 * 
 * @file
 * 
 * Script that allows superusers direct access to the function library via the URL / get method.
 * 
 * in the format:
 * 
 * execute.php?function=foo&args=["string"#1#2]&locationAfter=[index.php?ui=search]
 * 
 * (note that everything within [] needs to be url-encoded for non-alphanumerics)
 * 
 * args is also allowed to be an array.
 * 
 * 
 * ANOTHER IMPORTANT NOTE:
 * =======================
 * 
 * It is quite possible to **break CQPweb** using this script.
 * 
 * It has been written on the assumption that anyone who is a superuser is sufficiently
 * non-idiotic to avoid doing so.
 * 
 * If for any given superuser this assumption is false, then that is his/her/your problem.
 * 
 * Not CQPweb's.
 * 
 */


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');


/* include all function files */
require('../lib/admin-install-lib.php');
require('../lib/annotation-lib.php');
require('../lib/cache-lib.php');
require('../lib/ceql-lib.php');
require('../lib/ceqlparser.php');
require('../lib/collocation-lib.php');
require('../lib/concordance-lib.php');
require('../lib/corpus-lib.php');
require('../lib/cqp.inc.php');
require('../lib/db-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/html-lib.php');
require('../lib/lgcurve-lib.php');
require('../lib/general-lib.php');
require('../lib/metadata-lib.php');
require('../lib/multivariate-lib.php');
require('../lib/plugin-lib.php');
require('../lib/query-lib.php');
require('../lib/sql-lib.php');
require('../lib/sql-definitions.php');
require('../lib/scope-lib.php');
require('../lib/template-lib.php');
require('../lib/upload-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/useracct-lib.php');
require('../lib/xml-lib.php');


cqpweb_startup_environment(CQPWEB_STARTUP_CHECK_ADMIN_USER, (PHP_SAPI == 'cli' ? RUN_LOCATION_CLI : RUN_LOCATION_CORPUS));
/* 
 * note above - only superusers get to use this script!
 * also note, this script assumes it is running within a corpus.
 * If it ISN'T, then you need to be careful not to call any
 * function that needs an environment $Corpus to be specified.
 * This applies, most critically, to admin-execute and execute-cli. 
 */




/* get the name of the function to run */
if (isset($_GET['function']))
	$function = $_GET['function'];
else
	execute_print_and_exit('No function specified for execute.php', 
		"You did not specify a function name for execute.php.\n\nYou should reload and specify a function.");




/* extract the arguments */
if (isset($_GET['args']))
{
	/* args can be either a '#' delimited string (where the things between # are strings
	 * either equal to or convertible to the arguments we want to pass),
	 * in which case we explode it;
	 * 
	 * OR it can be pre-prepared by script that includes this one, to be some kind of
	 * other single value (integer, object, whatever), which we array-ise in order to pass;
	 * 
	 * OR it can be an array, which we assume to be pre-prepared multiple arguments.
	 * 
	 * Note this means that it being integer 1, or an array containing integer 1, has the same effect.
	 * 
	 * It also means that an array cannot be passed as a bare single-argument: it must be wrapped.
	 * Strings that contain '#' naturally also cannot be passed as bare single-arguments, and
	 * likewise must be wrapped. 
	 */
	if (is_array($_GET['args']))
	{
		/* pre-prepared array of arguments */
		$argv = $_GET['args'];
		$argc = count($argv);
	}
	else if (!is_string($_GET['args']))
	{
		/* pre-prepared single argument */
		$argv = array($_GET['args']);
		$argc = 1;
	}
	else
	{
		/* non-prepared string containing a series of (possiby convertible) string arguments */
		$argv = explode('#', $_GET['args']);
		$argc = count($argv);
	}
}
else
{
	$argc = 0;
	$argv = array();
}

/* 50 parameters should be plenty for anybody. */
if ($argc > 50)
	execute_print_and_exit('Too many arguments for execute.php', 
		"You specified too many arguments for execute.php.\n\nThe script only allows up to 50  arguments, as a cautionary measure."
		);


/* Convert string arguments matching symbolic constants to declared value of constant,
   but ONLY if we ran with $_cqpweb_execute_cli_is_running */
global $_cqpweb_execute_cli_is_running; 

if ($_cqpweb_execute_cli_is_running ?? false) 
{
	$const_map = get_defined_constants(true)['user'];
	for ($i = 2 ; $i < $argc ; $i++)
		if (is_string[$argv[$i]])
			if (isset($const_map[$argv[$i]]))
				$argv[$i] = $const_map[$argv[$i]];
	unset($const_map);
}



/* check the function is safe to call */
$all_function = get_defined_functions();
if (in_array($function, $all_function['user']))
	; /* all is well */
else
	execute_print_and_exit('Function not available -- execute.php',
'The function you specified is not available via execute.php.

The script only allows you to call CQPweb\'s own function library -- NOT the built-in functions
of PHP itself. This is for security reasons (otherwise someone could hijack your password and go
around calling passthru() or unlink() or any other such dodgy function with arbitrary arguments).'
		);



/* run the function */
call_user_func_array($function, $argv);


cqpweb_shutdown_environment();


/* go to the specified address, if one was specified AND if the HTTP headers have not been sent yet
 * (if execution of the function caused anything to be written, then they WILL have been sent)
 */


if ( isset($_GET['locationAfter']) && !headers_sent() )
	header('Location: ' . url_absolutify($_GET['locationAfter']));
else if ( ! isset($_GET['locationAfter']) && !headers_sent() )
	execute_print_and_exit( 'CQPweb -- execute.php', 'Your function call has been executed.');


/*
 * =============
 * END OF SCRIPT
 * =============
 */

/** a special form of "exit" function just used by execute.php script */
function execute_print_and_exit($title, $content)
{
	global $_cqpweb_execute_cli_is_running;
	if ($_cqpweb_execute_cli_is_running ?? false) 
		exit("CQPweb has completed the requested action.\n");
	else
		exit(<<<HERE
<html>
<head><title>$title</title></head>
<body>
<pre>
$content

CQPweb (c) 2008-today
</pre>
</body>
</html>

HERE
		);
}
