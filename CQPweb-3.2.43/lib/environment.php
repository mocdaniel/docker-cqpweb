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
 * This file contains several things:
 * 
 * (1) Constant definitions for the system.
 * 
 * (2) The three global objects ($Config, $User, $Corpus) into which everything is stuffed.
 * 
 * (3) The environment startup and shutdown functions that need to be called to get things moving.
 * 
 */




/*
 * ------------------------------- *
 * Constant definitions for CQPweb *
 * ------------------------------- *
 */


/*
 * version number of CQPweb 
 */
define('CQPWEB_VERSION', '3.2.43');


/*
 * FLAGS for cqpweb_startup_environment()
 */
 
define('CQPWEB_STARTUP_NO_FLAGS',              0);
define('CQPWEB_STARTUP_DONT_CONNECT_CQP',      1);
define('CQPWEB_STARTUP_DONT_CONNECT_SQL',      2);
define('CQPWEB_STARTUP_CHECK_OWNER_OR_ADMIN',  4);
define('CQPWEB_STARTUP_CHECK_ADMIN_USER',      8);
define('CQPWEB_STARTUP_DONT_CHECK_USER',       16);
define('CQPWEB_STARTUP_ALLOW_ANONYMOUS_ACCESS',32);   /* which is *not* the same thing as the previous! */


/*
 * Run location constants 
 */

define('RUN_LOCATION_CORPUS',                  0);
define('RUN_LOCATION_MAINHOME',                1);
define('RUN_LOCATION_ADM',                     2);
define('RUN_LOCATION_USR',                     3);
define('RUN_LOCATION_CLI',                     4);
define('RUN_LOCATION_RSS',                     5);
define('RUN_LOCATION_USERCORPUS',              6);


/*
 * Metadata type constants (for texts and XML segments)
 */

define('METADATA_TYPE_NONE',                   0);
define('METADATA_TYPE_CLASSIFICATION',         1);
define('METADATA_TYPE_FREETEXT',               2);
define('METADATA_TYPE_UNIQUE_ID',              3);
define('METADATA_TYPE_IDLINK',                 4);
define('METADATA_TYPE_DATE',                   5);


/*
 * User-database type constants
 */

define('DB_TYPE_NONE',                         0);
define('DB_TYPE_DIST',                         1);
define('DB_TYPE_COLLOC',                       2);
define('DB_TYPE_SORT',                         3);
define('DB_TYPE_CATQUERY',                     4);
define('DB_TYPE_PARLINK',                      5);

/*
 * Collocation statistic constants
 */

define('COLLSTAT_RANK_FREQ',                   0);
define('COLLSTAT_MI',                          1);
define('COLLSTAT_MI3',                         2);
define('COLLSTAT_ZSCORE',                      3);
define('COLLSTAT_TSCORE',                      4);
// define('COLLSTAT_CHI_SQUARED',                 5);
define('COLLSTAT_LOG_LIKELIHOOD',              6);
define('COLLSTAT_DICE',                        7);
define('COLLSTAT_LOG_RATIO',                   8);
define('COLLSTAT_LR_CONSERVATIVE',             9);



/*
 * Keywords (and key tags) statistic constants
 */

define('KEYSTAT_NONE_COMPARE',                 0);
define('KEYSTAT_LOGLIKELIHOOD',                1);
define('KEYSTAT_LR_UNFILTERED',                2);
define('KEYSTAT_LR_WITH_LL',                   3);
define('KEYSTAT_LR_WITH_CONFINT',              4);
define('KEYSTAT_LR_CONSERVATIVE',              5);




/* 
 * Plugin type constants 
 */

define('PLUGIN_TYPE_UNKNOWN',                  0);
define('PLUGIN_TYPE_ANNOTATOR',                1);
define('PLUGIN_TYPE_FORMATCHECKER',            2);
define('PLUGIN_TYPE_SCRIPTSWITCHER',           4);
define('PLUGIN_TYPE_POSTPROCESSOR',            8);
define('PLUGIN_TYPE_CORPUSINSTALLER',          16);
define('PLUGIN_TYPE_QUERYANALYSER',            32);
define('PLUGIN_TYPE_CORPUSANALYSER',           64);
define('PLUGIN_TYPE_CEQLEXTENDER',             128);
define('PLUGIN_TYPE_ANY',                      1|2|4|8|16|32|64|128);


/*
 * User account state constants
 */

define('USER_STATUS_UNVERIFIED',               0);
define('USER_STATUS_ACTIVE',                   1);
define('USER_STATUS_SUSPENDED',                2);
define('USER_STATUS_PASSWORD_EXPIRED',         3);


/*
 * Colleague link constants
 */

define('COLLEAGUATE_STATUS_UNDEFINED',         0);
define('COLLEAGUATE_STATUS_PENDING',           1);
define('COLLEAGUATE_STATUS_ACTIVE',            2);
define('COLLEAGUATE_STATUS_ANY',               3);


/*
 * Privilege types
 */

define('PRIVILEGE_TYPE_NO_PRIVILEGE',          0);	/* can be used to indicate absence of one or more privileges; not used in the DB except in error condition */
define('PRIVILEGE_TYPE_CORPUS_RESTRICTED',     1);
define('PRIVILEGE_TYPE_CORPUS_NORMAL',         2);
define('PRIVILEGE_TYPE_CORPUS_FULL',           3);
/* note that the above 4 definitions create a greater-than/less-than sequence. Intentionally so. */

