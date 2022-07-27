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
 * Library of functions for dealing with frequency tables for corpora and
 * subcorpora.
 * 
 * These are stored (largely) in MySQL.
 * 
 * Frequency table naming convention:
 * 
 * for a corpus:	freq_corpus_{$corpus}_{$att}
 * 
 * for a subcorpus:	freq_sc_{$corpus}_{$instance_name}_{$att}
 * 
 */

/*
 * =========================
 * FREQTABLE SETUP FUNCTIONS
 * =========================
 * 
 */

/**
 * Creates MySQL frequency tables for each attribute in a corpus;
 * any pre-existing tables are deleted.
 */
function corpus_make_freqtables($corpus)
{
	global $Config;
	global $User;
	
	
	/* only superusers are allowed to do this! */
	if (! $User->is_admin_or_owner($corpus))
		return;

	$corpus = escape_sql($corpus);
	
	$c_info = get_corpus_info($corpus);

	$corpus_sql_collation = deduce_corpus_sql_collation($c_info);

	$cqp = get_global_cqp();
	
	$cqp->set_corpus($c_info->cqp_name);
	
	/* list of attributes on which to make frequency tables */
	$attribute = array_merge(array('word'), array_keys(list_corpus_annotations($corpus)));
	
	/* create a temporary table */
	$temp_tablename = "__tempfreq_{$corpus}";
	do_sql_query("DROP TABLE if exists $temp_tablename");

	$sql = "CREATE TABLE $temp_tablename (
		freq int(11) unsigned default NULL";
	foreach ($attribute as $att)
		$sql .= ",
			`$att` varchar(255) NOT NULL";
	foreach ($attribute as $att)
		$sql .= ",
			key (`$att`)";
	$sql .= "
		) CHARACTER SET utf8 COLLATE $corpus_sql_collation";

	do_sql_query($sql);
	

	/* for convenience, $filename is absolute */
	$filename = "{$Config->dir->cache}/____$temp_tablename.tbl";

	/* now, use cwb-scan-corpus to prepare the input */
	$cwb_command = "{$Config->path_to_cwb}cwb-scan-corpus -r \"{$Config->dir->registry}\" -o \"$filename\" -q {$c_info->cqp_name}";
	foreach ($attribute as $att)
		$cwb_command .= " $att";
	$status = 0;
	$msg = array();
	exec($cwb_command . ' 2>&1', $msg, $status);
	if ($status != 0)
		exiterror("cwb-scan-corpus error!\n" . implode("\n", $msg));
	
	/* We need to check if the CorpusCharset is other than ASCII/UTF8. 
	 * If it is, we need to call the library function that runs over it with iconv. */
	if (($corpus_charset = $cqp->get_corpus_charset()) != 'utf8')
	{
		$utf8_filename = $filename .'.utf8.tmp';
		
		change_file_encoding($filename, 
		                     $utf8_filename, 
		                     CQP::translate_corpus_charset_to_iconv($corpus_charset), 
		                     CQP::translate_corpus_charset_to_iconv('utf8') . '//TRANSLIT');
		
		unlink($filename);
		rename($utf8_filename, $filename);
		/* so now, either way, we need to work further on $filename. */
	}


	disable_sql_table_keys($temp_tablename);
	do_sql_infile_query($temp_tablename, $filename, true);
	enable_sql_table_keys($temp_tablename);

	unlink($filename);

	/* OK - the temporary, ungrouped frequency table is in memory. 
	 * Each line is a unique binary line across all the attributes.
	 * It needs grouping differently for each attribute. 
	 * (This will also take care of putting 'the', 'The' and 'THE' together,
	 * if the collation does that) */

	foreach ($attribute as $att)
	{
		$sql_tablename = "freq_corpus_{$corpus}_$att";

		do_sql_query("DROP TABLE if exists $sql_tablename");

		$sql = "CREATE TABLE $sql_tablename (
			freq int(11) unsigned default NULL,
			item varchar(255) NOT NULL,
			primary key(item)
			) CHARACTER SET utf8 COLLATE $corpus_sql_collation";
		do_sql_query($sql);
		
		disable_sql_table_keys($sql_tablename);
		$sql = "
			INSERT INTO $sql_tablename 
				select sum(freq) as f, `$att` as item 
					from $temp_tablename
					group by `$att`";

		do_sql_query($sql);
		enable_sql_table_keys($sql_tablename);
	}

	/* delete temporary ungrouped table */
	do_sql_query("DROP TABLE if exists $temp_tablename");
	
	/* log the amount of disk space in use */
	update_corpus_freqtable_size($corpus);
	
	/* log the n of types in corpus_info. */
	update_corpus_n_types($corpus);
	/* the above action does not, strictly, belong here. 
	 * But freq table set up is necessary for it to work.
	 * So, we might as well set the n types immediately. */
}







/**
 * Creates frequency lists for a --subsection only-- of the current corpus, 
 * ie a restriction or subcorpus.
 * 
 * @param QueryScope $qscope  QueryScope object containing the subsection to make freqtables for.
 */
