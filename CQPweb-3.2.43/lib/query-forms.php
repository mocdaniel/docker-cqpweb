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
 * This file contains the user interfaces for each of the primary corpus query entry points.  
 * Most of these functions (as in all "indexforms" files) print a table for the right-hand side interface.
 * Some are support functions, providing reusable chunks of HTML.
 */


/**
 * Builds and returns an HTML string containing the search-box and associated UI elements 
 * used in the Standard and Restricted Query forms. 
 * 
 * @param  string $form_id                 ID of the form these inputs will be inserted into.
 * @param  string $qstring                 A search pattern that will be inserted into the query textbox Or an empty value.
 * @param  string $qmode                   The query-mode to pre-set in the query control. Or an empty value.
 * @param  int    $qsubcorpus              Integer ID of pre-selected subcorpus. Only works if $show_mini_restrictions is true. 
 * @param  bool   $show_mini_restrictions  Set to true if you want the "simple restriction" control for Standard Query.
 * @return string                          The assembled HTML.
 */
function print_search_box($form_id, $qstring, $qmode, $qsubcorpus, $show_mini_restrictions)
{
	global $Config;
	global $Corpus;
	global $User;
	
	/* GET VARIABLES READY: form attribute, if needed */
	$form_att = empty($form_id) ? '' : " form=\"$form_id\"";
	
	/* GET VARIABLES READY: contents of query box */
	$qstring = ( ! empty($qstring) ? escape_html(prepare_query_string($qstring)) : '' );
	
	if ($Config->show_match_strategy_switcher)
	{
		if (preg_match('/^\(\?\s*(\w+)\s*\)\s*/', $qstring, $m))
		{
			if (in_array($m[1], array('traditional', 'shortest', 'longest')))
				$strategy_insert = $m[1];
			else if ('standard' == $m[1])
				$strategy_insert = '0';
			$qstring = preg_replace('/^'.preg_quote($m[0], '/').'/', '', $qstring);
		}
		else
			$strategy_insert = '0';
	}
	

	/* GET VARIABLES READY: the query mode. */
	if (! array_key_exists($qmode, $Config->query_mode_map) )
		$qmode = ($Corpus->uses_case_sensitivity ? 'sq_case' : 'sq_nocase');
		/* includes NULL, empty */
	
	$mode_options = '';
	foreach ($Config->query_mode_map as $mode => $modedesc)
		$mode_options .= "\n\t\t\t\t\t\t\t<option value=\"$mode\"" . ($qmode == $mode ? ' selected' : '') . ">$modedesc</option>";

	
	/* GET VARIABLES READY: hidden attribute help */
	$style_display = ('cqp' != $qmode ? "display: none" : '');
// 	$mode_js       = ('cqp' != $qmode ? 'onChange="if ($(\'#qmode\').val()==\'cqp\') $(\'#searchBoxAttributeInfo\').slideDown();"' : '');
	$mode_js       = ('cqp' != $qmode ? 'onChange="if ($(\'#qmode\').val()==\'cqp\') { $(\'#searchBoxAttributeInfo\').slideDown(); $(\'#searchBox\').off(\'keypress\');}"' : '');
	
	$p_atts = "\n";
	foreach(get_all_annotation_info($Corpus->name) as $p)
	{
		$p->tagset = escape_html($p->tagset);
		$p->description = escape_html($p->description);
		$tagset = (empty($p->tagset) ? '' : "(using {$p->tagset})");
		$p_atts .= "\t\t\t<tr>\t<td><code>{$p->handle}</code></td>\t<td>{$p->description}$tagset</td>\t</tr>\n";
	}
 	
	$s_atts = "\n";
	foreach(list_xml_all($Corpus->name) as $s=>$s_desc)
		$s_atts .= "\t\t\t\t\t<tr>\t<td><code>&lt;{$s}&gt;</code></td>\t<td>" . escape_html($s_desc) . "</td>\t</tr>\n";
	if ($s_atts == "\n")
		$s_atts = "\n<tr>\t<td colspan='2'><code>None.</code></td>\t</tr>\n";

	/* and, while we do the a-atts, simultaneously,  GET VARIABLES READY: aligned corpus display */
	$a_atts = "\n";
	$align_options = '';
	foreach(check_alignment_permissions(list_corpus_alignments($Corpus->name)) as $a=>$a_desc)
	{
		$a_atts .= "\t\t\t\t\t<tr>\t<td><code>&lt;{$a}&gt;</code></td>\t<td>" . escape_html($a_desc) . "</td>\t</tr>\n";
		$align_options .= "\n\t\t\t\t\t\t\t<option value=\"$a\">Show text from parallel corpus &ldquo;" . escape_html($a_desc) . "&rdquo;</option>";
	}
	if ($a_atts == "\n")
		$a_atts = "\n<tr>\t<td colspan='2'><code>None.</code></td>\t</tr>\n";
	/* we do this for a-atts but not p/s-atts because there is always at least word and at least text/text_id */



	/* GET VARIABLES READY: hits per page select */
	$pp_options = '';
	foreach (array (10,50, 100, 250, 350, 500, 1000) as $val)
		$pp_options .= "\n\t\t\t\t\t\t\t<option value=\"$val\""
			. ($Config->default_per_page == $val ? ' selected' : '')
			. ">$val</option>"
			;

	if ($User->is_admin())
		$pp_options .=  "\n\t\t\t\t\t\t\t<option value=\"all\">show all</option>";



	/* ASSEMBLE ALIGNMENT DISPLAY CONTROL */
	if (empty($align_options))
		$parallel_html = '';
	else
		$parallel_html = <<<END_PARALLEL_ROW

				<tr>
					<td class="basicbox">Display alignment:</td>
					<td class="basicbox">
						<select$form_att name="showAlign">
							<option value="~~none" selected>Do not show aligned text in parallel corpus</option>
							$align_options
						</select>
					</td>
				</tr>

END_PARALLEL_ROW;

	
	
	/* ASSEMBLE MATCH STRATEGY SWITCHER */
	
	/* NB, this relies on having a version of the CWB core more recent than 2017-07-01;
	 * when CQPweb reaches v 3.5 and demands CWB v 3.5, this can be made non-conditional */ 
	if (!$Config->show_match_strategy_switcher)
		$strategy_html = '';
	else
	{
		$select_standard    = ( $strategy_insert == '0'           ? ' selected' : '' );
		$select_longest     = ( $strategy_insert == 'longest'     ? ' selected' : '' );
		$select_shortest    = ( $strategy_insert == 'shortest'    ? ' selected' : '' );
		$select_traditional = ( $strategy_insert == 'traditional' ? ' selected' : '' );
		
		$strategy_html = <<<END_STRATEGY_ROW

				<tr>
					<td class="basicbox">Match strategy:</td>
					<td class="basicbox">
						<select$form_att name="qstrategy">
							<option value="0"$select_standard>Standard</option>
							<!-- Note that because "standard" is normal, we do not actually want
							     to use a flag for it (or all queries would end up with a modifier!) -->
							<option value="longest"$select_longest>Longest-possible match</option>
							<option value="shortest"$select_shortest>Shortest-possible match</option>
							<option value="traditional"$select_traditional>Old-style CQP matching</option>
						</select>
					</td>
				</tr>
	
END_STRATEGY_ROW;
	}
		
	


	

	/* ASSEMBLE THE RESTRICTIONS MINI-CONTROL TOOL */
	if ($show_mini_restrictions)
		$restriction_dropdown = print_mini_restriction_search_tool($form_id, $qsubcorpus);
	else
		$restriction_dropdown = '';


	/* ALL DONE: so assemble the HTML from the above variables && return it. */

	return <<<END_OF_HTML


			&nbsp;<br>

			<textarea
				$form_att
				id="searchBox"
				name="theData" 
				rows="5" 
				cols="65" 
				style="font-size: 16px"  
				spellcheck="false" 
				autofocus
			>$qstring</textarea>

			&nbsp;<br>
			&nbsp;<br>


			<table>
				<tr>
					<td class="basicbox">Query mode:</td>

					<td class="basicbox">
						<select$form_att id="qmode" name="qmode" $mode_js>
							$mode_options
						</select>
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<a id="ceqlManLink" class="hasToolTip" target="_blank" href="../doc/cqpweb-simple-syntax-help.pdf"
							data-tooltip="How to compose a search using the Simple Query language">
							Simple query language syntax
						</a>
					</td>
				</tr>

				<tr>
					<td class="basicbox">Number of hits per page:</td>
					<td class="basicbox">
						<select$form_att name="pp">
							<option value="count">count hits</option>

							$pp_options

						</select>
					</td>
				</tr>


				$parallel_html


				$strategy_html



				<tr>
					<td class="basicbox">Restriction:</td>
					<td class="basicbox">
						$restriction_dropdown
					</td>
				</tr>

				<tr>
					<td class="basicbox">&nbsp;</td>
					<td class="basicbox">
						<input$form_att type="submit" value="Start Query">
						<input$form_att type="reset" value="Reset Query">
					</td>
				</tr>
			</table>

			<div id="searchBoxAttributeInfo" style="$style_display">
				<table>
					<tr>
						<td colspan="2"><b>P-attributes in this corpus:</b></td>
					</tr>
					<tr>
						<td width="40%"><code>word</code></td>
						<td><p>Main word-token attribute</p></td>
					</tr>

					$p_atts

					<tr>
						<td colspan="2">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="2"><b>S-attributes in this corpus:</b></td>
					</tr>

					$s_atts

					<tr>
						<td colspan="2">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="2"><b>A-attributes in this corpus:</b></td>
					</tr>

					$a_atts

				</table>
				<p>
					<a id="CqpTutorialLink" class="hasToolTip" target="_blank" href="http://cwb.sourceforge.net/files/CQP_Tutorial/"
						data-tooltip="Detailed help on CQP syntax">
						Click here to open the full CQP-syntax tutorial
					</a>
				</p>
			</div>


END_OF_HTML;

}


