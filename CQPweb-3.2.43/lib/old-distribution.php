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


// comntains old distrivutin code for reference. Delete and get rid of when  not logner needed.


/**
 * @file
 * 
 * This file contains the code for calculating and showing distribution of hits across text cats.
 */

/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');
require('../lib/environment.php');


/* include function library files */
require("../lib/sql-lib.php");
require("../lib/general-lib.php");
require("../lib/html-lib.php");
require("../lib/concordance-lib.php");
require("../lib/postprocess-lib.php");
require("../lib/exiterror-lib.php");
require("../lib/useracct-lib.php");
require('../lib/corpus-lib.php');
require("../lib/metadata-lib.php");
require("../lib/scope-lib.php");
require("../lib/cache-lib.php");
require("../lib/xml-lib.php");
require("../lib/db-lib.php");
require("../lib/cqp.inc.php");



// here beginneth the SPLIT in distribution.inc.php. to be got shut of at some later point..... (3.3.0 would be a good spot.)


cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);





/* ------------------------------- *
 * initialise variables from $_GET *
 * and perform initial fiddling    *
 * ------------------------------- */




/* this script takes all of the GET parameters from concordance.php,
 * but only qname is absolutely critical, the rest just get passed */

$qname = safe_qname_from_get();

/* all scripts that pass on $_GET['theData'] have to do this, to stop arg passing adding slashes */
if (isset($_GET['theData']))
// 	$_GET['theData'] = prepare_query_string($_GET['theData']);
	unset($_GET['theData']);
// TODO. The above is a BNCweb holdover. Commented out experimentally. Check for use of theData in files other than "concordance.inc.php"






/* parameters unique to this script */


/* workout the type of distribution we want .... text-based or xml-based? Get the attribute name. */ 
if (isset($_GET['distXml']) && '--text' != $_GET['distXml'])
	$distribution_att = cqpweb_handle_enforce($_GET['distXml']);
else
	$distribution_att = '--text';
/* nb.  "--text" is the standard code used for "this is special, use the text metadata table used throughout the system" */
//TODO
//TODO
// TODO on 2nd thorughts
// TODO we cannot rely on getting distXML thru the above method.
// TODO for the download script - fine. Redirect now works, with the above.
// tODO though we should prob change the "distXml" to be "downloadXml" or summat similar. 
//TODO However, folr general use, the "classification" which contains the field will ALSO need to contain the att.
// eg classifcation=genre
// needs ot become classification=--text~genre
// so we can then have
// classification=u_who~sex
// and we can use double or triple ~~~ ~~ for the "special" ones.
// which are: 
//TODO
//TODO
	// becasue of the ntoes above elt's bodge this for now.
	$distribution_att = '--text';
	if ($_GET['classification'] == '~~idfreqs~u_who')
		if ($present_corpus_has_a_u_who)
		{
			$classification = '~~all';
			$distribution_att = 'u_who';
		}
	// becasue of the ntoes below elt's bodge this.
	$distribution_att = '--text';
	if ($_GET['classification'] == '~~classfreqs~u_who')
		if ($present_corpus_has_a_u_who)
		{
			$classification = '~~all';
			$distribution_att = 'u_who';	
		}


/* specific classification? Or just show 'em all? */

if (isset($_GET['classification']))
	$class_scheme_to_show = $_GET['classification'];
else
	$class_scheme_to_show = '~~all';
	
if (isset($_GET['crosstabsClass']))
	$class_scheme_for_crosstabs = $_GET['crosstabsClass'];
else
	$class_scheme_for_crosstabs = '~~none';
// TODO we#'re going ot get shut of this. 

/* crosstabs only allowed if general information not selected */

// TODO expand ~~textfreqs to also allow ~~idfreqs~ATTRIBUTE
// idfreqs~~--text??

if ($class_scheme_to_show == '~~all' || $class_scheme_to_show == '~~textfreqs') 
	$class_scheme_for_crosstabs = '~~none';
/* nb crosstabs also overriden if "text frequency" is selected */


if (isset($_GET['showDistAs']) && $_GET['showDistAs'] == 'graph' && $class_scheme_to_show != '~~textfreqs')
{
	$print_function = 'do_distribution_graph';
	$class_scheme_for_crosstabs = '~~none';
}
else
	$print_function = 'do_distribution_table';

/* as you can see above, if graph is selected, then crosstabs is overridden */

/* do we want a nice HTML table or a downloadable table? */
$download_mode = ( isset($_GET['tableDownloadMode']) && 1 == $_GET['tableDownloadMode'] );





/* work out bits of SQL (e.g. the join-table) for the distribution analysis. 
 * By default, this is the corpus's text-metadata table. But it can also be an XML idlink table. */

$xml_info = get_all_xml_info($Corpus->name);

if ('--text' == $distribution_att)
{
	$join_table = "text_metadata_for_{$Corpus->name}";
	$join_field = "text_id";
	$join_ntoks = "words";

	$db_idfield = "text_id";
	
	// TODO the alias n_regions is used in SQL. But some vars are still called "text". Print "texts" or the XML description + "regions".
}
else 
{
	/* check is real xml element. */
// 	if (false === ($idlink_att_info = get_xml_info($Corpus->name, $distribution_att))) // we need em all anyway for the form, so don't request separately
	if (! isset($xml_info[$distribution_att]))
		exiterror("You tried to do a distribution analysis on a non-existent type of XML region.");
	else
		$idlink_att_info = $xml_info[$distribution_att];
		
	/* check it has idlink datatype. */
	if (METADATA_TYPE_IDLINK != $idlink_att_info->datatype)
	{
		// nonurgent TODO what should happen if a distribution across something that doesn'#t have an idlink is requested?
		// worry about this later. Should rpob be a distribution across actual values (from the att).
		// for now, just error-out.
		
		exiterror("There is no linked metadata for this type of XML region.");
	}
	

	// TODO
		
		// get the needed xml/idlink metadata
		
		// work out the columns etc.
	
	/* set the join_* variables appropriately. */
	$join_table = get_idlink_table_name($Corpus->name, $distribution_att);
	$join_field = "__ID";
	$join_ntoks = "n_tokens";

	$db_idfield = $distribution_att; 
}





