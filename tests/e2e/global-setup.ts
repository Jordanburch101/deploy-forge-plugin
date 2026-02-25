import * as path from 'path';
import * as dotenv from 'dotenv';

// Load .env from the e2e directory
dotenv.config({ path: path.resolve(__dirname, '.env') });

const MAX_RETRIES = 5;
const RETRY_DELAY_MS = 3_000;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function globalSetup(): Promise<void> {
  const wpURL = process.env.STAGING_WP_URL;
  const resetSecret = process.env.E2E_RESET_SECRET;

  if (!wpURL) {
    throw new Error(
      'STAGING_WP_URL is not set. Copy .env.example to .env and fill in values.'
    );
  }

  if (!resetSecret) {
    throw new Error(
      'E2E_RESET_SECRET is not set. Copy .env.example to .env and fill in values.'
    );
  }

  const loginURL = `${wpURL}/wp-login.php`;

  // Railway containers can sleep — retry until the staging site is awake
  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    try {
      console.log(
        `[global-setup] Attempt ${attempt}/${MAX_RETRIES}: reaching ${loginURL}`
      );
      const response = await fetch(loginURL);
      if (response.ok) {
        console.log(
          `[global-setup] Staging site is reachable (status ${response.status})`
        );
        return;
      }
      console.log(
        `[global-setup] Non-OK status ${response.status}, retrying...`
      );
    } catch (error) {
      console.log(
        `[global-setup] Request failed: ${error instanceof Error ? error.message : error}`
      );
    }

    if (attempt < MAX_RETRIES) {
      console.log(
        `[global-setup] Waiting ${RETRY_DELAY_MS / 1000}s before next attempt...`
      );
      await sleep(RETRY_DELAY_MS);
    }
  }

  throw new Error(
    `[global-setup] Staging site at ${loginURL} is unreachable after ${MAX_RETRIES} attempts`
  );
}

export default globalSetup;
