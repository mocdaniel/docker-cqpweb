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


// TODO A lot of the functions in this file could do with renaming in a way that at least AIMS to be systematic.





/**
 * Translates a SQL sort database field-name(*) to an integer sort position.
 * 
 * (* - not including the "tag", i.e. just node, beforeX, afterX)
 */
function integerise_sql_position($sql_position)
{
	if ( $sql_position == 'node' )
		return 0;
	
	/* failsafe to node ==> 0 */
	if (1 > preg_match('/^(before|after)(\d+)/', $sql_position, $m)) 
		return 0;
	
	return ($m[1]==='before' ? -1 : 1) * (int)$m[2] ;
}


/**
 * Translates an integer sort position to the equivalent MySQL sort database field-name.
 * 
 * @see integerise_sql_position()
 */
function sqlise_integer_position($int_position)
{
	$ip = (int) $int_position;
	
	switch (true)
	{
	case (0 == $ip):
		return "node";
	case (0 <  $ip):
		return 'after' . abs($ip);
	case (0 >  $ip):
		return 'before' . abs($ip);
	}
}

/**
 * Translates an integer sort position to a string suitable to print in the UI.
 * 
 * @see integerise_sql_position()
 */
function stringise_integer_position($int_position)
{
	global $Corpus;
	
	$ip = (int) $int_position;
	
	if ($ip == 0)
		return "at the node";
	
	
	if ($Corpus->main_script_is_r2l)
		return abs($ip) . ($ip < 0 ? ' before the node' : ' after the node');
	else
		return abs($ip) . ($ip < 0 ? ' to the Left' : ' to the Right'); 
}





/** 
 * Returns the maximum number of hits that a user should get in a concordance if they only have restricted access,
 * which is calculated based on the size of the corpus (should be supplied!)
 * 
 * Broadly, the number of hits allowed in this situation is half the square root of the corpus size.
 * 
 * The precise number is calculated as follows: is by the following formula:
 * 		- N tokens in corpus
 *      - square root
 *      - divide by two thousand
 *      - round up to integer; ALWAYS upwards so it can never be 0. 
 *      - times by one thousand
 * In other words, the limit is half of square root N, rounded upwards always, and never less than 1 K.
 * 
 * Examples:
 *     - 100,000,000   => 5,000
 *     - 2,000,000,000 => 23,000
 *     - 1,000,000     => 1,000
 */
function max_hits_when_restricted($tokens_in_corpus_or_section)
{
	return 1000 * (int)ceil(sqrt($tokens_in_corpus_or_section)/2000.0); 
}




/**
 * Returns arrays of "extra code" files for the active global $Corpus,
 * in a format ready to be passed to print_html_header().
 * 
 * @param  string $mode  Either "conc" or "context" (to get the right set of files).
 * @param  string $type  Either "js" or "css" (to specify what type of files to get).
 * @return array         Array for use as 3rd (if $mode==js) or 4th (if $mode=css)
 *                       argument to  print_html_header(), q.v.
 */
function extra_code_files_for_concord_header($mode, $type)
{
	global $Corpus;
	
	/* these funcs render the list of extra code files in an appropriate way for print_html_header() */
	$callback = array(
		'js'  => function ($s){return str_replace('.js', '', $s);} ,
		'css' => function ($s){return "../css/$s";}
		);

	$field = 'visualise_'.$mode.'_extra_'.$type;
	
	return array_map($callback[$type], preg_split('/~/', $Corpus->$field, -1, PREG_SPLIT_NO_EMPTY));
}



function format_time_string($time_taken, $not_from_cache = true)
{
	if (!empty($time_taken) )
		return " <span class=\"concord-time-report\">[$time_taken seconds"
			. ($not_from_cache ? '' : ' - retrieved from cache') . ']</span>';
	else if ( ! $not_from_cache )
		return ' <span class="concord-time-report">[data retrieved from cache]</span>';
}




/**
 * Builds HTML of concordance screen control row. Cache record needed for forms.  
 * 
 * Configuration parameters should be passed in with an associative array.
 * Here's the list of possible parameters:
 * 
 * program - version of the concordance display we are using
 * page_no - page in the concordance
 * per_page - how many hits per page
 * num_of_pages - how many pages there are
 * view_mode - the view mode we are currently using (for the kwic/line switcher, which will offer the other option) 
 * alignment_att - if an alignment is currently being shown, this should contain its a-attribute
 * align_info - array of alignemnt info from the DB.
 * 
 * @param  QueryRecord $cache_record        The query we are rendering. 
 * @param  array       $control_row_config  Associative array of configuration variables.
 * 
 * @return string                           Printable HTML.
 */
