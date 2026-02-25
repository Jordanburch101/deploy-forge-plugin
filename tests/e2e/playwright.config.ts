import { defineConfig, devices } from '@playwright/test';
import * as path from 'path';
import * as dotenv from 'dotenv';

// Load .env from the e2e directory
dotenv.config({ path: path.resolve(__dirname, '.env') });

export default defineConfig({
  globalSetup: './global-setup.ts',
  testDir: './tests',

  /* Run tests serially — deployment tests are sequential and stateful */
  fullyParallel: false,
  workers: 1,

  /* Fail CI if test.only is left in code */
  forbidOnly: !!process.env.CI,

  /* Sequential tests should not retry mid-chain */
  retries: 0,

  /* 5 minutes per test — deployments are slow */
  timeout: 300_000,

  expect: {
    timeout: 30_000,
  },

  /* Reporter: github + html in CI, html only locally */
  reporter: process.env.CI
    ? [['github'], ['html', { open: 'never' }]]
    : [['html', { open: 'never' }]],

  use: {
    baseURL: process.env.STAGING_WP_URL,
    ignoreHTTPSErrors: true,
    navigationTimeout: 60_000,
    actionTimeout: 15_000,

    /* Capture artifacts for debugging */
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'on-first-retry',
  },

  projects: [
    {
      name: 'setup',
      testMatch: '*.setup.ts',
    },
    {
      name: 'e2e',
      testMatch: '*.spec.ts',
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.auth/wp-admin.json',
      },
      dependencies: ['setup'],
    },
  ],
});
