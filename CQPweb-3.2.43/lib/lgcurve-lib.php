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
 * @file This file contains functions for creating and manipulating lexical growth curves.
 */

/*
 * CLOSURE CURVES
 * ==============
 * 
 * (notes here still v rough)
 * 
 * See McEnery & Wilson 1996/2001, chapter 6.
 * 
 * "lgcurve" is the abbreviation used in-code for "corpus lexical growth curve". 
 * 
 * We use two tables to represnt lgcurves: one for the info, and all datapoints go in a single table. 
 * 
 * 
 * clocure_info MASY ALOS NEED A LAST USED TIME IF A LAST-USED-FIRST-OUT CAHCE IS APPLIED...
 * 
 * A note: the datapoints are stored as a pair of BIGINT. this wastes space, but not so drastically (only 20 bytes per row, int, bigint, bigint,
 * ergo it will be lightning fast...) variable lenght encoding (in a varchar)O would prob not save space & would
 * be slower.
 *  
 */

/*  ===================== TEMP PREP NOTES. 

TWO ALTERNATIVES FOR THE ALGORITHM
		
		ALGORITHM 1 - a stream. The obvious way to do it.
		
		stream the p-att.
		
		$current_interval = 0.

		$n_toks_in_curr_interval = 0.
		
		$new_types_per_interval=array();
		
		while ($word = get_from_stream()
		{
			has this word been seen before? Check against a CUMULATIVE LIST OF TYPES SEEN (using the approp collation)
		
			if in cumulative list,
				do nothing
			if not,
				add the type to the cumulative list
				increment the n of new types in interval. $new_types_per_interbval[$curent_interval]++;
		
			$n_toks_in_curr_interval++
		
			if $n_toks_in_curr_interval == the intervaL SIZE
				$current_interval++
				$n_toks_in_curr_interval = 0.
				$new_types_per_interval[$current_interval] = 0
					// or maybe just don't reset so it is cumulative?
		}
		
		when done,
			for each $new_types_per_interval
				write one entry to the lgcurve_data table.
		
		PROS AND CONS
		=============
		
		The cumulative freqlist will end up being as big as the whole-corpus freq list.
		as such it will be necessary to have it as a mysql table.
		this table will need a LOT of updates and queries.
		
		THe references to MySQL could be minimised by keeping a list of "recently-seen types" wihtin PHP/RAM,
		and checking there FIRST. also, batching up new trypes and doing - lets say - 1000 inserts at a go, rather than one by one
		that makes life much more complex but cuts down on disk hugely.
		
		but FACT IS we KNOW how logn building tables like this takes. we don't want it to take this long.
		
		ALSO NOTE - in terms of the basic N of ops, this will loop an equiv to the N oif TOKENS.
		
=================================================================================================================


		
		ALGORITHM 2 - by lookign up the lcoation of the first instance of each TYPE from the freq table.
		
		First, set up ALL the interval slots.
		
		Let's say our interval is 1000, and our corpus n tokens is 9980
			(I'm dividing all numebrs by 1K ehere from what they would be in reality.)
			 we need 10 intervals. ( 0 to 9)
			 but if our corpus n tokens is 1000 , we need 1 interval
			 this is presunkly what we gett with ceil($toks_in_corpus/$interval_width)
		
		So n_intervals = that.
		intervals are 0 indexed.
		
		new_types_in_interval[0] = n new types seen at cpos 0 to cpos 999.
		new_types_in_interval[1] = n nhew tyhpes seen at cpos 1000 to cpos 1999
		...
		new_types_in_interval[k] = n new types seen at cpos 1000 * k to cpos (1000 * (k+1)) -1.
		...
		new types in interval[9] = n new types seen at cpos 9000 to cpos 9999 - the last 19 of whihc obv never happens, but no worries!
			// when we converty this to an X point, note we cannot just do (9+1) * 1000 : 
			// we have to cap the x point at 9980, to avoid the last point being off the line.
			
		
		
		$result = "select item from freq_corpus_____p_att"     // nbote, in practice, we'd need to use LIMIT OFFSET to get, say 1000 types at a time.
		// so actually...
		
		$case = %c or not, depending on the corpus collation mode.
		
		while true
			if $result = NULL or false = ($word = get the next row of the result. ) // no rows left in result,
				$result = select item n_toks_in_curr_interval order by item OFFSET LIMIT  // get the next 1K examples.
				continue
			
			// $word may need ot be cqp escaped. Check this.
			
			cmd = ClocurveQuery = [$p_att="$word"$case]
			$cqp->execute(cmd)
			$cqp->execute(dump ClocurveQuery 0 0)
			THE FIRST NUMBER ON THE LINE IS THE CPOS OF THE FIRST INSTANCE OF THE TYPE .
			$first_cpos = (fiorst number on the dump line)
			free up tha RAM
			$cqp->execute(discard ClocurveQuery)
			
			ok, so now we increment the correct interval.
			
			what interval is a cpos in?
			
			let's take cpos 228. We know this is in interval 0.  cpos 1000 should go in inteval 1 not interval 0
			so it's 228/1000, then floor. 
			
			first_interval_of_type = (int) floor(cpos/interval_width) 
			new_types_in_interval[first_interval_of_type] ++
			 
		$tuples = array();
		$cum_types = 0   // cumualtiobve numebr fo types seen
		
		for i = 0, n =count(new_types_in_interval) ;  i < n ; i ++
		{
			X = (i+1) * 1000
			// ie 
			// X = (i+1) * inteval_width
			if i == n-1
				X = toptal tokens in corpus // final interval may not be complete
			$cum_types + = new_types_in_interval[i];
			$tuples[] ="CONSTANT_INT_ID, X, $cum_freq"    // e.g. 1000, 12      2000, 24,      3000, 36      .....     9980, 100 
			if coujnt($tuples) > 99
				 nsert into lgcurve_datapoints (closurve_id, tokens, cum_type) VALUES implode(tuples)
				 tuples = array
		}
		
		if (not em pty tyupers)
		
			insert into lgcurve_datapoints (closurve_id, tokens, cum_type) VALUES implode(tuples)
							
			
		PROS AND CONS
		=============
		
		one pro is that the loop is across TYPES not tokens. so fewer lookups of any kind.
		
		more cirtically nothing has to go to disk - as it might well if we relied on the DB. 
		
		so the really big queries that eat RAM (the) only happen once.
		and most queries will not be so big.
		
		critically,once the p-att is in RAM, everything should be happenmiong in RAM
		nothing has to be WRITTEN to disk till the very last moment
		all intermediate list-keeping is evaded.
		
===============================

		A third possibility would be to try and use the freq-text-index, which already has some of the info we want. 
		
		Reasons why not: not all corpora have texts. Those that do differ widely in how big they are. 
*/


