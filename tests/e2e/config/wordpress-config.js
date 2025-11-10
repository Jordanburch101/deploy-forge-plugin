/**
 * WordPress-specific Playwright test configuration
 * Extends Playwright test with WordPress utilities
 */

const { test: base, expect } = require('@playwright/test');

/**
 * Simple admin helper for WordPress navigation
 */
class SimpleAdmin {
  constructor(page) {
    this.page = page;
  }

  async visitAdminPage(path) {
    // Ensure we're logged in first
    await this.ensureLoggedIn();

    // Navigate to admin page
    const url = path.startsWith('http') ? path : `http://localhost:8888/wp-admin/${path}`;
    await this.page.goto(url);
    await this.page.waitForLoadState('networkidle');
  }

  async ensureLoggedIn() {
    // Check if already logged in by looking for admin bar
    const adminBar = await this.page.locator('#wpadminbar').count();
    if (adminBar === 0) {
      await this.login();
    }
  }

  async login() {
    await this.page.goto('http://localhost:8888/wp-login.php');
    await this.page.waitForSelector('#user_login', { timeout: 10000 });
    await this.page.fill('#user_login', 'admin');
    await this.page.fill('#user_pass', 'password');
    await this.page.click('#wp-submit');
    await this.page.waitForURL(/wp-admin/, { timeout: 10000 });
  }
}

/**
 * Extended test with WordPress fixtures
 */
exports.test = base.extend({
  /**
   * Admin utilities for WordPress
   */
  admin: async ({ page }, use) => {
    const admin = new SimpleAdmin(page);
    // Login once at the start
    await admin.login();
    await use(admin);
  },

  /**
   * Authenticated page with admin user logged in
   */
  authenticatedPage: async ({ page }, use) => {
    // Navigate to login page
    await page.goto('http://localhost:8888/wp-login.php');
    await page.waitForSelector('#user_login', { timeout: 10000 });

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
