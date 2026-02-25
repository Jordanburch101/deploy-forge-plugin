import { test, expect } from '@playwright/test';
import {
  APP_URL,
  APP_EMAIL,
  APP_PASSWORD,
  TEST_REPO,
  TEST_BRANCH,
  VERCEL_BYPASS_TOKEN,
} from '../helpers';

test.describe('Site Connection', () => {
  /**
   * The entire setup wizard (initiate → login → register → GitHub →
   * repo/branch → method → redirect back) must run in a single test
   * because Playwright gives each test() a fresh page/context.
   * The cross-origin OAuth flow requires continuous browser state.
   */
  test('complete setup wizard and connect site', async ({ page }) => {
    // ── Step 0: Set Vercel deployment protection bypass cookie ────────
    // The staging Deploy Forge app is behind Vercel deployment protection.
    // Navigate there once with the bypass token to set the cookie before
    // the OAuth redirect happens.

    if (VERCEL_BYPASS_TOKEN) {
      await page.goto(
        `${APP_URL}/login?x-vercel-protection-bypass=${VERCEL_BYPASS_TOKEN}&x-vercel-set-bypass-cookie=samesitenone`
      );
      // Wait briefly for the cookie to be set
      await page.waitForTimeout(2_000);
    }

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

    // ── Step 4: GitHub installations (may be skipped) ─────────────────
    // If the user already has a GitHub App installed, the wizard may skip
    // directly to repository selection. Handle both paths.

    const continueToRepoBtn = page.getByRole('button', {
      name: /continue to repository selection/i,
    });
    try {
      await continueToRepoBtn.waitFor({ state: 'visible', timeout: 10_000 });
      await continueToRepoBtn.click();
    } catch {
      // Already on the repository selection step — continue
    }

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

    // Select branch — the dropdown may show "Select branch" or auto-populate
    // with the default branch (e.g. "main"). Find the button near the Branch label.
    const branchSection = page.locator('text=Branch').locator('..');
    const branchDropdown = branchSection.getByRole('button');
    await expect(branchDropdown).toBeEnabled({ timeout: 10_000 });
    await branchDropdown.click();
    await page
      .getByRole('menuitem', { name: new RegExp(TEST_BRANCH, 'i') })
      .click();

    await page.getByRole('button', { name: /^continue$/i }).click();

    // ── Step 6: Select deployment method and complete ─────────────────
    // Use Direct Clone — simpler and faster than GitHub Actions for E2E.

    const methodDropdown = page.getByRole('button', {
      name: /select method/i,
    });
    try {
      await methodDropdown.waitFor({ state: 'visible', timeout: 10_000 });
      await methodDropdown.click();
      await page.getByRole('menuitem', { name: /direct clone/i }).click();
    } catch {
      // Method already auto-selected — continue
    }

    // Wait for "Complete Setup" and click it
    const completeBtn = page.getByRole('button', { name: /complete setup/i });
    await expect(completeBtn).toBeVisible({ timeout: 10_000 });
    await completeBtn.click();

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
