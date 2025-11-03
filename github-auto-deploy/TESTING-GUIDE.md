# Testing Guide - GitHub Auto-Deploy Plugin

Complete guide to testing the GitHub Auto-Deploy WordPress plugin.

## Prerequisites

### Required
- WordPress 5.8+ installation (local or staging)
- PHP 7.4+ with sodium extension
- GitHub account with repository access
- Theme repository with GitHub Actions enabled
- HTTPS enabled (for webhooks)

### Recommended Testing Environments
- **Local**: Local by Flywheel, MAMP, or Docker
- **Staging**: WP Engine, Kinsta, or similar
- **Tools**: WP-CLI, Postman (for API testing)

---

## Installation & Setup Testing

### 1. Plugin Installation

```bash
# Navigate to WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Copy plugin
cp -r /path/to/github-auto-deploy ./

# Or symlink for development
ln -s /path/to/github-auto-deploy ./github-auto-deploy
```

**Test Checklist:**
- [ ] Plugin appears in WordPress admin → Plugins
- [ ] Plugin metadata displays correctly (name, version, author)
- [ ] No PHP errors in debug.log

### 2. Plugin Activation

**Activate via Admin:**
1. Go to WordPress admin → Plugins
2. Find "GitHub Auto-Deploy for WordPress"
3. Click "Activate"

**Activate via WP-CLI:**
```bash
wp plugin activate github-auto-deploy
```

**Test Checklist:**
- [ ] Plugin activates without errors
- [ ] Database table created: `wp_github_deployments`
- [ ] WP_Cron job scheduled: `github_deploy_check_builds`
- [ ] Menu item appears: "GitHub Deploy"

**Verify Database Table:**
```sql
SHOW TABLES LIKE 'wp_github_deployments';
DESCRIBE wp_github_deployments;
```

**Verify Cron Job:**
```bash
wp cron event list
# Look for: github_deploy_check_builds
```

---

## Configuration Testing

### 3. Settings Page

Navigate to: **GitHub Deploy → Settings**

**Test Each Field:**

1. **Repository Owner**
   - [ ] Enter your GitHub username/organization
   - [ ] Test with invalid characters (should sanitize)
   - [ ] Leave empty and save (should show validation error)

2. **Repository Name**
   - [ ] Enter repository name
   - [ ] Test with special characters

3. **Branch**
   - [ ] Enter "main" or your branch name
   - [ ] Test with invalid branch name

4. **Workflow File Name**
   - [ ] Enter "build-theme.yml"
   - [ ] Verify default value

5. **Personal Access Token**
   - [ ] Enter valid GitHub token
   - [ ] Verify it shows as masked (••••)
   - [ ] Save and reload page (should remain masked)
   - [ ] Enter invalid token

6. **Target Theme Directory**
   - [ ] Enter existing theme folder name
   - [ ] Enter non-existent folder (should show error)
   - [ ] Verify full path displays correctly

7. **Webhook Configuration**
   - [ ] Verify webhook URL displays
   - [ ] Copy webhook URL (should work)
   - [ ] Click "Generate New Secret"
   - [ ] Verify secret changes

8. **Deployment Options**
   - [ ] Toggle "Auto Deploy" checkbox
   - [ ] Toggle "Manual Approval" checkbox
   - [ ] Toggle "Create Backups" checkbox
   - [ ] Test notification email field

**Test Save Functionality:**
```bash
# Check saved settings in database
wp option get github_deploy_settings
wp option get github_deploy_token_encrypted
```

### 4. Test GitHub Connection

1. Click "Test Connection" button
2. Verify AJAX request completes
3. Check for success/error message

**Expected Results:**
- ✅ Success: "Successfully connected to repository!"
- ❌ Error: Shows specific error message

**Test Invalid Scenarios:**
- [ ] Invalid token → Shows auth error
- [ ] Non-existent repository → Shows 404 error
- [ ] Network error → Shows connection error

---

## GitHub Repository Setup

### 5. Create Test Repository

1. Create a new GitHub repository or use existing theme
2. Add a simple theme structure:

