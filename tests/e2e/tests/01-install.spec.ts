import { test, expect } from '@playwright/test';
import { resetDeployForge, PLUGIN_ZIP_PATH } from '../helpers';

test.describe('Plugin Installation', () => {
  test('reset staging environment', async ({ request }) => {
    await resetDeployForge(request);
  });

  test('upload and activate plugin via WordPress admin', async ({ page }) => {
    // Guard: PLUGIN_ZIP_PATH must be set
    test.skip(!PLUGIN_ZIP_PATH, 'PLUGIN_ZIP_PATH not set — skipping upload test');

    // Navigate to Plugins > Add New
    await page.goto('/wp-admin/plugin-install.php');

    // Click "Upload Plugin" button to reveal the upload form
    await page.getByRole('button', { name: 'Upload Plugin' }).click();

    // Wait for file input to be visible
    const fileInput = page.locator('#pluginzip');
    await expect(fileInput).toBeAttached();

    if (PLUGIN_ZIP_PATH.startsWith('http')) {
      // CI: download the ZIP first, then upload via buffer
      const response = await page.request.get(PLUGIN_ZIP_PATH);
      const buffer = await response.body();
      await fileInput.setInputFiles({
        name: 'deploy-forge.zip',
        mimeType: 'application/zip',
        buffer,
      });
    } else {
      // Local: use file path directly
      await fileInput.setInputFiles(PLUGIN_ZIP_PATH);
    }

    // Click "Install Now"
    await page.click('#install-plugin-submit');

    // Wait for installation to complete
    await expect(
      page.getByText('Plugin installed successfully')
    ).toBeVisible({ timeout: 60_000 });

    // Click "Activate Plugin"
    await page.click("a:has-text('Activate Plugin')");

    // Verify plugin is active — Deploy Forge menu should appear in admin sidebar
    await expect(
      page.locator('#adminmenu .wp-menu-name').filter({ hasText: 'Deploy Forge' })
    ).toBeVisible({ timeout: 10_000 });
  });

  test('verify admin pages load without errors', async ({ page }) => {
    const adminPages = [
      { slug: 'deploy-forge', label: 'Deployments' },
      { slug: 'deploy-forge-settings', label: 'Settings' },
      { slug: 'deploy-forge-logs', label: 'Debug Logs' },
    ];

    for (const adminPage of adminPages) {
      await page.goto(`/wp-admin/admin.php?page=${adminPage.slug}`);

      // Verify no PHP fatal or parse errors in page content
      const content = await page.content();
      expect(content).not.toContain('Fatal error');
      expect(content).not.toContain('Parse error');

      // Verify the h1 heading is visible
      await expect(page.locator('h1')).toBeVisible();
    }
  });

  test('deployments page shows welcome banner', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=deploy-forge');

    // Not configured yet — should show the welcome banner
    await expect(
      page.locator('.deploy-forge-welcome-banner')
    ).toBeVisible();

    await expect(
      page.getByText('Welcome to Deploy Forge')
    ).toBeVisible();
  });

  test('settings page shows Connect button', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=deploy-forge-settings');

    // Not connected — should show disconnected connection card
    await expect(
      page.locator('.deploy-forge-connection-card.is-disconnected')
    ).toBeVisible();

    // Should show the Connect button
    await expect(
      page.getByRole('button', { name: 'Connect to Deploy Forge' })
    ).toBeVisible();
  });
});
