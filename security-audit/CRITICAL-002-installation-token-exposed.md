# CRITICAL-002: Installation Token Exposed to WordPress Sites

**Status:** 🔴 CRITICAL - OPEN
**Priority:** P0 - Fix Immediately
**Impact:** Cross-tenant data breach, full repository access, potential theft of secrets and source code

## Location

- **Backend File:** `/api/github/get-token.js`
- **Lines:** 48-58
- **Endpoint:** `POST /api/github/get-token`
- **WordPress Usage:** `github-auto-deploy/includes/class-github-api.php` lines 231-269

## Vulnerability Description

The backend provides a direct endpoint that returns GitHub installation access tokens to WordPress sites. While this requires API key authentication, it creates a massive security hole:

**The Problem:** GitHub App installation tokens provide access to ALL repositories in the installation, not just the repository the WordPress site is bound to.

If a WordPress site is compromised, an attacker can:
1. Extract the API key from the WordPress database
2. Call `/api/github/get-token` to obtain an installation token
3. Use that token to access ANY repository the GitHub App can access
4. Read source code, download artifacts, access secrets, modify workflows

## Vulnerable Code

### Backend (get-token.js)

```javascript
export default async function handler(req, res) {
  // Validate API key
  const apiKey = req.headers['x-api-key'];
  const siteId = await getSiteIdByApiKey(apiKey);

  // Get GitHub installation
  const installation = await getGitHubInstallation(siteId);

  // Get installation token - THIS IS THE PROBLEM
  const octokit = await getInstallationOctokit(installation.installation_id);
  const { token } = await octokit.auth({ type: 'installation' });

  // Return full installation token to WordPress
  res.status(200).json({
    success: true,
    token: token,  // ⚠️ Grants access to ALL repos in installation
    expires_in: 3600
  });
}
```

### WordPress Usage (class-github-api.php)

```php
// WordPress code that uses the installation token
$token_response = wp_remote_post($token_url, [
    'headers' => [
        'Content-Type' => 'application/json',
        'X-API-Key' => $api_key,
    ],
]);

$token_body = json_decode($token_response_body, true);
$installation_token = $token_body['token'];

// Now WordPress has full installation token
// Can access ANY repository, not just bound repo
```

## Attack Scenario

### Scenario: One Compromised Site = All Repos Compromised

**Setup:**
- GitHub App installed on organization with 10 private repositories
- WordPress Site A bound to `repo-1`
- WordPress Site B bound to `repo-2`
- WordPress Site C bound to `repo-3`

**Attack Chain:**
1. Attacker finds SQL injection in WordPress Site A
2. Dumps `wp_options` table
3. Extracts `github_deploy_api_key` option value
4. Makes direct HTTP request to backend:
   ```bash
   curl -X POST https://deploy-forge.vercel.app/api/github/get-token \
     -H "X-API-Key: stolen-api-key-from-site-a"
   ```
5. Receives installation token with access to ALL 10 repos
6. Uses token to:
   - Clone all 10 private repositories
   - Download build artifacts from all repos
   - Read GitHub Actions secrets
   - Modify workflow files
   - Access repos belonging to Sites B and C

**Impact:**
- One compromised WordPress site = All repositories compromised
- Cross-tenant data breach
- Theft of intellectual property, credentials, API keys
- Potential supply chain attack via workflow modification

## Current Mitigation (INSUFFICIENT)

The plugin has "repository binding" which prevents WordPress sites from *configuring* multiple repositories in the UI:

```php
// class-settings.php
public function is_repo_bound(): bool {
    $github_data = $this->get_github_data();
    return !empty($github_data['repo_bound']) && $github_data['repo_bound'] === true;
}
```

**Why This Doesn't Help:**
- Repository binding is UI-only, not enforced at API level
- WordPress can still call backend with any endpoint
- Backend doesn't validate that endpoints match bound repository
- Attacker doesn't use WordPress UI, they make direct HTTP calls

## Remediation

### Solution 1: Remove get-token Endpoint Entirely (RECOMMENDED)

**Action:** Delete `/api/github/get-token.js` completely

**Rationale:**
- WordPress should NEVER have direct access to installation tokens
- All GitHub API calls should go through the proxy
- Proxy can enforce repository-level access control

**Changes Required:**

1. **Delete backend endpoint:**
   ```bash
   rm /api/github/get-token.js
   ```

