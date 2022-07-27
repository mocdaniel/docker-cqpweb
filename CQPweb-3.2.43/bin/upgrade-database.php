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
 * This script updates the structure of the database to match the version of the code.
 * 
 * It is theoretically always safe to run this script because, if the db structure is up to date, it won't do anything.
 * 
 * Note that, up to and including 3.0.16, it was assumed that DB changes would be done manually. 
 * 
 * So, all manual changes up to 3.0.16 MUST be applied before running this script.
 */


require('../lib/environment.php');

/* include function library files */
require('../lib/general-lib.php');
require('../lib/sql-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');

require('../bin/cli-lib.php');




/* ============================================================
 * VARS THAT NEED UPDATING EVERY TIME A NEW VERSION IS PILED ON 
 */

		/* the most recent database version: ie the last version whose release involved a DB change */
		$last_changed_version = '3.2.40';
		/* the 3.2 series is unlikely to make any further changes. */
		
		/* 
		 * versions where there is no change. Array of old_version => version that followed. 
		 * E.g. if there were no changes between 3.1.0 and 3.1.1, this array should contain
		 * '3.1.0' => '3.1.1', so the function can then reiterate and look for changes between
		 * 3.1.1 and whatever follows it.
		 */
		$versions_where_there_was_no_change = array(
			'3.1.0'  => '3.1.1',
			'3.1.1'  => '3.1.2',
			'3.1.2'  => '3.1.3',
			'3.1.5'  => '3.1.6',
			'3.1.6'  => '3.1.7',
			'3.1.10' => '3.1.11',
			'3.1.11' => '3.1.12',
			'3.1.12' => '3.1.13',
			'3.1.13' => '3.1.14',
			'3.1.14' => '3.1.15',
			'3.1.15' => '3.1.16',
			'3.2.0'  => '3.2.1',
			'3.2.2'  => '3.2.3',
			'3.2.8'  => '3.2.9',
			'3.2.10' => '3.2.12',
			'3.2.13' => '3.2.14',
			'3.2.15' => '3.2.17',
			'3.2.18' => '3.2.19',
			'3.2.20' => '3.2.21',
			'3.2.22' => '3.2.23',
			'3.2.25' => '3.2.26',
			'3.2.27' => '3.2.31',
			'3.2.32' => '3.2.34',
			'3.2.37' => '3.2.39',
			);

/* END COMPULSORY UPDATE VARS
 * ==========================
 */



/* ============ * 
 * begin script * 
 * ============ */

/* a hack to make debug printing & mysql connection work */
include("../lib/config.inc.php");
$Config = new NotAFullConfig();
$Config->Api                     = false;
$Config->print_debug_messages    = false;
$Config->client_is_disconnected  = false;
$Config->debug_messages_textonly = true;
$Config->all_users_see_backtrace = false;
$Config->mysql_utf8_set_required = (isset($mysql_utf8_set_required) && $mysql_utf8_set_required);
$Config->mysql_schema  = $mysql_schema;
$Config->mysql_webpass = $mysql_webpass;
$Config->mysql_webuser = $mysql_webuser;
$Config->mysql_server  = $mysql_server;

/* instead of cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP , RUN_LOCATION_CLI); ....... */
$Config->mysql_link = create_sql_link();

do_sql_query("set time_zone = '+00:00'"); /* to allow us to use timestamp 1970-01-01 00:00:01 as a zero value with confidence */


/* begin by checking for a really old database version ... */

$greater_than_3_0_16 = false;

$result = do_sql_query('show tables');

while ($o = mysqli_fetch_row($result))
{
	if ($o[0] == 'system_info')
	{
		$greater_than_3_0_16 = true;
		break;
		/* if this table is NOT present, then we have a very old database version. */
	}
}

if (!$greater_than_3_0_16)
{
	echo "Database version is now at < 3.1.0. Database will now be upgraded to 3.1.0...\n";
	upgrade_db_version_from('3.0.16');
}

while (0 > version_compare($version = get_sql_cqpwebdb_version(), $last_changed_version))
{
	echo "Current DB version is $version ; target version is $last_changed_version .  About to upgrade....\n";
	upgrade_db_version_from($version);
}

echo "CQPweb database is now at or above the most-recently-changed version ($last_changed_version). Upgrade complete!\n";

$Config->mysql_link->close();

exit(0);





/* --------------------------------------------------------------------------------------------------------- */





function upgrade_db_version_from($oldv)
{
	global $versions_where_there_was_no_change;

	if (isset($versions_where_there_was_no_change[$oldv]))
		upgrade_db_version_note($versions_where_there_was_no_change[$oldv]);
	else
	{
		$func = 'upgrade_' . str_replace('.','_',$oldv);
		$func();
	}
}


function upgrade_db_version_note($newv)
{
	do_sql_query("update system_info set value = '$newv' where setting_name = 'db_version'");
	do_sql_query("update system_info set value = '" .  date('Y-m-d H:i') . "' where setting_name = 'db_updated'");
}



/* --------------------------------------------------------------------------------------------------------- */




/* 3.2.39->3.2.40 */
function upgrade_3_2_39()
{
	$sql = array();
	$run = array();
	
	$result = do_mysql_query("select * from xml_metadata where `datatype` = " . METADATA_TYPE_IDLINK);
	while ($o = $result->fetch_object())
	{
		$tbl = "idlink_xml_{$o->corpus}_{$o->handle}";
		if (get_sql_value("show tables like '$tbl'")) /* if the idlink table exists ... */
		{
			if (false === get_sql_value("show columns from `$tbl` like '__DATA'")) /* if the table doesn't exist */
				$sql[] = "alter table `$tbl` add column `__DATA` longblob";
			$run[] = "php execute-cli.php update_idlink_item_size_data {$o->corpus} {$o->handle}";
		}
	}
	
	foreach ($sql as $q)
		do_sql_query($q);

	upgrade_db_version_note('3.2.40');
	
	if (empty($run))
		return;
	
	echo <<<END_OF_MESSAGE

	======================================================================
	Now running data insertion processes for new columns.
	======================================================================


END_OF_MESSAGE;
	
	sleep(1);
	
	foreach($run as $r)
		system($r);
	
	echo <<<END_OF_MESSAGE

	======================================================================
	Data insertion processes complete.
	======================================================================


END_OF_MESSAGE;
	
	
}



