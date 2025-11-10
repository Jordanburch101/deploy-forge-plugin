/**
 * Setup Wizard Page Object
 * Provides methods for interacting with the setup wizard
 */

class SetupWizardPage {
  constructor(page) {
    this.page = page;

    // Step selectors
    this.stepWelcome = '.wizard-step-welcome';
    this.stepConnect = '.wizard-step-connect';
    this.stepRepository = '.wizard-step-repository';
    this.stepMethod = '.wizard-step-method';
    this.stepOptions = '.wizard-step-options';
    this.stepReview = '.wizard-step-review';

    // Navigation buttons
    this.nextButton = 'button:has-text("Next")';
    this.backButton = 'button:has-text("Back")';
    this.skipButton = 'button:has-text("Skip Setup")';
    this.completeButton = 'button:has-text("Complete Setup")';
    this.getStartedButton = 'button:has-text("Get Started")';

    // Form elements
    this.repoSelector = '.repo-select';
    this.branchSelector = '.branch-select';
    this.workflowSelector = '.workflow-select';

    // Method selection
    this.githubActionsMethod = '[data-method="github_actions"]';
    this.directCloneMethod = '[data-method="direct_clone"]';

    // Toggle switches
    this.autoDeployToggle = '#toggle-auto-deploy';
    this.manualApprovalToggle = '#toggle-manual-approval';
    this.createBackupsToggle = '#toggle-create-backups';
    this.webhookToggle = '#toggle-webhook';

    // Progress stepper
    this.progressStepper = '.wizard-progress';
    this.activeStep = '.wizard-step.active';

    // Review screen
    this.reviewRepository = '.review-repository';
    this.reviewBranch = '.review-branch';
    this.reviewMethod = '.review-method';
    this.reviewWorkflow = '.review-workflow';
  }