define('PRIVILEGE_TYPE_FREQLIST_CREATE',       4);
define('PRIVILEGE_TYPE_UPLOAD_FILE',           5);

// TODO not properly sorted yet, still thinking about.......
// just one "DISK SPACE" to account for how much cache the user can use, which then applies to saved databases as well as saved queries?
// YES, but let it have scope over different cache types!!
// define('PRIVILEGE_TYPE_DISK_FOR_SAVEDATA',     6);

define('PRIVILEGE_TYPE_CQP_BINARY_FILE',       7);
define('PRIVILEGE_TYPE_EXTRA_RUNTIME',         8);

define('PRIVILEGE_TYPE_DISK_FOR_UPLOADS',      9);
define('PRIVILEGE_TYPE_INSTALL_CORPUS',        10);

//TODO not properly sorted yet
define('PRIVILEGE_TYPE_DISK_FOR_CORPUS',       11);


/*
 * corpus installer progress-status consts.
 */

define('INSTALLER_STATUS_UNKNOWN',             0);
define('INSTALLER_STATUS_WAITING',             1);
define('INSTALLER_STATUS_TAGGING',             2);
define('INSTALLER_STATUS_ENCODING',            3);
define('INSTALLER_STATUS_INDEXING',            4);
define('INSTALLER_STATUS_SETUP',               5);
define('INSTALLER_STATUS_FREQLIST',            6);
define('INSTALLER_STATUS_DONE',                7);
define('INSTALLER_STATUS_ABORTED',             8);


/* 
 * query-record save-status indicators 
 */

/** Signifies that a recorded query exists in cache but has not been saved. */
define('CACHE_STATUS_UNSAVED',                 0);
/** Signifies that a recorded query has been saved by a user. */
define('CACHE_STATUS_SAVED_BY_USER',           1);
/** Signifies that a recorded query has been saved as a "categorised" query. */
define('CACHE_STATUS_CATEGORISED',             2);


/*
 * handle-length constants
 */

define('HANDLE_MAX_CORPUS',                    20);
define('HANDLE_MAX_ANNOTATION',                20);
define('HANDLE_MAX_XML',                       64);

define('HANDLE_MAX_USERNAME',                  64);

define('HANDLE_MAX_FIELD',                     64);
define('HANDLE_MAX_CATEGORY',                  200);

define('HANDLE_MAX_SAVENAME',                  200);

define('HANDLE_MAX_ITEM_ID',                   255);



/*
 * CEQL-related
 */

 /* symbolic constants allowing the CQPweb terminology to be used rather than the BNCweb string constants
  * that CEQL uses internally. Note, only functions that CALL the parser use these. Not the parser itself. */
define('CEQL_PRIMARY_ANNOTATION'          , 'pos_attribute'       );
define('CEQL_SECONDARY_ANNOTATION'        , 'lemma_attribute'     );
define('CEQL_TERTIARY_ANNOTATION'         , 'simple_pos_attribute');
define('CEQL_TERTIARY_MAPPING TABLE'      , 'simple_pos'          );
define('CEQL_COMBO_ANNOTATION'            , 'combo_attribute'     );

// TODO, should these be here or in query-lib? or as class constants of CeqlParser??? Or of CeqlPArserForCQPweb????


/*
 * misc constants
 */



define('ARCHIVE_TYPE_UNKNOWN',                 0);
define('ARCHIVE_TYPE_TAR_GZ',                  1);
define('ARCHIVE_TYPE_TAR_BZ2',                 2);
define('ARCHIVE_TYPE_ZIP',                     3);
// longterm TODO maybe xz? rar ? Others?


/** The common date-time format string used around CQPweb. */
define('CQPWEB_UI_DATE_FORMAT', 'Y-M-d H:i');

/** Regular expression used in multiple places to interpret CQP concordance.
 *  Used with PREG_PATTERN_ORDER, it puts tokens in $m[4]; xml-tags-before in $m[1]; xml-tags-after in $m[5] */
define('CQP_INTERFACE_WORD_REGEX', '|((<\S+?( [^>]*?)?>)*)([^ <]+)((</\S+?>)*) ?|');
/* note, this is prone to interference from literal < in the index.  Also note that we need to allow for empty strings as annotations, hence [^>]* rather than [^>]+
 * TODO: 
 * Will be fixable when we have XML concordance output in CQP v 4.0 */

/** Regular expression used in multiple places to split apart words/annotations
 *  in CQP concordance format. First match = the word; second match = the annotation. */
define('CQP_INTERFACE_EXTRACT_TAG_REGEX', '/\A(.*)\/([^\/]*)\z/');

/** Magic number: string that should match against the first four bytes of a CQP query file. 
 *  NB. This is not the "original", pre-1995 format, but the "plus one" format introduced in 1995. */
define('CQP_INTERFACE_FILE_MAGIC_NUMBER', "\x89\x46\x28\x02");


/** Regular expression FRAGMENT (in capturing brackets) that catches a corpus (or annotation, or xml) handle.
 *  Currently this is just c words; in future we'll aim to allow - as well. */ 
define('CQPWEB_HANDLE_BYTE_REGEX', '[A-Za-z0-9_]');
define('CQPWEB_HANDLE_STRING_REGEX','([A-Za-z0-9_]+)');
//TODO, this constant isn't yet used everywhere it should be.





/* --------------------- *
 * Global object classes *
 * --------------------- */




/**
 * Class of which each run of CQPweb should only ever have ONE - it holds config settings as public variables
 * (sometimes hierarchically using other objects).
 * 
 * The instantiation should always be the global $Config object.
 * 
 * This class's constructor loads all the config settings from file.
 * 
 * Config settings in the database are NOT loaded by the constructor.
 */
