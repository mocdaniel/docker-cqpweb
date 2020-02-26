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
 * This file contain functions that deal with the cache, saved/categorised queries, etc.;
 * plus also the query record object.
 */



/**
 * Encapsulates a query record (cached, user-saved, or categorised query).
 */
class QueryRecord
{
	/*
	 * INFO ABOUT THE STRUCTURE OF THE DATABASE
	 * ========================================
	 */
	
	/** knowledge about the database; stored as private static 'cos a const can't be an array. TODO: in php 7.3 it can. */
	private static $DATABASE_FIELD_NAMES = array(
		'query_name',
		'user',
		'corpus',
		'query_mode',
		'simple_query',
		'cqp_query',
		'query_scope',
		'postprocess',
		'hits_left',
		'time_of_query',
		'hits',
		'file_size',
		'saved',
		'save_name',
		'hit_texts'
	);
	
	/** Array containing the ^\w+$ strings that are possible values for a query mode (in the DB and as HTML-form-input values). */
	public static $POSSIBLE_QUERY_MODES = array(
		'cqp','sq_nocase','sq_case','uploaded'
	);
	
	
	
	/* 
	 * MEMBER VARIABLES CORRESPONDING TO THE FIELDS OF THE DATABASE
	 * ============================================================
	 * 
	 * Private ones are the features that are encoded in some particular way 
	 * - they should be accessed via the class API to encapuslate the database conventions 
	 * so that the rest of CQPweb does not need to worry about them.
	 * 
	 * Public ones are the features that are "obvious" in their interpretation 
	 * and can just be read from / written to in exactly the way you'd think.
	 */
	
	/** unique identifier of the query -- corresponds to the query_name field in the db */
	public $qname;
	
	/** unique identifier of the query when this object was originally loaded from DB; if this does not match $qname, $qname has been changed. */
	private $qname_as_in_db = '';
	
	/** dump variable -- we don't use this, we use the more sensibly-named $qname. But the DB read dumps to here. */
	private $query_name = '';
	
	/** user to whom the query "belongs" */
	public $user;
	
	/** "name" (ie handle) of the corpus */
	public $corpus;
	
	/** identifier for the mode the original query ran in (in db format); one of [ cqp | sq_nocase | sq_case | uploaded ] */
	public $query_mode;
	
	/** the simple query text of the original query; if none, empty string or NULL. */
	public $simple_query;
	
	/** the cqp-syntax content of the original query */
	public $cqp_query;
	
// following two both now commented out, as it's been almost a year since they were made private and nothing yet seems to be broken... 
// 	/* * corpus "restrictions" used when the query was first run, in format as stored in the database. */
// 	private $restrictions; // retained for the nonce as a private, so that if anything references it directly, things will break.
// 	/* * integer ID of the subcorpus within which the query was first run, in databse fromat; none = "" (empty string) */
// 	private $subcorpus;// retained for the nonce as a private, so that if anything references it directly, things will break.
	
	/** The query scope: serialisation of the QueryScope (Subcorpus or Restriction) under which the query ran. */
	public $query_scope;
	
	/** database-encoded postprocess string */
	public /*private*/ $postprocess; 
	/* note -- public for now because some scripts manipulate it directly. Longterm- TODO: stop that! and make it private again. */
	
	/** string with sequence of tilde-delimited integers indicating n of hits preserved after each postprocess */
	private $hits_left;
	
	/** 
	 * Time of query (unix time integer). 
	 * 
	 * If query is a user-saved query, this represents the time at which it was originally saved.
	 * If query is a categorised query, this represents the time when its categorisations were last updated. 
	 * If query is an unsaved in-cache query, then this is set to last time the query was "touched" e.g. used for a concordance.
	 * 
	 * Note that for a postprocessed query it is the creation time of the postprocessed version, NOT the underlying original.
	 */
	public $time_of_query;
	
	/** number of hits when query was originally run */
	private $hits;
	
	/** number of texts containing one or more hits when the query was originally run. */
	public $hit_texts;
	
	/** Size on disk in bytes of the file containing the query on disk */
	public $file_size;
	
	/** Current saved-status: one of the integer constants starting in CACHE_STATUS ... */
	public $saved;
	
	/** If this is a saved query, the save-name is the (normally user-specified) name it is stored under. If not, NULL. */
	public $save_name;
	
	
	/*
	 * SOME NOTES ON THE DATABASE FORMAT
	 * =================================
	 * 
	 * The following fields must always be populated, and must contain a string handle:
	 * 
	 *   query_name
	 *   user
	 *   corpus
	 *   query_mode
	 *
	 * All the integer fields default to NULL in the DB, and are expected to always have numbers added to overwrite the NULL.
	 * 
	 * Other fields have various "empty" values. (MySQL won't let "text" fields have default values, alas, so the 
	 * implementation is in places, ahem, inconsistent.)
	 * 
	 * The query_scope field follows the format outlined in scope-lib.php. It uses "" as the "empty" value, 
	 * i.e. to refer to the scope across the full corpus.
	 * 
	 * simple_query: contains the simple query, will be set to empty string otherwise.
	 * cqp_query   : contains the actual query, will be set to empty string otherwise (e.g. if the query was uploaded).
	 * postprocess : contains the stored postprocess data, will be set to empty string otherwise.
	 * hits_left   : contains further postprocess data; the empty value is an empty string.
	 * save_name   : contains the name assigned by the user, will be set to empty string otherwise.
	 * 
	 * So, none of the above should ever have a NULL in them (as they should be set to "" on creation if they had no particular value).
	 * In the 3.2.5 upgrade this was enforced on existing queries.
	 */

	
	/*
	 * OTHER OBJECT MEMBER VARIABLES 
	 * =============================
	 */

	/** has this object been loaded with a query record from the DB (or a full set of manual settings), or is it still empty? */
	public $has_been_loaded = false;
	
	/** The Query Scope object. Represents the object-ised version of the query_scope field.  
	 *  It's public so that its methods can be accessed. (and it can in turn contain a Subcorpus or Restriction.)*/
	public $qscope = NULL;
	
	
	/* variables for checking if a setup method has run: all are $hasrun_{name_of_method} and all are pre-set to false. */
	
	private $hasrun_internal_amount_of_text_searched_setup = false;
	private $hasrun_internal_postprocess_info_setup = false;
	
	/* postprocess monitoring variables */
	
	/** stores the postprocess data as an exploded array for pulling out info. NOTE: the "args" are unparsed in this variable! */
	private $postprocess_stack;
	/** stores the hits-left data as an exploded array for pulling out info. */
	private $hits_left_stack;
	
	/** The thin extrapolation factor is used by the distribution script to guesstimate unthinned frequency from a thinned query. 
	 *  This variable is private because it is NULL until it is set up. */
	private $thin_extrapolation_factor = NULL;
	/** Is true (after postprocess setup) if the thin_extrapolation_factor has been altered from the base value of 1.0,
	 *  which happens iff there is at least one "thin" in this query's postprocess chain. */
	private $thin_factor_exists = false;
	/** array containing extra text-category applied by "dist"-type postprocesses in this query's postprocess chain. */
	private $extra_category_filters;
	/** temporary holder for the name of an intermed file used when running a postprocess. */
	private $pp_temp_file;
	
	
	/* variables for storing different aspects of the amount-of-text-searched info. */
	
	private $tokens_searched_initially   = NULL;
	private $items_searched_initially    = NULL;
	private $item_type_initially         = NULL;
	private $tokens_searched_reduced     = NULL;
	private $items_searched_reduced      = NULL;
	private $item_type_reduced           = NULL;
	
	/* * A whereclause, including leading "where", to use on the text_metadata table, in order to find the amount of text searched,
	 * INCLUDING reductions by "dist" type postprocesses after the initial query. If it is set to an empty string, then that means
	 * the amount of text searched HAS NOT been reduced, and the original subcorpus/restrictions tells the correct story. */
// 	private $scope_reduction_whereclause = NULL;
	//maybe later?
	// 	private $ranges_searched_initially;
	// 	private $range_type_searched_initially;
	// etc.
	
	
	/*
	 * METHODS: OBJECT CREATION
	 * ========================
	 */
	public function __construct()
	{
		/* the constructor does bugger all: a query record has to be *loaded* before we can do anything.... */
	}

	/* A note: we have no clone method yet, because we do not yet have any inner-objects that are mutable.
	 * 
	 * The only inner object at the moment is QueryScope, and the QueryScope is not mutable for a given
	 * query once created.
	 * 
	 * The code below illustrates how this would work using the QueryScope object. But as noted, it's totally not needed.
	public function __clone()
	{
		if (is_object($this->qscope))
			$this->qscope = clone $this->qscope;
	}
	 */
	
	
	
	/*
	 * Creator functions. (Roll together a call to "new" and a call to one of the "load" functions.)
	 */
	
	/**
	 * Create a new query record which did not exist before. Note, this does not commit it to the DB! 
	 * Use the save method to write the query to the cache table.
	 * 
	 * @see QueryRecord::save
	 * @param  string $qname            Unique identifier for the query in cache. Must correspond to an actually-existing 
	 *                                  file in CQPweb's cache directory.
	 * @param  string $user             Username of the user to whom this query "belongs".
	 * @param  string $corpus           The corpus in which the query ran.
	 * @param  string $query_mode       One of the mode-indicator strings. 
	 * @param  string $simple_query     Text of the CEQL query from which the CQP code was created. An empty value can be passed if there was none.
	 * @param  string $cqp_query        The CQP code of the query. An empty value can be passed if there was none.
	 * @param  QueryScope $qscope       Object representing the scope within which this query was executed. 
	 *                                  If an empty value, a whole-corpus query scope is assumed.
	 *                                  Note that only one of $restrictions and $suibcorpus may be non-empty.
	 * @param  int    $n_hits           Number of hits.
	 * @param  int    $n_hit_texts      Number of texts in which 1+ hits occur.
	 * @param  string $postprocess      A postprocess string in database format, if a postprocess has been run. 
	 *                                  If no postprocess, pass empty string or omit.
	 * @param  string $hits_left        A postprocess "hits left" string in database format, if a postprocess has been run. 
	 *                                  If no postprocess, pass empty string or omit. 
	 * @return QueryRecord              Returns the created object or false in case of error.
	 */
	public static function create(
									$qname,
									$user,
									$corpus,
									$query_mode,
									$simple_query,
									$cqp_query,
									$qscope,
									$n_hits,
									$n_hit_texts,
									$postprocess = NULL,
									$hits_left = NULL
									)
	{
		$obj = new self();
		
		/* set each of the values needed for a DB commit one by one. Test for empty values where appropriate. */
		
		if (empty($qname))
			exiterror("Cannot create QueryRecord with empty query name!");
		else
			$obj->qname = cqpweb_handle_enforce($qname);
		
		/* check existence of the file */
		if (!cqp_file_exists($qname))
			return false;
		
		$obj->user   = cqpweb_handle_enforce($user);
		$obj->corpus = cqpweb_handle_enforce($corpus);
		
		if (!in_array($query_mode, self::$POSSIBLE_QUERY_MODES))
			exiterror("Cannot create QueryRecord with invalid query mode ``$query_mode''!");
		else
			$obj->query_mode = $query_mode;
		
		/* unsanitised, possibly empty, sequences. */
		$obj->simple_query = (empty($simple_query) ? '' : $simple_query); 
		$obj->cqp_query    = (empty($cqp_query)    ? '' : $cqp_query); 
		$obj->postprocess  = (empty($postprocess)  ? '' : $postprocess); 
		$obj->hits_left    = (empty($hits_left)    ? '' : $hits_left);
		
		$obj->qscope = $qscope;
		$obj->query_scope = $obj->qscope->serialise();
		
		/* final two args from the collection are sanitised to integer. */
		$obj->hits      = (int)$n_hits;
		$obj->hit_texts = (int)$n_hit_texts;
		
		/* non-passed values that we assign here. */ 
		$obj->file_size = cqp_file_sizeof($qname);
		$obj->saved = CACHE_STATUS_UNSAVED;
		$obj->save_name = '';
		$obj->set_time_to_now();
		
		$obj->has_been_loaded = true;
	
		return $obj;
	}
	
