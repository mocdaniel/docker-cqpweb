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


/*
 * Note: a lot of the code in the later functions in this file is extremely wonky in terms of encapsulation.
 * 
 * The use of global variables is ridiculous and needs to be stopped.
 */




function print_statistic_form_options($index_to_select)
{
	$statistic = load_statistic_info();
	$output = '';
	foreach($statistic as $index => $s)
	{
		if ($index == COLLSTAT_RANK_FREQ)		/* this one is saved for last */
			 continue;
		$output .= "\n\t<option value=\"$index\"". ($index_to_select == $index ? ' selected' : '') . ">{$s['desc']}</option>";
	}
	
	$output .= "<option value=\"".COLLSTAT_RANK_FREQ.'" ' . ($index_to_select == COLLSTAT_RANK_FREQ ? ' selected' : '') . ">{$statistic[COLLSTAT_RANK_FREQ]['desc']}</option>";
	
	return $output;
}



function print_fromto_form_options($colloc_range, $index_to_select_from, $index_to_select_to)
{
	global $Corpus;
	
	/* In the /usr context, there is no corpus... */
	if ($Corpus->specified && $Corpus->main_script_is_r2l)
	{
		$rightlabel = ' after the node';
		$leftlabel  = ' before the node';
	}
	else
	{
		$rightlabel = ' to the Right';
		$leftlabel = ' to the Left'; 
	}
	
	$output1 = $output2 = '';
	for ($i = -$colloc_range ; $i <= $colloc_range ; $i++)
	{
		if ( $i > 0 )
			$str = $i . $rightlabel;
		else if ( $i < 0 )
			$str = (-1 * $i) . $leftlabel;
		else   /* $i is 0 so do nothing */
			continue;
	
		$output1 .= "\n\t<option value=\"$i\"" 
			. ($i == $index_to_select_from ? ' selected' : '')
			. ">$str</option>";
		$output2 .= "\n\t<option value=\"$i\"" 
			. ($i == $index_to_select_to   ? ' selected' : '') 
			. ">$str</option>";
	}
	return array($output1, $output2);
}


function print_freqtogether_form_options($index_to_select)
{
	$string = '';
	foreach(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 50, 100) as $n)
		$string .= '
			<option' . ($n == $index_to_select ? ' selected' : '')
			. ">$n</option>";
	return $string;
}

function print_freqalone_form_options($index_to_select)
{
	$string = '';
	foreach(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 15, 20, 50, 100, 500, 1000, 5000, 10000, 20000, 50000) as $n)
		$string .= '
			<option' . ($n == $index_to_select ? ' selected' : '') . ">$n</option>";
	return $string;
}





/**
 * Returns a hash whose keys are the COLLSTAT constants.
 */
