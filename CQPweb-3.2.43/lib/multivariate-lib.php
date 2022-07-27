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
 * Library of functions supporting multivariate analysis operations 
 * and user-created feature matrices.
 */


class MultivarFeatureDef
{
	const TYPE_UNKNOWN   = 0;
	const TYPE_SAVEDQ    = 1;
	const TYPE_MATHMANIP = 2;
	const TYPE_STTR      = 3;
	const TYPE_AVGWDLEN  = 4;

	const OP_NONE        = 0;
	const OP_ADD         = 1;
	const OP_SUBTRACT    = 2;
	const OP_MULTIPLY    = 3;
	const OP_DIVIDE      = 4;

	public $type = self::TYPE_UNKNOWN; 

	public $source_info = '';

	public $label = '';
	
	private $use_cleanup = false;

	private $operator = self::OP_NONE;

	private $query_operands = [];
	private $discount_ratio = 1.0;
	
	private $operand_ix = 0;
	
	private $sttr_basis = 1000;
	
// this is a pretty limited style of discount - times one query by one number between 0 and 1. This could be done better
// as an OP addable to ANY feature.

	public function __construct($type)
	{
		if (!$this->is_real_type($type))
			exiterror("Cannot create MultivarFeatureDef of type $type, type ID not recognised.");
		$this->type = $type;
	}

	public function add_qname($qname)
	{
		$this->query_operands[] = $qname;
	}
	
	public function get_qname()
	{
		if ($this->operand_ix >= count($this->query_operands))
			return NULL;
		else
			return $this->query_operands[$this->operand_ix++];
	}
	
	public function n_operands()
	{
		return count($this->query_operands);
	}
	
	public function get_discount_ratio() 
	{
		return $this->discount_ratio;
	}
	
	public function set_discount_ratio($new_disc_ratio)
	{
		$new_disc_ratio = (float)$new_disc_ratio;
		
		if (0.0 <= $new_disc_ratio && $new_disc_ratio <= 1.0)
			$this->discount_ratio = $new_disc_ratio;
		/* else, leave unchanged. */
	}
	
	public function get_op()
	{
		return $this->operator;
	}
	
	public function set_op($new_op)
	{
		switch($new_op)
		{
		case self::OP_NONE:
		case self::OP_ADD:
		case self::OP_SUBTRACT:
		case self::OP_MULTIPLY:
		case self::OP_DIVIDE:
			$this->operator = $new_op;
			break;
		}
		/* if not a proper const, don't set anything! */
	}
	
	/** Get the printable string for this object's mathematical operation. */
	public function print_op_as_word()
	{
		return self::get_op_string($this->operator);
	}
	
	public function apply_op($operand_vals)
	{
		if (self::TYPE_MATHMANIP != $this->type)
			return NULL;
		
		list($op1_val, $op2_val) = $operand_vals;
		
		switch ($this->operator)
		{
		case self::OP_ADD:
			return $op1_val + $op2_val;
			
		case self::OP_SUBTRACT:
			return $op1_val - $op2_val;
		
		case self::OP_MULTIPLY:
			return $op1_val * $op2_val;
			
		case self::OP_DIVIDE:
			return $op1_val / $op2_val;
		
		default:
			return NULL;
		}
	}
	
	public function set_sttr_basis($new_basis)
	{
		$this->sttr_basis = (int)$new_basis;
	}
	
	public function get_sttr_basis()
	{
		if ($this->type == self::TYPE_STTR)
			return $this->sttr_basis;
		else
			return NULL;
	}
	
	public function set_avgwdlen_cleanup($new_setting)
	{
		$this->use_cleanup = (bool)$new_setting;
	}
	
	public function get_avgwdlen_cleanup()
	{
		return $this->type == self::TYPE_AVGWDLEN ? $this->use_cleanup : NULL;
	}
	
	
	/**
	 * Determines whether an integer is a real "type of feature" constant. 
	 * The "unknown" value counts as not real.
	 * 
	 * @param  int  $type
	 * @return bool
	 */
	public static function is_real_type($type)
	{
		switch($type)
		{
		case self::TYPE_SAVEDQ:
		case self::TYPE_MATHMANIP:
		case self::TYPE_STTR:
		case self::TYPE_AVGWDLEN:
			return true;
		default:
			return false;
		}
	}
	
