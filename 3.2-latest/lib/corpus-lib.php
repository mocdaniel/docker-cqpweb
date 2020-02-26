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
 * 
 * @file
 * 
 * Functions acting on corpora (system and user)
 * 
 * ===============================================================================
 * 
 * The object covered here is the CORPUS (and the related minor object of a CORPUS CATEGORY).
 * 
 * Note that all the corpus functions were written long, long ago, when CQPweb only ever
 * operated "inside" a particular corpus:  the admin interface, main entry screen, and user
 * account screen were not even twinkles in my eye. So, at the time, the corpus argument
 * was totally superfluous: the corpus name could always be globalled in. 
 * 
 * In retrospect, and with an extra 8 years or so of software dev experience,
 * the shortcomings of this design are pretty fucking obvious.
 * 
 * That led, circa 2015, to the abomination that is the function safe_specified_or_global_corpus()
 * which globals the corpus name in if its parameter is NULL, but otherwise accepts a string
 * parameter.
 *  
 * In 2019, this was scrubbed out of the code by making all calls specify a corpus.
 * 
 * And yes, I know I should have used integer IDs for database objects. 
 * I was in fact teaching myself both PHP and MySQL at the time. 
 * 
 */



/*
 * ==========================
 * CORPUS CATEGORY MANAGEMENT
 * ==========================
 */


/** 
 * Returns a list of currently-defined corpus categories, as an array (integer keys = id numbers).
 * 
 * This list is never empty (if the database table is empty, a default entry "uncategorised" is created
 * with id number 1 (since 1 is the default category that new corpora have first off....).
 * 
 * @return array
 */
function list_corpus_categories()
{
	$result = do_sql_query("select id, label from corpus_categories order by sort_n asc");
	if (1 > mysqli_num_rows($result))
	{
		do_sql_query("ALTER TABLE corpus_categories AUTO_INCREMENT=1");
		do_sql_query("insert into corpus_categories (id, label, sort_n) values (1, 'Uncategorised', 0)");
		return array(1=>'Uncategorised');
	}
	$list_of_cats = array();
	while ($r=mysqli_fetch_row($result))
		$list_of_cats[$r[0]] = $r[1];
	return $list_of_cats;

}


function update_corpus_category_sort($category_id, $new_sort_n)
{
	$category_id = (int)$category_id;
	$new_sort_n  = (int)$new_sort_n;
	do_sql_query("update corpus_categories set sort_n = $new_sort_n where id = $category_id");
}

function delete_corpus_category($category_id)
{
	$category_id = (int)$category_id;
	do_sql_query("delete from corpus_categories where id = $category_id");
}

function add_corpus_category($label, $initial_sort_n = 0)
{
	$label = escape_sql($label);
	if (empty($label))
		return;
	$initial_sort_n = (int)$initial_sort_n;
	do_sql_query("insert into corpus_categories (label, sort_n) values ('$label', $initial_sort_n)");
}




/*
 * =================================
 * DEALING WITH THE CORPUS AS ENTITY
 * =================================
 */


/**
 * Queries whether a given corpus name exists on the system.
 * 
 * Works for user corpora as well as system corpora. 
 * 
 * @param  string $corpus        Handle of a corpus.
 * @return bool
 */
function corpus_exists($corpus)
{
	static $existing = NULL;
	if (is_null($existing))
		$existing = array_merge(list_corpora(), list_user_corpora());
	return in_array($corpus, $existing);
}


/** 
 * Returns a list of all the corpora (referred to by the corpus name strings) currently in the system, as a flat array.
 * DOES NOT include users' own corpora.
 * 
 * @return array     Array of corpus handles, ordered alphabetically.
 */
function list_corpora()
{
	return list_sql_values("select corpus from corpus_info where `owner` IS NULL order by corpus asc");
}


/** 
 * Returns a list of all the corpora currently in the system, as a hash (from handle to descriptive title).
 * DOES NOT include users' own corpora.
 * 
 * @return array     Array of corpus handles, ordered alphabetically.
 */
function list_corpora_with_titles()
{
	return list_sql_values_as_map("select corpus, title from corpus_info where `owner` IS NULL order by title asc", 'corpus', 'title');
}


/**
 * A quick way to get a list of the fields in the corpus_info table - which some of the functions
 * dealing with that table need.
 */
function get_corpus_info_sql_fields()
{
	static $cache = NULL;

	if (is_null($cache))
		$cache = array_keys(mysqli_fetch_assoc(do_sql_query("select * from corpus_info limit 1")));

	return $cache;
}

/** the corpus info functions cache DB reads in static local vars; this flushes the statics. */ 
function flush_corpus_info_caches()
{
	get_corpus_info(NULL, false);
	get_corpus_info_by_id(NULL, false);
}
/**
 * Gets a database info object for the specified corpus.
 * Returns false if the string argument is not a handle for an existing corpus.
 */
function get_corpus_info($corpus, $use_cache = true)
{
	/* we cache results, because the same corpus info will often be queried many time per script;
	 * indeed, the majority of CQPweb runs use only one corpus!    */
	static $cache = array();
	if (!$use_cache)
		$cache = array();
	if (is_null($corpus))
		return false;

	if (! isset($cache[$corpus]))
	{
		$sql_corpus = escape_sql($corpus);
		$result = do_sql_query( "select * from corpus_info where corpus = '$sql_corpus'" );
		if (0 == mysqli_num_rows($result))
			return false;
		else
			$cache[$corpus] = mysqli_fetch_object($result);
	}

	return $cache[$corpus];
}


/**
 * Gets a database info object for the specified corpus.
 * Returns false if the integer argument is not an ID number for an existing corpus.
 */
