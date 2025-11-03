# Multi-Site Deployment Architecture

## The Problem You've Identified 🎯

**Scenario:** You want to deploy this plugin to **multiple WordPress sites** (staging, production, client sites, etc.)

**Current Design:**
- Each site has its own settings
- Each site stores its own GitHub token
- Each site connects to the same GitHub repo

**Question:** How does this work across many sites?

---

## Architecture Options

### Option 1: Current Design - Per-Site Configuration ✅ **RECOMMENDED**

**How it works:**
```
Site 1 (Production)     →  GitHub Repo (main branch)
Site 2 (Staging)        →  GitHub Repo (staging branch)
Site 3 (Development)    →  GitHub Repo (dev branch)
Site 4 (Client A)       →  GitHub Repo A
Site 5 (Client B)       →  GitHub Repo B
```

**Each site:**
- Has its own GitHub PAT
- Connects to its own repo/branch
- Receives its own webhooks
- Manages its own deployments

**Pros:**
- ✅ **Maximum flexibility** - Each site is independent
- ✅ **Different repos** - Each site can use different themes
- ✅ **Different branches** - Production uses `main`, staging uses `staging`
- ✅ **No single point of failure** - One site down doesn't affect others
- ✅ **Easy to manage** - Each site admin controls their own settings
- ✅ **Secure** - Tokens are isolated per site

**Cons:**
- ❌ Must configure each site individually
- ❌ Multiple GitHub webhooks (one per site)
- ❌ Multiple PATs to manage

**Use Case:**
- Different environments (dev/staging/prod)
- Different clients with different themes
- SaaS platforms where each tenant has their own theme

---

### Option 2: Centralized Configuration (Network Settings)

**How it works:**
```
Central Config API
       ↓
   ┌───┴───┬───────┬──────┐
   ↓       ↓       ↓      ↓
Site 1  Site 2  Site 3  Site 4
```

**Implementation:**
- Store settings centrally (database, API, or network admin)
- Sites pull config from central source
- One webhook triggers all sites

**Pros:**
- ✅ Configure once, deploy everywhere
- ✅ Easier management for many sites
- ✅ Consistent settings across all sites

**Cons:**
- ❌ All sites must use same repo/branch
- ❌ Complex to implement
- ❌ Single point of failure
- ❌ Less flexibility

**When to use:**
- WordPress Multisite network
- Identical theme across all sites
- Central IT managing many sites

---

### Option 3: Hybrid - Network Defaults + Site Overrides

**How it works:**
```
Network Admin Sets:
  - Default repo
  - Default branch
  - Default PAT

Individual Sites Can:
  - Override with own settings
  - Or inherit from network
```

**Implementation:**

```php
// Network-level settings (WordPress Multisite)
if (is_multisite() && is_network_admin()) {
    // Network admin can set defaults
    add_action('network_admin_menu', [$this, 'add_network_menu']);
}

// Site-level settings
class GitHub_Deploy_Settings {
    public function get(string $key, $default = null) {
        // Check site-specific setting first
        $site_value = $this->settings[$key] ?? null;

        if ($site_value !== null && $site_value !== '') {
            return $site_value;
        }

        // Fall back to network setting
        if (is_multisite()) {
            $network_value = get_site_option('github_deploy_network_' . $key);
            if ($network_value !== false) {
                return $network_value;
            }
        }

        return $default;
    }
}
```

**Pros:**
- ✅ Best of both worlds
- ✅ Easy default setup
- ✅ Flexibility when needed

**Cons:**
- ❌ More complex code
- ❌ Only works with WordPress Multisite

---

## Real-World Scenarios

### Scenario 1: Agency with Multiple Clients ✅

**Setup:**
- 50 different client sites
- Each client has their own theme repo
- Each site is independent

**Solution: Option 1 (Current Design)**
- Install plugin on each site
- Each site admin (or you) configures their own repo
- Each repo has its own webhook

**Why it works:**
- Clients can manage their own deployments
- Different themes per client
- Isolated and secure

---

### Scenario 2: Same Theme Across Environments ✅

**Setup:**
- 1 theme, 3 environments (dev, staging, prod)
- Different branches per environment

```
Dev Site       → GitHub Repo (dev branch)
Staging Site   → GitHub Repo (staging branch)
Production     → GitHub Repo (main branch)
```

**Solution: Option 1 (Current Design)**
- Install plugin on each environment
- Configure different branches per site
- Set up 3 webhooks (one per environment)

