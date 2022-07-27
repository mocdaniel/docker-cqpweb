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



/* Allow for usr/x/xxxx/corpus: if we are 4 levels down instead of 2, move up 3 levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../../exe');

require('../lib/environment.php');

/* include function library files */
require('../lib/general-lib.php');
require('../lib/sql-lib.php');
require('../lib/scope-lib.php');
require('../lib/xml-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');
require('../lib/useracct-lib.php');


/* declare global variables */
$Corpus = $User = $Config = $m = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP);

if (PRIVILEGE_TYPE_CORPUS_FULL > $Corpus->access_level)
	exiterror("You do not have permission to use this function.");

// temp hack for a BIG CORPOUS.
php_execute_time_unlimit();


/* ----------------------------- *
 * create and send the text file *
 * ----------------------------- */


/* 
 * first an EOL check 
 */
if (isset($_GET['downloadLinebreak']))
{
	$eol = preg_replace('/[^da]/', '', $_GET['downloadLinebreak']);
	$eol = strtr($eol, "da", "\r\n");
}
else
	$eol = $User->eol();


/*
 * Has a zip mode download been requested?
 */
$zip_mode = false;
if (isset($_GET['downloadZip']) && $_GET['downloadZip'])
{
	if (! extension_loaded('zip'))
		exiterror("CQPweb can't createzip files, because the PHPzip extension is not available. Contact your system administrator!");
	$zip_mode = true;
}


/* 
 * the filename for the output 
 */
$filename = (isset($_GET['exportFilename']) ? preg_replace('/[^\w\-]/', '', $_GET['exportFilename']) : '' );
if (empty($filename))
	$filename = $Corpus->name . '-export';
if (! preg_match(($zip_mode ? '/\.zip$/' : '/\.txt$/'), $filename))
	$filename .= ($zip_mode ? '.zip' : '.txt');



/* 
 * what to download? 
 */
if (!isset($_GET['exportWhat']) || '~~corpus' == $_GET['exportWhat'])
{
	$use_sc = false;
	$fileflag = '';
}
else
{
	if($zip_mode)
		exiterror("You cannot download a subcorpus in Zip format. Sorry.");
	/* see note below for why this is (possibility of partial texts!) */

	if (! preg_match('/^sc~(\d+)$/', $_GET['exportWhat'], $m))
		exiterror("Section of corpus to export has been badly specified!");

	$sc = Subcorpus::new_from_id($m[1]);
	if (false === $sc)
		exiterror("The subcorpus you specified could not be found on the system.");
	
	$use_sc = true;
	$fileflag = '-f "' .  $sc->get_dumpfile_path() . '"';
}


/* 
 * which format? 
 */
$format = (isset($_GET['format']) ? $_GET['format'] : 'standard');
	
switch ($format)
{
case 'standard':
	$flags = ($use_sc ? '-C' : '-H');
	$atts = '-P word';
	break;
	
case 'word_annot':
	$flags = ($use_sc ? '-C' : '-H');
	if (empty($Corpus->primary_annotation))
		exiterror("This corpus has no primary annotation, so word-and-annotation export is not available.");
	$atts = '-P word -P ' . $Corpus->primary_annotation;
	break;
	
case 'col':
	$flags = '-C';   /* C rather than Cx because Cx introduces a <Corpus> tag, XML decalration, etc. */
	$atts = '-ALL';
	break;
	
default:
	exiterror("Invalid export format specified.");
}

/* turn on <text_id> display in the output if we are in multifile mode */
if ($zip_mode && '-ALL' != $atts)
	$atts .= ' -S text_id';




/* 
 * AND NOW THE POINTY BIT 
 */


$cmd = "{$Config->path_to_cwb}cwb-decode $flags -r \"{$Config->dir->registry}\" $fileflag {$Corpus->cqp_name} $atts";
// show_var($cmd);exit;
$proc = popen($cmd, 'r');



$collection = '';
$n_key_items = 0;


if (!$zip_mode)
{
	/* mode to download a single text file */
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-Disposition: attachment; filename=$filename");
	
	while (false !== ($line = fgets($proc)))
	{
		if ($use_sc && $format == 'word_annot')
			$line = str_replace("\t", '/', $line);
		$collection .= ( $format == 'col' ? $line : (trim($line) . ' ') );
		/* assemble into 12-word lines */
		if ( false !== strpos($line, '</') || 0 == (++$n_key_items % 12))
		{
			echo $collection, ( $format == 'col' ? '' : $eol );
			$collection = '';
			$n_key_items = 0;
		}
	}
	if (!empty($collection))
		echo $collection, ( $format == 'col' ? '' : $eol );
}
else
{
	/* mode to download zip file */
//TODO
// what if we are dealing with a subcorpus that contains partial files? 
// In that case the method for splitting files would break. Ergo, disallow this combination of options.
	
	$tempzip = $Config->dir->cache . '/__exporttempzip-' . $Config->instance_name . '.zip';
	
	$z = new ZipArchive();
	$z->open($tempzip, ZipArchive::CREATE);
	$z->addEmptyDir("corpus");
	
	$n_files = 0;
	$num_files_at_once = 50; /* a relatively modest number. */
	
	$prev_text_id = '';
	
	while (false !== ($line = fgets($proc)))
	{
		/* NB, this way of doing it means that anything before the first <text_id> will be inserted into the first file;
		 * and moreover, that anything between </text_id> and <text_id> will be in the file before.... this may be bad
		 * in case of columnar download, as <text> and/or <text_*> could be stuck in the wrong file !!!
		 * TODO. */
// HOW TO FIX: separate out the getting of <text_id 
// form the "new file". 
		if (preg_match('/^<text_id (\w+)>/', $line, $m))
		{
			/* <text_id line */
			if (empty($prev_text_id))
			{
				/* beginning of first file. Do not create a file. Just store the ID. (below) */
			}
			else
			{
				/* end running file, start next file */
				$collection .= ($format == 'col' ? '' : $eol);
					
				$z->addFromString("corpus/{$prev_text_id}.txt", $collection);
				
				$collection = '';
				
				if (++$n_files >= $num_files_at_once)
				{
					/* to avoid overfilling RAM: commit after a certain N of files. */
					$z->close();
// i don't know whether this will work. Does the Zip extension load the whole zip file every time.
unset($zip);
$zip = new ZipArchive;
					$z->open($tempzip);
					$n_files = 0;
				}
			}
			/* for the next time! */
			$prev_text_id = $m[1];

			$collection .= $line;
			/* note, in this case <text_id> is always included at the start/end of each file ... */
		}
		else
		{
			if ($use_sc && $format == 'word_annot')
				$line = str_replace("\t", '/', $line);
		
			$collection .= ( $format == 'col' ? $line : (trim($line) . ' ') );
			
			if ($format != 'col')
				if ( false !== strpos($line, '</') || 0 == (++$n_key_items % 12))
					$collection .= $eol;
		}
	}
	if (!empty($collection))
	{
		$collection .= ($format == 'col' ? '' : $eol);
		$z->addFromString("corpus/{$prev_text_id}.txt", $collection);
	}
	

	$z->close();
	
	
	header("Content-Type: application/zip");
	header("Content-Disposition: attachment; filename=$filename");
	readfile($tempzip);
	
	unlink($tempzip);
}


pclose($proc);

/*
 * All done!
 */


cqpweb_shutdown_environment();
