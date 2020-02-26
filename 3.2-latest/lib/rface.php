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
 * This class contains the code shared by different slave-program 
 * controller classes. 
 */
abstract class SlaveFace 
{
	/* constants for accessing "pipe" to a particular child. */
	
	/** index in $this->pipe of slave STDIN */
	const SLV_IN  = 0;
	/** index in $this->pipe of slave STDOUT */
	const SLV_OUT = 1;
	/** index in $this->pipe of slave STDERR*/
	const SLV_ERR = 2;
	
	/* member variables : general */

	/** String containing a readable name for the current object */
	protected $slave_name;
	
	/** String containing the name of the executable */
	protected $slave_executable;
	
	/** String containing options for when the executable is called */
	protected $slave_opts;
	
	/** Callable (string or closure) to check the content of lines read from the error pipe. */
	protected $slave_error_pipe_checker;
	
	/** handle for the process */
	protected $process;

	/** array for the input/output stream handles themselves to go in */
	protected $pipe;
	
	/** variable that remembers path on system of the exectuable that was started up */
	protected $slave_which;
	
	/** setting: should the object exit the program on detection of an error? */
	protected $exit_on_error;
	
	/** setting: if true, debug info will be printed to debug_dest */
	protected $debug_mode;
	
	/** setting: flag determining whether or not the stream in $debug_dest will be closed on destruct() */
	protected $debug_dest_autoclose = false;
	
	/** stream that debug messages will be sent to; if none specified, constructor sets it to php://output */
	protected $debug_dest;
	
	/** function (or array of object/classname + method at [0] and [1]) for handling textual output from program */
	protected $output_handler_callback = false;
	
	/* variables for error handling and debug logging */
	
	/** has there been an error? If there has, this variable will not be true. */
	protected $ok = true;
	
	/** most recent error message */
	protected $last_error_message;
	
	
	
	
	/** This is the generic connector */
	public function __construct($path_to_prog = false, $debug = false, $debug_dest = false, $debug_dest_autoclose = false)
	{
		/* Constructor errors are critical, so let's turn on exit_on_error. */
		$this->exit_on_error = true;

		/* passthru settings for debug mode for this object */
		$this->set_debug($debug);
		if (false === $debug_dest)
		{
			$debug_dest = fopen("php://output", "w");
			$debug_dest_autoclose = true;
		}
		$this->set_debug_destination($debug_dest, $debug_dest_autoclose);

		/* work out whether the call will need a '.exe' */
		$ext = (strtoupper(substr(php_uname('s'), 0, 3)) == 'WIN' ? '.exe' : '' );

		/* check that we know where the program is... */
		if (!empty($path_to_prog))
		{
			if ( !is_dir($path_to_prog = rtrim($path_to_prog, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR ))
				$this->error(static::class . "Directory $path_to_prog for $this->slave_name executable not found.\n");		
		}
		else
		{
			/* assume that it is on the path; if not, test for proc_open will catch it. */
			$this->debug_alert("Assuming $this->slave_name executable is on the normal PATH.....\n");
			$path_to_prog = '';
		}


		/* array of settings for the three pipe-handles */
		$io_settings = array(
			self::SLV_IN  => array("pipe", "r"),  /* pipe allocated to slave stdin  */
			self::SLV_OUT => array("pipe", "w"),  /* pipe allocated to slave stdout */
			self::SLV_ERR => array("pipe", "w")   /* pipe allocated to slave stderr */
			);
		
		$command = "$path_to_prog$this->slave_executable$ext $this->slave_opts ";
		
		$this->process = proc_open($command, $io_settings, $this->pipe);
		
		if (! is_resource($this->process))
			$this->error("$this->slave_name backend startup failed; command: $command\n");
		else
			$this->debug_alert("$this->slave_name backend successfully started up.\n");

		
		/* remember the executable */
		$this->slave_which = "{$path_to_prog}$this->slave_executable$ext";
		
		/* finally, turn down the severity level of errors... user can always turn it up again. */
		$this->exit_on_error = false;
	}
	
	/** Generic destructor. */
	public function __destruct()
	{
		if (is_resource($this->pipe[self::SLV_IN]))
		{
			fwrite($this->pipe[self::SLV_IN], $this->slave_shutdown_command);
			fclose($this->pipe[self::SLV_IN]);
		}
		if (is_resource($this->pipe[1]))
			fclose($this->pipe[1]);
		if (is_resource($this->pipe[2]))
			fclose($this->pipe[2]);
		
		$this->debug_alert("Pipes to $this->slave_name backend successfully closed.\n");
		
		/* and finally shut down the child process so script doesn't hang */
		if (isset($this->process))
			proc_close($this->process);

		$this->debug_alert("$this->slave_name slave process has been closed; interface object will now destruct.\n");
		
		if ($this->debug_dest_autoclose)
		{
			$this->debug_alert("Closing debug alert stream after this message.\n");
			if ( ! fclose($this->debug_dest) )
				$this->debug_alert("Failed to close debug alert stream.\n");
		}	
	}
	

	/**
	 * Specify a callback function to be used on output from execute().
	 *
	 * The callback can be (a) a closure (b) a string naming a function (c) an array of an object plus a method name
	 * (d) an array of a class name plus a method name (that is, any of the usual options for callbacks in PHP).
	 *
	 * To use no output handler, pass false.
	 */
	public function set_output_handler($callback)
	{
		if (false === $callback)
		{
			$this->output_handler_callback = false;
			$this->debug_alert("Output handler wiped, output handling disabled.\n");
		}
		else if (is_array($callback))
		{
			if ( isset($callback[0], $callback[1]) && count($callback) == 2)
			{
				/* case one:  we have been passed an object or class and its method */
				$callback_name = '[not known]';
				if ( is_object($callback[0]) && is_callable($callback, false, $callback_name) )
				{
					$this->output_handler_callback = $callback;
					$this->debug_alert("Output handler accepted ( $callback_name, object call ).\n");
				}
				else if (class_exists($callback[0] && method_exists($callback[0], $callback[1])))
				{
					$this->output_handler_callback = $callback;
					$this->debug_alert("Output handler accepted ( $callback[0]::$callback[1], static call ).\n");
			 	}
				else
					$this->error("Uncallable object/class method passed as output handler.\n");
			}
			else
				$this->error("Invalid array layout for output handler callback.\n");
		}
		else if  (is_callable($callback))
		{
			$this->output_handler_callback = $callback;
			$callback_name = ( is_string($callback) ? $callback : '[anonymous function]');
			$this->debug_alert("Output handler accepted ( $callback_name ).\n");
		}
		else
			$this->error("Unrecognisable output handler function was passed ( $callback ).\n");
	}
	

	/*
	 * ===============================
	 * error control & debug messaging
	 * ===============================
	 */
	
	
	
	/**
	 * Sets debug messages on or off (parameter is a bool).
	 *
	 * (By default, debug messages are off. They can also be turned on when
	 * the constructor is called.)
	 */
	public function set_debug($new_value)
	{
		$this->debug_mode = (bool)$new_value;
	}
	
	
	/**
	 * Sets the destination stream for debug messages.
	 * 
	 * Typically, you'd pass in an open file handle.
	 * 
	 * The second parameter determines whether the stream is self-closing; by
	 * default it isn't, but if it is, then the object destructor will attempt to
	 * close the stream with fclose().
	 */
	public function set_debug_destination($new_stream, $autoclose = false)
	{
		$x = @get_resource_type($new_stream);
		if ($x != 'file' && $x != 'stream')
			$this->error("Stream specified for printing debug messages is not valid.");
		$this->debug_dest = $new_stream;
		$this->debug_dest_autoclose = (bool)$autoclose;	
	}
	

	/**
	 * Print a message to the debug stream, iff debug output is enabled.
	 */
	protected function debug_alert($msg)
	{
		if ($this->debug_mode)
			fputs($this->debug_dest, static::class . ': ' . $msg . "\n");
	}
	
	
	/**
	 * Print a report on data being piped TO the slave.
	 */
	protected function alert_data_in($dat)
	{
		if ($this->debug_mode)
			fputs($this->debug_dest, static::class . ": $this->slave_executable << $dat\n");
	}

	/**
	 * Print a report on data received through pipe FROM the slave. 
	 */
	protected function alert_data_out($dat)
	{
		if ($this->debug_mode)
			fputs($this->debug_dest, static::class . ": $this->slave_executable >> $dat\n");
	}
		

	
	/**
	 * Sets exit-on-error mode on/off (parameter is a bool).
	 * 
	 * (By default, this mode is OFF.)
	 */
	public function set_exit_on_error($new_value)
	{
		$this->exit_on_error = (bool)$new_value;
	}
	
	
	/**
	 * Checks whether everything has gone OK.
	 *
	 * Returns false if there has been an error.
	 */
	public function ok()
	{
		return $this->ok;
	}
	
	/**
	 * Gets the most recent error message.
	 */
	public function error_message()
	{
		return $this->last_error_message;
	}
	
	/**
	 * Raises an error from within the RFace.
	 * 
	 * When an error is raised, it will exit PHP if the "exit_on_error" variable
	 * is set to true. Otherwise, the error is stored, and can be accessed using
	 * the error_message() and ok() methods.
	 * 
	 * In debug mode, errors will be sent to the debug output stream as well.
	 * 
	 * Method can optionally be passed a message and line number.
	 * 
	 * @param  string  $msg   An error message (optional)
	 * @param  int     $line  Line number where error occurred (optional)
	 * @return bool           Always false.
	 */
	protected function error($msg = '', $line = NULL)
	{
		$this->ok = false;
		
		if (empty($msg))
			$msg = "Nonspecific $this->slave_name interface error";
		if (!is_null($line))
			$msg .= "\n\t... at line $line\n";
		
		$msg .= $this->read_error_pipe();
		
		$msg = static::class . " ERROR: $msg\n";
		
		$this->last_error_message = $msg;
		
		$this->debug_alert($msg);
		
		if ($this->exit_on_error)
			exit($msg);
		
		return false;
	}


	/** 
	 * Check slave pipe for any error messages.
	 * 
	 * @return string   Error message (single string with all the different lines concatenated). 
	 *                  Or null if there were none. 
	 */ 
	protected function read_error_pipe()
	{
		$lines = [];

		while (false !== ($tmp = fgets($this->pipe[self::SLV_ERR])))
			$lines[] = $tmp; 

		if (empty($lines))
			return NULL;
		else
		{
			if (!empty($this->slave_error_pipe_checker))
				array_walk($lines, $this->slave_error_pipe_checker);
			
// donm't debug alert;error() does it. 
			$str = "$this->slave_name error output:\n\t" . implode("\n\t", $lines) . "\n";
			$this->debug_alert($str);
			
			return $str;
		}
	}

}















/**
 * class RFace:
 * 
 * PHP interface to the R statistical environment.
 * 
 * Usage:
 * 
 *     $r = new RFace($path_to_r);
 * 
 * ... where $path_to_r is an absolute or relative path to the directory containing 
 * the R executable (if you leave it unspecified, the environment path will be checked) ...
 * 
 *     $result = $r->execute("command(s) to be fed to R here");
 * 
 * To explicitly shut down child process:
 * 
 *     unset($r);
 * 
 * Other methods (many of which are to-do!) wrap-around execute() 
 * to provide a friendlier API for various uses of R. 
 * 
 * This class inherits from an abstract class that handles basic process-control.
 * 
 */
class RFace extends SlaveFace
{
	
