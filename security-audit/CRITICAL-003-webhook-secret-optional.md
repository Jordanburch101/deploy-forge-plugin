# CRITICAL-003: Webhook Secret Can Be Optionally Disabled

**Status:** 🔴 CRITICAL - OPEN
**Priority:** P0 - Fix Immediately
**Impact:** Remote code execution, complete site compromise, deployment of malicious code

## Location

- **File:** `github-auto-deploy/includes/class-webhook-handler.php`
- **Lines:** 141-159
- **Endpoint:** `/wp-json/github-deploy/v1/webhook`

## Vulnerability Description

Webhook signature validation is **optional** if no secret is configured. This allows anyone who discovers the webhook endpoint to send malicious webhook events that will be processed without authentication.

The code accepts webhooks even when no secret is configured, only logging a warning instead of rejecting the request.

## Vulnerable Code

```php
// Get webhook secret
$webhook_secret = $this->settings->get('webhook_secret');

// Only validate signature if a secret is configured
if (!empty($webhook_secret) && !empty($payload) && !$this->verify_signature($signature_payload, $signature)) {
    // Signature validation FAILED
    $this->logger->error('Webhook', 'Invalid webhook signature');
    return new WP_REST_Response([
        'success' => false,
        'message' => __('Invalid webhook signature.', 'github-auto-deploy'),
    ], 401);
}

// If no secret configured, log warning (insecure but allows GitHub App webhooks)
if (empty($webhook_secret)) {
    $this->logger->log('Webhook', 'Webhook accepted without signature validation (no secret configured)');
}

// ⚠️ WEBHOOK CONTINUES TO BE PROCESSED WITHOUT VALIDATION
```

## Attack Scenario

### Scenario 1: No Secret Configured (Initial Setup)

1. User installs plugin but hasn't configured webhook secret yet
2. Attacker discovers webhook endpoint: `/wp-json/github-deploy/v1/webhook`
3. Sends malicious `workflow_run` webhook:
   ```json
   {
     "action": "completed",
     "workflow_run": {
       "id": 12345,
       "status": "completed",
       "conclusion": "success",
       "head_sha": "abc123"
     }
   }
   ```
4. WordPress accepts webhook without validation
5. Triggers deployment process
6. Downloads malicious artifact
7. Deploys attacker's theme files containing backdoor

### Scenario 2: User Deletes Secret

1. Plugin configured and working
2. User accidentally deletes webhook secret from settings
3. Saves settings with empty secret
4. Plugin now accepts unauthenticated webhooks
5. Attacker exploits this window of vulnerability

### Scenario 3: First-Time Connection Race Condition

1. User connects GitHub App
2. Brief window where secret may not be set
3. Attacker sends malicious webhook during this window
4. Webhook accepted and processed

## Proof of Concept

```bash
# Discover WordPress site using plugin
curl https://victim-site.com/wp-json/github-deploy/v1/webhook

# Send malicious workflow_run event (no signature)
curl -X POST https://victim-site.com/wp-json/github-deploy/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: workflow_run" \
  -H "X-GitHub-Delivery: fake-delivery-id" \
  -d '{
    "action": "completed",
    "workflow_run": {
      "id": 999999,
      "status": "completed",
      "conclusion": "success",
      "head_sha": "attacker-controlled-commit",
      "html_url": "https://github.com/attacker/repo/actions/runs/999999"
    },
    "installation": {
      "id": 12345
    }
  }'

# If no secret configured, this triggers deployment!
```

## Why This Is Critical

1. **Remote Code Execution:** Attacker can deploy malicious theme files containing:
   - PHP backdoors
   - Database dumpers
   - Credential harvesters
   - Cryptocurrency miners
   - Malware distribution

2. **Complete Site Compromise:** Theme files execute with full WordPress privileges

3. **Persistence:** Malicious theme remains until manually removed

4. **Supply Chain Attack:** If deploying to production, affects all site visitors

## Remediation

### Solution: Make Webhook Secret Mandatory

```php
/**
 * Handle incoming webhook
 */
public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
    $signature = $request->get_header('x-hub-signature-256');
    $event = $request->get_header('x-github-event');

    // Get webhook secret
    $webhook_secret = $this->settings->get('webhook_secret');

    // ALWAYS require webhook secret - no exceptions
    if (empty($webhook_secret)) {
        $this->logger->error('Webhook', 'Webhook secret not configured - rejecting request');
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Webhook secret must be configured. Please configure webhook secret in plugin settings.', 'github-auto-deploy'),
        ], 401);
    }

    // Get payload
    $raw_payload = file_get_contents('php://input');
    if (empty($raw_payload)) {
        $raw_payload = $request->get_body();
    }

    // Parse payload based on content type
    $content_type = $request->get_header('content-type');
    $payload = '';

    if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
        parse_str($raw_payload, $form_data);
        $payload = $form_data['payload'] ?? $raw_payload;
    } else {
        $payload = $raw_payload;
    }

    // Fallback to WordPress parsed methods if needed
    if (empty($payload)) {
        $data = $request->get_json_params();
        if (!empty($data)) {
            $payload = wp_json_encode($data);
        }
    }

    // Require non-empty payload
    if (empty($payload)) {
        $this->logger->error('Webhook', 'Empty payload received');
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Empty payload received.', 'github-auto-deploy'),
        ], 400);
    }

    // Determine which payload to sign for form-encoded webhooks
    $signature_payload = (strpos($content_type, 'application/x-www-form-urlencoded') !== false)
        ? $raw_payload
        : $payload;

    // ALWAYS validate signature - no exceptions
    if (!$this->verify_signature($signature_payload, $signature)) {
        $this->logger->error('Webhook', 'Invalid webhook signature', [
            'content_type' => $content_type,
            'has_signature' => !empty($signature),
            'payload_length' => strlen($payload),
        ]);
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Invalid webhook signature.', 'github-auto-deploy'),
        ], 401);
    }

    // Signature validated, continue processing...
    $this->logger->log('Webhook', 'Webhook signature validated successfully');

    // Rest of webhook handling...
}
```

