<?php

	/**
	 * Function library read in upon startup
	 *
	 * $Id: lib.inc.php,v 1.123 2008/04/06 01:10:35 xzilla Exp $
	 */

	include_once('./libraries/decorator.inc.php');
	include_once('./libraries/session.inc.php');
	include_once('./lang/translations.php');

	// Report everything.
	error_reporting(E_ALL);

	// Application name
	$appName = 'phpPgAdmin';

	// Application version: this fork's release tag plus "-mod" (it is based on
	// upstream 7.14.8-mod). Release images set PPA_RELEASE from the git tag at
	// build time; without it the latest release below is shown.
	$appVersion = 'v1.1.0-mod';
	$appRelease = getenv('PPA_RELEASE');
	if (is_string($appRelease) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,39}$/', $appRelease)) {
		$appVersion = "{$appRelease}-mod";
	}
	unset($appRelease);

	// PostgreSQL and PHP minimum version
	global $postgresqlMinVer;
	$postgresqlMinVer = '7.4';
	$phpMinVer = '7.4';

	// Check the version of PHP
	if (version_compare(phpversion(), $phpMinVer, '<'))
		exit(sprintf('Version of PHP not supported. Please upgrade to version %s or later.', $phpMinVer));

	// Check to see if the configuration file exists, if not, explain
	if (file_exists('conf/config.inc.php')) {
		$conf = array();
		include('./conf/config.inc.php');
	}
	else {
		echo 'Configuration error: Copy conf/config.inc.php-dist to conf/config.inc.php and edit appropriately.';
		exit;
	}

	// Configuration file version.  If this is greater than that in config.inc.php, then
	// the app will refuse to run.  This and $conf['version'] should be incremented whenever
	// backwards incompatible changes are made to config.inc.php-dist.
	$conf['base_version'] = 16;

	// Always include english.php, since it's the master language file
	if (!isset($conf['default_lang'])) $conf['default_lang'] = 'english';
	$lang = array();
	require_once('./lang/english.php');

	// Create Misc class references
	require_once('./classes/Misc.php');
	$misc = new Misc();

	// Session start: if extra_session_security is on, make sure cookie_samesite
	// is on (exit if we fail); otherwise, just start the session
	$our_session_name = 'PPA_ID';
	if (($conf['extra_session_security'] ?? true) === true) {
		if (ini_get('session.auto_start')) {
			// If session.auto_start is on, and the session doesn't have
			// session.cookie_samesite set, destroy and re-create the session
			if (session_name() !== $our_session_name) {
				$setting = strtolower(ini_get('session.cookie_samesite'));

				if ($setting !== 'lax' && $setting !== 'strict') {
					session_destroy();
					session_name($our_session_name);
					ini_set('session.cookie_samesite', 'Strict');
					session_start();
				}
			}
		} else {
			session_name($our_session_name);
			ini_set('session.cookie_samesite', 'Strict');
			session_start();
		}
	} else {
		if (!ini_get('session.auto_start')) {
			session_name($our_session_name);
			session_start();
		}
	}

	$misc->setHREF();
	$misc->setForm();

	// Enforce PHP environment
	ini_set('arg_separator.output', '&amp;');

	foreach ($_POST as $_login_key => $_login_val) {
		if (strncmp((string) $_login_key, 'login', 5) === 0 && !is_scalar($_login_val))
			unset($_POST[$_login_key]);
	}
	// If login action is set, then set session variables
	if (isset($_POST['loginServer']) && isset($_POST['loginUsername']) &&
		isset($_POST['loginPassword_'.md5($_POST['loginServer'])])) {

		$_server_info = $misc->getServerInfo($_POST['loginServer']);

		$_server_info['username'] = $_POST['loginUsername'];
		$_server_info['password'] = $_POST['loginPassword_'.md5($_POST['loginServer'])];

		$misc->setServerInfo(null, $_server_info, $_POST['loginServer']);

		// Check for shared credentials
		if (isset($_POST['loginShared'])) {
			$_SESSION['sharedUsername'] = $_POST['loginUsername'];
			$_SESSION['sharedPassword'] = $_POST['loginPassword_'.md5($_POST['loginServer'])];
		}

		$_reload_browser = true;
	}

	/* select the theme */
	unset($_theme);
	if (!isset($conf['theme']))
		$conf['theme'] = 'default';

	// Theme names come from the request or a cookie and end up in a <link>
	// tag, so only accept plain directory names.
	$_valid_theme = function ($name) {
		return is_string($name) && preg_match('/^[A-Za-z0-9_-]+$/', $name)
			&& is_file("./themes/{$name}/global.css");
	};

	// 1. Check for the theme from a request var
	if (isset($_REQUEST['theme']) && $_valid_theme($_REQUEST['theme'])) {
		/* save the selected theme in cookie for a year */
		setcookie('ppaTheme', $_REQUEST['theme'], time()+31536000);
		$_theme = $_SESSION['ppaTheme'] = $conf['theme'] = $_REQUEST['theme'];
	}

	// 2. Check for theme session var
	if (!isset($_theme) && isset($_SESSION['ppaTheme']) && $_valid_theme($_SESSION['ppaTheme'])) {
		$conf['theme']  = $_SESSION['ppaTheme'];
	}

	// 3. Check for theme in cookie var
	if (!isset($_theme) && isset($_COOKIE['ppaTheme']) && $_valid_theme($_COOKIE['ppaTheme'])) {
		$conf['theme']  = $_COOKIE['ppaTheme'];
	}

	// 4. Check for theme by server/db/user
	$info = $misc->getServerInfo();

	if (!is_null($info)) {
		$_theme = '';

		if ( (isset($info['theme']['default']))
			and $_valid_theme($info['theme']['default'])
		)
			$_theme = $info['theme']['default'];

		if ( isset($_REQUEST['database'])
			and isset($info['theme']['db'][$_REQUEST['database']])
			and $_valid_theme($info['theme']['db'][$_REQUEST['database']])
		)
			$_theme = $info['theme']['db'][$_REQUEST['database']];

		if ( isset($info['username'])
			and isset($info['theme']['user'][$info['username']])
			and $_valid_theme($info['theme']['user'][$info['username']])
		)
			$_theme = $info['theme']['user'][$info['username']];

		if ($_theme !== '') {
			setcookie('ppaTheme', $_theme, time()+31536000);
			$conf['theme'] = $_theme;
		}
	}

	// Determine language file to import:
	unset($_language);

	// 1. Check for the language from a request var
	if (isset($_REQUEST['language']) && isset($appLangFiles[$_REQUEST['language']])) {
		/* save the selected language in cookie for a year */
		setcookie('webdbLanguage', $_REQUEST['language'], time()+31536000);
		$_language = $_REQUEST['language'];
	}

	// 2. Check for language session var
	if (!isset($_language) && isset($_SESSION['webdbLanguage']) && isset($appLangFiles[$_SESSION['webdbLanguage']])) {
		$_language = $_SESSION['webdbLanguage'];
	}

	// 3. Check for language in cookie var
	if (!isset($_language) && isset($_COOKIE['webdbLanguage']) && isset($appLangFiles[$_COOKIE['webdbLanguage']])) {
		$_language  = $_COOKIE['webdbLanguage'];
	}

	// 4. Check for acceptable languages in HTTP_ACCEPT_LANGUAGE var
	if (!isset($_language) && $conf['default_lang'] == 'auto' && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		// extract acceptable language tags
		// (http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4)
		preg_match_all('/\s*([a-z]{1,8}(?:-[a-z]{1,8})*)(?:;q=([01](?:.[0-9]{0,3})?))?\s*(?:,|$)/', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $_m, PREG_SET_ORDER);
		foreach($_m as $_l) {  // $_l[1] = language tag, [2] = quality
			if (!isset($_l[2])) $_l[2] = 1;  // Default quality to 1
			if ($_l[2] > 0 && $_l[2] <= 1 && isset($availableLanguages[$_l[1]])) {
				// Build up array of (quality => language_file)
				$_acceptLang[$_l[2]] = $availableLanguages[$_l[1]];
			}
		}
		unset($_m);
		unset($_l);
		if (isset($_acceptLang)) {
			// Sort acceptable languages by quality
			krsort($_acceptLang, SORT_NUMERIC);
			$_language = reset($_acceptLang);
			unset($_acceptLang);
		}
	}

	// 5. Otherwise resort to the default set in the config file
	if (!isset($_language) && $conf['default_lang'] != 'auto' && isset($appLangFiles[$conf['default_lang']])) {
		$_language = $conf['default_lang'];
	}

	// 6. Otherwise, default to english.
	if (!isset($_language))
		$_language = 'english';


	// Import the language file
	if (isset($_language)) {
		include("./lang/{$_language}.php");
		$_SESSION['webdbLanguage'] = $_language;
	}

	// The bootstrap's own session work is done. Release the session lock so
	// a slow page doesn't block the user's other requests; later changes to
	// $_SESSION are still saved at the end of the request.
	ppa_session_release();

	// Check for config file version mismatch
	if (!isset($conf['version']) || $conf['base_version'] > $conf['version']) {
		echo $lang['strbadconfig'];
		exit;
	}

	// Check php libraries
	$php_libraries_requirements = [
		// required_function => name_of_the_php_library
		'pg_connect' => 'pgsql',
		'mb_strlen' => 'mbstring'];
	$missing_libraries = [];
	foreach($php_libraries_requirements as $funcname => $lib)
		if (!function_exists($funcname))
			$missing_libraries[] = $lib;
	if ($missing_libraries) {
		$missing_list = implode(', ', $missing_libraries);
		$error_missing_template_string = count($missing_libraries) <= 1 ? $lang['strlibnotfound'] : $lang['strlibnotfound_plural'];
		printf($error_missing_template_string, $missing_list);
		exit;
	}

	// Manage the plugins
	require_once('./classes/PluginManager.php');

	// Create data accessor object, if necessary
	if (!isset($_no_db_connection)) {
		if (!isset($_REQUEST['server'])) {
			echo $lang['strnoserversupplied'];
			exit;
	    }
		$_server_info = $misc->getServerInfo();

		/* starting with PostgreSQL 9.0, we can set the application name.
		 * Before the first connection the version isn't known yet: assume a
		 * current server (libpq retries without it if the server rejects it). */
		if (!isset($_server_info['pgVersion']) || version_compare($_server_info['pgVersion'], '9', '>='))
			putenv("PGAPPNAME={$appName}_{$appVersion}");

		// Don't hang on a server that doesn't answer.
		if (getenv('PGCONNECT_TIMEOUT') === false)
			putenv('PGCONNECT_TIMEOUT=10');

		// A per-database login only gets that one database. Server-wide pages
		// and other databases send you back to it instead.
		if (isset($_server_info['database'])) {
			$_server_wide = in_array(basename($_SERVER['SCRIPT_NAME']),
				array('all_db.php', 'roles.php', 'users.php', 'groups.php', 'tablespaces.php'), true);
			if ($_server_wide
				|| (isset($_REQUEST['subject']) && $_REQUEST['subject'] === 'server')
				|| (isset($_REQUEST['database']) && $_REQUEST['database'] !== $_server_info['database'])
			) {
				header('Location: redirect.php?' . http_build_query(array(
					'subject' => 'database',
					'server' => $_REQUEST['server'],
					'database' => $_server_info['database'],
				), '', '&'));
				exit;
			}
		}

		// Redirect to the login form if not logged in
		if (!isset($_server_info['username'])) {
			include('./login.php');
			exit;
		}

		// Connect to the current database, or if one is not specified
		// then connect to the default database.
		if (isset($_REQUEST['database']))
			$_curr_db = $_REQUEST['database'];
		else
			$_curr_db = $_server_info['defaultdb'];

		include_once('./classes/database/Connection.php');

		// Connect to database and set the global $data variable
		$data = $misc->getDatabaseAccessor($_curr_db);

		// If schema is defined and database supports schemas, then set the
		// schema explicitly.
		if (isset($_REQUEST['database']) && isset($_REQUEST['schema'])) {
			$status = $data->setSchema($_REQUEST['schema']);
			if ($status != 0) {
				echo $lang['strbadschema'];
				exit;
			}
		}
	}

	$plugin_manager = new PluginManager($_language);

	/**
	 * Safe unserializer wrapper
	 *
	 * It does not unserialize data containing objects
	 *
	 * Function from phpMyAdmin version 5.2.1
	 *
	 * @param string $data Data to unserialize
	 *
	 * @return mixed|null
	 */
	function safeUnserialize(string $data) {
		/* validate serialized data */
		$length = strlen($data);
		$depth = 0;
		for ($i = 0; $i < $length; $i++) {
			$value = $data[$i];

			switch ($value) {
				case '}':
					/* end of array */
					if ($depth <= 0) {
						return null;
					}

					$depth--;
					break;
				case 's':
					/* string */
					// parse sting length
					$strlen = intval(substr($data, $i + 2));
					// string start
					$i = strpos($data, ':', $i + 2);
					if ($i === false) {
						return null;
					}

					// skip string, quotes and ;
					$i += 2 + $strlen + 1;
					if ($data[$i] !== ';') {
						return null;
					}

					break;

				case 'b':
				case 'i':
				case 'd':
					/* bool, integer or double */
					// skip value to separator
					$i = strpos($data, ';', $i);
					if ($i === false) {
						return null;
					}

					break;
				case 'a':
					/* array */
					// find array start
					$i = strpos($data, '{', $i);
					if ($i === false) {
						return null;
					}

					// remember nesting
					$depth++;
					break;
				case 'N':
					/* null */
					// skip to end
					$i = strpos($data, ';', $i);
					if ($i === false) {
						return null;
					}

					break;
				default:
					/* any other elements are not wanted */
					return null;
			}
		}

		// check unterminated arrays
		if ($depth > 0) {
			return null;
		}

		return unserialize($data);
	}
