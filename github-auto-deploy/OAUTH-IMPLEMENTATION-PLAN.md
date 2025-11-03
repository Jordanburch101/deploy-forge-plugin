# GitHub OAuth Implementation Plan

## Current State vs OAuth

### Current Implementation
- ✅ Manual entry of repo owner/name
- ✅ User provides Personal Access Token
- ❌ No visual repo selection
- ❌ Manual token creation/management

### With OAuth
- ✅ One-click GitHub login
- ✅ Visual repo picker with autocomplete
- ✅ Automatic token management
- ✅ Scope validation
- ✅ Better UX for non-technical users

---

## Implementation Complexity

### Difficulty Rating: **Medium** (2-3 days)

**Why Medium?**
- GitHub OAuth is well-documented
- WordPress REST API already in place
- UI changes are straightforward
- Security is critical but manageable

---

## Technical Architecture

### OAuth Flow

```
WordPress Admin              GitHub OAuth           WordPress Backend
     |                            |                        |
     |-- Click "Connect GitHub" ->|                        |
     |                            |                        |
     |<------ Redirect to --------|                        |
     |   github.com/login/oauth   |                        |
     |                            |                        |
     |-- User authorizes app ---->|                        |
     |                            |                        |
     |                            |-- Callback with code ->|
     |                            |                        |
     |                            |<-- Exchange code ------|
     |                            |    for access token    |
     |                            |                        |
     |<-------------------------- Store token -------------|
     |                            |                        |
     |-- Fetch repos using token ----------------------->  |
     |<-------------------------- Return repo list --------|
     |                            |                        |
     |-- Select repo from UI ---->|                        |
```

---

## Implementation Steps

### Phase 1: GitHub OAuth App Setup (30 minutes)

**1. Create GitHub OAuth App**
- Go to: https://github.com/settings/developers
- Click "New OAuth App"
- **Application name**: WordPress GitHub Deploy
- **Homepage URL**: `https://yoursite.com`
- **Authorization callback URL**: `https://yoursite.com/wp-admin/admin.php?page=github-deploy-oauth-callback`
- Copy Client ID and Client Secret

**2. Store Credentials in WordPress**
```php
// In wp-config.php (recommended) or Settings
define('GITHUB_OAUTH_CLIENT_ID', 'your_client_id');
define('GITHUB_OAUTH_CLIENT_SECRET', 'your_client_secret');
```

---

### Phase 2: Backend Implementation (4-6 hours)

**File: `includes/class-github-oauth.php`** (NEW)

