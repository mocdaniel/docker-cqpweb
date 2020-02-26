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
 * @file This file contains the script that manages the "Quick username search" function on the "Manage Users" page.
 */
function do_username_chooser_insertion(uname, dest)
{
	$("#"+dest).val(uname);
	$(".userQuicksearchResults").detach().empty();
alert("setting " + dest + " to " +uname);
return false;
}


/**
 * We only create the function in global space if 1+ elements to anchor it on exist.
 */
$(function() {

	/* reusable data in the document-onReady function's closure to avoid reconstructing on every keyup */
	var user_qs_store = [] ;



//		/* stop dummy form from submitting, ever */
//		$(input[i]).parent().submit(function() { return false; });
		

	/* and create the function that monitors what the user types in the quicksearch box */
	$(".userQuicksearch")
		.keydown(function (e) { if (e.keyCode == 0x0d && !e.shiftKey)  e.preventDefault(); } )
		.keyup( 
			function(e) 
			{
console.log(e.keyCode);
//					e.preventDefault();
				/* don't submit form on enter. */
//				if (e.keyCode == 0x0d && !e.shiftKey)
//{
//					e.preventDefault();
//					$( e.target.form ).keyup( function (sub_e)  {alert("preventing def");  sub_e.preventDefault(); return false;} );
//}
				var ref;
				var seq = parseInt(e.target.id.split("_")[1]);

				if (undefined == user_qs_store[seq])
				{
					ref = { };
					
					ref.node        = $(e.target);
					ref.task        = ref.node.attr("data-effect");
					ref.tabix       = parseInt(ref.node.attr("tabindex"));
					
					ref.results     = $("#userQuicksearchResults_" + seq); 
					ref.anchor      = $("#userQuicksearchResultsAnchor_" + seq);
					ref.usernames   = $("#userQuicksearchData_" + seq).val().split("|");
					
					user_qs_store[seq] = ref;
				}
				else
					ref = user_qs_store[seq];
				
				var text = ref.node.val().toLowerCase();
				
//				var all_results = $(".userQuicksearchResults");
	
//				all_results.detach();
//				all_results.empty();
				if ( 2 <= text.length )
				{
//					results.detach(); 
					ref.results.empty();
					
					/* build max 20 links from username array */
					
					var n = 0;
					for (var j = 0 ; j < ref.usernames.length && n <= 20 ; j++)
					{
						if ( text == ref.usernames[j].substr(0, text.length).toLowerCase() )
						{
							switch (ref.task)
							{
							case 'link':
								ref.results.append(
										'<li><a tabindex="' 
										+ (ref.tabix+(++n))
										+ '" href="index.php?ui=userView&username=' 
										+ ref.usernames[j] 
										+ '">' 
										+ ref.usernames[j] 
										+ '</a></li>'
										);
								break;
							
							case 'insert':
								ref.results.append(
										'<li><a id="" tabindex="' 
										+ (ref.tabix+(++n)) 
										+ '" onclick="do_username_chooser_insertion(\'' 
										+ ref.usernames[j] 
										+ '\', \''
										+ e.target.id
										+ '\');">' 
										+ ref.usernames[j] 
										+ '</a></li>'
										);
								break;
							}
						}
					}
					
					/* fire the insert function if there is only one name and someone presses enter. */ 
					if ('insert' == ref.task && e.keyCode == 0x0d && !e.shiftKey )
					{
//						do_username_chooser_insertion(ref.usernames[0], e.target.id);
//console.log(ref.results[0]);	
						do_username_chooser_insertion(ref.results.find("li")[0].innerText, e.target.id);
						return;
					}

					/* set position of the ul and re-attach */
					ref.results.appendTo(ref.anchor);
					var coord = get_element_bottom_left_corner_coords(ref.node[0]);
					ref.results.css( 'left', 5 + coord[0] + ref.node.width() );
					var h = coord[1] - ( 3 * ref.results.height()/4);
					ref.results.css( 'top', (h > 0 ? h : 0)  );
					
				}
				else
					ref.results.detach(); /* removes from display if search string too short */
			}
		); 
	}
);


