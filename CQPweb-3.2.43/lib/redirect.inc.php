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
 * This script processes the different commands that can be issued by the 
 * "redirect box" -- a little dropdown that contains commands on various pages.
 * 
 * Because this dropdown goes to multiple pages, the redurect script is needed
 * to work out what page we're going to (and sometimes, to provide a filter
 * on the HTTP paramters - since the dropdown poitns to multiple functions,
 * it generates more parameters than any one function actually needs.
 * 
 * Sometimes, a script is "included" from here. In that case, anything in _GET
 * that is not explicitly cleared will be available to the included script.
 * 
 * Other times we use Location, and in that case, only the bits of _GET 
 * explicitly stuck into the URL will be available to the addressed page.
 * 
 * (Note: the only case in which Location is currently used is the "New Query" 
 * option. This could, in fact, be extended where that would make life neater.)
 * 
 */

// TODO. Get shut of this.


if (isset($_POST['redirect']) && empty($_GET['redirect']))
	$_GET['redirect'] = $_POST['redirect'];

if ( ! isset($_GET['redirect']))
{
	?>
	
	<html>
	<head>
		<title>Error!</title>
	</head>
	<body>
		<pre>
		
			ERROR: Incorrectly formatted URL, or no redirect parameter provided.
			
			<a href="index.php">Please reload CQPweb</a>.
		</pre>
	</body>
	</html>
	
	<?php
	exit();
}
else
{
	$redirect_script_redirector = $_GET['redirect'];
	unset ($_GET['redirect']);
	
	/*
	 * ======================
	 * SPECIALIST REDIRECTORS (where more than one input needs to go to the same switch-case)
	 * ======================
	 */
	
	/* (1) allow for custom plugins in concordance.php, whose redirect could be ANYTHING */
	if (substr($redirect_script_redirector, 0, 11) == 'CustomPost:')
	{
		$custom_pp_parameter = $redirect_script_redirector;
		$redirect_script_redirector = 'customPostprocess';
	}
	
	/* (2) allow for an XML attribute handle to be added to distributionDownload. */
	if (substr($redirect_script_redirector, 0, 20) == 'distributionDownload')
	{
		$_GET['redirect'] = $redirect_script_redirector;
		$redirect_script_redirector = 'distributionDownload';
	}

	
	
	
	
	
	switch($redirect_script_redirector)
	{
	
	/* from more than one control box */
	
	case 'newQuery':
		header("Location: index.php");
		break;
	/* this will be the last thing to delete once we're done */
	
	/* from control box in concordance.php */
	
	case 'thin':
		require("../lib/thin-control.inc.php");
		break;

	case 'breakdown':
		require("../lib/breakdown-ui.php");
		break;

	case 'distribution':
		require("../lib/distribution-ui.php");
		break;
		
case 'Dispersion':
require("../lib/dispersion.php");
break;
		

	case 'sort':
		$_GET['program'] = 'sort';
		$_GET['newPostP'] = 'sort';
		$_GET['newPostP_sortPosition'] = 1;
		$_GET['newPostP_sortThinTag'] = '';
		$_GET['newPostP_sortThinTagInvert'] = 0;
		$_GET['newPostP_sortThinString'] = '';
		$_GET['newPostP_sortThinStringInvert'] = 0;
		unset($_GET['pageNo']);
		require("../lib/concordance-ui.php");
		break;

	case 'collocations':
		require("../lib/colloc-options.inc.php");
		break;

	case 'download-conc':
		require("../lib/download-conc.php");
		break;

	case 'categorise':
		if (empty($_GET['sqAction']))
			$_GET['sqAction'] = 'enterCategories';
		require("../lib/savequery-ui.php");
		break;
	case 'categorise-do':
		require("../lib/savequery-act.php");
		break;

/* this option used to come from many places, but now only from concordance.php. */
	case 'savequery':
		require("../lib/savequery-ui.php");
		break;

	case 'customPostprocess':
		$_GET['newPostP'] = $custom_pp_parameter;
		unset($_GET['pageNo']);
		require("../lib/concordance-ui.php");
		break;


	/* from control box in context.php */
	
	case 'fileInfo':
		require("../lib/textmeta-ui.php");
		break;
		
	case 'moreContext':
		if (isset($_GET['contextSize']))
			$_GET['contextSize'] += 100;
		require("../lib/context-ui.php");
		break;
		
	case 'lessContext':
		if (isset($_GET['contextSize']))
			$_GET['contextSize'] -= 100;
		require("../lib/context-ui.php");
		break;
		
	case 'backFromContext':
		require("../lib/concordance-ui.php");
		break;



	/* from control box in distribution.php */
	
	case 'backFromDistribution':
		require("../lib/concordance-ui.php");
		break;
	
	case 'refreshDistribution':
	case 'distributionDownload':
		require("../lib/distribution-ui.php");
		break;
		
		
		
	/* from control box in collocation.php */

	case 'backFromCollocation':
		require("../lib/concordance-ui.php");
		break;

	case 'rerunCollocation':
		require("../lib/collocation-ui.php");
		break;

	case 'collocationDownload':
		$_GET['tableDownloadMode'] = 1;
		require("../lib/collocation-ui.php");
		break;
		


	/* from control box in keywords.php */
	
	case 'newKeywords':
		header("Location: index.php?ui=keywords");
		exit;

	case 'downloadKeywords':
		$_GET['downloadMode'] = 1;
		require("../lib/keywords-ui.php");
		break;
		
	case 'kwCloudGraphix':
		unset($_GET['redirect']);
		$_GET['kwWhatToShow'] = 'onlyPos';
		$_GET['kwRender'] = 'clGraphix';
		require("../lib/keywords-ui.php");
		break;
		
	case 'kwCloudWmatrix':
		unset($_GET['redirect']);
		$_GET['kwWhatToShow'] = 'onlyPos';
		$_GET['kwRender'] = 'clWmatrix';
		require("../lib/keywords-ui.php");
		break;
	
	case 'showAll':
		unset($_GET['redirect']);
		$_GET['kwWhatToShow'] = 'allKey';
		unset($_GET['pageNo']);
		require('../lib/keywords-ui.php');
		break;
		
	case 'showPos':
		unset($_GET['redirect']);
		$_GET['kwWhatToShow'] = 'onlyPos';
		unset($_GET['pageNo']);
		require('../lib/keywords-ui.php');
		break;
		
	case 'showNeg':
		unset($_GET['redirect']);
		$_GET['kwWhatToShow'] = 'onlyNeg';
		unset($_GET['pageNo']);
		require('../lib/keywords-ui.php');
		break;
		
	case 'showLock':
		unset($_GET['redirect']);
		$_GET['kwWhatToShow'] = 'lock';
		unset($_GET['pageNo']);
		require('../lib/keywords-ui.php');
		break;
		


	/* from control box in breakdown.php */
	
	case 'concBreakdownWords':
		unset($_GET['concBreakdownWords']);
		$_GET['concBreakdownOf'] = 'words';
		require("../lib/breakdown-ui.php");
		break;

	case 'concBreakdownAnnot':
		unset($_GET['concBreakdownAnnot']);
		$_GET['concBreakdownOf'] = 'annot';
		require("../lib/breakdown-ui.php");
		break;

	case 'concBreakdownBoth':
		unset($_GET['concBreakdownBoth']);
		$_GET['concBreakdownOf'] = 'both';
		require("../lib/breakdown-ui.php");
		break;

	case 'concBreakdownDownload':
		unset($_GET['concBreakdownDownload']);
		$_GET['tableDownloadMode'] = 1;
		require("../lib/breakdown-ui.php");
		break;

	case 'concBreakdownPositionSort':
		$_GET['newPostP_sortPosition'] = $_GET['concBreakdownAt'];
		/* all rest is shared with node-sort, so fall through.... */
			
	case 'concBreakdownNodeSort':
		/* nb no sanitisation of qname needed, will be done by the Concordance program */
		$_GET['program'] = 'sort';
		$_GET['newPostP'] = 'sort';
		/* this  checks the above...  */
		if (empty($_GET['newPostP_sortPosition']))
			$_GET['newPostP_sortPosition'] = 0;
		$_GET['newPostP_sortThinTag'] = '';
		$_GET['newPostP_sortThinTagInvert'] = 0;
		$_GET['newPostP_sortThinString'] = '';
		$_GET['newPostP_sortThinStringInvert'] = 0;
		$_GET['newPostP_sortThinStringInvert'] = 0;
		require("../lib/concordance-ui.php");
		break;









	default:
		?>
		<html>
		<head><title>Error!</title></head>
		<body>
			<pre>
			
			ERROR: Redirect type unrecognised.
			
			<a href="index.php">Please reload CQPweb</a>.
			</pre>
		</body>
		</html>
		<?php
		break;
	}
	/* end of switch */
}

/*
 * =============
 * END OF SCRIPT
 * =============
 */
