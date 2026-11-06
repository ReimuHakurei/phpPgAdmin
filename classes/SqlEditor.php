<?php

	/**
	 * Helpers for the SQL editor (js/sqleditor.js): the tags it needs in the
	 * page head, the editor textarea and its buttons, autocompletion data
	 * and a few checks on the SQL text that sql.php needs.
	 *
	 * The textarea is a plain <textarea class="sql-editor"> that still works
	 * when JavaScript is off; js/sqleditor.js turns it into CodeMirror.
	 */
	class SqlEditor {

		// Autocompletion data is capped so huge catalogs don't bloat pages.
		const HINT_MAX_TABLES = 500;
		const HINT_MAX_COLUMNS = 1500;
		const HINT_MAX_SCHEMAS = 200;

		private static $hintCounter = 0;

		/**
		 * Returns the <link>/<script> tags the editor needs, for the $script
		 * parameter of $misc->printHeader().
		 */
		static function head() {
			$base = 'libraries/js/codemirror/';
			$out = '';
			foreach (array('lib/codemirror.css', 'addon/hint/show-hint.css') as $css)
				$out .= "<link rel=\"stylesheet\" href=\"{$base}{$css}\" type=\"text/css\" />\n";
			foreach (array('lib/codemirror.js', 'mode/sql/sql.js', 'addon/edit/matchbrackets.js',
					'addon/display/placeholder.js', 'addon/hint/show-hint.js', 'addon/hint/sql-hint.js') as $js)
				$out .= "<script type=\"text/javascript\" src=\"{$base}{$js}\"></script>\n";
			$out .= "<script type=\"text/javascript\" src=\"js/sqleditor.js\"></script>\n";
			return $out;
		}

		/**
		 * Prints the SQL textarea, plus the autocompletion data for it.
		 * @param $value The SQL to put in the editor
		 * @param $opts Optional settings:
		 *        name      - field name (default 'query')
		 *        rows      - initial height in lines (default 12)
		 *        run       - true if Ctrl+Enter should submit the form (default true)
		 *        hint      - true to add table and column names of the current
		 *                    database for autocompletion (default true)
		 *        autofocus - focus the editor on page load (default false)
		 *        error     - array('line' => n, 'col' => n) to highlight, 1-based
		 *        selection - 'fromLine:fromCh:toLine:toCh' to select after load
		 */
		static function printTextarea($value, $opts = array()) {
			global $lang;

			$name = isset($opts['name']) ? $opts['name'] : 'query';
			$rows = isset($opts['rows']) ? (int) $opts['rows'] : 12;

			$attrs = '';
			if (!isset($opts['run']) || $opts['run'])
				$attrs .= ' data-sql-run="1"';
			if (!empty($opts['autofocus']))
				$attrs .= ' autofocus="autofocus"';
			if (!empty($opts['error']['line'])) {
				$attrs .= ' data-error-line="' . (int) $opts['error']['line'] . '"';
				if (isset($opts['error']['col']))
					$attrs .= ' data-error-col="' . (int) $opts['error']['col'] . '"';
			}
			if (isset($opts['selection']) && preg_match('/^\d+:\d+:\d+:\d+$/', $opts['selection']))
				$attrs .= ' data-selection="' . $opts['selection'] . '"';

			$hint = null;
			if (!isset($opts['hint']) || $opts['hint']) {
				$hint = self::hintData();
				if ($hint !== null) {
					$hintId = 'ppa-sqlhint-' . (++self::$hintCounter);
					$attrs .= " data-sql-hint=\"{$hintId}\""
						. " data-hint-schema=\"" . htmlspecialchars($lang['strhintschema']) . "\""
						. " data-hint-in-schema=\"" . htmlspecialchars($lang['strhintinschema']) . "\"";
				}
			}

			echo "<textarea class=\"sql-editor\" style=\"width:100%;\" rows=\"{$rows}\" cols=\"80\" spellcheck=\"false\" name=\"",
				htmlspecialchars($name), "\"{$attrs}>", htmlspecialchars($value), "</textarea>\n";

			if ($hint !== null) {
				$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
				if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
				$json = json_encode($hint, $flags);
				if ($json !== false)
					echo "<script type=\"application/json\" id=\"{$hintId}\">{$json}</script>\n";
			}
		}

		/**
		 * Prints the script upload field when uploads are enabled.
		 */
		static function printUpload() {
			global $misc, $lang;

			if (!ini_get('file_uploads')) return;
			// Don't show upload option if max size of uploads is zero
			$max_size = $misc->inisizeToBytes(ini_get('upload_max_filesize'));
			if (is_double($max_size) && $max_size > 0) {
				echo "<p><input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"{$max_size}\" />\n";
				echo "<label for=\"script\">{$lang['struploadscript']}</label> <input id=\"script\" name=\"script\" type=\"file\" /></p>\n";
			}
		}

		/**
		 * Prints the "paginate results" checkbox.
		 */
		static function printPaginate($checked) {
			global $lang;

			echo "<p><label for=\"paginate\"><input type=\"checkbox\" id=\"paginate\" name=\"paginate\"",
				($checked ? ' checked="checked"' : ''), " />&nbsp;{$lang['strpaginate']}</label></p>\n";
		}

		/**
		 * Prints the Execute / Explain / Explain analyze / Reset buttons.
		 * js/sqleditor.js adds the recent queries menu to this toolbar.
		 * @param $reset Show the reset button
		 */
		static function printButtons($reset = true) {
			global $lang;

			echo "<div class=\"sql-editor-toolbar\" data-label-history=\"", htmlspecialchars($lang['strsqlhistory']),
				"\" data-label-history-empty=\"", htmlspecialchars($lang['strsqlhistoryempty']),
				"\" data-label-history-clear=\"", htmlspecialchars($lang['strsqlhistoryclear']), "\">\n";
			echo "<input type=\"submit\" name=\"execute\" accesskey=\"r\" class=\"sql-editor-execute\" value=\"",
				htmlspecialchars($lang['strexecute']), "\" title=\"", htmlspecialchars($lang['strexecutehint']),
				"\" data-label=\"", htmlspecialchars($lang['strexecute']),
				"\" data-label-selection=\"", htmlspecialchars($lang['strexecuteselection']), "\" />\n";
			echo "<input type=\"submit\" name=\"explain\" class=\"sql-editor-explain\" value=\"",
				htmlspecialchars($lang['strexplain']), "\" title=\"", htmlspecialchars($lang['strexplainhint']), "\" />\n";
			echo "<input type=\"submit\" name=\"explain_analyze\" class=\"sql-editor-explain-analyze\" value=\"",
				htmlspecialchars($lang['strexplainanalyze']), "\" title=\"", htmlspecialchars($lang['strexplainanalyzehint']), "\" />\n";
			if ($reset)
				echo "<input type=\"reset\" accesskey=\"q\" value=\"", htmlspecialchars($lang['strreset']), "\" />\n";
			echo "</div>\n";
		}

		/**
		 * Table, view and column names for autocompletion: those of the
		 * schemas in the search path first, then, if there is room, those of
		 * other non-system schemas (which only complete schema-qualified).
		 * Also the schema names: those in the search path, then the other
		 * non-system ones. Bounded by HINT_MAX_TABLES/HINT_MAX_COLUMNS/
		 * HINT_MAX_SCHEMAS. Errors are swallowed: autocompletion is optional.
		 * @return array('schemas' => array(name, ...),
		 *               'tables' => array of array(schema, name, array(column, ...), in search path 1/0)),
		 *         or null
		 */
		static function hintData() {
			global $data;

			if (!isset($_REQUEST['database']) || !isset($data) || !is_object($data)
					|| empty($data->conn->_connectionID))
				return null;
			$conn = $data->conn->_connectionID;

			// In search path order, so the JS can give unqualified names to
			// the schema PostgreSQL would pick.
			$sql = "SELECT * FROM (
					SELECT c.oid, n.nspname, c.relname,
						(SELECT i FROM pg_catalog.generate_series(1, pg_catalog.array_upper(pg_catalog.current_schemas(false), 1)) AS i
							WHERE (pg_catalog.current_schemas(false))[i] = n.nspname) AS pos
					FROM pg_catalog.pg_class c
						JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
					WHERE c.relkind IN ('r', 'v', 'm', 'f', 'p')
						AND (n.nspname = ANY (pg_catalog.current_schemas(false))
							OR (n.nspname !~ '^pg_' AND n.nspname <> 'information_schema'))
				) AS t
				ORDER BY pos IS NULL, pos, nspname, relname
				LIMIT " . self::HINT_MAX_TABLES;
			$rs = @pg_query($conn, $sql);
			if (!$rs) return null;

			$tables = array();
			$schemas = self::hintSchemas($conn);
			$order = array();
			while (($row = pg_fetch_row($rs)) !== false) {
				$tables[(int) $row[0]] = array($row[1], $row[2], array(), $row[3] === null ? 0 : 1);
				$order[] = '(' . (int) $row[0] . ',' . count($order) . ')';
			}
			pg_free_result($rs);
			if (!$tables) return array('schemas' => $schemas, 'tables' => array());

			// Columns of the first tables first, if they don't all fit
			$sql = "SELECT a.attrelid, a.attname
				FROM pg_catalog.pg_attribute a
					JOIN (VALUES " . implode(',', $order) . ") AS t (relid, ord) ON t.relid = a.attrelid
				WHERE a.attnum > 0 AND NOT a.attisdropped
				ORDER BY t.ord, a.attnum
				LIMIT " . self::HINT_MAX_COLUMNS;
			$rs = @pg_query($conn, $sql);
			if ($rs) {
				while (($row = pg_fetch_row($rs)) !== false)
					$tables[(int) $row[0]][2][] = $row[1];
				pg_free_result($rs);
			}

			return array('schemas' => $schemas, 'tables' => array_values($tables));
		}

		/**
		 * Schema names for autocompletion, search path first. Empty schemas
		 * are included: you may well be about to create something in one.
		 */
		private static function hintSchemas($conn) {
			$sql = "SELECT nspname FROM pg_catalog.pg_namespace
				WHERE nspname = ANY (pg_catalog.current_schemas(false))
					OR (nspname !~ '^pg_' AND nspname <> 'information_schema')
				ORDER BY nspname = ANY (pg_catalog.current_schemas(false)) DESC, nspname
				LIMIT " . self::HINT_MAX_SCHEMAS;
			$rs = @pg_query($conn, $sql);
			if (!$rs) return array();
			$schemas = array();
			while (($row = pg_fetch_row($rs)) !== false) $schemas[] = $row[0];
			pg_free_result($rs);
			return $schemas;
		}

		/**
		 * The prefix to add to a query for the Explain buttons, or '' when
		 * none of them was pressed.
		 */
		static function explainPrefix() {
			global $data;

			if (isset($_POST['explain_analyze'])) {
				// EXPLAIN options in parentheses (and BUFFERS) exist since 9.0
				if (isset($data->major_version) && $data->major_version < 9.0)
					return 'EXPLAIN ANALYZE ';
				return 'EXPLAIN (ANALYZE, BUFFERS) ';
			}
			if (isset($_POST['explain']))
				return 'EXPLAIN ';
			return '';
		}

		/**
		 * Strips trailing semicolons and whitespace, so a prefix like EXPLAIN
		 * applies to the (last) statement and not to an empty one.
		 */
		static function stripTrailingSemicolons($sql) {
			return preg_replace('/[\s;]+$/', '', $sql);
		}

		/**
		 * Returns the first word(s) of every statement in an SQL string,
		 * upper-cased, skipping comments and anything inside quotes.
		 * @return array of strings like 'CREATE TABLE', 'SELECT'
		 */
		static function statementHeads($sql) {
			$heads = array();
			$len = strlen($sql);
			$i = 0;
			$atStart = true;   // no word seen yet in the current statement
			$head = '';        // words collected for the current statement
			$words = 0;
			$depth = 0;        // parenthesis depth, a ';' only ends a statement at 0

			while ($i < $len) {
				$c = $sql[$i];
				$next = $i + 1 < $len ? $sql[$i + 1] : '';

				if ($c == '-' && $next == '-') {
					$end = strpos($sql, "\n", $i);
					$i = $end === false ? $len : $end + 1;
					continue;
				}
				if ($c == '/' && $next == '*') {
					// Block comments nest in PostgreSQL
					$nest = 1;
					$i += 2;
					while ($i < $len && $nest > 0) {
						if ($sql[$i] == '/' && $i + 1 < $len && $sql[$i + 1] == '*') { $nest++; $i += 2; }
						elseif ($sql[$i] == '*' && $i + 1 < $len && $sql[$i + 1] == '/') { $nest--; $i += 2; }
						else $i++;
					}
					continue;
				}
				if ($c == "'" || $c == '"') {
					// E'...' strings may contain backslash escapes
					$escapes = $c == "'" && $i > 0 && ($sql[$i - 1] == 'E' || $sql[$i - 1] == 'e');
					$i++;
					while ($i < $len) {
						if ($escapes && $sql[$i] == '\\') { $i += 2; continue; }
						if ($sql[$i] == $c) {
							if ($i + 1 < $len && $sql[$i + 1] == $c) { $i += 2; continue; }
							break;
						}
						$i++;
					}
					$i++;
					$atStart = false;
					continue;
				}
				if ($c == '$' && preg_match('/\G\$([A-Za-z_][A-Za-z0-9_]*)?\$/', $sql, $m, 0, $i)
						&& ($i == 0 || !preg_match('/[A-Za-z0-9_]/', $sql[$i - 1]))) {
					// Dollar quoting: skip to the matching tag
					$end = strpos($sql, $m[0], $i + strlen($m[0]));
					$i = $end === false ? $len : $end + strlen($m[0]);
					$atStart = false;
					continue;
				}
				if ($c == '(') { $depth++; $atStart = false; $i++; continue; }
				if ($c == ')') { if ($depth > 0) $depth--; $atStart = false; $i++; continue; }
				if ($c == ';' && $depth == 0) {
					if ($head !== '') $heads[] = $head;
					$head = '';
					$words = 0;
					$atStart = true;
					$i++;
					continue;
				}
				if (ctype_space($c)) { $i++; continue; }

				if (($atStart || $words > 0) && $words < 3 && preg_match('/\G[A-Za-z_]+/', $sql, $m, 0, $i)) {
					$head .= ($head === '' ? '' : ' ') . strtoupper($m[0]);
					$words++;
					$atStart = false;
					$i += strlen($m[0]);
					continue;
				}

				// Anything else ends the leading keywords of this statement
				$words = 3;
				$atStart = false;
				$i++;
			}
			if ($head !== '') $heads[] = $head;

			return $heads;
		}

		/**
		 * True if any statement in the SQL can change the schema, so the
		 * browser tree should be reloaded.
		 */
		static function changesSchema($sql) {
			foreach (self::statementHeads($sql) as $head) {
				if (preg_match('/^(CREATE|ALTER|DROP|COMMENT|GRANT|REVOKE|SECURITY LABEL|IMPORT FOREIGN SCHEMA)\b/', $head))
					return true;
			}
			return false;
		}

		/**
		 * Finds where a PostgreSQL error points, from the "LINE n: ..." and
		 * caret lines libpq adds to the message.
		 * @return array('line' => n, 'col' => n or null), 1-based, or null
		 */
		static function errorPosition($message) {
			if (!preg_match('/^(LINE (\d+): )(.*)\n( *)\^/m', $message, $m))
				return null;
			$col = null;
			// Long lines are cut and start with "...", then the column is unknown
			if (substr($m[3], 0, 3) != '...')
				$col = strlen($m[4]) - strlen($m[1]) + 1;
			return array('line' => (int) $m[2], 'col' => ($col !== null && $col > 0) ? $col : null);
		}
	}
?>