function get_corpus_info_by_id($corpus_id, $use_cache = true)
{
	/* see note above re: cache */
	static $cache = array();
	if (!$use_cache)
		$cache = array();
	if (is_null($corpus_id))
		return false;

	$corpus_id = (int)$corpus_id;

	if (!isset($cache[$corpus_id]))
	{
		$result = do_sql_query( "select * from `corpus_info` where `id` = $corpus_id" );
		if (0 == mysqli_num_rows($result))
			return false;
		else
			$cache[$corpus_id] = mysqli_fetch_object($result);
	}

	return $cache[$corpus_id];
}

/**
 * Gets an array of corpus_info objects. The array keys are the corpus
 * handles (the corpus field in the database). The array is sorted by these keys.
 * 
 * User corpora are not included.
 * 
 * If a regex argument is supplied, then only corpora whose "name" 
 * (the MySQL 'corpus' field) matches the regex will be included in the returned array.
 * Regex syntax is PCRE, not MySQL-REGEXP!
 */
function get_all_corpora_info($regex = false)
{
	$list = array();
	$result = do_sql_query("select * from corpus_info where `owner` IS NULL order by corpus asc");
	while ($o = mysqli_fetch_object($result))
	{
		if ($regex)
			if (! preg_match("|$regex|", $o->corpus))
				continue;
		$list[$o->corpus] = $o;
	}
	return $list;
}




/** returns a list of all the text IDs in the specified corpus, as a flat array */
function list_texts_in_corpus($corpus)
{
	$result = do_sql_query("select text_id from text_metadata_for_" . escape_sql($corpus));

	$list_of_texts = array();
	while ( $r = mysqli_fetch_row($result) )
		$list_of_texts[] = $r[0];
	return $list_of_texts;
}




/**
 * Gets an item of corpus metadata, either from the corpus_info table, or the corpus_metadata_variable table.
 * 
 * Normally, it's better just to get a DB object with all items.
 * 
 */
function get_corpus_metadata($corpus, $field)
{
	$corpus = escape_sql($corpus);
	$field  = escape_sql($field);

	/* we either interrogate corpus_info or corpus_metadata_variable */
	if (in_array($field, get_corpus_info_sql_fields()))
	{
		global $Corpus;
		/* if we are interrogating the global corpus, we do not need to re-query the database. */
		if ($Corpus->specified && $corpus == $Corpus->name)
			return $Corpus->$field;
		else
			$result = do_sql_query("select `$field` from corpus_info where corpus = '$corpus'");
	}
	else
		$result = do_sql_query("select `value` from corpus_metadata_variable where corpus = '$corpus' AND attribute = '$field'");

	/* was data found? */
	if ($result && 0 < mysqli_num_rows($result))
		list($value) = mysqli_fetch_row($result);
	else
		$value = "";

	return $value;
}




/**
 * Returns an array of arrays; inner arrays are database associate arrays of attribute/value. 
 * @param  string $corpus
 * @return array               Array (possibly empty) as described above.
 */
function get_all_variable_corpus_metadata($corpus)
{
	$corpus = escape_sql($corpus);
	$result = do_sql_query("select attribute, value from corpus_metadata_variable where corpus = '$corpus'");
	$list = array();
	while ($r = mysqli_fetch_assoc($result))
		$list[] = $r;
	return $list;
}



/**
 * Adds an attribute-value pair to the variable-metadata table.
 * 
 * Note, there is no requirement for attribute names to be unique.
 */
function add_variable_corpus_metadata($corpus, $attribute, $value)
{
	global $User;
	if (!$User->is_admin_or_owner($corpus))
		exiterror("Your user account does not have permission to perform that operation.");

	$corpus    = escape_sql($corpus);
	$attribute = escape_sql($attribute);
	$value     = escape_sql($value);

	$sql = "insert into corpus_metadata_variable (corpus, attribute, value) values ('$corpus', '$attribute', '$value')";
	do_sql_query($sql);
}

/**
 * Deletes an attribute-value pair from the variable-metadata table.
 * 
 * The pair to be deleted must both be specified, as well as the corpus,
 * because there is no requirement that attribute names be unique.
 */
function delete_variable_corpus_metadata($corpus, $attribute, $value)
{
	global $User;
	if (!$User->is_admin_or_owner($corpus))
		exiterror("Your user account does not have permission to perform that operation.");

	$corpus    = escape_sql($corpus);
	$attribute = escape_sql($attribute);
	$value     = escape_sql($value);

	$sql = "delete from corpus_metadata_variable 
				where corpus    = '$corpus'
				and   attribute = '$attribute'
				and   value     = '$value'";
	do_sql_query($sql);
}



/**
 * Update one or more of the corpus annotation fields (primary, 2ndary etc.) - pass in values to update as a
 * map [field=>value]. If a field is not in there, it is left unchanged; any empty value sets the DB field to NULL.
 */
function update_corpus_ceql_bindings($corpus, $update_array)
{
	static $updatable_fields = array (
						'primary_annotation', 
						'secondary_annotation', 
						'tertiary_annotation',
						'tertiary_annotation_tablehandle',
						'combo_annotation'
						);

	$corpus = escape_sql($corpus);

	flush_corpus_info_caches();

	foreach ($updatable_fields as $field)
		if (array_key_exists($field, $update_array))
			do_sql_query("update corpus_info set $field = " 
				. (empty($update_array[$field]) ? 'NULL' : ("'". escape_sql($update_array[$field]) . "'") )
				. " where corpus = '$corpus'");
}

/**
 * 
 * @param  string $corpus   Corpus handle.
 * @param  string $which    One of the CEQL fields-to-bind-to (use a constant!)
 * @param  string $handle   Handle of the annotation or mapping table.
 * @return bool             True iff it worked.
 */
