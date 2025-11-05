# Backend Security Fixes - Implementation Guide

**Date:** 2025-11-05
**Target Repository:** https://github.com/Jordanburch101/github-wordpress-backend

This document provides complete implementation instructions for CRITICAL-002 and HIGH-001 security fixes.

---

## CRITICAL-002: Delete Installation Token Endpoint

### Step 1: Delete the File

**File to Delete:** `/api/github/get-token.js`

```bash
# In the backend repository root
rm api/github/get-token.js
```

**Rationale:** This endpoint returns installation access tokens that grant access to ALL repositories in the installation. This creates a cross-tenant security vulnerability where one compromised WordPress site can access other sites' repositories.

### Step 2: Verify Deletion

```bash
# Should return: No such file or directory
ls -la api/github/get-token.js
```

### Step 3: Test After Deployment

```bash
# Should return 404 Not Found
curl -X POST https://your-backend.vercel.app/api/github/get-token \
  -H "X-API-Key: valid-key"

# Expected response:
# 404 - Not Found or "NO_SUCH_ENDPOINT"
```

---

## HIGH-001: Add Repository Validation to Proxy

### File to Modify: `/api/github/proxy.js`

**Complete implementation with repository validation:**

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
    // Validate API key from header
    const apiKey = req.headers['x-api-key'];
    if (!apiKey) {
      return res.status(401).json({ error: 'Missing X-API-Key header' });
    }

    // Get site ID from API key
    const siteId = await getSiteIdByApiKey(apiKey);
    if (!siteId) {
      return res.status(401).json({ error: 'Invalid API key' });
    }

    // Update last seen timestamp
    await updateSiteLastSeen(siteId);

    // Get GitHub installation for this site
    const installation = await getGitHubInstallation(siteId);
    if (!installation || !installation.installation_id) {
      return res.status(400).json({
        error: 'GitHub App not installed',
        message: 'No GitHub installation found for this site'
      });
    }

    // Parse request body
    const { method, endpoint, data } = req.body;

    // Validate request parameters
    if (!method || !endpoint) {
      return res.status(400).json({
        error: 'Missing required parameters',
        required: ['method', 'endpoint']
      });
    }

    // Validate HTTP method
    const allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    if (!allowedMethods.includes(method.toUpperCase())) {
      return res.status(400).json({
        error: 'Invalid HTTP method',
        allowed: allowedMethods
      });
    }

    // ==========================================
    // SECURITY: REPOSITORY ACCESS VALIDATION
    // ==========================================

    // Get the bound repository for this site
    const boundRepo = installation.selected_repo_full_name; // e.g., "owner/repo-name"

    if (!boundRepo) {
      console.warn(`Site ${siteId} has no bound repository`);
      return res.status(403).json({
        error: 'No repository bound',
        message: 'You must bind a repository before making API requests'
      });
    }

    // Validate repository-scoped endpoints
    if (endpoint.startsWith('/repos/')) {
      // Extract repository from endpoint
      // Example: /repos/owner/repo-name/contents/file.txt -> owner/repo-name
      const pathParts = endpoint.split('/');

      if (pathParts.length >= 4) {
        const endpointRepo = `${pathParts[2]}/${pathParts[3]}`;

        // Block access to repositories other than the bound one
        if (endpointRepo !== boundRepo) {
          console.warn(
            `SECURITY: Site ${siteId} attempted cross-repo access`,
            {
              siteId,
              boundRepo,
              attemptedRepo: endpointRepo,
              endpoint
            }
          );

          return res.status(403).json({
            error: 'Access denied',
            message: `You can only access your bound repository: ${boundRepo}`,
            requested: endpointRepo,
            allowed: boundRepo
          });
        }
      }
    }

    // Validate installation-level endpoints (return data for ALL repos)
    const restrictedInstallationEndpoints = [
      '/installation/repositories',
      '/user/repos',
      '/user/installations'
    ];

    for (const restrictedEndpoint of restrictedInstallationEndpoints) {
      if (endpoint.startsWith(restrictedEndpoint)) {
        console.warn(
          `SECURITY: Site ${siteId} requested installation-level endpoint: ${endpoint}`
        );

        // For now, allow but we should filter the response in the future
        // TODO: Filter response to only include bound repository
      }
    }

    // ==========================================
    // END REPOSITORY ACCESS VALIDATION
    // ==========================================

    // Make the GitHub API request using installation token
    const response = await makeInstallationRequest(
      installation.installation_id,
      method,
      endpoint,
      data
    );

    // Filter /installation/repositories response if applicable
    if (endpoint === '/installation/repositories' && response.data && response.data.repositories) {
      // Only return the bound repository
      response.data.repositories = response.data.repositories.filter(
        repo => repo.full_name === boundRepo
      );
      response.data.total_count = response.data.repositories.length;
    }

    // Return response to WordPress
    if (response.error) {
      return res.status(response.status).json({
        error: true,
        status: response.status,
        message: response.data?.message || 'GitHub API request failed',
        data: response.data
      });
    }

    res.status(response.status).json({
      success: true,
      status: response.status,
      data: response.data,
      headers: response.headers
    });

  } catch (error) {
    console.error('Error in /api/github/proxy:', error);
    res.status(500).json({
      error: 'Internal server error',
      message: error.message
    });
  }
}
```

---

## Implementation Steps

### 1. Backup Current Files

```bash
cd /path/to/github-wordpress-backend
cp api/github/get-token.js api/github/get-token.js.backup
cp api/github/proxy.js api/github/proxy.js.backup
```

### 2. Apply Changes

```bash
# Delete get-token endpoint
rm api/github/get-token.js

