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
 * page scripting the interface for corpus analysis. 
 * 
 * Currently only allows multivariate analysis, but hopefully will allow
 * others later, including custom analysis.
 */

/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');


/* library files */
require('../lib/general-lib.php');
require('../lib/sql-lib.php');
require('../lib/html-lib.php');
require('../lib/useracct-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/cache-lib.php');
require('../lib/query-lib.php');
require('../lib/scope-lib.php');
require('../lib/xml-lib.php');
require('../lib/multivariate-lib.php');
require('../lib/rface.inc.php');
require('../lib/cqp.inc.php');


cqpweb_startup_environment();


// tmp shortcut
if (isset($_GET['correl']))
{
	output_analysis_correlations(NULL, $_GET['correl']);
	goto end_of_this_all;
}



//temp shortcut -- will eventually be a switch on a parameter here
output_analysis_factanal();




end_of_this_all:


/* shutdown and end script */
cqpweb_shutdown_environment();

/*
 * =============
 * END OF SCRIPT
 * =============
 */ 

/** 
 * Support function converting R output lines to an HTML blob
// TODO this whole function is a dirty hack. Get the info bit by bit form the R object instead.
 */
function create_rendered_factanal($lines_from_r)
{
//show_var($lines_from_r);
	for ($i = 0, $n = count($lines_from_r) ; $i < $n ; $i++ )
	{
		switch (trim($lines_from_r[$i]))
		{
		case 'Uniquenesses:':
			$ix_uniqueness_begin = $i + 1;
			break;
		case 'Loadings:':
			$ix_loadings_label = $i;
			break;
		default:
			/* things that need a substring test... */
			if ('Factor1' == substr($lines_from_r[$i], 0, 7))
			{
				if (($i - 1) == $ix_loadings_label)
					$ix_loadings_begin = $i + 1;
				else
				{
					$ix_loadings_end = $i -1;
					$ix_more_loadings_begin = $i + 1;
					$lines_from_r[$i+1] = str_replace('SS loadings', 'Sum-of-Squares&nbsp;loadings', $lines_from_r[$i+1]);
					$lines_from_r[$i+2] = str_replace('Proportion Var', 'Proportion&nbsp;of&nbsp;variance&nbsp;explained', $lines_from_r[$i+2]);
					$lines_from_r[$i+3] = str_replace('Cumulative Var', 'Cumulative&nbsp;variance&nbsp;explained', $lines_from_r[$i+3]);
				}
			}
			else if ('Test of the hypothesis' == substr($lines_from_r[$i], 0, 22))
				$sigtest_html = "<p><strong>{$lines_from_r[$i]}</strong></p><p>{$lines_from_r[$i+1]}</p><p>{$lines_from_r[$i+2]}.</p>";
			else if ('The degrees of freedom for the model is' == substr($lines_from_r[$i], 0, 39))
				$sigtest_html = "<p>{$lines_from_r[$i]}.</p>";
			break; 
		}
	}
	
	
	if (preg_match('|The p-value is (.*?)<|', $sigtest_html, $m))
	{
		$p = (float)$m[1];
		$sigtest_html .= "\n<p>Interpretation: This solution probably <strong>" . ($p <= 0.05 ? 'does' : 'does not') . "</strong> fit the data very well.</p>\n";
	}
	else if (preg_match('/degrees of freedom for the model is (\d+)\b/', $sigtest_html, $m))
		if ('0' == $m[1])
			$sigtest_html .= "\n<p>Because there are 0 degrees of freedom, a significance test could not be performed.</p>\n";
	
	/* build uniquenesses table */
	$colspan = ($ix_loadings_end - $ix_loadings_begin) + 1;
	$unique_html = <<<END

	&nbsp;<br>
	<table class="concordtable" align="center">
		<tr>
			<th class="concordtable" colspan="$colspan">Uniquenesses</th>
		</tr>
		<tr>
			FEATURES_HERE
		</tr>
		<tr>
			UNIQUENESSES_HERE
		</tr>
	</table>
	
END;
	$row_first  = '<td class="concordgeneral">' 
		. preg_replace("|\s+|", '</td><td class="concordgeneral">', $lines_from_r[$ix_uniqueness_begin])
		. "</td>"
		;
	$row_second = '<td class="concordgeneral">' 
		. preg_replace("|\s+|", '</td><td class="concordgeneral">', $lines_from_r[$ix_uniqueness_begin+1])
		. "</td>"
		;
	$unique_html = str_replace('FEATURES_HERE', $row_first, $unique_html);
	$unique_html = str_replace('UNIQUENESSES_HERE', $row_second, $unique_html);
	
	/* build loadings tables */
	$colspan = 8; // TODO - N of factors + 1.
	$loadings_html = <<<END

	<br>&nbsp;<br>
	<table class="concordtable">
		<tr>
			<th class="concordtable" colspan="$colspan">Feature loadings</th>
		</tr>

END;

//dirty hack!
	$lines_from_r[$ix_loadings_begin-1] = ' ' . $lines_from_r[$ix_loadings_begin-1];
	for ($i = $ix_loadings_begin-1 ; $i <= $ix_loadings_end ; $i++)
		$loadings_html .= "\n<tr><td class=\"concordgeneral\">". preg_replace('|\s+|', '</td><td class="concordgeneral">', $lines_from_r[$i]) . "</tr>\n";
	$loadings_html .= <<<END
	</table>
END;
	
	$extra_html = <<<END
	<br>&nbsp;<br>
	<table class="concordtable">
		<tr>
			<th class="concordtable" colspan="$colspan">Factor analysis info</th>
		</tr>
END;
	$lines_from_r[$ix_more_loadings_begin-1] = ' '. $lines_from_r[$ix_more_loadings_begin-1];
	for ($i = $ix_more_loadings_begin-1; $i < $ix_more_loadings_begin + 3 ; $i++)
	{
//		show_var($extra_html);
		$extra_html .= "\n<tr><td class=\"concordgeneral\">". preg_replace('|\s+|', '</td><td class="concordgeneral">', $lines_from_r[$i]) . "</tr>\n";
	}
	$extra_html .= <<<END
	</table>
END;

	
//	$html = $unique_html . $loadings_html . $extra_html . $sigtest_html;
	$html = $extra_html . $sigtest_html . $loadings_html . $unique_html;
	
	return $html;
}

