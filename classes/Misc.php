<?php
	/**
	 * Class to hold various commonly used functions
	 *
	 * $Id: Misc.php,v 1.171 2008/03/17 21:35:48 ioguix Exp $
	 */

	class Misc {
		// Tracking string to include in HREFs
		public $href;
		// Tracking string to include in forms
		public $form;

		/* Constructor */
		function __construct() {
		}

		/**
		 * Checks if dumps are properly set up
		 * @param $all (optional) True to check pg_dumpall, false to just check pg_dump
		 * @return True, dumps are set up, false otherwise
		 */
		function isDumpEnabled($all = false) {
			$info = $this->getServerInfo();
			return !empty($info[$all ? 'pg_dumpall_path' : 'pg_dump_path']);
		}

		/**
		 * Sets the href tracking variable
		 */
		function setHREF() {
			$this->href = $this->getHREF();
		}

		/**
		 * Get a href query string, excluding objects below the given object type (inclusive)
		 */
		function getHREF($exclude_from = null) {
			$href = '';
			if (isset($_REQUEST['server']) && $exclude_from != 'server') {
				$href .= 'server=' . urlencode($_REQUEST['server']);
				if (isset($_REQUEST['database']) && $exclude_from != 'database') {
					$href .= '&database=' . urlencode($_REQUEST['database']);
					if (isset($_REQUEST['schema']) && $exclude_from != 'schema') {
						$href .= '&schema=' . urlencode($_REQUEST['schema']);
					}
				}
			}
			return htmlentities($href);
		}

		function getSubjectParams($subject) {
			global $plugin_manager;

			$vars = array();

			switch($subject) {
				case 'root':
					$vars = array (
						'params' => array(
							'subject' => 'root'
						)
					);
					break;
				case 'server':
					$vars = array ('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'server'
					));
					break;
				case 'role':
					$vars = array('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'role',
						'action' => 'properties',
						'rolename' => $_REQUEST['rolename']
					));
					break;
				case 'database':
					$vars = array('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'database',
						'database' => $_REQUEST['database'],
					));
					break;
				case 'schema':
					$vars = array('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'schema',
						'database' => $_REQUEST['database'],
						'schema' => $_REQUEST['schema']
					));
					break;
				case 'table':
					$vars = array('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'table',
						'database' => $_REQUEST['database'],
						'schema' => $_REQUEST['schema'],
						'table' => $_REQUEST['table']
					));
					break;
				case 'selectrows':
					$vars = array(
						'url' => 'tables.php',
						'params' => array(
							'server' => $_REQUEST['server'],
							'subject' => 'table',
							'database' => $_REQUEST['database'],
							'schema' => $_REQUEST['schema'],
							'table' => $_REQUEST['table'],
							'action' => 'confselectrows'
					));
					break;
				case 'view':
					$vars = array('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'view',
						'database' => $_REQUEST['database'],
						'schema' => $_REQUEST['schema'],
						'view' => $_REQUEST['view']
					));
					break;
				case 'fulltext':
				case 'ftscfg':
					$vars = array('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'fulltext',
						'database' => $_REQUEST['database'],
						'schema' => $_REQUEST['schema'],
						'action' => 'viewconfig',
						'ftscfg' => $_REQUEST['ftscfg']
					));
					break;
				case 'function':
					$vars = array('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'function',
						'database' => $_REQUEST['database'],
						'schema' => $_REQUEST['schema'],
						'function' => $_REQUEST['function'],
						'function_oid' => $_REQUEST['function_oid']
					));
					break;
				case 'aggregate':
					$vars = array('params' => array(
						'server' => $_REQUEST['server'],
						'subject' => 'aggregate',
						'action' => 'properties',
						'database' => $_REQUEST['database'],
						'schema' => $_REQUEST['schema'],
						'aggrname' => $_REQUEST['aggrname'],
						'aggrtype' => $_REQUEST['aggrtype']
					));
					break;
				case 'column':
					if (isset($_REQUEST['table']))
						$vars = array('params' => array(
							'server' => $_REQUEST['server'],
							'subject' => 'column',
							'database' => $_REQUEST['database'],
							'schema' => $_REQUEST['schema'],
							'table' => $_REQUEST['table'],
							'column' => $_REQUEST['column']
						));
					else
						$vars = array('params' => array(
							'server' => $_REQUEST['server'],
							'subject' => 'column',
							'database' => $_REQUEST['database'],
							'schema' => $_REQUEST['schema'],
							'view' => $_REQUEST['view'],
							'column' => $_REQUEST['column']
						));
					break;
				case 'plugin':
					$vars = array(
						'url' => 'plugin.php',
						'params' => array(
							'server' => $_REQUEST['server'],
							'subject' => 'plugin',
							'plugin' => $_REQUEST['plugin'],
					));

					if (!is_null($plugin_manager->getPlugin($_REQUEST['plugin'])))
						$vars['params'] = array_merge($vars['params'], $plugin_manager->getPlugin($_REQUEST['plugin'])->get_subject_params());
					break;
				default:
					return false;
			}

			if (!isset($vars['url']))
				$vars['url'] = 'redirect.php';

			return $vars;
		}

		function getHREFSubject($subject) {
			$vars = $this->getSubjectParams($subject);
			return "{$vars['url']}?". http_build_query($vars['params'], '', '&amp;');
		}

		/**
		 * Sets the form tracking variable
		 */
		function setForm() {
			$this->form = '';
			if (isset($_REQUEST['server'])) {
				$this->form .= "<input type=\"hidden\" name=\"server\" value=\"" . htmlspecialchars($_REQUEST['server']) . "\" />\n";
				if (isset($_REQUEST['database'])) {
					$this->form .= "<input type=\"hidden\" name=\"database\" value=\"" . htmlspecialchars($_REQUEST['database']) . "\" />\n";
					if (isset($_REQUEST['schema'])) {
						$this->form .= "<input type=\"hidden\" name=\"schema\" value=\"" . htmlspecialchars($_REQUEST['schema']) . "\" />\n";
					}
				}
			}
		}

		/**
		 * Render a value into HTML using formatting rules specified
		 * by a type name and parameters.
		 *
		 * @param $str The string to change
		 *
		 * @param $type Field type (optional), this may be an internal PostgreSQL type, or:
		 *			yesno    - same as bool, but renders as 'Yes' or 'No'.
		 *			pre      - render in a <pre> block.
		 *			nbsp     - replace all spaces with &nbsp;'s
		 *			verbatim - render exactly as supplied, no escaping what-so-ever.
		 *			callback - render using a callback function supplied in the 'function' param.
		 *
		 * @param $params Type parameters (optional), known parameters:
		 *			null     - string to display if $str is null, or set to TRUE to use a default 'NULL' string,
		 *			           otherwise nothing is rendered.
		 *			clip     - if true, clip the value to a fixed length, and append an ellipsis...
		 *			cliplen  - the maximum length when clip is enabled (defaults to $conf['max_chars'])
		 *			ellipsis - the string to append to a clipped value (defaults to $lang['strellipsis'])
		 *			tag      - an HTML element name to surround the value.
		 *			class    - a class attribute to apply to any surrounding HTML element.
		 *			align    - an align attribute ('left','right','center' etc.)
		 *			true     - (type='bool') the representation of true.
		 *			false    - (type='bool') the representation of false.
		 *			function - (type='callback') a function name, accepts args ($str, $params) and returns a rendering.
		 *			lineno   - prefix each line with a line number.
		 *			map      - an associative array.
		 *
		 * @return string The HTML rendered value
		 */
		function printVal($str, $type = null, $params = array()) {
			global $lang, $conf, $data;

			// Shortcircuit for a NULL value
			if (is_null($str))
				return isset($params['null'])
						? ($params['null'] === true ? '<span class="null">NULL</span>' : $params['null'])
						: '';

			if (isset($params['map']) && isset($params['map'][$str])) $str = $params['map'][$str];

			// Clip the value if the 'clip' parameter is true.
			if (isset($params['clip']) && $params['clip'] === true) {
				$maxlen = isset($params['cliplen']) && is_integer($params['cliplen']) ? $params['cliplen'] : $conf['max_chars'];
				$ellipsis = isset($params['ellipsis']) ? $params['ellipsis'] : $lang['strellipsis'];
				if (mb_strlen($str, 'UTF-8') > $maxlen + mb_strlen($ellipsis)) {
					$full_str = $ellipsis . mb_substr($str, $maxlen-1, $maxlen*8, 'UTF-8');
					$str = mb_substr($str, 0, $maxlen-1, 'UTF-8') . $ellipsis;
				}
			}

			$out = '';

			switch ($type) {
				case 'int2':
				case 'int4':
				case 'int8':
				case 'float4':
				case 'float8':
				case 'money':
				case 'numeric':
				case 'oid':
				case 'xid':
				case 'cid':
				case 'tid':
					$align = 'right';
					$out = nl2br(htmlspecialchars($str));
					break;
				case 'yesno':
					if (!isset($params['true'])) $params['true'] = $lang['stryes'];
					if (!isset($params['false'])) $params['false'] = $lang['strno'];
					// No break - fall through to boolean case.
				case 'bool':
				case 'boolean':
					if (is_bool($str)) $str = $str ? 't' : 'f';
					switch ($str) {
						case 't':
							$out = (isset($params['true']) ? $params['true'] : $lang['strtrue']);
							$align = 'center';
							break;
						case 'f':
							$out = (isset($params['false']) ? $params['false'] : $lang['strfalse']);
							$align = 'center';
							break;
						default:
							$out = htmlspecialchars($str);
					}
					break;
				case 'bytea':
					$tag = 'div';
					$class = 'pre';
					$out = $data->escapeBytea($str);
					break;
				case 'macaddr':
					$tag = 'tt';
					$out = $str;
					break;
				case 'errormsg':
					$tag = 'pre';
					$class = 'error';
					$out = htmlspecialchars($str);
					break;
				case 'pre':
					$tag = 'pre';
					$out = htmlspecialchars($str);
					break;
				case 'prenoescape':
					$tag = 'pre';
					$out = $str;
					break;
				case 'nbsp':
					$out = nl2br(str_replace(' ', '&nbsp;', htmlspecialchars($str)));
					break;
				case 'verbatim':
					$out = $str;
					break;
				case 'callback':
					$out = $params['function']($str, $params);
					break;
				case 'prettysize':
					if ($str == -1)
						$out = $lang['strnoaccess'];
					else
					{
						$limit = 10 * 1024;
						$mult = 1;
						if ($str < $limit * $mult)
							$out = $str.' '.$lang['strbytes'];
						else
						{
							$mult *= 1024;
							if ($str < $limit * $mult)
								$out = floor(($str + $mult / 2) / $mult).' '.$lang['strkb'];
							else
							{
								$mult *= 1024;
								if ($str < $limit * $mult)
									$out = floor(($str + $mult / 2) / $mult).' '.$lang['strmb'];
								else
								{
									$mult *= 1024;
									if ($str < $limit * $mult)
										$out = floor(($str + $mult / 2) / $mult).' '.$lang['strgb'];
									else
									{
										$mult *= 1024;
										if ($str < $limit * $mult)
											$out = floor(($str + $mult / 2) / $mult).' '.$lang['strtb'];
									}
								}
							}
						}
					}
					break;
				default:
					// If the string contains at least one instance of >1 space in a row, a tab
					// character, a space at the start of a line, or a space at the start of
					// the whole string then render within a pre-formatted element (<pre>).
					if (preg_match('/(^ |  |\t|\n )/m', $str)) {
						$tag = 'pre';
						$class = 'data';
						$out = htmlspecialchars($str);
						if (isset($full_str)) $out = '<abbr title="' . htmlspecialchars($full_str) . "\">$out</abbr>";
					} else {
						$out = nl2br(htmlspecialchars($str));
						if (isset($full_str)) $out = '<abbr title="' . nl2br(htmlspecialchars($full_str)) . "\">$out</abbr>";
					}
			}

			if (isset($params['class'])) $class = $params['class'];
			if (isset($params['align'])) $align = $params['align'];

			if (!isset($tag) && (isset($class) || isset($align))) $tag = 'div';

			if (isset($tag)) {
				$alignattr = isset($align) ? " style=\"text-align: {$align}\"" : '';
				$classattr = isset($class) ? " class=\"{$class}\"" : '';
				$out = "<{$tag}{$alignattr}{$classattr}>{$out}</{$tag}>";
			}

			// Add line numbers if 'lineno' param is true
			if (isset($params['lineno']) && $params['lineno'] === true) {
				$lines = explode("\n", $str);
				$num = count($lines);
			if ($num > 0) {
					if (!isset($class)) $class = '';
					$temp = "<table>\n<tr><td class=\"{$class}\" style=\"vertical-align: top; padding-right: 10px;\"><pre class=\"{$class}\">";
					for ($i = 1; $i <= $num; $i++) {
						$temp .= $i . "\n";
					}
					$temp .= "</pre></td><td class=\"{$class}\" style=\"vertical-align: top;\">{$out}</td></tr></table>\n";
					$out = $temp;
				}
				unset($lines);
			}

			return $out;
		}

		/**
		 * Print out the page heading and help link
		 * @param $title Title, already escaped
		 * @param $help (optional) The identifier for the help link
		 */
		function printTitle($title, $help = null) {
			global $data, $lang;

			echo "<h2>";
			$this->printHelp($title, $help);
			echo "</h2>\n";
		}

		/**
		 * Print out a message
		 * @param $msg The message to print
		 */
		function printMsg($msg) {
			if ($msg == '') return;
			$error = $this->isErrorMessage($msg);
			echo "<p class=\"message", ($error ? ' error' : ''), "\" role=\"", ($error ? 'alert' : 'status'), "\">{$msg}</p>\n";
		}

		/** Messages from the language file that report a failure, see isErrorMessage(). */
		private $errorMessages = null;

		/**
		 * Tells failure messages ("Table creation failed.") from success
		 * messages, so they can be shown differently. Pages pass the
		 * translated text, so this looks it up by the language keys that
		 * name failures.
		 */
		function isErrorMessage($msg) {
			global $lang;

			if ($this->errorMessages === null) {
				$this->errorMessages = array();
				foreach ($lang as $key => $text) {
					if (is_string($text) && $text !== ''
						&& preg_match('/(bad|failed|needs|error|invalid|notfound|disallowed|specifytable)/', $key)) {
						$this->errorMessages[] = $text;
					}
				}
			}
			$msg = (string) $msg;
			foreach ($this->errorMessages as $text) {
				if (strncmp($msg, $text, strlen($text)) === 0) return true;
			}
			return false;
		}

		/**
		 * Creates a database accessor
		 */
		function getDatabaseAccessor($database, $server_id = null) {
			global $lang, $conf, $misc, $_connection, $postgresqlMinVer;

			$server_info = $this->getServerInfo($server_id);

			// Perform extra security checks if this config option is set
			if ($conf['extra_login_security']) {
				// Disallowed logins if extra_login_security is enabled.
				// These must be lowercase.
				$bad_usernames = array('pgsql', 'postgres', 'root', 'administrator');

				$username = strtolower($server_info['username']);

				if ($server_info['password'] == '' || in_array($username, $bad_usernames)) {
					unset($_SESSION['webdbLogin'][$_REQUEST['server']]);
					$msg = $lang['strlogindisallowed'];
					include('./login.php');
					exit;
				}
			}

			// Session settings travel in the connection's startup packet (via
			// libpq's environment variables, so the host:port handling stays as
			// it is) instead of costing a SET statement each after connecting.
			// We work on UTF-8 only encoding.
			$startup = array('PGCLIENTENCODING' => 'UTF8');
			$options = array('DateStyle=ISO');
			// bytea_output only exists from 9.0 on, and the version is only
			// known once we have connected to this server before.
			$bytea_at_startup = isset($server_info['pgVersion'])
				&& version_compare($server_info['pgVersion'], '9.0', '>=');
			if ($bytea_at_startup)
				$options[] = 'bytea_output=escape';
			if (isset($conf['statement_timeout']) && $conf['statement_timeout'] !== '') {
				$_timeout = preg_replace('/\s+/', '', (string) $conf['statement_timeout']);
				if (preg_match('/^[0-9]+(ms|s|min|h|d)?$/', $_timeout))
					$options[] = "statement_timeout={$_timeout}";
				else
					trigger_error("Ignoring invalid \$conf['statement_timeout']", E_USER_WARNING);
			}
			$_prev_options = getenv('PGOPTIONS');
			$startup['PGOPTIONS'] = trim(($_prev_options === false ? '' : $_prev_options) . ' -c ' . implode(' -c ', $options));

			$_saved_env = array();
			foreach ($startup as $_name => $_value) {
				$_saved_env[$_name] = getenv($_name);
				putenv("{$_name}={$_value}");
			}

			// Create the connection object and make the connection
			$_connection = new Connection(
				$server_info['host'],
				$server_info['port'],
				$server_info['sslmode'],
				$server_info['username'],
				$server_info['password'],
				$database
			);

			// Don't hand these to child processes (pg_dump).
			foreach ($_saved_env as $_name => $_value)
				putenv($_value === false ? $_name : "{$_name}={$_value}");

			// Get the name of the database driver we need to use.
			// The description of the server is returned in $platform.
			$_type = $_connection->getDriver($platform);
			if ($_type === null) {
				printf($lang['strpostgresqlversionnotsupported'], $postgresqlMinVer);
				exit;
			}
			$this->setServerInfo('platform', $platform, $server_id);
			// Without the detailed info: that would cost a "select version()".
			$this->setServerInfo('pgVersion', $_connection->conn->ServerInfo(false)['version'], $server_id);

			// Create a database wrapper class for easy manipulation of the
			// connection.
			include_once('./classes/database/' . $_type . '.php');
			$data = new $_type($_connection->conn);
			$data->platform = $_connection->platform;

			// Only needed when the startup settings above didn't apply.
			if (pg_parameter_status($_connection->conn->_connectionID, 'client_encoding') !== 'UTF8') {
				$data->execute("SET client_encoding TO 'UTF-8'");
			}

			if ($data->hasByteaHexDefault() && !$bytea_at_startup) {
				$data->execute("SET bytea_output TO escape");
			}

			return $data;
		}


		/**
		 * Prints the page header.  If global variable $_no_output is
		 * set then no header is drawn.
		 * @param $title The title of the page
		 * @param $script script tag
		 */
		/**
		 * True when the page was requested by the app shell (js/app.js) to
		 * swap into the main area. Such responses leave out the sidebar.
		 */
		function isPartialRequest() {
			return ($_SERVER['HTTP_X_PPA_PARTIAL'] ?? '') === '1';
		}

		/**
		 * URL of a static asset with the app version appended, so browsers
		 * can cache it for a long time and still pick up a new release.
		 */
		function asset($path) {
			global $appVersion;
			return $path . '?v=' . rawurlencode($appVersion);
		}

		function printHeader($title = '', $script = null) {
			global $appName, $lang, $_no_output, $conf, $plugin_manager;

			if (!isset($_no_output)) {
				header("Content-Type: text/html; charset=utf-8");
				// The response differs with and without the sidebar.
				header("Vary: X-PPA-Partial", false);

				echo "<!DOCTYPE html>\n";
				echo "<html lang=\"", htmlspecialchars($lang['applocale']), "\"";
				if (strcasecmp($lang['applangdir'], 'ltr') != 0) echo " dir=\"", htmlspecialchars($lang['applangdir']), "\"";
				echo ">\n";

				$_formatTitle = '';
				if (!empty($title)) {
					$_formatTitle = htmlspecialchars($appName . ' - ' . $title);
				} else {
					$_formatTitle = htmlspecialchars($appName);
				}

				$theme = htmlspecialchars($conf['theme']);
				$css = htmlspecialchars($this->asset("themes/{$conf['theme']}/global.css"));
				// Not every theme has its own icons.
				$favicon = is_file("images/themes/{$conf['theme']}/Favicon.ico")
					? "images/themes/{$theme}/Favicon.ico" : 'images/themes/default/Favicon.ico';
				$appicon = htmlspecialchars($this->icon('Introduction'));
				$jquery = htmlspecialchars($this->asset('libraries/js/jquery.js'));
				$appjs = htmlspecialchars($this->asset('js/app.js'));
				$treejs = htmlspecialchars($this->asset('js/tree.js'));

				echo <<<EOL
				<head>
					<meta charset="utf-8" />
					<meta name="viewport" content="width=device-width, initial-scale=1" />
					<link rel="stylesheet" href="{$css}" id="csstheme" data-theme="{$theme}" />
					<link rel="shortcut icon" href="{$favicon}" type="image/vnd.microsoft.icon" />
					<link rel="icon" type="image/png" href="{$appicon}" />
					<script src="{$jquery}"></script>
					<script src="{$appjs}"></script>
					<script src="{$treejs}"></script>
					<title>{$_formatTitle}</title>

				EOL;

				if ($script) {
					// Version our own scripts and stylesheets like the ones above.
					$script = preg_replace_callback('/\b(src|href)="((?:js|libraries\/js|themes|plugins)\/[^"?]+\.(?:js|css)|[a-z_]+\.js)"/',
						function ($m) { return $m[1] . '="' . htmlspecialchars($this->asset($m[2])) . '"'; }, $script);
					echo "{$script}\n";
				}

				$plugins_head = array();
				$_params = array('heads' => &$plugins_head);

				$plugin_manager->do_hook('head', $_params);

				foreach($plugins_head as $tag) {
					echo $tag;
				}

				echo "</head>\n";
			}
		}

		/**
		 * Prints the page footer
		 * @param $doBody True to output body tag, false otherwise
		 */
		function printFooter($doBody = true) {
			global $_reload_browser, $_reload_drop_database;
			global $lang, $_no_bottom_link;

			if ($doBody) {
				// A full page load builds the tree from scratch anyway, so
				// only a swapped-in page has to ask for a refresh.
				if ($this->isPartialRequest()) {
					if (isset($_reload_browser)) $this->printReload(false);
					elseif (isset($_reload_drop_database)) $this->printReload(true);
				}
				if (!isset($_no_bottom_link))
					echo "<a href=\"#\" class=\"bottom_link\">".$lang['strgotoppage']."</a>";

				if ($this->shellOpen) {
					echo "</main>\n</div>\n";
					$this->shellOpen = false;
				}
				echo "</body>\n";
			}
			echo "</html>\n";
		}

		/** True between printBody() and printFooter() when the app shell is open. */
		public $shellOpen = false;

		/**
		 * Prints the page body, and around it the app shell: the sidebar with
		 * the object tree and the main area the page content goes into.
		 * Set global $_no_shell for pages that are shown on their own (help
		 * popups and the like).
		 * @param $bodyClass - name of body class
		 * @param $doBody True to output body tag, false otherwise
		 */
		function printBody($bodyClass = '', $doBody = true ) {
			global $_no_output, $_no_shell, $lang, $conf;

			if (!isset($_no_output)) {
				if ($doBody) {
					if (!isset($_no_shell)) $bodyClass = trim("ppa-shell {$bodyClass}");
					$bodyClass = htmlspecialchars($bodyClass);
					echo "<body", ($bodyClass == '' ? '' : " class=\"{$bodyClass}\"");
					echo ">\n";

					if (!isset($_no_shell)) {
						$width = (int) ($conf['left_width'] ?? 200);
						if ($width < 120) $width = 200;
						echo "<div id=\"ppa-app\" style=\"--ppa-sidebar-width: {$width}px\" data-load-error=\"",
							htmlspecialchars($lang['strpageloaderror']), "\" data-close=\"", htmlspecialchars($lang['strclose']),
							"\" data-discard=\"", htmlspecialchars($lang['strdiscardchanges']), "\">\n";
						if (!$this->isPartialRequest()) $this->printSidebar();
						echo "<main id=\"ppa-main\" tabindex=\"-1\">\n";
						$this->shellOpen = true;
					}
				}
			}
		}

		/**
		 * Prints the sidebar: app name, tree filter and the object tree.
		 * The tree itself is built in the browser by js/tree.js from the
		 * same XML the old frame-based tree used.
		 */
		function printSidebar() {
			global $appName, $appVersion, $lang, $conf;

			$cfg = array(
				'src' => 'servers.php?action=tree',
				'rootText' => $lang['strservers'],
				'rootAction' => 'servers.php',
				'rootIcon' => $this->icon('Servers'),
				'loadingText' => $lang['strloading'],
				'errorText' => $lang['strerrorloading'],
				'errorIcon' => $this->icon('ObjectNotFound'),
				'noMatchText' => $lang['strnotreematch'],
			);
			echo "<aside id=\"ppa-sidebar\" aria-label=\"", htmlspecialchars($lang['strobjectbrowser']), "\">\n";
			// "logo" picks up each theme's logo styling.
			echo "\t<div class=\"logo ppa-brand\"><a href=\"intro.php\" title=\"", htmlspecialchars("{$appName} {$appVersion}"), "\">",
				htmlspecialchars($appName), "</a></div>\n";
			echo "\t<div class=\"ppa-tree-tools\">\n";
			echo "\t\t<input type=\"search\" id=\"ppa-tree-filter\" placeholder=\"", htmlspecialchars($lang['strfiltertree']),
				" (Ctrl+K)\" aria-label=\"", htmlspecialchars($lang['strfiltertree']), "\" autocomplete=\"off\" spellcheck=\"false\" />\n";
			echo "\t\t<button type=\"button\" id=\"ppa-tree-refresh\" title=\"", htmlspecialchars($lang['strrefresh']), "\" aria-label=\"",
				htmlspecialchars($lang['strrefresh']), "\"><img src=\"", htmlspecialchars($this->icon('Refresh')), "\" alt=\"\" /></button>\n";
			echo "\t</div>\n";
			echo "\t<div id=\"ppa-tree\" data-config=\"", htmlspecialchars(json_encode($cfg)), "\"></div>\n";
			echo "\t<div id=\"ppa-sidebar-resize\" role=\"separator\" aria-orientation=\"vertical\" title=\"", htmlspecialchars($lang['strresizesidebar']), "\"></div>\n";
			echo "</aside>\n";
			echo "<button type=\"button\" id=\"ppa-sidebar-toggle\" aria-controls=\"ppa-sidebar\" aria-expanded=\"false\" title=\"",
				htmlspecialchars($lang['strtogglesidebar']), "\"><span aria-hidden=\"true\">&#9776;</span></button>\n";
		}

		/**
		 * Outputs JavaScript code that will reload the browser
		 * @param $database True if dropping a database, false otherwise
		 */
		function printReload($database) {
			echo "<script>window.ppa && ppa.reloadTree(", ($database ? 'true' : 'false'), ");</script>\n";
		}

		/**
		 * Display a link
		 * @param $link An associative array of link parameters to print
		 * 	link = array(
		 * 		'attr' => array( // list of A tag attribute
		 * 			'attrname' => attribute value
		 * 			...
		 * 		),
		 *		'content' => The link text
		 * 		'fields' => (optional) the data from which content and attr's values are obtained
		 * 	);
		 * the special attribute 'href' might be a string or an array. If href is an array it
		 * will be generated by getActionUrl. See getActionUrl comment for array format.
		 */
		function printLink($link) {
			if (! isset($link['fields']))
				$link['fields'] = $_REQUEST;

			$tag = "<a ";
			foreach ($link['attr'] as $attr => $value) {
				if ($attr == 'href' and is_array($value)) {
					$tag.= 'href="'. htmlentities($this->getActionUrl($value, $link['fields'])).'" ';
				}
				else {
					$tag.= htmlentities($attr).'="'. value($value, $link['fields'], 'html') .'" ';
				}
			}
			$tag.= ">". value($link['content'], $link['fields'], 'html') ."</a>\n";
			echo $tag;
		}

		/**
		 * Display a list of links
		 * @param $links An associative array of links to print. See printLink function for
		 *               the links array format.
		 * @param $class An optional class or list of classes seprated by a space
		 *   WARNING: This field is NOT escaped! No user should be able to inject something here, use with care.
		 */
		function printLinksList($links, $class='') {
			echo "<ul class=\"{$class}\">\n";
			foreach ($links as $link) {
				echo "\t<li>";
				$this->printLink($link);
				echo "</li>\n";
			}
			echo "</ul>\n";
		}

		/**
		 * Display navigation tabs
		 * @param $tabs The name of current section (Ex: intro, server, ...), or an array with tabs (Ex: sqledit.php doFind function)
		 * @param $activetab The name of the tab to be highlighted.
		 */
		function printTabs($tabs, $activetab) {
			global $misc, $conf, $data, $lang;

			if (is_string($tabs)) {
				$_SESSION['webdbLastTab'][$tabs] = $activetab;
				$tabs = $this->getNavTabs($tabs);
			}

			echo "<nav class=\"tabs\" aria-label=\"", htmlspecialchars($lang['strsections']), "\"><ul>\n";

			foreach ($tabs as $tab_id => $tab) {
				$isActive = ($tab_id == $activetab);

				if (!isset($tab['hide']) || $tab['hide'] !== true) {
					$tablink = '<a href="' . htmlentities($this->getActionUrl($tab, $_REQUEST)) . '"'
						. ($isActive ? ' aria-current="page"' : '') . '>';

					// The label says it all; the icon is decoration.
					if (isset($tab['icon']) && $icon = $this->icon($tab['icon']))
						$tablink .= "<span class=\"icon\"><img src=\"{$icon}\" alt=\"\" /></span>";

					$tablink .= "<span class=\"label\">{$tab['title']}</span></a>";

					echo "<li class=\"tab", ($isActive ? ' active' : ''), "\">";

					if (isset($tab['help']))
						$this->printHelp($tablink, $tab['help']);
					else
						echo $tablink;

					echo "</li>\n";
				}
			}

			echo "</ul></nav>\n";
		}

		/**
		 * Retrieve the tab info for a specific tab bar.
		 * @param $section The name of the tab bar.
		 */
		function getNavTabs($section) {
			global $data, $lang, $conf, $plugin_manager;

			$hide_advanced = ($conf['show_advanced'] === false);
			$tabs = array();

			switch ($section) {
				case 'root':
					$tabs = array (
						'intro' => array (
							'title' => $lang['strintroduction'],
							'url'   => "intro.php",
							'icon'  => 'Introduction',
						),
						'servers' => array (
							'title' => $lang['strservers'],
							'url'   => "servers.php",
							'icon'  => 'Servers',
						),
					);
					break;

				case 'server':
					$hide_users = !$data->isSuperUser();
					$tabs = array (
						'databases' => array (
							'title' => $lang['strdatabases'],
							'url'   => 'all_db.php',
							'urlvars' => array('subject' => 'server'),
							'help'  => 'pg.database',
							'icon'  => 'Databases',
						)
					);
					if ($data->hasRoles()) {
						$tabs = array_merge($tabs, array(
							'roles' => array (
								'title' => $lang['strroles'],
								'url'   => 'roles.php',
								'urlvars' => array('subject' => 'server'),
								'hide'  => $hide_users,
								'help'  => 'pg.role',
								'icon'  => 'Roles',
							)
						));
					}
					else {
						$tabs = array_merge($tabs, array(
							'users' => array (
								'title' => $lang['strusers'],
								'url'   => 'users.php',
								'urlvars' => array('subject' => 'server'),
								'hide'  => $hide_users,
								'help'  => 'pg.user',
								'icon'  => 'Users',
							),
							'groups' => array (
								'title' => $lang['strgroups'],
								'url'   => 'groups.php',
								'urlvars' => array('subject' => 'server'),
								'hide'  => $hide_users,
								'help'  => 'pg.group',
								'icon'  => 'UserGroups',
							)
						));
					}

					$tabs = array_merge($tabs, array(
						'account' => array (
							'title' => $lang['straccount'],
							'url'   => $data->hasRoles() ? 'roles.php' : 'users.php',
							'urlvars' => array('subject' => 'server', 'action' => 'account'),
							'hide'  => !$hide_users,
							'help'  => 'pg.role',
							'icon'  => 'User',
						),
						'tablespaces' => array (
							'title' => $lang['strtablespaces'],
							'url'   => 'tablespaces.php',
							'urlvars' => array('subject' => 'server'),
							'hide'  => (!$data->hasTablespaces()),
							'help'  => 'pg.tablespace',
							'icon'  => 'Tablespaces',
						),
						'export' => array (
							'title' => $lang['strexport'],
							'url'   => 'all_db.php',
							'urlvars' => array('subject' => 'server', 'action' => 'export'),
							'hide'  => (!$this->isDumpEnabled()),
							'icon'  => 'Export',
						),
					));
					break;
				case 'database':
					$tabs = array (
						'schemas' => array (
							'title' => $lang['strschemas'],
							'url'   => 'schemas.php',
							'urlvars' => array('subject' => 'database'),
							'help'  => 'pg.schema',
							'icon'  => 'Schemas',
						),
						'sql' => array (
							'title' => $lang['strsql'],
							'url'   => 'database.php',
							'urlvars' => array('subject' => 'database', 'action' => 'sql', 'new' => 1),
							'help'  => 'pg.sql',
							'tree'  => false,
							'icon'  => 'SqlEditor'
						),
						'find' => array (
							'title' => $lang['strfind'],
							'url'   => 'database.php',
							'urlvars' => array('subject' => 'database', 'action' => 'find'),
							'tree'  => false,
							'icon'  => 'Search'
						),
						'variables' => array (
							'title' => $lang['strvariables'],
							'url'   => 'database.php',
							'urlvars' => array('subject' => 'database', 'action' => 'variables'),
							'help'  => 'pg.variable',
							'tree'  => false,
							'icon'  => 'Variables',
						),
						'processes' => array (
							'title' => $lang['strprocesses'],
							'url'   => 'database.php',
							'urlvars' => array('subject' => 'database', 'action' => 'processes'),
							'help'  => 'pg.process',
							'tree'  => false,
							'icon'  => 'Processes',
						),
						'locks' => array (
							'title' => $lang['strlocks'],
							'url'   => 'database.php',
							'urlvars' => array('subject' => 'database', 'action' => 'locks'),
							'help'  => 'pg.locks',
							'tree'  => false,
							'icon'  => 'Key',
						),
						'admin' => array (
							'title' => $lang['stradmin'],
							'url'   => 'database.php',
							'urlvars' => array('subject' => 'database', 'action' => 'admin'),
							'tree'  => false,
							'icon'  => 'Admin',
						),
						'privileges' => array (
							'title' => $lang['strprivileges'],
							'url'   => 'privileges.php',
							'urlvars' => array('subject' => 'database'),
							'hide'  => (!isset($data->privlist['database'])),
							'help'  => 'pg.privilege',
							'tree'  => false,
							'icon'  => 'Privileges',
						),
						'languages' => array (
							'title' => $lang['strlanguages'],
							'url'   => 'languages.php',
							'urlvars' => array('subject' => 'database'),
							'hide'  => $hide_advanced,
							'help'  => 'pg.language',
							'icon'  => 'Languages',
						),
						'casts' => array (
							'title' => $lang['strcasts'],
							'url'   => 'casts.php',
							'urlvars' => array('subject' => 'database'),
							'hide'  => ($hide_advanced),
							'help'  => 'pg.cast',
							'icon'  => 'Casts',
						),
						'export' => array (
							'title' => $lang['strexport'],
							'url'   => 'database.php',
							'urlvars' => array('subject' => 'database', 'action' => 'export'),
							'hide'  => (!$this->isDumpEnabled()),
							'tree'  => false,
							'icon'  => 'Export',
						),
					);
					break;

				case 'schema':
					$tabs = array (
						'tables' => array (
							'title' => $lang['strtables'],
							'url'   => 'tables.php',
							'urlvars' => array('subject' => 'schema'),
							'help'  => 'pg.table',
							'icon'  => 'Tables',
						),
						'views' => array (
							'title' => $lang['strviews'],
							'url'   => 'views.php',
							'urlvars' => array('subject' => 'schema'),
							'help'  => 'pg.view',
							'icon'  => 'Views',
						),
						'sequences' => array (
							'title' => $lang['strsequences'],
							'url'   => 'sequences.php',
							'urlvars' => array('subject' => 'schema'),
							'help'  => 'pg.sequence',
							'icon'  => 'Sequences',
						),
						'functions' => array (
							'title' => $lang['strfunctions'],
							'url'   => 'functions.php',
							'urlvars' => array('subject' => 'schema'),
							'help'  => 'pg.function',
							'icon'  => 'Functions',
						),
						'fulltext' => array (
							'title' => $lang['strfulltext'],
							'url'   => 'fulltext.php',
							'urlvars' => array('subject' => 'schema'),
							'help'  => 'pg.fts',
							'tree'  => true,
							'icon'  => 'Fts',
						),
						'domains' => array (
							'title' => $lang['strdomains'],
							'url'   => 'domains.php',
							'urlvars' => array('subject' => 'schema'),
							'help'  => 'pg.domain',
							'icon'  => 'Domains',
						),
						'aggregates' => array (
							'title' => $lang['straggregates'],
							'url'   => 'aggregates.php',
							'urlvars' => array('subject' => 'schema'),
							'hide'  => $hide_advanced,
							'help'  => 'pg.aggregate',
							'icon'  => 'Aggregates',
						),
						'types' => array (
							'title' => $lang['strtypes'],
							'url'   => 'types.php',
							'urlvars' => array('subject' => 'schema'),
							'hide'  => $hide_advanced,
							'help'  => 'pg.type',
							'icon'  => 'Types',
						),
						'operators' => array (
							'title' => $lang['stroperators'],
							'url'   => 'operators.php',
							'urlvars' => array('subject' => 'schema'),
							'hide'  => $hide_advanced,
							'help'  => 'pg.operator',
							'icon'  => 'Operators',
						),
						'opclasses' => array (
							'title' => $lang['stropclasses'],
							'url'   => 'opclasses.php',
							'urlvars' => array('subject' => 'schema'),
							'hide'  => $hide_advanced,
							'help'  => 'pg.opclass',
							'icon'  => 'OperatorClasses',
						),
						'conversions' => array (
							'title' => $lang['strconversions'],
							'url'   => 'conversions.php',
							'urlvars' => array('subject' => 'schema'),
							'hide'  => $hide_advanced,
							'help'  => 'pg.conversion',
							'icon'  => 'Conversions',
						),
						'privileges' => array (
							'title' => $lang['strprivileges'],
							'url'   => 'privileges.php',
							'urlvars' => array('subject' => 'schema'),
							'help'  => 'pg.privilege',
							'tree'  => false,
							'icon'  => 'Privileges',
						),
						'export' => array (
							'title' => $lang['strexport'],
							'url'   => 'schemas.php',
							'urlvars' => array('subject' => 'schema', 'action' => 'export'),
							'hide'  => (!$this->isDumpEnabled()),
							'tree'  => false,
							'icon'  => 'Export',
						),
					);
					if (!$data->hasFTS()) unset($tabs['fulltext']);
					break;

				case 'table':
					$tabs = array (
						'columns' => array (
							'title' => $lang['strcolumns'],
							'url'   => 'tblproperties.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table')),
							'icon'  => 'Columns',
							'branch'=> true,
						),
						'browse' => array(
							'title' => $lang['strbrowse'],
							'icon'=>'Columns',
							'url'   => 'display.php',
							'urlvars' => array ('subject' => 'table','table' => field('table')),
							'return' => 'table',
						),
						'select' => array(
							'title' => $lang['strselect'],
							'icon'  => 'Search',
							'url'   => 'tables.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table'),'action' => 'confselectrows',),
							'help'  => 'pg.sql.select',
						),
						'insert'=>array(
							'title' => $lang['strinsert'],
							'url' => 'tables.php',
							'urlvars' => array (
								'action' => 'confinsertrow',
								'table' => field('table')
							),
							'help'  => 'pg.sql.insert',
							'icon'=>'Operator'
						),
						'indexes' => array (
							'title' => $lang['strindexes'],
							'url'   => 'indexes.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table')),
							'help'  => 'pg.index',
							'icon'  => 'Indexes',
							'branch'=> true,
						),
						'constraints' => array (
							'title' => $lang['strconstraints'],
							'url'   => 'constraints.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table')),
							'help'  => 'pg.constraint',
							'icon'  => 'Constraints',
							'branch'=> true,
						),
						'triggers' => array (
							'title' => $lang['strtriggers'],
							'url'   => 'triggers.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table')),
							'help'  => 'pg.trigger',
							'icon'  => 'Triggers',
							'branch'=> true,
						),
						'rules' => array (
							'title' => $lang['strrules'],
							'url'   => 'rules.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table')),
							'help'  => 'pg.rule',
							'icon'  => 'Rules',
							'branch'=> true,
						),
						'admin' => array (
							'title' => $lang['stradmin'],
							'url'   => 'tables.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table'), 'action' => 'admin'),
							'icon'  => 'Admin',
						),
						'info' => array (
							'title' => $lang['strinfo'],
							'url'   => 'info.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table')),
							'icon'  => 'Statistics',
						),
						'privileges' => array (
							'title' => $lang['strprivileges'],
							'url'   => 'privileges.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table')),
							'help'  => 'pg.privilege',
							'icon'  => 'Privileges',
						),
						'import' => array (
							'title' => $lang['strimport'],
							'url'   => 'tblproperties.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table'), 'action' => 'import'),
							'icon'  => 'Import',
							'hide'	=> false,
						),
						'export' => array (
							'title' => $lang['strexport'],
							'url'   => 'tblproperties.php',
							'urlvars' => array('subject' => 'table', 'table' => field('table'), 'action' => 'export'),
							'icon'  => 'Export',
							'hide'	=> false,
						),
					);
					break;

				case 'view':
					$tabs = array (
						'columns' => array (
							'title' => $lang['strcolumns'],
							'url'   => 'viewproperties.php',
							'urlvars' => array('subject' => 'view', 'view' => field('view')),
							'icon'  => 'Columns',
							'branch'=> true,
						),
						'browse' => array(
							'title' => $lang['strbrowse'],
							'icon'=>'Columns',
							'url'   => 'display.php',
							'urlvars' => array (
									'action' => 'confselectrows',
									'return' => 'schema',
									'subject' => 'view',
									'view' => field('view')
							),
						),
						'select' => array(
							'title' => $lang['strselect'],
							'icon'  => 'Search',
							'url'   => 'views.php',
							'urlvars' => array('action' => 'confselectrows', 'view' => field('view'),),
							'help'  => 'pg.sql.select',
						),
						'definition' => array (
							'title' => $lang['strdefinition'],
							'url'   => 'viewproperties.php',
							'urlvars' => array('subject' => 'view', 'view' => field('view'), 'action' => 'definition'),
							'icon'  => 'Definition'
						),
						'rules' => array (
							'title' => $lang['strrules'],
							'url'   => 'rules.php',
							'urlvars' => array('subject' => 'view', 'view' => field('view')),
							'help'  => 'pg.rule',
							'icon'  => 'Rules',
							'branch'=> true,
						),
						'privileges' => array (
							'title' => $lang['strprivileges'],
							'url'   => 'privileges.php',
							'urlvars' => array('subject' => 'view', 'view' => field('view')),
							'help'  => 'pg.privilege',
							'icon'  => 'Privileges',
						),
						'export' => array (
							'title' => $lang['strexport'],
							'url'   => 'viewproperties.php',
							'urlvars' => array('subject' => 'view', 'view' => field('view'), 'action' => 'export'),
							'icon'  => 'Export',
							'hide'	=> false,
						),
					);
					break;

				case 'function':
					$tabs = array (
						'definition' => array (
							'title' => $lang['strdefinition'],
							'url'   => 'functions.php',
							'urlvars' => array(
									'subject' => 'function',
									'function' => field('function'),
									'function_oid' => field('function_oid'),
									'action' => 'properties',
								),
							'icon'  => 'Definition',
						),
						'privileges' => array (
							'title' => $lang['strprivileges'],
							'url'   => 'privileges.php',
							'urlvars' => array(
									'subject' => 'function',
									'function' => field('function'),
									'function_oid' => field('function_oid'),
								),
							'icon'  => 'Privileges',
						),
					);
					break;

				case 'aggregate':
					$tabs = array (
						'definition' => array (
							'title' => $lang['strdefinition'],
							'url'   => 'aggregates.php',
							'urlvars' => array(
									'subject' => 'aggregate',
									'aggrname' => field('aggrname'),
									'aggrtype' => field('aggrtype'),
									'action' => 'properties',
								),
							'icon'  => 'Definition',
						),
					);
					break;

				case 'role':
					$tabs = array (
						'definition' => array (
							'title' => $lang['strdefinition'],
							'url'   => 'roles.php',
							'urlvars' => array(
									'subject' => 'role',
									'rolename' => field('rolename'),
									'action' => 'properties',
								),
							'icon'  => 'Definition',
						),
					);
					break;

				case 'popup':
					$tabs = array (
						'sql' => array (
							'title' => $lang['strsql'],
							'url'   => 'sqledit.php',
							'urlvars' => array('subject' => 'schema', 'action' => 'sql'),
							'help'  => 'pg.sql',
							'icon'  => 'SqlEditor',
						),
						'find' => array (
							'title' => $lang['strfind'],
							'url'   => 'sqledit.php',
							'urlvars' => array('subject' => 'schema', 'action' => 'find'),
							'icon'  => 'Search',
						),
					);
					break;

				case 'column':
					$tabs = array(
						'properties' => array (
							'title'		=> $lang['strcolprop'],
							'url'		=> 'colproperties.php',
							'urlvars'	=> array(
								'subject' => 'column',
								'table' => field('table'),
								'column' => field('column')
							),
							'icon'		=> 'Column'
						),
						'privileges' => array (
							'title' => $lang['strprivileges'],
							'url'   => 'privileges.php',
							'urlvars' => array(
								'subject' => 'column',
								'table' => field('table'),
								'column' => field('column')
							),
							'help'  => 'pg.privilege',
							'icon'  => 'Privileges',
						)
					);
					break;

				case 'fulltext':
					$tabs = array (
						'ftsconfigs' => array (
							'title' => $lang['strftstabconfigs'],
							'url'   => 'fulltext.php',
							'urlvars' => array('subject' => 'schema'),
							'hide'  => !$data->hasFTS(),
							'help'  => 'pg.ftscfg',
							'tree'  => true,
							'icon'  => 'FtsCfg',
						),
						'ftsdicts' => array (
							'title' => $lang['strftstabdicts'],
							'url'   => 'fulltext.php',
							'urlvars' => array('subject' => 'schema', 'action' => 'viewdicts'),
							'hide'  => !$data->hasFTS(),
							'help'  => 'pg.ftsdict',
							'tree'  => true,
							'icon'  => 'FtsDict',
						),
						'ftsparsers' => array (
							'title' => $lang['strftstabparsers'],
							'url'   => 'fulltext.php',
							'urlvars' => array('subject' => 'schema', 'action' => 'viewparsers'),
							'hide'  => !$data->hasFTS(),
							'help'  => 'pg.ftsparser',
							'tree'  => true,
							'icon'  => 'FtsParser',
						),
					);
					break;
			}

			// Tabs hook's place
			$plugin_functions_parameters = array(
				'tabs' => &$tabs,
				'section' => $section
			);
			$plugin_manager->do_hook('tabs', $plugin_functions_parameters);

			return $tabs;
		}

		/**
		 * Get the URL for the last active tab of a particular tab bar.
		 */
		function getLastTabURL($section) {
			global $data;

			$tabs = $this->getNavTabs($section);

			if (isset($_SESSION['webdbLastTab'][$section]) && isset($tabs[$_SESSION['webdbLastTab'][$section]]))
				$tab = $tabs[$_SESSION['webdbLastTab'][$section]];
			else
				$tab = reset($tabs);

			return isset($tab['url']) ? $tab : null;
		}

		function printTopbar() {
			global $lang, $conf, $plugin_manager, $appName, $appVersion, $appLangFiles;

			$server_info = $this->getServerInfo();
			$reqvars = $this->getRequestVars('table');

			if (isset($conf['extra_session_security']) && $conf['extra_session_security'] === false) {
				echo '<div class="alert-banner"><p><a href="https://www.php.net/manual/en/session.configuration.php#ini.session.cookie-samesite" target="_blank" rel="noopener noreferrer">', htmlspecialchars($lang['sessionsecuritywarning']), '</a></p></div>';
			}

			echo "<div class=\"topbar\">\n<div class=\"topbar-info\">";

			if ($server_info && isset($server_info['platform']) && isset($server_info['username'])) {
				/* top left information when connected */
				echo sprintf($lang['strtopbar'],
					'<span class="platform">'.htmlspecialchars($server_info['platform']).'</span>',
					'<span class="host">'.htmlspecialchars((empty($server_info['host'])) ? 'localhost':$server_info['host']).'</span>',
					'<span class="port">'.htmlspecialchars($server_info['port']).'</span>',
					'<span class="username">'.htmlspecialchars($server_info['username']).'</span>');

				echo "</div>\n";

				/* top right information when connected */

				$toplinks = array (
					'sql' => array (
						'attr' => array (
							'href' => array (
								'url' => 'sqledit.php',
								'urlvars' => array_merge($reqvars, array (
									'action' => 'sql'
								))
							),
							'id' => 'toplink_sql',
						),
						'content' => $lang['strsql']
					),
					'history' => array (
						'attr'=> array (
							'href' => array (
								'url' => 'history.php',
								'urlvars' => array_merge($reqvars, array (
									'action' => 'pophistory'
								))
							),
							'id' => 'toplink_history',
						),
						'content' => $lang['strhistory']
					),
					'find' => array (
						'attr' => array (
							'href' => array (
								'url' => 'sqledit.php',
								'urlvars' => array_merge($reqvars, array (
									'action' => 'find'
								))
							),
							'id' => 'toplink_find',
						),
						'content' => $lang['strfind']
					),
					'logout' => array(
						'attr' => array (
							'href' => array (
								'url' => 'servers.php',
								'urlvars' => array (
								'action' => 'logout',
								'logoutServer' => self::getServerId($server_info)
								)
							),
							'id' => 'toplink_logout',
						),
						'content' => $lang['strlogout']
					)
				);

				// Toplink hook's place
				$plugin_functions_parameters = array(
					'toplinks' => &$toplinks
				);

				$plugin_manager->do_hook('toplinks', $plugin_functions_parameters);

				echo "<nav class=\"topbar-links\" aria-label=\"", htmlspecialchars($lang['strtools']), "\">";
				$this->printLinksList($toplinks, 'toplink');
				echo "</nav>\n";

				if (isset($_SESSION['sharedUsername'])) {
					printf("<script>
						$('#toplink_logout').on('click', function() {
							return confirm(%s);
						});
					</script>\n", json_encode($lang['strconfdropcred']));
				}
			}
			else {
				echo "<span class=\"appname\">{$appName}</span> <span class=\"version\">{$appVersion}</span></div>\n";
			}
			echo "</div>\n";
		}

		/**
		 * Display a bread crumb trail.
		 */
		function printTrail($trail = array()) {
			global $lang;

			$this->printTopbar();

			if (is_string($trail)) {
				$trail = $this->getTrail($trail);
			}

			echo "<nav class=\"trail\" aria-label=\"", htmlspecialchars($lang['strbreadcrumb']), "\"><ol>";

			$last = count($trail);
			$n = 0;
			foreach ($trail as $crumb) {
				$n++;
				echo "<li class=\"crumb\">";
				$crumblink = "<a";

				if (isset($crumb['url']))
					$crumblink .= " href=\"{$crumb['url']}\"";

				if (isset($crumb['title']))
					$crumblink .= " title=\"{$crumb['title']}\"";

				if ($n == $last)
					$crumblink .= " aria-current=\"location\"";

				$crumblink .= ">";

				// The label carries the name; the icon is decoration.
				if (isset($crumb['icon']) && $icon = $this->icon($crumb['icon']))
					$crumblink .= "<span class=\"icon\"><img src=\"{$icon}\" alt=\"\" /></span>";

				$crumblink .= "<span class=\"label\">" . htmlspecialchars($crumb['text']) . "</span></a>";

				if (isset($crumb['help']))
					$this->printHelp($crumblink, $crumb['help']);
				else
					echo $crumblink;

				echo "<span class=\"sep\" aria-hidden=\"true\">{$lang['strseparator']}</span>";
				echo "</li>";
			}

			echo "</ol></nav>\n";
		}

		/**
		 * Create a bread crumb trail of the object hierarchy.
		 * @param $object The type of object at the end of the trail.
		 */
		function getTrail($subject = null) {
			global $lang, $conf, $data, $appName, $plugin_manager;

			$trail = array();
			$vars = '';
			$done = false;

			$trail['root'] = array(
				'text'  => $appName,
				'url'   => 'redirect.php?subject=root',
				'icon'  => 'Introduction'
			);

			if ($subject == 'root') $done = true;

			if (!$done) {
				$server_info = $this->getServerInfo();
				$trail['server'] = array(
					'title' => $lang['strserver'],
					'text'  => $server_info['server_desc'] ?? $server_info['desc'],
					'url'   => $this->getHREFSubject('server'),
					'help'  => 'pg.server',
					'icon'  => 'Server'
				);
			}
			if ($subject == 'server') $done = true;

			if (isset($_REQUEST['database']) && !$done) {
				$trail['database'] = array(
					'title' => $lang['strdatabase'],
					'text'  => $_REQUEST['database'],
					'url'   => $this->getHREFSubject('database'),
					'help'  => 'pg.database',
					'icon'  => 'Database'
				);
			} elseif (isset($_REQUEST['rolename']) && !$done) {
				$trail['role'] = array(
					'title' => $lang['strrole'],
					'text'  => $_REQUEST['rolename'],
					'url'   => $this->getHREFSubject('role'),
					'help'  => 'pg.role',
					'icon'  => 'Roles'
				);
			}
			if ($subject == 'database' || $subject == 'role') $done = true;

			if (isset($_REQUEST['schema']) && !$done) {
				$trail['schema'] = array(
					'title' => $lang['strschema'],
					'text'  => $_REQUEST['schema'],
					'url'   => $this->getHREFSubject('schema'),
					'help'  => 'pg.schema',
					'icon'  => 'Schema'
				);
			}
			if ($subject == 'schema') $done = true;

			if (isset($_REQUEST['table']) && !$done) {
				$trail['table'] = array(
					'title' => $lang['strtable'],
					'text'  => $_REQUEST['table'],
					'url'   => $this->getHREFSubject('table'),
					'help'  => 'pg.table',
					'icon'  => 'Table'
				);
			} elseif (isset($_REQUEST['view']) && !$done) {
				$trail['view'] = array(
					'title' => $lang['strview'],
					'text'  => $_REQUEST['view'],
					'url'   => $this->getHREFSubject('view'),
					'help'  => 'pg.view',
					'icon'  => 'View'
				);
			} elseif (isset($_REQUEST['ftscfg']) && !$done) {
				$trail['ftscfg'] = array(
					'title' => $lang['strftsconfig'],
					'text'  => $_REQUEST['ftscfg'],
					'url'   => $this->getHREFSubject('ftscfg'),
					'help'  => 'pg.ftscfg.example',
					'icon'  => 'Fts'
				);
			}
			if ($subject == 'table' || $subject == 'view' || $subject == 'ftscfg') $done = true;

			if (!$done && !is_null($subject)) {
				switch ($subject) {
					case 'function':
						$trail[$subject] = array(
							'title' => $lang['str'.$subject],
							'text'  => $_REQUEST[$subject],
							'url'   => $this->getHREFSubject('function'),
							'help'  => 'pg.function',
							'icon'  => 'Function'
						);
						break;
					case 'aggregate':
						$trail[$subject] = array(
							'title' => $lang['straggregate'],
							'text'  => $_REQUEST['aggrname'],
							'url'   => $this->getHREFSubject('aggregate'),
							'help'  => 'pg.aggregate',
							'icon'  => 'Aggregate'
						);
						break;
					case 'column':
						$trail['column'] = array (
							'title' => $lang['strcolumn'],
							'text'  => $_REQUEST['column'],
							'icon'	=> 'Column',
							'url'   => $this->getHREFSubject('column')
						);
						break;
					default:
						if (isset($_REQUEST[$subject])) {
							switch ($subject) {
								case 'domain': $icon = 'Domain'; break;
								case 'sequence': $icon = 'Sequence'; break;
								case 'type': $icon = 'Type'; break;
								case 'operator': $icon = 'Operator'; break;
								default: $icon = null; break;
							}
							$trail[$subject] = array(
								'title' => $lang['str'.$subject],
								'text'  => $_REQUEST[$subject],
								'help'  => 'pg.'.$subject,
								'icon'  => $icon,
							);
						}
				}
			}

			// Trail hook's place
			$plugin_functions_parameters = array(
				'trail' => &$trail,
				'section' => $subject
			);

			$plugin_manager->do_hook('trail', $plugin_functions_parameters);

			return $trail;
		}

		/**
		* Display the navlinks
		*
		* @param $navlinks - An array with the the attributes and values that will be shown. See printLinksList for array format.
		* @param $place - Place where the $navlinks are displayed. Like 'display-browse', where 'display' is the file (display.php)
		* @param $env - Associative array of defined variables in the scope of the caller.
		*               Allows to give some environment details to plugins.
		* and 'browse' is the place inside that code (doBrowse).
		*/
		function printNavLinks($navlinks, $place, $env = array()) {
			global $plugin_manager;

			// Navlinks hook's place
			$plugin_functions_parameters = array(
				'navlinks' => &$navlinks,
				'place' => $place,
				'env' => $env
			);
			$plugin_manager->do_hook('navlinks', $plugin_functions_parameters);

			if (count($navlinks) > 0) {
				$this->printLinksList($navlinks, 'navlink');
			}
		}


		/**
		 * Do multi-page navigation.  Displays the prev, next and page options.
		 * @param $page - the page currently viewed
		 * @param $pages - the maximum number of pages, an estimate if $opts['estimated'],
		 *                 or null when the number of pages is unknown
		 * @param $gets -  the parameters to include in the link to the wanted page
		 * @param $max_width - the number of pages to make available at any one time (default = 20)
		 * @param $opts - 'has_next' => whether there is a page after this one (defaults to
		 *                $page < $pages), 'estimated' => true if $pages is an estimate,
		 *                'label' => accessible name of the navigation
		 */
		function printPages($page, $pages, $gets, $max_width = 20, $opts = array()) {
			global $lang;

			$window = 10;

			if (!is_numeric($page) || $max_width <= 0) return;
			$page = (int)$page;
			if ($page < 1) return;

			// Only a known, exact number of pages gives a "Last" link
			$known = ($pages !== null && empty($opts['estimated']));
			if ($pages !== null) {
				$pages = (int)$pages;
				if ($pages < 0) return;
				if ($known && $page > $pages) return;
			}
			$has_next = isset($opts['has_next']) ? (bool)$opts['has_next'] : ($pages !== null && $page < $pages);

			// The last page number we can list
			if ($known) $last = $pages;
			elseif (!$has_next) $last = $page;
			elseif ($pages !== null) $last = max($pages, $page + 1);
			else $last = $page + 1;

			if ($last <= 1) return;

			unset ($gets['page']);
			$url = 'display.php?' . http_build_query($gets, '', '&amp;');
			if (count($gets) > 0) $url .= '&amp;';
			$url .= 'page=';

			$label = isset($opts['label']) ? $opts['label'] : $lang['strpagination'];
			echo "<nav class=\"pagination\" aria-label=\"", htmlspecialchars($label), "\">\n";
			if ($page != 1) {
				echo "<a class=\"pagenav\" href=\"{$url}1\">", htmlspecialchars($lang['strfirst']), "</a>\n";
				$temp = $page - 1;
				echo "<a class=\"pagenav\" rel=\"prev\" href=\"{$url}{$temp}\">", htmlspecialchars($lang['strprev']), "</a>\n";
			}

			if ($page <= $window) {
				$min_page = 1;
				$max_page = min(2 * $window, $last);
			}
			elseif ($page > $window && $last >= $page + $window) {
				$min_page = ($page - $window) + 1;
				$max_page = $page + $window;
			}
			else {
				$min_page = ($page - (2 * $window - ($last - $page))) + 1;
				$max_page = $last;
			}

			// Make sure min_page is always at least 1
			// and max_page is never greater than $last
			$min_page = max($min_page, 1);
			$max_page = min($max_page, $last);

			for ($i = $min_page; $i <= $max_page; $i++) {
				if ($i != $page) echo "<a class=\"pagenav\" href=\"{$url}{$i}\">{$i}</a>\n";
				else echo "<span class=\"pagenav current\" aria-current=\"page\">{$i}</span>\n";
			}
			// More pages than we can tell
			if (!$known && $has_next && $max_page >= $last) echo "<span class=\"pagenav gap\">&hellip;</span>\n";

			if ($has_next) {
				$temp = $page + 1;
				echo "<a class=\"pagenav\" rel=\"next\" href=\"{$url}{$temp}\">", htmlspecialchars($lang['strnext']), "</a>\n";
				if ($known)
					echo "<a class=\"pagenav\" href=\"{$url}{$pages}\">", htmlspecialchars($lang['strlast']), "</a>\n";
			}
			echo "</nav>\n";
		}

		/**
		 * Remembers a query in the session, so that pages and links can refer to it
		 * by a short token instead of sharing the single "current query": two browser
		 * tabs can then page through different results.  Also sets it as the current
		 * query ($_SESSION['sqlquery']) for the code that still reads that.
		 * @param $sql The SQL query
		 * @return string The token (16 hex digits)
		 */
		function storeQuery($sql) {
			$sql = (string)$sql;
			$token = substr(sha1($sql), 0, 16);

			if (!isset($_SESSION['ppaQueries']) || !is_array($_SESSION['ppaQueries']))
				$_SESSION['ppaQueries'] = array();

			// Move it to the end, the most recently used, and keep the 20 latest
			unset($_SESSION['ppaQueries'][$token]);
			$_SESSION['ppaQueries'][$token] = $sql;
			if (count($_SESSION['ppaQueries']) > 20)
				$_SESSION['ppaQueries'] = array_slice($_SESSION['ppaQueries'], -20, null, true);

			$_SESSION['sqlquery'] = $sql;

			return $token;
		}

		/**
		 * Returns a query remembered with storeQuery()
		 * @param $token The token storeQuery() returned
		 * @return string|null The query, or null if the token is unknown
		 */
		function getStoredQuery($token) {
			if (!is_string($token) || !preg_match('/^[0-9a-f]{16}$/', $token)) return null;
			if (!isset($_SESSION['ppaQueries'][$token])) return null;
			return $_SESSION['ppaQueries'][$token];
		}

		/**
		 * Displays link to the context help.
		 * @param $str   - the string that the context help is related to (already escaped)
		 * @param $help  - help section identifier
		 */
		function printHelp($str, $help) {
			global $lang, $data;

			echo $str;
			if ($help) {
				echo "<a class=\"help\" href=\"";
				echo htmlspecialchars("help.php?help=".urlencode($help)."&server=".urlencode($_REQUEST['server']));
				echo "\" title=\"{$lang['strhelp']}\" target=\"phppgadminhelp\">{$lang['strhelpicon']}</a>";
			}
		}

		/**
		 * Outputs JavaScript to set default focus
		 * @param $object eg. forms[0].username
		 */
		function setFocus($object) {
			echo "<script type=\"text/javascript\">\n";
			echo "   document.{$object}.focus();\n";
			echo "</script>\n";
		}

		/**
		 * Outputs JavaScript to set the name of the browser window.
		 * @param $name the window name
		 * @param $addServer if true (default) then the server id is
		 *        attached to the name.
		 */
		function setWindowName($name, $addServer = true) {
			// Pages used to name their popup window. Everything lives in one
			// window now, so there is nothing to name.
		}

		/**
		 * Converts a PHP.INI size variable to bytes.  Taken from publicly available
		 * function by Chris DeRose, here: http://www.php.net/manual/en/configuration.directives.php#ini.file-uploads
		 * @param $strIniSize The PHP.INI variable
		 * @return size in bytes, false on failure
		 */
		function inisizeToBytes($strIniSize) {
			// This function will take the string value of an ini 'size' parameter,
			// and return a float (64-bit) representing the number of bytes that
			// the parameter represents. Or false if $strIniSize is unparsable.
			// PHP's shorthand notation allows a single optional K, M or G suffix;
			// a bare B suffix is also accepted here for backwards compatibility.
			$a_IniParts = array();

			if (!is_string($strIniSize))
				return false;

			if (!preg_match('/^\s*(\d+)\s*([bkmg]?)\s*$/i', $strIniSize, $a_IniParts))
				return false;

			$nSize = (float) $a_IniParts[1];
			$strUnit = strtolower($a_IniParts[2]);

			switch($strUnit) {
				case 'g':
					return ($nSize * (float) 1073741824);
				case 'm':
					return ($nSize * (float) 1048576);
				case 'k':
					return ($nSize * (float) 1024);
				case 'b':
				default:
					return $nSize;
			}
		}

		/**
		 * Returns URL given an action associative array.
		 * NOTE: this function does not html-escape, only url-escape
		 * @param $action An associative array of the follow properties:
		 *			'url'  => The first part of the URL (before the ?)
		 *			'urlvars' => Associative array of (URL variable => field name)
		 *						these are appended to the URL
		 * @param $fields Field data from which 'urlfield' and 'vars' are obtained.
		 */
		function getActionUrl(&$action, &$fields) {
			$url = value($action['url'], $fields);

			if ($url === false) return '';

			if (!empty($action['urlvars'])) {
				$urlvars = value($action['urlvars'], $fields);
			} else {
				$urlvars = array();
			}

			/* set server, database and schema parameter if not presents */
			if (isset($urlvars['subject']))
				$subject = value($urlvars['subject'], $fields);
			else
				$subject = '';

			if (isset($_REQUEST['server']) and !isset($urlvars['server']) and $subject != 'root') {
				$urlvars['server'] = $_REQUEST['server'];
				if (isset($_REQUEST['database']) and !isset($urlvars['database']) and $subject != 'server') {
					$urlvars['database'] = $_REQUEST['database'];
					if (isset($_REQUEST['schema']) and !isset($urlvars['schema']) and $subject != 'database') {
						$urlvars['schema'] = $_REQUEST['schema'];
					}
				}
			}

			$sep = '?';
			foreach ($urlvars as $var => $varfield) {
				$url .= $sep . value_url($var, $fields) . '=' . value_url($varfield, $fields);
				$sep = '&';
			}

			return $url;
		}

		function getRequestVars($subject = '') {
			$v = array();
			if (!empty($subject))
				$v['subject'] = $subject;
			if (isset($_REQUEST['server']) && $subject != 'root') {
				$v['server'] = $_REQUEST['server'];
				if (isset($_REQUEST['database']) && $subject != 'server') {
					$v['database'] = $_REQUEST['database'];
					if (isset($_REQUEST['schema']) && $subject != 'database') {
						$v['schema'] = $_REQUEST['schema'];
					}
				}
			}
			return $v;
		}

		function printUrlVars(&$vars, &$fields) {
			foreach ($vars as $var => $varfield) {
				echo "{$var}=", urlencode($fields[$varfield]), "&amp;";
			}
		}

		/**
		 * Display a table of data.
		 * @param $tabledata A set of data to be formatted, as returned by $data->getDatabases() etc.
		 * @param $columns   An associative array of columns to be displayed:
		 *			$columns = array(
		 *				column_id => array(
		 *					'title' => Column heading,
		 * 					'class' => The class to apply on the column cells,
		 *					'field' => Field name for $tabledata->fields[...],
		 *					'help'  => Help page for this column,
		 *				), ...
		 *			);
		 * @param $actions   Actions that can be performed on each object:
		 *			$actions = array(
		 *				* multi action support
		 *				* parameters are serialized for each entries and given in $_REQUEST['ma']
		 *				'multiactions' => array(
		 *					'keycols' => Associative array of (URL variable => field name), // fields included in the form
		 *					'url' => URL submission,
		 *					'default' => Default selected action in the form.
		 *									if null, an empty action is added & selected
		 *				),
		 *				* actions *
		 *				action_id => array(
		 *					'title' => Action heading,
		 *					'url'   => Static part of URL.  Often we rely
		 *							   relative urls, usually the page itself (not '' !), or just a query string,
		 *					'vars'  => Associative array of (URL variable => field name),
		 *					'multiaction' => Name of the action to execute.
		 *										Add this action to the multi action form
		 *				), ...
		 *			);
		 * @param $place     Place where the $actions are displayed. Like 'display-browse', where 'display' is the file (display.php)
		 *                   and 'browse' is the place inside that code (doBrowse).
		 * @param $nodata    (optional) Message to display if data set is empty.
		 * @param $pre_fn    (optional) Name of a function to call for each row,
		 *					 it will be passed two params: $rowdata and $actions,
		 *					 it may be used to derive new fields or modify actions.
		 *					 It can return an array of actions specific to the row,
		 *					 or if nothing is returned then the standard actions are used.
		 *					 (see tblproperties.php and constraints.php for examples)
		 *					 The function must not must not store urls because
		 *					 they are relative and won't work out of context.
		 */
		function printTable(&$tabledata, &$columns, &$actions, $place, $nodata = null, $pre_fn = null) {
			global $data, $conf, $misc, $lang, $plugin_manager;

			// Action buttons hook's place
			$plugin_functions_parameters = array(
				'actionbuttons' => &$actions,
				'place' => $place
			);
			$plugin_manager->do_hook('actionbuttons', $plugin_functions_parameters);

			$ma = $actions['multiactions'] ?? null;
			$has_ma = $ma !== null;
			unset($actions['multiactions']);

			if ($tabledata->recordCount() > 0) {
				// Remove the 'comment' column if they have been disabled
				if (!$conf['show_comments']) {
					unset($columns['comment']);
				}

				if (isset($columns['comment'])) {
					// Uncomment this for clipped comments.
					// TODO: This should be a user option.
					//$columns['comment']['params']['clip'] = true;
				}

				if ($ma !== null) {
					echo "<script src=\"", htmlspecialchars($this->asset('multiactionform.js')), "\"></script>\n";
					echo "<form id=\"multi_form\" action=\"{$ma['url']}\" method=\"post\" enctype=\"multipart/form-data\">\n";
					if (isset($ma['vars']))
						foreach ($ma['vars'] as $k => $v)
							echo "<input type=\"hidden\" name=\"$k\" value=\"$v\" />";
				}

				echo "<table>\n";
				echo "<tr>\n";

				// Display column headings
				if ($ma !== null) echo "<th></th>";
				foreach ($columns as $column_id => $column) {
					// Optional extra CSS class for this column's heading ('class' key)
					$class = ($column['class'] ?? '') !== '' ? " {$column['class']}" : '';
					switch ($column_id) {
						case 'actions':
							if (sizeof($actions) > 0) echo "<th class=\"data\" colspan=\"", count($actions), "\">{$column['title']}</th>\n";
							break;
						default:
							echo "<th class=\"data{$class}\">";
							if (isset($column['help']))
								$this->printHelp($column['title'], $column['help']);
							else
								echo $column['title'];
							echo "</th>\n";
							break;
					}
				}
				echo "</tr>\n";

				// Display table rows
				$i = 0;
				$a = array();
				while (!$tabledata->EOF) {
					$id = ($i % 2) + 1;

					unset($alt_actions);
					if (!is_null($pre_fn)) $alt_actions = $pre_fn($tabledata, $actions);
					if (!isset($alt_actions)) $alt_actions =& $actions;

					echo "<tr class=\"data{$id}\">\n";
					if ($ma !== null) {
						foreach ($ma['keycols'] as $k => $v)
							$a[$k] = $tabledata->fields[$v];
						echo "<td>";
						echo "<input type=\"checkbox\" name=\"ma[]\" value=\"". htmlentities(serialize($a), ENT_COMPAT, 'UTF-8') ."\" />";
						echo "</td>\n";
					}

					foreach ($columns as $column_id => $column) {
						// Apply default values for missing parameters
						if (isset($column['url']) && !isset($column['vars'])) $column['vars'] = array();

						// Optional extra CSS class for this column's cells ('class' key)
						$class = ($column['class'] ?? '') !== '' ? " class=\"{$column['class']}\"" : '';

						switch ($column_id) {
							case 'actions':
								foreach ($alt_actions as $action) {
									if (isset($action['disable']) && $action['disable'] === true) {
										echo "<td></td>\n";
									} else {
										echo "<td class=\"opbutton{$id}\">";
										$action['fields'] = $tabledata->fields;
										$this->printLink($action);
										echo "</td>\n";
									}
								}
								break;
							case 'comment':
								echo "<td class='comment_cell'>";
								$val = value($column['field'], $tabledata->fields);
								if (!is_null($val)) {
									echo htmlentities($val);
								}
								echo "</td>";
								break;
							default:
								echo "<td{$class}>";
								$val = value($column['field'], $tabledata->fields);
								if (!is_null($val)) {
									if (isset($column['url'])) {
										echo "<a href=\"{$column['url']}";
										$misc->printUrlVars($column['vars'], $tabledata->fields);
										echo "\">";
									}
									$type = isset($column['type']) ? $column['type'] : null;
									$params = isset($column['params']) ? $column['params'] : array();
									echo $misc->printVal($val, $type, $params);
									if (isset($column['url'])) echo "</a>";
								}

								echo "</td>\n";
								break;
						}
					}
					echo "</tr>\n";

					$tabledata->moveNext();
					$i++;
				}
				echo "</table>\n";

				// Multi action table footer w/ options & [un]check'em all
				if ($ma !== null) {
					// if default is not set or doesn't exist, set it to null
					if (!isset($ma['default']) || !isset($actions[$ma['default']]))
						$ma['default'] = null;
					echo "<br />\n";
					echo "<table>\n";
					echo "<tr>\n";
					echo "<th class=\"data\" style=\"text-align: left\" colspan=\"3\">{$lang['stractionsonmultiplelines']}</th>\n";
					echo "</tr>\n";
					echo "<tr class=\"row1\">\n";
					echo "<td>";
					echo "<a href=\"#\" onclick=\"javascript:checkAll(true);\">{$lang['strselectall']}</a> / ";
					echo "<a href=\"#\" onclick=\"javascript:checkAll(false);\">{$lang['strunselectall']}</a></td>\n";
					echo "<td>&nbsp;--->&nbsp;</td>\n";
					echo "<td>\n";
					echo "\t<select name=\"action\">\n";
					if ($ma['default'] == null)
						echo "\t\t<option value=\"\">--</option>\n";
					foreach($actions as $k => $a)
						if (isset($a['multiaction']))
							echo "\t\t<option value=\"{$a['multiaction']}\"", ($ma['default']  == $k? ' selected="selected"': ''), ">{$a['content']}</option>\n";
					echo "\t</select>\n";
					echo "<input type=\"submit\" value=\"{$lang['strexecute']}\" />\n";
					echo $misc->form;
					echo "</td>\n";
					echo "</tr>\n";
					echo "</table>\n";
					echo '</form>';
				};

				return true;
			} else {
				if (!is_null($nodata)) {
					echo "<p>{$nodata}</p>\n";
				}
				return false;
			}
		}

		/** Produce XML data for the browser tree
		 * @param $treedata A set of records to populate the tree.
		 * @param $attrs Attributes for tree items
		 *        'text' - the text for the tree node
		 *        'icon' - an icon for node
		 *        'openIcon' - an alternative icon when the node is expanded
		 *        'toolTip' - tool tip text for the node
		 *        'action' - URL to visit when single clicking the node
		 *        'iconAction' - URL to visit when single clicking the icon node
		 *        'branch' - URL for child nodes (tree XML)
		 *        'expand' - the action to return XML for the subtree
		 *        'nodata' - message to display when node has no children
		 * @param $section The section where the branch is linked in the tree
		 */
		function printTree(&$_treedata, &$attrs, $section) {
			global $plugin_manager;

			$treedata = array();

			if ($_treedata->recordCount() > 0) {
				while (!$_treedata->EOF) {
					$treedata[] = $_treedata->fields;
					$_treedata->moveNext();
				}
			}

			$tree_params = array(
				'treedata' => &$treedata,
				'attrs' => &$attrs,
				'section' => $section
			);

			$plugin_manager->do_hook('tree', $tree_params);

			$this->printTreeXML($treedata, $attrs);
		}

		/** Produce XML data for the browser tree
		 * @param $treedata A set of records to populate the tree.
		 * @param $attrs Attributes for tree items
		 *        'text' - the text for the tree node
		 *        'icon' - an icon for node
		 *        'openIcon' - an alternative icon when the node is expanded
		 *        'toolTip' - tool tip text for the node
		 *        'action' - URL to visit when single clicking the node
		 *        'iconAction' - URL to visit when single clicking the icon node
		 *        'branch' - URL for child nodes (tree XML)
		 *        'expand' - the action to return XML for the subtree
		 *        'nodata' - message to display when node has no children
		 */
		function printTreeXML(&$treedata, &$attrs) {
			global $conf, $lang;

			header("Content-Type: text/xml; charset=UTF-8");
			header("Cache-Control: no-cache");

			echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";

			echo "<tree>\n";

			if (count($treedata) > 0) {
				foreach($treedata as $rec) {
					echo "<tree";
					echo value_xml_attr('text', $attrs['text'], $rec);
					echo value_xml_attr('action', $attrs['action'], $rec);
					echo value_xml_attr('src', $attrs['branch'], $rec);

					$icon = $this->icon(value($attrs['icon'], $rec));
					echo value_xml_attr('icon', $icon, $rec);
					echo value_xml_attr('iconaction', $attrs['iconAction'], $rec);

					if (!empty($attrs['openicon'])) {
						$icon = $this->icon(value($attrs['openIcon'], $rec));
					}
					echo value_xml_attr('openicon', $icon, $rec);

					echo value_xml_attr('tooltip', $attrs['toolTip'], $rec);

					echo " />\n";
				}
			} else {
				$msg = isset($attrs['nodata']) ? $attrs['nodata'] : $lang['strnoobjects'];
				echo "<tree text=\"{$msg}\" onaction=\"tree.getSelected().getParent().reload()\" icon=\"", $this->icon('ObjectNotFound'), "\" />\n";
			}

			echo "</tree>\n";
		}

		function adjustTabsForTree(&$tabs) {
			include_once('./classes/ArrayRecordSet.php');

			foreach ($tabs as $i => $tab) {
				if ((isset($tab['hide']) && $tab['hide'] === true) || (isset($tab['tree']) && $tab['tree'] === false)) {
					unset($tabs[$i]);
				}
			}
			return new ArrayRecordSet($tabs);
		}

		function icon($icon) {
			if (is_string($icon)) {
				global $conf;
				$path = "images/themes/{$conf['theme']}/{$icon}";
				if (file_exists($path.'.png')) return $path.'.png';
				if (file_exists($path.'.gif')) return $path.'.gif';
				$path = "images/themes/default/{$icon}";
				if (file_exists($path.'.png')) return $path.'.png';
				if (file_exists($path.'.gif')) return $path.'.gif';
			}
			elseif (is_array($icon)) {
				// Icon from plugins
				$path = "plugins/{$icon[0]}/images/{$icon[1]}";
				if (file_exists($path.'.png')) return $path.'.png';
				if (file_exists($path.'.gif')) return $path.'.gif';
			}
			return '';
		}

		/**
		 * Function to escape command line parameters
		 * @param $str The string to escape
		 * @return string The escaped string
		 */
		function escapeShellArg($str) {
			global $data, $lang;

			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				// Due to annoying PHP bugs, shell arguments cannot be escaped
				// (command simply fails), so we cannot allow complex objects
				// to be dumped.
				if (preg_match('/^[_.[:alnum:]]+$/', $str))
					return $str;
				else {
					echo $lang['strcannotdumponwindows'];
					exit;
				}
			}
			else
				return escapeshellarg($str);
		}

		/**
		 * Function to escape command line programs
		 * @param $str The string to escape
		 * @return string The escaped string
		 */
		function escapeShellCmd($str) {
			global $data;

			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$data->fieldClean($str);
				return '"' . $str . '"';
			}
			else
				return escapeshellcmd($str);
		}

		/**
		 * Get list of servers' groups if existing in the conf
		 * @return a recordset of servers' groups
		 */
		function getServersGroups($recordset = false, $group_id = false) {
			global $conf, $lang;
			$grps = array();

			if (isset($conf['srv_groups'])) {
				foreach ($conf['srv_groups'] as $i => $group) {
					if (
						(($group_id === false) and (! isset($group['parents']))) /* root */
						or (
							($group_id !== false)
							and isset($group['parents'])
							and in_array($group_id,explode(',',
									preg_replace('/\s/', '', $group['parents'])
								))
						) /* nested group */
					)
						$grps[$i] = array(
							'id' => $i,
							'desc' => $group['desc'],
							'icon' => 'Servers',
							'action' => url('servers.php',
								array(
									'group' => field('id')
								)
							),
							'branch' => url('servers.php',
								array(
									'action' => 'tree',
									'group' => $i
								)
							)
						);
				}

				if ($group_id === false)
					$grps['all'] = array(
						'id' => 'all',
						'desc' => $lang['strallservers'],
						'icon' => 'Servers',
						'action' => url('servers.php',
							array(
								'group' => field('id')
							)
						),
						'branch' => url('servers.php',
								array(
									'action' => 'tree',
									'group' => 'all'
								)
							)
					);
			}

			if ($recordset) {
				include_once('./classes/ArrayRecordSet.php');
				return new ArrayRecordSet($grps);
			}

			return $grps;
		}


		/**
		 * The identifier of a server (or of one database on it), as used in
		 * URLs and as the key of its login in the session.
		 * @param $info array A server entry from getLoginTargets()
		 * @return string
		 */
		static function getServerId($info) {
			$id = $info['host'].':'.$info['port'].':'.$info['sslmode'];
			if (isset($info['database']))
				$id .= ':'.$info['database'];
			return $id;
		}

		/**
		 * Everything the user can log in to, keyed by server id.
		 *
		 * A server whose config has a 'databases' list doesn't get a login of
		 * its own. Instead each of those databases becomes a separate entry
		 * with its own login, which only ever connects to that database.
		 * @return array
		 */
		function getLoginTargets() {
			global $conf;

			$targets = array();
			foreach($conf['servers'] as $idx => $info) {
				$info['server_index'] = $idx;

				if (empty($info['databases']) || !is_array($info['databases'])) {
					$targets[self::getServerId($info)] = $info;
					continue;
				}

				foreach($info['databases'] as $database) {
					$target = $info;
					unset($target['databases']);
					$target['database'] = (string) $database;
					$target['defaultdb'] = (string) $database;
					$target['server_desc'] = $info['desc'];
					$target['desc'] = $info['desc'].' / '.$database;
					$targets[self::getServerId($target)] = $target;
				}
			}

			return $targets;
		}

		/**
		 * Get list of servers
		 * @param $recordset return as RecordSet suitable for printTable if true,
		 *                   otherwise just return an array.
		 * @param $group a group name to filter the returned servers using $conf[srv_groups]
		 * @param $server_index only return the per-database logins of this
		 *                      $conf['servers'] entry
		 */
		function getServers($recordset = false, $group = false, $server_index = null) {
			$logins = isset($_SESSION['webdbLogin']) && is_array($_SESSION['webdbLogin']) ? $_SESSION['webdbLogin'] : array();
			$srvs = array();

			global $conf;
			if (($group !== false) and ($group !== 'all'))
				if (isset($conf['srv_groups'][$group]['servers']))
					$group = array_fill_keys(explode(',', preg_replace('/\s/', '',
						$conf['srv_groups'][$group]['servers'])), 1);
				else
					$group = '';

			foreach($this->getLoginTargets() as $server_id => $info) {
				$idx = $info['server_index'];
				if ($server_index !== null && ((string) $idx !== (string) $server_index || !isset($info['database'])))
					continue;

				if (($group === false)
					or (isset($group[$idx]))
					or ($group === 'all')
				) {
					if (isset($logins[$server_id])) $srvs[$server_id] = array_merge($info, $logins[$server_id]);
					else $srvs[$server_id] = $info;

					$srvs[$server_id]['id'] = $server_id;
					$logged_in = isset($srvs[$server_id]['username']);

					if (isset($info['database'])) {
						$srvs[$server_id]['action'] = url('redirect.php',
							array(
								'subject' => 'database',
								'server' => field('id'),
								'database' => field('database')
							)
						);
						$srvs[$server_id]['icon'] = $logged_in ? 'Database' : 'DisconnectedDatabase';
						$srvs[$server_id]['branch'] = $logged_in ? url('database.php',
							array(
								'action' => 'tree',
								'subject' => 'database',
								'server' => field('id'),
								'database' => field('database')
							)
						) : false;
						continue;
					}

					$srvs[$server_id]['action'] = url('redirect.php',
						array(
							'subject' => 'server',
							'server' => field('id')
						)
					);
					if ($logged_in) {
						$srvs[$server_id]['icon'] = 'Server';
						$srvs[$server_id]['branch'] = url('all_db.php',
							array(
								'action' => 'tree',
								'subject' => 'server',
								'server' => field('id')
							)
						);
					}
					else {
						$srvs[$server_id]['icon'] = 'DisconnectedServer';
						$srvs[$server_id]['branch'] = false;
					}
				}
			}

			uasort($srvs, function($a, $b) {
				return strcmp($a['desc'], $b['desc']);
			});

			if ($recordset) {
				include_once('./classes/ArrayRecordSet.php');
				return new ArrayRecordSet($srvs);
			}
			return $srvs;
		}

		/**
		 * Put the per-database logins of a server under one node for the
		 * browser tree, so the databases show up as children of their server.
		 * @param $srvs array as returned by getServers()
		 * @return array
		 */
		function groupServersForTree($srvs) {
			$nodes = array();
			foreach($srvs as $server_id => $srv) {
				if (!isset($srv['database'])) {
					$nodes[$server_id] = $srv;
					continue;
				}

				$key = 'databases:'.$srv['server_index'];
				if (!isset($nodes[$key]))
					$nodes[$key] = array(
						'id' => $srv['host'].':'.$srv['port'],
						'desc' => $srv['server_desc'],
						'icon' => 'DisconnectedServer',
						'action' => url('servers.php',
							array('dbserver' => $srv['server_index'])
						),
						'branch' => url('servers.php',
							array('action' => 'tree', 'dbserver' => $srv['server_index'])
						),
					);
				if (isset($srv['username']))
					$nodes[$key]['icon'] = 'Server';
			}

			return $nodes;
		}

		/**
		 * Validate and retrieve information on a server.
		 * If the parameter isn't supplied then the currently
		 * connected server is returned.
		 * @param $server_id A server identifier (host:port)
		 * @return array|null An associative array of server properties
		 */
		function getServerInfo($server_id = null) {
			global $conf, $_reload_browser, $lang;

			if ($server_id === null && isset($_REQUEST['server']))
				$server_id = $_REQUEST['server'];

			// Look for the server in the conf file
			$targets = $this->getLoginTargets();
			$server_info = is_scalar($server_id) && isset($targets[$server_id]) ? $targets[$server_id] : null;

			if (isset($_SESSION['webdbLogin'][$server_id ?? ''])) {
				if ($server_info === null)
					return $_SESSION['webdbLogin'][$server_id ?? ''];

				return array_merge($server_info, $_SESSION['webdbLogin'][$server_id ?? '']);
			}

			if ($server_info !== null) {
				// Automatically use shared credentials if available
				if (!isset($server_info['username']) && isset($_SESSION['sharedUsername'])) {
					$server_info['username'] = $_SESSION['sharedUsername'];
					$server_info['password'] = $_SESSION['sharedPassword'];
					$_reload_browser = true;
					$this->setServerInfo(null, $server_info, $server_id);
				}

				return $server_info;
			}

			if ($server_id === null){
				return null;
			} else {
				// Unable to find a matching server, are we being hacked?
				echo $lang['strinvalidserverparam'];
				exit;
			}
		}

		/**
		 * Set server information.
		 * @param $key string parameter name to set, or null to replace all
		 *             params with the assoc-array in $value.
		 * @param $value string the new value, or null to unset the parameter
		 * @param $server_id string the server identifier, or null for current
		 *                   server.
		 */
		function setServerInfo($key, $value, $server_id = null)
		{
			if ($server_id === null && isset($_REQUEST['server']))
				$server_id = $_REQUEST['server'];

			if ($key === null) {
				if ($value === null)
					unset($_SESSION['webdbLogin'][$server_id ?? '']);
				else
					$_SESSION['webdbLogin'][$server_id ?? ''] = $value;
			} else {
				if ($value === null)
					unset($_SESSION['webdbLogin'][$server_id ?? ''][$key]);
				else
					$_SESSION['webdbLogin'][$server_id ?? ''][$key] = $value;
			}
		}

		/**
		 * Set the current schema
		 * @param $schema The schema name
		 * @return 0 on success
		 * @return $data->seSchema() on error
		 */
		function setCurrentSchema($schema) {
			global $data;

			$status = $data->setSchema($schema);
			if($status != 0)
				return $status;

			$_REQUEST['schema'] = $schema;
			$this->setHREF();
			return 0;
		}

		/**
		 * Save the given SQL script in the history
		 * of the database and server. Only the most recent 50 entries
		 * per server and database are kept.
		 * @param $script the SQL script to save.
		 */
		function saveScriptHistory($script) {
			$max = 50;
			list($usec, $sec) = explode(' ', microtime());
			$time = ((float)$usec + (float)$sec);
			$history =& $_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']];
			$history["$time"] = array(
				'query' => $script,
				'paginate' => (!isset($_REQUEST['paginate'])? 'f':'t'),
				'queryid' => $time,
			);
			if (count($history) > $max)
				$history = array_slice($history, -$max, null, true);
		}

		/*
		 * Output dropdown list to select server and
		 * databases form the popups windows.
		 * @param $onchange Javascript action to take when selections change.
		 */
		function printConnection($onchange) {
			global $data, $lang, $misc;

			echo "<table style=\"width: 100%\"><tr><td>\n";
			echo "<label>";
			$misc->printHelp($lang['strserver'], 'pg.server');
			echo "</label>";
			echo ": <select name=\"server\" {$onchange}>\n";

			$servers = $misc->getServers();
			foreach($servers as $info) {
				if (empty($info['username'])) continue; // not logged on this server
				echo "<option value=\"", htmlspecialchars($info['id']), "\"",
					((isset($_REQUEST['server']) && $info['id'] == $_REQUEST['server'])) ? ' selected="selected"' : '', ">",
					htmlspecialchars("{$info['desc']} ({$info['id']})"), "</option>\n";
			}
			echo "</select>\n</td><td style=\"text-align: right\">\n";

			$server_info = $misc->getServerInfo();

			// A per-database login only gets to see its own database
			if (isset($server_info['database'])) {
				include_once('./classes/ArrayRecordSet.php');
				$databases = new ArrayRecordSet(array(array('datname' => $server_info['database'])));
			}
			else {
				// Get the list of all databases
				$databases = $data->getDatabases();
			}

			if ($databases->recordCount() > 0) {
				echo "<label>";
				$misc->printHelp($lang['strdatabase'], 'pg.database');
				echo ": <select name=\"database\" {$onchange}>\n";

				//if no database was selected, user should select one
				if (!isset($_REQUEST['database']))
					echo "<option value=\"\">--</option>\n";

				while (!$databases->EOF) {
					$dbname = $databases->fields['datname'];
					echo "<option value=\"", htmlspecialchars($dbname), "\"",
						((isset($_REQUEST['database']) && $dbname == $_REQUEST['database'])) ? ' selected="selected"' : '', ">",
						htmlspecialchars($dbname), "</option>\n";
					$databases->moveNext();
				}
				echo "</select></label>\n";
			}
			else {
				echo "<input type=\"hidden\" name=\"database\" value=\"",
					htmlspecialchars($server_info['defaultdb']), "\" />\n";
			}

			echo "</td></tr></table>\n";
		}

		/**
		 * returns an array representing FKs definition for a table, sorted by fields
		 * or by constraint.
		 * @param $table The table to retrieve FK constraints from
		 * @returns the array of FK definition:
		 *   array(
		 *     'byconstr' => array(
		 *       constrain id => array(
		 *         confrelid => foreign relation oid
		 *         f_schema => foreign schema name
		 *         f_table => foreign table name
		 *         pattnums => array of parent's fields nums
		 *         pattnames => array of parent's fields names
		 *         fattnames => array of foreign attributes names
		 *       )
		 *     ),
		 *     'byfield' => array(
		 *       attribute num => array (constraint id, ...)
		 *     ),
		 *     'code' => HTML/js code to include in the page for auto-completion
		 *   )
		 **/
		function getAutocompleteFKProperties($table) {
			global $data;

			$fksprops = array(
				'byconstr' => array(),
				'byfield' => array(),
				'code' => ''
			);

			$constrs = $data->getConstraintsWithFields($table);

			if (!$constrs->EOF) {
				$conrelid = $constrs->fields['conrelid'];
				while(!$constrs->EOF) {
					if ($constrs->fields['contype'] == 'f') {
						if (!isset($fksprops['byconstr'][$constrs->fields['conid']])) {
							$fksprops['byconstr'][$constrs->fields['conid']] = array (
								'confrelid' => $constrs->fields['confrelid'],
								'f_table' => $constrs->fields['f_table'],
								'f_schema' => $constrs->fields['f_schema'],
								'pattnums' => array(),
								'pattnames' => array(),
								'fattnames' => array()
							);
						}

						$fksprops['byconstr'][$constrs->fields['conid']]['pattnums'][] = $constrs->fields['p_attnum'];
						$fksprops['byconstr'][$constrs->fields['conid']]['pattnames'][] = $constrs->fields['p_field'];
						$fksprops['byconstr'][$constrs->fields['conid']]['fattnames'][] = $constrs->fields['f_field'];

						if (!isset($fksprops['byfield'][$constrs->fields['p_attnum']]))
							$fksprops['byfield'][$constrs->fields['p_attnum']] = array();
						$fksprops['byfield'][$constrs->fields['p_attnum']][] = $constrs->fields['conid'];
					}
					$constrs->moveNext();
				}

				$fksprops['code'] = "<script type=\"text/javascript\">\n";
				$fksprops['code'] .= "var constrs = {};\n";
				foreach ($fksprops['byconstr'] as $conid => $props) {
					$fksprops['code'] .= "constrs.constr_{$conid} = {\n";
					$fksprops['code'] .= 'pattnums: ['. implode(',',$props['pattnums']) ."],\n";
					$fksprops['code'] .= "f_table:'". addslashes(htmlentities($props['f_table'], ENT_QUOTES, 'UTF-8')) ."',\n";
					$fksprops['code'] .= "f_schema:'". addslashes(htmlentities($props['f_schema'], ENT_QUOTES, 'UTF-8')) ."',\n";
					$_ = '';
					foreach ($props['pattnames'] as $n) {
						$_ .= ",'". htmlentities($n, ENT_QUOTES, 'UTF-8') ."'";
					}
					$fksprops['code'] .= 'pattnames: ['. substr($_, 1) ."],\n";

					$_ = '';
					foreach ($props['fattnames'] as $n) {
						$_ .= ",'". htmlentities($n, ENT_QUOTES, 'UTF-8') ."'";
					}

					$fksprops['code'] .= 'fattnames: ['. substr($_, 1) ."]\n";
					$fksprops['code'] .= "};\n";
				}

				$fksprops['code'] .= "var attrs = {};\n";
				foreach ($fksprops['byfield'] as $attnum => $cstrs ) {
					$fksprops['code'] .= "attrs.attr_{$attnum} = [". implode(',', $fksprops['byfield'][$attnum]) ."];\n";
				}

				$fksprops['code'] .= "var table='". addslashes(htmlentities($table, ENT_QUOTES, 'UTF-8')) ."';";
				$fksprops['code'] .= "var server='". htmlentities($_REQUEST['server'], ENT_QUOTES, 'UTF-8') ."';";
				$fksprops['code'] .= "var database='". addslashes(htmlentities($_REQUEST['database'], ENT_QUOTES, 'UTF-8')) ."';";
				$fksprops['code'] .= "</script>\n";

				$fksprops['code'] .= '<div id="fkbg"></div>';
				$fksprops['code'] .= '<div id="fklist"></div>';
				$fksprops['code'] .= '<script src="' . htmlspecialchars($this->asset('js/ac_insert_row.js')) . '"></script>';
			}

			else /* we have no foreign keys on this table */
				return false;

			return $fksprops;
		}
	}