function load_statistic_info()
{
	static $info = NULL;
	if (!is_null($info))
		return $info;
	
	$info = array();
	
	/* the labels for the different stats are as follows ... */
	/* note, the 0 is a special index - ie no statistic! (use in if statements and the like) */
	$info[COLLSTAT_RANK_FREQ]['desc'] = 'Rank by frequency';
	$info[COLLSTAT_MI]['desc'] = 'Mutual information';
	$info[COLLSTAT_MI3]['desc'] = 'MI3';
	$info[COLLSTAT_ZSCORE]['desc'] = 'Z-score';
	$info[COLLSTAT_TSCORE]['desc'] = 'T-score';
	$info[COLLSTAT_LOG_LIKELIHOOD]['desc']  = 'Log-likelihood';
	$info[COLLSTAT_DICE]['desc']            = 'Dice coefficient';
	$info[COLLSTAT_LOG_RATIO]['desc']       = 'Log Ratio (filtered)';
	$info[COLLSTAT_LR_CONSERVATIVE]['desc'] = 'Conservative LR';
	
	/* the "extra info" bar is here: appears above the actual collocation table. 
	 * Log Ratio has a long explanation; the rest, just short.*/
	$info[COLLSTAT_MI]['extra'] = '<p><strong>Mutual information</strong> (MI) is an effect-size measure, scoring the collocation strength: 
		how strongly (how exclusively) two items are bound to one another. It is one of the most commonly used collocation measures, but
		tends to give excessively high scores if the frequency of the collocate is low (below about 10).</p>';
	
	$info[COLLSTAT_MI3]['extra'] = '<p><strong>MI3</strong> is a modified version of Mutual Information which is often used for extracting items of terminology, 
		but not often for collocation more generally, because it over-emphasises high frequency items.</p>';
	
	$info[COLLSTAT_ZSCORE]['extra'] = '<p>The <strong>Z-score</strong> is a measure whose results reflect a combination of significance (amount of evidence) and 
		effect size (strength of connection), producing a compromise ranking relative to MI (effect size) and LL (significance).</p>';
	
	$info[COLLSTAT_TSCORE]['extra'] = '<p>The <strong>T-score</strong> is a significance measure that is <strong>not</strong> recommended for calculating collocations 
		(Log-likelihood is better) but is included in CQPweb for backwards compatability as it was very popular in earlier studies.</p>';
	
	$info[COLLSTAT_LOG_LIKELIHOOD]['extra'] = '<p><strong>Log-likelihood</strong> (LL) scores collocations by significance: the higher the score, the more evidence you 
		have that the association is not due to chance. More frequent words tend to get higher log-likelihood scores, because there is more evidence for such words.</p>';
	
	$info[COLLSTAT_DICE]['extra'] = '<p>The <strong>Dice coefficient</strong> is a measure whose results reflect a combination of significance (amount of evidence) and 
		effect size (strength of connection), producing a compromise ranking relative to &ldquo;non-compromise$rdquo; such as MI or LL.</p>';
	
	$info[COLLSTAT_LOG_RATIO]['extra'] = <<<END_OF_EXTRA

		<p>The <strong>Log Ratio</strong> statistic is a measurement of <em>how big the difference is</em> between the (relative) frequency 
		of the collocate alongside the node, and its (relative) frequency in the rest of the corpus or subcorpus.</p>

		<p><!--On its own, Log Ratio is very similar to the Mutual Information measure (both measure <em>effect size</em>). 
		However, CQPweb combines Log Ratio with a statistical-significance filter. 
		The collocate list is <u>sorted</u> by Log Ratio but <u>filtered</u> using Log-likelihood.</p>

		<p>Collocates are only included in the list if they are significant at the 5% level (p &lt; 0.05), adjusted using the Šidák
		correction. For <strong>your current collocation analysis</strong>, that means all collocates displayed have Log-likelihood of at least 
		<strong>\$LL_CUTOFF</strong>.</p>
		-->
		<p>In <strong>the current collocation analysis</strong>, all collocates displayed have Log-likelihood of at least 
		<strong>\$LL_CUTOFF</strong>.</p>

		<p>The use of a log-likelihood filter means that it is not necessary to set high minimum values for <em>Freq(node, collocate)</em>
		and <em>Freq(collocate)</em> when using Log Ratio.</p>

END_OF_EXTRA;
	
	$info[COLLSTAT_LR_CONSERVATIVE]['extra'] = <<<END_OF_EXTRA

		<p>The <strong>Log Ratio</strong> statistic is a measurement of <em>how big the difference is</em> between the (relative) frequency 
		of the collocate alongside the node, and its (relative) frequency in the rest of the corpus or subcorpus.</p>

		<p>The <em>conservative estimate of Log Ratio</em>, <strong>Conservative LR</strong> for short, 
		is the <em>lower bound of the 95% confidence interval</em> surrounding the best-guess estimate 
		of Log Ratio as generated by the usual procedure.</p>

		<p>This means that there is a 97.5% probability that the &ldquo;real&rdquo; LR value is <em>at least as high as or higher than</em>
		the lower bound represented by the Conservative LR. This is what makes it a <strong>conservative</strong> estimate. For this reason, 
		Conservative LR does not need to be used with any additional statistical filter.</p>

END_OF_EXTRA;
	/* long term, once Log Ratio is no longer a new thing, the above two "extra" can be shortened . */

	/* the number of decimal places we use to display the "statistic column: normally 3, but 5 for Dice coefficient */
	$info[COLLSTAT_RANK_FREQ]['decimal_places']       = 0;
	$info[COLLSTAT_MI]['decimal_places']              = 3;
	$info[COLLSTAT_MI3]['decimal_places']             = 3;
	$info[COLLSTAT_ZSCORE]['decimal_places']          = 3;
	$info[COLLSTAT_TSCORE]['decimal_places']          = 3;
	$info[COLLSTAT_LOG_LIKELIHOOD]['decimal_places']  = 3;
	$info[COLLSTAT_DICE]['decimal_places']            = 5;
	$info[COLLSTAT_LOG_RATIO]['decimal_places']       = 3;
	$info[COLLSTAT_LR_CONSERVATIVE]['decimal_places'] = 3;
	
	return $info;
}