/*
 * FUNCTIONS NEEDED:
 */

/**
 * Checks for the existence of a lgcurve, given the same three defining
 * parameter used to request generation of said lgcurve: the corpus, the annotation, the interval.
 * 
 * @see    create_lgcurve()
 * @param  string $corpus          Corpus handle: as in create_lgcurve
 * @param  string $annotation      Annotation handle: as in create_lgcurve
 * @param  int    $interval_width  Token interval: as in create_lgcurve 
 * @return int                     Returns the integer ID of the lgcurve, if it exists. If not, returns boolean false. 
 */
function check_lgcurve_parameters($corpus, $annotation, $interval_width)
{
	$corpus = escape_sql($corpus);
	$annotation = escape_sql($annotation);
	$interval_width = (int)$interval_width;
	
// 	$result = do_sql_query("select id from lgcurve_info where corpus = '$corpus' and annotation = '$annotation' and interval_width = $interval_width");
	
// 	if (0 == mysqli_num_rows($result))
// 		return false;
	
// 	list($id) = mysqli_fetch_row($result);

// 	return (int)$id;
	return (int)get_sql_value("select id from lgcurve_info where corpus = '$corpus' and annotation = '$annotation' and interval_width = $interval_width");
}

/**
 * Gets the information on aspecifired lgcurve from the database.
 * 
 * @param  int      $id  ID of the lgcurve whose information is required.
 * @return stdClass      Database object containing the attrributes of this Clocurve datraset; false if id not foudn.
 */
function get_lgcurve_info($id)
{
	$id = (int) $id;
	
	$result = do_sql_query("select * from lgcurve_info where id = $id");
	
	if (1 > mysqli_num_rows($result))
		return false;
	
	return mysqli_fetch_object($result);
}


