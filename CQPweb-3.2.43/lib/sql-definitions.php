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


function get_array_of_sql_type_definitions($which = NULL)
{
	
	/*
	 * STRING FIELD LENGTHS TO USE
	 * ===========================
	 *  
	 * EXCEPTION: dbname is 200 because historically it was built from many components. 
	 * However,now its maxlength = 'db_catquery_' (12) plus the length of an instance name (which is 10).
	 * But we are keeping it at 200 for now because old data could be lost otherwise.
	 * 
	 * Long string (for names, descriptions, etc) - varchar 255
	 * 
	 * Handles - are informed by the limits of MySQL. Most MySQL identfiiers are limited to 64 chars.
	 * =======
	 *  - the corpus:      20  (as needs to be a sub-part of a table name)
	 *  - p-attributes:    20  (also need to be a sub-part of a table name)
	 *  - s-attributes:    64  (as may need to be a column name)
	 *  - metadata fields: 64  (as may need to be a column name)
	 *  - username:        64
	 *  - metadata values: 200 (as they may need to be part of a key made up of multiple varchars)
	 *  - save names:      200 (to fit with the above)
	 *  - unique item ID:  255 (as they may need to be a key on their own) 
	 *  
	 *  See also constant definitions in environment.php
	 */
	
	$strlen_sql_table    = 64;
	$strlen_corpus       = HANDLE_MAX_CORPUS;
	$strlen_annotation   = HANDLE_MAX_ANNOTATION;
	$strlen_xml          = HANDLE_MAX_XML;
	$strlen_username     = HANDLE_MAX_USERNAME;
	$strlen_field        = HANDLE_MAX_FIELD;
	$strlen_category     = HANDLE_MAX_CATEGORY;
	$strlen_savename     = HANDLE_MAX_SAVENAME;
	$strlen_item_id      = HANDLE_MAX_ITEM_ID;
	$strlen_general      = 255; /* no especial reason to limit L of strings that don't have to be indexed */
	$strlen_general_ixed = 191; /* for utf8mb4: 191 * 4 = 764, longest possible size of index = 767. */
	$strlen_general_ixed = 255; /* for utf8mb3: 255 * 3 = 765, longest possible size of index = 767. */
	
	
	
	goto jump_over_inactive_code;
	$collate_for_qdata   = best_sql_collation(true, true);
	$collate_for_text    = best_sql_collation(false, false);
	
	$charset_for_qdata   = "CHARSET utf8mb4 collate $collate_for_qdata";
	$charset_for_text    = "CHARSET utf8mb4 collate $collate_for_text"; 
jump_over_inactive_code:
	
	$charset_for_handle  = 'CHARSET ascii COLLATE ascii_bin';
// temp override before mb4
$charset_for_qdata = 'CHARSET utf8 COLLATE utf8_bin';
$charset_for_text  = 'CHARSET utf8 COLLATE utf8_general_ci';
	
//nb, new goal is for ALL columns to have a specified charset / collate/
//databse will be utf8 utf8_bin    ...     //at table level, no specification. (BUT set existing to utf8mb4_bin, justin case)
	
	
	$timestamp_zero = '1970-01-01 00:00:01';
	/* IMPORTANT NOTE: this cannot be used unless "set time_zone = '+00:00'" has been run first.
	 * MySQL/MariaDB always timezone-converts things going into a timestamp field, so if we're on 
	 * GMT (or whatever), we'll get a wrong zero-date. Or, if the TZ-conversion pushes it back into
	 * 1969, we might even get an error, because timezones are limited to the same set of values 
	 * as Unixtime. */   

	/* nb, the 3 key types DO NOT cover primary/foreign keys that are varchar. Of which many do persist. See $type_handle_key. */
	$type_primary_key_id      = 'int unsigned NOT NULL AUTO_INCREMENT';  
	$type_primary_key_id_big  = 'big' . $type_primary_key_id;             
	$type_foreign_key_id      = 'int unsigned NOT NULL';
	$type_bool_falsy          = 'tinyint(1) NOT NULL default 0';                        /* boolean, false by default */
	$type_bool_truthy         = 'tinyint(1) NOT NULL default 1';                        /* boolean, true by default */
	$type_unixtime            = 'int unsigned NOT NULL default 0';                      /* n of seconds since 1970; or, a duration in seconds */
	$type_datatype            = 'tinyint(2) NOT NULL default '.METADATA_TYPE_NONE;      /* one of the METADATA_ constants */
	$type_datatype_safe       = 'tinyint(2) NOT NULL default '.METADATA_TYPE_FREETEXT;  /* one of the METADATA_ constants; safe default */
	$type_symbolic_const      = 'tinyint(2) unsigned NOT NULL default 0';               /* misc constants. We assume the 0 value is unknown/errro/summat sutable. */
	$type_sequence_order      = 'smallint unsigned NOT NULL default 0';                 /* represents order in some sequence. */
	$type_nondata_string      = "varchar($strlen_general) $charset_for_text default ''";
	$type_nondata_text        = "text $charset_for_text";
	$type_qdata_string        = "varchar($strlen_general) $charset_for_qdata default ''";
	$type_qdata_text          = "text $charset_for_qdata";
	$type_n_cpos_bytes        = 'bigint unsigned NOT NULL default 0';                   /* for counts of tokens, types, bytes (can exceed 4 billion) */
	$type_n_objects           = 'int unsigned NOT NULL default 0';                      /* for counts of things like texts, speakers etc. */
	$type_date_historic       = 'timestamp NOT NULL default CURRENT_TIMESTAMP';         /* for unmodifiable timestamps */
	$type_date_tracker        = 'timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP';         /* for modifiable timestamps */
	$type_date_zero           = 'timestamp NOT NULL default "'.$timestamp_zero.'"';     /* manually modifiable timestamps, with a zero default */
	$type_sql_table_name      = "varchar($strlen_sql_table) $charset_for_handle NOT NULL";
	
	$type_qmode               = 'enum ("cqp", "sq_case", "sq_nocase", "uploaded") NOT NULL';
	$type_linefeed            = 'enum ("au", "a", "d", "da") NOT NULL';
	
	$type_handle_key              = new stdClass;  
	/* primary or foreign key string IDs referencing a handle that can't be NULL */
	$type_handle_key->corpus      = "varchar($strlen_corpus)     $charset_for_handle NOT NULL"; 
	$type_handle_key->annotation  = "varchar($strlen_annotation) $charset_for_handle NOT NULL"; 
	$type_handle_key->xml         = "varchar($strlen_xml)        $charset_for_handle NOT NULL"; 
	$type_handle_key->username    = "varchar($strlen_username)   $charset_for_handle NOT NULL"; 
	$type_handle_key->field       = "varchar($strlen_field)      $charset_for_handle NOT NULL"; 
	$type_handle_key->category    = "varchar($strlen_category)   $charset_for_handle NOT NULL"; 
	$type_handle_key->savename    = "varchar($strlen_savename)   $charset_for_handle NOT NULL"; 
	$type_handle_key->item_id     = "varchar($strlen_item_id)    $charset_for_handle NOT NULL";

	/* key string IDs that are references that are capable of being empty, IE NULL */
	$type_handle_ref = clone $type_handle_key;
	foreach(['corpus','annotation','xml','username','field','category','savename','item_id'] as $k)
		$type_handle_ref->$k = str_replace('NOT NULL', 'default NULL', $type_handle_ref->$k);
	

	$defs = compact(
			'strlen_sql_table'
		,	'strlen_corpus'
		,	'strlen_annotation'
		,	'strlen_xml'
		,	'strlen_username'
		,	'strlen_field'
		,	'strlen_category'
		,	'strlen_savename'
		,	'strlen_item_id'
		,	'strlen_general'
		,	'strlen_general_ixed'
		,	'charset_for_handle'
		,	'charset_for_qdata'
		,	'charset_for_text'
		,	'timestamp_zero'
		,	'type_primary_key_id'
		,	'type_primary_key_id_big'
		,	'type_foreign_key_id'
		,	'type_bool_falsy'
		,	'type_bool_truthy'
		,	'type_unixtime'
		,	'type_datatype'
		,	'type_datatype_safe'
		,	'type_symbolic_const'
		,	'type_sequence_order'
		,	'type_nondata_string'
		,	'type_nondata_text'
		,	'type_qdata_string'
		,	'type_qdata_text'
		,	'type_n_cpos_bytes'
		,	'type_n_objects'
		,	'type_date_historic'
		,	'type_date_tracker'
		,	'type_date_zero'
		,	'type_sql_table_name'
		,	'type_qmode'
		,	'type_linefeed'
		,	'type_handle_key'
		,	'type_handle_ref'
	);	

	return $defs;
}




