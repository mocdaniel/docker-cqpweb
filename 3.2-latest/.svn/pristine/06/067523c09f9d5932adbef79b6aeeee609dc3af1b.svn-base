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



// todo, newname postprocess-lib.php





/*

	Format of the postprocess string:
	---------------------------------
	
	Postprocesses are separated by ~~
	
	Postprocesses are named as follows:
	
	coll	... collocating with
	sort	... a sort implemented using the "sort" program (can also thin)
	thin	... thinned using the "thin" function
	dist	... a reduction from the distribution page to a class of texts
	text	... a reduction from the distribution page to a specified text
	rand	... randomise order
	unrand  ... usually (but not always) a pseudo-value, it really means take out rand
	cat		... a particular "categorisation" has been applied
	item	... a particular item was selected on the "frequency distribution" page aka "item thinning"
	custom  ... a custom postprocess was run, doing "something".
	upload  ... not a postprocess, but it is added to the postprocess string in order 
	            to form part of the match-by-parameter system
	
	
	Some of these have parameters. These are in the format:
	~~XXXX[A~B~C]~~
	
	coll[dbname~att~target~from~to~tagfilter]
		dbname=the database used for the collocation
		att=the mysql handle of the att the match is to be done on
		target=the match pattern
		from=the minimum dist the target can occur at
		to=the maximum dist the target can occur at
		tagfilter=the regex filter applied for primary-att of collocate, if any
	
	sort[position~thin_tag~thin_tag_inv~thin_str~thin_str_inv]
		position=the position of the sort, in format 2, -1, 3, etc. + = right. - = left. Maximum: +/- 5.
		thin_tag=the tag that the sorted position must have (full string, not regex); or '.*' if any tag is allowed
		thin_tag_inv=1 if the specified tag is to be *excluded*, otherwise 0
		thin_str=the string that the sorted position must begin with (full string, not regex); or '.*' if any wordform is allowed
		thin_str_inv=1 if the specified starts-with-string is to be *excluded*, otherwise 0
		
		#note: the .* is NOT fed to mySQL, it is just a code that CQPweb will pick up on.
	
	thin[count~method]
		count=the number of queries REMAINING after it has been thinned
		method=r|n : r for "random reproducible", n for "random nonreproducible"
		When the method is "n", it is always followed by an instance identifier. This ensures that
		this method can never be matched in the cache.
	
	dist[categorisation~class]
		categorisation=handle of the field that the distribution is being done over
		class=the handle that occurs in that field for the texts we want
		
	text[target_text_id]
		target_text_id -- what it says on the tin!
	
	rand
		no parameters
	
	unrand
		no parameters
		
	cat[category]
		category=the label of the category to which the solutions in this query were manually assigned
		Note: this postprocess is always done by the 'categorise' tools (under savequery-act), never 
		here (so, no functions or cases for it); all this library needs to do is render it properly
	
	item[position~form~tag]
		position=the position of the sort, in format 2, -1, 3, etc. + = right. - = left. Maximum: +/- 5.
		(node-based item breakdown comes via the "normal" version of Frequency Breakdown; in that case position must be 0.)
		EITHER of the specifiers can be an empty string.
		
	custom[class]
		The name of the class that did the postprocessing is stored here. The class will be queried for
		a description!
		
	upload[instance_name]
		The presence of an arbitrary argument (usually the instance_name, but can be anything) prevents 
		an uploaded query ever being matched-by-parameter.
	
	Postprocesses are listed in the order they were applied.
	
	When a query is "unrandomised", rand is removed from anywhere in its string,
	but "unrand" is only added if there has been a prior "sort".
	When a query is sorted using sort, rand and unrand are removed from anywhere in its string.

*/


/** class for descriptor object for a new postprocess operation */
class CQPwebPostprocess 
{
	
	/** this variable contains one of the lowercase abbreviations of the different postprocess types */
	public $postprocess_type;
	
	/** boolean: true if the $_GET was parsed OK, false if not */
	private $i_parsed_ok;
	
	/* this stops the function "add to postprocess string" running more than once */
	public $stored_postprocess_string;
	