```php
<?php
/**
 * GitHub OAuth handler class
 */

class GitHub_OAuth {

    private GitHub_Deploy_Settings $settings;
    private const OAUTH_URL = 'https://github.com/login/oauth/authorize';
    private const TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const API_BASE = 'https://api.github.com';

    public function __construct(GitHub_Deploy_Settings $settings) {
        $this->settings = $settings;
        add_action('admin_init', [$this, 'handle_oauth_callback']);
    }

    /**
     * Get OAuth authorization URL
     */
    public function get_authorization_url(): string {
        $state = wp_create_nonce('github_oauth_state');
        set_transient('github_oauth_state', $state, 10 * MINUTE_IN_SECONDS);

        $params = [
            'client_id' => GITHUB_OAUTH_CLIENT_ID,
            'redirect_uri' => admin_url('admin.php?page=github-deploy-oauth-callback'),
            'scope' => 'repo,workflow',
            'state' => $state,
        ];

        return self::OAUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback(): void {
        if (!isset($_GET['page']) || $_GET['page'] !== 'github-deploy-oauth-callback') {
            return;
        }

        // Verify state
        $state = $_GET['state'] ?? '';
        $stored_state = get_transient('github_oauth_state');

        if (!$state || $state !== $stored_state) {
            wp_die('Invalid OAuth state. Please try again.');
        }

        delete_transient('github_oauth_state');

        // Exchange code for token
        $code = $_GET['code'] ?? '';
        if (!$code) {
            wp_die('No authorization code received.');
        }

        $token = $this->exchange_code_for_token($code);

        if (is_wp_error($token)) {
            wp_die('Failed to get access token: ' . $token->get_error_message());
        }

        // Store token
        $this->settings->set_github_token($token);

        // Redirect back to settings with success message
        wp_redirect(admin_url('admin.php?page=github-deploy-settings&oauth=success'));
        exit;
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchange_code_for_token(string $code): string|WP_Error {
        $response = wp_remote_post(self::TOKEN_URL, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'client_id' => GITHUB_OAUTH_CLIENT_ID,
                'client_secret' => GITHUB_OAUTH_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => admin_url('admin.php?page=github-deploy-oauth-callback'),
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('oauth_error', $body['error_description'] ?? $body['error']);
        }

        return $body['access_token'] ?? new WP_Error('no_token', 'No access token in response');
    }

    /**
     * Get user's repositories
     */
    public function get_user_repositories(): array|WP_Error {
        $token = $this->settings->get_github_token();

        if (!$token) {
            return new WP_Error('no_token', 'Not authenticated');
        }

        // Get authenticated user's repos
        $response = wp_remote_get(self::API_BASE . '/user/repos', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
            ],
            'body' => [
                'per_page' => 100,
                'sort' => 'updated',
                'affiliation' => 'owner,collaborator,organization_member',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $repos = json_decode(wp_remote_retrieve_body($response), true);

        // Format for select dropdown
        return array_map(function($repo) {
            return [
                'id' => $repo['id'],
                'full_name' => $repo['full_name'],
                'name' => $repo['name'],
                'owner' => $repo['owner']['login'],
                'private' => $repo['private'],
                'default_branch' => $repo['default_branch'],
                'updated_at' => $repo['updated_at'],
            ];
        }, $repos);
    }

    /**
     * Get repository workflows
     */
    public function get_repository_workflows(string $owner, string $repo): array|WP_Error {
        $token = $this->settings->get_github_token();

        $response = wp_remote_get(self::API_BASE . "/repos/{$owner}/{$repo}/actions/workflows", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return array_map(function($workflow) {
            return [
                'id' => $workflow['id'],
                'name' => $workflow['name'],
                'path' => $workflow['path'],
                'state' => $workflow['state'],
            ];
        }, $data['workflows'] ?? []);
    }
}
```

**Update `github-auto-deploy.php`:**
```php
// Add to imports
require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-github-oauth.php';

// Add to GitHub_Auto_Deploy::init()
$this->oauth = new GitHub_OAuth($this->settings);
```

**Add AJAX handlers to `class-admin-pages.php`:**
```php
// In __construct()
add_action('wp_ajax_github_deploy_get_repos', [$this, 'ajax_get_repos']);
add_action('wp_ajax_github_deploy_get_workflows', [$this, 'ajax_get_workflows']);

/**
 * AJAX: Get repositories
 */
public function ajax_get_repos(): void {
    check_ajax_referer('github_deploy_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $oauth = github_auto_deploy()->get_oauth();
    $repos = $oauth->get_user_repositories();

    if (is_wp_error($repos)) {
        wp_send_json_error(['message' => $repos->get_error_message()]);
    }

    wp_send_json_success(['repos' => $repos]);
}

/**
 * AJAX: Get workflows for a repository
 */
public function ajax_get_workflows(): void {
    check_ajax_referer('github_deploy_admin', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $owner = sanitize_text_field($_POST['owner'] ?? '');
    $repo = sanitize_text_field($_POST['repo'] ?? '');

    if (!$owner || !$repo) {
        wp_send_json_error(['message' => 'Missing owner or repo']);
    }

    $oauth = github_auto_deploy()->get_oauth();
    $workflows = $oauth->get_repository_workflows($owner, $repo);

    if (is_wp_error($workflows)) {
        wp_send_json_error(['message' => $workflows->get_error_message()]);
    }

    wp_send_json_success(['workflows' => $workflows]);
}
```

---

### Phase 3: Frontend UI Updates (3-4 hours)

