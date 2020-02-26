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
 * Functions for management / invocation of corpus-indexing templates.
 */


/*
 * =========================================
 * FUNCTION LIBRARY FOR ANNOTATION TEMPLATES
 * =========================================
 */


/**
 * Gets a single database object representing the specified annotation template.
 * 
 * @param  int      $template_id    ID of template to look up.
 * @return object                   Database object, or false if not found.
 */
function get_annotation_template_info($template_id)
{
	$template_id = (int)$template_id;
	
	$result = do_sql_query("select * from annotation_template_info where id=$template_id");
	
	if (1 > mysqli_num_rows($result))
		return false;
	
	$template = mysqli_fetch_object($result);	
	$template->attributes = array();
	
	$result = do_sql_query("select * from annotation_template_content where template_id=$template_id order by order_in_template");
	
	while ($o = mysqli_fetch_object($result))
		$template->attributes[$o->order_in_template] = $o;
	
	return $template;
}


/**
 * Returns an array of objects representing annotation templates.
 * 
 * Each object contains: (a) the fields from the database; (b) an array "attributes" of database objects for the template's p-attributes.
 * 
 * The array keys are the ID numbers.
 */
function list_annotation_templates()
{
	$list = array();
	
	$result = do_sql_query("select * from annotation_template_info order by id");
	
	while ($o = mysqli_fetch_object($result))
	{
		$o->attributes = array();
		$list[$o->id] = $o;
	}
	
	$result = do_sql_query("select * from annotation_template_content order by template_id, order_in_template");
	
	while ($o = mysqli_fetch_object($result))
	{
		/* skip any attributes whose linked template does not exist (sanity check) */
		if (!isset($list[$o->template_id]))
			continue;
		$list[$o->template_id]->attributes[$o->order_in_template] = $o;
	}
	
	return $list;
}

/**
 * Add a new annotation template.
 * 
 * @param string $description  A string to label it with. Does not have to be unique.
 * @param string $primary      Handle of the primary annotation (one of the attributes listed in the next argument).
 * @param array  $attributes   An array of p-attribute descriptions. This must contain either inner-arrays or objects, each of which is a
 *                             description of a p-attribute, as created by the various forms and stored in the database. Necessary fields are
 *                             as follows:
 *                              * handle => C-word-only short handle for the attribute.
 *                              * description => Long description for the attribute.
 *                              * is_feature_set => Boolean for whether this is a feature set.
 *                              * tagset => name of tagset (empty string if none).
 *                              * external_url => URL to tagset manual (empty string if none). 
 *                             The keys of the entire array should be numeric and should represent the column-numbers to which each attribute
 *                             applies - that is, start at 1 (because "word" is 0) and go up from there, with no gaps. 
 */
function add_annotation_template($description, $primary, $attributes)
{
	$description  = escape_sql($description);
	do_sql_query("insert into annotation_template_info (description) values ('$description')");
	$id = get_sql_insert_id(); 
	
	if (isset($attributes[0]))
		exiterror("Incorrectly formatted array passed to add_ANNOTATION_template!! (Attribute array seems to be zero-based).");
	
	if (!empty($primary))
	{
		$primary = cqpweb_handle_enforce($primary);
		do_sql_query("update annotation_template_info set primary_annotation = '$primary' where id = $id");
	} 
	
	for ($i = 1 ; isset($attributes[$i]) ; $i++)
	{
		$a = is_object($attributes[$i]) ? $attributes[$i] : (object)$attributes[$i];
		
		$sql = "insert into annotation_template_content (template_id, order_in_template, handle, description, is_feature_set, tagset, external_url) values (";
		
		$sql .= "$id, $i,";
		
		$sql .= ' \'' . cqpweb_handle_enforce($a->handle) . '\', ' ;
		
		$sql .= ' \'' . escape_sql($a->description) . '\', ';
		
		$sql .= ($a->is_feature_set ? '1,' : '0,');

		$sql .= ' \'' . escape_sql($a->tagset) . '\', ';
		
		$sql .= ' \'' . escape_sql($a->external_url) . '\'';

		$sql .= ')';
		
		do_sql_query($sql);
	}
}


