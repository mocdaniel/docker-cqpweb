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




function coerce_numbers_for_position_data(d)
{
	d.position = +d.position;
	d.startpoint = +d.startpoint;
	//TODO this records the query string in every row.
	//Is there a cleaner / lest intensive way to do this?
	d.qstg = Dispersion.querystrings[Dispersion.querystrings.length - 1];
}			

function coerce_numbers_for_distribution_data(d)
{
	// console.log(d.Text_n +  " has been integer-pardsed!!!");	
	d.freqpm = +d.freqpm;
	d.absfreq = +d.absfreq;
	d.textsize = +d.textsize;
	d.TxtBegin = +d.TxtBegin;
	d.TxtEnd = +d.TxtEnd;
	d.Text_n = +d.Text_n;
	d.qstg = Dispersion.querystrings[Dispersion.querystrings.length - 1];
					// TODO - see above again
}




/**
 * Initialises the dispersion overview visualisation
 * (all data stored in global Overview object).
 */
function initialise_dispersion_overview_visualisation()
{ 
	/* global container object for the overview display */
	Overview = {};
	/* margins for main plot and brush view */
	Overview.margin = {top: 20, right: 40, bottom: 40, left: 40};
	/* SVG size for main plot */
	Overview.width  = 980 - Overview.margin.left - Overview.margin.right;
	Overview.height = 500 - Overview.margin.top  - Overview.margin.bottom;


	/* append svg for plot of the Overview area to the appropriate empty div in the DOM */
	Overview.svg_plot = d3.select("#overview").append("svg")
		.attr("class","overview")
		.attr("id", "svgOverview")
		.attr("width", Dispersion.width_of_viz_svg)
		.attr("height", Dispersion.height_of_viz_svg)
		.append("g")
			.attr("id", "topContainer")
			.attr("transform", "translate(" + Overview.margin.left + "," + Overview.margin.top + ")")
			;


	
	/* set scales and domains for the Overview axes */ 
//	Overview.x_scale = d3.scalePoint().range([0, Overview.width], 0.01);
	Overview.x_scale = d3.scaleLinear().range([0, Overview.width]);
	Overview.x_scale.domain([0, Dispersion.d3_dist_data.length+1]);
// 	var reducetick = parseInt( Dispersion.d3_dist_data.length / 65);
	Overview.x_axis = d3.axisBottom()
		.scale(Overview.x_scale)
// 		.tickValues(
// 					Overview.x_scale.domain().filter(function(d, i) { return !(i % reducetick); })
// 				)
// #######################################prob need to do this in frce_x_etc forit to work.
	//TODO limit the number of ticks (if number of text is higher than 70?)
		// .tickValues(Overview.x_scale.domain().filter(function(d,i){ return !(i%10)}));
				;
	force_x_ticks_to_text_id(Overview.x_axis, Overview.x_scale);

	Overview.y_axis = d3.axisLeft();
	Overview.y_scale = d3.scaleLinear().range([Overview.height, 0]);

	rescale_dispersion_overview_y_axis();
	  
	/* Append Overview axes and labels to the Overview plot. */
	  
	/* y label */
	Overview.svg_plot.append('text')
		.attr('x', 10)
		.attr('y', 10)
		.attr('class', 'label')
		.text('Freq per 1,000,000')
		;
	/* x label */
	Overview.svg_plot.append('text')
		.attr('x', Overview.width)
		.attr('y', Overview.height - 10)
		.attr('text-anchor', 'end')
		.attr('class', 'label')
		.text('Texts')
		;

	/* x-axis */
	// force_x_ticks_to_text_id(Overview.x_axis, Overview.x_scale);
	Overview.x_selection = Overview.svg_plot.append('g');
	Overview.x_selection
		.attr('transform', 'translate(0,' + Overview.height + ')')
		.attr('class', 'x axis')
		.call(Overview.x_axis)
		.selectAll("text")
		.attr("y", 0)
		.attr("x", 9)
		.attr("dy", ".35em")
		.attr("transform", "rotate(90)")
		.style("text-anchor", "start")
		;

	/* y-axis */
	Overview.y_selection = Overview.svg_plot.append('g');
	Overview.y_selection
		.attr('transform', 'translate(0,0)')
		.attr('class', 'y-axis')
		.call(Overview.y_axis)
		;
	
	/* create function to generate tooltip from hovering over a dot */
	Dispersion.tool_tip = d3.tip()
		.attr("class", "d3-tip")
		.offset([-8, 0])
		.html(function(d) { return `Query: ${d.qstg}<br>Text: ${d.Text}<br>Rel. Freq.: ${d.freqpm}<br>Abs. Freq.: ${d.absfreq}`; })
		;


	/* now, set up the dot area: first initialise, then set the data.  */
	initialise_dispersion_overview_dot_area();
	update_dispersion_overview_dot_area();
	
	/* turn wheelzoom on any dot into wheel zoom on #topContainer */
	Overview.dot_area.enter()
		.on("wheel.zoom", function (d) 
			{
				// console.log("about to dispatch wheel from " + d.Text);
				d3.select("#topContainer").dispatch("wheel.zoom");
				// console.log("wheel sent!!!!");
				return false;
			})
		;

	/* add the tooltip function just created to the SVG plot. */
	Overview.svg_plot.call(Dispersion.tool_tip);



	/* put the zoom action on the SVG group (#topContainer) that contains everything else */
	var zoom = d3.zoom().on("zoom", zoom_dispersion_overview);
	d3.select("#topContainer").call(zoom);
// console.log("zoom action created and added to topContainer");
}




