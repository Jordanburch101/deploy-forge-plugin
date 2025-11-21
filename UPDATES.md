# Deploy Forge - Plugin Update System

## Overview

The Deploy Forge plugin includes an automatic update system that allows WordPress sites to receive plugin updates directly from the Deploy Forge update server, bypassing the WordPress.org repository.

This enables updates to be delivered from the private GitHub repository through a secure, authenticated process.

## How It Works

### Architecture

```
WordPress Site → Update Server → GitHub Releases
    (API Key)         (PAT)
```

**Two-Layer Authentication:**
1. **WordPress → Update Server**: API key from Deploy Forge backend
2. **Update Server → GitHub**: Personal Access Token (PAT)

### Components

1. **Update Checker Class** (`includes/class-update-checker.php`)
   - Integrates with WordPress update system
   - Checks for updates from update server
   - Handles plugin downloads
   - Manages update caching

2. **Update Server** (`https://updates.getdeployforge.com`)
   - Serves plugin updates from GitHub releases
   - Validates API keys
   - Caches release metadata
   - Rate limits requests

3. **GitHub Releases**
   - Source of truth for plugin versions
   - Created automatically via GitHub Actions
   - Contains plugin ZIP files

## Update Process

### Automatic Updates

WordPress automatically checks for plugin updates twice daily. Here's what happens:

1. **Update Check**
   ```
   WordPress → GET /api/updates/check/deploy-forge
   Headers: X-API-Key: {your-api-key}
   ```

2. **Update Server Response**
   ```json
   {
     "new_version": "0.5.2",
     "package": "https://updates.getdeployforge.com/api/updates/download",
     "url": "https://github.com/jordanburch101/deploy-forge",
     "tested": "6.4",
     "requires_php": "7.4"
   }
   ```

3. **WordPress Downloads Update**
   ```
   WordPress → GET /api/updates/download?version=0.5.2
   Headers: X-API-Key: {your-api-key}
   ```

4. **Update Server Streams ZIP**
   - Validates API key
   - Checks rate limits (10 downloads/day)
   - Streams ZIP from GitHub
   - Tracks analytics

5. **WordPress Installs Update**
   - Deactivates plugin
   - Replaces files
   - Reactivates plugin
   - Preserves settings

### Manual Updates

Users can manually check for updates:

1. Go to **Plugins** page in WordPress admin
2. Click **Check for Updates** button
3. If an update is available, click **Update Now**

### Update Notifications

The plugin displays update notifications:

- **Yellow badge** on Plugins menu when update available
- **Update row** on Plugins page with version info
- **Admin notice** if API key is not configured

## Configuration

### Requirements

For updates to work, the following must be configured:

1. **API Key** (Required)
   - Obtained from Deploy Forge backend
   - Stored in plugin settings
   - Used to authenticate with update server

2. **Backend URL** (Required)
   - URL of Deploy Forge backend API
   - Used to validate API key
   - Example: `https://api.deployforge.com`

### Setup

1. **Install Plugin**
   ```bash
   # Upload deploy-forge.zip to WordPress
   # Or install via WP-CLI
   wp plugin install deploy-forge.zip --activate
   ```

2. **Configure Settings**
   - Go to **Deploy Forge → Settings**
   - Enter Backend URL
   - Enter API Key
   - Save settings

3. **Verify Updates Work**
   - Go to **Plugins** page
   - Click **Check for Updates**
   - Should see current version or available update

### Troubleshooting

#### No Updates Showing

**Symptom**: WordPress says "You have the latest version" even though a newer version exists.

**Solutions**:
1. **Check API Key**: Ensure API key is configured in settings
2. **Check Backend Connection**: Verify backend URL is accessible
3. **Clear Cache**:
   ```php
   // In WordPress admin, run this in a temporary plugin:
   delete_transient('deploy_forge_update_' . md5('deploy-forge/deploy-forge.php'));
   ```
4. **Check Logs**: Look for errors in WordPress debug log
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

#### Update Download Fails

**Symptom**: Update check works, but download fails.