  /**
   * Navigate to wizard page
   */
  async navigate() {
    await this.page.goto('/wp-admin/admin.php?page=github-deploy-wizard');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Complete Step 1 - Welcome
   */
  async completeWelcomeStep() {
    await this.page.waitForSelector(this.stepWelcome, { state: 'visible' });
    await this.page.click(this.getStartedButton);
    await this.page.waitForTimeout(500); // Allow animation
  }

  /**
   * Complete Step 2 - Connect to GitHub
   * Simulates successful GitHub connection
   */
  async completeConnectStep() {
    await this.page.waitForSelector(this.stepConnect, { state: 'visible' });

    // Simulate GitHub connection by setting localStorage
    await this.page.evaluate(() => {
      localStorage.setItem('github_installation_id', '98765432');
      localStorage.setItem('github_connected', 'true');
      localStorage.setItem('github_user', 'testuser');
    });

    // Reload to trigger connection check
    await this.page.reload();
    await this.page.waitForLoadState('networkidle');

    // Should auto-advance to next step
    await this.page.waitForTimeout(1000);
  }

  /**
   * Complete Step 3 - Select Repository
   * @param {string} repoName - Repository name to select
   * @param {string} branchName - Branch name to select
   */
  async completeRepositoryStep(repoName = 'test-theme-repo', branchName = 'main') {
    await this.page.waitForSelector(this.stepRepository, { state: 'visible' });

    // Wait for Select2 to initialize
    await this.page.waitForSelector('.select2-container', { timeout: 5000 });

    // Open repository dropdown
    await this.page.click(`${this.repoSelector} + .select2-container`);
    await this.page.waitForSelector('.select2-results', { state: 'visible' });

    // Select repository
    await this.page.click(`.select2-results li:has-text("${repoName}")`);
    await this.page.waitForTimeout(500);

    // Wait for branches to load
    await this.page.waitForSelector(`${this.branchSelector} + .select2-container:not(.select2-container--disabled)`, {
      timeout: 5000,
    });

    // Open branch dropdown
    await this.page.click(`${this.branchSelector} + .select2-container`);
    await this.page.waitForSelector('.select2-results', { state: 'visible' });

    // Select branch
    await this.page.click(`.select2-results li:has-text("${branchName}")`);
    await this.page.waitForTimeout(500);

    // Click next
    await this.page.click(this.nextButton);
    await this.page.waitForTimeout(500);
  }

  /**
   * Complete Step 4 - Select Deployment Method
   * @param {string} method - 'github_actions' or 'direct_clone'
   * @param {string} workflowName - Workflow name (if GitHub Actions)
   */
  async completeMethodStep(method = 'github_actions', workflowName = 'Build Theme') {
    await this.page.waitForSelector(this.stepMethod, { state: 'visible' });

    // Select method
    if (method === 'github_actions') {
      await this.page.click(this.githubActionsMethod);

      // Wait for workflows to load
      await this.page.waitForTimeout(1000);

      // Select workflow
      await this.page.click(`${this.workflowSelector} + .select2-container`);
      await this.page.waitForSelector('.select2-results', { state: 'visible' });
      await this.page.click(`.select2-results li:has-text("${workflowName}")`);
    } else {
      await this.page.click(this.directCloneMethod);
    }

    await this.page.waitForTimeout(500);
    await this.page.click(this.nextButton);
    await this.page.waitForTimeout(500);
  }

  /**
   * Complete Step 5 - Configure Options
   * @param {Object} options - Options to enable/disable
   */
  async completeOptionsStep(options = {}) {
    await this.page.waitForSelector(this.stepOptions, { state: 'visible' });

    const {
      autoDeploy = true,
      manualApproval = false,
      createBackups = true,
      webhook = false,
    } = options;

    // Toggle auto deploy if needed
    const autoDeployChecked = await this.page.isChecked(this.autoDeployToggle);
    if (autoDeployChecked !== autoDeploy) {
      await this.page.click(this.autoDeployToggle);
    }

    // Toggle manual approval if needed
    const manualApprovalChecked = await this.page.isChecked(this.manualApprovalToggle);
    if (manualApprovalChecked !== manualApproval) {
      await this.page.click(this.manualApprovalToggle);
    }

    // Toggle backups if needed
    const backupsChecked = await this.page.isChecked(this.createBackupsToggle);
    if (backupsChecked !== createBackups) {
      await this.page.click(this.createBackupsToggle);
    }

    // Toggle webhook if needed
    const webhookChecked = await this.page.isChecked(this.webhookToggle);
    if (webhookChecked !== webhook) {
      await this.page.click(this.webhookToggle);
    }

    await this.page.waitForTimeout(500);
    await this.page.click(this.nextButton);
    await this.page.waitForTimeout(500);
  }

  /**
   * Complete Step 6 - Review and Finish
   */
  async completeReviewStep() {
    await this.page.waitForSelector(this.stepReview, { state: 'visible' });
    await this.page.click(this.completeButton);

    // Wait for redirect to dashboard
    await this.page.waitForURL(/dashboard/, { timeout: 10000 });
  }

  /**
   * Complete entire wizard flow
   * @param {Object} config - Configuration options
   */
  async completeWizard(config = {}) {
    const {
      repo = 'test-theme-repo',
      branch = 'main',
      method = 'github_actions',
      workflow = 'Build Theme',
      options = {},
    } = config;

    await this.navigate();
    await this.completeWelcomeStep();
    await this.completeConnectStep();
    await this.completeRepositoryStep(repo, branch);
    await this.completeMethodStep(method, workflow);
    await this.completeOptionsStep(options);
    await this.completeReviewStep();
  }

  /**
   * Skip wizard
   */
  async skipWizard() {
    await this.page.click(this.skipButton);
    await this.page.waitForURL(/dashboard/, { timeout: 10000 });
  }

  /**
   * Get current step number
   * @returns {Promise<number>}
   */
  async getCurrentStep() {
    const stepElement = await this.page.locator(this.activeStep);
    const stepAttr = await stepElement.getAttribute('data-step');
    return parseInt(stepAttr, 10);
  }

  /**
   * Verify review screen data
   * @param {Object} expectedData - Expected data to verify
   */
  async verifyReviewData(expectedData = {}) {
    const {
      repository = 'test-theme-repo',
      branch = 'main',
      method = 'GitHub Actions',
      workflow = 'Build Theme',
    } = expectedData;

    await this.page.waitForSelector(this.reviewRepository);

    const repoText = await this.page.textContent(this.reviewRepository);
    const branchText = await this.page.textContent(this.reviewBranch);
    const methodText = await this.page.textContent(this.reviewMethod);

    if (!repoText.includes(repository)) {
      throw new Error(`Expected repository "${repository}", found "${repoText}"`);
    }

    if (!branchText.includes(branch)) {
      throw new Error(`Expected branch "${branch}", found "${branchText}"`);
    }

    if (!methodText.includes(method)) {
      throw new Error(`Expected method "${method}", found "${methodText}"`);
    }

    if (method === 'GitHub Actions' && workflow) {
      const workflowText = await this.page.textContent(this.reviewWorkflow);
      if (!workflowText.includes(workflow)) {
        throw new Error(`Expected workflow "${workflow}", found "${workflowText}"`);
      }
    }
  }
}

module.exports = { SetupWizardPage };
