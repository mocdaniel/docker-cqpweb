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
 * The Utf8FileChecker goes over a file and makes sure it is all UTF-8.
 */
class Utf8FileChecker extends FormatCheckerBase implements FormatChecker
{
	/**
	 * {@inheritDoc}
	 * @see FormatChecker::file_is_valid()
	 */
	public function file_is_valid($path_to_input_file)
	{
		if (! is_readable($path_to_input_file))
			return false;
		
		$src = fopen($path_to_input_file, 'r');
		
		$ln = 0;
		
		$result = true;
		
		while (false !== ($line = fgets($src)))
		{
			++$ln;
			if (!mb_check_encoding($line, 'UTF-8'))
			{
				$result = false;
				$this->error_line_number = $ln;
				break;
			}
		}
		
		fclose($src);
		
		return $result;
	}
	
	public function description()
	{
		return "Checks file is valid UTF-8.";
	}

	public function long_description($html = true)
	{
		return "This plugin checks the encoding of a file to make sure it is valid UTF-8 throughout.";
	}

}