class CQPwebEnvConfig
{
	/**
	 * The $instance_name is the unique identifier of the present run of a given script 
	 * which will be used as the name of any queries/records saved by the present script.
	 * 
	 * It was formerly the username plus the unix time, but this raised the possibility of
	 * one user seeing another's username linked to a cached query. So now it's the PHP uniqid(),
	 * which is a hexadecimal version of the Unix time in microseconds. This shouldn't be 
	 * possible to duplicate unless (a) we're on a computer fast enough to call uniqid() twice
	 * in two different processes in the same microsecond AND (b) two users do happen to hit 
	 * the server in the same microsecond. Unlikely, but id codes based on the $instance_name
	 * should still be checked for uniqueness before being used in any situation where the 
	 * uniqueness matters (e.g. as a database primary key).
	 * 
	 * For compactness, we express as base-36. Total length = 10 chars (for the foreseeable future!).
	 */
	public $instance_name;
	
	/** The run location of this instance of CQPweb. */
	public $run_location;
	
	/** 
	 * Object containing as members the config values for different directory paths;
	 * vars are : cache, upload, index, registry.
	 */ 
	public $dir;
	
	/**
	 * Hash of keys to connection objects (mysqli/CQP).
	 * For people still running old PHP, former might be a mysql resource. 
	 */
	private $slave_process_links = ['sql'=>NULL, 'cqp'=>NULL];
	
	
	
	/** Object for use in API mode. Only instance of ApiController. */
	public $Api = false;
	
	
	/**
	 * CQPweb sometimes disconnects the client. If so, this variable must be
	 * set false so that the rest of the system knows not to echo stuff.
	 */
	public $client_is_disconnected = false;
	
	
	/** The path to the CSS file to use. Can be set by other objects. */
	public $css_path;
	

	/* the constructor function creates other members dynamically */
	
	
	public function __construct($run_location)
	{
		/* set up the instance name; add a random letter for additional uniqueness */
		$this->instance_name = base_convert(uniqid(), 16, 36) . chr(random_int(0x61,0x7a));
		/* why not a uuid? because the max uuid (128 bit number) is f5lxx1zz5qo8c0o44occ0skog in base 36. IE, 25 chars. */
		
		/* import config variables from the global state of the config file */
		require('../lib/config.inc.php');
		require('../lib/defaults.php');
		
		/* transfer imported variables to object members */
		$variables = get_defined_vars();
		/* these MAY be defined... if so, we no want! */
		unset($variables['GLOBALS'], $variables['this']);

		/* superglobals all start in '_', which CQPweb config vars DON'T. */
		foreach ($variables as $k => $v)
			if ('_' != $k[0])
				$this->$k = $v;
		/* this also creates run_location as a member.... */
		
		/* check compulsory config variables */
		$compulsory_config_variables = array(
				'superuser_username',
				'mysql_webuser',
				'mysql_webpass',
				'mysql_schema',
				'mysql_server',
				'cqpweb_tempdir',
				'cqpweb_uploaddir',
				'cwb_datadir',
				'cwb_registry'
			);
		foreach ($compulsory_config_variables as $which)
			if (!isset($this->$which))
				exiterror("CRITICAL ERROR: \$$which has not been set in the configuration file.");


		/* and now, let's organise the directory variables into something saner */
		$this->dir = new stdClass;
		$this->dir->cache = $this->cqpweb_tempdir;
		$this->dir->upload = $this->cqpweb_uploaddir;
		$this->dir->index = $this->cwb_datadir;
		$this->dir->registry = $this->cwb_registry;
		unset($this->cqpweb_tempdir, $this->cqpweb_uploaddir, $this->cwb_datadir, $this->cwb_registry);
		
		/* CSS action based on run_location */
		switch ($this->run_location)
		{
		case RUN_LOCATION_MAINHOME:     $this->css_path = $this->css_path_for_homepage;     break;
		case RUN_LOCATION_ADM:          $this->css_path = $this->css_path_for_adminpage;    break;
		case RUN_LOCATION_USR:          $this->css_path = $this->css_path_for_userpage;     break;
		case RUN_LOCATION_RSS:          /* no CSS path needed */                            break;
		case RUN_LOCATION_CLI:          /* no CSS path needed */                            break;
		/* 
		 * tacit default: RUN_LOCATION_CORPUS/RUN_LOCATION_USERCORPUS, where the $Corpus object
		 * takes responsibility for setting $Config->css_path appropriately. 
		 */
		}
		
		
		/* debug messages should be textonly ALWAYS in CLI, regardless of the setting in the config file. */
		if (PHP_SAPI == 'cli')
			$this->debug_messages_textonly = true;
		
		/* add further system config here. */
	}
	
	
	
	
	
	
	/* Getter methods: only for variables more complex than a straightforward read. */
	
	/**
	 * Returns an integer containing the RAM limit to be passed to CWB programs that
	 * allow a RAM limit to be set - note, the flag (-M or whatever) is not returned,
	 * just the number of megabytes as an integer.
	 */
	public function get_cwb_memory_limit()
	{
		return ( ('cli' == php_sapi_name()) ? $this->cwb_max_ram_usage_cli : $this->cwb_max_ram_usage );
	}
	
	/* functions for the internal slave processes (which will replace globals. ) */
		