	public $run_function_name;

	/* variables for collocation */
	public $colloc_db;
	public $colloc_dist_from;
	public $colloc_dist_to;
	public $colloc_att;
	public $colloc_target;
	public $colloc_tag_filter;
	
	/* variables for thin */
	public $thin_target_hit_count;
	public $thin_genuinely_random;
	
	/* variables for sort */
	public $sort_position;
	public $sort_thin_tag;
	public $sort_thin_tag_inv;
	public $sort_thin_str;
	public $sort_thin_str_inv;
	public $sort_remove_prev_sort;
	public $sort_pp_string_of_query_to_be_sorted;
	public $sort_db;
	public $sort_thinning_sql_where;
	
	/* variables for item-thinning */
	public $item_position;
	public $item_form;
	public $item_tag;
	
	/* variables for distribution-thinning */
	public $dist_db;
	public $dist_categorisation_handle;
	public $dist_class_handle;
	
	/* variables for text-distribution-thinning */
	public $text_target_id;
	
	/* variables for custom postprocesses */
	public $custom_class;
	public $custom_obj;
	
	
	/** Constructor - this reads things in from GET (and gets rid of them) */
	function __construct()
	{
		/* unless disproven below */
		$this->i_parsed_ok = true;
		
		/* all input sanitising is done HERE */
		switch($_GET['newPostP'])
		{
		case 'coll':
			
			global $Corpus;
			
			$this->postprocess_type = 'coll';

			if ( ! isset(
				$_GET['newPostP_collocDB'],
				$_GET['newPostP_collocDistFrom'],
				$_GET['newPostP_collocDistTo'],
				$_GET['newPostP_collocAtt'],
				$_GET['newPostP_collocTarget'],
				$_GET['newPostP_collocTagFilter']
				) )
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->colloc_db = preg_replace('/\W/', '', $_GET['newPostP_collocDB']);
			$this->colloc_dist_from = (int)$_GET['newPostP_collocDistFrom'];
			$this->colloc_dist_to = (int)$_GET['newPostP_collocDistTo'];
			$this->colloc_att = preg_replace('/\W/', '', $_GET['newPostP_collocAtt']);
			if (! check_is_real_corpus_annotation($Corpus->name, $this->colloc_att))
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->colloc_target = escape_sql($_GET['newPostP_collocTarget']);
			$this->colloc_tag_filter = escape_sql($_GET['newPostP_collocTagFilter']);
			/* it should be safe to real-escape this, even though it may be a regex, because there
			 * is no metacharacter meaning for ' or ". */ 
			
			break;
		
		
		case 'sort':
			$this->postprocess_type = 'sort';
			
			if ( ! isset( $_GET['newPostP_sortPosition']	) )
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->sort_position = (int)$_GET['newPostP_sortPosition'];

			$this->sort_thin_tag = escape_sql($_GET['newPostP_sortThinTag']);
			if (empty($this->sort_thin_tag))
				$this->sort_thin_tag = '.*';
			$this->sort_thin_tag_inv = (isset($_GET['newPostP_sortThinTagInvert']) ? (bool)$_GET['newPostP_sortThinTagInvert'] : false);
				

			$this->sort_thin_str = escape_sql($_GET['newPostP_sortThinString']);
			if (empty($this->sort_thin_str))
				$this->sort_thin_str = '.*';
			$this->sort_thin_str_inv = (isset($_GET['newPostP_sortThinStringInvert']) ? (bool)$_GET['newPostP_sortThinStringInvert'] : false);
			
			/* note that either the tag or the string used to restrict could include punctuation
			   or UTF8 characters: so only mysql sanitation is done for these two things. */
			$this->sort_remove_prev_sort = (empty($_GET['newPostP_sortRemovePrevSort']) ? false : true);		
			
			break;


		case 'thin':
			$this->postprocess_type = 'thin';
			
			/* empty() checks for a range of nonsensical things: '', '0', etc. */
			if ( ! isset( $_GET['newPostP_thinReallyRandom'], $_GET['newPostP_thinTo'], $_GET['newPostP_thinHitsBefore'] )
				|| empty($_GET['newPostP_thinTo'])  )
			{
				$this->i_parsed_ok = false;
				return;
			}
			
			$_GET['newPostP_thinTo'] = trim($_GET['newPostP_thinTo']);
			if (substr($_GET['newPostP_thinTo'], -1) == '%')
			{
				$thin_factor = str_replace('%', '', $_GET['newPostP_thinTo']) / 100;
				
				/* check for insane percentage values */
				if ($thin_factor >= 1)
				{
					$this->i_parsed_ok = false;
					return;
				}
				
				$this->thin_target_hit_count = (int)round(((float)$_GET['newPostP_thinHitsBefore']) * $thin_factor, 0);
			}
			else
			{
				$this->thin_target_hit_count = (int)$_GET['newPostP_thinTo'];
				
				/* a check for thinning to "more hits than we originally had" */			
				if ($this->thin_target_hit_count >= (int)$_GET['newPostP_thinHitsBefore'])
				{
					$this->i_parsed_ok = false;
					return;
				}				
			}
		// TODO. The above assumes honesty in newPostP_thinHitsBefore. Such honesty should not be assumed.  
		// TODO. Implement a check against a query record -- when this is put into effect? (within the QR object...) 
			
			$this->thin_genuinely_random = ($_GET['newPostP_thinReallyRandom'] == 1) ? true : false;
			
			break;

			
		case 'item':
			$this->postprocess_type = 'item';
			
			/* We have to have this variable */
			if ( ! isset( $_GET['newPostP_itemPosition']	) )
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->item_position = (int) $_GET['newPostP_itemPosition'];
			
			/* we only need one out of form and tag */
			if ( empty($_GET['newPostP_itemForm']) && empty($_GET['newPostP_itemTag']))
			{
				$this->i_parsed_ok = false;
				return;
			}
			
			$this->item_form = (isset($_GET['newPostP_itemForm']) ? escape_sql($_GET['newPostP_itemForm']) : '');
			$this->item_tag  = (isset($_GET['newPostP_itemTag'])  ? escape_sql($_GET['newPostP_itemTag'])  : '');
	
			break;

			
			
		case 'dist':
			$this->postprocess_type = 'dist';

// TODO this line previously used mepty() which is true if the value s a string "0". 
// check the rest to make usre they use isset() not empty.
			if (!isset($_GET['newPostP_distCateg']) || '' === $_GET['newPostP_distCateg'] || !isset($_GET['newPostP_distClass']) || '' === $_GET['newPostP_distClass'])
			{
				$this->i_parsed_ok = false;
				return;
			}
			
			$this->dist_categorisation_handle = escape_sql($_GET['newPostP_distCateg']);
			$this->dist_class_handle = escape_sql($_GET['newPostP_distClass']);
			
			break;
			
		case 'text':
			$this->postprocess_type = 'text';
			if ( empty($_GET['newPostP_textTargetId']) )
			{
				$this->i_parsed_ok = false;
				return;
			}
			$this->text_target_id = escape_sql($_GET['newPostP_textTargetId']);
			break;	
		
		case 'rand':
			$this->postprocess_type = 'rand';
			break;
		case 'unrand':
			$this->postprocess_type = 'unrand';
			break;
		
		default:
			/* it might be a custom postprocess */
			if (substr($_GET['newPostP'], 0, 11) == 'CustomPost:')
			{
				$this->custom_class = preg_replace('/\W/', '', substr($_GET['newPostP'], 11));
				$this->postprocess_type = 'custom';
				$record = retrieve_plugin_info($this->custom_class);
				$this->custom_obj = new $this->custom_class($record->path);
				break;
			}

			/* no, it's not a custom process - it's just a bad value. */
			$this->i_parsed_ok = false;
			
			break;
		}
		
		/* clear these things out of GET so they are not passed on */
		foreach (array_keys($_GET) as $key)
			if (substr($key, 0, 8) == 'newPostP')
				unset($_GET[$key]);
		// TODO - is this still needful?
	}
	
	
	
