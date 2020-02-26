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
 * This file contains functions used in the installation of CQPweb corpora
 * (not including text metadata installation!)
 * 
 * It should generally not be included into scripts unless the user is a sysadmin.
 */




/**
 * Just a little object to hold info on the install corpus parsed from GET;
 * NOT an independent module in any way, shape or form, just a way to simplify
 * variable parsing.
 * 
 * It is used in only one place - the function install_new_corpus.
 * 
 * @see install_new_corpus.
 */
class CQPwebNewCorpusInfo
{
	private $is_user_corpus = false;

	public $corpus_name;

	public $send_sysadmin_email_when_done;


	public $already_cwb_indexed;

	public $main_script_is_r2l;

	/** ORDERED array of statements to create/adjust the corpus_info entry. */
	public $corpus_info_sql_insert;

	public $css_path;
	public $title;

	/** p-attribute string bits for the cwb-encode command line */
	public $p_attributes = [];
	/** array of statements to create p-attribute entries in the DB */
	public $p_attributes_sql_insert = [];
	/** handle of the primary annotation */
	public $primary_p_attribute;
	/** s-attribute string bits for the cwb-encode command line */
	public $s_attributes = [];
	/** array of statements to create s-attribute entries in the DB */
	public $s_attributes_sql_insert = [];

	/** list of data files for input */
	public $file_list;


	/* constructor is sole public function */
	function __construct()
	{
		global $Config;

		/* an aforethought: communications to sysadmin */
		if (isset($_GET['emailDone']))
			$this->send_sysadmin_email_when_done = (bool)$_GET['emailDone'];
		else
			$this->send_sysadmin_email_when_done = false;


		/* first thing: establish which mode we are dealing with 
		 *              ======================================== */
		$this->already_cwb_indexed = ($_GET['admF'] === 'installCorpusIndexed'); 


		/* get each thing from GET *
		 * ======================= */

		/* the corpus name: first, the handle */
		if (empty($_GET['corpus_name']))
			exiterror("No corpus handle was supplied.");

		$this->corpus_name = strtolower($_GET['corpus_name']);
		
		if (strlen($this->corpus_name) > HANDLE_MAX_CORPUS)
			exiterror("That corpus name is invalid because it is to long (the maximum length is " . HANDLE_MAX_CORPUS . " characters.)");
		if (!cqpweb_handle_check($this->corpus_name))
			exiterror("That corpus name is invalid. You must specify a corpus name using only ASCII letters, numbers and underscore.");
		if (substr($this->corpus_name, -6) == '__freq')
			exiterror("That corpus name is invalid because it ends in __freq (not allowed!)");

		/* check whether corpus already exists */
		if (corpus_exists($this->corpus_name))
			exiterror("Corpus `$this->corpus_name' already exists on the system. Please specify a different name for your new corpus.");

		/* check handle against reserved words */
		if (in_array($this->corpus_name, $Config->cqpweb_reserved_subdirs))
			exiterror("The following corpus names are not allowed: " . implode(' ', $Config->cqpweb_reserved_subdirs));


		/* ***************** */

		if ($this->already_cwb_indexed)
			$this->parse_info_on_indexed_corpus();
		else
		{
			/* the corpus is NOT already indexed, so get the information we need to set it up from GET. */

			/* FIRST, the file list. */
			$array_of_files = list_parameter_values_from_http_query('includeFile', $_SERVER['QUERY_STRING']);

			$this->file_list = convert_uploaded_files_to_realpaths($array_of_files);

			if (empty($this->file_list))
				exiterror("You must specify at least one file to include in the corpus!");

			/* THEN, the attribute setup. */
			$this->load_p_atts_based_on_get();
			$this->load_s_atts_based_on_get();
		}


		/* ******************* */

		/* everything else! */

		$this->load_corpus_info_based_on_get();

		/* note, this has to be the last action in the constructor, so that all the 
		 * statements are appended AFTER creation of the entry in the corpus_info table. */

	} /* end constructor */



