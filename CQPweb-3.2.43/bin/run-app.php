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
 * CQPweb apps are programs that run within the CQPweweb environment, 
 * with access to the internal function library and the main data 
 * structures (like global objects $Corpsu, $User, $Config).
 * 
 * This file, run-app.php, is the program that - given the filename of 
 * an app - sets up the envioronment, calls the code in the app, and then 
 * tidies up afterwards. 
 * 
 * Apps should not contain any global-scope code. Instead, they should 
 * supply functions for run-app.php to call.
 *    
 * Functions that MUST be supplied:
 * 
 *    - cqpweb_run_app_main(int arg_count, array arg_vector) : int
 *      The main function which contains the body of the app.
 *      Returns the exit code. Use the passed arg vector
 *      rather than global $argv. This function can access the
 *      full CQPweb environemnt - the other functions, below,
 *      cannot, because they are used prior to environment setup. 
 * 
 * Functions that MAY be supplied:
 *      
 *    - cqpweb_run_app_options() : array
 *      This function should return an array of CQPwebRunAppOptionSpec
 *      objects, each of which specifies a possible option.
 *      These objects are the easy way to parse options, because 
 *      they link together long and short, and allow an automatic 
 *      usage message to be generated. Also, --help and --version
 *      (-h / -v) are automagically enabled. If this function is NOT
 *      supplied, none of this magic is made avaialble, and the main()
 *      function will need to work out options on its own. 
 *      The option data will be stored as a member of the global
 *      $Config object, namely $Config->run_app_option_data .
 *      The format of this is true/fasle for boolean options, 
 *      simplex value for single-valued options, array for 
 *      multi-valued options, indexed by the long-option string.
 *      
 *    - cqpweb_run_app_list_library_files() : array
 *      This function, if specified, shoudl return an array of 
 *      filenames where each file is a library file in the
 *      lib directory which should be included into the script.
 *      Note: including files with glolbal code will make things
 *      go badly wrong and probably break.
 * 
 *    - cqpweb_run_app_help(array $options_as_given) : void
 *      A function to print out the help text. If not supplied,
 *      a standard function is used. Either way, the call to this 
 *      function supplies an array which is the return from 
 *      cqpweb_run_app_options() if that was supplied, so that 
 *      option-info can be referenced in the help text.
 *    
 *    - cqpweb_run_app_specify_corpus(array $option_data) : string
 *      This function should specify the handle of the corpus
 *      that the program is to run "within". If an empty value
 *      is returned, then the usual auto-detect mechanism will 
 *      be used. If this function is not provided, then 
 *      the app will run in an environment with no corpus set.
 *      This mostly affects use of the global $Corpus object.
 *      The argument is the option data resulting from the parsing
 *      of the command-line options. This function can also access 
 *      the global $argv if desired. 
 *      
 *    - cqpweb_run_app_name() : string
 *      A function to return the name of the app for use 
 *      with -v / -h.
 *      
 *    - cqpweb_run_app_version() : string
 *      A function to return the version of the app for use 
 *      with -v / -h.
 */



/* this library is needed before main environment setup */
require(__DIR__ . '/cli-lib.php');


/*
 * BEGIN SCRIPT 
 */

$argc = $argc ?? 1;
$argv = $argv ?? ['run-app.php', '????'];
if (!is_file($argv[1]))
	exit("Cannot run CQPweb app: the specified app file '$argv[1]' does not exist.\n.");
require($argv[1]);


/* check that we have the functions we need */
if (!function_exists('cqpweb_run_app_main'))
	exit("Cannot run CQPweb app: requisite function 'cqpweb_run_app_main()' not supplied in '{$argv[1]}'.\n");


/*
 * Generic help function, only used if the app does not define one.
 */
if (!function_exists('cqpweb_run_app_help'))
{
	function cqpweb_run_app_help($option_info = []) { cqpweb_run_app_help_builtin($option_info); }
}


$option_info = [];
$missing = NULL;
if (function_exists('cqpweb_run_app_options'))
{
	$option_info = cqpweb_run_app_options();

	/* check for "-h/--help" and "-v/--version" which can't be used for anything else. */
	switch(true)
	{
	case in_array('-h', $argv):
	case in_array('--help', $argv):
		cqpweb_run_app_help($option_info);
		exit(1);
	case in_array('-v', $argv):
	case in_array('--version', $argv):
		$vv = function_exists('cqpweb_run_app_version') ? cqpweb_run_app_version() : '?.?.?'  ;
		$nn = function_exists('cqpweb_run_app_name'   ) ? cqpweb_run_app_name()    : $argv[0] ;
		echo "$nn for CQPweb, version $vv\n";
		exit(1);
	}
}

