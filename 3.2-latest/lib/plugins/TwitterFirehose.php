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
 * Thuis pluigin isn;'t finished yet. 
 */
class TwitterFirehose extends CorpusInstallerBase implements CorpusInstaller
{
	/** max tweets to collect; if < 0, no limit and we get as many as Twitter will give us. */
	private $max_tweets = -1;
	
	/** the query that will be submitted. If blank, no query. */ // TODO - check what forms of query are possibnle4 
	private $twitter_query;
	
	/** the annotator to use */
	private $annotator_id = NULL;
	
	// TODO add priv vars fior anno/xml templates too. Make sure everything compatible with CorpuysInstallerBase.
	
	
	/** data for a filter to apply on fields of the Tweet obje4cts once downloaded.  */
	
	public function __construct($extra_config = [])
	{
		parent::__construct($extra_config);
		
		// tODO chec k we have the vars we need. 		
	}
	
	/**
	 * {@inheritDoc}
	 * @see CQPwebPlugin::description()
	 */
	public function description()
	{
		return "A basic corpus builder plugin: connects to the Twitter firehose, collects tweets according to a query, and creates a corpus from them."; 
	}

	
	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::do_setup()
	 */
	public function do_setup()
	{
		//TODO
		// access the Fh with the set search term.
		
		// download some tweets#
		
		// allow tweet filtering like w/ fireant
		
		// delete tweets that aren't wiotjhin the word limit.
		
		// TODO delete non-bmp characters (or replace with relacement char) : only needed before utf8mb4 migration. 
		
		// tag em
		
		// bring fire from the house of a Brahmin, and proceeed as before. 
	}
}