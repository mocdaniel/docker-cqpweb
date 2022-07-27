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
 * Functions that render interface forms related to subcorpora.
 */



/** Main entry point for this set fo forms. */
function do_ui_subcorpus()
{
	if(!isset($_GET['subcorpusFunction']))
		$function = 'list_subcorpora';
	else
		$function = $_GET['subcorpusFunction'];

	if (!isset($_GET['subcorpusCreateMethod']))
		$create_method = 'metadata';
	else if (preg_match('/^manual~(.*)$/', $_GET['subcorpusCreateMethod'], $m))
	{
		$create_method = 'manual';
		$item_type = empty($m[1]) ? 'text_id' : $m[1];
	}
	else
		$create_method = $_GET['subcorpusCreateMethod'];

	/* a short circuit for returning to the subcorpus list from the "define...." dropdown */
	if ($function == 'define_subcorpus' && $create_method == 'return')
		$function = 'list_subcorpora';

	if (!isset($_GET['subcorpusBadName']))
		$badname_entered = false;
	else
	{
		$badname_entered = ($_GET['subcorpusBadName'] == 'y' ? true : false);
		/* so it doesn't get passed to other scripts... */
		unset($_GET['subcorpusBadName']);
	}

	
	/* this is fundamentally a redirecting function */
	switch($function)
	{
	case 'list_subcorpora':
		do_bitui_sc_newform(false);
		do_ui_sc_showsubcorpora();
		break;
	
	case 'view_subcorpus':
		do_ui_sc_view_and_edit();
		break;
		
	case 'copy_subcorpus':
		do_ui_sc_copy($badname_entered);
		break;

	case 'add_texts_to_subcorpus':
		do_ui_sc_addtexts();
		break;	
	
	case 'list_of_files':
		do_ui_sc_text_search_results();
		break;
	
	case 'define_subcorpus':
		do_bitui_sc_newform(true);	/* this is here to allow them to abort and select a new method */
		
		switch($create_method)
		{
		case 'query':
			do_bitui_sc_nameform($badname_entered, 2);
			do_bitui_sc_define_query();
			break;
			
		case 'query_regions':
			do_bitui_sc_nameform($badname_entered, 2);
			do_bitui_sc_define_query_regions();
			break;
			
		case 'metadata_scan':
			/* no name form in metadata scan -- the name is specified in the list page */
			do_bitui_sc_define_metadata_scan();
			break;
			
		case 'manual':
			/* manual entry can involve LONG lists of ID codes - so use post not get as form method */
			do_bitui_sc_nameform($badname_entered, 1, 'post');
			do_bitui_sc_define_id_entry($item_type);
			break;
			
		case 'invert':
			do_bitui_sc_nameform($badname_entered, 4);
			do_bitui_sc_define_invert();
			break;
			
		case 'text_id':
			/* no nameform ! */
			do_bitui_sc_define_subcorp_per_text();
			break;
			
		/* if an unrecognised method is passed, it is treated as "metadata " */
		default:
		case 'metadata':
			do_bitui_sc_nameform($badname_entered, 3);
			do_bitui_sc_define_metadata();
			break;
		}
		break;
	
	
	
	//more here
		

	default:
		/* anything else: DO NOTHING, as someone is playing silly beggars. */
		break;
	}

}




function do_bitui_sc_newform($with_return_option)
{
	global $Corpus;
	
	$idlink_options = '';
	
	foreach(get_all_xml_info($Corpus->name) as $handle => $info)
		if (METADATA_TYPE_IDLINK == $info->datatype)
			$idlink_options .= "<option value=\"manual~$handle\">Manual entry of items: " . escape_html($info->description) . "</option>";
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Create and edit subcorpora</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form action="index.php" method="get">
					<input type="hidden" name="ui" value="subcorpus">
					<input type="hidden" name="subcorpusFunction" value="define_subcorpus">
					<table>
						<tr>
	 						<td class="basicbox">
								<strong>Define new subcorpus via:</strong>
							</td>
							<td class="basicbox">
								<select name="subcorpusCreateMethod">
									<option value="metadata"     >Corpus metadata</option>
									<option value="metadata_scan">Scan text metadata</option>
									<option value="manual~text_id">Manual entry of text IDs</option>
									<?php echo $idlink_options, "\n"; ?>
									<option value="invert"       >Invert an existing subcorpus</option>
									<option value="query"        >Full texts found in a saved query</option>
									<option value="query_regions">Partial-text regions found in a saved query</option>
									<option value="text_id"      >Create a subcorpus for every text</option>
									<?php if ($with_return_option) echo "<option value=\"return\">Return to list of existing subcorpora</option>\n"; ?>
								</select>
							</td>
							<td class="basicbox">
								<input type="submit" value="Go!">
							</td>
						</tr>
					</table>
				</form>
			</td>
		</tr>
	</table>
	
	<?php

}



