# Changelog

All notable changes to Deploy Forge will be documented in this file.

## [1.0.46] - 2026-02-10

### Added
- Self-hosted plugin update system via GitHub Releases (`class-plugin-updater.php`)
- Plugin checks the public GitHub repository for new releases and integrates with WordPress's native update UI
- Users receive update notifications on the Plugins page and can update with one click
- "View details" modal shows changelog from the GitHub Release body
- Release data cached for 12 hours (1 hour on failure) to respect API rate limits

### Fixed
- Synced `DEPLOY_FORGE_VERSION` constant to `1.0.45` (was `1.0.43`, header already said `1.0.45`)
- Synced test bootstrap version constant to `1.0.45` (was `1.0.41`)

## [1.0.45] - 2025-02-09

### Changed
- Updated all URLs to getdeployforge.com

## [1.0.43] - 2025-02-08

### Fixed
- Fix fatal error preventing error reporting to platform
