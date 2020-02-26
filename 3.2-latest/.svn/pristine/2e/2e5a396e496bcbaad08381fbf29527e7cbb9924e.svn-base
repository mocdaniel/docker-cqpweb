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
 * This file contains the class definition for the API controller,
 * plus the associated constants.
 * 
 */




/*
 * ======================
 * API FUNCTION CONSTANTS
 * ======================
 * 
 * These constants are the ASCII strings used in the api.php requests.
 * 
 * RULE: All function names begin with a verb.
 * 
 * Note that the header and footer to this file section should not be changed 
 * because they are used to detect the part that needs auto-transfer
 * (cf. code-update-api-client-consts.php). 
 */


/** Get the version of this copy of CQPweb. */
const API_FUNC_GET_VERSION                     = 'get_version';

/** Get the version of the CWB core that is in use. */
const API_FUNC_GET_CWB_VERSION                 = 'get_cwb_version';

/** Get a list of available API functions. */
const API_FUNC_LIST_API_FUNCTIONS              = 'list_api_functions';

/** Get information about what a particular API error code indicates. */
const API_FUNC_GET_API_ERROR_INFO              = 'get_api_error_info';

/** Log in to a CQPweb server */
const API_FUNC_LOG_IN                          = 'log_in';

/** Log out of the CQPweb server */
const API_FUNC_LOG_OUT                         = 'log_out';

/** Run a query in the current "environment" corpus. */
const API_FUNC_DO_QUERY                        = 'do_query'; //UNFINISHED

// TODO. DO this one first, as a proof-of-concept.
/** Get a frequency list for a specified annotation. */
const API_FUNC_FETCH_FREQLIST                  = 'fetch_freqlist';
/* print out a concordance for an existing query */
// const API_FUNC_FETCH_CONCORDANCE = 'concordance';
// const API_FUNC_FETCH_EXTENDED_CONTEXT = 'extended_context';
// const API_FUNC_FETCH_COLLOCATIONS = '';
// const API_FUNC_CREATE_SUBCORPUS = '';
// const API_FUNC_FETCH_KEYWORDS = '';
// const API_FUNC_FETCH_KEYCLOUD = '';
// const API_FUNC_FETCH_CONCORDANCE_BREAKDOWN = 'fetch_concordance_breakdown';
// const API_FUNC_FETCH_DISTRIBUTION = '';
// const API_FUNC_FETCH_DISPERSION = '';
const API_FUNC_FETCH_QUERY_HISTORY = 'fetch_query_history';
// const API_FUNC_GET_USER_SETTING = 'get_user_setting';
// const API_FUNC_SET_USER_SETTING = 'set_user_setting';
// const API_FUNC_SAVE_QUERY = 'save_query';
// const API_FUNC_LIST_SAVED_QUERIES = 'list_saved_queries';
// const API_FUNC_LIST_SUBCORPORA = 'list_subcorpora';
// const API_FUNC_DELETE_SAVED_QUERY = 'delete_saved_query';
// const API_FUNC_DELETE_SUBCORPUS = 'delete_subcorpus';
// const API_FUNC_DO_SUBCORPUS_FREQLIST = 'do_subcorpus_freqlist';
// const API_FUNC_FETCH_SUBCORPUS_FREQLIST = 'fetch_subcorpus_freqlist';
// const API_FUNC_THIN_QUERY = 'thin_query';
// const API_FUNC_ = '';
// const API_FUNC_ = '';
		/*
		 * function ideas:
		 * 
// 		case 'concordance': API_FUNC_CONCORDANCE
// 			/* print out a concordance for an existing query */// interface to concordance download.
			
// 		case 'collocationTable':
// 			/* get a collocation table for an existing query */

// 		case 'collocationSoloInfo':
// 			/* interface to collocation solo mode */

// 		case 'collocationThin':
// 			/* postprocess the query according to a specified collocate */

// 		case 'distributionTable':

// 		case 'frequencyBreakdownTable':
		
			// dump, 
			// tabulate etc....
		 // postprocesses - each one should return the new query name.
		 // interrogate_cache - check whether a cached query still exists.
