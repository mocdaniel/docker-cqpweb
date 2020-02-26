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



/* initialise variables from settings files  */
require('../lib/environment.php');


/* include function library files */
require('../lib/general-lib.php');
require('../lib/sql-lib.php');
require('../lib/useracct-lib.php');
require('../lib/html-lib.php');
require('../lib/corpus-lib.php');
require('../lib/metadata-lib.php');
require('../lib/exiterror-lib.php');



/* declare global variables */
$Corpus = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);




/* initialise variables from $_GET */

if (empty($_GET["text"]) )
	exiterror("No text was specified for metadata-view! Please reload CQPweb.");
else 
	$text_id = cqpweb_handle_enforce($_GET["text"], HANDLE_MAX_ITEM_ID);


$field_info = get_all_text_metadata_info($Corpus->name);


/*
 * Render!
 */

echo print_html_header($Corpus->title . ': viewing text metadata -- CQPweb', $Config->css_path, array('textmeta'));

?>

<table class="concordtable fullwidth">
	<tr>
		<th colspan="2" class="concordtable">Metadata for text <em><?php echo $text_id; ?></em></th>
	</tr>
	<tr>
		<th width="40%"></th>
		<th></th>
	</tr>

	<?php
	
	foreach (metadata_of_text($Corpus->name, $text_id) as $field => $value)
	{
		if (isset($field_info[$field]))
		{
			/* standard field */
			$desc = escape_html($field_info[$field]->description);
	
			/* don't allow empty cells */
			if (empty($value))
				$show = '&nbsp;';		
			else if (METADATA_TYPE_FREETEXT == $field_info[$field]->datatype)
				$show = render_metadata_freetext_value($value); /* which also HTML-escapes */
			else if (METADATA_TYPE_CLASSIFICATION == $field_info[$field]->datatype)
			{
				$pair = expand_text_metadata_attribute($Corpus->name, $field, $value);
				$show = escape_html($pair['value']);
			}
			else
				$show = escape_html($value);
		}
		else
		{
			/* non standard, hardwired fields */
			if ($field == 'text_id')
			{
				$desc = 'Text identification code';
				$show = escape_html($value); /* this is paranoid ... but just in case. */
			}
			/* this expansion is hardwired */
			else if ($field == 'words')
			{
				/* save for last */
				$n_words_row = '<tr><td class="concordgrey">No. words in text</td><td class="concordgeneral">' . number_format($value, 0) . "</td></tr>\n";
				continue;
			}
			/* don't show the CQP delimiters for the file */
			else if ($field == 'cqp_begin' || $field == 'cqp_end')
				continue;
		}
		
		echo '<tr><td class="concordgrey">' , $desc, '</td><td class="concordgeneral">' , $show, "</td></tr>\n";
	}
	
	echo $n_words_row;
	
	?>
</table>

<?php

echo print_html_footer('textmeta');

cqpweb_shutdown_environment();