/**
 * Deletes template with the specified ID. 
 */
function delete_annotation_template($id)
{
	$id = (int) $id;
	do_sql_query("delete from annotation_template_content where template_id = $id");
	do_sql_query("delete from annotation_template_info where id = $id");
}


/**
 * Some templates which it suits me to have built into the system.
 */
function load_default_annotation_templates()
{
	add_annotation_template('Word tokens only', NULL, NULL);
	
	$pos_plus_lemma = array (
			1 => array('handle'=>'pos', 'description'=>'Part-of-speech tag', 'is_feature_set'=>false, 
						'tagset'=>'', 'external_url'=>''),
			2 => array('handle'=>'lemma', 'description'=>'Lemma', 'is_feature_set'=>false, 
						'tagset'=>'', 'external_url'=>'')
			);
			
	add_annotation_template('POS plus lemma (TreeTagger format)', 'pos', $pos_plus_lemma);

	$pos_plus_lemma[3] = 
			array('handle'=>'semtag', 'description'=>'Semantic tag', 'is_feature_set'=>false, 
					'tagset'=>'USAS tagset', 'external_url'=>'http://ucrel.lancs.ac.uk/usas');


	add_annotation_template('POS plus lemma plus semtag', 'pos',  $pos_plus_lemma);

	$lancaster_annotations = array ( 
//TODO what are these index literal numerals here for? 
			1 => array('handle'=>'pos', 'description'=>'Part-of-speech tag', 'is_feature_set'=>false, 
						'tagset'=>'C6 tagset', 'external_url'=>'http://ucrel.lancs.ac.uk/claws6tags.html'),
			2 => array('handle'=>'lemma', 'description'=>'Lemma', 'is_feature_set'=>false, 
						'tagset'=>'', 'external_url'=>''),
			3 => array('handle'=>'semtag', 'description'=>'Semantic tag', 'is_feature_set'=>false, 
						'tagset'=>'USAS tagset', 'external_url'=>'http://ucrel.lancs.ac.uk/usas'),
			4 => array('handle'=>'class', 'description'=>'Simple POS', 'is_feature_set'=>false, 
						'tagset'=>'Oxford Simplified Tagset', 'external_url'=>'http://www.natcorp.ox.ac.uk/docs/URG/codes.html#klettpos'),
			5 => array('handle'=>'taglemma', 'description'=>'Tagged lemma', 'is_feature_set'=>false, 
						'tagset'=>'', 'external_url'=>''),
			6 => array('handle'=>'fullsemtag', 'description'=>'Full USAS analysis', 'is_feature_set'=>false, 
						'tagset'=>'USAS tagset', 'external_url'=>'')
			);

	add_annotation_template('Lancaster toolchain annotations', 'pos', $lancaster_annotations);
	
	$lancaster_annotations[7] = 
					array('handle'=>'orig', 'description'=>'Unregularised spelling', 'is_feature_set'=>false, 
							'tagset'=>'', 'external_url'=>'');

	add_annotation_template('Lancaster toolchain annotations ( + VARD orig.)', 'pos',  $lancaster_annotations);

}


/**
 * This is a bit of a cheat function as it breaks the usual separation of levels.
 * 
 * But it beats creating a separate script for just this!
 */
function interactive_load_annotation_template()
{
	if (empty($_GET['newTemplateDescription']))
		exiterror("No description given for new template.");
	
	$description = $_GET['newTemplateDescription'];
	
	$atts = array();

	for ( $i = 1; !empty($_GET["templatePHandle$i"]) ; $i++ )
	{
		$atts[$i] = new stdClass();
		
		$atts[$i]->handle = cqpweb_handle_enforce($_GET["templatePHandle$i"]);
		
		if ($atts[$i]->handle == '__HANDLE')
		{
			unset($atts[$i]);
			break;
		}
		
		$atts[$i]->description = $_GET["templatePDesc$i"];
		
		$atts[$i]->tagset = $_GET["templatePTagset$i"];
		
		$atts[$i]->external_url = $_GET["templatePurl$i"];
		
		$atts[$i]->is_feature_set = (isset($_GET["templatePfs$i"]) && 1 == $_GET["templatePfs$i"]);
		
		if (isset($_GET['templatePPrimary']) && $i == $_GET['templatePPrimary'])
			$primary = $atts[$i]->handle;
	}
	
	if (!isset($primary))
		$primary = NULL;
	
	add_annotation_template($description, $primary, $atts);
}







