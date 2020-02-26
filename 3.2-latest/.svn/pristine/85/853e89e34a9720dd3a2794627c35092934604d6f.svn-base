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
 * @file This file contains the functions utilised for actually running queries.
 */
 
/**
 * Classes with this trait can use the READ_ONLY_MEMBERS array technique. 
 * (This exists mostly for me to experiment with traits. -- AH)
 * 
 * To use the trait, as well as decalring its use, the class also needs to
 * provide a class constant called READ_ONLY_MEMBERS whichi s a contant hash
 * mapping strings (name of members) to boolean true. 
 */
trait class_with_read_only_members
{
	/**
	 * This use of magic __get allows us to have world readable private/protected members. 
	 * 
	 * If a magic __get is needed by the class, it can simply override __get, 
	 * and instead call the identical non-magic function when desired *from* its own __get.
	 * 
	 * @param  string $name      Name of member. 
	 * @return mixed             The member's value. Any type of value, returned *not*
	 *                           as a reference; if the member variable holds an object 
	 *                           then a clone is returned. NULL if someone accesses a 
	 *                           variable not declared as read-only.
	 */
	public function __get($name)
	{
		return nonmagic_get_of_some_read_only_member_in_this_class($name);
	}

	/**
	 * Non magic version of this trait's "__get".
	 * @see class_with_read_only_members::__get()
	 * @param  string $name       The member requested (name as string).
	 * @return mixed              Its value.
	 */
	public function nonmagic_get_of_some_read_only_member_in_this_class($name)
	{
		if (defined("self::READ_ONLY_MEMBERS") && isset(self::READ_ONLY_MEMBERS[$name]))
		{
			if (is_object($this->$name))
				return clone $this->name;
			else
				return $this->$name;
		}
		return NULL;
	}
	
	/**
	 * Check whether a named member of the object is a private+read-only variable.
	 * 
	 * @param  string $name  Name of member variable. 
	 * @return bool          True if $this->$name is a private variable that can be read.
	 *                       False might mean it is private but not readonly, public,
	 *                       or nonexistent. 
	 */
	public function is_a_read_only_member_in_this_class($name)
	{
		return (defined("self::READ_ONLY_MEMBERS") && isset(self::READ_ONLY_MEMBERS[$name]));
	}
	
	/**
	 * Get a list of private/protected variables that this readon;ly-trait is looking after. 
	 * The absurdly long name is for the purpose of not blocking useful method names. 
	 * 
	 * @return array    Array of strings (membr names); empty array if none specified.
	 */
	public function list_of_the_read_only_members_in_this_class()
	{
		if (defined("self::READ_ONLY_MEMBERS"))
		{
			if (empty(self::READ_ONLY_MEMBERS) || !is_array(self::READ_ONLY_MEMBERS))
				return [];
			return array_keys(self::READ_ONLY_MEMBERS);
		}
		return [];
	}
}


/**
 * A QuerySpec bundles together the information required to run a query. 
 * 
 * It does not contain any "data" - just an easy way to acces different bits of the 
 * request.
 *
 */
class QuerySpec
{
	/* this code basically makes the whole object read-only. (const * in the C sense. */
	use class_with_read_only_members;
	const READ_ONLY_MEMBERS = ['qdata'=>true, 'qmode'=>true, 'qstrategy'=>true, 'qscope'=>true];	
	private $qdata;
	private $qmode;
	private $qstrategy;
	private $qscope;

	public function __construct(array $request_params = NULL)
	{
		if (is_null($request_params))
			$request_params = $_GET;
		
		
	}
	public function print_url_chunk()
	{
		
		
	}

	public function print_inputs()
	{
		
		
	}
	
	public function export_parameter_map()
	{
		
	}
}



class ConcViewLocus
{
	use class_with_read_only_members;
	const READ_ONLY_MEMBERS = ['page_no'=>true, 'per_page'=>true, 'qstrategy'=>true, 'qscope'=>true];	
	
	// this will eventually move to conc-lib but it can stay here while I work it through. 
	
	private $per_page;
	private $page_no;
	
