/**
 * WordPress-specific Playwright test configuration
 * Extends Playwright test with WordPress utilities
 */

const { test: base, expect } = require('@playwright/test');
const { Admin, RequestUtils } = require('@wordpress/e2e-test-utils-playwright');

/**
 * Extended test with WordPress fixtures
 */
exports.test = base.extend({
  /**
   * Admin utilities for WordPress
   * Provides helper methods for navigating WordPress admin
   */
  admin: async ({ page, request }, use) => {
    const admin = new Admin({ page, request });
    await use(admin);
  },

  /**
   * Request utilities for WordPress REST API
   * Provides helper methods for making authenticated requests
   */
  requestUtils: async ({ request }, use) => {
    const requestUtils = await RequestUtils.setup({
      request,
      baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
      user: {
        username: 'admin',
        password: 'password',
      },
    });
    await use(requestUtils);
  },

  /**
   * Authenticated page with admin user logged in
   * Automatically logs in before each test
   */
  authenticatedPage: async ({ page, admin }, use) => {
    // Navigate to login page
    await page.goto('/wp-login.php');

    // Fill in credentials
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');

    // Submit login form
    await page.click('#wp-submit');

    // Wait for redirect to admin dashboard
    await page.waitForURL(/wp-admin/, { timeout: 10000 });

    await use(page);
  },
});

exports.expect = expect;