/* does a db for the distribution exist? */

/* search the db list for a db whose parameters match those of the query named as qname;
 * if it doesn't exist, create one */

$query_record = QueryRecord::new_from_qname($qname);
if (false === $query_record)
	exiterror("The specified query $qname was not found in cache!");

$db_record = check_dblist_parameters(new DbType(DB_TYPE_DIST), $query_record->cqp_query, $query_record->query_scope, $query_record->postprocess);


if (false === $db_record)
{
	$dbname = create_db(new DbType(DB_TYPE_DIST), $qname, $query_record->cqp_query, $query_record->query_scope, $query_record->postprocess);
	$db_record = check_dblist_dbname($dbname);
}
else
{
	$dbname = $db_record['dbname'];
	touch_db($dbname);
}
/* this dbname & its db_record can be globalled by the various print-me functions */





/* 
 * Set up global-able variables for thinned-query-extrapolation, and populate from the query record.
 * 
 * If the query has been thinned at any point, add extrapolation columns to the output.
 */
$extrapolate_from_thinned = $query_record->postprocess_includes_thin();
$extrapolation_factor     = $query_record->get_thin_extrapolation_factor();
// $extra_category_filters   = $query_record->get_extra_category_filters();
$display_colspan          = ' colspan="5" ';
if ($extrapolate_from_thinned) 
{
	$extrapolation_render = number_format(1.0/$extrapolation_factor, 4);
	$display_colspan = ' colspan="7" ';
}


/*
 * ==================================
 * VARIABLE SETUP IS NOW COMPLETE ...
 * ==================================
 */



/* 
 * This "if/else" covers the rest of the script. 
 * It calls blocks of code contained as this-script-only functions
 * (later in the file).
 */
if ($download_mode)
	do_distribution_plaintext_download();
else
{
	/* begin HTML output */
	echo print_html_header($Corpus->title . " -- distribution of query solutions", 
	                       $Config->css_path, 
	                       array('cword', 'distTableSort'));


	/* -------------------------------- *
	 * print upper table - control form * 
	 * -------------------------------- */
	
	/* get a list of handles and descriptions for classificatory metadata fields in this corpus */
	$class_scheme_list = list_text_metadata_classifications();
	
	
	?>
	<form action="redirect.php" method="get">
				<input type="hidden" name="qname" value="<?php echo $qname; ?>" />
	
	<table class="concordtable fullwidth">
	
	<tr>
		<th colspan="4" class="concordtable">
				<?php echo print_distribution_header_line($query_record, true); ?>
			</th>
	</tr>
	
	<?php 
	echo print_distribution_extrapolation_header(
			$class_scheme_to_show == '~~textfreqs' ? false :  ($print_function == 'print_distribution_table' ? 'column' : 'tip') 
			); 
	?> 
	
		<tr>
			<td class="concordgrey">Categories:</td>
			<td class="concordgrey">
				<select name="classification">
					<?php
					
					$selected_done = false;
					$class_desc_to_pass = "";
					
					foreach($class_scheme_list as $c)
					{
						echo "\n\t\t\t\t\t<option value=\"" . ($c['handle']) . '"';
						if ($c['handle'] == $class_scheme_to_show)
						{
							$class_desc_to_pass = $c['description'];
							echo ' selected="selected"';
							$selected_done = true;
						}
						echo '>Text: ' . ($c['description']) . '</option>';
// TODO: maybe make "text" only appear IFF needed???? 
					}
					
					if ($selected_done)
						$tf_selected = $all_selected = '';
					else
					{
						$tf_selected  = ($class_scheme_to_show == '~~textfreqs' ? ' selected="selected"' : '');
						$all_selected = ($class_scheme_to_show == '~~all'       ? ' selected="selected"' : '');
					}
					echo "\n\t\t\t\t\t<option value=\"~~all\"$all_selected>All classifications for texts</option>";
					
// TODO: having done 
					
					
					
					echo "\n\t\t\t\t\t<option value=\"~~textfreqs\"$tf_selected>Text-frequency information</option>\n";

					//Commented out the correct way by now - let's just bodge it to get it up and running;
					if ($present_corpus_has_a_u_who)
					{
			// tODO these need to be slected where necessary
						echo "\n\t\t\t\t\t<option value=\"~~classfreqs~u_who\">Distribution over speaker categories</option>";
						echo "\n\t\t\t\t\t<option value=\"~~idfreqs~u_who\">Speaker frequency information</option>\n";
					}
// 					foreach($xml_info as $x)
// 						if (METADATA_TYPE_IDLINK == $x->datatype)
// 							echo "\n\t\t\t\t\t<option value=\"~~textfreqs\"$tf_selected>XML-frequency information</option>\n";
/// TODO make the bove line produce the right thing!!!
// TODO the tf_selected / all_selected binary prob will not work now we have many of this sort.
// So, 
							

					?>
				</select>
			</td>
			<td class="concordgrey">Show as:</td>
			<td class="concordgrey">
				<select name="showDistAs">
					<option value="table"
						<?php 
							echo ($print_function != 'print_distribution_graph' ? ' selected="selected"' : '');
						?>>Distribution table</option>
					<option value="graph"
						<?php
							echo ($print_function == 'print_distribution_graph' ? ' selected="selected"' : '');
						?>>Bar chart</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Category for crosstabs:</td>
			<td class="concordgrey">
				<select name="crosstabsClass">
						<?php
// TODO crosstabs needs an update.
// how will it work?
// can we cross tab, say, genre, and speaker sex? check bncweb.
						
						$selected_done = false;
						$class_desc_to_pass_for_crosstabs = "";
						
						foreach($class_scheme_list as $c)
						{
							echo '
								<option value="' . ($c['handle']) . '"';
							if ($c['handle'] == $class_scheme_for_crosstabs)
							{
								$class_desc_to_pass_for_crosstabs = $c['description'];
								echo ' selected="selected"';
								$selected_done = true;
							}
							echo '>' . ($c['description']) . '</option>';
						}
						if ($selected_done)
							echo '
								<option value="~~none">No crosstabs</option>';
						else
							echo '
								<option value="~~none" selected="selected">No crosstabs</option>';
						?>
						
				</select>
			</td>
			<td class="concordgrey">
				<!-- This cell kept empty to add more controls later --> 
				&nbsp;
			</td>
			<td class="concordgrey">
				<select name="redirect">
					<option value="refreshDistribution" selected="selected">Show distribution</option>
					<option value="distributionDownload">Download text frequencies</option>
					
					<?php
					/* create an option for each possible type of download (XML idlinks). */
					foreach ($xml_info as $x)
						if (METADATA_TYPE_IDLINK == $x->datatype)
							echo "\n\t\t\t\t\t"
 								, '<option value="distributionDownload~'
 								, $x->handle
 								, '">Download '
 								, escape_html($x->description)
 								, ' frequencies</option>'
 								;
					?>
					
					<option value="newQuery">New query</option>
					<option value="backFromDistribution">Back to query result</option>
				</select> 
				<input type="submit" value="Go!" /></td>
				
				<?php
				
				/* iff we have a per-page / page no passed in, pass it back, so we can return to
				 * the right place using the back-from-distribution option */
				
				if (isset($_GET['pageNo']))
				{
					$_GET['pageNo'] = (int)$_GET['pageNo'];
					echo "<input type=\"hidden\" name=\"pageNo\" value=\"{$_GET['pageNo']}\" />";
				}
				if (isset($_GET['pp']))
				{
					$_GET['pp'] = (int)$_GET['pp'];
					echo "<input type=\"hidden\" name=\"pp\" value=\"{$_GET['pp']}\" />";
				}
				
				?>	
			</td>
		</tr>
	
	<?php 
	if (count($class_scheme_list) == 0  && $class_scheme_to_show != '~~textfreqs')
	{
		?>
		<tr>
			<th class="concordtable" colspan="4">
				This corpus has no text-classification metadata, so the distribution cannot be shown.
				You can still select the &ldquo;<em>text-frequency information</em>&rdquo; command from the menu above.
			</th>
		</tr>
		<?php
	}
	?>
	
	</table>
	</form>

	<?php
	
	echo '<table class="concordtable" width="100%">';
	
	
	if ($class_scheme_for_crosstabs == '~~none')
	{
		switch ($class_scheme_to_show)
		{
		case '~~all':
			/* show all schemes, one after another */
			foreach ($class_scheme_list as $c)
				$print_function($c['handle'], $c['description'], $qname);
			break;
		
		case '~~textfreqs':
			do_distribution_textfreqs($qname);
			break;
			
		default:
			/* print lower table - one classification has been specified */
			$print_function($class_scheme_to_show, $class_desc_to_pass, $qname);
		}
	
	}
	else
	{
		/* do crosstabs */
		do_distribution_crosstabs($class_scheme_to_show, $class_desc_to_pass, $class_scheme_for_crosstabs, $class_desc_to_pass_for_crosstabs, $qname);
	}
	
	
	
	echo '</table>';
	
	echo print_html_footer('dist');

} /* end of "else" for "if download_mode" */


