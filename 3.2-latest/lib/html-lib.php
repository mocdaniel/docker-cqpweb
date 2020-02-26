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
 * A file full of functions that generate handy bits of HTML.
 * 
 * MOST of the functions in this library *return* a string rather than echoing it.
 * 
 * So, the return value can be echoed (to browser), or stuffed into a variable.
 */






/** 
 * Call as show_var($x, get_defined_vars());
 * 
 * Omit 2nd arg in global scope.
 * 
 * THIS IS A DEBUG FUNCTION. 
 */
function show_var(&$var, $scope=false, $prefix='unique', $suffix='value')
{
	global $Config;
// 	if (!$Config->print_debug_messages)
// 		return;
	// TODO the above is actually a bit crap, cos it also prints all the SQL messages. 
	
	$vals = (is_array($scope) ? $scope : $GLOBALS);

	$old = $var;
	$var = $new = $prefix.random_int(0,PHP_INT_MAX).$suffix;
	$vname = false;
	foreach($vals as $key => $val) 
		if($val === $new) 
			$vname = $key;
	$var = $old;

	echo "\n<pre>-->\$$vname<--\n";
	var_dump($var);
	echo "</pre>";
}



function print_menurow_backend($link_handle, $link_text, $active, $script='index.php')
{
	// TODO, make tooltip p[ossible here?
	$s = "\n<tr>\n\t<td class=\"";
	if (!$active)
		$s .= "concordgeneral\">\n\t\t<a class=\"menuItem\" href=\"$script?ui=$link_handle\">";
	else 
		$s .= "concordgrey\">\n\t\t<a class=\"menuCurrentItem\">";
	$s .= "$link_text</a>\n\t</td>\n</tr>\n";
	return $s;
}


/**
 * Creates a table row for the index-page left-hand-side menu, which is either a link,
 * or a greyed-out entry if the variable specified as $current_query is equal to
 * the link handle. It is returned as a string, -not- immediately echoed.
 *
 * This is the version for the help page.
 */
function print_menurow_help($link_handle, $link_text, $active_handle, $script='help.php')
{
	return print_menurow_backend($link_handle, $link_text, ($active_handle == $link_handle), $script);
}

/**
 * Creates a table row for the index-page left-hand-side menu, which is either a link,
 * or a greyed-out entry if the $active_handle is equal to
 * the link handle. It is returned as a string, -not- immediately echoed.
 *
 * This is the version for a normal index.php.
 */
function print_menurow_index($link_handle, $link_text, $active_handle)
{
	return print_menurow_backend($link_handle, $link_text, ($active_handle == $link_handle));
}


/**
 * Creates a table row for the index-page left-hand-side menu, which is a section heading
 * containing the label as provided.
 */
function print_menurow_heading($label)
{
	return "\n<tr><th class=\"concordtable\"><a class=\"menuHeaderItem\">$label</a></th></tr>\n\n";
}


/**
 * Prints a count of bytes in an auto-adapting unit (bytes, KB, MB, GB, TB)
 * to one decimal place, with thousands-separating (by comma).
 * 
 * @param  int    $bytes   A count of bytes. (Should typically be positive!)
 *                         IF the number is huge enough, it might be a float 
 *                         rather than an int; that's not a problem
 * @return string          The rendered string. Contains no HTML. 
 */
function print_bytes_flexibly($bytes)
{
	/* orders of magnitude */
	static $ooms = [
			0 => ' bytes',
			1 => ' KB',
			2 => ' MB',
			3 => ' GB',
			4 => ' TB',
	];
	
	for ($p = 0 ; true ; $p++)
		if ( 4 == $p || $bytes < 1024 ** ($p+1) )
			return number_format($bytes/(1024**$p), $p==0 ? 0 : 1). $ooms[$p];
	/* test is inside loop so that passing it goes to the same return statement. */
}


/**
 * Echoes a string, but with HTML 'pre' tags (ideal for debug messages).
 */
function pre_echo($s)
{
	echo "\n\n<pre>\n", escape_html($s), "\n</pre>\n";
}
//TODO .... IO mostly forgot this existeed! See where I've used "pre"



/**
 * Print the "about CQPweb" block that appears at the bottom of the menu for both queryhome and userhome.
 * 
 * Returns string (does not echo automatically!) 
 */
function print_menu_aboutblock($what_ui)
{
	global $Config;
	
	$prefix = '';
	if (RUN_LOCATION_USERCORPUS  == $Config->run_location)
		$prefix = '../../../';
	
	return  print_menurow_heading('About CQPweb') . 
		<<<HERE

<tr>
	<td class="concordgeneral">
		<a id="menu:about:homepage" class="menuItem hasToolTip" href="$prefix../"
			data-tooltip="Go to the main homepage for this CQPweb server">
			CQPweb main menu
		</a>
	</td>
</tr>
<tr>
	<td class="concordgeneral">
		<a id="menu:about:account" class="menuItem hasToolTip" href="$prefix../usr"
			data-tooltip="Account control and your personal settings">
			Your user page
		</a>
	</td>
</tr>
<tr>
	<td class="concordgeneral">
		<a id="menu:about:help" class="menuItem hasToolTip" target="cqpweb_help_browser" href="help.php"
			data-tooltip="Open the help browser for this corpus">
			Help system
		</a>
	</td>
</tr>
<tr>
	<td class="concordgeneral">
		<a id="menu:about:videos" class="menuItem hasToolTip" target="_blank" href="http://www.youtube.com/playlist?list=PL2XtJIhhrHNQgf4Dp6sckGZRU4NiUVw1e"
			data-tooltip="CQPweb video tutorials (on YouTube)">
			Video tutorials
		</a>
	</td>
</tr>
HERE
		. print_menurow_index('who_the_hell', 'Who did it?',    $what_ui)
		. print_menurow_index('latest', 'Latest news',    $what_ui)
		. print_menurow_index('bugs', 'Report bugs',    $what_ui)
		;
}


/**
 * Create hidden input elements from a hash containing parameters to pass through.
 * 
 * @param  array  $hash      Asociative array (name=>value) from which to generate hidden inputs.
 * @param  string $form_id   If not empty, the hidden inputs will be flagged as belonging to the specified form.
 * @return string            Line of HTML hidden inputs. 
 */

function print_hidden_inputs($hash, $form_id = '')
{
	array_walk($hash, 
			function (&$value, $name, $form_id)
			{ 
				$value = '<input ' 
						. (empty($form_id) ? '' : ('form="'.$form_id.'" ')) 
						. 'type="hidden" name="' . $name 
						. '" value="' . $value 
						. '">'
						; 
			},
			$form_id);
	return implode('', $hash);
}

/**
 * Gets a string for a URL (without '&' before or after).
 * @param  array $hash    Associative array of parameters.
 * @param  bool  $html    Whether to be safe for HTML (e.g. for an href="" ). 
 *                        If we do, the value separator is &amp; . 
 *                        If not, it's a plain ampersand. Defaults false.
 * @return string         URL query fragment.
 */
function print_url_params($hash, $html = false)
{
	return http_build_query($hash, NULL, ($html ? '&amp;' : '&'));
}


/**
 * Produces three options for a select(d/a/da) to choose the CRLF for a data download.
 * 
 * @return string     HTML: three option elements; default set by the user setting, or guessed.
 */
function print_download_crlf_options()
{
	global $User;
	
	$da_select = array('d' => '', 'a' => '', 'da' => '');
	
	$opt_selected = ( $User->linefeed != 'au' ? $User->linefeed : guess_user_linefeed($User->username) ) ;
	$da_select[$opt_selected] = ' selected';
	
	return <<<END_OF_HTML

						<option value="d"  {$da_select['d']} >Macintosh (OS 9 and below)</option>
						<option value="da" {$da_select['da']}>Windows</option>
						<option value="a"  {$da_select['a']} >UNIX (incl. Mac OS X &amp; iOS/Android)</option>

END_OF_HTML;
}




/**
 * Gets a string with an <a><img></a> blob of the right kind for the runlocatuion.
 * @return string
 */
function print_rss_icon_link()
{
	global $Config;
	$url_begin = ( RUN_LOCATION_MAINHOME == $Config->run_location ? '' : '../' );
	return "<a href=\"{$url_begin}rss\"><img src=\"{$url_begin}css/img/feed-icon-14x14.png\"></a>";
}




/**
 * Create the content-specification rows of a metadata-design form.
 * For metadata table install and metadata template creation.
 * 
 * Requires the metadata-embiggen javascript.
 * 
 * TODO should the other 2 embiggenable forms be in the html-lib as well?
 */