	/**
	 * Create a new query record object from the unique query name identifier string.
	 * 
	 * @param string $qname       The query identifier to be looked up in the DB. 
	 * @return QueryRecord  Returns the created object or false in case of error.
	 */
	public static function new_from_qname($qname)
	{
		$newobj = new self();
		if (!$newobj->load_from_qname($qname))
			return false;
		return $newobj;
	}
	
	/**
	 * Create a new query record object from a set of parameters to be matched in the cache
	 * (i.e. "find a cached query for THIS search-pattern, in THIS corpus, with THESE additional features...").
	 * 
	 * @param  string $corpus        The corpus to match.
	 * @param  string $query_mode    The mode-indicator string to match. 
	 * @param  string $cqp_query     The cqp query to match. 
	 * @param  string $query_scope   A serialised query scope to match. (Any empty value becomes an empty string.)
	 *                               Or, it can be an actual QueryScope object, in which case the object will be stored,
	 *                               and its serialisation used to check the database.
	 * @param  string $postprocess   A database-format postprocess string to match.
	 * @return QueryRecord    Returns the created object or false in case of error.
	 */
	public static function new_from_params($corpus, $query_mode, $cqp_query, $query_scope = '', $postprocess = '')
	{
		$newobj = new self();
		if (!$newobj->load_from_params($corpus, $query_mode, $cqp_query, $query_scope, $postprocess))
			return false;
		return $newobj;
	}
	
	/**
	 * Create a new query record object from a MySQL result set. 
	 * 
	 * @param resource $result     Result containing at least one full record from the saved_queries table. 
	 * @return QueryRecord   The new object, or false if a query record could not be extracted.
	 */
	public static function new_from_db_result($result)
	{
		$newobj = new self();
		if (!$newobj->load_from_db_result($result))
			return false;
		return $newobj;
	}
	
	/**
	 * Loads this object with the query record that matches the given qname.
	 * 
	 * @param string $qname  Query identifier string from the query_name field.
	 * @return bool          True if the object was loaded (i.e. if the given qname exists in cache), otherwise false.
	 */
	public function load_from_qname($qname)
	{
		$result = do_sql_query("SELECT * from saved_queries where query_name = '". escape_sql($qname) . "'");
		if (0 == mysqli_num_rows($result))
			return false;
		return $this->load($result);
	}
	
	
	/**
	 * Loads this object with the query record that matches the given parameters.
	 * 
	 * @param  string $corpus        The corpus to match.
	 * @param  string $query_mode    The mode-indicator string to match. 
	 * @param  string $cqp_query     The cqp query to match. 
	 * @param  string $query_scope   A serialised query scope to match. (Any empty value becomes an empty string.) 
	 *                               It's allowed for a QueryScope object to be passed instead.
	 * @param  string $postprocess   A database-format postprocess string to match.
	 * @return bool                  True if the object was loaded (i.e. if a query with those params exists in cache), otherwise false.
	 */
	public function load_from_params($corpus, $query_mode, $cqp_query, $query_scope = '', $postprocess = '')
	{
		/* make safe the parameters as passed in..... */
		$corpus       = cqpweb_handle_enforce($corpus);
		$query_mode   = cqpweb_handle_enforce($query_mode);
		$cqp_query    = escape_sql($cqp_query);
		
		if (is_object($query_scope))
		{
			/* stash the object */
			$this->qscope = $query_scope;
			$sql_query_scope = ( QueryScope::TYPE_WHOLE_CORPUS == $this->qscope->type ? '' : escape_sql($this->qscope->serialise()) );
		}
		else
			$sql_query_scope  = (empty($query_scope) ? '' : escape_sql($query_scope));
		
		$postprocess  = escape_sql((string)$postprocess);
		
		$result = do_sql_query("SELECT * from saved_queries
									where corpus     = '$corpus'
									and query_mode   = '$query_mode'
									and cqp_query    = '$cqp_query'
									and query_scope  = '$sql_query_scope'
									and postprocess  = '$postprocess'
									and saved = " . CACHE_STATUS_UNSAVED . " 
									limit 1"
								);

		if (0 == mysqli_num_rows($result))
			return false;
		
		return $this->load($result);
	}
	
	/**
	 * Load variables from a MySQL result. 
	 * If checking is enabled, and the result does not have the shape of a result selected from
	 * saved_queries, CQPweb aborts.
	 *  
	 * @param  mysqli_result $result MySQL result containing the query record from ``saved_queries''.
	 *                               Its internal pointer will be moved onwards-by-one.
	 * @param  bool $check           Whether to check that the result has the correct fields.
	 * @return bool                  True (unless no object could be extracted from the result argument,
	 *                               in which case false).
	 */
	public function load_from_db_result(mysqli_result $result, $check = false)
	{
		if ($check)
		{
			/* We check that the result has the correct fields */
			$fields_present = array();
// 			for ( $i = 0, $n = mysql_num_fields($result)  ; $i < $n ; $i++ )
// 				$fields_present[] = mysql_field_name($result, $i);
			
			foreach (mysqli_fetch_fields($result) as $fo)
				$fields_present[] = $fo->name;
			
			$diff = array_diff(self::$DATABASE_FIELD_NAMES, $fields_present);
			if (!empty($diff))
				exiterror("QueryRecord cannot load a query record from a DB record that lacks 1+ relevant fields!");
		}
		
		return $this->load($result);
	}
	
	
	
	
	/*
	 * METHODS FOR MAIN DB LOAD / SAVE
	 * ===============================
	 */
	
	/**
	 * Load variables from a known-good MySQL result. Backend for all create/load functions.
	 *  
	 * @param  mysqli_result $result  MySQL result containing the query record from ``saved_queries''.
	 *                                Its internal pointer will be moved onwards-by-one.
	 * @return bool                   True if the load was OK. False if something went wrong. Possibilities: 
	 *                                (1) no object could be extracted from the result argument;
	 *                                (2) an object was loaded from DB, but had no corresponding file.
	 *                                in which case false).
	 */
	private function load(mysqli_result $result)
	{
		if (!($o = mysqli_fetch_object($result)))
			return false;

		/* We assume the query has the correct fields, since it was either created by
		 * or checked by one of the outer functions that calls this inner function.
		 * 
		 * We ALSO assume that there is at least one result in the result set argument.
		 */

		$this->qname          = $o->query_name;
		$this->qname_as_in_db = $o->query_name;
		foreach (self::$DATABASE_FIELD_NAMES as $df)
			$this->$df = $o->$df;

		/* if the class was given a pre-created QueryScope object, don't overwrite it */ 
		if (is_null($this->qscope))
			$this->qscope = QueryScope::new_by_unserialise($this->query_scope);

		$this->has_been_loaded = true;

		if (cqp_file_exists($this->qname))
			return true;
		else
		{
			/* the sql record of the query with that cqp_query exists, but the file doesn't */
			do_sql_query( "DELETE FROM saved_queries where query_name = '{$this->qname}'");
			return false;
		}
	}
	
	/** 
	 * Commits any changes made to the query record back to the database. 
	 * 
	 * If a new qname has been assigned, then the query record is saved as new, rather than updated.
	 * Note: this DOES NOT auto-set the time to now. If you want the time to be now, you need to set it.
	 * 
	 * Note also that ALL fields are written back to the DB. Even if they've not changed.
	 */
	public function save()
	{
		/* values that don't exist as handle-enforced are made safe prior to DB commit */
		$simple_query = escape_sql($this->simple_query);
		$cqp_query    = escape_sql($this->cqp_query);
		$query_scope  = escape_sql($this->query_scope);
		$postprocess  = escape_sql($this->postprocess);
		
		if ($this->qname == $this->qname_as_in_db)
		{
			/* we need an UPDATE query. */
			$sql = "update saved_queries set 
						user          = '{$this->user}',
						corpus        = '{$this->corpus}',
						query_mode    = '{$this->query_mode}',
						simple_query  = '$simple_query',
						cqp_query     = '$cqp_query',
						query_scope   = '$query_scope',
						postprocess   = '$postprocess',
						hits_left     = '{$this->hits_left}',
						time_of_query = {$this->time_of_query},
						hits          = {$this->hits},
						hit_texts     = {$this->hit_texts},
						file_size     = {$this->file_size},
						saved         = {$this->saved},
						save_name     = '{$this->save_name}'
					where query_name = '{$this->qname}'";
			do_sql_query($sql);
		}
		else
		{
			/* we need an INSERT query. */
			$sql = "insert into saved_queries
						( query_name,             user,               corpus,
						  query_mode,             simple_query,       cqp_query, 
						  query_scope,            postprocess,        hits_left,
						  hits,                   hit_texts,          time_of_query, 
						  file_size,              saved,              save_name
						)
					values
						( '{$this->qname}',       '{$this->user}',    '{$this->corpus}', 
						  '{$this->query_mode}',  '$simple_query',    '$cqp_query', 
						  '$query_scope',         '$postprocess',     '{$this->hits_left}',
						  {$this->hits},          {$this->hit_texts}, {$this->time_of_query}, 
						  {$this->file_size},     {$this->saved},     '{$this->save_name}' 
						)";
			do_sql_query($sql);

			/* once the database has been updated, update the variable that recalls the db-qname */
			$this->qname_as_in_db = $this->qname;
		}
	}
	
	
	/*
	 * POSTPROCESS IMPLEMENTATION METHODS
	 * ==================================
	 */