	private function parse_info_on_indexed_corpus()
	{
		/* get blocks of information from the registry file & from cwb-describe-corpus, for use below. 
		 * involves checking validity of the registry path & the data directory. */

		$use_normal_regdir = (bool)$_GET['corpus_useDefaultRegistry'];
		$registry_file = standard_corpus_reg_path($this->corpus_name);

		if (!$use_normal_regdir)
		{
			$orig_registry_file = 
				'/' 
				. trim(trim($_GET['corpus_cwb_registry_folder']), '/')
				. '/' 
				. $this->corpus_name
				;
			if (is_file($registry_file))
				exiterror("A corpus by that name already exists in the CQPweb registry!");
			if (!is_file($orig_registry_file))
				exiterror("The specified CWB registry file does not seem to exist in that location.");
			/* the next check is probably a bit paranoid, but just in case ... */
			if (!is_readable($orig_registry_file))
				exiterror("The specified CWB registry file cannot be read (suggestion: check file ownership/permissions).");

			/* we have established that the desired registry file does not exist and the original we are importing from does,
			 * so we can now import the registry file into CQPweb's registry */
			copy($orig_registry_file, $registry_file);
		}
		else
		{
			/* check that the registry file exists */
			if (!is_file($registry_file))
				exiterror("The specified CWB corpus does not seem to exist in CQPweb's registry.");
		}

		/* we are assured of the existence of a suitable reg file within our normal registry. So read it into a var for parsing. */
		$regdata = file_get_contents($registry_file);

		/* since it exists in our normal registry, we can also use cwb-describe-corpus on it there. */
		$descdata = get_cwb_describe_corpus(strtoupper($this->corpus_name));



		/* now we have the data blocks, use them for some additional checks before extracting attributes. */

		/* check that the corpus data directory actually exists. */
		if (1 > preg_match("/\bHOME\s+(\/[^\n\r]+)\s/", $regdata, $m) ) /* TODO note Windows incompatibility here. */
		{
			if (!$use_normal_regdir)
				unlink($registry_file);
			exiterror("A data-directory path could not be found in the registry file for the CWB corpus you specified."
				. "\n\nEither the data-directory is unspecified, or it is specified with a relative path (an absolute path is needed).");
		}
		$test_datadir = $m[1];

		if (!is_dir($test_datadir))
			exiterror("The data directory specified in the registry file [$test_datadir] could not be found.");

		/* check that <text> and <text_id> are s-attributes */
		if (preg_match('/\bSTRUCTURE\s+text\b/', $regdata) < 1  || preg_match('/\bSTRUCTURE\s+text_id\b/', $regdata) < 1)
			exiterror("Pre-indexed corpora require s-attributes text and text_id!!");




		/* ******************* */

		/* p-attributes */

		preg_match_all("/ATTRIBUTE\s+(\w+)\s*[#\n]/", $regdata, $m, PREG_PATTERN_ORDER);
		foreach($m[1] as $p)
		{
			if ($p == 'word')
				continue;
			$this->p_attributes[] = $p;
			$this->p_attributes_sql_insert[] = $this->get_p_att_mysql_insert($p, '', '', '', false);
		}


		/* ******************* */

		/* s-attributes */

		$all_att_handles = array();

		preg_match_all("/s-(?:att|ATT)\s+(\w+)\s+\d+ regions\s*(\(with annotations\))?\s*/", $descdata, $m, PREG_SET_ORDER);

		/* first pass fills the list of handles, so that on the next pass, we can check for family-heads. */
		foreach($m as $structure)
			$all_att_handles[] = $structure[1];

		/* second pass works out the SQL for each s-attribute from the registry file, assuming all are free-text. */
		foreach($m as $structure)
		{
			/* HANDLE */
			$s = $structure[1];

			/* DATATYPE: none for -S, for -V it is freetext (admin user can change later), unless it's text_id, in which case unique id */
			if (empty($structure[2]))
				$dt = METADATA_TYPE_NONE; 
			else
				$dt = ($s == 'text_id' ? METADATA_TYPE_UNIQUE_ID : METADATA_TYPE_FREETEXT);

			/* FAMILY */
			$att_family = $s;
			if (false !== strpos($s, '_'))
			{
				list($poss_fam) = explode('_', $s);
				if (in_array($poss_fam, $all_att_handles))
					$att_family = $poss_fam;
			}

			/* note: we do not actually need the s_attributes array in this case, as it is only used for the cwb-encode command line;
			 * but we DO need to build a list of insert statements for the XML metadata table. */
			$this->s_attributes_sql_insert[] = $this->get_s_att_mysql_insert($s, $att_family, "Structure ``$s''''", $dt);
		}

		/* ******************* */

		/* and round off with corpus_info. */

		/* note that no "primary" annotation is created if we are loading in an existing corpus; 
		 * instead, the primary annotation can be set later.
		 * note also that cwb_external applies EVEN IF the indexed corpus was already in this directory
		 * (its sole use is to prevent deletion of data that CQPweb did not create)
		 */
		$this->corpus_info_sql_insert[] =
			"insert into corpus_info 
				(corpus,                 primary_annotation, cwb_external) 
			values 
				('{$this->corpus_name}', NULL,               1)";


	} /* end method parse_info_on_indexed_corpus */


