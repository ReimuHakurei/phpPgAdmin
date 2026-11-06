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
		visit(a.href);
	});

	window.addEventListener('submit', function (e) {
		if (e.defaultPrevented) return;
		var form = e.target;
		if (!form || form.nodeName !== 'FORM' || form.hasAttribute('data-ppa-native')) return;
		var submitter = e.submitter || null;
		var action = (submitter && submitter.hasAttribute('formaction')) ? submitter.formAction : form.action;
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
			var cancel = forms[i].querySelector('input[type="submit"][name="cancel"], button[name="cancel"]');
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

	document.addEventListener('ppa:load', function () {
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
