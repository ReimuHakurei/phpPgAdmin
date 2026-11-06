<?php

/**
 * Read-only, forward-only recordset over a server-side cursor, for exports.
 *
 * pg_query() buffers the whole result in memory, which runs out of memory on
 * big tables. This fetches the rows of an already DECLAREd cursor in batches
 * and offers the part of the ADOdb recordset interface dataexport.php uses
 * (EOF, fields, moveNext(), fetchField()). Output is flushed after every
 * batch so the download starts right away.
 */
class DumpCursor {

	public $EOF = true;
	public $fields = false;

	private $conn;
	private $name;
	private $batch;
	private $rs = null;
	private $open = true;
	private $commit;

	/**
	 * @param $conn ADOdb connection, inside the transaction that declared the cursor
	 * @param $name The cursor name (a plain identifier)
	 * @param $batch Rows per FETCH
	 * @param $commit Commit the transaction once the cursor is done (it was
	 *        started just for the cursor)
	 */
	function __construct($conn, $name, $batch = 1000, $commit = false) {
		$this->conn = $conn;
		$this->name = $name;
		$this->batch = (int) $batch;
		$this->commit = $commit;
		$this->fetch();
	}

	private function fetch() {
		$rs = $this->conn->Execute("FETCH {$this->batch} FROM {$this->name}");
		if ($this->rs) $this->rs->Close();
		$this->rs = $rs ? $rs : null;

		if (!$rs) {
			// The error was reported already and the transaction is aborted:
			// CLOSE would only fail again.
			$this->open = false;
			if ($this->commit) $this->conn->Execute('ROLLBACK');
		}
		if (!$rs || $rs->EOF) {
			$this->EOF = true;
			$this->fields = false;
			$this->close();
		}
		else {
			$this->EOF = false;
			$this->fields = $rs->fields;
		}
	}

	function moveNext() {
		if ($this->EOF) return false;

		$this->rs->moveNext();
		if ($this->rs->EOF) {
			// Send what we have before waiting for the next batch.
			if (ob_get_level() > 0) ob_flush();
			flush();
			$this->fetch();
		}
		else {
			$this->fields = $this->rs->fields;
		}
		return !$this->EOF;
	}

	function fetchField($fieldOffset = -1) {
		return $this->rs ? $this->rs->fetchField($fieldOffset) : false;
	}

	function recordCount() {
		return -1;
	}

	function close() {
		if ($this->open) {
			$this->open = false;
			$this->conn->Execute("CLOSE {$this->name}");
			if ($this->commit) $this->conn->Execute('COMMIT');
		}
	}
}

?>
