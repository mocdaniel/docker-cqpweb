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



/*
 * ==============
 * USER FUNCTIONS
 * ==============
 */







/**
 * Converts a string username to an integer. If the argument is not
 * the name of a user account that exists, the program aborts.
 * 
 * @param  string $user  Username.
 * @return int           User ID number.
 */
function user_name_to_id($user)
{
	if (false === ($id = user_name_to_id_with_test($user)))
		exiterror("Invalid user name specified at database level: $user");
	return $id;
}

/**
 * Like user_name_to_id() without abort on failure. 
 * 
 * @param  string $user  Username.
 * @return int           User ID number, or false if nonexistent.
 */
function user_name_to_id_with_test($user)
{
	/* a VERY common use pattern will be for this function to be called again and again
	 * on the the same username (the logged in user - which will be the first username
	 * checked). So let's optimise for that case. */
	static $cached_user = NULL;
	static $cached_id = NULL;
	
	if (!is_null($cached_user))
		if ($user == $cached_user)
			return $cached_id;
	
	$user = escape_sql($user);

	$id = get_sql_value("select id from user_info where username = '$user'");
	
	if (is_null($cached_user) && false !== $id)
	{
		$cached_user = $user;
		$cached_id = $id;
	}
	return $id;
}


function user_id_to_name($id)
{
	$id = (int)$id;
// 	$result = do_sql_query("select username from user_info where id = $id");
// 	if (mysqli_num_rows($result) < 1)
// 		exiterror("Invalid user ID specified at database level: $id");
// 	list($name) = mysqli_fetch_row($result);
	
	if (false === ($name = get_sql_value("select username from user_info where id = $id")))
		exiterror("Invalid user ID specified at database level: $id");
	
	return $name;
}

/**
 * Gets a list of all the users that exist on the system.
 * @return array       Returns flat array of usernames, ordered alphabetically.
 */
function get_list_of_users()
{
	$result = do_sql_query("select username from user_info order by username asc");
	$u = array();
	while ($r = mysqli_fetch_row($result))
		$u[] = $r[0];
	return $u;
}

/**
 * Adds a new user with the specified username, password and email.
 * 
 */
function add_new_user($username, $password, $email, $initial_status = USER_STATUS_UNVERIFIED, $expiry_time = 0)
{
	global $Config;
	
	/* checks, e.g. on usernames containing non-word characters, must be performed at a higher level. 
	 * Here, we only check for database safety. */
	$username = escape_sql($username);
	$email = escape_sql($email);
	$expiry_time = (int)$expiry_time;
	
	/* no need to check password, since we create a passhash from the password & don't store it */
	$passhash = generate_new_hash_from_password($password);

	$sql = "INSERT INTO user_info (
		username,
		realname,
		email,
		passhash,
		acct_status,
		expiry_time,
		coll_statistic,
		coll_freqtogether,
		coll_freqalone,
		coll_from,
		coll_to,
		max_dbsize
		)
		VALUES
		(
		'$username',
		'unknown person',
		'$email',
		'$passhash',
		$initial_status,
		$expiry_time,
		{$Config->default_colloc_calc_stat},
		{$Config->default_colloc_minfreq},
		{$Config->default_colloc_minfreq},
		" . (-1 * ($Config->default_colloc_range-2)) . ",
		" . ($Config->default_colloc_range-2) . ",
		{$Config->default_max_dbsize}
		)"
		;
// 					conc_kwicview = DEFAULT,
// 					conc_corpus_order = DEFAULT,
// 					cqp_syntax = DEFAULT,   ##remove from setup
// 					css_monochrome = DEFAULT, 
// 					freqlist_altstyle = DEFAULT,
// 					linefeed = DEFAULT,
		
	do_sql_query($sql);
	
	/* check for automatic group membership */
	
	foreach (list_group_regexen() as $group => $regex)
		if (0 < preg_match("/$regex/", $email))
			add_user_to_group($username, $group);
}


/**
 * Automatically creates a batch of numbered users. Function available to admin users only.
 * 
 * @param string $username_root  Root of username to which numbers are added.
 * @param int $number_in_batch   N of accounts to create (usernames will be 1 through N)
 * @param string $password       Password for batch of accounts.
 * @param string $autogroup      If specified, users will automatically be added to the group with this name.
 */
function add_batch_of_users($username_root, $number_in_batch, $password, $autogroup = NULL)
{
	global $User;
	
	/* fail silently if the user is not admin. */
	if (!$User->is_admin())
		return;

	$autogroup = preg_replace('/\W/', '', $autogroup);
	if (! in_array($autogroup, get_list_of_groups()))
		$autogroup = NULL;
	$username_root = preg_replace('/\W/', '', $username_root);
	$number_in_batch = (int)$number_in_batch;
	
	for ($i = 1 ; $i <= $number_in_batch; $i++)
	{
		$u = "$username_root$i";
		add_new_user($u, $password, '');
		change_user_status($u, USER_STATUS_ACTIVE);

		if (!empty($autogroup))
			add_user_to_group($u, $autogroup);
	}
}





/**
 * Add a whole number of users.
 * 
 * The argument is a matrix (array of arrays).
 * 
 * Each inner array has:
 *    0 => username
 *    1 => password
 *    2 => email
 * 
 */
function add_multiple_users($data_matrix)
{
	foreach($data_matrix as $arr)
		add_new_user($arr[0], $arr[1], $arr[2]);
}



/**
 * Deletes a specified user account (and all its saved and categorised queries)
 * 
 * If the username passed in is an empty string,
 * it will return without doing anything; all non-word
 * characters are removed for database safety.
 */
function delete_user($user)
{
	global $User;
	
	if (!$User->is_admin())
		return;
	
	/* db sanitise */
	$user = preg_replace('/\W/', '', $user);
	if (empty($user))
		return;
	
	/* refuse to delete admin users. */
	if (user_is_superuser($user))
		return;
	
	$id = user_name_to_id($user);
	
	/* unjoin all groups */
	do_sql_query("delete from user_memberships where user_id = $id");

	/* delete user privilege grants */
	do_sql_query("delete from user_grants_to_users where user_id = $id");
	
	/* delete uploaded files (and directory) */
	delete_user_upload_area($user);
	
	/* and their user corproa, if any */
	delete_user_installed_corpora($user);
	
	/* and the place we stored their weblinks */
	delete_user_corpus_web_directory($user);
	
	/* delete user saved queries and categorised queries */
	$result = do_sql_query("select query_name, saved from saved_queries where saved != ".CACHE_STATUS_UNSAVED." and user = '$user'");
	while (($q = mysqli_fetch_object($result)))
	{
		if ($q->saved == CACHE_STATUS_CATEGORISED)
		{
			/* catquery */
			$dbname = get_sql_value("select dbname from saved_catqueries where catquery_name='{$q->query_name}'");
			do_sql_query("drop table if exists $dbname");
			do_sql_query("delete from saved_catqueries where catquery_name='{$q->query_name}'");
		}
		/* for both catquery and saved query */
		delete_cached_query($q->query_name);
	}

	/* delete user itself */
	do_sql_query("delete from user_info where id = $id");
}


/**
 * Delete a given user upload area inc. all its content.
 * @param string $username    Username: person whose area is to be deleted.
 */
function delete_user_upload_area($username)
{
	$d = get_user_upload_area($username);
	if (is_dir($d))
		recursive_delete_directory($d);
}


/**
 * Deletes a user account IF it has unverified status.
 * Otherwise, does nothing.
 *
 * @param string $user
 */
function delete_unverified_user($user)
{
	global $User;
	
	if ($User->is_admin())
		if ($info = get_user_info($user))
			if (USER_STATUS_UNVERIFIED == $info->acct_status)
				delete_user($user);
}

/**
 * Touch the specified user's last_seen_time....
 */
function touch_user($username)
{
	$username = escape_sql($username);
	do_sql_query("update user_info set last_seen_time = CURRENT_TIMESTAMP where username='$username'");
}


/**
 * Changes the specified user's password.
 * 
 * Note that on a change of password, a log out should alos be forced: but this funciton doesn't do that,
 * caller should use another funciton afterwards to make that happen. 
 * 
 * @param string $user          Username.
 * @param string $new_password  New password.
 */
function update_user_password($user, $new_password)
{
	$user = escape_sql($user);

	if (empty($new_password))
		exiterror("Cannot set password to empty string!");
	$new_passhash = generate_new_hash_from_password($new_password);

	do_sql_query("update user_info set passhash = '$new_passhash' where username = '$user'");
}

function lock_user_password($user_id)
{
	$user_id = (int) $user_id;
	do_sql_query("update user_info set password_locked = 1 where id=$user_id");
}

function unlock_user_password($user_id)
{
	$user_id = (int) $user_id;
	do_sql_query("update user_info set password_locked = 0 where id=$user_id");
}

function user_password_is_locked($username)
{
	$user_id = user_name_to_id_with_test($username);
	return (bool)get_sql_value("select password_locked from user_info where id=$user_id");
}



/**
 * Uses the details within a user database-object to render a name
 * and email suitable for use within an email body and header respectively.
 * 
 * Usage: list($realname, $address) = render_user_name_and_email($object);
 * 
 * @param stdClass $user_object  A DB object (members used: realname, email).
 * @return array                 An array with first member printable name,
 *                               second member email (either raw address or "Name <address>").
 */
function render_user_name_and_email($user_object)
{
	if (empty($user_object->realname) || $user_object->realname == 'unknown person')
	{
		$realname = 'User';
		$user_address = $user_object->email;
	}
	else
	{
		$realname = $user_object->realname;
		$user_address = "$realname <{$user_object->email}>";
	}
	
	return array($realname, $user_address);
}


/**
 * Sends out an account verification email,
 * with a freshly-generated verification key.
 * 
 * The verification key goes into the db.
 */
function send_user_verification_email($user)
{
	/* create key and set in database */
	$verify_key = set_user_verification_key($user);
	
	list($realname, $user_address) 
		= render_user_name_and_email(mysqli_fetch_object(do_sql_query("select email, realname from user_info where username='$user'")));

	$verify_url = url_absolutify('../usr/useracct-act.php?userAction=verifyUser&v=' . urlencode($verify_key) );
	
	$body = <<<HERE
Dear $realname,

A new user account has been created on our CQPweb server in
association with your email address.

To validate this new account, and confirm as yours the address to
which this email was sent, please visit the following link:

$verify_url

If your email client disables external links, copy and paste 
the address above into a web browser.

If CQPweb cannot read your verification code successfully
from the link, it will ask you for a verification key. 
In that case, copy-and-paste the following 32-letter code:

$verify_key 

If you DID NOT create this account, or request it to be created
on your behalf, then all you need to do is ignore this email; 
the account will then never be activated.

Yours sincerely,

The CQPweb User Administration System


HERE;
	
	send_cqpweb_email($user_address, 'CQPweb: please verify user account creation!', $body);
}

