# API Integration Specification

**Last Updated:** 2025-11-09

## Overview

This document specifies all external API integrations used by the plugin, including GitHub REST API, webhook handling, and backend proxy communication.

## GitHub REST API v3

### Base URL

`https://api.github.com`

### Authentication

**Method:** GitHub App Installation Tokens (via backend proxy)

- Tokens are generated via backend proxy service
- Short-lived (1 hour expiration)
- Scoped to specific repositories
- More secure than Personal Access Tokens

### Proxy Architecture

**Backend URL:** `https://deploy-forge.vercel.app`

All GitHub API requests are proxied through our backend to:

1. Manage GitHub App authentication
2. Generate installation tokens
3. Handle token refresh
4. Provide rate limit buffering

### Request Format

```php
// Proxied request structure
POST https://deploy-forge.vercel.app/api/github/proxy

Headers:
  Content-Type: application/json
  X-API-Key: {user_api_key}

Body:
{
  "method": "GET|POST|PUT|DELETE",
  "endpoint": "/repos/{owner}/{repo}/...",
  "data": { /* optional request data */ }
}

Response:
{
  "status": 200,
  "data": { /* GitHub API response */ },
  "headers": { /* GitHub response headers */ }
}
```

## API Endpoints Used

### 1. Repository Access

#### Test Connection

```http
GET /repos/{owner}/{repo}
```

**Purpose:** Verify credentials and repository access

**Response:**

```json
{
  "id": 123456,
  "name": "my-theme",
  "full_name": "owner/my-theme",
  "private": false,
  "default_branch": "main"
}
```

### 2. Workflows

#### List Workflows

```http
GET /repos/{owner}/{repo}/actions/workflows
```

**Purpose:** Get available GitHub Actions workflows

**Response:**

```json
{
  "workflows": [
    {
      "id": 123456,
      "name": "Build Theme",
      "path": ".github/workflows/build.yml",
      "state": "active"
    }
  ]
}
```

#### Trigger Workflow

```http
POST /repos/{owner}/{repo}/actions/workflows/{workflow_id}/dispatches
```

**Body:**

```json
{
  "ref": "main"
}
```

**Response:** `204 No Content` on success

#### Get Workflow Run Status

```http
GET /repos/{owner}/{repo}/actions/runs/{run_id}
```

**Response:**

```json
{
  "id": 123456,
  "status": "completed",
  "conclusion": "success",
  "html_url": "https://github.com/...",
  "head_sha": "abc123..."
}
```

**Status Values:**

- `queued` - Waiting to start
- `in_progress` - Currently running
- `completed` - Finished (check conclusion)

**Conclusion Values:**

- `success` - Build succeeded
- `failure` - Build failed
- `cancelled` - User cancelled
- `skipped` - Workflow skipped

#### List Workflow Runs

```http
GET /repos/{owner}/{repo}/actions/workflows/{workflow_id}/runs?per_page={limit}
```

**Purpose:** Get recent workflow runs for polling

#### Cancel Workflow Run

```http
POST /repos/{owner}/{repo}/actions/runs/{run_id}/cancel
```

**Response:** `202 Accepted` on success

### 3. Artifacts

#### List Artifacts

```http
GET /repos/{owner}/{repo}/actions/runs/{run_id}/artifacts
```

**Response:**

```json
{
  "artifacts": [
    {
      "id": 789,
      "name": "theme-build",
      "size_in_bytes": 1024000
    }
  ]
}
```

#### Download Artifact

```http
GET /repos/{owner}/{repo}/actions/artifacts/{artifact_id}/zip
```

**Response:** `302 Found` with `Location` header to Azure blob storage

**Implementation:**

1. Request artifact download URL (returns 302)
2. Extract `Location` header
3. Download from pre-signed URL (no auth needed)
4. Stream to local file

### 4. Commits

#### Get Recent Commits

```http
GET /repos/{owner}/{repo}/commits?sha={branch}&per_page={limit}
```

**Response:**

```json
[
  {
    "sha": "abc123...",
    "commit": {
      "message": "Fix bug",
      "author": {
        "name": "John Doe",
        "date": "2025-11-09T12:00:00Z"
      }
    }
  }
]
```

#### Get Commit Details

```http
GET /repos/{owner}/{repo}/commits/{sha}
```

### 5. Installation Repositories

#### List Installation Repositories

```http
GET /installation/repositories
```

**Purpose:** Get repositories the GitHub App can access

**Response:**

```json
{
  "repositories": [
    {
      "id": 123,
      "full_name": "owner/repo",
      "name": "repo",
      "private": true
    }
  ]
}
```

## Rate Limiting

### GitHub Limits

- **Authenticated:** 5,000 requests/hour
- **Per Repository:** No specific limit
- **Workflow Triggers:** 1,000/hour per repository

### Plugin Strategies

1. **Caching:** Transients API (2-5 min TTL)
2. **Exponential Backoff:** On 429 responses
3. **Conditional Requests:** ETag support
4. **Monitoring:** Log remaining quota

### Rate Limit Response

```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1699564800
```

**Plugin Response:**

- Log warning at 10 remaining requests
- Cache more aggressively
- Delay non-critical requests
- Fire `github_deploy_rate_limit_warning` action

