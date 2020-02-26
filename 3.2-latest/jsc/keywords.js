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
 * This file contains javascript code to drive the keywords interface ... mostly, it must be said, the wordclouds.
 * 
 * A note for the record. I considered this alternative library,
 *       https://github.com/jasondavies/d3-cloud
 * but the wordcloud2 library seems drastically more commonly used.
 */




/* global variable for SVG canvases */
var keyCloudSvgCanvases;





/** Gets an array of text lines for the caption */
function get_keycloud_caption_line_array()
{
	return cloud_caption_line_array;
}



/** Utility func used for the downloading of both kinds of cloud. */
function get_keycloud_canvas_extra_height(n_lines)
{
	/* this is the once-and-for-all setting of the extra height/font size of caption. */
	return { 
		base      : (20 * (1 + n_lines)) , 
		increment : 25 , 
		gx_bump   : 40,
		x_offset  : 100
		};
	/* the x_offset is here only for convenience. The bump is the height added for graphix clouds. */
}


// TODO this is only relevant for wmatrix style. 
/**
 * Return value: DOM object for the new "canvas" element.
 * Parameters are boolean.
 */
function create_background_svg_canvas(with_caption, in_monochrome)
{
	/* the height and the width that will be imposed. */
	var svg_area_w = 1200;
	var svg_area_h =  600;

	var image_y = 0;
	if (with_caption)
		image_y = -30; /* to get the position right, this needs to be shinmted upwards a bit. */
	
	
	var svg = document.getElementById('keyItemCloud');
clog(svg);

	var svg_as_str = new XMLSerializer().serializeToString(svg);
	
	/* turn tyhe boderder white and make it thicker (to get left/right padding in iomg to look like that in the main cloud).*/
	svg_as_str = svg_as_str.replace('border: 1px solid', 'border: 5px solid white');
	/* empirically, 5px does the trick. */
	
	/* to get monochrome, change styling of the <a> elements. */
	if (in_monochrome)
		svg_as_str = svg_as_str.replace('color: blue;', 'color: black;');
	
	/* let's build our image */
	var tmp = new Image(svg_area_w, svg_area_h);
	tmp.src = "data:image/svg+xml," + encodeURIComponent(svg_as_str);
	
	/* now, add that img to a canvas */
	var cnv = document.createElement('canvas');
	
	cnv.width  = svg_area_w;
	
	if (with_caption)
	{
		/* as we're sizing the cnv NOW, we need to set up for adding a caption NOW */
		var caption_lines = get_keycloud_caption_line_array();
		
		var extra_height  = get_keycloud_canvas_extra_height(caption_lines.length);
		
		cnv.height = svg_area_h + extra_height.base ;
	}
	else
		cnv.height = svg_area_h;
	
	var context = cnv.getContext('2d');
	
	context.fillStyle = "white";
	context.fillRect(0, 0, cnv.width, cnv.height);
	context.fillStyle = "blue";
	context.font = "Arial";


	/* tmp images must be in the DOM or it doesn't appear on the canvas; but we hide them immediately. */
	$(tmp).hide();
	$("body").append(tmp);
	
	/* stick the drawing into an onload closure, so that it won't try to draw on the canvas
	 * until everything is ready. */ 
	tmp.onload = function () 
	{ 	
		context.drawImage(tmp, 0, image_y);  

		/* OK, now add write the caption */
		if (with_caption)
		{
			context.font = extra_height.increment.toString() + "px Verdana";
			context.fillStyle = "black";

			for (var i = 0; i < caption_lines.length ; i++ )
				context.fillText(
						caption_lines[i], 
						/*x*/ extra_height.x_offset, 
						/*y*/ svg_area_h + (i * extra_height.increment)
					);
		}
	};
	
	return cnv;
}








/** This function forces the browser to run a concordance for the item clicked. */ 
function navigate_to_link_for_item(e)
{
	/* get a reference to the global var holding the links. Navigate to it. */
	if (wordcloudClickableItemLinks[e.target.innerHTML])
		window.location.href = wordcloudClickableItemLinks[e.target.innerHTML];
}




/** 
 * this function highlights an item when hovered by toggling 
 *  (a) the colour to its complementary colour,
 *  (b) italic on/off (will work regardless of which is the base state),
 *  (c) the cursor to be a poitner (as if this were a link, which 
 * Should be used for mouseover/mouseout ie via jQuery.hover().
 */
function highlight_link_for_item(e)
{
	var jq = $(e.target);

	/* color alteration: involves yanking numbers from a regex, and then doing some jiggery-pokery. */  
	var cols = ( jq.css("color") ).match(/rgb\((\d+),\s*(\d+),\s*(\d+)\s*\)/)
		, highest = Math.max(cols[1], cols[2], cols[3])
		, lowest  = Math.min(cols[1], cols[2], cols[3])
		;
	for (let i = 1 ; i <= 3 ; i++)
		if (highest == cols[i])
			cols[i] = lowest;
		else if (lowest == cols[i])
			cols[i] = highest;
		else
			cols[i] = (lowest + highest) - cols[i];
	jq.css("color", 'rgb(' + cols[1] + ', ' + cols[2] +', '+ cols[3] + ')');

	/* italicisation switch */
	jq.css("font-style", ("italic"  == jq.css("font-style") ? "normal"  : "italic") );

	/* cursor appearance switch */
	jq.css("cursor",     ("pointer" == jq.css("cursor")     ? "default" : "pointer") );
}














/*
 * ===========================
 * KEYWORD CODE INITIALISATION
 * ===========================
 */
