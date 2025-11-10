/**
 * Dashboard Page Object
 * Provides methods for interacting with the plugin dashboard
 */

class DashboardPage {
  constructor(page) {
    this.page = page;

    // Selectors
    this.deployNowButton = 'button[data-action="deploy"]';
    this.cancelButton = 'button[data-action="cancel"]';
    this.rollbackButton = 'button[data-action="rollback"]';
    this.approveButton = 'button[data-action="approve"]';

    this.deploymentStatus = '.deployment-status';
    this.statusBadge = '.status-badge';
    this.latestCommit = '.latest-commit';
    this.deploymentInfo = '.deployment-info';

    this.recentDeployments = '.recent-deployments';
    this.deploymentRow = '.deployment-row';

    this.successNotice = '.notice-success';
    this.errorNotice = '.notice-error';
    this.warningNotice = '.notice-warning';

    this.loadingSpinner = '.loading-spinner';
    this.refreshButton = 'button[data-action="refresh"]';
  }

  /**
   * Navigate to dashboard page
   */
  async navigate() {
    await this.page.goto('/wp-admin/admin.php?page=github-deploy-dashboard');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Trigger manual deployment
   */
  async triggerDeployment() {
    await this.page.waitForSelector(this.deployNowButton, { state: 'visible' });
    await this.page.click(this.deployNowButton);

    // Wait for confirmation dialog (if any)
    await this.page.waitForTimeout(500);

    // Check if there's a confirmation modal
    const confirmButton = await this.page.locator('button:has-text("Deploy")').count();
    if (confirmButton > 0) {
      await this.page.click('button:has-text("Deploy")');
    }

    // Wait for deployment to start
    await this.page.waitForTimeout(1000);
  }

  /**
   * Cancel deployment
   */
  async cancelDeployment() {
    await this.page.waitForSelector(this.cancelButton, { state: 'visible' });
    await this.page.click(this.cancelButton);

    // Confirm cancellation
    await this.page.waitForTimeout(500);
    const confirmButton = await this.page.locator('button:has-text("Cancel Deployment")').count();
    if (confirmButton > 0) {
      await this.page.click('button:has-text("Cancel Deployment")');
    }

    await this.page.waitForTimeout(1000);
  }

  /**
   * Approve pending deployment
   */
  async approveDeployment() {
    await this.page.waitForSelector(this.approveButton, { state: 'visible' });
    await this.page.click(this.approveButton);

    // Confirm approval
    await this.page.waitForTimeout(500);
    const confirmButton = await this.page.locator('button:has-text("Approve")').count();
    if (confirmButton > 0) {
      await this.page.click('button:has-text("Approve")');
    }

    await this.page.waitForTimeout(1000);
  }

  /**
   * Rollback to previous deployment
   * @param {number} deploymentId - Deployment ID to rollback to
   */
  async rollbackDeployment(deploymentId = null) {
    if (deploymentId) {
      await this.page.click(`[data-deployment-id="${deploymentId}"] ${this.rollbackButton}`);
    } else {
      await this.page.click(this.rollbackButton);
    }

    // Confirm rollback
    await this.page.waitForTimeout(500);
    const confirmButton = await this.page.locator('button:has-text("Rollback")').count();
    if (confirmButton > 0) {
      await this.page.click('button:has-text("Rollback")');
    }

    await this.page.waitForTimeout(1000);
  }

  /**
   * Refresh deployment status
   */
  async refreshStatus() {
    await this.page.click(this.refreshButton);
    await this.page.waitForTimeout(500);
  }

  /**
   * Get current deployment status
   * @returns {Promise<string>}
   */
  async getDeploymentStatus() {
    await this.page.waitForSelector(this.deploymentStatus, { state: 'visible', timeout: 5000 });
    const statusElement = await this.page.locator(this.statusBadge);
    return await statusElement.textContent();
  }

  /**
   * Wait for deployment to complete
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForDeploymentComplete(timeout = 30000) {
    const startTime = Date.now();

    while (Date.now() - startTime < timeout) {
      const status = await this.getDeploymentStatus();

      if (status.includes('Success') || status.includes('success')) {
        return 'success';
      }

      if (status.includes('Failed') || status.includes('failed')) {
        return 'failed';
      }

      if (status.includes('Cancelled') || status.includes('cancelled')) {
        return 'cancelled';
      }

      // Still building/pending, wait and check again
      await this.page.waitForTimeout(2000);
    }

    throw new Error('Deployment did not complete within timeout');
  }

  /**
   * Get latest commit info
   * @returns {Promise<Object>}
   */
  async getLatestCommit() {
    await this.page.waitForSelector(this.latestCommit, { state: 'visible' });
    const commitElement = await this.page.locator(this.latestCommit);

    return {
      hash: await commitElement.locator('.commit-hash').textContent(),
      message: await commitElement.locator('.commit-message').textContent(),
      author: await commitElement.locator('.commit-author').textContent(),
    };
  }

  /**
   * Get recent deployments count
   * @returns {Promise<number>}
   */
  async getRecentDeploymentsCount() {
    const rows = await this.page.locator(this.deploymentRow).count();
    return rows;
  }

  /**
   * Verify success notice is displayed
   * @param {string} expectedMessage - Expected message text
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
   * Verify error notice is displayed
   * @param {string} expectedMessage - Expected message text
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
   * Check if loading spinner is visible
   * @returns {Promise<boolean>}
   */
  async isLoading() {
    const spinnerCount = await this.page.locator(this.loadingSpinner).count();
    return spinnerCount > 0;
  }

  /**
   * Wait for loading to finish
   * @param {number} timeout - Timeout in milliseconds
   */
  async waitForLoadingComplete(timeout = 10000) {
    await this.page.waitForSelector(this.loadingSpinner, { state: 'hidden', timeout });
  }
}

module.exports = { DashboardPage };
