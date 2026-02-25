import { test, expect } from '@playwright/test';
import { APP_URL, APP_EMAIL, APP_PASSWORD } from '../helpers';

test.describe.serial('Site Connection', () => {
  test('initiate connection from settings page', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=deploy-forge-settings');

    // Verify the connect button is present
    const connectBtn = page.locator('#connect-btn');
    await expect(connectBtn).toBeVisible();

    // Listen for the AJAX response that returns the redirect URL
    const ajaxResponse = page.waitForResponse(
      (resp) =>
        resp.url().includes('admin-ajax.php') &&
        resp.request().postData()?.includes('deploy_forge_connect') === true,
      { timeout: 30_000 }
    );

    // Click the connect button
    await connectBtn.click();

    // Wait for the AJAX call to complete
    await ajaxResponse;

    // The JS redirects to the Deploy Forge app after receiving the redirect URL.
    // Wait for navigation to the app domain.
    await page.waitForURL(`${APP_URL}/**`, { timeout: 30_000 });
  });

  test('authenticate with Deploy Forge app and select repo', async ({
    page,
  }) => {
    // We should now be on the Deploy Forge app domain.
    // Check if we're on a login/sign-in page and need to authenticate.
    const url = page.url();
    const isLoginPage =
      url.includes('login') ||
      url.includes('sign-in') ||
      url.includes('signin');

    if (isLoginPage) {
      // Fill in email and password
      const emailField =
        page.locator('input[type="email"]').or(
          page.locator('input[name="email"]')
        );
      const passwordField =
        page.locator('input[type="password"]').or(
          page.locator('input[name="password"]')
        );

      await emailField.first().fill(APP_EMAIL);
      await passwordField.first().fill(APP_PASSWORD);

      // Submit the login form
      await page
        .locator('button[type="submit"]')
        .or(page.getByRole('button', { name: /sign in|log in|login/i }))
        .first()
        .click();

      // Wait for login to complete — we should leave the login page
      await page.waitForURL((u) => !u.href.includes('login'), {
        timeout: 15_000,
      });
    }

    // TODO: The exact selectors for the app-side repo selection UI need to be
    // filled in based on the actual staging app. The flow typically involves:
    //
    // 1. Selecting a GitHub repository from a list or dropdown
    //    e.g., await page.locator('.repo-selector').click();
    //         await page.locator('.repo-option:has-text("owner/repo")').click();
    //
    // 2. Choosing a branch (e.g., main, master, develop)
    //    e.g., await page.locator('.branch-picker').selectOption('main');
    //
    // 3. Selecting a deployment method (GitHub Actions or Direct Clone)
    //    e.g., await page.locator('.method-option:has-text("GitHub Actions")').click();
    //
    // 4. Confirming the connection / clicking a "Connect" or "Save" button
    //    e.g., await page.getByRole('button', { name: 'Connect' }).click();
    //
    // After the user completes the flow on the app side, the app redirects
    // back to WordPress with ?action=df_callback&connection_token=...&nonce=...

    // Wait for redirect back to WordPress settings page
    await page.waitForURL(
      '**/wp-admin/admin.php?page=deploy-forge-settings**',
      { timeout: 60_000 }
    );
  });

  test('verify connection is established', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=deploy-forge-settings');

    // Connection card should show connected state
    await expect(
      page.locator('.deploy-forge-connection-card.is-connected')
    ).toBeVisible();

    // Header should show "Connected to Deploy Forge"
    await expect(
      page.getByText('Connected to Deploy Forge')
    ).toBeVisible();

    // Repo info should be displayed (format: "owner/repo" — contains a slash)
    const repoValue = page.locator('.deploy-forge-detail-value').filter({
      hasText: '/',
    });
    await expect(repoValue.first()).toBeVisible();

    // Disconnect button should be visible
    await expect(
      page.getByRole('button', { name: 'Disconnect from Deploy Forge' })
    ).toBeVisible();

    // Deployment Options form section should be visible (only shown when connected)
    await expect(
      page.getByRole('heading', { name: 'Deployment Options' })
    ).toBeVisible();
  });

  test('deployments page shows repo header', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=deploy-forge');

    // Welcome banner should NOT be visible (site is now connected)
    await expect(
      page.locator('.deploy-forge-welcome-banner')
    ).not.toBeVisible();

    // Repo header bar should be visible
    await expect(
      page.locator('.deploy-forge-header-bar')
    ).toBeVisible();

    // Deploy Now button should be visible
    await expect(
      page.getByRole('button', { name: 'Deploy Now' })
    ).toBeVisible();
  });
});