// END LIST OF FUNC IDEAS





/*
 * ===================
 * API ERROR CONSTANTS
 * ===================
 */


/* 0-9: API problems */

/** No error. */
const API_ERR_NONE               = 0;

/** The API system is not available on this CQPweb server. */
const API_ERR_SYS_UNAVAILABLE    = 1; // not implemented
/** CQPweb is switched off. */
const API_ERR_SWITCHED_OFF       = 2;// not implemented
/** Function parameter (f) not specified. */
const API_ERR_NO_FUNCTION        = 3;
/** Nonexistent function called. */
const API_ERR_BAD_FUNCTION       = 4;
/** Non-optional argument was missing. */
const API_ERR_MISSING_ARG        = 5;
/** Unrecognised argument name */
const API_ERR_UNKNOWN_ARG        = 6;
/** Invalid value supplied as argument. */
const API_ERR_INVALID_ARG        = 7;

/* 10-19: user errors. */

/** The login function was called, but failed (bad username/incorrect password) */
const API_ERR_LOGIN_FAIL         = 11;
/** The remote user is not presently logged in. */
const API_ERR_LOGGED_OUT         = 12;
/** The user has no access to the specified corpus. */
const API_ERR_NO_ACCESS          = 13;
/** The requested action would exceed the user's privileges in some other way (message array may say more) */
const API_ERR_EXCEEDS_PRIV       = 14;

/* 20-29: errors for queries. */

/** CQP reported syntax error. */
const API_ERR_CQP_SYNTAX         = 21;
/** Error parsing CEQL */
const API_ERR_CEQL_SYNTAX        = 22;
/** Error in query scope: invalid restriction */
const API_ERR_SCOPE_RESTRICT     = 23;
/** Error in query scope: subcorpus not found */
const API_ERR_SCOPE_SUBCORPUS    = 24;

/* ... */

/** Only a textual description of the error is available. It may, in fact, be one of the errors
 *  which has a constant; but we weren't able to get the const, for whatever reason. */ 
const API_ERR_MESSAGES_ONLY      = 1022;

/** An error for which no constant exists. */
const API_ERR_OTHER              = 1023;


/*
 * ====================
 * END OF API CONSTANTS
 * ====================
 */



/**
 * Object that contains and manages data for an API response.
 * 
 * There will only be one instance, which is a member of the global $Config object.
 * 
 * The static functions are used to set things up before the creation of this object 
 * during environment setup.
 */
class ApiController
{
	/** Array whose keys = all the valid function strings. The value is "true" if it is a no-script
	 * "little" function; or, if the function needs us to include a script, the value is the string
	 * name of the file in the lib directory to include.  */ 
	const VALID_FUNCTIONS = array(
			API_FUNC_GET_VERSION             => true,
			API_FUNC_GET_CWB_VERSION         => true,
			API_FUNC_LIST_API_FUNCTIONS      => true,
			API_FUNC_GET_API_ERROR_INFO      => true,
			API_FUNC_LOG_IN                  => 'useracct-act.php',
			API_FUNC_LOG_OUT                 => 'useracct-act.php',
			API_FUNC_DO_QUERY                => 'concordance-ui.php',
			API_FUNC_FETCH_FREQLIST          => 'freqlist-ui.php',
			API_FUNC_FETCH_QUERY_HISTORY     => true,
			
			
			// more here, as we add more to the switch
		
		);
	
