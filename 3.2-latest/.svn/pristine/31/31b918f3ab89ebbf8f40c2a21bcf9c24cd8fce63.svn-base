<?php
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
 * @file
 * 
 * The exiterror module prints out an error page and performs necessary
 * error-actions before shutting down CQPweb.
 * 
 * Functions outside the module should always call one of the functions
 * that builds up a message template, e.g. exiterror(), which is the usual choice.
 * 
 * These functions in turn call the ones that do formatting etc.
 */
 

// TODO all funcs need to be sensible about text / JSON output. ie,. they should use $Config for that. 





/** Old fucniton name temproaryly preserved. 
 */
function print_debug_message($message)
{
	squawk($message);
}

/**
 * Prints a debug message. 
 * 
 * Messages are not printed if the config variable $print_debug_messages is not set to true.
 * 
 * If the browser is disconnected, then messages go to the error log.
 * 
 * Messages may be text only or HTML-wrapped (with a 'pre' tag). 
 * This depends config variable on $debug_messages_textonly.
 * 
 * @param string $message   The message to squawk out.
 */
function squawk($message)
{
	global $Config;
	
	static $order = 0;
	
	if (!$Config->print_debug_messages)
		return;
	
	$order++;
	
	if ($Config->client_is_disconnected || false)
	{
		$pid = getmypid();
		error_log("CQPweb (#$pid) squawks ($order): " . /*str_replace("'",  "\\'", $message)*/ $message, 4);
		/* 4 is the constant for "send this message to the standard webserver log"; 
		 * but note that in CLI, it still goes to the console. */
	}
	else
	{
		if ($Config->debug_messages_textonly)
			echo $message. "\n\n";
		else
			pre_echo($message);
	}
}





/**
 * Function internal to the exiterror module.
 * 
 * Writes the start of an error page, if and only if nothing has been sent back
 * via HTTP yet.
 * 
 * If the HTTP response headers have been sent, it does nothing.
 * 
 * Used by other exiterror functions (can be called unconditionally).
 *
 * @param string $page_title            Text for the HTML "title" element.
 * @param string $page_heading_message  Text to appear as the header for the table
 *                                      that this function starts.
 */
function exiterror_beginpage($page_title = 'CQPweb has encountered an error!', $page_heading_message = 'CQPweb encountered an error and could not continue.')
{
	global $Config;
	
	/*
	 * if the exiterror module is called BEFORE $Config exists, then we need to set up a dummy object
	 * with the members used in this module.
	 *
	 * NOTE that exiterror_beginpage always runs first - and this bit of the function executes
	 * even if the page has already started.
	 */
	if (!is_object($Config))
		$Config = (object) array ('debug_messages_textonly' => false, 'all_users_see_backtrace' => false);
	
	/* don't send headers, or write <head>, if we've already started writing. */
	if (headers_sent())
		return;

	if ($Config->debug_messages_textonly)
	{
		header("Content-Type: text/plain; charset=utf-8");
		echo "$page_heading_message\n";
		for ($i= 0, $n = strlen($page_heading_message); $i < $n ; $i++)
			echo '=';
		echo "\n\n";
	}
	else
	{
		echo print_html_header($page_title, isset($Config->css_path) ? $Config->css_path : ''), "\n";
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable"><?php echo escape_html($page_heading_message); ?></th>
			</tr>
		<?php
	}
}

/**
 * Function internal to the exiterror module.
 * 
 * Prints error message lines in either plaintext or HTML.
 * 
 * (The actual HTML that causes the formatting of the error page is here.)
 */
function exiterror_printlines($lines)
{
	global $Config;
	
	$lines = array_map('trim', $lines);
	
	$before = ($Config->debug_messages_textonly ? ''   : '<p class="errormessage">');
	$after  = ($Config->debug_messages_textonly ? "\n" : "</p>\n");

	if (!$Config->debug_messages_textonly)
		$lines = array_map('escape_html', $lines);

	echo $Config->debug_messages_textonly ? '' : '<tr><td class="concorderror">', "\n";
	foreach($lines as $l)
		if (! empty($l))
			echo $before , $l , $after;
	echo $Config->debug_messages_textonly ? '' : "</td></tr>\n";;
}

/**
 * Function internal to exiterror module.
 * 
 * Prints a debug backtrace if user is superuser.
 * 
 * Prints a footer iff we're in HTML context; then kills CQPweb.
 * 
 * If $backlink is true, a link to the home page for the corpus is included.
 */
