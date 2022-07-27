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
 * This contains interfaces / classes/functions needed for CQPweb plugins.
 */

/**
 * General plugin interface that defines the protoype of the constructor,
 * don't actually use this, only the interfaces that inherit from it.
 * 
 * Please note, if you update ANY of the phpdoc blocks for this interface or
 * any that inherit from it, then make sure that the equivalent text in 
 * doc/CQPweb-plugins.html is still correct! 
 */
interface CQPwebPlugin
{
	/**
	 * The constructor function of a Plugin may be passed a single argument.
	 * 
	 * If it is, it is a hash of extra parameter info, defined according to the 
	 * nature of the plugin. 
	 * 
	 * This argument's default value is an empty array. Any "empty" value,
	 * such as '', NULL, 0 or false, should be interpreted as "no config file".
	 * 
	 * The internal format of the config file, and how it is parsed and the info
	 * stored, is a matter for the plugin to decide. Config files can be anywhere
	 * on the system that is accessible to the username that CQPweb runs under.
	 * 
	 * @param  array  $extra_config    Unordered hash containing extra configuration
	 *                                 info for a specific plugin.  
	 */
	public function __construct($extra_config = []);
	
	/** 
	 * Returns a string containing the title or short description of this plugin. 
	 * This should be relatively short, and not contain any HTML.  
	 */
	public function description();
	
	/** 
	 * Returns a string describing the plugin. This may be the same as returned by
	 * description() but can also be longer. Line breaks ("\n") in the string 
	 * may be rendered as HTML line breaks in some contexts, unless HTML 
	 * is requested.
	 * 
	 * @param  bool   $html  If true, the plugin may (but need not) add HTML styling.
	 * @return string        String containing long description of what the plugin does.
	 */
	public function long_description($html = true);

	/**
	 * Return true if the plugin is OK, false if not. If false,
	 * there should be something readable via the error_desc() call.
	 * 
	 * @return bool    Status of the plugin.
	 */
	public function status_ok();
	
	/**
	 * Returns a string describing the last encountered error.
	 * 
	 * If there has been no error, then it can return an empty string,
	 * or a message saying there has been no error. It doesn't matter which.
	 * 
	 * @param  bool   $html  If true, the plugin may (but need not) add HTML styling.
	 * @return string        String containing error description.
	 */
	public function error_desc($html = true);
	
}



/**
 * Interface for Annotator Plugins.
 * 
 * An Annotator Plugin is an object that represents a program external
 * to CQPweb that can be used to manage files in some way (e.g. by 
 * tagging them.) 
 */
interface Annotator extends CQPwebPlugin
{
	/**
	 * Process a file (e.g. to tag or tokenise it).
	 * 
	 * Both arguments are relative or absolute paths. The method SHOULD NOT use
	 * CQPweb global variables.
	 * 
	 * The input file MUST NOT be modified.
	 * 
	 * This function should return false if the output file was not 
	 * successfully created.
	 * 
	 * If the output file is partially created or created with errors, it
	 * should be deleted before false is returned.
	 * 
	 * @return bool     True if file fully processed. 
	 */
	public function process_file($path_to_input_file, $path_to_output_file);

	/**
	 * Process a group of files into a single big vrt file.
	 * 
	 * @param  array   $input_paths             Array of files to process. 
	 * @param  string  $path_to_output_file     Where to save the output. 
	 * @return bool                             Status after: ok?
	 */
	public function process_file_batch($input_paths, $path_to_output_file);

	
	/* 
	 * functions describing the output of the annotation.
	 */
	
	
	/**
	 * Returns the size of the last output file created as an integer count of bytes.
	 * 
	 * If no file has yet been processed, return 0.
	 * 
	 * @return  int      Size of last file processed
	 */
	public function output_size();
	
	/**
	 * Returns an ordered array of annotation specifications.
	 * Each spec is an array with three components: 'handle',
	 * 'description', and (optionally) 'is_feature_set' 
	 * (boolean).
	 * 
	 * The 'word' column should be omitted.
	 * 
	 * Alternatively can return false; then nothing is known about the columns,
	 * except their number, which comes from a different method.
	 * 
	 * @return array  An array of column handles.
	 */
	public function output_annotation_list();
	
	/**
	 * Find out how many columns the output file has. 
	 * 
	 * @return int The number of annotation columns (i.e. excluding
	 *             the intital "word" column, so this can be 0).
	 */
	public function output_n_annotations();
	
	
	/**
	 * Returns an array of XML specifications for the corpus setup.
	 * Each spec is an array of two components: 'element' (the XML
	 * element's tag) and an 'attributes' array - which may be empty 
	 * - of descriptors for its attributes. 
	 * 
	 * If the intention is for these specifiers to be used with 
	 * CorpusInstallerBase::declare_xml(), each descriptor should be 
	 * formatted follows: an associative array,
	 * mapping attribute handles (e.g. id) to datatypes constants.
	 * Or, a flat array of just attribute handles. 
	 * e.g. ['id'=>METADATA_TYPE_UNIQUE_ID, 'name'=>METADATA_TYPE_FREETEXT]
	 * or   ['id', 'name', 'classification']  .
	 * 
	 * @return array   An array as described above.
	 */
	public function output_xml();
}

	
/**
 * Interface for CorpusInstaller Plugins.
 * 
 * A CorpusInstaller Plugin is a driver for corpus setup by users.
 * 
 * It carries out all steps in the procedure. This may include running an Annotator,
 * checking file formats, doing cleanup, etc. Files may be generated anew,
 * or created from operations on the user's existing files. 
 * 
 * The plugin does not actually run cwb-encode and friends. 
 * But it does supply options for setup based on what it's done. 
 */
interface CorpusInstaller extends CQPwebPlugin
{
	/**
	 * Make the plugin restrict the amount of data by tokens.
	 * If 0 or a negative limit is set, no restriction at all applies.
	 * @param int $max
	 */
	public function set_max_input_tokens($max);
	
	
	/**
	 * Tell the plugin what the CQPweb handle ofr the corpus will be.
	 * (Necessary for it to generate the correct SQL statements.)
	 * @param string $name   A lowercase CQP-corpus name.
	 */
	public function set_corpus_name($name);
	

	/**
	 * Ascertain whether this CorpusInstaller needs input files
	 * (some plugins generate data externally). This controls 
	 * whether the file selector is enabled/disabled, and whether
	 * any input files are passed in. 
	 * 
	 * @return bool  True if input files are needed.
	 */
	public function needs_input_files();
	
	/**
	 * Add a given path to the list of files to be used as input to the installation.
	 * @param string|array $path   A path, or an array of paths.
	 */
	public function add_input_file($path);
	
	/**
	 * Run setup - that is,  anything that needs to be done to get files 
	 * ready to be encoded. This might include tagging, or even building a corpus.
	 * 
	 * @return bool    True if setup worked OK; false if not.
	 */
	public function do_setup();
	
	/**
	 * Run text metadata (and/or xml metadata) setup, if any.
	 */
	public function do_metadata();
	