function do_bitui_sc_nameform($badname_entered, $colspan, $form_method = 'get')
{
	if ($colspan < 2)
		$colspan_text = '';
	else
		$colspan_text = " colspan=\"$colspan\"";

	/* this function contains the form definition for "newSubcorpusForm"; other functions refer to it */
	
	?>

	<form id="newSubcorpusForm" class="greyoutOnSubmit" action="subcorpus-act.php" method="<?php echo $form_method; ?>"></form>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable"<?php echo $colspan_text; ?>>Design a new subcorpus</th>
		</tr>
		
		<?php
		if($badname_entered)
		{
			?>
			
			<tr>
				<td class="concorderror" align="center" <?php echo $colspan_text; ?>>
					<strong>Warning:</strong>
					The name you entered, &ldquo;<?php echo escape_html($_GET['subcorpusNewName']);?>&rdquo;,
					is not allowed as a name for a subcorpus.
				</td>
			</tr>
			
			<?php
		}
		?>
		
		<tr>
			<td class="concordgeneral"<?php echo $colspan_text; ?> align="center">
				<table>
					<tr>
	 					<td class="basicbox">
							<p><strong>Please enter a name for your new subcorpus.</strong></p>
							<p>
								Names for subcorpora can only contain letters, numbers
								and the underscore character (&nbsp;_&nbsp;)!
							</p>
						</td>
						<td class="basicbox">
							<input form="newSubcorpusForm" type="text" size="50" maxlength="200" name="subcorpusNewName"
								<?php
								if(isset($_GET['subcorpusNewName']))
									echo ' value="' . escape_html($_GET['subcorpusNewName']) . '"';
								?> onKeyUp="check_c_word(this)">
						</td>
					</tr>
				</table>
			</td>	
		</tr>
		
	<?php
}



function do_bitui_sc_define_metadata()
{
	?>
	
		<tr>
			<td class="concordgeneral" colspan="3" align="center">
				&nbsp;
				<br>
				Choose the categories you want to include from the lists below. 
				<br>&nbsp;<br>
				Then either create the subcorpus directly from those categories, or view a list
				of texts to choose from.
				<br>&nbsp;
				<br>
				
				<input form="newSubcorpusForm" name="action" type="submit" value="Create subcorpus from selected categories">
				<br>&nbsp;<br>&nbsp;<br>
				<input form="newSubcorpusForm" name="action" type="submit" value="Get list of texts">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input form="newSubcorpusForm" type="reset" value="Clear form">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input form="newSubcorpusForm" name="action" type="submit" value="Cancel">
				<br>&nbsp;
				<input form="newSubcorpusForm" type="hidden" name="scriptMode" value="create_from_metadata">
				<input form="newSubcorpusForm" type="hidden" name="ui" value="subcorpus">
			</td>
		</tr>
		
	<?php

	/* build pre-insertion data from the http query string */
	$insert_r = Restriction::new_from_url($_SERVER['QUERY_STRING']); /* possibly false! */
	
	echo print_restriction_block($insert_r, 'subcorpus', 'newSubcorpusForm'), "\n";
	
	?>
	
	</table>

	<?php
}