	/**
	 * This function alters the query record of the object by putting it through a postprocess.
	 * (So, it will no longer refer to the same cached query. It will refer to a new DB entry, and 
	 * a new CQP file in the cache.) It will, in consequence, have a new qname after running.
	 * 
	 * Usage example: 
	 * 
	 * $qr = QueryRecord::new_from_qname($qname);
	 * $de = new CQPwebPostprocess;
	 * $nq = clone $qr;                                     # skip iff you no longer need the old query record.
	 * $nq->execute_postprocess($de);
	 * echo "{$nq->qname}    {$qr->qname}", PHP_EOL;        # will not be the same. 
	 * 
	 * @param CQPwebPostprocess $descriptor  Object containing information about the postprocess to be applied.
	 * @return bool                          True iff the postprocessed query has resulted in a query with more than one hit;
	 *                                       otherwise false (and this object is in disarray!).
	 */
	public function do_postprocess($descriptor)
	{
		global $Config;
		global $User;
		
		/* passed to the methods so they can access the unchanged versions where necessary */
		$orig_record = clone $this;
		
		/* record update: operations relevant to every postprocess type. */
		$this->qname = qname_unique($Config->instance_name);
		$this->user = $User->username;
		$this->postprocess = $descriptor->get_stored_postprocess_string();
		$this->reset_internal_info();
		$this->saved = CACHE_STATUS_UNSAVED;
		$this->save_name = NULL;
		$this->set_time_to_now();
		/* note that the individual functions update hits_left based on their operation! */

		/* a temp file may or may not be needed. realpath, because it's going to be used for MySQL outfile. */
		$this->pp_temp_file = realpath($Config->dir->cache) . "/temp_{$descriptor->postprocess_type}_{$this->qname}.tbl";
		
		/* all methods return false if the postprocess resulted in an empty query (which will not be cached) */
		$method = 'do_postprocess_' . $descriptor->postprocess_type;
				
		$r = $this->$method($descriptor, $orig_record);
		
		if (file_exists($this->pp_temp_file))
			unlink($this->pp_temp_file);

		/* if the method call did not return false, then we should save this query record. */
		if ($r)
			$this->save();

		return $r;
	}
	
	/* 
	 * the actual implementations of each postprocess have some similarities, but most have been factored out
	 * into the supervisor function (above). The actual psotprocess emthods are only EVER called via "$method"
	 * in the suipervisor function. 
	 */
	
	/* rand / unrand / thin / custom use CQP directly, and do not use the database */
	
	private function do_postprocess_rand($descriptor, $orig_record)
	{
		$cqp = get_global_cqp();	

		/* note for randomisation, "hits left"/"file size" don't need changing */
	
		$cqp->execute("{$this->qname} = {$orig_record->qname}");
		$cqp->execute("sort {$this->qname} randomize 42");
		$cqp->execute("save {$this->qname}");
	
		return true;
	}
	
	private function do_postprocess_unrand($descriptor, $orig_record)
	{
		$cqp = get_global_cqp();
	
		/* note for unrandomisation, "hits left"/"file size" don't need changing */
	
		$cqp->execute("{$this->qname} = {$orig_record->qname}");
		$cqp->execute("sort {$this->qname}");
		$cqp->execute("save {$this->qname}");

		return true;
	}	
	
	private function do_postprocess_thin($descriptor, $orig_record)
	{
		$cqp = get_global_cqp();

		$this->hits_left .= (empty($this->hits_left) ? '' : '~') . $descriptor->thin_target_hit_count;
	
		/* actually thin */
		$cqp->execute("{$this->qname} = {$orig_record->qname}");
		
		/* constant seed of 42 results in reproducibly-random thinning */
		if ( ! $descriptor->thin_genuinely_random)
			$cqp->execute("randomize 42");

		$cqp->execute("reduce {$this->qname} to {$descriptor->thin_target_hit_count}");
		$cqp->execute("save {$this->qname}");
		$this->file_size = cqp_file_sizeof($this->qname);

		return true;
	}	
	
	private function do_postprocess_custom($descriptor, $orig_record)
	{
		global $Config;
		
		$cqp = get_global_cqp();
	
		/* the heart of a custom postprocess: dump, process, undump */
		$matches = $cqp->dump($orig_record->qname);
		$matches = $descriptor->custom_obj->postprocess_query($matches);
		$cqp->undump($this->qname, $matches, $Config->dir->cache);
		$cqp->execute("save {$this->qname}");
	
		/* get the size of the new query */
		$this->hits_left .= (empty($this->hits_left) ? '' : '~') . count($matches) ;
		$this->file_size = cqp_file_sizeof($this->qname);
	
 		return true;
	}
	
	/* 
	 * coll / sort / item / dist / text all use the DB and then undump in a common pattern which is factored out. 
	 * The functions are largely the same. 
	 */
	
	private function do_postprocess_coll($descriptor, $orig_record)
	{
		$sql = $descriptor->colloc_sql_for_queryfile();
	
		$solutions_remaining = do_sql_outfile_query($sql, $this->pp_temp_file);
	
		$this->hits_left .= (empty($this->hits_left) ? '' : '~') . $solutions_remaining;
	
		return $this->do_postprocess_undump_and_filesize($solutions_remaining);
	}

	private function do_postprocess_sort($descriptor, $orig_record)
	{
		$sql = $descriptor->sort_sql_for_queryfile($orig_record);
	
		$solutions_remaining = do_sql_outfile_query($sql, $this->pp_temp_file);
	
		if ($descriptor->sort_remove_prev_sort)
		{
			/* we need to remove the previous "hits left" because a sort has been undone */
			$this->hits_left = preg_replace('/~?\d+\Z/', '', $this->hits_left);
		}
		$this->hits_left .= (empty($this->hits_left) ? '' : '~') . $solutions_remaining;
	
		return $this->do_postprocess_undump_and_filesize($solutions_remaining);
	}
	
	private function do_postprocess_item($descriptor, $orig_record)
	{
		/* this method call creates the DB if it doesn't already exist */
		$sql = $descriptor->item_sql_for_queryfile($orig_record);
	
		$solutions_remaining = do_sql_outfile_query($sql, $this->pp_temp_file);
	
		$this->hits_left .= (empty($this->hits_left) ? '' : '~') . $solutions_remaining;
	
		return $this->do_postprocess_undump_and_filesize($solutions_remaining);
	}
	
	private function do_postprocess_dist($descriptor, $orig_record) 
	{
		/* this method call creates the DB if it doesn't already exist */
		$sql = $descriptor->dist_sql_for_queryfile($orig_record);

		$solutions_remaining = do_sql_outfile_query($sql, $this->pp_temp_file);
	
		$this->hits_left .= (empty($this->hits_left) ? '' : '~') . $solutions_remaining;
	
		return $this->do_postprocess_undump_and_filesize($solutions_remaining);
	}
	
	private function do_postprocess_text($descriptor, $orig_record)
	{
		/* this method call creates the DB if it doesn't already exist */
		$sql = $descriptor->text_sql_for_queryfile($orig_record);
	
		$solutions_remaining = do_sql_outfile_query($sql, $this->pp_temp_file);
	
		$this->hits_left .= (empty($this->hits_left) ? '' : '~') . $solutions_remaining;
	
		return $this->do_postprocess_undump_and_filesize($solutions_remaining);
	}
	
	
	/** Helper method for postprocesses based on the database, which only use CQP to dump/save/size. */
	private function do_postprocess_undump_and_filesize($solutions_remaining)
	{
		$cqp = get_global_cqp();
		
		if ($solutions_remaining > 0)
		{
			/* load to CQP as a new query, and save */
			$cqp->execute("undump {$this->qname} < '{$this->pp_temp_file}'");
			$cqp->execute("save {$this->qname}");
			$this->file_size = cqp_file_sizeof($this->qname);
			return true;
		}
		else
			return false;
	}
	
	
	/**
	 * Appends extra postprocess code to the existing postprocess code. Deals automatically with delimiters.
	 * 
	 * This code can only be used by SIMPLE postprocesses - currently it's only used by "cat". 
	 * For anything more complex it is super-dangverous
	 * 
	 * @param string $pp_code    Database format single postprocess.
	 * @param int    $hits_left  The number of hits left after the completion of the postprocess being described.
	 */
	public function postprocess_append($pp_code, $hits_left)
	{
		$new_pp_string = $this->postprocess	. (empty($this->postprocess) ? '' : '~~') . $pp_code;
		$new_hits_left = $this->hits_left   . (empty($this->hits_left)   ? '' : '~')  . $hits_left;
		$this->postprocess = $new_pp_string;
		$this->hits_left   = $new_hits_left;
		/* in case the internal stacks were created... flag all internal data for recreation */
		$this->reset_internal_info();
	}
	
	/*
	 * MISC METHODS
	 * ============
	 */
	
	public function set_time_to_now()
	{
		$this->time_of_query = time();
	}
	
	
	/**
	 * Needed for one button-builder process in the concordance display.
	 * 
	 * @return bool  True if the last postprocess on the stack was "rand". Otherwise false.
	 */
	public function last_postprocess_is_rand()
	{
		return (substr($this->postprocess, -4) == 'rand' &&  substr($this->postprocess, -6) !== 'unrand');
	}
	
	
	
	
	// thought I'd need this, turns out not.
// 	/**
// 	 * Gets an array of extra category filters that exist in this query's postprocess chain
// 	 * (used in the Distribution display).
// 	 * 
// 	 * Each filter is a string "class~cat" - as used by get_search_scope_with_extra_restrictions.
// 	 */
// 	public function get_extra_category_filters()
// 	{
// 		if ( ! $this->hasrun_internal_postprocess_info_setup )
// 			$this->internal_postprocess_info_setup();
// 		return $this->extra_category_filters;
// 	}



	/**
	 * Calculates the extrapolation factor for this query (used in the Distribution display).
	 * 
	 * An "extrapolation factor" allows the number of hits to be guessed from a thinned query 
	 * (i.e. a query that has been reduced in certain specific ways). Defined as follows:
	 * 
	 * The THINNING EXTRAPOLATION FACTOR = 1 over THIN FRACTION.
	 * The THIN FRACTION = hits after the thin, divided by hits before the thin.
	 * If there are 2+ different thin fractions, then they get multiplied together.
	 */
	public function get_thin_extrapolation_factor()
	{
		if ( ! $this->hasrun_internal_postprocess_info_setup )
			$this->internal_postprocess_info_setup();
		return $this->thin_extrapolation_factor;
	}
	
