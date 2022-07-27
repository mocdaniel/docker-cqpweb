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
 * Annotator plugin for the UCREL toolchain with CLAWS/USAS.
 * 
 * Note this assumes the UCREL toolchain is installed as it is
 * on our systems at Lancaster.
 * 
 * Elsewhere, it probably won't work without tweaks.
 */
class UcrelTagger extends AnnotatorBase implements Annotator
{
	private $script = 'ucrel-tagger-toolchain';
	private $script_opts = [];
	
	
	// TODO in future there will be 4 modes, depending on the Claws resources (maybe also USAS resoruces? )
	
	const MODE_CLASSIC = 1; /* classic lexicon used for all those years in Wmatrix etc., based on Written BNC1994 */
	const MODE_WRITTEN = 2; /* including new Writ BNC2014 vocab (added to classic lexicon) */
	const MODE_SPOKEN  = 3; /* including new Spok BNC2014 vocab (added to Old BNC Spoken lexicon) */
	const MODE_EMODENG = 4; /* including new Shakespearean vocab (added to classic lexicon) */
	// all depends on the toolchain knowing about them,  of course. 
	
	
	private $mode = self::MODE_CLASSIC;
	
	public function __construct($extra_config = [])
	{
		/* the only extra params we accept are (a) options for ucrel-tagger-toolchain, or (b) a few specials. */
		foreach($extra_config as $param => $val)
		{
			switch($param)
			{
			case '_special_use_this_script':
				$this->script = realpath($val);
				break;
				
				/* UTT OPTIONS THAT WE ALLOW HERE THAT HAVE VALUES */
			case 'config-file':
				$this->script_opts[$param] = "-c ".escapeshellarg(realpath($val)) ;
				break;
			case 'semtag-resources':
				$this->script_opts[$param] = "--semtag-resources=".escapeshellarg(realpath($val));
				break;
			case 'working-directory':
				$this->script_opts[$param] = "--working-directory=".escapeshellarg(realpath($val));
				break;
				
				/* UTT BOOL-LIKE OPTIONS */
			case 'text-tags-are-present':
			case 'sanitise-encoding':
			case 'sanitise-xml':
			case 'skip-semtag':
			case 'skip-template-tagger':
			case 'use-c7-tagset':
			case 'pre-varded':
				/* ignore the val - check only for bool true or false.*/
				if ($val)
					$this->script_opts[$param] = '--' . $param;
				break;
			}
		}
	}
	
	public function description()
	{
		return "CLAWS/USAS from UCREL, Lancaster University";
	}
	
	public function long_description($html = true)
	{
		return "Tag English text with the UCREL taggers from Lancaster University (CLAWS plus USAS within a CQPweb-friendly wrapper).";
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
		/* check the setup flags that we MUST have */
		if (!isset($this->script_opts['semtag-resources']))
			return $this->raise_error("UcrelTagger: cannot run unless provided with parameter **semtag-resources**");
		
		/* if no working directory has been specified, we get a temporary location to work in */
		if (!isset($this->script_opts['working-directory']))
			$this->script_opts['working-directory'] = "--working-directory=" . escapeshellarg(pluginhelper_get_temp_dir_path(true));
// squawk( str_replace("'", '%', $this->script_opts['working-directory']));
		/* no need to record where this is... we just set the autodelete argument. */
		
		/* end of check -- time to GET SHIT DONE!! */
		
		/* both these funcs raise their own errors. */
		if (!$this->validate_write_paths([$path_to_output_file]))
			return false;
		if (!$this->validate_read_paths($input_paths))
			return false;
		
		/* build the command base */
		$cmd_base   = "{$this->script} -q -p --overwrite-files " . implode(' ', $this->script_opts );
		$cmd_stderr = " 2> /dev/null ";
// 		$cmd_stderr = " 2> &1 ";
		$fileopts = '';
		/* we will always want to throw away stderr for the present. */

		
		$dst = fopen($path_to_output_file, 'w');
					//TODO when we are happy using .vrt.gz files, here would be a great place to do that.
		$n_lines_written = 0;
		

// squawk("How many inpit paths?? ==>" .  count($input_paths));
		for($i = 0, $n = count($input_paths);  $i < $n ; $i++)
		{
			/* do 8 files at a go. */
			$fileopts .= ' -f ' . escapeshellarg($input_paths[$i]);
			
			if ( 0 == ($i % 8) || $i == ($n - 1) )
			{
if($i == 8 || $i == ($n - 1) ) squawk("Example tagger call: ".str_replace("'", '%', "$cmd_base $fileopts $cmd_stderr"));
				/* TODO, longterm proc_open might be better, or anything that lets us read exit status */
				$src = popen("$cmd_base $fileopts $cmd_stderr", 'r');
				while (false !== ($line = fgets($src)))
					fputs($dst, $line);
				pclose($src);
				$fileopts = '';
			}
		}
// squawk($path_to_output_file);
// squawk(substr(file_get_contents($path_to_output_file), 0, 1024));
		
		fclose($dst);
		
		if (0 == ($this->bytes_in_output = filesize($path_to_output_file)))
			return $this->raise_error("UcrelTagger error: The tagged file is empty! (Lines theoretically written: $n_lines_written)");
		
		return $this->status_ok();
	}
	
	
	/* dummy cos we use this annotator with a template. */
	public function output_n_annotations() {}
	
	
	
	/* this allows callers to get the list of possible extra config. */
	public static function list_of_ucrel_toolchain_options()
	{
		return array( 
			'config-file',
			'semtag-resources',
			'text-tags-are-present',
			'sanitise-encoding',
			'sanitise-xml',
			'skip-semtag',
			'skip-template-tagger',
			'use-c7-tagset',
			'pre-varded',
			'working-directory',
		);
	}
}
/* end of plugin "UcrelTagger" */