function subsection_make_freqtables($qscope)
{
	global $Config;
	global $Corpus;
	global $User;

	$cqp = get_global_cqp();


	/* list of attributes on which to make frequency tables */
	$attribute = array_merge(array('word'), array_keys(list_corpus_annotations($Corpus->name)));


	/* From the unique instance name, create a freqtable base name */
	$freqtables_base_name = freqtable_name_unique("freq_sc_{$Corpus->name}_{$Config->instance_name}");


	/* register this script as working to create a freqtable, after checking there is room for it */
	if ( check_db_max_processes('freqtable') === false )
		exiterror_toomanydbprocesses('freqtable');
	register_db_process($freqtables_base_name, 'freqtable');


	/* BEFORE WE START: can we use the text-index, or do we need to fall-back to cwb-scan-corpus? */ 

	/* set $use_freq_index to true if the item type is 'text', and get an item list. */
	$item_type = $qscope->get_item_type();
	$item_type = $qscope->get_item_identifier();
	$list = $qscope->get_item_list();
	$use_freq_index = ($item_type == 'text' && $item_identifier == 'id');
	/* if the subsection is a set of complete texts, we can use the more efficient approach via the CWB frequency table
	 * (essentially a cwb-encoded cache of the output from cwb-scan-corpus, grouped text-by-text). */

	/* the temporary table names flag the algorithm version (just in case) */
	$algov = ($use_freq_index ? 'v1' : 'v2');


	/* OK we are READY TO RUMBLE. */

	/* STEP 1: Check cache contents. (We do this before building, in order that we don't overflow the cache
	 * by TOO much in the intermediate step when the new freq table is being built.) */
	delete_freqtable_overflow();


	/* STEP 2: set up the temp vars for the Master Frequency table (will contain ungrouped data!) as a temporary MySQL thingy */
	$master_table = "__freqmake_temptable{$algov}_{$Config->instance_name}";
	do_sql_query("DROP TABLE if exists $master_table");
	$master_table_loadfile = "{$Config->dir->cache}/__infile$algov$master_table";

	/* This is how we CREATE the Master Frequency table for subcorpus frequencies */
	$sql = "CREATE TABLE `$master_table` ( `freq` int(11) unsigned NOT NULL default 0";
	foreach ($attribute as $att)
		$sql .= ", `$att` varchar(255) NOT NULL default ''";
	foreach ($attribute as $att)
		$sql .= ", key(`$att`)";
	$sql .= ") CHARACTER SET utf8 COLLATE {$Corpus->sql_collation}";
//var_dump($Corpus->sql_collation); var_dump($Corpus); exit;
	do_sql_query($sql);


	/* NOW: here we switch between the two possible algorithms */
	if ($use_freq_index)
	{
		/* use the algorithm that was originally used: ie, use the freq text index to get already-grouped frequencies by text_id. */

		/* save regions to be scanned to a temp file */
		$regionfile = new CQPInterchangeFile($Config->dir->cache);
		$region_list_array = get_freq_index_positionlist_for_text_list($list, $qscope->get_corpus());

		foreach ($region_list_array as $reg)
			$regionfile->write("{$reg[0]}\t{$reg[1]}\n");

		$regionfile->finish();

		/* run command to extract the frequency lines for those bits of the corpus */
		$cmd_scancorpus 
			= "{$Config->path_to_cwb}cwb-scan-corpus -r \"{$Config->dir->registry}\" -o \"$master_table_loadfile\" -F __freq "
			. "-R \"" . $regionfile->get_filename()
			. "\" {$Corpus->cqp_name}__FREQ"
			;
		foreach ($attribute as $att)
			$cmd_scancorpus .= " $att+0";
		$cmd_scancorpus .=  ' 2>&1';

		$status = 0;
		$msg = array();
		exec($cmd_scancorpus, $msg, $status);
		if ($status != 0)
			exiterror("cwb-scan-corpus error!\n" . implode("\n", $msg));

		/* close and delete the temp file containing the text regions */
		$regionfile->close(); // nb, if we don't use CQPInterchangeFile, then we can extract out a lot more of the algorithm outside the if-else.

		/* END OF ORIGNAL ALGORITHM for subsections consisting of a full number of texts */
	}
	else
	{
		/* use the algorithm for arbitrary sets of ranges: using a dumpfile plus cwb-scan-corpus, this is more like the
		 * manner in which the whole corpus's frequency list was originally created.    */

		/* the regions of the original corpus are in the scope's dumpfile */
		$remove_file = false;
		$region_path = $qscope->get_dumpfile_path($remove_file);

		/* use cwb-scan-corpus to prepare the input */
		$cmd_scancorpus 
			= "{$Config->path_to_cwb}cwb-scan-corpus -r \"{$Config->dir->registry}\" -o \"$master_table_loadfile\" "
			. "-R \"$region_path\" -q {$Corpus->cqp_name}"
			;
		/* nb the big difference on Algorithm 2 is that we don't have -F option, because we are grouping data 
		 * from the original corpus, not from a CWB-encoded frequency index. */
		foreach ($attribute as $att)
			$cmd_scancorpus .= " $att";
		$cmd_scancorpus .= ' 2>&1';

		$status = 0;
		$msg = array();
		exec($cmd_scancorpus, $msg, $status);
		if ($status != 0)
			exiterror("cwb-scan-corpus error!\n" . implode("\n", $msg));

		/* if necessary, remove the file (if it was a temp one) */
		if ($remove_file)
			unlink($region_path);

		/* END OF REVISED ALGORITHM for subsections not based on full set of text_ids. */ 
	}

	/* 
	 * the following bits are in common to the two algorithms. 
	 */


	/* We need to check if the CorpusCharset is other than ASCII/UTF8. 
	 * If it is, we need to open & cycle iconv on the whole thing.     */
	if ('utf8' !=($corpus_charset = $cqp->get_corpus_charset()))
	{
		$utf8_filename = $master_table_loadfile .'.utf8.tmp';

		change_file_encoding($master_table_loadfile, 
		                     $utf8_filename, 
		                     CQP::translate_corpus_charset_to_iconv($corpus_charset), 
		                     CQP::translate_corpus_charset_to_iconv('utf8') . '//TRANSLIT');

		unlink($master_table_loadfile);
		rename($utf8_filename, $master_table_loadfile);
	}


	/* ok, now we are ready to transfer the base frequency list from the master loadfile into the master table in mysql */

	disable_sql_table_keys($master_table);
	do_sql_infile_query($master_table, $master_table_loadfile, true);
	enable_sql_table_keys($master_table);

	unlink($master_table_loadfile);


	/* we now have the ungrouped frequency table ("master table") in MySQL, all we need to do is group its contents
	 * differently to create a freqlist-table for each attribute from the master table */

	foreach ($attribute as $att)
	{
		$att_sql_name = "{$freqtables_base_name}_{$att}";
		do_sql_query("DROP TABLE if exists `$att_sql_name`");

		/* create the table */
		$sql = "create table `$att_sql_name` (
			freq int(11) unsigned default NULL,
			item varchar(255) NOT NULL,
			primary key(item)
			) CHARACTER SET utf8 COLLATE {$Corpus->sql_collation}";
		do_sql_query($sql);

		/* and fill it */
		disable_sql_table_keys($att_sql_name);
		$sql = "insert into $att_sql_name 
					select sum(freq), `$att` from $master_table
					group by `$att`";
		do_sql_query($sql);
		enable_sql_table_keys($att_sql_name);
	}
	/* end foreach $attribute */

	/* delete the temporary ungrouped "master" table */
	do_sql_query("DROP TABLE if exists `$master_table`");


	/* end of two-algorithm section: all that remains is to create a record for this freqtable. */

	$sql = "insert into saved_freqtables (
			freqtable_name,
			corpus,
			user,
			query_scope
		) values (
			'$freqtables_base_name',
			'$Corpus->name',
			'{$User->username}',
			'" . $qscope->serialise() . "'
		)";
		/* no need to set `public`: it sets itself to 0 by default */
	do_sql_query($sql);

	/* post create additions */
	$thistime = time();
	if (false === ($thissize = calculate_freqtable_size($freqtables_base_name)))
		exiterror("Could not calculate size of frequency table # $freqtables_base_name!");
	do_sql_query("update saved_freqtables set ft_size = $thissize, create_time = $thistime where freqtable_name = '$freqtables_base_name'");


	/* NB: freqtables share the dbs' register/unregister functions, with process_type 'freqtable' */
	unregister_db_process();


	/* Check cache contents AGAIN (in case the newly built frequency table has overflowed the cache limit */
	delete_freqtable_overflow();


	/* return as an assoc array a copy of what has just gone into saved_freqtables */
	return (object) array (
		'freqtable_name' => $freqtables_base_name,
		'corpus' => $Corpus->name,
		'user' => $User->username,
		'query_scope' => $qscope->serialise(),
		'create_time' => $thistime,
		'ft_size' => $thissize,
		'public' => 0
		);
} /* end of function subsection_make_freqtables() */