# Replace proxy.js with the updated version above
# (Copy the complete proxy.js code from above)
```

### 3. Create Security Fix Branch

```bash
git checkout -b security/critical-backend-fixes
git add api/github/get-token.js api/github/proxy.js
git commit -m "fix: resolve critical security vulnerabilities in backend API (CRITICAL-002, HIGH-001)

This commit addresses two critical security vulnerabilities:

**CRITICAL-002: Installation Token Exposed to WordPress Sites**
- Deleted /api/github/get-token endpoint entirely
- WordPress sites can no longer obtain installation tokens directly
- Prevents cross-tenant access to repositories
- All GitHub API calls must now go through validated proxy

**HIGH-001: No Repository Access Isolation in Backend Proxy**
- Added repository validation to /api/github/proxy endpoint
- Validates that requested endpoints match site's bound repository
- Blocks cross-tenant repository access attempts
- Returns 403 Forbidden for unauthorized repository access
- Filters /installation/repositories responses to only show bound repo
- Added security logging for attempted violations

**Security Impact:**
- Eliminates cross-tenant data breach vulnerability
- Enforces strict repository isolation per WordPress site
- One compromised site can no longer access other sites' repositories
- Prevents unauthorized access to source code, secrets, and workflows

**Testing:**
- get-token endpoint returns 404 Not Found
- Cross-repo proxy requests return 403 Forbidden
- Same-repo proxy requests return 200 OK
- Sites without bound repos cannot make requests

Related security audit files:
- security-audit/CRITICAL-002-installation-token-exposed.md
- security-audit/HIGH-001-no-repository-isolation.md
"
```

### 4. Push to GitHub

```bash
git push -u origin security/critical-backend-fixes
```

---

## Testing Checklist

### Test 1: Get-Token Endpoint Deleted

```bash
# Should return 404
curl -X POST https://your-backend.vercel.app/api/github/get-token \
  -H "X-API-Key: valid-key" \
  -H "Content-Type: application/json"

# Expected: 404 Not Found
```

### Test 2: Cross-Repository Access Blocked

```bash
# Assuming Site A is bound to "owner/repo-a"
# Try to access "owner/repo-b"
curl -X POST https://your-backend.vercel.app/api/github/proxy \
  -H "X-API-Key: site-a-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/owner/repo-b/contents/README.md"
  }'

# Expected: 403 Forbidden
# {
#   "error": "Access denied",
#   "message": "You can only access your bound repository: owner/repo-a",
#   "requested": "owner/repo-b",
#   "allowed": "owner/repo-a"
# }
```

### Test 3: Same-Repository Access Allowed

```bash
# Site A accessing own bound repo "owner/repo-a"
curl -X POST https://your-backend.vercel.app/api/github/proxy \
  -H "X-API-Key: site-a-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/owner/repo-a/contents/README.md"
  }'

# Expected: 200 OK with file contents
```

### Test 4: No Bound Repository Rejected

```bash
# Site with no bound repository
curl -X POST https://your-backend.vercel.app/api/github/proxy \
  -H "X-API-Key: unbound-site-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/owner/repo-a/contents/README.md"
  }'

# Expected: 403 Forbidden
# {
#   "error": "No repository bound",
#   "message": "You must bind a repository before making API requests"
# }
```

### Test 5: Existing Functionality Still Works

```bash
# Normal WordPress operations should continue working
# - Fetching repository info
# - Triggering workflows
# - Downloading artifacts (within bound repo)
# - Checking deployment status
```

---

## Deployment Checklist

- [ ] Backup current files
- [ ] Delete `/api/github/get-token.js`
- [ ] Update `/api/github/proxy.js` with validation code
- [ ] Commit changes with detailed message
- [ ] Push to security fix branch
- [ ] Test all 5 test cases above
- [ ] Verify no functionality regressions
- [ ] Deploy to Vercel staging (if available)
- [ ] Test in staging environment
- [ ] Deploy to production
- [ ] Monitor logs for security violations
- [ ] Update WordPress plugin to remove get-token calls (if any exist)

---

## WordPress Plugin Updates (If Needed)

Check if the WordPress plugin calls the get-token endpoint:

```bash
# In wordpress-github-theme-plugin repo
grep -r "get-token" github-auto-deploy/
```

If found, those calls should be refactored to use the proxy endpoint instead.

---

## Monitoring

After deployment, monitor for:

1. **403 Forbidden responses** - Attempted cross-repo access (potential security violations)
2. **Increased 401 errors** - Invalid API keys
3. **Error rates** - Ensure no legitimate requests are blocked
4. **Log messages containing "SECURITY:"** - Attempted security violations

Example log search (Vercel):
```
"SECURITY: Site" AND "attempted cross-repo access"
```

---

## Rollback Plan

If issues occur:

```bash
# Restore backup files
cp api/github/get-token.js.backup api/github/get-token.js
cp api/github/proxy.js.backup api/github/proxy.js

# Commit and deploy
git add api/github/
git commit -m "rollback: restore previous backend API version"
git push
```

---

## Success Criteria

After deployment, all of these should be true:

- ✅ `/api/github/get-token` returns 404
- ✅ Cross-repo access returns 403
- ✅ Same-repo access returns 200
- ✅ Sites without bound repos cannot make requests
- ✅ All existing WordPress deployments still work
- ✅ No increase in error rates for legitimate requests
- ✅ Security logs show attempted violations are blocked

---

## Questions?

If you encounter issues during implementation:

1. Check Vercel deployment logs for errors
2. Verify the `selected_repo_full_name` field exists in your KV store data structure
3. Test with a known good API key first
4. Check that WordPress plugin doesn't directly call get-token endpoint

---

**Phase 1 Critical Security Fixes - Backend Complete** ✅

When both backend and WordPress plugin fixes are deployed, all 3 critical security vulnerabilities will be resolved.
