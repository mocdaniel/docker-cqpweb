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
 * Each of these functions prints a table for the right-hand side interface. 
 */



function do_ui_queryhistory()
{
	global $User;
	global $Config;
	global $Corpus;
	$m = NULL;
	
	if (isset($_GET['historyView']))
		$view = $_GET['historyView'];
	else
		$view = ( (bool)get_user_setting($User->username, 'cqp_syntax') ? 'cqp' : 'simple');
	

	if (isset($_GET['beginAt']))
		$begin_at = (int)$_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
	{
		$per_page = (int)$_GET['pp'];
		$per_page_to_pass = $per_page;
	}
	else
		$per_page = $Config->default_history_per_page;


	/* variable for superuser usage */
	if (isset($_GET['showUser']) && $User->is_admin())
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $User->username;


	/* create sql query and set options */
	
	/* if the corpus has an indexing date set, only get query history instances newer than that
	 * (to avoid retrieving instances from before a re-indexing, which might use incompatible attributes/metadata fields.; */
	$timewhere = (0 == strtotime($Corpus->date_of_indexing[0]) ? '' : " and date_of_query > '{$Corpus->date_of_indexing}' ");
	
	switch ($user_to_show)
	{
	case '~~ALL':
		$sql = "select * from query_history where corpus = '{$Corpus->name}' $timewhere order by date_of_query DESC";
		$column_count = 6;
		$usercolumn = true;
		$current_string = 'Currently showing history for all users';
		break;
		
	case '~~SYNERR':
		/* I have forgotten why column_count is so high here - you see, it is not used if an admin user is plugged in */
		$column_count = 9;
		$sql = "select * from query_history where corpus = '{$Corpus->name}' $timewhere and hits = -1 order by date_of_query DESC";
		$usercolumn = true;
		$current_string = 'Currently showing history of queries with a syntax error';
		break;
	
	case '~~RUNNING':
		$sql = "select * from query_history where corpus = '{$Corpus->name}' $timewhere and hits = -3 order by date_of_query DESC";
		$column_count = 9;
		$usercolumn = true;
		$current_string = 'Currently showing history of incompletely-run queries';
		break;
	
	default:
		$sql = "select * from query_history where corpus = '{$Corpus->name}' $timewhere and user = '$user_to_show' order by date_of_query DESC";
		$column_count = 5;
		$usercolumn = false;
		$current_string = "Currently showing history for user <b>&ldquo;$user_to_show&rdquo;</b>";
		break;
	}


	$result = do_sql_query($sql);
	
	$link_to_change_view = "&nbsp;&nbsp;&nbsp;&nbsp;(<a href=\"index.php?ui=history&historyView=" 
		. ( $view == 'simple' ? 'cqp' : 'simple' )
		. ($begin_at != 1 ? "&beginAt=$begin_at": '' )
		. (isset($per_page_to_pass) ? "&pp=$per_page_to_pass": '' )
		. '">Show ' . ( $view == 'simple' ? 'in CQP syntax' : 'as Simple Query' ) . '</a>)'
		;
	


	if ($User->is_admin())
	{
		/* there will be a delete column */
		$delete_lines = true;
		$column_count++;
	
		/* version giving superuser access to everything */
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">Query history: admin controls</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<form action="index.php" method="get">
					<input type="hidden" name="ui" value="history">
					<input type="hidden" name="historyView" value="<?php echo $view;?>">
					<table>
						<tr>
							<td class="basicbox">Select a user...</td>
							<td class="basicbox">
								<select name="showUser">
									<option value="~~ALL" selected>Show all users' history</option>
									<option value="~~RUNNING">Show incompletely-run queries</option>
									<option value="~~SYNERR">Show queries with a syntax error</option>
									<?php
									$user_result = do_sql_query("SELECT distinct(user) FROM query_history where corpus = '{$Corpus->name}' $timewhere order by user");
									while ($r = mysqli_fetch_row($user_result))
										echo '<option value="' , $r[0] , '">' , $r[0] , "</option>\n";
									?>
								</select>
							</td>
							<td class="basicbox"><input type="submit" value="Show history"></td>
						</tr>
						<tr>
							<td class="basicbox">Number of records per page</td>
							<td class="basicbox">
								<select name="pp">
									<option value="10"   <?php if ($Config->default_history_per_page == 10)   echo 'selected'; ?>>10</option>
									<option value="50"   <?php if ($Config->default_history_per_page == 50)   echo 'selected'; ?>>50</option>
									<option value="100"  <?php if ($Config->default_history_per_page == 100)  echo 'selected'; ?>>100</option>
									<option value="250"  <?php if ($Config->default_history_per_page == 250)  echo 'selected'; ?>>250</option>
									<option value="350"  <?php if ($Config->default_history_per_page == 350)  echo 'selected'; ?>>350</option>
									<option value="500"  <?php if ($Config->default_history_per_page == 500)  echo 'selected'; ?>>500</option>
									<option value="1000" <?php if ($Config->default_history_per_page == 1000) echo 'selected'; ?>>1000</option>
								</select>
							</td>
							<td></td>
						</tr>
						<tr>
							<td colspan="3" class="basicbox">
								<p><?php echo $current_string; ?>.</p>
							</td>
						</tr>
					</table>
					</form>
				</td>
			</tr>
		</table>
		
		<?php
	}
	else
		$delete_lines = false;
	?>

	<table class="concordtable fullwidth">
		<?php
		if (!$User->is_admin())
			echo '<tr><th colspan="', $column_count,'" class="concordtable">Query history</th></tr>', "\n";
		?>
		<tr>
			<th class="concordtable">No.</th>
			<?php if ($usercolumn) echo '<th class="concordtable">User</th>', "\n"; ?>
			<th class="concordtable">Query <?php echo $link_to_change_view; ?></th>
			<th class="concordtable">Restriction</th>
			<th class="concordtable">Hits</th>
			<th class="concordtable">Date</th>
			<?php if ($delete_lines) echo '<th class="concordtable">Delete</th>', "\n"; ?>
		</tr>

	<?php

	$subc_mapper = get_subcorpus_name_mapper($Corpus->name);

	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysqli_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		$row = mysqli_fetch_assoc($result);

		if (false === $row)
			break;
		if ($i < $begin_at)
			continue;
		
		echo "<tr>\n<td class='concordgeneral' align='center'>$i</td>";
		if ($usercolumn)
			echo "<td class='concordgeneral' align='center'>" , $row['user'] , '</td>';
		
		if ( $view == 'simple' && $row['simple_query'] != "" )
		{
			if (preg_match('/^\(\?(standard|longest|shortest|traditional)\)(.*)$/', $row['simple_query'], $m))
				$link_body = escape_html($m[2]) . '&nbsp;&nbsp;with match strategy <em>' . $m[1] . '</em>';
			else
				$link_body = escape_html($row['simple_query']);
			
			echo '<td class="concordgeneral">'
 				, '<a id="qInsertLink:', $i, '" class="hasToolTip" href="index.php?ui=search&insertString=' 
				, urlencode($row['simple_query']) , '&insertType=' , $row['query_mode'] , '"'
				, ' data-tooltip="Insert query string into query window">' 
				, $link_body, '</a>'
				, ($row['query_mode'] == 'sq_case' ? " (case sensitive)" : "") 
				, '</td>'
				;
		}
		else
			echo '<td class="concordgeneral">'
 				, '<a id="qInsertLink:', $i, '" class="hasToolTip" href="index.php?ui=search&insertString=' 
				, urlencode($row['cqp_query']) , '&insertType=' 
				, ( $view == 'simple' ? $row['query_mode'] : 'cqp' ) , '"'
				, ' data-tooltip="Insert query string into query window">' 
				, escape_html($row['cqp_query']) 
				, '</a></td>'
				;

		$qs = QueryScope::new_by_unserialise($row['query_scope']);

		if ($qs->type == QueryScope::TYPE_SUBCORPUS)
		{
			/* wee hack: when a subcorpus is deleted, it parses as a subcorpus, but the serialisation contains a special string. */
			if (QueryScope::$DELETED_SUBCORPUS == $qs->serialise())
				echo '<td class="concordgeneral">(a deleted subcorpus)</td>';
			else
				echo '<td class="concordgeneral">Subcorpus:<br>'
 					, '<a id="resQInsertLink:', $i, '" class="hasToolTip" href="index.php?ui=search&insertString='
					, urlencode(($view == 'simple' && $row['simple_query'] != "") ? $row['simple_query'] : $row['cqp_query']) 
					, '&insertType=' , ( $view == 'simple' ? $row['query_mode'] : 'cqp' ) 
					, '&insertSubcorpus=' , $row['query_scope'] , '"'
					, ' data-tooltip="Insert query string and subcorpus into query window">'
					, $subc_mapper[(int)$qs->serialise()]
					, '</a></td>'
					;
		}
		else if ($qs->type == QueryScope::TYPE_RESTRICTION)
			echo '<td class="concordgeneral">'
 				, '<a id="resQInsertLink:', $i, '" class="hasToolTip" href="index.php?ui=restrict&insertString='
				, urlencode(($view == 'simple' && $row['simple_query'] != "") ? $row['simple_query'] : $row['cqp_query']) 
				, '&insertType=' , ( $view == 'simple' ? $row['query_mode'] : 'cqp' ) 
				, '&insertRestrictions=' , urlencode($row['query_scope']) . '"'
				, ' data-tooltip="Insert query string and textual restrictions into query window">'
				, 'Restrictions</a>:<br>' 
				, str_replace('restricted to ', '', str_replace('; ', '; <br>', $qs->print_description(true)))
				, '</td>'
				;
		else
			echo '<td class="concordgeneral">-</td>';

		
		switch($row['hits'])
		{
		/* maybe add links to explanations? (-3 and -1) */
		case -3:
			echo "<td class='concordgeneral' align='center'>Run error</a></td>";
			break;
		case -1:
			echo "<td class='concordgeneral' align='center'>Syntax error</td>";
			break;
		default:
			$query_data = $row['query_mode'] == 'cqp' ? $row['cqp_query'] : $row['simple_query'];
			if ($qs->type == QueryScope::TYPE_SUBCORPUS)
			{
				if (QueryScope::$DELETED_SUBCORPUS == $qs->serialise())
					echo '<td class="concordgeneral" align="center">', $row['hits'] , '</td>';
				else
					echo '<td class="concordgeneral" align="center">'
 						, '<a id="revisitLink:', $i, '" class="hasToolTip"'
//						, ' href="concordance.php?theData=' , urlencode($row['cqp_query']) 
						, ' href="concordance.php?theData=' , urlencode($query_data) 
						, '&', $qs->url_serialise()
//						, '&simpleQuery=', urlencode($row['simple_query'])
						, '&qmode=', $row['query_mode'] , '" data-tooltip="Recreate query result">'
						, $row['hits'] , "</a></td>"
						;
			}
			else if ($qs->type == QueryScope::TYPE_RESTRICTION)
				echo '<td class="concordgeneral" align="center">'
 					, '<a id="revisitLink:', $i, '" class="hasToolTip"'
//					, ' href="concordance.php?theData=' , urlencode($row['cqp_query']) 
					, ' href="concordance.php?theData=' , urlencode($query_data) 
//					, '&simpleQuery=' , urlencode($row['simple_query'])
					, '&' , $qs->url_serialise()
					, '&qmode=', $row['query_mode'] , '" data-tooltip="Recreate query result">'
					, $row['hits'] , "</a></td>"
					;
			else
				echo '<td class="concordgeneral" align="center">'
 					, '<a id="revisitLink:', $i, '" class="hasToolTip"'
//					, ' href="concordance.php?theData=' , urlencode($row['cqp_query']) 
					, ' href="concordance.php?theData=' , urlencode($query_data) 
//					, '&simpleQuery=' , urlencode($row['simple_query'])
					, '&qmode=', $row['query_mode'] , '" data-tooltip="Recreate query result">'
					, $row['hits'] , "</a></td>"
					;
			break;
		}
		echo "<td class='concordgeneral' align='center'>" , $row['date_of_query'] , "</td>";
		
if (!array_key_exists('id', $row )) $row['id'] = $row['instance_name'];
		
		if ($delete_lines)
			echo '<td class="concordgeneral" align="center">'
 				, '<a id="delHist:', $i, '" class="menuItem hasToolTip"'
				, ' href="execute.php?function=history_delete&args=' , urlencode($row['id']), '&locationAfter=' 
					, urlencode('index.php?ui=history' 
							. (1 != $begin_at ? '&beginAt='.$begin_at : '') 
							. (isset($per_page_to_pass) ? '&pp='.$per_page_to_pass : '') )
				, '" '
				, 'data-tooltip="Delete history item">[x]</a></td>'
				;
		echo "\n</tr>\n";
	}
	
	?>
	
	</table>
	
	<?php

	$navlink_base =  'index.php?ui=history' . ($view == 'simple' ? '' : '&historyView=cqp') ;
	
	echo print_simple_navlinks(
		$navlink_base, $begin_at, $i, 
		mysqli_num_rows($result), 
		$per_page, isset($per_page_to_pass),
		'Newer queries', 'Older queries'
		);
}






