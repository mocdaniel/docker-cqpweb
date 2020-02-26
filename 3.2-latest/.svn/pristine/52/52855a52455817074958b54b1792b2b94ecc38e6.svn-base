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
 * functions for expanding the "install corpus" ^& template forms
// TODO maybe change the filename to "template-embiggen" instead?
 */


function add_s_attribute_row()
{
	var namebase = $("#inputNameBaseS").val();

	var number = 1 + parseInt($("#sNumRows").val());

	/* note, this HTML closely matches similar lines in the PHP code that creates the initial table; they must be kept sync'ed! */
	var three_cells_html = ""
			+ "<td id=\"cell" + namebase + "SHandle" + number + "\" align=\"center\" class=\"concordgeneral\">"
				+ "<input type=\"text\" maxlength=\"64\" name=\"" + namebase + "SHandle" + number + "\" onKeyUp=\"check_c_word(this)\" />"
			+ "</td>"
			+ "<td id=\"cell" + namebase + "SDesc" + number + "\" align=\"center\" class=\"concordgeneral\">"
				+ "<input type=\"text\" maxlength=\"255\" name=\"" + namebase + "SDesc" + number + "\" />"
			+ "</td>"
			+ "<td id=\"addXmlAttributeButtonCellFor" + number + "\" align=\"center\" class=\"concordgeneral\" colspan=\"3\">"
				+ "<a class=\"menuItem\" onClick=\"add_xml_attribute_to_s(" + number + ")\">[Add attribute slot]</a>"
			+ "</td>"
			;

	var hidden_tracker_html = "<input type=\"hidden\" name=\"nOfAttsFor" + namebase + "Xml" + number + "\" id=\"nOfAttsFor" + namebase + "Xml" + number + "\" value=\"0\" />";

	/* create the new nodes by patching all the HTML together */
	var newrow = $("<tr id=\"row_for_S_" + number + "\">" + three_cells_html + "</tr>" + hidden_tracker_html);

	$("#s_embiggen_button_row").before( newrow );

	/* and update the stored number */
	$("#sNumRows").val(number.toString());
}


function add_xml_attribute_to_s(n_of_s)
{
	var namebase = $("#inputNameBaseS").val();

	/* this is a bit of a cheat. because we are using PHP constanrts for values, 
	 * we get innerHTML from a hidden <select>
	 * tucked into the page by the server-side function. */
	var datatype_options = $("#getDataTypeOptionsFromHere").html();

	/* work out the number of the new attribute on this element */
	var n_of_atts_already = parseInt($("#nOfAttsFor" + namebase + "Xml" + n_of_s).val());
	var n_of_att = n_of_atts_already + 1;

	var att_label = "Att" + n_of_s + "_" + n_of_att;

	/* OK, so let's DO THIS */
	var three_new_cells_html = ""
			+ "<td align=\"center\" class=\"concordgeneral\">"
				+ "<input type=\"text\" maxlength=\"64\" name=\"" + namebase + "SHandle" + att_label + "\" onKeyUp=\"check_c_word(this)\" />"
			+ "</td>"
			+ "<td align=\"center\" class=\"concordgeneral\">"
				+ "<input type=\"text\" maxlength=\"255\" name=\"" + namebase + "SDesc" + att_label + "\" />"
			+ "</td>"
			+ "<td align=\"center\" class=\"concordgeneral\">"
				+ "<select name=\"" + namebase + "SType" + att_label + "\">" + datatype_options + "</select>"
			+ "</td>"
			;

	/* (1) increase the rowspan of the cell{$BASE}SHandleXXX and cell{$BASE}SHandleXXX  by 1 */
	$("#cell" + namebase + "SHandle" + n_of_s).prop('rowspan', n_of_att+1);
	$("#cell" + namebase + "SDesc"   + n_of_s).prop('rowspan', n_of_att+1);

	if (n_of_att < 2)
	{
		/* (2) IF this is the first attribute, create the three new cells outside of a row */
		var newcells = $(three_new_cells_html);
		/* and now move stuff about. First, extract the cell cotnaining the button, and its row */
		var buttoncell = $("#addXmlAttributeButtonCellFor" + n_of_s);
		var buttonrow = buttoncell.parent();
		buttoncell.detach();
		/* put the 3 new cells in its place */
		buttonrow.append(newcells);
		/* create a new row containing just the button, and add it after the base row for the s-attribute in question. */
		var newrow = $("<tr></tr>");
		newrow.append(buttoncell);
		buttonrow.after(newrow);
	}
	else
	{
		/* (3) otherwise, create 3 new cells within a new row */
		var newrow = $("<tr>" + three_new_cells_html + "</tr>");
		/* put that row before the row containng the button */
		$("#addXmlAttributeButtonCellFor" + n_of_s).parent().before(newrow);
	}

	/* (4) update number of atts on this element.... */
	$("#nOfAttsFor" + namebase + "Xml" + n_of_s).val(n_of_att.toString());
}


function add_p_attribute_row()
{
//TODO migrate this func to jQuery, which will be drastically more condensed (See above)

	var number = 1 + Number(document.getElementById('pNumRows').value);
	document.getElementById('pNumRows').value = number;
	
	var namebase = document.getElementById('inputNameBaseP').value;

	var theTr = document.createElement('tr');

	var theTd = document.createElement('td');
	var theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','radio');
	theIn.setAttribute('name', namebase+'PPrimary');
	theIn.value = number;
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);
	
	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','15');
	theIn.setAttribute('name', namebase+'PHandle'+number);
	theIn.setAttribute('onKeyUp','check_c_word(this)');
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name',namebase+'PDesc'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name',namebase+'PTagset'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);

	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','text');
	theIn.setAttribute('maxlength','150');
	theIn.setAttribute('name',namebase+'Purl'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);
	
	theTd = document.createElement('td');
	theIn = document.createElement('input');
	theTd.setAttribute('align','center');
	theTd.setAttribute('class','concordgeneral');
	theIn.setAttribute('type','checkbox');
	theIn.setAttribute('value','1');
	theIn.setAttribute('name',namebase+'Pfs'+number);
	theTd.appendChild(theIn);
	theTr.appendChild(theTd);
	
	document.getElementById('p_att_row_1').parentNode.insertBefore(theTr, document.getElementById('p_embiggen_button_row'));
}