function initialise_dispersion_overview_dot_area()
{
	/*  create inner SVG group to attach the zoom functionality to later */
	Overview.zoom_group = Overview.svg_plot.append("g");

	/* create a D3 selection for teh area that contains the overview dots. */ 
	Overview.dot_area = Overview.zoom_group.selectAll("circle");

	/* add settings for the dot-collection itself */
	Overview.dot_area
		.attr('x', Overview.width)
		.attr('y', Overview.height - 10)
		;
}








/**
 * Sets up the d3 objects and dispersion-measure arrays based on 2 TSV strings:
 * one with a row per text, one with a row per hit containing data on its
 * position. 
 * 
 * OR, if this is a second or subsequent query, adds to what is there already.
 * 
 * @param tsv_dist_data
 * @param tsv_hitposition
 * @returns
 */
function import_dispersion_data_from_tsv(tsv_dist_data, tsv_hitposition)
{
	/* for each tsv table that we have created, turn it into a d3 object, 
	 * and then go through to coerce all strings to numbers. */ 
	var d3_dist_data = d3.tsvParse(tsv_dist_data);
	d3_dist_data.forEach(coerce_numbers_for_distribution_data);
	
	var d3_hitposition = d3.tsvParse(tsv_hitposition);
	d3_hitposition.forEach(coerce_numbers_for_position_data);

	/* get some basic numbers */
	
	/* arrays with sizes of texts in (absolute and proportion-of-whole) */
	var text_sizes = d3_dist_data.map(function(value) { return value.textsize; });
	var corpus_size = array_sum_absolute(text_sizes);
	var text_sizes_as_corpus_fractions = array_divide(text_sizes, corpus_size);
	var n_texts = text_sizes.length;

	/* frequency of result in each text (array); plus a sum; then the proportion of total hits within each text . */
	var n_hits_in_text_abs  = d3_dist_data.map(function(v) {return v.absfreq === undefined ? 0 : v.absfreq; });
	var total_n_hits = array_sum_absolute(n_hits_in_text_abs);
	var n_hits_in_text_as_fraction = array_divide(n_hits_in_text_abs, total_n_hits);

// 	document.getElementById("freqcorpus").innerHTML = total_n_hits; //return hits in corpus to result heading
// 	document.getElementById("cospussize").innerHTML = corpussize; // display corpus size in result heading
// 	document.getElementById("totrelfreq").innerHTML = Number(total_n_hits / corpussize * 1000000).toFixed(2); //display relative frequency


	/* calculate DP and DPnorm, but only store the latter in the Dispersion array.  */
	var dpmeasure = 0.5 * array_sum_absolute(array_subtract(n_hits_in_text_as_fraction, text_sizes_as_corpus_fractions));
	var curr_dpnorm = dpmeasure/(1 - Math.min.apply(null, text_sizes_as_corpus_fractions));
	Dispersion.dpnorm.push(curr_dpnorm);

	/* calculate && store Juilland's D */
	var stdev_of_hits_fractional = array_stdev_population(n_hits_in_text_as_fraction);
	var mean_of_hits_fractional = array_mean(n_hits_in_text_as_fraction);
	var curr_juilld = 1 - (stdev_of_hits_fractional/mean_of_hits_fractional)/Math.sqrt(n_texts-1);
	Dispersion.juilland.push(curr_juilld);

	/* calculate && store Range */
	var n_texts_with_at_least_one_hit = n_hits_in_text_abs.reduce(function (total, n_hits) {return 0 == n_hits ? 0 : 1 ;}, 0);
	Dispersion.range.push(n_texts_with_at_least_one_hit);

	/* only on the first call to this function, i.e. for the first query. */
	if (Dispersion.range.length == 1)
	{
		/* insert dispersion measure statistics into page heading */
		document.getElementById("dpnorm").innerHTML = Number(curr_dpnorm).toFixed(2);
		document.getElementById("juilld").innerHTML = Number(curr_juilld).toFixed(2);
		document.getElementById("range").innerHTML  = n_texts_with_at_least_one_hit;
		document.getElementById("textsn").innerHTML = n_hits_in_text_abs.length;
		
		/* create D3 object members on the Dispersion object */
		Dispersion.d3_dist_data   = d3_dist_data;
		Dispersion.d3_hitposition = d3_hitposition;
	}
	else 
	{	
		/* d3 objects belonging to Dispersion already exist; concatenate onto them. */
		Dispersion.d3_dist_data   = Dispersion.d3_dist_data.concat(d3_dist_data);
		Dispersion.d3_hitposition = Dispersion.d3_hitposition.concat(d3_hitposition );
	}
}


