import { rmSync } from 'node:fs';
import { resolve } from 'node:path';

// Mirrors TestEnvironment::tearDown() in Tests/Support/TestEnvironment.php. It is done here instead of
// spawning `php`, so no command has to be looked up via PATH. The setup runs as part of the web server
// command in playwright.config.ts, because Playwright starts the web server before globalSetup.
export default function globalTeardown(): void {
  // The configuration of the new pages and the one of the legacy pages, never a developer's own.
  for (const configFile of ['config-test.yml', 'config-test.inc.php']) {
    rmSync(resolve('conf', configFile), { force: true });
  }
}