function print_control_row($cache_record, $control_row_config)
{
	global $Config;
	global $Corpus;
	
	/* harvest vars from control_mode_config */
	
	$program = NULL;
	
	$page_no = NULL;
	$per_page = NULL;
	$num_of_pages = NULL;

	$view_mode = NULL;
	
	$align_info = NULL;
	$alignment_att = NULL;
	
	extract($control_row_config, EXTR_IF_EXISTS);
	
	
	/* certain controls have a set % width. If we need an extra cell for an alignment control, then that needs to be smaller. */
	$button_cell_width = (empty($align_info) ? '20%' : '15%');
	
	
	
	
	/* this is the variable to which everything is printed */
	$final_string = "\n\t\t<tr>";
	
	
	/* ------------------------------------------------------------------------------------------ *
	 * SHORT CIRCUIT: omit everything except the "further actions" tool iff count_hits_then_cease *
	 * ------------------------------------------------------------------------------------------ */
	if ('count_hits_then_cease' != $program)
	{
		/* do the first 7 cells */

		/* ------------------------------------------ *
		 * first, create backwards-and-forwards-links *
		 * ------------------------------------------ */
		
		$marker = array( 'first' => '|&lt;', 'prev' => '&lt;&lt;', 'next' => "&gt;&gt;", 'last' => "&gt;|" );
		
		/* work out page numbers */
		$nav_page_no = array();
		$nav_page_no['first'] = ($page_no == 1 ? 0 : 1);
		$nav_page_no['prev']  = $page_no - 1;
		$nav_page_no['next']  = ($num_of_pages == $page_no ? 0 : $page_no + 1);
		$nav_page_no['last']  = ($num_of_pages == $page_no ? 0 : $num_of_pages);
		/* all page numbers that should be dead links are now set to zero  */
		
		
		foreach ($marker as $key => $m)
		{
			$final_string .= '<td align="center" class="concordgrey" nowrap><b><a class="page_nav_links" ';
			$n = $nav_page_no[$key];
			if ( $n != 0 )
				/* this should be an active link */
				$final_string .= 'href="concordance.php?qname=' . $cache_record->qname . '&pageNo=' . $n
					. ('' != ($pp_r = print_per_page_for_reinsertion('url', $per_page)) ? '&'.$pp_r : '')
					. (empty($program) ?  '' :  '&program='.$program)
					. (empty($alignment_att) ? '' : '&showAlign='.$alignment_att)
					. url_printget(array(
						array('pageNo', ''), array('qname', ''), ['pp', ''], ['program', ''], ['showAlign', '']
						) )
					. '"';
			$final_string .= ">$m</b></a></td>";
		}
		
		/* ----------------------------------------- *
		 * end of create backards-and-forwards-links *
		 * ----------------------------------------- */
		
		
		
		/* --------------------- *
		 * create show page form *
		 * --------------------- */
		
		$final_string .= 
			  "<td width=\"$button_cell_width\" class=\"concordgrey\" nowrap>"
			
			. "<form action=\"concordance.php\" method=\"get\">"
			
			. '<input type="hidden" name="qname" value="' . $cache_record->qname . '">'
			
			. print_per_page_for_reinsertion('input', $per_page)
			
			. (empty($program) ? '' : '<input type="hidden" name="program" value="' . $program . '">') 

			. (empty($alignment_att) ? '' : '<input type="hidden" name="showAlign" value="' . $alignment_att. '">')
			
			. '<input type="submit" value="Show Page:"> &nbsp; '
			
			. '<input type="number" name="pageNo" value="1" size="8">' // TODO change this to current page????
			
			. url_printinputs(array(
					array('pageNo', ""), array('qname', ''), ['pp', ''], ['program', ''],['showAlign',''],
// other garbage sometimes found here:
					['distOver', ''], ['progToChart', ''], 
// from links gen'ed in breakdown-ui
					['concBreakdownAt',''], 
				))
			
			. '</form>'
			
			. '</td>'
			
			;
		
		
		
		/* ----------------------- *
		 * create change view form *
		 * ----------------------- */
		if ($Corpus->visualise_translate_in_concordance)
			$final_string .= "<td align=\"center\" width=\"$button_cell_width\" class=\"concordgrey\" nowrap>No KWIC view available</td>";
		else
			$final_string .= 
				"<td align=\"center\" width=\"$button_cell_width\" class=\"concordgrey\" nowrap>"
				
				. "<form action=\"concordance.php\" method=\"get\">"
				
				. '<input type="hidden" name="qname" value="' . $cache_record->qname . '">'
				
				. print_per_page_for_reinsertion('input', $per_page)
				
				. print_page_no_for_reinsertion('input', $page_no)
				
				. (empty($program) ? '' : '<input type="hidden" name="program" value="' . $program . '">')
				
				. (empty($alignment_att) ? '' : '<input type="hidden" name="showAlign" value="' . $alignment_att. '">')
				
				. url_printinputs(array(
					array('viewMode', ''), array('qname', ''), ['pp',''], ['pageNo', ''],  ['program', ''],['showAlign',''],
// junk if we pased through distribution:
					['progToChart',''],['distOver',''],
// from links gen'ed in breakdown-ui
					['concBreakdownAt',''], 
					))
				
				. '<input type="hidden" name="viewMode" value="' . ($view_mode == 'kwic' ? 'line' : 'kwic') . '">'
				
				. "<input type=\"submit\" value=\"" 
					. ($view_mode == 'kwic' ? 'Line View' : 'KWIC View') 
					. "\">"
				
				. '</form>'
				
				. '</td>'
				
				;
		
		
		/* ------------------------ *
		 * create random order form *
		 * ------------------------ */
		if ($program == 'categorise')
		{
			/* don't gen the random order button */
			$final_string .= '<td class="concordgrey" width="' . $button_cell_width . '">&nbsp;</td>';
		}
		else
		{
			if ($cache_record->last_postprocess_is_rand())
			{
				/* current display is randomised */
				$newPostP_value = 'unrand';
				$randomButtonText = 'Show in corpus order';
			}
			else
			{
				/* current display is not randomised */
				$newPostP_value = 'rand';
				$randomButtonText = 'Show in random order';
			}
			
			$final_string .= "
				<td align=\"center\" width=\"$button_cell_width\" class=\"concordgrey\" nowrap>
					<form action=\"concordance.php\" method=\"get\">
						<input type=\"hidden\" name=\"qname\" value=\"{$cache_record->qname}\">
						<input type=\"hidden\" name=\"newPostP\" value=\"$newPostP_value\">"
						. (empty($alignment_att) ? '' : "<input type=\"hidden\" name=\"showAlign\" value=\"$alignment_att\">")
						. print_per_page_for_reinsertion('input', $per_page)
						. url_printinputs( array( ['qname', ''],  ['pageNo', ''], ['pp', ''], ['newPostP', ''], ['showAlign', ''],
					['progToChart',''],['distOver',''],   //distribution junk
// from links gen'ed in breakdown-ui
					['concBreakdownAt',''], 
// if program is set to something that matters, THEN THIS BUTTON MAKES IT SO NO LONGER: don't pass owt
					['program',''], 
//TODO, above is prime material for generating inputs from an array of parameters and vales
											))
						. "<input type=\"submit\" value=\"$randomButtonText\">
					</form>
				</td>
				\n";
		}
		
		
		/* ---------------------------------------------- *
		 * add switch-parallel-display form, if necessary *
		 * ---------------------------------------------- */
		if (! empty($align_info))
			$final_string .= print_alignment_switcher('concordance', $cache_record->qname, $align_info, $alignment_att);
	
	} /* end if program not equal to count_hits_then_cease */
	
	
	
	/* at this point, if program is categorise, all we need to do is add one more blank cell; && return. */
	if ($program == 'categorise')
	{
		$final_string .= '<td class="concordgrey" width="' . $button_cell_width . '">&nbsp;</td></tr>';
		return modify_control_row_for_categorise_check($final_string);
	}
	/* the categorise display does not have the standard action-control dropdown. */
	
	
	
	/* -------------------------- *
	 * create action control form *
	 * -------------------------- */
	
	/* if the program is count_hits_then_cease, we need to span 8 cols. */
	$cell_decl = (
		$program == 'count_hits_then_cease' 
			? '<td class="concordgeneral" colspan="8" align="center" nowrap>' 
			: '<td class="concordgrey" nowrap>'
		);
	
	$custom_options = '';
	
	foreach (get_all_plugins_info(PLUGIN_TYPE_POSTPROCESSOR) as $custompp)
	{
// change following to new layout of plugin registry. 
// 		$obj = new $record->class($record->path);
// 		$label = $obj->get_label();
// 		$custom_options .= "<option value=\"CustomPost:{$record->id}\">$label</option>\n\t\t\t";
	}
	
	$final_string .= '
		<form id="concordanceMainDropdown" class="greyoutOnSubmit" action="redirect.php" method="get">' . $cell_decl . '&nbsp;
			<select name="redirect">
				<option selected>Choose action...</option>
				<option value="newQuery">New query</option>
				<option value="thin">Thin...</option>
				<option value="breakdown">Frequency breakdown</option>
				<option value="distribution">Distribution</option>
' . ( !$Config->hide_experimental_features ? '<option value="Dispersion">Dispersion</option>' . "\n" : '' ) . '
				<option value="sort">Sort</option>
				<option value="collocations">Collocations...</option>
				<option value="download-conc">Download...</option>
				<option value="categorise">Categorise...</option>
				<option value="savequery">Save current query result...</option>
				' . $custom_options . '
			</select>
			&nbsp;
			<input type="submit" value="Go!">
			<input type="hidden" name="qname" value="' . $cache_record->qname . '">
			'
			. print_per_page_for_reinsertion('input', $per_page)
			. print_page_no_for_reinsertion('input', $page_no)
			;
	
	
	$final_string .= url_printinputs(array(
		array('redirect', ''), array('qname', ''), ['pp',''], [ 'pageNo', ''],['program',''],['showAlign','']
//TODO. we don't pass through showAlign. But, it should ideally be retained for things like sort, etc. 
// but for now leave that till the new "view cache" system.
		));
	
	/* finish off and return */
	$final_string .= "&nbsp;\n\t\t\t</td>\n\t\t</form>\n\t</tr>\n\n";

	return $final_string;
}


/**
 * Returns an HTML form (dropdown, button) allowing choice of which parallel corpus to show.
 * 
 * @param  string $target_script  Either "context" or "concordance".
 * @param  string $qname          Query name to use in the form info.
 * @param  array  $align_info     Array of alignment handle/description pairs
 * @param  string $current        Alignment currently being displayed. Any empty value = none showing.
 * @return string                 HTML string; consists of a TD.
 */