	/* 
	 * =================
	 * general functions
	 * =================
	 */
	
	
	
	/**
	 * Takes the string argument and modifies it to ADD to its postprocess stack the postprocess
	 * stored in this object. The result is stored inside the object as well as returned.
	 * 
	 * (If this function has already been called and there is a stored_postprocess_string,
	 * that will be returned instead of the function running. So this is 
	 * a call-only-once function.)
	 * 
	 * @param string $string_to_work_on   The string which will be modified and returned (and stored inside this object).
	 *                                    Should be an existing postprocess string from a QueryRecord. 
	 *                                    TODO: pass a QueryRecord instead???
	 * @return string|bool                Modified postprocess string.
	 */
	function add_to_postprocess_string($string_to_work_on)
	{
		global $Config;
		
		if (!empty ($this->stored_postprocess_string))
			return $this->stored_postprocess_string;
			
		/* implicit else: rest of function only runs if the stored_postprocess_string has not been set */
	
//		$rand_taken_off = false;
//		if (substr($string_to_work_on, -6) == '~~rand')
//		{
//			$rand_taken_off = true;
//			$string_to_work_on = substr($string_to_work_on, 0, strlen($string_to_work_on)-6);
//		}
		// is this needed? there certainly doens't seem to be a "put back on" in the code
	
		switch($this->postprocess_type)
		{
		case 'coll':
			$string_to_work_on .= "~~coll[$this->colloc_db~$this->colloc_att~$this->colloc_target~"
				. "$this->colloc_dist_from~$this->colloc_dist_to~$this->colloc_tag_filter]";
			break;
		
		case 'thin':
			$r_or_n = ($this->thin_genuinely_random ? ('n' . $Config->instance_name) : 'r');
			$string_to_work_on .= "~~thin[$this->thin_target_hit_count~$r_or_n]";
			break;
			
		case 'rand':
			$string_to_work_on = preg_replace('/(~~)?unrand\z/', '', $string_to_work_on);
			$string_to_work_on .= '~~rand';
			break;
			
		case 'unrand':
			/* remove "rand" */
			if ($string_to_work_on == 'rand' || $string_to_work_on == 'unrand')
				$string_to_work_on = '';
			else
			{
				$string_to_work_on = str_replace('~~unrand', '', $string_to_work_on);
				$string_to_work_on = str_replace('unrand~~', '', $string_to_work_on);
				$string_to_work_on = str_replace('~~rand', '', $string_to_work_on);
				$string_to_work_on = str_replace('rand~~', '', $string_to_work_on);
			}
			/* but --DON'T-- add ~~unrand unless there is a "sort" somewhere in the string */
			if (strpos($string_to_work_on, 'sort[') !== false)
				$string_to_work_on .= '~~unrand';
			break;
		
		/* case sort : remove the immediately previous postprocess if it is '(un)rand' or a sort */
		case 'sort':
			if ($string_to_work_on == 'rand' || substr($string_to_work_on, -6) == '~~rand')
			{
				$string_to_work_on = substr($string_to_work_on, 0, -6);
			}
			else if ($string_to_work_on == 'unrand' ||substr($string_to_work_on, -8) == '~~unrand')
			{
				$string_to_work_on = substr($string_to_work_on, 0, -8);
			}
			else if ($this->sort_remove_prev_sort)
			{
				$string_to_work_on = preg_replace('/(~~)?sort\[[^\]]+\]\Z/', '', $string_to_work_on);
			}
			
			/* at this point, "string to work on" is the pp string of query that needs to be sorted */
			$this->sort_pp_string_of_query_to_be_sorted = ($string_to_work_on === NULL ? '' : $string_to_work_on);

				
			$mybool1 = (int)$this->sort_thin_tag_inv;
			$mybool2 = (int)$this->sort_thin_str_inv;
			$string_to_work_on .= "~~sort[$this->sort_position~$this->sort_thin_tag~$mybool1~$this->sort_thin_str~$mybool2]";
			break;

		case 'item':
			/* because "item" uses many of the same variables as "sort", we also need to set up the following : */
			$this->sort_pp_string_of_query_to_be_sorted = ($string_to_work_on === NULL ? '' : $string_to_work_on);
			/* for item this is always a straight copy, we don't need to fart about with the removal of a previous sort. */

			/* and now, just pop on the end of the string the item effect. */
			$string_to_work_on .= "~~item[$this->item_position~$this->item_form~$this->item_tag]";
			break;
		
		case 'dist':
			$string_to_work_on .= "~~dist[$this->dist_categorisation_handle~$this->dist_class_handle]";
			break;
		
		case 'text':
			$string_to_work_on .= "~~text[$this->text_target_id]";
			break;
		
		case 'custom':
			$string_to_work_on .= "~~custom[$this->custom_class]";
			break;
		
		default:
			return false;
		}
				
		if (substr($string_to_work_on, 0, 2) == '~~')
			$string_to_work_on = substr($string_to_work_on, 2);

		$this->stored_postprocess_string = $string_to_work_on;
		return $string_to_work_on;
	}
	
	
	function get_stored_postprocess_string()
	{
		return $this->stored_postprocess_string;
	}
	
	
	function parsed_ok()
	{
		return $this->i_parsed_ok;
	}

	
	
	
	/* functions for collocations */
	