	/**
	 * This is behind the new get_global functions.
	 * 
	 * @param  string      $which  'sql' or 'cqp'. Any other argument string will cause an exiterror.
	 * @return CQP|mysqli          Depending on $which.
	 */
	public function get_slave_link($which)
	{
		static $time_sql_link_last_requested = 0;
		
		if ('sql' != $which && 'cqp' != $which)
			exiterror("Bad slacve function handle  $which!!");
		
		if ('sql' == $which)
		{
			$t = time();
			if ($time_sql_link_last_requested && 60 < (time() - $time_sql_link_last_requested))
			{
				if (!mysqli_ping($this->slave_process_links['sql']))
				{
					/* close lost connection and null over object so it will be connected. */
					@mysqli_close($this->slave_process_links['sql']);
					$this->slave_process_links['sql'] = NULL;
				}
			}
		}
		
		if (!is_object($this->slave_process_links[$which]))
			$this->connect_slave_program($which);
		
		if ('sql' == $which)
			$time_sql_link_last_requested = $t;
		
		return $this->slave_process_links[$which];
	}

	
	private function connect_slave_program($which)
	{
		global $Corpus;
		
		if (!empty($this->slave_process_links[$which]))
			return;
		switch ($which)
		{
		case 'sql':
			$this->slave_process_links['sql'] = create_sql_link();
			break;
			
		case 'cqp': 
			$slave_arg = (
				(RUN_LOCATION_CORPUS == $this->run_location || RUN_LOCATION_USERCORPUS == $this->run_location)
				&&
				$Corpus && $Corpus->specified
				)
				? $Corpus->cqp_name
				: NULL;
			$this->slave_process_links['cqp'] = create_slave_cqp($slave_arg);
			break;
			
		default:
			exiterror("Bad slave function handle  $which!!");
		}
	}
	
	
	public function disconnect_slave_program($which)
	{
		if (!is_object($this->slave_process_links[$which]))
			return;
		switch ($which)
		{
		case 'sql':
			mysqli_close($this->slave_process_links['sql']);
			break;
		case 'cqp':
			$this->slave_process_links['cqp']->disconnect();
			break;
		default: 
			return;
		}
		$this->slave_process_links[$which] = NULL;
	}
	
}


/**
 * Class of which each run of CQPweb should only ever have ONE - it represents the logged in user.
 * 
 * The instantiation should always be the global $User object.
 * 
 */
class CQPwebEnvUser 
{
	/** Is there a logged in user? (bool) */
	public $logged_in;
	
	/** full array of privileges (db objects) available to this user (individually or via group) */
	public $privileges;
	
	/** This user's username. */
	public $username;

	public function __construct($check_for_logged_in_user = true)
	{
		global $Config;

		/* 
		 * Now, let us get the username ... 
		 */
		
		/* if this environment is in a CLI script, count us as being logged in as the first admin user */ 
		if (PHP_SAPI == 'cli')
		{
			list($username) = list_superusers();
			$this->logged_in = true; 
		}
		else
		{
			/* look for logged on user */
			if (!$check_for_logged_in_user  || !isset($_COOKIE[$Config->cqpweb_cookie_name]) || false === ($checked = check_user_cookie_token($_COOKIE[$Config->cqpweb_cookie_name])))
			{
				/* no one is logged in */
				$username = '__unknown_user'; // TODO maybe change this
				$this->logged_in = false;
			}
			else
			{
				$this->logged_in = true;
				$username = $checked->username;
				
				/* if the cookie token is more than half an hour old, delete and emit a new one; otherwise, touch the existing one. 
				 * (so the token should only get used within a single session, or for the first connection of a subsequent session.) */
				if (time() - 1800 > $checked->creation)
					emit_new_cookie_token($username, 
							(isset($_COOKIE[$Config->cqpweb_cookie_name . 'Persist']) && '1' === $_COOKIE[$Config->cqpweb_cookie_name . 'Persist'])
						);
				else
				{
					touch_cookie_token($_COOKIE[$Config->cqpweb_cookie_name]);
					/* cookie tokens which don't get touched will eventually get old enough to be deleted */
				}
			}
		}

		/* now we know whether we are logged in and if so, who we are, set up the user information */
		if ($this->logged_in)
		{
			/* Update the last-seen date (on every hit from user's browser!) */
			touch_user($username);
			
			/* import database fields as object members. */
			foreach ( ((array)get_user_info($username)) as $k => $v)
			{
				/* currently, we only re-type bools. */
				switch($k)
				{
				/* BOOL */
				case 'use_tooltips':
				case 'conc_kwicview':
				case 'conc_corpus_order':
				case 'cqp_syntax':
				case 'context_with_tags':
				case 'css_monochrome':
				case 'thin_default_reproducible':
					$this->$k = (bool) $v;
					break;
				default:
					$this->$k = $v;
					break;
				}
			}
			/* will also import $username --> $User->username which is canonical way to acces it. */
			
			/* look for a full list of privileges that this user has. */
			$this->privileges = get_collected_user_privileges($username);
			
			/* so we can now apply privileges that require some sort of setup action. */
			php_execute_time_add($this->max_extra_runtime());
		}
		else
			$this->privileges =  array();
		
		/* one more thing ... */
		if (!$this->is_admin())
			$Config->print_debug_messages = false;
		/* only admin users see debug messages / squawks */
	}
	
	/**
	 * Is the currently logged in user an administrator (aka superuser)?
	 * @return bool
	 */
	public function is_admin()
	{
		return ( PHP_SAPI=='cli' || ($this->logged_in && user_is_superuser($this->username)) );
	}
	
