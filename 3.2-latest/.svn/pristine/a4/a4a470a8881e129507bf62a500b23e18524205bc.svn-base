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
 * This file contains IntervalStream objects which hide the complexity
 * of the many ways we can access sequences of cpos pairs 
 * - that is, ordered sets of corpus intervals representing a 
 * query result or subcorpus - behind a single interface which
 * replicates, as close as possible, classic C file streams.
 * 
 * All are designed to use as little extra RAM as possible.
 * 
 * Out of the 7 different types of cpos interval data currently defined,
 * SIX are presently implemented.
 */



interface IntervalStream 
{
	/** open an IntervalStream  by wrapping a cpos-collection array. */
	const OPEN_CPOS_COLLECTION = 1;
	
// const OPEN_CQP_BINARY_FILE  

	/** open an IntervalStream  by creating a result to an SQL SELECT containing b and e values. */
	const OPEN_SQL_SELECT      = 3;

	/** open an IntervalStream  by running a tabulation command with [match,matched] as the first two columns. */
	const OPEN_TAB_CQP_READ    = 4;

	/** open an IntervalStream  by opening a tabulation file. */
	const OPEN_TAB_FILE        = 5;
	
	/** open an IntervalStream  by reading tabulation data from a supplied pipe. */
	const OPEN_TAB_PIPE        = 6;
	
	/** open an IntervalStream  by opening an xml attribute stream. */
	const OPEN_XML_ATTRIBUTE   = 7;
	
	/** const for the error return value of functions that return
	 *  an integer cpos. */
	const OUT_OF_BOUNDS        = -1;
	
	/**
	 * Get begin point of current interval. 
	 * @return int  cpos for interval begin
	 *              (or -1 if stream has ended)
	 */
	public function b() : int;
	
	/**
	 * Get end point of current interval. 
	 * @return int  cpos for interval end
	 *              (or -1 if stream has ended)
	 */
	public function e() : int;
	
	/** 
	 * Get begin point of the *next* interval (to enable peekahead). 
	 * @return int  cpos for next-interval begin 
	 *              (or -1 if current interval is the last)
	 */
	public function next_b() : int;
	
	/** 
	 * Get end point of the *next* interval. 
	 * @return int  cpos for next-interval end 
	 *              (or -1 if current interval is the last)
	 */
	public function next_e() : int;
	
	/**
	 * Returns the current interval (same that would be accessed by b() and e())
	 * and then moves the pointer onwards so that all access functions now point
	 * to the next interval. Returns NULL if we are at the end of the stream.
	 * @return array|NULL
	 */
// 	public function get() : ?array; //needs >= 7.1
	public function get();// : array;
	
	/**
	 * Move the stream pointer one step backwards. If an argument is supplied,
	 * it's pushed back into the stream if the stream type affords that;
	 * otherwise it's ignored.
	 */
// 	public function unget(array $interval = []) : void;
	public function unget(array $interval = []);
	
	/**
	 * Move the stream pointer to an arbitrary point. 
	 * 
	 * @param  int $offset  Number of steps forward to move, measured in n of cpos intervals. 
	 * @param  int $whence  SEEK_SET: *offset* intervals from the start.
	 *                      SEEK_CUR: *offset* intervals from the current location.
	 *                      SEEK_END: *offset* intervasl from the end, 
	 *                      such that seek(-1, SEEK_END); get();
	 *                      will return the final interval; implementing negative offsets 
	 * @return bool         True for success, false for failure
	 *                      (normally meaning that the requested position was out-of-bounds, 
	 *                      in which case, it will be clamped within [0,n]
	 */
	public function seek(int $offset, int $whence) : bool;
	
	/**
	 * Return current position of stream pointer as offset
	 * (number of intervals from the start). 
	 * If stream has ended, the offset returned will be equal
	 * to the size of the stream. 
	 * 
	 * @return int
	 */
	public function tell() : int;
	
	/**
	 * Reset stream to the beginning,.
	 */
// 	public function rewind() : void;
	public function rewind();
	
	/**
	 * Get total number of intervals.
	 * 
	 * @return int|NULL    NULL is returned if this info is not available 
	 *                     for the stream 
	 */
// 	public function size() : ?int; // needs 7.1
	public function size() : int;
	
