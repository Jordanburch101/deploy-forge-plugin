# Changelog

All notable changes to Deploy Forge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.54] - 2026-02-10

### Fixed
- Deployment status stuck on "deploying" after successful deploy — split status update into separate DB call from file manifest write so charset/encoding issues in the manifest don't block the status change
- Added error logging for silent `update_deployment` failures

## [1.0.53] - 2026-02-10

### Changed
- Deploy and rollback now use atomic directory swap (copy to staging, rename swap) instead of deleting the theme directory before copying — prevents broken state if the copy fails mid-way
- Unified deployment cleanup: `run_full_cleanup()` prunes backup/snapshot files by count AND deletes DB rows + files older than 90 days

### Added
- `safe_swap_directory()` helper for atomic rename-swap of directories
- `run_full_cleanup()` method combining file and DB row cleanup
- `get_old_deployments()` database method for age-based cleanup
- Daily WP-Cron schedule (`deploy_forge_daily_cleanup`) for background cleanup

## [1.0.52] - 2026-02-10

### Added
- Active deployment tracking via `deploy_forge_active_deployment_id` WP option
- "Active" badge on the deployment whose files are currently live on disk
- File change detection: compares live theme files against stored SHA-256 file manifest
- Unified diff viewer for modified files using WordPress core `Text_Diff` library
- "Check Changes" button on active deployment row to manually trigger file drift scan
- Auto-check on page load with 5-minute sessionStorage and server-side transient cache
- File Changes modal with summary bar (modified/added/removed counts) and file list table
- File Diff modal with dark-theme syntax highlighting (additions, deletions, hunks, context)
- Database migration 1.2: `file_manifest` (LONGTEXT) and `snapshot_path` (varchar) columns
- Automatic cleanup of old backup and snapshot ZIPs (keeps 10 most recent)

### Changed
- `extract_and_deploy()` now generates file manifest, creates snapshot, and sets active deployment ID on success
- `rollback_deployment()` now updates active deployment ID to the previous successful deployment and refreshes its manifest
- Settings page notes the backup retention policy

### Fixed
- Rollback now correctly copies files from the inner theme directory within the backup ZIP (previously created a nested `theme/theme/` structure)
- Rollback now clears the theme directory before restoring, preventing orphan files from newer deployments surviving the rollback
- XSS protection: all dynamic content in deployment details and connection test results is now HTML-escaped
- HTML attribute injection: `escapeHtml` now also encodes quotes for safe use in attributes

## [1.0.51] - 2026-02-10

### Changed
- Deployments header now shows repo name (linked to GitHub) and branch badge instead of generic "Connected to GitHub"

## [1.0.50] - 2026-02-10

### Fixed
- Changelog display in plugin update modal had excessive spacing caused by `nl2br()` converting blank lines and post-block-element newlines into `<br>` tags

## [1.0.49] - 2026-02-10

### Fixed
- Removed 5 debug `console.log` statements from production JavaScript

### Added
- `Author URI` plugin header

## [1.0.48] - 2026-02-10

### Removed
- Stats widget boxes from Deployments page (stats available in web app dashboard)

## [1.0.47] - 2026-02-10

### Changed
- Simplified admin UI from 4 pages to 3: Deployments (landing), Settings, Debug Logs
- Removed Dashboard page (redundant with Deploy Forge web app dashboard)
- Renamed History page to Deployments and made it the plugin landing page
- Merged Deploy Now button and stats cards from Dashboard into Deployments page
- Added search/filter controls to Deployments table
- Added "View in Deploy Forge" button linking to web app dashboard

### Removed
- `dashboard-page.php` template (merged into `deployments-page.php`)
- `history-page.php` template (replaced by `deployments-page.php`)
- `deploy-forge-history` submenu item
- "Latest Deployment Summary" section (redundant with table)

## [1.0.46] - 2026-02-10

### Added
- Self-hosted plugin update system via GitHub Releases (`class-plugin-updater.php`)
- Plugin checks the public GitHub repository for new releases and integrates with WordPress's native update UI
- Users receive update notifications on the Plugins page and can update with one click
- "View details" modal shows changelog from the GitHub Release body
- Release data cached for 12 hours (1 hour on failure) to respect API rate limits
- Site disconnected webhook event handler
- Version constant now reads from plugin header (single source of truth)
- Public README.md, LICENSE (GPL v2), and improved .gitignore

