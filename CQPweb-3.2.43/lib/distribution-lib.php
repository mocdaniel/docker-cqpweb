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
 * This file has classes/functions for the distribution interface in it; 
 * they may go back into the main "distribution" file eventually.
 */






/**
 * Class for an objecxt containing the configurations for a run of the Distribution tool.
 * 
 * Also contains constants for code readability. (And that is why it is not just an array
 * or stdClass which it otherwise could easily be!)
 */
class DistInfo
{
	/* oh, alas alack, how I long for enums! */

	/** We are in DOWNLOAD mode (download text file) */
	const PROG_DOWNLOAD = 0;
	/** We are in GRAPHICAL mode (show distribution tables) */
	const PROG_UI_TABLE = 1;
	/** We are in GRAPHICAL mode (show distribution barcharts) */
	const PROG_UI_CHART = 2;
	/** We are in GRAPHICAL mode (show extremes for texts/IDlinks/etc) */
	const PROG_UI_FREQS = 4;
	
	
	
	/** which version of Distribution are we running? set to one of the class constant beginning in PROG_ */
	public $program;
	
	/** cached copy of the XML-att database objects (for use in various times and places... */
	public $all_xml_info;
	
	/** An attribute on which to perform distribution. This can be '--text' (the default) or any s-attribute.
	 *  If an s-attribute is specified, the distribution is in/out; if an s-attribute of type IDLINK is given,
	 *  then all classification fields are distributed, and crosstabs allowed, as with --text.
	 *  Special treatment of other datatypes (e.g. date) may be added in future. 
	 */
	public $distribution_att;
	
	/** XML attribute information for the distribution att (unless it's --text, in which case this is an empty value) */
	public $att_info = NULL;
	
	/** Variant of theattribute handle for use in a query scope serilaiser. */
	public $qscope_serialiser;
	
	
	/** A classification field to use for crosstabs; some empty value if none. */
	public $crosstabs_classification = NULL;
	
	
	/** Boolean: has the query we are looking at been thinned in such a way as to require "extrapolation" stats? */ 
	public $extrapolate_from_thinned;

	/** Numeric factor to scale the freq by when we extrapolate from a thinned query */
	public $extrapolation_factor = NULL;
	
	/** String containing a renderable (formatted) version of the extrapolation factor (or empty if we aren't extrapolating) */
	public $extrapolation_render = NULL;

	
	/*
	 * Information on what classifications are avaialble.
	 */
	
	/** Handle-to-description map (sorted by key, alphabetically) of classification-type text metadata fields. */
	public $text_classification_fields;
	
	/** 
	 * Array of arrays; outer array has idlink-type XML attributes as keys, 
	 * each mapped to handle-to-description map of classification-type metadata fields for that idlink.
	 */
	public $idlink_classification_fields;
	
	/** record of global emptiness, or not, of the above */
	public $at_least_one_idlink_with_one_classification_field;
	
	/** Boolean: true iff the two arrays of classification fields have, between them, 2 or more classifications - enabling crosstabs */
	public $enough_classifications_for_crosstabs;
	
	/*
	 * Some SQL bits to be used in query assembly. 
	 */
	
	/** SQL table name for table-to-be-joined in the main query run */
	public $join_table;
	
	/** SQL field name for the column-on-which-to-join in the main qwuery run */
	public $join_field;
	
	/** SQL field name for the column containing the number of tokens in each ID in $join_field */
	public $join_ntoks;
	
	/** SQL field name for the column containing the IDs on-which-to-join in the distribution DB. */
	public $db_idfield;
	
	
	/*
	 * Some HTML display stuff
	 */
	
	/** the number of columns that table-headers must span. */
	private $display_colspan;
	
	
	/*
	 * Back-to-concordance tracker variables
	 */
	
	/** Page number of the concordance page we came from (if set) */
	public $concordance_pageNo = NULL;
	
	/** Boolean: was there a page number set on the concordance we came from? */
	public $has_concordance_pageNo;
	
	/** N of hits per page in the concordance we came from (if set) */
	public $concordance_pp = NULL;
	
	/** Boolean: was there a per-page set on the concordance we came from? */
	public $has_concordance_pp;
	
	
	
	/*
	 * Easy access to global vars
	 */
	
	/** QueryRecord generated for what we are dist'ing across */
	public $query_record;
	