	/** Array containing the parameter maps. It links the parameter names in the API to the GET indexes
	 *  they need to be placed in when the usual script is called. When extra GET params are needed
	 *  which the API caller does not supply, these should be specified in the mapper at the key 
	 *  '~~additional', whose value is itself an array mapping GET-keys to the values to set them to.  */
	private static $function_parameter_maps = array(
			
		);
//TODO a good idea to ahve this? Or just embed directly inot the switch in prepare_arguments?
	
	
	/** Array with standard error messages for different error constants. */
	const STANDARD_MESSAGES = array(
			API_ERR_NONE               => 'All OK',
			API_ERR_SYS_UNAVAILABLE    => 'The API system is not available on this CQPweb server',
			API_ERR_SWITCHED_OFF       => 'CQPweb is switched off',
			API_ERR_NO_FUNCTION        => 'Function parameter (f) not specified',
			API_ERR_BAD_FUNCTION       => 'The function that was called does not exist in the API',
			API_ERR_MISSING_ARG        => 'A non-optional argument was missing',
			API_ERR_UNKNOWN_ARG        => 'One of the arguments supplied was not recognised',
			API_ERR_INVALID_ARG        => 'Invalid value supplied for one or more arguments',
			API_ERR_LOGIN_FAIL         => 'The login function was called, but failed (bad username/incorrect password)',
			API_ERR_LOGGED_OUT         => 'The remote user is not presently logged in',
			API_ERR_NO_ACCESS          => 'The logged-in user has no access to the specified corpus',
			API_ERR_EXCEEDS_PRIV       => 'Privileges to perform the requested action have not been granted to the logged-in user',
			API_ERR_CQP_SYNTAX         => 'CQP reported a syntax error in the query',
			API_ERR_CEQL_SYNTAX        => 'Error parsing CEQL',
			API_ERR_SCOPE_RESTRICT     => 'Error in query scope: invalid restriction ',
			API_ERR_SCOPE_SUBCORPUS    => 'Error in query scope: subcorpus not found',
			API_ERR_MESSAGES_ONLY      => 'No error code available; please refer to error messages',   /* prob not needed! */
			API_ERR_OTHER              => 'Unknown type of error (no error code or message)'
		);
	
	
	/* strings for status dispatch */
	const STATUS_OK = 'ok';
	const STATUS_ERROR = 'error';
	
	
	/** String with the name of the API function to run. */
	public $function = NULL;
	
	/** array of arguments. These will also be in "get" where
	private $arguments = NULL;
	
	/**	Variable of any kind: return value from the API function; 
	 *  API documentation will say what it is. */
	private $response_content = NULL;
	
	/** Object status variable (bool). */
	private $status_ok      = true;
	private $errno          = API_ERR_NONE;
	private $error_messages = [];
	
	private $http_request_copy;

	private $user_login_token;
	
	
	
