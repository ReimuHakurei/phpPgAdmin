<?php

	/**
	 * History of the SQL run on each database (the History link of the top bar)
	 *
	 * $Id: history.php,v 1.3 2008/01/10 19:37:07 xzilla Exp $
	 */

	// Include application functions
	include_once('./libraries/lib.inc.php');

	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : '';

	/**
	 * Prints the page header, trail and title
	 */
	function _printPageStart() {
		global $misc, $lang;

		$misc->printHeader($lang['strhistory']);
		$misc->printBody();
		$misc->printTrail(isset($_REQUEST['database']) ? 'database' : 'server');
		$misc->printTitle($lang['strhistory']);
	}

	function doDefault() {
		global $misc, $lang;

		_printPageStart();

		// Changing the server or database shows its history; changing the
		// server forgets the database, it may not exist there.
		echo "<form action=\"history.php\" method=\"get\" id=\"history-connection\">\n";
		$misc->printConnection("onchange=\"if (this.name == 'server') { var db = this.form.elements.database; if (db) db.disabled = true; } if (this.form.requestSubmit) this.form.requestSubmit(); else this.form.submit();\"");
		echo "<noscript><p><input type=\"submit\" value=\"", htmlspecialchars($lang['strchangeconnection']), "\" /></p></noscript>\n";
		echo "</form>\n";
	
		if (!isset($_REQUEST['database'])) {
			echo "<p>{$lang['strnodatabaseselected']}</p>\n";
			return;
		}
			
		if (isset($_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']])) {
			include_once('classes/ArrayRecordSet.php');
						   
			// Most recent first
			$history = new ArrayRecordSet(array_reverse($_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']], true));
			
			$columns = array(
				'query' => array(
					'title' => $lang['strsql'],
					'field' => field('query'),
				),
				'paginate' => array(
					'title' => $lang['strpaginate'],
					'field' => field('paginate'),
					'type' => 'yesno',
				),
				'actions' => array(
					'title' => $lang['stractions'],
				),
			);

			$actions = array(
				'run' => array(
					'content' => $lang['strexecute'],
					'attr'=> array (
						'href' => array (
							'url' => 'sql.php',
							'urlvars' => array (
								'subject' => 'history',
								'nohistory' => 't',
								'queryid' => field('queryid'),
								'paginate' => field('paginate')
							)
						)
					)
				),
				'remove' => array(
					'content' => $lang['strdelete'],
					'attr'=> array (
						'href' => array (
							'url' => 'history.php',
							'urlvars' => array (
								'action' => 'confdelhistory',
								'queryid' => field('queryid'),
							)
						)
					)
				)
			);

			$misc->printTable($history, $columns, $actions, 'history-history', $lang['strnohistory']);
		}
		else echo "<p>{$lang['strnohistory']}</p>\n";

		$navlinks = array (
			'refresh' => array (
				'attr'=> array (
					'href' => array (
						'url' => 'history.php',
						'urlvars' => array (
							'action' => 'history',
							'server' => $_REQUEST['server'],
							'database' => $_REQUEST['database'],
						)
					)
				),
				'content' => $lang['strrefresh']
			)
		);

		if (isset($_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']]) 
				&& count($_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']])) {
			$navlinks['download'] = array (
				'attr'=> array (
					'href' => array (
						'url' => 'history.php',
						'urlvars' => array (
							'action' => 'download',
							'server' => $_REQUEST['server'],
							'database' => $_REQUEST['database']
						)
					)
				),
				'content' => $lang['strdownload']
			);
			$navlinks['clear'] = array (
				'attr'=> array (
					'href' => array (
						'url' => 'history.php',
						'urlvars' => array(
							'action' => 'confclearhistory',
							'server' => $_REQUEST['server'],
							'database' => $_REQUEST['database']
						)
					)
				),
				'content' => $lang['strclearhistory']
			);
		}

		$misc->printNavLinks($navlinks, 'history-history', get_defined_vars());
	}

	function doDelHistory($qid, $confirm) {
		global $misc, $lang;

		if ($confirm) {
			_printPageStart();

			echo "<h3>{$lang['strdelhistory']}</h3>\n";
			echo "<p>{$lang['strconfdelhistory']}</p>\n";

			echo "<pre>", htmlentities($_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']][$qid]['query'], ENT_QUOTES, 'UTF-8'), "</pre>";
			echo "<form action=\"history.php\" method=\"post\">\n";
			echo "<input type=\"hidden\" name=\"action\" value=\"delhistory\" />\n";
			echo "<input type=\"hidden\" name=\"queryid\" value=\"$qid\" />\n";
			echo $misc->form;
			echo "<input type=\"submit\" name=\"yes\" value=\"{$lang['stryes']}\" />\n";
			echo "<input type=\"submit\" name=\"no\" value=\"{$lang['strno']}\" />\n";
			echo "</form>\n";
		}
		else
			unset($_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']][$qid]);
	}       

	function doClearHistory($confirm) {
		global $misc, $lang;

		if ($confirm) {
			_printPageStart();

			echo "<h3>{$lang['strclearhistory']}</h3>\n";
			echo "<p>{$lang['strconfclearhistory']}</p>\n";

			echo "<form action=\"history.php\" method=\"post\">\n";
			echo "<input type=\"hidden\" name=\"action\" value=\"clearhistory\" />\n";
			echo $misc->form;
			echo "<input type=\"submit\" name=\"yes\" value=\"{$lang['stryes']}\" />\n";
			echo "<input type=\"submit\" name=\"no\" value=\"{$lang['strno']}\" />\n";
			echo "</form>\n";
		}
		else
			unset($_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']]);
	}

	function doDownloadHistory() {
		header('Content-Type: application/download');
		$datetime = date('YmdHis');
		header("Content-Disposition: attachment; filename=history{$datetime}.sql");

		foreach ($_SESSION['history'][$_REQUEST['server']][$_REQUEST['database']] as $queries) {
			$query = rtrim($queries['query']);
			echo $query;
			if (substr($query, -1) != ';')
				echo ';';
			echo "\n";
		}

		exit;
	}
	
	switch ($action) {
		case 'confdelhistory':
			doDelHistory($_REQUEST['queryid'], true);
			break;
		case 'delhistory':
			if (isset($_POST['yes'])) doDelHistory($_REQUEST['queryid'], false);
			doDefault();
			break;
		case 'confclearhistory':
			doClearHistory(true);
			break;
		case 'clearhistory':
			if (isset($_POST['yes'])) doClearHistory(false);
			doDefault();
			break;
		case 'download':
			doDownloadHistory();
			break;
		default:
			doDefault();
	}

	$misc->printFooter();
	
?>