function set_install_data_from_annotation_template($template_id, $corpus_name, &$primary_annotation, &$encode_strings, &$sql_statements)
{
	$t = get_annotation_template_info($template_id);
	if (!$t)
		return false;
	
	if (!is_array($encode_strings))
		$encode_strings = array();
	if (!is_array($sql_statements))
		$sql_statements = array();
	
	for ($q = 1 ; isset($t->attributes[$q]) ; $q++)
	{
		$encode_strings[] = $t->attributes[$q]->handle . ($t->attributes[$q]->is_feature_set ? '/' : '') ;
		$sql_statements[] = sql_for_p_att_insert($corpus_name,
								$t->attributes[$q]->handle, 
								escape_sql($t->attributes[$q]->description), 
								escape_sql($t->attributes[$q]->tagset), 
								escape_sql($t->attributes[$q]->external_url),
								$t->attributes[$q]->is_feature_set
							); 
	}
	if (!empty($t->primary_annotation))
		$primary_annotation = $t->primary_annotation;
	
	return true;
}









/*
 * ==================================
 * FUNCTION LIBRARY FOR XML TEMPLATES
 * ==================================
 */


/**
 * Gets a single database object representing the specified XML template.
 * 
 * @param  int      $template_id    ID of template to look up.
 * @return object                   Database object, or false if not found.
 */
function get_xml_template_info($template_id)
{
	$template_id = (int)$template_id;
	$result = do_sql_query("select * from xml_template_info where id=$template_id");
	
	if (1 > mysqli_num_rows($result))
		return false;
	
	$template = mysqli_fetch_object($result);
	$template->attributes = array();
	
	$result = do_sql_query("select * from xml_template_content where template_id=$template_id order by order_in_template");
	
	while ($o = mysqli_fetch_object($result))
		$template->attributes[$o->order_in_template] = $o;
	
	return $template;
}

/**
 * Returns an array of objects representing XML templates.
 * 
 * Each object contains: (a) the fields from the database; (b) an array "attributes" of database objects for the template's s-attributes.
 * 
 * The array keys are the ID numbers.
 */
function list_xml_templates()
{
	$list = array();
	
	$result = do_sql_query("select * from xml_template_info order by id");
	
	while ($o = mysqli_fetch_object($result))
	{
		$o->attributes = array();
		$list[$o->id] = $o;
	}
		
	$result = do_sql_query("select * from xml_template_content order by template_id, order_in_template");
	
	while ($o = mysqli_fetch_object($result))
	{
		/* skip any attributes whose linked template does not exist (sanity check) */
		if (!isset($list[$o->template_id]))
			continue;
		$list[$o->template_id]->attributes[$o->order_in_template] = $o;
	}
	
	return $list;
}


/**
 * Add a new XML template.
 * 
 * @param string $description  A string to label it with. Does not have to be unique.
 * @param array  $attributes   An array of s-attribute specifications. This must contain either inner-arrays or objects, each of which is a
 *                             description of an s-attribute, as created by the various forms and stored in the database. Necessary fields are
 *                             as follows:
 *                              * handle => C-word-only short handle for the attribute.
 *                              * description => Long description for the attribute.
 *                              * att_family => the att family
 *                              * datatype => a datatype constant
 *                              * note that "order in template" may be present but will be ignored: the integer keys of the outer-array will be used.
 *                             Important note: array numbering should start at 1 and run sequentially, as 1-based numbering is used for "order in template".
 *                             See the description of the xml_metadata table for more.
 */