```bash
# Minimal theme structure
your-theme/
├── style.css          # Required theme file
├── index.php          # Required theme file
├── package.json       # For build process
└── .github/
    └── workflows/
        └── build-theme.yml
```

**style.css** (minimum):
```css
/*
Theme Name: Test Theme
Version: 1.0
*/
```

**index.php** (minimum):
```php
<?php
// Theme template
get_header();
get_footer();
```

### 6. Create GitHub Actions Workflow

Copy the example workflow to `.github/workflows/build-theme.yml`:

```yaml
name: Build Theme

on:
  workflow_dispatch:
  push:
    branches: [ main ]

jobs:
  build:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      # Simple build (or skip if no build needed)
      - name: Create build
        run: echo "Build complete"

      # Upload entire theme
      - name: Upload artifact
        uses: actions/upload-artifact@v3
        with:
          name: theme-build
          path: |
            **/*
            !.git/**
            !.github/**
```

**Test Workflow:**
```bash
# Push to GitHub
git add .
git commit -m "Add workflow"
git push

# Manually trigger in GitHub Actions tab
# Verify artifact is created
```

### 7. Create Personal Access Token

1. GitHub → Settings → Developer settings → Personal access tokens
2. Click "Generate new token (classic)"
3. Select scopes:
   - ✅ `repo` (all)
   - ✅ `workflow`
4. Copy token immediately

**Test Token Permissions:**
- [ ] Can read repository
- [ ] Can trigger workflows
- [ ] Can download artifacts

---

## Manual Deployment Testing

### 8. Test Manual Deployment

**Navigate to:** GitHub Deploy → Dashboard

**Test Steps:**
1. [ ] Click "Deploy Now" button
2. [ ] Verify confirmation dialog appears
3. [ ] Confirm deployment
4. [ ] Check deployment status updates
5. [ ] Monitor deployment logs

**Expected Flow:**
1. Deployment record created (status: pending)
2. GitHub Actions workflow triggered
3. Status changes to "building"
4. Workflow completes
5. Artifact downloaded
6. Backup created (if enabled)
7. Files deployed to theme directory
8. Status changes to "success"

**Verify in Database:**
```bash
wp db query "SELECT * FROM wp_github_deployments ORDER BY id DESC LIMIT 1;"
```

**Check Theme Files:**
```bash
ls -la wp-content/themes/your-theme/
```

**Check Backup:**
```bash
ls -la wp-content/uploads/github-deploy-backups/
```

### 9. Monitor Deployment

**Real-time Monitoring:**
- [ ] Dashboard shows deployment in "Recent Deployments"
- [ ] Status updates automatically (or on refresh)
- [ ] Build URL link works (opens GitHub Actions)
- [ ] Deployment completes within expected time

**Check Logs:**
- [ ] Navigate to History page
- [ ] Find deployment
- [ ] Click "Details"
- [ ] Verify logs are comprehensive

---

## Webhook Testing

### 10. Configure GitHub Webhook

**In GitHub Repository:**
1. Go to Settings → Webhooks → Add webhook
2. **Payload URL**: Copy from WordPress Settings
3. **Content type**: `application/json`
4. **Secret**: Copy from WordPress Settings
5. **Events**: Select "Just the push event"
6. **Active**: Check
7. Click "Add webhook"

**Test Webhook Setup:**
- [ ] Webhook appears in GitHub
- [ ] Green checkmark shows (or test delivery)

### 11. Test Automatic Deployment

**Enable Auto-Deploy:**
1. GitHub Deploy → Settings
2. Check "Enable automatic deployments on commit"
3. Uncheck "Require manual approval before deploying"
4. Save settings

**Test Deployment:**
```bash
# Make a change to your theme
echo "/* Test */" >> style.css

# Commit and push
git add style.css
git commit -m "Test auto-deploy"
git push origin main
```

**Verify:**
- [ ] Webhook received (check GitHub webhook deliveries)
- [ ] WordPress received webhook (check debug.log if enabled)
- [ ] Deployment triggered automatically
- [ ] Build started on GitHub Actions
- [ ] Deployment completed successfully

