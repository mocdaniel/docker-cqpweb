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
 * This script prints a form to collect the options for numerically-thinning a query.
 */

/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');

/* include function library files */
require('../lib/general-lib.php');
require('../lib/sql-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/scope-lib.php');
require('../lib/cqp.inc.php');
require('../lib/cache-lib.php');
require('../lib/useracct-lib.php');
require('../lib/xml-lib.php');
require('../lib/concordance-lib.php');

/* declare global variables */
$Corpus = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);

/* check parameters - only one we really need is qname */

$qname = safe_qname_from_get();

/* get the query record so we can find out how many hits we are thinning */
$query_record = QueryRecord::new_from_qname($qname);
if (false === $query_record)
	exiterror("The specified query $qname was not found in cache!");

echo print_html_header($Corpus->title . ' -- CQPweb Thinning Options', $Config->css_path, array('cword'));

do_conc_popup_thin_control($query_record);

echo print_html_footer('thin');

cqpweb_shutdown_environment();
