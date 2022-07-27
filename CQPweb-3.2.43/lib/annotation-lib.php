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
 * Functions acting on annotations.
 * 
 * 
 * An annotation is CQPweb's wrap-around of the CWB p-attribute. It consists of the p-attribute
 * (obviously) but also other info about it, such as its tagset, documentation links, etc.
 * 
 * Alignments (a-attributes) are included here because there aren't enough related functions
 * to justify a file of their own.
 */






/**
 * Returns an associative array: the keys are annotation handles, 
 * the values are annotation descs.
 * 
 * If the corpus has no annotation, an empty array is returned. 
 * 
 * NOTE: this is NOT a list of p-attributes. In particular, there
 * is no member with the key "word". If you want that, add 'word'=>'Word'
 * manually to the returned array.
 * The returned dscriptions may be empty values. They have not been
 * HTML-escaped.
 */
function list_corpus_annotations($corpus)
{
	$corpus = escape_sql($corpus);

	$result = do_sql_query("select handle, description from annotation_metadata where corpus = '$corpus'");

	$compiled = array();

	while ($r = mysqli_fetch_row($result)) 
		$compiled[$r[0]] = $r[1];

	return $compiled;
}

function list_corpus_annotations_html($corpus)
{
	$list = [];

	foreach(list_corpus_annotations($corpus) as $h => $d)
		if (empty($d))
			$list[$h] = $h;
		else
			$list[$h] = escape_html($d);

	return $list;
}

/**
 * Get a DB object representing a single corpus annotation (p-attribute). 
 * This has four members: handle, description, tagset, external_url
 * TODO will be 5 memebrs once we add sensitivity bitmap tinyint!
 * @param  string $corpus       Which corpus (by handle).
 * @param  string $annotation   Which annotation (by handle).
 * @return stdClass             Annotation DB object, or false if not found. 
 */
function get_annotation_info($corpus, $annotation)
{
	static $cache = NULL;
	if (empty($cache))
		$cache = array();
	if (empty($cache[$corpus]))
		$cache[$corpus] = get_all_annotation_info($corpus);
	return isset($cache[$corpus][$annotation]) ? $cache[$corpus][$annotation] : false;
}

/**
 * Returns an associative array: the keys are annotation handles,
 * the values are objects with four members: handle, description, tagset, external_url
 * TODO will be 5 memebrs once we add sensitivity bitmap tinyint!
 * 
 * @param  string $corpus       Which corpus (by handle).
 * @return array                Array of annotation DB objects (stdClass).
 */
function get_all_annotation_info($corpus)
{
	$corpus = escape_sql($corpus);

	$result = do_sql_query("select * from annotation_metadata where corpus = '$corpus'");

	$compiled = array();

	while ($o = mysqli_fetch_object($result))
		$compiled[$o->handle] = $o;

	return $compiled;
//TODO change to use get all obj
}
//TODO should this func be named "...corpus_all_anno..." (or all_corpus? or g)for consistency?
// do we even have a func to get just one??


/**
 * Update an annotation's text-description, tagset, or external url (use argument $field to specify which).
 */
function update_annotation_info($corpus, $annotation, $field, $new)
{
	switch ($field)
	{
	case 'description':
	case 'tagset':
	case 'external_url':
		break;
	default:
		exiterror("Critical error: invalid field specified for annotaiton metadata update.");
	}
	
	if (empty($new))
		$new = 'NULL';
	else
		$new = "'" . escape_sql($new) . "'";
	$annotation = cqpweb_handle_enforce($annotation);
	$corpus = cqpweb_handle_enforce($corpus);
	
//	squawk("update annotation_metadata set `$field` = $new where corpus = '$corpus' and handle = '$annotation'");
	do_sql_query("update annotation_metadata set `$field` = $new where corpus = '$corpus' and handle = '$annotation'");
}





/**
 * Update all three of description, tagset & external URL for a corpus annotation.
 * 
 * @param string $corpus             Handle for the corpus.
 * @param string $annotation         Handle for the annotation.
 * @param string $new_desc           Replacement description. 
 * @param string $new_tagset         Replacement tagset name.
 * @param string $new_external_url   Replacement documentation URL (external).
 */
function update_all_annotation_info($corpus, $annotation, $new_desc, $new_tagset, $new_external_url)
{
	update_annotation_info($corpus, $annotation, 'description',  $new_desc);
	update_annotation_info($corpus, $annotation, 'tagset',       $new_tagset);
	update_annotation_info($corpus, $annotation, 'external_url', $new_external_url);
}

/**
 * Boolean: is $handle the handle of an actually-existing word-level annotation?
 */
