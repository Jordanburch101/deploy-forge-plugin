# Changelog

**Last Updated:** 2025-11-18

This file tracks all significant changes, features, and planned enhancements for Deploy Forge by Jordan Burch.

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

**Plugin Update Checker** (2025-11-18)

- Type: Feature
- Description: Implement automatic plugin updates from private GitHub releases via custom update server
- Purpose: Enable WordPress sites to receive automatic plugin updates without relying on WordPress.org repository
- Architecture: Two-part system with WordPress plugin integration and Express/Vercel proxy server
- Components:
  - **WordPress Plugin Side** (`includes/class-update-checker.php`):
    - Integrates with WordPress update API via hooks (`pre_set_site_transient_update_plugins`, `plugins_api`, `upgrader_source_selection`)
    - Queries custom update server for latest version information
    - Handles version comparison using semantic versioning
    - Provides plugin information for WordPress update modal
    - Renames downloaded plugin folder to match WordPress expectations
    - Implements caching strategy (6-hour transient for release info)
    - Supports manual update checks and one-click updates
  - **Update Server** (Express/Vercel):
    - Serverless Express.js application deployed on Vercel
    - Acts as authenticated proxy to private GitHub repository releases
    - Endpoints: `/api/update-check` (POST), `/api/download/:plugin/:version` (GET), `/api/health` (GET)
    - Transforms GitHub release data to WordPress update format
    - Streams release ZIP files without local storage
    - Implements edge caching (1 hour for checks, 24 hours for downloads)
    - Rate limiting (100 requests per 15 minutes per site)
    - Plugin whitelist validation for security
    - GitHub API authentication via Personal Access Token (environment variable)
- Features:
  - Dashboard update notifications (same UX as WordPress.org plugins)
  - One-click updates from WordPress admin
  - Automatic update checks (twice daily via wp-cron)
  - Plugin details modal with changelog, requirements, screenshots
  - Support for semantic versioning (MAJOR.MINOR.PATCH)
  - Secure HTTPS-only communication
  - Optional package integrity verification (SHA-256 hash)
  - Comprehensive error handling and logging
  - Settings page section for manual update checks
- Technical Details:
  - WordPress hooks: `pre_set_site_transient_update_plugins`, `plugins_api`, `upgrader_source_selection`, `load-update-core.php`, `load-plugins.php`
  - Update server stack: Node.js 18.x, Express 4.x, Axios, Winston logging
  - GitHub API endpoints used: `/repos/{owner}/{repo}/releases`, `/repos/{owner}/{repo}/releases/assets/{id}`
  - Caching: WordPress transients (6 hours), Vercel edge cache (1-24 hours), in-memory cache (1 hour)
  - Security: HTTPS enforcement, rate limiting, plugin whitelist, request validation, CORS configuration
  - Performance: Streaming downloads, parallel requests, aggressive caching, minimal data transfer
- Benefits:
  - Automatic updates for private/proprietary plugins
  - Familiar WordPress update experience for users
  - No manual ZIP uploads required
  - Secure access to private GitHub releases
  - Scalable serverless architecture
  - Minimal maintenance overhead
- Configuration:
  - WordPress constants: `DEPLOY_FORGE_UPDATE_SERVER`, `DEPLOY_FORGE_UPDATE_INTERVAL`, `DEPLOY_FORGE_DISABLE_UPDATES`
  - Vercel environment variables: `GITHUB_TOKEN`, `GITHUB_OWNER`, `GITHUB_REPO`, `NODE_ENV`, `RATE_LIMIT_WINDOW`, `RATE_LIMIT_MAX_REQUESTS`
  - Plugin whitelist in `config/plugins.js`
- Monitoring:
  - Health check endpoint for uptime monitoring
  - GitHub API rate limit tracking (5,000/hour limit)
  - Error logging via Winston (Vercel logs)
  - Update check and download metrics
- Status: 📋 Planned - Specification Complete
- Related Specs:
  - `spec/plugin-update-checker.md` - WordPress plugin side (60+ KB detailed spec)
  - `spec/update-server.md` - Express/Vercel update server (50+ KB detailed spec)
