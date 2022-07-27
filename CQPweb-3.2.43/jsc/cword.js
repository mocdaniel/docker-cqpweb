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
 * This file enables the use of c_word checking on forms in the page.
 * 
 * TODO : translate to less wordy / quirks-prone jQuery.
 */


/*
 * The setup function.
 */
$(document).ready (function()
{
	/* create global variables for c_word checking */
	c_word_error_block = null;
	c_word_error_showing = false;

	/* setup for c_word-checking */
	c_word_error_block = document.createElement("div");
	var warningspan = document.createElement("span");
	warningspan.appendChild(document.createTextNode("WARNING!"));
	c_word_error_block.appendChild(warningspan);
	c_word_error_block.appendChild(document.createElement("br"));
	c_word_error_block.appendChild(document.createTextNode(" "));
	c_word_error_block.appendChild(document.createElement("br"));
	c_word_error_block.appendChild(document.createTextNode("You should only use plain unaccented letters, digits and the underscore in this entry on the form."));
	document.body.appendChild(c_word_error_block);
	hide_c_word_error();

	warningspan.style.color = "red";
	warningspan.style.fontWeight = "bold";
	warningspan.style.fonsize = "small";

	c_word_error_block.style.width = "150";
	c_word_error_block.style.border = "medium solid red";
	c_word_error_block.style.padding = "5";
	c_word_error_block.style.backgroundColor = "cyan";
	c_word_error_block.style.fontFamily = "Verdana,Arial";
	c_word_error_block.style.fontSize = "x-small";
});





function is_c_word(string)
{
	if (string.match(/[^A-Za-z0-9_]/))
		return false;
	else
		return true;
}

function show_c_word_error(on_element)
{
	c_word_error_showing = true;
	c_word_error_block.style.position = "absolute";
	var coords = get_element_bottom_left_corner_coords(on_element);
	c_word_error_block.style.left = coords[0].toString() + "px";
	c_word_error_block.style.top = coords[1].toString() + "px";
	c_word_error_block.style.display = "block";
}

function hide_c_word_error()
{
	c_word_error_block.style.display = "none";
	c_word_error_showing = false;
}

function check_c_word(element)
{
	if (! is_c_word(element.value))
	{
		if (c_word_error_showing)
			hide_c_word_error();
		show_c_word_error(element);
	}
	else
	{
		if (c_word_error_showing)
			hide_c_word_error();
	}
}


/**
 * Function used by the cword module. 
 */
function get_element_bottom_left_corner_coords(element)
{
	/* the X axis gets a start of "a bit" (enough to make it look right on chromium) */
	var x_coord = 11;
	/* we give the Y axis a "start" of its height, plus "a bit" (enough to make it look right on chromium) */
	var y_coord =  element.offsetHeight + 12; 

	while (element)
	{
		x_coord += element.offsetLeft;
		y_coord += element.offsetTop;
		element = element.offsetParent;
	}

	return new Array (x_coord, y_coord);
}