	function colloc_sql_for_queryfile()
	{
		if (!$this->colloc_sql_capable())
			return false;

		return "SELECT beginPosition, endPosition
			FROM {$this->colloc_db}
			WHERE `{$this->colloc_att}` = '{$this->colloc_target}'
			"  . $this->colloc_tag_filter_clause() . "
			AND dist BETWEEN {$this->colloc_dist_from} AND {$this->colloc_dist_to}";
			//TODO: does this need an "order by" like dist does, to make sure results are in corpus order?
	}
	
	private function colloc_tag_filter_clause()
	{
		global $Corpus;
		
		if (empty($this->colloc_tag_filter))
			return '';
		
		/* if there are nonword characters, assume this is a regex filter; otherwise, assume plain filtering */
		$op = (preg_match('/\W/', $this->colloc_tag_filter) ? 'REGEXP' : '=');
		$filter = ('REGEXP'==$op ? regex_add_anchors($this->colloc_tag_filter) : $this->colloc_tag_filter);
		/* note -- we do not need to call escape_sql() because it was called when the object was set up. */
		
		return "AND {$Corpus->primary_annotation} $op '{$filter}'";
	}



	function colloc_sql_capable()
	{
		return ( isset(	$this->colloc_db, 
						$this->colloc_dist_from, 
						$this->colloc_dist_to, 
						$this->colloc_att, 
						$this->colloc_target
					) );
	}
	
	
	
	
	/* functions for sorting */
	
