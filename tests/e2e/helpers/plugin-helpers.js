/**
 * Plugin helper functions for E2E tests
 * Provides utilities for plugin-specific operations
 */

const { execSync } = require('child_process');

/**
 * Reset all plugin data via WP-CLI
 */
async function resetPluginData() {
  try {
    // Delete all plugin options
    execSync(
      `npx wp-env run cli wp option delete github_deploy_repository --quiet`,
      { stdio: 'ignore' }
    );
    execSync(
      `npx wp-env run cli wp option delete github_deploy_settings --quiet`,
      { stdio: 'ignore' }
    );
    execSync(
      `npx wp-env run cli wp option delete github_deploy_installation_id --quiet`,
      { stdio: 'ignore' }
    );

    // Clear plugin transients
    execSync(
      `npx wp-env run cli wp transient delete --all --quiet`,
      { stdio: 'ignore' }
    );

    // Truncate deployment tables
    execSync(
      `npx wp-env run cli wp db query "TRUNCATE TABLE wp_github_deployments" --quiet`,
      { stdio: 'ignore' }
    );

    console.log('✅ Plugin data reset successfully');
  } catch (error) {
    // Ignore errors if options don't exist
    console.log('⚠️  Some plugin data may not have existed (this is OK)');
  }
}

/**
 * Reset plugin data via admin AJAX
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function resetPluginDataViaAjax(page) {
  await page.evaluate(async () => {
    const formData = new FormData();
    formData.append('action', 'github_deploy_reset_all_data');
    formData.append('nonce', window.githubDeployData?.nonce || '');
    formData.append('confirm', 'yes');

    const response = await fetch('/wp-admin/admin-ajax.php', {
      method: 'POST',
      body: formData,
    });

    return response.json();
  });

  console.log('✅ Plugin data reset via AJAX');
}

/**
 * Get plugin settings from database
 * @returns {Promise<Object>} Plugin settings
 */
async function getPluginSettings() {
  try {
    const result = execSync(
      `npx wp-env run cli wp option get github_deploy_settings --format=json`,
      { encoding: 'utf-8' }
    );
    return JSON.parse(result);
  } catch (error) {
    return null;
  }
}

/**
 * Set plugin settings in database
 * @param {Object} settings - Settings object
 */
async function setPluginSettings(settings) {
  const settingsJson = JSON.stringify(settings).replace(/"/g, '\\"');
  execSync(
    `npx wp-env run cli wp option update github_deploy_settings '${settingsJson}' --format=json`,
    { encoding: 'utf-8', stdio: 'inherit' }
  );
  console.log('✅ Plugin settings updated');
}

/**
 * Get deployment count from database
 * @returns {Promise<number>} Number of deployments
 */
async function getDeploymentCount() {
  try {
    const result = execSync(
      `npx wp-env run cli wp db query "SELECT COUNT(*) as count FROM wp_github_deployments" --skip-column-names`,
      { encoding: 'utf-8' }
    );
    return parseInt(result.trim(), 10);
  } catch (error) {
    return 0;
  }
}

/**
 * Insert test deployment into database
 * @param {Object} deployment - Deployment data
 */
async function insertTestDeployment(deployment) {
  const {
    commit_hash = 'abc123',
    commit_message = 'Test deployment',
    commit_author = 'Test User',
    status = 'success',
    trigger_type = 'manual',
  } = deployment;

  execSync(
    `npx wp-env run cli wp db query "INSERT INTO wp_github_deployments (commit_hash, commit_message, commit_author, status, trigger_type, deployed_at) VALUES ('${commit_hash}', '${commit_message}', '${commit_author}', '${status}', '${trigger_type}', NOW())"`,
    { encoding: 'utf-8', stdio: 'inherit' }
  );

  console.log('✅ Test deployment inserted');
}

/**
 * Clear all deployments from database
 */
async function clearDeployments() {
  try {
    execSync(
      `npx wp-env run cli wp db query "TRUNCATE TABLE wp_github_deployments" --quiet`,
      { stdio: 'ignore' }
    );
    console.log('✅ Deployments cleared');
  } catch (error) {
    console.log('⚠️  Could not clear deployments');
  }
}

/**
 * Wait for element with retry
 * @param {import('@playwright/test').Page} page - Playwright page object
 * @param {string} selector - Element selector
 * @param {number} timeout - Timeout in milliseconds
 */
async function waitForElement(page, selector, timeout = 10000) {
  await page.waitForSelector(selector, { timeout, state: 'visible' });
}

/**
 * Wait for AJAX to complete
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function waitForAjaxComplete(page) {
  await page.waitForFunction(() => {
    return typeof jQuery !== 'undefined' && jQuery.active === 0;
  }, { timeout: 10000 });
}

/**
 * Simulate wizard completion
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function completeWizardViaOption(page) {
  await page.evaluate(() => {
    document.cookie = 'github_deploy_wizard_completed=1; path=/';
  });

  // Also set via WP-CLI
  try {
    execSync(
      `npx wp-env run cli wp option update github_deploy_wizard_completed 1`,
      { stdio: 'ignore' }
    );
  } catch (error) {
    // Ignore
  }

  console.log('✅ Wizard marked as completed');
}

/**
 * Reset wizard state
 */
async function resetWizardState() {
  try {
    execSync(
      `npx wp-env run cli wp option delete github_deploy_wizard_completed --quiet`,
      { stdio: 'ignore' }
    );
    execSync(
      `npx wp-env run cli wp transient delete github_deploy_wizard_data --quiet`,
      { stdio: 'ignore' }
    );
    console.log('✅ Wizard state reset');
  } catch (error) {
    // Ignore
  }
}

/**
 * Check if plugin is activated
 * @returns {Promise<boolean>}
 */
async function isPluginActivated() {
  try {
    const result = execSync(
      `npx wp-env run cli wp plugin is-active github-auto-deploy`,
      { encoding: 'utf-8', stdio: 'pipe' }
    );
    return result.includes('Success');
  } catch (error) {
    return false;
  }
}

/**
 * Activate plugin via WP-CLI
 */
async function activatePlugin() {
  execSync(
    `npx wp-env run cli wp plugin activate github-auto-deploy`,
    { encoding: 'utf-8', stdio: 'inherit' }
  );
  console.log('✅ Plugin activated');
}

/**
 * Deactivate plugin via WP-CLI
 */
async function deactivatePlugin() {
  execSync(
    `npx wp-env run cli wp plugin deactivate github-auto-deploy`,
    { encoding: 'utf-8', stdio: 'inherit' }
  );
  console.log('✅ Plugin deactivated');
}

module.exports = {
  resetPluginData,
  resetPluginDataViaAjax,
  getPluginSettings,
  setPluginSettings,
  getDeploymentCount,
  insertTestDeployment,
  clearDeployments,
  waitForElement,
  waitForAjaxComplete,
  completeWizardViaOption,
  resetWizardState,
  isPluginActivated,
  activatePlugin,
  deactivatePlugin,
};