/*
 * TODO    ----      * here is where the really shonky global-abuse starts.
 */







/**
 * Gets the SQL statement required to generate the collocation table. 
 * 
 * "soloform" is assumed to be pre-escaped with escape_sql.
 * 
 * Field names (keys) for the table you get back when you actually
 * run the resulting query:
 *
 * $att 		 -- the collocate itself, with the name of the attribute it comes from as the field name 
 * observed 	 -- the number of times the collocate occurs in the window
 * expected 	 -- the number of times the collocate would occur in the window given smooth distribution
 * significance  -- the statistic [NOT PRESENT IF IT'S FREQ ONLY]
 * freq 		 -- the freq of that word or tag in the entire corpus (or subcorpus, etc)
 * text_id_count -- the number of texts in which the collocation occurs  
 */

function create_statistic_sql_query($calculation_options)
{
	global $Config;
	global $Corpus;
	
	/* The variables created by the "extract" call. */
	$dbname = NULL;
	$freq_table_to_use = NULL;
	$calc_stat = NULL;
	$att_for_calc = NULL;
	$calc_range_begin = NULL;
	$calc_range_end= NULL;
	$calc_minfreq_collocalone = NULL;
	$calc_minfreq_together = NULL;
	$tag_filter = NULL;
	$download_mode = NULL;
	$soloform = NULL;
	$begin_at = NULL;

	extract($calculation_options);

	/* abbreviate the name for nice-ness in this function */
	$freq_table = $freq_table_to_use;
	
	/* table-field-clause shorthand combos */
	
	/* the column in the db that is being collocated on */
	$item = "`$dbname`.`$att_for_calc`";
	
	$tag_clause = colloc_tagclause_from_filter($dbname, $att_for_calc, $Corpus->primary_annotation, $tag_filter);

	/* number to show on one page */
	$limit_string = ($download_mode ? '' : "LIMIT $begin_at, $Config->default_collocations_per_page");	


	/* the condition for including only the collocates within the window */
	if ($calc_range_begin == $calc_range_end)
		$range_condition = "dist = $calc_range_end";
	else
		$range_condition = "dist between $calc_range_begin and $calc_range_end";


	/* $sql_endclause -- a block at the end which is the same regardless of the statistic */
	if (empty($soloform))
	{
		/* the normal case */
		$sql_endclause = "where $item = $freq_table.`item`
			and $range_condition
			$tag_clause
			and $freq_table.`freq` >= $calc_minfreq_collocalone
			group by $item
			having observed >= $calc_minfreq_together
			order by significance desc
			$limit_string
			";
	}
	else
	{
		/* if we are getting the formula for a solo form */
		$soloform_sql = escape_sql($soloform);
		
		$sql_endclause = "where $item = $freq_table.`item`
			and $range_condition
			$tag_clause
			and $item = '$soloform_sql'
			group by $item
			";
	}



	/* shorthand variables for contingency table */
	$N   = calculate_total_basis($freq_table_to_use);
	$R1  = calculate_words_in_window($dbname, $calc_range_begin, $calc_range_end);
	$R2  = $N - $R1;
	$C1  = "($freq_table.freq)";
	$C2  = "($N - $C1)";
	$O11 = "1e0 * COUNT($item)";
	$O12 = "($R1 - $O11)";
	$O21 = "($C1 - $O11)";
	$O22 = "($R2 - $O21)";
	$E11 = "($R1 * $C1 / $N)";
	$E12 = "($R1 * $C2 / $N)";
	$E21 = "($R2 * $C1 / $N)";
	$E22 = "($R2 * $C2 / $N)";

	/*
	
	2-by-2 contingency table
	
	--------------------------------
	|        | Col 1 | Col 2 |  T  |
	--------------------------------
	| Row 1  | $O11  | $O12  | $R1 |
	|        | $E11  | $E12  |     |
	--------------------------------
	| Row 2  | $O21  | $O22  | $R2 |
	|        | $E21  | $E22  |     |
	--------------------------------
	| Totals | $C1   | $C2   | $N  |
	--------------------------------
	
	N   = total words in corpus (or the section)
	C1  = frequency of the collocate in the whole corpus
	C2  = frequency of words that aren't the collocate in the corpus
	R1  = total words in window
	R2  = total words outside of window
	O11 = how many of collocate there are in the window 
	O12 = how many words other than the collocate there are in the window (calculated from row total)
	O21 = how many of collocate there are outside the window
	O22 = how many words other than the collocate there are outside the window
	E11 = expected values (proportion of collocate that would belong in window if collocate were spread evenly)
	E12 =     "    "      (proportion of collocate that would belong outside window if collocate were spread evenly)
	E21 =     "    "      (proportion of other words that would belong in window if collocate were spread evenly)
	E22 =     "    "      (proportion of other words that would belong outside window if collocate were spread evenly)
	
	*/
	
	switch ($calc_stat)
	{
	case COLLSTAT_RANK_FREQ:		/* Rank by frequency */
		$sql = "select $item, $O11 as observed,  $E11 as expected,
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table 
			$sql_endclause";
		/* for rank by freq, we need to sort by something other than frequency */
		$sql = str_replace('order by significance', 'order by observed', $sql);
		break;
	
	case COLLSTAT_MI:		/* Mutual information */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			log2($O11 / $E11) as significance, $freq_table.freq,
			count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
		
	case COLLSTAT_MI3:		/* MI3 (Cubic mutual information) */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			3 * log2($O11) - log2($E11) as significance, $freq_table.freq, 
			count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table 
			$sql_endclause";
		break;
		
	case COLLSTAT_ZSCORE:		/* Z-score  (with Yates' continuity correction as of v3.0.8) */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			sign($O11 - $E11) * if(abs($O11 - $E11) > 0.5, abs($O11 - $E11) - 0.5, abs($O11 - $E11) / 2) / sqrt($E11) as significance, $freq_table.freq,
			count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
		
	case COLLSTAT_TSCORE:		/* T-score */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			($O11 - $E11) / sqrt($O11) as significance, $freq_table.freq,
			count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
		
	case COLLSTAT_LOG_LIKELIHOOD:		/* Log likelihood */
		$sql = "select $item, count($item) as observed, $E11 as expected,
			sign($O11 - $E11) * 2 * (
				IF($O11 > 0, $O11 * log($O11 / $E11), 0) +
				IF($O12 > 0, $O12 * log($O12 / $E12), 0) +
				IF($O21 > 0, $O21 * log($O21 / $E21), 0) +
				IF($O22 > 0, $O22 * log($O22 / $E22), 0)
			) as significance,
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
		
	case COLLSTAT_DICE:		/* Dice coefficient */
		/* this one uses extra variables, so get these first */
		$result = do_sql_query("SELECT COUNT(DISTINCT refnumber) from $dbname WHERE $range_condition");
		list($DICE_NODE_F) = mysqli_fetch_row($result);
		$P_COLL_NODE = "(COUNT(DISTINCT refnumber) / $DICE_NODE_F)";
		$P_NODE_COLL = "(COUNT($item) / ($freq_table.freq))";
		
		$sql = "select $item, count($item) as observed, $E11 as expected,
			2 / ((1 / $P_COLL_NODE) + (1 / $P_NODE_COLL)) as significance, 
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table 
			$sql_endclause";
		break;

	case COLLSTAT_LOG_RATIO:		/* Log Ratio filtered by log likelihood */
		/* 
		 * Before getting to the actual SQL, we need to add the LL filter to the end clause;
		 * use a base alpha of 0.05 and adjust to number of types we are testing. 
		 * Nothing is ever easy!
		 */
		/* make the LL cutoff globally available, for the infobox. Bugger me this code is ugly! */
		global $LL_CUTOFF;
		$n_comparisons = calculate_types_in_window($sql_endclause, array('order by significance desc', 
																		$limit_string,
																		"and $freq_table.freq >= $calc_minfreq_collocalone",
																		"having observed >= $calc_minfreq_together"
																		));
		$alpha = correct_alpha_for_familywise(0.05, $n_comparisons, 'Šidák');
		$LL_CUTOFF = calculate_LL_threshold($alpha);
		$sql_endclause = str_replace('having observed', "having LogLikelihood >= $LL_CUTOFF and observed", $sql_endclause);
		/* NB note that this means that the LL filter does not apply in colloc-solo mode. */
		
		$sql = "select $item, count($item) as observed, $E11 as expected,
			log2( ($O11 / $R1) / (IF($O21 > 0, $O21, 0.5) / $R2) ) as significance ,
			sign($O11 - $E11) * 2 * (
				IF($O11 > 0, $O11 * log($O11 / $E11), 0) +
				IF($O12 > 0, $O12 * log($O12 / $E12), 0) +
				IF($O21 > 0, $O21 * log($O21 / $E21), 0) +
				IF($O22 > 0, $O22 * log($O22 / $E22), 0)
			) as LogLikelihood,
			$freq_table.freq, count(distinct(text_id)) as text_id_count
			from $dbname, $freq_table
			$sql_endclause";
		break;
		
	case COLLSTAT_LR_CONSERVATIVE:		/* Log Ratio filtered by log likelihood */
		/* 
		 * We need the confidence interval.
		 */
		$n_comparisons = calculate_types_in_window($sql_endclause, array('order by significance desc', 
																		$limit_string,
																		"and $freq_table.freq >= $calc_minfreq_collocalone",
																		"having observed >= $calc_minfreq_together"
																		));
		$alpha = correct_alpha_for_familywise(0.05, $n_comparisons, 'Šidák');

		$Z_unit = calculate_Z_for_LR_confinterval($alpha);
		
		$fragment_RRF    = "(($O11 / $R1) / (IF($O21 > 0, $O21, 0.5) / $R2))";
		$fragment_CIhalf = "($Z_unit * SQRT( ($O12 / ($R1 * IF($O11 > 0, $O11, 0.5))) + ($O22 / ($R2 * IF($O21 > 0, $O21, 0.5))) ))";
		
		$sql = "select $item, count($item) as observed, $E11 as expected,
		/*	log2( ($O11 / $R1) / (IF($O21 > 0, $O21, 0.5) / $R2) ) as RawLogRatio, (not needed I think!*/
			log2( exp(log($fragment_RRF) - $fragment_CIhalf) ) as significance, /* lower CI of Log Ratio */
			`$freq_table`.freq, count(distinct(text_id)) as text_id_count
			from `$dbname`, `$freq_table`
			$sql_endclause";
		break;
		
	default:
		exiterror("Collocation script specified an unrecognised statistic (code no. $calc_stat) !");
	}

	return $sql;
}