function rescale_dispersion_overview_y_axis()
{
	Overview.y_scale.domain([0, d3.max(Dispersion.d3_dist_data, function(d) { return d.freqpm; })]);
	Overview.y_axis.scale(Overview.y_scale);
	/* when this is called the first time, y_selection is not yet set up. */
	if ('undefined' != typeof Overview.y_selection)
		Overview.y_selection.call(Overview.y_axis);
}




/** 
 * This function opens the query stats popup for the corpus
 * whose dot was clicked in the legend.
 */
function open_query_stats_popup()
{
	/* "each" here only affects the legend dot that was actually clicked. 
	 * Not ALL legend dots. */
	d3.select(this).each(function(d)
	{
		var a = array_lookup_index(querystrings, d);
		var x = $("circle").position();
		
		var stats_popup = $("#qStatsPopup");
		stats_popup.css('left', '980').css('top', (300 + a*21).toString());
		
//		document.getElementById("myDropdown").style.left = 980 ;
//		document.getElementById("myDropdown").style.top = 300 + a*21 ;
//		document.getElementById("myDropdown").classList.toggle("show");

//		var dpsts = Number(dpnorm[a]).toFixed(2);
//		document.getElementById("dpsts").innerHTML = dpsts;
		$("#dpsts").html( Number(Dispersion.dpnorm[a]).toFixed(2) );

//		var jlsts = Number(Dispersion.juilland[a]).toFixed(2);
//		document.getElementById("jlsts").innerHTML = jlsts;
		$("#jlsts").html( Number(Dispersion.juilland[a]).toFixed(2) );

//		var rgsts = rgarray[a] + "/" + freqtext.length;
//		document.getElementById("rgsts").innerHTML = rgsts;
		$("#rgsts").html(rgarray[a] + "/" + freqtext.length );
		
		stats_popup.fadeIn();
	});
}



