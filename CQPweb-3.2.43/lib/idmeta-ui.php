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
require('../lib/useracct-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/corpus-lib.php');
require('../lib/metadata-lib.php');
require('../lib/xml-lib.php');



/* declare global variables */
$Corpus = $User = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);




/* initialise variables from $_GET */

if (empty($_GET["idlink"]) )
	exiterror("No IDLINK was specified for metadata-view! Please reload CQPweb.");
else 
	$idlink = cqpweb_handle_enforce($_GET["idlink"]);

if (empty($_GET["id"]) )
	exiterror("No ID was specified for metadata-view! Please reload CQPweb.");
else 
	$id = cqpweb_handle_enforce($_GET["id"]);

/* OK, we now need to do our validity checking. */

$xml_atts = get_all_xml_info($Corpus->name);
if (!isset($xml_atts[$idlink]))
	exiterror("Cannot find information on IDLINK called '$idlink'.");

$idlink_desc_print = escape_html($xml_atts[$idlink]->description);


$field_info = get_all_idlink_field_info($Corpus->name, $idlink);

/* if we got back an empty array, then the idlink does not exist. */
if (empty($field_info))
	exiterror("We're sorry, but there is no metadata in the system for the linked ID-data you requested!");


$table = get_idlink_table_name($Corpus->name, $idlink);

$result = do_sql_query("SELECT * from `$table` where `__ID` = '$id'");

if (1 > mysqli_num_rows($result))
	exiterror("The database doesn't appear to contain any metadata for entity $id.");

$metadata = mysqli_fetch_assoc($result);


/*
 * Render!
 */

echo print_html_header($Corpus->title . ': viewing ' . $idlink_desc_print . ' metadata -- CQPweb', $Config->css_path, array('textmeta'));

?>

<table class="concordtable fullwidth">
	<tr>
		<th colspan="2" class="concordtable">Metadata for <?php echo $idlink_desc_print;?> <em><?php echo $id; ?></em></th>
	</tr>
	<tr>
		<th width="40%"></th>
		<th></th>
	</tr>

<?php

foreach ($metadata as $field => $value)
{
	if (isset($field_info[$field]))
	{
		/* standard field */
		$desc = escape_html($field_info[$field]->description);

		/* don't allow empty cells */
		if (empty($value))
			$show = '&nbsp;';
		else if (METADATA_TYPE_FREETEXT == $field_info[$field]->datatype)
			$show = render_metadata_freetext_value($value);	
		else if (METADATA_TYPE_CLASSIFICATION == $field_info[$field]->datatype)
		{
			$catdescs = idlink_category_listdescs($Corpus->name, $idlink, $field);
			$show = escape_html($catdescs[$value]);
		}
		else
			$show = $value;
	}
	else
	{
		/* non standard, hardwired fields */
		switch($field)
		{
		case '__ID':
			$desc = $idlink_desc_print . ' identification code';
			$show = $value;
			break;
		case 'n_items':
			$desc = 'No. ' 
 					. escape_html($xml_atts[$xml_atts[$idlink]->att_family]->description) 
 					. ' units linked to this ' . $idlink_desc_print . ' ID'; 
			$show = number_format($value, 0);
			break;

		case 'n_tokens':
			$desc = 'No. tokens for this ' . $idlink_desc_print . ' ID'; 
			$show = number_format($value, 0);
			break;

		default:
			/* in future there may be cached-data-fields here: don't show them */
			continue 2;
		}
	}

	echo '<tr><td class="concordgrey">' , $desc, '</td><td class="concordgeneral">' , $show, "</td></tr>\n";
}

?>

</table>

<?php


// echo print_html_footer('idmeta');//TODO once we have a help video
echo print_html_footer();

cqpweb_shutdown_environment();