function print_embiggenable_metadata_form($nrows = 5, $show_primary_classification = true, $form_id = NULL)
{
	global $Config;
	
	/* "None" is impossible in a metadata table; so is IDLINK (this kind of table is the target of an IDLINK!) */
				//TODO IDLINK *will* be possible for text_metadata! 
	$types_enabled = array(METADATA_TYPE_CLASSIFICATION, METADATA_TYPE_FREETEXT/*, METADATA_TYPE_UNIQUE_ID, METADATA_TYPE_DATE*/);
	/* note that unique ID and DATE are temporarily disabled; will be reinserted later. */
	
	$form_marker = '';
	if (!empty($form_id))
		$form_marker = 'form="'.$form_id.'" ';
	
	
	$options = '';
	foreach ($Config->metadata_type_descriptions as $value => $desc)
		if (in_array($value, $types_enabled))
			$options .= '<option value="' . $value . ($value == METADATA_TYPE_CLASSIFICATION ? '" selected>' : '">') . $desc . '</option>';

	$rows_html = <<<END

			<tr>
				<th class="concordtable">&nbsp;</th>
				<th class="concordtable">Handle for this field</th>
				<th class="concordtable">Description for this field</th>
				<th class="concordtable">Datatype of this field</th>
				COL_5_PLACEHOLDER
			</tr>

END;

	$rows_html = str_replace('COL_5_PLACEHOLDER', 
			($show_primary_classification 
					? '<th class="concordtable">Which field is the <br>primary classification?</th>' 
					: ''
					), $rows_html) ;

	for ( $i = 1 ; $i <= $nrows ; $i++ )
	{
		$rows_html .=  "
			<tr>
				<td class=\"concordgeneral\">Field $i</td>
				<td class=\"concordgeneral\" align=\"center\">
					<input {$form_marker}type=\"text\" id=\"fieldHandle$i\" name=\"fieldHandle$i\" maxlength=\"64\" onKeyUp=\"check_c_word(this)\">
				</td>
				<td class=\"concordgeneral\" align=\"center\">
					<input {$form_marker}type=\"text\" id=\"fieldDescription$i\" name=\"fieldDescription$i\" maxlength=\"255\">
				</td>
				<td class=\"concordgeneral\" align=\"center\">
					<select {$form_marker}id=\"fieldType$i\" name=\"fieldType$i\" align=\"center\">
						$options
					</select>
				</td>
				COL_5_PLACEHOLDER
			</tr>
		";
		$rows_html = str_replace('COL_5_PLACEHOLDER', 
				($show_primary_classification 
						? "<td class=\"concordgeneral\" align=\"center\"><input {$form_marker}type=\"radio\" id=\"primaryClassification:$i\" name=\"primaryClassification\" value=\"$i\"></td>" 
						: ''
						), $rows_html) ;
	}

	$n_of_cols = 4 + ($show_primary_classification ? 1 : 0);

	$rows_html .= <<<END

			<tr id="metadataEmbiggenRow">
				<td colspan="$n_of_cols" class="concordgrey" align="center">
					&nbsp;<br>
					<a onClick="add_metadata_form_row()" class="menuItem">[Embiggen form]</a>
					<br>&nbsp;
				</td>
			</tr>

END;

	return $rows_html;
}






/** 
 * Returns a 4-column file selector for files in a given folder (radio button style).
 *
 * There is no header row. (TODO though.) The columns are: 1) radio button, 2) filename,
 * 3) size in KB, 4) last-modification date.
 * 
 * @param  string $input_name       String to use as basis for the name/id attributes 
 *                                  of the form inputs. Default is 'dataFile'.
 * @param  string $form_target      String to use as the "form" attribute for inputs. 
 *                                  If an empty value is given (the default) no form 
 *                                  attributes will be inserted.
 * @param  string $from_dir_path    Path to the directory to get files from. If not 
 *                                  supplied, defaults to the config file's upload directory.
 * @param  bool   $show_compressed  Default false; if true, compressed files will not be
 *                                  skipped. 
 * @return string                   Resulting HTML (a tr element or sequence of tr elements). 
 */
function print_file_selector($input_name = 'dataFile', $form_target = false, $from_dir_path = NULL, $show_compressed = false)
{
	global $Config;
	
	if (is_null($from_dir_path))
		$path = $Config->dir->upload;
	else 
		$path = rtrim($from_dir_path, "\\/");

	if (! is_dir($path))
		$file_list = [];
	else
	{
		$file_list = scandir($path);
		natcasesort($file_list);
	}

if (!empty($form_target))
$target  = ' form="' . $form_target . '" ';
else
$target = '';

//TODO maybe this func should also create the column headers? Parameterisable?
//currently, every caller needs to know about the internals of 
		
	if (empty($file_list))
		return '<tr><td class="concordgeneral" align="center" colspan="4"><p>There are no files available.</p></td></tr>';

	$rows = '';
		
	foreach ($file_list as $f)
	{
		$file = "$path/$f";
		
		if (!is_file($file)) 
			continue;
		
		if (! $show_compressed)
			if ('.gz' == substr($f,-3)) 
				continue;

		$stat = stat($file);
		
		$rows .= '
		
				<tr>
					<td class="concordgeneral" align="center">
						<input type="radio" id="' .$input_name.':'.$f. '" name="' .$input_name.'" value="'.$f. '"'. $target .'> 
					</td>
					
					<td class="concordgeneral" colspan="2" align="left">
						<label for="' .$input_name.':'.$f. '">' . escape_html($f) . '</label>
					</td>
					
					<td class="concordgeneral" align="right">
						' .  number_format((float)$stat['size']/1024.0, 0) . ' 
					</td>
				
					<td class="concordgeneral" align="center">
						' . date(CQPWEB_UI_DATE_FORMAT, $stat['mtime']) . '
					</td>		
				</tr>

		';
	}
	return $rows;
}







// TODO move this into UiFrameWriter
/**
 * Creates an HTML page footer for all flavours of CQPweb page.
 * 
 * It takes a single argument: the required help page to link to
 * (see short codes in help.inc.php) -- if an empty value or nothing is passed,
 * no help link will appear. 
 * 
 * If "hello" is passed, a link to the "Hello" page appears.
 * 
 * @param  string $helplink  String with a short code for a help video to embed in the linked help page.
 * @return string            String containing the HTML page footer.
 */
function print_html_footer($helplink = false)
{
	global $User;
// 	global $Config;
	
	$v = CQPWEB_VERSION;

	if (!empty($helplink))
		$help_cell = '<a class="cqpweb_copynote_link" href="help.php?'
			. ($helplink == 'hello' ? 'ui=hello' : 'vidreq=' . $helplink) 
			. '" target="cqpweb_help_browser">' 
			. ($helplink ==  'hello' ? 'Help! on CQPweb' : 'Help! for this screen') 
			. '</a>'
			;
	else
		$help_cell = '&nbsp;';

	if (! (isset($User) && $User->logged_in))
		$lognote = 'You are not logged in';
	else
		$lognote = "You are logged in as user [{$User->username}]";

// 	$js_path = ($Config->run_location == RUN_LOCATION_MAINHOME ? 'jsc' : '../jsc');

// should there just be a "tooltip" pair of classs styles in the main CSS file? which are as modifiable as everything else?
// YES. But, do it this way for ow, till we are happy it works.

	// the "configurable things should be settable in the custom CSS file 
	// the rest should be in 0system. 
	

// 	<div id="templateForToolTip" class="floatingToolTip">
// 		<table class="floatingToolTipFrame">
// 			<tr>
// 				<td class="floatingToolTipTarget">
// 					&nbsp;
// 				</td>
// 			</tr>
// 		</table>
// 	</div>

	

// TEMP_END;
// 	}
	
	
	
// objecty funcs up top don't have this; when they do will be kinda like this:
//			$writer->do_tooltip_system_styles();
//			$writer->do_tooltip_system_script();
//			$writer->do_tooltip_template();
//			(or the like)

	
	
	return <<<RETURN_ME

	<hr>

	<table class="concordtable fullwidth">
		<tr>
			<td align="left" class="cqpweb_copynote" width="33%">
				CQPweb v$v &#169; 2008-2020
			</td>
			<td align="center" class="cqpweb_copynote" width="33%">
				$help_cell
			</td>
			<td align="right" class="cqpweb_copynote" width="33%">
				$lognote
			</td>
		</tr>
	</table>


	<div id="templateForToolTip" class="floatingToolTip">
		<table class="floatingToolTipFrame">
			<tr>
				<td class="floatingToolTipTarget">
					&nbsp;
				</td>
			</tr>
		</table>
	</div>

	</body>
	
</html>

RETURN_ME;
}


// TODO the print_header has too many arguments. Use an object with setters.
// TODO, split builtin JS / extra JS / external JS (external can be same as extra, if value contains '/', treat as external url, if not, tereat as extra.
// TODO, split internal / external CSS. Likewise w/extra. 
// obj writtern, but not in use yet. 
// (MAYBE: have the same obj do the footer, so that asll vars for the layout vcan be set at once??. 

/**
 * Object which generates the HTML for the beginning and end of a CQPweb UI "screen".
 */
class UiPageFramer
{
	private $title = '';
	
	private $helplink = '';
	
	private $js_builtin = [];
	private $js_extra   = [];
	
	private $css_path   = '';
	private $css_extra  = [];
	
	/**
	 * Optionally set title & css path; can also be done via setters. 
	 * @param string $title
	 * @param string $css_path
	 */
	function __construct($title = NULL, $css_path = NULL)
	{
		if (!is_null($title))
			$this->title = $title;
		if (!is_null($css_path))
			$this->css_path = $css_path;
	}
	
	/** @param  string $title      New title value. Not checked, not escaped. */
	function set_title($title) {$this->title = $title; }
	
	/** @param  string $css_url    New CSS URL value. Not checked, not escaped. */
	function set_css_url($css_url) {$this->css_url = $css_url; }
	
	/** 
	 * Sets a string with a short code for a help video to embed in the linked help page.
	 * @param   string $helplink   New help code. 
	 */
	function set_help($helplink) {$this->helplink = $helplink; }
	
	
	
	/**
	 * Specify extra CSS urls.
	 * 
	 * @param  string|array $css_path   An extra CSS URL. Or array of such. 
	 */		
	function add_css_extra($css_path)
	{
		if (is_array($css_path))
			$this->css_extra = array_merge($this->css_extra, $css_path);
		else 
			$this->css_extra[] = $css_path;
	}
	
	/**
	 * Specify that a JSC builtin should be declared within the header.
	 * 
	 * @param string|array $js_name   A name (file in normal location) without js suffix.
	 */
	function add_js_builtin($js_name)
	{
		if (is_array($js_name))
			foreach ($js_name as $jn)
				$this->js_builtin[] = $jn;
		else
			$this->js_builtin[] = $js_name;
		$this->js_builtin = array_unique($this->js_builtin);
	}
	
	
	/**
	 * Specify that 1+  extra js files should be declared within the header.
	 * 
	 * @param string|array $js_name   A name (file in normal location) or full URL. Or array of such. 
	 */	
	function add_js_extra($js_name)
	{
		if (is_array($js_name))
			foreach ($js_name as $jn)
				$this->js_extra[] = $jn;
		else
			$this->js_extra[] = $js_name;
		$this->js_extra = array_unique($this->js_extra);
	}
	