/**
 * Returns the create-table statements for setup as an array.
 * 
 * @return array   Array keys are the table names.
 */
function get_sql_create_tables()
{
	$create_statements = array();
	

	/* 
	 * IMPORTANT NOTE.
	 * 
	 * MySQL 5.5.5 (in Q2 2010) changed the default storage engine to InnoDB. 
	 * 
	 * CQPweb was originally based on the assumption that the engine would be MyISAM and
	 * thus, several of the statements below contained MyISAM-isms.
	 * 
	 * In Nov 2013, the MyISAM-isms were removed, so it will still work with the default InnoDB.
	 * HOWEVER, fulltext index was not added to InnoDB until 5.6 (rel in Feb 2013).
	 * 
	 * Similarly, MsriaDB only got InnoDB/XtraDB fulltext indexes in v10.0.5 (Nov 2013).
	 * 
	 * The following checks the versioning of both.
	 * 
	 * Note that we need InnoDB to allow tables to be stored in different locations 
	 * (i.e. on different drives if necessary....)
	 */
	$link = get_global_sql_link();
	list($major, $minor, $rest) = explode('.', mysqli_get_server_info($link), 3);
	if (preg_match('/-(\d+)\.(\d+)\.(\d+)\.-mariadb-/i', $rest, $m))
	{
		/* mariadb: need 10.0.5 */
		list(, $major, $minor, $incr) = $m;
		$engine_if_fulltext_key_needed = ( ($major > 10 || ($major == 10 && $minor >= 1) || ($major == 10 && $incr >= 5) )  ? 'ENGINE=InnoDB' : 'ENGINE=MyISAM');
	}
	else
		$engine_if_fulltext_key_needed = ( ($major > 5 || ($major == 5 && $minor >= 6) ) ? 'ENGINE=InnoDB' : 'ENGINE=MyISAM');

	$engine = 'ENGINE=InnoDB';

	
	$defs                     = get_array_of_sql_type_definitions();
	$strlen_sql_table         = $defs['strlen_sql_table'];
	$strlen_corpus            = $defs['strlen_corpus'];
	$strlen_annotation        = $defs['strlen_annotation'];
	$strlen_xml               = $defs['strlen_xml'];
	$strlen_username          = $defs['strlen_username'];
	$strlen_field             = $defs['strlen_field'];
	$strlen_category          = $defs['strlen_category'];
	$strlen_savename          = $defs['strlen_savename'];
	$strlen_item_id           = $defs['strlen_item_id'];
	$strlen_general           = $defs['strlen_general'];
	$strlen_general_ixed      = $defs['strlen_general_ixed'];
	$charset_for_handle       = $defs['charset_for_handle'];
	$charset_for_qdata        = $defs['charset_for_qdata'];
	$charset_for_text         = $defs['charset_for_text'];
	$timestamp_zero           = $defs['timestamp_zero'];
	$type_primary_key_id      = $defs['type_primary_key_id']; 
	$type_primary_key_id_big  = $defs['type_primary_key_id_big'];      
	$type_foreign_key_id      = $defs['type_foreign_key_id'];
	$type_bool_falsy          = $defs['type_bool_falsy'];
	$type_bool_truthy         = $defs['type_bool_truthy'];
	$type_unixtime            = $defs['type_unixtime'];
	$type_datatype            = $defs['type_datatype'];
	$type_datatype_safe       = $defs['type_datatype_safe'];
	$type_symbolic_const      = $defs['type_symbolic_const'];
	$type_sequence_order      = $defs['type_sequence_order'];
	$type_nondata_string      = $defs['type_nondata_string'];
	$type_nondata_text        = $defs['type_nondata_text'];
	$type_qdata_string        = $defs['type_qdata_string'];
	$type_qdata_text          = $defs['type_qdata_text'];
	$type_n_cpos_bytes        = $defs['type_n_cpos_bytes'];
	$type_n_objects           = $defs['type_n_objects'];
	$type_date_historic       = $defs['type_date_historic'];
	$type_date_tracker        = $defs['type_date_tracker'];
	$type_date_zero           = $defs['type_date_zero'];
	$type_sql_table_name      = $defs['type_sql_table_name'];
	$type_qmode               = $defs['type_qmode'];
	$type_linefeed            = $defs['type_linefeed'];
	$type_handle_key          = $defs['type_handle_key'];
	$type_handle_ref          = $defs['type_handle_ref'];
	
	
	/*
	 * OK, the actual create statements begin here.
	 * ============================================
	 */
	
	
	/* nb it is somewhat inconsistent that here "name" = long desc rather than short handle. TODO change to "description".  */
	$create_statements['annotation_mapping_tables'] =
		"CREATE TABLE `annotation_mapping_tables` (
			`handle`                     $type_handle_key->item_id,
			`name`                       $type_nondata_string, 
			`mappings`                   $type_qdata_text,
			primary key (`handle`)
		) $engine ";
	
	
	$create_statements['annotation_metadata'] =
		"CREATE TABLE `annotation_metadata` (
			`corpus`                     $type_handle_key->corpus,
			`handle`                     $type_handle_key->annotation,
			`description`                $type_nondata_string,
			`is_feature_set`             $type_bool_falsy,
			`tagset`                     $type_nondata_string,
			`external_url`               $type_nondata_string,
			primary key (`corpus`, `handle`)
		) $engine ";
	
	
	$create_statements['annotation_template_info'] =
		"CREATE TABLE `annotation_template_info` (
			`id`                         $type_primary_key_id,
			`description`                $type_nondata_string,
			`primary_annotation`         $type_handle_ref->annotation,
			primary key (`id`)
		) $engine ";


	$create_statements['annotation_template_content'] =
		"CREATE TABLE `annotation_template_content` (
			`template_id`                $type_foreign_key_id,
			`order_in_template`          $type_sequence_order,
			`handle`                     $type_handle_key->annotation,
			`description`                $type_nondata_string,
			`is_feature_set`             $type_bool_falsy,
			`tagset`                     $type_nondata_string,
			`external_url`               $type_nondata_string
		) $engine ";
	
	
	$create_statements['catdesc_template_info'] =
		"CREATE TABLE `catdesc_template_info` (
			`id`                         $type_primary_key_id,
			`description`                $type_nondata_string,
			primary key (`id`)
		) $engine ";


	$create_statements['catdesc_template_content'] =
		"CREATE TABLE `catdesc_template_content` (
			`template_id`                $type_foreign_key_id,
			`order_in_template`          $type_sequence_order,
			`handle`                     $type_handle_key->category,
			`description`                $type_nondata_string
		) $engine ";
	
	
	$create_statements['corpus_alignments'] = 
		"CREATE TABLE `corpus_alignments` (
			`corpus`                     $type_handle_key->corpus,
			`target`                     $type_handle_key->corpus
		) $engine ";

	
	$create_statements['corpus_categories'] =
		"CREATE TABLE `corpus_categories` (
			`id`                         $type_primary_key_id,
			`label`                      $type_nondata_string,
			`sort_n`                     smallint NOT NULL DEFAULT 0,  ## note: this CAN be negative. 
			primary key (`id`)
		) $engine ";
	
	
	$create_statements['corpus_info'] =
		"CREATE TABLE `corpus_info` (
			/* 
			 * General fields: identity, CWB data addresses, && size 
			 */
			`id`                         $type_primary_key_id,
			`corpus`                     $type_handle_key->corpus,
											# NB. This is always 100% the same as the handle in the PHP code.
			`cqp_name`                   varchar($strlen_general) $charset_for_handle NOT NULL default '',   
											# Needed because cwb_external might be true...
			`date_of_indexing`           $type_date_historic,
			`cwb_external`               $type_bool_falsy,
			`language`                   char(3) $charset_for_handle NOT NULL DEFAULT 'und',
			`owner`                      varchar($strlen_username) $charset_for_handle default NULL,
			`size_tokens`                $type_n_cpos_bytes,
			`size_types`                 $type_n_cpos_bytes,
			`size_texts`                 $type_n_objects,
			`size_bytes_index`           $type_n_cpos_bytes,
			`size_bytes_freq`            $type_n_cpos_bytes,
			`sttr_1kw`                   float default 0.0,

			/* 
			 * Licensing and access info
			 */
			`access_statement`           $type_nondata_text,

			/* 
			 * Search & analysis settings
			 */
			`primary_classification_field`  
			                             $type_handle_key->field,
			`uses_case_sensitivity`      $type_bool_falsy,