/**
 * Sets a key to verify either an account or a password reset.
 * 
 * Creates and returns a 32-byte key, which is also stored in the DB for the 
 * specified user.
 * 
 * Note this function does not check for the reality of the user -
 * if a nonexistent user is specified, then the result will be no change
 * to the DB, and the key returned will be useless.
 */
function set_user_verification_key($user)
{
	$user = escape_sql($user);
	
	$key = md5(uniqid($user,true));
	/* use an additional round of md5 -- less security than for password, but for a one-use token that's OK */
	$stored_key = md5($key);

	do_sql_query("update user_info set verify_key = '$stored_key' where username = '$user'");
	
	return $key;
}

/**
 * Removes a user verification key for the given user, setting the entry in the DB to NULL.
 */
function unset_user_verification_key($user)
{
	$user = escape_sql($user);
	
	do_sql_query("update user_info set verify_key = NULL where username = '$user'");
}

/**
 * Gets the username associated with a given verification key, if one exists. 
 * 
 * If the key does not exist, returns false.
 */
function resolve_user_verification_key($key)
{
	// TODO: there should be a user_info.verify_key_expiry_time field which 
	// is checked before any resoltuion:
	// update "update user_info set verify_key = NULL where verify_key_expiry_time < " . (the time now)
	// and update teh texts of the emails to say "this link will expire/code will expire in ... " 
	// (say, 30 mins, 45 mins? 
	$key = md5($key);

	return get_sql_value("select username from user_info where verify_key = '$key'");
		
}

/**
 * Resets the user account status: 2nd arg must be one of the USER_STATUS_* constants.
 */
function change_user_status($user, $new_status)
{
	$new_status = (int)$new_status;
	$user = escape_sql($user);
	
	/* do nothing if new status not a valid status constant */
	switch ($new_status)
	{
	case USER_STATUS_UNVERIFIED:
	case USER_STATUS_ACTIVE:
	case USER_STATUS_SUSPENDED:
		do_sql_query("update user_info set acct_status = $new_status where username = '$user'");
		break;
	default:
		/* do nothing */
		break;
	}
}


/** Wrapper for the two steps involved in verifying a user account */
function verify_user_account($username)
{
	/* first, change user status; second, remove the verifcation key set for them. */
	change_user_status($username, USER_STATUS_ACTIVE);
	unset_user_verification_key($username);
}



/**
 * Retrieves a given setting for a particular user.
 * 
 * Note that it's not necessary for the user to be the same person
 * as the user logged-on in the environment (global $User).
 */
function get_user_setting($username, $field)
{
	$o = get_user_info($username);
	if (empty($o))
		return false;
	else
		return $o->$field;
}

/** 
 * Returns an object (stdClass with members corresponding to the
 * fields of the user_info table in the database) containing
 * the specified user's data.
 * 
 * Returns false in case of a nonexistent user.
 * 
 * Note that it's not necessary for the user to be the same person
 * as the user logged-on in the environment (global $User).

 * @param  string $username  Username to look up.
 * @return stdClass          User object, or false in case specified user doesn't exist. 
 */
function get_user_info($username)
{
	static $cache = array();
	
	if (isset($cache[$username]))
		return $cache[$username];
	
	$username = escape_sql($username);
	
	$result = do_sql_query("SELECT * from user_info WHERE username = '$username'");
	
	if (0 == mysqli_num_rows($result))
		return false;
	else
		return ($cache[$username] = mysqli_fetch_object($result));
}

/**
 * 
 * @param  int $user_id   User ID code (or, string representation o the integer).
 * @return stdClass       User object, or false in case specified user doesn't exist.
 */
function get_user_info_by_id($user_id)
{
	return get_sql_object("SELECT * from user_info WHERE id = ". (int) $user_id);
}

/**
 * Allows either a "username" or integer "userID" parameter in GET to be used to generate
 * a user info object. Aborts the program if the user does not exist, or if neither parameter is present.
 * 
 * @return object   User object; program aborts if the user does not exist.
 */
function safe_user_info_from_get()
{
	if (isset($_GET['username']))
		$user = get_user_info($_GET['username']);
	else if (!isset($_GET['userID']))
			$user = false;
	else 
		$user = get_user_info_by_id((int)$_GET['userID']);

	if (false === $user)
		exiterror("This call to CQPweb included an invalid or missing username or user ID.");
	return $user;
}


/**
 * Gets full info for all users.
 * 
 * Returns array of objects of the kind returned by get_user_info.
 * 
 * The keys are the user ids.
 */
function get_all_users_info()
{
	$users = array();
	
	$result = do_sql_query("select * from user_info");
	
	while ($o = mysqli_fetch_object($result))
		$users[$o->id] = $o;
	
	return $users;
}



/** 
 * Update a user setting (interface preferences only, not logon info).
 */
function update_user_setting($username, $field, $setting)
{
	/* instead of using escape_sql() for the field, we go through this switch instead. */
	switch($field)
	{
	/* STRING fields */
	case 'realname':
	case 'affiliation':
	case 'country':
	case 'linefeed':
		$new_val_sql = '\'' . escape_sql($setting) . '\'';
		break;

	/* BOOLEAN fields */
	case 'conc_kwicview':
	case 'conc_corpus_order':
	case 'cqp_syntax':
	case 'context_with_tags':
	case 'use_tooltips':
	case 'css_monochrome':
	case 'thin_default_reproducible':
	case 'freqlist_altstyle':
		$new_val_sql = ($setting ? '1' : '0');
		break;

	/* INTEGER fields (incl. integers representing constants) */
	case 'coll_statistic':
	case 'coll_freqtogether':
	case 'coll_freqalone':
	case 'coll_from':
	case 'coll_to':
	case 'max_dbsize':
		$new_val_sql = (int) $setting;
		break;

	default:
		exiterror('Update operation: not a valid setting.');
	}
	
	$username = escape_sql($username);
	
	do_sql_query("UPDATE user_info SET $field = $new_val_sql WHERE username = '$username'");
}

/**
 * Return all user settings to initial values.
 * 
 * NOTE this will need updating any time a new UI setting is added. 
 */
function reset_all_user_settings($username)
{
	global $Config;
	$username = escape_sql($username);
	$sql = "update user_info set 
					conc_kwicview = DEFAULT,
					conc_corpus_order = DEFAULT,
					cqp_syntax = DEFAULT,   ##remove from setup
					context_with_tags = DEFAULT,   ##remove from setup
					use_tooltips = DEFAULT,
					thin_default_reproducible = DEFAULT,
					css_monochrome = DEFAULT, 
					freqlist_altstyle = DEFAULT,
					linefeed = DEFAULT,

					coll_statistic = {$Config->default_colloc_calc_stat},
					coll_freqtogether = {$Config->default_colloc_minfreq},
					coll_freqalone = {$Config->default_colloc_minfreq},
					coll_from = " . (-1 * ($Config->default_colloc_range-2)) . ",
					coll_to = " . ($Config->default_colloc_range-2) . ",
					max_dbsize = {$Config->default_max_dbsize}
				where username = '$username'";
	do_sql_query($sql);
}



/** 
 * Update many user-interface settings all at once.
 * 
 * @param string $username  Username of account to update.
 * @param array  $settings  Associative field=>value array of settings to update. 
 */
function update_multiple_user_settings($username, $settings)
{
	foreach ($settings as $field => $value)
		update_user_setting($username, $field, $value);
}






// function get_user_linefeed($username)
// {
// 	$current = get_user_setting($username, 'linefeed');

// 	if (empty($current) || $current = 'au')
// 		$current = guess_user_linefeed($username);
	
// 	return strtr($current, "da", "\r\n");
// }



function guess_user_linefeed($user_to_guess)
{
	global $User;
	
	if ($user_to_guess != $User->username)
		return 'da';
	/* da is the default guess when no guess can be made, because Windows dominates the OS market.
	 * and *nix people are more likely to be computer literate enough to fix it ;-P */
	
	/* Otherwise, guess based on active user. 
	 * a and d are symbols, of course, for \n and \r respectively. */
	if (array_key_exists('HTTP_USER_AGENT', $_SERVER) )
	{
		/* we have a user agent to guess from... */
		if (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'Windows') )
			return 'da';
		else if (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'Macintosh')   || 
				 false !== strpos($_SERVER['HTTP_USER_AGENT'], 'Mac_PowerPC')  )
		{
			if (false !== strpos($_SERVER['HTTP_USER_AGENT'], 'OS X') )
				return 'a';           /* cos OS X is like *nix */
			else
				return 'd';           /* cos old Macs aren't */
		}
		else /* unix or linux prolly */
			return 'a';

	}
	else /* guess windows */
		return 'da';
}



/*
 * =======================
 * LOGON-RELATED FUNCTIONS
 * =======================
 */


/**
 * For creating new passwords. Returns the hash to store in the database.
 */
function generate_new_hash_from_password($password)
{
	global $Config;
	
	/* we are using BLOWFISH with 2^11 (by default!) iterations, so start of salt always same: */
// 	$salt = '$2a$' . $Config->blowfish_cost . '$';
	
	/* NOTES ON HASH ALGORITHM:
	 * 
	 * (1) $2a may produce buggy behaviour pre PHP v5.3.7 that it does not produce after.
	 *     PHP after 5.3.7 has alternatives $2x and $2y which allow different approaches to
	 *     backward compatibility. http://www.php.net/security/crypt_blowfish.php
	 *     (x = keep buggy mode; y = don't use countermeasures against old, buggy hashes.)
	 *     But in the case of CQPweb neither of these seems necessary. So we use $2a, IE
	 *     in PHP < 5.3.7 get very-rarely buggy hashes (unavoidable), and
	 *     in PHP > 5.3.7 get correct behaviour, with anti-old-hash countermeasures. 
	 *     >> CHANGED in v 3.2.34 when we switched to using 2y, the defaykt used by
	 *     >> password_hash().
	 * 
	 * (2) Blowfish cost parameter increased from 10 to 11 in v3.2 according to updated best advice.
	 */

	/* get 22 (pseudo-)random bytes from the alphabet that BLOWFISH expects */
// 	$salt_language = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
//	$randfunc = get_security_rand_function();
// 	for ($i = 0; $i < 22 ; $i++)
// 		$salt .= $salt_language[random_int(0,63)];

// 	return crypt($password, $salt);
	
	return password_hash($password, PASSWORD_BCRYPT, ['cost'=>$Config->blowfish_cost]);
}

/**
 * Performs a timing on 50 password hashes (to assess performance on a given system).
 * 
 * @param  int   $iter   Number of iterations to use (overriding 50).
 * @param  bool  $shout  If true, a message about the results will be printed
 *                       (mostly useful on the CLI). 
 * @return float         Average time per hash op, in milliseconds
 */
