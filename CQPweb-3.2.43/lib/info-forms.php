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
 * Each of these functions prints a table for the right-hand side interface.
 * 
 * This file contains the functions joint to queryhome and userhome.  
 */



function do_ui_embed_page()
{
	if (!isset($_GET['id']))
		exiterror("Embedded page was requested, but no page was specified!");
	
	if (false === ($embed = get_embed_info($_GET['id'])))
		exiterror("Embedded page was requested, but no embed with the given ID exists.");
	
	if (!is_readable($embed->file_path))
		exiterror("Embedded page was requested, but the specified file does not exist or is unreadable.");
	
?>

<table class="concordtable fullwidth">

	<tr>
		<th class="concordtable"><?php echo escape_html($embed->title); ?></th>
	</tr>

	<tr>
		<td class="concordgeneral">

		<?php include ($embed->file_path); ?>
		
		</td>
	</tr>
	
</table>

<?php
}






function do_ui_credits()
{
?>

<table class="concordtable fullwidth">

	<tr>
		<th class="concordtable">Publications about CQPweb</th>
	</tr>

	<tr>
		<td class="concordgeneral">
		
			<p>
				If you use CQPweb in your published research, it would be very much appreciated if you could
				provide readers with a reference to the following paper:
			</p>
			
			<ul>
				<li>
					Hardie, A (2012) <strong>CQPweb - combining power, flexibility 
					and usability in a corpus analysis tool</strong>. 
					<em>International Journal of Corpus Linguistics</em> 17&nbsp;(3): 380&ndash;409.
					<a href="http://www.ingentaconnect.com/content/jbp/ijcl/2012/00000017/00000003/art00004" target="_blank">
						[Full text on publisher's website]
					</a>
					<a href="http://www.lancaster.ac.uk/staff/hardiea/cqpweb-paper.pdf" target="_blank">
						[Alternative source for paper download]
					</a>
				</li>
			</ul>
						
			<p><a href="http://cwb.sourceforge.net/doc_links.php">Click here for other references relating to Corpus Workbench software.</a></p>
			
			<p>&nbsp;</p>
			
		</td>
	</tr>

	<tr>
		<th class="concordtable">Who did it?</th>
	</tr>

	<tr>
		<td class="concordgeneral">
		
			<p>CQPweb was created by Andrew Hardie (Lancaster University).</p>
				
			<p>
				Most of the architecture, the look-and-feel, and even some snippets of code
				were shamelessly half-inched from <em>BNCweb</em>.
			</p>
			
			<p>
				BNCweb's most recent version was written by Sebastian Hoffmann 
				(University of Trier) and Stefan Evert (FAU Erlangen-Nuremberg). 
				It was originally created by Hans-Martin Lehmann, 
				Sebastian Hoffmann, and Peter Schneider.
			</p>
			
			<p>The underlying technology of CQPweb is manifold.</p>
			
			<ul>
				<li>
					Indexing and querying is done using the <a target="_blank" href="http://cwb.sourceforge.net/">IMS Corpus Workbench</a> with its
					<a target="_blank" href="http://cwb.sourceforge.net/files/CQP_Tutorial/">CQP corpus query processor</a>.
					Thus the name.
					<br>&nbsp;
				</li>
				<li>
					Other functions (collocations, corpus management etc.) are powered by
					<a target="_blank" href="http://www.mysql.com/">MySQL</a> databases.
					<br>&nbsp;
				</li>
				<li>
					The web-scripts are written in <a target="_blank" href="http://www.php.net/">PHP</a>.
					<br>&nbsp;
				</li>
				<li>
					<a target="_blank" href="http://www.w3schools.com/JS">JavaScript</a>
					is used to make the data displays more interactive.
					<br>&nbsp;
				</li>
				<li>
					The look-and-feel relies on <a target="_blank" href="http://www.w3schools.com/css">Cascading Style Sheets</a>
					plus good old fashioned <a target="_blank" href="http://www.w3schools.com/html">HTML</a>.
					<br>&nbsp;
				</li>
			</ul>			
			
			<p>CQPweb uses the following external JavaScript libraries:</p>
			
			<ul>
				<li><a target="_blank" href="https://jquery.com">jQuery</a></li>
				<li><a target="_blank" href="https://wordcloud2-js.timdream.org">wordcloud2.js</a></li>
				<li><a target="_blank" href="https://d3js.org">d3.js</a></li>
			</ul>
		</td>
	</tr>
</table>

<?php
}





function do_ui_latest()
{
?>

<table class="concordtable fullwidth">

	<tr>
		<th class="concordtable">Latest news</th>
	</tr>

	<tr>
	
	<td class="concordgeneral">

	<p>&nbsp;</p>


	<ul>
		<li>
		<p><strong>Version 3.2.43</strong>, 2021-03-01</p>
		<p>
		Version bump for a year's worth of bug fixes, including some pretty major ones. 
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.42</strong>, 2020-03-12</p>
		<p>
		Assorted bug fixes. 3.2 is now feature-frozen and will only receive bug-fix updates.
		<br>
		New features will be added to v3.3 only.
		</p>
		</li>

 		<li>
		<p><strong>Version 3.2.41</strong>, 2020-01-09</p>
		<p>
		Finalised the (undocumented) &ldquo;run CQPweb apps&rdquo; tool.
		</p>
		<p>
		Improved memory usage in subcorpus manipulation.
		</p>
		<p>
		Fixed a ton of bugs, including some major issues in distribution tables.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.40</strong>, 2019-09-17</p>
		<p>
		Extended and streamlined the distribution tool to do a larger range of operations based on XML.
		</p>
		<p>
		Did some subcorpus-management optimisation.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.39</strong>, 2019-07-04</p>
		<p>
		Added a command-line script to install a corpus.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.38</strong>, 2019-06-12</p>
		<p>
		Revamped corpus access permissions for keywords, to make matching up a greater range of datasets possible.
		</p>
		<p>
		Made the new, internal CEQL parser into the default (so CWB-Perl is no longer necessary to run CQPweb). 
		</p>
		<p>
		Began integration work on the new Dispersion tool (code contributed by Andressa Gomide).
		</p>
		<p>
		Fixed multiple bugs.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.37</strong>, 2019-06-07</p>
		<p>
		Standardised the back-end data formats; also fixed some problems with one of the plugins.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.36</strong>, 2019-05-31</p>
		<p>
		Added the "embed-page" tool as per feature request.
		</p>
		<p>
		Added a tool for administrators to view users' upload areas.
		</p>
		<p>
		Fixed multiple bugs.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.35</strong>, 2019-05-27</p>
		<p>
		Added the "lock password" tool for administrators to stop users changing their passwords.
		</p>
		<p>
		Added a button for administrators to reset all of a user's preferences to default values.
		</p>
		<p>
		Fixed multiple bugs.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.34</strong>, 2019-05-07</p>
		<p>
		Added the "colleaguation" networking system for data sharing (experimental only).
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.33</strong>, 2019-04-24</p>
		<p>
		Many, many bugfixes from the previous release.
		</p>
		<p>
		Updated the required version of PHP from 5.x to 7.0 (the latter itself being out of date but still present in Debian).
		</p>
		<p>
		Made improvements to the CQP class making it easier to set system options, change registry, change data directory, etc.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.32</strong>, 2019-04-15</p>
		<p>
		Added a long-requested major new feature: users can now install their own corpora.
		</p>
		<p>
		Revamped the plugin system from the ground up, and added administrative tools for managing plugins, 
		as well as some built-in demo plugins.
		</p>
		<p>
		Added wordcloud-view to the keywords tool.
		</p>
		<p>
		Added new built-in corpus statistic: 1,000 token Standardised TTR. 
		</p>
		<p>
		Added a new collocation statistic: conservative estimate of Log Ratio (credit: Stefan Evert).
		Also added this statistic to the keywords tool.
		</p>
		<p>
		Changed the default collocation statistic and the default keywords statistic to	Log Ratio. 
		</p>
		<p>
		Added tool to manipulate Saved Queries by merging two existing queries into one. 
		</p>
		<p>
		Added a built-in CEQL parser, allowing the dependency on Perl to be ended.
		</p>
		<p>
		Added additional, more complex methods for defining variables for multivariate analysis.
		</p>
		<p> 
		Added programmatic access to CQPweb via an API (which has, so far, only a few unexciting functions;
		but more is on the way!)
		</p>
		<p> 
		Added better tools for measuring disk usage for database tables, as well as a UI to 
		optimise tables that are wasting a lot of disk space.
		</p>
		<p> 
		Added an internal replacement for the now-very-old wztooltip.js library for rendering tips.
		</p>
		<p> 
		Changed the required version of the CWB core to 3.4.10 or later.
		</p>
		<p> 
		Fixed an edge-case bug related to the collation of handles in various table fields. 
		</p>
		<p>
		Ended support for Internet Explorer; the browsers for which bugs will be accepted 
		are henceforth Edge, Chrom[e,ium], Safari, and Firefox.
		</p>
		</li>


		<li>
		<p><strong>Version 3.2.31</strong>, 2017-10-04</p>
		<p>
		Completed the feature that adds new data to a corpus without re-indexing it 
		(this can now be done for p-attributes as well as s-attributes and corpus metadata). 
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.30</strong>, 2017-10-04</p>
		<p>
		Added an alternative method to insert new p/s-attributes: via rescanning the corpus registry file.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.29</strong>, 2017-09-29</p>
		<p>
		Added a new method of corpus export: separate text files within a zip archive.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.28</strong>, 2017-09-28</p>
		<p>
		Added a way to add new data to a corpus without re-indexing it (s-attributes and corpus metadata). 
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.27</strong>, 2017-09-25</p>
		<p>
		Revamped the Distribution system to allow for non-text-based Distrbution statistics. 
		</p>
		<p>
		Fixed a longstanding and embarrassing potential security hole (non-use of CQP QueryLock for user queries).
		</p>
		<p>
		Added Lexical Growth graph-drawing function as first of an anticipated series of data visualisation tools.
		</p>
		<p>
		Added a system for XML visualisation templates.
		</p>
		<p>
		Added an option for users to get frequency-list downloads in AntConc-compatible format.
		</p>
		<p>
		Added a control to the search form allowing the CQP match-strategy to be set.
		</p>
		<p>
		Tweaked some database structures in an attempt to optimise.
		</p>
		<p>
		Fixed a CEQL bug with queries of the form {LEMMA/TAG}: the fallback to searching secondary
		plus tertiary annotations was not working, now it is.
		</p>
		<p>
		Fixed multiple other bugs, including a potential data-shadow in the Restriction cache.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.26</strong>, 2016-12-23</p>
		<p>
		Added access to CQP binary files for privileged users (for archiving, etc. purposes).
		</p>
		<p>
		Added administrative tools for creating and editing user privileges.
		</p>
		<p>
		Added additional admin tools for controlling user login sessions, as well as some extra security
		measures to the change-password mechanism.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.25</strong>, 2016-12-15</p>
		<p>
		Added a <em>new feature</em> (yay!): experimental lexical growth curve analysis system.
		</p>
		<p>
		Fixed a bug that stopped XML values containing spaces rendering correctly in the concordance.
		</p>
		<p>
		Fixed a number of bugs in the storage of subcorpora containing very many texts selected individually
		or using the <em>scan text metadata</em> tool.
		</p>
		<p>
		Added a tool in the administrator's interface to delete the entire query history 
		(thus, setting the usage statistics back to empty values). 
		</p>
		<p>
		Reorganised the text metadata management screen. 
		</p>
		<p>
		Improved error messages in the save-query system.
		</p>
		<p>
		Improved the gizmos for monitoring disk usage: query cache control now distinguishes user-saved 
		data from deletable cache data, user profile view says how much disk space they are using for 
		saved/categorised queries.
		</p>
		<p>
		(v 3.2.24 was a partial way-point towards 3.2.25, it gets no separate entry.)
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.23</strong>, 2016-08-08</p>
		<p>
		Made parallel-corpus view work properly in categorise-query mode.
		</p>
		<p>
		Made the limit on the size of file a user can upload configurable via the privilege system.
		</p>
		<p>
		Fixed one or two bugs.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.22</strong>, 2016-07-31</p>
		<p>
		Added support for alignment attributes: 
		display of one parallel corpus matching-region is possible in concordance/context view, 
		inclusion of multiple parallel corpus matching-regions is possible in concordance download.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.21</strong>, 2016-07-04</p>
		<p>
		Fixed a critical bug in the code calculating confidence intervals for keywords and lockwords.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.20</strong>, 2016-07-01</p>
		<p>
		Added XML visualisation in concordance download.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.19</strong>, 2016-06-29</p>
		<p>
		Added the ability to create subcorpora from sub-text regions containing a query hit.
		</p>
		<p>
		Added metadata view for XML-based ID-link metadata (e.g. speaker metadata in spoken corpora).
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.18</strong>, 2016-06-25</p>
		<p>
		Reimplemented the XML visualisation system.
		</p>
		<p>
		Made breaking paragraphs after punctuation in extended context view optional, rather than always implemented.
		</p>
		<p>
		Allowed "extra code files" (JS/CSS) to be added to enhance the visualisations.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.17</strong>, 2016-06-03</p>
		<p>
		Fixed a bug affecting the installation of pre-indexed corpora.
		</p>
		<p>
		Fixed a critical bug in the Frequency Breakdown that was getting "words to the Left" and "words to the Right" the wrong way round.
		</p>
		<p>
		Cleaned up the user management interface a bit.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.16</strong>, 2016-06-02</p>
		<p>
		Fixed a small but critical bug affecting stopping the system running on PHP version 7.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.15</strong>, 2016-05-31</p>
		<p>
		Tweaked the user-permissions lookup system to make pages load faster.
		</p>
		<p>
		Fixed some miscellaneous bugs, including one in the log-on system.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.14</strong>, 2016-04-04</p>
		<p>
		Added the &ldquo;export corpus&rdquo; function.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.13</strong>, 2016-04-03</p>
		<p>
		Fixed the opcode cache monitor to work with newer versions of PHP.
		</p>
		<p>
		Added a cache monitor for stray temporary tables.
		</p>
		<p>
		Made some background tweaks to speed up performance.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.12</strong>, 2016-03-23</p>
		<p>
		Added a readout of the amount of disk space used by each corpus, and a database-cache monitor.
		</p>
		<p>
		Made it possible to add readable descriptions for XML idlink categories.
		</p>
		<p>
		Added a tool to allow the administrator to upgrade the database format to InnoDB.
		</p>
		<p>
		Added &ldquo;switched off&rdquo; mode for use during database upgrades etc.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.11</strong>, 2016-03-21</p>
		<p>
		This is a (hopefully) stable version, prior to some upcoming extensive changes in 3.2.12.
		</p>
		<p>
		Fixed a lingering bug in the upgrade process.
		</p>
		<p>
		Fixed an edge-case bug in the collocation function.
		</p>
		<p>
		Reorganised the superuser's cache-control functions for the different types of cached data.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.10</strong>, 2016-03-06</p>
		<p>
		Added restricted-query data caching to improve performance.
		</p>
		<p>
		Addressed a number of other performance-related issues.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.9</strong>, 2016-03-02</p>
		<p>
		Multiple bug fixes for the previously-added subcorpus/restricted query features.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.8</strong>, 2016-02-19</p>
		<p>
		More big internal reorganisation.
		</p>
		<p>
		Added query restriction by conditions on corpus XML. 
		</p>
		<p>
		Added, likewise, subcorpus creation by conditions on corpus XML.
		</p>
		<p>
		(v 3.2.7 was a partial way-point towards 3.2.8, it gets no separate entry.)
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.6</strong>, 2016-02-06</p>
		<p>
		More effort to rework the internals for XML support.
		</p>
		<p>
		By popular demand: added back the "create batch of accounts" tool in the admin interface.
		</p>
		<p>
		Added "alternative view" to extended context, allowing historical corpora to show original spelling. 
		</p>
		<p>
		Improved the interface for managing user accounts a bit.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.5</strong>, 2016-01-23</p>
		<p>
		Fixed numerous minor bugs.
		</p>
		<p>
		Made the tabulation-download system able to access s-attributes.
		</p>
		<p>
		Reworked some of the internals as a stepping stone to (yet more) XML support.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.4</strong>, 2015-11-19</p>
		<p>
		Finished (for now) the XML metadata management functions.
		</p>
		<p>
		Added plain-text download of feature matrices.
		</p>
		<p>
		Added monochrome view to assist visually-impaired accessibility.
		</p>
		<p>
		Rewrote the Distribution function for full BNCweb-style functionality (plus a fix to the broken sort buttons).
		</p>
		<p>
		Fixed a potential security bug in the signup and persistent-login systems.
		</p>
		<p>
		Fixed multiple minor bugs from the big 3.2 upgrade.
		</p>
		<p>
		Fixed a bug affecting thinned queries, and another affecting collocate highlighting in concordances.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.3</strong>, 2015-10-15</p>
		<p>
		Added new feature: restricted access to corpora.
		</p>
		<p>
		Metadata templates now work properly.
		</p>
		<p>
		Added list of CWB attributes to query forms (displays when "CQP syntax" is selected).
		</p>
		<p>
		Fixed more bugs.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.2</strong>, 2015-10-09</p>
		<p>
		Fixed a cluster of bugs, including some critical, in the corpus setup process. 
		</p>
		<p>
		Added metadata templates (some functionality incomplete).
		</p>
		<p>
		And finally: added two new colour schemes just for the hell of it (there's more to life than databases, tha knows). 
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.2.1</strong>, 2015-10-07</p>
		<p>
		Reorganised the "manage metadata" functions.
		</p>
		<p>
		Added management functions (not yet entirely complete!) for XML metadata.
		</p>
		<p>
		Added potted explanations of collocation statistics to the collocation screen.
		</p>
		<p>
		Fixed a serious bug in the Distribution / Restricted Query functions, and a minor bug in the keywords download format for Log Ratio.
		</p>
		</li>

		<li>
		<p><strong>Version 3.2.0</strong>, 2015-10-01</p>
		<p>
		Reorganised the architecture in preparation for some new features.
		</p>
		<p>
		Added new and better user account management tools in the admin control panel.
		</p>
		<p>
		Rewrote indexing of s-attributes to support improved XML metadata features (upcoming!)
		</p>
		<p> 
		Added new feature of XML templates: pre-set patterns of XML elements/attributes.
		</p>
		<p>
		Indexing of a corpus can now be done using a template for XML. 
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.16</strong>, 2015-06-08</p>
		<p>
		Fixed a number of bugs in the frequency-list generation functions.
		</p>
		<p>
		Fixed a bug causing an inordinate number of warning messages to be printed.
		</p>
		<p>
		Improved the display of factor analysis output.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.15</strong>, 2015-03-30</p>
		<p>
		Added embedded-image (or, embedded webpage) functions for text metadata.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.14</strong>, 2015-03-25</p>
		<p>
		Drastically improved the "cache control" and "monitor MySQL" interfaces in the Admin control panel.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.13</strong>, 2015-01-30</p>
		<p>
		Fixed a critical bug (namespace clash with PHP's "intl" module).
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.12</strong>, 2015-01-09</p>
		<p>
		Wrote new help system: YouTube tutorial videos are now directly embedded in the "Help" pages.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.11</strong>, 2014-11-18</p>
		<p>
		Bug fix update (bugs affecting frequency lists, error reporting, and other fairly dull stuff).
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.10</strong>, 2014-09-03</p>
		<p>
		Some minor performance tweaks and improved error reporting in the background.
		</p>
		<p>
		Updated the bug report screen.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.9</strong>, 2014-06-20</p>
		<p>
		Added new feature: factor analysis of a feature matrix derived from saved queries.
		</p>
		<p>
		Reorganised the JavaScript code to allow for a more user-friendly interface. 
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.8</strong>, 2014-06-16</p>
		<p>
		Added new feature: adjust individual user permissions on creation of frequency lists for subcorpora.
		</p>
		<p>
		Updated frequency breakdown to enable breakdown of any concordance position within 5 tokens (as well as the node).
		</p>
		<p>
		Fixed keyword download bug (no confidence interval prinout).
		</p>
		<p>
		Fixed critical cache leak bug.
		</p>
		<p>
		Improved cache control display.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.7</strong>, 2014-04-28</p>
		<p>
		Reorganised the keyword output screen to give a richer analysis.
		</p>
		<p>
		Added experimental effect-size statistics for keyness: Log Ratio unfiltered, and Log Ratio with LL or CI Filter.
		</p>
		<p>
		Added tool to extract lockwords using Log Ratio.
		</p>
		<p>
		Added the same Log Ratio with LL filter to the Collocations tool.
		</p>
		<p>
		Fixed a bug affecting collocation/sorting done on uploaded queries.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.6</strong>, 2014-04-11</p>
		<p>
		Fixed a bug affecting collocations in subcorpora of very large corpora and making the process take hours to run. 
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.5</strong>, 2014-03-31</p>
		<p>
		Added annotation templates, and interface for controlling them.
		</p>
		<p>
		Added basic cache control mechanism to admin interface.
		</p>
		<p>
		Added query-page link to YouTube video tutorials.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.4</strong>, 2014-02-11</p>
		<p>
		Added CAPTCHA to account-creation process.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.3</strong>, 2014-02-03</p>
		<p>
		Added bulk-add function for assigning users to groups <em>en masse</em>.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.2</strong>, 2014-01-31</p>
		<p>
		Gave the admin control panel a spring-clean, and added a facility to monitor the PHP opcode cache.
		Plus more bug fixes!
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.1</strong>, 2014-01-20</p>
		<p>
		Fixes for the inevitable bugs following a large update.
		</p>
		</li>

		<li>
		<p><strong>Version 3.1.0</strong>, 2014-01-20</p>
		<p>
		Revamped user account system.
		</p>
		<p>
		Added a script to automatically upgrade an existing CQPweb MySQL database to match a more recent version of the code.
		</p>
		<p>
		Added a script to import user groups from the old system.
		</p>
		<p>
		Added a script to import group privileges from the old system.
		</p>
		<p>
		Fixed bug affecting use of XML tags in CEQL queries.
		</p>
		<p>
		Rewrote configuration file format and added documentation to system administrator's manual.
		</p>
		<p>
		Many other miscellaneous tweaks, improvements and architectural changes.
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.16</strong>, 2013-12-24</p>
		<p>
		Fixed two minor bugs in the concordance download function.
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.15</strong>, 2013-11-20</p>
		<p>
		Improved background handling of frequency lists (no changes a user would notice).
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.14</strong>, 2013-11-18</p>
		<p>
		Added protection against users compiling very, very large frequency tables for subcorpora or on-the-fly for collocations.
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.13</strong>, 2013-11-04</p>
		<p>
		Implemented context-width restrictions for limited-license corpora.
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.12</strong>, 2013-11-02</p>
		<p>
		Updated database template for newer MySQL servers.
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.11</strong>, 2013-08-30</p>
		<p>
		New feature: non-classification metadata fields can now be included in a concordance-download. 
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.10</strong>, 2013-04-22</p>
		<p>
		Added some extra protection against possible XSS (cross-site-scripting) attacks. 
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.0.9</strong>, 2013-04-06</p>
		<p>
		Added a new feature: queries can now be downloaded as &quot;tabulations&quot;. 
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.0.8</strong>, 2013-03-22</p>
		<p>
		Added a debugging backtrace to the error messages seen by superusers.
		</p>
		<p>
		Added Yates' continuity correction to the calculation of Z-score in the Collocation function.
		</p>
		<p>
		The usual miscellaneous bug fixes, including one affecting character encoding.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.0.7</strong>, 2013-03-19</p>
		<p>
		Fixed a bug affecting creation of batches of user accounts. 
		</p>
		<p>
		Fixed a bug causing the number of hits in a categorised query to be displayed incorrectly.
		</p>
		<p>
		Fixed a bug causing insertion of line-breaks into queries with long lines.
		</p>
		<p>
		Fixed an inconsistency in how batches of usernames are created.
		</p>
		<p>
		Fixed a bug in the management of user groups, plus a bug affecting the installation of
		corpora that are not in UTF-8.
		</p>
		<p>
		Fixed a bug in the install/delete corpus procedures which made deletion of a corpus
		difficult if its installation had previously failed halfway through.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.0.6</strong>, 2012-05-15</p>
		<p>
		More bug fixes.
		</p>
		<p>
		Added a new feature: a full file-by-file distribution table can now be downloaded.
		</p>
		<p>
		Adjusted the Distribution interface to make it more like the Collocations interface.
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.0.5</strong>, 2012-02-19</p>
		<p>
		Just bug fixes, but major ones!
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.0.4</strong>, 2012-02-10</p>
		<p>
		New feature: optional position labels in concordance (just like "sentence numbers" in BNCweb) 
		(this feature originally planned for 3.0.3 but not complete in that release). 
		</p>
		<p>
		Extended the XML visualisation system to allow conditional visualisations (ditto).
		</p>
		<p>
		XML visualisations now actually appear in the concordance (but only paritally rendered: they look like raw XML).
		</p>
		</li>
		
		<li>
		<p><strong>Version 3.0.3</strong>, 2012-02-05</p>
		<p>
		Mostly a boring bug-fix release, with only one new feature: users can now 
		customise their default thin-mode setting. 
		</p>
		<p>
		Fixed a bug in concordance download function that was scrambling links to context.		
		</p>
		<p>
		Fixed a bug in categorisation system that allowed invalid category names to be created.
		</p>
		<p>
		Fixed a bug in frequency list creation that introduced forms in the wrong character set
		into the database.
		</p>
		<p>
		Fixed a bug in the keyword function's frequency table lookup process.
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.2</strong>, 2011-08-28</p>
		<p>
		Added the long-awaited "upload user's own query" function.
		</p>
		<p>
		Finished the administrator's management of XML visualisations. Coming next, implementation in concordance view.
		</p>
		<p>
		Made it possible for a user to have the same saved-query name in two different corpora.
		</p>
		<p>
		Fixed a bug that made non-reproducible random thinning, actually always reproducible!
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.1</strong>, 2011-08-20</p>
		<p>
		Implemented a better system for sorting corpora into categories on the homepage.
		</p>
		<p>
		Fixed a fairly nasty bug that was blocking corpus indexing.
		</p>
		<p>
		Fixed an uninformative error message when textual restrictions are selected that no texts actually 
		match (zero-sized section of the corpus). The new error message explains the issue more clearly.
		</p>
		</li>

		<li>
		<p><strong>Version 3.0.0</strong>, 2011-07-18</p>
		<p>
		New feature: custom postprocess plugins!
		</p>
		<p>
		Fixed some bugs in unused parts of the CQP interface.
		</p>
		<p>
		Added support for all ISO-8859 character sets.
		</p>
		<p>
		Version number bumped to 3.0.0 to match new CWB versioning rules, though CQPweb is in fact now
		compatible with the post-Unicode versions of CWB (3.2.0+).
		</p>
		</li>

		<li>
		<p><strong>Version 2.17</strong>, 2011-05-18</p>
		<p>
		Fixed a fairly critical (and very silly) bug that was blocking compression of indexed corpora.
		</p>
		<p>
		Added extra significance-threshold options for keywords analysis.
		</p>
		</li>

		<li>
		<p><strong>Version 2.16</strong>, 2011-03-08</p>
		<p>
		Added a workaround for a problem that arises with some MySQL security setups.
		</p>
		<p>
		Added an optional RSS feed of system messages, and made links in system messages display correctly
		both within webpages and in the RSS feed.
		</p>
		<p>
		Created a storage location for executable command-line scripts that perform offline administration
		tasks (in a stroke of unparalleled originality, I call it "bin").
		</p>
		<p>
		Added customisable headers and logos to the homepage (a default CWB logo is supplied).
		</p>
		<p>
		Fixed a bug in right-to-left corpora (Arabic etc.) where collocations were referred to as being "to
		the right" or "to the left" of the node even though this was wrong by about 180 degrees.
		</p>
		</li>

		<li>
		<p><strong>Version 2.15</strong>, 2010-12-02</p>
		<p>
		Licence switched from GPLv3+ to GPLv2+ to match the rest of CWB. Some source files remain to be updated!
		</p>
		<p>
		A framework for "plugins" (semi-freestanding programlets) has been added. Three types of
		plugins are envisaged: transliterator plugins, annotator plugins, and format-checker plugins. Some
		"default" plugins will be supplied later.
		</p>
		<p>
		Some tweaks have been made to the concordance download options, in particular, giving a new default
		download style (&ldquo;field-data-style&rdquo;).
		</p>
		<p>
		For the adminstrator, there is a new group-access-cloning function.
		</p>
		<p>
		The required version of CWB has been dropped back down to a late v2, but you still need 3.2.x
		if you want UTF-8 regular expression matching to work properly in all languages.
		</p>
		<p>
		Improvements to query cache management internals.
		</p>
		<p>
		Plus the usual bug fixes, including some that deal with security issues, and further work on the R
		interface.
		</p>
		</li>

		<li>
		<p><strong>Version 2.14</strong>, 2010-08-27</p>
		<p>
		Quite a few new features this time. First, finer control over concordance display has been added;
		if you have the data, you can how have concordance results rendered as three-line-examples (field
		data or typology style with interlinear glosses).
		</p>
		<p>
		The R interface is ready for use with this version, although it is not actually used anywhere yet, and
		additional interface methods will be added as the need for them becomes evident. It goes without saying 
		that you need R installed in order to do anything with this.
		</p>
		<p>
		The new Web API has been established, and the first two functions "query" and "concordance" created.
		Documentation for the Web API is still on the to-do list, and it's not quite ready for use...
		</p>
		<p>
		Plus, a new function for creating snapshots of the system (useful for making backups); a "diagnostic"
		interface for checking out common problems in setting up CQP (incomplete as yet); and some improvements
		to the documentation for system administrators.
		</p>
		<p>
		Also added a new subcorpus creation function which makes one subcorpus for every text in the corpus.
		</p>
		<p>
		
		<li>
		<p><strong>Version 2.13</strong>, 2010-05-31</p>
		<p>
		Increased required version of CWB to 3.2.0 (which has Unicode regular expression matching). This means
		that regular expression wildcards will work properly with non-Latin alphabets.
		</p>
		<p>
		Also added a function to create an "inverted" subcorpus (one that contains all the texts in the corpus
		except those in a specified existing subcorpus).
		</p>
		<p>
		Plus, as ever, more bug fixes and usability tweaks.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.12</strong>, 2010-03-19</p>
		<p>
		Added first version of XML visualisation.
		</p>
		<p>
		Also made distribution tables sortable on frequency or category handle (latter remains the default). 
		</p>
		<p>
		Also added support for CQP macros and for configurable context
		width in concordances (including xml-based context width as well as word-based context width).
		</p>
		<p>
		Plus many bug fixes and minor tweaks.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.11</strong>, 2010-01-20</p>
		<p>
		First release of 2010! CQPweb is now two years old.
		</p>
		<p>
		Added improved group access management, and a setting allowing corpora to be processed 
		in a case-sensitive way throughout (not recommended in general, but potentially useful 
		for some languages e.g. German).
		<br> 
		Also added a big red warning that pops up when a user types an invalid character in a 
		"letters-and-numbers-only" entry on a form.
		<br>
		Plus lots of bug fixes.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.10</strong>, 2009-12-18</p>
		<p>
		Added customisable mapping tables for use with CEQL tertiary-annotations.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.09</strong>, 2009-12-13</p>
		<p>
		New metadata-importing functions and other improvements to the internals of CQPweb.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.08</strong>, 2009-11-27</p>
		<p>
		Updated internal database-query interaction. As a result, CQPweb requires CWB version 2.2.101 or later.
		<br>
		Other changes (mostly behind-the-scenes):  enabled Latin-1 corpora; accelerated concordance display 
		by caching number of texts in a query in the database; plus assorted bug fixes.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.07</strong>, 2009-09-08</p>
		<p>
		Fixed a bug in context display affecting untagged corpora.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.07</strong>, 2009-08-07</p>
		<p>
		Enabled frequency-list comparison; fixed a bug in the sort function and another in the corpus setup procedure.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.06</strong>, 2009-07-27</p>
		<p>
		Added distribution-thin postprocessing function.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.05</strong>, 2009-07-26</p>
		<p>
		Added frequency-list-thin postprocessing function.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.04</strong>, 2009-07-05</p>
		<p>
		Bug fixes (thanks to Rob Malouf for spotting the bugs in question!) plus improvements to CQP interface
		object model.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.03</strong>, 2009-06-18</p>
		<p>
		Added interface to install pre-indexed CWB corpus and made further tweaks to admin functions.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.02</strong>, 2009-06-06</p>
		<p>
		Fixed some minor bugs, added categorised corpus display to main page, 
		added option to sort frequency lists alphabetically.
		</p>
		</li>
		
		<li>
		<p><strong>Version 2.01</strong>, 2009-05-27</p>
		<p>
		Added advanced subcorpus editing tools. All the most frequently-used BNCweb functionality is now replicated.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.26</strong>, 2009-05-25</p>
		<p>
		Added Categorise Query function.
		</p>
		</li>	
			
		<li>
		<p><strong>Version 1.25</strong>, 2009-04-05</p>
		<p>
		Added Word lookup function.
		</p>
		</li>		
		<li>
		<p><strong>Version 1.24</strong>, 2009-03-18</p>
		<p>
		Added concordance sorting.
		</p>
		</li>
			
		<li>
		<p><strong>Version 1.23</strong>, 2009-03-01</p>
		<p>
		Minor updates to admin functions.
		</p>
		</li>
			
		<li>
		<p><strong>Version 1.22</strong>, 2009-01-20</p>
		<p>
		Added support for right-to-left scripts (e.g. Arabic).
		</p>
		</li>		
		<li>
		<p><strong>Version 1.21</strong>, 2009-01-06</p>
		<p>
		Added (a) concordance downloads and (b) concordance thinning function.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.20</strong>, 2008-12-19</p>
		<p>
		Added (a) improved concordance Frequency Breakdown function and (b) downloadable concordance tables.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.19</strong>, 2008-11-24</p>
		<p>
		New-style simple queries are now in place! This means that "lemma-tags" will now work for
		most corpora.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.18</strong>, 2008-11-20</p>
		<p>
		The last bits of the Collocation function have been added in. Full BNCweb-style functionality
		is now available. The next upgrade will be to the new version of CEQL.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.17</strong>, 2008-11-12</p>
		<p>
		Links have been added to collocates in collocation display, leading to full statistics for
		each collocate (plus position breakdown).
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.16</strong>, 2008-10-23</p>
		<p>
		Concordance random-order button has now been activated.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.15</strong>, 2008-10-11</p>
		<p>
		A range of bugs have been fixed.<br>
		New features: a link to &ldquo;corpus and tagset help&rdquo; on every page from the middle of the footer.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.14</strong>, 2008-09-16</p>
		<p>
		Not much change that the user would notice, but the admin functions have been completely overhauled.<br>
		The main user-noticeable change is that UTF-8 simple queries are now possible.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.13</strong>, 2008-08-04</p>
		<p>
		Added collocation concordances (i.e. concordances of X collocating with Y).<br>
		Also added system-messages function.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.12</strong>, 2008-07-27</p>
		<p>
		Upgrades made to database structure to speed up collocations and keywords.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.11</strong>, 2008-07-25</p>
		<p>
		Added improved user options database.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.10</strong>, 2008-07-13</p>
		<p>
		Added frequency list view function, plus download capability for keywords and frequency lists.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.09</strong>, 2008-07-03</p>
		<p>
		Added keywords, made fixes to frequency lists.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.08</strong>, 2008-06-27</p>
		<p>
		Added collocations (now with full functionality). Added frequency list support for subcorpora.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.07</strong>, 2008-06-10</p>
		<p>
		Added collocations function (beta version only).
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.06</strong>, 2008-06-07</p>
		<p>
		Minor (but urgent) fixes to the system as a result of changes to MySQL database structure.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.05</strong>, 2008-05-23</p>
		<p>
		Added subcorpus functionality (not yet as extensive as BNCweb's).
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.04</strong>, 2008-02-04</p>
		<p>
		Added restricted queries, and successfully trialled the system on a 4M word corpus.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.03</strong>, 2008-01-23</p>
		<p>
		Added distribution function.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.02</strong>, 2008-01-08</p>
		<p>
		Added save-query function and assorted cache management features for sysadmin.
		</p>
		</li>
		
		<li>
		<p><strong>Version 1.01</strong>, 2008-01-06</p>
		<p>
		First version of CQPweb with fully working concordance function, cache management, 
		CSS layouts, metadata view capability and basic admin functions (including 
		username control) -- trial release with small test corpus only.
		</p>
		</li>
		
		<li>
		<p><strong>Autumn 2007</strong>.</p>
		<p>
		Development of core PHP scripts, the CQP interface object model and the MySQL database 
		architecture.
		</p>
		</li>
		
	</ul>
	</td>
	</tr>
</table>

<?php
}




function do_ui_bugs()
{
	?>

	<table class="concordtable fullwidth">
	
		<tr>
			<th class="concordtable">Bugs in CQPweb</th>
		</tr>
	
		<tr>
			<td class="concordgeneral">
			
			<p class="spacer">&nbsp;</p>
			
			<h3>Found a bug?</h3>
			
			<p>If you observe a problem in the CQPweb software itself, you can report it to the CWB/CQPweb developers in two ways:</p>
			
			<ul>
				<li>
					Join <a href="http://devel.sslmit.unibo.it/mailman/listinfo/cwb">the CWB developers' email list</a> 
					and send an email to the list reporting your problem.
				</li>
				<li>
					If you have a SourceForge.net account, you can post a report to 
					<a href="http://sourceforge.net/p/cwb/bugs">our bug tracker</a>.
				</li>
			</ul>
			
			<p>
				(Because of increasing levels of spam, we cannot accept anonymous bug reports 
				either on the tracker or the email list.
				You must have an account.)
			</p>
			
			<p>
				Personal emails to the developers regarding bugs are accepted but we prefer to 
				receive reports by the two public methods above.
			</p>
	
			<p class="spacer">&nbsp;</p>
	
			<h3>Server problems</h3>
		
			<p>
				Problems regarding the configuration of this server, or any of the individual corpora, 
				should not be reported as bugs.
				Instead, you should contact your server administrator.
			</p>
			
			<p>
				If in doubt regarding whether a problem should be reported to your server 
				administrator or the development team,
				please check first with your server administrator.
			</p>
			
			<?php
			global $Config;
			
			if (!empty($Config->server_admin_email_address))
				echo '<p>Your server administrator\'s contact email address is: <strong>', $Config->server_admin_email_address, '</strong>.</p>';
			?>

			<p class="spacer">&nbsp;</p>
		
			</td>
		</tr>
	</table>

	<?php
}