## TODO diacritic here (or not)

			/* 
			 * System display: how the corpus is listed / appears in the interface
			 */
			`visible`                    $type_bool_truthy,
			`title`                      $type_nondata_string, 
			`corpus_cat`                 int NOT NULL default 1,
			`external_url`               $type_nondata_string,
			`css_path`                   varchar($strlen_general) $charset_for_qdata default '../css/CQPweb-blue.css',

			/* 
			 * Annotation (p-attribute) info fields
			 */
			`primary_annotation`         $type_handle_ref->annotation,
			`secondary_annotation`       $type_handle_ref->annotation,
			`tertiary_annotation`        $type_handle_ref->annotation,
			`tertiary_annotation_tablehandle`
			                             varchar($strlen_item_id)    $charset_for_handle default NULL,
			`combo_annotation`           $type_handle_ref->annotation,

			/* 
			 * Concordance/Context: appearance and visualisation control
			 */
			`main_script_is_r2l`         $type_bool_falsy,
			`conc_s_attribute`           varchar($strlen_xml) $charset_for_handle NOT NULL default '', # default for this + next translates to 12 words.
			`conc_scope`                 smallint unsigned NOT NULL default 12,
			`initial_extended_context`   smallint unsigned NOT NULL default 100,
			`max_extended_context`       smallint unsigned NOT NULL default 1100,
			`alt_context_word_att`       $type_handle_ref->annotation,
			