/**
 * User interface function for factor analysis.
 * 
 * TODO an as-text version? For download.
 * TODO tidy up all the HTML.
 * 
 * TODO move to multivariate-interface.inc.php at some point, when there is more than one kind of "analysis"
 */
function output_analysis_factanal()
{
	global $Corpus;
	global $Config;
	

	// work out the minimum number of features needed for factor analysis.
	// Then check the matrix for this number of features and print an error message if absent.
	
// the R code says that (1)_ there must be at leawst 3 variables, (2)_, degree_of_freedom
// has to be more than zero.
// And here is now dof is calcualted:
// 
/*
p <- ncol(cv)   ## cv is the covariance matrix.  So, this is the n of variables. 
dof <- 0.5 * ((p - factors)^2 - p - factors)  ## factors is the n of factors. 

so dof is HALF of nvars-minus-nfacs (squaredm minus itself). 
*/

	/* get matrix info object */
	
	if ( empty($_GET['matrix']) )
		exiterror("No feature matrix was specified for the analysis.");
	if (false === ($matrix = get_feature_matrix((int) $_GET['matrix'])))
		exiterror("The specified feature matrix does not exist on the system.");
	
//	$matrix_vars = feature_matrix_list_variables($matrix->id);
//	$n_matrix_vars = count($matrix_vars);
$n_matrix_vars = feature_matrix_n_of_variables($matrix->id);


	$factor_integers = array(2,3,4,5,6,7);

	


	/* import the matrix to R in raw form */
	$r = new RFace();
//show_var($r);
//$r->set_exit_on_error(true);
	insert_feature_matrix_to_r($r, $matrix->id, 'mydata');
	
	$op = array();
	
//$r->set_debug(true);
	// TODO maybe parameterise the "max number of factors" as an "advanced" option on the query page 

	$skipped = array();

	foreach ($factor_integers as $k=>$i)
	{
		/* work out degrees of freedom for this analysis; if too low, continue. */
		$vars_minus_i = $n_matrix_vars - $i;
		$dof = 0.5 * ( ($vars_minus_i ** 2) - $n_matrix_vars - $i );   //FIXME is this correct ????????? | - n - 1| or | - (n - 1) | ???
//show_var($e="DOF for $i: $dof.");

		if ($dof < 0)
//if (false)
		{
			unset($factor_integers[$k]);
			$skipped[] = $i;
			continue;
		}
		

		// TODO make rotation type an "advanced" option on the query page 
		// (advanced options to be hidden behind a JavaScript button of course)


		/* check for false, which here usually means that the factanal() function has complained. */
		if (false === $r->execute("out = factanal(mydata, $i, rotation=\"varimax\")"))
		{
		//	$errinfo = $r->error_message();
			/* we need ot skip this integer in any case. If the exectuion was halted, we need to skip the rest too. */
			if ($r->check_execution_halted())
			{
// $op[$i]="DOF == $dof";
				++$k;
				while (isset($factor_integers[$k]))
				{
					$skipped[] = $factor_integers[$k];
					unset($factor_integers[$k++]);
				}
			}
			break;
		}
		// TODO arguments to pritn - do I want them to be thus?
		// digits = 2 probably correct, but sort=TRUE??????
		$op[$i] = create_rendered_factanal($r->execute("print(out, cutoff = 0, digits = 2, sort = TRUE)"));
	}



	/* ready to render */
	
	echo print_html_header($Corpus->title, $Config->css_path, array('analyse-md'));
	
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Analyse Corpus: Multidimensional Analysis of Feature Matrix
				&ldquo;<?php echo $matrix->savename; ?>&rdquo; 
			</th>
		</tr>
		<tr>
			<td class="concorderror">
				&nbsp;<br>
				<strong>This function is currently under development</strong>. 
				The statistical output is displayed, but not in fully cleaned-up form.
				Solutions for numbers of factors between 2 and 7 are sought.
				<br>&nbsp;<br>
				The analysis has been run for the following numbers of factors:
				<?php echo empty($factor_integers) ? 'NONE' : implode(', ', $factor_integers); ?>.
				<br>&nbsp;<br>
				<?php
				if (!empty($skipped))
				{
					echo 'Analyses where the degrees-of-freedom would be too low have been skipped. This was the case for the following analyses: ';
					foreach($skipped as $k => $sk)
						echo (0==$k?' ':'; '),  "$sk factors";
					echo ".\n";
				}
				?>
				<br>&nbsp;
			</td>
		</tr>

		<?php
		if (!empty($op))
		{
			?>

			<tr>
				<td class="concordgeneral">
					&nbsp;<br>
					Use the buttons below to display different solutions.
					<br>&nbsp;<br>

					<?php
					foreach($op as $i => $solution)
						echo '<button id="solButton', $i, '" type="button">Show ', $i, '&ndash;factor solution</button>';
					?> 
					<br>&nbsp;<br>
				</td>
			</tr>

			<?php
		}
		?>

	</table>


	<?php
	
	foreach($op as $i => $solution)
	{
		// TODO - evenutally, solution will become an stdClass whose members correspond
		// to those of the R object (or, at least, which use regexen to slice up the print() output.)
		//
		// We can then insert formatting around and between the different bits (e.g. to render the tables
		// as actual HTML tables.
		echo "\n\t<table id=\"solution$i\" class=\"concordtable fullwidth\">"
			, "\n\t\t<tr>"
			, "\n\t\t\t<th class=\"concordtable\">"
			, "Factor Analysis Output for $i factors</th>"
			, "\n\t\t</tr>\n\t\t<tr>"
			, "\n\t\t\t<td class=\"concordgeneral\">"
//			, "\n<pre>"
			, $solution
//			, "\n</pre>
			, "\n\t\t\t</td>\n\t\t</tr>"
			, "\n\t</table>\n"
			;
	}

	echo print_html_footer('hello');
}