	private function load_corpus_info_based_on_get()
	{
		/* this method should not run until the initial statement in the array has been created (the "insert" statement) */
		if (1 != count($this->corpus_info_sql_insert))
			exiterror("Critical code error: can't create corpus_info updates as there is no INSERT statement!");

		/* FIRST: prepare remaining corpus-level info from get */

		$this->title               = escape_sql($_GET['corpus_description']);
		$this->main_script_is_r2l  = ( (isset($_GET['corpus_scriptIsR2L'])    && $_GET['corpus_scriptIsR2L']    === '1') ? '1'      : '0');

		$this->encode_charset = ( isset($_GET['corpus_charset']) ? CQP::translate_corpus_charset_to_cwb($_GET['corpus_charset']) : 'utf8' );
		if(is_null($this->encode_charset))
			$this->encode_charset  = 'utf8';
		/* note that the charset is ONLY used for cwb-encode, not for the corpus_info statements;
		 * that means this var is never used when an existing corpus is added to CQPweb, 
		 * so it is safe to let it default to utf-8. */

		/* The CSS entry is a bit more involved.... */
		if ($_GET['cssCustom'] == 1)
		{
			/* escape single quotes in the address because it will be embedded in a single-quoted string */ 
			$this->css_path = addcslashes($_GET['cssCustomUrl'], "'");
			/* only a silly URL would have ' in it anyway, so this is for safety */

			/* TODO: There is a possibility of an XSS vulnerability - as this URL is sent back to the client eventually.
			 * MAYBE: make safe by attempting to retrieve it???????? and check it is really a CSS file???
			 * Or, only allow CSS files actually on the server?
			 */ 
		}
		else
		{
			/* we assume no single quotes in names of builtin CSS files */ 
			$this->css_path = "../css/{$_GET['cssBuiltIn']}";
			if (! is_file($this->css_path))
				$this->css_path = '';
			/* the is_file check means that only files actually on the server can be used, ergo XSS is protected against. */
		}

		/* OK, now we can assemble the SQL update. */
		$this->corpus_info_sql_insert[] 
			= "update corpus_info 
					set
						cqp_name = '" . strtoupper($this->corpus_name) . "',
						main_script_is_r2l = {$this->main_script_is_r2l},
						title = '{$this->title}',
						css_path = '{$this->css_path}'
					where corpus = '{$this->corpus_name}'";

	}	/* end method load_corpus_info_based_on_get() */


	private function load_s_atts_based_on_get()
	{
		if (!isset($_GET['useXmlTemplate']))
			exiterror("Critical error: missing parameter useXmlTemplate");

		if ('~~customSs' == $_GET['useXmlTemplate'])
		{
			/* custom s-attributes */

			/* note this code draws on what is done in template setup, EXCEPT instead of building variables to
			 * create a template in the DB, we build variables for corpus indexing. */

			for ( $i = 1, $a_ix = 0; !empty($_GET["customSHandle$i"]) ; $i++, $a_ix++ )
			{
				$handle = cqpweb_handle_enforce($_GET["customSHandle$i"]);
				$description = $_GET["customSDesc$i"];
				if ($handle == '__HANDLE')
					exiterror("Invalid s-attribute handle: " . $_GET["customSHandle$i"] . " .");
				if (HANDLE_MAX_XML < strlen($handle))
					exiterror("Overlong s-attribute handle ``{$handle}'' (must be 64 characters or less).");
				if (255 < strlen($description))
					exiterror("Overlong s-attribute description ``{$description}'' (must be 255 characters (bytes) or less).");

				$this->s_attributes_sql_insert[] 
					= $this->get_s_att_mysql_insert($handle, $handle, escape_sql($description), METADATA_TYPE_NONE);

				$encode_str = $handle;

				/* attributes of the element */
				for ( $j = 1/*, $family = $handle*/; !empty($_GET["customSHandleAtt{$i}_$j"]) ; $j++)
				{
					/* grab and check handle and desc */
					$att_handle = cqpweb_handle_enforce($_GET["customSHandleAtt{$i}_$j"]);
					$att_desc = $_GET["customSDescAtt{$i}_$j"];
					if ($att_handle == '__HANDLE')
						exiterror("Invalid s-attribute handle: " . $_GET["customSHandleAtt{$i}_$j"] . " .");
					if (64 < strlen($att_handle))
						exiterror("Overlong s-attribute handle ``{$att_handle}'' (must be 64 characters or less).");
					if (255 < strlen($att_desc))
						exiterror("Overlong s-attribute description ``{$att_desc}'' (must be 255 characters (bytes) or less).");

					/* check the datatype: what is allowed here must track what is allowed in an XML template */
					$dt = (int)$_GET["customSTypeAtt{$i}_$j"];
					switch($dt)
					{
					case METADATA_TYPE_CLASSIFICATION:
					case METADATA_TYPE_FREETEXT:
					case METADATA_TYPE_UNIQUE_ID:
					case METADATA_TYPE_IDLINK:
					case METADATA_TYPE_DATE:
						break;
					default:
						exiterror("Invalid attribute datatype supplied  for attribute ``{$att_handle}''!");
					}

					/* ok, we can now add the bits n pieces... */
					$encode_str .= '+' . $att_handle;
					$this->s_attributes_sql_insert[]
						= $this->get_s_att_mysql_insert($handle.'_'.$att_handle, $handle, escape_sql($att_desc), $dt);
				}

				/* we now have the complete string for cwb-encode so add to array.... */
				$this->s_attributes[] = $encode_str;
			}
		}
		else
		{
			/* s-attributes from XML template */

			$template_id = (int)$_GET['useXmlTemplate'];

			if (!set_install_data_from_xml_template($template_id, $this->corpus_name, $this->s_attributes, $this->s_attributes_sql_insert))
				exiterror("Critical error: nonexistent annotation template specified.");
		}

	}	/* end method load_s_atts_based_on_get() */



