import { test as setup } from '@playwright/test';
import { WP_ADMIN_USER, WP_ADMIN_PASS } from './helpers';

setup('authenticate as WP admin', async ({ page }) => {
  if (!WP_ADMIN_USER || !WP_ADMIN_PASS) {
    throw new Error(
      'STAGING_WP_ADMIN_USER and STAGING_WP_ADMIN_PASS must be set in .env'
    );
  }

  await page.goto('/wp-login.php');

  await page.locator('#user_login').fill(WP_ADMIN_USER);
  await page.locator('#user_pass').fill(WP_ADMIN_PASS);
  await page.locator('#wp-submit').click();

  // Confirm login succeeded by waiting for the admin bar
  await page.locator('#wpadminbar').waitFor({ state: 'visible' });

  // Persist auth state for downstream tests
  await page.context().storageState({ path: '.auth/wp-admin.json' });
});
