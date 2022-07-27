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





/* note: this script emits nothing on stdout until the last minute, because it can alternatively write a plaintext file as HTTP attachment */



/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');



$Config = $Corpus = NULL; 

require('../lib/environment.php');


/* The freqlist UI form can redirect us away from freqlist.php */
if (isset($_GET['redirect']))
{
	switch($_GET['redirect'])
	{
	case 'newQuery':
		header("Location: index.php");
		break;
	case 'newFreqlist':
		header("Location: index.php?ui=freqList");
		exit;
	case 'downloadFreqList':
		$_GET['tableDownloadMode'] = 1;
		break;
	/* default continue to this script */
	}
}



/* include function library files */
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');
require('../lib/xml-lib.php');
require('../lib/freqtable-lib.php');
require('../lib/scope-lib.php');
require('../lib/useracct-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/cqp.inc.php');


$Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);



/* ------------------------------- *
 * initialise variables from $_GET *
 * and perform initial fiddling    *
 * ------------------------------- */


/* this is a bit of a cheat to get rid of empty strings && make sure isset works properly */
foreach ($_GET as $k => $g)
	if ($g === '')
		unset($_GET[$k]);
		/* note, this could bugger up any script that needs to read a restriction string! */


/* do we want a nice HTML table or a downloadable table? */
$download_mode = (isset ($_GET['tableDownloadMode']) ? (bool)$_GET['tableDownloadMode'] : false);

/* Params to be passed to functions that need to assemble links to this page */
$param_hash = array();

/*
 * the table to use would be extracted from GET here, but it requires mysql
 */




/* flAtt: attribute to base the table on */

if (!isset($_GET['flAtt']) )
	$att = 'word';
else
	$att = $_GET['flAtt'];
if (preg_match('/\W/', $_GET['flAtt']) > 0)
	exiterror("An invalid word-annotation (" . escape_html($att) . ") was specified!");
/* validated below */
$param_hash['flAtt'] = $att;

/* determine the order of the frequency list */

switch ($_GET['flOrder'])
{
case 'alph':
	$order_by_clause = 'order by item asc, freq desc';
	$param_hash['flOrder'] = 'alph';
	break;
case 'asc':
	$order_by_clause = 'order by freq asc, item';
	$param_hash['flOrder'] = 'asc';
	break;
default:
	$order_by_clause = 'order by freq desc, item';
	$param_hash['flOrder'] = 'desc';
	break;
}



/* set up the filter */

/* value checking is done by the escape_sql and by the "switch" */

if (!empty($_GET['flFilterString']))
{
	$param_hash['flFilterString'] = $_GET['flFilterString'];
	
	switch($_GET['flFilterType'])
	{
	case 'begin':
		$filter_clause = "item like '" . escape_sql($_GET['flFilterString']) . "%'";
		$filter_desc = ", starting with &ldquo;" . escape_html($_GET['flFilterString']) . "&rdquo;";
		$param_hash['flFilterType'] = 'begin';
		break;
	case 'end':
		$filter_clause = "item like '%" . escape_sql($_GET['flFilterString']) . "'";
		$filter_desc = ", ending with &ldquo;" . escape_html($_GET['flFilterString']) . "&rdquo;";
		$param_hash['flFilterType'] = 'end';
		 break;
	case 'contain':
		$filter_clause = "item like '%" . escape_sql($_GET['flFilterString']) . "%'";
		$filter_desc = ", containing &ldquo;" . escape_html($_GET['flFilterString']) . "&rdquo;";
		$param_hash['flFilterType'] = 'contain';
		break;
	case 'exact':
		$filter_clause = "item = '" . escape_sql($_GET['flFilterString']) . "'";
		$filter_desc = ", matching &ldquo;" . escape_html($_GET['flFilterString']) . "&rdquo;";
		$param_hash['flFilterType'] = 'exact';
		break;
	default:	/* inc NULL or '' if filter type not set */
		$filter_clause = "";
		$filter_desc = '';
		unset($param_hash['flFilterString']);
		break;
	}
}
else
	$filter_desc = $filter_clause = '';

/* set up the frequency filter */

/* if only one is set, make sure it is flFreqLimit1 */
if (isset($_GET['flFreqLimit2']) && !isset($_GET['flFreqLimit1']))
{
	$_GET['flFreqLimit1'] = $_GET['flFreqLimit2'];
	unset($_GET['flFreqLimit2']);
}

if (isset($_GET['flFreqLimit1'], $_GET['flFreqLimit2']))
{
	/* both are set */
	$up_limit = (int)$_GET['flFreqLimit1'];
	$down_limit = (int)$_GET['flFreqLimit2'];
	
	if ($down_limit > $up_limit)
	{
		$temp = $down_limit;
		$down_limit = $up_limit;
		$up_limit = $temp;
	}
	
	$range_clause = "freq BETWEEN $down_limit AND $up_limit";
	$range_desc = ", occurring between $down_limit and $up_limit times";
	
	$param_hash['flFreqLimit1'] = $up_limit;
	$param_hash['flFreqLimit2'] = $down_limit;
}
else if (isset($_GET['flFreqLimit1']))
{
	/* only one was set: treat as a minimum if we are in desc order, a maximum otherwise */
	$limit = (int)$_GET['flFreqLimit1'];
	
	if ($_GET['flOrder'] === 'asc')
	{
		$range_clause = "freq >= $limit";
		$range_desc = ", occurring at least $limit times";
	}
	else
	{
		$range_clause = "freq <= $limit";
		$range_desc = ", occurring not more than $limit times";
	}
	
	$param_hash['flFreqLimit1'] = $limit;
}
else
	$range_desc = $range_clause = '';