	function do_begin_page()
	{
		global $Config;
		global $User;
	
		/* preparation: css url, js directory */
		
		$js_builtin_path = ($Config->run_location == RUN_LOCATION_MAINHOME ? 'jsc' : '../jsc');
		
		/* override the CSS url with accessibility style option if enabled;
		 * isset() check necessary because in case of error this can be called before $User setup. */
		if (isset($User) && $User->logged_in && $User->css_monochrome)
			$this->css_url = ($Config->run_location == RUN_LOCATION_MAINHOME ? 'css' : '../css') . '/CQPweb-user-monochrome.css';
//TODO, think we need prefix here. 
		
		
		/* set the generic header before starting the write  */
		if (!headers_sent())
			header('Content-Type: text/html; charset=utf-8');
	
		?>
<!DOCTYPE html>
<html>
	<head>
		<title><?php echo $this->title; ?></title>
		<meta charset="UTF-8">
		
		<script       src="<?php echo $js_builtin_path; ?>/jquery.js"></script>
		<script defer src="<?php echo $js_builtin_path; ?>/always.js"></script>
		
		<?php
		/* jquery can't be deferred safely. Must be loaded synchronously. For reason why see:
		 *   https://stackoverflow.com/questions/37272020/jquery-loaded-async-and-ready-function-not-working
		 *   https://github.com/jquery/jquery/issues/3271
		 * but we can "defer" the rest, starting with "always", as above. */ 
		foreach ($this->js_builtin as $js)
			echo "\t\t<script defer src=\"$js_builtin_path/$js.js\"></script>\n";
		
		foreach ($this->js_extra as $js)
			if (false !== strpos($js, '/'))
				echo "\t\t<script defer src=\"$js\"></script>\n";
			else
				echo "\t\t<script defer src=\"$js_builtin_path/$js.js\"></script>\n";
		// TODO, different path for "extra". (new folder, jse?)
		
		
		/* js now done - move on to css. */
		
		echo (empty($this->css_url) ? "\n" : "\n\t\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$this->css_url\">\n"); 

		/* Extra CSS files, if supplied, add additional styles for whatever reason. They do not get overridden. */
		foreach($this->css_extra as $extra_url)
			echo "\t\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$extra_url\">\n";
		
		?>
		
	</head>
	<body>

		<?php
		
		echo "\n\n";
		
		if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match("/\bTrident\b/", $_SERVER['HTTP_USER_AGENT']))
		{
			?>
			<table class="concordtable fullwidth">
				<tr>
					<td class="concorderror">
						<b>You appear to be using Internet Explorer</b>.
						This browser is not fully compatible with CQPweb - some things may not work.
						Try Edge, Chrome or Firefox instead.
					</td>
				</tr>
			</table>

			<?php
		}
		/* HTML of the page beginning is all done. */
	}
	
	
	
	function do_end_page()
	{
		global $User;

		if (!empty($this->helplink))
			$help_cell = '<a class="cqpweb_copynote_link" href="help.php?'
				. ($this->helplink == 'hello' ? 'ui=hello' : 'vidreq=' . $this->helplink) 
				. '" target="cqpweb_help_browser">' 
				. ($this->helplink ==  'hello' ? 'Help! on CQPweb' : 'Help! for this screen') 
				. '</a>'
				;
		else
			$help_cell = '&nbsp;';

		if (! (isset($User) && $User->logged_in))
			$lognote = 'You are not logged in';
		else
			$lognote = "You are logged in as user [{$User->username}]";


		?>
	
		<hr>
		
		<table class="concordtable fullwidth">
			<tr>
				<td align="left" class="cqpweb_copynote" width="33%">
					CQPweb v<?php echo CQPWEB_VERSION; ?> &#169; 2008-2019
				</td>
				<td align="center" class="cqpweb_copynote" width="33%">
					<?php echo $help_cell, "\n"; ?>
				</td>
				<td align="right" class="cqpweb_copynote" width="33%">
					<?php echo $lognote, "\n"; ?>
				</td>
			</tr>
		</table>

		<?php

		echo "\n\t</body>\n</html>\n";

	// TODO: transfer across the new tooltip system from the footer fuinc.
		/* page footer done. */
	}
}







/*
some notyes on a dta structure:
each UI is defined as follows:

HANLDE               UI FUNCTION                                 MENU LINK TEXT       MENU LINK TIP               UI ACTIVE       MENU TYOPE
installCorpus        printquery_installcorpus_unindexed          Install new corpus   ''                          true~fasle      item ~ sub ~ heading

Then, we also need the memory structure. which should allow for non-appearing items. (e.g. deleteCorpus.

given an ordered array of these, we cabn iterate across to get 

*/

class UiMenuItem
{
// 	const ITEM_SUB = 1; (etc)
	public $handle = '';
	public $do_ui;
	public $menu_text = '';
	public $menu_tooltip = '';
	public $active = false;
	public $level;// set to the "normal" value. 
	
	// add: helpcode. 
	
	public function __construct($src_hash = [])
	{
		foreach ( [ 'handle','do_ui','menu_text','menu_tooltip','active','level' ] as $param )
			if (isset($src_hash[$param]))
				$this->$param = $src_hash[$param];
	}
}
// and a menu writer, fed one of these, 

// $def = [
// 	'showCorpora' => new UiMenuItem( ['handle'=>'showCorpora','menu_text'=>'Show corpora','do_ui'=>'do_ui_showcorpora','level'=>SOME_CONST] )
// ];
// $def['showCorpora']->active = true;
// $def[$what_ui]->do_ui();





















// ~TODO - mechanism for more global items to be added before print by the script. 

function print_application_meta()
{
	$url_prefix = get_url_prefix();

	return <<<END_HTML

	<!-- CQPweb application data -->
	<meta
		id="CQPwebGlobalApplicationData"
		name="application-name" 
		content="CQPweb"

		data-urlPrefix="$url_prefix"
	>

END_HTML;

}


// maybe a method on Config??????s
/** return value ends in a / unless empty. */
function get_url_prefix()
{
	global $Config;
	
if (!isset($Config->run_location)) { squawk (print_r(debug_backtrace(), true)); }
	switch($Config->run_location)
	{
	case RUN_LOCATION_MAINHOME:
		return '';
	case RUN_LOCATION_USERCORPUS:
		return '../../../../';
	default:
		return '../';
	}
}

// TODO this is still used,s o keep around till scripts have moved over the UiHeadWriter.

/**
 * Create an HTML header (everything from <html> to <body>,
 * which specified the title as provided, embeds a CSS link,
 * and finally imports the specified JavaScript files.
 * 
 * It alsom sets the Content-Type via header() 
 * 
 * @param  string $title         Note this WILL NOT be HTML escaped. Iff needed, caller must. 
 * @param  string $css_url       URL for CSS link.
 * @param  array  $js_scripts    Array of names of JS to include.
 * @param  array  $extra_css     Array of extra CSS to include.
 * @return string                The HTML header string ready to be printed. 
 */
function print_html_header($title, $css_url = '', $js_scripts = array(), $extra_css = array())
{
	global $User;

	/* preparation: css url, js directory */
	/*
	switch($Config->run_location)
	{
	case RUN_LOCATION_MAINHOME:
		$url_prefix = '';
		break;
	case RUN_LOCATION_USERCORPUS:
		$url_prefix = '../../../../';
		break;
	default:
		$url_prefix = '../';
		break;
	}*/

	$url_prefix = get_url_prefix();

	$js_path = $url_prefix . 'jsc';
	
	/* override the CSS url with accessibility style option if enabled;
	 * isset() check necessary because in case of error this can be called before $User setup. */
	if (isset($User) && $User->logged_in && $User->css_monochrome)
		$css_url = $url_prefix . 'css/CQPweb-user-monochrome.css';
	
	$hdr = "<!DOCTYPE html>\n<html>\n<head>\n\t<meta charset=\"UTF-8\">\n"
		. print_application_meta()
		. "\t<title>$title</title>\n"
		. (empty($css_url) ? '' : "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$css_url\">\n");
		;
	
	/* Extra CSS files, if supplied, add additional styles for whatever reason. They do not get overridden. */
	foreach($extra_css as $extra_url)
		$hdr .= "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"$extra_url\">\n";

	
	/* jQuery can't be deferred safely. Must be loaded synchronously. For reason why see:
	 *   https://stackoverflow.com/questions/37272020/jquery-loaded-async-and-ready-function-not-working
	 *   https://github.com/jquery/jquery/issues/3271
	 * but we can "defer" the rest, starting with "always/tooltip". As follows. */ 

	$hdr .=  "\t<script src=\"$js_path/jquery.js\"></script>"
		. "\n\t<script defer src=\"$js_path/always.js\"></script>\n"
		. "\n\t<script defer src=\"$js_path/tooltip.js\"></script>\n"
		;
		
	foreach ($js_scripts as $js)
		$hdr .= "\t<script defer src=\"$js_path/$js.js\"></script>\n";
	
	$hdr .= "\n</head>\n<body>\n";

		if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match("/\bTrident\b/", $_SERVER['HTTP_USER_AGENT']))
			$hdr .= <<<END_OF_WARNING
			<table class="concordtable fullwidth">
				<tr>
					<td class="concorderror">
						<b>You appear to be using Internet Explorer</b>.
						This browser is not fully compatible with CQPweb - some things may not work.
						Try Edge, Chrome or Firefox instead.
					</td>
				</tr>
			</table>

