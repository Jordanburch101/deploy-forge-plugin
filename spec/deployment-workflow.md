# Deployment Workflow Specification

**Last Updated:** 2025-11-09

## Overview

This document describes the complete deployment workflow from commit to deployed theme, including all states, transitions, and error handling.

## Deployment Lifecycle

### State Diagram

```
                    ┌──────────┐
                    │ PENDING  │
                    └────┬─────┘
                         │
                    trigger workflow
                         │
                         ▼
                    ┌──────────┐      cancel      ┌───────────┐
              ┌────▶│ BUILDING │─────────────────▶│ CANCELLED │
              │     └────┬─────┘                   └───────────┘
              │          │
         find run        │ workflow completes
              │          │
              │          ▼
              │     ┌──────────┐
              │     │  Check   │
              │     │Conclusion│
              │     └────┬─────┘
              │          │
              │     ┌────┴────┐
              │     │         │
              │  success    failure
              │     │         │
              │     ▼         ▼
              │ ┌────────┐ ┌────────┐
              │ │SUCCESS │ │ FAILED │
              │ └───┬────┘ └────────┘
              │     │
              │  rollback
              │     │
              │     ▼
              │ ┌──────────────┐
              └─│ ROLLED_BACK  │
                └──────────────┘
```

### State Definitions

| State | Description | Next States | Terminal |
|-------|-------------|-------------|----------|
| `pending` | Deployment created, workflow not yet triggered | building, failed | No |
| `building` | GitHub Actions workflow running | success, failed, cancelled | No |
| `success` | Theme deployed successfully | rolled_back | Yes |
| `failed` | Build or deployment failed | - | Yes |
| `cancelled` | User cancelled deployment | - | Yes |
| `rolled_back` | Previous deployment restored | - | Yes |

## Workflow Types

### 1. Automatic Deployment (Webhook)

**Trigger:** GitHub push event

**Flow:**
```
1. Developer pushes to GitHub (main branch)
2. GitHub sends webhook to WordPress
3. Webhook handler validates signature
4. Checks auto_deploy_enabled setting
5. Checks if branch matches configured branch
6. Extracts commit information
7. Creates deployment record (status: pending)
8. Triggers GitHub Actions workflow
9. Updates status to building
10. WP_Cron polls for completion (every minute)
11. On completion, downloads artifact
12. Creates backup (if enabled)
13. Deploys theme files
14. Updates status to success
```

**Prerequisites:**
- ✅ Auto-deploy enabled in settings
- ✅ Webhook secret configured
- ✅ Valid webhook signature
- ✅ Push to configured branch
- ✅ No deployment currently building

### 2. Manual Deployment

**Trigger:** Admin clicks "Deploy Now" button

**Flow:**
```
1. Admin selects commit from dashboard
2. JavaScript sends AJAX request
3. Nonce and capability verified
4. Checks for building deployment
5. Creates deployment record
6. Triggers GitHub Actions workflow
7. UI polls for status updates
8. Same as automatic from step 10 onwards
```

**Prerequisites:**
- ✅ User has `manage_options` capability
- ✅ Valid nonce
- ✅ No deployment currently building
- ✅ Valid commit hash

### 3. Deployment Cancellation

**Trigger:** Admin clicks "Cancel" button

**Flow:**
```
1. Admin clicks cancel on building deployment
2. JavaScript sends cancel request
3. Plugin sends cancel to GitHub API
4. GitHub attempts to cancel workflow run
5. Deployment status updated to cancelled
6. No files are deployed
```

**Allowed States:**
- `pending` - Before workflow starts
- `building` - While workflow is running

**Not Allowed:**
- `success` - Already completed
- `failed` - Already failed
- `cancelled` - Already cancelled

## Detailed Workflow Steps

### Step 1: Create Deployment Record

```php
$deployment_id = $database->insert_deployment([
    'commit_hash' => $commit_hash,
    'commit_message' => $commit_message,
    'commit_author' => $commit_author,
    'commit_date' => $commit_date,
    'status' => 'pending',
    'trigger_type' => 'manual', // or 'webhook'
    'triggered_by_user_id' => get_current_user_id(),
]);
```

