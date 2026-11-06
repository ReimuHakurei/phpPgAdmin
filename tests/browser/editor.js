// Browser checks for the SQL editor. Usage: node test.js [baseUrl]
const puppeteer = require('puppeteer-core');
const path = require('path');
const fs = require('fs');

const BASE = (process.argv[2] || 'http://127.0.0.1:8080').replace(/\/$/, '') + '/';
const SERVER = 'db:5432:allow';
const Q = encodeURIComponent(SERVER);
const SHOTS = process.env.SHOTS || path.join(require('os').tmpdir(), 'ppa-shots', 'editor');
fs.mkdirSync(SHOTS, { recursive: true });

let failures = 0;
function ok(cond, msg) {
	console.log((cond ? 'ok    ' : 'FAIL  ') + msg);
	if (!cond) failures++;
}

async function shot(page, name) {
	await page.screenshot({ path: path.join(SHOTS, name + '.png'), fullPage: true });
}

const cmValue = (page) => page.evaluate(() => document.querySelector('.CodeMirror').CodeMirror.getValue());
const cmSet = (page, v) => page.evaluate((v) => {
	const cm = document.querySelector('.CodeMirror').CodeMirror;
	cm.setValue(v); cm.focus(); cm.setCursor(cm.lineCount(), 0);
}, v);

async function run(page, key = 'Control') {
	await page.focus('.CodeMirror textarea');
	await Promise.all([
		page.waitForNavigation({ waitUntil: 'load' }),
		(async () => { await page.keyboard.down(key); await page.keyboard.press('Enter'); await page.keyboard.up(key); })(),
	]);
}

