# Changelog

All notable changes to Deploy Forge will be documented in this file.

## [1.0.62] - 2026-02-19

### Fixed
- Drift indicator pill spacing: changed `margin-left: 6px` to `margin-top: 6px; margin-left: 0` so the pill sits below the status badge instead of beside it
- Fatal error in "View Diff": `Text_Diff_Renderer_unified` never existed in WordPress core — defined a minimal unified renderer inline that extends the base `Text_Diff_Renderer` class

## [1.0.58] - 2026-02-10

### Added
- Bundled Sentry error telemetry (opt-out) for anonymous error reporting
- New `Deploy_Forge_Error_Telemetry` class using PHP-Scoper-prefixed Sentry SDK (`DeployForge\Vendor\Sentry`)
- `before_send` filter ensures only errors originating from Deploy Forge plugin files are captured
- Error Reporting checkbox in Settings > Deployment Options (enabled by default)
- `error_telemetry` setting in defaults, save handler, and sanitizer
- PHP-Scoper config (`scoper.inc.php`) and composer `scope` script
- Build script step to verify `vendor-prefixed/` directory before packaging

## [1.0.57] - 2026-02-10

### Changed
- shadcn/ui-inspired table styling pass (CSS-only, no template/JS changes)
- Table container: rounded corners (8px), subtle shadow, clean white background
- Removed alternating row stripes; hover now uses clean #fafafa highlight
- Header row gets #fafafa background with 1px bottom border (from 2px)
- Status badges: pill-shaped (9999px radius), softer Tailwind pastel colors, lighter font weight
- Active badge and drift indicator also pill-shaped
- Table action buttons: ghost style (transparent bg/border) until hovered
- Cancel button: red ghost, fills red on hover
- Search/filter inputs: lighter border, ring-style focus outlines
- CSS variables shifted to zinc palette (--df-bg-secondary: #fafafa, --df-border-secondary: #e4e4e7)
- Border radii increased (sm: 4px, md: 6px, lg: 8px)
- Responsive: comfortable padding at 782px breakpoint

## [1.0.56] - 2026-02-10

### Changed
- Minimal header with deployment details, changed link text to "View Dashboard"
- Fix rollback on active deployment

## [1.0.55] - 2026-02-10

### Changed
- Redesigned deployments table: reduced from 8 columns to 5 (Deployment, Date, Author, Status, Actions)
- Merged Commit/Message/Trigger columns into a single "Deployment" column with commit message, hash link, and trigger label
- Status badges now include colored status dots for better visual scanning
- Table rows have colored left borders matching deployment status
- Active deployment row gets a subtle green tint
- Dates display as relative timestamps ("3h ago", "2 days ago") with full date on hover tooltip
- Relative timestamps auto-refresh every 60 seconds
- Search input includes a search icon
- Author column hidden on mobile (< 782px)
- Table uses `border-collapse: separate` with enhanced row spacing and hover states

## [1.0.54] - 2026-02-10

### Fixed
- Deployment status stuck on "deploying" after successful deploy — split status update into separate DB call from file manifest write so charset/encoding issues in the manifest don't block the status change
- Added error logging for silent `update_deployment` failures

## [1.0.53] - 2026-02-10

### Changed
- Deploy and rollback now use atomic directory swap (copy to staging, rename swap) instead of deleting the theme directory before copying — prevents broken state if the copy fails mid-way
- Unified deployment cleanup: `run_full_cleanup()` prunes backup/snapshot files by count AND deletes DB rows + files older than 90 days
- Post-deploy cleanup now calls `run_full_cleanup()` instead of only `cleanup_old_deployment_files()`

### Added
- `safe_swap_directory()` private helper for atomic rename-swap of directories
- `run_full_cleanup()` public method combining file and DB row cleanup
- `get_old_deployments()` database method to fetch deployments older than N days with file paths
- Daily WP-Cron schedule (`deploy_forge_daily_cleanup`) ensures cleanup runs even without new deployments
- Cron hook is registered on plugin activation and cleared on deactivation

## [1.0.52] - 2026-02-10

### Added
- Active deployment tracking via `deploy_forge_active_deployment_id` WP option
- "Active" badge on the deployment whose files are currently live on disk
- File change detection: compares live theme files against stored file manifest (SHA-256 hashes)
- Unified diff viewer for modified files using WordPress core `Text_Diff` library
- "Check Changes" button on active deployment row to manually trigger file drift scan
- Auto-check on page load with 5-minute sessionStorage cache for drift indicator
- File Changes modal with summary bar (modified/added/removed counts) and file list table
- File Diff modal with dark-theme syntax highlighting (additions, deletions, hunks, context)
- Database migration 1.2: `file_manifest` (LONGTEXT) and `snapshot_path` (varchar) columns
- `generate_file_manifest()` — creates SHA-256 hash map of deployed files
- `create_deployment_snapshot()` — creates post-deployment ZIP snapshot
- `detect_file_changes()` — compares stored manifest against live directory
- `get_file_diff()` — generates unified diff with path traversal protection
- `get_active_deployment_id()`, `set_active_deployment_id()`, `get_active_deployment()`, `get_previous_successful_deployment()` database methods

### Changed
- `extract_and_deploy()` now generates file manifest, creates snapshot, and sets active deployment ID on success
- `rollback_deployment()` now updates active deployment ID to the previous successful deployment and refreshes its manifest
- Automatic cleanup of old backup and snapshot ZIPs — only the 10 most recent are kept
- Settings page notes the backup retention policy

### Fixed
- Rollback now correctly copies files from the inner theme directory within the backup ZIP (previously created a nested `theme/theme/` structure)
- Rollback now clears the theme directory before restoring, preventing orphan files from newer deployments surviving the rollback
- XSS protection: all dynamic content in deployment details and connection test results is now HTML-escaped
- HTML attribute injection: `escapeHtml` now also encodes quotes for safe use in attributes

## [1.0.47] - 2026-02-10

### Changed
- Simplified admin UI from 4 pages to 3: Deployments (landing), Settings, Debug Logs
- Removed Dashboard page (redundant with Deploy Forge web app dashboard)
- Renamed History page to Deployments and made it the plugin landing page
- Merged Deploy Now button and stats cards from Dashboard into Deployments page
- Added search/filter controls to Deployments table

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

### Fixed
- Synced `DEPLOY_FORGE_VERSION` constant to `1.0.45` (was `1.0.43`, header already said `1.0.45`)
- Synced test bootstrap version constant to `1.0.45` (was `1.0.41`)

## [1.0.45] - 2025-02-09

### Changed
- Updated all URLs to getdeployforge.com

## [1.0.43] - 2025-02-08

### Fixed
- Fix fatal error preventing error reporting to platform
