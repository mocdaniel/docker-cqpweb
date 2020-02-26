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
 * Suggest a password in the admin interface for acct creation, taking a new string from the initialisation array. 
 * Pass in data to this func through a global var called cqpwebInsertPasswordData.
 * 
 * A note: would it work if we just set insert_password_suggestion.pwordfuncto a closure in document-ready, and then used this.pwordfunc here? 
 *         .... I think that's how js objects work ...
 */
function insert_password_suggestion()
{
	/* setup on first call */
	if (typeof insert_password_suggestion.passwords == 'undefined') 
	{
		insert_password_suggestion.passwords = ( cqpwebInsertPasswordData ? cqpwebInsertPasswordData : [] );
		insert_password_suggestion.index = 0;
	}
	if (insert_password_suggestion.passwords.length >= insert_password_suggestion.index)
		document.getElementById('newPassword').value = insert_password_suggestion.passwords[insert_password_suggestion.index++];
}




/**
 * Auto-create the dropdown for "remove from group"
 */
function setup_user_group_remove_dropdown(e)
{
	var select_jq = $(e.target);
	
	var members = select_jq.attr("data-member-usernames").split("|");
	/* only the username lists we are using get converted into DOM stuff. */ 
	
	/* create an option for each member, and append to the select. */
	for (let ix = 0; ix < members.length; ix++)
		select_jq.append("<option>" + members[ix] + "</option>");
	
	/* delete self. */
	select_jq.off("mousedown");
	
	return true;
}




$(document).ready(function() {
	
	/* add the setup function to "removeFromGroupDropDown" select elements */
	$("select.removeFromGroupDropDown").mousedown(setup_user_group_remove_dropdown);
	
} );

	
	
