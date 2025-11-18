# Plugin Update Checker - WordPress Plugin Side

**Last Updated:** 2025-11-18

## Overview

The Plugin Update Checker enables Deploy Forge to receive automatic updates from private GitHub releases without relying on the WordPress.org plugin repository. This is essential for proprietary plugins hosted in private repositories.

## Purpose

- Enable automatic plugin updates for sites using Deploy Forge
- Support private GitHub repository releases
- Provide seamless WordPress update experience (dashboard notifications, one-click updates)
- Maintain security through authenticated update server proxy

## Architecture

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────────┐
│   WordPress     │────────▶│  Update Server   │────────▶│     GitHub      │
│   Plugin        │  check  │   (Vercel)       │   API   │   Releases      │
│                 │◀────────│   Express Proxy  │◀────────│   (Private)     │
└─────────────────┘ update  └──────────────────┘  fetch  └─────────────────┘
```

### Update Flow

1. **WordPress checks for updates** (twice daily via wp-cron)
2. **Plugin queries update server** with current version
3. **Update server fetches releases** from GitHub API
4. **Server returns update info** (version, download URL, changelog)
5. **WordPress shows update notification** in admin dashboard
6. **User clicks update** in WordPress admin
7. **WordPress downloads from update server** (proxied GitHub release)
8. **Plugin installs and activates** new version

## Core Components

### 1. Update Checker Class

**File:** `includes/class-update-checker.php`

**Purpose:** Integrate with WordPress update API to check for and install plugin updates

**Key Methods:**

```php
class Deploy_Forge_Update_Checker {
    /**
     * Initialize update checker hooks
     */
    public function __construct() {}

    /**
     * Check for plugin updates
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient with update info
     */
    public function check_for_updates( $transient ) {}

    /**
     * Query update server for latest version
     *
     * @return array|WP_Error Update information or error
     */
    private function query_update_server() {}

    /**
     * Get plugin information for WordPress update screen
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return object Plugin information
     */
    public function get_plugin_info( $result, $action, $args ) {}

    /**
     * Rename downloaded plugin folder to match expected structure
     *
     * @param string $source Source path
     * @param string $remote_source Remote source path
     * @param WP_Upgrader $upgrader Upgrader instance
     * @return string|WP_Error New source path or error
     */
    public function rename_plugin_folder( $source, $remote_source, $upgrader ) {}

    /**
     * Clear update cache when manually checking for updates
     */
    public function clear_update_cache() {}

    /**
     * Parse semantic version string
     *
     * @param string $version Version string (e.g., "1.2.3", "v1.2.3")
     * @return array Parsed version components
     */
    private function parse_version( $version ) {}

    /**
     * Compare two semantic versions
     *
     * @param string $version1 First version
     * @param string $version2 Second version
     * @return int -1 if v1 < v2, 0 if equal, 1 if v1 > v2
     */
    private function compare_versions( $version1, $version2 ) {}
}
```

### 2. WordPress Hooks Integration

**Hooks Used:**

```php
// Check for updates (runs twice daily by default)
add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );

// Provide plugin information for details modal
add_filter( 'plugins_api', [ $this, 'get_plugin_info' ], 20, 3 );

// Fix plugin folder name after download (GitHub releases use repo-name format)
add_filter( 'upgrader_source_selection', [ $this, 'rename_plugin_folder' ], 10, 3 );

// Clear cache when manually checking updates
add_action( 'load-update-core.php', [ $this, 'clear_update_cache' ] );
add_action( 'load-plugins.php', [ $this, 'clear_update_cache' ] );
```

## Update Server Communication

### Endpoint

**URL:** `https://your-update-server.vercel.app/api/update-check`

**Method:** POST

**Headers:**
```
Content-Type: application/json
User-Agent: WordPress/{wp_version}; {site_url}
```

**Request Body:**
```json
{
  "plugin": "deploy-forge",
  "version": "1.0.0",
  "php_version": "8.1.0",
  "wp_version": "6.4.0",
  "site_url": "https://example.com"
}
```

**Response (Update Available):**
```json
{
  "version": "1.1.0",
  "download_url": "https://your-update-server.vercel.app/api/download/deploy-forge/1.1.0",
  "requires": "5.8",
  "requires_php": "7.4",
  "tested": "6.4",
  "last_updated": "2025-11-18 12:00:00",
  "upgrade_notice": "This update includes security fixes. Update immediately.",
  "changelog": {
    "1.1.0": [
      "Added: Plugin update checker",
      "Fixed: Security vulnerability in webhook handler",
      "Improved: GitHub API error handling"
    ],
    "1.0.1": [
      "Fixed: Deployment race condition",
      "Improved: Logging performance"
    ]
  },
  "sections": {
    "description": "Automates theme deployment from GitHub repositories using GitHub Actions",
    "installation": "Upload and activate the plugin...",
    "changelog": "See full changelog above"
  }
}
```