**Update `templates/settings-page.php`:**

```php
<!-- Add before existing Repository Settings -->
<h2><?php esc_html_e('GitHub Connection', 'github-auto-deploy'); ?></h2>

<table class="form-table">
    <?php if (!$this->settings->get_github_token()): ?>
        <tr>
            <th scope="row"><?php esc_html_e('Connect to GitHub', 'github-auto-deploy'); ?></th>
            <td>
                <a href="<?php echo esc_url(github_auto_deploy()->get_oauth()->get_authorization_url()); ?>"
                   class="button button-primary button-hero">
                    <span class="dashicons dashicons-admin-links"></span>
                    <?php esc_html_e('Connect with GitHub', 'github-auto-deploy'); ?>
                </a>
                <p class="description">
                    <?php esc_html_e('Connect your GitHub account to easily select repositories and workflows.', 'github-auto-deploy'); ?>
                </p>
            </td>
        </tr>
    <?php else: ?>
        <tr>
            <th scope="row"><?php esc_html_e('GitHub Connected', 'github-auto-deploy'); ?></th>
            <td>
                <p class="status-connected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Connected to GitHub', 'github-auto-deploy'); ?>
                </p>
                <button type="button" id="disconnect-github" class="button">
                    <?php esc_html_e('Disconnect', 'github-auto-deploy'); ?>
                </button>
            </td>
        </tr>

        <tr>
            <th scope="row">
                <label for="repo-selector"><?php esc_html_e('Select Repository', 'github-auto-deploy'); ?></label>
            </th>
            <td>
                <select id="repo-selector" class="regular-text">
                    <option value=""><?php esc_html_e('Loading repositories...', 'github-auto-deploy'); ?></option>
                </select>
                <button type="button" id="refresh-repos" class="button">
                    <span class="dashicons dashicons-update"></span>
                </button>
                <p class="description">
                    <?php esc_html_e('Select a repository from your GitHub account', 'github-auto-deploy'); ?>
                </p>
            </td>
        </tr>

        <tr id="workflow-selector-row" style="display: none;">
            <th scope="row">
                <label for="workflow-selector"><?php esc_html_e('Select Workflow', 'github-auto-deploy'); ?></label>
            </th>
            <td>
                <select id="workflow-selector" class="regular-text">
                    <option value=""><?php esc_html_e('Select a workflow...', 'github-auto-deploy'); ?></option>
                </select>
                <p class="description">
                    <?php esc_html_e('Choose the GitHub Actions workflow to use for deployments', 'github-auto-deploy'); ?>
                </p>
            </td>
        </tr>
    <?php endif; ?>
</table>

<!-- Then show manual entry as fallback -->
<details>
    <summary><?php esc_html_e('Or enter manually', 'github-auto-deploy'); ?></summary>

    <!-- Existing manual entry fields here -->

</details>
```

**Update `admin/js/admin-scripts.js`:**