$( function() {

	// TODO this comes from  the initialisation for wmatrix styl;e cloud. it will break the graphivcal ones. 
	// see commit 1184.
	
	if (!we_want_a_cloud)
		return;

	/* first, for download: create two pairs of hidden canvases and fills it. */
	keyCloudSvgCanvases = { 
		with_caption         : create_background_svg_canvas(true , false), 
		with_caption_mono    : create_background_svg_canvas(true , true ), 
		without_caption      : create_background_svg_canvas(false, false),
		without_caption_mono : create_background_svg_canvas(false, true ) 
	};

	$('#dl-img-caption').click(
		function() { $(this)[0].href = keyCloudSvgCanvases.with_caption.toDataURL('image/png'); }
	);
	$('#dl-img-caption-mono').click(
		function() { $(this)[0].href = keyCloudSvgCanvases.with_caption_mono.toDataURL('image/png'); }
	);
	$('#dl-img-nocaption').click(
		function() { $(this)[0].href = keyCloudSvgCanvases.without_caption.toDataURL('image/png'); }
	);
	$('#dl-img-nocaption-mono').click(
		function() { $(this)[0].href = keyCloudSvgCanvases.without_caption_mono.toDataURL('image/png'); }
	);
	
	$('#dl-img-nocaption,#dl-img-nocaption-mono').click(
			function()
			{
				var canvas 
					= document.getElementById( (this.id.match(/-mono$/) ? 'keyItemCloudCanvasMonochrome' : 'keyItemCloudCanvas') );
				
				$(this)[0].href = canvas.toDataURL('image/png');
			}
	);
	
	// tODO I think this is for non-wmx stylee??? no??
	$('#dl-img-caption,#dl-img-caption-mono').click(
			function()
			{
				/* let's set up some variables first of all. */
				var caption_lines = get_caption_line_array(); 

				/* how much height do we have to add to the canvas? 30 px times n of lines. */
				var extra_height = get_keycloud_canvas_extra_height(caption_lines.length);
				
				
				/* get a new canvas for the download which duplicates the old one */
				var canvas1     = document.getElementById(
									(this.id.match(/-mono$/) ? 'keyItemCloudCanvasMonochrome' : 'keyItemCloudCanvas')
								);
				
				var canvas2    = document.createElement('canvas');
				canvas2.width  = canvas1.width;
				canvas2.height = canvas1.height + extra_height.gx_bump + extra_height.base ;
				                 /* we bump the basic extra height, as it isn't really enough for graphical clouds. */
				
				var context = canvas2.getContext('2d');
				context.fillStyle = "white";
				context.fillRect(0, 0, canvas2.width, canvas2.height);
				context.drawImage(canvas1, 0, 0);
				
				/* OK, now write the caption */
				
				/* use line increment px as font size. */
				context.font = extra_height.increment.toString() + "px Verdana";
				context.fillStyle = "black";
				
				for (var i = 0; i < caption_lines.length ; i++ )
					context.fillText(caption_lines[i],
							/*x*/ extra_height.x_offset, 
							/*y*/ canvas1.height + extra_height.gx_bump + (i*extra_height.increment)
						); 
				
				$(this)[0].href = canvas2.toDataURL('image/png');
			}
		);
	
	// TODO weird conditionm.
	if (!cloud_wmx_style)
		return;
	
	WordCloud(
			[document.getElementById('keyItemCloudCanvas'), document.getElementById('keyItemCloudDiv')] ,
			{
				classes : 'wordcloudClickableItem',
				list    : wordcloudDataObject
			}
		);

	// TODO 
	
	
	$('#keyItemCloudDiv').on(
			'wordcloudstop', 
			function() 
			{
				/* make links work */
				$(".wordcloudClickableItem")
					.click(navigate_to_link_for_item)
					.hover(highlight_link_for_item)
					;
				
				
				/* NOW create the background cloud canvas in greyscale */ 
				
				var src_canvas  = document.getElementById('keyItemCloudCanvas');
				var src_context = src_canvas.getContext("2d");
				
				var dst_canvas  = document.getElementById('keyItemCloudCanvasMonochrome');
				var dst_context = dst_canvas.getContext("2d");
				
				var mono_img = new Image();
				mono_img.src = src_canvas.toDataURL();
				
				/* CLOSURE: so that we wait for the image above to be loaded before draawing it. */
				mono_img.onload = function() 
				{
					dst_context.drawImage(mono_img, 0, 0);
				
					var imgdata_holder = dst_context.getImageData(0, 0, dst_canvas.width, dst_canvas.height);
					
					var imgdata = imgdata_holder.data;
					
					/* go pixel by pixel and adjust to greyscale */
					var i = 0, n = imgdata.length, luma;
				
					while (i < n)
					{
						/* many ways to calculate luma...  they come from different TV standards. */
						if ( 'undefined' == typeof luma_method )
							luma_method =  'Rec709';
						switch (luma_method)
						{
						case 'pureBW':
							/* any nonwhite pixel becomes black. */
							luma = (imgdata[i]==255 && imgdata[i+1]==255 && imgdata[i+2]==255) ? 255 : 0;
							break;
							
						case 'Rec601':
							luma = Math.floor( (0.299 * imgdata[i]) + (0.587 * imgdata[i+1]) + (0.114 * imgdata[i+2]) );
							break;
							
						case 'Rec2100':
							luma = Math.floor( (0.2627 * imgdata[i]) + (0.6780 * imgdata[i+1]) + (0.0593 * imgdata[i+2]) );
							break;
							
						case 'Rec709':
						default:
							luma = Math.floor( (0.2126 * imgdata[i]) + (0.7152 * imgdata[i+1]) + (0.0722 * imgdata[i+2]) ); 
							break;
						}
						imgdata[i++] = luma;
						imgdata[i++] = luma;
						imgdata[i++] = luma;
						i++;
					}
					
					/* return data to image */
					dst_context.putImageData(imgdata_holder,0,0);
				}
			}
		);
		
} );         /* end of onReady code */
