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
 * This file contains the objects for the PHP port of the original CEQL Parser.
 * 
 * The Perl original is part of the CWB perl installation (CWB::CEQL).
 * It has been ported to PHP to allow Simple Queries to be parsed without
 * the need to create an external Perl process for the job.
 * 
 * The code has been kept as close as possible to the Perl original.
 * Changes usually relate to differences in how object-oriented programming
 * work in Perl and PHP. (That is why, for instance, the "Frame" is an 
 * actual class here, and why all the classes are in a single file.)
 * 
 * Very few of the original comments are still here, and the functions are 
 * undocumented; the documentation of the Perl CEQL module still applies.
 * 
 * The main objects here are CeqlParser (cf CEQL.pm) and CeqlParserForCQPweb
 * (cf perl/cqpwebCEQL.pm, which will eventually be removed from the repository). 
 * 
 * This parser replaced the Perl parser in CQPweb version 3.2.32.
 */


/**
 * This class of objects represents the context within which some parsing rule is attempted.
 */
class CeqlParseFrame
{
	public $RULE;
	public $INPUT;
	public $APPLY_ITEMS;
	public $APPLY_DONE;
	
	public function __construct($rule, $input, $apply_items = NULL, $apply_done = NULL)
	{
		$this->RULE  = $rule;
		$this->INPUT = $input;
		
		if (!is_null($apply_items))
			$this->APPLY_ITEMS = $apply_items;
		
		if (!is_null($apply_done))
			$this->APPLY_DONE = $apply_done;
	}
	
	/* this method does not replicate anything in the Perl original, it's an assist for debugging. */
	public function __toString()
	{
		$s = "call::{$this->RULE}({$this->INPUT})";
		if (! is_null($this->APPLY_ITEMS))
			$s .= '   [' . implode('/', $this->APPLY_ITEMS) . ']==>[' . implode('/', $this->APPLY_DONE) . ']';
		return $s;
	}
}





/**
 * A somewhat advanced string object, capable of being typed and being assigned attribute/value pairs. 
 */
class CeqlSuperString
{
	private $VALUE;
	private $TYPE = NULL;
	private $ATTRIBUTE = [];
	
	public function __construct($value, $type = NULL) 
	{
		$this->VALUE = $value;
		$this->TYPE  = $type;
	}
	
	public function __toString()
	{
		return $this->VALUE;
	}
	
	public function value($new_val = NULL)
	{
		if (!is_null($new_val))
			$this->VALUE = $new_val;
		return $this->VALUE;
	}
	
	public function append($value)
	{
		$this->VALUE .= $value;
	}
	
	public function type($new_type = NULL)
	{
		if (!is_null($new_type))
			$this->TYPE = $new_type;
		return $this->TYPE;
	}
	
	public function attribute($name, $new_value = NULL)
	{
		if (!is_null($new_value))
			$this->ATTRIBUTE[$name] = $new_value;
		else if (!isset($this->ATTRIBUTE[$name]))
			throw new Exception("CeqlSuperString: user attribute '$name' has not been defined\n");
		return $this->ATTRIBUTE[$name];
	}
	
	public function copy()
	{
		return clone $this;
	}
	
	public function cmp($other, $reverse = false)
	{
		if (is_object($other))
			$other = $other->value();
		$result = strcmp($this->VALUE, $other);
		return ($reverse ? -$result : $result);
	}
}




/**
 * Generic parser class (which can be extended into an actual parser).
 * 
 * The Perl version of CEQL has a "Parser" module that the CEQL grammar extends;
 * here, the same thing is implemented as an abstract class from which the 
 * main CeqlParser object inherits. 
 */
abstract class CeqlParserBackend 
{
	protected $PARAM_DEFAULTS = [];
	protected $PARAM = NULL;
	protected $INPUT = NULL;
	protected $ERROR = NULL;
	protected $CALLSTACK = [];
	protected $GROUPS = NULL;
	protected $CURRENT_GROUP = NULL;
	protected $GROUPSTACK = NULL;

	
	public function Parse($input, $rule = false)
	{

		if (empty($rule))
			$rule = "default_parse_method";
		if (!is_null($this->INPUT))
		{
			/* simulate a exception outside of the try/catch block. */
			$this->ERROR = "Parse() method is not re-entrant\n(tried to parse '$input' while parsing '{$this->INPUT}').\n";
			return NULL;
		}

		$this->INPUT = $input;
		$this->PARAM = $this->PARAM_DEFAULTS;   # shallow copy of hash
		$this->CALLSTACK = [];                  # re-initialise call stack (should destroy information from last parse)

		$this->GROUPS = NULL;
		$this->CURRENT_GROUP = NULL;
		$this->GROUPSTACK = NULL;
		$this->ERROR = NULL;

		/* The Perl module uses "die" to produce exceptions in the parser, translated here as "throw";
		 * so we need to wrap the use of Call() in this method (the main entry point) in a try/catch. */ 
		try 
		{
			$result = $this->Call($rule, $input);
		}
		catch (Exception $e)
		{
			$result = NULL;
			$error  = trim($e->getMessage());
			if (empty($error))
				$error = "parse of '' {$this->INPUT} '' returned no result (reason unknown)";
			$this->ERROR = preg_replace("/\s*\n\s*/", " **::** ", $error);
		}
		
		$this->INPUT = NULL;               # no active parse
		$this->PARAM = NULL;               # restore global parameter values (PARAM_DEFAULTS)

		return $result;                    # NULL if parse failed
	}
	
	
	public function ErrorMessage()
	{
		if (empty($this->ERROR))
			return [];
		$lines = ["**Error:** {$this->ERROR}"]; 

		$previous_frame = new CeqlParseFrame("", ""); # init do dummy frame to avoid need for special case below
		
		foreach (array_reverse($this->CALLSTACK) as $frame) 
		{
			if ($frame->RULE == "APPLY")
			{
				$done     = implode(' ', $frame->APPLY_DONE);
				$remain   = implode(' ', $frame->APPLY_ITEMS);
				$lines[]  = " - at this location: '' $done ''**<==**'' $remain ''";
			}
			else 
			{
				if ($previous_frame->INPUT == $frame->INPUT && $previous_frame->RULE != "APPLY") 
					$lines[(count($lines)-1)] .= ", **{$frame->RULE}**";
				else 
					$lines[] = " - when parsing '' {$frame->INPUT} '' as **{$frame->RULE}**";
			}
			$previous_frame = $frame;
		}
		return $lines;
	}



