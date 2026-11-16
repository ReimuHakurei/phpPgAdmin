import { createHash } from 'node:crypto';
import { test, expect } from '@playwright/test';
import {
  LOGGED_IN_SERVER_ICON,
  LOGIN_FORM_SELECTOR,
  NOT_RUNNING_SERVER_DESC,
  NOT_RUNNING_SERVER_HOST,
  NOT_RUNNING_SERVER_PORT,
  RUNNING_SERVER_DESC,
  browserTreeRow,
  detailFrame,
  expandBrowserTreeRow,
  login,
  passwordFieldName,
  runningServerId,
  serverTreeIcon,
  serverTreeLink,
  submitLoginForm,
  testPassword,
  testUsername,
} from './support/environment';

const DATABASE = 'postgres';

function databaseParams(serverId = runningServerId()): string {
  return `${new URLSearchParams({ server: serverId, database: DATABASE, subject: 'database' })}`;
}

test('Login > fails for a server that is not running', async ({ page }) => {
  await page.goto('/');
  await serverTreeLink(page, NOT_RUNNING_SERVER_HOST, NOT_RUNNING_SERVER_PORT).click();

  const loginForm = detailFrame(page).locator(LOGIN_FORM_SELECTOR);
  const usernameField = loginForm.locator('input[name="loginUsername"]');
  const passwordField = loginForm.locator(`input[name="${passwordFieldName(NOT_RUNNING_SERVER_DESC)}"]`);
  await expect(usernameField).toHaveValue('');
  await expect(passwordField).toHaveValue('');

  await usernameField.fill('postgres');
  await passwordField.fill('wrongpassword');
  await loginForm.locator('#loginSubmit').click();

  await expect(detailFrame(page).getByText('Login failed')).toBeVisible();
});

test('Login > succeeds for the running server', async ({ page }) => {
  await login(page);
});

test('Login > reloads the browser tree, which then lists the databases of the server', async ({ page }) => {
  await page.goto('/');
  await expect(serverTreeIcon(page, RUNNING_SERVER_DESC)).toHaveAttribute('src', /\/DisconnectedServer\.png$/);

  await login(page);

  // Without clicking "Refresh" in the browser tree.
  await expect(serverTreeIcon(page, RUNNING_SERVER_DESC)).toHaveAttribute('src', LOGGED_IN_SERVER_ICON);
  await expandBrowserTreeRow(page, RUNNING_SERVER_DESC);
  await expect(browserTreeRow(page, 'postgres')).toBeVisible();
});

test('Login > leads back to the page that led to the login form', async ({ page }) => {
  await page.goto(`/languages.php?${databaseParams()}`);
  await expect(page).toHaveURL(/\/login\.php\?/);

  await submitLoginForm(page);

  await expect(page).toHaveURL(`/languages.php?${databaseParams()}`);
  await expect(page).toHaveTitle('phpPgAdmin - Languages');
});

test('Login > leads back to the legacy page that showed the login form', async ({ page }) => {
  // The legacy pages show the login form in place, under their own URL (see libraries/lib.inc.php).
  await page.goto(`/schemas.php?${databaseParams()}`);
  await expect(page.locator('h2')).toHaveText(`Login to ${RUNNING_SERVER_DESC}`);

  await submitLoginForm(page);

  await expect(page).toHaveURL(`/schemas.php?${databaseParams()}`);
  await expect(page).toHaveTitle('phpPgAdmin - Schemas');
  await expect(page.getByRole('link', { name: 'public', exact: true })).toBeVisible();
});

test('Login > is not possible with the fields of the legacy login form', async ({ page }) => {
  // The legacy login form named the password field after the md5 hash of the server id. libraries/lib.inc.php took a
  // login from these fields on every legacy page, without the checks of login.php (see Login::handlePostRequest()).
  await page.request.post(`/schemas.php?${databaseParams()}`, {
    form: {
      loginServer: runningServerId(),
      loginUsername: testUsername(),
      [`loginPassword_${createHash('md5').update(runningServerId()).digest('hex')}`]: testPassword(),
    },
  });

  await page.goto(`/schemas.php?${databaseParams()}`);

  await expect(page.locator('h2')).toHaveText(`Login to ${RUNNING_SERVER_DESC}`);
});

test('Login > after it expired, a form of a legacy page leads to the login form', async ({ page }) => {
  await login(page);
  await page.goto(`/schemas.php?${databaseParams()}&action=create`);
  await page.locator('input[name="formName"]').fill('after_the_login_expired');
  // Like an expired login: the session is gone.
  await page.context().clearCookies();

  await page.locator('input[name="create"]').click();

  // In place of the form, like for a page that is opened without a login.
  await expect(page.locator('h2')).toHaveText(`Login to ${RUNNING_SERVER_DESC}`);
  await submitLoginForm(page);
  // The login does not send the form again, so it leads to the databases of the server.
  await expect(page).toHaveURL(`/all_db.php?${new URLSearchParams({ server: runningServerId() })}`);
});

test('Login > from a link to a database leads to the database', async ({ page }) => {
  // Like the databases in the browser tree, which redirect.php leads to their last tab, the schemas at first.
  await page.goto(`/redirect.php?${databaseParams()}`);
  await expect(page.locator('h2')).toHaveText(`Login to ${RUNNING_SERVER_DESC}`);

  await submitLoginForm(page);

  await expect(page).toHaveTitle('phpPgAdmin - Schemas');
  await expect(page.getByRole('link', { name: 'public', exact: true })).toBeVisible();
});

test('Login > keeps the page to lead back to after a failed login', async ({ page }) => {
  const notRunningServerId = `${NOT_RUNNING_SERVER_HOST}:${NOT_RUNNING_SERVER_PORT}:allow`;
  await page.goto(`/languages.php?${databaseParams(notRunningServerId)}`);

  const loginForm = page.locator(LOGIN_FORM_SELECTOR);
  await loginForm.locator('input[name="loginUsername"]').fill('postgres');
  await loginForm.locator(`input[name="${passwordFieldName(NOT_RUNNING_SERVER_DESC)}"]`).fill('wrongpassword');
  await loginForm.locator('#loginSubmit').click();

  await expect(page.getByText('Login failed')).toBeVisible();
  await expect(loginForm.locator('input[name="loginReturnUrl"]')).toHaveValue(
    `./languages.php?${databaseParams(notRunningServerId)}`,
  );
});

test('Login > does not lead to another site', async ({ page }) => {
  const params = new URLSearchParams({ server: runningServerId(), subject: 'server', loginReturnUrl: 'https://example.com/' });
  await page.goto(`/login.php?${params}`);

  await submitLoginForm(page);

  // Instead to the databases of the server, like a login from the browser tree.
  await expect(page).toHaveURL(`/all_db.php?${new URLSearchParams({ server: runningServerId() })}`);
});
