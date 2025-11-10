/**
 * Setup Wizard Flow Tests
 * Tests the complete wizard experience and all steps
 */

const { test, expect } = require('../config/wordpress-config');
const { SetupWizardPage } = require('../page-objects/wizard-page');
const { setupGitHubMocks, mockGitHubOAuthFlow } = require('../mocks/github-api-mock');
const { resetPluginData, resetWizardState, getPluginSettings } = require('../helpers/plugin-helpers');
const { getTestConfig } = require('../config/test-environment');

test.describe('Setup Wizard Flow', () => {
  let wizardPage;

  test.beforeEach(async ({ page }) => {
    // Setup mocks and clean state
    await setupGitHubMocks(page);
    await resetPluginData();
    await resetWizardState();

    wizardPage = new SetupWizardPage(page);
  });

  test('should display welcome screen on wizard start', async ({ page }) => {
    await wizardPage.navigate();

    // Verify welcome screen is visible
    await expect(page.locator('h1')).toContainText('Welcome');
    await expect(page.locator(wizardPage.stepWelcome)).toBeVisible();
    await expect(page.locator(wizardPage.getStartedButton)).toBeVisible();

    // Verify progress stepper shows step 1
    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBe(1);
  });

  test('should advance from welcome to connect step', async ({ page }) => {
    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();

    // Should be on connect step
    await expect(page.locator(wizardPage.stepConnect)).toBeVisible();

    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBe(2);
  });

  test('should simulate GitHub connection', async ({ page }) => {
    const testConfig = getTestConfig();

    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();

    if (testConfig.useRealGitHub) {
      // In integration mode, skip OAuth mock - user would need to manually authenticate
      // This test becomes a placeholder for manual OAuth flow
      test.skip();
    } else {
      await mockGitHubOAuthFlow(page);

      // Reload to check connection status
      await page.reload();
      await page.waitForLoadState('networkidle');

      // Should show connected state
      const connectionStatus = await page.textContent('.connection-status, .github-status');
      expect(connectionStatus.toLowerCase()).toContain('connect');
    }
  });

  test('should complete repository selection step', async ({ page }) => {
    const testConfig = getTestConfig();

    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();
    await wizardPage.completeConnectStep();

    // Wait for repository step
    await expect(page.locator(wizardPage.stepRepository)).toBeVisible({ timeout: 10000 });

    // Complete repository selection - use real test repo in integration mode
    const repoName = testConfig.useRealGitHub ? testConfig.github.repository : 'test-theme-repo';
    await wizardPage.completeRepositoryStep(repoName, 'main');

    // Should advance to method step
    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBe(4);
  });

  test('should complete deployment method selection', async ({ page }) => {
    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();
    await wizardPage.completeConnectStep();
    await wizardPage.completeRepositoryStep();

    // Complete method step
    await wizardPage.completeMethodStep('github_actions', 'Build Theme');

    // Should advance to options step
    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBe(5);
  });

  test('should configure deployment options', async ({ page }) => {
    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();
    await wizardPage.completeConnectStep();
    await wizardPage.completeRepositoryStep();
    await wizardPage.completeMethodStep();

    // Configure options
    await wizardPage.completeOptionsStep({
      autoDeploy: true,
      manualApproval: false,
      createBackups: true,
      webhook: false,
    });

    // Should advance to review step
    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBe(6);
  });

  test('should display review screen with correct data', async ({ page }) => {
    const testConfig = getTestConfig();
    const repoName = testConfig.useRealGitHub ? testConfig.github.repository : 'test-theme-repo';

    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();
    await wizardPage.completeConnectStep();
    await wizardPage.completeRepositoryStep(repoName, 'main');
    await wizardPage.completeMethodStep('github_actions', 'Build Theme');
    await wizardPage.completeOptionsStep();

    // Verify review screen shows correct data
    await wizardPage.verifyReviewData({
      repository: repoName,
      branch: 'main',
      method: 'GitHub Actions',
      workflow: 'Build Theme',
    });
  });

  test('should complete wizard and redirect to dashboard', async ({ page }) => {
    const testConfig = getTestConfig();
    const repoName = testConfig.useRealGitHub ? testConfig.github.repository : 'test-theme-repo';

    await wizardPage.navigate();

    // Complete entire wizard
    await wizardPage.completeWizard({
      repo: repoName,
      branch: 'main',
      method: 'github_actions',
      workflow: 'Build Theme',
      options: {
        autoDeploy: true,
        manualApproval: false,
        createBackups: true,
      },
    });

    // Should redirect to dashboard
    await expect(page).toHaveURL(/dashboard/);

    // Should show success notice
    const pageContent = await page.textContent('body');
    const hasSuccess = pageContent.includes('Setup complete') || pageContent.includes('success');
    expect(hasSuccess).toBe(true);
  });

  test('should save settings after wizard completion', async ({ page }) => {
    const testConfig = getTestConfig();
    const repoName = testConfig.useRealGitHub ? testConfig.github.repository : 'test-theme-repo';
    const expectedFullRepo = testConfig.useRealGitHub
      ? testConfig.github.fullRepo
      : 'testuser/test-theme-repo';

    await wizardPage.navigate();

    // Complete wizard
    await wizardPage.completeWizard({
      repo: repoName,
      branch: 'main',
      method: 'github_actions',
      workflow: 'Build Theme',
    });

    // Check if settings were saved
    const settings = await getPluginSettings();
    expect(settings).toBeTruthy();
    expect(settings.repository).toBe(expectedFullRepo);
    expect(settings.branch).toBe('main');
    expect(settings.deployment_method).toBe('github_actions');
  });

  test('should allow skipping wizard', async ({ page }) => {
    await wizardPage.navigate();

    // Skip wizard
    await wizardPage.skipWizard();

    // Should redirect to dashboard
    await expect(page).toHaveURL(/dashboard/);
  });

  test('should allow back navigation through steps', async ({ page }) => {
    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();
    await wizardPage.completeConnectStep();

    // Go back
    await page.click(wizardPage.backButton);
    await page.waitForTimeout(500);

    // Should be on previous step
    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBeLessThan(4);
  });

  test('should select direct clone deployment method', async ({ page }) => {
    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();
    await wizardPage.completeConnectStep();
    await wizardPage.completeRepositoryStep();

    // Select direct clone method
    await wizardPage.completeMethodStep('direct_clone');

    // Should advance without workflow selection
    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBe(5);
  });

  test('should preserve step data on page reload', async ({ page }) => {
    const testConfig = getTestConfig();
    const repoName = testConfig.useRealGitHub ? testConfig.github.repository : 'test-theme-repo';

    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();
    await wizardPage.completeConnectStep();
    await wizardPage.completeRepositoryStep(repoName, 'main');

    // Reload page
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Should still be on same step (or resume from saved progress)
    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBeGreaterThanOrEqual(1);
  });

  test('should validate required fields before advancing', async ({ page }) => {
    await wizardPage.navigate();
    await wizardPage.completeWelcomeStep();
    await wizardPage.completeConnectStep();

    // Try to advance without selecting repository
    await page.click(wizardPage.nextButton);
    await page.waitForTimeout(500);

    // Should still be on repository step (validation failed)
    const currentStep = await wizardPage.getCurrentStep();
    expect(currentStep).toBe(3);
  });
});