	/* class constants */
	
	const DEFAULT_CHART_FILENAME = 'R-chart';
	
	const DEFAULT_CHART_FILETYPE = 'png';
	
	const DEFAULT_WORKSPACE_FILENAME = '.RData';

	const VALTYPE_DEDUCE  = -1;
	const VALTYPE_UNKNOWN =  0;
	const VALTYPE_MIXED   =  1;
	const VALTYPE_NUMBER  =  2;
	const VALTYPE_STRING  =  3;
	const VALTYPE_ARRAY   =  4;
	const VALTYPE_UNDEF   =  5;
	
    const CHART_FILETYPE_PNG = 'png';
    //TODO more, and use below. 
	
	
	/* member variables : general */
	
	/** Array containing default options for the ->read() method. DO NOT CHANGE AT RUNTIME!! */
	private $default_read_options = array ('transpose'=>false, 'use_labels'=>false);
	
	
	
	/* caches for oft-used R calls */
	
	/** cache of the list of object names; begins as false, set it back to false to clear it! */
	private $object_list_cache = false;
	
	/** cache for whether or not it is possible to use the json function (NULL to begin with, later true or false) */
	private $json_is_possible_cache = NULL;
	
	/** did the last error spit out "execution halted"? true = yes, false = no, NULL = haven't checked yet. */
	private $execution_halted = NULL;
	
	
	
	
	
	/**
	 * Constructor function for RFace class.
	 * 
	 * There are no compulsory arguments. The path to R should be a relative or absolute
	 * path of the directory where the R executable lives (i.e. DON'T put "/R" or "/R.exe"
	 * or whatever at the end of this string). If it is false, however, the normal PATH 
	 * variable from PHP's $_ENV superglobal array is used.
	 *  
	 * Note that the debug destination stream can be set up at construct-time, or later, using
	 * the dedicated functions.
	 * 
	 * @param string   $path_to_r
	 * @param bool     $debug
	 * @param resource $debug_dest
	 * @param bool     $debug_dest_autoclose
	 */
	public function __construct($path_to_r = false, $debug = false, $debug_dest = false, $debug_dest_autoclose = false)
	{
		$this->slave_name = 'R';
		$this->slave_executable = 'R';
		$this->slave_opts = '--slave --no-readline';
		$this->slave_error_pipe_checker = [ $this, 'check_error_string_for_execution_handled' ];
        // FIXME use closure above instead? to avoid the circular reference. 
		
		parent::__construct($path_to_r, $debug, $debug_dest, $debug_dest_autoclose);			
		
		/* make life easier by getting rid of most/all line wrapping */
		$this->execute('options(width=10000)');
	}
	
	
	
	/** 
	 * Destructor function for the RFace class.
	 */
	public function __destruct()
	{
		$this->slave_shutdown_command = "q(save=\"no\", runLast=FALSE)\n";
		parent::__destruct(); 
	}





