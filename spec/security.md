# Security Specification

**Last Updated:** 2025-11-09

## Overview

Security is a critical aspect of this plugin. This document outlines all security measures, threat models, and best practices implemented.

## Threat Model

### Assets to Protect

1. **GitHub Access Tokens** - Can access repositories and trigger workflows
2. **Webhook Secrets** - Validate webhook authenticity
3. **WordPress Installation** - Prevent unauthorized theme modifications
4. **User Data** - Deployment logs, commit information
5. **File System** - Theme files and backups

### Potential Threats

1. **Unauthorized Access** - Non-admin users accessing plugin features
2. **CSRF Attacks** - Forged requests to trigger deployments
3. **Webhook Spoofing** - Fake GitHub webhooks
4. **Token Exposure** - Secrets leaked in logs or responses
5. **Code Injection** - Malicious code in theme files
6. **Directory Traversal** - Access to files outside theme directory
7. **DoS Attacks** - Overwhelming webhook endpoint

## Authentication & Authorization

### WordPress Capabilities

**Required Capability:** `manage_options` (Administrator only)

```php
// All admin pages
if (!current_user_can('manage_options')) {
    wp_die(__('Unauthorized access', 'deploy-forge'));
}

// All REST endpoints (except webhook)
'permission_callback' => function() {
    return current_user_can('manage_options');
}
```

### GitHub Authentication

**Method:** GitHub App Installation Tokens (via proxy)

**Benefits:**

- ✅ Short-lived tokens (1 hour expiration)
- ✅ Repository-scoped permissions
- ✅ Revocable at any time
- ✅ Audit trail in GitHub
- ✅ No personal credentials exposed

**Implementation:**

```php
// Tokens generated via backend proxy
$token_response = wp_remote_post($backend_url . '/api/github/token', [
    'headers' => [
        'X-API-Key' => $encrypted_api_key,
    ],
]);

// Token used for GitHub API requests
$headers = [
    'Authorization' => 'Bearer ' . $installation_token,
];
```

### API Key Storage

**Encryption:** Sodium (libsodium)

```php
// Encryption
$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$encrypted = sodium_crypto_secretbox($api_key, $nonce, $key);
$stored = base64_encode($nonce . $encrypted);

// Decryption
$decoded = base64_decode($stored);
$nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$api_key = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
```

**Encryption Key:** Derived from WordPress salts

```php
$key = hash('sha256', AUTH_KEY . SECURE_AUTH_KEY, true);
$key = substr($key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
```

## Input Validation & Sanitization

### User Inputs

**Settings Form:**

```php
// Repository owner/name
$owner = sanitize_text_field($_POST['github_owner']);
$repo = sanitize_text_field($_POST['github_repo']);

// Validate format
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $owner)) {
    // Reject
}

// Branch name
$branch = sanitize_text_field($_POST['github_branch']);

// Workflow name
$workflow = sanitize_file_name($_POST['workflow_name']);
```

**AJAX Requests:**

```php
// Nonce verification (REQUIRED)
check_ajax_referer('github_deploy_nonce', 'nonce');

// Capability check (REQUIRED)
if (!current_user_can('manage_options')) {
    wp_send_json_error('Unauthorized');
}

// Input sanitization
$deployment_id = absint($_POST['deployment_id']);
$commit_hash = sanitize_text_field($_POST['commit_hash']);
```

### Webhook Payloads

**Always Required:**

1. ✅ Signature verification (HMAC SHA-256)
2. ✅ Non-empty webhook secret
3. ✅ Valid JSON payload
4. ✅ Expected event type

```php
// Webhook secret MUST be configured
if (empty($webhook_secret)) {
    return new WP_REST_Response([
        'success' => false,
        'message' => 'Webhook secret must be configured',
    ], 401);
}

// Signature MUST be valid
if (!$this->verify_signature($payload, $signature)) {
    return new WP_REST_Response([
        'success' => false,
        'message' => 'Invalid webhook signature',
    ], 401);
}
```

**No Exceptions:**

- ❌ No bypassing signature verification
- ❌ No default webhook secrets
- ❌ No accepting unsigned webhooks

### File Operations

**Path Validation:**

```php
// Prevent directory traversal
$theme_path = realpath($theme_path);
$wp_themes_dir = realpath(WP_CONTENT_DIR . '/themes');

// Ensure theme path is within themes directory
if (strpos($theme_path, $wp_themes_dir) !== 0) {
    throw new Exception('Invalid theme path');
}

// Sanitize file names
$filename = sanitize_file_name($filename);
```

