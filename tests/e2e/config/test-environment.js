/**
 * Test Environment Configuration
 * Determines whether to use real GitHub integration or mocks
 */

/**
 * Check if integration tests should run
 * @returns {boolean}
 */
function shouldUseIntegration() {
  return !!(
    process.env.TEST_GITHUB_TOKEN &&
    process.env.TEST_GITHUB_USERNAME &&
    process.env.TEST_GITHUB_REPO
  );
}

/**
 * Get test environment configuration
 * @returns {Object}
 */
function getTestConfig() {
  const useIntegration = shouldUseIntegration();

  return {
    mode: useIntegration ? 'integration' : 'mock',
    useRealGitHub: useIntegration,
    github: {
      token: process.env.TEST_GITHUB_TOKEN || null,
      username: process.env.TEST_GITHUB_USERNAME || 'test-user',
      repository: process.env.TEST_GITHUB_REPO || 'test-repo',
      fullRepo: useIntegration
        ? `${process.env.TEST_GITHUB_USERNAME}/${process.env.TEST_GITHUB_REPO}`
        : 'testuser/test-theme-repo',
    },
  };
}

/**
 * Log test environment info
 */
function logTestEnvironment() {
  const config = getTestConfig();

  if (config.useRealGitHub) {
    console.log('🔗 Using REAL GitHub integration');
    console.log(`   Repository: ${config.github.fullRepo}`);
    console.log(`   Mode: Integration tests`);
  } else {
    console.log('🎭 Using MOCKED GitHub responses');
    console.log(`   Mode: Fast mock tests`);
    console.log(`   Tip: Set TEST_GITHUB_TOKEN to enable integration tests`);
  }
}

module.exports = {
  shouldUseIntegration,
  getTestConfig,
  logTestEnvironment,
};
