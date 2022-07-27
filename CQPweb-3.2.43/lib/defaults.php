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
 * This file contains the global configuration checks and default values that are imported into the $Config object.
 * 
 * It can be seen as "the code that interprets the config file", as it mostly checks things that can be supplied there
 * (but also supplies some universal stuff that is not dependent on the config file).
 */





/* ------------------------ *
 * ARRAYS OF CONSTANTS ETC. *
 * ------------------------ */

/* most of these are arrays of strings that get re-used over and over.
 * so, unlike a ton of other stuff, their being set here is simply about
 * creating easily-accessible values (in $Config, usually.) 
 */ 


/* "reserved words" that can't be used for corpus ids.
 * All reserved words are 3 lowercase letters, and any new ones we add will also be 3 lowercase letters */
$cqpweb_reserved_subdirs = array('adm', 'bin', 'css', 'doc', 'exe', 'jsc', 'lib', 'rss', 'usr'); 
/* nb: jsc stands for "javascript clientside"; if ever we have any "javascript serverside", 'jss' will be used */


/* mapper hash for turning privilege type constants into descriptive strings for printing etc. */
$privilege_type_descriptions = array(
	PRIVILEGE_TYPE_CORPUS_FULL       => "Full access to corpus",
	PRIVILEGE_TYPE_CORPUS_NORMAL     => "Normal access to corpus",
	PRIVILEGE_TYPE_CORPUS_RESTRICTED => "Restricted access to corpus",
	PRIVILEGE_TYPE_FREQLIST_CREATE   => "Permission to build frequency list",
	PRIVILEGE_TYPE_UPLOAD_FILE       => "Permission to upload file",
	PRIVILEGE_TYPE_INSTALL_CORPUS    => "Permission to use corpus-installer plugin",
	PRIVILEGE_TYPE_CQP_BINARY_FILE   => "Access to CQP binary files",
	PRIVILEGE_TYPE_EXTRA_RUNTIME     => "Extension to CQPweb's runtime limit",
	PRIVILEGE_TYPE_DISK_FOR_UPLOADS  => "Storage allocation in the upload area",
	PRIVILEGE_TYPE_DISK_FOR_CORPUS   => "Storage allocation for user's corpora",
);
/* some functions define alternative mapper hashes for their own purposes, note! */


/* mapper hash for turning datatype constants into descriptive strings for printing etc. */
$metadata_type_descriptions = array(
	METADATA_TYPE_NONE           => 'No data: XML element',
	METADATA_TYPE_CLASSIFICATION => 'Classification',
	METADATA_TYPE_FREETEXT       => 'Free text',
	METADATA_TYPE_IDLINK         => 'ID link',
	METADATA_TYPE_UNIQUE_ID      => 'Unique ID',
	METADATA_TYPE_DATE           => 'Date',
);


/* mapper hash containing the SQL column declarations needed for different metadata types. */
$metadata_mysql_type_map = array (
	/* there is, of course, no entry for datatype NONE */
	METADATA_TYPE_CLASSIFICATION => 'varchar(255) default NULL COLLATE utf8_bin',
	METADATA_TYPE_FREETEXT       => 'text default NULL COLLATE utf8_general_ci',
	METADATA_TYPE_IDLINK         => 'varchar(255) default NULL COLLATE utf8_bin',
	METADATA_TYPE_UNIQUE_ID      => 'varchar(255) default NULL COLLATE utf8_bin',
	METADATA_TYPE_DATE           => 'varchar(255) default NULL COLLATE utf8_bin', //TODO this could be char 8 most likely??
	);
//TODO this is now outdated. See sql-definitions

/* mapper hash containing printable descriptions for user account status consts. */
$user_account_status_description_map = array (
	USER_STATUS_ACTIVE           => 'Active',
	USER_STATUS_UNVERIFIED       => 'Unverified',
	USER_STATUS_SUSPENDED        => 'Inactive: account suspended',
	USER_STATUS_PASSWORD_EXPIRED => 'Inactive: password expired',
	);

/* mapper hash containing printable descrriptions for user corpus-installer processes status constants */
$installer_process_status_description_map = array(
	INSTALLER_STATUS_UNKNOWN  => 'Unknown (??)',
	INSTALLER_STATUS_WAITING  => 'Waiting in job queue',
	INSTALLER_STATUS_TAGGING  => 'Generating input data',
	INSTALLER_STATUS_ENCODING => 'Encoding corpus',
	INSTALLER_STATUS_INDEXING => 'Indexing corpus',
	INSTALLER_STATUS_FREQLIST => 'Building frequency lists',
	INSTALLER_STATUS_SETUP    => 'Finalising setup',
	INSTALLER_STATUS_DONE     => 'Installation complete',
	INSTALLER_STATUS_ABORTED  => 'Aborted!',
);



