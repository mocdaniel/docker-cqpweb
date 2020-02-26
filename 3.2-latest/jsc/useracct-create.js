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

/* This file has the setup for the new-user-account form. */

// nb, not everythign has been moved in here yet., TODO move other code here if need be. 




/**
 * Used on the user signup page, this function retrieves and inserts into the DOM
 * a new captcha from the server. Useful for when the captcha is randomly too hard. 
 */
function refresh_captcha()
{
	var req = new XMLHttpRequest();
	
	req.open("GET", "../usr/useracct-act.php?userAction=ajaxNewCaptchaImage&cacheblock="
			+ Math.floor(Math.random()*99999999)
			, true);

	req.onreadystatechange = 
		function()
		{
			if (req.readyState != 4) 
				return;
			if (req.status != 200 && req.status != 304)
				return;
			if (req.responseText == null)
			{
				alert("Could not load new CAPTCHA image!");
			}
			else
			{
				which = req.responseText;

				/* stick the captcha ref into the form */
				document.getElementById('captchaRef').value = which;

				/* change the image */
				document.getElementById('captchaImg').src =
					"../usr/useracct-act.php?userAction=captchaImage&which=" +
					which + "&cacheblock=" + Math.floor(Math.random()*99999999) 
					;
			}
		}
	
	req.send();
}






$( function () {
	/* add caps lock warning if it seems necessary. */
	add_check_caps_lock_to_input("newPassword", ".capsLockHiddenPara");
	add_check_caps_lock_to_input("newPasswordCheck", ".capsLockHiddenPara");
} );

/* 
TODO add javascript strength meter for passwords. . (see user-acct-forms) 

strength link:
https://github.com/dropbox/zxcvbn/blob/master/README.md
(which in turn points at https://requirejs.org/docs/start.html to explain the bundling)


*/