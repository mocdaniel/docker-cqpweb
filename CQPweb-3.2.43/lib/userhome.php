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

require('../lib/environment.php');

/* include function library files */
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/corpus-lib.php');
require('../lib/html-lib.php');
require('../lib/useracct-lib.php');
require('../lib/metadata-lib.php');
require('../lib/usercorpus-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/collocation-lib.php');
require('../lib/plugin-lib.php');
require('../lib/upload-lib.php');

require('../lib/useracct-forms.php');
require('../lib/info-forms.php');


/* declare global variables */
$User = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_USR);



/* ui: the interface page is to be displayed on the right-hand-side. */
$what_ui = ( isset($_GET["ui"]) ? $_GET["ui"] : 'welcome' );


echo print_html_header('CQPweb User Page', $Config->css_path, array('cword', 'useracct-create'));


/* =================== *
 * PRINT SIDE BAR MENU *
 * =================== */
?>
<!-- main table -->
<table class="concordtable fullwidth">
	<tr>
		<td valign="top">
			<!-- start of cell with left-hand menu -->



<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable"><a class="menuHeaderItem">Menu</a></th>
	</tr>
</table>

<table class="concordtable fullwidth">

<?php

/* The menu is different for when we are logged on, versus when we are not */

if ($User->logged_in)
{
	echo print_menurow_heading('Your account');
	echo print_menurow_index('welcome', 'Overview',    $what_ui);
	echo print_menurow_index('userSettings', 'Interface settings',    $what_ui);
	echo print_menurow_index('userMacros', 'User macros',    $what_ui);
	echo print_menurow_index('corpusAccess', 'Corpus permissions',    $what_ui);
	if ($Config->user_corpora_enabled)
	{
		echo print_menurow_heading('Your files and corpora');
		echo print_menurow_index('showCorpora', 'View your corpora',    $what_ui);
		echo print_menurow_index('installCorpus', 'Install a new corpus',    $what_ui);
		echo print_menurow_index('viewFiles', 'Manage your files',    $what_ui);
	}
	if ($Config->colleaguate_system_enabled)
	{
		echo print_menurow_heading('Colleaguates');
		echo print_menurow_index('showColleaguates', 'Show colleaguates',    $what_ui);
		echo print_menurow_index('newColleaguate', 'Add new colleaguate',    $what_ui);
		echo print_menurow_index('colleaguateRequests', 'Colleaguate requests received',    $what_ui);
		echo print_menurow_index('viewSharedCorpora', 'View shared corpora',    $what_ui);
	}
	echo print_menurow_heading('Account actions');
	echo print_menurow_index('userDetails', 'Account details',    $what_ui);
	echo print_menurow_index('changePassword', 'Change password',    $what_ui);
	echo print_menurow_index('userLogout', 'Log out of CQPweb',    $what_ui);
	if ($User->is_admin())
	{
		?>
		<tr>
			<td class="concordgeneral">
				<a class="menuItem" href="../adm">Go to admin control panel</a>
			</td>
		</tr>
		<?php
	
	}
}
else
{
	/* if we are not logged in, then we want to show a different default ... */
	if ($what_ui == 'welcome')
		$what_ui = 'login';

	/* menu seen when no user is logged in */
	echo print_menurow_heading('Account actions');
	echo print_menurow_index('login', 'Log in to CQPweb',    $what_ui);
	echo print_menurow_index('create', 'Create new user account',    $what_ui);
	echo print_menurow_index('verify', 'Activate new account',    $what_ui);
	echo print_menurow_index('resend', 'Resend account activation',    $what_ui);
	echo print_menurow_index('lostUsername', 'Retrieve lost username',    $what_ui);
	echo print_menurow_index('lostPassword', 'Reset lost password',    $what_ui);
	
}

/* and now the menu that is seen unconditionally ... */
echo print_menu_aboutblock($what_ui);



?>
</table>

		<!--  end of cell with left-hand menu -->
		</td>
		
		<td valign="top">
			<!--  start of cell with right-hand content -->
			
		
