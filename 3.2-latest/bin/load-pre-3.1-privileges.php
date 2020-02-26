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
 * This script loads privileges from old .htaccess files into the new internal database format.
 */

require('../lib/environment.php');

/* include function library files */
require('../lib/metadata-lib.php');
require('../lib/general-lib.php');
require('../lib/html-lib.php');
require('../lib/sql-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');

require ('../bin/cli-lib.php');



cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP, RUN_LOCATION_CLI);

echo "\n"
	, wordwrap("User access is managed in the database now, not in the Apache htaccess files. "
		. "This script allows you to restore your old privileges and grants.")
	, "\n\n"
	, wordwrap("Scanning now for an htaccess file for each existing corpus...")
	, "\n\n";

$all_groups = get_list_of_groups();

foreach(list_corpora() as $corpus)
{
	$htf = "../$corpus/.htaccess";
	
	if (is_file($htf))
	{
		echo " .htaccess detected for corpus [$corpus].\n";
		
		echo "    - generating default privileges....";
		create_corpus_default_privileges($corpus);
		$p_id = check_privilege_by_content(PRIVILEGE_TYPE_CORPUS_NORMAL, array($corpus));
		echo "done\n";
		
		echo "    - importing group grants....";
		$data = file_get_contents($htf);
		if (1 > preg_match("/require group([^\n]*)\n/", $data, $m))
		{
			echo "unsuccessful (regex failure)\n\n";
			continue;
		}
		foreach(preg_split('/\s+/', trim($m[1])) as $g)
		{
			if ('superusers' == $g)
				continue;
			if (!in_array($g, $all_groups))
			{
				echo "\n    UNRECOGNISED group $g in htaccess file, group skipped!\n";
				continue;
			}
			/* grant privilege to that group */
			grant_privilege_to_group($g, $p_id);
		}
		echo "done\n";

		echo "    - disabling original htaccess file....";
		if (is_writable($htf) && is_writable("../$corpus"))
		{
			rename($htf, "../$corpus/_.htaccess");
			echo "done\n\n";
		}
		else
			echo "unsuccessful (no write access)\n\n";
	}
	else
		echo " No .htaccess found for corpus [$corpus]: no privileges/grants imported.\n\n";
}

echo "All corpora checked: script exiting now.\n";

cqpweb_shutdown_environment();


