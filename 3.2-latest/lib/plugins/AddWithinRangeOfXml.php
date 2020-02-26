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
 * This is an example CeqlExtender plugin. Unlike most plugins, this file 
 * contains two classes: the plugin itself, and the CeqlParser that it provides.
 */

/**
 * This plugin exemplifies the process of providing a different CEQL grammar.
 */
class AddWithinRangeOfXml extends CeqlExtenderBase implements CeqlExtender
{
	public $tag = 's';
	
	public function __construct($extra_config)
	{
		/* we allow the tag to be set. */
		if (isset($extra_config['s-attribute']))
			$this->tag = cqpweb_handle_enforce($extra_config['s-attribute']);
	}
	
	public function get_parser()
	{
		return new AddWithinRangeOfXmlCeqlParser($this->tag);
	}
	
	public function description()
	{
		return "Parser which adds 'within <s>' to all queries.";
	}

	public function long_description($html = true)
	{
		return $html ? escape_html($this->description()) : $this->description;
	}

	public function apply_standard_setup()
	{
		return true;
	}
}

/**
 * CeqlParser with variant treatment of phrase queries: they are all 
 * treated as being "within xxx".
 */
class AddWithinRangeOfXmlCeqlParser extends CeqlParserForCQPweb
{
	/* any functions put here override the equivalent functions in the CeqlParser / CeqlParserForCQPweb */
	
	/**
	 * @param string $s_attribute_to_add
	 */
	public function __construct($s_attribute_to_add)
	{
		parent::__construct();
		
		$this->NewParam("s_attribute_to_add", $s_attribute_to_add);
	}

	/** overrride the normal phrase_query() method by adding "within $s_att" to the outcome of the normal call. */
	protected function phrase_query($input)
	{
		return parent::phrase_query($input) . ' within ' . $this->GetParam('s_attribute_to_add');
	}
}


