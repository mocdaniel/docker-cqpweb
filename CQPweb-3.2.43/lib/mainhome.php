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
 * This is the script that renders the CQPweb "main menu" / "entrance page" / whate'er-you-ma-call-it.
 */

/* Very first thing: Let's work in a subdirectory so that we can use the same subdirectory references! */
chdir('exe');


require('../lib/environment.php');

require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/html-lib.php');
require('../lib/useracct-lib.php');
require('../lib/corpus-lib.php');
require('../lib/metadata-lib.php');
require('../lib/exiterror-lib.php');

$User = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_MAINHOME);

if ($Config->homepage_use_corpus_categories)
{
	/* get a list of categories */
	$categories = list_corpus_categories();
	
	/* how many categories? if only one, it is either uncategorised or a single assigned cat: ergo don't use cats */
	$n_key_items = count($categories);
	if ($n_key_items < 2)
		$Config->homepage_use_corpus_categories = false;
}
else
{
	/* empty string: to make the loops cycle once */
	$categories = array(0=>'');
}


/* devise the HTML for the header-bar logos. */
$logo_divs = '';
foreach ( array('left', 'right') as $side)
{
	$addresses = 'homepage_logo_'.$side;
	if (empty($Config->$addresses))
		continue;
	if (false !== strpos($Config->$addresses, "\t"))
		list ($img_url, $link_url) = explode("\t", $Config->$addresses, 2);
	else
	{
		$img_url = $Config->$addresses;
		$link_url = false;
	}
	$logo_divs .= "<div style=\"float: $side;\">" .
		($link_url ? "<a href=\"$link_url\">" : '') .
		"<img src=\"$img_url\" height=\"80\"  border=\"0\" >" .
		($link_url ? '</a>' : '') .
		'</div>      ';
}





echo print_html_header('CQPweb Main Page', $Config->css_path);

?>


<table class="concordtable fullwidth">

	<tr>
		<th colspan="3" class="concordtable">
			<?php echo $logo_divs, $Config->homepage_welcome_message; ?>
		</th>
	</tr>
	
<!-- 	<tr> -->
	<?php
	
	$corpus_info = get_all_corpora_info();



	if ($User->logged_in)
	{
		/* personalised welcome message */
		if (empty($User->realname) || $User->realname == 'unknown person')
			$personalise = '';
		else
			$personalise = ', ' . escape_html($User->realname);

		$result = do_sql_query("select corpus from query_history where user='{$User->username}' order by date_of_query desc");
		
		$recent_corpora = array();
		
		while (count($recent_corpora) < 6 && ($o = mysqli_fetch_row($result)))
			if (!in_array($o[0], $recent_corpora))
				$recent_corpora[] = $o[0];
		?>
		
		<tr>
			<td colspan="3" class="concordgeneral">
			
				<p>&nbsp;</p>
			
				<p align="center" style="font-size:large">
					Welcome back to the CQPweb server<?php echo $personalise; ?>.<br>You are logged in to the system.
				</p>

				<p>&nbsp;</p>
				
				<table class="basicbox" style="margin:auto; width:40%;">
					<tr>
						<th width="50%" class="basicbox">Recently-used corpora</th>
						<th width="50%" class="basicbox">Quick links</th>
					</tr>
					<tr>
						<td class="basicbox">
							<ul style="margin:auto">
								<?php
								echo "\n";
								foreach($recent_corpora as $rc)
									if (isset($corpus_info[$rc]))
										echo "\t\t\t\t\t\t\t\t<li><a href=\"{$corpus_info[$rc]->corpus}/\">"
											, escape_html($corpus_info[$rc]->title)
											, "</a></li>\n"
											;
								?>
							</ul>
						</td>
						<td class="basicbox">
							<ul>
								<li><a href="usr/index.php?ui=corpusAccess">Your corpus access privileges</a></li>
								<?php if ($User->is_admin()) echo "<li><a href=\"adm\">Admin control panel</a></li>\n"; ?>
								<li><a href="usr/index.php?ui=userDetails">Your user account details</a></li>
								<li><a href="usr/useracct-act.php?userAction=userLogout">Log out of CQPweb</a></li>
							</ul>
						</td>
					</tr>
				</table>
			</td>
		</tr>
		
		<?php
// 		echo "\t<tr>\n\t\t\n";
	}
	else
	{
		?>
		
		<tr>
			<td colspan="3" class="concordgeneral">
				<?php echo print_login_form(), "\n"; ?>
			</td>
		</tr>
		<tr>
			<td colspan="3" class="concordgeneral">
				<p align="center">
					<a href="usr/?ui=create">Create account</a>
					|
					<a href="usr/">Full account-control options</a>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		<?php
	}
	?>
	
	<tr>
		<th colspan="3" class="concordtable">
			Corpora available on this server
			<?php 
			if ($User->logged_in)
				echo '(<a href="usr/index.php?ui=corpusAccess">click here to view your own corpus access privileges</a>)';
			echo "\n";
			?>
		</th>
	</tr>
	
<?php

foreach ($categories as $id => $cat)
{
	/* get a filtered, ordered list of corpora */
	$sql = "select corpus from corpus_info where owner IS NULL and visible = 1 "
		. ($Config->homepage_use_corpus_categories ? "and corpus_cat = $id" : '') 
		. " order by corpus asc";

	$result = do_sql_query($sql);
	
//	$corpus_list = array();
//	while ( ($x = mysqli_fetch_object($result)) != false)
//		$corpus_list[] = $x;
	
	/* don't print a table for empty categories */
	if (0 == mysqli_num_rows($result))
		continue;
	


	if ($Config->homepage_use_corpus_categories)
		echo "\t\t<tr><th colspan=\"3\" class=\"concordtable\">$cat</th></tr>\n\n";
	
	
	
	$i = 0;
	$celltype = 'concordgeneral';
	
//	foreach ($corpus_list as $c)
	while ($o = mysqli_fetch_row($result)) 
	{
		list($c) = $o;
		
		if ($i == 0)
			echo "\t\t<tr>";

		echo "
			<td class=\"$celltype\" width=\"33%\" align=\"center\">
				&nbsp;<br>
				<a href=\"{$c}/\">", escape_html($corpus_info[$c]->title), "</a>
				<br>&nbsp;
			</td>\n";
		
		$celltype = ($celltype=='concordgrey'?'concordgeneral':'concordgrey');
		
		if ($i == 2)
		{
			echo "\t\t</tr>\n";
			$i = 0;
		}
		else
			$i++;
	}
	
	if ($i == 1)
	{
		echo "\t\t\t<td class=\"$celltype\" width=\"33%\" align=\"center\">&nbsp;</td>\n";
		$i++;
		$celltype = ($celltype=='concordgrey'?'concordgeneral':'concordgrey');
	}
	if ($i == 2)
		echo "\t\t\t<td class=\"$celltype\" width=\"33%\" align=\"center\">&nbsp;</td>\n\t\t</tr>\n";
}

?>


</table>

<a id="mainhome:messages"></a>

<?php

display_system_messages();

// TODO. Build in helplinks above instead of a bottom-row helplink?
echo print_html_footer(false);

cqpweb_shutdown_environment();


/* END OF SCRIPT */