**Response (No Update Available):**
```json
{
  "version": "1.0.0",
  "message": "Plugin is up to date"
}
```

**Response (Error):**
```json
{
  "error": true,
  "message": "Failed to fetch release information from GitHub"
}
```

### Download Endpoint

**URL:** `https://your-update-server.vercel.app/api/download/{plugin}/{version}`

**Method:** GET

**Parameters:**
- `plugin` - Plugin slug (e.g., "deploy-forge")
- `version` - Version to download (e.g., "1.1.0")

**Response:**
- Content-Type: `application/zip`
- Content-Disposition: `attachment; filename="deploy-forge-1.1.0.zip"`
- Body: ZIP file containing plugin

**Security:**
- Server validates plugin slug against whitelist
- Server validates version format (semver)
- Server uses GitHub API with authentication to fetch private releases
- Server streams download (doesn't store releases locally)

## Configuration

### Constants

```php
// Update server URL (can be overridden in wp-config.php)
if ( ! defined( 'DEPLOY_FORGE_UPDATE_SERVER' ) ) {
    define( 'DEPLOY_FORGE_UPDATE_SERVER', 'https://deploy-forge-updates.vercel.app' );
}

// Update check interval (seconds, default: 12 hours)
if ( ! defined( 'DEPLOY_FORGE_UPDATE_INTERVAL' ) ) {
    define( 'DEPLOY_FORGE_UPDATE_INTERVAL', 12 * HOUR_IN_SECONDS );
}

// Disable update checker (for development)
if ( ! defined( 'DEPLOY_FORGE_DISABLE_UPDATES' ) ) {
    define( 'DEPLOY_FORGE_DISABLE_UPDATES', false );
}
```

### Settings

No UI settings required. Update checker works automatically using constants above.

For debugging, add option to Settings page (Advanced section):

- **Update Server URL** - Text field (default: constant value)
- **Last Update Check** - Display only, shows timestamp
- **Check Now** - Button to manually trigger update check

## Caching Strategy

### Update Cache

WordPress handles update caching via transients:
- **Transient:** `update_plugins`
- **Duration:** 12 hours (default)
- **Cleared:** When manually checking for updates

### Release Info Cache

Plugin-specific caching for release details:
- **Transient:** `deploy_forge_update_info`
- **Duration:** 6 hours
- **Data:** Full release information from update server
- **Purpose:** Reduce API calls to update server

## Error Handling

### Network Errors

```php
// If update server is unreachable, fail silently
// Don't block WordPress updates for other plugins
if ( is_wp_error( $response ) ) {
    error_log( 'Deploy Forge: Update check failed - ' . $response->get_error_message() );
    return $transient; // Return unchanged
}
```

### Invalid Response

```php
// If response is malformed, log and skip
if ( ! isset( $data['version'] ) ) {
    error_log( 'Deploy Forge: Invalid update response from server' );
    return $transient;
}
```

### Download Errors

```php
// If download fails, WordPress shows error to user
// Provide clear error message with support contact
add_filter( 'upgrader_pre_download', function( $reply, $package, $upgrader ) {
    if ( strpos( $package, 'deploy-forge-updates.vercel.app' ) !== false ) {
        // Custom error handling for our update server
    }
    return $reply;
}, 10, 3 );
```

## Security Considerations

### HTTPS Required

- Update server MUST use HTTPS
- WordPress HTTPS verification enabled by default
- No plaintext communication allowed

### Signature Verification

**Optional but Recommended:**

```php
// Verify package integrity using SHA-256 hash
if ( ! empty( $data['package_hash'] ) ) {
    $downloaded_hash = hash_file( 'sha256', $downloaded_file );
    if ( $downloaded_hash !== $data['package_hash'] ) {
        return new WP_Error( 'hash_mismatch', 'Package integrity check failed' );
    }
}
```

### Rate Limiting

- WordPress already rate-limits update checks (twice daily)
- Update server should implement additional rate limiting
- Server should block IPs with excessive requests

### Authentication

Update server uses GitHub Personal Access Token (server-side only):
- Token stored in Vercel environment variables
- Never exposed to WordPress plugin
- Token only used server-to-server

WordPress plugin requires NO authentication to check updates:
- Public update check endpoint
- Private GitHub releases fetched server-side
- Download URLs are temporary/signed (optional)

## Version Comparison Logic

### Semantic Versioning

Plugin follows semver: `MAJOR.MINOR.PATCH`

Examples:
- `1.0.0` → `1.0.1` (patch update)
- `1.0.1` → `1.1.0` (minor update)
- `1.1.0` → `2.0.0` (major update)

### Version Parsing

```php
/**
 * Parse version string (handles "v1.0.0" and "1.0.0")
 */
private function parse_version( $version ) {
    $version = ltrim( $version, 'vV' ); // Remove v prefix
    $parts = explode( '.', $version );

    return [
        'major' => (int) ( $parts[0] ?? 0 ),
        'minor' => (int) ( $parts[1] ?? 0 ),
        'patch' => (int) ( $parts[2] ?? 0 ),
    ];
}
```

### Comparison

```php
/**
 * Compare two versions using semantic versioning rules
 *
 * @return int -1 if v1 < v2, 0 if equal, 1 if v1 > v2
 */
private function compare_versions( $version1, $version2 ) {
    $v1 = $this->parse_version( $version1 );
    $v2 = $this->parse_version( $version2 );

    // Compare major
    if ( $v1['major'] !== $v2['major'] ) {
        return $v1['major'] <=> $v2['major'];
    }

    // Compare minor
    if ( $v1['minor'] !== $v2['minor'] ) {
        return $v1['minor'] <=> $v2['minor'];
    }

    // Compare patch
    return $v1['patch'] <=> $v2['patch'];
}
```

## WordPress Integration

### Update Notification

WordPress shows update notification in:
- **Plugins page** - "Update available" badge
- **Dashboard** - Plugin updates widget
- **Updates page** - Bulk update checkbox

### Plugin Details Modal

When user clicks "View details":
- Modal shows plugin information
- Tabs: Description, Installation, Changelog
- Data comes from `plugins_api` filter

### One-Click Update

User clicks "Update Now":
1. WordPress downloads from `download_url`
2. WordPress extracts ZIP
3. `upgrader_source_selection` hook renames folder
4. WordPress replaces old plugin
5. WordPress activates if was active before

## Folder Naming Issue

**Problem:** GitHub release ZIPs use format `repo-name-{tag}` instead of `deploy-forge`

**Example:**
- GitHub ZIP contains: `deploy-forge-1.1.0/`
- WordPress expects: `deploy-forge/`

**Solution:** Hook into `upgrader_source_selection` filter

```php
public function rename_plugin_folder( $source, $remote_source, $upgrader ) {
    global $wp_filesystem;

    // Only process our plugin updates
    if ( ! isset( $upgrader->skin->plugin ) ||
         $upgrader->skin->plugin !== DEPLOY_FORGE_PLUGIN_BASENAME ) {
        return $source;
    }

    // Expected folder name
    $desired_folder = 'deploy-forge';

    // Extract current folder name
    $path_parts = explode( '/', trim( $source, '/' ) );
    $current_folder = array_pop( $path_parts );

    // If already correct, do nothing
    if ( $current_folder === $desired_folder ) {
        return $source;
    }

    // Rename folder
    $new_source = implode( '/', $path_parts ) . '/' . $desired_folder . '/';

    if ( $wp_filesystem->move( $source, $new_source ) ) {
        return $new_source;
    }

    return new WP_Error( 'rename_failed', 'Could not rename plugin folder' );
}
```

## Testing

### Manual Testing

**Test Update Available:**
1. Set current version to `1.0.0`
2. Publish GitHub release `1.1.0`
3. Go to WordPress Dashboard → Updates
4. Click "Check Again"
5. Verify update notification appears
6. Click "View details" - verify modal shows changelog
7. Click "Update Now" - verify update installs successfully

**Test No Update:**
1. Set current version to `1.1.0`
2. Latest release is `1.1.0`
3. Check for updates
4. Verify no notification appears

**Test Update Server Down:**
1. Stop update server
2. Check for updates
3. Verify no fatal errors
4. Check error logs for warning message

### Automated Testing

**Unit Tests:**
- Version parsing and comparison
- Response parsing
- Error handling

**Integration Tests:**
- Update check flow
- Plugin info retrieval
- Folder renaming

**E2E Tests:**
- Full update flow from check to installation
- Rollback after failed update

## Rollback Support

**Automatic Rollback:**

WordPress doesn't support automatic rollback. If update fails:
- WordPress shows error message
- Old plugin remains active
- User can manually reinstall previous version

**Manual Rollback:**

Provide previous versions on Settings page:

```php
// Settings page section
<h3>Plugin Versions</h3>
<p>Current Version: <?php echo DEPLOY_FORGE_VERSION; ?></p>

<?php
$previous_versions = $this->get_previous_versions(); // From update server
foreach ( $previous_versions as $version ) :
?>
    <div class="version-item">
        <strong><?php echo esc_html( $version['version'] ); ?></strong>
        <span><?php echo esc_html( $version['date'] ); ?></span>
        <a href="<?php echo esc_url( $version['download_url'] ); ?>"
           class="button">Download</a>
    </div>
<?php endforeach; ?>
```

## Logging

### Update Check Logs

```php
// Log all update checks
Deploy_Forge_Debug_Logger::log( 'update-checker', [
    'action' => 'check_for_updates',
    'current_version' => DEPLOY_FORGE_VERSION,
    'server_url' => DEPLOY_FORGE_UPDATE_SERVER,
    'response' => $data,
], 'info' );
```

### Update Install Logs

```php
// Log successful updates
Deploy_Forge_Debug_Logger::log( 'update-checker', [
    'action' => 'update_installed',
    'old_version' => $old_version,
    'new_version' => $new_version,
    'duration' => $duration,
], 'info' );
```

### Error Logs

```php
// Log errors
Deploy_Forge_Debug_Logger::log( 'update-checker', [
    'action' => 'update_failed',
    'error' => $error->get_error_message(),
    'version' => $version,
], 'error' );
```

## Performance Considerations

### Caching

- Cache update info for 6 hours
- Cache release list for 12 hours
- Clear cache on manual check

### Background Checks

- WordPress runs update checks in background
- Uses wp-cron (twice daily by default)
- Doesn't block user interactions

### Download Streaming

- Update server streams downloads (no disk storage)
- WordPress downloads to temp directory
- Automatic cleanup after install

## Compatibility

### WordPress Versions

- **Minimum:** WordPress 5.8
- **Tested:** WordPress 6.4
- **Uses:** Core update API (stable since WP 3.0)

### PHP Versions

- **Minimum:** PHP 7.4
- **Recommended:** PHP 8.0+
- **Uses:** Standard PHP functions (cURL via wp_remote_get)

### Hosting Requirements

- **Outbound HTTPS:** Required (to update server)
- **wp-cron:** Enabled (for automatic checks)
- **file_get_contents:** Allowed (for downloads)
- **ZipArchive:** Enabled (WordPress core requirement)

## Migration Path

### From Manual Updates

**Current State:** Users manually download and upload plugin ZIP

**After Implementation:**
1. Install plugin with update checker
2. WordPress automatically checks for updates
3. Users click "Update Now" instead of manual upload

No data migration required.

### From WordPress.org

**If plugin was on WordPress.org:**
1. Remove from WordPress.org
2. Deploy version with update checker
3. Users receive one final update from WordPress.org
4. Future updates come from custom update server

## Future Enhancements

### Beta Channel

Allow users to opt into beta releases:

```php
// Settings option
$beta_channel = get_option( 'deploy_forge_beta_updates', false );

// Request parameter
$request_body['channel'] = $beta_channel ? 'beta' : 'stable';
```

### Automatic Updates

Enable automatic background updates:

```php
// Enable automatic updates for Deploy Forge
add_filter( 'auto_update_plugin', function( $update, $item ) {
    if ( isset( $item->slug ) && $item->slug === 'deploy-forge' ) {
        return true; // Enable auto-update
    }
    return $update;
}, 10, 2 );
```

### Update Notifications

Email notification when update available:

```php
// Send email to admin on new version
if ( $new_version_available ) {
    wp_mail(
        get_option( 'admin_email' ),
        'Deploy Forge Update Available',
        "Version {$version} is now available. Update in your WordPress dashboard."
    );
}
```

## References

- [WordPress Plugin Update API](https://developer.wordpress.org/plugins/the-basics/wordpress-org/how-your-readme-txt-works/)
- [WordPress Upgrader Class](https://developer.wordpress.org/reference/classes/wp_upgrader/)
- [Plugin API Hooks](https://developer.wordpress.org/reference/hooks/plugins_api/)
- [Semantic Versioning](https://semver.org/)
- [GitHub Releases API](https://docs.github.com/en/rest/releases)

## Related Specifications

- `spec/update-server.md` - Update server implementation (Express/Vercel)
- `spec/security.md` - Security requirements
- `spec/CHANGELOG.md` - Feature tracking