	public function HtmlErrorMessage()
	{
		if (! empty($lines = $this->ErrorMessage()))
			return $this->formatHtmlText($lines);
		else
			return NULL;
	}
	
	
	public function SetParam($name, $value)
	{
		$param_set = (!is_null($this->INPUT) ? 'PARAM' : 'PARAM_DEFAULTS');
		if (!array_key_exists($name, $this->$param_set))
			throw new Exception("SetParam(): parameter '$name' does not exist\n");
		$this->{$param_set}[$name] = $value;
	}
	

	public function GetParam($name)
	{
		$param_set = (!is_null($this->INPUT) ? 'PARAM' : 'PARAM_DEFAULTS');
		if (!array_key_exists($name, $this->$param_set))
			throw new Exception("GetParam(): parameter '$name' does not exist\n");
		return $this->{$param_set}[$name];
	}
	
	
	public function NewParam($name, $default_value)
	{
		$param_set = (!is_null($this->INPUT) ? 'PARAM' : 'PARAM_DEFAULTS');
		if (array_key_exists($name, $this->$param_set))
			throw new Exception("NewParam(): parameter '$name' already exists, cannot create with NewParam()\n");
		$this->{$param_set}[$name] = $default_value;
	}
	
	
	public function Call($rule, $input)
	{
		if (is_null($this->INPUT))
			throw new Exception("Sorry, we're not parsing yet\n");
		
		if (!method_exists($this, $rule))
			throw new Exception("the rule **$rule** does not exist in grammar **".spl_object_id($this)."** (internal error)\n");
		
		$frame = new CeqlParseFrame($rule, $input);
		
		$this->CALLSTACK[] = $frame;
		
		$result = $this->$rule($input);
		
		if (is_null($result))
			throw new Exception("rule **$rule** failed to return a result (internal error)\n");
	
		$return_frame = array_pop($this->CALLSTACK);
		
		if ($return_frame != $frame)
			throw new Exception("call stack has been corrupted (internal error)\n");
		
		return $result;
	}
	
	
	/** nb - renamed from "Try" because of PHP keyword */
	public function TryCall($rule, $input)
	{
		if (is_null($this->INPUT))
			throw new Exception("Sorry, we're not parsing yet\n");
		
		$back_param = $this->PARAM;
		$back_callstack = $this->CALLSTACK;
		
		$back_groups = NULL;
		$back_current_group = NULL; 
		$back_groupstack = NULL;
		
		if (!empty($this->GROUPS) )
		{
			$back_groups = $this->GROUPS;
			if (count($back_groups) > 0)
				$back_current_group = $back_groups[0];
		}
		if (!empty($this->GROUPSTACK))
			$back_groupstack = $this->GROUPSTACK;

		$result = $this->Call($rule, $input);

		## if parsing failed, restore internal data structures from backup copies
		if (is_null($result)) 
		{
			$this->PARAM = $back_param;
			$this->CALLSTACK = $back_callstack;
			$this->GROUPS = $back_groups;
			if (!is_null($back_groups) && !is_null($back_current_group)) 
				$this->GROUPS[0] = $back_current_group;
			$this->GROUPSTACK = $back_groupstack;
		}
		return $result;
	}
	
	
	
	
	public function Apply($rule, $items)
	{
		$frame = new CeqlParseFrame('APPLY', NULL, $items, []);
		$this->CALLSTACK[] = $frame;
		
		$apply_saved_GROUPS     = $this->GROUPS;
		$apply_saved_GROUPSTACK = $this->GROUPSTACK;
		$this->GROUPS           = [ [] ];
		$this->GROUPSTACK       = [];

		while(0 < count($frame->APPLY_ITEMS))
		{
			$input = array_shift($frame->APPLY_ITEMS);
			$frame->APPLY_DONE[] = $input;
			$result = $this->Call($rule, $input);
			if ('' != $result)
				$this->GROUPS[0][] = $result;
		}

		if (0 < count($this->GROUPSTACK))
		{
			$next_type = array_pop($this->GROUPSTACK);
			$type_msg = ($next_type == "*" ? "" : "of type ''$next_type''");
			throw new Exception("bracketing is not balanced: too many opening delimiters $type_msg\n");
		}

		if (1 != count($this->GROUPS))
			throw new Exception("data structure for result values is corrupt in Apply() call (internal error)\n");
		
		$results = $this->GROUPS[0];

		$return_frame = array_pop($this->CALLSTACK);
		if ($frame != $return_frame)
			throw new Exception("call stack has been corrupted (internal error)\n");

		$this->GROUPS     = $apply_saved_GROUPS ;
		$this->GROUPSTACK = $apply_saved_GROUPSTACK ;
		
		return $results;
	}
	
	
	