	/**
	 * Run cleanup, e.g., deleting temporary files, if any.
	 * @param bool $delete_input_files  If true, the files specified using
	 *                                  CorpusInstaller::add_input_file() will be deleted.
	 */
	public function do_cleanup($delete_input_files = false);
	
	/**
	 * Get the CWB string indicator for the charset of the corpus text.
	 * @return string
	 */
	public function get_charset();
	
	/**
	 * Gets information about the p-attributes for cwb encoding
	 * (an array of strings for use with -P with cwb-encode).
	 * @return array
	 */
	public function get_p_attribute_info();
	
	/**
	 * Gets information about the s-attributes for cwb encoding
	 * (an array of strings for use with -S with cwb-encode).
	 */
	public function get_s_attribute_info();
	
	/**
	 * Gets the annotation bindings that will be used in the created 
	 * corpus (for Simple Queries). 
	 * @return array     A hash with one or more keys referring to
	 *                   'special' syntax in CEQL, each mapping to
	 *                   the p-attribute that will be searched.
	 *                   For instance, 'primary_annotation'=>'pos'.
	 *                   The "tertiary_annotation_tablehandle"
	 *                   is the only non-p-attribute binding; it 
	 *                   must be for a table that actually exists 
	 *                   on the system, if not, declare_maptable()
	 *                   can be used.
	 */
	public function get_annotation_bindings();
	
	/**
	 * Hash of simple-pos to p-attribute regex. CQPweb will add it 
	 * as a mapping table to the system. If you don't want to bother
	 * with this, just have an empty function (which is what the 
	 * CorpusInstallerBase does).
	 * 
	 * @return array
	 */
	public function declare_maptable();
	
	/**
	 * Gets information about the files to be specified for corpus encoding
	 * (which the CorpusInstaller plugin may have created itself).
	 */
	public function get_infile_info();
	
	/**
	 * Ascertains whether it is necessary to run a datatype check on the XML.
	 * @return bool    True if the datatype check should be performed.
	 */
	public function get_xml_datatype_check_needed();
	
	/** 
	 * Gets an array of SQL statements to run to complete installation
	 * (for declaration of annotation, XML, etc.) 
	 * 
	 * The CorpusInstallerBase class has some methods for setting these
	 * up from your p/s-attribute data without having to actually write the SQL.
	 * 
	 * @return array     Can be empty though usually it would not be.
	 */
	public function get_sql_for_corpus();
	
	
	/**
	 * Gets an array of SQL statements to "uncreate" the corpus.
	 * (This means, when installation aborts, this function
	 * will supply SQL statements to get rid of leftover entries
	 * in assorted tables.)  
	 * 
	 * @return array     Can be empty though usually it would not be.
	 */
	public function get_sql_for_abort();
}

/**
 * Interface for FormatChecker Plugins.
 * 
 * An FormatChecker Plugin is an object capable of checking files for their 
 * compliance with some specified format - like, say for instance, "valid
 * UTF 8 text", "valid XML", "valid CWB input format". It can do this either using
 * internal PHP code, or by calling an external program.
 */
interface FormatChecker extends CQPwebPlugin
{
	/**
	 * Checks the specified file to see if it complies with this FormatChecker's
	 * particular file-formatting rules.
	 * 
	 * The argument can be absolute or relative path but the file it specifies
	 * MUST NOT be changed in any way.
	 * 
	 * @param  string $path_to_input_file  The file to be checked.
	 * @return bool                        True if the file meets all the rules, 
	 *                                     or false if there is one or more 
	 *                                     problems. (Thisa might include the 
	 *                                     file not existing or being unreadable.)
	 */
	public function file_is_valid($path_to_input_file);
	
	/**
	 * Returns the integer line number of the location, within the file that was
	 * last checked, where the error described by $this->error_desc
	 * was noticed. 
	 * 
	 * Note that this is NOT necesasarily the place where the error
	 * actually occurred. In some types of format checker, such as an XML parser,
	 * errors may become apparetn well after they actually happened.
	 *
	 * Should return NULL if either (a) the implementing class does not keep track
	 * of the location of errors or (b) the last file processed did not have any
	 * problems in it or (c) no file has been processed yet.
	 * 
	 * The first line of a file is considered to be line 1, not line 0.
	 * 
	 * @return int    Number of the line where the error occurred (NULL if no error).
	 */ 
	public function error_line_number();
	
	/**
	 * Returns the integer byte offset of the location, within the line given by
	 * $this->error_line_number(), where the error described by $this->error_desc
	 * was noticed.
	 * 
	 * Note it is a byte offset not a character offset in the case of non-8-bit
	 * data.
	 * 
	 * Should return NULL if either (a) the implementing class does not keep track
	 * of the location of errors or (b) the last file processed did not have any
	 * problems in it or (c) no file has been processed yet.
	 * 
	 * @return int    Column (as byte) where the error occurred (NULL if no error).
	 */
	public function error_line_byte();
}


/**
 * Interface for ScriptSwitcher Plugins.
 * 
 * A ScriptSwitcher Plugin is an object which implements this interface.
 * 
 * It must be able to something sensible with any UTF8 text passed to it.
 * 
 * This will normally mean transliterating it to Latin or other alphabet
 * native/familiar to the user base.
 * 
 * A class implementing this interface can do this however it likes - 
 * internally, by calling a library, by creating a back-end process
 * and piping data back and forth - CQPweb doesn't care.
 * 
 * What you are NOT allowed to do in a plugin is use any of CQPweb's
 * global data. (Or rather, you are ALLOWED to - it's your computer! -
 * I just don't think it would be a good idea at all.)
 * 
 * There are other functions defined in the base class. They are not 
 * compulsory, but if you use them, the code you need to write for
 * the transliterate() function will be rather less. 
 * 
 * This interface used to have the much simpler name "Transliterator",
 * but it turns out this clashes with a class added to the "intl"
 * extension in PHP 5.4.
 */
interface ScriptSwitcher extends CQPwebPlugin
{
	/**
	 * This function takes a UTF8 string and returns a UTF8 string.
	 * 
	 * The returned string is the direct equivalent, but with some
	 * (or all) characters from the source writing system converted
	 * to the target writing system.
	 * 
	 * It must be possible to pass a raw string straight from CQP,
	 * and get back a string that is still structured the same
	 * (so CQPweb functions don't need to know about whether or not
	 * transliteration has happened).
	 * 
	 * @return  string   String in the new script.
	 */
	public function transliterate($string);
}



interface CorpusAnalyser extends CQPwebPlugin
{
	// TODO   plugin to display an analaysis screen for a corpus or subcorpus.
	// like postprocessor, needs the oppportunity to pop up a screen asking for settings.
}



/**
 * This interface contains the shared parts of the Postprocessor / QueryAnalyser
 * interfaces, to avoid repetition. 
 * 
 * Both these types of plugin (a) add an entry to the Concocrdance menu, 
 * (b) do something with the data of a query. 
 * 
 * The difference between the two is that a Postprocessor *creates a new query*
 * (i.e. a postprocessed query ... hence the name) whereas a QueryAnalyser
 * *generates arbitrary analytic data* to be viewed or downloaded.  
 */
