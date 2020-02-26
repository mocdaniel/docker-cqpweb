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

include('../lib/ceqlparser.php');


echo "\n";

if (empty($argv[1]))
	list($n_passed, $n_failed) = run_ceql_php_test_suite("all-queries.txt");
else if ($argv[1] == '--help' || $argv[1] == '--help')
	exit("Usage: php ceql-unit-test.php [FILE_WITH_TESTS]\nFormat for test file is described in code.\nIf no file given, 5 simple tests will run.\n");
else
	list($n_passed, $n_failed) = run_ceql_php_test_suite($argv[1]);

$total = $n_failed + $n_passed;

echo <<<END

All tests complete; here is the report:

   * Total  tests:   $total
   * Passed tests:   $n_passed
   * Failed tests:   $n_failed


END;


exit; /* COMMENT OUT TO INCLUDE THE MAPPING TABLE TEST */



mapping_table_test:

/* a demo on getting the mapping tables right: 
 * A Perl hash becomes a JSON object thusly:
 */
$x = <<<END
{
                        "A" => "ADJ",
                        "ADJ" => "ADJ",
                        "N" => "SUBST",
                        "SUBST" => "SUBST",
                        "V" => "VERB",
                        "VERB" => "VERB",
                        "ADV" => "ADV",
                        "ART" => "ART",
                        "CONJ" => "CONJ",
                        "INT" => "INTERJ",
                        "INTERJ" => "INTERJ",
                        "PREP" => "PREP",
                        "PRON" => "PRON",
                        '$' => "STOP",
                        "STOP" => "STOP",
                        "UNC" => "UNC"
                        }
END;

$y = str_replace('=>', ':', $x);

var_dump(json_decode($y));

/* a Perl hash becomes a PHP associative array like this:
 */
$y = [];
foreach (explode(',', trim($x, '{}')) as $pair)
{
	list ($k, $v) = explode('=>', trim($pair));
	$k = trim(trim($k), '\'"');
	$v = trim(trim($v), '\'"');
	$y[$k] = $v;
}
var_dump($y);




/*
 * =========
 * FUNCTIONS
 * =========
 */




function get_ceql_test_items($testfile = '')
{
	/* test lineformat: CEQL \t CQP \t Mode\t some number \n */

	if (! empty($testfile))
		$block = file_get_contents($testfile);
	else
		$block = <<<END_OF_BLOCK
said_VVD >>6>> that_CST	MU(meet [word=\"said\"%c & pos=\"VVD\"] [word=\"that\"%c & pos=\"CST\"] 1 6)	sq_nocase	114
elephant	[word="elephant"%c]	sq_nocase	11
elephant*	[word="elephant.*"%c]	sq_nocase	11
elephant*	[word="elephant.*"]	sq_case	11
{break}	[lemma="break"%c]	sq_nocase	647
by your favour	[word="by"%c] [word="your"%c] [word="favour"%c]	sq_nocase	100
END_OF_BLOCK;
	
	/*  == uncomment to test just one pattern.
 	$block = '\+ty	[word="\+ty"%c]	sq_nocase	2';
	*/
	
	/* 
	How I got a bunch of tests from the CQPweb history on my local server:
	
	select distinct(CONCAT(simple_query,"\t",cqp_query,"\t",query_mode)) as qq, count(*) as n 
			from query_history 
			where query_mode !="cqp" 
			group by qq 
			having n > 1 
			order by n desc 
			into outfile "/tmp/all-queries.txt";
	Result was circa 100K queries which I narrowed to approx 37K.
	
	The results are stored outside the repo (privacy issues.)
	*/ 
	
	$keys = ['input', 'desired_result', 'mode', 'freq'];
	$tests = array();
	$line_no = 0;
	foreach (explode("\n", $block) as $line)
	{
		++$line_no;
		if (empty($line = trim($line)))
			continue;

		$arr = explode("\t", trim($line));
		if (4 != count($arr))
		{
			echo "BAD LINE: $line\n";
			continue;
		}
		$o = (object) array_combine($keys, $arr);
		
		/* we want an empty string to be NULL */
		if ('' === $o->desired_result)
			$o->desired_result = NULL;
		
		/* sometimes, in tests derived from query_history, there are bad ones where the simple query is equal tot he CQP query. */
		if ($o->input != $o->desired_result)
			$tests[$line_no] = $o; 
	}
	return $tests;
}

function run_ceql_php_test_suite($testfile = '')
{
	
	/* these are Oxford Simplified Tags. Can't yet test CEQL expressions which use a different one. */
	$simpleHash = [
                        "A" => "ADJ",
                        "ADJ" => "ADJ",
                        "N" => "SUBST",
                        "SUBST" => "SUBST",
                        "V" => "VERB",
                        "VERB" => "VERB",
                        "ADV" => "ADV",
                        "ART" => "ART",
                        "CONJ" => "CONJ",
                        "INT" => "INTERJ",
                        "INTERJ" => "INTERJ",
                        "PREP" => "PREP",
                        "PRON" => "PRON",
                        '$' => "STOP",
                        "STOP" => "STOP",
                        "UNC" => "UNC"
	];

	$ceql = new CeqlParserForCQPweb();
	
	/* these are most of the xml tags used in my own database of tests... */
	$xmlHash = [];
	foreach (
		explode(' ' , 'text text_id p head s u u_who u_trans u_whoConfidence event event_desc foreign foreign_lang gap gap_desc pause pause_length article article_category scene_type stress stage unclear voc voc_desc quote') 
			as $x)
		$xmlHash[$x] = 1;
	
	$ceql->SetParam("s_attributes", $xmlHash);
	
	$p = explode(' ', 'pos lemma class taglemma');
	
	if (isset($p[0]))
		$ceql->SetParam("pos_attribute", $p[0]);
	if (isset($p[1]))
		$ceql->SetParam("lemma_attribute", $p[1]);
	if (isset($p[2]))
	{
		$ceql->SetParam("simple_pos_attribute", $p[2]);
		$ceql->SetParam("simple_pos", $simpleHash);
	}
	if (isset($p[3]))
		$ceql->SetParam("combo_attribute", $p[3]);
	
	$n_passed = 0;
	$n_failed = 0;
	
	foreach (get_ceql_test_items($testfile) as $test_no => $test)
	{
		$ceql->SetParam("default_ignore_case", ($test->mode == 'sq_nocase'));

		/* because we assume CLI, we use ncurses code for report. */
		echo "\033[sRunning test $test_no  ....";
		
		$result = $ceql->Parse($test->input);

		
		if ($test->desired_result == $result)
		{
			/* this includes a NULL result that we expected. */
			++$n_passed;
			echo "\033[u                             \033[u";
			/* only in case of a passed test do we go into reverse. */
		}
		else
		{
			++$n_failed;
			if (is_null($result))
			{
				/* indicates parser error, not just output mismatch. */
				foreach ($ceql->ErrorMessage() as $el)
					echo $el, "\n";
				break;
			}
			echo "\tTest # $test_no failed!! GOT != WANTED     $result   !=   {$test->desired_result}\n";
		}
		if (49 < $n_failed)
			break;
	}
	
	unset($ceql);

	return array (0=>$n_passed, 1=>$n_failed, 'passed'=>$n_passed, 'failed'=>$n_failed);
}

