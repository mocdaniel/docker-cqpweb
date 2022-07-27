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
 * This file contains functions related to user corpora.
 */


function user_owns_corpus($username, $corpus)
{
	if ($c = get_corpus_info($corpus))
		if ($username == $c->owner)
			return true;
	return false;
}


/** 
 * Returns a list of user corpora (referred to by the corpus name strings), as a flat array.
 * If a username is supplied, only that user's corpora will be returned.
 * 
 * @see list_corpora()
 * @return  array         Flat array, sorted by value (the corpus handles).
 */
function list_user_corpora($username = '', $only_visible = true)
{
	$where = (empty($username) ? ' where `owner` IS NOT NULL ' : " where `owner` = '" . escape_sql($username) . "'");
	$and = $only_visible ? ' and `visible` != 0' : '';
	return list_sql_values("select corpus from corpus_info $where $and order by corpus asc");
}


/** 
 * Returns a list of user corpora as a hash (keys = handles, values = titles). 
 * If a username is supplied, only that user's corpora will be returned.
 * 
 * @see list_corpora_with_titles()
 * @return  array    Associative array, sorted by value.
 */
function list_user_corpora_with_titles($username = '', $only_visible = true)
{
	$where = (empty($username) ? ' where `owner` IS NOT NULL ' : " where `owner` = '" . escape_sql($username) . "'");
	$and = $only_visible ? ' and `visible` != 0' : '';
	return list_sql_values_as_map("select corpus, title from corpus_info $where $and order by title asc", 'corpus', 'title');
}


/**
 * Gets an array of corpus_info objects for user corpora. The array keys are the corpus
 * handles (the corpus field in the database). The array is sorted by these keys.
 * 
 * If a username is specified, only that user's corpora will be returned. 
 * 
 * @see get_all_corpora_info()
 */
function get_all_user_corpora_info($username = '', $only_visible = false)
{
	$where = (empty($username) ? ' where `owner` IS NOT NULL ' : " where `owner` = '" . escape_sql($username) . "'");
	$and = $only_visible ? ' and `visible` != 0' : '';
	return get_all_sql_objects("select * from corpus_info $where $and order by corpus asc", "corpus");
}




/**
 * Gets the (web or filesystem) path of a user-corpus symlink container directory. (In the web tree.) 
 * Creates it if it doesn't exist. Is relative to the current script, so if we're 
 * RUN_LOCATION_MAINHOME, it won't start in '..'.
 * 
 * @param  string $username   Username; if empty, the current user is used.
 * @return string             Path to the directory. False if no username given or currently logged in.
 *                            Path DOES NOT end in '/'.
 */
function get_user_corpus_web_directory($username = false)
{
	global $Config;
	
	/* cached because of the multiple checks on the filesystem used in asserting the directory. */
	static $cache = [];
	
	if (isset($cache[$username]))
		return $cache[$username];
	
	if (empty($username))
	{
		global $User;
		if (!$User->logged_in)
			return false;
		else
			$username = $User->username;
	}
	
	$dir = (RUN_LOCATION_MAINHOME == $Config->run_location ? 'usr/' : '../usr/');
	
	/* in case there are too many users, we separate them by hex code of BYTE 1 of username. */
	$dir .= sprintf("%02x/", ord($username[0]));
	if (!is_dir($dir))
		mkdir($dir, 0775);
	/* block access to the listing */
	file_put_contents("{$dir}index.php", "<?php exit(1);\n");
	
	/* and now assert the directory itself. */
	$dir .= $username . '/';
	if (!is_dir($dir))
		mkdir($dir, 0775);
	file_put_contents("{$dir}index.php", "<?php exit(1);\n");
	
	return ( $cache[$username] = $dir );
}


function delete_user_corpus_web_directory($username)
{
	global $User;
	
	if ( ! ($User->is_admin() || $User->username == $username) )
		return;
	
	$path = get_user_corpus_web_directory($username);
	
	recursive_delete_directory($path);
}

function get_user_corpus_web_path($corpus_id, $username)
{
	return get_user_corpus_web_directory($username) . make_user_corpus_handle($corpus_id);
}



/**
 * The user corpus name is a direct function of its ID. 
 * 
 * @param  int    $id  Integer ID of the corpus.
 * @return string      Corpus handle.
 */