	/**
	 * Main execution method: returns an array of lines of output from R.
	 * 
	 * This may be an empty array if R didn't print anything.
	 * 
	 * False is returned in case of error.
	 * 
	 * If $output_handler_callback is specified, it will be called on the
	 * output (single string with lines separated by \n but other
	 * whitespace trimmed out), and execute() will pass back the return value of 
	 * the callback function. 
	 * 
	 * If $line_handler_callback is NOT specified, the function checks whether
	 * one has been set at the object level ($this->output_handler_callback). If it
	 * has, that is used, and its return value is sent back. 
	 * 
	 * Note that empty lines are ALWAYS skipped (never collected for the output handler
	 * OR never added to the return array).
	 * 
	 */
	public function execute($command, $output_handler_callback = false)
	{
		if ($output_handler_callback === false)
			if ($this->output_handler_callback !== false)
				$output_handler_callback = $this->output_handler_callback;

		/* reset the execution halted check */
		$this->execution_halted = NULL;
		
		/* execute can change the number of objects, so clear object-list cache */
		$this->object_list_cache = false;
		
		$command = trim($command);
		
		if (empty($command))
			return $this->error(static::class . "::execute() was called with no command\n");
		
		$command .= "\n";

		$this->alert_data_in($command);

		/* send the command to slave [IN] */
		if (false === fwrite($this->pipe[0], $command))
			return $this->error("problem writing to the $this->slave_name input stream\n");

		/* that executes the command ... */
		if (false === fwrite($this->pipe[0], "cat(\"\\n-::-EOL-::-\\n\")\n"))
			return $this->error("problem writing to the $this->slave_name input stream\n");

		$result = array();
		
		/* then, get lines one by one from [OUT] */
		while (1)
		{
			$line = fgets($this->pipe[1]);
			
			/* should never happen, unless there was a syntax error before we got to the cat() call */
			if (false === $line)
			{
//$el= "ERR::";while ($ll = fgets($this->handle[2]))$el.=$ll;
//var_dump($el);
				$this->error("Read from pipe failed, due to syntax error or R internal error.\n");
				return false;
			}

			/* delete whitespace from the line; */
			$line = trim($line, " \t\r\n");
			
			/* check for delimiter being printed for end-of-output */
			if ($line == '-::-EOL-::-')
				break;
			
			/* blank lines NEVER added to the array */
			if (empty($line))
				continue;

// TODO R specific. 
			/* an output line we ALWAYS ignore; an empty statement terminated by ; is not invalid! */
			if ($line == 'Error: unexpected \';\' in ";"')
				continue;

			/* add the line to an array of results */
			$result[] = $line;

			$this->alert_data_out($line);
		}
		/* Note, no attempt is made to do anything with R's [ERR] stream, at least for now! */

		if (!empty($output_handler_callback))
		{
			/* call the specified function or class/object method */
			$callback_return = call_user_func($output_handler_callback, implode("\n", $result));
			$return_print = (string) $callback_return;
			if (strlen($return_print) > 16)
				$return_print = '[extra-long string]';
			$this->debug_alert("output-collector >> output-handler-callback >> $return_print\n");
			return $callback_return;
		}
		else
			/* return the array of result lines */
			return $result;
	}

	protected function check_error_string_for_execution_handled($str) // TODO shouldn't this be "halted"?
	{
		if (preg_match('/\bexecution\s+halted\b/i', $str))
			$this->execution_halted = true;
	}
	
	public function check_execution_halted()
	{
// FIXME this will only work if the global errro pipe handler is still [this, check_error_string_for_execution_handled] -- whihc isfragile. 
		if (is_null($this->execution_halted))
			$this->read_error_pipe();
		return $this->execution_halted;
	}
	
	
	/**
	 * Tells you what R executable is being used.
	 * 
	 * This variable is filled in by the constructor. It can't be set, as it is 
	 * permanent: you can only read it.
	 * 
	 * If you passed in a path, then obviously, this will be the R in the location
	 * you specified. If you didn't pass in a path, this should tell you which
	 * R executable was found.
	 * 
	 * Mostly for debugging purposes.
	 */
	public function get_which_R() { return $this->slave_which; }
	
	
	/**
	 * Gets the version of the current R executable.
	 * 
	 * @param string $what  Can have one of the following values:
	 *               "all" -- return the version string in full (the default)
	 *               "version" -- return just the version number xx.yy.zz
	 *               "date" -- return just the build date yyyy-mm-dd 
	 */
	public function get_R_version($what = "all")
	{
		$v = $this->execute("R.Version.string");	
		switch($what)
		{
		case "version":
			if ( 0 < preg_match('/R version (\d+\.\d+\.\d+)/', $v, $m))
				return $m[1];
			else
				$this->error("Could not parse version string for version number.\n");
			break;
		case "date":
			if ( 0 < preg_match('/R version \d+\.\d+\.\d+/ \((\d+-\d+-\d+)\)', $v, $m))
				return $m[1];
			else
				$this->error("Could not parse version string for build date.\n");
			break;
		default: 
		case "all":
			return $v;
		}
	}
	
	
	/*
	 * load methods (move data object from PHP to R)
	 */
	
	
	/**
	 * Loads data from a PHP array to an R vector.
	 * 
	 * The PHP array is assumed to have continuous numeric keys starting at 0,
	 * which will be shifted to continuous numeric keys starting at 1 in R.
	 * 
	 * A number-mode array can contain only numbers (ints or floats).
	 * 
	 * A string-mode array is allowed to contain ints and floats - but they will be
	 * converted to strings by PHP's normal string-embedding mechanism.
	 * 
	 * @param string $varname The name the new R object should have. It is also possible to
	 *                        overwrite an object, as usual in R. No checks are performed on
	 *                        overwriting.
	 * @param array $array    The array itself; passed by reference. If it's not an array but
	 *                        a single variable, then it will be converted to a one-member
	 *                        vector.
	 * @param int $type       Optionally specify the type of array: using a VALTYPE constant.
	 * @return bool           True if load successful, otherwise false.
	 */
	public function load_vector($varname, &$array, $type = self::VALTYPE_DEDUCE)
	{
		if (! is_array($array))
		{
			$tmparray = array($array);
			return $this->load_vector($varname, $tmparray, self::deduce_array_type($tmparray));
		}
		
		switch ($type)
		{
		case self::VALTYPE_DEDUCE:
			return $this->load_vector($varname, $array, self::deduce_array_type($array));
		case self::VALTYPE_STRING:
			return $this->load_vector_of_strings($varname, $array);
		case self::VALTYPE_NUMBER:
			return $this->load_vector_of_numbers($varname, $array);
		default:
			$this->error('Unrecognised array type!', __LINE__);
			break;
		}
	}
	
	private function load_vector_of_strings($varname, &$array)
	{
		/* build comma-delimited string of values */
		$instring = '';
		foreach($array as &$a)
			$instring .= '","' . addslashes($a);
		
		/* now, add start and end of command, before sending to R */
		$instring = "$varname = c(\"" . substr($instring, 2) . '")';
		$r = $this->execute($instring);
		
		/* successful load will have returned empty array */
		return (empty($r) && is_array($r));
	}
	
