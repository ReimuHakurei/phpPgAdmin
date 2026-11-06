/**
 * SQL editor: turns every <textarea class="sql-editor"> into a CodeMirror
 * editor (classes/SqlEditor.php prints the textarea and its toolbar).
 *
 * The textarea stays the form field, so the form works without this script.
 * With it you get syntax highlighting, autocompletion (Ctrl+Space, and after
 * typing "."), Ctrl/Cmd+Enter to run, "run only the selection" and a menu of
 * recently run queries kept in localStorage.
 *
 * Textarea attributes read here:
 *   data-sql-run        Ctrl+Enter submits the form, selection and history work
 *   data-sql-hint       id of a <script type="application/json"> with
 *                       {"schemas": [name, ...], "tables":
 *                       [[schema, table, [column, ...], inSearchPath], ...]}
 *                       for autocompletion
 *   data-error-line/col where the last error was (1-based, in the executed SQL)
 *   data-selection      "fromLine:fromCh:toLine:toCh" to select after loading
 *
 * When only a selection is run, the textarea is disabled for the submit and
 * hidden fields carry the SQL instead: query (the selection), sqleditor_text
 * (the whole editor text) and sqleditor_selection. They are swapped in by a
 * capture-phase submit listener on window and swapped back in a setTimeout,
 * so whoever builds FormData from the submit event sees them.
 *
 * Page lifecycle: editors are created on DOMContentLoaded and on the
 * "ppa:load" event, idempotently, and removed on "ppa:unload".
 */
