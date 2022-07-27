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
 * Subcorpus-act: carries out actions related to subcorpus creation, deletion etc.
 * 
 * This script does its action, then calls the index page with a subcorpus function for whatever is to be displayed next.
 * 
 */


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');


require('../lib/environment.php');


require('../lib/cache-lib.php');
require('../lib/db-lib.php');
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/useracct-lib.php');
require('../lib/html-lib.php');
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/xml-lib.php');
require('../lib/scope-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/cqp.php');

$Corpus = $User = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);


if (!isset($_GET['scriptMode']))
	exiterror('No scriptmode specified for subcorpus-act.php!');
else
	$script_action = $_GET['scriptMode'];


/* this variable is allowed to be missing */
if (isset($_GET['subcorpusNewName']))
	$subcorpus_name = escape_sql($_GET['subcorpusNewName']);







/* if "Cancel" was pressed on a form, do nothing, and just go straight to the index */
if (isset ($_GET['action']) && $_GET['action'] == 'Cancel')
{
	set_next_absolute_location('index.php?ui=subcorpus');
	cqpweb_shutdown_environment();
	exit();
}


/* 
 * A note on two variables.
 * 
 * $list_of_texts is always an array of text IDs.
 * $string_of_texts is always a space-delimited imploded string derived from, or used to derive, such an array. 
 */



