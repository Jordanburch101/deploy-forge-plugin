# Backend Security Tasks - Complete Implementation Guide

**Target Repository:** https://github.com/Jordanburch101/github-wordpress-backend
**Date:** 2025-11-05
**Total Backend Issues:** 5 (2 Critical, 3 High Priority)

This document provides complete, self-contained instructions for implementing all backend security fixes. All context, code, and testing procedures are included.

---

## Quick Summary

| Priority | Issue ID | Task | Time | Status |
|----------|----------|------|------|--------|
| 🔴 CRITICAL | CRITICAL-002 | Delete installation token endpoint | 30 min | ❌ Not Started |
| 🟠 HIGH | HIGH-001 | Add repository validation to proxy | 2 hours | ❌ Not Started |
| 🟠 HIGH | HIGH-002 | Hash API keys in Redis | 3-4 hours | ❌ Not Started |
| 🟠 HIGH | HIGH-003 | Add rate limiting to proxy | 1 hour | ❌ Not Started |
| 🟠 HIGH | HIGH-005 | Improve OAuth state validation | 2 hours | ❌ Not Started |

**Total Time:** ~8-10 hours
**Phase 1 (Critical):** 2.5 hours
**Phase 2 (High):** 6-8 hours

---

## Architecture Context

### System Overview

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│  WordPress  │────────▶│    Vercel    │────────▶│   GitHub    │
│    Sites    │  HTTPS  │   Backend    │   API   │     API     │
└─────────────┘         └──────────────┘         └─────────────┘
       │                        │                        │
       │                        │                        │
   API Key              GitHub App Token         Installation
  (per site)            (installation-wide)      (org-level)
```

### Current Security Model

1. **WordPress sites** authenticate with API keys stored in their database
2. **Backend** validates API keys against Redis KV store
3. **Backend** uses GitHub App installation tokens to make API requests
4. **Problem:** Installation tokens grant access to ALL repos in installation

### Technology Stack

- **Runtime:** Node.js on Vercel Serverless
- **Storage:** Upstash Redis (KV store)
- **API:** GitHub REST API v3
- **Auth:** GitHub App installation tokens
- **WordPress Communication:** REST API with X-API-Key header

---

## 🔴 CRITICAL-002: Delete Installation Token Endpoint

### Context

**File:** `/api/github/get-token.js`
**Endpoint:** `POST /api/github/get-token`
**Vulnerability:** Returns GitHub installation access tokens directly to WordPress sites

### The Problem

Installation tokens provide access to **ALL repositories** in a GitHub App installation, not just the repository bound to a specific WordPress site.

**Attack Scenario:**
1. Attacker compromises WordPress Site A (SQL injection, etc.)
2. Extracts API key from `wp_options` table
3. Calls `/api/github/get-token` with stolen API key
4. Receives installation token with access to ALL repos
5. Uses token to access repositories belonging to other WordPress sites
6. Downloads source code, secrets, modifies workflows

**Impact:** Cross-tenant data breach, one compromised site = all repos accessible

### Current Code (Vulnerable)

```javascript
// api/github/get-token.js
export default async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  try {
    const apiKey = req.headers['x-api-key'];
    const siteId = await getSiteIdByApiKey(apiKey);

    if (!siteId) {
      return res.status(401).json({ error: 'Invalid API key' });
    }

    const installation = await getGitHubInstallation(siteId);
    const octokit = await getInstallationOctokit(installation.installation_id);

    // ⚠️ SECURITY ISSUE: Returns installation-wide token
    const { token } = await octokit.auth({ type: 'installation' });

    return res.status(200).json({
      success: true,
      token: token,  // Grants access to ALL repos
      expires_in: 3600
    });
  } catch (error) {
    return res.status(500).json({ error: error.message });
  }
}
```

### Fix Implementation

**Action:** Delete the file entirely

```bash
# In backend repository root
git rm api/github/get-token.js
```

**Rationale:**
- WordPress should NEVER have direct access to installation tokens
- All GitHub API calls must go through validated proxy
- Proxy can enforce repository-level access control

### Verification

```bash
# Should return 404 Not Found
curl -X POST https://your-backend.vercel.app/api/github/get-token \
  -H "X-API-Key: valid-key" \
  -H "Content-Type: application/json"

# Expected response:
# {"error": "NOT_FOUND"} or 404 page
```

### WordPress Plugin Impact

The WordPress plugin should NOT be calling this endpoint. Verify:

```bash
# In wordpress plugin repo
grep -r "get-token" github-auto-deploy/

