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
 * This file contains the code for (a) doing and (b) displaying a corpus query as a concordance. 
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
require('../lib/ceql-lib.php');
require('../lib/ceqlparser.php');
require('../lib/annotation-lib.php');
require('../lib/corpus-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/metadata-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/cache-lib.php');
require('../lib/scope-lib.php');
require('../lib/db-lib.php');
require('../lib/useracct-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/plugin-lib.php');
require('../lib/xml-lib.php');
require('../lib/cqp.inc.php');



/* declare global variables */
$Corpus = $User = $Config = NULL;

cqpweb_startup_environment();



/* Load user macros! */
user_macro_loadall($User->username);






/* ------------------------------- *
 * initialise variables from $_GET *
 * and perform initial fiddling    *
 * ------------------------------- */



/* --------------- *
 * QUERY VARIABLES *
 * --------------- */

/* qname is the overriding variable --- is it set?
 * If it is, then we are accessing an already-existing query in order to 
 * display it (or rather, an approrpiate subset of it).
 * 
 * In this case, we don't need $qdata or anything like that.
 * 
 * If qname is not set, this is a new query (but in may be in cache, 
 * in which case we will get a query name from the database). 
 */
if (isset($_GET['qname']))
{
	$qname = safe_qname_from_get();
	/* we did some pre-checks before calling the safe-qname function to allow 
	 * the case where qname is absent to pass through, which normally would be Wrong */
	$incoming_qname_specified = true;
}
else
	$incoming_qname_specified = false;



/* Handling of theData && qmode && qstrategy. Also lookupType, if present. .
 *
 * "theData" is the contents of a query, either in CQP-syntax, or in
 * the CEQL simple-syntax formalism. The qmode parameter indicates which
 * of these it is. If a new query is to be performed, both these parameters
 * are indispensible.
 */
if (! $incoming_qname_specified )
{
	if (isset($_GET['theData']))
		$qdata = prepare_query_string($_GET['theData'], 
				isset($_GET['qstrategy'])  ? $_GET['qstrategy'] : false ,
				isset($_GET['lookupType']) ? $_GET['lookupType']: NULL
				);
	else
		exiterror('The content of the query was not specified!');

	if (isset($_GET['qmode']))
		$qmode = prepare_query_mode($_GET['qmode'], true);
	else
		exiterror('No query mode was specified!');
}
else
{
	/* theData & qmode are optional: set them to NULL if not present. 
	 * Note that they are ignored UNLESS qname turns out not to be cached after all */
	if (isset($_GET['theData']))
		$qdata = prepare_query_string($_GET['theData'], 
				isset($_GET['qstrategy'])  ? $_GET['qstrategy'] : false ,
				isset($_GET['lookupType']) ? $_GET['lookupType']: NULL
				);
	else
		$qdata = NULL;

	if (isset($_GET['qmode']))
		$qmode = prepare_query_mode($_GET['qmode'], false);
	else
		$qmode = NULL;
}
/* stop "theData", "qmode" and "qstrategy" from being passed to any other script */
unset($_GET['theData']);
unset($_GET['qmode']);
unset($_GET['qstrategy']);
/* $case_sensitive is only used if this is a new query */
//TODO the above will not be needed once we junk "url_print_"
$case_sensitive = ($qmode === 'sq_nocase' ? false : true);




$qscope = QueryScope::new_from_url($_SERVER['QUERY_STRING']);

/* did the user specify restrictions that exclude the entirety of the corpus? */
if (QueryScope::TYPE_EMPTY == $qscope->type)
	do_query_unsuccessful_page_and_exit('no_scope');

/*
 * Note that the query scope (subcorpus or restrictions) will be overwritten below 
 * if a named query is retrieved from cache.
 */


/*
 * Variable $postprocess describes all the postprocesses applied to a query; it 
 * always starts as an empty string, but it may be added to later by new-postprocess,
 * or an existing postprocessor string may be loaded from memory. 
 */
