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
 * Script for keywords data output.
 *
 * This script emits nothing on stdout until the last minute, because it
 * can alternatively write a plaintext file as HTTP attachment. 
 */


// a very old todo: 
// TODO: the left join for "comp" function may be quite slow. It is worth doing a time-test on the db, mebbe.


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');


/* include function library files */
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');
require('../lib/xml-lib.php');
require('../lib/useracct-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/scope-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/rface.inc.php');
require('../lib/cqp.inc.php');


/* ------------ *
 * BEGIN SCRIPT *
 * ------------ */

/* declare global variables */
$Corpus = $User = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);


/*
 * =================================
 * Interface / output mode variables
 * ================================= 
 *
 * VAR   | $program... | $kw_method | $what_to_show  
 * 
 * VAL     _table      | comp       | allKey
 *
 *         ! _table    | key        | onlyPos
 *         
 *         _dl         | lock       | onlyNeg
 *         
 *         ! _dl
 *
 *  if $program_table is false = cloud; so, cloud, method/show must be 'key' / 'onlyPos'
 *  and $program_dl is not relevant
 *  
 *  otherwise, table mode permits can be 'comp'/'key' or 'lock';
 *  and if the method is 'key', $what_to_show becomes relevant. 
 *  And, of course, $program_dl matters.
 */

/* what type of stat do we want? the $kw_method tells us. */

if (isset($_GET['kwMethod']) && $_GET['kwMethod'] == 'comp' )
	$kw_method = 'comp';
else
	$kw_method = 'key'; /* which we may change to lock, below */


	
/* the program can now be either table (for a table of stats), or (grr) wordcloud. */

$kw_render = isset($_GET['kwRender']) ? $_GET['kwRender'] : 'table';

switch($kw_render)
{
case 'clGraphix':
	$program_table = false;
	$program_dl = false;
	$cloud_wmx_style = false;
	break;
case 'clWmatrix':
	$program_table = false;
	$program_dl = false;
	$cloud_wmx_style = true;
	break;
case 'table':
default:
	$kw_render = 'table';
	$program_table = true; 
	/* are we using a dl program, or not */
	$program_dl = ( isset($_GET['downloadMode']) && (bool)$_GET['downloadMode'] );
	break;
}



/* now, work out the string that will be used to limit the SQL queries below... 
 *
 * What section of the data are we looking at? 
 *    For wordcloud, top 100. 
 *    For table, base on per page / page no.
 *    For table download, all.
 */
if ($program_table)
{
	/* Table. if we want to download, it's easy. */
	if ($program_dl)
		$limit_string = ' ';
	else
	{
		/* we need the paginated table system */
		if (isset($_GET['pageNo']))
			$_GET['pageNo'] = $page_no = prepare_page_no($_GET['pageNo']);
		else
			$page_no = 1;
		
		$per_page = prepare_per_page(isset($_GET['pp']) ? $_GET['pp'] : NULL);   /* filters out any invalid options */
		/* note use of same variables as used in a concordance; we assume per-page always to be (potentially) present in the GET string. */
		
		$limit_string = ' LIMIT ' . ($page_no-1) * $per_page . ', ' . $per_page . ' ';
	}
}
else
{
	/* cloud: easy */
	$limit_string = ' LIMIT 100 ';
	$page_no = 0;
	$per_page = 0;
}



/* do we want all keywords, only positive, or only negative, or lockwords? */

if (isset($_GET['kwWhatToShow']) && in_array($_GET['kwWhatToShow'], array('allKey', 'onlyPos', 'onlyNeg', 'lock')))
	$what_to_show = $_GET['kwWhatToShow'];
else
	exiterror("The system could not detect whether you selected positive keywords, negative keywords, or lockwords.");

if ('lock' == $what_to_show)
	$kw_method = 'lock';


/* cloud mode: we want only the positive keywords, and we cannot do anything else. So: sanity check that that was what was requested. */

if (!$program_table)
{
	if ($kw_method != 'key')
		exiterror( "You cannot use wordcloud view for anything other than a key items display." );
	
	/* a bodge till theer is JS dynamic disabling of the "show what" radio */
	if('allKey' == $what_to_show)
		$what_to_show = 'onlyPos';
	
	if ($what_to_show != 'onlyPos')
		exiterror( "You cannot look at negative keywords or lockwords in wordcloud view." );
}



/*
 * ===================================
 * Procedural / statistical parameters
 * ===================================
 */


/* attribute to compare  (plus get its description) */

if (!isset($_GET['kwCompareAtt']) )
	$att_for_comp = 'word';
else
	$att_for_comp = $_GET['kwCompareAtt'];

$att_desc = list_corpus_annotations($Corpus->name);
$att_desc['word'] = 'Word';

/* if the script has been fed an attribute that doesn't exist for this corpus, failsafe to 'word' */
if (! array_key_exists($att_for_comp, $att_desc) )
	$att_for_comp = 'word';



/* minimum frequencies */

$minfreq = array();

if (!isset($_GET['kwMinFreq1']) )
	$minfreq[1] = 5;
else
	$minfreq[1] = (int)$_GET['kwMinFreq1'];	
if ($minfreq[1] < 1)
	$minfreq[1] = 1;

if (!isset($_GET['kwMinFreq2']) )
	$minfreq[2] = 5;
else
	$minfreq[2] = (int)$_GET['kwMinFreq2'];	
if ($minfreq[2] < 0)
	$minfreq[2] = 0;




/* statistic to use: check by keys of mapper of column headings */

$stat_sort_col_head = array (
	KEYSTAT_LOGLIKELIHOOD   => 'Log likelihood',
	KEYSTAT_LR_UNFILTERED   => 'Log Ratio',
	KEYSTAT_LR_WITH_LL      => 'Log Ratio',
	KEYSTAT_LR_WITH_CONFINT => 'Log Ratio',
	KEYSTAT_LR_CONSERVATIVE => 'Conservative LR',
	KEYSTAT_NONE_COMPARE    => 'DUMMY VALUE',
	'CI'=>'Conf interval',      /* misbegotten hack to get the right col header when we need to print the CI. */
	);

if (!isset($_GET['kwStatistic']) )
	exiterror("No statistic was specified!");

$statistic = (int)$_GET['kwStatistic'];

if (! array_key_exists($statistic, $stat_sort_col_head))
	exiterror("An invalid statistic was specified!");


/* override statistic if we are not in key/lock word mode */
if ($kw_method == 'comp')
{
	$statistic = KEYSTAT_NONE_COMPARE;
	
	/* in compare  mode, we also need ... */
	
	$empty = 'f2';
	if ($_GET['kwEmpty'] == 'f1')
		$empty = 'f1';
	$title_bar_index = (int)substr($empty, 1, 1);
	$title_bar_index_other = ($title_bar_index == 1 ? 2 : 1);
	
	/* in keywords/lockwords mode the above just go unused. */
}

/* if we are in lockwords mode, check we are using a compatible statistic */
if ($kw_method == 'lock')
{
	switch ($statistic)
	{
	case KEYSTAT_LOGLIKELIHOOD:
		exiterror('Lockword calculations cannot be performed with the log-likelihood statistic. '
			. 'Try Log Ratio (unfiltered or with Confidence Interval filter) instead.');
	case KEYSTAT_LR_WITH_LL:
		exiterror('Lockword calculations cannot be performed with a log-likelihood filter. '
			. 'Use unfiltered Log Ratio, or Log Ratio with Confidence Interval filter, instead.');
	}
}
// Longterm would-be-nice TODO : use JavaScript to disable selection of this pair of options in the initial page.
// and to grey controls out. EG, on select cloud, disable show what - becomes "onlyPos" automatically.



/* the significance threshold */

if ( !isset($_GET['kwAlpha']) )
	$_GET['kwAlpha'] = '0.05';

$alpha_as_string = preg_replace('/[^\.\d]/', '', $_GET['kwAlpha']);

$alpha = (float)$alpha_as_string;