	/**
	 * Is the currently logged in user an owner of the given or current corpus (or, a superuser)?
	 * 
	 * @param  string  $corpus_name    If given, this corpus, instead of the active one, will be checked.
	 * @return bool                    True if the current user is an admin or the owner of the corpus. 
	 */	
	public function is_admin_or_owner($corpus_name = NULL)
	{
		global $Corpus;
		
		if (!$this->logged_in)
			return false;
		
		if ($this->is_admin())
			return true;
		
		if (is_null($corpus_name))
		{
			if ($Corpus->specified)
				return ($Corpus->owner == $this->username);
			else 
				return false;
		}
		else 
		{
			if (! ($c_info = get_corpus_info($corpus_name)))
				return false;
			return ($c_info->owner == $this->username);
		}
	}
	
	
	/**
	 * Checks whether this user has binary-file privilege.
	 * @return boolean
	 */
	public function has_cqp_binary_privilege()
	{
		if ($this->is_admin())
			return true;
		foreach ($this->privileges as $p)
			if (PRIVILEGE_TYPE_CQP_BINARY_FILE == $p->type)
				return true;
		return false;
	}
	
	/** 
	 * Checks whether this user is over their limit, disk-space-wise.
	 * @return bool 
	 */
	public function has_exceeded_user_corpus_disk_limit()
	{
		return 1 > ( 
				max_value_of_integer_scoped_privilege($this->username, PRIVILEGE_TYPE_DISK_FOR_CORPUS) 
				- 
				sum_user_corpus_disk_usage($this->username) 
				);
	}
	
	/**
	 * Returns the size, in tokens, of the largest sub-corpus for which this user
	 * allowed to create frequency lists.
	 * @return int
	 */
	public function max_freqlist()
	{
		return max_value_of_integer_scoped_privilege($this->username, PRIVILEGE_TYPE_FREQLIST_CREATE);
	}
	
	/**
	 * Returns the size, in bytes, of the biggest file this user is allowed to upload into CQPweb space.
	 * @return int
	 */
	public function max_upload_file()
	{
		return max_value_of_integer_scoped_privilege($this->username, PRIVILEGE_TYPE_UPLOAD_FILE);
	}
	
	/**
	 * Returns the limit, in bytes, of the amount of space in the CQPweb upload area this user is allowed to use.
	 * @return int
	 */
	public function max_upload_disk_space()
	{
		return max_value_of_integer_scoped_privilege($this->username, PRIVILEGE_TYPE_DISK_FOR_UPLOADS);
	}
	
	/**
	 * Returns the limit, in bytes, of the amount of space this user is allowed to use for user-installed corpora.
	 * @return int
	 */
	public function max_user_corpus_disk_space()
	{
		return max_value_of_integer_scoped_privilege($this->username, PRIVILEGE_TYPE_DISK_FOR_CORPUS);
	}
	
	
	/**
	 * Returns the number of "extra seconds" of execution time this user is allowed.
	 * @return int
	 */
	public function max_extra_runtime()
	{
		if (!$this->logged_in)
			return 0;
		return max_value_of_integer_scoped_privilege($this->username, PRIVILEGE_TYPE_EXTRA_RUNTIME);
	}

	/**
	 * Returns the right EOL to use in text downloads for this user. 
	 * @return string
	 */
	public function eol()
	{
		if (is_null($this->linefeed) || 'au' == $this->linefeed)
			$eol = guess_user_linefeed($this->username);
		else
			$eol = $this->linefeed;
		
		return strtr($eol, "da", "\r\n");
	}
}





/**
 * Class of which each run of CQPweb should only ever have ONE - it represents the environment-corpus
 * of the currently-running instance (i.e. the corpus that we "are in", if there is one.
 * 
 * The instantiation should always be the global $Corpus object.
 *
 */
class CQPwebEnvCorpus 
{
	/*
	 * CORPUS ACCESS INFORMATION
	 * =========================
	 */
	
	/* constants that flag the reason the corpus data cannot be accessed by the current user */
	
	const NOACCESS_BUT_ACTUALLY_THERE_IS   = 0;
	const NOACCESS_USERCORPUS_SWITCHED_OFF = 1;
	const NOACCESS_USERCORPUS_NOT_OWNED    = 2;
	const NOACCESS_USERCORPUS_NO_GRANT     = 3;
	const NOACCESS_USERCORPUS_DISKBLOCKED  = 4;
	const NOACCESS_SYSTEMCORPUS_NO_PRIV    = 5;
	const NOACCESS_SYSTEMCORPUS_LOGGED_OUT = 6;
	
	/** This is set to a privilege constant to indicate what level of privilege the currently-logged-on user has. */
	public $access_level;
	
	/** contains a class constant indicating why the user can't access this corpus */
	public $no_access_reason_why = self::NOACCESS_BUT_ACTUALLY_THERE_IS;

	
	
	/** are we running within a particular corpus ? */
	public $specified = false;
	
	
	/*
	 * Fields from the corpus_info database table
	 * ==========================================
	 */

	//TODO find out which of these need actually to be here.
	
	/** the integer ID of this corpus */
	public $id;
	
	/** This is the way to get the corpus handle. */
	public $name;   //TODO - change to "handle" - cxan be that in the DB too.
	
	/** The corpus title */
	public $title;
	
	
	/** the username of the owner; if empty, it's a system corpus. @see CQPwebEnvCorpus::is_user_owned() */
	public $owner;
	