	/**
	 * Close the stream. This will release any resources.
	 * Any subsequent calls to any getting functions
	 * cause undefined behaviour.
	 */
// 	public function close() : void;
	public function close();
	
	/**
	 * Move the stream along to the first interval with a beginning
	 * point equal to or greater than the given cpos.
	 * 
	 * @param  int $cpos
	 * @return bool      False if the stream reaches the end without getting to cpos.
	 *                   Otherwise true (incl. if the stream is already past cpos). 
	 *                   
	 */
	public function seek_to_cpos(int $cpos) : bool;
	
}





/**
 * Basic class for the different variants of the "reading from a file or pipe" ones. 
 */
abstract class IntervalStreamBase implements IntervalStream
{
// 	abstract protected function internal_open() : void ;
	abstract protected function internal_open() ;
// 	abstract protected function fetch_interval() : ?array ;	
	abstract protected function fetch_interval() /*: array */;	
	abstract protected function skip_interval() : bool ;
	
	/** a "helper" for fetch interval. Any string-tab-string input can be processed. */
// 	protected function fetch_interval_from_tabulated_stream(string $line) : ?array
	protected function fetch_interval_from_tabulated_stream(string $line) //: array
	{
		if (empty($line))
			return NULL;
		else
		{
			$a = explode("\t", $line);
			return [ (int)$a[0], (int)$a[1] ];
		}
	}
	
	protected $ix = 0;
	protected $curr = NULL;
	protected $next = NULL; 
	protected $next_next = NULL;
	
	public function __construct()
	{
		$this->internal_open();
		$this->curr = $this->fetch_interval();
	}
	
	public function __destruct()
	{
		$this->close();
	}
	
// 	protected function prepare_next() : void
	protected function prepare_next()
	{
		if (!$this->next)
		{
			if ($this->next_next)
			{
				$this->next = $this->next_next;
				$this->next_next = NULL;
			}
			else
				$this->next = $this->fetch_interval();
		}
	}
	
	public function b(): int
	{
		return $this->curr[0] ?? self::OUT_OF_BOUNDS;
	}

	public function e(): int
	{
		return $this->curr[1] ?? self::OUT_OF_BOUNDS;
	}


	public function next_b(): int
	{
		$this->prepare_next();
		return $this->next[0] ?? self::OUT_OF_BOUNDS;
	}

	public function next_e(): int
	{
		$this->prepare_next();
		return $this->next[1] ?? self::OUT_OF_BOUNDS;
	}
	
// 	public function get(): ?array
	public function get()//: array
	{
		$val = $this->curr;

		++$this->ix;
		$this->curr = $this->next;
		$this->next_next = NULL;
		$this->next_next = NULL;

		if (!$this->curr)
			$this->curr = $this->fetch_interval();
	
		return $val;
	}

	/* nb undefined behaviour if unget() is used more than once between calls. */
// 	public function unget(array $interval = []) : void
	public function unget(array $interval = [])
	{
		if (empty($interval) || !is_array($interval) || 2 != count($interval))
		{
			trigger_error(get_class()."::unget() passed a bad interval, data lost.", E_USER_WARNING);
			return;
		}
		/* in theory I could implement a stack, but I don't see the need right now. */
		if ($this->next_next)
			trigger_error(get_class()."::unget() called more than once between reads, data lost.", E_USER_WARNING);
			
		$this->next_next = $this->next; 
		$this->next = $this->curr;
		$this->curr = $interval;
		$this->ix--;
	}
	
	/* this default implementation handles matters for no-way-back streams. */
	public function seek(int $offset, int $whence): bool
	{
		switch($whence)
		{
		case SEEK_SET:    $dest = $offset;              break;
		case SEEK_CUR:    $dest = $this->ix + $offset;  break;
		case SEEK_END:
			trigger_error(get_class()."::seek() cannot move relative to stream end, staying at current position.", E_USER_WARNING);
			return false;
		default:
			exiterror("Invalid integer for 'whence' when calling " . get_class() . "::seek()");
		}
		
		/* only way to go backwardsis to rewind. */
		if ($dest < $this->ix)
			$this->rewind();
		
		/* fortwards to the destination! */
		while ($dest > $this->ix)
			if ($this->skip_interval())
				$this->ix++;
			else 
				break;
		return $this->ix == $dest;
	}
	
