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
 * This file contains a chunk of script which is included at the start of the Adminhome program.
 *
 * What it does is as follows: if one of a set of actions has been requested, it calls the execute.php program
 * to carry out the action. It then redirects to a followup page (so that the rest of the Adminhome program
 * does not run - if a render of the Adminhome is needed, it takes place after the redirect).
 */

// a generic, not a specific todo, here:
//TODO locationAfter is clunky. change goballyt ro afterLoc ? locAfter ? thenLoc ? nextLoc ? thenTo ? thence?

/* check for an uploaded file */
if (!empty($_FILES))
{
	/* in this case, there will be no $_GET: so create what will be needed */
	$_GET['admF'] = 'uploadFile';
}


/* code block that diverts up the various "actions" that may enter adminhome, so that they go to execute.php */

$_GET['admF'] = (isset($_GET['admF']) ? $_GET['admF'] : (isset($_POST['admF']) ? $_POST['admF'] : false));

switch($_GET['admF'])
{
/* 
 * NB. some cases go the same "action" places as various other scripts
 * and therefore include "redirect" instead of "execute".
 * 
 * Actions that are too complex to go via "execute" can instead be sent
 * via "admin-do".
 */


case 'execute':
	/* general case for when it's all already set up */
	require('../lib/execute.php');
	exit();


case 'resetSystemSecurity':
	$_GET['function'] = 'restore_system_security';
	require('../lib/execute.php');
	exit();


case 'uploadFile':
	$_GET['function'] = 'uploaded_file_to_upload_area';
	$_GET['args'] = array('uploadedFile', false);
	$_GET['locationAfter'] = 'index.php?ui=uploadArea';
	require('../lib/execute.php');
	exit();


case 'fileView':
	$_GET['function'] = 'do_uploaded_file_view';
	$_GET['args'] = array($_GET['filename'], true);
	require('../lib/execute.php');
	exit();


case 'fileCompress':
	$_GET['function'] = 'uploaded_file_gzip';
	$_GET['args'] = array($_GET['filename'], true);
	$_GET['locationAfter'] = 'index.php?ui=uploadArea';
	require('../lib/execute.php');
	exit();


case 'fileDecompress':
	$_GET['function'] = 'uploaded_file_gunzip';
	$_GET['args'] = array($_GET['filename'], true);
	$_GET['locationAfter'] = 'index.php?ui=uploadArea';
	require('../lib/execute.php');
	exit();


case 'fileFixLinebreaks':
	$_GET['function'] = 'uploaded_file_fix_linebreaks';
	$_GET['args'] = array($_GET['filename'], true);
	$_GET['locationAfter'] = 'index.php?ui=uploadArea';
	require('../lib/execute.php');
	exit();


case 'fileDelete':
	$_GET['function'] = 'uploaded_file_delete';
	$_GET['args'] = array($_GET['filename'], true);
	$_GET['locationAfter'] = 'index.php?ui=uploadArea';
	require('../lib/execute.php');
	exit();


case 'installCorpus':
case 'installCorpusIndexed':
	$_GET['function'] = 'install_new_corpus';
	/* in this case there is no point sending parameters;      */
	/* the function is better off just getting them from $_get */
	$_GET['locationAfter'] = 'XX'; /* the function itself sets this */ 
	require('../lib/execute.php');
	exit();


	/* as with previous, the function gets its "parameters" from _GET */


case 'deleteCorpus':
	if ( ! (isset($_GET['sureyouwantto']) && $_GET['sureyouwantto'] == 'yes') )
	{
		/* default back to non-function-execute-mode */
		$_GET = array();
		break;
	}
	$_GET['function'] = 'delete_corpus_from_cqpweb';
	$_GET['args'] = $_GET['corpus'];
	$_GET['locationAfter'] = 'index.php';
	require('../lib/execute.php');
	exit();


case 'newCorpusCategory':
	$_GET['function'] = 'add_corpus_category';
	/* there is just a chance a legit category label might contain #, so replace with UTF-8 sharp U+266f */
	$_GET['args'] = str_replace('#',"\xE2\x99\xAF",$_GET['newCategoryLabel']) . '#' . $_GET['newCategoryInitialSortKey'];
	$_GET['locationAfter'] = 'index.php?ui=manageCorpusCategories';
	require('../lib/execute.php');
	exit();


case 'newBatchOfUsers':
	$_GET['function'] = 'add_batch_of_users';
	$_GET['args'] = trim($_GET['newUsername']) . '#' . $_GET['sizeOfBatch'] . '#' . trim($_GET['newPassword']) . '#' . trim($_GET['batchAutogroup']);
	$_GET['locationAfter'] = 'index.php?ui=userAdmin';
	require('../lib/execute.php');
	exit();


case 'resetUserPassword':
	$_GET['redirect'] = 'resetUserPassword';
	$_GET['userFunctionFromAdmin'] = 1;
	unset($_GET['admF']);
	require('../lib/redirect.inc.php');
	exit();


case 'deleteUser':
	if (!isset($_GET['sureyouwantto']) || $_GET['sureyouwantto'] !== 'yes')
	{
		/* default back to non-function-execute-mode */
		$_GET = array();
		break;
	}
	$_GET['function'] = 'delete_user';
	$_GET['args'] = $_GET['userToDelete'] ;
	$_GET['locationAfter'] = 'index.php?ui=userAdmin';
	require('../lib/execute.php');
	exit();


case 'addUserToGroup':
	$_GET['function'] = 'add_user_to_group';
	$_GET['args'] = $_GET['userToAdd'] . '#' . $_GET['groupToAddTo'] ;
	$_GET['locationAfter'] = 'index.php?ui=groupMembership';
	require('../lib/execute.php');
	exit();


case 'removeUserFromGroup':
	$_GET['function'] = 'remove_user_from_group';
	$_GET['args'] = $_GET['userToRemove'] . '#' . $_GET['groupToRemoveFrom'] ;
	$_GET['locationAfter'] = 'index.php?ui=groupMembership';
	require('../lib/execute.php');
	exit();


case 'newGroup':
	$_GET['function'] = 'add_new_group';
	$_GET['args'] = $_GET['groupToAdd'] . '#' . str_replace('#',"\xE2\x99\xAF",$_GET['newGroupDesc']) . '#' . $_GET['newGroupAutojoinRegex'];
	$_GET['locationAfter'] = 'index.php?ui=groupAdmin';
	require('../lib/execute.php');
	exit();


case 'updateGroupInfo':
	$_GET['function'] = 'update_group_info';
	$_GET['args'] = $_GET['groupToUpdate'] . '#' . str_replace('#',"\xE2\x99\xAF",$_GET['newGroupDesc']) . '#' . $_GET['newGroupAutojoinRegex'];
	$_GET['locationAfter'] = 'index.php?ui=groupAdmin';
	require('../lib/execute.php');
	exit();


case 'groupRegexRerun':
	$_GET['function'] = 'reapply_group_regex';
	$_GET['args'] = $_GET['group'];
	$_GET['locationAfter'] = 'index.php?ui=groupMembership';
	require('../lib/execute.php');
	exit();


case 'groupRegexApplyCustom':
	$_GET['function'] = 'apply_custom_group_regex';
	$_GET['args'] = $_GET['group'] . '#' . $_GET['regex'];
	$_GET['locationAfter'] = 'index.php?ui=groupMembership';
	require('../lib/execute.php');
	exit();


case 'generateDefaultPrivileges':
	if ($_GET['corpus'] == '~~all~~')
	{
		$_GET['function'] = 'create_default_privileges_for_all_corpora';
		$_GET['args'] = '';
	}
	else if ($_GET['corpus'] == '~~noncorpus~~')
	{
		$_GET['function'] = 'create_all_default_noncorpus_privileges';
		$_GET['args'] = '';
	}
	else
	{
		$_GET['function'] = 'create_corpus_default_privileges';
		$_GET['args'] = $_GET['corpus'];
	}
	$_GET['locationAfter'] = 'index.php?ui=privilegeAdmin'; 
	require('../lib/execute.php');
	exit();


case 'newCorpusPrivilege':
	/* v rudimentary check for obvious kind of error */
	if (empty($_GET['corpus']))
		break;
	$_GET['corpus'] = preg_replace('/\W/', '', $_GET['corpus']);
	$_GET['args'] = array((int)$_GET['privilegeType'], array($_GET['corpus']), $_GET['description']) ;
	$_GET['function'] = 'add_new_privilege';
	$_GET['locationAfter'] = 'index.php?ui=privilegeAdmin';
	require('../lib/execute.php');
	exit();


case 'newFreqlistPrivilege':
	$_GET['args'] = array((int)$_GET['privilegeType'], (int)$_GET['nTokens'], $_GET['description']) ;
	$_GET['function'] = 'add_new_privilege';
	$_GET['locationAfter'] = 'index.php?ui=privilegeAdmin';
	require('../lib/execute.php');
	exit();


case 'newUploadPrivilege':
case 'newDiskForUploadsPrivilege':
case 'newDiskForCorpusPrivilege':
	$_GET['args'] = array((int)$_GET['privilegeType'], (int)$_GET['nBytes'], $_GET['description']) ;
	$_GET['function'] = 'add_new_privilege';
	$_GET['locationAfter'] = 'index.php?ui=privilegeAdmin';
	require('../lib/execute.php');
	exit();


case 'newInstallPrivilege':
	$_GET['args'] = array(
						(int)$_GET['privilegeType'], 
						(object)['max_tokens'=>(int)$_GET['nTokens'], 'plugin_id'=>(int)$_GET['pluginId']],
						$_GET['description']
					) ;
	$_GET['function'] = 'add_new_privilege';
	$_GET['locationAfter'] = 'index.php?ui=privilegeAdmin';
	require('../lib/execute.php');
	exit();


case 'newCqpBinaryPrivilege':
	$_GET['args'] = array((int)$_GET['privilegeType'], NULL, $_GET['description']) ;
	$_GET['function'] = 'add_new_privilege';
	$_GET['locationAfter'] = 'index.php?ui=privilegeAdmin';
	require('../lib/execute.php');
	exit();


case 'newExtraRuntimePrivilege':
	$_GET['args'] = array((int)$_GET['privilegeType'], (int)$_GET['nSecs'], $_GET['description']) ;
	$_GET['function'] = 'add_new_privilege';
	$_GET['locationAfter'] = 'index.php?ui=privilegeAdmin';
	require('../lib/execute.php');
	exit();


case 'updatePrivilegeDesc':
	if (empty($_GET['privilege']))
		break;
	$_GET['privilege'] = (int)$_GET['privilege'];
	$_GET['args'] = array($_GET['privilege'], $_GET['description']);
	$_GET['function'] = 'update_privilege_description';
	$_GET['locationAfter'] = 'index.php?ui=editPrivilege&privilege=' . (int)$_GET['privilege'];
	require('../lib/execute.php');
	exit();


case 'updatePrivilegeIntMax':
	$_GET['args'] = array($_GET['privilege'], preg_replace('/\D/', '', $_GET['newMax']));
	$_GET['function'] = 'update_privilege_integer_max';
	$_GET['locationAfter'] = 'index.php?ui=editPrivilege&privilege=' . (int) $_GET['privilege'];
	require('../lib/execute.php');
	exit();


case 'updateInstallPrivilegeScope':
	$_GET['args'] = array($_GET['privilege'], preg_replace('/\D/', '', $_GET['newMax']), false);
	$_GET['function'] = 'update_install_privilege_scope';
	$_GET['locationAfter'] = 'index.php?ui=editPrivilege&privilege=' . (int) $_GET['privilege'];
	require('../lib/execute.php');
	exit();


case 'editPrivAddCorpus':
	$_GET['args'] = array($_GET['privilege'], $_GET['corpus']);
	$_GET['function'] = 'add_corpus_to_privilege_scope';
	$_GET['locationAfter'] = 'index.php?ui=editPrivilege&privilege=' . (int) $_GET['privilege'];
	require('../lib/execute.php');
	exit();


case 'editPrivRemoveCorpus':
	$_GET['args'] = array($_GET['privilege'], $_GET['corpus']);
	$_GET['function'] = 'remove_corpus_from_privilege_scope';
	$_GET['locationAfter'] = 'index.php?ui=editPrivilege&privilege=' . (int) $_GET['privilege'];
	require('../lib/execute.php');
	exit();


case 'deletePrivilege':
	$_GET['function'] = 'delete_privilege';
	$_GET['args'] = (int)$_GET['privilege'];
	$_GET['locationAfter'] = 'index.php?ui=privilegeAdmin';
	require('../lib/execute.php');
	exit();


case 'newGrantToUser':
	$_GET['function'] = 'grant_privilege_to_user';
	$_GET['args'] = $_GET['user'] . '#' . (int)$_GET['privilege'];
	$_GET['locationAfter'] = 'index.php?ui=userGrants';
	require('../lib/execute.php');
	exit();


case 'newGrantToGroup':
	$_GET['function'] = 'grant_privilege_to_group';
	$_GET['args'] = $_GET['group'] . '#' . (int)$_GET['privilege'];
	$_GET['locationAfter'] = 'index.php?ui=groupGrants';
	require('../lib/execute.php');
	exit();


case 'removeUserGrant':
	$_GET['function'] = 'remove_grant_from_user';
	$_GET['args'] = $_GET['user'] . '#' . (int)$_GET['privilege'];
	$_GET['locationAfter'] = 'index.php?ui=userGrants';
	require('../lib/execute.php');
	exit();

case 'removeGroupGrant':
	$_GET['function'] = 'remove_grant_from_group';
	$_GET['args'] = $_GET['group'] . '#' . (int)$_GET['privilege'];
	$_GET['locationAfter'] = 'index.php?ui=groupGrants';
	require('../lib/execute.php');
	exit();


case 'cloneGroupGrants':
	$_GET['function'] = 'clone_group_grants';
	$_GET['args'] = $_GET['groupCloneFrom'] . '#' . $_GET['groupCloneTo'];
	$_GET['locationAfter'] = 'index.php?ui=groupGrants';
	require('../lib/execute.php');
	exit();


case 'newPlugin':
	$_GET['function'] = 'register_plugin';
	$extra = array();
	foreach ($_GET as $k => $v)
		if (!empty($v))
			if (preg_match('/^extraKey(\d+)$/', $k, $m))
				$extra[$v] = $_GET["extraVal{$m[1]}"];
	$_GET['args'] = array($_GET['class'], $_GET['description'], $extra); 
	$_GET['locationAfter'] = 'index.php?ui=pluginAdmin';
	require('../lib/execute.php');
	exit();



case 'addSystemMessage':
	$_GET['function'] = 'add_system_message';
	$_GET['args'] = $_GET['systemMessageHeading']. '#' . $_GET['systemMessageContent'];
	$_GET['locationAfter'] = 'index.php?ui=systemMessages';
	require('../lib/execute.php');
	exit();


case 'regenerateCSS':
	$_GET['function'] = 'css_regenerate_skinfiles';
	$_GET['locationAfter'] = 'index.php?ui=skins';
	require('../lib/execute.php');
	exit();


case 'transferStylesheetFile':
	$_GET['function'] = 'import_css_file';
	if (!isset($_GET['cssFile']))
	{
		header("Location: index.php?ui=skins");
		exit();
	}
	$_GET['args'] = $_GET['cssFile'];
	$_GET['locationAfter'] = 'index.php?ui=skins';
	require('../lib/execute.php');
	exit();



case 'newMappingTable':
	if(strpos($_GET['newMappingTableCode'], '#') !== false)
	{
		$_GET['args'] = "You cannot use the \"hash\" character in a mapping table.";
		/* Actually this is a lie. You can, should you really want to do something that bonkers.
		 * the problem is, rather, that then it can't be passed to execute.php ,
		 * because hash is that script's argument separator. */
		$_GET['function'] = 'exiterror';
	}
	else
	{
		$_GET['function'] = 'add_tertiary_mapping_table';
		$_GET['locationAfter'] = 'index.php?ui=mappingTables&showExisting=1';
		$_GET['args'] = $_GET['newMappingTableId'] . '#' . $_GET['newMappingTableName'] . '#' . $_GET['newMappingTableCode'] ;
	}
	require('../lib/execute.php');
	exit();

	
case 'registerEmbeddedPage':
	$_GET['function'] = 'add_new_embed';
	$_GET['args'] = [$_GET['title'],$_GET['path']];
	$_GET['locationAfter'] = 'index.php?ui=manageEmbeds';
	require('../lib/execute.php');
	exit();
	

case 'newAnnotationTemplate':
	$_GET['function'] = 'interactive_load_annotation_template';
	$_GET['locationAfter'] = 'index.php?ui=annotationTemplates';
	require('../lib/execute.php');
	exit();


case 'deleteAnnotationTemplate':
	$_GET['function'] = 'delete_annotation_template';
	$_GET['args'] = $_GET['toDelete'];
	$_GET['locationAfter'] = 'index.php?ui=annotationTemplates';
	require('../lib/execute.php');
	exit();


case 'loadDefaultAnnotationTemplates':
	$_GET['function'] = 'load_default_annotation_templates';
	$_GET['locationAfter'] = 'index.php?ui=annotationTemplates';
	require('../lib/execute.php');
	exit();


case 'newXmlTemplate':
	$_GET['function'] = 'interactive_load_xml_template';
	$_GET['locationAfter'] = 'index.php?ui=xmlTemplates';
	require('../lib/execute.php');
	exit();


case 'deleteXmlTemplate':
	$_GET['function'] = 'delete_xml_template';
	$_GET['args'] = $_GET['toDelete'];
	$_GET['locationAfter'] = 'index.php?ui=xmlTemplates';
	require('../lib/execute.php');
	exit();


case 'loadDefaultXmlTemplates':
	$_GET['function'] = 'load_default_xml_templates';
	$_GET['locationAfter'] = 'index.php?ui=xmlTemplates';
	require('../lib/execute.php');
	exit();


case 'newMetadataTemplate':
	$_GET['function'] = 'interactive_load_metadata_template';
	$_GET['locationAfter'] = 'index.php?ui=metadataTemplates';
	require('../lib/execute.php');
	exit();


case 'deleteMetadataTemplate':
	$_GET['function'] = 'delete_metadata_template';
	$_GET['args'] = $_GET['toDelete'];
	$_GET['locationAfter'] = 'index.php?ui=metadataTemplates';
	require('../lib/execute.php');
	exit();


case 'loadDefaultMetadataTemplates':
	$_GET['function'] = 'load_default_metadata_templates';
	$_GET['locationAfter'] = 'index.php?ui=metadataTemplates';
	require('../lib/execute.php');
	exit();


case 'deleteCacheLeakSubcorpus':
	/* fall through is intentional, as a cached subcorpus is just a cache file. */
case 'deleteCacheLeakFiles':
	$_GET['function'] = 'delete_stray_cache_file';
	/*an arrayas an argument neds to go int an inner array */
	$_GET['args'] = array(0=>array());
	/* fill array from form entries */
	foreach($_GET as $k => $v)
	{
		if (preg_match('/^fn_\d+$/', $k))
		{
			$_GET['args'][0][] = $v;
			unset($_GET[$k]);
			//TODO,note this (fn_UNIQID=FILE) is way better than way used below (xx_FILE=1).. Switch rest over at some point.
			// more efficien t than repeating the code, with only a diff of func
			// would be to have just one single case, PLUS a map of admF=>func_name, PLUS one of admF=>locAfter. 
			// they could alljust be dl,\d+   (for deleteleak, N) 
		}
	}
	if ('deleteCacheLeakFiles' == $_GET['admF'])
		$_GET['locationAfter'] = 'index.php?ui=queryCacheControl';
	else
		$_GET['locationAfter'] = 'index.php?ui=subcorpusCacheControl';
	require('../lib/execute.php');
	exit();


case 'deleteCacheLeakDbEntries':
	$_GET['function'] = 'delete_stray_cache_entry';
	$_GET['args'] = array(0=>array());
	foreach($_GET as $k => $v)
	{
		if ('1' == $v && preg_match('/^qn_(.+)$/', $k, $m))//'qn_' === substr($k, 0, 3))
		{
			$_GET['args'][0][] = $m[1];
			unset($_GET[$k]);
		}
	}
	$_GET['locationAfter'] = 'index.php?ui=queryCacheControl';
	require('../lib/execute.php');
	exit();


case 'deleteFreqtableLeak':
	$_GET['function'] = 'delete_stray_freqtable_part'; 
	$_GET['args'] = array(0=>array());
	foreach($_GET as $k => $v)
	{
		if ('1' == $v && preg_match('/^del_(.+)$/', $k, $m))//'del_' === substr($k, 0, 4))
		{
			$_GET['args'][0][] = $m[1];
			unset($_GET[$k]);
		}
	}
	$_GET['locationAfter'] = 'index.php?ui=freqtableCacheControl';
	require('../lib/execute.php');
	exit();


case 'deleteDbLeak':
	$_GET['function'] = 'delete_stray_db_table'; 
	$_GET['args'] = array(0=>array());
	foreach($_GET as $k => $v)
	{
		if ('1' == $v && preg_match('/^del_(.+)$/', $k, $m))
		{
			$_GET['args'][0][] = $m[1];
			unset($_GET[$k]);
		}
	}
	$_GET['locationAfter'] = 'index.php?ui=dbCacheControl';
	require('../lib/execute.php');
	exit();


case 'deleteDbLeakDbEntries':
	$_GET['function'] = 'delete_stray_db_entry';
	$_GET['args'] = array(0=>array());
	foreach($_GET as $k => $v)
	{
		if ('1' == $v && preg_match('/^dl_(.+)$/', $k, $m))
		{
			$_GET['args'][0][] = $m[1];
			unset($_GET[$k]);
		}
	}
	$_GET['locationAfter'] = 'index.php?ui=dbCacheControl';
	require('../lib/execute.php');
	exit();


case'clearQueryHistory':
	if ( ! (isset($_GET['sureyouwantto']) && $_GET['sureyouwantto'] == 'yes') )
	{
		/* default back to non-function-execute-mode */
		$_GET = array();
		break;
	}
	$_GET['function'] = 'history_total_clear';
	$_GET['args'] = array();
	$_GET['locationAfter'] = 'index.php';
	require('../lib/execute.php');
	exit();
	



default:
	/* break and fall through to the rest of adminhome.inc.php */
	break;
}

/* end of big main switch, and thus end of admin-execute */

/* if we have broken the switch rather than exiting from a case, we fall-through to adminhome.inc.php,
 * which includes() this script.  */

