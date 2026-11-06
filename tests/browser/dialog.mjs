// Browser checks for dialogs: row edit as a side panel, row delete and drop
// confirmations as modals.  node dialog.mjs [base]
import puppeteer from 'puppeteer-core';
import crypto from 'crypto';
import fs from 'fs';
import os from 'os';
import path from 'path';

const BASE = (process.argv[2] || 'http://127.0.0.1:8080/').replace(/\/?$/, '/');
const OUT = (process.env.SHOTS || path.join(os.tmpdir(), 'ppa-shots', 'dialog')) + '/';
fs.mkdirSync(OUT, { recursive: true });

const SERVER = 'db:5432:allow';
const Q = encodeURIComponent(SERVER);
const TABLE = 'dlg_test';
const BROWSE = `${BASE}display.php?server=${Q}&database=ppa_test&schema=public&subject=table&table=${TABLE}`;

const browser = await puppeteer.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 800 });

const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push('console.error: ' + m.text()); });
let confirmAnswer = false, confirms = 0;
page.on('dialog', async (d) => { confirms++; if (confirmAnswer) await d.accept(); else await d.dismiss(); });

const results = [];
const check = (ok, what) => results.push((ok ? 'ok    ' : 'FAIL  ') + what);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const idle = () => page.waitForNetworkIdle({ idleTime: 300, timeout: 15000 }).catch(() => {});
const dialogOpen = (kind) => page.evaluate((k) => !!document.querySelector('dialog.ppa-dialog-' + k + '[open]'), kind);
const anyDialog = () => page.evaluate(() => !!document.querySelector('dialog.ppa-dialog'));
const cell = (id) => page.evaluate((id) => {
	for (const tr of document.querySelectorAll('table#data tbody tr, table#data tr')) {
		const tds = [...tr.querySelectorAll('td')].map((td) => td.innerText.trim());
		if (tds.includes(String(id))) return tds.join('|');
	}
	return null;
}, id);
// The row's link exactly: key[id]=2 must not match key[id]=20.
const rowLink = (action, id) => `a[href*="action=${action}"][href*="key%5Bid%5D=${id}&"], a[href*="action=${action}"][href$="key%5Bid%5D=${id}"]`;

async function sql(query) {
	await page.evaluate(async (q, server) => {
		const body = new URLSearchParams({ server, database: 'ppa_test', query: q });
		await fetch('sql.php', { method: 'POST', body });
	}, query, SERVER);
}