	public function tell(): int
	{
		return $this->ix;
	}
		
// 	public function rewind() : void
	public function rewind()
	{
		$this->close();
		$this->internal_open();
		$this->ix = 0;
		$this->next = $this->next_next = NULL;
		$this->curr = $this->fetch_interval(); 
	}

// 	public function size(): ?int
	public function size(): int
	{
		trigger_error(get_class()."::size() cannot report total stream size, returning current position.", E_USER_WARNING);
		return $this->ix;
	}
	
	public function seek_to_cpos(int $cpos) : bool
	{		
		while ($this->b() < $cpos)
			if (!$this->get())
				return false;
		return true;
	}
}






/**
 * Note this one does not inherit because ranging over an array is so different to using a stream.
 */
class CposCollectionStream implements IntervalStream
{
	private $data = [];
	private $ix = 0;
	private $n;
	
	/** iff irreversible, collection entries are unset once the stream moves over them;
	 * in this case, the stream can't go backwards! */
	private $irreversible = false;
	
	public function __construct($data)
	{
		$this->data = $data;
		$this->n    = count($data);
	}
	
	public function __destruct()
	{
		$this->close();
	}
	
	public function b() : int
	{
		return ( $this->ix < $this->n ? $this->data[$this->ix][0] : self::OUT_OF_BOUNDS );
	}
	
	public function e() : int
	{
		return ( $this->ix < $this->n ? $this->data[$this->ix][1] : self::OUT_OF_BOUNDS );
	}
	
	public function next_b() : int
	{
		$i = $this->ix + 1;
		return ( $i < $this->n ? $this->data[$i][0] : self::OUT_OF_BOUNDS );
	}
	
	public function next_e() : int
	{
		$i = $this->ix + 1;
		return ( $i < $this->n ? $this->data[$i][1] : self::OUT_OF_BOUNDS );
	}
	
// 	public function get() : ?array
	public function get() //: array
	{
		if ($this->ix == $this->n)
			return NULL;
		
		if (!$this->irreversible)
			return $this->data[$this->ix++] ?? NULL;
		else 
		{
			$r = $this->data[$this->ix] ?? NULL;
			unset($this->data[$this->ix]);
			$this->ix++;
			return $r;
		}
	}
	
// 	public function unget(array $interval = []) : void
	public function unget(array $interval = [])
	{
		if (!$this->irreversible)
			--$this->ix;
	}
	
	public function seek(int $offset, int $whence) : bool
	{
		if ($this->irreversible)
			if (    ($whence == SEEK_SET && $offset < $this->ix)
					||
					($whence == SEEK_CUR && $offset < 0)
					||
					($whence == SEEK_END && ($this->n - $offset) < $this->ix)
				)
				return false;
					
		switch($whence)
		{
		case SEEK_SET:    $this->ix  = $offset;                break;
		case SEEK_CUR:    $this->ix += $offset;                break;
		case SEEK_END:    $this->ix  = $this->n + $offset;     break;
		default:
			exiterror("Invalid integer for 'whence' when calling " . get_class() . "::seek()");
		}
		/* clamp */
		$this->ix = min( [$this->ix, $this->n] );
		$this->ix = max( [$this->ix, 0] );
		return ($this->ix == $this->n);
	}

	public function tell() : int
	{
		return $this->ix;
	}
	
// 	public function rewind() : void
	public function rewind()
	{
		if (!$this->irreversible)
			$this->ix = 0;
	}

// 	public function size() : ?int
	public function size() : int
	{
		return $this->n;
	}
	
// 	public function close() : void
	public function close()
	{
		unset($this->data);
		$this->ix = $this->n;
	}
	
	public function seek_to_cpos(int $cpos) : bool
	{
		if ($this->ix == $this->n)
			return false;
		if ($this->b() >= $cpos)
			return true;
		
		/* otherwise: let's try a binary search */
		
		$ceiling = $this->n;
		
		while (true)
		{
			$guess = $this->ix + intval(($ceiling - $this->ix)/2);
		
			switch ($this->data[$guess][0] <=> $cpos)
			{
			case 0:
				$this->ix = $guess;
				return true;
		
			case 1:
				/* guess too high: make $guess the new ceiling, try again */
				$ceiling = $guess;
				continue 2;
			
			case -1: 
				/* guess too low: move ix to guess */
				$this->ix = $guess; 
				if (8 > ($ceiling - $guess))
				{
					while ($this->b() < $cpos && $this->ix < $this->n)
						$this->ix++;
					return $this->ix < $this->n;
				}
				else
					continue 2;
			}
		}
	}
	