```javascript
const GitHubOAuth = {
    init: function() {
        this.loadRepositories();
        this.bindEvents();
    },

    bindEvents: function() {
        $('#repo-selector').on('change', this.onRepoSelect.bind(this));
        $('#workflow-selector').on('change', this.onWorkflowSelect.bind(this));
        $('#refresh-repos').on('click', this.loadRepositories.bind(this));
        $('#disconnect-github').on('click', this.disconnect.bind(this));
    },

    loadRepositories: function() {
        const $select = $('#repo-selector');
        $select.html('<option value="">Loading...</option>').prop('disabled', true);

        $.ajax({
            url: githubDeployAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'github_deploy_get_repos',
                nonce: githubDeployAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $select.empty();
                    $select.append('<option value="">Select a repository...</option>');

                    response.data.repos.forEach(function(repo) {
                        const badge = repo.private ? '🔒' : '📖';
                        $select.append(
                            $('<option></option>')
                                .val(JSON.stringify({
                                    owner: repo.owner,
                                    name: repo.name,
                                    branch: repo.default_branch
                                }))
                                .text(`${badge} ${repo.full_name}`)
                        );
                    });

                    $select.prop('disabled', false);
                } else {
                    $select.html('<option value="">Error loading repos</option>');
                }
            },
            error: function() {
                $select.html('<option value="">Error loading repos</option>');
            }
        });
    },

    onRepoSelect: function(e) {
        const value = $(e.target).val();
        if (!value) return;

        const repo = JSON.parse(value);

        // Auto-fill manual fields
        $('#github_repo_owner').val(repo.owner);
        $('#github_repo_name').val(repo.name);
        $('#github_branch').val(repo.branch);

        // Load workflows
        this.loadWorkflows(repo.owner, repo.name);
    },

    loadWorkflows: function(owner, repo) {
        const $row = $('#workflow-selector-row');
        const $select = $('#workflow-selector');

        $row.show();
        $select.html('<option value="">Loading workflows...</option>').prop('disabled', true);

        $.ajax({
            url: githubDeployAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'github_deploy_get_workflows',
                nonce: githubDeployAdmin.nonce,
                owner: owner,
                repo: repo
            },
            success: function(response) {
                if (response.success) {
                    $select.empty();
                    $select.append('<option value="">Select a workflow...</option>');

                    response.data.workflows.forEach(function(workflow) {
                        const filename = workflow.path.split('/').pop();
                        $select.append(
                            $('<option></option>')
                                .val(filename)
                                .text(`${workflow.name} (${filename})`)
                        );
                    });

                    $select.prop('disabled', false);
                } else {
                    $select.html('<option value="">No workflows found</option>');
                }
            }
        });
    },

    onWorkflowSelect: function(e) {
        const value = $(e.target).val();
        if (value) {
            $('#github_workflow_name').val(value);
        }
    },

    disconnect: function() {
        if (!confirm('Disconnect from GitHub? You will need to reconnect to use repository selection.')) {
            return;
        }

        // Clear token
        $.ajax({
            url: githubDeployAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'github_deploy_disconnect',
                nonce: githubDeployAdmin.nonce
            },
            success: function() {
                location.reload();
            }
        });
    }
};

// Initialize on settings page
if ($('.github-deploy-settings').length > 0) {
    GitHubOAuth.init();
}
```

---

### Phase 4: Enhanced UX Features (2-3 hours)

**Add Search/Filter to Repo Selector:**

```javascript
// Use Select2 for better UX
$('#repo-selector').select2({
    placeholder: 'Search repositories...',
    allowClear: true,
    width: '100%'
});
```

**Add Workflow Preview:**

```php
// Show workflow file content preview
public function ajax_preview_workflow(): void {
    // Fetch and display workflow YAML content
}
```

**Add Repository Insights:**

```html
<!-- Show last commit, branch info, etc. -->
<div class="repo-insights">
    <span>Last updated: 2 hours ago</span>
    <span>Default branch: main</span>
    <span>Workflows: 3</span>
</div>
```

---

## Complexity Breakdown

### Easy Parts (1 day)
- ✅ OAuth flow setup (standard GitHub OAuth)
- ✅ Token exchange (wp_remote_post)
- ✅ Basic UI updates
- ✅ AJAX handlers

### Medium Parts (1 day)
- ⚠️ Repo listing with pagination
- ⚠️ Workflow detection
- ⚠️ UI/UX polish
- ⚠️ Error handling