- Next Steps:
  1. Create Express/Vercel update server repository
  2. Implement server endpoints and GitHub API integration
  3. Deploy to Vercel with environment variables
  4. Create `class-update-checker.php` in WordPress plugin
  5. Integrate update checker into main plugin initialization
  6. Test update flow end-to-end
  7. Create first GitHub release with proper format (tag, changelog, ZIP asset)
  8. Monitor update checks and downloads in production

**Data Formatter Usage** (2025-11-15)

- Type: Enhancement / Code Quality
- Description: Applied the Data_Formatter utility class created in Phase 2 to eliminate manual formatting logic
- Changes:
  - Updated `Deploy_Forge_Data_Formatter::format_repository_for_select()` to include Select2-compatible `id` and `text` fields
  - Replaced manual `array_map()` formatting in `ajax_get_repos()` with `Deploy_Forge_Data_Formatter::format_repositories()`
- Benefits:
  - Eliminated 12 lines of duplicate formatting logic
  - Single source of truth for repository formatting
  - Consistent formatting across wizard and admin areas
  - More maintainable and easier to update
- Code Reduction:
  - Manual array_map with inline function (13 lines) → Single formatter call (1 line)
- Files Modified:
  - `deploy-forge/includes/class-data-formatter.php` (added Select2 fields)
  - `deploy-forge/admin/class-setup-wizard.php` (uses formatter)
- Status: ✅ Implemented
- Related: Completes the Data_Formatter utility created in Phase 2

**AJAX Handler Consolidation - Phase 3** (2025-11-15)

- Type: Refactoring / Code Quality
- Description: Refactored all existing AJAX handlers to use the base class and utility methods created in Phase 2
- Changes:
  - Extended `Deploy_Forge_Admin_Pages` from `Deploy_Forge_Ajax_Handler_Base`
  - Extended `Deploy_Forge_Setup_Wizard` from `Deploy_Forge_Ajax_Handler_Base`
  - Refactored all 16 AJAX methods in `class-admin-pages.php` to use base class methods
  - Refactored all 8 AJAX methods in `class-setup-wizard.php` to use base class methods
  - Added `log()` method overrides in both classes to use logger instances
  - Replaced direct `check_ajax_referer()` + `current_user_can()` with `$this->verify_ajax_request()`
  - Replaced `wp_send_json_success()` with `$this->send_success()`
  - Replaced `wp_send_json_error()` with `$this->send_error()`
  - Replaced `wp_send_json()` with `$this->handle_api_response()` where appropriate
  - Replaced `sanitize_text_field($_POST['x'])` with `$this->get_post_param('x')`
  - Replaced `intval($_POST['x'])` with `$this->get_post_int('x')`
- Technical Details:
  - **Admin AJAX Methods Refactored** (16 total):
    - `ajax_test_connection()`, `ajax_manual_deploy()`, `ajax_get_status()`, `ajax_rollback()`
    - `ajax_approve_deployment()`, `ajax_cancel_deployment()`, `ajax_get_commits()`, `ajax_get_repos()`
    - `ajax_get_workflows()`, `ajax_generate_secret()`, `ajax_get_logs()`, `ajax_clear_logs()`
    - `ajax_get_connect_url()`, `ajax_disconnect_github()`, `ajax_get_installation_repos()`, `ajax_bind_repo()`
    - `ajax_reset_all_data()`
  - **Wizard AJAX Methods Refactored** (8 total):
    - `ajax_get_repos()`, `ajax_get_branches()`, `ajax_get_workflows()`, `ajax_validate_repo()`
    - `ajax_bind_repo()`, `ajax_save_step()`, `ajax_complete()`, `ajax_skip()`
- Benefits:
  - **Eliminated ~250-300 lines of duplicate code** across both classes
  - Consistent security checks across all AJAX endpoints
  - Consistent error handling and response formatting
  - Improved code readability with cleaner, more concise methods
  - Easier to add new AJAX handlers (just extend base class)
  - Single source of truth for security, validation, and response patterns
  - Reduced potential for security vulnerabilities through standardization
