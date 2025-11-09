# Requirements Specification

**Last Updated:** 2025-11-09

## Overview

This document defines all system requirements, both functional and non-functional, for the WordPress GitHub Auto-Deploy plugin.

## Functional Requirements

### FR001: GitHub Repository Connection

**Priority:** Critical

**Description:** Users must be able to connect the plugin to a GitHub repository using GitHub App authentication.

**Acceptance Criteria:**
- User can initiate OAuth flow from settings page
- API key is stored encrypted
- Repository can be selected from user's accessible repositories
- Branch can be selected from repository branches
- Connection can be tested and validated
- Connection can be disconnected/reset

**Dependencies:**
- GitHub App configured
- Backend proxy service running
- HTTPS enabled (for OAuth callback)

### FR002: GitHub Actions Workflow Configuration

**Priority:** Critical

**Description:** Users must be able to select which GitHub Actions workflow to trigger for deployments.

**Acceptance Criteria:**
- List all active workflows from repository
- Filter workflows appropriately
- Save selected workflow
- Workflow name persists across sessions
- Can change workflow at any time

**Dependencies:**
- FR001 (GitHub connection)
- Repository has workflows configured

### FR003: Manual Deployment

**Priority:** Critical

**Description:** Administrators must be able to manually trigger deployments from the WordPress admin dashboard.

**Acceptance Criteria:**
- Can view list of recent commits
- Can select specific commit to deploy
- Deployment starts immediately when triggered
- Real-time status updates during deployment
- Success/failure notification displayed
- Link to GitHub Actions build logs

**Dependencies:**
- FR001, FR002
- User has `manage_options` capability

### FR004: Automatic Deployment via Webhooks

**Priority:** Critical

**Description:** Deployments should automatically trigger when code is pushed to the configured branch.

**Acceptance Criteria:**
- Webhook endpoint available at `/wp-json/github-deploy/v1/webhook`
- HMAC SHA-256 signature verification required
- Push events trigger deployment
- workflow_run events update deployment status
- Only configured branch triggers deployment
- Auto-deploy can be enabled/disabled in settings

**Dependencies:**
- FR001, FR002
- Webhook configured on GitHub
- Webhook secret configured
- HTTPS enabled

### FR005: Deployment Status Tracking

**Priority:** Critical

**Description:** System must track and display deployment status throughout the lifecycle.

**Acceptance Criteria:**
- States: pending, building, success, failed, cancelled, rolled_back
- Real-time status updates
- Progress indicators where applicable
- Detailed deployment logs
- Build logs from GitHub Actions
- Error messages for failures

**Dependencies:**
- FR003 or FR004

### FR006: Automatic Backups

**Priority:** High

**Description:** System should create backups of the current theme before deploying.

**Acceptance Criteria:**
- Backup created before each deployment
- Backup stored as ZIP file
- Backup location configurable
- Backup linked to deployment record
- Backups can be automatically cleaned up
- Option to disable backups

**Dependencies:**
- FR003 or FR004
- Sufficient disk space

### FR007: Rollback Capability

**Priority:** High

**Description:** Users must be able to rollback to a previous deployment.

**Acceptance Criteria:**
- Rollback button available on successful deployments
- Confirmation required before rollback
- Previous theme restored from backup
- Rollback status recorded
- Cannot rollback if no backup exists

**Dependencies:**
- FR005 (successful deployment)
- FR006 (backup exists)

### FR008: Deployment Cancellation

**Priority:** High

**Description:** Users must be able to cancel in-progress deployments.

**Acceptance Criteria:**
- Cancel button available during building state
- GitHub Actions workflow cancellation requested
- Deployment marked as cancelled
- No theme files modified
- New deployment can be started after cancellation

**Dependencies:**
- FR003 or FR004
- Deployment in building state

### FR009: Manual Approval Workflow

**Priority:** Medium

**Description:** When enabled, deployments should require manual approval before execution.