cqpweb_shutdown_environment();

exit(0);




/* ------------- *
 * END OF SCRIPT *
 * ------------- */




/*
 * A NOTE ON THE FUNCTION NAMING CONVENTION IN THIS FILE
 * -----------------------------------------------------
 * 
 * Functions which lead with "print_*" all return a string (and echo nothing to STDOUT).
 * 
 * Functions which lead with "do_*" return nothing - instead they cause something (HTML or plain text) to be output to STDOUT.
 * 
 * This means that the "print_*" convention matches that in, for instnace, the html-lib file.
 * 
 */




function do_distribution_textfreqs($qname_for_link)
{
	global $Corpus;
	global $Config;
	
	global $dbname;
	global $db_record;
	
	global $join_table;
	global $join_field;
	global $join_ntoks;
	
	global $db_idfield;


	
	$sql = "SELECT db.$db_idfield as $db_idfield, md.$join_ntoks as n_tokens, count(*) as hits 
		FROM `$dbname` as db 
		LEFT JOIN $join_table as md 
		ON db.$db_idfield = md.$join_field
		GROUP BY db.$db_idfield"
		;

	$result = do_sql_query($sql);
	
	$master_array = array();
	$i = 0;
	while ( false !== ($t = mysqli_fetch_assoc($result)))
	{
		$master_array[$i] = $t;
		$master_array[$i]['per_mill'] = round(($t['hits'] / $t['n_tokens']) * 1000000, 2);
		$i++;
	}
	// TODO would it be quicker to do the above in MySQL and ONLY get top 15 / bottom 15?
	// because on a slow machine, this code seems to have caused a 15-sec-or-so runtime, 
	// so it may be inefficient to do it in PHP.

	?>
	
	<tr>
		<th colspan="4" class="concordtable">Your query was <i>most</i> frequently found in the following texts:</th>
	</tr>
	<tr>
		<th class="concordtable">Text</th>
		<th class="concordtable">Number of words</th>
		<th class="concordtable">Number of hits</th>
		<th class="concordtable">Frequency<br />per million words
		</th>
	</tr>
	
	<?php

// 	usort($master_array, "text_freq_comp_desc");
	usort($master_array, 
			function ($a, $b)
			{
			    if ($a['per_mill'] == $b['per_mill'])
			        return 0;
			    return ($a['per_mill'] < $b['per_mill']) ? 1 : -1;
			}
		);

	
	for ( $i = 0 ; $i < $Config->dist_num_files_to_list && isset($master_array[$i]) ; $i++ )
	{
		$textlink = "concordance.php?qname=$qname_for_link&newPostP=text&newPostP_textTargetId={$master_array[$i][$db_idfield]}";
		?>
		<tr>
			<td align="center" class="concordgeneral"><a
				href="textmeta.php?text=<?php echo $master_array[$i][$db_idfield]; ?>">
							<?php echo $master_array[$i][$db_idfield]; ?> 
						</a></td>
			<td align="center" class="concordgeneral">
						<?php echo number_format((float)$master_array[$i]['n_tokens']); ?> 
					</td>
			<!-- note - link to restricted query (to just that text) needed here -->
			<td align="center" class="concordgeneral"><a
				href="<?php echo $textlink; ?>">
							<?php echo number_format((float)$master_array[$i]['hits']); ?> 
						</a></td>
			<td align="center" class="concordgeneral">
						<?php echo $master_array[$i]['per_mill']; ?> 
					</td>
		</tr>
		<?php
	}	


	?>
	<tr>
		<th colspan="4" class="concordtable">
			Your query was <i>least</i> frequently found in the following texts
			(only texts with at least 1 hit are included):
		</th>
	</tr>
	<tr>
		<th class="concordtable">Text</th>
		<th class="concordtable">Number of words</th>
		<th class="concordtable">Number of hits</th>
		<th class="concordtable">Frequency<br />per million words
		</th>
	</tr>
	<?php

// 	usort($master_array, "text_freq_comp_asc");
	usort($master_array, 
			function ($a, $b)
			{
			    if ($a['per_mill'] == $b['per_mill'])
			        return 0;
			    return ($a['per_mill'] < $b['per_mill']) ? -1 : 1;
			}
		);

	
	for ( $i = 0 ; $i < $Config->dist_num_files_to_list && isset($master_array[$i]) ; $i++ )
	{
		// TODO better variable name!
		$textlink = "concordance.php?qname=$qname_for_link&newPostP=text&newPostP_textTargetId={$master_array[$i][$db_idfield]}";
		
		// TODO the textmeta link below needs to be conditionalised.
		?>
		
		<tr>
			<td align="center" class="concordgeneral">
				<a href="textmeta.php?text=<?php echo $master_array[$i][$db_idfield]; ?>"><?php echo $master_array[$i][$db_idfield]; ?></a>
			</td>
			<td align="center" class="concordgeneral">
				<?php echo number_format((float)$master_array[$i]['n_tokens']); ?>
			</td>
			<!-- note - link to restricted query (to just that text) needed here -->
			<td align="center" class="concordgeneral">
				<a href="<?php echo $textlink; ?>"><?php echo number_format((float)$master_array[$i]['hits']); ?></a>
			</td>
			<td align="center" class="concordgeneral">
				<?php echo $master_array[$i]['per_mill']; ?>
			</td>
		</tr>
		
		<?php
	}
}