function stresstest_hash_from_password($iter = 50, $shout = false)
{
	global $Config;
	
	$tests = array();
// 	$salt_language = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	/* test 8 byte ASCII-only random passswords. */ 
	$pwd_min = 32;
	$pwd_max = 126;
	$pwd_len = 8;
	
//	$randfunc = get_security_rand_function();
	
	for ($i = 0 ; $i < $iter ; $i++)
	{
// 		$rand_salt = '$2a$' . $Config->blowfish_cost . '$';
// 		for ($j = 0; $j < 22 ; $j++)
// 			$rand_salt .= $salt_language[random_int(0,63)];
		$rand_pwd = '';
		for ($j = 0; $j < $pwd_len ; $j++)
			$rand_pwd .= chr(random_int($pwd_min, $pwd_max));
// 		$tests[$rand_pwd] = $rand_salt;
		$tests[] = $rand_pwd;
	}
	
	$begin = microtime(true);
	
	foreach($tests as $pw)
// 		$x = crypt($pw, $salt);
		generate_new_hash_from_password($pw);
	
	/* divide total time taken by N of iterations, then * 1000 for seconds to milliseconds
	 * (cos float-mode microtime returns an N of seconds). */
	
	$time = 1000.0 * ( (microtime(true) - $begin ) / (float)$iter );

	/* This is just to do something with $x, so we know the variable is REALLY created above. */
// 	$x .= "_{$x}_";

	if ($shout)
		echo "With cost = {$Config->blowfish_cost} and iterations = $iter, time per password was ", number_format($time, 5), " milliseconds\n";
	
	return $time;
}


/**
 * Check a username / password combo against the passhash held in the database.
 * 
 * Returns a database record for the user (as an object),  
 * if the user account exists and the password matches its hash.
 *  
 * Otherwise returns false.
 */
function check_user_password($username, $password)
{
	global $Config;
	
	if (!($u = get_user_info($username)))
		return false;

	if (password_verify($password, $u->passhash))
	{
		if (password_needs_rehash($u->passhash, PASSWORD_BCRYPT, ['cost'=>$Config->blowfish_cost]))
		{
			$u->passhash = generate_new_hash_from_password($password);
			$hash = escape_sql($u->passhash);
			do_sql_query("update user_info set passhash = '$hash' where id = {$u->id}");
		}
		return $u;
	}
	else 
		return false;
	
// 	/* in PHP 5.6.0+ use the timing-attack secure comparison function */
// 	if (0 >= version_compare('5.6.0', PHP_VERSION))
// 	{
// 		if (hash_equals($obj->passhash, $newhash))
// 			return $obj;
// 	}
// 	else
// 	{
// 		if ($obj->passhash == $newhash)
// 			return $obj;
// 	}
// 	return false;
// now we assume php 7, so the above isn't relevant any longer. 
}



/**
 * Checks whether a given cookie token is for a valid login.
 * 
 * Returns an object if it is, and returns false if it isn't.
 * The object contains 2 fields: username, creation.
 * 
 * @param  string   $token  The cookie token to be checked.
 * @return stdClass         Object with 2 members; boolean false if the 
 */
function check_user_cookie_token($token)
{
	list ($username, $content) = split_up_cookie_token($token);
	if (empty($content))
		return false;
	$u_id = user_name_to_id($username);
	$hash = hash('sha256', $token);
	
	if (!($creation = get_sql_value("select creation from user_cookie_tokens where token = '$hash' and user_id = $u_id")))
		return false;
	
// 	return (object) array('username' => user_id_to_name($u_id), 'creation'=> (int)$creation);
	return (object) array('username' => $username, 'creation'=> (int)$creation);
}

/**
 * Creates a new pseudorandom string of letters and numbers (32 chars in length),
 * which is appended to username + separator (:) to form a token.
 */
function generate_new_cookie_token($username)
{
	$token_language = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_~|';
	
	$token = $username . ':';
	for ($i = 0; $i < 100 ; $i++)
		$token .= $token_language[random_int(0,64)];
	/* 
	 * Note a possible race condition: once a token is generated, 
	 * the same token could be generated by another process from 
	 * the same user *before* the first goes into the DB.
	 * But this is a tiny chance, and even if it happens, nothing bad results.
	 */
	
	return $token;
}

/**
 * Cookie tokens are generated as "$username:$random_text" 
 * (the hash of the whole thing being what is actually stored in the "token"
 * column of the database table.) 
 *
 * This function breaks up such a token
 * and returns the two pieces as an array (0=>username, 1=>random text).
 *
 * If the random text is not valid (32 characters from the cookie-token alphabet),
 * or if the divisor ":" is not present, then it returns ['',''].
 */
function split_up_cookie_token($token)
{
	/* note: this MUST be the same as in the generate-function. */
	$token_language = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_~|';

	if (!preg_match("/^(\w+):([$token_language]{100})$/", $token, $m))
		return array("", "");
	else
		return array($m[1], $m[2]);
}


/**
 * Creates a DB entry for the specified cookie token,
 * signifiying a login of the username given.
 */
function register_new_cookie_token($token)
{
	global $Config;

	/* we store the SHA-256 hash of the username + ':' + token combination. */
	list ($username, $content) = split_up_cookie_token($token);
	if (!empty($content))
	{
		$u_id = user_name_to_id($username);
		$hash = hash('sha256', $token);
		$ctime = time();
		$expiry = $ctime + $Config->cqpweb_cookie_max_persist;
		do_sql_query("insert into user_cookie_tokens (token, user_id, expiry, creation) values ('$hash', $u_id, $expiry, $ctime)");

		/* every time a new token is registered, run cleanup */
		cleanup_expired_cookie_tokens();
	}
	/* we do not register if, for whatever reason, we were passed a bad token. */
}


/**
 * Resets the expiry time of a cookie token to the maximum
 * logon persist time in the future.
 */
function touch_cookie_token($token)
{
	global $Config;
	$expiry = time() + $Config->cqpweb_cookie_max_persist;

	list ($username) = split_up_cookie_token($token);
	$u_id = user_name_to_id($username);
	$hash = hash('sha256', $token);

	do_sql_query("update user_cookie_tokens set expiry = $expiry where token = '$hash' and user_id = $u_id");
}


/**
 * Deletes all cookie tokens whose expiry time is in the past.
 */
function cleanup_expired_cookie_tokens()
{
	do_sql_query("delete from user_cookie_tokens where expiry < " . time() );
}

/**
 * Deletes a specified cookie token.
 */
function delete_cookie_token($token)
{
	list($username, $content) = split_up_cookie_token($token);
	/* note that if ["",""] was returned, delete will fail. Ergo, the following. */
	if (!empty($content))
	{
		$u_id = user_name_to_id($username);
		$hash = hash('sha256', $token);
		do_sql_query("delete from user_cookie_tokens where token = '$hash' and user_id = $u_id");
	}
}

/**
 * Invalidates (deletes) all current tokens belonging to a specified user.
 * This has the effect of forcing log out from all browser sessions where they
 * are currently logged in.
 * 
 * It's possible to specify a single exception: a token not to be deleted.
 * This allows just one login to persist (e.g. if the change of password
 * has been done *from that login*).
 * 
 * @param string $username      Username. 
 * @param string $except_token  Full token (username:content format) to preserve.
 *                              If unspecified, all sessions are logged out.
 */
function invalidate_user_cookie_tokens($username, $except_token = false)
{
	$u_id = user_name_to_id($username);
	
	$sql = "delete from user_cookie_tokens where user_id = $u_id";
	
	if (! empty($except_token))
	{
		$hash = hash('sha256', $except_token);
		$sql .= " and token != '$hash'"; 
	}
	
	do_sql_query($sql);
}


/**
 * Deletes all log-in tokens. Equivalent to forcing a logout of
 * all users currently logged in, everywhere.
 */
function invalidate_all_cookie_tokens()
{
	do_sql_query("delete from user_cookie_tokens");
}


/**
 * Does all necessary steps to generate and emit a cookie token for the currently-logged in user.
 *
 * @param string $username  Username to emit the cookie for.
 * @param bool   $persist   Persistent cookie = true; this browser session only = false.
 */
function emit_new_cookie_token($username, $persist)
{
	global $Config;

	$token = generate_new_cookie_token($username);
	register_new_cookie_token($token);

	/* how long before timeout? either 1 year (i.e. forever given that tokens expire before that at the server end), or till browser closed */
	$browser_timeout = ($persist ? (time()+(365*24*60*60)) : 0);

	setcookie($Config->cqpweb_cookie_name, $token, $browser_timeout, '/');
	setcookie($Config->cqpweb_cookie_name . 'Persist', ($persist ? '1' : '0'), $browser_timeout, '/');
	
	if ($Config->Api)
		$Config->Api->set_outgoing_cookie_token($token);
}





/*
 * ====================
 * USER GROUP FUNCTIONS
 * ====================
 */




function group_name_to_id($group)
{
	$group = escape_sql($group);
	if ( false === ($id = get_sql_value("select id from user_groups where group_name = '$group'")) )
		exiterror("Invalid group name specified at database level: $group");
	return $id;
}

function group_id_to_name($id)
{
	$id = (int)$id;
	$result = do_sql_query("select group_name from user_groups where id = $id");
	if (mysqli_num_rows($result) < 1)
		exiterror("Invalid group ID specified at database level: $id");
	list($name) = mysqli_fetch_row($result);
	return $name;
}


/**
 * Create a new group with the specified name (description and autojoin-regex can also
 * be set at creation time).
 */
function add_new_group($group, $description = '', $regex = '')
{
	$group = cqpweb_handle_enforce($group);
	if (empty($group))
		exiterror("You cannot create a group with no name!");
	
	if (0 !== get_sql_value("select count(*) from user_groups where group_name = '$group'"))
		exiterror("You tried to create a group which already exists!"); 

	$description = escape_sql($description);
	$regex = escape_sql($regex);

	do_sql_query("insert into user_groups (group_name,description,autojoin_regex) values ('$group','$description','$regex')");
}



/**
 * Deletes a user group.
 * 
 * @param string $group  Name of the group to delete.
 */
function delete_group($group)
{
	assert_not_reserved_group($group);

	$g = group_name_to_id($group);

	/* delete all memberships */
	do_sql_query("delete from user_memberships where group_id = $g");

	/* delete group privilege grants */
	do_sql_query("delete from user_grants_to_groups where group_id = $g");

	/* delete group */
	do_sql_query("delete from user_groups where id = $g");
}


/**
 * Assertion: causes an error abort if this group is one of the "reserved" 
 * group names (i.e. magic, can't be deleted from the database etc.)
 * 
 * @param string $group  The group name to check.
 */
function assert_not_reserved_group($group)
{
	if ($group == 'superusers' || $group == 'everybody')
		exiterror("An illegal operation was attempted on one of the system-reserved groups, namely [$group].");
}

/**
 * Returns flat array of group names (ordered alphabetically, but with superusers and everybody first).
 * 
 * If the function is passed a username, then only the groups of which that user is a member will be returned. 
 * 
 * @param  string $check_user  Optional: if supplied then ity is a username which limits the list of groups returned.
 * @return array               Flat array of group names.
 */