$postprocess = '';

/* load variables for new postprocesses */
$new_postprocess = false;
if (isset($_GET['newPostP']) && $_GET['newPostP'] !== '')
{
	$new_postprocess = new CQPwebPostprocess();
	if ( ! $new_postprocess->parsed_ok() )
		exiterror('The parameters for query postprocessing could not be loaded!');
	unset($_GET['pageNo']);//TODO, avopid use of 
	/* so that we know it will go to page 1 of the postprocessed query */
}




/* ******************* *
 * RENDERING VARIABLES *
 * ******************* */

/* In a multi-page concordance: which page to display. Note: parsed value overwrites GET. */
if (isset($_GET['pageNo']))
	$_GET['pageNo'] = $page_no = prepare_page_no($_GET['pageNo']);
else
	$page_no = 1;


/* In a multi-page concordance: how many hits per page. 
 * 
 * Note that &pp=count indicates that rather than show ANY 
 * hits, we should just display how many hits there were,
 * plus print out the command bar to allow additional analysis.
 * 
 * This is used to override the "program" variable (which presumes 
 * we are NOT just counting the hits).
 */
if (isset($_GET['pp']))
	$per_page_specified = $per_page = prepare_per_page($_GET['pp']);   /* filters out any invalid options */
else
	$per_page = $Config->default_per_page;

if ($per_page == 'count')
{
	$_GET['program'] = 'count_hits_then_cease';
	$per_page = $Config->default_per_page;
}

if ($per_page == 'all')
{
	$show_all_hits = true;
	$per_page = $Config->default_per_page;
}
else
	$show_all_hits = false;


/* viewMode can be either kwic or line. Allow no other values. */
if (isset($_GET['viewMode']))
	$view_mode = ('kwic' == $_GET['viewMode'] ? 'kwic' : 'line');
else
	$view_mode = ( $User->conc_kwicview ? 'kwic' : 'line' ) ;

/* there is an override... when translation is showing, only line mode is possible */
if ($Corpus->visualise_translate_in_concordance)
	$view_mode = 'line';

/* any possible aligned data in this corpus? */
$align_info = check_alignment_permissions(list_corpus_alignments($Corpus->name));

/* do we need to show the aligned region for each match? */
$show_align = false;
/* again note override: we do not allow BOTH translation viz AND parallel corpus viz */
if (isset($_GET['showAlign']) && !$Corpus->visualise_translate_in_concordance)
{
	/* the "don't show" value is '~~none' whhich will never be set in this array */
	if ( isset($align_info[$_GET['showAlign']]) )
	{
		$show_align = true;
		$alignment_att_to_show = $_GET['showAlign'];
// 		$alignment_att_description = $align_info[$_GET['showAlign']]; // not used. Get rid? Or use?
	}
}




/* the $program variable: filtered by a switch to admit only OK values;
 * note this is only used for the RENDERING of the query */
if(empty($_GET['program']))
	$program = 'search';
else
{
	switch($_GET['program'])
	{
// 	case 'collocation':	#doesn't seem to actually be a thing.
	case 'sort':
	case 'lookup':
	case 'categorise':
	case 'count_hits_then_cease':
		$program = $_GET['program'];
		break;
	default:
		$program = 'search';
		break;
	}
}




/* ----------------------------------------------------------------------------- *
 * This is the section which runs two separate tracks:                           *
 * a track for a query that is in cache and another track for a query that isn't *
 * ----------------------------------------------------------------------------- */

$start_time = microtime(true);



/* start by assuming that an old query can be dug up */
$run_new_query = false;
/* this will, or will not, be disproven later on     */

/* and set $n_of_solutions so it fails-safe to 0   */
$n_of_solutions = 0;

/* and flag a history insertion as NOT done (this variable will be set to true when it is) */
$history_inserted = false;