function do_distribution_graph($classification_handle, $classification_desc, $qname_for_link)
{
	global $Corpus;
	global $Config;
	
	global $dbname;
	global $db_record;
	
	global $join_table;
	global $join_field;
	global $join_ntoks;
	
	global $db_idfield;
	
	global $query_record;
	
	global $extrapolate_from_thinned;
	global $extrapolation_factor;
	
	/* just in case! this var is always used within HTML display in this func. */
	$classification_desc = escape_html($classification_desc);

	/* a list of category descriptions, for later accessing */
	$desclist = list_text_metadata_category_descriptions($Corpus->name, $classification_handle); // TODO, make this "if...."

	/* the main query that gets table data */
	$sql = "SELECT md.$classification_handle as handle,
		count(db.$db_idfield) as hits
		FROM $join_table  as md 
		LEFT JOIN $dbname as db 
		ON md.$join_field = db.$db_idfield
		GROUP BY md.$classification_handle";

	
	$result = do_sql_query($sql);


	/* compile the info */
	
	$max_per_mill = 0;
	$master_array = array();
	
	/* for each category: */
	for ($i = 0 ; false !== ($c = mysqli_fetch_assoc($result)) ; $i++)
	{
		/* skip the category of "null" ie no category in this classification */
		if ($c['handle'] == '')
		{
			$i--;
			continue;
		}
		$master_array[$i] = $c;
		
		/*
		TEMP: we can go back to this code later, once we are confident we aren't going to be getting "false" from the method.
		list ($words_in_cat, $texts_in_cat)
			= $query_record->get_search_scope_with_extra_text_restrictions(array("$classification_handle~{$c['handle']}"));
		 */
// begin temp replacement 
show_var($x=array("$classification_handle~{$c['handle']}"));
		$tempvar = $query_record->get_search_scope_with_extra_text_restrictions(array("$classification_handle~{$c['handle']}"));
		if (false === $tempvar)
			exiterror("Drawing this graph requires calculation of a subsection intersect that is not yet possible in this version of CQPweb.");
		else 
			list ($words_in_cat, $texts_in_cat) = $tempvar;
// end temp replacement */
		if (is_null($words_in_cat))
			$words_in_cat = 0;
		if (is_null($texts_in_cat))
			$texts_in_cat = 0;
		
		$master_array[$i]['words_in_cat'] = $words_in_cat;
		$master_array[$i]['per_mill'] = (
			0 == $master_array[$i]['words_in_cat'] 
			? 0
			: round(($master_array[$i]['hits'] / $master_array[$i]['words_in_cat']) * 1000000, 2)
			);
		
		if ($master_array[$i]['per_mill'] > $max_per_mill)
			$max_per_mill = $master_array[$i]['per_mill'];
	}
	
	if ($max_per_mill == 0)
	{
		/* no category in this classification has any hits */
		echo "<tr><th class=\"concordtable\">No category within the classification scheme 
			\"$classification_desc\" has any hits in it.</th></tr></table>
			<table class=\"concordtable\" width=\"100%\">";
		return;
	}
	
	$n = count($master_array);
	$num_columns = $n + 1;

	/* header row */
	
	?>
	<tr>
		<th colspan="<?php echo $num_columns; ?>" class="concordtable">
			<?php echo "Based on classification: <i>$classification_desc</i>"; ?>
		</th>
	</tr>
	<tr>
		<td class="concordgrey"><b>Category</b></td>
		<?php
		
		/* line of category labels */
	
		for($i = 0; $i < $n; $i++)
		{
			echo '<td class="concordgrey" align="center"><b>' . $master_array[$i]['handle'] . '</b></td>';
		}
		
		?>
	</tr>
	<tr>
		<td class="concordgeneral">&nbsp;</td>
		
		<?php
		
		/* line of bars */
	
		for($i = 0; $i < $n; $i++)
		{
			if (empty($desclist[$master_array[$i]['handle']]))
				$this_label = $master_array[$i]['handle'];
			else
				$this_label = escape_html($desclist[$master_array[$i]['handle']]);
	
			$html_for_hover = "Category: <b>$this_label</b><br><hr color=&quot;#000099&quot;>" 
				. '<font color=&quot;#DD0000&quot;>' . $master_array[$i]['hits'] . '</font> hits in '
				. '<font color=&quot;#DD0000&quot;>' . number_format((float)$master_array[$i]['words_in_cat']) 
				. '</font> words.'
	 			;
			if ($extrapolate_from_thinned)
				$html_for_hover .= '<br><hr color=&quot;#000099&quot;><b>Extrapolated</b> no. of hits: <font color=&quot;#DD0000&quot;>' 
					. round($master_array[$i]['hits'] * $extrapolation_factor, 0)
					. '</font> hits<br>(<font color=&quot;#DD0000&quot;>'
					. round($master_array[$i]['per_mill']  * $extrapolation_factor, 2)
					. '</font> per million words).'
					;
				
	
			$this_bar_height = round( ($master_array[$i]['per_mill'] / $max_per_mill) * 100, 0);
	
			/* make this a link to the limited query when I do likewise in the distribution table */
			echo '<td align="center" valign="bottom" class="concordgeneral">'
				, '<a onmouseover="return escape(\'' , $html_for_hover , '\')">'
				, '<img border="1" src="' , $Config->dist_graph_img_path, '" width="70" height="', $this_bar_height, '" align="absbottom"/></a></td>'
				;
		}
		
		?>
		
	</tr>
	<tr>
		<td class="concordgrey"><b>Hits</b></td>

		<?php
		
		/* line of hit counts */
		for ($i = 0; $i < $n; $i++)
			echo "\n\t\t\t", '<td class="concordgrey" align="center">' , $master_array[$i]['hits'] , '</td>';
		?>
		
	</tr>
	<tr>
		<td class="concordgrey"><b>Cat size (MW)</b></td>
		
		<?php
		
		/* line of cat sizes */
		for ($i = 0; $i < $n; $i++)
			echo '<td class="concordgrey" align="center">' 
				, round(($master_array[$i]['words_in_cat'] / 1000000), 2)
				, '</td>'
				;
		?>
		
	</tr>
	<tr>
		<td class="concordgrey"><b>Freq per M</b></td>
		
		<?php
		
		/* line of per-million-words */
		for ($i = 0; $i < $n; $i++)
			echo '<td class="concordgrey" align="center">' 
				, $master_array[$i]['per_mill']
				, '</td>'
				;
		
		/* end the table and re-start for the next graph, so it can have its own number of columns */
		?>
	
	</tr>
</table>

<table class="concordtable fullwidth">

<?php
}