function do_ui_catqueries()
{
	global $User;
	global $Corpus;
	global $Config;
	
	/* variable for superuser usage */
	if (isset($_GET['showUser']) && $User->is_admin())
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $User->username;

	if ($user_to_show == '~~ALL')
		$current_string = 'Currently showing history for all users';
	else
		$current_string = "Currently showing history for user <b>&ldquo;$user_to_show&rdquo;</b>";
	
	$usercolumn = (($user_to_show == '~~ALL') && $User->is_admin());

	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $Config->default_history_per_page;

	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Categorised queries</th>
		</tr>
	</table>
	
	<?php

	/* form for admin controls */
	if ($User->is_admin())
	{
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">Categorised queries: admin controls</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<form action="index.php" method="get">
						<input type="hidden" name="ui" value="categorisedQs">
						<table>
							<tr>
								<td class="basicbox">Select a user...</td>
								<td class="basicbox">
								<select name="showUser">
									<option value="~~ALL" selected>all users</option>
									<?php
									$result = do_sql_query("SELECT distinct(user) FROM saved_catqueries where corpus = '{$Corpus->name}' order by user");
									while ($r = mysqli_fetch_row($result))
										echo '<option value="' . $r[0] . '">' . $r[0] . "</option>\n";
									?>
								</select>
								</td>
								<td class="basicbox"><input type="submit" value="Show history"></td>
							</tr>
							<tr>
								<td class="basicbox">Number of records per page</td>
								<td class="basicbox">
									<select name="pp">
										<option value="10"   <?php if ($per_page == 10)   echo 'selected'; ?>>10</option>
										<option value="50"   <?php if ($per_page == 50)   echo 'selected'; ?>>50</option>
										<option value="100"  <?php if ($per_page == 100)  echo 'selected'; ?>>100</option>
										<option value="250"  <?php if ($per_page == 250)  echo 'selected'; ?>>250</option>
										<option value="350"  <?php if ($per_page == 350)  echo 'selected'; ?>>350</option>
										<option value="500"  <?php if ($per_page == 500)  echo 'selected'; ?>>500</option>
										<option value="1000" <?php if ($per_page == 1000) echo 'selected'; ?>>1000</option>
									</select>
								</td>
								<td></td>
							</tr>
							<tr>
								<td colspan="3" class="basicbox">
									<p><?php echo $current_string; ?>.</p>
								</td>
							</tr>
						</table>
					</form>
				</td>
			</tr>
		</table>
		
		<?php
	}
	else
	{
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="1" class="concordtable">Categorised queries</th>
			</tr>
		</table>
		
		<?php
	}
	
	/* set this up as a variable, to be used in a loop below. */
	$action_form_begin = '

		<td class="concordgeneral">
			<form action="redirect.php" method="get">
				<input type="hidden" name="redirect" value="categorise-do">
				<select name="sqAction">
					<option value="enterNewValue">Add categories</option>
					<option value="separateQuery" selected>Separate categories</option>
					<option value="deleteCategorisedQuery">Delete complete set</option>
				</select>
				<input type="submit" value="Go">
				<input type="hidden" name="qname" value="';
	
	$action_form_end = '">
			</form>
		</td>

		';
	/* so we simply echo $action_form_begin , $row_qname , $action_form_end */
	
	/* now it's time to look up the categorised queries */

	
	/* 
	 * the saved_catqueries table does not contain the actual info, for that we need to look up the savename etc. 
	 * from the main query cache
	 */
	$user_clause = ($usercolumn ? '' : " user='$user_to_show' and ");
	$result = do_sql_query("select catquery_name, category_list, dbname from saved_catqueries where $user_clause corpus='{$Corpus->name}'");

	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">No.</th>
			<?php if ($usercolumn) echo '<th class="concordtable">User</th>'; ?>
			<th class="concordtable">Name of set</th>
			<th class="concordtable">Categories</th>
			<th class="concordtable">No. of hits</th>
			<th class="concordtable">Categorised</th>
			<th class="concordtable">Date</th>
			<th class="concordtable">Action</th>
		</tr>

	<?php
	if (1 > mysqli_num_rows($result))
		echo "<tr>\n<td class='concordgrey' colspan='$n_columns' align='center'><p>You have no categorised queries for this corpus.</p></td></tr>";
	
	
	$n_columns = ($usercolumn ? 8 : 7);

	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysqli_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	
	
	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		/* note, this loop includes some hefty mysql-ing 
		 * BUT it is not expected that the number of
		 * entries in the saved_catqueries table will be large
		 */ 
		if ( !($row = mysqli_fetch_row($result)))
			break;
		/* so we don't have to run the SQL query below unless 'tis needed */
		if ($i < $begin_at)
			continue;

		/* find out how many rows have been assigned a value */
		$n_categorised = get_sql_value("select count(*) from `{$row[2]}` where category IS NOT NULL");
		$row_qname = $row[0];
		$catlist = explode('|', $row[1]);
		$qrecord = QueryRecord::new_from_qname($row[0]);
		$n_hits = $qrecord->hits();


		/* no. */
		echo "<tr>\n<td class='concordgeneral' align='center'>$i</td>";
		
		/* user */
		if ($usercolumn)
			echo "<td class='concordgeneral' align='center'>" 
				, $qrecord->user 
				, '</td>'
 				;
		
		/* Name of set */
		if (!empty($qrecord->save_name))
			$print_name = $qrecord->save_name;
		else
			$print_name = $row_qname;
		
		echo '<td class="concordgeneral">'
			, '<a id="catQConc:', $i , '" class="hasToolTip" href="concordance.php?program=categorise&qname='
			, $row_qname 
			, '" data-tooltip="View or amend category assignments">'
			, $print_name , '</a></td>'
			;

		/* categories */
		echo '<td class="concordgeneral" align="center">' , implode(', ', $catlist)
			, '</td>'
			;
		
		/* number of hits */
		echo '<td class="concordgeneral" align="center">' , $n_hits , '</td>';
		
		/* number and % of hits categorised */
		echo '<td class="concordgeneral" align="center">' , $n_categorised 
			, ' ('
			, number_format(100.0*(float)$n_categorised/(float)$n_hits, 0) 
			, '%)</td>'
			;
		
		/* date of saving */
		echo '<td class="concordgeneral" align="center">' , $qrecord->print_time() , '</td>';
		
		/* actions */
		echo $action_form_begin , $row_qname , $action_form_end;
		
		echo "</tr>\n";
	}
	?>
	
	</table>
	
	<?php
	
	$navlinks = '<table class="concordtable fullwidth"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Newer categorised queries]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysqli_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Older categorised queries] &gt;&gt;';
	if (mysqli_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks, "\n";

}