/* per page and page numbers */

if (isset($_GET['pageNo']))
	$page_no = prepare_page_no($_GET['pageNo']);
else
	$page_no = 1;

if (isset($_GET['pp']))
	$param_hash['pageNo'] = $per_page = prepare_per_page($_GET['pp']);   /* filters out any invalid options */
else
	$per_page = $Config->default_per_page;
/* note use of same variables as used in a concordance */

$limit_string = ($download_mode ? '' : ("LIMIT ". ($page_no-1) * $per_page . ', ' . $per_page));




/* -------------------------- *
 * end of variable initiation *
 * -------------------------- */





/* now there are two more parameters to process */

/* the table to use (basename) */

if ( ($_GET['flTable'] ?? '__entire_corpus') == '__entire_corpus' )
{
	$table_base = "freq_corpus_{$Corpus->name}";
	$table_desc = "entire &ldquo;" . escape_html($Corpus->title) . "&rdquo;";
	$param_hash['flTable'] = '__entire_corpus';
}
else
{
	if (false === ($subcorpus = Subcorpus::new_from_id($_GET['flTable'])))
		exiterror("Cannot find the specified subcorpus.");
	if (!$subcorpus->has_freqtable())
		exiterror("The specified subcorpus has no frequency table (hint: compile it under ''Create/edit subcorpora''.");
	
	$freqtable_record = $subcorpus->get_freqtable_record();
	touch_freqtable($freqtable_record->freqtable_name);
	$table_base = $freqtable_record->freqtable_name; // TODO 3.3 this is a possible fail point for new, rationalised tablenames.  
	$table_desc = "subcorpus &ldquo;{$subcorpus->name}&rdquo;";
	$param_hash['flTable'] = $subcorpus->id;
}

/* create a restriction string to go in any queries that are created */
$restrict_url_fragment = (isset($subcorpus) ? '&del=begin&t=~sc~'. $subcorpus->id . '&del=end' : '');


/* check the attribute setting is valid */

$att_desc = list_corpus_annotations($Corpus->name);
$att_desc['word'] = 'Word';

/* if the script has been fed an attribute that doesn't exist for this corpus, failsafe to 'word' */
if (!isset($att_desc[$att]))
	$att = 'word';

$freqtable = "{$table_base}_$att";// TODO 3.3 this is a possible fail point for new, rationalised tablenames.  





/* now we can assemble the SQL query */

if (! $range_clause && ! $filter_clause)
	$grand_where = '';
else if ($range_clause && !$filter_clause)
	$grand_where = "where $range_clause";
else if (!$range_clause && $filter_clause)
	$grand_where = "where $filter_clause";
else
	$grand_where = "where $filter_clause and $range_clause";

$sql = "SELECT item, freq from $freqtable 
			$grand_where
			$order_by_clause
			$limit_string";

/* and run it */
$result = do_sql_query($sql);

/* get number of tokens for AntConc-style downloads */
if ($download_mode)
	$sum_tokens = get_sql_value(str_replace('SELECT item, freq', 'SELECT sum(freq)', $sql));

$n_key_items = mysqli_num_rows($result);

$next_page_exists = ( $n_key_items == $per_page ? true : false );




$description = 
	($download_mode 
		? "Frequency list: {$att_desc[$att]} frequencies in {$table_desc}{$filter_desc}{$range_desc}"
				// TODO. Note that the 3 final things may contain HTML (&ldquo; etc.) 
				// ... these are cuirrently removed by the write_download func, but perhaps do this here instead?
		: "Frequency list: <em>".escape_html($att_desc[$att])."</em> frequencies in {$table_desc}{$filter_desc}{$range_desc}"
	);








if ($download_mode)
{
	freqlist_write_download($att_desc[$att], $description, $result, $sum_tokens, $n_key_items);
	/* nb. $n is only the sum of types in download mode! 
	 * In non-download mode, it is the n of items viewed. */
}
else if ($Config->Api)
{
	freqlist_write_for_api($result);
}
else
{
	/* writing HTML begins here! */

	echo print_html_header($Corpus->title . ' -- view CQPweb frequency list', $Config->css_path);

	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">
				<?php echo $description; ?>
			</th>
		</tr>
		
		<?php echo print_freqlist_control_row($page_no, $next_page_exists, $param_hash); ?>
		
	</table>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" width="5%">No.</th>
			<th class="concordtable" width="40%"><?php echo $att_desc[$att]; ?></th>
			<th class="concordtable">Frequency</th>
		</tr>

		<?php
		/* print the results */
		
		/* this is the number SHOWN on the first line */
		/* the value of $i is (relatively speaking) 1 less than this */
		$begin_at = (($page_no - 1) * $per_page) + 1; 
		
		for ( $i = 0 ; $i < $n_key_items ; $i++ )
		{
			$o = mysqli_fetch_object($result);
			echo "\n\t<tr>\n\t\t", print_freqlist_line($o, ($begin_at + $i), $att, $restrict_url_fragment), "\n\t</tr>\n";
		}
		
		?>
	
	</table>
	
	<?php
	
	echo print_html_footer('freqlist');

}



