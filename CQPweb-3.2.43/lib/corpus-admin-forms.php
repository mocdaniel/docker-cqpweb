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

// TODO rename: corpus-manage-forms or just corpus-forms???


function do_ui_corpussettings()
{
	global $Corpus;
	global $User;

	/* convenience vars */
	$r2l = $Corpus->main_script_is_r2l;
	$case_sensitive = $Corpus->uses_case_sensitivity;

	$advanced_view = $User->is_admin();

	$classifications = list_text_metadata_classifications($Corpus->name);
	$class_options = '';

	foreach ($classifications as $class_handle => $class_desc)
		$class_options .= "\n\t\t<option value=\"$class_handle\""
					. ($class_handle == $Corpus->primary_classification_field ? 'selected' : '')
					. '>'. escape_html($class_desc) . '</option>'
					;
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Corpus settings</th>
		</tr>
	</table>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="3">Core corpus options</th>
		</tr>

		<tr>
			<td class="concordgrey" align="center">
				Corpus title:
				<form id="updateCorpusTitleForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updateCorpusTitleForm" type="hidden" name="caAction" value="updateCorpusTitle">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateCorpusTitleForm" type="text" name="newTitle" value="<?php echo escape_html($Corpus->title); ?>">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateCorpusTitleForm" type="submit" value="Update">
			</td>
		</tr>

		<tr>
			<td class="concordgrey" align="center">
				Directionality of main corpus script:
				<form id="updateScriptR2LForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updateScriptR2LForm" type="hidden" name="caAction" value="updateScriptR2L">
			</td>
			<td class="concordgeneral" align="center">
				<select form="updateScriptR2LForm" name="isR2L">
					<!-- note, false = left-to-right -->
					<option value="0" <?php echo ($r2l ? '' : 'selected'); ?>>Left-to-right</option>
					<option value="1" <?php echo ($r2l ? 'selected' : ''); ?>>Right-to-left</option>
				</select>
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateScriptR2LForm" type="submit" value="Update">
			</td>
		</tr>

		<?php 
		if ($advanced_view)
		{
			// TODO longterm = we might let this be changed by a user-owner as a "job"
			// as it would require disabling the corpus while freqlists are rebuilt.
			?>

			<tr>
				<td class="concordgrey" align="center">
					Corpus requires case-sensitive collation for string comparison and searches
					<form id="updateCaseSensitiveForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
					<input form="updateCaseSensitiveForm" type="hidden" name="caAction" value="updateCaseSensitive">
					<br>&nbsp;<br>
					<em>
						(note: the default, and recommended, value is &ldquo;No&rdquo;; if you change this
						<br>
						setting, you must delete and recreate all frequency lists and delete cached databases)
					</em> 
				</td>
				<td class="concordgeneral" align="center">
					<select form="updateCaseSensitiveForm" name="isCS">
						<!-- note, 0 (false) = set to false -->
						<option value="0" <?php echo ($case_sensitive ? '' : 'selected'); ?>>No</option>
						<option value="1" <?php echo ($case_sensitive ? 'selected' : ''); ?>>Yes</option>
					</select>
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateCaseSensitiveForm" type="submit" value="Update">
				</td>
				</tr>

			<?php
		}

		?>


		<!-- ***************************************************************************** -->

		<tr>
			<th class="concordtable" colspan="3">Display settings</th>
		</tr>

		<?php 
		if ($advanced_view)
		{
			?>

			<tr>
				<td class="concordgrey" align="center">
					Stylesheet address
					(<a href="<?php echo $Corpus->css_path; ?>" target="_blank">click here to view</a>):
					<form id="updateCssPathForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
					<input form="updateCssPathForm" type="hidden" name="caAction" value="updateCssPath">
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateCssPathForm" type="text" name="newCssPath" value="<?php echo escape_html($Corpus->css_path); ?>">
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateCssPathForm" type="submit" value="Update">
				</td>
			</tr>

			<?php
		}
		else
		{
			//TODO - alternative way to pick a colour goes here!
		}

		?>

		<tr>
			<td class="concordgrey" align="center">
				How many words/elements of context should be shown in concordances?
				<br>&nbsp;<br>
				<form id="updateConcScopeForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updateConcScopeForm" type="hidden" name="caAction" value="updateConcScope">
				<em>Note</em>: context of a hit is counted <strong>each way</strong>.
			</td>
			<td class="concordgeneral" align="center">
				show
				<input form="updateConcScopeForm" type="number" name="newConcScope" size="3" value="<?php echo $Corpus->conc_scope; ?>">
				of
				<select form="updateConcScopeForm" name="newConcScopeUnit">
					<?php

						echo '<option value="*words*"' 
							, ( empty($Corpus->conc_s_attribute) ? ' selected' : '' ) 
							, '>words</option>'
 							;

						foreach (list_xml_elements($Corpus->name) as $element => $element_desc)
							echo "<option value=\"$element\""
								, ($element == $Corpus->conc_s_attribute ? ' selected' : '')
								, ">XML element: "
								, escape_html($element_desc)
								, " ($element)</option>"
								;
						echo "\n";

						?>
				</select>
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateConcScopeForm" type="submit" value="Update">
			</td>
		</tr>

		<tr>
			<td class="concordgrey" align="center">
				Initial words to show (each way) in extended context:
				<form id="updateInitExtContextForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updateInitExtContextForm" type="hidden" name="caAction" value="updateInitExtContext">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateInitExtContextForm" type="text" name="newInitExtContext" value="<?php echo $Corpus->initial_extended_context; ?>">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateInitExtContextForm" type="submit" value="Update">
			</td>
		</tr>

		<tr>
			<td class="concordgrey" align="center">
				Maximum words to show (each way) in extended context:
				<form id="updateMaxExtContextForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updateMaxExtContextForm" type="hidden" name="caAction" value="updateMaxExtContext">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateMaxExtContextForm" type="number" name="newMaxExtContext" value="<?php echo $Corpus->max_extended_context; ?>">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateMaxExtContextForm" type="submit" value="Update">
			</td>
		</tr>

		<tr>
			<td class="concordgrey" align="center">
				Word annotation to make available as alternative view in extended context:
				<form id="updateAltViewAttForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updateAltViewAttForm" type="hidden" name="caAction" value="updateAltViewAtt">
			</td>
			<td class="concordgeneral" align="center">
				<select form="updateAltViewAttForm" name="newAltAtt">

					<?php
						echo '<option value=""'
							, (empty($Corpus->alt_context_word_att)) ?  ' selected' : ''
							, '>Do not make alternative view available</option>'
 							;
						foreach (list_corpus_annotations($Corpus->name) as $att=>$desc)
							echo '<option value="'
 								, $att, '"'
 								, ($Corpus->alt_context_word_att == $att ? ' selected>' : '>')
 								, escape_html($desc)
 								, '</option>'
 								;
						?>

				</select>
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateAltViewAttForm" type="submit" value="Update">
			</td>
		</tr>


		<!-- ***************************************************************************** -->

		<tr>
			<th class="concordtable" colspan="3">General options</th>
		</tr>

		<?php

		if ($advanced_view)
		{
			?>

			<tr>
				<td class="concordgrey" align="center">
					The corpus is currently in the following category:
					<form id="updateCorpusCategoryForm" class="greyoutOnSubmit" action="execute.php" method="get"></form>
					<input form="updateCorpusCategoryForm" type="hidden" name="function" value="update_corpus_category">
					<input form="updateCorpusCategoryForm" type="hidden" name="locationAfter" value="index.php?ui=corpusSettings">
				</td>
				<td class="concordgeneral" align="center">
					<select form="updateCorpusCategoryForm" name="args">
						<?php
						foreach (list_corpus_categories() as $i => $c)
							echo "<option value=\"{$Corpus->name}#$i\"", ( ($Corpus->corpus_cat == $i) ? ' selected': ''), ">$c</option>\n\t\t\t\t\t\t";
						echo "\n";
						?>
					</select>
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateCorpusCategoryForm" type="submit" value="Update">
				</td>
			</tr>
			
			<tr>
				<td class="concordgrey" align="center">
					Visibility of the corpus is currently set to:
					<br>&nbsp;<br>
					<em>
						(note: &ldquo;Visible&rdquo; means the corpus is accessible through the main menu.
						<br>
						Invisible corpora can still be accessed by direct URL entry by people who know the address.
					</em>
					<form id="updateVisibilityForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
					<input form="updateVisibilityForm" type="hidden" name="caAction" value="updateVisibility">
				</td>
				<td align="center" class="concordgeneral">
					<table>
						<tr>
							<td class="basicbox">
								<input form="updateVisibilityForm" type="radio" name="newVisibility" id="newVisibility:1" value="1"
									<?php echo $Corpus->visible ? ' checked' : ''; ?>>
							</td>
							<td class="basicbox">
								<label for="newVisibility:1">Visible</label>
							</td>
						</tr>
						<tr>
							<td class="basicbox">
								<input form="updateVisibilityForm" type="radio" name="newVisibility" id="newVisibility:0" value="0"
									<?php echo $Corpus->visible ? '' : ' checked'; ?>>
							</td>
							<td class="basicbox">
								<label for="newVisibility:0">Invisible</label>
							</td>
						</tr>
					</table>
				</td>

				<td align="center" class="concordgeneral">
					<input form="updateVisibilityForm" type="submit" value="Update!">
				</td>
			</tr>

			<?php
		}
		?>

		<tr>
			<td class="concordgrey" align="center">
				The external URL (for documentation/help links) is:
				<form id="updateExternalUrlForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updateExternalUrlForm" type="hidden" name="caAction" value="updateExternalUrl">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateExternalUrlForm" type="url" name="newExternalUrl" maxlength="255" value="<?php echo escape_html($Corpus->external_url); ?>">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateExternalUrlForm" type="submit" value="Update">
			</td>
		</tr>

		<tr>
			<td class="concordgrey" align="center">
				The primary text categorisation scheme is currently:
				<form id="updatePrimaryClassificationForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updatePrimaryClassificationForm" type="hidden" name="caAction" value="updatePrimaryClassification">
			</td>
			<td class="concordgeneral" align="center">
				<select form="updatePrimaryClassificationForm" name="newField">
					<?php
					if (empty($class_options))
					{
						$button = '&nbsp;';
						echo '<option selected>There are no classification schemes for this corpus.</option>', "\n";
					}
					else
					{
						$button = '<input form="updatePrimaryClassificationForm" type="submit" value="Update">';
						echo $class_options, "\n";
					}
					?>
				</select>
			</td>
			<td class="concordgeneral" align="center">
				<?php echo $button, "\n"; ?>
			</td>
		</tr>
	</table>


	<!-- ***************************************************************************** -->

	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">Corpus-level metadata</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p>
					The corpus-level metadata is a set of freeform attribute/value pairs that will become
					visible in the user interface (under &ldquo;Corpus info &gt; View corpus metadata&rdquo;).
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>

		<tr>
			<td class="concordgrey" align="center" width="50%">Attribute</td>
			<td class="concordgrey" align="center">Value</td>
		</tr>

		<tr>
			<td class="concordgeneral" align="center">
				<form id="addVariableCorpusMetadataForm" class="greyoutOnSubmit" action="metadata-act.php" method="get"></form>
				<input form="addVariableCorpusMetadataForm" type="hidden" name="mdAction" value="addVariableCorpusMetadata">
				<input form="addVariableCorpusMetadataForm" type="text" name="variableMetadataAttribute">
			</td>
			<td class="concordgeneral" align="center">
				<input form="addVariableCorpusMetadataForm" type="text" name="variableMetadataValue">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p>
					<input form="addVariableCorpusMetadataForm" type="submit" value="Add a new item to the corpus metadata">
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" align="center" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p>
					<em><?php
						$varmeta = get_all_variable_corpus_metadata($Corpus->name);
						echo 0 < count($varmeta) 
								? 'Existing items of variable corpus-level metadata (as attribute-value pairs):' 
								: 'No items of variable corpus-level metadata have been set.'
 							, "\n"
							;
						?></em>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>

		<?php
		foreach ($varmeta as $metadata)
		{
			$del_link = 'metadata-act.php?mdAction=deleteVariableCorpusMetadata'
 				. '&variableMetadataAttribute='
 				. urlencode($metadata['attribute']) 
				. '&variableMetadataValue=' 
 				. urlencode($metadata['value']) 
				;
			?>
			<tr>
				<td class="concordgeneral" align="center">
					Attribute  [<strong><?php echo escape_html($metadata['attribute']); ?></strong>]
					with value [<strong><?php echo escape_html($metadata['value']);     ?></strong>]
				</td>
				<td class="concordgeneral" align="center">
					<a class="menuItem" href="<?php echo $del_link; ?>">[Delete]</a>
				</td>
			</tr>
			<?php
		}
		?>
		
	</table>
	
	<?php
}



function do_ui_sharecorpus()
{
		//TODO
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Share access to this corpus</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Feature not yet available.
			</td>
		</tr>
	</table>
	
	<?php
}




function do_ui_manageaccess()
{

// TODO, when there are lots of users, this screen gets pretty unreadable... pretty fast!


	/// TODO - the access stateent is deactivated for user corpora
	/// but what actually needs doing is a separate funciton for user corpoira 
	/// which uses data shares instead of privileges to work out who can access the corpus. 
	/// "disabled readonly\n" is a stopgap.
	
	
	global $Corpus;
	global $User;

// 	$options_groups_to_add = '';

	$short_priv_desc = array(
		PRIVILEGE_TYPE_CORPUS_FULL       => 'Full',
		PRIVILEGE_TYPE_CORPUS_NORMAL     => 'Normal',
		PRIVILEGE_TYPE_CORPUS_RESTRICTED => 'Restricted'
		);
	
	$all_users_allowed = array();
	
	$corpus_privileges = get_all_privileges_info(array('corpus'=>$Corpus->name));
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">Corpus access control panel</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center" colspan="4">
				<p class="spacer">&nbsp;</p>
				<p>The following privileges control access to this corpus:</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<th class="concordtable">ID</th>
			<th class="concordtable">Description</th>
			<th class="concordtable">Access level</th>
			<th class="concordtable">Granted to...</th>
		</tr>
		
		<?php
		
		foreach ($corpus_privileges as $p)
		{
			$users_with = list_users_with_privilege($p->id);
			natcasesort($users_with);
			$grant_string_u = (empty($users_with) ? '&nbsp;' : "<b>Users:</b> " . implode(', ',$users_with));
			$all_users_allowed = array_merge($all_users_allowed, $users_with);
			
			
			$groups_with = list_groups_with_privilege($p->id);
			natcasesort($groups_with);
			$grant_string_g = (empty($groups_with) ? '&nbsp;' : "<b>Groups:</b> " . implode(', ',$groups_with));
			foreach($groups_with as $gw)
				$all_users_allowed = array_merge($all_users_allowed, list_users_in_group($gw));
			
			echo "\t\t<tr>\n"
				, "\t\t\t<td class=\"concordgeneral\" align=\"center\">{$p->id}</td>\n"
				, "\t\t\t<td class=\"concordgeneral\">{$p->description}</td>\n"
				, "\t\t\t<td class=\"concordgeneral\">{$short_priv_desc[$p->type]}</td>\n"
				, "\t\t\t<td class=\"concordgeneral\">$grant_string_g<br>$grant_string_u</td>\n"
				;
		}
		?>

		<tr>
			<td class="concordgrey" align="center" colspan="4">
				&nbsp;<br>
				<a class="menuItem" href="../adm/index.php?ui=userGrants">Manage individual privileges</a>
				|
				<a class="menuItem" href="../adm/index.php?ui=groupGrants">Manage group privileges</a>
				| 
				<a class="menuItem" href="../adm/index.php?ui=groupMembership">Manage group membership</a>
				<br>&nbsp;
			</td>
		</tr>

		<tr>
			<th class="concordtable" colspan="4">Corpus access statement</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="4">
				<p class="spacer">&nbsp;</p>
				<p>An <em>access statement</em> can be given to each corpus. This appears to users who try to access the data when they lack permission.</p>
				<p>It can be useful to explain why they can't use the data (to avoid too many queries for the server administrator!)</p>
				<p class="spacer">&nbsp;</p>
				<p>The statement can include links, as follows:</p>
				<ul>
					<li><code>&lt;a href="http://www.somewhere.net/some-page.html"&gt;Click here to go to somewhere.net!&lt;/a&gt;</code></li>
				</ul> 
				<p>
					&hellip; but any other HTML codes will be deactivated.
					<a href="index.php?ui=previewAccessStatement">Click here for a preview of how the current version appears.</a>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="4">
				<form class="greyoutOnSubmit" action="corpus-act.php" method="post">
					<input type="hidden" name="caAction" value="updateAccessStatement">
					<p class="spacer">&nbsp;</p>
					<textarea 
						name="statement" 
						placeholder="The access statement is currently empty...."
						rows="24"
						cols="80"
						<?php if ($Corpus->is_user_owned()) echo "disabled readonly\n"; ?>
						><?php echo escape_html($Corpus->access_statement); ?></textarea>
					<p class="spacer">&nbsp;</p>
					<?php if ($User->is_admin()) echo '<p><input type="submit" value="Update the access statement"></p>', "\n"; ?>
					<p class="spacer">&nbsp;</p>
				</form>
			</td>
		</tr>

		<tr>
			<th class="concordtable" colspan="4">Full list of users with access </th>
		</tr>
		<tr>
			<td class="concordgrey" align="center" colspan="4">
				<p class="spacer">&nbsp;</p>
				<p>The following users have access to this corpus (any level), individually or via a group membership:</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="4">
				<p class="spacer">&nbsp;</p>
				<table class="basicbox">
					<tr>
					
						<?php
						$all_users_allowed = array_values(array_unique($all_users_allowed));
						natcasesort($all_users_allowed);
						
						for ($i = 0, $n = count($all_users_allowed); $i < $n ; $i++)
							echo "\n\t\t\t\t\t\t<td class=\"basicbox\">"
								, "<a href=\"../adm/index.php?ui=userView&username={$all_users_allowed[$i]}\">{$all_users_allowed[$i]}</a>"
	 							, "</td>"
								, ( 0 == (($i+1) % 8) && ($i+1) != $n ) ? "\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>" : ''
	 							;
						?>
					
					</tr>
				</table>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
	
	<?php
}


function do_ui_previewaccessstatement()
{
	global $Corpus;	

	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Preview of corpus access statement</th>
		</tr>
		<tr>
			<td class="concordgrey" >
				<p class="spacer">&nbsp;</p>
				<p>
					Below is a preview of how this corpus's access statement will appear
					when it is shown to users who try to access it but do not have
					the necessary permissions.
				</p> 
				<p>
					<a href="index.php?ui=manageAccess">Click here to edit the access statement.</a>
				</p>
				<p class="spacer">&nbsp;</p>	
			</td>
		</tr>
	</table>

	<?php	
	
	do_bitui_access_statement($Corpus->access_statement);
}




// TODO this func is over 500 lines long. Ri-frakking-diculous. Split it up.
// ideas: put the different meta forms into different funcs.
function do_ui_managemeta()
{
	global $Config;
	global $Corpus;

	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Admin tools for managing text metadata</th>
		</tr>
		<tr>
			<td class="concordgrey" >
				<p class="spacer">&nbsp;</p>
				<p>
					The <em>text metadata table</em> is a database within CQPweb which contains information on the texts in this corpus.
					For every text, a number of <b>fields</b> of information are stored. The fields can be <em>classifications</em>,
					which contain handles for categories of texts (for use in the distribution and restricted query tools); 
					or <em>free text</em> fields, which can contain anything. 
					Every corpus must have a text metadata table, but it can be a <em>minimalist</em> table (lists the texts with no
					additional fields). 
				</p> 
				<p class="spacer">&nbsp;</p>	
			</td>
		</tr>
	</table>
	

	<?php 
	// TODO this untidily repeats the IF below.
	// TODO put the 2yes it is set up"! and the "no it isn't" into 2 different funcs
	// and call. one or the other from the main "manaqge text meta" func.
	if (text_metadata_table_exists($Corpus->name))
	{
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="4">Metadata status summary</th>
			</tr>
			
			<tr>
				<td class="concordgrey" align="center" colspan="4">
					<p class="spacer">&nbsp;</p>
					<p>
						The text metadata table <b>has been</b> created, and contains the fields shown below 
						(as specified at thge time it was set up).
					</p> 
					<p class="spacer">&nbsp;</p>
					<p>If you need delete and re-load the text metadata table, you should use the control at the bottom of this page.</p> 
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<th class="concordtable">Field handle</th>
				<th class="concordtable">Description</th>
				<th class="concordtable">Datatype</th>
				<th class="concordtable">Update?</th>
			</tr>
			
			<?php 
			
			foreach (get_all_text_metadata_info($Corpus->name) as $field)
				echo "\n\t\t\t<tr>"
 					, "\n\t\t\t\t", '<td class="concordgeneral">', $field->handle, '</td>'
 					, "\n\t\t\t\t", '<td class="concordgeneral">', escape_html($field->description), '</td>'
 					, "\n\t\t\t\t", '<td class="concordgeneral">', $Config->metadata_type_descriptions[$field->datatype], '</td>'
 					, "\n\t\t\t\t", '<td class="concordgeneral">', '[TODO]', '</td>'
 					, "\n\t\t\t</tr>"
 					;
 			// TODO make it possible to change the descriptioin -in the table above.
 			// TODO (less important) make it possible to change datatype. 
 			?>
		
		</table>
		
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="2" class="concordtable">Reset the metadata table for this corpus</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey" align="center">
					Are you sure you want to do this?
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<form id="clearMetadataAreYouReallySureForm" class="greyoutOnSubmit" action="metadata-act.php" method="get"></form>
					<input form="clearMetadataAreYouReallySureForm" type="hidden" name="mdAction" value="clearMetadataTable">
					<input form="clearMetadataAreYouReallySureForm" type="hidden" name="corpus" value="<?php echo $Corpus->name; ?>">
					<input form="clearMetadataAreYouReallySureForm" type="checkbox" name="clearMetadataAreYouReallySure" value="yesYesYes">
					Yes, I'm really sure and I know I can't undo it.
				</td>
				<td class="concordgeneral" align="center">
					<input form="clearMetadataAreYouReallySureForm" type="submit" value="Delete metadata table for this corpus">
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
				<th class="concordtable">Metadata status summary</th>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					<p class="spacer">&nbsp;</p>
					<p>The text metadata table <b>has not yet been</b> created.</p> 
					<p class="spacer">&nbsp;</p>
					<p>
						Please use the controls below to create the text metadata table - either by loading it from an external text file, 
						or by duplicating data from one or more speciifed corpus XML attrributes. 
					</p> 
					<p>You will not be able to search the corpus until you have set up the text metadata table.</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>

		
<!-- 		<table class="concordtable fullwidth"> -->
<!-- 			<tr> -->
<!-- 				<td class="concordgrey"> -->
<!-- 					&nbsp;<br> -->
<!-- 					The text metadata table for this corpus has not yet been set up. You must create it, -->
<!-- 					using the controls below, before you can search this corpus. -->
<!-- 					<br>&nbsp; -->
<!-- 				</td> -->
<!-- 			</tr> -->
<!-- 		</table> -->
		
		<?php
		
		/* first, test for the "alternate" form. */
		if (isset($_GET['createMetadataFromXml']) && '1' == $_GET['createMetadataFromXml'])
		{
			?>
			
			<form class="greyoutOnSubmit" action="metadata-act.php" method="post">
				<input type="hidden" name="mdAction" value="createMetadataFromXml">
				<input type="hidden" name="corpus" value="<?php echo $Corpus->name; ?>">
				
				<table class="concordtable fullwidth">
					<tr>
						<th class="concordtable" colspan="5" >Create text metadata table from corpus XML</th>
					</tr>
					<?php
					$possible_xml = get_all_xml_info($Corpus->name);
					/* remove the two we know cannot be used for this: */
					unset($possible_xml['text'], $possible_xml['text_id']);
					
					if (empty($possible_xml))
					{
						?>
						<tr>
							<td class="concordgrey" colspan="5" align="center">
								&nbsp;<br>
								No usable XML elements/attributes are available in this corpus.
								<br>&nbsp;
							</td>
						</tr>
						<?php
					}
					else
					{
						/* post form used here, because if there is a lot of XML, the URL may get too long for some servers. */
						?>
					
						<tr>
							<td class="concordgrey" colspan="5">
								&nbsp;<br>
								
								The following XML annotations are indexed in the corpus.
								Select the ones which you wish to use as text-metadata fields.
								
								<br>&nbsp;<br>
								
								<em>Note: you must only select annotations that occur <strong>at or above</strong>
								the level of &lt;text&gt; in the XML hierarchy of your corpus; doing otherwise may 
								cause a CQP error, and will in any case not give you the expected results.</em> 
								
								<br>&nbsp;<br>
								
								The descriptions of the XML elements/attributes can be altered from their original values for
								their use as metadata fields. However, the datatype cannot be changed. 
								
								<br>&nbsp;<br>
							</td>
						</tr>
						<tr>
							<td class="concordgrey" colspan="3" valign="middle">
								Auto-select all attributes of the &lt;text&gt; element?
							</td>
							<td class="concordgrey" colspan="2" align="center">
								<button type="button" onclick="$('[name^=&quot;createMetadataFromXmlUse_&quot;]').prop('checked', true);">Select text attributes!</button>
							</td>
						</tr>
						<tr>
							<th class="concordtable">Use?</th>
							<th class="concordtable">Field handle</th>
							<th class="concordtable">Description for this field</th>
							<th class="concordtable">Datatype of this field</th>
							<th class="concordtable">Which field is the primary classification?</th>
						</tr>
						<?php
						
						foreach($possible_xml as $x)
						{
							if (METADATA_TYPE_NONE == $x->datatype)
								continue;
							
							$x->description = escape_html(trim($x->description));
							if (empty($x->description))
								$x->description = $x->handle;
								
							echo "\n\n<tr>"
								, '<td class="concordgeneral" align="center">'
								, '<input name="createMetadataFromXmlUse_'
								, $x->handle
								, '" type="checkbox" value="1"> '
								, '</td>'
								, '<td class="concordgeneral">' , $x->handle , '</td>'
								;
							echo '<td class="concordgeneral" align="center">' 
								, '<input name="createMetadataFromXmlDescription_' 
								, $x->handle
								, '" type="text" value="' , $x->description, '"> '
								, '</td>'
								;
							echo '<td class="concordgeneral" align="center">'
								, $Config->metadata_type_descriptions[$x->datatype]
		//						, '<select name="fieldType_'
		//						, $x->handle
		//						, '"><option value="', METADATA_TYPE_CLASSIFICATION, '" selected>Classification</option>'
		//						, '<option value="', METADATA_TYPE_FREETEXT, '">Free text</option></select>'
								, '</td>'
								;
		// TODO what's going on with the above?
							if (METADATA_TYPE_CLASSIFICATION == $x->datatype)
								echo '<td class="concordgeneral" align="center">'
									, '<input type="radio" name="primaryClassification" value="'
									, $x->handle 
									/* nb this form, unlike t'other, has primaryClassification as a handle, not a row ix */
									, '"></td>'
									;
							else
								echo '<td class="concordgeneral" align="center">(not a classication)</td>';
							
							echo "</tr>\n\n\n";
						}
						?>
						
						<tr>
							<th class="concordtable" colspan="5">
								Do you want to automatically run frequency-list setup?
							</th>
						</tr>
						<tr>
							<td align="center" class="concordgeneral" colspan="5">
								<table>
									<tr>
										<td class="basicbox" align="left">
											<input type="radio" name="createMetadataRunFullSetupAfter" value="1">
											<strong>Yes please</strong>, run this automatically (ideal for relatively small corpora)
										</td>
									</tr>
									<tr>
										<td class="basicbox" align="left">
											<input type="radio" name="createMetadataRunFullSetupAfter" value="0"  checked>
											<strong>No thanks</strong>, I'll run this myself (safer for very large corpora)
										</td>
									</tr>
								</table>
							</td>
						</tr>
						<tr>
							<td align="center" class="concordgeneral" colspan="5">
								<input type="submit" value="Create metadata table from XML using the settings above">
							</td>
						</tr>
						<tr>
							<td align="center" class="concordgrey" colspan="5">
								&nbsp;<br>
								<a href="index.php?ui=manageMetadata">
									Click here to go back to the normal metadata setup form.</a>
								<br>&nbsp;
							</td>
						</tr>
					</table>
				</form>
				<?php
			}
			
			
			/* to avoid wrapping the whole of the rest of the function in an else */
			return;
			
		} /* end if (create metadata from XML) */
		
		
		/* OK, print the usual (non-XML) metadata setup page. */
		
//		$number_of_fields_in_form = ( isset($_GET['metadataFormFieldCount']) ? (int)$_GET['metadataFormFieldCount'] : 8);
		
		?>

		
		<!-- i want a form with more slots!  - no longer needed since we have an embiggener

		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="3">I need more fields!</th>
			</tr>
			<form class="greyoutOnSubmit" action="index.php" method="get">
				<input type="hidden" name="ui" value="manageMetadata">
					<tr>
					<td class="concordgeneral">
						Do you need more metadata fields? Use this control:
					</td>
					<td class="concordgeneral">
						I want a metadata form with 
						<select name="metadataFormFieldCount">
							<option>9</option>
							<option>10</option>
							<option>11</option>
							<option>12</option>
							<option>14</option>
							<option>16</option>
							<option>20</option>
							<option>25</option>
							<option>30</option>
							<option>40</option>
						</select>
						slots!
					</td>
					<td class="concordgeneral">
						<input type="submit" value="Create bigger form!">
					</td>
				</td>
			</form>
		</table>
		-->

		<form class="greyoutOnSubmit" action="metadata-act.php" method="get">
			<input type="hidden" name="mdAction" value="createMetadataFromFile"> 
			<input type="hidden" name="fieldCount" id="fieldCount" value="5">
			<input type="hidden" name="corpus" value="<?php echo $Corpus->name; ?>">

			<table class="concordtable fullwidth">
				<tr>
					<th class="concordtable" colspan="5">Choose the file containing the metadata</th>
				</tr>

				<tr>
					<th class="concordtable">Use?</th>
					<th colspan="2" class="concordtable">Filename</th>
					<th class="concordtable">Size (K)</th>
					<th class="concordtable">Date modified</th>
				</tr>

				<?php
				echo print_file_selector();
				?>

				<tr>
					<th class="concordtable" colspan="5">Describe the contents of the file you have selected</th>
				</tr>

				<tr>
					<td class="concordgeneral" colspan="5">
						<table class="fullwidth">
							<tr>
								<td class="basicbox" width="50%">
									Choose template for text metadata structure
									<br>
									<i>(or select "Custom metadata structure" and specify annotations in the boxes below)</i>
								</td>
								<td class="basicbox" width="50%">
									<select name="useMetadataTemplate">
										<option value="~~customMetadata" selected>Custom metadata structure</option>
										<?php
										foreach(list_metadata_templates() as $t)
											echo "\n\t\t\t\t\t\t\t\t\t"
												, '<option value="'
												, $t->id
												, '">'
												, escape_html($t->description)
												, ' (containing ', count($t->fields), ' defined fields)' 
												, "</option>\n"
												;
										?>
									</select>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				
				<tr>
					<td class="concordgrey" colspan="5">
						Note: you should not specify the text identifier (text_id), which must be the first field. 
						This is inserted automatically.
						
						<br>&nbsp;<br>
						
						<em>Classification</em> fields contain one of a set number of handles indicating text categories. 
						<em>Free-text metadata</em> fields can contain anything, and don't indicate categories of texts.
					</td>
				</tr>

				<?php
				
				echo print_embiggenable_metadata_form(5);

				?>
			
				<tr>
					<th class="concordtable" colspan="5">
						Do you want to automatically run frequency-list setup?
					</th>
				</tr>
				<tr>
					<td align="center" class="concordgeneral" colspan="5">
						<table>
							<tr>
								<td class="basicbox" align="left">
									<input type="radio" name="createMetadataRunFullSetupAfter" value="1">
									<strong>Yes please</strong>, run this automatically (ideal for relatively small corpora)
								</td>
							</tr>
							<tr>
								<td class="basicbox" align="left">
									<input type="radio" name="createMetadataRunFullSetupAfter" value="0" checked>
									<strong>No thanks</strong>, I&rsquo;ll run this myself (safer for very large corpora)
								</td>
							</tr>
						</table>
					</td>
				</tr>
				
				<tr>
					<td align="center" class="concordgeneral" colspan="5">
						<input type="submit" value="Install metadata table using the settings above">
					</td>
				</tr>
				
			</table>

		</form>

		<!-- minimalist metadata -->

		<form class="greyoutOnSubmit" action="metadata-act.php" method="get">
			<input type="hidden" name="mdAction" value="createTextMetadataMinimalist">
			<table class="concordtable fullwidth">
				<tr>
					<th class="concordtable">My corpus has no metadata!</th>
				</tr>
				<tr>
					<td class="concordgeneral" align="center">
						&nbsp;<br>

						Use this tool to automatically generate a &ldquo;dummy&rdquo; metadata table,
						containing only text IDs, for a corpus with no other metadata.

						<br>&nbsp;<br>

						Do you want to automatically run frequency-list setup for your corpus?

						<br>&nbsp;<br>

						<table>
							<tr>
								<td class="basicbox" align="left">
									<input type="radio" name="createMetadataRunFullSetupAfter" value="1">
									<strong>Yes please</strong>, run this automatically (ideal for relatively small corpora)
								</td>
							</tr>
							<tr>
								<td class="basicbox" align="left">
									<input type="radio" name="createMetadataRunFullSetupAfter" value="0"  checked>
									<strong>No thanks</strong>, I'll run this myself (safer for very large corpora)
								</td>
							</tr>
						</table>
						<input type="submit" value="Create minimalist metadata table">
						
						<br>&nbsp;
					</td>
				</tr>
			</table>
		</form>	
		
		
		
		<!-- pre-encoded metadata:link to alt page -->
		
		<table class="concordtable fullwidth">
			
			<tr>
				<th class="concordtable" >My metadata is embedded in the XML of my corpus!</th>
			</tr>
			
			<?php
			/* check for less-than 2, because text_id always exists. */
			if (2 > count(list_xml_with_values($Corpus->name)))
			{
				?>
				<tr>
					<td class="concordgrey" colspan="5" align="center">
						&nbsp;<br>
						No XML annotations found for this corpus.
						<br>&nbsp;
					</td>
				</tr>
				<?php
			}
			else
			{
				?>
					<tr>
						<td class="concordgrey" align="center">
							&nbsp;<br>
							
							<a href="index.php?ui=manageMetadata&createMetadataFromXml=1">
								Click here to install metadata from within-corpus XML annotation.
							</a>
							
							<br>&nbsp;<br>
						</td>
					</tr>

				<?php
			}
			?>
			
		</table>

		<?php
	
	}  /* endif text metadata table does not already exist */

}


function do_ui_managefreqlists()
{
	global $Corpus;
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">Corpus frequency list controls</th>
		</tr>
		<tr>
			<th class="concordtable">Frequency-list data</th>
			<th class="concordtable">Status</th>
			<th class="concordtable"></th>
			<th class="concordtable">Actions</th>
		</tr>
		
		<?php
		
		$ok = true;
		
		if (!text_metadata_table_exists($Corpus->name))
		{
			$ok = false;
			$message = 'The text metadata table <strong>does not yet exist</strong> &ndash; '
				. 'you must create it (on the <strong>Manage metadata</strong> page) before you can setup text begin/end positions.';
			$make_a_button = false;
			$button_label = 'Update CWB text-position records';
		}
		else
		{
			$make_a_button = true;
			
			if (0 < get_sql_value("select count(*) from text_metadata_for_{$Corpus->name} where words > 0"))
			{
				$message = 'The text metadata table <strong>has already been populated</strong> with begin/end offset positions. '
					. 'Use the button to refresh this data.';
				$button_label = 'Update CWB text-position records';
			}
			else
			{
				$ok = false;
				$message = 'The text metadata table <strong>has not yet been populated</strong> with begin/end offset positions. '
					. 'Use the button to generate this data.';
				$button_label = 'Generate CWB text-position records';
			}
		}
		
		?>
		
		<tr>
			<td class="concordgrey" valign="middle">Text begin/end positions</td>
			<td class="<?php echo $ok ? 'concordgeneral' : 'concorderror'; ?>" align="center" valign="middle">
				<strong><?php echo $ok? 'OK!' : 'Unready'; ?></strong>
			</td>
			<td class="concordgeneral" valign="middle">
				<?php echo $message, "\n"; ?>
			</td>
			<td class="concordgeneral" align="center">
				<p class="spacer">&nbsp;</p>
				
				<?php 
				if ($make_a_button)
				{
					?>
					
					<form class="greyoutOnSubmit" action="execute.php" method="get">
						<input type="hidden" name="function"      value="populate_corpus_cqp_positions">
						<input type="hidden" name="args"          value="<?php echo $Corpus->name; ?>">
						<input type="hidden" name="locationAfter" value="index.php?ui=manageFreqLists">
						<p><input type="submit" value="<?php echo $button_label; ?>"></p>
					</form>
					
					<?php
				}
				?>
				
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		<?php
		
		
		/* ==================================================== */
		
		
		if (0 == count(list_text_metadata_classifications($Corpus->name)))
		{
			?>
			
			<tr>
				<td class="concordgrey" valign="middle">Text category wordcounts</td>
				<td class="concordgeneral" align="center" valign="middle">&nbsp;</td>
				<td class="concordgeneral" valign="middle">
					There are no text classification systems in this corpus; wordcounts are therefore not relevant.
				</td>
				<td class="concordgeneral" align="center" valign="middle">
					<p class="spacer">&nbsp;</p>
					<p>&nbsp;</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
			<?php
		}
		else
		{
			$message = 'The word count tables for the different text classification categories in this corpus ';
			
			$sql = "select handle from text_metadata_values where corpus = '{$Corpus->name}' and category_num_words > 0 limit 1";
			if ( 0 < mysqli_num_rows(do_sql_query($sql)) )
			{
				$ok = true;
				$button_label = 'Update word and file counts';
				$message .= '<strong>have already been populated</strong>. Use the button to regenerate them.';
			}
			else
			{
				$ok = false;
				$button_label = 'Populate word and file counts';
				$message .= '<strong>have not yet been populated</strong>. Use the button to populate them.';
			}
			
			?>
			
			<tr>
				<td class="concordgrey" valign="middle">Text category wordcounts</td>
				<td class="<?php echo $ok ? 'concordgeneral' : 'concorderror'; ?>" align="center" valign="middle">
					<strong><?php echo $ok? 'OK!' : 'Unready'; ?></strong>
				</td>
				<td class="concordgeneral" valign="middle">
					<?php echo $message, "\n"; ?>
				</td>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<form class="greyoutOnSubmit" action="execute.php" method="get">
						<input type="hidden" name="function"      value="metadata_calculate_category_sizes">
						<input type="hidden" name="args"          value="<?php echo $Corpus->name; ?>">
						<input type="hidden" name="locationAfter" value="index.php?ui=manageFreqLists">
						<p><input type="submit" value="<?php echo $button_label; ?>"></p>
					</form>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
			<?php
		}
		
		
		/* ==================================================== */
		
		
		$message = "The CWB text-by-text frequency index is used to generate subcorpus frequency lists (important for keywords, collocations etc.)<br>\n\t\t\t\t";

		if (2 > $Corpus->size_texts)
		{
			$message .= "This corpus only contains one text. Using a text-by-text frequency index is therefore <strong>neither necessary nor desirable.</strong>";
			?>
			
			<tr>
				<td class="concordgrey" valign="middle">Text-by-text frequency index</td>
				<td class="concordgeneral" align="center" valign="middle">&nbsp;</td>
				<td class="concordgeneral" valign="middle">
					<?php echo $message, "\n"; ?>
				</td>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p>&nbsp;</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
			<?php
		
		}
		else 
		{
			if (check_cwb_freq_index($Corpus->name))
			{
				$ok = true;
				$message .= 'The text-by-text index for this corpus <strong>has already been created</strong>. Use the button to delete and recreate it.';
				$button_label = 'Recreate CWB frequency table';
			}
			else
			{
				$ok = false;
				$message .= 'The text-by-text index for this corpus <strong>has not yet been created</strong>. Use the button to generate it.';
				$button_label = 'Create CWB frequency table';
			}
			?>
			
			<tr>
				<td class="concordgrey" valign="middle">Text-by-text frequency lists</td>
				<td class="<?php echo $ok ? 'concordgeneral' : 'concorderror'; ?>" align="center" valign="middle">
					<strong><?php echo $ok? 'OK!' : 'Unready'; ?></strong>
				</td>
				<td class="concordgeneral" valign="middle">
					<?php echo $message, "\n"; ?>
				</td>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<form class="greyoutAreYouSure" data-areYouSureQ="Are you sure you want to do this? It can take some time!" action="execute.php" method="get">
						<input type="hidden" name="function"      value="make_cwb_freq_index">
						<input type="hidden" name="args"          value="<?php echo $Corpus->name; ?>">
						<input type="hidden" name="locationAfter" value="index.php?ui=manageFreqLists">
						<p><input type="submit" value="<?php echo $button_label; ?>"></p>
					</form>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
			<?php
		}
		
		
		/* ==================================================== */
		
		
		$message = 'Word and annotation frequency tables for this corpus ';
		
		if (0 < mysqli_num_rows(do_sql_query("show tables like 'freq_corpus_{$Corpus->name}_word'")))
		{
			$ok = true;
			$message .= '<strong>have already been created</strong>. Use the button to delete and recreate them.';
			$button_label = 'Recreate frequency tables';
		}
		else
		{
			$ok = false;
			$message .= '<strong>have not yet been created</strong>. Use the button to generate them.';
			$button_label = 'Create frequency tables';
		}
		?>
		
		<tr>
			<td class="concordgrey" valign="middle">Frequency tables</td>
			<td class="<?php echo $ok ? 'concordgeneral' : 'concorderror'; ?>" align="center" valign="middle">
				<strong><?php echo $ok? 'OK!' : 'Unready'; ?></strong>
			</td>
			<td class="concordgeneral" valign="middle">
				<?php echo $message, "\n"; ?>
			</td>
			<td class="concordgeneral" align="center">
				<p class="spacer">&nbsp;</p>
				<form class="greyoutAreYouSure" data-areYouSureQ="Really recreate frequency tables? This can take a long time!" action="execute.php" method="get">
					<input type="hidden" name="function"      value="corpus_make_freqtables">
					<input type="hidden" name="args"          value="<?php echo $Corpus->name; ?>">
					<input type="hidden" name="locationAfter" value="index.php?ui=manageFreqLists">
					<p><input type="submit" value="<?php echo $button_label; ?>"></p>
				</form>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	
	</table>
	
	<?php
}


function do_ui_managetextcats()
{
	global $Corpus;
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Insert or update text category descriptions</th>
		</tr>
		
		<?php
		
		$classification_list = list_text_metadata_classifications($Corpus->name);
		if (empty($classification_list))
			echo '<tr><td class="concordgrey" align="center">&nbsp;<br>No text classification schemes exist for this corpus.<br>&nbsp;</td></tr>';
		
		foreach ($classification_list as $scheme_handle => $scheme_desc)
		{
			?>
			
			<tr>
				<td class="concordgrey" align="center">
					Categories in classification scheme <em><?php echo $scheme_handle;?></em>
					(&ldquo;<?php echo escape_html($scheme_desc); ?>&rdquo;)
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<form class="greyoutOnSubmit" action="metadata-act.php" method="get">
						<input type="hidden" name="mdAction" value="updateMetadataCategoryDescriptions">
						<table>
							<tr>
								<td class="basicbox" align="center"><strong>Scheme = Category</strong></td>
								<td class="basicbox" align="center"><strong>Category description</strong></td>
							</tr>
							<?php
							foreach (list_text_metadata_category_descriptions($Corpus->name, $scheme_handle) as $handle => $description)
								echo "\n", '<tr><td class="basicbox">', "{$scheme_handle} = $handle", '</td>'
									, '<td class="basicbox"><input type="text" name="'
 									, "desc-$scheme_handle-$handle"
									, '" value="', escape_html($description), '"></td></tr>'
 									, "\n"
									;
							?>
							<tr>
								<td class="basicbox" align="center" colspan="2">
									<input type="submit" value="Update category descriptions">
								</td>
							</tr>
						</table>
					</form>
				</td>
			</tr>
			
			<?php
		}
		?>
	
	</table>
	
	<?php
}



function do_ui_managexml()
{
	global $Config;
	global $Corpus;
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Manage corpus XML</th>
		</tr>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">Available XML elements/attributes (s-attributes)</th>
		</tr>
		<tr>
			<th class="concordtable">Handle</th>
			<th class="concordtable">Dependent of...</th>
			<th class="concordtable">Description</th>
			<th class="concordtable" colspan="2">Datatype</th>
		</tr>
		
		<?php
		
		$classifications = array();
		$idlinks = array();
		
		foreach (get_all_xml_info($Corpus->name) as $x)
		{
			if (METADATA_TYPE_CLASSIFICATION == $x->datatype)
				$classifications[] = $x;
			if (METADATA_TYPE_IDLINK == $x->datatype)
				$idlinks[] = $x;
			$id = "desc-{$x->corpus}-{$x->handle}";
			$x->description = escape_html($x->description); /* because it is always going to be rendered. */
			
			$descform = <<<END_OF_FORM

				&nbsp;
				<form class="greyoutOnSubmit" action="execute.php" method="get"
					onSubmit="
						/* add corpus and handle arguments to the description (after hiding it from the user) */
						var t = $('#$id').css('visibility', 'hidden').val(); 
 						$('#$id').val('$x->corpus#$x->handle#' + t); 
 						return true;
						"
				>
					<input type="hidden" name="function"      value="update_xml_description">
					<input type="hidden" name="locationAfter" value="index.php?ui=manageXml">
					<input type="text"   name="args" id="$id" maxlength="255" value="$x->description">
					<input type="submit" value="Update!">
				</form>

END_OF_FORM;
			
			$typeopts = '';
			foreach($Config->metadata_type_descriptions as $const => $desc)
				if ($x->datatype != $const)
					if (METADATA_TYPE_NONE != $const)
						$typeopts .= '<option value="' . $const . '">' . $desc . '</option>';
					
			$typeform = <<<END_OF_FORM

				&nbsp;
				<form class="greyoutAreYouSure" action="metadata-act.php" method="get">
					<input type="hidden" name="mdAction" value="xmlChangeDatatype">
					<input type="hidden" name="handle"   value="$x->handle">
					<select name="newDatatype">
						<option value="~~NULL" selected>Change datatype to...</option>
						$typeopts
					</select>
					<input type="submit" value="Change">
				</form>

END_OF_FORM;
			
			
			echo "\n\t\t<tr>"
				, "\n\t\t\t<td class=\"concordgeneral\"><strong>{$x->handle}</strong></td>"
				, "\n\t\t\t<td class=\"concordgeneral\">", ($x->handle == $x->att_family ? '<i>None</i>' : $x->att_family), "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">\n$descform</td>"
				, "\n\t\t\t<td ", (METADATA_TYPE_NONE == $x->datatype ? 'colspan="2" ' : ''),  "class=\"concordgeneral\">"
					, "{$Config->metadata_type_descriptions[$x->datatype]}" 
					, (METADATA_TYPE_IDLINK == $x->datatype ? (' (linked table '. (xml_idlink_table_exists($Corpus->name, $x->handle) ? 'exists)' : 'does not exist)')) : '') 
				, '</td>'
				, (METADATA_TYPE_NONE != $x->datatype ? "\n\t\t\t<td class=\"concordgeneral\">$typeform</td>": '') 
				, "\n\t\t</tr>\n"
				;
		}
		?>
		
		<tr>
			<td colspan="5" class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				<p align="center">Important note: if you change the datatype of a Classification, any customised category descriptions will be lost.</p>
				<p class="spacer">&nbsp;</p>
				<p align="center">Important note: similarly, if you change the datatype of an ID link, the entire linked data-table will be lost.</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">XML Region Categories (for Classification-type attributes)</th>
		</tr>
		
		<?php
		
		if (empty($classifications))
			echo "\n\t\t\t<tr>\n\t\t\t\t"
				, '<td colspan="4" class="concordgrey">No classification-type XML attributes exist.</td>'
				, "\n\t\t\t<tr>\n"
				;
		
		foreach ($classifications as $x)
		{
			echo "\n\t\t\t<tr>\n\t\t\t\t"
				, '<td colspan="2" class="concordgrey">Categories of classification attribute <b>', $x->handle
				, '</b> (<em>', $x->description, '</em>), classifying <b>', $x->att_family
				, '</b></td>'
				;
			$cats = xml_category_listdescs($Corpus->name, $x->handle); 

			if (empty($cats))
				echo "\n\t\t\t<tr>\n\t\t\t\t"
					, '<td colspan="2" class="concordgeneral">'
						, '<p>The categories have not been set up yet. Click below to generate them.</p>'
						, '<form class="greyoutOnSubmit" action="metadata-act.php" method="get" align="center">'
							, '<input type="submit" value="Generate categories for &rdquo;', $x->handle, '&ldquo;">'
							, '<input type="hidden" name="mdAction" value="runXmlCategorySetup">'
							, '<input type="hidden" name="xmlClassification" value="' , $x->handle, '">'
						, '</form>'
					, '</td>'
					;
				/* the above button will be needed for old corpora (pre 3.2). 
				 * In later versions, it should always be run automatically where needed. 
				 * In 3.3 it will be REMOVED. 
				 */
			else
			{
				?>
				
				<tr>
					<td class="concordgeneral" align="center">
						<form class="greyoutOnSubmit" action="metadata-act.php" method="get">
							<table>
								<tr>
									<td class="basicbox" align="center"><strong>Category handle</strong></td>
									<td class="basicbox" align="center"><strong>Category description</strong></td>
								</tr>
								
								<?php
								foreach($cats as $handle=>$desc)
								{
									?>
									
									<tr>
										<td class="basicbox">
											<?php echo $x->handle, ' = ', $handle; ?>
										</td>
										<td class="basicbox">
											<input type="text" name="desc-<?php echo $x->handle, '-', $handle; ?>" value="<?php echo $desc; ?>">
										</td>
									</tr>
									<?php
								}
								?>
								
								<tr>
									<td class="basicbox" align="center" colspan="2">
										<input type="submit" value="Update category descriptions">
									</td>
								</tr>
							</table>
							<input type="hidden" name="mdAction" value="updateXmlCategoryDescriptions">
						</form>
					</td>
				</tr>
				
				<?php
			}
		}
		?>
	
	</table>
	
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">ID link metadata tables (for ID link-type attributes)</th>
		</tr>
		
		<?php
		
		if (empty($idlinks))
			echo "\n\t\t\t<tr>\n\t\t\t\t"
				, '<td colspan="" class="concordgrey">No ID link-type XML attributes exist.</td>'
				, "\n\t\t\t<tr>\n"
				;
		
		foreach ($idlinks as $x)
		{
			$n_embiggenable_rows = 5;
			
			echo "\n\t\t\t<tr>\n\t\t\t\t"
				, '<td colspan="2" class="concordgrey"><p>IDlink attribute <b>', $x->handle
				, '</b> (<em>', $x->description, '</em>), providing ID codes for XML element <b>', $x->att_family, '</b></p>'
 				, '<p class="spacer">&nbsp;</p>'
				;
			/* note: the above leaves a cell unfinished, so the next if/else starts by finishing it! */


			if (! xml_idlink_table_exists($Corpus->name, $x->handle))
			{
				echo '<p>The IDlink metadata table for this attribute does not yet exist.</p><p class="spacer">&nbsp;</p>'
					, "</td></tr>\n"
					;
				?>


				<tr>
					<th class="concordtable" colspan="5">
						Choose the file containing the metadata
						<form  id="createXmlIdlinkTableForm:<?php echo $x->handle; ?>" class="greyoutOnSubmit" action="metadata-act.php" method="get"></form>
						<input form="createXmlIdlinkTableForm:<?php echo $x->handle; ?>" type="hidden" name="mdAction" value="createXmlIdlinkTable">
						<input form="createXmlIdlinkTableForm:<?php echo $x->handle; ?>" type="hidden" name="fieldCount" id="fieldCount" value="<?php echo $n_embiggenable_rows;?>">
						<input form="createXmlIdlinkTableForm:<?php echo $x->handle; ?>" type="hidden" name="corpus" value="<?php echo $Corpus->name; ?>">
						<input form="createXmlIdlinkTableForm:<?php echo $x->handle; ?>" type="hidden" name="xmlAtt" value="<?php echo $x->handle; ?>">
					</th>
				</tr>

				<tr>
					<th class="concordtable">Use?</th>
					<th colspan="2" class="concordtable">Filename</th>
					<th class="concordtable">Size (K)</th>
					<th class="concordtable">Date modified</th>
				</tr>

				<?php
//TODO col headers into the print file selector func??
				echo print_file_selector('dataFile', 'createXmlIdlinkTableForm:'.$x->handle);
				?>

				<tr>
					<th class="concordtable" colspan="5">Describe the contents of the file you have selected</th>
				</tr>

				<tr>
					<td class="concordgeneral" colspan="5">
						<table class="fullwidth">
							<tr>
								<td class="basicbox" width="50%">
									Choose template for idlink metadata structure
									<br>
									<i>(or select "Custom metadata structure" and specify annotations in the boxes below)</i>
								</td>
								<td class="basicbox" width="50%">
									<select form="createXmlIdlinkTableForm:<?php echo $x->handle; ?>" name="useMetadataTemplate">
										<option value="~~customMetadata" selected>Custom metadata structure</option>
										<?php
										foreach(list_metadata_templates() as $t)
											echo "\n\t\t\t\t\t\t\t\t\t"
												, '<option value="'
												, $t->id
												, '">'
												, escape_html($t->description)
												, ' (containing ', count($t->fields), ' defined fields)' 
												, "</option>\n"
												;
										?>
									</select>
								</td>
							</tr>
						</table>
					</td>
				</tr>
				
				<tr>
					<td class="concordgrey" colspan="5">
						Note: you should not specify the identifier (matches the contents of <b><?php echo $x->handle; ?></b>), 
						which must be the first field. 
						This is inserted automatically.
						
						<br>&nbsp;<br>
						
						<em>Classification</em> fields contain one of a set number of handles indicating text categories. 
						<em>Free-text metadata</em> fields can contain anything, and don't indicate categories of texts.
					</td>
				</tr>

				<?php
				
// TODO might some of the above be put-into-funcs-able?
				
				echo print_embiggenable_metadata_form($n_embiggenable_rows, false, 'createXmlIdlinkTableForm'.$x->handle); 
				
				?>
				<tr>
					<td align="center" class="concordgeneral" colspan="5">
						<input form="createXmlIdlinkTableForm:<?php echo $x->handle; ?>" type="submit" value="Install ID-link data table using the settings above">
					</td>
				</tr>

				<?php
			}
			else
			{
				echo '<p>You can use form below to manipulate the interface-descriptions of its classification schemes.</p>'
					, "<p class='spacer'>&nbsp;</p></td></tr>\n"
					;
				$classification_list = get_all_idlink_field_info($Corpus->name, $x->handle);
				foreach($classification_list as $k => $v)
					if (METADATA_TYPE_CLASSIFICATION != $v->datatype)
						unset($classification_list[$k]);
				?>

				<tr>
					<th class="concordtable">Insert or update category descriptions for this ID link</th>
				</tr>

				<?php
				if (empty($classification_list))
					echo '<tr><td class="concordgrey" align="center">&nbsp;<br>'
						, 'This IDlink table has no classification-type columns.'
						, "<br>&nbsp;</td></tr>\n"
						;
		
				foreach ($classification_list as $scheme)
				{
					?>
					
					<tr>
						<td class="concordgrey" align="center">
							Categories in classification scheme <em><?php echo $scheme->handle;?></em>
							(&ldquo;<?php echo escape_html($scheme->description); ?>&rdquo;)
						</td>
					</tr>
					<tr>
						<td class="concordgeneral" align="center">
							<form class="greyoutOnSubmit" action="metadata-act.php" method="get">
								<input type="hidden" name="mdAction" value="updateXmlIdlinkCategoryDescriptions">
								<table>
									<tr>
										<td class="basicbox" align="center"><strong>Scheme = Category</strong></td>
										<td class="basicbox" align="center"><strong>Category description</strong></td>
									</tr>
									<?php
										foreach (idlink_category_listdescs($Corpus->name, $x->handle, $scheme->handle) as $handle => $description)
											echo "\n<tr><td class=\"basicbox\">{$scheme->handle} = $handle</td>"
												, '<td class="basicbox">'
 												, "<input type=\"text\" name=\"desc-{$x->handle}-{$scheme->handle}-$handle\" value=\"$description\">"
												, "</td></tr>\n"
												;
										?>
									<tr>
										<td class="basicbox" align="center" colspan="2">
											<input type="submit" value="Update category descriptions">
										</td>
									</tr>
								</table>
							</form>
						</td>
					</tr>
					
					<?php
				}
			}
		}
		?>

	</table>

	<?php

}



function do_ui_manageannotation()
{
	global $Corpus;

	$annotation_list = list_corpus_annotations_html($Corpus->name);

	/* set variables */

	$select_for_primary = '<select name="setPrimaryAnnotation">';
	$selector = ($Corpus->primary_annotation === NULL ? 'selected' : '');
	$select_for_primary .= '<option value="~~UNSET"' . $selector . '>Not in use in this corpus</option>';
	foreach ($annotation_list as $handle=>$desc)
	{
		$selector = ($Corpus->primary_annotation === $handle ? 'selected' : '');
		$select_for_primary .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_primary .= "</select>\n";

	$select_for_secondary = '<select name="setSecondaryAnnotation">';
	$selector = ($Corpus->secondary_annotation === NULL ? 'selected' : '');
	$select_for_secondary .= '<option value="~~UNSET"' . $selector . '>Not in use in this corpus</option>';
	foreach ($annotation_list as $handle=>$desc)
	{
		$selector = ($Corpus->secondary_annotation === $handle ? 'selected' : '');
		$select_for_secondary .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_secondary .= "</select>\n";

	$select_for_tertiary = '<select name="setTertiaryAnnotation">';
	$selector = ($Corpus->tertiary_annotation === NULL ? 'selected' : '');
	$select_for_tertiary .= '<option value="~~UNSET"' . $selector . '>Not in use in this corpus</option>';
	foreach ($annotation_list as $handle=>$desc)
	{
		$selector = ($Corpus->tertiary_annotation === $handle ? 'selected' : '');
		$select_for_tertiary .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_tertiary .= "</select>\n";

	$select_for_combo = '<select name="setComboAnnotation">';
	$selector = ($Corpus->combo_annotation === NULL ? 'selected' : '');
	$select_for_combo .= '<option value="~~UNSET"' . $selector . '>Not in use in this corpus</option>';
	foreach ($annotation_list as $handle=>$desc)
	{
		$selector = ($Corpus->combo_annotation === $handle ? 'selected' : '');
		$select_for_combo .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_combo .= "</select>\n";


	/* and the mapping table */

	$mapping_table_list = get_list_of_tertiary_mapping_tables();
	$select_for_maptable = '<select name="setMaptable">';
	$selector = ($Corpus->tertiary_annotation_tablehandle === NULL ? 'selected' : '');
	$select_for_maptable .= '<option value="~~UNSET"' . $selector . '>Not in use in this corpus</option>';
	foreach ($mapping_table_list as $handle=>$desc)
	{
		$selector = ($Corpus->tertiary_annotation_tablehandle === $handle ? 'selected' : '');
		$select_for_maptable .= "<option value=\"$handle\" $selector>$desc</option>";
	}
	$select_for_maptable .= "</select>\n";


	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Manage annotation
			</th>
		</tr>
	</table>
	<form class="greyoutOnSubmit" action="corpus-act.php" method="get">
		<input type="hidden" name="caAction" value="updateCeqlBinding">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="2" class="concordtable">
					CEQL (simple query) syntax bindings for token-level annotation in <em><?php echo $Corpus->name; ?></em>
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					<b>Primary annotation</b>
					&ndash; used for tags given after the underscore character (typically POS)
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_primary, "\n";?>
				</td>
			</tr>
			<tr>
				<td class="concordgrey">
					<b>Secondary annotation</b>
					&ndash; used for searches like <em>{...}</em> (typically lemma)
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_secondary, "\n";?>
				</td>
			</tr>
			<tr>
				<td class="concordgrey">
					<b>Tertiary annotation</b>
					&ndash; used for searches like <em>_{...}</em> (typically simplified POS tag)	
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_tertiary, "\n";?>
				</td>
			</tr>
			<tr>
				<td class="concordgrey">
					<b>Tertiary annotation mapping table</b>
					&ndash; handle for the list of aliases used in the tertiary annotation
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_maptable, "\n";?>
				</td>
			</tr>
			<tr>
				<td class="concordgrey">
					<b>Combination annotation</b>
					&ndash; typically lemma_simpletag, used for searches in the form <em>{.../...}</em>
				</td>
				<td class="concordgeneral">
					<?php echo $select_for_combo, "\n";?>
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgeneral" align="center">
					&nbsp;<br>
					<input type="submit" value="Update CEQL bindings">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>

	<table class="concordtable fullwidth">
		<tr>
			<th colspan="5" class="concordtable">
				Annotation metadata
			</th>
		</tr>
		<tr>
			<th class="concordtable">Handle</th>
			<th class="concordtable">Description</th>
			<th class="concordtable">Tagset name</th>
			<th class="concordtable">External URL</th>
			<th class="concordtable">Update?</th>
		</tr>

		<?php
 		$annotation_info = get_all_annotation_info($Corpus->name);

 		if (1 > count($annotation_info))
			echo '<tr><td colspan="5" class="concordgrey" align="center">'
 				, '<p class="spacer">&nbsp;</p>&nbsp;<p>This corpus has no annotation.</p><p class="spacer">&nbsp;</p></td></tr>'
 				;
 		
		foreach($annotation_info  as $tag)
		{
			?>

			<tr>
				<td class="concordgrey">
					<strong><?php echo $tag->handle; ?></strong>
					<form id="updateAnnotationInfoForm:<?php echo $tag->handle; ?>" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
					<input form="updateAnnotationInfoForm:<?php echo $tag->handle; ?>" type="hidden" name="caAction" value="updateAnnotationInfo">
					<input form="updateAnnotationInfoForm:<?php echo $tag->handle; ?>" type="hidden" name="annotationHandle" value="<?php echo $tag->handle; ?>">
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateAnnotationInfoForm:<?php echo $tag->handle; ?>" name="annotationDescription" maxlength="255" type="text" value="<?php echo escape_html($tag->description); ?>">
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateAnnotationInfoForm:<?php echo $tag->handle; ?>" name="annotationTagset" maxlength="255" type="text" value="<?php echo escape_html($tag->tagset); ?>">
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateAnnotationInfoForm:<?php echo $tag->handle; ?>" name="annotationURL" maxlength="255" type="url" value="<?php echo escape_html($tag->external_url); ?>">
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateAnnotationInfoForm:<?php echo $tag->handle; ?>" type="submit" value="Go!">
				</td>
			</tr>

			<?php
		}
		?>

		<tr>
			<td colspan="5" class="concordgeneral">&nbsp;<br>&nbsp;</td>
		</tr> 
	</table>

	<?php
}




function do_ui_managealignment()
{
	global $Corpus;
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Manage parallel-corpus alignment
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				Parallel corpora can be linked with alignments to other corpora on the CQPweb server.
				Add an alignment using command-line CWB methods, then use the control below to register the
				alignment with CQPweb.
			</td>
		</tr>
	</table>
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Existing alignments
			</th>
		<tr>
			<th class="concordtable">
				Target corpus
			</th>
			<th class="concordtable">
				Link
			</th>
		</tr>
		

		<?php 
		
		$alx = list_corpus_alignments($Corpus->name);

		if (empty($alx))
		{
			?>
			
			<tr>
				<td colspan="2" class="concordgrey" align="center">
					<p class="spacer">&nbsp;</p>
					<p>This corpus has no registered alignments.</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
			<?php 
		}
		else
			foreach($alx as $target => $desc)
				echo "\n\t\t<tr><td class=\"concordgeneral\"><em>"
 					, escape_html($desc) , '</em> [', $target , '] '
 					, '</td><td class="concordgeneral" align="center">'
	 				, "<a class=\"menuItem\" href=\"../$target/index.php?ui=manageAlignment\">[Switch to target corpus alignments]</a>"
 					, "</td>\n"
 					;
		?>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Scan for new alignments
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<p class="spacer">&nbsp;</p>
				<form class="greyoutOnSubmit" action="execute.php" method="get">
					&nbsp;<br>
					<input type="submit" value="Click here to scan the registry for newly-added alignments">
					<br>
					<input type="hidden" name="function" value="scan_for_corpus_alignments">
					<input type="hidden" name="args" value="<?php echo $Corpus->name; ?>">
					<input type="hidden" name="locationAfter" value="index.php?ui=manageAlignment">
					<br>&nbsp;
				</form>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr> 
	</table>

	<?php
}




function do_ui_visualisation()
{
	global $Corpus;
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th  colspan="2" class="concordtable">
				Query result visualisation (concordance and extended-context displays)
			</th>
		</tr>
	</table>
	
	<?php

	/* 
	 * FIRST SECTION --- GLOSS VISUALISATION 
	 */

	
	/* set up option strings for first form  */

	$annotations = list_corpus_annotations($Corpus->name);
	
	$opts = array(	'neither'=>'Don\'t show anywhere', 
					'concord'=>'Concordance only', 
					'context'=>'Context only', 
					'both'=>'Both concordance and context'
					);
	if ($Corpus->visualise_gloss_in_concordance)
	{
		if ($Corpus->visualise_gloss_in_context)
			$show_gloss_curr_opt = 'both';
		else
			$show_gloss_curr_opt = 'concord';
	}
	else
	{
		if ($Corpus->visualise_gloss_in_context)
			$show_gloss_curr_opt = 'context';
		else
			$show_gloss_curr_opt = 'neither';
	}

	$show_gloss_options = '';
	foreach ($opts as $o => $d)
		$show_gloss_options .= "\t\t\t\t\t\t<option value=\"$o\""
							. ($o == $show_gloss_curr_opt ? ' selected' : '')
							. ">$d</option>\n";

	$gloss_annotaton_options = "\t\t\t\t\t\t<option value=\"~~none~~\""
								. (isset($Corpus->visualise_gloss_annotation) ? '' : ' selected')
								. ">No annotation selected</option>\n";
	foreach($annotations as $h => $d)
		$gloss_annotaton_options .= "\t\t\t\t\t\t<option value=\"$h\""
							. (isset($Corpus->visualise_gloss_annotation) && $h == $Corpus->visualise_gloss_annotation ? ' selected' : '')
							. '>' . escape_html($d) . "</option>\n";
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th  colspan="2" class="concordtable">
				(1) Interlinear gloss
			</th>
		</tr>
		<tr>
			<td  colspan="2" class="concordgrey">
				&nbsp;<br>
				You can select an annotation to be treated as the "gloss" and displayed in
				query results and/or extended context display.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Use annotation:</td>
			<td class="concordgeneral">
				<form id="updateGlossForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updateGlossForm" type="hidden" name="caAction" value="updateGloss">
				<select form="updateGlossForm" name="updateGlossAnnotation">
					<?php echo $gloss_annotaton_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<!-- at some point, it might be nice to allow users to set this for themselves. -->
			<td class="concordgrey">Show gloss in:</td>
			<td class="concordgeneral">
				<select form="updateGlossForm" name="updateGlossShowWhere">
					<?php echo $show_gloss_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center" class="concordgeneral">
				<input form="updateGlossForm" type="submit" value="Update settings">
			</td>
		</tr>
	</table>
	
	<?php

	/* 
	 * SECOND SECTION --- TRANSLATION VISUALISATION 
	 */


	/* set up option string for second form */
	$s_attributes = list_xml_all($Corpus->name);
	/* the descriptions will be printed more than once, so escape now. */
	$s_attributes_escaped = array_map('escape_html', $s_attributes);
	

	/* note that $opts array already exists (from previous form set up) */
	if ($Corpus->visualise_translate_in_concordance)
	{
		if ($Corpus->visualise_translate_in_context)
			$show_translate_curr_opt = 'both';
		else
			$show_translate_curr_opt = 'concord';
	}
	else
	{
		if ($Corpus->visualise_translate_in_context)
			$show_translate_curr_opt = 'context';
		else
			$show_translate_curr_opt = 'neither';
	}
	
	$show_translate_options = '';
	foreach ($opts as $o => $d)
		$show_translate_options .= "\t\t\t\t\t\t<option value=\"$o\""
								. ($o == $show_translate_curr_opt ? ' selected' : '')
								. ">$d</option>\n"
								;
	$translate_XML_options = "\t\t\t\t\t\t<option value=\"~~none~~\""
							. (isset($Corpus->visualise_translate_s_att) ? '' : ' selected')
							. ">No XML element-attribute selected</option>"
							;
	foreach($s_attributes_escaped as $s=>$s_desc)
		$translate_XML_options .= "\t\t\t\t\t\t<option value=\"$s\""
								. (isset($Corpus->visualise_translate_s_att) && $s == $Corpus->visualise_translate_s_att ? ' selected' : '')
								. ">$s_desc ($s)</option>\n"
								;
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th  colspan="2" class="concordtable">
				(2) Free translation
			</th>
		</tr>
		<tr>
			<td colspan="2" class="concordgrey">
				&nbsp;<br>
				You can select an XML element/attribute to be used to provide whole-sentence or
				whole-utterance translation.
				<br>&nbsp;<br>
				Note that if this setting is enabled, it <b>overrides</b> the context setting.
				The context is automatically set to "one of whatever XML attribute you are using".
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Select XML element/attribute to get the translation from:</td>
			<td class="concordgeneral">
			<form id="updateTranslateForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<select form="updateTranslateForm" name="updateTranslateXML">
					<?php echo $translate_XML_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<!-- at some point, it might be nice to allow users to set this for themselves. -->
			<td class="concordgrey">Show free translation in:</td>
			<td class="concordgeneral">
				<select form="updateTranslateForm" name="updateTranslateShowWhere">
					<?php echo $show_translate_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center" class="concordgeneral">
				<input form="updateTranslateForm" type="submit" value="Update settings">
				<input form="updateTranslateForm" type="hidden" name="caAction" value="updateTranslate">
			</td>
		</tr>
	</table>

	<?php

	/* 
	 * THIRD SECTION --- POSITION LABELS 
	 */


	/* we can again re-use $s_attributes from above */
	$position_label_options = "\t\t\t\t\t\t<option value=\"~~none~~\""
							. ($Corpus->visualise_position_labels ? '' : ' selected')
							. ">No position labels will be shown in the concordance</option>"
							;
	foreach(array_keys(list_xml_with_values($Corpus->name)) as $s)
		if ('text_id' != $s)
			$position_label_options .= "\t\t\t\t\t\t<option value=\"$s\""
							. ($s == $Corpus->visualise_position_label_attribute ? ' selected' : '')
							. ">{$s_attributes_escaped[$s]} ($s) will used for position labels</option>\n"
							;
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th  colspan="2" class="concordtable">
				(3) Position labels
			</th>
		</tr>
		<tr>
			<td  colspan="2" class="concordgrey">
				&nbsp;<br>
				You can select an XML element/attribute to be used to indicate the position <em>within</em> its text
				where each concordance result appears. A typical choice for this would be sentence or utterance number.
				<br>&nbsp;<br>
				<strong>Warning</strong>: If you select an element/attribute pair that does not cover the entire corpus, no
				position label will be shown next to a result at a corpus position with no value for the selected attribute!
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Select XML element/attribute to use for position labels:</td>
			<td class="concordgeneral">
				<form id="updatePositionLabelAttributeForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="updatePositionLabelAttributeForm" type="hidden" name="caAction" value="updatePositionLabelAttribute">
				<select form="updatePositionLabelAttributeForm" name="newPositionLabelAttribute">
					<?php echo $position_label_options; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center" class="concordgeneral">
				<input form="updatePositionLabelAttributeForm" type="submit" value="Update setting">
			</td>
		</tr>
	</table>

	
	<?php
//	TODO from here on down.....
//	
//	
//	note, way down the road, it would be nice if auto-transliteration
//	could affect database-derived tables as well
//	- and, of course, be configurable on a per-user basis.
	
	// for now, don't display
	return;
	
	/* 
	 * FOURTH SECTION --- TRANSLITERATION VISUALISATION 
	 */

	
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th  colspan="2" class="concordtable">
				(4) Transliteration    [NOT WORKING YET!!]
			</th>
		</tr>
		<tr>
			<td  colspan="2" class="concordgrey">
				&nbsp;<br>
				You can have the "word" attribute automatically transliterated into the Latin
				alphabet, as long as you have added an appropriate transliterator plugin to CQPweb
				(or are happy to use the default).
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Select transliterator:</td>
			<td class="concordgeneral">
			
			</td>
		</tr>
		<tr>
			<!-- at some point, it might be nice to allow users to set this for themselves. -->
			<td class="concordgrey">Autotransliterate in:</td>
			<td class="concordgeneral">
				<!-- no action cos not finished yet -->
				<form class="greyoutOnSubmit" action="NEEED_AN_ACTION_HERE_TODOTODOTODO" method="get"></form>
				<select name="no name yet">
					<option>Concordance only</option>
					<option>Context only</option>
					<option>Both concordance and context</option>
				</select>
			</td>
		</tr>
		<tr>
			<!-- at some point, it might be nice to allow users to set this for themselves. -->
			<td class="concordgrey">Show:</td>
			<td class="concordgeneral">
				<select name="no name yet">
					<option>Original script only</option>
					<option>Autotransliterated text only</option>
					<option>Original and autotransliterated text</option>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center" class="concordgeneral">
				<input type="submit" value="Update settings">
			</td>
		</tr>
	</table>

	<?php
}




function do_ui_xmlvisualisation()
{
	global $Corpus;
	
	
	/* variable setup for "extra code file" controls */
	
	/* possible JavaScript extra code: all file in ../jsc, MINUS built-in */
	$js_options = scandir('../jsc');
	$js_builtin = get_jsc_reflection_list();
	foreach ($js_options as $k=>$v)
		if (in_array($v, $js_builtin) || 1 > preg_match('/^[^\.].*\.js$/i', $v))
			unset($js_options[$k]);
	
	
	/* possible CSS extra code: all files in ../css. Again, exclude built-in. */
	$css_options = scandir('../css');
	$css_builtin = get_css_reflection_list();
	foreach ($css_options as $k=>$v)
		if (in_array($v, $css_builtin) || ! preg_match('/^[^\.].*\.css$/i', $v))
			unset($css_options[$k]);
	
	/* and what is currently activated? check db field. Disallow empty string which results if there are no activated files. */
	$conc_existing_js     = preg_split('/~/', $Corpus->visualise_conc_extra_js,     -1, PREG_SPLIT_NO_EMPTY);
	$conc_existing_css    = preg_split('/~/', $Corpus->visualise_conc_extra_css,    -1, PREG_SPLIT_NO_EMPTY);
	$context_existing_js  = preg_split('/~/', $Corpus->visualise_context_extra_js,  -1, PREG_SPLIT_NO_EMPTY);
	$context_existing_css = preg_split('/~/', $Corpus->visualise_context_extra_css, -1, PREG_SPLIT_NO_EMPTY);
	
 	/* 
 	 * OK, now the getting ready is done, let's render the form 
 	 */
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				(5) XML visualisation
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				XML visualisations are commands stored in the database which describe how an indexed
				XML element (or, in CWB terms, an &ldquo;s-attribute&rdquo;) is to appear in the concordance.
				<br>&nbsp;<br>
				By default, all XML elements are invisible. You must create and enable a visualisation for
				each XML element in each corpus that you wish to display to the user.  
				<br>&nbsp;<br>
				An XML visualisation can be unconditional, in which case it will always apply. Or, it can have
				a condition attached to it - a regular expresion that will be matched against an attribute on
				the XML tag, with the visualisation only displayed if the regular expression matches. This allows
				you to have different visualisations for &lt;element type="A"&gt; and &lt;element type="B"&gt;.
				<br>&nbsp;<br>
				You can define an unconditional visualisation for the same element as one or more conditional
				visualisations, in which case, the unconditional visualisation applies in any cases where none of the 
				conditional visualisations apply. In addition, note that conditions are only possible on start tags, 
				not end tags.
				<br>&nbsp;<br>
				You can use the forms below to manage your visualisations.
				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="3" class="concordtable">Extra code files for visualisation</th>
		</tr>
		<tr>
			<td colspan="3" class="concordgrey">
				&nbsp;<br>
				Extra code files are specified JavaScript (.js) or Cascading Stylesheet (.css) files that will
				be inserted into the concordance and context displays <em>in additon to</em> CQPweb's
				normal JS/CSS files. Using extra code files allows you to apply addiitonal styling or dynamic]
				behaviour to an XML visualisation that would not be possible through the subset of hTML
				that can be used directlym ina  visualisation. 
				<br>&nbsp;<br>
				You can only specify an extra code file for use in concordance or context display if it actually
				exists in the appropriate location on your server (the web-directory <code>css</code> for 
				CSS files and <code>jsc</code> for JavaScript files).
				<br>&nbsp;<br>
				You can add as many code files as you like of either kind. If you want to use the same code file in 
				both concordance display and context display, you must add it to the list for each separately.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th colspan="3" class="concordtable">(a) Extra code files in <strong>concordance</strong> display</th>
		</tr>
		<tr>
			<td rowspan="2" class="concordgrey">Add an extra code file:</td>
			<td class="concordgeneral">
				<form id="addXmlVizConcJSForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="addXmlVizConcJSForm" type="hidden" name="caAction" value="addXmlVizConcJS">
				<select form="addXmlVizConcJSForm" name="newFile">
					<option selected value="">
						<?php 
						if (empty($js_options)) 
							echo "No JavaScript files are available.";
						else
							echo 'Choose a JavaScript file to use in concordance display...</option><option>'
 								, implode('</option><option>', $js_options)
 								, "\n"
								;
						?>
					</option>
				</select>
			</td>
			<td class="concordgeneral">
				<input form="addXmlVizConcJSForm" type="submit" value="Add this file">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				<form id="addXmlVizConcCSSForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="addXmlVizConcCSSForm" type="hidden" name="caAction" value="addXmlVizConcCSS">
				<select form="addXmlVizConcCSSForm" name="newFile">
					<option selected value="">
						<?php 
						if (empty($css_options)) 
							echo 'No CSS files are available.', "\n";
						else
							echo 'Choose a CSS file to use in concordance display...</option><option>'
 								, implode('</option><option>', $css_options)
								, "\n"
								;
						?>
					</option>
				</select>
			</td>
			<td class="concordgeneral">
				<input form="addXmlVizConcCSSForm" type="submit" value="Add this file">
			</td>
		</tr>
		<tr>
			<td rowspan="2" class="concordgrey">Currently activated extra code files:</td>
			<td class="concordgeneral" align="center" width="33%">
				<b>... JavaScript files</b>
			</td>
			<td class="concordgeneral" align="center" width="33%">
				<b>... CSS files</b>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<table class="basicbox">
					<?php
					if (empty($conc_existing_js))
						echo '<tr><td class="basicbox">(none added)</td></tr>';
					echo "\n";
					foreach ($conc_existing_js as $file)
						echo "\t\t\t\t\t<tr>"
							, '<td class="basicbox">', $file, '</td>'
							, '<td class="basicbox"><a class="menuItem" href="corpus-act.php?caAction=removeXmlVizConcJS&fileRemove='
							, urlencode($file)
							, '">[x]</a></td>'
							, "</tr>\n"
							;
					?>
				</table>
			</td>
			<td class="concordgeneral" align="center">
				<table class="basicbox">
					<?php
					if (empty($conc_existing_css))
						echo '<tr><td class="basicbox">(none added)</td></tr>'; 
					echo "\n";
					foreach ($conc_existing_css as $file)
						echo "\t\t\t\t\t<tr>"
							, '<td class="basicbox">', $file, '</td>'
							, '<td class="basicbox"><a class="menuItem" href="corpus-act.php?caAction=removeXmlVizConcCSS&fileRemove='
							, urlencode($file)
							, '">[x]</a></td>'
							, "</tr>\n"
							;
					?>
				</table>
			</td>
		</tr>
		<tr>
			<th colspan="3" class="concordtable">(b) Extra code files in <strong>context</strong> display</th>
		</tr>
		<tr>
			<td rowspan="2" class="concordgrey">Add an extra code file:</td>
			<td class="concordgeneral">
				<form id="addXmlVizContextJSForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="addXmlVizContextJSForm" type="hidden" name="caAction" value="addXmlVizContextJS">
				<select form="addXmlVizContextJSForm" name="newFile">
					<option selected value="">
						<?php 
						if (empty($js_options)) 
							echo "No JavaScript files are available.\n";
						else
							echo 'Choose a JavaScript file to use in context display...</option><option>'
								, implode('</option><option>', $js_options)
								, "\n"
								;
						?>
					</option>
				</select>
			</td>
			<td class="concordgeneral">
				<input form="addXmlVizContextJSForm" type="submit" value="Add this file">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				<form id="addXmlVizContextCSSForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="addXmlVizContextCSSForm" type="hidden" name="caAction" value="addXmlVizContextCSS">
				<select form="addXmlVizContextCSSForm" name="newFile">
					<option selected value="">
						<?php 
						if (empty($css_options)) 
							echo 'No CSS files are available.', "\n";
						else
							echo 'Choose a CSS file to use in context display...</option><option>'
 								, implode('</option><option>', $css_options)
								, "\n"
								;
						?>
					</option>
				</select>
			</td>
			<td class="concordgeneral">
				<input form="addXmlVizContextCSSForm" type="submit" value="Add this file">
			</td>
		</tr>
		<tr>
			<td rowspan="2" class="concordgrey">Currently activated extra code files:</td>
			<td class="concordgeneral" align="center" width="33%">
				<b>... JavaScript files</b>
			</td>
			<td class="concordgeneral" align="center" width="33%">
				<b>... CSS files</b>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<table class="basicbox">
					<?php
					if (empty($context_existing_js))
						echo '<tr><td class="basicbox">(none added)</td></tr>';
					echo "\n";
					foreach ($context_existing_js as $file)
						echo "\t\t\t\t\t<tr>"
							, '<td class="basicbox">', $file, '</td>'
							, '<td class="basicbox"><a class="menuItem" href="corpus-act.php?caAction=removeXmlVizContextJS&fileRemove='
							, urlencode($file)
							, '">[x]</a></td>'
							, "</tr>\n"
							;
					?>
				</table>
			</td>
			<td class="concordgeneral" align="center">
				<table class="basicbox">
					<?php
					if (empty($context_existing_css))
						echo '<tr><td class="basicbox">(none added)</td></tr>', "\n";
					else
						foreach ($context_existing_css as $file)
							echo '<tr>'
 								, '<td class="basicbox">', $file, '</td>'
 								, '<td class="basicbox"><a class="menuItem" href="corpus-act.php?caAction=removeXmlVizContextCSS&fileRemove='
 								, urlencode($file)
 								, '">[x]</a></td>'
 								, '</tr>', "\n"
 								;
					?>
				</table>
			</td>
		</tr>
	</table>

	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Visualisation fallback procedures 
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				Do you want to add a paragraph break after punctuation tokens in context view?
				<br>&nbsp;<br>
				<em>
					This is a basic fallback mechanism for breaking the corpus text into paragraphs. 
					It is usually best to switch it <b>on</b> if you are not using an actual 
					XML visualisation to break paragraphs in context view.
					It is switched on by default.
				</em>
			</td>
			<td class="concordgeneral" width="50%">
				<form class="greyoutOnSubmit" action="corpus-act.php" method="get">
					<input type="hidden" name="caAction" value="updateBreakOnPunc">
					<select name="break">
						<option value="1"<?php if ( $Corpus->visualise_break_context_on_punc) echo ' selected'; ?>>Yes</option>
						<option value="0"<?php if (!$Corpus->visualise_break_context_on_punc) echo ' selected'; ?>>No</option>
					</select>
					&nbsp;
					<input type="submit" value="Update">
				</form>
			</td>
		</tr>
	</table>


	<table class="concordtable fullwidth">
		<!-- display current visualisations for this corpus -->
		<tr>
			<th colspan="6" class="concordtable">
				Existing XML visualisation commands
			</th>
		</tr>
		<tr>
			<th class="concordtable">&nbsp;</th>
			<th class="concordtable">Applies to ... </th>
			<th class="concordtable">HTML code</th>
			<th class="concordtable">Show where?</th>
			<th class="concordtable" colspan="2">Actions</th>
		</tr>
		
		<?php
		
		/* show each existing visualisation for this corpus */
		
// 		$where_values = array(
// 			'in_conc'    => "In concordance displays only",
// 			'in_context' => "In extended context displays only",
// 			'both'       => "In concordance AND context displays",
// 			'neither'    => "Nowhere (visualisation disabled)"
// 			);

		$viz_list = get_all_xml_visualisations($Corpus->name, true, true, true, true); 
		
		if (0 == count($viz_list))
			echo '<tr><td colspan="6" class="concordgrey" align="center">'
				, '&nbsp;<br>There are currently no XML visualisations in the database.<br>&nbsp;'
				, "</td></tr>\n"
				;
		
		foreach ($viz_list as $v)
		{
			echo '
				<form class="greyoutOnSubmit" action="corpus-act.php" method="get">
				<tr>
				';
			
			list($tag, $startend) = explode('~', $v->element);
			$startend = ($startend=='end' ? '/' : ''); 
			$condition_print = escape_html($v->conditional_on, true);
			
			/* note that for the condition_print, as for the textarea with the code below,
			 * we cannot use the normal escape_html because we DO want entitites to be double-encoded
			 * so that they appear as-typed in the interface.
			 */
			
			echo '
				<td class="concordgeneral" align="center">', $v->id, '</td>
				';
			
			echo '
				<td class="concordgeneral">&lt;' , $startend , $tag , '&gt;'
				, (empty($v->conditional_on) ? '' : "<br>where value matches <em>$condition_print</em>\n")  
				, '</td>
				';
			
			echo '
				<td class="concordgeneral" align="center"><textarea cols="40" rows=3" name="xmlVizRevisedHtml">' 
				, escape_html($v->html, true)
				, '</textarea></td>
				';
			
			$in_concordance_select_no  = ' selected';
			$in_context_select_no      = ' selected';
			$in_download_select_no     = ' selected';
			$in_concordance_select_yes = '';
			$in_context_select_yes     = '';
			$in_download_select_yes    = '';
			if ($v->in_concordance)
			{
				$in_concordance_select_no  = '';
				$in_concordance_select_yes = ' selected';
			}
			if ($v->in_context)
			{
				$in_context_select_no  = '';
				$in_context_select_yes = ' selected';
			}
			if ($v->in_download)
			{
				$in_download_select_no  = '';
				$in_download_select_yes = ' selected';
			}
			
// 			switch (true)
// 			{
// 			case ( $v->in_context &&  $v->in_concordance):		$checked = 'both';			break; 
// 			case (!$v->in_context && !$v->in_concordance):		$checked = 'neither';		break; 
// 			case (!$v->in_context &&  $v->in_concordance):		$checked = 'in_conc';		break; 
// 			case ( $v->in_context && !$v->in_concordance):		$checked = 'in_context';	break; 
// 			}
// 			$options = "\n";
// 			foreach ($where_values as $val=>$label)
// 			{
// 				$ch = ($checked == $val ? ' selected' : '');
// 				$options .= "\n\t\t\t\t\t<option value=\"$val\"$ch>$label</option>\n";
// 			}
			
// 			echo '
// 				<td class="concordgeneral" align="center">
// 				<select name="xmlVizUseInSelector">'
// 				, $options
// 				, '
// 				</select>
// 				</td>
// 				<td class="concordgeneral" align="center">'
// 				, '<input type="submit" value="Update">' 
// 				, '</td>
// 				<td class="concordgeneral" align="center">'
// 				, '<a class="menuItem" href="corpus-act.php?caAction=deleteXmlViz&toDelete='
// 				, $v->id
// 				, '">[Delete]</a>'
// 				, '</td>
// 				';
			echo '
				<td class="concordgeneral" align="center">
					<table>
						<tr>
							<td class="tightbox">In concordance?&nbsp;&nbsp;</td>
							<td class="tightbox" align="center">
								<select name="xmlVizUseInConc">
									<option value="1"', $in_concordance_select_yes, '>Yes</option>
									<option value="0"', $in_concordance_select_no , '>No </option>
								</select>
							</td>
						</tr>
						<tr>
							<td class="tightbox">In context?&nbsp;&nbsp;</td>
							<td class="tightbox" align="center">
								<select name="xmlVizUseInContext">
									<option value="1"', $in_context_select_yes, '>Yes</option>
									<option value="0"', $in_context_select_no , '>No </option>
								</select>
							</td>
						</tr>
						<tr>
							<td class="tightbox">In query download?&nbsp;&nbsp;</td>
							<td class="tightbox" align="center">
								<select name="xmlVizUseInDownload">
									<option value="1"', $in_download_select_yes, '>Yes</option>
									<option value="0"', $in_download_select_no , '>No </option>
								</select>
							</td>
						</tr>
					</table>
					
				</td>
				
				<td class="concordgeneral" align="center">'
				, '<input type="submit" value="Update">' 
				, '</td>
				<td class="concordgeneral" align="center">'
				, '<a class="menuItem" href="corpus-act.php?caAction=deleteXmlViz&toDelete='
				, $v->id
				, '">[Delete]</a>'
				, '</td>
				';
				
				
			echo '
				</tr>
				<input type="hidden" name="vizToUpdate" value="' , $v->id , '">
				<input type="hidden" name="caAction" value="updateXmlViz">
				</form>
				';

		}
		?>

	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Create new XML visualisation command
			</th>
		</tr>
		
		<tr>
			<td class="concordgrey">
				Select one of the available XML elements:
			</td>
			<td class="concordgeneral">
				<form id="createXmlVizForm" class="greyoutOnSubmit" action="corpus-act.php" method="get"></form>
				<input form="createXmlVizForm" type="hidden" name="caAction" value="createXmlViz">
				<select form="createXmlVizForm" name="xmlVizElement">
					<?php
					foreach (list_xml_all($Corpus->name) as $x=>$x_desc)
						echo "<option value=\"$x\">$x : ", escape_html($x_desc), "</option>\n\t\t\t\t\t\t";
					echo "\n";
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Create visualisation for start or end tag?</td>
			<td class="concordgeneral">
				<input form="createXmlVizForm" type="radio" id="xmlVizIsStartTag:1" name="xmlVizIsStartTag" value="1" checked> 
				<label for="xmlVizIsStartTag:1">Start tag</label>
				<input form="createXmlVizForm" type="radio" id="xmlVizIsStartTag:0" name="xmlVizIsStartTag" value="0"> 
				<label for="xmlVizIsStartTag:0">End tag</label>
			</td>
		</tr>
		<tr>
			<td align="center" colspan="2" class="concordgrey">
				<em>
					Note: if you choose an element start/end for which a visualisation 
					already exists, the existing visualisation will NOT be deleted.  
					<br>
					Only one visualisation ever has effect for a given start/end tag.
					You can have different visualisations of the same tag in context/concordance.
				</em>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">
				Enter the code (text + restricted HTML) for the visualisation you want to create.
				<br>&nbsp;<br>
				(See the chapter on visualisation in the 
				<a target="_blank" href="../doc/CQPwebAdminManual.pdf">CQPweb System Administrator's Manual</a>
				for more information.)
			</td>
			<td class="concordgeneral">
				<textarea form="createXmlVizForm" cols="50" rows="3" name="xmlVizHtml"></textarea>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Use this visualisation in concordances?</td>
			<td class="concordgeneral">
				<input form="createXmlVizForm" type="radio" id="xmlVizUseInConc_1" name="xmlVizUseInConc" value="1" checked>
				<label for="xmlVizUseInConc_1">Yes</label>
				<input form="createXmlVizForm" type="radio" id="xmlVizUseInConc_0" name="xmlVizUseInConc" value="0">
				<label for="xmlVizUseInConc_0">No</label>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Use this visualisation in extended context display?</td>
			<td class="concordgeneral">
				<input form="createXmlVizForm" type="radio" id="xmlVizUseInContext_1" name="xmlVizUseInContext" value="1" checked>
				<label for="xmlVizUseInContext_1">Yes</label>
				<input form="createXmlVizForm" type="radio" id="xmlVizUseInContext_0" name="xmlVizUseInContext" value="0">
				<label for="xmlVizUseInContext_0">No</label>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Use this visualisation in downloaded query results (plaintext format)?</td>
			<td class="concordgeneral">
				<input form="createXmlVizForm" type="radio" id="xmlVizUseInDownload_1" name="xmlVizUseInDownload" value="1">
				<label for="xmlVizUseInDownload_1">Yes</label>
				<input form="createXmlVizForm" type="radio" id="xmlVizUseInDownload_0" name="xmlVizUseInDownload" value="0" checked>
				<label for="xmlVizUseInDownload_0">No</label>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">
				Specify a condition?
				<br>&nbsp;<br>
				<em>(Only possible for start tags. Leave blank for an unconditional visualisation.)</em>
			</td>
			<td class="concordgeneral">
				The value of the XML attribute must match this regular expression:
				<br>
				<input form="createXmlVizForm" type="text" size="100" maxlength="800" name="xmlVizCondition">
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Click here to store this visualisation</td>
			<td class="concordgeneral">
				<input form="createXmlVizForm" type="submit" value="Create XML visualisation">
			</td>
		</tr>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="5" class="concordtable">
				Import visualisation from template
			</th>
		</tr>
		<tr>
			<td colspan="5" class="concordgrey">
				&nbsp;<br>
				Templates are visualisations from other corpora that you have flagged to be generally available
				on this system. The currently available templates are listed below.
				<br>&nbsp;<br>
				<a href="../adm/index.php?ui=visualisationTemplates">Click here to manage visualisation templates.</a>
				<br>&nbsp;<br>
				If you import a template, an identical visualisation will be created in this corpus.
				<br>&nbsp;
			</td>
		</tr>
		
		<tr>
			<th class="concordtable" colspan="5">
				Available visualisation templates
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				Original corpus
			</th>
			<th class="concordtable">
				Applies to...
			</th>
			<th class="concordtable" >
				HTML code
			</th>
			<th class="concordtable">
				Show where?
			</th>
			<th class="concordtable">
				Import here
			</th>
		</tr>
		
		<?php
		$templates_printed = 0;

		$template_list = get_global_xml_visualisations(true);

		foreach($template_list as $t)
		{
// 			if ($t->corpus == $Corpus->name)
// 				continue;
// 			else 
				++$templates_printed;
			
			list($tag, $startend) = explode('~', $t->element);
			$startend = ($startend=='end' ? '/' : ''); 
			$condition_print = htmlspecialchars($t->conditional_on, ENT_COMPAT, 'UTF-8', true);

			echo "\n\t\t<tr>\n\n"
 				, '<td class="concordgeneral">', $t->corpus, '</td>'
 				, '<td class="concordgeneral">&lt;' , $startend , $tag , '&gt;'
				, (empty($t->conditional_on) ? '' : "<br>where value matches <em>$condition_print</em>\n")  
				, '</td>'
 				, '<td class="concordgeneral"><pre>', htmlspecialchars($t->html, ENT_COMPAT, 'UTF-8', true), '</pre></td>'
	 			, '<td class="concordgeneral">'
				, 'In concordance? &ndash; <b>', ($t->in_concordance?'Yes':'No'), '</b><br>'
				, 'In context?     &ndash; <b>', ($t->in_context    ?'Yes':'No'), '</b><br>'
				, 'In download?    &ndash; <b>', ($t->in_download   ?'Yes':'No'), '</b>'
				, '</td>'
				, '<td class="concordgeneral" align="center">'
					, '<form class="greyoutOnSubmit" action="corpus-act.php" method="get">'
						, '<input type="submit" value="Import!">'
						, '<input type="hidden" name="caAction" value="importXmlViz">'
						, '<input type="hidden" name="templateViz" value="', $t->id, '">'
					, '</form>'
				, '</td>'
				, "\n\n\t\t</tr>\n"
 				;
		}
		if (1 > $templates_printed)
		{
			?>
			<tr>
				<td class="concordgrey" colspan="5">
					<p class="spacer">&nbsp;</p>
					<p>None of your XML visualisations are currently enabled to act as templates. 
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<?php 
		}
		
		?>
		
	</table>
	
	<?php
}



function do_ui_addtocorpus()
{
	global $Corpus;
	
	/* links to 3 sorts of add */
	$plink = '<a href="index.php?ui=addToCorpus&addWhat=p" class="menuItem">[Add p-attribute data]</a>';
	$slink = '<a href="index.php?ui=addToCorpus&addWhat=s" class="menuItem">[Add s-attribute data]</a>';
	$mlink = '<a href="index.php?ui=addToCorpus&addWhat=m" class="menuItem">[Add metadata field]</a>';
	
	
	switch(isset($_GET['addWhat']) ? $_GET['addWhat'] : false)
	{
	case 'm':
		$mlink = '<a class="menuCurrentItem">Add metadata field</a>';
		break;
	case 's':
		$slink = '<a class="menuCurrentItem">Add s-attribute data</a>';
		break;
	case 'p':
		$plink = '<a class="menuCurrentItem">Add p-attribute data</a>';
		break;
	}
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="3" class="concordtable">
				Add data to corpus <?php echo $Corpus->name;?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey" width="33.3%" align="center">
				&nbsp;<br>
				<?php echo $plink, "\n"  ; ?>
				<br>&nbsp;
			</td>
			<td class="concordgrey" width="33.3%" align="center">
				&nbsp;<br>
				<?php echo $slink, "\n" ; ?>
				<br>&nbsp;
			</td>
			<td class="concordgrey" width="33.3%" align="center">
				&nbsp;<br>
				<?php echo $mlink, "\n"  ; ?>
				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	<?php
	
	switch(isset($_GET['addWhat']) ? $_GET['addWhat'] : false)
	{
	case 'm':
		do_bitui_addtocorpus_metadata();
		break;
	case 's':
		do_bitui_addtocorpus_s();
		break;
	case 'p':
		do_bitui_addtocorpus_p();
		break;
	}
	
}


function do_bitui_addtocorpus_p()
{
	global $Corpus;
	
	?>
	
	<form class="greyoutOnSubmit" action="corpus-act.php" method="get">
	<input type="hidden" name="caAction" value="extraPatt">


	<table class="concordtable fullwidth">
		<tr>
			<th colspan="5" class="concordtable">
				Add a new p-attribute (word-level annotation)
			</th>
		</tr>
		<tr>
			<td colspan="5" class="concordgrey">
				<p class="spacer">&nbsp;</p>
				<p>
					A new p-attribute can be imported from a plain text file.
					The annotation for each token must be placed on a separate line.
					The file must therefore contain as many lines as there are tokens in the corpus.
					(Note: this corpus contains <b><?php echo number_format($Corpus->size_tokens); ?></b> tokens.)
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Please enter a handle for your new p-attribute: 
			</td>
			<td colspan="2" class="concordgeneral">
				<input type="text" name="newAttHandle" onKeyUp="check_c_word(this)" >
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Please enter a decription for your new p-attribute: 
			</td>
			<td colspan="2" class="concordgeneral">
				<input type="text" name="newAttDesc">
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Please enter the name of the tagset used by this annotation:
				<br>
				<em>(leave empty if not applicable)</em> 
			</td>
			<td colspan="2" class="concordgeneral">
				<input type="text" name="newAttTagset">
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Please enter a URL for documentation on the tagset used: 
				<br>
				<em>(leave empty if not applicable)</em> 
			</td>
			<td colspan="2" class="concordgeneral">
				<input type="text" name="newAttUrl">
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Is your new attribute a feature set?
			</td>
			<td colspan="2" class="concordgeneral">
				<select name="newAttIsFS">
					<option value="0" selected>No</option>
					<option value="1">Yes</option>
				</select>
			</td>
		</tr>
		
		<?php
		
		if ($Corpus->cwb_external)
		{
			?>
			
			<tr>
				<td colspan="2" class="concorderror">
					Warning: this corpus was indexed externally and then imported into CWB.
					<br>&nbsp;<br>
					Adding new corpus data will only work if the web server has write-access
					to the data location of the CWB index files.
				</td>
			</tr>
			
			<?php
		}
		
		?>

		<tr>
			<th class="concordtable" colspan="5">Choose the file containing the data of the new S-attribute</th>
		</tr>

		<tr>
			<th class="concordtable">Use?</th>
			<th colspan="2" class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
		</tr>

		<?php
		echo print_file_selector();
		?>

		<tr>
			<td align="center" class="concordgeneral" colspan="5">
				<input type="submit" value="Add new p-attribute using the settings above">
			</td>
		</tr>
	</table>

	</form>

	<form class="greyoutOnSubmit" action="execute.php" method="get">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">Scan for new p-attributes added offline</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					&nbsp;<br>
					<input type="submit" value="Click here to scan the registry for newly-added p-attributes">
					<br>
					<input type="hidden" name="function" value="scan_for_new_corpus_attributes">
					<input type="hidden" name="args" value="<?php echo $Corpus->name, '#p'; ?>">
					<input type="hidden" name="locationAfter" value="index.php?ui=manageAnnotation">
					<br>&nbsp;
					<p class="spacer">&nbsp;</p>
				</td>
			</tr> 
		</table>
	</form>

	<?php
}


function do_bitui_addtocorpus_s()
{
	global $Corpus;

	/* create options for adding an annotation to an existing element */
	$existing_options = '';
	foreach(list_xml_elements($Corpus->name) as $handle => $desc)
		$existing_options .= "\n\t\t\t\t\t<option value=\"value~" . $handle 
			. '">A new set of data values for existing element &ldquo;' . $handle
			. '&rdquo; (' . escape_html($desc) . ')</option>'
 			;
	$existing_options .= "\n";
	
	?>
	
	<form class="greyoutOnSubmit" action="corpus-act.php" method="get">
	<input type="hidden" name="caAction" value="extraSatt">
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="5" class="concordtable">
				Add a new s-attribute (XML element/attribute)
			</th>
		</tr>
		<tr>
			<td colspan="5" class="concordgrey">
				<p class="spacer">&nbsp;</p>
				<p>
					A new s-attribute can be imported from a tab-delimited plain text file.
					When creating a new XML element, the file should contain a sorted sequence of
					corpus positions (begin/end pairs), with the start point in column one, and the 
					end point in column two. When adding a new annotation to an existing XML element,
					the corpus positions must be the same as those in the existing s-attribute,
					with an additional third column containing the values of the new annotation.
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Please enter a handle for your new s-attribute: 
			</td>
			<td colspan="2" class="concordgeneral">
				<input type="text" name="newAttHandle" onKeyUp="check_c_word(this)" >
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Please enter a description for your new s-attribute: 
			</td>
			<td colspan="2" class="concordgeneral">
				<input type="text" name="newAttDesc">
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				What kind of s-attribute do you wish to add?
			</td>
			<td colspan="2" class="concordgeneral">
				<select name="addType">
					<option value="~newElement">A new set of corpus regions (i.e. a new XML element)</option>
					<?php echo $existing_options; ?>
				</select>
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Select a datatype for the s-attribute:
				<br>
				<em>(only applies for new data values for existing XML elements)</em>
			</td>
			<td colspan="2" class="concordgeneral">
				<select name="datatype">
					<option value="<?php echo METADATA_TYPE_FREETEXT;       ?>" selected>Free text</option>
					<option value="<?php echo METADATA_TYPE_CLASSIFICATION; ?>">Classification</option>
					<option value="<?php echo METADATA_TYPE_IDLINK;         ?>">ID link</option>
					<option value="<?php echo METADATA_TYPE_UNIQUE_ID;      ?>">Unique ID</option>
					<!-- TODO use the values in $Config->metadata_type_descriptions once we allow DATE too. -->
				</select>
			</td>
		</tr>
		
		<?php
		
		if ($Corpus->cwb_external)
		{
			?>
			
			<tr>
				<td colspan="2" class="concorderror">
					Warning: this corpus was indexed externally and then imported into CWB.
					<br>&nbsp;<br>
					Adding new corpus data will only work if the web server has write-access
					to the data location of the CWB index files.
				</td>
			</tr>
			
			<?php
		}
		
		?>

		<tr>
			<th class="concordtable" colspan="5">Choose the file containing the data of the new s-attribute</th>
		</tr>

		<tr>
			<th class="concordtable">Use?</th>
			<th colspan="2" class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
		</tr>

		<?php
		echo print_file_selector();
		?>

		<tr>
			<td align="center" class="concordgeneral" colspan="5">
				<input type="submit" value="Add new s-attribute using the settings above">
			</td>
		</tr>
	</table>
	
	</form>

	<form class="greyoutOnSubmit" action="execute.php" method="get">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">Scan for new s-attributes added offline</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					&nbsp;<br>
					<input type="submit" value="Click here to scan the registry for newly-added s-attributes">
					<br>
					<input type="hidden" name="function" value="scan_for_new_corpus_attributes">
					<input type="hidden" name="args" value="<?php echo $Corpus->name, '#s'; ?>">
					<input type="hidden" name="locationAfter" value="index.php?ui=manageXml">
					<br>&nbsp;
					<p class="spacer">&nbsp;</p>
				</td>
			</tr> 
		</table>
	</form>

	<?php
	
}

function do_bitui_addtocorpus_metadata()
{
	global $Config;
	global $Corpus;
	
	/* The option for text metadata is hardcoded: the idlinks are generated here. */
	$idlink_options = '';
	foreach(get_all_xml_info($Corpus->name) as $x)
		if (METADATA_TYPE_IDLINK == $x->datatype)
			$idlink_options .= "\n\t\t\t\t\t<option value=\"" . $x->handle 
				. '">New metadata field for XML ID-link attribute &ldquo;' . $x->handle
				. '&rdquo; (' . escape_html($x->description) . ')</option>'
	 			;

	// the following bodge is copied from the embiggenable-meadata form code.
	$types_enabled = array(METADATA_TYPE_CLASSIFICATION, METADATA_TYPE_FREETEXT/*, METADATA_TYPE_UNIQUE_ID, METADATA_TYPE_DATE*/);
				/*note that we unique ID and DATE are temporarily disabled; will be reinserted later. */
	$datatype_options = '';
	foreach ($Config->metadata_type_descriptions as $value => $desc)
		if (in_array($value, $types_enabled))
			$datatype_options .= '<option value="' . $value . ($value == METADATA_TYPE_CLASSIFICATION ? '" selected>' : '">') . $desc . '</option>';

	?>
	
	<form class="greyoutOnSubmit" action="corpus-act.php" method="get">
	<input type="hidden" name="caAction" value="extraMeta"> 
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="5" class="concordtable">
				Add new metadata to the corpus
			</th>
		</tr>
		<tr>
			<td colspan="5" class="concordgrey">
				<p class="spacer">&nbsp;</p>
				<p>
					New metadata for texts or for XML attributes of type ID link 
					can be added from a tab-delimited plain text file.
					The first column should contain the ID code of the text (or other entity).
					The second column should contain the value of the new metadata field for that ID.
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Please enter a name for the new metadata field: 
			</td>
			<td colspan="2" class="concordgeneral">
				<input type="text" name="newFieldHandle" onKeyUp="check_c_word(this)" >
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Please enter a description for the new metadata field: 
			</td>
			<td colspan="2" class="concordgeneral">
				<input type="text" name="newFieldDesc">
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				What kind of metadata do you wish to add?
			</td>
			<td colspan="2" class="concordgeneral">
				<select name="target">
					<option value="--t" selected>Text metadata</option>
					<?php echo $idlink_options; ?>
				</select>
			</td>
		</tr>
		
		<tr>
			<td colspan="3" class="concordgrey">
				Select the datatype of the new metadata field:
			</td>
			<td colspan="2" class="concordgeneral">
				<select name="datatype">
					<?php echo $datatype_options; ?>
				</select>
			</td>
		</tr>
		
		
		<tr>
			<th class="concordtable" colspan="5">Choose the file containing the data for the new metadata field</th>
		</tr>
		
		
		<?php
		echo print_file_selector();
		?>

		<tr>
			<td align="center" class="concordgeneral" colspan="5">
				<input type="submit" value="Add new metadata field using the settings above">
			</td>
		</tr>
	</table>
	
	
	</form>
	
	<?php
}





function do_ui_viewsetupnotes()
{
	global $Corpus;
	
	$notes = print_indexing_notes($Corpus->name);
	
	if (empty($notes))
		$notes = '(No setup notes recorded for this corpus.)';
	
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Corpus setup notes
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p>
					The messages below were generated by different system components
					at the time the corpus was set up.
				</p>
				<p>
					Most of them are informational only. However, they may include
					some points useful to solving any problems that you may have had.
				</p>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				<?php echo $notes, "\n"; ?>
			</td>
	</table>
	<?php
}











function do_ui_showquerycache()
{
	global $Corpus;
	global $Config;
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Showing query cache for corpus <?php echo $Corpus->name;?>
			</th>
		</tr>
		<tr>
			<th colspan="2" class="concordtable">
				<i>Admin controls over query cache and query-history log</i>
			</th>
		</tr>
		<tr>
		
		<?php
		$return_to_url = urlencode('index.php?ui=cachedQueries');
		
		echo '<th width="50%" class="concordtable">'
				, '<a id="deleteCacheOverflowButton" class="hasToolTip" data-tooltip="This function affects <strong>all</strong> corpora in the CQPweb database" '
				, 'href="execute.php?function=delete_cache_overflow&locationAfter='
				, $return_to_url
			, '">Delete cache overflow</a></th>'
	 		, '<th width="50%" class="concordtable">Discard old query history<br>(function removed)</th>'
			, '</tr> <tr>'
	 		, '<th width="50%" class="concordtable">'
				, '<a id="clearWholeCacheButton" class="hasTooltip" data-tooltip="This function affects <strong>all</strong> corpora in the CQPweb database" '
				, 'href="execute.php?function=clear_cache&locationAfter='
				, $return_to_url
			, '">Clear entire cache<br>(but keep saved queries)</a></th>'
	 		, '<th width="50%" class="concordtable">Clear entire cache<br>(clear all saved queries)<br>(function removed)</th>'
	 		; 
		?>
		
		</tr>
	</table>
	
	<?php

	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $Config->default_history_per_page;

	echo print_cache_table($begin_at, $per_page, '~~ALL', true, true, 'cached');
}



function do_ui_showfreqtables()
{
	global $Corpus;
	global $Config;
	
	$size = (int)get_sql_value("select sum(ft_size) from saved_freqtables where corpus='{$Corpus->name}'");
	$percent = round( ($size / $Config->freqtable_cache_size_limit) * 100.0 , 2);
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Showing frequency table cache for corpus <em><?php echo $Corpus->name;?></em>
			</th>
		</tr>
		<tr>
			<td colspan="2" class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				<p>
					The currently saved frequency tables <b>for this corpus</b> have a total size of 
					<?php echo number_format($size/1024.0) , " kilobytes, $percent%\n"; ?>
					of the maximum frequency-table cache.
				</p>
				<p>
					<a href="../adm/index.php?ui=freqtableCacheControl">
						Click here for systemwide frequency-table control.
					</a>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
	
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">No.</th>
			<th class="concordtable">FT name</th>
			<th class="concordtable">User</th>
			<th class="concordtable">Size (bytes)</th>
			<th class="concordtable">Corpus section</th>
			<th class="concordtable">Last used</th>
			<th class="concordtable">Public?</th>
			<th class="concordtable">Delete</th>
		</tr>

	<?php
	
	$sc_mapper = get_subcorpus_name_mapper($Corpus->name);
	
	$result = do_sql_query("SELECT * FROM saved_freqtables WHERE corpus = '{$Corpus->name}' order by create_time desc");


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

	
	$return_to_url = urlencode('index.php?ui=cachedFrequencyLists' . (1 != $begin_at ? '&beginAt'.$begin_at : '') . (isset($per_page_to_pass) ? '&pp='.$per_page_to_pass : ''));
	
	
	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysqli_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	
	$name_trim_factor = strlen($Corpus->name) + 9;

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		$public_control = false;
		$row = mysqli_fetch_assoc($result);
		if (!$row)
			break;
		if ($i < $begin_at)
			continue;
		
		echo "\n\t\t<tr>\n\t\t\t<td class='concordgeneral' align='center'>$i</td>";
		echo "\n\t\t\t<td class='concordgeneral' align='center'>" , substr($row['freqtable_name'], $name_trim_factor) . '</td>';
		echo "\n\t\t\t<td class='concordgeneral' align='center'>" , $row['user'] , '</td>';
		echo "\n\t\t\t<td class='concordgeneral' align='center'>" , number_format($row['ft_size']) , '</td>';
		
		switch(true)
		{
		case empty($row['query_scope']):
			/* ought not actually to be possible: but hey */
			$qs = '-';
			break;
		case (bool) preg_match('/^\d+$/', $row['query_scope']):
			if (!empty($sc_mapper[(int)$row['query_scope']]))
				$qs = 'Subcorpus '. $sc_mapper[(int)$row['query_scope']];
			else
				$qs = 'Subcorpus id # '. $row['query_scope'];
			$public_control = true;
			break;
		case $row['query_scope'] == QueryScope::$DELETED_SUBCORPUS:
			/* this should not happen except in case of a mid-delete glitch of some kind */
			$qs = '[a deleted subcorpus]';
			break;
		default:
			$qs = $row['query_scope'];
			break;
		}
		
		echo "\n\t\t\t<td class='concordgeneral'>$qs</td>";
		
		echo "\n\t\t\t<td class='concordgeneral' align='center'>" , date(CQPWEB_UI_DATE_FORMAT, $row['create_time']), '</td>';
		
		if ( $public_control )
		{
			if ($row['public'])
				echo "\n\t\t\t", '<td class="concordgeneral" align="center"><span id="pubFl:', $row['freqtable_name'] 
					, '" class="hasToolTip"	data-tooltip="This frequency list is public on the system!">Yes</span>&nbsp;'
					, '<a id="pubFlButton:', $row['freqtable_name'], '" class="menuItem hasToolTip" href="execute.php?function=unpublicise_freqtable&args='
						, $row['freqtable_name'] , "&locationAfter=$return_to_url"
					, '" data-tooltip="Make this frequency list unpublic">[&ndash;]</a></td>'
					;
			else
				echo "\n\t\t\t", '<td class="concordgeneral" align="center"><span id="pubFl:', $row['freqtable_name'] 
					, '" class="hasToolTip" data-tooltip="This frequency list is not publicly accessible">No</span>&nbsp;'
					, '<a id="pubFlButton:', $row['freqtable_name'], '" class="menuItem hasToolTip" href="execute.php?function=publicise_freqtable&args='
						, $row['freqtable_name'] , "&locationAfter=$return_to_url"
					, '" data-tooltip="Make this frequency list public">[+]</a></td>'
					;
		}
		else
			/* only freqtables from subcorpora can be made public, not freqtables from restrictions*/
			echo "\n\t\t\t", '<td class="concordgeneral" align="center">N/A</td>';

		echo "\n\t\t\t", '<td class="concordgeneral" align="center"><a id="pubFlDel:', $row['freqtable_name'], '" class="menuItem hasToolTip" href="execute.php?function=delete_freqtable&args='
			, $row['freqtable_name'] , "&locationAfter=$return_to_url"
			, '" data-tooltip="Delete this frequency table">[x]</a></td>'
			, "\n\t\t</tr>"
			;
	}
	echo "\n";
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
	$navlinks .= '">&lt;&lt; [Newer frequency tables]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysqli_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Older frequency tables] &gt;&gt;';
	if (mysqli_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks, "\n\n";
}




function do_ui_showdbs()
{
	global $Corpus;
	global $Config;
	
	list($size) = mysqli_fetch_row(do_sql_query("select sum(db_size) from saved_dbs"));
	if (!isset($size))
		$size = 0;
	$percent = round(((float)$size / (float)$Config->db_cache_size_limit) * 100.0, 2);
	
	$subc_mapper = get_subcorpus_name_mapper($Corpus->name);
	
	?>
<table class="concordtable fullwidth">
	<tr>
		<th colspan="2" class="concordtable">
			Showing database cache for corpus <em><?php echo $Corpus->name;?></em>
		</th>
	</tr>
	<tr>
		<td colspan="2" class="concordgeneral">
			&nbsp;<br>
			The currently saved databases for all corpora have a total size of 
			<?php echo number_format((float)$size) . " bytes, $percent%"; ?>
			of the maximum cache.
			<br>&nbsp;
		</td>
	</tr>
	<tr>
		<th colspan="2" class="concordtable">
			<i>Admin controls over cached databases</i>
		</th>
	</tr>
	<tr>
		<th width="50%" class="concordtable">
			<a id="deleteDbOverflowButton" 
				class="hasToolTip" data-tooltip="This function affects &lt;strong>all&lt;/strong> corpora in the CQPweb database"
				href="execute.php?function=delete_db_overflow&locationAfter=<?php echo $return_to_url = urlencode('index.php?ui=cachedDatabases'); ?>"
			>
				Delete DB cache overflow
			</a>
		</th>
		<th width="50%" class="concordtable">
			<a id="clearDBsButton" 
				class="hasToolTip" data-tooltip="This function affects &lt;strong>all&lt;/strong> corpora in the CQPweb database"
				href="execute.php?function=clear_dbs&locationAfter=<?php echo $return_to_url; ?>"
			>
				Clear entire DB cache
			</a>
		</th>
	</tr>
</table>


<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable">No.</th>
		<th class="concordtable">User</th>
		<th class="concordtable">DB name</th>
		<th class="concordtable">DB type</th>
		<th class="concordtable">DB size</th>
		<th class="concordtable">Matching query...</th>
		<th class="concordtable">Restrictions/Subcorpus</th>
		<th class="concordtable">Last used</th>
		<th class="concordtable">Delete</th>
	</tr>


	<?php
	
	$result = do_sql_query("SELECT * FROM saved_dbs WHERE corpus = '{$Corpus->name}' order by create_time desc");

	if (isset($_GET['beginAt']))
		$begin_at = (int)$_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page_to_pass = $per_page = (int)$_GET['pp'];
	else
		$per_page = $Config->default_history_per_page;

	$return_to_url = urlencode('index.php?ui=cachedDatabases' . (1 != $begin_at ? '&beginAt='.$begin_at : '') . (isset($per_page_to_pass) ? '&pp='.$per_page_to_pass : ''));

	$toplimit = $begin_at + $per_page;
	$alt_toplimit = mysqli_num_rows($result);
	
	if (($alt_toplimit + 1) < $toplimit)
		$toplimit = $alt_toplimit + 1;
	

	for ( $i = 1 ; $i < $toplimit ; $i++ )
	{
		if (!($row = mysqli_fetch_assoc($result)))
			break;
		if ($i < $begin_at)
			continue;
		
		echo "\n<tr>"
			, "\n\t<td class='concordgeneral' align='center'>$i</td>"
			, "\n\t<td class='concordgeneral' align='center'>{$row['user']}</td>"
 			, "\n\t<td class='concordgeneral' align='center'>{$row['dbname']}</td>"
 			, "\n\t<td class='concordgeneral' align='center'>{$row['db_type']}</td>"
			, "\n\t<td class='concordgeneral' align='center'>", number_format($row['db_size']/1048576, 2), " MB</td>"
			, "\n\t<td class='concordgeneral' align='center'>{$row['cqp_query']}</td>"
			;
		
		if (empty($row['query_scope']))
			echo "\n\t<td class='concordgeneral' align='center'>-</td>";
		else if (preg_match('/^\d+$/', $row['query_scope']))
			echo "\n\t<td class='concordgeneral' align='center'>Subcorpus: {$subc_mapper[$row['query_scope']]}</td>";
		else
			echo "\n\t<td class='concordgeneral' align='center'>{$row['query_scope']}</td>";

		echo "\n\t<td class='concordgeneral' align='center'>" , date(CQPWEB_UI_DATE_FORMAT, $row['create_time']) 
			, '</td>'
			, "\n\t", '<td class="concordgeneral" align="center"><a id="delDBbutton:', $row['dbname'] , '" class="menuItem hasToolTip" href="execute.php?function=delete_db&args='
			, $row['dbname'] , "&locationAfter=$return_to_url"
			, '" data-tooltip="Delete this table">[x]</a></td>'
			, "\n</tr>"
			;
	}
	?>

</table>

	<?php
	
	//TODO use the fucniton!!
	
	$navlinks = '<table class="concordtable fullwidth"><tr><td class="basicbox" align="left';

	if ($begin_at > 1)
	{
		$new_begin_at = $begin_at - $per_page;
		if ($new_begin_at < 1)
			$new_begin_at = 1;
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$new_begin_at")));
	}
	$navlinks .= '">&lt;&lt; [Newer databases]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysqli_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array(array('beginAt', "$i + 1")));
	$navlinks .= '">[Older databases] &gt;&gt;';
	if (mysqli_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;
}