	/**
	 * Setup function for variables stating the size of the corpus section searched within.
	 */
	private function internal_amount_of_text_searched_setup()
	{
		/*
		 * note: we have the following int vars that need setup:
			private $tokens_searched_initially = NULL;
			private $texts_searched_initially  = NULL;
			private $tokens_searched_reduced   = NULL;
			private $texts_searched_reduced    = NULL;
		 */
		// see todo notes in the declarations of those vars!
		
		/* FIRST JOB: set up the "amount initially searched" numbers. */

		$this->items_searched_initially  = $this->qscope->size_items($this->item_type_initially);
		$this->tokens_searched_initially = $this->qscope->size_tokens();

		
		/* SECOND JOB: set up the "amount searched after reduction" numbers. */
	
// not  no longer. -->
//		/* NB: we save the "scope reduction whereclause" so that it can be re-used when necessary if an "extra" needs to be added. */
		
		/* we don't do anything here if this is not a postprocessed query. */
		if (empty($this->postprocess))
		{
			$this->tokens_searched_reduced = $this->tokens_searched_initially;
			$this->items_searched_reduced  = $this->items_searched_initially;
			$this->item_type_reduced       = $this->item_type_initially;
// 			$this->scope_reduction_whereclause = '';
		}
		else
		{
			/* to get the extra category filters */
			if ( ! $this->hasrun_internal_postprocess_info_setup)
				$this->internal_postprocess_info_setup();
			
// 			$pp_where = '';
// 			foreach($this->extra_category_filters as $e)
// 			{
// 				list($class, $cat) = explode('~', $e);
// 				$pp_where .= " && (`$class` = '$cat') ";
// 			}
			
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// EXPLANATION
// -----------
// (note sure if here is the place to explain this, but it will do.)
//
// the postprocess "dist" currently only works with text metadata. That means that, if it is carried out on a query
// whose scope is based on text metadata, we can find out reduced-amount-of-text-searched by combining together the extra class/cat
// thingies with the text-meta "where" from the scope. 
//
// HOWEVER, if the scope is not based on a whole number of texts, (be that Subcorpus or Restriction), then this approach won't work.
// LIKEWISE, in the future when we allow dist-reduction by things other than class/cat analysis, then this approach won't work.
// 
// the way it will have to work ultimately is, if either side brings something non-text-based to the table, then we need to
// create a new scope: a scope-intersect, if you will, by taking the existing scope and creating a new one from another.
// e.g. something not dissimilar to this: 
//
// $scope_after_reduction = QueryScope::new_by_intersect($this->qscope, $a_QueryScope_containing_a_Restriction_based_on_dist_reductions);
// $this->tokens_searched_reduced = $scope_after_reduction->size_tokens();
// etc.
// and use this mechanism in every case.
// 
// However, this is more than we have time for now. For now, we ASSUME the dist-postprocess is still text-meta-only.
// And we only allow it to be merged with the original QueryScope when that qscope consists of a complete set of texts, so that
// the text-metadata table can be used.
//
// SO: what does that imply we should do here?
// We need to ask the QueryScope if it consists purely of texts. If it does we can ask about the intersection with the 
// If it doesn't, we need to ..... what? exiterrror? YES: for now, since it means we have a dist postprocess + a non-text Scope, which
// ought not to be possible at present. 

			if (!empty($this->extra_category_filters))
			{
				$x = $this->qscope->size_of_classification_intersect($this->extra_category_filters);
				if (is_array($x))
				{		
					list($this->tokens_searched_reduced, $this->items_searched_reduced) = $x;
					$this->item_type_reduced = 'text';
					//TODO the above is always true at the moment.
				}
				else
					exiterror("TEMPORARY LOGIC ERROR: it should not be possible to have a dist postprocess combined with a non-textmeta queryscope.");
			}

			

// 			if (QueryScope::TYPE_SUBCORPUS == $this->qscope->type)
// 			{
// 				/* now translate our info regarding the subcorpus into restrictions for a text metadata query */
// 				$sc_conditions = $sc_record->temp_get_sql_cond_for_text_metadata(); // need thisn for restriction as well, needds to be dispatchable by Qscope.
// // 				if (! empty($sc_record->restrictions))
// // 					$sc_conditions = $sc_record->restrictions;
// // 				else
// // 					$sc_conditions = translate_itemlist_to_where($sc_record->get_item_list());
				
// 				$this->scope_reduction_whereclause = " where $sc_conditions $pp_where ";
// 			}
// 			else if ($this->restrictions != '')
// 				$this->scope_reduction_whereclause = " where {$this->restrictions} $pp_where "; /// broken
// 	 		else
// 		 		$this->scope_reduction_whereclause = (empty($pp_where) ? '' : preg_replace('|^ &&|', ' where ', $pp_where));
	 		
// 		 	list($this->tokens_searched_reduced, $this->texts_searched_reduced) 
// 		 		= mysqli_fetch_row(do_sql_query(
// 		 			"select sum(words), count(*) from text_metadata_for_{$this->corpus} {$this->scope_reduction_whereclause}")) ;
		}
		
		$this->hasrun_internal_amount_of_text_searched_setup = true;
	}
	
	
	
	
	/**
	 * Gives the size of the corpus/subcorpus searched when the query was initially run, in N of tokens.
	 */
	public function get_tokens_searched_initially()
	{
		if ( ! $this->hasrun_internal_amount_of_text_searched_setup )
			$this->internal_amount_of_text_searched_setup();
		return $this->tokens_searched_initially;
	}
	/**
	 * Gives the size of the corpus/subcorpus searched when the query was initially run, in N of texts.
	 */
	public function get_items_searched_initially(&$item_type = NULL)
	{
		if ( ! $this->hasrun_internal_amount_of_text_searched_setup )
			$this->internal_amount_of_text_searched_setup();
		$item_type = $this->item_type_initially;
		return $this->items_searched_initially;
	}
	/**
	 * Gives the size of the corpus/subcorpus searched, taking into account any subsections reductions
	 * via the distribution-postprocess, in N of tokens.
	 * 
	 * If there are no such reductions, this will return the same as the corresponding "_initially" method.
	 */
	public function get_tokens_searched_reduced()
	{
		if ( ! $this->hasrun_internal_amount_of_text_searched_setup )
			$this->internal_amount_of_text_searched_setup();
		return $this->tokens_searched_reduced;
	}
	/**
	 * Gives the size of the corpus/subcorpus searched, taking into account any subsections reductions
	 * via the distribution-postprocess, in N of texts or units.
	 * 
	 * If there are no such reductions, this will return the same as the corresponding "_initially" method.
	 */
	public function get_items_searched_reduced(&$item_type = NULL)
	{
		if ( ! $this->hasrun_internal_amount_of_text_searched_setup )
			$this->internal_amount_of_text_searched_setup();
		$item_type = $this->item_type_reduced;
		return $this->items_searched_reduced;
	}
	
	/**
	 * Returns a subset of the number of tokens / texts searched by the query (reduced) with the subset
	 * determined by a set of extra text restrictions.
	 * 
	 * If the argument is empty, then the results of this are equivalent to a joint call to 
	 * get_tokens_searched_reduced() and get_items_searched_reduced().
	 * 
	 * If the qscope was not a set-of-whole-texts, returns false (this is a TODO and will need to change).
	 * 
	 * @param array $extra  An array of extra classification/category pairs as strings in the form "class~cat".
	 * @return array        Two member array: first member = n of tokens, second member = n of texts.
	 */
	public function get_search_scope_with_extra_text_restrictions($extra = NULL)
	{
		if ( ! $this->hasrun_internal_amount_of_text_searched_setup )
			$this->internal_amount_of_text_searched_setup();
		
		if (empty($extra))
			return array($this->tokens_searched_reduced, $this->items_searched_reduced);
		
		/* we need to merge the restrictions with our own extra filters from the dist postprocess */
		$all_extra = array_merge($extra, $this->extra_category_filters);
		
		sort($all_extra);
		
		return $this->qscope->size_of_classification_intersect($all_extra);
		
		/* A TODO note:
			If the query scope is the whole corpus, this can be retrieved from text_metadata values (num words/. num files)

			(or maybe this is an optimisation in retriciton? If the restriction = just one cat, don’t queryt the metadata table, 
			instead just use text metadata values...
		 */


		//
		//
		//
		//
		//
		//
		//
		//
		// TODO fix the above, obviously: we should be able to get some kind of scope reduction even when it's not all texts.
		// currently it just returns false.
		
		// the old code from before restricitons got ricockulously more complex: 
// 		$morewhere = '';
// 		foreach($extra as $e)
// 		{
// 			list($class, $cat) = explode('~', $e);
// 			$class = escape_sql($class);
// 			$cat   = escape_sql($cat);
// 			$morewhere .= " && (`$class` = '$cat') ";
// 		}
// 		$where = (empty($this->scope_reduction_whereclause) 
// 					? preg_replace('|^ &&|', ' where ', $morewhere) 
// 					: " {$this->scope_reduction_whereclause} $morewhere")
// 					; 
// 		return mysqli_fetch_row(do_sql_query("select sum(words), count(*) from text_metadata_for_{$this->corpus} $where"));
	}
	
	
	/**
	 * Gets the extra category filters, if there are any, converted into a QueryScope object (internally, a Restriction).
	 * 
	 * If there aren't any, a QueryScope of TYPE_WHOLE_CORPUS is returned.
	 */
	public function get_extra_filters_as_qscope()
	{
		if (! $this->hasrun_internal_postprocess_info_setup)
			$this->internal_postprocess_info_setup();
		if (! $this->hasrun_internal_amount_of_text_searched_setup)
			$this->internal_amount_of_text_searched_setup();
		
		if (empty($this->extra_category_filters))
			return QueryScope::new_by_unserialise('');
		else
			return QueryScope::new_by_unserialise('$^--text|' . implode('.', $this->extra_category_filters));
	}

	
// 	/**
// 	 * Gets a "restrictions" string --  i.e. something that can be included in a MySQL where-clause -- that 
// 	 * represents BOTH any original subcorpus/restrictions when the query originally ran, AND any additional
// 	 * restrictions implied by dist-type postprocesses applied to the query.
// 	 * 
// 	 * Note that this is derived from the "scope reduction whereclause" stored internally... but DOES NOT
// 	 * have the leading "where" (as per standard restrictions-string format). 
// 	 * 
// 	 * Returns false if there are no dist-type postprocesses (in that case, you can safely rely on the 
// 	 * subcorpus/restrictions members). 
// 	 * 
// 	 * (Longterm-TO DO: this is shonky and fragile (And no longer works if the QueryScope is not text-based._) 
// 	 */
// 	public function get_reduced_restrictions()
// 	{
// 		if (! $this->hasrun_internal_postprocess_info_setup)
// 			$this->internal_postprocess_info_setup();
// 		if (! $this->hasrun_internal_amount_of_text_searched_setup)
// 			$this->internal_amount_of_text_searched_setup();
		
// 		if (empty($this->scope_reduction_whereclause))
// 			return false;

// 		$str = preg_replace('/^\s*(where|WHERE)\s+/', '', $this->scope_reduction_whereclause);
		
// 		return trim($str);

// 		// NB and longterm-TO DO: the above will need a serious rethink when subsections (of ANY type)
// 		// no longer correspond to a set of entire texts!
// 		// (make subsection frequency lists from a list of integer pairs?)
// 	}
	
	
	/**
	 * Tells you whether there is at least one thin in the query's postprocess chain.
	 * If true, then the thin extrapolation factor (qv) will have been modified from its initial value iof 1.0
	 * 
	 * @return Boolean: true if there is at least one thin-postprocess.
	 */
	public function postprocess_includes_thin()
	{
		if ( ! $this->hasrun_internal_postprocess_info_setup )
			$this->internal_postprocess_info_setup();
		return $this->thin_factor_exists;
	}