	/**
	 * The parameter allows the environment-corpus to be directly specified.
	 * If it is not given, then it will be deduced (a) on the CLI, from the working directory.
	 * (b) on the Web, from the SCRIPT_NAME value in $_SERVER (i.e. indirectly from the URL).
	 */
	public function __construct($parameter_corpus = NULL)
	{
		/* first, check whether we are actually in the run location required... 
		 * and leave $Corpus as just an empty object if we're not. */
		global $Config;
		if (RUN_LOCATION_CORPUS != $Config->run_location)
			return;

		/* first: try to identify the corpus. 
		 * Order of checks is: (1) constructor parameter; (2) $_GET["c"]; (3) guess from the $_SERVER */
		if (!is_null($parameter_corpus))
		{
			$this->name = $parameter_corpus;
			unset($parameter_corpus);
		}
		else if (!empty($_GET['c']))
		{
			/* this is currently only used by the "offline freqlists" script */
			$this->name = cqpweb_handle_enforce($_GET['c']);
			//TODO get rid of.
		}
		else 
		{
			if ('cli' == PHP_SAPI)
			{
				/* what corpus are we in? --> last element of the Unix environment variable PWD */
				$junk = explode('/', str_replace('\\', '/', rtrim($_SERVER['PWD'], '/')));
				$this->name = end($junk);
				unset($junk);
				/* note, this will not work if "chdir" has been used before environment setup,
				 * because the PWD is the working directory AT STARTUP.
				 * On the other hand, we cannot use getcwd() as it will (on Linux at least) resolve symlinks! 
				 * Whether the above will work on Windows is to be discovered. */ 
// FIXME  for applicaitons, etc. the above is SUPER shonky. 
			}
			else
			{
				/* what corpus are we in? --> last element of SCRIPT_NAME before the filename. */
				if (!preg_match('|/(\w+)/[^/]+\.php$|', $_SERVER['SCRIPT_NAME'], $m))
					exit("Core critical error: could not determine what corpus we are using.\n");
				$this->name =  $m[1];
				/* getcwd() would have worked here too BUT it is apparently disabled on some servers. */
			}
			/* if we got, for instance, "adm" from this process, then $Config's run_location being CORPUS is clearly wrong. */
			if (in_array($this->name, $Config->cqpweb_reserved_subdirs))
			{
				unset($this->name);
				return;
			}
		}

		if (!empty($this->name))
			$this->specified = true;
		/* if specified is not true, then $Config->run_location will tell you where we are running from. */

		/* only go hunting for more info on the $Corpus if one is actually specified...... */
		if ($this->specified)
		{
			/* import database fields as object members. */

			$result = do_sql_query("select * from corpus_info where corpus = '{$this->name}'");
			if (mysqli_num_rows($result) < 1)
				exit("Core critical error: invalid corpus handle submitted to database.\n");

			foreach (mysqli_fetch_assoc($result) as $k => $v)
			{
				/* allows for special cases */
				switch ($k)
				{
				/* fallthrough list for do-nothing cases */
				case 'corpus':
					break;
				/* note that here in the global object, `corpus` ==> $this->name ,
				 * but in small objects for non-environment corpora, it stays as `corpus`.
				 * This is annoying and confusing, and is for historical reasons.  */

				/* fallthrough list for bools */
				case 'is_user_corpus':
				case 'cwb_external':
				case 'visible':
				case 'uses_case_sensitivity':
				case 'main_script_is_r2l':
				case 'visualise_gloss_in_concordance':
				case 'visualise_gloss_in_context':
				case 'visualise_translate_in_concordance':
				case 'visualise_translate_in_context':
				case 'visualise_position_labels':
				case 'visualise_break_context_on_punc':
					$this->$k = (bool)$v;
					break;

				/* fallthrough list for integers */
				case 'id':
				case 'corpus_cat':
				case 'initial_extended_context':
				case 'max_extended_context':
				case 'size_tokens':
				case 'size_types':
				case 'size_texts':
				case 'conc_scope':
					$this->$k = (int)$v;
					break;
				
				/* everything else is added as a string - incl. the timestamp column date_of_indexing! */
				default:
					$this->$k = $v;
					break;
				}
			}

			
			/* 
			 * variables which need sanity checks/adjustment/deducing from another setting.... 
			 * ===============================================================================
			 * 
			 * (all so far about concordance/context rendering)
			 */
			
			/* deduce default regex flags for auto-generated CQP-syntax queries */
			$this->cqp_query_default_flags = $this->uses_case_sensitivity ? '' : '%cd' ;
			/* note that this assumes that case-insensitive means accent-insenitive, which is currently 
			 * true for older versions of MySQL/MariaDB (but won't be in the future).
			 */
			$this->sql_collation = deduce_corpus_sql_collation($this);
			
			/* concordance scope deduction */
			$this->conc_scope = ($this->conc_scope < 1 ? 1 :  $this->conc_scope);
			$this->conc_scope_is_based_on_s = !empty($this->conc_s_attribute);
			
			/* sanity check for extended context width values... */
			if ($this->initial_extended_context > $this->max_extended_context)
				$this->initial_extended_context = $this->max_extended_context;			

			/* sanity check for concordance / conteaxt glossing */
			if ($this->visualise_gloss_in_concordance || $this->visualise_gloss_in_context)
				if (!isset($this->visualise_gloss_annotation))
					$this->visualise_gloss_annotation = 'word'; 
			
			/* sanity check for translation */
			if (empty($this->visualise_translate_s_att))
			{
				/* we can't default this one: we'll have to switch off these variables */
				$this->visualise_translate_in_context = false;
				$this->visualise_translate_in_concordance = false;
			}
			else
			{
				/* we override $conc_scope etc... if the translation s-att is to be used in concordance */
				if ($this->visualise_translate_in_concordance)
				{
					$this->conc_s_attribute = $this->visualise_translate_s_att;
					$this->conc_scope_is_based_on_s = true;
					$this->conc_scope = 1;
				}
			}
 

			/* some settings then transfer to $Config */
			global $Config;
			if (!empty($this->owner))
			{
				$Config->run_location = RUN_LOCATION_USERCORPUS;
				if ('..' == substr($this->css_path, 0, 2))
					$this->css_path = '../../../' . $this->css_path;
			}
			if (isset($this->css_path))
			{
				$Config->css_path = $this->css_path;
				/* We keep it here so it can be discovered if necessary. */
			}
			
			/* finally, since we are in a corpus, we need to ascertain (a) whether the user is allowed
			 * to access this corpus; (b) at what level the access is. */
			$this->ascertain_access_level();
			
			if (PRIVILEGE_TYPE_NO_PRIVILEGE == $this->access_level)
			{
				if ($Config->Api)
					$Config->Api->raise_error(API_ERR_NO_ACCESS, "You cannot access this corpus (NOACCESS code = {$this->no_access_reason_why})");
				else
					set_next_absolute_location("../usr/index.php?ui=accessDenied&corpusDenied={$this->name}&why={$this->no_access_reason_why}");
					/* redirects to a page telling them they do not have the privilege to access this corpus. */
				cqpweb_shutdown_environment();
				exit();
				/* otherwise, we know that the user has some sort of access to the corpus, and we can continue */
			}
		}
	}
	
