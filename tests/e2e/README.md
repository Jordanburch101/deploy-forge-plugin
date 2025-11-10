# E2E Tests for GitHub Auto-Deploy Plugin

Automated end-to-end tests using Playwright and WordPress environment.

## Overview

This test suite validates the complete user journey through the GitHub Auto-Deploy plugin, including:

- ✅ Plugin installation and activation
- ✅ Setup wizard flow (all 6 steps)
- ✅ Repository connection and binding
- ✅ Manual deployment triggering
- ✅ Settings configuration and persistence
- ✅ Deployment status monitoring

## Tech Stack

- **Playwright** - Browser automation and testing framework
- **@wordpress/env** - WordPress Docker environment
- **@wordpress/e2e-test-utils-playwright** - WordPress-specific test utilities
- **Node.js 18+** - JavaScript runtime

## Prerequisites

- Node.js 18.0.0 or higher
- npm 9.0.0 or higher
- Docker Desktop (for wp-env)
- At least 4GB free RAM

## Installation

### 1. Install Dependencies

```bash
npm install
```

This installs:
- Playwright test runner
- WordPress environment tools
- WordPress E2E test utilities

### 2. Install Playwright Browsers

```bash
npx playwright install chromium
```

## Running Tests

### Quick Start - Run All Tests

```bash
# Start WordPress, run tests, stop WordPress
npm run test:full
```

### Individual Commands

```bash
# Start WordPress environment
npm run wp-env:start

# Run tests (headless)
npm test:e2e

# Run tests (headed - see browser)
npm run test:e2e:headed

# Run tests (debug mode)
npm run test:e2e:debug

# Run tests (UI mode - interactive)
npm run test:e2e:ui

# Stop WordPress environment
npm run wp-env:stop
```

### Running Specific Tests

```bash
# Run single test file
npx playwright test tests/e2e/specs/01-installation.spec.js

# Run tests matching pattern
npx playwright test --grep "wizard"

# Run tests in specific project
npx playwright test --project=chromium
```

### Development Workflow

For faster development iterations:

```bash
# 1. Start WordPress once
npm run wp-env:start

# 2. Run tests multiple times (WordPress stays running)
npm run test:e2e

# 3. Stop WordPress when done
npm run wp-env:stop
```

## Test Structure

```
tests/e2e/
├── config/
│   ├── global-setup.js          # Setup before all tests
│   ├── global-teardown.js       # Cleanup after all tests
│   └── wordpress-config.js      # WordPress fixtures
├── fixtures/
│   └── test-data.js             # Mock data for GitHub API
├── mocks/
│   └── github-api-mock.js       # GitHub API interception
├── helpers/
│   └── plugin-helpers.js        # Plugin utilities
├── page-objects/
│   ├── wizard-page.js           # Setup wizard page object
│   ├── dashboard-page.js        # Dashboard page object
│   └── settings-page.js         # Settings page object
└── specs/
    ├── 01-installation.spec.js  # Installation tests
    ├── 02-wizard-flow.spec.js   # Wizard flow tests
    ├── 03-manual-deploy.spec.js # Deployment tests
    └── 04-settings.spec.js      # Settings tests
```

## Test Scenarios

### 01-installation.spec.js
- Plugin appears in plugins list
- Plugin activates successfully
- Admin menu items created
- Database tables created
- Assets load correctly
- REST API endpoints registered

### 02-wizard-flow.spec.js
- Welcome screen displays
- GitHub connection simulation
- Repository selection
- Branch selection
- Deployment method selection
- Options configuration
- Review screen validation
- Settings persistence
- Wizard skip functionality
- Step validation

### 03-manual-deploy.spec.js
- Dashboard loads correctly
- Manual deployment trigger
- Deployment status monitoring
- Status refresh
- Deployment cancellation
- Deployment approval (manual mode)
- Rollback functionality
- Error handling

### 04-settings.spec.js
- Settings page loads
- Field validation
- Settings save and persistence
- Deployment method switching
- Toggle options
- GitHub connection test
- Webhook configuration
- Error handling

## Mocking Strategy

### Why Mock?

