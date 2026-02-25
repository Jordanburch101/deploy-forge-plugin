import { test, expect } from '@playwright/test';
import {
  APP_URL,
  APP_EMAIL,
  APP_PASSWORD,
  TEST_REPO,
  TEST_BRANCH,
} from '../helpers';

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
    // The app redirects to /site-setup?site_url=...&nonce=... or /login?redirect=...
    await page.waitForURL(`${APP_URL}/**`, { timeout: 30_000 });
  });

  test('authenticate with Deploy Forge app', async ({ page }) => {
    // If we're redirected to login, authenticate first
    if (page.url().includes('/login')) {
      await page.getByPlaceholder('you@example.com').fill(APP_EMAIL);
      await page.getByPlaceholder('Enter your password').fill(APP_PASSWORD);
      await page.getByRole('button', { name: /sign in/i }).click();

      // Wait for redirect away from login (to /site-setup)
      await page.waitForURL((url) => !url.href.includes('/login'), {
        timeout: 15_000,
      });
    }

    // We should now be on the site-setup page
    expect(page.url()).toContain('/site-setup');
  });

  test('register site', async ({ page }) => {
    // Step 1: Register Step — shows the WordPress site URL and a workspace picker

    // Wait for the "Register Your Site" heading
    await expect(
      page.getByRole('heading', { name: /register your site/i })
    ).toBeVisible({ timeout: 15_000 });

    // Select workspace (click the dropdown, pick the first option)
    const workspaceBtn = page.getByRole('button', {
      name: /select workspace/i,
    });
    // If the workspace is already pre-selected, skip the dropdown
    const needsWorkspaceSelection = await workspaceBtn.isVisible().catch(() => false);
    if (needsWorkspaceSelection) {
      await workspaceBtn.click();
      await page.getByRole('menuitem').first().click();
    }

    // Click "Register & Continue"
    await page.getByRole('button', { name: /register.*continue/i }).click();

    // Wait for registration to complete — success alert or move to next step
    await expect(
      page.getByText(/site registered|github/i)
    ).toBeVisible({ timeout: 15_000 });
  });

  test('continue past GitHub installations step', async ({ page }) => {
    // Step 2: GitHub Step — shows existing GitHub App installations
    // The staging E2E user should already have a GitHub App installed

    // Wait for the GitHub Accounts heading or the continue button
    const continueBtn = page.getByRole('button', {
      name: /continue to repository selection/i,
    });

    await expect(continueBtn).toBeVisible({ timeout: 15_000 });
    await continueBtn.click();
  });

  test('select repository and branch', async ({ page }) => {
    // Step 3: Repository Step — select account, repo, and branch

    // The repo name format is "owner/repo" — extract parts for matching
    const repoName = TEST_REPO.split('/').pop() ?? TEST_REPO;

    // Select GitHub account (first available)
    const accountDropdown = page.getByRole('button', {
      name: /select account/i,
    });
    // If account is already selected (only one installation), skip
    const needsAccountSelection = await accountDropdown.isVisible().catch(() => false);
    if (needsAccountSelection) {
      await accountDropdown.click();
      await page.getByRole('menuitem').first().click();
    }

    // Select repository — open dropdown, search, pick the match
    const repoDropdown = page.getByRole('button', {
      name: /select repository/i,
    });
    await expect(repoDropdown).toBeEnabled({ timeout: 10_000 });
    await repoDropdown.click();

    // Search for the test repo
    const repoSearch = page.getByPlaceholder('Search repositories...');
    if (await repoSearch.isVisible().catch(() => false)) {
      await repoSearch.fill(repoName);
    }

    // Click the matching repo
    await page.getByRole('menuitem', { name: new RegExp(repoName, 'i') }).click();

    // Select branch — open dropdown, pick the e2e-test branch
    const branchDropdown = page.getByRole('button', {
      name: /select branch/i,
    });
    await expect(branchDropdown).toBeEnabled({ timeout: 10_000 });
    await branchDropdown.click();
    await page
      .getByRole('menuitem', { name: new RegExp(TEST_BRANCH, 'i') })
      .click();

    // Click Continue
    await page.getByRole('button', { name: /^continue$/i }).click();
  });

  test('select deployment method and complete setup', async ({ page }) => {
    // Step 4: Method Step — select deployment method and workflow

    // Select deployment method — GitHub Actions
    const methodDropdown = page.getByRole('button', {
      name: /select method/i,
    });
    await expect(methodDropdown).toBeVisible({ timeout: 10_000 });
    await methodDropdown.click();
    await page
      .getByRole('menuitem', { name: /github actions/i })
      .click();

    // Select workflow (first available)
    const workflowDropdown = page.getByRole('button', {
      name: /select workflow/i,
    });
    await expect(workflowDropdown).toBeEnabled({ timeout: 10_000 });
    await workflowDropdown.click();
    await page.getByRole('menuitem').first().click();

    // Click "Complete Setup"
    await page.getByRole('button', { name: /complete setup/i }).click();

    // After completion, the app redirects back to WordPress with the
    // connection_token. Wait for the redirect back to WP admin.
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