/* next two functions support the "create statistic" function */


function colloc_tagclause_from_filter($dbname, $att_for_calc, $primary_annotation, $tag_filter)
{
	/* there may or may not be a primary_annotation filter; $tag_filter is from _GET, so check it */
	if (isset($tag_filter) && $tag_filter != false && $att_for_calc != $primary_annotation)
	{
		/* as of v2.11, tag restrictions are done with REGEXP, not = as the operator 
		 * if there are non-Word characters in the restriction; since tags usually
		 * are alphanumeric, defaulting to = may save procesing time.
		 * As with CQP, anchors are automatically added. */
		if (preg_match('/\W/', $tag_filter))
		{
			$tag_filter = regex_add_anchors($tag_filter);
			$tag_clause_operator = 'REGEXP';
		}
		else
			$tag_clause_operator = '=';
		
		/* tag filter is set and applies to a DIFFERENT attribute than the one being calculated */
		
		return "and $dbname.`$primary_annotation` $tag_clause_operator '"
			. escape_sql($tag_filter)
			. "' "
			;
	}
	else
		return '';
}





function calculate_total_basis($basis_table)
{
	global $Corpus;
	// TODO, not quite sure why this func has a cache... won't it only ever be called once?
	static $total_basis_cache;
	
	if (!isset($total_basis_cache[$basis_table]))
	{
		if (preg_match("/^freq_corpus_{$Corpus->name}_[\w\-]+$/",$basis_table))
			$total_basis_cache[$basis_table] = $Corpus->size_tokens;
		else
			$total_basis_cache[$basis_table] = get_sql_value('select sum(freq) from ' . escape_sql($basis_table));
//TODO the above could be avoided if this funciton had access to the freqtable_record, ft_size
	}
	
	return $total_basis_cache[$basis_table];
}