	// eg if the display is *SPECIFICALLY* kwic or line. 
	private $other_non_defaults = [];
	
// 	const PARAM_FOR_PER_PAGE = ;
// 	const PARAM_FOR_PAGE_NO = ;

	

	public function __construct($json_string = false)
	{
		if (false !== $json_string)
			$this->unpackage($json_string);
	}
	
	// (etc)

	/**
	 * Returns a JSON representation of this object.
	 */
	public function package_me_up()
	{
		$package = new stdClass;
		
		//TODO
		
		$package->p = $this->per_page;
		$package->n = $this->page_no;
		
		return json_encode($package);
	}
	
	/**
	 * Fill up this object from a JSON representation.
	 */
	private function unpackage($json_string)
	{
		$package = json_decode($json_string);
		
		//TODO
		
		$this->per_page = $package->p;
		$this->set_page_no  = $package->n;
	}
	
	
	public static function new_by_unpackage($json_string)
	{
		$obj = new self();
		$obj->package_store = $json; 
	}
	
	

}


/* 
 * ====================================
 * connect/disconnect functions for CQP
 * ====================================
 */

/**
 * Locates the general-use CQP object and returns an object handle. 
 * 
 * @return CQP     CQP object interfacing to a cqp child process. 
 */
function get_global_cqp()
{
	global $Config;
	return $Config->get_slave_link('cqp');
}



/**
 * Creates and returns a connection to a CQP child process.
 */
function create_slave_cqp($cqp_corpus_name = NULL)
{
	global $Config;
	
	$cqp = new CQP($Config->path_to_cwb, $Config->dir->registry);
	
	/* select an error handling function */
 	$cqp->set_error_handler("exiterror_cqp");
	
	/* set CQP's temporary directory */
	$cqp->set_data_directory($Config->dir->cache);
	
	/* select corpus */
	if (!empty($cqp_corpus_name))
		$cqp->set_corpus($cqp_corpus_name);
	
	/* finally set debug mode. */
	if ($Config->print_debug_messages)
		$cqp->set_debug_mode(true);
	
	return $cqp;
}




/**
 * This function refreshes CQP's internal list of queries currently existing in its data directory
 * 
 * NB should this perhaps b part of the CQP object model?
 * (as perhaps should set DataDirectory!)
 */
function refresh_directory_global_cqp()
{
	get_global_cqp()->refresh_data_directory();
	return;
}








/* 
 * ===================================
 * Functions for processing query data
 * ===================================
 */



/**
 * Standardises the whitespace within a query string, and checks for its being empty. 
 * If a valid query strategy is passed in as the second argument, it is added to the query.
 * 
 * @param  string  $query     Query string submitted to concordance.php via GET.
 * @param  string  $strategy  Possible match strategy flag to prepend to the query.
 * @return string             Standardised string.
 */
function prepare_query_string($query, $strategy = false, $lookup_type_from_get = NULL)
{
	/* note we do NOT use %0D, %0A etc. because PHP htmldecodes for us. */
	$query = trim(preg_replace('/\s+/', ' ', $query));
	if ('' == $query)
		exiterror('You are trying to search for nothing!');
	
	if (in_array($strategy, array('standard', 'shortest', 'longest', 'traditional')))
		if (! preg_match('/^\(\?\s*(\w+)\s*\)\s*/', $query))
			$query = '(?' . $strategy . ') ' . $query;
	/* note that we only prepend a match strategy option-setter if there is not one there already */


	/* extra action if we're using wordlookup. */
	if (!empty($lookup_type_from_get))
	{
		switch($lookup_type_from_get)
		{
		case 'end':
			$query = '*' . $query;
			break;
	
		case 'begin':
			$query = $query . '*';
			break;
	
		case 'contain':
			$query = '*' . $query . '*';
			break;
	
		case 'exact':
		default:
			break;
		}
	}
	
	return $query;
}


/**
 * This function gets one of the allowed query-mode strings from $_GET.
 * 
 * If no valid query-mode is specified, it (a) causes CQPweb to abort if
 * $strict is true; OR (b) returns NULL if $strict is false. 
 */