	/** Database record (assoc array) for the db we are using */
	public $db_record;
	
	
	
	
	/**
	 * Sets up variables for the Distribution system.
	 * 
	 * @param array       $G             Input array of variables from GET - normally, just pass $_GET.
	 * @param QueryRecord $query_record  The query that we are doing distribution for.
	 * @param array       $db_record     The database to use for the distribution. 
	 * 
	 */
	public function __construct($G, QueryRecord $query_record, $db_record)
	{
		global $Corpus;
		
		/* store query record and db record */
		$this->query_record = $query_record;
		$this->db_record    = $db_record;

		/* collect corpus info.... */
		$this->set_corpus_info();

		/* set a PROGRAM variable, to tell us what we are doing;
		 * and the DISTRIBUTION ATTRIBUTE, for what we are doing it on (will be checked for reality below).
		 */
		$this->set_program_and_att_from_get($G);
		
		
		/* Get other necessary info about the attribute now we know what it is. */
		if ('--text' == $this->distribution_att)
		{
			/* there is NO att_info object to add. */
			/* sql bits for enquiring about texts */
			$this->join_table = "text_metadata_for_{$Corpus->name}";
			$this->join_field = "text_id";
			$this->join_ntoks = "words";
			$this->db_idfield = "text_id";
		}
		else
		{
			/* check that attribute actually exists. (Only way it should not is if we have a hack-attempt.) */
			if (!array_key_exists($this->distribution_att, $this->all_xml_info))
				exiterror("You cannot perform distribution on the basis of that XML attribute (``{$this->distribution_att}''): it does not exist.");

			/* copy object ref */
			$this->att_info = $this->all_xml_info[$this->distribution_att];

			/* currently we do not support anything but --text and idlinks... */
			/* later we may support "att_family" type (in / out) , classification-type... */
			switch ($this->att_info->datatype)
			{
			case METADATA_TYPE_IDLINK:
				/* sql bits for inquiring about idlinks */
				$this->join_table = get_idlink_table_name($Corpus->name, $this->distribution_att);
				$this->join_field = "__ID";
				$this->join_ntoks = "n_tokens";
				$this->db_idfield = $this->distribution_att; 
				break;
			case METADATA_TYPE_NONE:
				// TODO. 
				//break;
			default:
				exiterror("CQPweb does not currently support distribution analysis of XML attributes with this datatype (# {$this->att_info->datatype}). ");
			}
		}


		/* Deduce the qscope serialisation string. */
		if ('--text' == $this->distribution_att)
			$this->qscope_serialiser = '$^' . $this->distribution_att . '|';
		else
		{
//TODO this is known to work for idlink. what about other types?????????
// don't need to worry about for now, because it will error out (above)
			if ($this->att_info->att_family != $this->att_info->handle)
			{
				$familyspec = ($this->att_info->att_family . '|');
				$this->qscope_serialiser = '$^' . $familyspec . substr($this->distribution_att, strlen($familyspec)) . '/';
			}
			else
				$this->qscope_serialiser = $this->distribution_att;
// show_var($this->qscope_serialiser);
		}
		
		
		/* get crosstabs config (NB, crosstabs is only currently available with the "table" program. */
		if (self::PROG_UI_TABLE == $this->program)
			if (isset($_GET['crosstabsClass']))
				$this->crosstabs_classification = cqpweb_handle_enforce($_GET['crosstabsClass']);



		/* 
		 * Set up config variables for thinned-query-extrapolation, and populate from the query record.
		 * 
		 * If the query has been thinned at any point, add extrapolation columns to the output.
		 */
		$this->extrapolate_from_thinned = $query_record->postprocess_includes_thin();
		$this->extrapolation_factor     = $query_record->get_thin_extrapolation_factor();
// $extra_category_filters   = $query_record->get_extra_category_filters();
// TODO
		$this->display_colspan          = 5;
		if ($this->extrapolate_from_thinned) 
		{
			$this->extrapolation_render = number_format(1.0/$this->extrapolation_factor, 4);
			$this->display_colspan = 7;
		}


// TODO what about 
// $extra_category_filters   = $query_record->get_extra_category_filters();
// ???????????????????????????????????????

	} /* end of constructor */
	

	/**
	 * Sets up information about the corpus that we can't get direct from the corpus object,
	 * which can be used around the script to avoid multiple database-seeking calls.
	 */
	private function set_corpus_info()
	{
		global $Corpus;
		
		
		/* get an array of all XML attribute info for this corpus, for reference around the script */
		$this->all_xml_info = get_all_xml_info($Corpus->name);

		
		$grand_total_classifications = 0; /* to check whether we have enough classifications for crosstabs */
		
		/* identify the idlinks, and get their classification columns */
		$this->idlink_classification_fields = array();
		
		foreach ($this->all_xml_info as $k => $x)
		{
			if (METADATA_TYPE_IDLINK == $x->datatype)
			{
				$this->idlink_classification_fields[$k] = array();
				
				foreach(get_all_idlink_field_info($Corpus->name, $k) as $field)
					if (METADATA_TYPE_CLASSIFICATION == $field->datatype)
						$this->idlink_classification_fields[$k][$field->handle] = $field->description;

				ksort($this->idlink_classification_fields[$k]);
				
				$grand_total_classifications += count($this->idlink_classification_fields[$k]);
			}
		}
		ksort($this->idlink_classification_fields);
		
		
		/* now get the classification fields for --text */
		$this->text_classification_fields = array();
		
		foreach (get_all_text_metadata_info($Corpus->name) as $field)
			if (METADATA_TYPE_CLASSIFICATION == $field->datatype)
				$this->text_classification_fields[$field->handle] = $field->description;
		
		ksort($this->text_classification_fields);
		
		$this->at_least_one_idlink_with_one_classification_field = 0 < $grand_total_classifications;
		
		$grand_total_classifications += count($this->text_classification_fields);
		
		$this->enough_classifications_for_crosstabs = ( 2 <= $grand_total_classifications );
	}

	
	/**
	 * Sets $this->program to one of the suitable constants, and deduces the distribution att. 
	 * 
	 * Setup function pulled out of the constructor for clarity, since this bit is complex. 
	 * 
	 * Many variables combine to determine what the program is, and the attribute-for-distribution
	 * can be specified in more than one place.
	 * 
	 * @param array $G  The setup array passed from the constructor (originates form GET).
	 */
	private function set_program_and_att_from_get($G)
	{
		/* start by checking the "redirect" which is where we distinguish between a UI display and a by-item download. */
		
 		if (isset($G['redirect']) && 'distributionDownload' == substr($G['redirect'], 0, 20))
 		{
 			/* we are in download mode ("distributionDownload~$ATT"): to find out which, look the end of the redirect. */
			
 			$this->program = self::PROG_DOWNLOAD;
			
			$this->set_distribution_att_from_second_part_of_string($G['redirect']);
 		}
		else
		{
			/* _all_ other cases. Either "distribution",if we came from concordance,
			 * or "refreshDistribution", if we came from the distribution UI control,
			 * or something else or parameter not set if someone is playing silly beggars */
			
			/* we are in distribution display mode. But which? We go to the other two relevant inputs to find out. */
			
			/* first, check distOver. It will be freqs or class followed by tilde followed by the att. */
			if (!isset($G['distOver']))
				$G['distOver'] = 'class~--text';
			
			switch (substr($G['distOver'], 0, 5))
			{
			// case 'inOut':
			// TODO : complete
// 				break;
			case 'freqs':
				$this->program = self::PROG_UI_FREQS;
				break;
				
			case 'class':
			default:
				
				/* note that bad arguments are dealt with by this also being default. */
				
				$this->program = self::PROG_UI_TABLE;
				
				/* in this event we must check for the switch from TABLE to CHART in the other input */
				if (isset($G['progToChart']) && $G['progToChart'])
					$this->program = self::PROG_UI_CHART;
				
				break;
			}
			/* now we can set the attribute from the second GHALF OF THE STRING. */
			$this->set_distribution_att_from_second_part_of_string($G['distOver']);
		}
		
		/* note that an extra possibility may need to be added to the tree above -- region  in/out, region of type classification. 
		 * See case 'inOut' above. */
	}