	public function BeginGroup($name = NULL)
	{
		if (is_null($name))
			$name = "*";
		
		if (is_null($this->GROUPSTACK))
			throw new Exception("BeginGroup() called outside Apply() operation (internal error)\n");
		$this->GROUPSTACK[] = $name;
		array_unshift($this->GROUPS, []);
	}
	
	
	public function EndGroup($name = NULL) 
	{
		if (is_null($name))
			$name = "*";
		
		if (is_null($this->GROUPSTACK))
			throw new Exception("EndGroup() called outside Apply() operation (internal error)\n");
		
		$type_msg = ($name == "*" ? "" : "of type ''$name''");
		
		if (1 > count($this->GROUPSTACK) )
			throw new Exception("bracketing is not balanced: too many closing delimiters $type_msg\n");
		
		if (count($this->GROUPS) != (count($this->GROUPSTACK) + 1) )
			throw new Exception("data structure for result values is corrupt in Apply() call (internal error)\n");
		
		$active_group = array_pop($this->GROUPSTACK);
		
		if ($name != $active_group)
			throw new Exception("opening delimiter of type ''$active_group'' paired with closing delimiter of type ''$name''\n");
		
		return array_shift($this->GROUPS);
	}
	
	
	public function NestingLevel()
	{
		if (is_null($this->GROUPSTACK))
			throw new Exception("NestingLevel() called outside Apply() operation (internal error)");
		return count($this->GROUPSTACK);
	}
	
	
	
	
	/* the func "currentGroup" would be here, except that in the Perl version it returns a pointer,
	 * and PHP references don't work that way. therefore, the GROUPS array of result lists must be
	 * accessed directly. The "current" group is at index 0 of that array.  */
	
	final protected function currentGroup()
	{
		throw new Exception("Parsers that extend the CeqlParserBackend MUST NOT use the currentGroup() method!\n");
	}
	
	
	
	
	protected function formatHtmlText($lines_of_text)
	{
		if (!is_array($lines_of_text))
			$lines_of_text = [$lines_of_text];
		
		$html_lines = [];
		$in_list = false;
		
		for ($i = 0, $n = count($lines_of_text) ; $i < $n ; $i++ )
		{
			$line = $lines_of_text[$i];

			$line = $this->encodeEntities($line);
			$line = preg_replace_callback("{(\*\*|//|'')(.*?)(\1)}", 
						function ($m) 
						{
							switch($m[1])
							{
							case '**': return "<strong>{$m[2]}</strong>";
							case '//': return "<em>{$m[2]}</em>";
							default  : return "<code>{$m[2]}</code>";
							}
						}, $line);
			
			if (preg_match('^ -\s+', $line))
			{
				if (!$in_list)
					$html_lines[] = "<ul>";
				$html_lines[] = "<li>$line</li>";
				$in_list = true;
			}
			else 
			{
				if ($in_list)
					$html_lines[] = "</ul>";
				$html_lines[] = "<p>$line</p>";
				$in_list = false;
			}
		}
		if ($in_list)
			$html_lines[] = "</ul>";

		return implode("\n", $html_lines);
	}
	
	
	protected function encodeEntities($s)
	{
		$s = htmlspecialchars($s);
		$s = preg_replace('/[ \t]+/', ' ', $s);
		$s = preg_replace('/[\x00-\x09\x0b\x0c\x0e-\x1f]+/', '', $s);
		
		if ('UTF-8' == mb_detect_encoding($s, NULL, true))
			$s = preg_replace_callback('', function ($m) {return sprintf("&#x%X;", ord($m[1]));}, $s);
		
		return $s;
	}
	
	
	
	
	

	
	
	/* 
	 * ============================
	 * Parser state debug functions
	 * ============================
	 * 
	 * These functions were not part of the original Perl version,
	 * and may be removed in future. 
	 */
	
	/* this could be called after an exception is thrown (alternative to Perl confess()) */
	public function ShowCallStack()
	{
		$s = "Call stack content: ";
		foreach ($this->CALLSTACK as $k => $f)
			$s .= "\n\tLevel $k:\t$f";
		return $s . "\n";
	}
	
	/* this could be called after an exception is thrown (alternative to Perl confess()) */
	public function ShowGroupStack()
	{
		$s = "Group stack content: ";
		foreach ($this->GROUPSTACK as $k => $v)
			$s .= "\n\tLevel $k:\t" . preg_replace('/\s+/', ' ', print_r($v, true));
		return $s . "\n";
	}
	
	/* this could be called after an exception is thrown (alternative to Perl confess()) */
	public function ShowGroups()
	{
		$s = "Groups stored: ";
		foreach ($this->GROUPSTACK as $k => $v)
			$s .= "\n\t[$k]:\t" . preg_replace('/\s+/', ' ', print_r($v, true));
		return $s . "\n";
	}
	
}