function print_alignment_switcher($target_script, $qname, $align_info, $current)
{
	if (empty($current))
		$opts = '<option value="" selected>Select aligned data to display...</option>';
	else
		$opts = '<option value="">Hide all aligned text</option>';
	
	foreach ($align_info as $target => $desc)
		$opts .= '<option value="' . $target . ($target == $current ? '" selected>Showing' : '">Show') 
			 . ' parallel corpus ' . escape_html($desc) .'</option>';
	
	return "<td align=\"center\" width=\"20%\" class=\"concordgrey\" nowrap>"
		. "<form action=\"$target_script.php\" method=\"get\">"
		. "<input type=\"hidden\" name=\"qname\" value=\"$qname\">"
		. "<select name=\"showAlign\">$opts</select>"
		. "&nbsp;"
		. "<input type=\"submit\" value=\"Switch\">"
		// FIXME. *Any* colection of concordnace.inc.php params needs to be passable here
		// pageNo, perPage, program ... so need to pass through the whole array of config from
		// print_control_row. 
		. url_printinputs(array(
							array('showAlign', ''), array('qname', '')
							))
// FIXME likewise, when this is used in context-ui, the critical param "batch" needs to be passed thru
		. '</form>'
		. '</td>'
		;
}


/**
 * Alters a control row string so that all links / forms have the right classes to pick up the needed 
 * JavaScript functions for "are you sure?" behaviour.
 */
function modify_control_row_for_categorise_check($control_row)
{
	/* the <a> all have class page_nav_links, so only the forms need changing;
	 * worth having this hived off in a function though in case this gets more complex later  */
	return str_replace('<form ', '<form class="unsaved_view_change" ', $control_row);
}

/**
 * Alters a control row string so that it contains the sort position in such a way
 * that it can be successfully passed through to breakdown-ui.php
 */
function add_sortposition_to_control_row($html, $sort_pos)
{
	/* NOTE: this string **must** match the last bit of HTML generated by print_control_row() */
	$search = '</form>';
	
	$add = '<input type="hidden" name="concBreakdownAt" value="' . $sort_pos . '">';
	
	return str_replace($search, $add.$search, $html);
}





/**
 * Prints and returns a series of <option> elements with values -5 to 5
 * (the standard posiitons available in a sort database). This is used in
 * both the sort control and in the freq breakdown control box.
 * 
 * Parameter: the integer value of the option to be pre-selected.
 */
function print_sort_position_options($current_position = 0)
{
	global $Corpus;
	
	$s = '';
	
	foreach(array(5,4,3,2,1) as $i)
		$s .= "\n\t<option value=\"-$i\""
			. (-$i == $current_position ? ' selected' : '')
			. ">$i Left</option>"
			;
	
	$s .= "\n\t<option value=\"0\""
		. (0 == $current_position ? ' selected' : '')
		. ">Node</option>"
		;
	
	foreach(array(1,2,3,4,5) as $i)
		$s .= "\n\t<option value=\"$i\""
			. ($i == $current_position ? ' selected' : '')
			. ">$i Right</option>\n"
			;
	
	if ($Corpus->main_script_is_r2l)
	{
		$s = str_replace('Left',  'Before', $s);
		$s = str_replace('Right', 'After',  $s);
	}
	
	return $s;
}



function print_sort_control($qname, $postprocess_string, &$sort_position_out)
{
	global $Corpus;
	
	/* get current sort settings : from the current query's postprocess string */
	/* ~~sort[position~thin_tag~thin_tag_inv~thin_str~thin_str_inv] */
	$tmp = explode('~~', $postprocess_string);
	$command = array_pop($tmp);
	
	if (substr($command, 0, 4) == 'sort')
	{
		list($current_settings_position, 
			$current_settings_thin_tag, $current_settings_thin_tag_inv,
			$current_settings_thin_str, $current_settings_thin_str_inv)
			=
			explode('~', trim(substr($command, 4), '[]'));
		if ($current_settings_thin_tag == '.*')
			$current_settings_thin_tag = '';
		if ($current_settings_thin_str == '.*')
			$current_settings_thin_str = '';
	}
	else
	{
		$current_settings_position = 1;
		$current_settings_thin_tag = '';
		$current_settings_thin_tag_inv = 0;
		$current_settings_thin_str = '';
		$current_settings_thin_str_inv = 0;
	}
	
	/* create a select box: the "position" dropdown */
	$position_select = '<select form="newSortForm" name="newPostP_sortPosition">'
		. print_sort_position_options($current_settings_position)
		. '</select>
		';
	
	
	/* create a select box: the "tag restriction" dropdown */
	if (!empty($Corpus->primary_annotation))
		$taglist = corpus_annotation_taglist($Corpus->name, $Corpus->primary_annotation);
	else
		$taglist = array();
	
	$tag_restriction_select = '<select form="newSortForm" name="newPostP_sortThinTag">
		<option value=""' . ('' === $current_settings_thin_tag ? ' selected' : '') 
		. '>None</option>';
	
	foreach ($taglist as $tag)
		$tag_restriction_select .= '<option' . ($tag == $current_settings_thin_tag ? ' selected' : '') . ">" . escape_html($tag) . "</option>\n\t";
	
	$tag_restriction_select .= '</select>';
	
	
	
	/* list of inputs with all the ones set by this form cleared */
	$forminputs = url_printinputs(array(
				array('qname', ''),['program',''], ['showAlign',''], // don't wanna autopass these!
											// "showAlign" doesn't go to main sort, so shouldn';t here. 
				array('pageNo', ''),// this is a postprocess so default pageNo
				array('newPostP_sortThinString', ''),
				array('newPostP_sortThinStringInvert', ''),
				array('newPostP_sortThinTag', ''),
				array('newPostP_sortThinTagInvert', ''),
				array('newPostP_sortPosition', ''),
				) );// TODO clear this up!! what exactly is passed?
	$forminputs = str_replace('type="', 'form="newSortForm" type="', $forminputs);
	
	/* stash sort position in an out parameter... */
	$sort_position_out = $current_settings_position;
	
	$thin_tag_inv_checked = ($current_settings_thin_tag_inv ? ' checked' : '');
	$thin_str_inv_checked = ($current_settings_thin_str_inv ? ' checked' : '');
	
	/* all is now set up so we are ready to return the final string */
	return <<<END_OF_HTML

	<tr>
		<td colspan="4" class="concordgrey">
			<form id="newSortForm" action="concordance.php" method="get"></form>
			<input form="newSortForm" type="hidden" name="qname" value="$qname">
			<input form="newSortForm" type="hidden" name="program" value="sort">
			<input form="newSortForm" type="hidden" name="newPostP" value="sort">
			<input form="newSortForm" type="hidden" name="newPostP_sortRemovePrevSort" value="1">
			$forminputs
			<strong>Sort control:
		</td>
		<td class="concordgrey">
			Position:
			$position_select
		</td>
		<td class="concordgrey" nowrap>
			Tag restriction:
			$tag_restriction_select
			<br>
			<input form="newSortForm" type="checkbox" id="newPostP_sortThinTagInvert:1" name="newPostP_sortThinTagInvert" value="1"$thin_tag_inv_checked> 
			<label for="newPostP_sortThinTagInvert:1">exclude</label>
		</td>
		<td class="concordgrey" nowrap>
			Starting with:
			<input form="newSortForm" type="text" name="newPostP_sortThinString" value="$current_settings_thin_str">
			<br>
			<input form="newSortForm" type="checkbox" id="newPostP_sortThinStringInvert:1" name="newPostP_sortThinStringInvert" value="1"$thin_str_inv_checked>
			<label for="newPostP_sortThinStringInvert:1">exclude</label>
		</td>
		<td class="concordgrey">
			&nbsp;
			<input form="newSortForm" type="submit" value="Update sort">
		</td>
	</tr>

END_OF_HTML;
//TODO if sort is on and align is on , the sort control has one fewer cell than the main control row. 

}




/**
 * Creates the control bar at the bottom of "categorise".
 * 
 * Since the whole screen is one big form, it DOES NOT contain any of the hidden inputs for the form.
 * @param  $view_mode  string    The current view mode (kwic/line).
 * @return             string    HTML of the control bar in question (a TR containing a SELECT). 
 */