	/**
	 * The distribution attribute is always specified as the second half of a ~ -delimited 
	 * GET parameter (tho' which one precisely, differs). 
	 * 
	 * This function sets it correctly and sanitises it but DOES NOT check it. 
	 * @param string $str  Argument string.
	 */
	private function set_distribution_att_from_second_part_of_string($str)
	{
		/* disable errors here so that, in case of too-short array, we can have a zero value that will produce a text-id download. */ 
		@list(, $this->distribution_att) = explode('~', $str);
		
		/* work round other malformations. */
		if (!empty($this->distribution_att))
			$this->distribution_att = cqpweb_handle_enforce($this->distribution_att);
		
		if ('text' == $this->distribution_att || empty($this->distribution_att))
			$this->distribution_att = '--text';
	}
	
	
	/**
	 * Setup function; this one checks the GET for its concordance-position backtrack variables.
	 * 
	 * @param array $G  The setup array passed from the constructor.
	 */
	private function set_preserved_concordance_pagination($G)
	{
		if (isset($G['pageNo']))
		{
			$this->concordance_pageNo = (int)$G['pageNo'];
			$this->has_concordance_pageNo = true;
		}
		else 
			$this->has_concordance_pageNo = false;
		
		if (isset($G['pp']))
		{
			$this->concordance_pp == (int)$G['pp'];
			$this->has_concordance_pp = true;
		}
		else 
			$this->has_concordance_pp = false;
	}
	
	
// 	/** Add the db record for easy global access */
// 	public function append_db_record($db_record)
// 	{
// 		$this->db_record = $db_record;
// 	}
	
// 	/** Add the query record for easy global access */
// 	public function append_query_record($query_record)
// 	{
// 	}
	
	
	public function get_colspan_html()
	{
		return ' colspan="' . $this->display_colspan . '" ';
	}
	
	
} /* end of class */





/**
 * Creates and returns a description of the current distribution program.
 * 
 * @param  DistInfo $dist_info   Program info.
 * @return string                HTML ready for page header embedding
 */
function print_distribution_program_header($dist_info)
{
	switch ($dist_info->program)
	{
	case DistInfo::PROG_UI_FREQS:
		if ('--text' == $dist_info->distribution_att)
			$s = "Currently displaying text-frequency extremes.";
		else
			$s = "Currently displaying frequency extremes for <em>" . escape_html($dist_info->att_info->description) . "</em>.";
		break;
	
	case DistInfo::PROG_UI_TABLE:
	case DistInfo::PROG_UI_CHART:
		if ('--text' == $dist_info->distribution_att)
			$s = "Currently displaying distribution across text classifications";
		else
			$s = "Currently displaying distribution across classifications for <em>" 
					. escape_html($dist_info->att_info->description) . "</em>";
		if (DistInfo::PROG_UI_CHART == $dist_info->program)
			$s .= ' (as bar chart).';
		else
			$s .= '.';
		break;
	
	/* no default; above is exhaustive. */
	}
	
	return $s;
}



/**
 * Creates and returns a message about extrapolation data in the distribution display. 
 * 
 * Extrapolation type can be "column" (message for when data is in col 6, 7); or "tip" (message for when data is in a hover-tip);
 * or anything else - default expected is an empty value - in which case, the assumption is that the extrapolation needs to be warned
 * about, but does not actually show up anywhere.
 * 
 * @param  DistInfo $dist_info           Script configuration object.
 * @return string                        Chunk of HTML to render at the top of the distribution interface. 
 */
function print_distribution_extrapolation_header($dist_info)
{
	if ($dist_info->extrapolate_from_thinned)
	{
		switch ($dist_info->program)
		{
		case DistInfo::PROG_UI_TABLE:
			$shown = true;
			$col_or_tip = 'are given in columns 6 and 7 ';
			break;

		case DistInfo::PROG_UI_CHART:
			$shown = true;
			$col_or_tip = 'appear in the pop-up if you move your mouse over one of the bars ';
			break;

		case DistInfo::PROG_UI_FREQS:
			/* we show the message for when the extrapolated figures aren't actually visible */
			$shown = false;	
			break;

		default:
			/* NOTREACHED */
			exiterror("Logic error: code point that should not be reached, has been reached.");
		}

		$msg = (
			$shown
				? "

					Extrapolated hit counts for the whole result set, and the corresponding frequencies per million words,
					$col_or_tip
					(thin-factor: {$dist_info->extrapolation_render}). 
					The smaller the thin-factor, the less reliable extrapolated figures will be.

				"
 				: "

					(The thin-factor is {$dist_info->extrapolation_render}.)
					Therefore, these frequencies will underestimate the real figures. 
					The lower the factor, the worse the underestimation.

				"
				);
		return <<<END_HTML
			
			<tr>
				<td colspan="4" class="concordgeneral">
					Your query result has been thinned.
					 
					$msg
				</td>
			</tr>
			
END_HTML;
	}
	else
		return '';
}




/**
 * Gets the header line for the distribution interface.
 * 
 * @param  QueryRecord $query_record  The query.
 * @param  bool        $html          Whether to create HTML. Defaults to true. 
 * @return string                     HTML string to print.
 */
function print_distribution_header_line($query_record, $html = true)
{
// 	/* Uses assumptions about what we get from the standard solution heading... */
// 	$solution_header = $query_record->print_solution_heading('Distribution of hits for ', false, $html);
	return $query_record->print_solution_heading('Distribution of hits for', false, $html);
	
// 	/* split and reunite */
// 	list($temp1, $temp2) = explode(' returned', $solution_header, 2);
// 	return rtrim(str_replace('Your', 'Distribution breakdown for', $temp1), ',')
// 		. ': this query returned'
// 		. $temp2
// 		;
}




/**
 * Prints the control block for the top of the distribution page. 
 * 
 * @param  DistInfo     $dist_info      Script configuration object.
 * @return string                       HTML for the table that contains the control. 
 */