function do_ui_savedqueries()
{
	global $User;
	global $Config;
	global $Corpus;


	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $Config->default_history_per_page;


	if (isset($_GET['showUser']) && $User->is_admin())
		$user_to_show = $_GET['showUser'];
	else
		$user_to_show = $User->username;

	if ($user_to_show == '~~ALL')
		$current_string = 'Currently showing history for all users';
	else
		$current_string = "Currently showing history for user <strong>&ldquo;$user_to_show&rdquo;</strong>";



	/* form for admin controls */
	if ($User->is_admin())
	{
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">Saved queries: admin controls</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<form action="index.php" method="get">
						<input type="hidden" name="ui" value="savedQs">
						<table>
							<tr>
								<td class="basicbox">Select a user...</td>
								<td class="basicbox">
								<select name="showUser">
									<option value="~~ALL" selected>all users</option>
									<?php
									$user_result = do_sql_query("SELECT distinct(user) FROM saved_queries where saved = ". CACHE_STATUS_SAVED_BY_USER ." 
														and corpus = '{$Corpus->name}' order by user");

									while ($r = mysqli_fetch_row($user_result))
										echo '<option value="' , $r[0] , '">' , $r[0] , "</option>\n\t\t\t\t\t\t";
									echo "\n";
									?>
								</select>
								</td>
								<td class="basicbox"><input type="submit" value="Show history"></td>
							</tr>
							<tr>
								<td class="basicbox">Number of records per page</td>
								<td class="basicbox">
									<select name="pp">
										<option value="10"   <?php if ($per_page == 10)   echo 'selected'; ?>>10</option>
										<option value="50"   <?php if ($per_page == 50)   echo 'selected'; ?>>50</option>
										<option value="100"  <?php if ($per_page == 100)  echo 'selected'; ?>>100</option>
										<option value="250"  <?php if ($per_page == 250)  echo 'selected'; ?>>250</option>
										<option value="350"  <?php if ($per_page == 350)  echo 'selected'; ?>>350</option>
										<option value="500"  <?php if ($per_page == 500)  echo 'selected'; ?>>500</option>
										<option value="1000" <?php if ($per_page == 1000) echo 'selected'; ?>>1000</option>
									</select>
								</td>
								<td></td>
							</tr>
							<tr>
								<td colspan="3" class="basicbox">
									<?php echo "<p>$current_string.</p>\n"; ?>
								</td>
							</tr>
						</table>
					</form>
				</td>
			</tr>
		</table>
		
		<?php
	}
	else
	{
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="1" class="concordtable">Saved queries</th>
			</tr>
		</table>
		<?php
	}



	echo print_cache_table($begin_at, $per_page, $user_to_show, false, false, 'saved');
}



function do_ui_aboutmatrix()
{
	global $Corpus;
	global $User;
	
	/* note that this function is always called via printquery_analysecorpus() */
	
	$matrix = get_feature_matrix( $_GET['aboutMatrix'] );
	
	if (false === $matrix)
		exiterror("Could not retrieve any information on the specified matrix!");
	
	if (!$User->is_admin())
		if ( $User->username != $matrix->user )
			exiterror("The specified matrix does not belong to this user account!");
	
	if ( $Corpus->name != $matrix->corpus )
		exiterror("The specified matrix is not associated with this corpus!");
	
	$variable_list  = feature_matrix_list_variables($matrix->id);
	$object_names   = feature_matrix_list_objects($matrix->id);
	
// 	$tablename = feature_matrix_id_to_tablename($matrix->id); // not used twould seem
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">Analyse corpus: Viewing feature matrix control</th>
		</tr>
		<tr>
			<td class="concordgrey">Name:</td>
			<td class="concordgeneral"><?php echo escape_html($matrix->savename); ?></td>
		</tr>
		<tr>
			<td class="concordgrey">Uses subcorpus:</td>
			<td class="concordgeneral">
				<?php echo empty($matrix->subcorpus) ? 'Whole corpus' : $matrix->subcorpus; ?>
			</td>
		</tr>

		<tr>
			<td class="concordgrey">Data objects are units of:</td>
			<td class="concordgeneral"><?php echo $matrix->unit; ?></td>
		</tr>

		<tr>
			<td class="concordgrey">Date created:</td>
			<td class="concordgeneral"><?php echo date(CQPWEB_UI_DATE_FORMAT, $matrix->create_time); ?></td>
		</tr>

		<tr>
			<td class="concordgrey">Number of variables (columns):</td>
			<td class="concordgeneral"><?php echo count($variable_list); ?></td>
		</tr>

		<tr>
			<td class="concordgrey">Number of data objects (rows):</td>
			<td class="concordgeneral"><?php echo count($object_names); ?></td>
		</tr>
		<tr>
			<td colspaN="2" class="concordgeneral" align="center">
				&nbsp;<br>
				<a class="menuOption" href="index.php?ui=analyseCorpus&showMatrix=<?php echo $matrix->id; ?>">View full matrix data</a>
				<br>&nbsp;
			</td>
		</tr>
	</table>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">Feature matrix variable list</th>
		</tr>
		<tr>
			<th class="concordtable">Variable label</th>
			<th class="concordtable">Source of variable</th>
		</tr>
		
		<?php
		
		if (empty($variable_list))
			echo "\n\t\t<tr>"
				, '<td class="concordgrey" colspan="2">&nbsp;<br>No variables found; data may be corrupted.<br>&nbsp;</td>'
				, "</tr>\n" 
				;
		else
			foreach($variable_list as $v)
				echo "\n\t\t<tr>"
					, '<td class="concordgeneral">' , $v->label , '</td>'
					, '<td class="concordgeneral">' , escape_html(mb_substr($v->source_info, 0, 50, 'UTF-8')) , '</td>'
					, '<td class="concordgeneral"><a href="analyse.php?matrix=', $matrix->id, '&correl=', $v->label ,'">[Show correlations]</a></td>'
					, "</tr>\n" 
					;
		?>
	
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Analyse feature matrix</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form action="analyse.php" method="get">
					<input type="hidden" name="matrix" value="<?php echo $matrix->id; ?>">
					&nbsp;<br>
					<input type="submit" value="Perform Multidimensional Analysis">
					<br>&nbsp;
				</form>
			</td>
		</tr>
	</table>
	
	<?php
}


function do_ui_showmatrix()
{
	global $Corpus;
	global $User;
	
	/* note that this function is always called via printquery_analysecorpus() */
	
	$matrix = get_feature_matrix( (int) $_GET['showMatrix'] );
	
	if (false === $matrix)
		exiterror("Could not retrieve any information on the specified matrix!");
	
	if (!$User->is_admin())
		if ( $User->username != $matrix->user )
			exiterror("The specified matrix does not belong to this user account!");
	
	if ( $Corpus->name != $matrix->corpus )
		exiterror("The specified matrix is not associated with this corpus!");

	
	$tablename = feature_matrix_id_to_tablename($matrix->id);
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Full matrix content</th>
		</tr>
	</table>

	<?php 
	
	$result = do_sql_query("select * from `$tablename`");
	$ncols  = mysqli_num_fields($result);

	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="<?php echo $ncols;?>">Matrix <?php echo $matrix->savename;?></th>
		</tr>
		<tr>
			<?php
			/* print column headers */
			for ( $i = 0 ; $i < $ncols ; $i++ )
				echo '<th class="concordtable">' , mysqli_fetch_field_direct($result, $i)->name . "</th>\n\t\t\t";
			echo "\n";
			?>
		</tr>
		<?php
		while ($r = mysqli_fetch_row($result))
		{
			echo "\n\t\t<tr>\n\t\t\t";
			$align = 'left';
			foreach ($r as $val)
			{
				echo '<td class="concordgeneral" style="font-size:70%;text-', $align, ';">', $val, '</td>';
				$align = 'right';
			}
			echo "\n\t\t</tr>\n";
		}
		?>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				<a class="menuItem" href="index.php?ui=analyseCorpus">[Back to Analyse Corpus menu]</a>
			</th>
		</tr>
	</table>

	<?php 
	
	
	/*
	 * NOTES
	 * This is a v quick and dirty way of printing the matrix. 
	 * At least now we have  a link to get back to the analysis screen. 
	 * 
	 */
	
	
	
}


function do_ui_analysecorpus()
{
	if (! empty($_GET['showMatrix']))
	{
		do_ui_showmatrix();
		return;
	}
	if (! empty($_GET['aboutMatrix']))
	{
		do_ui_aboutmatrix();
		return;
	}
	
	global $Config;
	global $Corpus;
	global $User;
	
	
	$subc_mapper = get_subcorpus_name_mapper($Corpus->name);
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="3">Analyse corpus</th>
		</tr>
		<tr>
			<td class="concorderror" colspan="3">
				&nbsp;<br>
				This page contains controls for advanced corpus analysis functions.
				<b>WARNING</b>: currently under development. You have been warned.
				<br>&nbsp;
				<?php 
				if (!$Config->hide_experimental_features)
				{
					// temp link
					// when well developed, will be an optioon on the proper "analyse corpus" menu below.
					?>&nbsp;<br>
					<p align="center"><a href="index.php?ui=lgcurve">Click here for the experimental Lexical Growth Curve tool</a>.</p>
					<br>&nbsp;<?php 
					// dropdown may need to be "redirect" ratrher than a jQuery gubbins??? we'll see
				}
				?>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="3">Select analysis</th>
		</tr>
		<tr>
			<td class="concordgrey" width="33.3%">
				&nbsp;<br>
				Choose an option for corpus analysis:
				<br>&nbsp;
			</td>
			<td class="concordgeneral" align="center" width="33.3%">
				<select id="analysisToolChoice">
					<!-- values match the ID of the hideable element they refer to -->
					<option value="featureMatrixDesign" selected>Design feature matrix for multivariate analysis</option>
					<option value="featureMatrixList"                      >View existing feature matrix analyses</option>
					<!-- More options will be added here later. -->
					<!-- Also: interface to corpus analysis plugins will be added here. -->
				</select>
			</td>
			<td class="concordgeneral" align="center" width="33.3%">
				<input type="button" id="analysisToolChoiceGo" value="Show analysis controls">
			</td>
		</tr>
	</table>
	
	
	<!-- begin saved feature matrix list block -->
	<table id="featureMatrixList" class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="7">
				Saved feature matrices
			</th>
		</tr>
		<tr>
			<th class="concordtable">Name</th>
			<th class="concordtable">Subcorpus</th>
			<th class="concordtable">Object unit</th>
			<th class="concordtable">Date created</th>
			<th class="concordtable" colspan="3">Actions</th>
		</tr>
		
		<?php
		
		$list = get_all_feature_matrices($Corpus->name, $User->username);

		if (empty($list))
			echo '<tr><td class="concordgrey" colspan="7">'
				, '&nbsp;<br>You have no saved features matrices.<br>&nbsp;</td></tr>'
				;
		else
			foreach($list as $fm)
				echo '<tr>'
					, '<td class="concordgeneral">' , escape_html($fm->savename), '</td>'
					, '<td class="concordgeneral">'
					, (empty($fm->subcorpus) ? '(whole corpus)' : $subc_mapper[$fm->subcorpus] )
					, '</td>'
					, '<td class="concordgeneral">' , $fm->unit , '</td>'
					, '<td class="concordgeneral">' , date(CQPWEB_UI_DATE_FORMAT, $fm->create_time) , '</td>'
					, '<td class="concordgeneral" align="center">' 
						, '<a class="menuItem" href="index.php?ui=analyseCorpus&aboutMatrix='
						, $fm->id
						, '">[View/Analyse]</a>' 
					, '</td>'
					, '<td class="concordgeneral" align="center">' 
						, '<a class="menuItem" href="multivariate-act.php?multivariateAction=downloadFeatureMatrix&matrix='
						, $fm->id
						, '">[Download]</a>'
					, '</td>'
					, '<td class="concordgeneral" align="center">' 
						, '<a class="menuItem" href="multivariate-act.php?multivariateAction=deleteFeatureMatrix&matrix='
						, $fm->id
						, '">[Delete]</a>'
					, '</td>'
					, "</tr>\n\t\t" 
					;
		?>

	</table>
	

	<!-- begin feature matrix control block -->
	<form id="featureMatrixDesign" action="multivariate-act.php" method="post">
		<input type="hidden" name="multivariateAction" value="buildFeatureMatrix">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">Design feature matrix for multivariate analysis</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2">
					&nbsp;<br>
					Explanation fo the use of feature matrices goes here.
					Note it can be used for PCA, cluster analysis or factor analysis.
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<th class="concordtable" colspan="2">Select unit of analysis</th>
			</tr>
			<tr>
				<td class="concordgrey">
					Choose a unit of analysis (for factoring, clustering, etc.)
				</td>
				<td class="concordgeneral">
					At the moment, the only choice is "text".
					<!--
					However in the future, we might want ot make it possible to use other elvels of XML in the corpus
					e.g. utterance, paragraph, chapter, etc·
					-->
				</td>
			</tr>
			<tr>
				<th class="concordtable" colspan="2">Define object labelling method</th>
			</tr>
			<tr>
				<td class="concordgrey">
					All data objects (e.g. texts) in a feature matrix need to have a label.
					<br>
					Choose one of the methods opposite for creation of object labels.
				</td>
				<td class="concordgeneral">
					<select name="labelMethod">
						<option value="id"  selected>Use &ldquo;id&rdquo; attributes, if available (recommended!)</option> 
						<option value="n"                      >Use &ldquo;n&rdquo; attributes, if available</option> 
						<option value="seq"                    >Assign a number to each object in order (fallback method)</option>
					</select>
				</td> 
			</tr>
			<tr>
				<th class="concordtable" colspan="2">Select texts<!--or, units more generally --></th>
			</tr>
			<tr>
				<td class="concordgrey" width="50%">
					Select a subcorpus or the full corpus. 
					<br>
					Only texts in the subcorpus you select will be included in the feature matrix.
					<!--
					<br>
					(when we add other possible levels, it will be possible to use subcorpora based on those divisions)
					-->
				</td>
				<td class="concordgeneral">
					<select name="corpusSubdiv">
						<option selected value="~~full~corpus~~">Use the entire corpus</option>
						<?php
						$sql = "select * from saved_subcorpora where corpus = '{$Corpus->name}' and user = '{$User->username}' order by name";
						$result = do_sql_query($sql);
						
						while (false !== ($sc = Subcorpus::new_from_db_result($result)))
							echo "\n\t\t\t\t\t\t<option value=\"{$sc->id}\">"
								, "Subcorpus &ldquo;" , $sc->name , "&rdquo; (", $sc->print_size_items() , ")" 
								, "</option>"
								;
						echo "\n";
						?>
					</select>
				</td>
			</tr>
		</table>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="5">Select features (from saved queries)</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="5">
					Use the tickboxes below to select the saved queries you want to include as features.
				</td>
			</tr>
			<tr>
				<th class="concordtable">Use?</th>
				<th class="concordtable">Name</th>
				<th class="concordtable">No. of hits</th>
				<th class="concordtable">Date</th>
				<th class="concordtable">
					Discount?
					<br>
					<span style="font-size: 70%;">(100% = No discount)</span>
				</th>
			</tr>
			
			<?php
			
			$sql = "select * from saved_queries where corpus = '{$Corpus->name}' and user = '{$User->username}' 
							and saved = " . CACHE_STATUS_SAVED_BY_USER ;
			
			$result = do_sql_query($sql);

			$saved_qs_log = array();

			for ($i = 0 ; false !== ($q = QueryRecord::new_from_db_result($result)) ; ++$i)
			{
				echo "\n<tr>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\"><input type=\"checkbox\" value=\"{$q->qname}\" name=\"useQuery$i\"></td>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\">{$q->save_name}</td>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\">", number_format($q->hits()), "</td>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\">", $q->print_time(), "</td>"
					, "\n\t<td class=\"concordgeneral\" align=\"center\"><input type=\"number\" size=\"4\" value=\"100\" name=\"q{$i}DR\"></td>"
					, "\n</tr>\n"
					;
				$saved_qs_log[$q->qname] = $q->save_name;
			}
			if (empty($saved_qs_log))
				echo "\n<tr>"
					, "\n\t<td class=\"concordgrey\" align=\"center\" colspan=\"5\">&nbsp;<br>You don't have any saved queries.<br>&nbsp;</td>"
					, "\n</tr>\n"
					;
			?>
			
		</table>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="5">Select features (based on query permutation)</th>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="5">
					This is for features whose value can only be deduced by mathemtaical manipulation of more than one saved query.
					Typical example: where a feature is equal to (search for soemthing ) minus (search for something elsE) 
				</td>
			</tr>
			<tr>
				<th class="concordtable">Use?</th>
				<th class="concordtable">Operand # 1</th>
				<th class="concordtable">Op</th>
				<th class="concordtable">Operand # 2</th>
				<th class="concordtable">
					Discount?
					<br>
					<span style="font-size: 70%;">(100% = No discount)</span>
				</th>
			</tr>

			<?php
			
			if (0 == count($saved_qs_log))
			{
				$n_arithmetic = 0;
				echo "\n<tr>"
					, "\n\t<td class=\"concordgrey\" align=\"center\" colspan=\"5\">&nbsp;<br>You don't have any saved queries.<br>&nbsp;</td>"
					, "\n</tr>\n"
					;
			}
			else
				$n_arithmetic = 15;
			
			for ($i = 1 ; $i <= $n_arithmetic ; $i++)
			{
				$use_name = "useManip" . $i;
				
				$q1_name = "manip{$i}q1";
				$q2_name = "manip{$i}q2";
				
				$op_input_name = "manip{$i}op";
				
				
				$sq_options = '<option selected>Select a saved query ...</option>';
				
				foreach ($saved_qs_log as $qn => $sn)
					$sq_options .= "\n\t\t\t<option value=\"$qn\">$sn</option>";
				?>

				<tr>
					<td class="concordgeneral" align="center">
						<input type="checkbox" name="<?php echo $use_name; ?>" value="1">
					</td>
					<td class="concordgeneral" align="center" >
						<select name="<?php echo $q1_name; ?>">
							<?php echo $sq_options, "\n"; ?>
						</select>
					</td>
					<td class="concordgeneral" align="center" >
						<input type="radio" id="<?php echo $op_input_name, ':', MultivarFeatureDef::OP_ADD ; ?>" 
							name="<?php echo $op_input_name; ?>" value="<?php echo MultivarFeatureDef::OP_ADD; ?>">
						<label for="<?php echo $op_input_name, ':', MultivarFeatureDef::OP_ADD ; ?>">
							<?php echo MultivarFeatureDef::get_op_string(MultivarFeatureDef::OP_ADD), "\n"; ?>
						</label>
						<br>
						<input type="radio" id="<?php echo $op_input_name, ':', MultivarFeatureDef::OP_SUBTRACT ; ?>" 
							name="<?php echo $op_input_name; ?>" value="<?php echo MultivarFeatureDef::OP_SUBTRACT; ?>" checked>
						<label for="<?php echo $op_input_name, ':', MultivarFeatureDef::OP_SUBTRACT ; ?>">
							<?php echo MultivarFeatureDef::get_op_string(MultivarFeatureDef::OP_SUBTRACT), "\n"; ?>
						</label>
						<br>
						<input type="radio" id="<?php echo $op_input_name, ':', MultivarFeatureDef::OP_MULTIPLY ; ?>" 
							name="<?php echo $op_input_name; ?>" value="<?php echo MultivarFeatureDef::OP_MULTIPLY; ?>">
						<label for="<?php echo $op_input_name, ':', MultivarFeatureDef::OP_MULTIPLY ; ?>">
							<?php echo MultivarFeatureDef::get_op_string(MultivarFeatureDef::OP_MULTIPLY), "\n"; ?>
						</label>
						<br>
						<input type="radio" id="<?php echo $op_input_name, ':', MultivarFeatureDef::OP_DIVIDE ; ?>" 
							name="<?php echo $op_input_name; ?>" value="<?php echo MultivarFeatureDef::OP_DIVIDE; ?>">
						<label for="<?php echo $op_input_name, ':', MultivarFeatureDef::OP_DIVIDE ; ?>">
							<?php echo MultivarFeatureDef::get_op_string(MultivarFeatureDef::OP_DIVIDE), "\n"; ?>
						</label>
					</td>
					<td class="concordgeneral" align="center">
						<select name="<?php echo $q2_name; ?>">
							<?php echo $sq_options, "\n"; ?>
						</select>
					</td>
					<td class="concordgeneral" align="center" >
						<input type="text" size="4" name="manip<?php echo $i; ?>DR" value="100">&nbsp;%
					</td>
				</tr>
				
				<?php
			}
			
			?>
			
		</table>
		
		<table class="concordtable fullwidth">
			
			<tr>
				<th class="concordtable" colspan="2">Select additional features</th>
			</tr>
			<tr>
				<th class="concordtable">Use?</th>
				<th class="concordtable">Description</th>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="2">
					<p>
						Allow extra features to be added that are not queries. The list of these is:
					</p>
					<ul>
						<li>Standardised type-token ratio (by increments of 400/1,000/2,000)</li>
						<li>Average word length (optionally limited to regular forms)</li>
<!-- 						<li>Average sub-unit length (as indicated by any XML element: s, p)</li>
						<li>Lexical density</li> 
						nb, lexical density can be done as e.g. _[N,V,J,R] or similar 
-->
					</ul>
					<p>Other statistical features can be defined via the saved-query feature function: e.g. lexical density.</p>
				</td>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					<input type="checkbox" id="useSpeshSttr04:1" name="useSpeshSttr04" value="1">
				</td>
				<td class="concordgeneral">
					<label for="useSpeshSttr04:1">Standardised type-token ratio (400 token segments)</label>
				</td>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					<input type="checkbox" id="useSpeshSttr10:1" name="useSpeshSttr10" value="1">
				</td>
				<td class="concordgeneral">
					<label for="useSpeshSttr10:1">Standardised type-token ratio (1,000 token segments)</label>
				</td>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					<input type="checkbox" id="useSpeshSttr20:1" name="useSpeshSttr20" value="1">
				</td>
				<td class="concordgeneral">
					<label for="useSpeshSttr20:1">Standardised type-token ratio (2,000 token segments)</label>
				</td>
			</tr>
			
<!-- 			<tr> -->
<!-- 				<td class="concordgrey"align="center"> -->
<!-- 					<input type="checkbox" id="useSpeshTtr:1" name="useSpeshTtr" value="1"> -->
<!-- 				</td> -->
<!-- 				<td class="concordgeneral"> -->
<!-- 					<label for="useSpeshTtr:1">Type-token ratio</label> -->
<!-- 				</td> -->
<!-- 			</tr> -->
			
			<tr>
				<td class="concordgrey"align="center">
					<input type="checkbox" id="useSpeshAvgWdLen:1" name="useSpeshAvgWdLen" value="1">
				</td>
				<td class="concordgeneral">
					<label for="useSpeshAvgWdLen:1">Average word length: all wordforms</label>
					<br>
					Every token, including punctuation, is included.
				</td>
			</tr>
			<tr>
				<td class="concordgrey"align="center">
					<input type="checkbox" id="useSpeshAvgWdLenClean:1" name="useSpeshAvgWdLenClean" value="1">
				</td>
				<td class="concordgeneral">
					<label for="useSpeshAvgWdLenClean:1">Average word length: with wordform cleanup.</label>
					<br>
					Excludes tokens that are punctuation, formulae, or otherwise unlikely to be &ldquo;real&rdquo; words.
				</td>
			</tr>
			
<!-- 			<tr> -->
<!-- 				<td class="concordgrey"> -->
<!-- 					<input type="checkbox" id="useSpeshXXXX:1" name="useSpeshXXXX" value="1"> -->
<!-- 				</td> -->
<!-- 				<td class="concordgeneral"> -->
<!-- 					<label for=""></label> -->
<!-- 				</td> -->
<!-- 			</tr> -->
<!-- 			<tr> -->
<!-- 				<td class="concordgrey"> -->
<!-- 					<input type="checkbox" id="useSpeshXXXX:1" name="useSpeshXXXX" value="1"> -->
<!-- 				</td> -->
<!-- 				<td class="concordgeneral"> -->
<!-- 					<label for=""></label> -->
<!-- 				</td> -->
<!-- 			</tr> -->
		
		</table>
		
		<table class="concordtable fullwidth">
			
			<tr>
				<td class="concordgrey" width="50%">Enter a name for this new feature matrix:</td>
				<td class="concordgeneral" width="50%">
					<!-- Does not need to be a handle. Can be anything. -->
					<input type="text" name="matrixName">
				</td>
			</tr>
		</table>
		
		<table class="concordtable fullwidth">
			<tr>
				<td class="concordgeneral" align="center" colspan="2">
					<input type="submit" value="Build feature matrix database!">
					<!--
					<p>
						The action above takes us to a new screen where the matrix already exists, and we then have
						the options for factor analysis.
					</p>
					-->
				</td>
			</tr>
		</table>
	</form>
	<!-- end feature matrix control block -->

	<?php
	
	/*
	
	Here is what will be on the controls for a saved feature matrix.
	
	(1) Export feature matrix.
		- as a plain-text file for offline analysis 
	
	(2) Configure factor analysis.
		Anything that is not a pre-calculated statistic (avg word lenghtr etc.)
		is normalised by dividing by text length.
	
	
	THE KMO test - code is in the HTML file I downloaded from the web,
	
	it requires the ginv function from the MASS library, but full code for ginv()
	is available in the MASS manual.
	
	Have an option to do it.
	
	
	*/
	
}


function do_ui_lgcurve()
{
	global $Corpus;
	global $Config;

	if ($Config->hide_experimental_features)
		exiterror("lgcurves are an experimental feature, disabled in your system.");
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="9">Leexical Growth Curve Tool</th>
		</tr>
		<tr>
			<td class="concorderror" colspan="9">
				&nbsp;<br>
				<b>WARNING</b> - Lexical Growth Curves are currently <strong>experimental</strong>.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="9">Lexical growth curves currently stored on the system</th>
		</tr>
		<tr>
			<th class="concordtable">ID</th>
			<th class="concordtable">Annotation</th>
			<th class="concordtable">Token-interval width</th>
			<th class="concordtable">No. of datapoints</th>
			<th class="concordtable">Date created</th>
			<th class="concordtable">Time taken</th>
			<th class="concordtable" colspan="3">Actions</th>
		</tr>
		
		
		<?php
		global $User;
		
		$lgc_list = list_corpus_lgcurves($Corpus->name);
		
		if (empty($lgc_list))
			echo "\n\t\t<tr><td class=\"concordgrey\" colspan=\"8\">Currently no Lexical Growth Curve data exists for this corpus.</td></tr>";
		
		foreach ($lgc_list as $lgcurve)
			echo "\n\t\t<tr>"
				, "\n\t\t\t<td class=\"concordgrey\">" , $lgcurve->id , "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">" , $lgcurve->annotation , "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">" , number_format($lgcurve->interval_width), "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">" , $lgcurve->n_datapoints , "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">" , date(CQPWEB_UI_DATE_FORMAT, $lgcurve->create_time), "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">" , number_format($lgcurve->create_duration), " s</td>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">" 
					, '<a class="menuItem" href="lgcurve-act.php?lgAction=download&lgcurve=', $lgcurve->id, '">[Download]</a></td>'
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">"
					, '<a class="menuItem" href="dataviz.php?viz=lgc&curves=', $lgcurve->id, '">[Plot graph]</a></td>'
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">"
					, ($User->is_admin() 
						? '<a class="menuItem" href="lgcurve-act.php?lgAction=delete&lgcToDelete=' . $lgcurve->id . '">[Delete]</a></td>' 
						: '&nbsp;</td>') 
				, "\n\t\t</tr>\n"
				;
		?>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">Generate new Lexical Growth Curve data</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p>
					To generate a lexical growth curve, pick which annotation you wish to apply it to (by default: just words),
					and the token interval at which you want to plot the number of cumulative types overserved.
				</p>
				<p>
					If you request a lexical growth curve with the same annotation and interval as one which already exists,
					it will <b>not</b> be recreated.
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>

		<tr>
			<td class="concordgrey">
				Select the annotation on which to build a lexical growth curve:</td>
			<td class="concordgeneral" width="50%">
				<form id="lgcurveGenerateForm" class="greyoutOnSubmit" action="lgcurve-act.php" method="get"></form>
				<input form="lgcurveGenerateForm" type="hidden" name="lgAction" value="generate">
				<select form="lgcurveGenerateForm" name="annotation">
					<option value="word" selected>Word</option>
					<?php
					foreach (get_all_annotation_info($Corpus->name) as $att)
						echo "\n\t\t\t\t\t\t<option value=\"", $att->handle, '">', escape_html($att->description), '</option>';
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Select your preferred interval (a good value is 1 thousandth the size of the corpus)</td>
			<td class="concordgeneral" width="50%">
				<select form="lgcurveGenerateForm" name="intervalWidth">
					<?php
						/* nb. this setup is a bit shonky, mgiht need a tidy later. */
						$int_values = [1000, 10000, 50000, 100000, 500000, 1000000, 5000000, 10000000];
						$preselected = 1000;
						
						/* we don't need to worry about rounding here, we are just trying to pick one on roughly the right order of magnitude. */
						while ($Corpus->size_tokens/$preselected > 2000.0)
							$preselected *= 10;
						/* e.g. if corpus is 2 billion, this will lead to 1,000,000 (2 thousand points) */
						
						foreach($int_values as $v)
							echo "\n\t\t\t\t\t\t<option value=\""
 								, $v
								, ($preselected == $v ? '" selected>': '">')
								, number_format($v)
								, '</option>'
 								;
 						echo "\n";
						?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p align="center">
					<input form="lgcurveGenerateForm" type="submit" value="Generate lexical growth curve data!">
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
	
	
	<?php 
}






function do_ui_uploadquery()
{
	global $User;
	
	$superuser_limit_msg = "(As a superuser, you are not actually restricted by this limit, but only by any limits in your webserver configuation.)\n"

	?>
	
	<form class="greyoutOnSubmit" action="upload-act.php" method="post" enctype="multipart/form-data">
		<input type="hidden" name="uplAction" value="uploadQuery">
		<?php echo ($User->has_cqp_binary_privilege() ? '' : '<input type="hidden" name="uploadBinary" value="0">'), "\n"; ?>
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="2" class="concordtable">Upload a query from an external data file</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgeneral" align="center">
					&nbsp;<br>
					You can upload files up to <?php echo number_format($User->max_upload_file()/(1024.0*1024.0), 1); ?> MB in size. 
					<?php if ($User->is_admin()) echo $superuser_limit_msg; ?>
					<br>&nbsp;
				</td>
			</tr>

			<tr>
				<td class="concordgrey">
					&nbsp;<br>
					Select file for upload:
					<br>&nbsp;
				</td>
				<td class="concordgeneral">
					&nbsp;<br>
					<input type="file" name="uploadQueryFile">
					<br>&nbsp;
				</td>
			</tr>
			
			<?php 
			if ($User->has_cqp_binary_privilege())
			{
				?>
				
				<tr>
					<td class="concordgrey">
						&nbsp;<br>
						Select type of upload:
						<br>&nbsp;
					</td>
					<td class="concordgeneral">
						&nbsp;<br>
						<input type="radio" id="uploadBinary:0" name="uploadBinary" value="0" checked>
						<label for="uploadBinary:0">Normal upload (text file containing corpus positions)</label>
						<br>
						<input type="radio" id="uploadBinary:1" name="uploadBinary" value="1">
						<label for="uploadBinary:1">Binary upload (reinsertion of a previously-exported CQP query data file)</label>
						<br>&nbsp;
					</td>
				</tr>
				
				<?php
			}
			?>
			
			<tr>
				<td class="concordgrey">
					&nbsp;<br>
					Enter a name for the new saved query:
					<br>&nbsp;
				</td>
				<td class="concordgeneral">
					&nbsp;<br>
					<input type="text" size="30" maxlength="30" name="uploadQuerySaveName">
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgeneral" align="center">
					&nbsp;<br>
					<input type="submit" value="Upload file">
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					<p>
						<strong>Instructions<?php if ($User->has_cqp_binary_privilege()) echo ' for normal (text) uploads'; ?></strong>
					</p>
					<ul>
						<li>You can use this page to upload a file to CQPweb and create a new saved query from it.</li>
						<li>The file must contain (only) two columns of corpus positions, separated by tabs.</li>
						<li>
							The numbers refer to the start point and end point of each individual &ldquo;hits&rdquo;
							of the query you want create.
						</li>
						<li>Normally, you would use (a subset of the) lines from a previously-exported query.</li>
						<li>Your query will be generated within <em>the current corpus only</em>.</li>
						<li>
							The name of the saved query can only contain letters, numbers and the underscore 
							character ("_"); it cannot contain any spaces.
						</li>
					</ul>
					
					<?php 
					if ($User->has_cqp_binary_privilege())
					{
						?>
						
						<p>
							<strong>Extra instructions for binary uploads</strong>
						</p>
						<ul>
							<li>
								As per above, your query will be created within the current corpus,
								and the name you give for the saved query must follow the same rules.
							</li>
							<li>
								The file must be an archived CQP binary data file that you previously
								exported from one of your saved queries (in this corpus).
							</li>
							<li>
								If you have modified the file in any way, or if you attempt to upload
								a query that you exported from a different CQPweb server (or a different
								corpus on this server), you will generate corrupt data with unpredictable
								&ndash; but certainly incorrect &ndash; results. So <b>don't do that</b>.
							</li>
						</ul>
						
						<?php 
					}
					?>
					
				</td>
			</tr>
		</table>
	</form>
	
	<?php
	
	/* TODO it will be pretty easy to upload a subcorpus through another form here,
	 * with relatively minor tweaks only to the code (parameterisable!) ? */
}

function print_cache_table($begin_at, $per_page, $user_to_show = NULL, $show_unsaved = true, $show_filesize = true, $delete_back_to = 'index')
{
	global $User;
	global $Corpus;
	
	if (empty($user_to_show))
		$user_to_show = $User->username;

	
	/* create sql query and set options */
	$sql = "select * from saved_queries where corpus = '{$Corpus->name}' ";
	
	if (($user_to_show == '~~ALL') && $User->is_admin())
		$usercolumn = true;
	else
	{
		$usercolumn = false;
		$user_to_show = escape_sql($user_to_show);
		$sql .= " and user = '$user_to_show' ";
	}
	if (! $show_unsaved)
		$sql .= " and saved =  " . CACHE_STATUS_SAVED_BY_USER;
	else
		$sql .= " and saved != " . CACHE_STATUS_CATEGORISED;
	
	$sql .= ' order by time_of_query DESC';
	
	$actions_colspan = 1;
	if ($User->has_cqp_binary_privilege())
		$actions_colspan++;

	/* only allow superusers to see file size */
	if (!$User->is_admin())
		$show_filesize = false;

	$result = do_sql_query($sql);

	$save_together = '';
	
	/* only show interface to union/intersect/difference if there are 2+ savedQs. */
	if (2 <= mysqli_num_rows($result) && !$show_unsaved)
	{
		$qlist_options = [];
		while ($o = mysqli_fetch_object($result))
			$qlist_options[$o->save_name] = '<option value="' . $o->query_name . '">' . $o->save_name . '</option>';
		mysqli_data_seek($result, 0);
		/* result gets reused below, so rewind it to get it ready. */
		
		ksort($qlist_options) ; 
		
		$opt_block = '<option selected>Select a query...</option> '. implode(' ', $qlist_options);
		$save_together = <<<END_OF_HTML_SAVE_TOGETHER
			<tr><td>
			<form action="temp-save-act.php" method="get">
				<table class="concordtable fullwidth" width="100%">
					<tr>
						<th class="concordtable">Unify two saved queries into one</th>
					</tr>
					<tr>
						<td class="concordgeneral">
							<p>
								<select name="q_a">$opt_block</select> 
								and 
								<select name="q_b">$opt_block</select> 
							</p>
						</td>
					</tr>
					<tr>
						<td class="concordgeneral">
							<p>
								Specify name for new saved query: 
								<input type="text" name="saveScriptSaveName">
							</p>
						</td>
					</tr>
					<tr>
						<td class="concordgeneral">
							<p>
								<input type="submit" value="Unify saved queries">
							</p>
						</td>
				</table>
			</form>
			</td></tr>
			
END_OF_HTML_SAVE_TOGETHER;
	}


	$s = $save_together . '

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">No.</th>
			' . ($usercolumn ? '<th class="concordtable">User</th>' : '') . '
			<th class="concordtable">Name</th>
			<th class="concordtable">No. of hits</th>
			' . ($show_filesize ? '<th class="concordtable">File size</th>' : '') . '
			<th class="concordtable">Date</th>
			<th class="concordtable" colspan="' . $actions_colspan .'">Actions</th>
			<th class="concordtable">Delete</th>
		</tr>';

	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysqli_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	
	if ($toplimit == 1)
		$s .= '<tr><td class="concordgrey" colspan="' . ($usercolumn ? '8' : '7') . '" align="center">
				&nbsp;<br>No saved queries were found.<br>&nbsp;
				</td</tr>';

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		if (false === ($qr = QueryRecord::new_from_db_result($result)))
			break;
		if ($i < $begin_at)
			continue;
		
		$s .= "\n\t\t<tr><td class='concordgeneral' align='center'>$i</td>";
		
		if ($usercolumn)
			$s .=  "<td class='concordgeneral' align='center'>" . $qr->user . '</td>';
		
		$print_name = ($qr->saved == CACHE_STATUS_UNSAVED ? $qr->qname : $qr->save_name);
		
		$s .= '<td class="concordgeneral">' 
			. '<a id="showLink:' . $i . '" class="hasToolTip" href="concordance.php?qname='
			. $qr->qname . '" data-tooltip="Show query solutions">'
			. $print_name . '</a></td>';

		$s .= '<td class="concordgeneral" align="center">' . number_format($qr->hits()) . '</td>';
		
		if ($show_filesize)
			$s .= "<td class='concordgeneral' align='center'>" . round(($qr->file_size/1024), 1) . ' Kb</td>';
		
		$s .= '<td class="concordgeneral" align="center">' . $qr->print_time() . '</td>';
		
		if ($qr->saved == CACHE_STATUS_SAVED_BY_USER)
			$s .= '<td class="concordgeneral" align="center">' 
				. '<a id="rnmSQ:' . $i . '" class="menuItem hasToolTip" href="savequery.php?sqAction=get_save_rename&qname='
				. $qr->qname . '" data-tooltip="Rename this saved query">'
				. '[Rename]</a></td>'
				;
		else
			$s .= '<td class="concordgeneral" align="center">-</td>';
		
		if ($User->has_cqp_binary_privilege())
			$s .= '<td class="concordgeneral" align="center">' 
				. '<a id="expSQb:' . $i . '" class="menuItem hasToolTip" href="savequery-act.php?&sqAction=binary_export&qname='
				. $qr->qname . '" data-tooltip="Download the raw CQP binary file for this query">'
				. '[Export datafile]</a></td>'
				;

		$s .= '<td class="concordgeneral" align="center">' 
				. '<a id="delSQ:' . $i . '" class="menuItem hasToolTip" href="savequery-act.php?sqAction=delete_saved&qname='
				. $qr->qname . '&backTo=' . $delete_back_to . '" data-tooltip="Delete this saved query">'
				. '[x]</a>'
			. "</td></tr>\n"
			;
		$s .= "</tr>\n";
	}
	
	$s .= "\t</table>\n\n\n";
	
	$navlink_base =  'index.php?ui=cachedQueries';
	
	$navlinks = print_simple_navlinks(
		$navlink_base, $begin_at, $i, 
		mysqli_num_rows($result), 
		$per_page, false,
		'Newer queries', 'Older queries'
		);

	return $s . $navlinks;
}





