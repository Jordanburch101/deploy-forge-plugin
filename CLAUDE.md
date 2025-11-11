# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Deploy Forge** - A WordPress plugin by Jordan Burch that automates theme deployment from GitHub repositories. When a developer commits to GitHub, the plugin triggers GitHub Actions to build the theme and automatically deploys compiled files to the WordPress theme directory.

## Architecture

### Workflow

1. Developer commits to GitHub → GitHub webhook notifies WordPress plugin
2. Plugin validates webhook and triggers GitHub Actions workflow
3. GitHub Actions builds theme (npm install, compile assets, etc.)
4. Plugin polls GitHub API for build completion
5. Plugin downloads artifact ZIP from GitHub
6. Plugin creates backup of current theme
7. Plugin extracts and deploys new theme files
8. Deployment logged to database with full details

### Key Components

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

**Core Classes:**

- `class-github-api.php` - GitHub REST API v3 wrapper using `wp_remote_request()`
- `class-deployment-manager.php` - Orchestrates deployment workflow, backup/rollback
- `class-webhook-handler.php` - REST API endpoint for GitHub webhooks with HMAC validation
- `class-database.php` - Custom tables for deployment history
- `class-settings.php` - Encrypted token storage and configuration
- `class-admin-pages.php` - Admin UI (settings, dashboard, history)

## Technology Stack

- **PHP:** 7.4+ (typed properties, arrow functions, null coalescing)
- **JavaScript:** Vanilla ES6+ (Fetch API, async/await) - NO frameworks
- **CSS:** WordPress admin styles + minimal custom CSS
- **HTTP Client:** `wp_remote_request()` (WordPress HTTP API, not cURL)
- **Database:** `wpdb` with custom tables (NOT post types)
- **Caching:** Transients API for GitHub API responses
- **Background Jobs:** WP_Cron for polling build status
- **File Operations:** `WP_Filesystem` API
- **Authentication:** GitHub Personal Access Token (encrypted with `sodium_crypto_secretbox()`)

## WordPress APIs Used

- **REST API:** Custom endpoints for webhooks, AJAX operations
- **Settings API:** Options page registration
- **Transients API:** Caching GitHub API responses
- **WP_Cron:** Every-minute polling for build completion
- **WP_Filesystem:** Safe file operations (backups, deployments)
- **Nonces:** CSRF protection on all forms and AJAX

## Database Schema

Custom table `{prefix}_deploy_forge_deployments`:

- `id` - Primary key
- `commit_hash` - 40 char SHA
- `commit_message`, `commit_author` - Git metadata
- `deployed_at` - Datetime
- `status` - enum: pending, building, success, failed, rolled_back
- `build_url`, `build_logs`, `deployment_logs` - Text fields
- `trigger_type` - enum: auto, manual, webhook
- `triggered_by_user_id` - WP user ID

## Project Structure

```
deploy-forge.php                   # Main plugin file with header
deploy-forge/
├── includes/
│   ├── class-database.php         # Schema and table creation
│   ├── class-github-api.php       # GitHub API wrapper
│   ├── class-deployment-manager.php  # Deployment orchestration
│   ├── class-webhook-handler.php  # REST endpoint for webhooks
│   └── class-settings.php         # Options management
├── admin/
│   ├── class-admin-pages.php      # Menu registration
│   ├── class-setup-wizard.php     # Setup wizard
│   ├── css/admin-styles.css       # Custom admin styles
│   └── js/admin-scripts.js        # AJAX handlers, UI logic
└── templates/
    ├── settings-page.php          # Configuration form
    ├── dashboard-page.php         # Status overview, deploy now
    ├── history-page.php           # Deployment log table
    └── setup-wizard/              # Setup wizard templates
```

## Security Requirements

- **Capability checks:** Only admins (`manage_options`)
- **Nonces:** All AJAX and form submissions
- **Input sanitization:** All user inputs via `sanitize_*()`
- **Output escaping:** All outputs via `esc_*()`
- **Token encryption:** `sodium_crypto_secretbox()` with WordPress salts
- **Webhook validation:** HMAC SHA256 signature verification
- **Path validation:** Prevent directory traversal in file operations
- **Rate limiting:** Webhook endpoint protection

## Scoping Notes

**v1.0 Scope (In):**

- Single repository/branch connection
- Theme deployment only (NOT plugins)
- GitHub Actions integration only
- Basic deployment history
- Manual and automatic modes
- Rollback to previous version
- Webhook support

**Out of Scope:**

- Plugin deployments
- Multiple repositories
- Multi-environment (staging/prod)
- Email/Slack notifications
- Deployment scheduling
- GitLab/Bitbucket support

## WordPress Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.2+
- Write permissions to `wp-content/themes`
- HTTPS (for webhooks)
- wp-cron enabled

## GitHub Requirements

- Personal Access Token with scopes: `repo`, `actions`
- GitHub Actions enabled
- Workflow file (e.g., `.github/workflows/build-theme.yml`)

## Development Principles

- **Minimize dependencies:** Use WordPress native functions over external libraries
- **No build process** for plugin itself (simple PHP/JS)
- **No Composer autoloader** (simple requires)
- **No jQuery plugins** (vanilla JS only)
- **No ORMs** (wpdb is sufficient)
- **Keep it simple:** WordPress best practices, maximum compatibility

## Documentation Policy

**Comprehensive specifications are located in the `spec/` directory.**

For detailed technical documentation, see:
- `spec/architecture.md` - System architecture and components
- `spec/requirements.md` - Functional and non-functional requirements
- `spec/database.md` - Database schema and operations
- `spec/api-integration.md` - GitHub API and webhook specifications
- `spec/security.md` - Security requirements and best practices
- `spec/deployment-workflow.md` - Deployment lifecycle and states
- `spec/testing.md` - Testing procedures and test cases
- `spec/CHANGELOG.md` - **Feature tracking and version history**

### Documentation Rules

**When making changes to the codebase:**

1. ✅ **Update `spec/CHANGELOG.md`** - Track all features, fixes, and changes with dates
2. ✅ **Update relevant spec files** - Keep technical docs in sync with implementation
3. ✅ **Create new spec files** - Only for documenting major new subsystems
4. ❌ **DO NOT create feature markdown files** - No files like `NEW-FEATURE.md`, `FEATURE-NOTES.md`, `IMPLEMENTATION.md`, etc.
5. ❌ **DO NOT add to `app-docs/`** - Legacy documentation directory (archived)

**Examples:**

**✅ Correct:**
```
- Add feature entry to spec/CHANGELOG.md with date (2025-11-09)
- Update spec/architecture.md if architecture changed
- Update spec/deployment-workflow.md if workflow changed
```

**❌ Incorrect:**
```
- Create app-docs/new-feature-2025-11-09.md
- Create NEW-WEBHOOK-HANDLER.md
- Create IMPLEMENTATION-NOTES.md
```

**Use `spec/CHANGELOG.md` as the single source for tracking changes, features, and notes.**
