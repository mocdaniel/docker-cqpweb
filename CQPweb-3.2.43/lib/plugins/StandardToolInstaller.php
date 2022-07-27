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
 * This plugin runs one of the standard taggers that CQPweb knows about.
 * (TreeTagger... CLAWS/USAS... there will be more!)
 * 
 * It uses the system's internal knowledge of how these taggers work 
 * in order to make setup easy (with as little extra_config as possible). 
 * 
 * One compulsory extra config: tool=>UCREL|TreeTagger
 * If the latter, then also:    language=>SomeTreeTaggerLanguage
 * If the former, then also:    semtag-resources=>a path, as for like config variable for the UcrelTagger. 
 * 
 * Optionally: xml_template_id, annotation_template_id.
 * 
 * Note that the tagger plugins need to be present, but do not need to be registered, 
 * as vcariables dfor the tagfger casn be passed through here 
 */
class StandardToolInstaller extends SimplePlaintextInstaller implements CorpusInstaller
{
	/* internal variables are private as they are NOT intended to be accessed by the base class. */
	private $_annotation_tid;
	private $_xml_tid;
	
	private $known_tools = ['UCREL', 'TreeTagger'];
	private $tool = '';
	private $tt_lang;
	private $extra_conf_for_tagger = [];

	/* Note, you can set a path here if you want, and it will be used instead of extra config. */
	private $USAS_resource_dir = '';
	
	public function __construct($extra_config = [])
	{
		/* this function DOESN'T use the parent constructor to turn $extra_config to members,
		 * so $extra_config variables that AREN'T explictly dealt with, are lost. */
		
		if (!isset($extra_config['tool']))
			return $this->raise_error('StandardToolInstaller: no tool specified.');
		
		$this->tool = $extra_config['tool'];
		
		/* seek config for, and initialise, the tagger. */
		switch($this->tool)
		{
		case 'UCREL':
			/* default tagger settings */
			if (!empty($this->USAS_resource_dir))
				$this->extra_conf_for_tagger['semtag-resources'] = $this->USAS_resource_dir;
			$this->extra_conf_for_tagger['sanitise-encoding'] = true;
			$this->extra_conf_for_tagger['sanitise-xml'] = true;
			$this->extra_conf_for_tagger['text-tags-are-present'] = false;
			
			foreach(UcrelTagger::list_of_ucrel_toolchain_options() as $option)
				if (isset($extra_config[$option]))
					$this->extra_conf_for_tagger[$option] = $extra_config[$option];
			
			if (empty($this->extra_conf_for_tagger['semtag-resources']))
				return $this->raise_error('USAS: semtag resource folder not specified.');
			
// 			if (isset($extra_config['sanitise-encoding']))
// 				$this->extra_conf_for_tagger['sanitise-encoding'] = $extra_config['sanitise-encoding'];

// 			if (isset($extra_config['sanitise-xml']))
// 				$this->extra_conf_for_tagger['sanitise-xml'] = $extra_config['sanitise-xml'];
			
			$this->tagger = new UcrelTagger($this->extra_conf_for_tagger);

			break;
			
		case 'TreeTagger':
			if (!isset($extra_config['language']))
				return $this->raise_error('TreeTagger: no language specified.');
			if (!TreeTagger::is_valid_language($extra_config['language']))
				return $this->raise_error('TreeTagger: bad language specified.');
			
			$this->tt_lang = $this->extra_conf_for_tagger['language'] = $extra_config['language'];
			
			$this->tagger = new TreeTagger($this->extra_conf_for_tagger);
			
			break;
			
		default:
			return $this->raise_error('StandardToolInstaller: bad tool specified.');
		}
		
		
		if (!is_object($this->tagger))
			return $this->raise_error("Could not create Annotator for standard-tool tagger {$this->tool}!");
		
		
		/* for BOTH types of tool, we use one single vrt file. */
		$this->vrt_file_path = pluginhelper_get_temp_file_path();
		
		/* find out what templates to use! */
		if (false === ($sought = $this->seek_template_ids($extra_config)))
			return false;
		
		list($this->_annotation_tid, $this->_xml_tid) = $sought;
		/* so the above does not need doing during setup */
	}