/* ------------------------------------------------------------------------ *
 * START OF CHUNK THAT CHECKS THE CACHE AND PREPARES THE QUERY IF NO RESULT *
 * ------------------------------------------------------------------------ */


if ( $incoming_qname_specified )
{
	/* TRACK FOR CACHED QUERY WITH QNAME IN THE GET REQUEST */

	/* check the cache */

	$cache_record = QueryRecord::new_from_qname($qname);

	if  ( $cache_record === false || 0 == ($n_of_solutions = $cache_record->hits()) )
	{
		/* if query not found in cache, JUMP TRACKS */
		unset($cache_record);
		$incoming_qname_specified = false;

		/* check the now-compulsory variables */
		if (empty($qdata))
			exiterror('The content of the query was not specified (and the named query was not in cache)!');

		if (empy($qmode))
			exiterror('No query mode was specified (and the named query was not in cache)!');
	}
	else
	{
		/* the cached file has been found and it DOESN'T contain 0 solutions */

		/* touch the query, updating its "time" to now */
		if ($cache_record->saved == CACHE_STATUS_UNSAVED)
			$cache_record->touch();
		
		/* take info from the cache record, and copy it to script variables */
		$qmode = $cache_record->query_mode;
		unset($_GET['qmode']);
		
		$cqp_query = $cache_record->cqp_query;
		$simple_query = $cache_record->simple_query;
		
		/* overwrite the previously-established $qscope if we have loaded a cached query */
		$qscope = $cache_record->qscope;
		
		$postprocess = $cache_record->postprocess;

		unset($qdata);
		
		/* next stop on this track is POSTPROCESS then DISPLAYING THE QUERY */
	}
}


/* this can't be an ELSE, because of the possibility of a track switch in preceding IF */
if ( ! $incoming_qname_specified )
{
	/* TRACK FOR A QUERY WHERE THE QNAME WAS NOT SPECIFIED */
	
	/* derive the $cqp_query and $simple_query variables and put the query into history */
	if ($qmode == 'cqp')
	{
		$simple_query = '';
		$cqp_query = $qdata;
	}
	else /* if this is a simple query */
	{
		/* keep a record of the simple query */
		$simple_query = $qdata;

		/* convert the simple query to a CQP query */
		if (false === ($cqp_query = process_simple_query($qdata, $case_sensitive, $ceql_errors)))
		{
			/* if conversion fails, add to history & then add syntax error code */
			
			if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
			{
				history_insert($Config->instance_name, '', $qscope->serialise(), $simple_query, ($case_sensitive ? 'sq_case' : 'sq_nocase'));
				history_update_hits($Config->instance_name, -1);
			}
			else
			{
				$history_record_id = history_insert(0, '', $qscope->serialise(), $simple_query, ($case_sensitive ? 'sq_case' : 'sq_nocase'));
				history_update_hits($history_record_id, -1);
			}
			
			/* and then call an error with the array of diagnostic strings from CEQL. */
			exiterror($ceql_errors);
		}
	}
	/* either way, $qdata is no longer needed */
	unset($qdata);
	
	/* we now have the query in CQP-syntax: the query can now go into history. */			
	if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
		history_insert($Config->instance_name, $cqp_query, $qscope->serialise(), $simple_query, $qmode);
	else
		$history_record_id = history_insert(0, $cqp_query, $qscope->serialise(), $simple_query, $qmode);
	$history_inserted = true;
	
	
	/* look in the cache for a query that matches this one on crucial parameters */

	$cache_record = QueryRecord::new_from_params($Corpus->name, $qmode, $cqp_query, $qscope);

	if  ( $cache_record === false || 0 == ($n_of_solutions = $cache_record->hits()) )
	{
		/* query is not found in cache at all - therefore, it needs to be run anew,
		 * and said new query inserted into cache with a brand-new qname.
		 * Queries with no solutions are also re-run.
		 */
		$run_new_query = true;
	}
	else
	{
		/* we have a query in the cache with the same cqp_query, subc., restr., & postp.! */

		/* take info from the cache record, and copy it to script variables */
		
		/* note: cqp_query (and restrictions/subcorpus) were what we matched on, so no need to copy */
		$qname = $cache_record->qname;

		/* the other two are slightly complicated */
		/* If the cache record already contains a simple_query, then it will be identical to
		 * simple_query, so no need to update that way. Rather, update the other way 
		 * (supply a simple query that generates the CQP query where none is available). */
		if (!empty($simple_query) && empty($cache_record->simple_query))
		{
			$cache_record->simple_query = $simple_query;
			$cache_record->save();
		}
		
		/* qmode shouldn't be updated, because this was, after all, a "new" query */
		/* so regardless of the qmode of the cached query, this instance has its own qmode */	
		
		/* touch the query, updating its "time" to now */
		if ($cache_record->saved == CACHE_STATUS_UNSAVED)
			$cache_record->touch();
		/* next stop on this track is POSTPROCESS then DISPLAYING THE QUERY */
	}
}


