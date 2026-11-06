/**
 * Data grid behaviour: browse and query results (display.php, sql.php).
 *
 *  - Double-click a cell to copy its value.  A clipped value (collapsed
 *    strings) carries its full text in data-full; a value too long for that
 *    has data-clipped and only its visible part can be copied.
 *  - Changing "rows per page" submits the grid form right away.
 *
 * Everything is delegated from document and bound once, so it keeps working
 * when the page content is swapped (ppa:load / ppa:unload events).
 */
(function ($) {
	'use strict';

	if (window.ppaGrid) return;

	var toastTimer = null;

	function message(table, name, fallback) {
		var text = table ? table.getAttribute('data-msg-' + name) : null;
		return text || fallback;
	}

	function toast(text, isError) {
		var $toast = $('#grid-toast');
		if (!$toast.length) {
			$toast = $('<div id="grid-toast" class="grid-toast" role="status" aria-live="polite"></div>')
				.appendTo(document.body);
		}
		$toast.text(text).toggleClass('grid-toast-error', !!isError).addClass('grid-toast-show');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () {
			$toast.removeClass('grid-toast-show');
		}, isError ? 3000 : 1500);
	}

	function copyWithTextarea(text) {
		var ta = document.createElement('textarea');
		ta.value = text;
		ta.setAttribute('readonly', '');
		ta.style.position = 'fixed';
		ta.style.top = '-1000px';
		ta.style.opacity = '0';
		document.body.appendChild(ta);
		ta.select();
		var ok = false;
		try {
			ok = document.execCommand('copy');
		} catch (e) {
			ok = false;
		}
		document.body.removeChild(ta);
		return ok;
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(text).then(null, function () {
				if (!copyWithTextarea(text)) throw new Error('copy failed');
			});
		}
		return new Promise(function (resolve, reject) {
			if (copyWithTextarea(text)) resolve();
			else reject(new Error('copy failed'));
		});
	}

	/* The value of a cell, as text */
	function cellValue(td) {
		if (td.hasAttribute('data-full')) return td.getAttribute('data-full');
		var $td = $(td);
		var $value = $td.children().not('div:has(a.fk)');
		if ($value.length === 1 && $value.is('span.null')) return 'NULL';
		if ($value.length === 1 && $value.is('span.empty')) return '';
		return $td.clone().find('a.fk').remove().end().text();
	}

	$(document).on('dblclick', 'table.data-grid > tbody > tr > td', function () {
		var td = this;
		// Action buttons (edit, delete) are not values
		if (/(^|\s)opbutton\d/.test(td.className) || $(td).find('a').not('.fk').length) return;

		var table = $(td).closest('table')[0];
		var partial = td.hasAttribute('data-clipped');

		// Double-click also selects a word; the copy covers the whole value
		if (window.getSelection) window.getSelection().removeAllRanges();

		copyText(cellValue(td)).then(function () {
			toast(partial
				? message(table, 'copied-part', 'Copied the visible part only')
				: message(table, 'copied', 'Copied'));
		}, function () {
			toast(message(table, 'copy-failed', 'Could not copy to the clipboard'), true);
		});
	});

	// Rows per page: apply at once (the Apply button stays for keyboard users)
	$(document).on('change', 'select.grid-page-size', function () {
		var form = this.form;
		if (!form) return;
		if (form.requestSubmit) form.requestSubmit();
		else form.submit();
	});

	// Leave no toast behind when the page content is replaced
	$(document).on('ppa:unload', function () {
		clearTimeout(toastTimer);
		$('#grid-toast').remove();
	});

	window.ppaGrid = { copyText: copyText };
})(jQuery);
