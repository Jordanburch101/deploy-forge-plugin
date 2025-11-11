# Backend Brief: Repository Filtering Issue in Wizard Setup

**Date**: 2025-11-11
**Priority**: High
**Component**: `/installation/repositories` endpoint
**Reporter**: Jordan Burch (WordPress Plugin Frontend)

---

## Problem Summary

The backend is filtering the `/installation/repositories` endpoint response to only return the bound repository, even when the WordPress site is in the wizard setup flow or after disconnecting. This prevents users from seeing all available repositories during initial configuration or when changing repositories.

---

## Current Behavior

When a WordPress site requests `/installation/repositories`:

1. Backend receives request from site with ID `6e69da9f-45c0-45a6-8c48-b3398fc39419`
2. Backend logs: `[warning] SECURITY: Site 6e69da9f-45c0-45a6-8c48-b3398fc39419 requested installation-level endpoint: /installation/repositories`
3. Backend filters response: `Filtered repositories response for site 6e69da9f-45c0-45a6-8c48-b3398fc39419: 4 -> 1`
4. WordPress plugin receives only 1 repository (the bound one) instead of all 4 available

**Example from logs**:
```
[warning] SECURITY: Site 6e69da9f-45c0-45a6-8c48-b3398fc39419 requested installation-level endpoint: /installation/repositories
[info] Filtered repositories response for site 6e69da9f-45c0-45a6-8c48-b3398fc39419: 4 -> 1
```

---

## Expected Behavior

The `/installation/repositories` endpoint should return ALL repositories that the GitHub App installation has access to when:

1. **During wizard setup** - Site has no bound repository yet
2. **After disconnect** - Site has been disconnected from GitHub and repo binding cleared
3. **Unbound state** - Any state where `boundRepoId` or equivalent is `null`/empty

The endpoint should ONLY filter to the bound repository when:

1. Site has completed wizard and successfully bound a repository
2. Site is making routine API calls (non-wizard flows)

---

## Technical Details

### Frontend State Management

The WordPress plugin tracks repository binding state via:

- **Database**: `github_repo_owner` and `github_repo_name` settings
- **Binding Check**: `is_repo_bound()` method returns `true` only when both owner and name are set
- **Wizard Flow**: On step 3 (Repository Selection), if `is_repo_bound() === false`, user should see ALL repositories
- **Disconnect Action**: `disconnect_github()` method clears all GitHub settings including repo binding

### Backend Filtering Logic (Suspected)

Based on logs, backend appears to be:

1. Checking if site has a `boundRepoId` in database
2. If `boundRepoId` exists, filtering `/installation/repositories` to only return that repo
3. Not considering whether site is in wizard/unbound state

---

## Required Backend Changes

### Option 1: Check Unbound State in Endpoint (Recommended)

Modify `/installation/repositories` endpoint to:

```pseudo
GET /installation/repositories

1. Identify requesting WordPress site
2. Check if site has boundRepoId
3. IF boundRepoId is NULL or empty:
   - Return ALL repositories from GitHub App installation
   - Skip filtering
4. ELSE:
   - Apply existing security filtering
   - Return only bound repository
```

### Option 2: Add Query Parameter

Add optional parameter to bypass filtering during wizard:

```pseudo
GET /installation/repositories?wizard=true

1. If wizard=true AND site is unbound:
   - Return all repositories
2. Else:
   - Apply standard filtering
```

**Recommendation**: Option 1 is preferred for security (no manual parameter manipulation)

---

## Security Considerations

### Why Current Filtering Exists

The security filtering prevents sites from:
- Accessing repositories they haven't bound to
- Seeing repositories from other users in the same installation
- Enumerating all repositories in an organization

### Why Changes Are Safe

The proposed changes maintain security because:

1. **Unbound state is legitimate** - Sites need to see all repos during initial setup
2. **Filtering still applies post-binding** - Once bound, sites only see their repository
3. **Installation-level access is already authorized** - GitHub App was authorized by admin
4. **WordPress plugin enforces binding** - Frontend prevents API calls with repo data until binding complete

### Attack Vector Analysis

**Could a malicious site claim to be "unbound" to access all repos?**

- No, because after initial binding, legitimate WordPress sites never clear `boundRepoId` unless user explicitly disconnects
- Backend can verify binding status independently (site should have binding timestamp)
- If site is truly bound but claims unbound, backend can reject as suspicious

**Mitigation**: Add binding timestamp check - if site was bound less than 5 minutes ago, reject subsequent "unbound" requests as potential attack.

---

## Testing Scenarios

### Scenario 1: Fresh Wizard Setup
1. New WordPress site completes GitHub App OAuth
2. Site requests `/installation/repositories`
3. Expected: ALL 4 repositories returned
4. Current: Only 1 repository returned ❌

### Scenario 2: After Disconnect
1. Site was bound to `MetaDigitalNZ/shpg`
2. User clicks "Restart Wizard" → full disconnect
3. Site reconnects with different GitHub account
4. Site requests `/installation/repositories`
5. Expected: ALL repositories from new account ✅
6. Current: Still returns old cached repository ❌

### Scenario 3: Post-Binding (Should Still Filter)
1. Site completes wizard and binds to `MetaDigitalNZ/shpg`
2. User adds new repository to GitHub App installation
3. Site requests `/installation/repositories`
4. Expected: Only `MetaDigitalNZ/shpg` returned (filtered) ✅
5. Current: Only `MetaDigitalNZ/shpg` returned ✅

---

## Frontend Readiness

The WordPress plugin is already prepared for this change:

- ✅ Wizard checks `is_repo_bound()` before showing dropdown
- ✅ Disconnect clears all repo settings and transients
- ✅ Binding is explicit (button click, not automatic)
- ✅ State persists across page refreshes
- ✅ Transient cache cleared on disconnect

**No frontend changes required** - this is purely a backend endpoint modification.

---

## Impact Assessment

### Users Affected
- **All new installations** - Cannot complete wizard setup if they have multiple repos
- **Users switching repositories** - Cannot see available repos after disconnect
- **Users adding repos to GitHub App** - Only see newly added repo, not all selected repos

### Severity
**High** - Blocks wizard completion for multi-repo installations

### Workaround
None currently available. Users must configure GitHub App to only have access to ONE repository during wizard setup.

---

## Questions for Backend Team

1. Where is the filtering logic located in backend codebase?
2. Is there a `boundRepoId` or equivalent field in backend database?
3. Is there a binding timestamp we can use for security checks?
4. Should we add a new `isWizardActive` flag in site metadata?

---

## Contact

For questions or clarification, contact Jordan Burch (WordPress Plugin Developer)

**Related Frontend Code**:
- `/deploy-forge/admin/class-setup-wizard.php` - Wizard controller
- `/deploy-forge/admin/js/setup-wizard.js:350-394` - Repository loading logic
- `/deploy-forge/includes/class-settings.php:280-314` - Disconnect implementation