if ($alpha > 1.0)
	$alpha = 1.0;

if ($alpha >= 1.0 && $statistic == KEYSTAT_LR_WITH_CONFINT)
	exiterror('You asked for Log Ratio with a Confidence Interval filter, but you did not specify the size of Confidence Interval...');
	// TODO use JavaScript in the form to check for this on the client side as well...

/* do we adjust the threshold? */
$familywise_adjust = (isset($_GET['kwFamilywiseCorrect']) && $_GET['kwFamilywiseCorrect']==='Y');








/* the two tables to compare: parse the parameter that specifies the freq tables to use. */

$subcorpus      = array();
$table_base     = array();
$table_desc     = array();
$table_foreign  = array();
$table_for_pass = array(); /* unparsed param, to render again in forms. */

if (isset($_GET['kwTable1']) )
	list($subcorpus[1], $table_base[1], $table_desc[1], $table_foreign[1], $table_for_pass[1]) = parse_keyword_table_parameter($_GET['kwTable1']);
else
	exiterror("No frequency list was specified (table 1)!");

if (isset($_GET['kwTable2']) )
	list($subcorpus[2], $table_base[2], $table_desc[2], $table_foreign[2], $table_for_pass[2]) = parse_keyword_table_parameter($_GET['kwTable2']);
else
	exiterror("No frequency list was specified (table 2)!");

if ($table_base[1] === false || $table_base[2] === false)
	exiterror("CQPweb could not interpret the tables you specified!");

/* check that the first table isn't the special flag ::remainder */
if ('::remainder' == $subcorpus[1])
	exiterror("It's not possible to specify ''rest of corpus'' for  frequency list (1)!");

/* check that the first table is a subcorpus if the 2nd is "remainder" */
if ('::remainder' == $subcorpus[2])
	if ( 's~' != substr($table_for_pass[1], 2, 2) )
		exiterror("Frequency list (1) MUST be a subcorpus if frequency list (2) is ''rest of corpus''!");


/* check we've got two DIFFERENT tables */
if ($table_base[1] == $table_base[2])
	exiterror("The two frequency lists you have chosen are identical!");

/* check that the first table isn't foreign */
if ($table_foreign[1] === true)
	exiterror("A foreign frequency list was specified for frequency list (1)!");

// TODO where is it checked whether the foreign table is allowed to be used for keywords????

/* get a string to put into linked queries with the subcorpus; also, touch subcorpus frequency lists */

$restrict_url_fragment = array();

foreach(array(1, 2) as $i)
{
	$restrict_url_fragment[$i] = '';
	if ('::entire_corpus' == $subcorpus[$i])
		/* this is the home corpus (or a foreign corpus), so no restrict needed; */
		/* if foreign, will be set to false later */
		;
	else if ('::remainder' == $subcorpus[$i])
		/* use '' (empty string) to signal "search in whole corpus" (in comp mode, see below); 
		 * otherwise use false to signal "rest of corpus, so no link" */
		$restrict_url_fragment[$i] = false;
	else
	{
		/* this is a subcorpus -- home or foreign, so a restrict is needed */
		$restrict_url_fragment[$i] = '&del=begin&t=~sc~'. $subcorpus[$i] . '&del=end';
		/* if foreign, will be set to false later */
		
		/* and we should touch it */
		touch_freqtable($table_base[$i]);
	}
}
/* and cos we already know the first table is NOT foreign, we only check # 2... */
if ($table_foreign[2])
	$restrict_url_fragment[2] = false;



/* special case: comp mode && ::remainder mode means both sides can just use the whole corpus as their restriction */
if ('comp' == $kw_method && in_array('::remainder', $subcorpus))
	$restrict_url_fragment[1] = $restrict_url_fragment[2] = '';







/*
 * ==============================================================================
 * Done with parameters. Now, start building bits and pieces for the calculation.
 * ==============================================================================
 */



/* create the full table names */

$table_name  = [
	1 => "{$table_base[1]}_$att_for_comp",
	2 => "{$table_base[2]}_$att_for_comp",
];

/* if the second table is foreign, check that a frequency table exists for the right attribute. */
if ($table_foreign[2])
	if (1 < mysqli_num_rows(do_sql_query("show tables like '{$table_name[2]}'")))
		exiterror("The corpus or subcorpus you selected for list (2) does not seem to have an annotation called &ldquo;$att_for_comp&rdquo;");



/* get the totals of tokens and types for each of the 2 tables */

$corpus_tokens = array();
$corpus_types  = array();

foreach (array(1, 2) as $i)
{
	if ('::remainder' == $subcorpus[$i])
		continue;

	$result = do_sql_query("select sum(freq) from {$table_name[$i]}");

	if (mysqli_num_rows($result) < 1)
		exiterror("sum(freq) not found in from {$table_name[$i]}, 0 rows returned from SQL DB.");
	list($corpus_tokens[$i]) = mysqli_fetch_row($result);
	if (is_null($corpus_tokens[$i]))
		exiterror("sum(freq) not found in from {$table_name[$i]}, null value returned from SQL DB.");

	list($corpus_types[$i]) = mysqli_fetch_row(do_sql_query("select count(*) from {$table_name[$i]}"));
}
if ('::remainder' == $subcorpus[2])
{
	$corpus_tokens[2] = $Corpus->size_tokens - $corpus_tokens[1];
	$corpus_types[2]  = $Corpus->size_types; 
	/* but note, this should never be used!! only types[1] is used for familywise correction. */ 
}


/* now we have total types, we can do this. */

$adjusted_alpha = correct_alpha_for_familywise($alpha, $corpus_types[1], $familywise_adjust ? 'Šidák' : false);




/* and now get the threshold or the Z value component of the Relative Risk conf interval measure */

if (KEYSTAT_LR_UNFILTERED == $statistic)
{
	/* No CI and a NON-FILTERING level of LL needed */
	$threshold = 0.0;
}
else if (KEYSTAT_LR_WITH_LL == $statistic || KEYSTAT_LOGLIKELIHOOD == $statistic)
{
	/* No CI needed but we need an LL threshold ... */
	$threshold = calculate_LL_threshold($adjusted_alpha);
}
else
{
	/* LR w / CI is only remaining option: we DO need conf intervals ... */
	$Z_unit = calculate_Z_for_LR_confinterval($adjusted_alpha);
}






/* assemble the main SQL query */



	/*

	Compare similar variable definitions in colloc-lib.php
		
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
	
	N   = total tokens in both (sub)corpora
	C1  = frequency of the item across both (sub)corpora
	C2  = frequency of items that aren't the item across both (sub)corpora 
	R1  = total tokens in Corpus1
	R2  = total tokens in Corpus2
	O11 = how many of item there are in Corpus1
	O12 = how many items that aren't the item there are in Corpus1
	O21 = how many of item there are in Corpus2
	O22 = how many items other than the item there are in Corpus2
	E11 = expected values (proportion of item that would belong in Corpus1 if item were spread evenly)
	E12 =     "    "      (proportion of item that would belong in Corpus2 if item were spread evenly)
	E21 =     "    "      (proportion of other items that would belong in Corpus1 if item were spread evenly)
	E22 =     "    "      (proportion of other items that would belong in Corpus2 if item were spread evenly)
	
	*/

$N   = $corpus_tokens[1] + $corpus_tokens[2];
$R1  = "{$corpus_tokens[1]}";
$R2  = "{$corpus_tokens[2]}";
$O11 = "(1e0 * IFNULL(`{$table_name[1]}`.freq, 0))";
if ('::remainder' != $subcorpus[2])
	$O21 = "(1e0 * IFNULL(`{$table_name[2]}`.freq, 0))";
else
	$O21 = "( (1e0 * IFNULL(`{$table_name[2]}`.freq, 0)) - $O11 )";
$O12 = "($R1 - $O11)";
$O22 = "($R2 - $O21)";
$C1  = "($O11 + $O21)";
$C2  = "($N - $C1)"; 
$E11 = "($R1 * $C1 / $N)";
$E12 = "($R1 * $C2 / $N)";
$E21 = "($R2 * $C1 / $N)";
$E22 = "($R2 * $C2 / $N)";

