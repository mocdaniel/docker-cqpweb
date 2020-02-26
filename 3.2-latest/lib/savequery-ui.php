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
 * Contains the interface for converting an everyday, cached query to a user-saved query.
 */






/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');


/* include defaults and settings */
require('../lib/environment.php');


/* include function files */
require('../lib/html-lib.php');
require('../lib/cache-lib.php');
require('../lib/xml-lib.php');
require('../lib/scope-lib.php');
require('../lib/concordance-lib.php');
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');



/* declare global variables */
$Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);



if (!isset($_GET['sqAction']))
	$script_action = 'get_save_name';
else
	$script_action = $_GET['sqAction'];

$qname = safe_qname_from_get();

if (!check_cached_query($qname))
	$script_action = 'errorNotInCache';



switch ($script_action)
{

case 'errorNotInCache':

	echo print_html_header('Save Query -- CQPweb', $Config->css_path, array('cword'));
	do_ui_savequery_nocache();
	break;

	
case 'save_name_error':

	echo print_html_header('Save Query -- CQPweb', $Config->css_path, array('cword'));
	do_ui_savename_bad_or_exists(false, isset($_GET['saveScriptNameExists']) ? $_GET['saveScriptNameExists'] : NULL);
	do_ui_savequery($qname);
	break;
	


case 'get_save_name':

	echo print_html_header('Save Query -- CQPweb', $Config->css_path, array('cword'));
	do_ui_savequery($qname);
	break;
	

case 'rename_error':

	echo print_html_header('Rename Saved Query -- CQPweb', $Config->css_path);
	do_ui_savename_bad_or_exists(true, isset($_GET['saveScriptNameExists']) ? $_GET['saveScriptNameExists'] : NULL);
	do_ui_renamequery($qname);
	break;



case 'get_save_rename':
	
	echo print_html_header('Rename Saved Query -- CQPweb', $Config->css_path);
	do_ui_renamequery($qname);
	break;

	
case 'enterCategories':
	
	echo print_html_header('Categorise Query -- CQPweb', $Config->css_path);
	do_ui_categorise_enter_categories($qname, isset($_GET['categoriseProblem']) ? $_GET['categoriseProblem'] : NULL);
	break;
	

case 'enterNewValue':
	
	echo print_html_header('Categorise Query -- CQPweb', $Config->css_path, array('cword'));
	do_ui_categorise_enter_new_value($qname);
	break;

}

cqpweb_shutdown_environment();






/** Prints the UI for saving a query under a particular name; then finishes the page layout. */
function do_ui_savequery($qname_to_pass)
{

// TODO: params we need to pass here, if specified, are: 
//  kwic v line, per page and page number 
// so that savequiery-act can redirecft back to where we were. 
// Maybe have a function: get_concordance_view_point() annd print_concordance_view_point_as_params() / print_concordance_view_point_as_inputs() ?
// where a viewpoint = pp, pageNo, kwic/line and all of these are optional?? (Maybe also: add in the "align" params if present?)
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Save a query result</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<form action="savequery-act.php" method="get">
					<input type="hidden" name="sqAction" value="ready_to_save" >
					<input type="hidden" name="qname" value="<?php echo $qname_to_pass; ?>">
					<?php /* TODO !!!!!! */ echo url_printinputs(array(['redirect', ''], ['sqAction', ''], ['qname', ''])); ?>
					<table>	
						<tr>
							<td class="basicbox" width="35%">Please enter a name for your query:</td>
							<td class="basicbox">
								<input type="text" name="saveScriptSaveName" size="50" maxlength="200">
								&nbsp;&nbsp;&nbsp;
								<input type="submit" value="Save the query">
							</td>
						</tr>
						<tr>
							<td class="basicbox" colspan="2">
								The name for your saved query may be up to 200 characters long (only unaccented letters, numbers, and underscore allowed!)
								After entering the name you will be taken back to the previous query result display. 
								The saved query can be accessed through the <strong>Saved queries</strong> link on the main page.
			 				</td>
			 			</tr>
			 		</table>
				</form>
			</td>
		</tr>
	</table>
	
	<?php
	echo print_html_footer('savequery');
}





/**
 * Does the main form and ends the page for the "rename saved query" UI.
 */