/**
 * Get database objects for all the lgcurves that currently exist in the specified corpus.
 * 
 * @param  string $corpus  Corpus to look for lgcurves in.
 * @return array           Array of database objects. May be empty if none exist. Keys are the ID numbers.
 */
function list_corpus_lgcurves($corpus)
{
	$corpus = cqpweb_handle_enforce($corpus);
	
	$result = do_sql_query("select * from lgcurve_info where corpus = '$corpus' order by id asc");
	
	$list = array();
	
	while ($o = mysqli_fetch_object($result))
		$list[$o->id] = $o;
	
	return $list;
}

/**
 * Get database objects for all the lgcurves across the system.
 * 
 * @return array           Array of database objects. May be empty if none exist. Keys are the ID numbers.
 */
function list_all_lgcurves()
{
	$result = do_sql_query("select * from lgcurve_info order by id asc");
	
	$list = array();
	
	while ($o = mysqli_fetch_object($result))
		$list[$o->id] = $o;
	
	return $list;
}

	
	
/**
 * Delete a lgcurve (record plus all datapoints) based on its integer reference.
 * 
 * @param  int $id  Integer ID of the lgcurve to delete.
 */
function delete_lgcurve($id)
{
	global $User;
	/* only superusers are allowed to do this! */
	if (! $User->is_admin())
		return;
	
// 	Later I may want to make it possible for lexical growth curve data that is old to delete itself to save space,
// 	for now tho deletion is an admin action.
	
	$id = (int) $id;
	
	/* this is a v  straightforward procedure! */
	do_sql_query("delete from lgcurve_datapoints where lgcurve_id = $id");
	do_sql_query("delete from lgcurve_info where id = $id");
}


/**
 * Creates a lgcurve with the specified feature triplet.
 * 
 * DELEGATOR FUNCTION FOR TWO ALGORITHMS INITIALLY TRIED OUT.
 * The briteforce seems to be the most effective, so that's what we're using. 
 * 
 * Will take no action if the specified lexical growth curve already exists. 
 * 
 * @param  string $corpus            Handle of the corpus to generate the data for
 * @param  string $annotation        The handle of the p attribute to gen for
 * @param  int    $interval_width    The interval is the n of tokens at which to take a reading.
 *                                   Eg, if this is 10000, a cumulative n of types will be calculated
 *                                   once per 10000 tokens in the corpus. Interval width cannot be
 *                                   less than 100, and normally should be much more!!! 
 * @return bool                      True for success: false if this curve already existed.
 */
function create_lgcurve($corpus, $annotation, $interval_width)
{
	return create_lgcurve_bruteforce($corpus, $annotation, $interval_width);
}


/**
 * Creates a lgcurve with the specified feature triplet.
 * 
 * ORIGINAL BY-TYPES ALGORITHM.
 * 
 * Will take no action if the specified lexical growth curve already exists. 
 * 
 * @param  string $corpus            Handle of the corpus to generate the data for
 * @param  string $annotation        The handle of the p attribute to gen for
 * @param  int    $interval_width    The interval is the n of tokens at which to take a reading.
 *                                   Eg, if this is 10000, a cumulative n of types will be calculated
 *                                   once per 10000 tokens in the corpus. Interval width cannot be
 *                                   less than 100, and normally should be much more!!! 
 * @return bool                      True for success: false if this curve already existed.
 */
