# Disconnect Flows Implementation

## Overview
Two-tier system for handling both graceful and ungraceful site disconnections.

## Phase 1: Complete ✅

### 1. Graceful Disconnect (User-Initiated)

**Flow:**
```
User clicks "Disconnect" button
  ↓
WordPress → Backend /api/auth/disconnect (with API key)
  ↓
Backend deletes site from KV store
  ↓
WordPress deletes local settings
  ↓
Done - Site cleanly disconnected
```

**Implementation:**
- **Backend:** `api/auth/disconnect.js` - Validates API key and calls `deleteSite()`
- **WordPress:** `class-github-app-connector.php::notify_backend_disconnect()` - Calls backend before local cleanup
- **Result:** Immediate cleanup, no orphaned webhooks

### 2. Ungraceful Disconnect (Auto-Detection)

**Scenarios Handled:**
- Database manually deleted
- Site migration/URL change
- Plugin uninstalled without disconnect
- WordPress site deleted

**Flow:**
```
GitHub webhook sent to WordPress
  ↓
Webhook fails (401/404/network error)
  ↓
Backend tracks failure counter
  ↓
After 20 consecutive failures → Auto-unbind site
```

**Implementation:**
- **KV Store:** Added `trackWebhookFailure()` and `trackWebhookSuccess()`
- **Webhook Handler:** Tracks 401, 404, and network errors
- **Auto-Cleanup:** After 20 failures, calls `deleteSite()`

**Tracking Data:**
```javascript
{
  consecutive_failures: 0,
  last_failure: "2025-11-06T10:00:00Z",
  last_success: "2025-11-06T09:00:00Z",
  last_failure_status: 401,
  first_failure: "2025-11-06T08:00:00Z"
}
```

### 3. Status Codes Tracked

**Counted as permanent failures:**
- `401 Unauthorized` - Webhook secret missing (ungraceful disconnect)
- `404 Not Found` - WordPress site gone
- `0` (network error) - ECONNREFUSED, timeout, DNS failure

**NOT counted:**
- `5xx` errors - Temporary server issues
- `200-299` - Success (resets failure counter)

### 4. Auto-Cleanup Threshold

**Current Settings:**
- **20 consecutive failures** → Site auto-unbound
- Failure counter **resets to 0** on any successful webhook delivery

## Benefits

✅ **Graceful disconnect** - Clean, immediate cleanup
✅ **Self-healing** - Dead sites automatically cleaned up
✅ **No log pollution** - Stops webhook spam after threshold
✅ **Resource efficient** - No indefinite webhook attempts
✅ **Handles all scenarios** - Migration, deletion, corruption, etc.

## Testing Checklist

### Graceful Disconnect
- [ ] Click "Disconnect" in WordPress
- [ ] Verify backend receives `/api/auth/disconnect` call
- [ ] Verify site removed from KV store
- [ ] Verify no more webhooks sent

### Ungraceful Disconnect
- [ ] Delete WordPress database/plugin
- [ ] Trigger 20 webhook deliveries from GitHub
- [ ] Verify site auto-unbound after 20 failures
- [ ] Verify no more webhooks sent

## Future Enhancements (Phase 2)

- Health check endpoint (`/wp-json/github-deploy/v1/health`)
- Admin dashboard for "zombie sites"
- Email notification before auto-unbind
- Time-based cleanup (7 days of continuous failures)
- Configurable thresholds

## Files Modified

### Backend
- `api/auth/disconnect.js` (new)
- `lib/kv-store.js` - Added tracking functions
- `api/webhooks/github.js` - Added failure tracking

### WordPress
- `includes/class-github-app-connector.php` - Added backend notification

## API Endpoints

### POST /api/auth/disconnect
**Headers:**
- `X-API-Key: <wordpress_api_key>`

**Response:**
```json
{
  "success": true,
  "message": "Site disconnected successfully",
  "site_id": "abc123"
}
```