function prepare_query_mode($s, $strict)
{
	$s = strtolower($s);
	
	switch($s)
	{
	case 'sq_case':
	case 'sq_nocase':
	case 'cqp':
		return $s;
	default:
		if ($strict)
			exiterror('Invalid query mode specified!');
		else
			return NULL;
	}
}



/**
 * Get a CQP query from user's CEQL query.
 * 
 * @param  string   $query
 * @param  bool     $case_sensitive
 * @param  array    $ceql_errors
 * @return string   
 */
function process_simple_query($query, $case_sensitive, &$ceql_errors)
{
	global $Config;

	/* allow us to harvest incompatibilities between old and new */
	if (@$Config->secret_setting_do_ceql_comparison)
	{
		$ceql_errors_orig = $ceql_errors_new = [];
		
		$original = process_simple_query_original($query, $case_sensitive, $ceql_errors_orig);
		$new      = process_simple_query_new($query, $case_sensitive, $ceql_errors_new);
		
		if ($original != $new)
		{
			$hdr = 'CEQL Parsers report mismatch';
			$bod = "At ".date(CQPWEB_UI_DATE_FORMAT)
					.", process_simple query() reported a failure of the old and new CEQL to match.\n\n";
			
			$bod .= "Input #$query#\nCS   ". ($case_sensitive ? 'On' : 'Off') . "\n\n";
			$bod .= "Orig: $original\n\nNew : $new\n\n";
			$bod .= "Error arrays:\n\nOrig:\n\t" 
					. implode("\n\t", $ceql_errors_orig)
					."\n\nNew:\n\t"
					. implode("\n\t", $ceql_errors_new)
					."\n\n"
					; 
			$bod .= "Good luck!\n\nbest\n\nCQPweb server.\n\n";
			
			@send_cqpweb_email(@$Config->server_admin_email_address, $hdr, $bod);
		}
		
		$ceql_errors = $ceql_errors_orig;
		
		return $original;
	}
	
	if (@$Config->use_the_new_ceql)
		return process_simple_query_new($query, $case_sensitive, $ceql_errors);
	else
		return process_simple_query_original($query, $case_sensitive, $ceql_errors);
}



/**
 * Translates a CEQL "simple" query and returns the CQP-syntax query.
 * 
 * @param  string $query           CEQL query 
 * @param  bool   $case_sensitive  Case sensitive mode on?
 * @param  array  $ceql_errors     Out parameter for error strings from the CEQL parser.
 * @return string                  The CQP query; empty value in case of error. 
 */
function process_simple_query_new($query, $case_sensitive, &$ceql_errors)
{
	global $Corpus;
	
	$ceql = new CeqlParserForCQPweb();

	
	/* if a primary annotation exists, specify it */
	if (!empty($Corpus->primary_annotation))
		$ceql->SetParam("pos_attribute", $Corpus->primary_annotation);
	
	
	/* if a secondary annotation exists, specify it */
	if (!empty($Corpus->secondary_annotation))
		$ceql->SetParam("lemma_attribute", $Corpus->secondary_annotation);
	
	
	/* if there is a tertiary annotation AND a tertiary annotation hash table, specify them;
	 * (these are needed as a pair; note, the mapping table is always set, but may be false) */
	if (!empty($Corpus->tertiary_annotation) && false !== ($mappings = get_tertiary_mapping_table($Corpus->tertiary_annotation_tablehandle)))
	{
		$ceql->SetParam("simple_pos_attribute", $Corpus->tertiary_annotation);
		$ceql->SetParam("simple_pos", $mappings);
	}
	
	
	/* if a combo annotation is given, specify it */
	if (isset($Corpus->combo_annotation))
		$ceql->SetParam("combo_attribute", $Corpus->combo_annotation);
	
	
	/* if there is an allowed-xml table, specify it */
	$xml_hash = array_map(function($x) {return 1;}, list_xml_all($Corpus->name));
	$ceql->SetParam("s_attributes", empty($xml_hash) ? [] : $xml_hash);

	
	/* set case sensitivity (since the arguemnt is "ignore", we need to NOT it. */
	$ceql->SetParam("default_ignore_case", !$case_sensitive);
	
	
	$cqp_query = $ceql->Parse($query);


	if (empty($cqp_query))
	{
		/* indicates parser error */
		$ceql_errors = $ceql->ErrorMessage();
		array_unshift($ceql_errors, "Syntax error", "Sorry, your simple query [[[ $query ]]] contains a syntax error.");
		return false;
	}

	return $cqp_query;
}