function print_distribution_control($dist_info)
{
	/* assemble embeddable HTML bits for the returnable HTML table */
	
	/* 2 header lines. The first says something about the current query; 
	 * the second says something about the currently-visible distribution.
	 */
	$first_header_line = print_distribution_header_line($dist_info->query_record, true);
	
	$second_header_line = print_distribution_program_header($dist_info);
	
	$extrapolation_header_line = print_distribution_extrapolation_header($dist_info);
	
	
	
	/* 
	 * variables for selection-control 
	 */
	
	
	/* assemmble the variable options for the "Display dist of ...." dropdown */
	
	/* are the ones embedded in the HTML framework belows elected? */
	$text_categories_selected = '';
	$text_frequencies_selected = '';
	if ('--text' == $dist_info->distribution_att)
	{
		if (DistInfo::PROG_UI_FREQS == $dist_info->program)
			$text_frequencies_selected = ' selected';
		else
			$text_categories_selected = ' selected';
	}
	
	$idlink_category_options  = '';
	$idlink_frequency_options = '';
	
	/* one of each kind of "option" per idlink; selected if program and distribution att match. */
	foreach ($dist_info->all_xml_info as $x)
	{
		if (METADATA_TYPE_IDLINK == $x->datatype)
		{
			$select_class = '';
			$select_freqs = '';
			if ($x->handle == $dist_info->distribution_att)
			{
				if (DistInfo::PROG_UI_FREQS == $dist_info->program)
					$select_freqs = ' selected';
				else
					$select_class = ' selected';
			}
			$idlink_category_options  .= '<option value="class~' 
					. $x->handle . '"' . $select_class . '>' 
					. escape_html($x->description) . ' categories</option>'
					;
			$idlink_frequency_options .= '<option value="freqs~' 
					. $x->handle . '"' . $select_freqs . '>' 
					. escape_html($x->description) . ' frequency extremes</option>'
					;
		}
	}

	
	/* select the active option in the chart/table dropdown. */
	if (DistInfo::PROG_UI_CHART == $dist_info->program)
	{
		$program_table_selected = '';
		$program_chart_selected = ' selected';
	}
	else
	{
		$program_table_selected = ' selected';
		$program_chart_selected = '';
	}
	
	
	/* create the crosstabs controller */
	if (! $dist_info->enough_classifications_for_crosstabs)
		$crosstabs_cell_content = 'Not available in this corpus.'; 
	else
	{
		$crosstabs_opt_none = '<option value=""' . (empty($dist_info->crosstabs_classification) ? ' selected' : '') . '">No crosstabs available</option>';
		$crosstabs_opt_other = '';
		
		// TODO
		// 
		// 
		// 
		// 
		// 
		// 
		// 
		$crosstabs_cell_content = '<select name="crosstabsClass">' . $crosstabs_opt_other . $crosstabs_opt_none . '</select>'; 
	}
	
	
	/* options for download of idlink freqs : one for each possible type of download (XML idlink). */
	$idlink_dl_opts = '';
	foreach ($dist_info->all_xml_info as $x)
		if (METADATA_TYPE_IDLINK == $x->datatype)
			$idlink_dl_opts .= "\n\t\t\t\t\t"
				. '<option value="distributionDownload~'
				. $x->handle
				. '">Download '	. escape_html($x->description)	. ' frequencies</option>'
				;
	
	
	
	/* hidden variables (per-page, page number) that remember the old concordance "position" 
	 * so that we go back to the same place if the back-from-distribution option is selected. */
	
	$concordance_pagination_hidden_inputs = '';
	
	if ($dist_info->has_concordance_pageNo)
		$concordance_pagination_hidden_inputs .= "<input type=\"hidden\" name=\"pageNo\" value=\"{$dist_info->concordance_pageNo}\">";
	if ($dist_info->has_concordance_pp)
		$concordance_pagination_hidden_inputs .= "<input type=\"hidden\" name=\"pp\" value=\"{$dist_info->concordance_pp}\">";

	
	

	/* finally: do we need a nothing-to-show message? Only if number of classifications across the current dist-att is zero. */
	if ('--text' == $dist_info->distribution_att)
		$nothing_to_show_needed = DistInfo::PROG_UI_FREQS != $dist_info->program && empty($dist_info->text_classification_fields);
	else 
		$nothing_to_show_needed = DistInfo::PROG_UI_FREQS != $dist_info->program && empty($dist_info->idlink_classification_fields[$dist_info->distribution_att]);
	
	if ($nothing_to_show_needed)
	{
		$which = '--text' == $dist_info->distribution_att ? 'texts' : ('<em>' . escape_html($dist_info->att_info->description) . '</em>');
		
		$idlink_proviso = '';
		if (empty($dist_info->att_info) && $dist_info->at_least_one_idlink_with_one_classification_field)
			$idlink_proviso = "<p>Other types of distribution are available (select under &ldquo;Display distribution of&rdquo;)</p>";

		$nothing_to_show_row = <<<END_HTML
		<tr>
			<th class="concordtable" colspan="4">
				<p>This corpus has no classification metadata for $which, so a distribution for $which cannot be shown.</p>

				$idlink_proviso

				<p>You can still select the &ldquo;<em>text-frequency information</em>&rdquo; command from the menu above.</p>
			</th>
		</tr>
END_HTML
		;
	}
	else
		$nothing_to_show_row = '';
	
	
	
	/* all done. Assemble into single HTML block, and return. */
	
 	$html = <<<END_LAYOUT_HTML
	
	<form id="distributionControlForm" action="redirect.php" method="get">

	<table class="concordtable fullwidth">

		<tr>
			<th colspan="4" class="concordtable">
				$first_header_line
				<br>
				$second_header_line
			</th>
		</tr>

		$extrapolation_header_line

		<tr>
			<td class="concordgrey">Display distribution of:</td>

			<td class="concordgrey">
				<select name="distOver">
					<option value="class~--text"$text_categories_selected>Text categories</option>
					$idlink_category_options
					<option value="freqs~--text"$text_frequencies_selected>Text frequency extremes</option>
					$idlink_frequency_options
				</select>
			</td>
			<td class="concordgrey">Show as:</td>
			<td class="concordgrey">
				<select name="progToChart">
					<option value="0"$program_table_selected>Distribution table</option>
					<option value="1"$program_chart_selected>Bar chart</option>
				</select>
			</td>
		</tr>


		<tr>
			<td class="concordgrey">Cross-tabulating against:</td>

			<td class="concordgrey">
				$crosstabs_cell_content
			</td>

			<td class="concordgrey">
				<!-- This cell kept empty to add more controls later --> 
				&nbsp;
			</td>
			<td class="concordgrey">
				<select name="redirect">
					<option value="refreshDistribution" selected>Show distribution</option>
					<option value="distributionDownload~--text">Download text frequencies</option>
					$idlink_dl_opts
					<option value="newQuery">New query</option>
					<option value="backFromDistribution">Back to query result</option>
				</select> 
				<input type="submit" value="Go!">
			</td>
			<input type="hidden" name="qname" value="{$dist_info->query_record->qname}">

			$concordance_pagination_hidden_inputs

		</tr>

		$nothing_to_show_row

	</table>
	
	</form>
	
END_LAYOUT_HTML;

	
	return $html;
	
	


}