	private function load_p_atts_based_on_get()
	{
		if (!isset($_GET['useAnnotationTemplate']))
			exiterror("Critical error: missing parameter useAnnotationTemplate");


		if ('~~customPs' == $_GET['useAnnotationTemplate'])
		{
			/* custom p-attributes */

			for ( $q = 1 ; isset($_GET["customPHandle$q"]) ; $q++ )
			{
				$cand = cqpweb_handle_enforce($_GET["customPHandle$q"], HANDLE_MAX_ANNOTATION);
				if ($cand === '__HANDLE')
					continue;

				if (isset($_GET["customPfs$q"] ) && $_GET["customPfs$q"] === '1')
				{
					$cand .= '/';
					$fs = 1;
				}
				else
					$fs = 0;

				$this->p_attributes[] = $cand;

				$cand = str_replace('/', '', $cand);

				$this->p_attributes_sql_insert[] 
					= $this->get_p_att_mysql_insert(
							$cand, 
							escape_sql($_GET["customPDesc$q"]), 
							escape_sql($_GET["customPTagset$q"]), 
							escape_sql($_GET["customPurl$q"]),
							$fs 
						);

				if (isset($_GET['customPPrimary']) && (int)$_GET['customPPrimary'] == $q)
					$this->primary_p_attribute = $cand;
			}
		}
		else
		{
			/* p-attributes from annotation template */

			$template_id = (int)$_GET['useAnnotationTemplate'];

			if (!set_install_data_from_annotation_template($template_id, $this->corpus_name, $this->primary_p_attribute, $this->p_attributes, $this->p_attributes_sql_insert))
				exiterror("Critical error: nonexistent annotation template specified.");
		}

		if (isset ($this->primary_p_attribute))
			$prim_val = " '" . $this->primary_p_attribute ."' ";
		else
			$prim_val = " NULL ";

		$this->corpus_info_sql_insert[] =
			"insert into corpus_info (corpus, primary_annotation) values ('{$this->corpus_name}', $prim_val)";


	} /* end method load_p_atts_based_on_get() */



	private function get_p_att_mysql_insert($tag_handle, $description, $tagset, $url, $feature_set)
	{
		return sql_for_p_att_insert($this->corpus_name, $tag_handle, $description, $tagset, $url, $feature_set);
	}


	private function get_s_att_mysql_insert($handle, $att_family, $description, $datatype)
	{
		return sql_for_s_att_insert($this->corpus_name, $handle, $att_family, $description, $datatype);
	}

} /* end class (CQPwebNewCorpusInfo) */




