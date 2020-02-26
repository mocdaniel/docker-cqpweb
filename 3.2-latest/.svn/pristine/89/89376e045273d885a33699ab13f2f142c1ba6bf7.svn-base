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
 *
 * @file
 * 
 * Function library for adminhome interface screen.
 *
 */

/** Dummy function wrapping the "showcorpora" call */
function do_adm_ui_showcorpora_system_admhome()
{
	do_ui_showcorpora('admin_systemcorpora');
}

/** Dummy function wrapping the "showcorpora" call */
function do_adm_ui_showcorpora_user_admhome()
{
	do_ui_showcorpora('admin_usercorpora');
}



function do_adm_ui_installcorpus_indexed()
{
	global $Config;

	?>
	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="installCorpusIndexed">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="2" class="concordtable">
					Install a corpus pre-indexed in CWB
				</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					&nbsp;<br>
					<a href="index.php?ui=installCorpus">
						Click here to install a completely new corpus from files in the upload area.
					</a>
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Specify the corpus &ldquo;name&rdquo; (limited to lowercase/digits/underscore)
					<br>(will be used in the web address and as the CWB/SQL short-handle)
				</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_name" onKeyUp="check_c_word(this)">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Enter the full descriptive name of the corpus</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_description">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" rowspan="2">Where is the registry file?</td>
				<td class="concordgeneral">
					<input type="radio" id="corpus_useDefaultRegistry:1" name="corpus_useDefaultRegistry" value="1" checked>
					<label for="corpus_useDefaultRegistry:1">
						In CQPweb's usual registry directory 
					</label>
					<a id="useDefaultRegistry:info" class="menuItem hasToolTip" data-tooltip="<?php echo escape_html($Config->dir->registry); ?>">
						[?]
					</a>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<input type="radio" id="corpus_useDefaultRegistry:0" name="corpus_useDefaultRegistry" value="0">
					<label for="corpus_useDefaultRegistry:0">
						In the directory specified here:
					</label>
					<br>
					<input type="text" name="corpus_cwb_registry_folder">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<label for="corpus_scriptIsR2L:1">
						Tick here if the main script in the corpus is right-to-left
					</label>
				</td>
				<td class="concordgeneral">
					<input type="checkbox" id="corpus_scriptIsR2L:1" name="corpus_scriptIsR2L" value="1">
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					<p class="spacer">&nbsp;</p>
					<p>
						P-attributes (annotation) are read automatically from the registry file.
						Use "Manage annotation" to add descriptions, tagset names/links, etc.
					</p>
					<p class="spacer">&nbsp;</p>
					<p>
						S-attributes (XML) are also read automatically from the registry file.
						Use "Manage XML" to add descriptions, change the datatype of an XML attribute, etc.
					</p> 
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>

		<?php do_adm_bitui_installcorpus_stylesheetrows(); ?>

		</table>

		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">Install corpus</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<input type="submit" value="Install corpus with settings above">
					<br>&nbsp;<br>
					<input type="reset" value="Clear this form">
				</td>
			</tr>
		</table>
	</form>

	<?php
}


/**
 * Returns string containing a form chunk that has in it the P-attribute definition form.
 * 
 * Works in tandem with clientside JS functions, q.v.
 *   
 * @param  string $input_name_base  Prefix for HTML-form-field "names".
 * @param  int    $init_n           Initial number of rows to print. Minimum is 1. Default is 6.
 * @return string                   A string containing the HTML of the form-chunk.

 */
function print_embiggenable_p_attribute_form($input_name_base, $init_n = 6)
{
	$html = <<<END

			<tr id="p_att_row_1">
				<td class="concordgrey" align="center">Primary?</td>
				<td class="concordgrey" align="center">Handle</td>
				<td class="concordgrey" align="center">Description</td>
				<td class="concordgrey" align="center">Tagset</td>
				<td class="concordgrey" align="center">External URL</td>
				<td class="concordgrey" align="center">Feature set?</td>
			</tr>
END;
	for ($q = 1 ; $q <= $init_n; $q++)
	{
		$html .= "
			<tr>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"radio\" name=\"{$input_name_base}PPrimary\" value=\"$q\">
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"15\" name=\"{$input_name_base}PHandle$q\" onKeyUp=\"check_c_word(this)\">
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"150\" name=\"{$input_name_base}PDesc$q\">
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"150\" name=\"{$input_name_base}PTagset$q\">
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"150\" name=\"{$input_name_base}Purl$q\">
				</td>
				<td align=\"center\" class=\"concordgeneral\">
					<input type=\"checkbox\" name=\"{$input_name_base}Pfs$q\"  value=\"1\">
				</td>
			</tr>\n";
	}
	$html .= <<<END
			<tr id="p_embiggen_button_row">
				<td colspan="6" class="concordgrey" align="center">
					&nbsp;<br>
					<a onClick="add_p_attribute_row()" class="menuItem">[Embiggen form]</a>
					<br>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="pNumRows" id="pNumRows" value="6">
			<input type="hidden" name="inputNameBaseP" id="inputNameBaseP" value="$input_name_base">
END;

	return $html;
}


/**
 * Creates a chunk of an HTML form containing a dynamically-growable form for use in XML templates
 * and installation of corpus XML.
 *
 * Works in tandem with two clientside JS functions, q.v.
 *
 * @param  string $input_name_base  Prefix for HTML-form-field "names".
 * @param  int    $init_n           Initial number of rows to print. Minimum is 1. Default is 4.
 * @return string                   A string containing the HTML of the form-chunk.
 */
function print_embiggenable_s_attribute_form($input_name_base, $init_n = 4)
{
	/* some chunks using constants that we need to create first, then embed. */
	$text_id_type = METADATA_TYPE_UNIQUE_ID;
	/* note, we *manully* list the datatypes, rather than using some sort of array. This may merit changing later. */
	$optblock = '
				<option value="' . METADATA_TYPE_FREETEXT . '" selected>Free text</option>
				<option value="' . METADATA_TYPE_CLASSIFICATION . '">Classification</option>
				<option value="' . METADATA_TYPE_IDLINK . '">ID link</option>
				<option value="' . METADATA_TYPE_UNIQUE_ID . '">Unique ID</option>
		';
	// TODO use $Config->metadata_type_descriptions once we allow DATE too.

	/* now compose the main returnable */ 
	$html = <<<END

			<!-- hidden block of select options for the attribute datatypes -->
			<select id="getDataTypeOptionsFromHere" style="display:none">
				$optblock
			</select>
			<!-- the above is never rendered, but is used as a template by the clientside JavaScript -->

			<tr id="s_att_row_1">
				<td rowspan="2" class="concordgrey" align="center">Element tag</td>
				<td rowspan="2" class="concordgrey" align="center">Description</td>
				<td colspan="3" class="concordgrey" align="center">Attributes</td>
			</tr>
			<tr>
				<td class="concordgrey" align="center">Att tag</td>
				<td class="concordgrey" align="center">Description</td>
				<td class="concordgrey" align="center">Datatype</td>
			</tr>

			<!-- first content row is the text element: special -->
			<tr id="row_for_S_1">
				<td id="cell{$input_name_base}SHandle1" rowspan="2" align="center" class="concordgeneral">
					<i>text</i>
				</td>
				<td id="cell{$input_name_base}SDesc1" rowspan="2" align="center" class="concordgeneral">
					<i>The text division markers<br>are automatic and compulsory.</i>
				</td>
				<td align="center" class="concordgeneral">
					<i>id</i>
				</td>
				<td colspan="2" align="center" class="concordgeneral">
					<i>automatic and compulsory</i>
				</td>
			</tr>
			<tr>
				<td id="addXmlAttributeButtonCellFor1" colspan="3" align="center" class="concordgeneral">
					<a class="menuItem" onClick="add_xml_attribute_to_s(1)">[Add attribute slot]</a>
				</td>
			</tr>
			<input type="hidden" name="nOfAttsFor{$input_name_base}Xml1" id="nOfAttsFor{$input_name_base}Xml1" value="1">

			<!-- hidden variables that == the inputs that **would** exist for text/text_id -->
			<input type="hidden" name="{$input_name_base}SHandle1" value="text">
			<input type="hidden" name="{$input_name_base}SDesc1" value="Text">
			<input type="hidden" name="{$input_name_base}SHandleAtt1_1" value="id">
			<input type="hidden" name="{$input_name_base}SDescAtt1_1" value="Text ID">
			<input type="hidden" name="{$input_name_base}STypeAtt1_1" value="$text_id_type">
			<!-- end special variant row for text/text_id -->

END;


	for ($q = 2 ; $q <= $init_n; $q++)
	{
		$html .= "
			<tr id=\"row_for_S_$q\">
				<td id=\"cell{$input_name_base}SHandle$q\" align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"64\" name=\"{$input_name_base}SHandle$q\" onKeyUp=\"check_c_word(this)\">
				</td>
				<td id=\"cell{$input_name_base}SDesc$q\" align=\"center\" class=\"concordgeneral\">
					<input type=\"text\" maxlength=\"255\" name=\"{$input_name_base}SDesc$q\">
				</td>
				<td id=\"addXmlAttributeButtonCellFor$q\" colspan=\"3\" align=\"center\" class=\"concordgeneral\">
					<a class=\"menuItem\" onClick=\"add_xml_attribute_to_s($q)\">[Add attribute slot]</a>
				</td>
			</tr>
			<input type=\"hidden\" name=\"nOfAttsFor{$input_name_base}Xml$q\" id=\"nOfAttsFor{$input_name_base}Xml$q\" value=\"0\">\n";
	}

	$html .= <<<END

			<tr id="s_embiggen_button_row">
				<td colspan="5" class="concordgrey" align="center">
					&nbsp;<br>
					<a onClick="add_s_attribute_row()" class="menuItem">[Embiggen form]</a>
					<br>&nbsp;
				</td>
			</tr>
			<input type="hidden" name="sNumRows" id="sNumRows" value="$init_n">
			<input type="hidden" name="inputNameBaseS" id="inputNameBaseS" value="$input_name_base">

END;

	return $html;
}