##TODO viz might be better prefix. 
			`visualise_gloss_in_concordance`     $type_bool_falsy,
			`visualise_gloss_in_context`         $type_bool_falsy,
			`visualise_gloss_annotation`         $type_handle_ref->annotation,
			`visualise_translate_in_concordance` $type_bool_falsy,
			`visualise_translate_in_context`     $type_bool_falsy,
			`visualise_translate_s_att`          $type_handle_ref->xml,
			`visualise_position_labels`          $type_bool_falsy,
			`visualise_position_label_attribute` $type_handle_ref->xml,
			`visualise_conc_extra_js`            $type_qdata_string,
			`visualise_context_extra_js`         $type_qdata_string,
			`visualise_conc_extra_css`           $type_qdata_string,
			`visualise_context_extra_css`        $type_qdata_string,
			`visualise_break_context_on_punc`    $type_bool_truthy,

			/* 
			 * Housekeeping
			 */
			`indexing_notes`             $type_nondata_text,

			unique key (`corpus`),
			primary key (`id`)

		) $engine ";

	
	$create_statements['corpus_metadata_variable'] =
		"CREATE TABLE `corpus_metadata_variable` (
			`corpus`                     $type_handle_key->corpus,
			`attribute`                  $type_nondata_text NOT NULL,
			`value`                      $type_nondata_text,
			key (`corpus`)
		) $engine ";


	$create_statements['embedded_pages'] = 
		"CREATE TABLE `embedded_pages` (
			`id`                         $type_primary_key_id,
			`title`                      $type_nondata_string,
			`file_path`                  $type_nondata_string,
			primary key (`id`)
		) $engine ";


	$create_statements['idlink_fields'] =
		"CREATE TABLE `idlink_fields` (
			`corpus`                     $type_handle_key->corpus,
			`att_handle`                 $type_handle_key->xml,
			`handle`                     $type_handle_key->field,
			`description`                $type_nondata_string,
			`datatype`                   $type_datatype,
			primary key (`corpus`, `att_handle`, `handle`)
	) $engine ";

	
	$create_statements['idlink_values'] =
		"CREATE TABLE `idlink_values` (
			`corpus`                     $type_handle_key->corpus,
			`att_handle`                 $type_handle_key->xml,
			`field_handle`               $type_handle_key->field,
			`handle`                     $type_handle_key->category,
			`description`                $type_nondata_string,
			`category_n_items`           $type_n_objects,                   ### could just be cat_n_items / n_items
			`category_n_tokens`          $type_n_cpos_bytes,
			primary key (`corpus`, `att_handle`, `field_handle`, `handle`)
	) $engine ";


	$create_statements['lgcurve_info'] = 
		"CREATE TABLE `lgcurve_info` (
			`id`                         $type_primary_key_id,
			`corpus`                     $type_handle_key->corpus,
			`annotation`                 $type_handle_key->annotation,
			`interval_width`             smallint unsigned NOT NULL default 0,
			`create_time`                $type_unixtime,
			`create_duration`            $type_unixtime,
			`n_datapoints`               $type_n_objects,
			primary key (`id`),
			unique key (`corpus`, `annotation`, `interval_width`)
		) $engine ";
	
	
	$create_statements['lgcurve_datapoints'] = 
		"CREATE TABLE `lgcurve_datapoints` (
			`lgcurve_id`                 $type_foreign_key_id,
			`tokens`                     $type_n_cpos_bytes,
			`types_so_far`               $type_n_cpos_bytes,
			key (`lgcurve_id`)
		) $engine ";
	
	
	$create_statements['metadata_template_info'] = 
		"CREATE TABLE `metadata_template_info` (
			`id`                         $type_primary_key_id,
			`description`                $type_nondata_string,
			`primary_classification`     $type_handle_ref->field,
			primary key (`id`)
		) $engine ";


	$create_statements['metadata_template_content'] =
		"CREATE TABLE `metadata_template_content` (
			`template_id`                $type_foreign_key_id,
			`order_in_template`          $type_sequence_order,
			`handle`                     $type_handle_key->field,
			`description`                $type_nondata_string,
			`datatype`                   $type_datatype_safe
		) $engine ";


	$create_statements['plugin_registry'] =
		"CREATE TABLE `plugin_registry` (
			`id`                         $type_primary_key_id,
			`class`                      $type_qdata_string,
			`type`                       $type_symbolic_const,
			`description`                $type_nondata_string,
			`extra`                      $type_qdata_text,
			primary key (`id`)
		) $engine ";


	$create_statements['query_history'] =
		"create table query_history (
			`id`                         $type_primary_key_id_big,
			`user`                       $type_handle_ref->username,
			`corpus`                     $type_handle_ref->corpus,
			`cqp_query`                  $type_qdata_text NOT NULL,
			`query_scope`                $type_qdata_text,
			`date_of_query`              $type_date_historic,
			`hits`                       int(11) default NULL, ### TODO bigint?? could be made unsigned if there was an enum for -1, -3 (EG called `outcome`)
			`simple_query`               $type_qdata_text,
			`query_mode`                 $type_qmode,
			key (`user`,`corpus`),
			key (`cqp_query`($strlen_general_ixed)),
			primary key (`id`)
		) $engine ";

	
	$create_statements['saved_catqueries'] =
		"CREATE TABLE `saved_catqueries` (
			`id`                         $type_primary_key_id,                     #### we don't use yet but we will....
			`catquery_name`              varchar(150) $charset_for_handle NOT NULL,   ## why 150? to match query_name.
			`user`                       $type_handle_ref->username,
			`corpus`                     $type_handle_ref->corpus,
			`dbname`                     $type_sql_table_name,
			`category_list`              text $charset_for_handle ,    ### might be better in a table?
			key (`catquery_name`),
			key (`user`),
			key (`corpus`),
			primary key (`id`)