**Solutions**:
1. **Check Rate Limits**: You're limited to 10 downloads per day
2. **Check Server Status**: Visit `https://updates.getdeployforge.com/health`
3. **Verify GitHub Release**: Ensure release has ZIP file attached
4. **Check Permissions**: WordPress needs write access to plugins directory

#### API Key Not Working

**Symptom**: Admin notice says API key is missing or invalid.

**Solutions**:
1. **Regenerate Key**: Generate new API key from backend
2. **Copy Correctly**: Ensure no extra spaces in API key
3. **Check Backend**: Verify backend is accessible at configured URL

## Developer Information

### Update Checker API

The `Deploy_Forge_Update_Checker` class provides these methods:

```php
// Get update checker instance
$update_checker = Deploy_Forge()->update_checker;

// Manually trigger update check
$update_info = $update_checker->manual_check();

// Clear update cache
$update_checker->clear_cache();

// Change update server URL
$update_checker->set_update_server_url('https://custom-server.com');

// Change API key
$update_checker->set_api_key('new-api-key-here');
```

### WordPress Filters

The update system uses standard WordPress filters:

```php
// Modify update check response
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    // Custom logic
    return $transient;
});

// Modify plugin information modal
add_filter('plugins_api', function($result, $action, $args) {
    // Custom logic
    return $result;
}, 10, 3);
```

### Caching

Update information is cached for 12 hours to reduce server load:

- **Cache Key**: `deploy_forge_update_{md5(plugin_basename)}`
- **Duration**: 43200 seconds (12 hours)
- **Storage**: WordPress transients (database)

Cache is automatically cleared when:
- User clicks "Check for Updates"
- User visits update-core.php page
- 12 hours have elapsed

### Rate Limiting

The update server enforces rate limits:

- **Update Checks**: 60 per hour per site
- **Downloads**: 10 per day per site
- **Plugin Info**: 60 per hour per site

Rate limits are per API key (per WordPress site).

## Security

### Authentication

- **API Keys**: SHA-256 hashed before storage in Redis
- **HTTPS Only**: All communication over HTTPS
- **No Token Exposure**: GitHub PAT never sent to WordPress

### Validation

- **Version Comparison**: Semantic versioning enforced
- **Signature Verification**: ZIP files validated
- **Domain Binding**: API keys tied to specific domains

### Privacy

- **Minimal Data**: Only site ID and version tracked
- **No Personal Info**: No user data collected
- **Analytics**: Basic usage stats (update checks, downloads)

## Update Server

The update server runs at `https://updates.getdeployforge.com`

### Endpoints

- `GET /health` - Server health check
- `GET /api/updates/check/:slug` - Check for updates
- `GET /api/updates/download` - Download plugin ZIP
- `POST /api/updates/info` - Get plugin information
- `POST /api/cache/invalidate` - Invalidate cache (webhook)

### Monitoring

Check server status:

```bash
curl https://updates.getdeployforge.com/health
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2024-11-21T10:30:00.000Z",
  "checks": {
    "redis": true,
    "github": true
  }
}
```

## Release Process

New versions are released automatically via GitHub Actions:

1. **Update Version**: Edit version in `deploy-forge.php`
2. **Update Changelog**: Add entry to `CHANGELOG.md`
3. **Commit Changes**: Push to main branch
4. **Create Tag**: `git tag v0.5.2 && git push origin v0.5.2`
5. **GitHub Actions**: Builds ZIP and creates release
6. **Update Server**: Cache is invalidated automatically
7. **WordPress Sites**: Receive update within 12 hours (or immediately if manually checked)

## Support

For issues with the update system:

1. **Check Logs**: Enable WordPress debug logging
2. **Test Manually**: Try manual update check
3. **Verify Server**: Check update server health endpoint
4. **Report Issue**: GitHub Issues with error logs

---

**Version**: 0.5.1
**Last Updated**: 2024-11-21
**Documentation**: [https://github.com/jordanburch101/deploy-forge-client-plugin](https://github.com/jordanburch101/deploy-forge-client-plugin)