function add_xml_template($description, $attributes)
{
	$description  = escape_sql($description);
	do_sql_query("insert into xml_template_info (description) values ('$description')");
	$id = get_sql_insert_id(); 
	
	if (isset($attributes[0]))
		exiterror("Incorrectly formatted array passed to add_xml_template!! (Attribute array seems to be zero-based).");
	
	for ($i = 1 ; isset($attributes[$i]) ; $i++)
	{
		$a = is_object($attributes[$i]) ? $attributes[$i] : (object)$attributes[$i];
		
		$sql = "insert into xml_template_content (template_id, order_in_template, handle, att_family, description, datatype) values ("
			. "$id, $i,"
			. ' \'' . cqpweb_handle_enforce($a->handle) . '\', ' 
			. ' \'' . cqpweb_handle_enforce($a->att_family) . '\', ' 
			. ' \'' . escape_sql($a->description) . '\', '
			. (int)$a->datatype
			. ')'
			;
		do_sql_query($sql);
	}
}

function delete_xml_template($id)
{
	$id = (int) $id;
	do_sql_query("delete from xml_template_info where id = $id");
	do_sql_query("delete from xml_template_content where template_id = $id");
}


function load_default_xml_templates()
{
	$specifiers = array (
		1 => array( 'handle'=>'text',    'description'=>'Text',    'att_family'=>'text', 'datatype'=>METADATA_TYPE_NONE ) ,
		2 => array( 'handle'=>'text_id', 'description'=>'Text ID', 'att_family'=>'text', 'datatype'=>METADATA_TYPE_UNIQUE_ID ) ,
		);
	add_xml_template('Text elements (with id attributes) only', $specifiers);	
	
	$specifiers[3] = array( 'handle'=>'s', 'description'=>'Sentence', 'att_family'=>'s', 'datatype'=>METADATA_TYPE_NONE );
	add_xml_template('Text elements (with IDs) plus s for Sentence', $specifiers);	
}

/** 
 * "Interactive" function: deals with the GET from the creation form.
 */
function interactive_load_xml_template()
{
	if (empty($_GET['newTemplateDescription']))
		exiterror("No description given for new template.");
	$description = $_GET['newTemplateDescription'];
	
	$atts = array();

	/* LOOP VARIABLES: we use $i to cycle through elements, and $j to cycle through attributes.
	 * 
	 * However, these are indexes into the $_GET variable (the form, 
	 * which uses names like templateSDesc1, templateSDescAtt1_1, and so on.)
	 * 
	 * We need a separate index into the object array, which increments BOTH for $i AND for $j.
	 * This is $a_ix (short for "$atts index").
	 */
	for ( $i = 1, $a_ix = 1; !empty($_GET["templateSHandle$i"]) ; $i++, $a_ix++ )
	{
		/* create a four-member object for the element */
		$atts[$a_ix] = new stdClass();
		$atts[$a_ix]->handle = cqpweb_handle_enforce($_GET["templateSHandle$i"]);
		if ($atts[$a_ix]->handle == '__HANDLE')
			exiterror("Invalid s-attribute handle: " . escape_html($_GET["templateSHandle$i"]) . ".");
		$atts[$a_ix]->description = $_GET["templateSDesc$i"];
		$atts[$a_ix]->att_family = $atts[$a_ix]->handle;
		$atts[$a_ix]->datatype = METADATA_TYPE_NONE;

		/* attributes of the element */
		for ( $j = 1, $family_handle = $atts[$a_ix++]->handle; !empty($_GET["templateSHandleAtt{$i}_$j"]) ; $j++, $a_ix++ )
		{
			/* we had an extra increment of $a_ix in loop initialisation, so we don't overwrite what we just finished doing! */
			$atts[$a_ix] = new stdClass();
			/* grab the handle alone for checking; then, prepend the family handle. */
			$atts[$a_ix]->handle = cqpweb_handle_enforce($_GET["templateSHandleAtt{$i}_$j"]);
			if ($atts[$a_ix]->handle == '__HANDLE')
				exiterror("Invalid s-attribute handle: " . escape_html($_GET["templateSHandleAtt{$i}_$j"]) . ".");
			$atts[$a_ix]->handle = $family_handle . '_' . $atts[$a_ix]->handle;
			$atts[$a_ix]->description = $_GET["templateSDescAtt{$i}_$j"];
			$atts[$a_ix]->att_family = $family_handle;
			$atts[$a_ix]->datatype = (int)$_GET["templateSTypeAtt{$i}_$j"];

			/* note on next action: any existing datatype other than "none" is allowed. */
			switch($atts[$a_ix]->datatype)
			{
			case METADATA_TYPE_CLASSIFICATION:
			case METADATA_TYPE_FREETEXT:
			case METADATA_TYPE_UNIQUE_ID:
			case METADATA_TYPE_IDLINK:
			case METADATA_TYPE_DATE:
				break;
			default:
				exiterror("Invalid attribute datatype supplied  for attribute ``{$atts[$a_ix]->handle}''!");
			}
		}
		/* if there were not any attributes, roll back $a_ix (so next loop will set it to the value after the element) */
		if (!isset($atts[$a_ix])) /* ie if no object was created */
			--$a_ix;
	}

	/* VALIDATION: check length of text fields - handle and description. */
	foreach ($atts as $check)
	{
		if (64 < strlen($check->handle))
			exiterror("Overlong s-attribute handle {$check->handle} (must be 64 characters or less).");
		if (255 < strlen($check->description))
			exiterror("Overlong s-attribute handle {$check->description} (must be 255 characters (bytes) or less).");
		/* NB MySQL's varchar = bytes not characters when UTF-8 */
	}
	
	add_xml_template($description, $atts);
}


