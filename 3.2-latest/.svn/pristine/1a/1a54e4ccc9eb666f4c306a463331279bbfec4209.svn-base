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
 * Array mathemetics functions: beginning with arithmetic, moving on to some stats.
 */


/* functions producing a new array */

function array_divide(arr, divisor)
{
	if (divisor instanceof Array)
	{
		var result = new Array();
		if (divisor.length != arr.length)
			return null;
		for (var i = 0 ; i < arr.length ; i++)
			result[i] = arr[i] / divisor[i];
		return result;
	}
	else
		return arr.map(function(v) {return v / divisor;});
}

function array_multiply(arr, multiplier)
{
	if (multiplier instanceof Array)
	{
		var result = new Array();
		if (multiplier.length != arr.length)
			return null;
		for (var i = 0 ; i < arr.length ; i++)
			result[i] = arr[i] * multiplier[i];
		return result;
	}
	else
		return arr.map(function(v) {return v * multiplier;});

}

function array_add(arr, addend)
{
	if (addend instanceof Array)
	{
		var result = new Array();
		if (addend.length != arr.length)
			return null;
		for (var i = 0 ; i < arr.length ; i++)
			result[i] = arr[i] + addend[i];
		return result;
	}
	else
		return arr.map(function(v) {return v + addend;});
}

function array_subtract(arr, subtrahend)
{
	if (subtrahend instanceof Array)
	{
		var result = new Array();
		if (subtrahend.length != arr.length)
			return null;
		for (var i = 0 ; i < arr.length ; i++)
			result[i] = arr[i] - subtrahend[i];
		return result;
	}
	else
		return arr.map(function(v) {return v - subtrahend;});

}

function array_absolute(arr)
{
	return arr.map(function(v) {return Math.abs(v) });
}



/* single-value functions */

function array_sum(arr)
{
	return arr.reduce(function(t,v) {return t + v;}, 0);
}

function array_max(arr)
{
	return arr.reduce(function (t,v) {return Math.max(t,v);});
}

function array_min(arr)
{
	return arr.reduce(function (t,v) {return Math.min(t,v);});
}

function array_range(arr)
{
	// faster or slower than callinbg _max, _min?
	var min = 0; 
	var max = 0;
	for (var i ; i < arr.length ; i++)
	{
		min = Math.min(min, arr[i]);
		max = Math.max(max, arr[i]);
	}
	return max - min;
}

function array_sum_absolute(arr)
{
	return array_sum(array_absolute(arr));
}

function array_mean(arr)
{
	return array_sum(arr)/arr.length;
}

function array_stdev(arr)
{
	var mean = array_mean(arr);
	
	var square_diffs = arr.map( 
		function (v)
		{
			var curr_square_diff = v - mean;
			return curr_square_diff * curr_square_diff;
		}
	);
	
	var variance = array_mean(square_diffs);
	return Math.sqrt(variance);
}

function array_stdev_population(arr)
{
	return array_stdev(arr) * Math.sqrt((arr.length - 1) / arr.length);
}


function array_median(arr)
{
	return array_percentile(arr, 50);
}

function array_upper_quartile(arr)
{
	return array_percentile(arr, 75);
}

function array_lower_quartile(arr)
{
	return array_percentile(arr, 25);
}

function array_interquartile_range(arr)
{	
	var mutable = JSON.parse(JSON.stringify(arr));
	mutable.sort();
	
	return array_percentile_presorted(arr, 75) - array_percentile_presorted(arr, 25);
}

function array_percentile_presorted(arr, percentile)
{
	var desired_position = (percentile/100) * (arr.length-1);
	var before = Math.floor(desired_position);
	var fraction_after = desired_position - before;
	
	if (undefined === arr[before+1])
		return arr[before];
	else
		return arr[before] + (fraction_after * (mutable[before+1]-mutable[before]));
}


function array_percentile(arr, percentile)
{
	var mutable = JSON.parse(JSON.stringify(arr));
	mutable.sort();
	
	return array_percentile_presorted(mutable, percentile);
	
}