/* we now know if it's a new query, and can check whether to apply the user's auto-randomise function;
 * but this is only applied if no other postprocess has been asked for. */
if ($run_new_query && ! $new_postprocess && ! $User->conc_corpus_order)
{
	$_GET['newPostP'] = 'rand';
	$new_postprocess = new CQPwebPostprocess();
	/* no need to check whether it parsed correctly, cos we know it did! */
	$_GET['pageNo'] = $page_no = 1;
	/* so that we know the display will go to page 1 of the postprocessed query */
}

$cqp = get_global_cqp();






/* ---------------------------------------------------------- *
 * START OF MAIN CHUNK THAT RUNS THE QUERY AND GETS SOLUTIONS *
 * ---------------------------------------------------------- */
if ($run_new_query)
{
	/* if we are here, it is a brand new query -- not saved or owt like that. Ergo: */
	$qname = qname_unique($Config->instance_name);

	/* delete a cache file with this name if it exists */
	cqp_file_unlink($qname);
	
	/* set restrictions / activate subcorpus */
	$qscope->insert_to_cqp(); 

	/* this is the business end */
	$cqp->query("$qname = $cqp_query");

	/* now that we have the query, find out its size */
	
	if (0 == ($n_of_solutions = $cqp->querysize($qname)) )
	{
		/* no solutions: update the history, then send the user a message and exit */
		if ($history_inserted)
		{
			if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
				history_update_hits($Config->instance_name, 0);
			else
				history_update_hits($history_record_id, 0);
		}
		do_query_unsuccessful_page_and_exit();
	}
	
	/* otherwise, save the query file to disk, then create a cache record. */
	$cqp->execute("save $qname");

	$n_of_texts = count( $cqp->execute("group $qname match text_id") );
	/* note that this field in the record always refers to the ORIGINAL n of texts
	 * so, it is OK to set it here and not anywhere else (as postprocesses don't affect it) */

	/* put the query in the cache and get a cache-record object.*/
	$cache_record = QueryRecord::create(
			$qname, 
			$User->username, 
			$Corpus->name, 
			$qmode, 
			$simple_query, 
			$cqp_query, 
			$qscope,
			$n_of_solutions, 
			$n_of_texts
			);
	$cache_record->save();
}
else
{
	/* if ! $run_new_query, do nothing. The query has been retrieved from cache. */
}

/* set flag in history for query completed (IFF a record was created): overwrite default "run error" value of -3  */
if ($history_inserted)
// 	history_update_hits($Config->instance_name, $n_of_solutions);
{
	if (version_compare(CQPWEB_VERSION, '3.2.37', '<'))
		history_update_hits($Config->instance_name, $n_of_solutions);
	else
		history_update_hits($history_record_id, $n_of_solutions);
}

/* -------------------------------------------------------- *
 * END OF MAIN CHUNK THAT RUNS THE QUERY AND GETS SOLUTIONS *
 * -------------------------------------------------------- */