/**
 * Builds and returns an HTML string containing a short, compressed run-a-query form
 * with many options from the main Standard/Restricted query missing. 
 * 
 * @param  string $form_id       ID to give to the form.
 * @param  string $qsubcorpus    Integer ID of pre-selected subcorpus. 
 * @return string                The assembled HTML (sequence of 3 td's).
 */
function print_mini_search_box($form_id = '', $qsubcorpus = NULL)
{
	global $Config;
	global $Corpus;
// qsubcorpus: might we want to have all new queries run in a particular subcorpus/restruicitonm?

	/* GET VARIABLES READY: the query mode. */
	$qmode = ($Corpus->uses_case_sensitivity ? 'sq_case' : 'sq_nocase');

	$mode_options = '';
	foreach ($Config->query_mode_map as $mode => $modedesc)
		$mode_options .= "\n\t\t\t\t\t\t\t<option value=\"$mode\"" . ($qmode == $mode ? ' selected' : '') . ">$modedesc</option>";

	/* ASSEMBLE THE RESTRICTIONS MINI-CONTROL TOOL */
	$restriction_dropdown = print_mini_restriction_search_tool($form_id, $qsubcorpus);

	$id_att   = empty($form_id) ? '' : " id=\"$form_id\"";
	$form_att = empty($form_id) ? '' : " form=\"$form_id\"";

	return <<<END_OF_HTML
		
			<td class="concordgeneral">
				<form$id_att></form>
				<input$form_att type="submit" value="Run Query">
				<input$form_att type="text" name="theData" placeholder="Enter query">
			</td>
			<td class="concordgeneral">
				Query mode:
				<select$form_att name="qmode">
					$mode_options
				</select>
			</td>
			<td class="concordgeneral">
				Restriction:
				$restriction_dropdown
			</td>

END_OF_HTML;

}




/**
 * Create the HTML for a "restriction" dropdown in a search form.
 * 
 * @param  string $form_id                  ID of form these will be added to.
 * @param  int    $subcorpus_to_preselect   Integer subcorpus ID; if specified, 
 *                                          that subcorpus will be preselected. 
 * @return string                           HTML of the mini restriction dropdown.
 */
function print_mini_restriction_search_tool($form_id = '', $subcorpus_to_preselect = NULL)
{
	global $Corpus;
	global $User;
	
	/* create options for the Primary Classification */
	/* first option is always whole corpus */
	$restrict_options = "\n\t\t\t\t\t\t\t<option value=\"\"" 
		. ( is_null($subcorpus_to_preselect) ? ' selected' : '' )
		. '>None (search whole corpus)</option>'
		;
		
	$field = $Corpus->primary_classification_field;
	foreach (list_text_metadata_category_descriptions($Corpus->name, $field) as $h => $c)
		$restrict_options .= "\n\t\t\t\t\t\t\t<option value=\"-|$field~$h\">".(empty($c) ? $h : escape_html($c))."</option>";
	
	/* list the user's subcorpora for this corpus, including the last set of restrictions used */
	
	$result = do_sql_query("select * from saved_subcorpora where corpus = '{$Corpus->name}' and user = '{$User->username}' order by name");
	
	while ($sc = Subcorpus::new_from_db_result($result))
		if ($sc->name == '--last_restrictions')
			$restrict_options .= "<option value=\"--last_restrictions\">Last restrictions ("
				. $sc->print_size_tokens() . ' words in ' 
				. $sc->print_size_items()  . ')</option>'
				;
		else
			$restrict_options .= "\n\t\t\t\t\t\t\t<option value=\"~sc~{$sc->id}\""
				. ($subcorpus_to_preselect == $sc->id ? ' selected' : '')
				. '>Subcorpus: ' . $sc->name . ' ('
				. $sc->print_size_tokens() . ' words in ' 
				. $sc->print_size_items()  . ')</option>'
				;
	
	/* we now have all the subcorpus/restrictions options, so assemble the HTML */
	
	$form_att = empty($form_id) ? '' : " form=\"$form_id\"";
	
	return <<<END_RESTRICT_DROPDOWN

					<input$form_att type="hidden" name="del" size="-1" value="begin">
					<select$form_att name="t">
						$restrict_options
					</select>
					<input$form_att type="hidden" name="del" size="-1" value="end">

END_RESTRICT_DROPDOWN;

}