function do_ui_renamequery($qname_to_pass)
{
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Rename a saved query</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<form action="savequery-act.php" method="get">
					<input type="hidden" name="sqAction" value="rename_saved" >
					<input type="hidden" name="qname" value="<?php echo $qname_to_pass; ?>">
					<table>
						<tr>
							<td class="basicbox" width="35%">Please enter a new name for your query:</td>
							<td class="basicbox">
								<input type="text" name="saveScriptSaveReplacementName" size="50" maxlength="200" >
								&nbsp;&nbsp;&nbsp;
								<input type="submit" value="Rename the query" >
							</td>
						</tr>
						<tr>
							<td class="basicbox" colspan="2">
								The name for your saved query may be up to 200 characters long.
								After entering the name you will be taken back to the list of saved queries.
				 			</td>
				 		</tr>
				 	</table>
					<?php     echo /*url_printinputs(array(array('redirect', ''),array('sqAction', ''), ['qname',''] )), */"\n"; ?>
				</form>
			</td>
		</tr>
	</table>
	
	<?php
	echo print_html_footer('savequery');
}



/** Prints the error-message UI if the query-to-save is not in cache. Ends the page. */
function do_ui_savequery_nocache()
{
	global $Config;

	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Save Query: Error message</th>
		</tr>
		<tr>
			<td class="concorderror">
				<p class="errormessage">
					CQPweb cannot complete your save-query request
					because the query you are trying to save 
					no longer exists in CQPweb's memory.
				</p>
				<p class="errormessage">
					Please try running the query again  
					(from <a href="index.php?ui=history">your Query History</a>)
					and then saving the query from there. 
				</p>
				<p class="errormessage">
					If you get this error message repeatedly, you should report it
					to your server&rsquo;s system administrator.
					<?php
					if (! empty($Config->server_admin_email_address))
						echo "\n\t\t\t\t</p>\n\t\t\t\t"
							, '<p class="errormessage">Your server administrator&rsquo;s contact email address is: <b>'
							, $Config->server_admin_email_address
							, "</b>.</p>\n"
							;
					else
						echo "\n";
					?>
				</p>
			</td>
		</tr>

	</table>
	
	<?php
	echo print_html_footer('savequery');
}

/** Prints the error-message UI for a supplied name which is badly-formed or does not exist. */
function do_ui_savename_bad_or_exists($for_rename_procedure, $name_which_already_exists = NULL)
{
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable"><?php echo $for_rename_procedure ? 'Rename Saved' : 'Save' ; ?> Query: Error message</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<table>
					<tr>
						<td class="basicbox">
							<?php
							if (!empty($name_which_already_exists))
								echo "A query called <strong>"
			 						, escape_html($name_which_already_exists)
									, "</strong> has already been saved. Please specify a different name.<br>&nbsp;<br>\n"
			 						;
							?>
							
							Names for saved queries can only contain letters, numbers and 
							the underscore character ("_"), and cannot be longer than 200 characters!
							
							<br>&nbsp;<br>
							
							Enter a name that follows this rule into the form below.
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	
	<?php
}







/** This function prints a page with a simple form for a new categorisation value to be entered */
function do_ui_categorise_enter_new_value($qname_to_pass)
{
	global $Config;

// 	$qname = safe_qname_from_get();

	?>
	<form action="redirect.php" method="get">
		<input type="hidden" name="qname" value="<?php echo $qname_to_pass; ?>">
		<input type="hidden" name="redirect" value="categorise-do">
		<input type="hidden" name="sqAction" value="addNewValue">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="2" class="concordtable">
					Add a category to the existing set of categorisation values for this query
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					&nbsp;<br>
					Current categories:
					<br>&nbsp;
				</td>
				<td class="concordgeneral">
					<em>
						<?php echo implode(', ', catquery_list_categories($qname_to_pass)); ?>
					</em>
				</td>
			</tr>
			<tr>
				<td class="concordgrey">
					&nbsp;<br>
					New category:
					<br>&nbsp;
				</td>
				<td class="concordgeneral" >
					&nbsp;<br>
					<input type="text" name="newCategory" maxlength="99">
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="center" colspan="2">
					<input type="submit" value="Submit">
				</td>
			</tr>
		</table>
	</form>
	
	<?php
	
}