function install_new_corpus()
{
	global $Config;

	/* note that most of the overall setup time is taken up by other processes (cwb-encode etc.), so this is only rarely needed. */
	php_execute_time_unlimit();

	$info = new CQPwebNewCorpusInfo;
	/* we need both case versions here */
	$corpus = $info->corpus_name;
	$CORPUS = strtoupper($corpus);


	/* =============================================================================== *
	 * create web symlink FIRST, so that if indexing fails, deletion should still work *
	 * =============================================================================== */

	$newdir = '../' . $corpus;

	if (file_exists($newdir))
	{
		if (!is_link($newdir))
			recursive_delete_directory($newdir);
		else
			unlink($newdir);
	}

	if (!symlink("exe", $newdir))
		exiterror("A web-folder for the new corpus could not be created at location '$newdir' . " 
			. "Please make sure your web server has permission to write files to the directory where the CQPweb scripts are installed.");
	chmod($newdir, 0775);


	/* sql table inserts */
	foreach ($info->corpus_info_sql_insert as $s)
		do_sql_query($s);
	foreach ($info->p_attributes_sql_insert as $s)
		do_sql_query($s);
	foreach ($info->s_attributes_sql_insert as $s)
		do_sql_query($s);


	/* ========================================================================== *
	 * CWB setup comes after the SQL ops; if it fails, deletion should still work *
	 * ========================================================================== */

	if ($info->already_cwb_indexed)
		;
	else
	{
		/* cwb-create the file */
		$datadir = standard_corpus_index_path($corpus); // "{$Config->dir->index}/$corpus"

// test code for a bug:
if (realpath($datadir) == realpath($Config->dir->index)) {exit("Critical error in installation: corpus dir not specified");}

		if (is_dir($datadir))
		{
			if (!is_link($datadir))
				recursive_delete_directory($datadir);
			else
				unlink($datadir);
		}
		mkdir($datadir, 0775);

		$regfile = standard_corpus_reg_path($corpus);

		/* run the commands one by one */
		$encode_comm_errs = [];
		$encode_command = cwb_encode_new_corpus_command(
										$corpus, $info->encode_charset, $datadir, 
										$info->file_list, $info->p_attributes, $info->s_attributes, 
										$encode_comm_errs);
		if (false === $encode_command)
			exiterror($encode_comm_errs);

		$exit_status_from_cwb = 0;
		/* NB this array collects both the commands used and the output sent back (via stderr, stdout) */
		$output_lines_from_cwb = array($encode_command);

		exec($encode_command, $output_lines_from_cwb, $exit_status_from_cwb);
		if ($exit_status_from_cwb != 0)
			exiterror(array_merge(array("cwb-encode reported an error! Corpus indexing aborted."), $output_lines_from_cwb)); 

		/* registry will have been created by the previous step */
		chmod($regfile, 0664);

		$output_lines_from_cwb[] = $makeall_command = "{$Config->path_to_cwb}cwb-makeall -r \"{$Config->dir->registry}\" -V $CORPUS 2>&1";

		exec($makeall_command, $output_lines_from_cwb, $exit_status_from_cwb);
		if ($exit_status_from_cwb != 0)
			exiterror(array_merge(array("cwb-makeall reported an error! Corpus indexing aborted."), $output_lines_from_cwb));

		if (! cwb_compress_corpus_index($CORPUS, $output_lines_from_cwb))
			exiterror($output_lines_from_cwb);


		/*
		 * Finally, we save the entire output blob to preserve its contents.
		 * The "finished" screen has a link which slide-downs the content of this field 
		 * to display the output from CWB. This allows you to see, f'rinstance, any dodgy messages 
		 * about XML elements that were droppped or encoded as literals.
		 */
		update_corpus_indexing_notes($corpus, $output_lines_from_cwb);


	} /* end else (from if cwb index already exists) */

	/* now the CWB index has been created, we can log its disk space usage in the database */
	update_corpus_index_size($corpus);



	/* ================================================= *
	 * post-installation datatype checks on S-attributes *
	 * ================================================= */

	/* We cannot check s-attribute validity before the S-attributes actually exist. 
	 * Now, we should check each one - and if validity check fails, switch the datatype 
	 * to the most permissive (i.e. FREETEXT). And we log that in the indexing notes.
	 */
	check_corpus_xml_datatypes($corpus);


	/* send sysadmin notification via email, if we've been asked to do so */
	if ($info->send_sysadmin_email_when_done)
		send_cqpweb_email(
			$Config->server_admin_email_address, 
			"CQPweb says: indexing complete ($corpus)", 
			"\n\nYour CQPweb corpus ($corpus) had completed first-stage installation\nas of " 
				.  @date(CQPWEB_UI_DATE_FORMAT) 
				. " .\n\nbest wishes,\n\nThe CQPweb server.\n\n"
			);

	/* make sure execute.php takes us to a nice results screen */
	$_GET['locationAfter'] = "index.php?ui=installCorpusDone&newlyInstalledCorpus={$info->corpus_name}";

}
/* end of function "install_new_corpus" */
