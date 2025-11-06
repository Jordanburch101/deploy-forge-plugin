# GitHub Webhook Setup Guide

## Overview

This plugin uses GitHub webhooks to receive instant notifications when:
1. **Push events** - Code is pushed to your repository (triggers deployment)
2. **Workflow run events** - GitHub Actions workflow completes (triggers immediate deployment, eliminating polling delays)

## Why Use Webhooks?

**Without webhooks** (polling only):
- Plugin checks every 60 seconds via WP_Cron
- 30-second build can take 3-5 minutes to deploy
- Depends on WordPress site traffic (WP_Cron is pseudo-cron)

**With webhooks** (recommended):
- GitHub notifies WordPress immediately when workflow completes
- 30-second build deploys in ~30 seconds
- No polling delays, no waiting
- More reliable and efficient

## Setup Instructions

### Step 1: Get Your Webhook URL

1. Log in to your WordPress admin dashboard
2. Navigate to **GitHub Deploy → Settings**
3. Scroll to the "Webhook Configuration" section
4. Copy the **Webhook URL** (should look like: `https://yoursite.com/wp-json/github-deploy/v1/webhook`)

### Step 2: Create Webhook Secret (Recommended)

1. In WordPress **GitHub Deploy → Settings**
2. Click **"Generate Secret"** button in the Webhook Configuration section
3. Copy the generated secret
4. Click **"Save Settings"** at the bottom of the page

> **Security Note:** The webhook secret ensures only GitHub can trigger deployments. While optional, it's **strongly recommended** for production sites.

### Step 3: Configure GitHub Webhook

1. Go to your GitHub repository
2. Click **Settings** (repository settings, not your profile)
3. Click **Webhooks** in the left sidebar
4. Click **Add webhook** button
5. Configure the webhook:

#### Payload URL
```
https://yoursite.com/wp-json/github-deploy/v1/webhook
```
(Use the URL from Step 1)

#### Content type
```
application/json
```

