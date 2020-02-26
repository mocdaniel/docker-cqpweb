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
 * @file This file contains some corpus-level data visualisation things.
 * 
 * HOW IT WORKS: The main script just creates an SVG area in the middle of a one-column table row.
 * 
 * Different "versions" of this script area expected to do their setup from GET
 * parameters, and in turn set up functions for use by the main script, within the switch
 * that checks the main 'viz' GET parameter.
 * 
 * Each "version" sets the following variables:
 * 
 *    $do_d3_svg_script - contains a no-parameter anonymous function that will write the 
 *    d3 script needed to manage the SVG to stdout.
 * 
 *    $viz_heading - what goes into the <th> above the SVG area.
 *    
 *    $do_control_block - contains a no-parameter anonymous function that will write the
 *    HTML for the (optional) control block below the generated graph.
 */

/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');

/* include function library files */
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/html-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/cqp.inc.php');
require('../lib/corpus-lib.php');
require('../lib/lgcurve-lib.php');



/* declare global variables */
$Corpus = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);


if (!isset($_GET['viz']))
	$_GET['viz'] = NULL;


switch ($_GET['viz'])
{
case 'lgc':
	
	/* LEXICAL GROWTH CURVE */
	
	if (empty($_GET['curves']))
		exiterror("No LG curve ID as specified.");
	if (! preg_match('/^\d+(;\d+)*$/', $_GET['curves']))
		exiterror("Invalid LG curve specified.");
	
	$curves = explode(';', $_GET['curves']);
	natsort($curves);
	
	/* set the three variables we need... */ 
	
	$viz_heading = 'Lexical Growth Curve';
	
	$do_d3_svg_script = function () use ($curves) { do_lgcurve_graph($curves); };
	
	$do_control_block = function () use ($curves) { do_lgcurve_controls($curves); };
	
	break;

case 'plugin':
	// TODO. it should be possible to use a plugin here. Initialise plugin object, (which one being specified from GET)  
	// then call on its methods to provide the 3 necessary bits...
	break;
	
default:
	exiterror("Unknown data-visualisation type requested!");
}


/* time now to set up the page! */


echo print_html_header(strip_tags($Corpus->title . ' &ndash; corpus data visualisations'), $Config->css_path);



/* container for the graph area.*/

// TODO use an internal d3 ....

?>
	<script defer src="https://d3js.org/d3.v4.min.js"></script>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable"><?php echo $Corpus->title, ': ', $viz_heading; ?>: </th>
		</tr>
	
		<tr>
	
			<td class="concordgeneral" align="center">
				<div id="graphGoesHere" style="background-color: white">
					<svg width="1200" height="600"></svg><!-- NB. should these come from variables instead? -->
				</div>
			</td>
	
		</tr>
	</table>

<?php 

$do_d3_svg_script();


if (! empty($do_control_block))
	$do_control_block();

//TODO - we need a helplink specifier here!
echo print_html_footer(); 


cqpweb_shutdown_environment();







/* 
 * ================
 * END OF SCRIPT
 * 
 * FUNCTIONS FOLLOW
 * ================
 */





/**
 * Prints to stdout (browser) the JavaScript / D3 code for generating the SVG
 * graph for the set of lexical growth curves specified by the argument array.
 * 
 * @param array $curves  List of curves to show in the graph (by integer ID).
 */
