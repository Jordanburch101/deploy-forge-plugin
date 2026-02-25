import { type Page, type APIRequestContext } from '@playwright/test';

/* ------------------------------------------------------------------ */
/*  Environment constants                                              */
/* ------------------------------------------------------------------ */

export const WP_URL = process.env.STAGING_WP_URL ?? '';
export const WP_ADMIN_USER = process.env.STAGING_WP_ADMIN_USER ?? '';
export const WP_ADMIN_PASS = process.env.STAGING_WP_ADMIN_PASS ?? '';
export const APP_URL = process.env.STAGING_APP_URL ?? '';
export const APP_EMAIL = process.env.STAGING_APP_E2E_EMAIL ?? '';
export const APP_PASSWORD = process.env.STAGING_APP_E2E_PASSWORD ?? '';
export const RESET_SECRET = process.env.E2E_RESET_SECRET ?? '';
export const PLUGIN_ZIP_PATH = process.env.PLUGIN_ZIP_PATH ?? '';
export const TEST_REPO = process.env.E2E_TEST_REPO ?? '';
export const TEST_BRANCH = process.env.E2E_TEST_BRANCH ?? 'e2e-test';

/* ------------------------------------------------------------------ */
/*  resetDeployForge                                                   */
/*  Calls the staging reset endpoint to wipe Deploy Forge state        */
/* ------------------------------------------------------------------ */

export async function resetDeployForge(
  request: APIRequestContext
): Promise<void> {
  const resetURL = `${WP_URL}/wp-json/e2e/v1/reset-deploy-forge`;

  console.log(`[resetDeployForge] POST ${resetURL}`);

  const response = await request.post(resetURL, {
    headers: {
      'X-E2E-Secret': RESET_SECRET,
    },
  });

  if (!response.ok()) {
    const body = await response.text();
    throw new Error(
      `[resetDeployForge] Reset failed with status ${response.status()}: ${body}`
    );
  }

  const json = await response.json();
  console.log('[resetDeployForge] Reset successful:', JSON.stringify(json));
}

/* ------------------------------------------------------------------ */
/*  waitForDeploymentStatus                                            */
/*  Polls the current page by reloading every 10s, checking the first  */
/*  deployment row's status badge until it matches targetStatus.        */
/* ------------------------------------------------------------------ */

export async function waitForDeploymentStatus(
  page: Page,
  targetStatus: string,
  timeoutMs: number = 300_000
): Promise<void> {
  const pollIntervalMs = 10_000;
  const startTime = Date.now();

  console.log(
    `[waitForDeploymentStatus] Waiting for status "${targetStatus}" (timeout: ${timeoutMs / 1000}s)`
  );

  while (Date.now() - startTime < timeoutMs) {
    await page.reload({ waitUntil: 'domcontentloaded' });

    // Look for the first deployment row's status badge
    const statusBadge = page.locator(
      'table#deployments-table tbody tr:first-child .deployment-status'
    );

    const badgeCount = await statusBadge.count();
    if (badgeCount > 0) {
      const statusText = (await statusBadge.first().textContent()) ?? '';
      const normalizedStatus = statusText.trim().toLowerCase();
      const normalizedTarget = targetStatus.trim().toLowerCase();

      console.log(
        `[waitForDeploymentStatus] Current status: "${normalizedStatus}"`
      );

      if (normalizedStatus === normalizedTarget) {
        console.log(
          `[waitForDeploymentStatus] Target status "${targetStatus}" reached`
        );
        return;
      }

      // Fail early if deployment failed and we weren't waiting for failure
      if (
        normalizedStatus === 'failed' &&
        normalizedTarget !== 'failed'
      ) {
        throw new Error(
          `[waitForDeploymentStatus] Deployment failed unexpectedly while waiting for "${targetStatus}"`
        );
      }
    } else {
      console.log(
        '[waitForDeploymentStatus] No status badge found yet, retrying...'
      );
    }

    // Wait before next poll
    await page.waitForTimeout(pollIntervalMs);
  }

  throw new Error(
    `[waitForDeploymentStatus] Timed out after ${timeoutMs / 1000}s waiting for status "${targetStatus}"`
  );
}