function make_cwb_freq_index($corpus)
{
	global $Config;
	global $User;
	
	/* only superusers are allowed to do this! */
	if (! $User->is_admin_or_owner($corpus))
		return;
	
	$corpus = escape_sql($corpus);
	
	$c_info = get_corpus_info($corpus);
	
	/* disallow this function for corpora with only one text */
	if (2 > (int)get_sql_value("select count(*) from text_metadata_for_$corpus"))
		exiterror("This corpus only contains one text. Using a CWB text-by-text frequency index is therefore neither necessary nor desirable.");
	/* NB, it would probably be safe to use c_info->size_texts, but let's use direct SQL just as a precaution */
	
	/* this function may take longer than the script time limit */
	php_execute_time_unlimit();
	
	
	/* list of attributes on which to make frequency tables */
// 	$attribute = array('word');    // why is this array not used later? prob was copied from another func.
	$p_att_line = '-P word ';
	$p_att_line_no_word = '';
	foreach (list_corpus_annotations($corpus) as $a => $junk)
	{
		if ($a == '__freq')  /* very unlikely, but... */
			exiterror("you've got a p-att called __freq!! That's very much not allowed.");
// 		$attribute[] = $a;
		$p_att_line .= "-P $a ";
		$p_att_line_no_word .= "-P $a ";
	}

	/* names of the created corpus (lowercase, upppercase) and various paths for commands */
	$freq_corpus_cqp_name_lc = $corpus . '__freq';
	$freq_corpus_cqp_name_uc = strtoupper($freq_corpus_cqp_name_lc);
	
	$datadir = "{$Config->dir->index}/$freq_corpus_cqp_name_lc";
	$regfile = "{$Config->dir->registry}/$freq_corpus_cqp_name_lc";

	
	/* character set to use when encoding the new corpus */
	$cqp = get_global_cqp();
// 	$cqp = new CQP($Config->path_to_cwb, $Config->dir->registry);
	$cqp->set_corpus($c_info->cqp_name);
	$charset = $cqp->get_corpus_charset();


	/* delete any previously existing corpus datadir/regfile of this name, then make the data directory ready */
	if (is_dir($datadir))
		recursive_delete_directory($datadir);
	if (is_file($regfile))
		unlink($regfile);

	if (! mkdir($datadir))
		exiterror("CQPweb could not create a directory for the frequency index. Check filesystem permissions!");
	chmod($datadir, 0777);

	/* open a pipe **from** cwb-decode and another **to** cwb-encode */
	$cmd_decode = "{$Config->path_to_cwb}cwb-decode -r \"{$Config->dir->registry}\" -Cx {$c_info->cqp_name} $p_att_line -S text_id";

	$source = popen($cmd_decode, 'r');
	if (!is_resource($source))
		exiterror('Freq index creation: CWB decode source pipe did not open properly.');
	/* we are using -Cx mode, so we need to skip the first two lines */
	$junk = fgets($source);
	if ( $junk[0] != '<' || $junk[1] != '?' )
		exiterror("Freq index creation: unexpected first XML line from CWB decode process.");
	$junk = fgets($source);
	if (! preg_match('/^<corpus/', $junk))
		exiterror("Freq index creation: unexpected second XML line from CWB decode process.");

	$cmd_encode = "\"{$Config->path_to_cwb}cwb-encode\" -U \"\" -x -d \"$datadir\" -c $charset -R \"$regfile\" $p_att_line_no_word -P __freq -S text:0+id ";

	$encode_pipe = NULL;
	$pipe_creator = array(0 => array("pipe", "r"),1 => array("pipe", "w"),2 => array("pipe", "w"));

	$encode_process = proc_open($cmd_encode, $pipe_creator, $encode_pipe);
	if (! is_resource($encode_process))
		exiterror('Freq index creation: CWB encode process did not open properly.');

	/* so now we can stick the pipe to child STDIN into DEST. */
	$dest = $encode_pipe[0];


	/* Right, we can now filter the flow from decode to encode... */
	squawk("Beginning to filter data from decode to encode to build the frequency-list-by-text CWB database...");

	$F = array();

	/* for each line in the decoded output ... */
	while ( ($line = fgets($source)) !== false)
	{
		/* in case of whitespace... */
		$line = trim($line, "\r\n ");
		/* we do not trim off \t because it might be a column terminator */


		if (preg_match('/^<text_id\s+(\w+)>$/', $line, $m) > 0)
		{
			/* extract the id from the preceding regex using (\w+) */
			$current_id = $m[1];
			$F = array();
		}
		else if ($line == '</text_id>')
		{
			/* do the things to be done at the end of each text */

			if ( ! isset($current_id) )
				exiterror("Unexpected /text_id end-tag while creating corpus $freq_corpus_cqp_name_uc! -- creation aborted");

			if (false === fputs($dest, "<text id=\"$current_id\">\n"))
				exiterror("Freq index creation: Could not write [text] to CWB encode destination pipe");
			arsort($F);

			foreach ($F as $l => &$c)
			{
				if (false === fputs($dest, "$l\t$c\n"))
					exiterror("Freq index creation: Could not write [$l--$c] to CWB encode destination pipe");
				/* after each write, check the encode process for errors; print them if found */
				$w = NULL;
				$e = NULL;
				$encode_output = '';
				foreach(array(1,2) as $x)
				{
					$r=array($encode_pipe[$x]);
					while (0 < stream_select($r, $w, $e, 0))
					{
						if (false !== ($fgets_return = fgets($encode_pipe[$x])))
							$encode_output .= $fgets_return;
						else
							break;
						$r=array($encode_pipe[$x]); /* ready for next loop */
					}
				}
				if (!empty($encode_output) )
					squawk($encode_output);
			}
			if (false === fputs($dest, "</text>\n"))
				exiterror("Freq index creation: Could not write [/text] to CWB encode destination pipe");
			unset($current_id, $F);
		}
		else
		{
			/* if we're at the point of waiting for a text_id, and we got this, then ABORT! */
			if ( ! isset($current_id) )
			{
				/* this is the only thing that will validly occur outside of a <text> */
				if ($line == '</corpus>')
					continue;
				else
					exiterror(["Unexpected line (shown below) outside text_id tags while creating corpus $freq_corpus_cqp_name_uc! -- creation aborted", $line]);
			}
			/* otherwise... */

			/* first, run line through a minimal XML filter to re-escape things (cwb-decode outputs < if it was &lt; in the input) */
			if (isset($F[$line]))
				$F[$line]++;
			else
				$F[$line] = 1;
			/* whew! that's gonna be hell for memory allocation in the bigger texts */
		}
	}	/* end of while */


	squawk("Encoding of the frequency-list-by-text CWB database is now complete.");

	/* close the pipes and the encode process */
	pclose($source);
	fclose($encode_pipe[0]);
	fclose($encode_pipe[1]);
	fclose($encode_pipe[2]);
	proc_close($encode_process);


	/* system commands for everything else that needs to be done to make it a good corpus */

	$mem_flag = '-M ' . $Config->get_cwb_memory_limit();
	$cmd_makeall  = "\"{$Config->path_to_cwb}cwb-makeall\" $mem_flag -r \"{$Config->dir->registry}\" -V $freq_corpus_cqp_name_uc ";
	$cmd_huffcode = "\"{$Config->path_to_cwb}cwb-huffcode\"          -r \"{$Config->dir->registry}\" -A $freq_corpus_cqp_name_uc ";
	$cmd_pressrdx = "\"{$Config->path_to_cwb}cwb-compress-rdx\"      -r \"{$Config->dir->registry}\" -A $freq_corpus_cqp_name_uc ";


	/* make the indexes & compress */
	$output = array();
	
	exec($cmd_makeall,  $output);
	
	exec($cmd_huffcode, $output);
	squawk("Stage 1 (huffman code) compression of the frequency-list-by-text CWB database is now complete...");
	/* delete the intermediate files that we were told we could delete */
	delete_cwb_uncompressed_data($output);


	exec($cmd_pressrdx, $output);
	squawk("Stage 2 (compress-rdx) compression of the frequency-list-by-text CWB database is now complete...");
	/* delete the intermediate files that we were told we could delete */
	delete_cwb_uncompressed_data($output);


	/* now we are done indexing "__freq", update the record of disk space use to include it */
	update_corpus_index_size($corpus);
	
	/* the new CWB frequency-list-by-text "corpus" is now finished! */
	squawk("Done with the frequency-list-by-text CWB database...");


	/*
	 * last thing is to create a file of indexes of the text_ids in this "corpus".
	 * contains 3 whitespace delimited fields: begin_index - end_index - text_id.
	 * 
	 * This then goes into a mysql table which corresponds to the __freq cwb corpus.
	 */
	$index_filename = "{$Config->dir->cache}/{$corpus}_freqdb_index.tbl";
	
	$s_decode_cmd = "{$Config->path_to_cwb}cwb-s-decode -r \"{$Config->dir->registry}\" $freq_corpus_cqp_name_uc -S text_id > \"$index_filename\"";
	exec($s_decode_cmd);

	
	/* make sure the $index_filename is utf8 */
	if ($charset != 'utf8')
	{
		$index_filename_new = $index_filename . '.utf8.tmp';
		
		change_file_encoding($index_filename, 
		                     $index_filename_new,
		                     CQP::translate_corpus_charset_to_iconv($charset), 
		                     CQP::translate_corpus_charset_to_iconv('utf8') . '//TRANSLIT');
		
		unlink($index_filename);
		$index_filename = $index_filename_new;
	}



	
	/* now, create a mysql table with text begin-&-end-point indexes for this cwb-indexed corpus *
	 * (a table which is subsequently used in the process of making the subcorpus freq lists)    */


	$freq_text_index = "freq_text_index_$corpus";
	
	do_sql_query("drop table if exists `$freq_text_index`");

	
	$creation_query = "CREATE TABLE `$freq_text_index` 
		(
			`start` int(11) unsigned NOT NULL,
			`end` int(11) unsigned NOT NULL,
			`text_id` varchar(50) NOT NULL,
			KEY `text_id` (`text_id`)
		) 
		CHARACTER SET utf8 COLLATE utf8_bin";
	do_sql_query($creation_query);

	do_sql_infile_query($freq_text_index, $index_filename);
	/* NB we don't have to worry about the character encoding of the infile as it contains
	 * only integers and ID codes - so, all ASCII. */

	unlink($index_filename);

	/* update the size of the freq tables */
	update_corpus_freqtable_size($corpus);

	/* turn the limit back on */
	php_execute_time_relimit();
}




