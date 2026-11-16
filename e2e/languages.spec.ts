import { test, expect, type Page } from '@playwright/test';
import {
  RUNNING_SERVER_DESC,
  browserTreeRow,
  expandBrowserTreeRow,
  login,
  runningServerId,
} from './support/environment';

const DATABASE = 'postgres';

function languagesUrl(params: Record<string, string>): string {
  return `/languages.php?${new URLSearchParams(params)}`;
}

async function openLanguages(page: Page): Promise<void> {
  await login(page);
  await page.goto(languagesUrl({ server: runningServerId(), database: DATABASE, subject: 'database' }));
}

function searchParams(href: string | null): Record<string, string> {
  return Object.fromEntries(new URL(href ?? '', 'http://localhost/').searchParams);
}

function languagesTable(page: Page) {
  return page.locator('table').filter({ has: page.locator('th', { hasText: 'Trusted' }) });
}

test('Languages > lists the procedural languages of the database', async ({ page }) => {
  await openLanguages(page);

  await expect(page).toHaveTitle('phpPgAdmin - Languages');

  const table = languagesTable(page);
  await expect(table.locator('thead th')).toHaveText(['Name', 'Trusted', 'Function']);
  // show_system is off in the test configuration, so the built-in languages (c, internal, sql) are hidden.
  await expect(table.locator('tbody tr')).toHaveCount(1);
  await expect(table.locator('tbody tr td')).toHaveText(['plpgsql', 'Yes', 'plpgsql_call_handler']);
});

test('Languages > trail leads to the server and the database', async ({ page }) => {
  await openLanguages(page);

  const crumbs = page.locator('div.trail td.crumb');
  await expect(crumbs.locator('span.label')).toHaveText(['phpPgAdmin', RUNNING_SERVER_DESC, DATABASE]);

  const databaseLink = crumbs.locator('a[title="Database"]');
  expect(searchParams(await databaseLink.getAttribute('href'))).toEqual({
    database: DATABASE,
    server: runningServerId(),
    subject: 'database',
  });
  expect(searchParams(await crumbs.nth(2).locator('a.help').getAttribute('href'))).toEqual({
    help: 'pg.database',
    server: runningServerId(),
  });
});

test('Languages > database tabs with the languages tab active', async ({ page }) => {
  await openLanguages(page);

  const tabs = page.locator('table.tabs td.tab');
  // show_advanced is on in the test configuration, which shows the Languages and Casts tabs.
  await expect(tabs.locator('span.label')).toHaveText([
    'Schemas',
    'SQL',
    'Find',
    'Variables',
    'Processes',
    'Locks',
    'Admin',
    'Privileges',
    'Languages',
    'Casts',
    'Export',
  ]);
  await expect(page.locator('table.tabs td.tab.active span.label')).toHaveText(['Languages']);

  const languagesTab = page.locator('table.tabs td.tab.active a').first();
  expect(searchParams(await languagesTab.getAttribute('href'))).toEqual({
    database: DATABASE,
    server: runningServerId(),
    subject: 'database',
  });
});

test('Languages > top bar links lead to the database', async ({ page }) => {
  await openLanguages(page);

  // Like the legacy Misc::printTopbar(), whose links take the database of the request.
  const params = { database: DATABASE, server: runningServerId(), subject: 'table' };
  expect(searchParams(await page.locator('#toplink_sql').getAttribute('href'))).toEqual({ action: 'sql', ...params });
  expect(searchParams(await page.locator('#toplink_history').getAttribute('href'))).toEqual(params);
  expect(searchParams(await page.locator('#toplink_find').getAttribute('href'))).toEqual({ action: 'find', ...params });
});

for (const link of ['sql', 'history', 'find']) {
  test(`Languages > the ${link} link of the top bar selects the database`, async ({ page }) => {
    await openLanguages(page);

    // The pages open in a window of their own, which only matters for the link, not for the selected database.
    await page.goto(`/${await page.locator(`#toplink_${link}`).getAttribute('href')}`);

    await expect(page.locator('select#database')).toHaveValue(DATABASE);
  });
}

// Without a login or a server parameter, see server-login.spec.ts.

test('Languages > without a database parameter the request is rejected', async ({ page }) => {
  await login(page);

  const response = await page.goto(languagesUrl({ server: runningServerId(), subject: 'database' }));

  expect(response?.status()).toBe(400);
  await expect(page.locator('h1')).toHaveText('400: Missing required parameter: database');
});

test('Languages > tree lists the languages of the database', async ({ page }) => {
  await login(page);

  // The database node of the browser tree loads this URL when the Languages node is expanded.
  const response = await page.request.get(
    languagesUrl({ action: 'tree', server: runningServerId(), database: DATABASE, subject: 'database' }),
  );

  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toContain('text/xml');

  const xml = await response.text();
  expect(xml).toMatch(/^<\?xml version="1\.0" encoding="utf-8"\?>/);
  expect(xml).toMatch(/<tree [^>]*text="plpgsql"[^>]*\/>/);
  expect(xml).toMatch(/<tree [^>]*icon="images\/themes\/default\/Language\.png"[^>]*\/>/);
  expect(xml).not.toMatch(/text="internal"/);
});

test('Languages > the database crumb leads back to the languages tab', async ({ page }) => {
  await openLanguages(page);

  // The crumb links redirect.php, which opens the database tab that was used last (a legacy page).
  await page.locator('div.trail a[title="Database"]').click();

  await expect(page).toHaveURL(/\/redirect\.php\?/);
  await expect(page).toHaveTitle('phpPgAdmin - Languages');
  await expect(languagesTable(page).locator('tbody tr td')).toHaveText(['plpgsql', 'Yes', 'plpgsql_call_handler']);
});

test('Languages > the browser tree lists the languages of a database', async ({ page }) => {
  await login(page);

  // The database node is loaded by the legacy database.php, its Languages node by languages.php.
  for (const label of [RUNNING_SERVER_DESC, DATABASE, 'Languages']) {
    await expandBrowserTreeRow(page, label);
  }

  await expect(browserTreeRow(page, 'plpgsql')).toBeVisible();
});
