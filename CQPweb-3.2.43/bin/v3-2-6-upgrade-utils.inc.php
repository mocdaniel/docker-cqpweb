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

function upgrade326_recode_textlist($s)
{
	if (empty($s)) 
		return $s;
	return "^text^id^$s";
}
function upgrade326_recode_restriction($s)
{
	if (empty($s))
		return $s;
	
	// OK, here's the procesas. first change the link char from = to ~
	$s = str_replace('=', '~', $s);
	// get rid of quotes
	$s = str_replace('\'', '', $s);
	// get rid of logical  operators
	$s = str_replace('|', '', $s);
	$s = str_replace('&', '', $s);
	// and brackets
	$s = str_replace(')', '', $s);
	$s = str_replace('(', '', $s);
	
	// I'm pretty certain everything is sorted as it should be.
	// so I am not going to break it apart and re-sort.
	
	// change delimiter
	$s = preg_replace('/\s+/',  '.', $s);
	$s = trim($s);
	
	return '$^--text|' . $s;
}
function upgrade326_recode_pair_to_scope($subcorpus, $restrictions)
{
	if ($subcorpus == '')
	{
		/* restrictions */
		if ($restrictions == '') 
			return '';
		else 
			return upgrade326_recode_restriction($restrictions);
	}
	else
	{
		/* subcorp id */
		if ("-1" === $subcorpus) 
			return "\xc3\x90";
		else if (preg_match('/^\d+$/', $subcorpus)) 
			return $subcorpus;
		else
		{
			echo "Couldn't convert Subcorpus indicator string |$subcorpus| -- left as is in DB in new field \n";
			return $subcorpus;
		}
	}
}
function upgrade326_recode_to_new_subcorpus($restrictions, $text_list)
{
	if ("" == $text_list)
		return upgrade326_recode_restriction($restrictions);
	else
		return upgrade326_recode_textlist($text_list);
}