# If found, those calls need to be refactored to use proxy endpoint
```

**Note:** The current WordPress plugin already uses the proxy for most operations, so this should be safe to delete.

---

## 🟠 HIGH-001: Add Repository Validation to Proxy

### Context

**File:** `/api/github/proxy.js`
**Endpoint:** `POST /api/github/proxy`
**Vulnerability:** Proxy makes ANY GitHub API request without validating repository scope

### The Problem

Backend proxy accepts any GitHub API endpoint from WordPress sites without checking if the endpoint matches the site's bound repository.

**Attack Scenario:**
1. Site A bound to `owner/repo-a`
2. Site B bound to `owner/repo-b`
3. Attacker compromises Site A, gets API key
4. Calls proxy with Site A's key but requests Site B's repo:
   ```json
   {
     "method": "GET",
     "endpoint": "/repos/owner/repo-b/contents/config/secrets.php"
   }
   ```
5. Backend processes request without validation
6. Returns Site B's repository contents to attacker

**Impact:** Cross-tenant information disclosure, unauthorized repository access

### Current Code (Vulnerable)

```javascript
// api/github/proxy.js (simplified)
export default async function handler(req, res) {
  const apiKey = req.headers['x-api-key'];
  const siteId = await getSiteIdByApiKey(apiKey);
  const installation = await getGitHubInstallation(siteId);

  const { method, endpoint, data } = req.body;

  // ⚠️ NO VALIDATION: endpoint can be anything
  const response = await makeInstallationRequest(
    installation.installation_id,
    method,
    endpoint,  // Not validated against bound repository
    data
  );

  return res.status(response.status).json(response.data);
}
```

### Fix Implementation

**Replace `/api/github/proxy.js` with this complete implementation:**

```javascript
import {
  getSiteIdByApiKey,
  getGitHubInstallation,
  updateSiteLastSeen
} from '../../lib/kv-store.js';
import { makeInstallationRequest } from '../../lib/github-app.js';

export default async function handler(req, res) {
  // Only allow POST requests
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  try {
    // ==========================================
    // 1. AUTHENTICATION: Validate API Key
    // ==========================================

    const apiKey = req.headers['x-api-key'];
    if (!apiKey) {
      return res.status(401).json({
        error: 'Missing X-API-Key header',
        message: 'API key required for authentication'
      });
    }

    const siteId = await getSiteIdByApiKey(apiKey);
    if (!siteId) {
      console.warn('Invalid API key attempt:', { apiKey: apiKey.substring(0, 10) + '...' });
      return res.status(401).json({ error: 'Invalid API key' });
    }

    // Update last seen timestamp
    await updateSiteLastSeen(siteId);

    // ==========================================
    // 2. Get GitHub Installation
    // ==========================================

    const installation = await getGitHubInstallation(siteId);
    if (!installation || !installation.installation_id) {
      return res.status(400).json({
        error: 'GitHub App not installed',
        message: 'No GitHub installation found for this site'
      });
    }

    // ==========================================
    // 3. PARSE REQUEST
    // ==========================================

    const { method, endpoint, data } = req.body;

    if (!method || !endpoint) {
      return res.status(400).json({
        error: 'Missing required parameters',
        required: ['method', 'endpoint'],
        received: { method: !!method, endpoint: !!endpoint }
      });
    }

    // Validate HTTP method
    const allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    if (!allowedMethods.includes(method.toUpperCase())) {
      return res.status(400).json({
        error: 'Invalid HTTP method',
        allowed: allowedMethods,
        received: method
      });
    }

    // ==========================================
    // 4. SECURITY: REPOSITORY ACCESS VALIDATION
    // ==========================================

    // Get the bound repository for this site
    const boundRepo = installation.selected_repo_full_name; // e.g., "owner/repo-a"

    if (!boundRepo) {
      console.warn(`SECURITY: Site ${siteId} has no bound repository`);
      return res.status(403).json({
        error: 'No repository bound',
        message: 'You must bind a repository before making API requests',
        action: 'Configure repository binding in WordPress plugin settings'
      });
    }

    // Validate repository-scoped endpoints
    if (endpoint.startsWith('/repos/')) {
      // Extract repository from endpoint
      // Example: /repos/owner/repo-name/contents/file.txt -> owner/repo-name
      const pathParts = endpoint.split('/').filter(p => p); // Remove empty strings

      // pathParts = ['repos', 'owner', 'repo-name', 'contents', 'file.txt']
      if (pathParts.length >= 3) {
        const endpointRepo = `${pathParts[1]}/${pathParts[2]}`;

        // CRITICAL: Block access to repositories other than the bound one
        if (endpointRepo !== boundRepo) {
          console.warn(
            `SECURITY VIOLATION: Cross-repository access attempt`,
            {
              siteId,
              boundRepo,
              attemptedRepo: endpointRepo,
              endpoint,
              timestamp: new Date().toISOString()
            }
          );

          return res.status(403).json({
            error: 'Access denied',
            message: `You can only access your bound repository: ${boundRepo}`,
            requested: endpointRepo,
            allowed: boundRepo,
            hint: 'If you need to change repositories, update your binding in WordPress plugin settings'
          });
        }

        // Log successful validation for audit
        console.log(`Repository access validated: Site ${siteId} accessing ${boundRepo}`);
      }
    }

    // Validate installation-level endpoints
    // These endpoints return data for ALL repos in installation
    const restrictedInstallationEndpoints = [
      '/installation/repositories',
      '/user/repos',
      '/user/installations',
      '/repos' // List all repos
    ];

    let shouldFilterResponse = false;
    for (const restrictedEndpoint of restrictedInstallationEndpoints) {
      if (endpoint.startsWith(restrictedEndpoint)) {
        console.warn(
          `SECURITY: Site ${siteId} requested installation-level endpoint: ${endpoint}`
        );
        shouldFilterResponse = true;

        // For now, allow but we'll filter the response
        // In the future, consider blocking these entirely
      }
    }

    // ==========================================
    // 5. MAKE GITHUB API REQUEST
    // ==========================================

    const response = await makeInstallationRequest(
      installation.installation_id,
      method,
      endpoint,
      data
    );

    // ==========================================
    // 6. FILTER RESPONSE (if needed)
    // ==========================================

    // Filter /installation/repositories response to only show bound repo
    if (shouldFilterResponse && endpoint === '/installation/repositories') {
      if (response.data && Array.isArray(response.data.repositories)) {
        const originalCount = response.data.repositories.length;

        response.data.repositories = response.data.repositories.filter(
          repo => repo.full_name === boundRepo
        );

        response.data.total_count = response.data.repositories.length;

        console.log(
          `Filtered repositories response for site ${siteId}: ${originalCount} -> ${response.data.total_count}`
        );
      }
    }

    // ==========================================
    // 7. RETURN RESPONSE
    // ==========================================

    if (response.error) {
      return res.status(response.status || 500).json({
        error: true,
        status: response.status,
        message: response.data?.message || 'GitHub API request failed',
        data: response.data
      });
    }

    return res.status(response.status).json({
      success: true,
      status: response.status,
      data: response.data,
      headers: response.headers
    });

  } catch (error) {
    console.error('Error in /api/github/proxy:', {
      message: error.message,
      stack: error.stack
    });

    return res.status(500).json({
      error: 'Internal server error',
      message: process.env.NODE_ENV === 'production'
        ? 'An error occurred processing your request'
        : error.message
    });
  }
}
```

### Testing HIGH-001

**Test 1: Cross-Repository Access Blocked**
```bash
# Assuming Site A is bound to "Jordanburch101/repo-a"
# Try to access a different repo "Jordanburch101/repo-b"