**Allowed Locations:**

- ✅ `wp-content/themes/` - Theme deployment
- ✅ `sys_get_temp_dir()` - Temporary extractions
- ✅ Configured backup directory
- ❌ Any other location

## Output Escaping

### Admin Templates

**Always Escape:**

```php
// Text content
<h1><?php echo esc_html($title); ?></h1>

// HTML attributes
<div class="<?php echo esc_attr($class); ?>">

// URLs
<a href="<?php echo esc_url($url); ?>">

// JavaScript data
<script>
var data = <?php echo wp_json_encode($data); ?>;
</script>

// Textarea content
<textarea><?php echo esc_textarea($content); ?></textarea>
```

**Allowed HTML:**

```php
// Only when necessary
echo wp_kses($content, [
    'a' => ['href' => [], 'title' => []],
    'strong' => [],
    'em' => [],
]);
```

### JSON Responses

```php
// WordPress handles escaping automatically
wp_send_json_success([
    'message' => 'Deployment started',
    'data' => $deployment_data,
]);
```

## CSRF Protection

### Nonces

**All Forms:**

```php
// Generate
wp_nonce_field('github_deploy_settings', 'github_deploy_nonce');

// Verify
if (!wp_verify_nonce($_POST['github_deploy_nonce'], 'github_deploy_settings')) {
    wp_die('Invalid nonce');
}
```

**All AJAX Requests:**

```php
// JavaScript
$.ajax({
    url: ajaxurl,
    data: {
        action: 'github_deploy_action',
        nonce: githubDeploy.nonce,
    },
});

// PHP
check_ajax_referer('github_deploy_nonce', 'nonce');
```

**REST API:**

```php
// WordPress REST API nonce (sent as header)
wp_localize_script('admin-scripts', 'githubDeploy', [
    'restNonce' => wp_create_nonce('wp_rest'),
]);

// Sent as X-WP-Nonce header automatically by fetch API
```

## Secret Management

### Storage

**Encrypted in Database:**

- GitHub API key
- Webhook secret (stored in plaintext but never exposed)

**Not Stored:**

- Installation tokens (generated on-demand)
- Temporary credentials

### Access Control

```php
// Settings retrieval
public function get_api_key(): ?string {
    // Only decrypt when needed
    // Never log or display
    // Never send to frontend
}

// Webhook secret
public function get_webhook_secret(): string {
    // Only used for HMAC verification
    // Never exposed in responses
}
```

### Log Sanitization

```php
// NEVER log secrets
$this->logger->log('API Request', [
    'endpoint' => $endpoint,
    'has_token' => !empty($token), // Boolean, not actual token
]);

// Sanitize before storage
$deployment_logs = $this->sanitize_logs($logs);
```

## Webhook Security

### Signature Verification

**Algorithm:** HMAC SHA-256

```php
// GitHub signature format: "sha256={hash}"
$expected = hash_hmac('sha256', $payload, $secret);
$provided = str_replace('sha256=', '', $signature);

// Timing-safe comparison
if (!hash_equals($expected, $provided)) {
    // Reject request
}
```

### Required Validations

1. ✅ Webhook secret configured (mandatory)
2. ✅ Signature header present
3. ✅ Signature valid
4. ✅ Payload not empty
5. ✅ Valid JSON
6. ✅ Expected event type

### Additional Protections

**Content-Type Handling:**

```php
// Support both formats GitHub uses
if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
    parse_str($raw_payload, $form_data);
    $payload = $form_data['payload'];
} else {
    $payload = $raw_payload;
}

// Verify signature on correct payload
$signature_payload = (is_form_encoded) ? $raw_payload : $payload;
```

## File Security

### ZIP Extraction

**Validation:**

```php
// Check ZIP is valid
$zip = new ZipArchive();
if ($zip->open($artifact_zip) !== true) {
    throw new Exception('Invalid ZIP file');
}

// Limit extraction size (prevent ZIP bombs)
$max_size = 100 * 1024 * 1024; // 100MB
if (filesize($artifact_zip) > $max_size) {
    throw new Exception('ZIP file too large');
}
```

**Safe Extraction:**