/* 3.2.36->3.2.37 */
function upgrade_3_2_36()
{
	echo <<<END_OF_MESSAGE

	======================================================================
	Please note, some of the operations in this upgrade are time-consuming
	(especially if your installation has a long history).  
	It is recommended that you run this upgrade in a quiet period, and
	(ideally) with CQPweb in "switched-off" mode if it is accessible to 
	users other than you.
	======================================================================


END_OF_MESSAGE;
	
	if (!ask_boolean_question("Do you wish to continue running the upgrade now?"))
	{
		echo "OK, we'll leave the database at version 3.2.36.\n\n";
		exit;
	}
	
	/*
	 * This upgrade imposes across-the-board standardisationn of column-level charset / collation,
	 * allowing the utf8mb3 -> utf8mb4 conversion to be done reasonbly mechanistically.
	 * While we're at it, some other datatype cleanup has been imposed.
	 */
	
	/* before implementing qmode as an SQL enum type, check that all existing values are valid. */
	$qmode_enums = ['saved_queries'=>'query_mode', 'query_history'=>'query_mode'];
	$qmode_vals = ['cqp', 'sq_case', 'sq_nocase', 'uploaded'];
	foreach ($qmode_enums as $t => $col)
	{
		$any_bad = false;
		foreach(list_sql_values("select distinct(`$col`) from `$t`") as $v)
			if (!in_array($v, $qmode_vals))
			{
				echo "Bad value for $col in $t: $v\n";
				$any_bad = true;
			}
		if ($any_bad)
		{
			echo "\nPossible values for query mode are:    cqp   sq_case    sq_nocase    uploaded\n\n"
				, "Due to the presence of bad query-mode values in the present table ($t),\n"
				, "the database cannot be upgraded at present. Please check your database,\n"
				, "correct any erroneous values, and try again.\n\n ";
			exit;
		}
	}
	
	/* everything is OK, so go go with the enums! */
	foreach ($qmode_enums as $t => $col)
		do_sql_query("alter table `$t` modify column `$col` enum ('cqp', 'sq_case', 'sq_nocase', 'uploaded') NOT NULL");
		
	
	

	/* system messages may or may not not have a primary key */
	if (0 < mysqli_num_rows(do_sql_query("show indexes from system_messages where Key_name = 'PRIMARY'")))
		do_sql_query('alter table system_messages drop primary key');
	
	/* now, on to this version's more generic changes. */

		
	$sql = array(

			/*
			 * This upgrade imposes across-the-board standardisationn of column-level charset / collation,
			 * allowing the utf8mb3 -> utf8mb4 conversion to be done reasonably mechanistically.
			 * While we're at it, other datatype cleanup has been imposed.
			 */
			'alter table `annotation_mapping_tables` modify column `handle` varchar(255) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `annotation_mapping_tables` modify column `name` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `annotation_mapping_tables` modify column `mappings` text CHARSET utf8 COLLATE utf8_bin',
			
			'alter table `annotation_metadata` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `annotation_metadata` modify column `handle` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `annotation_metadata` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `annotation_metadata` modify column `tagset` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `annotation_metadata` modify column `external_url` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',

			'alter table `annotation_template_info` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `annotation_template_info` modify column `primary_annotation` varchar(20) CHARSET ascii COLLATE ascii_bin default NULL',

			'alter table `annotation_template_content` modify column `handle`  varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `annotation_template_content` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `annotation_template_content` modify column `tagset` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `annotation_template_content` modify column `external_url` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `annotation_template_content` modify column `order_in_template` smallint unsigned NOT NULL default 0',

			'alter table `corpus_alignments` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin',
			'alter table `corpus_alignments` modify column `target` varchar(20) CHARSET ascii COLLATE ascii_bin',
			
			'alter table `corpus_categories` modify column `label` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',

			/* corpus_info is a big un. Let's deal with the integers first. */	
			'alter table `corpus_info` modify column `size_tokens` bigint unsigned NOT NULL default 0',
			'alter table `corpus_info` modify column `size_types` bigint unsigned NOT NULL default 0',
			'alter table `corpus_info` modify column `size_texts` int unsigned NOT NULL default 0',
			'alter table `corpus_info` modify column `conc_scope` smallint unsigned NOT NULL default 12 ',
			'alter table `corpus_info` modify column `initial_extended_context` smallint unsigned NOT NULL default 100',
			'alter table `corpus_info` modify column `max_extended_context` smallint unsigned NOT NULL default 1100 ',
			/* and now on to the strings ... first, handles. */
			'alter table `corpus_info` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `corpus_info` modify column `cqp_name` varchar(255) CHARSET ascii COLLATE ascii_bin NOT NULL default ""',
			'alter table `corpus_info` modify column `owner` varchar(64) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `primary_classification_field` varchar(64) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `primary_annotation` varchar(20) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `secondary_annotation` varchar(20) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `tertiary_annotation` varchar(20) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `combo_annotation` varchar(20) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `tertiary_annotation_tablehandle` varchar(255) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `alt_context_word_att` varchar(20) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `conc_s_attribute` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL default ""',
			'alter table `corpus_info` modify column `visualise_position_label_attribute` varchar(64) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `visualise_translate_s_att` varchar(64) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `corpus_info` modify column `visualise_gloss_annotation` varchar(20) CHARSET ascii COLLATE ascii_bin default NULL',
			/* binary-collate data strings */
			'alter table `corpus_info` modify column `css_path` varchar(255) CHARSET utf8 COLLATE utf8_bin DEFAULT "../css/CQPweb-blue.css"',
			'alter table `corpus_info` modify column `visualise_conc_extra_js` varchar(255) CHARSET utf8 COLLATE utf8_bin default ""',
			'alter table `corpus_info` modify column `visualise_context_extra_js` varchar(255) CHARSET utf8 COLLATE utf8_bin default ""',
			'alter table `corpus_info` modify column `visualise_conc_extra_css` varchar(255) CHARSET utf8 COLLATE utf8_bin default ""',
			'alter table `corpus_info` modify column `visualise_context_extra_css` varchar(255) CHARSET utf8 COLLATE utf8_bin default ""',
			/* now, descriptions/etc. */
			'alter table `corpus_info` modify column `external_url` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `corpus_info` modify column `title` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `corpus_info` modify column `public_freqlist_desc` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default "" ',
			'alter table `corpus_info` modify column `indexing_notes` text CHARSET utf8 COLLATE utf8_general_ci',
			'alter table `corpus_info` modify column `access_statement` text CHARSET utf8 COLLATE utf8_general_ci',
			
			'alter table `corpus_metadata_variable` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `corpus_metadata_variable` modify column `attribute` text CHARSET utf8 COLLATE utf8_general_ci NOT NULL',
			'alter table `corpus_metadata_variable` modify column `value` text CHARSET utf8 COLLATE utf8_general_ci',
			
			'alter table `idlink_fields` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `idlink_fields` modify column `att_handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `idlink_fields` modify column `handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `idlink_fields` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `idlink_fields` modify column `datatype` tinyint(2) NOT NULL default 0',
			
			'alter table `idlink_values` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `idlink_values` modify column `att_handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `idlink_values` modify column `field_handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `idlink_values` modify column `handle`  varchar(200) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `idlink_values` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `idlink_values` modify column `category_n_items` int unsigned NOT NULL default 0',
			'alter table `idlink_values` modify column `category_n_tokens` bigint unsigned NOT NULL default 0',
			
			'alter table `lgcurve_info` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `lgcurve_info` modify column `annotation` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `lgcurve_info` modify column `interval_width` smallint unsigned NOT NULL default 0',
			'alter table `lgcurve_info` modify column `create_time` int unsigned NOT NULL default 0',
			'alter table `lgcurve_info` modify column `create_duration` int unsigned NOT NULL default 0',
			'alter table `lgcurve_info` modify column `n_datapoints` int unsigned NOT NULL default 0',
			'alter table `lgcurve_info` add unique key(`corpus`, `annotation`, `interval_width`)',

			'alter table `lgcurve_datapoints` modify column `tokens` bigint unsigned NOT NULL default 0',
			'alter table `lgcurve_datapoints` modify column `types_so_far` bigint unsigned NOT NULL default 0',

			'alter table `metadata_template_info` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `metadata_template_info` modify column `primary_classification` varchar(64) CHARSET ascii COLLATE ascii_bin default NULL',

			'alter table `metadata_template_content` modify column `order_in_template` smallint unsigned NOT NULL default 0',
			'alter table `metadata_template_content` modify column `handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `metadata_template_content` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `metadata_template_content` modify column `datatype` tinyint(2) NOT NULL default 2',
			
			'alter table `plugin_registry` modify column `class` varchar(255) CHARSET utf8 COLLATE utf8_bin default ""',
			'alter table `plugin_registry` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `plugin_registry` modify column `extra` text CHARSET utf8 COLLATE utf8_bin',
			
			'alter table `query_history` modify column `user` varchar(64) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `query_history` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `query_history` modify column `cqp_query` text CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `query_history` modify column `query_scope` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `query_history` modify column `simple_query` text CHARSET utf8 COLLATE utf8_bin',
			/* next few lines might take a LOOOONG while */
			'alter table `query_history` drop key `corpus`',
			'alter table `query_history` drop key `user`',
			'alter table `query_history` add column `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
			'alter table `query_history` drop column `instance_name`',
			'alter table `query_history` add key(`user`,`corpus`)',
			'optimize NO_WRITE_TO_BINLOG table `query_history`', 

			'alter table `saved_catqueries` add column `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
			'alter table `saved_catqueries` modify column `catquery_name` varchar(150) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_catqueries` modify column `user` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `saved_catqueries` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_catqueries` modify column `dbname` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_catqueries` modify column `category_list` text CHARSET ascii COLLATE ascii_bin ',

			'alter table `saved_dbs` modify column `dbname` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_dbs` modify column `user` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_dbs` modify column `create_time` int unsigned NOT NULL default 0',
			'alter table `saved_dbs` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_dbs` modify column `db_type` varchar(15) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `saved_dbs` modify column `colloc_atts` varchar(1024) CHARSET ascii COLLATE ascii_bin NOT NULL default ""',
			'alter table `saved_dbs` modify column `colloc_range` smallint NOT NULL default 0',
			'alter table `saved_dbs` modify column `sort_position` smallint NOT NULL default 0',
			'alter table `saved_dbs` modify column `db_size` bigint unsigned NOT NULL default 0',

			'alter table `saved_freqtables` modify column `freqtable_name` varchar(43) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_freqtables` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_freqtables` modify column `user` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_freqtables` modify column `create_time` int unsigned NOT NULL default 0',
			'alter table `saved_freqtables` modify column `ft_size` bigint unsigned NOT NULL default 0',
			'alter table `saved_freqtables` modify column `public` tinyint(1) NOT NULL default 0',

			'alter table `saved_matrix_info` modify column `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `saved_matrix_info` modify column `savename` varchar(200) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `saved_matrix_info` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_matrix_info` modify column `subcorpus` varchar(200) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `saved_matrix_info` modify column `unit` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `saved_matrix_info` modify column `user` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_matrix_info` modify column `create_time` int unsigned NOT NULL default 0',

			'alter table `saved_matrix_features` modify column `label` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `saved_matrix_features` modify column `source_info` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',

			'alter table `saved_queries` modify column `query_name`              varchar(150) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `saved_queries` modify column `user`                    varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_queries` modify column `corpus`                  varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_queries` modify column `simple_query`            text CHARSET utf8 COLLATE utf8_bin',
			'alter table `saved_queries` modify column `cqp_query`               text CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `saved_queries` modify column `query_scope`             text CHARSET utf8 COLLATE utf8_bin',
			'alter table `saved_queries` modify column `postprocess`             text CHARSET utf8 COLLATE utf8_bin',
			'alter table `saved_queries` modify column `time_of_query`           int unsigned NOT NULL default 0',
			'alter table `saved_queries` modify column `hits`                    bigint unsigned NOT NULL default 0',
			'alter table `saved_queries` modify column `hits_left`               text CHARSET ascii COLLATE ascii_bin ',
			'alter table `saved_queries` modify column `hit_texts`               int unsigned NOT NULL default 0',
			'alter table `saved_queries` modify column `file_size`               bigint unsigned NOT NULL default 0',
			'alter table `saved_queries` modify column `saved`                   tinyint(2) unsigned NOT NULL default 0',
			'alter table `saved_queries` modify column `save_name`               varchar(200) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			
			'alter table `saved_restrictions` modify column `cache_time` int unsigned NOT NULL default 0',
			'alter table `saved_restrictions` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_restrictions` modify column `n_tokens` bigint unsigned NOT NULL default 0',
			'alter table `saved_restrictions` modify column `n_items` int unsigned NOT NULL default 0',
			
			'alter table `saved_subcorpora` modify column `name` varchar(200) CHARSET ascii COLLATE ascii_bin NOT NULL ',
			'alter table `saved_subcorpora` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_subcorpora` modify column `user` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `saved_subcorpora` modify column `n_items` int unsigned NOT NULL default 0',
			'alter table `saved_subcorpora` modify column `n_tokens` bigint unsigned NOT NULL default 0',
			
			'alter table `system_info` modify column `setting_name` varchar(255) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `system_info` modify column `value` varchar(255) CHARSET utf8 COLLATE utf8_bin default ""',
			
			'delete from `system_longvalues`', /* just in case */
			'alter table `system_longvalues` drop primary key',
			'alter table `system_longvalues` modify column `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
			'alter table `system_longvalues` change column `timestamp` `date_of_storing` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP',
			'alter table `system_longvalues` modify column `value` longtext CHARSET utf8 COLLATE utf8_bin NOT NULL',

			'alter table `system_messages` add column `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST',
			'alter table `system_messages` change column `timestamp` `date_of_posting` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP',
			'alter table `system_messages` modify column `header` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `system_messages` modify column `content` text CHARSET utf8 COLLATE utf8_general_ci',
			'alter table `system_messages` modify column `fromto` varchar(255) CHARSET utf8 COLLATE utf8_bin default ""',

			'alter table `system_processes` modify column `dbname` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `system_processes` modify column `process_type` varchar(15) CHARSET ascii COLLATE ascii_bin',
			'alter table `system_processes` modify column `process_id`  varchar(15) CHARSET ascii COLLATE ascii_bin',
			'alter table `system_processes` modify column `begin_time` int unsigned NOT NULL default 0',

			'alter table `text_metadata_fields` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `text_metadata_fields` modify column `handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `text_metadata_fields` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `text_metadata_fields` modify column `datatype` tinyint(2) NOT NULL default 2',

			'alter table `text_metadata_values` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `text_metadata_values` modify column `field_handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `text_metadata_values` modify column `handle` varchar(200) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `text_metadata_values` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `text_metadata_values` modify column `category_num_files` bigint unsigned NOT NULL default 0',
			'alter table `text_metadata_values` modify column `category_num_words` bigint unsigned NOT NULL default 0',
			
			'alter table `user_captchas` modify column `captcha` char(6) CHARSET ascii COLLATE ascii_bin',
			'alter table `user_captchas` modify column `expiry_time` int unsigned NOT NULL default 0',

			'alter table `user_colleague_grants` modify column `comment` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',

			'alter table `user_colleague_links` modify column `status` tinyint(2) unsigned NOT NULL default 0',

			'alter table `user_cookie_tokens` modify column `token` char(64) CHARSET ascii COLLATE ascii_bin NOT NULL default ""',

			/* user_grants_to_users, user_grants_to_groups: nothing to do. */

			'alter table `user_groups` modify column `group_name` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `user_groups` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `user_groups` modify column `autojoin_regex` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `user_groups` add unique key (`group_name`)',
			
			'alter table `user_info` modify column `username`                   varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `user_info` modify column `realname`                   varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `user_info` modify column `email`                      varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `user_info` modify column `affiliation`                varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `user_info` modify column `passhash`                   char(61) CHARSET ascii COLLATE ascii_bin default ""',
			'alter table `user_info` modify column `country`                    char(2) CHARSET ascii COLLATE ascii_bin default "00"',
			'alter table `user_info` modify column `verify_key`                 varchar(32) CHARSET ascii COLLATE ascii_bin default NULL',
			'alter table `user_info` modify column `acct_status`                tinyint(2) unsigned NOT NULL default 0',
			'update      `user_info` set `linefeed` = "au"    where `linefeed` NOT IN("au", "a", "d", "da") ',
			'alter table `user_info` modify column `linefeed`                   enum ("au", "a", "d", "da") NOT NULL',
			'alter table `user_info` modify column `acct_create_time`           timestamp NOT NULL default "1970-01-01 00:00:01"',
			'alter table `user_info` modify column `last_seen_time`             timestamp NOT NULL default CURRENT_TIMESTAMP',
			'alter table `user_info` modify column `conc_kwicview`              tinyint(1) NOT NULL default 1',
			'alter table `user_info` modify column `conc_corpus_order`          tinyint(1) NOT NULL default 1',
			'alter table `user_info` modify column `cqp_syntax`                 tinyint(1) NOT NULL default 0',
			'alter table `user_info` modify column `context_with_tags`          tinyint(1) NOT NULL default 0',
			'alter table `user_info` modify column `use_tooltips`               tinyint(1) NOT NULL default 1',
			'alter table `user_info` modify column `thin_default_reproducible`  tinyint(1) NOT NULL default 1',
			'alter table `user_info` modify column `freqlist_altstyle`          tinyint(1) NOT NULL default 0',
			'alter table `user_info` modify column `coll_statistic`             tinyint(2)  unsigned NOT NULL default 0',
			'alter table `user_info` modify column `coll_freqtogether`          smallint unsigned NOT NULL default 0 ',
			'alter table `user_info` modify column `coll_freqtogether`          smallint unsigned NOT NULL default 0 ',
			'alter table `user_info` modify column `coll_from`                  tinyint NOT NULL default 0 ',
			'alter table `user_info` modify column `coll_to`                    tinyint NOT NULL default 0 ',
			'optimize NO_WRITE_TO_BINLOG table `user_info`', 
			
			'alter table `user_installer_processes` modify column `status` tinyint(2) unsigned NOT NULL default 0',
			'alter table `user_installer_processes` modify column `error_message` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',

			'alter table `user_macros` modify column `user` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `user_macros` modify column `macro_name` varchar(255) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `user_macros` modify column `macro_num_args` smallint unsigned default 0',
			'alter table `user_macros` modify column `macro_body` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `user_macros` add key(`user`)',

			/* user_memberships: nothing to do. */

			'alter table `user_privilege_info` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `user_privilege_info` modify column `type` tinyint(2) unsigned NOT NULL default 0',
			'alter table `user_privilege_info` modify column `scope` text CHARSET utf8 COLLATE utf8_bin',

			'alter table `xml_metadata` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_metadata` modify column `handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_metadata` modify column `att_family` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_metadata` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			
			'alter table `xml_metadata_values` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_metadata_values` modify column `att_handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_metadata_values` modify column `handle` varchar(200) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_metadata_values` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			'alter table `xml_metadata_values` modify column `category_num_words` bigint unsigned NOT NULL default 0',
			'alter table `xml_metadata_values` modify column `category_num_segments` int unsigned NOT NULL default 0',
			
			'alter table `xml_template_info` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ""',
			
			'alter table `xml_template_content` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default "" ',
			'alter table `xml_template_content` modify column `handle` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_template_content` modify column `att_family` varchar(64) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_template_content` modify column `order_in_template` smallint unsigned NOT NULL default 0',

			'alter table `xml_visualisations` modify column `corpus` varchar(20) CHARSET ascii COLLATE ascii_bin NOT NULL',
			'alter table `xml_visualisations` modify column `element` varchar(70) CHARSET ascii COLLATE ascii_bin NOT NULL default ""',
			'alter table `xml_visualisations` modify column `conditional_on` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `xml_visualisations` modify column `html` text CHARSET utf8 COLLATE utf8_bin',

			"CREATE TABLE `catdesc_template_info` (
				`id`                         int unsigned NOT NULL AUTO_INCREMENT,
				`description`                varchar(255) CHARSET utf8 COLLATE utf8_general_ci default '',
			primary key (`id`)
			) ENGINE=InnoDB ",
			

			"CREATE TABLE `catdesc_template_content` (
				`template_id`                int unsigned NOT NULL,
				`order_in_template`          smallint unsigned NOT NULL default 0,
				`handle`                     varchar(200) CHARSET ascii COLLATE ascii_bin NOT NULL,
				`description`                varchar(255) CHARSET utf8 COLLATE utf8_general_ci default ''
			) ENGINE=InnoDB "

	);
	
	foreach ($sql as $q)
		do_sql_query($q);

	upgrade_db_version_note('3.2.37');
}