function do_lgcurve_graph($curves)
{
	/* note: we go straight to JavaScript, only moving to PHP to set 
	 * a few things that are best generated serverside.  */
	?>
	<script>

	/*
	 * NB. This is largely based on various elaborations, found online,
	 * of the line-graph tutorial in the "D3 Tips and Tricks" book.
	 */
	
	var title_map = <?php echo print_lgcurve_title_map($curves); ?>;
	
	var color_map = <?php echo print_lgcurve_color_map($curves); ?>;

	/* these two things must match the headings in the data written by the server-side function print_threecol_data() */
	var COL_1 = "types_so_far";
	var COL_2 = "tokens";

	var svg = d3.select("svg");
	var margin = {top: 20, right: 300, bottom: 50, left: 70};

	var width  = +svg.attr("width")  - margin.left - margin.right;
	var height = +svg.attr("height") - margin.top  - margin.bottom;

	var g = svg.append("g").attr("transform", "translate(" + margin.left + "," + margin.top + ")");

	var x = d3.scaleLinear()
		.rangeRound([0, width]);
	var y = d3.scaleLinear()
		.rangeRound([height, 0]);
		
	var line = d3.line()
		.x(function(d) { return x(d[COL_2]); })
		.y(function(d) { return y(d[COL_1]); })
		;


	var data = d3.tsvParse(`<?php echo print_lgcurve_threecol_data($curves); ?>`);

	<?php 
	$max = get_lgcurve_max_types_tokens($curves);
	?>

	/* the x and y domains are set with numeric literals, inserted serverside */
	
	y.domain([0, <?php echo $max->y_max; ?> ]);
	x.domain([0, <?php echo $max->x_max; ?> ]);

	/* "nesting the entries"= turning a three column table into more than one sequence
	 * of pairs of [x,y] to get multiple curves on one graph. */
	var dataNest = d3.nest()
		.key(function(d) {return d.lgcurve_id;})
		.entries(data);


	/* now we have nested data, we loop each of the sequences,
	 * and add a line for each to the SVG. */

	dataNest.forEach(function(d) {


		/* add line to graph */
		g.append("path")
			.attr("class", "line")
			.attr("fill", "none")
			.attr("stroke", color_map[d.key])
			.attr("stroke-linejoin", "round")
			.attr("stroke-linecap", "round")
			.attr("stroke-width", 2)
			.attr("d", line(d.values))
			;

		/* add corpus title to graph */
		g.append("text")
			.attr("transform", "translate("+(width+3)+","+y(d.values[d.values.length-1].types_so_far)+")")
			.attr("dy", ".35em")
			.attr("text-anchor", "start")
			.style("fill", "black")
			.text(title_map[d.key])
			;

	} );



	/* AXIS -- x */
	g.append("g")
		.attr("transform", "translate(0," + height + ")")
		.call(d3.axisBottom(x))
		.append("text")
			.attr("fill", "#000")
			.attr("x", width / 2 )
			.attr("y", margin.top + 20)
			.style("text-anchor", "middle")
			.text("Tokens through the corpus")
			;
	/* AXIS -- y */
	g.append("g")
		.call(d3.axisLeft(y))
		.append("text")
			.attr("fill", "#000")
			.attr("transform", "rotate(-90)")
			.attr("y", 0 - margin.left)
			.attr("x", 0 - (height / 2))
			.attr("dy", "1em")
			.style("text-anchor", "middle")
			.text("Cumulative count of types")
			;

	</script>
	
	<?php
}

/**
 * Prints to stdout (browser) the table of controls for the lexical growth curve
 * (a table with add / remvoe links for all avaialble curves).
 * 
 * @param array $curves  List of curves in the existing graph (by integer ID).
 */
