# Testing Guide: Asynchronous Deployment Processing

## Overview

This guide will help you test the new asynchronous deployment processing feature that prevents GitHub webhook timeouts.

---

## What Changed

### Before (Synchronous)
```
Webhook → Validate → Download (30s) → Extract (20s) → Deploy (60s) → Return 200
Total: 110+ seconds ❌ (GitHub timeout after 10s)
```

### After (Asynchronous)
```
Webhook → Validate → Queue → Return 200 (< 500ms) ✅
                         ↓
              Background: Download → Extract → Deploy
```

---

## Installation

1. **Upload Plugin**
   ```bash
   # The plugin ZIP is located at:
   dist/deploy-forge-1.0.0.zip
   ```

2. **Install in WordPress**
   - Go to: `Plugins → Add New → Upload Plugin`
   - Upload: `deploy-forge-1.0.0.zip`
   - Click: `Activate`

3. **Verify Installation**
   - Check for "Deploy Forge" in admin menu
   - Navigate to `Deploy Forge → Settings`

---

## Testing Scenarios

### Test 1: Verify New Database Schema

**Objective:** Ensure new statuses are supported

**Steps:**
1. After activation, check the database
2. Run this SQL query:
   ```sql
   SELECT * FROM wp_github_deployments LIMIT 1;
   ```
3. **Expected:** The `status` column should accept: `pending`, `building`, `queued`, `deploying`, `success`, `failed`

**✓ Pass Criteria:** No database errors

---

### Test 2: FastCGI Async Processing (PHP-FPM)

**Objective:** Test immediate response with FastCGI

**Requirements:**
- PHP-FPM environment
- `fastcgi_finish_request()` function available

**Steps:**

1. **Check if FastCGI is available:**
   ```php
   <?php
   var_dump(function_exists('fastcgi_finish_request'));
   // Should output: bool(true)
   ```

2. **Set up GitHub webhook**
   - Go to GitHub repo → Settings → Webhooks
   - Add webhook URL: `https://yoursite.com/wp-json/deploy-forge/v1/webhook`
   - Content type: `application/json`
   - Secret: (copy from Deploy Forge settings)
   - Events: Select "Workflow runs"

3. **Trigger deployment**
   - Push a commit to your repository
   - This triggers GitHub Actions workflow

4. **Monitor webhook response time**
   - Go to GitHub → Settings → Webhooks → Recent Deliveries
   - Click on the delivery
   - Check "Response" section

**✓ Pass Criteria:**
- Response time: **< 500ms** (shown in GitHub webhook delivery)
- Response body: `{"success":true,"message":"Deployment queued","deployment_id":X}`
- Deployment status transitions: `building → queued → deploying → success`

**Debug Logs:**
Check Deploy Forge logs for:
```
[Webhook] Using fastcgi_finish_request for async processing
[Webhook] Processing deployment #X after early response
```

---

### Test 3: WP-Cron Fallback (Non-FastCGI)

**Objective:** Test WP-Cron processing when FastCGI unavailable

**Requirements:**
- Non-PHP-FPM environment (e.g., Apache with mod_php)
- OR temporarily disable FastCGI for testing

**Steps:**

1. **Verify WP-Cron is enabled:**
   ```php
   // In wp-config.php, ensure this is NOT set:
   // define('DISABLE_WP_CRON', true);
   ```

2. **Trigger deployment** (same as Test 2, step 3)

3. **Check deployment status**
   - Go to: `Deploy Forge → Dashboard`
   - Observe status: Should show `queued` immediately
   - Wait 30-60 seconds
   - Refresh page: Status should change to `deploying` then `success`

**✓ Pass Criteria:**
- Webhook responds in **< 1 second**
- Deployment shows `queued` status
- WP-Cron processes within 60 seconds
- Status transitions: `queued → deploying → success`

**Debug Logs:**
Check for:
```
[Webhook] fastcgi_finish_request not available, using WP-Cron fallback
[Deployment] Deployment #X skipped - another deployment (#Y) is processing (if concurrent)
```

**Trigger WP-Cron Manually (if needed):**
```bash
curl https://yoursite.com/wp-cron.php
```

---

### Test 4: Deployment Queue Locking

**Objective:** Ensure concurrent deployments don't conflict

**Steps:**

1. **Trigger first deployment**
   - Push commit A to GitHub
   - Verify status: `queued`

2. **Immediately trigger second deployment** (within 30 seconds)
   - Push commit B to GitHub
   - Verify status: `queued`

3. **Observe processing**
   - First deployment should process immediately: `queued → deploying → success`
   - Second deployment should wait, then process: `queued → (wait) → deploying → success`

**✓ Pass Criteria:**
- Only ONE deployment shows `deploying` at a time
- Second deployment reschedules automatically
- Both deployments complete successfully
- No file conflicts or corruption

**Debug Logs:**
```
[Deployment] Deployment #2 skipped - another deployment (#1) is processing
[Deployment] Deployment #1 complete, lock released
[Deployment] Deployment #2 now processing
```

---

### Test 5: Error Handling & Lock Release

**Objective:** Ensure locks are released on failure

**Steps:**

1. **Trigger deployment that will fail**
   - Remove artifacts from GitHub Actions workflow (intentionally break it)
   - OR delete the theme directory on server (create permission error)

2. **Push commit to trigger deployment**

3. **Verify failure handling**
   - Check deployment status: Should show `failed`
   - Check error message: Should show clear error

4. **Trigger another deployment**
   - Fix the issue
   - Push new commit
   - Verify it processes (not blocked by previous lock)

**✓ Pass Criteria:**
- Failed deployment shows `failed` status with error message
- Lock is released (second deployment can start)
- No "stuck" deployments