/* 3.2.35->3.2.36 */
function upgrade_3_2_35()
{
	$sql = array(
		"CREATE TABLE `embedded_pages` (
			`id`                         int unsigned NOT NULL AUTO_INCREMENT,
			`title`                      varchar(255) CHARSET utf8 COLLATE utf8_general_ci default '',
			`file_path`                  varchar(255) CHARSET utf8 COLLATE utf8_general_ci default '',
			primary key(`id`)
		) ENGINE=InnoDB  "
	);
	
	foreach ($sql as $q)
		do_sql_query($q);

	upgrade_db_version_note('3.2.36');
}



/* 3.2.34->3.2.35 */
function upgrade_3_2_34()
{
	/* I forgot to do this on an earlier cleanup. */
	$ix = get_sql_object("show index from lgcurve_datapoints");
	if ($ix->Key_name == 'clocurve_id')
	{
		do_sql_query("alter table `lgcurve_datapoints` drop index `clocurve_id`, add index (`lgcurve_id`)");
		do_sql_query("analyze NO_WRITE_TO_BINLOG table `lgcurve_datapoints`");
	}
	do_sql_query("alter table `user_info` add column `password_locked` tinyint(1) NOT NULL default 0 after `expiry_time`");
	upgrade_db_version_note('3.2.35');
}