interface GenericQueryActionInterface extends CQPwebPlugin
{
	/**
	 * Returns the menu label to invoke this postprocess or query analysis.
	 *
	 * Query actions, both posprocesses (like Thin) or query analysis (like Dispersion) 
	 * are invoked from the dropdown on the concordance screen. So, query action plugins
	 * plugins must tell CQPweb what they want their label on that dropdown to be.
	 * (The corresponding value for the HTML form select element will be based on the 
	 * classname - but don't depend on this as it is an implementaton detail.  
	 * classname.)
	 *
	 * This function should return either a string, or any empty value if this query action
	 * is not to be displayed in the dropdown menu.
	 * 
	 * The string may not be identical to any of the options present on the built-in dropdown 
	 * menu. (Nothing in particular would go wrong if they were - it would just be 
	 * extremely confusing.)
	 * 
	 * @return string        String containing the label for the menu (no HTML styling). 
	 */ 
	public function get_menu_label();
	

	/**
	 * If extra information is needed from the user, this fucntion should return its 
	 * definition. Returning any empty value means that no extra information is required.
	 * 
	 * TODO define the format for this.
	 */
	public function get_info_request();
	
	
	/**
	 * Return true if this is a postprocessor plugin; else return false.
	 * 
	 * @return bool
	 */
	public static function is_postprocessor();
	
	/**
	 * Return true if this is a postprocessor plugin; else return false.
	 * 
	 * @return bool
	 */
	public static function is_query_analyser();
	
	/**
	 * Return true if this is a query analyser which produces a download. 
	 * Otherwise, return false.
	 */
	public static function is_query_analyser_with_download();
	
	/**
	 * Return true if this is a query analyser which produces a display page. 
	 * Otherwise, return false.
	 */
	public static function is_query_analyser_with_display();
}


/**
 * Interface for Postprocess Plugins.
 * 
 * A Postprocess Plugin is an object capable of transmuting a CQP query in some way.
 * These postprosses are "custom" versions of the built-in query postprocessing
 * tools - distribution, collocation, thin, sort and so on.
 * 
 * It does not need to actually interface with CQP in any way - all it needs to do
 * is operate on the integer indexes that the query result consists of.
 *
 * Postprocess helper functions are provided to help access CQP so that the actual
 * content of concordances can be retrieved. Of course the plugin can access CQPweb's
 * internal functions at liberty, if you so choose; it's your funeral!
 */
interface Postprocessor extends GenericQueryActionInterface
{
	/**
	 * Runs the defined custom postprocess on the query.
	 *
	 * The parameter is an array of arrays. Each inner array contains the four
	 * numbers that result from "dumping" a CQP query. See the CQP documentation
	 * for more info on this. Basically each match is represented by two, three or
	 * four numbers: match, matchend[, target[, keyword]]. The outer array will have 
	 * sequential integer indexes beginning at 0.
	 *
	 * The return value should be the same array, but edited as necessary - for example,
	 * entries removed, or expanded, or results added... as appropriate to the purpose
	 * of the custom postprocess.
	 * 
	 * The inner arrays can contain integers or integers-as-strings (both will be OK as
	 * a result of PHP's automatic type juggling). All inner arrays must be of the same
	 * length (i.e. you do not have to supply target and keyword if you don't want to, 
	 * but if you do, every match must have them). The indexes in the inner arrays 
	 * are not important, only the --order-- of the elements; but the outer array 
	 * will be re-sorted, so order in that does not matter.
	 * 
	 * @param  array $query_array    Query dump data (array of arrays)
	 * @return array                 Modified query dump data.
	 */
	public function postprocess_query($query_array);
	
	/**
	 * Returns a string to be used in header descriptions of the sequence postprocesses in the CQPweb visual interface.
	 * 
	 * This is best phrased as a past participial clause. For example, "manipulated by the 
	 * Jones system", or "reduced in an arbitrary way", or something else. It should be 
	 * compatible with the description returned by ->get_label(), as both are shown to the
	 * user. 
	 * 
	 * Note that this function will often be called on a SEPARATE instance of the object to the 
	 * one that does the actual postprocessing. So, you cannot include particulars about 
	 * a specific run of the postprocessor. It can only be a generic description.  
	 * 
	 * TODO, could it not be given access to the postprocess string?
	 * 
	 * If the empty string '' is returned, then this postprocess will not be mentioned in
	 * the header interface -- but the reduced number of hits will still be shown (it has
	 * to be, to make sense. So on balance, it's better to say something!
	 * 
	 * The optional argument (bool) specifies whether the returned value will be printed out
	 * in an HTML context (true, the default) or a plain text context. The class may, but
	 * does not have to, return something different depending on the argument.
	 */
	public function get_postprocess_description($html = true);
	
}



interface QueryAnalyser extends GenericQueryActionInterface
{
	//TODO      plugin to display an analysis screen for a query OR produce a text download of the analysis.
	
	
}



/**
 * Interface for CeqlExtender plugin. 
 * 
 * This object provides information about an alternative parser.
 */
interface CeqlExtender extends CQPwebPlugin
{
	/**
	 * Determine whether the standard setup (passing in of attribute handles, etc.)
	 * should be a pplied to this plugin's CeqlParser.
	 * 
	 * @return bool   True if standard setup should be applied.
	 */
	public function apply_standard_setup();
	
	/**
	 * Returns a new instance of a CeqlParser (that is, a class that inherits from CeqlParser).
	 * 
	 * @return CeqlParser
	 */
	public function get_parser();
}




/*
 * ======================================
 * END INTERFACES, START ABSTRACT CLASSES
 * ======================================
 */




/**
 * A class that Plugins can inherit from if they wish!
 *
 */
abstract class CQPwebPluginBase implements CQPwebPlugin
{
	protected $status_is_ok = true;
	protected $last_error_message = '';
	
	public function __construct($extra_config = [])
	{
		foreach($extra_config as $k=>$v)
			$this->$k = $v;
	}
	
	/**
	 * {@inheritDoc}
	 * @see CQPwebPlugin::status_ok()
	 */
	public function status_ok()
	{
		return (bool)$this->status_is_ok;
	}
	
	/** 
	 * {@inheritDoc}
	 * 
	 * The CQPwebPluginBase implements a default method, which just
	 * falls back to the same as description()! 
	 * 
	 * Children can override this method, but don't have to. 
	 * But children MUST implement the short description method!
	 *  
	 * @see CQPwebPlugin::long_description()
	 * @see CQPwebPlugin::description()
	 */
	public function long_description($html = true)
	{
		return $this->description();
	}
	
	
	/**
	 * {@inheritDoc}
	 * @see CQPwebPlugin::error_desc()
	 */
	public function error_desc($html = true)
	{
		return $this->last_error_message;
	}

	/**
	 * Raises an error by setting the error message and status variables.
	 * 
	 * @param  string $msg    Message describing what the error was.
	 * @return bool           Always false (caller can return this value after raising an error).
	 */
	protected function raise_error($msg)
	{
		$this->status_is_ok = false;
		$this->last_error_message = $msg;
		return false;
	}
}