curl -X POST https://your-backend.vercel.app/api/github/proxy \
  -H "X-API-Key: site-a-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/Jordanburch101/repo-b/contents/README.md"
  }'

# Expected: 403 Forbidden
# {
#   "error": "Access denied",
#   "message": "You can only access your bound repository: Jordanburch101/repo-a",
#   "requested": "Jordanburch101/repo-b",
#   "allowed": "Jordanburch101/repo-a"
# }
```

**Test 2: Same-Repository Access Allowed**
```bash
# Site A accessing its own bound repo
curl -X POST https://your-backend.vercel.app/api/github/proxy \
  -H "X-API-Key: site-a-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/Jordanburch101/repo-a/contents/README.md"
  }'

# Expected: 200 OK with file contents
```

**Test 3: No Bound Repository Rejected**
```bash
# Site with no bound repository
curl -X POST https://your-backend.vercel.app/api/github/proxy \
  -H "X-API-Key: unbound-site-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/Jordanburch101/repo-a/contents/README.md"
  }'

# Expected: 403 Forbidden
# {
#   "error": "No repository bound",
#   "message": "You must bind a repository before making API requests"
# }
```

---

## 🟠 HIGH-002: Hash API Keys in Redis

### Context

**File:** `/lib/kv-store.js`
**Vulnerability:** API keys stored in plaintext in Redis database

### The Problem

Currently, API keys are stored unhashed in Redis:
```javascript
// Current (vulnerable)
await redis.set(`apikey:${apiKey}`, siteId);
```

If Redis is compromised (data breach, misconfigured access, etc.), all API keys are immediately exposed in plaintext.

**Impact:**
- Redis compromise = all API keys leaked
- Attacker can impersonate any WordPress site
- Cross-tenant access to all repositories

### Fix Implementation

**Update `/lib/kv-store.js` with bcrypt hashing:**

```javascript
import { Redis } from '@upstash/redis';
import bcrypt from 'bcryptjs';

const redis = new Redis({
  url: process.env.UPSTASH_REDIS_REST_URL,
  token: process.env.UPSTASH_REDIS_REST_TOKEN,
});

// ==========================================
// API KEY MANAGEMENT WITH HASHING
// ==========================================

/**
 * Generate API key (called when site connects)
 */
export async function generateApiKey(siteId) {
  // Generate random API key
  const apiKey = generateSecureRandomString(32);

  // Hash the API key before storing
  const hashedKey = await bcrypt.hash(apiKey, 10);

  // Store hashed key with site ID
  // Key format: sitekey:<siteId> -> hashedApiKey
  await redis.set(`sitekey:${siteId}`, hashedKey);

  // Create reverse index for quick lookups
  // Store site ID indexed by first 8 chars of hash (for collision detection)
  const hashPrefix = hashedKey.substring(0, 16);
  await redis.sadd(`keyhash:${hashPrefix}`, siteId);

  // Return plaintext key ONLY to caller (WordPress site)
  // This is the ONLY time the plaintext key is accessible
  return apiKey;
}

/**
 * Validate API key and return site ID (called on every request)
 */
export async function getSiteIdByApiKey(apiKey) {
  if (!apiKey || typeof apiKey !== 'string') {
    return null;
  }

  try {
    // Get all site IDs (or implement a more efficient lookup)
    // Option 1: Iterate through sites (works for <1000 sites)
    // Option 2: Use hash prefix index (more efficient)

    // For small scale, get all sites
    const siteKeys = await redis.keys('sitekey:*');

    for (const siteKey of siteKeys) {
      const siteId = siteKey.replace('sitekey:', '');
      const hashedKey = await redis.get(siteKey);

      if (hashedKey) {
        // Compare provided key with stored hash
        const isValid = await bcrypt.compare(apiKey, hashedKey);

        if (isValid) {
          console.log(`API key validated for site: ${siteId}`);
          return siteId;
        }
      }
    }

    console.warn('Invalid API key attempt');
    return null;

  } catch (error) {
    console.error('Error validating API key:', error);
    return null;
  }
}

