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
 * This script moves corpus settings from the OLD "settings.inc.php" storage method to the
 * NEW "in the database" storage method.
 * 
 * It also adds in information about existing-corpora s-attributes into the new xml-database.
 * 
 * This could in theory be part of the DB upgrade script, but for conceptual simplicity, we 
 * keep it separate.
 */


require('../lib/environment.php');

require('../lib/useracct-lib.php');
require('../lib/general-lib.php');
require('../lib/metadata-lib.php');
require('../lib/html-lib.php');
require('../lib/sql-lib.php');
require('../lib/exiterror-lib.php');


cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);

foreach (list_corpora() as $c_to_modify)
{
	insert_a_set_of_corpus_settings_to_the_db($c_to_modify);
	change_web_folder_to_a_symlink($c_to_modify);
	load_s_attributes_to_xml_table($c_to_modify);
	echo "\n";
}

echo "All done: pre-v3.2 corpora will now operate correctly in 3.2.\n",
	"Please note, you may need to manually change ownership of the corpus web-folder symlinks back to the user name that the WWW server runs under!\n",
	"Upgrade script exits.\n";

cqpweb_shutdown_environment();

/*
 * END OF SCRIPT
 */

function insert_a_set_of_corpus_settings_to_the_db($corpus)
{
	echo "Inserting corpus settings for $corpus into the DB ....\n";
	
	if (!is_file("../$corpus/settings.inc.php"))
	{
		echo "Corpus $corpus: no settings file found, skipping.\n";
		return;
	}

	/* import settings vars into local scope; then, for each one where we've added a column, set that column. */
	require("../$corpus/settings.inc.php");

	
	if (isset($corpus_cqp_name))
	{
		$corpus_cqp_name = mysql_real_escape_string($corpus_cqp_name);
		do_mysql_query("update corpus_info set cqp_name = '$corpus_cqp_name' where corpus='$corpus'");
	}
	if (isset($corpus_uses_case_sensitivity))
	{
		$val = ($corpus_uses_case_sensitivity?'1':'0');
		do_mysql_query("update corpus_info set uses_case_sensitivity = $val where corpus='$corpus'");
	}
	if (isset($corpus_title))
	{
		$corpus_title = mysql_real_escape_string($corpus_title);
		do_mysql_query("update corpus_info set title = '$corpus_title' where corpus='$corpus'");
	}
	if (isset($css_path))
	{
		$css_path = mysql_real_escape_string($css_path);
		do_mysql_query("update corpus_info set css_path = '$css_path' where corpus='$corpus'");
	}
	if (isset($corpus_main_script_is_r2l))
	{
		$val = ($corpus_main_script_is_r2l?'1':'0');
		do_mysql_query("update corpus_info set main_script_is_r2l = $val where corpus='$corpus'");
	}
	if (isset($context_s_attribute))
	{
		$context_s_attribute = mysql_real_escape_string($context_s_attribute);
		do_mysql_query("update corpus_info set conc_s_attribute = '$context_s_attribute' where corpus='$corpus'");
	}	
	if (isset($context_scope))
	{
		$context_scope = (int)$context_scope;
		do_mysql_query("update corpus_info set conc_scope = $context_scope where corpus='$corpus'");
	}
	if (isset($initial_extended_context))
	{
		$initial_extended_context = (int)$initial_extended_context;
		do_mysql_query("update corpus_info set initial_extended_context = $initial_extended_context where corpus='$corpus'");
	}
	if (isset($max_extended_context))
	{
		$max_extended_context = (int)$max_extended_context;
		do_mysql_query("update corpus_info set max_extended_context = $max_extended_context where corpus='$corpus'");
	}
	if (isset($visualise_gloss_in_concordance))
	{
		$val = ($visualise_gloss_in_concordance?'1':'0');
		do_mysql_query("update corpus_info set visualise_gloss_in_concordance = $val where corpus='$corpus'");
	}
	if (isset($visualise_gloss_in_context))
	{
		$val = ($visualise_gloss_in_context?'1':'0');
		do_mysql_query("update corpus_info set visualise_gloss_in_context = $val where corpus='$corpus'");
	}
	if (isset($visualise_gloss_annotation))
	{
		$visualise_gloss_annotation = mysql_real_escape_string($visualise_gloss_annotation);
		do_mysql_query("update corpus_info set visualise_gloss_annotation = '$visualise_gloss_annotation' where corpus='$corpus'");
	}	
	if (isset($visualise_translate_in_concordance))
	{
		$val = ($visualise_translate_in_concordance?'1':'0');
		do_mysql_query("update corpus_info set visualise_translate_in_concordance = $val where corpus='$corpus'");
	}
	if (isset($visualise_translate_in_context))
	{
		$val = ($visualise_translate_in_context?'1':'0');
		do_mysql_query("update corpus_info set visualise_translate_in_context = $val where corpus='$corpus'");
	}
	if (isset($visualise_translate_s_att))
	{
		$visualise_translate_s_att = mysql_real_escape_string($visualise_translate_s_att);
		do_mysql_query("update corpus_info set visualise_translate_s_att = '$visualise_translate_s_att' where corpus='$corpus'");
	}
	if (isset($visualise_position_labels))
	{
		$val = ($visualise_position_labels?'1':'0');
		do_mysql_query("update corpus_info set visualise_position_labels = $val where corpus='$corpus'");
	}
	if (isset($visualise_position_label_attribute))
	{
		$visualise_position_label_attribute = mysql_real_escape_string($visualise_position_label_attribute);
		do_mysql_query("update corpus_info set visualise_position_label_attribute = '$visualise_position_label_attribute' where corpus='$corpus'");
	}
	 
	echo "Done.....settings file for $corpus is now obsolete.\n";
	
}