/**
 * The Annotator base includes methods for management of text IDs
 * so that these can be managed easily, either generated, or supplied,
 * or worked out from filenames.
 */
abstract class AnnotatorBase extends CQPwebPluginBase implements Annotator
{
	const ID_METHOD_GIVEN    = 0;
	const ID_METHOD_FILENAME = 1;
	const ID_METHOD_NUMBER   = 2;
	const ID_METHOD_SUPPLIED = 3; // Todo this isn't used below - what did I mean here? 

	protected $bytes_in_output = 0;
		
	
	protected $text_id_method  = self::ID_METHOD_FILENAME;
	protected $text_id_counter = 0;
	protected $text_id_list    = [];
	/* to prevent giving a file the same name as one we've seen. */
	protected $text_ids_used   = [];
	
	

	/** Check whether a text_id is OK to used, based on having been used before */
	protected function test_text_id($id)
	{
		if (!in_array($id, $this->text_ids_used))
		{
			$this->text_ids_used[] = $id;
			return $id;
		}
		else
		{
			$this->raise_error('ID ``'.$id.'`` would duplicate the ID of an already-seen text.');
			return NULL;
		}
	}
	
	
	/**
	 * Create a text ID based on the path of a source file.
	 * 
	 * @param  string $fn      A file path.
	 * @return string          Either the new text id as a string, or NULL if it would be a duplicate. 
	 */
	protected function deduce_id_from_filename($fn)
	{
		$fn = preg_replace('/\.\w+$/', '', $fn); /* since the suffix could be anything, use regex */
		$fn = basename($fn);                     /* thus, no suffix specified in call to basename */
		$fn = preg_replace('/\W/', '_', $fn);
//squawk($fn);

		return $this->test_text_id($fn);
	}
	

	/**
	 * Create a text ID based on the path of a source file.
	 * 
	 * @param  string $fn      A file path.
	 * @return string          Either the new text id as a string, or NULL if it would be a duplicate. 
	 */
	protected function get_next_numeric_id()
	{
		$s = sprintf("t%04x", $this->text_id_counter++);
		return $this->test_text_id($s);
	}
	
	/**
	 * Create a text ID based on the path of a source file.
	 * 
	 * @param  string $fn      A file path.
	 * @return string          Either the new text id as a string, or NULL if it would be a duplicate. 
	 */
	protected function get_next_given_id()
	{
		$t = current($this->text_id_list);
		if (!empty($t))
		{
			next($this->text_id_list);
			return $this->test_text_id($t);
		}
	}
	
	/**
	 * Get the text id to be used for the output of the next input file.
	 * 
	 * @param  string $fn      The input file's path.
	 * @return string          Either the new text id as a string, or NULL if it would be a duplicate. 
	 */
	protected function get_next_text_id($path_to_its_file = NULL)
	{
		switch ($this->text_id_method)
		{
		case self::ID_METHOD_GIVEN:
			return $this->get_next_given_id();
		case self::ID_METHOD_FILENAME:
			return $this->deduce_id_from_filename($path_to_its_file);
		case self::ID_METHOD_NUMBER:
			return $this->get_next_numeric_id();
		default:
			return NULL;
		}
	}
	
	
	public function get_id_method()
	{
		return $this->text_id_method;
	}
	
	public function set_id_method($value)
	{
		/* we accept strings of the constants, jut in case... */ 
		switch ($value)
		{
		case 'ID_METHOD_GIVEN':
		case self::ID_METHOD_GIVEN:
		case 'ID_METHOD_FILENAME':
		case self::ID_METHOD_FILENAME:
		case 'ID_METHOD_NUMBER':
		case self::ID_METHOD_NUMBER:
			$this->text_id_method = $value;
			break;
		default:
			break;
		}
	}
	

	public function output_size()
	{
		return $this->bytes_in_output;
	}
	
	
	/* these methods are here to allow inheritors not to bother implementing. */
	
	public function output_annotation_list() {return false;}
	public function output_xml()             {return false;}
	
	
	/**
	 * Support function to check that target paths really are writable.
	 * If there is an invalid path anywhere on the list, creates an error 
	 * state in the object. 
	 *  
	 * Not necessitated by the interface, but handy.
	 * 
	 * @param  array    $paths   Array of paths to check.
	 * @return bool              True iff all paths are writable.
	 */
	public function validate_write_paths($paths)
	{
		if (empty($paths))
			return $this->raise_error('Annotator says: zero files specfied for write.');

		foreach ($paths as $p)
			if (!is_writeable($p))
				return $this->raise_error('Annotator says: Cannot write to  ``'.$p.'`` .');
		return true;
	}
	
	
	/**
	 * Support function to check that target paths really are writable.
	 * If there is an invalid path anywhere on the list, creates an error 
	 * state in the object. 
	 * 
	 * Not necessitated by the interface, but handy.
	 * 
	 * @param  array    $paths   Array of paths to check.
	 * @return bool              True iff all paths are valid input files.
	 */
	public function validate_read_paths($paths)
	{
		if (empty($paths))
			return $this->raise_error('Annotator says: zero files specified for read.');

		foreach ($paths as $p)
			if (!is_readable($p))
				return $this->raise_error('Annotator says: File ``'.$p.'`` does not exist or is not readable.');
		return true;
	}
	
}

/**
 *  This base class implements a generic mechanism for setting the max amount of data to index,
 *  and for trimming it down to a set size.
 *  
 *  And likewise a generic internal list of input files, and method for adding.
 *  
 *  And a generic do_cleanup.
 *  
 *  Building on this allows corpus installer plugins to be pretty minimal.
 */
abstract class CorpusInstallerBase extends CQPwebPluginBase implements CorpusInstaller
{
	protected $input_files = [];
	protected $max_input_tokens = 0;
	protected $restrict_mode_on = false;
	
	protected $corpus_name;
	
	protected $charset;
	
	/* used by default system, children should not access */
	private   $chopfile;
	private   $sql_for_corpus = [];
	private   $sql_for_abort  = [];
	private   $p_array        = [];
	private   $s_array        = [];
	
	private   $bindings       = [];
	

	/** used by default system, child classes can access if they want. 
	 * Set to true to make the check happen. */
	protected $xml_datatype_check_needed = false;

	
	
	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::set_max_input_tokens()
	 */
	public function set_max_input_tokens($max)
	{
		$this->max_input_tokens = (int)$max;
		$this->restrict_mode_on = (0 < $this->max_input_tokens);
	}

	
	/**
	 * This is not part of the interface, but is a utility function:
	 * it sets the token restriction according to a privilege.
	 * 
	 * The privilege should be of type PRIVILEGE_TYPE_INSTALL_CORPUS.
	 * 
	 * @param object $priv   Database object for the privilege.
	 */
	public function set_restriction_from_privilege($priv)
	{
		
		if (PRIVILEGE_TYPE_INSTALL_CORPUS != $priv->type)
			return;
		
		$this->set_max_input_tokens($priv->scope_object->max_tokens);
	}
	
	
	/**
	 * {@inheritDoc}
	 * 
	 * This default implementation reflects the fact that nearly 
	 * all CorpusInstallers do, in fact, require input files.  
	 * 
	 * @see CorpusInstaller::needs_input_files()
	 */
	public function needs_input_files()
	{
		return true;
	}
	
	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::add_input_file()
	 */
	public function add_input_file($path)
	{
		if (is_string($path))
			$this->input_files[] = $path;
		if (is_array($path))
			$this->input_files = array_merge($this->input_files, $path);
	}
	