/* note override for $O21,  where we need to SUBTRACT to get the correct frequency from the corpus freq table. */


switch ($statistic)
{
case KEYSTAT_LOGLIKELIHOOD:

	$extrastat_present = false;
	switch($what_to_show)
	{
	case 'onlyPos':
		$show_only_clause = "and freq1 > E1";
		break;
	case 'onlyNeg':
		$show_only_clause = "and freq1 < E1";
		break;
	case 'allKey':
		$show_only_clause = '';
		break;
	}

	$order_by_clause = 'order by sortstat desc';


	$sql = "select 
		`{$table_name[1]}`.item as item,
		$O11 as freq1, 
		$O21 as freq2, 
		$E11 as E1, 
		$E21 as E2, 
		2 * ( IF($O11 > 0, $O11 * log($O11 / $E11), 0) 
			+ IF($O21 > 0, $O21 * log($O21 / $E21), 0)
			+ IF($O12 > 0, $O12 * log($O12 / $E12), 0)
			+ IF($O22 > 0, $O22 * log($O22 / $E22), 0)
			)
			as sortstat
		from {$table_name[1]}, {$table_name[2]}
		
		where {$table_name[1]}.item = {$table_name[2]}.item 
		and   $O11 >= {$minfreq[1]}
		and   $O21 >= {$minfreq[2]}
		
		having sortstat >= $threshold
		$show_only_clause
		$order_by_clause 
		$limit_string
		";
	$using = "using log-likelihood statistic, significance cut-off " 
		. preg_replace('/0+000\d+$/', '', sprintf('%.20f', (100.0 * $alpha))) 
		. "% \n(" . ($familywise_adjust ? 'adjusted ' : '') . "LL threshold = " . round($threshold, 2) . ");"
		;
	break;



case KEYSTAT_LR_WITH_LL:
case KEYSTAT_LR_UNFILTERED:
	
	/* Log Ratio unfiltered shows LL for info, so we use the same formula as for Log Ratio + LL filter, with minor tweaks */ 
	
	$extrastat_present = true;
	
	switch($what_to_show)
	{
	case 'onlyPos':
		$show_only_clause = "having (freq1 / $R1) > (freq2 / $R2)";
		$order_by_clause  = "order by sortstat desc";
		break;
	case 'onlyNeg':
		$show_only_clause = "having (freq1 / $R2) < (freq2 / $R1)";
		$order_by_clause  = "order by abs(sortstat) desc";
		break;
	case 'allKey':
		$show_only_clause = '';
		$order_by_clause  = 'order by abs(sortstat) desc';
		break;
	case 'lock':
		$show_only_clause = '';
		$order_by_clause  = 'order by abs(sortstat) asc';
		break;
	}
	/* ADD FILTER to the above.,*/
	if (KEYSTAT_LR_WITH_LL == $statistic)
	{
		/* KEY items mode only see above*/
		if (empty($show_only_clause))
			$show_only_clause = "having extrastat >= $threshold";
		else
			$show_only_clause = str_replace('having', "having extrastat >= $threshold and", $show_only_clause);
	}
	
	$sql = "select
		{$table_name[1]}.item as item,
		$O11 as freq1, 
		$O21 as freq2, 
		$E11 as E1, 
		$E21 as E2, 
		log2( ($O11 / $R1) / (IF($O21 > 0, $O21, 0.5) / $R2) ) as sortstat,
		2 * ( IF($O11 > 0, $O11 * log($O11 / $E11), 0) 
			+ IF($O21 > 0, $O21 * log($O21 / $E21), 0)
			+ IF($O12 > 0, $O12 * log($O12 / $E12), 0)
			+ IF($O22 > 0, $O22 * log($O22 / $E22), 0)
			)
			as extrastat

		from {$table_name[1]} left join {$table_name[2]}
		on {$table_name[1]}.item =  {$table_name[2]}.item

		where $O11 >= {$minfreq[1]}
		and   $O21 >= {$minfreq[2]}
		
		$show_only_clause
		$order_by_clause 
		$limit_string
		";
	if (KEYSTAT_LR_WITH_LL == $statistic)
		$using = 'using Log Ratio (with ' 
			. preg_replace('/0+000\d+$/', '', sprintf('%.20f',(100.0 * $alpha)))
			. '% significance filter, ' 
			. ($familywise_adjust ? 'adjusted ' : '') 
			. 'LL threshold = ' . round($threshold, 2) . ');'
			;
	else
		$using = 'using Log Ratio (no filter applied, LL shown for information);';
	break;


case KEYSTAT_LR_WITH_CONFINT:

	/* Log Ratio with CI filter is a bit different .... */

	$extrastat_present = true;
	
	switch($what_to_show)
	{
	/* this switch adds the filter; the CI's relationship to 0 also determines positive versus negative keyness */
	case 'onlyPos':
		$show_only_clause = "having CI_lower >= 0";
		$order_by_clause  = "order by sortstat desc";
		break;
	case 'onlyNeg':
		$show_only_clause = "having CI_upper <= 0";
		$order_by_clause  = "order by abs(sortstat) desc";
		break;
	case 'allKey':
		$show_only_clause = 'having CI_lower >= 0 or CI_upper <= 0';
		$order_by_clause  = 'order by abs(sortstat) desc';
		break;
	case 'lock':
		$show_only_clause = 'having CI_lower <= 0 and CI_upper >= 0';
		$order_by_clause  = 'order by abs(sortstat) asc';
		break;
	}
	
	/* to stop the main stat formula getting too complex ... */
	$fragment_RRF    = "(($O11 / $R1) / (IF($O21 > 0, $O21, 0.5) / $R2))";
	$fragment_CIhalf = "($Z_unit * SQRT( ($O12 / ($R1 * IF($O11 > 0, $O11, 0.5))) + ($O22 / ($R2 * IF($O21 > 0, $O21, 0.5))) ))";

	$sql = "select
		{$table_name[1]}.item as item,
		$O11 as freq1, 
		$O21 as freq2, 
		$E11 as E1, 
		$E21 as E2, 
		log2( $fragment_RRF ) as sortstat,
		log2( exp(log($fragment_RRF) - $fragment_CIhalf) ) as CI_lower,
		log2( exp(log($fragment_RRF) + $fragment_CIhalf) ) as CI_upper,
		'CONFINTERVAL' as extrastat

		from {$table_name[1]} left join {$table_name[2]}
		on {$table_name[1]}.item =  {$table_name[2]}.item

		where $O11 >= {$minfreq[1]}
		and   $O21 >= {$minfreq[2]}
		
		$show_only_clause
		$order_by_clause 
		$limit_string
		";

	$using = 'using Log Ratio (filtered by ' 
		. (100.0 * (1.0-$alpha))
		. '% confidence interval' 
		. ($familywise_adjust ? (', adjusted to '.(100.0 * (1.0-$adjusted_alpha)).'%') : '') 
		. ');'
		;
	break;
	
	
case KEYSTAT_LR_CONSERVATIVE:
	
	/* for this one, it's like using a CI, but use only need one side */
	
	$extrastat_present = false;
	
	switch($what_to_show)
	{
	/* this switch adds the filter; the CI's relationship to 0 also determines positive versus negative keyness */
	case 'onlyPos':
		$show_only_clause = "having sortstat >= 0";
		$order_by_clause  = "order by sortstat desc";
		break;
	case 'onlyNeg':
		$show_only_clause = "having sortstat <= 0";
		$order_by_clause  = "order by abs(sortstat) desc";
		break;
	case 'allKey':
		$show_only_clause = '';
		$order_by_clause  = 'order by abs(sortstat) desc';
		break;
	case 'lock':
		$show_only_clause = '';
		$order_by_clause  = 'order by abs(sortstat) asc';
		break;
	}
	
	$show_only_clause = '';
	
	
	/* to stop the main stat formula getting too complex ... */
	$fragment_RRF    = "(($O11 / $R1) / (IF($O21 > 0, $O21, 0.5) / $R2))";
	$fragment_CIhalf = "($Z_unit * SQRT( ($O12 / ($R1 * IF($O11 > 0, $O11, 0.5))) + ($O22 / ($R2 * IF($O21 > 0, $O21, 0.5))) ))";

	$sql = "select
		{$table_name[1]}.item as item,
		$O11 as freq1, 
		$O21 as freq2, 
		$E11 as E1, 
		$E21 as E2, 
		log2( exp(log($fragment_RRF) - $fragment_CIhalf) ) as sortstat

		from {$table_name[1]} left join {$table_name[2]}
		on {$table_name[1]}.item =  {$table_name[2]}.item

		where $O11 >= {$minfreq[1]}
		and   $O21 >= {$minfreq[2]}
		
		$show_only_clause
		$order_by_clause 
		$limit_string
		";

	$using = 'using Conservative Estimate of Log Ratio';
	break;



case KEYSTAT_NONE_COMPARE:
	
	/* we are in compare mode, not keyword mode */
	if ('f2' == $empty)
	{
		$a = 2;
		$b = 1;
	}
	else
	{
		$a = 1;
		$b = 2;
	}
	
	$where_test = ' is NULL ';
	if ('::remainder' == $subcorpus[$b])
		$where_test = " = {$table_name[$a]}.freq ";

	$sql = "SELECT {$table_name[$a]}.item, {$table_name[$a]}.freq as freq$a, 0 as freq$b 
		FROM  {$table_name[$a]} left join {$table_name[$b]} on {$table_name[$a]}.item = {$table_name[$b]}.item 
		where {$table_name[$b]}.freq $where_test
		order by {$table_name[$a]}.freq desc 
		$limit_string";
	break;

default:
	exiterror("Undefined statistic!");
}