/** Event handler for clicks on dots in the disperrsion overview. */
function add_new_single_text_view_to_display()
{
	/* within this function, this = the DOM node for the circle that has been clicked. 
	 * ie. the same thing as "event.target". 
	 *
	 * We want to access the circle's d3 data, so we call d3 each
	 * on a selection that includes just that single circle.
	 * The rest of this event handler function is inside the callback 
	 * that is passed to d3 each.
	 *
	 * Within the callback, d = the datum associated with the circle that was clicked. 
	 */   
	d3.select(this).each(function(d)
	{
		/* Holds the ID of the text whose dot was clicked on. */
		var text_id_clicked = d.Text;

		/* Hold the search term of the dot was clicked on. */ 
		var query_of_dot_clicked = d.qstg;

		/* Extract the set of datapoints we need for the hits that occur in this particular text. 
		 * This is done by searching the "hitpositon" d3 object, and keeping those which have the
		 * same query string / text ID as the dot that was clicked on.
		 *
		 * The result is an  array of objects where each object = 1 row from the "hitposition" table.
		 */

		var d3_by_single_text = Dispersion.d3_hitposition.filter(function(d)
		{
			/* if callback returns true, the datap;oint at hand is retained in the fil;tered d3. */
			return (d.Text == Overview.text_id_clicked && d.qstg == Overview.query_of_dot_clicked);
///############# hmm, long term it would be better to use a mroe objective f key for the query, e.g. qname. 
//bsically what is happening here is 3 database tables - texts, hits, queries 
//vbuilding up in memory
//need ot be careful not to do in JS what woudl be better in SQL. 
		} );

		/* basic info needed to create the by-text display */
		var margin = {top: 20, right: 20, bottom: 30, left: 40};

		var width = 960 - margin.left - margin.right;

		var height = 100 - margin.top - margin.bottom;

		var x_range = d3.scaleLinear().range([0,width]);

		var y_range = d3.scaleLinear().range([height, 0]); // not used.

		var x_axis = d3.axisBottom(x_range).scale(x_range);

		var y_axis = d3.axisLeft().scale(y_range); // not used


		/* now, create and fill in a new SVG for the new display at the bottom. 
		 * thus, every text-dot-click creates a new svg on the end of #singleTextView */
		var svg = d3.selectAll("#singleTextView").append("svg:svg");
		
		svg .attr("class","bySingleText")		
			.data(d3_by_single_text)
			.attr("width", width + margin.left + margin.right)
			.attr("height", height + margin.top + margin.bottom)
			// .call(d3.zoom().on("zoom", function ()
			// {
			//   svg.attr("transform", d3.event.transform) //TODO fix zoom
			//nb here is the critical point for the sideways zoom
			// }))
			.append("g")
				.attr("transform", "translate(" + margin.left + "," + margin.top + ")")
			;

		
//			//filter d3_dist_data to get each text length
//			var textBegEnd = d3_dist_data.filter(function(d)
//			{
//				// clog(d);
//				return d.Text == text_id_clicked;
//			});
//				x_range.domain([d3.min(textBegEnd, function(d)
//					{
//						return d.TxtBegin;
//					}),
//should not now be needed. the following simpler way does the job. 

		/* set the domain of the x_range (from trext begin to text end as cpos deltas) */
		x_range.domain( [0, d.TxtEnd] );

		/* now we can add the data, and then turn on its display. */
		svg.append('g')
				.data(d3_by_single_text)
				.attr('transform', 'translate(0,' + height + ')')
				.attr('class', 'x axis')
				.call(x_axis)
				;

		/* create the tooltip for this by-text display. */
		var tool_tip = d3.tip();
		tool_tip
			.attr("class", "d3-tip")
			.offset([-8, 0])
			.html(function(d) { return "position: " + d.position; })
			// TODO maybe clcik through to concordance instead?
			;
		svg.call(tool_tip);

		/* ok, now we can draw the dots inside the SVG */
		svg.selectAll("circle")
			.data(d3_by_single_text)
			.enter()
				.append("circle")
				.attr("cx", function(d) { return x(d.position); } )
				.attr("r", 5)		
//					.attr("fill", function(d) { return get_colour_scheme_for_overview(Dispersion.querystrings); })
				.attr("fill", function(d) { return get_colour_scheme_for_overview(d.qstg); })
				.style("fill-opacity", 0.3)
				.on('mouseover', tool_tip.show)
				.on('mouseout', tool_tip.hide)
				.on('click', function (d) { navigate_to_context_at_cpos(d.real_cpos);})
				;

		/* commented out: alternative draw which produces it "barcode style"" */
		// svg.selectAll("circle")
		//     .data(d3_by_single_text)
		//     .enter()
		//         .append("rect")
		//         .attr("x",function(d) {return x(d.position);})
		//         .attr("height", 25)
		//         .attr("width", 1)
		//         .attr("fill", function(d) { return get_colour_scheme_for_overview(Dispersion.querystrings); })
		//         .style("fill-opacity", 0.3)
		//         .on('mouseover', tool_tip.show)
		//         .on('mouseout', tool_tip.hide);


		/* at first the query string, and then the text ID, as extra labels at the end of the SVG. */ 
		svg.append('text')
//				.data(d3_by_single_text)// AH: I think this is not needed, but try uncommmenting if bugs occur
			.attr('x', 10)
			.attr('y', height - 10)
			.attr('class', 'label')
			.text(function() {return query_of_dot_clicked;})
			;

		// add text ID
		svg.append('text')
//				.data(Dispersion.d3_hitposition) // AH: I think this is not needed, but try uncommmenting if bugs occur
			.attr('x', width)
			.attr('y', height - 10)
			.attr('text-anchor', 'end')
			.attr('class', 'label')
//				.text(function() {return JSON.stringify(text_id_clicked);})
			.text(function() {return text_id_clicked;})
			;

	}); /* end of the callback passed to d3.each for the overview dot clicked on. */

	/* no need for a return because these dots do not have a default action that we need to block. */
}