**GitHub Webhook Config:**
```
Webhook 1: https://dev.site.com/wp-json/github-deploy/v1/webhook (branch: dev)
Webhook 2: https://staging.site.com/wp-json/github-deploy/v1/webhook (branch: staging)
Webhook 3: https://prod.site.com/wp-json/github-deploy/v1/webhook (branch: main)
```

**Why it works:**
- Push to `dev` → only dev site deploys
- Push to `main` → only production deploys
- Perfect for CI/CD workflow

---

### Scenario 3: WordPress Multisite Network

**Setup:**
- 1 WordPress install
- 100 subsites (blog.example.com, store.example.com, etc.)
- All sites use the same theme

**Solution: Option 3 (Network Admin)**

**Current Plugin Support:** ❌ Not implemented
**Effort to Add:** Medium (1-2 days)

**How it would work:**
1. Network admin configures repo once
2. All subsites inherit settings
3. Individual subsites can override if needed

---

### Scenario 4: WP Engine or Kinsta Multi-Environment

**Setup:**
- Hosting with built-in dev/staging/prod
- Same database, different URLs
- Want different branches per environment

**Solution: Option 1 + Environment Detection**

```php
// Auto-detect environment and set branch
public function get_environment_branch(): string {
    // Detect WP Engine environment
    if (defined('WPE_ENV')) {
        return match(WPE_ENV) {
            'production' => 'main',
            'staging' => 'staging',
            'development' => 'dev',
            default => 'main'
        };
    }

    // Detect Kinsta environment
    if (defined('KINSTA_ENV')) {
        return match(KINSTA_ENV) {
            'live' => 'main',
            'staging' => 'staging',
            default => 'dev'
        };
    }

    // Default
    return $this->get('github_branch', 'main');
}
```

**Enhancement Needed:** Auto-environment detection (2-3 hours)

---

## GitHub Webhook Considerations

### Current Design: ✅ **Works Great**

**One webhook per site:**
```
GitHub Repo
    ↓
Webhook 1 → Site 1 (https://site1.com/wp-json/github-deploy/v1/webhook)
Webhook 2 → Site 2 (https://site2.com/wp-json/github-deploy/v1/webhook)
Webhook 3 → Site 3 (https://site3.com/wp-json/github-deploy/v1/webhook)
```

**Each webhook can:**
- Filter by branch
- Have different secrets
- Be enabled/disabled independently

**GitHub Limits:**
- Up to **20 webhooks** per repository (usually enough)
- Webhooks are free and unlimited

---

### Alternative: Smart Webhook Router

If you have **more than 20 sites**, you could build:

```
GitHub Repo
    ↓
Single Webhook → Central Router API
                      ↓
    ┌────────────────┼────────────────┐
    ↓                ↓                ↓
  Site 1           Site 2          Site 3
```

**Router determines which sites to notify based on:**
- Branch pushed
- Site configuration database
- Tags, commit messages, etc.

**Complexity:** High
**When needed:** 20+ sites using same repo

---

## OAuth Impact on Multi-Site

### With Personal Access Token (Current): ✅

**Simple:**
- Each site has its own PAT
- PAT is user-scoped (your GitHub account)
- Works across all sites you manage

**Example:**
```
Your GitHub Account
    ↓
One PAT (or multiple)
    ↓
Used by: Site 1, Site 2, Site 3, ... Site 50
```

**Considerations:**
- ✅ Easy to manage
- ✅ Same PAT can be used on all sites
- ⚠️ If PAT is revoked, all sites break
- ⚠️ PAT has full access to all your repos

---

### With OAuth App: ⚠️ **More Complex**

**Problem:**
- OAuth apps are site-specific
- Each site needs its own OAuth callback URL
- Need to register each site with GitHub

**Example:**
```
GitHub OAuth App 1 → Site 1 (callback: site1.com/wp-admin/...)
GitHub OAuth App 2 → Site 2 (callback: site2.com/wp-admin/...)
GitHub OAuth App 3 → Site 3 (callback: site3.com/wp-admin/...)
```

**Solutions:**

#### Option A: One OAuth App per Site
- ❌ Must register 50 OAuth apps
- ❌ Very tedious

#### Option B: Wildcard Callback URLs (Not Supported)
- ❌ GitHub doesn't support wildcard callbacks

#### Option C: Central OAuth Proxy
- ✅ One OAuth app
- ✅ Central callback handler
- ✅ Redirects to individual sites
- ⚠️ Complex to implement

---

## Recommendation for Multi-Site

### ✅ **Current Design is Perfect** (Option 1)

