import { test, expect } from '@playwright/test';
import { RUNNING_SERVER_DESC, runningServerId } from './support/environment';

// The pages of a server, which are only shown while one is logged in to it. Like the legacy pages, which need a login
// unless they set $_no_db_connection (see libraries/lib.inc.php).
const SERVER_PAGES = [
  'all_db.php',
  'all_db_export.php',
  'alter_db.php',
  'alter_role.php',
  'alter_tablespace.php',
  'create_db.php',
  'create_role.php',
  'create_tablespace.php',
  'dbexport.php',
  'drop_db.php',
  'drop_role.php',
  'drop_tablespace.php',
  'history.php',
  'history_clear.php',
  'history_delete.php',
  'history_download.php',
  'languages.php',
  'roles.php',
  'sqledit.php',
  'tablespaces.php',
];

function searchParams(url: string): Record<string, string> {
  return Object.fromEntries(new URL(url).searchParams);
}

for (const path of SERVER_PAGES) {
  test(`Server Login > ${path} without a login leads to the login form of the server`, async ({ page }) => {
    await page.goto(`/${path}?${new URLSearchParams({ server: runningServerId() })}`);

    // The legacy pages show the login form in place, under their own URL.
    await expect(page).toHaveURL(/\/login\.php\?/);
    // After the login, it leads back to the page.
    expect(searchParams(page.url())).toEqual({
      server: runningServerId(),
      subject: 'server',
      loginReturnUrl: `./${path}?${new URLSearchParams({ server: runningServerId() })}`,
    });
    await expect(page.locator('h2')).toHaveText(`Login to ${RUNNING_SERVER_DESC}`);
  });

  test(`Server Login > ${path} without a server parameter is rejected`, async ({ page }) => {
    // Without a login: there is no server to log in to.
    const response = await page.goto(`/${path}`);

    expect(response?.status()).toBe(400);
    await expect(page.locator('h1')).toHaveText('400: Missing required parameter: server');
  });
}