- Code Reduction:
  - Each AJAX method reduced by ~10-15 lines on average
  - Security checks: 2-4 lines → 1 line per method
  - Response calls: 1-2 lines → 1 line per method
  - Parameter sanitization: 1-2 lines → 1 line per parameter
  - Total: ~250-300 lines eliminated across 24 AJAX methods
- Files Modified:
  - `deploy-forge/admin/class-admin-pages.php` (extended base, refactored all AJAX methods)
  - `deploy-forge/admin/class-setup-wizard.php` (extended base, refactored all AJAX methods)
- Status: ✅ Implemented (Phase 3 Complete - Actual Consolidation Applied)
- Next Steps: Phase 4+ - Additional consolidation opportunities (templates, Select2 helpers, form field renderers, etc.)
- Related: Code consolidation plan - Phase 3 of 10 total opportunities

**PHP & JavaScript Consolidation - Phase 2** (2025-11-15)

- Type: Refactoring / Code Quality
- Description: Major PHP and JavaScript consolidation to eliminate duplicate AJAX logic and data formatting
- Changes:
  - Created `class-ajax-handler-base.php` - Base class for AJAX handlers with shared security/validation methods
  - Created `class-data-formatter.php` - Utility class for formatting repositories, workflows, deployments
  - Created `ajax-utilities.js` - Shared JavaScript utilities for AJAX requests and Select2 initialization
  - Updated main plugin file to require new utility classes
  - Updated both admin and wizard enqueue functions to load AJAX utilities JavaScript
- Technical Details:
  - **Base AJAX Handler**: Provides `verify_ajax_request()`, `send_success()`, `send_error()`, `get_post_param()`, `validate_required_params()`, `handle_api_response()`
  - **Data Formatter**: Static methods for formatting repositories, workflows, branches, deployments for Select2/JSON responses
  - **AJAX Utilities JS**: `DeployForgeAjax.request()`, `loadRepositories()`, `loadWorkflows()`, `loadBranches()`, `initSelect2()`, `showError()`, `showSuccess()`
- Benefits:
  - Eliminates foundation for ~200+ lines of duplicate AJAX security checks (ready for Phase 3 refactoring)
  - Provides reusable data formatting (ready for Phase 3 refactoring)
  - Provides reusable JavaScript AJAX patterns (ready for future use)
  - Single source of truth for AJAX request handling
  - Consistent error handling and validation
  - Improved code organization and maintainability
- Files Created:
  - `deploy-forge/includes/class-ajax-handler-base.php` (AJAX base class)
  - `deploy-forge/includes/class-data-formatter.php` (data formatting utilities)
  - `deploy-forge/admin/js/ajax-utilities.js` (shared JavaScript utilities)
- Files Modified:
  - `deploy-forge.php` (added utility class includes)
  - `deploy-forge/admin/class-admin-pages.php` (enqueue AJAX utilities)
  - `deploy-forge/admin/class-setup-wizard.php` (enqueue AJAX utilities)
- Status: ✅ Implemented (Phase 2 Complete - Foundation Laid)
- Next Steps: Phase 3 - Refactor existing AJAX handlers to use base class and formatter (actual consolidation)
- Related: Code consolidation plan - Phase 2 of 10 total opportunities

**CSS Consolidation - Phase 1** (2025-11-15)

- Type: Refactoring / Code Quality
- Description: Major code consolidation to eliminate duplicate CSS and improve maintainability
- Changes:
  - Created `deploy-forge/admin/css/shared-styles.css` with common styles used across admin and wizard
  - Moved CSS variables, loading spinners, status badges, modals, buttons to shared file
  - Removed duplicate styles from `admin-styles.css` (deployment status, modal, loading, buttons)
  - Removed duplicate styles from `setup-wizard.css` (loading spinner animation)
  - Removed inline modal styles from `history-page.php` (lines 114-156)
  - Added utility classes (flex helpers, spacing, text alignment, visibility)
  - Updated both `class-admin-pages.php` and `class-setup-wizard.php` to enqueue shared styles