/* --------------------------------------------- *
 * End of section which runs two separate tracks *
 * --------------------------------------------- */



/* ----------------------- *
 * START OF POSTPROCESSING *
 * ----------------------- */

/* note that, for reasons of auto-thinning queries for users with restricted access, all this bit is inside a once-only loop */ 
while (true)
{
	if ($new_postprocess)
	{
		/* Add the new postprocess to the existing  postprocessor string, and look it up
		 * by parameter (using cqp_query, query_scope, postprocessor string)  */
		
		$postprocess = $new_postprocess->add_to_postprocess_string($postprocess);
		
		$check_cache_record = QueryRecord::new_from_params($Corpus->name, $qmode, $cqp_query, $qscope, $postprocess);

		
		/*	If it exists, the orig qname is replaced by this one */
		if ( false !== $check_cache_record)
		{
			/* dump the cache record retrieved or created above and use this one */
			$cache_record = $check_cache_record;
			$qname = $cache_record->qname;
			
			/* PLUS change variable settings, as we did before (see above) for original-query-matched */
			if (!empty($simple_query) && empty($cache_record->simple_query))
			{
				$cache_record->simple_query = $simple_query;
				$cache_record->save();
			}
			
			if ($cache_record->saved == CACHE_STATUS_UNSAVED)
				$cache_record->touch();
		}
		/* If it doesn't exist, the postprocess is applied to the qname (ergo the qname is replaced) */
		else
		{
			/* do_postprocess: returns false if the postprocess did not work. */
			if ( ! $cache_record->do_postprocess($new_postprocess))
				do_query_unsuccessful_page_and_exit('empty_postproc');

			/* calling the above method re-sets cr->postprocess and cr->hits_left etc.
			 * in the new query that is created; also touches the time, caches, and sets the new query to unsaved. */
			$qname = $cache_record->qname;

			/* and, because this means we are dealing with a query new-created in cache... */
			$run_new_query = true;
			/* so that it won't say the answer was retrieved from cache in the heading */
		}
	} /* endif $new_postprocess */
	
	/* get the highlight-positions table */
	$highlight_show_tag = false; /* which the next call *may* change to true */
	$highlight_positions_array = $cache_record->get_highlight_position_table($highlight_show_tag);
	
	/* even if tags are to be shown, don't do so if no primary annotation is specified, or if we are lgossing the text */
	$highlight_show_tag = ( $highlight_show_tag &&  !empty($Corpus->primary_annotation)  && !$Corpus->visualise_gloss_in_concordance );
	
	
	/* --------------------- *
	 * END OF POSTPROCESSING *
	 * --------------------- */
	
	
	$time_taken = round(microtime(true) - $start_time, 3);
	
	
	
	/* for safety, put the new qname into _GET; if a function looks there, it'll find the right qname */
	$_GET['qname'] = $qname;
	/* this is the qname of the cached query which the rest of the script will render */
	
	
	
	/* whatever happened above, $n_of_solutions contains the number of solutions in the original query.
	 * BUT a postprocess can reduce the number of solutions that get rendered and thus the number of pages.
	 * $n_of_solutions_final == the number of solutions all AFTER postprocessing.
	 */
	
	$n_of_solutions_final = $cache_record->hits();

	/* we can now check if there are too many hits in the query for a restricted access query! 
	 * if there are, we need to set up for a *reduce the query via random thin* postprocess, then
	 * use a "continue" in this while-true-break to repeat the application of postprocessing. */
	if (PRIVILEGE_TYPE_CORPUS_RESTRICTED >= $Corpus->access_level)
	{
		/* we only need to look at the section searched initially, 
		 * because we don't care about subsequent distribution-postproc reductions. */
		$restrict_access_max = max_hits_when_restricted($cache_record->get_tokens_searched_initially());

		if ($n_of_solutions_final > $restrict_access_max)
		{
			/* we DO need to thin down the concordance result to a smaller n of hits! 
			 * To do this: set new values to trigger a THIN of the query. */
			$_GET['newPostP'] = 'thin';
			$_GET['newPostP_thinTo'] = $restrict_access_max;
			$_GET['newPostP_thinHitsBefore'] = $n_of_solutions_final;
			$_GET['newPostP_thinReallyRandom'] = 0; /* we use REPRODUCIBLE thin so what users get to see is deterministic. */
			/* We do not need to remove any existing newPostP parameters: they were all cleared out 
			 * the last time the CQPwebPostprocess object was constructed (or, there were none to begin 
			 * with && the CQPwebPostprocess constructor has never been called).  */
			$new_postprocess = new CQPwebPostprocess();
			$_GET['pageNo'] = $page_no = 1;
			/* so that we know the display will go to page 1 of the postprocessed query */
			continue;
		}
	}
	
	/* otherwise (or, AFTER the re-loop) we just proceed to break out of the while-true-break "loop" and carry on ..... */
	break;
}



