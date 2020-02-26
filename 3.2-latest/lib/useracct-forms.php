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
 * Each of these functions prints a table for the right-hand side interface.
 * 
 * This file contains the forms deployed by userhome and not queryhome. 
 * 
 */


function do_usr_ui_accessdenied()
{
	global $User;
	
	$access_block_go = false;
	
	$main_message = 'You do not have access to this corpus.' ;
	$reason_why_message = 'It is not clear why you cannot access this data; there may be a setup glitch.';
	
	if (isset($_GET['corpusDenied']))
	{
		if ($c_info = get_corpus_info($_GET['corpusDenied']))
		{
			if (empty($c_info->title))
				$c_info->title = $c_info->name;
			
			$main_message = "You do not have access to the corpus <strong>" .  escape_html($c_info->title) . '</strong>.' ;
			
			if (isset($_GET['why']))
			{
				switch((int)$_GET['why'])
				{
				case CQPwebEnvCorpus::NOACCESS_SYSTEMCORPUS_NO_PRIV:
					$reason_why_message = "Your user account doesn't have the necessary permissions to use this corpus.";
					if (!empty($c_info->access_statement))
					{
						$reason_why_message .= " The access information below may explain why.";
						$access_block_go = true;
					}
					break;
				case CQPwebEnvCorpus::NOACCESS_SYSTEMCORPUS_LOGGED_OUT:
					/* note, this doesn't activate yet, but is dealt with below instead. It anticipates "open" servers. */
					$reason_why_message = "You are not logged in to CQPweb. You need to be logged in to access this corpus. ";
					break;
				case CQPwebEnvCorpus::NOACCESS_USERCORPUS_SWITCHED_OFF:
					$reason_why_message = "The user-corpus system is switched off; all user-owned corpora are offline.";
					break;
				case CQPwebEnvCorpus::NOACCESS_USERCORPUS_NOT_OWNED:
					$reason_why_message = "This corpus belongs to another user.";
					break;
				case CQPwebEnvCorpus::NOACCESS_USERCORPUS_NO_GRANT:
					$reason_why_message = "The user who owns this corpus has not shared it with you.";
					break;
				case CQPwebEnvCorpus::NOACCESS_USERCORPUS_DISKBLOCKED:
					$main_message = "You have used up your available disk space for installing corpora. "; 
					$reason_why_message = "You cannot access <strong>any</strong> of your installed corpora until you have <a href=\"../usr/index.php?ui=showCorpora\">deleted some of the excess data</a>.";
					break;
				}
			}
		}
		else 
		{
			$main_message = "The corpus wasn't found on the system. ";
			$reason_why_message = "<a href=\"../\">Go back to the main page and start again</a>.";
		}
	}
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Access denied!
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>

				<?php
				
				if ($User->logged_in)
				{
					?>
					<p>
						<?php echo $main_message, "\n"; ?>
					</p>
					<p class="spacer">&nbsp;</p>
					<p>
						<?php echo $reason_why_message, "\n"; ?>
					</p>
					<?php
				}
				else
				{
					?>
					<p>
						You cannot access that corpus because you are not logged in.
					</p>
					<p>
						Please <a href="../usr/index.php?ui=login">log in to CQPweb</a> and then try again!
					</p>
					<?php
				}
				
				?>

				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>


	<?php
	
	if ($access_block_go)
		do_bitui_access_statement($c_info->access_statement);
}


function do_usr_ui_welcome()
{
	global $User;
	
	if (empty($User->realname) || $User->realname == 'unknown person')
		$personalise = '';
	else
		$personalise = ', ' . escape_html($User->realname);
	
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				You are logged on to CQPweb
			</th>
		</tr>
		
		<?php
		if (!empty($_GET['extraMsg']))
			echo '<tr><td class="concordgeneral">&nbsp;<br>', escape_html($_GET['extraMsg']), "<br>&nbsp;</td></tr>\n";
		?>
		
		<tr>
			<td class="concordgeneral">
				&nbsp;<br>
			
				Welcome back to the CQPweb server<?php echo $personalise; ?>. You are logged in to the system.

				<br>&nbsp;<br>

				This is your user page; select an option from the menu on the left, or
				<a href="../">click here to return to the main homepage</a>.

				<br>&nbsp;
			</td>
		</tr>
	</table>
	
	<?php

}

function do_usr_ui_login()
{
	global $Config;
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Log in to CQPweb
			</th>
		</tr>

		<?php
		if (!empty($_GET['extraMsg']))
			echo '<tr><td class="concordgeneral">&nbsp;<br>', escape_html($_GET['extraMsg']), "<br>&nbsp;</td></tr>\n";
		?>

		<tr>
			<td class="concordgeneral">
				
				<?php
				
				echo print_login_form( isset($_GET['locationAfter']) ? $_GET['locationAfter'] : false );
				
				?>
			
				<p>To log in to CQPweb, you must have cookies turned on in your browser.</p> 
			
				<ul>
					
					<?php
					if ($Config->allow_account_self_registration)
					{
						?>
						
						<li>
							<p>
								If you do not already have an account, you can 
								<a href="index.php?ui=create">create one</a>.
							</p>
						</li>
					
						<?php
					}
					?>
					
					<li>
						<p>
							If you have forgotten your password, you can 
							<a href="index.php?ui=lostPassword">request a reset</a>.
						</p>
					</li>
				</ul>
			</td>
		</tr>
	</table>
	
	<?php
}


function do_usr_ui_logout()
{
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Log out of CQPweb?
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				<p>Are you sure you want to log out of the system?</p>
				
				<table class="basicbox" style="margin:auto">
					<tr>
						<td class="basicbox">
							<form action="useracct-act.php" method="get">
								<input type="hidden" name="userAction" value="userLogout">
								<input type="submit" value="Click here to log out and return to the main menu">
							</form>
						</td>
					</tr>
				</table>

				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
	<?php
}