/**
 * Writes a distribution table header to standard output.
 * 
 * @param DistInfo $dist_info
 * @param string   $classification_handle
 * @param string   $classification_desc
 */
function do_distribution_table_header($dist_info, $classification_handle, $classification_desc)
{
	if (DistInfo::PROG_UI_CHART == $dist_info->program)
		return;
	/* nothing. Bar chart mode does not print its header rows at this point. */
	
	if ('--text' == $dist_info->distribution_att)
		$region_unit = 'texts';
	else
	{
		if (METADATA_TYPE_IDLINK == $dist_info->att_info->datatype)
			$region_unit = '<em>' . escape_html($dist_info->att_info->description) . '</em> IDs';
	}
	
	?>
	
	<tr>
		<th <?php echo $dist_info->get_colspan_html(); ?> class="concordtable">
			Based on classification:
			<em><?php echo escape_html($classification_desc); ?></em>
		</th>
	</tr>
	<tr>
		<td class="concordgrey">
			Category
			<a id="catSrtButton" class="menuItem hasToolTip" onClick="distTableSort(this, 'cat')" data-tooltip="Sort by category">[&darr;]</a>
		</td>
		<td class="concordgrey" align="center">Words in category</td>
		<td class="concordgrey" align="center">Hits in category</td>
		<td class="concordgrey" align="center">Dispersion<br>(no. <?php echo $region_unit ?> with 1+ hits)
		</td>
		<td class="concordgrey" align="center">
			Frequency
			<a id="freqSrtButton" class="menuItem hasToolTip" onClick="distTableSort(this, 'freq')" data-tooltip="Sort by frequency per million">[&darr;]</a>
			<br>
			per million words in category
		</td>
		
		<?php
		if ($dist_info->extrapolate_from_thinned)
		{
			?>
			
			<td class="concordgrey" align="center">Hits in category<br>(extrapolated)</td>
			<td class="concordgrey" align="center">
				Frequency
				<a id="extrpSrtButton" class="menuItemh hasToolTip" onClick="distTableSort(this, 'extr')" 
					data-tooltip="Sort by extrapolated frequency per million')"
					>[&darr;]</a>
				<br>
				per million words in category
				<br>(extrapolated)
			</td>

			<?php
		}
		?>
		
	</tr>

	<?php
}



/**
 * Produces and prints a distribution table for a given classification. 
 * 
 * @param DistInfo $dist_info
 * @param string   $classification_handle
 * @param string   $classification_desc
 */