**Logged:**
- Deployment ID
- Trigger type
- User ID (0 for webhooks)
- Commit details

### Step 2: Check for Existing Building Deployment

```php
$building = $database->get_building_deployment();

if ($building) {
    if ($trigger_type === 'manual') {
        // Block and return error
        return ['error' => 'deployment_in_progress'];
    } else {
        // Auto-cancel existing and continue
        $this->cancel_deployment($building->id);
    }
}
```

**Rationale:**
- Only one deployment can build at a time
- Manual deploys require explicit cancellation
- Webhooks auto-cancel to prevent queue buildup

### Step 3: Trigger GitHub Actions

```php
$result = $github_api->trigger_workflow($workflow_name, $branch);

if ($result['success']) {
    $database->update_deployment($deployment_id, [
        'status' => 'building',
    ]);
}
```

**GitHub API:**
- `POST /repos/{owner}/{repo}/actions/workflows/{workflow}/dispatches`
- Returns `204 No Content` on success

**Logs:**
- Workflow name
- Branch
- API response

### Step 4: Find Workflow Run ID

**Why Needed:** GitHub's dispatch endpoint doesn't return run ID

**Strategy:**
```php
// Poll recent workflow runs
$runs = $github_api->get_latest_workflow_runs(10);

foreach ($runs as $run) {
    if ($run->head_sha === $commit_hash) {
        $database->update_deployment($deployment_id, [
            'workflow_run_id' => $run->id,
            'build_url' => $run->html_url,
        ]);
        break;
    }
}
```

**Timing:**
- First check: Immediately after trigger
- WP_Cron: Every minute until found
- Timeout: 10 minutes (configurable)

### Step 5: Poll Build Status

**WP_Cron Job:** `github_deploy_check_deployments` (runs every minute)

```php
public function check_pending_deployments() {
    $pending = $database->get_pending_deployments();

    foreach ($pending as $deployment) {
        if ($deployment->status === 'building') {
            $this->check_build_status($deployment->id);
        }
    }
}
```

**API Call:**
```php
$run = $github_api->get_workflow_run_status($workflow_run_id);

if ($run->status === 'completed') {
    if ($run->conclusion === 'success') {
        $this->process_successful_build($deployment_id);
    } else {
        $this->mark_failed($deployment_id, $run->conclusion);
    }
}
```

**Logged:**
- Workflow status (queued, in_progress, completed)
- Conclusion (success, failure, cancelled, skipped)
- Build URL

### Step 6: Download Artifacts

```php
$artifacts = $github_api->get_workflow_artifacts($workflow_run_id);

if (empty($artifacts)) {
    $this->mark_failed($deployment_id, 'No artifacts found');
    return;
}

$artifact = $artifacts[0]; // Assuming single artifact
$temp_zip = sys_get_temp_dir() . '/artifact-' . $deployment_id . '.zip';

$download_result = $github_api->download_artifact(
    $artifact->id,
    $temp_zip
);
```

**Validation:**
- Artifact exists
- Download successful
- File size > 0
- Valid ZIP format

### Step 7: Create Backup

```php
if ($settings->get('create_backups')) {
    $backup_path = $deployment_manager->backup_current_theme($deployment_id);

    if ($backup_path) {
        $database->update_deployment($deployment_id, [
            'backup_path' => $backup_path,
        ]);
    }
}
```

**Backup Details:**
- Format: ZIP archive
- Location: Configured backup directory
- Naming: `backup-{deployment_id}-{timestamp}.zip`
- Contents: Entire current theme directory

### Step 8: Extract Artifact

```php
$zip = new ZipArchive();
$zip->open($artifact_zip);
$zip->extractTo($temp_extract_dir);
$zip->close();

// Check for double-zipped artifacts (GitHub Actions behavior)
$files = scandir($temp_extract_dir);
if (count($files) === 3 && pathinfo($files[2], PATHINFO_EXTENSION) === 'zip') {
    // Extract inner ZIP
    $inner_zip = new ZipArchive();
    $inner_zip->open($temp_extract_dir . '/' . $files[2]);
    $inner_zip->extractTo($final_extract_dir);
    $inner_zip->close();
}
```

**Handles:**
- Single ZIP
- Double-zipped artifacts (GitHub Actions uploads)
- Nested directory structures