/* 3.2.31->3.2.32 */
function upgrade_3_2_31()
{
	$sql = array(

			/* before any actual needed upgrades: we backtrack and make sure to adjust the collate on any columns 
			 * where we changed the default table collation. I didn't realise this was needed at the time... shame on me. */

			'alter table `annotation_metadata` modify column `corpus` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `annotation_metadata` modify column `handle` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `annotation_metadata` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `annotation_metadata` modify column `tagset` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `annotation_metadata` modify column `external_url` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',
			
			'alter table `annotation_template_info` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `annotation_template_info` modify column `primary_annotation` varchar(20) CHARSET utf8 COLLATE utf8_bin default NULL',
			
			'alter table `annotation_template_content` modify column `handle` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `annotation_template_content` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `annotation_template_content` modify column `tagset` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `annotation_template_content` modify column `external_url` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',
			
			'alter table `corpus_metadata_variable` modify column `corpus` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `corpus_metadata_variable` modify column `attribute` text CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `corpus_metadata_variable` modify column `value` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `query_history` modify column `instance_name` varchar(31) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `query_history` modify column `user` varchar(64) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `query_history` modify column `corpus` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL default ""',
			'alter table `query_history` modify column `cqp_query` text CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `query_history` modify column `query_scope` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `query_history` modify column `simple_query` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `query_history` modify column `query_mode` varchar(12) CHARSET utf8 COLLATE utf8_bin default NULL',
			
			'alter table `saved_dbs` modify column `dbname` varchar(200) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `saved_dbs` modify column `user` varchar(64) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `saved_dbs` modify column `cqp_query` text CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `saved_dbs` modify column `query_scope` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `saved_dbs` modify column `postprocess` text CHARSET utf8 COLLATE utf8_bin',
			'alter table `saved_dbs` modify column `corpus` varchar (20) CHARSET utf8 COLLATE utf8_bin NOT NULL default ""',
			'alter table `saved_dbs` modify column `db_type` varchar(15) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `saved_dbs` modify column `colloc_atts` varchar(200) CHARSET utf8 COLLATE utf8_bin default ""',
			
			'alter table `saved_freqtables` modify column `freqtable_name` varchar(150) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `saved_freqtables` modify column `corpus` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL default ""',
			'alter table `saved_freqtables` modify column `user` varchar(64) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `saved_freqtables` modify column `query_scope` text CHARSET utf8 COLLATE utf8_bin',
			
			'alter table `saved_subcorpora` modify column `name` varchar(200) CHARSET utf8 COLLATE utf8_bin NOT NULL default ""',
			'alter table `saved_subcorpora` modify column `corpus` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL default ""',
			'alter table `saved_subcorpora` modify column `user` varchar(64) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `saved_subcorpora` modify column `content` mediumtext CHARSET utf8 COLLATE utf8_bin',
			
			'alter table `system_processes` modify column `dbname` varchar(200) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `system_processes` modify column `process_type` varchar(15)CHARSET utf8 COLLATE utf8_bin default NULL ',
			'alter table `system_processes` modify column `process_id` varchar(15) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `text_metadata_fields` modify column `corpus` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `text_metadata_fields` modify column `handle` varchar(64) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `text_metadata_fields` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `text_metadata_values` modify column `corpus` varchar(20) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `text_metadata_values` modify column `field_handle` varchar(64) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `text_metadata_values` modify column `handle` varchar(200) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `text_metadata_values` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_bin default NULL',

			'alter table `user_info` modify column `username` varchar(64) CHARSET utf8 COLLATE utf8_bin NOT NULL',
			'alter table `user_info` modify column `country` char(2) CHARSET utf8 COLLATE utf8_bin default "00"',
			'alter table `user_info` modify column `passhash` char(61) CHARSET utf8 COLLATE utf8_bin',
			'alter table `user_info` modify column `verify_key` varchar(32) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `user_info` modify column `linefeed` char(2) CHARSET utf8 COLLATE utf8_bin default NULL',
			'alter table `user_privilege_info` modify column `description` varchar(255) CHARSET utf8 COLLATE utf8_bin default ""',
			'alter table `user_privilege_info` modify column `scope` text CHARSET utf8 COLLATE utf8_bin'

		/* end collation fixes. */
	);

	foreach($sql as $q)
		do_sql_query($q);


	/* NB. We change the default collocation stat. in this version IFF it is not overridden by admin. */
	/* the config file is already included so we can look and see if there is a variable override. */
	if (!isset($GLOBALS['default_colloc_calc_stat']))
		do_sql_query('update user_info set coll_statistic = 8 where coll_statistic = 6');
	/* overwhelming majority of users who have NOT changed the default are not especially fond of LL... */

	$sql = array(
			'alter table `corpus_info` alter `css_path` set default "../css/CQPweb-blue.css"',
			'update `corpus_info` set `css_path` = "../css/CQPweb-navy.css" where `css_path` = "../css/CQPweb-darkblue.css"',
			'update `corpus_info` set `css_path` = "../css/CQPweb-blue.css" where `css_path` = "../css/CQPweb.css"',
			'alter table `corpus_info` add column `sttr_1kw` float DEFAULT 0.0 after `size_bytes_freq`',
			'create table IF NOT EXISTS `user_colleague_links`  (
				`from_id` int unsigned NOT NULL, `to_id` int unsigned NOT NULL, `status` tinyint default 0 
				) ENGINE=InnoDB CHARSET ascii COLLATE ascii_bin',
			'create table IF NOT EXISTS `user_colleague_grants` (
				`corpus_id` int unsigned NOT NULL, `grantee_id` int unsigned NOT NULL, `comment` varchar(255) NOT NULL DEFAULT "", key(`grantee_id`) 
				) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin',
			'alter table `corpus_info` drop column `is_user_corpus`',
			'alter table `corpus_info` add column `owner` varchar(64) DEFAULT NULL after `cwb_external`',
			'create table IF NOT EXISTS `plugin_registry` (
				`id` int unsigned NOT NULL AUTO_INCREMENT,
				`class` varchar(255) default NULL,
				`type` tinyint unsigned NOT NULL DEFAULT 0,
				`description` varchar(255) default "",
				`extra` text,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin',
			'create table IF NOT EXISTS `user_installer_processes` (
				`id` int unsigned NOT NULL AUTO_INCREMENT,
				`corpus_id` int unsigned NOT NULL,
				`user_id` int unsigned NOT NULL,
				`plugin_reg_id` int unsigned NOT NULL,
				`php_pid` int unsigned NOT NULL,
				`status` smallint NOT NULL,
				`last_status_change` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
				`error_message` varchar(255) DEFAULT "",
				primary key (`id`)
			) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_unicode_ci',
			'alter table `corpus_info` ADD COLUMN `language` char(3) CHARSET ascii COLLATE ascii_bin NOT NULL DEFAULT "und" after `cwb_external`',
			'alter table `corpus_info` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `corpus_categories` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `saved_matrix_info` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `saved_matrix_features` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `user_groups` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `user_info` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `user_macros` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `user_privilege_info` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `xml_metadata` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `xml_visualisations` MODIFY COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT',
			'alter table `saved_matrix_features` MODIFY COLUMN `matrix_id` int unsigned NOT NULL',
			'alter table `user_memberships` MODIFY COLUMN `user_id` int unsigned NOT NULL',
			'alter table `user_memberships` MODIFY COLUMN `group_id` int unsigned NOT NULL',
			'alter table `user_cookie_tokens` MODIFY COLUMN `user_id` int unsigned NOT NULL',
			'alter table `user_grants_to_users` MODIFY COLUMN `user_id` int unsigned NOT NULL',
			'alter table `user_grants_to_users` MODIFY COLUMN `privilege_id` int unsigned NOT NULL',
			'alter table `user_grants_to_groups` MODIFY COLUMN `group_id` int unsigned NOT NULL',
			'alter table `user_grants_to_groups` MODIFY COLUMN `privilege_id` int unsigned NOT NULL'
	);
	
	foreach ($sql as $q)
		do_sql_query($q);
	
	upgrade_db_version_note('3.2.32');
	
	echo <<<END_OF_NOTE
	
	IMPORTANT NOTE: this upgrade has added the STTR statistic to the database.

	You should run the following command in order to add the STTR to your existing corpora:

		   php execute-cli.php update_all_missing_sttr

	Be aware this can take a LONG time to run!

	The STTR for *new* corpora will be calculated at corpus-installation time.
 
END_OF_NOTE;
}






/* 3.2.26->3.2.27 */
function upgrade_3_2_26()
{
	$sql = array(
			'alter table saved_dbs add index (`corpus`)',
			'alter table `user_info` add column `freqlist_altstyle` tinyint default 0 after `max_dbsize`',
			'alter table `xml_visualisations` add column `is_template` tinyint(1) NOT NULL default 0 after `html`',
			'rename table `clocurve_info` to `lgcurve_info`',
			'rename table `clocurve_datapoints` to `lgcurve_datapoints`',
			'alter table `lgcurve_datapoints` change column `clocurve_id` `lgcurve_id` int unsigned NOT NULL',
	);
	
	foreach ($sql as $q)
		do_sql_query($q);

	upgrade_db_version_note('3.2.27');
}

/* 3.2.24->3.2.25 */
function upgrade_3_2_24()
{
	$sql = array(
			'alter table `system_longvalues` modify column `value` longtext NOT NULL',
			'create table IF NOT EXISTS `clocurve_info` (
					`id` int unsigned NOT NULL AUTO_INCREMENT,
					`corpus` varchar(20) NOT NULL,
					`annotation` varchar(20) NOT NULL,
					`interval_width` int unsigned NOT NULL,
					`create_time` int default NULL,
					`create_duration` int unsigned default NULL,
					`n_datapoints` int unsigned,
					PRIMARY KEY (`id`)
				) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin',
			'create table IF NOT EXISTS `clocurve_datapoints` (
					`clocurve_id` int unsigned NOT NULL,
					`tokens` bigint unsigned NOT NULL,
					`types_so_far` bigint unsigned NOT NULL,
					KEY (`clocurve_id`)
				) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin',
	);
	
	foreach ($sql as $q)
		do_sql_query($q);
	
	/* a special one: only if no CQP privilege not set up already. */
	$sym = PRIVILEGE_TYPE_CQP_BINARY_FILE;
	if (1 > mysqli_num_rows(do_sql_query("select * from user_privilege_info where type = $sym ")))
		do_sql_query("insert into `user_privilege_info` (description, type, scope) VALUES ('CQP binary file access privilege',$sym,'')");
	
	upgrade_db_version_note('3.2.25');
}

/* 3.2.23->3.2.24 */
function upgrade_3_2_23()
{
	/* NOTE:
	 * This is a dummy. It does nothing. The reason it is here is that it was present as a placeholder for a long while,
	 * while I was waiting to add database changes that, in the end, I held over to 3.2.25; and users may have picked it up
	 * from the svn repo in that period. I am therefore not jumping a version here because of the faint possiblity I might
	 * break the upgrade path for some users. 
	 */
	upgrade_db_version_note('3.2.24');
}




/* 3.2.21->3.2.22 */
function upgrade_3_2_21()
{
	$sql = array(
			'create table IF NOT EXISTS `corpus_alignments` (`corpus` varchar(20) NOT NULL,`target` varchar(20) NOT NULL) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin',
	);

	foreach ($sql as $q)
		do_sql_query($q);
	
	/* EXTRA ACTION: populate the database table thus created. 
	$result = do_sql_query("select corpus from corpus_info");
	while ($o = mysqli_fetch_object($result))
		system("php execute-cli.php scan_for_corpus_alignments {$o->corpus}");
	 */
	/* commented out because not all system admins will want to do the above; manual explains how to do, er, manually. */
	
	upgrade_db_version_note('3.2.22');
}


/* 3.2.19->3.2.20 */
function upgrade_3_2_19()
{
	$sql = array(
			'alter table `xml_visualisations` add column `in_download` tinyint(1) NOT NULL default 0 after `in_context`',
	);
	foreach ($sql as $q)
		do_sql_query($q);

	upgrade_db_version_note('3.2.20');
}


/* 3.2.17->3.2.18 */
function upgrade_3_2_17()
{
	$sql = array(
			'alter table corpus_info add column `visualise_context_extra_css` varchar(255) default "" after `visualise_position_label_attribute`',
			'alter table corpus_info add column `visualise_conc_extra_css`    varchar(255) default "" after `visualise_position_label_attribute`',
			'alter table corpus_info add column `visualise_context_extra_js`  varchar(255) default "" after `visualise_position_label_attribute`',
			'alter table corpus_info add column `visualise_conc_extra_js`     varchar(255) default "" after `visualise_position_label_attribute`',
			'alter table corpus_info add column `visualise_break_context_on_punc` tinyint(1) NOT NULL default 1 after `visualise_context_extra_css`',
			'drop table if exists `xml_visualisations`', 
			'create table IF NOT EXISTS `xml_visualisations` (
                         `id` int NOT NULL AUTO_INCREMENT,
                         `corpus` varchar(20) NOT NULL default "",
                         `element` varchar(70) NOT NULL default "", 
                         `conditional_on` varchar(1024) NOT NULL default "",
                         `in_concordance` tinyint(1) NOT NULL default 1,
                         `in_context` tinyint(1) NOT NULL default 1,
                         `html` varchar(1024) NOT NULL default "",
                         primary key (`id`),
                         key(`corpus`)
                    ) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin',
	);
	foreach ($sql as $q)
		do_sql_query($q);

	upgrade_db_version_note('3.2.18');
}


