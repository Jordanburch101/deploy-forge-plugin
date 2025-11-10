const { defineConfig, devices } = require('@playwright/test');

/**
 * Playwright configuration for WordPress E2E tests
 *
 * @see https://playwright.dev/docs/test-configuration
 */
module.exports = defineConfig({
  testDir: './tests/e2e/specs',

  // Run tests sequentially (WordPress doesn't handle parallel well)
  fullyParallel: false,

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Single worker for WordPress (database state management)
  workers: 1,

  // Reporter configuration
  reporter: [
    ['html', { outputFolder: 'playwright-report' }],
    ['junit', { outputFile: 'test-results/junit.xml' }],
    ['list'],
    // Add GitHub Actions reporter when running in CI
    ...(process.env.CI ? [['github']] : []),
  ],

  // Shared settings for all tests
  use: {
    // Base URL for WordPress installation
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',

    // Collect trace on first retry
    trace: 'on-first-retry',

    // Screenshot on failure
    screenshot: 'only-on-failure',

    // Video on failure
    video: 'retain-on-failure',

    // Viewport size
    viewport: { width: 1280, height: 720 },

    // Timeout for each action (e.g., click, fill)
    actionTimeout: 10000,

    // Ignore HTTPS errors (for local development)
    ignoreHTTPSErrors: true,
  },

  // Test timeout (2 minutes per test)
  timeout: 120000,

  // Expect timeout for assertions
  expect: {
    timeout: 10000,
  },

  // Configure projects for different browsers
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        // WordPress admin requires cookies
        storageState: undefined,
      },
    },

    // Uncomment to test on Firefox (optional)
    // {
    //   name: 'firefox',
    //   use: { ...devices['Desktop Firefox'] },
    // },

    // Uncomment to test on WebKit/Safari (optional)
    // {
    //   name: 'webkit',
    //   use: { ...devices['Desktop Safari'] },
    // },
  ],

  // Run local dev server before starting the tests
  webServer: {
    command: 'npm run wp-env:start',
    url: 'http://localhost:8888/wp-admin',
    timeout: 120000,
    reuseExistingServer: true,
    stdout: 'ignore',
    stderr: 'pipe',
  },

  // Global setup and teardown
  globalSetup: './tests/e2e/config/global-setup.js',
  globalTeardown: './tests/e2e/config/global-teardown.js',
});