	/**
	 * Get an all-caps string that represents the operation specified by the argument constant.
	 * 
	 * @param  int     $op  One of the MultivarFeatureDef OP_* constants.
	 * @return string       A string that is printable (as a form label, etc.)
	 */
	public static function get_op_string($op)
	{
		switch($op)
		{
		case self::OP_NONE:       return '(NONE)';
		case self::OP_ADD:        return 'PLUS'  ;
		case self::OP_SUBTRACT:   return 'MINUS' ;
		case self::OP_MULTIPLY:   return 'TIMES' ;
		case self::OP_DIVIDE:     return 'DIV BY';
		default:                  return '!ERROR';
		}
	}
	
	
	
}


/*
 * 
 * FEATURE MATRIX OBJECT FUNCTIONS
 * 
 */





/**
 * Gets a DB object corresponding to the specified feature matrix.
 * Returns false if no matching entry found.
 */
function get_feature_matrix($id)
{
	return mysqli_fetch_object(do_sql_query("select * from saved_matrix_info where id = " . (int) $id));
}



/**
 * Get info objects for all feature matrices.
 * 
 * Returns an array consisting of stdClass objects, each of which
 * contains the MySQL fields for a single saved feature matrix.
 * 
 * The array is ordered alphabetically by savename, but the ID numbers of the matrices are
 * also available (given as array keys).
 * 
 * @param  string  $corpus  If not an empty value, only feature matrices from the given corpus will be returned.
 *                          Default: empty (retrieve for all corpora).
 * @param  string  $user    If not an empty value, only feature matrices belonging to the given user will be returned.
 *                          Default: empty (retrieve for all users).
 * @return array            Array containing object list. Object IDs are keysm, as well as the "id" property of the objkects. 
 */
function get_all_feature_matrices($corpus = NULL, $user = NULL)
{
	$list = array();
	
	if (empty($corpus))
	{
		if (!empty($user))
			$where = ' where user = \'' . escape_sql($user) . '\' ';
	}
	else
	{
		$where = ' where corpus = \'' . escape_sql($corpus) . '\' ';
		if (!empty($user))
			$where .= ' and user = \'' . escape_sql($user) . '\' ';	
	}
	
	$result = do_sql_query("select * from saved_matrix_info $where order by savename asc");	
	
	while ($o = mysqli_fetch_object($result))
		$list[$o->id] = $o;
	
	return $list;
}




/**
 * Create a new feature matrix. 
 * 
 * @param  string $matrix_savename
 * @param  string $username
 * @param  string $corpus
 * @param  array  $feature_list       Array of MultivarFeatureDef objects.
 * @param  string $within_subcorpus   Integer ID of subcorpus (or, empty string for whole corpus).
 * @param  string $unit
 * @return int                        Integer ID of the resulting matrix.
 */
