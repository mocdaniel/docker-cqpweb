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
 * This file contains the entry point for the CQPweb API-via-HTTP.
 * 
 * It is just a pointer that wraps around the correct script-call or 
 * other-action code.
 */


/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');
/* We need to require() the correct main script. The above test won't be true twice. 
 * So no worries about duplicating this check in the script we go to. */ 
	
/* this is the only script that includes api-lib. We need it before environment startup. */
require('../lib/api-lib.php');


/* 
 * We need to find out what function in order to stick the correct strin ginto the global $API.
 * The setting up of the $_GET parameters for the actual operation is done by
 * ApiController::__construct() when it is called by cqpweb_startup_environment().
 */

if (empty($_GET) && empty($_POST))
{
	ApiController::static_dispatch(NULL, API_ERR_NO_FUNCTION);
	exit;
}

if (empty($_GET) && isset($_POST['f']))
	$_GET = [ $_POST['f'] ];

if (empty($_GET['f']))
{
	ApiController::static_dispatch(NULL, API_ERR_NO_FUNCTION);
	exit;
}

/* startup will check for this var, and if found, switch the system to API mode, setting up $Config->Api as the API management/response object. */
$API = $_GET['f'];
unset($_GET['f']);

/* This function is the entry point for all API functions.
 * It either includes the correct script, or else does the business itself. */ 
api($API);


/* that's it. The whole API entry-point script is tiny. It just uses the single string $API to indicate to the main startup function what to do. */ 

/* ------------- *
 * END OF SCRIPT *
 * ------------- */