Tests mock GitHub API calls because:
1. **Speed** - No network delays
2. **Reliability** - No API rate limits
3. **Predictability** - Same results every time
4. **Cost** - No API quota usage

### What Gets Mocked?

- ✅ GitHub API responses (repos, branches, workflows)
- ✅ OAuth flow and authentication
- ✅ Deployment triggers and status
- ✅ Webhook callbacks

### What's Real?

- ✅ WordPress installation
- ✅ Plugin PHP code execution
- ✅ JavaScript execution
- ✅ Database operations
- ✅ File operations
- ✅ UI rendering

## Integration Testing (Real GitHub)

For more realistic testing, you can configure tests to use a real GitHub account instead of mocks.

### Setup Test GitHub Account

See [`TEST-ACCOUNT-SETUP.md`](../../TEST-ACCOUNT-SETUP.md) for detailed instructions on creating a dedicated GitHub test account and repository.

### Configure Integration Mode

Set these environment variables to enable integration testing:

```bash
# Required environment variables
export TEST_GITHUB_TOKEN="your_github_personal_access_token"
export TEST_GITHUB_USERNAME="your-test-github-username"
export TEST_GITHUB_REPO="test-theme-repo"

# Run tests with real GitHub integration
npm run test:e2e
```

### Integration vs Mock Mode

The test framework automatically detects which mode to use:

**Mock Mode** (Default):
- No environment variables needed
- GitHub API calls intercepted and mocked
- Fast, predictable, no API limits
- Console shows: `🎭 Using MOCKED GitHub responses`

**Integration Mode** (When env vars set):
- Real GitHub API calls
- Real repository and workflows
- Tests actual GitHub integration
- Console shows: `🔗 Using REAL GitHub integration`

### Running Integration Tests

```bash
# Option 1: Export environment variables
export TEST_GITHUB_TOKEN="ghp_xxxxxxxxxxxx"
export TEST_GITHUB_USERNAME="my-test-account"
export TEST_GITHUB_REPO="wordpress-test-theme"
npm run test:e2e

# Option 2: Inline environment variables
TEST_GITHUB_TOKEN="ghp_xxx" TEST_GITHUB_USERNAME="test-user" TEST_GITHUB_REPO="test-theme" npm run test:e2e
```

### GitHub Secrets for CI

To run integration tests in GitHub Actions:

1. Go to repository Settings → Secrets and variables → Actions
2. Add these repository secrets:
   - `TEST_GITHUB_TOKEN` - Personal access token from test account
   - `TEST_GITHUB_USERNAME` - Test account username
   - `TEST_GITHUB_REPO` - Test repository name

3. Update workflow file to use secrets:

```yaml
- name: Run Playwright tests
  run: npm run test:e2e
  env:
    CI: true
    TEST_GITHUB_TOKEN: ${{ secrets.TEST_GITHUB_TOKEN }}
    TEST_GITHUB_USERNAME: ${{ secrets.TEST_GITHUB_USERNAME }}
    TEST_GITHUB_REPO: ${{ secrets.TEST_GITHUB_REPO }}
```

### Integration Test Considerations

**Advantages:**
- Tests real GitHub API behavior
- Validates actual OAuth flow
- Tests real workflow execution
- Catches integration bugs

**Limitations:**
- Slower than mocked tests
- Requires GitHub account setup
- Subject to API rate limits
- OAuth flow still requires manual setup in some tests
- Tests may fail due to network issues