/* THIS IS THE MAIN SQL QUERY THAT CALCULATES THE ITEMS TO DISPLAY */

$result = do_sql_query($sql);

$n_key_items = mysqli_num_rows($result);

if ($program_table && !$program_dl)
	$next_page_exists = ( $n_key_items == $per_page );


/* calculate the description line */
switch ($kw_method)
{
case 'key':
	$description = 'Key' . ($att_for_comp == 'word' ? '' : ' ') 
		. mb_strtolower($att_desc[$att_for_comp], 'UTF-8') . ' list for '
		. "{$table_desc[1]} compared to {$table_desc[2]};<br>"
		. str_replace("\n", ' <br>', $using)
		. ( ($minfreq[1] > 1 || $minfreq[2] > 0) 
				? "<br>items must have minimum frequency {$minfreq[1]} in list #1 and {$minfreq[2]} in list #2."
				: "<br>no frequency minima." )
		;
	break;
	
case 'lock':
	$description = 'Lock' . ($att_for_comp == 'word' ? '' : ' ') 
		. strtolower($att_desc[$att_for_comp]) . ' list for '
		. "{$table_desc[1]} compared to {$table_desc[2]};<br>"
		. str_replace("\n", ' <br>', $using)
		. ( ($minfreq[1] > 1 && $minfreq[2] > 0) 
				? "<br>items must have minimum frequency {$minfreq[1]} in list #1 and {$minfreq[2]} in list #2."
				: "<br>no frequency minima." )
		;
	break;
	
case 'comp':
	$description = 'Items which occur in  ' 
		. $table_desc[$title_bar_index]
		. ' but not in ' 
		. $table_desc[$title_bar_index_other]
		. ', sorted by frequency'
		;
	break;
	
default:
	/* it shouldn't be able to get to here, but if it does, */
	exiterror('Keywords function: unreachable mode was reached!!');
	break;
}

if ($program_table)
{
	switch ($what_to_show)
	{
	case 'onlyPos':
		$description .= '<br>Showing positively key items only.';
		break;
	case 'onlyNeg':
		$description .= '<br>Showing negatively key items only.';
		break;
	}
}
else
	$description = str_replace(' list for ', ' cloud for ', $description);

	
	
/* OK, we are all ready. It's now time to DO IT! 
 * First check for download, and call correct func. */