function do_ui_search()
{
	/* most of the hard work of this function is done by the inner "print search box" function
	 * and thisd function merely wraps it, yanks vars from GET, and begins/ends the form. */


	?>
	<table class="concordtable fullwidth">

		<tr>
			<th class="concordtable">Standard Query</th>
		</tr>
	
		<tr>
			<td class="concordgeneral">
	
				<form class="runQueryForm" action="concordance.php" accept-charset="UTF-8" method="get"> 
	
					<?php
					echo print_search_box(
						NULL,
						isset($_GET['insertString'])    ? $_GET['insertString']    : NULL,
						isset($_GET['insertType'])      ? $_GET['insertType']      : NULL,
						isset($_GET['insertSubcorpus']) ? $_GET['insertSubcorpus'] : NULL,
						true
					);
					?>
	
				</form>
			</td>
		</tr>

	</table>
	<?php
}




function do_ui_restricted()
{
	/* insert restrictions as checked tickboxes lower down */
// 	$checkarray = array();
// 	if (isset($_GET['insertRestrictions']))
// 	{
// 		/* note that, counter to what one might expect, the parameter here is given as a serialisation, not URL-format */
// 		if (false === ($restriction = Restriction::new_by_unserialise($_GET['insertRestrictions'])))
// 			/* it can't be read: so don't populate $checkarray. */
// 			;
// 		else
// 			foreach ($restriction->get_form_check_pairs() as $pair)
// 				$checkarray[$pair[0]][$pair[1]] = 'checked ';
// // old method:
// // 		preg_match_all('/\W+(\w+)=\W+(\w+)\W/', $_GET['insertRestrictions'], $matches, PREG_SET_ORDER);
// // 		foreach($matches as $m)
// // 			$checkarray[$m[1]][$m[2]] = 'checked ';
// 	}
	if (isset($_GET['insertRestrictions']))
		$insert_r = Restriction::new_by_unserialise($_GET['insertRestrictions']);
	else
		$insert_r = NULL;

	?>

	<form class="runQueryForm" action="concordance.php" accept-charset="UTF-8" method="get"> 
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="3">Restricted Query</th>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="3">

					<?php
					echo print_search_box(
						NULL,
						isset($_GET['insertString']) ? $_GET['insertString']  : NULL,
						isset($_GET['insertType'])   ? $_GET['insertType']    : NULL,
						NULL,
						false
					);
					?>

				</td>
			</tr>

			<?php
			echo print_restriction_block($insert_r, 'query');
			?>

		</table>
	</form>

	<?php
}





/**
 * This provides the metadata restrictions block that is used for queries and for subcorpora.
 * 
 * @param  Restriction $insert_restriction       If not empty, contains a Restriction to be rendered in the form.
 * @param  string      $thing_to_produce         String labelling the thing the form will produce: "query", "subcorpus"
 * @param  string      $form_id                  If set, inputs will be given a form="THIS" attriburte. 
 * @return string                                HTML of the restriction block.
 */