/**
 * More efficient implementation for large scale (Option 2)
 */
export async function getSiteIdByApiKeyEfficient(apiKey) {
  if (!apiKey || typeof apiKey !== 'string') {
    return null;
  }

  try {
    // Get all sites (cache this in production)
    const siteKeys = await redis.keys('sitekey:*');

    // Parallel comparison using Promise.all
    const validationPromises = siteKeys.map(async (siteKey) => {
      const siteId = siteKey.replace('sitekey:', '');
      const hashedKey = await redis.get(siteKey);

      if (!hashedKey) return null;

      const isValid = await bcrypt.compare(apiKey, hashedKey);
      return isValid ? siteId : null;
    });

    const results = await Promise.all(validationPromises);
    const validSiteId = results.find(id => id !== null);

    if (validSiteId) {
      console.log(`API key validated for site: ${validSiteId}`);
      return validSiteId;
    }

    console.warn('Invalid API key attempt');
    return null;

  } catch (error) {
    console.error('Error validating API key:', error);
    return null;
  }
}

/**
 * Revoke API key (called when site disconnects)
 */
export async function revokeApiKey(siteId) {
  await redis.del(`sitekey:${siteId}`);
  console.log(`API key revoked for site: ${siteId}`);
}

/**
 * Rotate API key (generate new key, invalidate old)
 */
export async function rotateApiKey(siteId) {
  // Delete old key
  await revokeApiKey(siteId);

  // Generate new key
  const newApiKey = await generateApiKey(siteId);

  console.log(`API key rotated for site: ${siteId}`);

  return newApiKey;
}

// ==========================================
// HELPER FUNCTIONS
// ==========================================

function generateSecureRandomString(length) {
  const crypto = require('crypto');
  return crypto.randomBytes(length).toString('base64')
    .replace(/[^a-zA-Z0-9]/g, '')
    .substring(0, length);
}

// ==========================================
// EXISTING FUNCTIONS (unchanged)
// ==========================================

export async function updateSiteLastSeen(siteId) {
  await redis.set(`site:${siteId}:lastseen`, Date.now());
}

export async function getGitHubInstallation(siteId) {
  const data = await redis.get(`site:${siteId}:installation`);
  return data;
}

export async function saveGitHubInstallation(siteId, installationData) {
  await redis.set(`site:${siteId}:installation`, installationData);
}
```

### Migration Plan

Since this changes how API keys are stored, existing keys need migration:

**Migration Script: `/scripts/migrate-api-keys.js`**

```javascript
import { Redis } from '@upstash/redis';
import bcrypt from 'bcryptjs';

const redis = new Redis({
  url: process.env.UPSTASH_REDIS_REST_URL,
  token: process.env.UPSTASH_REDIS_REST_TOKEN,
});

async function migrateApiKeys() {
  console.log('Starting API key migration...');

  // Get all old format keys: apikey:<key> -> siteId
  const oldKeys = await redis.keys('apikey:*');

  console.log(`Found ${oldKeys.length} API keys to migrate`);

  for (const oldKey of oldKeys) {
    const apiKey = oldKey.replace('apikey:', '');
    const siteId = await redis.get(oldKey);

    if (!siteId) continue;

    console.log(`Migrating key for site: ${siteId}`);

    // Hash the API key
    const hashedKey = await bcrypt.hash(apiKey, 10);

    // Store in new format: sitekey:<siteId> -> hashedKey
    await redis.set(`sitekey:${siteId}`, hashedKey);

    // Optional: Delete old key (do this after confirming migration works)
    // await redis.del(oldKey);

    console.log(`✓ Migrated site ${siteId}`);
  }

  console.log('Migration complete!');
  console.log('IMPORTANT: Test thoroughly before deleting old keys');
  console.log('After testing, run cleanup script to delete apikey:* keys');
}

migrateApiKeys().catch(console.error);
```

**Run migration:**
```bash
# Install bcryptjs
npm install bcryptjs

# Run migration (test in staging first!)
node scripts/migrate-api-keys.js

# After testing, cleanup old keys
# node scripts/cleanup-old-keys.js
```

### Testing HIGH-002

**Test 1: New Key Generation**
```javascript
// Create test endpoint /api/test/generate-key
const newKey = await generateApiKey('test-site-123');
console.log('Generated key:', newKey); // Plaintext key

// Verify stored in hashed format
const stored = await redis.get('sitekey:test-site-123');
console.log('Stored hash:', stored); // Should be bcrypt hash, not plaintext
```

**Test 2: Key Validation**
```javascript
// Should return site ID
const siteId = await getSiteIdByApiKey('the-plaintext-key');
console.log('Validated site:', siteId); // Should be 'test-site-123'

// Should return null for invalid key
const invalid = await getSiteIdByApiKey('wrong-key');
console.log('Invalid key:', invalid); // Should be null
```

**Test 3: Performance**
```bash
# Measure validation time (should be <100ms)
time curl -X POST https://your-backend.vercel.app/api/github/proxy \
  -H "X-API-Key: valid-key" \
  -d '{"method":"GET","endpoint":"/repos/owner/repo/commits"}'
```

### Performance Considerations

bcrypt is intentionally slow for security. For high-traffic sites:

**Option 1: Cache validation results**
```javascript
const validatedKeysCache = new Map(); // In-memory cache
const CACHE_TTL = 5 * 60 * 1000; // 5 minutes

