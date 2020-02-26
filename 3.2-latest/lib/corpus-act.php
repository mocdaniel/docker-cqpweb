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
 * This file contains the script for actions affecting corpus settings etc.
 * 
 * Currently, mnay of these things are done using execute.php.
 * 
 * However, once people can index their own corpora, this will not be an option:
 * we do not allow non-admin users to use that script!
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
require('../lib/annotation-lib.php');
require('../lib/corpus-lib.php');
require('../lib/metadata-lib.php');
require('../lib/useracct-lib.php');
require('../lib/template-lib.php');
require('../lib/xml-lib.php');


/* declare global variables */
$Corpus = $User = $Config = NULL;

/* check: if this is a system corpus, only let the admin use.  If it's a user corpus, let the owner use. */
cqpweb_startup_environment( CQPWEB_STARTUP_CHECK_OWNER_OR_ADMIN , RUN_LOCATION_CORPUS );



/* set a default "next" location..." */
$next_location = "index.php?ui=corpusSettings";
/* cases are allowed to change this */

$script_action = isset($_GET['caAction']) ? $_GET['caAction'] : false; 


switch ($script_action)
{
	/*
	 * =======================
	 * CORPUS SETTINGS ACTIONS
	 * =======================
	 */
	
case 'updateCorpusTitle':
	
	if (!isset($_GET['newTitle']))
		exiterror("Missing parameter for new corpus title.");
	
	update_corpus_title($Corpus->name, $_GET['newTitle']);
	
	break;
	
	
case 'updatePrimaryClassification':
	
	if (!isset($_GET['newField']))
		exiterror("Missing parameter for new primary classification.");

	update_corpus_primary_classification_field($Corpus->name, $_GET['newField']);
	
	break;
	
	
case 'updateExternalUrl':
	
	if (!isset($_GET['newExternalUrl']))
		exiterror("Missing parameter for new external URL.");

	update_corpus_external_url($Corpus->name, $_GET['newExternalUrl']);

	break;
	
	
case 'updateCssPath':
	
	/* don't let css path be altered directly by users */
	if (!$User->is_admin())
		break;
	
	if (!isset($_GET['newCssPath']))
		exiterror("Missing parameter for new CSS path.");
	
	update_corpus_css_path($Corpus->name, $_GET['newCssPath']);

	break;
	

case 'updateAltViewAtt':
	
	if (!isset($_GET['newAltAtt']))
		exiterror("Missing parameter for new alternative-view annotation.");
	
	update_corpus_alt_context_word_att($Corpus->name, $_GET['newAltAtt']);

	break;

	
case 'updateAccessStatement':
	
	if (!isset($_GET['statement']))
		exiterror("Missing parameter for updated access statement.");
	
	update_corpus_access_statement($Corpus->name, $_GET['statement']);
	
	$next_location = "index.php?ui=manageAccess";

	break;
	

case 'updateCaseSensitive':
	
	if (!isset($_GET['isCS']))
		exiterror("Missing parameter for new case-sensitivity setting.");

	update_corpus_uses_case_sensitivity($Corpus->name, $_GET['isCS']);

	break;
	

// TODO - no form or indeed SQL column for this yet.
case 'updateDiacriticSensitive':
	
	if (!isset($_GET['isDS']))
		exiterror("Missing parameter for new diacritic-sensitivity setting.");

	update_corpus_uses_diacritic_sensitivity($Corpus->name, $_GET['isDS']);

	break;
	
			
case 'updateScriptR2L':
	
	if (!isset($_GET['isR2L']))
		exiterror("Missing parameter for new script right-to-left setting.");

	update_corpus_main_script_is_r2l($Corpus->name, $_GET['isR2L']);

	break;
	
	
case 'updateVisibility':
	
	/* don't let users make their user-corpora visible */
	if (!$User->is_admin())
		break;
	
	if (!isset($_GET['newVisibility']))
		exiterror("Missing parameter for new Visibility setting.");

	update_corpus_visible($Corpus->name, $_GET['newVisibility']);

	break;
	
	
case 'updateConcScope':
		
	if (isset($_GET['newConcScope']))
	{
		if (!isset($_GET['newConcScopeUnit']) || '*words*' == $_GET['newConcScopeUnit'])
			$_GET['newConcScopeUnit'] = '';
		update_corpus_conc_scope($Corpus->name, $_GET['newConcScope'], $_GET['newConcScopeUnit']);
	}
	else
		exiterror("Missing parameter for concordance context-scope update.");

	break;

case 'updateInitExtContext':
	
	if (!isset($_GET['newInitExtContext']))
		exiterror("Missing parameter for new initial extended-context setting.");
	
	update_corpus_initial_extended_context($Corpus->name, $_GET['newInitExtContext']);

	break;
	
	
case 'updateMaxExtContext':
	
	if (!isset($_GET['newMaxExtContext']))
		exiterror("Missing parameter for new maximum extended-context setting.");
		
	update_corpus_max_extended_context($Corpus->name, $_GET['newMaxExtContext']);
	
	break;
	
	
	
	/*
	 * ==================
	 * ANNOTATION ACTIONS
	 * ==================
	 */
	
case 'updateAnnotationInfo':
	
	/* we have incoming annotation metadata to update */
	if (! isset($_GET['annotationHandle']))
		exiterror("Cannot update annotation info:  no handle specified.");
	if (! array_key_exists($_GET['annotationHandle'], list_corpus_annotations($Corpus->name)))
		exiterror('Cannot update ' . escape_html($_GET['annotationHandle']) . ' - not a real annotation!');
	
	update_all_annotation_info( $Corpus->name, 
								$_GET['annotationHandle'], 
								isset($_GET['annotationDescription']) ? $_GET['annotationDescription'] : NULL, 
								isset($_GET['annotationTagset'])      ? $_GET['annotationTagset']      : NULL, 
								isset($_GET['annotationURL'])         ? $_GET['annotationURL']         : NULL
								);
	
	$next_location = "index.php?ui=manageAnnotation";
	
	break;
	
	
case 'updateCeqlBinding':

	/* we have incoming values from the CEQL table to update */
	$changes = array();
	if (isset($_GET['setPrimaryAnnotation']))
		$changes['primary_annotation']   = ($_GET['setPrimaryAnnotation']   == '~~UNSET' ? NULL : $_GET['setPrimaryAnnotation']);
	if (isset($_GET['setSecondaryAnnotation']))
		$changes['secondary_annotation'] = ($_GET['setSecondaryAnnotation'] == '~~UNSET' ? NULL : $_GET['setSecondaryAnnotation']);
	if (isset($_GET['setTertiaryAnnotation']))
		$changes['tertiary_annotation']  = ($_GET['setTertiaryAnnotation']  == '~~UNSET' ? NULL : $_GET['setTertiaryAnnotation']);
	if (isset($_GET['setMaptable']))
		$changes['tertiary_annotation_tablehandle'] = ($_GET['setMaptable'] == '~~UNSET' ? NULL : $_GET['setMaptable']);
	if (isset($_GET['setComboAnnotation']))
		$changes['combo_annotation']     = ($_GET['setComboAnnotation']     == '~~UNSET' ? NULL : $_GET['setComboAnnotation']);
	
	if (! empty($changes))
		update_corpus_ceql_bindings($Corpus->name, $changes);

	$next_location = "index.php?ui=manageAnnotation";
	
	break;
	
	
	
	
	/*
	 * ===========================
	 * GLOSS VISUALISATION ACTIONS
	 * ===========================
	 */

case 'updateGloss':
	
	$annotations = list_corpus_annotations($Corpus->name);
	
	if (isset($_GET['updateGlossAnnotation']))
	{
		/* we overwrite the values in the global object too so that after
		 * we update the database, the global object still matches it
		 * (this is really just for convenience as the glocal object is not 
		 * used in this script) */
		switch($_GET['updateGlossShowWhere'])
		{
		case 'both':
			$Corpus->visualise_gloss_in_context = true;
			$Corpus->visualise_gloss_in_concordance = true;
			break;
		case 'concord':
			$Corpus->visualise_gloss_in_context = false;
			$Corpus->visualise_gloss_in_concordance = true;
			break;
		case 'context':
			$Corpus->visualise_gloss_in_context = true;
			$Corpus->visualise_gloss_in_concordance = false;
			break;
		default:
			$Corpus->visualise_gloss_in_context = false;
			$Corpus->visualise_gloss_in_concordance = false;
			break;			
		}
		if ($_GET['updateGlossAnnotation'] == '~~none~~')
			$_GET['updateGlossAnnotation'] = NULL;
		if (array_key_exists($_GET['updateGlossAnnotation'], $annotations) || empty($_GET['updateGlossAnnotation']))
		{
			$Corpus->visualise_gloss_annotation = $_GET['updateGlossAnnotation'];
			update_corpus_visualisation_gloss(  $Corpus->name, 
												$Corpus->visualise_gloss_in_concordance, 
												$Corpus->visualise_gloss_in_context, 
												$Corpus->visualise_gloss_annotation
												);
		}
		else
			exiterror("A non-existent annotation was specified to be used for glossing.");
	}
	else
		exiterror("Missing parameter; CQPweb aborts.");
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;
	
	
case 'updateTranslate':
	
	$s_attributes = list_xml_all($Corpus->name);
	
	if (isset($_GET['updateTranslateXML']))
	{
		/* for clarity - we do the business by overwriting the global object. */
		switch($_GET['updateTranslateShowWhere'])
		{
		case 'both':
			$Corpus->visualise_translate_in_context = true;
			$Corpus->visualise_translate_in_concordance = true;
			break;
		case 'concord':
			$Corpus->visualise_translate_in_context = false;
			$Corpus->visualise_translate_in_concordance = true;
			break;
		case 'context':
			$Corpus->visualise_translate_in_context = true;
			$Corpus->visualise_translate_in_concordance = false;
			break;
		default:
			$Corpus->visualise_translate_in_context = false;
			$Corpus->visualise_translate_in_concordance = false;
			break;
		}
		if ($_GET['updateTranslateXML'] == '~~none~~')
			$_GET['updateTranslateXML'] = NULL;
		if (array_key_exists($_GET['updateTranslateXML'], $s_attributes) || empty($_GET['updateTranslateXML']))
		{
			$Corpus->visualise_translate_s_att = $_GET['updateTranslateXML'];
			update_corpus_visualisation_translate($Corpus->name,
												  $Corpus->visualise_translate_in_concordance, 
												  $Corpus->visualise_translate_in_context, 
												  $Corpus->visualise_translate_s_att);
		}
		else
			exiterror("A non-existent s-attribute was specified to be used for translation.");
	}
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;

	
case 'updatePositionLabelAttribute':
	
	$s_attributes = list_xml_with_values($Corpus->name);
	unset($s_attributes['text_id']);

	if (isset($_GET['newPositionLabelAttribute']))
	{
		$show = true;
		$attribute = $_GET['newPositionLabelAttribute'];
		
		if ($attribute == '~~none~~')
		{
			$show = false;
			$attribute = NULL;
		}
		else if ( !isset($s_attributes[$attribute]) )
			exiterror("An invalid or non-existent s-attribute was specified for position labels.");

		/* so we know at this point that $attribute contains an OK s-att */
		update_corpus_visualisation_position_labels($Corpus->name, $show, $attribute);
	}
	else
		exiterror("No new s-attribute was specified for position labels.");
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;

//case 'updatePositionLabelAttribute':
//OLD VERSION, NOTREACHED
	
	$s_attributes = list_xml_all($Corpus->name);

	if (isset($_GET['newPositionLabelAttribute']))
	{
		$Corpus->visualise_position_labels = true;
		$Corpus->visualise_position_label_attribute = $_GET['newPositionLabelAttribute'];
		
		if ($Corpus->visualise_position_label_attribute == '~~none~~')
		{
			$Corpus->visualise_position_labels = false;
			$Corpus->visualise_position_label_attribute = NULL;
		}
		else if ( ! array_key_exists($Corpus->visualise_position_label_attribute, $s_attributes) )
		{
			exiterror("A non-existent s-attribute was specified for position labels.");
		}
		/* so we know at this point that $Corpus->visualise_position_label_attribute contains an OK s-att */ 
		update_corpus_visualisation_position_labels($Corpus->name, $Corpus->visualise_position_labels, $Corpus->visualise_position_label_attribute);
	}
	else
		exiterror("No new s-attribute was specified for position labels.");
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;
	
	
		
	/*
	 * =========================
	 * XML VISUALISATION ACTIONS
	 * =========================
	 */

	
case 'updateBreakOnPunc':
	
	if (!isset($_GET['break']))
		exiterror('No new value supplied for break-on-punctuation setting');
	else
		do_sql_query("update corpus_info set visualise_break_context_on_punc = " 
							. ($_GET['break'] === '1' ? '1' : '0') 
							. " where corpus = '{$Corpus->name}'");
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;

	
case 'addXmlVizConcJS':
	
	$field = 'visualise_conc_extra_js';
	/* INTENTIONAL case fall-thru for code efficiency....... */
	
case 'addXmlVizContextJS':

	/* this "if" sets up this case statement (does not take effect if we have fallen-through from above */
	if ('addXmlVizContextJS' == $script_action)
		$field = 'visualise_context_extra_js';

	$previous = preg_split('/~/', $Corpus->$field, -1, PREG_SPLIT_NO_EMPTY);
	
	if (isset($_GET["newFile"]))
	{
		if (! preg_match('/\.js$/', $_GET["newFile"]))
			exiterror("That file does not have a valid name for a client side code file.");
		if (file_exists('../jsc/'.$_GET["newFile"]))
		{
			$previous[] = $_GET["newFile"];
			sort($previous);
			$newval = escape_sql(implode('~', array_unique($previous)));
			do_sql_query("update corpus_info set $field = '$newval' where corpus = '{$Corpus->name}'");
		}
		else
			exiterror("The specified file does not exist in the folder for client side code.");
	}
	else
		exiterror("No filename supplied!");
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;

	
	/*
	 * a meta-coding note:
	 * 
	 * The following two cases for CSS largely duplicate the previous two cases for JS.
	 * I couldn't think of a way to factor out the commonalities that would not cause 
	 * AWFULNESS OF AWFULLITY in terms of the readability of the code.
	 * So we live with the duplication however distasteful it might be.
	 * 
	 * The "remove", however, was much easier factor out. So the casers are merged. See below.
	 */

	
case 'addXmlVizConcCSS':
	
	$field = 'visualise_conc_extra_css';
	/* INTENTIONAL case fall-thru for code efficiency....... */
	
case 'addXmlVizContextCSS':

	/* this "if" sets up this case statement (does not take effect if we have fallen-through from above */
	if ('addXmlVizContextCSS' == $script_action)
		$field = 'visualise_context_extra_css';
	
	$previous = preg_split('/~/', $Corpus->$field, -1, PREG_SPLIT_NO_EMPTY);
	
	if (isset($_GET["newFile"]))
	{
		if (! preg_match('/\.css$/', $_GET["newFile"]))
			exiterror("That file does not have a valid name for a CSS stylesheet file.");
		if (file_exists('../css/'.$_GET["newfile"]))
		{
			$previous[] = $_GET["newFile"];
			sort($previous);
			$newval = escape_sql(implode('~', array_unique($previous)));
			do_sql_query("update corpus_info set $field = '$newval' where corpus = '{$Corpus->name}'");
		}
		else
			exiterror("The specified file does not exist in the folder for CSS stylesheets.");
	}
	else
		exiterror("No filename supplied!");
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;


case 'removeXmlVizConcJS':	
case 'removeXmlVizConcCSS':
	
	preg_match ('/(CSS|JS)$/', $script_action, $m);
	$field = 'visualise_conc_extra_' . strtolower($m[1]);
	/* INTENTIONAL case fall-thru for code efficiency....... */

case 'removeXmlVizContextJS':
case 'removeXmlVizContextCSS':	

	/* this "if" sets up this case statement (does not take effect if we have fallen-through from above */
	if (preg_match ('/removeXmlVizContext(CSS|JS)$/', $script_action, $m))
		$field = 'visualise_context_extra_' . strtolower($m[1]);
	
	$previous = preg_split('/~/', $Corpus->$field, -1, PREG_SPLIT_NO_EMPTY);
	
	if (isset($_GET['fileRemove']))
	{
		if (false !== ($k = array_search($_GET['fileRemove'], $previous)))
		{
			unset($previous[$k]);
			$newval = escape_sql(implode('~', $previous));
			do_sql_query("update corpus_info set $field = '$newval' where corpus = '{$Corpus->name}'");
		}
	}
	else
		exiterror("No filename supplied!");
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;
	

case 'importXmlViz':
	
	if (! isset($_GET['templateViz']))
		exiterror("Badly formatted import-template request");
	
	$source_template = (int)$_GET['templateViz'];
	$target_corpus = $Corpus->name;
	
	xml_visualisation_import_from_template($source_template, $target_corpus);
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;
	

case 'createXmlViz':

	if (!isset($_GET['xmlVizElement']) || ! array_key_exists($_GET['xmlVizElement'], list_xml_all($Corpus->name)))
		exiterror("Non-valid XML attribute was specified!");
	
	if (!isset($_GET['xmlVizIsStartTag'], $_GET['xmlVizHtml'], $_GET['xmlVizUseInConc'], $_GET['xmlVizUseInContext']))
		exiterror("Malformed input for creation of an XML visualisation!");
	
	$condition = (empty($_GET['xmlVizCondition']) ? '' : $_GET['xmlVizCondition']);

	xml_visualisation_create(	$Corpus->name, 
								$_GET['xmlVizElement'], 
								(bool) $_GET['xmlVizIsStartTag'], 
								$condition, 
								$_GET['xmlVizHtml'], 
								(bool) $_GET['xmlVizUseInConc'], 
								(bool) $_GET['xmlVizUseInContext'],
								(bool) $_GET['xmlVizUseInDownload']
							);
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;
	
	
case 'updateXmlViz':

	if (!isset($_GET['vizToUpdate']))
		exiterror("Incomplete update request: cannot update unspecified visualisation.");

	/* update the html */

	if (isset($_GET['xmlVizRevisedHtml']))
		xml_visualisation_update_html($_GET['vizToUpdate'], $_GET['xmlVizRevisedHtml']);
	
	/* update use in concordance / context / downloadsettings */
	
	if (isset($_GET['xmlVizUseInConc']))
		xml_visualisation_use_in_concordance($_GET['vizToUpdate'], (bool) $_GET['xmlVizUseInConc']);
	if (isset($_GET['xmlVizUseInContext']))
		xml_visualisation_use_in_context($_GET['vizToUpdate'], (bool) $_GET['xmlVizUseInContext']);
	if (isset($_GET['xmlVizUseInDownload']))
		xml_visualisation_use_in_download($_GET['vizToUpdate'], (bool) $_GET['xmlVizUseInDownload']);
	
	$next_location = 'index.php?ui=manageVisualisation';

	break;
	
	
case 'deleteXmlViz':
	
	if (! isset($_GET['toDelete']))
		exiterror("No visualisation to delete was provided!");
		
	xml_visualisation_delete((int) $_GET['toDelete'], true);
	
	$next_location = 'index.php?ui=manageVisualisation';
	
	break;
	
	

	
	/*
	 * ==========================
	 * ADD DATA TO CORPUS ACTIONS
	 * ==========================
	 */
	
	
	
case 'extraSatt':
	
	if (!isset($_GET['addType']))
		exiterror("Missing parameter: the type of s-attribute to add was not specified.");
	
	/* 
	 * FALL-THRU: because these two actions share so much in common. 
	 * The GET parameters are either the same, or else can be ignored.
	 * The checks above are the only bits exclusive to S. 
	 */
	
case 'extraPatt':

	if (!isset($_GET['newAttHandle']))
		exiterror("No handle was specified for the new attribute.");
	$handle = cqpweb_handle_enforce($_GET['newAttHandle']);
	
	$description = (isset($_GET['newAttDesc'])   ? $_GET['newAttDesc']   : '');
	$tagset      = (isset($_GET['newAttTagset']) ? $_GET['newAttTagset'] : '');
	$url         = (isset($_GET['newAttUrl'])    ? $_GET['newAttUrl']    : '');
	
	$is_fs  = (isset($_GET['newAttIsFS']) ? (bool)$_GET['newAttIsFS'] : false);
		
	if (!isset($_GET['dataFile']))
		exiterror("No data file was specified for the new attribute.");
	$path = "{$Config->dir->upload}/{$_GET['dataFile']}";
	if (!is_file($path))
		exiterror("A nonexistent input file for the new attribute was specified.");

	/* now use an extra IF to differentiate P from S */ 
	
	if ('extraPatt' == $script_action)
	{
		add_new_annotation_to_corpus($Corpus->name, $handle, $description, $tagset, $url, $is_fs, $path);
		$next_location = 'index.php?ui=manageAnnotation';
	}
	else
	{
		if ('~newElement' == $_GET['addType'])
		{
			add_new_xml_to_corpus($Corpus->name, $handle, $handle, $description, METADATA_TYPE_NONE, $path);
		}
		else if (preg_match('/^value~(\w+)$/', $_GET['addType'], $m))
		{
			$att_family = $m[1];
			
			/* if the user has already given the prefix, don't add it. But if they haven't, add it. */
		
			if (!preg_match("/^{$att_family}_/", $handle))
				$handle = $att_family . '_' . $handle;
	
			$dt = (isset($_GET['datatype']) ? (int)$_GET["datatype"] : METADATA_TYPE_FREETEXT);
			if (!metadata_valid_datatype($dt))
				exiterror("Invalid attribute datatype supplied for new s-attribute!");
	
			add_new_xml_to_corpus($Corpus->name, $handle, $att_family, $description, $dt, $path);
		}
		else
			exiterror("An invalid type of s-attribute was specified to be added.");
		
		$next_location = 'index.php?ui=manageXml';
	}
	
	break;
	
	
	
case 'extraMeta':

	if (!isset($_GET['newFieldHandle']))
		exiterror("No handle was specified for the new metadata field.");
	$handle = cqpweb_handle_enforce($_GET['newFieldHandle']);
	
	if (!isset($_GET['newFieldDesc']))
		$description = '';
	else
		$description = $_GET['newFieldDesc'];
	
	if (!isset($_GET['dataFile']))
		exiterror("No data file was specified for the new metadata field.");
	$path = "{$Config->dir->upload}/{$_GET['dataFile']}";
	if (!is_file($path))
		exiterror("A nonexistent input file for the new metadata field was specfied.");

	$dt = (isset($_GET['datatype']) ? (int)$_GET["datatype"] : METADATA_TYPE_FREETEXT);
	if (!metadata_valid_datatype($dt))
		exiterror("Invalid attribute datatype supplied for new s-attribute!");
		
	if (!isset($_GET['target']))
		exiterror("Missing parameter: the type of s-attribute to add was not specified.");
	
	if ('--t' == $_GET['target'])
	{
		/* add field to text metadata */
		add_text_metadata_field($Corpus->name, $handle, $description, $dt, $path);
	}
	else
	{
		/* add field to idlink metadata */
		$att_handle = $_GET['target'];
		
		$xml = get_all_xml_info($Corpus->name);
		
		if (!array_key_exists($att_handle, $xml))
			exiterror("The specified IDLINK-type XML attribute does not exist.");
		if (METADATA_TYPE_IDLINK != $xml[$att_handle]->datatype)
			exiterror("The XML attribute you specified is not of IDLINK datatype.");
		
		add_idlink_field($Corpus->name, $att_handle, $handle, $description, $dt, $path);
	}

	$next_location = 'index.php?ui=manageMetadata';
	
	break;
	

default:

	exiterror("No valid action specified for corpus administration.");
	break;


} /* end the main switch */



if (isset($next_location))
	set_next_absolute_location($next_location);

cqpweb_shutdown_environment();

exit(0);


/* ------------- *
 * END OF SCRIPT *
 * ------------- */

