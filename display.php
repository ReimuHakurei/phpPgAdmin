<?php

	/**
	 * Common relation browsing function that can be used for views,
	 * tables, reports, arbitrary queries, etc. to avoid code duplication.
	 * @param $query The SQL SELECT string to execute
	 * @param $count The same SQL query, but only retrieves the count of the rows (AS total)
	 * @param $return The return section
	 * @param $page The current page
	 *
	 * $Id: display.php,v 1.68 2008/04/14 12:44:27 ioguix Exp $
	 */

	// Prevent timeouts on large exports
	set_time_limit(0);

	// Include application functions
	include_once('./libraries/lib.inc.php');

	global $conf, $lang;

	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : '';

	/**
	 * Rows per page: the page_size parameter if it is one of the offered sizes
	 * (remembered for the session), else the remembered one, else max_rows.
	 * @return array(page size, list of offered sizes)
	 */
	function getBrowsePageSize() {
		global $conf;

		$default = (isset($conf['max_rows']) && (int)$conf['max_rows'] > 0) ? (int)$conf['max_rows'] : 30;
		$sizes = array(25, 50, 100, 250, 500);
		if (!in_array($default, $sizes, true)) {
			$sizes[] = $default;
			sort($sizes);
		}

		if (isset($_REQUEST['page_size']) && is_string($_REQUEST['page_size'])
				&& ctype_digit($_REQUEST['page_size']) && in_array((int)$_REQUEST['page_size'], $sizes, true)) {
			$_SESSION['ppaPageSize'] = (int)$_REQUEST['page_size'];
		}

		if (isset($_SESSION['ppaPageSize']) && in_array($_SESSION['ppaPageSize'], $sizes, true))
			return array($_SESSION['ppaPageSize'], $sizes);
		return array($default, $sizes);
	}

	/**
	 * Quick filters from the request: filter[column name] => text
	 */
	function getBrowseFilters() {
		$filters = array();
		if (isset($_REQUEST['filter']) && is_array($_REQUEST['filter'])) {
			foreach ($_REQUEST['filter'] as $column => $value) {
				if (!is_string($value) || trim($value) === '') continue;
				$filters[(string)$column] = $value;
			}
		}
		return $filters;
	}

	/**
	 * Prints hidden form fields for scalar request parameters
	 * @param $fields name => value
	 * @param $skip names to leave out; a name ending in '[' leaves out that array
	 */
	function printHiddenFields($fields, $skip = array()) {
		foreach ($fields as $name => $value) {
			if ($value === null || is_array($value)) continue;
			foreach ($skip as $s) {
				if ($name === $s || (substr($s, -1) == '[' && strncmp($name, $s, strlen($s)) == 0)) continue 2;
			}
			echo "<input type=\"hidden\" name=\"", htmlspecialchars($name), "\" value=\"", htmlspecialchars($value), "\" />\n";
		}
	}

	/**
	 * Hidden fields that keep the browsing state (stored query, page size and
	 * filters) through the edit and delete row forms.
	 */
	function printBrowseStateFields() {
		$fields = array();
		foreach (array('qt', 'page_size') as $name) {
			if (isset($_REQUEST[$name]) && is_string($_REQUEST[$name])) $fields[$name] = $_REQUEST[$name];
		}
		foreach (getBrowseFilters() as $column => $value) {
			$fields["filter[{$column}]"] = $value;
		}
		printHiddenFields($fields);
	}

	/**
	 * Rounds a row estimate for display: 950, 12K, 1.2M
	 */
	function formatApproxCount($n) {
		$n = (float)$n;
		if ($n >= 1e12) { $n /= 1e12; $suffix = 'T'; }
		elseif ($n >= 1e9) { $n /= 1e9; $suffix = 'B'; }
		elseif ($n >= 1e6) { $n /= 1e6; $suffix = 'M'; }
		elseif ($n >= 1e3) { $n /= 1e3; $suffix = 'K'; }
		else return number_format($n);

		$s = number_format($n, $n < 10 ? 1 : 0);
		if (substr($s, -2) === '.0') $s = substr($s, 0, -2);
		return $s . $suffix;
	}

	/**
	 * Show confirmation of edit and perform actual update
	 */
	function doEditRow($confirm, $msg = '') {
		global $data, $misc, $conf;
		global $lang;

		if (is_array($_REQUEST['key']))
			$key = $_REQUEST['key'];
		else
			$key = safeUnserialize(urldecode($_REQUEST['key']));

		if ($confirm) {
			$misc->printTrail($_REQUEST['subject']);
			$misc->printTitle($lang['streditrow']);
			$misc->printMsg($msg);

			$attrs = $data->getTableAttributes($_REQUEST['table']);
			$rs = $data->browseRow($_REQUEST['table'], $key);

			if (($conf['autocomplete'] != 'disable')) {
				$fksprops = $misc->getAutocompleteFKProperties($_REQUEST['table']);
				if ($fksprops !== false)
					echo $fksprops['code'];
			}
			else $fksprops = false;

			echo "<form action=\"display.php\" method=\"post\" id=\"ac_form\">\n";
			$elements = 0;
			$error = true;
			if ($rs->recordCount() == 1 && $attrs->recordCount() > 0) {
				echo "<table>\n";

				// Output table header
				echo "<tr><th class=\"data\">{$lang['strcolumn']}</th><th class=\"data\">{$lang['strtype']}</th>";
				echo "<th class=\"data\">{$lang['strformat']}</th>\n";
				echo "<th class=\"data\">{$lang['strnull']}</th><th class=\"data\">{$lang['strvalue']}</th></tr>";

				$i = 0;
				while (!$attrs->EOF) {

					$attrs->fields['attnotnull'] = $data->phpBool($attrs->fields['attnotnull']);
					$id = (($i % 2) == 0 ? '1' : '2');

					// Initialise variables
					if (!isset($_REQUEST['format'][$attrs->fields['attname']]))
						$_REQUEST['format'][$attrs->fields['attname']] = 'VALUE';

					echo "<tr class=\"data{$id}\">\n";
					echo "<td style=\"white-space:nowrap;\">", $misc->printVal($attrs->fields['attname']), "</td>";
					echo "<td style=\"white-space:nowrap;\">\n";
					echo $misc->printVal($data->formatType($attrs->fields['type'], $attrs->fields['atttypmod']));
					echo "<input type=\"hidden\" name=\"types[", htmlspecialchars($attrs->fields['attname']), "]\" value=\"",
						htmlspecialchars($attrs->fields['type']), "\" /></td>";
					$elements++;
					echo "<td style=\"white-space:nowrap;\">\n";
					echo "<select name=\"format[", htmlspecialchars($attrs->fields['attname']), "]\">\n";
					echo "<option value=\"VALUE\"", (($_REQUEST['format'][$attrs->fields['attname']] ?? null) == 'VALUE') ? ' selected="selected"' : '', ">{$lang['strvalue']}</option>\n";
					echo "<option value=\"EXPRESSION\"", (($_REQUEST['format'][$attrs->fields['attname']] ?? null) == 'EXPRESSION') ? ' selected="selected"' : '', ">{$lang['strexpression']}</option>\n";
					echo "</select>\n</td>\n";
					$elements++;
					echo "<td style=\"white-space:nowrap;\">";
					// Output null box if the column allows nulls (doesn't look at CHECKs or ASSERTIONS)
					if (!$attrs->fields['attnotnull']) {
						// Set initial null values
						if (($_REQUEST['action'] ?? '') == 'confeditrow' && $rs->fields[$attrs->fields['attname']] === null) {
							$_REQUEST['nulls'][$attrs->fields['attname']] = 'on';
						}
						echo "<label><span><input type=\"checkbox\" title=\"{$lang['strnull']}\" aria-label=\"{$lang['strnull']}\" name=\"nulls[{$attrs->fields['attname']}]\"",
							isset($_REQUEST['nulls'][$attrs->fields['attname']]) ? ' checked="checked"' : '', " /></span></label></td>\n";
						$elements++;
					}
					else
						echo "&nbsp;</td>";

					echo "<td id=\"row_att_{$attrs->fields['attnum']}\" style=\"white-space:nowrap;\">";

					$extras = array();

					// If the column allows nulls, then we put a JavaScript action on the data field to unset the
					// NULL checkbox as soon as anything is entered in the field.  We use the $elements variable to
					// keep track of which element offset we're up to.  We can't refer to the null checkbox by name
					// as it contains '[' and ']' characters.
					if (!$attrs->fields['attnotnull']) {
						$extras['onChange'] = 'elements[' . ($elements - 1) . '].checked = false;';
					}

					if (($fksprops !== false) && isset($fksprops['byfield'][$attrs->fields['attnum']])) {
						$extras['id'] = "attr_{$attrs->fields['attnum']}";
						$extras['autocomplete'] = 'off';
					}

					echo $data->printField("values[{$attrs->fields['attname']}]", $rs->fields[$attrs->fields['attname']], $attrs->fields['type'], $extras);

					echo "</td>";
					$elements++;
					echo "</tr>\n";
					$i++;
					$attrs->moveNext();
				}
				echo "</table>\n";

				$error = false;
			}
			elseif ($rs->recordCount() != 1) {
				echo "<p>{$lang['strrownotunique']}</p>\n";
			}
			else {
				echo "<p>{$lang['strinvalidparam']}</p>\n";
			}

			echo "<input type=\"hidden\" name=\"action\" value=\"editrow\" />\n";
			echo $misc->form;
			if (isset($_REQUEST['table']))
				echo "<input type=\"hidden\" name=\"table\" value=\"", htmlspecialchars($_REQUEST['table']), "\" />\n";
			if (isset($_REQUEST['subject']))
				echo "<input type=\"hidden\" name=\"subject\" value=\"", htmlspecialchars($_REQUEST['subject']), "\" />\n";
			if (isset($_REQUEST['query']))
				echo "<input type=\"hidden\" name=\"query\" value=\"", htmlspecialchars($_REQUEST['query']), "\" />\n";
			if (isset($_REQUEST['count']))
				echo "<input type=\"hidden\" name=\"count\" value=\"", htmlspecialchars($_REQUEST['count']), "\" />\n";
			if (isset($_REQUEST['return']))
				echo "<input type=\"hidden\" name=\"return\" value=\"", htmlspecialchars($_REQUEST['return']), "\" />\n";
			echo "<input type=\"hidden\" name=\"page\" value=\"", htmlspecialchars($_REQUEST['page']), "\" />\n";
			echo "<input type=\"hidden\" name=\"sortkey\" value=\"", htmlspecialchars($_REQUEST['sortkey']), "\" />\n";
			echo "<input type=\"hidden\" name=\"sortdir\" value=\"", htmlspecialchars($_REQUEST['sortdir']), "\" />\n";
			echo "<input type=\"hidden\" name=\"strings\" value=\"", htmlspecialchars($_REQUEST['strings']), "\" />\n";
			echo "<input type=\"hidden\" name=\"key\" value=\"", htmlspecialchars(urlencode(serialize($key))), "\" />\n";
			printBrowseStateFields();
			echo "<p>";
			if (!$error) echo "<input type=\"submit\" name=\"save\" accesskey=\"r\" value=\"{$lang['strsave']}\" />\n";
			echo "<input type=\"submit\" name=\"cancel\" value=\"{$lang['strcancel']}\" />\n";

			if($fksprops !== false) {
				if ($conf['autocomplete'] != 'default off')
					echo "<input type=\"checkbox\" id=\"no_ac\" value=\"1\" checked=\"checked\" /><label for=\"no_ac\">{$lang['strac']}</label>\n";
				else
					echo "<input type=\"checkbox\" id=\"no_ac\" value=\"0\" /><label for=\"no_ac\">{$lang['strac']}</label>\n";
			}

			echo "</p>\n";
			echo "</form>\n";
		}
		else {
			if (!isset($_POST['values'])) $_POST['values'] = array();
			if (!isset($_POST['nulls'])) $_POST['nulls'] = array();

			$status = $data->editRow($_POST['table'], $_POST['values'], $_POST['nulls'],
				$_POST['format'], $_POST['types'], $key);
			if ($status == 0)
				doBrowse($lang['strrowupdated']);
			elseif ($status == -2)
				doEditRow(true, $lang['strrownotunique']);
			else
				doEditRow(true, $lang['strrowupdatedbad']);
		}

	}

	/**
	 * Show confirmation of drop and perform actual drop
	 */
	function doDelRow($confirm) {
		global $data, $misc;
		global $lang;

		if ($confirm) {
			$misc->printTrail($_REQUEST['subject']);
			$misc->printTitle($lang['strdeleterow']);

			$rs = $data->browseRow($_REQUEST['table'], $_REQUEST['key']);

			echo "<form action=\"display.php\" method=\"post\">\n";
			echo $misc->form;

			if ($rs->recordCount() == 1) {
				echo "<p>{$lang['strconfdeleterow']}</p>\n";

				$fkinfo = array();
				echo "<table><tr>";
					printTableHeaderCells($rs, false, true);
				echo "</tr>";
				echo "<tr class=\"data1\">\n";
					printTableRowCells($rs, $fkinfo, true);
				echo "</tr>\n";
				echo "</table>\n";
				echo "<br />\n";

				echo "<input type=\"hidden\" name=\"action\" value=\"delrow\" />\n";
				echo "<input type=\"submit\" name=\"yes\" value=\"{$lang['stryes']}\" />\n";
				echo "<input type=\"submit\" name=\"no\" value=\"{$lang['strno']}\" />\n";
			}
			elseif ($rs->recordCount() != 1) {
				echo "<p>{$lang['strrownotunique']}</p>\n";
				echo "<input type=\"submit\" name=\"cancel\" value=\"{$lang['strcancel']}\" />\n";
			}
			else {
				echo "<p>{$lang['strinvalidparam']}</p>\n";
				echo "<input type=\"submit\" name=\"cancel\" value=\"{$lang['strcancel']}\" />\n";
			}
			if (isset($_REQUEST['table']))
				echo "<input type=\"hidden\" name=\"table\" value=\"", htmlspecialchars($_REQUEST['table']), "\" />\n";
			if (isset($_REQUEST['subject']))
				echo "<input type=\"hidden\" name=\"subject\" value=\"", htmlspecialchars($_REQUEST['subject']), "\" />\n";
			if (isset($_REQUEST['query']))
				echo "<input type=\"hidden\" name=\"query\" value=\"", htmlspecialchars($_REQUEST['query']), "\" />\n";
			if (isset($_REQUEST['count']))
				echo "<input type=\"hidden\" name=\"count\" value=\"", htmlspecialchars($_REQUEST['count']), "\" />\n";
			if (isset($_REQUEST['return']))
				echo "<input type=\"hidden\" name=\"return\" value=\"", htmlspecialchars($_REQUEST['return']), "\" />\n";
			echo "<input type=\"hidden\" name=\"page\" value=\"", htmlspecialchars($_REQUEST['page']), "\" />\n";
			echo "<input type=\"hidden\" name=\"sortkey\" value=\"", htmlspecialchars($_REQUEST['sortkey']), "\" />\n";
			echo "<input type=\"hidden\" name=\"sortdir\" value=\"", htmlspecialchars($_REQUEST['sortdir']), "\" />\n";
			echo "<input type=\"hidden\" name=\"strings\" value=\"", htmlspecialchars($_REQUEST['strings']), "\" />\n";
			echo "<input type=\"hidden\" name=\"key\" value=\"", htmlspecialchars(urlencode(serialize($_REQUEST['key']))), "\" />\n";
			printBrowseStateFields();
			echo "</form>\n";
		}
		else {
			$status = $data->deleteRow($_POST['table'], safeUnserialize(urldecode($_POST['key'])));
			if ($status == 0)
				doBrowse($lang['strrowdeleted']);
			elseif ($status == -2)
				doBrowse($lang['strrownotunique']);
			else
				doBrowse($lang['strrowdeletedbad']);
		}

	}

	/* build & return the FK information data structure
	 * used when deciding if a field should have a FK link or not*/
	function &getFKInfo() {
		global $data, $misc, $lang;

		// Get the foreign key(s) information from the current table
		$fkey_information = array('byconstr' => array(), 'byfield' => array());

		if (isset($_REQUEST['table'])) {
			$constraints = $data->getConstraintsWithFields($_REQUEST['table']);
			if ($constraints->recordCount() > 0) {

				$fkey_information['common_url'] = $misc->getHREF('schema') .'&amp;subject=table';

				/* build the FK constraints data structure */
				while (!$constraints->EOF) {
					$constr =& $constraints->fields;
					if ($constr['contype'] == 'f') {

						if (!isset($fkey_information['byconstr'][$constr['conid']])) {
							$fkey_information['byconstr'][$constr['conid']] = array (
								'url_data' => 'table='. urlencode($constr['f_table']) .'&amp;schema='. urlencode($constr['f_schema']),
								'fkeys' => array(),
								'consrc' => $constr['consrc']
							);
						}

						$fkey_information['byconstr'][$constr['conid']]['fkeys'][$constr['p_field']] = $constr['f_field'];

						if (!isset($fkey_information['byfield'][$constr['p_field']]))
							$fkey_information['byfield'][$constr['p_field']] = array();

						$fkey_information['byfield'][$constr['p_field']][] = $constr['conid'];
					}
					elseif ($constr['contype'] == 'p') $fkey_information['pkey'] = $constr['p_field'];
					$constraints->moveNext();
				}
			}
		}

		return $fkey_information;
	}

	/* Columns of a record set, as shown in the grid.  Works on an empty
	 * record set too, so the header and filters still show without rows.
	 * @return array of array('key' => key in $rs->fields, 'index' => column
	 *         number from 0, 'name' => column name, 'type' => type name)
	 * */
	function getGridColumns(&$rs, $withOid) {
		global $data, $conf;
		$columns = array();

		if (is_array($rs->fields) && count($rs->fields) > 0) {
			$j = 0;
			foreach ($rs->fields as $k => $v) {
				if (($k === $data->id) && ( !($withOid && $conf['show_oids']) )) {
					$j++;
					continue;
				}
				$finfo = $rs->fetchField($j);
				$columns[] = array('key' => $k, 'index' => $j, 'name' => $finfo->name, 'type' => $finfo->type);
				$j++;
			}
		}
		else {
			for ($j = 0, $n = $rs->FieldCount(); $j < $n; $j++) {
				$finfo = $rs->fetchField($j);
				if (($finfo->name === $data->id) && ( !($withOid && $conf['show_oids']) )) continue;
				$columns[] = array('key' => $finfo->name, 'index' => $j, 'name' => $finfo->name, 'type' => $finfo->type);
			}
		}

		return $columns;
	}

	/* Print table header cells
	 * @param $args - associative array for sort link parameters
	 * */
	function printTableHeaderCells(&$rs, $args, $withOid) {
		global $misc;

		foreach (getGridColumns($rs, $withOid) as $column) {
			$j = $column['index'];

			if ($args === false) {
				echo "<th class=\"data\">", $misc->printVal($column['name']), "</th>\n";
			}
			else {
				$sorted = ($_REQUEST['sortkey'] == ($j + 1));
				$args['page'] = $_REQUEST['page'];
				$args['sortkey'] = $j + 1;
				// Sort direction opposite to current direction, unless it's currently ''
				$args['sortdir'] = (
					$_REQUEST['sortdir'] == 'asc'
					and $sorted
				) ? 'desc' : 'asc';

				$sortLink = http_build_query($args, '', '&amp;');

				$ariaSort = '';
				if ($sorted)
					$ariaSort = ($_REQUEST['sortdir'] == 'asc') ? ' aria-sort="ascending"' : ' aria-sort="descending"';

				echo "<th class=\"data\" scope=\"col\"{$ariaSort}><a href=\"display.php?{$sortLink}\">"
					, $misc->printVal($column['name']);
				if ($sorted) {
					if($_REQUEST['sortdir'] == 'asc')
						echo '<img src="'. $misc->icon('RaiseArgument') .'" alt="asc">';
					else	echo '<img src="'. $misc->icon('LowerArgument') .'" alt="desc">';
				}
				echo "</a></th>\n";
			}
		}
	}

	/* Print the quick filter cells, one text box per column
	 * @param $columns - from getGridColumns()
	 * @param $filters - column name => current filter text
	 * @param $form - id of the form the text boxes belong to
	 * */
	function printTableFilterCells($columns, $filters, $form) {
		global $lang;

		$placeholder = htmlspecialchars($lang['strfilter']);
		$help = htmlspecialchars($lang['strfilterhelp']);
		foreach ($columns as $column) {
			$name = $column['name'];
			$value = isset($filters[$name]) ? $filters[$name] : '';
			echo "<th class=\"data grid-filter-cell\"><input type=\"search\" form=\"{$form}\" name=\"",
				htmlspecialchars("filter[{$name}]"), "\" value=\"", htmlspecialchars($value),
				"\" placeholder=\"{$placeholder}\" title=\"{$help}\" aria-label=\"",
				htmlspecialchars(sprintf($lang['strfiltercolumn'], $name)), "\" /></th>\n";
		}
	}

	/* Print data-row cells */
	function printTableRowCells(&$rs, &$fkey_information, $withOid) {
		global $data, $misc, $conf, $lang;
		$j = 0;

		if (!isset($_REQUEST['strings'])) $_REQUEST['strings'] = 'collapsed';
		$clip = ($_REQUEST['strings'] == 'collapsed');

		foreach ($rs->fields as $k => $v) {
			$finfo = $rs->fetchField($j++);

			if (($k === $data->id) && ( !($withOid && $conf['show_oids']) )) continue;
			elseif ($v !== null && $v == '') echo "<td><span class=\"empty\">", htmlspecialchars($lang['strgridempty']), "</span></td>";
			else {
				/* A clipped value keeps its full text for copying it (js/grid.js),
				 * unless it is too big to repeat in the page. */
				$attrs = '';
				if ($clip && $v !== null && strlen($v) > $conf['max_chars']) {
					$text = ($finfo->type == 'bytea') ? $data->escapeBytea($v) : $v;
					if (strlen($text) <= 65536)
						$attrs = ' data-full="' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
					else
						$attrs = ' data-clipped="1"';
				}
				echo "<td style=\"white-space:nowrap;\"{$attrs}>";

				if (($v !== null) && isset($fkey_information['byfield'][$k])) {
					foreach ($fkey_information['byfield'][$k] as $conid) {

						$query_params = $fkey_information['byconstr'][$conid]['url_data'];

						foreach ($fkey_information['byconstr'][$conid]['fkeys'] as $p_field => $f_field) {
							$query_params .= '&amp;'. urlencode("fkey[{$f_field}]") .'='. urlencode($rs->fields[$p_field]);
						}

						/* $fkey_information['common_url'] is already urlencoded */
						$query_params .= '&amp;'. $fkey_information['common_url'];
						echo "<div style=\"display:inline-block;\">";
						echo "<a class=\"fk fk_". htmlentities($conid, ENT_QUOTES, 'UTF-8') ."\" href=\"display.php?{$query_params}\">";
						echo "<img src=\"".$misc->icon('ForeignKey')."\" style=\"vertical-align:middle;\" alt=\"[fk]\" title=\""
							. htmlentities($fkey_information['byconstr'][$conid]['consrc'], ENT_QUOTES, 'UTF-8')
							."\" />";
						echo "</a>";
						echo "</div>";
					}
					echo $misc->printVal($v, $finfo->type, array('null' => true, 'clip' => $clip, 'class' => 'fk_value'));
				} else {
					echo $misc->printVal($v, $finfo->type, array('null' => true, 'clip' => $clip));
				}
				echo "</td>";
			}
		}
	}

	/* Print the FK row, used in ajax requests */
	function doBrowseFK() {
		global $data, $misc, $lang;

		$ops = array();
		foreach($_REQUEST['fkey'] as $x => $y) {
			$ops[$x] = '=';
		}
		$query = $data->getSelectSQL($_REQUEST['table'], array(), $_REQUEST['fkey'], $ops);
		$_REQUEST['query'] = $query;

		$fkinfo =& getFKInfo();

		$max_pages = 1;
		// Retrieve page from query.  $max_pages is returned by reference.
		// One row is all we show, no need to count them.
		$rs = $data->browseQuery('SELECT', $_REQUEST['table'], $_REQUEST['query'],
			null, null, 1, 1, $max_pages, null, array('count' => 'none'));

		echo "<a href=\"\" style=\"display:table-cell;\" class=\"fk_delete\"><img alt=\"[delete]\" src=\"". $misc->icon('Delete') ."\" /></a>\n";
		echo "<div style=\"display:table-cell;\">";

		if (is_object($rs) && $rs->recordCount() > 0) {
			/* we are browsing a referenced table here
			 * we should show OID if show_oids is true
			 * so we give true to withOid in functions below
			 * as 3rd parameter */

			echo "<table><tr>";
				printTableHeaderCells($rs, false, true);
			echo "</tr>";
			echo "<tr class=\"data1\">\n";
				printTableRowCells($rs, $fkinfo, true);
			echo "</tr>\n";
			echo "</table>\n";
		}
		else
			echo $lang['strnodata'];

		echo "</div>";

		exit;
	}

	/**
	 * Displays requested data
	 */
	function doBrowse($msg = '') {
		global $data, $conf, $misc, $lang, $plugin_manager;

		$save_history = false;
		// If current page is not set, default to first page
		if (!isset($_REQUEST['page']) || !is_numeric($_REQUEST['page']) || (int)$_REQUEST['page'] < 1)
			$_REQUEST['page'] = 1;
		$_REQUEST['page'] = (int)$_REQUEST['page'];
		if (!isset($_REQUEST['nohistory']))
			$save_history = true;

		if (isset($_REQUEST['subject'])) {
			$subject = $_REQUEST['subject'];
			if (isset($_REQUEST[$subject])) $object = $_REQUEST[$subject];
		}
		else {
			$subject = '';
		}

		$misc->printTrail(isset($subject) ? $subject : 'database');
		$misc->printTabs($subject,'browse');

		/* This code is used when browsing FK in pure-xHTML (without js) */
		$fkey_query = null;
		if (isset($_REQUEST['fkey'])) {
			$ops = array();
			foreach($_REQUEST['fkey'] as $x => $y) {
				$ops[$x] = '=';
			}
			$fkey_query = $data->getSelectSQL($_REQUEST['table'], array(), $_REQUEST['fkey'], $ops);
			$_REQUEST['query'] = $fkey_query;
		}

		/* Find the query to browse: a query posted by a form (sql.php, the query
		 * box below, the edit row form), else the one stored under the qt token
		 * of the link that brought us here, else one given in the URL.  Results
		 * pages only fall back to the session's current query when there is
		 * nothing else: it is shared by all the browser tabs. */
		$query_sql = null;
		if ($fkey_query !== null) {
			$query_sql = $fkey_query;
		}
		elseif (isset($_POST['query']) && is_string($_POST['query']) && trim($_POST['query']) !== '') {
			$query_sql = $_POST['query'];
		}
		elseif (isset($_REQUEST['qt']) && ($stored = $misc->getStoredQuery($_REQUEST['qt'])) !== null) {
			$query_sql = $stored;
		}
		elseif (isset($_REQUEST['query']) && is_string($_REQUEST['query']) && trim($_REQUEST['query']) !== '') {
			$query_sql = $_REQUEST['query'];
		}
		elseif (!isset($object) && isset($_SESSION['sqlquery']) && trim($_SESSION['sqlquery']) !== '') {
			$query_sql = $_SESSION['sqlquery'];
		}

		if (isset($object)) {
			if ($query_sql !== null) {
				$misc->printTitle($lang['strselect']);
				$type = 'SELECT';
			}
			else {
				$type = 'TABLE';
			}
		} else {
			$misc->printTitle($lang['strqueryresults']);
			$type = 'QUERY';
		}

		// Remember the query under a token for the links of this page
		$qt = ($query_sql !== null) ? $misc->storeQuery($query_sql) : null;

		$misc->printMsg($msg);

		// If 'sortkey' is not set, default to ''
		if (!isset($_REQUEST['sortkey'])) $_REQUEST['sortkey'] = '';

		// If 'sortdir' is not set, default to ''
		if (!isset($_REQUEST['sortdir'])) $_REQUEST['sortdir'] = '';

		// If 'strings' is not set, default to collapsed
		if (!isset($_REQUEST['strings'])) $_REQUEST['strings'] = 'collapsed';

		list($page_size, $page_sizes) = getBrowsePageSize();
		$filters = getBrowseFilters();
		$count_mode = (isset($_REQUEST['count']) && $_REQUEST['count'] === 'exact') ? 'exact' : 'auto';

		// Fetch unique row identifier, if this is a table browse request.
		if (isset($object))
			$key = $data->getRowIdentifier($object);
		else
			$key = array();

		// Set the schema search path
		if (isset($_REQUEST['search_path'])) {
			if ($data->setSearchPath(array_map('trim',explode(',',$_REQUEST['search_path']))) != 0) {
				return;
			}
		}

		$fkey_information =& getFKInfo();

		/* Retrieve page from query.  $max_pages and $info are returned by
		 * reference.  A page past the end comes back as the last page. */
		$max_pages = null;
		$info = array('total' => null, 'total_type' => 'unknown', 'has_next' => false,
			'page' => $_REQUEST['page'], 'rows' => 0, 'filters' => array());
		if ($type == 'TABLE' || $query_sql !== null) {
			$rs = $data->browseQuery($type,
				isset($object) ? $object : null,
				$query_sql,
				$_REQUEST['sortkey'], $_REQUEST['sortdir'], $_REQUEST['page'],
				$page_size, $max_pages,
				isset($fkey_information['pkey']) ? $fkey_information['pkey'] : null,
				array(
					'count' => $count_mode,
					'exact_limit' => isset($conf['browse_exact_count_limit']) ? (int)$conf['browse_exact_count_limit'] : 100000,
					'filters' => $filters,
				),
				$info);
		}
		else {
			$rs = -4;
		}
		if (is_object($rs)) $_REQUEST['page'] = $info['page'];

		// Build strings for GETs in array
		$_gets = array(
			'server' => $_REQUEST['server'],
			'database' => $_REQUEST['database']
		);

		if (isset($_REQUEST['schema'])) $_gets['schema'] = $_REQUEST['schema'];
		if (isset($object)) $_gets[$subject] = $object;
		if (isset($subject)) $_gets['subject'] = $subject;
		/* Results of a query are found through the qt token: the query itself
		 * would make the links huge.  A table's SELECT keeps it, in case the
		 * token was dropped from the session. */
		if ($type == 'SELECT') $_gets['query'] = $query_sql;
		if ($qt !== null) $_gets['qt'] = $qt;
		if ($count_mode == 'exact') $_gets['count'] = 'exact';
		if (isset($_REQUEST['return'])) $_gets['return'] = $_REQUEST['return'];
		if (isset($_REQUEST['search_path'])) $_gets['search_path'] = $_REQUEST['search_path'];
		if (isset($_REQUEST['table'])) $_gets['table'] = $_REQUEST['table'];
		if (isset($_REQUEST['sortkey'])) $_gets['sortkey'] = $_REQUEST['sortkey'];
		if (isset($_REQUEST['sortdir'])) $_gets['sortdir'] = $_REQUEST['sortdir'];
		if (isset($_REQUEST['nohistory'])) $_gets['nohistory'] = $_REQUEST['nohistory'];
		$_gets['strings'] = $_REQUEST['strings'];
		$_gets['page_size'] = $page_size;
		// Keep the filters that apply to this result
		$active_filters = is_object($rs) ? $info['filters'] : $filters;
		foreach ($active_filters as $column => $value) {
			$_gets["filter[{$column}]"] = $value;
		}

		if ($save_history && is_object($rs) && ($type == 'QUERY'))
			$misc->saveScriptHistory($query_sql);

		/* The query form must carry the current context (server, database, schema,
		 * ...) in its own fields: display.php can be reached through a POST with an
		 * empty query string, eg. right after saving an edited row. */
		echo "<form method=\"post\" action=\"display.php\" class=\"sql-query-form\">\n";
		printHiddenFields($_gets, array('query', 'qt'));
		echo "<input type=\"hidden\" name=\"page\" value=\"", htmlspecialchars($_REQUEST['page']), "\" />\n";
		/* Show the query that was actually executed. */
		if ($query_sql !== null) {
			$query = $query_sql;
		} else {
			$query = "SELECT * FROM ".$data->escapeIdentifier($_REQUEST['schema'] ?? '');
			if (($_REQUEST['subject'] ?? '') == 'view') {
				$query = "{$query}.".$data->escapeIdentifier($_REQUEST['view'] ?? '').";";
			} else {
				$query = "{$query}.".$data->escapeIdentifier($_REQUEST['table'] ?? '').";";
			}
		}
		echo "<label for=\"ppa-sql-query\">", htmlspecialchars($lang['strsqlquery']), "</label><br />\n";
		echo "<textarea id=\"ppa-sql-query\" name=\"query\" rows=\"5\" cols=\"100\" class=\"sql-query\">";
		echo htmlspecialchars($query);
		echo "</textarea>\n";
		echo "<div><input type=\"submit\" value=\"", htmlspecialchars($lang['strexecute']), "\" /></div>\n";
		echo "</form>\n";

		if (is_object($rs) && $rs->FieldCount() > 0) {
			$rows = $info['rows'];

			/* "Rows 31-60 of 1,234", "of about 1.2M", or just "Rows 31-60" when
			 * the total is unknown */
			$first_row = ($info['page'] - 1) * $page_size + 1;
			$last_row = $first_row + $rows - 1;
			if ($rows == 0)
				$count_text = $lang['strnodata'];
			elseif ($info['total_type'] == 'exact')
				$count_text = sprintf($lang['strrowsrange'], number_format($first_row), number_format($last_row), number_format($info['total']));
			elseif ($info['total_type'] == 'estimate')
				$count_text = sprintf($lang['strrowsrangeabout'], number_format($first_row), number_format($last_row), formatApproxCount($info['total']));
			else
				$count_text = sprintf($lang['strrowsrangeunknown'], number_format($first_row), number_format($last_row));

			$count_link = '';
			if ($info['total_type'] != 'exact' && $rows > 0) {
				$count_link = " <a class=\"grid-count-exact\" href=\"display.php?"
					. http_build_query(array_merge($_gets, array('count' => 'exact', 'page' => $info['page'])), '', '&amp;') . "\">"
					. htmlspecialchars($info['total_type'] == 'estimate' ? $lang['strcountexactly'] : $lang['strcountrows'])
					. "</a>";
			}

			$pages_opts = array('has_next' => $info['has_next'], 'estimated' => ($info['total_type'] == 'estimate'));

			/* Page size and quick filters: one GET form, the filter boxes in the
			 * grid's header belong to it through their form attribute. */
			$grid_form = 'ppa-grid-form';
			echo "<div class=\"grid-toolbar\">\n";
			echo "<p class=\"grid-count\">", htmlspecialchars($count_text), $count_link, "</p>\n";
			echo "<form method=\"get\" action=\"display.php\" id=\"{$grid_form}\" class=\"grid-form\">\n";
			printHiddenFields($_gets, array('page_size', 'page', 'filter['));
			echo "<input type=\"hidden\" name=\"page\" value=\"1\" />\n";
			echo "<label>", htmlspecialchars($lang['strrowsperpage']), " <select name=\"page_size\" class=\"grid-page-size\">\n";
			foreach ($page_sizes as $size) {
				echo "<option value=\"{$size}\"", ($size == $page_size) ? ' selected="selected"' : '', ">{$size}</option>\n";
			}
			echo "</select></label>\n";
			echo "<input type=\"submit\" value=\"", htmlspecialchars($lang['strapplyfilters']), "\" title=\"", htmlspecialchars($lang['strfilterhelp']), "\" />\n";
			if (count($active_filters) > 0) {
				$clear = $_gets;
				foreach (array_keys($active_filters) as $column) unset($clear["filter[{$column}]"]);
				$clear['page'] = 1;
				echo "<a class=\"grid-clear-filters\" href=\"display.php?", http_build_query($clear, '', '&amp;'), "\">",
					htmlspecialchars($lang['strclearfilters']), "</a>\n";
			}
			echo "</form>\n";
			if ($rows > 0) echo "<p class=\"grid-hint\">", htmlspecialchars($lang['strcopycell']), "</p>\n";
			echo "</div>\n";

			// Show page navigation
			$misc->printPages($info['page'], $max_pages, $_gets, 20, $pages_opts);

			// Check that the key is actually in the result set.  This can occur for select
			// operations where the key fields aren't part of the select.  XXX:  We should
			// be able to support this, somehow.
			foreach ($key as $v) {
				// If a key column is not found in the record set, then we
				// can't use the key.
				if ($rows == 0 || !in_array($v, array_keys($rs->fields))) {
					$key = array();
					break;
				}
			}

			$buttons = array(
				'edit' => array (
					'content' => $lang['stredit'],
					'attr'=> array (
						'href' => array (
							'url' => 'display.php',
							'urlvars' => array_merge(array (
								'action' => 'confeditrow',
								'strings' => $_REQUEST['strings'],
								'page' => $_REQUEST['page'],
							), $_gets)
						),
						// Opens as a side panel over the grid (js/app.js)
						'data-ppa-dialog' => 'panel',
					)
				),
				'delete' => array (
					'content' => $lang['strdelete'],
					'attr'=> array (
						'href' => array (
							'url' => 'display.php',
							'urlvars' => array_merge(array (
								'action' => 'confdelrow',
								'strings' => $_REQUEST['strings'],
								'page' => $_REQUEST['page'],
							), $_gets)
						),
						'data-ppa-dialog' => 'modal',
					)
				),
			);
			$actions = array(
				'actionbuttons' => &$buttons,
				'place' => 'display-browse'
			);
			$plugin_manager->do_hook('actionbuttons', $actions);

			foreach (array_keys($actions['actionbuttons']) as $action) {
				$actions['actionbuttons'][$action]['attr']['href']['urlvars'] = array_merge(
					$actions['actionbuttons'][$action]['attr']['href']['urlvars'],
					$_gets
				);
			}

			$edit_params = isset($actions['actionbuttons']['edit'])?
				$actions['actionbuttons']['edit']:array();
			$delete_params = isset($actions['actionbuttons']['delete'])?
				$actions['actionbuttons']['delete']:array();

			/* The grid scrolls inside its own box, so that the header row can
			 * stick to its top.  js/display.js wraps table#data for the FK popups. */
			echo "<div class=\"data-scroll\">\n";
			echo "<table id=\"data\" class=\"data-grid\" data-msg-copied=\"", htmlspecialchars($lang['strcopied']),
				"\" data-msg-copied-part=\"", htmlspecialchars($lang['strcopiedpart']),
				"\" data-msg-copy-failed=\"", htmlspecialchars($lang['strcopyfailed']), "\">\n";
			echo "<thead>\n<tr>";

			// Display edit and delete actions if we have a key
			$colspan = count($buttons);
			$with_actions = ($colspan > 0 and count($key) > 0);
			if ($with_actions)
				echo "<th colspan=\"{$colspan}\" class=\"data\">{$lang['stractions']}</th>\n";

			/* we show OIDs only if we are in TABLE or SELECT type browsing */
			printTableHeaderCells($rs, $_gets, isset($object));

			echo "</tr>\n";

			// Quick filter row
			echo "<tr class=\"grid-filter\">";
			if ($with_actions)
				echo "<th colspan=\"{$colspan}\" class=\"data\"></th>\n";
			printTableFilterCells(getGridColumns($rs, isset($object)), $active_filters, $grid_form);
			echo "</tr>\n</thead>\n<tbody>\n";

			$i = 0;
			// The record set can hold one row more than the page, to tell whether there is a next page
			while (!$rs->EOF && $i < $rows) {
				$id = (($i % 2) == 0 ? '1' : '2');
				echo "<tr class=\"data{$id}\">\n";
				// Display edit and delete links if we have a key
				if ($with_actions) {
					$keys_array = array();
					$has_nulls = false;
					foreach ($key as $v) {
						$finfo = $rs->fieldTypesArray();

						if ($rs->fields[$v] === null) {
							$has_nulls = true;
							break;
						}

						foreach ($finfo as $field) {
							if ($field->name === $v) {
								if ($field->type === 'bytea') {
									$keys_array["key[{$v}]"] = $data->conn->BlobEncode($rs->fields[$v]);
								} else {
									$keys_array["key[{$v}]"] = $rs->fields[$v];
								}

							}
						}
					}
					if ($has_nulls) {
						echo "<td colspan=\"{$colspan}\">&nbsp;</td>\n";
					} else {

						if (isset($actions['actionbuttons']['edit'])) {
							$actions['actionbuttons']['edit'] = $edit_params;
							$actions['actionbuttons']['edit']['attr']['href']['urlvars'] = array_merge(
								$actions['actionbuttons']['edit']['attr']['href']['urlvars'],
								$keys_array
							);
						}

						if (isset($actions['actionbuttons']['delete'])) {
							$actions['actionbuttons']['delete'] = $delete_params;
							$actions['actionbuttons']['delete']['attr']['href']['urlvars'] = array_merge(
								$actions['actionbuttons']['delete']['attr']['href']['urlvars'],
								$keys_array
							);
						}

						foreach ($actions['actionbuttons'] as $action) {
							echo "<td class=\"opbutton{$id}\">";
							$misc->printLink($action);
							echo "</td>\n";
						}
					}
				}

				print printTableRowCells($rs, $fkey_information, isset($object));

				echo "</tr>\n";
				$rs->moveNext();
				$i++;
			}
			echo "</tbody>\n</table>\n</div>\n";

			if ($rows == 0) {
				echo "<p>{$lang['strnodata']}</p>\n";
			}
			else {
				echo "<p class=\"grid-count\">", htmlspecialchars($count_text), $count_link, "</p>\n";
				// Show page navigation
				$misc->printPages($info['page'], $max_pages, $_gets, 20, $pages_opts);
			}
		}
		else echo "<p>{$lang['strnodata']}</p>\n";

		// Navigation links
		$navlinks = array();

		$fields = array(
			'server' => $_REQUEST['server'],
			'database' => $_REQUEST['database'],
		);

		if (isset($_REQUEST['schema']))
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

		// Edit SQL link
		if ($type == 'QUERY')
			$navlinks['edit'] = array (
				'attr'=> array (
					'href' => array (
						'url' => 'database.php',
						'urlvars' => array_merge($fields, array (
							'action' => 'sql',
							'paginate' => 'on',
						))
					)
				),
				'content' => $lang['streditsql']
			);

		// Expand/Collapse
		if ($_REQUEST['strings'] == 'expanded')
			$navlinks['collapse'] = array (
				'attr'=> array (
					'href' => array (
						'url' => 'display.php',
						'urlvars' => array_merge(
							$_gets,
							array (
								'strings' => 'collapsed',
								'page' => $_REQUEST['page']
						))
					)
				),
				'content' => $lang['strcollapse']
			);
		else
			$navlinks['collapse'] = array (
				'attr'=> array (
					'href' => array (
						'url' => 'display.php',
						'urlvars' => array_merge(
							$_gets,
							array (
								'strings' => 'expanded',
								'page' => $_REQUEST['page']
						))
					)
				),
				'content' => $lang['strexpand']
			);

		// Create view and download
		if ($query_sql !== null && isset($rs) && is_object($rs) && $info['rows'] > 0) {


			// Report views don't set a schema, so we need to disable create view in that case
			if (isset($_REQUEST['schema'])) {

				$navlinks['createview'] = array (
					'attr'=> array (
						'href' => array (
							'url' => 'views.php',
							'urlvars' => array_merge($fields, array(
								'action' => 'create',
								'formDefinition' => $query_sql
							))
						)
					),
					'content' => $lang['strcreateview']
				);
			}

			// Export this query, not the session's current one (another tab may have changed it)
			$urlvars = array('query' => $query_sql);
			if (isset($_REQUEST['search_path']))
				$urlvars['search_path'] = $_REQUEST['search_path'];

			$navlinks['download'] = array (
				'attr'=> array (
					'href' => array (
						'url' => 'dataexport.php',
						'urlvars' => array_merge($fields, $urlvars)
					)
				),
				'content' => $lang['strdownload']
			);
		}

		// Insert
		if (isset($object) && (isset($subject) && $subject == 'table'))
			$navlinks['insert'] = array (
				'attr'=> array (
					'href' => array (
						'url' => 'tables.php',
						'urlvars' => array_merge($fields, array(
							'action' => 'confinsertrow',
							'table' => $object
						))
					)
				),
				'content' => $lang['strinsert']
			);

		// Refresh
		$navlinks['refresh'] = array (
			'attr'=> array (
				'href' => array (
					'url' => 'display.php',
					'urlvars' => array_merge(
						$_gets,
						array(
							'strings' => $_REQUEST['strings'],
							'page' => $_REQUEST['page']
					))
				)
			),
			'content' => $lang['strrefresh']
		);

		$misc->printNavLinks($navlinks, 'display-browse', get_defined_vars());
	}


	/* shortcuts: this function exit the script for ajax purpose */
	if ($action == 'dobrowsefk') {
		doBrowseFK();
	}

	$scripts = "<script src=\"js/display.js\" type=\"text/javascript\"></script>";
	$scripts .= "<script src=\"js/grid.js\" type=\"text/javascript\"></script>";

	$scripts .= "<script type=\"text/javascript\">\n";
	$scripts .= "var Display = {\n";
	$scripts .= "errmsg: '". str_replace("'", "\'", $lang['strconnectionfail']) ."'\n";
	$scripts .= "};\n";
	$scripts .= "</script>\n";

	// Set the title based on the subject of the request
	$title = $lang['strqueryresults'];
	if (isset($_REQUEST['subject']) && isset($_REQUEST[$_REQUEST['subject']])) {
		if ($_REQUEST['subject'] == 'table') {
			$title = $lang['strtables'].': '.$_REQUEST[$_REQUEST['subject']];
		} else if ($_REQUEST['subject'] == 'view') {
			$title = $lang['strviews'].': '.$_REQUEST[$_REQUEST['subject']];
		} else if ($_REQUEST['subject'] == 'column') {
			$title = $lang['strcolumn'].': '.$_REQUEST[$_REQUEST['subject']];
		}
	}
	$misc->printHeader($title, $scripts);

	$misc->printBody();

	switch ($action) {
		case 'editrow':
			if (isset($_POST['save'])) doEditRow(false);
			else doBrowse();
			break;
		case 'confeditrow':
			doEditRow(true);
			break;
		case 'delrow':
			if (isset($_POST['yes'])) doDelRow(false);
			else doBrowse();
			break;
		case 'confdelrow':
			doDelRow(true);
			break;
		default:
			doBrowse();
			break;
	}

	$misc->printFooter();