/**
 * A Simple Query (CEQL) parser. 
 */
class CeqlParser extends CeqlParserBackend
{
	protected $_wildcard_table;
	
	public function __construct()
	{
		$this->NewParam("pos_attribute", "pos");
		$this->NewParam("lemma_attribute", "lemma");
		$this->NewParam("simple_pos", NULL);
		$this->NewParam("simple_pos_attribute", NULL);
		$this->NewParam("s_attributes", [ "s" => 1 ] );
		$this->NewParam("default_ignore_case", true);
		$this->NewParam("default_ignore_diac", false);
		
		$this->_wildcard_table = array(
				"?" => ".",
				"*" => ".*",
				"+" => ".+",
				"\\a" => "\\pL",
				"\\A" => "\\pL+",
				"\\l" => "\\p{Ll}",
				"\\L" => "\\p{Ll}+",
				"\\u" => "\\p{Lu}",
				"\\U" => "\\p{Lu}+",
				"\\d" => "\\pN",
				"\\D" => "\\pN+",
				"\\w" => "[\\pL\\pN'-]",
				"\\W" => "[\\pL\\pN'-]+",
			);
	}
	
	
	/**
	 * Main entry point (since parent::Parse() calls it)
	 * 
	 * nb - renamed from "Try" because of PHP keyword.
	 */
	public function default_parse_method($input)
	{
		return (string)$this->ceql_query($input);
	}
	
	
	public function ceql_query($input)
	{
		$input = trim(preg_replace('/\s+/', ' ', $input));
		
		if (preg_match('/(?<!\\\\)((<<|>>)[^<>\\\\ ]*(<<|>>))/', $input))
			return $this->Call("proximity_query", $input);
		else
			return $this->Call("phrase_query", $input);
	}
	
	
	protected function phrase_query($input)
	{
		$modifier = '';
		$input = preg_replace_callback(
					'/^\s*\(\?\s*(\w+)\s*\)\s*/', 
					function ($m) use (&$modifier) {$modifier = $m[1]; return ''; },
					$input, 1
					);
		switch ($modifier)
		{
		case 'longest':
		case 'shortest':
		case 'standard':
		case 'traditional':
			$modifier = "(?$modifier) ";
			break;
		case '':
			break;
		default:
			throw new Exception("invalid modifier (?$modifier) -- specify (?longest), (?shortest) or (?standard)\n");
		}
		
		$input = preg_replace('{(?<!\\\\)(</?[A-Za-z0-9_-]+>)}', ' $1 ', $input);
		$input = preg_replace('{(?<!\\\\)([(|])}', ' $1 ', $input);
		$input = preg_replace('{(?<!\\\\)([)][*+?{},0-9]*)}', ' $1 ', $input);
		
		$input = preg_replace('/\s+/', ' ', $input);
		$items = explode(' ', trim($input));

		$cqp_code = $this->Apply("phrase_element", $items);

		return $modifier . implode(" ", $cqp_code);
	}

	
	protected function phrase_element($item)
	{
		if ($item == '(')
		{
			$this->BeginGroup("(...)");
			return '';
		}
		else if ($item == '|')
		{
			if ( $this->NestingLevel() < 1 )
				throw new Exception("alternatives separator (''|'') may only be used within parentheses ''( .. )''\n");
			return "|";
		}
		else if ($item[0] == ')')
		{
			$parts = $this->EndGroup("(...)");
			if (empty($parts))
				throw new Exception("groups ''( ... )'' must not be empty\n");
			
			list($has_empty_alternative) = $this->_remove_empty_alternatives($parts);
			if ($has_empty_alternative)
				throw new Exception("empty alternatives not allowed in phrase query\n");
			
			if ($item == ')')
				return '(' . implode(" ", $parts) . ')';
			else if (preg_match('/^\)([?*+]|[{][0-9]+(,[0-9]*)?[}])$/', $item, $m))
				return '(' . implode(" ", $parts) . ')' . $m[1];
			else
			{
				$item = preg_replace('/^\)/', '', $item);
				throw new Exception("invalid quantifier '' $item '' on closing parenthesis\n");
			}
		}
		
		else if (preg_match('/^<.*>$/', $item))
			return $this->Call("xml_tag", $item);
		
		else if (preg_match('/^[*+]+$/', $item))
		{
			if ("*" == $item)
				return "[]?";
			if ("+" == $item)
				return "[]";
			$n_plus = substr_count($item, '+');
			$n_astx = substr_count($item, '*');
			return '[]{' . $n_plus . ',' . ($n_plus + $n_astx) . '}';
		}
		
		else if ('@' == $item[0])
			return '@' . $this->Call("token_expression", substr($item, 1));
			
		else
			return $this->Call("token_expression", $item);
	}
		


