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
 * show just one factanal solution 
 */
function show_solution(sol)
{
	for (var j = 2 ; j < 8 ; j++)
	{
		if ($("#solution" + j).is(':visible'))
			$("#solution" + j).fadeOut();
	}
	$("#solution" + sol).fadeIn();
}

/**
 * Sets up the display function. 
 */
$(function() {

	for (var i = 2 ; i < 8 ; i++)
	{
		$("#solButton" + i).click( (function (par) { return function(){show_solution(par);return false;}; })(i) );
		$("#solution" + i).hide();
	}

	show_solution(2);
} );