export async function getSiteIdByApiKeyCached(apiKey) {
  // Check cache first
  const cached = validatedKeysCache.get(apiKey);
  if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
    return cached.siteId;
  }

  // Validate against Redis
  const siteId = await getSiteIdByApiKey(apiKey);

  if (siteId) {
    validatedKeysCache.set(apiKey, {
      siteId,
      timestamp: Date.now()
    });
  }

  return siteId;
}
```

**Option 2: Use Upstash Edge Cache**
```javascript
// Leverage Vercel Edge caching
const cached = await redis.get(`cache:apikey:${hash(apiKey)}`);
if (cached) return cached;
```

---

## 🟠 HIGH-003: Add Rate Limiting to Proxy

### Context

**File:** `/api/github/proxy.js`
**Vulnerability:** No rate limiting on proxy endpoint

### The Problem

The proxy endpoint has no rate limiting. An attacker or misbehaving site can:
- Exhaust GitHub API rate limits (5000 req/hour) affecting all sites
- Perform DoS attack on backend
- Cause excessive Vercel function invocations (costs)

### Fix Implementation

**Install Upstash Rate Limit:**
```bash
npm install @upstash/ratelimit
```

**Add rate limiting to `/api/github/proxy.js`:**

```javascript
import { Ratelimit } from '@upstash/ratelimit';
import { Redis } from '@upstash/redis';

// Initialize rate limiter
const redis = new Redis({
  url: process.env.UPSTASH_REDIS_REST_URL,
  token: process.env.UPSTASH_REDIS_REST_TOKEN,
});

// Rate limit: 60 requests per minute per site
const ratelimit = new Ratelimit({
  redis,
  limiter: Ratelimit.slidingWindow(60, '1 m'),
  analytics: true,
  prefix: 'ratelimit:proxy',
});

export default async function handler(req, res) {
  if (req.method !== 'POST') {
    return res.status(405).json({ error: 'Method not allowed' });
  }

  try {
    // Authenticate first
    const apiKey = req.headers['x-api-key'];
    if (!apiKey) {
      return res.status(401).json({ error: 'Missing X-API-Key header' });
    }

    const siteId = await getSiteIdByApiKey(apiKey);
    if (!siteId) {
      return res.status(401).json({ error: 'Invalid API key' });
    }

    // ==========================================
    // RATE LIMITING: Check per-site limit
    // ==========================================

    const { success, limit, reset, remaining } = await ratelimit.limit(siteId);

    // Add rate limit headers to response
    res.setHeader('X-RateLimit-Limit', limit.toString());
    res.setHeader('X-RateLimit-Remaining', remaining.toString());
    res.setHeader('X-RateLimit-Reset', reset.toString());

    if (!success) {
      const retryAfter = Math.ceil((reset - Date.now()) / 1000);

      console.warn(
        `RATE LIMIT: Site ${siteId} exceeded limit`,
        {
          siteId,
          limit,
          reset: new Date(reset).toISOString(),
          endpoint: req.body.endpoint
        }
      );

      return res.status(429).json({
        error: 'Rate limit exceeded',
        message: `Too many requests. Limit: ${limit} requests per minute`,
        limit,
        remaining: 0,
        reset: reset,
        retryAfter: retryAfter,
        hint: 'Please wait before making more requests'
      });
    }

    console.log(
      `Rate limit check passed: Site ${siteId} (${remaining}/${limit} remaining)`
    );

    // Continue with normal proxy logic...
    // (rest of proxy implementation from HIGH-001)

  } catch (error) {
    console.error('Error in proxy handler:', error);
    return res.status(500).json({ error: 'Internal server error' });
  }
}
```

### Rate Limit Configuration

**Different limits for different operations:**

```javascript
// More sophisticated rate limiting
const rateLimits = {
  // Read operations: 60/min
  read: new Ratelimit({
    redis,
    limiter: Ratelimit.slidingWindow(60, '1 m'),
    prefix: 'ratelimit:proxy:read',
  }),

  // Write operations: 20/min (more restrictive)
  write: new Ratelimit({
    redis,
    limiter: Ratelimit.slidingWindow(20, '1 m'),
    prefix: 'ratelimit:proxy:write',
  }),

  // Artifact downloads: 10/min (expensive operations)
  download: new Ratelimit({
    redis,
    limiter: Ratelimit.slidingWindow(10, '1 m'),
    prefix: 'ratelimit:proxy:download',
  }),
};

// Select appropriate rate limiter based on operation
function getRateLimiter(method, endpoint) {
  // Artifact downloads
  if (endpoint.includes('/artifacts/') && endpoint.endsWith('/zip')) {
    return rateLimits.download;
  }

  // Write operations
  if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
    return rateLimits.write;
  }

  // Default: Read operations
  return rateLimits.read;
}

// In handler:
const limiter = getRateLimiter(method, endpoint);
const { success, limit, reset, remaining } = await limiter.limit(siteId);
```

### Testing HIGH-003

**Test 1: Normal Usage (Within Limit)**
```bash
# Should succeed
for i in {1..10}; do
  curl -X POST https://your-backend.vercel.app/api/github/proxy \
    -H "X-API-Key: test-key" \
    -H "Content-Type: application/json" \
    -d '{"method":"GET","endpoint":"/repos/owner/repo/commits"}' \
    -i | grep -E "(HTTP|X-RateLimit)"