END_OF_WARNING;


	/* also set the generic header (will only be sent when something is echo'd, though) */
	if (!headers_sent())
		header('Content-Type: text/html; charset=utf-8');
	
	return $hdr;
}

/**
 * The login form is used in more than one place, so this function 
 * puts the code in just one place.
 * 
 * @return string    HTML (form element with table in it) of the login form.
 */
function print_login_form($location_after = false)
{
	// TODO - implement the measures discusswed here: https://blog.codinghorror.com/the-god-login/
	
	global $Config;
	
	if ($Config->run_location == RUN_LOCATION_USR)
		$pathbegin = '';	
	else if ($Config->run_location == RUN_LOCATION_MAINHOME)
		$pathbegin = 'usr/';
	else
		/* in a corpus, or in adm */
		$pathbegin = '../usr/';
	
	/* pass through a location after, if one was given */
	$input_loc_after = (empty($location_after) 
							? '' 
							: '<input type="hidden" name="locationAfter" value="'.escape_html($location_after).'">'
							);
		
	$uname_len = HANDLE_MAX_USERNAME;
	
	return <<<HERE

		<form action="{$pathbegin}useracct-act.php" method="post">
			<input type="hidden" name="userAction" value="userLogin">
			$input_loc_after
			<table class="basicbox" style="margin:auto">
				<tr>
					<td class="basicbox" width="170">Enter your username:</td>
					<td class="basicbox" width="420">
						<input type="text" name="username" maxlength="$uname_len" onKeyUp="check_c_word(this)">
					</td>
				</tr>
				<tr>
					<td class="basicbox">Enter your password:</td>
					<td class="basicbox" nowrap>
						<!-- maximum password length is 72 with the bcrypt algorithm -->
						<p style="display:inline-block;"><input id="passwordTextInput" type="password" name="password" maxlength="72"></p>
						<p id="capsLockHiddenPara" style="display:none"><strong>WARNING</strong>: Caps Lock seems to be on!</p>
					</td>
				</tr>
				<tr>
					<td class="basicbox">	
						<label for="persist:1">
							Stay logged in
							<br>
							on this computer:
						</label>
					</td>
					<td class="basicbox">
						<input type="checkbox" id="persist:1" name="persist" value="1">
					</td>
				</tr>
				<tr>
					<td class="basicbox" align="right">
						<input type="submit" value="Click here to log in">
					</td>
					<td class="basicbox" align="left">
						<input type="reset" value="Clear form">
					</td>
				</tr>
			</table>
		</form>
		<script>
		$( function () { add_check_caps_lock_to_input("passwordTextInput", "#capsLockHiddenPara");  } );
		</script>

HERE;

}




//TODO use this function for simple navlinks everywhere. 
/**
 * Creates simple navlinks to go the bottom of a table that prints opuit the content of a numbered
 * MySQL query (or similar).
 *  
 * @param  string $base_url         Basic URL to which parameters will be added. Should end in ? if no parameters, but not in &.
 * @param  int    $first_shown      1-based integer ix of the row of the query that is the FIRST currently shown.
 * @param  int    $first_unshown    1-based integer ix of the row of the query that is the FIRST after the LAST currently shown.
 * @param  int    $n_in_list        1-based integer ix of the LAST row of the query. (Get from mysqli_num_rows().)
 * @param  int    $per_page         Number of rows shown on each page.
 * @param  bool   $embed_per_page   Whetehr or not the per_page parameter should be embedded in the link.
 * @param  string $label_upwards    Label for the link on the left that moves back up the list.
 * @param  string $label_downwards  Label for the link on the right that moves down the list.
 * @return string                   HTML of a simple navlink bar as HTML table.  
 */
function print_simple_navlinks($base_url, $first_shown, $first_unshown, $n_in_list, $per_page, $embed_per_page, $label_upwards, $label_downwards)
{
	$navlinks = "\n\n" . '<table class="concordtable fullwidth"><tr><td class="concordgrey" width="50%" align="left';
	
	/* FURTHER UP the list */
	
	if ($first_shown > 1)
	{
		$new_begin_at = $first_shown - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="' . $base_url . (1 != $new_begin_at ? "&beginAt=$new_begin_at" : '');
		if ($embed_per_page)
			$navlinks .= '&pp=' . $per_page;
	}
	$navlinks .= '">&lt;&lt; [' . $label_upwards . ']';
	if ($first_shown > 1)
		$navlinks .= '</a>';
	

	$navlinks .= '</td><td class="concordgrey" width="50%" align="right';

	
	/* FURTHER DOWN the list */
	
	if ($first_unshown <= $n_in_list)
	{
		$navlinks .=  '"><a href="' . $base_url . '&beginAt=' . $first_unshown;
		if ($embed_per_page)
			$navlinks .= '&pp=' . $per_page;
	}
	$navlinks .= '">[' . $label_downwards . '] &gt;&gt;';
	if ($first_unshown < $n_in_list)
		$navlinks .= '</a>';
	
	$navlinks .= "</td></tr></table>\n\n";
	
	return $navlinks;
}

/**
 * An even simpler navlink system, for pagination where items-per-page is not user-settable.
 * 
 * @param  string $base_url          Basic URL to which parameters will be added. Should end in ? if no parameters, but not in &.
 * @param  int    $curr_page_no      Number of current page (where page numbers begin at 1).
 * @param  bool   $next_page_exists  False if we're on the last page; else true. 
 * @return string                    HTML of 3 x td elements, creating super-simple navigation.  
 */
function print_braindead_navlinks($base_url, $curr_page_no, $next_page_exists)
{
	/* work out page numbers */
	$marker = array( 'first' => '|&lt;', 'prev' => '&lt;&lt;', 'next' => "&gt;&gt;" );
	$nav_page_no = array(
		'first' => ($curr_page_no == 1 ? 0 : 1),
		'prev'  => $curr_page_no - 1,
		'next'  => ( (! $next_page_exists) ? 0 : $curr_page_no + 1)
		);
	/* all page numbers that should be dead links have zero in the above array */
	
	$div = ( false === strpos($base_url, '?') ? '?' : '&' ); 
	
	$string = '';

	foreach ($marker as $k => $label)
		$string .= '<td align="center" class="concordgrey"><b><a class="page_nav_links" '
				. ($nav_page_no[$k] ? "href=\"{$base_url}{$div}pageNo={$nav_page_no[$k]}\">$label</b></a></td>" : ">$label</a></td>")
				;

	return $string;
}




/**
 * Print out the system messages in HTML, including links to delete them. No return value.
 */
function display_system_messages()
{
	global $User;
	global $Config;
	
	/* weeeeeelll, this is unfortunately complex! */

	switch ($Config->run_location)
	{
	case RUN_LOCATION_ADM:
		$execute_path = 'index.php?admF=execute&function=delete_system_message';
		$after_path = urlencode("index.php?ui=systemMessages");
		break;
	case RUN_LOCATION_USR:
		$execute_path = '../adm/index.php?admF=execute&function=delete_system_message';
		$after_path = urlencode("../usr/");
		break;
	case RUN_LOCATION_MAINHOME:
		$execute_path = 'adm/index.php?admF=execute&function=delete_system_message';
		$after_path = urlencode("../");
		break;
	case RUN_LOCATION_USERCORPUS:
	case RUN_LOCATION_CORPUS:
		/* we are in a corpus */
		$execute_path = 'execute.php?function=delete_system_message';
		$after_path = urlencode(basename($_SERVER['SCRIPT_FILENAME']));
		break;
	}

	$result = do_sql_query("select * from `system_messages` order by `date_of_posting` desc");
	
	if (0 == mysqli_num_rows($result))
		return;
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="<?php echo ($User->is_admin() ? 3 : 2) ; ?>" class="concordtable">
				System messages
				<?php echo ($Config->rss_feed_available ? print_rss_icon_link() : ''), "\n";	?> 
			</th>
		</tr>
		
		<?php
		
		while ($o = mysqli_fetch_object($result))
		{
			?>
			<tr>
				<td class="concordgrey" rowspan="2" nowrap>
					<?php echo substr($o->date_of_posting, 0, 10), "\n"; ?>
				</td>
				<td class="concordgeneral">
					<strong><?php echo escape_html(stripslashes($o->header)); ?></strong>
				</td>
				
				<?php
				if ($User->is_admin())
				{
					echo '
					<td rowspan="2" class="concordgeneral" nowrap="nowrap" align="center">
						<a id="sysMsgDel:', $o->id, '" class="menuItem hasToolTip" data-tooltip="Delete this system message"'
						, 'href="', $execute_path , '&args='
						, $o->id
						, '&locationAfter=' , $after_path , '">
							[x]
						</a>
					</td>', "\n";
				}
				?>
			</tr>
			<tr>
				<td class="concordgeneral">
					<?php
					/* Sanitise, then add br's, then restore whitelisted links ... */
					echo "\n" //TODO -- we now have a function for this!!
						, preg_replace('|&lt;a\s+href=&quot;(.*?)&quot;\s*&gt;(.*?)&lt;/a&gt;|', 
										'<a href="$1">$2</a>', 
										str_replace("\n", '<br>', escape_html(stripslashes($o->content))))
						, "\n";
					?>
				</td>
			</tr>
			<?php
		}
		?>
	
	</table>
	
	<?php
}




/**
 * Print the "coming soon message", and end the HTML page.
 * NOTE: this does not shut down CQPweb. 
 */
function coming_soon_page()
{
	global $Config;
// example implementation of the new Ui obbj.	
// 	$html = new UiPageFramer('unfinished function!', $Config->css_path);
// 	$html->do_begin_page();
	
	echo print_html_header('unfinished function!', $Config->css_path);
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Unfinished function!</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				&nbsp;<br>
				<b>We are sorry, but that part of CQPweb has not been built yet.</b>
				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	<?php
	
	echo print_html_footer();
	
// 	$html->do_end_page();
}



