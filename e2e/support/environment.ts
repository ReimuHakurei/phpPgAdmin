import { createHash } from 'node:crypto';
import { expect, type FrameLocator, type Page } from '@playwright/test';

// Mirrors Tests/Support/TestEnvironment.php, which writes the server configuration the app runs with.
export const NOT_RUNNING_SERVER_DESC = 'Not Running Server';
export const NOT_RUNNING_SERVER_HOST = '127.0.0.1';
export const NOT_RUNNING_SERVER_PORT = 1;
export const RUNNING_SERVER_DESC = 'Running Server';
export const RUNNING_SERVER_PORT = 5432;
const SERVER_SSL_MODE = 'allow';

export const LOGIN_FORM_SELECTOR = 'form[name="login_form"]';

export function runningServerHost(): string {
  return process.env.PHPPGADMIN_TEST_SERVER_HOSTNAME || '127.0.0.1';
}

/** The `server` request parameter of the running server: `host:port:sslmode`. */
export function runningServerId(): string {
  return `${runningServerHost()}:${RUNNING_SERVER_PORT}:${SERVER_SSL_MODE}`;
}

export function testUsername(): string {
  return process.env.PHPPGADMIN_TEST_SERVER_USERNAME || 'postgres';
}

export function testPassword(): string {
  return process.env.PHPPGADMIN_TEST_SERVER_PASSWORD ?? '';
}

export function browserFrame(page: Page): FrameLocator {
  return page.locator('iframe#browser').contentFrame();
}

export function detailFrame(page: Page): FrameLocator {
  return page.locator('iframe#detail').contentFrame();
}

/** The server entry in the browser tree; its tooltip is the server id `host:port:sslmode`. */
export function serverTreeLink(page: Page, host: string, port: number) {
  return browserFrame(page).locator(`a[title="${host}:${port}:${SERVER_SSL_MODE}"]`);
}

/** The row of a browser tree node, found by its label. */
export function browserTreeRow(page: Page, label: string) {
  const browser = browserFrame(page);

  return browser.locator('div.webfx-tree-row', {
    has: browser.locator('.webfx-tree-item-label').getByText(label, { exact: true }),
  });
}

/**
 * Opens a browser tree node, found by its label. The tree opens the only child of a node by itself
 * (see xloadtree/xloadtree2.js), e.g. the only database of a server, so a click would close it again.
 */
export async function expandBrowserTreeRow(page: Page, label: string): Promise<void> {
  const expandIcon = browserTreeRow(page, label).locator('img.webfx-tree-expand-icon');

  // The icon shows a plus while the node is closed and a minus while it is open. The tree opens the only child in
  // the same step in which it adds it, so the icon is final once the row exists.
  if (/plus\.png$/.test((await expandIcon.getAttribute('src')) ?? '')) {
    await expandIcon.click();
  }

  await expect(expandIcon).toHaveAttribute('src', /minus\.png$/);
}

/** The icon of a server in the browser tree, which shows whether one is logged in to it. */
export function serverTreeIcon(page: Page, serverDesc: string) {
  return browserTreeRow(page, serverDesc).locator('img.webfx-tree-icon');
}

export const LOGGED_IN_SERVER_ICON = /\/Server\.png$/;

/** The password field name contains a hash of the server description. */
export function passwordFieldName(serverDesc: string): string {
  return `loginPassword_${createHash('sha256').update(serverDesc).digest('hex')}`;
}

/** Logs in with the login form of the running server, which a page or a frame shows. */
export async function submitLoginForm(
  pageOrFrame: Page | FrameLocator,
  username = testUsername(),
  password = testPassword(),
): Promise<void> {
  const loginForm = pageOrFrame.locator(LOGIN_FORM_SELECTOR);
  await loginForm.locator('input[name="loginUsername"]').fill(username);
  await loginForm.locator(`input[name="${passwordFieldName(RUNNING_SERVER_DESC)}"]`).fill(password);
  await loginForm.locator('#loginSubmit').click();
}

export async function login(page: Page, username = testUsername(), password = testPassword()): Promise<FrameLocator> {
  await page.goto('/');
  await serverTreeLink(page, runningServerHost(), RUNNING_SERVER_PORT).click();

  const detail = detailFrame(page);
  await submitLoginForm(detail, username, password);

  await expect(detail.getByText(`You are logged in as user "${username}"`)).toBeVisible();
  // The login reloads the browser tree. Waiting for it keeps the tests from using the tree from before the login.
  await expect(serverTreeIcon(page, RUNNING_SERVER_DESC)).toHaveAttribute('src', LOGGED_IN_SERVER_ICON);

  return detail;
}
