import { test, expect } from '@playwright/test';
import { WP_URL } from '../helpers';

/**
 * Rollback E2E tests.
 *
 * These tests verify the rollback button visibility rules and the AJAX
 * endpoint behaviour. A full end-to-end rollback (actually restoring files)
 * would require at least two successful deployments so that a non-active
 * deployment with a backup_path exists. With only one deployment from the
 * prior test suite, we focus on UI state and API error handling.
 */
test.describe.serial('Rollback', () => {
  test('rollback button is not visible on active deployment', async ({
    page,
  }) => {
    await page.goto('/wp-admin/admin.php?page=deploy-forge');

    // The active deployment row has the class "deploy-forge-row-active".
    // Active deployments cannot be rolled back — the rollback button must
    // not be rendered for them.
    const activeRow = page.locator('tr.deploy-forge-row-active');
    await expect(activeRow).toBeVisible();

    // Verify no rollback button exists inside the active row.
    const rollbackBtn = activeRow.locator('.rollback-btn');
    await expect(rollbackBtn).toHaveCount(0);
  });

  test('verify rollback AJAX endpoint responds cleanly', async ({ page }) => {
    // Navigate to the deployments page so the localized script data
    // (window.deployForgeAdmin) is available.
    await page.goto('/wp-admin/admin.php?page=deploy-forge');

    // Extract the nonce that WordPress embedded on the page.
    const nonce = await page.evaluate(() => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      return (window as any).deployForgeAdmin?.nonce as string;
    });

    expect(nonce).toBeTruthy();

    // Send a rollback request with an invalid deployment ID.
    // The endpoint should return a clean JSON error (not a 500 or crash).
    const response = await page.request.post(
      `${WP_URL}/wp-admin/admin-ajax.php`,
      {
        form: {
          action: 'deploy_forge_rollback',
          nonce,
          deployment_id: '99999',
        },
      }
    );

    // WordPress AJAX handlers return 200 even for logical errors;
    // the JSON body carries { success: false, data: { ... } }.
    expect(response.ok()).toBe(true);

    const json = await response.json();
    expect(json.success).toBe(false);
  });
});