function print_categorise_control($view_mode)
{
	$colspan = ($view_mode == 'kwic' ? 6 : 4);

	return <<<END_OF_ROW

		<tr>
			<td class="concordgrey" align="right" colspan="$colspan">
				<select name="sqAction">
					<option value="updateCategorisationAndLeave"            >Save values and leave categorisation mode</option>
					<option value="updateCategorisationAndNextPage" selected>Save values for this page and go to next</option>
					<option value="noUpdateNewQuery"                        >New Query (without saving values!)</option>
				</select>
				<input type="submit" value="Go!">
			</td>
		</tr>

END_OF_ROW;
}







/**
 * Processes a line of CQP output that is the "aligned" data for a concordance line,
 * when we have an activated-for-display a-attribute.
 * 
 * The $display_params are the same as for print_concordance_line().
 * 
 * @see print_concordance_line()
 * @param  string $cqp_line          A line of output from CQP.
 * @param  array  $display_params    An associative array of variables from the main script; explained in print_concordance_line.
 * @return string                    A complete <td> containing the generated alignment-line. 
 * 
 */
function print_aligned_line($cqp_line, $display_params)
{
	/* we collect info about the target corpus of the alignment just once, as it does not vary */
	static $cell_align = NULL;
	static $target_bdo_begin = NULL;
	static $target_bdo_end   = NULL;
	static $tags_exist_in_aligned_cqp_output = NULL;
	static $colspan = NULL;
	static $cell_class = NULL;
	
	if (is_null($cell_align))
	{
		$target_info = get_corpus_info($display_params['alignment_att_to_show']);
		$target_bdo_begin = ( $target_info->main_script_is_r2l ? '<bdo dir="ltr">' : '' ); 
		$target_bdo_end   = ( $target_info->main_script_is_r2l ? '</bdo>' : '' ); 
		$cell_align       = ( $target_info->main_script_is_r2l ? ' align="right"' : '' );
		
		if ('kwic' == $display_params['view_mode'])
		{
			$colspan    = ' colspan="3"';
			$cell_class = 'parallel-kwic';
		}
		else
		{
			$colspan    = '';
			$cell_class = 'parallel-line';
		}
		/* does the target corpus have an annotation with the same handle as the source primary annotation? */
		global $Corpus;
		$tags_exist_in_aligned_cqp_output
			= array_key_exists($Corpus->primary_annotation, list_corpus_annotations($display_params['alignment_att_to_show']));
	}
	
	/* OK, build the line. First -- no text id or line number, cos if $show_align, then they are rowspan=2 
	 * So we go straight to the one cell. */
	
	/* Remove leading flag of alignment att from commandlne CQP */
	$line = preg_replace("/^-->{$display_params['alignment_att_to_show']}:\s/", '', $cqp_line);
	
	if ('(no alignment found)' != $line)
	{
		/* use the same extraction technique as for the main concordance line,
		 * but inform the inner function using the $type" parameter. */
		list($line) = concordance_line_blobprocess($line, $tags_exist_in_aligned_cqp_output ? 'aligned-tags' : 'aligned-notags', 100000);
	}
	
	return "<td class=\"$cell_class\"$colspan$cell_align>$target_bdo_begin$line$target_bdo_end</td>\n";
}



/**
 * Processes a line of CQP output for display in the CQPweb concordance table.
 * 
 * This is done with regard to certain rendering-control variables esp. related to gloss
 * visualisation.
 * 
 * Returns a line of 3 or 5 td's that can be wrapped in a pair of tr's, or have other
 * cells added (e.g. for categorisation).
 * 
 * Note no tr's are added at this point.
 * 
 * In certain display modes, these td's may have other smaller tables within them.
 * 
 * @param  string $cqp_line         A line of output from CQP.
 * @param  array  $display_params   An associative array of variables from the main script about the query we are printing.
 *                                  Contents as follows:
 *                                  qname              => The query name (cache identifier) of the query - for context display link. Compulsory.
 *                                  view_mode          => The mode to display (kwic or line). Compulsory.
 *                                  line_number        => The line number to be PRINTED (counted from 1). Compulsory. Also used for nodelink ID.
 *                                  highlight_position => Integer offset indicating the entry in left or right context to be highlit. 
 *                                                        If absent, no highlight. 
 *                                  highlight_show_tag => Boolean: if true, show the primary annotation of the highlit item in-line. 
 *                                                        If absent, false is assumed.
 *                                  show_align         => Boolean: if true, we are in "show parallel corpus" mode, with parallel data
 *                                                        included in the $cqp_line, and the alignment_att_to_show parameter included.
 *                                  alignment_att_to_show  => String: handle of the target corpus that we are to display.  
 * @return string                   The built-up line.
 */
// function print_concordance_line($cqp_line, $qname, $view_mode, $line_number, $highlight_position, $highlight_show_pos = false)
function print_concordance_line($cqp_line, $display_params)
{
	global $Corpus;
	
	
	/* harvest vars from display_params */
	$qname              = $display_params['qname'];
	$view_mode          = $display_params['view_mode'];
	$line_number        = $display_params['line_number'];
	$highlight_position = (isset($display_params['highlight_position']) ? $display_params['highlight_position'] : 100000) ;
	/* nb re the above: setting highlight position to a superhigh number means we utilise NO HIGHLIGHT. */
	$highlight_show_tag = (isset($display_params['highlight_show_tag']) ? (bool) $display_params['highlight_show_tag'] : false) ;
	$show_align         = $display_params['show_align'];
	$alignment_att_to_show = ($show_align ? $display_params['alignment_att_to_show'] : NULL);
	
	
	/* note they should all always be set. The isset is a bit paranoid here. */
	
	
	/* get URL of the extra-context page right at the beginning, because we don't know when we may need it;
	 * create as string that is easily embeddable into the <a> of the node-link (or empty string if not permitted) */
	if (PRIVILEGE_TYPE_CORPUS_RESTRICTED < $Corpus->access_level)
		$context_href = ' href="context.php?batch=' . ($line_number-1) 
						. '&qname=' . $qname 
						. ($show_align ? '&showAlign=' . $alignment_att_to_show : '') 
						. '" '
						;
	else
		$context_href = '';
	
	if ($Corpus->visualise_translate_in_concordance)
	{
		/* extract the translation content, which will be BEFORE the text_id */
		preg_match("/<{$Corpus->visualise_translate_s_att} (.*?)><text_id/", $cqp_line, $m);
		$translation_content = $m[1];
		$cqp_line = preg_replace("/<{$Corpus->visualise_translate_s_att} .*?><text_id/", '<text_id', $cqp_line);
	}
// 	else if ()
	{
//		reuse translation contetn formatting????????????????
		
// 		TODO
// 		$translation_content
	}
	
	/* extract the text_id and delete that first bit of the line */
	$text_id = $position_label = false;
	extract_cqp_line_position_labels($cqp_line, $text_id, $position_label);
	
	/* divide up the CQP line */
	list($kwic_lc, $kwic_match, $kwic_rc) = explode('--%%%--', $cqp_line);
	
	/* left context string */
	list($lc_string, $lc_tool_string) 
		= concordance_line_blobprocess($kwic_lc, 'left', $highlight_position, $highlight_show_tag);
	
	list($node_string, $node_tool_string) 
		= concordance_line_blobprocess($kwic_match, 'node', $highlight_position, $highlight_show_tag, $context_href);
	
	/* right context string */
	list($rc_string, $rc_tool_string) 
		= concordance_line_blobprocess($kwic_rc, 'right', $highlight_position, $highlight_show_tag);
	
	
	// TODO remove any XML viz from the $node_string and put it onto the left or right context.
	// this is also a TODO in  concordance_line_blobprocess ... not clear if its better dione here or there.
	
	/* if the corpus is r-to-l, this function call will spot it and handle things for us */
	right_to_left_adjust($lc_string, $lc_tool_string, $node_string, $node_tool_string, $rc_string,$rc_tool_string); 
	
	
	
	/* create final contents for putting in the cells */
	if ($Corpus->visualise_gloss_in_concordance)
	{
		$lc_final   = build_glossbox('left' , $lc_string  , $lc_tool_string);
		$node_final = build_glossbox('node' , $node_string, $node_tool_string);
		$rc_final   = build_glossbox('right', $rc_string  , $rc_tool_string);
	}
	else
	{
		$lc_final = $lc_string;
		$rc_final = $rc_string;
		
		$full_tool_tip = ' data-tooltip="'
			. str_replace('"', '&quot;', $lc_tool_string . '<span style="color:#DD0000;">'
				. $node_tool_string . '</span> ' . $rc_tool_string)
			. '"';
		$node_final = '<b><a id="concNL_' . $line_number. '" class="nodelink hasToolTip"' . "$context_href$full_tool_tip>$node_string</a></b>";
	}
	
	
	/* print cell with line number; then text_id (Across 2 rows iff we are displaying alignment data. */
	$init_cells_rowspan = ( $show_align ? ' rowspan="2"' : '' );
	
	$final_string = "<td class=\"text_id\"$init_cells_rowspan><b>$line_number</b></td>";
	
	$final_string .= "<td class=\"text_id\"$init_cells_rowspan><a id=\"concTL_$line_number\" class=\"hasToolTip\" href=\"textmeta.php?text=$text_id\" "
		. print_text_metadata_tooltip($text_id) . '>' . $text_id . '</a>' 
		. ($position_label === '' ? '' : ' <span class="concordposlabel">' . escape_html($position_label) . '</span>')
		. '</td>'
		;
	
	if ($view_mode == 'kwic')
	{
		/* print three cells - kwic view */

		$final_string .= '<td class="before" nowrap><div class="before">' . $lc_final   . '</div></td>';
		$final_string .= '<td class="node"   nowrap>'                     . $node_final . '</td>';
		$final_string .= '<td class="after"  nowrap><div class="after">'  . $rc_final   . '</div></td>';
	}
	else
	{
		/* print one cell - line view */
	
		/* glue it all together, then wrap the translation if need be */
		$subfinal_string =  $lc_final . ' ' . $node_final . ' ' . $rc_final;
		if ($Corpus->visualise_translate_in_concordance)
			$subfinal_string = concordance_wrap_translationbox($subfinal_string, $translation_content);
	
		/* and add to the final string */
		$final_string .= '<td class="lineview">' . $subfinal_string . '</td>';
	}
	
	$final_string .= "\n";
	
	return $final_string;
}