if ($program_dl)
{
	do_keywords_table_download($att_desc[$att_for_comp], $description, $result, $corpus_tokens);
}
else
{
	$js_insert = [ 'keywords' ];
	if (!($program_table || $cloud_wmx_style))
		$js_insert[] = 'wordcloud2';
	
	echo print_html_header($Corpus->title, $Config->css_path, $js_insert) ;

	?>
	<table class="concordtable fullwidth">
		<tr>
			<th id="caption-cell" class="concordtable"<?php if ($program_table) echo ' colspan="4"'; ?>>
				<?php echo $description, "\n"; ?>
			</th>
		</tr>
		<?php 
		$pass_params = array(
			/* these are the ones needed for clouds. */
			'kwTable1'            => $table_for_pass[1],
			'kwTable2'            => $table_for_pass[2],
			'kwCompareAtt'        => $att_for_comp,
			'kwStatistic'         => $statistic,
			'kwWhatToShow'        => $what_to_show,
			'kwAlpha'             => $alpha_as_string,
			'kwFamilywiseCorrect' => ($familywise_adjust ? 'Y' : 'N') ,
			'kwMinFreq1'          => $minfreq[1],
			'kwMinFreq2'          => $minfreq[2],
			);
		if ($program_table)
		{
			$pass_params['kwRender'] = $kw_render;
			$pass_params['kwMethod'] = $kw_method;
			if ('comp' == $kw_method)
				$pass_params['kwEmpty'] = $empty;

			echo print_keywords_control_row($page_no, $next_page_exists, $what_to_show, $pass_params), "\n";
		}
 		else
 			echo print_keycloud_control_row($cloud_wmx_style, $pass_params), "\n"; 
		?>
	</table>
	
	<table class="concordtable fullwidth">

	<?php

	if ($program_table)
	{
		/* TABLE DISPLAY HTML */
		
		?>

		<script>
		we_want_a_cloud = false; // TODO put this on the app meta node
		</script> 


		<tr>
			<th class="concordtable" rowspan="2">No.</th>
			<th class="concordtable" rowspan="2" width="25%"><?php echo $att_desc[$att_for_comp]; ?></th>
			<th class="concordtable" colspan="2">In <?php echo $table_desc['comp' == $kw_method && 'f2' == $empty ? 2 : 1]; ?>:</th>
			
			<?php 
			if ('comp' != $kw_method) 
			{
				?>
				<th class="concordtable" colspan="2">In <?php echo $table_desc[2]; ?>:</th>
				<th class="concordtable" rowspan="2">+/-</th>
				<th class="concordtable" rowspan="2"><?php echo $stat_sort_col_head[$statistic]; ?></th>
				<?php
				if ($extrastat_present)
					echo "\n\t\t\t\t<th class=\"concordtable\" rowspan=\"2\">" 
 						, $stat_sort_col_head[($statistic == KEYSTAT_LR_WITH_CONFINT?'CI':KEYSTAT_LOGLIKELIHOOD)] 
						, "</th>\n";
					/* extra stat is always log likelihood or confidence interval, though the "CI" entry in the array is a dirty hack */
			}
			?>
			
		</tr>
		<tr>
			<th class="concordtable">Frequency<br>(absolute)</th>
			<th class="concordtable">Frequency<br>(per mill)</th>
			
			<?php 
			
			if ('comp' != $kw_method) 
			{ 
				?>
				<th class="concordtable">Frequency<br>(absolute)</th>
				<th class="concordtable">Frequency<br>(per mill)</th>
				<?php 
			} 
			?>
			
		</tr>
	
		<?php
		
		/* this is the number SHOWN on the first line; the value of $i is (relatively speaking) 1 less than this */
		$begin_at = (($page_no - 1) * $per_page) + 1; 
	
		for ( $i = 0 ; $i < $n_key_items ; $i++ )
			echo print_keyword_table_row(mysqli_fetch_object($result), ($begin_at + $i), $att_for_comp, $restrict_url_fragment, $corpus_tokens)
	 			;
		if (1 > $n_key_items)
		{
			if ("comp" == $kw_method)
				echo '<tr><td class="concordgrey" colspan="4" align="center">'
					, '<p class="spacer">&nbsp;</p><p>No unique items detected!</p><p class="spacer">&nbsp;</p>'
 					, '</td></tr>'
 					, "\n"
 					;
			else
				echo '<td class="concordgrey" colspan="', 8 + ($extrastat_present ? 1 : 0), '" align="center">'
 					, '<p class="spacer">&nbsp;</p><p>There are no key items with a high enough score to display.</p><p class="spacer">&nbsp;</p>'
 					, '</td></tr>'
			 		, "\n"
			 		;
		}
	}
	else
	{
		/* CLOUD DISPLAY HTML */

		

		?>
		
		<script>
		/* this just transfers some global vars (would be better hung off the global app opbject */

		var we_want_a_cloud = true;

		var cloud_wmx_style = <?php echo $cloud_wmx_style ? " true " : " false " ; ?>;

		var cloud_caption_line_array = <?php 
					echo json_encode(
						explode("\n", 
							html_entity_decode(strip_tags(str_replace('<br>', "\n", $description)))
						));
					?>;
		</script>
		
		
	 	
	 	<tr>
			<td colspan="4" class="concordgeneral" align="center">
				
				<div 
					class="algCentre"
					id="keyItemCloudHolder"
					style="background-color: white; display: flex;  justify-content: center; align-items: center; margin: 20px; padding: 20px;"
				>

				<?php
				if ($cloud_wmx_style)
				{
					/* 
					 * ===========================
					 * CLOUD DISPLAY USING WMATRIX 
					 * ===========================
					 */
					
					
					?>
					
					<!-- NB. should width/height come from variables instead? this is a bit big for the  wmatrix-style cloud.-->
					
					<svg id="keyItemCloud" style="width:1200px;height:600px;">
						<foreignObject x="0" y="0" width="100%" height="100%">
							
							<!-- note we set the height of the div to 2 px less than that of the whole svg due to the border. -->
							<div xmlns="http://www.w3.org/1999/xhtml"
								id="cloudParaHolder"
								style="height: 598px; display: flex; flex-direction: column; justify-content: center; border: 1px solid;"
							>
	
								<!-- here's as good a place as any for this. -->
								<!-- <?php /* TODO actually <style> can't occur in <body>. Must be in the head. SO move to the CSS system. */ ?> -->
								<style>
									a.wordcloudQuery {
										/* 16px because that's median. the A elements will override. */
										font:  bold 16px Arial, Helvetica, sans-serif;
										color: blue;
									}
									a.wordcloudQuery:visited {  color: blue;  }		
									a.wordcloudQuery:hover   {  color: red;   }
								</style>
								
								<p style="margin:0px; padding: 0px; display: block; text-align:center;" class="wordcloudPara">
									<?php

									/* the SQL query orders by keyness, but for a WM-style cloud, we want 'em alphabetical... */
									$wmatrix_htmls = [];
									
									if (1 > $n_key_items)
										$wmatrix_htmls['a'] = '<a class="wordcloudQuery">There are no key items with a score high enough to display.</a>';
									else
									{
										$rank = 1;
										while ($o = mysqli_fetch_object($result))
											$wmatrix_htmls[$o->item] 
												= print_keycloud_wmatrix_item($o, $rank++, true, $att_for_comp, $restrict_url_fragment[1]);
										ksort($wmatrix_htmls, SORT_NATURAL|SORT_FLAG_CASE);
									}
									
									foreach($wmatrix_htmls as $html)
										echo "\n\t\t\t\t\t\t\t\t\t$html\n";
										
									?>
								</p>
							</div>
						</foreignObject>
					</svg>


					<?php
				}
				else
				{
					/* 
					 * ===========================
					 * CLOUD DISPLAY USING GRAPHIX 
					 * ===========================
					 */

					/* the output_type defines the scaling of input data to wordcloud2.js */
					$output_type = 'rank'; /* this can also be 'freq' or 'stat'. */
					
					/*
					 * rank can go in as-is. stat and freq require scaling with a linear factor & a power operation.
					 */

					if ('freq' == $output_type)
					{
						$root = function ($x) {return pow($x, 1/4);};
						// get top freq
						$max_f = 0;
						
						while ($o = mysqli_fetch_object($result))
							if ($max_f < $root($o->freq1))
								$max_f = $root($o->freq1);
						mysqli_data_seek($result, 0);	
						echo "\n<script>/* max  is $max_f */</script>\n";
					}
					if ('stat' == $output_type)
						$root = function ($x) {return pow($x, 1/2);};
					

					$items_for_cloud = array();
					$link_hash = array();
					$rank = 100;
					
					while ($o = mysqli_fetch_object($result))
					{
						if (100 == $rank)
						{
							switch ($output_type)
							{
							case 'freq':
								$scaler = 100.0 / $max_f;
								$rank--;
								break;
							case 'stat':
								$scaler = 100.0 / $root($o->sortstat);
								$rank--;
								break;
							default:
								$scaler = NULL;
								break;
							}
						}
						switch ($output_type)
						{
						case 'rank':
							$items_for_cloud[] = array($o->item, $rank--);
							break;

						case 'stat':
							$items_for_cloud[] = array($o->item, 4 * (int)round($scaler * $root($o->sortstat), 0)); 
							break;

						case 'freq':
							$items_for_cloud[] = array($o->item, 2 * (int)round($scaler * $root($o->freq1), 0)); 
							break;
						}
						$link_hash[$o->item] = get_keyword_query_url($o->item, $att_for_comp, $restrict_url_fragment[1]);
					}
					if ('freq' == $output_type)
						usort($items_for_cloud, function ($a, $b) { $x = $b[1]-$a[1] ; return (0 != $x ? ($x/abs($x)) : 0); } );

					?>
					
					<canvas id="keyItemCloudCanvas"           width="1200" height="900" style="display:none;" ></canvas>

					<canvas id="keyItemCloudCanvasMonochrome" width="1200" height="900" style="display:none;" ></canvas>
					
					<div    id="keyItemCloudDiv"    style="width:1200px; height:900px; display:block;"></div>
					
					
					<!-- data for the cloud goes here.  -->
					
					<!-- NB, scaler function for the wordcloud size numbers is <?php echo is_null($scaler) ? 'NULL' : $scaler; ?> -->
					
					<script>

					var wordcloudClickableItemLinks = <?php echo json_encode((object)$link_hash); ?>;

					var wordcloudDataObject = <?php echo json_encode($items_for_cloud); ?>;
					/* this just transfers some global vars (would be better hung off the global app opbject */

					
					<?php

					if (1 > $n_key_items)
						echo 'alert("There are no key items with a statistical score high enough to display: word cloud is empty.");';
					
					?>


					</script>
					
					<?php
				
				} /* endif graphical */
				?>

				</div>
			</td>
		</tr>
		
		<?php
		$statistic_str = array (
			KEYSTAT_NONE_COMPARE => 'DUMMY_VALUE',
			KEYSTAT_LOGLIKELIHOOD => 'LL',
			KEYSTAT_LR_UNFILTERED => 'LR_UN',
			KEYSTAT_LR_WITH_LL => 'LR_LL',
			KEYSTAT_LR_WITH_CONFINT => 'LR_CI',
			KEYSTAT_LR_CONSERVATIVE => 'ConsLR',
		);
		
		$dl_fn_basic   = "key-cloud-{$statistic_str[$statistic]}" . (isset($output_type) ? ('-'.$output_type) : '') . '.png' ;  
		$dl_fn_caption = str_replace('.', '-captioned.', $dl_fn_basic);
		
		$dl_fn_basic_mono   = str_replace('key-cloud-', 'key-cloud-mc-', $dl_fn_basic);
		$dl_fn_caption_mono = str_replace('.', '-captioned.', $dl_fn_basic_mono);
		
		?>
		
		<tr>
			<td class="concordgrey" align="center" width="25%">
				<p class="spacer">&nbsp;</p>
				<p>
					<a id="dl-img-caption"   class="menuItem" href="#" download="<?php echo $dl_fn_caption; ?>">
						[download <strong>colour</strong> image with caption]
					</a>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
			<td class="concordgrey" align="center" width="25%">
				<p class="spacer">&nbsp;</p>
				<p>
					<a id="dl-img-caption-mono"   class="menuItem" href="#" download="<?php echo $dl_fn_caption_mono; ?>">
						[download <strong>monochrome</strong> image with caption]
					</a> 
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
			<td class="concordgrey" align="center" width="25%">
				<p class="spacer">&nbsp;</p>
				<p>
					<a id="dl-img-nocaption" class="menuItem" href="#" download="<?php echo $dl_fn_basic; ?>">
						[download image <strong>colour</strong> without caption]
					</a>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
			<td class="concordgrey" align="center" width="25%">
				<p class="spacer">&nbsp;</p>
				<p>	
					<a id="dl-img-nocaption-mono" class="menuItem" href="#" download="<?php echo $dl_fn_basic_mono; ?>">
						[download <strong>monochrome</strong> image without caption]
					</a>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		
		<?php
		
		} /* endif cloud mode */
		
		?>

	</table>
	
	<?php
	
	echo print_html_footer('keywords');
	// TODO separate help for wordcloud??? or, just re-do keywords to cover the cloud?

} /* endif not download. */