**Acceptance Criteria:**
- Setting to enable/disable manual approval
- Webhook events create "pending" deployments (not auto-deployed)
- Dashboard shows "Deploy" button for pending deployments
- Admin can approve or cancel pending deployments
- Approved deployments trigger workflow and proceed normally
- Cancelled deployments are marked as such in history
- Manual deployments bypass approval requirement

**Dependencies:**
- FR004 (webhooks)
- User has `manage_options` capability

**Status:** ✅ Implemented (2025-11-09)

### FR010: Direct Clone Deployment

**Priority:** Medium

**Description:** Alternative deployment method that downloads repository directly without GitHub Actions build step, ideal for simple themes with no build process.

**Acceptance Criteria:**
- Setting to choose deployment method: GitHub Actions or Direct Clone
- Direct Clone mode downloads repository ZIP at specific commit
- Skips GitHub Actions workflow entirely
- Reuses existing backup and extraction logic
- UI shows/hides workflow field based on method
- Works with webhooks and manual deployments
- Deploys faster than GitHub Actions (no build time)
- Backend provides pre-signed download URLs

**Use Cases:**
- Simple themes using plain CSS/JS
- No webpack, npm, or build tools required
- Faster deployments for static files
- Lower GitHub Actions usage
- Development environments where builds aren't needed

**Technical Implementation:**
- WordPress calls backend: `POST /api/github/download-repo`
- Backend authenticates with GitHub using installation token
- Backend requests: `GET /repos/{owner}/{repo}/zipball/{ref}` (returns 302)
- Backend extracts `Location` header (pre-signed download URL)
- Backend returns: `{ download_url }` to WordPress
- WordPress downloads ZIP using pre-signed URL
- Same backup/rollback/extraction flow as artifact deployments
- Workflow field becomes optional when Direct Clone selected

**Backend Requirements:**
- New endpoint: `/api/github/download-repo`
- Request: `{ owner, repo, ref }` + `X-API-Key` header
- Response: `{ download_url }` or `{ error, message }`
- Uses same authentication pattern as `/api/github/proxy`

**Security:**
- Download URLs are pre-signed and time-limited by GitHub
- No direct GitHub token exposure to WordPress
- Same X-API-Key validation as other endpoints

**Dependencies:**
- FR001 (GitHub connectivity)
- FR003 or FR004 (deployment methods)
- Backend proxy service

**Status:** ✅ Implemented (2025-11-09) - Backend endpoint pending

### FR011: Deployment History

**Priority:** Medium

**Description:** System must maintain a history of all deployments.

**Acceptance Criteria:**
- All deployments recorded in database
- Searchable and filterable history
- Displays: commit info, status, timestamp, trigger type
- Link to build logs
- Configurable retention period
- Old deployments auto-deleted

**Dependencies:**
- FR003 or FR004

### FR012: Settings Management

**Priority:** High

**Description:** Users must be able to configure all plugin settings.

**Acceptance Criteria:**
- Repository connection settings
- Workflow selection
- Branch selection
- Auto-deploy enable/disable
- Manual approval enable/disable
- Backup enable/disable
- Webhook secret configuration
- Settings validation
- Settings can be reset

**Dependencies:**
- User has `manage_options` capability

### FR013: Debug Logging

**Priority:** Medium

**Description:** System must provide detailed logging for troubleshooting.

**Acceptance Criteria:**
- All deployment steps logged
- All API requests/responses logged
- Error details captured
- Logs viewable in admin interface
- Log levels: INFO, WARNING, ERROR
- Logs can be exported
- Log retention configurable

**Dependencies:**
- Any feature that performs operations

### FR014: Concurrent Deployment Prevention

**Priority:** High

**Description:** System must prevent multiple simultaneous deployments.

**Acceptance Criteria:**
- Only one deployment can be in "building" state
- Manual deployments blocked if one is building
- Webhook deployments auto-cancel existing builds
- Clear error message when blocked
- Building deployment info displayed

**Dependencies:**
- FR003, FR004

## Non-Functional Requirements

