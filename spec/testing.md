# Testing Specification

**Last Updated:** 2025-11-09

## Overview

This document outlines testing strategies, methodologies, and procedures for ensuring the WordPress Deploy Forge plugin functions correctly and securely.

## Testing Levels

### 1. Unit Testing

**Scope:** Individual methods and functions in isolation

**Not Currently Implemented** (Future Enhancement)

**Planned Coverage:**

- GitHub API wrapper methods
- Encryption/decryption functions
- Input sanitization functions
- Database queries
- Helper functions

**Framework:** PHPUnit with WordPress test suite

### 2. Integration Testing

**Scope:** Component interactions

**Critical Integration Points:**

- Plugin ↔ GitHub API
- Plugin ↔ WordPress database
- Plugin ↔ WordPress filesystem
- Webhook handler ↔ Deployment manager
- GitHub API ↔ Deployment manager

**Test Scenarios:**

- End-to-end deployment flow
- Webhook signature verification
- Artifact download and extraction
- Backup and rollback
- Concurrent deployment handling

### 3. Manual Testing

**Scope:** Full application testing in real environment

**Required Before Release:**

- All user workflows
- Error scenarios
- Edge cases
- Cross-browser compatibility (admin UI)
- Different WordPress versions
- Different PHP versions

### 4. Security Testing

**Scope:** Vulnerability assessment

**Areas:**

- Authentication bypass attempts
- CSRF attacks
- SQL injection
- XSS attacks
- Path traversal
- Webhook spoofing
- Token exposure

## Test Environments

### Local Development

**Setup:**

```bash
# WordPress local installation
- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+
- Mock GitHub repository
```

**Tools:**

- Local by Flywheel / XAMPP / MAMP
- ngrok for webhook testing
- GitHub test repository

### Staging Environment

**Requirements:**

- Production-like WordPress installation
- HTTPS enabled (for webhooks)
- Real GitHub repository
- GitHub Actions configured

### Production Testing

**Limited Scope:**

- Smoke tests after deployment
- Monitoring for errors
- Real-world usage validation

## Manual Test Cases

### TC001: Plugin Activation

**Prerequisites:** Fresh WordPress installation

**Steps:**

1. Upload plugin to `/wp-content/plugins/`
2. Navigate to Plugins page
3. Click "Activate"

**Expected Results:**

- ✅ Plugin activates without errors
- ✅ Database tables created
- ✅ Admin menu appears
- ✅ Settings page accessible
- ✅ No PHP errors in logs

### TC002: GitHub Connection Setup

**Prerequisites:** Plugin activated

**Steps:**

1. Navigate to Settings page
2. Click "Connect with GitHub"
3. Complete OAuth flow
4. Enter repository details
5. Click "Test Connection"

**Expected Results:**

- ✅ OAuth flow completes
- ✅ API key stored encrypted
- ✅ Connection test successful
- ✅ Success message displayed

### TC003: Workflow Selection

**Prerequisites:** Connected to GitHub

**Steps:**

1. Navigate to Settings page
2. Repository and branch selected
3. Click "Load Workflows" button
4. Select workflow from dropdown
5. Save settings

**Expected Results:**

- ✅ Workflows load successfully
- ✅ Dropdown populated with active workflows
- ✅ Selected workflow saved
- ✅ Settings persist after page reload

### TC004: Manual Deployment

**Prerequisites:** Plugin fully configured

**Steps:**

1. Navigate to Dashboard page
2. Select commit from list
3. Click "Deploy Now"
4. Monitor deployment progress
5. Wait for completion

**Expected Results:**

- ✅ Deployment starts immediately
- ✅ Status updates in real-time
- ✅ GitHub Actions workflow triggered
- ✅ Artifact downloaded
- ✅ Backup created
- ✅ Theme files deployed
- ✅ Status changes to "Success"
- ✅ No errors in logs

### TC005: Webhook Deployment

**Prerequisites:** Webhook configured on GitHub

**Steps:**

1. Push commit to configured branch
2. GitHub sends webhook
3. Monitor deployment in WordPress

