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
 * Class representing a CQP child process and handling 
 * all interaction with that excellent program.
 */
class CQP
{
	/*
	 * ===============
	 * CLASS CONSTANTS
	 * ===============
	 */
	
	
	
	/* the minimum version of CWB that this class requires */
	const VERSION_MAJOR_DEFAULT     = 3;
	const VERSION_MINOR_DEFAULT     = 4;
	const VERSION_REVISION_DEFAULT  = 12;
	
	
	
	/* constants for accessing "pipe" to a particular child. */
	
	/** index in $this->pipe of slave STDIN */
	const SLV_IN  = 0;
	/** index in $this->pipe of slave STDOUT */
	const SLV_OUT = 1;
	/** index in $this->pipe of slave STDERR*/
	const SLV_ERR = 2;
	
	/** max number of error lines to read from slave */
	const MAX_SLV_ERRS_STORED = 1024;
	
	
	/* data string constants */
	
	/** CQP command to get an end-of-output marker inserted into the slave output stream. */
	const END_OF_OUTPUT_COMMAND = '.EOL.'; 
	/** String for detecting end of output on slave stdout (produced from self::END_OF_OUTPUT_COMMAND). */
	const END_OF_OUTPUT_SEEK = '-::-EOL-::-'; 
	
	/** regex to parse the version out of cqp's statup prinotut. */
	const VERSION_REGEX = '/^CQP\s+(?:\w+\s+)*([0-9]+)\.([0-9]+)(?:\.(b?[0-9]+))?(?:\s+(.*))?$/';
	
	/** progress bar: regex used to detect a progressbar line. */
	const PROGRESSBAR_REGEX = '/^-::-PROGRESS-::-/';
	
	
	/* error status constants */
	
	/** indicates "status is fine" */
	const STATUS_OK    = 0;
	/** indicates "something is wrong" */
	const STATUS_ERROR = 1;
	
	
	/* constants for character encodings */
	
	/* Note, unlike the CWB internals, there is no separate value for ASCII. ASCII counts as UTF8. */ 
	const CHARSET_UTF8       =  0;
	const CHARSET_LATIN1     =  1;
	const CHARSET_LATIN2     =  2;
	const CHARSET_LATIN3     =  3;
	const CHARSET_LATIN4     =  4;
	const CHARSET_CYRILLIC   =  5;
	const CHARSET_ARABIC     =  6;
	const CHARSET_GREEK      =  7;
	const CHARSET_HEBREW     =  8;
	const CHARSET_LATIN5     =  9;
	const CHARSET_LATIN6     = 10;
	const CHARSET_LATIN7     = 13;
	const CHARSET_LATIN8     = 14;
	const CHARSET_LATIN9     = 15;
	/* the literal values are ISO-8859 part numbers, but this is only for neatness; these numbers
	 * are not actually used for their values. Note these have no link to CWB internal consts. */
	
	
	
	/* CQP option type constants. Note they do not correspond entirely to the CQP internals. */
	
	const OPT_TYPE_NONE       = 0;             /**< unknown type / type not relevant */
	const OPT_TYPE_BOOL       = 1;             /**< Boolean CQP option. */
	const OPT_TYPE_INT        = 2;             /**< Integer CQP option. */
	const OPT_TYPE_STRING     = 3;             /**< Arbitrary string CQP option. */
	const OPT_TYPE_ENUM       = 4;             /**< CQP option which must be one of a limited set of options. */
	const OPT_TYPE_CONTEXT    = 5;             /**< CQP option which has a comple internal structure, 
	                                             *  but which is treated by this object as just having string status.  */
	
	
	
	/* 
	 * =================================================
	 * MEMBER VARIABLES FOR THE SLAVE PROCESS CONNECTION 
	 * =================================================
	 */
	
	/** Boolean: used to avoid multiple "shutdown" attempts. */
	private $has_been_disconnected = false;
	
	/** handle for the process */
	private $process;

	/** array for the input/output handles themselves to go in */
	private $pipe;
	
	/** Store the last string used to set the registry. */
	private $last_registry_invoked;
	
	
	/* 
	 * ===================================
	 * MEMBER VARIABLES FOR CQP VERSIONING 
	 * ===================================
	 */
	
	
	/* version numbers for the version of CQP we actually connected to */
	public $major_version;
	public $minor_version;
	public $revision_version;
	
	/** indicates whether the revision version number was flagged by a "b" */
	private $revision_version_flagged_beta; 
	
	/** stores compile date reported by cqp -v */
	public $compile_date;
	
	/* plus the original string from which the above was captured */
	public $version_string;
	
	
	/* 
	 * ============================
	 * VARIABLES FOR ERROR HANDLING
	 * ============================
	 */
	
	
	/** status of last executed command (STATUS_OK or STATUS_ERROR) */
	private $status = self::STATUS_OK;
	
	/** set to "callable" of user-defined error handler, or to false if there isn't one */
	private $error_handler = false;
	
	/** array containing string(s) produced by last error */
	private $error_message = array();
	
	
	/* 
	 * ==============================
	 * MISCELLANEOUS MEMBER VARIABLES
	 * ==============================
	 */
	
	
	/** for convenience: we store the system EOL as an embeddable variable once, globally */
	private $EOL = PHP_EOL;
	/* this makes sure output is linebreak-sane locally, as well as via browser */
	
	// TODO make sure the above is used throughout the file. 
	
	
	/** progress bar handling: set progress_handler to "callable" of user-defined progressbar,
	 *  or to false if there isn't one */
	private $progress_handler = false;
	
	/** this class uses gzip, so the path must be set */
	private $gzip_path = '';
	
	/** Boolean: is debug mode switched on? */
	private $debug_mode = false;
	
	/** Boolean: is there at least one output line to be read in unbuffered mode?
	 *  (or to put it another way, has CQP::raw_read() returned false at least once 
	 *  since the last CQP::raw_execute() ? )  
	 *  (this is only set to true when CQP::raw_execute() is used.) */
	private $unbuffered_output_pending = false;
	
	/** Boolean: controls PrettyPrint suspension. */
	private $pretty_suspended = false;
	
	
	
	/* 
	 * ================================
	 * CHARACTER SET HANDLING VARIABLES 
	 * ================================
	 */
	
	/** stores the current corpus character set, for use in filtering. */
	private $corpus_charset = self::CHARSET_UTF8;    /* utf8 is the default charset, can be overridden when corpus is set. */
	
	
	/** array mapping the CHARSET constants to strings for iconv() */
	private static $charset_labels_iconv = array(
		self::CHARSET_UTF8			=> 'UTF-8',
		self::CHARSET_LATIN1		=> 'ISO-8859-1',
		self::CHARSET_LATIN2		=> 'ISO-8859-2',
		self::CHARSET_LATIN3 		=> 'ISO-8859-3',
		self::CHARSET_LATIN4 		=> 'ISO-8859-4',
		self::CHARSET_CYRILLIC		=> 'ISO-8859-5',
		self::CHARSET_ARABIC 		=> 'ISO-8859-6',
		self::CHARSET_GREEK 		=> 'ISO-8859-7',
		self::CHARSET_HEBREW 		=> 'ISO-8859-8',
		self::CHARSET_LATIN5 		=> 'ISO-8859-9',
		self::CHARSET_LATIN6 		=> 'ISO-8859-10',
		self::CHARSET_LATIN7 		=> 'ISO-8859-13',
		self::CHARSET_LATIN8 		=> 'ISO-8859-14',
		self::CHARSET_LATIN9 		=> 'ISO-8859-15'
		);
	
	/** array mapping the CHARSET constants to strings in the cwb-style */
	private static $charset_labels_cwb = array(
		self::CHARSET_UTF8			=> 'utf8',
		self::CHARSET_LATIN1		=> 'latin1',
		self::CHARSET_LATIN2		=> 'latin2',
		self::CHARSET_LATIN3		=> 'latin3',
		self::CHARSET_LATIN4 		=> 'latin4',
		self::CHARSET_CYRILLIC		=> 'cyrillic',
		self::CHARSET_ARABIC		=> 'arabic',
		self::CHARSET_GREEK			=> 'greek',
		self::CHARSET_HEBREW		=> 'hebrew',
		self::CHARSET_LATIN5		=> 'latin5',
		self::CHARSET_LATIN6		=> 'latin6',
		self::CHARSET_LATIN7		=> 'latin7',
		self::CHARSET_LATIN8		=> 'latin8',
		self::CHARSET_LATIN9		=> 'latin9'
		);
	
	/** 
	 * array for interpreting CWB or (selected, lowercased) 
	 * iconv identifier strings into CQP class constants;
	 * as usual, ASCII counts as UTF-8 
	 */
	private static $charset_interpreter = array (
		'ascii'       => self::CHARSET_UTF8,
		'us-ascii'    => self::CHARSET_UTF8,
		'utf8'        => self::CHARSET_UTF8,
		'utf-8'       => self::CHARSET_UTF8,
		'latin1'      => self::CHARSET_LATIN1,
		'iso-8859-1'  => self::CHARSET_LATIN1,
		'latin2'      => self::CHARSET_LATIN2,
		'iso-8859-2'  => self::CHARSET_LATIN2,
		'latin3'      => self::CHARSET_LATIN3,
		'iso-8859-3'  => self::CHARSET_LATIN3,
		'latin4'      => self::CHARSET_LATIN4,
		'iso-8859-4'  => self::CHARSET_LATIN4,
		'cyrillic'    => self::CHARSET_CYRILLIC,
		'iso-8859-5'  => self::CHARSET_CYRILLIC,
		'arabic'      => self::CHARSET_ARABIC,
		'iso-8859-6'  => self::CHARSET_ARABIC,
		'greek'       => self::CHARSET_GREEK,
		'iso-8859-7'  => self::CHARSET_GREEK,
		'hebrew'      => self::CHARSET_HEBREW,
		'iso-8859-8'  => self::CHARSET_HEBREW,
		'latin5'      => self::CHARSET_LATIN5,
		'iso-8859-9'  => self::CHARSET_LATIN5,
		'latin6'      => self::CHARSET_LATIN6,
		'iso-8859-10' => self::CHARSET_LATIN6,
		'latin7'      => self::CHARSET_LATIN7,
		'iso-8859-13' => self::CHARSET_LATIN7,
		'latin8'      => self::CHARSET_LATIN8,
		'iso-8859-14' => self::CHARSET_LATIN8,
		'latin9'      => self::CHARSET_LATIN9,
		'iso-8859-15' => self::CHARSET_LATIN9
		);
	
	
	
	
	/** Maps the names of CQP settings to their datatypes. */
	private static $option_type = array(
		'AutoSave'             => self::OPT_TYPE_BOOL ,
		'AutoShow'             => self::OPT_TYPE_BOOL ,
		'AutoSubquery'         => self::OPT_TYPE_BOOL ,
		'Colour'               => self::OPT_TYPE_BOOL ,
		'ExternalSort'         => self::OPT_TYPE_BOOL ,
		'Highlighting'         => self::OPT_TYPE_BOOL ,
		'Optimize'             => self::OPT_TYPE_BOOL ,
		'Paging'               => self::OPT_TYPE_BOOL ,
		'PrettyPrint'          => self::OPT_TYPE_BOOL ,
		'ProgressBar'          => self::OPT_TYPE_BOOL ,
		'SaveOnExit'           => self::OPT_TYPE_BOOL ,
		'ShowTagAttributes'    => self::OPT_TYPE_BOOL ,
		'ShowTargets'          => self::OPT_TYPE_BOOL ,
		'StrictRegions'        => self::OPT_TYPE_BOOL ,
		'Timing'               => self::OPT_TYPE_BOOL ,
		'WriteHistory'         => self::OPT_TYPE_BOOL ,
		
		'AttributeSeparator'   => self::OPT_TYPE_STRING ,
		'DataDirectory'        => self::OPT_TYPE_STRING ,
		'DefaultNonbrackAttr'  => self::OPT_TYPE_STRING ,
		'ExternalSortCommand'  => self::OPT_TYPE_STRING ,
		'HistoryFile'          => self::OPT_TYPE_STRING ,
		'LeftKWICDelim'        => self::OPT_TYPE_STRING ,
		'Pager'                => self::OPT_TYPE_STRING ,
		'PrintStructures'      => self::OPT_TYPE_STRING ,
		'Registry'             => self::OPT_TYPE_STRING ,
		'RightKWICDelim'       => self::OPT_TYPE_STRING ,
		
		'MatchingStrategy'     => self::OPT_TYPE_ENUM ,
		'PrintMode'            => self::OPT_TYPE_ENUM ,
		'PrintOptions'         => self::OPT_TYPE_ENUM ,
		
		'Context'              => self::OPT_TYPE_CONTEXT ,
		'LeftContext'          => self::OPT_TYPE_CONTEXT ,
		'RightContext'         => self::OPT_TYPE_CONTEXT ,
		);
	