- Technical Details:
  - CSS Variables: Centralized color palette, spacing, radius, shadows
  - Loading Spinners: Single definition with theming overrides
  - Deployment Status Badges: Unified styling for all status types (success, failed, pending, building, etc.)
  - Modal System: Shared modal styles replacing inline styles
  - Import Chain: `shared-styles.css` → `admin-styles.css` / `setup-wizard.css`
- Benefits:
  - Reduced CSS by ~30% (eliminated ~200 lines of duplication)
  - Single source of truth for common UI components
  - Easier to maintain consistent styling across pages
  - Improved code DRY (Don't Repeat Yourself) principles
- Files Created:
  - `deploy-forge/admin/css/shared-styles.css` (new consolidated stylesheet)
- Files Modified:
  - `deploy-forge/admin/css/admin-styles.css` (removed duplicates, added @import)
  - `deploy-forge/admin/css/setup-wizard.css` (removed duplicates, added @import)
  - `deploy-forge/templates/history-page.php` (removed inline modal styles)
  - `deploy-forge/admin/class-admin-pages.php` (updated enqueue to load shared styles)
  - `deploy-forge/admin/class-setup-wizard.php` (updated enqueue to load shared styles)
- Status: ✅ Implemented (Phase 1 Complete)
- Next Steps: Phase 2 - PHP consolidation (AJAX handler base class, data formatters)
- Related: Code consolidation plan (10 total consolidation opportunities identified)

**Plugin Renamed to Deploy Forge** (2025-11-11)

- Type: Enhancement
- Description: Complete plugin rebrand from "Deploy Forge" to "Deploy Forge" by Jordan Burch
- Changes:
  - Renamed plugin directory from `deploy-forge/` to `deploy-forge/`
  - Renamed main plugin file from `deploy-forge.php` to `deploy-forge.php`
  - Updated all class name prefixes: `GitHub_Deploy_*` → `Deploy_Forge_*`
  - Updated all constant prefixes: `GITHUB_DEPLOY_*` → `DEPLOY_FORGE_*`
  - Updated all function prefixes: `github_deploy_*` → `deploy_forge_*`
  - Updated text domain: `deploy-forge` → `deploy-forge`
  - Updated CSS class prefixes: `deploy-forge` → `deploy-forge`
  - Updated JavaScript object names: `githubDeploy` → `deployForge`
  - Updated plugin header with author info (Jordan Burch)
  - Updated all documentation references
- Files Modified:
  - All PHP, JavaScript, and CSS files in plugin
  - `CLAUDE.md` - Updated project overview and structure
  - `deploy-forge/README.md` - Updated branding throughout
  - `spec/CHANGELOG.md` - Updated title and branding
- Files Removed:
  - `security-audit/` directory
  - `deploy-forge.zip`
  - `LINTER-CONFIGURATION.md`
  - Duplicate plugin files
- Status: ✅ Implemented

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

**Setup Wizard - Custom Icon Support** (2025-11-11)

- Type: Enhancement
- Description: Replaced emoji icons with custom image icons in setup wizard deployment method step
- Changes:
  - Created `deploy-forge/admin/images/` directory for custom icons
  - Replaced ⚙️ emoji with `power-icon.png` for GitHub Actions option
  - Replaced ⚡ emoji with `flash-icon.png` for Direct Clone option
  - Images use `max-width: 32px; max-height: 32px;` for consistent sizing
  - Updated path references to use `DEPLOY_FORGE_PLUGIN_URL . 'deploy-forge/admin/images/'`
- Files Modified:
  - `deploy-forge/templates/setup-wizard/step-method.php` - Updated icon markup
- Files Added:
  - `deploy-forge/admin/images/` directory (icons to be added by user)
- Status: ✅ Implemented
- Related: Setup wizard implementation

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
- Example: `wp deploy-forge deploy --commit=abc123`

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