function print_restriction_block($insert_restriction, $thing_to_produce, $form_id = false)
{
	global $Corpus;

	$block = '
		<tr>
			<th colspan="3" class="concordtable">
				Select the text-type restrictions for your '. $thing_to_produce . ':
			</th>
		</tr>
		';
	
	$fid_string = $form_id ? " form=\"$form_id\"" : '';


	/* TEXT METADATA */

	/* get a list of classifications and categories from mysql; print them here as tickboxes */

	$block .= '<tr><input' . $fid_string . ' type="hidden" name="del" size="-1" value="begin">';

	$classifications = list_text_metadata_classifications($Corpus->name);

	$header_row = array();
	$body_row = array();
	$i = 0;

	foreach ($classifications as $c_handle => $c_desc)
	{
		$header_row[$i] = '<td width="33%" class="concordgrey" align="center">' .escape_html($c_desc) . '</td>';
		$body_row[$i] = '<td class="concordgeneral" valign="top" nowrap>';

		$catlist = list_text_metadata_category_descriptions($Corpus->name, $c_handle);

		foreach ($catlist as $handle => $desc)
		{
			$input_id = 'R: ' . $c_handle . ':' . $handle;
			$t_value = '-|' . $c_handle . '~' . $handle;
			$check = ( ( $insert_restriction && $insert_restriction->form_t_value_is_activated($t_value) ) ? 'checked ' : '');
			$body_row[$i] .= 
				"\n<input$fid_string id=\"$input_id\"" . ' type="checkbox" name="t" value="' . $t_value . '" ' . $check 
				. '> <label for="' . $input_id . '">' . ($desc == '' ? $handle : escape_html($desc)) . '</label><br>';
		}


		$body_row[$i] .= "\n&nbsp;</td>";

		$i++;
		/* print three columns at a time */
		if ( $i == 3 )
		{
			$block .= 
				$header_row[0] . $header_row[1] . $header_row[2] . "</tr>\n\t\t\t\t<tr>"
				. $body_row[0] . $body_row[1] . $body_row[2] . "</tr>\n\t\t\t\t<tr>"
				;
			$i = 0;
		}
	}

	if ($i > 0) /* not all cells printed */
	{
		while ($i < 3)
		{
			$header_row[$i] = '<td class="concordgrey" align="center">&nbsp;</td>';
			$body_row[$i] = '<td class="concordgeneral">&nbsp;</td>';
			$i++;
		}
		$block .= $header_row[0] . $header_row[1] . $header_row[2] . '</tr>
			<tr>
			' . $body_row[0] . $body_row[1] . $body_row[2] . '</tr>
			<tr>
			';
	}


	if (empty($classifications))
		$block .= <<<END_ROW
			<tr><td colspan="3" class="concordgrey" align="center">
				<p class="spacer">&nbsp;</p>
				<p>There are no text classification schemes set up for this corpus.</p>
				<p class="spacer">&nbsp;</p>
			</td></tr>
END_ROW;


	$classification_elements_matrix = array();
	$idlink_elements_matrix = array();

	$xml = get_all_xml_info($Corpus->name);


	foreach ($xml as $x)
		if ($x->datatype == METADATA_TYPE_NONE)
			$classification_elements_matrix[$x->handle] = array();

	foreach ($xml as $x)
	{
		if ($x->datatype == METADATA_TYPE_CLASSIFICATION)
			$classification_elements_matrix[$x->att_family][] = $x->handle;
		else if ($x->datatype == METADATA_TYPE_IDLINK)
		{
			foreach (get_all_idlink_field_info($Corpus->name, $x->handle) as $k=> $field)
				if ($field->datatype == METADATA_TYPE_CLASSIFICATION)
					$idlink_elements_matrix[$x->handle][$k] = $field;
		}
	}

	foreach($classification_elements_matrix as $k=>$c)
		if (empty($c))
			unset($classification_elements_matrix[$k]);

	/* we now know which elements we need a display for. */

	foreach ($classification_elements_matrix as $el => $class_atts)
	{
		/* We have already done <text>-level, above. Don't allow <text> to be a sub-text element. */
		if ('text' == $el)
			continue;

		$block .= <<<END_HTML
			<tr>
				<th colspan="3" class="concordtable">
					Select sub-text restrictions for your $thing_to_produce -- for <em>{$xml[$el]->description}</em> regions:
				</th>
			</tr>
END_HTML;

		$header_row = array();
		$body_row = array();
		$i = 0;

		foreach($class_atts as $c)
		{
			$header_row[$i] = '<td width="33%" class="concordgrey" align="center">' . $xml[$c]->description . '</td>';
			$body_row[$i] = '<td class="concordgeneral" valign="top" nowrap>';

			$catlist = xml_category_listdescs($Corpus->name, $c);

			$t_base_c = preg_replace("/^{$el}_/",  '', $c);

			foreach ($catlist as $handle => $desc)
			{
				$input_id = 'RXC: ' . $el . ':' . $t_base_c . ':' . $handle;
				$t_value = $el . '|'. $t_base_c . '~' . $handle;
				$check = ( ( $insert_restriction && $insert_restriction->form_t_value_is_activated($t_value) ) ? 'checked ' : '');
				$body_row[$i] .= 
					"\n<input$fid_string id=\"$input_id\"" . ' type="checkbox" name="t" value="' . $t_value . '" ' . $check 
					. '> <label for="' . $input_id . '">' . ($desc == '' ? $handle : escape_html($desc)) . '</label><br>';
			}

			/* whitespace is gratuitous for readability */
			$body_row[$i] .= '
				&nbsp;
				</td>';

			$i++;
			/* print three columns at a time */
			if ( $i == 3 )
			{
				$block .= $header_row[0] . $header_row[1] . $header_row[2] . '</tr>
				<tr>
				' . $body_row[0] . $body_row[1] . $body_row[2] . '</tr>
				<tr>
				';
				$i = 0;
			}
		}

		if ($i > 0) /* not all cells printed */
		{
			while ($i < 3)
			{
				$header_row[$i] = '<td class="concordgrey" align="center">&nbsp;</td>';
				$body_row[$i] = '<td class="concordgeneral">&nbsp;</td>';
				$i++;
			}
			$block .= $header_row[0] . $header_row[1] . $header_row[2] . '</tr>
			<tr>
			' . $body_row[0] . $body_row[1] . $body_row[2] . '</tr>
			<tr>
			';
		}
	}
	
	//TODO we also need xml-within. RXW for XML within IDs!

	//TODO
	// a lot of stuff is now repeated 3 times, for text metadata, xml classification, and idlink classifications. Look at factoring some of it out. 



	foreach ($idlink_elements_matrix as $el => $idlink_classifications)
	{
		$block .= <<<END_HTML
			<tr>
				<th colspan="3" class="concordtable">
					Select restrictions on <em>{$xml[$el]->description}</em> 
					for your $thing_to_produce -- affects <em>{$xml[$xml[$el]->att_family]->description}</em> regions:
				</th>
			</tr>
END_HTML;

		$header_row = array();
		$body_row = array();
		$i = 0;

		foreach ($idlink_classifications as $field_h => $field_o)
		{
			$header_row[$i] = '<td width="33%" class="concordgrey" align="center">' . $field_o->description . '</td>';
			$body_row[$i] = '<td class="concordgeneral" valign="top" nowrap>';

			$catlist = idlink_category_listdescs($Corpus->name, $field_o->att_handle, $field_h);

			$t_base = preg_replace("/^{$xml[$el]->att_family}_/",  '', $el) . '/' . $field_h;

			foreach ($catlist as $handle => $desc)
			{
				$input_id = 'RID: ' . $el . ':' . $field_h . ':' . $handle;
				$t_value = $xml[$el]->att_family . '|'. $t_base . '~' . $handle;
				$check = ( ( $insert_restriction && $insert_restriction->form_t_value_is_activated($t_value) ) ? 'checked ' : '');
				$body_row[$i] .= 
					"\n<input$fid_string id=\"$input_id\"" . ' type="checkbox" name="t" value="' . $t_value . '" ' . $check 
					. '> <label for="' . $input_id . '">'  . ($desc == '' ? $handle : escape_html($desc)) . '</label><br>';
			}

			/* whitespace is gratuitous for readability */
			$body_row[$i] .= '
				&nbsp;
				</td>';

			$i++;
			/* print three columns at a time */
			if ( $i == 3 )
			{
				$block .= $header_row[0] . $header_row[1] . $header_row[2] . '</tr>
				<tr>
				' . $body_row[0] . $body_row[1] . $body_row[2] . '</tr>
				<tr>
				';
				$i = 0;
			}
		}

		if ($i > 0) /* not all cells printed */
		{
			while ($i < 3)
			{
				$header_row[$i] = '<td class="concordgrey" align="center">&nbsp;</td>';
				$body_row[$i] = '<td class="concordgeneral">&nbsp;</td>';
				$i++;
			}
			$block .= $header_row[0] . $header_row[1] . $header_row[2] . '</tr>
			<tr>
			' . $body_row[0] . $body_row[1] . $body_row[2] . '</tr>
			<tr>
			';
		}
	}

	$block .= '</tr>
		<input' . $fid_string . ' type="hidden" name="del" size="-1" value="end">
		';

	return $block;
}





function do_ui_lookup()
{
	/* much of this is the same as the form for freq list, but simpler */

	/* do we want to allow an option for "showing both words and tags"? */
	global $Corpus;

	$annotation_available = ! empty($Corpus->primary_annotation);

?>

<form class="runQueryForm" action="concordance.php" method="get">
	<input type="hidden" name="program" value="lookup">
	<?php if (!$annotation_available) { echo '<input type="hidden" name="lookupShowWithTags" value="0">' ;} echo "\n"; ?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">Word lookup</th>
		</tr>

		<tr>
			<td class="concordgrey" colspan="2">
				&nbsp;<br>
				You can use this search to find out how many words matching the form 
				that you look up occur in the corpus, and the different tags that they have.
				<br>&nbsp;
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">Enter the word-form you want to look up</td>
			<td class="concordgeneral">
				<input type="text" name="theData" size="32">
				<br>
				<em>(N.B. you can use the normal wild-cards of Simple Query language)</em>
				<input type="hidden" name="qmode" value="<?php echo $Corpus->uses_case_sensitivity ? 'sq_case' : 'sq_nocase' ; ?>">
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">Show only words ...</td>
			<td class="concordgeneral">
				<table>
					<tr>
						<td class="basicbox">
							<p style="white-space: nowrap">
								<input type="radio" id="lookupType:begin" name="lookupType" value="begin" checked>
								<label for="lookupType:begin">starting&nbsp;with</label>
							</p>
							<p style="white-space: nowrap">
								<input type="radio" id="lookupType:end" name="lookupType" value="end">
								<label for="lookupType:end">ending&nbsp;with</label>
							</p>
							<p style="white-space: nowrap">
								<input type="radio" id="lookupType:contain" name="lookupType" value="contain">
								<label for="lookupType:contain">containing</label>
							</p>
							<p style="white-space: nowrap">
								<input type="radio" id="lookupType:exact" name="lookupType" value="exact">
								<label for="lookupType:exact">matching&nbsp;exactly</label>
							</p>
						</td>
						<td class="basicbox" style="vertical-align:middle">
							... the pattern you specified
						</td>
					</tr>
				</table>
			</td>
		</tr>
		
		<?php
		if ($annotation_available)
		{
			?>

			<tr>
				<td class="concordgeneral">List results by word-form, or by word-form AND tag?</td>
				<td class="concordgeneral">
					<select name="lookupShowWithTags">
						<option value="1" selected>List by word-form and tag</option>
						<option value="0">Just list by word-form</option>
					</select>
				</td>
			</tr>

			<?php
		}
		?>
		
		<tr>
			<td class="concordgeneral">Number of items shown per page:</td>
			<td class="concordgeneral">
			
			<?php /* TODO set the "selected" on the basis of which one matches the default */ ?>
			
				<select name="pp">
					<option>10</option>
					<option selected>50</option>
					<option>100</option>
					<option>250</option>
					<option>350</option>
					<option>500</option>
					<option>1000</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				&nbsp;<br>
				<input type="submit" value="Lookup">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="reset" value="Clear the form">
				<br>&nbsp;
			</td>
		</tr>
	</table>
	<input type="hidden" name="del" size="-1" value="begin"><input type="hidden" name="del" size="-1" value="end">
</form>

<?php

}




