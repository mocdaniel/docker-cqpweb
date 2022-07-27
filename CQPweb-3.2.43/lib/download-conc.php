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


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');

/* include function library files */
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/html-lib.php');
require('../lib/concordance-lib.php');
require('../lib/postprocess-lib.php');
require('../lib/cache-lib.php');
require('../lib/scope-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');
require('../lib/xml-lib.php');
require('../lib/useracct-lib.php');
require('../lib/cqp.inc.php');


/* declare global variables */
$Corpus = $User = $Config = NULL;

cqpweb_startup_environment();





/* variables from GET needed by both versions of this script */

$qname = safe_qname_from_get();


/* similarly, we'll always need this.... */

$align_info = check_alignment_permissions(list_corpus_alignments($Corpus->name));




if ( isset($_GET['downloadGo']) && $_GET['downloadGo'] === 'yes')
{
	$qrecord = QueryRecord::new_from_qname($qname);

	/* ----------------------------- *
	 * create and send the text file *
	 * ----------------------------- */
	
	/* gather format settings from $_GET */
	
	/* first an EOL check */
	if (isset($_GET['downloadLinebreak']))
	{
		$eol = preg_replace('/[^da]/', '', $_GET['downloadLinebreak']);
		$eol = strtr($eol, "da", "\r\n");
	}
	else
		$eol = $User->eol();
	

	/* the following switch deals wth the ones that have "typical settings" */
	switch ($_GET['downloadTypical'])
	{
	case 'threeline':
		/* The threeline format falls through to copypaste. 
		 * A correction function is applied to output lines. */

	case 'copypaste':
		
		/* handles or values? */
		$category_handles_only = true;
		
		/* use <<<>>>? -- NO */
		$hit_delimiter_before = '';
		$hit_delimiter_after  = '';
		
		/* context size */
		$words_in_context = $Config->default_words_in_download_context;
		
		/* tagged and untagged? */
		$tagged_as_well = false;
		
		/* file-start info format */
		$header_format = 'tabs';
		
		/* kwic or line? */
		$download_view_mode = 'kwic';
		
		
		/* visualisable XML */
		$include_visualisable_xml = true;

		/* include corpus positions? */
		$include_positions = false;
		
		/* include url as column? */
		$context_url = false;
		
		/* the filename for the output */
		$filename = 'concordance-download.txt';
		
		/* NO metadata */
		$fields_to_include = array();
		
		/* NO parallel data */
		$alx_to_include = array();
		
		break;
		
		
	case 'filemaker':
		
		/* handles or values? */
		$category_handles_only = true;
		
		/* use <<<>>>? -- YES */
		$hit_delimiter_before = '<<< ';
		$hit_delimiter_after  = ' >>>';
		
		/* context size */
		$words_in_context = $Config->default_words_in_download_context;
		
		/* tagged and untagged? */
		$tagged_as_well = !empty($Corpus->primary_annotation);
		
		/* file-start info format */
		$header_format = NULL;
		
		/* kwic or line? */
		$download_view_mode = 'line';
		
		/* NO visualisable XML */
		$include_visualisable_xml = false;

		/* include corpus positions? */
		$include_positions = true;
		
		/* include url as column? */
		$context_url = true;
		
		/* the filename for the output */
		$filename = "concordance_filemaker_import.txt";
		
		/* in this case, ALL categories are downloaded */
		$fields_to_include = list_text_metadata_fields($Corpus->name);
		
		/* NO parallel data */
		$alx_to_include = array();
		
		break;
		
	default:
		/* IE, no special set of pre-sets given */

		/* handles or values? */
		
		if (isset($_GET['downloadFullMeta']) && $_GET['downloadFullMeta'] == 'handles')
			$category_handles_only = true;
		else
			$category_handles_only = false;
		
		/* use <<<>>>? */
		
		$hit_delimiter_before = '';
		$hit_delimiter_after  = '';
		if (isset($_GET['downloadResultAnglebrackets']) && $_GET['downloadResultAnglebrackets'])
		{
			$hit_delimiter_before = '<<< ';
			$hit_delimiter_after  = ' >>>';
		}
		
		/* context size */
		
		if (isset($_GET['downloadContext']))
			$words_in_context = (int) $_GET['downloadContext'];
		else
			$words_in_context = $Config->default_words_in_download_context;
		if ($words_in_context > $Corpus->max_extended_context)
			$words_in_context = $Corpus->max_extended_context;
		if (PRIVILEGE_TYPE_CORPUS_RESTRICTED == $Corpus->access_level)
			if ( ($Corpus->conc_scope_is_based_on_s && $words_in_context > 10) || (!$Corpus->conc_scope_is_based_on_s && $words_in_context > $Corpus->conc_scope))
				$words_in_context = $Corpus->conc_scope;
		
		/* tagged and untagged? */
		
		if (isset($_GET['downloadTaggedAndUntagged']) && $_GET['downloadTaggedAndUntagged'] == 1)
			$tagged_as_well = !empty($Corpus->primary_annotation);
		else
			$tagged_as_well = false;
		
		/* file-start info format */
		
		$header_format = NULL;
		if (isset($_GET['downloadHeadType']))
		{
			switch ($_GET['downloadHeadType'])
			{
			case 'list':
			case 'tabs':
				$header_format = $_GET['downloadHeadType'];
				break;
			default:
				/* leave as NULL */
				break;
			}
		}
		
		/* kwic or line? */
		
		if (isset($_GET['downloadViewMode']) && $_GET['downloadViewMode'] == 'line')
			$download_view_mode = 'line';
		else
			$download_view_mode = 'kwic';
		
		/* Include visualisable XML? If false, XML viz will simply be excluded */
		
		$include_visualisable_xml = (isset($_GET['downloadVizXml']) ? (bool) $_GET['downloadVizXml'] : false );
		
		/* include corpus positions? */
		
		if (isset($_GET['downloadPositions']) && $_GET['downloadPositions'] == 1)
			$include_positions = true;
		else
			$include_positions = false;
		
		/* include url as column? */
		
		if (isset($_GET['downloadURL']) && $_GET['downloadURL'] == 1)
			$context_url = true;
		else
			$context_url = false;
		
		/* the filename for the output */

		$filename = (isset($_GET['downloadFilename']) ? preg_replace('/\W/', '', $_GET['downloadFilename']) : '' );
		if (empty($filename))
			$filename = 'concordance-download';
		$filename .= '.txt';


		/* the categories to include */
		
		$field_full_list = list_text_metadata_fields($Corpus->name);
		$fields_to_include = array();
		
		switch ($_GET['downloadMetaMethod'])
		{
		case 'all':
			$fields_to_include = $field_full_list;
			break;
		
		case 'allclass':
			foreach ($field_full_list as $f)
				if (metadata_field_is_classification($Corpus->name, $f))
					$fields_to_include[] = $f;
			break;
		
		case 'ticked':
			foreach($_GET as $key => &$val)
			{
				if (substr($key, 0, 13) != 'downloadMeta_')
					continue;
				$c = substr($key, 13);
				if ($val && in_array($c, $field_full_list))
					$fields_to_include[] = $c;
			}
			break;
		
		default:
			/* shouldn't ever get here */
			/* add no metadata fields to the array to include */
			break;
		}
		
		/* parallel regions to include */
		$alx_to_include = array();
		foreach($_GET as $key => $val)
		{
			if (substr($key, 0, 14) != 'downloadAlign_')
				continue;
			$c = substr($key, 14);
			if ($val && isset($align_info[$c]))
				$alx_to_include[] = $c;
		}
		
		/* and that is the end of setup when using non-preset download settings. */ 
		break;
		
	} /* end of switch */
	
	/* end of variable setup */

	
	/* send the HTTP header */
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=$filename");

	/* write the file header if specified */
	
	if ($header_format == 'list')
	{
		/* print the header line from the query */
		
		echo str_replace('&rdquo;', '"', 
				str_replace('&ldquo;', '"', 
					preg_replace('/<[^>]+>/', '', 
						$qrecord->print_solution_heading(NULL, true, false) /* non-html heading */
							))),
			$eol, $eol
			;
		
		/* print the rest of the header */
		
		echo "Processed for <{$User->username}> at <", url_absolutify(''), '>', $eol, $eol
			, "Order of tab-delimited text:", $eol
			, "1. Number of hit", $eol
			, "2. Text ID$eol"
			;
		if ($download_view_mode == 'kwic')
		{
			echo "3. Context before", $eol, "4. Query item", $eol, "5. Context after", $eol;
			$j = 6;
			if ($tagged_as_well)
			{
				echo "6. Tagged context before", $eol, "7. Tagged query item", $eol, "8. Tagged context after", $eol;
				$j = 9;
			}
		}
		else
		{
			echo "3. Concordance line", $eol;
			$j = 4;
			if ($tagged_as_well)
			{
				echo "4. Tagged concordance line", $eol;
				$j = 5;
			}
		}
		foreach($fields_to_include as $c)
			echo $j++ , '. ' , expand_text_metadata_field($Corpus->name, $c) , $eol;
		
		foreach($alx_to_include as $a)
			echo $j++ , '. Parallel region from "', $align_info[$a], '"', $eol;
		
		if ($context_url)
			echo $j++, ". URL", $eol;

		if ($include_positions)
		{
			echo $j++, ". Matchbegin corpus position", $eol;
			echo $j++, ". Matchend corpus position",   $eol;
		}
		
		echo $eol;
	}
	else if ($header_format == 'tabs')
	{
		echo "Number of hit\tText ID";
		if ($download_view_mode == 'kwic')
		{
			echo "\tContext before\tQuery item\tContext after";
			if ($tagged_as_well)
				echo "\tTagged context before\tTagged query item\tTagged context after";
		}
		else
		{
			echo "\tConcordance line";
			if ($tagged_as_well)
				echo "\tTagged concordance line";
		}
		foreach($fields_to_include as $f)
			echo "\t" , expand_text_metadata_field($Corpus->name, $f);
		foreach($alx_to_include as $a)
			echo "\t" , 'Parallel: ', $align_info[$a];
		if ($context_url)
			echo "\tURL";
		if ($include_positions)
		{
			echo "\tMatchbegin corpus position";
			echo "\tMatchend corpus position";
		}
		echo $eol;
	}
	
	/* end of file heading */


	/* CQP commands to make ready for concordance line download */

	$cqp = get_global_cqp();
	
	$cqp->execute("set LD '--<<>>--'");
	$cqp->execute("set RD '--<<>>--'");
	$cqp->execute("set Context $words_in_context words");
	$cqp->execute('show +word ' . ( (!$tagged_as_well) ? '' : "+{$Corpus->primary_annotation} "));
	/* NB: $tagged_as well is only ever true if a primary annotation exists: see places at top of file where
	 * it is initialised.... it can be set to false, but true always depends on "!empty(primary_annotation)" */
	if ($include_visualisable_xml)
	{
		$xml_tags_to_show = xml_visualisation_s_atts_to_show('download');
		if ( ! empty($xml_tags_to_show) )
			$cqp->execute('show +' . implode(' +', $xml_tags_to_show));
	}
	if (!empty($alx_to_include))
		$cqp->execute("show +" . implode(' +', $alx_to_include));
	$cqp->execute("set PrintStructures \"text_id\""); 
	
// 	list($n_of_solutions) = $cqp->execute("size $qname");
	$n_of_solutions = $cqp->querysize($qname);
	
	
	/* get category descriptions for each field that is a classification (iff they need expanding) */
	$category_descriptions = array();
	foreach ($fields_to_include as $f)
	{
		if (metadata_field_is_classification($Corpus->name, $f))
		{
			$category_descriptions[$f] = list_text_metadata_category_descriptions($Corpus->name, $f);
			if ($category_handles_only)
				$category_descriptions[$f] = array_combine(array_keys($category_descriptions[$f]), array_keys($category_descriptions[$f]));
		}
	}
	
	
	/* Get the index of visualisations. */
	$xml_viz_index = index_xml_visualisation_list(get_all_xml_visualisations($Corpus->name, false, false, true));
	
	/* Get a table of which aligned corpora have our corpus's primary annotation */
	$alx_has_annotation = array();
	foreach ($align_info as $a=>$desc)
		$alx_has_annotation[$a] = array_key_exists($Corpus->primary_annotation, list_corpus_annotations($a));
	
	
	
	/* loop for concordance line download, 100 lines at a time */
	
	/* before running the loop, unlimit in case of big query */
	if ($n_of_solutions > 100)
		php_execute_time_unlimit();
	
	for ($batch_start = 0; $batch_start < $n_of_solutions; $batch_start += 100) 
	{
		$batch_end = $batch_start + 99;
		if ($batch_end >= $n_of_solutions)
			$batch_end = $n_of_solutions - 1; 
		
		$kwic = $cqp->execute("cat $qname $batch_start $batch_end");
		$table = $cqp->dump($qname, $batch_start, $batch_end); 
		$n_key_items = count($kwic);
		
		/* loop for each line. $i = index into $kwic (may include parallel lines)
		 * $t_i = index into table (no extras for parallel lines, so equal to the line number */
		for ($i = 0, $t_i = 0 ; $i < $n_key_items ; $i++, $t_i++)
		{
			$line_indicator = $batch_start + $t_i + 1;
			preg_match("/\A\s*\d+: <text_id (\w+)>:/", $kwic[$i], $m);
			$text_id = $m[1];
			$kwic[$i] = preg_replace("/\A\s*\d+: <text_id \w+>:\s+/", '', $kwic[$i]);
			
			$kwic_chunks = explode('--<<>>--', $kwic[$i]);
			list($match, $matchend /*, $target, $keyword */) = $table[$t_i];
			/* NB: we don't actually use target & keyword. */

			/* get tagged and untagged lines for print */
			
// 			$untagged = $kwic_lc . ' ~~~***###' 
// 				. $hit_delimiter_before . $kwic_match . $hit_delimiter_after 
// 				. ' ~~~***###' . $kwic_rc;
// 			if ($tagged_as_well) 
// 				$tagged = "\t" . preg_replace('/(\S+)\/([^\s\/]+)/', '$1_$2', $untagged);
// 			else
// 				$tagged = '';
// 			/* now, we can erase the tags from the "untagged" line. */
// 			$untagged = preg_replace('/(\S+)\/([^\s\/]+)/', '$1', $untagged);
			
// 			$kwiclimiter = ($download_view_mode == 'kwic' ? "\t" : ' ');
// 			$tagged   = preg_replace('/\s*~~~\*\*\*###\s*/', $kwiclimiter, $tagged);
// 			$untagged = preg_replace('/\s*~~~\*\*\*###\s*/', $kwiclimiter, $untagged);

			$untagged_bits = array();
			$tagged_bits   = array();
			$xml_before_string = $xml_after_string = '';
			
			foreach ($kwic_chunks as $ix=>$chunk)
			{
				/* process the chunk word by word, including XML viz if necessary */
				preg_match_all(CQP_INTERFACE_WORD_REGEX, trim($chunk), $m, PREG_PATTERN_ORDER);
				$words = $m[4];
				$xml_before_array = $m[1];
				$xml_after_array  = $m[5];
				$ntok = count($words);
				$untagged_bits[$ix] = $tagged_bits[$ix] = array();
				
				for ($j = 0; $j < $ntok; $j++)
				{
					/* apply XML visualisations */
					if ($include_visualisable_xml)
					{
						$xml_before_string = apply_xml_visualisations($xml_before_array[$j], $xml_viz_index);
						if (!empty($xml_before_string))
							$xml_before_string .= ' ';
						$xml_after_string  = apply_xml_visualisations($xml_after_array[$j], $xml_viz_index);
						if (!empty($xml_after_string))
							$xml_after_string = ' ' . $xml_after_string;
					}

					if ($tagged_as_well)
					{
						if (preg_match(CQP_INTERFACE_EXTRACT_TAG_REGEX, $words[$j], $m))
						{
							$word = $m[1];
							$tag  = $m[2];
						}
						else
							$tag = $word = '[UNREADABLE]';
						
					}
					else
						$word = $words[$j];
					
					$untagged_bits[$ix][] .= $xml_before_string . $word . $xml_after_string;
					if ($tagged_as_well)
						$tagged_bits[$ix][] .= $xml_before_string . $word . '_' . $tag . $xml_after_string;
				}
				/* arrays to strings now we have it all */
				$untagged_bits[$ix] = trim(implode(' ', $untagged_bits[$ix]));
				$tagged_bits[$ix]   = trim(implode(' ', $tagged_bits[$ix]));
				
				/* if this chunk is the node, wrap in the deliminter. */
				if ($ix == 1)
				{
					$untagged_bits[$ix] = $hit_delimiter_before . $untagged_bits[$ix] . $hit_delimiter_after;
					if ($tagged_as_well)
						$tagged_bits[$ix] = $hit_delimiter_before . $tagged_bits[$ix] . $hit_delimiter_after;
				}
			}
			
			$kwiclimiter = ($download_view_mode == 'kwic' ? "\t" : ' ');
			$untagged = implode($kwiclimiter, $untagged_bits);
			if ($tagged_as_well)
				$tagged = "\t" . implode($kwiclimiter, $tagged_bits);
			else
				$tagged = '';
			/* and the actual concordance field(s) is/are complete. */
			
			
			
			if (!empty($fields_to_include)) 
			{
				$categorisation_string = "\t";

				foreach(metadata_of_text($Corpus->name, $text_id, $fields_to_include) as $field => $value)
				{
					if (isset($category_descriptions[$field])) 
						$categorisation_string .= $category_descriptions[$field][$value] . "\t";
					else
						$categorisation_string .= $value . "\t";
				}
				if (substr($categorisation_string, -1) == "\t")
					$categorisation_string = substr($categorisation_string, 0, -1);
			}
			else
				$categorisation_string = '';
			
			
			if (!empty($alx_to_include))
			{
// TODO there is an assumption here that needs testing:
// TODO ... that the aligned regions *appear* in the same order as we requested them in the "show" command above.
// TODO That may not be the case. If not, then the headings will be wrong, and the key into $alx_has_annotation will be wrong.

				$alx_lines = array(); 

				foreach($alx_to_include as $a)
				{
					/* first, work out if this data has tags showing in it. If tagged data as well was downloaded, we download tags.
					 * NB. to save space, unlike the main-language column, we DON'T download 2 different columsn for parallel regions.  */
					$this_alx_tagged_as_well = ( $tagged_as_well && $alx_has_annotation[$a] );
// show_var($this_alx_tagged_as_well);
// show_var($tagged_as_well);
// show_var($alx_has_annotation);
					
					$alx_input = trim(preg_replace("/-->$a:\s/", '', $kwic[++$i]));
					
					if ($alx_input == '(no alignment found)')
						$alx_lines[] = '(no alignment found)';
					else
					{
						preg_match_all(CQP_INTERFACE_WORD_REGEX, $alx_input, $m, PREG_PATTERN_ORDER);
						$words = $m[4];
						$xml_before_array = $m[1];
						$xml_after_array  = $m[5];
						$ntok = count($words);
						
						/* process the chunk word by word, including XML viz if necessary */
						$alx_word_bits = array();
						for ($j = 0; $j < $ntok; $j++)
						{
							/* apply XML visualisations */
							if ($include_visualisable_xml)
							{
								$xml_before_string = apply_xml_visualisations($xml_before_array[$j], $xml_viz_index);
								if (!empty($xml_before_string))
									$xml_before_string .= ' ';
								$xml_after_string  = apply_xml_visualisations($xml_after_array[$j], $xml_viz_index);
								if (!empty($xml_after_string))
									$xml_after_string = ' ' . $xml_after_string;
							}
							
							if($this_alx_tagged_as_well)
							{
								if (preg_match(CQP_INTERFACE_EXTRACT_TAG_REGEX, $words[$j], $m))
								{
									$word = $m[1];
									$tag  = $m[2];
								}
								else
									$tag = $word = '[UNREADABLE]';
							}
							else
								$word = $words[$j];
							$alx_word_bits[] = $xml_before_string . $word . ($this_alx_tagged_as_well ? '_' . $tag :''). $xml_after_string;
						}
						$alx_lines[] = trim(implode(' ', $alx_word_bits));
					}
				}
				$alx_string = "\t" . implode("\t", $alx_lines);
			}
			else
				$alx_string = '';
			
			
			$link = ($context_url ? "\t". url_absolutify("context.php?qname=$qname&batch=" . ($batch_start + $t_i) ) : '');
			
			
			echo $line_indicator, "\t", $text_id, "\t", $untagged, $tagged, $categorisation_string, $alx_string, $link;
			
			if ($include_positions)
				echo "\t", $match, "\t", $matchend;
			
			echo $eol;
		
		} /* end loop for each line */
	
	} /* end loop for concordance line batch download */
	
	/* just in case ... */
	if ($n_of_solutions > 100)
		php_execute_time_relimit();

} /* end of if ($_GET['downloadGo'] === 'yes') */

