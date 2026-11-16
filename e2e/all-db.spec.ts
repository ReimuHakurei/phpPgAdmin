import { test, expect } from '@playwright/test';
import { login, runningServerId } from './support/environment';

test('All Databases > tabs after login', async ({ page }) => {
  const detail = await login(page);

  const tabs = detail.locator('table.tabs tbody tr td');
  await expect(tabs).toHaveCount(4);
  await expect(tabs.locator('span.label')).toHaveText(['Databases', 'Roles', 'Tablespaces', 'Export']);

  await expect(tabs.nth(0)).toHaveClass('tab active');
  await expect(tabs.nth(1)).toHaveClass('tab');
  await expect(tabs.nth(2)).toHaveClass('tab');
  await expect(tabs.nth(3)).toHaveClass('tab');
});

test('All Databases > tabs link to the matching help pages', async ({ page }) => {
  const detail = await login(page);

  const tabs = detail.locator('table.tabs tbody tr td');
  const helpPages = await tabs.evaluateAll((cells) =>
    cells.map((cell) => {
      const href = cell.querySelector('a.help')?.getAttribute('href');

      return href ? new URL(href, 'http://localhost/').searchParams.get('help') : null;
    }),
  );

  expect(helpPages).toEqual(['pg.database', 'pg.role', 'pg.tablespace', null]);
});

// Both tabs show the databases of the server under the same title, like the legacy all_db.php.
for (const path of ['all_db.php', 'all_db_export.php']) {
  test(`All Databases > ${path} has the title in the language of the user`, async ({ page }) => {
    await login(page);

    // Not English, which the texts are without a translation, nor German: gettext on macOS translates to the
    // language of the system as long as no locale is set.
    await page.goto(`/${path}?${new URLSearchParams({ server: runningServerId(), language: 'french' })}`);

    await expect(page.locator('table.tabs span.label').first()).toHaveText('Bases de données');
    await expect(page).toHaveTitle('phpPgAdmin - Bases de données');
  });
}