function print_indexing_notes($corpus)
{
	$corpus = cqpweb_handle_enforce($corpus); 
	
	$content = "\n" . escape_html($corpus ? escape_html(get_corpus_info($corpus)->indexing_notes) : '') . "\n";
	
	return <<<END_OF_HTML

			<pre>$content</pre>

END_OF_HTML;
}







function do_bitui_access_statement($statement)
{
	$statement = escape_html($statement);
	
	$statement = unescape_escaped_a_href($statement);
	
	$statement = str_replace("\r\n", "\n", $statement);
	
	/* is it multi para? */
	if (false !== strpos($statement, "\n\n"))
		$statement = str_replace("\n\n", "\n</p>\n<p>\n", $statement);
	else 
		$statement = str_replace("\n", "<br>\n", $statement);

	$statement = "<p>\n$statement\n</p>\n";
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Information on access to this corpus
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>

				<?php echo $statement; ?>

				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
		
	<?php
}



//TODO make admin install form use this too!

/**
 * 
 * @param string $target_path   The folder to view.
 * @param string $form_id       If specified, all inputs will use this value for their explicitly declared "form" attribute.
 *                              If this is an empty value, there will be no "form" attribute. 
 * @param string $message       String (one of "admincorpus", "usercorpus" which determines the set of messages to print.
 */
function do_fileselector_table($target_path, $form_id = '', $message = 'admincorpus')
{
	global $Config;
	
	if ('admincorpus' == $message)
	{
		$msgtxt = 'The following files are available (uncompressed) in the upload area. Put a tick next to the files you want to index into CWB format.';
		$emptymsg = 'The uplload area is presently empty.';
	}
	else if ('usercorpus' == $message)
	{
		$msgtxt = 'Select files from your upload area to include in the corpus. If the files contain more data 
					than you are allowed to install, the overflow will be left out of the corpus.';
		$emptymsg = 'You don\'t currently have any uploaded files!';
	}
	else
		$msgtxt = 'Choose one or more of your files below.';
	
	if (empty($form_id))
		$form_decl = '';
	else 
		$form_decl = ' form="'.$form_id.'" ';
		
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="4" class="concordtable">
				Select files
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="4">
				<p class="spacer">&nbsp;</p>
				<p><?php echo $msgtxt; ?></p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<th class="concordtable">Include?</th>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
		</tr>
		
		<?php 
		
		if (false === ($file_list = scandir($target_path)) || 2 == count($file_list))
			$file_list = [];
		
		if (empty($file_list))
			echo "\n\t\t<tr>\n\t\t\t<td colspan=\"4\" class=\"concordgrey\"><p>$emptymsg</p></td>\n\t\t</tr>\n";

		natcasesort($file_list);
		
		foreach ($file_list as $f)
		{
			$file = "$target_path/$f";
			
			/* skip hidden files & directories */
			if ('.' == $f[0] || !is_file($file))
				continue;
			/* but symlinks is allowed ! */
	
			/* DO NOT skip compressed files: any modern cwb-encode version will support them
			 * .... unless we are running on Windows! in which case, cwb-encode can't use pipes. */
			if ($Config->cqpweb_running_on_windows)
				if (preg_match ( '/\.(gz|bz2)$/', $f ))
					continue;
	
			/* skip PHP scripts (regardless) */
			if ('.php' == substr($f, -4))
				continue;
	
			$stat = stat($file);
			
			?>
			
			<tr>
				<td class="concordgeneral" align="center">
					<?php 
					echo '<input type="checkbox" id="includeFile:' , urlencode($f) , '" name="includeFile" value="' , urlencode($f) , '"', $form_decl,  ">\n"; 
					?>
				</td>
				
				<td class="concordgeneral" align="left">
					<label for="includeFile:<?php echo urlencode($f);?>"><?php echo escape_html($f); ?></label>
				</td>
				
				<td class="concordgeneral" align="right">
					<?php echo number_format(round($stat['size']/1024.0, 0)), "\n"; ?>
				</td>
			
				<td class="concordgeneral" align="center">
					<?php echo date(CQPWEB_UI_DATE_FORMAT, $stat['mtime']), "\n"; ?>
				</td>		
			</tr>
			
			<?php
		}
		?>
		
	</table>
	
	<?php
}




/**
 * Create generic upload form. The file itself has parameter name "uploadedFile". 
 * 
 * @param string $script_address  Filename of the script to use as the "action" in the form.
 * @param string $instruction     If set, a table row will be added above the form containing 
 *                                this string as an instruction. 
 * @param array $hidden_params
 */
function do_ui_upload_form_as_table($script_address, $instruction = NULL, $hidden_params = [])
{
	if (empty($instruction))
	{
		global $User;
		if ($User->is_admin())
			$instruction_html = "Files uploaded to CQPweb can be used as the input to indexing, or as database inputs.";
		else
			$instruction_html = false;
	}
	else
		$instruction_html = escape_html($instruction);

	$param_html = print_hidden_inputs($hidden_params);
	
	?>

	<form enctype="multipart/form-data" action="<?php echo $script_address; ?>" method="post">
		<?php echo $param_html, "\n"; ?>
		<table class="concordtable fullwidth">
		
			<?php
			if ($instruction_html)
			{
				?>
				
				<tr>
					<td class="concordgrey" colspan="2">
						<p class="spacer">&nbsp;</p>
						<?php echo $instruction_html, "\n"; ?>
						<p class="spacer">&nbsp;</p>
					</td>
				</tr>
				
				<?php
			}
			?>
			
			<tr>
				<td class="concordgeneral" align="center">
					Choose a file to upload: 
				</td>
				<td class="concordgeneral" align="center">
					<input type="file" name="uploadedFile">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					<input type="submit" value="Upload file">
				</td>
			</tr>
		</table>
	</form>
	
	<?php
}


function do_ui_display_upload_area($username = false, $show_admin_area = false)
{
	global $Config;
	global $User;

	if (empty($username) && !$show_admin_area)
		$username = ( $User->logged_in ? $User->username : '' );
	
	/* only let people view their own upload area */
	if (! $User->is_admin())
		if ($show_admin_area || $User->username != $username)
			exiterror("You do not have permission to view that area.");

	if ($show_admin_area)
		$area_path = $Config->dir->upload;
	else
		if (false === ($area_path = get_user_upload_area($username)))
			exiterror("Unable to work out path to upload area to display.");

	$third_pers = $User->is_admin() && $User->username != $username;
	

	$file_list = scandir($area_path);
	$n_files = count($file_list) - 2;
	natcasesort($file_list);
	
	$file_action_param = ($show_admin_area  ? 'admF'       : 'uplAction');
	$file_action_delete = ($show_admin_area ? 'fileDelete' : 'userFileDelete');
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th colspan="7" class="concordtable">
				Files currently in the upload area
			</th>
		</tr>

		<?php
		echo "\n";
		if (0 == $n_files)
		{
			?>
			<tr>
				<td class="concordgeneral" align="center" colspan="7">
					<p class="spacer">&nbsp;</p>
					<p><?php echo $third_pers ? "The" : "Your"; ?> upload area is currently empty.</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<?php
		}
		else
		{
			?>
			<tr>
				<th class="concordtable">Filename</th>
				<th class="concordtable">Size (K)</th>
				<th class="concordtable">Date modified</th>
				<th colspan="<?php echo $show_admin_area ? '4' : '2'; ?>" class="concordtable">Actions</th>
			</tr>
			<?php
			
		}
		
		$total_files = 0;
		$total_bytes = 0;
		/* we keep a running total to avoid a second directory sweep from calling a separate function */

		foreach ($file_list as &$f)
		{
			$file = "$area_path/$f";
			
			if (!is_file($file)) 
				continue;
			
			$file_is_compressed = ( (substr($f,-3) === '.gz') ? true : false);

			$stat = stat($file);
			
			$total_files++;
			$total_bytes += $stat['size'];
			
			?>

			<tr>
				<td class="concordgeneral" align="left"><?php echo escape_html($f); ?></td>
				
				<td class="concordgeneral" align="right">
					<?php echo number_format(round($stat['size']/1024, 0)), "\n"; ?>
				</td>
				
				<td class="concordgeneral" align="center"><?php echo date(CQPWEB_UI_DATE_FORMAT, $stat['mtime']); ?></td>
				
				<td class="concordgeneral" align="center">
					<?php 
					if ($file_is_compressed)
						echo "&nbsp;\n";
					else
						echo '<a class="menuItem" href="index.php?', ($show_admin_area ? 'admF=fileView' : 'ui=userFilePreview'), '&filename=' 
							, escape_html($f) 
							, '">[View]</a>', "\n"
							;
					?>
				</td>
				
				<?php
				
				if ($show_admin_area)
				{
					?>
					
					<td class="concordgeneral" align="center">
						<?php 
						echo '<a class="menuItem" href="index.php?', $file_action_param, "="
							, ($file_is_compressed 
								? 'fileDecompress&filename=' . escape_html($f) . '">[Decompress]'
								: 'fileCompress&filename='   . escape_html($f) . '">[Compress]'
								)
							, "</a>\n"
							;
						?>
					</td>
					<td class="concordgeneral" align="center">
						<?php 
						if ($file_is_compressed)
							echo "&nbsp;\n";
						else
							echo '<a class="menuItem" href="index.php?', $file_action_param, '=fileFixLinebreaks&filename=' 
								, escape_html($f) , "\">[Fix linebreaks]</a>\n"
 								;
						?>
					</td>
					
					<?php
				}
				?>
				
				<td class="concordgeneral" align="center">
					<a class="menuItem" href="<?php 
						echo ($show_admin_area ? 'index.php?' : 'upload-act.php?')
							, $file_action_param,'=',$file_action_delete
							, '&filename=', escape_html($f)
							;
					?>">[Delete]</a>
				</td>
			</tr>

			<?php
		}
		?>
		
		<tr>
			<td align="left" class="concordgrey" colspan="7">
				<?php 
				echo ( $show_admin_area ? '' : ($third_pers ? '<p>This user&rsquo;s ' : '<p>Your '). 'upload area currently contains ' )
					, number_format($total_files, 0) , ' files (' , number_format($total_bytes/1024.0, 1) , " KB)</p>\n"
 					; 
				if (!$show_admin_area)
				{
					if (0 == ($max = user_upload_space_limit($username)))
						$max = 0.0000000000000000000001;
					else
						$max = (float)$max;
					echo "\t\t\t\t<p class=\"spacer\">&nbsp;</p>\n\t\t\t\t<p>"
 						, $third_pers ? 'This user is ' : 'You are '
 						, ' currently using ', number_format((float)$total_bytes/$max, 1), '% of '
 						, $third_pers ? 'their' : 'your' , ' maximum limit of '
						, number_format($max/(1024.0*1024.0), 0)
						, " megabytes.\n"
						;
				}
				?>
			</td>
		</tr>
	</table>

	<?php
}