function do_adm_ui_installcorpus_unindexed()
{
	global $Config;
	
	?>

	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="installCorpus">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="2" class="concordtable">
					Install new corpus
				</th>
			</tr>
			<tr>
				<td colspan="2" class="concordgrey">
					&nbsp;<br>
					<a href="index.php?ui=installCorpusIndexed">
						Click here to install a corpus you have already indexed in CWB.
					</a>
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Specify the corpus &ldquo;name&rdquo; (limited to lowercase/digits/underscore)
					<br>(will be used in the web address and as the CWB/SQL short-handle)
				</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_name" onKeyUp="check_c_word(this)">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Enter the full descriptive name of the corpus</td>
				<td class="concordgeneral">
					<input type="text" name="corpus_description">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">Tick here if the main script in the corpus is right-to-left</td>
				<td class="concordgeneral">
					<input type="checkbox" name="corpus_scriptIsR2L" value="1">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Select the character encoding of your input files:
				</td>
				<td class="concordgeneral">
					<select name="corpus_charset">
						<option value="utf8" selected>UTF-8 (Unicode)</option>
						<option value="latin1">ISO-8859-1</option>
						<option value="latin2">ISO-8859-2</option>
						<option value="latin3">ISO-8859-3</option>
						<option value="latin4">ISO-8859-4</option>
						<option value="cyrillic">ISO-8859-5</option>
						<option value="arabic">ISO-8859-6</option>
						<option value="greek">ISO-8859-7</option>
						<option value="hebrew">ISO-8859-8</option>
						<option value="latin5">ISO-8859-9</option>
						<option value="latin6">ISO-8859-10</option>
						<option value="latin7">ISO-8859-13</option>
						<option value="latin8">ISO-8859-14</option>
						<option value="latin9">ISO-8859-15</option>
					</select>
				</td>
			</tr>
		</table>
		
		<?php
		
		do_fileselector_table($Config->dir->upload, NULL, 'admincorpus');
		
		?>
		
		<!-- 
		
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="4" class="concordtable">
					Select files
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="4">
					The following files are available (uncompressed) in the upload area. Put a tick next to
					the files you want to index into CWB format.
				</td>
			</tr>
			<tr>
				<th class="concordtable">Include?</th>
				<th class="concordtable">Filename</th>
				<th class="concordtable">Size (K)</th>
				<th class="concordtable">Date modified</th>
			</tr>
			<?php     // TODO, we can scrub the below as the fubncrtion version - c alled abnove =- now dfoes a better job.
			
// 			$file_list = scandir($Config->dir->upload);
$file_list = [];
			natcasesort($file_list);
	
			foreach ($file_list as $f)
			{
				$file = "{$Config->dir->upload}/$f";
				
				/* skip hidden files & directories */
				if ('.' == $f [0] || ! is_file($file))
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
						echo '<input type="checkbox" name="includeFile" value="' , urlencode($f) , '">', "\n"; 
						?>
					</td>
					
					<td class="concordgeneral" align="left"><?php echo $f; ?></td>
					
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
			
		</table>-->
		
		<table class="concordtable fullwidth" id="annotation_table_second">
			<tr>
				<th  colspan="7" class="concordtable">
					Define corpus annotation
				</th>
			</tr>
			<tr>
				<td  colspan="7" class="concordgrey">
					You do not need to specify the <em>word</em> as a P-attribute or the <em>text</em> as
					an S-attribute. Both are assumed and added automatically.
				</td>
			</tr>
		</table>
		<table class="concordtable fullwidth" id="annotation_table">
			<tr>
				<th colspan="5" class="concordtable">S-attributes (corpus XML)</th>
			</tr>

			<tr>
				<td colspan="5" class="concordgeneral" align="center">
					<table class="fullwidth">
						<tr>
							<td class="basicbox" width="50%">
								&nbsp;<br>
								Choose XML template
								<br>
								<i>
									(or select &ldquo;Custom XML structure&rdquo; and specify  
									XML elements and attributes in the form below)
								</i>
								<br>&nbsp;
							</td>
							<td class="basicbox" align="center" width="50%">
								<select name="useXmlTemplate">
									<option value='~~customSs' selected>Custom XML structure</option>
									
									<?php
									foreach (list_xml_templates() as $t)
										echo "\n\t\t\t\t\t\t\t\t\t<option value=\"{$t->id}\">{$t->description}</option>";
									?>
									
								</select>
							</td>
						</tr>
					</table>
				</td>
			</tr>

			<?php echo print_embiggenable_s_attribute_form('custom'), "\n"; ?>

		</table>
		
		<table class="concordtable fullwidth" id="annotation_table_third">
			<tr id="p_att_header_row">
				<th colspan="6" class="concordtable">P-attributes (word annotation)</th>
			</tr>
			
			<tr>
				<td colspan="6" class="concordgeneral" align="center">
					<table class="fullwidth">
						<tr>
							<td class="basicbox" width="50%">
								&nbsp;<br>
								Choose annotation template
								<br>
								<i>(or select "Custom annotation" and specify annotations in the boxes below)</i>
								<br>&nbsp;
							</td>
							<td class="basicbox" align="center" width="50%">
							
								<select name="useAnnotationTemplate">
									<option value='~~customPs' selected>Custom annotation</option>
									
									<?php
									foreach (list_annotation_templates() as $t)
										echo "\t\t\t\t\t\t<option value=\"{$t->id}\">{$t->description}</option>\n";
									?>
									
								</select>
							
							</td>
						</tr>
					</table>
				</td>
			</tr>
			
		<?php

		echo print_embiggenable_p_attribute_form('custom');
		
		?>

		</table>
		
		<table class="concordtable fullwidth">
		<?php do_adm_bitui_installcorpus_stylesheetrows(); ?>
		</table>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">Install corpus</th>
			</tr>
			
			<?php 
			if (! empty($Config->server_admin_email_address))
			{
				?>
				<tr>
					<td class="concordgrey" width="50%">
						Send email to administrator (<?php echo escape_html($Config->server_admin_email_address); ?>)
						when first-stage installation is complete?
					</td>
					<td class="concordgeneral">
						<input type="radio" id="emailDone:1" name="emailDone" value="1" checked>
						<label for="emailDone:1">Yes</label>
						<br>
						<input type="radio" id="emailDone:0" name="emailDone" value="0">
						<label for="emailDone:0" >No</label>
					</td>
				</tr>
				<?php
			}
			?> 
			
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					<input type="submit" value="Install corpus with settings above">
					<br>&nbsp;<br>
					<input type="reset" value="Clear this form">
				</td>
			</tr>
		</table>
	</form>

	<?php
}


function do_adm_ui_installcorpusdone()
{
	/* if, for whatever reason, we get here without the appropriate corpus handle, we get a link to the homepage as the first <li> */
	$corpus = isset($_GET['newlyInstalledCorpus']) ? cqpweb_handle_enforce($_GET['newlyInstalledCorpus']) : '' ;
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Your corpus has been successfully installed!
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p>You can now:</p>
				<ul>
					<li>
						<a href="../<?php echo $corpus; ?>/index.php?ui=manageMetadata">Design and 
						insert a text-metadata table for the corpus</a> (searches won't work till you do)<br>
					</li>
					<li>
						<a href="index.php?ui=installCorpus">Install another corpus</a>
					</li>
					<li>
						<a onClick="$('#installedCorpusIndexingNotes').slideDown();">View the indexing notes</a>
					</li>
				</ul>
				<p>&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" id="installedCorpusIndexingNotes" style="display:none">
				<?php echo print_indexing_notes($corpus); ?>
			</td>
	</table>
	<?php
}



function do_adm_bitui_installcorpus_stylesheetrows()
{
	?>
	
			<tr>
				<th colspan="2" class="concordtable">Select a stylesheet</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="left" width="50%">
					<input type="radio" id="cssCustom:0" name="cssCustom" value="0" checked>
					<label for="cssCustom:0">Choose a built in stylesheet:</label>
				</td>
				<td class="concordgeneral" align="left">
					<select name="cssBuiltIn">

						<?php
						foreach(scandir('../css') as $l)
							if ('.css' == substr($l, -4))
								echo "\n\t\t\t\t\t\t<option>$l</option>";
						?>

					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="left">
					<input type="radio" id="cssCustom:1" name="cssCustom" value="1">
					<label for="cssCustom:1">Use the stylesheet at this URL:</label>
				</td>
				<td class="concordgeneral" align="left">
					<input type="text" maxlength="255" name="cssCustomUrl">
				</td>
			</tr>
			
	<?php
}



function do_adm_ui_pluginadmin()
{
	$plugins = get_all_plugins_info();
	
	$plugin_file_options = '';
	foreach (scandir("../lib/plugins/") as $f)
		if (preg_match('/^\w+\.php$/', $f))
			$plugin_file_options .= "\n\t\t\t\t\t<option value=\"" . substr($f, 0, -4) . "\">$f</option>" ;
	$plugin_file_options .= "\n";
	?>
	
	<form id="newPluginForm" action="index.php" method="get"></form>
	<input form="newPluginForm" type="hidden" name="admF" value="newPlugin">
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="6">
				Manage Plugins
			</th>
		</tr>

		<tr>
			<td class="concordgrey" colspan="6">
				Explanation (delete if need be)
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="6">
				Register a new plugin
			</th>
		</tr>
		
		<tr>
			<td class="concordgrey" colspan="2">
				Select plugin code file:
			</td>
			<td class="concordgeneral" colspan="4">
				<select form="newPluginForm" name="class">
					<option selected>Choose from files in the plugins directory....</option>
					<?php echo $plugin_file_options; ?>
				</select>
				
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				Enter a short description:
			</td>
			<td class="concordgeneral" colspan="4">
				<input form="newPluginForm" type="text" name="description" size="50" maxlength="255">
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				Add extra configuration<br>(set of key/value data)
			</td>
			<td class="concordgeneral" colspan="4">
				<?php ?> 
				<table>
					<tr>
						<th class="basicbox" align="center">Key</th>
						<th class="basicbox" align="center">&nbsp;</th>
						<th class="basicbox" align="center">Value</th>
					</tr>
					
					<?php
						//TODO : make embiggenable 
					for ($i = 1; $i < 8; $i++)
					{
						?>

						<tr>
							<td class="basicbox" align="center">
								<input form="newPluginForm" type="text" name="extraKey<?php echo $i; ?>" size="40" maxlength="255" onKeyUp="check_c_word(this)">
							</td>
							<td class="basicbox" align="center">
								=&gt;
							</td>
							<td class="basicbox" align="center">
								<input form="newPluginForm" type="text" name="extraVal<?php echo $i; ?>" size="60" maxlength="255">
							</td>
						</tr>

						<?php
					}
					?>

				</table>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="6" align="center">
				&nbsp;<br>
				<input form="newPluginForm" type="submit" value="Click here to register this plugin">
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="6">
				Existing Plugins
			</th>
		</tr>
		
		<tr>
			<th class="concordtable">ID</th>
			<th class="concordtable">Type</th>
			<th class="concordtable">Description</th>
			<th class="concordtable">Class</th>
			<th class="concordtable">Extra setup</th>
			<th class="concordtable">Delete</th>
		</tr>
		
		<?php
		
		$objects = [];
		
		if (empty($plugins))
			echo "\n\n"
 				, '<td class="concordgrey" colspan="6">'
 				,'<p class="spacer">&nbsp;</p><p>There are no plugins currently registered.</p><p class="spacer">&nbsp;</p>'
 				, '</td>'
 				, "\n\n"
 				;
		
		foreach ($plugins as $p)
		{
			if (!isset($objects[$p->class]))
				$objects[$p->class] = new $p->class;
			if (!is_object($objects[$p->class]))
				$p->class = "Error!<br>{$p->class} not found";
			
			$extra_html = '';
			foreach($p->extra as $k=>$e)
				$extra_html .= "<strong>" . escape_html($k) . "</strong> =&gt; &ldquo;" . escape_html($e) . "&rdquo;<br>";
			
			echo "\n\t\t<tr>"
				, '<td class="concordgeneral" align="center">', $p->id, '</td>'
				, '<td class="concordgeneral" align="center">', plugin_interface_from_type($p->type), '</td>'
				, '<td class="concordgeneral">', escape_html($p->description), '</td>'
				, '<td class="concordgeneral" align="center">', $p->class, '</td>'
				, '<td class="concordgeneral">'
 					, '' == $extra_html ? '&nbsp;' : $extra_html
				, '</td>'
				, '<td class="concordgeneral" align="center">'
					, '<a class="menuItem" href="index.php?admF=execute&function=unregister_plugin&args='
					, $p->id
					, '&locationAfter=', urlencode('index.php?ui=pluginAdmin'), '">[x]</a>'
				, '</td>'
				, "</tr>\n"
				;
		}
		?>

	</table>

	<?php
}



function do_adm_ui_viewinstallers()
{
	global $Config;
	
	$plugins = get_all_plugins_info();
	
	$status_mapper = $Config->installer_process_status_description_map;
	
	?>
	
	<script>
	/* Auto-reload the page after 60 seconds. */
	window.setTimeout(function () {window.location.reload(true);}, 60000);
	</script>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="7">
				Log of user-corpus installer processes (at <?php echo date(CQPWEB_UI_DATE_FORMAT); ?>)
			</th>
		</tr>

		<tr>
			<td class="concordgrey" colspan="7">
				<p class="spacer">&nbsp;</p>
				<p>
					This table lists tagging/indexiong jobs in the queue or currently running. 
					Aborted or complete processes are listed below (and can be dismissed by their
					owner as well as the admin user). 
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		<tr>
			<th class="concordtable">ID</th>
			<th class="concordtable">Owner</th>
			<th class="concordtable">Installer</th>
			<th class="concordtable">Current status</th>
			<th class="concordtable">Last status change</th>
			<th class="concordtable">PHP process #</th>
			<th class="concordtable">Abort?</th>
		</tr>
		
		<?php
		$n_rows_written = 0;
		$complete = [];
		
		foreach (get_all_installer_process_info() as $p)
		{
			$plugins[$p->plugin_reg_id]->extra_string = [];
			foreach ($plugins[$p->plugin_reg_id]->extra as $k=>$v)
				$plugins[$p->plugin_reg_id]->extra_string[] = "$k=>$v";
			
			if (INSTALLER_STATUS_DONE == $p->status || INSTALLER_STATUS_ABORTED == $p->status)
			{
				$complete[] = $p;
				continue;
			}
			
			$likely_zombie = '';
			// nb, an alternaitve approach to see if a process is till running:: is_dir('/proc/$p->php_pid' ..
			if (function_exists('posix_getpgid') && false === posix_getpgid($p->php_pid))
				$likely_zombie = '<br>(likely zombie)';
			
			$n_rows_written++;
			
			echo "\n\t\t<tr>"
				, '<td class="concordgeneral" align="center">', $p->id, '</td>'
				, '<td class="concordgeneral" align="center">', user_id_to_name($p->user_id), '</td>'
				, '<td class="concordgeneral">'
					, escape_html($plugins[$p->plugin_reg_id]->description)
					, '<br>'
					, $plugins[$p->plugin_reg_id]->class , ' / ' , escape_html(implode(' ', $plugins[$p->plugin_reg_id]->extra_string)) 
				, '</td>'
				, '<td class="concordgeneral">', $status_mapper[$p->status], '</td>'
				, '<td class="concordgeneral" align="center">', substr($p->last_status_change, 0, -3), $likely_zombie, '</td>'
				, '<td class="concordgeneral" align="center">', $p->php_pid, '</td>'
				, '<td class="concordgeneral" align="center">'
					, '<a id="abortZombie:', $p->id, '" class="menuItem hasToolTip" data-tooltip="Add the abort flag to this zombie process" '
					, 'href="index.php?admF=execute&function=abort_zombie_installer_process&args='
					, $p->id
					, '&locationAfter=', urlencode('index.php?ui=viewInstallers') ,'">[Flag aborted]</a>'
				, '</td>'
				, "</tr>\n"
				;
		}
		
		if (0 == $n_rows_written)
		{
			?>
			<tr>
				<td class="concordgrey" colspan="7">
					<p class="spacer">&nbsp;</p>
					<p>
						There are no currently running or waiting installer processes.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<?php
		}
			
		if(!empty($complete))
		{
			?>
			</table>
			
			
			<table class="concordtable fullwidth">
				<tr>
					<th class="concordtable" colspan="7">
						Records of now-complete processes 
					</th>
				</tr>
				<tr>
					<th class="concordtable">ID</th>
					<th class="concordtable">Owner</th>
					<th class="concordtable">Installer</th>
					<th class="concordtable">Corpus/Error
					<th class="concordtable">Date complete</th>
					<th class="concordtable">Delete record</th>
				</tr>
				
				<?php

				$after = 'locationAfter=' . urlencode('index.php?ui=viewInstallers');
				
				foreach($complete as $p)
				{
					$c = get_corpus_info(make_user_corpus_handle($p->corpus_id));
					if (empty($c))
						$c = (object) ['id'=>0,'title'=>'Corpus not found'];
					
					echo "\n\t\t\t\t<tr>"
						, '<td class="concordgeneral" align="center">', $p->id, '</td>'
						, '<td class="concordgeneral" align="center">', $un = user_id_to_name($p->user_id), '</td>'
						, '<td class="concordgeneral">'
	 						, escape_html($plugins[$p->plugin_reg_id]->description)
	 						, '<br>'
							, $plugins[$p->plugin_reg_id]->class , ' / ' , escape_html(implode(' ', $plugins[$p->plugin_reg_id]->extra_string))
						, '</td>'
						, '<td class="concordgeneral">'
 							, $status_mapper[$p->status], ' : '
 							, (INSTALLER_STATUS_ABORTED == $p->status 
 									? escape_html($p->error_message)
 									: '<a class="menuItem hasToolTip" href="'
										. get_user_corpus_web_path($c->id, $un). '" data_tooltip="'. escape_html($c->title). '">'
										. $p->corpus_id
									. '</a>'
 									)
						, '</td>'
						, '<td class="concordgeneral" align="center">', substr($p->last_status_change, 0, -3), '</td>'
						, '<td class="concordgeneral" align="center">'
							, '<a class="menuItem" href="index.php?admF=execute&function=delete_installer_process&args='
							, $p->id, '&', $after
							, '">[x]</a>'
						, "</td></tr>\n"
						;
				}
		}
		else 
			echo '<tr><td class="concordgrey" colspan="7">' 
				, '<p class="spacer">&nbsp;</p><p>There are no completed processs in the record.</p>'
				,'<p class="spacer">&nbsp;</p></td></tr>'
				, "\n"
				;
		?>
	</table>

	<?php
}


function do_adm_ui_deletecorpus()
{
	$corpus = escape_html($_GET['corpus']);
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				You have requested deletion of the corpus &ldquo;<?php echo $corpus; ?>&rdquo; from the CQPweb system.
			</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center">Are you sure you want to do this?</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="admF" value="deleteCorpus">
					<input type="hidden" name="corpus" value="<?php echo $corpus; ?>">
					<br>
					<input type="checkbox" id="sureyouwantto" name="sureyouwantto" value="yes">
					<label for="sureyouwantto">Yes, I'm sure I want to do this.</label>
					<br>&nbsp;<br>
					<input type="submit" value="I am definitely sure I want to delete this corpus.">
					<br>&nbsp;
				</form>
			</td>
		</tr>
	</table>

	<?php
}


function do_adm_ui_corpuscategories()
{
	global $Config;
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="6">
				Manage corpus categories
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="6">
				Corpus categories are used to organise links to corpora on CQPweb's home page.
				<br>&nbsp;<br>
				This behaviour can be turned on or off using the setting 
					<code>$homepage_use_corpus_categories</code>
				in your configuration file.
				<br>&nbsp;<br>
				Currently, it is turned <strong><?php echo ($Config->homepage_use_corpus_categories?'on':'off'); ?></strong>.
				<br>&nbsp;<br>
				Categories are displayed on the home page in the defined <em>sort order</em>, with low numbers shown first
				(in the case of a numerical tie, categories are sorted alphabetically).
				<br>&nbsp;<br>
				The available categories are listed below. Use the form at the bottom to ad a new category.
				<br>&nbsp;<br>
				Important note: you cannot have two categories with the same name, and you cannot delete 
				<em>&ldquo;Uncategorised&rdquo;</em>, which is the default category of a new corpus.
			</td>
		</tr>
		<tr>
			<th class="concordtable">
				Category label
			</th>
			<th class="concordtable">
				No. corpora
			</th>
			<th class="concordtable">
				Sort order
			</th>
			<th class="concordtable" colspan="3">
				Actions
			</th>
		</tr>

		<?php
		/* this function call is a bit wasteful, but it makes sure "Uncategorised" exists... */
		list_corpus_categories();

		$result = do_sql_query("select id, label, sort_n from corpus_categories order by sort_n asc, label asc");
		$sort_key_max = 0;
		$sort_key_min = 0; 
		while ($o = mysqli_fetch_object($result))
		{
			list($n) = mysqli_fetch_row(do_sql_query("select count(*) from corpus_info where corpus_cat={$o->id}"));
			echo '<tr><td class="concordgeneral">', $o->label, '</td>',
				'<td class="concordgeneral" align="center">', $n, '</td>',
				'<td class="concordgeneral" align="center">', $o->sort_n, '</td>',
				'<td class="concordgeneral" align="center">',
					'<a class="menuItem" href="index.php?admF=execute&function=update_corpus_category_sort&args=',
					$o->id, urlencode('#'), $o->sort_n - 1, 
					'&locationAfter=', urlencode('index.php?ui=manageCorpusCategories'), '">',
					'[Move up]</a></td>',
				'<td class="concordgeneral" align="center">',
					'<a class="menuItem" href="index.php?admF=execute&function=update_corpus_category_sort&args=',
					$o->id, urlencode('#'), $o->sort_n + 1, 
					'&locationAfter=', urlencode('index.php?ui=manageCorpusCategories'), '">',
					'[Move down]</a></td>',
				'<td class="concordgeneral" align="center">',
					'<a class="menuItem" href="index.php?admF=execute&function=delete_corpus_category&args=',
					$o->id, '&locationAfter=', urlencode('index.php?ui=manageCorpusCategories'), '">',
					'[Delete]</a></td>',
				"</tr>\n";
			if ($sort_key_max < $o->sort_n)
				$sort_key_max = $o->sort_n;
			if ($sort_key_min > $o->sort_n)
				$sort_key_min = $o->sort_n;
		}
		?>

	</table>

	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="newCorpusCategory">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="5">
					Create a new category
				</th>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					&nbsp;<br>
					Specify a category label
					<br>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br>
					<input name="newCategoryLabel" size="50" type="text" maxlength="255">
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					&nbsp;<br>
					Initial sort key for this category
					<br>
					<em>(lower numbers appear higher up)</em>
					<br>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br>
					<select name="newCategoryInitialSortKey">
						<?php
						echo "\n";
						/* give options for intial sort key of zero to existing range, plus one */
						for ($sort_key_min--; $sort_key_min < 0; $sort_key_min++)
							echo "\t\t<option>$sort_key_min</option>\n";
						echo "\t\t<option selected>0</option>\n";
						for ($sort_key_max++, $i = 1; $i <= $sort_key_max; $i++)
							echo "\t\t<option>$i</option>\n";
						?>
					</select>
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="3" align="center">
					&nbsp;<br>
					<input type="submit" value="Click here to create the new category">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>
	
	<?php
}


function do_adm_ui_annotationtemplates()
{
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="8">
				Manage annotation templates
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="8">
				&nbsp;<br>
				An annotation template is a description of a predefined set of word-level annotations (p-attributes).
				<br>&nbsp;<br>
				You can use templates when indexing corpora instead of specifying the p-attribute information every time.
				<br>&nbsp;<br>
				Use the controls below to create and manage annotation templates.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="8">
				Currently-defined annotation templates
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				ID
			</th>
			<th class="concordtable">
				Description
			</th>
			<th class="concordtable" colspan="5">
				Attributes (in order of columns left-to-right; [*] = primary)
			</th>
			<th class="concordtable">
				Delete
			</th>
		</tr>
		
		<?php
		
		foreach(list_annotation_templates() as $template)
		{
			$rowspan = 1 + count($template->attributes);
			echo "\n\t\t<tr>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"$rowspan\">{$template->id}</td>"
				, "\n\t\t\t<td class=\"concordgeneral\" rowspan=\"$rowspan\">{$template->description}</td>\n"
				, "\n\t\t\t", '<td class="concordgrey" align="center">N</td>'
				, '<td class="concordgrey" align="center">Handle</td><td class="concordgrey" align="center">Description</td>'
				, '<td class="concordgrey" align="center">Feature set?</td><td class="concordgrey" align="center">Tagset</td>'
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"$rowspan\">"
				, "<a class=\"menuItem\" href=\"index.php?admF=deleteAnnotationTemplate&toDelete={$template->id}\">[x]</a></td>"
				, "\n\t\t</tr>"
				;
			
			foreach($template->attributes as $att)
			{
				$star = ($att->handle == $template->primary_annotation ? ' [*] ' : '');
				
				$link = (empty($att->external_url) ? "{$att->tagset}" :"<a href=\"{$att->external_url}\" target=\"_blank\">{$att->tagset}</a>");
				
				echo "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">{$att->order_in_template}</td>"
					, "\n\t\t\t<td class=\"concordgeneral\">{$att->handle}$star</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\">{$att->description}</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">", ($att->is_feature_set ? 'Y' : 'N'), "</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\">$link</td>\n"
					, "\n\t\t</tr>"
					;
			}
		}
		?>
		
	</table>

	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="newAnnotationTemplate">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="6">
					Add new annotation template
				</th>
			</tr>

			<tr>
				<td colspan="6" class="concordgeneral" align="center">
					<table class="fullwidth">
						<tr>
							<td class="basicbox" width="50%" align="center">
								&nbsp;<br>
								Enter a description for your new template:
								<br>&nbsp;
							</td>
							<td class="basicbox" width="50%" align="center">
								<input type="text" name="newTemplateDescription" size="60" maxlength="255">
							</td>
						</tr>
					</table>
				</td>
			</tr>

			<?php echo print_embiggenable_p_attribute_form('template'); ?>

			<tr>
				<td class="concordgeneral" colspan="6" align="center">
					&nbsp;<br>
					<input type="submit" value="Click here to create annotation template">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>

	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="loadDefaultAnnotationTemplates">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="6">
					Install default templates
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					&nbsp;<br>
					The default annotation templates describe commonly-used corpus annotation patterns 
					(especially those generated by annotation tools created or used by the CWB/CQPweb developers).
						<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					&nbsp;<br>
					<input type="submit" value="Load built-in annotation templates">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>
	
	<?php
}


function do_adm_ui_metadatatemplates()
{
	/* for datatype descriptions */
	global $Config;
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="8">
				Manage metadata templates
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="8">
				&nbsp;<br>
				A metadata template is a description of a series of columns of metadata (data about either corpus texts, or some other
				relevant entity in the structure of the corpus.) Each column has (1) a handle, (2) a description, and (3) a datatype.
				<br>&nbsp;<br>
				You can use templates when setting up metadata tables instead of entering the field-structure every time.
				<br>&nbsp;<br>
				Use the controls below to create and manage metadata templates.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="7">
				Currently-defined metadata templates
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				ID
			</th>
			<th class="concordtable">
				Description
			</th>
			<th class="concordtable" colspan="4">
				Fields
			</th>
			<th class="concordtable">
				Delete
			</th>
		</tr>
		
		<?php
		$all_templates = list_metadata_templates();
		if (empty($all_templates))
			echo '<tr><td class="concordgrey" colspan="8">'
				, '<p class="spacer">&nbsp;</p><p>No metadata templates are defined.</p><p class="spacer">&nbsp;</p>'
				, '</td></tr>'
				;
		
		foreach($all_templates as $template)
		{
			$rowspan = 1 + count($template->fields);
			echo "\n\t\t<tr>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"$rowspan\">{$template->id}</td>"
				, "\n\t\t\t<td class=\"concordgeneral\" rowspan=\"$rowspan\">{$template->description}</td>\n"
				, "\n\t\t\t"
				, '<td class="concordgrey" align="center">N</td>'
				, '<td class="concordgrey" align="center">Handle</td>'
				, '<td class="concordgrey" align="center">Description</td>'
				, '<td class="concordgrey" align="center">Datatype</td>'
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"$rowspan\">"
				, "<a class=\"menuItem\" href=\"index.php?admF=deleteMetadataTemplate&toDelete={$template->id}\">[x]</a></td>"
				, "\n\t\t</tr>"
				;
			foreach($template->fields as $field)
				echo "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">{$field->order_in_template}</td>"
					, "\n\t\t\t<td class=\"concordgeneral\">{$field->handle}</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\">{$field->description}"
					, ($field->handle == $template->primary_classification ? ' <em>(primary classification)</em>' : '')
					, "</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\">{$Config->metadata_type_descriptions[$field->datatype]}</td>\n"
					, "\n\t\t</tr>"
					;
		}
		?>

	</table>
	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="newMetadataTemplate">
		<input type="hidden" id="fieldCount" name="fieldCount" value="5">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="6">
					Add new metadata template
				</th>
			</tr>
			<tr>
				<td colspan="6" class="concordgeneral">
					<table class="fullwidth">
						<tr>
							<td class="basicbox" align="center" width="50%">
								&nbsp;<br>
								Enter a description for your new template:
								<br>&nbsp;
							</td>
							<td class="basicbox" align="center" width="50%">
								<input type="text" name="newTemplateDescription" size="60" maxlength="255">
							</td>
						</tr>
					</table>
					<p>
						Important note: when you specify the metadata fields below, you <strong>must not</strong> include the identifier column
						- which is implicit, and must be the first column of every file that is used with this template.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>

			<?php echo print_embiggenable_metadata_form(); ?> 

			<tr>
				<td class="concordgeneral" colspan="6" align="center">
					&nbsp;<br>
					<input type="submit" value="Click here to create metadata template">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="6">
				Install default templates
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				The default metadata templates describe commonly-used patterns for metadata files.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="admF" value="loadDefaultMetadataTemplates">
					&nbsp;<br>
					<input type="submit" value="Load built-in metadata templates">
					<br>&nbsp;
				</form>
			</td>
		</tr>
	</table>

	<?php
}


function do_adm_ui_xmltemplates()
{
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="8">
				Manage XML templates
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="8">
				&nbsp;<br>
				An XML template is a description of a predefined set of XML elements/attributes (s-attributes).
				<br>&nbsp;<br>
				You can use templates when indexing corpora instead of specifying the s-attribute information every time.
				<br>&nbsp;<br>
				Use the controls below to create and manage XML templates.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="7">
				Currently-defined XML templates
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				ID
			</th>
			<th class="concordtable">
				Description
			</th>
			<th class="concordtable" colspan="4">
				Elements and attributes
			</th>
			<th class="concordtable">
				Delete
			</th>
		</tr>
		
		<?php
		
		/* for datatype descriptions */
		global $Config;
		
		foreach(list_xml_templates() as $template)
		{
			$rowspan = 1 + count($template->attributes);
			echo "\n\t\t<tr>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"$rowspan\">{$template->id}</td>"
				, "\n\t\t\t<td class=\"concordgeneral\" rowspan=\"$rowspan\">{$template->description}</td>\n"
				, "\n\t\t\t", '<td class="concordgrey" align="center">N</td>'
				, '<td class="concordgrey" align="center">Handle</td><td class="concordgrey" align="center">Description</td>'
				, '<td class="concordgrey" align="center">Datatype</td>'
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"$rowspan\">"
				, "<a class=\"menuItem\" href=\"index.php?admF=deleteXmlTemplate&toDelete={$template->id}\">[x]</a></td>"
				, "\n\t\t</tr>"
				;
			foreach($template->attributes as $att)
			{
				if ($att->handle != $att->att_family)
				{
					$a_handle = preg_replace("/^{$att->att_family}_/", '', $att->handle);
					$att->description .= ' (<i>' . $a_handle . '</i> attribute on <i>' . $att->att_family . '</i>)'; 
				}
				echo "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">{$att->order_in_template}</td>"
					, "\n\t\t\t<td class=\"concordgeneral\">{$att->handle}</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\">{$att->description}</td>\n"
					, "\n\t\t\t<td class=\"concordgeneral\">{$Config->metadata_type_descriptions[$att->datatype]}</td>\n"
					, "\n\t\t</tr>"
					;
			}
		}
		?>
	
	</table>

	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="newXmlTemplate">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="6">
					Add new XML template
				</th>
			</tr>
		

			<tr>
				<td colspan="6" class="concordgeneral" align="center">
					<table class="fullwidth">
						<tr>
							<td class="basicbox" width="50%" align="center">
								&nbsp;<br>
								Enter a description for your new template:
								<br>&nbsp;
							</td>
							<td class="basicbox" width="50%" align="center">
								<input type="text" name="newTemplateDescription" size="60" maxlength="255">
							</td>
						</tr>
					</table>
				</td>
			</tr>

			<?php echo print_embiggenable_s_attribute_form('template'), "\n"; ?> 

			<tr>
				<td class="concordgeneral" colspan="6" align="center">
					&nbsp;<br>
					<input type="submit" value="Click here to create XML template">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="6">
				Install default templates
			</th>
		</tr>		
		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				The default xml templates describe commonly-used corpus XML patterns.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					&nbsp;<br>
					<input type="submit" value="Load built-in XML templates">
					<br>&nbsp;
					<input type="hidden" name="admF" value="loadDefaultXmlTemplates">
				</form>
			</td>
		</tr>
	</table>
	
	<?php
}


function do_adm_ui_visualisationtemplates()
{
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">
				Manage visualisation templates
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="5">
				&nbsp;<br>
				An XML-visualisation template is created when you flag an existing visualisation, created for any
				corpus in the system, to be usable as a template.
				<br>&nbsp;<br>
				Once a visualisation is made a template it can be imported for use in another corpus by using the 
				template list in that corpus's interface.  
				<br>&nbsp;<br>
				Use the controls to add or remove visualisation templates.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="5">
				Current visualisation templates
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
				Deactivate
			</th>
		</tr>
		
		<?php
		$template_list = get_global_xml_visualisations(true);
		if (empty($template_list))
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
		foreach($template_list as $t)
		{
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
				, 'In concordance? &ndash; <strong>', ($t->in_concordance?'Yes':'No'), '</strong><br>'
				, 'In context?     &ndash; <strong>', ($t->in_context    ?'Yes':'No'), '</strong><br>'
				, 'In download?    &ndash; <strong>', ($t->in_download   ?'Yes':'No'), '</strong>'
				, '</td>'
				, '<td class="concordgeneral" align="center">'
					, '<form class="greyoutOnSubmit" action="index.php" method="get">'
						, '<input type="submit" value="Deactivate!">'
						, '<input type="hidden" name="admF" value="execute">'
						, '<input type="hidden" name="function" value="xml_visualisation_use_as_template">'
						, '<input type="hidden" name="args" value="', $t->id, '#0">'
						, '<input type="hidden" name="locationAfter" value="index.php?ui=visualisationTemplates">'
					, '</form>'
				, '</td>'
				, "\n\n\t\t</tr>\n"
				;
		}
		?>
		
		<tr>
			<th class="concordtable" colspan="5">
				Available non-template visualisations
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				Home corpus
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
				Make template
			</th>
		</tr>
		
		<?php
		$template_list = get_global_xml_visualisations(false);
		if (empty($template_list))
		{
			?>
			
			<tr>
				<td class="concordgrey" colspan="5">
					<p class="spacer">&nbsp;</p>
					<p>No visualisations are available to be turned into templates.
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
			<?php 
		}
		foreach($template_list as $t)
		{
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
				, 'In concordance? &ndash; <strong>', ($t->in_concordance?'Yes':'No'), '</strong><br>'
				, 'In context?     &ndash; <strong>', ($t->in_context    ?'Yes':'No'), '</strong><br>'
				, 'In download?    &ndash; <strong>', ($t->in_download   ?'Yes':'No'), '</strong>'
				, '</td>'
				, '<td class="concordgeneral" align="center">'
					, '<form class="greyoutOnSubmit" action="index.php" method="get">'
						, '<input type="submit" value="Activate!">'
						, '<input type="hidden" name="admF" value="execute">'
						, '<input type="hidden" name="function" value="xml_visualisation_use_as_template">'
						, '<input type="hidden" name="args" value="', $t->id, '#1">'
						, '<input type="hidden" name="locationAfter" value="index.php?ui=visualisationTemplates">'
					, '</form>'
				, '</td>'
				, "\n\n\t\t</tr>\n"
				;
		}
		
		?>
	
	</table>
	
	<?php
}




function do_adm_ui_uploadarea()
{
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Uploaded files
			</th>
		</tr>
	</table>
	
	<?php 
	
	do_ui_upload_form_as_table('index.php'); 
	
	do_ui_display_upload_area(NULL, true);
}


function do_adm_ui_useruploads()
{
	$user = safe_user_info_from_get();

	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Viewing user <?php echo $user->username; ?>&rsquo;s upload area
			</th>
		</tr>
	</table>

	<?php 


	do_ui_display_upload_area($user->username, false);

}




function do_adm_bitui_useroverview()
{
	$n_users_by_status = array(
		USER_STATUS_UNVERIFIED => 0,
		USER_STATUS_ACTIVE => 0,
		USER_STATUS_SUSPENDED => 0,
		USER_STATUS_PASSWORD_EXPIRED => 0,
		);

	$result = do_sql_query("select acct_status, count(acct_status) as N from user_info group by acct_status");

	while ($o = mysqli_fetch_object($result))
		$n_users_by_status[$o->acct_status] = $o->N;

	?>
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Manage Users
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" width="60%"><strong>Total users on the system:</strong></td>
			<td class="concordgeneral"><strong><?php echo number_format(array_sum($n_users_by_status)); ?></strong></td>
		</tr>
		<tr>
			<td class="concordgeneral">Number of accounts validated and active:</td>
			<td class="concordgeneral"><?php echo number_format($n_users_by_status[USER_STATUS_ACTIVE]); ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Number of unverified accounts (*):</td>
			<td class="concordgeneral"><?php echo number_format($n_users_by_status[USER_STATUS_UNVERIFIED]); ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Number of suspended accounts:</td>
			<td class="concordgeneral"><?php echo number_format($n_users_by_status[USER_STATUS_SUSPENDED]); ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Number of accounts with expired passwords:</td>
			<td class="concordgeneral"><?php echo number_format($n_users_by_status[USER_STATUS_PASSWORD_EXPIRED]); ?></td>
		</tr>
		<tr>
			<td colspan="2" class="concordgrey"> 
				(*) <a class="menuItem" href="index.php?ui=userUnverified">Click here to go to list of unverified accounts</a>
			</td>
		</tr>
	</table>

	<?php
}

function do_adm_bitui_usersearchform()
{
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th colspan="3" class="concordtable">
				Search for user account details
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="3">
				&nbsp;

				<?php

				do_adm_bitui_quicksearch_css(); 
				do_adm_bitui_user_quicksearch_form('', 'link');

				?>

				&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				&nbsp;<br>
				Full search (username, realname, or email):
				<br>&nbsp;
			</td>
			<td class="concordgeneral" align="center">
				<form id="userFullSearchForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="userFullSearchForm" type="hidden" name="ui" value="userSearch">
				<input form="userFullSearchForm" tabindex="29" name="searchterm" size="50" type="text">
			</td>
			<td class="concordgeneral" align="center" width="20%">
				<input form="userFullSearchForm" tabindex="30" type="submit" value="Search">
			</td>
		</tr>
	</table>

	<?php
}

function do_adm_bitui_user_quicksearch_form($input_name, $task, $label = NULL, $form = '')
{
	static $seq = 1;

	/* putting username data for responsive quicksearch into the page reduces need for Ajax */
	$username_list = implode('|', list_sql_values("select username from user_info order by username collate " . get_sql_handle_collation(true)));

	if (empty($form))
	{
		$form_att = '';
		$form_start = '<form>';
		$form_end = '</form>';
	}
	else
	{
		$form_att = ' form="' . $form . '"'; 
		$form_start = $form_end = ''; 
	}

	if (!is_string($label))
		$label = 'Quick username search:';
	else
		$label = escape_html($label);

	echo $form_start, "\n";
	?>
		<label for="userQuicksearch_<?php echo $seq; ?>"><?php echo $label; ?></label>
		<input 
			class="userQuicksearch" 
			name="<?php echo $input_name; ?>"
			id="userQuicksearch_<?php echo $seq; ?>"
			data-effect="<?php echo $task; ?>" 
			<?php echo $form_att; ?> 
			type="text" 
			autocomplete="off" 
			tabindex="<?php echo $seq; ?>"
			placeholder="Type username..."
		>
		<input class="userQuicksearchData" id="userQuicksearchData_<?php echo $seq; ?>" type="hidden" value="<?php echo $username_list; ?>">
	<?php 
	echo $form_end, "\n"; 
	?>
	
	<!-- empty element, anchor for results -->
	<div id="userQuicksearchResultsAnchor_<?php echo $seq; ?>"></div>
	<ul id="userQuicksearchResults_<?php echo $seq; ?>" class="userQuicksearchResults"></ul>
	
	<?php
	
	$seq += 2;
}

function do_adm_bitui_quicksearch_css()
{
	// TODO move to a system .css
	?>

	<!-- ad hoc (non-global-stylesheet-based) style for quicksearch popup -->
	<style>
	ul.userQuicksearchResults {
		position: absolute;
		float: left;
		vertical-align: top;
		text-align:left;
		margin: 0px;
		padding: 0px;
		list-style: none;
		margin: 0px;
		padding: 0px;
	}					
	.userQuicksearchResults li { 
		padding: 0px;
		display: block;
	}
	.userQuicksearchResults  a, .userQuicksearchResults  a:link, .userQuicksearchResults  a:visited {
		display: block;
		background-color: #f2f2e0;
		padding: 10px;
		margin: 0px;
		color: #000099;
		text-decoration: none;
		font-size: 11pt;
		border: 1px solid #cccccc;	
	}
	.userQuicksearchResults  a:hover {
		background-color: #dfdfff;
		color: #ff0000;
	}
	</style>

	<?php
				
}




function do_adm_ui_usersearch_searchterm() { do_adm_ui_usersearch(false); } 
function do_adm_ui_usersearch_unverified() { do_adm_ui_usersearch(true ); } 

/* NB this func handles both the "search for some term" AND "search for unverified users" */
function do_adm_ui_usersearch($look_for_unverified = false)
{
	if (!$look_for_unverified)
		if (! isset($_GET['searchterm']))
			exiterror("No search term was supplied.");

	$sql = "select username, realname, email, affiliation, acct_create_time from user_info ";
	
	if ($look_for_unverified)
	{
		$sql .= " where acct_status = " . USER_STATUS_UNVERIFIED . " order by acct_create_time asc";
		$get_user_delete_url = 
			function ($o) 
			{
				return "index.php?admF=execute&function=delete_unverified_user&args={$o->username}&locationAfter=" . urlencode('index.php?ui=userUnverified');
			};
	}
	else
	{
		$term = escape_sql($_GET['searchterm']);
		$sql .= "
					where username collate " . get_sql_handle_collation(true) . " like '%$term%' 
					or email collate utf8_general_ci like '%$term%' 
					or realname collate utf8_general_ci like '%$term%' 
					order by username asc
				";
		$get_user_delete_url = 
			function ($o) 
			{
				return "index.php?ui=userDelete&checkUserDelete={$o->username}";
			};
	}
	
	$result = do_sql_query($sql);
	
// TODO should it also be psosible to search for really old "last-seen" accounts, to make it easy to delete / suspend them?
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="8" class="concordtable">
				<?php echo ($look_for_unverified ? 'Viewing list of unverified user accounts' : 'User search results'); ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="8">
				<p class="spacer">&nbsp;</p>
				<p>
					<?php
					if ($look_for_unverified)
						echo "The following ", number_format(mysqli_num_rows($result))
							, " user accounts have been created on the system, but <strong>not verified</strong> as using a valid email address.\n"
 							;
					else
						echo "Your search term: <strong>", escape_html($_GET['searchterm']), "</strong>\n";
					?>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<th class="concordtable">&nbsp;</th>
			<th class="concordtable">Username</th>
			<th class="concordtable">Realname</th>
			<th class="concordtable">Email</th>
			<th class="concordtable">Affiliation</th>
			<th class="concordtable">Date created</th>
			<th class="concordtable" colspan="2">Actions</th>
		</tr>

		<?php		

		$i = 0;
	
		if (1 > mysqli_num_rows($result))
			echo "\n\t\t<tr><td colspan=\"8\" class=\"concordgrey\">"
				, "<p class=\"spacer\">&nbsp;</p><p>No results found.</p><p class=\"spacer\">&nbsp;</p>"
				, "</td></tr>\n"
				;
		
		while ($r = mysqli_fetch_object($result))
			echo "\n\t\t<tr>"
				, "\n\t\t\t<td class=\"concordgeneral\">", ++$i, "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">"
					, "<strong><a class=\"menuItem\" href=\"index.php?ui=userView&username={$r->username}\">{$r->username}</a></strong></td>"
				, "\n\t\t\t<td class=\"concordgeneral\">", escape_html($r->realname), "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">", escape_html($r->email), "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">", escape_html($r->affiliation), "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\">", substr($r->acct_create_time, 0, -3), "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\"><a class=\"menuItem\" href=\"index.php?ui=userView&username="
					, $r->username, "\">[View full details]</a></td>"
				, "\n\t\t\t<td class=\"concordgeneral\" align=\"center\"><a class=\"menuItem\" href=\""
					, $get_user_delete_url($r)
				, "\">[Delete account]</a></td>"
				, "\n\t\t</tr>\n"
				;
		?>

	</table>

	<?php
}


function do_adm_ui_userview()
{
	global $Config;
	include('../lib/user-iso31661.php');
	
	/* allow this view to be accessed either by username or by an ID. 
	 * n.b. Do not confuse local object $user with global object $User!!!! */
	$user = safe_user_info_from_get();
// 	if (isset($_GET['username']))
// 		$user = get_user_info($_GET['username']);
// 	else 
// 	{
// 		if (!isset($_GET['userID']))
// 			exiterror("User view function accessed, but no username / user ID specified.");
// 		else 
// 			$user = get_user_info_by_id((int)$_GET['userID']);
// 	}
	
// 	if (false === $user)
// 		exiterror("Invalid username or user ID.");
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Viewing User Profile
			</th>
		</tr>
		<tr>
			<td class="concordgeneral"><strong>Username:</strong></td>
			<td class="concordgeneral"><strong><?php echo $user->username; ?></strong></td>
		</tr>
		<tr>
			<td class="concordgeneral">Account ID:</td>
			<td class="concordgeneral"><?php echo $user->id; ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Email address linked to account:</td>
			<td class="concordgeneral"><?php echo escape_html($user->email); ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Account status:</td>
			<td class="concordgeneral"><?php echo $Config->user_account_status_description_map[$user->acct_status]; ?></td>
		</tr>
		<?php
		
		if (USER_STATUS_UNVERIFIED == $user->acct_status)
		{
			?>
			<tr>
				<td colspan="2" class="concorderror">
					&nbsp;<br>
					This user's account has not yet been verified via the link sent to their email address. 
					You can manually verify it using the button below.
					<br> 
					This will allow them to log on, but circumvents the check on the correctness of their email address!
					<br>&nbsp;
					<div align="center">
						<form class="greyoutOnSubmit" action="index.php" method="get">
							<input type="submit" value="Manually verify this user's account">
							<input type="hidden" name="admF" value="execute">
							<input type="hidden" name="function" value="verify_user_account">
							<input type="hidden" name="args" value="<?php echo $user->username; ?>">
							<input type="hidden" name="locationAfter" value="index.php?ui=userView&username=<?php echo $user->username; ?>">
						</form>
					</div>
				</td>
			</tr>
			<?php
		} 
		
		?>
		
		<!-- ****************************************************************** -->
		
		<tr>
			<td colspan="2" class="concordgrey">
				&nbsp;<br>
				The user has entered their personal details as follows:
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Real name:</td>
			<td class="concordgeneral"><?php echo escape_html($user->realname); ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Stated affiliation:</td>
			<td class="concordgeneral"><?php echo escape_html($user->affiliation); ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Country:</td>
			<td class="concordgeneral"><?php echo $Config->iso31661[$user->country]; ?></td>
		</tr>
		
		<!-- ****************************************************************** -->
		
		<tr>
			<td colspan="2" class="concordgrey">
				&nbsp;<br>
				User activity on this account:
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Account originally created:</td>
			<td class="concordgeneral"><?php echo escape_html($user->acct_create_time);  ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Number of queries in history:</td>
			<td class="concordgeneral">
				<?php
				$n_of_queries = get_sql_value("select count(*) from query_history where user = '{$user->username}'");
				echo number_format($n_of_queries), "\n"; 
				?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Time of last visit to CQPweb:</td>
			<td class="concordgeneral"><?php echo escape_html($user->last_seen_time);  ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Account expires:</td>
			<td class="concordgeneral"><?php echo 0==$user->expiry_time ? 'Not set' : escape_html($user->expiry_time); ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Password expires:</td>
			<td class="concordgeneral"><?php echo 0==$user->password_expiry_time ? 'Not set' : escape_html($user->password_expiry_time); ?></td>
		</tr>
		
		<!-- ************************************************************************* -->
		
		<!--  TODO the following could prob be reformatted as separate a 3-col table: saved item desc, N of items, disk space used -->

		<tr>
			<th colspan="2" class="concordtable">
				User's disk usage
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">Number of saved/categorised queries:</td>
			<td class="concordgeneral">
				<?php 
				$sql = "select count(*) as number, sum(file_size) as bytes from saved_queries 
							where saved != " . CACHE_STATUS_UNSAVED . " and user = '{$user->username}'";
				$o = mysqli_fetch_object(do_sql_query($sql));
				$saved_query_number     = number_format( $o->number );
				$saved_query_disk_space = number_format( ((float)$o->bytes) / (1024.0 * 1024.0), 1 ) . ' MB';
				echo $saved_query_number;
				?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Disk space for saved queries:</td>
			<td class="concordgeneral"><?php echo $saved_query_disk_space; ?></td>
		</tr>
		<tr>
			<td class="concordgeneral">Disk space for categorised-query user-databases:</td>
			<td class="concordgeneral"><?php /*TODO */ ?>TODO</td>
		</tr>
		<tr>
			<td class="concordgeneral">Number of saved multivariate data matrices:</td>
			<td class="concordgeneral"><?php /*TODO */ ?>TODO</td>
		</tr>
		<tr>
			<td class="concordgeneral">Disk space for multivariate data matrices:</td>
			<td class="concordgeneral"><?php /*TODO */ ?>TODO</td>
		</tr>
		
		
		<!-- ************************************************************************* -->
		
		
		<!--  TODO not v pretty like this.  -->
		
		<tr>
			<th colspan="2" class="concordtable">
				User's current log-in sessions
			</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center">Session was logged in</td>
			<td class="concordgrey" align="center">Session will expire</td>
		</tr>
		
		<?php 
		
		$result = do_sql_query("select * from user_cookie_tokens where user_id = " . $user->id);
		
		if (0 == mysqli_num_rows($result))
			echo "\n\t\t<tr><td class=\"concordgeneral\" colspan=\"2\" align=\"center\">No present logins.</td></tr>\n";
		
		while ($o = mysqli_fetch_object($result))
			echo "\n\t\t<tr>"
				, "<td class=\"concordgeneral\" align=\"center\">"
				, date(CQPWEB_UI_DATE_FORMAT, $o->creation)
				, "</td>"
				, "<td class=\"concordgeneral\" align=\"center\">"
				, date(CQPWEB_UI_DATE_FORMAT, $o->expiry)
				, "</td>"
				, "</tr>\n"
				;
		
		?>

	</table>
	
	<form id="resetUserPasswordForm" class="greyoutOnSubmit" action="useracct-act.php" method="post"></form>
	<input form="resetUserPasswordForm" type="hidden" name="userAction" value="resetUserPassword">
	<input form="resetUserPasswordForm" type="hidden" name="userFunctionFromAdmin" value="1">
	<input form="resetUserPasswordForm" type="hidden" name="userForPasswordReset" value="<?php echo $user->username; ?>">
	<input form="resetUserPasswordForm" type="hidden" name="locationAfter" value="index.php?ui=userView&username=<?php echo $user->username; ?>">
			
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="3">
				Actions
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				Reset the user's password:
				<br>&nbsp;
			</td>
			<td class="concordgeneral">
				Enter new password for <strong><?php echo $user->username; ?></strong>:
				<input form="resetUserPasswordForm" type="text" name="newPassword" width="50">
			</td>
			<td class="concordgeneral" align="center">
				<input form="resetUserPasswordForm" type="submit" value="Reset the password">
			</td>
		</tr>
		<!-- ****************************************************************** -->
		
		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				Password locking:<br>
				<em>When an account is password-locked, <br>only the admin user can change the password.</em>
				<br>&nbsp;
			</td>
			
			<?php
			if ($user->password_locked)
			{
				?>
				
				<td class="concordgeneral">
					This user's password is <strong>currently locked</strong>.
				</td>
				<td class="concordgeneral" align="center">
					<form action="index.php" method="get">
						<input type="hidden" name="admF" value="execute">
						<input type="hidden" name="function" value="unlock_user_password">
						<input type="hidden" name="args" value="<?php echo $user->id; ?>">
						<input type="hidden" name="locationAfter" value="index.php?ui=userView&username=<?php echo $user->username; ?>">
						<input type="submit" value="Unlock this user's password">
					</form>
				</td>
				
				<?php
			}
			else
			{
				?>
				
				<td class="concordgeneral">
					<p>This user's password is <strong>not</strong> locked.</p>
				</td>
				<td class="concordgeneral" align="center">
					<form action="index.php" method="get">
						<input type="hidden" name="admF" value="execute">
						<input type="hidden" name="function" value="lock_user_password">
						<input type="hidden" name="args" value="<?php echo $user->id; ?>">
						<input type="hidden" name="locationAfter" value="index.php?ui=userView&username=<?php echo $user->username; ?>">
						<p>
							<input type="submit" value="Lock this user's password">
						</p>
					</form>
				</td>
				
				<?php
			}
			?>

		</tr>
		
		<!-- ****************************************************************** -->
		
		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				Uploadarea:
				<br>&nbsp;
			</td>
			<td class="concordgeneral" colspan="2">
				&nbsp;<br>
				<a class="menuItem" href="index.php?ui=userUploads&userID=<?php echo $user->id; ?>">
					[Click here to view this user's uploaded files]
				</a>
				<br>&nbsp;
			</td>
		</tr>
		
		<!-- ****************************************************************** -->
		
		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				Delete this user account:
				<br>&nbsp;
			</td>
			<td class="concordgeneral" colspan="2">
				&nbsp;<br>
				<a class="menuItem" href="index.php?<?php 

				/* direct delete for unverified accounts; otherwise, the are-you-sure page */
				if (USER_STATUS_UNVERIFIED == $user->acct_status)
					echo "admF=execute&function=delete_unverified_user&args={$user->username}&locationAfter=" . urlencode('index.php?ui=userAdmin');
				else
					echo "ui=userDelete&checkUserDelete={$user->username}";

				?>">
					[Click here to totally delete this user's account from the system]
				</a>
				<br>&nbsp;
			</td>
		</tr>
		
		<!-- ****************************************************************** -->
	
		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				Reset all user options to default values:
				<br>&nbsp;
			</td>
			<td class="concordgeneral" colspan="2">
				<form action="index.php" method="get">
					<input type="hidden" name="admF" value="execute">
					<input type="hidden" name="function" value="reset_all_user_settings">
					<input type="hidden" name="args" value="<?php echo $user->username; ?>">
					&nbsp;<br>
					<input type="submit" value="Reset all user settings for <?php echo $user->username; ?>">
					<br>&nbsp;
				</form>
			</td>
		</tr>
		
		
		<!-- ****************************************************************** -->

		<tr>
			<td class="concordgrey">
				&nbsp;<br>
				Logout this user from all current sessions:
				<br>&nbsp;
			</td>
			<td class="concordgeneral" colspan="2">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="admF" value="execute">
					<input type="hidden" name="function" value="invalidate_user_cookie_tokens">
					<input type="hidden" name="args" value="<?php echo $user->username; ?>">
					&nbsp;<br>
					<input type="submit" value="Force full logout for <?php echo $user->username; ?>">
					<br>&nbsp;
				</form>
			</td>
		</tr>
		
		<!-- ****************************************************************** -->

		<?php
		
		// TODO - more actions under this heading!!
		// suspend; set expriy on password; force logout;  ...... etc.
		
		
		?>
		
	</table>
	
	

	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				This user's corpus-query usage stats
			</th>
		</tr>
		<tr>
			<th class="concordtable" width="60%">
				Corpus
			</th>
			<th class="concordtable">
				N of queries
			</th>
		</tr>
		
		<?php
		
		$result = do_sql_query("select corpus, count(corpus) as N from query_history where user='{$user->username}' group by corpus order by N desc");
		if (1 > mysqli_num_rows($result))
			echo "\n\t\t<tr>"
				, "\n\t\t\t<td colspan=\"2\" align=\"center\" class=\"concordgrey\"><p class=\"spacer\">&nbsp;</p>"
				, "<p>This user has performed no queries.</p>"
				, "<p class=\"spacer\">&nbsp;</p></td>"
				, "\n\t\t</tr>\n"
				;
		while ($nq = mysqli_fetch_object($result))
			echo  "\n\t\t<tr>"
				, "\n\t\t\t<td class=\"concordgeneral\">", $nq->corpus, "</td>"
				, "\n\t\t\t<td class=\"concordgeneral\">", number_format($nq->N), "</td>"
				, "\n\t\t</tr>\n"
				;
		?>
		
	</table>
	
	<hr>
	<!-- TODO: NB, this is temporary: to be handled by the new privilege system at some point...
	           All the forms under this line require a sort-out in terms of their layout, they were just bodged togerther for the moment. 
	-->
	
	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="execute">
		<input type="hidden" name="function" value="update_user_setting">
		<input type="hidden" name="locationAfter" value="index.php?ui=userView&username=<?php echo $user->username; ?>">
		
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="4" class="concordtable">
					Set user's maximum database size
				</th>
			</tr>
			<tr>
				<td colspan="4" class="concordgrey">
					&nbsp;<br>
					This limit allows you to control the amount of disk space that SQL operations - such as 
					calculating distributions or collocations - can take up at one go from each user.
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<th class="concordtable">Username</th>
				<th class="concordtable">Current limit</th>
				<th class="concordtable">New limit</th>
				<th class="concordtable">Update</th>
			</tr>
			
			<?php
			$limit_options = "<option value=\"{$user->username}#max_dbsize#100\" selected>100</option>\n";
			for ($n = 100, $i = 1; $i < 8; $i++)
			{
				$n *= 10;
				$w = number_format((float)$n);
				$limit_options .= "<option value=\"{$user->username}#max_dbsize#$n\">$w</option>\n";
			}
			?>
			<tr>
				<td class="concordgeneral"><strong><?php echo $user->username;?></strong></td>
				<td class="concordgeneral" align="center">
					<?php echo number_format((float)$user->max_dbsize); ?>
				</td>
				<td class="concordgeneral" align="center">
					<select name="args">
						<?php echo $limit_options; ?>
					</select>
				</td>
				<td class="concordgeneral" align="center"><input type="submit" value="Go!"></td>
			</tr>
		
		</table>
	
	</form>

	<?php
	
	// TODO: add privileges; add groups && group privileges ;  add indication of user files / user corpora
	// individual user privileges should have [x] boxes (as should group memberships)
	
	// TODO add delete user w. JavaScript "are you sure" (see useradmin_old for old v of form.)
	
	// TODO add the expiry functionality for both acft and password. 
	
}

function do_adm_ui_useradmin()
{
	do_adm_bitui_useroverview();
	do_adm_bitui_usersearchform();

	// TODO functionalise create user form  ; make the javascript less wasteful
	// alos create sep funciton for user logout fgorm??? 
	
	global $Config;
	
	/* before we start, add the javascript function that inserts password candidates */
	echo print_javascript_for_password_insert();
	
	?>
	
	<form class="greyoutOnSubmit" action="useracct-act.php" method="post">
		<input type="hidden" name="userAction" value="newUser">
		<input type="hidden" name="userFunctionFromAdmin" value="1">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="3" class="concordtable">
					Create new user
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter the username you wish to create:
				</td>
				<td class="concordgeneral">
					<input type="text" name="newUsername" tabindex="31" width="30" onKeyUp="check_c_word(this)">
				</td>
				<td class="concordgeneral" rowspan="4" align="center">
					<input type="submit" value="Create user account" tabindex="35">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter a new password for the specified user:
				</td>
				<td class="concordgeneral">
					<input type="text" id="newPassword" name="newPassword" tabindex="32" width="50">
					<a id="suggestPasswordButton" class="menuItem hasToolTip" tabindex="33" 
						data-tooltip="Suggest a password" onclick="insert_password_suggestion()">
						[+]
					</a>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter the user's email address:
				</td>
				<td class="concordgeneral">
					<input type="email" name="newEmail" tabindex="34" width="30">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Send verification email?
				</td>
				<td class="concordgeneral">
					<select name="verifyType">
						<?php echo ($Config->cqpweb_no_internet ? '' : '<option value="yes">Yes, send a verification email</option>'), "\n"; ?>
						<option value="no:Verify" selected>No, auto-verify the account</option>
						<option value="no:DontVerify"     >No, and leave the account unverified</option>
					</select>
				</td>
			</tr>
		</table>
	</form>
		
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="3" class="concordtable">
				Create a batch of user accounts
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Enter the root for the batch of usernames:
				<form class="greyoutOnSubmit" id="newBatchOfUsersForm" action="index.php" method="get"></form>
				<input form="newBatchOfUsersForm" type="hidden" name="admF" value="newBatchOfUsers">
			</td>
			<td class="concordgeneral">
				<input form="newBatchOfUsersForm" type="text" name="newUsername" width="30" onKeyUp="check_c_word(this)">
			</td>
			<td class="concordgeneral" rowspan="4">
				<input form="newBatchOfUsersForm" type="submit" value="Create batch of users">
				<br>&nbsp;<br>
				<input form="newBatchOfUsersForm" type="reset" value="Clear form">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Enter the number of accounts in the batch:
				<br>
				<em>(Usernames will have the numbers 1 to N appended to them)</em>
			</td>
			<td class="concordgeneral">
				<input form="newBatchOfUsersForm" type="number" name="sizeOfBatch" width="30">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Enter a password for all accounts in the batch:
			</td>
			<td class="concordgeneral">
				<input form="newBatchOfUsersForm" type="text" name="newPassword" width="30">
			</td>
		</tr>

		<tr>
			<td class="concordgeneral">
				Select a group for the new users to be assigned to:
			</td>
			<td class="concordgeneral">
				<select form="newBatchOfUsersForm" name="batchAutogroup">
					<option value="" selected>Do not assign new users to a group</option>
					<?php 
					foreach(get_list_of_groups() as $g)
						if ($g != 'everybody' && $g != 'superusers')
							echo '<option>', $g, "\n";
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="3" class="concordgrey">
				<strong>Note</strong>: Use this function with caution, as it is a potential security hole (password known to more than one person)!
				<br>&nbsp;<br>
				A typical use-case would be to create a set of accounts for a demonstration, then delete them (automatable via commandline
				but not currently in the web interface - sorry).
				<br>&nbsp;<br>
				In future it will be possible to set an expiry date on user accounts: batch-created accounts will then have a nearby
				expiry set on them. However, this is not possible yet, so rememebr to delete the accounts.
			</td>
		</tr>
	</table>
	
	<?php
	
	list($n_sessions, $n_users) = mysqli_fetch_row(do_sql_query("select count(*), count(distinct(user_id)) from user_cookie_tokens"));
		
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Logged in users
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" width="70%">
				Number of users currently logged in:
			</td>
			<td class="concordgeneral" align="right">
				<?php echo number_format($n_users), "\n"; ?>
			</td>
		<tr>
			<td class="concordgeneral">
				Number of log in sessions:
				<br>
				(one user may have multiple sessions)
			</td>
			<td class="concordgeneral" align="right">
				<?php echo number_format($n_sessions), "\n"; ?>
			</td>
		</tr>
	</table>
		
	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="execute">
		<input type="hidden" name="function" value="invalidate_all_cookie_tokens">
		<input type="hidden" name="locationAfter" value="../">
		<table class="concordtable fullwidth">
			<tr>
				<td colspan="2" class="concordgrey" align="center">
					Click below to perform a mass logout of all currently logged-in users
					(your own current session included)
				</td>
			</tr>
			<tr>
				<td colspan="2" class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p>
						<input type="submit" value="Log out all currently logged-in users">
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
	</form>
	
	
	<?php
}

function printquery_useradmin_old()
{
	global $Config;
	
	$array_of_users = get_list_of_users();
	
	$user_list_as_options = '';
	foreach ($array_of_users as $a)
		$user_list_as_options .= "<option>$a</option>\n";
	
	/* before we start, add the javascript function that inserts password candidates */
	
	echo print_javascript_for_password_insert();
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="3" class="concordtable">
				Create new user
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Enter the username you wish to create:
				<form id="newUserForm" class="greyoutOnSubmit" action="useracct-act.php" method="post"></form>
				<input form="newUserForm" type="hidden" name="userAction" value="newUser">
			</td>
			<td class="concordgeneral">
				<input form="newUserForm" type="text" name="newUsername" tabindex="1" width="30" onKeyUp="check_c_word(this)">
			</td>
			<td class="concordgeneral" rowspan="4">
				<input form="newUserForm" type="submit" value="Create user account" tabindex="5">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Enter a new password for the specified user:
			</td>
			<td class="concordgeneral">
				<input form="newUserForm" type="text" id="newPassword" name="newPassword" tabindex="2" width="50">
				<a id="suggestPasswordButton" class="menuItem hasToolTip"  tabindex="3"
					data-tooltip="Suggest a password" onClick="insert_password_suggestion()">
					[+]
				</a>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Enter the user's email address:
			</td>
			<td class="concordgeneral">
				<input form="newUserForm" type="text" name="newEmail" tabindex="4" width="30">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Send verification email?
			</td>
			<td class="concordgeneral">
				<select form="newUserForm" name="verifyType">
					<?php echo ($Config->cqpweb_no_internet ? '' : '<option value="yes">Yes, send a verification email</option>'), "\n"; ?>
					<option value="no:Verify" selected>No, auto-verify the account</option>
					<option value="no:DontVerify"     >No, and leave the account unverified</option>
				</select>
			</td>
		</tr>
		
		
		<tr>
			<th colspan="3" class="concordtable">
				Reset a user's password
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Select the user for password reset:
				<form id="resetUserPasswordForm" class="greyoutOnSubmit" action="useracct-act.php" method="post"></form>
				<input form="resetUserPasswordForm" type="hidden" name="userAction" value="resetUserPassword">
			</td>
			<td class="concordgeneral">
				<select form="resetUserPasswordForm" name="userForPasswordReset">
					<option>Select user ....</option>
					<?php echo $user_list_as_options; ?>
				</select>
			</td>
			<td class="concordgeneral" rowspan="2">
				<input form="resetUserPasswordForm" type="submit" value="Reset this user's password">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Enter new password:
			</td>
			<td class="concordgeneral">
				<input form="resetUserPasswordForm" type="text" name="newPassword" width="50">
			</td>
		</tr>
		<tr>
			<th colspan="3" class="concordtable">
				Delete a user account
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Select a user to delete:
				<form id="deleteUserForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="deleteUserForm" type="hidden" name="admF" value="deleteUser">
			</td>
			<td class="concordgeneral">
				<select form="deleteUserForm" name="userToDelete">
					<option>Select user ....</option>
					<?php echo $user_list_as_options; ?>
				</select>
			</td>
			<td class="concordgeneral">
				<input form="deleteUserForm" type="submit" value="Delete this user's account">
			</td>
		</tr>
		<?php
		// TODO add JavaScript Are You Sure? Pop up to the submission button of this form 
		?>
			
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="4" class="concordtable">
				Set user's maximum database size
			</th>
		</tr>
		<tr>
			<td colspan="4" class="concordgrey">
				&nbsp;<br>
				This limit allows you to control the amount of disk space that SQL operations - such as 
				calculating distributions or collocations - can take up at one go from each user.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable">Username</th>
			<th class="concordtable">Current limit</th>
			<th class="concordtable">New limit</th>
			<th class="concordtable">Update</th>
		</tr>
		
		<?php
		$result = do_sql_query("SELECT username, max_dbsize from user_info");
		
		while ($r = mysqli_fetch_assoc($result))
		{
			$limit_options 
				= "<option value=\"{$r['username']}#max_dbsize#100\" selected>100</option>\n";
			for ($n = 100, $i = 1; $i < 8; $i++)
			{
// this was just lazy of me.... oh well, this code will be gone soon enough.
				$n *= 10;
				$w = number_format((float)$n);
				$limit_options .= "<option value=\"{$r['username']}#max_dbsize#$n\">$w</option>\n";
			}
			?>
			<tr>
				<td class="concordgeneral">
					<strong><?php echo $r['username'];?></strong>
					<form id="updateUserDbsizeForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
					<input form="updateUserDbsizeForm" type="hidden" name="admF" value="execute">
					<input form="updateUserDbsizeForm" type="hidden" name="function" value="update_user_setting">
					<input form="updateUserDbsizeForm" type="hidden" name="locationAfter" value="index.php?ui=userAdmin">
				</td>
				<td class="concordgeneral" align="center">
					<?php echo number_format((float)$r['max_dbsize']), "\n"; ?>
				</td>
				<td class="concordgeneral" align="center">
					<select form="updateUserDbsizeForm" name="args">
						<?php echo $limit_options; ?>
					</select>
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateUserDbsizeForm" type="submit" value="Go!">
				</td>
			</tr>
			<?php
		}
		?>
		
	</table>

	<?php
}


function do_adm_ui_userdelete()
{
	global $Config;
	
	?>
	
	<table class="concordtable fullwidth">
		<?php 
		
		/* we expect this form to be accessed from another form which populates this member of $_GET */
		if (empty($_GET['checkUserDelete']))
			exiterror("No ID specified for account to delete!");
		else
			$checkname = cqpweb_handle_enforce($_GET['checkUserDelete']);
		
		if (false === ($user = get_user_info($checkname)))
			exiterror("User $checkname doesn't exist: account can't be deleted.");
			
		if (user_is_superuser($checkname))
			exiterror("It's not possible to delete superuser accounts.");
		
		/* all is OK, so print the form. */
		
		?>
		
		<tr>
			<th class="concordtable">
				Totally delete account with username &ldquo;<?php echo $checkname; ?>&rdquo;? 
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<p class="spacer">&nbsp;</p>
				<p>
					Current account status: <strong><?php echo $Config->user_account_status_description_map[$user->acct_status]; ?></strong>
				</p>
				<p>
					Real name:  <em><?php echo empty($user->realname)    ? '[unset]' : escape_html($user->realname);    ?></em>
					|
					Affilation: <em><?php echo empty($user->affiliation) ? '[unset]' : escape_html($user->affiliation); ?></em>
					|
					Email:      <em><?php echo empty($user->email)       ? '[unset]' : escape_html($user->email);       ?></em>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" align="center">
				<p class="spacer">&nbsp;</p>
				<p>Are you sure you want to do this?</p>
				<p>Deleting a user account also deletes <strong>all</strong> their saved data and uploaded files.</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="admF" value="deleteUser">
					<input type="hidden" name="userToDelete" value="<?php echo $checkname; ?>">
					<p class="spacer">&nbsp;</p>
					<p>
						<input type="checkbox" name="sureyouwantto" value="yes">
						Yes, I'm sure I want to do this.
					</p>
					<p class="spacer">&nbsp;</p>
					<p><input type="submit" value="I am definitely sure I want to delete this user's account."></p>
					<p class="spacer">&nbsp;</p>
				</form>
			</td>
		</tr>
	</table>					
		
	<?php	
}


function do_adm_ui_groupadmin()
{
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="7" class="concordtable">
				Manage user groups
			</th>
		</tr>
		<tr>
			<th class="concordtable">ID</th>
			<th class="concordtable">Group</th>
			<th class="concordtable">Description</th>
			<th class="concordtable">Auto-add regex</th>
			<th class="concordtable">Update</th>
			<th class="concordtable">Delete</th>
		</tr>
	<?php

	foreach (get_all_groups_info() as $group)
	{
		?>
		
		<tr>
			<td class="concordgeneral" align="center">
				<strong><?php echo $group->id; ?></strong>
				<form id="updateGroupInfoForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="updateGroupInfoForm" type="hidden" name="admF" value="updateGroupInfo">
				<input form="updateGroupInfoForm" type="hidden" name="groupToUpdate" value="<?php echo $group->group_name; ?>">
			</td>
			<td class="concordgeneral" align="center">
				<strong><?php echo $group->group_name; ?></strong>
			</td>
			<td class="concordgeneral"  align="center">
				<?php
				if ($group->group_name == 'everybody')
					echo '<em>Group to which all users automatically belong.</em>';
				else if ($group->group_name == 'superusers')
					echo '<em>Only admin accounts belong to this group.</em>';
				else
					echo '<input form="updateGroupInfoForm" type="text" maxlength="255" size="50" name="newGroupDesc" value="'
						, escape_html($group->description)
						, '">'
						;
				echo "\n";
				?>
			</td>
			
			<?php
			if ($group->group_name == 'superusers' || $group->group_name == 'everybody')
				echo '<td class="concordgeneral" colspan="3">&nbsp;</td>', "\n";				
			else
			{
				?>
				<td class="concordgeneral"  align="center">
					<input form="updateGroupInfoForm" type="text" maxlength="1024" size="50" name="newGroupAutojoinRegex" value="<?php
						echo escape_html($group->autojoin_regex);
					?>">
				</td>
				<td class="concordgeneral" align="center">
					<input form="updateGroupInfoForm" type="submit" value="Update">
				</td>
				<?php
			}
			?>
			
			<?php 
			if ( ! ($group->group_name == 'superusers' || $group->group_name == 'everybody') )
			{
				?>
				
				<td class="concordgeneral" align="center">
					<a class="menuItem" 
						href="index.php?admF=execute&function=delete_group&args=<?php echo $group->group_name, '&locationAfter=', urlencode('index.php?ui=groupAdmin');
						?>">[x]</a>
				</td>
				
				<?php
			}
			echo "\n\t\t\t</tr>\n";
		}
		?>

		<tr>
			<td class="concordgrey" colspan="6">
				&nbsp;<br>
				The &ldquo;description&rdquo; will be visible in various places in the user interface (to users as well
				as to system administrators).
				<br>&nbsp;<br>
				The &ldquo;auto-add regex&rdquo; determines which users will be added automatically to this group at time of
				account creation.
				<br>&nbsp;<br>
				Any new user whose email address matches the regular expression given here will automatically be added to
				the group in question. For example, if you set the regex to <strong>(\.edu|\.ac\.uk)$</strong> then all users with
				email addresses that end in .edu or .ac.uk (i.e. US and UK academic addresses) will be added to the group
				automatically. Regexes use <a href="" target="_blank">PCRE syntax</a>.
				<br>&nbsp;<br>
				(Note this only affects <em>new</em> user accounts, i.e. if you add or change a regex, existing accounts
				will <em>not</em> be added to the group. You can perform a 
				<em><a href="index.php?ui=groupMembership">bulk add</a></em> 
				to accomplish that.)
				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="execute">
		<input type="hidden" name="function" value="add_new_group">
		<input type="hidden" name="locationAfter" value="index.php?ui=groupAdmin">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="3" class="concordtable">
					Add new group
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<?php /* TODO, don't we need &nbsp; in the following??? */ ?>
					<br>
					Enter the name for the new group:
					<br>
					&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					<br>
					<input type="text" maxlength="20" name="args" onKeyUp="check_c_word(this)" >
					<br>
					&nbsp;
				<td class="concordgeneral" align="center">
					<br>
					<input type="submit" value="Add this group to the system">
					<br>
					&nbsp;
				</td>
			</tr>
		</table>
	</form>
	
	<?php
}

// Todo - if there are many users on the system, this gets VERY unwieldy, verty fast.
// tODO make dynamic. Only have 2 data objects with groups of iseras. 
// swiotch to new model if there are more than 560 users in the module - for that, use a js picker like the userrserarch.
// if wer do it dynamically in browser, we don;'t have ot do it for every single group. Since most will be ignored!
// So: create a user-type-a-bit for the "add" fierld ; and for the "remove" field, proceed as previously. 
function do_adm_ui_groupmembership()
{
	do_adm_bitui_quicksearch_css();
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="7" class="concordtable">
				Manage user groups
			</th>
		</tr>
		<tr>
			<th class="concordtable">Group</th>
			<th class="concordtable">Members</th>
			<th class="concordtable" colspan="2">Add member</th>
			<th class="concordtable" colspan="2">Remove member</th>	
		</tr>
	<?php
	
	$full_list_of_users = get_list_of_users();
	
	$use_easy_select_forms = (count($full_list_of_users) < 150);
	$group_list = get_list_of_groups();
	
	foreach ($group_list as $group)
	{
		?>
		
		<tr>
			<td class="concordgeneral"><strong><?php echo $group; ?></strong></td>
			<td class="concordgeneral"><?php
				$member_list = list_users_in_group($group);
				natcasesort($member_list);
				$i = 0;
				if ($group == 'everybody')
					echo '<em>All users are members of this group.</em>';
				else
				{
					foreach ($member_list as $member)
					{
						echo $member , ' ';
						$i++;
						if ($i == 5)
						{
							echo "<br>\n";
							$i = 0;
						}
					}
					if (empty($member_list))
						echo '&nbsp;';
				}				
			?></td>
		
			<?php
			if ($group == 'superusers' || $group == 'everybody')
			{
				echo '<td class="concordgeneral" colspan="4">&nbsp;</td>', "\n\t\t\t</tr>\n";
				continue;
			}
			
			$members_not_in_group = array_diff($full_list_of_users, $member_list);
			natcasesort($members_not_in_group);
		
			if ($use_easy_select_forms)
			{
				$options = "<option>[Select user from list]</option>\n";
				foreach ($members_not_in_group as $m)
					$options .= "\t\t\t\t\t<option>$m</option>\n";
				?>
				<td class="concordgeneral" align="center">
					<form id="addUserToGroupForm:<?php echo $group;?>" class="greyoutOnSubmit" action="index.php" method="get"></form>
					<input form="addUserToGroupForm:<?php echo $group;?>" type="hidden" name="admF" value="addUserToGroup">
					<input form="addUserToGroupForm:<?php echo $group;?>" type="hidden" name="groupToAddTo" value="<?php echo $group;?>">
					<select form="addUserToGroupForm:<?php echo $group;?>" name="userToAdd">
						<?php echo $options; ?>
					</select>
				</td>
				<td class="concordgeneral" align="center">
					<input form="addUserToGroupForm:<?php echo $group;?>" type="submit" value="Add user to group">
				</td>
				<?php
				$options = "<option>[Select user from list]</option>\n";
				foreach ($member_list as $m)
					$options .= "\t\t\t\t\t<option>$m</option>\n";
 				echo "\n";
 				?>
				<td class="concordgeneral" align="center">
					<form id="removeUserFromGroupForm:<?php echo $group; ?>" class="greyoutOnSubmit" action="index.php" method="get"></form>
					<input form="removeUserFromGroupForm:<?php echo $group; ?>" type="hidden" name="admF" value="removeUserFromGroup">
					<input form="removeUserFromGroupForm:<?php echo $group; ?>" type="hidden" name="groupToRemoveFrom" value="<?php echo $group; ?>">
					<select form="removeUserFromGroupForm:<?php echo $group; ?>" name="userToRemove">
						<?php echo $options; ?>
					</select>
				</td>
				<td class="concordgeneral" align="center">
					<input form="removeUserFromGroupForm:<?php echo $group; ?>" type="submit" value="Remove user from group">
				</td>
				<?php
				echo "\n";
			}
			else
			{
				?>
	
				<td class="concordgeneral" align="center">
					<form id="grp_<?php echo $group; ?>" class="greyoutOnSubmit" action="index.php" method="get"></form>
					<input form="grp_<?php echo $group; ?>" type="hidden" name="admF" value="addUserToGroup">
					<input form="grp_<?php echo $group; ?>" type="hidden" name="groupToAddTo" value="<?php echo $group; ?>">
	
					<?php
					do_adm_bitui_user_quicksearch_form('userToAdd', 'insert', '', "grp_$group");
					?>
	
				</td>
				<td class="concordgeneral" align="center">
					<input form="grp_<?php echo $group; ?>" type="submit" value="Add user to group">
				</td>
				<td class="concordgeneral" align="center">
					<form id="removeUserFromGroupForm:<?php echo $group; ?>" class="greyoutOnSubmit" action="index.php" method="get"></form>
					<input form="removeUserFromGroupForm:<?php echo $group; ?>" type="hidden" name="admF" value="removeUserFromGroup">
					<input form="removeUserFromGroupForm:<?php echo $group; ?>" type="hidden" name="groupToRemoveFrom" value="<?php echo $group; ?>">
					<select form="removeUserFromGroupForm:<?php echo $group; ?>" class="removeFromGroupDropDown" name="userToRemove"
						data-member-usernames="<?php echo implode('|', $member_list); ?>"
					>
						<option value="">[Select user from list]</option>
					</select>
				</td>
				<td class="concordgeneral" align="center">
					<input form="removeUserFromGroupForm:<?php echo $group; ?>" type="submit" value="Remove user from group">
				</td>
	
				<?php
			}
			?>
			
		</tr>
		
		<?php
//TODO - maybe the way to do it uiis with "data-member-usernames" for all current memberws in group. Then this can be diff'ed against some globally placed vruiable. 
	}
	?>
	
	</table>

	<?php

	$g_opts = '';
	
	foreach ($group_list as $g)
		if ($g != 'superusers' && $g != 'everybody')
			$g_opts .= "\n\t\t\t\t\t\t<option value=\"$g\">$g</option>\n";
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Bulk Add:
				<br>
				<em>Add users to group by email address pattern-match</em>
			</th>
		</tr>
		<tr>
			<td class="concordgrey" width="50%">
				<form id="groupRegexRerunForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="groupRegexRerunForm" type="hidden" name="admF" value="groupRegexRerun">
				<p>&nbsp;</p>
				<p>
					Apply group's stored pattern-match to existing users
					<br>&nbsp;<br>
					<i>by default, the group auto-add regex only applies to <strong>new</strong>
					accounts; this function adds any existing users whose emails match
					that regex to the group in question.</i>
				</p>
				<p>&nbsp;</p>
			</td>
			<td class="concordgeneral">
				<p>&nbsp;</p>
				<p>Select group:</p>
				<select form="groupRegexRerunForm" name="group">
					<option value="">[Select a group...]</option>
					<?php echo $g_opts, "\n"; ?>
				</select>
				<p>
					<input form="groupRegexRerunForm" type="submit" value="Click here to run group regex against existing users">
				</p>
				<p>&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">
				<p>&nbsp;</p>
				<p>Apply one-off custom regex to all existing users:</p>
				<p>&nbsp;</p>
				<form id="groupRegexApplyCustomForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="groupRegexApplyCustomForm" type="hidden" name="admF" value="groupRegexApplyCustom">
			</td>
			<td class="concordgeneral">
				<p>&nbsp;</p>
				
				<p>Select group:</p>
				
				<select form="groupRegexApplyCustomForm" name="group">
					<option value="">[Select a group...]</option>
					<?php echo $g_opts, "\n"; ?>
				</select>
				
				<p>Enter the regex to apply:</p>
				<p>					
					<input form="groupRegexApplyCustomForm" type="text" maxlength="255" size="50" name="regex">
				</p>
				<p>
					<input form="groupRegexApplyCustomForm" type="submit" value="Click here to add all users matching this regex to the group specified">
				</p>
				<p>&nbsp;</p>
			</td>
		</tr>
	</table>
	<?php
	
}





function do_adm_ui_privilegeadmin()
{
	global $Config;
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="6">
				Manage privileges
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="6">
				&nbsp;<br>
				&ldquo;Privileges&rdquo; are rights to use different aspects of the CQPweb system: corpora,
				plugins, and so on. Once defined, privileges can be assigned (&ldquo;granted&rdquo;)
				individually to users and/or collectively to groups of users.
				<br>&nbsp;<br>
				What users are able to do when logged on to CQPweb is defined by the privileges that have
				been granted to them.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="6">
				Existing privileges
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				ID
			</th>
			<th class="concordtable">
				Description
			</th>
			<th class="concordtable">
				Type
			</th>
			<th class="concordtable">
				Scope
			</th>
			<th class="concordtable" colspan="2">
				Actions
			</th>
		</tr>
		
		<?php
		foreach (get_all_privileges_info() as $p)
		{
			$scope_cell_string = print_privilege_scope_as_html($p->type, $p->scope_object);
			
			echo "<tr>"
				, "<td class=\"concordgeneral\" align=\"center\">{$p->id}</td>"
				, "<td class=\"concordgeneral\"><em>{$p->description}</em></td>"
				, "<td class=\"concordgeneral\">{$Config->privilege_type_descriptions[$p->type]}</td>"
				, "<td class=\"concordgeneral\">$scope_cell_string</td>"
				, "<td class=\"concordgeneral\" align=\"center\">"
					, "<a class=\"menuItem\" href=\"index.php?ui=editPrivilege&privilege={$p->id}\">[Edit]</a>"
				, "</td>"
				, "<td class=\"concordgeneral\" align=\"center\">"
					, "<a class=\"menuItem\" href=\"index.php?admF=deletePrivilege&privilege={$p->id}\">[Delete]</a>"
				, "</td>"
				, "</tr>\n"
				;
		}

		?>
		
	</table>
	
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="3">
				Create a new privilege
			</th>
		</tr>

		<tr>
			<td class="concordgrey" rowspan="4" width="30%">
				New corpus access privilege
				<form id="newCorpusPrivilegeForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="newCorpusPrivilegeForm" type="hidden" name="admF" value="newCorpusPrivilege">
			</td>
			<td class="concordgeneral">Description</td>
			<td class="concordgeneral">
				<input form="newCorpusPrivilegeForm" type="text" name="description">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Access level</td>
			<td class="concordgeneral">
				<select form="newCorpusPrivilegeForm" name="privilegeType">
					<?php
					echo  '<option value="', PRIVILEGE_TYPE_CORPUS_FULL,       '">Full</option>'
						, '<option value="', PRIVILEGE_TYPE_CORPUS_NORMAL,     '" selected>Normal</option>'
						, '<option value="', PRIVILEGE_TYPE_CORPUS_RESTRICTED, '">Restricted</option>'
						, "\n"
						;
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Initial corpus</td>
			<td class="concordgeneral">
				<select form="newCorpusPrivilegeForm" name="corpus">
					<option selected>Select corpus...</option>
					<?php
					foreach (list_corpora() as $c)
						echo '<option>', $c, "</option>\n";
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<input form="newCorpusPrivilegeForm" type="submit" value="Create privilege!">
			</td>
		</tr>
		
		<?php /* TODO empty rows like this prob will not be needed when the form elements are correctly placed. */ ?>
		<tr><th class="concordtable" colspan="3"></th></tr>
		
		<tr>
			<td class="concordgrey" rowspan="3" width="30%">
				New frequency list privilege
				<form id="newFreqlistPrivilegeForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="newFreqlistPrivilegeForm" type="hidden" name="admF" value="newFreqlistPrivilege">
				<input form="newFreqlistPrivilegeForm" type="hidden" name="privilegeType" value="<?php echo PRIVILEGE_TYPE_FREQLIST_CREATE; ?>">
			</td>
			<td class="concordgeneral">Description</td>
			<td class="concordgeneral">
				<input form="newFreqlistPrivilegeForm" type="text" name="description">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Subcorpus size limit<br>(in tokens)</td>
			<td class="concordgeneral">
				<input form="newFreqlistPrivilegeForm" type="number" name="nTokens">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<input form="newFreqlistPrivilegeForm" type="submit" value="Create privilege!">
			</td>
		</tr>
		
		<tr><th class="concordtable" colspan="3"></th></tr>
		
		<tr>
			<td class="concordgrey" rowspan="3" width="30%">
				New file upload privilege
				<br>&nbsp;<br>
				(This is the maximum size for <em>each uploaded file</em>.)
				<form id="newUploadPrivilegeForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="newUploadPrivilegeForm" type="hidden" name="admF" value="newUploadPrivilege">
				<input form="newUploadPrivilegeForm" type="hidden" name="privilegeType" value="<?php echo PRIVILEGE_TYPE_UPLOAD_FILE; ?>">
			</td>
			<td class="concordgeneral">Description</td>
			<td class="concordgeneral">
				<input form="newUploadPrivilegeForm" type="text" name="description">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Maximum allowed upload<br>(in bytes)</td>
			<td class="concordgeneral">
				<input form="newUploadPrivilegeForm" type="number" name="nBytes">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<input form="newUploadPrivilegeForm" type="submit" value="Create privilege!">
			</td>
		</tr>
		
		<tr><th class="concordtable" colspan="3"></th></tr>
		
		<tr>
			<td class="concordgrey" rowspan="3" width="30%">
				New filestore privilege for uploaded files
				<br>&nbsp;<br>
				(This limits the <em>total</em> amount of uploaded data that can be stored.)
				<form id="newDiskForUploadsPrivilegeForm" class="greyoutOnSubmit" action="index.php" method="GET"></form>
				<input form="newDiskForUploadsPrivilegeForm" type="hidden" name="admF" value="newDiskForUploadsPrivilege">
				<input form="newDiskForUploadsPrivilegeForm" type="hidden" name="privilegeType" value="<?php echo PRIVILEGE_TYPE_DISK_FOR_UPLOADS; ?>">
			</td>
			<td class="concordgeneral">Description</td>
			<td class="concordgeneral">
				<input form="newDiskForUploadsPrivilegeForm" type="text" name="description">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Upload area space allocated<br>(in bytes)</td>
			<td class="concordgeneral">
				<input form="newDiskForUploadsPrivilegeForm" type="number" name="nBytes">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<input form="newDiskForUploadsPrivilegeForm" type="submit" value="Create privilege!">
			</td>
		</tr>
		
		<tr><th class="concordtable" colspan="3"></th></tr>
		
		<tr>
			<td class="concordgrey" rowspan="4" width="30%">
				New install-corpus privilege
				<br>&nbsp;<br>
				(Allows corpus creation up to a given size with a given plugin.)
				<form id="newInstallPrivilegeForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="newInstallPrivilegeForm" type="hidden" name="admF" value="newInstallPrivilege">
				<input form="newInstallPrivilegeForm" type="hidden" name="privilegeType" value="<?php echo PRIVILEGE_TYPE_INSTALL_CORPUS; ?>">
			</td>
			<td class="concordgeneral">Description</td>
			<td class="concordgeneral">
				<input form="newInstallPrivilegeForm" type="text" name="description">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Plugin to use as installer</td>
			<td class="concordgeneral">
				<select form="newInstallPrivilegeForm" name="pluginId">
					<option value="~~" disabled selected>Choose an installer plugin ...</option>
					<?php		
					// TODO - make it impossible to submit the form if the "empty" plugin is the one chosen (~~)
					foreach (get_all_plugins_info(PLUGIN_TYPE_CORPUSINSTALLER) as $p)
						echo "<option value=\"{$p->id}\">#$p->id: ", escape_html($p->description), "\n\t\t\t\t\t\t";
					echo "\n";
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Maximum corpus size<br>(in tokens)</td>
			<td class="concordgeneral">
				<input form="newInstallPrivilegeForm" type="number" name="nTokens">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<input form="newInstallPrivilegeForm" type="submit" value="Create privilege!">
			</td>
		</tr>
		
		<tr><th class="concordtable" colspan="3"></th></tr>
		
		<tr>
			<td class="concordgrey" rowspan="3">
				New filestore privilege for user's installed corpora
				<br>&nbsp;<br>
				(This limits the <em>total</em> disk space that the user can fill with installed corpora.)
				<form id="newDiskForCorpusPrivilegeForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="newDiskForCorpusPrivilegeForm" type="hidden" name="privilegeType" value="<?php echo PRIVILEGE_TYPE_DISK_FOR_CORPUS; ?>">
				<input form="newDiskForCorpusPrivilegeForm" type="hidden" name="admF" value="newDiskForCorpusPrivilege">
			</td>
			<td class="concordgeneral">Description</td>
			<td class="concordgeneral">
				<input form="newDiskForCorpusPrivilegeForm" type="text" name="description">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">User-corpus area space allocated<br>(in bytes)</td>
			<td class="concordgeneral">
				<input form="newDiskForCorpusPrivilegeForm" type="number" name="nBytes">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<input form="newDiskForCorpusPrivilegeForm" type="submit" value="Create privilege!">
			</td>
		</tr>
		
		<tr><th class="concordtable" colspan="3"></th></tr>
		
		<tr>
			<td class="concordgrey" rowspan="3" width="30%">
				New extra runtime privilege
				<form id="newExtraRuntimePrivilegeForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="newExtraRuntimePrivilegeForm" type="hidden" name="admF" value="newExtraRuntimePrivilege">
				<input form="newExtraRuntimePrivilegeForm" type="hidden" name="privilegeType" value="<?php echo PRIVILEGE_TYPE_EXTRA_RUNTIME; ?>">
			</td>
			<td class="concordgeneral">Description</td>
			<td class="concordgeneral">
				<input form="newExtraRuntimePrivilegeForm" type="text" name="description">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Extra runtime granted<br>(in seconds)</td>
			<td class="concordgeneral">
				<input form="newExtraRuntimePrivilegeForm" type="number" name="nSecs">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<input form="newExtraRuntimePrivilegeForm" type="submit" value="Create privilege!">
			</td>
		</tr>
		
		<tr><th class="concordtable" colspan="3"></th></tr>
		
			<tr>
				<td class="concordgrey" rowspan="2" width="30%">
					New CQP binary-file privilege
					<form id="newCqpBinaryPrivilegeForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
					<input form="newCqpBinaryPrivilegeForm" type="hidden" name="admF" value="newCqpBinaryPrivilege">
					<input form="newCqpBinaryPrivilegeForm" type="hidden" name="privilegeType" value="<?php echo PRIVILEGE_TYPE_CQP_BINARY_FILE; ?>">
				</td>
				<td class="concordgeneral">Description</td>
				<td class="concordgeneral">
					<input form="newCqpBinaryPrivilegeForm" type="text" name="description">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					<input form="newCqpBinaryPrivilegeForm" type="submit" value="Create privilege!">
				</td>
			</tr>
		
		<tr><th class="concordtable" colspan="3"></th></tr>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">
				Generate default corpus privileges
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				&nbsp;<br>
				The &ldquo;default&rdquo; corpus privileges are:
				<ul>
					<li>A <em>full access</em> privilege for each corpus;</li>
					<li>A <em>normal access</em> privilege for each corpus;</li>
					<li>A <em>restricted access</em> privilege for each corpus.</li>
				</ul>
				Generating default privileges creates these three privileges for each corpus on the system,
				if those privileges do not exist already. Existing privileges are not affected.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				<form id="generateDefaultPrivilegesForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="generateDefaultPrivilegesForm" type="hidden" name="admF" value="generateDefaultPrivileges">
				&nbsp;<br>
				<strong>Generate default privileges for corpus...</strong>
				<select form="generateDefaultPrivilegesForm" name="corpus">
					<option selected>[Select a corpus...]</option>
					<?php
					foreach(list_corpora() as $c)
						echo "\t\t\t\t\t\t<option value=\"$c\">$c</option>\n";
					?>
				</select>
				<br>&nbsp;
			</td>
			<td class="concordgeneral" align="center">
				&nbsp;<br>
				<input form="generateDefaultPrivilegesForm" type="submit" value="Generate default privileges for this corpus">
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="admF" value="generateDefaultPrivileges">
					<input type="hidden" name="corpus" value="~~all~~">
					&nbsp;<br>
					<input type="submit" value="Generate default privileges for all corpora">
					<br>&nbsp;
				</form>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				&nbsp;<br>
				The other &ldquo;default&rdquo; privileges are for the frequency-list creation and file upload.
				<br>&nbsp;<br>
				When these defaults are generated, four levels of privilege are added for frequency-list creation.
				Users can only build frequency lists for subcorpora if they have a privilege that
				covers a subcorpus of that size. The automatically-created levels are one, ten, 
				twenty-five and one hundred million tokens. 
				<br>&nbsp;<br>
				Similarly, four levels of privilege for file upload are added. These affect all functions where
				a user uploads a file (for example, for query upload) and set a limit on the size of file that can
				be inserted. The automatically-created levels are 0.5MB, 1MB, and 2MB. 
				<br>&nbsp;<br>
				At least one of each of these two types of privilege should be granted to the "everybody" group, 
				or some users may not be able to create frequency lists / upload files at all.  
				<br>&nbsp;<br>
				The amount of disk space that the user can fill with their user corpora is constrained likewise,
				for the same reason and in the same way.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="admF" value="generateDefaultPrivileges">
					<input type="hidden" name="corpus" value="~~noncorpus~~">
					&nbsp;<br>
					<input type="submit" value="Generate default privileges for all frequency lists / uploading / disk space">
					<br>&nbsp;
				</form>
			</td>
		</tr>
	</table>
	<?php
}

function do_adm_ui_editprivilege()
{
	global $Config;
	
	if (!isset($_GET['privilege']))
		exiterror("No privilege ID to edit was supplied!");
	$p = (int) $_GET['privilege'];
	
	$info = get_privilege_info($p);
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">
				Editing privilege # <?php echo $p; ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p>
					<strong>Privilege type</strong>: <?php 
						echo '<em>', $Config->privilege_type_descriptions[$info->type], '</em>';
						if (PRIVILEGE_TYPE_INSTALL_CORPUS == $info->type)
						{
							$plugin = get_plugin_info($info->scope_object->plugin_id);
							echo ' (covering plugin # ', $info->scope_object->plugin_id, ': ', $plugin->description, " )";
						}
					?>.
				</p>
				<p class="spacer">&nbsp;</p>
				<?php
				
				?>
				<p>You can change the description of any kind of privilege.</p>
				<p class="spacer">&nbsp;</p>
				<p>
					<?php
					switch ($info->type)
					{
					case PRIVILEGE_TYPE_CORPUS_FULL:
					case PRIVILEGE_TYPE_CORPUS_NORMAL:
					case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
						echo "You can also add or remove a corpus from the list of corpora that this privilege applies to, "
							, "or change the access level (among normal, restricted, full).\n"
							;
						break;
					case PRIVILEGE_TYPE_FREQLIST_CREATE:
						echo "You can also change the limit on the size of subcorpora or corpus sections "
							, "for which users with this privilege can create frequency lists.\n"
							;
						break;
					case PRIVILEGE_TYPE_UPLOAD_FILE:
						echo "You can also change the limit on the size of files that users with this privilege can upload.\n";
						break;
					case PRIVILEGE_TYPE_DISK_FOR_UPLOADS:
						echo "You can also change the amount of upload area filestore granted to users with this privilege.\n";
						break;
					case PRIVILEGE_TYPE_INSTALL_CORPUS:
						echo "You can also change the limit on the size of corpora that users with this privilege can install.\n";
						break;
					case PRIVILEGE_TYPE_EXTRA_RUNTIME:
						echo "You can also change the number of extra seconds that this privilege affords.\n";
						break;
					case PRIVILEGE_TYPE_CQP_BINARY_FILE:
						echo "This kind of privilege does not have any other features that can be edited.\n";
						break;
					case PRIVILEGE_TYPE_DISK_FOR_CORPUS:
						echo "You can also change the amount of user-corpus filestore granted to users with this privilege.\n";
						break;
					}
					?>
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="2">
				Change description
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form id="updatePrivilegeDescForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="updatePrivilegeDescForm" type="hidden" name="admF" value="updatePrivilegeDesc">
				<input form="updatePrivilegeDescForm" type="hidden" name="privilege" value="<?php echo $p; ?>">
				<p class="spacer">&nbsp;</p>
				<p>
					Edit:&nbsp;
					<input form="updatePrivilegeDescForm" type="text" size="50" name="description" value="<?php echo escape_html($info->description); ?>">
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
			<td class="concordgeneral" align="center">
				<p class="spacer">&nbsp;</p>
				<p><input form="updatePrivilegeDescForm" type="submit" value="Update"></p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		<?php 
		switch ($info->type)
		{
		case PRIVILEGE_TYPE_CORPUS_FULL:
		case PRIVILEGE_TYPE_CORPUS_NORMAL:
		case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
			
			/* for all these, we will need a list of corpus descriptions eventually. */
			$corpora_info = get_all_corpora_info();
			
			?>
			
			<tr>
				<th class="concordtable" colspan="2">
					Add or remove corpora
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2">
					<p>
						This privilege currently covers the following corpora:
						<?php echo 1 > count($info->scope_object) ? "<em>None (empty scope)</em>" : "\n" ; ?>
					</p>
				</td>
			</tr>
			<?php 
			foreach($info->scope_object as $c)
				echo  "\n\t\t\t\t\t\t\t<tr>"
					, '<td class="concordgeneral">'
						, escape_html($corpora_info[$c]->title) , " <em>($c)</em>"
					, '</td>'
					, '<td class="concordgeneral" align="center">'
						, '<a href="index.php?admF=editPrivRemoveCorpus&corpus='
						, $c , '&privilege=', $p, '" class="menuItem">[Remove]</a>'
					, '</td>'
					, "</tr>\n"
					;
			?>
			<tr>
				<td class="concordgeneral" align="center">
					<form id="editPrivAddCorpusForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
					<input form="editPrivAddCorpusForm" type="hidden" name="admF" value="editPrivAddCorpus">
					<input form="editPrivAddCorpusForm" type="hidden" name="privilege" value="<?php echo $p; ?>">
					<p class="spacer">&nbsp;</p>
					<p>
						Select corpus to add: 
						<select form="editPrivAddCorpusForm" name="corpus">
							<option selected>Select corpus...</option>
							<?php
							foreach ($corpora_info as $c => $obj)
								if (!in_array($c, $info->scope_object))
									echo '<option value="', $c,  '">', escape_html($obj->title), '</option>', "\n\t\t\t\t\t\t\t\t";
							echo "\n";
							?>
						</select>
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p><input form="editPrivAddCorpusForm" type="submit" value="Add corpus to privilege"></p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<th class="concordtable" colspan="2">
					Change access level
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					<p>
						This privilege gives <strong>
						<?php 
						switch ($info->type)
						{
						case PRIVILEGE_TYPE_CORPUS_FULL:       echo "full";       break;
						case PRIVILEGE_TYPE_CORPUS_NORMAL:     echo "normal";     break;
						case PRIVILEGE_TYPE_CORPUS_RESTRICTED: echo "restricted"; break;
						}
						?>
						</strong> access.
					</p>
				</td>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					
					<?php
					foreach (array(PRIVILEGE_TYPE_CORPUS_FULL,PRIVILEGE_TYPE_CORPUS_NORMAL,PRIVILEGE_TYPE_CORPUS_RESTRICTED) as $t)
						if ($t != $info->type)
							echo "\n\t\t\t\t\t<p>"
								, '<a class="menuItem" href="index.php?admF=execute&function=update_privilege_access_level&args='
								, $p, urlencode('#'), $t
								, '&locationAfter=', urlencode('index.php?ui=privilegeAdmin')
								, '">[Change to '
								, str_replace(' to corpus', '', $Config->privilege_type_descriptions[$t])
								, ']</a>'
								, "</p>\n\n\t\t\t\t\t<p class=\"spacer\">&nbsp;</p>\n"
								;
					?>
				</td>
			</tr>
			
			<?php 
			
			break;
			
			
		/* INTENTIONAL FALL-THROUGH -- THESE USE THE SAME FORM WITH DIFFERENCES OF WORDING which we set via if.  */

		case PRIVILEGE_TYPE_FREQLIST_CREATE:
		case PRIVILEGE_TYPE_UPLOAD_FILE:
		case PRIVILEGE_TYPE_DISK_FOR_UPLOADS:
		case PRIVILEGE_TYPE_DISK_FOR_CORPUS:
		case PRIVILEGE_TYPE_INSTALL_CORPUS:
		case PRIVILEGE_TYPE_EXTRA_RUNTIME:
			
			if (PRIVILEGE_TYPE_FREQLIST_CREATE == $info->type)
			{
				$header_text  = "Change size of frequency list that this privilege allows to be created";
				$current_desc = "This privilege currently allows creation of frequency lists for subcorpora up to <strong>"
									. number_format($info->scope_object) . "</strong> tokens in extent.";
				$label_text   = "Enter a new size limit:";
				$admin_action = 'updatePrivilegeIntMax';
			}

			else if (PRIVILEGE_TYPE_UPLOAD_FILE == $info->type)
			{
				$header_text  = "Change size of file that this privilege allows to be uploaded";
				$current_desc = "This privilege currently allows files up to <strong>"
								. number_format($info->scope_object/(1024.0*1024.0), 1) . " MB</strong> to be uploaded.";
				$label_text   = "Enter a new maximum file size (in bytes)";
				$admin_action = 'updatePrivilegeIntMax';
			}

			else if (PRIVILEGE_TYPE_DISK_FOR_UPLOADS == $info->type)
			{
				$header_text  = "Change amount of disk space that this privilege grants to a user's upload area";
				$current_desc = "This privilege currently allocates <strong>"
								. number_format($info->scope_object/(1024.0*1024.0), 0) . " MB</strong> of disk space.";
				$label_text   = "Enter a new maximum filestore limit (in bytes)";
				$admin_action = 'updatePrivilegeIntMax';
			}

			else if (PRIVILEGE_TYPE_DISK_FOR_CORPUS == $info->type)
			{
				$header_text  = "Change amount of disk space that this privilege grants grants for aa user's installed corpora";
				$current_desc = "This privilege currently allocates <strong>"
								. number_format($info->scope_object/(1024.0*1024.0), 0) . " MB</strong> of disk space.";
				$label_text   = "Enter a new maximum filestore limit (in bytes)";
				$admin_action = 'updatePrivilegeIntMax';
			}

			else if (PRIVILEGE_TYPE_INSTALL_CORPUS == $info->type)
			{
				$header_text  = "Change size of corpora that this privilege allows users to install";
				$current_desc = "This privilege currently allows users to install their own corpora of up to <strong>"
								. number_format($info->scope_object->max_tokens, 0) . " tokens</strong>.";
				$label_text   = "Enter a new maximum corpus size (in tokens)";
				$admin_action = 'updateInstallPrivilegeScope';
			}

			else if (PRIVILEGE_TYPE_EXTRA_RUNTIME == $info->type)
			{
				$header_text  = "Change amount of extra runtime that this privilege gives the user";
				$current_desc = "This privilege currently allows up to <strong>"
								. number_format($info->scope_object, 0) . " seconds</strong> of extra runtime.";
				$label_text   = "Enter a new amount of extra runtime (in seconds)";
				$admin_action = 'updatePrivilegeIntMax';
			}
			?>
			
			<tr>
				<th class="concordtable" colspan="2">
					<?php echo $header_text; ?>
				</th>
			</tr>
			
			<tr>				
				<td class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<p><?php echo $current_desc; ?></p>
					<form id="<?php echo $admin_action; ?>Form" class="greyoutOnSubmit" action="index.php" method="get"></form>
						<input form="<?php echo $admin_action; ?>Form" type="hidden" name="admF" value="<?php echo $admin_action; ?>">
						<input form="<?php echo $admin_action; ?>Form" type="hidden" name="privilege" value="<?php echo $p; ?>">
					<p>
						<label for="newMax"><?php echo $label_text; ?></label>#
						&nbsp;
						<input form="<?php echo $admin_action; ?>Form" type="number" id="newMax" name="newMax">
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p><input form="<?php echo $admin_action; ?>Form" type="submit" value="Update"></p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
			<?php
			
			if (in_array($info->type, [PRIVILEGE_TYPE_UPLOAD_FILE,PRIVILEGE_TYPE_DISK_FOR_UPLOADS,PRIVILEGE_TYPE_DISK_FOR_CORPUS]))
			{
				/* we have different lists of commonly used sizes. */
				$byte_vals = array(
						PRIVILEGE_TYPE_UPLOAD_FILE => array(
								'524288'      => '0.5 MB',
								'1048576'     => '1   MB',
								'2097152'     => '2   MB',
								'4194304'     => '4   MB',
								'10485760'    => '10  MB',
								'20971520'    => '20  MB',
								'41943040'    => '40  MB',
						),
						PRIVILEGE_TYPE_DISK_FOR_UPLOADS => array(
								'1048576'     => '1   MB',
								'5242880'     => '5   MB',
								'10485760'    => '10  MB',
								'20971520'    => '20  MB',
								'52428800'    => '50  MB',
								'104857600'   => '100 MB',
								'209715200'   => '200 MB',
								'524288000'   => '500 MB',
						),
						PRIVILEGE_TYPE_DISK_FOR_CORPUS => array(
								'20971520'    => '20    MB',
								'52428800'    => '50    MB',
								'104857600'   => '100   MB',
								'209715200'   => '200   MB',
								'524288000'   => '500   MB',
								'1048576000'  => '1,000 MB',
								'2097152000'  => '2,000 MB',
								'5242880000'  => '5,000 MB',
						),
				)
				
				?>
				
				<tr>				
					<td class="concordgeneral">
						<form id="updatePrivilegeIntMaxForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
						<input form="updatePrivilegeIntMaxForm" type="hidden" name="admF" value="updatePrivilegeIntMax">
						<input form="updatePrivilegeIntMaxForm" type="hidden" name="privilege" value="<?php echo $p; ?>">
						<p class="spacer">&nbsp;</p>
						<p>
							<label for="newMaxCommonly">Or select a commonly-used size:</label>&nbsp;
							<select id="newMaxCommonly" form="updatePrivilegeIntMaxForm" name="newMax">
								<?php
									foreach ($byte_vals[$info->type] as $bytes => $label)
										echo "\n\t\t\t\t\t\t\t\t\t<option value=\"$bytes\">$label</option>";
									echo "\n";
 									?>
							</select>
						</p>
						<p class="spacer">&nbsp;</p>
					</td>
					<td class="concordgeneral" align="center">
						<p class="spacer">&nbsp;</p>
						<p>
							<input form="updatePrivilegeIntMaxForm" type="submit" value="Update">
						</p>
						<p class="spacer">&nbsp;</p>
					</td>
				</tr>
				
				<?php 
			}

			break;
			
			
			
		case PRIVILEGE_TYPE_CQP_BINARY_FILE:
			/* do nowt */
			break;
		}
		
		?>
		
		
<!-- 		<tr> -->
<!-- 			<td class="concordgeneral" align="center"> -->
<!-- 				&nbsp;<br> -->
<!-- 				This function is under construction. -->
<!-- 				<br>&nbsp; -->
<!-- 			</td> -->
<!-- 		</tr> -->
	</table>
	<?php
}


function do_adm_ui_usergrants()
{
	$priv_desc = get_all_privilege_descriptions();
	$user_list = get_list_of_users();
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Manage grants of privileges to users
			</th>
		</tr>
	</table>
	
	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="newGrantToUser">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="3">
					Grant new privilege to user
				</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p>
						Select user:
						<select name="user">
							<?php
							if (1 < count($user_list))
								echo '<option value="" selected>[Select a user...]</option>';
							/* this makes life easier for single-user intallations. */
							
							echo "\n";
								
							foreach ($user_list as $u)
								echo "\t\t\t\t\t\t\t<option value=\"$u\">$u</option>\n";
							?> 
						</select>
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p>
						Select privilege:
						<select name="privilege">
							<option value="">[Select a privilege...]</option>
							<?php
							echo "\n";
							foreach ($priv_desc as $id => $desc)
								echo "\t\t\t\t\t\t\t<option value=\"$id\">$id: $desc</option>\n";
							?> 
						</select>
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p><input type="submit" value="Grant privilege to user!"></p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
	</form>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">
				Existing grants to individual users
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				Username
			</th>
			<th class="concordtable">
				Privilege
			</th>
			<th class="concordtable">
				Expiry time
			</th>
			<th class="concordtable">
				Delete
			</th>
		</tr>
		
		<?php
		
		$at_least_one_row_written = false;
				
		foreach($user_list as $user)
		{
			$grants = list_user_grants($user);
			
			$nrows = count($grants);
			
			$firstgrant = true;
			
			foreach($grants as $g)
			{
				$at_least_one_row_written = true;
				echo "<tr>"
					, ($firstgrant ? "<td class=\"concordgeneral\" align=\"center\" rowspan=\"$nrows\">$user</td>" : '')
					, "<td class=\"concordgeneral\" align=\"center\"><strong>{$g->privilege_id}</strong>: {$priv_desc[$g->privilege_id]}</td>"
					, "<td class=\"concordgeneral\" align=\"center\">"
						, ($g->expiry_time < 1 ? 'Never' : date(CQPWEB_UI_DATE_FORMAT, $g->expiry_time))
					, "</td>"
					, "<td class=\"concordgeneral\" align=\"center\">"
					, "<a class=\"menuItem\" href=\"index.php?admF=removeUserGrant&user=$user&privilege={$g->privilege_id}\">[x]</a>"
					, "</td>"
					, "</tr>"
					;
				$firstgrant = false;
			}
		}
		
		if ( ! $at_least_one_row_written)
			echo "<tr><td class=\"concordgrey\" colspan=\"4\" align=\"center\">"
				, "&nbsp;<br>There are currently no individual-user grants.<br>&nbsp;</td></tr>"
				;
		?>
		
	</table>

	<?php
}


function do_adm_ui_groupgrants()
{
	$priv_desc = get_all_privilege_descriptions();
	$group_list = get_list_of_groups();
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Manage grants of privileges to groups
			</th>
		</tr>
	</table>

	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="newGrantToGroup">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="3">
					Grant new privilege to group
				</th>
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					&nbsp;<br>
					Select group:
					<select name="group">
						<option value="">[Select a group...]</option>
						<?php
						foreach ($group_list as $g)
							if ($g != 'superusers')
								echo "\n\t\t\t\t\t\t<option value=\"$g\">$g</option>\n";
						?> 
					</select>
					<br>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br>
					Select privilege:
					<select name="privilege">
						<option value="">[Select a privilege...]</option>
						<?php
						foreach ($priv_desc as $id => $desc)
							echo "\n\t\t\t\t\t\t<option value=\"$id\">$id: $desc</option>\n";
						?> 
					</select>
					<br>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br>
					<input type="submit" value="Grant privilege to group!">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>


	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="cloneGroupGrants">
		<table class="concordtable fullwidth">
			<tr>
				<th colspan="3" class="concordtable">
					Clone a group&rsquo;s granted privileges
				</th>
			</tr>
			
			<tr>
				<td colspan="3" class="concordgrey">
					&nbsp;<br>
					If you "clone" privilege grants from Group A to Group B, you overwrite all the current privileges
					of Group B; it will have exactly the same set of privileges as Group A.
					<br>&nbsp;
				</td>
			</tr>
			
			<?php
			
			$clone_group_options = '<option value="">[Select a group...]</option>';
			foreach ($group_list as $group)
			{
				if ($group == 'superusers')
					continue;
				$clone_group_options .= "<option>$group</option>\n";
			}
			
			?>
		
		
			<tr>
				<td class="concordgeneral">
					&nbsp;<br>
					Clone from:
					<select name="groupCloneFrom">
						<?php echo $clone_group_options; ?>
					</select>
					<br>&nbsp;
				</td>
				<td class="concordgeneral">
					&nbsp;<br>
					Clone to:
					<select name="groupCloneTo">
						<?php echo $clone_group_options; ?>
					</select>
					<br>&nbsp;
				</td>
				<td class="concordgeneral" align="center">
					&nbsp;<br>
					<input type="submit" value="Clone access rights!">
					<br>&nbsp;
				</td>
			</tr>
		</table>		
	</form>


	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">
				Existing grants to user groups
			</th>
		</tr>
		<tr>
			<th class="concordtable">
				Group
			</th>
			<th class="concordtable">
				Privilege
			</th>
			<th class="concordtable">
				Expiry time
			</th>
			<th class="concordtable">
				Delete
			</th>
		</tr>
		
		<?php
		
		foreach($group_list as $group)
		{
			$grants = list_group_grants($group);
			
			if ($group == 'superusers')
				echo "\t\t<tr>\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"1\"><strong>superusers</strong></td>\n"
					, "\t\t\t<td class=\"concordgrey\" align=\"center\" colspan=\"3\"><em>This group always has all privileges.</em></td>\n"
					, "\t\t</tr>";
			else
			{
				if (empty($grants))
					echo "\t\t<tr>\n\t\t\t<td class=\"concordgeneral\" align=\"center\" rowspan=\"1\"><strong>$group</strong></td>\n"
					, "\t\t\t<td class=\"concordgrey\" align=\"center\" colspan=\"3\"><em>This group currently has no granted privileges.</em></td>\n"
					, "\t\t</tr>"
					;
				else
				{
					if (0 == ($nrows = count($grants)))
						++$nrows;
					$firstgrant = true;	

					foreach($grants as $g)
					{
						echo "<tr>"
							, ($firstgrant ? "<td class=\"concordgeneral\" align=\"center\" rowspan=\"$nrows\"><strong>$group</strong></td>" : '')
							, "<td class=\"concordgeneral\" align=\"center\"><strong>{$g->privilege_id}</strong>: {$priv_desc[$g->privilege_id]}</td>"
							, "<td class=\"concordgeneral\" align=\"center\">"
								, ($g->expiry_time < 1 ? 'Never' : date(CQPWEB_UI_DATE_FORMAT, $g->expiry_time))
							, "</td>"
							, "<td class=\"concordgeneral\" align=\"center\">"
							, "<a class=\"menuItem\" href=\"index.php?admF=removeGroupGrant&group=$group&privilege={$g->privilege_id}\">[x]</a>"
							, "</td>"
							, "</tr>"
							;
						$firstgrant = false;
					}
				}
			}
		}
	
		?>
		
	</table>	
	

	<?php
}


function do_adm_ui_skins()
{
	global $Config;
	
	?>
	
	<form id="transferStylesheetFileForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
	<input form="transferStylesheetFileForm" type="hidden" name="admF" value="transferStylesheetFile">

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="4">
				Skins and colour schemes
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="4">
				<p class="spacer">&nbsp;</p>
				<p>Use the button below to re-generate built-in colour schemes:</p>
				<p class="spacer">&nbsp;</p>
				<form id="regenerateCSSForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="regenerateCSSForm" type="hidden" name="admF" value="regenerateCSS">
				<p align="center" >
					<input form="regenerateCSSForm" type="submit" value="Regenerate colour schemes!">
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="4">
				<p class="spacer">&nbsp;</p>
				<p>
					Listed below are the CSS files currently present in the upload area which do 
					<em>not</em> already appear in the main <em>css</em> directory.
				</p>
				<p>
					Select a file and click &ldquo;Import!&rdquo; 
					to create a copy of the file in the <em>css/extra</em> directory.
				</p>  
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<th class="concordtable">Transfer?</th>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
		</tr>
		
		<?php
		$file_list = scandir($Config->dir->upload);
		
		foreach ($file_list as $f)
		{
			$file = "{$Config->dir->upload}/$f";
			$target = "../css/extra/$f";
			
			if (!is_file($file)) continue;	
			if (substr($f,-4) != '.css') continue;
			if (is_file($target)) continue;

			$stat = stat($file);

			$esc_f = escape_html($f);

			?>
			
			<tr>
				<td class="concordgeneral" align="center">
					<?php echo '<input form="transferStylesheetFileForm" type="radio" id="cssFile:' , $esc_f , '" name="cssFile" value="' , $esc_f , '">', "\n"; ?>
				</td>
				
				<td class="concordgeneral" align="left">
					<label for="cssFile:<?php echo $esc_f; ?>">
						<?php echo $esc_f, "\n"; ?>
					</label>
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
		
		<tr>
			<td class="concordgrey" align="center" colspan="4">
				<p class="spacer">&nbsp;</p>
				<p><input form="transferStylesheetFileForm" type="submit" value="Transfer"></p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
	
	<?php
}



function do_adm_ui_mappingtables()
{
	$show_existing = ( isset($_GET['showExisting']) ? (bool)$_GET['showExisting'] : false );
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="3">
				Mapping tables
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">
				&nbsp;<br>
				
				&ldquo;Mapping tables&rdquo; are used in the Common Elementary Query Language (CEQL)
				system (aka &ldquo;Simple query&rdquo;).
				
				<br>&nbsp;<br>
				
				They transform <em>the tag the user searches for</em> (referred to as an 
				<strong>alias</strong>) into <em>the tag that actually occurs in the corpus</em>, or 
				alternatively into <em>a regular expression covering a group of tags</em> (referred to
				as the <strong>search term</strong>.
				
				<br>&nbsp;<br>
				
				Each alias-to-search-term mapping has the form "ALIAS" => "SEARCH TERM".  
					
				<br>&nbsp;<br>
				
				<?php
				echo '<a href="index.php?ui=mappingTables&showExisting='
					, ($show_existing ? '0' : '1')
					, '">Click here '
					, ($show_existing ? 'to add a new mapping table' : 'to view all stored mapping tables')
					, "</a>.\n\n"
					;
				?>
				<br>&nbsp;
			</td>
		</tr>
		<?php
		if ($show_existing)
		{
			/* show existing mapping tables */
			?>
			
			<tr>
				<th class="concordtable" colspan="3">
					Currently stored mapping tables
				</th>
			</tr>
			<tr>
				<th class="concordtable">Name (and <em>handle</em>)</th>
				<th class="concordtable">Mapping table</th>
				<th class="concordtable">Actions</th>
			</tr>
			
			<?php
			foreach(get_all_tertiary_mapping_tables() as $table)
				echo '<tr>'
					, '<td class="concordgeneral">' . $table->name . ' <br>&nbsp;<br>(<em>' . $table->handle . '</em>)</td>'
					, '<td class="concordgeneral"><code>' 
						, strtr($table->mappings, array("\n"=>'<br>', "\t"=>'&nbsp;&nbsp;&nbsp;') )
					, '</code></td>'
					, '<td class="concordgeneral" align="center">'
						, '<a class="menuItem" href="index.php?admF=execute&function=drop_tertiary_mapping_table&args=' 
						, $table->handle . '&locationAfter=' . urlencode('index.php?ui=mappingTables&showExisting=1') , '">'
						, '[Delete]</a>'
					, '</td>'
					, "</tr>\n\n"
					;	
		}
		else
		{
			/* add new mapping table */
			?>
			<tr>
				<th class="concordtable" colspan="3">
					Create a new mapping table
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="3">
					Your mapping table must start and end in a brace <strong>{ }</strong> ; each 
					alias-to-search-term mapping but the last must be followed by a comma. 
					Use Perl-style escapes for quotation marks where necessary.
					
					<br>&nbsp;<br>
					
					You are strongly advised to save an offline copy of your mapping table,
					as it is a lot of work to recreate if it accidentally gets deleted from
					the database.
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" align="center" valign="top">
					Enter an ID code
					<br> 
					(letters, numbers, and _ only)
					<form id="newMappingTableForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
					<input form="newMappingTableForm" type="hidden" name="admF" value="newMappingTable">
					<br>&nbsp;<br>
					<input form="newMappingTableForm" type="text" size="30" name="newMappingTableId" onKeyUp="check_c_word(this)">
				</td>
				<td class="concordgeneral" align="center" valign="top">
					Enter the name of the mapping table:
					<br>&nbsp;<br>&nbsp;<br>
					<input form="newMappingTableForm" type="text" size="30" name="newMappingTableName">
				</td>
				<td class="concordgeneral" align="center" valign="top">
					Enter the mapping table code here:
					<br>&nbsp;<br>&nbsp;<br>
					<textarea form="newMappingTableForm" name="newMappingTableCode" cols="60" rows="25"></textarea>					
				</td>				
			</tr>
			<tr>
				<td class="concordgeneral" colspan="3" align="center">
					<input form="newMappingTableForm" type="submit" value="Create mapping table!">
				</td>				
			</tr>
			
			<?php
		}
		?>

		<tr>
			<th class="concordtable" colspan="3">
				Built-in mapping tables
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" colspan="3" align="center">
				CQPweb contains a number of built-in mapping tables, including the Oxford Simplified Tagset 
				devised for the BNC (highly recommended).
				<br>&nbsp;<br>
				Use the button below to insert them into the database.
				<br>&nbsp;<br>

				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="submit" value="Click here to regenerate built-in mapping tables.">
					<br>
					<input type="hidden" name="admF" value="execute">
					<input type="hidden" name="function" value="regenerate_builtin_mapping_tables">
					<input type="hidden" name="locationAfter" value="index.php?ui=mappingTables&showExisting=1">
				</form>					
			</td>
		</tr>
	</table>

	<?php
}





// This function is VERRY MUCH NOT completye.
function do_adm_ui_systemsnapshots()
{
	// TODO "snapshotFunction" should not be processed here! snapshot-act? or else via admin-execute?
	global $Config;
	
	/* this dir needs to exist for us to scan it... */
	if (!is_dir($d = "{$Config->dir->upload}/dump"))
		mkdir($d);
	
	if (isset($_GET['snapshotFunction']))
		switch($_GET['snapshotFunction'])
		{
		case 'createSystemSnapshot':
			cqpweb_dump_snapshot("$d/CQPwebFullDump-" . time());
			break;
		case 'createUserdataBackup':
			cqpweb_dump_userdata("$d/dump/CQPwebUserDataDump-" . time());
			break;
		case 'undumpSystemSnapshot':
			/* check that the argument is an approrpiate-format undump file that exists */
			if 	(	preg_match('/^CQPwebFullDump-\d+$/', $_GET['undumpFile']) > 0
					&&
					is_file($_GET['undumpFile'])
				)
				/* call the function */
				cqpweb_undump_snapshot("$d/".$_GET['undumpFile']);
			else
				exiterror("Invalid filename specified for system snapshot, or file does not exist!");
			break;
		case 'undumpUserdataBackup':
			/* check that the argument is an approrpiate-format undump file that exists */
			if 	(	preg_match('/^CQPwebUserDataDump-\d+$/', $_GET['undumpFile']) > 0
					&&
					is_file($_GET['undumpFile'])
				)
				/* call the function */
				cqpweb_undump_userdata("$d/{$_GET['undumpFile']}");
			else
				exiterror("Invalid filename specified for user data backup, or file does not exist!");
			break;
		default:
			break;
		}
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="3">
				CQPweb system snapshots
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">
				&nbsp;<br>
				Use the button below to create a system snapshot (a zip file containing all the data from this
				CQPweb system's current state, <em>except</em> the CWB registry and data files).
				<br>&nbsp;<br>
				Snapshot files are create as .tar.gz files in the "dump" subdirectory of the upload area.
				<br>&nbsp;<br>
				Warning: snapshot files <em>can be very big.</em>
				<br>&nbsp;<br>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="ui" value="systemSnapshots">
					<input type="hidden" name="snapshotFunction" value="createSystemSnapshot">
					<br>
					<input type="submit" value="Create a snapshot file!">
					<br>
				</form>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">
				&nbsp;<br>
				Use the button below to create a userdata backup (a zip file containing all the 
				<strong>irreplaceable</strong> data in the system).
				<br>&nbsp;<br>
				Currently, this means user-saved queries and categorised queries. It is assumed
				that the corpus itself and all associated metadata is <em>not</em> irreplaceable
				(as you will have your own backup systems in place) but that user-generated data
				<em>is</em>.
				<br>&nbsp;<br>
				These backups are placed initially in the same location as snapshot files, but
				you should move them as soon as possible to a backup location.
				<br>&nbsp;<br>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="snapshotFunction" value="createUserdataBackup">
					<input type="hidden" name="ui" value="systemSnapshots">
					<br>
					<input type="submit" value="Create a userdata backup file!">
					<br>
				</form>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="3">
				The following files currently exist in the "dump" directory.
			</th>
		</tr>
		<tr>
			<th class="concordtable">Filename</th>
			<th class="concordtable">Size (K)</th>
			<th class="concordtable">Date modified</th>
		</tr>
		<?php
		$num_files = 0;
		$file_options = "\n";
		$file_list = scandir($d);
		foreach ($file_list as &$f)
		{
			$file = "$d/$f";
			
			if (!is_file($file))
				continue;
			$stat = stat($file);
			$num_files++;
			
			$file_options .= "\t\t\t<option>$f</option>\n";

			?>
			<tr>
				<td class="concordgeneral" align="left">
					<?php echo $f; ?>
				</td>
				
				<td class="concordgeneral" align="right">
					<?php echo number_format(round($stat['size']/1024, 0)); ?>
				</td>
				
				<td class="concordgeneral" align="center">
					<?php echo date(CQPWEB_UI_DATE_FORMAT, $stat['mtime']); ?>
				</td>
			
			</tr>
			<?php
		}
		if ($num_files < 1)
			echo "\n\n\t<tr><td class='concordgrey' align='center' colspan='3'>
				&nbsp;<br>This directory is currently empty.<br>&nbsp;</td></tr>\n";

		?>
		<tr>
			<th class="concordtable" colspan="3">
				Undump system snapshot
			</th>
		<tr>
			<td class="concordgeneral" colspan="3">
				<strong>Warning: this function is experimental.</strong>
				<br>&nbsp;<br>
				It will overwrite the current state of the CQPweb system.
				<br>&nbsp;<br>
				Select a file from the "dump" directory:
				
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="ui" value="systemSnapshots">
					<input type="hidden" name="snapshotFunction" value="undumpSystemSnapshot">
					<select name="undumpFile">
						<?php 
						echo ($file_options == "\n" ? '<option>No undump files available</option>' : $file_options);
						?>
					</select>
					<br>&nbsp;<br>
					Press the button below to overwrite CQPweb with the contents of this snapshot:
					<br>
					<input type="submit" value="Undump snapshot">
				</form>
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="3">
				Reload backed-up userdata
			</th>
		<tr>
			<td class="concordgeneral" colspan="3">
				<strong>Warning: this function is experimental.</strong>
				<br>&nbsp;<br>
				It will overwrite any queries with the same name that are in the system already.
				<br>&nbsp;<br>
				Select a file from the "dump" directory:
				
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<input type="hidden" name="ui" value="systemSnapshots">
					<input type="hidden" name="snapshotFunction" value="undumpUserdataBackup">
					<select>
						<?php 
						echo ($file_options == "\n" ? '<option>No undump files available</option>' : $file_options);
						?>
					</select>
					<br>&nbsp;<br>
					Press the button below to overwrite CQPweb with the contents of this snapshot:
					<br>
					<input type="submit" value="Reload user data">
				</form>
			</td>
		</tr>
	</table>
	<?php
}


function do_adm_ui_systemdiagnostics()
{
	global $Config;

	if (empty($_GET['runDiagnostic']))
		$_GET['runDiagnostic'] = 'none';
	
		
	/* every case of this switch should print an entire table, then return */
	switch ($_GET['runDiagnostic'])
	{
	
	case 'dbVersion':
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Database version check
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					&nbsp;<br>
					The system database is at version <?php echo get_sql_cqpwebdb_version(); ?>. 
					<br>&nbsp;<br>
					CQPweb's code is at version <?php echo CQPWEB_VERSION; ?>. 
					<br>&nbsp;<br>
					It is normal for the database version to be a little behind the code. 
					But if there is a major mismatch between the two, you may run into trouble.
					<br>&nbsp;<br>
					If in doubt, run the <strong>upgrade-databse</strong> script (see system adminisatrator's manual for detail).
					<br>&nbsp;<br>
				</td>
			</tr>
		</table>
		
		<?php
		return;
		
		
	case 'blowfishCost':

		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Running a stress-test on password encryption via Blowfish
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					<p class="spacer">&nbsp;</p>
					<p>Using the Blowfish cost of <strong><?php echo $Config->blowfish_cost; ?></strong> as in your current configuration.</p>
					<p class="spacer">&nbsp;</p>
					<p>This may take some time. Please wait.</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<p>Running &hellip;</p>
					<p class="spacer">&nbsp;</p>
					<p>
						<?php
						flush();
						$time = stresstest_hash_from_password(50);
						echo "The stresstest gave an average time-per-password of <strong>",  number_format($time, 2), "</strong> milliseconds. ";
						?>
						(For <em>maximum</em> security, this time should be as high as is tolerable for users.)
					</p>
					<p class="spacer">&nbsp;</p>
					<p>
						If you wish to change this time, alter the configuration variable $blowfish_cost. 
						Each cost increase of 1 point approximately doubles the time taken.  
					</p>
					<p class="spacer">&nbsp;</p>					
				</td>
			</tr>
		</table>
	
		<?php
		return;
	
		
	case 'cqp':
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Diagnosing connection to child process for CQP back-end
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					<pre>
					<?php echo "\n" . CQP::diagnose_connection($Config->path_to_cwb, $Config->dir->registry) . "\n"; ?>
					</pre>
				</td>
			</tr>
		</table>
		<?php
		return;
		
		
	case 'none':
	default:
		/* this is the only route to the rest of the function */
		break;
	}
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				CQPweb system diagnostics
			</th>
		</tr>	
		
		<tr>
			<td class="concordgrey">
				<p class="spacer">&nbsp;</p>
				<p>Use the controls below to run diagnostics for parts of CQPweb that aren't working properly.</p>
				<p class="spacer">&nbsp;</p>
				<p><strong>UNDER DEVELOPMENT</strong>. Only some of them work.</p>
				<p class="spacer">&nbsp;</p>
				<p>Many of these tests take a while to run - so, don't refresh the page if it loads partially, and have patience!.</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>

		<tr>

			<th class="concordtable">
				Check adequacy of the Blowfish &ldquo;cost&rdquo;
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<p class="spacer">&nbsp;</p>
					<input type="submit" value="See if the blowfish cost for password encryption is high enough">
					<p class="spacer">&nbsp;</p>
					<input type="hidden" name="ui" value="systemDiagnostics">
					<input type="hidden" name="runDiagnostic" value="blowfishCost">
				</form>
			</td>
		</tr>
		
		<tr>
			<th class="concordtable">
				Check database version
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<p class="spacer">&nbsp;</p>
					<input type="submit" value="Find out if the system database is out-of-sync with the CQPweb code">
					<p class="spacer">&nbsp;</p>
					<input type="hidden" name="ui" value="systemDiagnostics">
					<input type="hidden" name="runDiagnostic" value="dbVersion">
				</form>
			</td>
		</tr>
		
		<tr>
			<th class="concordtable">
				Check CQP back-end
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="3">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<p class="spacer">&nbsp;</p>
					<input type="submit" value="Run a system check on the CQP back-end process connection">
					<p class="spacer">&nbsp;</p>
					<input type="hidden" name="ui" value="systemDiagnostics">
					<input type="hidden" name="runDiagnostic" value="cqp">
				</form>
			</td>
		</tr>
	</table>
	<?php
}




function do_adm_ui_systemannouncements()
{
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Add a system message
			</th>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<strong>Heading:</strong>
				<form id="addSystemMessageForm" class="greyoutOnSubmit" action="index.php" method="get"></form>
				<input form="addSystemMessageForm" type="hidden" name="admF" value="addSystemMessage">
				<input form="addSystemMessageForm" type="text" name="systemMessageHeading" size="90" maxlength="100">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<textarea form="addSystemMessageForm" name="systemMessageContent" rows="5" cols="65" 
					style="font-size: 16px;"></textarea>
				<br>&nbsp;<br>
				<input form="addSystemMessageForm" type="submit" value="Add system message">
				&nbsp;&nbsp;
				<input form="addSystemMessageForm" type="reset" value="Clear form">
				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	<?php
	display_system_messages();
}



function do_adm_ui_tableview()
{
	
	if(!empty($_GET['table']))
	{
		/* a table has already been chosen */
		?>

		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Viewing SQL table <?php echo escape_html($_GET['table']), "\n"; ?>
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<?php
					$table = escape_sql($_GET['table']);
					if (!empty($_GET['limit']))
						$limit = " LIMIT " . escape_sql($_GET['limit']);
					else
						$limit = "";
					$sql = "SELECT * FROM `$table`$limit";
					
					echo "\n", print_sql_result_dump(do_sql_query($sql)), "\n";
					?>
				</td>
			</tr>
		</table>
		
		<?php
	}
	else
	{
		/* no table has been chosen */
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">View an SQL table</th>
			</tr>

			<tr>
				<td class="concordgeneral">
					<form class="greyoutOnSubmit" action="index.php" method="get"> 
						<input type="hidden" name="ui" value="tableView">
						<table>
							<tr>
								<td class="basicbox">Select table to show:</td>
								<td class="basicbox">
									<select name="table">
										<?php
										echo "\n";
										$result = do_sql_query("SHOW TABLES");
										while ($row = mysqli_fetch_row($result)) 
											echo "\t\t\t\t\t\t\t\t<option value='{$row[0]}'>{$row[0]}</option>\n";
										?>
									</select>
								</td>
							</tr>
							<tr>
								<td class="basicbox">Optionally, enter a LIMIT:</td>
								<td class="basicbox">
									<input type="number" name="limit">
								</td>
							</tr>
							<tr>
								<td class="basicbox">&nbsp;</td>
								<td class="basicbox">
									<input type="submit" value="Show table">
								</td>
							</tr>
						</table>

					</form>
				</td>
			</tr>
		</table>

		<?php
	}

}




function do_adm_ui_manageprocesses()
{
	global $Config;
	

	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">Manage SQL processes</th>
		</tr>
	</table>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">Viewing registered &ldquo;big&rdquo; SQL processes</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="5">
				<p>
					This table displays CQPweb's log of database creation processes (aka "big" processes, SQL calls that
					require lots of hardware resources). If a process runs to completion correctly, it will be removed from
					this log. However, if a process is interrupted for any reason, its entry on the log will remain here.
				</p>
				<p>
					Only a certain number of concurrent "big" processes are allowed - thus, if the log fills up with "zombie" processes,
					new databases will be blocked from creation.
				</p>
				<p>
					If a big process listed in this table is no longer running on the SQL-DB daemon (i.e. is flagged as zombie in this table),
					it is safe to delete it from the log.
				</p>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">Number of big processes allowed:<br><i>(for most database types)</i></td>
			<td class="concordgeneral" colspan="2" align="right"><?php echo $Config->mysql_big_process_limit; ?></td>
		</tr>
		<tr>
			<td class="concordgrey" colspan="3">Number of big processes allowed:<br><i>(for distribution databases, which are quicker)</i></td>
			<td class="concordgeneral" colspan="2" align="right"><?php echo $Config->mysql_process_limit['dist']; ?></td>
		</tr>
		<tr>
			<th class="concordtable" >Database being created</th>
			<th class="concordtable" >Time process began</th>
			<th class="concordtable" >Process type</th>
			<th class="concordtable" >Still running?</th>
			<th class="concordtable" >Delete</th>
		</tr>
		<?php

		$processlist = do_sql_query('show full processlist');
		
		$db_processes = array();
		
		while ($o = mysqli_fetch_object($processlist))
			if (preg_match('|create table (db_\w+)\b|i', $o->Info, $m))
				$db_processes[] = $m[1];
		// TODO this does not allow for freqlist creation processes, which look different from DB creation processes
		// (and involve the creation of different things)
		// actually won't work for  DBs either, cos they spenmd more time loading than creating.
		// TODO result is that freqlist processes ALWAYS look like zombies.
		// solution:  capture the whole query and search for the "dbname" in each entry?
		
		mysqli_data_seek($processlist, 0);

		$result = do_sql_query('SELECT * from system_processes');
		
		if (1 > mysqli_num_rows($result))
			echo '<tr><td class="concordgeneral" align="center" colspan=5">&nbsp;<br>The log is currently empty.<br>&nbsp;</td></tr>';

		while ($process = mysqli_fetch_object($result))
			echo '<tr>'
				, '<td class="concordgeneral">' , $process->dbname , '</td>'
				, '<td class="concordgeneral" align="center">' , date(CQPWEB_UI_DATE_FORMAT, $process->begin_time) , '</td>'
				, '<td class="concordgeneral" align="center">' , $process->process_type , '</td>'
				, '<td class="concordgeneral" align="center">' 
					, (in_array($process->dbname, $db_processes) ? ('<a class="menuItem" href="#'.$process->dbname.'">[Yes!]</a>') : 'No: zombie')
				, '</td>'
				, '<td class="concordgeneral" align="center">'
					, '<a class="menuItem" href="index.php?locationAfter='
					, urlencode('index.php?ui=manageProcesses')
					, '&admF=execute&function=unregister_db_process&args=' 
					, $process->process_id , '">[x]</a>'
				, '</td>'
				, "</tr>\n"
				;
		?>
		
	</table>
	
	
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="6">SQL-DB Daemon: activity snapshot</th>
		</tr>
		
		<tr>
			<th class="concordtable">Thread ID</th>
			<th class="concordtable">Command/State</th>
			<th class="concordtable">User</th>
			<th class="concordtable">Source</th>
			<th class="concordtable">Running time (s)</th>
			<th class="concordtable">Request Kill</th>
		</tr>
		
		<?php
		
		/* if number of rows == 1, then nothing is running but "show processlist" itself */
		if (2 > mysqli_num_rows($processlist))
			echo '<tr><td class="concordgeneral" align="center" colspan="6">&nbsp;<br>There is no activity on the SQL-DB daemon.<br>&nbsp;</td></tr>';
		else
		{
			while ($o = mysqli_fetch_object($processlist))
			{
				$details_available = false;
				if (is_null($o->Info))
					$o->q = $o->u = $o->f = $o->t = 'n/a';
				else
				{
					/* catch jump hyperlinks from table above... */
					if (preg_match('|create table (db_\w+)\b|i', $o->Info, $m))
						echo "\n\n", '<a name="', $m[1], '">' , "\n\n";

					/* extract info hidden in the query comment */
					if (preg_match('~/\* from User: (\w+) \| Function: (\w+)\(\) \| (\S+ \S+) \*/~s', $o->Info, $m))
					{
						list(, $o->u, $o->f, $o->t) = $m;
						$details_available = true;
					}
					else
						$o->q = $o->u = $o->f = $o->t = '???';

					list($o->q) = explode('/*', $o->Info);
				}

				echo '<tr>'
					, '<td class="concordgeneral">' , $o->Id , '</td>'
					, '<td class="concordgeneral">' , $o->Command, ' / ',  (empty($o->State) ? 'NULL' : $o->State), '</td>'
					, '<td class="concordgeneral">' , $o->u , '</td>'
					, '<td class="concordgeneral">' 
						, escape_html($o->q) , ($details_available ? '<br>from function ' . $o->f . '()<br>at time '. $o->t : '')
					, '</td>'
					, '<td class="concordgeneral" align="right">' , $o->Time , '</td>'
					, '<td class="concordgeneral">TODO:kill: ' , $o->Id , '</td>'
					, "</tr>\n"
					;
					// TODO: kill button??????
			}
		}
		
		?>
		
	</table>
	<?php

}



function do_adm_ui_statistic_corpus() { do_adm_ui_statistic('corpus'); }
function do_adm_ui_statistic_user()   { do_adm_ui_statistic('user'  ); }
function do_adm_ui_statistic_query()  { do_adm_ui_statistic('query' ); }

/**
 * Thjis function normally called via one of three aliases that supply the parameter. 
 * @param string $type  corpus|query|user
 */
function do_adm_ui_statistic($type = 'user')
{
	global $Config;

	/* note usage of the same system of "perpaging" as the "Query History" function */
	if (isset($_GET['beginAt']))
		$begin_at = $_GET['beginAt'];
	else
		$begin_at = 1;

	if (isset($_GET['pp']))
		$per_page = $_GET['pp'];
	else
		$per_page = $Config->default_history_per_page;


	switch($type)
	{
	case 'corpus':
		$bigquery = 'select corpus, count(*) as c from query_history group by corpus order by c desc';
		$colhead = 'Corpus';
		$pagehead = 'for corpora';
		$list_of_corpora = list_corpora();
		break;
	case 'query':
		$bigquery = 'select cqp_query, count(*) as c from query_history group by cqp_query order by c desc';
		$colhead = 'Query';
		$pagehead = 'for particular query strings';
		break;
	case 'user':
	default:
		$bigquery = 'select user, count(*) as c from query_history group by user order by c desc';
		$colhead = 'Username';
		$pagehead = 'for user accounts';
		break;
	}
	
	$result = do_sql_query($bigquery);
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="3" class="concordtable">Usage statistics <?php echo $pagehead;?></th>
		</tr>
		<tr>
			<th class="concordtable" width="10%">No.</th>
			<th class="concordtable" width="60%"><?php echo $colhead; ?></th>
			<th class="concordtable" width="30%">No. of queries</th>
		</tr>
		
		<?php
		
		$toplimit = $begin_at + $per_page;
		$alt_toplimit = mysqli_num_rows($result);

		if (($alt_toplimit + 1) < $toplimit)
			$toplimit = $alt_toplimit + 1;

		for ( $i = 1 ; $i < $toplimit ; $i++ )
		{
			if ( !($row = mysqli_fetch_row($result)) )
				break;
			if ($i < $begin_at)
				continue;
			
			if ($type == 'corpus')
				if( !in_array($row[0], $list_of_corpora))
					$row[0] .= ' <em>(deleted)</em>';

			echo "<tr>\n";
			echo '<td class="concordgeneral" align="center">' . "$i</td>\n";
			echo '<td class="concordgeneral" align="left">' . "{$row[0]}</td>\n";
			echo '<td class="concordgeneral" align="center">' . number_format((float)$row[1]) . "</td>\n";
			echo "\n</tr>\n";
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
	$navlinks .= '">&lt;&lt; [Move up the list]';
	if ($begin_at > 1)
		$navlinks .= '</a>';
	$navlinks .= '</td><td class="basicbox" align="right';
	
	if (mysqli_num_rows($result) > $i)
		$navlinks .=  '"><a href="index.php?' . url_printget(array( ['beginAt', "$i + 1"], ['ui',''] ));
	$navlinks .= '">[Move down the list] &gt;&gt;';
	if (mysqli_num_rows($result) > $i)
		$navlinks .= '</a>';
	$navlinks .= '</td></tr></table>';
	
	echo $navlinks;
}


function do_adm_ui_historyclear()
{
// TODO does this func still exst?
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Clear the Query History
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				<p>Please note the following points:</p>
				<ul>
					<li>The Query History logs all queries performed by all users.</li>
					<li>CQPweb's &ldquo;Usage statistics&rdquo; are produced by analysing the Query History.</li>
					<li>Deleting the Query History will re-set all the statistics to a zero baseline.</li>
					<li><em>But</em> it will also mean that <em>all</em> users lose their record of previously-performed queries.</li>
					<li>Clearing the Query History affects <strong>all</strong> users and <strong>all</strong> corpora.</li>
					<li>
						You should not use this control unless you know what you're doing 
						and you are <em>sure</em> you want to delete the entire Query History.
					</li> 
				</ul>
				<p>Are you sure you want to clear the Query History?</p>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form class="greyoutOnSubmit" action="index.php" method="get">
					<br>
					<input type="checkbox" name="sureyouwantto" value="yes">
					Yes, I'm sure I want to do this.
					<br>&nbsp;<br>
					<input type="submit" value="I am definitely sure I want to clear the entire Query History.">
					<br>
					<input type="hidden" name="admF" value="clearQueryHistory">
				</form>
			</td>
		</tr>
	</table>					
		
	<?php
}


function do_adm_ui_phpconfig()
{
	if (isset ($_GET['showPhpInfo']) && $_GET['showPhpInfo'])
	{
		/* this messes up the HTML styling unfortunately, but I can't see a way to stop it from doing so */
		phpinfo();
		return;
	}
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">
				Internal PHP settings relevant to CQPweb
			</th>
		</tr>
		<tr>
			<td colspan="2" class="concordgrey" align="center">
				&nbsp;<br>
				To see the full phpinfo() dump, 
					<a href="index.php?ui=phpConfig&showPhpInfo=1">click here</a>.
				<br>&nbsp;	
			</td>
		</tr>
		
		<tr>
			<th colspan="2" class="concordtable">
				General
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				PHP version
			</td>
			<td class="concordgeneral">
				<?php echo phpversion(); ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Location of INI file
			</td>
			<td class="concordgeneral">
				<?php echo php_ini_loaded_file(); ?>
			</td>
		</tr>
		<tr>
			<td colspan="2" class="concordgrey" align="center">
				&nbsp;<br>
				The active settings shown below may not match those in the INI file if the latter have been overridden
				(e.g. by your web server's configuration).
				<br>&nbsp;	
			</td>
		</tr>


		<tr>
			<th colspan="2" class="concordtable">
				Memory and runtime
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				PHP's memory limit
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('memory_limit'), "\n"; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Maximum script running time 
				<br>
				<em>(turned off by some scripts)</em>
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('max_execution_time'), "\n"; ?> seconds
			</td>
		</tr>




		<tr>
			<th colspan="2" class="concordtable">
				File uploads
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				File uploads enabled
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('file_uploads')? 'On' : 'Off', "\n"; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Temporary upload directory
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('upload_tmp_dir'), "\n" ; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Maximum upload size
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('upload_max_filesize'), "\n"; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Maximum size of HTTP post data
				<br>
				<em>(NB: uploads cannot be bigger than this)</em>
			</td>
			<td class="concordgeneral">
				<?php echo ini_get('post_max_size'), "\n"; ?>
			</td>
		</tr>



		<tr>
			<th colspan="2" class="concordtable">
				SQL Database (MySQL / MariaDB)
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Server version
			</td>
			<td class="concordgeneral">
				<?php echo mysqli_get_server_info(get_global_sql_link()), "\n"; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Client PHP extension 
			</td>
			<td class="concordgeneral">
				<?php echo "MySQLi (improved) extension ", (extension_loaded('mysqli') ? '' : 'NOT'), " loaded\n"; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Client API version
			</td>
			<td class="concordgeneral">
				<?php echo mysqli_get_client_info(get_global_sql_link()), "\n"; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Socket on localhost
			</td>
			<td class="concordgeneral">
				<?php $sock = ini_get('mysql.default_socket'); echo empty($sock) ? '(Unspecified)' : $sock, "\n"; ?>
			</td>
		</tr>

	</table>
	
	<?php
}

function do_adm_ui_opcodecache()
{
	$mode = detect_php_opcaching();
	$mode_names = array ('apc'=>'APC', 'opcache'=>'OPcache', 'wincache'=>'WinCache');

	$codefiles = list_php_files('code');
	$stubfiles = list_php_files('stub');
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Opcode cache overview
			</th>
		</tr>
		<tr>
			<td class="concordgrey">
				<p class="spacer">&nbsp;</p>
				<p>
					Opcode caches are tools to speed up PHP applications like CQPweb. Several different ones are available,
					but any individual server will only use <i>one</i>. 
					<strong>OPcache</strong>, <strong>APC</strong>,  and <strong>WinCache</strong> are three opcode caches 
					that can be monitored from within CQPweb.
				</p>
				<?php
				echo '<ul>'
					, '<li><strong>OPcache</strong> ' , $mode == 'opcache'  ? 'is <u>active</u>' : 'is inactive or unavailable', '.</li>'
					, '<li><strong>WinCache</strong> ', $mode == 'wincache' ? 'is <u>active</u>' : 'is inactive or unavailable', '.</li>'
					, '<li><strong>APC</strong> '     , $mode == 'apc'      ? 'is <u>active</u>' : 'is inactive or unavailable', '.</li>'
					, "</ul>\n"
					;
				?>
				<p>
					Use the controls below to monitor your opcode cache, and to clear/reload it if necessary (e.g. after a version upgrade;
					should not normally be necessary as a properly-working opcode cache will reload from disk automatically as needed).
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
	
	<?php
	
	if (!$mode)
	{
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Opcode cache monitor unavailable
				</th>
			</tr>
			<tr>
				<td class="concordgrey" align="center">
					<p class="spacer">&nbsp;</p>
					<p>Opcode cache monitoring is not available (opcode cache extension not installed?)</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
		
		<?php	
	}
	else
	{
		switch($mode)
		{
		case 'apc':
			$info = apc_cache_info();
			$rawinfo = $info['cache_list'];
			$fnkey = 'filename';
			$func_date_timestamp = function ($x) {return $x["creation_time"];};
			$hitkey = 'num_hits';
			break;
		case 'opcache':
			$info = opcache_get_status(true);
			$rawinfo = $info['scripts'];
			$fnkey = 'full_path';
			$func_date_timestamp = function ($x) {return $x["timestamp"];};
			$hitkey = 'hits';
			break;
		case 'wincache':
			$info = wincache_ocache_fileinfo(false);
			$rawinfo = $info['file_entries'];
			$fnkey = 'file_name';
			$func_date_timestamp = function($x) {return (time() - $x["add_time"]);};
			$hitkey = 'hit_count';
			break;
		}
		$codeinfo = array();
		$stubinfo = array();
		
		foreach($rawinfo as $f)
		{
			if (in_array($f[$fnkey], $stubfiles))
				$stubinfo[$f[$fnkey]] = $f;
			else if(in_array($f[$fnkey], $codefiles))
				$codeinfo[$f[$fnkey]] = $f;
		}
		
		$n_cqpweb = ($n_stub = count($stubinfo)) + ($n_code = count($codeinfo));
		$n_overall = count($rawinfo);
		
		/* locationAfter for buttons */
		$loc = '&locationAfter=' . urlencode('index.php?ui=opcodeCache');
		
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="4">
					<?php echo $mode_names[$mode], ' status as of <u>', date(CQPWEB_UI_DATE_FORMAT), '</u>'; ?>
				</th>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="4" align="center">
					<p class="spacer">&nbsp;</p>
					<?php 
					echo "<p>The cache contains <strong>", $n_overall, "</strong> files, <strong>", $n_cqpweb, "</strong> of which are part of CQPweb.</p>"
						, "<p><strong>", $n_stub, "</strong> of these are stub-files and <strong>", $n_code, "</strong> of these are library code files (see below).</p>"
						, "<p>(Stub-files present on the system: <strong>", count($stubfiles), "</strong>).</p>\n"
 						; 
					?>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<th class="concordtable" colspan="4">Manipulate cache</th>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="4" align="center">
					<table class="basicbox fullwidth">
						<tr>
							<td class="basicbox" align="center" width="25%">
								<a class="menuItem" href="index.php?admF=execute<?php echo $loc; ?>&function=do_opcache_full_unload">
									[Clear all files from cache]
								</a>
							</td>
							<td class="basicbox" align="center" width="25%">
								<a class="menuItem" href="index.php?admF=execute<?php echo $loc; ?>&function=do_opcache_full_load&args=code">
									[Insert library files to cache]
								</a>
							</td>
							<td class="basicbox" align="center" width="25%">
								<a class="menuItem" href="index.php?admF=execute<?php echo $loc; ?>&function=do_opcache_full_load&args=stub">
									[Insert stub files to cache]
								</a>
							</td>
							<td class="basicbox" align="center" width="25%">
								<a class="menuItem" href="index.php?admF=execute<?php echo $loc; ?>&function=do_opcache_full_load">
									[Insert all files to cache]
								</a>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<th class="concordtable">Library file</th>
				<th class="concordtable">Last loaded</th>
				<th class="concordtable">Times reused</th>
				<th class="concordtable">Actions</th>
			</tr>
			
			<?php
			
			$chop_off = realpath('../lib/'). '/';
			
			foreach($codefiles as $f)
			{
				echo "<tr>\n", '<td class="concordgeneral">', str_replace($chop_off, '', $f), "</td>\n";
				if (isset ($codeinfo[$f]))
				{
					$i = $codeinfo[$f];
					echo '<td class="concordgeneral" align="center">', date(CQPWEB_UI_DATE_FORMAT, $func_date_timestamp($i)), "</td>\n"
						, '<td class="concordgeneral" align="center">', number_format($i[$hitkey]), "</td>\n"
						, '<td class="concordgeneral" align="center">'
							, '<a class="menuItem" href="index.php?admF=execute'
							, $loc, '&function=do_opcache_unload_file&args=', urlencode($f), '">[Unload]</a>' 
						, "</td>\n"
						;
				}
				else
				{
					echo  '<td class="concordgeneral" align="center" colspan="2">-</td>'
						, '<td class="concordgeneral" align="center">'
							, '<a class="menuItem" href="index.php?admF=execute'
							, $loc, '&function=do_opcache_load_file&args=', urlencode($f), '">[Load]</a>' 
						, "</td>\n"
						, "\n"
						;
				}
				echo "</tr>\n";
			}
			?>

		</table>
		
		<?php
	}
}



function do_adm_ui_publictables()
{
	?>

	<p class="errormessage">We're sorry, this function has not been built yet.</p>
	
	<?php
}




function do_adm_ui_embeddedpages()
{
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">
				Manage embedded pages
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="5">
				&nbsp;<br>
				
				&ldquo;Embedded pages&rdquo; are ad hoc web pages which you create and store on your server.
				
				<br>&nbsp;<br>
				
				Registering them here allows CQPweb to generate a view within the corpus-query homepage 
				which contains the content of that file. The view has an opaque URL.
				
				<br>&nbsp;<br>
				
				You can use the URL of an embedded page for documentation links, etc. The URLs are relative
				to any corpus-query or user-account homepage (including the help page system).  
					
				<br>&nbsp;<br>
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<th class="concordtable" colspan="5">
				Existing embedded pages
			</th>
		</tr>
		<tr>
			<th class="concordtable">ID</th>
			<th class="concordtable">Title</th>
			<th class="concordtable">File path</th>
			<th class="concordtable">Relative URL</th>
			<th class="concordtable">Delete</th>
		</tr>
		<?php

		$embeds = get_all_embeds_info();
		
		if (empty($embeds))
			echo '<tr><td class="concordgrey" colspan="5"><p>There are currently no registered embedded pages</p></td></tr>', "\n";
		
		$after = '&locationAfter=' . urlencode('index.php?ui=manageEmbeds');
			
		foreach($embeds as $ep)
			echo "\n\t\t<tr>"
 				, '<td class="concordgeneral" align="center">' , $ep->id , '</td>'
 				, '<td class="concordgeneral">' , escape_html($ep->title) , '</td>'
 				, '<td class="concordgeneral">' , escape_html($ep->file_path) , '</td>'
 				, '<td class="concordgeneral"><strong>' , 'index.php?ui=embed&id=', $ep->id, '</strong></td>'
 				, '<td class="concordgeneral" align="center"><a class="menuItem" href="index.php?admF=execute&function=delete_embed&args=' , $ep->id , $after, '">[x]</a></td>'
 		 		, "</tr>\n"
				;
 		?>
 	</table>
 	
 	<form class="greyoutOnSubmit" action="index.php" method="get">
		<input type="hidden" name="admF" value="registerEmbeddedPage">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">
					Add new embedded page
				</th>
			</tr>
			<tr>
				<td class="concordgeneral" width="50%" align="center">
					<p class="spacer">&nbsp;</p>
					<p>
						Page title:
						&nbsp;
						<input type="text" name="title" size="70" maxlength="255" placeholder="Please enter the title to appear at the top of the page (no HTML)">
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
				<td class="concordgeneral" width="50%" align="center">
					<p class="spacer">&nbsp;</p>
					<p>
						File path:
						&nbsp;
						<input type="text" name="path" size="70" maxlength="255" placeholder="Enter absolute path, or path relative to the CQPweb &ldquo;exe&rdquo; directory">
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2" align="center">
					<p class="spacer">&nbsp;</p>
					<p>
						<input type="submit" value="Register new embedded page">
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
	 	</table>
 	</form>
		
 	
 	<?php
}



