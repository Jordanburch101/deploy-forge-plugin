import { test, expect } from '@playwright/test';
import {
  APP_URL,
  APP_EMAIL,
  APP_PASSWORD,
  TEST_REPO,
  TEST_BRANCH,
} from '../helpers';

test.describe.serial('Site Connection', () => {
  /**
   * The entire setup wizard (initiate → login → register → GitHub →
   * repo/branch → method → redirect back) must run in a single test
   * because Playwright gives each test() a fresh page/context.
   * The cross-origin OAuth flow requires continuous browser state.
   */
  test('complete setup wizard and connect site', async ({ page }) => {
    // ── Step 1: Initiate connection from WordPress ────────────────────

    await page.goto('/wp-admin/admin.php?page=deploy-forge-settings');

    const connectBtn = page.locator('#connect-btn');
    await expect(connectBtn).toBeVisible();

    // Listen for the AJAX response that returns the redirect URL
    const ajaxResponse = page.waitForResponse(
      (resp) =>
        resp.url().includes('admin-ajax.php') &&
        resp.request().postData()?.includes('deploy_forge_connect') === true,
      { timeout: 30_000 }
    );

    await connectBtn.click();
    await ajaxResponse;

    // Wait for redirect to the Deploy Forge app
    await page.waitForURL(`${APP_URL}/**`, { timeout: 30_000 });

    // ── Step 2: Authenticate with Deploy Forge app ────────────────────

    if (page.url().includes('/login')) {
      await page.getByPlaceholder('you@example.com').fill(APP_EMAIL);
      await page.getByPlaceholder('Enter your password').fill(APP_PASSWORD);
      await page.getByRole('button', { name: /sign in/i }).click();

      // Wait for redirect away from login
      await page.waitForURL((url) => !url.href.includes('/login'), {
        timeout: 15_000,
      });
    }

    // Should now be on /site-setup
    expect(page.url()).toContain('/site-setup');

    // ── Step 3: Register site ─────────────────────────────────────────

    await expect(
      page.getByRole('heading', { name: /register your site/i })
    ).toBeVisible({ timeout: 15_000 });

    // Select workspace if the dropdown is showing
    const workspaceBtn = page.getByRole('button', {
      name: /select workspace/i,
    });
    if (await workspaceBtn.isVisible().catch(() => false)) {
      await workspaceBtn.click();
      await page.getByRole('menuitem').first().click();
    }

    await page.getByRole('button', { name: /register.*continue/i }).click();

    // Wait for registration to complete
    await expect(
      page.getByText(/site registered|github/i)
    ).toBeVisible({ timeout: 15_000 });

    // ── Step 4: GitHub installations ──────────────────────────────────

    const continueToRepoBtn = page.getByRole('button', {
      name: /continue to repository selection/i,
    });
    await expect(continueToRepoBtn).toBeVisible({ timeout: 15_000 });
    await continueToRepoBtn.click();

    // ── Step 5: Select repository and branch ──────────────────────────

    const repoName = TEST_REPO.split('/').pop() ?? TEST_REPO;

    // Select GitHub account if dropdown is showing
    const accountDropdown = page.getByRole('button', {
      name: /select account/i,
    });
    if (await accountDropdown.isVisible().catch(() => false)) {
      await accountDropdown.click();
      await page.getByRole('menuitem').first().click();
    }

    // Select repository
    const repoDropdown = page.getByRole('button', {
      name: /select repository/i,
    });
    await expect(repoDropdown).toBeEnabled({ timeout: 10_000 });
    await repoDropdown.click();

    const repoSearch = page.getByPlaceholder('Search repositories...');
    if (await repoSearch.isVisible().catch(() => false)) {
      await repoSearch.fill(repoName);
    }

    await page
      .getByRole('menuitem', { name: new RegExp(repoName, 'i') })
      .click();

    // Select branch
    const branchDropdown = page.getByRole('button', {
      name: /select branch/i,
    });
    await expect(branchDropdown).toBeEnabled({ timeout: 10_000 });
    await branchDropdown.click();
    await page
      .getByRole('menuitem', { name: new RegExp(TEST_BRANCH, 'i') })
      .click();

    await page.getByRole('button', { name: /^continue$/i }).click();

    // ── Step 6: Select deployment method and complete ─────────────────

    const methodDropdown = page.getByRole('button', {
      name: /select method/i,
    });
    await expect(methodDropdown).toBeVisible({ timeout: 10_000 });
    await methodDropdown.click();
    await page.getByRole('menuitem', { name: /github actions/i }).click();

    // Select workflow (first available)
    const workflowDropdown = page.getByRole('button', {
      name: /select workflow/i,
    });
    await expect(workflowDropdown).toBeEnabled({ timeout: 10_000 });
    await workflowDropdown.click();
    await page.getByRole('menuitem').first().click();

    // Complete setup — redirects back to WordPress
    await page.getByRole('button', { name: /complete setup/i }).click();

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
    ).toBeVisible({ timeout: 15_000 });

    await expect(
      page.getByText('Connected to Deploy Forge')
    ).toBeVisible();

    // Repo info should be displayed (format: "owner/repo")
    const repoValue = page.locator('.deploy-forge-detail-value').filter({
      hasText: '/',
    });
    await expect(repoValue.first()).toBeVisible();

    // Disconnect button should be visible
    await expect(
      page.getByRole('button', { name: 'Disconnect from Deploy Forge' })
    ).toBeVisible();

    // Deployment Options form section should be visible
    await expect(
      page.getByRole('heading', { name: 'Deployment Options' })
    ).toBeVisible();
  });

  test('deployments page shows repo header', async ({ page }) => {
    await page.goto('/wp-admin/admin.php?page=deploy-forge');

    // Welcome banner should NOT be visible
    await expect(
      page.locator('.deploy-forge-welcome-banner')
    ).not.toBeVisible();

    // Repo header bar should be visible
    await expect(page.locator('.deploy-forge-header-bar')).toBeVisible();

    // Deploy Now button should be visible
    await expect(
      page.getByRole('button', { name: 'Deploy Now' })
    ).toBeVisible();
  });
});
