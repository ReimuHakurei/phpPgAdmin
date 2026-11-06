<?php

	/**
	 * Process an arbitrary SQL query - tricky!  The main problem is that
	 * unless we implement a full SQL parser, there's no way of knowing
	 * how many SQL statements have been strung together with semi-colons
	 * @param $_SESSION['sqlquery'] The SQL query string to execute
	 *
	 * $Id: sql.php,v 1.43 2008/01/10 20:19:27 xzilla Exp $
	 */

	// Global also when included from a function (the Report plugin does that)
	global $lang, $conf, $_reload_browser, $sql_script_errors;

	// Prevent timeouts on large exports
	set_time_limit(0);

	// Include application functions
	include_once('./libraries/lib.inc.php');
	include_once('./classes/SqlEditor.php');

	/**
	 * Prints one cell of a result. Long values are clipped like on the
	 * browse page; the full value (up to a limit) is printed alongside and
	 * shown by the "show long values in full" checkbox.
	 * @return true if the value was clipped
	 */
	function sqlPrintCell($v, $type) {
		global $misc, $conf, $lang;

		$params = array('null' => true);
		if ($v === null || mb_strlen($v, 'UTF-8') <= $conf['max_chars'] + mb_strlen($lang['strellipsis'], 'UTF-8')) {
			echo '<td>', $misc->printVal($v, $type, $params), '</td>';
			return false;
		}

		$fullmax = max($conf['max_chars'] * 100, 5000);
		$full = mb_strlen($v, 'UTF-8') > $fullmax ? mb_substr($v, 0, $fullmax, 'UTF-8') . $lang['strellipsis'] : $v;
		echo '<td><div class="sql-clipped">', $misc->printVal($v, $type, array('null' => true, 'clip' => true)),
			'</div><div class="sql-full">', $misc->printVal($full, $type, $params), '</div></td>';
		return true;
	}

	/**
	 * Prints a result set as a table, at most $conf['max_result_rows'] rows.
	 * @param $names Column names
	 * @param $types Column types
	 * @param $fetch Function returning the next row as a numeric array, or false
	 * @param $total Number of rows in the result
	 */
	function sqlPrintResults($names, $types, $fetch, $total) {
		global $misc, $conf, $lang;
		static $count = 0;
		$count++;

		$max = isset($conf['max_result_rows']) ? (int) $conf['max_result_rows'] : 1000;
		$shown = ($max > 0 && $total > $max) ? $max : $total;

		ob_start();
		echo "<div class=\"data-scroll sql-results\">\n<table class=\"data-grid\">\n<thead><tr>";
		foreach ($names as $name)
			echo "<th class=\"data\">", $misc->printVal($name), "</th>";
		echo "</tr></thead>\n<tbody>\n";

		$clipped = false;
		for ($i = 0; $i < $shown && ($row = $fetch()) !== false; $i++) {
			echo "<tr class=\"data", ($i % 2 == 0 ? '1' : '2'), "\">";
			foreach ($row as $k => $v)
				$clipped = sqlPrintCell($v, $types[$k]) || $clipped;
			echo "</tr>\n";
		}
		echo "</tbody>\n</table>\n</div>\n";
		$table = ob_get_clean();

		if ($shown < $total)
			echo "<p class=\"sql-rows-capped\">", sprintf($lang['strresultrowscapped'], $shown, $total), "</p>\n";
		echo "<div class=\"sql-result\">\n";
		// The checkbox has to come before the table for the CSS to work
		if ($clipped)
			echo "<input type=\"checkbox\" class=\"sql-expand\" id=\"sql-expand-{$count}\" />",
				"<label for=\"sql-expand-{$count}\">{$lang['strexpandvalues']}</label>\n";
		echo $table;
		echo "<p>{$total} {$lang['strrows']}</p>\n</div>\n";
	}

	/**
	 * This is a callback function to display the result of each separate query
	 * @param ADORecordSet $rs The recordset returned by the script execetor
	 */
	function sqlCallback($query, $rs, $lineno) {
		global $data, $misc, $lang, $_connection, $sql_script_errors;
		// Check if $rs is false, if so then there was a fatal error
		if ($rs === false) {
			$sql_script_errors++;
			echo "<table class=\"error sql-error\"><tr><td><p><b>", htmlspecialchars($_FILES['script']['name']), ':', $lineno, ":</b></p>",
				$misc->printVal($_connection->getLastError(), 'errormsg'), "</td></tr></table>\n";
		}
		else {
			// Print query results
			switch (pg_result_status($rs)) {
				case PGSQL_TUPLES_OK:
					// If rows returned, then display the results
					$names = $types = array();
					for ($k = 0; $k < pg_num_fields($rs); $k++) {
						$names[] = pg_field_name($rs, $k);
						$types[] = pg_field_type($rs, $k);
					}
					sqlPrintResults($names, $types, function () use ($rs) { return pg_fetch_row($rs); }, pg_num_rows($rs));
					break;
				case PGSQL_COMMAND_OK:
					// Print the command completion tag
					echo "<p>", htmlspecialchars(pg_result_status($rs, PGSQL_STATUS_STRING)), "</p>\n";
					break;
				case PGSQL_EMPTY_QUERY:
					break;
				default:
					break;
			}
		}
	}

	// The Explain buttons of the SQL editor run EXPLAIN on the query. Done
	// before the query is stored, so pagination and history see what ran.
	$sql_posted_query = isset($_POST['query']) ? $_POST['query'] : null;
	if ($sql_posted_query !== null && ($sql_explain = SqlEditor::explainPrefix()) !== '') {
		$_POST['query'] = $sql_explain . SqlEditor::stripTrailingSemicolons($sql_posted_query);
		// Explain what is in the editor, not an uploaded script
		unset($_FILES['script']);
	}

	// We need to store the query in a session for editing purposes
	// We avoid GPC vars to avoid truncating long queries
	$_sqlquery = null;
	if (isset($_REQUEST['subject']) && $_REQUEST['subject'] == 'history') {
		// Or maybe we came from the history popup
		if (isset($_GET['queryid'], $_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']][$_GET['queryid']]['query']))
			$_sqlquery = $_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']][$_GET['queryid']]['query'];
	}
	elseif (isset($_POST['query']) && is_string($_POST['query'])) {
		// Or maybe we came from an sql form
		$_sqlquery = $_POST['query'];
	}

	if ($_sqlquery === null) {
		/* Nothing to run, eg. the browser reloaded or went back to this page
		 * with a GET: go to the SQL editor instead. */
		$_params = array('action' => 'sql');
		if (isset($_REQUEST['server'])) $_params['server'] = $_REQUEST['server'];
		if (isset($_REQUEST['database'])) $_params['database'] = $_REQUEST['database'];
		header('Location: sqledit.php?' . http_build_query($_params, '', '&'));
		exit;
	}

	/* Remember it under a token as well: display.php pages through it with
	 * qt=<token> in its links, so each browser tab keeps its own query.  This
	 * also sets $_SESSION['sqlquery'], which other pages still read. */
	$_REQUEST['qt'] = $misc->storeQuery($_sqlquery);

	// Pagination maybe set by a get link that has it as FALSE,
	// if that's the case, unset the variable.

	if (isset($_REQUEST['paginate']) && $_REQUEST['paginate'] == 'f') {
		unset($_REQUEST['paginate']);
		unset($_POST['paginate']);
		unset($_GET['paginate']);
	}
	// Check to see if pagination has been specified. In that case, send to display
	// script for pagination
	/* if a file is given or the request is an explain, do not paginate */
	if (isset($_REQUEST['paginate']) && !(isset($_FILES['script']) && $_FILES['script']['size'] > 0)
			&& (preg_match('/^\s*explain/i', $_SESSION['sqlquery']) == 0)) {
		include('./display.php');
		exit;
	}

	// The query that runs. What goes back into the editor is what the user
	// had there: all of it when only a selection ran, and without EXPLAIN.
	$sql_query = isset($_SESSION['sqlquery']) ? $_SESSION['sqlquery'] : '';
	if (isset($_POST['sqleditor_text']))
		$sql_editor_text = $_POST['sqleditor_text'];
	elseif ($sql_posted_query !== null)
		$sql_editor_text = $sql_posted_query;
	else
		$sql_editor_text = $sql_query;
	$sql_is_script = isset($_FILES['script']) && $_FILES['script']['size'] > 0;

	$subject = isset($_REQUEST['subject'])? $_REQUEST['subject'] : '';
	$misc->printHeader($lang['strqueryresults'], SqlEditor::head() . "<script src=\"js/grid.js\"></script>\n");
	$misc->printBody();
	$misc->printTrail('database');
	$misc->printTitle($lang['strqueryresults']);

	// Set the schema search path
	if (isset($_REQUEST['search_path'])) {
		if ($data->setSearchPath(array_map('trim',explode(',',$_REQUEST['search_path']))) != 0) {
			$misc->printFooter();
			exit;
		}
		$sql_search_path = $_REQUEST['search_path'];
	}
	else
		$sql_search_path = implode(',', $data->getSearchPath());

	// May as well try to time the query
	if (function_exists('microtime')) {
		list($usec, $sec) = explode(' ', microtime());
		$start_time = ((float)$usec + (float)$sec);
	}
	else $start_time = null;

	// Results are printed below the editor, which needs to know about errors
	$sql_error = null;
	$sql_script_errors = 0;
	ob_start();
	// Execute the query.  If it's a script upload, special handling is necessary
	if ($sql_is_script)
		$data->executeScript('script', 'sqlCallback');
	else {
		// Set fetch mode to NUM so that duplicate field names are properly returned
		$data->conn->setFetchMode(ADODB_FETCH_NUM);
		// An error in the user's SQL is not a PHP warning, and the error
		// handler's message is replaced by the one printed below.
		ob_start();
		$rs = @$data->conn->Execute($sql_query);
		ob_end_clean();

		// $rs will only be an object if there is no error
		if (is_object($rs)) {
			// Request was run, saving it in history
			if(!isset($_REQUEST['nohistory']))
				$misc->saveScriptHistory($sql_query);

			// Now, depending on what happened do various things

			// First, if rows returned, then display the results
			if ($rs->recordCount() > 0) {
				$names = $types = array();
				foreach ($rs->fields as $k => $v) {
					$finfo = $rs->fetchField($k);
					$names[] = $finfo->name;
					$types[] = $finfo->type;
				}
				sqlPrintResults($names, $types, function () use ($rs) {
					if ($rs->EOF) return false;
					$row = $rs->fields;
					$rs->moveNext();
					return $row;
				}, $rs->recordCount());
			}
			// Otherwise if any rows have been affected
			elseif ($data->conn->Affected_Rows() > 0) {
				echo "<p>", $data->conn->Affected_Rows(), " {$lang['strrowsaff']}</p>\n";
			}
			// Otherwise nodata to print
			else echo '<p>', $lang['strnodata'], "</p>\n";
		}
		else {
			$sql_error = $data->conn->ErrorMsg();
			if ($sql_error === '' || $sql_error === false) $sql_error = $lang['strsqlfailed'];
		}
	}
	$sql_results = ob_get_clean();

	// May as well try to time the query
	if ($start_time !== null) {
		list($usec, $sec) = explode(' ', microtime());
		$end_time = ((float)$usec + (float)$sec);
		// Get duration in milliseconds, round to 3dp's
		$duration = number_format(($end_time - $start_time) * 1000, 3);
	}
	else $duration = null;

	// Reload the browser only if the schema may have changed
	if ($sql_is_script || SqlEditor::changesSchema($sql_query))
		$_reload_browser = true;

	// The editor, filled with the query, to change and run it again
	echo "<form action=\"sql.php\" method=\"post\" enctype=\"multipart/form-data\" id=\"sql-editor-form\" class=\"sql-editor-form\">\n";
	echo $misc->form;
	if (isset($_REQUEST['return']))
		echo "<input type=\"hidden\" name=\"return\" value=\"", htmlspecialchars($_REQUEST['return']), "\" />\n";
	echo "<p><label>";
	$misc->printHelp($lang['strsearchpath'], 'pg.schema.search_path');
	echo ": <input type=\"text\" name=\"search_path\" size=\"50\" value=\"", htmlspecialchars($sql_search_path), "\" /></label></p>\n";
	SqlEditor::printTextarea($sql_editor_text, array(
		'rows' => 6,
		'error' => $sql_error !== null ? SqlEditor::errorPosition($sql_error) : null,
		'selection' => isset($_POST['sqleditor_selection']) ? $_POST['sqleditor_selection'] : null,
	));
	SqlEditor::printPaginate(isset($_REQUEST['paginate']));
	SqlEditor::printButtons(false);
	echo "</form>\n";

	// Outcome, then the results
	if ($sql_error !== null) {
		echo "<table class=\"error sql-error\" role=\"alert\"><tr><td><p><b>{$lang['strsqlerror']}</b></p>",
			$misc->printVal($sql_error, 'errormsg'), "</td></tr></table>\n";
	}
	echo "<p class=\"sql-status\">";
	if ($sql_error === null && $sql_script_errors == 0)
		echo "<span class=\"sql-ok\">{$lang['strsqlexecuted']}</span> ";
	else
		echo "<span class=\"sql-failed\">{$lang['strsqlfailed']}</span> ";
	// Display duration if we know it
	if ($duration !== null)
		echo sprintf($lang['strruntime'], $duration);
	echo "</p>\n";

	echo $sql_results;
	unset($sql_results);

	$navlinks = array();
	$fields = array(
		'server' => $_REQUEST['server'],
		'database' => $_REQUEST['database'],
	);

	if(isset($_REQUEST['schema']))
		$fields['schema'] = $_REQUEST['schema'];

	// Return
	if (isset($_REQUEST['return'])) {
		$urlvars = $misc->getSubjectParams($_REQUEST['return']);
		$navlinks['back'] = array (
			'attr'=> array (
				'href' => array (
					'url' => $urlvars['url'],
					'urlvars' => $urlvars['params']
				)
			),
			'content' => $lang['strback']
		);
	}

	// Edit
	$navlinks['alter'] = array (
		'attr'=> array (
			'href' => array (
				'url' => 'database.php',
				'urlvars' => array_merge($fields, array (
					'action' => 'sql',
				))
			)
		),
		'content' => $lang['streditsql']
	);

	// Create view and download
	if (isset($_SESSION['sqlquery']) && isset($rs) && is_object($rs) && $rs->recordCount() > 0) {
		// Report views don't set a schema, so we need to disable create view in that case
		if (isset($_REQUEST['schema'])) {
			$navlinks['createview'] = array (
				'attr'=> array (
					'href' => array (
						'url' => 'views.php',
						'urlvars' => array_merge($fields, array (
							'action' => 'create'
						))
					)
				),
				'content' => $lang['strcreateview']
			);
		}

		if (isset($_REQUEST['search_path']))
			$fields['search_path'] = $_REQUEST['search_path'];

		$navlinks['download'] = array (
			'attr'=> array (
				'href' => array (
					'url' => 'dataexport.php',
					'urlvars' => $fields
				)
			),
			'content' => $lang['strdownload']
		);
	}

	$misc->printNavLinks($navlinks, 'sql-form', get_defined_vars());

	$misc->printFooter();
?>