function make_user_corpus_handle($id)
{
	return sprintf("_%05x", $id);
}



/** For a given installer plugin, says how many tokens the user can install in a single corpus. */
function get_corpus_installer_privilege_tokens($plugin_id, $username)
{
	$max = -1;
	foreach (get_collected_user_privileges($username) as $p)
		if (PRIVILEGE_TYPE_INSTALL_CORPUS == $p->type && $plugin_id == $p->scope_object->plugin_id)
			if ($p->scope_object->max_tokens > $max)
				$max = $p->scope_object->max_tokens;
	return $max;
}



/**
 * Delete a user-owned corpus. 
 * 
 * @param  string $corpus     Handle of the corpus to delete. 
 * @return bool               True for success.
 */
function delete_user_corpus($corpus)
{
	global $User;

	/* only admin or the owner can delete a user corpus */
	if (!$User->is_admin_or_owner($corpus))
		return false;
	
	if (!($c = get_corpus_info($corpus)))
		return false;

	revoke_all_access_to_user_corpus($c->id);

	delete_corpus_from_cqpweb($corpus);
	
	return true;
}


/** 
 * Delete all corpora belonging to the specified user. 
 * @param string $username    Username: person whose corpora are to be deleted.
 */
function delete_user_installed_corpora($username)
{
	global $User;

	/* only admin or the owner can delete a user corpus */
	if (!$User->is_admin() || $User->username == $username)
		return false;
	/* check once to avoid multiple checks in loop below. */
	
	/* do not attempt to delete user corpora tghat are not visible */ 
	foreach(list_user_corpora($username, true) as $uc)
		delete_user_corpus($uc);
	
	return true;
}

/**
 * Install a user corpus.
 * 
 * @param  string $username       Username of the new corpus's owner.
 * @param  array  $install_info   A hash of installation info. Must contain:
 *                                  - plugin_reg_id == what CorpusInstaller to use
 *                                  - input_files   == array of inputs. Can be [] if the plugin
 *                                    self-generates its data, e.g. if it is a probe of the web.
 *                                  - css_path      == css entry for corpus_info
 *                                  - title         == descriptive title
 *                                  - r2l           == bool, is this corpus r2l script? 
 * @param  string $username       Message explaining what went wrong can go here as out-param.
 *                                (Optional.)
 */