### Step 9: Deploy Files

```php
$themes_dir = WP_CONTENT_DIR . '/themes';

// Copy entire extracted structure
$this->recursive_copy($temp_extract_dir, $themes_dir);

// Set correct permissions
chmod($themes_dir . '/' . $theme_name, 0755);
```

**File Operations:**
- Recursive copy
- Preserve directory structure
- Set file permissions (0644)
- Set directory permissions (0755)

### Step 10: Update Status

```php
$database->update_deployment($deployment_id, [
    'status' => 'success',
    'deployed_at' => current_time('mysql'),
]);

do_action('github_deploy_completed', $deployment_id);
```

**Cleanup:**
- Delete temporary files
- Delete extracted directories
- (Optional) Delete old backups

## Error Handling

### Build Failures

**Causes:**
- Code errors (syntax, runtime)
- Failed tests
- Missing dependencies
- Workflow configuration errors

**Plugin Action:**
```php
$database->update_deployment($deployment_id, [
    'status' => 'failed',
    'error_message' => "Build failed: {$conclusion}",
]);
```

**User Notification:**
- Dashboard shows failed status
- Red error message
- Link to build logs on GitHub
- Option to retry deployment

### Download Failures

**Causes:**
- Network timeout
- Invalid artifact ID
- Expired artifact (30 days retention)
- Insufficient disk space

**Recovery:**
- Retry with exponential backoff (up to 4 times)
- Log detailed error
- Mark deployment as failed
- Preserve backup if created

### Deployment Failures

**Causes:**
- Insufficient permissions
- Disk space full
- Corrupted ZIP file
- Theme directory locked

**Recovery:**
- Automatic rollback to backup (if exists)
- Log error details
- Notify admin
- Cleanup partial deployment

## Rollback Procedure

**Trigger:** Admin clicks "Rollback" on successful deployment

**Requirements:**
- Deployment has `backup_path`
- Backup file exists
- User has `manage_options` capability

**Process:**
```php
1. Verify backup exists
2. Extract backup to temp directory
3. Copy backup files to theme directory
4. Update deployment status to 'rolled_back'
5. Fire 'github_deploy_rolled_back' action
```

**Limitations:**
- Can only rollback if backup exists
- Rollback is one-way (no "undo rollback")
- Database changes not rolled back

## Concurrency Control

### Single Building Deployment

**Enforced:**
```php
if ($building_deployment = $database->get_building_deployment()) {
    // Only one deployment can be building
    if ($trigger_type === 'manual') {
        return error('Deployment already in progress');
    } else {
        // Auto-cancel for webhooks
        $this->cancel_deployment($building_deployment->id);
    }
}
```

**Race Condition Protection:**
- Database-level checks
- Transaction support (where applicable)
- Last-write-wins for status updates

## Performance Considerations

### Timeout Handling

```php
// Increase for large files
@set_time_limit(300); // 5 minutes

// Chunk large operations
// Use streaming for downloads
'stream' => true,
'filename' => $destination,
```

### Memory Limits

```php
// Monitor during extraction
if (memory_get_usage() > ini_get('memory_limit') * 0.8) {
    // Log warning
}
```

### Background Processing

**WP_Cron:**
- Runs every minute
- Non-blocking
- Handles multiple pending deployments
- Timeout: 2 minutes per cron run

## Monitoring & Observability

### Deployment Metrics

**Tracked:**
- Total deployments
- Success rate
- Average deployment time
- Build time vs download time
- Most common failure reasons

### Logging

**Every Step Logged:**
```php
$logger->log_deployment_step($deployment_id, 'Step Name', 'status', [
    'context' => 'data',
]);
```

**Levels:**
- INFO - Normal operation
- WARNING - Recoverable issues
- ERROR - Failures

### Debugging

**Debug Logs Page:**
- Real-time log streaming
- Filter by deployment ID
- Filter by log level
- Export to file

## Future Enhancements

- Parallel deployments (multiple themes)
- Progressive deployment (staged rollout)
- Blue-green deployment strategy
- Deployment scheduling
- Pre-deployment health checks
- Post-deployment smoke tests
- Deployment approval workflow
- Multi-environment support