#### Secret
Paste the secret you generated in Step 2 (or leave blank if you didn't create one)

#### SSL verification
```
✓ Enable SSL verification (recommended)
```

#### Which events would you like to trigger this webhook?

Select: **"Let me select individual events"**

Then check these two events:
- ✅ **Pushes** - Triggers deployment when code is pushed
- ✅ **Workflow runs** - Receives workflow completion notifications (instant deployment!)

Uncheck all other events.

#### Active
```
✓ Active
```

6. Click **Add webhook**

### Step 4: Test the Webhook

#### Test 1: Ping Event
1. After creating the webhook, GitHub will send a test "ping" event
2. Click on the webhook you just created
3. Scroll down to "Recent Deliveries"
4. You should see a ping event with a green checkmark ✓
5. Click on it to see the response (should say "Webhook ping received successfully!")

#### Test 2: Manual Deployment
1. In WordPress, go to **GitHub Deploy → Dashboard**
2. Click **Deploy Now**
3. Watch the deployment status change from "Pending" → "Building" → "Success"
4. This should take ~30-40 seconds (build time + download time)

#### Test 3: Push Event (if auto-deploy enabled)
1. Make a small change to your repository (e.g., update README)
2. Commit and push to the configured branch
3. In GitHub, go to **Settings → Webhooks → [Your Webhook] → Recent Deliveries**
4. You should see two webhook deliveries:
   - **push** event (triggers the deployment)
   - **workflow_run** event (triggers immediate deployment when build completes)
5. Check WordPress **GitHub Deploy → Dashboard** - deployment should complete in ~30-40 seconds

## Webhook Events Explained

### Push Event
- **When:** Code is pushed to the repository
- **Purpose:** Triggers a new deployment (if auto-deploy is enabled)
- **Payload includes:** Commit hash, message, author, branch
- **What happens:** Plugin creates deployment record and triggers GitHub Actions workflow

### Workflow Run Event (NEW!)
- **When:** GitHub Actions workflow completes (success, failure, or cancelled)
- **Purpose:** Immediately processes the completed build without polling
- **Payload includes:** Workflow run ID, status, conclusion, commit SHA
- **What happens:**
  - **On success:** Immediately downloads artifact and deploys theme files
  - **On failure:** Marks deployment as failed with error message
  - **On cancellation:** Marks deployment as cancelled

## Troubleshooting

### Webhook shows error (red X)

**Check the error message:**
1. Go to GitHub → Settings → Webhooks → [Your Webhook]
2. Click on the failed delivery under "Recent Deliveries"
3. Check the "Response" section

**Common issues:**

#### 401 Unauthorized - "Invalid webhook signature"
- Webhook secret in GitHub doesn't match WordPress
- **Solution:** Copy secret from WordPress settings and paste into GitHub webhook

#### 404 Not Found
- Webhook URL is incorrect
- **Solution:** Verify the URL matches exactly: `https://yoursite.com/wp-json/github-deploy/v1/webhook`
- Ensure WordPress permalinks are enabled (not using "Plain" permalinks)

#### 500 Internal Server Error
- PHP error in WordPress
- **Solution:** 
  - Enable debug mode in WordPress settings
  - Check error logs at **GitHub Deploy → Debug Logs**
  - Check PHP error logs on your server

#### Connection timeout
- Your server is blocking GitHub's IP addresses
- **Solution:** Check your firewall/security settings

### Deployment still takes 3-5 minutes

**Possible causes:**

1. **Workflow run webhook not configured**
   - Go to GitHub → Settings → Webhooks → [Your Webhook]
   - Click "Edit"
   - Verify "Workflow runs" is checked under events
   - Click "Update webhook"

2. **Webhook deliveries failing**
   - Check "Recent Deliveries" for errors
   - Fix any errors (see above)

3. **WordPress cron disabled**
   - Webhook triggers immediate deployment, but WP_Cron is still needed as fallback
   - Check if `DISABLE_WP_CRON` is set to `true` in `wp-config.php`

4. **Debug logs**
   - Go to **GitHub Deploy → Debug Logs**
   - Enable debug mode in Settings if not already enabled
   - Look for webhook events and timing

### Webhook receives event but nothing happens

1. **Check deployment status:**
   - Go to **GitHub Deploy → History**
   - Look for deployments with matching commit hash
   - Check deployment logs/error messages

2. **Verify webhook signature:**
   - If webhook secret is set, signature must match
   - Try removing the secret temporarily to test

3. **Check branch configuration:**
   - Plugin only deploys from configured branch
   - Verify you're pushing to the correct branch

### WordPress returns "Unsupported event type"

- Your webhook is sending events the plugin doesn't handle
- **Solution:** Only select "Pushes" and "Workflow runs" events in GitHub webhook settings

## Advanced Configuration

### Rate Limiting

The webhook endpoint has built-in rate limiting to prevent abuse. If you're hitting rate limits:
- Check GitHub's "Recent Deliveries" for duplicate/excessive webhooks
- Verify you're only receiving push and workflow_run events

### Multiple Environments

If you have staging and production environments:
1. Use different webhook secrets for each environment
2. Create separate webhooks pointing to each environment's URL
3. Configure branch-specific webhooks (e.g., `main` → production, `staging` → staging)

### Webhook vs Polling

Even with webhooks configured, the plugin maintains WP_Cron polling as a fallback:
- **With webhooks:** Deployment completes in ~30-40 seconds
- **Without webhooks (polling only):** Deployment takes 3-5 minutes
- **If webhook fails:** Polling will catch it within 60 seconds

The webhook provides instant notifications, while polling ensures deployments don't get stuck if webhooks fail.

## Security Best Practices

1. ✅ **Always use HTTPS** - GitHub won't send webhooks to HTTP URLs
2. ✅ **Use webhook secrets** - Prevents unauthorized deployment triggers
3. ✅ **Restrict events** - Only enable "Pushes" and "Workflow runs"
4. ✅ **Monitor webhook deliveries** - Check for suspicious patterns
5. ✅ **Keep WordPress updated** - Security patches and improvements
6. ✅ **Use strong GitHub tokens** - With minimal required permissions

## Webhook Payload Examples

### Push Event Payload (Partial)
```json
{
  "ref": "refs/heads/main",
  "head_commit": {
    "id": "abc123...",
    "message": "Update homepage layout",
    "author": {
      "name": "John Doe"
    },
    "timestamp": "2024-01-15T10:30:00Z"
  }
}
```

### Workflow Run Event Payload (Partial)
```json
{
  "action": "completed",
  "workflow_run": {
    "id": 123456789,
    "status": "completed",
    "conclusion": "success",
    "head_sha": "abc123...",
    "html_url": "https://github.com/user/repo/actions/runs/123456789"
  }
}
```

## Need Help?

1. Check **GitHub Deploy → Debug Logs** for detailed information
2. Enable debug mode in plugin settings for verbose logging
3. Check GitHub's webhook delivery logs for HTTP errors
4. Review deployment history for error messages
5. Verify all setup steps were completed correctly

## Related Documentation

- [GitHub Token Guide](GITHUB-TOKEN-GUIDE.md) - How to create and configure GitHub tokens
- [Deployment Guide](DEPLOYMENT.md) - How to prepare your repository for deployment
- [Cancel Deployment Feature](CANCEL-DEPLOYMENT-FEATURE.md) - How to cancel in-progress deployments