**Check Webhook Delivery in GitHub:**
1. Repository → Settings → Webhooks
2. Click webhook
3. Click "Recent Deliveries"
4. Verify 200 response

### 12. Test Manual Approval Mode

**Enable Manual Approval:**
1. Settings → Check "Require manual approval"
2. Save settings

**Test:**
```bash
# Make another change
echo "/* Test 2 */" >> style.css
git add . && git commit -m "Test manual approval" && git push
```

**Verify:**
- [ ] Deployment created with status "pending"
- [ ] Does NOT automatically deploy
- [ ] Dashboard shows pending deployment
- [ ] Can manually approve from admin

---

## Rollback Testing

### 13. Test Rollback Functionality

**Prerequisites:**
- [ ] At least 2 successful deployments
- [ ] Backups enabled

**Test Steps:**
1. Navigate to GitHub Deploy → History
2. Find a successful deployment (not the latest)
3. Click "Rollback" button
4. Confirm rollback
5. Verify files restored

**Verify:**
- [ ] Confirmation dialog appears
- [ ] Backup extracted successfully
- [ ] Theme files match previous version
- [ ] Deployment status updated to "rolled_back"

**Check Theme Version:**
```bash
# Compare file from backup
cat wp-content/themes/your-theme/style.css
```

---

## Error Scenario Testing

### 14. Test Error Handling

**Test Failed Build:**
1. Create workflow that fails:
```yaml
steps:
  - name: Fail build
    run: exit 1
```
2. Trigger deployment
3. Verify error handling

**Expected:**
- [ ] Status changes to "failed"
- [ ] Error message logged
- [ ] Theme files NOT modified
- [ ] Error visible in admin

**Test Network Errors:**
- [ ] Disconnect internet during deployment
- [ ] Test with invalid GitHub token
- [ ] Test with non-existent repository

**Test File Permission Errors:**
```bash
# Make theme directory read-only
chmod 555 wp-content/themes/your-theme/
```
- [ ] Deployment should fail gracefully
- [ ] Error message should be clear

```bash
# Restore permissions
chmod 755 wp-content/themes/your-theme/
```

### 15. Test Edge Cases

**Large Theme:**
- [ ] Deploy theme with 100+ files
- [ ] Deploy theme with large assets (images, fonts)
- [ ] Verify timeout handling

**Rapid Deployments:**
- [ ] Trigger multiple deployments quickly
- [ ] Verify queuing/handling

**Concurrent Deployments:**
- [ ] Start manual deployment
- [ ] Trigger webhook deployment
- [ ] Verify handling

---

## Security Testing

### 16. Test Security Features

**Token Encryption:**
```bash
# Check token in database (should be encrypted)
wp db query "SELECT option_value FROM wp_options WHERE option_name = 'github_deploy_token_encrypted';"
```
- [ ] Token is NOT plaintext
- [ ] Token is base64 encoded (encrypted)

**Webhook Signature Validation:**
1. Send webhook without signature → Should fail
2. Send webhook with invalid signature → Should fail
3. Send webhook with valid signature → Should succeed

**Test Webhook with Postman:**
```bash
# Calculate HMAC signature
echo -n '{"test":"data"}' | openssl dgst -sha256 -hmac "your-webhook-secret"

# Send POST request to webhook URL with:
# Header: x-hub-signature-256: sha256=<calculated-hash>
# Header: x-github-event: ping
# Body: {"test":"data"}
```

**Permission Testing:**
- [ ] Log in as non-admin user
- [ ] Try to access GitHub Deploy pages (should be denied)
- [ ] Try AJAX requests (should be denied)

**XSS Testing:**
- [ ] Enter `<script>alert('XSS')</script>` in settings
- [ ] Verify output is escaped
- [ ] Check in deployment logs

**SQL Injection Testing:**
- [ ] Try SQL in commit message
- [ ] Verify queries use prepared statements

---

## Performance Testing

### 17. Test Performance

**Large Deployments:**
```bash
# Monitor deployment time
time wp db query "SELECT * FROM wp_github_deployments WHERE id = X;"
```

**Database Performance:**
```bash
# Check query execution time
wp db query "EXPLAIN SELECT * FROM wp_github_deployments WHERE status = 'pending';"
```