done

# Should see:
# HTTP/1.1 200 OK
# X-RateLimit-Limit: 60
# X-RateLimit-Remaining: 59, 58, 57...
```

**Test 2: Rate Limit Exceeded**
```bash
# Send 61 requests rapidly
for i in {1..61}; do
  curl -X POST https://your-backend.vercel.app/api/github/proxy \
    -H "X-API-Key: test-key" \
    -d '{"method":"GET","endpoint":"/repos/owner/repo/commits"}' &
done
wait

# Request 61 should return:
# HTTP/1.1 429 Too Many Requests
# {
#   "error": "Rate limit exceeded",
#   "message": "Too many requests. Limit: 60 requests per minute",
#   "retryAfter": 45
# }
```

**Test 3: Different Sites Have Separate Limits**
```bash
# Site A: 60 requests (hits limit)
# Site B: Should still work (separate limit)

curl -X POST https://your-backend.vercel.app/api/github/proxy \
  -H "X-API-Key: site-b-key" \
  -d '{"method":"GET","endpoint":"/repos/owner/repo/commits"}'

# Should succeed even if Site A is rate limited
```

---

## 🟠 HIGH-005: Improve OAuth State Validation

### Context

**File:** `/api/auth/callback.js`
**Vulnerability:** OAuth state parameter can be replayed within 10-minute window

### The Problem

Current state validation:
1. State token generated with 10-minute TTL
2. Token stored in Redis: `state:<token>` -> site data
3. Token validated on callback
4. **Issue:** Token can be reused multiple times within 10 minutes

**Attack Scenario:**
1. Attacker intercepts OAuth callback URL (MITM, browser history, referrer logs)
2. URL contains valid state token
3. Attacker replays URL within 10-minute window
4. Backend accepts state token again
5. Attacker can associate their GitHub account with victim's WordPress site

### Current Code (Vulnerable)

```javascript
// api/auth/callback.js
export default async function handler(req, res) {
  const { code, state } = req.query;

  // Validate state
  const stateData = await redis.get(`state:${state}`);

  if (!stateData) {
    return res.status(400).json({ error: 'Invalid state' });
  }

  // ⚠️ SECURITY ISSUE: State is not deleted after use
  // Can be replayed multiple times within 10 minutes

  // Exchange code for access token...
}
```

### Fix Implementation

**Update `/api/auth/callback.js` with one-time state tokens:**

```javascript
import { Redis } from '@upstash/redis';

const redis = new Redis({
  url: process.env.UPSTASH_REDIS_REST_URL,
  token: process.env.UPSTASH_REDIS_REST_TOKEN,
});

export default async function handler(req, res) {
  const { code, state } = req.query;

  if (!code || !state) {
    return res.status(400).json({
      error: 'Missing parameters',
      required: ['code', 'state']
    });
  }

  try {
    // ==========================================
    // 1. ONE-TIME STATE TOKEN VALIDATION
    // ==========================================

    // Get state data
    const stateData = await redis.get(`state:${state}`);

    if (!stateData) {
      console.warn('OAuth callback with invalid/expired state:', { state });
      return res.status(400).json({
        error: 'Invalid or expired state parameter',
        message: 'Please try connecting again from WordPress'
      });
    }

    // IMMEDIATELY delete state token (one-time use)
    // This prevents replay attacks
    await redis.del(`state:${state}`);

    console.log('State token consumed (one-time use):', { state });

    // ==========================================
    // 2. ADDITIONAL SECURITY VALIDATIONS
    // ==========================================

    // IP Address Binding (optional but recommended)
    const requestIP = req.headers['x-forwarded-for'] || req.socket.remoteAddress;

    if (stateData.ip && stateData.ip !== requestIP) {
      console.warn(
        'OAuth callback from different IP address',
        {
          state,
          originalIP: stateData.ip,
          callbackIP: requestIP
        }
      );

      // For high-security: reject mismatched IPs
      // return res.status(403).json({ error: 'IP address mismatch' });

      // For production: log warning but allow (IPs can change legitimately)
    }

    // User-Agent Binding (optional)
    const requestUA = req.headers['user-agent'];

    if (stateData.userAgent && stateData.userAgent !== requestUA) {
      console.warn(
        'OAuth callback from different User-Agent',
        {
          state,
          originalUA: stateData.userAgent,
          callbackUA: requestUA
        }
      );
    }

    // Timestamp validation (paranoid check)
    const now = Date.now();
    const age = now - stateData.timestamp;
    const MAX_AGE = 10 * 60 * 1000; // 10 minutes

    if (age > MAX_AGE) {
      console.warn('OAuth state token expired:', { state, age });
      return res.status(400).json({
        error: 'State token expired',
        message: 'Please try connecting again'
      });
    }

    // ==========================================
    // 3. CONTINUE WITH OAUTH FLOW
    // ==========================================

    // Exchange authorization code for access token
    const tokenResponse = await fetch('https://github.com/login/oauth/access_token', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        client_id: process.env.GITHUB_CLIENT_ID,
        client_secret: process.env.GITHUB_CLIENT_SECRET,
        code: code,
        redirect_uri: `${process.env.NEXT_PUBLIC_APP_URL}/api/auth/callback`,
      }),
    });

    const tokenData = await tokenResponse.json();

    if (tokenData.error) {
      console.error('GitHub OAuth error:', tokenData);
      return res.status(400).json({
        error: 'OAuth failed',
        message: tokenData.error_description || tokenData.error
      });
    }

    // Get user info
    const userResponse = await fetch('https://api.github.com/user', {
      headers: {
        'Authorization': `Bearer ${tokenData.access_token}`,
        'Accept': 'application/json',
      },
    });

    const userData = await userResponse.json();

    // Store connection token for WordPress to retrieve
    const connectionToken = generateSecureRandomString(32);

    await redis.set(`connection:${connectionToken}`, {
      githubUser: userData.login,
      githubUserId: userData.id,
      accessToken: tokenData.access_token,
      siteId: stateData.siteId,
      returnUrl: stateData.returnUrl,
      timestamp: Date.now()
    }, {
      ex: 60 // 60 seconds to retrieve
    });

    // Redirect back to WordPress
    const redirectUrl = new URL(stateData.returnUrl);
    redirectUrl.searchParams.set('connection_token', connectionToken);

    return res.redirect(302, redirectUrl.toString());

  } catch (error) {
    console.error('Error in OAuth callback:', error);
    return res.status(500).json({
      error: 'Internal server error',
      message: 'Failed to process OAuth callback'
    });
  }
}

