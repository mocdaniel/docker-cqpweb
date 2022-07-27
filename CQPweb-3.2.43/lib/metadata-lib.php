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
 * Library of database-access functions for dealing with metadata tables.
 * 
 * All metadata has a DATATYPE, and may also have a list of categories (in assoc table) if it
 * is a classification.
 */






/** 
 * Core function for metadata: gets an array of info about this corpus' fields. 
 * Other functions that ask things about metadata fields interface to this. 
 * 
 * So this gets you "metadata about metadata", so to speak.
 * 
 * Format: an array of objects (keys = field handles). 
 * Each object has 3 members: handle, description, datatype.
 * 
 * @param  string $corpus   Name of corpus.
 * @return array            Array of text metadata field-info objects.
 */
function get_all_text_metadata_info($corpus)
{
	$corpus = escape_sql($corpus);
	
	return get_all_sql_objects("SELECT handle, description, datatype FROM text_metadata_fields WHERE corpus = '$corpus'", 'handle');
}



function text_metadata_table_exists($corpus)
{
	$corpus = escape_sql($corpus);

	return (0 < mysqli_num_rows(do_sql_query("show tables like 'text_metadata_for_$corpus'")));
}


/** 
 * Returns true if the argument, once cast to int, is a valid datatype.
 * Otherwise returns false.
 */
function metadata_valid_datatype($type)
// TODO maybe "check_valid_metadata_datatype" ? or, "datatype_is_valid_for_meta" ? or "is_valid_metadata_datatype"?
{
	global $Config;
	return array_key_exists((int)$type, $Config->metadata_mysql_type_map);
}

/**
 * Returns a three-member object (->handle, ->datatype, ->description) or false
 * if the field supplied as argument does not exist.
 * 
 * (Single-field accessor function to the data extracted in metadata_get_array_of_metadata().)
 */
function get_text_metadata_field_info($corpus, $field)
{
	static $cache = [];
	if (! isset($cache[$corpus]))
		$cache[$corpus] = get_all_text_metadata_info($corpus);

	return (isset($cache[$corpus][$field]) ? $cache[$corpus][$field] : false);
}



/**
 * Returns an array of field handles for the metadata table in this corpus.
 */
function list_text_metadata_fields($corpus)
{
	return array_keys(get_all_text_metadata_info($corpus));
}



/**
 * Returns an array of all the classification schemes and
 * their descs for the specified corpus. 
 * 
 * If a field's description is NULL or an empty string 
 * in the database, a copy of the handle 
 * is put in place of the description. 
 * This default functionality can be turned off 
 * by setting the second argument to false.
 * 
 * @param  string  $corpus
 * @param  bool    $disallow_empty_description   If true (as is default), empty descriptions will
 *                                               be overwritten with a copy of the handle. 
 * @return array                                 A map from key (handle) to value (description). 
 */