function output_analysis_correlations($m_id, $correlation_feature)
{
	global $Config;
	global $Corpus;

	/* get matrix info object */ // TOOD, factor out, and  pass in as $mx_id to these varioius funcitons.
	
	if ( empty($_GET['matrix']) )
		exiterror("No feature matrix was specified for the analysis.");
	if (false === ($matrix = get_feature_matrix((int) $_GET['matrix'])))
		exiterror("The specified feature matrix does not exist on the system.");
	
	$matrix_vars = feature_matrix_list_variables($matrix->id);
// it is confusing that list_ returns an array of objects instead of a flat array of the labels.
// see shenanigans below. 
// TODO have a different func for the objexcts? 


//	if (!in_array($correlation_feature, $matrix_vars))
	$found = false;
	foreach($matrix_vars as $obj)
		if ($obj->label == $correlation_feature)
			$found = true;
	if (!$found)
		exiterror("The specified feature does not exist in the matrix.");

	$c_var = $correlation_feature;
	
	/* ready to render */
	
	echo print_html_header($Corpus->title, $Config->css_path, array('modal'));
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">
				Analyse Corpus: Examining correlations for variable "<?php echo $c_var; ?>"</th>
		</tr>

		<tr>
			<th class="concordtable">... against variable</th>
			<th class="concordtable">Pearson coefficient</th>
			<th class="concordtable">Confidence interval</th>
			<th class="concordtable">R-squared</th>
			<th class="concordtable">Significance test outcomes</th>
		</tr>

	<?php


	/* import the matrix to R in raw form */
	$r = new RFace();

	insert_feature_matrix_to_r($r, $matrix->id, 'mydata');

	$c_vec = 'mydata$'.$c_var;

	foreach ($matrix_vars as $obj)
	{
		$var = $obj->label;

		if ($var == $c_var)
			continue;
		$vec = 'mydata$'.$var;

// old version - now superseded
// 		$coeff = $r->read_execute("cor($c_vec, $vec, method=\"pearson\")", 'solo');
		// don'#t actually need ot do this. cor.test() produces "sample estimates" of cor() .... it's $estimate member ofthe object.
		// we can use test.result$estimate to get what the above produces.

// 		if (false === $coeff)
// 			$coeff = '<pre>'.$r->error_message().'</pre>';
// 		else
// 			$r2 = $coeff ** 2;

// 		$test_data = $r->read_execute("cor.test($c_vec, $vec, method=\"pearson\")", 'verbatim'); 
		
		$name = $r->new_object_name();	
		
// $a = $r->execute("$name <- cor.test($c_vec, $vec, method=\"pearson\")"); show_var($a);//debug

		if (false === $r->execute("$name <- cor.test($c_vec, $vec, method=\"pearson\")"))
			exiterror($r->error_message());
		else 
		{
			/* The cor.test function returns a complex object,
			 * some of whose members are one-member lists, some are vectors, etc.
			 * Code below extracts all bits and pieces. 
			 * See R doc on cor.test for explanation.
			 */

			$coeff = $r->read_execute("$name\$estimate[[\"cor\"]]", 'solo');
			$r2 = $coeff ** 2;

			$t = $r->read_execute("$name\$statistic[[\"t\"]]", 'solo');
			$df = $r->read_execute("$name\$parameter[[\"df\"]]", 'solo');
			$p = $r->read_execute("$name\$p.value", 'solo');

			$ci_lower = $r->read_execute("$name\$conf.int[1]", 'solo');
			$ci_upper = $r->read_execute("$name\$conf.int[2]", 'solo');
			$ci_level = (int)(100 * $r->read_execute("attributes($name\$conf.int)[[\"conf.level\"]]", 'solo'));
		}

		$r->drop_object($name);

		?>

		<tr>
			<td class="concordgeneral"><?php echo $var;    ?></td>
			<td class="concordgeneral"><?php echo $coeff;  ?></td>
			<td class="concordgeneral"><?php echo $ci_lower, '&nbsp;&#x27f7;&nbsp;', $ci_upper, '&nbsp;(', $ci_level, '%)'; ?></td>
			<td class="concordgeneral"><?php echo number_format($r2, 7); ?></td>
			<td class="concordgeneral">
				<?php echo "t = $t, df = $df, p-value = $p\n"; ?>
				<?php //TODO echo "t = $t, df = $df, p-value = ", number_format($p,7), "\n"; ?>
				<!--
				NOTE: for the above, letting p print normally can result in am E-X type of number.
				using number_format() then helps... but otherwise it doesn't. 7) commented above. 
				See example w/number_Format(, 
				How's about this: stringize $p; if it ends in E-10 or whatever, then convert to "x 10 <supr>-10</supr>
				Yes, do that. TODO tho.
				 -->
			</td>
		</tr>

		<?php
	}
	?>

	</table>

	<?php

	echo print_html_footer('hello');

}