function check_is_real_corpus_annotation($corpus, $handle)
{
	if ($handle == 'word')
		return true;

	$handle = escape_sql($handle);
	$corpus = escape_sql($corpus);
	
	$sql = "select handle from annotation_metadata where handle='$handle' and corpus='$corpus'";
	return (0 < mysqli_num_rows(do_sql_query($sql)));
}

/** 
 * Returns a list of tags used in the given annotation field, 
 * derived from the corpus's freqtable. It returns a maximum of 1000 items,
 * so should only be used on fields that ACTUALLY DO just use a tagset.
 */
function corpus_annotation_taglist($corpus, $field)
{
	/* this function WILL NOT RUN on word - the results would be huge & unwieldy */
	if ($field == 'word')
		return array();
	
	$corpus = escape_sql($corpus);
	$field  = escape_sql($field);

	$result = do_sql_query("select item from freq_corpus_{$corpus}_{$field} limit 1000");

	$tags = array();

	while ($r = mysqli_fetch_row($result))
		$tags[] = $r[0];
	
	natcasesort($tags);
	/* natcasesort preserves key/value association ... so we need array_values() */
	return array_values($tags);
}





/**
 * Adds a new annotation to the corpus, i.e. a new p-attribute, by encoding the values from 
 * a vertical input file, then running makeall etc. and adding to reg file and database.
 * 
 * @param string $corpus          Corpus to add the new p-attribute to
 * @param string $handle          Handle for the new p-attribute
 * @param string $description     Description for the attribute.
 * @param string $tagset          Name of the tagset used by the new annotation. 
 * @param string $url             URL to documentation of the tagset (empty if none).
 * @param bool   $is_feature_set  If true, the new p-attribute is a feature set.
 * @param string $input_path      Path to the input file with the new data
 */