function list_corpus_freqtable_components($corpus)
{
	$list = [];
	
	foreach(array_merge(array_keys(list_corpus_annotations($corpus)), ['word']) as $t)
		$list[] = 'freq_corpus_' . $corpus . '_' . $t;
	
	return $list;
}




function list_saved_freqtable_components($freqtable_name, $corpus)
{
	$list = [];
	
	foreach(array_merge(array_keys(list_corpus_annotations($corpus)), ['word']) as $t)
		$list[] = $freqtable_name . '_' . $t;
	
	return $list;
}







/**
 * Turns a list of text IDs into a series of
 * corpus positon pairs corresponding to the 
 * CWB frequency index corpus (NOT the corpus
 * itself).
 * 
 * @param array  $text_list  Array of text_id strings.
 * @param string $corpus     The corpus to look in.
 */
function get_freq_index_positionlist_for_text_list($text_list, $corpus)
{
	/* Check whether the specially-indexed cwb per-file freqlist corpus exists */
	if ( ! check_cwb_freq_index($corpus) )
		exiterror("No CWB frequency-by-text index exists for corpus {$corpus}!");
	/* because if it doesn't exist, we can't get the positions from it! */
	
	/* For each text id, we now get the start-and-end positions in the
	 * FREQ TABLE CORPUS (NOT the actual corpus).
	 * 
	 * We can't just do a query for "start,end WHERE text_id is ... or text_id is ..." because
	 * this will overflow the max packet size for a server data transfer if the text list is
	 * long. So, instead, let's do it a blob at a time.
	 */

	$position_list = array();

	foreach(array_chunk($text_list, 20) as $chunk_of_texts)
	{
		/* first step: convert list of texts to an sql where clause */
		$textid_whereclause = translate_itemlist_to_where($chunk_of_texts);
		
		/* second step: get that chunk's begin-and-end points in the specially-indexed cwb per-file freqlist corpus */
		$result = do_sql_query("select start, end from freq_text_index_{$corpus} where $textid_whereclause");
		
		/* third step: store regions to be scanned in output array */
		while ($r = mysqli_fetch_row($result)) 
			$position_list[] = $r;
	}

	/* All position lists must be ASC sorted for CWB to make sense of them. The list we have built from
	 * MySQL may or may not be pre-sorted depending on the original history of the text-list... */
	$position_list = sort_positionlist($position_list);
	
	return $position_list;
}





