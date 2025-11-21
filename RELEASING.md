# Deploy Forge - Release Process

This document describes how to create a new release of the Deploy Forge plugin using the automated GitHub Actions workflow.

## Quick Start

To release version **1.2.0**:

```bash
# 1. Update version in plugin file
# Edit deploy-forge.php: Version: 1.2.0

# 2. Update CHANGELOG.md
# Add section: ## [1.2.0] - 2024-11-21

# 3. Commit changes
git add deploy-forge.php CHANGELOG.md
git commit -m "Bump version to 1.2.0"
git push origin main

# 4. Create and push tag (THIS triggers the release)
git tag v1.2.0
git push origin v1.2.0
```

**That's it!** GitHub Actions will automatically:
- ✅ Build the production ZIP
- ✅ Create a GitHub Release
- ✅ Attach the ZIP as a downloadable asset
- ✅ Notify the update server
- ✅ Make the update available to WordPress sites

---

## Semantic Versioning

We follow [Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`

### When to bump each version number:

**MAJOR version** (1.0.0 → 2.0.0):
- Breaking changes that affect existing functionality
- Removed features or public APIs
- Changed behavior that requires user action
- Incompatible API changes

**Examples**:
- Removing a hook that other plugins depend on
- Changing database schema in incompatible way
- Requiring higher PHP/WordPress version
- Restructuring plugin files/classes

**MINOR version** (1.0.0 → 1.1.0):
- New features added (backwards-compatible)
- Enhanced existing functionality
- New hooks/filters/APIs added
- Deprecated features (but not removed)

**Examples**:
- Adding new admin settings page
- New deployment options
- Additional GitHub integrations
- Performance improvements

**PATCH version** (1.0.0 → 1.0.1):
- Bug fixes
- Security patches
- Small improvements
- Documentation updates

**Examples**:
- Fixed webhook signature validation bug
- Corrected typos in admin interface
- Improved error messages
- Updated translations

---

## Detailed Release Process

### Step 1: Prepare the Release

#### 1.1 Update Version in Plugin File

Edit `deploy-forge.php`:

```php
/**
 * Plugin Name: Deploy Forge
 * Plugin URI: https://github.com/jordanburch101/deploy-forge
 * Description: Automates theme deployment from GitHub repositories using GitHub Actions
 * Version: 1.2.0  ← UPDATE THIS
 * Author: Jordan Burch
 * Author URI: https://jordanburch.dev
 */
```

#### 1.2 Update CHANGELOG.md

Add a new section for your version:

```markdown
## [1.2.0] - 2024-11-21

### Added
- New feature: Scheduled deployments
- Support for deployment notifications via email

### Fixed
- Bug where webhook timeouts caused failed deployments
- Memory leak in deployment status polling

### Changed
- Improved admin UI performance
- Updated dependencies to latest versions

[1.2.0]: https://github.com/jordanburch101/deploy-forge-client-plugin/compare/v1.1.0...v1.2.0
```

**Changelog Tips**:
- Use categories: Added, Changed, Deprecated, Removed, Fixed, Security
- Write from user perspective (not technical implementation)
- Link issues/PRs if applicable
- Be concise but descriptive

#### 1.3 Test the Changes

Before releasing, ensure:
- [ ] Plugin works correctly with updated version
- [ ] No console errors or PHP warnings
- [ ] All features work as expected
- [ ] Database migrations run successfully (if any)
- [ ] Settings are preserved (if structure changed)

### Step 2: Commit Version Bump

```bash
# Stage the version changes
git add deploy-forge.php CHANGELOG.md

# Commit with clear message
git commit -m "Bump version to 1.2.0"

# Push to main branch
git push origin main
```

**Note**: This push does NOT trigger a release. Only the tag does.

### Step 3: Create Release Tag

```bash
# Create annotated tag (recommended)
git tag -a v1.2.0 -m "Release version 1.2.0"

# Or lightweight tag (simpler)
git tag v1.2.0

# Push the tag (THIS TRIGGERS THE RELEASE)
git push origin v1.2.0
```

**Important**:
- Tag must start with `v` (e.g., `v1.2.0`, not `1.2.0`)
- Tag must match version in plugin file exactly
- Tag must follow semantic versioning format

### Step 4: GitHub Actions Runs Automatically

Once you push the tag, GitHub Actions will:

1. **Validate** the tag format
2. **Verify** version in plugin file matches tag
3. **Build** the production ZIP (excluding dev files)
4. **Extract** changelog for this version
5. **Create** GitHub Release
6. **Upload** ZIP as release asset
7. **Notify** update server (if configured)

**Monitor the workflow**:
- Go to: https://github.com/jordanburch101/deploy-forge-client-plugin/actions
- Click on the "Release Plugin" workflow
- Watch the progress in real-time

### Step 5: Verify the Release

After the workflow completes:

1. **Check GitHub Releases**:
   - Go to: https://github.com/jordanburch101/deploy-forge-client-plugin/releases
   - Verify your release appears with:
     - Correct version number
     - ZIP file attached
     - Changelog displayed
     - Release notes generated

2. **Test Download**:
   ```bash
   curl -L -O https://github.com/jordanburch101/deploy-forge-client-plugin/releases/download/v1.2.0/deploy-forge-1.2.0.zip
   ```

3. **Verify Update Server** (if deployed):
   ```bash
   # Check update server sees new version
   curl https://updates.deployforge.com/api/updates/check/deploy-forge
   ```

   Should return:
   ```json
   {
     "version": "1.2.0",
     "download_url": "https://updates.deployforge.com/api/updates/download",
     ...
   }
   ```

---

## Workflow Details

### What Gets Excluded from ZIP

See `.distignore` for the complete list. Key exclusions:
- `.git` and `.github` directories
- Development tools (`.vscode`, `.claude`, etc.)
- Build artifacts (`build/`, `dist/`, `node_modules/`)
- Documentation (`spec/`, `TESTING-*.md`, etc.)
- CI/CD files
- Test files

### Validation Checks

The workflow performs these validations:

1. **Tag Format**: Must be `v*.*.*` (e.g., v1.0.0)
2. **Version Match**: Tag must match version in `deploy-forge.php`
3. **No Pre-release Tags**: Ignores tags like `v1.0.0-beta`, `v1.0.0-rc1`

If any validation fails, the workflow stops and shows an error.

### Changelog Extraction

The workflow automatically extracts changelog from `CHANGELOG.md`:
- Looks for section: `## [1.2.0]`
- Extracts content until next version header
- Includes in GitHub Release description
- Falls back to generic message if not found

---

## Troubleshooting

### Error: "Version mismatch detected"

**Problem**: Version in `deploy-forge.php` doesn't match git tag.

**Solution**:
```bash
# Fix the version in deploy-forge.php
# Then update the tag:
git add deploy-forge.php
git commit -m "Fix version to 1.2.0"
git push origin main

# Move the tag
git tag -f v1.2.0
git push -f origin v1.2.0
```

### Error: "Tag must match v*.*.* format"

**Problem**: Tag doesn't follow semantic versioning.

**Solution**: Delete and recreate tag with correct format:
```bash
# Delete incorrect tag
git tag -d 1.2.0  # wrong format
git push origin :refs/tags/1.2.0

# Create correct tag
git tag v1.2.0  # correct format (with v prefix)
git push origin v1.2.0
```

### Release Didn't Trigger

**Problem**: Pushed tag but no workflow ran.

**Check**:
1. Did you push the tag? `git push origin v1.2.0`
2. Is tag format correct? Must be `v*.*.*`
3. Is it a pre-release tag? (`-beta`, `-rc` are ignored)
4. Check GitHub Actions tab for errors

### Workflow Failed During Build

**Problem**: Build script failed.

**Solution**:
1. Check workflow logs in GitHub Actions
2. Test build locally: `./build-plugin.sh`
3. Fix any errors
4. Commit fixes and re-tag

### Update Server Not Getting New Version

**Problem**: WordPress sites not seeing update.

**Check**:
1. Is update server deployed?
2. Is `UPDATE_SERVER_URL` secret configured in GitHub?
3. Check update server logs
4. Manually invalidate cache:
   ```bash
   curl -X POST \
     https://updates.deployforge.com/api/cache/invalidate
   ```

---

## Pre-Release Versions

For beta/RC releases, use tags with suffixes:

```bash
# Beta releases
git tag v1.2.0-beta.1
git push origin v1.2.0-beta.1

# Release candidates
git tag v1.2.0-rc.1
git push origin v1.2.0-rc.1

# Alpha releases
git tag v1.2.0-alpha.1
git push origin v1.2.0-alpha.1
```

**Note**: These will NOT trigger the automated release workflow (intentionally ignored).

To release a pre-release version, you would need to:
1. Manually create the release in GitHub UI
2. Or modify the workflow to include pre-release tags

---

## Manual Release (Without GitHub Actions)

If you need to create a release manually:

```bash
# 1. Build the plugin
./build-plugin.sh

# 2. Create GitHub Release via CLI
gh release create v1.2.0 \
  dist/deploy-forge-1.2.0.zip \
  --title "Version 1.2.0" \
  --notes "See CHANGELOG.md for details"

# Or create via GitHub web interface:
# Go to: https://github.com/jordanburch101/deploy-forge-client-plugin/releases/new
```

---

## Best Practices

1. **Always update CHANGELOG.md** before releasing
2. **Test locally** before tagging
3. **Use semantic versioning** consistently
4. **Write clear commit messages** for version bumps
5. **Monitor GitHub Actions** for workflow success
6. **Verify downloads work** after release
7. **Keep version numbers in sync** across all files
8. **Tag from main branch** only
9. **Never force push tags** to main (creates confusion)
10. **Document breaking changes** prominently in CHANGELOG

---

## Rollback a Release

If a release has issues:

### Option 1: Quick Patch Release

```bash
# Fix the issue
# Bump to next patch version (e.g., 1.2.0 → 1.2.1)
# Follow normal release process
```

### Option 2: Delete Release (GitHub UI)

1. Go to Releases page
2. Click "Edit" on problematic release
3. Click "Delete this release"
4. Delete the tag: `git push origin :refs/tags/v1.2.0`

**Note**: WordPress sites that already downloaded won't rollback automatically.

---

## Questions?

- Check the [GitHub Actions workflow](.github/workflows/release.yml)
- Review the [build script](build-plugin.sh)
- See [CHANGELOG.md](CHANGELOG.md) for examples

**Need help?** Open an issue in the repository.
