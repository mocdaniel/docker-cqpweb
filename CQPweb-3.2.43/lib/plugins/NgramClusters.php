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

/* NgramClusters: example plugin by Andrew Hardie. */
 
/**
 * This class is a very basic "cluster" (in the N-gram sense) calculator.
 */
class NgramClusters extends QueryAnalyserBase implements QueryAnalyser
{
	/** length of n-grams to find. */
	protected $length;
	
	/** annotation to use (word, pos, etc.) */
	protected $annotation;
	
//TODO these should be in bas class, no?
	/** number of rows in output, not counting 1 header row; -1 if we are not going to use  */
	public $n_rows_in_output = -1;
	/** number of rows in output, not counting 1 header row; -1 if we are not going to use  */
	public $n_cells_per_row = -1;
	/** Type of HTML layout for output. False if none (download), otherwise 'table', 'div', or 'svg'. */
	public $html_layout = false;
	
	/** wording for column headers (array fo strings, left to right) */ 
	protected $header_row;
	protected $row_pointer = -1;
	protected $cell_pointer = -1;
	
	/** Overrideable defaults for the range of possible values for N */
	protected $clustermin = 2;
	protected $clustermax = 10;
	protected $clusterdef = 3;
		
	
	public function __construct($extra_config = [])
	{
		/* make it easy for people to specify the type as display/download */
		if (isset($extra_config['type']))
		{
			$extra_config['type_of_query_analyser_output'] = $extra_config['type'];
			unset($extra_config['type']);
		}
		
		parent::__construct($extra_config);
		
		if (!isset($this->length))
			$this->raise_error("No cluster length supplied!!");
		else
			$this->length = (int)$this->length;
		
		if (!isset($this->annotation))
			$this->raise_error("!!");
		
		if (parent::OUTPUT_DISPLAY == $this->type_of_query_analyser_output)
		{
			$this->html_layout = 'table';
			$this->n_cells_per_row = 2;
		}
		$this->header_row = [ 'Cluster type', 'Frequency' ]; 
	}
	
	
	/* we need the user to specify one thing: the N of tokens to use to look for clusters. */
	public function get_info_request()
	{
		global $Corpus; 
		
		$annotations = array_merge(['word'], array_keys(list_corpus_annotations($Corpus->name)));
		
		
		return array (
			/* length is a number from 2 to 10, which determines how long are the clusters treated of. */
			[ 'name' => 'length', 'type' => 'number', 'min' => $this->clustermin, 'max' => $this->clustermax, 'default' => $this->clusterdef ],
			/* but note that "extra_config" can overrule these numbers */
				
			/* annotation is the p-attribute on which to calculate the clusters */
			[ 'name' => 'annotation', 'type' => 'select', 'options' => implode('|', $annotations), 'default' => 'word']
		);
	}

	
	public function description()
	{
		return "View clusters surrounding the hits in this query.";
	}
	
	
	public function get_menu_label()
	{
		return "View clusters... ";
	}

	//inset dat , process data, get back fata ....
	
	
	// TODO add the following to the intrface / base class...  
	
	public function set_query_data()
	{
		
	}
	
	public function get_http_content_type()
	{
		if (parent::OUTPUT_DOWNLOAD != $this->type_of_query_analyser_output)
			return false;
		return "";
	}
	public function get_http_content_disposition()
	{
		if (parent::OUTPUT_DOWNLOAD != $this->type_of_query_analyser_output)
			return false;
		return "";
	}

	public function get_next_download_line()
	{
		if (parent::OUTPUT_DOWNLOAD != $this->type_of_query_analyser_output)
			return false;
		
	}
	

	
	public function begin_next_row()
	{
		$this->cell_pointer = 0;
		if ($this->row_pointer < $this->n_rows_in_output)
			return ($this->row_pointer++);
		else 
			return false;
	}

	
	public function read_next_cell()
	{
		if ($this->row_pointer >= $this->n_cells_per_row)
			return false;
		if (-1 == $this->row_pointer)
			return $this->header_row[ ($this->cell_pointer++) ];

		return $this->output_table[$this->row_pointer][ ($this->cell_pointer++) ];
	}
	
	/* this plugin doesn't use this. Should not be instantiated by base class */
	public function read_full_content()
	{
		
	}
	
}

