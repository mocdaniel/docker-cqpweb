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
 * TODO : Replace the contents of thjis file with jQuery, so we get cross browser support.
 * (Current code tested only in Chromium) 
 */

function distTableSort(link, type)
{
	var table = link.parentNode.parentNode.parentNode;
	var toprow = link.parentNode.parentNode;
	
	var sort_function = distTableSort_catorder;
	if (type == "freq")
		sort_function = distTableSort_freqorder;
	if (type == "extr")
		sort_function = distTableSort_extrorder;

	var newRows = new Array();
	var index_of_toprow = -1;
	var bottomrow;
	
	for (var i = 1 ; i < table.rows.length ; i++)
	{
		if (table.rows[i] == toprow)
		{
			index_of_toprow = i;	
			break;
		}
	}

// not sure how the bug below crept in, but it's a funny un.	
//	for (var j = index_of_toprow + 2 ; j < table.rows.length ; j++)
	for (var j = index_of_toprow + 1 ; j < table.rows.length ; j++)
	{
		if (table.rows[j].cells[0].className == "concordgrey")
		{
			bottomrow = table.rows[j];
			break;
		}
		newRows.push(table.rows[j]);
	}
	
	newRows.sort(sort_function);
	
	for (var k = 0; k < newRows.length; k++)
	{
		table.insertBefore(newRows[k], bottomrow);
	}
}

function distTableSort_freqorder(a,b)
{
	if (parseFloat(a.cells[4].innerHTML.replace(/,/g, "")) < parseFloat(b.cells[4].innerHTML.replace(/,/g, "")) ) 
		{ return 1; }
	if (parseFloat(a.cells[4].innerHTML.replace(/,/g, "")) > parseFloat(b.cells[4].innerHTML.replace(/,/g, "")) ) 
		{ return -1; }
	return 0;
}

function distTableSort_extrorder(a,b)
{
	if (parseFloat(a.cells[6].innerHTML.replace(/,/g, "")) < parseFloat(b.cells[6].innerHTML.replace(/,/g, "")) ) 
		{ return 1; }
	if (parseFloat(a.cells[6].innerHTML.replace(/,/g, "")) > parseFloat(b.cells[6].innerHTML.replace(/,/g, "")) ) 
		{ return -1; }
	return 0;
}

function distTableSort_catorder(a,b)
{
	if (a.cells[0].id.toLowerCase().trim() < b.cells[0].innerHTML.toLowerCase().trim() ) 
		{ return -1; }
	if (a.cells[0].id.toLowerCase().trim() > b.cells[0].innerHTML.toLowerCase().trim() ) 
		{ return 1; }
	return 0;
}
