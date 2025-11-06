# Key Technology Stack & Decisions

## Core Technologies

### 1. **PHP Version**
**Recommendation: PHP 7.4+ (Target 8.0+)**
- **Why:** Balance between modern features and WordPress compatibility
- Use modern features:
  - Typed properties
  - Arrow functions
  - Null coalescing operators
  - Return type declarations
- WordPress 6.0+ officially supports PHP 7.4-8.2

### 2. **JavaScript Approach**
**Recommendation: Vanilla JavaScript (ES6+) with WordPress REST API**

**Why NOT React/Vue:**
- Adds unnecessary complexity and bundle size
- Requires build process for the plugin itself
- Overkill for this use case

**What to use:**
```javascript
// Modern vanilla JS with:
- Fetch API for AJAX requests
- Async/await for cleaner code
- ES6 modules (if needed)
- WordPress REST API nonces
```

**Alternative:** Use jQuery only if you need broad compatibility (it's already loaded in WP admin)

### 3. **CSS Framework**
**Recommendation: Plain CSS with WordPress Admin Styles**

**Why:**
- WordPress admin already has excellent styling
- Use native WP classes: `.button`, `.notice`, `.wrap`, etc.
- Add custom CSS only for unique components

**Structure:**
```css
/* Use WordPress core styles + minimal custom CSS */
- WordPress admin color schemes (automatic)
- WordPress responsive breakpoints
- WordPress form styling
- Custom styles only for dashboard cards, history table
```

**Alternative:** CSS Variables for theming if you want custom branding

## WordPress-Specific Technologies

### 4. **WordPress APIs to Leverage**

**✅ REST API** - For AJAX operations
```php
register_rest_route('github-deploy/v1', '/webhook', [...]);
register_rest_route('github-deploy/v1', '/deploy', [...]);
register_rest_route('github-deploy/v1', '/status', [...]);
```

**✅ Settings API** - For options page
```php
register_setting('github_deploy_options', 'github_deploy_settings');
add_settings_section(...);
add_settings_field(...);
```

**✅ Transients API** - For caching GitHub API responses
```php
set_transient('github_deploy_commits_' . $repo, $data, HOUR_IN_SECONDS);
```

**✅ WP_Cron** - For polling build status
```php
wp_schedule_event(time(), 'every_minute', 'github_deploy_check_builds');
```

**✅ WP_Filesystem** - For safe file operations
```php
global $wp_filesystem;
WP_Filesystem();
$wp_filesystem->copy($source, $dest);
```

### 5. **Database Strategy**
**Recommendation: Custom Tables (not post types)**

**Why:**
- Deployments aren't "content" - they're system records
- Need complex queries and relationships
- Better performance for logging/history
- Cleaner data model

**Structure:**
```sql
CREATE TABLE {prefix}_github_deployments (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    commit_hash varchar(40) NOT NULL,
    status varchar(20) NOT NULL,
    deployed_at datetime,
    -- ... etc
    PRIMARY KEY (id),
    KEY commit_hash (commit_hash),
    KEY status (status),
    KEY deployed_at (deployed_at)
);
```

## External Integrations

### 6. **GitHub API Client**
**Recommendation: Native PHP cURL with wrapper class**

**Why NOT use a library:**
- Most PHP GitHub libraries are overkill
- We only need ~5 API endpoints
- Reduces dependencies and potential conflicts
- Full control over requests

**Implementation:**
```php
class GitHub_API {
    private $token;
    private $base_url = 'https://api.github.com';
    
    private function request($method, $endpoint, $data = null) {
        // Use wp_remote_request() - WordPress's HTTP API
        // It's better than raw cURL in WordPress context
    }
}
```

**Use WordPress HTTP API (`wp_remote_request`)** instead of cURL directly:
- Built-in error handling
- Proxy support
- Better WordPress integration
- Fallback mechanisms

### 7. **Authentication & Security**

**Token Encryption:**
```php
// Use WordPress salts for encryption
define('GITHUB_DEPLOY_ENCRYPT_KEY', wp_salt('auth'));

// Or use sodium (PHP 7.2+) for modern encryption
sodium_crypto_secretbox();
```

**Webhook Validation:**
```php
// HMAC SHA256 signature validation
hash_hmac('sha256', $payload, $secret);
```

**Nonces:**
```php
wp_create_nonce('github_deploy_action');
wp_verify_nonce($_POST['nonce'], 'github_deploy_action');
```

## Development Tools

### 8. **Code Quality Tools**
**Recommendation:**
- **PHP_CodeSniffer** with WordPress Coding Standards
- **PHPStan** or **Psalm** for static analysis
- **WP-CLI** for testing deployments

```bash
# Install WordPress Coding Standards
composer require --dev wp-coding-standards/wpcs

# Check code
phpcs --standard=WordPress plugin-name/
```

### 9. **Version Control & Structure**
**Recommendation:**
```
github-auto-deploy/
├── .gitignore
├── composer.json (for dev dependencies only)
├── package.json (not needed unless bundling admin JS)
├── README.md
└── [plugin files]
```

**No build process needed** for the plugin itself - keep it simple!

## Specific Library Recommendations

### ✅ Use These (WordPress Native):
- `wp_remote_request()` - HTTP requests
- `wpdb` - Database operations
- `WP_Filesystem` - File operations  
- `wp_localize_script()` - Pass data to JS
- `sanitize_*()` functions - Input sanitization
- `esc_*()` functions - Output escaping

### ❌ Avoid These:
- ❌ Guzzle HTTP client (wp_remote_request is sufficient)
- ❌ Monolog logger (use error_log or custom)
- ❌ PHPMailer (WP has wp_mail)
- ❌ Carbon dates (WP has date functions)
- ❌ Symfony components (overkill)

## Admin Interface Components

### 10. **UI Components to Use**

**WordPress Native Elements:**
```html
<!-- Use these existing WP components -->
<div class="wrap">
<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
<div class="notice notice-success"><p>Success message</p></div>
<table class="wp-list-table widefat fixed striped">
<button class="button button-primary">Primary Action</button>
<input type="text" class="regular-text">
```

**JavaScript Libraries:**
- **NO jQuery plugins** - use vanilla JS
- **NO DataTables** - build simple table with WP_List_Table class
- **NO Chart.js** - not needed for v1.0
- **NO Moment.js** - use native Date or PHP formatting

## Performance Optimizations

### 11. **Caching Strategy**
```php
// Use WordPress Transients API
set_transient('github_deploy_status_' . $run_id, $status, 5 * MINUTE_IN_SECONDS);

// Object cache compatible (Redis/Memcached if available)
wp_cache_set($key, $value, 'github_deploy', HOUR_IN_SECONDS);
```

### 12. **Background Processing**
**Recommendation: WP_Cron (with fallback to real cron)**

```php
// Check every minute for completed builds
wp_schedule_event(time(), 'every_minute', 'github_deploy_check_builds');

// Custom interval
add_filter('cron_schedules', function($schedules) {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display'  => __('Every Minute')
    ];
    return $schedules;
});
```

## Summary: Tech Stack

| Component | Technology | Why |
|-----------|-----------|-----|
| **Backend** | PHP 7.4+ (OOP) | WordPress standard, modern features |
| **Frontend** | Vanilla JS (ES6+) | No build process, lightweight |
| **CSS** | WordPress Admin + Custom | Consistent, minimal |
| **HTTP Client** | wp_remote_request() | WordPress native, reliable |
| **Database** | wpdb + Custom Tables | Performance, proper structure |
| **Caching** | Transients API | Simple, effective |
| **Background Tasks** | WP_Cron | WordPress native |
| **File Ops** | WP_Filesystem | Safe, compatible |
| **Security** | WordPress APIs + sodium | Battle-tested |
| **Admin UI** | WordPress Components | Native look & feel |

## What NOT to Include

- ❌ No Composer autoloader (use simple requires)
- ❌ No NPM/Webpack/bundler (keep JS simple)
- ❌ No ORM/Query Builder (wpdb is fine)
- ❌ No template engine (PHP templates are fine)
- ❌ No DI container (overkill for this size)

**Philosophy: Keep it simple, use WordPress native functions, minimize dependencies.**

This approach ensures maximum compatibility, easy maintenance, and follows WordPress best practices!