/**
 * Converts a node-or-right-or-left context string from CQP output into
 * two HTML strings ready for printing in CQPweb.
 * 
 * The FIRST string is the "main" string; the one that is the principle
 * readout. The SECOND string is the "other" string: either for a 
 * tag-displaying tooltip, or for the gloss-line when the gloss is visible.
 * 
 * This function gets called 3 times per hit, obviously.
 * 
 * Note: we do not apply links here in normal mode, but if we are visualising
 * a gloss, then we have to (because the node gets buried in the table
 * otherwise). Thus $context_href must be passed in.
 * 
 * Possible values for "type": left, node, right; aligned-tags, aligned-notags.
 */
function concordance_line_blobprocess($lineblob, $type, $highlight_position, $highlight_show_pos = false, $context_href = '')
{
	global $Corpus;
	
	static $xml_viz_index = NULL;
	
	/* set up the opaque xml visualisations on first call */
	if (is_null($xml_viz_index))
		$xml_viz_index = index_xml_visualisation_list(get_all_xml_visualisations($Corpus->name, true, false, false));
	
	/* all string literals (other than empty strings or spacers) must be here so they can be conditionally set. */
	if ($type == 'node')
	{
		/* the node does not receive a highlight */
		$main_begin_high = '';
		$main_end_high = '';
		$other_begin_high = '';
		$other_end_high = '';
		$glossbox_nodelink_begin = '<b><a class="nodelink"' . $context_href . '>';
		$glossbox_nodelink_end = '</a></b>';
	}
	else
	{
		$main_begin_high = '<span class="contexthighlight">';
		$main_end_high = '</span> ';
		$other_begin_high = '<b>';
		$other_end_high = '</b> ';
		$glossbox_nodelink_begin = '';
		$glossbox_nodelink_end = '';
	}
	/* every glossbox will contain a bolded link, if it is a node; otherwise not */
	$glossbox_line1_cell_begin = "<td class=\"glossbox-$type-line1\" nowrap>$glossbox_nodelink_begin";
	$glossbox_line2_cell_begin = "<td class=\"glossbox-$type-line2\" nowrap>$glossbox_nodelink_begin";
	$glossbox_end = $glossbox_nodelink_end . '</td>';
	/* end of string-literals-into-variables section */
	
	
	/* the "trim" is just in case of unwanted spaces (there will deffo be some on the left) ... */
	/* this regular expression puts tokens in $m[4]; xml-tags-before in $m[1]; xml-tags-after in $m[5] . */
	preg_match_all(CQP_INTERFACE_WORD_REGEX, trim($lineblob), $m, PREG_PATTERN_ORDER);
	$token_array = $m[4];
	$xml_before_array = $m[1];
	$xml_after_array = $m[5];
	
	$n = (empty($token_array[0]) ? 0 : count($token_array));
	
	/* if we are in the left string, we need to translate the highlight position from
	 * a negative number to a number relative to 0 to $n... */
	if ($type == 'left')
		$highlight_position = $n + $highlight_position + 1;
	
	/* these are the strings we will build up and return */
	$main_string = '';
	$other_string = '';
	// I thought returning two more strings would do the trick for the "XML viz within node colum" problem (see below), but ....
	$append_to_previous_from_node = '';// Hmm, but this won't work in glossbox mode, cos, tyhe left XML viz already has the end of a globssbox wrapped roudn it. 
	$prepend_to_next_from_node = ''; // and we don't want to have it outside any table, that will obviously look crappo.  I need ot think again!!
	
	
	
	for ($i = 0; $i < $n; $i++) 
	{
		/* apply XML visualisations */
//		$xml_before_string = apply_xml_visualisations($xml_before_array[$i], $xml_viz_index) . ' ';
//		$xml_after_string  =  ' ' . apply_xml_visualisations($xml_after_array[$i], $xml_viz_index);
		$xml_before_string = apply_xml_visualisations($xml_before_array[$i], $xml_viz_index);
		$xml_after_string  = apply_xml_visualisations($xml_after_array[$i],  $xml_viz_index);
		
		if ('node' == $type)
		{
			/* XML viz before the first word of a node, or after the last word of a node, 
			 * should be transferred to the right/left context
			 * (so that they don't appear in the central column as part of the node link);
			 * XML viz between words of the node is fair game. */
			if ($i == 1)
			{
				// TODO somehow move $xml_before_string to the LEFT context (end of),
				// and set that variable itself to ''.
				//$magic_locus_for_this_move = $xml_before_string;
				//$xml_before_string = '';
			}
			else if ($i == ($n-1))
			{
				//$magic_locus_for_that_move = $xml_after_string;
				//$xml_after_string = '';
			}
		}

		list($word, $tag) = extract_cqp_word_and_tag($token_array[$i], $Corpus->visualise_gloss_in_concordance, $type=='aligned-notags');

		if ($type == 'left' && $i == 0 && preg_match('/\A[.,;:?\-!"]\Z/', $word))
			/* don't show the first word of left context if it's just punctuation */
			continue;
		
		if (!$Corpus->visualise_gloss_in_concordance)
		{
			/* the default case: we are building a concordance line and a tooltip */
			if ($highlight_position == $i+1) /* if this word is the word being sorted on / collocated etc. */
			{
				$main_string .= "$xml_before_string$main_begin_high$word"
					. ($highlight_show_pos ? $tag : '') 
					. "$main_end_high$xml_after_string ";
				$other_string .= "$other_begin_high$word$tag$other_end_high";
			}
			else
			{
				$main_string .= "$xml_before_string$word$xml_after_string ";
				$other_string .= "$word$tag ";
			}
		}
		else
		{
			/* build a gloss-table instead;
			 * other_string will be the second line of the gloss table instead of a tooltip */
			if ($highlight_position == $i+1)
			{
				$main_string .= "$glossbox_line1_cell_begin$xml_before_string$main_begin_high$word$main_end_high$xml_after_string$glossbox_end";
				$other_string .= "$glossbox_line2_cell_begin$main_begin_high$tag$main_end_high$glossbox_end";
			}
			else
			{
				$main_string .= "$glossbox_line1_cell_begin$xml_before_string$word$xml_after_string$glossbox_end";
				$other_string .= "$glossbox_line2_cell_begin$tag$glossbox_end";
			}
		}
	}
	if ($main_string == '' && !$Corpus->visualise_gloss_in_concordance)
		$main_string = '&nbsp;';
	
	
	/* extra step needed because otherwise a space may get linkified */
	if ($type == 'node')
		$main_string = trim($main_string);
	
	return array($main_string, $other_string, $append_to_previous_from_node, $prepend_to_next_from_node);
}