/* so we can work out how many pages there are (also == the # of the last page) */
if ($show_all_hits && 0 < $n_of_solutions_final)
	$per_page = $n_of_solutions_final;
	/* which will make the next statement set $n_of_pages to 1 (unless there are zero hits) */

$n_of_pages = (int)($n_of_solutions_final / $per_page) + (($n_of_solutions_final % $per_page) > 0 ? 1 : 0 );

/* make sure current page number is within feasible scope */
if ($page_no > $n_of_pages)
	$_GET['pageNo'] = $page_no = $n_of_pages;







/* ----------------------- *
 * DISPLAY THE CONCORDANCE *
 * ----------------------- */


/* if program is word-lookup, we don't display here - we go straight to freqlist. */
if ($program == 'lookup')
{
	$showtype = ($_GET['lookupShowWithTags'] == 0 ? 'words' : 'both');
	$per_page_for_breakdown = isset($per_page_specified) ? ('&pp='.$per_page_specified) : ''; 
	header("Location: breakdown.php?qname=$qname&concBreakdownOf=$showtype$per_page_for_breakdown");
	cqpweb_shutdown_environment();
	exit();
}




/* begin HTML page.... */

/* list of JS files: any specified extra code files, plus the standard categorise func. */
$js_scripts = extra_code_files_for_concord_header('conc', 'js');
if ($program == 'categorise')
	$js_scripts[] = 'categorise';

echo print_html_header(		$Corpus->title . " -- CQPweb Concordance", 
							$Config->css_path, 
							$js_scripts, 
							extra_code_files_for_concord_header('conc', 'css')
						);

/* print table headings && control lines */
?>

<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable" colspan="<?php echo (!empty($align_info) && $program != 'count_hits_then_cease') ? 9 : 8 ; ?>"> 
			<?php 
			echo  $cache_record->print_solution_heading()
				, format_time_string($time_taken, $run_new_query)
				, "\n"
 				; 
			?>
		</th>
	</tr>

<?php

$control_row = print_control_row(
					$cache_record,
					array_merge(
						array(
							'program'=>$program, 
							'page_no'=>$page_no, 
							'per_page'=>$per_page, 
							'num_of_pages'=>$n_of_pages, 
							'view_mode'=>$view_mode, 
							'align_info' => $align_info
						), 
						($show_align ? ['alignment_att'=> $alignment_att_to_show] : [])
					)
				);

if ($program == "sort")
{
	/* if the query being displayed is sorted, then we need to put the sort position
	 * into the control row, and we also need a second control row for the sort. */
	$sort_pos_recall = 0;
	$sort_control_row = print_sort_control($cache_record->qname, $cache_record->postprocess, $sort_pos_recall);
	echo add_sortposition_to_control_row($control_row, $sort_pos_recall)
		, $sort_control_row
		;
}
else
	echo $control_row;


