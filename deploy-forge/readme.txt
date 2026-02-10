=== Deploy Forge ===
Contributors: deployforge
Tags: deployment, github, theme, automation, ci-cd
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.56
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automates WordPress theme deployment from GitHub repositories via GitHub Actions.

== Description ==

Deploy Forge automates WordPress theme deployment from GitHub repositories via GitHub Actions webhooks. Push code to GitHub, and it automatically deploys to your WordPress site.

= Features =

* **Automatic Deployments** - Deploy on commit via GitHub webhooks
* **GitHub Actions Integration** - Build themes with your existing workflow
* **Deployment History** - Track all deployments with logs
* **Automatic Backups** - Rollback to previous versions
* **Manual Approval Mode** - Control when deployments happen
* **Secure** - HMAC webhook validation and encrypted token storage
* **WordPress Admin UI** - Configure and monitor everything from your dashboard

= Requirements =

* WordPress 5.8+
* PHP 8.0+
* HTTPS enabled (for webhooks)
* GitHub repository with Actions enabled
* GitHub Personal Access Token

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/deploy-forge` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Deploy Forge → Settings to configure your repository details.
4. Create and enter your GitHub Personal Access Token.

= GitHub Setup =

1. Go to GitHub → Settings → Developer settings → Personal access tokens
2. Click "Generate new token (classic)"
3. Select scopes: `repo` (Full control of private repositories) and `actions` (workflow access)
4. Copy the token and enter it in the plugin settings

= Create GitHub Actions Workflow =

Create `.github/workflows/build-theme.yml` in your repository:

`
name: Build Theme

on:
  workflow_dispatch:
  push:
    branches: [ main ]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install dependencies
        run: npm install
      - name: Build theme
        run: npm run build
      - name: Upload artifact
        uses: actions/upload-artifact@v3
        with:
          name: theme-build
          path: |
            **/*
            !node_modules/**
            !.git/**
`

== Frequently Asked Questions ==

= What permissions does the GitHub token need? =

The token needs `repo` scope for repository access and `actions` scope for triggering workflows.

= Does this work with private repositories? =

Yes, as long as your GitHub Personal Access Token has the appropriate permissions.

= Can I rollback to a previous deployment? =

Yes, go to Deploy Forge → History, find a successful deployment, and click "Rollback".

= Is my GitHub token stored securely? =

Yes, tokens are encrypted using sodium cryptography before storage.

== Screenshots ==

1. Dashboard - Monitor deployment status and trigger manual deployments
2. Settings - Configure repository connection and deployment options
3. History - View deployment history with logs and rollback options

== Changelog ==

= 1.0.56 =
* Borderless deployment header with last deploy time, commit hash, and deployment count
* Fixed rollback button incorrectly showing on the active deployment

= 1.0.55 =
* Redesigned deployments table — 5 columns instead of 8, with colored status borders, status dots, relative timestamps, and merged deployment info column

= 1.0.52 =
* Active deployment tracking — "Active" badge shows which deployment's files are live
* File change detection — compare live theme files against the deployed state
* Unified diff viewer for modified files with syntax-highlighted dark theme
* Auto-detection of file drift on page load (cached in sessionStorage)
* Active badge moves correctly on rollback
* Automatic cleanup of old backup and snapshot files (keeps 10 most recent)
* Fixed rollback creating nested theme directories
* Fixed rollback not removing files added by newer deployments
* XSS hardening in deployment details and connection test UI

= 1.0.51 =
* Deployments header now shows repo name (linked to GitHub) and branch badge

= 1.0.50 =
* Fixed excessive spacing in changelog display in plugin update modal

= 1.0.49 =
* Removed debug console.log statements from production JavaScript
* Added Author URI plugin header

= 1.0.48 =
* Removed stats widget boxes from Deployments page (stats available in web app dashboard)

= 1.0.47 =
* Simplified admin UI: removed Dashboard page, promoted Deployments as the landing page
* Merged Deploy Now button, stats cards, and search/filter into Deployments page
* Added "View in Deploy Forge" button linking to the web app dashboard

= 1.0.46 =
* Self-hosted plugin updates via GitHub Releases — update notifications and one-click updates from the WordPress admin
* Site disconnected webhook event handler
* Version constant now reads from plugin header (single source of truth)

= 1.0.45 =
* Updated all URLs to getdeployforge.com

= 1.0.43 =
* Fix fatal error preventing error reporting to platform

= 1.0.42 =
* Enhanced deployment error reporting with rich debugging context

= 1.0.41 =
* Add CI/CD test integration to release workflow

= 1.0.0 =
* Initial release
* GitHub Actions integration
* Automatic and manual deployments
* Webhook support
* Backup and rollback functionality
* Admin interface

== Upgrade Notice ==

= 1.0.56 =
Minimal header with deployment details. Rollback button fix.

= 1.0.55 =
Enhanced deployments table with better visual hierarchy, relative timestamps, and status indicators.

= 1.0.52 =
Active deployment tracking, file change detection with diff viewer, rollback fixes, and automatic backup cleanup.

= 1.0.51 =
Deployments header shows repo and branch at a glance.

= 1.0.50 =
Fixes changelog formatting in the plugin update details modal.

= 1.0.49 =
Production JS cleanup and plugin header improvements.

= 1.0.48 =
Removed stats widgets from plugin — view stats in the Deploy Forge web app instead.

= 1.0.47 =
Simplified admin UI. Deployments is now the landing page with Deploy Now and stats built in.

= 1.0.46 =
Self-hosted update system. Plugin now checks GitHub Releases for updates.

= 1.0.45 =
Updated branding URLs. No breaking changes.