	/* the "orig" query record is needed because the DB is created for the orig query */
	private function sort_set_dbname($orig_query_record)
	{
		/* the argument needs to be a QueryRecord object!! */
//show_var($this->postprocess); show_var($this->sort_pp_string_of_query_to_be_sorted); show_var($orig_query_record->postprocess);	show_var($d = "======================================================================================");

		/* search the db list for a db whose parameters match those of the query we are working with  */
		/* if it doesn't exist, we need to create one */
		$db_record = check_dblist_parameters(new DbType(DB_TYPE_SORT), $orig_query_record->cqp_query,
						$orig_query_record->query_scope,
						$this->sort_pp_string_of_query_to_be_sorted);
		/* note, instead of the postprocess string from the query record (which has not been edited by the
		 * add_to_postprocess_string function) we use the postprocess string of the query we need to work 
		 * from -- which was recorded in this object when we ran add_to_postprocess_string !
		 */

		if ($db_record === false)
		{
			$this->sort_db = create_db(new DbType(DB_TYPE_SORT), $orig_query_record->qname, 
						$orig_query_record->cqp_query, 
						$orig_query_record->query_scope, 
						$this->sort_pp_string_of_query_to_be_sorted);
		}
		else
		{
			touch_db($db_record['dbname']);
			$this->sort_db = $db_record['dbname'];
		}
		
	}

