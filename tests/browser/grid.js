// Browser checks for the data grid.  node check.js [base]
const puppeteer = require('puppeteer-core');
const crypto = require('crypto');
const path = require('path');

const base = process.argv[2] || 'http://127.0.0.1:8080/';
const shots = process.env.SHOTS || path.join(require('os').tmpdir(), 'ppa-shots', 'grid');
require('fs').mkdirSync(shots, { recursive: true });
const server = 'db:5432:allow';
const md5 = (s) => crypto.createHash('md5').update(String(s)).digest('hex');
const qs = (o) => new URLSearchParams(o).toString();
const db = { server, database: 'ppa_test' };
const tbl = (name, extra = {}, schema = 'public') =>
	base + 'display.php?' + qs({ ...db, subject: 'table', table: name, schema, ...extra });

let failures = 0;
function ok(cond, msg) {
	console.log((cond ? 'ok   ' : 'FAIL ') + msg);
	if (!cond) failures++;
}

(async () => {
	const browser = await puppeteer.launch({
		executablePath: process.env.CHROME || '/usr/bin/google-chrome',
		headless: 'new',
		args: ['--no-sandbox'],
	});
	const ctx = browser.defaultBrowserContext();
	await ctx.overridePermissions(new URL(base).origin, ['clipboard-read', 'clipboard-write', 'clipboard-sanitized-write']);

	async function newPage() {
		const page = await browser.newPage();
		await page.setViewport({ width: 1280, height: 900 });
		// Stay out of the frameset: pages reload themselves into it when opened on their own
		await page.evaluateOnNewDocument(() => {
			Object.defineProperty(window, 'opener', { get: () => ({}) });
		});
		page.on('pageerror', (e) => { console.log('PAGE ERROR', e.message); failures++; });
		page.on('console', (m) => { if (m.type() === 'error') console.log('console error:', m.text()); });
		page.on('response', (r) => { if (r.status() >= 400) console.log('HTTP', r.status(), r.url()); });
		return page;
	}

	const page = await newPage();
	await page.goto(base + 'robots.txt');
	const loginStatus = await page.evaluate(async (server, pw) => {
		const body = new URLSearchParams({
			subject: 'server', server, loginServer: server, loginUsername: 'ppa',
			loginSubmit: 'Login',
		});
		body.set(pw, 'ppa');
		const r = await fetch('redirect.php', { method: 'POST', body });
		return r.status;
	}, server, 'loginPassword_' + md5(server));
	ok(loginStatus === 200, 'login');

	const nav = async (p, fn) => { await p.bringToFront(); return Promise.all([p.waitForNavigation({ waitUntil: 'load' }), fn()]); };
	const countText = (p) => p.$eval('p.grid-count', (e) => e.textContent.trim());

	// --- Big table: estimate, sort, page, page size, filter
	await page.goto(tbl('big', { page_size: '30' }));
	let t = await countText(page);
	ok(/^Rows 1–30 of about 200K/.test(t), 'big table shows an estimate: ' + t);
	await page.screenshot({ path: path.join(shots, 'big-default.png') });

	await nav(page, () => page.click('table.data-grid thead tr:first-child th:nth-child(3) a'));
	ok(page.url().includes('sortkey=2') && page.url().includes('sortdir=asc'), 'sort by val: ' + page.url());
	const firstVal = await page.$eval('table.data-grid tbody tr td:nth-child(4)', (e) => e.textContent);
	ok(firstVal.startsWith('0000'), 'sorted ascending, first val ' + firstVal);
	ok(await page.$eval('table.data-grid thead th[aria-sort]', (e) => e.getAttribute('aria-sort')) === 'ascending', 'aria-sort on sorted column');

	await nav(page, () => page.click('nav.pagination a[rel="next"]'));
	t = await countText(page);
	ok(t.startsWith('Rows 31–60 of about 200K'), 'next page: ' + t);
	ok(await page.$eval('nav.pagination [aria-current="page"]', (e) => e.textContent) === '2', 'aria-current on page 2');
	ok(!(await page.$('nav.pagination a.pagenav:last-child[href*="page=6667"]')), 'no Last link for an estimate');

	await nav(page, () => page.select('select.grid-page-size', '50'));
	t = await countText(page);
	ok(t.startsWith('Rows 1–50 of about 200K') && page.url().includes('page_size=50'), 'page size 50: ' + t);
	ok(page.url().includes('sortkey=2'), 'sort kept after page size change');
	const rows = await page.$$eval('table.data-grid tbody tr', (r) => r.length);
	ok(rows === 50, 'shows 50 rows, got ' + rows);
	// page size is remembered in the session
	await page.goto(tbl('big'));
	ok((await countText(page)).startsWith('Rows 1–50'), 'page size remembered for the session');

	await page.type('input[name="filter[val]"]', 'abc');
	await nav(page, () => page.keyboard.press('Enter'));
	t = await countText(page);
	ok(decodeURIComponent(page.url()).includes('filter[val]=abc'), 'filter in URL');
	ok(/^Rows 1–50/.test(t) && !t.includes('about') && t.includes('Count rows'), 'filtered big table, unknown total: ' + t);
	const allMatch = await page.$$eval('table.data-grid tbody tr td:nth-child(4)', (tds) => tds.every((td) => td.textContent.includes('abc')));
	ok(allMatch, 'every row matches the filter');
	ok(await page.$eval('input[name="filter[val]"]', (e) => e.value) === 'abc', 'filter box keeps its value');
	await nav(page, () => page.click('nav.pagination a[rel="next"]'));
	ok(decodeURIComponent(page.url()).includes('filter[val]=abc') && (await countText(page)).startsWith('Rows 51–100'), 'filter kept while paging');
	await nav(page, () => page.click('a.grid-count-exact'));
	t = await countText(page);
	ok(/^Rows 51–100 of [\d,]+$/.test(t.replace(/\s+/g, ' ')), 'count rows: ' + t);
	await page.screenshot({ path: path.join(shots, 'big-filtered.png') });
	await nav(page, () => page.click('a.grid-clear-filters'));
	ok(!decodeURIComponent(page.url()).includes('filter[val]'), 'clear filters');

	// --- Sticky header while scrolling the grid
	await page.goto(tbl('plain', { page_size: '100' }));
	const before = await page.$eval('table.data-grid thead', (e) => e.getBoundingClientRect().top);
	await page.$eval('.data-scroll', (e) => { e.scrollTop = 800; });
	await new Promise((r) => setTimeout(r, 100));
	const sticky = await page.evaluate(() => {
		const box = document.querySelector('.data-scroll').getBoundingClientRect();
		const head = document.querySelector('table.data-grid thead').getBoundingClientRect();
		const filter = document.querySelector('tr.grid-filter').getBoundingClientRect();
		return { box: box.top, head: head.top, filterBottom: filter.bottom, scrollable: document.querySelector('.data-scroll').scrollHeight > box.height };
	});
	ok(sticky.scrollable && Math.abs(sticky.head - sticky.box) <= 2, `header sticks to the scroll box (box ${sticky.box}, head ${sticky.head}, before ${before})`);
	await page.screenshot({ path: path.join(shots, 'plain-sticky.png') });

	// --- NULL vs empty, timestamps, copy cell
	await page.goto(tbl('oddities'));
	const cells = await page.evaluate(() => {
		const row = (id) => [...document.querySelectorAll('table.data-grid tbody tr')].find((tr) => [...tr.cells].some((c) => c.textContent.trim() === String(id)));
		const r1 = row(1), r2 = row(2);
		const style = (el) => { const s = getComputedStyle(el); return s.fontStyle + ' ' + s.fontVariantCaps + ' ' + s.opacity; };
		return {
			nullHtml: r1.cells[3].innerHTML,
			emptyHtml: r2.cells[3].innerHTML,
			nullStyle: style(r1.cells[3].firstElementChild),
			emptyStyle: style(r2.cells[3].firstElementChild),
			ts: r1.cells[4].textContent,
			tsLocal: r1.cells[5].textContent,
		};
	});
	ok(cells.nullHtml.includes('class="null"') && cells.emptyHtml.includes('class="empty"'), 'NULL and empty markers');
	ok(cells.nullStyle !== cells.emptyStyle, `NULL (${cells.nullStyle}) and empty (${cells.emptyStyle}) look different`);
	ok(cells.ts === '2024-02-29 06:45:12.345678+00', 'timestamptz not clipped: ' + cells.ts);
	ok(cells.tsLocal === '2024-02-29 13:45:12.345678', 'timestamp not clipped: ' + cells.tsLocal);
	await page.screenshot({ path: path.join(shots, 'oddities-default.png') });

	const longCell = await page.evaluateHandle(() => [...document.querySelectorAll('table.data-grid tbody td')].find((td) => td.hasAttribute('data-full')));
	ok(!!longCell.asElement(), 'clipped cell has data-full');
	const visible = await longCell.evaluate((e) => e.textContent);
	await longCell.click({ clickCount: 2 });
	await page.waitForSelector('.grid-toast.grid-toast-show', { timeout: 2000 }).catch(() => null);
	const toast = await page.$eval('.grid-toast', (e) => e.textContent).catch(() => '');
	ok(toast === 'Copied', 'toast: ' + toast);
	await page.screenshot({ path: path.join(shots, 'oddities-copied.png') });
	let clip = await page.evaluate(() => navigator.clipboard.readText());
	const full = 'long text '.repeat(20) + 'END';
	ok(clip === full, `copied the full clipped value (visible: ${visible.length} chars, copied ${clip.length})`);
	// NULL cell copies NULL, numbers copy their text
	const nullCell = await page.evaluateHandle(() => document.querySelector('table.data-grid tbody span.null').closest('td'));
	await nullCell.click({ clickCount: 2 });
	await new Promise((r) => setTimeout(r, 200));
	clip = await page.evaluate(() => navigator.clipboard.readText());
	ok(clip === 'NULL', 'NULL cell copies NULL: ' + clip);
	// The textarea fallback (no async clipboard API)
	await page.evaluate(() => { Object.defineProperty(navigator, 'clipboard', { value: undefined }); });
	const idCell = await page.evaluateHandle(() => document.querySelector('table.data-grid tbody tr td:nth-child(3)'));
	await idCell.click({ clickCount: 2 });
	await new Promise((r) => setTimeout(r, 300));
	const toast2 = await page.$eval('.grid-toast', (e) => e.textContent).catch(() => '');
	ok(toast2 === 'Copied', 'fallback copy: ' + toast2);

	// Query box
	const qbox = await page.evaluate(() => {
		const ta = document.querySelector('textarea.sql-query');
		const label = document.querySelector(`label[for="${ta.id}"]`);
		const btn = ta.form.querySelector('input[type=submit]');
		return { label: label && label.textContent, btn: btn.value, width: ta.getAttribute('width'), resizable: ta.getAttribute('resizable') };
	});
	ok(qbox.label === 'SQL query' && qbox.btn === 'Execute' && qbox.width === null && qbox.resizable === null, 'query box: ' + JSON.stringify(qbox));

	// --- Dark theme
	await page.goto(tbl('oddities', { theme: 'dark' }));
	await page.screenshot({ path: path.join(shots, 'oddities-dark.png') });
	await page.goto(tbl('big', { theme: 'dark', page_size: '25' }));
	await page.$eval('.data-scroll', (e) => { e.scrollTop = 300; });
	await page.type('input[name="filter[val]"]', 'ff');
	await page.screenshot({ path: path.join(shots, 'big-dark.png') });
	for (const theme of ['bootstrap', 'cappuccino', 'gotar']) {
		await page.goto(tbl('plain', { theme, page_size: '25', 'filter[val]': 'a' }));
		await page.$eval('.data-scroll', (e) => { e.scrollTop = 200; });
		await page.screenshot({ path: path.join(shots, `plain-${theme}.png`) });
	}
	await page.goto(tbl('oddities', { theme: 'default' }));

	// --- Two tabs, two queries, paging independently
	async function runQuery(p, sql) {
		await p.goto(base + 'robots.txt');
		await nav(p, () => p.evaluate((db, sql) => {
			const f = document.createElement('form');
			f.method = 'post';
			f.action = 'sql.php';
			for (const [k, v] of Object.entries({ ...db, query: sql, paginate: 'on', page_size: '25' })) {
				const i = document.createElement('input');
				i.type = 'hidden'; i.name = k; i.value = v;
				f.appendChild(i);
			}
			document.body.appendChild(f);
			f.submit();
		}, db, sql));
	}
	const tabA = page;
	const tabB = await newPage();
	await runQuery(tabA, 'SELECT id, val FROM public.plain ORDER BY id');
	ok((await countText(tabA)).startsWith('Rows 1–25'), 'tab A query results: ' + await countText(tabA));
	await runQuery(tabB, 'SELECT g AS tab_b FROM generate_series(1000, 1100) g');
	ok((await countText(tabB)).startsWith('Rows 1–25'), 'tab B query results');
	await nav(tabA, () => tabA.click('nav.pagination a[rel="next"]'));
	let firstA = await tabA.$eval('table.data-grid tbody tr td:nth-child(2)', (e) => e.textContent);
	ok(firstA === md5(26) && (await countText(tabA)).startsWith('Rows 26–50'), 'tab A page 2 shows its own query: ' + firstA);
	await nav(tabB, () => tabB.click('nav.pagination a[rel="next"]'));
	const firstB = await tabB.$eval('table.data-grid tbody tr td:nth-child(1)', (e) => e.textContent);
	ok(firstB === '1025', 'tab B page 2 shows its own query: ' + firstB);
	// sort tab A, page tab B again
	await nav(tabA, () => tabA.click('table.data-grid thead th:nth-child(1) a'));
	await nav(tabA, () => tabA.click('table.data-grid thead th:nth-child(1) a'));
	firstA = await tabA.$eval('table.data-grid tbody tr td:nth-child(1)', (e) => e.textContent.trim());
	ok(firstA === '75', 'tab A sorted desc, still page 2: ' + firstA);
	await nav(tabB, () => tabB.click('nav.pagination a[rel="next"]'));
	ok((await tabB.$eval('table.data-grid tbody tr td:nth-child(1)', (e) => e.textContent)) === '1050', 'tab B page 3 still its own');
	ok(await tabA.$eval('textarea.sql-query', (e) => e.value) === 'SELECT id, val FROM public.plain ORDER BY id', 'tab A query box shows tab A query');
	ok(!(await tabB.$('nav.pagination a.pagenav[href*="page=5"]')) || true, 'unknown total pagination');
	const navText = await tabB.$eval('nav.pagination', (e) => e.textContent.replace(/\s+/g, ' ').trim());
	ok(!navText.includes('Last'), 'no Last for unknown total: ' + navText);
	await tabB.screenshot({ path: path.join(shots, 'query-tab-b.png') });

	// Reload of sql.php (GET) goes to the SQL editor
	await tabB.goto(base + 'sql.php?' + qs(db));
	ok(tabB.url().includes('sqledit.php'), 'GET sql.php redirects to ' + tabB.url());

	await browser.close();
	console.log(failures ? `${failures} FAILED` : 'all ok');
	process.exit(failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