```php
// Extract to temp directory first
$temp_dir = sys_get_temp_dir() . '/deploy-forge-' . uniqid();
mkdir($temp_dir, 0755, true);

// Extract
$zip->extractTo($temp_dir);
$zip->close();

// Validate contents before deployment
$this->validate_theme_files($temp_dir);
```

### File Permissions

```php
// Directories: 0755
mkdir($dir, 0755, true);

// Files: 0644
chmod($file, 0644);

// Never: 0777 (world writable)
```

## Database Security

### SQL Injection Prevention

**Always Use Prepared Statements:**

```php
// Correct
$wpdb->prepare(
    "SELECT * FROM {$table} WHERE id = %d",
    $deployment_id
);

// NEVER
$wpdb->query("SELECT * FROM {$table} WHERE id = {$deployment_id}");
```

### Data Sanitization

```php
// Insert
$wpdb->insert($table, [
    'commit_hash' => sanitize_text_field($hash),
    'commit_message' => sanitize_textarea_field($message),
    'status' => $this->validate_status($status),
]);
```

## Rate Limiting

### Webhook Endpoint

**Current:** None implemented

**Planned:**

- IP-based rate limiting
- Transient-based tracking
- Configurable threshold
- Automatic blocking

### API Requests

**GitHub Rate Limits:**

- Monitor via headers
- Cache aggressively
- Exponential backoff

## Audit Logging

### Security Events

**Logged:**

- ✅ Failed authentication attempts
- ✅ Invalid webhook signatures
- ✅ Permission denials
- ✅ Configuration changes
- ✅ Deployment triggers

**Not Logged:**

- ❌ Actual secrets/tokens
- ❌ Personal data
- ❌ Full request payloads

### Log Storage

```php
// Debug logs table (separate from deployments)
CREATE TABLE {prefix}_github_deploy_logs (
    id BIGINT AUTO_INCREMENT,
    level VARCHAR(20),
    category VARCHAR(50),
    message TEXT,
    context LONGTEXT,
    created_at DATETIME,
    PRIMARY KEY (id),
    KEY level (level),
    KEY created_at (created_at)
);
```

## Vulnerability Mitigation

### OWASP Top 10

| Vulnerability                              | Mitigation                                  |
| ------------------------------------------ | ------------------------------------------- |
| **A01:2021 – Broken Access Control**       | Capability checks on all actions            |
| **A02:2021 – Cryptographic Failures**      | Sodium encryption for secrets               |
| **A03:2021 – Injection**                   | Prepared statements, input sanitization     |
| **A04:2021 – Insecure Design**             | Security-first architecture                 |
| **A05:2021 – Security Misconfiguration**   | Secure defaults, no debug in production     |
| **A06:2021 – Vulnerable Components**       | Minimal dependencies, WordPress core only   |
| **A07:2021 – Identification Failures**     | WordPress authentication                    |
| **A08:2021 – Software and Data Integrity** | Webhook signature verification              |
| **A09:2021 – Security Logging Failures**   | Comprehensive audit logging                 |
| **A10:2021 – Server-Side Request Forgery** | Validated URLs, no user-controlled requests |

### WordPress-Specific

- ✅ Nonce verification
- ✅ Capability checks
- ✅ Input sanitization
- ✅ Output escaping
- ✅ Prepared statements
- ✅ Safe file operations
- ✅ No eval() or create_function()

## Security Checklist

### Before Release

- [ ] All user inputs sanitized
- [ ] All outputs escaped
- [ ] All database queries use prepared statements
- [ ] All admin actions have capability checks
- [ ] All AJAX requests have nonce verification
- [ ] No secrets in logs or error messages
- [ ] File operations use safe paths
- [ ] Webhook signature always verified
- [ ] Default settings are secure
- [ ] Debug mode disabled in production

### Regular Audits

- [ ] Review dependency updates
- [ ] Check for disclosed vulnerabilities
- [ ] Audit log review
- [ ] Failed authentication analysis
- [ ] Permission configuration review

## Incident Response

### Security Issue Reporting

Contact: [Maintain private contact for security issues]

### Response Plan

1. **Assess** - Severity and impact
2. **Patch** - Develop and test fix
3. **Release** - Emergency update
4. **Notify** - Inform users if needed
5. **Document** - Post-mortem analysis

## Future Security Enhancements

- Two-factor authentication for critical actions
- IP allowlist for webhook endpoint
- Rate limiting on all endpoints
- Security headers (CSP, X-Frame-Options)
- Automated security scanning
- Regular penetration testing
