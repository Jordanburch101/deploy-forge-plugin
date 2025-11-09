# Database Specification

**Last Updated:** 2025-11-09

## Overview

The plugin uses custom WordPress database tables to store deployment history and metadata. We use custom tables instead of post types for better performance and cleaner data modeling.

## Tables

### `{prefix}_github_deployments`

Primary table storing all deployment records.

#### Schema

```sql
CREATE TABLE {prefix}_github_deployments (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    commit_hash VARCHAR(40) NOT NULL,
    commit_message TEXT,
    commit_author VARCHAR(255),
    commit_date DATETIME,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    trigger_type VARCHAR(20) NOT NULL DEFAULT 'manual',
    triggered_by_user_id BIGINT(20) UNSIGNED,
    workflow_run_id BIGINT(20) UNSIGNED,
    build_url VARCHAR(500),
    build_logs LONGTEXT,
    deployment_logs LONGTEXT,
    error_message TEXT,
    backup_path VARCHAR(500),
    deployed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY commit_hash (commit_hash),
    KEY status (status),
    KEY trigger_type (trigger_type),
    KEY deployed_at (deployed_at),
    KEY created_at (created_at),
    KEY workflow_run_id (workflow_run_id)
) {charset_collate};
```

#### Column Definitions

| Column | Type | Null | Description |
|--------|------|------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | Auto-increment primary key |
| `commit_hash` | VARCHAR(40) | NO | Git commit SHA (40 chars) |
| `commit_message` | TEXT | YES | Commit message from Git |
| `commit_author` | VARCHAR(255) | YES | Git commit author name |
| `commit_date` | DATETIME | YES | Timestamp from Git commit |
| `status` | VARCHAR(20) | NO | Current deployment state |
| `trigger_type` | VARCHAR(20) | NO | How deployment was initiated |
| `triggered_by_user_id` | BIGINT(20) UNSIGNED | YES | WordPress user ID (0 for webhook) |
| `workflow_run_id` | BIGINT(20) UNSIGNED | YES | GitHub Actions run ID |
| `build_url` | VARCHAR(500) | YES | Link to GitHub Actions run |
| `build_logs` | LONGTEXT | YES | Output from GitHub Actions |
| `deployment_logs` | LONGTEXT | YES | Plugin deployment logs |
| `error_message` | TEXT | YES | Error details if failed |
| `backup_path` | VARCHAR(500) | YES | Path to backup ZIP |
| `deployed_at` | DATETIME | YES | Completion timestamp |
| `created_at` | DATETIME | NO | Record creation time |
| `updated_at` | DATETIME | NO | Last update time |

#### Status Values

| Status | Description | Terminal? |
|--------|-------------|-----------|
| `pending` | Deployment created, waiting to start | No |
| `building` | GitHub Actions workflow running | No |
| `success` | Deployment completed successfully | Yes |
| `failed` | Deployment or build failed | Yes |
| `cancelled` | User cancelled deployment | Yes |
| `rolled_back` | Deployment was rolled back | Yes |

#### Trigger Type Values

| Type | Description |
|------|-------------|
| `manual` | Triggered by admin from dashboard |
| `webhook` | Triggered by GitHub webhook (push event) |
| `auto` | Automatic deployment |

#### Indexes

- **PRIMARY KEY** (`id`) - Fast lookup by ID
- **KEY** (`commit_hash`) - Find deployments by commit
- **KEY** (`status`) - Filter by deployment state
- **KEY** (`trigger_type`) - Filter by trigger method
- **KEY** (`deployed_at`) - Sort by deployment time
- **KEY** (`created_at`) - Sort by creation time
- **KEY** (`workflow_run_id`) - Find by GitHub Actions run

## Database Operations

### Insert Deployment

```php
$deployment_id = $database->insert_deployment([
    'commit_hash' => $commit_hash,
    'commit_message' => $commit_message,
    'commit_author' => $commit_author,
    'commit_date' => $commit_date,
    'status' => 'pending',
    'trigger_type' => 'manual',
    'triggered_by_user_id' => get_current_user_id(),
]);
```

### Update Deployment

```php
$database->update_deployment($deployment_id, [
    'status' => 'building',
    'workflow_run_id' => $run_id,
    'build_url' => $html_url,
]);
```

### Get Deployment

```php
$deployment = $database->get_deployment($deployment_id);
```

### Get Recent Deployments

```php
$deployments = $database->get_recent_deployments($limit = 20);
```

### Get Pending Deployments

```php
// Get all deployments in 'pending' or 'building' status
$pending = $database->get_pending_deployments();
```

### Get Building Deployment

```php
// Get the current deployment in 'building' status (should be only one)
$building = $database->get_building_deployment();
```

### Delete Old Deployments

```php
// Clean up deployments older than retention period
$database->delete_old_deployments($retention_days = 30);
```

## Data Retention

### Automatic Cleanup

- Deployments older than configured retention period are automatically deleted
- Default retention: 30 days
- Configurable via settings

### Backup Cleanup

- Backup files are deleted with their deployment records
- Manual backups (from rollback) are preserved
- Disk space monitoring recommended

## Migration Strategy

### On Plugin Activation

```php
register_activation_hook(__FILE__, function() {
    $database = new GitHub_Deploy_Database();
    $database->create_tables();
});
```

### Version Upgrades

- Schema version stored in WordPress options
- Migration scripts run on version mismatch
- Backwards compatible changes preferred

## Performance Considerations

### Query Optimization

- Indexed columns for common queries
- LIMIT clauses on all list queries
- Prepared statements for security

### Data Size

- LONGTEXT for logs (4GB max per field)
- Automatic cleanup prevents unbounded growth
- Monitor for large log entries

### Concurrency

- Single building deployment enforced
- Row-level locking for updates
- Transaction support where needed

## Security

### SQL Injection Prevention

- All queries use `$wpdb->prepare()`
- Input sanitization on inserts
- Capability checks before queries

### Data Validation

- Status values validated against enum
- Trigger type values validated against enum
- Foreign key relationships checked

### Sensitive Data

- No tokens or secrets in database
- Logs sanitized before storage
- User IDs validated against WordPress users

## Backup and Recovery

### Plugin Backup

- Uses WordPress database backup tools
- Custom tables included in full backups
- Export functionality for deployment history

### Rollback Data

- Backup paths stored for rollback
- Filesystem and database must sync
- Orphaned backups cleaned up

## Monitoring

### Health Checks

- Table existence verification
- Schema version validation
- Disk space for backups

### Metrics

- Total deployments
- Success/failure rates
- Average deployment time
- Disk usage by backups

## Future Enhancements

- Multi-environment support (staging/production tables)
- Deployment analytics and reporting
- Audit log for all operations
- Deployment scheduling table