$option_data = [];
if (!empty($option_info))
	$option_data = cqpweb_run_app_parse_out_argopts($option_info, $missing);

/* were all arguments specified as compulsory actually supplied? */
if (!empty($missing))
{
	echo "Usage error when invoking the CQPweb app!\n";
	foreach($missing as $m)
		echo "\tYou did not supply the compulsory option --$m.\n";
	echo "\n";
	$cqpweb_run_app_help_builtin_SHORTFORM = true;
	cqpweb_run_app_help($option_info);
	exit(1);
}



/* include library files */

chdir(__DIR__);

/* we are now within "bin" */

/* include the need-them-always library files */

require('../lib/environment.php');
require('../lib/exiterror-lib.php');
require('../lib/html-lib.php');
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/sql-lib.php');
require('../lib/useracct-lib.php');

/* what other library files are needed? */

if (function_exists('cqpweb_run_app_list_library_files'))
	foreach(cqpweb_run_app_list_library_files() as $incfile)
		if (!file_exists("../lib/$incfile"))
			exit("Cannot run CQPweb app: the app's list of required library files includes '$incfile', which seems not to exist.");
		else
			require_once("../lib/$incfile");
unset($incfile);

$Config = NULL;


$rl = RUN_LOCATION_CLI;
$cp = NULL;
if (function_exists('cqpweb_run_app_specify_corpus'))
{
	$cp = cqpweb_run_app_specify_corpus($option_data);
	if (empty($cp))
		$cp = NULL;
	$rl = RUN_LOCATION_CORPUS;
	chdir('../exe');
}

/* this startup uses the same super-safe checks as execute.php */
cqpweb_startup_environment(CQPWEB_STARTUP_CHECK_ADMIN_USER, $rl, $cp);
unset($rl, $cp);

/* hang any options off the global array */
$Config->run_app_option_data = $option_data;
unset($option_info, $option_data);


/* we can now call the main function, passing it the arguments not parsed out by the creation of $option_data */
$cqpweb_run_app_exit_code = cqpweb_run_app_main($argc, $argv);

cqpweb_shutdown_environment();

/* the main return value should have been an error integer code, or an error message, or 0 for all OK. 
 * But if the app's main() has no return, we'll have a NULL ... which we will be kind and count as status 0. */
exit($cqpweb_run_app_exit_code ?? 0);





/*
 * =============
 * end of script
 * =============
 */

/**
 * Specifier of a command-line optioon for a CQPweb app. 
 */
class CQPwebRunAppOptionSpec
{
	/** Option has no value (switches a boolean flag to true that is false by default) */
	const NO     = 0;
	/** Option has a single value; multiple uses of same option will overwrite. */
	const SINGLE = 1;
	/** Option can have multiple values; the extracted value will be an arrtay of 0+ elements */ 
	const MULTI  = 2;
	
	public $long; 
	public $short;
	public $has_value;
	public $usage;
	public $usage_placeholder;
	public $compulsory;
	
	/**
	 * Create an option specification by passing in the values for that spec.
	 * 
	 * @param string $long                  Long option.
	 * @param string $short                 Single-character short option alternative. Can be NULL.
	 * @param int    $has_value             Does it have a value? (0,1,2; class constants.)
	 * @param string $usage                 Message to print in the "--help" description of the option.
	 * @param string $usage_placeholder     Value placeholder for the "--help" description, for instance
	 *                                      "file" for --infile=<file>, -i <file>. If empty, the placeholder 
	 *                                      will just be "value".
	 * @param bool   $compulsory            Whether the option is compulsory or not. 
	 */
//	public function __construct(string $long, string $short, int $has_value, 
//	                            string $usage = '', string $usage_placeholder = 'value', bool $compulsory = false)
	public function __construct( $long, $short, $has_value, $usage = '', $usage_placeholder = 'value',  $compulsory = false)
	{
		$this->long              = $long;
		$this->short             = $short;
		$this->has_value         = $has_value;
		$this->usage             = $usage;
		$this->usage_placeholder = $usage_placeholder;
		$this->compulsory        = $compulsory;
	}
}