/**
 * Creates a message box from $_GET "&message=" at the point where it's called.
 * 
 *  Used in adminhome: could also be used elsewhere.
 */
function do_ui_message()
{
	$msg = isset($_GET['message']) ? escape_html($_GET['message']) : '';
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				CQPweb says:
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				<p align="center">
					<?php echo $msg, "\n"; ?>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>

	<?php
}


/**
 * Creates and renders a table listing corpus details. Parameters allow details to be set for use
 * in either the administrator's or user's interface.
 * 
 * @param string $which_ui                   One of the folllowing : 'admin_usercorpora', 'admin_systemcorpora, 'usercorpora'.
 * @param string $username                   If which = user_corpora: do we want the corpora of a specific user?
 */
function do_ui_showcorpora($which_ui, $username = '')
{
	global $Config;
	global $User;

	if (!$User->is_admin())
		$which_ui = 'usercorpora';

	
	$hdr_txt_map = array(
			'admin_usercorpora'    => 'Showing user-corpora installed by ' . (empty($username)?'all users':"user $username"),
			'admin_systemcorpora'  => 'Showing installed corpora',
			'usercorpora'          => 'Your installed corpora',
	);
	$hdr_txt = $hdr_txt_map[$which_ui];

	$show_disk_space_divided = ('usercorpora' != $which_ui);
	$owner_col               = ('admin_usercorpora' == $which_ui && empty($username));
	
	$ncols = 7;
	if ($show_disk_space_divided)
		$ncols++;
	if ($owner_col)
		$ncols++;
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="<?php echo $ncols; ?>"><?php echo $hdr_txt; ?></th>
		</tr>
		<tr>
			<th class="concordtable" rowspan="2">Corpus</th>
			
			<?php
			if ($owner_col)
			{
				?>
				<th class="concordtable" rowspan="2">Owner</th>
				<?php
			}
			?>
			
			<th class="concordtable" rowspan="2">Indexing date</th>
			<th class="concordtable" colspan="3">Size</th>
			<th class="concordtable"<?php if ($show_disk_space_divided) echo ' colspan="2"'; else echo ' rowspan="2"';?>>Disk space</th>
			<th class="concordtable" rowspan="2">Actions</th>
		</tr>
		<tr>
			<th class="concordtable">Tokens</th>
			<th class="concordtable">Types</th>
			<th class="concordtable">Texts</th>
			
			<?php
			if ($show_disk_space_divided)
			{
				?>

				<th class="concordtable">Indexes</th>
				<th class="concordtable">Freq tables</th>

				<?php
			}
			?>
			
		</tr>

	<?php
	
	$mb_div = 1024.0 * 1024.0;
	$total_index_size = 0;
	$total_freqtable_size = 0;
	
	if ('admin_systemcorpora' == $which_ui)
		$array_of_corpus_objs = get_all_corpora_info();
	else
		$array_of_corpus_objs = get_all_user_corpora_info($username, $User->is_admin());
		/* admin uswer can see unfinished (invisible) corpora; owner can't */

	if (empty($array_of_corpus_objs))
		echo "\n\t<tr>\n\t\t"
 			, '<td class="concordgrey" colspan="', $ncols, '"><p class="spacer">&nbsp;</p><p>'
 			, ('usercorpora' != $which_ui ? 'No corpora to show.' : 'You do not have any installed corpora.')
 			, '</p><p class="spacer">&nbsp;</p></td>'
			, "\n\t</tr>\n"
	 		;
	
	foreach ($array_of_corpus_objs as $curr_corpus => $r)
	{
		$owned = !empty($r->owner);
		$ui_url = ($owned ? get_user_corpus_web_path($r->id, $r->owner)  : "../$curr_corpus");
//show_var($ui_url);
		
// 		$javalinks = ' onmouseover="corpus_box_highlight_on(\''  . $curr_corpus 
// 			. '\')" onmouseout="corpus_box_highlight_off(\'' . $curr_corpus 
// 			. '\')" '
//  			;
		
 		$label = escape_html($r->title) . ($User->is_admin() ? (' (' . $curr_corpus . ')') : '');

		?>
		
		<tr>
			<td class="concordgeneral" <?php echo "id=\"corpusCell_$curr_corpus\""; ?>>
				<strong>
					<a id="goC:<?php echo $curr_corpus; ?>" class="menuItem hasToolTip" 
						data-tooltip="<?php echo escape_html($r->title) ; ?>" href="<?php echo $ui_url; ?>"
					>
						<?php echo $label, "\n"; ?>
					</a>
				</strong>
			</td>
			
			<?php
			if ($owner_col)
			{
				?>
				<td class="concordgeneral" align="center">
					<?php echo $r->owner, "\n"; ?>
				</td>
				<?php
			}
			?>

			<td class="concordgeneral" align="center">
				<?php echo (sql_timestamp_is_zero($r->date_of_indexing) ? '(unrecorded)' : substr($r->date_of_indexing,0,-3)), "\n"; ?>
			</td>

			<td class="concordgeneral" align="right">
				<?php echo number_format($r->size_tokens), "\n"; ?>
			</td>

			<td class="concordgeneral" align="right">
				<?php echo number_format($r->size_types), "\n"; ?>
			</td>

			<td class="concordgeneral" align="right">
				<?php echo number_format($r->size_texts), "\n"; ?>
			</td>
			
			<?php

			$total_index_size += $r->size_bytes_index;
			$total_freqtable_size += $r->size_bytes_freq;
			$joinsize = (float)($r->size_bytes_index + $r->size_bytes_freq);
			
			if ($show_disk_space_divided)
			{
				?>

				<td class="concordgeneral" align="right">
					<?php echo number_format($r->size_bytes_index/$mb_div, 1), " MB\n"; ?>
				</td>

				<td class="concordgeneral" align="right">
					<?php echo number_format($r->size_bytes_freq/$mb_div, 1), " MB\n"; ?>
				</td>
				
				<?php
			}
			else
			{
				?>

				<td class="concordgeneral" align="right">
					<?php echo number_format($joinsize/$mb_div, 0), " MB\n"; ?>
				</td>

				<?php
			}
			?>

			<td class="concordgeneral" align="center">
				<a class="menuItem corpusToggle" 
					data-corpus="<?php echo $curr_corpus; ?>" 
					href="index.php?ui=deleteCorpus&corpus=<?php echo $curr_corpus; ?>">
					[Delete corpus]
				</a>
			</td>
		</tr>
		
	<?php
	}
	?>
	
	</table>
	
	<?php
	
	if ('usercorpora' != $which_ui)
	{
		/* this implies that we want 2 x size columns */
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">Disk use totals</th>
			</tr>
			<tr>
				<td class="concordgrey">Total index data disk use</td>
				<td class="concordgeneral" align="right">
					<?php echo number_format($total_index_size/$mb_div, 1); ?> MB
				</td>
			</tr>
			<tr>
				<td class="concordgrey">Total frequency table disk use</td>
				<td class="concordgeneral" align="right">
					<?php echo number_format($total_freqtable_size/$mb_div, 1); ?> MB
				</td>
			</tr>
			<tr>
				<td class="concordgrey">Grand total </td>
				<td class="concordgeneral" align="right">
					<?php echo number_format(($total_index_size+$total_freqtable_size)/$mb_div, 1); ?> MB
				</td>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2">
					<p class="spacer">&nbsp;</p>
					<p>
						Important note: the disk usage estimates for corpus indexes <b>do not include</b>
						the data for corpora which were inserted into CQPweb from pre-indexed CWB corpora,
						since these indexes are not under CQPweb's control, 
						even if they are physically stored in CQPweb's designated index-data folder
						(though the frequency-table indexes for all such &ldquo;external&rdquo; corpora 
						<em>are</em> counted).
					</p>
					<p>
						(For reference: the total disk space taken up by the index-data directory is 
						<b><?php echo number_format(recursive_sizeof_directory($Config->dir->index)/(1024*1024*1024),1); ?> GB</b>.)
					<p>
						User data such as queries, databases, and subcorpora are not counted towards the disk use total.
						Moreover, there is a certain amount of system overhead for the MySQL database.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
		
		<?php
	}
	else
	{
		$allowance = get_user_corpus_disk_allocation($User->username);
		$overflow = ($total_index_size+$total_freqtable_size) - $allowance;

		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">Your corpus data storage allowance</th>
			</tr>
			<tr>
				<td class="concordgrey">Amount of disk space your installed corpora currently take up:</td>
				<td class="concordgeneral" align="right">
					<?php echo number_format(($total_index_size+$total_freqtable_size)/$mb_div, 0); ?> MB
				</td>
			</tr>
			<tr>
				<td class="concordgrey">Your disk space allocation for installed corpora:</td>
				<td class="concordgeneral" align="right">
					<?php echo number_format($allowance/$mb_div, 0); ?> MB
				</td>
			</tr>
			
			<?php
			if (0 < $overflow)
			{
				?>
				
				<tr>
					<td class="concorderror" align="left" colspan="2">
						<p>
							The corpora you have installed now exceed your disk space allocation 
							by <strong><?php echo number_format($overflow/$mb_div, 0);?> MB</strong>.
						</p>
						<p>
							You will not be able to access any of your installed corpora until you have freed some disk space 
							(by deleting one or more corpora).
						</p>
					</td>
				</tr>
				
				<?php
			}
			?>

		</table>
		
		<?php
	}
}




