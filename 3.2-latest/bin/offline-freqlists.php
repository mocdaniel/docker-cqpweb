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

echo "\n\n/***********************/\n\n\n";

echo '

This script runs all the setup for frequency lists for a corpus.

Full debug messages are printed, unless the flag --quiet is provided. 

Note, if you run this script before setting up the text metdata table, things WILL go badly wrong.


';




/* include defaults and settings */
require('../lib/environment.php');


/* include all function files */
include('../lib/admin-lib.php');
include('../lib/cache-lib.php');
include('../lib/db-lib.php');
include('../lib/collocation-lib.php');
include('../lib/concordance-lib.php');
include('../lib/freqtable-lib.php');
include('../lib/corpus-lib.php');
include('../lib/annotation-lib.php');
include('../lib/general-lib.php');
include('../lib/query-lib.php');
include('../lib/html-lib.php');
include('../lib/sql-lib.php');
include('../lib/metadata-lib.php');
include('../lib/scope-lib.php');
include('../lib/exiterror-lib.php');
include('../lib/useracct-lib.php');
include('../lib/rface.php');
include('../lib/xml-lib.php');
include('../lib/cqp.php');


/* has the corpus been specified as a command-line argument rather than it being derived from a web-directory? */
if (isset($argv[1]))
{
	if ( is_dir ("../$argv[1]") || is_link("../$argv[1]") )
		$corpus = $argv[1];
	else
		exit("Critical error: the corpus you specified does not appear to exist on the system.\n");
}
else
{
	/* otherwise, we need to extract the corpus from our present path; the current directory will
	 * resolve to 'somthing/exe' so we need to work out the final symlink.
	 * When running on Unix, this is available as the PWD variable in $_SERVER (inherited from the shell).
	 * ( TODO how to access this on Windows? ) 
	 */
	if (empty($_SERVER['PWD']))
		exit("Critical error: cannot identify corpus from shell env variable ``PWD''.\n");
	/* the following code uses the method from the $Corpus constructor */
	$junk = explode('/', str_replace('\\', '/', rtrim($_SERVER['PWD'], '/\\')));
	$corpus = end($junk);
	unset($junk);
}
/* now, we put the corpus name in the right place for startup... */
if (! isset($_GET))
	$_GET = array();
$_GET['c'] = $corpus;


/* prepare globals */
$Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_NO_FLAGS);

/* keep a note of when we started */
$start_time = @date(DATE_RSS);

/* if PHP memory is limited (not -1), then expand the PHP memory limit to the same generous limit allowed for CWB */
if ("-1" !== ini_get("memory_limit"))
	ini_set('memory_limit', "{$Config->cwb_max_ram_usage_cli}M");


if (! in_array('--quiet', $argv))
	$Config->print_debug_messages = true;


echo "About to run the function populating corpus CQP positions...\n\n";
populate_corpus_cqp_positions($corpus);
echo "Done populating corpus CQP positions.\n\n";

/* if there are any classifications... */
if (0 < mysqli_num_rows(
		do_sql_query("select handle from text_metadata_fields 
			where corpus = '$corpus' and datatype = " . METADATA_TYPE_CLASSIFICATION)
		) )
{
	echo "About to run the function calculating category sizes...\n\n";
	metadata_calculate_category_sizes($corpus);
	echo "Done calculating category sizes.\n\n";
}
else
	echo "Function calculating category sizes was not run because there aren't any text classifications.\n\n";

/* if there is more than one text ... */
list($n) = mysqli_fetch_row(do_sql_query("select count(text_id) from text_metadata_for_$corpus"));
if ($n > 1)
{
	echo "About to run the function making the CWB text-by-text frequency index...\n\n";	
	make_cwb_freq_index($corpus);
	echo "Done making the CWB text-by-text frequency index.\n\n";	
}
else
	echo "Function making the CWB text-by-text frequency index was not run because there is only one text.\n\n";	


/* do unconditionally */

echo "About to run the function creating frequency tables.\n\n";	
corpus_make_freqtables($corpus);
echo "Done creating frequency tables...\n\n";

echo "About to calculate the cached STTR.\n\n";
update_corpus_sttr($corpus);
echo "Done!\n\n";

cqpweb_shutdown_environment();

$end_time = @date(DATE_RSS);
echo <<<END_OF_MESSAGE

========================================================
Frequency-list setup for corpus $corpus is now complete.

   Began at: $start_time

   Finished at: $end_time

CQPweb now terminates.
========================================================



END_OF_MESSAGE;

/*
 * END OF SCRIPT
 */