### Challenging Parts (0.5 days)
- 🔴 OAuth state management
- 🔴 Token refresh (GitHub tokens don't expire, but good to handle)
- 🔴 Multi-account support (optional)
- 🔴 Permission scope validation

---

## Security Considerations

### Important
- ✅ Store Client Secret securely (wp-config.php)
- ✅ Validate OAuth state parameter
- ✅ Use nonces for AJAX requests
- ✅ Encrypt stored token (already implemented)
- ✅ HTTPS required for OAuth callback
- ✅ Sanitize all repo/workflow data

### Optional Enhancements
- Rate limiting OAuth attempts
- IP-based OAuth restrictions
- Audit log for OAuth connections

---

## User Experience Comparison

### Current Flow (Manual)
1. Go to GitHub settings
2. Create Personal Access Token
3. Copy token
4. Go to WordPress
5. Paste token
6. Manually type repo owner
7. Manually type repo name
8. Manually type branch name
9. Manually type workflow name

**Steps: 9** | **Time: ~10 minutes** | **Error-prone: High**

### With OAuth
1. Click "Connect with GitHub"
2. Authorize app
3. Select repo from dropdown
4. Select workflow from dropdown

**Steps: 4** | **Time: ~2 minutes** | **Error-prone: Low**

---

## Pros & Cons

### Pros ✅
- **Better UX**: Visual selection vs. manual typing
- **Fewer errors**: No typos in repo names
- **Faster setup**: 2 minutes vs. 10 minutes
- **Auto-discovery**: See all available repos/workflows
- **Professional**: Modern OAuth is expected
- **Flexibility**: Can still manually enter if needed

### Cons ❌
- **Complexity**: More code to maintain
- **OAuth App**: Requires GitHub App registration
- **Callback URL**: Must be HTTPS and publicly accessible
- **Token Management**: Additional logic needed
- **Testing**: More scenarios to test

---

## Migration Strategy

### For Existing Users
- Keep manual entry option
- Add OAuth as "recommended" method
- Detect if token is OAuth vs. PAT
- Allow switching between methods

### For New Users
- Show OAuth button prominently
- Manual entry as "Advanced" option
- Guide users through OAuth flow

---

## Testing Requirements

### OAuth Flow
- [ ] Authorization works
- [ ] Callback handling
- [ ] State validation
- [ ] Token exchange
- [ ] Error scenarios

### UI/UX
- [ ] Repo selector loads
- [ ] Search/filter works
- [ ] Workflow selector populates
- [ ] Manual entry still works
- [ ] Responsive design

### Security
- [ ] State parameter validation
- [ ] CSRF protection
- [ ] Token encryption
- [ ] Scope validation

---

## Alternative: Simpler Approach

If full OAuth is too complex, consider:

### Option 1: Enhanced Manual Entry with Validation
- Keep PAT entry
- Add "Validate & Fetch Repos" button
- Show dropdown of repos after validation
- **Time: 4-6 hours** | **Complexity: Low**

### Option 2: GitHub App (vs OAuth App)
- More powerful permissions model
- Installation-based (not user-based)
- Better for multi-user WordPress sites
- **Time: 3-4 days** | **Complexity: High**

---

## Recommendation

**Implement OAuth in v2.0** as a feature enhancement

### Why wait?
1. Current PAT method works fine
2. OAuth adds complexity to v1.0
3. Need real user feedback first
4. Better to ship v1.0 faster
5. OAuth can be added without breaking changes

### Include in v1.0:
- ✅ Keep current PAT method
- ✅ Add "Validate Token" button
- ✅ Add "Fetch Repositories" button (once token is valid)
- ✅ Show repo dropdown (using existing token)

This gives 80% of the UX benefit with 20% of the complexity!

---

## Quick Win: Repo Selector (No OAuth)

**Implementation time: 2-3 hours**

Use existing PAT to fetch repos:

```javascript
// After user enters PAT and clicks "Validate"
function fetchRepos() {
    // Use entered PAT to call GitHub API
    // Populate dropdown
    // Select repo → auto-fill fields
}
```

This approach:
- ✅ No OAuth complexity
- ✅ Better UX than manual typing
- ✅ Can add OAuth later without changes
- ✅ Works with existing architecture

---

## Conclusion

**Difficulty: Medium** (2-3 days for full OAuth)

**Recommended Approach:**
1. **v1.0**: Ship with current PAT method + repo dropdown helper
2. **v1.5**: Add full OAuth flow
3. **v2.0**: Consider GitHub App for advanced features

**Immediate Quick Win:**
Add repo/workflow dropdown that uses the PAT after it's entered (2-3 hours)

This gives users a better experience without delaying v1.0 launch!