## Webhook API

### Endpoint

```
POST https://yoursite.com/wp-json/deploy-forge/v1/webhook
```

### Authentication

**Method:** HMAC SHA-256 signature

```
X-Hub-Signature-256: sha256={hash}
```

**Verification:**

```php
$expected = hash_hmac('sha256', $payload, $secret);
hash_equals($expected, $provided_hash);
```

### Event Types

#### Push Event

**Header:** `X-GitHub-Event: push`

**Payload:**

```json
{
  "ref": "refs/heads/main",
  "head_commit": {
    "id": "abc123...",
    "message": "Update theme",
    "author": {
      "name": "John Doe"
    },
    "timestamp": "2025-11-09T12:00:00Z"
  }
}
```

**Plugin Action:**

- Validate branch matches configured branch
- Extract commit details
- Create deployment record
- Trigger GitHub Actions workflow

#### Workflow Run Event

**Header:** `X-GitHub-Event: workflow_run`

**Payload:**

```json
{
  "action": "completed",
  "workflow_run": {
    "id": 123456,
    "status": "completed",
    "conclusion": "success",
    "head_sha": "abc123..."
  }
}
```

**Plugin Action:**

- Find deployment by workflow_run_id or commit_hash
- Update deployment status
- If success: download artifacts and deploy
- If failure: mark deployment as failed

#### Ping Event

**Header:** `X-GitHub-Event: ping`

**Purpose:** Test webhook configuration

**Plugin Response:** `200 OK` with success message

### Content Types

GitHub sends webhooks in two formats:

1. **application/json** - Direct JSON body
2. **application/x-www-form-urlencoded** - JSON in `payload` field

Plugin handles both formats automatically.

### Security

**Required:**

- ✅ HTTPS endpoint (webhooks require SSL)
- ✅ Signature validation (HMAC SHA-256)
- ✅ Secret configuration (mandatory, no default)
- ✅ IP allowlist (optional, not implemented yet)

**Rejected Requests:**

- Missing signature
- Invalid signature
- Empty webhook secret
- Unsupported event types

## WordPress REST API

### Internal Endpoints

#### Deploy Now

```http
POST /wp-json/deploy-forge/v1/deploy
X-WP-Nonce: {nonce}

Body:
{
  "commit_hash": "abc123..."
}
```

**Capability:** `manage_options`

#### Deployment Status

```http
GET /wp-json/deploy-forge/v1/deployment/{id}/status
X-WP-Nonce: {nonce}
```

**Response:**

```json
{
  "id": 123,
  "status": "building",
  "progress": 60,
  "message": "Building theme..."
}
```

#### Cancel Deployment

```http
POST /wp-json/deploy-forge/v1/deployment/{id}/cancel
X-WP-Nonce: {nonce}
```

**Capability:** `manage_options`

#### Test Connection

```http
POST /wp-json/deploy-forge/v1/test-connection
X-WP-Nonce: {nonce}
```

## Error Handling

### API Errors

```php
// WP_Error format
new WP_Error(
    'github_api_error',
    'Failed to trigger workflow',
    ['status' => 401, 'response' => $response]
);
```

### HTTP Status Codes

| Code | Meaning           | Plugin Action                  |
| ---- | ----------------- | ------------------------------ |
| 200  | Success           | Process response               |
| 201  | Created           | Process response               |
| 204  | No Content        | Success (no body)              |
| 302  | Redirect          | Follow Location header         |
| 401  | Unauthorized      | Token expired/invalid          |
| 403  | Forbidden         | Permission denied              |
| 404  | Not Found         | Resource doesn't exist         |
| 422  | Validation Failed | Invalid request data           |
| 429  | Rate Limited      | Back off and retry             |
| 500  | Server Error      | Retry with exponential backoff |

### Retry Logic

**Retryable Errors:**

- Network timeouts
- 500/502/503 server errors
- Rate limit (after wait period)

**Retry Strategy:**

```php
// Exponential backoff: 2s, 4s, 8s, 16s
$max_retries = 4;
$wait_time = pow(2, $attempt); // seconds
```

**Non-Retryable:**

- 401 Unauthorized
- 403 Forbidden
- 404 Not Found
- 422 Validation Failed

## Logging

All API requests and responses are logged via `GitHub_Deploy_Debug_Logger`:

```php
$logger->log_api_request($method, $endpoint, $data);
$logger->log_api_response($endpoint, $status, $response, $error);
```

**Log Levels:**

- `INFO` - Successful requests
- `WARNING` - Rate limit warnings
- `ERROR` - Failed requests, invalid responses

## Testing

### Manual Testing

```bash
# Test webhook locally
curl -X POST https://yoursite.local/wp-json/deploy-forge/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: ping" \
  -H "X-Hub-Signature-256: sha256=..." \
  -d '{"zen": "Test"}'
```

### Mock Responses

For testing without GitHub API access:

- Use transients to cache responses
- Implement mock backend proxy
- Use test webhook payloads

## Future Enhancements

- GraphQL API support (better performance)
- Webhook retry mechanism
- IP allowlist for webhooks
- Custom webhook events
- API response caching improvements