/**
 * This function can be placed on window as an event handler 
 * to catch all clicks outside a query stats popup and respond 
 * by hiding said popups. 
 */
function close_query_stats_popups(e)
{
	/* if the click is NOT on the qStatsPopup ... */
	if (!e.target.matches('.qStatsPopup'))
//	{
		$("#qStatsPopup").fadeOut();
		
//		var dropdowns = document.getElementsByClassName("dropdown-content");
//		var i;
//		for (i = 0; i < dropdowns.length; i++)
//		{
//			var openDropdown = dropdowns[i];
//			if (openDropdown.classList.contains('show'))
//			{
//				openDropdown.classList.remove('show');
//			}
//		}
//	}
}


/**
 * Redirects the browser to extended context view, with focus
 * at the given cpos.
 *   
 * @param cpos Integer corpus position.
 */
function navigate_to_context_at_cpos(cpos)
{
	greyout_and_throbber();
	window.location.href = "context.php?viewAtCpos="+cpos;
}
// TODO move to always.js


/** Writes out the dispersion measures popup as a static HTML page in a new tab. */
function render_dispersion_measures_table_in_new_tab()
{
	var tab = window.open('about:blank', '_blank');
	tab.document.write(`
		<html>
			<head>
				<title>Dispersion Measures</title>
			</head>
			<body>
				<h2>dispersion table</h2>
				
				${Dispersion.dispersion_measures_table_html}
				
				<br>
				<button>Download table</button>
			</body>
		</html>
			
	`);
	tab.document.close();
}



/**
 * Updates the stored HTML table containing all dispersion measures for a set of queries.
 */
function update_dispersion_measure_table() 
{
	var result = `
	<table style="border: 1px solid black; border-collapse:collapse">
		<tr>
			<th>query</th>
			<th>DPnorm</th>
			<th>Juilland</th>
			<th>Range</th>
		</tr>
		`;

	for(var i = 0; i < Dispersion.querystrings.length; i++) 
		result +=  `
		<tr>
			<th>${Dispersion.querystrings[i]}</th>
			<th>${Dispersion.dpnorm[i]}</th>
			<th>${Dispersion.juilland[i]}</th>
			<th>${Dispersion.range[i]}</th>
		</tr>
	`;


	result += "</table>\n";

	Dispersion.dispersion_measures_table_html = result;
}




/** Adds a new entry to the legend that has the query strings in it, and is clickable. */
function update_dispersion_overview_legend()
{
	/* append a group to the SVG for the new entry on the legend. */
	var new_legend_entry = Overview.svg_plot.append("g");
	
	new_legend_entry.attr('transform', "translate(" + Overview.width + "," + Overview.margin.top + ")");

	new_legend_entry.selectAll("mydots")
		.data(Dispersion.querystrings) // TODO pass in th eqstrign index instead so array search not needed., 
		.enter()
			.append("circle")
			.attr("id", "dStats")
			.attr("class","dropbtn")
			.attr("cx", 100)
			.attr("cy", function(d,i){ return 100 + i*25}) // 100 is where the first dot appears. 25 is the distance between dots
			.attr("r", 7)
			.attr("fill", function(d) { return get_colour_scheme_for_overview(d)})
			.on("click", open_query_stats_popup)
			;

	new_legend_entry.selectAll("mylabels")
		.data(Dispersion.querystrings)
		.enter()
			.append("text")
			.attr("x", 120)
			.attr("y", function(d,i){ return 100 + i*25})
			.style("fill", function(d){ return get_colour_scheme_for_overview(d)})
			.text(function(d, i) {return d + " (" + Number(Dispersion.dpnorm[i]).toFixed(2) + ")"} )
			.attr("text-anchor", "left")
			.style("alignment-baseline", "middle")
			;

	new_legend_entry.append("text")
		.text("Queries (DPnorm)")
		.attr("x", 90)
		.attr("y", 70)
		.attr("text-anchor", "left")
		.style("alignment-baseline", "middle")
		;
}