/** 
 * Switches around the contents of the left/right strings, if necessary, 
 * to support L2R scripts. 
 * 
 * All parameters are passed by reference.
 */
function right_to_left_adjust(&$lc_string,   &$lc_tool_string, 
                              &$node_string, &$node_tool_string, 
                              &$rc_string,   &$rc_tool_string)
{
	global $Corpus;
	global $view_mode;
	
	if ($Corpus->main_script_is_r2l)
	{
		/* ther are two entirely different styles of reordering.
		 * (1) if we are using glosses (strings of td's all over the shop)
		 * (2) if we have the traditional string-o'-words
		 */ 
		if ($Corpus->visualise_gloss_in_concordance)
		{
			/* invert the order of table cells in each string. */
			$lc_string        = concordance_invert_tds($lc_string);
			$lc_tool_string   = concordance_invert_tds($lc_tool_string);
			$node_string      = concordance_invert_tds($node_string);
			$node_tool_string = concordance_invert_tds($node_tool_string);
			$rc_string        = concordance_invert_tds($rc_string);
			$rc_tool_string   = concordance_invert_tds($rc_tool_string);
			/* note this is done regardless of whether we are in kwic or line */	
			/* similarly, regardless of whether we are in kwic or line, we need to flip lc and rc */
			$temp_r2l_string = $lc_string;
			$lc_string = $rc_string;
			$rc_string = $temp_r2l_string;
			/* we need ot do the same with the tooltips too */
			$temp_r2l_string = $lc_tool_string;
			$lc_tool_string = $rc_tool_string;
			$rc_tool_string = $temp_r2l_string;
		}
		else
		{
			/* we only need to worry in kwic. In line mode, the flow of the
			 * text and the normal text-directionality systems in the browser
			 * will deal wit it for us.*/
			if ($view_mode == 'kwic')
			{
				$temp_r2l_string = $lc_string;
				$lc_string = $rc_string;
				$rc_string = $temp_r2l_string;
			}
		}
	}
	/* else it is an l-to-r script, so do nothing. */
}

/**
 * Build a two-line (or three line?) glossbox table from
 * two provided sequences of td's.
 * 
 * $type must be left, node, or right (as a string). Anything
 * else will be treated as if it was "node".
 */
function build_glossbox($type, $line1, $line2, $line3 = false)
{
	global $Corpus;
	global $view_mode;
	
	if ($view_mode =='kwic')
	{
		switch($type)
		{
			case 'left':	$align = 'right';	break;
			case 'right':	$align = 'left';	break;
			default:		$align = 'center';	break;
		}
	}
	else
		$align = ($Corpus->main_script_is_r2l ? 'right' : 'left');
	
	if (empty($line1) && empty($line2))
		return '';
	
	return
		'<table class="glossbox" align="' . $align . '"><tr>'
		. $line1
		. '</tr><tr>' 
		. $line2
		. '</tr>'
//		. ($line3 ? '' : '')
		. ($line3 ? $line3 : '')
		. '</table>'
		;
}

function concordance_wrap_translationbox($concordance, $translation)
{
	return 
		'<table class="transbox"><tr><td class="transbox-top">'
		. $concordance
		. '</td></tr><tr><td class="transbox-bottom">'
		. $translation
		. "\n</td></tr></table>\n"
		;
}

/**
 * Takes a string consisting of a sequence of td's.
 * 
 * Returns the same string of td's, in the opposite order.
 * Note - if there is material outside the td's, results may
 * be unexpected. Should not be used outside the concordance
 * line rendering module.
 */
function concordance_invert_tds($string)
{
	$string = rtrim($string);
	if ('</td>' == substr($string, -5))
		$string = substr($string, 0, -5);
	$stack = explode('</td>', $string);
	
	$stack = array_reverse($stack);
	
	return implode('</td>', $stack) . '</td>';
}


/**
 * Function used by print_concordance_line, and also to print the sole concordance
 * line in context.inc.php.
 * 
 * It takes a single word/tag string from the CQP concordance line, and
 * returns a array of printable word + printable tag.
 * 
 * The second arg determines whether or not we give the primary annotation 
 * according to the requirements of gloss-visualisation mode. This arg
 * should be either $Corpus->visualise_gloss_in_concordance
 * or $Corpus->visualise_gloss_in_context depending on which script is calling.
 * 
 * The third arg, if true, disables word-and-tag extraction for parallel data 
 * that do not have the same annotation p-attribute of the main corpus
 * 
 * @param  string $cqp_source_string        A string, containing one "word/tag" pair from a CQP concordance.
 *                                          (or, potentially, just-a-word if there is neither a primary 
 *                                          nor a gloss annotation). The word 
 * @param  bool   $gloss_visualisation      If true, the tag doesn't have a prepresnded '_' when returned.
 * @param  bool   $disable_tag_for_aligned  If true, we are extracting a parallel-corpus printout
 *                                          that lacks the tag that is printed for the main-corpus;
 *                                          so for this call only, we override into just-a-word mode. 
 * @return array                            Numeric w/ indexes 0 => word, 1 => tag; the word is HTML-escaped, 
 *                                          the tag is HTML-escaped *And* has a '_' prepended (usually: see above).
 *                                          If we're in just-a-word mode, the tag is always an empty string. 
 */
function extract_cqp_word_and_tag($cqp_source_string, $gloss_visualisation = false, $disable_tag_for_aligned = false)
{
	global $Corpus;
	
	/* we know this bool is worked out once and for all for all non-parallel words, so LET'S PREMATURELY OPTIMISE */
	static $word_and_tag_present = NULL;
	
	/* on the first call only: only deduce the pattern once per run of the script */
	if (is_null($word_and_tag_present))
	{ 
		/* OK, this is how it works: if EITHER a primary tag is set, OR we are visualising 
		 * glosses, then we must split the token into word and tag using a regex.
		 * 
		 * If NEITHER of these things is the case, then we assume the whole thing we've been passed is a word.
		 * 
		 * Note that we assume that forward slash can be part of a word, but not part of a (primary) tag.
		 * [TODO: note this in the manual] 
		 */
		if (empty($Corpus->primary_annotation) && !$gloss_visualisation)
			$word_and_tag_present = false;
		else
			$word_and_tag_present = true;
	}
	
	if ($word_and_tag_present && !$disable_tag_for_aligned)
	{
		preg_match(CQP_INTERFACE_EXTRACT_TAG_REGEX, $cqp_source_string, $m);
		
		if (!isset($m[1], $m[2]))
		{
			/* a fallback case, for if the regular expression is derailed;
			 * should only happen with badly-encoded XML tags. */
			$word = '[UNREADABLE]';
			$tag = $gloss_visualisation ? '[UNREADABLE]' : '_UNREADABLE';
		}
		else
		{
			/* this will nearly always be the case. */
			$word = escape_html($m[1]);
			$tag = escape_html(($gloss_visualisation ? '' : '_') . $m[2]);
		}
	}
	else
	{
		$word = escape_html($cqp_source_string);
		$tag = '';
	}
	
	return array($word, $tag);
}