function exiterror_endpage($backlink = false)
{
	global $Config;
	global $User;
	
	/* print the PHP back trace */
	if ( (isset($User) && $User->is_admin()) || $Config->all_users_see_backtrace)
	{
		$backtrace = debug_backtrace();
		unset($backtrace[0]); /* because we don't care about the call to *this* function */
		
		if ($Config->debug_messages_textonly)
		{
			echo "\n\nPHP debugging backtrace\n=======================\n";
			var_dump($backtrace); // TODO - is there a better wauy to priont this?
		}
		else
		{
			?>
			<tr>
				<th class="concordtable">PHP debugging backtrace</th>
			</tr>
			<tr>
				<td class="concorderror">
					<pre><?php var_dump($backtrace); ?></pre>
				</td>
			</tr>
			<?php
		}
	}

	/* print the backlink, if requested. */	
	if ( ! $Config->debug_messages_textonly)
	{
		if ($backlink)
		{
			?>
			<tr>
				<td class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<p class="errormessage">
						<a href="index.php">Back to main page.</a>
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<?php
		}
		?>
		
		</table>
		
		<?php
		echo print_html_footer('hello');
	}
}





/**
 * Primary function to be called by other modules.
 * 
 * Prints the specified error messages (with location of error if we're told that)
 * and then exits.
 * 
 * The error message is allowed to be an array of paragraphs.
 */
function exiterror($errormessage, $script=NULL, $line=NULL)
{
	global $Config;
	
	$msg = is_array($errormessage) ? $errormessage : [ $errormessage ] ;
	
	if (!empty($script))
		$msg[] = "... in file $script"
			. ( empty($line) ? '' : ", line $line")
			. '.'
			;

	/* report error via API return object */
	if (is_object($Config) && $Config->Api)
	{
		if (is_int($errormessage))
			$Config->Api->raise_known_error($errormessage);
		else
			$Config->Api->raise_described_error($errormessage);
	}
		
	/* report error to error log */
	else if(is_object($Config) && $Config->client_is_disconnected)
		foreach ($msg as $k=>$m)
			error_log("CQPweb aborts: (" . (1+$k) . ") $m", 4);
	
	/* print error to page */
	else
	{
		exiterror_beginpage();
		exiterror_printlines($msg);
		exiterror_endpage();
//TODO need to make sure the above 3 funcs behave themselves in commandline context. 
	}
	
	cqpweb_shutdown_environment();
	exit();
}
	
// TODO move this to exiterrro. Ideally, work out a way for the style of exit message (cmdline text, browser, log, json) to happen automatically. 
function exiterror_json($msg)
{
	// this should be merged to the AJAX object.
	// OR, merged inot exiterror() which will then test whether Ajax is in use.
	$o = new stdClass();
	$o->status = 'error';
	$o->message = $msg;
	if (!headers_sent())
{squawk("headers not sent");
		header("Content-Type: application/json");
}else squawk("headers were sent");
	echo json_encode($o);
	error_log('Error response sent as JSON: ' . print_r($o, true), 4);
	cqpweb_shutdown_environment();
	exit(0);
}
// TODO this isa mess. sort it out. Is it even needed?




/**
 * Variation on general error function specifically for failed login.
 * 
 * Unlike other exiterrors, it does not admit of script / line errors
 * (because this "error" is not a bug: it's a user error but not a
 * software error).
 */
function exiterror_login($errormessage) 
{
	global $Config;
	if (is_array($errormessage))
		$msg = $errormessage;
	else
		$msg = array($errormessage);
	
	if (is_object($Config) && $Config->Api)
		$Config->Api->raise_error(API_ERR_LOGIN_FAIL);
	else 
	{
		exiterror_beginpage("Unsuccessful login!", "Your login was not successful.");
		exiterror_printlines($msg);
		exiterror_endpage();
	}
	cqpweb_shutdown_environment();
	exit();
	
	// TODO this should be a proper separate page, not just an error. function do_login_error_page()
	// which could embed a "try again" form.
	// Or, just redirect ot he usr/ page with the login form, with the correct message?
}



function exiterror_toomanydbprocesses($process_type)
{
	global $Config;

	exiterror(array(
			"Too many database processes!",
			"There are already {$Config->mysql_process_limit[$process_type]} " 
				. "{$Config->mysql_process_name[$process_type]}	databases being compiled.",
			"Please use the Back-button of your browser and try again in a few moments."
		));

}



function exiterror_sqlquery($errornumber, $errormessage, $origquery=NULL, $script=NULL, $line=NULL)
{
	$msg = array("An SQL query did not run successfully!");
	
	if (!empty($origquery))
		$msg[] = "Original query: \n\n$origquery\n\n";
	
	$msg[] = "Error # $errornumber: $errormessage ";
	
	exiterror($msg, $script, $line);
}



/** Prints out CQP error messages in exiterror format. */
function exiterror_cqp($error_array)
{
	$msg = array_merge(["CQP reports an error! The CQP program sent back these error messages:"], $error_array);
	
// 	exiterror_beginpage('CQPweb -- CQP reports errors!');
// 	exiterror_printlines($msg);
// 	exiterror_endpage();
	exiterror($msg);
// 	cqpweb_shutdown_environment();
// 	exit(0);
}