function do_ui_keywords()
{
	global $Config;
	global $Corpus;
	global $User;


	/* =================================================
	 * create the options for frequency lists to compare
	 * ================================================= */

	/* THE ORDER IN LIST 1:
	 *       - Subcorpora (local, owned)               (lus)
	 *       - Granted subcorpora (local)              (lgs)
	 *       - Public subcorpora (local, non-owned)    (lps)
	 *       - Entire corpus                           (***)
	 * 
	 * THE ORDER IN LIST 2: 
	 *       - Remainder                               (***)
	 *       - Subcorpora (local, owned)               (lus)
	 *       - Granted subcorpora (local)              (lgs)
	 *       - Public subcorpora (local, non-owned)    (lps)
	 *       - Entire corpus                           (***)
	 *       - System corpora (any access level)       (fsc)
	 *       - User's own corpora.                     (fuc)
	 *       - Granted corpora.                        (fgc)
	 *       - User's nonlocal subcorpora              (fus)
	 *       - Public subcorpora (nonlocal, nonowned)  (fps)
	 *       - Granted subcorpora (nonlocal, nonowned) (fgs)
	 */

	// TODO, in future, maybe these could be filtered by the corpus->language value??
	// ................ this would save the list from getting super long.

	/* id / handle => title maps; needed for both local and public subcorpora */
	$sc_lookup   = array_map('escape_html', get_subcorpus_name_mapper());
	$corp_lookup = array_map('escape_html', list_corpora_with_titles());
	$uc_lookup   = array_map('escape_html', list_user_corpora_with_titles());
	/* tool to lookup what corpus an sc is found in */
	$sc_corp_lookup = get_subcorpus_corpus_mapper();
	
	/* build option strings of various kinds */
	
	$option_groups = [
			'fsc'=>'',     'fuc'=>'',     'fgc'=>'',  /* foreign system corpora  | foreign user corpora | foreign granted corpora */
			'lus'=>'',     'lgs'=>'',     'lps'=>'',  /* local user subcorpora   | local granted SCs    | local public SCs        */
			'fus'=>'',     'fgs'=>'',     'fps'=>'',  /* foreign user subcorpora | foreign granted SCs  | foreign public SCs      */
	];
	
	
	
	/* fsc */
	foreach ($corp_lookup as $fc_name => $fc_desc)
		if ($fc_name != $Corpus->name)  /* don't show the corpus we're presently "in". */
			if (PRIVILEGE_TYPE_NO_PRIVILEGE < max_user_privilege_level_for_corpus($User->username, $fc_name))
				$option_groups['fsc'] .=  "\t\t\t\t\t<option value=\"fsc~$fc_name\">Corpus: {$fc_desc}</option>\n" ;
	
	if ($Config->user_corpora_enabled)
	{
		/* fuc */
		foreach (list_user_corpora($User->username) as $uc_name)
			if ($uc_name != $Corpus->name)
				$option_groups['fuc'] .=  "\t\t\t\t\t<option value=\"fuc~$uc_name\">Your corpus: {$uc_lookup[$uc_name]}</option>\n" ;
		
		/* fgc */
		if ($Config->colleaguate_system_enabled)
			foreach(get_all_user_corpus_grants_incoming($User->id) as $gr)
				if ($gr->corpus != $Corpus->name)
					$option_groups['fgc'] .=  "\t\t\t\t\t<option value=\"fgc~{$gr->corpus}\">Corpus shared with you: {$uc_lookup[$gr->corpus]}</option>\n" ;
	}
	
	
	/* lus */
	foreach (list_freqtabled_subcorpora($User->username, true, true, true) as $sc_id)
		$option_groups['lus'] .= "\t\t\t\t\t<option value=\"lus~$sc_id\">Subcorpus: {$sc_lookup[$sc_id]}</option>\n" ;
	
	/* lgs */
	if ($Config->colleaguate_system_enabled)
		foreach (list_freqtabled_subcorpora($User->username, true, false, false) as $sc_id)
			$option_groups['lgs'] .= "\t\t\t\t\t<option value=\"lus~$sc_id\">Subcorpus shared with you: {$sc_lookup[$sc_id]}</option>\n" ;
	
	/* lps */
	foreach (list_public_freqtabled_subcorpora(true, false) as $sc_id)
		$option_groups['lps'] .= "\t\t\t\t\t<option value=\"lps~$sc_id\">Public subcorpus: {$sc_lookup[$sc_id]}</option>\n" ;

	
	if ($Config->user_corpora_enabled)
	{
		/* fus */
		foreach (list_freqtabled_subcorpora($User->username, false, true, false) as $sc_id)
			$option_groups['fus'] .= "\t\t\t\t\t<option value=\"fus~$sc_id\">Subcorpus: {$sc_lookup[$sc_id]}; from &ldquo;{$corp_lookup[$sc_corp_lookup[$sc_id]]}&rdquo;</option>\n" ;
		
		/* fgs */
		if ($Config->colleaguate_system_enabled)
			foreach (list_freqtabled_subcorpora($User->username, false, false, false) as $sc_id)
				$option_groups['fgs'] .= "\t\t\t\t\t<option value=\"fgs~$sc_id\">Subcorpus shared with you: {$sc_lookup[$sc_id]}; from &ldquo;{$corp_lookup[$sc_corp_lookup[$sc_id]]}&rdquo;</option>\n" ;
	}
	
	/* fps */
	foreach (list_public_freqtabled_subcorpora(false, true) as $sc_id)
		$option_groups['fps'] .= "\t\t\t\t\t<option value=\"fps~$sc_id\">Public subcorpus: {$sc_lookup[$sc_id]}</option>\n" ;
	
	
	
	/* =============================================
	 * create the options for selecting an attribute
	 * ============================================= */
	
	$attribute = list_corpus_annotations($Corpus->name);
	
	$att_options = '<option value="word">Word forms</option>' . "\n";
	
	foreach ($attribute as $k => $a)
		$att_options .= "\t\t\t\t\t<option value=\"$k\">" . escape_html($a) . "</option>\n";

?>

<form id="kwForm" class="greyoutOnSubmit" action="keywords.php" method="get">
	<input id="kwMethodInput" type="hidden" name="kwMethod" value="">
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">Keywords and key tags</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="4" align="center">
				&nbsp;<br>
				Keyword lists are compiled by comparing frequency lists you have created for different subcorpora. 
				<a href="index.php?ui=subcorpus">Click here to create/view frequency lists</a>.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Select frequency list 1:</td>
			<td class="concordgeneral">
				<select id="kwTable1" name="kwTable1">
					<option value="::null" selected>Choose a subcorpus...</option>
					<?php echo "\n", $option_groups['lus'], $option_groups['lgs'], $option_groups['lps']; ?>
					<option value="::entire_corpus">Whole of <?php echo escape_html($Corpus->title); ?></option>
				</select>
			</td>
			<td class="concordgeneral">Select frequency list 2:</td>
			<td class="concordgeneral">
				<select id="kwTable2" name="kwTable2">
					<option value="::remainder" selected>Compare subcorpus to rest of this corpus</option>
					<?php echo "\n", $option_groups['lus'], $option_groups['lgs'], $option_groups['lps']; ?>
					<option value="::entire_corpus">Whole of <?php echo escape_html($Corpus->title); ?></option>
					<?php 
					echo "\n"
						, $option_groups['fsc'], $option_groups['fuc'], $option_groups['fgc']
						, $option_groups['fus'], $option_groups['fps'], $option_groups['fgs']
						;
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Compare:</td>
			<td class="concordgeneral" colspan="3">
				<select name="kwCompareAtt">
					<?php echo $att_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="4">Options for keyword analysis:</th>
		</tr>
		<tr>
			<td class="concordgeneral">Show:</td>
			<td class="concordgeneral">
				<input type=radio id="kwWhatToShow:allKey"  name="kwWhatToShow" value="allKey" checked>
				&nbsp;&nbsp;
				<label for="kwWhatToShow:allKey">All keywords</label>
				<br>
				
				<input type=radio id="kwWhatToShow:onlyPos" name="kwWhatToShow" value="onlyPos">
				&nbsp;&nbsp;
				<label for="kwWhatToShow:onlyPos">Positive keywords</label>
				<br>
				
				<input type=radio id="kwWhatToShow:onlyNeg" name="kwWhatToShow" value="onlyNeg">
				&nbsp;&nbsp;
				<label for="kwWhatToShow:onlyNeg">Negative keywords</label>
				<br>
				
				<input type=radio id="kwWhatToShow:lock"    name="kwWhatToShow" value="lock">
				&nbsp;&nbsp;
				<label for="kwWhatToShow:lock">Lockwords</label>
			</td>
			<td class="concordgeneral">Display as:</td>
			<td class="concordgeneral">
				<input type=radio id="kwRender:table"  name="kwRender" value="table" checked>
				&nbsp;&nbsp;
				<label for="kwRender:table">Key items table (full results)</label>
				<br>
				
				<input type=radio id="kwRender:clWmatrix" name="kwRender" value="clWmatrix">
				&nbsp;&nbsp;
				<label for="kwRender:clWmatrix">Textual wordcloud (Wmatrix-style)</label>
				<br>
				
				<input type=radio id="kwRender:clGraphix" name="kwRender" value="clGraphix">
				&nbsp;&nbsp;
				<label for="kwRender:clGraphix">Graphical wordcloud (colourful style)</label>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Comparison statistic:</td>
			<td class="concordgeneral">
				<select name="kwStatistic">
<?php /* TODO implement useR preference here */ ?>
					<option value="<?php echo KEYSTAT_LR_WITH_LL     ;?>" selected>Log Ratio with Log-likelihood filter</option>
					<option value="<?php echo KEYSTAT_LOGLIKELIHOOD  ;?>"         >Log-likelihood</option>
					<option value="<?php echo KEYSTAT_LR_WITH_CONFINT;?>"         >Log Ratio with Confidence Interval filter</option>
					<option value="<?php echo KEYSTAT_LR_CONSERVATIVE;?>"         >Log Ratio (conservative estimate)</option>
					<option value="<?php echo KEYSTAT_LR_UNFILTERED  ;?>"         >Log Ratio (unfiltered)</option>
				</select>
			</td>
			<td class="concordgeneral">
				Significance cut-off point:
				<br>(or confidence interval width)
				</td>
			<td class="concordgeneral">
				<select name="kwAlpha">
					<option value="0.05"           >5%</option>
					<option value="0.01"           >1%</option>
					<option value="0.001"          >0.1%</option>
					<option value="0.0001" selected>0.01%</option>
					<option value="0.00001"        >0.001%</option>
					<option value="0.000001"       >0.0001%</option>
					<option value="0.0000001"      >0.00001%</option>
					<option value="1.0"            >No cut-off</option>
				</select>
				<br>
				<input id="kwFamilywiseCorrect:Y" name="kwFamilywiseCorrect" value="Y" type="checkbox" checked>
				<label for="kwFamilywiseCorrect:Y">Use Šidák correction?</label>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Min. frequency (list 1):</td>
			<td class="concordgeneral">
				<select name="kwMinFreq1">
					<option>1</option>
					<option>2</option>
					<option selected>3</option>
					<option>4</option>
					<option>5</option>
					<option>6</option>
					<option>7</option>
					<option>8</option>
					<option>9</option>
					<option>10</option>
					<option>15</option>
					<option>20</option>
					<option>50</option>
					<option>100</option>
					<option>500</option>
					<option>1000</option>
				</select>
			</td>
			<td class="concordgeneral">Min. frequency (list 2):</td>
			<td class="concordgeneral">
				<select name="kwMinFreq2">
					<option>0</option>
					<option>1</option>
					<option>2</option>
					<option selected>3</option>
					<option>4</option>
					<option>5</option>
					<option>6</option>
					<option>7</option>
					<option>8</option>
					<option>9</option>
					<option>10</option>
					<option>15</option>
					<option>20</option>
					<option>50</option>
					<option>100</option>
					<option>500</option>
					<option>1000</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="4" align="center">
				<p class="spacer">&nbsp;</p>
				<input id="kwSubmitWithKey" type="button" value="Calculate keywords">
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="4">
				View unique words or tags on one frequency list:
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" rowspan="2">Display items that occur in...</td>
			<td class="concordgeneral" colspan="2">
				<input type="radio" id="kwEmpty:f1" name="kwEmpty" value="f1" checked>
				<label for="kwEmpty:f1">... frequency list 1 but NOT frequency list 2</label>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2">
				<input type="radio" id="kwEmpty:f2" name="kwEmpty" value="f2">
				<label for="kwEmpty:f2">... frequency list 2 but NOT frequency list 1</label>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="4" align="center">
				<p class="spacer">&nbsp;</p>
				<input id="kwSubmitWithComp" type="button" value="Show unique items on list">
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
</form>

<?php // name="kwMethod" value="comp|key"

}





