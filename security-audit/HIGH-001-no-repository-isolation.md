# HIGH-001: No Repository Access Isolation in Backend Proxy

**Status:** 🟠 HIGH - OPEN
**Priority:** P1 - Fix Within 24 Hours
**Impact:** Cross-tenant data breach, unauthorized repository access

## Location

- **File:** `/api/github/proxy.js`
- **Lines:** 69-74
- **Endpoint:** `POST /api/github/proxy`

## Vulnerability Description

The backend proxy endpoint makes ANY GitHub API request on behalf of a WordPress site without validating that the endpoint matches the site's bound repository. This allows cross-tenant access where one site can access another site's repository data.

## Vulnerable Code

```javascript
export default async function handler(req, res) {
  // Validate API key
  const apiKey = req.headers['x-api-key'];
  const siteId = await getSiteIdByApiKey(apiKey);

  // Get GitHub installation
  const installation = await getGitHubInstallation(siteId);

  // Parse request body
  const { method, endpoint, data } = req.body;

  // Make the GitHub API request using installation token
  const response = await makeInstallationRequest(
    installation.installation_id,
    method,
    endpoint,  // ⚠️ No validation - can be ANY endpoint
    data
  );

  // Return response to WordPress
  res.status(response.status).json({
    success: true,
    status: response.status,
    data: response.data,
    headers: response.headers
  });
}
```

## Attack Scenario

**Setup:**
- Site A bound to `owner/repo-a`
- Site B bound to `owner/repo-b`
- Both sites in same GitHub App installation

**Attack:**
1. Site A is compromised (SQL injection, etc.)
2. Attacker extracts Site A's API key from database
3. Attacker calls proxy endpoint with Site A's API key but requests Site B's repo:
   ```bash
   curl -X POST https://deploy-forge.vercel.app/api/github/proxy \
     -H "X-API-Key: site-a-api-key" \
     -H "Content-Type: application/json" \
     -d '{
       "method": "GET",
       "endpoint": "/repos/owner/repo-b/contents/config/secrets.php"
     }'
   ```
4. Backend makes request with installation token
5. Returns Site B's repository contents to attacker
6. Attacker downloads all of Site B's source code, configuration files, secrets

**Impact:**
- Cross-tenant information disclosure
- Unauthorized access to other users' repositories
- Theft of source code, credentials, API keys
- Violation of tenant isolation

## Remediation

### Add Repository Access Validation

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

    // ========== NEW: REPOSITORY ACCESS VALIDATION ==========

    // Get the bound repository for this site
    const boundRepo = installation.selected_repo_full_name; // e.g., "owner/repo-a"

    if (!boundRepo) {
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

        if (endpointRepo !== boundRepo) {
          console.warn(`Access denied: Site ${siteId} attempted to access ${endpointRepo} but is bound to ${boundRepo}`);
          return res.status(403).json({
            error: 'Access denied',
            message: `You can only access your bound repository: ${boundRepo}`,
            requested: endpointRepo,
            allowed: boundRepo
          });
        }
      }
    }

    // Validate installation-level endpoints
    // These endpoints return data for ALL repos in installation
    const restrictedInstallationEndpoints = [
      '/installation/repositories',
      '/user/repos'
    ];

    for (const restrictedEndpoint of restrictedInstallationEndpoints) {
      if (endpoint.startsWith(restrictedEndpoint)) {
        console.warn(`Potentially unsafe endpoint requested by site ${siteId}: ${endpoint}`);
        // For now, allow but log - in future, filter response
        // TODO: Filter response to only include bound repository
      }
    }

    // ========== END REPOSITORY ACCESS VALIDATION ==========

    // Make the GitHub API request using installation token
    const response = await makeInstallationRequest(
      installation.installation_id,
      method,
      endpoint,
      data
    );

    // Return response to WordPress
    if (response.error) {
      return res.status(response.status).json({
        error: true,
        status: response.status,
        message: response.data.message || 'GitHub API request failed',
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

### Filter Installation Repositories Response

When WordPress requests `/installation/repositories`, filter to only show bound repo:

```javascript
// After making request
if (endpoint === '/installation/repositories' && response.data && response.data.repositories) {
  // Filter to only bound repository
  response.data.repositories = response.data.repositories.filter(repo =>
    repo.full_name === boundRepo
  );
  response.data.total_count = response.data.repositories.length;
}
```

## Testing

### Test 1: Cross-Repo Access Blocked

```bash
# Site A tries to access Site B's repo
curl -X POST https://deploy-forge.vercel.app/api/github/proxy \
  -H "X-API-Key: site-a-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/owner/repo-b/contents/README.md"
  }'

# Expected: 403 Forbidden
{
  "error": "Access denied",
  "message": "You can only access your bound repository: owner/repo-a",
  "requested": "owner/repo-b",
  "allowed": "owner/repo-a"
}
```

### Test 2: Same-Repo Access Allowed

```bash
# Site A accesses own bound repo
curl -X POST https://deploy-forge.vercel.app/api/github/proxy \
  -H "X-API-Key: site-a-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/owner/repo-a/contents/README.md"
  }'

# Expected: 200 OK with file contents
```

### Test 3: No Bound Repo Blocked

```bash
# Site with no bound repo tries to make request
curl -X POST https://deploy-forge.vercel.app/api/github/proxy \
  -H "X-API-Key: unbound-site-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/owner/repo-a/contents/README.md"
  }'

# Expected: 403 Forbidden
{
  "error": "No repository bound",
  "message": "You must bind a repository before making API requests"
}
```

## Related Issues

- CRITICAL-002: Installation Token Exposed (root cause)
- CROSS-TENANT-001: Installation-Level Token Access

## Implementation Checklist

- [ ] Add repository validation to `/api/github/proxy.js`
- [ ] Extract repository from endpoint paths
- [ ] Compare against bound repository
- [ ] Return 403 for cross-repo access
- [ ] Filter `/installation/repositories` responses
- [ ] Add logging for access denial attempts
- [ ] Test cross-repo access blocked
- [ ] Test same-repo access allowed
- [ ] Test unbound site blocked
- [ ] Update API documentation

## Timeline

- **Discovered:** 2025-11-05
- **Target Fix Date:** 2025-11-06
- **Assigned To:** TBD
- **Status:** OPEN