/**
 * This function prints a webpage enabling the user to enter their category names;
 * passing it an error argument affects the display in various ways,
 * but it will always produce a full webpage.
 */
function do_ui_categorise_enter_categories($qname_to_pass, $error = NULL)
{
	global $Config;

// 	$qname = safe_qname_from_get();


	/* if an error is specified, an error message is printed at the top, and the values from GET are re-printed */
	switch($error)
	{
	case 'no_name':
		$error_message = 'You have not entered a name for your query result! Please amend the settings below.';
		break;
	case 'bad_names':
		$error_message = 'Query names and category labels can only contain letters, numbers and the underscore character' .
			' (&ldquo;_&rdquo;)! Moreover, they can only be 100 letters long (the query name) or' .
			' 99 letters long (the categories). Please amend the badly-formed name(s) below (an alternative has been suggested).';
		break;
	case 'no_cats':
		$error_message = 'You have not entered any categories! Please add some category names below.';
		break;
	case 'name_exists':
		$error_message = 'A categorised or saved query with the name you specified already exists! Please choose a different name.';
		break;
	case 'cat_repeated':
		$error_message = 'You have entered the same category more than once! Please double-check your category names.';
		break;

	/* note that default includes "NULL", which is the norm */
	default:
		break;
	}


	?>

	<form action="redirect.php" method="get">
		<input type="hidden" name="qname" value="<?php echo $qname_to_pass; ?>">
		<input type="hidden" name="redirect" value="categorise-do">
		<input type="hidden" name="sqAction" value="createCategorisedQuery">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="2" class="concordtable">Categorise query results</th>
			</tr>
			
			<?php
			if (!empty($error_message))
				echo "\n", '<tr><td class="concorderror" colspan="2"><strong>Error!</strong><br>' , $error_message , "</td></tr>\n";
			?>
			
			<tr>
				<td class="concordgrey">
					&nbsp;<br>
					Please enter a name for this set of categories:
					<br>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					<input type="text" name="categoriseCreateName" size="34" maxlength="100"
					<?php 
					if ($error !== NULL && isset($_GET['categoriseCreateName']))
						echo 'value="' , substr(preg_replace('/\W/', '', $_GET['categoriseCreateName']), 0, 100) , '"';
					?>
					>
				</td>
			</tr>
			<tr>
				<th class="concordtable">List category labels:</th>
				<th class="concordtable">Default category?</th>
			</tr>
			
			<?php
			for ($i = 1 ; $i < 7 ; $i++)
			{
				$val = '';
				$checked = '';
				
				if ($error !== NULL && isset($_GET["cat_$i"]))
					$val = ' value="' . substr(preg_replace('/\W/', '', $_GET["cat_$i"]), 0, 99) . '"';
				if ($error !== NULL && (isset($_GET["defaultCat"]) && $_GET["defaultCat"] == $i) )
					$checked = ' checked';
					
				echo "
				<tr>
					<td class=\"concordgeneral\" align=\"center\">
						<input type=\"text\" name=\"cat_$i\" size=\"34\" maxlength=\"99\"$val>
					</td>
					<td class=\"concordgeneral\" align=\"center\">
						<input type=\"radio\" id=\"defaultCat:$i\" name=\"defaultCat\" value=\"$i\"$checked>
					</td>
				</tr>\n\n";
			}
			?>
			
			<tr>
				<td class="concordgeneral" align="center" colspan="2">
					<input type="submit" value="Submit">
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					<strong>Instructions</strong>
					<br>&nbsp;<br>
					<ul>
						<li>
							Category names can only contain letters, numbers and the underscore character
							(&nbsp;<strong>_</strong>&nbsp;) and can be at most 99 letters long. 
						</li>
						<li>
							The categories <strong>Unclear</strong> and <strong>Other</strong>
							will be automatically added to the list
						</li>
						<li>
							Selecting a default category will mean that all hits will be automatically 
							set to this value. This can be useful if you expect most of the hits
							to belong to one particular category. However, it will mean that you 
							have to go through the <em>complete</em> set of concordances (and not only 
							the first x number of hits of a randomly-ordered query result).
						</li>
						<li>
							You can add additional categories at any time.
						</li>
					</ul>
				</td>
			</tr>
		</table>
	</form>
	
	<?php
	echo print_html_footer('categorise');

}

