# Security Audit - All Issues Summary

**Audit Date:** 2025-11-05
**Total Issues:** 20 (3 Critical, 5 High, 8 Medium, 4 Low)

---

## 🔴 CRITICAL ISSUES (3)

### CRITICAL-001: Debug Endpoint Exposes Sensitive Information Without Authentication
- **File:** `github-auto-deploy/includes/class-webhook-handler.php:34-72`
- **Endpoint:** `/wp-json/github-deploy/v1/webhook-test`
- **Issue:** Publicly accessible endpoint exposes all request headers, payloads, and diagnostic info
- **Impact:** Information disclosure, server fingerprinting, credential leakage
- **Fix:** Remove endpoint or require admin authentication
- **Estimated Time:** 15 minutes

### CRITICAL-002: Installation Token Exposed to WordPress Sites
- **File:** `/api/github/get-token.js:48-58`
- **Endpoint:** `POST /api/github/get-token`
- **Issue:** Returns GitHub installation access token that grants access to ALL repositories in installation
- **Impact:** One compromised site = all repos accessible, cross-tenant data breach
- **Fix:** Delete endpoint, force all API calls through proxy with repository validation
- **Estimated Time:** 2-3 hours (includes HIGH-001)

### CRITICAL-003: Webhook Secret Can Be Optionally Disabled
- **File:** `github-auto-deploy/includes/class-webhook-handler.php:141-159`
- **Issue:** Webhook signature validation is optional if no secret configured
- **Impact:** Remote code execution via malicious webhook deployment
- **Fix:** Make webhook secret mandatory, always validate signatures
- **Estimated Time:** 1 hour

---

## 🟠 HIGH ISSUES (5)

### HIGH-001: No Repository Access Isolation in Backend Proxy
- **File:** `/api/github/proxy.js:69-74`
- **Issue:** Proxy doesn't validate that endpoint matches site's bound repository
- **Impact:** Cross-tenant access, Site A can access Site B's repository
- **Fix:** Add repository validation, block requests to unbound repos
- **Estimated Time:** 2 hours

### HIGH-002: API Keys Stored in Plaintext in Redis
- **File:** `/lib/kv-store.js:28-30`
- **Issue:** API keys stored unhashed in Redis database
- **Impact:** Redis compromise = all API keys leaked
- **Fix:** Hash API keys with bcrypt, implement secure lookup
- **Estimated Time:** 3-4 hours

### HIGH-003: No Rate Limiting on API Proxy Endpoint
- **File:** `/api/github/proxy.js`
- **Issue:** Proxy endpoint has no rate limiting (webhook has 30/10s)
- **Impact:** DoS, GitHub rate limit exhaustion affecting all tenants
- **Fix:** Add per-site rate limiting (60 req/min)
- **Estimated Time:** 1 hour

### HIGH-004: Insecure Direct Object Reference (IDOR) in Deployment Operations
- **File:** `github-auto-deploy/admin/class-admin-pages.php:33-37`
- **Issue:** Rollback/cancel operations don't validate deployment ownership
- **Impact:** User can manipulate other site's deployments (multisite)
- **Fix:** Add site ownership validation
- **Estimated Time:** 2 hours

### HIGH-005: OAuth State Parameter Not Validated for Reuse
- **File:** `/api/auth/callback.js:39-44`
- **Issue:** State parameter can be replayed within 10-minute window if intercepted
- **Impact:** OAuth hijacking, unauthorized GitHub association
- **Fix:** Add IP binding, shorter TTL, browser fingerprint
- **Estimated Time:** 2 hours

---

## 🟡 MEDIUM ISSUES (8)

### MEDIUM-001: Insufficient Input Validation on Webhook Payload Fields
- **File:** `class-webhook-handler.php:217-289`
- **Issue:** Commit messages and author names not sanitized
- **Impact:** Potential XSS in admin UI
- **Fix:** `sanitize_text_field()` on all payload inputs
- **Time:** 1 hour

### MEDIUM-002: Webhook Secret Generated Client-Side
- **File:** `class-settings.php:278-282`
- **Issue:** Uses `wp_generate_password()` instead of CSPRNG
- **Impact:** Weak secrets, potential prediction
- **Fix:** Use `random_bytes(32)`
- **Time:** 15 minutes

### MEDIUM-003: Connection Token Has 60-Second Expiry But No Anti-Replay
- **File:** `/api/auth/callback.js:98-104`
- **Issue:** Token can be reused multiple times within 60 seconds
- **Impact:** MITM attack, credential theft
- **Fix:** One-time use token (delete on first read)
- **Time:** 1 hour

### MEDIUM-004: AJAX Endpoints Use Same Nonce for All Operations
- **File:** `class-admin-pages.php:129`
- **Issue:** Single nonce for all AJAX operations
- **Impact:** Nonce compromise = all operations exploitable
- **Fix:** Action-specific nonces for destructive ops
- **Time:** 1 hour

### MEDIUM-005: Backup Files Stored in Web-Accessible Directory
- **File:** `class-settings.php:264-273`
- **Issue:** Backups in `uploads/github-deploy-backups` (publicly accessible)
- **Impact:** Source code disclosure via backup download
- **Fix:** Move to `WP_CONTENT_DIR` + `.htaccess` deny
- **Time:** 1 hour

### MEDIUM-006: No Audit Logging for Security Events
- **Issue:** No logging of auth failures, access denials, config changes
- **Impact:** No forensics capability, attacks go unnoticed
- **Fix:** Create audit log table, log security events
- **Time:** 4 hours

