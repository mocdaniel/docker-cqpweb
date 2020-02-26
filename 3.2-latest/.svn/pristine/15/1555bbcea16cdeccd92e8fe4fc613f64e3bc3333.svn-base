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
 * The BasicVrtInstaller indexes its input files, which are assumed to be vrt files (and not checked).
 * 
 * It allows the following $extra_config:
 *      p_attributes: ordered array of p attribute handles.
 *      s_attributes: ordered array of s attribute info, where each array member
 *                    is an array with two components, 'element' and 'attributes', 
 *                    in the format required by CorpusInstallerBase::declare_xml(),
 *                    and defined in the documentation of Annotator::output_xml().
 *      "bindings":   mappings for the Ceql bindings to set. Key = what
 *                    to bind; value = what to bind it to. (Within the $extra_config).
 * 
 * Since the $extra_config array holds only strings, the 1st and 3rd arrays can be 
 * serialised by joining them together with a '~' divider.
 *                    
 * @see CorpusInstallerBase::declare_xml()
 * @see Annotator::output_xml()
 */
class BasicVrtInstaller extends CorpusInstallerBase implements CorpusInstaller
{
	/* these need to be protected not private so that they can be set via parent construct. */
	protected $p_attributes = NULL;
	protected $s_attributes = NULL;
	protected $binding_list = NULL;
	
public function needs_input_files(){		return false;}
	
	public function __construct($extra_config = [])
	{
		parent::__construct($extra_config);
	}
	
	public function description()
	{
		return "Corpus installer for VRT files.";
	}
	
	public function long_description($html = true)
	{
		return "This is a basic installer plugin; it does not tag the input text, rather it " 
				. "assumes the input files are already in CQPweb input format (.vrt).";
	}

	public function do_setup()
	{
		if (!$this->check_for_corpus_name())
			return false;
			
		$this->restrict_input_data(false);
		
		if (is_null($this->p_attributes))
			$this->p_attributes = [];
		if (!is_null($this->annotation_template_id))
			$this->declare_annotations_from_template($this->annotation_template_id);
		else
			foreach($this->p_attributes as $p)
				if ('word' != $p)
					$this->declare_annotation($p, "Annotation ``$p''");
		
		if (is_null($this->s_attributes))
			$this->s_attributes = [['element'=>'text', 'attributes'=>['id'=>METADATA_TYPE_UNIQUE_ID]]];
		if (!is_null($this->xml_template_id))
			$this->declare_xml_from_template($this->xml_template_id);
		else
		{
			foreach($this->s_attributes as $s)
			{
				$dts_present = is_string(array_first_key($s['attributes']));
				$this->declare_xml($s['element'], $s['attributes'], $dts_present);
			}
		}
		
		foreach(['primary_annotation','secondary_annotation','tertiary_annotation','tertiary_annotation_tablehandle','combo_annotation'] as $bind)
			if (isset($this->$bind))
				$this->set_binding($bind, $this->$bind);

		
		/* nowt else needed. Perhaps a format checker at some point? */
				
		return true;
	}
	
	public function do_cleanup($delete_input_files = false)
	{
		/* nothing needed; do not call parent. */
	}
	
}

