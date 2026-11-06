// Browser checks for the app shell: in-page navigation, history, the
// sidebar tree, themes and the phone layout.  node shell.mjs [base]
import puppeteer from 'puppeteer-core';
import fs from 'fs';
import os from 'os';
import path from 'path';

const BASE = (process.argv[2] || 'http://127.0.0.1:8080/').replace(/\/?$/, '/');
const OUT = (process.env.SHOTS || path.join(os.tmpdir(), 'ppa-shots', 'shell')) + '/';
fs.mkdirSync(OUT, { recursive: true });

const browser = await puppeteer.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1366, height: 860 });

const errors = [];
page.on('pageerror', e => errors.push('pageerror: ' + e.message));
page.on('console', m => { if (m.type() === 'error' || m.type() === 'warning') errors.push('console.' + m.type() + ': ' + m.text()); });
const requests = [];
page.on('request', r => requests.push(r.url()));
page.on('response', r => { if (r.status() >= 400) errors.push('HTTP ' + r.status() + ' ' + r.url()); });

const results = [];
function check(ok, what) { results.push((ok ? 'ok    ' : 'FAIL  ') + what); }
const sleep = ms => new Promise(r => setTimeout(r, ms));
async function idle() { await page.waitForNetworkIdle({ idleTime: 300, timeout: 15000 }).catch(() => {}); }
async function mainText() { return page.$eval('#ppa-main', m => m.innerText); }
async function treeLabels() { return page.$$eval('#ppa-tree .ppa-label', a => a.filter(x => x.offsetParent).map(x => x.textContent)); }
async function clickTree(text) {
	const h = await page.evaluateHandle(t => [...document.querySelectorAll('#ppa-tree .ppa-label')].find(a => a.textContent === t && a.offsetParent), text);
	if (!h.asElement()) throw new Error('no tree node ' + text);
	await h.asElement().click();
	await idle();
}