cqpweb_shutdown_environment();



/* ------------- *
 * end of script *
 * ------------- */




/**
 * Builds the URL for a query of a given key word / key item.
 * Usable in different outputs.
 *
 * @param  string $keyword      Key item to create a search for.
 * @param  string $att          Attribute on which the item occurs (to be queried).
 * @param  string $restriction  The code for URL-format "restrictions" for the subcorpus to search in.
 * @return string               Containing a query URL.
 */
function get_keyword_query_url($keyword, $att, $restriction)
{
	global $Corpus;

	/* the "restriction" variable is set to false to indicate that it should not be linked (foreign or rest-of) */
	if (false === $restriction)
		return '';
	
	$target = CQP::escape_metacharacters($keyword);
	
	/* Note use of '%c' or not in link below depends on corpus-level settings */
	
	return 'concordance.php?theData=' . urlencode( "[$att=\"{$target}\"{$Corpus->cqp_query_default_flags}]" ) . $restriction . '&qmode=cqp';
}





/**
 * Print a single line of the keyword data display (returns HTML string for printing).
 * 
 * @param  stdClass $data           Database object for one row of the key-items query result.
 * @param  int      $line_number    Line number to print
 * @param  string   $att_for_comp   Comparision attribute (for links to queries).
 * @param  array    $restricts      The code for URL-format "restrictions" for the two (sub)corpora. At keys 1 and 2. 
 * @param  array    $corpus_size    Size of corpus (for calculating freq per million). Array of two ints at keys 1 and 2.
 * @return string                   An HTML string (TR element) that can be written to browser.
 */
function print_keyword_table_row($data, $line_number, $att_for_comp, $restricts, $corpus_size)
{
	/* the format of "data" is as follows
	object(stdClass)(6) {
	  ["item"]=>
	  ["freq1"]=>
	  ["freq2"]=>
	  ["E1"]=>
	  ["E2"]=>
	  ["sortstat"]=>
	  ["extrastat"]=>         (only in some modes)
	  ["CI_upper"]=>          (only when "extrastat" is set to string "CONFINTERVAL")
	  ["CI_lower"]=>          (ditto)
	unless mode is comparison in which case we only have item and freq1, freq2.
	*/
	
	$comp_mode = !isset($data->sortstat);  /* which is to say, if mode is keyword rather than comparison */
	

	if (!$comp_mode) 
	{
		/* td classes for this line */
		if ( $data->freq1 > $data->E1 )
		{
			/* positively key */
			$plusminus = '+';
			$leftstyle = 'concordgeneral';
			$rightstyle = 'concordgrey';
		}
		else
		{
			/* negatively key */
			$plusminus = '-';
			$leftstyle = 'concordgrey';
			$rightstyle = 'concordgeneral';
		}
	}
	else
		$leftstyle = $rightstyle = 'concordgeneral';
	
	/* links do not appear if restricts[1|2] == false */

	$link = array();
	$link[1] = ($restricts[1] !== false && $data->freq1 > 0 ? ' href="' . get_keyword_query_url($data->item, $att_for_comp, $restricts[1]) . '"' : '');
	$link[2] = ($restricts[2] !== false && $data->freq2 > 0 ? ' href="' . get_keyword_query_url($data->item, $att_for_comp, $restricts[2]) . '"' : '');
		
	
	
	if ($comp_mode)
	{
		$ix = $data->freq1 > 0 ? 1 : 2;
// 		$other_ix = $data->freq1 > 0 ? 2 : 1;
		$ixvar = "freq$ix";
// 		$other_ixvar = "freq$other_ix";
		
		$string = "\n\t<tr>\n\t\t<td class=\"concordgeneral\" align=\"center\">$line_number</td>" 
			. "\n\t\t<td class=\"$leftstyle\"><b>{$data->item}</b></td>"
			. "\n\t\t<td class=\"$leftstyle\"  align=\"center\"><a {$link[$ix]}>"
				. number_format((float)$data->$ixvar) 
			. '</a></td>'
			. "\n\t\t<td class=\"$leftstyle\"  align=\"center\">" 
				. number_format(1000000*($data->$ixvar)/$corpus_size[$ix] , 2)
			. "</td>"
			;
	}
	else
		$string = "\n\t<tr>\n\t\t<td class=\"concordgeneral\" align=\"center\">$line_number</td>" 
			. "\n\t\t<td class=\"$leftstyle\"><b>{$data->item}</b></td>"
			. "\n\t\t<td class=\"$leftstyle\"  align=\"center\"><a {$link[1]}>"
				. number_format((float)$data->freq1) 
			. '</a></td>'
			. "\n\t\t<td class=\"$leftstyle\"  align=\"center\">" 
				. number_format(1000000*($data->freq1)/$corpus_size[1] , 2)
			. "</td>"
			. "\n\t\t<td class=\"$rightstyle\" align=\"center\"><a {$link[2]}>" 
				. number_format((float)$data->freq2) 
			. '</a></td>'
			. "\n\t\t<td class=\"$rightstyle\" align=\"center\">" 
				. number_format(1000000*($data->freq2)/$corpus_size[2] , 2)
			. "</td>"
			;
			
	if (isset($plusminus))
		$string .= "\n\t\t<td class=\"concordgrey\" align=\"center\">$plusminus</td>" 
			. "\n\t\t<td class=\"concordgrey\" align=\"center\">" . round($data->sortstat, 2) . '</td>'
			;
	if (isset($data->extrastat))
		$string .= "\n\t\t<td class=\"concordgrey\" align=\"center\">" 
			. ( 'CONFINTERVAL' == $data->extrastat ? (round($data->CI_lower, 2).', '.round($data->CI_upper, 2)) : round($data->extrastat, 2) ) 
			. '</td>'
			;
	
	$string .= "\n\t</tr>\n";
	
	return $string;
}


/**
 * 
 * @param  stdClass $data                Database object for the present wordcloud item. 
 * @param  int      $rank                Positive integer indicating the rank order of this item (for sizing)
 * @param  bool     $include_a_link      (For if we are creating an image)
 * @param  string   $att_for_comp        For building the link.
 * @param  string   $restriction         For restricted queries, if needed.  Pass through to another func. 
 * @return string                        An HTML a-element containing a single wordcloud item.
 */