	/**
	 * Returns an array of highlight positions matching the postprocess string specified.
	 *
	 * NB. "Highlight positions" = which words in concordance lines should be emphasised
	 * (usually in bold), e.g. collocating words in a collocation-thinned query, or sort
	 * key word in a sorted query.
	 *
	 * If there is no postprocess, or if the last postprocess applied is one that does not
	 * require an in-context highlight to be applied, then false is returned.
	 * 
	 * Otherwise, an array of "distance" offsets (one per query hit) is returned.
	 * 
	 * @param bool $show_tags_in_highlight  Out-parameter: whether tags should be displayed 
	 *                                      on the highlighted token.
	 * @return array                        Highlight position table (or false if none).
	 */
	public function get_highlight_position_table(&$show_tags_in_highlight)
	{
		if (empty($this->postprocess))
			return false;

		/* to get the "stack" arrays of postprocesses. */
		if ( ! $this->hasrun_internal_postprocess_info_setup)
			$this->internal_postprocess_info_setup();

		$highlight_process = end($this->postprocess_stack);

		if (strpos($highlight_process, '[') !== false)
		{
			list($process_name, $argstring) = explode('[', str_replace(']', '', $highlight_process));
			$args = explode('~', $argstring);
		}
		else
			$process_name = $highlight_process;

		/* variables which may be changed in the switch to control what happens after */
		$coll_order_by = '';
		$sql_query_from_coll = false;

		switch($process_name)
		{
		/* cases where there is a highlight */
		case 'sort':
			/* if a tag-restriction is set, show tags for the sort-word */
			$show_tags_in_highlight = ($args[1] != '.*');

			/* return an array with the sort position as many times as the query has hits ;
			 * return it straight away without going through SQL stuff at end of this function */
			return array_fill(0, (int)end($this->hits_left_stack), (int)$args[0]);

		case 'rand':
			//TODO : fix rand
			// take the rand off
			// is what's left a coll?
			// if not, return false
			// if it is, put its args into args; set a flag to say that a sort using the cqp algorithm must be applied to the array that we get back
			// $sql_query_from_coll = true;
			break;

		case 'unrand':
			//TODO : fix unrand
			// take the unrand off
			// is what's left a coll?
			// if not, return false
			// if it is, put its args into args; $coll_order_by = 'order by beginPosition'
			// $sql_query_from_coll = true;
			break;

		// TODO
		// actually, for both rand and unrand, it would be better to remove the rand or unrand, then return the results
		// of simply calling this function again.

		case 'coll':
			$sql_query_from_coll = true;
			break;

		/* case dist, case thin, case custom, (or...) an additional rand, a syntax error, or anything else */
		default:
			/* no highlight in these cases. */
			return false;
			/* note that arguably, "custom" should be possible to do...
			 * e.g. by using the "keyword" field as an indication of what should be highlit.
			 *
			 * But this can be added later, if necessary. */
		}

		/* this is out here in an IF so that three different cases can access it */
		if ($sql_query_from_coll)
		{
			if (count($args) != 6)
				return false;

			if (empty($args[5]))
				$tag_filter_clause = '';
			else
			{
				$is_regex = (bool)preg_match('/\W/', $args[5]);
				$op = ($is_regex ? 'REGEXP' : '=');
				$filter = ($is_regex ? regex_add_anchors($args[5]) : $args[5]);
				$tag_filter_att = get_corpus_metadata($this->corpus, 'primary_annotation');
				$tag_filter_clause = "AND $tag_filter_att $op '$filter'";
			}

			$sql = "select dist from {$args[0]}
				where {$args[1]} = '{$args[2]}'
				$tag_filter_clause
				and dist between {$args[3]} and {$args[4]}
				$coll_order_by";
		}

		if (isset($sql))
		{
			$result = do_sql_query($sql);

			$highlight_positions = array();
			while ($r = mysqli_fetch_row($result))
				$highlight_positions[] = $r[0];

			return $highlight_positions;
		}
		else
			return false;
	}
	
	
	/**
	 * Analyses the postprocess and hits_left fields to set up certain internal info fields.
	 * 
	 * Note, those members variables are not automatically re-set if the postprocess/hits_left are changed
	 * externally (at least, not yet: it will become possible to do so once the postprocess and hits_left member
	 * variables can be made private). So, we provide a separate funciton to force setup to be re-run.
	 * Just in case!
	 * 
	 * @see QueryRecord::reset_internal_info
	 */
	private function internal_postprocess_info_setup()
	{
		/*
		 * The THINNING EXTRAPOLATION FACTOR = 1 over THIN FRACTION.
		 * The THIN FRACTION = hits after the thin, divided by hits before the thin.
		 * If there are N different thin fractions, then they get multiplied together.
		 */
		$this->thin_factor_exists = false;
		$this->thin_extrapolation_factor = 1.0;
		/*
		 * PLUS, we also need to set up a list of any EXTRA RESTRICTIONS added by the distribution-kind
		 * of postprocess (in the format of an array of "class~cat" strings.
		 */
		$this->extra_category_filters = array();
		
		/* populate the exploded-arrays */
		$this->hits_left_stack    = ('' === $this->hits_left   ? array() : explode('~',  $this->hits_left));
		
		// NOTE. This old version meant that empty strings inside dist arguments would ALSO cause a split. 
		// So, new version based on regex.
// 		$this->postprocess_stack  = ('' === $this->postprocess ? array() : explode('~~', $this->postprocess));
		if (0 == preg_match_all('/\b\w+(\[[^\]]+\])?/', $this->postprocess, $m))
			$this->postprocess_stack = array();
		else
			$this->postprocess_stack = $m[0];

		/* we have to loop our way through the postprocesses in order to work out the thin-factor... */

		/* create an index into each of the above */
		$hl_ix = 0;
		$p_ix = 0;
		while (isset($this->postprocess_stack[$p_ix]))
		{
			/* we use @ here to suppress warning messages if there is no [arg block] in the postprocess string. */
			@list ($pp, $args) = explode('[', rtrim($this->postprocess_stack[$p_ix], ']'));
			switch($pp)
			{
			case 'thin':
				$this->thin_factor_exists = true;
				list($hits_after) = explode('~', $args);
				$hits_before = ($hl_ix == 0 ? $this->hits : (int) $this->hits_left_stack[$hl_ix-1]);
				$this->thin_extrapolation_factor *= $hits_before/$hits_after;
				break;
	
			case 'dist':
				/* these strings are in the "class~cat" format that is stored in the DB for "dist[...]" */
				$this->extra_category_filters[] = $args;
				/* we *do not* amend the thin-extrapolation in the case of extra-restrictions. */
				break;
	
			/* finally: handle the cases where $hl_ix shouldn't be advanced. */
			case 'rand':
			case 'unrand':
			case 'upload':
				--$hl_ix;
				break;
			}
		
			/* roll indices around for the next postprocess */
			++$p_ix;
			++$hl_ix;
		}
		
		$this->hasrun_internal_postprocess_info_setup = true;
	}
	
	/**
	 * Force-reset function for all internally-deduced variables.
	 * 
	 * Should be called if the user of the object has overwritten 
	 * the source-variables (i.e. the strings-from-database).
	 */
	public function reset_internal_info()
	{
		$this->hasrun_internal_postprocess_info_setup = false;
		$this->hasrun_internal_amount_of_text_searched_setup = false;
		/* add more here as necessary */
	}
	
	/**
	 * Touches the DB record of this query without changing anything else.
	 * 
	 * @return bool  True for success, false for failure.
	 */
	public function touch()
	{
		$this->set_time_to_now();
		
		do_sql_query("update saved_queries set time_of_query = {$this->time_of_query} where query_name = '{$this->qname}'");

		return (1 == get_sql_affected_rows());
	}
	
	/**
	 * Returns the number of hits that were in the query when it was first run
	 * (or, originally created by whatever means).
	 */
	public function hits_originally()
	{
		return $this->hits;
	}
	
	/**
	 * Returns the number of hits in the query at present 
	 * (i.e. taking into account any effects of postprocessing).
	 */
	public function hits()
	{
		if (empty($this->hits_left))
			return $this->hits;
		else
		{
			$arr = explode('~', $this->hits_left);
			return (int) end($arr);
		}
	}
	
	
	
	
	/*
	 * =======================================
	 * METHODS FOR PRODUCING PRINTABLE OUTPUTS
	 * =======================================
	 */
	
	
	
	
	/**
	 * Provides a printable string version of this query's "time" value. 
	 * The format used is that of CQPWEB_UI_DATE_FORMAT.
	 * 
	 * @see QueryRecord::$time_of_query
	 */
	public function print_time()
	{
		return date(CQPWEB_UI_DATE_FORMAT, $this->time_of_query);
	}

	
	/** 
	 * Get printable HTML string that can be used in the header bar of various pages;
	 * prefix can be added to replace the "Your query ABC returned..." with "PREFIX query ABC returned...";
	 * information about the size of the corpus is optional, pass false as 2nd argument to turn off.
	 * 
	 * To get plaintext instead of HTML, pass false as 3rd argument. 
	 */
	public function print_solution_heading($prefix = 'Your', $include_corpus_size = true, $html = true)
	{
		$final_string = trim($prefix);
		$addcap = empty($final_string);

		if ($this->query_mode == 'uploaded')
			$final_string .= ' uploaded query';
		else
		{
			$final_string .= ' query ' . ($html?'&ldquo;':'"');
		
			if ('cqp' == $this->query_mode || empty($this->simple_query))
				$final_string .= ($html ? escape_html($this->cqp_query)    : $this->cqp_query);
			else
				$final_string .= ($html ? escape_html($this->simple_query) : $this->simple_query);
			
			$final_string .= ($html?'&rdquo;':'"');
			
			if ('sq_case' == $this->query_mode)
				$final_string .= ' (case-sensitive)';
		}
		if ($addcap)
			$final_string = preg_replace_callback('/^ ([qu])/', function($m) {return strtoupper($m[1]);}, ($final_string));
		
		$desc = $this->qscope->print_description($html);
		if (!empty($desc))
			$final_string .= ', ' . $desc . ', ';
		
		$final_string .= ' returned ' . number_format((float)$this->hits) . ' matches';
		
		
		if ($this->hit_texts > 1)
			$final_string .= ' in ' . number_format((float)$this->hit_texts) . ' different texts';
		else
			$final_string .= ' in 1 text';
		
		
		/* default is yes, but it can be overidden and left out eg for collocation */
		if ($include_corpus_size)
		{
			/* find out total amount of text searched (with either a restriction or a subcorpus) */
			$num_of_words_searched = $this->get_tokens_searched_initially();
			$item_type_initially = NULL;
			$num_of_items_searched = $this->get_items_searched_initially($item_type_initially);
			$items_pl = (1 < $num_of_items_searched ? 's' : ''); /* pluraliser for word "unit" */

			if ($num_of_words_searched == 0)
				$num_of_words_searched = 0.1;
				/* this should never happen, but let's not have any problems with div-by-zero */
			
			/* NB. The following is v. similar to a QueryScope/Restriction/Subcorpus method, 
			 * print_size_items/print_size_tokens; but we do it again here, because of the
			 * possibility of a whole-corpus scope, where the QueryRecord has the right numbers stored
			 * but the QueryScope might not. (Something to possibly look into might be streamlining all this...) */
			if ('text' == $item_type_initially)
				$itemdesc = 'text' . $items_pl;
			else if('@' == $item_type_initially)
				$itemdesc = 'segment' . $items_pl . ' of the corpus';
			else
			{
				$xinfo = get_xml_info($this->corpus, $item_type_initially);
				$itemdesc = ($html?'<em>':'*') . $xinfo->description . ($html?'</em> unit':'* unit') . $items_pl;
			}

			$final_string .= ' (in ' . number_format((float)$num_of_words_searched) . ' words ['
						. number_format((float)$num_of_items_searched) . ' ' . $itemdesc . ']; frequency: '
						. number_format(($this->hits / $num_of_words_searched) * 1000000.0, 2)
						. ' instances per million words)'
						;
		}
	
		/* add postprocessing comments */
		$final_string .= $this->print_postprocess_description($html);
	
		return $final_string;
	}
	