function do_distribution_table($classification_handle, $classification_desc, $qname_for_link)
{
	global $Corpus;
	
	global $dbname;
	global $db_record;
	
	global $join_table;
	global $join_field;
	
	global $db_idfield;
	
	global $query_record;
	
	global $display_colspan;
	
	global $extrapolate_from_thinned;
	global $extrapolation_factor;


	/* just in case! this var is always used within HTML display in this func. */
	$classification_desc = escape_html($classification_desc);

	/* print header row for this table */

	?>

	<tr>
		<th <?php echo $display_colspan; ?> class="concordtable">
			Based on classification: 
			<i><?php echo $classification_desc; ?></i>
		</th>
	</tr>
	<tr>
		<td class="concordgrey">
			Category 
			<a class="menuItem" onClick="distTableSort(this, 'cat')" onMouseOver="return escape('Sort by category')">[&darr;]</a>
		</td>
		<td class="concordgrey" align="center">Words in category</td>
		<td class="concordgrey" align="center">Hits in category</td>
		<td class="concordgrey" align="center">Dispersion<br />(no. texts with 1+ hits)
		</td>
		<td class="concordgrey" align="center">
			Frequency <a class="menuItem" onClick="distTableSort(this, 'freq')" onMouseOver="return escape('Sort by frequency per million')">[&darr;]</a>
			<br />
			per million words in category
		</td>
			
		<?php
		
		if ($extrapolate_from_thinned)
		{
			?>

			<td class="concordgrey" align="center">Hits in category<br />(extrapolated)</td>
			<td class="concordgrey" align="center">
				Frequency 
				<a class="menuItem" onClick="distTableSort(this, 'extr')" 
					onMouseOver="return escape('Sort by extrapolated frequency per million')">[&darr;]</a>
				<br />
				per million words in category
				<br />(extrapolated)
			</td>

			<?php
		}
		?>
			
	</tr>

	<?php



	/* variables for keeping track of totals */
	$total_words_in_all_cats = 0;
	$total_hits_in_all_cats = 0;
	$total_hit_texts_in_all_cats = 0;
	$total_texts_in_all_cats = 0;

	/* a list of category descriptions, for later accessing */
	$desclist = list_text_metadata_category_descriptions($Corpus->name, $classification_handle);
	foreach ($desclist as $k=>$v)
		$desclist[$k] = escape_html($v);

		
// hacky hacky hacky bodge bodge bodge
if ('~~classfreqs~u_who' == $classification_handle)   $classification_handle='__ID';
	/* the main query that gets table data */
	$sql = "SELECT md.$classification_handle as handle,
		count(db.$db_idfield) as hits,
		count(distinct db.$db_idfield) as n_regions
		FROM $join_table  as md 
		LEFT JOIN $dbname as db 
		ON md.$join_field = db.$db_idfield
		GROUP BY md.$classification_handle";

	$result = do_sql_query($sql);

	/* for each category: */
	while (false !== ($c = mysqli_fetch_assoc($result)))
	{
		/* skip the category of "null" ie no category in this classification */
		if ($c['handle'] == '')
			continue;
		
		$hits_in_cat = $c['hits'];
		$hit_texts_in_cat = $c['n_regions'];

		/*
		TODO: we can go back to this code later, once we are confident we aren't going to be getting "false" from the method.
		list ($words_in_cat, $texts_in_cat)
			= $query_record->get_search_scope_with_extra_text_restrictions(array("$classification_handle~{$c['handle']}"));
		 */
// begin temp replacement 
show_var($x=array("$classification_handle~{$c['handle']}"));
		$tempvar = $query_record->get_search_scope_with_extra_text_restrictions(array("$classification_handle~{$c['handle']}"));
		// TODO for idlink we will need to use something other than "extra text restrictions".
		if (false === $tempvar)
			exiterror("Drawing this distribution table requires calculation of a subsection intersect that is not yet possible in this version of CQPweb.");
		else 
			list ($words_in_cat, $texts_in_cat) = $tempvar;
// end temp replacement */
		if (is_null($words_in_cat))
			$words_in_cat = 0;
		if (is_null($texts_in_cat))
			$texts_in_cat = 0;
		
		$link = "concordance.php?qname=$qname_for_link&newPostP=dist&newPostP_distCateg=$classification_handle&newPostP_distClass={$c['handle']}";

		/* print a data row */
		?>

		<tr>
			<td class="concordgeneral" id="<?php echo $c['handle'];?>">
				<?php 
				if (empty($desclist[$c['handle']]))
					echo $c['handle'], "\n";
				else
					echo $desclist[$c['handle']], "\n";
				?>
			</td>
			<td class="concordgeneral" align="center">
				<?php echo $words_in_cat;?> 
			</td>
			<td class="concordgeneral" align="center">
				<a href="<?php echo $link; ?>"><?php echo $hits_in_cat; ?></a>
			</td>
			<td class="concordgeneral" align="center">
				<?php echo "$hit_texts_in_cat out of $texts_in_cat"; ?> 
			</td>
			<td class="concordgeneral" align="center">
				<?php echo(0 == $words_in_cat ? '0' :  round(($hits_in_cat / $words_in_cat) * 1000000, 2) ); ?> 
			</td>
				
			<?php
			
			if ($extrapolate_from_thinned)
			{
				?>
	
				<td class="concordgeneral" align="center">
					<?php echo round($hits_in_cat * $extrapolation_factor, 0); ?>	
				</td>
				<td class="concordgeneral" align="center">
					<?php echo(0 == $words_in_cat ? '0' :  round(($hits_in_cat / $words_in_cat) * 1000000 * $extrapolation_factor, 2) ); ?>  
				</td>
	
				<?php
			}
			
			?>

		</tr>

		<?php
		
		/* add to running totals */
		$total_words_in_all_cats     += $words_in_cat;
		$total_hits_in_all_cats      += $hits_in_cat;
		$total_hit_texts_in_all_cats += $hit_texts_in_cat;
		$total_texts_in_all_cats     += $texts_in_cat;
	}


	/* print total row of table */
	?>
	<tr>

		<td class="concordgrey">Total:</td>
		
		<td class="concordgrey" align="center">
			<?php echo $total_words_in_all_cats; ?> 
		</td>
		<td class="concordgrey" align="center">
			<?php echo $total_hits_in_all_cats; ?> 
		</td>
		<td class="concordgrey" align="center">
			<?php echo $total_hit_texts_in_all_cats; ?> out of <?php echo $total_texts_in_all_cats; ?> 
		</td>
		<td class="concordgrey" align="center">
			<?php echo round(($total_hits_in_all_cats / $total_words_in_all_cats) * 1000000, 2); ?> 
		</td>
			
		<?php
		if ($extrapolate_from_thinned)
		{
			?>
			
			<td class="concordgrey" align="center">
				<?php echo round($total_hits_in_all_cats * $extrapolation_factor, 0); ?> 
			</td>
			<td class="concordgrey" align="center">
				<?php echo round(($total_hits_in_all_cats / $total_words_in_all_cats) * 1000000 * $extrapolation_factor, 2); ?> 
			</td>
			
			<?php
		}
		?>

	</tr>

	<?php
}