	/**
	 * This function is designed to be called by do_setup of children.
	 * It goes over the input files, and modifies those that need to
	 * be modified so that the resulting corpus is not too big. 
	 * 
	 * 
	 * @param bool $unlink_excess    If true, files that cannot be included in the corpus will be deleted. 
	 */
	protected function restrict_input_data($unlink_excess = false)
	{
		if (!$this->restrict_mode_on)
			return;
		
		$revised = [];
		
		$total = 0;
		
		$chop_last = false;
		
		foreach($this->input_files as $if)
		{
			/* open the file, cycle thru line by line */
			$sz = 0;
			$src = fopen($if, 'r');
			while (false !== ($l = fgets($src)))
				if ('<' != $l[0])
					$sz++;
			fclose($src);

			/* this is the last file that we can include, and needs to be chopped. */
			if ($total+$sz > $this->max_input_tokens)
			{
				$chop_last = true;
				break;
			}
			/* we can include the next file ... and continue. */
			$total += $sz;
			$revised[] = $if;
		}

		/* if necessary, create a new file which chops the last-file-processed. */
		if ($chop_last)
		{
			$this->chopfile = pluginhelper_get_temp_file_path();
			
			/* created chopped-down version of the file */
			$src = fopen($if, 'r');
			$dst = fopen($this->chopfile, 'w');
			while (false !== ($l = fgets($src)))
			{
				if ('<' != $l[0])
					$total++;
				if ($total >= $this->max_input_tokens)
				{
					fputs($dst, "</text>\n");
					break;
				}
				else
					fputs($dst, $l);
			}
			fclose($src);
			fclose($dst);
			
			if ($unlink_excess)
				unlink($if);
			
			$revised[] = $this->chopfile;
		}
		
		if ($unlink_excess)
			array_walk($this->input_files, function ($f) use ($revised){if (!in_array($f, $revised)) unlink($f);} );
		
		$this->input_files = $revised;
	}
	
	/**
	 * A default metadata setup: minimalist text-metadata table only.
	 * It's expected that most CorpusInstaller plugins will just use this. 
	 */
	public function do_metadata()
	{
		create_text_metadata_minimalist($this->corpus_name);
	}
	
	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::do_cleanup()
	 */
	public function do_cleanup($delete_input_files = false)
	{
		if (!empty($this->chopfile))
			if(file_exists($this->chopfile))
				unlink($this->chopfile);

if ($delete_input_files){squawk("delete_inputs is trooooooooo " );/*squawk( print_r(debug_backtrace(), true)); */}
		if ($delete_input_files)
			foreach ($this->input_files as $if)
				if (is_file($if))
					unlink($if);
	}
	
	
	/* default system for bindings */
	
	/**
	 * This method provides default system for the communication
	 * of CEQL bindings. The possible things to bind are:
	 * 					'primary_annotation',
	 * 					'secondary_annotation',
	 * 					'tertiary_annotation',
	 * 					'tertiary_annotation_tablehandle',
	 * 					'combo_annotation'.
	 * 
	 * @param string $binding    What to bind. See list above. 
	 * @param string $value      What it should be bound to.
	 */
	protected function set_binding($which, $value)
	{
		static $possible = NULL;
		if (is_null($possible))
			$possible = array(
					'primary_annotation',
					'secondary_annotation',
					'tertiary_annotation',
					'tertiary_annotation_tablehandle',
					'combo_annotation'
			);
		if(!in_array($which, $possible))
			return;
		
		$this->bindings[$which] = $value;
	}
	
	/**
	 * 
	 * @see CorpusInstaller::get_annotation_bindings()
	 */
	public function get_annotation_bindings()
	{
		return $this->bindings;
	}
	

	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::get_p_attribute_info()
	 */
	public function get_p_attribute_info() 
	{
		return $this->p_array;
	}
	
	
	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::get_s_attribute_info()
	 */
	public function get_s_attribute_info() 
	{
		return $this->s_array;
	}
	

	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::get_infile_info()
	 */
	public function get_infile_info()
	{
		return array_unique($this->input_files);
	}

	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::get_charset()
	 */
	public function get_charset()
	{
		return empty($this->charset) ? 'utf8' : $this->charset; 
	}
	

	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::get_xml_datatype_check_needed()
	 */
	public function get_xml_datatype_check_needed()
	{
		return $this->xml_datatype_check_needed;
	}
	
	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::get_sql_statements()
	 */
	public function get_sql_for_corpus()
	{
		return $this->sql_for_corpus;
	}
	
	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::get_sql_for_abort()
	 */
	public function get_sql_for_abort()
	{
		return array_unique($this->sql_for_abort);
	}
	
	/**
	 * {@inheritDoc}
	 * @see CorpusInstaller::set_corpus_name()
	 */
	public function set_corpus_name($name)
	{
		$this->corpus_name = $name;
	}

	/**
	 * checks whether the corpus name has been set.
	 * Utility funciton - do_setup() in children can use this to make sure 
	 * that they have got this critical paramter.
	 * @return bool
	 */
	protected function check_for_corpus_name()
	{
		if (empty($this->corpus_name))
			return $this->raise_error("Cannot run do_setup() when the name of the corpus has not been set!");
		return true;
	}
	
	

	
	/* dummy to make this function optional in inheritors. */
	public function declare_maptable() {}
	
	
	/**
	 * Add an annotation to the corpus setup. This enables inheritors to add p-attribute specs without 
	 * having to deal with the p-attribute formats for CWB and SQL.
	 * 
	 * @param string $handle
	 * @param string $description
	 * @param boolean $is_feature_set
	 */
	protected function declare_annotation($handle, $description, $is_feature_set = false)
	{
		$handle = cqpweb_handle_enforce($handle, HANDLE_MAX_ANNOTATION);
		$this->p_array[] = $handle . ($is_feature_set ? '/' : '');
		
		$this->sql_for_corpus[] = sql_for_p_att_insert($this->corpus_name, $handle, $description, '', '', $is_feature_set);
		$this->sql_for_abort[]  = "delete from `annotation_metadata` where `corpus`='{$this->corpus_name}'";
		
		/* note, this is v basic, so we assume no tagset. */
	}
	