function do_distribution_table($dist_info, $classification_handle, $classification_desc)
{
	global $Config;
	global $Corpus;
	
//global $User;
//if ($User->is_admin()) $Config->print_debug_messages = true;
	
	/* print header row for this table */
	do_distribution_table_header($dist_info, $classification_handle, $classification_desc);
	
	/* a list of category descriptions, for later accessing */
	if ('--text' == $dist_info->distribution_att)
		$desclist = list_text_metadata_category_descriptions($Corpus->name, $classification_handle);
	else
		$desclist = idlink_category_listdescs($Corpus->name, $dist_info->distribution_att, $classification_handle);
	foreach ($desclist as $k=>$v)
		$desclist[$k] = escape_html($v);
	
	$e_factor = ($dist_info->extrapolate_from_thinned ? $dist_info->extrapolation_factor : 1.0);
	
	/* variables for keeping track of totals (TABLE MODE) */
	$total_words_in_all_cats = 0;
	$total_hits_in_all_cats = 0;
	$total_hit_ids_in_all_cats = 0;
	$total_ids_in_all_cats = 0;
	
	/* storage for info for bars (BAR MODE) */
	$max_per_mill = 0;
	$master_array = array();
	$master_ix = 0;
	
	
	/* the main query that gets table data */
	$sql = 
		"SELECT md.`$classification_handle`              as handle,
		count(db.`$dist_info->db_idfield`)               as hits,
		count(distinct db.`$dist_info->db_idfield`)      as n_ids           # this is not in the version used for the bar charts
		FROM `{$dist_info->join_table}`                  as md
		LEFT JOIN `{$dist_info->db_record['dbname']}`    as db

		ON md.`{$dist_info->join_field}` = db.`{$dist_info->db_idfield}`
		GROUP BY md.`$classification_handle`
		ORDER BY md.`$classification_handle`
		";
	$result = do_sql_query($sql);
	
	/* for each category: */
	while ($c = mysqli_fetch_object($result))
	{
		/* skip the category of "null" ie no category in this classification */
//		if (empty($c->handle))
//			continue;
// this is for hits, for isntance, which are not in u when we look at b/d by speaker cat.
// but we don't actually need it. The join sorts it out for us.
// the effect of this was to drop categories whose handle was an "empty" string - obvs a bug. 

		/* rest of this loop: we are nto in bar char mode. */

// 		$hits_in_cat = $c->hits;
// 		$hit_ids_in_cat = $c->n_ids; /* number of IDs with 1+ hit */
// vars above no onger needed cos we use the obj values directly below. 

		/* string containing this category's restriction - for the intersection */
		$serialised_on_the_fly = $dist_info->qscope_serialiser . $classification_handle . '~' . $c->handle;
		// TODO might be better to factor out the above somewhere????

//squawk("About to create intersect arg QS dfrom serialisation: '$serialised_on_the_fly'");
//$intersect_arg = QueryScope::new_by_unserialise($serialised_on_the_fly);
//$scope_on_the_fly = $dist_info->query_record->qscope->get_intersect($intersect_arg);
		$scope_on_the_fly = $dist_info->query_record->qscope->get_intersect(QueryScope::new_by_unserialise($serialised_on_the_fly));
		if (false === $scope_on_the_fly)
			exiterror("Drawing this distribution table requires calculation of a subsection intersect that is not yet possible in this version of CQPweb.");
		else
		{
			$words_in_cat = $scope_on_the_fly->size_tokens();
			$items_in_cat = $scope_on_the_fly->size_items();
			$ids_in_cat   = $scope_on_the_fly->size_ids();
// FIXME temp bodge: (cos number of ids is tucked in items her5e...... 
			if (0 == $ids_in_cat)
				$ids_in_cat = $items_in_cat;
// end temp bodge.
//			if ('' != ($item_type = $scope_on_the_fly->get_item_type()))
//				$ids_in_cat = $scope_on_the_fly->size_ids();
		}
// end temp replacement */
		$words_in_cat = $words_in_cat ?? 0;
// TODO above line couldn be incorporated into the ELSE above.
//		if (is_null($words_in_cat))
//			$words_in_cat = 0;
		$ids_in_cat = $ids_in_cat ?? 0;
//		if (is_null($ids_in_cat))
//			$ids_in_cat = 0;
		
		
		if (DistInfo::PROG_UI_CHART == $dist_info->program)
		{
			/* build up an array! */
			$c->words_in_cat = $words_in_cat;
			$c->per_mill = (
				0 == $c->words_in_cat 
				? 0
				: round(($c->hits / $c->words_in_cat) * 1000000.0, 2)
				);
			if ($c->per_mill > $max_per_mill)
				$max_per_mill = $c->per_mill;
			
			/* stash $c */
			$master_array[$master_ix++] = $c;
			/* ++ so that the next time we iterate, it goes in a different slot. */
			
			
			/* the print loop is for the table only. */
			continue;
		}
		
//TODO dist postprocess not working yet w/ anything other than text
//		if ('--text' == $dist_info->distribution_att)
		if ('text' == $scope_on_the_fly->get_item_type())
		{
			$link = "concordance.php?qname={$dist_info->query_record->qname}&newPostP=dist&newPostP_distCateg=$classification_handle&newPostP_distClass={$c->handle}";
			$link_begin = '<a href="' . $link . '">';
			$link_end = '</a>';
		}
		else
			$link_begin = $link_end = $link = '';
		
		/* print a data row */
		?>
		
		<tr>
			<td class="concordgeneral" id="<?php echo $c->handle;?>">
				<?php 
				// TODO needed? will empty description have been checked for when desclist was built? --> check this
				if (empty($desclist[$c->handle]))
					echo $c->handle, "\n";
				else
					echo $desclist[$c->handle], "\n";
				?>
			</td>
			<td class="concordgeneral" align="center">
				<?php echo number_format($words_in_cat), "\n";?> 
			</td>
			<td class="concordgeneral" align="center">
				<?php echo $link_begin, number_format($c->hits), $link_end, "\n"; ?>
			</td>
			<td class="concordgeneral" align="center">
<?php
//TODO  the following needs to be conditional: ids if the intersected restriction is all id-based (e.g text + id metadata)
//but intervals if  it's arbitrary slices.
// or perhaops, if just arbitrary ranges, just print "-" (cos result prob not meaningful?)
?>
				<?php echo number_format($c->n_ids), ' out of ', number_format($ids_in_cat), "\n"; ?> 
			</td>
			<td class="concordgeneral" align="center">
				<?php echo(0 == $words_in_cat ? '0' :  number_format(($c->hits / $words_in_cat) * 1000000, 2) ), "\n"; ?> 
			</td>

			<?php
			if ($dist_info->extrapolate_from_thinned)
			{
				?>

				<td class="concordgeneral" align="center">
					<?php echo number_format($c->hits * $e_factor, 0), "\n"; ?>
				</td>
				<td class="concordgeneral" align="center">
					<?php echo(0 == $words_in_cat ? '0' :  number_format(($c->hits / $words_in_cat) * 1000000 * $e_factor, 2) ), "\n"; ?>
				</td>

				<?php
			}
			?>

		</tr>

		<?php

		/* add to running totals */
		$total_words_in_all_cats     += $words_in_cat;
		$total_hits_in_all_cats      += $c->hits;
		$total_hit_ids_in_all_cats   += $c->n_ids;
		$total_ids_in_all_cats       += $ids_in_cat;
	}

	if (DistInfo::PROG_UI_CHART == $dist_info->program)
	{
		if (0 == $max_per_mill)
		{
			/* no category in this classification has any hits */
			echo <<<END_HTML
					
					<tr>
						<th class="concordtable">
							No category within the classification scheme 
							"$classification_desc" has any hits in it.
						</th>
					</tr>
				</table>
				<table class="concordtable fullwidth">
				
END_HTML;
			return;
		}
		
		$n = count($master_array);
		$num_columns = $n + 1;
		
		/* header row */
		
		?>
		
		<tr>
			<th colspan="<?php echo $num_columns; ?>" class="concordtable">
				<?php echo "Based on classification: <em>$classification_desc</em>", "\n"; ?>
			</th>
		</tr>
		<tr>
			<td class="concordgrey"><strong>Category</strong></td>
			<?php
			/* line of category labels */
			for($i = 0; $i < $n; $i++)
				echo '<td class="concordgrey" align="center"><strong>' . $master_array[$i]->handle . '</strong></td>', "\n";
			?>
		</tr>
		<tr>
			<td class="concordgeneral">&nbsp;</td>
			
			<?php
			
			/* line of bars */
			
			for ($i = 0; $i < $n; $i++)
			{
				// TODO needful? see note above, desclist may have beenb checked when built. 
				if (empty($desclist[$master_array[$i]->handle]))
					$this_label = $master_array[$i]->handle;
				else
					$this_label = $desclist[$master_array[$i]->handle];
				
				//TODO get rid of <font> in the hover text
				$html_for_hover = "Category: <strong>$this_label</strong><br><hr color=&quot;#000099&quot;>" 
					. '<font color=&quot;#DD0000&quot;>' . number_format($master_array[$i]->hits) . '</font> hits in '
					. '<font color=&quot;#DD0000&quot;>' . number_format($master_array[$i]->words_in_cat) 
					. '</font> words.'
		 			;
				if ($dist_info->extrapolate_from_thinned)
					$html_for_hover 
						.= '<br><hr color=&quot;#000099&quot;><strong>Extrapolated</strong> no. of hits: <font color=&quot;#DD0000&quot;>' 
						. number_format($master_array[$i]->hits * $dist_info->extrapolation_factor, 0)
						. '</font> hits<br>(<font color=&quot;#DD0000&quot;>'
						. number_format($master_array[$i]->per_mill * $dist_info->extrapolation_factor, 2)
						. '</font> per million words).'
						;
				
				$this_bar_height = round( ($master_array[$i]->per_mill / $max_per_mill) * 100 , 0);
				
				/* make this a link to the limited query when I do likewise in the distribution table */
				echo '<td align="center" valign="bottom" class="concordgeneral">'
					, '<a id="barlink', $i, '" data-tooltip="' , $html_for_hover , '">'
					, '<img border="1" src="' 
						, $Config->dist_graph_img_path
						, '" width="70" height="'
						, $this_bar_height
						, '" align="absbottom"></a></td>'
					, "\n"
					;
			}
			
			?>
		</tr>
		<tr>
			<td class="concordgrey"><strong>Hits</strong></td>
			<?php
			/* line of hit counts */
			for ($i = 0; $i < $n; $i++)
				echo "\n\t\t\t", '<td class="concordgrey" align="center">' , number_format($master_array[$i]->hits) , '</td>';
			echo "\n";
			?>
		</tr>
		<tr>
			<td class="concordgrey"><strong>Cat size (MW)</strong></td>
			<?php
			/* line of cat sizes */
			for ($i = 0; $i < $n; $i++)
				echo '<td class="concordgrey" align="center">'
					, number_format(($master_array[$i]->words_in_cat / 1000000.0), 2)
					, '</td>'
					;
			echo "\n";
			?>
		</tr>
		<tr>
			<td class="concordgrey"><strong>Freq per M</strong></td>
			<?php
			/* line of per-million-words */
			for ($i = 0; $i < $n; $i++)
				echo '<td class="concordgrey" align="center">'
					, number_format($master_array[$i]->per_mill, 2)
					, '</td>'
					;
			echo "\n";
			/* Now, end the table and re-start for the next graph, so it can have its own number of columns */
			?>
		</tr>
	</table>
	
	<table class="concordtable fullwidth">
	
		<?php
	}
	else
	{
		/* print total row of table */
		?>
		
		<tr>
			<td class="concordgrey">Total:</td>
			<td class="concordgrey" align="center">
				<?php echo number_format($total_words_in_all_cats), "\n"; ?>
			</td>
			<td class="concordgrey" align="center">
				<?php echo number_format($total_hits_in_all_cats), "\n"; ?>
			</td>
			<td class="concordgrey" align="center">
				<?php echo number_format($total_hit_ids_in_all_cats); ?> out of <?php echo number_format($total_ids_in_all_cats), "\n"; ?>
			</td>
			<td class="concordgrey" align="center">
				<?php echo number_format(($total_hits_in_all_cats / $total_words_in_all_cats) * 1000000, 2), "\n"; ?>
			</td>
			<?php
			if ($dist_info->extrapolate_from_thinned)
			{
				echo "\n";
				?>
				<td class="concordgrey" align="center">
					<?php echo number_format($total_hits_in_all_cats * $e_factor), "\n"; ?>
				</td>
				<td class="concordgrey" align="center">
					<?php echo number_format(($total_hits_in_all_cats / $total_words_in_all_cats) * 1000000 * $e_factor, 2), "\n"; ?>
				</td>
				<?php
			}
			echo "\n";
			?>
			
		</tr>
		
		<?php
	}

}






