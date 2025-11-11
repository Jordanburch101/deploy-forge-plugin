# Changelog

**Last Updated:** 2025-01-27

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

**Dashboard Redesign - Wireframe-Based Layout** (2025-11-11)

- Type: Enhancement
- Description: Complete redesign of dashboard page based on new wireframe specifications
- Layout Changes:
  - Replaced 3-column card grid with streamlined single-column sections
  - Connection & Controls: Combined status and quick actions into single header with Deploy Now button right-aligned
  - Stats: 4 clickable stat cards in a row with hover effects and direct links to filtered history
  - Latest Deployment Summary: New dedicated section showing full deployment metadata before recent deployments table
  - Recent Deployments: Added search input and status filter dropdown for table
- Design Improvements:
  - Consistent card-based design with white backgrounds and subtle shadows
  - Clickable stat cards with hover effects (transform, shadow, border color change)
  - Improved spacing and visual hierarchy
  - Better mobile responsiveness with flexible layouts
  - Table controls (search + filter) in horizontal layout
- User Experience:
  - Click stat cards to view filtered deployment history
  - Search deployments by commit hash, message, or any table text
  - Filter deployments by status (success, failed, pending, building, etc.)
  - Quick access to Deploy Now button in header
  - Latest deployment details prominently displayed
- Technical Implementation:
  - JavaScript table filtering (client-side for performance)
  - Flexbox layouts for responsive design
  - CSS Grid for stat cards
  - Status-based URL parameters for history page linking
  - Real-time search/filter without page reload
- Files Modified:
  - `templates/dashboard-page.php` (complete structure redesign)
  - `admin/css/admin-styles.css` (new sections: header, stats-section, latest-summary, table-controls)
  - `admin/js/admin-scripts.js` (added initTableFilters, filterTable methods)
- Status: ✅ Implemented
- Related: Dashboard page (original implementation)

**Setup Wizard - Vercel-Inspired Dark Theme** (2025-11-09)

