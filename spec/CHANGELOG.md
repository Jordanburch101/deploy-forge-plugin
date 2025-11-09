# Changelog

**Last Updated:** 2025-11-09

This file tracks all significant changes, features, and planned enhancements for the WordPress GitHub Auto-Deploy plugin.

## Purpose

This changelog replaces individual feature markdown files (e.g., `NEW-FEATURE.md`). All feature planning, changes, and notes should be added here with dates for tracking.

## Format

Each entry should include:
- **Date:** When the change was made/planned
- **Type:** Feature, Enhancement, Fix, Security, Breaking Change
- **Description:** What changed and why
- **Related Spec:** Link to relevant spec file if applicable

---

## [Unreleased] - Planned Features

### High Priority

**Multi-Environment Support** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Support for staging and production environments with separate configuration
- Technical Approach:
  - Separate database table for environments
  - Environment-specific GitHub branches
  - Environment switching in UI
- Related Spec: To be created

**Deployment Scheduling** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Schedule deployments for specific times
- Technical Approach:
  - WP_Cron scheduled events
  - UI for schedule management
  - Timezone handling
- Related Spec: To be created

**Email Notifications** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Email notifications for deployment events
- Technical Approach:
  - wp_mail() integration
  - Configurable notification preferences
  - HTML email templates
- Related Spec: To be created

### Medium Priority

**Plugin Deployment Support** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Extend beyond themes to support plugin deployments
- Breaking: Yes (requires architecture changes)
- Related Spec: To be created

**Deployment Approval Workflow** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Multi-step approval process for deployments
- Technical Approach:
  - Approval states in database
  - Role-based permissions
  - Email notifications for approvers
- Related Spec: To be created

**Advanced Logging & Analytics** (Planned)
- Date Planned: TBD
- Type: Enhancement
- Description: Enhanced logging with search, filtering, and analytics dashboard
- Technical Approach:
  - Improved log table schema
  - Analytics aggregation
  - Chart visualization
- Related Spec: To be created

### Low Priority / Nice to Have

**Slack Integration** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Send deployment notifications to Slack
- Technical Approach:
  - Webhook integration
  - Configurable message templates

**GitLab/Bitbucket Support** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Support other Git hosting providers
- Breaking: Yes (requires abstraction layer)

**WP-CLI Commands** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Command-line interface for deployments
- Example: `wp github-deploy deploy --commit=abc123`

**Deployment Health Checks** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Pre and post-deployment validation
- Technical Approach:
  - Configurable health check URLs
  - HTTP status verification
  - Auto-rollback on failure

**Progressive Deployments** (Planned)
- Date Planned: TBD
- Type: Feature
- Description: Staged rollout to percentage of traffic
- Technical Approach:
  - Requires load balancer integration
  - Complex implementation

---

## [1.0.0] - 2025-11-09

### Added

**Workflow Selection Feature** (2025-11-07)
- Type: Feature
- Description: Dynamic workflow selection instead of hardcoded workflow names
- Changes:
  - Added workflow dropdown in settings
  - Load workflows from GitHub API
  - Filter for active workflows only
  - Store selected workflow filename
- Files Modified:
  - `admin/class-admin-pages.php`
  - `includes/class-github-api.php`
  - `templates/settings-page.php`
  - `admin/js/admin-scripts.js`
- Related: `app-docs/workflow-dropdown-feature-2025-11-07.md` (now archived)

**Manual Approval Workflow - Deploy Button** (2025-11-09)
- Type: Fix
- Description: Added missing "Deploy" button for pending deployments in manual approval workflow
- Issue: When manual approval was enabled, pending deployments only showed "Cancel" button, making approval impossible from the UI
- Changes:
  - Added "Deploy" button (primary/blue) for pending deployments
  - Shows both "Deploy" and "Cancel" buttons in Actions column
  - Building deployments show only "Cancel" button
  - Added `approve_pending_deployment()` method to deployment manager
  - Added AJAX handler `ajax_approve_deployment()`
  - JavaScript function `approveDeployment()` with confirmation dialog
- Files Modified:
  - `templates/dashboard-page.php`
  - `templates/history-page.php`
  - `admin/js/admin-scripts.js`
  - `admin/class-admin-pages.php`
  - `includes/class-deployment-manager.php`
- Impact: Manual approval feature now fully functional

**Deployment Cancellation** (2025-11-09)
- Type: Feature
- Description: Ability to cancel in-progress deployments
- Changes:
  - Cancel button in dashboard UI
  - GitHub API workflow cancellation
  - Status tracking for cancelled deployments
  - Concurrent deployment handling
- Files Modified:
  - `includes/class-deployment-manager.php`
  - `includes/class-github-api.php`
  - `templates/dashboard-page.php`
  - `admin/js/admin-scripts.js`
