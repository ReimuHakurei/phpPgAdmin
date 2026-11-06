<?php

	/**
	 * SQL editor and object finder (the SQL and Find links of the top bar)
	 *
	 * $Id: sqledit.php,v 1.40 2008/01/10 19:37:07 xzilla Exp $
	 */

	// Include application functions
	include_once('./libraries/lib.inc.php');
	include_once('./classes/SqlEditor.php');

	$action = (isset($_REQUEST['action'])) ? $_REQUEST['action'] : '';
	if (!isset($msg)) $msg = '';

	/**
	 * Prints the page header, trail and title
	 */
	function _printPageStart($title, $script = null) {
		global $misc;

		$misc->printHeader($title, $script);
		$misc->printBody();
		$misc->printTrail(isset($_REQUEST['database']) ? 'database' : 'server');
		$misc->printTitle($title);
	}

	/**
	 * Prints the form that switches server and database: it reloads this
	 * page for the new connection, so that the right encoding and search
	 * path are used. Must be printed outside of any other form.
	 */
	function _printConnectionForm() {
		global $action, $lang;

		echo "<form id=\"sqledit-connection\" action=\"sqledit.php\" method=\"get\">\n";
		echo "<input type=\"hidden\" name=\"action\" value=\"", htmlspecialchars($action == 'find' ? 'find' : 'sql'), "\" />\n";
		echo "<noscript><p><input type=\"submit\" value=\"", htmlspecialchars($lang['strchangeconnection']), "\" /></p></noscript>\n";
		echo "</form>\n";
	}

	/**
	 * Prints the server and database selectors. They belong to the
	 * connection form (see above) through their form attribute; the form they
	 * are printed in gets the current connection as hidden fields.
	 */
	function _printConnection() {
		global $misc;

		// Changing the server forgets the database, it may not exist there
		$misc->printConnection("form=\"sqledit-connection\" onchange=\"if (this.name == 'server') { var db = this.form.elements.database; if (db) db.disabled = true; } if (this.form.requestSubmit) this.form.requestSubmit(); else this.form.submit();\"");
		echo $misc->form;
		if (!isset($_REQUEST['database']))
			echo "<input type=\"hidden\" name=\"database\" value=\"\" />\n";
	}
	
	/**
	 * Searches for a named database object
	 */
	function doFind() {
		global $data, $misc;
		global $lang, $conf;
		
		if (!isset($_REQUEST['term'])) $_REQUEST['term'] = '';
		if (!isset($_REQUEST['filter'])) $_REQUEST['filter'] = '';
		
		_printPageStart($lang['strfind']);
		_printConnectionForm();

		echo "<form action=\"database.php\" method=\"post\" id=\"sqledit-find\">\n";
		_printConnection();
		echo "<p><input name=\"term\" value=\"", htmlspecialchars($_REQUEST['term']),
			"\" size=\"32\" maxlength=\"{$data->_maxNameLen}\" autofocus=\"autofocus\" />\n";
			
		// Output list of filters.  This is complex due to all the 'has' and 'conf' feature possibilities
		echo "<select name=\"filter\">\n";
		echo "\t<option value=\"\"", ($_REQUEST['filter'] == '') ? ' selected="selected"' : '', ">{$lang['strallobjects']}</option>\n";
		echo "\t<option value=\"SCHEMA\"", ($_REQUEST['filter'] == 'SCHEMA') ? ' selected="selected"' : '', ">{$lang['strschemas']}</option>\n";
		echo "\t<option value=\"TABLE\"", ($_REQUEST['filter'] == 'TABLE') ? ' selected="selected"' : '', ">{$lang['strtables']}</option>\n";
		echo "\t<option value=\"VIEW\"", ($_REQUEST['filter'] == 'VIEW') ? ' selected="selected"' : '', ">{$lang['strviews']}</option>\n";
		echo "\t<option value=\"SEQUENCE\"", ($_REQUEST['filter'] == 'SEQUENCE') ? ' selected="selected"' : '', ">{$lang['strsequences']}</option>\n";
		echo "\t<option value=\"COLUMN\"", ($_REQUEST['filter'] == 'COLUMN') ? ' selected="selected"' : '', ">{$lang['strcolumns']}</option>\n";
		echo "\t<option value=\"RULE\"", ($_REQUEST['filter'] == 'RULE') ? ' selected="selected"' : '', ">{$lang['strrules']}</option>\n";
		echo "\t<option value=\"INDEX\"", ($_REQUEST['filter'] == 'INDEX') ? ' selected="selected"' : '', ">{$lang['strindexes']}</option>\n";
		echo "\t<option value=\"TRIGGER\"", ($_REQUEST['filter'] == 'TRIGGER') ? ' selected="selected"' : '', ">{$lang['strtriggers']}</option>\n";
		echo "\t<option value=\"CONSTRAINT\"", ($_REQUEST['filter'] == 'CONSTRAINT') ? ' selected="selected"' : '', ">{$lang['strconstraints']}</option>\n";
		echo "\t<option value=\"FUNCTION\"", ($_REQUEST['filter'] == 'FUNCTION') ? ' selected="selected"' : '', ">{$lang['strfunctions']}</option>\n";
		echo "\t<option value=\"DOMAIN\"", ($_REQUEST['filter'] == 'DOMAIN') ? ' selected="selected"' : '', ">{$lang['strdomains']}</option>\n";
		if ($conf['show_advanced']) {
			echo "\t<option value=\"AGGREGATE\"", ($_REQUEST['filter'] == 'AGGREGATE') ? ' selected="selected"' : '', ">{$lang['straggregates']}</option>\n";
			echo "\t<option value=\"TYPE\"", ($_REQUEST['filter'] == 'TYPE') ? ' selected="selected"' : '', ">{$lang['strtypes']}</option>\n";
			echo "\t<option value=\"OPERATOR\"", ($_REQUEST['filter'] == 'OPERATOR') ? ' selected="selected"' : '', ">{$lang['stroperators']}</option>\n";
			echo "\t<option value=\"OPCLASS\"", ($_REQUEST['filter'] == 'OPCLASS') ? ' selected="selected"' : '', ">{$lang['stropclasses']}</option>\n";
			echo "\t<option value=\"CONVERSION\"", ($_REQUEST['filter'] == 'CONVERSION') ? ' selected="selected"' : '', ">{$lang['strconversions']}</option>\n";
			echo "\t<option value=\"LANGUAGE\"", ($_REQUEST['filter'] == 'LANGUAGE') ? ' selected="selected"' : '', ">{$lang['strlanguages']}</option>\n";
		}
		echo "</select>\n";
					
		echo "<input type=\"submit\" value=\"{$lang['strfind']}\" />\n";
		echo "<input type=\"hidden\" name=\"action\" value=\"find\" /></p>\n";
		echo "</form>\n";
	}

	/**
	 * Allow execution of arbitrary SQL statements on a database
	 */
	function doDefault() {
		global $data, $misc;
		global $lang; 
		
		if (!isset($_SESSION['sqlquery'])) $_SESSION['sqlquery'] = '';
		
		_printPageStart($lang['strsql'], SqlEditor::head());
		_printConnectionForm();

		echo "<form action=\"sql.php\" method=\"post\" enctype=\"multipart/form-data\" id=\"sql-editor-form\" class=\"sql-editor-form\">\n";
		_printConnection();
		echo "\n";
		if (!isset($_REQUEST['search_path']))
			$_REQUEST['search_path'] = implode(',',$data->getSearchPath());

		echo "<p><label>";
		$misc->printHelp($lang['strsearchpath'], 'pg.schema.search_path');
		echo ": <input type=\"text\" name=\"search_path\" size=\"50\" value=\"",
			htmlspecialchars($_REQUEST['search_path']), "\" /></label></p>\n";

		SqlEditor::printTextarea($_SESSION['sqlquery'], array('rows' => 12, 'autofocus' => true));
		SqlEditor::printUpload();
		SqlEditor::printPaginate(isset($_REQUEST['paginate']));
		SqlEditor::printButtons();
		echo "</form>\n";
	}

	switch ($action) {
		case 'find':
			doFind();
			break;
		case 'sql':
		default:
			doDefault();
			break;
	}

	$misc->printFooter();
	
?>