	/**
	 * Set up object for Api response.
	 * 
	 * @param string  $func      API function to run. If empty, object will look for an 'f' parameter in the $params array.
	 * @param array   $params    HTTP request parameters (usually from $_POST or $_GET).
	 */
	public function __construct($func = NULL, $params = [])
	{
		global $Config; 
		
		$this->http_request_copy = $params;
		
		/* allow the cookie token to be provided as an extra fucntion paramter, rather than in an actual cookie. */
		if (isset($this->http_request_copy['user_login_token']))
		{
			$_COOKIE[$Config->cqpweb_cookie_name] = $this->http_request_copy['user_login_token'];
			unset($this->http_request_copy['user_login_token']);
		}
		/* and prepare to send it back again in the response. */
		if (isset($_COOKIE[$Config->cqpweb_cookie_name]))
		{
			$this->user_login_token = $_COOKIE[$Config->cqpweb_cookie_name];
		}
		
		if (empty($func))
			$this->set_function(isset($this->http_request_copy['f']) ? $this->http_request_copy['f'] : NULL);
		else
			$this->set_function($func);
		
		// TODO, should we turn on output buffering, for any text / HTML that leaks past API checks?
		// and clear the ob before we dispatch (or static dispatch)
	}
	
	
	/** Set the user's login token for the return object. */
	public function set_outgoing_cookie_token($token)
	{
		$this->user_login_token = $token;
	}
	
	
	/**
	 * Check that the arguments needed by the API function are available, AND stick them into the correct place in GET.
	 * (Separated from the constructor because caller might opt to empty out GET before calling this fucntion to re-fill it.)
	 * 
	 * @return bool   Is everything OK?
	 */
	public function prepare_arguments()
	{
		switch ($this->function)
		{
		case NULL:
			/* func not set yet, so can't check params. */
			return ;
		
		
		/* functions with no arguments. */
		
		case API_FUNC_GET_CWB_VERSION:
		case API_FUNC_LIST_API_FUNCTIONS:
			break;
			
			
		case API_FUNC_GET_API_ERROR_INFO:
			$this->restore_param_to_GET('error_code', true);
			break;
			
			
		case API_FUNC_LOG_IN:
			$_GET['userAction'] = 'userLogin';
			$this->restore_param_to_GET('persist', false);
			$this->restore_param_to_GET('username', true);
			$this->restore_param_to_GET('password', true);
			break;
			
			
		case API_FUNC_LOG_OUT:
			$_GET['userAction'] = 'userLogin';
			break;
			
			
		case API_FUNC_DO_QUERY:
			// TODO :  data, mode, scope
			
			break;
			
			
		case API_FUNC_FETCH_FREQLIST:
			$this->set_param_to_GET('flTable'        , 'subcorpus'  , false, '__entire_corpus');
			$this->set_param_to_GET('flAtt'          , 'annotation' , false, 'word');
			$this->set_param_to_GET('flFilterType'   , 'filter_type', false, 'begin');
			$this->set_param_to_GET('flFilterString' , 'filter'     , false);
			$this->set_param_to_GET('flFreqLimit1'   , 'freq_max'   , false);
			$this->set_param_to_GET('flFreqLimit2'   , 'freq_min'   , false);
			$this->set_param_to_GET('flOPrder'       , 'sort'       , false, 'desc');
			break;
			
			
		case API_FUNC_FETCH_QUERY_HISTORY:
			$this->restore_param_to_GET('limit', false);
			break;
			
			/* ... additional functions will be added here. */
			
		}
		
		/* if anything in the switch error'ed out, the return will be false. */
		return $this->status_ok;
	}

	
	
	
	/**
	 * Restore a paramater to the global $_GET array so that it can be accessed
	 * by  a script that expects it to be there. 
	 * 
	 * For the simple case where we just need to check something is present,
	 * under the same array index incoming as it needs to have in GET.
	 * 
	 * @param  string  $key          Key into GET array of the patameter in question.
	 * @param  bool    $compulsory   Controls what happens if the internal copy  of the HTTP request
	 *                               does not have a paramater of the name given.
	 *                               If true, an error is raised. If false (as by default), 
	 *                               nothing happens.
	 */
	private function restore_param_to_GET($key, $compulsory = false)
	{
		if (isset($this->http_request_copy[$key]))
			$_GET[$key] = $this->http_request_copy[$key];
		else if ($compulsory)
			$this->raise_error(API_ERR_MISSING_ARG, "Compulsory argument ''$key'' was not present. ");
		/* else no need to do anything. */
	}
	