---

### Test 6: Status Display & Auto-Refresh

**Objective:** Test UI updates for new statuses

**Steps:**

1. **Open dashboard in browser**
   - Navigate to: `Deploy Forge → Dashboard`

2. **Trigger deployment**
   - Push commit to GitHub

3. **Watch status changes** (without manually refreshing)
   - Should auto-refresh every 30 seconds
   - Status badges should update:
     - `building` → blue badge
     - `queued` → purple badge
     - `deploying` → light blue badge with pulse animation
     - `success` → green badge

**✓ Pass Criteria:**
- Status badges display correctly
- Colors match new statuses:
  - **Queued:** Purple background (#e3d4fc)
  - **Deploying:** Light blue with pulse animation (#c4e0ff)
- Auto-refresh works (page updates without manual refresh)

---

### Test 7: Manual Deployment

**Objective:** Ensure manual "Deploy Now" still works

**Steps:**

1. **Click "Deploy Now" button**
   - Go to: `Deploy Forge → Dashboard`
   - Click: `Deploy Now`

2. **Verify behavior**
   - Deployment should trigger GitHub Actions
   - Status should show: `pending → building → queued → deploying → success`

**✓ Pass Criteria:**
- Manual deployment works
- Same async processing applies
- No errors or timeouts

---

## Verification Checklist

After testing, verify the following:

- [ ] **Webhook response time < 500ms** (GitHub webhook delivery page)
- [ ] **New statuses visible:** `queued`, `deploying`
- [ ] **Status transitions work:** `building → queued → deploying → success`
- [ ] **Deployment lock prevents concurrency**
- [ ] **Lock released on success**
- [ ] **Lock released on failure**
- [ ] **WP-Cron fallback works** (non-FastCGI)
- [ ] **FastCGI processing works** (PHP-FPM)
- [ ] **UI auto-refresh detects new statuses**
- [ ] **CSS styling shows correctly** (purple queued, blue deploying with pulse)
- [ ] **Manual deployment still works**
- [ ] **No webhook timeout errors in GitHub**

---

## Debugging Tools

### Check Deployment Status
```php
// In WordPress admin, use this in a test page:
<?php
global $wpdb;
$deployments = $wpdb->get_results("
    SELECT id, status, created_at, updated_at
    FROM {$wpdb->prefix}github_deployments
    ORDER BY id DESC LIMIT 5
");
print_r($deployments);
```

### Check Deployment Lock
```php
<?php
$lock = get_transient('deploy_forge_processing_lock');
echo "Lock: " . ($lock ? "Deployment #$lock" : "No lock");
```

### Check WP-Cron Jobs
```bash
wp cron event list --allow-root
```

### Force Process Queued Deployment
```php
<?php
// Manually trigger processing
do_action('deploy_forge_process_queued_deployment', $deployment_id);
```

---

## Expected Performance

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Webhook response time | 30-150s | < 500ms | **99.7% faster** |
| GitHub timeout errors | Frequent | None | **100% resolved** |
| Deployment accuracy | Unreliable | Accurate | **100% reliable** |
| Concurrent deployment handling | Conflicts | Queued | **Safe** |

---

## Troubleshooting

### Issue: Webhook still timing out

**Cause:** PHP execution time limits

**Solution:**
```php
// In webhook-handler.php, verify:
@set_time_limit(300);
@ignore_user_abort(true);
```

### Issue: Deployment stuck in "queued"

**Cause:** WP-Cron not running

**Solution:**
```bash
# Manually trigger WP-Cron
curl https://yoursite.com/wp-cron.php

# Or set up system cron:
*/5 * * * * curl https://yoursite.com/wp-cron.php > /dev/null 2>&1
```

### Issue: "Lock" prevents all deployments

**Cause:** Lock not released (server crash during deployment)

**Solution:**
```php
// Manually release lock in WordPress admin or via SSH:
delete_transient('deploy_forge_processing_lock');
```

---

## Success Indicators

When testing is successful, you should see:

1. **GitHub Webhook Deliveries:**
   - ✅ All deliveries show HTTP 200
   - ✅ Response time < 500ms
   - ✅ No timeout errors

2. **Deploy Forge Dashboard:**
   - ✅ Status transitions smoothly: `queued → deploying → success`
   - ✅ Purple "queued" badge
   - ✅ Blue pulsing "deploying" badge
   - ✅ Green "success" badge

3. **Deployment Logs:**
   - ✅ "Deployment queued for processing"
   - ✅ "Using fastcgi_finish_request" OR "Using WP-Cron fallback"
   - ✅ "Lock acquired"
   - ✅ "Lock released"

4. **No Errors:**
   - ✅ No PHP errors in logs
   - ✅ No webhook timeout errors
   - ✅ No stuck deployments

---

## Reporting Issues

If you encounter issues during testing, please provide:

1. **Environment info:**
   - PHP version
   - Web server (Apache/Nginx)
   - PHP SAPI (FPM/mod_php/CGI)
   - WordPress version

2. **Error details:**
   - Deployment ID
   - Status shown
   - Error messages from logs
   - GitHub webhook delivery response

3. **Reproduction steps:**
   - What you did
   - What you expected
   - What actually happened

---

## Next Steps After Testing

Once testing is complete and successful:

1. **Production deployment:** Upload to live site
2. **Monitor webhooks:** Watch GitHub webhook deliveries for 24 hours
3. **Verify logs:** Check deployment logs for any issues
4. **Update documentation:** Document any environment-specific findings
5. **Celebrate!** 🎉 No more webhook timeouts!

---

**Happy Testing!** 🚀