/**
 * Extracts the position indicators (text_id and, optionally, one other) and place them
 * in the given variables; scrub them from the CQP line and put the new CQP line
 * back in the variable the old one came from.
 * 
 * Returns nothing; modifies all its parameters.
 * 
 * Note that if the corpus is set up to not use a position label, that argument will be
 * set to an empty string.
 */ 
function extract_cqp_line_position_labels(&$cqp_line, &$text_id, &$position_label)
{
	global $Corpus;
	
	if ($Corpus->visualise_position_labels)
	{
		/* if a position label is to be used, it is extracted from between <text_id ...> and the colon. */
		if (0 < preg_match("/\A\s*\d+: <text_id (\w+)><{$Corpus->visualise_position_label_attribute} ([^>]+)>:/", $cqp_line, $m) )
		{
			$text_id = $m[1];
			$position_label = escape_html($m[2]);
			$cqp_line = preg_replace("/\A\s*\d+: <text_id \w+><$Corpus->visualise_position_label_attribute [^>]+>:/", '', $cqp_line);
		}
		else
		{
			/* Position label could not be extracted, so just extract text_id */
			preg_match("/\A\s*\d+: <text_id (\w+)><$Corpus->visualise_position_label_attribute>:/", $cqp_line, $m);
			$text_id = $m[1];
			$position_label = '';
			$cqp_line = preg_replace("/\A\s*\d+: <text_id \w+><$Corpus->visualise_position_label_attribute>:/", '', $cqp_line);
			/* note it IS NOT THE SAME as the "normal" case below: the s-att still prints, just wihtout a value */
		}
	}
	else
	{
		/* If we have no position label, just extract text_id */
		preg_match("/\A\s*\d+: <text_id (\w+)>:/", $cqp_line, $m);
		$text_id = $m[1];
		$position_label = '';
		$cqp_line = preg_replace("/\A\s*\d+: <text_id \w+>:/", '', $cqp_line);
	}
}


/** print a sorry-no-solutions page, shut down CQPweb, and end the script. */
function do_query_unsuccessful_page_and_exit($sorry_input = "no_solutions")
{
	global $Config;
	
	switch ($sorry_input)
	{
	case 'empty_postproc':
		$error_text = "No results were left after performing that operation!";
		break;
	case 'no_scope':
		$error_text = "Nowhere to search! There are no sub-parts of the corpus that satisfy all of the restrictions you specified.";
		break;
	case 'no_solutions':
		$error_text = "Your query had no results.";
		break;
	default:
		$error_text = "Something went wrong!";
		break;
	}
	
	echo print_html_header('Query unsuccessful', $Config->css_path);
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				<p class="spacer">&nbsp;</p>
				<p><?php echo $error_text; ?></p>
				<p class="spacer">&nbsp;</p>
			</th>
		</tr>
		<tr>
			<td class="concorderror" align="center">
				<p class="spacer">&nbsp;</p>
				<p>
					<b>Press [Back] and try again.</b>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>

	<?php
	
	echo print_html_footer('hello');
	cqpweb_shutdown_environment();
	exit(0);
}



function do_conc_popup_thin_control(QueryRecord $query_record)
{
	global $User;
	
	$num_of_hits_text = '(current no. of instances: ' . number_format((float)$query_record->hits()) . ')';
	
	?>

	<div id="thin-control" class="concordance-popup">
		<form action="concordance.php" method="get">
			<input type="hidden" name="qname" value="<?php echo $query_record->qname; ?>">
			<input type="hidden" name="newPostP" value="thin">
			<input type="hidden" name="newPostP_thinHitsBefore" value="<?php echo $query_record->hits(); ?>">
			
			<?php 
			if (isset($_GET['viewMode']))
				echo '<input type="hidden" name="viewMode" value="' . $_GET['viewMode'] . '">';
			if (isset($_GET['pp']))
				echo '<input type="hidden" name="pp" value="' . ((int) $_GET['pp']) . '">';
			echo "\n";
			/* does anything else from GET need passing on? */
// NB, TODO I probably ought to check that pp gets passed through other postprocesses, since it can't be set interactively...
			?>
		
			<table class="concordtable fullwidth">
				<tr>
					<th colspan="4" class="concordtable">
						Choose options for thinning your query <?php echo $num_of_hits_text; ?>
					</th>
				</tr>
				<tr>
					<td class="concordgrey">
						Thinning method:
					</td>
				</tr>
					<td class="concordgrey">
						<select name="newPostP_thinReallyRandom">
							<option value="0"<?php if ($User->thin_default_reproducible)  echo ' selected'; ?>>
								random (selection is reproducible)
							</option>
							<option value="1"<?php if (!$User->thin_default_reproducible) echo ' selected'; ?>>
								random (selection is not reproducible)
							</option>
						</select>
					</td>
					<td class="concordgrey">
						<input type="text" name="newPostP_thinTo">
						(number of instances or percentage)
					</td>
					<td class="concordgrey">
						<input type="submit" value="Thin this query">
					</td>
				</tr>
			</table>
	</form>
</div>

	<?php

}



/**
 * 
 * @param QueryRecord $query_record
 */
function print_collocation_warning_cell($query_record)
{
	global $Config;
	
	$issue_warning = false;

	/* if there is a subcorpus / restriction, check whether it has frequency lists */
	if (QueryScope::TYPE_WHOLE_CORPUS != $query_record->qscope->type) 
	{
		if (NULL === ($freqtable_record = $query_record->qscope->get_freqtable_record()))
			$issue_warning = true;
		else
			/* touch the freqtable as we will be using it soon. */
			touch_freqtable($freqtable_record->freqtable_name);
	}
	
	
	/* if either (a) it's the whole corpus or (b) a freqtable was found */
	if (!$issue_warning)
		return '';

	$tokens_searched = $query_record->get_tokens_searched_reduced();
	
	if ( $tokens_searched >= $Config->collocation_disallow_cutoff )
		/* we need to point out that the main corpus WILL be used */
		$s = '
			<tr>
				<td class="concorderror" colspan="3">
					The current set of hits was retrieved from a large subpart of the corpus 
					(' . number_format($tokens_searched) . ' words). No cached frequency data
					was found, and this is too much text for frequency lists to be compiled 
					on the fly in order to provide accurate measures of collocational strength. 
					<br>&nbsp;<br>
					The frequency lists for the main corpus will be used instead (less precise
					results, but probably reliable if word-frequencies are 
					relatively homogenous across the corpus).

					<input type="hidden" name="freqtableOverride" value="1">
				</td>
			</tr>
			';

	else if ( $tokens_searched >= $Config->collocation_warning_cutoff )
		/* we need a major warning */
		$s = '
			<tr>
				<td class="concorderror" colspan="2">
					The current set of hits was retrieved from a large subpart of the corpus 
					(' . number_format($tokens_searched) . ' words). No cached frequency data
					was found and frequency lists for the relevant part of the corpus will have to 
					be compiled in order to provide accurate measures of collocational strength. 
					Depending on the size of the subcorpus this may take several minutes and may
					use a lot of disk space.
					<br>&nbsp;<br>
					Alternatively, you can use the frequency lists for the main corpus (less precise
					results, but will run faster and is a valid option if word-frequencies are 
					relatively homogenous across the corpus).
				</td>
				<td class="concordgeneral">
					<select name="freqtableOverride">
						<option value="1" selected>Use main corpus frequency lists</option>
						<option value="0">Compile accurate frequency lists</option>
					</select>
				</td>
			</tr>
			';

	else
		/* a minor warning will do */
		$s = '
			<tr>
				<td class="concorderror" colspan="3">
					<strong>Note:</strong> The current set of hits was retrieved from a subpart 
					of the corpus (' . number_format($tokens_searched) . ' words). No cached frequency data 
					was found and frequency lists for the relevant part of the corpus will have to 
					be compiled in order to provide accurate measures of collocational strength. 
			 		This will increase the time needed for the calculation - please be patient.
				</td>
			</tr>
			';

	return $s;
}