switch ($script_action)
{

case 'create_from_manual':
	
	/* what type of ID has been manually entered? */
	if (empty($_GET['idType']))
		$id_type = 'text_id';
	else
		$id_type = $_GET['idType'];
	
	if ('text_id' == $id_type)
	{
		$item_type       = 'text';
		$item_identifier = 'id';
	}
	else
	{
		if ( !($idlink = get_xml_info($Corpus->name, $id_type)) || METADATA_TYPE_IDLINK != $idlink->datatype )
			exiterror("Invalid ID-link specified - cannot create subcorpus!");
		$item_type       = $idlink->att_family;
		$item_identifier = preg_replace("/^{$idlink->att_family}_/", '', $idlink->handle);
	}

	if (!isset($_GET['subcorpusListOfIds']))
		exiterror("List of ID codes has not been supplied - cannot create subcorpus!");
// 	{
// 		/* effectively do not allow a submission (but sans error message) if the field is empty */
// 		set_next_absolute_location('index.php?ui=subcorpus&subcorpusCreateMethod=manual&subcorpusFunction=define_subcorpus'
// 			. "&subcorpusNewName=" . urlencode($subcorpus_name));
// 	}
	else
	{
		subcorpus_admin_check_name($subcorpus_name, url_absolutify('index.php?subcorpusBadName=y&' . url_printget()));

// TODO function-ise, get_list_and_string_of_ids()???????? ... note this code is duplicated below. 
		/* delete nonword nonspace for data safety; replace comma/space for standardisation */ 
		$string_of_ids = trim(preg_replace('/[\s,]+/', ' ', preg_replace('/[^\w\s,]/', '', $_GET['subcorpusListOfIds'])));
		$list_of_ids = explode(' ', $string_of_ids);

		if (empty($list_of_ids))
			exiterror("List of ID codes is empty - cannot create subcorpus!");

		/* get a list of the ID codes that are not real text/idlink ids */
		if ('text_id' == $id_type)
			$errors = check_textlist_valid($list_of_ids, $Corpus->name);
		else 
			$errors = check_id_list_valid($Corpus->name, $id_type, $list_of_ids);

		if (!empty($errors))
		{
			$errstr = implode(' ', $errors);
			// TODO use the http_build_query function in order that these variables get url-encoded. 
			set_next_absolute_location("index.php?ui=subcorpus&subcorpusCreateMethod=manual~$id_type"
				. "&subcorpusListOfIds=$string_of_ids&subcorpusFunction=define_subcorpus"
				. "&subcorpusNewName=$subcorpus_name&subcorpusBadIds=$errstr");
			break;
		}
		else
		{
			$sc = Subcorpus::create($subcorpus_name, $Corpus->name, $User->username);
			$sc->populate_from_list($item_type, $item_identifier, $list_of_ids);

			if ($sc->size_tokens() < 1)
				exiterror("This subcorpus does not contain any text. It can't be created.");
			$sc->save();

			set_next_absolute_location('index.php?ui=subcorpus');
		}
	}
	break;



case 'create_from_metadata':

	$restriction = Restriction::new_from_url($_SERVER['QUERY_STRING']);

	if (!$restriction)
	{
		/* effectively do not allow a submission (but sans error message) if no cats selected */
		set_next_absolute_location('index.php?ui=subcorpus&subcorpusCreateMethod=metadata&subcorpusFunction=define_subcorpus'
			. "&subcorpusNewName=$subcorpus_name");
		break;
	}

//TODO use a proper parameter, and a button not a submit.
	if ($_GET['action'] == 'Get list of texts') /* little trick with the button text! */
	{
		/* then we don't want to actually store it, just display a new form */
// temp code ....
if (false === ($lll = ($restriction->get_item_list())))
	exiterror("Sorry, no text list can be dsplayed for the type of metadata restriction you have specified.");
$string_of_texts_to_show_in_form = implode(' ', $lll);
//		$string_of_texts_to_show_in_form = implode(' ', $restriction->get_item_list());
		$header_cell_text = 'Viewing texts that match the following metadata restrictions: <br>' . $restriction->print_as_prose();
		$field_to_show = $Corpus->primary_classification_field;

		$longval_id = longvalue_store($string_of_texts_to_show_in_form . '~~~~~' . escape_sql($header_cell_text) . '~~~~~' . $field_to_show);

		set_next_absolute_location("index.php?ui=subcorpus&subcorpusFunction=list_of_files&listOfFilesLongValueId=$longval_id");
	}
	else
	{
		subcorpus_admin_check_name($subcorpus_name, 
			url_absolutify('index.php?ui=subcorpus&subcorpusFunction=define_subcorpus&subcorpusCreateMethod=metadata&subcorpusNewName='
				. urlencode(escape_html($subcorpus_name)) . '&subcorpusBadName=y&' . $restriction->url_serialise() )
				);

		$sc = Subcorpus::create($subcorpus_name, $Corpus->name, $User->username);
		$sc->populate_from_restriction($restriction);
		if (!$sc->save())
			exiterror("The conditions you have specified would create an empty subcorpus; this cannot be done.");

		set_next_absolute_location('index.php?ui=subcorpus');
	}
	break;






case 'create_from_metadata_scan':

	/* set up variables in memory, manipulate GET, and then re-Location to the index to render the list */

	if (!isset($_GET['metadataFieldToScan']))
		exiterror('No search field specified!');
	else
		$field_to_show = $field = escape_sql($_GET['metadataFieldToScan']);

	if (!isset($_GET['metadataScanString']))
		exiterror('No search target specified!');
	else
		$orig_value = $value = escape_sql($_GET['metadataScanString']);
	
	$header_cell_text = 'Viewing texts where <em>' . expand_text_metadata_field($Corpus->name, $field);
	
	switch($_GET['metadataScanType'])
	{
	case 'begin':
		$value .= '%';
		$header_cell_text .= '</em> begins with';
		break;
		
	case 'end':
		$value = '%' . $value;
		$header_cell_text .= '</em> ends with';
		break;
		
	case 'contain':
		$value = '%' . $value . '%';
		$header_cell_text .= '</em> contains';
		break;
		
	case 'exact':
		/* note - if nothing is specified, assume exact match required */
	default:
		$header_cell_text .= '</em> matches exactly';
		break;
	}
	
	$header_cell_text .= ' &ldquo;' . $orig_value . '&rdquo;';
	
	$result = do_sql_query("select text_id from text_metadata_for_{$Corpus->name} where $field like '$value'");
	
	$string_of_texts_to_show_in_form = '';
	
	while ($o = mysqli_fetch_object($result))
		$string_of_texts_to_show_in_form .= ' ' . $o->text_id;
	
	$string_of_texts_to_show_in_form = trim($string_of_texts_to_show_in_form);

	$longval_id = longvalue_store($string_of_texts_to_show_in_form . '~~~~~' . escape_sql($header_cell_text) . '~~~~~' . $field_to_show);
	
	set_next_absolute_location("index.php?ui=subcorpus&subcorpusFunction=list_of_files&listOfFilesLongValueId=$longval_id");
	break;




case 'create_from_query_texts':

	if (!isset($_GET['savedQueryToScan']))
	{
		/* effectively do not allow a submission (but sans error message) if no query specified */
		set_next_absolute_location(
			"index.php?ui=subcorpus&subcorpusCreateMethod=query&subcorpusFunction=define_subcorpus&subcorpusNewName=$subcorpus_name");
		break;
	}

	$create = ($_GET['action'] != 'Get list of texts');
	$qname = escape_sql($_GET['savedQueryToScan']);

	if ($create)
	{
		subcorpus_admin_check_name($subcorpus_name, 
			url_absolutify('index.php?ui=subcorpus&subcorpusBadName=y&subcorpusCreateMethod='
				. 'query&subcorpusFunction=define_subcorpus&' 
				. url_printget()));

		$sc = Subcorpus::create($subcorpus_name, $Corpus->name, $User->username);
		$sc->populate_from_query_texts($qname);
		$sc->save();

		set_next_absolute_location('index.php?ui=subcorpus');
	}
	else
	{
		$header_cell_text = "Viewing texts in saved query &ldquo;$qname&rdquo;";
		
		$cqp = get_global_cqp();
		
		$grouplist = $cqp->execute("group $qname match text_id");
		
		$texts = array();
		foreach($grouplist as &$g)
			list($texts[]) = explode("\t", $g);

		$string_of_texts_to_show_in_form = implode(' ', $texts);

		$longval_id = longvalue_store($string_of_texts_to_show_in_form . '~~~~~' . $header_cell_text . '~~~~~' . $Corpus->primary_classification_field);

		set_next_absolute_location("index.php?ui=subcorpus&subcorpusFunction=list_of_files&listOfFilesLongValueId=$longval_id");
	}
	break;



case 'create_from_query_regions':

	if (!isset($_GET['savedQueryToScan']))
	{
		/* effectively do not allow a submission (but sans error message) if no query specified */
		set_next_absolute_location(
			"index.php?ui=subcorpus&subcorpusCreateMethod=query_regions&subcorpusFunction=define_subcorpus&subcorpusNewName=$subcorpus_name");
		break;
	}
	$qname = escape_sql($_GET['savedQueryToScan']);

	if (!isset($_GET['xmlAtt']))
		exiterror("No XML attribute was specified.");
	if (!xml_exists($_GET['xmlAtt'], $Corpus->name))
		exiterror("Nonexistant XML attribute specified.");

	subcorpus_admin_check_name($subcorpus_name, 
		url_absolutify('index.php?ui=subcorpus&subcorpusBadName=y&subcorpusCreateMethod='
			. 'query_regions&subcorpusFunction=define_subcorpus&' 
			. url_printget()));

	$sc = Subcorpus::create($subcorpus_name, $Corpus->name, $User->username);
	$sc->populate_from_query_xml($qname, $_GET['xmlAtt']);
	$sc->save();

// 	set_next_absolute_location('index.php?ui=subcorpus');

	break;



case 'create_inverted':

	if (empty($_GET['subcorpusToInvert']))
		exiterror("You must specify a subcorpus to invert!");

	subcorpus_admin_check_name($subcorpus_name, 
		url_absolutify('index.php?ui=subcorpus&subcorpusBadName=y&subcorpusCreateMethod='
			. 'invert&subcorpusFunction=define_subcorpus&' 
			. url_printget()));

	$sc = Subcorpus::create($subcorpus_name, $Corpus->name, $User->username);
	if (! $sc->populate_from_inverting($_GET['subcorpusToInvert']))
		exiterror("You can (at present) only use the invert-subcorpus function with a subcorpus made up of a set of texts. ");
		// TODO see the SC::populate_from_invert comments for why; this MUST change later, we want to be able to invert ANY kind of Sc. 
	$sc->save();

	set_next_absolute_location('index.php?ui=subcorpus');
	break;



case 'create_sc_per_text':

	$text_list = list_texts_in_corpus($Corpus->name);

	if (count($text_list) > 100)
		exiterror('This corpus contains more than 100 texts, so you cannot use the one-subcorpus-per-text function!');
	if (count($text_list) == 1)
		exiterror('This corpus contains only one text, so you cannot use the one-subcorpus-per-text function!');

	foreach($text_list as $id)
	{
		$sc = Subcorpus::create($id, $Corpus->name, $User->username);
		$sc->populate_from_list('text', 'id', array($id));
		$sc->save();
	}

	set_next_absolute_location('index.php?ui=subcorpus');
	break;


case 'copy':

	if (! isset($_GET['subcorpusToCopy']) )
		exiterror('No subcorpus was specified for copying!');
	if (! isset($subcorpus_name))
		exiterror('No name was supplied for the new subcorpus!');

	if (preg_match('/\W/', $subcorpus_name) > 0)
	{
		/* call the index script with a rejected name */
		set_next_absolute_location('index.php?ui=subcorpus&subcorpusBadName=y&' . url_printget());
		break;
	}

	if (!($copy_src = Subcorpus::new_from_id((int)$_GET['subcorpusToCopy'])))
		exiterror('The subcorpus you want to copy does not seem to exist!');

	if ($subcorpus_name == $copy_src->name)
		exiterror("It's not possible to create a copy of a subcorpus with the same name as the original ({$copy_src->name}).");

	/* What this call does: clone the subcorpus, then flag as unsaved to give it a new ID, then save. */
	Subcorpus::duplicate($copy_src, $subcorpus_name);
	/* We just throw away the return value, since we don't do anything with it. */

	set_next_absolute_location('index.php?ui=subcorpus');
	break;


case 'delete':

	if (isset($_GET['subcorpusToDelete']))
	{
		if ($delenda = Subcorpus::new_from_id((int)$_GET['subcorpusToDelete']))
		{
			if (!$delenda->owned_by_user())
				exiterror("You cannot delete this subcorpus, it is not linked to your user account.");
			$delenda->delete();
		}

		set_next_absolute_location('index.php?ui=subcorpus');
	}
	else
		exiterror('No subcorpus specified to delete!');	
	break;



case 'remove_texts':

	if (! (isset($_GET['subcorpusToRemoveFrom']) ) )
		exiterror('No subcorpus was specified for text removal!');
	else
		$subcorpus_from = Subcorpus::new_from_id($_GET['subcorpusToRemoveFrom']);

	if (!$subcorpus_from)
		exiterror("The specified subcorpus does not seem to exist.");
	if (!$subcorpus_from->owned_by_user())
		exiterror("You cannot modify a subcorpus that your user account does not own.");

	preg_match_all('/dT_([^&]*)=1/', $_SERVER['QUERY_STRING'], $m, PREG_PATTERN_ORDER);

	if (!empty($m[1]))
	{
		$list_of_texts = array_unique(array_map('cqpweb_handle_enforce', $m[1]));
		$subcorpus_from->modify_remove_items($list_of_texts);
		$subcorpus_from->save();
	}
	else
		exiterror("You didn't specify any files to remove from this subcorpus! Go back and try again.");

	set_next_absolute_location('index.php?ui=subcorpus');
	break;


case 'add_texts':

	if (! (isset($_GET['subcorpusToAddTo']) ) )
		exiterror('No subcorpus ID specified for adding texts to!');

	$subcorpus_to = Subcorpus::new_from_id($_GET['subcorpusToAddTo']);

	if (!$subcorpus_to)
		exiterror('The subcorpus you specified does not seem to exist.');
	if (! $subcorpus_to->owned_by_user())
		exiterror("You cannot modify this subcorpus, it is not linked to your user account.");

	if (!isset($_GET['subcorpusListOfIds']))
		; /* no texts specified, so don't do anything. (Will redirect back to the subcorpus UI.) */
	else
	{
		/* delete nonword nonspace for data safety; replace comma/space for standardisation */ 
		$string_of_ids = trim(preg_replace('/[\s,]+/', ' ', preg_replace('/[^\w\s,]/', '', $_GET['subcorpusListOfIds'])));
		$list_of_ids = explode(' ', $string_of_ids);

		/* get a list of text names that are not real text ids */
		$errors = check_textlist_valid($list_of_ids, $Corpus->name);

		if (!empty($errors))
		{
			$errstr = urlencode(implode(' ', $errors));
			set_next_absolute_location("index.php?ui=subcorpus&subcorpusListOfIds=$string_of_ids"
				. "&subcorpusFunction=add_texts_to_subcorpus&subcorpusToAddTo={$subcorpus_to->id}"
				. "&subcorpusBadIds=$errstr");
			break;
		}

		/* OK, we now know the list of names is OK */
		$subcorpus_to->modify_add_items($list_of_ids);
		$subcorpus_to->save();
	}

	set_next_absolute_location('index.php?ui=subcorpus');
	break;


case 'process_from_text_list':

	if (isset($_GET['processTextListAddAll']))
	{
		/* "include all texts" was ticked */
		/* the actual list of texts may be too long for HTTP GET, so is stored in the longvalues table */
		$list_of_texts = array_unique(explode(' ', preg_replace('/\W+/', ' ', longvalue_retrieve($_GET['processTextListAddAll']))));
	}
	else
	{
		/* "include all" not ticked: refer to individual checkboxes. */
		if (0 < preg_match_all('/aT_([^&]*)=1/', $_SERVER['QUERY_STRING'], $m, PREG_PATTERN_ORDER))
			$list_of_texts = array_map('cqpweb_handle_enforce', array_unique($m[1]));
		else
			$list_of_texts = [];
	}
	if (empty($list_of_texts))
		exiterror("You didn't specify any texts to add to this subcorpus! Go back and try again.");

	/* work out if we're adding or creating */

	if ( (!isset($_GET['subcorpusToAddTo'],$subcorpus_name)) )
		exiterror('No subcorpus name specified for adding these texts to!');

	if ($_GET['subcorpusToAddTo'] !== '!__NEW')
	{
		/* add to existing */
		$subcorpus_to = Subcorpus::new_from_id($_GET['subcorpusToAddTo']);
		if (!$subcorpus_to)
			exiterror('The subcorpus you specified does not seem to exist.');
		if (! $subcorpus_to->owned_by_user())
			exiterror("You cannot modify this subcorpus, it is not linked to your user account.");

		$subcorpus_to->modify_add_items($list_of_texts);
		$subcorpus_to->save();
	}
	else
	{
		if (! cqpweb_handle_check($subcorpus_name, HANDLE_MAX_SAVENAME))
			exiterror('The subcorpus name you specified is invalid. Please go back and revise!');
		$subcorpus_to = Subcorpus::create($subcorpus_name, $Corpus->name, $User->username);

		/* note we get the obj to do che3ckign for us just in case... */
		$subcorpus_to->populate_from_list('text', 'id', $list_of_texts);
		$subcorpus_to->save();
	}

	set_next_absolute_location('index.php?ui=subcorpus');
	break;


case 'compile_freqtable':

	if (!isset($_GET['compileSubcorpus']))
		exiterror('No subcorpus was specified - frequency tables cannot be compiled!!');

	if (false == ($sc = Subcorpus::new_from_id($_GET['compileSubcorpus'])))
		exiterror('A non-existent subcorpus was specified - frequency tables cannot be compiled!!');
	else
	{
		if ($User->max_freqlist() < $sc->size_tokens())
	 		exiterror('You do not have the necesssary permission to create a frequency list for this subcorpus; '
	 				. 'it is too big (' . $sc->print_size_tokens() . ' words).');

	 	/* otherwise... */
		$qs = QueryScope::new_by_unserialise("{$sc->id}");
	 	subsection_make_freqtables($qs);
	}

	set_next_absolute_location('index.php?ui=subcorpus');
	break;


default:
	exiterror('Unrecognised scriptmode for subcorpus-act.php!');


} 
/* end of big switch. Some paths through it exit; the ones that break are ready to shutdown. */


/* final actions.... */
cqpweb_shutdown_environment();
exit();



/* ---------- *
 * END SCRIPT *
 * ---------- */




/**
 * Checks a subcorpus name parameter for validity: redirects to specified URL & exits if the test is failed.
 * 
 * @param string $subcorpus_name
 * @param string $location_url
 */
function subcorpus_admin_check_name($subcorpus_name, $location_url)
{
	if (empty($subcorpus_name) || 0 < preg_match('/\W/', $subcorpus_name) || 200 < strlen($subcorpus_name))
	{
		set_next_absolute_location($location_url );
		cqpweb_shutdown_environment();
		exit();
	}
}