try {
	await page.goto(BASE + 'intro.php', { waitUntil: 'networkidle0' });
	await page.evaluate(async (server, pw) => {
		await fetch('redirect.php', { method: 'POST', body: new URLSearchParams({ subject: 'server', server, loginServer: server, loginUsername: 'ppa', ['loginPassword_' + pw]: 'ppa', loginSubmit: 'Login' }) });
	}, SERVER, crypto.createHash('md5').update(SERVER).digest('hex'));
	await sql(`DROP TABLE IF EXISTS public.${TABLE}; CREATE TABLE public.${TABLE} (id int PRIMARY KEY, name text NOT NULL, note text);
		INSERT INTO public.${TABLE} SELECT i, 'row ' || i, NULL FROM generate_series(1, 10) i;`);

	await page.goto(BROWSE, { waitUntil: 'networkidle0' });
	const url = page.url();
	await page.evaluate(() => { window.__marker = 1; window.scrollTo(0, 400); });

	// Edit: side panel
	await page.click(rowLink('confeditrow', 2));
	await page.waitForSelector('dialog.ppa-dialog-panel[open]', { timeout: 5000 }).catch(() => {});
	check(await dialogOpen('panel'), 'Edit opens a side panel');
	check(page.url() === url && await page.evaluate(() => window.__marker === 1), 'the page underneath stays');
	check(await page.$eval('dialog.ppa-dialog h2', (h) => h.textContent.trim().length > 0).catch(() => false), 'panel has the page title as heading');
	check(await page.evaluate(() => !document.querySelector('dialog .trail, dialog .topbar, dialog nav.tabs')), 'no breadcrumbs or tabs in the panel');
	check(await page.evaluate(() => document.querySelector('dialog').contains(document.activeElement)), 'focus moves into the panel');
	await sleep(300); await page.screenshot({ path: OUT + 'edit-panel.png' });

	await page.keyboard.press('Escape'); await sleep(200);
	check(!(await anyDialog()), 'Esc closes an untouched panel');

	// Unsaved changes: Esc asks first
	await page.click(rowLink('confeditrow', 2));
	await page.waitForSelector('dialog.ppa-dialog-panel[open]');
	await page.evaluate(() => {
		const f = document.querySelector('dialog [name="values[name]"]');
		f.focus(); f.select();
	});
	await page.keyboard.type('renamed two');
	confirms = 0; confirmAnswer = false;
	await page.keyboard.press('Escape'); await sleep(200);
	check(confirms === 1 && await dialogOpen('panel'), 'Esc with changes asks, and Cancel keeps the panel');

	// Save
	const scrollBefore = await page.evaluate(() => window.scrollY);
	await page.click('dialog input[type=submit][name=save]');
	await idle(); await sleep(300);
	check(!(await anyDialog()), 'Save closes the panel');
	check(/renamed two/.test(await cell(2) || ''), 'the grid shows the new value: ' + await cell(2));
	check(await page.evaluate(() => [...document.querySelectorAll('#ppa-main p.message')].some((p) => p.getAttribute('role') === 'status')), 'success message shown on the page');
	check(page.url() === url && await page.evaluate(() => window.__marker === 1), 'still the same page, no reload');
	check(Math.abs(await page.evaluate(() => window.scrollY) - scrollBefore) < 5, 'scroll position kept');

	// Save that fails stays in the panel
	await page.click(rowLink('confeditrow', 3));
	await page.waitForSelector('dialog.ppa-dialog-panel[open]');
	await page.evaluate(() => {
		const f = document.querySelector('dialog [name="values[id]"]');
		f.value = '1';   // duplicate primary key
		f.dispatchEvent(new Event('input', { bubbles: true }));
	});
	await page.click('dialog input[type=submit][name=save]');
	await idle(); await sleep(300);
	check(await dialogOpen('panel'), 'a failed save stays in the panel');
	check(await page.evaluate(() => !!document.querySelector('dialog p.message.error')), 'with the error message');
	check(await page.evaluate(() => !!document.querySelector('dialog input[name=save]')), 'and the form to fix it');
	await sleep(300); await page.screenshot({ path: OUT + 'edit-error.png' });
	confirmAnswer = true;
	await page.click('dialog .ppa-dialog-close'); await sleep(200);
	check(!(await anyDialog()), 'close button closes it (after confirming)');
	confirmAnswer = false;

	// Cancel button closes without a request
	await page.click(rowLink('confeditrow', 4));
	await page.waitForSelector('dialog.ppa-dialog-panel[open]');
	let posts = 0;
	const countPosts = (r) => { if (r.method() === 'POST') posts++; };
	page.on('request', countPosts);
	await page.click('dialog input[type=submit][name=cancel]'); await sleep(300);
	page.off('request', countPosts);
	check(!(await anyDialog()) && posts === 0, 'Cancel closes the panel without sending anything');

	// Delete: modal
	await page.click(rowLink('confdelrow', 5));
	await page.waitForSelector('dialog.ppa-dialog-modal[open]', { timeout: 5000 }).catch(() => {});
	check(await dialogOpen('modal'), 'Delete opens a modal');
	check(await page.evaluate(() => document.activeElement && document.activeElement.name === 'no'), 'focus starts on No');
	check(await page.evaluate(() => !!document.querySelector('dialog input.danger[name=yes]')), 'Yes is marked as dangerous');
	await sleep(300); await page.screenshot({ path: OUT + 'delete-modal.png' });
	await page.click('dialog input[name=no]'); await sleep(200);
	check(!(await anyDialog()) && await cell(5) !== null, 'No closes it and keeps the row');
	await page.click(rowLink('confdelrow', 5));
	await page.waitForSelector('dialog.ppa-dialog-modal[open]');
	await page.click('dialog input[name=yes]');
	await idle(); await sleep(300);
	check(!(await anyDialog()) && await cell(5) === null, 'Yes deletes the row and closes the modal');
	check(page.url() === url, 'still on the same page');

	// Clicking the backdrop closes a modal
	await page.click(rowLink('confdelrow', 6));
	await page.waitForSelector('dialog.ppa-dialog-modal[open]');
	await page.mouse.click(5, 790); await sleep(200);
	check(!(await anyDialog()), 'clicking outside closes the modal');

	// Modifier click still opens the page itself
	const href = await page.$eval(rowLink('confeditrow', 7), (a) => a.href);
	await page.goto(href, { waitUntil: 'networkidle0' });
	check(await page.evaluate(() => !!document.querySelector('#ppa-main input[name=save]') && !document.querySelector('dialog')), 'the edit page still works on its own');

	// Drop confirmations from a list open as a modal too
	await page.goto(`${BASE}tables.php?server=${Q}&database=ppa_test&schema=public&subject=schema`, { waitUntil: 'networkidle0' });
	const dropLink = await page.evaluateHandle((t) => [...document.querySelectorAll('a[href*="action=confirm_drop"]')].find((a) => a.href.includes('table=' + t)), TABLE);
	await dropLink.asElement().click();
	await page.waitForSelector('dialog.ppa-dialog-modal[open]', { timeout: 5000 }).catch(() => {});
	check(await dialogOpen('modal'), 'Drop table opens a modal');
	check(await page.evaluate(() => document.activeElement && document.activeElement.name === 'cancel'), 'focus on Cancel');
	await sleep(300); await page.screenshot({ path: OUT + 'drop-modal.png' });
	await page.click('dialog input.danger');
	await idle(); await sleep(500);
	check(!(await anyDialog()), 'dropping closes the modal');
	check(!(await page.evaluate((t) => document.querySelector('#ppa-main').innerText.includes(t + '\n'), TABLE)) && /tables\.php/.test(page.url()), 'table list is shown again without it');

	// Dark theme and phone
	await sql(`CREATE TABLE public.${TABLE} (id int PRIMARY KEY, name text NOT NULL, note text); INSERT INTO public.${TABLE} VALUES (1, 'one', 'x');`);
	await page.goto(BROWSE + '&theme=dark', { waitUntil: 'networkidle0' });
	await page.click(rowLink('confeditrow', 1));
	await page.waitForSelector('dialog.ppa-dialog-panel[open]');
	await sleep(300);
	await page.screenshot({ path: OUT + 'edit-panel-dark.png' });
	await page.keyboard.press('Escape');
	await page.goto(BASE + 'intro.php?theme=default', { waitUntil: 'networkidle0' });
	await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
	await page.goto(BROWSE, { waitUntil: 'networkidle0' });
	await page.tap(rowLink('confeditrow', 1));
	await page.waitForSelector('dialog.ppa-dialog-panel[open]');
	await sleep(300);
	const w = await page.$eval('dialog.ppa-dialog-panel', (d) => d.getBoundingClientRect().width);
	check(w >= 380, 'panel fills a phone screen (' + w + 'px)');
	await page.screenshot({ path: OUT + 'edit-panel-phone.png' });
	await sql(`DROP TABLE IF EXISTS public.${TABLE}`);
} catch (e) {
	results.push('ERROR ' + (e && e.stack || e));
	await page.screenshot({ path: OUT + 'error.png' }).catch(() => {});
}

console.log(results.join('\n'));
console.log('\nJS errors:\n' + (errors.length ? errors.join('\n') : '(none)'));
await browser.close();
process.exit(results.some((r) => !r.startsWith('ok')) || errors.length ? 1 : 0);
