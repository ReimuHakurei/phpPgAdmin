import { existsSync } from 'node:fs';
import { defineConfig, devices } from '@playwright/test';

// Same .env the PHP side reads (see .env.sample); variables already set in the environment take precedence.
if (existsSync('.env')) {
  process.loadEnvFile('.env');
}

const baseURL = 'http://127.0.0.1:9876';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  testDir: './e2e',
  /* The PHP built-in server handles one request at a time and the tests share one database,
     so the tests run one after another. */
  fullyParallel: false,
  workers: 1,
  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: 'html',
  globalTeardown: './e2e/global-teardown.ts',
  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    baseURL,
    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: 'on-first-retry',
  },

  /* A server-rendered page in iframes gains little from cross-browser runs; Chromium only keeps the suite fast. */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  /* Writes the test configuration (Tests/test-environment.php), then starts the PHP built-in server.
     The setup runs here and not in globalSetup, because Playwright starts the web server first. */
  webServer: {
    command: 'php Tests/test-environment.php setup && php -S 127.0.0.1:9876 > /dev/null 2>&1',
    url: baseURL,
    env: {
      PHPPGADMIN_CONFIG_YAML_FILE: 'config-test.yml',
      PHPPGADMIN_CONFIG_INC_PHP_FILE: 'config-test.inc.php',
    },
    /* Never reuse a running server: it would not have the test configuration. */
    reuseExistingServer: false,
  },
});