function process_simple_query_original($query, $case_sensitive, &$ceql_errors)
{
	/* create the script that will be bunged to perl */
	/* note, this function ALSO accepts an XML table, but this isn't implemented yet */
	$script = get_ceql_script_for_perl($query, $case_sensitive);

	$cqp_query = '';
	$ceql_errors = array();
	
	if ( ! run_perl_script($script, $cqp_query, $ceql_errors))
		exiterror("The CEQL parser could not be run (problem with perl)!");

	if ( empty($cqp_query) )
	{
		squawk("Error in perl script for CEQL: this was the script\n\n$script\n\n");
		array_unshift($ceql_errors, "Syntax error", "Sorry, your simple query [[[ $query ]]] contains a syntax error.");
		return false;
	}
	return $cqp_query;
}





/*
 * ==============
 * MAPPING TABLES
 * ==============
 * 
 */
// TODO test this on all known mapping tables. 
// TODO insert this between the DB and CEQL, making CEQL accept JSON
// TODO write an object-to-Perl-hash-code method.
function translate_perl_hash_to_json($perl_hash, &$result, &$errors = [])
{
	$result = NULL;
	
	$working = trim($perl_hash);
	$working = preg_replace('/\s+/', ' ',$working);
	
	$slash_before = false;
	$within_single = false;
	$within_double = false;
	
	for ($i = 0 , $n = strlen($perl_hash) ; $i < $n ; $i++)
	{
		$apply_slash_before = false;
		
		switch($working[$i])
		{
		case '\\':
			if (!$slash_before)
				$apply_slash_before = true;
			break;

		case "'":
			if ($slash_before || $within_double)
				;
			else
			{
				if ($within_single)
					$within_single = false;
				else
					$within_single = true;
			}
			break;
			
		case '"':
			if ($slash_before || $within_single)
				;
			else
			{
				if ($within_double)
					$within_double = false;
				else
					$within_double = true;
			}
			break;
		
		case '=':
			if (!$slash_before && $n > ($i+1))
			{
				if ('>' == $working[$i+1])
				{
					$working[$i]   = ':';
					$working[$i+1] = ' ';
				}
			}
			break;
		
		default:
			break;
		}
		
		$slash_before = $apply_slash_before;
	}

	if ($within_double || $within_single)
	{
		if ($within_double)
			$errors[] = "Unterminated double quote at end of Perl data!!";
		if ($within_single)
			$errors[] = "Unterminated single quote at end of Perl data!!";
		return false;
	}
	
	$test_obj = json_decode($working);
	
	if (is_null($test_obj))
	{
		 if (JSON_ERROR_NONE == json_last_error())
		 	$errors[] = "The Perl data converted to JSON ``$working``\n\t... which decoded as NULL!";
		else
			$errors[] = "JSON decode error on converted Perl: \n\t" . json_last_error_msg();
	}
	
	$result = $working;
	
	return true;
}

/**
 * Perl hashes and JSON objects (as used in CQPweb) are v. similar: only difference 
 * is the character indicating  key->value. 
 * 
 * @param  string $str      String containg Perl hash or JAvascriopt object,
 *                          i.e. an associative array with beginning end marked by { ... } . 
 * @param  bool   $is_json  If true: input is JSON, will become Perl. If false: the converse.
 * @return string           A string of a hash/object switched round. 
 */
function swop_perl_and_json_hash_styles($str, $is_json)
{
	
	
	
}
function translate_php_hash_to_perl($hash)
{
	$json = json_encode($hash);
	
	for ($i = 0 , $n = strlen($json) ;  $i < $n ; $i++)
	{
		
	}
}


