<?php

	/**
	 * Release the session lock early.
	 *
	 * PHP's file sessions are locked from session_start() until the session is
	 * closed, which used to be the end of the request: one slow query froze the
	 * browser tree, every other tab and the processes page of the same user.
	 *
	 * ppa_session_release() is called by lib.inc.php once its own session work
	 * (login, theme, language) is done. It takes a snapshot of $_SESSION and
	 * closes the session. Code may still change $_SESSION afterwards (last tab,
	 * query history, server info, ...): at shutdown the changes since the
	 * snapshot are computed and applied to a freshly read copy of the session,
	 * so whatever concurrent requests stored in the meantime is kept.
	 */

	/**
	 * Snapshot and close the session. Must be called before any output.
	 */
	function ppa_session_release() {
		if (session_status() !== PHP_SESSION_ACTIVE) return;

		$GLOBALS['_ppa_session'] = array(
			'id' => session_id(),
			'snapshot' => $_SESSION,
		);
		session_write_close();

		// The session is reopened at shutdown, after output and headers went
		// out. The cookie and the cache headers were sent by the first
		// session_start() already, so don't try to send them again.
		ini_set('session.use_cookies', '0');
		ini_set('session.cache_limiter', '');

		register_shutdown_function('ppa_session_shutdown');
	}

	/**
	 * Shutdown function: write back what changed in $_SESSION since
	 * ppa_session_release().
	 */
	function ppa_session_shutdown() {
		if (!isset($GLOBALS['_ppa_session'])) return;
		$state = $GLOBALS['_ppa_session'];
		unset($GLOBALS['_ppa_session']);

		// Someone started the session again themselves; it is theirs to close.
		if (session_status() === PHP_SESSION_ACTIVE) return;

		$final = (isset($_SESSION) && is_array($_SESSION)) ? $_SESSION : array();
		$changes = array();
		ppa_session_diff($state['snapshot'], $final, array(), $changes);
		if (!$changes || $state['id'] === '') return;

		session_id($state['id']);
		if (!session_start()) return;

		// With session.use_strict_mode a session that was destroyed in the
		// meantime (logout in another tab) comes back under a new id. Don't
		// bring it back to life.
		if (session_id() !== $state['id']) {
			session_abort();
			return;
		}

		foreach ($changes as $change) {
			ppa_session_apply($_SESSION, $change[0], $change[1], $change[2]);
		}
		session_write_close();
	}

	/**
	 * Collect the differences between two arrays, recursing into arrays.
	 * Each change is array(path, 'set', new value) for an added or changed
	 * key, array(path, 'unset', null) for a removed one and
	 * array(path, 'array', null) for a key that has to be an array.
	 *
	 * New arrays are recursed into as well, so that two requests adding
	 * entries under the same new key (e.g. the first query history) both
	 * keep theirs.
	 */
	function ppa_session_diff(array $old, array $new, array $path, array &$changes) {
		foreach ($new as $key => $value) {
			$p = $path;
			$p[] = $key;
			$exists = array_key_exists($key, $old);
			if (is_array($value)) {
				if ($exists && is_array($old[$key])) {
					ppa_session_diff($old[$key], $value, $p, $changes);
				} else {
					$changes[] = array($p, 'array', null);
					ppa_session_diff(array(), $value, $p, $changes);
				}
			} elseif (!$exists || $value !== $old[$key]) {
				$changes[] = array($p, 'set', $value);
			}
		}
		foreach ($old as $key => $value) {
			if (!array_key_exists($key, $new)) {
				$p = $path;
				$p[] = $key;
				$changes[] = array($p, 'unset', null);
			}
		}
	}

	/**
	 * Apply one change from ppa_session_diff() to an array.
	 */
	function ppa_session_apply(array &$data, array $path, $op, $value) {
		$last = array_pop($path);
		$node =& $data;
		foreach ($path as $key) {
			if (!isset($node[$key]) || !is_array($node[$key])) {
				if ($op === 'unset') return; // already gone
				$node[$key] = array();
			}
			$node =& $node[$key];
		}
		if ($op === 'set') {
			$node[$last] = $value;
		} elseif ($op === 'array') {
			if (!isset($node[$last]) || !is_array($node[$last])) $node[$last] = array();
		} else {
			unset($node[$last]);
		}
	}