/** Adds circles for each text to the inner area of the dispersion overview. */
function update_dispersion_overview_dot_area()
{
	/* the data is set to the correct D3 data table. 
	 * If that's been updated, the result will be to update the display. */ 
	Overview.dot_area.data(Dispersion.d3_dist_data);
	 
	/* create & add settings for each individual dot */
	Overview.dot_area.enter()
		.append("circle")
			.attr("class", "dot")
			.attr("r", 5)
			.attr("cx", function(d) 
				{ 
					/* DEBUG */ if(false){ clog(d, convert_text_id_to_tick_index(d.Text), Overview.x_scale(convert_text_id_to_tick_index(d.Text))); }
	// 				return Overview.x_scale(convert_text_id_to_tick_index(d.Text));
					return Overview.x_scale(d.Text_n);
				})
			.attr("cy", function(d) { return Overview.y_scale(d.freqpm); })
			.attr("fill", function(d) { return get_colour_scheme_for_overview(d.qstg); })
			.style("fill-opacity", 0.6)
			.on('mouseover', Dispersion.tool_tip.show)
			.on('mouseout', Dispersion.tool_tip.hide)
			.on("click", add_new_single_text_view_to_display)
			;

// console.log("dispatch successfully added");

}




/**
 * Function for "on zoom" event on the overview area.
 */
function zoom_dispersion_overview() 
{
//todo: edit zoom to update axis --> new zoom_dispersion_overview should accomplish this

	/* create new scaling functions from the original ones */
	var new_x_scale = d3.event.transform.rescaleX(Overview.x_scale);
	var new_y_scale = d3.event.transform.rescaleY(Overview.y_scale);


	// update axes with these new boundaries */
	Overview.x_selection.call(d3.axisBottom(new_x_scale));
	Overview.y_selection.call(d3.axisLeft(new_y_scale));
 
	/* now we need to re-impose the labels. */
	force_x_ticks_to_text_id(Overview.x_axis, new_x_scale);

/*
   This is a copy-paste of two online examples of foinf it. 
    // update axes with these new boundaries
	//      .attr("class", "dot")
    xAxis.call(d3.axisBottom(new_x_scale))
    yAxis.call(d3.axisLeft(new_y_scale))
OR
	// create new scale ojects based on event
	var new_xScale = d3.event.transform.rescaleX(xAxisScale)
	var new_yScale = d3.event.transform.rescaleY(yAxisScale)
	console.log(d3.event.transform)
	
	// update axes
	gX.call(xAxis.scale(new_xScale));
	gY.call(yAxis.scale(new_yScale));
 */
// clog("scale adjustments done");

// transform the dots,. AGAIN INVOLVES RE-COPYING
// 			 .attr("cx", function(d) { return Overview.x_scale(convert_text_id_to_tick_index( d.Text) ); })

//     Overview.zoom_group.attr("transform", d3.event.transform)
	Overview.dot_area
		.attr('cx', function(d) {return new_x_scale(d.Text_n)} )
		.attr('cy', function(d) {return new_y_scale(d.freqpm)} )
		;

	// clog ("circle adjustments done");
}







/*
 * ajax dispatcher / handlers
 */



/* Ajax function: runs a new query and adds the resulting data to the dispersion */
function add_new_query_dispersion(e)
{
	// newVal contains the new search pattern
//		var newVal = document.getElementById("theData").value;
	var newVal = $(e.target.elements).filter('[name="theData"]').val();
clog("newval is "+newVqal);
	// store new values to qstrings
	Dispersion.querystrings.push(newVal);

	// call concordance.php to do the query and extract the query identifier from its return
	jQuery.get(
		"concordance.php?theData=" + encodeURI(newVal) + "&qmode=sq_nocase",
//		function(data){ handle_new_query_for_dispersion(data); },
		handle_new_query_for_dispersion,
		"text" 
	);
	return false;
};