<table class="concordtable fullwidth">
	<tr>
		<th class="concordtable">
			<a class="menuHeaderItem">
				CQPweb User Page
			</a>
		</th>
	</tr>
</table>



<?php




/* =============================== *
 * PRINT SELECTED FUNCTION CONTENT *
 * =============================== */

$helplink = false;


/*
 * Note: we need to have two wholly disjunct sets here, one if a user is logged in, and one if they are not. 
 * If no option matches, $switch_again is set true, and the common cases for both are checked after the specific ones.
 */

$switch_again = false;

//TODO. when not logged in, these options can't be accessed, but there is no login redirect. 

if ($User->logged_in)
{
	switch($what_ui)
	{
	case 'welcome':
		do_usr_ui_welcome();
		display_system_messages();
		break;

	case 'userSettings':
		do_usr_ui_usersettings();
		break;

	case 'userMacros':
		do_usr_ui_usermacros();
		break;

	case 'corpusAccess':
		do_usr_ui_corpusaccess();
		break;

	case 'showCorpora':
		do_usr_ui_showcorpora();
		break;

	case 'viewFiles':
		do_usr_ui_useruploads();
		break;

	case 'userFilePreview':
		do_usr_ui_filepreview();
		break;

	case 'installCorpus':
		do_usr_ui_install();
		break;

	case 'deleteCorpus':
		do_usr_ui_deletecorpus();
		break;

	case 'showColleaguates':
		do_usr_ui_showcolleaguates();
		break;

	case 'newColleaguate':
		do_usr_ui_newcolleaguate();
		break;

	case 'colleaguateRequests':
		do_usr_ui_colleaguaterequests();
		break;

	case 'viewSharedCorpora':
		do_usr_ui_viewsharedcorpora();
		break;

	case 'userDetails':
		do_usr_ui_userdetails();
		break;

	case 'changePassword':
		do_usr_ui_changepassword();
		break;

	case 'userLogout':
		do_usr_ui_logout();
		break;

	/* common cases: switch again below. */
	default:
		$switch_again = true;
		break;
	}
} /* endif a user is logged in */

else
{
	switch($what_ui)
	{
	case 'login':
		do_usr_ui_login();
		display_system_messages();
		$helplink = 'hello';
		break;
	
	case 'create':
		do_usr_ui_create();
		$helplink = 'signup';
		break;
	
	case 'verify':
		do_usr_ui_verify();
		$helplink = 'hello';
		break;

	case 'resend':
		do_usr_ui_resend();
		$helplink = 'hello';
		break;
	
	case 'lostUsername':
		do_usr_ui_lostusername();
		$helplink = 'hello';
		break;
	
	case 'lostPassword':
		do_usr_ui_lostpassword();
		$helplink = 'hello';
		break;

	/* common cases: switch again below. */
	default :
		//TODO what should actually happen: if they were TRYING to access one of the real UIs, redirect to the login page with a decent locationAfter.
		// if they weren't, then redirect them to welcome. 
		$switch_again = true;
		break;
	}
} /* endif no user is logged in */

/* COMMON CASES */

if ($switch_again)
{
	switch ($what_ui)
	{
	case 'accessDenied':
		do_usr_ui_accessdenied();
		$helplink = 'hello';
		break;
	
	case 'who_the_hell':
		do_ui_credits();
		$helplink = 'hello';
		break;
		
	case 'latest':
		do_ui_latest();
		$helplink = 'hello';
		break;
	
	case 'bugs':
		do_ui_bugs();
		$helplink = 'hello';
		break;

	case 'embed':
		do_ui_embed_page();
		$helplink = 'hello';
		break;
	
	default:
		?>
		
		<p class="spacer">&nbsp;</p>
		<p class="errormessage">
			Sorry, but that is not a valid menu option.
		</p>
		<p class="spacer">&nbsp;</p>
		
		<?php
		break;
	}

}
	

/* finish off the page */
?>

			<!--  end of cell with right-hand content -->
		</td>
	</tr>
</table>
<?php

echo print_html_footer($helplink);

cqpweb_shutdown_environment();

exit();


/* ------------- *
 * END OF SCRIPT *
 * ------------- */