function get_list_of_groups($check_user = NULL)
{
	if (empty($check_user))
	{
		$result = do_sql_query("select group_name from user_groups order by group_name asc");
		$g = array('superusers','everybody');
	}
	else
	{
		$u = user_name_to_id($check_user);
		$sql = "select group_name from user_memberships inner join user_groups on id = group_id where user_id = $u order by group_name asc";
		$result = do_sql_query($sql);
		$g = ( user_is_superuser($check_user) ? array('superusers','everybody') : array('everybody') );
	}

	while ($o = mysqli_fetch_object($result))
		if ( ! ($o->group_name == 'superusers' ||$o->group_name  == 'everybody') )
			$g[] = $o->group_name;

	return $g;
}




/**
 * Lists the usernames of the users who are in the specified group.
 * 
 * @param  string $group  Name of the group to list.
 * @return array          Returns flat array of usernames.
 */
function list_users_in_group($group)
{
	/* specials: user membership not recorded in user_memberships table */
	if ($group == 'superusers')
		return list_superusers();
	else if ($group == 'everybody')
		$sql = "select username from user_info";
	else
	{
		$g = group_name_to_id($group);
		$sql = "select user_info.username from user_memberships inner join user_info on user_info.id = user_memberships.user_id where group_id = $g";
	}
	
	$result = do_sql_query($sql);

	$users = array();
	
	while ($r = mysqli_fetch_row($result))
		$users[] = $r[0];
	
	return $users;
}

/**
 * Gets a database object for a given group.
 * 
 * @param  string $group  Name of the group to look up. 
 * @return object         Database object containing all information on the group.
 */
function get_group_info($group)
{
	$group = escape_sql($group);
	$result = do_sql_query("select * from user_groups where group_name = $group");
	if (1 > mysqli_num_rows($result))
		exiterror("Info requested for non-existent group $group!");
	return mysqli_fetch_object($result);
}

/**
 * Returns array of group DB objects, ordered alphabetically, but with superusers and everybody first.
 */
function get_all_groups_info()
{
	$result = do_sql_query("select * from user_groups order by group_name asc");
	$all = array();
	while ($o = mysqli_fetch_object($result))
	{
		if ($o->group_name == 'everybody' )
			$everybody = $o;
		else if ($o->group_name == 'superusers')
			$superusers = $o;
		else
			$all[] = $o;
	}
	array_unshift($all, $superusers, $everybody);
	return $all;
}

/**
 * Set new values for group description and/or regex 
 */
function update_group_info($group, $new_description, $new_regex)
{
	assert_not_reserved_group($group);
	$group = escape_sql($group);
	$new_description = escape_sql($new_description);
	$new_regex = escape_sql($new_regex);
	do_sql_query("update user_groups set description = '$new_description', autojoin_regex = '$new_regex' where group_name = '$group'");
}

/**
 * Add the usernmae given to the membership of the named group.
 * If the user is already a member of the group, does nothing.
 * 
 * @param string $user          Account username.
 * @param string $group         Name of group to add the user to. 
 * @param int    $expiry_time   Not used yet. TODO sort this out. 
 */
function add_user_to_group($user, $group, $expiry_time = 0)
{
	/* do not add user to group if user is already a member */
	if (user_is_group_member($user, $group))
		return;
	assert_not_reserved_group($group);
	$g = group_name_to_id($group);
	$u = user_name_to_id($user);
	$expiry_time = (int) $expiry_time;
	do_sql_query("insert into user_memberships (user_id,group_id,expiry_time)values($u,$g,$expiry_time)");
}

/**
 * Remove the user with the given username from the named group.
 * If the user is not a member of the group, has no effect.
 * 
 * @param string $user          Account username.
 * @param string $group         Name of group to remove the user from.
 */ 
function remove_user_from_group($user, $group)
{
	assert_not_reserved_group($group);
	$g = group_name_to_id($group);
	$u = user_name_to_id($user);
	do_sql_query("delete from user_memberships where group_id = $g and user_id = $u");
}

/**
 * Check whether a user is a member of a given group.
 * 
 * @param  string $user    A username.
 * @param  string $group   A group name.
 * @return bool            True iff the user is a member of the group.
 */
function user_is_group_member($user, $group)
{
	switch ($group)
	{
	case 'everybody':
		return true;
	case 'superusers':
		return user_is_superuser($user);
	default:
		$g = group_name_to_id($group);
		$u = user_name_to_id($user);
		return (0 < mysqli_num_rows(do_sql_query("select * from user_memberships where user_id = $u and group_id = $g")));
	}
}

/**
 * Returns associative array of groupname => group_autojoin_regex
 */
function list_group_regexen()
{
	$result = do_sql_query("select group_name, autojoin_regex from user_groups");
	$list = array();
	while ($o = mysqli_fetch_object($result))
	{
		if (empty($o->autojoin_regex))
			continue;
		else
			$list[$o->group_name] = $o->autojoin_regex;
	}
	return $list;
}

/**
 * Run group regex against all existing users; if their email address mathces the regex, 
 * they are added to the group.
 * 
 * (Note this is the only way for regexes to take effect retroactively; otherwise regexen
 * only run at account create-time.)
 */
function reapply_group_regex($group)
{
	$group = escape_sql($group);
	
	$rx = get_sql_value("select autojoin_regex from user_groups where group_name = '$group'");
	
	if (!empty($rx)) 
		apply_custom_group_regex($group, $rx);
}


/**
 * Run an arbitrary regex acropss all users' emails, and add them to the group specified
 * if their email address matches.
 */
function apply_custom_group_regex($group, $regex)
{
	foreach(get_all_users_info() as $u)
		if (preg_match("/$regex/", $u->email))
			add_user_to_group($u->username, $group);
}


/*

	THE CODING OF PRIVILEGES
	========================
	
	Privileges are coded as follows.
	
	The *owner* or *subject* of the privilege is not stored in the privilege table.
	Instead, that info is stored in the "_grants" tables.
	
	The privilege table consists of "verbs" and "objects".
	
	The "verb" consists of an integer constant explaining what kind of access privilege
	this is. The "object" is a scope object whose layout may be complex or simplex
	depending on what type of privielege is involved.

	All "object" arrays are encodable as strings, which are what is stored in the
	database in the `scope` field. 
	
	Single values (e.g. integers) are stringified as-is. Arrays are imploded using
	some delimiter. Associative arrays (whose members may themselves be complex) 
	are set up as stdClass and inserted as JSON.
	
	The "verb" is stored in the `type` field of the database using the correct constant.

	The following explains the nature, and subcategorisation frame template,
	of each type of privilege.
	
	
	Privileges of type PRIVILEGE_TYPE_CORPUS_FULL
	---------------------------------------------
	
	This privilege represents the level of access a user can have when it is assumed that
	they have full rights to access the underlying text of a particular corpus.
	
	- Concordances WILL NOT be auto-thinned.
	- User can access the "Context" feature with the maximum possible scope.
	- User can access the "Browse Text" feature (anyway they will be able to, once it is implemented).
	
	
	Privileges of type PRIVILEGE_TYPE_CORPUS_NORMAL
	-----------------------------------------------
	
	This privilege represents a normal level of access a user can have when it is assumed that
	they have normal privileges to use a particular corpus.
	
	- Concordances WILL NOT be auto-thinned.
	- User can access the "Context" feature with a configurable scope.
	- User cannot access the "Browse Text" feature.
	
	This is equivalent to the level of access that any user had in CQPweb v less than 3.1.
	
	
	Privileges of type PRIVILEGE_TYPE_CORPUS_RESTRICTED
	---------------------------------------------------
	
	This privilege represents the level of access a user can have when it is assumed that
	they can only be allowed restricted access to a particular corpus.
	
	- Concordances WILL be auto-thinned to a configurable maximum number of hits (random, reproducible)
	- User can access the "Context" feature with a configurable scope (less than that for normal privilege).
	- User cannot access the "Browse Text" feature.
	
	
	Syntax for PRIVILEGE_TYPE_CORPUS_*
	----------------------------------
	
	The "object" of these privileges is a set of corpora which must contain at least one corpus.
	
	The data-object representation of this is an array of strings, where each string is an array of
	corpus handles.
	
	The string representation of this in the DB is the strings in question concatenated together and
	separated by ~ .
	
	
	Privileges of type PRIVILEGE_TYPE_FREQLIST_CREATE
	-------------------------------------------------
	
	This privilege enables a user to build frequency lists up to a certain size (while working
	within any corpus).
	
	This applies currently only to subcorpus frequency list creation; in future, it might apply
	to collocation frequency list creation as well (the latter currently keys off a pair
	of global variables set via the configuration file).
	
	The "object" of this kind of privilege is an integer. Its string representation is that integer,
	as a string. (Simples!)
	
	
	Privileges of type PRIVILEGE_TYPE_UPLOAD_FILE
	---------------------------------------------
	
	This privilege enables a user to upload a file (of ANY kind, for ANY reason: subsystems may
	add their own specific restrictions on files, but this privilege governs all file uploads).
	
	This privilege covers the ability to move a file of a given size out of temporary HTTP and
	into CQPweb's storage (whether it persists there is a sseparate issue!). A privilege of this
	sort applies to a particular number of bytes (its scope object) which = the maximum size of
	files that can be uploaded. Admin users do not have this restriction. 
	
	The "object" of this kind of privilege is an integer. Its string representation is that integer,
	as a string (just like for FREQLIST_CREATE).
	
	
	Privileges of type PRIVILEGE_TYPE_CQP_BINARY_FILE
	-------------------------------------------------
	
	This privilege activates access to the user's binary files in the CQP cache.
	
	The main purpose of this privilege is to allow users with very big saved queries to move them
	off the server for cold-storage, freeing up cache space, for potential re-upload later.
	
	(NB!!! as of v 3.2.32, the relevant functions are only semi-implemented.... TODO)
	
	It's for experts only; admin users always have use of binary files. 
	
	The "object" of this kind of privilege is always an empty string; its string representation
	is likewise, always just a string. So pribvileges of this typoe will only differ by their
	description. There is not, actually, any point having more than the one that is part of the
	default non-corpus privileges.
	
	The space limit involved in binary insertion stems from the upload file privilege.


	Privileges of type PRIVILEGE_TYPE_EXTRA_RUNTIME
	-----------------------------------------------
	
	This privilege adds a given number of seconds to PHP's maximum runtime. Basically, it allows
	the user to run bigger processes than CQPweb would otherwise be capable of.
	
	The "object" of this kind of privilege is an integer. Its string representation is that integer,
	as a string (just like for FREQLIST_CREATE).

	
	Privileges of type PRIVILEGE_TYPE_DISK_FOR_UPLOADS
	--------------------------------------------------
	
	This privileges define how much space the user is allowed to take up in the upload-area.
	Uploads are not allowed if they would push the user past their filestore limit. 
	
	The "object" of this kind of privilege is an integer. Its string representation is that integer,
	as a string (just like for FREQLIST_CREATE).

	
	Privileges of type PRIVILEGE_TYPE_INSTALL_CORPUS
	------------------------------------------------
	
	This privilege allows a user to install their own corpus, using a specfied plugin,
	up to a specified size (in tokens).
	
	The scope object of this kind of privilege is actuually an object: an stdClass with the
	following members: ->max_tokens (int), ->plugin_id(int). This is encoded as an integer pair
	with a delimiter in the database. 

	
	Privileges of type PRIVILEGE_TYPE_DISK_FOR_CORPUS
	--------------------------------------------------
	
	This one is just like  PRIVILEGE_TYPE_DISK_FOR_UPLOADS, but it affects how much storage they are
	allowed to use for their user-corpora. If they add a corpus that pushes them over the limit,
	all their user corpora are disabled till they delete something.
	
	The "object" of this kind of privilege is an integer. Its string representation is that integer,
	as a string (just like for FREQLIST_CREATE).
	
	
	
	
	(TODO document more here)
*/