/* having done the control row, it is time to exit if we are in count-hits mode */
if ($program == 'count_hits_then_cease')
{
	echo '</table>', "\n", print_html_footer('concordance');
	cqpweb_shutdown_environment();
	exit(0);
}



/* set up CQP options for the concordance display */
$cqp->execute("set Context {$Corpus->conc_scope} " . ($Corpus->conc_scope_is_based_on_s ? $Corpus->conc_s_attribute : 'words'));

/* what p-attributes to show? (annotations) */
if ($Corpus->visualise_gloss_in_concordance)
	$cqp->execute("show +word +{$Corpus->visualise_gloss_annotation} ");
else
	$cqp->execute('show +word ' . (empty($Corpus->primary_annotation) ? '' : "+{$Corpus->primary_annotation} "));
	/* note that $Corpus->primary_annotation should only be empty in an unannotated corpus. */

/* what inline s-attributes to show? (xml elements) */
$xml_tags_to_show = xml_visualisation_s_atts_to_show('conc');
if ( ! empty($xml_tags_to_show) )
	$cqp->execute('show +' . implode(' +', $xml_tags_to_show));

/* what a-attributes to show? (parallel lines) */
if ($show_align)
	$cqp->execute('show +' . $alignment_att_to_show);

/* what corpus location attributes to show? */
$cqp->execute('set PrintStructures "' 
				// TODO. Will this work along with XML visualisation? Should it be one or the other?
				// TODO. does it work along with position labels???
				. ($Corpus->visualise_translate_in_concordance ? "{$Corpus->visualise_translate_s_att} " : '') 
				. 'text_id'
				. ($Corpus->visualise_position_labels ? " {$Corpus->visualise_position_label_attribute}" : '')
				. '"'
				);

$cqp->execute("set LeftKWICDelim '--%%%--'");
$cqp->execute("set RightKWICDelim '--%%%--'");




/* what number does the concordance start and end at? 
 * conc_ = numbers that are shown
 * batch_ = numbers for CQP, which are one less */
$conc_start = (($page_no - 1) * $per_page) + 1; 
$conc_end   = $conc_start + $per_page - 1;
if ($conc_end > $n_of_solutions_final)
	$conc_end = $n_of_solutions_final;

$batch_start = $conc_start - 1;
$batch_end   = $conc_end - 1;

/* get an array containing the lines of the query to show this time */
$kwic = $cqp->execute("cat $qname $batch_start $batch_end");

/* get a table of corpus positions */
// $table = $cqp->dump($qname, $batch_start, $batch_end);
// This is a match / matchend / target / anchor table, formerly used (in BNCweb) for something to do with rendering but not used in CQPweb.
// it might return if we ever do anything with the target/keyword??
// MAYBE: ask if  a query has target/keyword; if it does, retrieve, and add a special css class to the words at the specified cpos offsets?

/* n = number of concordances we have to display in this run of the script (may be less than $per_page) */
$n_key_items = count($kwic);
/* NOTE: if we have requested parallel corpus display, $n is 2 x the number of conc lines, and every other array entry is an align-line! */


?>

</table>

<?php

if ($program == 'categorise')
{
	echo '<form id="mainCategoriseInputForm" action="redirect.php" method="get">'
		, '<input type="hidden" name="redirect" value="categorise-do">'
		, ($show_align ? '<input type="hidden" name="showAlign" value="' . $alignment_att_to_show . '">' : '')
		, '<input type="hidden" name="pageNo" value="' , $page_no , '">'
		, '<input type="hidden" name="qname" value="' , $qname , '">'
		, "\n"
		;

	/* and note, in this case we will need info on categories for the drop-down controls */
	$list_of_categories = catquery_list_categories($qname);
	$category_table = catquery_get_categorisation_table($qname, $conc_start, $conc_start+($show_align?$n_key_items/2:$n_key_items)-1);
}


?>

<!-- MAIN CONCORDANCE TABLE -->
<table class="concordtable fullwidth">

<?php


