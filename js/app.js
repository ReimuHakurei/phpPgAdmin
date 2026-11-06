/*
 * phpPgAdmin app shell.
 *
 * Every page is rendered by the server inside <main id="ppa-main">, next to
 * the sidebar with the object tree (see Misc::printBody). After the first
 * page load this script takes over links and forms: it fetches the next page
 * with an "X-PPA-Partial: 1" header (the server then leaves out the sidebar),
 * swaps the new content into <main>, runs its scripts and updates the
 * address bar. The sidebar and its tree stay as they are.
 *
 * Contract for page scripts:
 *  - <script src> files run once per full page load (de-duplicated by URL).
 *    Do per-page set-up in a listener for the "ppa:load" event on document,
 *    which fires after the first load and after every swap. Bind delegated
 *    handlers once, at the top level of the file.
 *  - Inline <script> blocks run every time their page is shown.
 *  - "ppa:unload" fires on document right before the content is replaced.
 *  - Links and forms with a target other than _self/_top (or "detail", the
 *    old frame name) are left to the browser, as are links with a download
 *    attribute or data-ppa-native, and forms posting to the export pages.
 *
 * Dialogs: links with data-ppa-dialog="modal" or "panel", and links to the
 * drop/delete/empty confirmation pages, open that page in a dialog instead
 * (a centred modal, or a panel sliding in from the side) over the current
 * page. Its form is submitted from the dialog; if the answer is the same
 * form again (e.g. an error while saving) it stays in the dialog, otherwise
 * the dialog closes and the answer replaces the page underneath. The pages
 * themselves are unchanged and still work on their own.
 */