	/**
	 * 
	 * @param int $template_id
	 */
	protected function declare_annotations_from_template($template_id)
	{
		$this->sql_for_abort[]  = "delete from `annotation_metadata` where `corpus`='{$this->corpus_name}'";
		return set_install_data_from_annotation_template(
				$template_id, $this->corpus_name, 
				$this->bindings['primary_annotation'], $this->p_array, $this->sql_for_corpus
				);
	}
	
	
	/**
	 * Add an XML element to the corpus setup. This enables inheritors to add s-attribute specs without 
	 * having to deal with the s-attribute formats for CWB and SQL.
	 * 
	 * @param string $xml_element         The element's tag. 
	 * @param array  $xml_attributes      An associative array, mapping attribute handles (e.g. id) to datatypes constants.
	 *                                    Or, a flat array of just attribute handles. Depends on next paramater.
	 *                                    e.g. ['id'=>METADATA_TYPE_UNIQUE_ID, 'name'=>METADATA_TYPE_FREETEXT]
	 *                                    or   ['id', 'name', 'classification']
	 * @param bool   $datatypes_supplied  If true, arg 2 is a hash. If false, it's just a list of handles.
	 */
	protected function declare_xml($xml_element, $xml_attributes, $datatypes_supplied)
	{
		$xml_element = cqpweb_handle_enforce($xml_element);
		$cwb_in = $xml_element;
		$description = "XML element ``$xml_element''";
		$this->sql_for_corpus[] = sql_for_s_att_insert($this->corpus_name, $xml_element, $xml_element, $description, METADATA_TYPE_NONE);

		if (!$datatypes_supplied)
			$xml_attributes = array_combine($xml_attributes, array_fill(0, count($xml_attributes), METADATA_TYPE_FREETEXT));
		
		foreach ($xml_attributes as $handle => $dt)
		{
			$handle = cqpweb_handle_enforce($handle);
			$cwb_in .= "+$handle";
			$description = "Attribute ``$handle'' of XML element ``$xml_element";
			$this->sql_for_corpus[] = sql_for_s_att_insert($this->corpus_name, "{$xml_element}_{$handle}", $xml_element, $description, $dt);
		}
		
		$this->s_array[] = $cwb_in;
		$this->sql_for_abort[] = "delete from `xml_metadata` where `corpus`='{$this->corpus_name}'";
		$this->sql_for_abort[] = "delete from `xml_metadata_values` where `corpus`='{$this->corpus_name}'";
	}

	
	/**
	 * 
	 * @param int $template_id
	 */
	protected function declare_xml_from_template($template_id)
	{
		$this->sql_for_abort[] = "delete from `xml_metadata` where `corpus`='{$this->corpus_name}'";
		$this->sql_for_abort[] = "delete from `xml_metadata_values` where `corpus`='{$this->corpus_name}'";
		return set_install_data_from_xml_template(
				$template_id, $this->corpus_name, 
				$this->s_array, $this->sql_for_corpus
				);
	}
	
	
	/**
	 * This is a simple default system which passes through values from the annotator to 
	 * this object (assuming that the annotator uses the defined format for these things). 
	 * 
	 * In order for it to work, the Annotator plugin object needs to be passed in.
	 * @param Annotator $tagger  The tagger to read from.  
	 */
	protected function declare_content_from_annotator(Annotator $tagger)
	{
		if (!is_object($tagger))
			return;
		
		$annot = $tagger->output_annotation_list();
		
		if (empty($annot))
			for ($i = 1, $n = $tagger->output_n_annotations() ; $i <= $n ; $i++)
				$this->declare_annotation("tag$i", "Annotation # $i");
		else
			foreach($annot as $handle)
				if ('word' != $handle)
					$this->declare_annotation($handle, "Annotation '$handle'");
			
		$seen_text = false;
		foreach($tagger->output_xml() as $x_info)
		{
			$dts_present = is_string(array_first_key($x_info['attributes']));
			if ('text' == $x_info['element'])
			{
				$seen_text = true;
				if($dts_present)
				{
					if (!array_key_exists('id', $x_info['attributes']))
						$x_info['attributes']['id'] = METADATA_TYPE_UNIQUE_ID;
				}
				else
				{
					if (!in_array('id', $x_info['attributes']))
						$x_info['attributes'][] = 'id';
				}
			}
			$this->declare_xml($x_info['element'], $x_info['attributes']);
		}
		if (!$seen_text)
			$this->declare_xml('text', ['id'=>METADATA_TYPE_UNIQUE_ID]);
	}
	
}



abstract class FormatCheckerBase extends CQPwebPluginBase implements FormatChecker
{
	/** internal var in support of basic error-tracking function. */
	protected $error_line_number = NULL;
	/** internal var in support of basic error-tracking function. */
	protected $error_line_byte = NULL;

	
	/**
	 * {@inheritDoc}
	 * @see FormatChecker::error_line_number()
	 */
	public function error_line_number()
	{
		return $this->error_line_number;
	}
	
		
	/**
	 * {@inheritDoc}
	 * @see FormatChecker::error_line_byte()
	 */
	public function error_line_byte()
	{
		return $this->error_line_byte;
	}
}



abstract class ScriptSwitcherBase extends CQPwebPluginBase implements ScriptSwitcher
{
	/** shared locus to stash output */
	protected $out;
	
	/** shared locus for integer array derived from input */
	protected $uc;
	
	/* override constructor to do NOTHING with $extra_config. */
	public function __construct($extra_config = []) { }
	
	/** standard translit function relying on whatever the internal data structures are. */
	public function transliterate($string)
	{
		$this->make_integers_from_instring($string);
		
		$this->make_outstring_from_integers();
		
		return $this->out;
	}
	
	protected function make_integers_from_instring($instring)
	{
		$this->uc = array_map(
						function($x){return hexdec(bin2hex($x));}, 
						str_split(mb_convert_encoding($instring, 'UTF-32BE', 'UTF-8'), 4)
					);
	}
	
	/**
	 * Using this default function requires you to provide a mapchar() method. 
	 * If you don't use it, just leave a stub.
	 */
	protected function make_outstring_from_integers()
	{
		$mapped = [];
		
		$ix = 0;
		
		$n = count($this->uc);
		
		$skip = 0;
		
		for ($ix = 0; $ix < $n; $ix += $skip)
			$mapped += $this->mapchar($this->uc[$ix], ($n == $ix+1 ? 0 : $this->uc[$ix+1]), $skip);
			
		/* render as UTF-8 */
		$this->out = implode('', array_map('Intl::chr', $mapped));
	}
	
	
	