function do_usr_ui_create()
{
	global $Config;
	
	/**
	 * If we are returning from a failed CAPTCHA, we should put several of the values into the slots.
	 */
	if (isset($_GET['captchaFail']))
	{
		$prepop = new stdClass();
		foreach (array('newUsername', 'newEmail', 'realName', 'affiliation', 'country') as $x)
			$prepop->$x = isset($_GET[$x]) ? escape_html($_GET[$x]) : '';
	}
	else
		$prepop = false;
	

	if (!$Config->allow_account_self_registration)
	{
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Account self-registration not available
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2">
					&nbsp;<br>
					Sorry but self-registration has been disabled on this CQPweb server. 
					<?php
					if (! empty($Config->account_create_contact))
						echo "<br>&nbsp;<br>To request an account, contact {$Config->account_create_contact}."; 
					?>
					<br>&nbsp;
				</td>
			</tr>
		</table>
		
		<?php	
		return;
	}
	
	/* initialise the iso 3166-1 array... */
	require('../lib/user-iso31661.php');

	?>

	<form action="useracct-act.php" method="post">
		<input type="hidden" name="userAction" value="newUser">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">
					Register for an account on this CQPweb server
				</th>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2">
					&nbsp;<br>
					<strong>First</strong>, select a username and password. Your username can be up to 30 letters long, and must consist of only
					unaccented letters, digits and the underscore (&nbsp;_&nbsp;).
					<br>&nbsp;<br>
					Your password or passphrase can consist of any characters you like including punctuation marks and spaces. 
					The length limit is 255 characters.
					<br>&nbsp;
				</td>
			</tr>
			<?php
			if ($prepop)
			{
				?>
				<tr>
					<td class="concorderror" colspan="2">
						&nbsp;<br>
						You failed the human-being test; please try again.
						<br>&nbsp;<br>
						Note: you will need to re-enter your chosen password.
						<br>&nbsp;
					</td>
				</tr>
				<?php
			}
			?>
			<tr>
				<td class="concordgeneral" WIDTH="40%">
					Enter your chosen username:
				</td>
				<td class="concordgeneral">
					<p style="display:inline-block"><input type="text" placeholder="Choose a username (letters/numbers/underscore only)" size="50" maxlength="30" name="newUsername" 
					<?php
					if ($prepop)
						echo " value=\"{$prepop->newUsername}\" ";
					?>
					></p>
				</td>
			</tr>
			<tr>


				<td class="concordgeneral">
					Enter your password or passphrase:
				</td>
				<td class="concordgeneral">
					<p style="display:inline-block"><input id="newPassword" type="password" placeholder="Enter your chosen password here" size="50" maxlength="255" name="newPassword"></p>
					<p class="capsLockHiddenPara" style="display:none"><strong>WARNING</strong>: Caps Lock seems to be on!</p>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Retype the password or passphrase:
				</td>
				<td class="concordgeneral">
					<p style="display:inline-block"><input id="newPasswordCheck" type="password" placeholder="Please re-type your password!" size="50" maxlength="255" name="newPasswordCheck"></p>
					<p class="capsLockHiddenPara" style="display:none"><strong>WARNING</strong>: Caps Lock seems to be on!</p>
				</td>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2">
					<p class="spacer">&nbsp;</p>
					<p>
						<strong>Now</strong>, enter your email address. We will send a verification message to this email address, 
						so it is critical that you <strong>double-check for typing errors</strong>.
					</p>
					<p class="spacer">&nbsp;</p>
					<p>
						<em>Your account will not be activated until you click on the link that we send in that email message!</em>
					<p class="spacer">&nbsp;</p>
					<p>
						<strong>If you have an institutional email address</strong> (linked to a company or university, for instance), 
						<strong>you should use it to sign up</strong>.
					</p>
					<p class="spacer">&nbsp;</p>
					<p>
						This is because your access to some corpora may depend on what
						institution you are affiliated to &ndash; and we use your email address to detect your affiliation.
						If you specify a Gmail, Hotmail or other freely-obtainable email address, we won't be able to detect
						your affiliation, and you may not have access to all the corpora that you should have access to.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter your email address:
					<br>
					<em>Note that this cannot be changed later!</em>
				</td>
				<td class="concordgeneral">
					<input type="email" placeholder="your.address@somewhere.net" size="50" maxlength="255" name="newEmail"
					<?php
					if ($prepop)
						echo " value=\"{$prepop->newEmail}\" ";
					?>
					>
				</td>
			</tr>

			<?php
			if ($Config->account_create_captcha)
			{
				$captcha_code = create_new_captcha();
				$params = "userAction=captchaImage&which=$captcha_code&cacheblock=" . uniqid();
				?>
				
				<tr>
					<td class="concordgeneral">
						Type in the 6 characters from the picture to prove you are a human being:
						<br>
						<em>N.B.: all letters are lowercase.</em>
					</td>
					<td class="concordgeneral">
						<script defer src="../jsc/captcha.js"></script>
						<img id="captchaImg" src="useracct-act.php?<?php echo $params; ?>">
						<br>
						<a onClick="refresh_captcha();" class="menuItem">[Too hard? Click for another]</a>
						<br>
						<input type="text" size="30" maxlength="10" name="captchaResponse">
						<input id="captchaRef" type="hidden" name="captchaRef" value="<?php echo $captcha_code; ?>">
					</td>
				</tr>
				
				<?php
			}
			?>

			<tr>
				<td class="concordgrey" colspan="2">
					<p class="spacer">&nbsp;</p>
					<p>
						The following three questions are optional. You can leave these parts of the form empty if you wish. 
						However, it is highly useful to us to know a bit more about who is using our CQPweb installation,
						so we will be very grateful if you supply this information.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Please enter your real name:
				</td>
				<td class="concordgeneral">
					<input type="text" size="50" maxlength="255" name="realName" 
					<?php
					if ($prepop)
						echo " value=\"{$prepop->realName}\" ";
					?>
					>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Please enter your affiliation:
					<br>
					<em>(a company, university or other body that you are associated with)</em>
				</td>
				<td class="concordgeneral">
					<input type="text" size="50" maxlength="255" name="affiliation" 
					<?php
					if ($prepop)
						echo " value=\"{$prepop->affiliation}\" ";
					?>
					>
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Please enter your location (select a country or territory):
				</td>
				<td class="concordgeneral">
					<select name="country">
						<option selected value="00">Prefer not to specify</option>
						<?php
						$use_select = ( (! $prepop) || '00' == $prepop->country ? ' selected' : '');
						echo '<option value="00"', $use_select, '>Prefer not to specify</option>', "\n";

						unset($Config->iso31661['00']);

						foreach($Config->iso31661 as $code => $country)
						{
							$use_select = ($prepop && $code == $prepop->country ? ' selected' : '');
							echo "\t\t\t\t\t\t<option value=\"$code\"$use_select>$country</option>\n";
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2" align="center">
					&nbsp;<br>
					When you are happy with the settings you have entered, use the button below to register.
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					&nbsp;<br>
					<input type="submit" value="Register account">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>
	<?php
}




function do_usr_ui_verify()
{
	$screentype = (isset($_GET['verifyScreenType']) ? $_GET['verifyScreenType'] : 'newform');
	
	echo "\n\n";
	
	if ($screentype == 'newform' || $screentype == 'badlink')
	{
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Enter activation key
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<?php
					if ($screentype=='badlink')
						echo "\t\t\t\t\t<p>CQPweb could not read a verification key from the link you clicked.</p>\n"
							,"\t\t\t\t\t<p>Enter your 32-letter key code manually instead?</p>\n"
							;
					else
						echo "\t\t\t\t\t<p>You should have received an email with a 32-letter code.</p>\n"
							,"\t\t\t\t\t<p>Enter this code into the form below to activate the account.</p>\n"
							;
					?>

					<form action="useracct-act.php" method="get">
						<input type="hidden" name="userAction" value="verifyUser">
						<table class="basicbox" style="margin:auto">
							<tr>
								<td class="basicbox">
									Enter code here:
								</td>
								<td class="basicbox">
									<input type="text" name="v" size="32" maxlength="32">
								</td>
							</tr>

							<tr>
								<td class="basicbox" colspan="2" align="center">
									<input type="submit" value="Click here to verify account"> 
								</td>
							</tr>
						</table>
					</form>
					<p>
						If you have not received an email with an activation code,
						<a href="index.php?ui=resend">click here</a>
						to ask for one to be sent to your account's designated email address.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
		<?php	
	}
	else if ($screentype == 'success')
	{
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					New account verification has succeeded!
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<p align="center">
						Your new user account has been successfully activated. 
					</p>
					<p align="center">
						Welcome to our CQPweb server!
					</p>
					<p align="center">
						<a href="index.php">Click here to log in.</a>
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
		<?php
	}
	else if ($screentype == 'failure')
	{
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					Account verification failed!
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<p>
						Your account could not be verified. The activation key you supplied could not be found in our database. 
					</p>
					<p>
						We recommend you request <a href="index.php?ui=resend">a new activation email</a>.
					</p>
					<p>
						If a new email does not solve the problem, we suggest 
						<a href="index.php?ui=create">restarting the account-creation process from scratch</a>.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
		<?php
	}
	else if ($screentype == 'newEmailSent')
	{
		?>
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					A new verification email has been sent!
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					<p class="spacer">&nbsp;</p>
					<p>
						Please access your email account: a message with a new activation link should arrive soon. 
					</p>
					<p>
						Note that activation links from earlier emails will <em>no longer work</em>.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
		<?php
	}
}


function do_usr_ui_resend()
{
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Re-send account activation email
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p class="spacer">&nbsp;</p>
				<p>
					If you have created an account on CQPweb but have not received an email to activate it,
					you can use this control to request another activation email.
				</p>

				<p class="spacer">&nbsp;</p>
				<p>
					All accounts must be verified by the owner of the associated email address via clicking
					on the activation link in the email message.
				</p>

				<form action="useracct-act.php" method="GET">
					<input type="hidden" name="userAction" value="resendVerifyEmail">
					<table class="basicbox" style="margin:auto">
						<tr>
							<td class="basicbox">Enter your email address:</td>
							<td class="basicbox">
								<input type="email" placeholder="your.address@somewhere.net" name="email" width="50">
							</td>
						</tr>
						<tr>
							<td class="basicbox" colspan="2">
								<input type="submit" value="Request a new activation email">
							</td>
						</tr>
					</table>
				</form>

				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>

	<?php
}




function do_usr_ui_lostusername()
{
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Retrieve lost or forgotten username
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<form action="useracct-act.php" method="GET">
					<input type="hidden" name="userAction" value="remindUsername">
					<p>If you have lost or forgotten your username, you can request an email reminder.</p>
					<p>Enter the email address you used to sign up in the text box below and press &rdquo;Request username reminder email&ldquo;.</p>
					<p>A message will be sent to your email with a reminder of your username.</p>
					<p align="center">
						<input type="email" placeholder="your.address@somewhere.net" name="emailToRemind" size="30" maxlength="255">
					</p>
					<p align="center">
						<input type="submit" value="Request username reminder email">
					</p>
					<p class="spacer">&nbsp;</p>
				</form>
			</td>
		</tr>
	</table>
	<?php
}


function do_usr_ui_lostpassword()
{
	?>

	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">
				Reset lost password
			</th>
		</tr>
		<?php
		
		if (isset($_GET['showSentMessage']) && $_GET['showSentMessage'])
		{
			?>
			
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					&nbsp;<br>
					<strong>
						An email has been sent to the address associated with your account. Please check your inbox!
					</strong>
					<br>&nbsp;
				</td>
			</tr>
			
			<?php
		}
		?>
		
		<tr>
			<td class="concordgrey" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p>
					If you have forgotten your password, or if your password has expired, 
					you can request a password-reset.
					<i>CQPweb does not store your password and so we cannot send you a reminder
					of what your password is (because doing so would risk the security of your account).</i>
					You must instead reset the password to something new.
				</p>
				<p class="spacer">&nbsp;</p>
				<p>
					First, use the <strong>first</strong> form below to request a password-reset verification code.
					This will be sent to the email address associated with your username.
				</p>
				<p class="spacer">&nbsp;</p>
				<p>
					Then, return to this webpage, and use the <strong>second</strong> form below to change your password, 
					using the verification code that we send you via email message.
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>
	
	<form action="useracct-act.php" method="post">
		<input type="hidden" name="userAction" value="requestPasswordReset">
		<table class="concordtable fullwidth">

			<tr>
				<th class="concordtable" colspan="2">
					Request password reset via email
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter your username:
				</td>
				<td class="concordgeneral">
					<input type="text" size="40" maxlength="<?php echo HANDLE_MAX_USERNAME; ?>" name="userForPasswordReset">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					<p class="spacer">&nbsp;</p>
					<p><input type="submit" value="Click here to request a password reset verification code via email"></p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
	</form>
	
	<form action="useracct-act.php" method="post">
		<input type="hidden" name="userAction" value="resetUserPassword">

		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">
					Reset your password
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter your username:
				</td>
				<td class="concordgeneral">
					<input type="text" size="40" maxlength="30" name="userForPasswordReset">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter your <strong>new</strong> password or passphrase:
				</td>
				<td class="concordgeneral">
					<input type="password" size="40" maxlength="255" name="newPassword">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Retype the <strong>new</strong> password or passphrase:
				</td>
				<td class="concordgeneral">
					<input type="password" size="40" maxlength="255" name="newPasswordCheck">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter the 32-letter verification code sent to you by email:
					<br>
					<em>(spaces optional)</em>
				</td>
				<td class="concordgeneral">
					<input type="text" size="40" maxlength="40" name="v">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					&nbsp;<br>
					<input type="submit" value="Click here to reset password">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>
	<?php
}





function do_usr_ui_usersettings()
{
	global $User;
	
	list ($optionsfrom, $optionsto) = print_fromto_form_options(10, $User->coll_from, $User->coll_to);
	
	?>

<form action="useracct-act.php" method="get">
	<input type="hidden" name="userAction" value="revisedUserSettings">
	
	<table class="concordtable fullwidth">
	
		<tr>
			<th colspan="2" class="concordtable">User interface settings</th>
		</tr>
		<tr>
			<td colspan="2" class="concordgrey" align="center">
				<p class="spacer">&nbsp;</p>
				<p>Use this form to personalise your options for the user interface.</p> 
				<p>Important note: these settings apply to all the corpora that you access on CQPweb.</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<th colspan="2" class="concordtable">Display options</th>
		</tr>
		<tr>
			<td class="concordgeneral">Default view</td>
			<td class="concordgeneral">
				<select name="newSetting_conc_kwicview">
					<option value="1"<?php echo ($User->conc_kwicview ? ' selected' : '');?>>KWIC view</option>
					<option value="0"<?php echo ($User->conc_kwicview ? '' : ' selected');?>>Sentence view</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Default display order of concordances</td>
			<td class="concordgeneral">
				<select name="newSetting_conc_corpus_order">
					<option value="1"<?php echo ($User->conc_corpus_order ? ' selected' : '');?>>Corpus order</option>
					<option value="0"<?php echo ($User->conc_corpus_order ? '' : ' selected');?>>Random order</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Show Simple Query translated into CQP syntax (in title bar and query history)
			</td>
			<td class="concordgeneral">
				<select name="newSetting_cqp_syntax">
					<option value="1"<?php echo ($User->cqp_syntax ? ' selected' : '');?>>Yes</option>
					<option value="0"<?php echo ($User->cqp_syntax ? '' : ' selected');?>>No</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Context display</td>
			<td class="concordgeneral">
				<select name="newSetting_context_with_tags">
					<option value="0"<?php echo ($User->context_with_tags ? '' : ' selected');?>>Without tags</option>
					<option value="1"<?php echo ($User->context_with_tags ? ' selected' : '');?>>With tags</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Show tooltips
				<br>
				<em>
					(When moving the mouse over some links (e.g. in a concordance), additional 
					information will be displayed in tooltip boxes.)
				</em>
			</td>
			<td class="concordgeneral">
				<select name="newSetting_use_tooltips">
					<option value="1"<?php echo ($User->use_tooltips ? ' selected' : '');?>>Yes</option>
					<option value="0"<?php echo ($User->use_tooltips ? '' : ' selected');?>>No</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Default setting for thinning queries</td>
			<td class="concordgeneral">
				<select name="newSetting_thin_default_reproducible">
					<option value="0"<?php echo ($User->thin_default_reproducible ? '' : ' selected');?>>Random: selection is not reproducible</option>
					<option value="1"<?php echo ($User->thin_default_reproducible ? ' selected' : '');?>>Random: selection is reproducible</option>
				</select>
			</td>
		</tr>
		<tr>
			<th colspan="2" class="concordtable">Collocation options</th>
		</tr>
		<tr>
			<td class="concordgeneral">Default statistic to use when calculating collocations</td>
			<td class="concordgeneral">
				<select name="newSetting_coll_statistic">
					<?php echo print_statistic_form_options($User->coll_statistic); ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Default minimum for freq(node, collocate) [<em>frequency of co-occurrence</em>]
			</td>
			<td class="concordgeneral">
				<select name="newSetting_coll_freqtogether">
					<?php echo print_freqtogether_form_options($User->coll_freqtogether); ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Default minimum for freq(collocate) [<em>overall frequency of collocate</em>]
				</td>
			<td class="concordgeneral">
				<select name="newSetting_coll_freqalone">
					<?php echo print_freqalone_form_options($User->coll_freqalone); ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Default range for calculating collocations
			</td>
			<td class="concordgeneral">
				From
				<select name="newSetting_coll_from">
					<?php echo $optionsfrom; ?>
				</select>
				to
				<select name="newSetting_coll_to">
					<?php echo $optionsto; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th colspan="2" class="concordtable">Download options</th>
		</tr>
		<tr>
			<td class="concordgeneral">File format to use in text-only downloads</td>
			<td class="concordgeneral">
				<select name="newSetting_linefeed">
					<option value="au"<?php echo ($User->linefeed == 'au' ? ' selected' : '');?>>Automatically detect my computer</option>
					<option value="da"<?php echo ($User->linefeed == 'da' ? ' selected' : '');?>>Windows</option>
					<option value="a"<?php  echo ($User->linefeed == 'a'  ? ' selected' : '');?>>Unix / Linux / Mac OS X</option>
					<option value="d"<?php  echo ($User->linefeed == 'd'  ? ' selected' : '');?>>Mac OS 9 and below</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">File layout to use for frequency-list downloads</td>
			<td class="concordgeneral">
				<select name="newSetting_freqlist_altstyle">
					<option value="0"<?php echo ($User->freqlist_altstyle ? '' : ' selected');?>>CQPweb default format</option>
					<option value="1"<?php echo ($User->freqlist_altstyle ? ' selected' : '');?>>Alternative format (AntConc-compatible)</option>
				</select>
			</td>
		</tr>
		<tr>
			<th colspan="2" class="concordtable">Accessibility options</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Override corpus colour scheme with monochrome
				<br>
				<em>(useful if the colour schemes cause you vision difficulties)</em>
			</td>
			<td class="concordgeneral">
				<select name="newSetting_css_monochrome">
					<option value="1"<?php echo ($User->css_monochrome ? ' selected' : '');?>>Yes</option>
					<option value="0"<?php echo ($User->css_monochrome ? '': ' selected');?>>No</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgrey" align="right">
				<input type="submit" value="Update settings">
			</td>
			<td class="concordgrey" align="left">
				<input type="reset" value="Clear changes">
			</td>
		</tr>
	</table>
</form>

	<?php

}

function do_usr_ui_usermacros()
{
	global $User;
	
	?>
	
<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable" colspan="3">User's CQP macros</th>
	</tr>
	
	<?php
	
	$result = do_sql_query("select * from user_macros where user='{$User->username}'");
	if (1 > mysqli_num_rows($result))
	{
		?>
		
		<tr>
			<td colspan="3" align="center" class="concordgrey">
				&nbsp;<br>
				You have not created any user macros.
				<br>&nbsp;
			</td>
		</tr>
		
		<?php
	}
	else
	{
		?>

		<tr>
			<th class="concordtable">Macro</th>
			<th class="concordtable">Macro expansion</th>
			<th class="concordtable">Actions</th>
		</tr>
		
		<?php
		while ($o = mysqli_fetch_object($result))
			echo "\t\t<tr>"
				, "<td class=\"concordgeneral\">{$o->macro_name}({$o->macro_num_args})</td>"
				, '<td class="concordgrey"><code>', escape_html($o->macro_body), '</code></td>'
		 		, '<td class="concordgeneral" align="center">'
					, '<form action="useracct-act.php" method="get">'
						, '<input type="hidden" name="userAction" value="deleteUserMacro">'
						, '<input type="hidden" name="macroId" value="', $o->id, '">'
						, '<input type="submit" value="Delete macro">'
					, '</form>'
 				, '</td>'
				, "</tr>\n"
 				;
	}
	?>
	
</table>

<form action="useracct-act.php" method="get">
	<input type="hidden" name="userAction" value="newUserMacro">
	<input type="hidden" name="macroUsername" value="<?php echo $User->username;?>">
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="2" class="concordtable">Create a new CQP macro</th>
		</tr>
		<tr>
			<td class="concordgeneral">Enter a name for the macro:</td>
			<td class="concordgeneral">
				<input type="text" maxlength="20" name="macroNewName" onKeyUp="check_c_word(this)">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">Enter the body of the macro:</td>
			<td class="concordgeneral">
				<textarea rows="25" cols="80" name="macroNewBody"></textarea>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">Click here to save your macro<br>(It will be available in all CQP queries)</td>
			<td class="concordgrey">
				<input type="submit" value="Create macro">
			</td>
		</tr>
	</table>
</form>

	<?php

}


function do_usr_ui_corpusaccess()
{
	global $User;
	
	$header_text_mapper = array(
		PRIVILEGE_TYPE_CORPUS_FULL       => "You have <em>full</em> access to:",
		PRIVILEGE_TYPE_CORPUS_NORMAL     => "You have <em>normal</em> access to:",
		PRIVILEGE_TYPE_CORPUS_RESTRICTED => "You have <em>restricted</em> access to:"
		);
	
	/* now, compile an array of corpora to create table cells for */
	$accessible_corpora = array(
		PRIVILEGE_TYPE_CORPUS_FULL       => array(),
		PRIVILEGE_TYPE_CORPUS_NORMAL     => array(),
		PRIVILEGE_TYPE_CORPUS_RESTRICTED => array()
		);
	foreach ($User->privileges as $p)
	{
		switch($p->type)
		{
		case PRIVILEGE_TYPE_CORPUS_FULL:
		case PRIVILEGE_TYPE_CORPUS_NORMAL:
		case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
			foreach ($p->scope_object as $c)
				if ( ! in_array($c, $accessible_corpora[$p->type]) )
					$accessible_corpora[$p->type][] = $c;
			break;
		default:
			break;
		}
	}
	/* remove from normal if in full */
	foreach($accessible_corpora[PRIVILEGE_TYPE_CORPUS_NORMAL] as $k=>$c)
		if (in_array($c, $accessible_corpora[PRIVILEGE_TYPE_CORPUS_FULL]))
			unset($accessible_corpora[PRIVILEGE_TYPE_CORPUS_NORMAL][$k]);
	/* remove from restricted if in full or normal */
	foreach($accessible_corpora[PRIVILEGE_TYPE_CORPUS_RESTRICTED] as $k=>$c)
		if (in_array($c, $accessible_corpora[PRIVILEGE_TYPE_CORPUS_FULL]) || in_array($c, $accessible_corpora[PRIVILEGE_TYPE_CORPUS_NORMAL]))
			unset($accessible_corpora[PRIVILEGE_TYPE_CORPUS_RESTRICTED][$k]);

	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="3" class="concordtable">Corpus access permissions</th>
		</tr>
		<tr>
			<td colspan="3" class="concordgrey" align="center">
				<p class="spacer">&nbsp;</p>
				<p>You have permission to access the following corpora:</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		
		<?php
		
		/* in case of superuser, shortcut everything and return */
		if ($User->is_admin())
		{
			echo "\t\t<tr><td colspan=\"3\" class=\"concordgeneral\" align=\"center\">"
				, "&nbsp;<br><strong>You are a superuser. You have full access to everything.</strong><br>&nbsp;"
				, "</td></tr>\n\t</table>\n"
				;
			return;
		}
		
		foreach(array(PRIVILEGE_TYPE_CORPUS_FULL, PRIVILEGE_TYPE_CORPUS_NORMAL, PRIVILEGE_TYPE_CORPUS_RESTRICTED) as $t)
		{
			if ( empty($accessible_corpora[$t] ))
				continue;
			
			?>
			
			<tr>
				<th colspan="3" class="concordtable"><?php echo $header_text_mapper[$t]; ?></th>
			</tr>
			
			<?php
			
			/* the following hunk o' code is a variant on what is found in mainhome */
			
			$i = 0;
			$celltype = 'concordgeneral';
			
			foreach($accessible_corpora[$t] as $c)
			{
				if ($i == 0)
					echo "\t\t<tr>";
				
				/* get corpus title */
				$c_info = get_corpus_info($c);
				$corpus_title_html = (empty($c_info->title) ? $c : escape_html($c_info->title));
				
				echo "
					<td class=\"$celltype\" width=\"33.3%\" align=\"center\">
						<p class=\"spacer\">&nbsp;</p>
						<p><a href=\"../{$c}/\">$corpus_title_html</a></p>
						<p class=\"spacer\">&nbsp;</p>
					</td>
					\n";
				
				$celltype = ($celltype=='concordgrey'?'concordgeneral':'concordgrey');
				
				if ($i == 2)
				{
					echo "\t\t</tr>\n";
					$i = 0;
				}
				else
					$i++;
			}
			
			if ($i == 1)
			{
				echo "\t\t\t<td class=\"$celltype\" width=\"33.3%\" align=\"center\">&nbsp;</td>\n";
				$i++;
				$celltype = ($celltype=='concordgrey'?'concordgeneral':'concordgrey');
			}
			if ($i == 2)
				echo "\t\t\t<td class=\"$celltype\" width=\"33.3%\" align=\"center\">&nbsp;</td>\n\t\t</tr>\n";
		}
		
		?>
		
		<tr>
			<td colspan="3" class="concordgrey">
				<p class="spacer">&nbsp;</p>
				<p>
					If you think that you should have permissiona for more corpora than are listed above, 
					you should contact the system administrator, explaining which corpora you wish to use,
					and on what grounds you believe you have permission to use them.
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
	</table>

	<?php
}



function do_usr_ui_userdetails()
{
	global $User;
	global $Config;
	
	/* initialise the iso 3166-1 array... */
	require('../lib/user-iso31661.php');
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th colspan="3" class="concordtable">
				Account details
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				Username:
			</td>
			<td class="concordgeneral" colspan="2">
				<?php echo $User->username, "\n"; ?>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Email address:
			</td>
			<td class="concordgeneral" colspan="2">
				<?php echo escape_html($User->email), "\n"; ?>
			</td>
		</tr>
<!--
This is the old version of the form. Hasn't been written presently. CQPweb currently needs emails to be persistent because of group permissions.
		<tr>
			<td class="concordgeneral">Email address (system admin may use this if s/he needs to contact you!)</td>
			<td class="concordgeneral">
				<input name="newSetting_email" type="email" placeholder="your.address@somewhere.net" width="64" value="<?php echo escape_html($User->email); ?>">
			</td>
		</tr>
-->
		<tr>
			<td class="concordgrey" colspan="3">
				&nbsp;<br>
				<strong>Important note</strong>:
				You cannot change either the username or the email address that this account is associated with.
				<br>&nbsp;
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Your full name:
				<form id="updateAccountRealnameForm" action="useracct-act.php" method="post"></form>
				<input form="updateAccountRealnameForm" type="hidden" name="userAction" value="updateUserAccountDetails">
				<input form="updateAccountRealnameForm" type="hidden" name="fieldToUpdate" value="realname">
			</td>
			<td class="concordgeneral">
				<input form="updateAccountRealnameForm" type="text" name="updateValue" value="<?php echo escape_html($User->realname); ?>">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateAccountRealnameForm" type="submit" value="Update">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Your affiliation (institution or company):
				<form id="updateAccountAffiliationForm" action="useracct-act.php" method="post"></form>
				<input form="updateAccountAffiliationForm" type="hidden" name="userAction" value="updateUserAccountDetails">
				<input form="updateAccountAffiliationForm" type="hidden" name="fieldToUpdate" value="affiliation">
			</td>
			<td class="concordgeneral">
				<input form="updateAccountAffiliationForm" type="text" name="updateValue" value="<?php echo escape_html($User->affiliation); ?>">
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateAccountAffiliationForm" type="submit" value="Update">
			</td>
		</tr>
		<tr>
			<td class="concordgeneral">
				Your location:
				<form id="updateAccountCountryForm" action="useracct-act.php" method="post"></form>
				<input form="updateAccountCountryForm" type="hidden" name="userAction" value="updateUserAccountDetails">
				<input form="updateAccountCountryForm" type="hidden" name="fieldToUpdate" value="country">
			</td>
			<td class="concordgeneral">
				<table class="basicbox fullwidth">
					<tr>
						<td class="basicbox">
							<?php echo escape_html($Config->iso31661[$User->country]); ?>
						</td>
						<td class="basicbox">
							<select form="updateAccountCountryForm" name="updateValue">
								<option selected>Select new location ...</option>
								<?php
								foreach ($Config->iso31661 as $k => $country)
									echo "\t\t\t\t\t\t<option value=\"$k\">", escape_html($country), "</option>\n";
								?>
							</select>
						</td>
					</tr>
				</table>
			</td>
			<td class="concordgeneral" align="center">
				<input form="updateAccountCountryForm" type="submit" value="Update">
			</td>
		</tr>
	</table>

	<?php
}


function do_usr_ui_changepassword()
{
	global $User;
	
	?>
	
	<form action="useracct-act.php" method="post">
		<input type="hidden" name="userAction" value="resetUserPassword">
		<input type="hidden" name="userForPasswordReset" value="<?php echo escape_html($User->username); ?>">
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">
					Change your password
				</th>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter your <strong>current</strong> password or passphrase:
				</td>
				<td class="concordgeneral">
					<input type="password" size="30" maxlength="255" name="oldPassword">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Enter your <strong>new</strong> password or passphrase:
				</td>
				<td class="concordgeneral">
					<input type="password" size="30" maxlength="255" name="newPassword">
				</td>
			</tr>
			<tr>
				<td class="concordgeneral">
					Retype the <strong>new</strong> password or passphrase:
				</td>
				<td class="concordgeneral">
					<input type="password" size="30" maxlength="255" name="newPasswordCheck">
				</td>
			</tr>
			<tr>
				<td class="concordgrey" colspan="2" align="center">
					&nbsp;<br>
					Click below to change your password.
					<br>&nbsp;
				</td>
			</tr>
			<tr>
				<td class="concordgeneral" colspan="2" align="center">
					&nbsp;<br>
					<input type="submit" value="Submit this form to change your password">
					<br>&nbsp;
				</td>
			</tr>
		</table>
	</form>
	
	<?php
}






function do_usr_ui_filepreview()
{
	if (empty(($_GET['filename'])))
		exiterror("No file to preview was specified.");
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Previewing uploaded file
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">

				<?php 
				
				do_uploaded_file_view($_GET['filename'], false);
				
				?>
				
			</td>
		</tr>
	</table>
	
	<?php
}





function do_usr_ui_useruploads()
{
	global $User;
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Your uploaded files
			</th>
		</tr>
	</table>
	
	<?php 
	if (1024 < user_upload_space_remaining($User->username))
		do_ui_upload_form_as_table("upload-act.php", "Files that you upload can be used to install your own corpora.", ['uplAction' => 'userFileUpload']);
	else
	{
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					<p><strong>File upload disabled!</strong></p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			<tr>
				<td class="concordgrey">
					<p class="spacer">&nbsp;</p>
					<p>
						You cannot currently upload any files, because your upload area is full.
					</p>
					<p>
						To re-enable file upload, either request an increase to your file storage limit, 
						or delete some of your files to free up space.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
		</table>
		
		<?php
	}
	
	do_ui_display_upload_area($User->username, false);
}



function do_usr_ui_showcorpora()
{
	global $Config;
	global $User;
	
	if (!$User->is_admin() && !$Config->user_corpora_enabled)
		exiterror("The system for installing and using users' own corpus data is not switched on.");
	
	do_ui_showcorpora('usercorpora', $User->username);
}






function do_usr_ui_install()
{
	global $User;
	global $Config;
	
	if (!$User->is_admin() && !$Config->user_corpora_enabled)
		exiterror("The system for installing and using users' own corpus data is not switched on.");
	
	$status_mapper = $Config->installer_process_status_description_map;
	
	$done_procs    = count_installer_processes_of_user($User->username, INSTALLER_STATUS_DONE);
	$aborted_procs = count_installer_processes_of_user($User->username, INSTALLER_STATUS_ABORTED);
	$running_procs = count_installer_processes_of_user($User->username) - ($aborted_procs + $done_procs);
	
	$cant_install_cos_proc_running  = !($User->is_admin() || 0 == $running_procs);
	$cant_install_cos_no_disk_space = !($User->is_admin() || !$User->has_exceeded_user_corpus_disk_limit());
	
// 	$show_new_corpus = (0 == $running_procs && !$User->has_exceeded_user_corpus_disk_limit()) || $User->is_admin();
	$show_new_corpus = ! ($cant_install_cos_proc_running || $cant_install_cos_no_disk_space);
// show_var($running_procs);
// show_var($User->has_exceeded_user_corpus_disk_limit());

	$show_finished_procs =  0 < ($aborted_procs + $done_procs);

	$plugins = get_all_plugins_info(PLUGIN_TYPE_CORPUSINSTALLER);
	$procs   = get_all_installer_process_info();
	
	$list = list_installer_processes_of_user($User->username);

	
	if (!$show_new_corpus)
	{
//TODO,, both "reasons" show, regardless of which has triggered this. Split boolean into two paths. 
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="2">
					Install a new corpus
				</th>
			</tr>
			
			<?php
			
			if ($cant_install_cos_proc_running)
			{
				?>
				
				<tr>
					<td class="concordgrey" >
						<p class="spacer">&nbsp;</p>
						<p>
							You already have <?php echo 1 == $running_procs ? 'a process' : "$running_procs processes"; ?>
							on the corpus install queue; you can't add another until <?php echo 1 == $running_procs ? 'it has' : 'they have'; ?> completed.
						</p>
						<p class="spacer">&nbsp;</p>
					</td>
				</tr>
				
				<?php
			}
			
			?>
			
		</table>
		
		<?php
	}
	
	if ($running_procs)
	{
		//show the progress table unconsitionally if $running_procs > 0 : <th>Installer process progress</th>
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="3">
					Running corpus-install processes 
				</th>
			</tr>
	
			<tr>
				<th class="concordtable">Installer used</th>
				<th class="concordtable">Current status</th>
				<th class="concordtable">Time status last changed</th>
			</tr>
		
		<?php
		
// 		foreach ([INSTALLER_STATUS_WAITING,INSTALLER_STATUS_TAGGING,INSTALLER_STATUS_ENCODING,INSTALLER_STATUS_FREQLIST,INSTALLER_STATUS_SETUP] as $stat)
		foreach($list as $ip_id)
		{
			if (INSTALLER_STATUS_ABORTED == $procs[$ip_id]->status || INSTALLER_STATUS_DONE == $procs[$ip_id]->status)
				continue;
// 			if ($procs[$ip_id]->status == $stat)
			echo "\n\t\t\t<tr>"
					, '<td class="concordgeneral">'
 						, escape_html($plugins[$procs[$ip_id]->plugin_reg_id]->description)
					, '</td>'
				, '<td class="concordgeneral" align="center">', $status_mapper[$procs[$ip_id]->status], '</td>'
				, '<td class="concordgeneral" align="center">', date(CQPWEB_UI_DATE_FORMAT, strtotime($procs[$ip_id]->last_status_change)), '</td>'
				, "</tr>\n"
				;
		}
		?>
		
			<tr>
				<td class="concordgrey" colspan="3">
					<p>
						This display of your installer processes' status was last updated at 
						<strong><?php echo date("H:i"); ?></strong>.
					</p>
					
					<script>
					/* Auto-reload the page after 60 seconds. */
					window.setTimeout(function () {window.location.reload(true);}, 60000);
					</script>
					
					<!-- <p>
						<a onClick="window.location.reload(true);" class="menuItem">[Click here to update this information.]</a>
					</p> -->
				</td>
			</tr>
		
		</table>
	
		<?php
	}
	
	if ($show_finished_procs)
	{
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable" colspan="5">
					Completed corpus-install processes 
				</th>
			</tr>
			<tr>
				<th class="concordtable">Installer used</th>
				<th class="concordtable">Final status</th>
				<th class="concordtable">Status message</th>
				<th class="concordtable">Process ended...</th>
				<th class="concordtable">Dismiss</th>
			</tr>
			
			<?php
			
			$prefix = get_user_corpus_web_directory($User->username);
			
			foreach($list as $ip_id)
			{
				if (INSTALLER_STATUS_ABORTED != $procs[$ip_id]->status && INSTALLER_STATUS_DONE != $procs[$ip_id]->status)
					continue;
				
				$error_msg = 
					INSTALLER_STATUS_ABORTED == $procs[$ip_id]->status 
					? escape_html($procs[$ip_id]->error_message) 
					: preg_replace('~\[(\w+)\](.*?)\[/\]~', "<a href=\"$prefix$1\">$2</a>", $procs[$ip_id]->error_message)
					;
				
				echo "\n\t\t<tr>"
					, '<td class="concordgeneral">'
						, escape_html($plugins[$procs[$ip_id]->plugin_reg_id]->description)
					, '</td>'
					, '<td class="concordgeneral">', $status_mapper[$procs[$ip_id]->status], '</td>'
					, '<td class="concordgeneral">', $error_msg, '</td>'
					, '<td class="concordgeneral" align="center">'
						, date(CQPWEB_UI_DATE_FORMAT, strtotime($procs[$ip_id]->last_status_change))
					, '</td>'
					, '<td class="concordgeneral" align="center"><a class="menuItem" href="usercorpus-act.php?ucAction=dismissUserProcessRecord&job='
						, $ip_id, '">[x]</a></td>'
					, "</tr>\n"
					;
			}
			?>
		
		</table>
		
		<?php	
	}
	
	if ($cant_install_cos_no_disk_space)
// 	if ($User->has_exceeded_user_corpus_disk_limit() && !$User->is_admin())
	{
		?>
		
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					You cannot install a corpus at present because you are using all of your allocated disk-space. 
				</th>
			</tr>
			

			<tr>
				<td class="concordgrey" colspan="2">
					<p class="spacer">&nbsp;</p>
					<p>
						You must either <a href="index.php?ui=showCorpora">delete some of your existing corpus data</a>, 
						or else request a bigger space allocation.
					</p>
					<p>
						Your allocation is currently <strong><?php get_user_corpus_disk_allocation($User->username)?> MB</strong>.
					</p>
					<p class="spacer">&nbsp;</p>
				</td>
			</tr>
			
		</table>
		
		<?php
	}


	if (!$show_new_corpus)
		return;
		
	?>
	
	
	<form id="userCorpusInstall" action="usercorpus-act.php" method="get"></form>
	
	<input form="userCorpusInstall" type="hidden" name="ucAction" value="userCorpusInstall"> 
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">
				Install a new corpus
			</th>
		</tr>
		
		<tr>
			<th class="concordtable" colspan="2">
				Choose a Corpus Installer
			</th>
		</tr>
		<tr>
			<td class="concordgrey" colspan="2">
				<p class="spacer">&nbsp;</p>
				<p>
					You have access to the following Corpus Installer systems.
					Please select the installer you want to use.
				</p>
				<p class="spacer">&nbsp;</p>
			</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center" colspan="2">
				
				<input type="hidden" id="userCorpusInstaller" name="installer" value="" form="userCorpusInstall">
				
				<script>

				/* TODO maybe mve all this setup to a .js file? */

				$(function () {

					function install_user_corpus_ajax_return_handler(data)
					{
						greyout_and_throbber_off();
						if (!data || !data.status || !("error" == data.status || "ok" == data.status))
						{
							greyout_and_acknowledge("Error: Malformed response from the CQPweb server.");
							return;
						}

						if ("error" == data.status) 
						{
							greyout_and_acknowledge("Error: " + data.message);
							return;
						}
						greyout_and_acknowledge(
								"Your corpus is queued for installation.",
								"index.php?ui=installCorpus"
								);
					}

					$('a.select').click( 
								function (e)
								{
									var button = $(e.target);

									/* deselect others. */
									$('.selectChosen').removeClass('selectChosen');
									$('.installerBox').css('border-color', 'white');

									button.addClass('selectChosen');
									button.parents('.installerBox').css('border-color', 'blue');

									/* does this creator need file selector ? */
									if (0 == button.attr('data-needs-files'))
									{
// console.log("false: " +  button.attr('data-needs-files'));// need no selector/
										$('[name="includeFile"]').prop('disabled', true);
										$('#fileSelectorArea').slideUp();
										$('#noFileMessage').slideDown();
										// turn parent & uncle tds to main class "general"
									}
									else
									{
// console.log("true: " +  button.attr('data-needs-files'));
										$('[name="includeFile"]').prop('disabled', false);
										$('#noFileMessage').slideUp();
										$('#fileSelectorArea').slideDown();
									}

//console.log("what was on button that found:@ " + button.attr("data-plugin-id") );
									$('[id="userCorpusInstaller"]').val( button.attr("data-plugin-id") );
									return false;
								}
							);
					$('a.details').click(
								function (e)
								{
									var button = $(e.target);
									// TODO pop up a details pane. 
									alert("This will eventually pop up extra info for plugin # "+ button.attr("data-plugin-id")); 
									return false;
								}
							);
					$('#userCorpusInstall').submit(
								/** this is the function that sets everything up for form submission aqnd then sends it via Ajax instead. */
								function (e)
								{
									/* block submission */
									e.preventDefault();

									/* disable UI */
									greyout_and_throbber();
									
									var arr = [];
									$("input[name='includeFile']").each (
											function (ix, el) { console.log(el.checked); if (el.checked) {console.log("adding "+$(el).val());arr.push($(el).val()); }}
											);
									
									var data_obj = {};
									data_obj.ucAction = "userCorpusInstall";
									data_obj.corpus_description = $("#corpus_description").val();
									data_obj.corpus_scriptIsR2L = ( $("[id='corpus_scriptIsR2L:1']").prop('checked') ? "1" : "0" );
									data_obj.colourScheme = $("#colourScheme").val();
									data_obj.includeFileArray = arr;
									data_obj.installer = $("#userCorpusInstaller").val();
									if ("" === data_obj.installer)
									{
										greyout_and_acknowledge("Please select a Corpus Installer!");
										return;
									}
									data_obj.emailReq = ( $("[id='emailReq:1']").prop('checked') ? "1" : "0");
//console.log(data_obj);

									$.get({
											url:     "usercorpus-act.php",
											cache:   false,
											data:    data_obj,
											success: install_user_corpus_ajax_return_handler,
											error:   function (a,b,c) {greyout_and_acknowledge("Error: could not contact, or did not receive valid data from, the server!");}
										});
//alert("ready to go");
									return false;
								}
							);
				});



				</script>
				
				<?php /* TODO put this style into a system CSS file. */ ?>
				<style>
				
				div.installerContainer {
  					text-align: center;
 					display: inline-block;
 					width: 90%;
				}
				
				div.installerGrid {
 					margin-left: auto;
 					margin-right: auto;
					padding: 7px;
					display: grid;
					justify-items: center;
					grid-template-columns: repeat(auto-fill, 390px);
				}
				
				div.installerBox {
					display: block;
 					height: 100px;
					width: 350px;
					background-color: #efefef;
					border-radius: 25px;
					border: 2px solid white;
					margin: 7px;
					padding: 14px;
				}
				
				div.installerBox span.label {
					display: block;
					text-align: center;
					font-size: 12pt;
					font-weight: bold; 
				}
				
				div.installerBox span.maxtok {
					display: block;
					text-align: left;
				}
				
				div.installerBox a.select {
					display: inline-block;
					text-align: center;
					width: 50%;
					height: 30px;
					padding-top: 10px;
				}
				div.installerBox a.selectChosen {
					background-color: lavender;
				}
				div.installerBox a.select:hover,  a.selectChosen:hover {
					background-color: lemonchiffon;
				}
				
				div.installerBox a.details {
					display: inline-block;
					text-align: center;
					width: 50%;
					height: 30px;
					padding-top: 10px;
				}
				
				div.installerBox a.details:hover {
					background-color: lemonchiffon;
				}
				
				
				</style>
				
				<div class="installerContainer">
				
					<div class="installerGrid">
					
					<?php
					$blobs_written = false;
					
					/* list of all plugins for which the user has permission. */
					foreach (get_all_plugins_info(PLUGIN_TYPE_CORPUSINSTALLER) as $plg)
					{
						$installer_obj = new $plg->class($plg->extra);

						$max = get_corpus_installer_privilege_tokens($plg->id, $User->username);

						if (1 > $max)
							continue;

						echo "\n\t\t\t\t\t"
							, '<div class="installerBox">'
	 						, '<span class="label">', escape_html($plg->description), '</span>'
	 						, '<br>'
	 						, '<span class="maxtok">Install a corpus up to <strong>', number_format($max, 0), '</strong> tokens.</span>'
	 						, '<br>'
	 						, '<div width="100%" style="text-align: justify;"> '
		 						, '<a class="details" data-maxtok="', $max, '" data-plugin-id="', $plg->id, '">[Details]</a>'
	 							, '<a class="select"  data-needs-files="', ($installer_obj->needs_input_files() ? 1 : 0), '" data-plugin-id="', $plg->id, '">[Select]</a> '
							, '</div>'
	 						, '</div>'
	 						, "\n"
	 						;
						$blobs_written = true;
					}

					if (!$blobs_written)
					{
						?>
						<div style="text-align:left;">
							<p>
								You do not have permission to use any of the available Corpus Installers.
							</p>
							<p>
								If you think this is not correct, then please contact your system administrator.
							</p>
						</div>

						<?php
					}
					?>

					</div>

				</div>

			</td>
		</tr>
	</table>

	<div id="fileSelectorArea">

		<?php
		
		
		do_fileselector_table(get_user_upload_area($User->username), 'userCorpusInstall', 'usercorpus');
		
		// NB. Should fileselector hide itself, and filenames fail to be uploaded, if the CorpusInstaller assures us that it doesn't need file lists? TODO
		
		?>
	
	</div>
	
	<div id="noFileMessage" style="display:none">
	
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">
					No input files needed!
				</th>
			</tr>
			<tr>
				<td class="concordgrey">
					<p>This Corpus Installer does not need you to specify any input files.</p>
				</td>
			</tr>
		</table>

	</div>

<!-- 		<tr> -->
<!-- 			<th class="concordtable" colspan="2"> -->
<!-- 				Select files -->
<!-- 			</th> -->
<!-- 		</tr> -->
<!-- 		<tr> -->
<!-- 			<td class="concordgrey" colspan="2"> -->
<!-- 				<p class="spacer">&nbsp;</p> -->
<!-- 				<p> -->
<!-- 					Select files from your upload area to include in the corpus. If the file(s) contain more data  -->
<!-- 					than you are allowed to install, the overflow will be left out of the corpus. -->
<!-- 				</p> -->
<!-- 				<p class="spacer">&nbsp;</p> -->
<!-- 			</td> -->
<!-- 		</tr>		 -->
<!-- 		<tr> -->
<!-- 			<td class="concordgeneral" colspan="2"> -->
<!-- 				<p class="spacer">&nbsp;</p> -->
<!-- 				<p> -->
<!-- 					 No files are currently selected. -->
<!-- 				</p> -->
<!-- 				<p class="spacer">&nbsp;</p>			 -->
<!-- 				<p> -->
<!-- 					<input type="hidden" id="userCorpusInstall:fileList" name="fileList" value="" form="userCorpusInstall"> -->
<!-- 					(File selector tool herre - pop up as JS thingy) -->
					
<!-- 				</p> -->
<!-- 				<p> -->
<!-- 					 Be aware that if the file(s) contain more data than you atre allowed to install, -->
<!-- 					 the corpus will be truncated.  -->
<!-- 				</p>			  -->
<!-- 			</td> -->
<!-- 		</tr> -->
		
		
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="2">
				Set options
			</th>
		</tr>
		
		<tr>
			<td class="concordgrey" width="50%">Enter the full descriptive name of your corpus</td>
			<td class="concordgeneral">
				<input form="userCorpusInstall" id="corpus_description" type="text" name="corpus_description">
			</td>
		</tr>
		<!-- 
		<tr>
			<td class="concordgrey">(Optional) Select the language used in your corpus</td>
			<td class="concordgeneral">
				<input form="userCorpusInstall" type="checkbox" name="corpus_scriptIsR2L" value="1">
			</td>
		</tr>
		(Not yet. For now, either leave unknown, or else let the installer choose. 
		-->
		<tr>
			<td class="concordgrey">
				Tick here if the main script in the corpus is right-to-left
				<br>
				<em>(Arabic, Hebrew, Aramaic, etc.)</em>
			</td>
			<td class="concordgeneral">
				<input form="userCorpusInstall" type="checkbox" id="corpus_scriptIsR2L:1" name="corpus_scriptIsR2L" value="1">
			</td>
		</tr>
		
		<tr>
			<td class="concordgrey">
				Select a colour scheme for the interface to your corpus
				<br>
				<em>(will be overridden if you have the &rdquo;monochrome&rdquo; option enabled)</em>
			</td>
			<td class="concordgeneral">
				<select id="colourScheme" form="userCorpusInstall" name="colourScheme">
					<!-- TODO replace with colour picker that shows a bit of the colours (js); this is just a filler. OR, a pair of colouir pickers. -->
					<option value="blue" selected>Blue</option>
					<?php
					foreach (explode(',', 'yellow,green,red,brown,purple,navy,lime,aqua,neon,dusk,gold,rose,teal') as $col)
						echo "\n\t\t\t\t\t", '<option value="', $col, '">', ucfirst($col), '</option>';
					echo "\n";
					?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">
				Tick here to receive an alert by email when your corpus is ready to use.
			</td>
			<td class="concordgeneral">
				<input form="userCorpusInstall" type="checkbox" id="corpus_scriptIsR2L:1" name="corpus_scriptIsR2L" value="1">
			</td>
		</tr>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Run corpus installer 
			</th>
		</tr>
		
		<tr>
			<td class="concordgeneral" colspan="2" align="center">
				<p><input form="userCorpusInstall" type="submit" value="Install corpus with settings above"></p>
				<p><input form="userCorpusInstall" type="reset" value="Reset all options"></p>
			</td>
		</tr>
	</table>
	
	
	
	<?php
}



/** 
 * This is the "are you sure" screen for deleting a user corpus. 
 * It's similar to, but not identical to, the equivalent admin screen. 
// TODO - use a pop up are you sure instread. 
 */
function do_usr_ui_deletecorpus()
{
	if (false === ($c_info = get_corpus_info($_GET['corpus'])))
		exiterror("Can't delete a corpus that does not exist.");
	
	?>
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				You have requested deletion of the corpus &ldquo;<?php echo escape_html($c_info->title); ?>&rdquo; from the CQPweb system.
			</th>
		</tr>
		<tr>
			<td class="concordgrey" align="center">Are you sure you want to do this?</td>
		</tr>
		<tr>
			<td class="concordgeneral" align="center">
				<form class="greyoutOnSubmit" action="usercorpus-act.php" method="get">
					<input type="hidden" name="ucAction" value="userCorpusDelete">
					<input type="hidden" name="cId" value="<?php echo $c_info->id; ?>">
					<br>
					<input type="checkbox" id="sureyouwantto" name="sureyouwantto" value="yes">
					<label for="sureyouwantto">Yes, I'm sure I want to do this.</label>
					<br>&nbsp;<br>
					<input type="submit" value="I am definitely sure I want to delete this corpus.">
					<br>&nbsp;
				</form>
			</td>
		</tr>
	</table>					
		
	<?php
}




function do_usr_ui_showcolleaguates()
{
	global $Config;
	if (!$Config->colleaguate_system_enabled)
		exiterror("The Colleaguate system is not enabled on this CQPweb server. ");

	global $User;
	
	$peeps = get_all_active_colleaguate_info($User->id);
	
	
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="6">
				Your Colleaguates
			</th>
		</tr>
	
		<tr>
			<th class="concordtable">
				Username
			</th>
			<th class="concordtable">
				Realname
			</th>
			<th class="concordtable">
				Affiliation
			</th>
			<th class="concordtable">
				Email address
			</th>
			<th class="concordtable">
				Shares with you
			</th>
			<th class="concordtable">
				Actions
			</th>
		</tr>
	
	
	
	
	<?php
	if (empty($peeps))
		echo "\n\t\t<tr><td class=\"concordgrey\" colspan=\"6\"><p>You do not have any colleaguations.</p></td></tr>\n";
	
	foreach($peeps as $peep)
		echo "\n\t\t<tr>"
 			, '<td class="concordgeneral">', $peep->username, '</td>'
 			, '<td class="concordgeneral">', escape_html($peep->realname), '</td>'
 			, '<td class="concordgeneral">', escape_html($peep->affiliation), '</td>'
 			, '<td class="concordgeneral">', escape_html($peep->email), '</td>'
 			, '<td class="concordgeneral">', 'TODO: N corpora', '</td>'
 			, '<td class="concordgeneral" align="center">'
 					, '<a href="useracct-act.php?userAction=breakColleaguation&who=', escape_html($peep->username), '" class="menuItem">'
 		// TODO need an ARE YOU SURE? popup her.
 					, '[Remove colleaguate]</a></td>'
 			, '</tr>'
 			;
	//TODO, more is needed here in terms of formatting etx.
	
 	?>
 	
 	</table>
 			
 	<?php
}

function do_usr_ui_newcolleaguate()
{
	global $Config;
	if (!$Config->colleaguate_system_enabled)
		exiterror("The Colleaguate system is not enabled on this CQPweb server. ");
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">
				Add New Colleaguate
			</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<p>A &ldquo;Colleaguate&rdquo; is another user of this CQPweb who you wish to share data with.</p>
				<p>Use this tool to add new colleaguates.</p>
				<p>You can only add people as colleaguates if you know their email address.</p>
				<p>The link between you and the new person will only become active if they accept your request.</p>
			</td>
		</tr>
		<tr>
			<td class="concordgrey">
				<?php echo "\n", print_colleaguate_privacy_notice(), "\n"; ?>
			</td>
		</tr>
		<tr>
			<th class="concordtable">Send a colleaguate request to another user</th>
		</tr>
		<tr>		
			<td class="concordgeneral" align="center">
				<form action="useracct-act.php" method="get">
					<input type="hidden" name="userAction" value="requestColleaguation">
					<p>
						<label for="cgtEmail">
							Enter your collaborator's email address:
						</label>
						<input type="email" size="60" name="cgtEmail" placeholder="Please type carefully!">
					</p>
					<p>
						<input type="submit" value="Send colleaguate request">
					</p>
				</form>
			</td>
		</tr>
 	</table>
 			
 	<?php
}


function do_usr_ui_colleaguaterequests()
{
	global $Config;
	if (!$Config->colleaguate_system_enabled)
		exiterror("The Colleaguate system is not enabled on this CQPweb server. ");
	
	global $User;
		
	?>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="6">
				Colleaguation requests received
			</th>
		</tr>
		<tr>
			<th class="concordtable">From user</th>
			<th class="concordtable">Their name</th>
			<th class="concordtable">Their affiliation</th>
			<th class="concordtable">Their email address</th>
			<th class="concordtable" colspan="2">Actions</th>
		</tr>

		<?php
		$requesters = get_all_colleaguation_requesters_info($User->id);
		
		if (empty($requesters))
			echo "\n\t\t<tr><td class=\"concordgrey\" colspan=\"6\"><p>You do not have any colleaguate requests right now.</p></td></tr>\n";
		
		foreach($requesters as $u)
			echo "\n\t\t<tr>"
 				, "<td class=\"concordgeneral\">{$u->username}</td>"
 				, "<td class=\"concordgeneral\">", escape_sql($u->realname), "</td>"
 				, "<td class=\"concordgeneral\">", (empty($u->affiliation) ? '<em>(unknown)</em>' : escape_sql($u->affiliation)), "</td>"
 				, "<td class=\"concordgeneral\">", escape_sql($u->email), "</td>"
 				, "<td class=\"concordgeneral\" align=\"center\">"
					, "<a class=\"menuItem\" href=\"useracct-act.php?userAction=confirmColleaguation&confirmCgtId={$u->id}\">[Accept]</a>"
				, "</td>"
 				, "<td class=\"concordgeneral\" align=\"center\">"
					, "<a class=\"menuItem\" href=\"useracct-act.php?userAction=breakColleaguation&who={$u->id}&from=requests\">[Decline]</a>"
				, "</td>"
 				, "</tr>\n"
 				;
		?>
		
		<tr>
			<td class="concordgrey" colspan="6">
				<?php echo "\n", print_colleaguate_privacy_notice(), "\n"; ?>
			</td>
		</tr>
	</table>
	
	<?php
}


function do_usr_ui_viewsharedcorpora()
{
	global $Config;
	if (!$Config->colleaguate_system_enabled)
		exiterror("The Colleaguate system is not enabled on this CQPweb server. ");
	
	global $User;
	
	?>
	
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable">Share a corpus with one of your colleaguates</th>
		</tr>
		<tr>
			<td class="concordgeneral">
				<form action="usercorpus-act.php" method="get">
					<input type="hidden" name="ucAction" value="userCorpusMakeGrant">
					<p>
						Select one of your installed corpora:
						<select name="cId">
							<?php
							$corpora = get_all_sql_objects("select id, title from corpus_info where owner='{$User->username}' and visible = 1 order by corpus asc");
							
							if (empty($corpora))
								echo "<option selected>You don't have any installed corpora</option>\n"; 

							foreach ($corpora as $uc)
								echo "\n\t\t\t\t\t<option value=\"{$uc->id}\">"
									, escape_html($uc->title)
									, "</option>\n"
 									;
							?>
						</select>
					</p>
					<p>
						Select a colleaguate to share this data with:
						<select name="whither">
							<?php
							$colleaguates = get_all_active_colleaguate_info($User->id);
							
							if (empty($colleaguates))
								echo "<option selected>You don't have any colleaguates to share data with</option>\n"; 
							
							$cg_htmlmap = [];
							foreach ($colleaguates as $cu)
							{
								$cg_htmlmap[$cu->username] = $cu->username . escape_html( empty($cu->realname) ?  '' : ' (' .$cu->realname.')' );
								echo "\n\t\t\t\t\t<option value=\"{$cu->id}\">{$cg_htmlmap[$cu->username]}</option>\n";
							}
							?>
						</select>
					</p>
					<p>
						Add a message to your colleaguate about this corpus (optional):
						<br>
						<input type="text" size="100" maxlength="255" name="grantMsg" placeholder="Messages can be up to 255 characters">
					</p>
					<p>
						<input type="submit" value="Share corpus access">
					</p>
				</form>
			</td>
		</tr>
	</table>
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">Corpora shared with you by your colleaguates</th>
		</tr>
		<tr>
			<th class="concordtable">Corpus ID and link</th>
			<th class="concordtable">Description</th>
			<th class="concordtable">Owner</th>
			<th class="concordtable">Comment on sharing</th>
			<th class="concordtable">Actions</th>
		</tr>
		
		<?php
		$shares_in = get_all_user_corpus_grants_incoming($User->id);
//echo "<!--\n";
//var_dump($shares_in);
//echo "\n\n-->\n";
		if (empty($shares_in))
			echo "\n\t\t<tr><td class=\"concordgrey\" colspan=\"5\">No one has shared any of their corpora with you.</td>\n";
		
		foreach($shares_in as $sh)
			echo "\n\t\t<tr>"
 				, "<td class=\"concordgeneral\">"
 					, "<a href=\"", get_user_corpus_web_path($sh->id, $sh->owner), "\">{$sh->corpus}</a>"
 				, "</td>"
 				, "<td class=\"concordgeneral\">", escape_html($sh->title), "</td>"
 				, "<td class=\"concordgeneral\">{$cg_htmlmap[$sh->owner]}</td>"
 				, "<td class=\"concordgeneral\">", escape_html($sh->comment), "</td>"
 				, "<td class=\"concordgeneral\" align=\"center\">"
 					, "<a class=\"menuItem\" href=\"usercorpus-act.php?ucAction=userCorpusRejectGrant&cId={$sh->id}\">[Reject share]</a>"
 				, "</td>"
				, "</tr>\n"
 				;
		
		?>
		
	</table>
	
	
	<table class="concordtable fullwidth">
		<tr>
			<th class="concordtable" colspan="5">Corpus shares you have created for your colleaguates</th>
		</tr>
		<tr>
			<th class="concordtable">Corpus ID and link</th>
			<th class="concordtable">Description</th>
			<th class="concordtable">Shared with</th>
			<th class="concordtable">Your comment</th>
			<th class="concordtable">Actions</th>
		</tr>
		
		<?php
		$shares_out = get_all_user_corpus_grants_outgoing($User->id);
?><!--<?php var_dump($shares_out); ?>--><?php
		if (empty($shares_out))
			echo "\n\t\t<tr><td class=\"concordgrey\" colspan=\"5\">You have not shared any corpus data.</td>\n";

		foreach($shares_out as $sh)
			echo "\n\t\t<tr>"
 				, "<td class=\"concordgeneral\">"
 					, "<a href=\"", get_user_corpus_web_path($sh->id, $sh->owner), "\">{$sh->corpus}</a>"
 				, "</td>"
 				, "<td class=\"concordgeneral\">", escape_html($sh->title), "</td>"
				, "<td class=\"concordgeneral\">", $cg_htmlmap[user_id_to_name($sh->grantee_id)], "</td>"
 				, "<td class=\"concordgeneral\">", escape_html($sh->comment), "</td>"
 				, "<td class=\"concordgeneral\" align=\"center\">"
 					, "<a class=\"menuItem\" href=\"usercorpus-act.php?ucAction=userCorpusWithdrawGrant&cId={$sh->id}&whence={$sh->grantee_id}\">[Withdraw share]</a>"
 				, "</td>"
				, "</tr>\n"
 				;
		?>
	
	</table>
	
	
	<?php
}

function print_colleaguate_privacy_notice()
{
	return <<<END_OF_HTML

				<p>
					<strong>Privacy and data protection:</strong>
					When you add someone as a colleaguate, you make it possible for them to see your
					<em>username</em> as well as the <em>real name</em>, <em>affiliation</em> and 
					<em>email address</em> that you have added to your <a href="index.php?ui=userDetails">account profile</a>.
				</p>
				<p>
					The corpus data you have uploaded is only accessible to your colleaguates when you 
					actively share it with them.
				</p>

END_OF_HTML;
}