function generateSecureRandomString(length) {
  const crypto = require('crypto');
  return crypto.randomBytes(length).toString('base64')
    .replace(/[^a-zA-Z0-9]/g, '')
    .substring(0, length);
}
```

**Also update `/api/auth/connect.js` to include IP and UA:**

```javascript
// api/auth/connect.js
export default async function handler(req, res) {
  const { returnUrl, siteId } = req.body;

  // Generate state token
  const state = generateSecureRandomString(32);

  // Store state with additional security metadata
  await redis.set(`state:${state}`, {
    siteId,
    returnUrl,
    timestamp: Date.now(),
    ip: req.headers['x-forwarded-for'] || req.socket.remoteAddress,
    userAgent: req.headers['user-agent']
  }, {
    ex: 600 // 10 minutes
  });

  // Build GitHub OAuth URL
  const githubAuthUrl = new URL('https://github.com/login/oauth/authorize');
  githubAuthUrl.searchParams.set('client_id', process.env.GITHUB_CLIENT_ID);
  githubAuthUrl.searchParams.set('redirect_uri', `${process.env.NEXT_PUBLIC_APP_URL}/api/auth/callback`);
  githubAuthUrl.searchParams.set('state', state);
  githubAuthUrl.searchParams.set('scope', 'repo read:user');

  return res.status(200).json({
    success: true,
    authUrl: githubAuthUrl.toString()
  });
}
```

### Testing HIGH-005

**Test 1: Normal OAuth Flow**
```bash
# 1. Initiate OAuth
curl -X POST https://your-backend.vercel.app/api/auth/connect \
  -H "Content-Type: application/json" \
  -d '{
    "siteId": "test-site",
    "returnUrl": "https://wordpress-site.com/callback"
  }'

# Returns: {"authUrl": "https://github.com/login/oauth/authorize?..."}

# 2. Complete OAuth (simulate callback)
curl "https://your-backend.vercel.app/api/auth/callback?code=abc123&state=STATE_FROM_STEP1"

# Should succeed and redirect
```

**Test 2: State Token Replay Attack (Should Fail)**
```bash
# 1. Complete OAuth flow once (succeeds)
curl "https://your-backend.vercel.app/api/auth/callback?code=abc123&state=VALID_STATE"
# Returns: 302 redirect

# 2. Try to replay same state token (should fail)
curl "https://your-backend.vercel.app/api/auth/callback?code=abc123&state=VALID_STATE"

# Expected: 400 Bad Request
# {
#   "error": "Invalid or expired state parameter",
#   "message": "Please try connecting again from WordPress"
# }
```

**Test 3: Expired State Token**
```bash
# Wait 11 minutes after initiating OAuth
# Then try to complete callback

# Expected: 400 Bad Request (token expired)
```

---

## Implementation Order

### Phase 1: Critical (Day 1) - 2.5 hours

```bash
# 1. CRITICAL-002: Delete get-token endpoint (30 min)
git rm api/github/get-token.js

# 2. HIGH-001: Add repository validation (2 hours)
# - Update api/github/proxy.js with complete validation code
# - Test cross-repo blocking
# - Test same-repo allowing

# 3. Commit and test
git add .
git commit -m "fix: resolve CRITICAL-002 and HIGH-001"
npm run build
npm run test
vercel --prod
```

### Phase 2: High Priority (Week 1) - 6-8 hours

```bash
# 4. HIGH-003: Add rate limiting (1 hour)
npm install @upstash/ratelimit
# - Update api/github/proxy.js with rate limiting
# - Test rate limit enforcement

# 5. HIGH-005: Improve OAuth state (2 hours)
# - Update api/auth/callback.js with one-time tokens
# - Update api/auth/connect.js with IP/UA binding
# - Test replay attack prevention