/**
 * Performs the action of writing out the requested distribution data in the correct format.
 * This covers barcharts AND tables, crosstabs AND no-crosstabs.
 * 
 * @param DistInfo     $dist_info      Script configuration object.
 */
function do_distribution_classifications($dist_info)
{
	$array_of_classifications = (
			'--text' == $dist_info->distribution_att 
			?
			$dist_info->text_classification_fields 
			:
			$dist_info->idlink_classification_fields[$dist_info->distribution_att]
			);
	?>
	
	<table class="concordtable fullwidth">
	
	<?php 
	foreach($array_of_classifications as $classification_handle => $classification_desc)
		do_distribution_table($dist_info, $classification_handle, $classification_desc);
	?>
	
	</table>
	
	<?php 
}



// TODO do_distribution_in_and_out() for DATATYPE_NONE? ? ??? OR can do_distribution_table() be modified to do that>?
// if new func needed, what kind of test do we need to add to  do_distribution_classifications() ?



/**
 * Performs the action of writing out the requested frequency-extremes data in the correct format.
 * 
 * @param DistInfo     $dist_info      Script configuration object.
 */
function do_distribution_freq_extremes($dist_info)
{
	global $Config;
	
	/* simpler var names */
	$query_record = $dist_info->query_record;
	$db_record = $dist_info->db_record;
	
	/* the big SQL query. Start without order by clause; add it in when running./ */
	
	$id_field_handle = $dist_info->db_idfield;
	
	$base_sql = "SELECT 
			db.`{$dist_info->db_idfield}`     as `$id_field_handle`, 
			md.`{$dist_info->join_ntoks}`     as n_tokens, 
			count(*)                          as hits,
			(1000000 * (count(*)/md.`{$dist_info->join_ntoks}`))
			                                  as per_mill
		FROM `{$db_record['dbname']}`         as db 
#		LEFT JOIN `{$dist_info->join_table}`  as md   
# changed to inner join: we don't want any cases where the hit has no values for the idlink in question. 
		INNER JOIN `{$dist_info->join_table}` as md 
		ON db.`$id_field_handle` = md.`{$dist_info->join_field}`
		GROUP BY db.`$id_field_handle`
		HAVING hits > 0
		AND `$id_field_handle` != ''
		";
	
	$max_sql = $base_sql . "ORDER BY per_mill desc LIMIT {$Config->dist_num_files_to_list} ";
	$min_sql = $base_sql . "ORDER BY per_mill asc  LIMIT {$Config->dist_num_files_to_list} ";

// note that this SLOOOOOW (the one for the min in particular.). Not sure why. Worry about speedup later. TODO.
	
	
	/* render start of table */
	?>

	<table class="concordtable fullwidth">
	
	<?php 
	
	foreach (array ('most' => $max_sql, 'least' => $min_sql) as $superlative_label => $sql)
	{
		/* get bits of text ready for start of table */
		
		$header_message = 'Results for your query were found <em>' . $superlative_label . '</em> frequently';
		if ('--text' == $dist_info->distribution_att)
			$header_message .= ' in the following texts' 
 				. ('least' == $superlative_label ? ' (only texts with at least 1 hit are included)' : '') 
				. ':'
				;
		else
			$header_message 
				.= ' with the following ' 
 				. escape_html($dist_info->all_xml_info[$dist_info->distribution_att]->description) 
 				. '-type IDs' 
 				. ('least' == $superlative_label ? ' (only IDs with at least 1 hit are included)' : '') 
				. ':'
				;
 			// TODO this is rotten way to word it. But I can't think of anything better right now. 
		
		
		/* render start of table */
		
		?>

		<tr>
			<th colspan="4" class="concordtable">
				<?php echo $header_message; ?>
			</th>
		</tr>
		<tr>
			<th class="concordtable">Text</th>
			<th class="concordtable">Number of words</th>
			<th class="concordtable">Number of hits</th>
			<th class="concordtable">Frequency<br>per million words
			</th>
		</tr>

		<?php 
		
		$result = do_sql_query($sql);
		
		while ($o = mysqli_fetch_object($result))
		{
			/* get the two links ready. */
			if ('--text' == $dist_info->distribution_att)
			{
				$idlink_url = 'textmeta.php?text=' . $o->$id_field_handle ;
				$postp_link_begin = '<a href="' 
 					. "concordance.php?qname={$query_record->qname}&newPostP=text&newPostP_textTargetId={$o->$id_field_handle}"
					. '">'
 					;
				$postp_link_end = '</a>';
			}
			else
			{
				$idlink_url = 'idmeta.php?idlink=' . $dist_info->distribution_att . '&id=' . $o->$id_field_handle ;
				$postp_link_begin = '';
				$postp_link_end = '';
				/* for now, we don't have a postprocess to restrict to just-in-this-IDLInkVAL. 
				 * but we'll need one - call it idval?
				 */
			}

			?>
			
			<tr>
				<td align="center" class="concordgeneral">
					<a href="<?php echo $idlink_url; ?>"><?php echo $o->$id_field_handle; ?></a>
				</td>
				<td align="center" class="concordgeneral">
					<?php echo number_format($o->n_tokens), "\n"; ?>
				</td>
				<td align="center" class="concordgeneral">
					<?php echo $postp_link_begin, number_format($o->hits), $postp_link_end, "\n"; ?>
				</td>
				<td align="center" class="concordgeneral">
					<?php echo number_format($o->per_mill, 2), "\n"; ?>
				</td>
			</tr>
			
			<?php
			
		}
		
	}
	
	/* all done, so now wrap up the table */
	?>
	
	</table>

	<?php
}