(function () {
	'use strict';

	var ppa = window.ppa = window.ppa || {};

	var loadedScripts = {};
	var postPages = {};       // history id -> html of pages that came from a POST
	var postOrder = [];
	var scrolls = {};         // history id -> scroll position when leaving it
	var currentId = 0;
	var nextId = 1;
	var request = null;       // AbortController of the navigation in flight
	var baseUrl = null;

	// Downloads must stay native: the browser saves them without leaving the page.
	var NATIVE_FORMS = /\/(dataexport|dbexport)\.php$/;
	var SAME_WINDOW_TARGETS = { '': 1, '_self': 1, '_top': 1, '_parent': 1, 'detail': 1, 'browser': 1 };

	function main() { return document.getElementById('ppa-main'); }
	function shellActive() { return !!(main() && document.getElementById('ppa-sidebar')); }

	function fire(name, detail) {
		var ev;
		try {
			ev = new CustomEvent(name, { detail: detail || {} });
		} catch (e) {
			ev = document.createEvent('CustomEvent');
			ev.initCustomEvent(name, false, false, detail || {});
		}
		document.dispatchEvent(ev);
	}

	function storageGet(key) {
		try { return window.localStorage.getItem(key); } catch (e) { return null; }
	}
	function storageSet(key, value) {
		try { window.localStorage.setItem(key, value); } catch (e) { /* private mode etc. */ }
	}
	ppa.storageGet = storageGet;
	ppa.storageSet = storageSet;

	/* ---------------------------------------------------------------- */
	/* Which links and forms we handle                                  */
	/* ---------------------------------------------------------------- */

	function appBase() {
		if (!baseUrl) {
			var s = document.querySelector('script[src*="js/app.js"]');
			baseUrl = s ? new URL('../', s.src).href : new URL('./', location.href).href;
		}
		return baseUrl;
	}

	function handles(url, target) {
		if (!shellActive()) return false;
		if (!SAME_WINDOW_TARGETS.hasOwnProperty((target || '').toLowerCase())) return false;
		var u;
		try { u = new URL(url, location.href); } catch (e) { return false; }
		if (u.protocol !== 'http:' && u.protocol !== 'https:') return false;
		if (u.origin !== location.origin) return false;
		if (u.href.indexOf(appBase()) !== 0) return false;
		// Only our own pages: "x.php" or the start page.
		var file = u.pathname.substring(u.pathname.lastIndexOf('/') + 1);
		if (file !== '' && !/\.php$/.test(file)) return false;
		// Downloads (history.php?action=download and the like).
		if (u.searchParams.get('action') === 'download') return false;
		// A jump to an anchor on the current page.
		if (u.hash && u.pathname === location.pathname && u.search === location.search) return false;
		return true;
	}

	// Listen on window, not document: handlers that page scripts delegate to
	// document (jQuery's .on) run first and can cancel the default action.
	window.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
		if (!a || a.hasAttribute('download') || a.hasAttribute('data-ppa-native')) return;
		var href = a.getAttribute('href');
		if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) return;
		if (!handles(a.href, a.getAttribute('target'))) return;
		e.preventDefault();
		var kind = dialogKind(a);
		if (kind) openDialog(a.href, kind);
		else visit(a.href);
	});

	window.addEventListener('submit', function (e) {
		if (e.defaultPrevented) return;
		var form = e.target;
		if (!form || form.nodeName !== 'FORM' || form.hasAttribute('data-ppa-native')) return;
		var submitter = e.submitter || null;
		// Not form.action: nearly every form here has an <input name="action">,
		// which the browser returns for form.action instead of the URL.
		var action = (submitter && submitter.hasAttribute('formaction'))
			? submitter.formAction
			: new URL(form.getAttribute('action') || location.href, location.href).href;
		var method = ((submitter && submitter.getAttribute('formmethod')) || form.getAttribute('method') || 'get').toLowerCase();
		var target = (submitter && submitter.getAttribute('formtarget')) || form.getAttribute('target');
		if (!handles(action, target)) return;
		if (NATIVE_FORMS.test(new URL(action, location.href).pathname)) return;

		var data;
		try {
			data = new FormData(form, submitter);
		} catch (err) {
			data = new FormData(form);
			if (submitter && submitter.name) data.append(submitter.name, submitter.value);
		}
		e.preventDefault();

		if (form.closest('.ppa-dialog')) {
			submitDialog(form, submitter, action, method, data);
			return;
		}

		if (method === 'get') {
			var u = new URL(action, location.href);
			var params = new URLSearchParams();
			data.forEach(function (v, k) { if (typeof v === 'string') params.append(k, v); });
			u.search = params.toString();
			visit(u.href);
		} else {
			visit(action, { method: 'POST', body: data });
		}
	});

	/* ---------------------------------------------------------------- */
	/* Loading pages                                                    */
	/* ---------------------------------------------------------------- */

	var progressTimer = null;
	function progress(on) {
		var bar = document.getElementById('ppa-progress');
		if (!bar) {
			bar = document.createElement('div');
			bar.id = 'ppa-progress';
			bar.setAttribute('aria-hidden', 'true');
			document.body.appendChild(bar);
		}
		clearTimeout(progressTimer);
		if (on) {
			// Fast pages shouldn't flash a bar.
			progressTimer = setTimeout(function () {
				bar.className = 'active';
				document.documentElement.setAttribute('aria-busy', 'true');
			}, 120);
		} else {
			bar.className = '';
			document.documentElement.removeAttribute('aria-busy');
		}
	}

	function visit(url, opts) {
		opts = opts || {};
		closeDialog(true);
		var method = opts.method || 'GET';
		if (request) request.abort();
		var ctrl = request = (window.AbortController ? new AbortController() : null);
		progress(true);

		var finish = function () {
			if (request === ctrl) { request = null; progress(false); }
		};

		return fetch(url, {
			method: method,
			body: opts.body,
			credentials: 'same-origin',
			headers: { 'X-PPA-Partial': '1' },
			signal: ctrl ? ctrl.signal : undefined
		}).then(function (res) {
			var type = res.headers.get('Content-Type') || '';
			var disposition = res.headers.get('Content-Disposition') || '';
			if (!/text\/html/i.test(type) || /attachment/i.test(disposition)) {
				finish();
				if (method === 'GET') {
					location.href = url;
				} else {
					return res.text().then(function (text) {
						var pre = document.createElement('pre');
						pre.textContent = text;
						show('<pre></pre>', res.url || url, { method: method, push: opts.history !== false, node: pre });
					});
				}
				return;
			}
			return res.text().then(function (html) {
				if (ctrl && ctrl.signal.aborted) return;
				finish();
				show(html, res.url || url, { method: method, push: opts.history !== false, scroll: opts.scroll });
			});
		}).catch(function (err) {
			finish();
			if (err && err.name === 'AbortError') return;
			if (method === 'GET') {
				// Let the browser show what went wrong.
				location.href = url;
			} else {
				var app = document.getElementById('ppa-app');
				var p = document.createElement('p');
				p.className = 'message error';
				p.setAttribute('role', 'alert');
				p.textContent = (app && app.getAttribute('data-load-error')) || 'The page could not be loaded.';
				main().insertBefore(p, main().firstChild);
				window.scrollTo(0, 0);
			}
		});
	}
	ppa.visit = visit;

	function rememberPost(id, html) {
		postPages[id] = html;
		postOrder.push(id);
		while (postOrder.length > 10) delete postPages[postOrder.shift()];
	}

	function show(html, url, opts) {
		var doc = new DOMParser().parseFromString(html, 'text/html');
		var content = doc.getElementById('ppa-main') || doc.body;
		if (opts.node) {
			content = doc.createElement('div');
			content.appendChild(doc.importNode(opts.node, true));
		}

		// Leaving the current page.
		scrolls[currentId] = window.scrollY;
		fire('ppa:unload', { url: location.href });

		if (opts.push) {
			var id = nextId++;
			if (opts.method !== 'GET') rememberPost(id, html);
			history.pushState({ ppa: 1, id: id }, '', url);
			currentId = id;
		}

		if (doc.title) document.title = doc.title;
		var keep = document.body.classList.contains('ppa-shell');
		document.body.className = doc.body ? doc.body.className : '';
		if (keep) document.body.classList.add('ppa-shell');
		syncHead(doc);

		var m = main();
		var hadFocus = m.contains(document.activeElement);
		while (m.firstChild) m.removeChild(m.firstChild);
		var nodes = Array.prototype.slice.call(content.childNodes);
		for (var i = 0; i < nodes.length; i++) m.appendChild(document.importNode(nodes[i], true));

		if (typeof opts.scroll === 'number') window.scrollTo(0, opts.scroll);
		else window.scrollTo(0, 0);
		if (hadFocus) m.focus({ preventScroll: true });
		document.body.classList.remove('ppa-sidebar-open');

		runScripts(headScripts(doc).concat(Array.prototype.slice.call(m.querySelectorAll('script')))).then(function () {
			fire('ppa:load', { url: url });
		});
	}

	// Stylesheets and <style> blocks a page asks for in its <head>.
	function syncHead(doc) {
		var theme = document.getElementById('csstheme');
		var newTheme = doc.getElementById('csstheme');
		if (theme && newTheme && theme.getAttribute('href') !== newTheme.getAttribute('href')) {
			var themeChanged = theme.getAttribute('data-theme') !== newTheme.getAttribute('data-theme');
			theme.setAttribute('href', newTheme.getAttribute('href'));
			theme.setAttribute('data-theme', newTheme.getAttribute('data-theme'));
			// Tree icons come from the theme as well.
			if (themeChanged && ppa.tree) ppa.tree.reload();
		}

		var have = {};
		document.querySelectorAll('link[rel="stylesheet"]').forEach(function (l) { have[l.href] = 1; });
		doc.querySelectorAll('head link[rel="stylesheet"]').forEach(function (l) {
			if (l.id === 'csstheme') return;
			var href = new URL(l.getAttribute('href'), location.href).href;
			if (have[href]) return;
			var n = document.createElement('link');
			n.rel = 'stylesheet';
			n.href = href;
			document.head.appendChild(n);
		});

		document.querySelectorAll('style[data-ppa-page]').forEach(function (s) { s.parentNode.removeChild(s); });
		doc.querySelectorAll('head style').forEach(function (s) {
			var n = document.createElement('style');
			n.setAttribute('data-ppa-page', '');
			n.textContent = s.textContent;
			document.head.appendChild(n);
		});
	}

	function headScripts(doc) {
		return Array.prototype.slice.call(doc.querySelectorAll('head script')).filter(function (s) {
			var t = (s.getAttribute('type') || '').toLowerCase();
			return t === '' || t === 'text/javascript' || t === 'module';
		});
	}

	// Run scripts one after another, like the parser would. Scripts that
	// are part of <main> are replaced in place; head scripts are added to
	// <head>. Files already loaded on this page are not run again.
	function runScripts(list) {
		var chain = Promise.resolve();
		list.forEach(function (old) {
			chain = chain.then(function () { return runScript(old); });
		});
		return chain;
	}

	function runScript(old) {
		var type = (old.getAttribute('type') || '').toLowerCase();
		if (type !== '' && type !== 'text/javascript' && type !== 'module' && type !== 'application/javascript') return null;
		var inPage = old.ownerDocument === document;
		var src = old.getAttribute('src');

		if (src) {
			var abs = new URL(src, location.href).href;
			if (loadedScripts[abs]) {
				if (inPage) old.parentNode.removeChild(old);
				return null;
			}
			loadedScripts[abs] = 1;
		}

		var s = document.createElement('script');
		for (var i = 0; i < old.attributes.length; i++) {
			s.setAttribute(old.attributes[i].name, old.attributes[i].value);
		}
		if (!src) s.textContent = old.textContent;

		var done = src ? new Promise(function (resolve) {
			s.onload = s.onerror = function () { resolve(); };
		}) : null;
		if (src) s.async = false;

		if (inPage) old.parentNode.replaceChild(s, old);
		else document.head.appendChild(s);
		return done;
	}

	window.addEventListener('popstate', function (e) {
		var st = e.state;
		if (!st || !st.ppa) return;
		if (st.id === currentId) return;       // only the #hash changed
		scrolls[currentId] = window.scrollY;
		currentId = st.id;
		var scroll = scrolls[st.id];
		if (postPages[st.id]) {
			show(postPages[st.id], location.href, { method: 'POST', push: false, scroll: scroll });
		} else {
			visit(location.href, { history: false, scroll: scroll });
		}
	});

	/* ---------------------------------------------------------------- */
	/* Dialogs                                                          */
	/* ---------------------------------------------------------------- */

	// Confirmation pages that open as a modal without being marked.
	var DIALOG_ACTIONS = /^(confirm_drop|confirm_drop_[a-z]+|confirm_empty|confdelrow|confdelhistory|confclearhistory|confdelautovac)$/;
	// Buttons that only go back: in a dialog they just close it.
	var CLOSE_BUTTONS = /^(cancel|no)$/;

	var dialog = null;

	function dialogKind(a) {
		var kind = a.getAttribute('data-ppa-dialog');
		if (kind === 'modal' || kind === 'panel') return kind;
		if (kind !== null) return null;   // data-ppa-dialog="" opts out
		var action;
		try { action = new URL(a.href).searchParams.get('action'); } catch (e) { return null; }
		return action && DIALOG_ACTIONS.test(action) ? 'modal' : null;
	}

	function shellText(name, fallback) {
		var app = document.getElementById('ppa-app');
		return (app && app.getAttribute('data-' + name)) || fallback;
	}

	// The page's content without what only makes sense on a full page.
	function dialogContent(doc) {
		var content = doc.getElementById('ppa-main') || doc.body;
		content.querySelectorAll('.topbar, .trail, nav.tabs, .bottom_link, .alert-banner').forEach(function (n) {
			n.parentNode.removeChild(n);
		});
		return content;
	}

	function openDialog(url, kind) {
		closeDialog(true);
		progress(true);
		return fetch(url, { credentials: 'same-origin', headers: { 'X-PPA-Partial': '1' } })
			.then(function (res) {
				if (!/text\/html/i.test(res.headers.get('Content-Type') || '')) throw new Error('not a page');
				return res.text().then(function (html) { return { html: html, url: res.url || url }; });
			})
			.then(function (r) {
				progress(false);
				var doc = new DOMParser().parseFromString(r.html, 'text/html');
				syncHead(doc);

				var d = document.createElement('dialog');
				d.className = 'ppa-dialog ppa-dialog-' + kind;
				d.innerHTML = '<div class="ppa-dialog-head"><button type="button" class="ppa-dialog-close"></button></div>' +
					'<div class="ppa-dialog-body"></div>';
				var close = d.querySelector('.ppa-dialog-close');
				close.setAttribute('aria-label', shellText('close', 'Close'));
				close.title = shellText('close', 'Close');
				close.textContent = '×';
				document.body.appendChild(d);
				dialog = { el: d, kind: kind, url: r.url, dirty: false, scroll: window.scrollY };

				d.addEventListener('cancel', function (e) {   // Esc
					e.preventDefault();
					closeDialog(false);
				});
				close.addEventListener('click', function () { closeDialog(false); });
				// Clicking the dimmed backdrop of a modal closes it too.
				d.addEventListener('mousedown', function (e) {
					if (e.target === d && kind === 'modal') closeDialog(false);
				});
				d.addEventListener('input', function () { if (dialog) dialog.dirty = true; });
				d.addEventListener('change', function () { if (dialog) dialog.dirty = true; });

				fillDialog(doc);
				d.showModal();
				focusDialog();
			})
			.catch(function () {
				progress(false);
				visit(url);   // show it as a page instead
			});
	}

	function fillDialog(doc) {
		var d = dialog.el;
		var body = d.querySelector('.ppa-dialog-body');
		var content = dialogContent(doc);
		while (body.firstChild) body.removeChild(body.firstChild);
		var nodes = Array.prototype.slice.call(content.childNodes);
		for (var i = 0; i < nodes.length; i++) body.appendChild(document.importNode(nodes[i], true));

		// The page title becomes the dialog's title.
		var h = body.querySelector('h2');
		var head = d.querySelector('.ppa-dialog-head');
		var old = head.querySelector('h2');
		if (old) head.removeChild(old);
		if (h) {
			h.id = 'ppa-dialog-title';
			head.insertBefore(h, head.firstChild);
			d.setAttribute('aria-labelledby', 'ppa-dialog-title');
		}
		body.scrollTop = 0;
		return runScripts(headScripts(doc).concat(Array.prototype.slice.call(body.querySelectorAll('script')))).then(function () {
			fire('ppa:load', { url: dialog ? dialog.url : '', dialog: true });
		});
	}

	// Only once the dialog is open: nothing in a closed dialog takes focus,
	// and showModal() would otherwise focus the first button (Yes/Drop).
	function focusDialog() {
		if (!dialog) return;
		var body = dialog.el.querySelector('.ppa-dialog-body');
		// Confirmations focus Cancel (markDangerButtons); forms their first field.
		markDangerButtons(body);
		if (body.contains(document.activeElement)) return;
		var field = body.querySelector('input:not([type=hidden]):not([type=submit]):not([type=checkbox]), textarea, select');
		(field || body.querySelector('input[type=submit], button') || dialog.el).focus({ preventScroll: true });
	}

	function closeDialog(force) {
		if (!dialog) return true;
		if (!force && dialog.dirty && !window.confirm(shellText('discard', 'Discard your changes?'))) return false;
		var d = dialog.el;
		dialog = null;
		if (d.open) d.close();
		if (d.parentNode) d.parentNode.removeChild(d);
		return true;
	}
	ppa.closeDialog = closeDialog;

	function hiddenAction(form) {
		var f = form.querySelector('input[type=hidden][name=action]');
		return f ? f.value : '';
	}

	function submitDialog(form, submitter, action, method, data) {
		if (submitter && CLOSE_BUTTONS.test(submitter.name || '')) {
			closeDialog(true);
			return;
		}
		var sent = hiddenAction(form);
		var path = new URL(action, location.href).pathname;
		var url = action, body = data;
		if (method === 'get') {
			var u = new URL(action, location.href);
			var params = new URLSearchParams();
			data.forEach(function (v, k) { if (typeof v === 'string') params.append(k, v); });
			u.search = params.toString();
			url = u.href;
			body = undefined;
		}
		var buttons = form.querySelectorAll('input[type=submit], button[type=submit]');
		buttons.forEach(function (b) { b.disabled = true; });
		progress(true);

		fetch(url, { method: method === 'get' ? 'GET' : 'POST', body: body, credentials: 'same-origin', headers: { 'X-PPA-Partial': '1' } })
			.then(function (res) { return res.text().then(function (html) { return { html: html, url: res.url || url }; }); })
			.then(function (r) {
				progress(false);
				if (!dialog) return;
				var doc = new DOMParser().parseFromString(r.html, 'text/html');
				var main = doc.getElementById('ppa-main') || doc.body;
				// The same form came back (an error while saving): stay here.
				var again = sent && Array.prototype.some.call(main.querySelectorAll('form'), function (f) {
					return hiddenAction(f) === sent && new URL(f.getAttribute('action') || r.url, r.url).pathname === path;
				});
				if (again) {
					fillDialog(doc).then(focusDialog);
					return;
				}
				// Done: the answer (usually the list with a message) replaces
				// the page underneath, which keeps its address and scroll.
				var scroll = dialog.scroll;
				closeDialog(true);
				show(r.html, location.href, { method: 'GET', push: false, scroll: scroll });
			})
			.catch(function () {
				progress(false);
				buttons.forEach(function (b) { b.disabled = false; });
				var p = document.createElement('p');
				p.className = 'message error';
				p.setAttribute('role', 'alert');
				p.textContent = shellText('load-error', 'The page could not be loaded.');
				if (dialog) dialog.el.querySelector('.ppa-dialog-body').insertBefore(p, dialog.el.querySelector('.ppa-dialog-body').firstChild);
			});
	}

	/* ---------------------------------------------------------------- */
	/* Sidebar: tree refresh, mobile toggle, resizing, Ctrl+K           */
	/* ---------------------------------------------------------------- */

	ppa.reloadTree = function () {
		if (ppa.tree) ppa.tree.reload();
	};

	function setSidebarOpen(open) {
		document.body.classList.toggle('ppa-sidebar-open', open);
		var t = document.getElementById('ppa-sidebar-toggle');
		if (t) t.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	document.addEventListener('click', function (e) {
		var toggle = e.target.closest && e.target.closest('#ppa-sidebar-toggle');
		if (toggle) {
			setSidebarOpen(!document.body.classList.contains('ppa-sidebar-open'));
			return;
		}
		// Tapping the page closes the sidebar on small screens.
		if (document.body.classList.contains('ppa-sidebar-open') && !e.target.closest('#ppa-sidebar')) {
			setSidebarOpen(false);
		}
	});

	document.addEventListener('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && (e.key === 'k' || e.key === 'K')) {
			var f = document.getElementById('ppa-tree-filter');
			if (!f) return;
			e.preventDefault();
			setSidebarOpen(true);
			f.focus();
			f.select();
		} else if (e.key === 'Escape' && document.body.classList.contains('ppa-sidebar-open')) {
			setSidebarOpen(false);
		}
	});

	function initResize() {
		var sidebar = document.getElementById('ppa-sidebar');
		var handle = document.getElementById('ppa-sidebar-resize');
		var app = document.getElementById('ppa-app');
		if (!sidebar || !handle || !app) return;
		var configured = app.style.getPropertyValue('--ppa-sidebar-width');

		var saved = parseInt(storageGet('ppa.sidebarWidth'), 10);
		if (saved >= 140 && saved <= 800) app.style.setProperty('--ppa-sidebar-width', saved + 'px');

		var rtl = getComputedStyle(document.documentElement).direction === 'rtl';
		handle.addEventListener('pointerdown', function (e) {
			e.preventDefault();
			handle.setPointerCapture(e.pointerId);
			var startX = e.clientX;
			var startW = sidebar.getBoundingClientRect().width;
			document.body.classList.add('ppa-resizing');
			function move(ev) {
				var dx = rtl ? startX - ev.clientX : ev.clientX - startX;
				var w = Math.max(140, Math.min(800, Math.round(startW + dx)));
				app.style.setProperty('--ppa-sidebar-width', w + 'px');
			}
			function up() {
				handle.removeEventListener('pointermove', move);
				handle.removeEventListener('pointerup', up);
				handle.removeEventListener('pointercancel', up);
				document.body.classList.remove('ppa-resizing');
				storageSet('ppa.sidebarWidth', String(Math.round(sidebar.getBoundingClientRect().width)));
			}
			handle.addEventListener('pointermove', move);
			handle.addEventListener('pointerup', up);
			handle.addEventListener('pointercancel', up);
		});
		// Double click goes back to the configured width.
		handle.addEventListener('dblclick', function () {
			app.style.setProperty('--ppa-sidebar-width', configured);
			try { window.localStorage.removeItem('ppa.sidebarWidth'); } catch (e) { /* ignore */ }
		});
	}

	/* ---------------------------------------------------------------- */
	/* Small page enhancements, run on every page                       */
	/* ---------------------------------------------------------------- */

	// Confirmation pages: make the destructive button look the part and
	// put the keyboard focus on Cancel, so Enter doesn't drop anything.
	var DANGER = /^(drop|empty|truncate|delete|yes|dropcolumn|droptable|purge|kill|cancel_backend|terminate)$/i;
	function markDangerButtons(root) {
		var forms = root.querySelectorAll('form');
		for (var i = 0; i < forms.length; i++) {
			var cancel = forms[i].querySelector('input[type="submit"][name="cancel"], button[name="cancel"], input[type="submit"][name="no"]');
			if (!cancel) continue;
			var buttons = forms[i].querySelectorAll('input[type="submit"], button[type="submit"]');
			var marked = false;
			for (var j = 0; j < buttons.length; j++) {
				if (buttons[j] !== cancel && DANGER.test(buttons[j].name || '')) {
					buttons[j].classList.add('danger');
					marked = true;
				}
			}
			if (marked && !root.querySelector('[autofocus]')) cancel.focus({ preventScroll: true });
		}
	}

	document.addEventListener('ppa:load', function (e) {
		if (e.detail && e.detail.dialog) return;   // the dialog does its own
		var m = main();
		if (m) markDangerButtons(m);
	});

	/* ---------------------------------------------------------------- */
	/* Start                                                            */
	/* ---------------------------------------------------------------- */

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('script[src]').forEach(function (s) { loadedScripts[s.src] = 1; });
		if (shellActive()) {
			if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
			history.replaceState({ ppa: 1, id: 0 }, '', location.href);
			initResize();
		}
		fire('ppa:load', { url: location.href, initial: true });
	});
})();