/* Ajax return handler: for calls of concordance.php, extracts the query name */
function handle_new_query_for_dispersion(str)
{
	if (-1 != str.search(/Your query had no results/))
		greyout_and_acknowledge("There are no results in the corpus for that query!");
	else
	{
		var res = str.match(/<input type="hidden" name="qname" value="(\w+)"/);
		if (null === res)
			clog("error"); //todo add some kind of error action here
		else
			/* load data to vsualise the new query in the dispersion plot and table */
			jQuery.get(
				"dispersion.php?qname=" + res[1] + "&just=1",
//				function(data) { handle_new_data_for_dispersion(data); }, 
				handle_new_data_for_dispersion,
				"json"
			);
	}
};



/** Handler function for Ajax call: plots the new data to the Dispersion Overview */
function handle_new_data_for_dispersion(new_data)
{
// NB all the following has been factored into functions shared with the initial setup ...
// can eventually be deleted. 
// 		/* parse new [position data && coerce strings to number */
// 		var new_position_data = d3.tsvParse(new_data.tsv_hits);
// 		new_position_data.forEach(coerce_numbers_for_position_data);

// 		/* this concatenates the new data table onto the global main one. */
// 		d3_hitposition = d3_hitposition.concat(new_position_data); 

// 		/* parse new distribution data && coerce strings to number */
// 		var new_distribution_data = d3.tsvParse(new_data.tsv_dist);
// 		new_distribution_data.forEach(coerce_numbers_for_distribution_data);

// 		// calculate dispersion here and push new values to it
// 			// create a marged array of text names and hits per text
// 		let mergedTxt2 = [];


// 		//frequency in each text (array)
// 		var n_hits_in_text_abs2 = new_distribution_data.map(function(value) {
// 			return value.absfreq;
// 		});

// 		n_hits_in_text_abs2 = n_hits_in_text_abs2.map(v => v === undefined ? 0 : v); //get rid of undefined

// 		//frequency in corpus
// 		var total_n_hits2 = array_sum_absolute(n_hits_in_text_abs2);

// 		// calculate DP and DPnorm
// 		var dpmeasure2 = array_sum_absolute(array_subtract(array_divide(n_hits_in_text_abs2, total_n_hits2), text_sizes_as_corpus_fractions))/2;
// 		var newDP = (dpmeasure2/(1 - Math.min.apply(null, text_sizes_as_corpus_fractions)));
// 		 dpnorm.push(newDP);

// 		// calculate Juilland
// 		var propElemPar2 = array_divide(n_hits_in_text_abs2, total_n_hits2)
// 		var juilld2 = 1 - (array_stdev_population(propElemPar2)/array_mean(propElemPar2))/Math.sqrt(n_hits_in_text_abs2.length-1);
// 		Dispersion.juilland.push(juilld2);

// 		// Range
// 		var sumcon2 = 0;
// 		for(var i=0; i < n_hits_in_text_abs2.length; i++)
// 		{
// 			if (n_hits_in_text_abs2[i] > 0)
// 			{
// 				sumcon2 += 1;
// 			}
// 		};
// 		Dispersion.range.push(sumcon2);
// 		d3_dist_data = d3_dist_data.concat(new_distribution_data);


	/* adds the new data to the d3 objects and the Dispersion.xxx arrays. */
	import_dispersion_data_from_tsv(new_data.tsv_dist, new_data.tsv_hits);
clog(10);
	
	/* update table with dispersion measures */
	update_dispersion_measure_table();
clog(20);
	
	/* we now have to update the overall scale of the Y axis. */
	rescale_dispersion_overview_y_axis();
clog(30);
	
	/* and add a new legend */
	update_dispersion_overview_legend();
clog(40);
	
	/* delete existing dots */
	Overview.zoom_group.selectAll(".dot").remove();
clog(50);
	
	/* now add new dots for this data to the data display. */
	update_dispersion_overview_dot_area();
clog(60);
	
	
	
	
// some old code.
//	var dots_in_zoom_group = Overview.zoom_group.selectAll(".dot")
//									.remove()        /* remove the dots that are already there */
//									.exit()
//								.data(Dispersion.d3_dist_data); /* set the zoom_group data. */ 	
//	dots_in_zoom_group.enter()
//			.append("circle")
////			.attr("class", "dots2")   // TODO how is this class used?
//			.attr("class", "dot")   // TODO how is this class used?
//			.attr("r", 5)
//			.attr("cx", function(d) { return Overview.x_scale(d.Text_n); })
//			.attr("cy", function(d) { return Overview.y_scale(d.freqpm); })
//			.attr("fill", function(d) { return get_colour_scheme_for_overview(d.qstg); })
//			.style("fill-opacity", 0.6)
//			.on('mouseover', Dispersion.tool_tip.tool_tip.show)
//			.on('mouseout', Dispersion.tool_tip.tool_tip.hide)
//			.on("click", add_new_single_text_view_to_display)
//			;
}