	/**
	 * Transfer a parameter value to a secified slot in the global $_GET array 
	 * so that it can be accessed by a script that expects it to be there.
	 * 
	 * Covers more complex cases than restore_param_to_GET().
	 *  
	 * @param string  $new_GET_key  The GET key where the param will be placed.
	 * @param string  $orig_param   The name of the param in the API. 
	 * @param bool    $compulsory   Controls what happens if the internal copy 
	 *                              of the HTTP request does not have a parameter of the name given.
	 *                              If true, an error is raised. 
	 *                              If false, the default (if given) is used. False is the default.  
	 * @param mixed   $default      Value to use for this parameter if it is not specified and 
	 *                              is non-compulsory.
	 */
	private function set_param_to_GET($new_GET_key, $orig_param, $compulsory = false, $default = NULL)
	{
		if (isset($this->http_request_copy[$orig_param]))
			/* we've got it! */
			$_GET[$new_GET_key] = $this->http_request_copy[$orig_param];
		
		else if ($compulsory)
			/* this is a compulsory arg, and it's missing */
			$this->raise_error(API_ERR_MISSING_ARG, "Compulsory argument ''$orig_param'' was not present. ");
		
		else if (!is_null($default))
			/* this is an optional arg, and we don't have it, so supply the ddefault if there is one. */ 
			$_GET[$new_GET_key] = $default;
			
		/* else no need to do anything. */
	}
	
	
	/**
	 * Set the function to which the system is responding. 
	 * 
	 * @param  string  $f   Name of API function.
	 * @return bool         False if the string $f was not a real function; otherwise, true.
	 */
	private function set_function($f)
	{
		/* API function names are case insensitive. */ 
		$f = strtolower($f);
		
		if (!self::is_real_function($f))
			return $this->raise_known_error(API_ERR_BAD_FUNCTION);

		$this->function = $f;
		return true;
	}


	
	/**
	 * Set the content of the API response.
	 * 
	 * @param mixed $content     API response can be almost any kind of value: object, array, simplex.
	 *                           Resources, or object/array containing array/resources, can't be
	 *                           meaningfully serialised, it should be noted.
	 */
	public function set_response_content($content)
	{
		if ($this->status_ok)
			$this->response_content = $content;
	}
	
	
	/**
	 * Add another value to the API response content. 
	 * 
	 * Makes the response content be an array, if it wasn't already. 
	 *
	 * If this is the first time content has been added, it's directly equivalent to 
	 * calling ApiController::set_response_content().
	 * 
	 * @see   ApiController::set_response_content()
	 * @param mixed $more_content     Extra value for the API response array.
	 *                                If this is an array, its members are added sequentially to the response.
	 */
	public function append_to_response_content($more_content)
	{
		if (!$this->status_ok)
			return;
		
		if (is_null($this->response_content))
		{
			$this->response_content = $more_content;
			return;
		}
		
		if (!is_array($this->response_content))
			$this->response_content = [$this->response_content];
			/* the content we already have is definitely now in an array of 1+ members */
		
		/* append new values to the array: either by merging, if we got an array; or by pushing a simplex value. */ 
		if (is_array($more_content))
			$this->response_content = array_merge($this->response_content, $more_content);
		else
			$this->response_content[] = $more_content;
	}
	
	
	/**
	 * Put the object into error state, using just the error code;
	 * a message will be supplied internally.
	 * 
	 * @param  int  $err    Error code.
	 * @return bool         Always false.
	 */
	public function raise_known_error($err)
	{
		if (isset(self::STANDARD_MESSAGES[$err]))
			return $this->raise_error($err, self::STANDARD_MESSAGES[$err]);
		else
			return $this->raise_error($err, "(Error type # $err for which no standard message has been defined.)");
	}
	
	
	/**
	 * Put the object into error state, without any error code being set;
	 * API_ERR_MESSAGE_ONLY will be supplied as the error called.
	 * 
	 * Designed to be called by exiterror(). 
	 * 
	 * @param  string|array $msg    Text of error message. Or, array of such.
	 * @return bool                 Always false.
	 */
	public function raise_described_error($msg)
	{
		return $this->raise_error(API_ERR_MESSAGE_ONLY, $msg);
	}
	
	
	/**
	 * Put the object into error state.
	 * 
	 * Allows a more specific error message to be supplied 
	 * (the text of the error message is not determined by the error code).  
	 * 
	 * @param  int          $err    Error code.
	 * @param  string|array $msg    Text of error message. Or, array of such.
	 *                              If unspecified, this works the same as raise_known_error.
	 * @return bool                 Always false.
	 */
	public function raise_error($err, $msg = NULL)
	{
		if (is_null($msg))
			return $this->raise_known_error($err);
		
		$this->status_ok = false;
		$this->errno = $err;
		
		if (is_array($msg))
			$this->error_messages = array_merge($msg,$this->error_messages);
		else 
		{
			if (empty($this->error_messages))
				$this->error_messages = [$msg];
			else
				array_unshift($this->error_messages, $msg);
		}
		return false;
	}
	
	
	