function print_keycloud_wmatrix_item($data, $rank, $include_a_link, $att_for_comp, $restriction)
{

	/* what to set sizer to depends on the rank of this cloud item. */ 
	if ($rank <= 16)
		$sizer = '38px';  /* +4 points of relative change from default*/
	else if ($rank <= 32)
		$sizer = '30px';  /* +3 */
	else if ($rank <= 48)
		$sizer = '24px';  /* +2 */
	else if ($rank <= 64)
		$sizer = '20px';  /* +1 */
	else if ($rank <= 80)
		$sizer = '16px';  /*  0 */
	else if ($rank <= 96)
		$sizer = '13px';  /* -1 */
	else
		$sizer = '10px';  /* -2 */
	/* rank divisors (groups of sixteen, then last 4 are tiny), taken over from Wmatrix. */
	/* actual font sizes in px calculated from Wmatrix.  NB: at some point, check how well this works on mob devices. */
	
	
	if ($include_a_link)
	{
		$addclass = " hasToolTip";
		$tooltip = 'data-tooltip="' 
			. 'Frequency: <strong>' . number_format($data->freq1) . '</strong>'
			. '<br>'
			. 'Score: <strong>' . number_format($data->sortstat, 3) . '</strong>' 
			.'"';
		$href = '';
		if (!empty($url = get_keyword_query_url($data->item, $att_for_comp, $restriction)))
			$href =  ' href="' . $url . '"';
	}
	else 
	{
		$addclass = '';
		$tooltip = '';
		$href = '';
	}

	return "<a id=\"wcLink$rank\"class=\"wordcloudQuery$addclass\" style=\"font-size:$sizer;\" $tooltip$href>" . escape_html($data->item) . '</a>' ;
}



/**
 * 
 * Generates a single line of keyword table download.
 * 
 * @param  stdClass  $data
 * @param  int       $line_number
 * @param  string    $eol
 * @param  array     $corpus_size
 * @return string
 */
function print_keyword_table_plainline($data, $line_number, $eol, $corpus_size)
{
	/* simpler version of above for plaintext mode	*/

	if (isset($data->sortstat)) /* which is to say, if mode is keyword rather than comparison */
		$plusminus = ($data->freq1 > $data->E1 ? '+' : '-');
	
	$string = "$line_number\t{$data->item}\t{$data->freq1}\t" 
		. number_format(1000000*($data->freq1)/$corpus_size[1] , 2, '.', '')
		;
	if (isset($plusminus))
		$string .= "\t{$data->freq2}\t" 
			. number_format(1000000*($data->freq2)/$corpus_size[2] , 2, '.', '') 
			. "\t$plusminus\t" 
			. round($data->sortstat, 2)
			;
	if (isset($data->extrastat))
		$string .= "\t" . ('CONFINTERVAL' == $data->extrastat ? (round($data->CI_lower, 2).', '.round($data->CI_upper, 2)) : round($data->extrastat, 2));
	
	$string .= $eol;
	
	return $string;
}




/**
 * This function does the download procedure for the keywords table. 
 * 
 * @param string    $att_desc
 * @param string    $description
 * @param resource  $result
 * @param int       $corpus_size
 */
function do_keywords_table_download($att_desc, $description, $result, $corpus_size)
{
	global $User;
	$eol = $User->eol();
	
	/* we know the descrption as passed will have some HTML in it... */
	$description = preg_replace('/&[lr]dquo;/', '"', $description);
	$description = str_replace("<br>", $eol, $description);

	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=key_item_list.txt");
	echo "$description$eol";
	echo "__________________$eol$eol";
	echo "Number\t$att_desc\tFreq";


	if (substr($description, 0, 3) == 'Key' || substr($description, 0, 4) == 'Lock')
	{
		echo " 1\tFreq 1 (per mill)\tFreq 2\tFreq 2 (per mill)\t+/-";
		if ( 'extrastat' == mysqli_fetch_field_direct($result, mysqli_num_fields($result)-1)->name )
			echo "\tStat 1.\tStat 2.";
		else
			echo "\tStat.";	
	}

	echo $eol, $eol;


	for ($i = 1; $o = mysqli_fetch_object($result) ; $i++ )
		echo print_keyword_table_plainline($o, $i, $eol, $corpus_size);
}



/**
 * Works out, and returns, a cluster of needed values for keywords
 * based on a single passed-in parameter.  
 * @param  string  $par    Parameter string that determines some freqtable for keywords.
 * @param  bool    $html   Whether HTML or plaintext is required for the description.
 * @return array           Array of values extracted. The returned values are:
 *                         0 => subcorpus integer id, or string "::entire_corpus". 
 *                         1 => base of freqtable table names.
 *                         2 => printable HTML desc of the corpus/subcorpus
 *                         (plain text if the html parameter is false).
 *                         3 => bool: "foreign": is the corpus/subcorpus the same as $Corpus, 
 *                         i.e. the corpus we are in right now?
 *                         4 => valid param string: ie, the first arg iff it parsed; 
 *                         otherwise, empty value 
 */