function set_corpus_ceql_binding($corpus, $which, $handle)
{
	static $updatable_fields = array (
						'primary_annotation', 
						'secondary_annotation', 
						'tertiary_annotation',
						'tertiary_annotation_tablehandle',
						'combo_annotation'
						);
//TODO, now used in 2 places, should maybe eb global?
//TODO use the constants instead. 


	if (!in_array($which, $updatable_fields))
		return false;

	if ('tertiary_annotation_tablehandle' == $which)
	{
		if (!empty($handle))
			if (!get_tertiary_mapping_table($handle))
				return false;
	}
	else
	{
		if (!empty($handle))
			if (!check_is_real_corpus_annotation($corpus, $handle))
				return false;
	}
	
	$corpus = escape_sql($corpus);

	flush_corpus_info_caches();
	
	do_sql_query("update corpus_info set `$which` = " . (empty($handle)?'NULL':"'$handle'") . " where corpus = '$corpus'");
		
	return 1 == get_sql_affected_rows();
}

function update_corpus_category($corpus, $newcat)
{
	$corpus = escape_sql($corpus);
	$newcat = (int)$newcat;
	do_sql_query("update corpus_info set corpus_cat = $newcat where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_title($corpus, $newtitle)
{
	$corpus = escape_sql($corpus);
	$newtitle = escape_sql($newtitle);
	do_sql_query("update corpus_info set title = '$newtitle' where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_css_path($corpus, $newpath)
{
	$corpus = escape_sql($corpus);
	$newpath = escape_sql($newpath);
	do_sql_query("update corpus_info set css_path = '$newpath' where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_external_url($corpus, $newurl)
{
	$corpus = escape_sql($corpus);
	$newurl = escape_sql($newurl);
	do_sql_query("update corpus_info set external_url = '$newurl' where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_primary_classification_field($corpus, $newclassification)
{
	$corpus = escape_sql($corpus);
	$newclassification = cqpweb_handle_enforce($newclassification, HANDLE_MAX_FIELD);
	do_sql_query("update corpus_info set primary_classification_field = '$newclassification' where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_main_script_is_r2l($corpus, $newval)
{
	$corpus = escape_sql($corpus);
	$sqlbool = ($newval ? '1' : '0');
	do_sql_query("update corpus_info set main_script_is_r2l = $sqlbool where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_uses_case_sensitivity($corpus, $newval)
{
	$corpus = escape_sql($corpus);
	$sqlbool = ($newval ? '1' : '0');
	do_sql_query("update corpus_info set uses_case_sensitivity = $sqlbool where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_conc_scope($corpus, $newcount, $newunit)
{
	$corpus = escape_sql($corpus);
	$newcount = (int) $newcount;
	$newunit  = escape_sql($newunit);
	do_sql_query("update corpus_info set conc_scope = $newcount, conc_s_attribute = '$newunit' where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_initial_extended_context($corpus, $newval)
{
	$corpus = escape_sql($corpus);
	$newval = (int)$newval;
	do_sql_query("update corpus_info set initial_extended_context = $newval where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_max_extended_context($corpus, $newval)
{
	$corpus = escape_sql($corpus);
	$newval = (int)$newval;
	do_sql_query("update corpus_info set max_extended_context = $newval where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_alt_context_word_att($corpus, $newval)
{
	$corpus = escape_sql($corpus);
	if (check_is_real_corpus_annotation($corpus, $newval))
		do_sql_query("update corpus_info set alt_context_word_att = '$newval' where corpus = '$corpus'");
}

function update_corpus_visible($corpus, $newval)
{
	$corpus = escape_sql($corpus);
	$sqlbool = ($newval ? '1' : '0');
	do_sql_query("update corpus_info set visible = $sqlbool where corpus = '$corpus'");
	flush_corpus_info_caches();

}

function update_corpus_visualisation_position_labels($corpus, $show, $attribute)
{
	$corpus = escape_sql($corpus);
	$show = ($show ? '1' : '0');
	$attribute = escape_sql($attribute);
	do_sql_query("update corpus_info set 
							visualise_position_labels = $show,
							visualise_position_label_attribute = '$attribute' 
						where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_visualisation_gloss($corpus, $in_concordance, $in_context, $annot)
{
	$corpus = escape_sql($corpus);
	$in_concordance = ($in_concordance ? '1' : '0');
	$in_context = ($in_context ? '1' : '0');
	$annot = escape_sql($annot);
	do_sql_query("update corpus_info set 
							visualise_gloss_in_concordance = $in_concordance,
							visualise_gloss_in_context = $in_context,
							visualise_gloss_annotation = '$annot' 
						where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_visualisation_translate($corpus, $in_concordance, $in_context, $s_att)
{
	$corpus = escape_sql($corpus);
	$in_concordance = ($in_concordance ? '1' : '0');
	$in_context = ($in_context ? '1' : '0');
	$s_att = escape_sql($s_att);
	do_sql_query("update corpus_info set 
							visualise_translate_in_concordance = $in_concordance,
							visualise_translate_in_context = $in_context,
							visualise_translate_s_att = '$s_att' 
						where corpus = '$corpus'");
	flush_corpus_info_caches();
}

function update_corpus_access_statement($corpus, $new_access_statement)
{
/* ============================================================================================================================================================
 * NB. eventually we might evolve the access statemnt to something more ambitious, IE an internalised
 * licence-signup system (based on the one for BNC2014).
 * 
 * This would make it easier to host corpora like this: 
		http://www.ims.uni-stuttgart.de/forschung/ressourcen/korpora/TIGERCorpus/license/htmlicense.html
 * ============================================================================================================================================================ 
 */
	$corpus = escape_sql($corpus);
	$new_access_statement = escape_sql($new_access_statement);
	do_sql_query("update corpus_info set access_statement = '$new_access_statement' where corpus='$corpus'");
	flush_corpus_info_caches();
}

/**
 * Updates the corpus sizes in the database.
 */
function update_corpus_size($corpus)
{
	$corpus = escape_sql($corpus);
	$result = do_sql_query("select count(*) from text_metadata_for_$corpus");
	list($ntext) = mysqli_fetch_row($result);

	$info = get_corpus_info($corpus);

	$cqp = get_global_cqp();
	$cqp->set_corpus($info->cqp_name);
	$ntok = $cqp->get_corpus_tokens();
	do_sql_query("update corpus_info set size_tokens = $ntok, size_texts = $ntext where corpus = '$corpus'");
	flush_corpus_info_caches();
}

/**
 * Updates the number of word types in the corpus. (Requires freq lists to be set up, returns false if they aren't.)
 */
function update_corpus_n_types($corpus)
{
	/* potentially lengthy operation... */
	if (0 < mysqli_num_rows(do_sql_query("show tables like 'freq_corpus_{$corpus}_word'")))
	{
		list($types) = mysqli_fetch_row(do_sql_query("select count(*) from freq_corpus_{$corpus}_word"));
		do_sql_query("update corpus_info set size_types = $types where corpus = '$corpus'");
		flush_corpus_info_caches();
		return true;
	}
	else
		return false;
}

/**
 * Updates stored STTR (1,000-token wise) for the specified corpus.
 *
 * @param string $corpus
 */
function update_corpus_sttr($corpus)
{
	/* potentially lengthy : several seconds for just a 1 MW corpus. */
	php_execute_time_unlimit();

	$sttr = calculate_sttr($corpus, 1000);

	$corpus = escape_sql($corpus);

	do_sql_query("update corpus_info set sttr_1kw = $sttr where corpus = '$corpus'" );
	flush_corpus_info_caches();
}

/**
 * This is a function used for the upgrade to v 3.2.32. 
 */
function update_all_missing_sttr()
{
	$result = do_sql_query("select corpus, sttr_1kw from corpus_info");
	
	while ($o = mysqli_fetch_object($result))
		if (0.1 > $o->sttr_1kw)
			update_corpus_sttr($o->corpus);
}




/**
 * Calculates STTR (1kw) for the word attribute for a given set of begin / end pairs.
 *
 * @param  string $corpus   Name of corpus to look within.
 * @param  int    $basis    Positive integer: size of the chunks. 1 thousand tokens by default.  
 * @param  array  $poslist  Array of arrays specifying the region of the corpus to be covered.
 *                          Each inner array has 2 members: [0]=>begin, [1] =>end.
 *                          This can be empty, in which case, the whole corpus is used.
 * @return float            A float: the STTR as calculated. Or false if error.
 */
function calculate_sttr($corpus, $basis = 1000, $poslist = NULL)
{
//  should this func be here or elsewhere?

	global $Config;

	if (! is_object($c_info = get_corpus_info($corpus) ))
		return false;

	$posflag = (empty ( $poslist ) ? '' : ' -p ');

	$command = "\"{$Config->path_to_cwb}cwb-decode\" -C $posflag  -r \"{$Config->dir->registry}\"  {$c_info->cqp_name}  -P word  "; 

	$pipe = NULL;

	$proc = proc_open($command, [ ["pipe", "r"],["pipe", "w"],["pipe", "w"] ], $pipe);

	if (!empty($poslist))
	{
		/* feed pairs to STDIN */
		for($i = 0, $n = count($poslist ); $i < $n; $i ++)
			fputs($pipe[0], $poslist[$i][0] . ' ' . $poslist[$i][1] . "\n" );
	}

	$values = array();
	$types = array();
	$tokens = 0;

	/* main loop to calculate STTR. */
	while ( false !== ($line = fgets($pipe[1])) )
	{
		if ($tokens == $basis)
		{
			/* record average value for this ($basis) tokens. */
			$values[] = ((float)count($types)) / (float) $basis;
			$types = array();
			$tokens = 0;
		}

		$word = trim ($line);
		if (! $c_info->uses_case_sensitivity)
			$word = mb_strtolower($word, 'UTF-8');
		/* NB: this does not give us EXACTLY the folding that mysql_general_ci does; not exactly that of %c. */
		if (!isset($types[$word]))
			$types[$word] = true;

		$tokens++;
	}
	/* we chuck away anything less than 1K words at the end of the corpus... */

	foreach([0,1,2] as $i)
		fclose($pipe[$i]);

	proc_close($proc);

	return array_sum($values) / (float)count($values);
}




/**
 * Returns as integer the number of tokens in this corpus.
 */
function get_corpus_n_tokens($corpus)
{
	return (int)get_corpus_info($corpus)->size_tokens;
}

/**
 * Returns as integer the number of texts in this corpus.
 */
function get_corpus_n_texts($corpus)
{

	return (int)get_corpus_info($corpus)->size_texts;
}

/**
 * Returns as integer the number of word types in this corpus. Calculates it on the fly if not available.
 * 
 * Returns zero if the number of types cannot yet be calculated.
 */
function get_corpus_n_types($corpus)
{
	$c = get_corpus_info($corpus);

	if (empty($c->size_types))
	{
		if (update_corpus_n_types($corpus))
			return get_corpus_n_types($corpus); 
		else 
			return 0;
	}
	else
		return (int)$c->size_types;
}

/**
 * Returns as float the 1,000 token STTR of the specified corpus.
 */
function get_corpus_sttr($corpus)
{
	$sttr = get_corpus_info($corpus)->sttr_1kw;
	if (empty($sttr))
	{
		update_corpus_sttr($corpus);
		$sttr = get_corpus_info($corpus)->sttr_1kw;
	}
	return (float)get_corpus_info($corpus)->sttr_1kw;
}


/**
 * Updates the cached record of the on-disk size of the CWB indexes of a corpus, 
 * including the "__freq" corpus if it exists.
 * 
 * Note that if the corpus is cwb-external (reliant on indexes outside CWB's own folders),
 * those indexes aren't included. 
 * 
 * @param  string $corpus  Corpus to measure.
 */
function update_corpus_index_size($corpus)
{
	global $Config;

	if (!($c = get_corpus_info($corpus)))
		return;

	$size = ($c->cwb_external ? 0 : recursive_sizeof_directory("{$Config->dir->index}/{$c->corpus}")); 

	if (is_dir($fdir = "{$Config->dir->index}/{$c->corpus}__freq"))
		$size += recursive_sizeof_directory($fdir);

	do_sql_query("update corpus_info set size_bytes_index = $size where id = {$c->id}");
	flush_corpus_info_caches();
}

/**
 * @see update_corpus_index_size() - this is the same, except for corpus frequency tables.
 */
function update_corpus_freqtable_size($corpus)
{
	if (!($c = get_corpus_info($corpus)))
		return;

	$size = 0;
	foreach(list_corpus_freqtable_components($corpus) as $t)
		$size += get_sql_table_size($t);
	$size += get_sql_table_size("freq_text_index_$corpus");

	do_sql_query("update corpus_info set size_bytes_freq = $size where id = {$c->id}");
	flush_corpus_info_caches();
}


/**
 * Update the indexing notes field in the corpus_info table.
 * 
 * @param array $info   Array of strings containing messages.
 */
function update_corpus_indexing_notes($corpus, $info)
{
	$corpus = escape_sql($corpus);

	$txt = trim(escape_sql(implode("\n", $info)));

	if (!empty($txt))
	{
		do_sql_query("update corpus_info set indexing_notes = '$txt' where corpus = '$corpus'");
		flush_corpus_info_caches();
	}
}





/*
 * ======================
 * CORPUS SETUP FUNCTIONS
 * ======================
 */

/**
 * Get an SQL statement for the insertion of an annotation (p-att) into the database.
 * 
 * @param  string $corpus
 * @param  string $handle
 * @param  string $description
 * @param  string $tagset
 * @param  string $url
 * @param  bool   $feature_set
 * @return string                 SQL statement.
 */
function sql_for_p_att_insert($corpus, $handle, $description, $tagset, $url, $feature_set)
{
	$corpus = escape_sql($corpus);
	$handle = escape_sql($handle);
	$description = escape_sql($description);
	$tagset = escape_sql($tagset);
	$url = escape_sql($url);
	return "insert into annotation_metadata 
			(corpus, handle, description, tagset, external_url, is_feature_set) 
				values 
			('$corpus', '$handle', '$description', '$tagset', '$url', ". ($feature_set ? '1' : '0') . ")"
		;
}

/**
 * Get an SQL statement for the insertion of an XML element/attribute (s-att) into the database.
 * 
 * @param  string $corpus
 * @param  string $handle
 * @param  string $att_family     For an element, same as handle.
 * @param  string $description
 * @param  int    $datatype       One of the DATATYPE constants.
 * @return string                 SQL statement.
 */
function sql_for_s_att_insert($corpus, $handle, $att_family, $description, $datatype)
{
	$corpus = escape_sql($corpus);
	$handl = escape_sql($handle);
	$att_family = escape_sql($att_family);
	$description = escape_sql($description);
	$datatype = (int)$datatype;

	return "insert into xml_metadata 
			(  corpus,     handle,    att_family,    description,   datatype) 
				values 
			('$corpus', '$handle', '$att_family', '$description', $datatype)"
		;
}

/**
 * This function, for admin use only, updates the text metadata of the corpus with begin and end 
 * positions for each text, acquired from CQP; needs running on setup.
 * 
 * It also sets wordcount totals in the main corpus_info.
 */
function populate_corpus_cqp_positions($corpus)
{
	global $User;

	if (!($info = get_corpus_info($corpus)))
		return;

	if (!$User->is_admin_or_owner($corpus))
		exiterror("You do not have permission to perform that action.");

	$cqp = get_global_cqp();

	$cqp->set_corpus($info->cqp_name);
	$cqp->execute("A = <text> [] expand to text");
	$lines = $cqp->execute("tabulate A match, matchend, match text_id");

	/* algorithm suggested by K. Rothenhäusler provides high-speed by using a temp table to collect the positions. */
	$temp_table = "___temp_cqp_text_positions_for_{$info->corpus}";
	do_sql_query("drop table if exists `$temp_table`");
	do_sql_query("create table `$temp_table` (
						`text_id` varchar(255) NOT NULL,
						`cqp_begin` BIGINT UNSIGNED NOT NULL default '0',
						`cqp_end` BIGINT UNSIGNED NOT NULL default '0',
						primary key (text_id)
					) CHARSET utf8 COLLATE utf8_bin ");

	/* get a last-key so we know when the loop is about to end... */
	end($lines);
	$last_key = key($lines);
	reset($lines);

	$row_strings = array();

	foreach ($lines as $k=>$line)
	{
		list($begin, $end, $id) = explode("\t", trim($line, "\r\n"));
		$row_strings[] = "('$id',$begin,$end)";
		if (0 == (count($row_strings) % 10000) || $last_key == $k)
		{
			do_sql_query("insert into `$temp_table` (text_id, cqp_begin, cqp_end) VALUES " . implode(",", $row_strings));
			$row_strings = array();
		}
	}

	do_sql_query("update `text_metadata_for_$corpus`
						inner join `$temp_table`
						on  `text_metadata_for_$corpus`.text_id   = `$temp_table`.text_id
						set `text_metadata_for_$corpus`.cqp_begin = `$temp_table`.cqp_begin,
							`text_metadata_for_$corpus`.cqp_end   = `$temp_table`.cqp_end");

	do_sql_query("drop table `$temp_table`");

	/* update word counts for each text and for whole corpus */
	do_sql_query("update text_metadata_for_$corpus set words = cqp_end - cqp_begin + 1");

	/* the following depends on the CQP positions being populated, because it sums "words".... */
	update_corpus_size($corpus);
}



/**
 * Groups together the function calls needed for auto-setup of freqlist 
 * after the installation of a text metadata table.
 * 
 * Not suitable for use with REALLY big corpora of course!
 */
function setup_all_corpus_freqlist_data($corpus)
{
	squawk("$corpus: About to start running auto-pre-setup functions");

	$corpus = escape_sql($corpus);

	/* do unconditionally */
	populate_corpus_cqp_positions($corpus);

	/* if there are any classifications... */
	if (0 < mysqli_num_rows(
			do_sql_query("select handle from text_metadata_fields 
				where corpus = '$corpus' and datatype = " . METADATA_TYPE_CLASSIFICATION)
			) )
		metadata_calculate_category_sizes($corpus);

	/* if there is more than one text ... */
	$n = get_sql_value("select size_texts from corpus_info where corpus = '$corpus'");
	/* get_corpus_n_texts() uses the object; we recalculate here, because the number could have changed from 0. */
	if ($n > 1)
		make_cwb_freq_index($corpus);

	/* do unconditionally */
	corpus_make_freqtables($corpus);

	squawk("$corpus: Auto-freqlist functions complete.");
}



/**
 * Main corpus-deletion function.
 * 
 * The order of installation is WEB SYMLINK -- MYSQL -- CWB.
 * 
 * So, the order of deletion is:
 * 
 * (1) delete CWB - depends on both settings file and DB entry.
 * (2) delete MySQL - does not depend on CWB still being present
 * (3) delete the web directory symlink.
 */
function delete_corpus_from_cqpweb($corpus)
{
	if (empty($corpus))
		exiterror('No corpus specified. Cannot delete. Aborting.');

	$corpus = cqpweb_handle_enforce($corpus, HANDLE_MAX_CORPUS);


	/* get the cwb name of the corpus, etc. */
	$result = do_sql_query("select * from corpus_info where corpus = '$corpus'");
	if (1 > mysqli_num_rows($result))
		exiterror('Cannot delete: Master database entry for corpus [' . $corpus . '] is not present.' . "\n"
			. 'This can happen if the corpus information in the database has been incorrectly inserted or '
			. 'incompletely deleted. You must delete the CWB data files and any other database references manually.'
			);
	$info = mysqli_fetch_object($result);


	/* do we also want to delete the CWB data? */
	$also_delete_cwb = !$info->cwb_external;


	/* first off: delete dependent data that requires the cWB index to be in place. 
	 * This means Subcorpora! Two sorts to delete:
	 * (1) last restrictions (has no cpos file, so we can directly delete the database entry here), and then
	 * (2) all others (may have cpos file, so we must use the subcorpus object).
	 */
	do_sql_query("delete from saved_subcorpora where corpus = '$corpus' and name = '--last_restrictions'");
	$result = do_sql_query("select * from saved_subcorpora where corpus = '$corpus'");
	while (false !== ($sc = Subcorpus::new_from_db_result($result)))
		$sc->delete();

	$regfile = standard_corpus_reg_path($corpus);
	$datadir = standard_corpus_index_path($corpus);

	/* if they exist, delete the CWB registry and data for his corpus's __freq */
	if (file_exists("{$regfile}__freq"))
		unlink("{$regfile}__freq");
	recursive_delete_directory("{$datadir}__freq");
	/* 
	 * note, __freq deletion is not conditional on cwb_external -> also_delete_cwb
	 * because __freq corpora are ALWAYS created by CQPweb itself.
	 * 
	 * But the next deletion, of the main corpus CWB data, IS so conditioned.
	 *
	 * What this implies is that a registry file / data WON'T be deleted 
	 * unless CQPweb created them in the first place -- even if they are in
	 * the CQPweb standard registry / data locations.
	 */
	if ($also_delete_cwb)
	{
		/* delete the CWB registry and data */
		if (file_exists($regfile))
			unlink($regfile);
		recursive_delete_directory($datadir);
	}

	/* CWB data now clean: on to the MySQL database. All these queries are "safe":
	 * they will run OK even if some of the expected data has already been deleted. */

	/* delete all saved restrictions, saved queries, saved frequency tables, and saved dbs associated with this corpus */
// 	$result = do_sql_query("select query_name from saved_queries where corpus = '$corpus'");
// 	while ($r = mysqli_fetch_row($result))

	uncache_restrictions_by_corpus($corpus);

	foreach(list_sql_values("select query_name from saved_queries where corpus = '$corpus'") as $sq)
		delete_cached_query($sq);

// 	$result = do_sql_query("select dbname from saved_dbs where corpus = '$corpus'");
// 	while ($r = mysqli_fetch_row($result))
	foreach(list_sql_values("select dbname from saved_dbs where corpus = '$corpus'") as $sdb)
		delete_db($sdb);

// 	$result = do_sql_query("select freqtable_name from saved_freqtables where corpus = '$corpus'");
// 	while ($r = mysqli_fetch_row($result))
	foreach(list_sql_values("select freqtable_name from saved_freqtables where corpus = '$corpus'") as $sft)
		delete_freqtable($sft);


	/* delete main frequency tables */
	delete_corpus_freqtable($corpus);
// 	$result = do_sql_query("select handle from annotation_metadata where corpus = '$corpus'");
// 	while ($r = mysqli_fetch_row($result))
// 		do_sql_query("drop table if exists freq_corpus_{$corpus}_{$r[0]}");
// 	do_sql_query("drop table if exists freq_corpus_{$corpus}_word");

	/* delete CWB freq-index table */
	do_sql_query("drop table if exists `freq_text_index_$corpus`");

	/* clear the text metadata */
	delete_text_metadata_for($corpus);

	/* clear the annotation metadata */
	do_sql_query("delete from annotation_metadata where corpus = '$corpus'");

	/* clear any xml-idlink metadata */
	foreach(get_all_xml_info($corpus) as $x)
		if (METADATA_TYPE_IDLINK == $x->datatype)
			delete_xml_idlink($corpus, $x->handle);

	/* clear the XML metadata */
	delete_xml_metadata_for($corpus);

	/* delete the variable metadata */
	do_sql_query("delete from corpus_metadata_variable where corpus = '$corpus'");

	/* corpus_info is the master entry, so we have left it till last. */
	do_sql_query("delete from corpus_info where corpus = '$corpus'");
	flush_corpus_info_caches();

	/* sql cleanup is now complete */

	/* NOTE, this order of operations means it is possible - if a failure happens at 
	 * the right point - for the web entry to exist, but for the interface not to know
	 * about it (because there is no "master entry" in the MySQL corpus_info table).
	 * 
	 * This is low risk - a leftover symlink should not be so very problematic. */

	/* SO FINALLY: delete the web "directory" (actually a symlink to ../exe) */
	if (!empty($info->owner))
		$weblink = get_user_corpus_web_path($info->id, $info->owner);
	else
		$weblink = "../$corpus";
	if (is_link($weblink))
		unlink($weblink); 

// TODO clear out any entries in the restriction cache that depend on this corpus.

}




/*
 * ==========================
 * CORPUS ALIGNMENT FUNCTIONS
 * ==========================
 */




/**
 * Gets an associative array (corpus/att handle --> description) of a-attributes
 * belonging to the specified corpus.
 * 
 * An a-attribute always has the same handle as the target corpus that it points to.
 * 
 * So there is no separate "description" field: it is drawn from the corpus info
 * of the target corpus of the a-attribute.
 * 
 * If there are no a-attributes on this corpus, an empty array is returned.
 * 
 * @param string $corpus  The corpus whose a-attributes are to be returned.
 */
function list_corpus_alignments($corpus)
{
	$corpus = escape_sql($corpus);
	return list_sql_values_as_map("select corpus_alignments.target as `targ`, corpus_info.title as `desc` 
									from corpus_alignments left join corpus_info on corpus_alignments.target = corpus_info.corpus 
									where corpus_alignments.corpus = '$corpus'", 'targ', 'desc');
// // 	$result = do_sql_query();
// 	$list = array();

// 	while ($o = mysqli_fetch_object($result))
// 		$list[$o->targ] = $o->desc;

// 	return $list;
}




/**
 * Checks the global user's permissions on a set of available alignment permissions.
 * 
 * Removes any that the User does not have at least restricted-level access to.
 * 
 * @param  array $alignments  An array of alignment handle=>title pairs as returned by list_corpus_alignments()
 * @return array              Copy of input array, with any no-permission corpora deleted. 
 *                            (This may be an empty array.)
 */
function check_alignment_permissions($alignments)
{
	global $User;

	if ($User->is_admin())
		/* little shortcut since the admin user can always access everything. */
		return $alignments;
	else
	{
		$seek_permissions = array(PRIVILEGE_TYPE_CORPUS_FULL,PRIVILEGE_TYPE_CORPUS_NORMAL,PRIVILEGE_TYPE_CORPUS_RESTRICTED);

		$allowed_alignments = array();

		foreach ($alignments as $aligned_corpus => $desc)
		{
			foreach($User->privileges as $p)
			{
				/* check permission type FIRST to short-circuit use of in-array on non-array scope object */ 
				if (in_array($p->type, $seek_permissions) && in_array($aligned_corpus, $p->scope_object))
				{
					$allowed_alignments[$aligned_corpus] = $desc;
					break;
				}
			}
		}

		return $allowed_alignments;
	}
}




/*
 * ====================================
 * CWB-UTIL AND INDEX-RELATED FUNCTIONS
 * ====================================
 */


function standard_corpus_index_path($corpus)
{
	global $Config;
	return "{$Config->dir->index}/$corpus";
}


function standard_corpus_reg_path($corpus)
{
	global $Config;
	return "{$Config->dir->registry}/$corpus";
}




/**
 * Gets a path to the index directory of the specified corpus; 
 * this will be within CQPweb's datadir, unless the corpus is flagegd as cwb_external.
 * In the latter case, if the HOME folder can't be found from the registry file,
 * then thus function returns false.
 * 
 * @param  string  $corpus            Corpus to inquire about. 
 * @param  bool    $assert_writeable  If true, the function will abort if the directory 
 *                                    cannot be written to. Defaults false.
 * @return string                     Either a string containing the directory to write to,
 *                                    or boolean false if the directory cannot be found. 
 */
function get_corpus_index_directory($corpus, $assert_writeable = false)
{
	if (!($c_info = get_corpus_info($corpus)))
		return false;


	if ($c_info->cwb_external)
	{
		/* parse the reg file to find out where the data is */

		$regdata = read_cwb_registry_file($corpus);

		if (preg_match('/^HOME\s+"(.*?)"\s*$/m', $regdata, $m))
			;
		else if (preg_match('/^HOME\s+(.*?)\s*$/m', $regdata, $m))
			;
		else
		{
			if ($assert_writeable)
				exiterror("CWB external corpus: could not find HOME directory in the registry file.");
			else
				return false;
		}

		$path = trim($m[1]);
	}
	else
		$path = standard_corpus_index_path($corpus);

	if ($assert_writeable)
		if (!is_writeable($path))
			exiterror("Corpus ''$corpus'': index data directory is not writeable for CQPweb.");

	return $path;
}



/**
 * Create the shell command for running cwb-encode to create a new corpus.
 * 
 * @param  string $corpus         Lowercase form of corpus name.
 * @param  string $charset        Characterset using CWB string specifier.
 * @param  array  $datadir        Where to save the corpus data files.
 * @param  array  $filespec       An array of file paths; will be shellarg-escaped.
 * @param  array  $p_spec         Array of p-attribute handles. Those meant to be feature sets should already be suffixed with '/'.
 * @param  array  $s_spec         Array of s-attribute specs. They need to have all their value-attributes included.
 * @param  array  $error_message  Out param: reference to array of error messages. If the func returns false, a message will be appended here.
 * @return string                 Command to run cwb-encode with exec(). Or, false in case of a problem.
 */
function cwb_encode_new_corpus_command($corpus, $charset, $datadir, $filespec, $p_spec, $s_spec, &$error_message = [])
{
	global $Config;

	if (!cqpweb_handle_check($corpus, HANDLE_MAX_CORPUS))
	{
		$error_message[] = "Bad corpus handle: $corpus; could not create encode command.";
		return false;
	}

	$charset = strtolower($charset);
	if (is_null(CQP::translate_corpus_charset_to_iconv($charset)))
	{
		$error_message[] = "Bad charset: $charset; could not create encode command.";
		return false;
	}

	if (!(is_dir($datadir) && is_writeable($datadir)))
	{
		$error_message[] = "$datadir is not a writeable directory; cannot create encode command.";
		return false;
	}
	$qdatadir = escapeshellarg($datadir);

	$encode_command = "{$Config->path_to_cwb}cwb-encode -xsB -c $charset -d $qdatadir";

	foreach($filespec as $f)
		$encode_command .= ' -f ' . escapeshellarg($f);

	$encode_command .= ' -R ' . escapeshellarg(standard_corpus_reg_path($corpus));

	foreach($p_spec as $p)
		if ('word' != $p)
			$encode_command .= ' -P '. $p;
	foreach($s_spec as $s)
		$encode_command .= ' -S '. $s;

	$encode_command .= ' 2>&1';
	/* NB the 2>&1 works on BOTH Win32 AND Unix */

	return $encode_command;
}


/**
 * Run the two compression steps for a corpus (huffcode, compress-rdx)
 * and delete the uncompressed files.
 * 
 * @param  string $CORPUS             CQP name of a corpus (ie the uppercase format)
 * @param  array $lines_of_output     Out parameter: preserved output (minus deletable file lines).
 * @return bool                       True for all OK; else false.
 */
function cwb_compress_corpus_index($CORPUS, &$lines_of_output = NULL)
{
	global $Config;

	if (is_null($lines_of_output))
		$lines_of_output = [];

	$compression_output = array();


	foreach (['cwb-huffcode','cwb-compress-rdx'] as $prog)
	{
		$exit_status_from_cwb = 0;
		$compression_output[] = $compress_command = "{$Config->path_to_cwb}$prog -r \"{$Config->dir->registry}\" -A $CORPUS 2>&1";
		exec($compress_command, $compression_output, $exit_status_from_cwb);
		if ($exit_status_from_cwb != 0)
		{
			$compression_output[] = "$prog reported an error! Corpus index compression aborted.";
			return false;
		}
		$compression_output = delete_cwb_uncompressed_data($compression_output);
	}

	$lines_of_output = array_merge($lines_of_output, $compression_output);

	return true;
}




/**
 * Gets the corpus charset from the corpus's registry.
 * 
 * @param  string $corpus  Name of corpus.
 * @return string          The characeteer set (verbatim as in registry file).
 */
function get_cwb_registry_charset($corpus)
{
	if (false === ($regdata = read_cwb_registry_file($corpus)))
		exiterror("Invalid corpus specified for registry read");

	if (!preg_match('/##::\s+charset\s*=\s*"(\w+)"/', $regdata, $m))
		exiterror("Could not parse charset from registry file for $corpus!!");
	else
		return $m[1];
}


/**
 * Deletes CWB corpus uncompressed data files that have been declared no longer needed.
 * 
 * Pass this function an array of CWB corpus-setup program output lines, and any file
 * that is declared deletable will be deleted, if possible.
 * 
 * @param  array $messages  Array of lines of cwb-huffcode or cwb-compress-rdx output (collected by exec function or otherwise).
 * @return array            Same array, but with the "delte-thjis-file" instructions removed. 
 */
function delete_cwb_uncompressed_data($messages)
{
	foreach ($messages as $k => $line)
		if (0 < preg_match('/!! You can delete the file <(.*)> now/', $line, $m))
			if (is_file($m[1]))
				if (unlink($m[1]))
					unset($messages[$k]);
	return array_values($messages);
}


/**
 * Utility function; many funcs currently have code to read a registry file,
 * over time replace them with calls to this.
 *
 * (this is a registry file WITIHN CQPweb's own reg directory... not elsewhere.)
 *
 * @param  string $corpus  Name of corpus.
 * @return string          String containing the contents of the registry file,
 *                         or boolean false if the file could not be found.
 */
function read_cwb_registry_file($corpus)
{
	$path = standard_corpus_reg_path($corpus);

	if (!file_exists($path))
		return false;
	else
		return file_get_contents($path);
}


/**
 * Utility function, many funcs currently have code to write a registry file, 
 * over time replace them with calls to this.
 * 
 * (this is a registry file WITIHN CQPweb's opwn reg directory... nto elsewhere.)
 * 
 *  @param string $corpus    Name of corpus.
 *  @param string $regdata   Full contents of the file to write.
 *  @return bool             True iff file was successfully written.
 */
function write_cwb_registry_file($corpus, $regdata)
{
	$path = standard_corpus_reg_path($corpus);
	return (false !== file_put_contents($path, $regdata));
}





/**
 * Get the output of cwb-describe-corpus for a given corpus.
 * 
 * The corpus's registry file must be in the usual CQPweb registry directory. 
 * 
 * @param  string $corpus_cqp_handle  Uppercase handle for the corpus, as used in CQP.
 * @return string                     Single string containing the textual output from cwb-describe-corpus.
 */
function get_cwb_describe_corpus($corpus_cqp_handle)
{
	global $Config;

	if (! preg_match('/^[A-Z0-9_]+$/', $corpus_cqp_handle))
		exiterror("Invalid CQP corpus name used with cwb-describe-corpus!!");

	$op = array();
	$reg_path = escapeshellarg($Config->dir->registry);
	exec("{$Config->path_to_cwb}cwb-describe-corpus -s -r $reg_path " . $corpus_cqp_handle, $op);
	return implode(PHP_EOL, $op);
}