	/* the "orig" query record is needed to be passed to sort_set_dbname */
	function sort_sql_for_queryfile($orig_query_record)
	{			
		$this->sort_set_dbname($orig_query_record);
		if (!$this->sort_sql_capable())
			return false;

		/* use the sort settings to create the where and order by clause */
		
		$extra_sort_pos_sql = '';
		
		/* the variable "sort_position_sql" is beforeX, afterX, or node */
		if ($this->sort_position < 0)
		{
			$sort_position_sql = 'before' . (-1 * $this->sort_position);
			for ($i = (-1 * $this->sort_position) ; $i < 6 ; $i++)
				$extra_sort_pos_sql .= ", before$i COLLATE utf8_general_ci ";
		}
		else if ($this->sort_position == 0)
		{
			$sort_position_sql = 'node';
			/* see note below for why the collation is what it is */
			$extra_sort_pos_sql = ", after1 COLLATE utf8_general_ci"
				. ", after2 COLLATE utf8_general_ci"
				. ", after3 COLLATE utf8_general_ci"
				. ", after4 COLLATE utf8_general_ci"
				. ", after5 COLLATE utf8_general_ci";
		}
		else if ($this->sort_position > 0)
		{
			$sort_position_sql = 'after' . $this->sort_position;
			for ($i = $this->sort_position ; $i < 6 ; $i++)
				$extra_sort_pos_sql .= ", after$i COLLATE utf8_general_ci";
		}
			

		
		/* first, what are we actually sorting on? */

		$this->sort_thinning_sql_where = '';
		
		/* do we have a tag restriction? */
		if ($this->sort_thin_tag != '.*')
		{
			$this->sort_thinning_sql_where = "where tag$sort_position_sql " 
												. ($this->sort_thin_tag_inv ? '!' : '')
												. "= '$this->sort_thin_tag'";
		}
		
		/* do we have a string restriction? */
		if ($this->sort_thin_str != '.*')
		{
			$where_clause_temp = "$sort_position_sql " 
								. ($this->sort_thin_str_inv ? 'NOT ' : '')
								. "LIKE '$this->sort_thin_str%'";
			if (!empty($this->sort_thinning_sql_where))
				$this->sort_thinning_sql_where .= ' and ' . $where_clause_temp;
			else
				$this->sort_thinning_sql_where = 'where ' . $where_clause_temp;
		}		
			
		return "SELECT beginPosition, endPosition
			FROM {$this->sort_db} 
			{$this->sort_thinning_sql_where}
			ORDER BY $sort_position_sql COLLATE utf8_general_ci  $extra_sort_pos_sql ";
		/* note:
		 * we always use utf8_general_ci for the actual sorting,
		 * even if the collation of the sort DB is actually utf8_bin 
		 * (for purposes of frequency breakdown, restriction matching etc);
		 * see also the creation of $extra_sort_pos_sql above
		 */
	}
	


	function sort_sql_capable()
	{
		return ( isset($this->sort_position, $this->sort_thin_tag, $this->sort_thin_tag_inv, 
			$this->sort_thin_str, $this->sort_thin_str_inv, $this->sort_remove_prev_sort, 
			$this->sort_db, $this->sort_pp_string_of_query_to_be_sorted) );
	}
	
	
	function sort_get_active_settings()
	{
		/* don't know if this is actually needed at all, but does not hurt to have */
		return array(
				'sort_position' 		=> $this->sort_position,
				'sort_thin_tag' 		=> $this->sort_thin_tag,
				'sort_thin_tag_inv' 	=> $this->sort_thin_tag_inv,
				'sort_thin_str' 		=> $this->sort_thin_str,
				'sort_thin_str_inv' 	=> $this->sort_thin_str_inv
				);
	}
	
	
	
	/* "item" functions ; note the "item" postprocess re-uses some of the sort methods too */ 
	
