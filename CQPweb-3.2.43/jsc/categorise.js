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





$(document).ready (function() {
		/* create global var, which tracks whether or not any changes have been made by the user. */
		categorise_modifications_made = 0;
		
		/* add a check function to the control row forms that cvhange the view. */
		var forms = $(".unsaved_view_change");
		forms.submit( 
				function() {
					if (0 == categorise_modifications_made)
						return true;
					//TODO use our own "modal" display instead.
					return window.confirm("You have unsaved categorisation changes. Leaving the page will discard them. Continue?");	
				}
			);
		
		/* now, add a check function to other go-elsewhere links: the nodelinks, textmeta links, and the nav links */ 
		var links = $(".page_nav_links, .nodelink, td.text_id a");
		links.click( 
				function () {
					if (0 == categorise_modifications_made)
						return true;
					//TODO use our own "modal" display instead.
					return window.confirm("You have unsaved categorisation changes. Leaving the page will discard them. Continue?");	
				} 
			);
		
		/* and last: add a function to each dropdown: increment categorise_modifications_made. */
		var cat_selectors = $(".category_chooser");
		cat_selectors.change( function() { categorise_modifications_made++; } );
	}
);