/**
 * Encodes a complex value that is the "object" of a privilege "verb" into
 * a string for the database.
 */
function encode_privilege_scope_to_string($type, $object)
{
	switch($type)
	{
	case PRIVILEGE_TYPE_CORPUS_FULL:
	case PRIVILEGE_TYPE_CORPUS_NORMAL:
	case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
		/* "object" is an array of corpus names... */
		if (empty($object))
			return '';
		foreach($object as &$c)
			$c = cqpweb_handle_enforce($c);
		return implode('~', $object);
		
	case PRIVILEGE_TYPE_FREQLIST_CREATE:
	case PRIVILEGE_TYPE_UPLOAD_FILE:
	case PRIVILEGE_TYPE_EXTRA_RUNTIME:
	case PRIVILEGE_TYPE_DISK_FOR_UPLOADS:
	case PRIVILEGE_TYPE_DISK_FOR_CORPUS:
		/* "object" is an integer */
		return (string)$object;
		
	case PRIVILEGE_TYPE_INSTALL_CORPUS:
		/* "object" is actually an object, with two integer members */
		return $object->plugin_id . '~' . $object->max_tokens;
	
	case PRIVILEGE_TYPE_CQP_BINARY_FILE:
		/* ignore whatever was passed, "object" is always an empty string. */
		return '';
		
	/* Add more privileges here as the system develops. */

	default:
		exiterror("Critical error: invalid privilege type constant encountered when encoding!");
	}
}


/**
 * Encodes a complex value that is the "object" of a privilege "verb" into
 * a string for the database.
 */
function decode_privilege_scope_from_string($type, $string)
{
	switch($type)
	{
	case PRIVILEGE_TYPE_CORPUS_FULL:
	case PRIVILEGE_TYPE_CORPUS_NORMAL:
	case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
		/* "object" is an array of corpus names... */
		if ('' == $string)
			return array();
		else
			return explode ('~', $string);

	case PRIVILEGE_TYPE_FREQLIST_CREATE:
	case PRIVILEGE_TYPE_UPLOAD_FILE:
	case PRIVILEGE_TYPE_EXTRA_RUNTIME:
	case PRIVILEGE_TYPE_DISK_FOR_UPLOADS:
	case PRIVILEGE_TYPE_DISK_FOR_CORPUS:
		/* "object" is a single integer */
		return (int)$string;
		
	case PRIVILEGE_TYPE_INSTALL_CORPUS:
		/* "object" is actually an object, with two integer members */
		preg_match('/^(\d+)~(\d+)$/', $string, $m);
		$o = new stdClass;
		list(, $o->plugin_id, $o->max_tokens) = $m;
		return $o;
		
	case PRIVILEGE_TYPE_CQP_BINARY_FILE:
		/* "object" is utterly irrelevant. Empty string symbolises. */ 
		return '';

	/* Add more privileges here as the system develops. */

	default:
		exiterror("Critical error: invalid privilege type constant encountered when decoding!");
	}
}

/**
 * Produces an HTML representation of a complex value that is the "object" of a privilege "verb".
 */
function print_privilege_scope_as_html($type, $object)
{
	switch($type)
	{
	case PRIVILEGE_TYPE_CORPUS_FULL:
	case PRIVILEGE_TYPE_CORPUS_NORMAL:
	case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
		/* "object" is an array of corpus names... */
		if (empty($object))
			return '<b>Nothing</b> (empty set of corpora';
		foreach($object as &$c)
			$c = cqpweb_handle_enforce($c);
		$s = (count($object) > 1 ? '<b>Corpora:</b> ' : '<b>Corpus:</b> ');
		$s .= implode(', ', $object);
		return $s;

	case PRIVILEGE_TYPE_FREQLIST_CREATE:
	case PRIVILEGE_TYPE_EXTRA_RUNTIME:
		/* "object" is a single integer, so just needs a suffix, plus thousand-grouping */
		return number_format($object) . (PRIVILEGE_TYPE_FREQLIST_CREATE == $type ? ' tokens' : ' seconds');
	
	case PRIVILEGE_TYPE_UPLOAD_FILE:
	case PRIVILEGE_TYPE_DISK_FOR_UPLOADS:
	case PRIVILEGE_TYPE_DISK_FOR_CORPUS:
		/* also an integer, but better rendered as MB than bytes */
		return number_format( ((float)$object)/(1024.0*1024.0) , 0 ) . ' MB';
		
	case PRIVILEGE_TYPE_INSTALL_CORPUS:
		/* needs 2 parts presenting. For brevity, use plugin's integer id. */
		return /*'up to ' .*/ number_format($object->max_tokens) . ' tokens via plugin # ' . $object->plugin_id;
		
	case PRIVILEGE_TYPE_CQP_BINARY_FILE:
		/* scope object not defined. */
		return '<em>(n/a)</em>';
	
	/* Add more privileges here as the system develops. */

	default:
		exiterror("Critical error: invalid privilege type constant encountered when printing!");
	}
}




/**
 * Creates a new privilege.
 */
function add_new_privilege($type, $scope, $description = '')
{
	$scope_string = encode_privilege_scope_to_string($type, $scope);
	$type = (int)$type;
	$description = escape_sql($description);

	do_sql_query("insert into user_privilege_info (type,scope,description) values ($type, '$scope_string', '$description')") ;
}


function update_privilege_description($id, $new_desc)
{
	$id = (int) $id;
	$new_desc = escape_sql($new_desc);	
	do_sql_query("update user_privilege_info set description = '$new_desc' where id = $id "); 
}

/**
 * Changes the maximum imposed by a privilege of one of the types whose
 * scope is an integer limit.
 * 
 * @param int $id        Privilege to change.
 * @param int $new_limit New integer maximum.
 */
function update_privilege_integer_max($id, $new_limit)
{
	$id = (int) $id;
	$info = get_privilege_info($id);

	switch($info->type)
	{
	case PRIVILEGE_TYPE_FREQLIST_CREATE:
	case PRIVILEGE_TYPE_UPLOAD_FILE:
	case PRIVILEGE_TYPE_EXTRA_RUNTIME:
	case PRIVILEGE_TYPE_DISK_FOR_UPLOADS:
	case PRIVILEGE_TYPE_DISK_FOR_CORPUS:
		break;
	default:
		exiterror("You cannot set a maximum integer for a privilege of this kind.");
	}
	
	$new_scope = encode_privilege_scope_to_string($info->type, (int)$new_limit);

	do_sql_query("update user_privilege_info set scope = '$new_scope' where id = $id "); 
}



/**
 * Changes the privilege type of a corpus-access privilege to one of the other levels.
 * 
 * Note, both the new level, and the existing level of the privilege must be 
 * one of the ones like PRIVILEGE_TYPE_CORPUS_*. Otherwise, nothing happens.
 * 
 * @param int $id                     The privilege to modify.
 * @param int $new_privilege_type     The privilege type constant of the new level.
 */
function update_privilege_access_level($id, $new_privilege_type)
{
	$id = (int) $id;
	$info = get_privilege_info($id);
	
	$new_privilege_type = (int) $new_privilege_type;
	
	$check = array(PRIVILEGE_TYPE_CORPUS_FULL,PRIVILEGE_TYPE_CORPUS_NORMAL,PRIVILEGE_TYPE_CORPUS_RESTRICTED);
	if ( ! (in_array((int)$info->type, $check, true) && in_array($new_privilege_type, $check, true)) )
		return;
	
	do_sql_query("update user_privilege_info set type = $new_privilege_type where id = $id ");
}


function add_corpus_to_privilege_scope($id, $corpus_to_add)
{
	$id = (int) $id;
	$info = get_privilege_info($id);

	switch($info->type)
	{
	case PRIVILEGE_TYPE_CORPUS_FULL:
	case PRIVILEGE_TYPE_CORPUS_NORMAL:
	case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
		break;
	default:
		exiterror("You cannot add a corpus to the scope of a privilege of this kind.");
	}
	
	$corpus_to_add = cqpweb_handle_enforce($corpus_to_add);
	$info->scope_object[] = $corpus_to_add;
	sort($info->scope_object);
	
	$new_scope = encode_privilege_scope_to_string($info->type, $info->scope_object);

	do_sql_query("update user_privilege_info set scope = '$new_scope' where id = $id "); 
}


function remove_corpus_from_privilege_scope($id, $corpus_to_remove)
{
	$id = (int) $id;
	$info = get_privilege_info($id);

	switch($info->type)
	{
	case PRIVILEGE_TYPE_CORPUS_FULL:
	case PRIVILEGE_TYPE_CORPUS_NORMAL:
	case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
		break;
	default:
		exiterror("You cannot remove a corpus from the scope of a privilege of this kind.");
	}
	
	$corpus_to_remove = cqpweb_handle_enforce($corpus_to_remove);
	
	if (false !== ($ix = array_search($corpus_to_remove, $info->scope_object)))
	{
		unset($info->scope_object[$ix]);
		$new_scope = encode_privilege_scope_to_string($info->type, $info->scope_object);
		do_sql_query("update user_privilege_info set scope = '$new_scope' where id = $id "); 
	}
	/* if the corpus wasn't on that list, do nothing. */
}

/**
 * Change the scope of a corpus-install privilege. 
 * 
 * @param int $id               ID of the privilege to update. 
 * @param int $new_max_tokens   Max number of tokens installable. Pass false to leave unchanged.
 * @param int $new_plugin_id    ID of plugin this covers. Pass false to leave unchanged. 
 */
function update_install_privilege_scope($id, $new_max_tokens, $new_plugin_id)
{
	$info = get_privilege_info($id);
	
	if (PRIVILEGE_TYPE_INSTALL_CORPUS != $info->type)
		exiterror("This is not a corpus-install privilege; it can't be given that kind of scope.");
	
	if (false !== $new_plugin_id)
	{
		$info->scope_object->plugin_id = (int)$new_plugin_id;
		if (0 < mysqli_num_rows(do_sql_query("select `id` from plugin_registry where `id`=$new_plugin_id")))
			exiterror("A corpus-install privilege cannot be linked to unreal plugin number ( # $new_plugin_id).");
		/* ie abort if the specified plugin isn't one that actually exists. */
	}
	if (false !== $new_max_tokens)
		$info->scope_object->max_tokens = (int)$new_max_tokens;

	$revised = escape_sql(encode_privilege_scope_to_string($info->type, $info->scope_object));
	
	do_sql_query("update user_privilege_info set scope='$revised' where id = $id");
}