/** 
 * Parse options out of arguments in a simplified way relative to getopt().
 * 
 * @param  array $opt_info              Array of CQPwebRunAppOptionSpec
 * @param  array $compulsory_missing    Out param: list of compulsory opts not found will be placed here  
 * @return array                        Array of resulting options (in a friendlier format than getopt).
 */
function cqpweb_run_app_parse_out_argopts($opt_info, &$compulsory_missing)
{
	global $argv;
	global $argc;

	$map_info = [];
	$data = [];

	$short = '';
	$long  = [];
	$short_map = [];

	foreach($opt_info as $i)
	{
		switch($i->has_value)
		{
		case CQPwebRunAppOptionSpec::NO: /* boolean*/
			$data[$i->long] = false;
			$long[] = $i->long;
			break;
		case CQPwebRunAppOptionSpec::SINGLE: /* single value */
			$data[$i->long] = NULL;
			$long[] = $i->long . ':';
			break;
		case CQPwebRunAppOptionSpec::MULTI: /* multiple values */
			$data[$i->long] = [];
			$long[] = $i->long . ':';
			break;
		}
		if (isset($i->short))
		{
			$short_map[$i->short] = $i->long;
			$short .= $i->short . ($i->has_value ? ':' : '');
		}
		$i->compulsory = $i->compulsory ?? false;
		$map_info[$i->long] = $i;
	}
	unset($opt_info);

	/* 0 == 'run-app'; we need [1] to become [0] because getopt would otherwise stop at [1] */
	unset($argv[0]);
	$argv = array_values($argv);
	$argc = count($argv);

	/* flexigetopt used because it actually accesses global $argv *as amended* */
	$junk = NULL;
	foreach(flexigetopt($short, $long, $junk, true) as $o => $v)
	{
		if (1 == strlen($o))
			$o = $short_map[$o];

		$info = $map_info[$o];

		switch($info->has_value)
		{
		case CQPwebRunAppOptionSpec::NO:
			$data[$o] = true;
			break;
		case CQPwebRunAppOptionSpec::SINGLE:
			$data[$o] = $v;
			break;
		case CQPwebRunAppOptionSpec::MULTI:
			if (is_array($v))
				$data[$o] = array_merge($data[$o], $v);
			else
				$data[$o][] = $v;
			break;
		}
	}

	/* check for missing options that were declared compulsory */
	$compulsory_missing = [];
	foreach($map_info as $o => $info)
		if ($info->compulsory)
			if ( is_null($data[$o]) || (is_array($data[$o]) && empty($data[$o])) )
				$compulsory_missing[] = $o;

	return $data;
}

/** Backend for the default "help" function which is used if no such function is defined by the app. */
function cqpweb_run_app_help_builtin($option_info)
{
	global $argv;

	global $cqpweb_run_app_help_builtin_SHORTFORM; /* use of a global for this is somewhat shonky, but hey */

	$progname = (basename($argv[0]) == basename(__FILE__)) ? $argv[1] : $argv[0];
	
	if ($cqpweb_run_app_help_builtin_SHORTFORM ?? false)
	{
		echo "Usage for this application:\n\tphp run-app.php $progname [OPTIONS] [[--] arg1 arg2 arg3 ...]\n\n";
		echo "Use the --help option to display details on available options.\n\n";
		return;	
	}
	echo "Usage for this application:\n\tphp run-app.php $progname [OPTIONS] [[--] arg1 arg2 arg3 ...]\n\nAvailable options:\n\n";

	foreach($option_info as $i)
	{
		$i->usage = $i->usage ?? '...';
		$i->usage_placeholder = $i->usage_placeholder ?? 'value';
		if (!$i->has_value)
		{
			$head = "--{$i->long}";
			if (!empty($i->short))
				$head .= ", -{$i->short}";
			$comp = '(boolean flag; option is false if flag not given)';
		}
		else
		{
			$head = "--{$i->long}=<{$i->usage_placeholder}>";
			if (!empty($i->short))
				$head .= ", -{$i->short} <{$i->usage_placeholder}>";
			if (2 == $i->has_value)
				$comp = ($i->compulsory ?? false) ? '(required at least once)' : '(optional; multiple values can be supplied)';
			else
				$comp = ($i->compulsory ?? false) ? '(compulsory; one value only)' : '(optional; one value only)';
		}
		echo $head, "\n        ", $comp, "\n";
		$u = str_replace("\n", " ", $i->usage);
		$u = wordwrap($u, 70, "\n", true);
		foreach(explode("\n", $u) as $line)
			echo "        ", $line, "\n";
		echo "\n";
	}

	echo "\n";
}

