/**
 * Global setup for Playwright tests
 * Runs once before all tests
 */

const { execSync } = require('child_process');
const { logTestEnvironment } = require('./test-environment');

async function globalSetup() {
  console.log('\n🚀 Starting global test setup...\n');

  // Log test environment mode
  logTestEnvironment();
  console.log('');

  try {
    // Wait for WordPress to be ready
    console.log('⏳ Waiting for WordPress to be ready...');
    let attempts = 0;
    const maxAttempts = 30;

    while (attempts < maxAttempts) {
      try {
        const result = execSync('curl -s -o /dev/null -w "%{http_code}" http://localhost:8888', {
          encoding: 'utf-8',
          stdio: 'pipe',
        });

        if (result.trim() === '200' || result.trim() === '302') {
          console.log('✅ WordPress is ready!\n');
          break;
        }
      } catch (error) {
        // Curl failed, WordPress not ready yet
      }

      attempts++;
      if (attempts >= maxAttempts) {
        throw new Error('WordPress failed to start within timeout period');
      }

      await new Promise((resolve) => setTimeout(resolve, 2000));
    }

    // Activate plugin
    console.log('🔌 Activating plugin...');
    execSync('npx wp-env run cli wp plugin activate github-auto-deploy', {
      encoding: 'utf-8',
      stdio: 'inherit',
    });
    console.log('✅ Plugin activated!\n');

    // Flush rewrite rules
    console.log('🔄 Flushing rewrite rules...');
    execSync('npx wp-env run cli wp rewrite flush', {
      encoding: 'utf-8',
      stdio: 'inherit',
    });
    console.log('✅ Rewrite rules flushed!\n');

    console.log('✨ Global setup complete!\n');
  } catch (error) {
    console.error('❌ Global setup failed:', error.message);
    throw error;
  }
}

module.exports = globalSetup;