/* 3.2.14->3.2.15 */
function upgrade_3_2_14()
{
	$sql = array(
			/* first, force log-out everyone. Then drop key. then redefine column. Then re-add key. */
			'delete from user_cookie_tokens',
			'alter table user_cookie_tokens drop key `token`',
			'alter table user_cookie_tokens modify column `token` char(64) NOT NULL DEFAULT ""',
			'alter table user_cookie_tokens add key(`token`, `user_id`)'
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	upgrade_db_version_note('3.2.15');
	
	echo "\n!!!!!!!!!!!!!!!!!!!!!!!!Important Note: this upgrade forces all users to re-log-in. \n\n";
}


/* 3.2.12->3.2.13 */
function upgrade_3_2_12()
{
	$sql = array(
		'alter table corpus_info add column `size_bytes_index` bigint unsigned NOT NULL DEFAULT 0 after `size_texts`',
		'alter table corpus_info add column `size_bytes_freq`  bigint unsigned NOT NULL DEFAULT 0 after `size_bytes_index`',
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	/* we need to run the update-function for these two columns for each existing corpus.
	 * However, we don't have the necessary library functions here, or a full environment.
	 * So DELEGATE to execute-cli.php.
	 */
	$result = do_sql_query("select corpus from corpus_info");
	while ($o = mysqli_fetch_object($result))
	{
		echo "Updating index size record for corpus {$o->corpus}\n\t";
		system("php execute-cli.php update_corpus_index_size {$o->corpus}");
		echo "Updating freq table size record for corpus {$o->corpus}\n\t";
		system("php execute-cli.php update_corpus_freqtable_size {$o->corpus}");
	}
	
	upgrade_db_version_note('3.2.13');
}


/* 3.2.9->3.2.10 */
function upgrade_3_2_9()
{
	$sql = array(
		"create table IF NOT EXISTS `saved_restrictions` (
			`id` bigint unsigned NOT NULL AUTO_INCREMENT,
			`cache_time` bigint unsigned NOT NULL default 0,
			`corpus` varchar(20) NOT NULL default '',
			`serialised_restriction` text,
			`n_items` int unsigned,
			`n_tokens` bigint unsigned,
			`data` longblob,
			primary key (`id`),
			key(`corpus`),
			key(`serialised_restriction`(255))
		) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin"
	);
	foreach ($sql as $q)
		do_sql_query($q);
	upgrade_db_version_note('3.2.10');
}


/* 3.2.7->3.2.8 */
function upgrade_3_2_7()
{
	$sql = array(
		'create table IF NOT EXISTS `idlink_fields` (
           `corpus` varchar(20) NOT NULL,
           `att_handle` varchar(64) NOT NULL,
           `handle` varchar(64) NOT NULL,
           `description` varchar(255) default NULL,
           `datatype` tinyint(2) NOT NULL default 0, 
           primary key (`corpus`, `att_handle`, `handle`)
         ) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin',
		'create table IF NOT EXISTS `idlink_values` (
           `corpus` varchar(20) NOT NULL,
           `att_handle` varchar(64) NOT NULL,
           `field_handle` varchar(64) NOT NULL,
           `handle` varchar(200) NOT NULL,
           `description` varchar(255) default NULL,
           `category_n_items` int unsigned default NULL,
           `category_n_tokens` int unsigned default NULL,
           primary key(`corpus`, `att_handle`, `field_handle`, `handle`)
         ) ENGINE=InnoDB CHARSET utf8 COLLATE utf8_bin'
	);
	foreach ($sql as $q)
		do_sql_query($q);
	upgrade_db_version_note('3.2.8');
}

/* --------------------------------------------------------------------------------------------------------- */

/* 3.2.6->3.2.7 */
function upgrade_3_2_6()
{
	echo "Warning: this upgrade action can take a long time.\nDO NOT interrupt the script; let it run to completion.\n";
	
	/* we have some extra functions for this one */
	require('../bin/v3-2-6-upgrade-utils.inc.php');
	/* replace with the actual funcs if they turn out to be shortish. */
	
	$sql = array(
			'alter table saved_subcorpora change column `numwords` `n_tokens` bigint(21) unsigned default NULL',
			'alter table saved_subcorpora change column `numfiles` `n_items`  int(11) unsigned default NULL',
				/* -------------------------- */
			'alter table saved_subcorpora  add column `content` mediumtext after text_list',
			'alter table query_history     add column `query_scope` text after `subcorpus`',
			'alter table saved_dbs         add column `query_scope` text after `subcorpus`',
			'alter table saved_freqtables  add column `query_scope` text after `subcorpus`',
			'alter table saved_queries     add column `query_scope` text after `subcorpus`',
	);
	foreach ($sql as $q)
		do_sql_query($q);

	/* the translation stage for the new "scope" / "content" columns. */
	
	/* saved_subcorpora  --- is a somewhat different case, because we have the text_list. */
	echo "    .... now updating the database format for the saved_subcorpora table\n";
	$result = do_sql_query("SELECT id, restrictions, text_list from saved_subcorpora");
	while ($o = mysqli_fetch_object($result))
	{
		$content = upgrade326_recode_to_new_subcorpus($o->restrictions, $o->text_list);
		do_sql_query("update saved_subcorpora set content='$content' where id = $o->id");
	}
	
	/* query hist */
	echo "    .... now updating the database format for the query_history table\n";
	do_sql_query('update query_history set query_scope = ""');
	$result = do_sql_query("SELECT instance_name, subcorpus, restrictions, query_scope from query_history");
	while ($o = mysqli_fetch_object($result))
	{
		$scope = upgrade326_recode_pair_to_scope($o->subcorpus, $o->restrictions);
		if ($o->query_scope != $scope)
			do_sql_query("update query_history set query_scope='$scope' where instance_name = '$o->instance_name'");
	}
	
	/* saved_dbs */
	echo "    .... now updating the database format for the saved_dbs table\n";
	$result = do_sql_query("SELECT dbname, subcorpus, restrictions from saved_dbs");
	while ($o = mysqli_fetch_object($result))
	{
		$scope = upgrade326_recode_pair_to_scope($o->subcorpus, $o->restrictions);
		do_sql_query("update saved_dbs set query_scope='$scope' where dbname = '$o->dbname'");
	}
	
	/* saved_queries */
	echo "    .... now updating the database format for the saved_queries table\n";
	$result = do_sql_query("SELECT query_name, subcorpus, restrictions from saved_queries");
	while ($o = mysqli_fetch_object($result))
	{
		$scope = upgrade326_recode_pair_to_scope($o->subcorpus, $o->restrictions);
		do_sql_query("update saved_queries set query_scope='$scope' where query_name = '$o->query_name'");
	}

	/* saved_freqtables */
	echo "    .... now updating the database format for the saved_freqtables table\n";
	$result = do_sql_query("SELECT freqtable_name, subcorpus, restrictions from saved_freqtables");
	while ($o = mysqli_fetch_object($result))
	{
		$scope = upgrade326_recode_pair_to_scope($o->subcorpus, $o->restrictions);
		do_sql_query("update saved_freqtables set query_scope='$scope' where freqtable_name = '$o->freqtable_name'");
	}
	
	/* back to single line database rewrite statements........... */
	
	$sql = array(
			'alter table saved_subcorpora change `restrictions` `_restrictions` text',
			'alter table saved_subcorpora change `text_list` `_text_list` text',
			'alter table query_history change `restrictions` `_restrictions` text',
			'alter table query_history change `subcorpus`    `_subcorpus` varchar(200) NOT NULL default ""',
			'alter table saved_dbs change `restrictions` `_restrictions` text',
			'alter table saved_dbs change `subcorpus`    `_subcorpus` varchar(200) NOT NULL default ""',
			'alter table saved_freqtables change `restrictions` `_restrictions` text',
			'alter table saved_freqtables change `subcorpus`    `_subcorpus` varchar(200) NOT NULL default ""',
			'alter table saved_queries change `restrictions` `_restrictions` text',
			'alter table saved_queries change `subcorpus`    `_subcorpus` varchar(200) NOT NULL default ""',

			'alter table saved_subcorpora drop index `text_list`',
			'alter table saved_freqtables drop index `subcorpus`',
			'alter table saved_freqtables ADD INDEX `query_scope` (`query_scope`(255))',
			'alter table saved_queries drop index `restrictions`',
			'alter table saved_queries drop index `subcorpus`',
			'alter table saved_queries ADD FULLTEXT KEY `query_scope` (`query_scope`)',
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	upgrade_db_version_note('3.2.7');
}

/* 3.2.5->3.2.6 */
function upgrade_3_2_5()
{
	echo "Warning: this upgrade action can take a long time.\nDO NOT interrupt the script; let it run to completion.\n";
	$sql = array(
			'insert into system_info (setting_name, value) values ("install_date", "Pre ' .  date('Y-m-d') . '") ',
			'insert into system_info (setting_name, value) values ("db_updated", "' .  date('Y-m-d H:i') . '") ',
			'alter table saved_queries modify column `save_name` varchar(200) default NULL',
			'alter table saved_subcorpora add column `id` bigint unsigned NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
			'alter table saved_subcorpora change column `subcorpus_name` `name` varchar(200) NOT NULL default ""',
			'update saved_subcorpora set name = "--last_restrictions" where name = "__last_restrictions"',
			'update saved_subcorpora set text_list    = "" where text_list    IS NULL',
			'update saved_subcorpora set restrictions = "" where restrictions IS NULL',
			'alter table saved_matrix_info modify column `subcorpus` varchar(200) NOT NULL default ""',
			'alter table query_history modify column `date_of_query` timestamp NOT NULL default CURRENT_TIMESTAMP',
				/* -------- */
			'alter table query_history modify column `subcorpus` varchar(200) NOT NULL default ""',
			'update query_history set subcorpus    = "" where subcorpus    = "no_subcorpus"',
			'update query_history set restrictions = "" where restrictions = "no_restriction"',
			
			'alter table saved_queries modify column `subcorpus` varchar(200) NOT NULL default ""',
			'update saved_queries set subcorpus    = "" where subcorpus    = "no_subcorpus"',
			'update saved_queries set restrictions = "" where restrictions = "no_restriction"',
			
			'alter table saved_dbs modify column `subcorpus` varchar(200) NOT NULL default ""',
			'update saved_dbs set subcorpus    = "" where subcorpus    = "no_subcorpus"',
			'update saved_dbs set restrictions = "" where restrictions = "no_restriction"',
			
			'alter table saved_freqtables modify column `subcorpus` varchar(200) NOT NULL default ""',
			'update saved_freqtables set subcorpus    = "" where subcorpus    = "no_subcorpus"',
			'update saved_freqtables set restrictions = "" where restrictions = "no_restriction"',
			'update saved_freqtables set restrictions = "" where restrictions = "no_restriction"',
				/*--------- */
	);
	foreach ($sql as $q)
		do_sql_query($q);

	/* 
	 * switching the subcorpus fields of the above tables to contain an integer ID representation rather than a name is more... complex. 
	 * (Why the change? because storing just the "name" creates an ambiguity: two subcorproa for different users/corpora might have the same "name".)
	 */
	
	/* saved_freqtables */
	$target_result = do_sql_query ('select freqtable_name, corpus, user, subcorpus from saved_freqtables');
	while ($o = mysqli_fetch_object($target_result))
	{
		if (!empty($o->subcorpus))
		{
			$seek_result = do_sql_query("select id from saved_subcorpora where corpus='{$o->corpus}' and user='{$o->user}' and name='{$o->subcorpus}'");
			/* the below should NEVER happen here because FTs are deleted when the SC is. Anwyay tho.... */
			if (0 == mysqli_num_rows($seek_result))
				$id = '-1'; /* because it's a signed integer field: so this is a pointer-to-nothing (fine for a deleted subcorpus). */ 
			else
				list($id) = mysqli_fetch_row($seek_result);
			do_sql_query("update saved_freqtables set subcorpus = '$id' where freqtable_name = '{$o->freqtable_name}'");
		}
	}
	
	/* saved_dbs */
	$target_result = do_sql_query ('select dbname, corpus, user, subcorpus from saved_dbs');
	while ($o = mysqli_fetch_object($target_result))
	{
		if (!empty($o->subcorpus))
		{
			$seek_result = do_sql_query("select id from saved_subcorpora where corpus='{$o->corpus}' and user='{$o->user}' and name='{$o->subcorpus}'");
			/* the below should NEVER happen here because DBs are deleted when the SC is. Anwyay tho.... */
			if (0 == mysqli_num_rows($seek_result))
				$id = '-1';
			else
				list($id) = mysqli_fetch_row($seek_result);
			do_sql_query("update saved_dbs set subcorpus = '$id' where dbname = '{$o->dbname}'");
		}
	}
	
	/* saved_queries */
	$target_result = do_sql_query ('select query_name, corpus, user, subcorpus from saved_queries');
	while ($o = mysqli_fetch_object($target_result))
	{
		if (!empty($o->subcorpus))
		{
			$seek_result = do_sql_query("select id from saved_subcorpora where corpus='{$o->corpus}' and user='{$o->user}' and name='{$o->subcorpus}'");
			/* the below should NEVER happen here because cached Qs are deleted when the SC is. Anwyay tho.... */
			if (0 == mysqli_num_rows($seek_result))
				$id = '-1'; 
			else
				list($id) = mysqli_fetch_row($seek_result);
			do_sql_query("update saved_queries set subcorpus = '$id' where query_name = '{$o->query_name}'");
		}
	}

	/* query history */
	$target_result = do_sql_query ('select instance_name, corpus, user, subcorpus from query_history');
	while ($o = mysqli_fetch_object($target_result))
	{
		if (!empty($o->subcorpus))
		{
			$seek_result = do_sql_query("select id from saved_subcorpora where corpus='{$o->corpus}' and user='{$o->user}' and name='{$o->subcorpus}'");
			/* the below is a step DESIGNED for this table really.........  */
			if (0 == mysqli_num_rows($seek_result))
				$id = '-1'; 
			else
				list($id) = mysqli_fetch_row($seek_result);
 			do_sql_query("update query_history set subcorpus = '$id' where instance_name = '{$o->instance_name}'");
		}
	}
	

	$sql = array(
			'alter table corpus_info modify column `primary_classification_field` varchar(64) default NULL',
			'alter table corpus_info add column `alt_context_word_att` varchar(20) default "" after `max_extended_context`',
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	upgrade_db_version_note('3.2.6');
}

/* 3.2.4->3.2.5 */
function upgrade_3_2_4()
{
	$sql = array(
		'alter table saved_queries drop column date_of_saving',
		'update saved_queries set simple_query = "" where simple_query IS NULL',
		'update saved_queries set cqp_query    = "" where cqp_query    IS NULL',
		'update saved_queries set postprocess  = "" where postprocess  IS NULL',
		'update saved_queries set hits_left    = "" where hits_left    IS NULL',
		'update saved_queries set save_name    = "" where save_name    IS NULL',
		'update saved_dbs     set postprocess  = "" where postprocess  IS NULL',
	);
	foreach ($sql as $q)
		do_sql_query($q);

	do_sql_query("update system_info set value = '3.2.5' where setting_name = 'db_version'");
}

/* 3.2.3->3.2.4 */
function upgrade_3_2_3()
{
	$sql = array(
		'alter table user_info add column `css_monochrome` tinyint(1) NOT NULL default 0 after use_tooltips',
		'drop table if exists `user_cookie_tokens`',
		'create table IF NOT EXISTS `user_cookie_tokens` (
				`token` bigint UNSIGNED NOT NULL default 0,
				`user_id` int NOT NULL,
				`creation`  int UNSIGNED NOT NULL default 0,
				`expiry`  int UNSIGNED NOT NULL default 0,
				key(`token`, `user_id`)
			) CHARSET utf8 COLLATE utf8_bin'
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	/* switch any existing verify-keys to new format. */
	$result = do_sql_query("select id, verify_key from user_info where verify_key is not null");
	while ($o = mysqli_fetch_object($result))
	{
		$hash = md5($o->verify_key);
		do_sql_query("update user_info set verify_key = '$hash' where id = {$o->id}");
	}
	
	do_sql_query("update system_info set value = '3.2.4' where setting_name = 'db_version'");
}

/* 3.2.1->3.2.2 */
function upgrade_3_2_1()
{
	$sql = array( 
		"create table IF NOT EXISTS `metadata_template_info` (
			`id` int unsigned NOT NULL AUTO_INCREMENT,
			`description` varchar(255) default NULL,
			`primary_classification` varchar(64) default NULL,
			PRIMARY KEY (`id`)
		) CHARSET utf8 COLLATE utf8_bin",

		"create table IF NOT EXISTS `metadata_template_content` (
			`template_id` int unsigned NOT NULL,
			`order_in_template` smallint unsigned,
			`handle` varchar(64) NOT NULL,
			`description` varchar(255) default NULL,
			`datatype`  tinyint(2) NOT NULL default " . METADATA_TYPE_FREETEXT . "
		) CHARSET utf8 COLLATE utf8_bin",
		
		"update system_info set value = '3.2.2' where setting_name = 'db_version'"
	);
	foreach ($sql as $q)
		do_sql_query($q);	
}

