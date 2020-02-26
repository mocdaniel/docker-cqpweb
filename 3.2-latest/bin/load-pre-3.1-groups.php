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
 * This script loads groups from a .htgroup file into the new internal database format.
 */


require('../lib/environment.php');

/* include function library files */
require('../lib/general-lib.php');
require('../lib/html-lib.php');
require('../lib/sql-lib.php');
require('../lib/useracct-lib.php');
require('../lib/exiterror-lib.php');

require ('../bin/cli-lib.php');

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);

echo "User groups are managed in the database now, not in the Apache htgroup file. This script allows you to restore your old groups.\n";

while (1)
{
	echo "Since I don't know where your groups used to be kept, I will look for a [.htgroup] file in whatever folder you specify.\n";
	$accessdir = get_variable_path("the folder that was \$cqpweb_accessdir in your old config file");
	if (!is_file($f = "$accessdir/.htgroup"))
	{
		echo "I can't find a [.htgroup] file in folder [$accessdir].\n";
		if (ask_boolean_question("Do you want to try re-entering the folder?"))
			continue;
	}
	else
	{
		foreach(file($f) as $$err_line_content)
		{
			$$err_line_content = trim($$err_line_content);
			if (empty($$err_line_content)) continue;
			if (1 > preg_match('/^(\w+):(.*)$/', $$err_line_content, $m))
				echo "Skipping indecipherable group line: $$err_line_content\n";
			else
			{
				$group = $m[1];
				if ($group == 'superusers') continue; # superusers live in the config file, not the DB.
				
				echo "Found group $group....\n";
				if (0 < mysql_num_rows(do_mysql_query("select id from user_groups where group_name = '$group'")))
				{
					echo "... error: group $group was already in the database, will not be overwritten.\n";
					continue;
				}
				
				do_mysql_query("insert into user_groups (group_name, description) values ('$group','(re-imported group)')");
				list($g_id) = mysql_fetch_row(do_mysql_query("select id from user_groups where group_name = '$group'"));
				foreach (explode(' ', trim($m[2])) as $a_user)
				{
					$u_res = do_mysql_query("select id from user_info where username='$a_user'");
					if (1 > mysql_num_rows($u_res))
					{
						echo "... user $a_user not found in DB, not readded to group $group.\n";
						continue;
					}
					list($u_id) = mysql_fetch_row($u_res); 
					do_mysql_query("insert into user_memberships (user_id, group_id, expiry_time) values ($u_id, $g_id, 0)");
				}
				echo "....reinserted $group successfully!\n";
			}
		}
	}
	break;
}
echo "Done: script exiting now.\n";

cqpweb_shutdown_environment();

exit;

?>
