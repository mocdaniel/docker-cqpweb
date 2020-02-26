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
 * The SimplePlaintextInstaller runs a specified annotator 
 * over its input files, and then indexes the result.
 * 
 * Its extra settings on construct MUST include an 'annotator_plugin_id'.
 * 
 * It assumes that the annotator can describe the s-attributes and p-attributes.
 */
class SimplePlaintextInstaller extends CorpusInstallerBase implements CorpusInstaller
{
	protected $annotator_plugin_id;
	
	private $vrt_file_path;
	
	private $annotator_plugin_info;
	
	private $tagger;

	
	public function __construct($extra_config = [])
	{
		parent::__construct($extra_config);
		
		if (!$this->status_ok())
			return;

		/* have we been passed an Annotator to use? */
		if (empty($this->annotator_plugin_id))
		{
			$this->raise_error("No Annotator has been specified!");
			return;
		}
		
		/* check if the annotator we need exists as a registered plugin */
		if (false === ($this->annotator_plugin_info = get_plugin_info($this->annotator_plugin_id)))
		{
			$this->raise_error("The Annotator specified to use with this installer doesn\'t exist!");
			return;
		}

		if (!is_object($this->tagger = new $this->annotator_plugin_info->class($this->annotator_plugin_info->extra)))
		{
			$this->raise_error("Could not create Annotator!");
			return;
		}
		
		/* set up a temporary VRT file path, if not passed in as extra config. */
		if (empty($this->vrt_file_path))
			$this->vrt_file_path = pluginhelper_get_temp_file_path();
		
	}
	
	
	public function description()
	{
		return "Installer which runs a tagger on its input data";
	}
	
	
	public function long_description($html = true)
	{
		return $this->description();
	}	
	

	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::do_setup()
	 */
	public function do_setup()
	{
		if (!$this->check_for_corpus_name())
			return false;
			
		$this->tagger->process_file_batch($this->input_files, $this->vrt_file_path);
		
		/* setup p/s using calls to annotator. */

		$this->declare_content_from_annotator($this->tagger);

		if (!$this->tagger->status_ok())
		{
			$this->raise_error("Annotator reports error:: " . $this->tagger->error_desc(false));
			return false;
		}

		$this->add_input_file($this->vrt_file_path);
		
		$this->restrict_input_data();
		
		return true;
	}
	
	
	public function do_cleanup($delete_input_files = false)
	{
		parent::do_cleanup($delete_input_files);
		
		/* delete the VRT file if it wasn't deleted by 
		 * parent::do_cleanup() (we know it's intermediate) */
		if (is_file($this->vrt_file_path))
			unlink($this->vrt_file_path);
	}

}



