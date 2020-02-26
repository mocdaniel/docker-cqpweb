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

/* DeleteEveryThirdHit: example plugin by Andrew Hardie. */

/**
 * This postprocess deletes every third example from the query.
 *
 * Of course, you are not expected to actually want to do this. 
 * It is just to demonstrate what a custom postprocess looks like.
 */
class DeleteEveryThirdHit extends PostprocessorBase implements Postprocessor
{
	/* this is a variable, so that we can pass in ['delete_tick' => '20'] 
	 * or whatever via the $extra passed to __construct. */ 
	private $delete_tick = 3;
	/* the code below casts this to int whenever used, in case a str is passed. */
	
	/* we don't need any extra input from the user */
	public function get_info_request()
	{
		return false;
	}
	
	public function postprocess_query($query_array)
	{
		$n = count($query_array);
		for ($i = 0 ; $i < $n; $i++)
			if (0 == $i % (int)$this->delete_tick)
				unset($query_array[$i]);
		return array_values($query_array);
	}
	
	
	public function description()
	{
		return "Thin a query by deleting hits regularly.";
	}
	
	public function long_description($html = true)
	{
		if ($html)
			return "This plugin thins a query by deleting every <em>N</em>'th hit (where <em>N</em> is 3 by default, but can be set)."; 
		return "This plugin thins a query by deleting every N'th hit (where N is 3 by default, but can be set)."; 
	}
	
	public function get_menu_label()
	{
		if (3 == (int)$this->delete_tick)
			return "Delete every third hit from the query!";
		else
			return "Delete every {$this->delete_tick}th hit from the query!";
	}
	
	public function get_postprocess_description($html = true)
	{
		if (3 == (int)$this->delete_tick)
			return "amended to delete every third hit";
		else
			return "amended to delete every {$this->delete_tick}th hit";
	}


}

