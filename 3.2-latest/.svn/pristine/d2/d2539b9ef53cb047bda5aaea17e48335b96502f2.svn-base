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



/* global code: create the object where we will stick stuff. */

var cqpwebToolTipData = {

		/* options array: all settings that can be set are set here. */ 
		option   : {
			/* number of milliseconds to deklay relocating a tooltip
			 * after a prior relocate. */
			delay_between_tip_moves : 5,
			
			/* pixel offset between the mouse and the tooltip's top-left corner. */
			mouse_offset_x : 12,
			mouse_offset_y : 15
		},
		
		/* other variables = stuff that is stashed. */

		/* if a tip is visiible, we stash its ID here.  */
		active_id : null,
		
		/* cache of height/width of active tip */
		active_width    : 0,
		active_height   : 0,
		
		/* limits on how right-downwards the tip can move. (reset for each active tip, as their size varies.) */
		display_limit_x : 0,
		display_limit_y : 0,
		
		/* this stops moves occurring more than every 10 ms */
		wait_before_move : false
	
};


function do_tooltip_setup()
{
	/* tooltip requires mouse enter/leave events on the actual tipp'ed elements ... */
	$(".hasToolTip")
		.mouseenter( activate_tooltip )
		.mouseleave( deactivate_tooltip )
		;
	
	/* and we need the overall document mouse mover to update tooltiup location as necessary. */
	document.onmousemove = tooltip_move_tracker;
	
	return true;
}


/** return result of a jQuery search for tooltip elements given a tooltip ID. */
function get_tip_jq_from_id(tip_id)
{
	/* because in a css selector, : will be interepreted incorrectly by jQuery */
	return $('[id="' + tip_id + '"]');
}


/**
 * Switch off the active hovering tip.
 * For use as an "onmouseout" function, but we avolid
 * an "event" parameter so it can also be used freestanding. 
 */
function deactivate_tooltip()
{
	var data = cqpwebToolTipData;
	
	/* the vanishing!! */
	var jQ = get_tip_jq_from_id(data.active_id);

	/* the "hide" location is negative width-and-height of the tooltip, plus a bit. */
	jQ.offset( {top: ( - data.active_height - 10), left: ( - data.active_width - 10) } )
		.css('visibility', 'hidden')
		.css('z-index', -1010)
		;
	
	/* clear data out of global data archive. */
	data.active_id       = null;
	data.active_width    = data.active_height   = 0;
	data.display_limit_x = data.display_limit_y = 0;
	/* setting the above stuff to zero may not be necessary, but just in case ! */
	
	return true;
}


/**
 * Event handler for mouseover on elements with the ToolTip nature.
 * We generate these on demand, but then are left floating around in hide mode.
 * 
 * @param e Event   Standard event object. 
 */
function activate_tooltip(e)
{
	/* our global-object shortcut. */
	var data = cqpwebToolTipData;
	
	/* is there a tooltip already switched on? */
	if (null !== data.active_id)
	{
		/* is this tooltip the one that is currently active? 
		 * if so, do nothing. */
		if (data.active_id == tip_id)
			return;
		else
			deactivate_tooltip();
	}

	/* OK, either way we now have no tooltip active. */
	
	var tip_id = "ttFor:" + e.target.id;
	
	/* check whether we have a jQ object for a tip for this element. */
	var jQ = get_tip_jq_from_id(tip_id);

	if (0 == jQ.length)
	{
		/* we don't: we need to create one. */
		
		/* create hovering tooltip as div */
		jQ = $( "#templateForToolTip" ).clone();
		
		/* set its ID. */ 
		jQ.attr("id", tip_id);
		
		/* get the html of the tip. It is a string (with double quotes escaped) */
		var tooltip_data_att = $(e.target).attr("data-tooltip");
		
		/* add the html in there. */
		jQ.find(".floatingToolTipTarget").html(tooltip_data_att);
		
		/* do we need to add to DOM???*/
		$("body").append(jQ);
	}
	
	/* as this is now the active tt, store its info in our global data object. */

	data.active_id = tip_id; 
	
	data.active_width  = jQ[0].offsetWidth;
	data.active_height = jQ[0].offsetHeight;

	/* X and Y limits for this floating tipbox: the top-left corner of the tip cannot move past here. 
	 * These must be set BEFORE we attempt to position the tooltip. */
	
	/* the X limit:  window width, plus window page X offset, minus the width of the tipbox */ 
	data.display_limit_x = window.innerWidth  + window.pageXOffset - data.active_width; 
	
	/* the Y limit:  window height, plus window page Y offset, minus the height of the tipbox, minus the mouse-offset */ 
	data.display_limit_y = window.innerHeight + window.pageYOffset - data.active_height - data.option.mouse_offset_y ;
	
	/* we are now done setting stuff up, so make it appear! */
	jQ.css('z-index', 1010).css('visibility', 'visible');	
	
	/* set the location using the current location of the mouse. */ 
	jQ.offset(calculate_tooltip_new_location(e));
	
	set_tooltip_mousemove_timeout(true);

	return true;
}



/** mouse mover handler: added to document.onmousemove */
function tooltip_move_tracker(e)
{
	/* our global-object shortcut. */
	var data = cqpwebToolTipData;
	
	/* this handler does nothing if there is no tooltip active */ 
	if (null === data.active_id)
		return true;

	/* check the move time blocker. */
	if (data.wait_before_move)
		return true;
	
	/* OK, we're going to move; but first, activate the timer. */
	set_tooltip_mousemove_timeout(false);
	
	/* get & use a top / left coordinates object for the mouse location of this event */
	get_tip_jq_from_id(data.active_id).offset( calculate_tooltip_new_location(e) )

	return true;
}

function set_tooltip_mousemove_timeout(double_length)
{
	var data = cqpwebToolTipData;
	data.wait_before_move = true;
	window.setTimeout(
			function() { cqpwebToolTipData.wait_before_move = false; }, 
			(double_length ? 2 : 1) * data.option.delay_between_tip_moves
		);
}
	


/** 
 * Gets the new location for the tooltip, based on the mouse location on an event. 
 */
function calculate_tooltip_new_location(e)
{
	/* our global-object shortcut. */
	var data = cqpwebToolTipData;
	
	/* -------------- X -------------- */
	
	/* result is equal to current location, plus "mouse gap" offset. */
	var xx = e.pageX + data.option.mouse_offset_x;
	
	/* if the result is over the display limit, set to the display limit (minus "a bit") */
	if (xx > data.display_limit_x)
		xx = data.display_limit_x - 20;
	
	/* if the result is under the amount of scroll, set to the amount of scroll. */
	var amount_of_scroll = window.pageXOffset;
	if(xx < amount_of_scroll)
		xx = amount_of_scroll;

	/* -------------- Y -------------- */
		
	/* to begin with, Y = location of the mouse. */
	var yy = e.pageY;
	
	/* if this is above the limit, reduce it by the height of the floating tip (and a bit) */
	if (yy > data.display_limit_y)
		yy -= (data.active_height + 5);

	/* otherwise, add the mouse offset. */
	else
		yy += data.option.mouse_offset_y;

	/* ------------------------------- */
	
	return { top : yy , left : xx };
}





$(function() {
	
	do_tooltip_setup();
	
} );

	
	
	