else
{
	/* --------------------------------------- *
	 * write an HTML page with all the options *
	 * --------------------------------------- */
	
	echo print_html_header($Corpus->title . " -- CQPweb Concordance Download", $Config->css_path, array('cword'));
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Download concordance</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<div style="display:block">
					<form style="display:inline-block" action="download-conc.php" method="get">
						<input type="submit" value="Download with typical settings for copy-paste into Word, Excel etc.">
						<input type="hidden" name="qname" value="<?php echo $qname; ?>">
						<input type="hidden" name="downloadGo" value="yes">
						<input type="hidden" name="downloadTypical" value="copypaste">
					</form>
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<form style="display:inline-block" action="download-conc.php" method="get">
						<input type="submit" value="Download with typical settings for FileMaker Pro">
						<input type="hidden" name="qname" value="<?php echo $qname; ?>">
						<input type="hidden" name="downloadGo" value="yes">
						<input type="hidden" name="downloadTypical" value="filemaker">
					</form>
				</div>
			</td>
		</tr>
	</table>
	<form action="download-conc.php" method="get">
		<input type="hidden" name="qname" value="<?php echo $qname; ?>">
		<input type="hidden" name="downloadGo" value="yes">
		<input type="hidden" name="downloadTypical" value="NULL">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">Detailed output options</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2" align="center">
					&nbsp;<br>
					Formatting options
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" width="50%">
					Choose operating system on which you will be working with the file:
				</td>
				<td class="concordgeneral">
					<select name="downloadLinebreak">
						<?php echo print_download_crlf_options(); ?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Print short handles or full values for text categories:</td>
				<td class="concordgeneral">
					<select name="downloadFullMeta">
						<option value="full" selected>full values</option>
						<option value="handles">short handles</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Mark query results as <b>&lt;&lt;&lt; result &gt;&gt;&gt;</b>: </td>
				<td class="concordgeneral">
					<select name="downloadResultAnglebrackets">
						<option value="1">Yes</option>
						<option value="0" selected>No</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Size of context: </td>
				<td class="concordgeneral">
					<select name="downloadContext">
						<option value="1">1 words each way</option>
						<option value="2">2 words each way</option>
						<option value="3">3 words each way</option>
						<option value="4">4 words each way</option>
						<option value="5">5 words each way</option>
						<option value="10" selected>10 words each way</option>
						<option value="20">20 words each way</option>
						<?php
						if (PRIVILEGE_TYPE_CORPUS_RESTRICTED < $Corpus->access_level)
							foreach([50, 100, 200, 300, 400, 500] as $words)
								if ($Corpus->max_extended_context >= $words) 
									echo "\n\t\t\t\t\t\t<option value=\"$words\">$words words each way</option>";
						echo "\n";
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Download both tagged and untagged version of your results: </td>
				<td class="concordgeneral">
					<select name="downloadTaggedAndUntagged">
						<option value="1" selected>Yes</option>
						<option value="0">No</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Write information about table columns at the beginning of file:</td>
				<td class="concordgeneral">
					<select name="downloadHeadType">
						<option value="NULL">No</option>
						<option value="tabs" selected>Yes - column headings</option>
						<option value="list">Yes - printer-friendly list</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Format of output - KWIC or line:</td>
				<td class="concordgeneral">
					<select name="downloadViewMode">
						<option value="kwic" selected>KWIC</option>
						<option value="line">Line</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Include sub-text region boundary markers:</td>
				<td class="concordgeneral">
					<select name="downloadVizXml">
						<option value="1" selected>Yes</option>
						<option value="0">No</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Include corpus positions (required for re-import)</td>
				<td class="concordgeneral">
					<select name="downloadPositions">
						<option value="1" selected>Yes</option>
						<option value="0">No</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Include URL to context display</td>
				<td class="concordgeneral">
					<select name="downloadURL">
						<option value="1" selected>Yes</option>
						<option value="0">No</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Enter name for the downloaded file:</td>
				<td class="concordgeneral">
					<input type="text" name="downloadFilename" value="concordance">
				</td>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2" align="center">
					&nbsp;<br>
					Please tick the text metadata categories that you want to include in your download:
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Method:</td>
				<td class="concordgeneral">
					<select name="downloadMetaMethod">
						<option value="all"            >Download all text metadata</option>
						<option value="allclass"       >Download classification-type metadata only</option>
						<option value="ticked" selected>Download text metadata ticked below</option>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Select from available text metadata:
				<td class="concordgeneral">
					<?php
					$meta_fields = list_text_metadata_fields($Corpus->name);
					if (empty($meta_fields))
						echo "There is no text metadata available in this corpus.";
					foreach ($meta_fields as $field)
						echo "\n\t\t\t\t"
							, "<input type=\"checkbox\" name=\"downloadMeta_$field\" id=\"downloadMeta:$field\" value=\"1\"> "
							, "<label for=\"downloadMeta:$field\">"
								, escape_html(expand_text_metadata_field($Corpus->name, $field))
							, "</label><br>"
 							;
					echo "\n";
					?>
				</td>
			</tr>
			
			<?php 
			if (!empty($align_info))
			{
				?>

				<tr>
					<td class="concordgrey" colspan="2" align="center">
						&nbsp;<br>
						You can include columns in your download that contain
						the query hit's aligned region from one or more parallel corpora:
						<br>&nbsp;
					</td>
				</tr>
				<tr>
					<td class="concordgeneral">Select from available parallel corpora:
					<td class="concordgeneral">
						<?php 
						foreach ( $align_info as $alx => $desc )
							echo "\n\t\t\t\t<input type=\"checkbox\" name=\"downloadAlign_"
								, $alx
								, '" id="downloadAlign_'
								, $alx
								, ':1" value="1"> <label for="downloadAlign_'
								, $alx
								, ':1">'
								, escape_html($desc)
								, "</label><br>\n"
	 							;
						?>
					</td>
				</tr>
				
				<?php 
			}
			
			?>
			
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					&nbsp;<br>
					<input type="submit" value="Download with settings above">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>

	<?php
	
	/* only display the button if the user is allowed to use it. */
	if (PRIVILEGE_TYPE_CORPUS_RESTRICTED < $Corpus->access_level)
	{
		?>
		
		<form action="download-tab.php" method="get">
			<input type="hidden" name="qname" value="<?php echo $qname; ?>">
			<table class="concordtable fullwidth">
				<tr>
					<th class="concordtable" colspan="2">Switch download type</th>
				</tr>
				<tr>
					<td class="concordgeneral" colspan="2" align="center">
						&nbsp;<br>
						<input type="submit" value="Download query as plain-text tabulation">
						<br>&nbsp;
					</td>
				</tr>
			</table>
		</form>
		
		<?php
	}
	
	echo print_html_footer('downloadconc');

	/*
	 * should we have the functionality to allow an annotation OTHER THAN the primary attribute
	 * to be selected for a concordance download?
	 * 
	 * For now, NO, because we already have the ability to access arbitrary annotations via "tabulate".
	 */
	

} /* end of the huge determining if-else */


/* disconnect CQP child process and mysql */
cqpweb_shutdown_environment();


