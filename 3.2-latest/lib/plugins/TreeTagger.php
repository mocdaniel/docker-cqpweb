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
 * Annotator plugin interfacing CQPweb with the TreeTagger.
 * 
 * It is designed for Unix alone, not Windows.
 * 
 * The main "extra config" it needs is "language".
 */
class TreeTagger extends AnnotatorBase implements Annotator
{
	protected $language = NULL;
	
	protected $tt_no_s_tags = false;
	protected $tt_show_unknown_lemma = false;
	protected $n_of_cols = 2;
	
	/** path to the folder containing tree tagger, wherever you've put it , e.g. '/opt/tree-tagger' */
	protected $tt_bin_path = '';
	
	public function __construct($extra_config = [])
	{
		parent::__construct($extra_config);
	
		if (!is_null($this->language))
			$this->set_language($this->language);
		/* done this way to avoid repeating validity check */
			
	
		/* option vars */
		$this->tt_no_s_tags = (bool)$this->tt_no_s_tags;
		$this->tt_show_unknown_lemma = (bool)$this->tt_show_unknown_lemma;
		
		if ('' != $this->tt_bin_path)
			if (substr($this->tt_bin_path, -1) != '/')
				$this->tt_bin_path .= '/';
			
		$this->set_id_method(parent::ID_METHOD_FILENAME);
	}
	
	public function get_language() { return $this->language;}
	
	public function set_language($lang) 
	{
		$lang = strtolower($lang);
		if (self::is_valid_language($lang))
			$this->language = $lang;
		else
			return $this->raise_error("TreeTagger: invalid language ''$lang''");
		
		if (self::LANG_INFO[$lang]['lemma'] == 'noLemma')
			$this->n_of_cols = 1;
		else 
			$this->n_of_cols = 2;
			
		return true;
	}
	
	public static function is_valid_language($lang)
	{
		return (array_key_exists($lang, self::LANG_INFO));
	}
	
	public function description()
	{
		return "TreeTagger";
	}
	
	public function long_description($html = true)
	{
		return "Multilingual Tree Tagger (by Helmut Schmid; parameters by many contributors)";
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
		/* is a language specified? */
		if (empty($this->language))
			return $this->raise_error("TreeTagger plugin: cannot run unless a language is specified.");

		/* both these funcs raise their own errors. */
		if (!$this->validate_write_paths([$path_to_output_file]))
			return false;
		if (!$this->validate_read_paths($input_paths))
			return false;
		
		/* move object settings into the local variable */
		$settings = (object) self::LANG_INFO[$this->language];
		
		if ($settings->lemma == 'completeUnknown' && $this->tt_show_unknown_lemma)
			$settings->lemma = 'showUnknown';
		if ($this->tt_no_s_tags)
			$settings->add_s_tags_after = false;
		
		
		/* prepare TT command chunks */
		if ($settings->latin1_needed)
		{
			$first_iconv = "iconv -f\"UTF-8\" -t\"LATIN1//TRANSLIT\" |";
			$second_iconv = "| iconv -f\"LATIN1\" -t\"UTF-8//TRANSLIT\"";
		}
		else
			$first_iconv = $second_iconv = '';


		$dst = fopen($path_to_output_file, 'w');
					//TODO when we are happy using .vrt.gz files, here would be a great place to do that.
		
		
		
		/* because it may or may not be used */
		$lemma = NULL;
		
		
		/* file loop */
		foreach($input_paths as $file)
		{
			if (is_null($text_id = $this->get_next_text_id($file)))
				return $this->raise_error("TreeTagger plugin: got a duplicate text ID for file $file!!");
			
			$file_tmp = pluginhelper_get_temp_file_path();
			/* note that we assured the bin path to end in '/' already */
			$qfile = escapeshellarg($file);
			$qbin = escapeshellcmd("{$this->tt_bin_path}cmd/{$settings->script}");
			exec("cat $qfile | $first_iconv $qbin $second_iconv > $file_tmp");
			if (! is_file($file_tmp))
				return $this->raise_error("TreeTagger plugin: failed to create temporary tagger-output file $file_tmp!\n");
			
			
			$src = fopen($file_tmp, "r");
			
			fputs($dst, "<text id=\"$text_id\">\n");
			
			/* add an s-element after any inital XML */
			$s_tag_pending = !empty($settings->add_s_tags_after);
			$slash_s = 0;
		
			/* postprocess loop */
			while (false !== ($line = fgets($src)))
			{
				/* tokens */
				if ('<' != $line[0])
				{
					if ($settings->lemma == 'noLemma')
						list($word, $tag) = explode("\t", trim($line));
					else
						list($word, $tag, $lemma) = explode("\t", trim($line));
					
					
					/* note that in some languages, lemma can be a feature set: but we don't account for that.
					
					/* check for need to merge together the < and > (and other entity refs? possibly) */
					if (! empty($settings->tag_for_entities))
					{
						if ($line[0] == '&')
						{
							/* the chinese tokeniser has & on a separate line, so needs separate code */
							if ('chinese' == $this->language)
							{
								$another_line = fgets($src);
								$yet_another_line = fgets($src);
								
								if ( ( 
										( $yet_another_line[0] == ';' && $yet_another_line[1] == " " && $yet_another_line[2] == "\t" )
										||
										( $yet_another_line[0] == ';' && $yet_another_line[1] == "\t") 
									)
									&& (0 < preg_match("/^\w+\t/", $another_line)) 
								)
								{
									list($body) = explode("\t", $another_line);
									$word = "&$body;";
									$tag = $settings->tag_for_entities;							
								}
								else
									fseek($src, -(strlen($another_line) + strlen($yet_another_line)), SEEK_CUR);
							}
							else
							{
								$another_line = fgets($src);
								
								if ($another_line[0] == ';' && $another_line[1] == "\t")
								{
									$word = $word . ';';
									$tag = $settings->tag_for_entities;
									if ($settings->lemma != 'noLemma')
										$lemma = $word;
								}
								else
									fseek($src, -(strlen($another_line)), SEEK_CUR);
							}
						}
					}
					
					if ($lemma == '<unknown>')
					{
						if ($settings->lemma == 'completeUnknown')
							$lemma = $word;
						else if ($settings->lemma == 'showUnknown')
							$lemma = '--unknown--';
					}
					
					if ($s_tag_pending)
					{
						$s_tag_pending = false;
						fputs($dst, "<s>\n");
						$slash_s--;
					}
					fputs($dst, "$word\t$tag" . ($settings->lemma=='noLemma'?'':"\t$lemma") . "\n");
					if (!empty($settings->add_s_tags_after))
					{
						if (0 < preg_match($settings->add_s_tags_after,$tag))
						{
							fputs($dst, "</s>\n");
							$s_tag_pending = true;
							$slash_s++;
						}
					}
				}
				else
					/* xml */
					fputs($dst, $line);
			}
			fclose($src);
			
			/* before closing dest, check that <s>....</s> balance. */
			while ($slash_s++ < 0)
				fputs($dst, "</s>\n");
			
			fputs($dst, "</text>\n");
			
			unlink($file_tmp);
			
			/* debug message */
			if (false)
				fputs(STDERR, "Done with file $file\n");
		}
		
		fclose($dst);
		
		$this->bytes_in_output = filesize($path_to_output_file);
		
		$this->n_of_cols = $settings->lemma == 'noLemma' ? 1 : 2;
		
		return $this->status_ok();
	}