function list_text_metadata_classifications($corpus, $disallow_empty_description = true)
{
	$list = array();
	
	$result = do_sql_query("SELECT handle, description FROM text_metadata_fields 
							WHERE corpus = '$corpus' AND datatype = " . METADATA_TYPE_CLASSIFICATION);

	while ($m = mysqli_fetch_object($result))
		$list[$m->handle] = ($disallow_empty_description && empty($m->description)) ? $m->handle : $m->description; 
// 	foreach(get_all_text_metadata_info($corpus) as $m)
// 	{
// 		if (METADATA_TYPE_CLASSIFICATION == $m->datatype)
// 		{
// 			if ($disallow_empty_descriptions && empty($m->description))
// 				$m->description = $m->handle;
// 			$return_me[$m->handle] = $m->description;
// 		}
// 	}
	
	return $list;
}


/**
 * Returns true if this text metadata field name is a classification; false if it is free text.
 * 
 * An exiterror will occur if the field does not exist!
 */
function metadata_field_is_classification($corpus, $field)
{
	$obj = get_text_metadata_field_info($corpus, $field);
	if (empty($obj))
		exiterror("Unknown metadata field specified!\n");
	return $obj->datatype == METADATA_TYPE_CLASSIFICATION;
}



/**
 * Expands the handle of a text metadata field to its description.
 * 
 * If there is no description, the handle is returned unaltered.
 */
function expand_text_metadata_field($corpus, $field)
{
	$obj = get_text_metadata_field_info($corpus, $field);
	return (empty($obj) ? $field : (empty($obj->description) ? $field : $obj->description));
}


/**
 * Expands a pair of text metadata field/value handles to their descriptions.
 * 
 * Returns an array with two members: field, value - each containing the "expansion",
 * i.e. the description entry from MySQL.
 */
function expand_text_metadata_attribute($corpus, $field, $value)
{
	$corpus = escape_sql($corpus);
	$field  = escape_sql($field);
	$value  = escape_sql($value);
	
	$sql = "SELECT description FROM text_metadata_values WHERE corpus = '$corpus' AND field_handle = '$field' AND handle = '$value'";

	if (0 == mysqli_num_rows($result = do_sql_query($sql)))
		$exp_val = $value;
	else
	{
		list($exp_val) = mysqli_fetch_row($result);
		if (empty($exp_val))
			$exp_val = $value;
	}
	
	return array('field' => expand_text_metadata_field($corpus, $field), 'value' => $exp_val);
}




/**
 * Returns an associative array (field=>value) for the text with the specified text id.
 * 
 * If the third argument is specified, it should be an array of field handles; only those fields will be returned.
 * 
 * If the third argument is not specified, then all fields will be returned.
 * 
 * @return array
 */
function metadata_of_text($corpus, $text_id, $fields = NULL)
{
	$corpus = escape_sql($corpus);
	$text_id = escape_sql($text_id);
	
	if (empty($fields))
		$sql_fields = '*';
	else
	{
		$fields = array_map('escape_sql', $fields);
		$sql_fields = '`' . implode('`,`', $fields) . '`';
	}

	$sql = "select $sql_fields from text_metadata_for_$corpus where text_id = '$text_id'";
	
	return mysqli_fetch_assoc(do_sql_query($sql));
}



/**
 *  Returns a list of category handles occuring for the given classification. 
 */
function metadata_category_listall($corpus, $classification)    // not currently used anywhere,. It['s pretty usekless, witness array_keys replacemetn.
{
	return array_keys(list_text_metadata_category_descriptions($corpus, $classification));
}



/**
 * Returns an associative array of category descriptions,
 * where the keys are the handles, for the given classification.
 * 
 * If no description exists, the handle is set as the description.
 */
function list_text_metadata_category_descriptions($corpus, $classification)
{
	$corpus = escape_sql($corpus);
	$classification = escape_sql($classification);

	$result = do_sql_query("SELECT handle, description FROM text_metadata_values WHERE field_handle = '$classification' AND corpus = '$corpus'");

	$return_me = array();
	
	while ($r = mysqli_fetch_row($result))
		$return_me[$r[0]] = (empty($r[1]) ? $r[0] : $r[1]);
	
	return $return_me;
}




/**
 * Returns a list of text IDs, plus their category for the given classification. 
 */
function metadata_category_textlist($corpus, $classification)
{
	// NB : this func seems not tot beuised anywhere at present.
	// "get_text_to_metadata_category_map"?
	
	
	$corpus = escape_sql($corpus);
	$classification = escape_sql($classification);
	
	$result = do_sql_query("SELECT `text_id`, `$classification` FROM text_metadata_for_$corpus");

	$return_me = array();
	
	while ($r = mysqli_fetch_assoc($result))
		$return_me[] = $r; // TODO make this return a hash table instead. Need to check places whrere this uis called.
	
	return $return_me;
// 	 poss new name? list_texts_with_metadata_value()?
}

/**
 * returns the size of a category within a given classification 
 * as an array with [0]=> size in words, [1]=> size in files
 */ 
function metadata_size_of_cat($corpus, $classification, $category)
{
	//  this func seems not to be used anywhere right now
	
	$corpus         = escape_sql($corpus);
	$classification = escape_sql($classification);
	$category       = escape_sql($category);

	$sql = "SELECT sum(words) FROM text_metadata_for_$corpus where `$classification` = '$category'";   // isn't the category num words stopred somethwere????? text_metadata_values??? get_category_info()
	list($size_in_words) = mysqli_fetch_row(do_sql_query($sql));

	$sql = "SELECT count(*) FROM text_metadata_for_$corpus where `$classification` = '$category'";
	list($size_in_files) = mysqli_fetch_row(do_sql_query($sql));

	return array($size_in_words, $size_in_files);
}


/** As metadata_size_of_cat(), but thins by an additional classification-catgory pair (for crosstabs) */
function metadata_size_of_cat_thinned($corpus, $classification, $category, $class2, $cat2)
{
	//  this func seems not to be used anywhere right now

	if (!text_metadata_table_exists($corpus))
		exiterror("Bad corpus name, or no metadata table exists.");
	
	$corpus         = escape_sql($corpus);
	$classification = escape_sql($classification);
	$category       = escape_sql($category);
	$class2         = escape_sql($class2);
	$cat2           = escape_sql($cat2);

	$sql = "SELECT sum(words) FROM text_metadata_for_$corpus where `$classification` = '$category' and `$class2` = '$cat2'";
	list($size_in_words) = mysqli_fetch_row(do_sql_query($sql));

	$sql = "SELECT count(*) FROM text_metadata_for_$corpus where `$classification` = '$category' and `$class2` = '$cat2'";
	list($size_in_files) = mysqli_fetch_row(do_sql_query($sql));

	return array($size_in_words, $size_in_files);
}




/** 
 * Counts the number of words in each text class for this corpus,
 * and updates the table containing that info.
 * 
 * This is done for a single classification, if a handle is provided;
 * if not, it is donefor all classifications.
 * 
 * @param string $corpus  The corpus.
 * @param string $handle  Optional handle of the classification to work on; 
 *                        if none is provided, all classifications are processed.
 */
function metadata_calculate_category_sizes($corpus, $handle = NULL)
{
	$corpus = escape_sql($corpus);

	/* get a list of classification schemes */
	$sql = "select handle from text_metadata_fields where corpus = '$corpus' and datatype = " . METADATA_TYPE_CLASSIFICATION;
	if (!empty($handle))
	{
		$handle = escape_sql($handle);
		$sql .= " and handle = '$handle'";
	}
	$result_list_of_classifications = do_sql_query($sql);
	
	/* for each classification scheme ... */
	while ($c = mysqli_fetch_row($result_list_of_classifications) )
	{
		$classification_handle = $c[0];
		
		/* get a list of categories */
		$sql = "select handle from text_metadata_values where corpus = '$corpus' and field_handle = '$classification_handle'";
		$result_list_of_categories = do_sql_query($sql);
		
		/* for each category handle found... */
		while ($d = mysqli_fetch_row($result_list_of_categories)) 
		{
			$category_handle = $d[0];
			
			/* how many files / words fall into that category? */
			$sql = "select count(*), sum(words) from text_metadata_for_$corpus where $classification_handle = '$category_handle'";
			
			$result_counts = do_sql_query($sql);
			
			if (mysqli_num_rows($result_counts) > 0)
			{
				list($file_count, $word_count) = mysqli_fetch_row($result_counts);

				$sql = "update text_metadata_values 
							set category_num_files = '$file_count',
							    category_num_words = '$word_count'
							where corpus       = '$corpus' 
							and   field_handle = '$classification_handle' 
							and   handle       = '$category_handle'";
				do_sql_query($sql);
			}
		} /* loop for each category */
	} /* loop for each classification scheme */
}



/**
 * Returns the "data-tooltip" attribute as a string for links to the specified text_id.
 */
function print_text_metadata_tooltip($text_id)
{
	global $Corpus;
	
	static $stored_tts = array();
	
	/* avoid re-running the queries / string building code for a text whose tooltip has already been created;
	 * worth doing because we KNOW a common use-case is to have lots of concordances from the same text visible at once,
	 * which will cause this function to be called once per concordance line. */
	if (isset($stored_tts[$text_id]))
		return $stored_tts[$text_id]; 
	
	$text_data = metadata_of_text($Corpus->name, $text_id);
	if (empty($text_data))
		return "";
	
	$result = do_sql_query("select handle from text_metadata_fields 
								where corpus = '{$Corpus->name}' and datatype = ".METADATA_TYPE_CLASSIFICATION);
	//if (0 == mysqli_num_rows($result))
	//	return "";
	$tail = ( 0 == mysqli_num_rows($result) ? '' : '--------------------<br>');
	
	$tt = 'data-tooltip="' 
			. str_replace('"', '&quot;', 
					'Text <strong>' . $text_id . '</strong><br>'
				. '<em>(length = ' . number_format($text_data['words'], 0)
				. ' words)</em><br>' . $tail
				)
			;
	while ($field = mysqli_fetch_object($result)) 
	{
// TODO inefficient, no? could we do a join? Or get the field info in an array? Or get the dectipytion in the previousn sql query?
		$item = expand_text_metadata_attribute($Corpus->name, $field->handle, $text_data[$field->handle]);
		if (!empty($item['value']))
			$tt .= str_replace('"', '&quot;', '<em>' . escape_html($item['field']) 
						. ':</em> <strong>' 
						. escape_html($item['value']) . '</strong><br>'
					);
	}

	$tt .= '"';
	
	/* store for later use */
	$stored_tts[$text_id] = $tt;
	
	return $tt;
}




/**
 * Freetext metadata fields are allowed to contain certain special forms which indicate
 * external resources of one kind or another. These are detected by examining the value's
 * "prefix", which is the part of the value before the first colon.
 * 
 * This function takes a value from a metadata table and returns a link or a video/audio/img
 * embed that can be sent to the browser.
 * 
 * @param  string $value  A text/idlink metadata table single value.
 * @return string         HTML render of the value to display.
 */
function render_metadata_freetext_value($value)
{
	/* if the value is a URL, convert it to a link;
	 * also allow audio, image, video, YouTube embeds */
	if (false !== strpos($value, ':') )
	{
		list($prefix, $url) = explode(':', $value, 2);
		
		switch($prefix)
		{
		case 'http':
		case 'https':
		case 'ftp':
			/* pipe is used as a delimiter between URL and linktext to show. */
			if (false !== strpos($value, '|'))
				list($url, $linktext) = explode('|', $value);
			else
				$url = $linktext = $value;
			$show = '<a target="_blank" href="'.$url.'">'.escape_html($linktext).'</a>';
			break;
			
		case 'youtube':
			/* if it's a YouTube URL of one of two kinds, extract the ID; otherwise, it should be a code already */
			if (false !== strpos($url, 'youtube.com'))
			{
				/* accept EITHER a standard yt URL, OR a yt embed URL. */
				if (preg_match('|(?:https?://)(?:www\.)youtube\.com/watch\?.*?v=(.*)[\?&/]?|i', $url, $m))
					$ytid = $m[1]; 
				else if (preg_match('|(?:https?://)(?:www\.)youtube\.com/embed/(.*)[\?/]?|i', $url, $m))
					$ytid = $m[1];
				else
					/* should never be reached unless bad URL used */
					$ytid = $url;
			}
			/* also allow the abbreviated "youtu.be" style. */
			else if (false !== strpos($url, 'youtu.be'))
			{
				$junk = explode('/', $url);
				$ytid = end($junk);
			}
			else
				$ytid = $url;
			$show = '<iframe width="640" height="480" src="http://www.youtube.com/embed/' . $ytid . '" frameborder="0" allowfullscreen></iframe>';
			break;
			
		case 'video':
			/* we do not specify height and width: we let the video itself determine that. */
			$show = '<video src="' . $url . '" controls preload="metadata"><a target="_blank" href="' . $url . '">[Click here for videofile]</a></video>';
			break;
			
		case 'audio':
			$show = '<audio src="' . $url . '" controls><a target="_blank" href="' . $url . '">[Click here for audiofile]</a></audio>';
			break;
		
		case 'image':
			/* Dynamic popup layer: see textmeta.js */
			$show = '<a class="menuItem" href="" onClick="textmeta_add_iframe(&quot;' . $url . '&quot;); return false;">[Click here to display]</a>';
			break;
			
		default;
			/* unrecognised prefix: treat as just normal value-content */
			$show = escape_html($value);
			break;
		}
	}
	/* otherwise simply escape it */
	else
		$show = escape_html($value);
	
	return $show;
}



/**
 * Add a new field to a corpus's text metadata.
 * 
 * @param string $corpus       Corpus we are adding to.
 * @param string $handle       Handle of the column to add.
 * @param string $description  Description of the column to add.
 * @param int    $datatype     Datatype constant for the new column.
 * @param string $input_path   Path to the input file: se notes on add_field_to_metadata_table().
 */
function add_text_metadata_field($corpus, $handle, $description, $datatype, $input_path)
{
	$datatype = (metadata_valid_datatype($datatype) ? (int) $datatype : METADATA_TYPE_FREETEXT);
	
	if (in_array($handle, list_text_metadata_fields($corpus)))
		exiterror("Cannot add metadata field $handle because a field by that name already exists.");
	
	add_field_to_metadata_table("text_metadata_for_$corpus", $handle, $datatype, $input_path);
	
	/* update the text_metadata_fields list */
	$corpus = cqpweb_handle_enforce($corpus);
	$handle = cqpweb_handle_enforce($handle);
	$description = escape_sql($description);

	do_sql_query("insert into text_metadata_fields 
		(corpus, handle, description, datatype) 
			VALUES 
		('$corpus','$handle','$description', $datatype)");

	/* and, if it is a classification, scan for values, then update the cat sizes for the values etc. */
	if (METADATA_TYPE_CLASSIFICATION == $datatype)
	{
		$result = do_sql_query("select distinct($handle) from text_metadata_for_$corpus");

		while ($r = mysqli_fetch_row($result))
			do_sql_query("insert into text_metadata_values 
				(corpus, field_handle, handle)
					values
				('$corpus', '$handle', '{$r[0]}')"
				);

		metadata_calculate_category_sizes($corpus, $handle);
	}
}

function drop_text_metadata_field($corpus, $handle)
{
	if (!in_array($handle, list_text_metadata_fields($corpus)))
		exiterror("Cannot drop metadata field $handle because that field seems not to exist.");
	
	$corpus = cqpweb_handle_enforce($corpus);
	$handle = cqpweb_handle_enforce($handle);
	
	do_sql_query("delete from text_metadata_fields where corpus='$corpus' and handle='$handle'");
	do_sql_query("delete from text_metadata_values where corpus='$corpus' and field_handle='$handle'");
	do_sql_query("alter table text_metadata_for_$corpus drop column `$handle`");
}


/**
 * This function adds a column to a metadata table, but *does not* 
 * do any of the associated actions e.g. inserting a line into the monitoring table,
 * or compiling-and-inserting the list of classification categories.
 * 
 * @see add_text_metadata_field()
 * @see add_idlink_field()
 * 
 * @param string $table        Database table we are modifying.
 * @param string $handle       Handle of the column to add.
 * @param int    $datatype     Datatype constant for the new column.
 * @param string $input_path   Fielsystem path for the input file, which should be
 *                             a plain text file wiht 2 tab-separated columns:
 * @param string $input_path   Full filesystem path for the input file, which should be
 *                             a plain text file with 2 tab-separated columns:
 *                             col 1. the ID; col 2. the new field's value for that ID.
 *                             No check is made for ID uniqueness or for presence of all IDs.
 */
function add_field_to_metadata_table($table, $handle, $datatype, $input_path)
{
	global $Config;
	
	$table = escape_sql($table);
	$handle = cqpweb_handle_enforce($handle);
	$datatype = (int) $datatype;
	
	/* check file exists by trying to open */
	if (false === ($source = fopen($input_path, 'r')))
		exiterror("Could not open the specified metadata input file.");
	
	if (preg_match('/^text_metadata_for_/', $table))
		$id_field = 'text_id';
	else
		$id_field = '__ID';
	
	/* add column to the table; if a duplicate colmn name has been used, this will abort. Caller should also check this, for neatness. */
	do_sql_query("alter table `$table` add column `$handle` {$Config->metadata_mysql_type_map[$datatype]}");
	/* note, ideally we'd add it "BEFROE `words`" for neatness, but this unfortunately does nto work. MySQl has only AFTER. */ 
	
	switch($datatype)
	{
	case METADATA_TYPE_CLASSIFICATION:
	case METADATA_TYPE_UNIQUE_ID:
	case METADATA_TYPE_IDLINK:
		$line_regex = '/^(\w+)\t(\w+)$/';
		break;
	default:
		/* all other datatypes */
		$line_regex = '/^(\w+)\t(.*)$/';
		break;
		/* note: this will need adjusting for dataypes such as DATE. */
	}
	
	$line_n = 0;
	
	while (false !== ($line = fgets($source)))
	{
		$line_n++;
		$line = rtrim($line, "\r\n");
		if (empty($line))
			continue;

		/* check format of line */
		if (! preg_match($line_regex, $line, $m))
		{
			do_sql_query("alter table `$table` drop column `$handle`");
			exiterror("Badly formed line: # $line_n in file $input_path.");
		}
		$id = $m[1];
		$val = escape_sql($m[2]);
		
		/* insert the actual data. Doing it one at a time is a bit inefficient but this is 
		 * not a time-critical action. */
		do_sql_query("update `$table` set `$handle` = '$val' where `$id_field` = '$id'");
	}
	
	fclose($source);
	
	if (METADATA_TYPE_CLASSIFICATION == $datatype)
		do_sql_query("alter table `$table` add index (`$handle`)");
}




/*
 * METADATA SETUP FUNCTIONS
 * ========================
 * 
 * ..... used by both admin and user corpus-install processes.
 */



/**
 * Utility function for the create_text_metadata functions.
 * 
 * Returns nothing, but deletes the text_metadata_for table and aborts the script 
 * if there are any non-word values in the specified field.
 * 
 * Use for categorisation columns. A BIT DIFFERENT to how we do it for text ids
 * (different error message).
 * 
 * (NB - doesn't do any other cleanup e.g. temporary files).
 * 
 * This function should be called before any other updates are made to the database.
 */
function create_text_metadata_check_field_words($corpus, $field)
{
	$bad_ids = create_text_metadata_get_bad_ids($corpus, $field);
	if (empty($bad_ids))
		return;
	
	/* database revert to zero text metadata prior to abort */
	do_sql_query("drop table if exists text_metadata_for_" . escape_sql($corpus));
	do_sql_query("delete from text_metadata_fields where corpus = '" . escape_sql($corpus) . '\'');
	
	$msg = "The data source you specified for the text metadata contains badly-formatted "
		. " category handles in field [$field], as follows: \n"
		. $bad_ids
		. "\n ... (category handles can only contain unaccented letters, numbers, and underscore)."
		;
	
	exiterror($msg);
}

/**
 * Returns false if there are no bad ids in the field specified.
 * 
 * If there are bad ids, a string containing those ids (space/semi-colon separated) is returned.
 */
function create_text_metadata_get_bad_ids($corpus, $field)
{
	$corpus = escape_sql($corpus);
	$field  = escape_sql($field);

	$result = do_sql_query("select distinct `$field` from `text_metadata_for_$corpus` where `$field` REGEXP '[^A-Za-z0-9_]'");
	if (0 == mysqli_num_rows($result))
		return false;

	$bad_ids = '';
	while ($r = mysqli_fetch_row($result))
		$bad_ids .= " '{$r[0]}';";

	return $bad_ids;
}




/**
 * Utility function for the create_text_metadata_..... functions.
 * 
 * Returns nothing, but deletes the text_metadata_for table and aborts the script 
 * if there are bad text ids.
 * 
 * (NB - doesn't do any other cleanup e.g. temporary files).
 * 
 * This function should be called before any other updates are made to the database.
 */
function create_text_metadata_check_text_ids($corpus)
{
	if (false === ($bad_ids = create_text_metadata_get_bad_ids($corpus, 'text_id')))
		return;

	/* database revert to zero text metadata prior to abort */
	do_sql_query("drop table if exists text_metadata_for_" . escape_sql($corpus));
	do_sql_query("delete from text_metadata_fields where corpus = '" . escape_sql($corpus) . '\'');
	
	$msg = array(
		"The data source you specified for the text metadata contains badly-formatted text ID codes, as follows: "
		, $bad_ids
		, "(text ids can only contain unaccented letters, numbers, and underscore)."
		);
	
	exiterror($msg);
}

/**
 * Wrapper round create_text_metadata_from_file() for when we need to create the file from CQP.
 *
 * @see   create_text_metadata_from_file()
 * @param string $corpus  The corpus affected. (System "name").
 * @param array  $fields  Field descriptors, as per create_text_metadata_from_file();
 *                        however, all handles MUST be valid s-attributes.
 * @param string $primary_classification
 *                        As per create_text_metadata_from_file().
 */
function create_text_metadata_from_xml($corpus, $fields, $primary_classification = NULL)
{
	global $Config;

	if (! cqpweb_handle_check($corpus))
		exiterror("Invalid corpus argument to create text metadata function!");

	if ( ! ($c_info = get_corpus_info($corpus)) )
		exiterror("Corpus $corpus does not seem to be installed! Metadata import from XML aborts.");	

	$full_filename = "{$Config->dir->upload}/___createMetadataFromXml_$corpus";

	/* quickly process the fields. */
	$fields_to_show = '';
	foreach($fields as $f)
	{
		if (!xml_exists($f['handle'], $corpus))
			exiterror("You have specified an s-attribute that does not seem to exist!");
		$fields_to_show .= ', match ' . $f['handle'];
	}
	/* other than the above, we leave all checks of the field array to the "wrapped" function */

	$cqp = get_global_cqp();
	/* refresh the corpus list, in case we are dealing with a newly-created corpus. */
	$cqp->refresh_available_corpora();
	$cqp->set_corpus($c_info->cqp_name);
	$cqp->execute('c_M_F_xml = <text> []');
	$cqp->execute("tabulate c_M_F_xml match text_id $fields_to_show > \"$full_filename\"");
// squawk ( $full_filename . (file_exists($full_filename) ? ' does ' : ' doesn\'t ') . 'exist.');

	/* the wrapping is done: pass to create_text_metadata_from_file() */
	create_text_metadata_from_file($corpus, $full_filename, $fields, $primary_classification);

	/* cleanup the temp file */
	unlink($full_filename);
}


/**
 * Install a text-metadata table for the given corpus.
 * 
 * @param string $corpus  The corpus affected. (System "name").
 * @param string $file    Full path to the input file to use.
 * @param array  $fields  Array of field descriptors. A field descriptor is an associative array
 *                        of three elements: handle, description, datatype. The fields need to be in
 *                        the correct order to match the columns of the input file. 
 * @param string $primary_classification
 *                        A handle, corresponding to something in the array of fields, which is
 *                        will be installed as the corpus's primary classification. Optional.
 */
function create_text_metadata_from_file($corpus, $file, $fields, $primary_classification = NULL)
{
	global $Config;

	if (!cqpweb_handle_check($corpus))
		exiterror("Invalid corpus argument to create text metadata function!");

	if (!corpus_exists($corpus))
		exiterror("Corpus $corpus does not seem to be installed! Metadata setup aborts.");	

	if (!is_file($file))
		exiterror("The metadata file you specified ($file) does not appear to exist! Metadata setup aborts.");



	/* get ready to process field declarations... */

	$cols_that_must_be_handles = array( 0 );
	$corresponding_col = 0;

	$classification_scan_statements = array();
	$inserts_for_metadata_fields = array();

	$create_statement = "create table `text_metadata_for_$corpus`(
		`text_id` varchar(255) NOT NULL";


	/* process field declarations to get all SQL setup actions. */

	foreach ($fields as $field)
	{
		/* Keep track of which column of the datafile we are looking at... */
		$corresponding_col++;
		/* because of text id, first field = col 1. */
		
		$field['handle'] = cqpweb_handle_enforce($field['handle']);
		$field['description'] = escape_sql($field['description']);
		
		/* check for valid datatype */
		if(! metadata_valid_datatype($field['datatype'] = (int)$field['datatype']))
			exiterror("Invalid datatype specified for field ``{$field['handle']}''.");
		
		/* the record in the metadata-fields table has a constant format.... */
		$inserts_for_metadata_fields[] = 
			"insert into text_metadata_fields 
			(corpus, handle, description, datatype)
			values 
			('$corpus', '{$field['handle']}', '{$field['description']}', {$field['datatype']} )
			";

		/* ... but the create statement depends on the datatype */
		$create_statement .= ",\n\t\t`{$field['handle']}` {$Config->metadata_mysql_type_map[$field['datatype']]}";
		
		/* ... as do any additional actions */ 
		switch ($field['datatype'])
		{
		case METADATA_TYPE_CLASSIFICATION:
			/* we need to scan this field for values to add to the values table! */
			$classification_scan_statements[$field['handle']] = "select distinct({$field['handle']}) from text_metadata_for_$corpus";
			$cols_that_must_be_handles[] = $corresponding_col;
			break;
			
		case METADATA_TYPE_FREETEXT:
			/* no extra actions */
			break;
		
		/* TODO extra actions for other datatypes here. */
//
//TODO idlink especially. not done on texts,a lthough done on XML, for now..... (as of 3.2.7)
//
//uniq ID needs a handle check too...
//
		
		/* no default needed, because we have already checked for a valid datatype above. */
		}
	}

	/* add the standard fields; begin list of indexes. */
	$create_statement .= ",
		`words` INTEGER NOT NULL default '0',
		`cqp_begin` BIGINT UNSIGNED NOT NULL default '0',
		`cqp_end` BIGINT UNSIGNED NOT NULL default '0',
		primary key (text_id)
		";
	
	/* we also need to add an index for each classifcation-type field;
	 * we can get these from the keys of the scan-statements array */
	foreach (array_keys($classification_scan_statements) as $cur)
		$create_statement .= ", index(`$cur`) ";
	
	/* finish off the rest of the create statement */
	$create_statement .= " ) CHARSET=utf8";
// squawk(preg_replace('/\s+/',' ',$create_statement));

	$cols_required = 1 + count($fields);

	
	/* we now have all the SQL commands -- it's time to start doing things. */
	
	$data_errors = array();
	
	/* create a temporary input file with the additional necessary zero fields (for CQP positions) */
// TODO : abstract out the temp file creation into a funciton so that the IDLINK table setup can use it -- avoid repetition??
// call it preprocess_metadata_file() and get it to return the array of errors or empty value if none?)
	$input_file = "{$Config->dir->cache}/___install_temp_{$Config->instance_name}";
	
	$source = fopen($file, 'r');
	$dest = fopen($input_file, 'w');
	
	$n = 0; /* line number */
	
	while (false !== ($line = fgets($source)))
	{
		$n++;

		$line = rtrim($line, "\r\n");

		/* Various validity checks before write. */
		$cols = explode("\t", $line);
		if (($nc = count($cols)) != $cols_required)
			$data_errors[] = "Input file line $n: ERROR, incorrect number of columns (expected $cols_required, got $nc).";
		foreach ($cols_that_must_be_handles as $c)
			if (!cqpweb_handle_check($cols[$c], 255)) /* TODO longterm, should the handle length here be constant or config variable?? */
				$data_errors[] = "Bad metadata value on input file line $n in column $c: ``{$cols[$c]}'' .";
		/* no point even continuing if too many errors have accumulated. */ 
		if (49 < count($data_errors))
			break;
		/* end of validity checks. */

		fputs($dest, $line . "\t0\t0\t0\n");
	}
	fclose($source);
	fclose($dest);
	
	/* Did any of our validity checks fail? */

	if (!empty($data_errors))
	{
// 		unlink($input_file);      // this could be in the input-file-preprocess function see TODO comment above.
		$msg = array(
				"Text metadata setup aborted due to errors in the metadata input file. Please fix the file and try again."
				,"Errors listed below (to maximum of 50):"
			);
		exiterror(array_merge($msg, $data_errors));
	}
	
	
	/* now we have our checked-correct input file, execute all the collected SQL commands! */
	
	do_sql_query($create_statement);
	
	do_sql_infile_query("text_metadata_for_$corpus", $input_file);
	
	unlink($input_file);

	foreach($inserts_for_metadata_fields as $ins)
		do_sql_query($ins);

	
	/* check resulting table for invalid text ids and invalid category handles */
	create_text_metadata_check_text_ids($corpus);
	/* again, use the keys of the classifications array to work out which we need to check */
	foreach (array_keys($classification_scan_statements) as $cur)
		create_text_metadata_check_field_words($corpus, $cur);
	/* note -- the above should not now be necessary, because we checked the input file while adding
	 * the empty columns. Doesn't hurt to have, though. */


	foreach($classification_scan_statements as $field_handle => $statement)
	{
		$result = do_sql_query($statement);

		while ($r = mysqli_fetch_row($result))
			do_sql_query("insert into text_metadata_values
					(corpus, field_handle, handle)
					values
					('$corpus', '$field_handle', '{$r[0]}')"
				);
	}

	/* if one of the classifications is primary, set it */
	if (array_key_exists($primary_classification, $classification_scan_statements))
		do_sql_query("update corpus_info set primary_classification_field = '$primary_classification' where corpus = '$corpus'");
	
	flush_corpus_info_caches(); /* just in case */

	/* there is no return value. *IF* anything has gone wrong, exiterror() will have been called above. */
}

/**
 * A much, much simpler version of create_text_metadata()
 * which simply creates a table of text_ids with no other info.
 */
function create_text_metadata_minimalist($corpus)
{
	global $Config;

	$c_info = get_corpus_info($corpus);
	
	if (empty($c_info))
		exiterror("Corpus $corpus does not seem to be installed! Minimalist metadata setup aborts.");	

	$input_file = "{$Config->dir->cache}/___install_temp_metadata_$corpus";

	exec("{$Config->path_to_cwb}cwb-s-decode -n -r \"{$Config->dir->registry}\" {$c_info->cqp_name} -S text_id > $input_file");
// tella2("{$Config->path_to_cwb}cwb-s-decode -n -r \"{$Config->dir->registry}\" {$c_info->cqp_name} -S text_id > $input_file");

	$create_statement = "create table `text_metadata_for_$corpus`(
		`text_id` varchar(".HANDLE_MAX_ITEM_ID.") NOT NULL default '',
		`words` INTEGER UNSIGNED NOT NULL default 0,
		`cqp_begin` BIGINT UNSIGNED NOT NULL default 0,
		`cqp_end` BIGINT UNSIGNED NOT NULL default 0,
		primary key (text_id)
		) CHARSET=utf8";

	do_sql_query($create_statement);

	do_sql_infile_query("text_metadata_for_$corpus", $input_file);

	create_text_metadata_check_text_ids($corpus);
	
	/* since it's minimilist, there are no classifications. */

	unlink($input_file);
	
	/* finally call position and word count update. */
	populate_corpus_cqp_positions($corpus);
}



/** 
 * Deletes the metadata table plus the records that log its fields/values.
 * this is a separate function because it reverses the "create_text_metadata_for" function 
 * and it is called by the general "delete corpus" function 
 */
function delete_text_metadata_for($corpus)
{
	$corpus = escape_sql($corpus);
	
	/* delete the table */
	do_sql_query("drop table if exists text_metadata_for_$corpus");
	
	/* delete its explicator records */
	do_sql_query("delete from text_metadata_fields where corpus = '$corpus'");
	do_sql_query("delete from text_metadata_values where corpus = '$corpus'");
}




















//
// TODO this class, and related mucking-about with datatypes, should perhaps be moved to "datatype-lib.php"....
//

/*
 * LIBRARY FOR METADATA_TYPE_DATE
 * ==============================
 */


/**
 * This class repesents a date in CQPweb metadata.
 * 
 * We need this class because MySQL dates only start at CE 1000.
 * 
 * These dates are only rough-gradience, to allow us to skim over issues like different month/year lengths,
 * Julian vs Gregorian issues, leap years, etc. etc. So we do not actually test for date validity,
 * EXCEPT that the year CANNOT be zero. So +1999_0231 will be accepted as valid.
 * 
 * Serialised format for database (and CWB!) storage:
 * 
 *  +yyyymmdd
 *  -yyyymmdd
 * 
 * Where "yyyy" is padded with zeroes; there can be any number of digits in the "yyyy",
 * but the number of digits in the mm and dd is fixed at 2; if there are 2 yy digits, 
 * this is NOT interepted as 20th/21st century but as 1st century CE.
 * 
 * Note that the leading plus or minus is NOT optional.
 * 
 * Vagueness:
 */ 
class VagueDate
{
	/* integer: years = CE; negative = BCE. */
	private $year;
	/* integer 1 to 12;  */
	private $month;
	/* intetger 1 to 31 */
	private $day;

	/** 
	 * regex - including delimiters - which validates a serialised date, and also captures its 4 components
	 * (sign, year, month, day).
	 */
	const VALIDITY_REGEX = '/^([+\-])(\d+)_(\d\d)(\d\d)$/';
	
	/** Creates a date object from a serialised string. */
	public function __construct($serialised)
	{
		if (! preg_match(self::VALIDITY_REGEX, trim($serialised), $m))
			exiterror("Invalid string argument to CQPwebMetaDate() ! ");
		
		if (0  == ($this->year  = (int)$m[2]))
			exiterror("Invalid year value to CQPwebMetaDate() ! There was no year zero.");
		if ('-' == $m[1])
			$this->year *= -1;

		if (13 <= ($this->month = (int)$m[3]))
			exiterror("Invalid month value to CQPwebMetaDate() ! Month cannot be more than 12.");
		if (0 == $this->month)
			exiterror("Invalid month value to CQPwebMetaDate() ! Month cannot be zero.");
		
		if (32 <= ($this->day   = (int)$m[4]))
			exiterror("Invalid day value to CQPwebMetaDate() ! Day cannot be more than 31.");
		if (0 == $this->day)
			exiterror("Invalid day value to CQPwebMetaDate() ! Day cannot be zero.");
	}
	
	/** Gets a string serialisation of the contents of this date. */
	public function serialise()
	{
		return ($this->year < 0 ? '-' : '+') . sprintf("%04d%02d%02d", abs($this->year), $this->month, $this->day);
	}


	/** 
	 * Returns a string that can be used to declare a MySQL field.
	 * 
	 * By default the field will be big enough to store dates from 1st Jan 9,999BCE to 31st Dec 9,999CE,
	 * i.e. a 4 digit year.
	 * 
	 * You can request a longer year by setting the argument to 5 or above.
	 */
	public function sql_type($year_num_digits = 4)
	{
		$year_num_digits = ($year_num_digits >= 4 ? (int)$year_num_digits : 4);
		$width = $year_num_digits + 5;
		return " varchar($width) default NULL CHARSET ascii COLLATE ascii_bin";
	}

// TODO difference - in years and/or in months, because days is dicey? Makle this a fucntion rathwr thana method?
// have to make the difference between -1 and 1 be 1 instead of 2!
// TODO group into month buckets?
// all this stuff: wait till we find out what we need. Only put basic operations as class methods. Anything fancy should be a separate function.

	
	/**
	 * @param VagueDate $earlier_date
	 * @return ????????????????????????
	 */
	public function timespan_since($earlier_date)
	{
		
		
	}
}