	private function load_vector_of_numbers($varname, &$array)
	{
		/* build comma-delimited string of values */
		$instring = '';
		foreach($array as &$a)
			$instring .= ',' . self::num($a);
		
		/* now, add start and end of command, before sending to R */
		$instring = "$varname = c(" . ltrim($instring, ",") . ')';		
		$r = $this->execute($instring);
		
		/* successful load will have returned empty array */
		return (empty($r) && is_array($r));
	}
	
	/**
	 * Creates a matrix from a 2 dimensional PHP array. By default, 
	 * inner arrays represent columns; set transpose to true to have inner 
	 * arrays represent rows instead.
	 * 
	 * The matrix will be created without row names or row labels.
	 * 
	 * @param string $varname    The name the resulting variable is to have in R. 
	 *                           If an object of that name already exists, it will 
	 *                           be overwritten.
	 * @param array $array       The array to load. It is passed by reference but
	 *                           not modified. It is assumed to be a 2D array with
	 *                           equal-length subarrays (length deduced from that of
	 *                           the first subarray). It is also assumed that each
	 *                           subarray, and each value within each subarray, is 
	 *                           sorted in the order they should be in; keys are 
	 *                           ignored.
	 * @param bool $transpose    If true, each inner array is taken to represent a 
	 *                           row. If false (which is default as in R), each 
	 *                           inner array is taken to represent a column. 
	 * @param bool $as_strings   If true, then a matrix of strings will be created.
	 *                           If false, a matrix of numbers will be created.
	 *                           False is the default.
	 * @return bool              True if load successful, otherwise false.
	 */
	public function load_matrix($varname, &$array, $transpose = false, $as_strings = false)
	{
		/* build the 1d data vector */
		if ($as_strings)
		{
			$c_string = '';
			foreach($array as $a)
				if ('' != $c_string)
					$c_string .= '","' . implode('","' , array_map("addslashes", $a));
				else
					$c_string = '"' . implode('","' , array_map("addslashes", $a)); 
			$c_string .= '"';
		}
		else
		{
			$c_string = '';
			foreach($array as $a)
				$c_string .= ',' . implode(',', array_map("RFace::num", $a));
			$c_string = ltrim($c_string, ',');
		}
		
		$n_inner = count($array);
		
		if ($transpose)
			$extra_commands = "nrow=$n_inner, byrow=TRUE";
		else
			$extra_commands = "ncol=$n_inner, byrow=FALSE";
		
		$ret_arr = $this->execute("$varname = matrix(c($c_string), $extra_commands)");
		
		/* successful load will have returned empty array */
		return (empty($ret_arr) && is_array($ret_arr));
	}
	
	/**
	 * Creates a factor from a PHP array of strings.
	 * 
	 * See load_vector for how conversion works.
	 * 
	 * @return        Boolean: true if load successful, otherwise false.
	 */
	public function load_factor($varname, &$array)
	{
		$temp_obj = $this->new_object_name();

		if (! $this->load_vector_of_strings($temp_obj, $array))
			return false;

		$ret_arr = $this->execute("$varname = factor($temp_obj)");

		$this->drop_object($temp_obj);
		
		return (empty($ret_arr) && is_array($ret_arr)); 
	}
	
	/**
	 * Creates a data frame from a PHP string or two-dimensional array.
	 * 
	 * If $data is a string, then the following assumptions are made:
	 * 
	 * (1) that lines are terminated by \n or \r\n
	 * (2) that fields are separated by \t
	 * (3) that objects = rows and fields = columns
	 * (4) if $header_row, then the first line is treated as containing column names
	 *     that can be used as variable labels
	 * (5) if $header_col, then the first column is treated as containing row labels
	 *     that can be used as object names
	 * 
	 * If $data is an array, then the following assumptions are made:
	 * 
	 * (1) that each member of the array represents a column of the table (a variable)
	 * (2) that each member is a one-dimensional array representing the values of that variable
	 * (3) that all inner arrys are of the same length and same type (that is, they
	 *     would work as R vectors)
	 * (4) if $header_row, then the first element of each array is treated as containing column names
	 *     that can be used as variable labels
	 * (5) if $header_col, then the first element of each array is treated as containing 
	 *     column labels that
	 * 
	 * In either mode, $transpose can be set to true: in which case, the inner arrays
	 * are assumed to be rows rather than columns (and the effects of $header_row and
	 * $header_col are switched).
	 * 
	 * @param  string $varname      The name the resulting variable is to have in R. If an object of
	 *                              that name already exists, it will be overwritten.
	 * @param  string $data         The data frame to be loaded (string representation of table, 
	 *                              or 2d array). Passed by reference, but not modified.
	 * @param  bool   $header_row   Boolean: does the data contain a header row? (Header row = 
	 *                              everything up to or including the first \n in a string; or,
	 *                              the first member of each array (the first array iff $transpose)
	 *                              contains a header string.) Defaults to true.
	 * @param  bool   $header_col   Boolean: does the data contain a header column? (Header column = 
	 *                              everything up to the first \t per line in a string; or,
	 *                              the first array (the first member of each array iff $transpose)
	 *                              contains a header string.) Defaults to true.
	 * @param  bool   $transpose    Boolean: if true, the two dimensions of an array are swopped.
	 * 
	 * @return bool                 True if load successful, otherwise false. 
	 */
	public function load_data_frame($varname, &$data, $header_row = true, $header_col = true, $transpose = false)
	{
		if (is_string($data))
			return $this->load_data_frame_from_string($varname, $data, $header_row, $header_col, $transpose);
		else if (is_array($data))
			return $this->load_data_frame_from_2darray($varname, $data, $header_row, $header_col, $transpose);
	}
	
	/** helper function called only by @see RFace::load_data_frame() */
	private function load_data_frame_from_string($varname, &$data, $header_row, $header_col, $transpose)
	{
		$tmpnam = $this->new_object_name();
		$this->execute("$tmpnam = \"" . addcslashes($data, '"') . '"');
		$cmd = "$varname = read.table(text=$tmpnam, header=" . ($header_row ? 'TRUE' : 'FALSE') . ($header_col ? ", row.names=1)" : ")");
		$ret_arr = $this->execute($cmd);
		$this->drop_object($tmpnam);

		if (empty($ret_arr) && is_array($ret_arr))
		{
			if ($transpose)
				$this->execute("$varname = as.data.frame(t($varname))");
				/* explanation: t(t(df)) is not equal to df - it loses its row labels, 
				 * cos t() always returns a matrix. 
				 * as.data.frame(t(as.data.frame(t(df)))) DOES equal df . 
				 */ 		
			return true;
		}
		else
			return false;
	}
	
	/** helper function called only by @see load_data_frame */
	private function load_data_frame_from_2darray($varname, &$data, $header_row, $header_col, $transpose)
	{
		if ($transpose)
			return $this->load_data_frame_from_2darray($varname, self::transpose($data), $header_row, $header_col, false);
		// will this actually work, or will it muck up the treatment of header row / header col? (see block of comments later in this func)

		$tmpnam = $this->new_object_name();
		$this->load_matrix($tmpnam, $data);
		$ret_arr = $this->execute("$varname = data.frame($tmpnam)");
		$this->drop_object($tmpnam);
		
		// but in this case how do we handle the header row, header col? see data.frame: you can specify the column/row containing lables
		// so the above call needs modifuying
		// but note that we can't just transmit the keys in the matrix, cos they are (potentially) of a different type to
		// the rest of the matrix
		// so we need ot separate out the names and insert them as separate string vectors.  (??)
		// TODO
		
		return (empty($ret_arr) && is_array($ret_arr));
	}
	
