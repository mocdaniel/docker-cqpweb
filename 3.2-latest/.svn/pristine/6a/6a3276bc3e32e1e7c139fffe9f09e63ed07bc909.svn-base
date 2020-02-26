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
require('../lib/corpus-lib.php');
require('../lib/annotation-lib.php');
require('../lib/metadata-lib.php');



/* declare global variables */
$Corpus = $Config = NULL;

/* run location might be usr or exe, we don't know.... */
$help_run_location = ('usr' == (array_slice(preg_split('~[/\\\\]~', dirname(getcwd()), -1, PREG_SPLIT_NO_EMPTY), -1)[0]) ? RUN_LOCATION_USR : RUN_LOCATION_CORPUS);

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, $help_run_location);


/*
 * OK, let's set things up a bit. We need the YouTube ID codes of the tutorials,
 * as well as some other info.
 * 
 * Represented as a map between short labels for the type of help, and an array of info:
 * FIRST item -- strings of the 11-byte IDs of the corresponding YouTube videos.
 * SECOND item -- human readable title for the help video.
 * 
 * If a video is re-recorded, then all that is necessary is to change the YT ID
 * in this array.
 * 
 * Note that the YouTube ID codes probably do require URL-encoding.
 */
$help_video_info = array(
	'signup'      => (object) array('ytcode'=>'0pSkr11xc1k', 'title'=>'Creating an account'), # 0
	'intro'       => (object) array('ytcode'=>'Yf1KxLOI8z8', 'title'=>'Introduction'), # 1
	'standardq'   => (object) array('ytcode'=>'0w5zLqjJiFQ', 'title'=>'The standard query'), # 2
	'wildcards'   => (object) array('ytcode'=>'SbxrWoVAa98', 'title'=>'Wildcards in queries'), # 3
	'primaryq'    => (object) array('ytcode'=>'ROfJ9PmRFvU', 'title'=>'Part-of-speech tags (primary annotation)'), # 4
	'secondaryq'  => (object) array('ytcode'=>'yPwjLQj4rKs', 'title'=>'Lemmata (secondary annotation)'), # 5
	'tertiaryq'   => (object) array('ytcode'=>'ZqApYrGAR00', 'title'=>'Simplified POS (tertiary annotation) '), # 6
	'sequenceq'   => (object) array('ytcode'=>'wO8jZRJWldY', 'title'=>'Word sequence queries '), # 7
	'concordance' => (object) array('ytcode'=>'vDbAF7TtS_I', 'title'=>'The concordance screen'), # 8
	//'' => array('ytcode'=>'', 'title'=>''), # 9
	'textmeta'    => (object) array('ytcode'=>'q1taOtYLPZo', 'title'=>'The text metadata screen'), # 10
	'restrictedq' => (object) array('ytcode'=>'zFJbPd2K-0c', 'title'=>'Restricted queries'), # 11
	//'' => array('ytcode'=>'', 'title'=>''), # 12
	'queryhist'   => (object) array('ytcode'=>'5bsrAM56Xco', 'title'=>'The query history'), # 13
	//'' => array('ytcode'=>'', 'title'=>''), # 14
	'thin'        => (object) array('ytcode'=>'dYMGB6oVETg', 'title'=>'Thinning a query'), # 15
	'sort'        => (object) array('ytcode'=>'FO87rwdFCNM', 'title'=>'Sorting a query'), # 16
	'dist'        => (object) array('ytcode'=>'7vty9NQ9jrE', 'title'=>'Distribution'), # 17
	'categorise'  => (object) array('ytcode'=>'_UHGWe1-vh0', 'title'=>'Categorising queries'), # 18
	'collocopt'   => (object) array('ytcode'=>'h9DQFCh0O54', 'title'=>'The collocation options screen'), # 19
	'collocation' => (object) array('ytcode'=>'SL2a3dsmZzc', 'title'=>'The collocation screen'), # 20
	'savequery'   => (object) array('ytcode'=>'4Wg_0lDsqts', 'title'=>'Saving queries'), # 21
	'downloadconc'=> (object) array('ytcode'=>'OIQA5pnk15M', 'title'=>'Downloading a concordance'), # 22
	'downloadtab' => (object) array('ytcode'=>'VeA21vLleUk', 'title'=>'Downloading a query tabulation'), # 23
	'qupload'     => (object) array('ytcode'=>'7sNJmTumsGo', 'title'=>'Uploading queries'), # 24
	'subcorpora'  => (object) array('ytcode'=>'huYhoS64fQQ', 'title'=>'Creating and managing subcorpora'), # 25
	'freqlist'    => (object) array('ytcode'=>'2JIgIZ8SWjU', 'title'=>'The frequency list display'), # 26
	'keywords'    => (object) array('ytcode'=>'GovfJmEpmwM', 'title'=>'Keywords'), # 27
	//'' => array('ytcode'=>'', 'title'=>''), # 
	);


