# Changelog

All notable changes to Deploy Forge will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
- **Backend URL**: `https://deploy-forge-website.vercel.app`
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