function do_bitui_sc_define_query()
{
	global $User;
	global $Corpus;
	
	$result = do_sql_query("select query_name, save_name from saved_queries 
								where corpus = '{$Corpus->name}' 
								and   user   = '{$User->username}' 
								and   saved  = " . CACHE_STATUS_SAVED_BY_USER);
	
	if (!isset($_GET['savedQueryToScan']))
		$_GET['savedQueryToScan'] = '';

	$field_options = '';
	
	while ($o = mysqli_fetch_object($result))
	{
		$selected = ($o->query_name == $_GET['savedQueryToScan'] ? ' selected' : '');
		$field_options .= "\t\t\t\t\t\t<option value=\"{$o->query_name}\"$selected>{$o->save_name}</option>\n";
	}
	
	?>

		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<p>Select a query from your Saved Queries list using the control below.</p>
				<p class="spacer">&nbsp;</p>
				<p>
					Then either directly create a subcorpus consisting of
					<strong>the whole of every text that contains at least one result for that query</strong>,
				 	or view a list of texts to choose from.
				 </p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>

			<?php
			if (0 == mysqli_num_rows($result))
			{
				?>
				<td class="concorderror" colspan="2">
					You do not have any saved queries.
				</td>
				<?php
			}
			else
			{
				?>

				<td class="concordgeneral" width="50%">
					&nbsp;<br>
					Which Saved Query do you want to use as the basis of the subcorpus?
					<br>&nbsp;
				</td>
				<td class="concordgeneral">
					&nbsp;<br>
					<select form="newSubcorpusForm" name="savedQueryToScan">
						<?php echo $field_options; ?>
					</select>
				<br>&nbsp;

				</td>
				<?php
			}
			?>

		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				&nbsp;<br>
				<input form="newSubcorpusForm" type="hidden" name="scriptMode" value="create_from_query_texts">
				<input form="newSubcorpusForm" name="action" type="submit" value="Create subcorpus from selected query">
				<br>&nbsp;<br>&nbsp;<br>
				<input form="newSubcorpusForm" name="action" type="submit" value="Get list of texts">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input form="newSubcorpusForm" type="reset" value="Clear form">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input form="newSubcorpusForm" name="action" type="submit" value="Cancel">
				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	

	<?php

}

function do_bitui_sc_define_query_regions()
{
	/*
	 * A note.
	 * 
	 * this is a minor variant on the "query" form. But, because one can go to a "list of texts" and the other can't,
	 * it seemed better to separate them. If at any point it seems like they can be folded together, do that!
	 */
	
	global $User;
	global $Corpus;
	
	$result = do_sql_query("select query_name, save_name from saved_queries 
								where corpus = '{$Corpus->name}' and user = '{$User->username}' and saved = ".CACHE_STATUS_SAVED_BY_USER);
	
	$zero_saved_queries = (0 == mysqli_num_rows($result));
	
	if (!isset($_GET['savedQueryToScan']))
		$_GET['savedQueryToScan'] = '';
	
	$field_options = '';
	
	while ($o = mysqli_fetch_object($result))
	{
		$selected = ($o->query_name == $_GET['savedQueryToScan'] ? ' selected' : '');
		$field_options .= "\t<option value=\"{$o->query_name}\"$selected>{$o->save_name}</option>\n";
	}
	
	if (!isset($_GET['xmlAtt']))
		$_GET['xmlAtt'] = '';
	$xml_options = '';
	
	foreach(list_xml_elements($Corpus->name) as $handle => $desc)
	{
		if ($handle == 'text')
			continue;
		$selected = ($handle == $_GET['xmlAtt'] ? ' selected' : '');
		$xml_options .= "\t<option value=\"$handle\"$selected>".escape_html($desc)."</option>\n";
	}
	
	
	?>
	
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<p class="spacer">&nbsp;</p>
				<p>Select a query from your Saved Queries list using the control below.</p>
				<p>
					Then select one of the available list of <b>region types</b> (defined sub-parts within different corpus texts)
					to create a subcorpus consisting of just the regions that contain one or more hits in that saved query.
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
		
		<?php
		if ($zero_saved_queries)
		{
			?>
			
			<td class="concorderror" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p>You do not have any saved queries.</p>
				<p class="spacer">&nbsp;</p>
			</td>
			
			<?php
		}
		else
		{
			?>
			
			<td class="concordgeneral" width="50%">
				<p class="spacer">&nbsp;</p>
				<p>Which Saved Query do you want to use as the basis of the subcorpus?</p>
				<p class="spacer">&nbsp;</p>
			</td>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				&nbsp;<br>
				<select form="newSubcorpusForm" name="savedQueryToScan">
					<?php echo $field_options; ?>
				</select>
				<p class="spacer">&nbsp;</p>
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				<p>Which type of sub-text region do you want the subcorpus to be made up of?</p>
				<p class="spacer">&nbsp;</p>
			</td>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				<select form="newSubcorpusForm" name="xmlAtt">
					<?php echo $xml_options; ?>
				</select>
				<p class="spacer">&nbsp;</p>
			</td>
			
			<?php
		}
		?>
		
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				&nbsp;<br>
				<input form="newSubcorpusForm" type="hidden" name="scriptMode" value="create_from_query_regions">
				<input form="newSubcorpusForm" type="submit" name="action" value="Create subcorpus from selected query">
				<br>&nbsp;<br>&nbsp;<br>
				<input form="newSubcorpusForm" type="reset" value="Clear form">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input form="newSubcorpusForm" type="submit" name="action" value="Cancel">
				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	<?php

}




function do_bitui_sc_define_metadata_scan()
{
	global $Corpus;
	
	$in_fields = list_text_metadata_fields($Corpus->name);

	$fields = array();
	
	/* allow sort by description... */
	foreach($in_fields as $if)
		$fields[$if] = expand_text_metadata_field($Corpus->name, $if);	
	
	natcasesort($fields);
	
	/* this function generates a COMPLETE create form, which is NOT THE SAME as the newSubcorpusForm 
	 * that is declared by the title-entry function. */
	
	if (empty($fields))
	{
		?>

		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">Design a new subcorpus</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<p>
						This create-subcorpus option is not available; the corpus does not have any text metadata to scan.
					</p>
					<p class="spacer">&nbsp;</p> 
				</td>
		
			</tr>
		</table>
		
		<?php
	}
	else
	{
		$field_options = "\n";
		
		foreach($fields as $f => $l)
			$field_options .= "<option value=\"$f\">$l</option>\n";
		
		?>

		<form class="greyoutOnSubmit" action="subcorpus-act.php" method="get">
			<input type="hidden" name="scriptMode" value="create_from_metadata_scan">
			<table class="concordtable fullwidth">
				<tr>
					<th class="concordtable" colspan="2">Design a new subcorpus</th>
				</tr>
				<tr>
					<td class="concordgeneral">
						Which metadata field do you want to search?
					</td>
					<td class="concordgeneral">
						<select name="metadataFieldToScan">
							<?php echo $field_options ?>
						</select>
					</td>
				</tr>
				<tr>
					<td class="concordgeneral">
						Search for texts where this metadata field ....
					</td>
					<td class="concordgeneral">
						<select name="metadataScanType">
							<option value="begin">starts with</option>
							<option value="end">ends with</option>
							<option value="contain" selected>contains</option>
							<option value="exact">matches exactly</option>
						</select>
						&nbsp;&nbsp;
						<input type="text" name="metadataScanString" size="32">
					</td>
				</tr>
				<tr>
					<td class="concordgeneral" colspan="2" align="center">
						&nbsp;<br>
						<input type="submit" value="Get list of texts">
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<input type="reset" value="Clear form">
						&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
						<input name="action" type="submit" value="Cancel">
						<br>&nbsp;<br>
					</td>
				</tr>
			</table>
		</form>

		<?php
	}
}




/**
 * Form to create a subcorpus by manual entry of IDs for texts or some entity with an idlink.
 * @param string $item_type  By default, "text_id" to get a form for texts. It can also be an idlink
 *                           XML field (s-attribute). 
 */
function do_bitui_sc_define_id_entry($item_type = 'text_id')
{
	global $Corpus;
	
	if ('text_id' == $item_type)
	{
		$item_desc = 'texts';
		$code_desc = 'IDs of the texts';
	}
	else
	{
		if ( !($idlink = get_xml_info($Corpus->name, $item_type)) || METADATA_TYPE_IDLINK != $idlink->datatype )
			exiterror("A type of item was specified that cannot be used for manual entry of Ids.");
		
		$item_desc = ' <em>' . $idlink->description . '</em> IDs ';
		$code_desc = 'ID codes for <em>' . $idlink->description . '</em> values ';
	}
	
	if (isset($_GET['subcorpusBadIds']))
	{
		?>
		
		<tr>
			<td class="concorderror" align="center">
				<strong>Warning:</strong>
				The following <?php echo $item_desc; ?> do not exist in the corpus:
				<br>
				&ldquo;<?php echo escape_html($_GET['subcorpusBadIds']); ?>&rdquo;
			</td>
		</tr>
		
		<?php
	}
	?>
	
		<tr>
			<td class="concordgeneral" align="center">
				<p class="spacer">&nbsp;</p>
				<p>
					Enter the <?php echo $code_desc; ?> you wish to combine to a subcorpus 
					(use commas or spaces to separate the individual IDs): 
				</p>
				<p class="spacer">&nbsp;</p>
				
				<input form="newSubcorpusForm" type="hidden" name="scriptMode" value="create_from_manual">
				<input form="newSubcorpusForm" type="hidden" name="idType" value="<?php echo $item_type; ?>">
				
				<textarea form="newSubcorpusForm" name="subcorpusListOfIds" rows="5" cols="58"><?php
					if (isset($_GET['subcorpusListOfIds']))
						echo preg_replace('/[^\w ,]/', '', $_GET['subcorpusListOfIds']);
				?></textarea>
				
				<p class="spacer">&nbsp;</p>
				
				<p>
					<input form="newSubcorpusForm" type="submit" value="Create subcorpus">
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input form="newSubcorpusForm" type="reset"  value="Clear form">
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input form="newSubcorpusForm" type="submit" name="action" value="Cancel">
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>

	</table>
	
	<?php

}


function do_bitui_sc_define_invert()
{
	global $User;
	global $Corpus;
	
	?>
	
		<tr>
			<td class="concordgeneral" colspan="4" align="center">
				&nbsp;
				<br>
				When you "invert" a subcorpus, you create a new subcorpus containing all texts from
				the corpus, <strong>except</strong> those in the subcorpus you selected to invert. 
				<br>&nbsp;<br>
				Choose the subcorpus you want to invert from the list below. 
				<br>&nbsp;<br>

			</td>
		</tr>
		
		<tr>
			<th class="concordtable">Select</th>
			<th class="concordtable">Name of subcorpus</th>
			<th class="concordtable">Size</th>
			<th class="concordtable">Size in words</th>
		</tr>

		<?php
		
		/* was a specified subcorpus-to-tick passed in? */
		$specified_invert_target = ( isset($_GET['subcorpusToInvert']) ? (int)$_GET['subcorpusToInvert'] : -1);
		/* -1 will never match any ID because they are unsigned ints in MySQL */
		
		$result = do_sql_query("select * from saved_subcorpora where corpus = '{$Corpus->name}' and user = '{$User->username}' order by name");
		
		if (0 == mysqli_num_rows($result))
			echo '<tr><td class="concordgrey" colspan="4" align="center">&nbsp;<br>No subcorpora were found.<br>&nbsp;</td></tr>', "\n";
		else
		{
		
			while (false !== ($sc = Subcorpus::new_from_db_result($result)))
				echo "\n\t\t<tr>"
					, '<td class="concordgrey" align="center">'
						, '<input form="newSubcorpusForm" id="subcorpusToInvert:', $sc->id , '" name="subcorpusToInvert" type="radio" '
						, 'value="' , $sc->id , '"'
						, ( $specified_invert_target == $sc->id ? ' checked' : '' ) 
					, '></td>'
					, '<td class="concordgeneral"><label for="subcorpusToInvert:', $sc->id , '">'
						, ($sc->name == '--last_restrictions' ? 'Last restrictions' : $sc->name)
					, '</label></td>'
					, '<td class="concordgeneral" align="center">' , $sc->print_size_items(), '</td>'
					, '<td class="concordgeneral" align="center">' , $sc->print_size_tokens(), '</td>'
					, "</tr>\n"
					;
			?>
	
			<tr>
				<td class="concordgeneral" colspan="4" align="center">
					<input form="newSubcorpusForm" type="hidden" name="scriptMode" value="create_inverted">
					<br>&nbsp;<br>
					<input form="newSubcorpusForm" type="submit" value="Create inverted subcorpus">
					<br>&nbsp;<br>&nbsp;<br>
					<input form="newSubcorpusForm" type="reset" value="Clear form">
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input form="newSubcorpusForm" name="action" type="submit" value="Cancel">
					<br>&nbsp;<br>
				</td>
			</tr>			
			
			
			<?php
		}
		?>
	
	</table>
	
	<?php
}


function do_bitui_sc_define_subcorp_per_text()
{
	/*
	 * Note that this function DOESN'T require a name form -- names are auto-generated.
	 * So, it spits out a complete form.
	 */
	?>
	
	<form class="greyoutOnSubmit" action="subcorpus-act.php" method="get">
		<input type="hidden" name="scriptMode" value="create_sc_per_text">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">Design a new subcorpus</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					&nbsp;
					<br>
					Click below to turn every text into a subcorpus. 
					<br>&nbsp;<br>
					This function is only available for corpora with 100 or less texts. 
					<br>&nbsp;<br>
	
					<br>
					
					<input type="submit" value="Create one subcorpus per text">
					<br>&nbsp;<br>&nbsp;<br>
					<input type="reset" value="Clear form">
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel"><?php // TODO -- clearer allroiund if this is just abnother form. likewise others like this . ?>
					<br>&nbsp;<br>
				</td>
			</tr>
		</table>
	</form>
	
	<?php
}



/**
 * Renders list of existing subcorpora.
 */
function do_ui_sc_showsubcorpora()
{
	global $User;
	global $Corpus;

	$show_owner = false;
	if ($User->is_admin())
		$show_owner = (bool)($_GET['showOwner'] ?? false);

	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="<?php echo $show_owner ? 8 : 7; ?>">Existing subcorpora</th>
		</tr>
		
		<?php
		if ($User->is_admin())
		{
			if ($show_owner)
			{
				$switch_value = 0;
				$switch_button_text = "Show only your subcorpora";
			}
			else
			{
				$switch_value = 1;
				$switch_button_text = "Show all users' subcorpora";
			}
			?>
				
			<tr>
				<td class="concordgrey" colspan="<?php echo $show_owner ? 8 : 7; ?>" align="center">
					<p class="spacer">&nbsp;</p>
					<form action="index.php" method="get">
						<input type="hidden" name="ui" value="subcorpus">
						<input type="hidden" name="showOwner" value="<?php echo $switch_value; ?>">
						<input type="submit" value="<?php echo $switch_button_text; ?>">
					</form>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
			<?php
		}
		?>
			
		<tr>
			<?php echo $show_owner ? '<th class="concordtable">User</th>' : '', "\n"; ?>
			<th class="concordtable">Name of subcorpus</th>
			<th class="concordtable">Size</th>
			<th class="concordtable">Size in words</th>
			<th class="concordtable">Frequency list</th>
			<th class="concordtable" colspan="2">Actions</th>
			<th class="concordtable">Delete</th>
		</tr>
		
		<?php

		if ($show_owner)
			$subcorpora_with_freqtables = list_freqtabled_subcorpora_for_admin(true,true,true);
		else
			$subcorpora_with_freqtables = list_freqtabled_subcorpora($User->username, true, true, true);
// FIXME: If the adminn user is looking at someone else's subcorpora, t


		$user_clause = ($show_owner ? '' : "and user = '{$User->username}'");
		$result = do_sql_query("select * from saved_subcorpora where corpus = '{$Corpus->name}' $user_clause order by name");
		
		while (false !== ($sc = Subcorpus::new_from_db_result($result))) 
		{
			echo '<tr>';
			
			if ($show_owner)
				echo '<td class="concordgeneral">', $sc->user, '</td>';
			
			if ('--last_restrictions' == $sc->name)
				echo '<td class="concordgeneral">Last restrictions</td>';
			else
				echo '<td class="concordgeneral">'
					, '<a id="scVw:', $sc->id, '" class="hasToolTip"'
					, ' href="index.php?ui=subcorpus&subcorpusFunction=view_subcorpus&subcorpusToView=' , $sc->id
					, '" data-tooltip="View (or edit) the composition  of this subcorpus">'
					, $sc->name
					, '</a></td>'
					;
			
			echo '<td class="concordgeneral" align="center">' , $sc->print_size_items() ,  '</td>';
			
			echo '<td class="concordgeneral" align="center">' , $sc->print_size_tokens() , '</td>';
			
			echo '<td class="concordgeneral" align="center">';
			
			if ('--last_restrictions' == $sc->name)
				echo 'N/A';
			else if (in_array($sc->id, $subcorpora_with_freqtables))
				/* freq tables exist for this subcorpus, ergo... */
				echo 'Available';
			else
			{
				if ($sc->size_tokens() >= $User->max_freqlist())
					echo '<a id="scComp:', $sc->id, '" class="menuItem hasToolTip"'
						, ' data-tooltip="Cannot compile frequency tables for this subcorpus, as it is too big (your limit: <strong>'
						, number_format($User->max_freqlist())
						, '</strong> tokens)">Cannot compile</a>'
						;
				else
					echo '<a id="scComp:', $sc->id, '" class="menuItem hasToolTip greyoutOnSubmit"'
						, ' href="subcorpus-act.php?scriptMode=compile_freqtable&compileSubcorpus=' 
						, $sc->id
						, '" data-tooltip="Compile frequency tables for subcorpus <strong>'
						, $sc->name
						, '</strong>, allowing calculation of collocations and keywords">[Compile]</a>'
						;
			}
			echo '</td>';
			
			echo '<td class="concordgeneral" align="center">'
				, '<a id="scCopy:', $sc->id, '" class="menuItem hasToolTip" ' 
				, 'href="index.php?ui=subcorpus&subcorpusFunction=copy_subcorpus&subcorpusToCopy=' 
				, $sc->id
				, '" data-tooltip="Copy this subcorpus">'   
				, '[copy]</a></td>'
				;
	
			echo '<td class="concordgeneral" align="center">'
				, '<a id="scAdd:', $sc->id, '" class="menuItem hasToolTip" ' 
				, 'href="index.php?ui=subcorpus&subcorpusFunction=add_texts_to_subcorpus&subcorpusToAddTo=' 
				, $sc->id
				, '" data-tooltip="Add texts to this subcorpus">'
				, '[add]</a></td>'
				;

			echo '<td class="concordgeneral" align="center">'
				, '<a id="scDel:', $sc->id, '" class="menuItem hasToolTip greyoutOnSubmit" ' 
				, 'href="subcorpus-act.php?scriptMode=delete&subcorpusToDelete='
				, $sc->id 
				, '" data-tooltip="Delete this subcorpus">'
				, '[x]</a></td>'
				;
				
			echo "</tr>\n";
		}
		if (0 == mysqli_num_rows($result))
			echo '<tr><td class="concordgrey" colspan="7" align="center">&nbsp;<br>No subcorpora were found.<br>&nbsp;</td></tr>', "\n";	
		?>
		
	</table>
	
	<?php
}



/**
 * Renders the UI for copying a subcorpus.
 * 
 * @param bool $badname_entered
 */
function do_ui_sc_copy($badname_entered)
{
	if (!isset($_GET['subcorpusToCopy']))
		exiterror('No subcorpus specified to copy!');	

	$copyme = Subcorpus::new_from_id( (int) $_GET['subcorpusToCopy']);
	
	if (false === $copyme)
		exiterror('Subcorpus does not exist: cannot copy it.')
	
	?>
	<form class="greyoutOnSubmit" action="subcorpus-act.php" method="get">
		<input type="hidden" name="scriptMode" value="copy">
		<?php echo url_printinputs(array( array('subcorpusNewName', '') )); ?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
				<?php
				if ($copyme->name == '--last_restrictions')
					echo "Copying last restrictions used to saved subcorpus";
				else
					echo "Copying subcorpus <em>{$copyme->name}</em>"; 
				?>
				</th>
			</tr>
			
			<?php
			
			if ($badname_entered)
			{
				?>
				
				<tr>
					<td class="concorderror" align="center">
						<strong>Warning:</strong>
						The name you entered, &ldquo;<?php echo escape_html($_GET['subcorpusNewName']);?>&rdquo;,
						is not allowed as a name for a subcorpus.
					</td>
				</tr>
				
				<?php	
			}
			
			?>
			<tr>
				<td class="concordgeneral" align="center">
					&nbsp;<br>
					<table>
						<tr>
		 					<td class="basicbox">
								<strong>What name do you want to give to the copied subcorpus?</strong>
								<br>
								Names for subcorpora can only contain letters, numbers
								and the underscore character (&nbsp;_&nbsp;)!
							</td>
							<td class="basicbox">
								<input type ="text" size="50" maxlength="200" name="subcorpusNewName"
									<?php
									if(isset($_GET['subcorpusNewName']))
										echo ' value="' , escape_html($_GET['subcorpusNewName']) , '"';
									?>
								onKeyUp="check_c_word(this)">
							</td>
						</tr>
					</table>
					&nbsp;<br>
					<input type="submit" name="action" value="Copy subcorpus">
					<input type="submit" name="action" value="Cancel">
					&nbsp;&nbsp;&nbsp;&nbsp;
					<br>&nbsp;
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>

	<?php

}



//
//
//
//
//
//TODO   Update this gizmo for the non-text-based subcorpora.
//
//
//
//
function do_ui_sc_addtexts()
{
	if (!isset($_GET['subcorpusToAddTo']))
		exiterror('No subcorpus specified to add to!');
	
	$subcorpus = Subcorpus::new_from_id($_GET['subcorpusToAddTo']);
	
	if (false === $subcorpus)
		exiterror('The subcorpus you want to make additions to does not seem to exist!');

	?>
	<form class="greyoutOnSubmit" action="subcorpus-act.php" method="get">
		<input type="hidden" name="scriptMode" value="add_texts">
		<input type="hidden" name="subcorpusToAddTo" value="<?php echo $subcorpus->id; ?>">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Adding texts to subcorpus &ldquo;<?php echo $subcorpus->name; ?>&rdquo;
				</th>
			</tr>
			<?php

//TODO, this is a safety valve for 3.2.9. how will this work in future???
			$check = '';
			$subcorpus->size_items($check);
			if ('text' != $check)
				exiterror("You can't add texts to this subcorpus because it does not consist of a whole number of texts.");
// end TODO
	
			if (isset($_GET['subcorpusBadIds']))
			{
				?>
				
				<tr>
					<td class="concorderror" align="center">
						<strong>Warning:</strong>
						you entered the following texts, but they do not exist in the corpus : 
						<br>
						&ldquo;<?php echo escape_html($_GET['subcorpusBadIds']); ?>&rdquo;
					</td>
				</tr>
				
				<?php
			}
			?>
			
			<tr>
				<td class="concordgeneral" align="center">
					&nbsp;
					<br>
					Enter the IDs of the texts you wish to add to this subcorpus 
					(use commas or spaces to separate the individual ID codes): 
					<br>&nbsp;
					<br>
					<textarea name="subcorpusListOfIds" rows="5" cols="58"><?php
						if (isset($_GET['subcorpusListOfIds']))
							echo preg_replace('/[^\w ,]/', '', $_GET['subcorpusListOfIds']);
					?></textarea>
					<br>&nbsp;<br>
					
					<input type="submit" value="Add texts to subcorpus">
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input type="reset" value="Clear form">
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel">
					<br>&nbsp;<br>
				</td>
			</tr>
		</table>
	</form>

	<?php

}

// temp function for no-edit subcorpora
function do_ui_sc_no_view_possible()
{
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">Create and edit subcorpora</th>
		</tr>
			<tr>
				<td class="concordgrey" colspan="5" align="center">
					<strong>
						&nbsp;<br>
						You can't edit this subcorpus because it does not consist of a whole number of complete texts.
						<br>
						Editing subcorpora of sub-text regions may be possible in later versions of CQPweb!
					</strong>
					<br>&nbsp;
				</td>
			</tr>
	</table>

	<?php
}



//
//Big TODO: update this to display -- something -- for all kinds of corpora and to allow editing for as many as possible. 
//
//
/**
 * Renders a page which displays the content of a specified subcorpus, and includes forms for editing.
 */
function do_ui_sc_view_and_edit()
{
	global $Corpus;
	
	if(empty($_GET['subcorpusToView']))
		exiterror('No subcorpus was specified!');
	
	if (false === ($subcorpus = Subcorpus::new_from_id((int) $_GET['subcorpusToView'])))
		exiterror('The specified subcorpus could not be found on the system.');

	if (!$subcorpus->owned_by_user())
		exiterror("You cannot access a corpus that is not owned by your user account.");
	
//TODO, this is a safety valve for 3.2.9....... how will this work in future???
	$check = '';
	$subcorpus->size_items($check);
	if ('text' != $check)
	{
		do_ui_sc_no_view_possible();
		return;
	}
// end todo. Note, this is a copy of code in do_ui_sc_addtexts() which protects us from the same issue. 
// FIXME in roderto avoid issues with saved queries etc, altered-content subcorpora ought really to be saved with a new integer ID. 

	if (!isset($_GET['subcorpusFieldToShow']))
		$show_field = $Corpus->primary_classification_field;
	else
		$show_field = cqpweb_handle_enforce($_GET['subcorpusFieldToShow']);

	if (empty($show_field))
	{
		$show_field = false;
		$catdescs = false;
		$field_options = "\n<option selected></option>";
	}
	else
	{
		if (metadata_field_is_classification($Corpus->name, $show_field))
			$catdescs = array_map("escape_html", list_text_metadata_category_descriptions($Corpus->name, $show_field));
		else
			$catdescs = false;
		$field_options = "\n";
	}


	
	foreach(list_text_metadata_fields($Corpus->name) as $f)
	{
		$l = expand_text_metadata_field($Corpus->name, $f);
		$selected = ($f == $show_field ? ' selected' : '');
		$field_options .= "<option value=\"$f\"$selected>$l</option>\n";
	}
	
	
	$text_list = $subcorpus->get_item_list();

	$i = 1;
	

	
	
	// TODO add a control bar and limit the number of texts per page, like BNCweb does; (longterm -- low priority, not many people use this tool)

	
	
	//TODO jQuery??? separate file?? THIS DOES NOT SEEM TO BE FINISHED YET.
	// TODO rather than re-submit everything, why not create all columns, hide most, then use this to change which is shown?
	// in which case it should deffo go in a separate file.
	
	// TODO this DOES NOT WORK because I turned the "submit" into a button to stop delete-selections being wiped.
	//TODO needs finishing off at some point! In my modified idea for it, no need to change the action of the form:
	// simply trigger a "hide all, show one" function which would also be called on window ready. 
	?>
	<script>
	function subcorpusAlterForm()
	{
//nb 2016-04-25 -- OLD VERSION REACTIVATED WHILE NEW VERSION IS PENDING. In spite of rambly comments above.
		document.getElementById('subcorpusTextListMainForm').action = "index.php";
		document.getElementById('inputSubcorpusToRemoveFrom').name = "subcorpusToView";
		document.getElementById('inputSubcorpusToRemoveFrom').value = "<?php echo $subcorpus->id; ?>";
		// is the line above needed???
		document.getElementById('inputScriptMode').name = "subcorpusFunction";
		document.getElementById('inputScriptMode').value = "view_subcorpus";
		document.getElementById('subcorpusTextListMainForm').submit();
	}
	</script>
	<form id="subcorpusTextListMainForm" action="subcorpus-act.php" method="get">
		<input id="inputUi"                    type="hidden" name="ui" value="subcorpus">
		<input id="inputScriptMode"            type="hidden" name="scriptMode" value="remove_texts">
		<input id="inputSubcorpusToRemoveFrom" type="hidden" name="subcorpusToRemoveFrom" value="<?php echo $subcorpus->id; ?>">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="5">Create and edit subcorpora</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="5" align="center">
					<strong>
						&nbsp;<br>
						Viewing subcorpus
						<?php
						echo "<em>{$subcorpus->name}</em>: this subcorpus consists of "
							, $subcorpus->print_size_items() , " with a total of "
							, $subcorpus->print_size_tokens() , " words.";
						?>
						<br>
					</strong>
					&nbsp;<br>
					<input type="submit" value="Delete marked texts from subcorpus">
					&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
					<input name="action" type="submit" value="Cancel"> 
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<th class="concordtable">No.</th>
				<th class="concordtable">Text</th>
					<th class="concordtable">
						Showing:
						<select name="subcorpusFieldToShow">
							<?php echo $field_options; ?>
						</select>
						<input type="button" onclick="subcorpusAlterForm()" value="Show">
					</th>
				<th class="concordtable">Size in words</th>
				<th class="concordtable">Delete</th>
			</tr>
			
			<?php
			
			foreach($text_list as $text)
			{
				$meta = metadata_of_text($Corpus->name, $text);
				
				echo '<tr>';
				
				/* number */
				echo '<td class="concordgrey" align="right"><strong>' , $i++ , '</strong></td>';
				
				/* text id with metadata link */
				echo '<td class="concordgeneral"><strong>'
					, '<a ' , print_text_metadata_tooltip($text) , ' href="textmeta.php?text=' , $text , '">'
					, $text
					, '</a></strong></td>'
		 			;
					
				/* primary classification (or whatever metadata feature has been selected) */
				echo '<td class="concordgeneral">'
					, ($show_field === false
							? '&nbsp;'
							: ($catdescs !== false ? $catdescs[$meta[$show_field]] : $meta[$show_field])
							)
					, '</td>'
		 			;
				
		
				/* number of words in file */
				echo '<td class="concordgeneral" align="center">'
					, number_format((float)$meta['words'])
					, '</td>'
		 			;
					
				/* tickbox for delete */
				echo '<td class="concordgrey" align="center">'
					, '<input type="checkbox" name="dT_' , $text , '" value="1">'
					, '</td>'
					;
				
				echo "</tr>\n";
			}
			?>
		</table>
	</form>
	<?php

}



/**
 * Renders the form for selecting texts from a list of search results to include in a subcorpus. 
 */
function do_ui_sc_text_search_results()
{
	global $User;
	global $Corpus;

	/* the form that refers to this one stashes a longvalue. */
	list ($list_of_texts_to_show_in_form, $header_cell_text, $field_to_show) 
		= explode('~~~~~', longvalue_retrieve($_GET['listOfFilesLongValueId']));
	if (empty($field_to_show))
		$field_to_show = $Corpus->primary_classification_field;

	$field_to_show_desc = expand_text_metadata_field($Corpus->name, $field_to_show);
	
	
	$form_full_list = str_replace(' ', '|', $list_of_texts_to_show_in_form);
	
	$form_full_list_idcode = longvalue_store($form_full_list);
	
	
	$text_list = ( empty($list_of_texts_to_show_in_form) ? NULL : explode(' ', $list_of_texts_to_show_in_form) );
	
	/* note: we probably should use the Subcorpus object here, but we only need the id and name, so going straight to the DB is acceptable-ish. */
	$sql = "select id, name from saved_subcorpora where corpus = '{$Corpus->name}' and user = '{$User->username}' order by name";
	$result = do_sql_query($sql);
	$subcorpus_options = "\n";
	while ($o= mysqli_fetch_object($result))
		if ($o->name != '--last_restrictions')
			$subcorpus_options .= '<option value="' . $o->id . '">Add to ' . $o->name . '</option>';
	$subcorpus_options .= "\n";


	$i = 1;

	?>

	<form action="subcorpus-act.php" method="get">
		<input type="hidden" name="scriptMode" value="process_from_text_list">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="5">Create and edit subcorpora</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="5" align="center">
					<strong>
						&nbsp;<br>
						<?php echo $header_cell_text; ?>
						<br>&nbsp;<br>
					</strong>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="5" align="center">
					<table class="fullwidth">
						<tr>
							<td class="basicbox">
								Add texts to subcorpus...
							</td>
							<td class="basicbox" align="center">
								<select name="subcorpusToAddTo">
									<option value="!__NEW">Use specified name for new subcorpus:</option>
									<?php echo $subcorpus_options, "\n"; ?>
								</select>
							</td>
							<td class="basicbox">
								&nbsp;<br>
								New subcorpus: <input type="text" name="subcorpusNewName">
								<br>
								(may only contain letters, numbers and underscore)
							</td>
							<td class="basicbox">
								<input type="checkbox" name="processTextListAddAll" 
									value="<?php echo $form_full_list_idcode; ?>" 
								>
								include all texts
							</td>
							<td class="basicbox">
								<input type="submit" value="Add texts">
								<br>&nbsp;<br>
								<input type="submit" name="action" value="Cancel">
							</td>
						</tr>
					</table>
				</td>
			<tr>
				<th class="concordtable">No.</th>		
				<th class="concordtable">Text</th>		
				<th class="concordtable"><?php echo escape_html($field_to_show_desc);?></th>		
				<th class="concordtable">Size in words</th>		
				<th class="concordtable">Include in subcorpus</th>
			</tr>

	<?php

	if (! empty($text_list))
	{
		foreach($text_list as $text)
		{
			$meta = metadata_of_text($Corpus->name, $text); 
			
			echo "\n\t\t\t<tr>"
			
				/* number */
				, '<td class="concordgrey" align="right"><strong>' , $i++ , '</strong></td>'
				
				/* text id with metadata link */
				, '<td class="concordgeneral">'
					, '<strong><a class="hasToolTip" id="link-' . $text . '"'
 					, print_text_metadata_tooltip($text), ' href="textmeta.php?text=', $text, '">', $text
 					, '</a></strong>'
				, '</td>'
				
				/* primary (or other) classification */
				, '<td class="concordgeneral">'
					, (empty($field_to_show) ? '' : $meta[$field_to_show])
				, '</td>'
	
				/* number of words in file */
				, '<td class="concordgeneral" align="center">'
					, number_format((float)$meta['words'])
				, '</td>'
				
				/* tickbox for add */
				, '<td class="concordgrey" align="center">'
					, '<input type="checkbox" name="aT_' , $text , '" value="1">'
				, '</td>'
	
			 	, "</tr>"
 				;
		}
	}
	else
	{
		?>
		
			<tr>
				<td class="concordgrey" colspan="5" align="center">
					&nbsp;<br>
					No texts found.
					<br>&nbsp;
				</td>
			</tr>
		
		<?php	
	}
	?>

		</table>
	</form>

	<?php
	
}


