# Deploy Forge E2E Tests

End-to-end tests that run against the Railway staging environment using Playwright. These tests exercise the full user journey: plugin installation, site connection to the Deploy Forge app, triggering a deployment, and verifying rollback behavior.

## Prerequisites

- Node.js 20+
- Access to staging secrets (see `.env.example` in this directory)
- A built plugin ZIP -- run `bash build.sh` from the repo root

## Local Setup

1. Copy the example env file and fill in the credentials:

   ```
   cp .env.example .env
   ```

   You need values for the staging WordPress instance URL and admin credentials, the Deploy Forge app URL and E2E account credentials, and the reset endpoint secret. See `.env.example` for all required variables.

2. Install dependencies:

   ```
   npm install
   ```

3. Install the Playwright browser:

   ```
   npx playwright install chromium
   ```

## Running Tests

Build the plugin ZIP first (from the repo root):

```
cd ../.. && bash build.sh && cd tests/e2e
```

Then run:

```bash
# All tests
PLUGIN_ZIP_PATH=../../dist/deploy-forge-*.zip npm test

# With the browser visible
PLUGIN_ZIP_PATH=../../dist/deploy-forge-*.zip npm run test:headed

# A single test file
PLUGIN_ZIP_PATH=../../dist/deploy-forge-*.zip npx playwright test tests/01-install.spec.ts

# Step through tests in the Playwright debugger
PLUGIN_ZIP_PATH=../../dist/deploy-forge-*.zip npm run test:debug

# View the HTML report from the last run
npm run test:report
```

## Test Flow

Tests run serially in a fixed order. Each suite depends on the state left by the previous one:

1. **01-install** -- Resets the staging environment via the mu-plugin endpoint, uploads the plugin ZIP through the WordPress admin UI, activates the plugin, and verifies all admin pages load without PHP errors.
2. **02-connect** -- Initiates the OAuth-style connection flow from the settings page, authenticates with the Deploy Forge app, selects a repository, and confirms the connection is established back in WordPress.
3. **03-deploy** -- Triggers a manual deployment via the "Deploy Now" button, polls the deployments page until the build succeeds (up to 8 minutes), and verifies the deployment details modal works.
4. **04-rollback** -- Verifies the rollback button is not shown on the active deployment and confirms the rollback AJAX endpoint returns a clean JSON error for an invalid deployment ID.

Because the tests are stateful and sequential, the Playwright config sets `fullyParallel: false` and `workers: 1`. Retries are disabled -- a failure mid-chain means subsequent tests cannot run meaningfully.

## CI

The E2E suite runs automatically on pushes to the `staging` branch and can also be triggered manually via the GitHub Actions `workflow_dispatch` event. See `.github/workflows/e2e-staging.yml`.

Key CI details:

- **Concurrency group:** `e2e-staging` with `cancel-in-progress: false`, so only one run executes at a time and in-flight runs are not cancelled.
- **Timeout:** 30 minutes for the entire job.
- **Secrets:** All `STAGING_*` and `E2E_RESET_SECRET` values are stored as GitHub repository secrets.
- **Artifacts:** The Playwright HTML report is uploaded on every run (retained 14 days). Test result traces and screenshots are uploaded on failure (retained 7 days).

## Staging Reset

Before each test run, the install suite calls a REST endpoint on the staging WordPress instance to wipe all Deploy Forge state (plugin files, database table, options, transients, and cron hooks). This ensures tests always start from a clean slate.

The endpoint is provided by a WordPress must-use plugin:

```
staging-fixtures/e2e-reset-deploy-forge.php
```

This file must be deployed to `wp-content/mu-plugins/` on the staging WordPress instance. It exposes `POST /wp-json/e2e/v1/reset-deploy-forge` and authenticates requests using the `X-E2E-Secret` header, which must match the `E2E_RESET_SECRET` environment variable on the server.

Because it runs as a mu-plugin, it loads even if the main Deploy Forge plugin is deactivated or in a broken state. Never deploy this file to a production environment.