	/**
	 * Returns a printable (HTML) description of all the things that have been done
	 * to this query in postprocessing.
	 * 
	 * @param  bool $html  Whether to return HTML or plain text (default = true = HTML).
	 * @return string      The printable string.
	 */
	public function print_postprocess_description($html = true)
	{
		if (empty($this->postprocess))
			return '';
		/* run this to get the exploded stacks */
		if ($this->hasrun_internal_postprocess_info_setup)
			$this->internal_postprocess_info_setup();
		
		$r_2_l = (bool) get_corpus_metadata($this->corpus, 'main_script_is_r2l');

		/* bdo tags ensure that l-to-r goes back to normal after an Arabic (etc) string */
		if ($html)
		{
			$em = '<em>';
			$slashem = '</em>';
			$bdo_tag1 = ($r_2_l ? '<bdo dir="ltr">' : '');
			$bdo_tag2 = ($r_2_l ? '</bdo>' : '');
		}
		else
		{
			$em = $slashem = '*';
			$bdo_tag1 = $bdo_tag2 = '';
		}
		
		$description = '';
		
		/* index into hits-left-stack */
		$i = 0;
		
		$annotations = list_corpus_annotations($this->corpus);
		
		foreach($this->postprocess_stack as $p)
		{
			$description .= ', ';

			if (false !== strpos($p, '[') )
			{
				list($current_process, $argstring) = explode('[', str_replace(']', '', $p));
				$args = explode('~', $argstring);
			}
			else
				$current_process = $p;

			switch ($current_process)
			{
			case 'upload':
				/* this is a dummy postprocess, so delete the last-added ', ', and loop to next */
				$description = preg_replace('/, $/', '', $description);
				break;

			case 'sort':
				$description .= 'sorted on ' . $em
					. ($args[0] == 0 ?  "node word"
						: ($args[0] > 0 ? "position +{$args[0]}"
							: "position {$args[0]}") )
					.  $slashem . ' ';

				if ($args[3] != '.*')
					$description .= ($args[4] == 1 ? 'not ' : '')
						. 'starting with ' . $em
						. $args[3]
						. '-' . $slashem . ' '
						;

				if ($args[1] != '.*')
					$description .= 'with tag-restriction ' . $em
						. ($args[2] == 1 ? 'exclude ' : '')
						. $args[1]
						. $slashem . ' '
						;

				$description .= $bdo_tag1 . '(' . number_format((float)$this->hits_left_stack[$i]) . ' hits)' . $bdo_tag2;
				$i++;

				break;

			case 'coll':
				$att_id = ($args[1] == 'word' ? '' : $annotations[$args[1]]);
				$description .= "collocating with $att_id $em{$args[2]}$slashem"
					. ( empty($args[5]) ? '' : " with tag restriction $em{$args[5]}$slashem" )
					. " $bdo_tag1("
					. number_format((float)$this->hits_left_stack[$i]) . ' hits)'. $bdo_tag2;
				$i++;
				break;

			case 'rand':
				$description .= 'ordered randomly';
				break;

			case 'unrand':
				$description .= 'sorted into corpus order';
				break;

			case 'thin':
				$method = ($args[1] == 'r' ? 'random selection' : 'random selection (non-reproducible)');
				$count = number_format((float)$this->hits_left_stack[$i]);
				$description .= "thinned with method $em$method$slashem to $count hits";
				$i++;
				break;

			case 'cat':
				$description.= "manually categorised as &ldquo;{$args[0]}&rdquo; ("
					. number_format((float)$this->hits_left_stack[$i]) . " hits)";
				$i++;
				break;

			case 'item':
				$description .= "reduced to results where ";
				if (0 == $args[0])
					$description .= 'query node matches ';
				else
					$description .= 'concordance position ' . $em  . stringise_integer_position($args) . $slashem . ' matches ';
				if (empty ($args[1]))
					$description .= 'tag: ' . $em . $args[2] . $slashem . ' ';
				else if (empty($args[2]))
					$description .= 'word: ' . $em  . $args[1] . $slashem . ' ';
				else
					$description .= 'word-tag combination: ' . $em  . $args[1] . '_' . $args[2] . $slashem . ' ';
				// TODO if the node is 2 words, then word-and-tag has both words 
				// then underscore, then both tags..... 
				// So the above ends up looking funny...
				$description .= '(' . number_format((float)$this->hits_left_stack[$i]) . ' hits)';
				$i++;
				break;

			case 'dist':
				$labels = expand_text_metadata_attribute($this->corpus, $args[0], $args[1]);
				$description .= "distribution over $em{$labels['field']} : {$labels['value']}$slashem ";
				$description .= '(' . number_format((float)$this->hits_left_stack[$i]) . ' hits)';
				$i++;
				break;

			case 'text':
				$description .= "occurrences in text $em{$args[0]}$slashem ";
				$description .= '(' . number_format((float)$this->hits_left_stack[$i]) . ' hits)';
				$i++;
				break;

			case 'custom':
				$record = retrieve_plugin_info($args[0]);
				$obj = new $record->class($record->path);
				/* custom PP descs are allowed to be empty, in which case, we just add the new number of hits. */
				$description .= 
					$obj->get_postprocess_description($html) . " $bdo_tag1(" . number_format((float)$this->hits_left_stack[$i]) . ' hits)'. $bdo_tag2;
				$i++;
				unset($obj);
				break;
		
			default:
				/* malformed postprocess string; so add an error to the return */
				$description .= '?????????????';
				break;
			}
		}

		return $description;
	}
	
}
/*
 * end of class QueryRecord
 */




/*
 * The rest of this file contains miscellaneous cache functions.
 */




/** Returns true if a CQP temp file exists with that qname in its filename, otherwise false */
function cqp_file_exists($qname)
{
	return file_exists(cqp_file_path($qname));
}


/** Returns size of a CQP temp file (including 0 if said file existeth not). */
function cqp_file_sizeof($qname)
{
	$s = filesize(cqp_file_path($qname));
	return ( $s === false ? 0 : $s );
}

/**
 * Removes any CQP-query file in the cache with $qname in its filename after
 * the ":" (i.e. works across corpora); returns true if the file existed and
 * was deleted, false if it did not exist or if deletion failed.
 */
function cqp_file_unlink($qname)
{
	if (false === ($f = cqp_file_path($qname)))
		return false;

	if ( !file_exists($f) )
		return false;

	if ( ! @unlink($f) ) /* block PHP warning for file not exists. */
	{
		squawk("file for query $qname, --> $f, but could not due to race condition between file stat and file unlink.");
		return false;
	}

	return true;
}

/**
 * Copies the cache file corresponding to oldqname as newqname; if a file
 * relating to newqname exists already, it will NOT be overwritten.
 * 
 * @return bool  True on success and false on failure.
 */
function cqp_file_copy($oldqname, $newqname)
{
	$of = cqp_file_path($oldqname);
	$nf = preg_replace("/:$oldqname\z/", ":$newqname", $of);
	if ( file_exists($of) && ! file_exists($nf) )
		return copy($of, $nf);
	else
		return false;
}

/**
 * Returns the path on disk of the cache file corresponding to the specified
 * query identifier (qname), or false if no such file appears to exist.
 * 
 * Works across corpora.
 */
function cqp_file_path($qname)
{
	global $Config;
	
	$globbed = glob("{$Config->dir->cache}/*:$qname");
	if (empty($globbed))
		return false;
	else
		return $globbed[0];
}





/**
 * Checks whether a file has the correct CQP format for saved queries
 * - that is, that it begins with the magic number that all CQP query files have.
 * 
 * Can also potentially check the two strings subsequently embedded. 
 * 
 * @param  string $path_or_qname            A path, OR a query name to be looked up in the normal place.
 * @param  bool $is_path                    If true (default), first parameter is treated as filesystem path.
 *                                          If false, first parameter is treated as a query name handle, from which
 *                                          a path must be deduced. 
 * @param  bool $also_check_env_registry    If true, as well as the magic number, the registry directory embedded after
 *                                          that is checked for its match with the path in $Config->dir->registry.
 * @param  bool $also_check_env_corpus      If true, as well as the magic number AND the registry path, the corpus handle 
 *                                          embedded after both is checked for its match with the string $Corpus->cqp_name.
 *                                          Note this implies the truth of $also_check_env_registry.
 * @return bool                             True if the file exists, is readable and has correct CQP format (by magic number).array
 *                                          Otherwise false.
 */