**Expected Results:**

- ✅ Webhook received and validated
- ✅ Deployment starts automatically
- ✅ Same as TC004 from step 4

### TC006: Webhook Signature Validation

**Prerequisites:** Webhook secret configured

**Steps:**

1. Send webhook with invalid signature
2. Send webhook with no signature
3. Send webhook with valid signature

**Expected Results:**

- ❌ Invalid signature rejected (401)
- ❌ Missing signature rejected (401)
- ✅ Valid signature accepted (200)

### TC007: Deployment Cancellation

**Prerequisites:** Deployment in building state

**Steps:**

1. Start deployment
2. Wait for building state
3. Click "Cancel" button
4. Confirm cancellation

**Expected Results:**

- ✅ Cancel request sent to GitHub
- ✅ Deployment status changes to "Cancelled"
- ✅ No files deployed
- ✅ Can start new deployment

### TC008: Rollback

**Prerequisites:** Successful deployment with backup

**Steps:**

1. Complete deployment
2. Navigate to History page
3. Click "Rollback" on deployment
4. Confirm rollback

**Expected Results:**

- ✅ Backup extracted
- ✅ Previous files restored
- ✅ Status changes to "Rolled Back"
- ✅ Theme functions correctly

### TC009: Failed Build Handling

**Prerequisites:** Repository with failing tests

**Steps:**

1. Push commit with failing code
2. Wait for deployment to process
3. Check deployment status

**Expected Results:**

- ✅ Build fails on GitHub
- ✅ Deployment status changes to "Failed"
- ✅ Error message displayed
- ✅ Link to build logs available
- ✅ No theme files modified
- ✅ Can retry deployment

### TC010: Double-Zipped Artifact

**Prerequisites:** GitHub Actions that uploads ZIP

**Steps:**

1. Trigger deployment
2. Verify artifact is double-zipped
3. Wait for completion

**Expected Results:**

- ✅ Outer ZIP extracted
- ✅ Inner ZIP detected
- ✅ Inner ZIP extracted
- ✅ Theme files deployed correctly

### TC011: Concurrent Deployment Prevention

**Prerequisites:** Plugin configured

**Steps:**

1. Start manual deployment
2. While building, try to start another
3. Check error message

**Expected Results:**

- ❌ Second deployment blocked
- ✅ Error message: "Deployment in progress"
- ✅ Building deployment info shown
- ✅ Option to cancel first deployment

### TC012: Settings Reset

**Prerequisites:** Plugin configured

**Steps:**

1. Navigate to Settings
2. Click "Reset All Data"
3. Confirm reset

**Expected Results:**

- ✅ All settings cleared
- ✅ Deployments deleted
- ✅ Connection removed
- ✅ Plugin returns to initial state

## Security Test Cases

### SEC001: Unauthorized Access

**Test:** Access admin pages without authentication

**Expected:** Redirect to login page

### SEC002: Insufficient Permissions

**Test:** Access as non-admin user (Editor, Author)

**Expected:** "You do not have sufficient permissions"

### SEC003: CSRF Attack

**Test:** Submit settings form without valid nonce

**Expected:** Request rejected with nonce error

### SEC004: SQL Injection

**Test:** Inject SQL in all form fields

**Expected:** Inputs sanitized, no SQL executed

### SEC005: XSS Attack

**Test:** Inject JavaScript in all form fields

**Expected:** All outputs escaped, scripts not executed

### SEC006: Path Traversal

**Test:** Attempt to deploy outside themes directory

**Expected:** Path validation prevents access

### SEC007: Token Exposure

**Test:** Check all API responses and logs

**Expected:** No tokens or secrets exposed anywhere

### SEC008: Webhook Replay Attack

**Test:** Replay captured webhook request

**Expected:** Same result (idempotent), no duplicate deployment

## Performance Test Cases

### PERF001: Large Theme Deployment

**Test:** Deploy 100MB+ theme

**Expected:**

- Completes without timeout
- Memory usage acceptable
- No PHP errors