function do_ui_freqlist()
{
	/* much of this is the same as the form for keywords, but simpler */
	global $Corpus;
	global $User;

	/* create the options for frequency lists to compare */

	/* subcorpora belonging to this user that have freqlists compiled (list of IDs returned) */
	$subcorpora = list_freqtabled_subcorpora($User->username, true, true, true);
	/* public freqlists - corpora */

	$list_options = "<option value=\"__entire_corpus\">Whole of ". escape_html($Corpus->title) . "</option>\n";

	$subc_mapper = array_map('escape_html', get_subcorpus_name_mapper());
	foreach ($subcorpora as $s)
		$list_options .= "<option value=\"$s\">Subcorpus: {$subc_mapper[$s]}</option>\n";

	/* and the options for selecting an attribute */

	$attribute = list_corpus_annotations($Corpus->name);

	$att_options = '<option value="word">Word forms</option>' . "\n";

	foreach ($attribute as $k => $a)
		$att_options .= "<option value=\"$k\">$a</option>\n";

?>

<form action="freqlist.php" method="get">
	<table class="concordtable fullwidth">
	
		<tr>
			<th class="concordtable" colspan="2">Frequency lists</th>
		</tr>
	
		<tr>
			<td class="concordgrey" colspan="2" align="center">
				You can view the frequency lists of the whole corpus and frequency lists for
				subcorpora you have created. 
				<a href="index.php?ui=subcorpus">
					Click here to create/view subcorpus frequency lists.
				</a>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">View frequency list for ...</td>
			<td class="concordgeneral">
				<select name="flTable">
					<?php echo $list_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">View a list based on ...</td>
			<td class="concordgeneral">
				<select name="flAtt">
					<?php echo $att_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="2">Frequency list option settings</th>
		</tr>
		<tr>
			<td class="concordgeneral">Filter the list by <em>pattern</em> - show only words/tags ...</td>

			<td class="concordgeneral">
				<table>
					<tr>
						<td class="basicbox" style="padding-right: 4em;">
							<p style="white-space: nowrap">
								<input type="radio" id="flFilterType:begin" name="flFilterType" value="begin" checked>
								<label for="flFilterType:begin">starting&nbsp;with</label>
							</p>
							<p style="white-space: nowrap">
								<input type="radio" id="flFilterType:end" name="flFilterType" value="end">
								<label for="flFilterType:end">ending&nbsp;with</label>
							</p>
							<p style="white-space: nowrap">
								<input type="radio" id="flFilterType:contain" name="flFilterType" value="contain">
								<label for="flFilterType:contain">containing</label>
							</p>
							<p style="white-space: nowrap">
								<input type="radio" id="flFilterType:exact" name="flFilterType" value="exact" >
								<label for="flFilterType:exact">matching&nbsp;exactly</label>
							</p>
						</td>
						<td class="basicbox" rowspan="4">
							<input type="text" name="flFilterString" size="32">
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Filter the list by <em>frequency</em> - show only words/tags ...</td>
			<td class="concordgeneral">
				with frequency between
				<input type="text" name="flFreqLimit1" size="8">
				and
				<input type="text" name="flFreqLimit2" size="8">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Number of items shown per page:</td>
			<td class="concordgeneral">
				<select name="pp">
					<option>10</option>
					<option selected>50</option>
					<option>100</option>
					<option>250</option>
					<option>350</option>
					<option>500</option>
					<option>1000</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">List order:</td>
			<td class="concordgeneral">
				<select name="flOrder">
					<option value="desc" selected>most frequent at top</option>
					<option value="asc">least frequent at top</option>
					<option value="alph">alphabetical order</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				&nbsp;<br>
				<input type="submit" value="Show frequency list">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="reset" value="Clear the form">
				<br>&nbsp;
			</td>
		</tr>
	</table>
</form>

<?php


}

