/**
 * GitHub API Mock for E2E Tests
 * Intercepts WordPress REST API calls and returns mock GitHub data
 */

const testData = require('../fixtures/test-data');
const { shouldUseIntegration } = require('../config/test-environment');

/**
 * Setup GitHub API mocks
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function setupGitHubMocks(page) {
  // Skip mocking if using real GitHub integration
  if (shouldUseIntegration()) {
    console.log('🔗 Skipping mocks - using real GitHub integration');
    return;
  }
  // Mock get installation repositories
  await page.route('**/wp-json/github-deploy/v1/installation/repos', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          repositories: testData.repositories,
          total_count: testData.repositories.length,
        },
      }),
    });
  });

  // Mock get repositories (alternative endpoint)
  await page.route('**/wp-json/github-deploy/v1/repos', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          repositories: testData.repositories,
          total_count: testData.repositories.length,
        },
      }),
    });
  });

  // Mock get branches
  await page.route('**/wp-json/github-deploy/v1/branches*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          branches: testData.branches,
          total_count: testData.branches.length,
        },
      }),
    });
  });

  // Mock get workflows
  await page.route('**/wp-json/github-deploy/v1/workflows*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          workflows: testData.workflows,
          total_count: testData.workflows.length,
        },
      }),
    });
  });

  // Mock validate repository
  await page.route('**/wp-json/github-deploy/v1/validate-repo', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          valid: true,
          message: 'Repository access validated successfully',
        },
      }),
    });
  });

  // Mock bind repository
  await page.route('**/wp-json/github-deploy/v1/bind-repo', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          message: 'Repository bound successfully',
          repository: testData.repositories[0],
        },
      }),
    });
  });

  // Mock trigger deployment
  await page.route('**/wp-json/github-deploy/v1/deploy', async (route) => {
    const request = route.request();
    const method = request.method();

    if (method === 'POST') {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: {
            deployment_id: testData.deployment.id,
            status: 'building',
            message: 'Deployment started successfully',
            build_url: testData.deployment.build_url,
          },
        }),
      });
    } else {
      await route.continue();
    }
  });

  // Mock get deployment status
  await page.route('**/wp-json/github-deploy/v1/status*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          status: 'success',
          deployment: testData.deployment,
          latest_commit: testData.commits[0],
        },
      }),
    });
  });

  // Mock get commits
  await page.route('**/wp-json/github-deploy/v1/commits*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          commits: testData.commits,
          total_count: testData.commits.length,
        },
      }),
    });
  });

  // Mock GitHub connection URL
  await page.route('**/wp-json/github-deploy/v1/connect-url', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          connect_url: 'https://github.com/apps/test-app/installations/new',
        },
      }),
    });
  });

  // Mock test connection
  await page.route('**/wp-json/github-deploy/v1/test-connection', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          connected: true,
          installation: testData.installation,
          message: 'Successfully connected to GitHub',
        },
      }),
    });
  });

  // Mock generate webhook secret
  await page.route('**/wp-json/github-deploy/v1/generate-secret', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          secret: 'new-webhook-secret-xyz789',
        },
      }),
    });
  });

  // Mock rollback deployment
  await page.route('**/wp-json/github-deploy/v1/rollback*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          message: 'Rollback completed successfully',
          deployment_id: testData.deployment.id - 1,
        },
      }),
    });
  });

  // Mock cancel deployment
  await page.route('**/wp-json/github-deploy/v1/cancel*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          message: 'Deployment cancelled successfully',
          deployment_id: testData.deployment.id,
          status: 'cancelled',
        },
      }),
    });
  });

  console.log('✅ GitHub API mocks configured');
}

/**
 * Setup mocks for GitHub App OAuth flow
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function mockGitHubOAuthFlow(page) {
  // Skip mocking if using real GitHub integration
  if (shouldUseIntegration()) {
    console.log('🔗 Skipping OAuth mock - using real GitHub OAuth flow');
    return;
  }

  // Simulate successful GitHub App installation
  await page.evaluate((installation) => {
    localStorage.setItem('github_installation_id', String(installation.id));
    localStorage.setItem('github_connected', 'true');
    localStorage.setItem('github_user', installation.account.login);
  }, testData.installation);

  console.log('✅ GitHub OAuth flow mocked');
}

/**
 * Clear GitHub mocks
 * @param {import('@playwright/test').Page} page - Playwright page object
 */
async function clearGitHubMocks(page) {
  await page.unroute('**/wp-json/github-deploy/**');
  console.log('🧹 GitHub API mocks cleared');
}

module.exports = {
  setupGitHubMocks,
  mockGitHubOAuthFlow,
  clearGitHubMocks,
};