function set_install_data_from_xml_template($template_id, $corpus_name, &$encode_strings, &$sql_statements)
{
	$t = get_xml_template_info($template_id);
	if (!$t)
		return false;
	
	if (!is_array($encode_strings))
		$encode_strings = array();
	if (!is_array($sql_statements))
		$sql_statements = array();
	
	for ($q = 1 ; isset($t->attributes[$q]) ; $q++)
	{
		if ($t->attributes[$q]->att_family == $t->attributes[$q]->handle)
			$encode_strings[$t->attributes[$q]->handle] = $t->attributes[$q]->handle;
		else
		{
			$unfamilied_handle = preg_replace("|^{$t->attributes[$q]->att_family}_|", '', $t->attributes[$q]->handle);
			$encode_strings[$t->attributes[$q]->att_family] .= '+' .  $unfamilied_handle;
		}
		
		$sql_statements[] = sql_for_s_att_insert($corpus_name,
								$t->attributes[$q]->handle,
								$t->attributes[$q]->att_family, 
								escape_sql($t->attributes[$q]->description),
								$t->attributes[$q]->datatype
							);
	}
	
	/* erase the keys added to the array of cwb-encode specifications */
	$encode_strings = array_values($encode_strings);
	
	return true;
}






/*
 * =======================================
 * FUNCTION LIBRARY FOR METADATA TEMPLATES
 * =======================================
 */

/**
 * Gets a single database object representing the specified metadata template.
 * 
 * @param  int      $template_id    ID of template to look up.
 * @return object                   Database object, or false if not found.
 */
function get_metdata_template_info($template_id)
{
	$template_id = (int)$template_id;
	
	$result = do_sql_query("select * from metadata_template_info where id=$template_id");
	
	if (1 > mysqli_num_rows($result))
		return false;

	$template = mysqli_fetch_object($result);
	$template->fields = array();
	
	$result = do_sql_query("select * from metadata_template_content where template_id=$template_id order by order_in_template");
	
	while ($o = mysqli_fetch_object($result))
		$template->fields[$o->order_in_template] = $o;
	
	return $template;
}


/**
 * Returns an array of objects representing metadata templates.
 * 
 * Each object contains: (a) the fields from the database; (b) an array "fields" of database objects for the template's columsn.
 * 
 * The array keys are the ID numbers.
 */
function list_metadata_templates()
{
	$list = array();
	
	$result = do_sql_query("select * from metadata_template_info order by id");
	
	while ($o = mysqli_fetch_object($result))
	{
		$o->fields = array();
		$list[$o->id] = $o;
	}
	
	$result = do_sql_query("select * from metadata_template_content order by template_id, order_in_template");
	
	while ($o = mysqli_fetch_object($result))
	{
		/* skip any attributes whose linked template does not exist (sanity check) */
		if (!isset($list[$o->template_id]))
			continue;
		$list[$o->template_id]->fields[$o->order_in_template] = $o;
	}
	
	return $list;
}