**Cron Performance:**
- [ ] Check cron doesn't slow down site
- [ ] Verify transient caching works

---

## WP-CLI Testing

### 18. Command Line Testing

**Check plugin status:**
```bash
wp plugin list
wp plugin status github-auto-deploy
```

**Database inspection:**
```bash
wp db query "SELECT COUNT(*) FROM wp_github_deployments;"
wp db query "SELECT * FROM wp_github_deployments ORDER BY created_at DESC LIMIT 5;"
```

**Option inspection:**
```bash
wp option get github_deploy_settings --format=json
```

**Cron management:**
```bash
wp cron event list
wp cron test
wp cron event run github_deploy_check_builds
```

---

## Browser Testing

### 19. Test Admin Interface

**Browsers to Test:**
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)

**Responsive Testing:**
- [ ] Desktop (1920x1080)
- [ ] Tablet (768px)
- [ ] Mobile (375px)

**Test All Pages:**
- [ ] Dashboard loads correctly
- [ ] Settings form works
- [ ] History table displays
- [ ] Modals open/close
- [ ] AJAX requests complete

**JavaScript Console:**
- [ ] No console errors
- [ ] AJAX requests successful
- [ ] Nonces working

---

## Cleanup & Deactivation Testing

### 20. Test Deactivation

**Deactivate Plugin:**
```bash
wp plugin deactivate github-auto-deploy
```

**Verify:**
- [ ] Cron jobs removed
- [ ] Menu items removed
- [ ] Settings remain in database (for reactivation)
- [ ] Deployments table remains

**Reactivate:**
```bash
wp plugin activate github-auto-deploy
```

**Verify:**
- [ ] Settings restored
- [ ] Deployments history intact
- [ ] Cron jobs recreated

### 21. Uninstall Testing

**Note:** Plugin doesn't currently have uninstall.php

**Manual cleanup if needed:**
```bash
wp db query "DROP TABLE IF EXISTS wp_github_deployments;"
wp option delete github_deploy_settings
wp option delete github_deploy_token_encrypted
wp option delete github_deploy_db_version
```

---

## Testing Checklist Summary

### Essential Tests (Must Pass)
- [x] Plugin activation without errors
- [x] Database table creation
- [x] Settings save/retrieve
- [x] GitHub connection test
- [x] Manual deployment
- [x] Webhook reception
- [x] Automatic deployment
- [x] Rollback functionality
- [x] Error handling
- [x] Security (XSS, SQL injection)

### Recommended Tests
- [ ] Multiple environments (local, staging)
- [ ] Different theme types
- [ ] Large file deployments
- [ ] Concurrent deployments
- [ ] Browser compatibility
- [ ] Mobile responsive

### Optional Tests
- [ ] WP Multisite compatibility
- [ ] Different WordPress versions
- [ ] Different PHP versions (7.4, 8.0, 8.1, 8.2)
- [ ] Load testing
- [ ] Stress testing

---

## Troubleshooting Common Issues

### Deployment Stuck in "Building"
1. Check GitHub Actions status
2. Verify workflow completed
3. Manually run cron: `wp cron event run github_deploy_check_builds`

### Files Not Deploying
1. Check theme directory permissions: `ls -la wp-content/themes/`
2. Check WP_Filesystem initialization
3. Review deployment logs

### Webhook Not Working
1. Check webhook deliveries in GitHub
2. Verify webhook secret matches
3. Confirm site is accessible via HTTPS
4. Check WordPress debug.log

### Build Not Starting
1. Verify GitHub token has correct permissions
2. Check workflow file exists
3. Test connection in Settings

---

## Next Steps After Testing

Once all tests pass:

1. **Document Issues**: Record any bugs found
2. **Performance Baseline**: Note deployment times
3. **Create Test Cases**: Document test scenarios
4. **Update README**: Add testing results
5. **Prepare Release**: Tag version 1.0.0

## Need Help?

Check the main [README.md](README.md) for troubleshooting tips or refer to the [LINT-REPORT.md](LINT-REPORT.md) for code quality validation.