/**
 * Check if a cwb-frequency-"corpus" exists for the specified lowercase corpus name.
 * 
 * For true to be returned, BOTH the cwb "__freq" corpus AND the corresponding text
 * index must exist. Both the registry and the index directory are checked 
 * (but not the *contents* of the index directory.)
 * 
 * Note: does not work for subcorpora, because they have neither a CWB "__freq" table,
 * nor a freq text index!
 */
function check_cwb_freq_index($corpus_name)
{
	global $Config;
	
	$freq_corpus = $corpus_name . '__freq';
		
	/* first, the CWB checks. */
		
	/* if the registry file does not exist, the corpus definitely doesn't */
	if (! is_file("{$Config->dir->registry}/$freq_corpus"))
		return false;

	/* now check for the existence of the data directory */
	if (! is_dir("{$Config->dir->index}/$freq_corpus"))
		return false;
	/* the __freq corpus is ALWAYS inside the CQPweb datadir, 
	 * so we do not need to worry about checking for "cwb_external" */

	/* now, the mysql check */
	 
	if ( 1 >  mysqli_num_rows(do_sql_query("show tables like 'freq_text_index_$corpus_name'")) )
		return false;

	/* neither the cwb nor the mysql check returned false, so we can return true. */
	return true;
}





/*
 * ========================
 * SQL Freqtable Management 
 * ========================
 */




