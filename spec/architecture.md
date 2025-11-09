# System Architecture

**Last Updated:** 2025-11-09

## Overview

WordPress GitHub Auto-Deploy Plugin - A WordPress plugin that automates theme deployment from GitHub repositories using GitHub Actions.

## High-Level Architecture

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   GitHub    │────────▶│  WordPress   │────────▶│   Theme     │
│ Repository  │ webhook │    Plugin    │ deploy  │  Directory  │
└─────────────┘         └──────────────┘         └─────────────┘
       │                        │
       │                        │
       ▼                        ▼
┌─────────────┐         ┌──────────────┐
│   GitHub    │◀────────│   Database   │
│   Actions   │ trigger │  Deployments │
└─────────────┘         └──────────────┘
```

## Core Components

### 1. Main Plugin File
- **File:** `github-auto-deploy.php`
- **Purpose:** Plugin initialization, dependency injection
- **Responsibilities:**
  - Register activation/deactivation hooks
  - Initialize all plugin components
  - Load dependencies

### 2. GitHub API Wrapper
- **File:** `includes/class-github-api.php`
- **Purpose:** All GitHub REST API v3 interactions
- **Key Methods:**
  - `test_connection()` - Verify credentials and repository access
  - `trigger_workflow()` - Start GitHub Actions workflow
  - `get_workflow_run_status()` - Poll build status
  - `get_workflow_artifacts()` - List available artifacts
  - `download_artifact()` - Download build artifacts
  - `cancel_workflow_run()` - Cancel running workflow

### 3. Deployment Manager
- **File:** `includes/class-deployment-manager.php`
- **Purpose:** Orchestrates entire deployment workflow
- **Key Methods:**
  - `start_deployment()` - Initialize new deployment
  - `trigger_github_build()` - Trigger GitHub Actions
  - `check_build_status()` - Poll for completion
  - `download_and_deploy()` - Download and extract artifacts
  - `backup_current_theme()` - Create backup before deployment
  - `rollback_deployment()` - Restore from backup
  - `cancel_deployment()` - Cancel in-progress deployment

### 4. Webhook Handler
- **File:** `includes/class-webhook-handler.php`
- **Purpose:** Handle GitHub webhooks
- **Key Features:**
  - HMAC SHA-256 signature verification
  - Push event handling
  - Workflow run completion handling
  - Ping event support

### 5. Settings Manager
- **File:** `includes/class-settings.php`
- **Purpose:** Configuration and encrypted storage
- **Key Features:**
  - Encrypted token storage (sodium)
  - Repository configuration
  - Deployment preferences
  - Backup settings

### 6. Database Layer
- **File:** `includes/class-database.php`
- **Purpose:** Custom tables for deployment history
- **Tables:**
  - `{prefix}_github_deployments` - Deployment records

### 7. Admin Interface
- **File:** `admin/class-admin-pages.php`
- **Purpose:** WordPress admin UI
- **Pages:**
  - Dashboard - Status overview, manual deploy
  - Settings - Configuration
  - History - Deployment logs
  - Debug Logs - System diagnostics

### 8. Debug Logger
- **File:** `includes/class-debug-logger.php`
- **Purpose:** Detailed logging for troubleshooting
- **Features:**
  - Deployment step tracking
  - API request/response logging
  - Error logging

## Data Flow

### Automatic Deployment (Webhook)

1. Developer pushes to GitHub
2. GitHub sends webhook to WordPress REST endpoint
3. Webhook handler validates HMAC signature
4. Deployment record created in database
5. GitHub Actions workflow triggered
6. Plugin polls workflow status (WP_Cron)
7. On completion, download artifact
8. Create backup of current theme
9. Extract and deploy new theme files
10. Update deployment status to success

### Manual Deployment

1. Admin triggers deployment from dashboard
2. Deployment record created
3. GitHub Actions workflow triggered
4. Same as automatic flow from step 6 onwards

### Deployment Cancellation

1. Admin clicks cancel button
2. Plugin sends cancel request to GitHub API
3. Workflow run cancelled on GitHub
4. Deployment status updated to 'cancelled'

## File Structure

```
github-auto-deploy/
├── github-auto-deploy.php         # Main plugin file
├── includes/
│   ├── class-database.php         # Schema and queries
│   ├── class-github-api.php       # GitHub API wrapper
│   ├── class-deployment-manager.php  # Deployment orchestration
│   ├── class-webhook-handler.php  # Webhook endpoint
│   ├── class-settings.php         # Configuration
│   ├── class-debug-logger.php     # Logging system
│   └── class-github-app-connector.php # GitHub App integration
├── admin/
│   ├── class-admin-pages.php      # Admin UI
│   ├── css/admin-styles.css       # Custom styles
│   └── js/admin-scripts.js        # AJAX, UI interactions
└── templates/
    ├── settings-page.php          # Settings form
    ├── dashboard-page.php         # Status overview
    ├── history-page.php           # Deployment history
    └── logs-page.php              # Debug logs