### NFR001: Performance

**Priority:** High

**Requirements:**
- Manual deployment initiated within 2 seconds
- Webhook processing under 5 seconds
- Admin pages load within 3 seconds
- Database queries optimized with indexes
- Large theme deployments (100MB+) complete without timeout
- UI remains responsive during deployments

**Metrics:**
- Response time
- Memory usage
- CPU usage
- Database query time

### NFR002: Security

**Priority:** Critical

**Requirements:**
- All secrets encrypted at rest (Sodium)
- All user inputs sanitized
- All outputs escaped
- CSRF protection on all forms (nonces)
- Capability checks on all admin actions
- SQL injection prevention (prepared statements)
- XSS prevention (output escaping)
- Webhook signature verification required
- No secrets in logs or error messages

**Compliance:**
- OWASP Top 10
- WordPress Security Best Practices
- PCI DSS (if handling payment data - N/A for this plugin)

### NFR003: Reliability

**Priority:** High

**Requirements:**
- 99% uptime for core functionality
- Graceful error handling
- Automatic retry for transient failures
- Rollback capability for failed deployments
- Database transactions where applicable
- No data loss on plugin failure

**Metrics:**
- Error rate
- Failed deployment rate
- Recovery time

### NFR004: Scalability

**Priority:** Medium

**Requirements:**
- Handle 100+ deployments per day
- Support theme files up to 500MB
- Maintain performance with 1000+ deployment records
- Efficient caching of GitHub API responses
- Background processing via WP_Cron

**Limits:**
- Maximum concurrent deployments: 1
- Maximum backup retention: 30 days (configurable)
- Maximum log retention: 30 days (configurable)

### NFR005: Usability

**Priority:** High

**Requirements:**
- Intuitive admin interface
- Clear error messages
- Helpful documentation
- Consistent with WordPress admin design
- Responsive admin pages (mobile friendly)
- Accessible (WCAG 2.1 AA)

**User Experience:**
- First-time setup under 10 minutes
- Deployment trigger under 3 clicks
- Settings findable within 2 clicks

### NFR006: Compatibility

**Priority:** Critical

**Requirements:**
- WordPress 5.8+ supported
- PHP 7.4+ supported
- PHP 8.0+ fully tested
- MySQL 5.7+ and MariaDB 10.2+ supported
- Compatible with major hosting providers
- No conflicts with popular plugins
- Theme agnostic (works with any WordPress theme)

**Testing Matrix:**
- Latest 3 WordPress versions
- PHP 7.4, 8.0, 8.1, 8.2, 8.3
- MySQL 5.7, 8.0 / MariaDB 10.2, 10.6

### NFR007: Maintainability

**Priority:** Medium