function add_new_annotation_to_corpus($corpus, $handle, $description, $tagset, $url, $is_feature_set, $input_path)
{
	global $Config;
	
	$eol = ($Config->cqpweb_running_on_windows ? "\r\n" : "\n");
	
	if (!corpus_exists($corpus))
		exiterror("Non-existent corpus specified!");
	
	$handle = cqpweb_handle_enforce($handle, HANDLE_MAX_ANNOTATION);

	if (check_is_real_corpus_annotation($corpus, $handle))
		exiterror("P-attribute handle ''$handle'' already exists in this corpus!");

	
	/* get and check new index data location */
	$datadir = get_corpus_index_directory($corpus, true);

	/* we need to know the corpus's underlying character set */
	$charset = get_cwb_registry_charset($corpus);
	
	/* add to registry (must be done before indexing/compresion but after encoding */	
	$old_regdata = $regdata = read_cwb_registry_file($corpus);
		
	$new_regline = "# added by CQPweb add-Annotation tool{$eol}ATTRIBUTE $handle$eol$eol";
	
	if (false !== (strpos($regdata, $encode_sig='# Yours sincerely, the Encode tool.')))
		$regdata = str_replace($encode_sig, "$new_regline$encode_sig", $regdata);
	else 
		$regdata .= $new_regline;

	write_cwb_registry_file($corpus, $regdata);

	
	/* get ready to encode etc. */

	$CORPUS = strtoupper($corpus);
	
	$abort_required_msg = array();


// begin chunk copied from install_corpus() to run encode, makeall, compress-rdx, huffcode. as mncessary for new att.
		$encode_command 
			= "{$Config->path_to_cwb}cwb-encode -xsB -c $charset -d \"$datadir\" -f \"$input_path\""
			. "-p - -P $handle" . ($is_feature_set ? '/' : '')
			. ' 2>&1'
			;
		// the above encode command is a ton simpler than in install_new_corpus since we don't create a reg.
		// this si why it has not been factored out, like the version there has.


		$exit_status_from_cwb = 0;
		/* NB this array collects both the commands used and the output sent back (via stderr, stdout) */
		$output_lines_from_cwb = array($encode_command);

		exec($encode_command, $output_lines_from_cwb, $exit_status_from_cwb);
		if ($exit_status_from_cwb != 0)
			$abort_required_msg = array_merge(array("cwb-encode reported an error! Corpus indexing aborted."), $output_lines_from_cwb); 

		$output_lines_from_cwb[] = $makeall_command = "{$Config->path_to_cwb}cwb-makeall -r \"{$Config->dir->registry}\" -V -P $handle $CORPUS 2>&1";
		exec($makeall_command, $output_lines_from_cwb, $exit_status_from_cwb);
		if ($exit_status_from_cwb != 0)
			$abort_required_msg = array_merge(array("cwb-makeall reported an error! Corpus indexing aborted."), $output_lines_from_cwb);

		if (!cwb_compress_corpus_index($CORPUS, $output_lines_from_cwb))
			$abort_required_msg = $output_lines_from_cwb;
//end coopied-and-modifiedchunk

	
	
	if (!empty($abort_required_msg))
	{
		/* delete the  files created if something went wrong above. */
		foreach (scandir($datadir) as $f)
			if (preg_match("/^$handle\./", $f))
				unlink("$datadir/$f");
		
		/* put the registry back how it was */
		write_cwb_registry_file($corpus, $old_regdata);
		
		exiterror($abort_required_msg);
	}
	
	
	/* and finally, log it in the DB */
	$description = escape_sql($description);
	$tagset = escape_sql($tagset);
	$url = escape_sql($url);
	$fs = ($is_feature_set ? '1' : '0');
	
	do_sql_query("insert into annotation_metadata 
					( corpus,    handle,    description,    tagset,    external_url, is_feature_set) 
						values 
					('$corpus', '$handle', '$description', '$tagset', '$url',        $fs)");
}






/**
 * Examines the registry file of a specified corpus for a-attributes
 * and adds the alignments to CQPweb's internal representation. 
 * 
 * @param string $corpus  The corpus to scan.
 */
function scan_for_corpus_alignments($corpus)
{
	$corpus = escape_sql($corpus);

	$regdata = read_cwb_registry_file($corpus);

	/* get list of alignments we know about already */
	$result = do_sql_query("select target from corpus_alignments where corpus = '$corpus'");
	$known_targets = array();
	while ($o = mysqli_fetch_object($result))
		$known_targets[] = $o->target;

	if (0 < preg_match_all("/\nALIGNED\s+(\w+)\b/", $regdata, $m, PREG_PATTERN_ORDER) )
		foreach($m[1] as $target)
			if (corpus_exists($target) && ! in_array($target, $known_targets))
				do_sql_query("insert into corpus_alignments (corpus,target) values ('$corpus','$target')");
}

/**
 * Examines the registry file (and cwb-describe-corpus data) of a specified corpus for new
 * s-attributes or p-attributes and adds them to CQPweb's internal representation. 
 * 
 * @param string $corpus    Corpus to be scanned.
 * @param string $type      Set to "p" or "s" to scan for just p- or just s-attributes.
 *                          Leave empty (the default) to scan for both. 
 */
function scan_for_new_corpus_attributes($corpus, $type = false)
{
// 	global $Config;
	
	$corpus = escape_sql($corpus);

	$c_info = get_corpus_info($corpus);
	
	$regdata = read_cwb_registry_file($corpus);
	
	$descdata = get_cwb_describe_corpus($c_info->cqp_name);
	
	if (empty($type) || 'p' == $type || 'P' == $type)
	{
		/* scan for unknown p-attributes */
		
		$known_p_atts = array_merge(array('word'=>'Word'), list_corpus_annotations($corpus));
		
		if (0 < preg_match_all("/ATTRIBUTE\s+(\w+)\b/", $regdata, $m, PREG_PATTERN_ORDER))
			foreach($m[1] as $p)
				if (! array_key_exists($p, $known_p_atts))
					do_sql_query("insert into annotation_metadata 
									(corpus, handle, description, tagset, external_url, is_feature_set) 
										values 
									('$corpus', '$p', '', '', '', 0)");
	}
	
	if (empty($type) || 's' == $type || 'S' == $type)
	{
		/* scan for unknown s-attributes */
		
		$known_s_atts = list_xml_all($corpus);
		
		if (0 < preg_match_all("/s-(?:att|ATT)\s+(\w+)\s+\d+ regions\s*(\(with annotations\))?\s*/", $descdata, $m, PREG_SET_ORDER))
		{
			foreach($m as $set)
			{
				if (! array_key_exists($set[1], $known_s_atts))
				{
					/* family */
					$family = $set[1];
					if (false !== strpos($set[1], '_'))
					{
						list($poss_fam) = explode('_', $set[1]);
						if (array_key_exists($poss_fam, $known_s_atts))
							$family = $poss_fam;
					}
					
					/* data type */
					$dt = ( (isset($set[2]) && preg_match('/\[annotations\]/', $set[2])) ? METADATA_TYPE_FREETEXT : METADATA_TYPE_NONE );
					
					do_sql_query("insert into xml_metadata (corpus,handle,att_family,description,datatype) 
										values 
									('$corpus','{$set[1]}','$family',\"Structure ``{$set[1]}''\",$dt)");
					
					/* we do not need to run checks on the values, since any value is valid for "FREETEXT", incl empty. */ 
					
					/* add to list, so that if this is a family, it can be picked up later. */ 
					$known_s_atts[$set[1]] = "Structure ``{$set[1]}''";
				}
			}
		}
	}
}




