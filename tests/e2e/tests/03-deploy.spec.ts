import { test, expect } from '@playwright/test';
import { waitForDeploymentStatus } from '../helpers';

const DEPLOYMENTS_PAGE = '/wp-admin/admin.php?page=deploy-forge';

test.describe.serial('Manual deployment', () => {
  test('trigger a manual deployment', async ({ page }) => {
    await page.goto(DEPLOYMENTS_PAGE);

    // The "Deploy Now" button must be present
    const deployBtn = page.locator('#deploy-now-btn');
    await expect(deployBtn).toBeVisible();

    // Register a dialog handler BEFORE clicking.
    // The first dialog is a confirm() asking to proceed with the deployment.
    // After the AJAX succeeds, the JS fires alert() with a success message,
    // then calls location.reload(). We accept both dialogs.
    page.on('dialog', async (dialog) => {
      console.log(
        `[03-deploy] Dialog type="${dialog.type()}" message="${dialog.message()}"`
      );
      await dialog.accept();
    });

    // Listen for the AJAX response before clicking
    const ajaxResponsePromise = page.waitForResponse(
      (resp) =>
        resp.url().includes('admin-ajax.php') &&
        resp.request().method() === 'POST' &&
        resp.status() === 200
    );

    // Click Deploy Now
    await deployBtn.click();

    // Wait for the AJAX response
    const ajaxResponse = await ajaxResponsePromise;
    const responseBody = await ajaxResponse.json();

    console.log(
      '[03-deploy] AJAX response:',
      JSON.stringify(responseBody)
    );

    // Verify the AJAX response indicates success
    expect(responseBody.success).toBe(true);
    expect(responseBody.data.deployment_id).toBeTruthy();
  });

  test('deployment appears in history with building status', async ({
    page,
  }) => {
    await page.goto(DEPLOYMENTS_PAGE);

    // Wait for the deployments table to have at least one row
    const firstRow = page.locator(
      'table#deployments-table tbody tr:first-child'
    );
    await expect(firstRow).toBeVisible();

    // Get the status text from the first row
    const statusBadge = firstRow.locator('.deployment-status');
    await expect(statusBadge).toBeVisible();

    const statusText = await statusBadge.textContent();
    const normalizedStatus = (statusText ?? '').trim().toLowerCase();

    console.log(
      `[03-deploy] First deployment status: "${normalizedStatus}"`
    );

    // Depending on timing, the status could be any of these
    expect(['building', 'pending', 'success']).toContain(normalizedStatus);
  });

  test('wait for deployment to succeed', async ({ page }) => {
    test.setTimeout(600_000); // 10 minutes

    await page.goto(DEPLOYMENTS_PAGE);

    // Poll until the first deployment reaches "success" (up to 8 minutes)
    await waitForDeploymentStatus(page, 'success', 480_000);

    // After success, the active badge should be visible
    const activeBadge = page.locator(
      'table#deployments-table tbody tr:first-child .deployment-active-badge'
    );
    await expect(activeBadge).toBeVisible();

    const badgeText = await activeBadge.textContent();
    expect((badgeText ?? '').trim().toLowerCase()).toBe('active');
  });

  test('deployment details modal works', async ({ page }) => {
    await page.goto(DEPLOYMENTS_PAGE);

    // Click the "Details" button on the first deployment row
    const detailsBtn = page.locator(
      'table#deployments-table tbody tr:first-child .view-details-btn'
    );
    await expect(detailsBtn).toBeVisible();
    await detailsBtn.click();

    // Verify the modal becomes visible
    const modal = page.locator('#deployment-details-modal');
    await expect(modal).toBeVisible();

    // Wait for the modal content to finish loading (should not say "Loading...")
    const modalContent = page.locator('#deployment-details-content');
    await expect(modalContent).not.toHaveText('Loading...');

    // Verify the modal has some actual content (e.g. a table with deployment info)
    await expect(modalContent.locator('table')).toBeVisible();

    // Close the modal by clicking the close button
    const closeBtn = modal.locator('.deploy-forge-modal-close');
    await closeBtn.first().click();

    // Verify the modal is hidden
    await expect(modal).toBeHidden();
  });
});
