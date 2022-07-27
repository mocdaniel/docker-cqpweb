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
 * Receiver script for actions relating to multivariate analysis - 
 * mostly the management of feature matrices.
 * 
 * The actions are triggered through redirect. The script has no stub of its own.
 * 
 * The actions are controlled via switcyh and mostly work by sorting through
 * the "_GET" parameters, and then calling the underlying functions
 * (mostly in multivariate-lib).
 * 
 * When a case is complex, it has been hived off into a function within this file.
 * 
 */


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');


/* library files */
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/query-lib.php');
require('../lib/html-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/cache-lib.php');
require('../lib/scope-lib.php');
require('../lib/corpus-lib.php');
require('../lib/metadata-lib.php');
require('../lib/xml-lib.php');
require('../lib/multivariate-lib.php');
require('../lib/cqp.inc.php');


$Corpus = $User = NULL;


/* declare global variables */
$Corpus = $User =  NULL;

cqpweb_startup_environment();


$script_action = isset($_GET['multivariateAction']) ? $_GET['multivariateAction'] : false;

switch($script_action)
{
case 'buildFeatureMatrix':

	/*
	 * get all values from GET
	 */
	
	// TODO unit of analysis
	$analysis_unit = 'text';
	// not sure exactly how to represent these going forward. Never mind
	
	// TODO more label methods!
	if (!isset($_GET['labelMethod']))
		exiterror("The object labelling method was not specified.");
	
	//TODO temp: enforce use of text_id
	if ($_GET['labelMethod'] != 'id')
		exiterror("Currently, only the ID method of labelling data objects is available.");
	$label_method = 'id';
	($label_method);// todo this var is not used!!
	
	//TODO subdivs.
	switch(isset($_GET['corpusSubdiv']) ? $_GET['corpusSubdiv'] : '~~full~corpus~~')
	{
	case '~~full~corpus~~':
		$within_subcorpus = false;
		break;
	default:
		//TODO
		exiterror("Sorry, that's not suppported yet.");
		break;
	}
	
	
	/* collect an array of info on saved queries to use as features. */
	$feature_list = feature_defs_from_get();//var_dump($feature_list);
	
	/* check we have at least *some* features, plural */
	switch (count($feature_list))
	{
		case 0: exiterror("You haven't specified any features, so the matrix could not be built.");
		case 1: exiterror("You can't build a feature matrix with only one feature.");
	}
	
	/* get the save-name. Unlike a save query, this is not a handle: it's a description . */
	if ((! isset($_GET['matrixName'])) || 1 > strlen($matrix_savename = trim($_GET['matrixName'])))
		exiterror("No name specified for the new feature matrix! Please go back and try again."); 
	
	/* OK, we are done collecting variables... */
	$id = create_feature_matrix($matrix_savename, $User->username, $Corpus->name, $feature_list, $within_subcorpus, $analysis_unit);

	/* The next location is the individual view of the new matrix. */
	$next_location = "index.php?ui=analyseCorpus&showMatrix=$id";
	
	break;


case 'deleteFeatureMatrix':
	if (!isset($_GET['matrix']))
		exiterror("No matrix to delete was specified.");

	delete_feature_matrix((int) $_GET['matrix']);
	$next_location = "index.php?ui=analyseCorpus";
	break;


case 'downloadFeatureMatrix':
	if (!isset($_GET['matrix']))
		exiterror("No matrix to delete was specified.");

	if (false === ($fm = get_feature_matrix((int) $_GET['matrix'])))
		exiterror("The matrix you specified does not seem to exist.");

	/* send out a plain text download. */
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename={$fm->savename}.txt");
	/* following function call writes out to echo.... */
	print_feature_matrix_as_text_table($fm->id);
	break;


default:
	/* dodgy parameter: ERROR out. */
	exiterror("A badly-formed multivariate-analysis operation was requested!"); 
	break;
}


if (isset($next_location))
	set_next_absolute_location($next_location);



cqpweb_shutdown_environment();

exit(0);


/* ------------- *
 * END OF SCRIPT *
 * ------------- */





/*
 * =======================================
 * FUNCTIONS for running bits of the above
 * =======================================
 */