	/**
	 * Sets up the access_level member to the privilege type indicating
	 * the HIGHEST level of access to which the currently-logged-in user
	 * is entitled for this corpus.
	 * 
	 * Assumes that the global object $User is already set up.
	 * 
	 * @return int      A PRIVILEGE_TYPE_CORPUS_ constant, indicating what
	 *                  level of access the logged-in user has. 
	 *                  This is also stored in the $access_level member.
	 *                  If the return is PRIVILEGE_TYPE_NO_PRIVILEGE,
	 *                  then the $no_access_reason_why member will be set to 
	 *                  a class constant  which indicates which of the
	 *                  various possible reasons is at fault. 
	 */ 
	private function ascertain_access_level()
	{
		global $Config;
		global $User;
		
		/* superusers have full access to everything. */
		if ($User->is_admin())
		{
			$this->access_level = PRIVILEGE_TYPE_CORPUS_FULL;
			return PRIVILEGE_TYPE_CORPUS_FULL;
		}
		
		/* otherwise we must dig through the privileges owned by this user. */
		
		/* start by assuming NO access. Then look for the highest privilege this user has. */
		$this->access_level = PRIVILEGE_TYPE_NO_PRIVILEGE;
		
		if (!$User->logged_in)
			$this->no_access_reason_why = self::NOACCESS_SYSTEMCORPUS_LOGGED_OUT;
		else
		{
			if ($this->is_user_owned())
			{
				/* user corpus */
				
				/* no one can ever get into a user corpus if the system is switched off. */
				if (!$Config->user_corpora_enabled)
					$this->no_access_reason_why = self::NOACCESS_USERCORPUS_SWITCHED_OFF;
				else
				{
					if ($this->owner == $User->username)
					{
						if ($User->has_exceeded_user_corpus_disk_limit())
							$this->no_access_reason_why = self::NOACCESS_USERCORPUS_DISKBLOCKED;
						else
							return $this->access_level = PRIVILEGE_TYPE_CORPUS_FULL;
					}
					else if ($Config->colleagate_system_enabled) 
					{
						/* check for colleaguate grant. */
						if (user_corpus_is_granted_to_user($this->id, $User->username))
							return $this->access_level = PRIVILEGE_TYPE_CORPUS_FULL;
						else
							$this->no_access_reason_why = self::NOACCESS_USERCORPUS_NO_GRANT;
					}
					else
						$this->no_access_reason_why = self::NOACCESS_USERCORPUS_NO_GRANT;
				}
			}
			else
			{
				/* system corpus */
				$this->access_level = max_user_privilege_level_for_corpus($User->username, $this->name);
				
				if (PRIVILEGE_TYPE_NO_PRIVILEGE == $this->access_level)
					$this->no_access_reason_why = self::NOACCESS_SYSTEMCORPUS_NO_PRIV;
			}
		}
		
		return $this->access_level;
	}
	
	/**
	 * Is this a user-owned corpus? 
	 * 
	 * @param  bool            $get_username   If true, owner's username is returned
	 *                                         to indicate boolean true.
	 * @return bool|string                     If not user-owned, then false. Otherwise,
	 *                                         eitehr boolean true or the owner's username.
	 */
	public function is_user_owned($get_username = false)
	{
		if (!empty($this->owner))
			return ($get_username ? $this->owner : true);
		else
			return false;
	}
	
	
	/**
	 * This allows the $Corpus object itself to be embedded
	 * as shorthand for $Corpus->name.
	 *  
	 * @return string
	 */
	public function __toString()
	{
		return $this->name;
	}
}














/* ============================== *
 * Startup and shutdown functions *
 * ============================== */




/**
 * Function that starts up CQPweb and sets up the required environment.
 * 
 * All scripts that require the environment should call this function.
 * 
 * It should be called *after* the inclusion of most functions, but
 * *before* the inclusion of admin functions (if any).
 * 
 * Ultimately, this function will be used instead of the various "setup
 * stuff" that uis currently done repeatedly, per-script.
 * 
 * Pass in bitwise-OR'd flags to control the behaviour. 
 */