function do_lgcurve_controls($curves)
{
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">Add / remove comparison curves</th>
		</tr>
	
		<tr>
			<th class="concordtable">Corpus</th>
			<th class="concordtable">Annotation</th>
			<th class="concordtable">No. of datapoints</th>
			<th class="concordtable" colspan="2">Actions</th>
		</tr>

	<?php
	
	$corpora_info = get_all_corpora_info();

	foreach (list_all_lgcurves() as $lgc)
	{
		$addlink = '&nbsp;';
		$remlink = '&nbsp;';

		if (in_array($lgc->id, $curves))
		{
			$frag = '';
			foreach($curves as $c)
				if ($c != $lgc->id)
					$frag .= ';' . $c;
			$frag = urlencode(ltrim($frag, ';'));

			if (!empty($frag))
				$remlink = '<a href="dataviz.php?viz=lgc&curves=' . $frag . '" class="menuItem">[Remove from graph]</a>';
		}
		else
		{
			$frag = urlencode(implode(';', $curves) . ';' . $lgc->id );
			$addlink = '<a href="dataviz.php?viz=lgc&curves=' . $frag . '" class="menuItem">[Add to graph]</a>';
		}

		?>
	
		<tr>
			<td class="concordgeneral"><?php echo escape_html($corpora_info[$lgc->corpus]->title); ?></td>
	
			<td class="concordgeneral"><?php echo $lgc->annotation; ?></td>
	
			<td class="concordgeneral"><?php echo number_format($lgc->n_datapoints); ?></td>
	
			<td class="concordgeneral" align="center"><?php echo $addlink; ?></td>
	
			<td class="concordgeneral" align="center"><?php echo $remlink; ?></td>
		</tr>
	
		<?php
	}

	?>
	
	</table>
	
	<?php
}



/**
 * Gets a string containing a JavaScript object literal that maps each curve ID to the 
 * title to be used for that curve on the graph.
 * 
 * @param  array $curves  List of curves (by integer ID); will be used as keys i  the JS object. 
 * @return string         String representation of the JavaScript object, from { to } . 
 */
function print_lgcurve_title_map($curves)
{
	$jsobj = '';
	foreach ($curves as $c)
	{
		$info = get_lgcurve_info($c);
		$corp = get_corpus_info($info->corpus);
		$label = str_replace('\'', '\\\'', $corp->title) . '/' . $info->annotation;
		$jsobj .= ",'$c':'$label'";
	}
	$jsobj[0] = '{';
	$jsobj .= '}';
	return $jsobj;
}


/**
 * Gets a string containing a JavaScript object literal that maps each curve ID to an HTML colour name.
 * 
 * @param  array $curves  List of curves (by integer ID); will be used as keys i  the JS object. 
 * @return string         String representation of the JavaScript object, from { to } . 
 */
function print_lgcurve_color_map($curves)
{
	$colours = array ('steelblue','sienna','slategray','salmon','seagreen', 'olivedrab');
	// TODO more colours - and think harder about what order they are in.

	$jsobj = '';
	
	$i = 0;
	$n = count($colours);
	
	foreach($curves as $c)
	{
		$x = $i % $n;
		$jsobj .= ",'$c':'{$colours[$x]}'";
		$i++;
	}
	$jsobj[0] = '{';
	$jsobj .= '}';
	return $jsobj;
}


/**
 * Gets a string containing the three-column data table used by the D3 script 
 * to render the appropriate lexical growth curves.
 * 
 * @param  array   $idlist  List of the integer IDs of the LG curves to include in the data.
 * @return string           TSV content to be printed to the browser. 
 */
function print_lgcurve_threecol_data($idlist)
{
	$list = implode(',', $idlist);
	$result = do_sql_query("select * from lgcurve_datapoints where lgcurve_id in ($list)");

	$str = "lgcurve_id\ttokens\ttypes_so_far\n";
	while ($o = mysqli_fetch_object($result))
		$str .= $o->lgcurve_id . "\t" . $o->tokens . "\t" . $o->types_so_far . "\n";

	return $str;
}



/**
 * Returns a MySQL object containing the maximum types and maximum tokens 
 * across any of the set of lgcurves in the argument array.
 * 
 * @param  array  $idlist  Array of lgcurve integer IDs.
 * @return object          Database object with two members: 
 *                         y_max (types_so_far) and x_max (tokens).
 */
function get_lgcurve_max_types_tokens($idlist)
{
	$list = implode(',', $idlist);
	return mysqli_fetch_object(do_sql_query("select max(types_so_far) as y_max, max(tokens) as x_max from lgcurve_datapoints where lgcurve_id in ($list)"));
}