/**
 * this class represents a single frequency table; mostly just a DB record, 
 * but with other info loaded in....
> describe saved_freqtables;
+----------------+---------------------+------+-----+---------+-------+
| Field          | Type                | Null | Key | Default | Extra |
+----------------+---------------------+------+-----+---------+-------+
| freqtable_name | varchar(150)        | NO   | PRI | NULL    |       |
| corpus         | varchar(20)         | NO   |     |         |       |
| user           | varchar(64)         | YES  |     | NULL    |       |
| query_scope    | text                | YES  | MUL | NULL    |       |
| create_time    | int(11)             | YES  |     | NULL    |       |
| ft_size        | bigint(20) unsigned | YES  |     | NULL    |       |
| public         | tinyint(1)          | YES  |     | 0       |       |
+----------------+---------------------+------+-----+---------+-------+

 */
class FreqtableRecord
{
	public $freqtable_name;
	public $corpus;
	public $user;
	public $query_scope;
	public $create_time;
	public $ft_size;
	public $public;
	
	/** Lists the SQL tables making up this freqtable. Flat unordered array. */
	public $component_tables = [];
	
	public function __construct() {}

	/**
	 * Create a new FreqtableRecord.
	 * 
	 * @param  string $name      The freqtable_name identifier for the saved_freqtables table.
	 * @return FreqtableRecord   Newly created FreqtableRecord.
	 */
	public static function new_from_freqtable_name($name)
	{
		$name = escape_sql($name);
		$result = do_sql_query("select * from `saved_freqtables` where freqtable_name = '$name'");
		if (!$result || 1 > mysqli_num_rows($result))
			return false;
		$ft = self::new_from_db_result($result);
		$result->free();
		return $ft;
	}
	
	/**
	 * Create a new FreqtableRecord.
	 * 
	 * @param  mysqli_result   $result  A mysqli result from saved_freqtables with at least one result remaining.
	 * @return FreqtableRecord          Newly created FreqtableRecord.
	 */
	public static function new_from_db_result(mysqli_result $result)
	{
		if (!($o = $result->fetch_object()))
			return false;
		return self::new_from_db_object($o);
	}
	
	/**
	 * Create a new FreqtableRecord.
	 * 
	 * @param  object $o         Database object from saved_freqtables
	 * @return FreqtableRecord   Newly crearted FreqtableRecord.
	 */
//	public static function new_from_db_object(object $o) // won't work till v7.2
	public static function new_from_db_object($o)
	{
		$ft = new self;
		
		$ft->freqtable_name = $o->freqtable_name;
		$ft->corpus         = $o->corpus;
		$ft->user           = $o->user;
		$ft->query_scope    = $o->query_scope;
		$ft->create_time    = $o->create_time;
		$ft->ft_size        = $o->ft_size;
		$ft->public         = (bool)$o->public;
		
		$ft->component_tables = list_saved_freqtable_components($ft->freqtable_name, $ft->corpus);
		return $ft;
	}
}


/** 
 * Makes sure that the name you are about to give to a freqtable is unique. 
 * 
 * Keeps adding random letters to the end of it if it is not. The name returned
 * is therefore definitely always unique across all corpora.
 */