/**
 * This is the wrapper function for the cross-tabs function: it foreaches across the category.
 * 
 * @param string $class_scheme_to_show
 * @param string $class_desc_to_pass
 * @param string $class_scheme_for_crosstabs
 * @param string $class_desc_to_pass_for_crosstabs
 * @param string $qname_for_link
 */
function do_distribution_crosstabs($class_scheme_to_show, $class_desc_to_pass, 
	$class_scheme_for_crosstabs, $class_desc_to_pass_for_crosstabs, $qname_for_link)
{
	global $Corpus;
	
	$class_desc_to_pass = escape_html($class_desc_to_pass);
	$class_desc_to_pass_for_crosstabs = escape_html($class_desc_to_pass_for_crosstabs);

	/* get a list of categories for the category specified in $class_scheme_to_show */
	$desclist = list_text_metadata_category_descriptions($Corpus->name, $class_scheme_to_show);
	
	/* for each category */
	foreach ($desclist as $h => $d)
	{
		if (empty($d))
			$d = $h;
		else 
			$d = escape_html($d) ;
		$table_heading = "$class_desc_to_pass_for_crosstabs / where <i>$class_desc_to_pass</i> is <i>$d</i>";
	
		do_distribution_crosstabs_once($class_scheme_for_crosstabs, $table_heading, $class_scheme_to_show, $h, $qname_for_link);
	}
}




