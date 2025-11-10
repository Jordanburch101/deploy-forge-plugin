/**
 * Global teardown for Playwright tests
 * Runs once after all tests complete
 */

async function globalTeardown() {
  console.log('\n🧹 Running global teardown...\n');

  // In CI, wp-env will be stopped by the workflow
  // In local dev, we keep it running for faster test iterations
  if (process.env.CI) {
    console.log('🛑 CI environment detected - wp-env will be stopped by workflow');
  } else {
    console.log('💻 Local environment - wp-env left running for faster iterations');
    console.log('   Run "npm run wp-env:stop" to stop manually\n');
  }

  console.log('✨ Teardown complete!\n');
}

module.exports = globalTeardown;
