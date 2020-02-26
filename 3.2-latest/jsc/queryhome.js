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
 * This file contains the setup and functions for the queryhome interface.
 */



// TODO this is bad UI.
function analysis_switch_tool()
{
	var new_tool = $("#analysisToolChoice").val();

	var callback = function()
	{
		/* get the new analysis tool */
		current_analysis_tool = $("#" + new_tool);

		/* and make it appear */
		current_analysis_tool.slideDown("slow");
	};

	/* if an analysis tool is visible, hide it. Then callback the reveal of the new tool. */
	if (current_analysis_tool)
	{
		if (new_tool != current_analysis_tool.attr('id'))
			current_analysis_tool.fadeOut("slow", callback);
	}
	else
		callback();
}







/** keypress handler for the searchbox-textarea. */
function check_for_enter_in_search_box(e)
{
	/* will this work cross-OS? (seems to) */
	if (e.keyCode == 0x0d && !e.shiftKey)
	{
		$(e.target.form).submit();
		return false;
	}
	return true;
}



/**
 * Switches ona throbber with a "query is running" message.
 */
function apply_run_query_throbber()
{
	greyout_and_throbber("Query is running &ndash; please wait!");
	return true;
}



/*
 * The setup function.
 */
$(function () {

	//TODO maybe: switch on .data-what-ui (on the application meta object node??????  rather than these ad hoc tests.

	/* 
	 * Setup the corpus analysis interface, if we have that form. 
	 */
	if ($("#analysisToolChoiceGo").length > 0)
	{
		window.current_analysis_tool = null;
		$("#analysisToolChoiceGo").click(analysis_switch_tool);

		/* now hide all the forms */
		$("#featureMatrixList").hide();
		$("#featureMatrixDesign").hide();

		/* greyouts on form submit... */
		$("#featureMatrixDesign").submit(function () { greyout_and_throbber(); return true; }); // Todo just use class greyoutOnSubmit
	}


	/* 
	 * Set up the behaviour of query-entry boxes. 
	 */
	if ($(".runQueryForm").length > 0)
	{
		/* add "do query on enter" functionality.*/
		$("#searchBox").keypress(check_for_enter_in_search_box);

		/* greyouts on form submit... */
		$(".runQueryForm").submit(function () { apply_run_query_throbber(); return true; });
	}


	/* 
	 * set up the special "check submission" actions for the keywords form. 
	 */
	if ($("#kwForm").length > 0)
	{
		$("#kwSubmitWithKey").click( function ()
								{
									$("#kwMethodInput").val("key");
									$("#kwForm").submit();
								}
		);
		$("#kwSubmitWithComp").click( function ()
								{
									$("#kwMethodInput").val("comp");
									$("#kwForm").submit();
								}
		);
		$("#kwForm").submit(
				function (event)
				{
					var val_1 = $("#kwTable1").val();
					var msg = false; 

					if ("::null" == val_1)
						msg = "Please select a frequency list to compare!";
					else if ("::remainder" == $("#kwTable2").val() &&  !val_1.match(/^..s~/))
						msg = "You can't use the &ldquo;rest of the corpus&rdquo; for frequency list 2 unless you have chosen a subcorpus as frequency list 1!";
	
					if (msg)
					{
						event.preventDefault();
						event.stopImmediatePropagation();
						greyout_and_acknowledge(msg);
						return false;
					}
	
					return true;
				}
		);
	}

});