	public function set_irreversible(bool $new_setting)
	{
		$this->irreversible = $new_setting ? true : false;
	}
}




/**
 * This class has simplified functions which do away with a lot of the diceyness 
 * of the no-way-back streams. 
 */
class SqlResultStream extends IntervalStreamBase implements IntervalStream
{
	private $r;
	private $sql;
	
	/* override parent requirement with null function */
// 	protected function internal_open() : void {}
	protected function internal_open() {}

	protected function fetch_interval() //: array // TODO this actually needs ?Array
	{
		/* we use the object so as not to be depenedent on the order of b and e in the select. */
		if ($o = $this->r->fetch_object())
//{
//show_var($o);
			return [ $o->b, $o->e ];
//}
		else 
			return NULL;
	}
	
	/* override parent requirement with null function */
	protected function skip_interval() : bool {}
	
	public function __construct(string $sql)
	{
		$this->sql = $sql;
// 		$this->r = do_mysql_query($sql, false);
		if (!($this->r = do_mysql_query($sql)))
			return NULL;
		$this->curr = $this->fetch_interval();
	}
		
// 	protected function prepare_next() : void
	protected function prepare_next()
	{
		if (!$this->next)
			$this->next = $this->fetch_interval();
	}

// 	public function get(): ?array
	public function get()//: array
	{
//echo "<pre>/=================================================================================\</pre>\n";
//show_var($this->ix);
		if ($this->ix == $this->r->num_rows)
			return NULL;

		$val = $this->curr;
//show_var($val);

		++$this->ix;
		$this->curr = $this->next;
		$this->next = NULL;

		if (!$this->curr)
			$this->curr = $this->fetch_interval();
	
//show_var($this->ix);
//echo "<pre>\=================================================================================/</pre>\n";


		return $val;
	}

// 	public function unget(array $interval = []) : void
	public function unget(array $interval = [])
	{
		$this->seek(-1, SEEK_CUR);
	}

	public function seek(int $offset, int $whence): bool
	{
		switch($whence)
		{
		case SEEK_SET:    $this->ix  = $offset;                break;
		case SEEK_CUR:    $this->ix += $offset;                break;
		case SEEK_END:    $this->ix  = $this->n + $offset;     break;
		default:
			exiterror("Invalid integer for 'whence' when calling " . get_class() . "::seek()");
		}
		/* clamp */
		$this->ix = min( [$this->ix, $this->r->num_rows] );
		$this->ix = max( [$this->ix, 0] );
		
		$success = $this->r->data_seek($this->ix);
		
		$this->curr = $this->fetch_interval();
		$this->next = NULL;
		
		return $success;
	}
	
// 	public function rewind() : void
	public function rewind() 
	{
		$this->seek(0, SEEK_SET);
	}

// 	public function size(): ?int
	public function size(): int
	{
		return $this->r->num_rows;
	}

// 	public function close() : void
	public function close()
	{
		if($this->r)
		{
			$this->ix = $this->r->num_rows;
			$this->r->free();
			unset($this->r);
		}
		
	}
}






class TabCqpStream extends IntervalStreamBase implements IntervalStream
{
	private $cqp;
	private $tab_cmd;
	
// 	protected function internal_open() : void
	protected function internal_open() 
	{
		$this->cqp->raw_execute($this->tab_cmd);
	}
	
// 	protected function fetch_interval() : ?array
	protected function fetch_interval() //: array
	{
		return $this->fetch_interval_from_tabulated_stream($this->cqp->raw_read());
	}
	
	protected function skip_interval() : bool
	{
		return false !== $this->cqp->raw_read();
	}

	public function __construct(CQP $cqp, string $tab_cmd)
	{
		$this->cqp = $cqp;
		$this->tab_cmd = $tab_cmd;
		
		parent::__construct();
	}

// 	public function close() : void
	public function close()
	{
		$this->cqp->raw_discard();
	}
}



class TabFileStream extends IntervalStreamBase implements IntervalStream
{
	protected $file;
	protected $src;
	
// 	protected function internal_open() : void
	protected function internal_open()
	{
		$this->src = fopen($this->file, "r");
	}
	
// 	protected function fetch_interval() : ?array
	protected function fetch_interval() //: array
	{
		return $this->fetch_interval_from_tabulated_stream(fgets($this->src));
	}