function do_ui_corpusmetadata()
{
	global $Corpus;

	?>

<table class="concordtable fullwidth">

	<tr>
		<th colspan="2" class="concordtable">
			Metadata for <?php echo escape_html($Corpus->title), "\n"; ?>
		</th>
	</tr>

	<?php

	/* set up tokens / types / texts in suitable format for HTML print */
	
	$words_in_all_texts  = empty($Corpus->size_tokens) ? 'Cannot be displayed  (wordcount not cached)'        : number_format($Corpus->size_tokens);
	$types_in_corpus     = empty($Corpus->size_types)  ? 'Cannot be calculated (frequency tables not set up)' : number_format($Corpus->size_types);
	$num_texts_in_corpus = empty($Corpus->size_texts)  ? 'Cannot be calculated (text metadata not set up)'    : number_format($Corpus->size_texts);
	$standard_ttr        = empty($Corpus->sttr_1kw)    ? 'Cannot be displayed  (STTR not cached)'             : number_format($Corpus->sttr_1kw, 4) . ' types per token';

	$type_token_ratio    = (empty($Corpus->size_tokens)||empty($Corpus->size_types))
							? 'Cannot be calculated (type or token count not available)'
							: number_format( ((float)$Corpus->size_types / (float)$Corpus->size_tokens) , 4) . ' types per token';
	?>

	<tr>
		<td width="50%" class="concordgrey">Corpus title</td>
		<td width="50%" class="concordgeneral"><?php echo escape_html($Corpus->title); ?></td>
	</tr>
	<tr>
		<td class="concordgrey">CQPweb's short handles for this corpus</td>
		<td class="concordgeneral"><?php echo "{$Corpus->name} / {$Corpus->cqp_name}"; ?></td>
	</tr>
	<tr>
		<td class="concordgrey">Total number of corpus texts</td>
		<td class="concordgeneral"><?php echo $num_texts_in_corpus; ?></td>
	</tr>
	<tr>
		<td class="concordgrey">Total words in all corpus texts</td>
		<td class="concordgeneral"><?php echo $words_in_all_texts; ?></td>
	</tr>
	<tr>
		<td class="concordgrey">Word types in the corpus</td>
		<td class="concordgeneral"><?php echo $types_in_corpus; ?></td>
	</tr>
	<tr>
		<td class="concordgrey">Standardised type:token ratio (1,000-token basis)</td>
		<td class="concordgeneral"><?php echo $standard_ttr; ?></td>
	</tr>
	<tr>
		<td class="concordgrey">Non-standardised type:token ratio</td>
		<td class="concordgeneral"><?php echo $type_token_ratio; ?></td>
	</tr>

	<?php

	/* VARIABLE METADATA */

	foreach(get_all_variable_corpus_metadata($Corpus->name) as $metadata)
	{
		/* if it looks like a URL, linkify it */
		if (0 < preg_match('|^https?://\S+$|', $metadata['value']))
			$metadata['value'] = "<a href=\"{$metadata['value']}\" target=\"_blank\">" . escape_html($metadata['value']) . "</a>";
		else
			$metadata['value'] = escape_html($metadata['value']);
		?>

		<tr>
			<td class="concordgrey">
				<p class="spacer">&nbsp;</p>
				<p><?php echo escape_html($metadata['attribute']); ?></p>
				<p class="spacer">&nbsp;</p>
			</td>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				<p><?php echo $metadata['value']; ?></p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>

		<?php
	}
	?>

	<tr>
		<th class="concordtable" colspan="2">Text metadata and word-level annotation</th>
	</tr>

	<?php

	/* TEXT CLASSIFICATIONS */

	$metadata_fields = list_text_metadata_classifications($Corpus->name);
	$num_rows = count($metadata_fields);

	?>

	<tr>
		<td rowspan="<?php echo max([$num_rows, 1]); ?>" class="concordgrey">
			The database stores the following information for each text in the corpus:
		</td>
		<?php
		$i = 0;
		foreach($metadata_fields as $field_handle => $field_desc)
		{
			$i++;
			echo "\n\t\t\t\t", '<td class="concordgeneral">', escape_html($field_desc), "</td>\n";

			if ($i < $num_rows)
				echo "\n\t\t\t</tr>\n\t\t\t<tr>\n"; /* done every time except the last */

			if (!empty($Corpus->primary_classification_field))
				if ($field_handle == $Corpus->primary_classification_field)
					$description_primary = $field_desc;
		}
		if (0 == $num_rows)
			echo "\n\t\t", '<td class="concordgeneral">There is no text-level metadata for this corpus.</td>', "\n";
		?>
	</tr>
	<tr>
		<td class="concordgrey">The <b>primary</b> classification of texts is based on:</td>
		<td class="concordgeneral">
			<?php
			echo (
				empty($description_primary)
				? 'A primary classification scheme for texts has not been set.'
				: escape_html($description_primary)
				)
				, "\n"
				;
			?>
		</td>
	</tr>

	<?php

	/* ANNOTATIONS */

	$array_of_annotations = get_all_annotation_info($Corpus->name);
	$num_rows = count($array_of_annotations);

	?>

	<tr>
		<td rowspan="<?php echo max([$num_rows, 1]); ?>" class="concordgrey">
			Words in this corpus are annotated with:
		</td>

		<?php

		$i = 0;

		foreach ($array_of_annotations as $annotation)
		{
			$i++;
			echo "\n\t\t\t\t", '<td class="concordgeneral">';
			if (!empty($annotation->description))
			{
				echo escape_html($annotation->description);

				/* while we're looking at the description, save it for later if this
				 * is the primary annotation */
				if ($Corpus->primary_annotation == $annotation->handle)
					$primary_annotation_html = escape_html($annotation->description);
			}
			else
				echo $annotation->handle;

			if (!empty($annotation->tagset))
			{
				echo ' (';
				if (!empty($annotation->external_url))
					echo '<a target="_blank" href="', escape_html($annotation->external_url), '">', escape_html($annotation->tagset), '</a>';
				else
					echo escape_html($annotation->tagset);
				echo ')';
			}

			echo '</td>';
			if ($i < $num_rows)
				echo "\n\t\t\t</tr>\n\t\t\t<tr>\n";
		}

		if(0 == $num_rows)
			echo "\n\t\t", '<td class="concordgeneral">There is no word-level annotation in this corpus.</td>', "\n";
		?>

	</tr>
	<tr>
		<td class="concordgrey">The <b>primary</b> word-level annotation scheme is:</td>
		<td class="concordgeneral">
			<?php 
			echo empty($primary_annotation_html) 
				? 'No primary word-level annotation scheme has been set' 
				: $primary_annotation_html
				, "\n"
				; 
			?>
		</td>
	</tr>

	<?php

	/* EXTERNAL URL */

	if (!empty($Corpus->external_url))
	{
		?>

		<tr>
			<td class="concordgrey">
				Further information about this corpus is available on the web at:
			</td>
			<td class="concordgeneral">
				<a target="_blank" href="<?php echo escape_html($Corpus->external_url); ?>">
					<?php echo escape_html($Corpus->external_url); ?>
				</a>
			</td>
		</tr>

		<?php
	}
	?>

</table>

	<?php
}