/* 3.1.16->3.2.0 */
function upgrade_3_1_16()
{
	$sql = array(
		'alter table corpus_info add column `cqp_name` varchar(255) NOT NULL default \'\' after `corpus`',
		'alter table corpus_info add column `access_statement` TEXT default NULL',
		'alter table corpus_info add column `uses_case_sensitivity` tinyint(1) NOT NULL default 0 after `primary_classification_field`',
		'alter table corpus_info add column `title` varchar(255) default \'\' after `visible`',
		'alter table corpus_info add column `css_path` varchar(255) default \'../css/CQPweb.css\' after `public_freqlist_desc`',
		'alter table corpus_info add column `main_script_is_r2l` tinyint(1) NOT NULL default 0 after `combo_annotation`',
		'alter table corpus_info add column `conc_s_attribute` varchar(64) NOT NULL default \'\' after `main_script_is_r2l`',
		'alter table corpus_info add column `conc_scope` smallint NOT NULL default 12 after `conc_s_attribute`',
		'alter table corpus_info add column `initial_extended_context` smallint NOT NULL default 100 after `conc_scope`',
		'alter table corpus_info add column `max_extended_context` smallint NOT NULL default 1100 after `initial_extended_context`',
		'alter table corpus_info add column `visualise_gloss_in_concordance` tinyint(1) NOT NULL default 0 after `max_extended_context`',
		'alter table corpus_info add column `visualise_gloss_in_context` tinyint(1) NOT NULL default 0 after `visualise_gloss_in_concordance`',
		'alter table corpus_info add column `visualise_gloss_annotation` varchar(20) default NULL after `visualise_gloss_in_context`',
		'alter table corpus_info add column `visualise_translate_in_concordance` tinyint(1) NOT NULL default 0 after `visualise_gloss_annotation`',
		'alter table corpus_info add column `visualise_translate_in_context` tinyint(1) NOT NULL default 0 after `visualise_translate_in_concordance`',
		'alter table corpus_info add column `visualise_translate_s_att` varchar(64) default NULL after `visualise_translate_in_context`',
		'alter table corpus_info add column `visualise_position_labels` tinyint(1) NOT NULL default 0 after `visualise_translate_s_att`',
		'alter table corpus_info add column `visualise_position_label_attribute` varchar(64) default NULL after `visualise_position_labels`',
		'alter table corpus_info add column `indexing_notes` TEXT default NULL', # NB column is added "LAST".

		'alter table text_metadata_fields modify column `handle` varchar(64) NOT NULL',
		'alter table text_metadata_values modify column `handle` varchar(200) NOT NULL',
		'alter table text_metadata_values modify column `field_handle` varchar(64) NOT NULL',
		'alter table text_metadata_fields add column `datatype` tinyint(2) NOT NULL default 0 after `description`',
		'update text_metadata_fields set datatype = 2',
		'update text_metadata_fields set datatype = 1 where is_classification = 1',
		/* NB this obsoletes the "is_classification" column, but we leave it for safety. */

		/* now, some general cleanup : tables whose collate should have been utf_bin all along, but for whatever reason, wasn't */
		'alter table `annotation_metadata` collate utf8_bin',
		'alter table `annotation_template_info` collate utf8_bin',
		'alter table `annotation_template_content` collate utf8_bin',
		'alter table `corpus_metadata_variable` collate utf8_bin',
		'alter table `saved_dbs` collate utf8_bin',
		'alter table `saved_freqtables` collate utf8_bin',
		'alter table `saved_subcorpora` collate utf8_bin',
		'alter table `user_memberships` collate utf8_bin',
		'alter table `user_privilege_info` collate utf8_bin',
		'alter table `query_history` collate utf8_bin',
		'alter table `system_processes` collate utf8_bin',
		'alter table `text_metadata_fields` collate utf8_bin',
		'alter table `text_metadata_values` collate utf8_bin',
		'alter table `user_info` collate utf8_bin',
		/* using utf8_bin for user_info implies the following for specific columnss: */
		'alter table `user_info` modify column `affiliation` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default NULL',
		'alter table `user_info` modify column `email` varchar(255) CHARSET utf8 COLLATE utf8_general_ci  default NULL',
		'alter table `user_info` modify column `realname` varchar(255) CHARSET utf8 COLLATE utf8_general_ci default NULL',
		
		/* more misc cleanup : new length for username */
		'alter table `query_history` modify column `user` varchar(64) default NULL',
		'alter table `saved_catqueries` modify column `user` varchar(64) default NULL',
		'alter table `saved_dbs` modify column `user` varchar(64) default NULL',
		'alter table `saved_matrix_info` modify column `user` varchar(64) default NULL',
		'alter table `saved_matrix_info` modify column `corpus` varchar(20) NOT NULL default \'\'',
		'alter table `saved_freqtables` modify column `user` varchar(64) default NULL',
		'alter table `saved_queries` modify column `user` varchar(64) default NULL',
		'alter table `saved_subcorpora` modify column `user` varchar(64) default NULL',
		'alter table `user_macros` modify column `user` varchar(64) default NULL',
		'alter table `user_info` modify column `username` varchar(64) NOT NULL',

		/* now, the 4 new database tables for XML management */
		"create table IF NOT EXISTS `xml_metadata` (
			`id` int NOT NULL AUTO_INCREMENT,
			`corpus` varchar(20) NOT NULL,
			`handle` varchar(64) NOT NULL,
			`att_family` varchar(64) NOT NULL default '',
			`description` varchar(255) default NULL,
			`datatype`  tinyint(2) NOT NULL default " . METADATA_TYPE_NONE . ",
			primary key(`id`),
			unique key (`corpus`, `handle`)
		) CHARSET utf8 COLLATE utf8_bin",
		"create table IF NOT EXISTS `xml_metadata_values` (
			`corpus` varchar(20) NOT NULL,
			`att_handle` varchar(64) NOT NULL,
			`handle` varchar(200) NOT NULL,
			`description` varchar(255) default NULL, 
			`category_num_words` int unsigned default NULL,
			`category_num_segments` int unsigned default NULL,
			primary key(`corpus`, `att_handle`, `handle`)
		) CHARSET utf8 COLLATE utf8_bin",
		"create table IF NOT EXISTS `xml_template_info` (
			`id` int unsigned NOT NULL AUTO_INCREMENT,
			`description` varchar(255) default NULL,
			PRIMARY KEY (`id`)
		) CHARSET utf8 COLLATE utf8_bin",
		"create table IF NOT EXISTS `xml_template_content` (
			`template_id` int unsigned NOT NULL,
			`order_in_template` smallint unsigned,
			`handle` varchar(64) NOT NULL,
			`att_family` varchar(64) NOT NULL default '',
			`description` varchar(255) default NULL,
			`datatype`  tinyint(2) NOT NULL default " . METADATA_TYPE_NONE . "
		) CHARSET utf8 COLLATE utf8_bin",
	);
	foreach ($sql as $q)
		do_sql_query($q);

	/* do the very last DB change! */
	do_sql_query("update system_info set value = '3.2.0' where setting_name = 'db_version'");
	
	/* because there is an extra step here, this merits breaking out of the loop and hard-exiting, naturellement */
	
	echo <<<ENDOFNOTE
	
	IMPORTANT MESSAGE
	=================
	
	The database has now upgraded as far v3.2.0; this involves a change to how corpora are stored on the system.
	Before you upgrade the database any further, you need to run the "load-pre-3.2-corpsettings.php" script to 
	make sure your corpus settings transition to the new format.
	
	This script now exits. Please run that other script, then (if you are on a later version than 3.2.0)
	run "upgrade-database.php" again.