function change_web_folder_to_a_symlink($corpus)
{
	$webdir = "../$corpus";
	$newdir = "../_.$corpus";
	
	/* errors suppressed in the below because we want our own error messages to be the visible ones.... */
	
	if (is_dir($webdir))
	{
		/* attempt to rename to archive format */
		$success = @rename($webdir, $newdir);
		/* attempt to create symlink */
		$success2 = ($success ? (bool) @symlink("exe", $webdir) : false);
		if ($success2)
			@chmod($webdir, 0775);
		
		/* if we renamed to archive format but couldn't create the symlink, put the folder back. */
		if ($success && ! $success2)
			@rename($newdir, $webdir);

		if ($success && $success2)
			echo "Web directory for $corpus changed to symlink; original archived at '_.$corpus' (which you can delete at your leisure).\n";
		else
			echo "Could not replace the web directory for $corpus with a symlink (probably due to filesystem permissions). You may need to do this manually.\n";
	}
}


function load_s_attributes_to_xml_table($corpus)
{
	global $Config;
	
	/* By the time we get here, we have already run the corpus_info update, so we can get the cqp name from there */ 
	$c_info = get_corpus_info($corpus);
	if (empty($c_info))
		exit("Critical error: invalid corpus $corpus.\n");
	
	$cmd = "{$Config->path_to_cwb}cwb-describe-corpus -r \"{$Config->dir->registry}\" -s {$c_info->cqp_name}";
	$results = array();
	exec($cmd, $results);
	
	/* keep track of attributes we've seen before */
	$seen_atts = array();
	
	foreach ($results as $line)
	{
		/* first, filter out lines that DO NOT refer to s-attributes */
		if ( 1 > preg_match('/s-ATT\s+(\w+)\s+\d+\s+regions?(\s*\(with\s+annotations\)|\s*)/', $line, $m))
			continue;
		$m = array_map('trim', $m);

		/* now, assemble the fields we need for the DB entry: handle, att_family, datatype.
		 * The description is the same as the handle for these re-installed types. */
		
		$description = $handle = $m[1];
		
		if ($m[2] == '(with annotations)')
		{
			/* DATATYPE is freetext, unless it's text_id, in which case unique id */
			$dt = ($handle == 'text_id' ? METADATA_TYPE_UNIQUE_ID : METADATA_TYPE_FREETEXT);
		}
		else
			$dt = METADATA_TYPE_NONE;
		
		/* att family is a little more difficult to work out. Family head = default.  */
		$att_family = $handle;
		if (false !== strpos($handle, '_'))
		{
			list($poss_fam) = explode('_', $handle);
			if (in_array($poss_fam, $seen_atts))
				$att_family = $poss_fam;
		}
		/* nb this assumes that the order of s-att mention is always text before text_id. That SHOULD be correct. */
		
		$sql = "insert into xml_metadata 
					(  corpus,    handle,    att_family,    description,   datatype) 
						values 
					('$corpus', '$handle', '$att_family', '$description', $dt)
			";
		do_mysql_query($sql);
		
		$seen_atts[] = $handle;
	}
	
	echo "Done .... ", count($seen_atts), " s-attributes have been detected in ``$corpus'', and added to the database.\n";

}

