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
 * This script generates an RSS feed containing the system messages. 
 * This is to make distributing alerts about downtime etc. a bit easier.
 */

require('../lib/environment.php');
require('../lib/sql-lib.php');
require('../lib/general-lib.php');
require('../lib/html-lib.php');
require('../lib/exiterror-lib.php');



/* declare global variables */
$Config = NULL;

cqpweb_startup_environment(CQPWEB_STARTUP_DONT_CONNECT_CQP|CQPWEB_STARTUP_DONT_CHECK_USER, RUN_LOCATION_RSS);

/* switch to text-mode errors, as they will  be sent out in an XML file */
$Config->debug_messages_textonly = true;

if (!$Config->rss_feed_available)
	exit();

/* The default value for the RSS link is a relative address ("up one level"). So, in that case, make absolute. */
if ('..' == $Config->rss_link)
	$Config->rss_link = url_absolutify('..');

/* use output buffering because we want to serve as quick-and-easily as possible */
ob_start();

/* before anything else ... note type is text/xml not HTML */
header('Content-Type: text/xml; charset=utf-8');

$t = escape_html($Config->rss_feed_title);
$l = escape_html($Config->rss_link);  /* as a URL, could contain ampersand. */
$d = escape_html($Config->rss_description); 

/* this is to prevent ? > or < ? being dealt with as PHP delimiters;
 * that shouldn't happen as PHP is supposed to interleave with XML,
 * but in (at least) some versions, this is not working out right. 
 */
echo '<' , '?xml version="1.0" encoding="UTF-8" ?' , '>', <<<BEGIN_RSS

<rss version="2.0">
	<channel> 
		<title>$t</title>
		<link>$l</link> 
		<description>$d</description> 

BEGIN_RSS;

$result = do_sql_query("select * from system_messages order by date_of_posting desc");
if (0 == mysqli_num_rows($result))
{
	echo <<<END_OF_DUMMY_ITEM

		<item>
			<title>No messages at the moment!</title>
			<link>{$Config->rss_link}?fakeArgumentFromRss=Dummy</link>
			<description>There are no messages from the CQPweb server just now.</description>
			<guid>no_messages_just_now</guid>
		</item>

END_OF_DUMMY_ITEM;
}
else 
{
	$i = 0;
	while ($o = mysqli_fetch_object($result))
	{
if (!isset($o->id)) $o->id = $o->message_id; //TODO delete!!!
		$i++;
		$o->date_of_posting = date(DATE_RSS, strtotime($o->date_of_posting));
		$o->content = escape_html(str_replace("\n", " <br>\n\t\t", $o->content));
		$o->header = escape_html($o->header);
		echo <<<ITEM_COMPLETE

		<item>
			<title>{$o->header}</title>
			<link>{$Config->rss_link}</link>
			<description>{$o->content}</description>
			<pubDate>{$o->date_of_posting}</pubDate>
			<guid>{$o->id}</guid>
		</item>

ITEM_COMPLETE;
	}
}


cqpweb_shutdown_environment();

echo <<<END_RSS

	</channel>
</rss>

END_RSS;

ob_end_flush();