	/**
	 * Generic implementation of mapchar; it checks for an offset, then looks for unconditional, conditional, and weird mappings.
	 * 
	 * @param  int $curr    Codepoint of the current character.
	 * @param  int $next    Codepoint of the next character. NULL if there isn't one.
	 * @param  int $skip    Out parameter: how many moves forward should the input index make before re-looping? 
	 * @return array        Array of integer codepoints making up the replacement.
	 */
	protected function mapchar($curr, $next, &$skip)
	{
		/* by default, skip 1 codepoint; */
		$skip = 1;
		
		/* do we need to offset the character? */
		$curr += $this->get_offset($curr);
		if (!is_null($next))
			$next += $this->get_offset($next);
		
		/* do we know of an unconditional mapping? */
		if (isset($this->map_uncond[$curr]))
			return $this->map_uncond[$curr];
		
		/* is there a simple conditional mapping? */
		if (!is_null($next) && isset($this->map_cond[$curr]))
			foreach([$next, 'def'] as $test)
				if (isset($this->map_cond[$curr][$test]))
				{
					if (isset($this->map_cond[$curr][$test]['mrg']))
					{
						$skip += ($this->map_cond[$curr][$test]['mrg'] ? 1 : 0);
						return array_slice($this->map_cond[$curr][$test], 0, (isset( $this->map_cond[$curr][$test]['mrg']) ? NULL : -1));
					}
					else 
						return $this->map_cond[$curr][$test];
				}

		/* is there an unusual mapping? */
		if (method_exists($this, 'map_unusual'))
			if ( false !== ($result = $this->map_unusual($curr, $next, $skip)) )
				return $result;
		
		/* all else has failed: leave the character as it is */
		return [$curr];
	}
}


abstract class CorpusAnalyserBase extends CQPwebPluginBase implements CorpusAnalyser
{
	/* this currently has nothing in it but it may be extended with "helper" methods for its descendents to use */
}


abstract class PostprocessorBase extends CQPwebPluginBase implements Postprocessor
{
	/* 
	 * MINIMALIST SYSTEM FOR IDENTIFYING AS POSTPROCESSOR
	 */
	public static function is_postprocessor() { return true; }
	public static function is_query_analyser() { return false; }
	public static function is_query_analyser_with_download() { return false; }
	public static function is_query_analyser_with_display() { return false; }
	
}


abstract class QueryAnalyserBase extends CQPwebPluginBase implements QueryAnalyser
{
	/* 
	 * MINIMALIST SYSTEM FOR IDENTIFYING AS QUERYANALYSER
	 */
	
	/** This should be set to either "display" or "download" (using constants provided) */
	protected $type_of_query_analyser_output;
	const OUTPUT_DISPLAY = 'display';
	const OUTPUT_DOWNLOAD = 'download';
	
	public static function is_postprocessor() { return false; }
	public static function is_query_analyser() { return true; }
	public static function is_query_analyser_with_download() 
	{ 
		return self::OUTPUT_DOWNLOAD == $this->type_of_query_analyser_output; 
	}
	public static function is_query_analyser_with_display() 
	{ 
		return self::OUTPUT_DISPLAY == $this->type_of_query_analyser_output; 
	}
	
	
	
}


abstract class CeqlExtenderBase extends CQPwebPluginBase implements CeqlExtender
{
	
	/* this currently has nothing in it but it may be extended with "helper" methods for its descendents to use */
}






/*
 * ============================================
 * END ABSTRACT CLASSES BEGIN LIBRARY FUNCTIONS
 * ============================================
 */






/**
 * An autoload function for plugin classes.
 * 
 * All plugin classes must be files of the form ClassName.php,
 * within the lib/plugins subdirectory.
 * 
 * Note that in CQPweb, plugins are the ONLY classes that can be
 * autoloaded.
 * 
 * The $plugin parameter is, of course, the classname.
 */ 
function autoload_plugin_class($plugin)
{
	$types_of_plugin = array(
			'Annotator',
			'CorpusInstaller',
	);
	$types_of_plugin_INCOMPLETE = array(
			'CeqlExtender',
			'CorpusAnalyser',
			'FormatChecker',
			'Postprocessor',
			'QueryAnalyser',
			'ScriptSwitcher',
	);
	
	
	/* if the file exists, load it. If not, fall over and die. */
	$file = "../lib/plugins/$plugin.php";
	if (is_file($file))
		require_once($file);
		/* note that apparently, from the PHP manual, this puts it into global scope. */
	else
		exiterror('Attempting to load a plugin file that could not be found! Check plugin directory.');
	
	if (!class_exists($plugin))
		exiterror('Plugin autoload failure, CQPweb aborts.');
	
	$interfaces = class_implements($plugin);
	
	if (!isset($interfaces['CQPwebPlugin']))
		exiterror("The class declared by $file does not implement the CQPwebPlugin interface!");
	
	unset($interfaces['CQPwebPlugin']);
	
	
		
	
	foreach ($interfaces as $int)
		if (in_array($int, $types_of_plugin))
			return;
		else if (in_array($int, $types_of_plugin_INCOMPLETE))
			exiterror("Plugin '$plugin' implements an interface that is not yet complete in CQPweb!");

	exiterror("Bad plugin $plugin': does not implement any of existing plugin interfaces!");
	
	
	// TODO on ScriptSwitcher plugins: 
	// modify Visualisations management to allow transliteration to be engaged
	// then modify concordance and context to run it on the strings that
	// come from CQP if the ncessary user options (d admin setup choices) are TRUE.
	// maybe make this per-user configurable?
	
	// TODO on format checker plugins:
	// modify the upload area to give each file a chec format button

	
	/* all done -- assuming we haven't died, the plugin is ready to construct. */
}






function register_plugin($class, $description, $extra = [])
{
	/* because we allow autoload for classes, this also checks that the file exists. */
	if (! class_exists($class, true))
		return;
	
	$description = escape_sql($description);
	
	$extra_enc = escape_sql(json_encode($extra));
	
	$type = plugin_type_from_interface(plugin_class_interface($class));
	if (false === $type)
		return;
	
	do_sql_query("insert into plugin_registry (class,type,description,extra) values ('$class',$type,'$description', '$extra_enc')");
}


function unregister_plugin($id)
{
	do_sql_query("delete from plugin_registry where id = " . ((int)$id));
}

/**
 * Gets the plugin info for a given plugin-registry info. The "extra" hash is decoded. 
 * 
 * @param  int    $id  ID of a plugin in the register. 
 * @return object      Database object; or, false if not found.
 */
function get_plugin_info($id)
{
	$result = do_sql_query("select * from plugin_registry where id = " . ((int)$id));
	if (0 == mysqli_num_rows($result))
		return false;
	$o = mysqli_fetch_object($result);
	$o->extra = (array)json_decode($o->extra);
	return $o;
}

function get_all_plugins_info($type = PLUGIN_TYPE_ANY)
{
	$list = [];

// 	will this work right in SQL?
// 	$result = do_sql_query("select * from plugin_registry where 0 != (type & $type)");
	$result = do_sql_query("select * from plugin_registry");
	
	while ($o = mysqli_fetch_object($result))
	{
		if ($type & $o->type)
		{
			$o->extra = (array)json_decode($o->extra);
			$list[$o->id] = $o;
		}
	}
	
	return $list;
}

function plugin_interface_from_type($type_id)
{
	static $mapper = NULL;
	
	if (is_null($mapper))
		$mapper = array(
			PLUGIN_TYPE_ANNOTATOR      => 'Annotator',
			PLUGIN_TYPE_SCRIPTSWITCHER => 'ScriptSwitcher',
			PLUGIN_TYPE_FORMATCHECKER  => 'FormatChecker',
			PLUGIN_TYPE_POSTPROCESSOR  => 'Postprocessor',
			PLUGIN_TYPE_CORPUSINSTALLER=> 'CorpusInstaller',
			PLUGIN_TYPE_QUERYANALYSER  => 'QueryAnalyser',
			PLUGIN_TYPE_CORPUSANALYSER => 'CorpusAnalyser',
			PLUGIN_TYPE_CEQLEXTENDER   => 'CeqlExtender',
		);
	
	if (isset($mapper[$type_id]))
		return $mapper[$type_id];
	else
		return false;
}

