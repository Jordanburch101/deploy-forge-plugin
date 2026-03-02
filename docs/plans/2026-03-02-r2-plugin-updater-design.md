# R2-Based Plugin Update System

**Date:** 2026-03-02
**Status:** Approved

## Problem

The plugin self-updates by checking GitHub Releases (`api.github.com/repos/.../releases/latest`). The codebase is moving to a private monorepo, so public GitHub releases will no longer be accessible to WordPress sites.

## Solution

Replace the GitHub API-based update check with a Cloudflare R2 public bucket. The developer workflow stays the same (create GitHub Releases), but the CI pipeline uploads the ZIP and a JSON manifest to R2. The plugin reads the manifest instead of GitHub API.

## Flow

```
Developer                GitHub Actions              R2 Bucket              WordPress Sites
────────                ──────────────              ─────────              ───────────────
Create release ──────▶  Build ZIP (existing)
                        Upload ZIP to R2  ──────▶   /deploy-forge-1.0.65.zip
                        Write manifest    ──────▶   /manifest.json
                                                         │
                                                         ◀────── GET /manifest.json (every 6h)
                                                         ◀────── GET /deploy-forge-1.0.65.zip
                                                                 (when update triggered)
```

## R2 Bucket Structure

```
updates.deployforge.com/
├── manifest.json
├── deploy-forge-1.0.65.zip
├── deploy-forge-1.0.64.zip
└── ...
```

Old ZIPs accumulate but are not referenced by the manifest.

## manifest.json Schema

```json
{
  "name": "Deploy Forge",
  "slug": "deploy-forge",
  "version": "1.0.65",
  "download_url": "https://updates.deployforge.com/deploy-forge-1.0.65.zip",
  "requires": "5.8",
  "requires_php": "8.0",
  "tested": "6.9",
  "changelog": "### 1.0.65\n- Added feature X\n- Fixed bug Y",
  "published_at": "2026-03-02T12:00:00Z",
  "checksum_sha256": "abc123..."
}
```

Changelog is extracted from the GitHub Release body (already parsed from CHANGELOG.md by the release workflow).

## Changes Required

### 1. GitHub Actions release workflow (`release.yml`)

Add a step after the existing release creation:

- Upload built ZIP to R2 (via `wrangler` or S3-compatible `aws` CLI)
- Generate `manifest.json` from release metadata (version from tag, changelog from release body, download URL, SHA256 checksum from build.sh output)
- Upload `manifest.json` to R2
- New repository secrets: `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN`

### 2. Plugin updater class (`class-plugin-updater.php`)

Swap the data source:

- Replace `get_release_data()` to fetch `https://updates.deployforge.com/manifest.json` instead of GitHub API
- Simpler parsing — manifest is already in the exact shape needed
- Cache: **6h success**, **1h failure** (changed from 12h to propagate hotfixes faster)
- Same WordPress hooks: `pre_set_site_transient_update_plugins`, `plugins_api`
- `plugin_info()` reads changelog from manifest instead of GitHub release body
- Remove `github_api_request()` — replaced by simple `wp_remote_get()`
- Remove `parse_version()` — manifest has clean version string
- Remove asset extraction logic — manifest has direct download URL

### 3. Constants

- Add `DEPLOY_FORGE_UPDATE_URL` constant pointing to `https://updates.deployforge.com/manifest.json`
- Hardcoded — no admin setting needed

## What stays the same

- `build.sh` — no changes
- WordPress update UX — same "Update available" notice, changelog modal, one-click update
- GitHub Release creation — developer workflow unchanged
- Cache invalidation — transient cleared after successful update via `upgrader_process_complete`
- Version comparison logic

## What gets removed

- `github_api_request()` method
- `parse_version()` method
- GitHub release asset extraction logic
- GitHub API URL constant/reference

## Required GitHub Repository Secrets

These secrets must be configured in the GitHub repository settings before the first R2-powered release:

| Secret | Description | Example |
|--------|-------------|---------|
| `CLOUDFLARE_R2_ACCESS_KEY_ID` | R2 API token access key ID | `abc123...` |
| `CLOUDFLARE_R2_SECRET_ACCESS_KEY` | R2 API token secret access key | `xyz789...` |
| `CLOUDFLARE_R2_ENDPOINT` | R2 S3-compatible endpoint URL | `https://<account-id>.r2.cloudflarestorage.com` |
| `CLOUDFLARE_R2_BUCKET` | R2 bucket name | `deploy-forge-updates` |
| `CLOUDFLARE_R2_PUBLIC_URL` | Public URL for the bucket (custom domain) | `https://updates.deployforge.com` |

Generate R2 API tokens in the Cloudflare dashboard under R2 > Manage R2 API Tokens. The token needs `Object Read & Write` permission on the target bucket.