cqpweb_shutdown_environment();


/* ------------- *
 * end of script *
 * ------------- */


/**
 * 
 * @param stdClass $data     Database object with two members: item and freq.
 * @param int $line_number   The line number to print.
 * @param string $att        The attribute handle fo the frquency list (for use in query building).
 * @param string $restricts  URL-serialised restrictions to use in the query-link, specifying which subcorpus.
 * 
 * @return string            HTML for printing (set of td elements, no surrounding tr!)
 */
function print_freqlist_line($data, $line_number, $att, $restricts)
{
	/* needed for corpus-specific query generation links */
	global $Corpus;

	if ( $att == 'word' && ! $Corpus->uses_case_sensitivity && function_exists('mb_strtolower') )
		$data->item = mb_strtolower($data->item, 'UTF-8');
		/* there may be a better function to use in future versions of PHP; the mb extension is nonstandard */

	$target = CQP::escape_metacharacters($data->item);

	$link = 'href="concordance.php?theData=' 
			. urlencode("[$att=\"{$target}\"{$Corpus->cqp_query_default_flags}]")
			. $restricts
			. '&qmode=cqp"'
			;
	$string  = "<td class=\"concordgeneral\" align=\"right\"><b>$line_number</b></td>";
	$string .= "<td class=\"concordgeneral\"><b><a $link>" . escape_html($data->item) . "</a></b></td>";
	$string .= "<td class=\"concordgeneral\"  align=\"center\">" 
		. number_format((float)$data->freq) . '</td>';

	return $string;
}





function print_freqlist_control_row($page_no, $next_page_exists, $param_hash = [])
{
	$marker = array( 'first' => '|&lt;', 'prev' => '&lt;&lt;', 'next' => "&gt;&gt;" );
	
	/* work out page numbers */
	$nav_page_no = array();
	$nav_page_no['first'] = ($page_no == 1 ? 0 : 1);
	$nav_page_no['prev']  = $page_no - 1;
	$nav_page_no['next']  = ( (! $next_page_exists) ? 0 : $page_no + 1);
	/* all page numbers that should be dead links are now set to zero  */
	
	$string = '<tr>';

	foreach ($marker as $key => $m)
	{
		$string .= '<td align="center" class="concordgrey"><b><a class="page_nav_links" ';
		$n = $nav_page_no[$key];
		if ( $n != 0 )
		{
			/* this should be an active link */
			$param_hash['pageNo'] = $n;
			$string .= 'href="freqlist.php?' . print_url_params($param_hash) . '"';
		}
		$string .= ">$m</b></a></td>";
	}
// TODO use the simple/braindead navlinks here instead.


	unset($param_hash['pageNo'], $param_hash['pp']);
	
	$string .= '
		<td class="concordgrey">
			<form action="freqlist.php" method="get">
				<select name="redirect">
					<option value="newFreqlist" selected>New Frequency List</option>
					<option value="downloadFreqList">Download whole list</option>
					<option value="newQuery">New Query</option>
				</select>
				&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="submit" value="Go!">
				' . print_hidden_inputs($param_hash) . '
			</form>
		</td>
	</tr>
';
	
	return $string;
}


function freqlist_write_download($att_desc, $description, $result, $sum_tokens, $sum_types)
{
	global $User;
	$eol = $User->eol();
	$description = preg_replace('/&[lr]dquo;/', '"', $description);

	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename=frequency_list.txt");
	
	/* test setting: false = normal style, true = AntConc style. */
	if (!$User->freqlist_altstyle)
	{
		/* classic CQPweb freqlist download. */
		echo "$description$eol";
		echo "__________________$eol$eol";
		echo "Number\t$att_desc\tFrequency$eol$eol";
	
		for ( $i = 1 ; $o = mysqli_fetch_object($result) ; $i++ )
			echo "$i\t{$o->item}\t{$o->freq}$eol";

		echo "{$eol}Total:\t\t$sum_tokens$eol";
	}
	else
	{
		/* alternative=style freqlist download (AntConc-compatible). */
		echo "#Word Types: $sum_types$eol";
		echo "#Word Tokens: $sum_tokens$eol";
		echo "#Search Hits: 0$eol";
	
		for ( $i = 1 ; $o = mysqli_fetch_object($result) ; $i++ )
			echo "$i\t{$o->freq}\t{$o->item}\t$eol";
	}
}

function freqlist_write_for_api($result)
{
	$table = [];
	
	while ($o = mysqli_fetch_object($result))
		$table[] = [$o->item, $o->freq];
	
	$Config->Api->set_response_content($table);
}