function plugin_type_from_interface($interface_string)
{
	static $mapper = NULL;
	
	if (is_null($mapper))
		$mapper = array(
			'Annotator'       => PLUGIN_TYPE_ANNOTATOR,
			'ScriptSwitcher'  => PLUGIN_TYPE_SCRIPTSWITCHER,
			'FormatChecker'   => PLUGIN_TYPE_FORMATCHECKER,
			'Postprocessor'   => PLUGIN_TYPE_POSTPROCESSOR,
			'CorpusInstaller' => PLUGIN_TYPE_CORPUSINSTALLER,
			'QueryAnalyser'   => PLUGIN_TYPE_QUERYANALYSER,
			'CorpusAnalyser'  => PLUGIN_TYPE_CORPUSANALYSER,
			'CeqlExtender'    => PLUGIN_TYPE_CEQLEXTENDER,
		);
	if (isset($mapper[$interface_string]))
		return $mapper[$interface_string];
	else
		return false;
}


function plugin_class_interface($class)
{
	$ifs = class_implements($class);
	unset($ifs['CQPwebPlugin']);
	
	if (1 != count($ifs))
		exiterror('Plugin class implements too many interfaces');
	
	return array_shift($ifs);
}



/*
 * ============================================
 * END LIBRARY FUNCTIONS BEGIN HELPER FUNCTIONS
 * ============================================
 */

/**
 * Returns a path that can be used as a temporary filename by a plugin. 
 * The plugin is responsible for making sure it gets deleted. 
 * (It will be pre-created as a temporary file.)
 */
function pluginhelper_get_temp_file_path()
{
	global $Config;
	$file = tempnam($Config->dir->cache, 'plgTmp_');
	chmod($file, 0664);
	return $file;
}

/**
 * Returns a path that can be used as a temporary directory by a plugin. 
 * (It will be created as a 0777 directory.)
 * Temporary directories are deleted at shutdown, 
 * unless false is passed as parameter.
 */
function pluginhelper_get_temp_dir_path($delete_at_shutdown = true)
{
	global $Config;
			
	$dir = tempnam($Config->dir->cache, 'plgTmD_');
	
	if (is_file($dir))
		unlink($dir);

	mkdir($dir);
	
	/* this ensures deletion even if plugin-writer doesn't. */
	if ($delete_at_shutdown) 
		register_shutdown_function(function ($d) {if (is_dir($d)) recursive_delete_directory($d);}, $dir);
	
	return $dir;
}


/**
 * Support function for the next: returns true if the file is one that can be decompressed.
 * 
 * Note that this is very paranoid in how it checks things.
 * 
 * @see pluginhelper_get_temp_decompressed_folder()
 */
function pluginhelper_file_is_decompressible_archive($path_to_zip)
{
	if (!is_file($path_to_zip) || !is_readable($path_to_zip))
		return false;
	
	/* check we have the right equipment ... */
	switch (archive_file_get_type($path_to_zip))
	{
	case ARCHIVE_TYPE_TAR_GZ:
		if (!extension_loaded('zlib'))
			return false;
		if (!class_exists('PharData'))
			return false;
		break;
		
	case ARCHIVE_TYPE_TAR_BZ2:
		if (!extension_loaded('bz2'))
			return false;
		if (!class_exists('PharData'))
			return false;
		break;
		
	case ARCHIVE_TYPE_ZIP:
		if (!extension_loaded("zip")) 
			return false;
		break;
		
	default:
		return false;
	}
	
	return true;
}


/**
 * 
 * @param  string $path_to_zip
 * @param  string $error_message
 * @return string                 Path to temporary directory, or false for an error 
 *                                (in which case, the error message is availabel via the outparameter. 
 */
function pluginhelper_get_temp_decompressed_folder($path_to_zip, &$error_message)
{
	if (!is_file($path_to_zip))
	{
		$error_message = "File $path_to_zip does not exist";
		return false;
	}
	
	$dir = pluginhelper_get_temp_dir_path();
	
	/* this switch assumes that all the needed functions are available;
	 * argument should have been checked with pluginhelper_file_is_decompressible_archive(),
	 * which does the actual checking. */
	switch ($ext = archive_file_get_type($path_to_zip))
	{
	case ARCHIVE_TYPE_ZIP:
		
		$zip = new ZipArchive();
		
		if (true !== $zip->open($path_to_zip))
		{
			$error_message = "Could not open $path_to_zip";
			return false;
		}
		else
		{
			if (!$zip->extractTo($dir))
			{
				if ("Compression method not supported" == $zip->getStatusString())
					$error_message = "Could not extract files from ". basename($path_to_zip) . " because it uses an unsupported compression method. ";
				else
					$error_message = "Could not extract files from ". basename($path_to_zip) . "; reason unknown. ";
				return false;
			}
			$zip->close();
		}
		
		break;
		
	case ARCHIVE_TYPE_TAR_GZ:
	case ARCHIVE_TYPE_TAR_BZ2:
		
		/* I hate exceptions. But the convenience of both tar and gz/bz2 being available is too good. */
		try 
		{
			$phar = new PharData($path_to_zip);
		}
		catch (Exception $e) 
		{
			$error_message = "$path_to_zip: compressed file not openable!";
			return false;
		}
		/* why oh why is PharData the only way to do this? why is there no module as convenient as "zip"? */
		
		$target_ext = implode('.', array_slice(explode('.', $path_to_zip), 1, -1));
		if (3 == strlen($ext))
			$target_ext .= ".tar";
		/* note on the above: the tar extension will already be there if it was tar.gz/tar.bz2. */ 
		
		$path_to_tar = preg_replace('/^([^.]+\.)(.*?)$/', "$1$target_ext", $path_to_zip);
		
		try 
		{
			$phar->decompress($target_ext);
		}
		catch (Exception $e) 
		{
			$error_message = "$path_to_zip: error decompressing archive file! ($e->message)";
			return false;
		}
		
		unset($phar);

		try 
		{
			$phar = new PharData($path_to_tar);
		}
		catch (Exception $e) 
		{
			$error_message = "$path_to_tar: archive file not openable! ($e->message)!";
			return false;
		}
		
		try 
		{
			$phar->extractTo($dir); 
		}
		catch (Exception $e)
		{
			$error_message = "$path_to_tar: error extracting files! ($e->message)";
			return false;
		}
		
		unset($phar);

		unlink($path_to_tar);
		
		break;
		
	// longterm TODO maybe xz? rar ? Others?
		
	default: 
		/* incl case false */
		$error_message = "Decompression of file $path_to_zip requested, but it does not seem to be a compressed file!";
		return false;
	}
	
	return $dir;
}