/**
 * Add a new metadata template.
 * 
 * @param string $description  A string to label it with. Does not have to be unique.
 * @param array  $fields       An array of s-attribute specifications. This must contain either inner-arrays or objects, each of which is a
 *                             description of an s-attribute, as created by the various forms and stored in the database. Necessary fields are
 *                             as follows:
 *                             * handle => C-word-only short handle for the attribute.
 *                             * description => Long description for the attribute.
 *                             * att_family => the att family
 *                             * datatype => a datatype constant
 *                             * note that "order in template" may be present but will be ignored: the integer keys of the outer-array will be used.
 *                             (Said keys should start at one because template order numbering is 1-based.)
 * @param string $primary_classification  Optional: handle of the primary classification.
 */
function add_metadata_template($description, $fields, $primary_classification = NULL)
{
	$description  = escape_sql($description);
	do_sql_query("insert into metadata_template_info (description) values ('$description')");
	$id = get_sql_insert_id(); 
	
	$collect_handles = array();
	
	if (isset($fields[0]))
		exiterror("Incorrectly formatted array passed to add_metadata_template!! (Field array seems to be zero-based).");
	
	for ($i = 1 ; isset($fields[$i]) ; $i++)
	{
		
		$f = is_object($fields[$i]) ? $fields[$i] : (object)$fields[$i];
		
		$sql = "insert into metadata_template_content (template_id, order_in_template, handle, description, datatype) values (";
		
		$sql .= "$id, $i,";
		
		$sql .= ' \'' . cqpweb_handle_enforce($f->handle) . '\', ' ;
		
		$sql .= ' \'' . escape_sql($f->description) . '\', ';
		
		$sql .= (int)$f->datatype;

		$sql .= ')';
		
		do_sql_query($sql);
		
		$collect_handles[] = $f->handle;
	}
	
	if (!empty($primary_classification))
		if (in_array($primary_classification, $collect_handles))
			do_sql_query("update metadata_template_info set primary_classification = '$primary_classification' where id = $id");
}

function delete_metadata_template($id)
{
	$id = (int) $id;
	do_sql_query("delete from metadata_template_info where id = $id");
	do_sql_query("delete from metadata_template_content where template_id = $id");
}

function load_default_metadata_templates()
{
	/* There do not, as yet, exist any default metadata templates.
	 * Some will be added as it becomes apparent what we need here.
	 */ 
}

function interactive_load_metadata_template()
{
	if (empty($_GET['newTemplateDescription']))
		exiterror("No description given for new template.");
	
	$description = $_GET['newTemplateDescription'];
	
	$fields = array();

	for ( $i = 1; !empty($_GET["fieldHandle$i"]) ; $i++ )
	{
		$fields[$i] = new stdClass();
		
		$fields[$i]->handle = cqpweb_handle_enforce($_GET["fieldHandle$i"]);
		
		if ($fields[$i]->handle == '__HANDLE')
		{
			unset($fields[$i]);
			break;
		}
		
		$fields[$i]->description = $_GET["fieldDescription$i"];
		$fields[$i]->datatype = (int)$_GET["fieldType$i"];

		/* note on next action: any existing datatype other than "none" is allowed. */
		switch($fields[$i]->datatype) 
		{
		case METADATA_TYPE_CLASSIFICATION:
		case METADATA_TYPE_FREETEXT:
		case METADATA_TYPE_UNIQUE_ID:
		case METADATA_TYPE_IDLINK:
		case METADATA_TYPE_DATE:
			break;
		default:
			exiterror("Invalid field datatype supplied  for field ``{$fields[$i]->handle}''!");
		}
		
		if (isset($_GET['primaryClassification']) && $i == $_GET['primaryClassification'])
			$primary = $fields[$i]->handle;	
	}

	add_metadata_template($description, $fields, (isset($primary) ? $primary : NULL));
}