/* GET VARIABLES FROM GET.... */

/*
 * "help" can be used in two modes: If a vidreq has been made, go into show video mode.
 * (which, as a handy mnmemonic, has the ui code "vidreq".
 * 
 * If a vidreq has not been made, we look for a "ui" just as in queryhome,
 * and the appropriate function renders out a blob of HTML.
 * 
 * If neither is specified, there should be a default "hello" ui.
 */
if (isset($_GET['vidreq']) && array_key_exists($_GET['vidreq'], $help_video_info))
{
	$vidreq = $_GET['vidreq'];
	$what_ui = 'vidreq';
}
else
	$what_ui = ( isset($_GET['ui']) ? $_GET['ui'] : 'hello' );



/* BEGIN PAGE RENDERING */

echo print_html_header( ($Corpus->specified ? (strip_tags($Corpus->title) . '-- CQPweb Help') : 'CQPweb Help'), $Config->css_path);
?>
<table class="concordtable fullwidth">
	<tr>
		<td valign="top">

<?php

/* ******************* *
 * PRINT SIDE BAR MENU *
 * ******************* */

?>
<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">Help! menu</a></th>
	</tr>
</table>

<table class="concordtable fullwidth">



<?php
echo print_menurow_help('hello',    'Welcome to help', $what_ui);
echo print_menurow_help('start',    'Start here',      $what_ui);
echo print_menurow_help('basic',    'Basic topics',    $what_ui);
echo print_menurow_help('advanced', 'Advanced topics', $what_ui);
echo print_menurow_help('full',     'Full contents',   $what_ui);



/*
 * TODO 
 * 
 * Note: clicking on one fo these takes you out of the help system and back to the queryhome interface.
 * 
 * Should these even be here in this same way? Should the HELP system have its own intefrface to the same thing?
 * 
 * Or should I, perhaps, dump the entire idea of the HELP being corpus-embedded?
 * 
 * No great problem for now as the system is not all *that* confusing.
 * But longterm this needs resolving.
 */

if ($Corpus->specified)
{
	echo print_menurow_heading('Info on this corpus');

	/* SHOW CORPUS METADATA */
	echo "<tr>\n\t<td class=\"concordgeneral\">\n\t\t"
		, "<a id=\"menuCorpusMetadata\" class=\"menuItem hasToolTip\" href=\"index.php?ui=corpusMetadata\" "
		, "data-tooltip=\"View CQPweb's database of information about this corpus\">View corpus metadata</a>\n\t</td>\n</tr>"
		;

	/* print a link to a corpus manual, if there is one */
	if (empty($Corpus->external_url))
		echo '<tr><td class="concordgeneral"><a class="menuCurrentItem"><em>No corpus documentation available</em></a></tr></td>';
	else
		echo '<tr><td class="concordgeneral"><a id="menuExternalDoc" target="_blank" class="menuItem hasToolTip" href="'
			, $Corpus->external_url , '" data-tooltip="Info on ' , escape_html($Corpus->title)
			, ' on the web">Corpus documentation</a></td></tr>'
			;


	foreach (get_all_annotation_info($Corpus->name) as $obj)
		if (!empty($obj->external_url))
			echo '<tr><td class="concordgeneral"><a id="menuTagset:', $obj->handle, '" target="_blank" class="menuItem hasToolTip" href="'
				, $obj->external_url
				, '" data-tooltip="', escape_html($obj->description), ': view documentation">' 
				, escape_html($obj->tagset)
				, "</a></td></tr>\n"
				;
}

echo print_menurow_heading('References and readings');

echo "\n<tr>\n\t<td class=\"concordgeneral\">\n\t\t<a id=\"menuCWBrefs\" class=\"menuItem hasToolTip\" href=\"http://cwb.sourceforge.net/doc_links.php#references\" "
	, "data-tooltip=\"View key references for CQPweb (on the CWB website)\""
	, ">Key references</a>\n\t</td>\n</tr>"
	;
echo "\n<tr>\n\t<td class=\"concordgeneral\">\n\t\t<a id=\"menuCWBdocs\" class=\"menuItem hasToolTip\" href=\"http://cwb.sourceforge.net/documentation.php#cqpweb\" "
	, "data-tooltip=\"View further documentationfor CQPweb (on the CWB website)\""
	, ">Additional CQPweb docs</a>\n\t</td>\n</tr>"
	;

?>

<tr>
	<th class="concordtable"><a class="menuHeaderItem">About CQPweb</a></th>
</tr>

<tr>
	<td class="concordgeneral">
		<a id="menu:about:homepage" class="menuItem hasToolTip" href="../"
			data-tooltip="Go to the main homepage for this CQPweb server">
			CQPweb main menu
		</a>
	</td>
</tr>



</table>

		</td>
		<td valign="top">
		