function install_user_corpus($username, $install_info, &$error_message = NULL)
{
	global $Config;
	
	$install_begin_time = time();
	
	/* convenience variables from out of our hash of data. */
	$plugin_reg_id = (int)$install_info['plugin_reg_id'];
	$input_files = empty($install_info['input_files']) ? [] : $install_info['input_files'];
	
	
	if (false === ($pl = get_plugin_info($plugin_reg_id)))
	{
		$error_message = "Cannot install corpus with an Installer plugin that is not in the registry!";
		return false;
	}
	
	if (PLUGIN_TYPE_CORPUSINSTALLER != $pl->type)
	{
		$error_message = "Cannot install corpus with a Plugin that is not a CorpusInstaller!";
		return false;
	}
	
	if (false === ($u_info = get_user_info($username)) )
	{
		$error_message = 'Cannot install corpus for user who doesn\'t exist!';
		return false;
	}
	
	if (1 > $max_tokens = get_corpus_installer_privilege_tokens($plugin_reg_id, $username))
	{
		$error_message = 'User does not have permission to use this CorpusInstaller!';
		return false;
	}
	
	
	/*
	 * REMOVE TIME LIMIT: this could be a long 'un.
	 */
	php_execute_time_unlimit();
	/* note that caller should have used "disconnect_browser_and_continue()" */
	

	
	/* install function should call on the CorpusInstaller plugin to provide the variant bits. */
	
	$engine = new $pl->class($pl->extra);
	if (!$engine->status_ok())
	{
		$error_message = $engine->error_desc();
if (empty($error_message)) {squawk("After new class(), empty error message");}
		goto install_user_corpus_cleanup_0;
	}
	
	$engine->set_max_input_tokens($max_tokens);
	
	/* create dummy row in corpus info, to preserve our idenitfier in advance. */
	$tmp_name = escape_sql('-' . substr(uniqid(NULL,true), 0, 19));
	/* note that we use an illegal handle character ('-') and then randomness to assure uniqueness for the next few milliseconds! */
	
	do_sql_query("insert into corpus_info (corpus, owner, cwb_external, visible) values ('$tmp_name', '{$u_info->username}', 0, 0)");
	
	$corpus_id = get_sql_insert_id();
	
	/* so we now might as well add the rest of the basic info ... */
	$name  = make_user_corpus_handle($corpus_id);
	$NAME  = strtoupper($name);
	$r2l   = $install_info['r2l'] ? '1' : '0';
	$title = escape_sql($install_info['title']);
	$css   = escape_sql($install_info['css_path']);
	
	do_sql_query("update corpus_info 
						set
							corpus             = '$name',
							cqp_name           = '$NAME',
							main_script_is_r2l = $r2l,
							title              = '$title',
							css_path           = '$css',
							access_statement   = 'This corpus has been installed by an individual user; only that user can choose to share it.'
						where id = $corpus_id");
	
	$engine->set_corpus_name($name);
	
	/* register this corpus install job */
	if (false === ($job_id = register_installer_process($corpus_id, $u_info->id, $plugin_reg_id)))
	{
		$error_message = 'Could not register the corpus-installer process.';
		goto install_user_corpus_cleanup_1;
	}
	
	

	/* web folder setup */
 	$link_path = get_user_corpus_web_path($corpus_id, $username);
	
	if (file_exists($link_path))
	{
		if (!is_link($link_path))
			recursive_delete_directory($link_path);
		else
			unlink($link_path);
	}

	if (! symlink("../../../exe", $link_path))
	{
		$error_message = 'Could not create web directory for the new corpus.';
		goto install_user_corpus_cleanup_2;
	}
	
	
	
	
	
	chmod($link_path, 0775);
	
	/* wait for our turn in the queue to come up */
	while (0 != ($duration = guess_installer_process_waittime($job_id)))
		sleep($duration);
	
	/*
	 * yawn ....
	 */
	

	/* run preparation (building/tagging) */
	update_installer_process_status($job_id, INSTALLER_STATUS_TAGGING);
	
	if ($engine->needs_input_files())
		$engine->add_input_file($input_files);
	
	if ( ! $engine->do_setup())
	{
		$error_message = $engine->error_desc();
if (empty($error_message)) {squawk("After do_setup, empty error message");}
		goto install_user_corpus_cleanup_2;
	}
	
	
	/* do corpus encoding */
	update_installer_process_status($job_id, INSTALLER_STATUS_ENCODING);

	$datadir = standard_corpus_index_path($name);
	
	if (realpath($datadir) == realpath($Config->dir->index)) // bug blocker. Just in case.
	{
		$error_message = "cqpweb: critical bug: datadir is index dir!!!!";
		goto install_user_corpus_cleanup_2;
		
	}
	if (is_dir($datadir))
	{
		if (!is_link($datadir))
			recursive_delete_directory($datadir);
		else
			unlink($datadir);
	}
	mkdir($datadir, 0775);
	
	$regfile = standard_corpus_reg_path($name);

	$encode_comm_errs = [];
	$encode_command = cwb_encode_new_corpus_command(
									$name, $engine->get_charset(), $datadir, 
									$engine->get_infile_info(), $engine->get_p_attribute_info(), $engine->get_s_attribute_info(),
									$encode_comm_errs);
	if (false === $encode_command)
	{
		$error_message = implode("/", $encode_comm_errs);
// if (empty($error_message)) {squawk("After encode, empty error message");}
		goto install_user_corpus_cleanup_3;
	}
// squawk($encode_command);

	$exit_status_from_cwb = 0;
	$output_lines_from_cwb = array($encode_command);

	exec($encode_command, $output_lines_from_cwb, $exit_status_from_cwb);
	if (0 != $exit_status_from_cwb)
	{
		$error_message = "Encoding error:" . array_pop($output_lines_from_cwb). array_pop($output_lines_from_cwb);
		goto install_user_corpus_cleanup_4;
	}

	/* registry will have been created by the previous step */
	chmod($regfile, 0664);

	

	/* do indexing and compression */
	update_installer_process_status($job_id, INSTALLER_STATUS_INDEXING);
	
	$qreg = escapeshellarg($Config->dir->registry);
	
	$output_lines_from_cwb[] = $makeall_command = "{$Config->path_to_cwb}cwb-makeall -r $qreg -V $NAME 2>&1";
	
	exec($makeall_command, $output_lines_from_cwb, $exit_status_from_cwb);
	if (0 != $exit_status_from_cwb)
	{
		$error_message = "Indexing error: " . array_pop($output_lines_from_cwb) . array_pop($output_lines_from_cwb);
		goto install_user_corpus_cleanup_4;
	}

	if ( ! cwb_compress_corpus_index($NAME, $output_lines_from_cwb))
	{
		$error_message = "Compression error: " . array_pop($output_lines_from_cwb). array_pop($output_lines_from_cwb);
		goto install_user_corpus_cleanup_4;
	}
	
	/* after this point, there is no reason to use the GOTO CLEANUP bits:
	 * the delete_corpus funcs will be capable of handline it. */
	
	
	/* do other misc setup */
	update_installer_process_status($job_id, INSTALLER_STATUS_SETUP);
	
	update_corpus_indexing_notes($name, $output_lines_from_cwb);

	/* now the CWB index has been created, we can do the following: */
	update_corpus_index_size($name);

	if ($engine->get_xml_datatype_check_needed())
		check_corpus_xml_datatypes($name);

	
	/* mysql table inserts */
	
	foreach ($engine->get_sql_for_corpus() as $s)
		do_sql_query($s);
// {squawk(preg_replace('/\s+/', ' ', $s));	do_sql_query($s); } 

	
	/* update the ceql bindings ... */
	
	$bind = $engine->get_annotation_bindings();
	
	update_corpus_ceql_bindings($name, $bind);
	
	if (isset($bind['tertiary_annotation_tablehandle']))
	{
		/* do we have it? */
		if (!get_tertiary_mapping_table($bind['tertiary_annotation_tablehandle']))
		{
			/* allow plugin to add it */
			$table = $engine->declare_maptable();
			if (!empty($table))
			{
				$mappings = serialise_mapping_table($table);
				add_tertiary_mapping_table($bind['tertiary_annotation_tablehandle'], "**Maptable from plugin {$pl->class}", $mappings);
			}
		}
	}

	
	/* text metadata setup */
// squawk("about to do metadata");
	$engine->do_metadata();
	// TODO in future we might allow users to add actual metadata after setup...
	// but we can allow the plugin to do whatever it wants!
// squawk("metadata done");
	
	
	/* time to run freqlist setup */
	update_installer_process_status($job_id, INSTALLER_STATUS_FREQLIST);

	setup_all_corpus_freqlist_data($name);
	
	update_corpus_sttr($name);


	
	
	/* all done! */
	update_installer_process_status($job_id, INSTALLER_STATUS_DONE);
	
	$secs = time() - $install_begin_time;
	if ($secs < 60)
		$dur = "$secs seconds";
	else if ($secs < 3600)
		$dur = intdiv($secs, 60) . " minutes, " . ($secs % 60) . " seconds";
	else if ($secs < 86400)
		$dur = intdiv($secs, 3600) . " hours, " . intdiv( ($secs % 3600) , 60) . " minutes";
	else
		$dur = number_format( intdiv($secs , 86400), 0) . " days, " . round( ((float)($secs % 3600))/60.0 , 1). " hours";
	
	set_installer_process_complete_message($job_id, "Corpus [$name]{$title}[/] is ready to use. Install time: $dur."); 
	
	/* corpora only become visible when they are complete (to stop them appearing in anyone's list) */
	update_corpus_visible($name, true);
	
	
	$engine->do_cleanup(false); /* don't delete the original files */
	
	add_variable_corpus_metadata($name, "Corpus installer the generated this corpus", $pl->description);
	
	add_variable_corpus_metadata($name, "Time taken to create this corpus", $dur);

	
	
	return true;
	
	
	/*
	 * MAIN PART OF FUNCTION DONE
	 */
	
	/* cleanup if there was a problem and we had to abort. Cleanup steps are in reverse order. */

	
install_user_corpus_cleanup_5:

	foreach($engine->get_sql_for_abort() as $s)
		do_sql_query($s);
	// hmm,  this cleanup step isn't called yet.  TODO: is this still the case???
	
	
install_user_corpus_cleanup_4:

	unlink($regfile);
	
	
install_user_corpus_cleanup_3:

	recursive_delete_directory($datadir);

	
install_user_corpus_cleanup_2:

	/* remove the web directory */
	if (is_link($link_path))
		unlink($link_path);
	
	
install_user_corpus_cleanup_1:

	/* delete the stub entry that we created & reserved. */
	do_sql_query("delete from corpus_info where id = $corpus_id");

	set_installer_process_status_abort($job_id, empty($error_message) ? 'Unknown error (abort/cleanup).' : $error_message);
squawk($error_message);





install_user_corpus_cleanup_0:

	$engine->do_cleanup();

	return false;
}



/**
 * User-alert function for completion of corpus-install.
 * 
 * @param  string  $which        Either 'complete' or 'error' to determine what message gets sent.
 * @param  string  $username     Username of addressee.
 * @param  mixed   $extra_info   If 'complete': the corpus integer ID. If 'error': the error message. 
 * @return bool                  Return value as from mail().
 */
function send_user_corpus_install_email($which, $username, $extra_info = NULL)
{
	$u = get_user_info($username);
	if (empty($u))
		return false;
	
	$mail_address = $u->email;
	
	if ('complete' == $which)
	{
		/* in this case, we expect "extra_info" to be the corpus ID. */
		if (!is_null($extra_info))
		{
			$c = get_corpus_info_by_id($extra_info); 
					$mail_subject =	"CQPweb says: your corpus is now installed and ready to use ({$c->title})";
			$main_para = "Your corpus is now installed and ready to use."; 
			$main_para .= "\n\nIt can be accessed at the following address:\n\n   " . url_absolutify(get_user_corpus_web_path($c->id, $username));
		}
		else
		{
			$mail_subject =	"CQPweb says: your corpus is now installed and ready to use";
			$main_para = "Your corpus is now installed and ready to use."; 
		}
	}
	else if ('error' == $which)
	{
		/* in this case, we expect "extra_info" to be the error message. */
		$mail_subject =	"CQPweb says: an error occurred while setting up your corpus"; 
		$main_para = "There was an error in the process of installing your corpus.";
		if (!empty($extra_info))
			$main_para .= "\n\nThe system error message was as follows: \n\n   $extra_info\n\n"
					. "If you receive error messages of this kind repeatedly, you\n\nshould contact the system administrator.";
	}

	$n = empty($u->realname) ? $u->username :  $u->realname; 
	
	$mail_body = <<<END_OF_EMAIL
Dear $n,

$main_para 

best regards,

The CQPweb server.

END_OF_EMAIL;
	
	return send_cqpweb_email($mail_address, $mail_subject, $mail_body);
}











/*
 * ============================
 * INSTALLER PROCESS MANAGEMENT
 * ============================
 */





function get_installer_process_info($id)
{
	$id = (int) $id;
	
	$r = do_sql_query("select * from user_installer_processes where id = $id");
	
	if (0 < mysqli_num_rows($r))
		return mysqli_fetch_object($r);
	else
		return false;
}


function get_all_installer_process_info()
{
	return get_all_sql_objects("select * from user_installer_processes order by `id`", 'id');
}

function list_installer_processes_of_user($username)
{
	$id = user_name_to_id($username);
	return list_sql_values("select id from user_installer_processes where `user_id` = $id order by `id`");
}

function list_installer_processes_with_status($status)
{
	$status = (int)$status;
	
	return list_sql_values("select id from user_installer_processes where `status` = $status order by `id`");
}

function list_installer_processes_queued_ahead($my_id)
{
	$my_id = (int)$my_id;
	
	return list_sql_values("select `id` from user_installer_processes where `id` < $my_id and `status` = ".INSTALLER_STATUS_WAITING);
}

/**
 * Estimates how many seconds a process should wait before checking again 
 * to see if it is at the top of the job queue yet.
 * 
 * If we ARE at the top of the job queue, returns 0 to indicate a process can start.
 * 
 * @param  int $job_id  ID of the job in the installer-process registry.
 * @return int          Number of seconds that the caller should wait.
 */
function guess_installer_process_waittime($job_id)
{
	global $Config;
	
	$counts = count_installer_processes_by_status();
	
	/* n of processes doing something */
	$n_proc 	= $counts[INSTALLER_STATUS_TAGGING]
				+ $counts[INSTALLER_STATUS_ENCODING]
				+ $counts[INSTALLER_STATUS_INDEXING]
				+ $counts[INSTALLER_STATUS_FREQLIST]
				+ $counts[INSTALLER_STATUS_SETUP]
				;
	
	/* give permission to run if there is a spare slot by returning 0. */
	if ($Config->max_installer_processes > $n_proc)
		return 0;
	
	/* OK, we need to wait. How long for? How long is the queue */
	$procs_in_queue = count(list_installer_processes_queued_ahead($job_id));
	
	/* if there was nothing ahead in the queue, we'd check after the default period. */
	if (0 == $procs_in_queue)
		return $Config->installer_process_wait_secs;
	
	/* OK, there are things ahead of us in the queue, AND the max processes are filled.
	 * So, we delay our checkback for an extra (default period) seconds per "batch" in the queue. */
	return $Config->installer_process_wait_secs + ($Config->installer_process_wait_secs * ((int) $procs_in_queue / $Config->max_installer_processes));
	
	/* EG: if the max is 5 and there are 15 in the queye, we wait 12 seconds to check. */
}


/**
 * List the counts of processes of different types in the installer-process log.
 * 
 * @return array   Array of integers; keys equal INSTALLER_STATUS constants.
 */
function count_installer_processes_by_status()
{
	/* pre-set the keys so they all get included, regardless of whether the DB has any */
	$count = [
			INSTALLER_STATUS_UNKNOWN  =>0,
			INSTALLER_STATUS_WAITING  =>0,
			INSTALLER_STATUS_TAGGING  =>0,
			INSTALLER_STATUS_ENCODING =>0,
			INSTALLER_STATUS_INDEXING =>0,
			INSTALLER_STATUS_FREQLIST =>0,
			INSTALLER_STATUS_SETUP    =>0,
			INSTALLER_STATUS_DONE     =>0,
			INSTALLER_STATUS_ABORTED  =>0,
	];
	
	$result = do_sql_query("select distinct(`status`) as s, count(*) as n from user_installer_processes group by `status`");
	
	while ($o = mysqli_fetch_object($result))
		$count[$o->s] = (int)$o->n; 
	
	return $count;	
}


/**
 * 
 * @param  int $status   If specified, the count is returned for processes with the given status. 
 *                       If not, the count is for all processes. 
 * @return int           How many processes were found.
 */
function count_installer_processes_of_user($username, $status = false)
{
	static $cache = [];
	
	if (!isset($cache[$username]))
	{
		$id = user_name_to_id($username);
		$result = do_sql_query("select distinct(`status`) as s, count(*) as n from user_installer_processes where user_id = $id group by `status`");
	
		$cache[$username] = array();

		while ($o = mysqli_fetch_object($result))
			$cache[$username][$o->s] = $o->n;
	}
	
	if (false === $status)
		return array_sum($cache[$username]);
	else if (isset($cache[$username][$status]))
		return $cache[$username][$status];
	else 
		return 0;
}



function update_installer_process_status($id, $new_status)
{
	$id = (int)$id;
	$new_status = (int)$new_status;
	do_sql_query("update user_installer_processes set `status` = $new_status where `id` = $id ");
}


function set_installer_process_status_abort($id, $error_message)
{
	$id = (int)$id;
	$s = INSTALLER_STATUS_ABORTED;
	$error_message = escape_sql($error_message);
	do_sql_query("update user_installer_processes set `status` = $s, error_message='$error_message' where `id` = $id ");
}


function abort_zombie_installer_process($job_id)
{
	global $User;
	if ($User->is_admin())
		set_installer_process_status_abort($job_id, "Process manually aborted by administrator as zombie.");
}



/**
 * Sets the corpus completion messaghe. Only works if status is "done".
 * @param int $id
 * @param string $message
 */
function set_installer_process_complete_message($id, $message)
{
	$id = (int) $id;
	$message = escape_sql($message);
	do_sql_query("update user_installer_processes set error_message='$message' where `id` = $id and `status` = " . INSTALLER_STATUS_DONE);	
}


/**
 * Adds a new installer process with initial WAITING status; returns its ID.
 * 
 * @param  int  $corpus_id       ID of the corpus (entry in corpus_info should have been pre-created).
 * @param  int  $user_id         ID of the user who owns this installer process.
 * @param  int  $plugin_reg_id   Plugin registry ID of the CorpusInstaller-type plugin. 
 * @return int                   ID in the installer process table of this process. Or, false for failure. 
 */
function register_installer_process($corpus_id, $user_id, $plugin_reg_id)
{
	/* clean up FIRST, as this might make life easier by freeing up a spot */
	delete_old_ok_installer_processes();
	
	$corpus_id     = (int)$corpus_id;
	$user_id       = (int)$user_id;
	$plugin_reg_id = (int)$plugin_reg_id;
	
	$php_pid       = getmypid();
	$s             = INSTALLER_STATUS_WAITING;
	
	$sql = "insert into user_installer_processes 
					( corpus_id, user_id, php_pid, plugin_reg_id,`status`) 
					values 
					($corpus_id,$user_id,$php_pid,$plugin_reg_id,$s)";
	
	do_sql_query($sql);
	
	if ($installer_id = get_sql_insert_id())
		return $installer_id;
	else 
		return false;
}


function delete_installer_process($id)
{
	$id = (int)$id;
	do_sql_query("delete from user_installer_processes where `id` = $id ");
}


/** Deletes all DONE-status installer processes that are older than a given number of weeks (4 by default). */
function delete_old_ok_installer_processes($weeks = 4)
{
	$n_hours = 24 * 7 * $weeks;
	$now = date('Y-m-d H:i:s'); /* this is the string format to feed into TIMESTAMPDIFF() */ 
	$done = INSTALLER_STATUS_DONE;
	do_sql_query("delete from user_installer_processes where `status`= $done and $n_hours <= TIMESTAMPDIFF(HOUR, `last_status_change`, '$now')");
}





/*
 * ================================
 * GRANT functions for user corpora
 * ================================
 */



function grant_user_corpus_access($corpus_id, $grantee_id, $comment = '')
{
	global $User; 
	
	$corpus_id = (int)$corpus_id;
	$grantee_id = (int)$grantee_id;
	
	if (!$User->is_admin_or_owner(get_corpus_info_by_id($corpus_id)->corpus))
		return;
	
	if (0 == get_sql_value("select count(*) from user_colleague_grants where corpus_id = $corpus_id and grantee_id = $grantee_id"))
	{
		$comment = escape_sql($comment);
		do_sql_query("insert into user_colleague_grants (corpus_id, grantee_id, comment) VALUES ($corpus_id,$grantee_id,'$comment')");
	}
}


function ungrant_user_corpus_access($corpus_id, $grantee_id)
{
	global $User;
	
	$corpus_id = (int)$corpus_id;
	$grantee_id = (int)$grantee_id;

	if ($grantee_id != $User->id && !$User->is_admin_or_owner(get_corpus_info_by_id($corpus_id)->corpus))
		return;
	
	do_sql_query("delete from user_colleague_grants where corpus_id=$corpus_id and grantee_id=$grantee_id");
}


function revoke_all_access_to_user_corpus($corpus_id)
{
	global $User;
	
	$corpus_id = (int)$corpus_id;
	
	if (!$User->is_admin_or_owner(get_corpus_info_by_id($corpus_id)->corpus))
		return;

	do_sql_query("delete from user_colleague_grants where corpus_id=$corpus_id ");
}


function get_all_user_corpus_grants_outgoing($user_id)
{
	$username = user_id_to_name($user_id);
	return get_all_sql_objects("select * from user_colleague_grants join corpus_info on user_colleague_grants.corpus_id = corpus_info.id where corpus_info.owner = '$username'");
}


function get_all_user_corpus_grants_incoming($user_id)
{
	$user_id = (int)$user_id;
	/* note the objects returned are corpus_info objects, plus an extra member so we can reference "corpus_id" */
	return get_all_sql_objects("select corpus_id, corpus_info.* from user_colleague_grants join corpus_info on user_colleague_grants.corpus_id = corpus_info.id where grantee_id = $user_id");
}


function user_corpus_is_granted_to_user($corpus_id, $user_id)
{
	$corpus_id = (int)$corpus_id;
	$user_id = (int)$user_id;

	return 0 < get_sql_value("select count(*) from user_colleague_grants where corpus_id=$corpus_id and grantee_id=$user_id");
}



function subcorpus_is_granted_to_user($subcorpus_id, $user_id)
{
	// TODO sharing of subcoorpora is not yet implemented 
	return false;
}