function do_ui_export()
{
	global $Corpus;
	global $User;

	if (PRIVILEGE_TYPE_CORPUS_FULL > $Corpus->access_level)
		exiterror("You do not have permission to use this function.");

	?>
	
	<form class="greyoutOnSubmit" action="export-corpus.php" method="get">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="2" class="concordtable">Export corpus or subcorpus</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					<p class="spacer">&nbsp;</p>
					<p>
						If you &ldquo;export&rdquo; a corpus, you download a copy of the whole text of the corpus
						(or one of your subcorpora) allowing you to analyse it offline.
					</p>
					<p>
						Be warned: export downloads can be very big files!
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					What do you want to export?
				</td>
				<td class="concordgeneral">
					<select name="exportWhat">
						<option selected value="~~corpus">Whole corpus</option>
						<?php
						$result = do_sql_query("select * from saved_subcorpora where corpus = '{$Corpus->name}' and user = '{$User->username}' order by name");
						while ($sc = Subcorpus::new_from_db_result($result))
							if ($sc->name != '--last_restrictions')
								echo "\n\t\t\t\t\t\t<option value=\"sc~", $sc->id, '">', 'Subcorpus "', $sc->name, '"</option>';
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" rowspan="3">
					Choose an export format:
				</td>
				<td class="concordgeneral">
					<input type="radio" name="format" value="standard" id="format_standard" checked>
					<label for="format_standard">Standard plain text</label>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<input type="radio" name="format" value="word_annot" id="format_word_annot" >
					<label for="format_word_annot">Word-and-tag format (joined with forward-slash)</label>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<input type="radio" name="format" value="col" id="format_col">
					<label for="format_col">Columnar with all tags (CWB input format)</label>
				</td>
			</tr>
<!-- 			<tr> -->
<!-- 				<td class="concordgeneral"> -->
<!-- 					<input type="radio" name="format" value="xml">XML format with all tags-->
<!-- 				</td> -->
<!-- 			</tr>	 -->
			<tr>
				<td class="concordgeneral">
					Choose the operating system on which you will use the file:
				</td>
				<td class="concordgeneral">
					<select name="exportLinebreak">
						<?php echo print_download_crlf_options(); ?>
					</select>
				</td>
			</tr>
			<tr>
				<td rowspan="2" class="concordgeneral">
					What kind of download do you want? 
				</td>
				<td class="concordgeneral">
					<input type="radio" name="downloadZip" value="0" id="downloadZip:0" checked>
					<label for="downloadZip:0">A single text file</label>
				</td>
			</tr>
			
			<?php
			if (extension_loaded('zip'))
			{
				?>
				
				<tr>
					<td class="concordgeneral">
						<input type="radio"id="downloadZip:1" name="downloadZip" value="1" >
						<label for="downloadZip:1">A zip file with separate files for each corpus text</label>
					</td>
				</tr>
				
				<?php
			}
			else
			{
				?>
				
				<tr>
					<td class="concordgerror">
						<i>Zip file download not available (PHP "zip" extension missing).</i>
					</td>
				</tr>
				
				<?php
			}
			?>

			<tr>
				<td class="concordgeneral">
					Enter a name for the downloaded file:
				</td>
				<td class="concordgeneral">
					<input type="text" name="exportFilename" value="<?php echo $Corpus->name; ?>-export">
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<p align="center">
						<input type="submit" value="Click to export corpus data!">
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
	</form>

	<?php
}


