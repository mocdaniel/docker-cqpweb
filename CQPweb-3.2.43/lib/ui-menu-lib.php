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
 * This library encapsulates the main left-hand menu, allowing future development 
 * to have more complex structure without making the main scripts more complicated.
 */

/**
 * Represents the left-hand-side menu: its structure and all info for its display.
 */
class UiMenuWriter
{
	
	
	private $sections = [];

	public function __construct()
	{
		
	}
	
	public function do_html()
	{
		// tODO: main container, write.
		
		foreach ($this->sections as $s)
			$s->do_html();
		
		// TODO, main container write.
	}
	
	// TODO a single setup funciton (parsE?) to set up the strucutre???
}


class UiMenuSection 
{
	private $label = '';
	private $main_items = [];
	
	public function __construct()
	{
		
	}
	
	public function do_html()
	{
		if (!empty($this->label))
		{
// 			echo a div/cell for the side mnus.
		}
		foreach ($this->main_items as $i)
			$i->do_html();
	}
}

class UiMenuItem
{
	const TYPE_MAIN_NONEXPANDED = 0;
	const TYPE_MAIN_EXPANDED = 1;
	const TYPE_SUB = 2;
	
	private $type = TYPE_SUB;
	
	/* all are sub by default, they can be made main. */
	
	private $sub_items = [];
	private $expanded = false;
	
	
	public function __construct()
	{
		
	}
	
	public function do_html()
	{
		// do the html for the main item TODO

		if ($this->expanded)
			foreach ($this->sub_items as $i)
				$i->do_html();
		
		// end of the html for the main item
	}
	private $label = ''; 
	private $current_view = false;
	private $type_ext_link = false;
	private $parameter_name = '';
	private $parameter_value = '';
	private $ext_url = '';
	
	
}


class UiMenuSubItem extends UiMenuItem
{
	public function __construct()
	{
		
	}
	
	public function do_html()
	{
		// todo, start of div or td.
		echo $this->get_a();
		// todo, end of div or td.
		
	}
	
	/* get the link for this single item. */
	public function get_a()
	{
		$a = '<a';
		
		if ($this->type_ext_link)
			$a .= ' target="_blank" class="summat" href="' . $this->ext_url . '">';
		else
			$a .= ($this->current_view 
					? ' class="summat"' 
					: ' class="summatother"'. " href=\"index.php?{$this->parameter_name}={$this->parameter_value}\""
					)
				;

		$a .= '>' . escape_html($this->label) . '</a>';
		
		return $a;
	}
	
	public function set_as_current_view()
	{
		$this->current_view = true;
	}
	
	public function set_parameter_name($str)
	{
		$this->parameter_name = $str;
	}

	public function set_parameter_value($str)
	{
		$this->parameter_value = $str;
	}
	
}