/**
 * Calculates the total number of word tokens in the collocation window
 * described by the paramters $calc_range_begin, $calc_range_end
 * for the specified $dbname.
 */
function calculate_words_in_window($dbname, $calc_range_begin, $calc_range_end)
{
	$sql = "SELECT COUNT(*) from $dbname";
	
	if ($calc_range_begin == $calc_range_end)
		$sql .= " where dist = $calc_range_end";
	else
		$sql .= " where dist between $calc_range_begin and $calc_range_end";
	
	/* note that MySQL 'BETWEEN' is inclusive of the limit-values */
	
//	$r = mysqli_fetch_row(do_sql_query($sql));

//	return (int)$r[0];
	return (int)get_sql_value($sql);
}



/**
 * Calculates the total number of word/annotation types in the collocation window
 * using a fragment of the main query (that designed as $sql_endclause).
 * 
 * Uses (some of the) same global values as the calling function.
 * 
 * Not to be called by any function other than create_statistic_sql_query()!
 */
function calculate_types_in_window($sql_endclause, $strings_to_remove_from_endclause = array() )
{
	global $dbname;
	global $freq_table_to_use;
	global $att_for_calc;
	
	$item = "$dbname.`$att_for_calc`";
	
	/* this is a dirty, dirty hack. The gods of modular programming will frown upon me. */
	foreach($strings_to_remove_from_endclause as $s)
		$sql_endclause = str_replace($s, '', $sql_endclause);
	/* note: we need to filter out based on the RANGE limits, but NOT based on the frequency cutoffs. */
	
	$sql = "select $item, count($item) as observed
			from $dbname, $freq_table_to_use
			$sql_endclause";
	
	return mysqli_num_rows(do_sql_query($sql));

//	global $dbname;
//	global $att_for_calc;
//	list($n) = mysqli_fetch_row(do_sql_query("select count(distinct($att_for_calc)) from $dbname"));
//	return $n;
}