/* big waste of code having all this twice - but it does no harm, and there ARE small changes */
function do_distribution_crosstabs_once($classification_handle, $table_heading,
	$condition_classification, $condition_category, $qname_for_link)
{
	global $Corpus;
	
	global $dbname;
	global $db_record;
	
	global $join_table;
	global $join_field;
	
	global $db_idfield;
	
	global $query_record;
	
	global $display_colspan;
	
	global $extrapolate_from_thinned;
	global $extrapolation_factor;

	
	/* NB. here would the be point to escape the HTML for the table heading. But no need - it was assembled as HTML. */


	/* print header row for this table */
	?>
	<tr>
		<th <?php echo $display_colspan; ?> class="concordtable">
			<?php echo $table_heading; ?> 
		</th>
	</tr>
	<tr>
		<td class="concordgrey">Category</td>
		<td class="concordgrey" align="center">Words in category</td>
		<td class="concordgrey" align="center">Hits in category</td>
		<td class="concordgrey" align="center">Dispersion<br />(no. texts with 1+ hits)</td>
		<td class="concordgrey" align="center">Frequency<br />per million words in category</td>
			
		<?php
		if ($extrapolate_from_thinned)
		{
			?>

			<td class="concordgrey" align="center">Hits in category<br />(extrapolated)</td>
			<td class="concordgrey" align="center">Frequency 
				<a class="menuItem"
				onClick="distTableSort(this, 'freq')" onMouseOver="return escape('Sort by frequency per million')"
				>[&darr;]</a>
				<br />
				per million words in category <br />(extrapolated)
			</td>

			<?php
		}
		?>
			
	</tr>
	<?php


	/* variables for keeping track of totals */
	$total_words_in_all_cats = 0;
	$total_hits_in_all_cats = 0;
	$total_hit_texts_in_all_cats = 0;
	$total_texts_in_all_cats = 0;

	/* a list of category descriptions, for later use/ Always pritned direct to HTML. */
	$desclist = list_text_metadata_category_descriptions($Corpus->name, $classification_handle);
	foreach ($desclist as $k=>$v)
		$desclist[$k] = escape_html($v);

	/* the main query that gets table data */
	$sql = "SELECT md.$classification_handle as handle,
		count(db.$db_idfield) as hits,
		count(distinct db.$db_idfield) as n_regions
		FROM $join_table  as md 
		LEFT JOIN $dbname as db 
		ON md.$join_field = db.$db_idfield
		WHERE $condition_classification = '$condition_category'
		GROUP BY md.$classification_handle"
 		;

	$result = do_sql_query($sql);

	/* for each category: */
	while (false !== ($c = mysqli_fetch_assoc($result)))
	{
		/* skip the category of "null" ie no category in this classification */
		if ($c['handle'] == '')
			continue;
			
		$hits_in_cat = $c['hits'];
		$hit_texts_in_cat = $c['n_regions']	;
		/*
		TEMP: we can go back to this code later, once we are confident we aren't going to be getting "false" from the method.
		list ($words_in_cat, $files_in_cat) 
			= $query_record->get_search_scope_with_extra_text_restrictions(
				array("$classification_handle~{$c['handle']}", "$condition_classification~$condition_category"));
		 */
// begin temp replacement 
		$tempvar = $query_record->get_search_scope_with_extra_text_restrictions(
										array("$classification_handle~{$c['handle']}", "$condition_classification~$condition_category"));
		if (false === $tempvar)
			exiterror("Drawing this crosstabs table requires calculation of a subsection intersect that is not yet possible in this version of CQPweb.");
		else 
			list ($words_in_cat, $texts_in_cat) = $tempvar;
// end temp replacement */
// 		list ($words_in_cat, $files_in_cat) 
// 			= $query_record->get_search_scope_with_extra_text_restrictions(
// 				array("$classification_handle~{$c['handle']}", "$condition_classification~$condition_category"));

		/* print a data row */
		?>
		
		<tr>
			<td class="concordgeneral">
				<?php 
				if (empty($desclist[$c['handle']]))
					echo $c['handle'], "\n";
				else
					echo $desclist[$c['handle']], "\n";
				?>
			</td>
			<td class="concordgeneral" align="center">
				<?php echo $words_in_cat;?> 
			</td>
			<td class="concordgeneral" align="center">
				<?php 
				/* TODO (non-high-priority) 
				make this a link to JUST the hits in the cross-tabbed pair of categories in question 
				*/ 
				echo $hits_in_cat; 
				?> 
			</td>
			<td class="concordgeneral" align="center">
				<?php echo "$hit_texts_in_cat out of $texts_in_cat"; ?> 
			</td>
			<td class="concordgeneral" align="center">
				<?php echo ($words_in_cat > 0 ? round(($hits_in_cat / $words_in_cat) * 1000000, 2) : 0) ;?> 
			</td>
			
			<?php
			
			if ($extrapolate_from_thinned)
			{
				?>

				<td class="concordgeneral" align="center">
					<?php echo round($hits_in_cat * $extrapolation_factor, 0); ?>	
				</td>
				<td class="concordgeneral" align="center">
					<?php echo(0 == $words_in_cat ? '0' :  round(($hits_in_cat / $words_in_cat) * 1000000 * $extrapolation_factor, 2) ); ?>  
				</td>

				<?php
			}
			
			?>

		</tr>
		<?php
		
		/* add to running totals */
		$total_words_in_all_cats     += $words_in_cat;
		$total_hits_in_all_cats      += $hits_in_cat;
		$total_hit_texts_in_all_cats += $hit_texts_in_cat;
		$total_texts_in_all_cats     += $texts_in_cat;
	}


	/* print total row of table */
	?>
	
	<tr>

		<td class="concordgrey">Total:</td>
		<td class="concordgrey" align="center">
			<?php echo $total_words_in_all_cats; ?> 
		</td>
		<td class="concordgrey" align="center">
			<?php echo $total_hits_in_all_cats; ?> 
		</td>
		<td class="concordgrey" align="center">
			<?php echo $total_hit_texts_in_all_cats; ?> out of <?php echo $total_texts_in_all_cats; ?> 
		</td>
		<td class="concordgrey" align="center">
			<?php echo ($words_in_cat > 0 ? round(($total_hits_in_all_cats / $total_words_in_all_cats) * 1000000, 2) : 0);?> 
		</td>
		
		<?php
		if ($extrapolate_from_thinned)
		{
			?>
			<td class="concordgrey" align="center">
				<?php echo round($total_hits_in_all_cats * $extrapolation_factor, 0); ?> 
			</td>
			<td class="concordgrey" align="center">
				<?php echo round(($total_hits_in_all_cats / $total_words_in_all_cats) * 1000000 * $extrapolation_factor, 2); ?> 
			</td>
			<?php
		}
		?>

	</tr>
	
	<?php
}