	/** internal util to get template IDs without 'em needing to be specified (tho they can be overridden */
	private function seek_template_ids($extra_config)
	{
		$sp = array(
				'annotation' => ['UCREL'=>'Lancaster toolchain annotations',              'TreeTagger'=>'POS plus lemma (TreeTagger format)'],
				'xml'        => ['UCREL'=>'Text elements (with IDs) plus s for Sentence', 'TreeTagger'=>'Text elements (with IDs) plus s for Sentence']
				/* note the xml names to look up are the same ... no effort made to optimise this away. */
				/* see:: the "load_default_(annotation|xml)_templates()" funcs. */
				);

		$tids = [0=>NULL, 1=>NULL]; /* preinitalise so they are actually in order */
		
		foreach ([0=>'annotation', 1=>'xml'] as $ix => $which)
		{
			/* TEMPLATES: we look for the 2 default template, unless this has been overridden */
			if (isset($extra_config["{$which}_template_id"]))
				$tids[$ix] = (int)$extra_config["{$which}_template_id"];
			else 
			{
				if (1 > mysqli_num_rows($result = do_sql_query("select id from {$which}_template_info where description  = '{$sp[$which][$this->tool]}'")))
					return $this->raise_error("StandardToolInstaller: could not find default $which template for {$this->tool}. ");
				list($tids[$ix]) = mysqli_fetch_row($result);
			}
		}
		
		return $tids;
	}
	
	/**
	 * 
	 * @see CorpusInstaller::get_annotation_bindings()
	 */
	public function get_annotation_bindings()
	{
		if ('TreeTagger' == $this->tool)
			/* TT */
			return array(
					'primary_annotation'=>'pos',
					'secondary_annotation'=>'lemma'
			);
		else
		{
			/* UCREL */
			/* use the builtin annotation mapping table, unless it doesn't exist, in which case, 
			 * look for one with the right description , and if not found, fall back to the builtin name. */ 
			$ucrel_maptable = 'oxford_simple_tags';
			
			if (!array_key_exists('oxford_simple_tags', $tables = get_all_tertiary_mapping_tables()))
				foreach($tables as $o)
					if ($o->name == 'Oxford Simplified Tagset (English)')
						$ucrel_maptable = $o->name;
			
			return array(
					'primary_annotation'=>'pos',
					'secondary_annotation'=>'lemma',
					'tertiary_annotation'=>'class',
					'tertiary_annotation_tablehandle'=> $ucrel_maptable,
					'combo_annotation'=>'taglemma'
			);
		}
	}
	
	public function do_setup()
	{
		if (!$this->check_for_corpus_name())
			return false;

		/* can't declare till we know the name of the corpus ... */
		
		$this->declare_annotations_from_template($this->_annotation_tid);
		$this->declare_xml_from_template($this->_xml_tid);
		
		$orig_input_files = $this->input_files;
		
		$this->tagger->process_file_batch($orig_input_files, $this->vrt_file_path);
		if (!$this->tagger->status_ok())
			return $this->raise_error("{$this->tool} error: " . $this->tagger->error_desc(false));
		/* check that the vrt file does actually contain some data */
		if (0 == filesize($this->vrt_file_path))
			return $this->raise_error("StandardToolInstaller error: VRT file from tagger is empty.");

		/* now reset the array for indexing ... */
		
		$this->input_files = [];
		
		$this->add_input_file($this->vrt_file_path);
		
		$this->restrict_input_data();
		
		return true;
	}
	
	public function description()
	{
		switch($this->tool)
		{
		case 'UCREL':
			return 'Installer using CLAWS/USAS taggers';
		case 'TreeTagger':
			return 'Installer using TreeTagger ' . (empty($this->tt_lang) ? '' : "for {$this->tt_lang}") ;
		}
	}

}
