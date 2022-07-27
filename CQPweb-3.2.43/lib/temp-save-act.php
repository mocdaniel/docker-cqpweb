<?php
/**
This handles the "merge saved query" action.

Next version will merge this where it neds to go. 
*/



/* Allow for usr/xxxx/corpus: if we are 3 levels down instead of 2, move up two levels in the directory tree */
if (! is_dir('../lib'))
	chdir('../../../exe');


/* include defaults and settings */
require('../lib/environment.php');


/* include function files */
require('../lib/html-lib.php');
include('../lib/cache-lib.php');
include('../lib/xml-lib.php');
include('../lib/cqp.inc.php');
include('../lib/scope-lib.php');
include('../lib/concordance-lib.php');
include('../lib/general-lib.php');
include('../lib/query-lib.php');
include('../lib/sql-lib.php');
include('../lib/useracct-lib.php');
include('../lib/exiterror-lib.php');

$User = $Corpus = $Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_NO_FLAGS);

$cqp = get_global_cqp();


if (!isset($_GET['saveScriptSaveName'], $_GET['q_a'], $_GET['q_b']))
	exiterror("Bad call!!!");


$savename = $_GET['saveScriptSaveName'];


if (preg_match('/\W/', $savename))
	exiterror("Bad savename!!!");


$q1 = cqpweb_handle_enforce($_GET['q_a']);
$q2 = cqpweb_handle_enforce($_GET['q_b']);
if ($q1 == $q2)
	exiterror("same query selected twice!");

$newqname = qname_unique($Config->instance_name);

$cqp->execute("$newqname = union $q1  $q2 ");

$cqp->execute("save $newqname");


/* work out how many texts have hits (for the DB record) */
$n_of_texts = count( $cqp->execute("group $newqname match text_id") );

/* put the query into the saved queries DB */
$cache_record = QueryRecord::create(
		$newqname, 
		$User->username, 
		$Corpus->name, 
		'uploaded', // TODO should say something else. Union op?? /Merge??
		'', 
		'', 
		QueryScope::new_by_unserialise(""),
		$cqp->querysize($newqname),
		$n_of_texts, 
		"upload[{$Config->instance_name}]" // TODO extend postp syntax to cover union (also difference, intersect)
		);
$cache_record->saved = CACHE_STATUS_SAVED_BY_USER;
$cache_record->save_name = $savename;
$cache_record->save();


set_next_absolute_location( 'index.php?ui=savedQs' );

cqpweb_shutdown_environment();
