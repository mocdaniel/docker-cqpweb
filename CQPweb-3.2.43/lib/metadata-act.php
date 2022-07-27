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
 * Execution script for metadata management actions of various kinds.
 * 
 * This script executes metadata creation functions, whose variable-handling is too complex
 * to go through the generic "execute" mechanism.
 */


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');


/* include all function files */
require('../lib/cqp.inc.php');
require('../lib/exiterror-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/html-lib.php');
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');
require('../lib/scope-lib.php');
require('../lib/useracct-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/template-lib.php');
require('../lib/xml-lib.php');



/* declare global variables */
$Corpus = $Config = NULL;

/* check: if this is a system corpus, only let the admin use.  If it's a user corpus, let the owner use. */
cqpweb_startup_environment( CQPWEB_STARTUP_CHECK_OWNER_OR_ADMIN , RUN_LOCATION_CORPUS );



/* set a default "next" location..." */
$next_location = "index.php?ui=manageMetadata";
/* cases are allowed to change this */

$script_action = isset($_GET['mdAction']) ? $_GET['mdAction'] : false; 

switch($script_action)
{

/* ============================================ *
 * ACTIONS AFFECTING TEXT-LEVEL METADATA SYSTEM *
 * ============================================ */


case 'createMetadataFromFile':

	php_execute_time_unlimit();

	if (empty($_GET['dataFile'] ) )
		exiterror("No input file specified for metadata installation!");
	if ( ! is_file($input_filename = "{$Config->dir->upload}/{$_GET['dataFile']}") )
		exiterror("The input file you specified for metadata installation does not appear to exist.");

	if (isset($_GET['useMetadataTemplate']) && '~~customMetadata' == $_GET['useMetadataTemplate']) 
	{
		$n_of_primary_classification = empty($_GET['primaryClassification']) ? NULL : (int)$_GET['primaryClassification'];
		
		/* build array of field descriptions */
		$fields = array();
		
		$field_count = isset($_GET['fieldCount']) ? (int)$_GET['fieldCount'] : 0;
		for ($i = 1; $i <= $field_count; $i++)
		{
			if (empty($_GET["fieldHandle$i"]))
				continue;
			
			$dt = (int)$_GET["fieldType$i"];
			if (!metadata_valid_datatype($dt))
				exiterror("Invalid datatype supplied for field no. $i!");
			
			$fields[] = array(
					'handle'      => $_GET["fieldHandle$i"],
					'description' => $_GET["fieldDescription$i"],
					'datatype'    => $dt
				);
			if ($n_of_primary_classification == $i)
				$primary_classification = $_GET["fieldHandle$i"];
		}
	}
	else
	{
		/* install from template */
		$template_id = (int) $_GET['useMetadataTemplate'];

		$t_list = list_metadata_templates();
		
		if (!array_key_exists($template_id, $t_list))
			exiterror("Critical error: nonexistent metadata template specified.");
			
		$primary_classification = $t_list[$template_id]->primary_classification;
		
		$fields = array();
		foreach($t_list[$template_id]->fields as $f)
			$fields[$f->order_in_template] = array(
					'handle'      => $f->handle,
					'description' => $f->description,
					'datatype'    => $f->datatype
				);
		ksort($fields); /* just in case */
	}
	

	create_text_metadata_from_file($Corpus->name, $input_filename, $fields, (isset($primary_classification) ? $primary_classification : NULL) );
	
	if (isset($_GET['createMetadataRunFullSetupAfter']) && $_GET['createMetadataRunFullSetupAfter'])
		setup_all_corpus_freqlist_data($Corpus->name);
	
 	break;


case 'createMetadataFromXml':
	
	php_execute_time_unlimit();

	/* build array of field descriptions */
	$fields = array();
	foreach($_GET as $k => &$v)
	{
		if (substr($k, 0, 24) != 'createMetadataFromXmlUse')
			continue;
		if ($v !== '1')
			continue;
			
		/* OK, we know we've found a field handle that we are supposed to use. */
		list(, $handle) = explode('_', $k, 2);
		/* note that the XML build function called below checks that all fields are actually s-attributes. */
		
		/* the datatype is deduced from the XML info table */
		$dt = get_xml_info($Corpus->name, $handle)->datatype;

		$fields[] = array(
				'handle'         => $handle,
				'description'    => $_GET["createMetadataFromXmlDescription_$handle"],
				'datatype'       => $dt
			);
	}

	/* note that the primary classification on this form is a handle (not an int as in the from-file function)! */
	$primary_classification = empty($_GET['primaryClassification']) ? NULL : (int)$_GET['primaryClassification'];
//TODO, so why is there an int typecast??????

	create_text_metadata_from_xml($Corpus->name, $fields, $primary_classification);

	if (isset($_GET['createMetadataRunFullSetupAfter']) && $_GET['createMetadataRunFullSetupAfter'])
		setup_all_corpus_freqlist_data($Corpus->name);

	break;


case 'createTextMetadataMinimalist':

	php_execute_time_unlimit();

	create_text_metadata_minimalist($Corpus->name);
	if (isset($_GET['createMetadataRunFullSetupAfter']) && $_GET['createMetadataRunFullSetupAfter'])
		setup_all_corpus_freqlist_data($Corpus->name);
	break;


case 'clearMetadataTable':

 	if ($_GET['clearMetadataAreYouReallySure'] != 'yesYesYes')
 		exiterror("CQPweb won't delete the metadata unless you confirm you're certain!");

	delete_text_metadata_for($Corpus->name);

 	break;


case 'updateMetadataCategoryDescriptions':

	foreach($_GET as $key => $val_desc)
	{
		if (substr($key, 0, 5) !== 'desc-')
			continue;
		list(, $field, $val_handle) = explode('-', $key);
		
		$field = escape_sql($field);
		$val_handle = escape_sql($val_handle);
		$val_desc = escape_sql($val_desc);
		
		$sql = "update text_metadata_values set description='{$val_desc}' 
			where corpus       = '{$Corpus->name}' 
			and   field_handle = '$field'
			and   handle       = '$val_handle'";
		do_sql_query($sql);
	}

	$next_location = "index.php?ui=manageCategories";
	
	break;





/* =========================================== *
 * ACTIONS AFFECTING XML-LEVEL METADATA SYSTEM *
 * =========================================== */



case 'runXmlCategorySetup':
	/* note that, in almost all circumstances, this will be run automatically;
	 * it is only for old (pre 3.2) corpora that this is really needed. */

	if (empty($_GET['xmlClassification']))
		exiterror("No XML Classification was specified.");
	
	$info = get_xml_info($Corpus->name, $_GET['xmlClassification']);
	
	if (empty($info))
		exiterror("That XML Classification could not be found.");
	if (METADATA_TYPE_CLASSIFICATION != $info->datatype)
		exiterror("That XML attribute is not of the necessary type (a Classification).");

	setup_xml_classification_categories($info->corpus, $info->handle);

	$next_location = "index.php?ui=manageXml";
	
	break;


case 'xmlChangeDatatype':

	$next_location = "index.php?ui=manageXml";

	/* if no datatype selected in the form, do nothing. */
	if (empty($_GET['newDatatype']) || $_GET['newDatatype'] == '~~NULL')
		break;
	
	/* if invalid handle, existerror. */
	if (empty($_GET['handle']) || ! xml_exists($_GET['handle'], $Corpus->name) )
		exiterror ("non-existent or invalid XML attribute handle.");

	change_xml_datatype($Corpus->name, $_GET['handle'], (int)$_GET['newDatatype']);

	break;


case 'updateXmlCategoryDescriptions':
	
	foreach($_GET as $key => $val_desc)
	{
		if (substr($key, 0, 5) !== 'desc-')
			continue;
		list(, $att, $val_handle) = explode('-', $key);
		
		$att = escape_sql($att);
		$val_handle = escape_sql($val_handle);
		$val_desc = escape_sql($val_desc);
		
		$sql = "update xml_metadata_values set description='{$val_desc}' 
			where corpus       = '{$Corpus->name}' 
			and   att_handle   = '$att'
			and   handle       = '$val_handle'";
		do_sql_query($sql);
	}

	$next_location = "index.php?ui=manageXml";
	
	break;
	
	
case 'createXmlIdlinkTable':
	
	/*TODO note
	 * this case is very, very similar to the createMetadataFromFile option -- consider wehtehr some of it can be abstracted to functions.
	 * 
	 * (basically all I did was yank out anything referring to "primary classification" by deleting the relevant lines.)
	 * 
	 * The same applies to the underluying functions in xml-lib.php and metadata-lib
	 */
	
	php_execute_time_unlimit();

	if (empty($_GET['dataFile'] ) )
		exiterror("No input file specified for idlink metadata installation!");
	if ( ! is_file($input_filename = "{$Config->dir->upload}/{$_GET['dataFile']}") )
		exiterror("The input file you specified for idlink metadata installation does not appear to exist.");
	
	if (empty($_GET['xmlAtt']))
		exiterror("No XML Attribute was specified for this idlink installation!");
	if (!array_key_exists($_GET['xmlAtt'], list_xml_with_values($Corpus->name)))
		exiterror("A non-existent XML Attribute was specified for this idlink installation!");
	
	$xml_att = $_GET['xmlAtt'];

	if (isset($_GET['useMetadataTemplate']) && '~~customMetadata' == $_GET['useMetadataTemplate']) 
	{
		/* build array of field descriptions */
		$fields = array();
		
		$field_count = isset($_GET['fieldCount']) ? (int)$_GET['fieldCount'] : 0;
		for ($i = 1; $i <= $field_count; $i++)
		{
			if (empty($_GET["fieldHandle$i"]))
				continue;
			
			$dt = (int)$_GET["fieldType$i"];
			if (!metadata_valid_datatype($dt))
				exiterror("Invalid datatype supplied for field no. $i!");
			
			$fields[] = array(
					'handle'      => $_GET["fieldHandle$i"],
					'description' => $_GET["fieldDescription$i"],
					'datatype'    => $dt
				);
		}
	}
	else
	{
		/* install from template */
		$template_id = (int) $_GET['useMetadataTemplate'];

		$t_list = list_metadata_templates();
		
		if (!array_key_exists($template_id, $t_list))
			exiterror("Critical error: nonexistent metadata template specified.");
			
		$fields = array();
		foreach($t_list[$template_id]->fields as $f)
			$fields[$f->order_in_template] = array(
					'handle'      => $f->handle,
					'description' => $f->description,
					'datatype'    => $f->datatype
				);
		ksort($fields); /* just in case */
	}
	
	create_idlink_table_from_file($Corpus->name, $xml_att, $input_filename, $fields);

 	$next_location = 'index.php?ui=manageXml';
	
	break;


case 'updateXmlIdlinkCategoryDescriptions':

	foreach($_GET as $key => $val_desc)
	{
		if (substr($key, 0, 5) !== 'desc-')
			continue;
		list(, $att, $field, $val_handle) = explode('-', $key);
		
		$att        = escape_sql($att);
		$field      = escape_sql($field);
		$val_handle = escape_sql($val_handle);
		$val_desc   = escape_sql($val_desc);
		
		$sql = "update idlink_values set description='$val_desc' 
			where corpus       = '{$Corpus->name}' 
			and   att_handle   = '$att'
			and   field_handle = '$field'
			and   handle       = '$val_handle'";
		do_sql_query($sql);
	}

	$next_location = "index.php?ui=manageXml";
	
	break;


	

/* ============= *
 * OTHER ACTIONS *
 * ============= */




case 'addVariableCorpusMetadata':
case 'deleteVariableCorpusMetadata':
	
	if (empty($_GET['variableMetadataAttribute'] ) )
		exiterror("No attribute was specified for the variable metadata!");
	
	if (empty($_GET['variableMetadataValue'] ) )
		exiterror("No value was specified for the variable metadata!");

	if ('addVariableCorpusMetadata' == $script_action)
		add_variable_corpus_metadata($Corpus->name, $_GET['variableMetadataAttribute'], $_GET['variableMetadataValue']);
	else
		delete_variable_corpus_metadata($Corpus->name, $_GET['variableMetadataAttribute'], $_GET['variableMetadataValue']);

	$next_location = "index.php?ui=corpusSettings";

	break;

	
	

/* ============== *
 * DEFAULT ACTION *
 * ============== */



default:

	exiterror("No action specified for corpus metadata admin.");
	break;


} /* end the main switch */



if (isset($next_location))
	set_next_absolute_location($next_location);

cqpweb_shutdown_environment();

exit(0);


/* ------------- *
 * END OF SCRIPT *
 * ------------- */

