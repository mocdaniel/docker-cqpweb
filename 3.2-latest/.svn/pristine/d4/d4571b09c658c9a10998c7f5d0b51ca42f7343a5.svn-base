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
/* First check for API mode; then print standard switched off page. */
if (isset($Config))
	if ($Config->Api)
		exit( $Config->Api->raise_error(API_ERR_SWITCHED_OFF) ? 0 : 1);
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<title>CQPweb is switched off!</title>
		<link rel="stylesheet" type="text/css" href="<?php echo ($Config->run_location == RUN_LOCATION_MAINHOME ? 'css' : '../css'); ?>/CQPweb-blue.css" >
	</head>
	<body>
	
		<table class="concordtable fullwidth">
			<tr>
				<th class="concordtable">CQPweb is currently switched off - back soon!</th> 
			</tr>
			<tr>
				<td class="concordgeneral" align="center">
					<p class="spacer">&nbsp;</p>
					
					<p>
						The CQPweb server has been switched off - this is normally done for a system repair or upgrade.
					</p>
					
					<p>
						Please wait a while and then press <b>Refresh</b> on your browser.
					</p>
					
					<p class="spacer">&nbsp;</p>
					
					<hr>
					
					<p class="spacer">&nbsp;</p>
					
					<?php if (isset($Config->cqpweb_switched_off_extra_message)) echo $Config->cqpweb_switched_off_extra_message, "\n"; ?> 

					<p class="spacer">&nbsp;</p>
				</td>
			</tr>

		</table>
		<hr>
		<table class="concordtable fullwidth">
			<tr>
				<td align="left" class="cqpweb_copynote" width="33%">
					CQPweb v<?php echo CQPWEB_VERSION; ?> &#169; 2008-2019
				</td>
				<td align="center" class="cqpweb_copynote" width="33%">
					&nbsp;
				</td>
				<td align="right" class="cqpweb_copynote" width="33%">
					&nbsp;
				</td>
			</tr>
		</table>
	</body>
</html>

