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
 * This file contains the code for the image-up control in the text-metadata display.
 */


function textmeta_add_iframe(url)
{
	var is_image;
	var iframe_line;
	var tsize;

	greyout_on();

	/* nb it is impossible to adjust an iframe so that the webpage content is centred ... cos the javascript can't reach inside the DOM of another domain. 
	   For that reason, if we think the URL is an image, we load it as an <img>, so we can do clever things with its size / position; otherwsie we assume
	   it's something like a PDF or HTML document, and we open it in as big an iframe as we can. 
	 */
	if (/\.(jpg|jpeg|png|bmp|gif|tif|tiff|svg)$/i.test(url))
	{
		is_image = true;
		tsize = ""; /* allows container to resize to the image */
		iframe_line = "<img id='textmeta_img' src='" + url + "'>";
	}
	else
	{
		is_image = false;
		tsize = " width='100%' height='100%'";
		iframe_line = "<iframe id='textmeta_embed' height='100%' width='100%' src='" + url + "'></iframe>";
	}

	/* NOTE: this copies && modifies code from the "throbber" function in modal.js; when that
	   is generalised, it might be possible to use it instead. */
	var t = $(
		"<div id='textmeta_embed_holder' align='center'><table width='100%' class='concordtable'><tr><th width='50%' class='concordtable' align='left'>" + 
		"<a target='_blank' class='menuItem' href='" + url + "'>[Popout]</a></th><th class='concordtable' align='right'>" + 
		"<a class='menuItem' href='' onClick='textmeta_close_iframe();'>[Close]</a></th></tr></table>" +
		"<table" + tsize + " class='concordtable'><tr><td class='concordgeneral' valign='center' align='center'>" + 
		iframe_line +
		"</td></tr></table></div>"
		);

	/* for non-images, add size settings for the container, to allow the iframe to be big. */
	if (!is_image)
		t.css({	width: '85%', height: '80%' });

	/* either way, centre it on the greyout. */
	t.css(	{
			position: 'absolute',
			left: '50%',
			top: '50%',
			transform: 'translate(-50%, -50%)',
			'background-color': 'white'   /* i.e. provide a white backdrop over the greyout, like the base layer of the page. */
		});
	
	t.appendTo($("#div_for_greyout"));
}



function textmeta_close_iframe()
{
	$("#textmeta_embed_holder").remove();
	greyout_off();
}