ENDOFNOTE;

	global $Config;
	mysqli_close($Config->mysql_link);
	exit;
}

/* 3.1.9->3.1.10 */
function upgrade_3_1_9()
{
	$sql = array(
		'alter table corpus_info add column `size_tokens` int NOT NULL DEFAULT 0 after `public_freqlist_desc`',
		'alter table corpus_info add column `size_texts`  int NOT NULL DEFAULT 0 after `size_tokens`',
		'alter table corpus_info add column `size_types`  int NOT NULL DEFAULT 0 after `size_tokens`'
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	/* now, install token / text counts for each corpus on the system */
	echo "Corpus metadata format has been changed: existing corpus info will now be updated. Please wait.\n";
	$result = do_sql_query('select corpus from corpus_info');
	while ($r = mysqli_fetch_row($result))
	{
		// tokens (and texts)
		if (0 < mysqli_num_rows(do_sql_query("show tables like 'text_metadata_for_{$r[0]}'")))
		{
			$inner = do_sql_query("select sum(words), count(*) from text_metadata_for_{$r[0]}");
			list($ntok, $ntext) = mysqli_fetch_row($inner);
			do_sql_query("update corpus_info set size_tokens = $ntok, size_texts = $ntext where corpus = '{$r[0]}'");
		}
		
		// types
		if (0 < mysqli_num_rows(do_sql_query("show tables like 'freq_corpus_{$r[0]}_word'")))
		{
			list($types) = mysqli_fetch_row(do_sql_query("select count(distinct(item)) from freq_corpus_{$r[0]}_word"));
			do_sql_query("update corpus_info set size_types = $types where corpus = '$r[0]'");
		}
		
		echo "Corpus info has been updated for ", $r[0], "!\n"; 
	}
	echo "Done updating existing corpus info.\n";
	
	/* do the very last DB change! */
	do_sql_query("update system_info set value = '3.1.10' where setting_name = 'db_version'");
}

/* 3.1.8->3.1.9 */
function upgrade_3_1_8()
{
	$sql = array(
		"create table IF NOT EXISTS `saved_matrix_info` (
           `id` int NOT NULL AUTO_INCREMENT,
           `savename` varchar(255),
           `user` varchar(255) default NULL,
           `corpus` varchar(255) NOT NULL default '',
           `subcorpus` varchar(255) default NULL,
           `unit` varchar(255) default NULL,
           `create_time` int(11) default NULL,
           primary key(`id`)
         ) CHARSET utf8 COLLATE utf8_bin",
		"create table IF NOT EXISTS `saved_matrix_features` (
            `id` int NOT NULL AUTO_INCREMENT,
            `matrix_id` int NOT NULL,
            `label` varchar(255) NOT NULL,
            `source_info` varchar(255) default NULL,
            primary key(`id`)
          ) CHARSET utf8 COLLATE utf8_bin"
	);
	foreach ($sql as $q)
		do_sql_query($q);

	
	/* do the very last DB change! */
	do_sql_query("update system_info set value = '3.1.9' where setting_name = 'db_version'");
}

/* 3.1.7->3.1.8 */
function upgrade_3_1_7()
{
	/* database format has not changed, but format of the postprocess string HAS. 
	 * So perform surgery on the saved-queries table to update it.
	 * 
	 * WARNING: if any new-format queries (using the new "item" postprocess)
	 * have been carried out between the code being updated and this script being run, 
	 * they will be corrupted by the oepration of this script.
	 */
	$count = 0;
	$result = do_sql_query("select query_name, postprocess from saved_queries where postprocess like 'item[%' or postprocess like '%~~item[%'");
	while ($o = mysqli_fetch_object($result))
	{
		$new_pp = preg_replace('/^item\[/', 'item[0~', $o->postprocess);
		$new_pp = preg_replace('/~~item\[/', '~~item[0~', $o->postprocess);
		$new_pp = mysqli_real_escape_string($new_pp);
		do_sql_query("UPDATE saved_queries set postprocess = '$new_pp' where query_name = '{$o->query_name}'");
		$count++;
	}
	echo "The format of $count cached queries has been updated to reflect changes in Frequency Breakdown in v3.1.8.\n\n";
	 
	/* delete databases associated with "item" postprocesses. */
	$result = do_sql_query("select dbname from saved_dbs where postprocess like 'item[%' or postprocess like '%~~item[%'");
	while ($o = mysqli_fetch_object($result))
	{
		do_sql_query("DROP TABLE IF EXISTS {$o->dbname}");
		do_sql_query("DELETE FROM saved_dbs where dbname = '{$o->dbname}'");
	}
	
	/* do the very last DB change! */
	do_sql_query("update system_info set value = '3.1.8' where setting_name = 'db_version'");
}

/* 3.1.4->3.1.5 */
function upgrade_3_1_4()
{
	$sql = array(
		'alter table annotation_template_content add column `order_in_template` smallint unsigned after `template_id`',
		'alter table annotation_template_info add column `primary_annotation` varchar(20) default NULL after `description`',
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	/* do the very last DB change! */
	do_sql_query("update system_info set value = '3.1.5' where setting_name = 'db_version'");
}

/* 3.1.3->3.1.4 */
function upgrade_3_1_3()
{
	$sql = array(
		'alter table user_info modify column `username` varchar(30) charset utf8 collate utf8_bin NOT NULL',
		'create table IF NOT EXISTS `user_captchas` (
		   `id` bigint unsigned NOT NULL AUTO_INCREMENT,
		   `captcha` char(6),
		   `expiry_time` int unsigned,
		   primary key (`id`)
		 ) CHARSET utf8 COLLATE utf8_bin',
		'alter table `annotation_metadata` add column `is_feature_set` tinyint(1) NOT NULL default 0 AFTER `description`',
		'create table IF NOT EXISTS `annotation_template_content` (
			`template_id` int unsigned NOT NULL,
			`handle` varchar(20) NOT NULL,
			`description` varchar(255) default NULL,
			`is_feature_set` tinyint(1) NOT NULL default 0,
			`tagset` varchar(255) default NULL,
			`external_url` varchar(255) default NULL
		) CHARSET utf8 COLLATE utf8_general_ci',
		'create table IF NOT EXISTS `annotation_template_info` (
			`id` int unsigned NOT NULL AUTO_INCREMENT,
			`description` varchar(255) default NULL,
			PRIMARY KEY (`id`)
		) CHARSET utf8 COLLATE utf8_general_ci'
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	/* do the very last DB change! */
	do_sql_query("update system_info set value = '3.1.4' where setting_name = 'db_version'");
}


