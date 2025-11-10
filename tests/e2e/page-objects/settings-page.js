/**
 * Settings Page Object
 * Provides methods for interacting with the plugin settings page
 */

class SettingsPage {
  constructor(page) {
    this.page = page;

    // Form fields
    this.repositoryField = '#repository';
    this.branchField = '#branch';
    this.workflowField = '#workflow_file';
    this.deploymentMethodSelect = '#deployment_method';

    // Toggles
    this.autoDeployToggle = '#auto_deploy_enabled';
    this.manualApprovalToggle = '#manual_approval_required';
    this.createBackupsToggle = '#create_backups';
    this.webhookToggle = '#webhook_enabled';

    // Buttons
    this.saveButton = 'button[type="submit"]';
    this.testConnectionButton = 'button[data-action="test-connection"]';
    this.generateSecretButton = 'button[data-action="generate-secret"]';
    this.disconnectButton = 'button[data-action="disconnect"]';
    this.resetButton = 'button[data-action="reset"]';

    // Repository selector (if using Select2)
    this.repoSelector = '.repo-select';
    this.branchSelector = '.branch-select';
    this.workflowSelector = '.workflow-select';

    // Connection status
    this.connectionStatus = '.connection-status';
    this.connectedBadge = '.connected-badge';

    // Notices
    this.successNotice = '.notice-success';
    this.errorNotice = '.notice-error';

    // Webhook section
    this.webhookUrl = '#webhook_url';
    this.webhookSecret = '#webhook_secret';
    this.copyWebhookUrlButton = 'button[data-action="copy-webhook-url"]';
    this.copyWebhookSecretButton = 'button[data-action="copy-webhook-secret"]';
  }