	function item_sql_for_queryfile($orig_query_record)
	{
		$this->sort_set_dbname($orig_query_record);
		
		$this->sort_thinning_sql_where = '';
		
		$pos = sqlise_integer_position($this->item_position);
		
		if (! empty($this->item_form))
			$this->sort_thinning_sql_where .= "where $pos = '$this->item_form' " ;
		
		if (! empty($this->item_tag))
		{
			if ( $this->sort_thinning_sql_where == '')
				$this->sort_thinning_sql_where .= 'where';
			else
				$this->sort_thinning_sql_where .= 'and';
			$this->sort_thinning_sql_where .= " tag$pos = '$this->item_tag' ";
		}

		return "SELECT beginPosition, endPosition
			FROM {$this->sort_db}
			$this->sort_thinning_sql_where
			ORDER BY beginPosition  ";
			//TODO: would refnumber be better than beginPosition? investigate
	}
	
	/**
	 * 
	 * @param QueryRecord $orig_query_record
	 */
	function dist_set_dbname($orig_query_record)
	{
		/* search the db list for a db whose parameters match those of the query we are working with  */
		/* if it doesn't exist, we need to create one */
		$db_record = check_dblist_parameters(new DbType(DB_TYPE_DIST), $orig_query_record->cqp_query,
						$orig_query_record->query_scope,
						$orig_query_record->postprocess);

		if ($db_record === false)
		{
			$this->dist_db = create_db(new DbType(DB_TYPE_DIST), $orig_query_record->qname, 
						$orig_query_record->cqp_query, 
						$orig_query_record->query_scope,
						$orig_query_record->postprocess);
		}
		else
		{
			touch_db($db_record['dbname']);
			$this->dist_db = $db_record['dbname'];
		}
	}
	
	function dist_sql_for_queryfile($orig_query_record)
	{
		global $Corpus;
		
		$this->dist_set_dbname($orig_query_record);
		
		return "SELECT beginPosition, endPosition
			FROM {$this->dist_db} 
			INNER JOIN text_metadata_for_{$Corpus->name} 
			ON {$this->dist_db}.text_id = text_metadata_for_{$Corpus->name}.text_id 
			WHERE text_metadata_for_{$Corpus->name}.`{$this->dist_categorisation_handle}`
				= '{$this->dist_class_handle}' 
			ORDER BY refnumber";
	}
	
	function text_sql_for_queryfile($orig_query_record)
	{
		/* IMPORTANT NB!! uses the same dbname-finding function as "dist" */
		$this->dist_set_dbname($orig_query_record);	
		
		return "SELECT beginPosition, endPosition FROM {$this->dist_db}
			WHERE text_id = '{$this->text_target_id}'";
	}
	

} 
/* end of class CQPwebPostprocess */




/*
 * 
 * Postprocess helper functions.
 * =============================
 * 
 * For use by custom postprocessors, to allow them to find out something 
 * about the data they are acting on.
 * 
 * 
 */ 

// TODO this function not tested yet.
/**
 * Gets the value of a given positional-attribute (word annotation)
 * at a given token position in the active corpus.
 * 
 * Returns a single string, or false in case of error.
 */
function pphelper_cpos_get_attribute($cpos, $attribute) 
{
	global $Config;
	global $Corpus;
	
	/* typecast in case anyone is foolish enough to pass a float... */
	$num_of_token = (int)$cpos;
	if ($num_of_token < 0)
		exiterror("pphelper_cpos_within_structure: invalid corpus index [$cpos]");

	/* work out whether cpos is within an instance of the structure */
	$cmd = "{$Config->path_to_cwb}cwb-decode -C -s $num_of_token -e $num_of_token -r \"{$Config->dir->registry}\"  {$Corpus->cqp_name} -P $attribute";
	$proc = popen($cmd, 'r');
	$value = fgets($proc);
	pclose($proc);
	
	if (empty($value))
		return false;
	else
		return trim($value);
}