### MEDIUM-007: Temporary Files Not Securely Deleted
- **File:** `class-deployment-manager.php:636-637`
- **Issue:** `unlink()` doesn't guarantee secure deletion
- **Impact:** Deleted artifacts recoverable from disk
- **Fix:** Overwrite with random data before delete
- **Time:** 1 hour

### MEDIUM-008: Return URL Validation Insufficient
- **File:** `/api/auth/connect.js:38-42`
- **Issue:** Only validates hostname, not protocol (HTTP vs HTTPS)
- **Impact:** Downgrade attack, credentials over unencrypted channel
- **Fix:** Enforce HTTPS protocol validation
- **Time:** 30 minutes

---

## 🟢 LOW ISSUES (4)

### LOW-001: Error Messages Expose Internal Details
- **Issue:** Stack traces and internal paths in error messages
- **Impact:** Information disclosure for attackers
- **Fix:** Sanitize all error messages
- **Time:** 2 hours

### LOW-002: GitHub App Private Key Stored in Environment Variable
- **File:** `/lib/github-app.js:18-33`
- **Issue:** Private key in plaintext env var (acceptable but risky)
- **Impact:** Key leakage via SSRF, logs, error pages
- **Fix:** Use Vercel Encrypted Secrets
- **Time:** 1 hour

### LOW-003: No Content Security Policy (CSP) Headers
- **Issue:** Admin pages don't set CSP headers
- **Impact:** XSS attacks easier to execute
- **Fix:** Add CSP headers to admin pages
- **Time:** 1 hour

### LOW-004: Debug Logs May Contain Sensitive Data
- **Issue:** Full API responses, headers, tokens logged
- **Impact:** Credential disclosure if logs compromised
- **Fix:** Redact sensitive fields, implement log rotation
- **Time:** 2 hours

---

## Cross-Tenant Security Risks

### CROSS-TENANT-001: Installation-Level Token Provides Access to All Repos
- **Root Cause:** GitHub App installations are org-level, not repo-level
- **Impact:** One compromised site can access all repos in installation
- **Mitigation:** Delete get-token endpoint + enforce repo validation in proxy
- **Related:** CRITICAL-002, HIGH-001

### CROSS-TENANT-002: Webhook Forwarding Relies on Secret Stored in Backend
- **Root Cause:** All webhook secrets stored in single Redis instance
- **Impact:** Redis compromise = malicious webhooks to all sites
- **Mitigation:** Encrypt webhook secrets at rest, implement rotation
- **Priority:** Medium-term

### CROSS-TENANT-003: Shared Rate Limits
- **Root Cause:** GitHub API rate limits shared across all tenants
- **Impact:** One abusive site exhausts quota for everyone
- **Mitigation:** Per-site rate limiting in proxy
- **Related:** HIGH-003

---

## Immediate Action Items (24 Hours)

1. **CRITICAL-001:** Delete or secure `/webhook-test` endpoint (15 min)
2. **CRITICAL-003:** Make webhook secret validation mandatory (1 hour)
3. **CRITICAL-002 + HIGH-001:** Delete get-token endpoint + add repo validation to proxy (2-3 hours)

**Total Time:** ~4-5 hours
**Impact:** Eliminates RCE risk, prevents cross-tenant data breach

---

## File Index

- `CRITICAL-001-debug-endpoint-exposed.md` - Full details on debug endpoint
- `CRITICAL-002-installation-token-exposed.md` - Full details on token exposure
- `CRITICAL-003-webhook-secret-optional.md` - Full details on webhook validation
- `HIGH-001-no-repository-isolation.md` - Full details on cross-tenant access
- `security-tasks.md` - Implementation roadmap and task tracking
- `ALL-ISSUES-SUMMARY.md` - This file (quick reference)

Additional issue files to be created:
- HIGH-002 through HIGH-005 (brief summaries)
- MEDIUM-001 through MEDIUM-008 (brief summaries)
- LOW-001 through LOW-004 (brief summaries)

---

## Testing Strategy

### Phase 1 (Critical Fixes)
```bash
# Test 1: Debug endpoint removed
curl https://site.com/wp-json/github-deploy/v1/webhook-test
# Expected: 404

# Test 2: Webhook requires secret
curl -X POST https://site.com/wp-json/github-deploy/v1/webhook \
  -d '{"test": "data"}'
# Expected: 401 "Webhook secret must be configured"

# Test 3: Get-token endpoint removed
curl -X POST https://backend.com/api/github/get-token \
  -H "X-API-Key: valid-key"
# Expected: 404

# Test 4: Cross-repo access blocked
curl -X POST https://backend.com/api/github/proxy \
  -H "X-API-Key: site-a-key" \
  -d '{"method":"GET","endpoint":"/repos/owner/repo-b/contents"}'
# Expected: 403 "Access denied"

# Test 5: Same-repo access allowed
curl -X POST https://backend.com/api/github/proxy \
  -H "X-API-Key: site-a-key" \
  -d '{"method":"GET","endpoint":"/repos/owner/repo-a/contents"}'
# Expected: 200 OK
```

---

## Deployment Checklist

Before deploying security fixes:

- [ ] All critical tests pass
- [ ] No functionality regressions
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Version bumped (1.0.0 → 1.0.1)
- [ ] Staging environment tested
- [ ] Backend deployed first (Vercel)
- [ ] Plugin update deployed second
- [ ] Monitoring enabled for errors
- [ ] Rollback plan ready

---

## Success Metrics

After Phase 1 deployment:

- ✅ Zero public debug endpoints
- ✅ 100% webhook signature validation
- ✅ Zero direct installation token exposure
- ✅ Zero cross-tenant repository access
- ✅ All existing deployments still work
- ✅ No increase in error rates

---

**Next Steps:** Review `security-tasks.md` for detailed implementation plan.