(function (window, document) {
	'use strict';

	if (window.PPASqlEditor) return;

	var HISTORY_MAX = 50;
	var HISTORY_MAX_LENGTH = 100000;
	var STORAGE_PREFIX = 'ppa.sqlhistory:';
	var editors = [];

	/* ---- localStorage, which may be missing or full ---- */

	function storageGet(key) {
		try { return window.localStorage.getItem(key); } catch (e) { return null; }
	}

	function storageSet(key, value) {
		try { window.localStorage.setItem(key, value); return true; } catch (e) { return false; }
	}

	function storageRemove(key) {
		try { window.localStorage.removeItem(key); } catch (e) { /* ignore */ }
	}

	/* ---- helpers ---- */

	function fieldValue(form, name) {
		var el = form.elements[name];
		if (!el) return '';
		if (!el.tagName && el.length) el = el[0];
		return el.value || '';
	}

	function historyKey(form) {
		return STORAGE_PREFIX + fieldValue(form, 'server') + '|' + fieldValue(form, 'database');
	}

	function readHistory(form) {
		var list;
		try { list = JSON.parse(storageGet(historyKey(form)) || '[]'); } catch (e) { list = []; }
		if (!Array.isArray(list)) return [];
		return list.filter(function (q) { return typeof q === 'string'; });
	}

	function pushHistory(form, query) {
		if (!query || !query.trim() || query.length > HISTORY_MAX_LENGTH) return;
		var list = readHistory(form).filter(function (q) { return q !== query; });
		list.unshift(query);
		list = list.slice(0, HISTORY_MAX);
		// When storage is full, keep dropping the oldest entries.
		while (list.length && !storageSet(historyKey(form), JSON.stringify(list)))
			list.pop();
	}

	function findEditor(textarea) {
		for (var i = 0; i < editors.length; i++)
			if (editors[i].textarea === textarea) return editors[i];
		return null;
	}

	function editorsIn(form) {
		return editors.filter(function (ed) { return ed.textarea.form === form; });
	}

	function parseColor(str) {
		var m = /rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s\/]+([\d.]+%?))?/.exec(str || '');
		if (!m) return null;
		var a = m[4] === undefined ? 1 : (m[4].slice(-1) === '%' ? parseFloat(m[4]) / 100 : parseFloat(m[4]));
		return [+m[1], +m[2], +m[3], a];
	}

	/** The first opaque background behind el, as [r, g, b, a]. */
	function backgroundOf(el) {
		for (; el && el.nodeType === 1; el = el.parentNode) {
			var c = parseColor(window.getComputedStyle(el).backgroundColor);
			if (c && c[3] > 0.5) return c;
		}
		return [255, 255, 255, 1];
	}

	function isDark(rgb) {
		return (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2]) / 255 < 0.45;
	}

	/*
	 * Names for autocompletion, from the JSON the page prints:
	 *  tables    for CodeMirror's SQL hint: "schema.table" for every table,
	 *            plus the bare name for tables in the search path
	 *  schemas   schema names, search path first
	 *  elsewhere [schema, table] of tables outside the search path, which
	 *            are offered as "schema.table" when you type their bare name
	 */
	function readHint(textarea) {
		var hint = { tables: Object.create(null), schemas: [], elsewhere: [] };
		var id = textarea.getAttribute('data-sql-hint');
		var el = id && document.getElementById(id);
		if (!el) return hint;
		var json;
		try { json = JSON.parse(el.textContent); } catch (e) { return hint; }
		// Older pages sent only the list of tables.
		var rows = Array.isArray(json) ? json : (json && json.tables);
		if (json && Array.isArray(json.schemas)) hint.schemas = json.schemas.filter(function (n) { return typeof n === 'string'; });
		if (!Array.isArray(rows)) return hint;
		rows.forEach(function (row) {
			if (!Array.isArray(row) || row.length < 2) return;
			var cols = Array.isArray(row[2]) ? row[2] : [];
			hint.tables[row[0] + '.' + row[1]] = cols;
			// Unqualified names only for schemas in the search path. Rows come
			// in search path order, so the first one wins like in PostgreSQL.
			if (row[3]) {
				if (!(row[1] in hint.tables)) hint.tables[row[1]] = cols;
			} else {
				hint.elsewhere.push([row[0], row[1]]);
			}
		});
		return hint;
	}

	// Picking a schema types "schema." and lists its tables right away.
	function pickSchema(cm, data, completion) {
		cm.replaceRange(completion.text + '.', completion.from || data.from, completion.to || data.to, 'complete');
		setTimeout(function () { cm.showHint({ completeSingle: false }); }, 0);
	}

	// A grey note after the name in the list ("schema", "in shop").
	function renderWithNote(el, self, item) {
		el.appendChild(document.createTextNode(item.label));
		var note = document.createElement('span');
		note.className = 'ppa-hint-note';
		note.textContent = item.note;
		el.appendChild(note);
	}

	function startsWith(name, search) {
		return name.toLowerCase().lastIndexOf(search.toLowerCase(), 0) === 0;
	}

	// CodeMirror's SQL completion, plus schema names and the tables of
	// schemas outside the search path. When the list pops up on its own
	// while typing, it stays closed once the word typed so far is itself one
	// of the suggestions, so Enter after a finished word starts a new line
	// instead of swapping it for a longer name. Ctrl+Space shows them all.
	function sqlHint(cm, options) {
		var result = window.CodeMirror.hint.sql(cm, options);
		var hint = cm.ppaHint;
		if (result && hint) {
			var search = cm.getRange(result.from, result.to);
			// Only for a plain word: after "x." CodeMirror's own completion
			// already lists the tables of a schema or the columns of a table.
			if (/^[A-Za-z_][\w$]*$/.test(search) || search === '') {
				var have = Object.create(null);
				result.list.forEach(function (item) {
					// A keyword (PUBLIC) mustn't hide the schema of that name.
					if (/CodeMirror-hint-keyword/.test(item.className || '')) return;
					have[String(typeof item === 'string' ? item : item.text).toLowerCase()] = 1;
				});
				var schemas = [], extra = [];
				hint.schemas.forEach(function (name) {
					if (startsWith(name, search) && !have[name.toLowerCase()]) {
						schemas.push({ text: name, label: name, note: options.schemaNote || 'schema',
							className: 'CodeMirror-hint-schema', hint: pickSchema, render: renderWithNote });
					}
				});
				hint.elsewhere.forEach(function (t) {
					var text = t[0] + '.' + t[1];
					if (search !== '' && startsWith(t[1], search) && !have[text.toLowerCase()]) {
						extra.push({ text: text, label: t[1], note: (options.inSchemaNote || 'in %s').replace('%s', t[0]),
							className: 'CodeMirror-hint-table', render: renderWithNote });
					}
				});
				// Schemas first, other tables before the keywords.
				var at = 0;
				while (at < result.list.length && !/CodeMirror-hint-keyword/.test(result.list[at].className || '')) at++;
				Array.prototype.splice.apply(result.list, [at, 0].concat(extra));
				Array.prototype.splice.apply(result.list, [0, 0].concat(schemas));
			}
		}
		if (!result || !result.list || !result.list.length) return null;
		if (options && options.auto) {
			var typed = cm.getRange(result.from, result.to).toLowerCase();
			for (var i = 0; i < result.list.length; i++) {
				var item = result.list[i];
				if (String(typeof item === 'string' ? item : item.text).toLowerCase() === typed) return null;
			}
		}
		return result;
	}

	function firstLine(query, max) {
		var s = query.replace(/\s+/g, ' ').trim();
		return s.length > max ? s.slice(0, max - 1) + '…' : s;
	}

	/* ---- the editor ---- */

	function runForm(cm) {
		var form = cm.getTextArea().form;
		if (!form) return;
		var button = form.querySelector('.sql-editor-execute');
		if (typeof form.requestSubmit === 'function') {
			if (button) form.requestSubmit(button);
			else form.requestSubmit();
		}
		else if (button) button.click();
		else form.submit();
	}

	function insertSpaces(cm) {
		if (cm.somethingSelected()) {
			cm.indentSelection('add');
			return;
		}
		cm.replaceSelection(new Array(cm.getOption('indentUnit') + 1).join(' '), 'end', '+input');
	}

	function enhance(textarea) {
		// A copy of the page (e.g. from a history cache) may still carry a
		// dead editor next to the textarea.
		var next = textarea.nextElementSibling;
		if (next && /\bCodeMirror\b/.test(next.className)) next.parentNode.removeChild(next);

		var taStyle = window.getComputedStyle(textarea);
		var bg = parseColor(taStyle.backgroundColor);
		if (!bg || bg[3] <= 0.5) bg = backgroundOf(textarea.parentNode);
		var runnable = textarea.hasAttribute('data-sql-run');
		var rows = parseInt(textarea.getAttribute('rows'), 10) || 10;

		var hint = readHint(textarea);
		var keys = {
			'Ctrl-Space': 'autocomplete',
			'Tab': insertSpaces,
			'Shift-Tab': 'indentLess'
		};
		if (runnable) {
			keys['Ctrl-Enter'] = runForm;
			keys['Cmd-Enter'] = runForm;
		}

		var cm = window.CodeMirror.fromTextArea(textarea, {
			mode: 'text/x-pgsql',
			theme: isDark(bg) ? 'ppa-dark' : 'ppa',
			lineNumbers: true,
			matchBrackets: true,
			indentUnit: 4,
			tabSize: 4,
			indentWithTabs: false,
			extraKeys: keys,
			hintOptions: {
				tables: hint.tables, completeSingle: false, hint: sqlHint,
				schemaNote: textarea.getAttribute('data-hint-schema') || 'schema',
				inSchemaNote: textarea.getAttribute('data-hint-in-schema') || 'in %s'
			}
		});
		cm.ppaHint = hint;
		var wrapper = cm.getWrapperElement();
		wrapper.className += ' sql-editor-cm';
		// Use the theme's field colours, whatever the theme.
		wrapper.style.backgroundColor = 'rgb(' + bg[0] + ',' + bg[1] + ',' + bg[2] + ')';
		if (taStyle.color) wrapper.style.color = taStyle.color;

		var ed = { cm: cm, textarea: textarea, height: 0, userSized: false, observer: null };
		editors.push(ed);
		textarea.setAttribute('data-sql-editor', 'on');

		// Grow with the content between the textarea's height and 60% of the
		// window, until the user resizes it by hand.
		var lineHeight = cm.defaultTextHeight();
		var minHeight = Math.round(Math.max(5, Math.min(rows, 20)) * lineHeight + 10);
		function autoSize() {
			if (ed.userSized) return;
			var maxHeight = Math.max(minHeight, Math.round(window.innerHeight * 0.6));
			var height = Math.min(maxHeight, Math.max(minHeight, Math.round(cm.lineCount() * lineHeight + 30)));
			if (height !== ed.height) {
				ed.height = height;
				cm.setSize(null, height);
			}
		}
		cm.on('change', autoSize);
		autoSize();
		if (window.ResizeObserver) {
			ed.observer = new window.ResizeObserver(function () {
				if (ed.height && wrapper.style.height && wrapper.style.height !== ed.height + 'px')
					ed.userSized = true;
				cm.refresh();
			});
			ed.observer.observe(wrapper);
		}

		// Suggestions pop up while typing a name (from its second character)
		// and after "schema." or "table.". Ctrl+Space opens them any time.
		cm.on('inputRead', function (cm, change) {
			if (cm.state.completionActive || change.origin !== '+input') return;
			if (change.text.length !== 1) return;
			var typed = change.text[0];
			var cur = cm.getCursor();
			var token = cm.getTokenAt(cur);
			if (/\b(string|comment)\b/.test(token.type || '')) return;
			if (typed === '.') {
				cm.showHint({ completeSingle: false });
				return;
			}
			if (!/^[A-Za-z0-9_]$/.test(typed)) return;
			var before = cm.getLine(cur.line).slice(0, cur.ch);
			var word = /[A-Za-z_][A-Za-z0-9_]*$/.exec(before);
			if (word && word[0].length >= 2) cm.showHint({ completeSingle: false, auto: true });
		});

		if (runnable) setupRunnable(ed);
		return ed;
	}

	function setupRunnable(ed) {
		var cm = ed.cm, textarea = ed.textarea, form = textarea.form;

		// "Execute" becomes "Execute selection" while text is selected.
		var execute = form && form.querySelector('.sql-editor-execute');
		if (execute) {
			cm.on('cursorActivity', function () {
				var label = execute.getAttribute(cm.somethingSelected() ? 'data-label-selection' : 'data-label');
				if (label && execute.value !== label) execute.value = label;
			});
		}

		var doc = cm.getDoc();
		var selection = /^(\d+):(\d+):(\d+):(\d+)$/.exec(textarea.getAttribute('data-selection') || '');
		var from = null;
		if (selection) {
			from = { line: +selection[1], ch: +selection[2] };
			doc.setSelection(from, { line: +selection[3], ch: +selection[4] });
		}

		// Point at the error. Its position is relative to what was run, which
		// was the selection if there was one.
		var errLine = parseInt(textarea.getAttribute('data-error-line'), 10);
		if (errLine > 0) {
			var line = errLine - 1 + (from ? from.line : 0);
			var col = parseInt(textarea.getAttribute('data-error-col'), 10);
			if (line < doc.lineCount()) {
				var handle = cm.addLineClass(line, 'background', 'sql-editor-error-line');
				var mark = null;
				if (col > 0) {
					var pos = { line: line, ch: col - 1 + (from && errLine === 1 ? from.ch : 0) };
					var word = cm.findWordAt(pos);
					if (word.head.ch > word.anchor.ch)
						mark = cm.markText(word.anchor, word.head, { className: 'sql-editor-error-mark' });
					if (!selection) doc.setCursor(pos);
				}
				else if (!selection) doc.setCursor({ line: line, ch: 0 });
				cm.scrollIntoView({ line: line, ch: 0 }, 40);
				cm.on('change', function clear() {
					cm.removeLineClass(handle, 'background', 'sql-editor-error-line');
					if (mark) mark.clear();
					cm.off('change', clear);
				});
			}
		}

		if (form) setupToolbar(form, ed);
	}

	function setupToolbar(form, ed) {
		var toolbar = form.querySelector('.sql-editor-toolbar');
		if (!toolbar || toolbar.querySelector('.sql-editor-history')) return;

		var menu = document.createElement('details');
		menu.className = 'sql-editor-history';
		var summary = document.createElement('summary');
		summary.textContent = toolbar.getAttribute('data-label-history') || 'History';
		menu.appendChild(summary);
		var panel = document.createElement('div');
		panel.className = 'sql-editor-history-menu';
		// Same colours as the editor, which took them from the theme.
		var wrapper = ed.cm.getWrapperElement();
		panel.style.backgroundColor = wrapper.style.backgroundColor;
		panel.style.color = wrapper.style.color;
		menu.appendChild(panel);
		toolbar.appendChild(menu);

		function render() {
			panel.textContent = '';
			var list = readHistory(form);
			if (!list.length) {
				var empty = document.createElement('p');
				empty.textContent = toolbar.getAttribute('data-label-history-empty') || '';
				panel.appendChild(empty);
				return;
			}
			var ul = document.createElement('ul');
			list.forEach(function (query) {
				var li = document.createElement('li');
				var item = document.createElement('button');
				item.type = 'button';
				item.className = 'sql-editor-history-item';
				item.textContent = firstLine(query, 120);
				item.title = query.length > 1000 ? query.slice(0, 1000) + '…' : query;
				item.addEventListener('click', function () {
					var target = editorsIn(form)[0] || ed;
					target.cm.setValue(query);
					target.cm.setCursor(target.cm.lineCount(), 0);
					menu.open = false;
					target.cm.focus();
				});
				li.appendChild(item);
				ul.appendChild(li);
			});
			panel.appendChild(ul);
			var clear = document.createElement('button');
			clear.type = 'button';
			clear.className = 'sql-editor-history-clear';
			clear.textContent = toolbar.getAttribute('data-label-history-clear') || 'Clear';
			clear.addEventListener('click', function () {
				storageRemove(historyKey(form));
				render();
			});
			panel.appendChild(clear);
		}

		menu.addEventListener('toggle', function () {
			if (menu.open) render();
		});
	}

	/* ---- form submission ---- */

	function hasScriptFile(form) {
		var file = form.elements.script;
		return !!(file && file.files && file.files.length);
	}

	function addHidden(after, name, value) {
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = name;
		input.value = value;
		input.className = 'sql-editor-temp';
		after.parentNode.insertBefore(input, after.nextSibling);
		return input;
	}

	function restore(form) {
		var temp = form.querySelectorAll('.sql-editor-temp');
		for (var i = 0; i < temp.length; i++) temp[i].parentNode.removeChild(temp[i]);
		editorsIn(form).forEach(function (ed) { ed.textarea.disabled = false; });
	}

	function prepareSubmit(form, ed) {
		var cm = ed.cm, textarea = ed.textarea;
		cm.save();
		if (!textarea.hasAttribute('data-sql-run')) return;

		var full = cm.getValue();
		var selected = cm.somethingSelected() ? cm.getSelections().join('\n') : '';
		var run = full;
		if (selected.trim() !== '') {
			run = selected;
			var range = cm.listSelections()[0];
			var a = range.anchor, b = range.head;
			var start = window.CodeMirror.cmpPos(a, b) <= 0 ? a : b;
			var end = start === a ? b : a;
			// A disabled field isn't submitted, the hidden one takes its place.
			textarea.disabled = true;
			addHidden(textarea, textarea.name, selected);
			addHidden(textarea, 'sqleditor_text', full);
			addHidden(textarea, 'sqleditor_selection', [start.line, start.ch, end.line, end.ch].join(':'));
		}
		if (!hasScriptFile(form)) pushHistory(form, run);
	}

	window.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || form.tagName !== 'FORM') return;
		var list = editorsIn(form);
		if (!list.length) return;
		restore(form);
		list.forEach(function (ed) { prepareSubmit(form, ed); });
		window.setTimeout(function () { restore(form); }, 0);
	}, true);

	// <input type="reset"> resets the textarea, the editor has to follow.
	document.addEventListener('reset', function (e) {
		var form = e.target;
		window.setTimeout(function () {
			editorsIn(form).forEach(function (ed) { ed.cm.setValue(ed.textarea.value); });
		}, 0);
	}, true);

	// Close an open recent-queries menu on a click elsewhere or on Escape.
	document.addEventListener('click', function (e) {
		var open = document.querySelectorAll('details.sql-editor-history[open]');
		for (var i = 0; i < open.length; i++)
			if (!open[i].contains(e.target)) open[i].open = false;
	});
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') return;
		var open = document.querySelectorAll('details.sql-editor-history[open]');
		for (var i = 0; i < open.length; i++) {
			open[i].open = false;
			open[i].querySelector('summary').focus();
		}
	});

	/* ---- lifecycle ---- */

	function destroy(ed) {
		if (ed.observer) ed.observer.disconnect();
		if (ed.cm.state.completionActive) ed.cm.state.completionActive.close();
		try { ed.cm.toTextArea(); } catch (e) { /* already gone */ }
		ed.textarea.removeAttribute('data-sql-editor');
	}

	function init() {
		if (typeof window.CodeMirror === 'undefined') return;
		// Forget editors whose page content was replaced.
		editors = editors.filter(function (ed) {
			if (document.documentElement.contains(ed.textarea)) return true;
			destroy(ed);
			return false;
		});
		var list = document.querySelectorAll('textarea.sql-editor');
		for (var i = 0; i < list.length; i++) {
			if (findEditor(list[i])) continue;
			try {
				enhance(list[i]);
			} catch (e) {
				// Leave the plain textarea working.
				if (window.console) window.console.error(e);
			}
		}
	}

	function destroyAll() {
		editors.forEach(destroy);
		editors = [];
	}

	document.addEventListener('ppa:load', init);
	document.addEventListener('ppa:unload', destroyAll);
	if (document.readyState === 'loading')
		document.addEventListener('DOMContentLoaded', init);
	else
		init();

	window.PPASqlEditor = { init: init, destroy: destroyAll };
})(window, document);