// prob don't need this function - can use corpus_annotation_taglist()

function colloc_table_taglist($field, $dbname)
{
	/* shouldn't be necessary...  but hey */
	$field  = escape_sql($field);
	$dbname = escape_sql($dbname);
	
	/* this function WILL NOT RUN on word - the results would be huge & unwieldy */
	if ($field == 'word')
		return array();
	/* this does not block it running on p-atts other than word that are equally huge, but we can't head off every problem. */
	
	$sql = "select distinct(`$field`) from `$dbname` limit 1000";
	$result = do_sql_query($sql);
	
	$tags = array();
	
	while ($r = mysqli_fetch_row($result))
		$tags[] = $r[0];
	
	sort($tags);
	
	return $tags;
}













/**
 * 
 * @param string         $att_for_calc
 * @param int            $calc_stat
 * @param string         $att_desc
 * @param string         $basis_desc
 * @param string         $stat_desc
 * @param string         $description
 * @param mysqli_result  $result               SQL result object.
 */
function collocation_write_download(
	$att_for_calc, 
	$calc_stat, 
	$att_desc, 
	$basis_desc, 
	$stat_desc, 
	$description, 
	mysqli_result $result
	)
{
	global $User;
	$eol = $User->eol();

	$description = preg_replace('/&([lr]dquo|quot);/', '"', $description);
	$description = preg_replace('/<span .*>/', '', $description);

	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=collocation_list.txt");
	echo "$description$eol";
	echo "__________________$eol$eol";
	$sighead = ($calc_stat == 0 ? '' : "\t$stat_desc value");   // tODO user a stat constant here. 

	echo "No.\t$att_desc\tTotal no. in $basis_desc\tExpected collocate frequency\t"
		, "Observed collocate frequency\tIn no. of texts$sighead"
		, "$eol$eol"
		;


	for ($i = 1 ; $row = mysqli_fetch_assoc($result) ; $i++ )
	{
		/* adjust number formatting : expected -> 3dp, significance -> 4dp */
		if ( empty($row['significance']) )
			$row['significance'] = 'n/a';
		else
			$row['significance'] = round($row['significance'], 3);
		$row['expected'] = round($row['expected'], 3);

		$sig = ($calc_stat == 0 ? '' : "\t{$row['significance']}");

		echo "$i\t{$row[$att_for_calc]}\t{$row['freq']}\t{$row['expected']}\t{$row['observed']}";
		echo "\t{$row['text_id_count']}$sig$eol";
	}
}