**Why:**
1. **Flexibility** - Each site can use different repos/branches
2. **Security** - Isolated tokens and settings
3. **Simplicity** - Easy to understand and manage
4. **Scalability** - Works for 1 site or 100 sites
5. **No vendor lock-in** - Not tied to WordPress Multisite

**Best Practices:**

#### For Multiple Environments (dev/staging/prod):
```bash
# Use different branches
Dev Site     → Configure: repo/dev branch
Staging Site → Configure: repo/staging branch
Prod Site    → Configure: repo/main branch

# Use one PAT across all environments
# Set up 3 webhooks (one per environment)
```

#### For Multiple Clients:
```bash
# Each client gets their own setup
Client A → Configure: client-a-theme/main
Client B → Configure: client-b-theme/main
Client C → Configure: client-c-theme/main

# Each client can have their own PAT or share yours
```

#### For WordPress Multisite:
```bash
# Current plugin: Install network-wide
# Each subsite configures independently

# Future enhancement: Add network admin settings
```

---

## Quick Setup Script for Multiple Sites

**If deploying to many sites, create a setup script:**

```bash
#!/bin/bash
# deploy-to-sites.sh

SITES=(
    "https://dev.example.com"
    "https://staging.example.com"
    "https://prod.example.com"
)

BRANCHES=("dev" "staging" "main")

for i in "${!SITES[@]}"; do
    SITE="${SITES[$i]}"
    BRANCH="${BRANCHES[$i]}"

    echo "Configuring $SITE with branch $BRANCH..."

    # Use WP-CLI to configure remotely
    wp --url="$SITE" option update github_deploy_settings --format=json << EOF
{
    "github_repo_owner": "your-username",
    "github_repo_name": "your-theme",
    "github_branch": "$BRANCH",
    "github_workflow_name": "build-theme.yml",
    "target_theme_directory": "your-theme",
    "auto_deploy_enabled": true,
    "create_backups": true
}
EOF

    # Set PAT
    wp --url="$SITE" option update github_deploy_token_encrypted "$(echo -n 'your-pat' | base64)"

    echo "✓ $SITE configured!"
done
```

---

## Future Enhancement: Multi-Site Management UI

**If you need to manage 10+ sites, consider adding (v2.0):**

```php
// Central management dashboard
class GitHub_Deploy_Multi_Site_Manager {

    /**
     * Show all sites using this plugin
     */
    public function get_all_installations(): array {
        // Query WordPress.com, WP Engine API, or custom registry
        // Return list of all sites with this plugin
    }

    /**
     * Deploy to multiple sites at once
     */
    public function deploy_to_sites(array $site_urls, string $commit_hash): array {
        // Trigger deployments on multiple sites
        // Return status of each
    }

    /**
     * Sync settings across sites
     */
    public function sync_settings(array $site_urls, array $settings): void {
        // Push same settings to multiple sites
    }
}
```

**Complexity:** High (3-5 days)
**When needed:** 10+ sites to manage

---

## Conclusion

### Current Design is ✅ **EXCELLENT** for Multi-Site

**Works perfectly for:**
- Multiple client sites (unlimited)
- Multiple environments (dev/staging/prod)
- WordPress Multisite (with individual site configs)
- Different repos per site
- Different branches per site

**No changes needed for v1.0**

### Optional Enhancements (v2.0+):

**If managing 10+ sites:**
- [ ] Add WP-CLI commands for bulk configuration
- [ ] Add environment auto-detection
- [ ] Add central management dashboard

**If using WordPress Multisite:**
- [ ] Add network admin settings panel
- [ ] Add network defaults with site overrides
- [ ] Add bulk deployment to all subsites

**If using OAuth:**
- [ ] Consider staying with PAT for simplicity
- [ ] Or build central OAuth proxy

---

## Answer to Your Question

**Q: Will this method of creating a bind to a repo work for many sites?**

**A: Yes! Absolutely.** ✅

The current design is **perfect** for multi-site deployments:

1. **Each site is independent** - Configure once per site
2. **Same PAT works everywhere** - Use one PAT across all your sites
3. **Flexible branching** - Dev/staging/prod use different branches
4. **GitHub webhooks support it** - Up to 20 webhooks per repo
5. **Scales well** - Works for 1 site or 100 sites

**No architectural changes needed.** The plugin is already designed correctly for multi-site scenarios! 🎉

---

**Pro Tip:** For easier management, create a **setup checklist** or **script** to quickly configure new sites with the same settings.