function do_conc_popup_colloc_control(QueryRecord $query_record)
{
	global $Corpus;
	global $User;
	
	/* get a list of annotations and count them for this corpus */
	$num_annotation_rows = count($annotation_list = list_corpus_annotations($Corpus->name));

	?>
	

<div id="colloc-control" class="concordance-popup">

	<form class="greyoutOnSubmit" action="collocation.php" method="get">
		<input type="hidden" name="qname" value="<?php echo $query_record->qname; ?>">
		<table class="concordtable fullwidth" id="tableCollocProximity">
			<tr>
				<th colspan="3" class="concordtable">
					Choose settings for proximity-based collocations:
				</th>
			</tr>
			<tr>
				<td rowspan="<?php echo $num_annotation_rows; ?>" class="concordgrey">
					Include annotation:
				</td>

				<?php
				$i = 1;
				foreach($annotation_list as $handle => $description)
				{
					if ($handle == $Corpus->primary_annotation)
						$check = array(1 => ' checked', 0 => '');
					else
						$check = array(0 => ' checked', 1 => '');

					echo '<td class="concordgeneral" align="left">'
						, (!empty($description) ? escape_html($description) : $handle)
						, "</td>\n"
						;

					echo "\t\t\t\t\t\t<td class=\"concordgeneral\" align=\"center\">
							<input type=\"radio\" id=\"collAtt_$handle:1\" name=\"collAtt_$handle\" value=\"1\"{$check[1]}>
							<label for=\"collAtt_$handle:1\">Include</label>
							&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
							<input type=\"radio\" id=\"collAtt_$handle:0\" name=\"collAtt_$handle\" value=\"0\"{$check[0]}>
							<label for=\"collAtt_$handle:0\">Exclude</label>
						</td>
						</tr>\n"
						;
					if ($i < $num_annotation_rows)
						echo "\n\t\t\t<tr>\n";
					$i++;
				}
				?>

			<tr>
				<td class="concordgrey">Maximum window span:</td>
				<td class="concordgeneral" align="center" colspan="2">
					+ / -
					<select name="maxCollocSpan">
						<?php
						
						$span_option = array();
						for ($i = 4 ; $i < 11 ; $i++)
							$span_option[$i] = "<option>$i</option>";
						
						$user_pref_span = max($User->coll_from, $User->coll_to);
						
						/* note, we don't allow spans greater than 10. 
						 * HOWEVER, just in case a bigger number has snuck into the user_info table.... */ 
						if ( ! ($user_pref_span > 5 && $user_pref_span < 11) )
							$user_pref_span = 5;
						$span_option[$user_pref_span] = "<option selected>$user_pref_span</option>";

						echo "\n";
						foreach($span_option as $s_o)
							echo "\t\t\t\t\t", $s_o, "\n";
						?>
					</select>
				</td>
			</tr>

			<?php
			
			/*
			Other potential options: 
				s-attributes: the option of crossing or not crossing their boundaries
				(but this is way, way down the list of TODO)
				foreach xml annotation that is not a member of a family: two radio buttons: cross/don't cross. All default to dont cross.
			*/
			
			echo print_collocation_warning_cell($query_record);
			
			?>
			
			<tr>
				<th colspan="3" class="concordtable">
					<input type="submit" value="Create collocation database">
				</th>
			</tr>
		</table>
	</form>
	
	<?php 
	
	
	
	/* ---------------------------------------------------- *
	 * end of proximity control; start of syntactic control *
	 * ---------------------------------------------------- */
	
	
	
	// if false: I don't want this switched on just yet!
	// also note: the above has been improved a whole bunch, whereas what follows has not. 
	if (false) 
	{
		/* ultimate intention: this if will check whether any syntactic collocations are actually available */
		?> 
	
	
	<form class="greyoutOnSubmit" action="collocation.php" method="get">
		<table class="concordtable fullwidth" id="tableCollocSyntax">
			<tr>
				<th colspan="3" class="concordtable">
					Syntactic collocations - choose settings:
				</th>
			</tr>
			<tr>
				<?php
				/* get a list of annotations && the primary && count them for this corpus */
				$sql = "select * from annotation_metadata where corpus = '{$Corpus->name}'";
				$result_annotations = do_sql_query($sql);
				
				$num_annotation_rows = mysqli_num_rows($result_annotations);
				
// 				$sql = "select primary_annotation from corpus_info 
// 					where corpus = '{$Corpus->name}'";
// 				$result_fixed = do_sql_query($sql);
// 				/* this will only contain a single row */
// 				list($primary_att) = mysqli_fetch_row($result_fixed);
	
				?>
				
				<td rowspan="<?php echo $num_annotation_rows; ?>" class="concordgrey">
					Include annotation:
				</td>
	
				<?php
				$i = 1;
				while ($annotation = mysqli_fetch_assoc($result_annotations))
				{
					echo '<td class="concordgeneral" align="left">';
					if ($annotation['description'] != '')
						echo $annotation['description'];
					else
						echo $annotation['handle'];
	
					if ($annotation['handle'] == $Corpus->primary_annotation) 
						$check = array(1 => ' checked', 0 => '');
					else
						$check = array(0 => ' checked', 1 => '');
	
					echo "</td>
						<td class=\"concordgeneral\" align=\"center\">
							<input type=\"radio\" id=\"collAtt_{$annotation['handle']}:1\" 
								name=\"collAtt_{$annotation['handle']}\" value=\"1\"{$check[1]}>
							<label for=\"collAtt_{$annotation['handle']}:1\">Include</label>
							&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
							<input type=\"radio\" id=\"collAtt_{$annotation['handle']}:0\" 
								name=\"collAtt_{$annotation['handle']}\" value=\"0\"{$check[0]}>
							<label for=\"collAtt_{$annotation['handle']}:0\">Exclude</label>
						</td>
						</tr>
						";
					if ($i < $num_annotation_rows)
						echo '  <tr>';
					$i++;
				}
				?>
	
			<tr>
				<td class="concordgrey">Maximum window span:</td>
				<td class="concordgeneral" align="center" colspan="2">
					+ / -
					<select name="maxCollocSpan">
						<option>4</option>
						<option selected>5</option>
						<!-- shouldn't this be related to the default option? -->
						<option>6</option>
						<option>7</option>
						<option>8</option>
						<option>9</option>
						<option>10</option>
					</select>
				</td>
			</tr>
			<?php 
			/*
			Other potential options: 
			the one about crossing/not crossing s-attributes that was mentioned above prob does not apply here, since it is dependencybased....
			*/
			
			// TODO. Work out what kind of warning, if any, is needed here.
			//echo print_warning_cell($query_record);
			
			
			?>
			
			<tr>
				<th colspan="3" class="concordtable">
					<input type="submit" value="Create database of syntactic collocations">
				</th>
			</tr>
		</table>
		<?php echo "\n\t<input type=\"hidden\" name=\"qname\" value=\"{$query_record->qname}\">\n"; ?> 
	</form>	
	<?php
	
		/* end if syntactic collocations are available */
	}
	
	if (false) // also temp, the next html block will be unconditional
	{
	?>
	
	
	
	<table class="concordtable fullwidth">
		<tr>
			<td class="concordgrey" align="center">
				&nbsp;<br>
				<a href="" class="menuItem" id="linkSwitchControl">
					<!-- no inner HTML, assigned via JavaScript -->
				</a>
				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	
	<?php
	}
	?>
	
</div>
	
	<?php

}


