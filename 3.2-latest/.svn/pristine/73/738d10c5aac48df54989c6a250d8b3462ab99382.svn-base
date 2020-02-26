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
 * This is the XML library - where "xml" means "interface to S-attributes" just as
 * in CQPweb generally, "annotation" means "interface to P-attributes".
 * 
 * The xml_metadata table makes extensive use of the "attribute family".
 * 
 * These are groups of attributes based on XML <el att="val">...</el> style input.
 * The resulting s-attributes (el, el_att) all have exactly the same sets of ranges.
 * So, we call them a family and only ever store their range-points *once*. 
 * As and when it seems necessary, we flip between treating them as separate s-attributes 
 * (where appropriate) and hiding this truth from the user in favour of making it look like
 * they are still an XML-style structure. The "parent" of a family is its *element* i.e. whatever
 * was the element name of the original XML. This element, by definition, does not have a value;
 * its value is split up into different s-attributes. The element can be spotted because its
 * "family" is listed in the database as being the same as its handle.
 * 
 * Every XML thingy has a type - the same range of datatypes exist as for corpus metadata
 * (since, after all, "text" is just an XML element - listed as normal in the database, but 
 * treated specially by the code. An s-attribute "without annotation" - such as the "parent"
 * of a family - has the NONE datatype.
 * 
 * If the datatype is CLASSIFICATION, then the possible values of that s-attribute all go into
 * the xml_metadata_values table as well.
 * 
 * If the datatype is IDLINK, then there is expected to be an idlink table that satisfies it.
 * 
 * The functions to be found here fall into 2 groups.
 * 
 * First, there are the ones that handle various types of access to the xml_metadata table,
 * which is the master-record of information about s-attributes. If this were object-oriented
 * stylie, which it isn't, these would be methods for the object representing an s-attribute.
 * 
 * 
 * Finally, there are the ones with the names xml_visualisation_*; this cluster 
 */




/*
 * ============================================
 * FUNCTION LIBRARY FOR XML/S-ATTRIBUTE CONCEPT
 * ============================================
 */



/**
 * Gets an array with handles of all s-attributes in the specified corpus.
 * 
 * The array is associative: handle=>description
 */
function list_xml_all($corpus)
{
	$corpus = escape_sql($corpus);
	
	$list = array();
	$result = do_sql_query("select handle,description from xml_metadata where corpus = '$corpus'");
	while ($r = mysqli_fetch_assoc($result))
		$list[$r['handle']] = $r['description'];
	
	return $list;
}

/**
 * Gets a full set of database objects for xml elements/attributes, from the database;
 * returned in associative array whose keys repeat the object's "handle" element.
 */
function get_all_xml_info($corpus)
{
	$corpus = escape_sql($corpus);
	
	$list = array();
	$result = do_sql_query("select * from xml_metadata where corpus = '$corpus'");
	while ($o = mysqli_fetch_object($result))
		$list[$o->handle] = $o;
	
	return $list;
}

/**
 * Gets an info object for the specified XML (from the database).
 * 
 * Returns false if not found.
 */
function get_xml_info($corpus, $xml_handle)
{
	$corpus = escape_sql($corpus);
	$xml_handle = escape_sql($xml_handle);
	
	$result = do_sql_query("select * from xml_metadata where corpus = '$corpus' and handle = '$xml_handle'");
	if (0 == mysqli_num_rows($result))
		return false;
	
	return mysqli_fetch_object($result);
}

/**
 * Checks whether or not the specified s-attribute exists in the specified corpus.
 * 
 */
function xml_exists($s_attribute, $corpus)
{
	$corpus = escape_sql($corpus);
	$s_attribute = escape_sql($s_attribute);
	return (0 < mysqli_num_rows(do_sql_query("select handle from xml_metadata where handle = '$s_attribute' and corpus = '$corpus'")));
}

/**
 * Gets an array of all s-attributes that have annotation values 
 * (includes all those derived from attribute-value annotations
 * of another s-attribute that was specified xml-style).
 * 
 * That is, the listed attributes definitely have a value that can be printed.
 * 
 * The array is associative (handle=>description).
 */
function list_xml_with_values($corpus)
{
	$corpus = escape_sql($corpus);

	$list = array();
	$result = do_sql_query("select handle,description from xml_metadata where corpus = '$corpus' and datatype !=" . METADATA_TYPE_NONE);
	while ($r = mysqli_fetch_assoc($result))
		$list[$r['handle']] = $r['description'];

	return $list;
}

/**
 * Gets an associative array of of s-attributes that are elements
 * (that is, they are not a member of some other s-attribute's "family"...)
 * in the format handle => description .
 */
function list_xml_elements($corpus)
{
	$corpus = escape_sql($corpus);
	
	$list = array();
	$result = do_sql_query("select handle,description from xml_metadata where corpus = '$corpus' and att_family = handle");
	while ($r = mysqli_fetch_assoc($result))
		$list[$r['handle']] = $r['description'];

	return $list;
}

/**
 * Gets an array of s-attributes that are subordinate
 * members of a specified element's "attribute family"; 
 * array is associative in the format handle=>description.
 */
function get_xml_family_attributes($corpus, $element)
{
	$corpus  = escape_sql($corpus);
	$element = escape_sql($element);

	$list = array();
	$result = do_sql_query("select handle,description from xml_metadata 
								where corpus = '$corpus'
								and handle != '$element'
								and att_family = '$element'");
	while ($r = mysqli_fetch_assoc($result))
		$list[$r['handle']] = $r['description'];

	return $list;
}




/**
 *  Returns a list of category handles occuring for the given classification.
 * 
 * If categories are not set up, or the $xml_handle is of a datatype other than 
 * CLASSIFICATION, the result will be an empty array.
 */
function xml_category_listall($corpus, $xml_handle)
{
	$corpus = escape_sql($corpus);
	$xml_handle = escape_sql($xml_handle);

	$result = do_sql_query("SELECT handle FROM xml_metadata_values WHERE att_handle = '$xml_handle' AND corpus = '$corpus'");

	$return_me = array();
	
	while ($r = mysqli_fetch_row($result)) 
		$return_me[] = $r[0];
	
	return $return_me;
}



/**
 * Returns an associative array of category descriptions,
 * where the keys are the handles, for the given classification.
 * 
 * If no description exists, the handle is set as the description.
 * 
 * If categories are not set up, or the $xml_handle is of a datatype other than 
 * CLASSIFICATION, the result will be an empty array.
 */
function xml_category_listdescs($corpus, $xml_handle)
{
	$corpus = escape_sql($corpus);
	$xml_handle = escape_sql($xml_handle);

	$result = do_sql_query("SELECT handle, description FROM xml_metadata_values WHERE att_handle = '$xml_handle' AND corpus = '$corpus'");

	$return_me = array();
	
	while ($r = mysqli_fetch_row($result))
		$return_me[$r[0]] = (empty($r[1]) ? $r[0] : $r[1]);
	
	return $return_me;
}



/**
 * Resets the description of an XML to a specified value.
 */
function update_xml_description($corpus, $handle, $new_desc)
{
	$corpus = escape_sql($corpus);
	$handle = escape_sql($handle);
	$new_desc = escape_sql($new_desc);
	
	do_sql_query("update xml_metadata set description = '$new_desc' where corpus = '$corpus' and handle = '$handle'");
}


/**
 * Changes the datatype of an XML thingie in the database, including all necessary parallel changes.
 * 
 * Note that if type conversion fails due to some condition not being fulfilled in the values in the underlying
 * index, then the type will become FREETEXT, which is the fallback type.
 */
function change_xml_datatype($corpus, $handle, $new_datatype)
{
	$corpus = escape_sql($corpus);
	$handle = escape_sql($handle);
	$new_datatype = (int) $new_datatype;
	
	if (!($x = get_xml_info($corpus, $handle)))
		exiterror(escape_html("Undefined XML element $corpus -- $handle specified."));
	
	/* check that fromtype and to type are not the same.  If they are, return. */
	if ($x->datatype == $new_datatype)
		return;
	
	/* if existing data type is not freetext, change it to freetext.
	 * first, in this switch, we do other necessary actions for different types. */
	switch($x->datatype)
	{
	/* the cases where nothing is actually done. */
	case METADATA_TYPE_FREETEXT:
		/* obviously no action here */
	case METADATA_TYPE_DATE:
	case METADATA_TYPE_UNIQUE_ID:
		/* no other actions necessary. The values are simply read as plain strings from now on. */
		break;
		
	case METADATA_TYPE_IDLINK:
		/* Extra action: delete the idlink table if it exists; delete entries from idlink_fields and idlink_values. */
		delete_xml_idlink($corpus, $handle);
		break;
	
	case METADATA_TYPE_CLASSIFICATION:
		/* extra action: we need to delete entries from xml_metadata_values (category table). */
		do_sql_query("delete from xml_metadata_values where corpus='$corpus' and att_handle='$handle'");
		break;
	
	case METADATA_TYPE_NONE:
		exiterror("The datatype of an XML element cannot be changed, because it is empty.");
	
	default:
		exiterror("Undefined metadata type specified."); 
		/* shouldn't actually be reachable, because bad datatype constants ought never to go into the DB */ 
	}
	
	/* in ANY case, we need to delete any restrictions from cache that involve this handle */
	uncache_restrictions_by_xml($corpus, $handle);

	/* this is just in case of trouble in the latter half of the function. */
	do_sql_query("update xml_metadata set datatype = " . METADATA_TYPE_FREETEXT . " where corpus='$corpus' and handle='$handle'");
	
	/* so, where are we now? we are changing FROM free text to something else. */
	
	switch($new_datatype)
	{
	case METADATA_TYPE_FREETEXT:
		/* already done above: so we can actually just return from the function. */
		return;
	
	case METADATA_TYPE_IDLINK:
		/* no checks needed, since the values on the target table need not be handles. */
		break;

	case METADATA_TYPE_UNIQUE_ID:
		/* We have to check that the values (a) are unique, (b) are CQPweb handles. 
		 * Unique - because that's the whole point. Handles - because text_id has this datatype. */
		$badval = '';
// TODO use constant below rather than hardcoded length of 255 for unique IDs???????????
		if ( ! xml_index_has_handles($corpus, $handle, 255, $badval))
			exiterror("The datatype of $handle cannot be changed to [unique ID], because there are non-handle values in the CWB index;"
				. " the first non-handle value found in the index is [$badval] .");
		if ( ! xml_index_is_unique($corpus, $handle))
			exiterror("The datatype of $handle cannot be changed to [unique ID], because there are duplicate values in the CWB index.");
		break;

	case METADATA_TYPE_DATE:
		/* check that the values are all correctly-formatted date strings. */
		if ( ! xml_index_is_valid_date($corpus, $handle))
			exiterror("The datatype of $handle cannot be changed to [date], because there are non-date values in the CWB index.");
		break;
		
	case METADATA_TYPE_CLASSIFICATION:
		/* this is the tough one! Note that the procedures below implicitly involve TWO sweeps through the s-attribute.
		 * We accept this overhead for the sake of simplicity and also because s-attributes are not actually all that big.
		 * (plus, after the first time, the index file is likely to be in OS cache, so it should be pretty quick ... */
		
		/* first: check that the values are all valid category handles */
		$badval = '';
// TODO use constant below rather than hardcoded length of 200 for catgegory handles???????
		if ( ! xml_index_has_handles($corpus, $handle, 200, $badval))
			exiterror("The datatype of $handle cannot be changed to [classification], because there are non-category-handle values in the CWB index;"
				. " the first non-handle value found in the index is [$badval] .");
		
		/* so we can safely build a record for the categories of this classification */
		setup_xml_classification_categories($corpus, $handle);

		break;
		
	case METADATA_TYPE_NONE:
		exiterror("You cannot change the datatype of an XML attribute to \"none\", as the data is still there in the underlying index.");

	default:
		exiterror("Critical error: an undefined metadata type specified."); 
	}

	/* so we can now do this. */
	do_sql_query("update xml_metadata set datatype = $new_datatype where corpus='$corpus' and handle='$handle'");

	/* and that's it! */
}



/**
 * Check index contents of a corpus's XML attribute for problems.
 * Normally done straight after setup.
 * 
 * If an attribute is found to have a problem, it will be converted
 * to free-text (so that its values will work).
 * 
 * @param  string $corpus       Corpus to analyse.
 * @param  string $only_check   If an xml handle is given here, only that one will be checked. 
 * @return bool                 True for all OK, false for one or more attributes with problems.
 *                              Due to the fallback to FREETEXT type, this can be ignored;
 *                              corpus integrity should be just fine.
 */
function check_corpus_xml_datatypes($corpus, $only_check = '')
{
	global $Config;
	
	$all_ok = true;
	
	$xml_to_check = ( empty($only_check) ? get_all_xml_info($corpus) : array(get_xml_info($corpus, $only_check)) );
	
	foreach($xml_to_check as $x)
	{
		$ok = true;
		$badval = '';
		switch($x->datatype)
		{
		case METADATA_TYPE_NONE:
		case METADATA_TYPE_FREETEXT:
		case METADATA_TYPE_IDLINK:
			/* no check needed */
			// TODO is this actually true for _IDLINK? Shol.d be be checking that all idlimnk columns are handles?
			break;

		case METADATA_TYPE_DATE:
			if ( ! xml_index_is_valid_date($corpus, $x->handle))
				$ok = false;
			break;

		case METADATA_TYPE_CLASSIFICATION:
			if ( ! xml_index_has_handles($corpus, $x->handle, HANDLE_MAX_CATEGORY, $badval))
				$ok = false;
			else
				/* extra step - load categories into the database. */
				setup_xml_classification_categories($corpus, $x->handle);
			break;

		case METADATA_TYPE_UNIQUE_ID:
			if ( ! xml_index_has_handles($corpus, $x->handle, HANDLE_MAX_ITEM_ID, $badval))
				$ok = false;
			if ( ! xml_index_is_unique($corpus, $x->handle))
				$ok = false;
			break;

		default:
			/* not reached */
			exiterror("A bad XML datatype has been inserted into the database, somehow! Please report this as a bug.");
		}
		if (!$ok)
		{
			$all_ok = false;
			change_xml_datatype($corpus, $x->handle, METADATA_TYPE_FREETEXT);
			list($notes) = mysqli_fetch_row(do_sql_query("select indexing_notes from corpus_info where corpus = '$corpus'"));
			$date = @date(CQPWEB_UI_DATE_FORMAT);
			$notes = "$notes\n\n$date: Post-indexing problem: contents of s-attribute {$x->handle} was not compatible with datatype ``"
				. $Config->metadata_type_descriptions[$x->datatype]
				. (empty($badval) ? '' : "'' - first bad value found was ``$badval")
				. "''. This s-attribute's datatype has been converted to datatype ``Free Text''."
				;
			$notes = escape_sql($notes);
			do_sql_query("update corpus_info set indexing_notes = '$notes' where corpus = '$corpus'");
			flush_corpus_info_caches();
		}
	}
	
	return $all_ok;
}




/**
 * Enters lines into xml_metadata_values for each of the categories in the classification.
 */
function setup_xml_classification_categories($corpus, $handle)
{
	$catlist = array();

	$source = open_xml_attribute_stream($corpus, $handle);

	while (false !== ($line = fgets($source)))
	{
		list($begin, $end, $cat) = explode("\t", trim($line, "\r\n"));
		
		$n_tokens = (int)$end - (int)$begin + 1;
		
		if (isset($catlist[$cat]))
		{
			/* known cat */
			$catlist[$cat]->words += $n_tokens;
			$catlist[$cat]->segments++;
		}
		else
		{
			/* unknown cat */
			$catlist[$cat] = (object) array('words'=>$n_tokens,'segments'=>1);
		} 
	}

	pclose($source);
	
	/* ensure no duplicates */
	do_sql_query("delete from xml_metadata_values where corpus = '$corpus' and att_handle = '$handle'");

	foreach ($catlist as $c=>$num)
		do_sql_query("insert into xml_metadata_values 
								(corpus, att_handle, handle, description, category_num_words, category_num_segments)
							values 
								('$corpus', '$handle', '$c', '$c', {$num->words}, {$num->segments})");
}


/**
 * Checks the CWB index of an s-attribute.
 * 
 * Returns true if the values are all handles (c-words up to 64 characters in legnth). 
 * 
 * If the attribute lacks values, or any values are not handles, returns false.
 * 
 * The third parameter is the length of handle that we want to test; by default, if this
 * parameter occurs, it looks for up to 64 chars, but if a longer handle is permissible
 * (e.g. category handles or ID codes) a different value can be passed in. But no 
 * integer smaller than 1 or greater than 255 will be accepted.
 * 
 * The fourth parameter, if supplied, is set to the first bad value found in the index.
 * 
 */
function xml_index_has_handles($corpus, $att_handle, $maxbytes = 64, &$bad_value = NULL)
{
	$answer = true;

	$maxbytes = min(255, max(1, (int)$maxbytes));
	$test = '|^\w{1,' . $maxbytes . '}$|';
	
	$source = open_xml_attribute_stream($corpus, $att_handle);
	
	while (false !== ($line = fgets($source)))
	{
		list(,,$val) = explode("\t", trim($line, "\r\n"));
		if (! preg_match($test, $val))
		{
			$answer = false;
			$bad_value = $val;
			break;
		}
	}
	
	pclose($source);
	return $answer;
}


/**
 * Checks the CWB index of an s-attribute.
 * 
 * Returns true if the values are unique. 
 * 
 * If the attribute lacks values, or any value is an empty string, or any values are repeated, returns false.
 */
function xml_index_is_unique($corpus, $att_handle)
{
	$answer = true;
	
	$seen = array();
	
	$source = open_xml_attribute_stream($corpus, $att_handle);

	while (false !== ($line = fgets($source)))
	{
		list(,,$val) = explode("\t", trim($line, "\r\n"));
		if (empty($val) || isset($seen[$val]))
		{
			$answer = false;
			break;
		}
		$seen[$val] = 1;
	}
	
	pclose($source);
	return $answer;
}


/**
 * Checks the CWB index of an s-attribute.
 * 
 * Returns true if the values are all valid DATE serialisations. 
 * 
 * If any value is not a string interpretable as a DATE, returns false.
 */
function xml_index_is_valid_date($corpus, $att_handle)
{
// TODO add a bad value out parameter to this function as with the check-hande function.
	$answer = true;

	$source = open_xml_attribute_stream($corpus, $att_handle);

	while (false !== ($line = fgets($source)))
	{
		list(,,$val) = explode("\t", trim($line, "\r\n"));
		if (empty($val) || ! preg_match(VagueDate::VALIDITY_REGEX, $val) )
		{
			$answer = false;
			break;
		}
	}

	pclose($source);
	return $answer;
}

/**
 * Gets a readable stream resource to cwb-s-decode for the underlying s-attribute of the specified XML.
 * 
 * Exits with an error if opening of the stream fails.
 * 
 * The resource returned can be closed with pclose().
 * 
 * @param string $corpus       The corpus of the attribute to open.
 * @param string $att_handle   Handle of the desired s-attribute.
 * @param bool   $with_values  Defaults true. When true, cwb-s-decode is opened in "show values" mode (no -v).
 *                             When false, the -v flag is set, and the output only includes the cpos values.
 * @return resource            Readable pipe from the output of cwb-s-decode. 
 */ 
function open_xml_attribute_stream($corpus, $att_handle, $with_values = true)
{
	global $Corpus;
	
	/* avoid a DB query if the requested corpus is global one. */
	if ($Corpus->specified && $Corpus->name == $corpus)
		$c_to_request = $Corpus->cqp_name;
	else
	{
		if (!($c = get_corpus_info($corpus)))
			exiterror("Cannot open xml attribute stream: corpus does not exist.");
		$c_to_request = $c->cqp_name;
	}
	
	if (!(get_xml_info($corpus, $att_handle)))
		exiterror("Cannot open xml attribute stream: specified s-attribute does not exist.");

	/* the above also effectively validates the arguments  */

	global $Config;
	
	$v_flag = ($with_values ? '' : '-v');
	
	$cmd = "{$Config->path_to_cwb}cwb-s-decode $v_flag -r \"{$Config->dir->registry}\" $c_to_request -S $att_handle";
	
	if (false === ($pipe = popen($cmd, "r")))
		exiterror("Cannot open xml attribute stream: process open failed for ``$cmd'' .");

	return $pipe;
}



/** 
 * Function used in uninstalling a corpus. 
 * 
 * Deletes all metadata relating to all XML elements for a particular corpus.
 */
function delete_xml_metadata_for($corpus)
{
	$corpus = escape_sql($corpus);
	
	do_sql_query("delete from xml_metadata        where corpus = '$corpus'");
	do_sql_query("delete from xml_metadata_values where corpus = '$corpus'");
}





/**
 * Adds a new XML s-attribute (either a new element, that is a new set of ranges; or 
 * annotated s-attribute linked to an unannotated "family", whose ranges it replicates).
 * 
 * @param string  $corpus       Corpus to add the new s-attribute to
 * @param string  $handle       Handle for the s-attribute (if this is being added to a family:  it 
 *                              should *include* the leading XML-family-handle).
 * @param string  $att_family   Handle for the existing s-attribute representing the XML element we are yoking this to;
 *                              if we are adding a new family, then this should be the same as $handle. 
 * @param string  $description  Description for the attribute.
 * @param int     $datatype     Integer constant for the datatype of the attribute that is to be added. 
 * @param string  $input_path   Path to the input file with the new data.
 */
function add_new_xml_to_corpus($corpus, $handle, $att_family, $description, $datatype, $input_path)
{
	global $Config;
	
	if (!($c_info = get_corpus_info($corpus)))
		exiterror("Non-existent corpus specified!");
	
	$handle = cqpweb_handle_enforce($handle, HANDLE_MAX_XML);
	
	if (xml_exists($handle, $corpus))
		exiterror("S-attribute handle ''$handle'' already exists in this corpus!");

	if ($att_family != $handle)
	{
		if (!xml_exists($att_family, $corpus))
			exiterror("S-attribute handle ''$att_family'' does not exist in this corpus!");
		$adding_new_family = false;
	}
	else
		$adding_new_family = true;
	
	$description = escape_sql($description);
	
	$datatype = (int)$datatype;
	
	if (false === ($source = fopen($input_path, 'r')))
		exiterror("could not open specified input file");
	
	/* get and check the directory where the new index data will go */
	$target_dir = get_corpus_index_directory($corpus);


	if (!$adding_new_family)
		$att_stream = open_xml_attribute_stream($corpus, $att_family, false);

	/* get everything ready for incremental write to cwb-s-encode */
	$LETTERCODE = ($adding_new_family ? 'S' : 'V');
	$cmd = "{$Config->path_to_cwb}cwb-s-encode -d \"$target_dir\" -$LETTERCODE $handle ";
	$process = popen($cmd, 'w');
	/* let's not rely on PHP_EOL */
	$eol = ($Config->cqpweb_running_on_windows ? "\r\n" : "\n");
	
	switch($datatype)
	{
	case METADATA_TYPE_CLASSIFICATION:
	case METADATA_TYPE_UNIQUE_ID:
	case METADATA_TYPE_IDLINK:
		$annot_criterion = '\t(\w+)';
		break;
	default:
		/* all other datatypes */
		$annot_criterion = '\t(.*)';
		break;
		/* note: this will need adjusting for dataypes such as DATE. */
	}
	if ($adding_new_family)
		$annot_criterion = '';
	
	$max = $c_info->size_tokens;
	
	$line_regex = "/^(\d+)\t(\d+)$annot_criterion$eol$/";
	$att_stream_regex = "/^(\d+)\t(\d+)$eol$/";

	$line_n = 0;
	$abort_required = false;

	while (false !== ($line = fgets($source)))
	{
		++$line_n;
		/* these checks always apply */
		if (1 > preg_match ($line_regex, $line, $m) || (int)$m[2] <= (int)$m[1] || $max < (int)$m[2])
		{
			$abort_required = true;
			break;
		}
		/* this check only applies if we are adding data to a family */
		if (!$adding_new_family)
		{
			/* load pair from att stream : do they match? */
			preg_match($att_stream_regex, fgets($att_stream), $n);

			if ($m[1] != $n[1] || $m[2] != $n[2])
			{
				$bad_cpos_match_abort_required = true;
				break;
			}
		}
		fputs($process, $line);
	}
	
	fclose($source);
	
	pclose($process);
	
	if (!$adding_new_family)
		pclose($att_stream);
	
	if ($abort_required || $bad_cpos_match_abort_required)
	{
		/* we have only created a rng file... */
		if (is_file("$target_dir/$handle.rng"))
			unlink("$target_dir/$handle.rng");
		
		exiterror(
			( $abort_required  
				? "Bad line in s-attribute input file (at line # $line_n), encoding aborted."
				: "Cpos at line # $line_n do not match those in existing attribute ''$att_family''!"
			));
	}
	
	/* add registry line... */
	$regdata = read_cwb_registry_file($corpus);
	if ($adding_new_family)
	{
		$new_regline = "#added by CQPweb add-XML tool{$eol}STRUCTURE $handle$eol$eol";
		if (false !== (strpos($regdata, $encode_sig='# Yours sincerely, the Encode tool.')))
			$regdata = str_replace($encode_sig, "$new_regline$encode_sig", $regdata);
		else 
			$regdata .= $new_regline;
	}
	else
	{
		$new_regline = "STRUCTURE $handle ";
		$n_spaces = 21 - strlen($handle);
		for ($i = 0 ; $i < $n_spaces; $i++)
			$new_regline .= ' ';
		$new_regline .= "# [annotations]$eol";
		$regdata = str_replace("STRUCTURE $att_family$eol", "STRUCTURE $att_family$eol$new_regline", $regdata);
	}
	write_cwb_registry_file($corpus, $regdata);
	
	/* we can now log this with the system. */
	$sql = "insert into xml_metadata 
 		(corpus, handle, att_family, description, datatype) 
 		VALUES 
 		('$corpus','$handle','$att_family','$description',".METADATA_TYPE_NONE.")";

	do_sql_query($sql);
}





/* -------------------------------------------- *
 * Functions relating to IDLINK-type attributes *
 * -------------------------------------------- */

//TODO s-att handles are currently able to be up to 64 chars. But the idlink table names use them as part of the table names.
// This implies that, like p-atts & corpus handles, they should be limited to 20 chars
// ..................................worry about this later.

function xml_idlink_table_exists($corpus, $att_handle)
{
	$t = get_idlink_table_name($corpus, $att_handle);
	$result = do_sql_query("show table status like '$t'");
	return (0 < mysqli_num_rows($result));
}

/**
 * Gets a flat array of all the IDs that exist for the given 
 * IDlink XML attribute.
 *  
 * @param  string  $corpus
 * @param  string  $att_handle
 * @return array
 */
function list_idlink_ids($corpus, $att_handle)
{
	$t = get_idlink_table_name($corpus, $att_handle);
	return list_sql_values("select `__ID` from `$t`");
}


function get_idlink_table_name($corpus, $att_handle)
{
	if ('--text' != substr($att_handle, 0, 6))
	{
		if (!cqpweb_handle_check($corpus))
			exiterror("Invalid corpus handle at database level!!");
		if (!cqpweb_handle_check($att_handle))
			exiterror("Invalid s-attribute handle at database level!!");
		return "idlink_xml_{$corpus}_{$att_handle}";
	}
	else
		; //TODO  this is needed in order for us to have idlink columns on texts.
}


/** returns array (handle=> description) of the fields of an idlink table */
function list_idlink_fields($corpus, $att_handle)
{
	$corpus = escape_sql($corpus);
	$att_handle = escape_sql($att_handle);
	
	$result = do_sql_query("select handle, description from idlink_fields where corpus='$corpus' and att_handle = '$att_handle'");
	
	$list = array();
	
	while ($o = mysqli_fetch_object($result))
		$list[$o->handle] = $o->description;
	
	return $list;
}

/** returns array of database objects for fields of an idlink table (handle is key) */
function get_all_idlink_field_info($corpus, $att_handle)
{
	$corpus = escape_sql($corpus);
	$att_handle = escape_sql($att_handle);
	
	$result = do_sql_query("select * from idlink_fields where corpus='$corpus' and att_handle = '$att_handle'");
	
	$list = array();
	
	while ($o = mysqli_fetch_object($result))
		$list[$o->handle] = $o;
	
	return $list;
}


/**
 * Returns an associative array of category descriptions,
 * where the keys are the handles, for the given classification.
 * 
 * If no description exists, the handle is set as the description.
 * 
 * If categories are not set up, or the $xml_handle is of a datatype other than 
 * CLASSIFICATION, the result will be an empty array.
 */
function idlink_category_listdescs($corpus, $att_handle, $field_handle)
{
	$corpus = escape_sql($corpus);
	$att_handle = escape_sql($att_handle);
	$field_handle = escape_sql($field_handle);

	$result = do_sql_query("SELECT handle, description FROM idlink_values 
									WHERE field_handle = '$field_handle' and att_handle = '$att_handle' AND corpus = '$corpus'");

	$return_me = array();
	
	while ($r = mysqli_fetch_row($result)) 
		$return_me[$r[0]] = (empty($r[1]) ? $r[0] : $r[1]);
	
	return $return_me;
}


/**
 * Returns false if there are no bad ids in the field specified.
 * 
 * If there are bad ids, a string containing those ids (space-separated) is returned.
 */
function check_idlink_get_bad_ids($corpus, $att, $field)
{
	$corpus = escape_sql($corpus);
	$att    = escape_sql($att);
	$field  = escape_sql($field);
	$table  = get_idlink_table_name($corpus, $att);
	
	$result = do_sql_query("select distinct `$field` from `$table` where `$field` REGEXP '[^A-Za-z0-9_]'");
	if (0 == mysqli_num_rows($result))
		return false;

	$bad_ids = '';
	while ($r = mysqli_fetch_row($result))
		$bad_ids .= " '{$r[0]}'";
	
	return $bad_ids;
}



/**
 * Utility function for the create idlink functions.
 * 
 * Returns nothing, but deletes the idlink table and aborts the script 
 * if there are bad ids.
 * 
 * (NB - doesn't do any other cleanup e.g. temporary files).
 * 
 * This function should be called before any other updates are made to the database.
 */
function check_idlink_ids($corpus, $att)
{
	if (false === ($bad_ids = check_idlink_get_bad_ids($corpus, $att, '__ID')))
		return;
	
	$corpus = escape_sql($corpus);
	$att = escape_sql($att);
	$table = get_idlink_table_name($corpus, $att);
	
	/* database revert to zero text metadata prior to abort */
	do_sql_query("drop table if exists `$table`");
	do_sql_query("delete from idlink_fields where corpus = '$corpus'");
	
	$msg = "The data source you specified for the IDLINK metadata contains badly-formatted item ID codes, as follows: <strong>"
		. $bad_ids
		. "</strong> (IDs can only contain unaccented letters, numbers, and underscore).";
	
	exiterror($msg);
}


/**
 * Utility function for the create idlink functions.
 * 
 * Returns nothing, but deletes the idlink table and aborts the script 
 * if there are any non-word values in the specified field.
 * 
 * Use for categorisation columns.
 * 
 * (NB - doesn't do any other cleanup e.g. temporary files).
 * 
 * This function should be called before any other updates are made to the database.
 */
function check_idlink_field_words($corpus, $att, $field)
{
	if (false === ($bad_ids = check_idlink_get_bad_ids($corpus, $att, $field)))
		return;
	
	$corpus = escape_sql($corpus);
	$att = escape_sql($att);
	$table = get_idlink_table_name($corpus, $att);
	
	/* database revert to zero text metadata prior to abort */
	do_sql_query("drop table if exists `$table`");
	do_sql_query("delete from idlink_fields where corpus = '$corpus'");
	
	$msg = "The data source you specified for the IDLINK metadata contains badly-formatted "
		. " category handles in field [$field], as follows:  <strong>"
		. $bad_ids
		. " </strong> ... (category handles can only contain unaccented letters, numbers, and underscore).";
	
	exiterror($msg);
}



/**
 * Install a idlink-metadata table.
 * 
 * @param string $corpus  The corpus affected. 
 * @param string $att     The s-attribute handle (e.g. "u_who") or "--text" id this is for text-idlink.
 * @param string $file    Full path to the input file to use.
 * @param array  $fields  Array of field descriptors (table columns). A field descriptor is an associative array
 *                        of three elements: handle, description, datatype.
 */
function create_idlink_table_from_file($corpus, $att, $file, $fields)
{
	global $Config;
	
	if (! cqpweb_handle_check($corpus))
		exiterror("Invalid corpus argument to create idlink metadata function!");
	
	if (!in_array($corpus, list_corpora()))
		exiterror("Corpus $corpus does not seem to be installed! Idlink metadata setup aborts.");
	
	$tablename = get_idlink_table_name($corpus, $att);
	
	if ('--text' != substr($att, 0, 6))
	{
		$xml = get_xml_info($corpus, $att);
		if (empty($xml))
			exiterror("XML attribute specified does not seem to exist.");
		if (METADATA_TYPE_IDLINK != $xml->datatype)
			exiterror("Cannot create an idlink table for ``$att'', it is not an IDLINK-type attribute!");
	}
	
	if (!is_file($file))
		exiterror("The metadata file you specified does not appear to exist!\nMetadata setup aborts.");

	/* create a temporary input file with the additional necessary zero fields (for counts) */
	$input_file = "{$Config->dir->cache}/___idlink_temp_{$Config->instance_name}";
	
	$source = fopen($file, 'r');
	$dest = fopen($input_file, 'w');
	while (false !== ($line = fgets($source)))
		fputs($dest, rtrim($line, "\r\n") . "\t0\t0".PHP_EOL);
	fclose($source);
	fclose($dest);


	/* get ready to process field declarations... */
	
	$classification_scan_statements = array();
	$inserts_for_idlink_fields = array();

	$create_statement = "create table `$tablename`(
		`__ID` varchar(255) NOT NULL";

	
	
	foreach ($fields as $field)
	{
		$field['handle'] = cqpweb_handle_enforce($field['handle']);
		$field['description'] = escape_sql($field['description']);
		/* check for valid datatype */
		if(! metadata_valid_datatype($field['datatype'] = (int)$field['datatype']))
			exiterror("Invalid datatype specified for field ``{$field['handle']}''.");
		
		/* the record in the idlink-fields table has a constant format.... */
		$inserts_for_idlink_fields[] = 
			"insert into idlink_fields 
			(corpus, att_handle, handle, description, datatype)
			values 
			('$corpus', '$att', '{$field['handle']}', '{$field['description']}', {$field['datatype']} )
			";

		/* ... but the create statement depends on the datatype */
		$create_statement .= ",\n\t\t`{$field['handle']}` {$Config->metadata_mysql_type_map[$field['datatype']]}";
		
		/* ... as do any additional actions */ 
		switch ($field['datatype'])
		{
		case METADATA_TYPE_CLASSIFICATION:
			/* we need to scan this field for values to add to the values table! */
			$classification_scan_statements[$field['handle']]
				= "select `{$field['handle']}` as handle, count(*) as n_items, sum(n_tokens) as n_tokens from `$tablename` group by handle";
			break;
			
		case METADATA_TYPE_FREETEXT:
			/* no extra actions */
			break;
		
		/* TODO extra actions for other datatypes here. */
	
		/* no default needed, because we have already checked for a valid datatype above. */
		}
	}

	/* add the standard fields; begin list of indexes. */
	$create_statement .= ",
		`n_items`  INTEGER UNSIGNED NOT NULL default '0',
		`n_tokens` BIGINT UNSIGNED NOT NULL default '0',
		`__DATA`     longblob,
		primary key (`__ID`)
		";
	
	/* we also need to add an index for each classifcation-type field;
	 * we can get these from the keys of the scan-statements array */
	foreach (array_keys($classification_scan_statements) as $cur)
		$create_statement .= ", index(`$cur`) ";
	
	/* finish off the rest of the create statement */
	$create_statement .= "
		) CHARSET=utf8";

	/* now, execute everything! */
	foreach($inserts_for_idlink_fields as $ins)
		do_sql_query($ins);

	do_sql_query("drop table if exists `$tablename`");
	do_sql_query($create_statement);
	
	do_sql_infile_query($tablename, $input_file);
	unlink($input_file);

	/* check resulting table for invalid text ids and invalid category handles */
	check_idlink_ids($corpus, $att);
	/* again, use the keys of the classifications array to work out which we need to check */
	foreach (array_keys($classification_scan_statements) as $cur)
		check_idlink_field_words($corpus, $att, $cur);

	
	/* update ID totals (and cached cpos data) in idlink table */
	update_idlink_item_size_data($corpus, $att);

	
	/* now we can scan for & insert the classification columns */
	update_idlink_category_sizes($corpus, $att);

	/* TODO optimisation: cpos data could be added to idlink category v alues as well. But let's not do that yet. */

// 	foreach($classification_scan_statements as $field_handle => $statement)
// 	{
// 		/* select `{$field['handle']}` as handle, count(*) as n_items, sum(n_tokens) as n_tokens from `$tablename` group by handle */		
// 		$result = do_sql_query($statement);

// 		while (($o = mysqli_fetch_object($result)) !== false)
// 			do_sql_query("insert into idlink_values 
// 					(corpus,    att_handle, field_handle,    handle,         category_n_items, category_n_tokens)
// 					values
// 					('$corpus', '$att',     '$field_handle', '{$o->handle}', {$o->n_items},    {$o->n_tokens})"
// 				);
// 	}
	
	/* that should now be everything */
}



/**
 * Delete an idlink table and associated info in the idlink_* tables.
 * 
 * Note that this DOESN'T handle the clearing out of the restriction-cache.
 * Those functions must be called separately...
 *  
 * @param string $corpus  Corpus to which the idlinked attribute belongs.
 * @param string $att     S-attribute handle. Must be of type IDLINK.
 */
function delete_xml_idlink($corpus, $att)
{
	$corpus = escape_sql($corpus);
	$att    = escape_sql($att);

	$table = get_idlink_table_name($corpus, $att);
	do_sql_query("drop table if exists `$table`");
	
	do_sql_query("delete from idlink_fields where corpus = '$corpus' and att_handle = '$att'");
	do_sql_query("delete from idlink_values where corpus = '$corpus' and att_handle = '$att'");
}


/**
 * Add a new field to the metadata for an idlink-type XML attribute.
 * 
 * @param string $corpus       The corpus we are working in.
 * @param string $att_handle   Handle of the IDLINK-type XML attribute to work on.
 * @param string $handle       Handle of the column to add.
 * @param string $description  Description of the column to add.
 * @param int    $datatype     Datatype constant for the new column.
 * @param string $input_path   Path to the input file: se notes on add_field_to_metadata_table().
 */
function add_idlink_field($corpus, $att_handle, $handle, $description, $datatype, $input_path)
{
	/*
	 * NB, this cannot be the same as add_text_metadata_field(), because we assume that text 
	 * category frequencies cannot immediately be setup (because text frequencies aren't known yet);
	 * whereas we assume that idlink frequencies are already known, so we create the value entries,
	 * and add the numbers, in one fell swoop.
	 */
	$datatype = (metadata_valid_datatype($datatype) ? (int) $datatype : METADATA_TYPE_FREETEXT);
	
	if (array_key_exists($handle, list_idlink_fields($corpus, $att_handle)))
		exiterror("Cannot add metadata field $handle because a field by that name already exists.");
		
	$table = get_idlink_table_name($corpus, $att_handle);
	add_field_to_metadata_table($table, $handle, $datatype, $input_path);
	
	/* update the idlink_fields list */
	$corpus = cqpweb_handle_enforce($corpus);
	$att_handle = cqpweb_handle_enforce($att_handle);
	$handle = cqpweb_handle_enforce($handle);
	$description = escape_sql($description);

	do_sql_query("insert into idlink_fields 
		(corpus, att_handle, handle, description, datatype) 
			VALUES
		('$corpus','$att_handle', '$handle','$description', $datatype)");

	/* and, if it is a classification, scan for values and category sizes */
	if (METADATA_TYPE_CLASSIFICATION == $datatype)
		update_idlink_category_sizes($corpus, $att_handle, $handle);
// 	{
// 		$result = do_sql_query("select `$handle` as c_handle, count(*) as n_items, sum(n_tokens) as n_tokens from `$table` group by c_handle");

// 		while (false !== ($o = mysqli_fetch_object($result)))
// 			do_sql_query("insert into idlink_values 
// 					(corpus,    att_handle,    field_handle, handle,         category_n_items, category_n_tokens)
// 					values
// 					('$corpus', '$att_handle', '$handle',    '{$o->c_handle}', {$o->n_items},    {$o->n_tokens})"
// 				);
// 	}
}

/**
 * Updates the token/item counts (and cached cpos collection) for each distinct ID in an idlink table. 
 * 
 * @param string $corpus        Corpus to work with.
 * @param string $att_handle    Handle for the XML attribute that contains the IDlink.
 */
function update_idlink_item_size_data($corpus, $att_handle)
{
	$corpus = escape_sql($corpus);
	$att_handle = escape_sql($att_handle);
	
	/* maps of __ID => totals. */
	$item_totals_for_id  = array();
	$token_totals_for_id = array();
	$cpos_collection_for_id = array();
	
	$tablename = get_idlink_table_name($corpus, $att_handle); 
	
	$source = open_xml_attribute_stream($corpus, $att_handle, true);
	while (false !== ($line = fgets($source)))
	{
		list($begin, $end, $val) = explode("\t", rtrim($line, "\r\n"));
		if (!isset($item_totals_for_id[$val]))
		{
			$item_totals_for_id[$val] = 0;
			$token_totals_for_id[$val] = 0;
			$cpos_collection_for_id[$val] = [];
		}
		$item_totals_for_id[$val]++;
		$token_totals_for_id[$val] += (int)$end - (int)$begin + 1;
		$cpos_collection_for_id[$val][] = [ $begin, $end ];
	}
	pclose($source);
	
	/* update idlink table to contain the counts */
	foreach(array_keys($item_totals_for_id) as $which_id)
	{
		$blob =  escape_sql(translate_restriction_cpos_to_db($cpos_collection_for_id[$which_id]));
		do_sql_query("update `$tablename`  set 
							`n_items` = {$item_totals_for_id[$which_id]}, 
							`n_tokens` = {$token_totals_for_id[$which_id]},
							`__DATA` = '$blob'
						where `__ID` = '$which_id'");
		unset($cpos_collection_for_id[$which_id]);
		/* i.e. reclaim RAM as we go. */
	}
}



/**
 * Updates the idlink values cache to hold the correct values for the present state of
 * the idlink metadata table in terms of size of each category of a classification-type field.
 * 
 * @param string $corpus       Corpus to work with.
 * @param string $att_handle   Handle for the XML attribute that contains the IDlink.
 * @param string $handle       Field of metadata table for which to calculate values. 
 *                             If this argument is not supplied, all classification-type 
 *                             fields are scanned.
 */
function update_idlink_category_sizes($corpus, $att_handle, $handle = NULL)
{
	/* TODO optimisation: cpos data blob could be added to idlink category values,
	 * just as it is to the actual idlink table. But let's not do that yet. 
	 * (similarly, the cpos data for classification-type xml data cxould be cached...)   */
	
	$corpus = cqpweb_handle_enforce($corpus);
	$att_handle = cqpweb_handle_enforce($att_handle);
	
	$table = get_idlink_table_name($corpus, $att_handle);
	
	if (is_null($handle))
	{
		$fields_to_scan = array();
		foreach(get_all_idlink_field_info($corpus, $att_handle) as $obj)
			if (METADATA_TYPE_CLASSIFICATION == $obj->datatype)
				$fields_to_scan[] = $obj->handle; 
	}
	else
	{
		$fields_to_scan = array(cqpweb_handle_enforce($handle));
		unset($handle);
	}
	
	foreach($fields_to_scan as $handle)
	{
		/* in case any already exist */
		do_sql_query("delete from idlink_values where corpus='$corpus' and att_handle='$att_handle' and field_handle='$handle'");
	
		$result = do_sql_query("select `$handle` as c_handle, count(*) as n_items, sum(n_tokens) as n_tokens from `$table` group by c_handle");

		while ($o = mysqli_fetch_object($result))
			do_sql_query("insert into idlink_values 
					(corpus,    att_handle,    field_handle, handle,           category_n_items, category_n_tokens)
					values
					('$corpus', '$att_handle', '$handle',    '{$o->c_handle}', {$o->n_items},    {$o->n_tokens})"
				);
	}
	
}





/*
 * ==================================
 * XML VISUALISATION FUNCTION LIBRARY
 * ==================================
 */
/**
 * 
 * @param  mixed $template_status  If set to true or false, the funciton returnsa only template-flagged
 *                                 or non-template-flagged visualisations (respectively). If omitted or set
 *                                 to NULL, all visualsiations of either tyoe are returned.
 * @return array 
 */
function get_global_xml_visualisations($template_status = NULL)
{
	$list = array();
	
	$sql = "select * from xml_visualisations";
	
	if (!is_null($template_status))
		$sql .= ' where is_template = ' . ($template_status ? '1' : '0');
	
	$sql .= " order by corpus";
	
	$result = do_sql_query($sql);

	while ($o = mysqli_fetch_object($result))
		$list[] = $o;
	
	return $list; 
}

/**
 * Get a collection of database objects for the specified corpus' XML Viz table entries. 
 * 
 * Limiting boolean parameters: For a complete set, pass TRUE TRUE TRUE. 
 * For use in concordance TRUE FALSE.
 * For use in context FALSE TRUE. 
 * 
 * @param string $corpus                  The corpus whose visualsiations we want.
 * @param bool   $get_used_in_conc        If true, will include visualsiations activated for concordance.
 * @param bool   $get_used_in_context     If true, will include visualisations activated for extended context.
 * @param bool   $get_currently_unused    If true, will include deactivated visualisations.  
 * @return array                          Flat array of database objects. Keys represent retrieval sequence only.
 */
function get_all_xml_visualisations($corpus, $get_used_in_conc, $get_used_in_context, $get_used_in_download, $get_currently_unused = false)
{
	$list = array();
	
	$corpus = escape_sql($corpus);
	
	$sql = "select * from xml_visualisations where corpus = '$corpus'";
	
	if ( $get_used_in_conc && $get_used_in_context && $get_used_in_download && $get_currently_unused )
		; /* add no further conditions, we just want the lot. */
	else
	{
		$conditions = array();

		if ($get_used_in_conc)
			$conditions[] = ' (in_concordance = 1) ';
		if ($get_used_in_context)
			$conditions[] = ' (in_context = 1) ';
		if ($get_used_in_download)
			$conditions[] = ' (in_download = 1) ';
		if ($get_currently_unused)
			$conditions[] = ' (in_concordance = 0 and in_context = 0 and in_download = 0) ';
		
		if (0 < count($conditions))
			$sql .= ' and (' . implode(' OR ', $conditions) . ' ) '; 
	}
	$result = do_sql_query($sql);
	
	while ($o = mysqli_fetch_object($result))
		$list[] = $o;
	
	return $list; 
}

/** 
 * Prepares an index of XML Viz info for use by the render function.
 * 
 * We need the index because otherwise we have to cycle through the whole viz list
 * for EVERY SINGLE bit of XML in the output that needs rendering.
 * 
 * @see apply_xml_visualisations()
 * @param  array $list             Input: flat array with meaningless keys.
 * @return array                   Hash of hashes. Should be treated as opaque.
 */
function index_xml_visualisation_list($list)
{
	$index = array();
	
	foreach($list as $viz)
	{
		list($tag, $other) = explode('~', $viz->element);
		
		if (! isset($index[$tag]))
			$index[$tag] = array('start'=>array('?'=> array('check' => function () {return true;}, 'html' => '')), 'end'=>'');
		/* we create "empty" visualisations for both, before adding what we have found here */
		
		if ($other == 'end')
			$index[$tag]['end'] = $viz->html;
		else
		{
			/* no condiiton: the key is a single letter (will not clash cos invalid regex) */
			if (empty($viz->conditional_on))
				$index[$tag]['start']['?']['html'] = $viz->html;
			else
			{
				$r = $viz->conditional_on;
				$f = function ($s) use ($r) { return (bool)preg_match("/$r/", $s); };
				/* NOTE FOR THE FUTURE: the line above can be made to depend on the datatype of the s-attribute, 
				 * so that we can have things in the closure other than a regex check for other datatypes. */
				$index[$tag]['start'][$r] = array('check' => $f, 'html' => $viz->html);
			}
		}
	}
	/* we now have our list, but we want to sort it. */
	ksort($index);
	foreach(array_keys($index) as $k)
		uksort($index[$k]['start'], 
				function ($a, $b) 
				{
					if ('?'== $a) return 1; 
					if ('?'== $b) return -1; 
					return strlen($b) - strlen($a);
				}
			);
	/* longest regex first; '?' is always last. */
	
	return $index;
}

/**
 * Rendering-engine function for XML visualisations. 
 * 
 * When applied to a <tag>, </tag> or <tag VAL>
 * input string, returns an HTML block ready to send to a browser
 * (or a text block if we're using this for conc-download).
 * The string is allowed to contain MULTIPLE such units,
 * and all wil be converted ultimately.
 * 
 * @param  string $input            Input string from which the HTML is to be generated.
 * @param  array  $visualisations   Array of database objects from xml_visualisations table; 
 *                                  should have been processed into a "hash of hashes" index.
 * @return string                   HTML/plain text output.
 */
function apply_xml_visualisations($input, $visualisations)
{
	if (empty($input))
		return '';
	
	preg_match_all('~<(/|)(\w+)( (?:.*?)|)>~', $input, $outer_m, PREG_SET_ORDER);
	
	$render = '';

	foreach ($outer_m as $m)
	{
		/* this check should not be needed; the entry in $visualisations should always exist.
		 * But a dodgily-encoded corpus might include tokens in <angle brackets> which will raise 
		 * "missing index" warning messages. So we do this check, and apply the fallback if need be. */
		
		if (!isset($m[2], $visualisations[$m[2]])) 
//			$render .= ' ' . escape_html($m[0]) .  ' ';
			$render .= escape_html($m[0]) ;
		else
		{
			$v = $visualisations[$m[2]];
			
			if('/' == $m[1])
				/* end tag : easy peasy */
				$render .= $v['end'];
			else if (empty($m[3]))
				/* unvalued start tag : does not fulfil ANY condition */
				$render .= $v['start']['?']['html'];
			else
			{
				/* by elimination, start tag which has a value; we need to check to see the condition. */
				/* $m[3] always starts with a space, see above */
				$val = escape_html(substr($m[3], 1));
				/* we escape in case the value has any XML < > & in it */
				foreach ($v['start'] as $candidate)
				{
					if ($candidate['check']($val))
					{
						$render .= str_replace('$$$$', $val, $candidate['html']);
						break;
					}
				}
			}
		}
	}

	return $render;
}


/**
 * Apply the HTML whitelist to user-submitted XML visualisation code.
 * 
 * @param  string $viz  A user-submitted string of visualisation data including HTML.
 * @return string       A "safe" viz string (still needs escaping for MySQL though!)
 */
function xml_visualisation_process_whitelist($viz)
{
	/* the "preserver" characters for allowed HTML */
	$lb = "\x12";
	$rb = "\x13";
	
	/* step 1: switch allowed simple formatting codes to $lb / $rb */
	$viz = preg_replace('/<(\/|)(b|i|u|s|sub|sup|code)>/', "$lb$1$2$rb", $viz);

	/* step 2: separately switch br, which cannot be an end tag */
	$viz = str_replace('<br>', "{$lb}br{$rb}", $viz);
	
	/* step 3: deal with closing tags for the complex cases */
	$viz = preg_replace('/<\/(span|a|bdo)>/', "$lb/$1$rb", $viz);
	/* the OPENING tags of complex cases are treated separately as different rules apply to each. */
	
	/* step 4: span */
	$viz = preg_replace('/<span\s+class="([^"]+)">/', "{$lb}span class=\"$1\"$rb", $viz);
	
	/* step 5: a */
	$viz = preg_replace('/<a\s+href="(https?:\/\/[^"]+)">/', "{$lb}a target=\"_blank\" href=\"$1\"$rb", $viz);
	
	/* step 6: bdo */
	$viz = preg_replace('/<bdo\s+dir="(ltr|rtl)">/', "{$lb}bdo dir=\"$1\"$rb", $viz);
	
	/* step 7: img */
	$viz = preg_replace('/<img\s+src="([^"]+)">/', "{$lb}img src=\"$1\"$rb", $viz);
		
	/* step 8: replace all remaining angle brackets with HTML entities */
	$viz = str_replace('<', '&lt;', $viz);
	$viz = str_replace('>', '&gt;', $viz);
	
	/* step 9: finally, restore angle brackets from the mask characters */
	$viz = str_replace($lb, '<', $viz);
	$viz = str_replace($rb, '>', $viz);
	
	/* all done! */
	return $viz;
}




/**
 * Creates an entry in the visualisation list.
 * 
 * Note it is quite possible to create exact-duplicate entries in the viz table;
 * only the id will be different. This has no effect (since only the first such viz
 * processed will find anything to translate).
 * 
 * @param string $corpus          Name of the corpus this visualisation belongs to.
 * @param string $attribute       The s-attribute (XML) that the visualisation is to be applied to.
 * @param bool   $is_start_tag    True if this is a template for the start tag, false if for the end tag. 
 * @param string $conditional_on  Content of the condition to apply. Empty string for unconditional.
 * @param string $html            The desired HTML code.
 * @param bool   $in_concordance  True if the initial "use in concordance" setting is to be true; defaults to true.
 * @param bool   $in_context      True if the initial "use in context" setting is to be true; defaults to true.
 * @param bool   $in_download      True if the initial "use in download" setting is to be true; defaults to false.
 */
function xml_visualisation_create($corpus, $attribute, $is_start_tag, $conditional_on, $html, 
		$in_concordance = true, $in_context = true, $in_download = false)
{
	/* disallow conditions in end tags (because they have no attributes) */
	if (! $is_start_tag)
		$conditional_on = '';
	
	/* make safe all db inputs: use handle enforce, where possible */
	$corpus = cqpweb_handle_enforce($corpus);
	$attribute = cqpweb_handle_enforce($attribute);
	$conditional_on = escape_sql($conditional_on);
	
	$element = $attribute . ($is_start_tag ? '~start' : '~end');
	
	$html = escape_sql(xml_visualisation_process_whitelist($html));
	
	/* bools as strings for sql insert */
	$in_concordance = ($in_concordance ? '1' : '0');
	$in_context     = ($in_context     ? '1' : '0');
	$in_download    = ($in_download     ? '1' : '0');
	
	do_sql_query("insert into xml_visualisations
						(corpus,    element,    conditional_on,    in_context,  in_concordance,  in_download,  html)
							values
						('$corpus', '$element', '$conditional_on', $in_context, $in_concordance, $in_download, '$html')"
			);
}


/**
 * Updates the HTML stored for an identified visualisation.
 * 
 * @param int     $viz_id    Identifier of the visualisation to update.
 * @param string  $new_html  The new HTML code.
 */
function xml_visualisation_update_html($viz_id, $new_html)
{
	$viz_id = (int) $viz_id;
	$new_html = escape_sql(xml_visualisation_process_whitelist($new_html));
	do_sql_query("update xml_visualisations set html = '$new_html' where id = $viz_id");
}


/**
 * Deletes an XML visualisation.
 * 
 * @param int  $id                          Numeric ID of viz to delete.
 * @param bool $constrain_to_global_Corpus  If true, will only delete a viz associated with the active corpus. Defaults false.
 */
function xml_visualisation_delete($id, $constrain_to_global_Corpus = false)
{
	if ($constrain_to_global_Corpus)
	{
		global $Corpus;
		$extra = " and corpus = '{$Corpus->name}'";
	}
	else
		$extra = '';
	
	$id = (int) $id;
	
	do_sql_query("delete from xml_visualisations where id = $id $extra");
}



/**
 * Turn on/off the use of an XML visualisation in context display.
 */
function xml_visualisation_use_in_context($id, $new)
{
	$newval = ($new ? '1' : '0');
	$id = (int)$id;
	do_sql_query("update xml_visualisations set in_context = $newval where id = $id");
}

/**
 * Turn on/off the use of an XML visualisation in concordance display.
 */
function xml_visualisation_use_in_concordance($id, $new)
{
	$newval = ($new ? '1' : '0');
	$id = (int)$id;
	do_sql_query("update xml_visualisations set in_concordance = $newval where id = $id");	
}


/**
 * Turn on/off the use of an XML visualisation in query downloads.
 */
function xml_visualisation_use_in_download($id, $new)
{
	$newval = ($new ? '1' : '0');
	$id = (int)$id;
	do_sql_query("update xml_visualisations set in_download = $newval where id = $id");	
}


/**
 * Turn on/off the availability of an XML visualisation as a cross-corpus template. 
 */
function xml_visualisation_use_as_template($id, $new)
{
	$newval = ($new ? '1' : '0');
	$id = (int)$id;
	do_sql_query("update xml_visualisations set is_template = $newval where id = $id");	
}

/**
 * Creates a copy of a cross-corpus "template" XML in the specified target corpus. 
 * @param  int    $template_id     Template to copy.
 * @param  string $target_corpus   Corpus to copy it to.
 * @return int                     The ID of the new template.
 */
function xml_visualisation_import_from_template($template_id, $target_corpus)
{
	$template_id   = (int) $template_id;
	$target_corpus = cqpweb_handle_enforce($target_corpus);
	
	$hash = mysqli_fetch_assoc(do_sql_query("select * from xml_visualisations where id = $template_id"));

	unset($hash['id'], $hash['is_template']);
	$hash['corpus'] = $target_corpus;
	$fields = implode(', ', array_keys($hash));
	/* note the following line relies on MySQL's string-to-int type juggling for convenience... */
	$values = '\'' . implode('\', \'', $hash) . '\''; 
	
	do_sql_query("insert into xml_visualisations ($fields) VALUES ($values)");

	return get_sql_insert_id();
}

/** 
 * Gets an array of s-attributes that need to be shown in the CQP concordance line
 * in order for visualisation to work. 
 * 
 * @param  string $where  Either "conc" or "context" or "download" 
 *                        (to get the right set of XML attributes for the active viz).
 * @return array          Array of s-attributes: flat array of strings containing the s-attributes.
 */
function xml_visualisation_s_atts_to_show($where)
{
	global $Corpus;

	$atts = array();
	
	switch($where)
	{
	case 'conc':     $field = 'in_concordance'; break;
	case 'context':  $field = 'in_context';     break;
	case 'download': $field = 'in_download';    break;
	}
	
	$result = do_sql_query("select element from xml_visualisations where corpus='{$Corpus->name}' and $field = 1");

	while ($r = mysqli_fetch_object($result))
	{
		list($r->element) = explode('~', $r->element); 
		if ( ! in_array($r->element, $atts) )
			$atts[] = $r->element;
	}
	
	return $atts;
}

