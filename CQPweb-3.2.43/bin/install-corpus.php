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
 * Run 
 *         php install-corpus.php --help 
 * 
 * ... for info about this script. 
 */
		
$longopts = [
		'help', 
		'version',
		'external_cwb_data',
		'emailDone',
		'corpus_name:',
		'corpus_description:',
		'corpus_scriptIsR2L',
		'cssCustom',
		'cssBuiltIn:',
		'cssCustomUrl:',
		
		'corpus_useDefaultRegistry',
		'corpus_cwb_registry_folder:',
		
		'corpus_charset',
		'useXmlTemplate:',
		'useAnnotationTemplate:',
		'file:'
];	

$_GET = [];

$_GET['admF'] = 'installCorpusIndexed';

$_GET['emailDone']                 = 0;
$_GET['corpus_scriptIsR2L']        = 0;
$_GET['corpus_useDefaultRegistry'] = 0;
$_GET['cssCustom']                 = 0;

$files = [];

$opt_array = getopt('hvHVf:', $longopts);

if (empty($opt_array))
	/* no options == same as "help" */
	$opt_array = array('help' => false);

foreach ($opt_array as $opt_key => $opt_val)
{
	switch($opt_key)
	{
		/* odd ones */
		
	case 'h':
	case 'H':
	case 'help':
		echo cli_print_install_corpus_help();
		exit;
	
	case 'v':
	case 'V':
	case 'version':
		echo cli_print_install_corpus_version_info();
		exit;
		
	case 'external_cwb_data':
		$_GET['admF'] = 'installCorpus';
		break;
	
	case 'f':
	case 'file':
		$files[] = $opt_val;
		break;
		
		/* bool default 0 */
		
	case 'emailDone':
	case 'corpus_scriptIsR2L':
	case 'cssCustom':
	case 'corpus_useDefaultRegistry':
		$_GET[$opt_key] = '1';
		break;	
		
		/* strings */
		
	case 'corpus_name':
	case 'corpus_description':
	case 'corpus_cwb_registry_folder':
	case 'corpus_charset':
	case 'useXmlTemplate':
	case 'useAnnotationTemplate':
	case 'cssCustomUrl':
	case 'cssBuiltIn':
		$_GET[$opt_key] = $opt_val;
		break;
		
	default:
		exit("\nERROR: unrecognised option $opt_key! \n\n");
	}
}
	
/* check for compulsory present / apply defaults */

$req = ['corpus_name','corpus_description'];

if($_GET['admF'] == 'installCorpusIndexed')
{
	if ( '1' == $_GET['corpus_useDefaultRegistry'] )
		$req[] = 'corpus_cwb_registry_folder';
}
else
{
	if (empty($files))
		exit("\nERROR: no input files specified. Please specify --file=FILENAME (at least once).\n\n");
	$req =  array_merge($req, ['corpus_charset', 'useAnnotationTemplate', 'useXmlTemplate'] );
}

foreach($req as $opt_key)
	if (!isset($_GET[$opt_key]))
		exit("\nERROR: an essential setting was not spe3cified. Please add option for --$opt_key.\n\n");

if (!isset($_SERVER))
	$_SERVER = [];
$_SERVER['QUERY_STRING'] = 'index.php?' . http_build_query($_GET) . '&' . http_build_query($files);
	
unset($files, $req, $opt_array, $opt_key, $opt_val, $longopts);

require ('../adm/index.php');



/* 
 * END OF SCRIPT
 */




function cli_print_install_corpus_help()
{
	return <<<END_OF_HELP

==================================
CQPWEB CORPUS INSTALLATION UTILITY
==================================

Install a system corpus from the command line.

Usage: 
   php install-corpus.php [options]
      (** from /path/to/cqpweb/bin **)

Arguments that are not options or values of options are ignored. 


General options:
================

* -h, --help
  Print this help message and exit.

* -v, --version
  Print the version message and exit.

* --emailDone
  Optional. If flag is present, send an alert to system admin email address when done.

* --external_cwb_data
  Optional. If flag is present, installation is based on existing CWB data. If not, it is based on installing from VRT files. 

* --corpus_name
  Compulsory. Handle for the corpus (letters, numbers, underscore only).

* --corpus_description
  Compulsory. Descriptive title for the corpus.

* --corpus_scriptIsR2L
  Optional. If flag is present, main script of corpus is assumed to be right-to-left. If not, L2R is assumed.
		
* --cssCustom
  Optional. If flag is present, specifies that a custom CSS file is to be used. Then --cssCustomUrl is required. 

* --cssCustomUrl
  Compulsory if --cssCustom set. URL of a custom CSS file.

* --cssBuiltIn
  Compulsory if --cssCustom not set. Filename of a CSS file in the path/to/cqpweb/css directory.


Options for indexing from file
==============================

* --corpus_charset
  Compulsory. Specifies input encoding. Any of utf8, greek, cyrillic, arabic, hebrew or latin1 thru 9.

* --useXmlTemplate
  Compulsory. Integer id of template to use.

* --useAnnotationTemplate
  Compulsory. Integer id of template to use.

* -f, --file
  Compulsory (at least one). Input file to add (must be in upload area).


Options for existing CWB data
=============================

* --corpus_useDefaultRegistry
  Optional. If flag is present, data will be sought in default registry for CQPweb. If not, --corpus_cwb_registry_folder must be specified.

* --corpus_cwb_registry_folder
  Compulsory if --corpus_useDefaultRegistry not set. Path to folder where the existing CWB index's registry file is.


END_OF_HELP;

}
	

function cli_print_install_corpus_version_info()
{
	return <<<END_OF_INFO

This script tracks the install-corpus form (see admin-install-lib.php). 
If the latter is updated, this script may become out of date. 
This script was last updated for version 3.2.39 of CQPweb.
For the current version number, see environment.php.


END_OF_INFO;
	
	
	
}