function cqpweb_startup_environment($flags = CQPWEB_STARTUP_NO_FLAGS, $run_location = RUN_LOCATION_CORPUS, $specify_corpus_direct = NULL)
{
	if ($run_location == RUN_LOCATION_CLI)
		if (php_sapi_name() != 'cli')
			exit("Critical error: Cannot run CLI scripts over the web!\n");

	/* -------------- *
	 * TRANSFROM HTTP *
	 * -------------- */

	/* the very first thing we do is set up _GET, _POST etc. .... */
	
	/* WE ALWAYS USE GET! */
	
	/* sort out our incoming variables.... */
	foreach($_POST as $k => $v)
		$_GET[$k] = $v;
	/* now, we can be sure that any bits of the system that rely on $_GET being there will work. */


	/* --------------------------------- *
	 * WORKAROUNDS FOR OTHER PHP SADNESS *
	 * --------------------------------- */

	/* As of 5.4, PHP emits nasty warning messages if date.timezone is not set in the ini file.... */
	if (empty(ini_get('date.timezone')))
		ini_set('date.timezone', date_default_timezone_get());
	
	/* save RAM by not letting the opcache store Andrew's legnthy comments. */
	ini_set('opcache.save_comments', false);
	
	/* We DON'T want to abort if the user disconnects, because that could leave stuff in an inconsistent state. */
	ignore_user_abort(true);	
	
	/* write progressively to output in case of long loading time */
	ini_set('output_buffering', false);
	ob_implicit_flush(true);
	
	
	/* ----------------------- *
	 * SETUP PLUGIN AUTOLOADER *
	 * ----------------------- */

	if (function_exists('autoload_plugin_class'))
		spl_autoload_register('autoload_plugin_class');
	


	/* -------------- *
	 * GLOBAL OBJECTS *
	 * -------------- */
	
	
	/** Global object containing information on system configuration. */
	global $Config;
	/** Global object containing information on the current user account. */
	global $User;
	/** Global object containing information on the current corpus. */
	global $Corpus;

	/* create global settings options */
	$Config = new CQPwebEnvConfig($run_location);
	
	
	/* and, if we are in API mode, add theApicontroller object to Config. */
	if (isset($GLOBALS['API']))
	{
		$Config->Api = new ApiController($GLOBALS['API'], $_GET);
		unset($GLOBALS['API']);
		
		/* now, scrub GET, so that when we prepare arguments, the API can shove it full of stuff. */
		$_GET = array();
		
		/* prepare API arguments; if doing so causes an error, skip straight to shutdown. */
		if (!$Config->Api->prepare_arguments())
		{
			cqpweb_shutdown_environment();
			exit;
		}
	}
	
	
	
	
	/* check for "switched-off" status; if we are switched off, then just spit out the "switched off" message. */
	if ($Config->cqpweb_switched_off && $Config->run_location != RUN_LOCATION_CLI)
	{
		include('../lib/switched-off.php');
		exit();
	}
	/* we have to do this BEFORE connecting to anything... */


	/*
	 * Now that we have the $Config object, we can connect to MySQL.
	 * 
	 * The flags (here and below) are for "dont" because we assume 
	 * the default behaviour is to need both a DB connection and a 
	 * slave CQP process.
	 * 
	 * If one or both is not required, a flag can be passed in to 
	 * save the connection (not much of a saving in the case of the DB,
	 * potentially quite a performance boost for the slave process.)
	 */
	if ( !($flags & CQPWEB_STARTUP_DONT_CONNECT_SQL) )
		$Config->get_slave_link('sql');


	/* now the DB is connected, we can do the other two global objects. */

	$User   = new CQPwebEnvUser( !($flags & CQPWEB_STARTUP_DONT_CHECK_USER) );
	
	$Corpus = new CQPwebEnvCorpus($specify_corpus_direct);


	/* now that we have the global $Corpus object, we can connect to CQP */

	if ( !($flags & CQPWEB_STARTUP_DONT_CONNECT_CQP) )
		$Config->get_slave_link('cqp');



	/* We do the following AFTER starting up the global objects, because without it, 
	 * we don't have the CSS path for exiterror. */

	if ($flags & CQPWEB_STARTUP_CHECK_ADMIN_USER)
		if (!$User->is_admin())
			exiterror("You do not have permission to use this part of CQPweb.");

	if ($flags & CQPWEB_STARTUP_CHECK_OWNER_OR_ADMIN)
		if (!$User->is_admin_or_owner())
			exiterror("You do not have permission to use this part of CQPweb.");
}
/* end of function cqpweb_startup_environment */



/**
 * Performs shutdown and cleanup for the CQPweb system.
 * 
 * The only thing that it will not do is finish off HTML. 
 * The script should do that separately -- BEFORE calling this function.
 * 
 * All scripts should finish by calling this function.
 */
function cqpweb_shutdown_environment()
{
	global $Config;
	
	/* the shutdown actions all rely on info stored in $Config. 
	 * If we are asked to shutdown before $Config is created, 
	 * we can't really do any of them. */
	if (is_object($Config))
	{
		/* This sends off the data object to the API client */
			if ($Config->Api)
				$Config->Api->dispatch();
	
		/* these funcs have their own "if" clauses so can be called here unconditionally... */
		$Config->disconnect_slave_program('cqp');
		$Config->disconnect_slave_program('sql');
	}
}