function freqtable_name_unique($name)
{
	while (true)
	{
		$sql = 'select freqtable_name from saved_freqtables where freqtable_name = \'' . escape_sql($name) . '\' limit 1';

		if (0 == mysqli_num_rows(do_sql_query($sql)))
			break;
		else
			$name .= chr(random_int(0x41,0x5a));
	}
	return $name;
}






/** Works out the combined size in bytes of all SQL DBs relating to a specific cached freqtable. */
function calculate_freqtable_size($freqtable_name)
{
	$size = 0;
	
	if (false === ($ft = FreqtableRecord::new_from_freqtable_name($freqtable_name)))
		return false;
	
	foreach($ft->component_tables as $t)
		$size += get_sql_table_size($t);

// 	$result = do_sql_query("SHOW TABLE STATUS LIKE '$freqtable_name%'");
	/* note the " % " */

// 	while ( $info = mysqli_fetch_assoc($result) ) 
// 		$size += ((int) $info['Data_length'] + $info['Index_length']);

	return $size;
}


function repair_all_freqtable_sizes()
{
	global $User;

	if (!$User->is_admin())
		return;

	$result = do_sql_query("select * from saved_freqtables");

	$n_changes = 0;
	$unchanged = 0;

	while ($o = mysqli_fetch_object($result))
	{
		if (($checksize = calculate_freqtable_size($o->freqtable_name)) != $o->ft_size)
		{
			++$n_changes;
			squawk("Frequency table {$o->freqtable_name}: updating from {$o->ft_size} to $checksize...");
			do_sql_query("update saved_freqtables set ft_size=$checksize where freqtable_name='{$o->freqtable_name}'");
		}
		else
			++$unchanged;
	}
	squawk("Total of $n_changes freqtable sizes repaired. $unchanged were fine and have been left as-is.");
}




/** Updates the timestamp (which is an int, note, not the SQL TIMESTAMP column type). */
function touch_freqtable($freqtable_name)
{
	$freqtable_name = escape_sql($freqtable_name);

	$time_now = time();

	do_sql_query("update saved_freqtables set create_time = $time_now where freqtable_name = '$freqtable_name'");
}







/**
 * Deletes a "cluster" of SQL tables relating to a particular saved freqtable, + their entry
 * in the saved_freqtables list.
 */
function delete_freqtable($freqtable_name)
{
	$freqtable_name = escape_sql($freqtable_name);
	
	/* delete no tables if this is not a real entry in the freqtable db
	 * (this check should in theory not be needed, but let's make sure) */
	$result = do_sql_query("select freqtable_name from saved_freqtables where freqtable_name = '$freqtable_name'");
	if (1 > mysqli_num_rows($result))
		return false;
	
	if (false !== ($ft = FreqtableRecord::new_from_freqtable_name($freqtable_name)))
	{
		foreach ($ft->component_tables as $t)
			do_sql_query("drop table if exists `$t`");
		do_sql_query("delete from saved_freqtables where freqtable_name = '$freqtable_name'");
	}
	
	return true;
}


function delete_corpus_freqtable($corpus)
{
	foreach(list_corpus_freqtable_components($corpus) as $t)
		do_sql_query("drop table if exists `$t`");
}


/**
 * Deletes a specified frequency-table component from MySQL, unconditionally.
 * 
 * Note it only works on frequency table components!
 * 
 * If passed an array rather than a single table name, it will iterate across 
 * the array, deleting each specified table.
 * 
 * Designed for deleting bits of frequency tables that have become unmoored from
 * the record in saved_freqtables that would normally enable their deletion.
 */
function delete_stray_freqtable_part($table)
{
	if (! is_array($table))
		$table = array($table);
	
	foreach ($table as $t)
		if (preg_match('/^freq_sc_/', $t))
			do_sql_query("drop table if exists `" . escape_sql($t) . "`");
}




/** 
 * Checks the size of the cache of saved frequency tables, and if it is higher
 * than the size limit (from config variable $freqtable_cache_size_limit),
 * then old frequency tables are deleted until the size falls below the said limit.
 * 
 * Public frequency tables will not be deleted from the cache. If you want public
 * frequency tables to be equally "vulnerable", pass in false as the argument.
 * 
 * Note: this function works ACROSS CORPORA.
 */
function delete_freqtable_overflow($protect_public_freqtables = true)
{
	global $User;
	global $Config;

	$attempts = 0;
	$max_attempts_by_non_admin = 5; // TODO should this be a config thing?

	while(true) 
	{
		/* step one: how many bytes in size is the freqtable cache RIGHT NOW? */
		$current_size = get_sql_value("select sum(ft_size) from saved_freqtables");
	
		/* if we're below the limit - either as a result of the steps below, or just 
		 * when we start - we can break the loop. */
		if ($current_size <= $Config->freqtable_cache_size_limit)
			break;
		
		/* step two: get a list of deletable freq tables */
		$sql = "select freqtable_name, ft_size from saved_freqtables" 
					. ( $protect_public_freqtables ? " where public = 0" : "" ) 
					. " order by create_time asc LIMIT 10"
					;
		
		$result = do_sql_query($sql);
		
		if ($result->num_rows < 1)
			exiterror(array(
					"CRITICAL ERROR - FREQUENCY TABLE OVERLOAD!\n",
					"CQPweb tried to clear cache space but failed!\n",
					"Please report this error to the system administrator."
				));
// TODO does this func run disconnected? cf. delete_cache_overflow()
	
		/* step three: delete FTs from the list until we've deleted enough */
		while ($current_size > $Config->freqtable_cache_size_limit)
			if (!($current_ft_to_delete = mysqli_fetch_object($result)))
				break;
			else
				if (delete_freqtable($current_ft_to_delete->freqtable_name))
					$current_size -= $current_ft_to_delete->ft_size;

		/* have the above deletions done the trick?
		 * If they have, the next loop will make that apparent
		 * But we need to make sure that the loop is not eternal. */
		if (!$User->is_admin())
		{
			$attempts++;
			if ($attempts > $max_attempts_by_non_admin)
				break;
		}
		/* we trust admin users. */
	}
}






