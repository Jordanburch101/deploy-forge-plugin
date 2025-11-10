/**
 * Quick diagnostic test to verify WordPress admin access
 */

const { test, expect } = require('@playwright/test');

test.describe('WordPress Admin Access Test', () => {
  test('should be able to access WordPress admin', async ({ page }) => {
    // Navigate to WordPress login
    await page.goto('http://localhost:8888/wp-login.php');

    // Fill in login credentials
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');

    // Click login button
    await page.click('#wp-submit');

    // Wait for redirect to admin
    await page.waitForURL(/wp-admin/, { timeout: 10000 });

    // Verify we're logged in
    await expect(page.locator('#wpadminbar')).toBeVisible();

    console.log('✅ Successfully logged into WordPress admin');
  });

  test('should see GitHub Deploy plugin menu', async ({ page }) => {
    // Login first
    await page.goto('http://localhost:8888/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForURL(/wp-admin/, { timeout: 10000 });

    // Look for plugin menu
    const menuExists = await page.locator('#toplevel_page_github-deploy-dashboard, text=GitHub Deploy').count() > 0;

    console.log('Plugin menu exists:', menuExists);
  });
});