	/** 
	 * Alternative method of object transfer using JSON as the interchange format. 
	 * 
	 * If the JSON library is not available, an error is raised and false returned.
	 * 
	 * The library used is package "rjson". Please read and understand its
	 * documentation!
	 * 
	 * @see RFace::read_via_json()
	 * 
	 * @param string $varname     The name the new R object should have. It is also possible to
	 *                            overwrite an object, as usual in R. No checks are performed on
	 *                            overwriting.
	 * @param mixed $object       The object itself (or, an already-encoded JSON string, see below).
	 * @param bool $already_json  Boolean: if true, the object parameter will be treated
	 *                            as a pre-encoded JSON string. If false (the default) the
	 *                            object parameter is assumed to be a normal object.
	 * @return bool               Boolean: true for success, false for failure.
	 */
	public function load_via_json($varname, $object, $already_json = false)
	{
		if (! $this->json_is_possible())
		{
			$this->error("Package rjson is not available, so the load_via_json() method cannot be used.");
			return false;
		}
		
		if ($already_json)
			$real_obj = $object;
		else
			$real_obj = json_encode($object);
		
		$cmd = "$varname = fromJSON(json_str=\"" . addcslashes($real_obj, '"') . "\")";
	
		$ret_arr = $this->execute($cmd);
		
		return (empty($ret_arr) && is_array($ret_arr));
	}
	
	
	
	
	/*
	 * read methods (move data object from R to PHP)
	 */
	
	/**
	 * Reads the value of an object from R.
	 * 
	 * The basic way of using the method returns an array equivalent to the R vector
	 * or other object specified.
	 * 
	 * @param string $varname   
	 *                   Name of the object to read
	 * @param string $mode      
	 *                   String specifying how to read the object.
	 *                   Options:
	 *                      'object'   -- the default, create a PHP value matching the R object as closely 
	 *                                    as possible. This works for the different types of vector, 
	 *                                    for matrices, for lists, and for data frames. It may 
	 *                                    not work for other object types; any object type not covered
	 *                                    will fallback to 'verbatim' mode.
	 *                      'verbatim' -- create a string containing the verbatim description
	 *                                    of the object from R's output (including whitespace/linebreaks).
	 *                      'solo'     -- for use with one-element vectors: returns it as a single variable,
	 *                                    not as an array, with the appropriate type. If this option is
	 *                                    used for a multi-element vector, you only get the first element.
	 *                                    If it is used for something that doesn't come out as a vector,
	 *                                    then you'll get an error.
	 * @param array|bool $options
	 *                   An associative array of extra options controlling details of the functions behaviour.
	 *                   The array key specifies the option; its value is the value you want to give that option.
	 *                   The following options are available:
	 *                   
	 *                   * "transpose" (Boolean)
	 *   
	 *                      By default, matrices and dataframes are returned as arrays-of-arrays where the
	 *                      inner arrays each represent a column. If "transpose" is set to true, then such
	 *                      objects will be transposed before being returned, so that inner arrays represent
	 *                      rows. "transpose" has no effect if the output is not an array of arrays.
	 * 
	 *                   * "use_labels" (Boolean)
	 * 
	 *                      By default, dataframes are returned as zero-indexed arrays-of-arrays. If 
	 *                      "use_labels" is set to true, then a dataframe will instead be returned as
	 *                      an associative array (of associative arrays). The column and row names of the 
	 *                      R dataframe will be used as the array keys (normally the column names are the
	 *                      outer key, but if "transpose" is also true, then the row names will be the 
	 *                      outer key).
	 * 
	 *                      "use_labels" also affect non-dataframe lists; 
	 * 
	 * @return mixed     A PHP value of the appropriate datatype. In case the object does not exist, then
	 *                   an error is raised, and false is returned.
	 * 
	 * General roadmap:
	 * 
	 * This function covers all the obvious object types (vectors of basic types - bool, int, double, string.
	 * But it needs to have more "special" object types added. Any special type not explicitly covered
	 * should probably be returned as a verbatim string... (only user knows exactly what to do with it)
	 * 
	 * Specific roadmap:
	 * 
	 * This function is known not to work for: vectors with names!
	 * How to fix: test for presence of names, and if they're there, Then (iff use_labels, build an
	 * assoc array, else extract the values without names in the printout)
	 * 
	 */
	public function read($varname, $mode = 'object', $options = false)
	{
		/* fill in empty options in the array with default values */
		if (empty($options))
			$options = $this->default_read_options;
		else
			foreach ($this->default_read_options as $k => $v)
				if (! isset($options[$k]))
					$options[$k] = $v;
		
		if (!$this->object_exists($varname))
		{
			$this->error("Cannot read contents of nonexistent $this->slave_name object $varname!\n");
			return false;
		}
		/* first, request the variable's contents as an array */
		$data = $this->execute($varname);
		
		$verbatim_fallthrough = false;
		
		/* generate object, or solo value, or verbatim text output. */
		switch($mode)
		{
		case 'solo':
		case 'object':
		
			/* for matrices: call this function recursively */
			if ($this->classof($varname) == 'matrix')
			{
				$output = array();
				/* handle transposition */
				if ($options['transpose'])
				{
					/* by rows ... */
					$c_1 = '';
					$c_2 = ',';
					list($n) = $this->dimensions($varname);
				}
				else
				{
					/* by columns ... */
					$c_1 = ',';
					$c_2 = '';
					list(,$n) = $this->dimensions($varname);
				}
				/* note we do not need to "really_transpose" in either case... */

				for ($i = 1 ; $i <= $n ; $i++)
					$output[] = $this->read_execute("{$varname}[$c_1$i$c_2]", "object");
				
				break;
			}
			/* code for non-matrices follows... */
			
			/* 
			 * This switch converts various types of R object to PHP values.
			 * The output is always stored in $output.
			 * If no conversion routine exists, verbatim is used as the fallback.
			 */
			switch ($this->typeof($varname))
			{
			case 'NULL':
				/* PHP's NULL and R's NULL correspond closely. */
				$output = NULL;
				break;
			/* endcase NULL */
			
				
			case 'logical':
				/* vector -> zero-indexed array of booleans. */
				$output = $this->read_vector_output_to_array($data);
				foreach ($output as &$o)
					$o = ($o === 'TRUE');
				break;
			/* endcase logical */
			
				
			case 'integer':
				/* vector -> zero-indexed array of ints. */
				if ($this->classof($varname) == 'factor')
				{
					/* A factor has type==integer but class==factor. 
					 * Convert it to an array of strings, 
					 * even if levels are composed of numbers. */
					
					/* First, get an array of the levels in the factor. */
					$levels = $this->factor_levels($varname);
					/* backwards-sort it, so that if one level = the beginning of another,
					 * the longer one comes first in the array and will be preferred as an alternative 
					 * by PCRE (php.net/pcre:"The matching process tries each alternative in turn, 
					 * from left to right, and the first one that succeeds is used").
					 */
					rsort($levels);
					
					/* now, get rid of the factors in the data output */
					foreach($data as &$d)
						if (0 < preg_match('/^(\d+\s+)?Levels:/', $d))
							unset($d);
					$s = $this->read_vector_output_to_string($data);
					
					$levels_as_alt = implode('|', array_map("preg_quote", $levels));
					
					$n = preg_match_all("/\b($levels_as_alt)\b/", $s, $m, PREG_PATTERN_ORDER);
					
					$output = $m[1];
				}
				else
				{
					/* normal integer vector */
					$output = $this->read_vector_output_to_array($data);
					foreach ($output as &$o)
						$o = (int) $o;
				}
				break;
			/* endcase integer (inc. factor) */
			
				
			case 'double':
				/* vector -> zero-indexed array of floats. */
				$output = $this->read_vector_output_to_array($data);
				foreach ($output as &$o)
					list($o) = sscanf(strtolower($o), '%e');
				break;
			/* endcase double */
			
				
			case 'character':
				/* vector -> zero-indexed array of strings. */
				$data = $this->read_vector_output_to_string($data);
				$output = array();
				for ($i = 0, $n = strlen($data) ; $i < $n ; $i++)
				{
					/* we are outside a value : test for start of value */
					if ($data[$i] == '"')
					{
						/* we are inside a value : scroll to end of value */
						for ($j = $i+1 ; 1 ; $j++)
						{
							if ($data[$j] == '\\')
							{
								/* neither this byte nor the next one is the closing delimiter */
								$j++;
								continue;
							}
							if ($data[$j] == '"')
								break;
						}
						
						/* i = index of opening quote; j = index of closing quote. */
						$output[] = stripcslashes(substr($data, $i+1, $j-($i+1)));
						/* we need stripcslashes() because R char values are printed out with \n, \t etc. */ 
						
						/* set $i to $j, it will then increment and the next loop of the for
						 * will start at the first character after the closing " */
						$i = $j;
					}	
				} 
				break;
			/* endcase character */
			
			
			case 'list':
				/* list -> zero-indexed array of objects of whatever kind (not always the same) */
				/* so, we do this by recursion */
				
				if ($this->classof($varname) == 'data.frame')
				{
					/* dataframes are somewhat different to other types of list... */
					
					if ($options['use_labels'])
					{
						$colnames = $this->read_execute("names($varname)");
						$rownames = $this->read_execute("row.names($varname)");
						if ($options['transpose'])
						{
							foreach($rownames as $r)
							{
								$r_s = addslashes($r);
								$output[$r] = array();
								foreach($colnames as $c)
								{
									$c_s = addslashes($c);
									$output[$r][$c] = $this->read_execute("{$varname}[\"$r_s\",\"$c_s\"]", "solo");
								}
							}
						}
						else
						{
							foreach($colnames as $c)
							{
								$c_s = addslashes($c);
								$output[$c] = array();
								$temp_r = $this->read_execute("{$varname}[[\"$c_s\"]]", "object");
								foreach($rownames as $k => $r)
									$output[$c][$r] = $temp_r[$k];
							}
						}
						break;
					}
					else
					{
						if ($options['transpose'])
							$really_transpose = true;
						/* since we don't need subscripts, we don't break: we just let the inner
						 * arrays get collected by normal list recursion */
					}
						
				} /* endif class is data.frame */

				/* if not broken above, we do this by recursion */
				$temp_r = array();
				$n = $this->sizeof($varname);
				for ($i = 1; $i <= $n; $i++)
					$temp_r[] = $this->read_execute("{$varname}[[$i]]", "object");
				/* temporary array used so we can decide what to do with the key... */
				
				if ($options['use_labels'])
				{
					$labels = $this->read_execute("names($varname)");
					$output = array(); 
					foreach ($labels as $k => $l)
						$output[$l] = $temp_r[$k];
				}
				else
					$output = $temp_r;
				break;
			/* endcase list */
			
			
			// Other data types that might need a case to treat them:
			// case 'special':
			// case 'builtin':
			// case 'complex':
			// case 'raw':
			// case 'environment':
			// case 'S4':
			
			
			/* data types *intentionally* covered by default:
			 *     -- closure (we want a verbatim print of the function's code)
			 */
			default:
				$verbatim_fallthrough = true;
				break;
				
			}	/* endswitch typeof(varname) */
			
			/* final operations in case object / solo:
			 * (1) do transposition if it was flagged in the switch above
			 * (2) check for solo mode, and de-array if found.
			 * (3) fallthrough to verbatim if we didn't have an algorithm!
			 */
			/* transpose if necessary */
			if (isset($really_transpose) && $really_transpose)
				$output = self::transpose($output);	
			if ($mode == 'solo' && is_array($output))
				$output = $output[0];
			if (! $verbatim_fallthrough)
				break;
			/* end of case "object" */
				
		case 'verbatim':
			/* this one is easy */
			$output = implode("\n", $data);
			break;
			
		default:
			return $this->error("Unacceptable object read-mode $mode!\n");
		}
		/* endswitch mode */
		
		
		return $output;
	}
	