	protected function xml_tag($tag)
	{
		if (preg_match('/^<\/([^\/<>=]+)>$/', $tag, $m))
		{
			$name = $m[1];
			$closing = "/";
			$value = NULL;
		}
		else if (preg_match('/^<([^\/<>=]+)(=([^\/<>]+))?>$/', $tag, $m))
		{
			$name = $m[1];
			$closing = "";
			$value = ( isset($m[3]) ? $m[3] : NULL );	
		}
		else
			throw new Exception("syntax error in XML tag '' $tag ''\n");

		$is_valid_tag = $this->GetParam("s_attributes");
		if (!empty($is_valid_tag))
		{
			if (! isset($is_valid_tag[$name]))
			{
				$valid_tags = array_keys($is_valid_tag);
				sort($valid_tags, SORT_STRING | SORT_FLAG_CASE);
				throw new Exception("invalid XML tag '' $tag '' (allowed tags: ''<".implode('> <', $valid_tags).">'')\n");
			}
		}
		else 
			throw new Exception("XML tags are not allowed in this corpus\n");
		
		if (!is_null($value))
			return "<$name = " . $this->Call("wildcard_pattern", $value) . ">";
		else 
			return "<$closing$name>";
	}
	
	
	
	protected function proximity_query($input)
	{
		$input = preg_replace("/(?<!\\\\)([()])/", "\t$1\t", $input);
		$input = preg_replace("/(?<!\\\\)((<<|>>)[^<>\\\\ ]*(<<|>>))/", "\t$1\t", $input);
		$input = trim($input);
		$items = preg_split("/\s*\t\s*/", $input);

		$new_items = [];
		array_map(
				/* perl map can split an element into multiple elemenmts.
				 * php array_map can't -- so pass in a reference to a receiver-array instead. */
				function ($v) use (&$new_items)
				{
					if (preg_match('/\s/', $v))
					{
						$shorthand = explode(' ', $v);
						$new_items[] = '(';
						$new_items[] = $shorthand[0];
						for ($i = 1, $n = count($shorthand) ; $i < $n ; $i++)
						{
							$new_items[] = ">>$i,$i>>"; 
							$new_items[] = $shorthand[$i];
						}
						$new_items[] = ")";
					}
					else
						$new_items[] = $v;
					return NULL;
				}
				, $items);
		$items = $new_items;
		
		$query = $this->Apply("proximity_expression", $items);
		
		if (2 == count($query) && $query[1]->type() == "Op")
			throw new Exception("incomplete proximity query: expected another term after distance operator\n");
		
		if ( ! (1 == count($query) && $query[0]->type() == "Term"))
			throw new Exception("shift-reduce parsing with **proximity_expression** failed to return a single term\n");
		
		return "MU{$query[0]}";
	}
	
	
	protected function proximity_expression($item)
	{
		/* the perl module here uses a ref returned by currentGroup - but PHP refs don't work like that. So we use GROUPS directly. */
		$n_results = count($this->GROUPS[0]); # current position in result list
		$new_term = NULL;

		if ($item == "(") 
		{
			if ($n_results != 0 && $n_results != 2)
				throw new Exception("cannot start subexpression at this point, expected distance operator\n");
			$this->BeginGroup("(...)"); # named group makes error messages more meaningful
			return "";
		}
		else if ($item == ")") 
		{
			$subexp = $this->EndGroup("(...)");
			if (empty($subexp))
				throw new Exception("empty subexpression not allowed in proximity query\n");
			if (2 == count($subexp) && $subexp[1]->type() == "Op")
				throw new Exception("incomplete subexpression in proximity query: expected another term after distance operator\n");
			if ( ! (1 == count($subexp) && $subexp[0]->type() == "Term"))
				throw new Exception("shift-reduce parsing of subexpression in **proximity_expression** failed to return a single term\n");
			
			$new_term = $subexp[0];
			/* the perl module here uses a ref returned by currentGroup - but PHP refs don't work like that. So we use GROUPS directly. */
			$n_results = count($this->GROUPS[0]);
		}
		else if (preg_match('/^(<<|>>).*(<<|>>)$/', $item))
		{
			if (1 != $n_results)
				throw new Exception("distance operator not allowed at this point, expected token expression or parenthesis\n");
			$new_term = $this->Call("distance_expression", $item);
		}
		else if (preg_match('/^[*+]+$/', $item))  
		{
			throw new Exception("optional/skipped tokens ''$item'' not allowed in proximity query\n");
		}
		else 
		{
			if ($n_results != 0 && $n_results != 2)
				throw new Exception("token expression not allowed at this point, expected distance operator\n");
			$token_exp = $this->Call("token_expression", $item);
			$new_term = new CeqlSuperString($token_exp, "Term");
		}
		
		if ($n_results > 2)
			throw new Exception("invalid state of result list with $n_results + 1 elements (internal error)\n");
		
		if ($n_results == 2) 
		{
			$term    = array_shift($this->GROUPS[0]); 
			$op      = array_shift($this->GROUPS[0]);
			$types   = array_map (function ($x){ return $x->type();}, [$term, $op, $new_term]);
			$types_s = implode(' ', $types);
			if ($types_s != "Term Op Term")
				throw new Exception("invalid state ''$types_s'' of result list (internal error\n)");
			return new CeqlSuperString("(meet $term $new_term $op)", "Term");
		}
		else 
			return $new_term;
	}
	
	
	protected function distance_expression($op)
	{
		if (! preg_match('/^(<<|>>)(.+)(<<|>>)$/', $op, $m))
			throw new Exception("syntax error in distance operator '' $op ''\n");

		$type = $m[1] . $m[3];
		$distance = $m[2];
		
		if ($type == '>><<')
			throw new Exception("invalid distance type ''>>..<<'' in distance operator '' $op ''\n");
		
		if (preg_match('/^(?:([1-9][0-9]*),)?([1-9][0-9]*)$/', $distance, $m))
		{
			# numeric distance
			$min = $m[1];
			$max = $m[2];
			if ($min && ! ($max >= $min))
				throw new Exception("maximum distance must be greater than or equal to minimum distance in '' $op ''\n");
			
			if ($min && $type == "<<>>")
				throw new Exception("distance range ''$distance'' not allowed for two-sided distance '' $op ''\n");
			
			if ($min < 1)
				$min = 1;
			
			if ($type == "<<>>")       
				return new CeqlSuperString( "-$max $max" , "Op"); 
			else if ($type == "<<<<")  
				return new CeqlSuperString( "-$max -$min", "Op"); 
			else if ($type == ">>>>")  
				return new CeqlSuperString( "$min $max"  , "Op"); 
			else                       
				throw new Exception("This can't happen."); 
		}
		else 
		{
			# structural distance
			$is_valid_region = $this->GetParam("s_attributes");
			if (empty($is_valid_region))
				$is_valid_region = [];
			if (isset($is_valid_region[$distance]))
			{
				if (!$type == "<<>>")
					throw new Exception("structural distance must be two-sided (''<<..>>'')\n");
				return new CeqlSuperString($distance, "Op");
			}
			else 
			{
				$valid_ops = array_map(function ($x){return "<<$x>>";}, array_keys($is_valid_region));
				sort($valid_ops, SORT_STRING|SORT_FLAG_CASE);
				throw new Exception("'' $op '' is neither a numeric distance nor a valid structural distance (supported structures: ''".implode(' ', $valid_ops)."'')\n");
			}
		}
	}
	
	

	
	protected function token_expression($input)
	{
		$parts = preg_split('/(?<!\\\\)_/', $input); # split input on unescaped underscores
	
		if (2 < count($parts))
			throw new Exception("only a single ''_'' separator allowed between word form and POS constraint (use ''\\_'' to match literal underscore)\n");

		$word = (isset($parts[0]) ? $parts[0] : ''); 
		$pos  = (isset($parts[1]) ? $parts[1] : ''); 
	
		$cqp_word = $cqp_pos = NULL;
	
		if ( $word != "" && ! ( ($word == '+'||$word == '*') && $pos != "" ) ) 
			$cqp_word = $this->Call("word_or_lemma_constraint", $word);
	
		if ($pos != "") 
			$cqp_pos = $this->Call("pos_constraint", $pos);
		
		if (!is_null($cqp_word) && !is_null($cqp_pos)) 
			return "[$cqp_word & $cqp_pos]";
		else if (!is_null( $cqp_word)) 
			return "[$cqp_word]";
		else if (!is_null($cqp_pos))
			return "[$cqp_pos]";
		else 
			throw new Exception("neither word form nor part-of-speech constraint in token expression '' $input ''\n");
	}
	