  /**
   * Navigate to settings page
   */
  async navigate() {
    await this.page.goto('/wp-admin/admin.php?page=github-deploy-settings');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Fill repository field
   * @param {string} repository - Repository in format 'owner/repo'
   */
  async fillRepository(repository) {
    await this.page.fill(this.repositoryField, repository);
  }

  /**
   * Fill branch field
   * @param {string} branch - Branch name
   */
  async fillBranch(branch) {
    await this.page.fill(this.branchField, branch);
  }

  /**
   * Fill workflow file field
   * @param {string} workflow - Workflow filename
   */
  async fillWorkflow(workflow) {
    await this.page.fill(this.workflowField, workflow);
  }

  /**
   * Select deployment method
   * @param {string} method - 'github_actions' or 'direct_clone'
   */
  async selectDeploymentMethod(method) {
    await this.page.selectOption(this.deploymentMethodSelect, method);
    await this.page.waitForTimeout(500); // Allow UI to update
  }

  /**
   * Toggle auto deploy
   * @param {boolean} enabled - Enable or disable
   */
  async toggleAutoDeploy(enabled) {
    const isChecked = await this.page.isChecked(this.autoDeployToggle);
    if (isChecked !== enabled) {
      await this.page.click(this.autoDeployToggle);
    }
  }

  /**
   * Toggle manual approval
   * @param {boolean} enabled - Enable or disable
   */
  async toggleManualApproval(enabled) {
    const isChecked = await this.page.isChecked(this.manualApprovalToggle);
    if (isChecked !== enabled) {
      await this.page.click(this.manualApprovalToggle);
    }
  }

  /**
   * Toggle create backups
   * @param {boolean} enabled - Enable or disable
   */
  async toggleCreateBackups(enabled) {
    const isChecked = await this.page.isChecked(this.createBackupsToggle);
    if (isChecked !== enabled) {
      await this.page.click(this.createBackupsToggle);
    }
  }

  /**
   * Toggle webhook
   * @param {boolean} enabled - Enable or disable
   */
  async toggleWebhook(enabled) {
    const isChecked = await this.page.isChecked(this.webhookToggle);
    if (isChecked !== enabled) {
      await this.page.click(this.webhookToggle);
    }
  }

  /**
   * Save settings
   */
  async saveSettings() {
    await this.page.click(this.saveButton);
    await this.page.waitForLoadState('networkidle');
    await this.page.waitForTimeout(1000);
  }

  /**
   * Test GitHub connection
   */
  async testConnection() {
    await this.page.click(this.testConnectionButton);
    await this.page.waitForTimeout(2000); // Wait for AJAX response
  }

  /**
   * Generate webhook secret
   */
  async generateWebhookSecret() {
    await this.page.click(this.generateSecretButton);
    await this.page.waitForTimeout(1000);
  }

  /**
   * Disconnect from GitHub
   */
  async disconnect() {
    await this.page.click(this.disconnectButton);

    // Confirm disconnection
    await this.page.waitForTimeout(500);
    const confirmButton = await this.page.locator('button:has-text("Disconnect")').count();
    if (confirmButton > 0) {
      await this.page.click('button:has-text("Disconnect")');
    }

    await this.page.waitForTimeout(1000);
  }

  /**
   * Reset all data
   */
  async resetAllData() {
    await this.page.click(this.resetButton);

    // First confirmation
    await this.page.waitForTimeout(500);
    await this.page.click('button:has-text("Yes, Reset")');

    // Second confirmation
    await this.page.waitForTimeout(500);
    await this.page.click('button:has-text("Confirm Reset")');

    await this.page.waitForTimeout(2000);
  }

  /**
   * Get repository value
   * @returns {Promise<string>}
   */
  async getRepository() {
    return await this.page.inputValue(this.repositoryField);
  }

  /**
   * Get branch value
   * @returns {Promise<string>}
   */
  async getBranch() {
    return await this.page.inputValue(this.branchField);
  }

  /**
   * Get workflow value
   * @returns {Promise<string>}
   */
  async getWorkflow() {
    return await this.page.inputValue(this.workflowField);
  }

  /**
   * Get deployment method value
   * @returns {Promise<string>}
   */
  async getDeploymentMethod() {
    return await this.page.inputValue(this.deploymentMethodSelect);
  }

  /**
   * Get webhook URL
   * @returns {Promise<string>}
   */
  async getWebhookUrl() {
    return await this.page.inputValue(this.webhookUrl);
  }

  /**
   * Get webhook secret
   * @returns {Promise<string>}
   */
  async getWebhookSecret() {
    return await this.page.inputValue(this.webhookSecret);
  }

  /**
   * Verify connection status
   * @param {boolean} shouldBeConnected - Expected connection state
   */
  async verifyConnectionStatus(shouldBeConnected = true) {
    await this.page.waitForSelector(this.connectionStatus, { state: 'visible' });

    if (shouldBeConnected) {
      await this.page.waitForSelector(this.connectedBadge, { state: 'visible' });
    } else {
      await this.page.waitForSelector(this.connectedBadge, { state: 'hidden' });
    }
  }

  /**
   * Verify success notice
   * @param {string} expectedMessage - Expected message
   */
  async verifySuccessNotice(expectedMessage = null) {
    await this.page.waitForSelector(this.successNotice, { state: 'visible', timeout: 5000 });

    if (expectedMessage) {
      const noticeText = await this.page.textContent(this.successNotice);
      if (!noticeText.includes(expectedMessage)) {
        throw new Error(`Expected notice "${expectedMessage}", found "${noticeText}"`);
      }
    }
  }

  /**
   * Verify error notice
   * @param {string} expectedMessage - Expected message
   */
  async verifyErrorNotice(expectedMessage = null) {
    await this.page.waitForSelector(this.errorNotice, { state: 'visible', timeout: 5000 });

    if (expectedMessage) {
      const noticeText = await this.page.textContent(this.errorNotice);
      if (!noticeText.includes(expectedMessage)) {
        throw new Error(`Expected error "${expectedMessage}", found "${noticeText}"`);
      }
    }
  }

  /**
   * Configure complete settings
   * @param {Object} settings - Settings object
   */
  async configureSettings(settings = {}) {
    const {
      repository = 'testuser/test-theme-repo',
      branch = 'main',
      workflow = 'build-theme.yml',
      method = 'github_actions',
      autoDeploy = true,
      manualApproval = false,
      createBackups = true,
      webhook = false,
    } = settings;

    await this.fillRepository(repository);
    await this.fillBranch(branch);
    await this.selectDeploymentMethod(method);

    if (method === 'github_actions') {
      await this.fillWorkflow(workflow);
    }

    await this.toggleAutoDeploy(autoDeploy);
    await this.toggleManualApproval(manualApproval);
    await this.toggleCreateBackups(createBackups);
    await this.toggleWebhook(webhook);

    await this.saveSettings();
  }
}

module.exports = { SettingsPage };