function feature_defs_from_get()
{
	global $User;
	global $Corpus;
	
	$feature_list = array();
	
	$sttrs_included = array();
	
	$labels_so_far = array();
	
	$saved_qnames = list_cached_queries($Corpus->name, $User->username);
	
	foreach ($_GET as $k => $v)
	{
		if (!preg_match('/(use\D+)(\d*)/', $k, $m))
			continue;

		/* i is the numeric index of this feature def. Numbers repeat between types. */
		$i = empty($m[2]) ? 0 : (int) $m[2];
		/* or, for the "specials" it might mean something else entirely... */
		
		switch($m[1])
		{
		case 'useQuery':
			
			if (!in_array($v, $saved_qnames))
				exiterror("Cannot create feature using the query you specified (query doesn't exist)");
			$record = QueryRecord::new_from_qname($v);

			$o = new MultivarFeatureDef(MultivarFeatureDef::TYPE_SAVEDQ);
			
			if (isset($_GET["q{$i}DR"]) && '100' == $_GET["q{$i}DR"])
				$o->set_discount_ratio( (float)$_GET["q{$i}DR"] );
			
			$o->add_qname($v);
			
			$labels_so_far[] = $o->label = ensure_valid_r_label($record->save_name, $labels_so_far);

			$o->source_info = 'Query = ';
			$o->source_info .= (empty($record->simple_query) ? $record->cqp_query : $record->simple_query);
			
			break;
			

		case 'useManip':
			
			if ('1' !== $v)
				exiterror("Could not create feature matrix: request format for feature is incorrect");
			if (! isset($_GET["manip{$i}op"]))
				exiterror("Could not create feature matrix: request format for feature is incorrect");
			if (!MultivarFeatureDef::is_real_type((int)$_GET["manip{$i}op"]))
				exiterror("Could not create feature matrix: invalid feature type.");
			
			$o = new MultivarFeatureDef(MultivarFeatureDef::TYPE_MATHMANIP);

			$o->set_op( (int)$_GET["manip{$i}op"] ); 
			
			if (isset($_GET["manip{$i}DR"]) && '100' == $_GET["manip{$i}DR"])
				$o->set_discount_ratio( (float)$_GET["manip{$i}DR"] );
			

			$qs = array();

			foreach(array(1,2) as $opnd)
			{
				$qs[$opnd] = cqpweb_handle_enforce($_GET["manip{$i}q{$opnd}"], 150);
				if (!in_array($qs[$opnd], $saved_qnames))
					exiterror("Cannot create feature using the query you specified (query doesn't exist)");
				$o->add_qname($qs[$opnd]);
			}
			
			$labels_so_far[] = $o->label = ensure_valid_r_label("MathsResult$i", $labels_so_far);
			
			$o->source_info = "(Q. {$qs[1]}) " . $o->print_op_as_word() . " (Q. {$qs[2]})";

			break;
			

		case 'useSpeshSttr':
			
			if ('1' !== $v)
				exiterror("Could not create feature matrix: request format for feature is incorrect");
			if (in_array($i, $sttrs_included))
				exiterror("You cannot include the same STTR measurement as a feature twice!");
			
			$o = new MultivarFeatureDef(MultivarFeatureDef::TYPE_STTR);
			
			/* for SpeshSttr, $i is not an idnetifying number, but the N of toklens to consider at a time;
			 * but divided by factor of 100 to avoid padding the URL....  */
			$basis = 100 * $i;
			
			$o->set_sttr_basis($basis);
			
			$sttrs_included[] = $basis;
			
			$labels_so_far[] = $o->label = ensure_valid_r_label("STTR_$basis", $labels_so_far);
			
			$o->source_info = "STTR (basis: $basis tokens)";

			break;

			
		case 'useSpeshAvgWdLen':
		case 'useSpeshAvgWdLenClean':
			
			if ('1' !== $v)
				exiterror("Could not create feature matrix: request format for feature is incorrect");
			
			$o = new MultivarFeatureDef(MultivarFeatureDef::TYPE_AVGWDLEN);
			
			$o->label = "AvgWordLength";
			
			$o->source_info = "Average word length (all forms)";
			
			if ('useSpeshAvgWdLenClean' == $m[1]) 
			{
				$o->set_avgwdlen_cleanup(true);
				$o->label = "AvgWordLengthClean";
				$o->source_info = "Average word length (with cleanup)";
			}
			
			$labels_so_far[] = $o->label = ensure_valid_r_label($o->label, $labels_so_far);
			
			break;
			

		default:
			exiterror("This default should not be reached (extraction of feature-type for matrix create)");
		}

		/* this makes sure there can be no duplicates of special features. */
		$feature_list[$o->label] = $o;
	}

	return $feature_list;
}