### might not a joint user/corpus key be better than having them separately?
		) $engine ";


	$create_statements['saved_dbs'] =
		"CREATE TABLE `saved_dbs` (
			`dbname`                     $type_sql_table_name,
			`user`                       $type_handle_ref->username,
			`create_time`                $type_unixtime,
			`cqp_query`                  $type_qdata_text NOT NULL,
			`query_scope`                $type_qdata_text,
			`postprocess`                $type_qdata_text,
			`corpus`                     $type_handle_ref->corpus,
			`db_type`                    varchar(15) $charset_for_handle default NULL, ## TODO change to enum or symbolic const
			`colloc_atts`                varchar(1024) $charset_for_handle default '',
			`colloc_range`               smallint NOT NULL default 0,
			`sort_position`              smallint NOT NULL default 0,
			`db_size`                    $type_n_cpos_bytes,
			`saved`                      $type_bool_falsy,
			primary key (`dbname`),
			key (`user`),
			key (`corpus`)
## TODO should there not be a joint key for parameter lookup as well? key `query_params`(various things here?)
		) $engine ";
	
	
	$create_statements['saved_freqtables'] =
		"CREATE TABLE `saved_freqtables` (
			`freqtable_name`             varchar(43) $charset_for_handle NOT NULL,    
											### NB, this is an overhead (frec_sc_ : 8) plus corpus (20+1) plus an instance name of indeterminte legbnth; THEN WE MUST ADD and annotation (20+1)   
			`corpus`                     $type_handle_ref->corpus,
			`user`                       $type_handle_ref->username,
			`query_scope`                $type_qdata_text,
			`create_time`                $type_unixtime,
			`ft_size`                    $type_n_cpos_bytes,
			`public`                     $type_bool_falsy,
			primary key (`freqtable_name`),
			key `query_scope` (`query_scope`($strlen_general))
		) $engine ";
	

	$create_statements['saved_matrix_info'] =
		"CREATE TABLE `saved_matrix_info` (
			`id`                         $type_primary_key_id,
			`savename`                   $type_handle_key->savename,
			`user`                       $type_handle_ref->username,
			`corpus`                     $type_handle_ref->corpus,
			`subcorpus`                  $type_handle_ref->savename,   #TODO this could be changed to an integer, couldn't it?? subcorpus_id
			`unit`                       $type_handle_ref->xml,   ## TODO. is this an s-att? if so, $type_handle_ref->xml
			`create_time`                $type_unixtime,
			primary key (`id`)
		) $engine ";
	
	
	$create_statements['saved_matrix_features'] = 
		"CREATE TABLE `saved_matrix_features` (
			`id`                         $type_primary_key_id,
			`matrix_id`                  $type_foreign_key_id,
			`label`                      $type_nondata_string,
			`source_info`                $type_nondata_string,
			primary key (`id`)
		) $engine ";


	$create_statements['saved_queries'] =
		"CREATE TABLE `saved_queries` (
			`query_name`                 varchar(150) $charset_for_handle NOT NULL,    ### why 150???
			`user`                       $type_handle_ref->username,
			`corpus`                     $type_handle_ref->corpus,
			`query_mode`                 $type_qmode,
			`simple_query`               $type_qdata_text,
			`cqp_query`                  $type_qdata_text NOT NULL,
			`query_scope`                $type_qdata_text,
			`postprocess`                $type_qdata_text,
			`time_of_query`              $type_unixtime,                  #### query_time better? Or, cache_time?
			`hits`                       $type_n_cpos_bytes,
			`hits_left`                  text $charset_for_handle, 
			`hit_texts`                  $type_n_objects,
			`file_size`                  $type_n_cpos_bytes,
			`saved`                      $type_symbolic_const,  ### RENAME `SAVE_STATUS` ???
			`save_name`                  $type_handle_key->savename,
			KEY (`query_name`),
			KEY (`user`),
			KEY (`corpus`),
			KEY (`time_of_query`),
			FULLTEXT KEY (`cqp_query`),
			FULLTEXT KEY (`query_scope`),
			FULLTEXT KEY (`postprocess`)
		) $engine_if_fulltext_key_needed ";
	
	
	$create_statements['saved_restrictions'] =
		"CREATE TABLE `saved_restrictions` (
			`id`                         $type_primary_key_id_big,
			`cache_time`                 $type_unixtime, 
			`corpus`                     $type_handle_ref->corpus,
			`serialised_restriction`     $type_qdata_text,
			`n_items`                    $type_n_objects,
			`n_tokens`                   $type_n_cpos_bytes,
			`data`                       longblob,
			primary key (`id`),
			key (`corpus`),
			key (`serialised_restriction`($strlen_general_ixed))
		) $engine ";


	$create_statements['saved_subcorpora'] =
		"CREATE TABLE `saved_subcorpora` (
			`id`                         $type_primary_key_id_big,
			`name`                       $type_handle_key->savename,    ### TODO any reason why these must be handles?? Likewise for all other savenames, why can't they be  freetext?
			`corpus`                     $type_handle_key->corpus,
			`user`                       $type_handle_key->username,   ## better if twas a foreign key user_id. 
			`content`                    medium$type_qdata_text,
			`n_items`                    $type_n_objects,
			`n_tokens`                   $type_n_cpos_bytes,
			primary key (`id`),
			key (`corpus`, `user`)
		) $engine CHARSET utf8 COLLATE utf8_bin";
	
	
	$create_statements['system_info'] =
		"CREATE TABLE `system_info` (
			setting_name                 $type_handle_key->item_id,
			value                        $type_qdata_string,
			primary key (`setting_name`)
		) $engine "; 


	$create_statements['system_longvalues'] =
		"CREATE TABLE `system_longvalues` (
			`id`                         $type_primary_key_id,
			`date_of_storing`            $type_date_tracker,
			`value`                      longtext $charset_for_qdata NOT NULL,
			primary key (`id`)
		) $engine ";
	
	
	$create_statements['system_messages'] =
		"CREATE TABLE `system_messages` (
			`id`                         $type_primary_key_id,
			`date_of_posting`            $type_date_tracker,
			`header`                     $type_nondata_string,
			`content`                    $type_nondata_text,
			`fromto`                     $type_qdata_string, ## nb if ever used, we should use a pair of foreign key ids.
			primary key (`id`)
		) $engine ";


	$create_statements['system_processes'] =
		"CREATE TABLE `system_processes` (
			`dbname`                     $type_sql_table_name,
			`begin_time`                 $type_unixtime,  
			`process_type`               varchar(15) $charset_for_handle default NULL,
			`process_id`                 varchar(15) $charset_for_handle default NULL,
			primary key (`dbname`)
		) $engine "; 


	$create_statements['text_metadata_fields'] =
		"CREATE TABLE `text_metadata_fields` (
			`corpus`                     $type_handle_key->corpus,
			`handle`                     $type_handle_key->field,
			`description`                $type_nondata_string,
			`datatype`                   $type_datatype_safe,
			primary key (`corpus`, `handle`)
		) $engine ";


	$create_statements['text_metadata_values'] =
		"CREATE TABLE `text_metadata_values` (
			`corpus`                     $type_handle_key->corpus,
			`field_handle`               $type_handle_key->field,
			`handle`                     $type_handle_key->category,
			`description`                $type_nondata_string,
			`category_num_files`         $type_n_objects,    ## rename to cat_n_texts. Or just n_texts, n_items
			`category_num_words`         $type_n_cpos_bytes, ## rename to cat_n_tokens   .... cf idlink values, xml_values
			primary key (`corpus`, `field_handle`, `handle`)
		) $engine ";


	$create_statements['user_captchas'] = 
		"CREATE TABLE `user_captchas` (
			`id`                         $type_primary_key_id_big,
			`captcha`                    char(6) CHARSET ascii COLLATE ascii_bin,
			`expiry_time`                $type_unixtime,
			primary key (`id`)
		) $engine ";


	$create_statements['user_colleague_grants'] = 
		"CREATE TABLE `user_colleague_grants` (
			`corpus_id`                  $type_foreign_key_id, 
			`grantee_id`                 $type_foreign_key_id, 
			`comment`                    $type_nondata_string,
			key (`grantee_id`) 
		) $engine ";


	$create_statements['user_colleague_links'] = 
		"CREATE TABLE `user_colleague_links` (
			`from_id`                    $type_foreign_key_id,
			`to_id`                      $type_foreign_key_id, 
			`status`                     $type_symbolic_const
		) $engine ";


	$create_statements['user_cookie_tokens'] =
		"CREATE TABLE `user_cookie_tokens` (
			`token`                      char(64) CHARSET ascii COLLATE ascii_bin NOT NULL default '',
			`user_id`                    $type_foreign_key_id,
			`creation`                   $type_unixtime,
			`expiry`                     $type_unixtime,
			key (`token`, `user_id`)
		) $engine ";


	$create_statements['user_grants_to_users'] =
		"CREATE TABLE `user_grants_to_users` (
			`user_id`                    $type_foreign_key_id,
			`privilege_id`               $type_foreign_key_id,
			`expiry_time`                $type_unixtime
		) $engine ";
	
	
	$create_statements['user_grants_to_groups'] =
		"CREATE TABLE `user_grants_to_groups` (
			`group_id`                   $type_foreign_key_id,
			`privilege_id`               $type_foreign_key_id,
			`expiry_time`                $type_unixtime
		) $engine ";
	
	
	$create_statements['user_groups'] =
		"CREATE TABLE `user_groups` (
			`id`                         $type_primary_key_id,
			`group_name`                 $type_handle_key->username, 
			`description`                $type_nondata_string,
			`autojoin_regex`             $type_qdata_text,
			unique key (`group_name`),
			primary key (`id`)
		) $engine";


	$create_statements['user_info'] =
		"CREATE TABLE `user_info` (
			`id`                         $type_primary_key_id,
			`username`                   $type_handle_key->username,
			`realname`                   $type_nondata_string,
			`email`                      $type_nondata_string,
			`affiliation`                $type_nondata_string,
			`country`                    char(2)  CHARSET ascii COLLATE ascii_bin default '00',
			`passhash`                   char(61) CHARSET ascii COLLATE ascii_bin default '',
			`acct_status`                $type_symbolic_const, 
			`verify_key`                 varchar(32) CHARSET ascii COLLATE ascii_bin default NULL,
			`expiry_time`                $type_unixtime,
			`password_locked`            $type_bool_falsy,
			`password_expiry_time`       $type_unixtime,
			`last_seen_time`             $type_date_zero,  ### TODO  rename to date_last_visited
			`acct_create_time`           $type_date_historic,   ### TODO  rename to date_acct_created
			`conc_kwicview`              $type_bool_truthy,
			`conc_corpus_order`          $type_bool_truthy,
			`cqp_syntax`                 $type_bool_falsy,
			`context_with_tags`          $type_bool_falsy,
			`use_tooltips`               $type_bool_truthy,
			`css_monochrome`             $type_bool_falsy,
			`thin_default_reproducible`  $type_bool_truthy,
			`coll_statistic`             $type_symbolic_const, 