### Additional: Auto-Generate Secret on Plugin Activation

```php
// In main plugin file activation hook
register_activation_hook(__FILE__, function() {
    $settings = new GitHub_Deploy_Settings();

    // Generate webhook secret if not exists
    if (empty($settings->get('webhook_secret'))) {
        $settings->generate_webhook_secret();
    }
});
```

### Additional: Validate Secret Exists Before Enabling Auto-Deploy

```php
// In settings validation
public function validate(): array {
    $errors = [];

    // ... existing validations ...

    // Require webhook secret if auto-deploy enabled
    if ($this->get('auto_deploy_enabled') && empty($this->get('webhook_secret'))) {
        $errors[] = __('Webhook secret is required when auto-deploy is enabled. Click "Generate Secret" to create one.', 'github-auto-deploy');
    }

    return $errors;
}
```

## Testing After Fix

### Test 1: Webhook Rejected Without Secret

```bash
# Remove webhook secret from settings
# Send webhook without signature
curl -X POST https://example.com/wp-json/github-deploy/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: workflow_run" \
  -d '{"action": "completed"}'

# Expected: 401 Unauthorized
{
  "success": false,
  "message": "Webhook secret must be configured. Please configure webhook secret in plugin settings."
}
```

### Test 2: Webhook Rejected With Invalid Signature

```bash
# Send webhook with wrong signature
curl -X POST https://example.com/wp-json/github-deploy/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: workflow_run" \
  -H "X-Hub-Signature-256: sha256=wrong_signature" \
  -d '{"action": "completed"}'

# Expected: 401 Unauthorized
{
  "success": false,
  "message": "Invalid webhook signature."
}
```

### Test 3: Webhook Accepted With Valid Signature

```bash
# Generate valid signature
SECRET="your-webhook-secret"
PAYLOAD='{"action":"completed","workflow_run":{"id":123}}'
SIGNATURE="sha256=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')"

# Send webhook with valid signature
curl -X POST https://example.com/wp-json/github-deploy/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: ping" \
  -H "X-Hub-Signature-256: $SIGNATURE" \
  -d "$PAYLOAD"

# Expected: 200 OK
```

## Backend Considerations

The backend (`/api/webhooks/github.js`) also validates webhooks but has the same issue:

```javascript
// Verify webhook signature (if webhook secret is configured)
const webhookSecret = process.env.GITHUB_WEBHOOK_SECRET;
if (webhookSecret) {
  // Only validates if secret exists
  if (!verifyWebhookSignature(payload, signature, webhookSecret)) {
    return res.status(401).json({ error: 'Invalid signature' });
  }
}
```

**Backend Fix:**
```javascript
// ALWAYS require webhook secret
const webhookSecret = process.env.GITHUB_WEBHOOK_SECRET;
if (!webhookSecret) {
  console.error('GITHUB_WEBHOOK_SECRET not configured');
  return res.status(500).json({
    error: 'Server configuration error',
    message: 'Webhook secret not configured on server'
  });
}

if (!verifyWebhookSignature(payload, signature, webhookSecret)) {
  console.error('Invalid webhook signature');
  return res.status(401).json({ error: 'Invalid signature' });
}
```

## References

- [GitHub Webhook Security](https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [CWE-306: Missing Authentication](https://cwe.mitre.org/data/definitions/306.html)

## Implementation Checklist

- [ ] Update `handle_webhook()` to require webhook secret
- [ ] Remove "optional validation" code
- [ ] Add secret existence check before validation
- [ ] Auto-generate secret on plugin activation
- [ ] Validate secret exists when enabling auto-deploy
- [ ] Update backend webhook handler similarly
- [ ] Test webhook rejection without secret
- [ ] Test webhook rejection with invalid signature
- [ ] Test webhook acceptance with valid signature
- [ ] Update documentation

## Timeline

- **Discovered:** 2025-11-05
- **Target Fix Date:** 2025-11-06 (24 hours)
- **Assigned To:** TBD
- **Status:** OPEN