//TODO this function not tested yet.
//TODO better: access to tabulate?
/**
 * Gets a full concordance from a set of matches.
 * 
 * The concordance is returned as an array of arrays. The outer array contains
 * as many members as the $matches argument, in corresponding order. Each inner array 
 * represents one hit, and corresponds to a single group of two-to-four integers.
 * Moreover, each inner array contains three members (all strings): the context
 * before, the context after, and the hit itself. 
 *
 * The $matches array is an array of arrays of integers or integers as strings, 
 * in the same format used to convey a query to a custom postprocess.
 *
 * You can specify what p-attributes and s-attributes you wish to be displayed in the
 * concordance. The default is to show words only, and no XML. Use an array of strings
 * to specify the attributes you want shown in each case.
 * 
 * You can also specify how much context is to be shown, and the unit it should be 
 * measured in. The default is ten words.
 * 
 * Individual tokens in the concordance are rendered using slashes to delimit the
 * different annotations.
 */
function pphelper_get_concordance($matches,
                                  $p_atts_to_show = 'word',
                                  $s_atts_to_show = '',
                                  $context_n = 10,
                                  $context_units = 'words')
{
	global $Config;
	global $Corpus;
	
	/* don't allow an empty array */
	if (empty($p_atts_to_show))
	
	/* for the default, but also in case someone passes a single argument. */
	if (!is_array($p_atts_to_show))
		$p_atts_to_show = array ($p_atts_to_show);

	if ( $context_units != 'words' && !xml_exists($context_units, $Corpus->name) )
		$context_units = 'words';

	/* get a new identifier by suffixing the instance name */
	$temp_qname = $Config->instance_name . 'pph';

	/* undump matches to that uniqid */
	$cqp = get_global_cqp();
	$cqp->undump($temp_qname, $matches, $Config->dir->cache);
	unset($matches);

	/* Set up CQP concordance output stuff:
	 * The main script will not have set up its options at this point!
	 * so we can do what we like, and it will be re-done
	 */
	$cqp = get_global_cqp();
	$cqp->execute("set Context $context_n $context_units");
	$cqp->execute("show +" . implode (' +', $p_atts_to_show));
	if (!empty($s_atts_to_show))
		$cqp->execute("set PrintStructures \"" . implode(' ', $s_atts_to_show) . "\""); 
	$cqp->execute("set LeftKWICDelim '--%%%--'");
	$cqp->execute("set RightKWICDelim '--%%%--'");

	/* cat concordance */
	$kwic = $cqp->execute("cat $temp_qname");

	/* extract lines to arrays. */
	$result = array();
	foreach ($kwic as &$line)
	{
		$result[] = explode ('--%%%--', $line);
		unset($line);
		/* so that as one array grows, the other shrinks. */
	}
	
	/* delete the query in CQP 
	 * (it shouldn't have been saved to file, so just get it out of memory....) */
	$cqp->execute("discard $temp_qname");

	return $result;
}


// TODO this function not tested yet.
/**
 * Determines whether or not the specified corpus position (integer index) occurs
 * within an instance of the specified structural attribute (XML element).
 * 
 * Returns a boolean (true or false, or NULL in case of error).
 */
function pphelper_cpos_within_structure($cpos, $struc_attribute)
{
	global $Config;
	global $Corpus;
	
	/* typecast in case anyone is foolish enough to pass a float... */
	$num_of_token = (int)$cpos;
	if ($num_of_token < 0)
		exiterror("pphelper_cpos_within_structure: invalid corpus index [$cpos]");
	
	/* Is $struc_attribute a valid s-att for this corpus? */
	if (! xml_exists($struc_attribute, $Corpus->name) )
		exiterror("pphelper_cpos_within_structure: invalid s-attribute index [$struc_attribute]");

	/* work out whether cpos is within an instance of the structure */
	$cmd = "{$Config->path_to_cwb}cwb-s-decode -r \"{$Config->dir->registry}\" {$Corpus->cqp_name} -S $struc_attribute";
	$proc = popen($cmd, 'r');
	$within = false;
	while ( false !== ($line = fgets($proc)) )
	{
		list($begin, $end) = explode ("\t", trim($line));
		if ($begin <= $cpos && $cpos <= $end)
		{
			$within = true;
			break;
		}
	}
	pclose($proc);

	return $within;
}