// ***************************************************************************************************
// TODO -- organise the defaults in this file according to how they are organised in the config manual
//         (for improved ease of reference / maintainability of both this file and the manual)
// ***************************************************************************************************


/* ------------------------ *
 * GENERAL DEFAULT SETTINGS *
 * ------------------------ */

/* Global setting: are we running on Windows? */
if (!isset($cqpweb_running_on_windows))
{
	/* PHP_OS_FAMILY defined in PHP >= 7.2.0 */
	if (is_null(@constant('PHP_OS_FAMILY')))
		$cqpweb_running_on_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
	else
		$cqpweb_running_on_windows = (PHP_OS_FAMILY == 'Windows');
}

if (!isset($cqpweb_switched_off))
	$cqpweb_switched_off = false;

/* Is this copy of CQPweb available for access via the internet? */
if (!isset($cqpweb_no_internet))
	$cqpweb_no_internet = false;

/* supply default email address */
if (!isset($cqpweb_email_from_address))
	$cqpweb_email_from_address = '';

	
/*
 * ---------------------
 * USER ACCOUNT CREATION
 * ---------------------
 */

if (!isset($allow_account_self_registration))
	$allow_account_self_registration = ( ! $cqpweb_no_internet );

if (!isset($account_create_contact))
	$account_create_contact = '';

if (!isset($account_create_captcha))
	$account_create_captcha = true;
/* override captcha setting if no gd extension */
if (! extension_loaded('gd'))
	$account_create_captcha = false;

if (!isset($account_create_one_per_email))
	$account_create_one_per_email = false;

if (!isset($blowfish_cost))
	$blowfish_cost = 11;

if (!isset($create_password_function))
	$create_password_function = "password_insert_internal";




/* name for cookies stored in users' browsers */
if (!isset($cqpweb_cookie_name))
	$cqpweb_cookie_name = "CQPwebLogonToken";

/* how long can someone stay logged in without visiting the site? */
if (!isset($cqpweb_cookie_max_persist))
	$cqpweb_cookie_max_persist = 5184000;
/* 5184000 = 60 days */ 



/* Does mysqld have file-write/read ability? If set to true, CQPweb uses LOAD DATA
 * INFILE and SELECT INTO OUTFILE. If set to false, file write/read into/out of
 * mysql tables is done via the client-server link.
 * 
 * Giving mysqld file access, so that CQPweb can directly exchange files in 
 * the temp/cache directory with the MySQL server, may be considerably more efficient.
 * 
 * (BUT -- we've not tested this yet)
 * 
 * The default is false. 
 */
if (!isset($mysql_has_file_access))
	$mysql_has_file_access = false;

/*
 * -- If mysqld has file access,  it is mysqld (not the php-mysql-client) which will do the opening of the file.
 * -- But if mysqld does not have file access, then we should load all infiles locally.
 */
if ($mysql_has_file_access)
	$mysql_LOAD_DATA_INFILE_command = 'LOAD DATA INFILE';
else
	$mysql_LOAD_DATA_INFILE_command = 'LOAD DATA LOCAL INFILE';

/* Has MySQL got LOAD DATA LOCAL disabled? */
if (!isset($mysql_local_infile_disabled))
	$mysql_local_infile_disabled = false;

/* From the previous two variables, deduce whether we have ANY infile access. */
if ($mysql_has_file_access)
	/* if the SERVER has file access, then lack of LOAD DATA LOCAL doesn't matter */
	// in THEORY. I haven't checked this out with a server that has LOAD DATA LOCAL disabled.
	$mysql_infile_disabled = false;
else
	/* otherwise, whether we have ANY infile access is dependent on whether we have local access */
	$mysql_infile_disabled = $mysql_local_infile_disabled;




/* These are defaults for the max amount of memory allowed for CWB programs that let you set this,
 * counted in megabytes. The first is used for web-scripts, the second for CLI-scripts. */
if (!isset($cwb_max_ram_usage))
	$cwb_max_ram_usage = 50;
else
	$cwb_max_ram_usage = (int)$cwb_max_ram_usage;
if (!isset($cwb_max_ram_usage_cli))
	$cwb_max_ram_usage_cli = 1000;
else
	$cwb_max_ram_usage_cli = (int)$cwb_max_ram_usage_cli;
/* the default allows generous memory for indexing in command-line mode,
 * but is stingy in the Web interface, so people can't bring down the server accidentally */