2. **Remove WordPress usage:**
   In `class-github-api.php`, delete lines 231-269 (entire token fetching logic)

3. **Update artifact download to use proxy:**
   ```php
   // Instead of getting token and calling GitHub directly,
   // add new proxy endpoint for artifact downloads
   public function download_artifact(int $artifact_id, string $destination): bool|WP_Error {
       // Call backend proxy which handles authentication
       $result = $this->request('GET', "/repos/{$repo}/actions/artifacts/{$artifact_id}/zip");

       // Backend handles redirect and streams file
       // WordPress just saves the response
   }
   ```

4. **Add artifact download handler to backend proxy:**
   ```javascript
   // In proxy.js, add special handling for artifact downloads
   if (endpoint.includes('/artifacts/') && endpoint.endsWith('/zip')) {
       // Get redirect URL from GitHub
       // Stream file to WordPress
       // Don't expose installation token
   }
   ```

### Solution 2: Add Repository Validation to Proxy (REQUIRED EVEN IF KEEPING TOKEN ENDPOINT)

Even if keeping the token endpoint for some reason, the proxy MUST validate repository access:

```javascript
// In proxy.js
export default async function handler(req, res) {
  const siteId = await getSiteIdByApiKey(apiKey);
  const installation = await getGitHubInstallation(siteId);

  // Get bound repository
  const boundRepo = installation.selected_repo_full_name; // e.g., "owner/repo-1"

  const { endpoint } = req.body;

  // Validate repository-scoped endpoints
  if (endpoint.startsWith('/repos/')) {
    const endpointRepo = endpoint.split('/').slice(2, 4).join('/');

    if (endpointRepo !== boundRepo) {
      return res.status(403).json({
        error: 'Access denied',
        message: `You can only access your bound repository: ${boundRepo}`,
        requested: endpointRepo,
        allowed: boundRepo
      });
    }
  }

  // Make request...
}
```

### Solution 3: Use Fine-Grained PATs Instead of App Installation (LONG-TERM)

**Alternative Architecture:**
- Each WordPress site gets its own GitHub Fine-Grained Personal Access Token
- PAT scoped to single repository with minimal permissions
- True tenant isolation at GitHub level

**Tradeoffs:**
- More complex to manage (PAT rotation, expiration)
- Requires users to create their own PATs
- Better security isolation
- No cross-tenant risk

## Testing After Fix

### Test 1: Verify Token Endpoint Returns 404

```bash
# Should fail
curl -X POST https://deploy-forge.vercel.app/api/github/get-token \
  -H "X-API-Key: valid-api-key"

# Expected: 404 Not Found
```

### Test 2: Verify Proxy Blocks Cross-Repo Access

```bash
# Site bound to owner/repo-1 tries to access owner/repo-2
curl -X POST https://deploy-forge.vercel.app/api/github/proxy \
  -H "X-API-Key: site-a-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/owner/repo-2/contents/README.md"
  }'

# Expected: 403 Forbidden
{
  "error": "Access denied",
  "message": "You can only access your bound repository: owner/repo-1"
}
```

### Test 3: Verify Proxy Allows Bound Repo Access

```bash
# Site bound to owner/repo-1 accesses own repo
curl -X POST https://deploy-forge.vercel.app/api/github/proxy \
  -H "X-API-Key: site-a-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "method": "GET",
    "endpoint": "/repos/owner/repo-1/contents/README.md"
  }'

# Expected: 200 OK with file contents
```

## References

- [GitHub App Installation Tokens](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/about-authentication-with-a-github-app)
- [GitHub Fine-Grained PATs](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token#creating-a-fine-grained-personal-access-token)
- [OWASP Broken Access Control](https://owasp.org/Top10/A01_2021-Broken_Access_Control/)

## Implementation Checklist

- [ ] Delete `/api/github/get-token.js`
- [ ] Remove token fetching code from `class-github-api.php`
- [ ] Add repository validation to `proxy.js`
- [ ] Update artifact download to use proxy
- [ ] Add artifact streaming handler to backend
- [ ] Test cross-repo access blocked
- [ ] Test same-repo access allowed
- [ ] Update documentation

## Timeline

- **Discovered:** 2025-11-05
- **Target Fix Date:** 2025-11-06 (24 hours)
- **Assigned To:** TBD
- **Status:** OPEN