##TODO,`key_statistic`   $type_symbolic_const
				## the collocation prefs default to 0 because their defaults are set in config.php, and imported into new user records. 
			`coll_freqtogether`          smallint unsigned NOT NULL default 0, 
			`coll_freqalone`             smallint unsigned NOT NULL default 0, 
			`coll_from`                  tinyint NOT NULL default 0,
			`coll_to`                    tinyint NOT NULL default 0,
			`max_dbsize`                 int unsigned default NULL,
			`freqlist_altstyle`          $type_bool_falsy, 
			`linefeed`                   $type_linefeed,
			unique key (`username`),
			primary key (`id`)
		) $engine ";
	
	
	$create_statements['user_installer_processes'] = 
		"CREATE TABLE `user_installer_processes` (
			`id`                         $type_primary_key_id,
			`corpus_id`                  $type_foreign_key_id,
			`user_id`                    $type_foreign_key_id,
			`plugin_reg_id`              $type_foreign_key_id,
			`php_pid`                    $type_foreign_key_id,
			`status`                     $type_symbolic_const, 
			`last_status_change`         $type_date_tracker,
			`error_message`              $type_nondata_string, 
			primary key (`id`)
		) $engine ";
	

	
	$create_statements['user_macros'] =
		"CREATE TABLE `user_macros` (
			`id`                         $type_primary_key_id,
			`user`                       $type_handle_key->username,
			`macro_name`                 $type_handle_key->item_id,
			`macro_num_args`             smallint unsigned default 0,
			`macro_body`                 $type_qdata_text,
			key (`user`), 
			unique key (`user`, `macro_name`),
			primary key (`id`)
		) $engine";


	$create_statements['user_memberships'] = 
		"CREATE TABLE `user_memberships` (
			`user_id`                    $type_foreign_key_id,
			`group_id`                   $type_foreign_key_id,
			`expiry_time`                $type_unixtime
		) $engine ";
	
	
	$create_statements['user_privilege_info'] =
		"CREATE TABLE `user_privilege_info` (
			`id`                         $type_primary_key_id,
			`description`                $type_nondata_string,
			`type`                       $type_symbolic_const,
			`scope`                      $type_qdata_text,
			primary key (`id`)
		) $engine ";
	

	$create_statements['xml_metadata'] = 
		"CREATE TABLE `xml_metadata` (
			`id`                         $type_primary_key_id,
			`corpus`                     $type_handle_key->corpus,
			`handle`                     $type_handle_key->xml,
			`att_family`                 $type_handle_ref->xml,
			`description`                $type_nondata_text,
			`datatype`                   $type_datatype,
			primary key (`id`),
			unique key (`corpus`, `handle`)
		) $engine ";


	$create_statements['xml_metadata_values'] = 
		"CREATE TABLE `xml_metadata_values` (
			`corpus`                     $type_handle_key->corpus,
			`att_handle`                 $type_handle_key->xml,
			`handle`                     $type_handle_key->category,
			`description`                $type_nondata_string, 
			`category_num_words`         $type_n_cpos_bytes,    ##### see note on text_metadata
			`category_num_segments`      $type_n_objects,
			primary key (`corpus`, `att_handle`, `handle`)
		) $engine ";


	$create_statements['xml_template_info'] = 
		"CREATE TABLE `xml_template_info` (
			`id`                         $type_primary_key_id,
			`description`                $type_nondata_string,
			primary key (`id`)
		) $engine ";


	$create_statements['xml_template_content'] =
		"CREATE TABLE `xml_template_content` (
			`template_id`                $type_foreign_key_id,
			`order_in_template`          $type_sequence_order,
			`handle`                     $type_handle_key->xml,
			`att_family`                 $type_handle_ref->xml,
			`description`                $type_nondata_string,
			`datatype`                   $type_datatype
		) $engine ";


	$create_statements['xml_visualisations'] =
		"CREATE TABLE `xml_visualisations` (
			`id`                        $type_primary_key_id,
			`corpus`                    $type_handle_ref->corpus,
			`element`                   varchar(70) CHARSET ascii COLLATE ascii_bin NOT NULL default '',   # length is maxlength of xml handle + suffix '~start/~end'
			`conditional_on`            $type_qdata_text,
			`in_concordance`            $type_bool_truthy,
			`in_context`                $type_bool_truthy,
			`in_download`               $type_bool_falsy,
			`html`                      $type_qdata_text,
			`is_template`               $type_bool_falsy,
			primary key (`id`),
			key (`corpus`)
		) $engine ";
	
	return $create_statements;
}

function print_sql_create_table($table, $echo_the_result = true)
{
	$t = get_sql_create_tables();
	if (isset($t[$table]))
	{
		if ($echo_the_result)
		{
			echo "\t\t" . $t[$table] . "\n";
			return false;
		}
		else
			return "\t\t" . $t[$table] . "\n";
	}
	else
		return false;
}


/**
 * Returns an array of statements that should be run
 * to put the system into initial state, AFTER creation of the tables.
 */
function get_sql_recreate_extras()
{
	return array(
		'insert into `user_groups` (group_name,description)values("superusers","Users with admin power")',
		'insert into `user_groups` (group_name,description)values("everybody" ,"Group to which all users automatically belong")',

		'insert into `system_info` (setting_name, value)values("db_version","' . CQPWEB_VERSION . '")',
		'insert into `system_info` (setting_name, value)values("install_date", "' . @date(CQPWEB_UI_DATE_FORMAT) . '") ',
		'insert into `system_info` (setting_name, value)values("db_updated", "'   . @date(CQPWEB_UI_DATE_FORMAT) . '") ',
		);
}