	/**
	 * Support function for ->read().
	 * 
	 * Converts an output array from ->execute() into a single string,
	 * with the line-start index numbers removed.
	 */
	private function read_vector_output_to_string(&$array)
	{
		$r = '';
		/* note that if we have something like "character(0)" it will be returned as 
         * the single member of the array. */
		foreach ($array as &$a)
		{
			$expl = explode(']',$a, 2);
			$r .= ' ' . end($expl);
		}
		return trim($r, " \t\r\n");
	}
	
	/**
	 * Support function for ->read().
	 * 
	 * Converts an output array from ->execute() (array of lines)
	 * into a PHP array of strings split on whitespace.
	 * 
	 * Important note: will only work for vectors of booleans or numbers -
	 * not for vectors of strings, which need a different approach.
	 */
	private function read_vector_output_to_array(&$array)
	{
		$s = $this->read_vector_output_to_string($array);
		/* this bit deals with empty vectors: character(0), numeric(0) etc. */
		if ( 0 < preg_match('/^\w+\(0\)$/', $s) )
			return array();
		return preg_split('/\s+/', $s, NULL, PREG_SPLIT_NO_EMPTY);
	}
	

	/**
	 * Like ->read(), but should be passed an R command instead of a variable name;
	 * that command is executed, and whatever value it outputs is returned.
	 * 
	 * The return value is a PHP object of the appropriate type, depending on
	 * what kind of object the execution the command outputs.
	 * 
	 * Note that the command must be one that evaluates to something 
	 * (as its result will be assigned to a temporary variable).
	 * 
	 * @see RFace::read()
	 * 
	 * @param  string $command  An executable R command that evaluates to something.
	 * @param  string $mode     See the like parameter for the ->read() method.
	 * @param  array  $options  See the like parameter for the ->read() method. 
	 * @return mixed            PHP object of the appropriate type
	 */ 
	public function read_execute($command, $mode = 'object', $options = false)
	{
		$temp_obj = $this->new_object_name();
		if (false === $this->execute("$temp_obj = $command"))
			return false;
		$return_me = $this->read($temp_obj, $mode, $options);
		$this->drop_object($temp_obj);
		return $return_me;
	}
	
	
	/** 
	 * Alternative method of object transfer using JSON as the interchange format. 
	 * 
	 * If the JSON library is not available, an error is raised and false returned.
	 * 
	 * The library used is package "rjson". Please read and understand its
	 * documentation!
	 * 
	 * @see RFace::load_via_json()
	 * @param  string  $varname  The variable to read.
	 * @return mixed             A PHP value representing whatever was read; Boolean false in 
	 *                           case of error (you can check for errors in the RFace object to
	 *                           distinguish this from a simplex Boolean false as the intended
	 *                           transfer object).
	 */
	public function read_via_json($varname)
	{
		if ( ! $this->error_if_not_object_exists($varname, "read_via_json"))
			return false;
		
		if (! $this->json_is_possible())
		{
			$this->error("Package rjson is not available, so the RFace::read_via_json() method cannot be used.");
			return false;
		}
		
		return json_decode($this->read_execute("toJSON($varname)"));
	}
	

	
	