/**
 * Gets the header line for the distribution interface.
 * 
 * @param  QueryRecord $query_record  The query.
 * @param  bool        $html          Whether to create HTML. Defaults to true. 
 * @return string                     HTML string to print.
 */
// oldfunc commented out
function print_distribution_header_line($query_record, $html = true)
{
	/* Uses assumptions about what we get from the standard solution heading... */
	$solution_header = $query_record->print_solution_heading(NULL, false, $html);
	
	/* split and reunite */
	list($temp1, $temp2) = explode(' returned', $solution_header, 2);
	return rtrim(str_replace('Your', 'Distribution breakdown for', $temp1), ',')
		. ': this query returned'
		. $temp2
		;
}



/**
 * Creates and returns a message about extrapolation data in the distribution display. 
 *  
 * Extrapolation type can be "column" (message for when data is in col 6, 7); or "tip" (message for when data is in a hover-tip);
 * or anything else - default expected is an empty value - in which case, the assumption is that the extrapolation needs to be warned
 * about, but does not actually show up anywhere.
 * 
 * @param string $extrapolation_type  If supplied, the message says that extrapolated data IS present in the display; if not, not. 
 */
function print_distribution_extrapolation_header($extrapolation_type = NULL)
{
	global $extrapolate_from_thinned;
	global $extrapolation_factor;
	global $extrapolation_render;

	if ($extrapolate_from_thinned)
	{
		$shown = true;

		switch ($extrapolation_type)
		{
		case 'column':
			$col_or_tip = 'are given in columns 6 and 7 ';
			break;
		case 'tip':
			$col_or_tip = 'appear in the pop-up if you move your mouse over one of the bars ';
			break;
		default:
			/* Incl. any empty values. In this case, we show the message for when the extrapolated figures aren't actually visible */
			$shown = false;	
			break;
		}

		$msg = ( 
			$shown
				? "

					Extrapolated hit counts for the whole result set, and the corresponding frequencies per million words,
					$col_or_tip
					(thin-factor: $extrapolation_render). 
					The smaller the thin-factor, the less reliable extrapolated figures will be.

				"
 				: "

					(The thin-factor is $extrapolation_render.)
					Therefore, these frequencies will underestimate the real figures. 
					The lower the factor, the worse the underestimation.

				"
				);
		return <<<ENDHTML
			
			<tr>
				<td colspan="4" class="concordgeneral">
				
					Your query result has been thinned.
					 
					$msg
					
				</td>
			</tr>
			
ENDHTML;
	}
	else
		return '';
}


function do_distribution_plaintext_download()
{
	/* -------------------------------------------------------------------------------------- *
	 * Here is how we do the plaintext download of all frequencies (by text, or XML ID-link). *
	 * -------------------------------------------------------------------------------------- */
	
	global $User;
	
	global $dbname;
	global $db_idfield;
	
	global $query_record;
	global $db_record;
	
	global $join_field;
	global $join_ntoks;
	global $join_table;
	
	global $extrapolate_from_thinned;
	global $extrapolation_render;
	
	global $distribution_att;
	global $idlink_att_info;
	
	$sql = "SELECT db.$db_idfield as item_id, md.$join_ntoks as n_tokens, count(*) as hits 
		FROM $dbname as db 
		LEFT JOIN $join_table as md ON db.$db_idfield = md.$join_field
		GROUP BY db.$db_idfield
		ORDER BY db.$db_idfield";
	$result = do_sql_query($sql);
	/* TODO (non-urgent) this seems to be quite a slow query to run.... check optimisation possible? */	

	$eol = $User->eol();
	
	$description = print_distribution_header_line($query_record, false);
	
	$extradesc = 
				( $extrapolate_from_thinned
				? "{$eol}WARNING: your query has been thinned (factor $extrapolation_render)."
					. "{$eol}Therefore, these frequencies will underestimate the real figures. The lower the factor, the worse the underestimation."
				: ''
				);
	
	$filename_prefix = ('--text' == $distribution_att? 'text' : preg_replace('/\W/', '', $idlink_att_info->description));
	
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename={$filename_prefix}_frequency_data.txt");
	
	echo $description, $extradesc, $eol;
	echo "__________________", $eol, $eol;

	if ('--text' == $distribution_att)
		echo "Text\t"
			, "No. words in text\t"
			, "No. hits in text\t"
			, "Freq. per million words"
			, $eol, $eol
			;
	else
	{
		// TODO this has not been tested yet, though it should now work.
		echo "{$idlink_att_info->description}\t"
			, "No. words for {$idlink_att_info->description}\t"
			, "No. hits for {$idlink_att_info->description}\t"
			, "Freq. per million words"
			, $eol, $eol
			;
	}

	while (false !== ($r = mysqli_fetch_object($result)))
		echo $r->item_id, "\t", $r->n_tokens, "\t", $r->hits, "\t", round(($r->hits / $r->n_tokens) * 1000000, 2), $eol;
	
	/* end of code for plaintext download. */
}




