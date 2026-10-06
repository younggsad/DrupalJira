import { defineConfig, devices } from '@playwright/test';
import { baseURL, ignoreHTTPSErrors } from './tests/e2e/helpers/environment.mjs';
import { randomUUID } from 'node:crypto';

process.env.E2E_ARTIFACT_ID ||= randomUUID();
if (!/^[a-zA-Z0-9-]{1,100}$/.test(process.env.E2E_ARTIFACT_ID)) throw new Error('Invalid E2E_ARTIFACT_ID');

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: Boolean(process.env.CI),
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  outputDir: `.playwright/results/${process.env.E2E_ARTIFACT_ID}`,
  reporter: [['list'], ['html', { outputFolder: `.playwright/reports/${process.env.E2E_ARTIFACT_ID}`, open: 'never' }], ['./tests/e2e/reporters/failure-html.mjs']],
  use: {
    baseURL,
    ignoreHTTPSErrors,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