/* column headings */
echo '<tr>'
	, '<th class="concordtable">No.</th><th class="concordtable">Text</th><th class="concordtable"'
	, ( $view_mode == 'kwic' ? ' colspan="3"' : '' )
	, ">Solution $conc_start to $conc_end &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Page $page_no / $n_of_pages</th>"
	, ($program == 'categorise' ? '<th class="concordtable">Category</th>' : '')
	, "</tr>\n\n"
	;





/* --------------------------- *
 * concordance line print loop *
 * --------------------------- */

/* display parameter hash for print_concordance_line */
$display_params = array(
		'qname' => $qname,
		'view_mode' => $view_mode,
		'highlight_show_tag' => $highlight_show_tag,
		'line_number' => $conc_start-1,
		'show_align' => $show_align
);
if ($show_align)
	$display_params['alignment_att_to_show'] = $alignment_att_to_show;

for ( $i = 0, $highlight_check = ($highlight_positions_array !== false) ; $i < $n_key_items ; $i++ )
{
	/* set the display parameters specific to this iteration of the loop */
	$display_params['line_number'] = ($show_align ? $conc_start + ($i / 2) : $conc_start + $i);
	if ($highlight_check)
		$display_params['highlight_position'] = (int)$highlight_positions_array[$display_params['line_number'] - 1] ;

	$line = print_concordance_line($kwic[$i], $display_params);

	$categorise_column = '';
	if ($program == 'categorise') 
	{
		/* The index into the category table is the number displayed (we set an index var for brevity)
		 * (note the category table uses the same refnumbers as the line-labels, ie, they are 1-based not 0-based.)
		 */
		$cat_ix = $display_params['line_number'];
		/* look up what category this line has, and then build a box for it */
		$categorise_column = '<td align="center" class="concordgeneral"' . ($show_align ? ' rowspan="2"' : '') . '>';
		$categorise_column .= '<select class="category_chooser" name="cat_' . $cat_ix . '">';

		if ($category_table[$cat_ix] === NULL)
			$categorise_column .= '<option select> </option>';

		foreach($list_of_categories as $thiscat)
		{
			$select =  ($category_table[$cat_ix] == $thiscat) ? ' selected' : '' ; 
			$categorise_column .= "<option$select>$thiscat</option>";
		}

		$categorise_column .= '</select></td>';
	}

	echo "\n<tr>", $line, $categorise_column, "</tr>\n";

	/* print an extra row iff we have parallel corpus data from an alignment attribute */
	if ($show_align)
		echo "\n<tr>", print_aligned_line($kwic[++$i], $display_params), "</tr>\n";
	/* NOTE the extra increment to get to the next line because in this case the array contains twice as many lines (see above) */
}
/* end of concordance line print loop */


/* the categorise control row */
if ($program == 'categorise')
	echo print_categorise_control($view_mode);

/* finish off table */
?>

</table>

<?php
if ($program == 'categorise')
	echo "</form>\n";


/* show the control row again at the bottom if there are more than 15 lines on screen */
if ($n_of_solutions_final > 15 && $per_page > 15)
	echo "\n<table class=\"concordtable fullwidth\">\n", $control_row, "\n</table>\n";


/* print out the hidden forms for the menu */
// this style is here intermediately. it will go in 0system.
?>
<style>
.concordance-popup { display: none; }
</style>

<?php

do_conc_popup_colloc_control($cache_record);

do_conc_popup_thin_control($cache_record);



/* Based on the program, vary the helplink. */
if ($program == 'sort' || $program == 'categorise')
	$helplink = $program;
else
	$helplink = 'concordance';

echo print_html_footer($helplink);

disconnect_browser_and_continue();

/* clear out old stuff from the query cache (left till here to increase speed for user) */
delete_cache_overflow();

/* and update the last restrictions (ditto) */
$qscope->commit_last_restriction();

cqpweb_shutdown_environment();


/* and we're done! */

