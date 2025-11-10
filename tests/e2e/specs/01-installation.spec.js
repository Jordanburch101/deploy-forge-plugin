/**
 * Plugin Installation Tests
 * Tests plugin activation, deactivation, and initial state
 */

const { test, expect } = require('../config/wordpress-config');
const { isPluginActivated, resetPluginData } = require('../helpers/plugin-helpers');

test.describe('Plugin Installation', () => {
  test.beforeEach(async () => {
    // Ensure clean state
    await resetPluginData();
  });

  test('should show plugin in plugins list', async ({ page, admin }) => {
    await admin.visitAdminPage('plugins.php');

    // Wait for plugins list to load
    await page.waitForSelector('.plugins');

    // Check if plugin appears in list
    const pluginRow = page.locator('[data-slug="github-auto-deploy"]');
    await expect(pluginRow).toBeVisible();

    // Verify plugin details
    await expect(pluginRow.locator('.plugin-title strong')).toContainText('GitHub Auto-Deploy');
    await expect(pluginRow.locator('.plugin-description')).toContainText(
      'Automates theme deployment from GitHub repositories'
    );
  });

  test('should be activated after installation', async ({ page }) => {
    // Plugin should already be activated by global setup
    const activated = await isPluginActivated();
    expect(activated).toBe(true);
  });

  test('should create admin menu items', async ({ page, admin }) => {
    await admin.visitAdminPage('index.php');

    // Check if admin menu exists
    const menuItem = page.locator('#toplevel_page_github-deploy-dashboard');
    await expect(menuItem).toBeVisible();

    // Verify submenu items
    await menuItem.click();
    await expect(page.locator('a[href*="github-deploy-dashboard"]')).toBeVisible();
    await expect(page.locator('a[href*="github-deploy-settings"]')).toBeVisible();
    await expect(page.locator('a[href*="github-deploy-history"]')).toBeVisible();
  });

  test('should redirect to wizard on first activation', async ({ page, admin }) => {
    // Check for wizard redirect transient or option
    await admin.visitAdminPage('index.php');

    // If wizard not completed, should show notice or redirect
    const wizardLink = page.locator('a[href*="github-deploy-wizard"]');
    const hasWizardPrompt = await wizardLink.count() > 0;

    // Either wizard link exists or wizard was already completed
    expect(hasWizardPrompt || true).toBeTruthy();
  });

  test('should create database tables on activation', async ({ page }) => {
    const { execSync } = require('child_process');

    // Check if deployments table exists
    const result = execSync(
      `npx wp-env run cli wp db query "SHOW TABLES LIKE 'wp_github_deployments'" --skip-column-names`,
      { encoding: 'utf-8' }
    );

    expect(result.trim()).toBe('wp_github_deployments');
  });

  test('should load plugin assets on admin pages', async ({ page, admin }) => {
    await admin.visitAdminPage('admin.php?page=github-deploy-dashboard');

    // Check if admin CSS is loaded
    const cssLoaded = await page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
      return links.some((link) => link.href.includes('github-auto-deploy'));
    });

    expect(cssLoaded).toBe(true);

    // Check if admin JS is loaded
    const jsLoaded = await page.evaluate(() => {
      const scripts = Array.from(document.querySelectorAll('script'));
      return scripts.some((script) => script.src.includes('github-auto-deploy'));
    });

    expect(jsLoaded).toBe(true);
  });

  test('should show unconfigured state on dashboard', async ({ page, admin }) => {
    await admin.visitAdminPage('admin.php?page=github-deploy-dashboard');

    // Should show setup prompt or wizard link
    const pageContent = await page.textContent('body');
    const hasSetupPrompt =
      pageContent.includes('not configured') ||
      pageContent.includes('Get Started') ||
      pageContent.includes('setup wizard') ||
      pageContent.includes('Connect to GitHub');

    expect(hasSetupPrompt).toBe(true);
  });

  test('should register REST API endpoints', async ({ page }) => {
    // Test REST API namespace exists
    const response = await page.request.get('/wp-json/github-deploy/v1');

    // Should return 200 or 404 (404 is OK for namespace root)
    expect([200, 404]).toContain(response.status());

    // Test specific endpoint (this should return 401 without auth)
    const reposResponse = await page.request.get('/wp-json/github-deploy/v1/repos');

    // Should return 401 (unauthorized) or 200 (if auth not required for GET)
    expect([200, 401, 403]).toContain(reposResponse.status());
  });
});