function create_feature_matrix($matrix_savename, $username, $corpus, $feature_list, $within_subcorpus, $unit)
{
	global $Config;
	
	/* could be lengthy for a big corpus, so we need to turn off the execution time limit. */
	php_execute_time_unlimit(); 
	// TODO, should we instead get around this with "extra time" privs?

	$matrix_savename = escape_sql($matrix_savename);
	$username = escape_sql($username);
	$corpus  = escape_sql($corpus);
	$unit = escape_sql($unit);
	
	if ('' != $within_subcorpus)
		$within_subcorpus = (int)$within_subcorpus;
	
	/* create the entry in the matrix info table */
	$id = save_feature_matrix_info( $matrix_savename, 
									$username, 
									$corpus, 
									$within_subcorpus, 
									$unit
									); 
	
	/* add entries to the variable table */
	foreach($feature_list as $variable)
		add_feature_to_matrix($id, $variable);
	
	/* 
	 * The algorithm is as follows:
	 * 
	 * - get a list of texts and their lengths from MySQL;
	 * - feature by feature, build up a multi-column infile (and create table statement)
	 * - create the table
	 * - load the infile.
	 */

	/* these are our temporary filenames: */
	$source_file = "{$Config->dir->cache}/_temp_matrix_{$Config->instance_name}.source";
	$dest_file   = "{$Config->dir->cache}/_temp_matrix_{$Config->instance_name}.dest";
		
	/* get text lengths and build array rows */
	$result = do_sql_query("select `text_id`, `words` from text_metadata_for_{$corpus} order by `text_id` asc");

	/* each member of this array is a 2-element array: first = word count; second = a temporary per-feature float. */
	$text_info = array(); 
	while ($r = mysqli_fetch_row($result))
		$text_info[$r[0]] = array ((float)$r[1], 0.0);

	file_put_contents($source_file, implode(PHP_EOL,array_keys($text_info)).PHP_EOL);
	
	$cqp = get_global_cqp();
	
	/* begin the create table SQL... */
	
	$sqltblname = feature_matrix_id_to_tablename($id);
	
	$sql = "create table `$sqltblname` ( obj_id varchar(255) NOT NULL ";

	/*	
	 * FIRST COLUMN: obj_id  - added above.
	 * Add one column per feature in order by reading the file. 
	 * 
	 * While we go, build the create-table.
	 */
	
	foreach($feature_list as $f)
	{
		/* add a create-table line. */
		$sql .= ", `{$f->label}` DOUBLE default 0.0";
		
		/* reset the array */
		array_walk($text_info, function (&$info) { $info[1] = 0.0; } );
		
		/* get the discount ratio to apply */
		$disc_ratio = $f->get_discount_ratio();

		/* generate figures for each text, depending on the feature type. */
		switch ($f->type)
		{
		case MultivarFeatureDef::TYPE_SAVEDQ:
			
			$qname = $f->get_qname();
			
			// FIXME hack hack hack (we will not always be able to do it with texts!)  
			// applies to the others below as well.
			foreach($cqp->execute("group {$qname} match text_id") as $line)
			{
				list($t, $n) = explode("\t", trim($line));
				$text_info[$t][1] = $disc_ratio * ( (float)$n / $text_info[$t][0] );
			}
			
			break;
			
			
		case MultivarFeatureDef::TYPE_MATHMANIP:

			$q1 = $f->get_qname();
			$q1_vals = array();

			foreach($cqp->execute("group {$q1} match text_id") as $line)
			{
				list($t, $n) = explode("\t", trim($line));
				$q1_vals[$t] = $n;
			}
			
			$q2 = $f->get_qname();
			$t_done = array();
			
			foreach($cqp->execute("group {$q2} match text_id") as $line)
			{
				list($t, $n) = explode("\t", trim($line));
				$op1 = isset($q1_vals[$t]) ? $q1_vals[$t] : 0;
 				$text_info[$t][1] = ((float)$f->apply_op([$op1,$n])) / $text_info[$t][0];
 				$t_done[$t] = true;
			}
			/* now we need to check for values where there was no op2. So the first val is unchanged.  */
			foreach ($q1_vals as $t => $val)
				if (!isset($t_done[$t]))
					$text_info[$t][1] = $disc_ratio * ( (float)$val / $text_info[$t][0] );
			
			break;
			
			
		case MultivarFeatureDef::TYPE_STTR:
			
			$textids = array_keys($text_info);
			
			$sttrs = calculate_text_sttrs($corpus, $f->get_sttr_basis(), $textids);
			
			foreach($textids as $t_id)
				$text_info[$t_id][1] = $sttrs[$t_id];
			
			break;
			
			
		case MultivarFeatureDef::TYPE_AVGWDLEN:
			
			$textids = array_keys($text_info);
			
			$avglens = calculate_text_avgwdlens($corpus, $f->get_avgwdlen_cleanup(), $textids);
			
			foreach($textids as $t_id)
				$text_info[$t_id][1] = $avglens[$t_id];
			
			break;
			
			
		default:
			/* not reached */
			break;
		}
		
		/* now we add a new column to the input file */
		$source = fopen($source_file, 'r');
		$dest   = fopen($dest_file, 'w');
		
		while (false !== ($line = fgets($source)))
		{
			$line = rtrim($line, "\r\n");
			list($text) = explode("\t", $line);
			$line .= "\t" . $text_info[$text][1] . PHP_EOL;
			fputs($dest, $line);
		}
		
		fclose($source);
		fclose($dest);
		
		unlink($source_file);
		rename($dest_file, $source_file);
	}
	
	/* round off the create table */
	$sql .= ') character set utf8mb4 collate utf8mb4_bin';


	/* create the mysql table that will contain the matrix. */
	do_sql_query($sql);
	
	do_sql_infile_query($sqltblname, $source_file);
	
	unlink($source_file);
	
	return $id;
}
	