- Type: Enhancement
- Description: Complete visual redesign of setup wizard with Vercel-inspired dark theme
- Design Changes:
  - Pure black background (#000000) with dark gray accents
  - White text with multiple gray shades for hierarchy
  - White buttons with black text (Vercel-style)
  - 2px border radius throughout (minimal aesthetic)
  - Mythic logo replacing WordPress dashicon
  - CSS variables for easy theme customization
  - Fully styled Select2 dropdowns (dark background, no blue highlights)
- Color System:
  - Background: #000000 (primary), #0a0a0a (secondary), #1a1a1a (tertiary)
  - Text: #ffffff (primary), #a1a1a1 (secondary), #666666 (tertiary)
  - Borders: #333333 (primary), #222222 (secondary)
  - Buttons: White background with black text
  - Success accent: #00ff00 (green) - kept for important states
  - Error accent: #ff0000 (red) - kept for warnings
  - Warning accent: #ffb900 (amber)
- Select2 Customization:
  - Dark dropdown background matching wizard theme
  - Dark search input with white border on focus
  - Hover states using tertiary background color
  - Selected options highlighted with green accent
  - All states themed (disabled, loading, no-results, placeholder)
- Technical Implementation:
  - 40+ CSS custom properties in :root for easy customization
  - All colors, borders, shadows defined as variables
  - No logic changes - purely visual enhancement
  - Maintained all existing functionality
  - Comprehensive Select2 component styling (dropdown, search, options)
- Files Modified:
  - `admin/css/setup-wizard.css` (complete theme overhaul + Select2 styling)
  - `templates/setup-wizard/step-welcome.php` (logo replacement)
- Status: ✅ Implemented
- Related: Original setup wizard implementation (2025-11-09)

**Setup Wizard** (2025-11-09)

- Type: Feature
- Description: Multi-step onboarding wizard for first-time setup with modern, sleek UI
- Features:
  - 6-step guided configuration process
  - Horizontal progress stepper with visual feedback
  - Select2-powered enhanced dropdowns for repositories, branches, workflows
  - Real-time validation and AJAX loading
  - Toggle switches for deployment options (iOS-style)
  - Visual deployment method cards (GitHub Actions vs Direct Clone)
  - Review screen with edit links
  - Skip/resume functionality
  - Auto-redirect on first activation
- User Experience:
  - Estimated 5-minute setup time
  - Contextual help text throughout
  - Success animations and feedback
  - Copy-to-clipboard for webhook configuration
  - Responsive design (mobile-friendly)
- Technical Implementation:
  - Full-screen modal overlay with smooth animations
  - Progress saved to WordPress transients (1-hour expiration)
  - 8 AJAX endpoints for repos, branches, workflows, validation
  - Completes setup by saving all settings to wp_options
- Files Added:
  - `admin/class-setup-wizard.php` (580 lines)
  - `admin/css/setup-wizard.css` (850+ lines)
  - `admin/js/setup-wizard.js` (650+ lines)
  - `templates/setup-wizard/wizard-container.php`
  - `templates/setup-wizard/step-welcome.php`
  - `templates/setup-wizard/step-connect.php`
  - `templates/setup-wizard/step-repository.php`
  - `templates/setup-wizard/step-method.php`
  - `templates/setup-wizard/step-options.php`
  - `templates/setup-wizard/step-review.php`
- Integration:
  - Added to main plugin initialization
  - Activation hook sets redirect transient
  - Hidden admin menu page (direct URL access only)
- Dependencies:
  - Select2 4.1.0 (CDN)
  - jQuery (WordPress core)
  - Existing GitHub API and settings infrastructure
- Status: ✅ Implemented
- Related: spec/setup-wizard.md

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

**Direct Clone Deployment Method** (2025-11-09)

- Type: Feature
- Description: Added alternative deployment method that clones repository directly without GitHub Actions build step
- Use Case: Perfect for simple themes using plain CSS/JS without build processes (webpack, npm, etc.)
- Technical Implementation:
  - Added `deployment_method` setting: `github_actions` (default) or `direct_clone`
  - New `download_repository()` method in GitHub API class to download repo ZIP at specific commit
  - New `direct_clone_deployment()` method in deployment manager
  - Reuses existing extract/deploy/backup logic
  - Downloads repository using GitHub's `/repos/{owner}/{repo}/zipball/{ref}` endpoint via backend proxy
  - Backend endpoint: `POST /api/github/download-repo` returns pre-signed download URL
- Changes:
  - Settings: Added deployment method dropdown in settings UI
  - JavaScript: Show/hide workflow field based on method selection
  - Deployment flow: Checks method and routes to appropriate deployment path
  - Backend: New endpoint required for getting repository download URLs
  - No GitHub Actions workflow required for direct clone mode
- Files Modified:
  - `includes/class-settings.php` - Added deployment_method setting and validation
  - `includes/class-github-api.php` - Added download_repository() method with backend proxy integration
  - `includes/class-deployment-manager.php` - Added direct_clone_deployment() and routing logic
  - `templates/settings-page.php` - Added deployment method selector UI
  - `admin/js/admin-scripts.js` - Added UI toggle for workflow field
  - `admin/class-admin-pages.php` - Added deployment_method to settings save handler
- Backend Requirements:
  - New endpoint: `POST /api/github/download-repo`
  - Accepts: `{ owner, repo, ref }`
  - Returns: `{ download_url }` (pre-signed GitHub download URL)
  - Authentication: Same X-API-Key pattern as existing endpoints
- Benefits:
  - Faster deployments for simple themes (no build time)
  - No need to maintain GitHub Actions workflow file
  - Lower GitHub Actions usage (reduced costs)
  - Simpler setup for non-technical users
  - Works with webhooks and manual deployments
- Related: FR010 in requirements.md

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

### Enhanced

**Setup Wizard Improvements** (2025-01-27)

- Type: Enhancement
- Description: Enhanced setup wizard with repository binding, improved OAuth flow, and better UX
- Changes:
  - Added repository binding functionality during wizard (`ajax_bind_repo`)
  - Improved OAuth callback handling to work seamlessly with wizard flow
  - Context-aware return URLs (wizard vs settings page)
  - Added wizard reset functionality via URL parameter for testing/troubleshooting
  - Changed Select2 CDN from jsdelivr to unpkg for better reliability
  - Improved settings merge logic to preserve webhook_secret during wizard completion
  - Enhanced workflow fetching to return total_count for better UI feedback
  - Removed webhook configuration from options step (simplified UI)
  - Better welcome message on dashboard for unconfigured state with wizard link
- Files Modified:
  - `admin/class-setup-wizard.php` - Added repository binding, reset functionality, improved OAuth handling
  - `admin/css/setup-wizard.css` - Refactored styles for better maintainability
  - `admin/js/setup-wizard.js` - Enhanced JavaScript for new binding flow
  - `includes/class-github-api.php` - Added `get_branches()` method
  - `includes/class-github-app-connector.php` - Improved OAuth callback with wizard context awareness
  - `templates/dashboard-page.php` - Better welcome message with wizard CTA
  - `templates/setup-wizard/step-options.php` - Removed webhook configuration section
- Impact: More robust wizard flow, better user experience, improved error handling

**GitHub API Enhancements** (2025-01-27)

- Type: Enhancement
- Description: Added branch fetching capability to GitHub API class
- Changes:
  - New `get_branches()` method for fetching repository branches
  - Includes caching (5-minute transient)
  - Proper error handling and logging
- Files Modified:
  - `includes/class-github-api.php`
- Use Case: Used by setup wizard and settings page for branch selection

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