	protected function word_or_lemma_constraint($input) 
	{
		$ignore_case = (bool)$this->GetParam("default_ignore_case");
		$ignore_diac = (bool)$this->GetParam("default_ignore_diac");
		
		if (preg_match('/(?<!\\\\):([A-Za-z]+)$/', $input, $m))
		{
			$input = preg_replace('/(?<!\\\\):([A-Za-z]+)$/', '', $input);
			foreach (str_split($m[1]) as $flag)
			{
				if ('c' == $flag)
					$ignore_case = true;
				else if ('C' == $flag)
					$ignore_case = false;
				else if ('d' == $flag)
					$ignore_diac = true;
				else if ('D' == $flag)
					$ignore_diac = false;
				else
					throw new Exception("invalid flag ''$flag'' in modifier '':{$m[1]}''\n");
			}
		}
		
		$cqp_code = $this->Call("word_or_lemma", $input);
	
		if ($ignore_case || $ignore_diac) 
			$cqp_code .= '%';
		if ($ignore_case)
			$cqp_code .= "c"; 
		if ($ignore_diac)
			$cqp_code .= "d";
		
		return $cqp_code;
	}

	
	protected function word_or_lemma($input)
	{
		switch (1)
		{
		case preg_match('/^\{(.+)\}$/', $input, $m):
			return $this->Call("lemma_pattern", $m[1]);
				
		case preg_match('/^\{/', $input):
		case preg_match('/(?<!\\\\)\}$/', $input):
			throw new Exception("lonely curly brace (''{'' or ''}'') at start/end of word form pattern -- did you intend to search by lemma as in ''{be}''?\n");
			
		case preg_match('/(?<!\\\\)%$/', $input):
			$input = preg_replace('/(?<!\\\\)%$/', '', $input);
			return $this->Call("lemma_pattern", $input);
			
		default:
			return $this->Call("wordform_pattern", $input);
		}
	}
	
	
	protected function wordform_pattern($wf)
	{
		$regexp = $this->Call("wildcard_pattern", $wf);
		return "word=$regexp";
	}
	
		
	protected function lemma_pattern($lemma)
	{
		$attr = $this->GetParam("lemma_attribute");
		if (empty($attr))
			throw new Exception("lemmatisation is not available for this corpus\n");
		$regexp = $this->Call("wildcard_pattern", $lemma);
		return "$attr=$regexp";
	}
	
		
		