/**
 * Generate the 3 default privileges for a specified corpus.
 * 
 * @return  bool     False if corpus did not exist, otherwise true.
 */
function create_corpus_default_privileges($corpus)
{
	/* generates the descriptions for the new privileges .... */
	static $mapper = array(
		PRIVILEGE_TYPE_CORPUS_FULL       => "Full access privilege",
		PRIVILEGE_TYPE_CORPUS_NORMAL     => "Normal access privilege",
		PRIVILEGE_TYPE_CORPUS_RESTRICTED => "Restricted access privilege",
		);
	
	if (!in_array($corpus, list_corpora()))
		return false;
	
	foreach(array_keys($mapper) as $type)
	{
		/* does a privilege exist which has this type and scope over just this corpus? */ 
		if (false === check_privilege_by_content($type, array($corpus)))
			add_new_privilege($type, array($corpus), $mapper[$type] . " for corpus [$corpus]");
	}
	return true;
}

function create_default_privileges_for_all_corpora()
{
	foreach(list_corpora() as $c)
		create_corpus_default_privileges($c);
}

function create_all_default_noncorpus_privileges()
{
	/* create default privileges for freqlists of 1 million, 10 million, 25 million, and 100 million. */
	$text = 'Frequency lists for subcorpora up to';
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_FREQLIST_CREATE, 1000000))
		add_new_privilege(PRIVILEGE_TYPE_FREQLIST_CREATE, 1000000,   "$text one million tokens.");
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_FREQLIST_CREATE, 10000000))
		add_new_privilege(PRIVILEGE_TYPE_FREQLIST_CREATE, 10000000,  "$text ten million tokens.");
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_FREQLIST_CREATE, 25000000))
		add_new_privilege(PRIVILEGE_TYPE_FREQLIST_CREATE, 25000000,  "$text 25 million tokens.");
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_FREQLIST_CREATE, 100000000))
		add_new_privilege(PRIVILEGE_TYPE_FREQLIST_CREATE, 100000000, "$text 100 million tokens.");
	
	/* create default privileges for uploads: 0.5 MB, 1 MB, 2 MB */
	$text = 'Upload files up to';
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_UPLOAD_FILE, 1024 * 512))
		add_new_privilege(PRIVILEGE_TYPE_UPLOAD_FILE, 1024 * 512,   "$text 0.5 MB.");
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_UPLOAD_FILE, 1024 * 1024))
		add_new_privilege(PRIVILEGE_TYPE_UPLOAD_FILE, 1024 * 1024,  "$text 1 MB.");
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_UPLOAD_FILE, 1024 * 2048))
		add_new_privilege(PRIVILEGE_TYPE_UPLOAD_FILE, 1024 * 2048,  "$text 2 MB.");
	
	/* create default privilege for upload-area filestore: 10 MB */
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_DISK_FOR_UPLOADS, 10485760))
		add_new_privilege(PRIVILEGE_TYPE_DISK_FOR_UPLOADS, 10485760, "Filestore allocation: 10 MB in the upload area.");

	/* there is NO default for PRIVILEGE_TYPE_INSTALL_CORPUS */
	
	/* create default privilege for corpus datastore: 50 MB, 100MB, 200MB */
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_DISK_FOR_CORPUS, 52428800))
		add_new_privilege(PRIVILEGE_TYPE_DISK_FOR_CORPUS, 52428800,  "Disk allocation: 50 MB for installed corpora.");
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_DISK_FOR_CORPUS, 104857600))
		add_new_privilege(PRIVILEGE_TYPE_DISK_FOR_CORPUS, 104857600,  "Disk allocation: 100 MB for installed corpora.");
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_DISK_FOR_CORPUS, 209715200))
		add_new_privilege(PRIVILEGE_TYPE_DISK_FOR_CORPUS, 209715200,  "Disk allocation: 200 MB for installed corpora.");
	
	/* create two sample privileges for extra runtime) */
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_EXTRA_RUNTIME, 60))
		add_new_privilege(PRIVILEGE_TYPE_EXTRA_RUNTIME, 60,   "Extra runtime: 60 seconds.");
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_EXTRA_RUNTIME, 120))
		add_new_privilege(PRIVILEGE_TYPE_EXTRA_RUNTIME, 120,   "Extra runtime: 120 seconds.");
	
	/* create the sole CQP bionary file privilege. */
	if (false === check_privilege_by_content(PRIVILEGE_TYPE_CQP_BINARY_FILE, ""))
		add_new_privilege(PRIVILEGE_TYPE_CQP_BINARY_FILE, "", "CQP binary file access privilege");
}

/**
 * Delete the privilege with the specified ID number.
 */
function delete_privilege($id)
{
	$id = (int) $id;
	
	/* delete all grants of this privilege; then delete the privilege itself. */
	do_sql_query("delete from user_grants_to_users  where privilege_id = $id");
	do_sql_query("delete from user_grants_to_groups where privilege_id = $id");
	do_sql_query("delete from user_privilege_info   where id = $id");
}

/** 
 * Returns an object (stdClass with members corresponding to the
 * fields of the user_privilege_info table in the database) containing
 * all DB fields for the specified privilege.
 * 
 * An extra field is added, namely the DECODED SCOPE. This is a complex value
 * (array or object) in the member $scope_object.
 * 
 * Returns false in case of a nonexistent privilege.
 */
function get_privilege_info($id)
{
	$id = (int)$id;

	if (1 > mysqli_num_rows($result = do_sql_query("select * from user_privilege_info where id = $id")))
		return false; 
	else
	{
		$o = mysqli_fetch_object($result);
		$o->scope_object = decode_privilege_scope_from_string($o->type, $o->scope);
		return $o;
	}
}

/**
 * Gets the description string for a given privilege ID.
 * Returns empty string in case of an invalid ID.
 */
function privilege_id_to_description($privilege_id)
{
	return get_sql_value("select description from user_privilege_info where id = " . (int)$privilege_id);
}

/**
 * Gets an array mapping privilege ids (as keys) to descriptions (as values).
 */
function get_all_privilege_descriptions()
{
	$a = array();
	$result = do_sql_query("select id, description from user_privilege_info order by id asc");
	while ($o = mysqli_fetch_object($result))
		$a[$o->id] = $o->description;
	return $a;
}




/** 
 * Returns an array of objects of the type returned by get_privilege_info().
 * 
 * The array keys are integers equal to the privilege ID code. 
 * The array is sorted by ascending ID.
 * 
 * Optional conditions can be specified as an associative array, as follows:
 * 
 * - If key 'corpus' is set, then only corpus privileges that affect the corpus specified
 *   are returned. 
 * - If key 'only' is set (value: an integer constant) then only the privileges
 *   of that type are returned.
 * (set both those keys,  and you get the intersection, which may be empty.)
 * 
 * @see get_privilege_info()
 */
function get_all_privileges_info($conditions = array())
{
	$cond = '';
	
	if (isset($conditions['corpus']))
		$cond = ' (type=' . PRIVILEGE_TYPE_CORPUS_FULL 
				. ' or type=' . PRIVILEGE_TYPE_CORPUS_NORMAL 
				. ' or type=' . PRIVILEGE_TYPE_CORPUS_RESTRICTED
				. ') ';
	if (isset($conditions['type']))
		$cond = (empty($cond) ? ' (type=' : ' and (type=') . (int)$conditions['type'] . ') ';
	
	if (!empty($cond))
		$cond = "where $cond";
	
	$result = do_sql_query("select * from user_privilege_info $cond order by id");

	$list = array();
	while ($o = mysqli_fetch_object($result))
	{
		$o->scope_object = decode_privilege_scope_from_string($o->type, $o->scope);
		
		/* corpus filter, if requested */
		if (isset($conditions['corpus']))
			if (!in_array($conditions['corpus'], $o->scope_object))
				continue;
		/* end corpus filter */
		
		/* no filter has taken effect, so add to returnable list */
		$list[$o->id] = $o;
	}
	return $list;
}

/**
 * Checks whether at least one privilege exists with the given type and scope.
 * 
 * Pass in scope as data object, not as string.
 * 
 * Returns the privilege ID (if a privilege exists) or false (no such privilege exists).
 */
function check_privilege_by_content($type, $scope)
{
	$type = (int)$type;
	$scope_string = encode_privilege_scope_to_string($type, $scope);
	/* scope object string representations are always known-MySQL-safe */
	
	if (1 > mysqli_num_rows($result = do_sql_query("select id from user_privilege_info where type = $type and scope = '$scope_string'")))
		return false;
	
	list($id) = mysqli_fetch_row($result);
	return $id;
}



function grant_privilege_to_user($user, $privilege_id, $expiry = 0)
{
	if (empty($user))
		return;
	$user_id = user_name_to_id($user);
	$privilege_id = (int)$privilege_id;
	$expiry = (int)$expiry;

	if ( false === get_sql_value("select user_id from user_grants_to_users where user_id=$user_id and privilege_id=$privilege_id") )
		do_sql_query("insert into user_grants_to_users(user_id, privilege_id, expiry_time) values ($user_id, $privilege_id,$expiry)");
}

function grant_privilege_to_group($group, $privilege_id, $expiry = 0)
{
	if (empty($group))
		return;
	$group_id = group_name_to_id($group);
	$privilege_id = (int)$privilege_id;
	$expiry = (int)$expiry;
	
	if (false === get_sql_value("select group_id from user_grants_to_groups where group_id=$group_id and privilege_id=$privilege_id"))
		do_sql_query("insert into user_grants_to_groups(group_id, privilege_id, expiry_time) values ($group_id, $privilege_id,$expiry)");
}

function remove_grant_from_user($user, $privilege_id)
{
	$user_id = user_name_to_id($user);
	$privilege_id = (int)$privilege_id;
	
	do_sql_query("delete from user_grants_to_users where user_id = $user_id and privilege_id = $privilege_id");
}

function remove_grant_from_group($group, $privilege_id)
{
	$group_id = group_name_to_id($group);
	$privilege_id = (int)$privilege_id;

	do_sql_query("delete from user_grants_to_groups where group_id = $group_id and privilege_id = $privilege_id");
}

/**
 * Returns flat array of usernames of all users who INDIVIDUALLY have the specified privilege.
 * 
 * For non-existent privilege, or privilege not assigned to anyone, returns empty array.
 */