// this func had maybe  better move elsewhere  TODO
//(and, likewise, need to move the one after it )
/**
 * Calculates the STTR (by $basis) for each text in the given corpus.
 * 
 * @param  string $corpus           Corpus to analyse.
 * @param  int    $basis            N of tokens for each TTR calculation.
 * @param  array  $text_list        If supplied, only the texts specified in the list will get their STTRs 
 *                                  calculated. If omitted, or an empty value passed, all texts will be
 *                                  in the array returned.
 * @return array                    Array mapping text ID to STTR. Or, boolean false for error.
 */
function calculate_text_sttrs($corpus, $basis, $text_list = NULL)
{
	global $Config;
	
	if (!is_object($c_info = get_corpus_info($corpus)))
		return false;
	
	$command = "\"{$Config->path_to_cwb}cwb-decode\" -C  -r \"{$Config->dir->registry}\" {$c_info->cqp_name} -P word -S text_id "; 
	
	$src = popen($command, 'r');
	
	$outcomes = array();
	
	$values = array();
	$types  = array();
	$tokens = 0;
	$store  = true;
	
	$present_text_id = '';
	
	/* main loop to calculate STTR. */
	while ( false !== ($line = fgets($src)) )
	{
		if ('<'== $line[0])
		{
			if (preg_match('/^<text_id (\w+)>/', $line, $m))
			{
				/* end of prev text, start of new one */
				if ($store && !empty($present_text_id))
					$outcomes[$present_text_id] = empty($values) ? 0.0 : array_sum($values)/(float)count($values);
				/* we chuck away anything less than (basis) words at the end of a given text... */
				/* texts shorter than the basis get an STTR of zero (a decision we might review later). */
				
				$present_text_id = $m[1];
				
				/* whether we just stored a value or not we need to reset the storage */
				$values = array();
				$types = array();
				$tokens = 0;
				$store = true;
				if (!empty($text_list) && !in_array($present_text_id, $text_list))
					$store = false;  /* unwanted text: skip */
			}
			/* otherwise it's likely just </text> */
		}
		else if ($store)
		{
			/* it's a token */
			$word = trim($line);
			
			/* check if we need to store a value and restart */
			if ($tokens == $basis)
			{
				/* record average value for this thousand tokens. */
				$values[] = ((float)count($types)) / (float) $basis;
				$types = array();
				$tokens = 0;
			}
			
			if (! $c_info->uses_case_sensitivity)
				$word = mb_strtolower( $word, 'UTF-8' );
			/* NB: this does not give us EXACTLY the folding that mysql_general_ci does; nor exactly that of %c. */
			if (!isset($types[$word] ))
				$types[$word] = true;

			$tokens++;
		}
		/* else, nothing, because it's a token while $store is off. */
	}
	/* handle final set of values not dealt with yet */
	if ($store && !empty($present_text_id))
		$outcomes[$present_text_id] = empty($values) ? 0.0 : array_sum($values)/(float)count($values);
	/* same code as for "start of new text" within the loop */

	
	ksort($outcomes);
	
	return $outcomes;
}
/**
 * 
 * Calculates the AvgWdLen for each text in the given corpus.
 * 
 * @param  string $corpus           Corpus to analyse.
 * @param  bool   $clean_only       If true, only tokens whose form is a "clean" word will be included.
 *                                  "Clean" is here defined in terms of the "cleanup" option of
 *                                  cwb-scan-corpus, i.e. "A regular word consists only of one or more hyphen-connected 
 *                                  components, each of which is made up of either all letters or all digits, 
 *                                  and does not start or end with a hyphen."
 * @param  array  $text_list        If supplied, only the texts specified in the list will get their AvgWdLen 
 *                                  calculated. If omitted, or an empty value passed, all texts will be
 *                                  in the array returned.
 * @return array                    Array mapping text ID to avg wd length. Or, boolean false for error.
 */