/* defaults for paths: we add on / unless it is empty, in which case, a zero-string gets embedded before program names. */
$path_to_cwb  = (empty($path_to_cwb)  ? '' : rtrim($path_to_cwb,  DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR );
$path_to_gnu  = (empty($path_to_gnu)  ? '' : rtrim($path_to_gnu,  DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR );
$path_to_perl = (empty($path_to_perl) ? '' : rtrim($path_to_perl, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR );
$path_to_r    = (empty($path_to_r)    ? '' : rtrim($path_to_r,    DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR );

/* Canonical form for $perl_extra_directories is an array of directories; but the input format is a string of pipe-
 * delimited directories. This bit of code converts. An empty array is used if the config string vairable is not set.    */
if (isset($perl_extra_directories))
{
	$perl_extra_directories = explode('|',$perl_extra_directories);
	$perl_extra_directories = array_map('rtrim',$perl_extra_directories);
}
else
	$perl_extra_directories = array();

if (!isset($mysql_utf8_set_required))
	$mysql_utf8_set_required = true;

/* the next defaults are for tweaks to the system -- not so much critical! */

if (! isset($show_match_strategy_switcher))
	$show_match_strategy_switcher = false;

if (!isset($hide_experimental_features))
	$hide_experimental_features = false;

if (!isset($use_the_new_ceql))
	$use_the_new_ceql = true;

if (!isset($css_path_for_homepage))
	$css_path_for_homepage = "css/CQPweb-blue.css";

if (!isset($css_path_for_adminpage))
	$css_path_for_adminpage = "../css/CQPweb-red.css";

if (!isset($css_path_for_userpage))
	$css_path_for_userpage = "../css/CQPweb-green.css";

if (!isset($homepage_use_corpus_categories))
	$homepage_use_corpus_categories = false;

if (!isset($homepage_welcome_message))
	$homepage_welcome_message = "Welcome to CQPweb!";

if (!isset($searchpage_corpus_name_suffix))
	$searchpage_corpus_name_suffix = ': <em>powered by CQPweb</em>';

if (!isset($print_debug_messages))
	$print_debug_messages = false;

if (!isset($debug_messages_textonly))
	$debug_messages_textonly = false;

if (!isset($all_users_see_backtrace))
	$all_users_see_backtrace = false;

if (!isset($uploaded_file_bytes_to_show))
	$uploaded_file_bytes_to_show = 102400;


/* This is not a default - it cleans up the input, so we can be sure the root URL ends in a slash. */
if (isset($cqpweb_root_url))
	$cqpweb_root_url = rtrim($cqpweb_root_url, '/') . '/';




/* ----------------------- *
 * USER INTERFACE SETTINGS *
 * ----------------------- */

if (!isset($dist_graph_img_path))
	$dist_graph_img_path = "../css/img/blue.bmp";

if (!isset($dist_num_files_to_list))
	$dist_num_files_to_list = 100;


if (!isset($default_per_page))
	$default_per_page = 50;

if (!isset($default_history_per_page))
	$default_history_per_page = 100;


/* collocation defaults */
	// TODO when these are documented, note that these are the defaults for user settings, and can be overridden.
if (!isset($default_colloc_range))
	$default_colloc_range = 5;

if (!isset($default_colloc_calc_stat))
	//$default_colloc_calc_stat = 6; 	/* {6 == log-likelihood} is default collocation statistic */
	$default_colloc_calc_stat = 8; 	/* {6 == log-ratio} is default collocation statistic */
	// TODO might be niceto haveinteger constants for collocation stats....

if (!isset($default_colloc_minfreq))
	$default_colloc_minfreq = 5;

if (!isset($default_collocations_per_page))
	$default_collocations_per_page = 50;

if (!isset($collocation_disallow_cutoff))
	$collocation_disallow_cutoff = 100000000; /* cutoff for disallowing on-the-fly freqtables altogether: 100 million */

if (!isset($collocation_warning_cutoff))
	$collocation_warning_cutoff = 5000000; /* cutoff for going from a minor warning to a major warning: 5 million */

/* NB, warning cutoff must always be lower than disallow cutoff: so let's sanity check */
if ($collocation_warning_cutoff >= $collocation_disallow_cutoff)
	$collocation_warning_cutoff = $collocation_disallow_cutoff - 1;

/* TODO ultimately, the "disallow" cutoff should be a user privilege. */

/* query concordance-download default */
if (!isset($default_words_in_download_context))
	$default_words_in_download_context = 10;

// TODO the above should prolly all be $User settings, not $Corpus settings.
// some of the above are part of the $User profile; the rest should eb in $Config, if they are system-wide, or $Corpsu, if they are corpus-specific.
// CHECK THIS OUT.




/* -------------------------------- *
 * STORED DATA SIZE LIMITS SETTINGS *
 * -------------------------------- */

/* Size limit of cache directory (based on CQP save-files only! not anything else that might be in that folder) */
if (!isset($query_cache_size_limit))
{
	/* this is what the variable used to be called: allow for backwards compatibility. */
	if (!isset($cache_size_limit))
		$cache_size_limit = 6442450944;
	$query_cache_size_limit = $cache_size_limit;
	unset($cache_size_limit);
}


/* Size limit for the MySQL restriction-data cache */
if (!isset($restriction_cache_size_limit))
	$restriction_cache_size_limit = 6442450944;



// TODO: don't we need a cache limit notation for subcorpus dumpfiles?
// (the dumpfile cache functions in scope-lib.php don't seem to be in use)


/* Size limit for the frequency table cache: defaulting to 6 gig */
if (!isset($freqtable_cache_size_limit))
{
	/* this is what the variable used to be called: allow for backwards compatibility. */
	if (!isset($mysql_freqtables_size_limit))
		$mysql_freqtables_size_limit = 6442450944;
	$freqtable_cache_size_limit = $mysql_freqtables_size_limit;
	unset($mysql_freqtables_size_limit);
}


/* Size limit for the MySQL user-database cache */
if (!isset($db_cache_size_limit))
	$db_cache_size_limit = 6442450944;




/* ---------------------------- *
 * USER CORPUS INSTALL SETTINGS *
 * ---------------------------- */

if(!isset($user_corpora_enabled))
	$user_corpora_enabled = false;


//// TODO NOT DOCUMENTED YET!!!!!
if(!isset($colleaguate_system_enabled))
	$colleaguate_system_enabled = false;
if (!isset($max_installer_processes))
	$max_installer_processes = 1;
if(!isset($installer_process_wait_secs))
	$installer_process_wait_secs = 3;
// endof   not yet documented.



/* ------------------------ *
 * DB SIZE MAXIMUM SETTINGS *
 * ------------------------ */

// TODO the way DB maxima are calculated is dodgy, to say the least.
// PROBLEMS: (1) names beginning $default that aren;t defaults is confusing
// (2) are the limits working as they should?

/* Default maximum size for DBs -- can be changed on a per-user basis */
if (!isset($default_max_dbsize))
	$default_max_dbsize = 1000000;
/* important note about default_max_dbsize: it refers to the ** query ** on which 
   the create_db action is being run. A distribution database will have as many rows as there
   are solutions, but a collocation database will have the num solutions x window x 2.

   For this reason we need the next variable as well, to control the relationship
   between the max dbsize as taken from the user record, and the effective max dbsize
   employed when we are creating a collocation database (rather than any other type of DB)
   */
if (!isset($colloc_db_premium))
	$colloc_db_premium = 4;

/* Total size (in rows) of database (distribution, colloc, etc) tables
 * before cached dbs are deleted: default is 100 of the biggest db possible  */
if (!isset($default_max_fullsize_dbs_in_cache))
	$default_max_fullsize_dbs_in_cache = 100;
// TODO when doing this note: $default_max_dbsize is inserted into the User profile and stays there!!!
// there, it is known as max_dbsize, and governs db-lib.php. This needs to be changed to use the permission system instead.
// see note above regarding "db_cache_size_limit"







/* ------------------------------- *
 * USER DB CREATE-PROCESS SETTINGS *
 * ------------------------------- */
	

/* max number of concurrent mysql processes of any one kind (big processes ie collocation, sort) */
if (!isset($mysql_big_process_limit))
	$mysql_big_process_limit = 5;

$mysql_process_limit = array(
	'freqtable' => $mysql_big_process_limit,
	'colloc' => $mysql_big_process_limit,
	'sort' => $mysql_big_process_limit,
	'dist' => (20 * $mysql_big_process_limit), /* Note: dist-db is lightweight, therefore there is a multiplier here. */ 
	'catquery' => $mysql_big_process_limit
	);
	//TODO use integer constants instead of strings.

/* plus names for if they need to be printed */
$mysql_process_name = array(
	'freqtable' => 'frequency table',
	'colloc'=> 'collocation',
	'sort' => 'query sort',
	'dist' => 'distribution',
	'catquery' => 'categorised query'
	);
	//TODO use integer constants instead of strings.
	
	
	
	
$query_mode_map = array(
		'cqp'       => 'CQP syntax',
		'sq_nocase' => 'Simple query (ignore case)',
		'sq_case'   => 'Simple query (case-sensitive)',
		);

		
/*
 * --------------------------------------------
 * DEFAULTS FOR THE RSS FEED OF SYSTEM MESSAGES
 * --------------------------------------------
 */
if (!isset($rss_feed_available))
	$rss_feed_available = true;
if ($rss_feed_available)
{
	if (!isset($rss_link))
		$rss_link = '..';
	if (!isset($rss_description))
		$rss_description = 'Messages from the CQPweb server\'s administrator.';
	if (!isset($rss_feed_title))
		$rss_feed_title = 'CQPweb System Messages';
}