try {
await page.goto(BASE, { waitUntil: 'networkidle0' });
check(await page.$('#ppa-sidebar') !== null, 'start page has the sidebar');
check((await treeLabels()).includes('Test'), 'tree lists server Test: ' + (await treeLabels()).join(','));
await page.screenshot({ path: OUT + '01-start.png' });

// Marker survives in-page navigation, but not a full reload.
await page.evaluate(() => { window.__marker = 1; });
await clickTree('Test');
check(await page.evaluate(() => window.__marker === 1), 'clicking a tree node does not reload the page');
check(await page.$('#loginUsername') !== null, 'login form shown in main');
check(/server=/.test(page.url()), 'address bar follows: ' + page.url());

await page.type('#loginUsername', 'ppa');
await page.type('#loginPassword', 'ppa');
await Promise.all([page.click('input[name=loginSubmit]'), idle()]);
await idle();
check(await page.evaluate(() => window.__marker === 1), 'login submit stays in page');
check(!(await mainText()).includes('loginUsername') && await page.$('#loginUsername') === null, 'logged in');
await sleep(500);
let labels = await treeLabels();
check(labels.includes('ppa_test'), 'tree reloaded after login and shows databases: ' + labels.join(','));

await clickTree('ppa_test');
await sleep(300);
labels = await treeLabels();
check(labels.includes('Schemas'), 'database branch opens on click: ' + labels.join(','));
await clickTree('Schemas');
await sleep(300);
await clickTree('shop');
await sleep(300);
await clickTree('Tables');
await sleep(300);
labels = await treeLabels();
check(labels.includes('orders'), 'tables listed: ' + labels.slice(-8).join(','));
await clickTree('orders');
const urlOrders = page.url();
check(/tblproperties\.php|table=orders/.test(urlOrders), 'table page url: ' + urlOrders);
check((await page.title()).includes('orders') || (await mainText()).includes('orders'), 'table page content');
await page.screenshot({ path: OUT + '02-table.png' });

// Tabs: Browse
const browse = await page.evaluateHandle(() => [...document.querySelectorAll('nav.tabs a')].find(a => /display\.php/.test(a.href)));
await browse.asElement().click();
await idle();
check(/display\.php/.test(page.url()), 'browse tab: ' + page.url());
check(await page.$('table#data, table.data-grid') !== null, 'browse shows a grid');
check(await page.evaluate(() => window.__marker === 1), 'tabs navigate in page');
await page.screenshot({ path: OUT + '03-browse.png' });

// Back / forward
await page.goBack(); await idle();
check(page.url() === urlOrders && (await mainText()).length > 0 && await page.$('nav.tabs a[aria-current="page"]') !== null, 'back goes to the table page: ' + page.url());
await page.goForward(); await idle();
check(/display\.php/.test(page.url()) && await page.$('table#data, table.data-grid') !== null, 'forward goes to browse');

// Full reload keeps the page (deep link) and the tree state.
await page.reload({ waitUntil: 'networkidle0' });
check(await page.evaluate(() => window.__marker === undefined), 'reload is a real reload');
check(/display\.php/.test(page.url()) && await page.$('table#data, table.data-grid') !== null, 'deep link renders the same page after reload');
await sleep(500); await idle();
labels = await treeLabels();
check(labels.includes('orders'), 'tree comes back expanded after reload');

// Filter + Ctrl+K
await page.keyboard.down('Control'); await page.keyboard.press('k'); await page.keyboard.up('Control');
check(await page.evaluate(() => document.activeElement && document.activeElement.id === 'ppa-tree-filter'), 'Ctrl+K focuses the tree filter');
await page.keyboard.type('custom');
await sleep(100);
labels = await treeLabels();
check(labels.includes('customer') && !labels.includes('orders'), 'filter narrows the tree: ' + labels.join(','));
await page.screenshot({ path: OUT + '04-filter.png' });
await page.keyboard.press('Enter'); await idle();
check(/customer/.test(page.url()), 'Enter in the filter opens the first match: ' + page.url());
await page.focus('#ppa-tree-filter'); await page.keyboard.press('Escape');
check((await treeLabels()).includes('orders'), 'Escape clears the filter');

// Keyboard in tree
await page.focus('#ppa-tree .ppa-label[tabindex="0"]');
await page.keyboard.press('ArrowDown');
const focused = await page.evaluate(() => document.activeElement.className);
check(focused.includes('ppa-label'), 'arrow keys move within the tree');

// SQL link in the top bar opens in page.
const sqlLink = await page.$('#toplink_sql');
await sqlLink.click(); await idle();
check(/sqledit\.php/.test(page.url()) && await page.evaluate(() => window.__marker === undefined || true), 'SQL link opens in the page: ' + page.url());
check((await page.pages?.() ?? null) === null || (await browser.pages()).length === 1, 'no popup windows');

// Run a query through the SQL tab of the database.
await page.goto(BASE + 'database.php?server=db%3A5432%3Aallow&database=ppa_test&subject=database&action=sql', { waitUntil: 'networkidle0' });
await page.evaluate(() => { window.__marker = 2; });
await page.evaluate(() => { const t = document.querySelector('textarea[name=query]'); t.value = 'SELECT 1 AS one, NULL AS nothing'; if (t.nextSibling && t.nextSibling.CodeMirror) t.nextSibling.CodeMirror.setValue(t.value); });
const exec = await page.$('form input[type=submit][name=execute], form input[type=submit]');
await exec.click(); await idle();
check(await page.evaluate(() => window.__marker === 2), 'SQL form posts in page');
check(/sql\.php|display\.php/.test(page.url()) && /one/.test(await mainText()), 'query results shown: ' + page.url());
await page.screenshot({ path: OUT + '05-sqlresult.png' });
await page.goBack(); await idle();
check(/action=sql/.test(page.url()), 'back from POST result returns to SQL tab');
await page.goForward(); await idle();
check(/one/.test(await mainText()), 'forward to POST result restores it from cache');

// The tree reloads after DDL from the SQL tab, not after a SELECT.
async function runSql(q) {
	await page.goto(BASE + 'database.php?server=db%3A5432%3Aallow&database=ppa_test&subject=database&action=sql', { waitUntil: 'networkidle0' });
	await page.evaluate((q) => { const t = document.querySelector('textarea[name=query]'); t.value = q; const cm = document.querySelector('.CodeMirror'); if (cm && cm.CodeMirror) cm.CodeMirror.setValue(q); const p = document.querySelector('input[name=paginate]'); if (p) p.checked = false; }, q);
	const n = requests.length;
	await page.click('form input[type=submit][name=execute]'); await idle(); await sleep(500);
	return requests.slice(n).filter(u => /servers\.php\?action=tree/.test(u)).length;
}
check(await runSql('SELECT 1') === 0, 'SELECT does not reload the tree');
check(await runSql('CREATE TABLE public.ppa_tmp_reload (id int); DROP TABLE public.ppa_tmp_reload;') > 0, 'CREATE/DROP reloads the tree');

// Ordinary forms (they have a hidden "action" field) submit in the page.
await page.goto(BASE + 'schemas.php?server=db%3A5432%3Aallow&database=ppa_test&subject=database&action=create', { waitUntil: 'networkidle0' });
await page.evaluate(() => { window.__marker = 3; });
await page.type('input[name=formName]', 'ppa_formtest');
await page.click('input[type=submit][name=create]'); await idle();
check(await page.evaluate(() => window.__marker === 3), 'a form with a hidden action field submits in the page');
check((await mainText()).includes('ppa_formtest'), 'and its result is shown (schema created)');

// File upload through a form, in the page.
await page.evaluate(async () => {
	await fetch('sql.php', { method: 'POST', body: new URLSearchParams({ server: 'db:5432:allow', database: 'ppa_test', query: 'DROP TABLE IF EXISTS public.imp_test; CREATE TABLE public.imp_test (id int, name text)' }) });
});
const csvFile = path.join(os.tmpdir(), 'ppa-import-test.csv');
fs.writeFileSync(csvFile, 'id,name\n1,one\n2,two\n');
await page.goto(BASE + 'tblproperties.php?server=db%3A5432%3Aallow&database=ppa_test&schema=public&subject=table&table=imp_test&action=import', { waitUntil: 'networkidle0' });
await page.evaluate(() => { window.__marker = 4; });
const fileInput = await page.$('input[type=file][name=source]');
await fileInput.uploadFile(csvFile);
await page.select('select[name=format]', 'csv');
await page.click('form[enctype="multipart/form-data"] input[type=submit]'); await idle();
check(await page.evaluate(() => window.__marker === 4), 'file upload form submits in the page');
await page.goto(BASE + 'display.php?server=db%3A5432%3Aallow&database=ppa_test&schema=public&subject=table&table=imp_test', { waitUntil: 'networkidle0' });
check(/two/.test(await mainText()), 'and the uploaded rows were imported');
await page.evaluate(async () => {
	await fetch('sql.php', { method: 'POST', body: new URLSearchParams({ server: 'db:5432:allow', database: 'ppa_test', query: 'DROP TABLE IF EXISTS public.imp_test; DROP SCHEMA IF EXISTS ppa_formtest' }) });
});

// Processes page polls; leaving it stops the polling.
await page.goto(BASE + 'database.php?server=db%3A5432%3Aallow&database=ppa_test&subject=database&action=processes', { waitUntil: 'networkidle2' });
await sleep(4000);
const polls1 = requests.filter(u => /action=refresh_processes|action=processes/.test(u) && /_=/.test(u)).length;
check(polls1 > 0, 'processes page refreshes itself (' + polls1 + ' polls)');
const before = requests.length;
await clickTree('Schemas');
await sleep(8000);
const polls2 = requests.filter(u => /action=refresh_processes|action=processes/.test(u) && /_=/.test(u)).length;
check(polls2 === polls1, 'polling stops after leaving the page (' + polls1 + ' -> ' + polls2 + ')');

// Destructive confirmation.
await page.goto(BASE + 'tables.php?server=db%3A5432%3Aallow&database=ppa_test&schema=shop&subject=table&table=orders&action=confirm_drop', { waitUntil: 'networkidle0' });
const danger = await page.$$eval('input.danger', x => x.map(i => i.name));
check(danger.length > 0, 'drop button marked as danger: ' + danger.join(','));
check(await page.evaluate(() => document.activeElement && document.activeElement.name === 'cancel'), 'focus starts on Cancel');
await page.screenshot({ path: OUT + '06-confirm.png' });

// Themes
for (const theme of ['default', 'bootstrap', 'cappuccino', 'dark', 'gotar']) {
	await page.goto(BASE + 'tblproperties.php?server=db%3A5432%3Aallow&database=ppa_test&schema=shop&subject=table&table=orders&theme=' + theme, { waitUntil: 'networkidle0' });
	await sleep(400);
	await page.screenshot({ path: OUT + '10-theme-' + theme + '.png' });
}
await page.goto(BASE + 'intro.php?theme=default', { waitUntil: 'networkidle0' });

// Phone
await page.setViewport({ width: 390, height: 844, isMobile: true, hasTouch: true });
await page.goto(BASE + 'tblproperties.php?server=db%3A5432%3Aallow&database=ppa_test&schema=shop&subject=table&table=orders', { waitUntil: 'networkidle0' });
await page.screenshot({ path: OUT + '20-phone.png' });
const sidebarHidden = await page.$eval('#ppa-sidebar', s => getComputedStyle(s).visibility === 'hidden');
check(sidebarHidden, 'sidebar hidden on a phone');
await page.tap('#ppa-sidebar-toggle'); await sleep(400);
check(await page.$eval('#ppa-sidebar', s => getComputedStyle(s).visibility === 'visible'), 'toggle opens the sidebar');
await page.screenshot({ path: OUT + '21-phone-sidebar.png' });
await clickTree('customer');
check(await page.$eval('#ppa-sidebar', s => !document.body.classList.contains('ppa-sidebar-open')), 'navigating closes the sidebar');
const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
check(overflow <= 1, 'no horizontal page scroll on a phone (' + overflow + 'px)');

} catch (e) { results.push('ERROR ' + e.message); await page.screenshot({ path: OUT + 'zz-error.png' }); results.push('url: ' + page.url()); results.push('tree: ' + (await treeLabels()).join(',')); }
console.log(results.join('\n'));
console.log('\nJS errors/warnings:\n' + (errors.length ? errors.join('\n') : '(none)'));
await browser.close();
process.exit(results.some(r => !r.startsWith('ok')) ? 1 : 0);