/* this one is the huge one ....... 3.0.16->3.1.0 */
function upgrade_3_0_16()
{
	/* first, the pre-amendments from v 3.0.15 */
	if (1 > mysqli_num_rows(do_sql_query("show indexes from saved_dbs where Key_name = 'PRIMARY'")))
	{
		if (1 > mysqli_num_rows(do_sql_query("show indexes from saved_dbs where Key_name = 'dbname'")))
			do_sql_query('alter table saved_dbs drop key dbname');
		do_sql_query('alter table saved_dbs add primary key `dbname` (`dbname`)');
	}
	if (1 > mysqli_num_rows(do_sql_query("show indexes from mysql_processes where Key_name = 'PRIMARY'")))
		do_sql_query('alter table mysql_processes add primary key (`dbname`)');
	if (1 > mysqli_num_rows(do_sql_query("show indexes from saved_freqtables where Key_name = 'PRIMARY'")))
		do_sql_query('alter table saved_freqtables add primary key (`freqtable_name`)');
	if (1 > mysqli_num_rows(do_sql_query("show indexes from system_messages where Key_name = 'PRIMARY'")))
	{
		if (1 > mysqli_num_rows(do_sql_query("show indexes from system_messages where Key_name = 'message_id'")))
			do_sql_query('alter table system_messages drop key `message_id`');
		do_sql_query('alter table system_messages add primary key (`message_id`)');
	}
	
	/* now, the main course: 3.0.16 */
	
	$sql = array(
		'alter table user_settings    alter column username set default ""',
		'alter table saved_catqueries alter column corpus set default ""',
		'alter table saved_catqueries alter column dbname set default ""',
		'alter table saved_dbs alter column corpus set default ""',
		'alter table saved_subcorpora alter column subcorpus_name set default ""',
		'alter table saved_subcorpora alter column corpus set default ""',
		'alter table saved_freqtables alter column subcorpus set default ""',
		'alter table saved_freqtables alter column corpus set default ""',
		'alter table system_messages modify header varchar(150) default ""',
		'alter table system_messages modify fromto varchar(150) default NULL',
		'alter table user_macros alter column username set default ""',
		'alter table user_macros alter column macro_name set default ""',
		'alter table user_macros add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table xml_visualisations alter column corpus set default ""',
		'alter table xml_visualisations alter column element set default ""',
		'alter table xml_visualisations drop primary key',
		'alter table xml_visualisations add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table xml_visualisations add unique key(`corpus`, `element`, `cond_attribute`, `cond_regex`)',
		/* The GREAT RENAMING  and rearrangement of main corpus/user tables */
		'rename table mysql_processes to system_processes',
		'rename table user_settings to user_info',
		'rename table corpus_metadata_fixed to corpus_info',
		'alter table user_info drop primary key',
		'alter table user_info add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table user_info add unique key (`username`)',		
		'alter table corpus_info drop primary key',
		'alter table corpus_info add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table corpus_info add unique key (`corpus`)',
		'alter table corpus_categories drop column idno',
		'alter table corpus_categories add column `id` int NOT NULL AUTO_INCREMENT FIRST, add primary key (`id`)',
		'alter table annotation_mapping_tables drop key `id`',
		'update annotation_mapping_tables set id="oxford_simple_tags" where id="oxford_simplified_tags"',
		'update annotation_mapping_tables set id="rus_mystem_classes" where id="russian_mystem_wordclasses"',
		'update annotation_mapping_tables set id="nepali_simple_tags" where id="simplified_nepali_tags"',
		'update corpus_info set tertiary_annotation_tablehandle="oxford_simple_tags" where tertiary_annotation_tablehandle="oxford_simplified_tags"',
		'update corpus_info set tertiary_annotation_tablehandle="rus_mystem_classes" where tertiary_annotation_tablehandle="russian_mystem_wordclasses"',
		'update corpus_info set tertiary_annotation_tablehandle="nepali_simple_tags" where tertiary_annotation_tablehandle="simplified_nepali_tags"'
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	$result = do_sql_query("select id from annotation_mapping_tables where char_length(id) > 20");
	while ($r = mysqli_fetch_row($result))
	{
		list($oldhandle) = $r;
		echo "WARNING. Annotation mapping table handle '$oldhandle' is too long for the new DB version. Please enter one of 20 characters or less.\n";
		for($continue = true; $continue; )
		{
			$newhandle = get_variable_word('a new handle for this table');
			$continue = false;
			
			if (strlen($newhandle) > 20)
			{
				echo "Sorry, that name is too long. 20 characters or less please!\n";
				$continue = true;
			}
			$result = do_sql_query("select id from annotation_mapping_tables where id = $newhandle");
			if (0 < mysqli_num_rows($result))
			{
				echo "Sorry, that handle already exists. Suggest another please!\n";
				$continue = true;
			}
		}
		echo "thank you, replacing the handle now.........\n";
		
		do_sql_query("update annotation_mapping_tables set id='$newhandle' where id='$oldhandle'");
		do_sql_query("update corpus_info set tertiary_annotation_tablehandle='$newhandle' where tertiary_annotation_tablehandle='$oldhandle'");
	}

	/* ok, with that fixed, back to just running lists of commands.... */
	
	$sql = array(
		'alter table annotation_mapping_tables CHANGE `id` `handle` varchar(20) NOT NULL, add primary key (`handle`)',
		/* some new info fields for the corpus table... for use later. */
		'alter table corpus_info add column `is_user_corpus` tinyint(1) NOT NULL default 0',
		'alter table corpus_info add column `date_of_indexing` timestamp NOT NULL default CURRENT_TIMESTAMP',
		/* let's get the system_info table */
		'create table IF NOT EXISTS `system_info` (
		   setting_name varchar(20) NOT NULL collate utf8_bin,
		   value varchar(255),
		   primary key(`setting_name`)
		 ) CHARSET utf8 COLLATE utf8_general_ci',
		"insert into system_info (setting_name, value) VALUES ('db_version',  '3.0.16')",	# bit pointless, but establishes the last-SQL template
		/* now standardise length of usernames across all tables to THIRTY. */
		'alter table user_macros drop key username, CHANGE `username`  `user` varchar(30) NOT NULL, add unique key (`user`, `macro_name`)',
		'alter table user_macros CHANGE macro_name `macro_name` varchar(20) NOT NULL default ""',
		'alter table saved_queries modify `user` varchar(30) default NULL',
		'alter table saved_catqueries modify `user` varchar(30) default NULL',
		'alter table query_history modify `user` varchar(30) default NULL',
		'alter table user_info modify `username` varchar(30) NOT NULL',
		/* new tables for the new username system */
		'create table IF NOT EXISTS `user_groups` (
		   `id` int NOT NULL AUTO_INCREMENT,
		   `group_name` varchar(20) NOT NULL UNIQUE COLLATE utf8_bin,
		   `description` varchar(255) NOT NULL default "",
		   `autojoin_regex` text,
		   primary key (`id`)
		 ) CHARSET utf8 COLLATE utf8_general_ci',
		 'create table IF NOT EXISTS `user_memberships` 
			(`user_id` int NOT NULL,`group_id` int NOT NULL,`expiry_time` int UNSIGNED NOT NULL default 0)
			CHARSET utf8 COLLATE utf8_general_ci',
		'insert into user_groups (group_name,description)values("superusers","Users with admin power")',
		'insert into user_groups (group_name,description)values("everybody","Group to which all users automatically belong")'
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	echo "User groups are managed in the database now, not in the Apache htgroup file.\n";
	echo "If you want to re-enable your old groups, please use load-pre-3.1-groups.php.\n";
	echo "(Please acknowledge.)\n";
	get_enter_to_continue();
	
	/* back to DB changes again */
	
	$sql = array(
		'alter table user_info add column `passhash` char(61) AFTER email',
		'alter table user_info add column `acct_status` tinyint(1) NOT NULL default 0 AFTER passhash',
		/* all existing users count as validated. */
		'update user_info set acct_status = ' . USER_STATUS_ACTIVE,
		'alter table user_info add column `expiry_time` int UNSIGNED NOT NULL default 0 AFTER acct_status',
		'alter table user_info add column `last_seen_time` timestamp NOT NULL default CURRENT_TIMESTAMP AFTER expiry_time',
		'alter table user_info add column `password_expiry_time` int UNSIGNED NOT NULL default 0 AFTER expiry_time',
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	/* CONVERT EXISTING PASSWORDS INTO PASSHASHES */
	echo "about to shift password system over to hashed-values in the database....\n";
	echo "all users whose accounts go back to the era before CQPweb kept passwords in the database will\n";
	echo "have their password changed to the string ``change_me'' (no quotes) and a near-future expiry date set on that password;\n";
	echo "depending on your code version, password expiry may or may not be implemented. (Please acknowledge).\n";
	get_enter_to_continue();
	
	$result = do_sql_query("select username, password from user_info");
	$t = time() + (7 * 24 * 60 * 60);
	while ($o = mysqli_fetch_object($result))
	{
		if (empty($o->password))
		{
			$extra =  ", password_expiry_time = $t";
			$o->password='change_me';
		}
		else
			$extra = '';
		
		$passhash = generate_new_hash_from_password($o->password);
		do_sql_query("update user_info set passhash = '$passhash'$extra where username = '{$o->username}'");
	}
	echo "done transferring passwords to secure encrypted form. Old passwords will NOT be deleted.\n";
	echo "Once you are satisfied the database transfer has worked correctly, you should MANUALLY run\n";
	echo "the following MySQL statement: \n";
	echo "    alter table `user_info` drop column `password`\n";
	echo "Please acknowledge.\n";
	get_enter_to_continue();
	
	/* back to DB changes again */
	
	$sql = array(
		"alter table user_info add column `verify_key` varchar(32) default NULL AFTER acct_status",
		"create table IF NOT EXISTS `user_cookie_tokens` (
			`token` char(33) NOT NULL default '__token' UNIQUE,
			`user_id` int NOT NULL,
			`expiry`  int UNSIGNED NOT NULL default 0
			) CHARSET utf8 COLLATE utf8_bin",
		"alter table user_info modify column `email` varchar(255) default NULL",
		"alter table user_info modify column `realname` varchar(255) default NULL",
		"alter table user_info add column `affiliation` varchar(255) default NULL after `email`",
		"alter table user_info add column `country` char(2) default '00' after `affiliation`",
		"create table IF NOT EXISTS `user_privilege_info` (
			`id` int NOT NULL AUTO_INCREMENT,
			`description` varchar(255) default '',
			`type` tinyint(1) unsigned default NULL,
			`scope` text,
			primary key(`id`)
			) CHARSET utf8 COLLATE utf8_general_ci",
		"create table IF NOT EXISTS `user_grants_to_groups` 
			(`group_id` int NOT NULL,`privilege_id` int NOT NULL,`expiry_time` int UNSIGNED NOT NULL default 0) 
			CHARSET utf8 COLLATE utf8_general_ci",
		"create table IF NOT EXISTS `user_grants_to_users` 
			(`user_id` int NOT NULL,`privilege_id` int NOT NULL,`expiry_time` int UNSIGNED NOT NULL default 0) 
			CHARSET utf8 COLLATE utf8_general_ci",
		"alter table user_info add column `acct_create_time` timestamp NOT NULL default 0 after `last_seen_time`"
	);
	foreach ($sql as $q)
		do_sql_query($q);
	
	echo "User privileges are managed in the database now, not in Apache htaccess files.\n";
	echo "If you want to re-import your old group access privileges, please use load-pre-3.1-privileges.php.\n";
	echo "(Please acknowledge.)\n";
	get_enter_to_continue();
	
	/* do the very last DB change! */
	do_sql_query("update system_info set value = '3.1.0' where setting_name = 'db_version'");
}