/** Dumps all cached freq tables from the database (unconditional cache clear). */
function clear_freqtables()
{
	$del_result = do_sql_query("select freqtable_name from saved_freqtables");

	while ($current_ft_to_delete = mysqli_fetch_assoc($del_result))
		delete_freqtable($current_ft_to_delete['freqtable_name']);
}



/*
 * ==========================
 * MANAGING PUBLIC FREQTABLES 
 * ==========================
 */




/**
 * Makes the frequency table of a subcorpus public. 
 * 
 * @param string $name               Name of frequency table.
 * @param bool   $switch_public_on   Defaults true (if false, makes the FT un-public).
 */
function publicise_freqtable($name, $switch_public_on = true)
{
	global $User;

	/* only superusers are allowed to do this! */
	if (!$User->is_admin())
		return;

	$name = escape_sql($name);
	
	$sql = "update saved_freqtables set public = " . ($switch_public_on ? 1 : 0) . " where freqtable_name = '$name'";
	
	do_sql_query($sql);

}


/** 
 * Make an SC frequency table nonpublic again; this is just for convenience .
 */
function unpublicise_freqtable($name)
{
	publicise_freqtable($name, false);
}






/**
 * Returns an array of subcorpus IDs. 
 * 
 * Looks at the present corpus (true, false); every other corpora (false, true), or both (true, true).
 * 
 * (false, false) would not make sense. You'll just get an empty array.
 */
function list_public_freqtabled_subcorpora($include_local = false, $include_foreign = true)
{
	global $Corpus;

	if ($include_local)
		$cond = $include_foreign ? '' : "and saved_freqtables.`corpus` = '$Corpus'";
	else
		$cond = $include_foreign ? "and saved_freqtables.`corpus` != '$Corpus'" : "and (1=0)";

	$sql = "select saved_freqtables.query_scope as id
				from saved_freqtables inner join saved_subcorpora on saved_freqtables.query_scope = saved_subcorpora.id
				where  (saved_freqtables.public)  
				$cond
				order by saved_subcorpora.name asc";

	return list_sql_values($sql);
}




/**
 * Returns a list of IDs of non-public subcorpora  belonging to the given corpus and user.
 * (sorted by the name of the subcorpus!)
 *
 * If seek owned is false, the list that comes back is limited by the grants (to implement).
 * 
 * By default, publics are excluded. Allowing publics to be included only takes effect when
 * seeking owned. 
 */
function list_freqtabled_subcorpora($username, $seek_local = true, $seek_owned = true, $include_public = false)
{
	global $Corpus;
	
	$username = escape_sql($username);
	
	$user_op     = $seek_owned     ? '=' : '!=';
	$corpus_op   = $seek_local     ? '=' : '!=';
	
	$public_cond = $include_public ? ''  : 'and (NOT saved_freqtables.public)';
	
	$sql = "select saved_freqtables.query_scope as id
				from saved_freqtables inner join saved_subcorpora on saved_freqtables.query_scope = saved_subcorpora.id
				where  saved_freqtables.corpus  $corpus_op  '$Corpus' 
				and    saved_subcorpora.user    $user_op    '$username'
				$public_cond
				order by saved_subcorpora.name asc";
	/* the inner join gets us the order, but also limits things to SCs rather than Restrictions. */
	
	$collection = list_sql_values($sql);
	
	if (!$seek_owned)
	{
		for ($i = 0 , $n = count($collection) ; $i < $n ; $i++)
		{
			if (true) // TODO this is where we'll check if the SC is granted.
// 			if (!subcorpus_is_granted_to_user(PARAM, PARAM))
				unset($collection[$i]);
		}
	}
	
	return $collection;
}


/**
 * Returns a list of IDs of subcorpora that have freqlists in cache. 
 * (sorted by the name of the subcorpus!)
 *
 * If seek owned is false, the list that comes back is limited by the grants (to implement).
 *
 * By default, publics are excluded. Allowing publics to be included only takes effect when
 * seeking owned.
 */
function list_freqtabled_subcorpora_for_admin($username, $seek_local = true, $seek_owned = true, $include_public = false)
{
//TODO do anything with the argum,ents? Currently, we respect only "Seek local./ 
//both thi9s and the previous function need seriosyu sorting out! 
        global $Corpus;

	$local_cond = $seek_local ? "where saved_freqtables.`corpus` = '$Corpus'" : "";

	$sql = "select saved_freqtables.query_scope as id
				from  saved_freqtables inner join saved_subcorpora on saved_freqtables.query_scope = saved_subcorpora.id
				$local_cond
				order by saved_subcorpora.name asc";

	$collection = list_sql_values($sql);
	return $collection;
}