function cqp_file_check_format($path_or_qname, $is_path = true, $also_check_env_registry = true, $also_check_env_corpus = true)
{
	if ($is_path)
		$path = $path_or_qname;
	else
	{
		if (false === ($path = cqp_file_path($path_or_qname)))
			return false;
	}
	
	if ( ! is_readable($path) || false === ($src = fopen($path, 'r')))
		return false;
	
	if (CQP_INTERFACE_FILE_MAGIC_NUMBER != fread($src, 4))
		return false;
	
	
	if ($also_check_env_registry || $also_check_env_corpus)
	{
		/* read reg string, check it matches, return false if not */
		$registry = '';
		while (0 != ord($c = fgetc($src)))
			$registry .= $c;
		
		global $Config;
		
		/* we use real path becasue the config object may contain extra slashes, etc. */
		if (realpath($registry) != realpath($Config->dir->registry))
		{
			fclose($src);
			return false;
		}
	}
	if ($also_check_env_corpus)
	{
		/* read corpus string, check it matches, return false if not */
		$CORPUS_in_file = '';
		while (0 != ord($c = fgetc($src)))
			$CORPUS_in_file .= $c;
		
		global $Corpus;
		
		if ($CORPUS_in_file != $Corpus->cqp_name)
		{
			fclose($src);
			return false;
		}
	}

	/* if we've got here, everything is OK. */
	fclose($src);
	return true;
}

/**
 * Deletes a specified file from the cache directory, unconditionally.
 * 
 * If passed an array rather than a single filename, it will iterate across 
 * the array, deleting each specified file.
 * 
 * Like delete_stray_cache_entry(), this is designed for cleanup of cache 
 * entities that have "leaked" by becoming disconnected from one another.
 * 
 * @see delete_stray_cache_entry()
 */
function delete_stray_cache_file($filename)
{
	global $Config;

	if (! is_array($filename))
		$filename = array($filename);

	foreach ($filename as $f)
	{
		$path = "{$Config->dir->cache}/$f";

		if (is_file($path))
			unlink($path);
	}
}

/**
 * Deletes a query record from the cache table, unconditionally.
 * 
 * If passed an array rather than a single query name, it will iterate across
 * the array, deleting each specified query record.
 * 
 * @see delete_stray_cache_file()
 */
function delete_stray_cache_entry($qname)
{
	if (!is_array($qname))
		$qname = array($qname);

	foreach ($qname as $q)
		do_sql_query("DELETE from saved_queries where query_name = '" . escape_sql($q) . '\'');
}





/** 
 * Makes sure that the name you are about to put into cache is unique.
 * 
 * Keeps adding random letters to the end of it if it is not.
 * By "Unique" we mean "unique across all corpora"; since all new qnames
 * should be based on the instance name, and the instance name should be
 * time-unique on a microsecond scale, this is really just belt-and-braces.
 * 
 * Typical usage: $qname = qname_unique($qname); 
 */
function qname_unique($qname)
{
	while (true)
	{
		$sql = 'select query_name from saved_queries where query_name = \'' . escape_sql($qname) . '\' limit 1';

		if (0 == mysqli_num_rows(do_sql_query($sql)))
			break;

		$qname .= chr(random_int(0x41,0x5a));
	}
	return $qname;
}



/**
 * Gets a flat arrya of qnames that exist, potentially limited by the corpus;
 * also if a username is specified, it'll be limited to saved queries only.
 */
function list_cached_queries($corpus = '', $username= '')
{
	$where = array();
	
	if (!empty($corpus))
	{
		$corpus = escape_sql($corpus);
		$where[] = " corpus = '$corpus' ";
	}
	if (!empty($username))
	{
		$username = escape_sql($username);
		$where[] = " user = '$username' ";
		$where[] = " saved = " . CACHE_STATUS_SAVED_BY_USER;
	}
	
	$where_sql = empty($where) ? '' : ' where ' . implode(' and ', $where);
	
	$result = do_sql_query("select query_name from saved_queries $where_sql");
	
	$queries = array();
	
	while ($r = mysqli_fetch_row($result))
		$queries[] = $r[0];
	
	return $queries;
}



/**
 * Delete a single, specified query from cache. 
 *
 * Note that qname is unique across corpora.
 *
 * @return bool  true iff the file and DB entry were both deleted; otherwise false.
 */
function delete_cached_query($qname)
{
	$file_deleted = cqp_file_unlink($qname);
	$qname = escape_sql($qname);

	do_sql_query("delete from saved_queries where query_name = '$qname'");
	$entry_deleted = ( 1 ==  get_sql_affected_rows() );

	return $entry_deleted && $file_deleted;
}




/**
 * Duplicates a query in cache under a new query name identifier.
 * 
 * @param  string $oldqname  The identfier of the existing query to duplicate. Must be a valid identifier that is in the DB.
 * @param  string $newqname  The new identifier of the query to create. Must be a valid identifier not in the DB.
 * @param  string $err_msg   Error message out-parameter (will explain the reason for a "false" return).
 * @return QueryRecord       QueryRecord object for the new query if copied correctly, otherwise false.
 *                           Possible reasons for false: (a) a query already exists with the specified new name.
 *                           (b) No query exists in cache with the specified existing name.
 *                           (c) The same string has been supplied for both parameters.
 */
function copy_cached_query($oldqname, $newqname, &$err_msg = NULL)
{
	if ($oldqname == $newqname)
	{
		$err_msg = "New qname and old qname are the same.";
		return false;
	}
	
	/* doesn't copy if the $newqname already exists */
	if (QueryRecord::new_from_qname($newqname))
	{
		$err_msg = "New qname already exists.";
		return false;
	}
	
	/* or indeed if the oldqname doesn't exist */
	if (!($q = QueryRecord::new_from_qname($oldqname)))
	{
		$err_msg = "Old qname could not be found in cache.";
		return false;
	}
	
	/* copy the file */
	cqp_file_copy($oldqname, $newqname);
	
	/* copy the query record */
	$create = clone $q;
	$create->qname = $newqname;
	$create->save();
	
	return $create;
}





/**
 * Checks whether a query exists in cache, based on itys qname.
 * 
 * This can also be checked by trying to create a QueryRecord using the new_from_qname method.
 * However, this function does not have the overhead of setting up an entire QR (including, 
 * possibly, a QueryScope!) 
 * 
 * This function works by checking the saved_queries table. It does not check whether the 
 * actual CQP file exists!
 * 
 * @see                   cqp_file_exists()
 * @param  string $qname  The query ID to check.
 * @return bool           True if the cache contains a query with the given name.
 */
function check_cached_query($qname)
{
	$qname = escape_sql($qname);
	$result = do_sql_query("select query_name from saved_queries where query_name = '$qname'");
	return (mysqli_num_rows($result) > 0);
}



/** 
 * Does nothing to the specified query, but refreshes its time_of_query to = now (and logs that in the DB).
 * 
 * Note: the QueryRecord object has this ability too, but this function is simpler, so we save it for
 * when a touch is needed without having to set up the record.
 */
function touch_cached_query($qname)
{
	$qname = cqpweb_handle_enforce($qname);
	
	$time_now = time();
	
	do_sql_query("update saved_queries set time_of_query = $time_now where query_name = '$qname'");
}





/**
 * Delete cached queries if the limit (set in global config) has been reached.
 *
 * Does not delete user-saved queries UNLESS passed "false" as an argument.
 *
 * Does nothing if the cache is not full.
 */
function delete_cache_overflow($protect_user_saved = true)
{
	global $User;
	global $Config;

	$attempts = 0;
	$max_attempts_by_non_admin = 5;

	while (true)
	{
		/* step one: how many bytes in size is the CQP cache RIGHT NOW? */
		$current_size = get_sql_value("select sum(file_size) from saved_queries");

		if ($current_size <= $Config->query_cache_size_limit)
			break;

		/* otherwise, the cache has exceeded its size limit, ergo: */

		/* step two: get a list of deletable files (10 at a time: if all are pulled at once, 
		 * there are race conditions on the deleting.) */
		$sql = "select query_name, file_size from saved_queries"
					. ($protect_user_saved ? " where saved = ".CACHE_STATUS_UNSAVED : "")
					. " order by time_of_query asc limit 10"
					;

		$del_result = do_sql_query($sql);

		if($del_result->num_rows < 1)
			exiterror(array(
					"CRITICAL ERROR - QUERY CACHE OVERLOAD!\n",
					"CQPweb tried to clear cache space but failed!\n",
					"Please report this error to the system administrator."
				));
// TODO this func now runs disconnected; should the above just squawk and break???? Or return???

		/* step three: delete queries from the list until we've deleted enough */
		while ($current_size > $Config->query_cache_size_limit)
			if (!($del = mysqli_fetch_object($del_result)) )
				break;
			else
				if (delete_cached_query($del->query_name))
					$current_size -= $del->file_size;

		/* have the above deletions done the trick?
		 * If they have, the next loop will make that apparent.
		 * But we need to make sure that the loop is not eternal. */
		if (!$User->is_admin())
		{
			$attempts++;
			if ($attempts > $max_attempts_by_non_admin)
				break;
		}
		/* we trust admin users. */
	}
}






/** Completely nuke the entire query cache: deletes all non-user-saved temp files, and removes their record from the saved_queries table */
function clear_cache()
{
	global $User;
	
	if ($User->is_admin())
	{
		/* this function can take a long time to run, so turn off the limits */
		php_execute_time_unlimit();
		
		/* get a list of deletable queries */
		$del_result = do_sql_query("select query_name from saved_queries where saved = " . CACHE_STATUS_UNSAVED);
		
		/* delete queries */
		while ($current_del_row = mysqli_fetch_row($del_result))
			delete_cached_query($current_del_row[0]);
		
		php_execute_time_relimit();
	}
	
	/* else do nothing because non-admin users aren't allowed to do this. */
}




/**
 * Checks a proposed save name to see if it is in use (for this user in this corpus). 
 * 
 * NOTE: this checks a *save name*, not a *query name* (qname) identifier.
 * Save names cannot be duplicated within a (user + corpus) combination,
 * but can be non-unique globally (whereas the query name is a unique key).
 * 
 * @param  string $save_name  Name to check.
 * @return bool               True if the save name is already in use; otherwise false.
 */