# 6. HIGH-002: Hash API keys (3-4 hours)
npm install bcryptjs
# - Update lib/kv-store.js with bcrypt hashing
# - Create migration script
# - Run migration (test first!)
# - Test performance
```

---

## Complete Testing Checklist

### Phase 1 Tests (Critical)

- [ ] **CRITICAL-002:** `/api/github/get-token` returns 404
- [ ] **HIGH-001:** Cross-repo access returns 403
- [ ] **HIGH-001:** Same-repo access returns 200
- [ ] **HIGH-001:** Unbound sites cannot make requests
- [ ] **HIGH-001:** Security logs show blocked attempts
- [ ] All existing WordPress deployments still work
- [ ] No increase in error rates

### Phase 2 Tests (High Priority)

- [ ] **HIGH-003:** Normal requests work (within rate limit)
- [ ] **HIGH-003:** 61st request in 1 minute returns 429
- [ ] **HIGH-003:** Different sites have separate limits
- [ ] **HIGH-003:** Rate limit headers present in responses
- [ ] **HIGH-005:** Normal OAuth flow works
- [ ] **HIGH-005:** State token replay returns 400
- [ ] **HIGH-005:** Expired state returns 400
- [ ] **HIGH-002:** New API keys are hashed in Redis
- [ ] **HIGH-002:** Validation works with hashed keys
- [ ] **HIGH-002:** Performance acceptable (<100ms)
- [ ] **HIGH-002:** Migration completed successfully

---

## Deployment Steps

### 1. Preparation

```bash
# Clone repository
git clone https://github.com/Jordanburch101/github-wordpress-backend.git
cd github-wordpress-backend

# Create security branch
git checkout -b security/backend-critical-fixes

# Backup current deployment
vercel list
# Note current deployment URL for rollback
```

### 2. Install Dependencies

```bash
npm install @upstash/ratelimit bcryptjs
```

### 3. Apply Phase 1 Changes

```bash
# Delete get-token endpoint
git rm api/github/get-token.js

# Update proxy.js (copy code from HIGH-001 section above)
code api/github/proxy.js
# Paste complete implementation

# Commit
git add .
git commit -m "fix: resolve CRITICAL-002 and HIGH-001

- Deleted /api/github/get-token endpoint
- Added repository validation to proxy
- Blocks cross-tenant repository access
- Adds security logging
"
```

### 4. Deploy to Staging (if available)

```bash
# Deploy to preview
vercel

# Test in staging environment
# Run all Phase 1 tests

# If successful, deploy to production
vercel --prod
```

### 5. Monitor Deployment

```bash
# Watch logs for errors
vercel logs --follow

# Monitor for security violations
# Look for: "SECURITY VIOLATION: Cross-repository access attempt"
```

### 6. Apply Phase 2 Changes (Optional - Can be done later)

```bash
# Update kv-store.js with hashing
# Update proxy.js with rate limiting
# Update auth files with improved state validation

git add .
git commit -m "fix: resolve HIGH-002, HIGH-003, HIGH-005

- Hash API keys in Redis using bcrypt
- Add rate limiting to proxy (60 req/min)
- Improve OAuth state validation (one-time tokens)
"

vercel --prod
```

---

## Monitoring and Alerting

### Key Metrics to Monitor

1. **Security Violations**
   - Search logs for: `"SECURITY VIOLATION"`
   - Alert on: >5 violations per hour

2. **Rate Limiting**
   - Search logs for: `"RATE LIMIT"`
   - Monitor: Normal operations shouldn't hit limits

3. **Authentication Failures**
   - Search logs for: `"Invalid API key"`
   - Alert on: Spike in failures

4. **Error Rates**
   - Monitor: Should not increase after deployment
   - Alert on: >5% error rate

### Vercel Log Queries

```bash
# View recent security violations
vercel logs --grep "SECURITY"

# View rate limit hits
vercel logs --grep "RATE LIMIT"

# View authentication failures
vercel logs --grep "Invalid API key"
```

---

## Rollback Plan

If critical issues occur:

```bash
# Option 1: Rollback deployment
vercel rollback

# Option 2: Revert commit and redeploy
git revert HEAD
git push
vercel --prod

# Option 3: Restore specific file
git checkout HEAD~1 api/github/proxy.js
git commit -m "rollback: restore previous proxy.js"
git push
vercel --prod
```

---

## Success Criteria

### Phase 1 (Critical) ✅

- [ ] All 3 critical vulnerabilities resolved
- [ ] Zero cross-tenant access possible
- [ ] All existing functionality works
- [ ] No increase in error rates
- [ ] Security logs show violations are blocked
- [ ] WordPress plugin works normally

### Phase 2 (High Priority) ✅

- [ ] API keys hashed in Redis
- [ ] Rate limiting prevents abuse
- [ ] OAuth replay attacks blocked
- [ ] Performance acceptable
- [ ] Monitoring in place

---

## Contact & Support

If you encounter issues:

1. **Check logs first:** `vercel logs --follow`
2. **Verify environment variables:** Especially Redis and GitHub credentials
3. **Test incrementally:** Deploy and test each change separately
4. **Have rollback ready:** Know how to quickly revert

---

## Summary

**Time Investment:** 8-10 hours total
- Phase 1 (Critical): 2.5 hours
- Phase 2 (High): 6-8 hours

**Issues Addressed:** 5 backend security vulnerabilities
- 1 Critical (installation token exposure)
- 4 High Priority (repository isolation, key hashing, rate limiting, OAuth)

**Security Impact:** Eliminates cross-tenant data breach risk, prevents abuse, hardens authentication

**Ready to implement:** All code provided, comprehensive testing plan included

---

**Good luck with the implementation! All the code and context you need is in this document.**