(async () => {
	const browser = await puppeteer.launch({
		executablePath: process.env.CHROME || '/usr/bin/google-chrome',
		headless: true,
		args: ['--no-sandbox', '--window-size=1280,900'],
		defaultViewport: { width: 1280, height: 900 },
	});
	const page = await browser.newPage();
	// Pages re-wrap themselves in the old frameset when opened directly;
	// pretend to be a popup so they render standalone (like in the new shell).
	await page.evaluateOnNewDocument(() => {
		try { Object.defineProperty(window, 'opener', { get: () => window, configurable: true }); } catch (e) { /* */ }
	});
	const jsErrors = [];
	page.on('pageerror', (e) => jsErrors.push(e.message));
	page.on('console', (m) => { if (m.type() === 'error') jsErrors.push('console: ' + m.text()); });

	// Log in
	await page.goto(BASE + 'redirect.php?subject=server&server=' + Q, { waitUntil: 'load' });
	await page.type('input[name=loginUsername]', 'ppa');
	await page.type('input[type=password]', 'ppa');
	await Promise.all([page.waitForNavigation(), page.click('[name=loginSubmit]')]);

	// Database SQL tab
	await page.goto(BASE + `database.php?subject=database&server=${Q}&database=ppa_test&action=sql&new=1`, { waitUntil: 'load' });
	ok(await page.$('.CodeMirror.sql-editor-cm') !== null, 'SQL tab: textarea became a CodeMirror editor');
	ok(await page.$('.sql-editor-toolbar details.sql-editor-history') !== null, 'SQL tab: recent queries menu added to toolbar');
	const analyzeTitle = await page.$eval('input[name=explain_analyze]', (e) => e.title + ' | ' + e.value);
	ok(/really executed/.test(analyzeTitle), 'Explain analyze says it runs the statement: ' + analyzeTitle);
	// unpaginated, so results come from sql.php
	await page.$eval('#paginate', (e) => { e.checked = false; });
	await page.focus('.CodeMirror textarea');
	await page.keyboard.type('SELECT * FROM shop.orders ORDER BY 1');
	await shot(page, '01-sqltab-default');
	await run(page);
	ok(page.url().endsWith('sql.php'), 'Ctrl+Enter submitted to sql.php (' + page.url() + ')');
	ok(await page.$('table.data-grid thead th.data') !== null, 'results: data-grid table with thead/th.data');
	ok(await page.$('div.data-scroll table.data-grid') !== null, 'results: wrapped in div.data-scroll');
	ok((await cmValue(page)) === 'SELECT * FROM shop.orders ORDER BY 1', 'results: editor prefilled with the query');
	const formAbove = await page.evaluate(() => {
		const f = document.querySelector('#sql-editor-form'), t = document.querySelector('table.data-grid');
		return !!(f && t && (f.compareDocumentPosition(t) & Node.DOCUMENT_POSITION_FOLLOWING));
	});
	ok(formAbove, 'results: editor form is above the results');
	ok(/SQL executed\./.test(await page.content()), 'results: success line shown');
	await shot(page, '02-results-default');

	// Run selection
	await cmSet(page, 'SELECT 1 AS first_col;\nSELECT 2 AS second_col');
	await page.evaluate(() => {
		const cm = document.querySelector('.CodeMirror').CodeMirror;
		cm.setSelection({ line: 1, ch: 0 }, { line: 1, ch: 23 });
	});
	ok(await page.$eval('.sql-editor-execute', (e) => e.value) === 'Execute selection', 'selection: Execute button reads "Execute selection"');
	await run(page);
	let heads = await page.$$eval('table.data-grid th', (a) => a.map((e) => e.textContent));
	ok(heads.join() === 'second_col', 'selection: only the selection ran (columns: ' + heads.join() + ')');
	ok((await cmValue(page)) === 'SELECT 1 AS first_col;\nSELECT 2 AS second_col', 'selection: editor still has the full text');
	const sel = await page.evaluate(() => document.querySelector('.CodeMirror').CodeMirror.getSelection());
	ok(sel === 'SELECT 2 AS second_col', 'selection: selection restored after the run');

	// Explain
	await cmSet(page, 'SELECT * FROM shop.orders WHERE id = 1;');
	await Promise.all([page.waitForNavigation(), page.click('input[name=explain]')]);
	ok(/QUERY PLAN/.test(await page.content()), 'explain: plan shown');
	ok((await cmValue(page)) === 'SELECT * FROM shop.orders WHERE id = 1;', 'explain: editor keeps the query without EXPLAIN');
	await Promise.all([page.waitForNavigation(), page.click('input[name=explain_analyze]')]);
	ok(/actual time=/.test(await page.content()) && /Buffers|Planning/.test(await page.content()), 'explain analyze: actual times shown');
	await shot(page, '03-explain-analyze');

	// History menu
	await page.click('details.sql-editor-history > summary');
	await page.waitForSelector('.sql-editor-history-menu ul, .sql-editor-history-menu p');
	const items = await page.$$eval('.sql-editor-history-item', (a) => a.map((e) => e.textContent));
	ok(items.length >= 3 && items[0].startsWith('SELECT * FROM shop.orders WHERE id = 1'), 'history: recent queries listed, newest first (' + items.length + ')');
	ok(items.filter((t) => t.startsWith('SELECT * FROM shop.orders WHERE id = 1')).length === 1, 'history: duplicates collapsed');
	ok(items.includes('SELECT 2 AS second_col'), 'history: stored the selection that ran');
	await shot(page, '04-history-menu');
	await page.evaluate(() => [...document.querySelectorAll('.sql-editor-history-item')].find((e) => e.textContent === 'SELECT * FROM shop.orders ORDER BY 1').click());
	ok((await cmValue(page)) === 'SELECT * FROM shop.orders ORDER BY 1', 'history: recalled query into the editor');
	ok(await page.$('details.sql-editor-history[open]') === null, 'history: menu closed after pick');

	// Autocomplete after "."
	await cmSet(page, 'SELECT * FROM shop');
	await page.keyboard.type('.');
	await page.waitForSelector('.CodeMirror-hints', { timeout: 3000 }).catch(() => null);
	let hints = await page.$$eval('.CodeMirror-hint', (a) => a.map((e) => e.textContent));
	ok(hints.includes('shop.orders') && hints.includes('shop.customer'), 'autocomplete after "shop.": ' + hints.slice(0, 8).join(', '));
	await shot(page, '05-autocomplete-schema');
	await page.keyboard.press('Escape');
	// columns after "shop.orders."
	await cmSet(page, 'SELECT shop.orders');
	await page.keyboard.type('.');
	await page.waitForSelector('.CodeMirror-hints', { timeout: 3000 }).catch(() => null);
	hints = await page.$$eval('.CodeMirror-hint', (a) => a.map((e) => e.textContent));
	ok(hints.includes('shop.orders.customer_id'), 'autocomplete columns after "shop.orders.": ' + hints.slice(0, 6).join(', '));
	await page.keyboard.press('Escape');
	// unqualified table of the search path (public.plain), then its columns
	await cmSet(page, 'SELECT * FROM pla');
	await page.keyboard.down('Control'); await page.keyboard.press('Space'); await page.keyboard.up('Control');
	await page.waitForSelector('.CodeMirror-hints', { timeout: 3000 }).catch(() => null);
	hints = await page.$$eval('.CodeMirror-hint', (a) => a.map((e) => e.textContent));
	ok(hints.includes('plain'), 'autocomplete unqualified search_path table: ' + hints.slice(0, 6).join(', '));
	await page.keyboard.press('Escape');
	await cmSet(page, 'SELECT plain');
	await page.keyboard.type('.');
	await page.waitForSelector('.CodeMirror-hints', { timeout: 3000 }).catch(() => null);
	hints = await page.$$eval('.CodeMirror-hint', (a) => a.map((e) => e.textContent));
	ok(hints.includes('plain.val'), 'autocomplete columns after "plain.": ' + hints.join(', '));
	await page.keyboard.press('Escape');
	// Bare name of a table outside the search path: offered schema-qualified
	await cmSet(page, 'SELECT * FROM ord');
	await page.keyboard.down('Control'); await page.keyboard.press('Space'); await page.keyboard.up('Control');
	await page.waitForSelector('.CodeMirror-hints', { timeout: 3000 }).catch(() => null);
	hints = await page.$$eval('.CodeMirror-hint', (a) => a.map((e) => e.textContent));
	ok(hints.includes('ordersin shop') && !hints.includes('orders'), 'table outside the search path offered with its schema: ' + hints.slice(0, 4).join(', '));
	await page.keyboard.press('Escape');
	await cmSet(page, 'SELECT * FROM custo');
	await page.keyboard.down('Control'); await page.keyboard.press('Space'); await page.keyboard.up('Control');
	await page.waitForSelector('.CodeMirror-hints', { timeout: 3000 }).catch(() => null);
	await page.keyboard.press('Enter');
	ok(await cmValue(page) === 'SELECT * FROM shop.customer', 'picking it inserts schema.table: ' + JSON.stringify(await cmValue(page)));
	await page.keyboard.press('Escape');
	// Ctrl+Space keywords + tables
	await cmSet(page, 'SEL');
	await page.keyboard.down('Control'); await page.keyboard.press('Space'); await page.keyboard.up('Control');
	await page.waitForSelector('.CodeMirror-hints', { timeout: 3000 }).catch(() => null);
	hints = await page.$$eval('.CodeMirror-hint', (a) => a.map((e) => e.textContent));
	ok(hints.includes('SELECT'), 'Ctrl+Space completes keywords: ' + hints.slice(0, 5).join(', '));
	await page.keyboard.press('Escape');

	// Suggestions pop up while typing, without Ctrl+Space
	const hintsShown = async () => {
		await new Promise((r) => setTimeout(r, 300));
		return page.$$eval('.CodeMirror-hint', (a) => a.map((e) => e.textContent));
	};
	await cmSet(page, '');
	await page.keyboard.type('sel');
	hints = await hintsShown();
	ok(hints.some((h) => /^select$/i.test(h)), 'typing "sel" suggests SELECT: ' + hints.slice(0, 5).join(', '));
	await page.keyboard.press('Enter');
	ok(/^select$/i.test(await cmValue(page)), 'Enter takes the suggestion: ' + JSON.stringify(await cmValue(page)));
	await page.keyboard.type(' * FROM pla');
	hints = await hintsShown();
	ok(hints.includes('plain'), 'typing a table name suggests it: ' + hints.slice(0, 5).join(', '));
	await page.keyboard.type('in');
	hints = await hintsShown();
	ok(hints.length === 0, 'no popup once the word is complete: ' + hints.join(', '));
	await page.keyboard.press('Enter');
	ok(/plain\n$/.test(await cmValue(page)), 'Enter after a finished word makes a new line');
	await cmSet(page, "SELECT 'pla");
	await page.keyboard.type('in');
	ok((await hintsShown()).length === 0, 'no suggestions inside a string');
	await cmSet(page, 'SELECT 1 -- pla');
	await page.keyboard.type('in');
	ok((await hintsShown()).length === 0, 'no suggestions inside a comment');
	await cmSet(page, 'SELECT x');
	ok((await hintsShown()).length === 0, 'one letter is not enough');

	// Schema names: suggested while typing; picking one lists its tables
	await cmSet(page, 'SELECT * FROM ');
	await page.keyboard.type('sh');
	hints = await hintsShown();
	ok(hints[0] === 'shopschema', 'schema suggested first: ' + hints.slice(0, 4).join(', '));
	await page.keyboard.press('Enter');
	await new Promise((r) => setTimeout(r, 300));
	ok(await cmValue(page) === 'SELECT * FROM shop.', 'picking a schema adds the dot: ' + JSON.stringify(await cmValue(page)));
	hints = await hintsShown();
	ok(hints.includes('shop.orders') && hints.includes('shop.customer'), 'and lists its tables: ' + hints.slice(0, 4).join(', '));
	await page.keyboard.press('Escape');
	await cmSet(page, 'SELECT * FROM ');
	await page.keyboard.type('pub');
	hints = await hintsShown();
	ok(hints.includes('publicschema'), 'schema in the search path suggested too: ' + hints.slice(0, 4).join(', '));
	await page.keyboard.press('Escape');

	// Error highlighting
	await cmSet(page, 'SELECT *\nFROM shop.orders\nWHERE nope = 1');
	await run(page);
	ok(await page.$('table.sql-error') !== null, 'error: error box shown');
	ok(!/SQL executed\./.test(await page.content()), 'error: no "SQL executed." line');
	const errLines = await page.$$eval('.CodeMirror .sql-editor-error-line', (a) => a.length);
	ok(errLines === 1, 'error: one editor line highlighted');
	const errLineNo = await page.evaluate(() => {
		const cm = document.querySelector('.CodeMirror').CodeMirror;
		for (let i = 0; i < cm.lineCount(); i++) {
			const info = cm.lineInfo(i);
			if (info.bgClass && info.bgClass.includes('sql-editor-error-line')) return i + 1;
		}
		return 0;
	});
	ok(errLineNo === 3, 'error: highlighted line is 3 (got ' + errLineNo + ')');
	ok(await page.$('.sql-editor-error-mark') !== null, 'error: offending word marked');
	await shot(page, '06-error-default');

	// Row cap
	await cmSet(page, 'SELECT generate_series(1,5000)');
	await run(page);
	const rows = await page.$$eval('table.data-grid tbody tr', (a) => a.length);
	ok(rows === 1000, 'cap: 1000 rows rendered (got ' + rows + ')');
	const notice = await page.$eval('p.sql-rows-capped', (e) => e.textContent).catch(() => '');
	ok(/1000 of 5000/.test(notice), 'cap: notice: ' + notice);

	// Long values clip + expand toggle
	await cmSet(page, "SELECT repeat('abcdefghij', 30) AS long_text, 1 AS n");
	await run(page);
	const clippedVisible = await page.$eval('.sql-clipped', (e) => getComputedStyle(e).display !== 'none' && e.textContent.length < 100);
	ok(clippedVisible, 'clip: long value shown clipped');
	await shot(page, '14-clip-default');
	await page.click('label[for^=sql-expand]');
	await shot(page, '15-expanded-default');
	const fullVisible = await page.$eval('.sql-full', (e) => getComputedStyle(e).display !== 'none' && e.textContent.length === 300);
	ok(fullVisible, 'clip: toggle shows the full value');

	// Reload of the tree only on DDL
	await cmSet(page, 'SELECT 1');
	await run(page);
	ok(!/ppa\.reloadTree\(/.test(await page.content()), 'reload: SELECT does not reload the tree');
	await cmSet(page, '-- make a table\nCREATE TABLE public.c1_tmp (id int); DROP TABLE public.c1_tmp;');
	await run(page);
	ok(/ppa\.reloadTree\(/.test(await page.content()), 'reload: CREATE/DROP reloads the tree');

	// sqledit.php: SQL mode, switching database with the select
	await page.goto(BASE + `sqledit.php?action=sql&server=${Q}`, { waitUntil: 'load' });
	ok(await page.$('.CodeMirror.sql-editor-cm') !== null, 'sqledit: editor present');
	ok(await page.$('.trail') !== null, 'sqledit: trail shown');
	await Promise.all([page.waitForNavigation(), page.select('select[name=database]', 'ppa_test')]);
	ok(/database=ppa_test/.test(page.url()) && /action=sql/.test(page.url()), 'sqledit: database select reloads the page for it (' + page.url() + ')');
	ok(await page.$eval('#sql-editor-form input[type=hidden][name=database]', (e) => e.value) === 'ppa_test', 'sqledit: main form carries the database');
	await page.$eval('#paginate', (e) => { e.checked = false; });
	await cmSet(page, 'SELECT current_database() AS db');
	await shot(page, '07-sqledit-default');
	await run(page, 'Meta');
	ok(/ppa_test/.test(await page.$eval('table.data-grid', (e) => e.textContent).catch(() => '')), 'sqledit: Cmd+Enter runs on the chosen database');

	// Find
	await page.goto(BASE + `sqledit.php?action=find&server=${Q}&database=ppa_test`, { waitUntil: 'load' });
	await page.type('input[name=term]', 'orders');
	await Promise.all([page.waitForNavigation(), page.click('#sqledit-find input[type=submit]')]);
	ok(/database\.php/.test(page.url()) && /orders/.test(await page.content()), 'find: search runs on database.php in the same page');

	// History page re-run
	await page.goto(BASE + `history.php?server=${Q}&database=ppa_test`, { waitUntil: 'load' });
	await shot(page, '08-history-page');
	const runLink = await page.$$eval('a', (a) => a.filter((e) => /sql\.php\?subject=history/.test(e.href)).map((e) => [e.href, e.target]));
	ok(runLink.length > 0 && runLink.every((l) => !l[1]), 'history page: re-run links without target');
	await Promise.all([page.waitForNavigation(), page.goto(runLink[0][0])]);
	ok(await page.$('.CodeMirror') !== null && /Query Results/.test(await page.title()), 'history page: re-run link opens results');

	// Dark theme
	await page.goto(BASE + `database.php?subject=database&server=${Q}&database=ppa_test&action=sql&theme=dark`, { waitUntil: 'load' });
	ok(await page.$('.CodeMirror.cm-s-ppa-dark') !== null, 'dark: editor uses the dark syntax theme');
	await page.$eval('#paginate', (e) => { e.checked = false; });
	await cmSet(page, "-- orders per status\nSELECT status, count(*) AS n, 'text' AS s\nFROM shop.orders\nWHERE id > 0\nGROUP BY status;");
	await shot(page, '09-sqltab-dark');
	await page.keyboard.press('Enter');
	await page.keyboard.type('SELECT * FROM shop');
	await page.keyboard.type('.');
	await page.waitForSelector('.CodeMirror-hints', { timeout: 3000 }).catch(() => null);
	await shot(page, '10-autocomplete-dark');
	await page.keyboard.press('Escape');
	await cmSet(page, 'SELECT status, count(*)\nFROM shop.orders\nGROUP BY statuss');
	await run(page);
	await shot(page, '12-error-dark');
	await cmSet(page, "SELECT g, repeat('abcdefghij', 30) AS long_text FROM generate_series(1, 1200) g");
	await run(page);
	await shot(page, '13-cap-clip-dark');
	await page.click('details.sql-editor-history > summary');
	await shot(page, '11-error-history-dark');
	await page.goto(BASE + `database.php?subject=database&server=${Q}&database=ppa_test&action=sql&theme=default`, { waitUntil: 'load' });

	ok(jsErrors.filter((e) => !/reading 'location'|status of 404/.test(e)).length === 0, 'no JS errors: ' + JSON.stringify(jsErrors));
	await browser.close();
	console.log(failures ? failures + ' FAILED' : 'ALL OK');
	process.exit(failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