function save_name_in_use($save_name)
{
	global $User;
	global $Corpus;

	$save_name = escape_sql($save_name);
	
	$result = do_sql_query("select query_name from saved_queries 
								where corpus = '{$Corpus->name}' and user = '{$User->username}' and save_name = '$save_name'");
	
	return (mysqli_num_rows($result) > 0);
}










/*
 * ===================================
 * CATEGORISED QUERY RELATED FUNCTIONS
 * ===================================
 */


/**
 * Given the name of a categorised query, this function returns an array of 
 * names of categories that exist in that query.
 */
function catquery_list_categories($qname)
{
	$result = do_sql_query("select category_list from saved_catqueries where catquery_name='". escape_sql($qname).'\'');
	list($list) = mysqli_fetch_row($result);
	return explode('|', $list);
}


/**
 * Returns an array of category values for a given catquery, with ints 
 * (reference numbers) indexing strings (category names).
 *
 * The from and to parameters specify the range of refnumbers in the catquery
 * that is desired to be returned; they are to be INCLUSIVE.
 */
function catquery_get_categorisation_table($qname, $from, $to)
{
	/* find out the dbname from the saved_catqueries table */
	$dbname = catquery_find_dbname($qname);
	
	$from = (int)$from;
	$to   = (int)$to;
	
	$result = do_sql_query("select refnumber, category from $dbname where refnumber >= $from and refnumber <= $to");
			
	$a = array();
	while ($row = mysqli_fetch_row($result)) 
		$a[(int)$row[0]] = $row[1];
	
	return $a;
}


/**
 * Returns a string containing the dbname associated with the given catquery.
 */
function catquery_find_dbname($qname)
{
	$qname = escape_sql($qname);
	$result = do_sql_query("select dbname from saved_catqueries where catquery_name ='$qname'");
	
	if (mysqli_num_rows($result) < 1)
		exiterror("The categorised query ``$qname'' could not be found in the database.");
	list($dbname) = mysqli_fetch_row($result);

	return $dbname;
}





/*
 * ===========================
 * CATEGORISED-QUERY FUNCTIONS
 * =========================== 
 */

// tODO, change names of these to catquery_ (etc)


function catquery_create($src_qname, $savename, $category_list, $default_cat = '')
{
	global $Config;
	global $User;
	global $Corpus;


	if (!QueryRecord::new_from_qname($src_qname))
		exiterror("The specified query $src_qname was not found in cache!");
	
	
	$cat_string = '';
	foreach($category_list as $thiscat)
	{
		$thiscat = cqpweb_handle_enforce($thiscat);
		/* skip any zero-length cats;  skip the defaults if they have been entered */
		if (empty($thiscat) || $thiscat == 'other' || $thiscat == 'unclear')
			continue;

		/* this cat is OK! */
		$cat_string .= '|' . $thiscat;
	}
	$cat_string .= '|other|unclear';
	$cat_string = ltrim($cat_string, '|');


	/* save the current query using a new qname name that was set for categorised query */
	$newqname = qname_unique($Config->instance_name);

	if (!($record = copy_cached_query($src_qname, $newqname)))
		exiterror("Unable to copy query data for new categorised query!");
	
	/* get the query record for the newly-saved query */
	
	/* and update it */
	$record->user = $User->username;
	$record->saved = CACHE_STATUS_CATEGORISED;
	$record->save_name = $savename;
	$record->set_time_to_now();
	$record->save();
	
	/* and refresh CQP's listing  of queries in the cache directory */
	refresh_directory_global_cqp();

	
	/* create a db for the categorisation */
	$dbname = create_db(new DbType(DB_TYPE_CATQUERY), $newqname, $record->cqp_query, $record->query_scope, $record->postprocess);

	/* if there is a default category, set that default on every line */
	if (!empty($default_cat))
	{
		$default_cat = escape_sql($default_cat);
		do_sql_query("update $dbname set category = '$default_cat'");
	}


	/* create a record in saved_catqueries that links the query and the db */
	$sql = "insert into saved_catqueries (catquery_name, user, corpus, dbname, category_list) 
					values ('$newqname', '{$User->username}', '{$Corpus->name}', '$dbname', '$cat_string')";
	do_sql_query($sql);
	
	return $newqname;
}




/**  delete the database, the cached query, and the record in saved_catqueries */
function catquery_delete($qname)
{
	list($dbname) = mysqli_fetch_row(do_sql_query("select dbname from saved_catqueries where catquery_name = '$qname'"));

	delete_db($dbname);

	delete_cached_query($qname);
	
	do_sql_query("delete from saved_catqueries where catquery_name = '$qname'");
}




function catquery_add_new_value($qname, $new_cat)
{
	if (! cqpweb_handle_check($new_cat))
		exiterror('The category name you tried to add contains spaces or punctuation. '
					. 'Category labels can only contain unaccented letters, digits, and the underscore.');
	if (99 < strlen($new_cat))
		exiterror('The category name you tried to add is too long. Category labels can only be 99 letters long at most.');
// TODO - is that still true?

	/* get the current list of categories */
	$category_list = catquery_list_categories($qname);
	
	/* adjust the category list */
	if (in_array($new_cat, $category_list))
		return;
	foreach($category_list as $i => $c)
		if ($c == 'other' || $c == 'unclear')
			unset($category_list[$i]);
	$category_list[] = $new_cat;
	$category_list[] = 'other';
	$category_list[] = 'unclear';
	
	$cat_list_string = implode('|', $category_list);
	
	do_sql_query("update saved_catqueries set category_list = '$cat_list_string' where catquery_name='$qname'");
	
	/* and finish by ... */
	$dbname = catquery_find_dbname($qname);
	touch_db($dbname);
	touch_cached_query($qname);
}


/**
 * Updates a categories query by changing the categories of concordance items 
 * according to a provided list of new values.
 * @param string $qname            Query name.
 * @param array  $update_map       Map where the key is the refnumber in the query, 
 *                                 and the value is the new desired category.
 */
function catquery_update($qname, $update_map)
{	
	$dbname = catquery_find_dbname($qname);
	
	foreach ($update_map as $ref => $val)
	{
		$ref = (int)$ref;
		$selected_cat = preg_replace('/\W/', '', $val);
		/* the above is easier than escape_sql because we KNOW that all real cats are \w-only by definition. */
		
		/* don't update if all we've been passed for this concordance line is an empty string */
		if (!empty($selected_cat))
			do_sql_query("update $dbname set category = '$selected_cat' where refnumber = $ref");
	}
	
	/* and finish by ... */
	touch_db($dbname);
	touch_cached_query($qname);
}



/**
 * Separate a categorised query inot one saved query per category.
 * @param string  $qname   The query name.
 */
function catquery_separate($qname)
{
	global $Config;
	

	/* check that the query in question exists and is a catquery */
	$query_record = QueryRecord::new_from_qname($qname);
	if ($query_record === false || $query_record->saved != CACHE_STATUS_CATEGORISED)
		exiterror("The specified categorised query \"$qname\" was not found!");
	
	
	$dbname = catquery_find_dbname($qname);
	
// TODO : the following is deeply shonky. Something should be done about it.
	/* we DO NOT use a unique ID from instance_name, because we want to be able to 
	 * delete this query later if the mother-query is re-separated. See below. */
	$newqname_root = $qname . '_';
	$newsavename_root = $query_record->save_name . '_';

	/* has to be "realpath" because it's going to be used as an outfile! */
	$outfile_path = realpath($Config->dir->cache) . "/temp_cat_$newqname_root.tbl";
	if (is_file($outfile_path))
		unlink($outfile_path);

	
	/* MAIN LOOP for this function :  applies to every category in the catquery we are dealing with */
	
	foreach(catquery_list_categories($qname) as $category)
	{
		$newqname = $newqname_root . $category;
		/* if the query exists... (note, we wouldn't normally overwrite, but for separation we do */
		delete_cached_query($newqname);
		/* we also want to eliminate any existing DBs based on this query, 
		 * so any data based on a previous separation is removed */
		delete_dbs_of_query($newqname);
		
		refresh_directory_global_cqp();
		
		$newsavename = $newsavename_root . $category;
		
		/* create the dumpfile & obtain solution count */
		$solution_count = do_sql_outfile_query("SELECT beginPosition, endPosition FROM $dbname WHERE category = '$category'", $outfile_path);
		
		if ($solution_count < 1)
		{
			unlink($outfile_path);
			continue;
		}
		
		$cqp = get_global_cqp();
		
		$cqp->execute("undump $newqname < '$outfile_path'");
		$cqp->execute("save $newqname");

		unlink($outfile_path);
		
		/* create, update and then save a new query record. */
		$new_record = clone $query_record;
		$new_record->postprocess_append("cat[$category]", $solution_count);
		$new_record->qname = $newqname;
		$new_record->file_size = cqp_file_sizeof($newqname);
		$new_record->saved = CACHE_STATUS_SAVED_BY_USER;
		$new_record->save_name = $newsavename;
		$new_record->set_time_to_now();
		$new_record->save();

	}
}






/*
 * ===============================
 * HISTORY TABLE RELATED FUNCTIONS
 * =============================== 
 */



function history_insert_old($instance_name, $cqp_query, $query_scope, $simple_query, $qmode)
{
	global $User;
	global $Corpus;

	$escaped_cqp_query    = escape_sql($cqp_query);
 	$escaped_query_scope  = escape_sql($query_scope);
	$escaped_simple_query = escape_sql($simple_query);
	$escaped_qmode        = escape_sql($qmode);
	
	$sql = "insert into query_history 
				(instance_name, user, corpus, cqp_query,  
					query_scope, hits, simple_query, query_mode) 
					values 
				('$instance_name', '{$User->username}', '{$Corpus->name}', '$escaped_cqp_query',
					'$escaped_query_scope', -3, '$escaped_simple_query', '$escaped_qmode')";
	do_sql_query($sql);
}




/**
 * Adds a trace of a query performed by the user to the query history.
 * 
 * Note that the "hits" field is set by default to -3; scripts should update
 * this later if the query is successful.
 * 
 * We **don't** use a QueryRecord for the parameter, because it might be a query that
 * will not find any hits...
 */
function history_insert($id, $cqp_query, $query_scope, $simple_query, $qmode)
{
	if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
	{
		history_insert_old($id, $cqp_query, $query_scope, $simple_query, $qmode);
		return;
	}
	global $User;
	global $Corpus;

	$escaped_cqp_query    = escape_sql($cqp_query);
 	$escaped_query_scope  = escape_sql($query_scope);
	$escaped_simple_query = escape_sql($simple_query);
// 	$escaped_qmode        = escape_sql($qmode);
	
	$sql = "insert into query_history 
				(user, corpus, cqp_query,  
					query_scope, hits, simple_query, query_mode) 
					values 
				('{$User->username}', '{$Corpus->name}', '$escaped_cqp_query',
					'$escaped_query_scope', -3, '$escaped_simple_query', '$qmode')";
	do_sql_query($sql);
	return get_sql_insert_id();
}
/**
 * Deletes the history entry with the specified integer ID.
 */
function history_delete($id)
{
	if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
	{
		$id = escape_sql($id);
		do_sql_query("delete from query_history where instance_name = '$id'");
	}
	else 
		do_sql_query("delete from query_history where id = " . (int)$id);
}

/**
 * Sets the number of hits associated with a given instance name in the query history.
 */
function history_update_hits($id, $hits)
{
	if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
	{
	
		$id = escape_sql($id);
		$hits = (int)$hits;
		do_sql_query("update query_history SET hits = $hits where instance_name = $id");
	}
	else 
	{
	
		$id = (int)$id;
		$hits = (int)$hits;
		do_sql_query("update query_history SET hits = $hits where id = $id");
	}
}


/**
 * Empties the query history - that is, total reset.
 * 
 * Restricted to admin users. 
 */
function history_total_clear()
{
	global $User;

	if ( ! $User->is_admin())
		return;
	
	do_sql_query("delete from query_history");
}



