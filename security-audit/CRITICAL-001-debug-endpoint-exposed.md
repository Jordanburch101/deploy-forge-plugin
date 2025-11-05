# CRITICAL-001: Debug Endpoint Exposes Sensitive Information Without Authentication

**Status:** 🔴 CRITICAL - OPEN
**Priority:** P0 - Fix Immediately
**Impact:** Information disclosure, server fingerprinting, potential leak of sensitive headers

## Location

- **File:** `github-auto-deploy/includes/class-webhook-handler.php`
- **Lines:** 34-72
- **Endpoint:** `/wp-json/github-deploy/v1/webhook-test`

## Vulnerability Description

The `/webhook-test` endpoint is publicly accessible with `permission_callback => '__return_true'` and exposes:
- Full request headers (may contain secrets, tokens, API keys)
- Complete payload data
- PHP input stream contents
- Request method and content-type details
- Diagnostic information about request parsing

## Vulnerable Code

```php
register_rest_route('github-deploy/v1', '/webhook-test', [
    'methods' => ['GET', 'POST'],
    'callback' => [$this, 'test_webhook_reception'],
    'permission_callback' => '__return_true', // ⚠️ NO AUTHENTICATION
]);

public function test_webhook_reception(WP_REST_Request $request): WP_REST_Response {
    $methods = [
        'get_body' => $request->get_body(),
        'get_json_params' => $request->get_json_params(),
        'get_body_params' => $request->get_body_params(),
        'get_params' => $request->get_params(),
    ];

    $diagnostics = [
        'request_method' => $request->get_method(),
        'content_type' => $request->get_header('content-type'),
        'headers' => $request->get_headers(), // ⚠️ ALL HEADERS EXPOSED
        'php_input' => file_get_contents('php://input'),
    ];

    // Returns everything to anyone who calls it
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Debug info',
        'diagnostics' => $diagnostics,
    ], 200);
}
```

## Attack Scenario

1. Attacker discovers public endpoint: `https://victim-site.com/wp-json/github-deploy/v1/webhook-test`
2. Sends crafted requests to probe for information leakage
3. Obtains header information revealing:
   - Server configuration (PHP version, web server type)
   - Installed plugins/themes
   - Authentication tokens if present in headers
   - Internal IP addresses (X-Forwarded-For, X-Real-IP)
4. Uses information for targeted attacks

## Proof of Concept

```bash
# Anyone can call this endpoint
curl -X POST https://example.com/wp-json/github-deploy/v1/webhook-test \
  -H "Authorization: Bearer secret-token-123" \
  -H "X-Custom-Internal-Header: sensitive-data" \
  -d '{"test": "data"}'

# Response includes ALL headers sent
{
  "success": true,
  "diagnostics": {
    "headers": {
      "authorization": ["Bearer secret-token-123"],
      "x-custom-internal-header": ["sensitive-data"],
      ...
    }
  }
}
```

## Remediation Options

### Option 1: Remove Endpoint Entirely (RECOMMENDED)

```php
// Delete the entire endpoint registration and method
// Lines 33-38 in class-webhook-handler.php
```

**Rationale:** Debug endpoints should never exist in production code. Use local development environments for debugging.

### Option 2: Restrict to Administrators Only

```php
register_rest_route('github-deploy/v1', '/webhook-test', [
    'methods' => ['GET', 'POST'],
    'callback' => [$this, 'test_webhook_reception'],
    'permission_callback' => function() {
        // Only allow for administrators
        return current_user_can('manage_options');
    }
]);
```

### Option 3: Add IP Whitelist + Admin Check

```php
register_rest_route('github-deploy/v1', '/webhook-test', [
    'methods' => ['GET', 'POST'],
    'callback' => [$this, 'test_webhook_reception'],
    'permission_callback' => function() {
        // Require admin AND specific IP
        $allowed_ips = ['127.0.0.1', '::1']; // Only localhost
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return current_user_can('manage_options')
            && in_array($client_ip, $allowed_ips);
    }
]);
```

### Option 4: Conditional Compilation (Development Only)

```php
// Only register in development environments
if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local') {
    register_rest_route('github-deploy/v1', '/webhook-test', [
        'methods' => ['GET', 'POST'],
        'callback' => [$this, 'test_webhook_reception'],
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
}
```

## Testing After Fix

```bash
# Should return 401 or 404
curl -X POST https://example.com/wp-json/github-deploy/v1/webhook-test

# Expected response:
# {"code":"rest_no_route","message":"No route was found matching the URL and request method","data":{"status":404}}
```

## References

- [WordPress REST API Security](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [OWASP Information Disclosure](https://owasp.org/www-community/vulnerabilities/Information_exposure_through_an_error_message)

## Timeline

- **Discovered:** 2025-11-05
- **Target Fix Date:** 2025-11-06 (24 hours)
- **Assigned To:** TBD
- **Status:** OPEN