/**
 * Creates a javascript function with $n password candidates that will write
 * one of its candidates to id=newPassword on each call.
 */
function print_javascript_for_password_insert($password_function = NULL, $n = 49)
{
	global $Config;

	if (empty($password_function))
		$password_function = $Config->create_password_function;

	$raw_array = array();

	foreach ($password_function($n) as $pwd)
		$raw_array[] = "'$pwd'";
	$array_initialisers = implode(',', $raw_array);

	return "


	<script>

	$(document).ready(function() {  window.cqpwebInsertPasswordData = new Array( $array_initialisers );  } );

	</script>

	";
}


/**
 * password_insert_internal is the default function for CQPweb candidate passwords.
 * 
 * 
 * Whatever function you use must be in a source file included() in adminhome.inc.php
 * (i.e. you need to hack the code a little bit).
 * 
 * All password-creation functions must return an array of n candidate passwords.
 * 
 */
function password_insert_internal($n)
{
	$pwd = array();
	
	for ( $i = 0 ; $i < $n ; $i++ )
		$pwd[$i] = sprintf("%c%c%c%c%d%d%c%c%c%c=%c%c%c%c%d%d%c%c%c%c",
						random_int(0x61, 0x7a), random_int(0x61, 0x7a), random_int(0x61, 0x7a), random_int(0x61, 0x7a),
						random_int(0,9), random_int(0,9),
						random_int(0x61, 0x7a), random_int(0x61, 0x7a), random_int(0x61, 0x7a), random_int(0x61, 0x7a),
						random_int(0x61, 0x7a), random_int(0x61, 0x7a), random_int(0x61, 0x7a), random_int(0x61, 0x7a),
						random_int(0,9), random_int(0,9),
						random_int(0x61, 0x7a), random_int(0x61, 0x7a), random_int(0x61, 0x7a), random_int(0x61, 0x7a)
						); 
	return $pwd;
}










/*
 * ==========================================================================================
 * functions dealing with "skins" i.e. the CSS files that implement different colour schemes.
 * ==========================================================================================
 */





// not in use, but once it is 
/**
 * Converts a colour scheme identifier into an array mapping CSS variables to create.
 * 
 * @param  string $scheme  The colour scheme.
 * @return array           An array containing CSS "variables". Or false if neither tuype of non-URL scheme sd ptrdrny.  
 */
function translate_colscheme_to_css_variables($scheme)
{
	/* a "scheme" begins with some punc mark to avoid confusion with a URL.
	 * if it's ; then the remainder of the string is an internal identifier.
	 * if it's a # then the remainder is a sequence of 3 rgb colours in hex mode:
	 * light colour, strong colour, error colour. The first does not have an extra hash.
	 * 
	 * IE....    #aabbcc#aabbcc#aabbcc
	 * OR....    ;neon
	 */
	
	
	/* fg = normal foreground; hd = header cells; er = error cells. */
	
	if (';' == $scheme[0])
	{
		switch (substr($scheme, 1))
		{
		case 'blue':
			$fg = '#ddddff';
			$hd = '#bbbbff';
			$er = '#ffeeaa';
			break;

		case 'yellow':
			$fg = '#ffeeaa';
			$hd = '#ffbb77';
			$er = '#ddddff';
			break;

		case 'green':
			$fg = '#ccffcc';
			$hd = '#66cc99';
			$er = '#ffeeaa';
			break;

		case 'red':
			$fg = '#ffcfdd';
			$hd = '#ff8899';
			$er = '#ddddff';
			break;

		case 'brown':
			$fg = '#eeaa77';
			$hd = '#cd663f';
			$er = '#ffeeaa';
			break;

		case 'purple':
			$fg = '#dfbaf5';
			$hd = '#be71ec';
			$er = '#ffeeaa';
			break;

		case 'navy':
			$fg = '#33aadd';
			$hd = '#0066aa';
			$er = '#ffeeaa';
			break;

		case 'lime':
			$fg = '#ecff6f';
			$hd = '#b9ff6f';
			$er = '#00ffff';
			break;

		case 'aqua':
			$fg = '#b0ffff';
			$hd = '#00ffff';
			$er = '#ffeeaa';
			break;

		case 'neon':
			$fg = '#ffa6ff';
			$hd = '#ff00ff';
			$er = '#00ff00';
			break;

		case 'dusk':
			$fg = '#d1a4ff';
			$hd = '#8000ff';
			$er = '#ffeeaa';
			break;

		case 'gold':
			$fg = '#c1c66c';
			$hd = '#808000';
			$er = '#80ffff';
			break;

		case 'rose':
			$fg = '#edc9af';
			$hd = '#bd6d5d';
			$er = '#827839';
			break;

		case 'teal':
			$fg = '#d0ffff';
			$hd = '#009090';
			$er = '#e67451';
			break;


// 		case '':
// 			$fg = '#';
// 			$hd = '#';
// 			$er = '#';
// 			break;

		}
		return array(
				'--colour-fg-light'  => $fg,
				'--colour-fg-strong' => $hd,
				'--colour-error'     => $er,
		);
	}
	else if ('#' == $scheme[0])
		return array (
				'--colour-fg-light'  => substr($scheme,  0, 7),
				'--colour-fg-strong' => substr($scheme,  7, 7),
				'--colour-error'     => substr($scheme, 14, 7),
		);
	else
		return false;
		/* we assume the ascheme is a raw URL of a CSS file. */	

}


function print_css_variables_from_colscheme($scheme)
{
	$vars = translate_colscheme_to_css_variables($scheme);
	if (empty($vars))
		return '';
	$varblock = "\n";
	foreach(translate_colscheme_to_css_variables($scheme) as $varname => $value)
		$varblock .= "\t\t$varname: $value;\n";
	
	return <<<END

		<style>
		:root {$varblock}
		</style>

END;
}


/**
 * Translate a colour scheme into the needed chunks for the header  
 */
function parse_colscheme($scheme)
{
	$standard = print_css_variables_from_colscheme($scheme);
	
	if (empty($standard))
		return '' . $scheme . ''; // TODO - do we wrap the link here? elsewhere?
	else 
		return $standard;
	
}





function import_css_file($filename)
{
	global $Config;
	global $User;
	
	if (!$User->is_admin())
		return;
	
	$orig = "{$Config->dir->upload}/$filename";
	$new = "../css/extra/$filename";
	
	if (is_file($orig))
	{
		if (is_file($new))
			exiterror("A CSS file with that name already exists. File not copied.");
		else
			copy($orig, $new);
	}
}



/**
 * Installs the default "skins" ie CSS colour schemes.
 * 
 * Note, doesn't actually specify that one of these should be used anywhere
 * -- just makes them available.
 */