////////////////////////////////////////////////////////////////////////////////////////////
/// unfiled functions
////////////////////////////////////////////////////////////////////////////////////////////


function convert_text_tick_index_to_id(j)
{
	if (1 > j) return ' ';
	
	var the_Result = '';
	Dispersion.d3_dist_data.forEach(function(d)
		{
			if (d.Text_n == j)
				the_Result = d.Text;
		});
// console.log(`conversion : ` + j + "    ===>   " + the_Result);
	return the_Result;
// 	return txtNames[j-1];
	// tick @ 1 returns text_id @ 0.
}
function convert_text_id_to_tick_index(id)
{
	var the_Result = 0;
	dataDist.forEach(function(d)
		{
			if (d.Text == id)
				the_Result = d.Text_n;
		});
	return the_Result;
}


function get_text_extent(id)
{
	var the_Result = 0;
	dataDist.forEach(function(d){
	if (d.Text == id)
		the_Result = d.textsize - 1;
	});
	return the_Result;
}

function get_text_start_cpos(id)
{
// console.log(id + " now being looked up");
		var the_Result = 0;
			dataDist.forEach(function(d){
			if (d.Text == id)
			{
// console.log(d);
				the_Result = d.TxtBegin;
// console.log(the_Result);
			}
			});
// console.log(the_Result);
			return the_Result;
}
function get_hit_cpos_as_offset(cpos, text_id)
{
	var begin = get_text_start_cpos(text_id);
// 	console.log({tx:text_id, pos:cpos, begin_At:get_text_start_cpos(text_id)});
	return cpos - get_text_start_cpos(text_id);
}





function force_x_ticks_to_text_id (some_axis, its_scale)
{
	var domain_limits = its_scale.domain().sort().map(function (arg) {return Math.round(arg);});
//console.log(domain_limits);
	var n_ticks = domain_limits[1] - domain_limits[0];
// console.log(n_ticks);

// this doesn't work after a zoom. sunno why yet. Probably has to do with integer/non-integr. 
	some_axis.ticks(n_ticks+2);
// 	console.log (some_axis);
// 	console.log(its_scale.range());
	some_axis.tickFormat(
			function(tick_ix)
			{
// console.log(tick_ix);
				if (tick_ix < domain_limits[0] || tick_ix > domain_limits[1])
					return;
				if (0 == tick_ix)
					return " ";
				if ( (Dispersion.d3_dist_data.length + 1) == tick_ix)
					return " ";
				return (  Math.floor(tick_ix) == tick_ix  ?  convert_text_tick_index_to_id(tick_ix)  :  undefined  ); 
			}
		);
	//##########################still conflicts. gaaaaahhhhh 
// some_axis.tickValues (
// 			its_scale.domain().filter(function(d, i) { return !(i % reducetick); })
// 			);
// this is not correct ...... should be the n of ticks that are CURRENTLY visible.
// so we need to get the currentrange, and get tit 

}







/** 
 * Search array for first instance of the given value, and return that entry's index.
 * 
 * @return  Numeric index or null if not found.
 */
function array_lookup_index(arr,val)
{
	for (var i = 0; i < arr.length; i++)
		if (arr[i] === val)
			return i;
	return null;
}



/*
 * Anonymous function for dispersion initialisatuion. 
 */
$( function () {
	
	
	
	/* This is a global function that supplies a colour scheme when given a querty string */
	get_colour_scheme_for_overview = d3.scaleOrdinal(d3.schemeCategory10);
	

	/*
	 * ==============
	 * EVENT HANDLERS
	 * ==============
	 */
	
	/* Close the legend popup if the user clicks outside of it */
	window.onclick = close_query_stats_popups;

	/* add the action to the add-query form */
	$("#addQueryForm").submit(add_new_query_dispersion);




	
	
	

} ); /* end of call to $() to make global code run when ready */