function parse_keyword_table_parameter($par, $html = true)
{
	global $User;
	global $Corpus;

	/* set the values that kick in if nothing else is found */
	$subcorpus = '';
	$base      = false;
	$desc      = '';
	$foreign   = false;

	/* this is a stopgap to allow links with the old flag, "__entire_corpus", to still work */
	if ('__entire_corpus' == $par)
		$par = '::entire_corpus';



	/*
	 * In the parameters being parsed,
	 *
	 * 1st char: l / f = local / foreign
	 *   followed by
	 * sc , uc, gc => system corpus, user corpus, granted user-corpus respectively
	 *   or
	 * Xs where X =  u, g, p => user-owned / granted / public subcorpus.
	 * 
	 * Corpora are represented by name; subcorpora, by ID.
	 */
	$hrx = CQPWEB_HANDLE_STRING_REGEX;


	if ('::entire_corpus' == $par)
	{
		$subcorpus = "::entire_corpus";
		$base = "freq_corpus_$Corpus"; //TODO 3.3
		$desc = ($html ? ("whole &ldquo;" . escape_html($Corpus->title). "&rdquo;") : ("whole \"" . $Corpus->title. "\"")) ;
	}

	else if ('::remainder' == $par)
	{
		$subcorpus = '::remainder';
		$base = "freq_corpus_$Corpus"; // TODO 3.3
		$desc = ($html ? ("the rest of &ldquo;" . escape_html($Corpus->title). "&rdquo;") : ("the rest of \"{$Corpus->title}\"")) ;
	}
	
	else
	{
		/* the nine possibilities are defined in the keywords form. */
		 
		switch (substr($par, 0, 3))
		{
		case 'lus':
		case 'lgs':
		case 'lps':
			if (0 < preg_match('/(l[ugp]s)~(\d+)/', $par, $m))
			{
				if (!($sc_record = Subcorpus::new_from_id($m[2])))
					exiterror("The subcorpus you selected could not be found on the system!");
				
				switch($m[1])
				{
					/* it's a subcorpus, owned by the user, in this corpus */
				case 'lus':
					if (!$sc_record->owned_by_user())
						exiterror("You cannot analyse keywords using that data, because you do not have permission to use it.");
					break;
					
					/* it's a subcorpus owned by someone else, but granted to the present user, in this corpus. */
				case 'lgs':
					// TODO this isn't implemented yet. func exists, but is not complete 
					if (!subcorpus_is_granted_to_user($sc_record->id, $User->id))
						exiterror("You cannot analyse keywords using that data, because you do not have permission to use it.");
					break;
					
					/* it's a subcorpus owned by someone else, but made public, in this corpus. */
				case 'lps':
					if (!$sc_record->get_freqtable_record()->public)
						exiterror("You cannot analyse keywords using that data, because you do not have permission to use it.");
					break;
					
				}
				
				$subcorpus = $sc_record->id;
				if (false === ($base = $sc_record->get_freqtable_base())) // TODO 3.3
					exiterror("The subcorpus you selected has no frequency list! Please compile the frequency list and try again.\n");
				$desc = 'subcorpus ' . ($html ? "&ldquo;{$sc_record->name}&rdquo;" : "\"{$sc_record->name}\"");
			}
			break;
			
		case 'fuc':
		case 'fgc':
		case 'fsc':
			$foreign = true;
			if (0 < preg_match("/(f[ugs]c)~$hrx\$/", $par, $m))
			{
				if (!($c = get_corpus_info($m[2])))
					exiterror("Corpus does not exist.");
				
				$your = '';
				
				switch($m[1])
				{
				/* foreign corpus freqlist (corpus owned by user) */
				case 'fuc':
					$your = 'your ';
					if (!user_owns_corpus($User->username, $c->name))
						exiterror("You cannot analyse keywords using that data, because you do not have permission to use it.");
					break;
				
				/* granted (via colleaguate) corpus freqlist */
				case 'fgc':
					if (!user_corpus_is_granted_to_user($c->id, $User->id))
						exiterror("You cannot analyse keywords using that data, because you do not have permission to use it.");
					break;
				
				/* foreign (accessed-by-privilege) corpus freqlist */
				case 'fsc':
					if (PRIVILEGE_TYPE_NO_PRIVILEGE >= max_user_privilege_level_for_corpus($User->username, $c->corpus))
						exiterror("You cannot analyse keywords using that data, because you do not have permission to use it.");
					break;
				}
				
				$subcorpus = "::entire_corpus";
				$base = "freq_corpus_{$c->corpus}";
				$desc = ($html ? ("{$your}corpus &ldquo;" . escape_html($c->title) . "&rdquo;") : "{$your}corpus \"{$c->title}\"") ;
			}
			break;

		case 'fus':
		case 'fgs':
		case 'fps':
			/* foreign subcorpus freqlist  */
			$foreign = true;
			if (0 < preg_match('/(f[upg]s)~(\d+)/', $par, $m))
			{
				if (!($sc_record = Subcorpus::new_from_id($m[2])))
					exiterror("The subcorpus you selected could not be found on the system!");
				
				if ('fgs' == $m[1])
				{
					if (!subcorpus_is_granted_to_user($c->id, $User->id))
						exiterror("You cannot analyse keywords using that data, because you do not have permission to use it.");
				}
				else
				{
					/* 1st test: for foreign user-owned subcorpus; 2nd test: for foreign public subcorpus */ 
					if ( ! ($sc_record->has_public_freqtable() || $sc_record->owned_by_user()) )
						exiterror("You cannot analyse keywords using that data, because you do not have permission to use it.");
				}
				
				$c = get_corpus_info($sc_record->corpus);
				
				$subcorpus = $sc_record->id;
				
				if (!($base = $sc_record->get_freqtable_base()))
					exiterror("The subcorpus you selected has no frequency list! Please compile the frequency list and try again.\n");

				$your = $sc_record->user == $User->username ? 'your ' : '';

				$desc = ($html 
							? "{$your}subcorpus &ldquo;{$sc_record->name}&rdquo; <br> from corpus &ldquo;".escape_html($c->title). "&rdquo;" 
							: "{$your}subcorpus \"{$sc_record->name}\" from corpus \"$c->title\""
						);
			}
			break;
			
		default:
			/* nothing has matched  -- default values at top of function get returned, plus an empty ("bad") par sting. */
			$par = '';
			break;
		}
	}

	return array($subcorpus, $base, $desc, $foreign, $par);
}


/**
 * Create HTML of control row for the keywords interface.
 * 
 * @param  bool    $cloud_wm_style   If true, display is currently a Wmatrix-stylee cloud.
 * @return string                    HTML of the control row.
 */
function print_keycloud_control_row($cloud_wm_style, $params_to_passthru = [])
{
	$hidden_inputs = print_hidden_inputs($params_to_passthru);
	
	return '
		<tr>
			<td class="concordgrey" align="center">
				<form action="redirect.php" method="get">
					<select name="redirect">
						<option value="newKeywords">New Keyword calculation</option>
						<option value="downloadKeywords" selected>Download keyword list</option>
						'
						. ( $cloud_wm_style 
								? '<option value="kwCloudGraphix">Switch to graphical wordcloud</option>' 
								: '<option value="kwCloudWmatrix">Switch to Wmatrix-style wordcloud</option>'
							)
							/* there is no "switch to table" option, because we've overridden assorted options, e.g. what to show. */
						. '
						<option value="newQuery">New Query</option>
					</select>
					&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="submit" value="Go!">
					<br>
					' . $hidden_inputs . '
				</form>
			</td>
		</tr>
		';
}






/**
 * Gets a string containing the control row for the keywords UI.
 * 
 * @param  int     $page_no            Number of the current page.
 * @param  bool    $next_page_exists   Whether the next page exists (to be linked to).
 * @param  string  $what_to_show       Which of the "show what?" codes is currently active?
 *                                     (onlyPos, onlyNeg, lock, allKey)
 * @return string                      HTML of the control row. 
 */
function print_keywords_control_row($page_no, $next_page_exists, $what_to_show, $params_to_passthru = [])
{
	/* this is a dirty hack way to access this value */
	if (isset($params_to_passthru['kwMethod']) && 'comp' == $params_to_passthru['kwMethod'])
		$middle_options = '';
	else 
		$middle_options =                   ( $what_to_show != 'allKey'  ? '<option value="showAll" >Show all keywords</option>'           : '' ) . "\n"
					. "					" . ( $what_to_show != 'onlyPos' ? '<option value="showPos" >Show only positive keywords</option>' : '' ) . "\n"
					. "					" . ( $what_to_show != 'onlyNeg' ? '<option value="showNeg" >Show only negative keywords</option>' : '' ) . "\n"
					. "					" . ( $what_to_show != 'lock'    ? '<option value="showLock">Show lockwords</option>'              : '' ) 
					. '
					<option value="kwCloudGraphix">Switch to graphical wordcloud</option>
					<option value="kwCloudWmatrix">Switch to Wmatrix-style wordcloud</option>';
					;
	return "\n\t<tr>"
			. print_braindead_navlinks("keywords.php?".print_url_params($params_to_passthru), $page_no, $next_page_exists)
			. '
		<td class="concordgrey">
			<form id="kwChangeDisplayForm" action="redirect.php" method="get">
				<select name="redirect">
					<option value="newKeywords">New Keyword calculation</option>
					<option value="downloadKeywords" selected>Download whole list</option>
					' . $middle_options . '
					<option value="newQuery">New Query</option>
				</select>
				' .  print_hidden_inputs($params_to_passthru, 'kwChangeDisplayForm'). '
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="submit" value="Go!">
			</form>
		</td>
	</tr>' 
			  . "\n";
}



/**
 * Gets a string containing the control row for the keywords UI.
 * 
 * @param  int     $page_no            Number of the current page.
 * @param  bool    $next_page_exists   Whether the next page exists (to be linked to).
 * @return string                      HTML of the control row. 
 */
function print_compare_control_row($page_no, $next_page_exists, $what_to_show, $params_to_passthru = [])
{	
	$nlinks = print_braindead_navlinks("keywords.php?".print_url_params($params_to_passthru), $page_no, $next_page_exists) . "\n";
	$hiddens = print_hidden_inputs($params_to_passthru, 'kwChangeDisplayForm');
	return <<<END_HTML
	
	<tr>
		$nlinks
		<td class="concordgrey">
			<form id="kwChangeDisplayForm" action="redirect.php" method="get">
				<select name="redirect">
					<option value="newKeywords">New Keyword calculation</option>
					<option value="downloadKeywords" selected>Download whole list</option>
					<option value="kwCloudGraphix">Switch to graphical wordcloud</option>
					<option value="kwCloudWmatrix">Switch to Wmatrix-style wordcloud</option>
					<option value="newQuery">New Query</option>

				</select>
				$hiddens
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="submit" value="Go!">
			</form>
		</td>
	</tr>

END_HTML;
}