	/*
	 * Object manipulation methods 
	 */
	
	
	/**
	 * Gets an array of object-names in the active R workspace, as strings.
	 * 
	 * If there are no objects, returns an empty array. 
	 */
	public function list_objects()
	{
		if ($this->object_list_cache !== false)
			return $this->object_list_cache;
		
		$rawtext = implode(' ', $this->execute("ls()"));
		
		if ($rawtext == 'character(0)')
			/* no objects */
			return array();

		if (1 > preg_match_all('/"(\S+)"/', $rawtext, $matches, PREG_PATTERN_ORDER))
			$this->error('Error parsing output from ls()!', __LINE__);
		
		$this->object_list_cache = $matches[1];
		return $this->object_list_cache;
	}
	
	/**
	 * Checks whether an object of the given name exists in the active R workspace.
	 */
	public function object_exists($obj)
	{
		return in_array($obj, $this->list_objects());
	}
	
	/**
	 * Internal method raising an Interface error if an object does not exist.
	 * 
	 * Call this function before doing something that would cause a problem 
	 * if the object does not exist, as follows:
	 * 
	 * if (!$this->error_if_not_object_exists($obj, "name_of_calling_method"))
	 *     return false;
	 * 
	 * Thus, we pre-empt causing an error that would cause trouble later.
	 */
	protected function error_if_not_object_exists($obj, $caller = false)
	{
		if ($this->object_exists($obj))
			return true;
		else
			return $this->error(
					($caller ? "Cannot call $caller()" : "Function called on") . " on nonexistent $this->slave_name object $obj!\n"
					);
	}
	
	/**
	 * Gets the type of an object in the active R workspace.
	 * 
	 * Returns a string indicating the type (same strings as in R)
	 * or false if there was an error. 
	 */
	public function typeof($obj)
	{
		if ( ! $this->error_if_not_object_exists($obj, "typeof"))
			return false;
		else
		{
			/* we know it will just be a "[1] ..." printout, so we can shortcut */
			list($rawtext) = $this->execute("typeof($obj)");
			return trim(substr($rawtext, 4),'"');
		}
	}
	
	/**
	 * Gets the classname of an object in the active R workspace.
	 * 
	 * Returns a string with the classname, or false if there was an error.
	 */
	public function classof($obj)
	{
		if ( ! $this->error_if_not_object_exists($obj, "classof"))
			return false;
		else
		{
			list($rawtext) = $this->execute("class($obj)");
			return trim(substr($rawtext, 4),'"');
		}
	}
	
	/** Convenience alias for "classof" for those who prefer the PHP function name */
	public function get_class($obj) { return $this->classof($obj); }

	/**
	 * Gets the size of an object (number of objects it contains)
	 * in the active R workspace; equivalent to the R function length().
	 * 
	 * Returns an integer indicating the count of objects
	 * or false if there was an error.
	 */
	public function sizeof($obj)
	{
		if ( ! $this->error_if_not_object_exists($obj, "sizeof"))
			return false;
		else
		{
			list($rawtext) = $this->execute("length($obj)");
			return (int) trim(substr($rawtext, 4));
		}
	}
	
	/** Convenience alias for "sizeof" for those who prefer PHP's alternative terminology! */
	public function count($obj) { return $this->sizeof($obj); }
	
	/** Convenience alias for "sizeof" for those who prefer R to PHP terminology! */
	public function length($obj) { return $this->sizeof($obj); }
	


	/**
	 * Get the length of each dimension in a multi-dimensional object
	 * such as a matrix or data frame.
	 * 
	 * The return value is an array of dimension lengths. In a 2D object
	 * such as a matrix or data frame, [0] will contain the number of
	 * rows, and [1] the number of columns. 
	 * 
	 * If passed a one-dimensional object, this function will behave
	 * like ->sizeof() (unlike R's native function dim() which returns
	 * NULL in such a case) except that the return is a one-member
	 * array rather than an uncontained integer.
	 * 
	 * @return array An array of dimension lengths for the specified object.
	 */
	public function dimensions($obj)
	{
		if ( ! $this->error_if_not_object_exists($obj, "dimensions"))
			return false;

		$dims = $this->read_execute("dim($obj)");

		/* account for one-D objects */
		if(is_null($dims))
			$dims = array($this->sizeof($obj));

		return $dims;
	}
	
	public function factor_levels($obj)
	{
		if ( ! $this->error_if_not_object_exists($obj, "factor_levels"))
			return false;
// TODO seems incomplete?
	}
	
	/**
	 * Deletes an object, checking first if it exists.
	 * 
	 * If it doesn't exist, no error is raised.
	 */
	public function drop_object($obj)
	{
		if ($this->object_exists($obj))
			$this->execute("rm($obj)");
	}
	