**Best Practice:**
- Use mocked tests for development (fast iteration)
- Use integration tests for CI/CD (validation before release)
- Keep test account credentials secure
- Use dedicated test repository (don't test on production repos)

## WordPress Environment

The tests run against a real WordPress installation:

- **WordPress Version:** 6.4
- **PHP Version:** 8.1
- **Database:** MariaDB (via Docker)
- **URL:** http://localhost:8888
- **Admin User:** admin
- **Admin Password:** password

### WP-CLI Access

```bash
# Run WP-CLI commands
npx wp-env run cli wp plugin list
npx wp-env run cli wp option get github_deploy_settings
npx wp-env run cli wp db query "SELECT * FROM wp_github_deployments"
```

### Environment Management

```bash
# Start environment
npm run wp-env:start

# Stop environment
npm run wp-env:stop

# Destroy environment (complete reset)
npm run wp-env:destroy

# Clean all data
npm run wp-env:clean
```

## Debugging Tests

### Interactive Debugging

```bash
# Debug mode - pauses execution
npm run test:e2e:debug

# UI mode - visual test runner
npm run test:e2e:ui
```

### Viewing Test Results

```bash
# Open HTML report
npm run test:e2e:report

# View in browser
open test-results/html-report/index.html
```

### Screenshots and Videos

Failed tests automatically capture:
- **Screenshots** - `test-results/**/*.png`
- **Videos** - `test-results/**/*.webm`
- **Traces** - `test-results/**/*.zip`

View traces with:
```bash
npx playwright show-trace test-results/trace.zip
```

## CI/CD Integration

Tests run automatically on GitHub Actions:

- ✅ On push to main, develop, claude/** branches
- ✅ On pull requests
- ✅ Manual workflow dispatch

### GitHub Actions Workflow

See `.github/workflows/e2e-tests.yml` for configuration.

Test artifacts (reports, videos, screenshots) are available for 30 days after each run.

## Writing New Tests

### 1. Use Page Objects

```javascript
const { test, expect } = require('../config/wordpress-config');
const { DashboardPage } = require('../page-objects/dashboard-page');

test('should do something', async ({ page }) => {
  const dashboard = new DashboardPage(page);
  await dashboard.navigate();
  await dashboard.triggerDeployment();

  // Assertions
  await expect(page).toHaveURL(/dashboard/);
});
```

### 2. Setup Mocks

```javascript
const { setupGitHubMocks } = require('../mocks/github-api-mock');

test.beforeEach(async ({ page }) => {
  await setupGitHubMocks(page);
});
```

### 3. Clean State

```javascript
const { resetPluginData } = require('../helpers/plugin-helpers');

test.beforeEach(async () => {
  await resetPluginData();
});
```

## Best Practices

### ✅ Do

- Use page objects for reusable interactions
- Mock external API calls
- Reset state before each test
- Use descriptive test names
- Add helpful console logs
- Use proper waits (waitForSelector, not waitForTimeout)
- Test user journeys, not implementation details

### ❌ Don't

- Make real GitHub API calls in tests
- Hardcode delays (use waitForSelector instead)
- Test internal implementation
- Share state between tests
- Skip error scenarios
- Ignore flaky tests (fix them!)

## Troubleshooting

### WordPress Won't Start

```bash
# Clean everything and restart
npm run wp-env:destroy
npm run wp-env:start
```

### Tests Fail Locally But Pass in CI

- Check Node.js version (should be 18+)
- Clear Playwright cache: `npx playwright install --force`
- Verify Docker has enough resources (4GB+ RAM)

### Tests Are Flaky

- Add explicit waits: `await page.waitForSelector(selector)`
- Check for race conditions in async operations
- Verify mocks are set up correctly
- Increase timeout if needed

### Plugin Not Activating

```bash
# Check plugin files are present
npx wp-env run cli wp plugin list

# Activate manually
npx wp-env run cli wp plugin activate github-auto-deploy

# Check for PHP errors
npx wp-env run cli wp plugin get github-auto-deploy --field=status
```

## Performance

- **Single test:** ~2-10 seconds
- **Full suite:** ~2-5 minutes
- **WordPress startup:** ~30-60 seconds (first time)

## Contributing

When adding new tests:

1. Follow existing patterns (page objects, mocks)
2. Add test to appropriate spec file
3. Update this README if adding new test categories
4. Ensure tests pass locally before committing
5. Check CI results after pushing

## Resources

- [Playwright Documentation](https://playwright.dev)
- [WordPress E2E Utils](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils-playwright/)
- [wp-env Documentation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
- [Plugin Specification](../../spec/)

## Support

For issues with tests:

1. Check this README for troubleshooting
2. Review test output and error messages
3. Check GitHub Actions logs
4. Open an issue with test name and error details

---

**Happy Testing! 🚀**
