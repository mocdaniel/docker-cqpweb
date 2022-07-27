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
 * Create only those config values needed to make SQL connection work
 * (for autosetup and upgrade-database)
 */
class NotAFullConfig 
{
	public function get_slave_link($which) { global $Config; if ($which == 'sql') return $Config->mysql_link; }
	/* dummy in case cqpweb_shutdown_environment() is called (e.g. by exiterror). */
	public function disconnect_slave_program() { }
}







/* BEGIN FUNCTION DEFINITIONS */





/**
 * Reimplementation of getopt() which uses the global $argv (so yuou can modify that before calling this).
 * 
 * getopt ( string $options [, array $longopts [, int &$optind ]] ) : array
 * 
 * Note, the reimplementation is partial in that there is no "optional value" support;
 * opts either have values or they don't.
 * 
 * Note also that something that has been processed will be removed from argv. argv will retain (1) arguuments,
 * (2) options that couldn't be processed, (3) anything after '--'. argv will be renumbered after the things that have
 * been processed are removed. 
 * 
 * Response to "bad" options, short or long, may not coincide with what would happen with normal getopt. 
 * 
 * @param  string $options                 Short option string, as per getopt(), but only ':' not '::' is allowed.
 * @param  array  $longopts                Long option array, as per getopt(), same proviso as for $options.
 * @param  int    $optind                  Out-param for index of first non-option argument. Doesn't mean anything if $continue_past_nonopt.
 * @param  bool   $continue_past_nonopt    Iff true, options will be sought throughou argv - not just prior to the first non-option argu7ment. 
 * @return array
 */
function flexigetopt(string $options, array $longopts = [], int &$optind = NULL, $continue_past_nonopt = false)
{

	/* encapsulation: this func adds a value to the returnable array */
	$addval = function (&$opts, $which, $val)
	{
		/* format of value depends non what is there already. */
		if (!isset($opts[$which]))
			$opts[$which] = $val;
		else if (is_array($opts[$which]))
			$opts[$which][] = $val;
		else
			$opts[$which] = [ $opts[$which], $val ];
	};
	
	
	/* prepare option maps */
	
	$shortopts = [];
	for ( $i = 1, $n = strlen($options) ; $i < $n ; $i++ )
	{
		if (':' == $options[$i])
			break;  /* no optional args */
		else if (':' == $options[$i+1])
			$shortopts[$options[$i++]] = true; /* skip colon via ++ */
		else
			$shortopts[$options[$i]] = false;
	}
	
	$rawlongopts = $longopts;
	$longopts = [];
	foreach($rawlongopts as $raw)
		if (preg_match('/:$/', $raw))
			$longopts[rtrim($raw, ':')] = true;
		else
			$longopts[$raw] = false;

			
	/* now, we can parse the options. */
	
	global $argv;
	global $argc;
	
	$opts = [];
	
	$optind = $c = count($argv);
	
	for ($i = 0 ; $i < $c ; $i++)
	{
		/* non-opt arg: - or begins with no - */		
		if ('-' == $argv[$i] || '-' != $argv[$i][0])
		{
			if ($continue_past_nonopt)
				continue;
			else 
			{
				$optind = $i;
				break;
			}
		}
		
		/* what are the different options now? */
		if ('-' == $argv[$i][1])
		{
			if ('--' == $argv[$i] )
			{
				$optind = $i+1;
				unset($argv[$i]);
				break;
			}
			
			/* long option! */
			if (false !== strpos($argv[$i], '='))
			{
				/* uses = instead of space */
				list($long, $val) = explode('=', substr($argv[$i], 2));
				
				/* ignore nonexistent option */
				if (!isset($longopts[$long]))
					continue;
				
				unset($argv[$i]);
				
				if (!$longopts[$long])
					$val = false;
			}
			else
			{
				$long = substr($argv[$i], 2);
				
				if (!isset($longopts[$long]))
					continue;
				
				if (!$longopts[$long])
				{
					/* no value : insert value "false" */
					$addval($opts, $long, false);
					continue;
				}
				else
				{
					/* has value */
					$val = $argv[$i+1] ?? false;
					unset($argv[$i], $argv[$i+1]);
					$i++;
				}
			}
				
			$addval($opts, $long, $val); 					
		}
		else
		{
			/* short option! */
			$delete = false; /* we only take the shortopt cluster out of argv if something is detected. */
			
			for ($six = 1, $len = strlen($argv[$i]) ; $six < $len ; $six++ ) 
			{
				$flag = $argv[$i][$six];
				
				/* ignore letters that don't exist as option markers */
				if (!isset($shortopts[$flag]))
					continue;

				$delete = true;
				
				if (!$shortopts[$flag])
				{
					$addval($opts, $flag, false);
					continue;
				}

				if ($six+1 < $len)
					/* has direct-follow value */
					$val = substr($argv[$i], ($six+1));
				else
				{
					/* next entry in argv is the value */
					$val = $argv[$i+1] ?? false;
					unset($argv[$i]);
					$i++;
				}
				
				$addval($opts, $flag, $val);
				break; /* break this loop to continue the outer loop */
			}
			if ($delete)
				unset($argv[$i]);
		}
	}
	
	/* re-number the arguments for in case we've taken anything out */
	$argv = array_values($argv);
	$argc = count($argv);
	
	return $opts;
}





function get_variable_string($desc)
{
	while (1)
	{
		echo wordwrap("Please enter $desc:"), "\n\n";
		
		$s = trim(fgets(STDIN));
		
		echo "\n\nYou entered [$s], are you happy with this?\n\n";
		echo "Enter [Y]es or [N]:";
		
		$check = strtolower(trim(fgets(STDIN), " \t\r\n"));
		if ($check[0] == 'y')
			return $s;
	}	
}

function get_variable_path($desc)
{
	echo wordwrap("Please enter $desc as an absolute or relative directory path:"), "\n\n";
	
	while (1)
	{
		$s = trim(fgets(STDIN));
		echo "\n\n";
	
		if (!is_dir($s) || empty($s))
			echo "\n\n", wordwrap("$s does not appear to be a valid directory path, please try again:"), "\n\n\n";
		else
			return $s;
	}	
}

function get_variable_word($desc)
{
	echo wordwrap("Please enter $desc. Note this can only contain ASCII letters, numbers and underscore."), "\n\n";
	
	while (1)
	{
		$s = trim(fgets(STDIN));
		echo "\n\n";
	
		if (preg_match('/\W/', $s) > 0 || $s === '')
			echo "\n\n$s contains invalid characters, please try again:\n\n\n";
		else
			return $s;
	}
}

function ask_boolean_question($question)
{
	while (1)
	{
		echo "\n", wordwrap($question), "\n\n";
	
		echo "Enter [Y]es or [N]:";
		
		$s = strtolower(trim(fgets(STDIN), "/ \t\r\n"));
		if (!empty($s))
		{
			if ($s[0] == 'y')
				return true;
			else if ($s[0] == 'n')
				return false;
		}
	}
}

function get_enter_to_continue()
{
	echo "Press [enter] to continue.\n\n";
	fgets(STDIN);	
}

/* END FUNCTION DEFINITIONS */





