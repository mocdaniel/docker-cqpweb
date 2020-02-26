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
 * This is a basic tokeniser plugin: creates a vrt file.
 */


/**
 * Annotator plugin that performs basic tokenisation based on "split at /\b/".
 */
class BasicTokeniser extends AnnotatorBase implements Annotator
{
	const SPLIT_REGEX = "\b";
	
	private $split_regex = self::SPLIT_REGEX;
	
	public function __construct($extra_config = [])
	{
		parent::__construct($extra_config);
		$this->set_id_method(parent::ID_METHOD_FILENAME);
	}
	
	public function description()
	{
		return "A simple tokeniser.";
	}
	
	public function long_description($html = true)
	{
		return "This is a simple tokeniser plugin; it uses regular expressions " 
					. "to split the text into tokens, which are then reformatted for CQPweb to use.";
	}
	
	/** Set the regular expression that will be used to divide each line into tokens. */
	public function set_split_regex($new_rx)
	{
		$this->split_regex = $new_rx;
	}

	
	/**
	 * {@inheritDoc}
	 * @see Annotator::process_file()
	 */
	public function process_file($path_to_input_file, $path_to_output_file)
	{
		return $this->process_file_batch([$path_to_input_file], $path_to_output_file);
	}


	/**
	 * {@inheritDoc}
	 * @see Annotator::process_file_batch()
	 */
	public function process_file_batch($input_paths, $path_to_output_file) 
	{
		if (! $this->validate_write_paths([$path_to_output_file]))
			return false;
		if (! $this->validate_read_paths($input_paths))
			return false;
		
		reset($this->text_id_list);
		
		$dst = fopen($path_to_output_file, 'w'); 
		
		foreach($input_paths as $inp)
		{
			$text_id = $this->get_next_text_id($inp);
			
			$src = fopen($inp, 'r');
			
			$check = $this->tokenise_stream($text_id, $src, $dst);
			
			fclose($src);
			
			if (!$check)
				break;
		}
		
		fclose($dst);
		
		$this->bytes_in_output = filesize($path_to_output_file);
		
		return $check;
	}
	
	/**
	 * Tokenise the whole of src to dst. Assume it's a single text, with the text_id as given.
	 * 
	 * @param  string   $text_id  Text ID for the single text. 
	 * @param  resource $src      Source stream.
	 * @param  resource $dst      Destination stream.
	 * @return bool               Status after.
	 */
	private function tokenise_stream($text_id, $src, $dst)
	{
		fputs($dst, "<text id=\"$text_id\">\n" . PHP_EOL);
		
		while (false !== ($line = fgets($src)))
		{
			$words = preg_split("/{$this->split_regex}/", trim($line), NULL, PREG_SPLIT_NO_EMPTY);
			
			foreach($words as $w)
			{
				/* this Annotator always escapes out XML. But we do it AFTER tokenisation. */
				$w = strtr(str_replace('&', '&amp;', $w), ['<' => '&lt;', '>' => '&gt;'] );
				$w = trim($w);
				if (!empty($w))
					fputs($dst, $w . PHP_EOL);
			}
		}
		
		fputs($dst, "</text>\n" . PHP_EOL);
		
		return $this->status_is_ok;
	}
	
	
	
	public function output_n_annotations()
	{
		return 0;
	}
	
	public function output_annotation_list()
	{
		return [];
	}
	public function output_xml()
	{
		return [ 'element'=>'text', 'attributes'=>['id'=>METADATA_TYPE_UNIQUE_ID]];
	}

}
/* end of plugin "BasicTokeniser" */
	