	protected function skip_interval() : bool
	{
		return false !== fgets($this->src);
	}
	
	public function __construct(string $file)
	{
		if ( !empty($file) && is_file($file) && is_readable($file) ) 
			$this->file = $file;
		parent::__construct();
	}
	
// 	public function close() : void
	public function close()
	{
		fclose($this->src);
	}
}






class TabPipeStream extends TabFileStream implements IntervalStream
{
	private $cmd;

// 	protected function internal_open() : void
	protected function internal_open()
	{
		$this->src = popen($this->cmd);
	}
	
	public function __construct(string $cmd)
	{
		if (!empty($cmd))
			$this->cmd = $cmd;
		
		parent::__construct('');
	}
	
// 	public function close() : void
	public function close()
	{
		pclose($this->src);
	}
}




class XmlAttributeStream extends TabPipeStream implements IntervalStream
{
	private $corpus;
	private $att;
	private $with_values;
	
// 	protected function internal_open() : void
	protected function internal_open()
	{
		$this->src = open_xml_attribute_stream($this->corpus, $this->att, true);
	}
	
	public function __construct(string $corpus, string $att, bool $with_values)
	{		
		$this->corpus = $corpus;
		$this->att = $att;
		$this->with_values = $with_values;
		
//TODO ->n() could be implemented here if we cache it in xml metadata and look it up here... */
		parent::__construct('');
	}
	
	
	/* extra functions for accessing values */
	
// 	protected function fetch_interval_from_tabulated_stream(string $line) : ?array
	protected function fetch_interval_from_tabulated_stream(string $line) //: array
	{
		if (empty($line))
			return NULL;
		else
		{
			$a = explode("\t", trim($line, "\r\n"));
// below can most likely be deleted as the bug is, I believe, now caught.
if (3 != count($a)) {squawk ("bad array line 762, value= $line!!"); return NULL;}
			if ($this->with_values)
				return [ (int)$a[0], (int)$a[1], $a[2] ];
			else
				return [ (int)$a[0], (int)$a[1] ];
		}
	}
	
	public function v() : string
	{
		/* will always return just an empty string if !$with_values */
		return $this->curr[2] ?? '';
	}
	

	public function next_v(): int
	{
		/* will always return just an empty string if !$with_values */
		$this->prepare_next();
		return $this->next[2] ?? self::OUT_OF_BOUNDS;
	}
}





/**
 * Open a sequence of corpus-position intervals ( [begin,end] or [match,matchend] pairs)
 * via an interface that allows file-stream style reading.
 * 
 * @param  int              $stream_type     IntervalStream::OPEN constant.
 * @param  mixed            ...$additional   Additional arguments depending on $stream_type. 
 *                                           Check each one's constructor for what arguments.
 * @return IntervalStream                    An object that can be used via the unified interface.
 */
// function open_interval_stream(int $stream_type, ...$additional) : ?IntervalStream 
function open_interval_stream(int $stream_type, ...$additional) : IntervalStream 
{
	switch($stream_type)
	{
	case IntervalStream::OPEN_CPOS_COLLECTION:
		/* additional : 0 => the cpos collection array */
		return new CposCollectionStream($additional[0]);

	case IntervalStream::OPEN_SQL_SELECT:
		/* additional : 0 => sql statement */
		return new SqlResultStream($additional[0]);

	case IntervalStream::OPEN_TAB_CQP_READ:
		/* additional: 0 => cqp object to use; 1 => tab command. */
		return new TabCqpStream($additional[0], $additional[1]);
		
	case IntervalStream::OPEN_TAB_FILE:
		/* additional : 0 => file path */
		return new TabFileStream($additional[0]);
		
	case IntervalStream::OPEN_TAB_PIPE:
		/* additional : 0 => command for poem */
		return new TabPipeStream($additional[0]);
		
	case IntervalStream::OPEN_XML_ATTRIBUTE:
		/* additional : 0 => corpus handle, 1 => xml att handle, 2 => values? */
		return new XmlAttributeStream($additional[0], $additional[1], $additional[2]);
	
	default:
		exiterror("Cannot open IntervalStream: unrecognised stream type.");
	}
}