function calculate_text_avgwdlens($corpus, $clean_only, $text_list = NULL)
{
	$regex_for_clean = '/^([\pL\pM]+|\pN+)(\p{Pd}([\pL\pM]+|\pN+))*$/u';

	/* note:a lot of code is duplicated here across this func & the STTR func
	 * everything works the same way, except the actual calc'ing.
	 * So, perhaps at a later point we could factor it out.
	 */
	
	/*
	 * Note. This calculates a len in terms of char count... does this need scaling? check Biber.
	 */
	
	global $Config;
	if (! is_object ( $c_info = get_corpus_info ( $corpus ) ))
		return false;
	
	$command = "\"{$Config->path_to_cwb}cwb-decode\" -C  -r \"{$Config->dir->registry}\" {$c_info->cqp_name} -P word -S text_id "; 

	$src = popen($command, 'r');
	
	$outcomes = array();
	
// 	$len_max = 1;
	$len_sum = 0;
	$tokens = 0;
	$store = true;
	
	$present_text_id = '';
	
	/* main loop to calculate STTR. */
	while ( false !== ($line = fgets($src)) )
	{
		if ('<'== $line[0])
		{
			if (preg_match('/^<text_id (\w+)>/', $line, $m))
			{
				/* end of prev text, start of new one */
				if ($store && !empty($present_text_id))
					$outcomes[$present_text_id] = (float)$len_sum / (float)$tokens;
				
				$present_text_id = $m[1];
				
				/* whether we just stored a value or not we need to reset the storage */
				$len_sum = 0;
				$tokens = 0;
				$store = true;
				if (!empty($text_list) && !in_array($present_text_id, $text_list))
					$store = false;  /* unwanted text: skip */
			}
			/* otherwise it's likely just </text> */
		}
		else if ($store)
		{
			/* it's a token */
			$word = trim($line);
			
			if ($clean_only)
				if (!preg_match($regex_for_clean, $word))
					continue;
			
			$len = mb_strlen($word, 'UTF-8');
			$len_sum += $len;
// 			if ($len > $len_max)
// 				$len_max = $len;
				
			$tokens++;
		}
		/* else, nothing, because it's a token while $store is off. */
	}
	
	/* change each avg word length to a fraction of the range from min to max. */
// 	$len_max = max($outcomes);
// 	$len_min = min($outcomes);
// 	$diff = $len_max - $len_min;
// 	foreach($outcomes as &$o)
// 		$o = ($o-$len_min)/$diff;

// this means word len is the only feature not between 0 and 1.
	
	ksort($outcomes);
	
	return $outcomes;
}


/**
 * Delete a specified feature matrix - identified by unique integer ID.
 */
function delete_feature_matrix($id)
{
	$id = (int)$id;
	
	/* first, delete the actual data table. */
	$table = feature_matrix_id_to_tablename($id);
	do_sql_query("drop table if exists $table");
	
	/* now, delete all the rows containing information about this fm's variables. */
	do_sql_query("delete from saved_matrix_features where matrix_id = $id");
	
	/* finally, delete the database row itself. */
	do_sql_query("delete from saved_matrix_info where id = $id");
}

/**
* Translates a feature matrix description to an ID number.
* 
* @return int     ID number. False if no matching entry found.
*/
function lookup_feature_matrix_id($corpus, $user, $savename)
{
	$corpus = escape_sql($corpus);
	$user = escape_sql($user);
	$savename = escape_sql($savename);
	
	$result = do_sql_query("select `id` from saved_matrix_info where corpus='$corpus' and user='$user' and savename='$savename'");
	
	if (1 == mysqli_num_rows($result))
		return (int)mysqli_fetch_row($result)[0]; 
	
	return false;
}



/**
 * Translates a feature matrix ID code to the SQL tablename 
 * that contains the data for that feature matrix.
 * 
 * @param  int    $id Integer ID.
 * @return string     Tablename (including base-36 rep. of the ID)
 */
function feature_matrix_id_to_tablename($id)
{
	return 'featmatrix_' . int_to_base_36($id);
}

/**
 * Creates a feature matrix object in the MySQL database.
 * 
 * Note this function *does not* populate the actual database table that contains the matrix. 
 * 
 * Nor does it add any rows to the variables table.
 * 
 * @param  string $savename    Savename for the matrix.
 * @param  string $user        Username of owner.
 * @param  string $corpus      The corpus 
 * @param  int    $subcorpus   Integer ID of subcorpus (or any empty value if none).
 * @param  string $unit
 * @return int                 The ID number of the saved feature matrix we have just created.
 */
