import { test, expect, type Page } from '@playwright/test';
import { login, runningServerId } from './support/environment';

const DATABASE = 'postgres';

/**
 * New pages with the top bar of WebsiteComponents::buildTopBar(): one about the server and one about a database.
 * all_db.php had a script of its own for the windows of the links (js/all_db.js), languages.php none.
 */
const PAGES: Record<string, () => Record<string, string>> = {
  'all_db.php': () => ({ server: runningServerId() }),
  'languages.php': () => ({ database: DATABASE, server: runningServerId(), subject: 'database' }),
};

/** The window each link opens in, like in the legacy Misc::printTopbar(): Find shares the window of SQL. */
const WINDOWS: Record<string, string> = { find: 'sqledit', history: 'history', sql: 'sqledit' };

async function openPage(page: Page, path: string): Promise<void> {
  await login(page);
  await page.goto(`/${path}?${new URLSearchParams(PAGES[path]())}`);
}

async function clickForPopup(page: Page, link: string): Promise<Page> {
  const popup = page.waitForEvent('popup');
  await page.locator(`#toplink_${link}`).click();

  return popup;
}

for (const path of Object.keys(PAGES)) {
  for (const [link, windowName] of Object.entries(WINDOWS)) {
    test(`Top bar > on ${path} the ${link} link opens in a window of its own`, async ({ page }) => {
      await openPage(page, path);
      const pageUrl = page.url();
      const linkUrl = await page.locator(`#toplink_${link}`).evaluate((a) => (a as HTMLAnchorElement).href);

      const popup = await clickForPopup(page, link);

      await expect(popup).toHaveURL(linkUrl);
      // Named after the server, so that the links of another server open in another window.
      expect(await popup.evaluate(() => window.name)).toBe(`${windowName}:${runningServerId()}`);
      expect(await popup.evaluate(() => [window.innerWidth, window.innerHeight])).toEqual([700, 500]);
      expect(page.url()).toBe(pageUrl);
    });
  }
}

test('Top bar > the find link opens in the window of the sql link', async ({ page }) => {
  await openPage(page, 'languages.php');
  const popup = await clickForPopup(page, 'sql');
  await expect(popup).toHaveURL(/[?&]action=sql(&|$)/);

  await page.locator('#toplink_find').click();

  await expect(popup).toHaveURL(/[?&]action=find(&|$)/);
  expect(page.context().pages()).toHaveLength(2);
});

test('Top bar > in the detail frame the sql link opens in a window of its own', async ({ page }) => {
  // The login leads the detail frame to all_db.php.
  const detail = await login(page);

  const popup = page.waitForEvent('popup');
  await detail.locator('#toplink_sql').click();

  expect(await (await popup).evaluate(() => window.name)).toBe(`sqledit:${runningServerId()}`);
});