<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable">
			<a class="menuHeaderItem">
				CQPweb Help System<?php echo $Corpus->specified ? (" &ndash; ".escape_html($Corpus->title)) : '', "\n"; ?> 
			</a>
		</th>
	</tr>
</table>


<?php

switch($what_ui)
{
case 'vidreq':
	do_showhelp_vidreq($help_video_info[$vidreq]);
	break;

case 'hello':
	do_showhelp_hello();
	break;
	
case 'start':
	/* this works for now, in future maybe come up with a better "start here" screen. */
	do_showhelp_vidreq($help_video_info['intro']);
	break;

//TODO: currently all fall through to "full"
case 'basic':
case 'advanced':

case 'full':
	do_showhelp_fullcontents($help_video_info);
	break;

case 'embed':
	do_ui_embed_page();
	break;
	
	
default:
	?>
	<p class="errormessage">
		&nbsp;<br>
		&nbsp;<br>
		Coming Soon!
	</p>
	<?php
	break;
}



/* finish off the page */
?>

		</td>
	</tr>
</table>
<?php







echo print_html_footer('hello');

cqpweb_shutdown_environment();




/*
 * -------------------------------
 * END OF SCRIPT; functions follow
 * -------------------------------
 */


/**
 * Displays a requested video.
 * @param object $info  An object from the global array of help information.
 */
function do_showhelp_vidreq($info)
{
	/*
	 * NB: 
	 * 
	 * YouTube embed code  Width & Height can be changed. YouTube suggests 420/315; Firefox suggests 640/360;
	 * I have gone with the latter.
	 */

	?>
	
	<table class="concordtable fullwidth">
	
		<tr>
			<th class="concordtable">Video help: <?php echo $info->title; ?></th>
		</tr>
	
		<tr>
			<td class="concordgeneral" align="center">
		
				<p class="spacer">&nbsp;</p>
				
				<iframe
				 
				width="640" 
				height="480" 
				src="https://www.youtube.com/embed/<?php echo $info->ytcode; ?>" 
				style="border:none;" 
				allowfullscreen
				
				></iframe>
				<!-- nb, allowfullscreen is now to be changed to allow="fullscreen" but this does not yet haave wide cross-browser support (Jan 2019) -->
			
				<p class="spacer">&nbsp;</p>	
			</td>
		</tr>
	
		
	</table>

	<?php
	// TODO add a "see also" list underneath the YT iframe, where appropriate.
}



function do_showhelp_fullcontents($vidinfo)
{
	?>
	
	<table class="concordtable fullwidth">
	
		<tr>
			<th class="concordtable">Full list of available help videos</th>
		</tr>
	
		<tr>
			<td class="concordgeneral">
		
				<p class="spacer">&nbsp;</p>

				<p>The following is a full list of currently-available help-and-tutorial videos:</p>
				
				<ul>
				
					<?php
					foreach ($vidinfo as $vid => $info)
						echo "\n\t\t\t\t<li>\n\t"
							, '<a href="help.php?vidreq=', $vid, '">'
							, $info->title
							, "</a>\n</li>\n"
							;
					?>
				
				</ul>

				<p>
					<a href="https://www.youtube.com/playlist?list=PL2XtJIhhrHNQgf4Dp6sckGZRU4NiUVw1e" 
						target="_blank">You can also view a full list of videos on YouTube</a>.
				</p>
							
				<p class="spacer">&nbsp;</p>	
			</td>
		</tr>
	
		
	</table>

	<?php
}


function do_showhelp_hello()
{
	global $Corpus;
	?>
	
	<table class="concordtable fullwidth">
	
		<tr>
			<th class="concordtable">CQPweb help system: Welcome!</th>
		</tr>
	
		<tr>
			<td class="concordgeneral">

				<p>Welcome to the CQPweb help system.</p>

				<p>
					This set of pages contains a set of embedded tutorials on the use of CQPweb. 
					They are hosted on YouTube, so if you are not able to access YouTube, you 
					may not be able to watch the videos.
				</p>

				<p>Use the menu on the left to navigate the list of help videos.</p>

				<p>
					For a general overview and introduction to CQPweb, follow the &ldquo;Start here&rdquo; option.
				</p>
				
				<p>
					Also: if you click the &ldquo;help&rdquo; link from a particular CQPweb screen, you should
					be taken straight to the most relevant video.
				</p>
				
				<p>
					This help system is available within every corpus. 
					
					<?php 
					if ($Corpus->specified)
						echo "You are currently working inside the corpus <strong>&ldquo;"
 							, escape_html($Corpus->title)
 							, "&rdquo;</strong>.\n"
 							; 
					?>

				</p>

				<p>
					You will find options on the menu to the left that will take you 
					to additional information specifically about this corpus, such as a corpus
					manual (if then system knows of one), or information about the corpus tags
					(if there are any). Following these links returns you to the corpus interface.
					
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	
		
	</table>

	<?php
}