function save_feature_matrix_info($savename, $user, $corpus, $subcorpus, $unit)
{
	$savename = escape_sql($savename);
	$user = escape_sql($user);
	$corpus = escape_sql($corpus);
	/* different cos we know it will be either integer ID, or empty value which is stored as empty string. */
	$subcorpus = (empty($subcorpus)? '' : (int)$subcorpus);
	$unit = escape_sql($unit);

	$t = time();

	do_sql_query("insert into saved_matrix_info
						(savename, user, corpus, subcorpus, unit, create_time)
					values
						('$savename', '$user', '$corpus', '$subcorpus', '$unit', $t)");

	return get_sql_insert_id();
}

/**
 * Creates a feature table entry linked to the specified matrix.
 * 
 * This DOES NOT create the actual Matrix data. 
 * 
 * @param  int                 $matrix_id      Integer ID of the matrix. 
 * @param  MultivarFeatureDef  $f_spec         Object, of which the following members will be used:
 *                                              - type   - integer indicating the type of feature.
 *                                              - qname  - 
 *                                              - label  - label for this feature
 *                                              - source_info - string describing the feature's source.
 * @return int                                 The ID number of the newly created feature table entry.
 */
function add_feature_to_matrix($matrix_id, $f_spec)
{
	/* safety! */
	$matrix_id = (int)$matrix_id;

	$type = (int)$f_spec->type;
	if (! MultivarFeatureDef::is_real_type($type))
		exiterror("Unrecognised type of feature; matrix could not be created.");

	$label = escape_sql($f_spec->label);
	$info  = escape_sql($f_spec->source_info);
	
	do_sql_query("insert into saved_matrix_features (matrix_id, label, source_info) 
						values ($matrix_id, '$label', '$info')");
	$f_id = get_sql_insert_id();
	
	/* extra actions go here */
// 	switch($f_spec->type)
// 	{
// 	case MultivarFeatureDef::TYPE_SAVEDQ:
// 		/* nothing extra */
// 		break;
	
// 	case MultivarFeatureDef::TYPE_MATHMANIP:
// // qname1, qname2, operator

// 		break;
// 	default:
// 		break;	
// 	}
	

	
	return $f_id;
}



// function populate_feature_matrix()
// {
// 	// can't recall what this was intended ot be .....
// }


/**
 * Ensure that a string is a suitable label for use as a "name" for a variable in an R data frame. 
 * (Or a data object for that matter.)
 * 
 * This is implemented as a standalone func for now, but might well become a static Rface method. 
 * 
 * NB - this is applied at the time of matrix creation, since the altered labels need to be stored 
 * as part of the matrix object in the DB.
 * 
 * @param  string $label          Label to be made known-valid. 
 * @param  array  $labels_so_far  Array of label strings already used for the d`ata matrix (to assure no duplication).
 * @return string                 Guaranteed R-compatible label for a feature matrix variable.  
 */
function ensure_valid_r_label($label, $labels_so_far = NULL)
{
	/* set regex: one for a complete R var, one for checking the validity of the start */
	$rx_check_complete   = '/^([A-Za-z]|\.[_A-Za-z])[\w\._]{0,9998}$/';
	$rx_check_start_only = '/^([A-Za-z]|\.[_A-Za-z])/';
	
	/* label might come from a query save name; if so, we need to check it's an allowed R name... */
	if (!preg_match($rx_check_start_only, $label))
		$label = 'Feat_'.$label;
	
	/* label can't match a label we've already seen */
	if (!empty($labels_so_far))
	{
		$base_label = $label;
		$i = 1;
		while(in_array($label, $labels_so_far))
			$label = $base_label . '.' . ++$i;
		/* incremented suffix to differentiate. */
	}
	
	if (!preg_match($rx_check_complete, $label))
		exiterror("Bad featurelabel ``$label'' (don't know how to fix!)");
	
	return $label;
}

/**
 * Inserts a feature matrix into R (as an R matrix).
 * 
 * @param RFace   $rface                RFace object holding active link to R.
 * @param int     $id                   ID of the feature matrix you want to get a string for.
 * @param string  $desired_object_name  Name to be given to the matrix's R object.
 *                                      Default is empty, in which case, a name will be created and returned. 
 * @return string                       Name of the object inserted, or false for failure. 
 */
function insert_feature_matrix_to_r($rface, $id, $desired_object_name = '')
{
	$id = (int) $id;
	
	if (empty($desired_object_name))
		$desired_object_name = $rface->new_object_name();
	else if ($rface->object_exists($desired_object_name))
		return false;
	
	$result = do_sql_query("select label from saved_matrix_features where matrix_id = $id");
	$label_array = array();
	while ($r = mysqli_fetch_row($result))
		$label_array[] = '`'. $r[0] . '`';
	
	$labels = implode(',', $label_array);
	
	$obj_id_list = array();

	/* NB, this assumes that object IDs are OK R object identifiers */
	$result = do_sql_query("select obj_id, $labels from " . feature_matrix_id_to_tablename($id));
	while ($r = mysqli_fetch_object($result))
	{
		$cmd = $r->obj_id . ' <- c(';
		$obj_id_list[] = $r->obj_id;
		foreach($label_array as $l)
		{
			$l = str_replace('`','', $l);
			$cmd .= $r->$l . ",";
		}
		$cmd = rtrim($cmd, ',');
		$cmd .= ')';
		$rface->execute($cmd);
	}
	
	$rface->execute("$desired_object_name <- data.frame(t(cbind( " . implode(',', $obj_id_list) . ")))");
	$rface->execute("names_vec = c(" . str_replace("`", "'", $labels) . ")");
	$rface->execute("names($desired_object_name) <- names_vec");
	
	return $desired_object_name;
}



/**
 * Get feature matrix as text table(usually for download).
 * 
 * If the second argument is true (default = false) it will be returned as a string.
 * 
 * Otherwise, it will be written out via echo (And the return is Boolean true.)
 * 
 * @return  mixed   A boolean value, or a string, as requested. 
 */
function print_feature_matrix_as_text_table($id, $return_string = false)
{
	global $User;
	
	$result = do_sql_query("select * from `" . feature_matrix_id_to_tablename($id) . "`");
	
	$eol = $User->eol();
	
	$header = '';
	
	while ($field = mysqli_fetch_field($result))
		$header .= $field->name . "\t";
	
	$header = rtrim($header, "\t") . $eol;
	
	if ($return_string)
	{
		$s = $header;
		while ($r = mysqli_fetch_row($result))
			$s .= implode("\t", $r) . $eol;
		mysqli_free_result($result);
		return $s;
	}
	else
	{
		echo $header;
		while ($r = mysqli_fetch_row($result))
			echo implode("\t", $r), $eol;
		mysqli_free_result($result);
		return true;
	}
}



// TODO should be "get all"
/**
 * Lists all the variables in a given feature matrix.
 *
 * @return array  An array of database objects (representing variables from the feature matrix).
 */
function feature_matrix_list_variables($id)
{
// 	$result = do_sql_query("select * from saved_matrix_features where matrix_id = ". (int) $id);
	
// 	$list = array();
	
// 	while ($o = mysqli_fetch_object($result))
// 		$list[] = $o;

// 	return $list;
	
	return get_all_sql_objects("select * from saved_matrix_features where matrix_id = ". (int)$id);
}


/**
 * Counts the variables in a given feature matrix.
 *
 * @return array  An array of database objects (representing variables from the feature matrix).
 */
function feature_matrix_n_of_variables($id)
{
	return get_sql_value("select count(*) from saved_matrix_features where matrix_id = ". (int)$id);
}




/**
 * Lists all the data objects in a given feature matrix.
 * 
 * @param  int   $id  The matrix to interrogate (ID number). 
 * @return array      An array of strings (object ID labels)
 */
function feature_matrix_list_objects($id)
{
	return list_sql_values("select obj_id from " . feature_matrix_id_to_tablename($id));
}





/**
 * Returns a count of the number of data objects in a given feature matrix.
 * 
 * @param  int   $id  The matrix to interrogate (ID number). 
 * @return array      An array of strings (object ID labels)
 */
function feature_matrix_n_of_objects($id)
{
	return get_sql_value("select count(obj_id) from " . feature_matrix_id_to_tablename($id));
}