function create_lgcurve_bytypes($corpus, $annotation, $interval_width)
{
	$check_id = check_lgcurve_parameters($corpus, $annotation, $interval_width);
	
	if (is_int($check_id))
		return false;
	/* a lgcurve for that data with that interval already exists. So we do nothing. */
	
	/* just a wee sanity check... even 100 is on the very small side for most corpora. */
	if ($interval_width < 100)
		exiterror("Cannot create a lgcurve with an interval of less than 100 tokens");
	
	/* also a sanity check, caller ought to make sure $corpsu is real  */
	if (!($c_info = get_corpus_info($corpus)))
		exiterror("Cannot create a lgcurve for the given corpus handle because it does not exist.");	
	
	
	/* create a record for this lgcurve, and get its ID. */ 
	$corpus = escape_sql($corpus);
	$annotation = escape_sql($annotation);
	$interval_width = (int) $interval_width;
	
	do_sql_query("insert into lgcurve_info (corpus, annotation, interval_width) values ('$corpus', '$annotation', $interval_width)");
	
	$lgcurve_id = get_sql_insert_id();
	
	
	/* this is a potentially long-running process. Avoid interrupts, plus, we want to keep track of how long it took. */
	php_execute_time_unlimit();
	$time_creation_began = time();
	
	/* ALGORITHM: 
	 * 
	 * -- we loop across types from the frequency table. We do a CQP query on each one, and get the cpos of its first occurrence.
	 * -- a combination of the frequency table's collation and the %c setting handle case-sensitivity for us.
	 * -- we take each first-instance-of-a-type cpos, and use that to add 1 to the count of previously-unseen types
	 *    in the interval in the coirpus where it first occurs.
	 * -- the idea is that this should be an efficient way to do it for 3 reasons. 
	 *    -- First, the p-attribute CWB index shoould be inm RAM afer the first few queries, reducing the amount of disk access;
	 *       there is no need to keep a track of "the lexicon so far" as we work through a token stream.
	 *    -- Second, looping over types rather than tokens (as in thew other possible approach) is up to millions of iterations 
	 *       rather than up to hundreds of millions of iterations (or even, y;know, 2.1 billion!)
	 *    -- Third, frequent items (like "the") only get dealt wiht ONCE; loooping across tokens they would be processed 
	 *       repeatedly, and they would be the most time-consuming ones to process!
	 * -- though I did not test the loop-over-tokens approach, so the above is theoreticall.
	 */
	
	
	/* first, set up our variables. */
	
	$new_types_in_interval = array(); 
	/* 
	 * this zero-indexed array holds our running counts of first-seen types, such that, 
	 * with for instance an interval of 1000 and a corpus of 9980 tokens, 
	 * 
	 * --> new_types_in_interval[0] = n new types seen at cpos 0        to cpos 999                 : will be x-point of 1000
	 * --> new_types_in_interval[1] = n new types seen at cpos 1000     to cpos 1999                : will be x-point of 2000
	 *     ...
	 * --> new_types_in_interval[k] = n new types seen at cpos 1000 * k to cpos (1000 * (k+1)) - 1  : will be x-point of (1000 * (k+1))
	 *     ...
	 * --> new types in interval[9] = n new types seen at cpos 9000     to cpos 9999                : will be x-point of 10000 => 9980 (apply cap!)
	 *     where the top end of the last interval is actually cpos 9979 because of the corpus size limit!
	 *     
	 * We build the array of intervals using the above arithmetic. 
	 * 
	 * (Equivalent way to say this: the number of intervals we need is equal to the ceil() of the corpus size divided by the interval width.)
	 */
// 	for ($i = 0; $c_info->size_tokens >= $interval_width*($i+1) ; $i++) // off by one bug
	for ($i = 0; $c_info->size_tokens >= $interval_width * $i ; $i++)
		$new_types_in_interval[$i] = 0;
	
	
	/* we also need a flag for our CQP queries: are we case sensitive or not? */ 
	$caseflag = $c_info->uses_case_sensitivity ? '' : '%c';

	/* get CQP onto the right corpus, if not already */
	$cqp = get_global_cqp();
	$cqp->set_corpus($c_info->cqp_name);

	
	/* OK, we're ready to go: get 10000 types at a time from the appropirate freq table,
	 * to avoid storing the whole lexicon table as a resultset in PHP RAM */
	
	/* init values for our read-the-freq-table SQL controllers */
	$result = NULL;
	$next_limit_begin = 0;
	
	while (1)
	{
		/* if we have no types pending, get 10000 types at a time from the appropirate freq table. */
		if (is_null($result) || !($r = mysqli_fetch_row($result)))
		{
			$result = do_sql_query("select item from freq_corpus_{$corpus}_{$annotation} LIMIT $next_limit_begin, 10000");
			/* something to consider (low urgency TODO) should the above have an order-by???? order should be deterministic even if arbitrary... 
			 * also, might it help performance to be order by freq desc? (Stefan may have thoughts.) */
			$next_limit_begin += 10000;

			/* If the above returned 0 results, we've passed the end of the freq table, and this whole job is done. 
			 * Otherwise, loop around to get the first row from the new result. */
			if (1 > mysqli_num_rows($result))
				break;
			else
				continue;
		}

		list($type) = $r;
		
		/* build and run a query in CQP for this type; use dump 0 0 to get the cpos of the first hit. */
		$cmd = 'ClosQ = [' . $annotation . '="' . CQP::escape_metacharacters($type) . '"' . $caseflag . ']';
			
 		$cqp->execute($cmd);
		
 		list($rawdump) = $cqp->execute('dump ClosQ 0 0');

		/* the first number on the line is the cpos of the first instance of the type. */
		list ($first_cpos) = explode("\t", $rawdump);
		$first_cpos = (int) $first_cpos;
		
		/* we don't need the CQP result any more, so we could 'discard' to save RAM in the CQP slave process, 
		 * but why bother: it will be overwritten on the next loop of this in any case. */

		/* what interval is the $first_cpos in? 
		 *     it's floor(cpos/$interval) so that, for instance, in the example from above w/interval 1000
		 *     cpos 999  goes in interval 0, but cpos 1000 goes in interval 1;
		 *     cpos 2999 goes in interval 2, but cpos 3000 goes in interval 3.
		 */
		$interval_of_first_cpos = (int)floor($first_cpos/$interval_width);
		
		/* so we increment the count of new typoes for that interval... and we're done for this type. */
		$new_types_in_interval[$interval_of_first_cpos]++;
	}

	
	/* our array of interval new-type scores is now complete: so we convert to a series of 
	 * (N-of-tokens, Cumulative-N-of-types) tuples that can be put into SQL.
	 * As above, to avoid thousands of insert queries, collect 500 tuples at a time.  
	 */

	$n_datapoints = count($new_types_in_interval);
	
	$tuples = array();
	
	$cumulative_n_types = 0;
	
	$tokens_after_interval = 0;
	
	for ($i = 0 ; $i < $n_datapoints ; $i++)
	{
		$tokens_after_interval += $interval_width;
		
		if ($i + 1  == $n_datapoints)
			$tokens_after_interval = $c_info->size_tokens;
			/* X val for the last interval is token count as last interval may be incomplete.  */
		
		$cumulative_n_types += $new_types_in_interval[$i];
		
		/* add an SQL tuple of the foreign key to the lgcurve ID, then the tokens, then the cumulative types. */
		$tuples[] = "($lgcurve_id,$tokens_after_interval,$cumulative_n_types)";
		/* e.g. id, 1000, 12      id, 2000, 24,     id, 3000, 36      .....     id, 9980, 100 */ 

		if (499 < count($tuples))
		{
			do_sql_query("insert into lgcurve_datapoints (lgcurve_id,tokens,types_so_far) VALUES " . implode(',', $tuples)); 
			$tuples = array();
		}
	}
	/* and now insert any leftover tuples : the datapoint set is now complete. */
	if (!empty($tuples))
		do_sql_query("insert into lgcurve_datapoints (lgcurve_id,tokens,types_so_far) VALUES " . implode(',', $tuples)); 
	
	
	/* finally: update the lgcurve_info entry to contain 
	 * (a) the creation time, which is NOW,
	 * (b) the timed creation period
	 * (c) the number of datapoints ($n_datapoints from above)
	 * */
	$create_time = time();
	$create_duration = $create_time - $time_creation_began;
	
	do_sql_query("
			update lgcurve_info set
				create_time     = $create_time,
				create_duration = $create_duration,
				n_datapoints    = $n_datapoints
			where id = $lgcurve_id");
	
	return true;
}





/**
 * Creates a lgcurve with the specified feature triplet.
 * 
 * EXPERIMENTAL IMPLEMENTATION OF THE BRUTE-FORCE ALGORITHM.
 * 
 * Will take no action if the specified lexical growth curve already exists. 
 * 
 * @param  string $corpus            Handle of the corpus to generate the data for
 * @param  string $annotation        The handle of the p attribute to gen for
 * @param  int    $interval_width    The interval is the n of tokens at which to take a reading.
 *                                   Eg, if this is 10000, a cumulative n of types will be calculated
 *                                   once per 10000 tokens in the corpus. Interval width cannot be
 *                                   less than 100, and normally should be much more!!! 
 * @return bool                      True for success: false if this curve already existed.
 */
function create_lgcurve_bruteforce($corpus, $annotation, $interval_width)
{
	global $Config;
	
	$check_id = check_lgcurve_parameters($corpus, $annotation, $interval_width);
	
	if (is_int($check_id))
		return false;
	/* a lgcurve for that data with that interval already exists. So we do nothing. */
	
	/* just a wee sanity check... even 100 is on the very small side for most corpora. */
	if ($interval_width < 100)
		exiterror("Cannot create a lgcurve with an interval of less than 100 tokens");
	
	/* also a sanity check, caller ought to make sure $corpus is real  */
	if (!($c_info = get_corpus_info($corpus)))
		exiterror("Cannot create a lgcurve for the given corpus handle becausew it does not exist.");	
	
	
	/* create a record for this lgcurve, and get its ID. */ 
	$corpus = cqpweb_handle_enforce($corpus, HANDLE_MAX_CORPUS);
	$annotation = cqpweb_handle_enforce($annotation, HANDLE_MAX_ANNOTATION);
	$interval_width = (int)$interval_width;
	
	do_sql_query("insert into lgcurve_info (corpus, annotation, interval_width) values ('$corpus', '$annotation', $interval_width)");
	
	$lgcurve_id = get_sql_insert_id();
	
	
	/* this is a potentially long-running process. Avoid interrupts, plus, we want to keep track of how long it took. */
	php_execute_time_unlimit();
	$time_creation_began = time();
	
	
	/* ALGORITHM: 
	 * 
	 * -- loops across tokens from the corpus using cwb-decode pipe.
	 * -- for each token,
	 *    -- if the corpus is case-insensitive, casefold (using strtolower if not UTF-8, mb_strtolower if it is)
	 *       (note that this only approximates %c, but is, we hope, close enough for this purpose)
	 *       (and note that this is only applied to the "word" attribute, not others;' we assume that in 
	 *       annotations applied as separate fields, people manage their own casefolding if they want it!)
	 *    -- check the "seen" hash. 
	 *    -- if it is seen, do nowt.
	 *    -- else add it to the hash, and increment the Cumulative Types Seen.
	 *    -- if the number of tokens examined = as full interval, add a tuple (id, X, Y) to the block of tuples
	 *    -- if we have 500 tuples, write them to SQL, and continue.
	 * -- keep track of N datapoints as we go.
	 * -- the idea is that although we need A LOOOOOT of RAM for the hashtable, we will get the quickest result
	 *    this way in terms of the amount of coding/decoding that has to be done in the CPU for the index.
	 * -- potential optimisation to maybe try: get the top 1K types from the freqtable, and keep them in a separate
	 *    check-me-first hash (so all the most frequent types only need to be checked in a much smaller hash)
	 *    This not done yet. (Will mean that all rare types require two lookups, as cost for speeding up sole lookup
	 *    for all frequent types. Top 1000 types will often cover a large % of the text.)
	 */
	
	/* variable setup */
	
	/* in order to know how to casefold, we need to know what the underlying charset is. Applies to "word" only. */
	if ( 'word' == $annotation && ! $c_info->uses_case_sensitivity )
	{
		$cqp = get_global_cqp();
		$cqp->set_corpus($c_info->cqp_name);

		switch ($cqp->get_corpus_charset())
		{
		case 'utf8':
			if (function_exists('mb_strtolower'))
				$casefold_func = function ($s){ return mb_strtolower($s, 'UTF-8'); } ;
			else
				exiterror("Cannot build a lexical growth curve for a case-insensitve corpus without PHP's mb_strtolower() function installed! ");
			break;
		default:
			$casefold_func = 'strtolower';
			/* this is only semi-satisfactory due to locale shenanigans applying to 8-bit encodings. But, it'll do I suppose. */
			break;
		}
	}
	
	$seen_types = array();
	
	$cumulative_n_types = 0;
	
	$total_tokens_seen = 0;
	$tokens_this_interval = 0;
	
	$n_datapoints = 0;
	$tuples = array();
	
	/* Open source pipe. NB, down the line this could be fixed to allow running on a subcorpus here. (Using matchlistfile mode with -f.) */ 
	$cwb_cmd = "{$Config->path_to_cwb}cwb-decode -r \"{$Config->dir->registry}\" -C {$c_info->cqp_name} -P $annotation";
	if (false === ($source = popen($cwb_cmd, "r")))
		exiterror("Couldn't open cwb-decode to read p-attribute for lexical growth curve!!");
	
	while (false !== ($token = fgets($source)))
	{
		$token = trim($token);
		
		/* casefold here if necessary */
		if ('word' == $annotation)
			if ( ! $c_info->uses_case_sensitivity)
				$token = $casefold_func($token);

		/* hash look up of (folded) token */
		if ( !isset($seen_types[$token]))
		{
			$seen_types[$token] = true;
			++$cumulative_n_types;
		}
		
		++$tokens_this_interval;
		++$total_tokens_seen;
		
		if ($tokens_this_interval == $interval_width)
		{
			$tuples[]= "($lgcurve_id,$total_tokens_seen,$cumulative_n_types)";
			++$n_datapoints;
			
			$tokens_this_interval = 0;
			
			if (499 < count($tuples))
			{
				do_sql_query("insert into lgcurve_datapoints (lgcurve_id,tokens,types_so_far) VALUES " . implode(',', $tuples)); 
				$tuples = array();
			}
		}
	}
	/* there may be an extra tuple if tokens were seen after the last commit. */
	if (0 < $tokens_this_interval)
	{
		$tuples[]= "($lgcurve_id,$total_tokens_seen,$cumulative_n_types)";
		++$n_datapoints;
	}
	/* and now insert any leftover tuples : the datapoint set is now complete. */
	if (!empty($tuples))
		do_sql_query("insert into lgcurve_datapoints (lgcurve_id,tokens,types_so_far) VALUES " . implode(',', $tuples));
	
	/* and we are done with the pipe, so close it. */
	pclose($source);
	

	/* finally: update the lgcurve_info entry to contain 
	 * (a) the creation time, which is NOW,
	 * (b) the timed creation period
	 * (c) the number of datapoints ($n_datapoints from above)
	 * */
	$create_time = time();
	$create_duration = $create_time - $time_creation_began;
	
	do_sql_query("
			update lgcurve_info set
				create_time     = $create_time,
				create_duration = $create_duration,
				n_datapoints    = $n_datapoints
			where id = $lgcurve_id");

	squawk("Max memory used for building lexical growth curve: " . memory_get_peak_usage());
	
	return true;
}




/**
 * Sends the content of a lexical growth curve to the browser as a text-file download.
 * 
 * The resulting file contains two columns of integers (tab separated).
 * The first column are the X points, the top of each "zone" considered 
 * (e.g. 1000 = the X point for the cumulative N or types between cpos 0 and 999 inclusive, the first 1000 tokens.). 
 * and the second column, the Y points, are the actual cumulative N of types for each interval.
 * 
 * @param int  $id           ID of the lgcurve to Download.
 * @param bool $header_line  If true, file has a header line. If false, just the graph coordinates.
 */
function download_lgcurve($id, $header_line = true)
{
	global $User;

	/* get EOL, as usual for a download. */
	$eol = $User->eol();
	
	$id = (int)$id;
	
	if (!($lgc = get_lgcurve_info($id)))
		exiterror("The lexical growth curve that you have tried to download does not seem to exist. It may have been deleted.");
	
	/* assemble the filename from info in the lgcurve obj */
	$filename = 'lgcurve-' . $lgc->corpus . '-' . $lgc->annotation . '.txt';
	
	/* send the HTTP header */
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-Disposition: attachment; filename=$filename");
	
	/* write header line if wanted */
	if ($header_line)
		echo "N of tokens\tCumulative N of types seen", $eol;

	/* get the lexical growth curve and write to output attachment! */
	$result = do_sql_query("select tokens as X, types_so_far as Y from lgcurve_datapoints where lgcurve_id = $id order by X asc");
	
	while ($o = mysqli_fetch_object($result))
		echo $o->X, "\t", $o->Y, $eol;

	/* and, all done. */
}



//TODO more shtoof:
//-----------------

// and then some funcs will be needed to do stuff with R graphs.... 

// load to R? load more than 1 to R, so we can overlay curves for comparison ; maybe

// later funcs like "check N of lexical growth curves, and how mcuh disk spacer they need" will be wanted...

// note, this does not cover subcorpora. the algo would have to be totally different I reckon...





// a function to scrub all lgcurves if the case-sensitivity of the corpus changes????????

// as with the main freqtables....