### PERF002: Concurrent Webhooks

**Test:** Send multiple webhooks rapidly

**Expected:**

- All webhooks processed
- Only one deployment active
- No race conditions

### PERF003: Database Performance

**Test:** Query performance with 1000+ deployments

**Expected:**

- List queries under 1 second
- Indexes utilized
- No N+1 queries

## Compatibility Testing

### WordPress Versions

**Test Matrix:**

- WordPress 5.8 (minimum)
- WordPress 6.0
- WordPress 6.1
- WordPress 6.2
- WordPress 6.3
- WordPress 6.4 (latest)

### PHP Versions

**Test Matrix:**

- PHP 7.4 (minimum)
- PHP 8.0
- PHP 8.1
- PHP 8.2
- PHP 8.3

### Database

**Test Matrix:**

- MySQL 5.7
- MySQL 8.0
- MariaDB 10.2
- MariaDB 10.6

### Hosting Providers

**Test On:**

- Shared hosting (GoDaddy, Bluehost)
- Managed WordPress (WP Engine, Kinsta)
- VPS (DigitalOcean, Linode)
- Local development (XAMPP, Local)

## Testing Tools

### Manual Testing Tools

**Webhook Testing:**

```bash
# Test webhook locally with ngrok
ngrok http 80

# Send test webhook
curl -X POST https://your-ngrok-url/wp-json/deploy-forge/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: ping" \
  -H "X-Hub-Signature-256: sha256=..." \
  -d '{"zen": "Test"}'
```

**GitHub Actions Testing:**

- Manual workflow dispatch
- Push to test branch
- Mock workflow runs

**Database Inspection:**

```sql
-- Check deployment records
SELECT * FROM wp_github_deployments ORDER BY created_at DESC LIMIT 10;

-- Check logs
SELECT * FROM wp_github_deploy_logs WHERE level = 'ERROR' LIMIT 20;
```

### Automated Testing Tools

**Linting:**

```bash
# PHP_CodeSniffer with WordPress standards
phpcs --standard=WordPress deploy-forge/

# PHPStan static analysis
phpstan analyse deploy-forge/
```

**Configuration:**

- See `LINTER-CONFIGURATION.md` for setup details

## Test Data

### Mock Webhook Payloads

**Push Event:**

```json
{
  "ref": "refs/heads/main",
  "head_commit": {
    "id": "abc123...",
    "message": "Test commit",
    "author": {
      "name": "Test User"
    },
    "timestamp": "2025-11-09T12:00:00Z"
  }
}
```

**Workflow Run Event:**

```json
{
  "action": "completed",
  "workflow_run": {
    "id": 123456,
    "status": "completed",
    "conclusion": "success",
    "head_sha": "abc123..."
  }
}
```

### Test Repository

**Requirements:**

- Public or private test repository
- GitHub Actions configured
- Sample theme structure
- Workflow that builds and uploads artifact

## Regression Testing

**Before Each Release:**

- [ ] Run all manual test cases
- [ ] Verify security test cases
- [ ] Test on multiple WordPress versions
- [ ] Test on multiple PHP versions
- [ ] Cross-browser admin UI testing
- [ ] Webhook integration testing
- [ ] Performance benchmarks

## Continuous Testing

**On Every Commit:**

- Linting (phpcs)
- Static analysis (phpstan)
- Code review

**Weekly:**

- Full regression suite
- Security audit
- Dependency updates

## Bug Reporting

**Required Information:**

- WordPress version
- PHP version
- Plugin version
- Error messages
- Debug logs
- Steps to reproduce
- Expected vs actual behavior

## Quality Metrics

**Target:**

- 0 critical bugs before release
- 0 security vulnerabilities
- 100% of critical paths tested manually
- All admin pages function correctly
- All user workflows complete successfully

## Future Enhancements

- Automated unit tests (PHPUnit)
- Integration test suite
- E2E testing (Playwright/Cypress)
- Load testing
- Continuous integration (GitHub Actions)
- Automated security scanning
- Performance benchmarking
- Visual regression testing (admin UI)