	/**
	 * Sends the API response to the browser as a JSON object.
	 */
	public function dispatch()
	{
		$o = new stdClass();
		
		$o->status = self::STATUS_OK;
		$o->errno  = $this->errno;

		if ($this->status_ok)
			$o->content  = $this->response_content;
		else
		{
			$o->status   = 'error';
			$o->errors   = empty($this->error_messages) ? ['Unclassified error.'] : $this->error_messages ;
			
			global $User;
			if (is_object($User) && $User->is_admin())
				foreach(debug_backtrace() as $bt)
					$o->errors[] = print_r($bt, true);
		}
		
		if (isset($this->user_login_token))
			$o->user_login_token = $this->user_login_token;
		/* when the cookie was first emitted, setcookie was called. */
		
		header('Content-Type: application/json');
		echo json_encode($o);
	}
	
	
	/**
	 * Gets the filename of the script to include for the specified function.
	 * 
	 * All strings are relative to the lib folder.
	 * 
	 * @param  string       $function     One of the API_INC string functions.
	 * @return string|bool                Either a script name (e.g. concordance.inc.php), or else a Boolean;
	 *                                    true indicates that the function exists but has no script, false
	 *                                    that the function does not exist.
	 */
	public static function get_script_for_function($function)
	{
		if (!isset(self::VALID_FUNCTIONS[$function]))
			return false;
		return self::VALID_FUNCTIONS[$function];
	}
	
	
	/** 
	 * Checks whether a string is a real API function.
	 * 
	 * @param  string $function   String containing the function name to check.
	 * @return bool               Is it real?
	 */
	public static function is_real_function($function)
	{
		return isset(self::VALID_FUNCTIONS[$function]);
	}
	
	/**
	 * Returns an array of names of available API functions.
	 * 
	 * @return array     Flat array with meaningless numeric keys.
	 */
	public static function list_api_functions()
	{
		return array_keys(self::VALID_FUNCTIONS);
	}
	

	/**
	 * Dispatch a result via JSON even if there is no API object available.
	 * 
	 * @param mixed $content    Data to send back: boolean, or number, or string, or object, or array.
	 * @param int   $error      If this is not API_ERR_NONE, an error object is dispatched 
	 *                          (and $content is ignored).
	 */
	public static function static_dispatch($content, $error = API_ERR_NONE)
	{
		$o           = new stdClass();
		$o->status   = 'ok';
		$o->errno    = $error;
		
		if (API_ERR_NONE != $error)
		{
			$o->status = 'error';
			$e         = self::get_standard_error_message($error);
			$o->errors = empty($e) ? ['Unknown error'] : [ $e ] ;
		}
		else
			$o->content  = $content;

		header('Content-Type: application/json');
		echo json_encode($o);
	}
	
	/**
	 * Get the standard error description for an API_ERR constant.
	 * Returns NULL if there isn't one.
	 * 
	 * @param  int     $errno   Integer code of an API_ERR constant.
	 * @return string           Text of the corresponding error message,
	 *                          or if that fails, the constant's name
	 *                          (NULL if there is no such message and 
	 *                          the constant's name is unfound).
	 */
	public static function get_standard_error_message($errno)
	{
		if (isset(self::STANDARD_MESSAGES[$errno]))
			return self::STANDARD_MESSAGES[$errno];
		else 
			return self::get_error_name($errno);
	}
	
	/**
	 * Get back the name of a given API_ERR constant as a string, or NULL
	 * if no such API_ERR constant is recognised as existing. 
	 */
	public static function get_error_name($errno)
	{
		$errno = (int)$errno;
		
		$all_c = get_defined_constants(true);
		
		foreach($all_c['user'] as $k => $v)
			if ($v == $errno)
				if (preg_match('/^API_ERR_/', $k))
					return $k;
		return NULL;
	}
}


/*
 * =================
 * SUPPORT FUNCTIONS
 * =================
 * 
 * Very few or none of these!
 */


/**
 * This function is the shell around CQPweb running in PAI mode.
 * 
 * @param string $function   One of the API function constants.
 */