	protected function pos_constraint($input)
	{
		switch (1)
		{
		case preg_match('/^\{(.+)\}$/', $input, $m):
			return $this->Call("simple_pos", $m[1]);
				
		case preg_match('/^\{/', $input):
		case preg_match('/(?<!\\\\)\}$/', $input):
			throw new Exception("lonely curly brace (''{'' or ''}'') at start/end of part-of-speech constraint -- did you intend to use a simple POS tag such as ''_{N}''?\n");
			
		case preg_match('/(?<!\\\\)%$/', $input):
			$input = preg_replace('/(?<!\\\\)%$/', '', $input);
			return $this->Call("simple_pos", $input);
			
		default:
			return $this->Call("pos_tag", $input);
		}
	}		
		
	protected function pos_tag($tag)
	{
		$attr = $this->GetParam("pos_attribute");
		if (empty($attr))
			throw new Exception("no attribute defined for part-of-speech tags (internal error)\n");
		$regexp = $this->Call("wildcard_pattern", $tag);
		return "$attr=$regexp";
	}
	
	
	protected function simple_pos($tag)
	{
		$attr = $this->GetParam("simple_pos_attribute");
		if (empty($attr))
		{
			$attr = $this->GetParam("pos_attribute");
			if (empty($attr))
				throw new Exception("no attribute defined for part-of-speech tags (internal error)\n");
		}

		$lookup = $this->GetParam("simple_pos");
		if (empty($lookup))
			throw new Exception("no simple part-of-speech tags are available for this corpus\n");

		if (empty($lookup[$tag])) 
		{
			$valid_tags = array_keys($lookup);
			sort($valid_tags, SORT_STRING|SORT_FLAG_CASE);
			throw new Exception("'' $tag '' is not a valid simple part-of-speech tag (available tags: '' ".implode(' ', $valid_tags)."'')\n");
		}
		else
			$regexp = $lookup[$tag];
		
		return "$attr=\"$regexp\"";
	}
			
	
	
	protected function wildcard_pattern($input)
	{
		$orig_input = $input;
		if (false !== strstr($input, '\\\\'))
			throw new Exception("literal backslash ''\\\\'' is not allowed in wildcard pattern '' $input '')\n");
		if ('\\' == substr($input, -1))
			throw new Exception("wildcard pattern must not end in a backslash ('' $input '')\n");

		$input = preg_replace('/(?<!\\\\)([?*+\[,\]])/', ' $1 ', $input);
		$input = preg_replace('/(\\\\[aAlLuUdDwW])/'   , ' $1 ', $input);
		$input = trim($input);
		
		if (empty($input))
			throw new Exception("empty wildcard pattern '' $orig_input '' is not allowed\n");
		$items = explode(' ', $input);
		
		$regexp_comps = $this->Apply("wildcard_item", $items);

		return '"'.implode('', $regexp_comps).'"'; 
	}
			
	
	
	protected function wildcard_item($item)
	{
		if (isset($this->_wildcard_table[$item]))
			return $this->_wildcard_table[$item];
		else if ('[' == $item)
		{
			$this->BeginGroup("[...]"); # group names make error messages more meaningful
			return '';
		}
		else if (',' == $item)
		{
			if (1 > $this->NestingLevel())
				throw new Exception("alternatives separator ('','') may only be used within brackets ''[ .. ]''\n");
			return	'|';
		}
		else if (']' == $item)
		{
			$parts = $this->EndGroup("[...]");
			list($has_empty_alternative, $filtered_parts) = $this->_remove_empty_alternatives($parts);
			if (empty($filtered_parts))
				throw new Exception("empty list of alternatives not allowed in wildcard pattern\n");
			$group = "(".implode('', $filtered_parts).")";
			return($has_empty_alternative ? "$group?" : $group);
		}
		else
			return $this->Call("literal_string", $item);
	}
			
	
	
	protected function literal_string($input)
	{
		$input = str_replace('\\', '', $input); 
		$input = preg_replace('/([.?*+|(){}\[\]\^\$])/', '\\\\$1', $input); 
		$input = str_replace('"', '""', $input);
		return $input;
	}
	
	
	
	
	protected function _remove_empty_alternatives($tokens)
	{
		$after_separator = true;
		$has_empty_alternative = false;
		$filtered_tokens = array();
		
		for ($i = 0, $n = count($tokens); $i < $n ; $i++)
		{
			$keep = true;
			
			if("|" == $tokens[$i])
			{
				if ($after_separator || $i + 1 == $n)
				{
					$has_empty_alternative = true;
					$keep = false;
				}
				$after_separator = true;
				
			}
			else 
				$after_separator = false;
			
			if ($keep)
				$filtered_tokens[] = $tokens[$i];
		}
		
		return [$has_empty_alternative, $filtered_tokens];
	}


}






