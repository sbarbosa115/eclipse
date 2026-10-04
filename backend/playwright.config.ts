import {defineConfig, devices} from '@playwright/test';

/**
 * The smoke suite: the simple cases of docs/tests/ui-regression.md (a view opens, a create/edit/remove, an access
 * rule, a form's messages), run against this checkout's Docker stack before anything is checked by hand.
 *
 *   backend/e2e/smoke.sh            resets the stack to the demo seed and runs it (from the repository root)
 *   backend/e2e/smoke.sh access     one spec file
 *
 * One worker, in file order: the specs share one database, and each test signs up a company of its own
 * (support/test.ts) so they never undo each other.
 */
export default defineConfig({
  testDir: './e2e',
  outputDir: './e2e/.results/artifacts',
  globalSetup: './e2e/support/globalSetup.ts',
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  timeout: 30_000,
  expect: {timeout: 7_000},
  reporter: [
    ['list'],
    ['json', {outputFile: './e2e/.results/report.json'}],
    ['html', {outputFolder: './e2e/.results/html', open: 'never'}],
  ],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8090',
    locale: 'es-CO',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      // Mustang is used on laptops (and tablets, checked by hand).
      name: 'laptop',
      use: {...devices['Desktop Chrome'], viewport: {width: 1366, height: 768}},
    },
  ],
});