### Fixed
- Synced `DEPLOY_FORGE_VERSION` constant (was out of sync with plugin header)
- Synced test bootstrap version constant

## [1.0.45] - 2026-01-19

### Changed
- Updated all URLs to use new domain `getdeployforge.com`
  - Plugin URI, Author URI, Update URI in plugin header
  - Backend URL constants in `class-settings.php` and `class-github-api.php`
  - Update checker author profile fallback
  - API documentation in `spec/api-integration.md`
  - Release documentation in `RELEASING.md`

## [1.0.43] - 2026-01-18

### Fixed
- Fixed fatal error in artifact_check context building that prevented error reporting to platform
  - Changed `$this->settings->get_option()` to `$this->settings->get()` (method didn't exist)

## [1.0.42] - 2026-01-18

### Added
- **Enhanced deployment error reporting**: Deployment failures now send rich debugging context to Deploy Forge platform
  - Added `context` field to `/api/plugin/deployments/complete` API payload
  - Base context includes: `deployment_method`, `trigger_type`, `workflow_run_id`, `build_url`, `artifact_id`, `artifact_name`, `artifact_size`, `commit_hash`, `plugin_version`, `php_version`, `wp_version`
  - Failure-specific context added for each error type:
    - `artifact_check`: API success status, API message, artifacts count, expected artifact name
    - `artifact_download`: Error code, artifact ID, direct URL endpoint
    - `zip_open`: ZipArchive error code with human-readable message, file existence, file size
    - `zip_extract`: Extract directory path, directory permissions, disk free space
    - `file_copy`: Source/target directories, file counts, write permissions, disk free space
    - `github_build`: Build conclusion status, build status
  - Detailed log messages added before each failure with actionable debugging info

### Changed
- `report_deployment_status()` now accepts optional `$context` array parameter
- `report_status_to_backend()` now accepts optional `$additional_context` parameter and auto-gathers deployment metadata
- GitHub build failures now report to platform (previously only updated local database)
- Removed unused `notification_email` setting from settings page and backend

## [1.0.41] - 2026-01-18

### Added
- **CI/CD Test Integration**: Release workflow now runs tests before building
  - Added PHP 8.2 setup step using `shivammathur/setup-php@v2`
  - Added Composer dependency installation
  - Added PHPCS coding standards check
  - Added PHPUnit test execution
  - Release will fail if tests or linting fail

### Fixed
- Fixed Intelephense type errors in `GitHubApiTest.php` by adding `@var` annotations
- Added explicit test directory exclusion to `phpcs.xml.dist`

## [1.0.4] - 2026-01-17

### Added
- **Comprehensive Unit Test Suite**: 197 tests with 455 assertions covering security-critical paths and core functionality
  - `WebhookHandlerTest.php` - 16 tests for HMAC-SHA256 signature verification, timing-safe comparison
  - `ConnectionHandlerTest.php` - 19 tests for OAuth flow security, nonce validation, token exchange
  - `AjaxHandlerBaseTest.php` - 29 tests for CSRF prevention, authorization, input sanitization
  - `GitHubApiTest.php` - 18 tests for API authentication, error handling, workflow operations
  - `DeploymentManagerTest.php` - 18 tests for deployment workflow, status transitions
  - `DatabaseTest.php` - 22 tests for CRUD operations
  - `DebugLoggerTest.php` - 25 tests for logging functionality, sensitive data redaction
  - `UpdateCheckerTest.php` - 19 tests for plugin update checking
- PHPUnit 10.5 test infrastructure with Brain Monkey for WordPress function mocking
- Mock classes for `WP_Error`, `WP_REST_Request`, `WP_REST_Response` in test bootstrap
- Composer scripts: `composer test` for running test suite

### Changed
- **WordPress Coding Standards Compliance**: Full WPCS 3.0 compliance for WordPress.org plugin repository submission
  - Added `phpcs.xml.dist` configuration file with WordPress ruleset
  - Updated all PHP files with proper PHPDoc blocks (`@param`, `@return`, `@since` tags)
  - Added file headers with `@package Deploy_Forge` to all files
  - Converted to WordPress spacing conventions (spaces inside parentheses)
  - Implemented Yoda conditions throughout codebase
  - Added translators comments for all i18n strings with placeholders
  - Added `/* global */` declarations and JSDoc to JavaScript files
  - Updated CSS files with proper file headers

### Fixed
- Replaced `unlink()` with `wp_delete_file()` in debug logger
- Fixed `count()` in loop condition in debug logger
- Added `wp_unslash()` before sanitization in AJAX handler

## [1.0.32] - 2026-01-16

### Fixed
- **Deployment failures now properly reported to Deploy Forge dashboard**: Fixed bug where artifact-related errors (no artifacts found, missing artifact ID, missing workflow run ID) would update local WordPress status to "failed" but never notify the Deploy Forge platform
  - Added `report_status_to_backend()` call for "No workflow run ID or artifact information" error
  - Added `report_status_to_backend()` call for "No artifacts found for successful build" error
  - Added `report_status_to_backend()` call for "Artifact ID not found" error
  - Dashboard now shows accurate failure status instead of being stuck on "artifact_ready"

## [1.0.31] - 2026-01-14

### Fixed
- **Respect Build Folder Structure**: Plugin now preserves the exact folder structure from build artifacts
  - Previously, the plugin would search for `style.css`/`functions.php` and flatten the structure
  - Now looks for directory matching theme slug first, preserving intentional subfolder structures (e.g., `/theme` folder)
  - Fixes issue where builds with nested theme folders (like `fiordland-lobsters/theme/`) were incorrectly flattened
  - Maintains backwards compatibility with existing deployment structures

## [1.0.3] - 2026-01-14

### Changed
- Version bump to 1.0.3

## [1.0.22] - 2025-12-28

### Fixed
- **Manual deployments now tracked in Deploy Forge dashboard**: Manual deployments (both GitHub Actions and Direct Clone) now create deployment records on the Deploy Forge website
  - Added `trigger_remote_deployment()` method to call `/api/plugin/deployments/trigger` endpoint
  - Manual deployments now sync with Deploy Forge before triggering locally
  - Remote deployment ID is stored and linked to local deployment record
  - Fixes "No deployment found for workflow run" errors for manual deployments

## [1.0.21] - 2025-12-28

### Added
- **Deployment Status Callback**: Plugin now reports deployment outcomes (success/failure) back to Deploy Forge API
  - New `report_deployment_status()` method in `Deploy_Forge_GitHub_API` class
  - New `report_status_to_backend()` helper in `Deploy_Forge_Deployment_Manager` class
  - Calls `/api/plugin/deployments/complete` endpoint after deployments finish
  - Includes error messages and deployment logs for failed deployments
  - Works for both GitHub Actions and Direct Clone deployment methods

### Fixed
- Dashboard now shows accurate deployment status instead of being stuck on intermediate states (pending, ready, cloning)

## [1.0.0] - 2025-12-05

### 🚀 MAJOR RELEASE - Deploy Forge Platform Integration

This release represents a complete architectural shift from direct GitHub integration to a centralized Deploy Forge platform.

### ⚠️ BREAKING CHANGES
- **Removed Setup Wizard**: Connection now handled entirely by Deploy Forge platform
- **No Direct GitHub Integration**: Plugin now connects to Deploy Forge platform instead of GitHub directly
- **Repository Configuration**: Repository selection and GitHub App setup moved to platform
- **Credentials Structure**: Changed from GitHub token to platform API key + webhook secret
- **No Backward Compatibility**: Existing installations must reconnect through the platform

### Added
- **Deploy Forge Platform Integration**: Centralized platform for managing GitHub connections
- **Connection Handler**: New `Deploy_Forge_Connection_Handler` class for platform communication
- **Platform API Endpoints**: Integration with Deploy Forge website API
  - `/api/plugin/connect/init` - Initialize connection
  - `/api/plugin/auth/exchange-token` - Exchange connection token for credentials
  - `/api/plugin/auth/verify` - Verify connection status
  - `/api/plugin/auth/disconnect` - Disconnect from platform
  - `/api/plugin/github/proxy` - Proxy GitHub API requests
  - `/api/plugin/github/artifacts/:id/download` - Download artifacts
  - `/api/plugin/github/clone-token` - Get clone credentials
- **Simplified Settings UI**: Clean interface with platform-managed configuration
- **Read-Only Repository Info**: Display repository details configured on platform
- **Connection Callback Handler**: Seamless return from platform after setup

### Changed
- **Settings Class**: Refactored credential storage (API key, webhook secret, site ID)
- **GitHub API Class**: Updated to use Deploy Forge proxy endpoints
- **Webhook Handler**: Adapted for platform-forwarded webhooks
- **Admin Pages**: Simplified AJAX handlers for platform connection flow
- **Settings Template**: Replaced complex UI with simple connect/disconnect buttons
- **Admin JavaScript**: Updated connection methods for platform integration

### Removed
- **Setup Wizard**: Entire wizard system (7 template files, CSS, JS)
- **GitHub App Connector**: `class-github-app-connector.php` (replaced by platform)
- **Repository Selector**: UI for selecting repositories (now on platform)
- **Direct OAuth Flow**: No longer handled in plugin
- **Manual Webhook Configuration**: Automatically managed by platform

### Technical Details
- **Backend URL**: `https://getdeployforge.com`
- **API Key Format**: `df_live_[32 hex chars]`
- **Webhook Secret Format**: `whsec_[32 hex chars]`
- **Connection Flow**: Plugin → Platform Setup → Callback → Credential Exchange
- **Lines Changed**: 622 insertions, 4,032 deletions

### Migration Notes
- No automatic migration path from v0.5.x
- Users must disconnect existing GitHub integration (if any)
- Reconnect through new Deploy Forge platform
- Repository and deployment settings preserved after reconnection

### Developer Notes
- New connection handler abstracts platform communication
- Settings class now uses separate options for credentials
- GitHub API methods remain mostly unchanged (proxy layer updated)
- Webhook signature verification unchanged (uses platform secret)

## [0.5.31] - 2025-11-23

### Fixed
- Removed duplicate update notifications in WordPress admin plugin list

## [0.5.3] - 2025-11-21

### Added
- Added `Update URI` header to plugin file for native WordPress update support

## [0.5.2] - 2025-11-21

### Added
- Update checker for automated plugin version management
- Enhanced WP-Cron integration for reliable async deployments

### Fixed
- Corrected file location for update checker class
- Fixed workflow issues with tag conflicts

## [0.5.1] - 2025-11-21

### Added
- Initial beta release
- GitHub Actions automated release workflow
- Automated update distribution via update server
- GitHub App integration for repository access
- Automated theme deployment from GitHub repositories
- Webhook handling for deployment triggers
- Manual deployment triggers from WordPress admin
- Rollback capability to previous deployments
- Async deployment status tracking
- Real-time deployment progress monitoring
- GitHub Actions workflow template
- Comprehensive admin interface
- Settings preservation during updates

### Features
- **GitHub Integration**: Connect WordPress to private GitHub repositories
- **Auto Deployment**: Push to main branch triggers automatic deployment
- **Manual Control**: Deploy specific commits from WordPress admin
- **Status Monitoring**: Real-time progress tracking with status updates
- **Rollback Support**: Quick rollback to previous successful deployments
- **Secure**: OAuth-based GitHub App authentication
- **Async Processing**: Non-blocking deployments with progress updates

## [1.0.0] - 2024-11-21

### Added
- Initial release
- GitHub App integration for repository access
- Automated theme deployment from GitHub repositories
- Webhook handling for deployment triggers
- Manual deployment triggers from WordPress admin
- Rollback capability to previous deployments
- Async deployment status tracking
- Real-time deployment progress monitoring
- GitHub Actions workflow template
- Comprehensive admin interface
- Settings preservation during updates

### Features
- **GitHub Integration**: Connect WordPress to private GitHub repositories
- **Auto Deployment**: Push to main branch triggers automatic deployment
- **Manual Control**: Deploy specific commits from WordPress admin
- **Status Monitoring**: Real-time progress tracking with status updates
- **Rollback Support**: Quick rollback to previous successful deployments
- **Secure**: OAuth-based GitHub App authentication
- **Async Processing**: Non-blocking deployments with progress updates

[Unreleased]: https://github.com/jordanburch101/deploy-forge-client-plugin/compare/v0.5.3...HEAD
[0.5.3]: https://github.com/jordanburch101/deploy-forge-client-plugin/compare/v0.5.2...v0.5.3
[0.5.2]: https://github.com/jordanburch101/deploy-forge-client-plugin/compare/v0.5.1...v0.5.2
[0.5.1]: https://github.com/jordanburch101/deploy-forge-client-plugin/releases/tag/v0.5.1
[1.0.0]: https://github.com/jordanburch101/deploy-forge-client-plugin/releases/tag/v1.0.0