function css_regenerate_skinfiles()
{
	global $User;
	
	if (!$User->is_admin())
		return;
	
	// TODO - we can now do variant tooltip colours; add this at some point.
	// TODO monochrome has not been checked out in a while (and not since tooltips were styled here); make sure they still work!  
	// TODO this could be coded more compactly...
	// Or even, have a genric CSS, and insert colour variables at the start? -- yes this last. "colscheme" fucnitons address this. 
	$yellow_pairs = array(
		'#ffeeaa' =>	'#ddddff',		/* error */
		'#bbbbff' =>	'#ffbb77',		/* dark */
		'#ddddff' =>	'#ffeeaa'		/* light */
		);
	$green_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#66cc99',		/* dark */
		'#ddddff' =>	'#ccffcc'		/* light */
		);
	$red_pairs = array(
		'#ffeeaa' =>	'#ddddff',		/* error */
		'#bbbbff' =>	'#ff8899',		/* dark */
		'#ddddff' =>	'#ffcfdd'		/* light */
		);
	$brown_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#cd663f',		/* dark */
		'#ddddff' =>	'#eeaa77'		/* light */
		);
	$purple_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#be71ec',		/* dark */
		'#ddddff' =>	'#dfbaf5'		/* light */
		);
	$navy_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#0066aa',		/* dark */
		'#ddddff' =>	'#33aadd'		/* light */
		);
	$lime_pairs = array(
		'#ffeeaa' =>	'#00ffff',		/* error */
		'#bbbbff' =>	'#B9FF6F',		/* dark */
		'#ddddff' =>	'#ECFF6F'		/* light */
		);
	$aqua_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#00ffff',		/* dark */
		'#ddddff' =>	'#b0ffff'		/* light */
		);
	$neon_pairs = array(
		'#ffeeaa' =>	'#00ff00',		/* error */
		'#bbbbff' =>	'#ff00ff',		/* dark */
		'#ddddff' =>	'#ffa6ff'		/* light */
		);
	$dusk_pairs = array(
		'#ffeeaa' =>	'#ffeeaa',		/* error */
		'#bbbbff' =>	'#8000ff',		/* dark */
		'#ddddff' =>	'#d1a4ff'		/* light */
		);
	$gold_pairs = array(
		'#ffeeaa' =>	'#80ffff',		/* error */
		'#bbbbff' =>	'#808000',		/* dark */
		'#ddddff' =>	'#c1c66c'		/* light */
		);
	$rose_pairs = array(
		'#ffeeaa' =>	'#827839',		/* error */
		'#bbbbff' =>	'#bd6d5d',		/* dark */
		'#ddddff' =>	'#edc9af'		/* light */
		);
	$teal_pairs = array(
		'#ffeeaa' =>	'#e67451',		/* error */
		'#bbbbff' =>	'#009090',		/* dark */
		'#ddddff' =>	'#d0ffff'		/* light */
		);

	
	
	$css_file = print_css_file_content();
	
	file_put_contents('../css/CQPweb-blue.css'    , $css_file);
	file_put_contents('../css/CQPweb-yellow.css'  , strtr($css_file, $yellow_pairs));
	file_put_contents('../css/CQPweb-green.css'   , strtr($css_file, $green_pairs));
	file_put_contents('../css/CQPweb-red.css'     , strtr($css_file, $red_pairs));
	file_put_contents('../css/CQPweb-brown.css'   , strtr($css_file, $brown_pairs));
	file_put_contents('../css/CQPweb-purple.css'  , strtr($css_file, $purple_pairs));
	file_put_contents('../css/CQPweb-navy.css'    , strtr($css_file, $navy_pairs));
	file_put_contents('../css/CQPweb-lime.css'    , strtr($css_file, $lime_pairs));
	file_put_contents('../css/CQPweb-aqua.css'    , strtr($css_file, $aqua_pairs));
	file_put_contents('../css/CQPweb-neon.css'    , strtr($css_file, $neon_pairs));
	file_put_contents('../css/CQPweb-dusk.css'    , strtr($css_file, $dusk_pairs));
	file_put_contents('../css/CQPweb-gold.css'    , strtr($css_file, $gold_pairs));
	file_put_contents('../css/CQPweb-rose.css'    , strtr($css_file, $rose_pairs));
	file_put_contents('../css/CQPweb-teal.css'    , strtr($css_file, $teal_pairs));

	
	/* creating the monochrome version for users with vision problems is a spot trickier... */
	$monochrome = $css_file;
	/* start by changing all colors to black. Then switch all background colors to white. */
	$monochrome = preg_replace('|color[ \t]*:.*?;|', 'color: black;', $monochrome);
	$monochrome = preg_replace('|background-color[ \t]*:[ \t]*black|', 'background-color: white', $monochrome);
	/* but in one case the bg color is the bordr color! so hacky hacky hacky it. */
	$monochrome .= "\n/* late append */\n\ndiv.floatingToolTip { background-color: black;  }" ;
	/* and make table borders just one pixel */
	$monochrome = preg_replace('|border-width[ \t]*:\s*\d+px|', 'border-width: 1px', $monochrome);
	
	
	file_put_contents('../css/CQPweb-user-monochrome.css', $monochrome);
	// TODO instead of this, have a file of "CQPweb-user-monochrome-opverrides.css" which ADDS to the main, unchanging CSS file? 
// 	(so, if monochrome, then we wil get (1) variables, 2) main file, 3) monchrome overides.

}






/**
 * Returns the code of the default CSS file for built-in colour schemes.
 */
function print_css_file_content()
{
	return <<<END_OF_CSS_DATA

/* Some resets to override unhelpful user-agent stylesheet stuff */

form {
	margin: 0;
	padding: 0;
	border: 0;
}
/* TODO we want to do this also for some but not all inputs....
   Note in particular we currently rely on the user agent stylesheet to make
   "submit" buttons not crap... we should supply some decent styling ourselves for that! */

/* end of resets */



/* alignment classes for positioning divs and tables. */

.tblRight {
	float: right;
	clear: both;
}
.tblLeft {
	float: left;
	clear: both;
}
.tblCentre {
	float: center;
	clear: both;
}


/* top page heading */

h1 {
	font-family: Verdana;
	text-align: center;
}



/* different paragraph styles */

p.errormessage {
	font-family: verdana;
	font-size: large;
}

p.instruction {
	font-family: verdana;
	font-size: 10pt;
}

p.helpnote {
	font-family: verdana;
	font-size: 10pt;
}

p.bigbold {
	font-family: verdana;
	font-size: medium;
	font-weight: bold;
}

p.spacer {
	font-size: small;
	padding: 0pt;
	line-height: 0%;
	font-size: 10%;
}

span.hit {
	color: red;
	font-weight: bold;
}

span.contexthighlight {
	font-weight: bold;
}

span.concord-time-report {
	color: gray;
	font-size: 10pt;
	font-weight: normal
}


/* table layout */

/* class for making any kind of table take up all horizontal space */
table.fullwidth {
	width:100%;
}


table.controlbox {
	border: large outset;
}

td.controlbox {
	font-family: Verdana;
	padding-top: 5px;
	padding-bottom: 5px;
	padding-left: 10px;
	padding-right: 10px;
	border: medium outset;
}



/* layout ; layoutFG ; layoutBG*/
table.concordtable {
	border-style: solid;
	border-color: #ffffff; 
	border-width: 5px;
}

th.concordtable {
	padding-left: 3px;
	padding-right: 3px;
	padding-top: 7px;
	padding-bottom: 7px;
	background-color: #bbbbff;
	font-family: verdana;
	font-weight: bold;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
}

td.concordgeneral {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ddddff;
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
}


td.concorderror {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ffeeaa;
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
}


td.concordgrey {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #d5d5d5;
	font-family: verdana;
	font-size: 10pt;
	border-style: solid;
	border-color: #ffffff; 
	border-width: 2px;
}


td.before {
	padding: 3px;
	background-color: #ddddff;
	border-style: solid;
	border-color: #ffffff; 
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 2px;
	border-right-width: 0px;
	text-align: right;
}

td.after {
	padding: 3px;
	background-color: #ddddff;
	border-style: solid;
	border-color: #ffffff; 
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 0px;
	border-right-width: 2px;
	text-align: left;
}

td.node {
	padding: 3px;
	background-color: #f0f0f0;
	border-style: solid;
	border-color: #ffffff;
	border-top-width: 2px;
	border-bottom-width: 2px;
	border-left-width: 0px;
	border-right-width: 0px;
	text-align: center;
}

td.lineview {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ddddff;
	border-style: solid;
	border-color: #ffffff;
	border-width: 2px;
}

td.parallel-line {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #f0f0f0;
	border-style: solid;
	border-color: #ffffff;
	border-width: 2px;
}

td.parallel-kwic {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #f0f0f0;
	border-style: solid;
	border-color: #ffffff;
	border-width: 2px;
	text-align: center;
}

td.text_id {
	padding: 3px;
	background-color: #ddddff;
	border-style: solid;
	border-color: #ffffff;
	border-width: 2px;
	text-align: center;
}

td.end_bar {
	padding: 3px;
	background-color: #d5d5d5;
	font-family: verdana;
	border-style: solid;
	border-color: #ffffff;
	border-width: 2px;
	text-align: center;
}


td.basicbox {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 10px;
	padding-bottom: 10px;
	background-color: #ddddff;
	font-family: verdana;
	font-size: 10pt;
}


/* like basic box, but with no padding */
td.tightbox {
	padding-left: 0px;
	padding-right: 0px;
	padding-top: 0px;
	padding-bottom: 0px;
	background-color: #ddddff;
	font-family: verdana;
	font-size: 10pt;
}


td.cqpweb_copynote {
	padding-left: 7px;
	padding-right: 7px;
	padding-top: 3px;
	padding-bottom: 3px;
	background-color: #ffffff;
	font-family: verdana;
	font-size: 8pt;
	color: gray;
	border-style: solid;
	border-color: #ffffff;
	border-width: 2px;
}


/* different types of link */
/* first, for left-navigation in the main query screen */
a.menuItem:link {
	display: block;
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}
a.menuItem:visited {
	display: block;
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}
a.menuItem:hover {
	display: block;
	white-space: nowrap;
	font-family: verdana;
	color: red;
	font-size: 10pt;
	text-decoration: underline;
}
/* there must be SOME way to avoid the repetitionn above but I have yet to find it! */

/* next, for the currently selected menu item 
 * will not usually have an href 
 * ergo, no visited/hover */
a.menuCurrentItem {
	display: block;
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-size: 10pt;
	text-decoration: none;
}


/* next, for menu bar header text item 
 * will not usually have an href 
 * ergo, no visited/hover 
a.menuHeaderItem {
	white-space: nowrap;
	font-family: verdana;
	color: black;
	font-weight: bold;
	font-size: 10pt;
	text-decoration: none;

}*/


/* the next three classes support the tooltip system. */


div.floatingToolTip  {
	/* configurable */
	width            : 350px;                        /* initial width of each tooltip box. */
	background-color : #003399;                      /* colour parameter -- colour of border */
	padding          : 0px;                          /* padding controls width of "border", which is 1 + padding */
	opacity          : 1.0;                          /* should the floating tooltip box be opaque ? */
	/* end configurable */

	/* these are part of the system: should not be changed. */
	visibility       : hidden;
	position         : absolute;
	z-index          : -1010;
	left             : 0px;
	top              : 0px;
}

table.floatingToolTipFrame {
	width      : 100%;
	opacity    : inherit;
}

td.floatingToolTipTarget {
	/* configurable */
	background-color : #e6ecff;                      /* colour parameter -- colour of background */
	color            : #000066;                      /* colour parameter -- colour of tooltip text */
	font-family      : Verdana,Arial,Helvetica,sans-serif;
	font-size        : 11px;
	font-weight      : normal;
	text-align       : left; 
	/* end configurable */
	padding          : 2px;
	opacity          : inherit;
}



/* here, for footer link to help */
a.cqpweb_copynote_link:link {
	color: gray;
	text-decoration: none;
}
a.cqpweb_copynote_link:visited {
	color: gray;
	text-decoration: none;
}
a.cqpweb_copynote_link:hover {
	color: red;
	text-decoration: underline;
}
END_OF_CSS_DATA;


}