/**
 * Performs the action of writing a plain-text download of file frequencies
 * (or XML idlink ID frequencies).
 * 
 * @param DistInfo     $dist_info      Script configuration object.
 */
function do_distribution_plaintext_download(DistInfo $dist_info)
{
	/* -------------------------------------------------------------------------------------- *
	 * Here is how we do the plaintext download of all frequencies (by text, or XML ID-link). *
	 * -------------------------------------------------------------------------------------- */
	
	global $User;
	
	/* create local vars to simplify SQL emmbedding... */
	$dbname                   = $dist_info->db_record['dbname'];
	$db_idfield               = $dist_info->db_idfield; 
	$join_field               = $dist_info->join_field;
	$join_ntoks               = $dist_info->join_ntoks;
	$join_table               = $dist_info->join_table;
	$extrapolate_from_thinned = $dist_info->extrapolate_from_thinned;
 	$extrapolation_render     = $dist_info->extrapolation_render;
	$distribution_att         = $dist_info->distribution_att;
	$idlink_att_info          = $dist_info->att_info;

	
	$sql = "SELECT 
			db.`$db_idfield` as item_id, 
			md.`$join_ntoks` as n_tokens, 
			count(*) as hits 
		FROM `$dbname` as db 
		LEFT JOIN `$join_table` as md ON db.`$db_idfield` = md.`$join_field`
		WHERE db.`$db_idfield` != ''
		GROUP BY db.`$db_idfield`
		ORDER BY db.`$db_idfield`";
	
	$result = do_sql_query($sql);
	/* TODO (non-urgent) this seems to be quite a slow query to run.... check optimisation possible? */

	$eol = $User->eol();
	
	$description = print_distribution_header_line($dist_info->query_record, false);
	
	$extradesc = 
				( $extrapolate_from_thinned
				? "{$eol}WARNING: your query has been thinned (factor $extrapolation_render)."
					. "{$eol}Therefore, these frequencies will underestimate the real figures. "
					. "The lower the factor, the worse the underestimation."
				: ''
				);
	
	$filename_prefix = ('--text' == $distribution_att? 'text' : preg_replace('/\W/', '', $idlink_att_info->description));
	
	header("Content-Type: text/plain; charset=utf-8");
	header("Content-disposition: attachment; filename={$filename_prefix}_frequency_data.txt");
	
	echo $description, $extradesc, $eol;
	echo "__________________", $eol, $eol;

	if ('--text' == $distribution_att)
		echo "Text\t"
			, "No. words in text\t"
			, "No. hits in text\t"
			, "Freq. per million words"
			, $eol, $eol
			;
	else
	{
		echo "{$idlink_att_info->description}\t"
			, "No. words for {$idlink_att_info->description}\t"
			, "No. hits for {$idlink_att_info->description}\t"
			, "Freq. per million words"
			, $eol, $eol
			;
	}

	while ($r = mysqli_fetch_object($result))
		echo $r->item_id, "\t", $r->n_tokens, "\t", $r->hits, "\t", round(($r->hits / $r->n_tokens) * 1000000, 2), $eol;
	
	/* end of code for plaintext download. */
}