	/**
	 * {@inheritDoc}
	 * @see Annotator::output_n_annotations()
	 */
	public function output_n_annotations()
	{
		/* nb, will only work after a file has been tagged */
		return $this->columns_in_latest_output;
	}


	/**
	 * {@inheritDoc}
	 * @see AnnotatorBase::output_annotation_list()
	 */
	public function output_annotation_list()
	{
		$p = array(
			array('handle'=>'pos', 'description'=>'Part-of-speech tag', 'is_feature_set'=>false)
		);
		if (2 == $this->n_of_cols)
			$p[] = array('handle'=>'lemma', 'description'=>'Lemma', 'is_feature_set'=>false);
		return $p;
	}

	/**
	 * {@inheritDoc}
	 * @see AnnotatorBase::output_xml()
	 */
	public function output_xml()
	{
		return array (
				array('element' => 'text', 'attributes' => ['id'=>METADATA_TYPE_UNIQUE_ID]),
				array('element' => 's'   , 'attributes' => [])
				);
	}
	
	
	public static function language_name($lang)
	{
		if (array_key_exists($lang, self::LANG_INFO))
			return self::LANG_INFO[$lang]['description'];
	}
	
	
	const LANG_INFO = array (

			'bulgarian'=> array(
					'script'                  => 'tree-tagger-bulgarian',
					'description'             => 'Bulgarian',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^PT_SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'PT'
			),
			'catalan'=> array(
					'script'                  => 'tree-tagger-catalan',
					'description'             => 'Catalan',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^PUNCT\.Final$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'SYM'
			),
			'czech'=> array(
					'script'                  => 'tree-tagger-czech',
					'description'             => 'Czech',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'Xx'
			),
			'danish'=> array(
					'script'                  => 'tree-tagger-danish',
					'description'             => 'Danish',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'XS:----:--:----'
			),
			'dutch'=> array(
					'script'                  => 'tree-tagger-dutch',
					'description'             => 'Dutch (default)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^\$\.$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'punc'
			),
			'dutch-eindhoven'=> array(
					'script'                  => 'tree-tagger-dutch-eindhoven',
					'description'             => 'Dutch (Eindhoven tags)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'PUNCT'
			),
			'english'=> array(
					'script'                  => 'tree-tagger-english',
					'description'             => 'English',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'SYM'
			),
			'estonian'=> array( 
					'script'                  => 'tree-tagger-estonian',
					'description'             => 'Estonian',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^Z\.(Fst|Int|Exc)$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'T'
			),
			'finnish'=> array(  
					'script'                  => 'tree-tagger-finnish',
					'description'             => 'Finnish',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'Punct'
			),
			'french'=> array(
					'script'                  => 'tree-tagger-french',
					'description'             => 'French',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'PUN'
			),
			'galician'=> array( 
					'script'                  => 'tree-tagger-galician',
					'description'             => 'Galician (default)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^Q[\.\?!]$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'Zs00'
			),
			'galician-gamallo'=> array(
					'script'                  => 'tree-tagger-galician-gamallo',
					'description'             => 'Galician (Gamallo tags)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'VIRG'
			),
			'german'=> array(
					'script'                  => 'tree-tagger-german',
					'description'             => 'German',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^\$\.$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => '$,'
			),
			'italian'=> array(
					'script'                  => 'tree-tagger-italian',
					'description'             => 'Italian (default)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'PON'
			),
			'italian-itwac'=> array(
					'script'                  => 'tree-tagger-italian-itwac',
					'description'             => 'Italian (itWaC tags)',
					'latin1_needed'           => true,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'PUN'
			),
			'korean'=> array(  
					'script'                  => 'tree-tagger-korean',
					'description'             => 'Korean',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SF$/',
					'lemma'                   => 'noLemma',
					'semtag_available'        => false,
					'tag_for_entities'        => 'SW'
			),
			'latin' => array(
					'script'                  => 'tree-tagger-latin',
					'description'             => 'Latin (default)',
					'latin1_needed'           => true,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'PUN'
			),

			'latin-it' => array(
					'script'                  => 'tree-tagger-latin-it',
					'description'             => 'Latin (Ind.Tho. tags)',
					'latin1_needed'           => true,
					'add_s_tags_after'        => false,
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'Punc'
			),
			'mandarin'=> array(
					'script'                  => 'tree-tagger-mandarin',
					'description'             => 'Mandarin Chinese',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^ew$/',
					'lemma'                   => 'noLemma',
					'semtag_available'        => false,
					'tag_for_entities'        => 'w'
			),
			'polish'=> array(
					'script'                  => 'tree-tagger-polish',
					'description'             => 'Polish',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'interp'
			),
			'portuguese'=> array( 
					'script'                  => 'tree-tagger-portuguese',
					'description'             => 'Portuguese (European, simple tagset)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^(Fp|Fat|Fit)$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'Fz'
			),
			'portuguese-fine'=> array(
					'script'                  => 'tree-tagger-portuguese-finegrained',
					'description'             => 'Portuguese (European, fine-grained tagset)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^(Fp|Fat|Fit)$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'Fz'
			),
			'portuguese-br'=> array(
					'script'                  => 'tree-tagger-portuguese2',
					'description'             => 'Portuguese (Brazilian)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^PUNCT\.Sent$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'SYM'
			),
			'romanian'=> array(
					'script'                  => 'tree-tagger-romanian',
					'description'             => 'Romanian',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'X'
			),
			'russian'=> array(
					'script'                  => 'tree-tagger-russian',
					'description'             => 'Russian',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'PUNCT'
			),
			'slovak'=> array(
					'script'                  => 'tree-tagger-slovak',
					'description'             => 'Slovak (simple tagset)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^(\.|!|\?)$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => '#'
			),
			'slovak-full'=> array(
					'script'                  => 'tree-tagger-slovak-full',
					'description'             => 'Slovak (full tagset)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^SENT$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'Z'
			),
			/*
			The TT aborts on any not-in-Lexicon word in Slovene mode - not sure why.
			'slovenian'=> array(
					'script'                  => 'tree-tagger-slovenian',
					'description'             => 'Slovenian',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^\$\.$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => '$$'
			),
			## NB, 'slovene' and 'slovenian' are synonyms, so this entry should always match the above. 
			'slovene'=> array(
					'script'                  => 'tree-tagger-slovenian',
					'description'             => 'Slovenian',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^\$\.$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => '$$'
			),
			*/
			'spanish'=> array(
					'script'                  => 'tree-tagger-spanish',
					'description'             => 'Spanish (default)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^FS$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'SYM'
			),
			'spanish-ancora'=> array(
					'script'                  => 'tree-tagger-spanish-ancora',
					'description'             => 'Spanish (Ancora tags)',
					'latin1_needed'           => false,
					'add_s_tags_after'        => '/^PUNCT\.Final$/', 
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'SYM'
			),
			'swahili'=> array(  
					'script'                  => 'tree-tagger-swahili',
					'description'             => 'Swahili',
					'latin1_needed'           => true,
					'add_s_tags_after'        => '/^CLB$/',
					'lemma'                   => 'completeUnknown',
					'semtag_available'        => false,
					'tag_for_entities'        => 'MARK'
			),

			
			
			//	'template'=> array(  'script'                  => 'tree-tagger-',
					//						'description'             => '',
					//						'latin1_needed'           => false,
					//						'add_s_tags_after'        => false, //'/^SENT$/',
					//						'lemma'                   => '', //'completeUnknown', 'showUnknown', 'noLemma'
					//						'semtag_available'        => false,
					//						'tag_for_entities'        => false //'SYM'
					//					)
	);


}
/* end of plugin "TreeTagger" */