**Requirements:**
- Code follows WordPress Coding Standards
- PHPDoc comments on all classes/methods
- Clear separation of concerns
- DRY (Don't Repeat Yourself) principles
- Modular architecture
- Version controlled
- Semantic versioning

**Code Quality:**
- Pass phpcs with WordPress standards
- Pass phpstan level 5+
- No deprecated WordPress functions
- No PHP warnings or notices

### NFR008: Recoverability

**Priority:** High

**Requirements:**
- Automatic backups before deployment
- Manual rollback capability
- Database export/import support
- Plugin can be safely deactivated
- Plugin can be safely deleted (with warning)
- Settings preserved during plugin updates

**Recovery Time Objectives:**
- Rollback completion: Under 5 minutes
- Failed deployment recovery: Immediate (no changes applied)

### NFR009: Availability

**Priority:** Medium

**Requirements:**
- Plugin functions offline (manual deploy unavailable)
- Graceful degradation when GitHub unavailable
- Cached data used when API rate limited
- Clear status indicators for service health

**Dependencies:**
- GitHub API availability
- Backend proxy availability
- WordPress cron functionality
- Internet connectivity

### NFR010: Documentation

**Priority:** High

**Requirements:**
- User guide for setup and usage
- Developer documentation for code
- API documentation for integrations
- Security documentation
- Deployment workflow documentation
- Inline code comments
- README with quick start
- Changelog maintained

**Deliverables:**
- `README.md`
- `spec/` directory with all specifications
- Inline PHPDoc comments
- GitHub wiki (optional)

## System Requirements

### WordPress Environment

**Minimum:**
- WordPress: 5.8+
- PHP: 7.4+
- MySQL: 5.7+ OR MariaDB: 10.2+
- HTTPS: Required (for webhooks)
- wp-cron: Enabled
- File permissions: Write access to `wp-content/themes/`

**Recommended:**
- WordPress: 6.4+
- PHP: 8.2+
- MySQL: 8.0+ OR MariaDB: 10.6+
- Memory Limit: 256MB+
- Max Execution Time: 300 seconds
- Disk Space: 1GB+ free (for backups)

### PHP Extensions

**Required:**
- `json` - API communication
- `zip` - Artifact extraction
- `sodium` - Encryption
- `curl` or `allow_url_fopen` - HTTP requests

**Optional:**
- `mbstring` - Better string handling
- `opcache` - Performance

### Server Configuration

**Required:**
- Outbound HTTPS connections allowed
- Incoming HTTPS connections (for webhooks)
- Sufficient disk space for backups
- Write permissions to upload directory

**Recommended:**
- CDN or caching layer
- Regular backups
- Monitoring/alerting
- Log aggregation

### GitHub Requirements

**Repository:**
- GitHub repository access
- GitHub Actions enabled
- Workflow file configured (`.github/workflows/*.yml`)
- Artifact upload in workflow

**GitHub App:**
- GitHub App installed
- Repository permissions granted
- Webhook configured (for auto-deploy)
- Webhook secret configured

**API:**
- Rate limits: 5,000 requests/hour (authenticated)
- Workflow trigger limits: 1,000/hour per repository

## Constraints

### Technical Constraints

1. **WordPress Core Only** - No external PHP dependencies (Composer libraries)
2. **Single Repository** - v1.0 supports one repository per installation
3. **Theme Only** - v1.0 only deploys themes, not plugins
4. **Single Building Deployment** - Only one deployment can build at a time
5. **WP_Cron Dependency** - Relies on WordPress cron (can use system cron)

### Business Constraints

1. **GitHub Only** - v1.0 only supports GitHub (not GitLab, Bitbucket)
2. **Free Tier** - Must work with GitHub free tier
3. **Shared Hosting** - Must work on shared hosting environments

### Legal/Compliance Constraints

1. **GPL Compatible** - All code must be GPL v2+ compatible
2. **No Telemetry** - No usage tracking without explicit opt-in
3. **Privacy** - No personal data collection
4. **Open Source** - Source code publicly available

## Assumptions

1. Users have basic WordPress administration knowledge
2. Users have access to GitHub repository
3. Users can configure GitHub Actions
4. Server has reliable internet connection
5. WordPress database is properly maintained

## Out of Scope (v1.0)

Explicitly NOT included in v1.0:

- ❌ Plugin deployments (themes only)
- ❌ Multiple repositories
- ❌ Multi-environment (staging/production)
- ❌ Email notifications
- ❌ Slack notifications
- ❌ Deployment scheduling
- ❌ GitLab/Bitbucket support
- ❌ Blue-green deployments
- ❌ A/B testing
- ❌ Multi-site support
- ❌ Custom deployment scripts
- ❌ Database migrations

## Acceptance Criteria Summary

The plugin is considered complete when:

- ✅ All critical and high priority functional requirements met
- ✅ All critical non-functional requirements met
- ✅ Security audit passed with no critical or high vulnerabilities
- ✅ Compatible with latest WordPress version
- ✅ Compatible with PHP 7.4 - 8.3
- ✅ Manual testing complete for all workflows
- ✅ Documentation complete
- ✅ No known critical or high severity bugs

## Future Enhancements

See `spec/CHANGELOG.md` for planned future features beyond v1.0.
