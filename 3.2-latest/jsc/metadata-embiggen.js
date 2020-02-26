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

/*
 * functions for expanding the "insert corpus/xml metadata" & metadata template forms
 */

function add_metadata_form_row()
{
	/* get the number of the row to create */
	var row_number = 1 + parseInt( $("#fieldCount").val() );

	/* create the new row element */
	var new_row = $("\
			<tr>\
				<td class=\"concordgeneral\">Field " + row_number + "</td>\
				<td class=\"concordgeneral\" align=\"center\">\
					<input type=\"text\" name=\"fieldHandle" + row_number + "\" maxlength=\"64\" onKeyUp=\"check_c_word(this)\" />\
				</td>\
				<td class=\"concordgeneral\" align=\"center\">\
					<input type=\"text\" name=\"fieldDescription" + row_number + "\" maxlength=\"255\"/>\
				</td>\
				<td class=\"concordgeneral\" align=\"center\">\
					<select name=\"fieldType" + row_number + "\" align=\"center\">\
						<!-- options to go here -->\
					</select>\
				</td>\
				<td class=\"concordgeneral\" align=\"center\">\
					<input type=\"radio\" name=\"primaryClassification\" value=\"" + row_number + "\"/>\
				</td>\
			</tr>\
		");
	/* insert the row into the DOM before the row containing the button */
	$("#metadataEmbiggenRow").before(new_row);
	
	/* clone the option elements, and add as children to the <select> element in the above */
	var opts = $("select[name=fieldType1]").children().clone();
	$("select[name=fieldType" + row_number + "]").append(opts);

	/* and finally, update the number of rows. */
	$("#fieldCount").val(row_number.toString());
	
}