function api($function)
{
	/* 3 possibilities for this: string, bool true, bool false. */
	$script = ApiController::get_script_for_function($function);
	
	if (is_string($script))
	{
		/*
		 * ======================================================
		 * API functions which access a complete existing script!
		 * ======================================================
		 * All that is needed is require, then exit. These are 
		 * dealt with largely within the ApiController object (setting up GET).
		 */
		require("../lib/$script");
	}
	else if ($script)
	{
		/*
		 * ====================================================================
		 * API functions which are "little tricks" so they are dealt with here!
		 * ====================================================================
		 * 
		 * This function only contains easy cases.... if anything is a bit hard, 
		 * it is referred out (e.g. to execute.php, which can call anything).
		 */
		run_nonscript_api_function($function);
	}
	else
	{
		/* not a real function */
		ApiController::static_dispatch(NULL, API_ERR_BAD_FUNCTION);
	}
	
	exit;
}



/**
 * This function contains a switch and then the code for the API functions that don't involve a require'd script.
 * 
 * @param string $function    API function (string constant).
 */
function run_nonscript_api_function($function)
{
	global $Config;
	global $User;
	global $Corpus;
	
	/* wherever possible, we do without the environment. This means using the static-dispatch
	 * rather than the normal dispatch through the object instance that happens at shutdown time;
	 * or, for anything a bit more complex, we can create and dispatch an ApiController right here.  */
	
	
	switch($function)
	{
	
	case API_FUNC_GET_VERSION:
		require('../lib/environment.php');
		ApiController::static_dispatch(CQPWEB_VERSION);
		return;
		
		
	case API_FUNC_GET_CWB_VERSION:
		require('../lib/environment.php');
		require('../lib/general-lib.php');
		require('../lib/sql-lib.php');
		require('../lib/cqp.inc.php');
		cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_SQL|CQPWEB_STARTUP_DONT_CHECK_USER, RUN_LOCATION_USR);
		$cqp = get_global_cqp();
		$Config->Api->set_response_content(str_replace('CQP', 'CWB',  $cqp->version_string));
		cqpweb_shutdown_environment();
		return;
		
		
	case API_FUNC_LIST_API_FUNCTIONS:
		ApiController::static_dispatch(ApiController::list_api_functions());
		return;
		
		
	case API_FUNC_GET_API_ERROR_INFO:
		$api_obj = new ApiController(API_FUNC_GET_API_ERROR_INFO, $_GET);
		if ($api_obj->prepare_arguments())
		{
			$e = $_GET['error_code'];
			$e_n = ApiController::get_error_name($e);
			$e_m = ApiController::get_standard_error_message($e);
			if (is_null($e_n) || is_null($e_m))
				$api_obj->raise_error(API_ERR_INVALID_ARG, "The error number supplied as argument ''error_code'' was not recognised.");
			else 
				$api_obj->set_response_content(  (object)[ 'name' => $e_n,  'standard_message' => $e_m ]  );
		}
		$api_obj->dispatch();
		return;
		
		
	case API_FUNC_FETCH_QUERY_HISTORY:
		require('../lib/environment.php');
		require('../lib/general-lib.php');
		require('../lib/sql-lib.php');
		require('../lib/html-lib.php');
		require('../lib/exiterror-lib.php');
		cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_CORPUS);

		$timewhere = (sql_timestamp_is_zero($Corpus->date_of_indexing) ? '' : " and `date_of_query` > '{$Corpus->date_of_indexing}' ");
		$limit_sql = '';
		if (isset($_GET['limit']))
			$limit_sql = 'limit '. (int)$_GET['limit'];
		$Config->Api->set_response_content(get_all_sql_objects(
									"select date_of_query as `timestamp`,simple_query,cqp_query,query_mode,query_scope,hits as n_hits 
										from query_history 
										where corpus = '{$Corpus->name}' 
										$timewhere 
										and user = '$User->username' 
										order by date_of_query DESC
										$limit_sql"
									)
								);
		
		cqpweb_shutdown_environment();
		return;
	
	}
	
}




/*
 * ===========================================================
 * Code for API functions that can't be delegated to a script.
 * ===========================================================
 * 
 * (IE if the simple code in run_nonscript_api_function gets
 * too complex, then hive some of it off into functions below.)
 */