```

## Communication Patterns

### Plugin ↔ GitHub

- **Protocol:** HTTPS
- **Method:** REST API v3
- **Authentication:** GitHub App installation tokens (via backend proxy)
- **Proxy:** https://deploy-forge.vercel.app
- **Rate Limiting:** Cached responses, exponential backoff

### GitHub ↔ Plugin (Webhooks)

- **Protocol:** HTTPS (required)
- **Method:** POST to REST endpoint
- **Authentication:** HMAC SHA-256 signature
- **Events:** push, workflow_run, ping

### Plugin ↔ WordPress

- **Database:** wpdb for custom tables
- **Options:** WordPress Options API
- **Cron:** WP_Cron for polling
- **REST API:** Custom endpoints for AJAX
- **Filesystem:** WP_Filesystem API (with native PHP fallback)

## Scalability Considerations

### Performance

- **Caching:** Transients API for GitHub API responses (2-5 min TTL)
- **Background Processing:** WP_Cron for async tasks
- **Timeouts:** Extended for large file operations (300s)

### Reliability

- **Backups:** Automatic backup before each deployment
- **Rollback:** One-click restore to previous version
- **Error Handling:** Comprehensive logging and error states
- **Atomic Operations:** Database transactions where applicable

### Security

- **Input Validation:** All user inputs sanitized
- **Output Escaping:** All outputs escaped
- **Capability Checks:** Admin-only access
- **Nonce Protection:** CSRF protection
- **Token Encryption:** Sodium crypto for secrets
- **Webhook Validation:** HMAC signature required

## Extension Points

### Action Hooks

- `github_deploy_completed` - After successful deployment
- `github_deploy_rolled_back` - After rollback
- `github_deploy_cancelled` - After cancellation
- `github_deploy_webhook_received` - On webhook receipt

### Filter Hooks

- `github_deploy_temp_directory` - Customize temp directory
- `github_deploy_backup_directory` - Customize backup location
- `cron_schedules` - Add custom polling intervals

## Dependencies

### WordPress Core

- Minimum: 5.8+
- PHP: 7.4+ (8.0+ recommended)
- MySQL: 5.7+ or MariaDB 10.2+

### PHP Extensions

- **Required:**
  - `sodium` - Token encryption
  - `zip` - Artifact extraction
  - `json` - API communication
  - `curl` - HTTP requests (via wp_remote_request)

### External Services

- **GitHub:**
  - GitHub App (for authentication)
  - GitHub Actions (for builds)
  - GitHub REST API v3

- **Backend Proxy:**
  - deploy-forge.vercel.app
  - Manages GitHub App tokens
  - Proxies API requests

## Deployment States

```
pending → building → success
                   ↘ failed
                   ↘ cancelled
                   ↘ rolled_back
```

## Future Enhancements

See `spec/CHANGELOG.md` for planned features and changes.