/**
 * CQPweb extension of the CEQL grammar.
 * 
 * These extensions were originally implemented within BNCweb, and 
 * adapted versions of the same functionality are included in CQPweb.
 * 
 * ================
 * A Brief Synopsis
 * ================
 * 
 * $CEQL = new CQPwebCeqlParser();
 * 
 * $CEQL->SetParam("default_ignore_case", false); # case-sensitive query mode
 *   
 * You must tell CEQL what the CWB attribute-names of your annotations are for the 
 * relevant queries to work. If any of the following are left undef, those bits of
 * CEQL syntax will cause an error.
 * 
 * $CEQL->SetParam("pos_attribute", "PRIMARY_ANNOTATION");            # _xxx
 * $CEQL->SetParam("lemma_attribute", "SECONDARY_ANNOTATION");        # {xxx}
 * $CEQL->SetParam("simple_pos_attribute", "TERTIARY_ANNOTATION");    # _{xxx}
 * 
 * to use a tertiary annotation you also require a mapping table ...
 * 
 * $CEQL->SetParam("simple_pos", HASH_TABLE_OF_ALIASES_TO_REGEX);
 * $CEQL->SetParam("combo_attribute", "COMBO_ANNOTATION");            # {xxx/yyy}
 *   
 * You can also set a list of XML elements allowed within queries:
 * (The hash should map s-attribute handles to something truish (1, true, etc.))
 * 
 * $CEQL->SetParam("s_attributes", HASH_TABLE_OF_S_ATTRIBUTES);
 * 
 * A $ceql_query must be in utf-8.
 * 
 * $cqp_query = $CEQL->Parse($ceql_query);   # returns CQP query; NULL for error.
 * 
 * Example usage:
 * 
 * if (empty($cqp_query) 
 * {
 *     $html_msg = $CEQL->HtmlErrorMessage();     # ready-made HTML error message
 *     echo "<html><body>$html_msg</body></html>\n";
 *     exit(0);
 * }
 * 
 * End synopsis.
 */
class CeqlParserForCQPweb extends CeqlParser
{
	/**
	 * The constructor sets up a blank parser, with everything empty array/NULL.
	 * Everything therefore MUST be set by the calling function. Or it won't work.
	 * Exception: ignore_case is set off, and ignore_diac is set off.
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->NewParam("combo_attribute", NULL);

		$this->SetParam("pos_attribute", NULL);
		$this->SetParam("lemma_attribute", NULL);
		$this->SetParam("simple_pos", NULL);
		$this->SetParam("simple_pos_attribute", NULL);
		$this->SetParam("s_attributes", [ ] );
		$this->SetParam("default_ignore_case", false);
		$this->SetParam("default_ignore_diac", false);
	}

	
	/**
	 * This function overrides the main CEQL lemma_pattern rule to provide support for {book/V} notation,
	 * which EITHER addresses the combo annotation, OR the 2nd'ary and 3rd'iary (before/after the /). 
	 */
	protected function lemma_pattern($lemma)
	{
		/* split lemma into headword pattern and optional simple POS constraint */

		$splitlemma = preg_split('/(?<!\\\\)\//', $lemma);
		if (isset($splitlemma[2]))
			throw new Exception("Only a single ''/'' separator is allowed between the first and second search terms in a {.../...} search.\n");
		
		if ('' === ($hw = $splitlemma[0]))
			throw new Exception("Missing first search term (nothing before the / in {.../...} )"
					. (empty($splitlemma[1]) ? ".\n" : "; did you mean ''_\{{$splitlemma[1]}\}''?\n")
					);
	
		/* translate wildcard pattern for headword */
		$regexp = $this->Call("wildcard_pattern", $hw);
	
		/* now, look up simple POS if specified, and then combine with $regexp */
		if (isset($splitlemma[1]))
		{
			$tag = $splitlemma[1];
		
			/* before looking up the simple POS, we must check that the mapping table is defined */
			$simple_pos = $this->GetParam("simple_pos");
			if (!is_array($simple_pos))
				throw new Exception("Searches of the form _{...} and {.../...} are not available.\n");
		
			if (!isset($simple_pos[$tag]))
			{
				$valid_tags = array_keys($simple_pos);
				sort($valid_tags, SORT_STRING|SORT_FLAG_CASE);
				throw new Exception("'' $tag '' is not a valid tag in this position (available tags: '' ".implode(' ', $valid_tags)."'')\n");
			}
			$tag_regexp = $simple_pos[$tag];
		
			$attr = $this->GetParam("combo_attribute");
			
			/* if combo exists, use it. If not, use secondary + tertiary. */
			if (!is_null($attr)) 
			{
				/* remove double quotes around regexp so it can be combined with POS constraint */
				$regexp = preg_replace('/^"/', '', $regexp);
				$regexp = preg_replace('/"$/', '', $regexp);
				return "$attr=\"($regexp)_${tag_regexp}\"";
			}
			else 
			{
				$first_attr = $this->GetParam("lemma_attribute");
				if (is_null($first_attr))
					throw new Exception("Searches of the form {.../...} are not available.\n");
				
				$second_attr = $this->GetParam("simple_pos_attribute");
				if (is_null($second_attr))
					throw new Exception("Searches of the form {.../...} are not available.\n");

				return "$second_attr=\"$tag_regexp\" & $first_attr=$regexp";
				/*
				 * Note here: by putting the tag condition first, we exploit a loophole in the CEQL internals.
				 * 
				 * The caller (word_or_lemma()) will add '%c' to the end of the return value of this function.
				 * For the fallback (non-combo), that '%c' needs to apply to the secondary
				 * but not the tertiary (for consistency with how they are treated elsewhere).
				 * So, the Secondary constraint MUST be at the end, and we can't parenthesise these constraints.
				 * 
				 * We know CEQL will not add further constraints, except perhaps a redundant
				 * primary annotation constraint with &, so the naked & here will still work OK.
				 */
			}
		}
		else 
			return parent::lemma_pattern($lemma);
		/* no simple POS specified => match the normal lemma attribute using the parent function we overrode... */
	}
}





