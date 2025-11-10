# GitHub Auto-Deploy - Test Account Setup Guide

This guide explains how to set up a dedicated GitHub account and repository for E2E testing.

## 📋 Overview

The E2E tests can run in two modes:
1. **Mock Mode** (default) - Uses fake GitHub API responses for fast testing
2. **Integration Mode** - Uses a real GitHub account for full end-to-end testing

Integration mode provides more confidence that the plugin works with real GitHub API calls.

---

## 🔧 Setup Steps

### Step 1: Create Test GitHub Account

1. **Create a new GitHub account** for testing:
   - Go to https://github.com/signup
   - Use an email like: `your-name+github-deploy-test@gmail.com`
   - Username suggestion: `github-deploy-testing` or similar
   - Complete signup and verify email

2. **Note down credentials:**
   - Username: `_______________________`
   - Email: `_______________________`
   - Password: Store securely (you'll need it for tests)

### Step 2: Create Test Repository

1. **Create a new public repository** in the test account:
   ```
   Repository name: test-wordpress-theme
   Description: Test theme for WordPress GitHub Auto-Deploy plugin E2E tests
   Visibility: Public (recommended)
   Initialize: Yes, with README
   ```

2. **Clone the repository locally:**
   ```bash
   git clone https://github.com/YOUR-TEST-ACCOUNT/test-wordpress-theme.git
   cd test-wordpress-theme
   ```

3. **Add theme files** (see Theme Structure section below)

4. **Push to GitHub:**
   ```bash
   git add .
   git commit -m "Add test WordPress theme"
   git push origin main
   ```

### Step 3: Create GitHub Personal Access Token

1. **Go to Settings** in your test account:
   - https://github.com/settings/tokens

2. **Generate new token (classic)**:
   - Click "Generate new token" → "Generate new token (classic)"
   - Note: "WordPress Deploy Plugin E2E Tests"
   - Expiration: No expiration (or 1 year)
   - Select scopes:
     - ✅ `repo` (Full control of private repositories)
     - ✅ `workflow` (Update GitHub Action workflows)
     - ✅ `read:org` (Read org and team membership)

3. **Copy the token** (starts with `ghp_...`)
   - ⚠️ Save this immediately - you can't see it again!
   - Store it securely (you'll add it as a GitHub Secret)

### Step 4: Add GitHub Secrets to Your Main Repository

In your **main plugin repository** (not the test repo):

1. **Go to Settings** → **Secrets and variables** → **Actions**

2. **Add repository secrets**:

   Click "New repository secret" for each:

   | Secret Name | Value | Example |
   |-------------|-------|---------|
   | `TEST_GITHUB_TOKEN` | Your personal access token | `ghp_xxxxxxxxxxxx` |
   | `TEST_GITHUB_USERNAME` | Test account username | `github-deploy-testing` |
   | `TEST_GITHUB_REPO` | Test repository name | `test-wordpress-theme` |

3. **Verify secrets are added**:
   - You should see 3 secrets listed
   - Values are hidden (this is correct)

### Step 5: Enable Integration Tests

The tests will automatically run in integration mode when these environment variables are present.

**To run locally with integration tests:**

1. Create `.env.local` file in project root:
   ```bash
   TEST_GITHUB_TOKEN=ghp_your_token_here
   TEST_GITHUB_USERNAME=github-deploy-testing
   TEST_GITHUB_REPO=test-wordpress-theme
   ```

2. Run tests:
   ```bash
   npm run test:e2e
   ```

   The tests will detect the environment variables and use real GitHub integration.

**In GitHub Actions:**
- Integration tests run automatically when secrets are configured
- No additional setup needed

---

## 📁 Test WordPress Theme Structure

Add these files to your `test-wordpress-theme` repository:

### 1. `style.css`

```css
/*
Theme Name: Test Theme for GitHub Deploy
Theme URI: https://github.com/YOUR-TEST-ACCOUNT/test-wordpress-theme
Author: Test Account
Author URI: https://github.com/YOUR-TEST-ACCOUNT
Description: A minimal WordPress theme for testing the GitHub Auto-Deploy plugin
Version: 1.0.0
License: MIT
Tags: test, deployment
*/

body {
  font-family: Arial, sans-serif;
  margin: 0;
  padding: 20px;
  background-color: #f5f5f5;
}

.container {
  max-width: 800px;
  margin: 0 auto;
  background: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

h1 {
  color: #333;
  border-bottom: 2px solid #0073aa;
  padding-bottom: 10px;
}
```

### 2. `index.php`

```php
<?php
/**
 * Main template file
 *
 * @package Test_Theme_GitHub_Deploy
 */

get_header(); ?>

<div class="container">
    <h1><?php bloginfo('name'); ?></h1>
    <p><?php bloginfo('description'); ?></p>

    <div class="content">
        <h2>Test Theme Successfully Deployed!</h2>
        <p>This theme was deployed using the WordPress GitHub Auto-Deploy plugin.</p>
        <p><strong>Deployment Version:</strong> <?php echo wp_get_theme()->get('Version'); ?></p>
        <p><strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article>
                <h2><?php the_title(); ?></h2>
                <div><?php the_content(); ?></div>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p>No posts found.</p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
```

### 3. `functions.php`

```php
<?php
/**
 * Theme functions
 *
 * @package Test_Theme_GitHub_Deploy
 */

// Theme setup
function test_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');

    // Register navigation menus
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'test-theme'),
    ));
}
add_action('after_setup_theme', 'test_theme_setup');

// Enqueue styles
function test_theme_enqueue_styles() {
    wp_enqueue_style('test-theme-style', get_stylesheet_uri(), array(), '1.0.0');
}
add_action('wp_enqueue_scripts', 'test_theme_enqueue_styles');

// Add deployment info to admin bar
function test_theme_admin_bar_info($wp_admin_bar) {
    if (!is_admin()) {
        return;
    }

    $args = array(
        'id' => 'deployment-info',
        'title' => '✓ Deployed from GitHub',
        'href' => 'https://github.com/YOUR-TEST-ACCOUNT/test-wordpress-theme',
        'meta' => array(
            'class' => 'github-deploy-info',
            'title' => 'View on GitHub'
        )
    );
    $wp_admin_bar->add_node($args);
}
add_action('admin_bar_menu', 'test_theme_admin_bar_info', 100);
```

### 4. `.github/workflows/build-theme.yml`

```yaml
name: Build WordPress Theme

on:
  push:
    branches: [main]
  workflow_dispatch:

jobs:
  build:
    name: Build and Package Theme
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Create theme package
        run: |
          mkdir -p build
          cp -r * build/ 2>/dev/null || true
          cd build
          rm -rf .git .github build
          zip -r ../test-wordpress-theme.zip .

      - name: Upload artifact
        uses: actions/upload-artifact@v4
        with:
          name: test-wordpress-theme
          path: test-wordpress-theme.zip
          retention-days: 30

      - name: Display success message
        run: |
          echo "✅ Theme packaged successfully"
          echo "📦 Artifact: test-wordpress-theme.zip"
```

### 5. `README.md`

```markdown
# Test WordPress Theme

A minimal WordPress theme for testing the GitHub Auto-Deploy plugin.

## Purpose

This theme is used exclusively for E2E testing of the WordPress GitHub Auto-Deploy plugin.

## Features

- Minimal WordPress theme structure
- GitHub Actions workflow for building
- Sample deployment information display
- Compatible with WordPress 5.8+

## Testing

This theme is automatically deployed during E2E tests to verify the GitHub Auto-Deploy plugin functionality.

## DO NOT USE IN PRODUCTION

This is a test theme only. Do not use it on production WordPress sites.
```

---

## 🧪 Testing the Setup

### Verify GitHub Actions Workflow

1. **Push changes** to your test repository
2. **Check Actions tab** in GitHub
3. **Verify workflow runs** successfully
4. **Download artifact** to confirm ZIP is created

### Verify E2E Tests

1. **Trigger GitHub Actions** in your main plugin repository
2. **Check workflow logs** for integration test output
3. **Look for** messages like:
   ```
   ✅ Using real GitHub integration (TEST_GITHUB_TOKEN found)
   ✅ Connecting to github-deploy-testing/test-wordpress-theme
   ```

---

## 🔒 Security Best Practices

### DO:
- ✅ Use a dedicated test account (not your personal account)
- ✅ Use minimal permissions on Personal Access Token
- ✅ Store tokens in GitHub Secrets (never in code)
- ✅ Use public repositories when possible (easier testing)
- ✅ Set token expiration to 1 year maximum
- ✅ Rotate tokens periodically

### DON'T:
- ❌ Never commit tokens to git
- ❌ Never use your personal GitHub account for tests
- ❌ Never use tokens with admin or delete permissions
- ❌ Never share test account credentials
- ❌ Never store `.env.local` in git (it's in .gitignore)

---

## 🐛 Troubleshooting

### Tests fail with "401 Unauthorized"
- Check token is valid and not expired
- Verify token has `repo` and `workflow` scopes
- Confirm secrets are added to correct repository

### Tests fail with "404 Not Found"
- Verify repository exists and is public
- Check repository name matches `TEST_GITHUB_REPO`
- Confirm test account username is correct

### Workflow doesn't trigger
- Check `.github/workflows/build-theme.yml` exists
- Verify workflow syntax is valid
- Try manually triggering via "Run workflow" button

### Tests timeout
- GitHub API may be rate limited
- Check GitHub status: https://www.githubstatus.com/
- Wait a few minutes and retry

---

## 📊 What Gets Tested

With integration tests enabled:

| Test | Mock Mode | Integration Mode |
|------|-----------|------------------|
| WordPress login | ✓ | ✓ |
| Plugin activation | ✓ | ✓ |
| Admin menu creation | ✓ | ✓ |
| Database tables | ✓ | ✓ |
| GitHub connection | Mocked | **Real OAuth** |
| Repository listing | Mocked | **Real API** |
| Branch listing | Mocked | **Real API** |
| Workflow detection | Mocked | **Real API** |
| Deployment trigger | Mocked | **Real Workflow** |
| Webhook setup | Mocked | **Real Webhook** |

Integration mode gives you **much higher confidence** that everything works!

---

## 🎯 Next Steps

After setup:

1. ✅ Verify test repository exists
2. ✅ Verify GitHub secrets are configured
3. ✅ Run tests locally (optional)
4. ✅ Push to main repository to trigger CI
5. ✅ Monitor GitHub Actions for test results

---

## 📚 Resources

- [GitHub Personal Access Tokens](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token)
- [GitHub Actions Secrets](https://docs.github.com/en/actions/security-guides/encrypted-secrets)
- [WordPress Theme Development](https://developer.wordpress.org/themes/)
- [GitHub Actions Workflows](https://docs.github.com/en/actions/using-workflows)

---

## ❓ Need Help?

If you encounter issues:
1. Check the Troubleshooting section above
2. Review GitHub Actions logs for detailed errors
3. Verify all setup steps were completed
4. Check that secrets are properly configured

**The integration tests are optional** - basic tests still run without them!