function list_users_with_privilege($privilege_id)
{
	$privilege_id = (int) $privilege_id;
	
	$result = do_sql_query("select username from user_grants_to_users 
									inner join user_info 
									on user_grants_to_users.user_id = user_info.id 
								where privilege_id = $privilege_id");
	
	$names = array();
	while ($r = mysqli_fetch_row($result))
		$names[] = $r[0];
	return $names;
}

/**
 * Returns flat array of names of groups with the specified privilege.
 */
function list_groups_with_privilege($privilege_id)
{
	$privilege_id = (int) $privilege_id;
	
	$result = do_sql_query("select group_name from user_grants_to_groups
									inner join user_groups 
									on user_grants_to_groups.group_id = user_groups.id 
								where privilege_id = $privilege_id");
	
	$names = array();
	while ($r = mysqli_fetch_row($result))
		$names[] = $r[0];
	return $names;
}


/**
 * Returns an array of DB objects, representing the grants given to the user with the specified name.
 */
function list_user_grants($user)
{
	$uid = user_name_to_id($user);
	$ret = array();
	$result = do_sql_query("select * from user_grants_to_users where user_id = $uid order by privilege_id asc");
	while ($o = mysqli_fetch_object($result))
		$ret[] = $o;
	return $ret;
}


/**
 * Returns an array of DB objects, representing the grants given to the user with the specified name.
 */
function list_group_grants($group)
{
	$gid = group_name_to_id($group);
	$ret = array();
	$result = do_sql_query("select * from user_grants_to_groups where group_id = $gid order by privilege_id asc");
	while ($o = mysqli_fetch_object($result))
		$ret[] = $o;
	return $ret;
}


/**
 * Returns an array of privilege objects, containing (unique) objects for the
 * privileges that the given user has, whether by virtue of a user grant, or via
 * their group memberships and group grants.
 * 
 * The privilege id numbers are the array keys (that's how the contents are kept unique!)
 */
function get_collected_user_privileges($user)
{
	static $privileges = NULL;
	static $all_privs = NULL;
	
	if (is_null($privileges))
	{
		$privileges = array();
		$all_privs = get_all_privileges_info(); 
	}
	
	if (!isset($privileges[$user]))
	{
		$privileges[$user] = array();
	
		/* add privileges held individually */ 
		foreach(list_user_grants($user) as $grant)
			if ( ! isset($privileges[$user][$grant->privilege_id]) )
				$privileges[$user][$grant->privilege_id] = $all_privs[$grant->privilege_id];
		
		/* add privileges held via groups */
		foreach(get_list_of_groups($user) as $group)
			foreach(list_group_grants($group) as $grant)
				if ( ! isset($privileges[$user][$grant->privilege_id]) )
					$privileges[$user][$grant->privilege_id] = $all_privs[$grant->privilege_id];
	}
	
	return $privileges[$user];
}

/** this func does not cover user-corpora, which are managed separately (by owner/granting) */
function max_user_privilege_level_for_corpus($user, $corpus)
{
	static $precalc_max = array();
	if (!isset($precalc_max[$user]))
	{
		$precalc_max[$user] = array();
		
		$all = get_collected_user_privileges($user);
		
		foreach($all as $p)
		{
			switch ($p->type)
			{
			/* a little trick: we know that these constants are 3, 2, 1 respectively,
			 * so we can do the following: */
			case PRIVILEGE_TYPE_CORPUS_FULL:
			case PRIVILEGE_TYPE_CORPUS_NORMAL:
			case PRIVILEGE_TYPE_CORPUS_RESTRICTED:
				foreach ($p->scope_object as $c)
				{
					if (!isset($precalc_max[$user][$c]))
						$precalc_max[$user][$c] = PRIVILEGE_TYPE_NO_PRIVILEGE; 
					if ($p->type > $precalc_max[$user][$c])
						$precalc_max[$user][$c] = $p->type;
				}
				break;
			}
		}
	}
	return isset($precalc_max[$user][$corpus]) ? $precalc_max[$user][$corpus] : PRIVILEGE_TYPE_NO_PRIVILEGE;
}



function max_value_of_integer_scoped_privilege($username, $type)
{
	static $max = array();
	
	if (!isset($max[$username]))
	{
		$max[$username] = array(
			PRIVILEGE_TYPE_FREQLIST_CREATE    => 0, 
			PRIVILEGE_TYPE_UPLOAD_FILE        => 0, 
			PRIVILEGE_TYPE_EXTRA_RUNTIME      => 0,
			PRIVILEGE_TYPE_DISK_FOR_UPLOADS   => 0,
			PRIVILEGE_TYPE_DISK_FOR_CORPUS    => 0,
		);
		/* we need a key in the array for each type of privilege whose scope-object is an integer;
		 * note that we begin with a ridiculously low value, so that any privilege will be higher */		
		
		foreach(get_collected_user_privileges($username) as $p)
			if (isset($max[$username][$p->type]) )
				if ( (int)$p->scope_object > $max[$username][$p->type])
					$max[$username][$p->type] = (int)$p->scope_object;		
	}
	
	return $max[$username][$type];
}

function clone_group_grants($from_group, $to_group)
{
	/* checks for group validity */
	if ($from_group == $to_group || empty($from_group) || empty($to_group))
		return;
	
	$id_from = group_name_to_id($from_group);
	$id_to   = group_name_to_id($to_group);
	
	do_sql_query("delete from user_grants_to_groups where group_id = $id_to");
	
	$result = do_sql_query("select privilege_id, expiry_time from user_grants_to_groups where group_id = $id_from");
	
	while ($o = mysqli_fetch_object($result))
		do_sql_query("insert into user_grants_to_groups 
							(group_id,privilege_id,expiry_time) 
						values 
							($id_to,{$o->privilege_id},{$o->expiry_time})");
}






/*
 * =====================
 * COLLEAGUATE FUNCTIONS
 * =====================
 */


/**
 * 
 * @param  int    $user_id    User ID to lookup. 
 * @param  int    $status     Limit the lookup to a particular status of links (or COLLEAGUATE_STATUS_ANY for both)
 * @return array              Array of integer user ids.
 */
function list_colleaguate_ids($username, $status)
{
	if (false === ($cond = get_sql_where_from_colleaguate_status($status)))
		return [];
	
	$id = user_name_to_id($username);
	
	/* remember, all links are symmetrical once confirmed, so we use "from" to get
	 * either bidirectional links, or not-yet-confirmed one-way links */
	return list_sql_values("select to_id from `user_colleague_links` where from_id = $id and $cond");
}

/**
 * Get objects for all the active colleaguates of the given user.
 *  
 * @param  int      $user_id     User ID to lookup.
 * @return array                 Array of objects with IDs as keys.
 */
function get_all_active_colleaguate_info($user_id)
{
	$id = (int)$user_id;
	$sql = "select `user_info`.* from `user_colleague_links` 
				inner join `user_info` on `to_id` = `user_info`.`id` 
				where `from_id` = $id and `user_colleague_links`.`status` = " . COLLEAGUATE_STATUS_ACTIVE;
	return get_all_sql_objects($sql, 'id');	
}

function get_sql_where_from_colleaguate_status($status)
{
	switch ($status)
	{
	case COLLEAGUATE_STATUS_PENDING: 
	case COLLEAGUATE_STATUS_ACTIVE: 
		return " (`status` = $status) ";
	case COLLEAGUATE_STATUS_ANY:
		return " (`status` in(" . COLLEAGUATE_STATUS_ACTIVE . ',' . COLLEAGUATE_STATUS_PENDING . ")) "; 
	case COLLEAGUATE_STATUS_UNDEFINED:
	default:
		return false;
	}
	
}

function break_colleaguate_link($user_id_from, $user_id_to)
{
	$from_id = (int)$user_id_from;
	$to_id   = (int)$user_id_to;
	
	do_sql_query("delete from user_colleague_links where from_id = $from_id and to_id = $to_id");
	do_sql_query("delete from user_colleague_links where from_id = $to_id   and to_id = $from_id");
}

function confirm_colleaguate($user_id_from, $user_id_to)
{
	$from_id = (int)$user_id_from;
	$to_id = (int)$user_id_to;

	do_sql_query("update user_colleague_links set `status` = " . COLLEAGUATE_STATUS_ACTIVE . " where from_id = $from_id and to_id = $to_id");
}

function get_all_colleaguation_requesters_info($user_id)
{
	$user_id = (int)$user_id;
	return get_all_sql_objects("select user_info.* 
									from  user_colleague_links left join user_info 
									on    from_id = user_info.id 
									where to_id = $user_id 
									and   user_colleague_links.`status` = " . COLLEAGUATE_STATUS_PENDING
									);
}

/**
 * Looks for a link between an "existing" from and an existing "to".
 * Iff the link is there, an opposite link (with the users switched) is created.
 * @param string $username_existing_from
 * @param string $username_to
 */
function make_colleaguation_bidirectional($user_id_existing_from, $user_id_existing_to)
{
	$from_id = (int)$user_id_existing_from;
	$to_id = (int)$user_id_existing_to;
	$s = COLLEAGUATE_STATUS_ACTIVE;
	
	/* confirm outbound link */
	$sql = "select from_id from user_colleague_links where from_id = $from_id and to_id = $to_id and `status` = $s" ;
	if (1 > mysqli_num_rows(do_sql_query($sql)))
		return;
	
	/* does the equivalent inbound link exist already? */
	$sql = "select from_id from user_colleague_links where from_id = $to_id and to_id = $from_id and `status` = $s" ;
	if (1 <= mysqli_num_rows(do_sql_query($sql)))
		return;	
	
	do_sql_query("insert into user_colleague_links (from_id, to_id, `status`) VALUES ($to_id, $from_id,$s)");
}

/**
 * 
 * @param  int $user_id_from   ID of account making the request.
 * @param  int $user_id_to     ID of the account to link to.
 * @return bool                True for sucesss; false if something went wrong.
 */
function raise_colleaguate_request($user_id_from, $user_id_to)
{
	if (!($from_info = get_user_info_by_id($user_id_from)))
		return false;
	
	if (!($to_info = get_user_info_by_id($user_id_to)))
		return false;
		
	/* if a link already exists, return false. Don't create another. */
	$result = do_sql_query("select * from `user_colleague_links` where from_id = {$from_info->id} && to_id = {$to_info->id}");
	if (0 < mysqli_num_rows($result))
		return false;
	
	/* if a link going the other way exists, and this is therefore reciprocal, then just do it - no permission check. */
	$result = do_sql_query("select * from `user_colleague_links` where to_id = {$from_info->id} && from_id = {$to_info->id}");
	if (0 < mysqli_num_rows($result))
		$init_status = COLLEAGUATE_STATUS_ACTIVE;
	else
		$init_status = COLLEAGUATE_STATUS_PENDING;
	
	do_sql_query("insert into `user_colleague_links` (`from_id`, `to_id`, `status`) values ({$from_info->id}, {$to_info->id}, $init_status)");
	
	if (COLLEAGUATE_STATUS_ACTIVE == $init_status)
		return true;

	list($from_realname, $from_address) = render_user_name_and_email($from_info);
	list($to_realname  , $to_address  ) = render_user_name_and_email($to_info);
	
	$affiliation = empty($from_info->affiliation) ? '' : " (of {$from_info->affiliation})";
	
	$body = <<<HERE
Dear $to_realname,

Another user on our CQPweb wants to collaborate with you.


* User :   {$from_info->username}
* Name :   $from_realname$affiliation
* Email:   $from_address 


If this user is a real "colleaguate", please log in to CQPweb in the
usual way, and then go to your user account (click the link that
says "Your user account details" on the main menu page).

Click on the "Colleaguate requests" option on the user account menu 
to view all requests that you have not yet responded to.

Note that data sharing is one-way: this user will not receive access
to your corpus data unless you explicitly give them access using the
CQPweb "colleaguate" tools.

If you DO NOT wish to allow this user to share corpus data with you, 
then all you need to do is ignore this email. 

Yours sincerely,

The CQPweb User Administration System


HERE;
	
	return send_cqpweb_email($to_address, 'CQPweb: new colleaguation request!', $body);
}




/* ==================== *
 * USER MACRO FUNCTIONS *
 * ==================== */



/**
 * Adds a CQP macro to the given user's account.
 * 
 * @param string $username    User account username.
 * @param string $macro_name  Name to be given to the macro.
 * @param string $macro_body  Body of the macro.
 */
function user_macro_create($username, $macro_name, $macro_body)
{
	$username   = escape_sql($username);
	$macro_name = escape_sql($macro_name);
	$macro_body = escape_sql($macro_body);
	
	/* convert any \r to \n and delete multiple \n */
	$macro_body = str_replace("\r", "\n", $macro_body);
	$macro_body = "\n" . str_replace("\n\n", "\n", $macro_body);
	
	/* deduce macro_num_args by matching all strings of form $\d+ */
	preg_match_all('|[^\\\\]\$(\d+)|', $macro_body, $m, PREG_PATTERN_ORDER);
	
	$top_mentioned_arg = -1;
	
	foreach($m[1] as $num)
		if ($num > $top_mentioned_arg)
			$top_mentioned_arg = $num;
	
	/* The $\d references count from zero so if $1 is top mentioned, num args is actually 2 */
	$macro_num_args = $top_mentioned_arg + 1;
	
	/* delete macro if already exists */
	user_macro_delete($username, $macro_name, $macro_num_args);
	
	$sql = "INSERT INTO user_macros
		(user, macro_name, macro_num_args, macro_body)
		values
		('$username', '$macro_name', $macro_num_args, '$macro_body')";
	
	do_sql_query($sql);
}

/**
 * Deletes a specified user macro from a given user account.
 * 
 * Returns true if the macro was found and deleted; false otherwise 
 * (bad ID, or the macro is not owned by this user.)
 * 
 * @param  string $username
 * @param  int    $macro_id
 * @return bool
 */
function user_macro_delete($username, $macro_id)
{
	$username = escape_sql($username);
	$macro_id = (int)$macro_id;
	
	if (do_sql_query("delete from user_macros where id=$macro_id and user='$username'") )
		return (0 < get_sql_affected_rows());
	else 
		return false;
}


/**
 * Load all macros for the specified user to the main $cqp slave process.  
 */
function user_macro_loadall($username)
{
	$username = escape_sql($username);

	$result = do_sql_query("select * from user_macros where user='$username'");

	$cqp = get_global_cqp();

	while ($o = mysqli_fetch_object($result))
	{
		$block = "define macro {$o->macro_name}({$o->macro_num_args}) ' ";
		$block .= str_replace("'", "\\'", strtr($o->macro_body, "\t\r\n", "   ")) . " '";
		$cqp->execute($block);
	}
}






/* ================= *
 * CAPTCHA FUNCTIONS *
 * ================= */

/**
 * Returns true if the captcha in DB matches the response.
 * 
 * Captcha comparison is always case-insensitive.
 * 
 * Whether true or false, destroys the captcha.
 */
function check_captcha($which, $response)
{
	$which = (int)$which;
	
	$result = do_sql_query('select captcha from user_captchas where id = ' . $which);
	
	if (mysqli_num_rows($result) < 1) 
		return false;
	
	list($correct) = mysqli_fetch_row($result);
	
	do_sql_query('delete from user_captchas where id = ' . $which);

	return ($correct == strtolower($response));
}

/**
 * Puts a new captcha into the DB, and return its DB number (for reference)
 */
function create_new_captcha()
{
	static $alphabet = 'abcdefghijkmnpqrstuvwxyz+=&!@:^23456789';

	/* delete any captchas that are past their expiry time. */
	$now = time();
	do_sql_query("delete from user_captchas where expiry_time < $now");
	
	/* create a new captcha from the alphabet above & store in the DB */
	
	for($i = 0, $n = 6, $l = strlen($alphabet)-1, $captcha = ''; $i < $n; $i++)
		$captcha .= $alphabet[random_int(0, $l)];
	
	/* captchas expire after 30 mins */
	$t = $now + 1800;
	
	do_sql_query("insert into user_captchas (captcha, expiry_time) values ('$captcha', $t)");
	
	/* we get back the ID, which is our reference number, & return it */
	return get_sql_insert_id();
}

/**
 * Sends to the browser the binary data of an image for the captcha in question.
 */
function send_captcha_image($which)
{	
	/* parameters used by all algorithms */
	$font = realpath('../css/img/LinLibertine_Mah.ttf');
	$height = 100;
	$width  = 240;
	
	$image = imagecreatetruecolor($width, $height);

	
	/* get the capcha from DB; if not available, put error message on image */
	
	$which = (int)$which;
	
	$result = do_sql_query('select captcha from user_captchas where id=' . $which);

	if (mysqli_num_rows($result) < 1)
	{
		$image = imagecreatetruecolor($width, $height);
		$bgcol   = imagecolorallocate($image, 255, 255, 255);
		$textcol = imagecolorallocate($image, 0, 0, 0);
		imagefill($image, 0, 0, $bgcol);
		imagettftext($image, 12, 0, 15, 15, $textcol, $font, 'Error: please retry');
		header("Content-Type: image/jpeg");
		imagejpeg($image); 
		imagedestroy($image);
		return;
	}
	
	list($captcha) = mysqli_fetch_row($result);


	/* three different image algorithms, to mix up the kinds of captcha people see */
	
	switch (random_int(0,2))
	{
	case 0:
	
		/* COLOURFUL */
		
		$bgcol   = imagecolorallocate($image, random_int(  0,100), random_int( 0,100), random_int(  0,100));
		$textcol = imagecolorallocate($image, random_int(200,255), random_int(80,255), random_int(100,200));

		imagefill($image, 0, 0, $bgcol);

		$fs = random_int(20,45);
		$x  = random_int(5,20);
		$y  = random_int(45,75);
		
		for ($i = 0 ; $i < 6 ; $i++)
		{
			$angle = random_int(-12,12);
			
			imagettftext($image, 
				$fs,           /* font size */
				$angle,        /* angle */
				$x,            /* x basepoint */
				$y,            /* y basepoint */
				$textcol,
				$font,
				$captcha[$i]);

			$x += $fs - random_int(0,$fs-(int)($fs/2));
			$y += ($angle > 0 ? 1 : -1) * random_int(0,10);
			$fs += random_int(-5,5);
			if ($fs < 16)
				$fs = $fs + 8;
			if ($fs > 45)
				$fs = $fs - 8;
		}

		for($x = 0, $n = random_int(7,12); $x < $n ; $x++)
			imageline($image, 0, random_int(0,$height), $width, 10 + random_int(0,$height), $textcol);

		break;
		
	case 1:
	
		/* GREY ON WHITE */
	
		$bgcol   = imagecolorallocate($image, 255, 255, 255);
		$textcol = imagecolorallocate($image, 150, 150, 150);

		imagefill($image, 0, 0, $bgcol);
		
		for($x = 1; $x <= 25; $x++)
			imageline($image, random_int(1, $width), random_int(1, $height), random_int(1, $width), random_int(1, $height), $textcol);

		$fs = random_int(20,56);
		$x  = random_int(10,15);
		$y  = random_int(45,70);
		
		for ($i = 0 ; $i < 6 ; $i++)
		{
			$angle = random_int(-4,4);
			
			imagettftext($image, 
				$fs,          /* font size */
				$angle,       /* angle */
				$x,           /* x basepoint */
				$y,           /* y basepoint */
				$textcol,
				$font,
				$captcha[$i]
				);

			$x += $fs - random_int(0,$fs-(int)($fs/2));
			$y += ($angle > 0 ? 1 : -1) * random_int(0,10);
			$fs += random_int(-5,5);
			if ($fs < 20)
				$fs = $fs + 8;
			if ($fs > 56)
				$fs = $fs - 8;
		}

		break;

	
	case 2:
	
		/* BLUE WITH DOTTY BACKGROUND */

		/* set the colours */ 
		$bgcol    = imagecolorallocate($image, 255, 255, 255);
		$textcol  = imagecolorallocate($image, 20, 40, 100); 
		$noisecol = imagecolorallocate($image, 60, 80, 140); 

		imagefill($image, 0, 0, $bgcol);
		
		/* random dots */ 
		for($x = 0; $x < ($width*$height)/8; $x++ ) 
			imagefilledellipse($image, random_int(0,$width), random_int(0,$height), 1, 1, $noisecol);
		
		/* random lines */ 
		for($x = 0; $x < ($width*$height)/300 ; $x++ )
			imageline($image, random_int(0,$width), random_int(0,$height), random_int(0,$width), random_int(0,$height), $noisecol);

		$fs = $height * 0.40;
		$x  = ($width - random_int(($width - 20), ($width - 5)));
		$y  = ($height - random_int(0, (int)$height/2));
		
		for ($i = 0 ; $i < 6 ; $i++)
		{
			$angle = random_int(-4,4);
			
			imagettftext($image, 
				$fs,          /* font size */
				$angle,       /* angle */
				$x,           /* x basepoint */
				$y,           /* y basepoint */
				$textcol,
				$font,
				$captcha[$i]
				);

			$x += $fs - random_int(0,$fs-(int)($fs/2));
			$y += ($angle > 0 ? 1 : -1) * random_int(0,10);
			$fs += random_int(-5,5);
			if ($fs < 20)
				$fs = $fs + 8;
			if ($fs > 56)
				$fs = $fs - 8;
		}

		break;
	}
	
	
	/* send the image */
	
	header("Content-Type: image/jpeg");
	imagejpeg($image); 
	imagedestroy($image);
}


/* these two functions are here because, although having to do with user corpora, they
 * don't rely on any of the usercorpus-lib apparatus, and they are needed even when 
 * other usercorpus stuff isn't. */


/**
 * Get the total amount of disk space being used for the user's installed corpora. 
 */
function sum_user_corpus_disk_usage($username)
{
	$username = escape_sql($username);
	return (int)get_sql_value("select sum(size_bytes_index + size_bytes_freq) from corpus_info where owner = '$username'");
}

/**
 * Get the amount of disk space the user is ALLOWED to use for corpora. 
 */
function get_user_corpus_disk_allocation($username)
{
	return max_value_of_integer_scoped_privilege($username, PRIVILEGE_TYPE_DISK_FOR_CORPUS);
}