	/**
	 * Returns a string, guaranteed to be a valid R object name which does not 
	 * currently exist in the active R workspace.
	 */
	public function new_object_name()
	{
		/* names to avoid: anything this function has generated, plus
		 * all existing objects (there will be overlap here but maybe not TOTAL overlap) */ 
		static $dont_repeat_me = [];
		
		$names = array_unique(array_merge($dont_repeat_me, $this->list_objects()));
		
		/* 'rfo' for "RFace Object" */
		for ($new = 'rfo'; true ; $new .= chr(random_int(0x41, 0x5a)) )
		{
			for ($i = 0x41; $i <= 0x5a; $i++)
			{
				$curr = $new . chr($i);
				if (!in_array($curr, $names))
					return $dont_repeat_me[] = $curr;
			}
		}
// TODO, use hash keys for names in the above instead of hash valeus.
		
		/* sanity check, should not be reached */
		return $this->error("New object name could not be generated.\n");
	}
	
	
	/*
	 * Graph / chart creation methods
	 */
	
	
	/**
	 * Saves the chart created by the given command to the specified filename.
	 * 
	 * To specify a chart that needs multiple commands to create, either (a)
	 * use execute() for all but the final command; or, specify multiple commands
	 * within this method's second argument by separating them using semi-colons.  
	 * 
	 * The type of the file is determined automagically by the file extension
	 * of the file path provided. 
	 */
	public function make_chart($filename, $chart_command)
	{
		if (is_dir($filename))
			$filename = rtrim($filename, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DEFAULT_CHART_FILENAME.'.'.self::DEFAULT_CHART_FILETYPE;
		
		/* auto-guess the function to use, based on the filename extension. */
		switch (substr($filename, -4))
		{
		case '.jpg':
			$func = 'jpeg';
			break;
		case '.bmp':
		case '.pdf':
		case '.png':
			$func = substr($filename, -3);
			break;
		default:
			switch (substr($filename, -5))
			{
			case '.tiff':
			case '.jpeg':
				$func = substr($filename, -4);
				break;
			default:
				$func = self::DEFAULT_CHART_FILETYPE;
				return;
			}
			break;
		}
		
		/* set the graphics output to save to this file */
		$this->execute("$func(file = \"$filename\")");
		
		/* create our chart... */
		$this->execute($chart_command);
		
		/* reset the graphics output */
		$this->execute("dev.off()");
	}
	
	
	/*
	 * Workspace control methods 
	 */
	
	
	/**
	 * Save the R workspace to a specified location.
	 * 
	 * If $path is a directory, the workspace is saved as the file .Rdata in that directory.
	 * 
	 * Otherwise, $path is assumed to be a target filename and the workspace saved in
	 * that location.
	 * 
	 * The default value for the path is PHP's current working directory.
	 * 
	 * Returns true for success, false for failure.
	 */
	public function save_workspace($path = false)
	{
		if ($path == false)
			$path = getcwd();
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		if (is_dir($path))
			$r = $this->execute('save.image(file="' . $path . DIRECTORY_SEPARATOR . self::DEFAULT_WORKSPACE_FILENAME . '")');
		else if (is_writable(dirname($path)))
			$r = $this->execute("save.image(file=\"$path\")");
		else
			return false;

		/* successful save will have returned empty array. */
		return (empty($r) && is_array($r));
	}

	/**
	 * Load an R workspace from a specified location.
	 * 
	 * If $path is a directory, this function loads the file .Rdata in that directory.
	 * 
	 * Otherwise, $path is treated as a filename (the method will add the .RData extension
	 * by default if the filename it is passed does not exist).
	 * 
	 * The default value for the path is the current working directory.
	 * 
	 * Returns true for success, false for failure.
	 */
	public function load_workspace($path = '.')
	{		
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		if (is_dir($path))
		{
			if (is_file("$path/.RData"))
				$r = $this->execute("load(file\"=$path/.RData\")");
			else
				$this->error("ERROR: can't load workspace, no file found at $path/.Rdata!");
		}
		else if (is_file($path))
			$r = $this->execute("load(file\"=$path\")");
		else if (is_file($path.'.RData'))
			$r = $this->execute("load(file\"=$path.RData\")");
		else
			$this->error("ERROR: can't load workspace, no file found at $path!");
			
		/* successful load will have returned empty array. */
		return (empty($r) && is_array($r));
	}
	
	
	/** 
	 * Loads the requested R library package (if it is available).
	 */
	public function load_library($lib)
	{
		if (!$this->library_is_available($lib))
            return false;
		if (false !== $this->execute("library($lib)"))
		{
			/* sometimes, successfully loading a library scribbles all over stderr;
			 * so having loaded a library, clean out the error pipe. */
			$this->read_error_pipe();
			return true;
		}
		return false;
	}
	
	/** 
	 * Checks whether the specified R library package is available.
	 */
	public function library_is_available($lib)
	{
		return in_array($lib, $this->list_libraries());
	}
	
	/**
	 * Returns an array of available R library packages.
	 */
	public function list_libraries()
	{
		return $this->read_execute(".packages(all.available = TRUE)");
	}
	
	
	/**
	 * Checks whether or not it is possible to use JSON (and if it is,
	 * loads the relevant library package). 
	 */
	public function json_is_possible()
	{
		if (!is_null($this->json_is_possible_cache))
			return $this->json_is_possible_cache;
			
		if ($this->library_is_available("rjson"))
		{
			$this->load_library("rjson");
			
			$this->json_is_possible_cache = true;
			return true;	
		}
		else
		{
			$this->json_is_possible_cache = false;
			return false;
		}
	}
	
	/*
	 * ==============
	 * Static methods: utilities for both internal and external use.
	 * ==============
	 * 
	 * (mostly variable manipulation functions)
	 * 
	 */
	
	
	
	/**
	 * Deduces whether an (incoming PHP) array has a single "type" or not.
	 * 
	 * Returns a VALTYPE class constant describing the type: this is "string" if
	 * every value in the array is a string, "number" if every value
	 * is either an int or a float, "mixed" if a value of the type
	 * contrary to the initially established type was detected, "undefined"
	 * if a value that is neither string nor number was detected.
	 * (These are the strings returned by RFace::translate_valtype_to_string();
	 * in reality the constants are integers.)
	 * 
	 * The string "array" can also be returned, if every component of
	 * the array is itself an array. NB: in this case there is no recursive checking.
	 * 
	 * Note: "mixed" and "undefined" are error values, and if they are
	 * returned, it says nothing about the presence of errors of the
	 * *other* type further down the array.
	 * 
	 * These strings are class constants (VALTYPE_). They may be replaced by 
	 * integers in future. 
	 */
	private static function deduce_array_type($array)
	{
		$type = self::VALTYPE_UNKNOWN;
		
		foreach ($array as $a)
		{
			/* get the type */
			if ( is_int($a) || is_float($a) )
				$currtype = self::VALTYPE_NUMBER;
			else if ( is_string($a) )
				$currtype = self::VALTYPE_STRING;
			else if (is_array($a))
				$currtype = self::VALTYPE_ARRAY;
			else
				return self::VALTYPE_UNDEF;
			
			/* check the type against the type established so far */
			if (self::VALTYPE_UNKNOWN == $type)
				$type = $currtype;
			else
				if ($type != $currtype)
					return self::VALTYPE_MIXED;
		}
		return $type;
	}
	
	/**
	 * Gets a one-word string describing a VALTYPE class constant.
	 * 
	 * @param  int     $type    A VALTYPE_ class constant referring to a type. 
	 * @return string           This string is equiv. to what the type flag was before use of integer constants.
	 */
	public static function translate_valtype_to_string($type)
	{
		static $map = array(
				self::VALTYPE_DEDUCE  => 'deduce', /* this shouldn't be passed in, but just in case */
				self::VALTYPE_UNKNOWN => 'UNKNOWN',
				self::VALTYPE_MIXED   => 'mixed',
				self::VALTYPE_NUMBER  => 'number',
				self::VALTYPE_STRING  => 'string',
				self::VALTYPE_ARRAY   => 'array',
				self::VALTYPE_UNDEF   => 'undefined'
		);
		if (isset($map[$type]))
			return $map[$type];
		else 
			return false;
	}
	

	/**
	 * Gets the numeric value of a string, regardless of whether it
	 * represents a float or an int.
	 * 
	 * More reliable when building arrays of numbers than a typecast!
	 * (Because a typecast would have to be to either int or float, but
	 * using PHP's context-based type juggling covers either.)
	 */
	public static function num($string)
	{
		return 1 * $string;
	}

	/** 
	 * Transposes a two-dimensional array - rows become columns 
	 * and columns become rows.
	 * 
	 * This is the "canonical" way to transpose an array in PHP,
	 * according to the manual; included here as a utility function,
	 * because it is sometimes convenient to do transposition at 
	 * "this end" of the pipe, rather than use R's t() function. 
	 */
	public static function transpose($array)
	{
		array_unshift($array, NULL);
		return call_user_func_array('array_map', $array);
	}

	/* end of class RFace */
}