- Related: `app-docs/CANCEL-DEPLOYMENT-FEATURE.md` (now archived)

**Webhook Improvements** (2025-11-08)
- Type: Enhancement
- Description: Better webhook handling and workflow_run event support
- Changes:
  - workflow_run event handling
  - Automatic deployment on workflow completion
  - Improved commit hash matching
  - Better error messages
- Related: `app-docs/WORKFLOW-WEBHOOK-IMPROVEMENT.md` (now archived)

**GitHub App Integration** (2025-11-07)
- Type: Enhancement
- Description: Switched from Personal Access Tokens to GitHub App authentication
- Changes:
  - Backend proxy for token management
  - Installation-based authentication
  - Improved security
  - Repository isolation
- Files Added:
  - `includes/class-github-app-connector.php`
- Files Modified:
  - `includes/class-github-api.php`
  - `includes/class-settings.php`
- Related: `app-docs/GITHUB-APP-SETUP-GUIDE.md`

**Reset All Data Feature** (2025-11-06)
- Type: Feature
- Description: Ability to completely reset plugin configuration and data
- Changes:
  - Settings reset button
  - Confirmation prompts
  - Delete all deployments
  - Clear all settings
  - Disconnect from GitHub
- Files Modified:
  - `admin/class-admin-pages.php`
  - `admin/js/admin-scripts.js`

### Security Fixes

**Critical Security Patches** (2025-11-05)
- Type: Security
- Description: Multiple critical security vulnerabilities fixed
- Fixes:
  1. Webhook secret now mandatory (no default value)
  2. Debug endpoint removed from production
  3. Installation token no longer exposed in responses
  4. Repository isolation enforced
  5. Improved nonce verification
- Related: `security-audit/` directory
- Severity: CRITICAL
- CVE: None assigned (private disclosure)

**Backend Security Improvements** (2025-11-05)
- Type: Security
- Description: Enhanced backend API security
- Changes:
  - API key validation
  - Request sanitization
  - Rate limiting preparation
  - Improved error messages
- Related: `app-docs/BACKEND-SECURITY-TASKS.md`

### Fixed

**Nonce Verification** (2025-11-06)
- Type: Fix
- Description: Fixed AJAX reset all data nonce verification
- Issue: Wrong nonce name in verification
- Fix: Updated to use correct nonce field

**Workflow Loading** (2025-11-07)
- Type: Fix
- Description: Improved workflow loading reliability
- Changes:
  - Better error handling
  - Loading state management
  - Empty state messaging

### Documentation

**Comprehensive Specifications Created** (2025-11-09)
- Type: Documentation
- Description: Created spec/ folder with comprehensive technical documentation
- Files Created:
  - `spec/architecture.md` - System architecture
  - `spec/database.md` - Database schema
  - `spec/api-integration.md` - API specifications
  - `spec/security.md` - Security requirements
  - `spec/deployment-workflow.md` - Deployment processes
  - `spec/testing.md` - Testing procedures
  - `spec/CHANGELOG.md` - This file
- Files Updated:
  - `CLAUDE.md` - References new spec structure
- Files Archived:
  - `app-docs/` - Old documentation (now superseded by spec/)

---

## Version History

### Version Numbering

This project follows [Semantic Versioning](https://semver.org/):

- **MAJOR** version: Incompatible API changes
- **MINOR** version: Backwards-compatible functionality
- **PATCH** version: Backwards-compatible bug fixes

### Release Timeline

- **v1.0.0** - Initial release (2025-11-09)
- Future releases TBD

---

## How to Use This Changelog

### Adding New Features

When planning a new feature:

1. Add an entry under "Unreleased" section
2. Include date planned, type, and description
3. Update technical approach as design progresses
4. Link to relevant spec files when created

### Recording Changes

When implementing a change:

1. Move from "Unreleased" to current version section
2. Update date to implementation date
3. List all files modified
4. Add any migration notes if needed

### Release Process

When releasing a new version:

1. Create new version section with release date
2. Move all unreleased items to the version
3. Update version numbers in plugin files
4. Tag release in Git
5. Update README with new version info

---

## Migration Notes

### Breaking Changes

None yet (v1.0.0 is initial release)

### Deprecations

None yet

### Database Migrations

**v1.0.0:**
- Initial schema creation
- `wp_github_deployments` table
- `wp_github_deploy_logs` table

---

## Contributing

When making changes to the plugin:

1. **Always update this changelog** with your changes
2. Include the date and type of change
3. Reference related spec files
4. Note any breaking changes
5. Update migration notes if schema changes

---

## References

- [Keep a Changelog](https://keepachangelog.com/)
- [Semantic Versioning](https://semver.org/)
- WordPress [Plugin Handbook](https://developer.wordpress.org/plugins/)