	private static $option_enum_regex= array(
		'MatchingStrategy'     => '/^(traditional|shortest|standard|longest)$/',
		'PrintMode'            => '/^(ascii|sgml|html|latex)$/',
		'PrintOptions'         => '/^(no)?(wrap|table|header|border|number)' ,
		);
	
	private static $print_option_abbr_map = array(
		'wrap'   => 'wrap',
		'table'  => 'tbl',
		'header' => 'hdr',
		'border' => 'bdr',
		'number' => 'num',
		'tbl' => 'tbl',
		'hdr' => 'hdr',
		'bdr' => 'bdr',
		'num' => 'num',
		); 
	
	
	/* 
	 * =======
	 * METHODS 
	 * =======
	 */
	
	
	/**
	 * Create a new CQP object.
	 * 
	 * Note that both parameters can be either absolute or relative paths.
	 * 
	 * This function calls exit() if the backend startup is unsuccessful.
	 * 
	 * @param string $path_to_cqp    Directory containing the cqp executable
	 * @param string $cwb_registry   Path to place to look for corpus registry files
	 */
	public function __construct($path_to_cqp, $cwb_registry)
	{
		/* check arguments */
		$call_cqp = 'cqp';
		if (!empty($path_to_cqp))
		{
			$call_cqp = rtrim($path_to_cqp, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $call_cqp; 
			if (!is_executable($call_cqp))
				exit("ERROR: CQP binary ``$call_cqp'' does not exist or is not executable! ");
		}
		if ( !is_readable($cwb_registry) || !is_dir($cwb_registry) )
			exit("ERROR: CWB registry dir ``$cwb_registry'' seems not to exist, or is not readable! ");
		$this->last_registry_invoked = $cwb_registry;
		
		/* create handles for CQP and leave CQP running in background */
		
		/* array of settings for the three pipe-handles */
		$io_settings = array(
			self::SLV_IN  => array("pipe", "r"), /* pipe to slave stdin  (slave reads) */
			self::SLV_OUT => array("pipe", "w"), /* pipe to slave stdout (slave writes) */
			self::SLV_ERR => array("pipe", "w")  /* pipe to slave stderr (slave writes) */
			);
		
		/* start the child process */
		/* NB: currently no allowance for extra arguments */
		$command = "$call_cqp -c -r $cwb_registry";
		
		$this->process = proc_open($command, $io_settings, $this->pipe);
		
		if (! is_resource($this->process))
			exit("ERROR: CQP backend startup failed; command == ``$command''");

		/* $handle now looks like this:
		   SLV_IN  => writeable handle connected to child stdin
		   SLV_OUT => readable  handle connected to child stdout
		   SLV_ERR => readable  handle connected to child stderr
	       Now that this has been done, fwrite to $handle[SLV_IN] passes input to  
		   the program we called; and reading from $handle[SLV_OUT] accesses the   
		   output from the program.
		
		   (EG) fwrite($handle[0], 'string to be sent to CQP');
		   (EG) fread($handle[SLV_OUT]); -- returns, 'whatever CQP sent back'
		*/
		
		/* process version numbers : "cqp -c" should print version on startup */
		$this->version_string = rtrim(fgets($this->pipe[self::SLV_OUT]));
		
		if (!preg_match(self::VERSION_REGEX, $this->version_string, $m))
			exit("ERROR: CQP backend startup failed; the reported CQP version [{$this->version_string}] could not be parsed.");
		else
		{
			$this->major_version = (int)$m[1];
			$this->minor_version = (int)$m[2];
			$this->revision_version_flagged_beta = false;
			$this->revision_version = 0;
			if (isset($m[3]))
			{
				if ($m[3][0] == 'b')
				{
					$this->revision_version_flagged_beta = true;
					$this->revision_version = (int)substr($m[3], 1);
				}
				else
					$this->revision_version = (int)$m[3];
			}
			$this->compile_date  = (isset($m[4]) ? $m[4] : NULL);
			
			if ( ! $this->check_version() )
				$this->add_error("ERROR: CQP version too old (running $this->version_string). v"
					. self::default_required_version() . " or higher required.");
		}
		
		
		/* pretty-printing should be turned off for non-interactive use */
		$this->execute("set PrettyPrint off");
		/* so should the use of the progress bar; setting a handler reactivates it */
		$this->execute("set ProgressBar off");
	}
	/* end of constructor method */
	
	
	/** on object-destroy, disconnect if haven't done so already. */
	public function __destruct()
	{
		$this->disconnect();
	}
	
	/**
	 * Disconnects the child process.
	 * 
	 * Having this outside the destructor allows the process 
	 * to be switched off but the object kept alive,
	 * should you wish to do that for any reason.
	 */ 
	public function disconnect()
	{
		if ($this->has_been_disconnected)
			return;
		
		/* the PHP manual says "It is important that you close any pipes
		 * before calling proc_close in order to avoid a deadlock" --
		 * well, OK then! */
		
		if (is_resource($this->pipe[self::SLV_IN]))
			fwrite($this->pipe[self::SLV_IN], "exit" . PHP_EOL);
		
		foreach (array(self::SLV_IN, self::SLV_OUT, self::SLV_ERR) as $ix)
			if (is_resource($this->pipe[$ix]))
				fclose($this->pipe[$ix]);
		
		$this->pipe = [];
		
		/* and finally shut down the child process so script doesn't hang */
		if (isset($this->process))
			proc_close($this->process);
		
		$this->has_been_disconnected = true;
	}
	
	
	/* ------------------------ *
	 * Version handling methods *
	 * ------------------------ */
	
	
	/**
	 * Is the version of CQP (and, ergo, CWB) that was connected equal to,
	 * or greater than, a specified minimum?
	 * 
	 * Parameters: a minimum major, minor & revision version number. The latter two 
	 * default to zero; if no value for major is set, the check is made against this
	 * class's default version number (expressed in relevant class constants).
	 * 
	 * @param  int  $major     1st part of version number.
	 * @param  int  $minor     2nd part of version number.
	 * @param  int  $revision  3rd part of version number.
	 * @return bool            True if the current version (loaded in __construct) 
	 *                         is greater than the minimum.
	 */
	public function check_version($major = 0, $minor = 0, $revision = 0)
	{
		if ($major == 0)
			return $this->check_version_default();
		
		return 
			(
				$this->major_version > $major
			||
				( $this->major_version == $major && $this->minor_version > $minor )
			||
				(
					$this->major_version        == $major 
					&& $this->minor_version     == $minor
					&& $this->revision_version  >= $revision
				)
			)
		;
	}
	
	/**
	 * Is the version of CQP that we're using equal to, or greater than, the minimum needed?
	 * 
	 * @see          CQP::check_version()
	 * 
	 * @return bool  True iff the current version (loaded in __construct()) 
	 *               is greater than/equal to the default lowest adequate.
	 */
	private function check_version_default()
	{
		return $this->check_version(
			self::VERSION_MAJOR_DEFAULT,
			self::VERSION_MINOR_DEFAULT,
			self::VERSION_REVISION_DEFAULT
			);
	}

	
	




	
	/* ---------------------------------------- *
	 * Methods for controlling the CQP back-end *
	 * ---------------------------------------- */

	
	
	
	
	/**
	 * Turns off PrettyPrint temporarily (so user setting can be restored).
	 */
	private function prettyprint_suspend_on()
	{
		if ($this->pretty_suspended = $this->get_option('PrettyPrint'))
			$this->set_option('PrettyPrint', false);
	}
	
	/**
	 * Restores PrettyPrint to user setting, if need be.
	 */
	private function prettyprint_suspend_off()
	{
		if ($this->pretty_suspended)
		{
			$this->set_option('PrettyPrint', true);
			$this->pretty_suspended = false;
		}
	}
	

	
	/**
	 * Get the value of a CQP option.
	 * 
	 * @param  string $option_name    Name of the option required.
	 * @return mixed                  Current value of the variable.
	 *                                NULL is returned if the option doesn't exist.
	 */
	public function get_option($option_name)
	{
		if ('Context' == $option_name)
			return NULL;
		
		if ( false === ($out = $this->execute("set")) )
		{
			$this->add_error("Could not execute ``set''.");
			return NULL;
		}

		foreach($out as $line)
		{
			if (! (' ' == $line[0] || '[' == $line[0]))
				continue;
			if (!preg_match("/\s*(\w+)\s*=\s*(.*)$/", $line, $m))
				continue;
			list(, $name, $strval) = $m;
			if ($option_name != $name)
				continue;
			switch (self::$option_type[$name])
			{
			case self::OPT_TYPE_BOOL:
				return ('yes' == $strval);
			case self::OPT_TYPE_INT:
				return (int)$strval;
			case self::OPT_TYPE_CONTEXT:
			case self::OPT_TYPE_STRING:
				if ('<no value>' == $strval)
					return NULL;
                else if ('<default>' == $strval)
                    return '/'; /* the default value for "AttributeSeparator" is '/' */
				else 
					return $strval;
			}
		}
		return NULL;
	}
	
	/**
	 * Set a CQP option to a new value. 
	 * 
	 * If there exists a specific function (e.g. registry, data directory)
	 * prefer that one!
	 * 
	 * @param  string $option_name   Name of the option to set.
	 * @param  mixed  $value         New value.
	 * @return bool                  True iff the option was successfully set.
	 */
	public function set_option($option_name, $value)
	{
		if (!isset(self::$option_type[$option_name]))
			return false;
		
		switch (self::$option_type[$option_name])
		{
		case self::OPT_TYPE_ENUM:
			$value = strtolower(trim($value));
			if (!preg_match(self::$option_enum_regex[$option_name], $value))
				return false;
			/* value is now validated. */ 
			if (false === $this->execute("set $option_name '$value'"))
				$this->add_error("Failed to set $option_name.");
			return $this->ok();
			
		case self::OPT_TYPE_BOOL:
			if (false === $this->execute("set $option_name " . ($value ? 'on' : 'off')))
				$this->add_error("Failed to set $option_name.");
			return $this->ok();
			
		case self::OPT_TYPE_INT:
			if (false === $this->execute("set $option_name " . (int)$value))
				$this->add_error("Failed to set $option_name.");
			return $this->ok();
		
		case self::OPT_TYPE_CONTEXT:
		case self::OPT_TYPE_STRING:
			$value = addcslashes($value, '"');
			if (false === $this->execute("set $option_name \"$value\""))
				$this->add_error("Failed to set $option_name.");
			return $this->ok();
		}
	}
	
	/**
	 * Gets the current print options (boolean array, 
	 * with entries for both full and abbreviated option names.
	 * 
	 * @return array   Array of bools.
	 */
	public function get_print_options()
	{
		$array = array(
				'wrap' => true,
				'tbl'  => true,
				'hdr'  => true,
				'bdr'  => true,
				'num'  => true,
			);
		
		list($linedata) = $this->execute('set PrintOptions');
		
		foreach ($array as $k => &$v)
			if (preg_match("/-$k\b/", $linedata))
				$v = false;
		
		foreach (self::$print_option_abbr_map as $exp => $abbr)
			if ($abbr != $exp)
				$array[$exp] = $array[$abbr];
		
		return $array;
	}
	
	/**
	 * Set one or more of the PrintOptions.
	 * 
	 * @param  array  unknown $option_values    Array mapping options (hdr, bdr, etc.)
	 *                                          to their desired new Boolean values.
	 * @return bool                             True for success, false for some error.
	 */
	public function set_print_options($option_values)
	{
		foreach ($option_values as $opt => $val)
		{
			if (!isset(self::$print_option_abbr_map[$opt]))
				continue;
			$code = ($val ? '' : 'no') . self::$print_option_abbr_map[$opt];
			$this->execute("set PrintOptions \"$code\"");
			if (!$this->ok())
				break;
		}
		return $this->ok();
	}
	
	
	/**
	 * Returns the current "show" status of a given attribute. 
	 * 
	 * @param  string  $att_name  Attribute to check. 
	 * @return bool               True if "show" is switched on for this attribute.
	 *                            Returns NULL if the feature isn't supported. 
	 */
	public function get_show_attribute($att_name)
	{
		/* feature needs core 3.4.18 or higher */
		if (!$this->check_version(3,4,18))
			return NULL;
		$map = $this->get_show_attribute_map();
		return isset($map[$att_name]) ? $map[$att_name] : false;
	}
	
	
	/**
	 * Sets a new concordance "show" status for a given attribute.
	 * 
	 * @param  string  $att_name   Attribute to show/unshow.
	 * @param  bool    $value      True to show, false to unshow.
	 * @return bool                True for all OK, false for some error.
	 *                             Returns NULL if the feature isn't 
	 *                             supported. 
	 */
	public function set_show_attribute($att_name, $value)
	{
		/* feature needs core 3.4.18 or higher */
		if (!$this->check_version(3,4,18))
			return NULL;
		$map = $this->get_show_attribute_map();
		if (isset($map[$att_name]) && $value != $map[$att_name])
			$this->execute("show " . ($value ? '+' : '-'). $att_name);
		return $this->ok();
	}
	
	
	/**
	 * Get a list of the attributes whose concordance "show" 
	 * is currently "+" (i.e. switched on).
	 *  
	 * The return is an ordered list of attribute identifiers.
	 * The list contains all and only the attributes 
	 * that are currently set to print in the concordance.
	 * 
	 * The order in the array is the order the attributes
	 * will be printed in within the concordance; this order is
	 * derived from the order in which the attributes appear in 
	 * the registry file).
	 * 
	 * @param  string $type  'p', 's', or 'a'; or, any empty value to get all three types.
	 * @return array         Flat numerically indexed array of those attributes 
	 *                       which are currently "show +"; or, NULL 
	 *                       if a needed CWB feature isn't supported.
	 */
	public function list_shown_attributes($type = NULL)
	{
		/* feature needs core 3.4.18 or higher */
		if (!$this->check_version(3,4,18))
			return NULL;
		$list = [];
		foreach($this->get_show_attribute_map($type) as $att => $shown)
			if ($shown)
				$list[] = $att;
		return $list;
	}
	
	/**
	 * Gets a map showing the current "show" status of all attributes. 
	 * 
	 * @param  string $type  'p', 's', or 'a'; or, any empty value to get all three types.
	 * @return array         Map from attribute names to true (show +) or false (show -). 
	 *                       Returns NULL if the feature isn't supported. 
	 */
	public function get_show_attribute_map($target_type = NULL)
	{
		/* feature needs core 3.4.18 or higher */
		if (!$this->check_version(3,4,18))
			return NULL;
		
		$map = [];
		
		$this->prettyprint_suspend_on();
		
		foreach ($this->execute("show cd") as $line)
		{
			list($type, $attname, , $star) = explode("\t", rtrim($line, "\r\n"));
			if ($target_type == substr(0, 1,$type) || is_null($target_type))
				$map[$attname] = ('*' == $star);
		}

		$this->prettyprint_suspend_off();

		return $map;
	}
	
	
	
	/**
	 * Get an object describing the width of the left or right concordance context.
	 * 
	 * @param  string   $which  The string "left" or "right", to determine which 
	 *                          side of the context will be described by the return.
	 * @return stdClass         Object with three members: (int) extent; (string) unit;
	 *                          (bool) unit_is_s_attribute.
	 */
	public function get_context($which)
	{
		$opt = ('left' == $which) ? 'Left' : 'Right';
		$o = (object)array('extent' => 0, 'unit' => '??', 'unit_is_s_attribute' => false);
		
		preg_match('/(\d+) (\w+)/', $this->get_option("{$opt}Context"), $m);
		list(, $n, $unit) = $m;
		$o->extent = (int)$n;
		if ('words' == $unit)
			$o->unit = 'word';
		else if ('characters' == $unit)
			$o->unit = 'char';
		else
		{
			$o->unit_is_s_attribute = true;
			$o->unit = $unit;
		}
		return $o;
	}
	
	
	/**
	 * Set either the right or left concordance context width (or both at once).
	 * 
	 * @param  string       $which          Which to set: left, right, both.
	 * @param  object|array $new_context    Object or array with three components; (int) extent, 
	 *                                      (string) unit, (bool) unit_is_s_attribute.
	 * @return boolean                      True for success, false for some error.
	 *                                      In case of error, the setting won't have changed. 
	 */
	public function set_context($which, $new_context)
	{
		if (is_array($new_context))
			$new_context = (object)$new_context;
		
		$n = (int)$new_context->extent;
		
		if ($new_context->unit_is_s_attribute)
			$unit = $new_context->unit;
		else if ('char' == $new_context->unit || empty($new_context->unit) || 'chars' == $new_context->unit || 'characters' == $new_context->unit)
			$unit = '';
		else if ('word' == $new_context->unit || 'words' == $new_context->unit)
			$unit = 'words';
		else
			return false;
		
		switch (strtolower($which))
		{
		case 'both':   $prefix = '';       break;
		case 'left':   $prefix = 'Left';   break;
		case 'right':  $prefix = 'Right';  break;
		default: 
			return false;
		}
			
		$this->execute("set {$prefix}Context $n $unit");
		return $this->ok();
	}
	
	/**
	 * for symmetry
	 */
	public function get_registry() { return $this->get_option('Registry'); }
	
	/**
	 * Sets the registry directory. This must be set at startup, 
	 * but can be RE-SET to access a different set of corpora 
	 * - or to refresh the list of corpora.  
	 */
	public function set_registry($new_registry)
	{
		if (! is_readable($new_registry) || ! is_dir($new_registry) )
			$this->add_error("ERROR: CWB registry dir ``$new_registry'' seems not to exist, or is not readable! ");
		
// 		if (false === $this->execute("set Registry \"$new_registry\""))
		if (false === $this->set_option("Registry",  $new_registry))
			$this->add_error("ERROR: CQP failed to set the registry to ``$new_registry'' ");
  	
		if (!$this->ok())
			return $this->shout_error();

		/* we only change last-invoked-reg in case of success */
		$this->last_registry_invoked = $new_registry;
		
		return true;
	}

	
private $last_set_corpus;	
	/**
	 * Sets the corpus.
	 * 
	 * Note: this is the same as running "execute" on the corpus name,
	 * except that it implements a "wrapper" around the charset
	 * if necessary, allowing utf8 input to be converted to some other
	 * character set for future calls to $this->execute(). 
	 * 
	 * It is recommended to always use this function and never to set 
	 * the corpus directly via execute().
	 * 
	 * If the corpus "name" passed is empty (whitespace, zero-length, NULL)
	 * then this function does nothing. The parameter must be CAPITALS
	 * version of the corpus handle (e.g. "BROWN" not "brown".
	 * 
	 * @return bool True for all OK, false for a problem.
	 */
	public function set_corpus($corpus_cqp_name)
	{
		$corpus_cqp_name = trim($corpus_cqp_name);
		
		$result = $this->execute($corpus_cqp_name);
		
		/* TEMPORARY SUPPORT FOR OLDER CWB CORE VERSIONS  ---  this is buggy, so delete ASAP*/
		$this->last_set_corpus = $corpus_cqp_name;
		
		/* We always default-assume that a newly-set corpus is UTF8, and only override
		 * if the infoblock (which comes ultimately from the registry) says otherwise. */
		$this->corpus_charset = self::CHARSET_UTF8;
		
		foreach ($this->execute('info') as $info)
		{
			if (preg_match("/^Charset:\s+(\S+)$/", trim($info), $m))
			{
				if (isset(self::$charset_interpreter[$m[1]]))
				{
					$this->corpus_charset = self::$charset_interpreter[$m[1]];
					break;
				}
			}
		}
		
		return ( false === $result ? false : ($this->ok() ? $result : false) );
	}

	/**
	 * Gets the CQP name of the present corpus.
	 *
	 * We do this by call to CQP, rather than by remembering it internally,
	 * because a command via execute() could easily change it.
	 *
	 * If no corpus is set, the return value is NULL.
	 * Activated subcorpora are ignored.
	 */
	public function get_corpus()
	{
		if ($this->check_version(3,4,15))
		{
			$this->prettyprint_suspend_on();
			$lines = $this->execute("show active");
			$this->prettyprint_suspend_off();
			
			return empty($lines) ? NULL : trim($lines[0]);
		}
		else
		{
			/* TEMPORARY SUPPORT FOR OLDER CWB CORE VERSIONS  ---    see note above */
			/* earlier versions need roundabout way. */
			if (empty($this->last_set_corpus)) 
				return NULL ; 
			else 
				return $this->last_set_corpus;
		}
	}

	

	/**
	 * Sets the data directory and auto-restores the prior active corpus.
	 * 
	 * @param string $path   Filesystem path.
	 */
	public function set_data_directory($path)
	{
		$active_corpus = $this->get_corpus();
		$this->execute("set DataDirectory \"$path\"");
		if (!empty($active_corpus))
			$this->set_corpus($active_corpus);
	}
	
	
	public function get_data_directory()
	{
		return $this->get_option("DataDirectory");
	}

	public function refresh_data_directory()
	{
		$d = $this->get_data_directory();
		$this->set_data_directory($d);
	}
	/**
	 * Gets a list of available corpora as a numeric array.
	 * 
	 * This is the same as "executing" the 'show corpora' command,
	 * but the function sorts through the output for you and returns 
	 * the list of corpora in a nice, whitespace-free array
	 */
	public function available_corpora()
	{
		$corpora = ' ' . implode(' ', $this->execute("show corpora"));
		$corpora = str_replace('System corpora:', '', str_replace('Named Query Results:', '', $corpora));
		/* note that the 'show corpora' command ought not to display NQRs in any case. */
		$corpora = preg_replace('/\s+/',   ' ', $corpora);
		$corpora = preg_replace('/ \w: /', ' ', $corpora);
		return explode(' ', trim($corpora));
	}
	
	
	/**
	 * Refresh list of available corpora by re-scanning the last-invoked registry folder.
	 */
	public function refresh_available_corpora()
	{
		if (!empty($this->last_registry_invoked))
			$this->set_registry($this->last_registry_invoked);
	}

	

	/** 
	 * Executes a CQP command and returns an array of results (output lines from CQP),
	 * or false if an error is detected.
	 * 
	 * This function should only be used for known-safe input; 
	 * command strings from a potentially-unsafe or potentially-malevolent 
	 * source (e.g. naive user or network source) should instead be
	 * passed through query(), q.v., which uses a Query Lock
	 * to prevent execution of anything *other* than a CQP query. 
	 * 
	 * @param  string        $command       CQP command to run.
	 * @param  callable      $line_handler  Function to call on each line of output.
	 *                                      This function should not return, as its return 
	 *                                      value will be ignored.
	 * @param  mixed         $handler_data  Arbitrary data to pass to $line_handler().
	 * @return bool|array                   False for error; otherwise array of lines.
	 *                                      If a line handler was used it is expected to
	 *                                      store the results; so only true is returned.
	 */
	public function execute($command, $line_handler = NULL, $handler_data = NULL)
	{
		$mark = self::END_OF_OUTPUT_COMMAND;
		
		/* jettison anything lingering in the pipe */
		$this->raw_discard();

		if (empty($command))
		{
			$this->add_error("ERROR: CQP::execute() was called with no command.");
			$this->shout_error();
			return false;
		}
		$command = $this->filter_input($command);

		/* change any newlines, tabs etc. in command to spaces */
		$command = preg_replace('/\s+/', ' ', $command);
		/* check for ; at end and remove if there */
		$command = preg_replace('/; ?$/', '', $command);

		if ($this->debug_mode == true)
			echo "CQP << $command;{$this->EOL}";

		/* send the command to CQP's stdin */
		fwrite($this->pipe[0], "$command;{$this->EOL}$mark;{$this->EOL}");
		/* that executes the command */

		/* check for error messages */
		if ($this->check_pipe_for_error())
			return false;

		/* we know we have actual results waiting on the pipe. */
			$result = array(); 
			
		/* then, get lines one by one from child stdout */
		while ( 0 < strlen($line = fgets($this->pipe[self::SLV_OUT])) )
		{
			/* delete carriage returns from the line */
			$line = trim($line, "\n");
			$line = str_replace("\r", '', $line);

			/* special line due to ".EOL.;" marks end of output;
			   avoids having to mess around with stream_select */
			if (self::END_OF_OUTPUT_SEEK == $line)
			{
				if ($this->debug_mode == true)
					echo "CQP --------------------------------------";
				break;
			}
			
			/* if line is a progressbar line */
			if (preg_match(self::PROGRESSBAR_REGEX, $line))
			{
				$this->handle_progressbar($line);
				continue;
			}

			/* OK, so it's an ACTUAL RESULT LINE */
			if ($this->debug_mode == true)
				echo "CQP >> $line{$this->EOL}";

			if (!empty($line_handler))
				/* call the specified function */
				$line_handler($this->filter_output($line), $handler_data);
			else
				/* add the line to an array of results */
				$result[] = $line;
		}

		/* check for error messages */
		if ($this->check_pipe_for_error())
			return false;

		/* return the array of results */
		return (empty($line_handler) ? $this->filter_output($result) : true);
		/* if there was a line handler function, we don't have anything to return */
	}



	/**
	 * Like execute(), but only allows query commands, so is safer for user-supplied commands.
	 * 
	 * @param  string        $command       CQP command to run.
	 * @param  callable      $line_handler  Function to call on each line of output.
	 *                                      This function should not return, as its return 
	 *                                      value will be ignored.
	 * @param  mixed         $handler_data  Arbitrary data to pass to $line_handler().
	 * @return bool|array                   False for error; otherwise array of lines.
	 *                                      If a line handler was used it is expected to
	 *                                      store the results; so only true is returned. 
	 */
	public function query($command, $line_handler = NULL, $handler_data = NULL)
	{
		$key = self::get_random_querykey();

		$errmsg_compiled = array(); /* we compile all errors here so as not to worry about overwriting the obj array */

		if ( empty($command) )
		{
			$this->add_error("ERROR: CQP::query() was called with no command.");
			return $this->shout_error();
		}

		/* engage query lock */
		if ( false === $this->execute("set QueryLock $key"))
			$errmsg_compiled = array_merge($errmsg_compiled, $this->error_message);
		$this->clear_errors();

		/* RUN THE QUERY */
		if ( false === ($result = $this->execute($command, $line_handler, $handler_data)) )
			$errmsg_compiled = array_merge($errmsg_compiled, $this->error_message);
		$this->clear_errors();

		/* release query lock */
		if ( false === $this->execute("unlock $key") )
			$errmsg_compiled = array_merge($errmsg_compiled, $this->error_message);
		$this->clear_errors();

		if (!empty($errmsg_compiled))
		{
			/* unclear all the errors we cleared. */
			$this->error_message = $errmsg_compiled;
			$this->add_error("ERROR: CQP::query() encountered a problem.");
			return $this->shout_error();
		}

		/* this will be whatever kind of value execute() returned. */
		return $result;
	}
	
	/**
	 * Like execute, but with raw (unbuffered) access to the results.
	 * Thus, no handlers can be supplied.
	 * 
	 * @param  string $command        CQP command or commands to run.
	 * @param  bool   $wait_for_more  Default false: we'll write .EOL. afterwards.
	 *                                If true, .EOL. is not written, and more command strings are awaited.
	 * @return bool                   False iff an error was detected. 
	 * @see    CQP::raw_query()
	 * @see    CQP::raw_read()
	 * @see    CQP::raw_discard()
	 */
	public function raw_execute($command, $wait_for_more = false)
	{
		/* we DO allow empty strings. An empty string can be passed to trigger end-of-input. */
		if ('' == $command && $wait_for_more)
			return true;

		/* we DO use the filters, because they affect character encoding. */
		$command = $this->filter_input($command);

		$full_command = $command . ($wait_for_more ? '' : ';') . $this->EOL;
		
		if (!$wait_for_more)
		{
			/* this is a complete command. Dump all previous output, and add the .EOL. */
			$this->raw_discard();
			$full_command .= ".EOL.;{$this->EOL}";
		}
		/* else, we want to leave anything on SLV_OUT hanging. */

		fwrite($this->pipe[self::SLV_IN], $full_command);
		$this->unbuffered_output_pending = true;

		/* check for error messages */
		if ($this->check_pipe_for_error())
		{
			if ($wait_for_more)
				fwrite($this->pipe[self::SLV_IN], ".EOL.;{$this->EOL}");
			$this->raw_discard();
			return $this->shout_error();
		}
		return true;
	}
	
	/**
	 * Perform a query in unbuffered mode. 
	 * 
	 * @param  string $command        CQP command; only queries are allowed.
	 * @return bool                   False iff an error was detected. 
	 * @see    CQP::raw_execute()
	 */
	public function raw_query($command)
	{
		$key = self::get_random_querykey();
		
		foreach (array (
				/* engage query lock mode  */     "set QueryLock $key",
				/* do the actual query     */     $command,
				/* release query lock mode */     "unlock $key",
				) as $c)
			if (!$this->raw_execute($c))
				return false;

		/* no need to add or shout error because raw_execute() does that */
				
		$this->raw_discard();
		
		return true;		
	}
	
	/**
	 * Get a single line from the output of a command executed in unbuffered mode.
	 * 
	 * @return string|bool    An output line, trimmed of newline, or false for no lines left. 
	 */
	public function raw_read()
	{
		if (!$this->unbuffered_output_pending)
			return false;

		/* cos of the above check, we know there at least 1 line (the -EOL-) waiting in the pipe. */
		do 
		{
			$line = trim(fgets($this->pipe[self::SLV_OUT])); 
			/* fgets() will never return "false" as the stream's not closed. 
			 * It wiill just hang forever if we try to read what's not there. */
		
			if (self::END_OF_OUTPUT_SEEK == $line)
			{
				if ($this->debug_mode)
					echo "CQP --------------------------------------", PHP_EOL;
				return ($this->unbuffered_output_pending = false);
			}

			else if (preg_match('/^-::-PROGRESS-::-/', $line))
				continue;
				/* progress bars are just skipped in raw mode. */
			
			else 
			{
				if ($this->debug_mode)
					echo "CQP >> $line", PHP_EOL;
				return $this->filter_output($line);
			}
		
		} while (0); /* not reached */
	}
	
	/**
	 * Discards any pending output lines created with CQP::raw_execute(). 
	 */
	public function raw_discard()
	{
		do { } while (false !== $this->raw_read());
	}

	
	/** helper func for query() and raw_query() */
	private static function get_random_querykey()
	{
		return random_int(0, 0x7fffffff); /* most +ve 32 bit signed int */
	}




	/**
	 * A wrapper for ->execute that gets the size of the named query.
	 * method has no error coding - relies on the normal ->execute error checking.
	 * 
	 * @return int  The number of hits in the query. 0 if there was an error.
	 */
	public function querysize($name)
	{
		if ((!is_string($name)) || empty($name))
		{
			$this->add_error("ERROR: CQP->querysize was passed an invalid argument");
			return (int)$this->shout_error();
		}
		
		$result = $this->execute("size $name");
		
		/* fails-safe to 0 */
		return (isset($result[0]) ? (int)$result[0] : 0);
	}




	/**
	 * Dumps a named query result into table of corpus positions.
	 * 
	 * See CQP documentation for explanation of what from and to do.
	 * 
	 * @return array      Returns an array of results. 
	 */
	public function dump($subcorpus, $from = '', $to = '')
	{
		if ( !is_string($subcorpus) || $subcorpus == "" )
		{
			$this->add_error("ERROR: CQP->dump was passed an invalid argument");
			return $this->shout_error();
		}
		
		$temp_returned = $this->execute("dump $subcorpus $from $to");

		$rows = array();

		foreach($temp_returned as $t)
			$rows[] = explode("\t", $t);
			
		return $rows;
	}



	/**
	 * Dumps a named query result into a table of corpus positions 
	 * that is saved in the specified write-path.
	 * 
	 * See CQP documentation for explanation of what from and to do.
	 * 
	 * @return bool  True (mostly), false (if something goes wrong). 
	 */
	public function dump_file($subcorpus, $writepath, $from = '', $to = '')
	{
		if (!is_string($subcorpus) || $subcorpus == '')
		{
			$this->add_error("ERROR: CQP->dump_file was passed an invalid argument");
			$this->shout_error();
			return false;
		}
		if (!is_writable($writepath))
		{
			$this->add_error("CQP: Filesystem path ``$writepath'' is not writeable!");
			$this->shout_error();
			return false;
		}
		
		$this->execute("dump $subcorpus $from $to > '$writepath'");
		
		return $this->ok();
	}



	/**
	 * Undumps a named query result from a table of corpus positions.
	 * 
	 * Usage:
	 * $cqp->undump($named_query, $matches); 
	 *
	 * Constructs a named query result from a table of corpus positions 
	 * (i.e. the opposite of the ->dump() method).  Each element of $matches 
	 * is an array as follows:
	 *           [match, matchend, target, keyword] 
	 * that represents the anchor points of a single match.  The target and 
	 * keyword anchors are optional, but every element array within the outer 
	 * array has to have the same length. When the matches are not sorted in 
	 * ascending order, CQP will automatically create an appropriate sort 
	 * index for the undumped query result.
	 * 
	 * An optional extra argument specifies a path to a directory 
	 * where the necessary temporary file can be stored; if none is given,
	 * the method will attempt to use the temporary directory (i.e. /tmp, which
	 * is the default location for CWB temp files). 
	 * 
	 * An optional extra argument specifies a path to a directory 
	 * where a temporary file can be stored; if none is given,
	 * the method will pipe in the data directly with no intermediate file.
	 */
	public function undump($subcorpus, $matches, $datadir = '')
	{
		if ( empty($subcorpus) || !is_array($matches) )
		{
			$this->add_error("ERROR: CQP->undump was passed an invalid argument");	
			return $this->shout_error();
		}
		
		/* number of matches ( = regions in the query/subcorpus) */
		$n_matches = count($matches);

		/* find out whether we're undumping targets, keywords etc; $with will determine it */
		list($row_1) = $matches;
		$n_anchors = count($row_1);
		switch($n_anchors)
		{
		case 2:
			$with = '';
			break;
		case 3:
			$with = "with target" ;
			break;
		case 4:
			$with = "with target keyword";
			break;
		default:
			$this->add_error("CQP: rows in undump table must have between 2 and 4 elements (first row has $n_anchors)");
			return $this->shout_error();
		}

		/* check whole array for member-array length correctness */
		foreach	($matches as $row)
		{
			if (($row_anchors = count($row)) != $n_anchors)
			{
				$this->add_error("CQP: all rows in undump table must have the same length (1st row = $n_anchors, this row = $row_anchors)");
				return $this->shout_error();
			}
		}

//$intermed_file_mode = true;
$intermed_file_mode = false;// DEBUG
//TODO make no-intermed work properly, and then remove the with-intermed-file version. 

if ($intermed_file_mode)
{
			/* need to read undump table from a temporary file, because entering a dumpfile
			 * from stdin requires cqp -e, which we don't have.
			 * 
			 * Allow a place on disk to be specified.
			 */
			if (!empty($datadir))
				$datadir = rtrim($datadir, '\\/') . DIRECTORY_SEPARATOR;

			$tempfile = new CQPInterchangeFile($datadir, true, 'this_undump');
			/* Writing the N of matches is not necessary, but may be more efficient */
			$tempfile->write($n_matches . $this->EOL);
				
			/* we iterate the array,  making sure it's valid, before writing to temp */
			foreach	($matches as $row)
				$tempfile->write(implode("\t", $row) . $this->EOL);

			$tempfile->finish();

			/* now send undump command with filename of temporary file */
			$tempfile_name = $tempfile->get_filename();
			$this->execute("undump $subcorpus $with < '{$this->gzip_path}gzip -cd $tempfile_name |'");

			/* delete temporary file */
			$tempfile->close();
}
else
{
			/* pipe based method: avoids the intermediate file without using any more RAM. */
			fwrite($this->pipe[self::SLV_IN], "undump $subcorpus $with ; {$this->EOL}$n_matches{$this->EOL}");
			foreach	($matches as $row)
				fwrite($this->pipe[self::SLV_IN], implode("\t", $row) . $this->EOL);
			/* to get the termination of the command, use raw_execute() */
			$this->raw_execute('', false);
}

		/* return success status of undump command */
		return $this->ok();
	}
	/* end of method undump() */

	
	

	/**
	 * Undumps a named query result from a set of matches already saved to disk
	 * in undump format (non-compressed).
	 * 
	 * (Compression might be added as automagic later: right now, that's not needed.)
	 * 
	 * Usage: $cqp->undump_file($named_query, $filepath); 
	 *
	 * Constructs a named query result from a table of corpus positions 
	 * (i.e. the opposite of the ->dump() method).  The table should be in
	 * the usual tab-delimited dump-file format and located at $filepath.
	 * Note that the file format is NOT checked - so if in doubt, check the 
	 * return value, which is the status of CQP after the undump command is 
	 * sent.
	 * 
	 * See documentation of undump() for more details. 
	 * 
	 * As with that function, all lines of the file must have the same number of
	 * columns - 2, 3 or 4. If 3, then there is a target. If 4, there is a target
	 * and a keyword. 2 is assumed, unless the appropriate boolean parameters are
	 * passed.
	 */
	public function undump_file($subcorpus, $filepath, $with_target = false, $with_keyword = false)
	{
		if ( (!is_string($subcorpus)) || $subcorpus == "" || (!is_file($filepath)) )
		{
			$this->add_error("ERROR: CQP->undump was passed an invalid argument");	
			return $this->shout_error();
		}

		/* undump with target and keyword? this variable will determine it based on the bool params. */
		$with = '';
		if ($with_target)
		{
			$with = "with target";
			if ($with_keyword)
				$with .= " keyword";
		}

		/* now send undump command */
		$this->execute("undump $subcorpus $with < '$filepath'");

		/* return success status of undump command */
		return $this->ok();
	}
	/* end of method undump_file() */



	/**
	 * Finds the length of the longest range within the specified subcorpus.
	 *
	 * @param  string $subcorpus  Subcorpus to measure.
	 * @return int                The maximum range length.
	 */
	public function max_range($subcorpus)
	{
		if (! is_string($subcorpus) || "" == $subcorpus)
		{
			$this->add_error("ERROR: CQP->max_width was passed an invalid argument");
			$this->shout_error();
			return false;
		}

		$cmd = "dump $subcorpus > \"| awk -F '\t' '{print \$2 - \$1 + 1}' | sort -rnu | head -1\";{$this->EOL}.EOL.;{$this->EOL}";
		/*
		 * note: it would also be possible to scroll through the dump in PHP.
		 * But that takes twice the time of using awk etc. (benchmarked!)
		 * maybe add that option only for if awk is not present? (longterm possibility)
		 */

		fwrite($this->pipe[self::SLV_IN], $cmd);

		$op = '';

		while ( 0 < strlen($line = fgets($this->pipe[self::SLV_OUT])) )
			if (preg_match('/^-::-EOL-::-/', $line))
				break;
			else
				$op .= $line;

		return (int)trim($op);
	}




	/**
	 * Computes frequency distribution over attribute values (single values or pairs)
	 * using CQP's group command.
	 * 
	 * Note that the arguments are specified in the logical order, in contrast to "group".
	 * 
	 * USAGE:  $cqp->group($named_query, "$anchor.$att", "$anchor.$att", $cutoff);
	 * 
	 * @param  string $subcorpus    Subcorpus or query result to perform "group" on. 
	 * @param  string $spec1        First specifier (compulsory): anchor plus attribute.
	 * @param  string $spec1        Second specifier (optional): anchor plus attribute.
	 * @param  int    $cutoff       The cutoff point: if not supplied, there will be no cutoff.
	 * @return array                Array of arrays with the data from "group".
	 */
	public function group($subcorpus, $spec1, $spec2 = NULL, $cutoff = NULL)
	{
		if ( empty($subcorpus) || empty($spec1) )
		{
			$this->add_error("ERROR: CQP->group was passed an invalid argument");
			$this->shout_error();
			return false;
		}
		
		$cutoff_cmd = is_null($cutoff) ? '' : ' cut ' . (int)$cutoff;

		if (!preg_match('/^(match|matchend|target[0-9]?|keyword)\.([A-Za-z0-9_-]+)$/', $spec1, $m))
		{
			$this->add_error("CQP:  invalid key \"$spec1\" in group() method");
			return $this->shout_error();
		}
		$spec1 = "{$m[1]} {$m[2]}";
		
		
		if (!empty($spec2))
		{
			if (!preg_match('/^(match|matchend|target[0-9]?|keyword)\.([A-Za-z0-9_-]+)$/',$spec2, $m) )
			{
				$this->add_error("CQP:  invalid key \"$spec2\" in group() method");
				return $this->shout_error();
			}
			$spec2 = "{$m[1]} {$m[2]}";

			$command = "group $subcorpus $spec2 by $spec1 $cutoff_cmd";
		}
		else
			$command = "group $subcorpus $spec1 $cutoff_cmd";
		
		$rows = array();
		
		foreach($this->execute($command) as $return_line)
			$rows[] = explode("\t", $return_line);
		
		return $rows;
	}



	/** Computes the frequency distribution for match strings based on a sort clause. */
	public function count($subcorpus, $sort_clause, $cutoff = 1)
	{
		$cutoff = (int)$cutoff;
		if (empty($subcorpus) || empty($sort_clause))
		{
			$this->add_error('ERROR: in CQP->count. USAGE: $cqp->count($named_query, $sort_clause [, $cutoff]);');
			$this->shout_error();
			return false;
		}
		
		$rows = array();
		
		$temp_returned = $this->execute("count $subcorpus $sort_clause cut $cutoff");
	
		foreach($temp_returned as $t)
		{
			list ($size, $first, $string) = explode("\t", $t);
			$rows[] = array($size, $string, $first, $first+$size-1);
		}
		return $rows;
	}



	/* ----------------------- *
	 * Error-handling methods. *
	 * ----------------------- */

	


	/** A method to read the CQP object's status variable. */
	public function status()
	{
		switch ($this->status)
		{
		case self::STATUS_ERROR: return 'error';
		case self::STATUS_OK:    return 'ok';
		}
	}
	
	
	
	
	/** 
	 * Check for any CQP errors.
	 *  
	 * @return bool   True if object status is "ok" (no errors); otherwise false.
	 */
	public function ok()
	{
		return ($this->status == self::STATUS_OK);
	}
	
	
	/**
	 * Returns the last error reported by CQP. 
	 * 
	 * This is not reset automatically, so you need to check $cqp->status() 
	 * in order to find out whether the error message was actually produced 
	 * by the last command.
	 *  
	 * @return array     An array of error message lines (without newlines).
	 */
	public function get_error_message()
	{
		return $this->error_message;
	}

	/** Does same as get_error_message(), but with all strings in the array rolled together. */
	public function get_error_message_as_string()
	{
		return implode(PHP_EOL, $this->error_message) . PHP_EOL;
	}

	/** 
	 * Does same as get_error_message, but with (X)HTML paragraph and linebreak tags.
	 * The parameter dictates what the value of the "class" attribute on the p-tag is to be;
	 * if no argument is supplied, the paragraph is given no class.
	 * 
	 * @param  string $p_class   Value for the p element's class attribute. Empty by default.
	 * @return string            HTML error message (single paragraph with br-elements 
	 *                           between separate error messages).
	 */
	public function get_error_message_as_html($p_class = '')
	{
		$class = (empty($p_class) ? '' : " class=\"$p_class\"");
		return "<p$class>{$this->EOL}" . implode("{$this->EOL}<br>{$this->EOL}", $this->error_message) . "{$this->EOL}</p>{$this->EOL}";
	}
	
	/** 
	 * Clears out all the error messages, returning the error message array
	 * to its original state (contains only 1 empty string).
	 * 
	 * Also returns the status to OK. It's the only funciton that does that.
	 * 
	 * Can only be called by user, the object itself won't call it from within
	 * another method. 
	 */
	public function clear_errors()
	{
		$this->error_message = array();
		$this->status = self::STATUS_OK;
	}	


	

	/**
	 * Function to call when the object encounters an error.
	 *  
	 * It takes as argument an array of strings to print; these 
	 * strings report errors in the object and CQP error messages. 
	 *
	 * If no argument is specified, the internal array of error 
	 * messages is used instead.
	 * 
	 * The strings are just printed to stdout if there is no error
	 * handler set; otherwise, the error handler callback is used.
	 * 
	 * @return bool     Always false.
	 */
	private function shout_error($messages = NULL)
	{
		if (empty($messages))
			$messages = $this->error_message;
		
		if (! empty($this->error_handler))
			call_user_func($this->error_handler, $messages);
			/* we need call_user_func() because trying to call $this->error_handler directly
			 * is ambiguous with trying to call a method called "error_handler". */
		else
			echo implode(PHP_EOL, $messages), PHP_EOL;
		
		return false;
	}

	
	/**
	 * Checks CQP's stderr stream for error messages.
	 * 
	 * IF THERE IS AN ERROR ON THE CHILD PROCESS'S STDERR, this function: 
	 * (1) moves the error message from stderr to $this->error_message 
	 * (2) prints an alert and the error message (up to MAX_SLV_ERRS_STORED lines)
	 * (3) returns true  (there is an error)
	 * 
	 * OTHERWISE, this function returns false (no errors found)
	 * 
	 * This is one of the methods that can shift us into an error state.
	 */
	private function check_pipe_for_error()
	{
		$r = array($this->pipe[self::SLV_ERR]);
		$w = NULL;
		$e = NULL;
		$error_strings = array();
		$n = 0;

		/* as long as there is anything on the child STDERR, 
		 * read up to MAX_SLV_ERRS_STORED lines from CQP's stderr */
		while (0 < stream_select($r, $w, $e, 0))
		{
			/* this will loop away any error lines beyond MAX_SLV_ERRS_STORED. */
			$curr_e_str = trim(fgets($this->pipe[self::SLV_ERR]));
			
			/* we only store the first MAX_SLV_ERRS_STORED. */
			if ($n++ < self::MAX_SLV_ERRS_STORED && !empty($curr_e_str))
				$error_strings[] = $curr_e_str;
			
			/* re-set the $r array for the next loop */
			$r = array($this->pipe[self::SLV_ERR]);
		}

		/* if we have error strings, add a header, and raise an error. */ 
		if (!empty($error_strings))
		{
			array_unshift($error_strings, "**** CQP ERROR ****");
			
			$this->status = self::STATUS_ERROR;
			$this->error_message = $error_strings;
			$this->shout_error();
			return true;    /* there has been an error */
		}
		else
			return false;   /* no error */
	}
	
	/**
	 * Adds an error at the top of the stack, without triggering the error
	 * handler or printing errors.
	 * 
	 * The ->status is also set to "error".
	 *
	 * Normal internal usage: first call add_error with the new error message,
	 * then EITHER carry on, OR call ->error() and then return false.
	 * 
	 * This is one of the methods that can shift us into an error state.
	 */
	private function add_error($message)
	{
		array_unshift($this->error_message, $message);
		$this->status = self::STATUS_ERROR;
	}



	/** Sets a user-defined error handler function. */
	public function set_error_handler($handler)
	{
		$this->error_handler = $handler;
	}
	



	
	/** set on/off progress bar display and specify function to deal with it. */
	public function set_progress_handler($handler = false)
	{
		if (false !== $handler)
		{
			$this->execute("set ProgressBar on");
			$this->progress_handler = $handler;
		}
		else
		{
			$this->execute("set ProgressBar off");
			$this->progress_handler = false;
		}
	}




	
	/**
	 * Execution-pause handler to process information from the progressbar. 
	 * 
	 * Note: makes calls to $this->progress_handler, with arguments 
	 * ($pass, $total, $progress [0 .. 100], $message) 
	 */
	function handle_progressbar($line = '')
	{
		if ($this->debug_mode)
			echo "CQP $line" . PHP_EOL;

		if ($this->progress_handler == false)
			return;
			
		list(, $pass, $total, $message) = explode("\t", $line);
		
		/* extract progress percentage, if present */
		if (preg_match('/([0-9]+)\%\s*complete/', $message, $m))
			$progress = $m[1];
		else 
			$progress = "??";
		
		$this->progress_handler($pass, $total, $progress, $message);
	}
	





	/**
	 * Set a path to find the gzip executable.
	 * 
	 * (This is an empty string by default; this method exists to make it possible
	 * to set the path to something else.) 
	 */
	public function set_gzip_path($newpath)
	{
		if (empty($newpath))
			$this->gzip_path = '';
		else
			$this->gzip_path = rtrim($newpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}

	
	/** Get the present state of the debug-mode setting. */
	public function get_debug_mode()
	{
		return $this->debug_mode;
	}
		
	/** 
	 * Switch debug mode on or off, and return the FORMER state (whatever it was).
	 * 
	 * Note that if a NULL argument or no argument is passed in, then debug_mode
	 * will not be changed -- only its value will be returned. 
	 * 
	 * The argument should be a Boolean. If it isn't, it will be typecast to
	 * bool according to the usual PHP rules (except NULL, of course).

	 * @param  bool $newstate   The new value; or leave empty to get the current value.
	 * @return bool             The value of the debug-mode setting before any change.
	 */
	public function set_debug_mode($newstate = NULL)
	{
		$oldstate = $this->debug_mode;
		
		if (!is_null($newstate))
			$this->debug_mode = (bool)$newstate;

		return $oldstate;
	}
	//TODO now we have get_debug_mode(), remove the "get" function of the above set funciton. 




	/* --------------- *
	 * Charset methods *
	 * --------------- */


	/** 
	 * Switch the character set encoding of an input string from the caller.
	 * 
	 * Input strings from caller are always utf8. 
	 * 
	 * This method filters them to another encoding, if necessary.
	 * 
	 * @param  string  $string   Input string - must be UTF-8.
	 * @return string            Output in the CWB corpus's native charset. 
	 */
	private function filter_input($string)
	{
		if ($this->corpus_charset == self::CHARSET_UTF8)
			return $string;
		else
			return iconv('UTF-8', self::$charset_labels_iconv[$this->corpus_charset] . '//TRANSLIT', $string);
	}
	
	
	/** 
	 * Switch the character set encoding of an output string to be sent to the caller.
	 * 
	 * Output strings from the CQP object are always utf8.
	 * 
	 * This method filters output in other encodings to utf8, if necessary.
	 * 
	 * The function can also cope with an array of strings. 
	 * 
	 * @param  array|string $string  A CQP native-charset output string to filter. 
	 *                               Or, array of such strings.
	 * @return array|string          The UTF-8 string. Or, an array, if $string was an array.
	 */
	private function filter_output($string)
	{
		if (self::CHARSET_UTF8 == $this->corpus_charset)
			return $string;
		
		if (!is_array($string))
			$string = array($string);
		
		$iconv_from = self::$charset_labels_iconv[$this->corpus_charset];
		
		/* array map for speed and elegance */
		return array_map(function($s) use ($iconv_from) { return iconv($iconv_from, 'UTF-8', $s); }, $string);
	}
	
	/** 
	 * Gets a string describing the charset of the currently loaded corpus,
	 * or NULL if no corpus is loaded.
	 * 
	 * @return string      CWB-style charset string label of the current corpus. 
	 *                     Or NULL, if no charset label could be found.
	 */
	public function get_corpus_charset()
	{
		if (isset($this->corpus_charset, self::$charset_labels_cwb[$this->corpus_charset]))
			return self::$charset_labels_cwb[$this->corpus_charset];
		else
			return NULL;
	}
	
	/**
	 * Gets the size of the currently loaded corpus (in tokens).
	 * 
	 * @return int    Number of tokens.
	 */
	public function get_corpus_tokens()
	{
		$infoblock = implode(PHP_EOL, $this->execute("info"));
		preg_match('/Size:\s+(\d+)\s/', $infoblock, $m);
		return (int) $m[1];
	}
	
	
	
	/* -------------- *
	 * STATIC METHODS *
	 * -------------- */

	/** 
	 * Get an ICONV-compatible string (assuming a fairly standard GNU-ICONV!) for
	 * a given character set.
	 * 
	 * NULL is returned if the argument is invalid.
	 * 
	 * @param string $charset     Character set indicator. Both CWB-style and ICONV-style 
	 *                            markers should be accepted and translated (e.g. both 'utf8' 
	 *                            and 'UTF-8' will return 'UTF-8'). 
	 */
	public static function translate_corpus_charset_to_iconv($charset)
	{
		$charset = strtolower($charset);
		if (isset(self::$charset_interpreter[$charset]))
			return self::$charset_labels_iconv[self::$charset_interpreter[$charset]];
		else
			return NULL;
	}
	
	/**
	 * Get a cwb-encode-compatible string for a given character set.
	 *  
	 * @param string $charset     Character set indicator. Both CWB-style and ICONV-style 
	 *                            markers should be accepted and translated (e.g. both 'utf8' 
	 *                            and 'UTF-8' will return 'utf8'). 
	 */
	public static function translate_corpus_charset_to_cwb($charset)
	{
		$charset = strtolower($charset);
		
		if (isset(self::$charset_interpreter[$charset]))
			return self::$charset_labels_cwb[self::$charset_interpreter[$charset]];
	}
	
	
	/**
	 * Backslash-escapes any regular expression metacharacters in the argument string,
	 * to create strings that can be sdafely embedded within the quote-marks-delimited parts
	 * of a CQP query.
	 * 
	 * Since CQP now uses PCRE, this is now a wrapper around the PHP function preg_quote; the wrapper supplies 
	 * the most common delimiter argument automatically (in the docs, all examples of CQP syntax use
	 * double quotes, though single quotes are certainly possible!)
	 * 
	 * @param  string $s          The string to escape.
	 * @param  string $delimiter  Optionally specify a delimiter character, which will also be escaped;
	 *                            default delimiter is double-quote, but single-quote can also be used.
	 * @return string             Modified string. 
	 */
	public static function escape_metacharacters($s, $delimiter = '"')
	{
		return preg_quote($s, $delimiter);
	}
	
	/** Gets a string containing the class's default required version of CWB. */ 
	public static function default_required_version()
	{
		return self::VERSION_MAJOR_DEFAULT
			. '.' . self::VERSION_MINOR_DEFAULT
			. '.' . self::VERSION_REVISION_DEFAULT
			;
	}

	/**
	 * Runs the connection procedure as in __construct(), but does it 
	 * with positively paranoid safety checks at every stage.
	 * 
	 * The results of every safety check are collected together in a
	 * (rather long, multi-line formatted) string which is the return value.
	 * 
	 * No class variables are set (obviously since this is a static 
	 * function) and the process is shut down at the end, regardless
	 * of success or failure.
	 * 
	 * If $as_boolean is set to true, then instead of an infoblock string,
	 * the return value is true (all checks passed) or false (one check
	 * failed).
	 */
	public static function diagnose_connection($path_to_cqp, $cwb_registry, $as_boolean = false)
	{
		$success = false;
		
		$infoblob = <<<END_BLOCK
Beginning diagnostics on CQP child process connection.

Using following configuration variables:
    \$path_to_cqp  = ``$path_to_cqp''
    \$cwb_registry = ``$cwb_registry''


END_BLOCK;
		
		/* all checks are wrapped in a do ... while(false) to allow a break to go straight to shutdown */
		do 
		{
			/* check path to cqp is a real directory */
			if ('' == $path_to_cqp)
			{
				/* we are expected to find it on the path; attempt to find with "which" */
				
				$infoblob .= "Executable location unspecified; checking that CQP is on the path ... ";
				$which_out = trim((string)shell_exec("which cqp"));
				
				if ( (DIRECTORY_SEPARATOR . 'cqp') == substr($which_out, -4) )
					$infoblob .= " yes it is!\n\n";
				else
					$infoblob .= "\n    could not ascertain, but let us proceed on the assumption that it is.\n";
				
				$cqp_exe = 'cqp';
			}
			else
			{
				$infoblob .= "Checking that directory $path_to_cqp exists... ";
				if (!is_dir($path_to_cqp))
				{
					$infoblob .= "\n    CHECK FAILED. Ensure that $path_to_cqp exists and contains the CQP executable.\n";
					break;
				}
				$infoblob .= " yes it does!\n\n";

				/* check that this user has read/execute permissions to the cqp executable */
				
				$cqp_exe = realpath($path_to_cqp . DIRECTORY_SEPARATOR . 'cqp');
				
				/* check that cqp exists */
				$infoblob .= "Checking that CQP program exists... ";
				if (!is_file($cqp_exe))
				{
					$infoblob .= "\n    CHECK FAILED. Ensure that $path_to_cqp contains the CQP executable.\n";
					break;
				}
				$infoblob .= " yes it does!\n\n";

				/* checking it's readable */
				$infoblob .= "Checking that CQP program is readable by the user... ";
				if (!is_readable($cqp_exe))
				{
					$infoblob .= "\n    CHECK FAILED. Ensure that $path_to_cqp contains the CQP executable.\n";
					break;
				}
				$infoblob .= " yes it is!\n\n";

				/* check that cqp is executable */
				$infoblob .= "Checking that CQP program is executable by this user... ";
				if (!is_executable($cqp_exe))
				{
					$infoblob .= "\n    CHECK FAILED. Ensure that $cqp_exe is readable by the username this script is running under.\n";
					break;
				}
				$infoblob .= " yes it is!\n\n";
			}
			
			
			/* check that cwb_registry is a real directory */
			$infoblob .= "Checking that $cwb_registry exists... ";
			if (!is_dir($cwb_registry))
			{
				$infoblob .= "\n    CHECK FAILED. Ensure that $cwb_registry exists and contains the CQP executable.\n";
				break;
			}
			$infoblob .= " yes it does!\n\n";
			
			/* check that this user has read/execute permissions to it */
			$infoblob .= "Checking that CWB registry is readable by this user... ";
			if (!is_readable($cwb_registry))
			{
				$infoblob .= "\n    CHECK FAILED. Ensure that $cwb_registry is readable by the username this script is running under.\n";
				break;
			}
			if (!is_executable($cwb_registry))
			{
				$infoblob .= "\n    CHECK FAILED. Ensure that $cwb_registry is executable by the username this script is running under.\n";
				break;
			}
			$infoblob .= " yes it is!\n\n";
			
			
			/* do an experimental startup */
			$infoblob .= "Now running process-open to get a link to CQP ... ";
			$io_settings = array(  	self::SLV_IN  => array("pipe", "r"),self::SLV_OUT => array("pipe", "w"),self::SLV_ERR => array("pipe", "w")  );
			$command = "$cqp_exe -c -r '$cwb_registry'";
			$pipe = NULL;
			$process = proc_open($command, $io_settings, $pipe);
			$infoblob .= "... complete. \n\n";
			
			$infoblob .= "Checking that the process we've set up to link to slave cqp has the datatypes it ought to... ";
			if (!is_resource($process))
			{
				$infoblob .= "\n    CHECK FAILED. Process handle is not a PHP resource.\n";
				break;
			}
			if ('process' != ($t = get_resource_type($process)))
			{
				$infoblob .= "\n    CHECK FAILED. Process handle is a PHP resource, but of the wrong type (it is '$t', it should be 'process').\n";
				break;
			}
			$infoblob .= " yes it has!\n\n";
			
			
			// TODO, maybe call proc_ get_ status() and check each field is as it should be >?
			
			
			/* check pipe has got the three stream resources in it that it should have. */ 
			$infoblob .= "Checking that we've got the right pipes into and out of the CQP process... ";
			if (3 != ($cnt = count($pipe)))
			{
				$infoblob .= "\n    CHECK FAILED. Wrong number of pipes to slave ($cnt); there should be exactly 3.\n";
				break;
			}
			$pipe_tests_failed = 0;
			foreach (array(self::SLV_IN,self::SLV_OUT,self::SLV_ERR) as $stream_id)
			{
				if (!isset($pipe[$stream_id]))
					$infoblob .= "\n    CHECK FAILED. Pipe no. $stream_id is missing.\n";
				else if (!is_resource($pipe[$stream_id]))
					$infoblob .= "\n    CHECK FAILED. Pipe no. $stream_id is not a PHP resource.\n";
				else if ('stream' != get_resource_type($pipe[$stream_id]))
					$infoblob .= "\n    CHECK FAILED. Pipe no. $stream_id is a resource, but it's not a stream!\n";
				else 
					continue;
				$pipe_tests_failed++;
			}
			if ($pipe_tests_failed)
				break;
			$infoblob .= " all looking good!\n\n";
			
			
			/* test read from pipe */
			$infoblob .= "Checking that it's possible to read lines of cqp output... ";
			$test = fgets($pipe[self::SLV_OUT]);
			if (false === $test)
			{
				$infoblob .= "\n    CHECK FAILED. Couldn't read a line from CQP.\n";
				break;
			}
			if ('' === $test)
			{
				$infoblob .= "\n    CHECK FAILED. Read a line, but it was empty; CQP should print its version on startup.\n";
				break;
			}
			if ('' === ($test = trim($test)))
			{
				$infoblob .= "\n    CHECK FAILED. Read a line, but it contained only spaces; CQP should print its version on startup.\n";
				break;
			}
			$infoblob .= " yes it is!\n\n";
			
			
			/* check versioning */
			$version_string = $test;
			
			/* look at the get the version info */
			$infoblob .= "Checking that this version of CQP is up-to-date enough (need " . self::default_required_version() . " or higher)... ";
			if (!preg_match(self::VERSION_REGEX, $version_string, $m))
			{
				$infoblob .= "\n    CHECK FAILED. The reported CQP version [$version_string] could not be parsed.\n";
				break;
			}
			if (!self::diagnose_version($m))
			{
				$needed = self::default_required_version();
				$infoblob .= "\n    CHECK FAILED. Your CWB-core is too old ($version_string). Please install version $needed or higher of CWB.\n";
				break;
			}
			$infoblob .= " yes, $version_string is high enough!\n\n";
			
			
			/* check write to the input pipe. */
			$infoblob .= "Checking that it's possible to write commands to lines of cqp output... ";
			$write_me = "show;\n".self::END_OF_OUTPUT_COMMAND.";\n";
			$write_me_len = strlen($write_me);
			if ($write_me_len != ($written_bytes = fwrite($pipe[self::SLV_IN], $write_me)))
			{
				$infoblob .= "\n    CHECK FAILED. Error writing to CQP's input pipe; only $written_bytes of $write_me_len.\n";
				break;
			}

			$n_lines = 0;
			$seen_marker = false;
			while (true)
			{
				if (false === ($line = fgets($pipe[self::SLV_OUT])))
					break;
				$line = trim($line);
				
				if (!empty($line))
					$n_lines++;
				
				if (self::END_OF_OUTPUT_SEEK == $line)
				{
					$seen_marker = true;
					break;
				}
			}
			if (!$seen_marker)
			{
				$infoblob .= "\n    CHECK FAILED. Sent data successfully to CQP, but didn't get back the full output data.\n";
				break;
			}
			if (1 > $n_lines)
			{
				$infoblob .= "\n    CHECK FAILED. Sent data successfully to CQP, but got too little data back ($n_lines lines).\n";
				break;
			}
			$infoblob .= " successfully sent $written_bytes bytes to CQP, and got back $n_lines line(s) of output!\n\n";
			
			
			/* we are happy that reading and writing have both worked out OK. */
			/* we can use this rough & ready execute function now. */
			$execute = 
				function($cmd, $pipe_in, $pipe_out) 
				{ 
					$mark = self::END_OF_OUTPUT_COMMAND;
					$result = [];
					fwrite($pipe_in, "$cmd;\n$mark;\n");
					while (false !== ($line = fgets($pipe_out)))
						if (self::END_OF_OUTPUT_SEEK == ($line = trim($line)))
							break;
						else
							$result[] = $line;
					return $result;
				};
			
			// TODO do something that should cause an error. Try reading from _ERR with select() as in check_pipe_for_erro. 
			
			/* if all that is working then the diagnostic is complete */
			$infoblob .= "The connection to the CQP child process was successful.\n\n";
			
			/* and we can finally set success to *true* */
			$success = true;
			
			/* end of do-while -- our "breakout" point. */
		} while (false);
		
		/* exit point for list of checks (from "break" above) */
		
		/* the following is just cleanup. */
		
		/* if the process was not created successfully, we do not need to close it */
		if (is_resource($process))
		{
			$infoblob .= "Attempting to shut down test process...\n";
			
			if (is_callable($execute))
				$execute('exit', $pipe[self::SLV_IN], $pipe[self::SLV_OUT]); 
			
			foreach (array(self::SLV_IN,self::SLV_OUT,self::SLV_ERR) as $stream_id)
				if (!is_resource($pipe[$stream_id]))
					$infoblob .= "\n  Couldn't find pipe # $stream_id to close it.\n";
				else if (!fclose($pipe[$stream_id]))
					$infoblob .= "\n  Attempting to close pipe # $stream_id failed.\n";
			
			$exit_code = proc_close($process);
			if (-1 == $exit_code)
				$infoblob .= "\n  Test process couldn't be closed (the attempt resulted inthe error code of -1).\n";
			else if ($exit_code)
				$infoblob .= "\n  Test process closed with an non-SUCCESS termination status (code number $exit_code).\n";
			else
				$infoblob .= "\nProcess shutdown was successful.\n";
		}
		
		$infoblob .= "\nCQP connection diagnostic complete.\n";

		if ("\n" != PHP_EOL)
			$infoblob = str_replace("\n", PHP_EOL, $infoblob);
		
		return ($as_boolean ? $success : $infoblob);
	}
	
	/** 
	 * Support method for CQP::diagnose_connection(). 
	 * Returns true if the core is new enough, else false. 
	 * Needs to be passed the match array from regex'ing 
	 * what cqp prints to CQP::VERSION_REGEX. 
	 * Duplicates code from __construct() and check_version(). 
	 */
	private static function diagnose_version($version_regex_match_array)
	{
		/* pull out the various bits from the regex match array.... */
		$major = (int)$version_regex_match_array[1];
		$minor = (int)$version_regex_match_array[2];
		$revision_flagged_beta = false;
		if (isset($version_regex_match_array[3]))
		{
			if ($version_regex_match_array[3][0] == 'b')
			{
				$revision_flagged_beta = true;
				$revision = (int)substr($version_regex_match_array[3], 1);
			}
			else
				$revision = (int)$version_regex_match_array[3];
		}
		else
			$revision = 0;

		/* one big boolean eval */
		return 
			(
				self::VERSION_MAJOR_DEFAULT < $major
			||
				( self::VERSION_MAJOR_DEFAULT == $major && self::VERSION_MINOR_DEFAULT < $minor )
			||
				(
					self::VERSION_MAJOR_DEFAULT        == $major
					&& self::VERSION_MINOR_DEFAULT     == $minor
					&& self::VERSION_REVISION_DEFAULT  <= $revision
					&& ($revision_flagged_beta||!$revision_flagged_beta) /* since, right now, we don't care. */
				)
			)
		;
	}

} /* end of class CQP */






/**
 * Interchange files are self-deleting temporary files. 
 * 
 * They are used to write some data; when you then 'finish' the file it hangs around
 * as a closed file, whose name you can send to another program (or you can read from it
 * via the object instead). 
 * 
 * The file is automatically deleted when you 'close' it, or when the object is destroyed.
 * 
 * The file will be either a plain file or a gzipped plain file.
 * 
 * Typical usage is as follows.
 * 
 * $intfile = new CQPInterchangeFile($my_temp_directory);
 * 
 * $intfile->write($my_data);
 * 
 * $intfile->finish();
 * 
 * send_to_some_other_module($intfile->get_filename());
 * 
 * ... or, instead of sending the filename to another module ...
 * 
 * $lines_to_do_something_with = $intfile->read();
 * 
 * $intfile->close();
 * 
 * This object is based on the CWB::TempFile object from the Perl interface, but with
 * simplified internals (doesn't use pipes, only gives two file format options instead 
 * of several).
 * 
 * This class requires the Zlib extension.
 */
class CQPInterchangeFile
{
	const STATUS_WRITING  = "W";
	const STATUS_FINISHED = "F";
	const STATUS_READING  = "R";
	const STATUS_DELETED  = "D";

	
	/* Members */
	
	/** Stores a reading/writing handle */
	private $handle;
	
	/** Full filepath (absolute or relative) */
	private $name;
	
	/** Status flag: W == writing, F == finished, R == reading, D == deleted; use STATUS_ constants. */
	private $status;
	
	/** Is the file written/read as a gz file or not?  */
	private $compression;
	
	/** The file protocol wrapper (dependent on $this->compression) */
	private $protocol;
	
	/** Callback for error handler function. */
	private $callback;


	/* METHODS */
	
		
	/**
	 * Note, the constructor interface is a bit different to the CWB::TempFile interface in the Perl
	 * module.
	 * 
	 * You can specify a directory for the file to be put in; the default is PHP's current working directory.
	 * If the directory you specify does not exist or is not writable, the location defaults back to the
	 * current working directory.
	 * 
	 * If $gzip is true, the file will be compressed.
	 * 
	 * If $nameroot is specified (letters, numbers, dash and underscore only!) it will be used as the 
	 * basis for the file's name. But it won't be precisely this name, of course. 
	 */
	public function __construct($location = '.', $gzip = false, $nameroot = 'CQPInterchangeFile')
	{
		/* process arguments */
		$this->compression = (bool)$gzip;
		
		$nameroot = preg_replace('/[^A-Za-z0-9_\-]/', '', $nameroot);
		if (empty($nameroot))
			$nameroot = 'CQPInterchangeFile';
			
		/* remove rightmost / or \ from folder, as below it is assumed there will be no slash */
		$location = rtrim($location, '/\\');
		if (empty($location) || !is_dir($location) || !is_writable($location))
			$location = '.';
		
		$unique = base_convert(uniqid(), 16, 36);
		$suffix = ( $this->compression ? '.gz' : '' );
		
		/* deeply unlikely you'll need this bit.... */
		for ($this->name = "$location/$nameroot-$unique$suffix", $n = 1; file_exists($this->name) ; $n++ )
			$this->name = "$location/$nameroot-$unique-$n$suffix";
		
		$this->protocol = ( $this->compression ? 'compress.zlib://' : '' );
		$this->handle = fopen($this->protocol . $this->name, 'w');
		if (false === $this->handle)
			$this->error( "CQPInterchangeFile: Error opening file {$this->name} for write" );
		$this->status = self::STATUS_WRITING;
	}
	
	/** Destructor; closes the file if not closed manually. */
	public function __destruct()
	{
		if ($this->status != self::STATUS_DELETED)
			$this->close();
	}
	
	/** Writes a line to the interchange file. */
	public function write($line)
	{
		if ($this->status != self::STATUS_WRITING)
			$this->error("CQPInterchangeFile: Can't write to file {$this->name} with status {$this->status}");
		
		if ( false === fwrite($this->handle, $line) )
 			$this->error("CQPInterchangeFile: Error writing to file {$this->name}");
	}
	
	
	/** Stops writing the file, and closes its handle */
	public function finish()
	{
		if (! ($this->status == self::STATUS_WRITING) )
			$this->error("CQPInterchangeFile: Can't finish file {$this->name} with status {$this->status}");

		/* close the file */
		if ( ! fclose($this->handle))
 			$this->error("CQPInterchangeFile: Error closing file {$this->name}");
		$this->status = self::STATUS_FINISHED;
	}
	
	/** 
	 * Reads a line from the file (opening before doing so if necessary). 
	 * 
	 * In case of error, the return values are the same as for fgets().
	 * 
	 * @return string
	 */
	public function read()
	{
		if ($this->status == self::STATUS_DELETED)
			$this->error("CQPInterchangeFile: Can't read from file {$this->name}, already deleted.");
				
		if ($this->status == self::STATUS_WRITING)
			$this->finish();
			
		if ($this->status != self::STATUS_READING)
		{
			$this->handle = fopen($this->protocol . $this->name, 'r');
			$this->status = self::STATUS_READING;
		}
		/* read a line */
		return fgets($this->handle);
	}

	/** Restart reading of the tempfile, by closing and re-opening it. */
	public function rewind()
	{
		if ($this->status == self::STATUS_DELETED || $this->status == self::STATUS_WRITING)
			$this->error("CQPInterchangeFile: Can't rewind file {$this->name} with status {$this->status}");
		
		/* if rewind is called before first read, it does nothing */
		if ($this->status != self::STATUS_READING)
			;
		else
		{
			if (!fclose($this->handle))
	 			$this->error("CQPInterchangeFile: Error closing file {$this->name}");
			$this->handle = fopen($this->protocol . $this->name, "r");
			if (false === $this->handle)
				$this->error("CQPInterchangeFile: Error opening file {$this->name} for read");
		}
	}
	
	/**
	 * Finishes reading or writing, and closes and deletes the file.
	 * 
	 * No return value (for either success or error conditions). In case of an error, 
	 * the object's error function is called.
	 */
	public function close()
	{
		if ( ($this->status == self::STATUS_WRITING || $this->status == self::STATUS_READING) && isset($this->handle) )
		{
			if (! fclose($this->handle))
 				$this->error( "CQPInterchangeFile: Error closing file " . $this->name);
 			unset ($this->handle);
  		}
  		
		if (is_file($this->name)) 
			if (!unlink($this->name))
				$this->error( "CQPInterchangeFile: Could not unlink file " . $this->name);
		$this->status = self::STATUS_DELETED;
	}
	
	
	/**
	 * Get the path of the temporary file.
	 * 
	 * It may be relative or absolute.
	 * @return string
	 */
	public function get_filename()
	{
		return $this->name;
	}



	/**
	 * Get the file's current status as an (uppercase) string.
	 * 
	 * Example usage: echo $interchange_file->status() . PHP_EOL; 
	 * 
	 * @return string
	 */
	public function get_status()
	{
		switch($this->status)
		{
		case self::STATUS_WRITING:      return "WRITING";
		case self::STATUS_FINISHED:     return "FINISHED";
		case self::STATUS_READING:      return "READING";
		case self::STATUS_DELETED:      return "DELETED";
		}
	}
	
	
	
	
	
	/* error handling functions */
	
	/**
	 * Allows a callback function to be specified for error messages
	 * (rather than exiting the program, which is the default error handling).
	 * 
	 * The callback can be anything that will work as the first argument of the PHP function
	 * call_user_func(). If it is an empty value, no callback will be used.
	 */
	public function set_error_callback($callback)
	{
		$this->callback = $callback;
	}
	
	/**
	 * Sends an error meessage to the user-specified callback, or aborts the program
	 * with that error message as the exit message if no callback is set.
	 */ 
	private function error($message)
	{
		if ( ! empty($this->error_callback) )
			call_user_func($this->error_callback, $message);
		else
			exit($message);
	}
	
} /* end of class CQPInterchangeFile */

