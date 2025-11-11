<?php

/**
 * Settings management class
 * Handles plugin options and encrypted token storage
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Settings
{

    private const OPTION_NAME = 'deploy_forge_settings';
    private const API_KEY_OPTION = 'deploy_forge_api_key';
    private const GITHUB_DATA_OPTION = 'deploy_forge_github_data';
    private array $settings;

    public function __construct()
    {
        $this->load_settings();
    }

    /**
     * Load settings from database
     */
    private function load_settings(): void
    {
        $defaults = [
            'github_repo_owner' => '',
            'github_repo_name' => '',
            'github_branch' => 'main',
            'github_workflow_name' => 'deploy-theme.yml',
            'deployment_method' => 'github_actions', // 'github_actions' or 'direct_clone'
            'auto_deploy_enabled' => false,
            'require_manual_approval' => false,
            'create_backups' => true,
            'notification_email' => get_option('admin_email'),
            'webhook_secret' => '',
            'debug_mode' => false,
        ];

        $this->settings = wp_parse_args(get_option(self::OPTION_NAME, []), $defaults);
    }

    /**
     * Get a setting value
     */
    public function get(string $key, $default = null)
    {
        // CRITICAL FIX: For webhook_secret, always read fresh from database
        // This ensures we get the latest value even if it was updated after this instance was created
        if ($key === 'webhook_secret') {
            $fresh_settings = get_option(self::OPTION_NAME, []);
            return $fresh_settings['webhook_secret'] ?? $default;
        }

        return $this->settings[$key] ?? $default;
    }

    /**
     * Get all settings
     */
    public function get_all(): array
    {
        return $this->settings;
    }

    /**
     * Save settings to database
     */
    public function save(array $settings): bool
    {
        // Sanitize settings
        $sanitized = [
            'github_repo_owner' => sanitize_text_field($settings['github_repo_owner'] ?? ''),
            'github_repo_name' => sanitize_text_field($settings['github_repo_name'] ?? ''),
            'github_branch' => sanitize_text_field($settings['github_branch'] ?? 'main'),
            'github_workflow_name' => sanitize_text_field($settings['github_workflow_name'] ?? 'deploy-theme.yml'),
            'deployment_method' => in_array($settings['deployment_method'] ?? '', ['github_actions', 'direct_clone'])
                ? $settings['deployment_method']
                : 'github_actions',
            'auto_deploy_enabled' => (bool) ($settings['auto_deploy_enabled'] ?? false),
            'require_manual_approval' => (bool) ($settings['require_manual_approval'] ?? false),
            'create_backups' => (bool) ($settings['create_backups'] ?? true),
            'notification_email' => sanitize_email($settings['notification_email'] ?? get_option('admin_email')),
            // Webhook secret is a hex string - only allow a-f0-9
            'webhook_secret' => preg_replace('/[^a-f0-9]/i', '', $settings['webhook_secret'] ?? ''),
            'debug_mode' => (bool) ($settings['debug_mode'] ?? false),
        ];

        $result = update_option(self::OPTION_NAME, $sanitized);

        if ($result) {
            $this->settings = $sanitized;
        }

        return $result;
    }

    /**
     * Update a single setting
     */
    public function update(string $key, $value): bool
    {
        $this->settings[$key] = $value;
        $result = update_option(self::OPTION_NAME, $this->settings);

        // CRITICAL: Reload settings from database to ensure in-memory cache is fresh
        // This is necessary because other instances of this class may have stale cached data
        if ($result) {
            $this->load_settings();
        }

        return $result;
    }

    /**
     * Get GitHub API key (for backend communication)
     */
    public function get_api_key(): string
    {
        return get_option(self::API_KEY_OPTION, '');
    }

    /**
     * Set GitHub API key
     */
    public function set_api_key(string $api_key): bool
    {
        if (empty($api_key)) {
            return delete_option(self::API_KEY_OPTION);
        }

        return update_option(self::API_KEY_OPTION, $api_key);
    }

    /**
     * Get GitHub connection data
     */
    public function get_github_data(): array
    {
        $defaults = [
            'installation_id' => 0,
            'account_login' => '',
            'account_type' => '',
            'account_avatar' => '',
            'selected_repo_id' => 0,
            'selected_repo_name' => '',
            'selected_repo_full_name' => '',
            'selected_repo_default_branch' => '',
            'connected_at' => '',
        ];

        return wp_parse_args(get_option(self::GITHUB_DATA_OPTION, []), $defaults);
    }

    /**
     * Set GitHub connection data
     */
    public function set_github_data(array $data): bool
    {
        return update_option(self::GITHUB_DATA_OPTION, $data);
    }

    /**
     * Check if GitHub is connected
     */
    public function is_github_connected(): bool
    {
        return !empty($this->get_api_key()) && !empty($this->get_github_data()['installation_id']);
    }

    /**
     * Check if repository is bound (locked to a specific repo)
     */
    public function is_repo_bound(): bool
    {
        $github_data = $this->get_github_data();
        return !empty($github_data['repo_bound']) && $github_data['repo_bound'] === true;
    }

    /**
     * Bind repository (lock it so it cannot be changed without reconnecting GitHub)
     *
     * @param string $owner Repository owner
     * @param string $name Repository name
     * @param string $default_branch Repository default branch
     * @return bool Success
     */
    public function bind_repository(string $owner, string $name, string $default_branch): bool
    {
        // Get current GitHub data
        $github_data = $this->get_github_data();

        // Check if already bound to prevent re-binding
        if ($this->is_repo_bound()) {
            return false;
        }

        // Update GitHub data with bound repo info
        $github_data['repo_bound'] = true;
        $github_data['selected_repo_name'] = $name;
        $github_data['selected_repo_full_name'] = $owner . '/' . $name;
        $github_data['selected_repo_default_branch'] = $default_branch;
        $github_data['account_login'] = $owner;
        $github_data['bound_at'] = current_time('mysql');

        // CRITICAL: Reload settings from database FIRST to ensure we have latest values
        // This prevents overwriting values (like webhook_secret) that may have been set recently
        $this->load_settings();

        // Update plugin settings with repo info
        $current_settings = $this->get_all();
        $current_settings['github_repo_owner'] = $owner;
        $current_settings['github_repo_name'] = $name;
        $current_settings['github_branch'] = $default_branch;

        // Save both GitHub data and settings locally
        $saved = $this->set_github_data($github_data) && $this->save($current_settings);

        if (!$saved) {
            return false;
        }

        // Notify backend about repository binding
        $this->notify_backend_repo_binding($owner . '/' . $name, $name, $default_branch);

        return true;
    }

    /**
     * Notify backend about repository binding
     *
     * @param string $repo_full_name Full repository name (owner/repo)
     * @param string $repo_name Repository name
     * @param string $default_branch Default branch
     */
    private function notify_backend_repo_binding(string $repo_full_name, string $repo_name, string $default_branch): void
    {
        $api_key = $this->get_api_key();

        if (empty($api_key)) {
            return;
        }

        $backend_url = defined('DEPLOY_FORGE_BACKEND_URL')
            ? constant('DEPLOY_FORGE_BACKEND_URL')
            : 'https://deploy-forge.vercel.app';

        $response = wp_remote_post($backend_url . '/api/repo/bind', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ],
            'body' => wp_json_encode([
                'repo_full_name' => $repo_full_name,
                'repo_name' => $repo_name,
                'default_branch' => $default_branch,
            ]),
            'timeout' => 15,
        ]);

        // Log the response (non-blocking, just for debugging)
        if (is_wp_error($response)) {
            error_log('Deploy Forge: Failed to notify backend about repo binding - ' . $response->get_error_message());
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                $body = wp_remote_retrieve_body($response);
                error_log('Deploy Forge: Backend repo binding failed - Status: ' . $status_code . ', Body: ' . $body);
            }
        }
    }

    /**
     * Disconnect from GitHub (remove API key and installation data)
     */
    public function disconnect_github(): bool
    {
        delete_option(self::API_KEY_OPTION);
        delete_option(self::GITHUB_DATA_OPTION);

        // Clear repo settings too
        $current_settings = $this->get_all();
        $current_settings['github_repo_owner'] = '';
        $current_settings['github_repo_name'] = '';
        $current_settings['github_branch'] = 'main';
        $this->save($current_settings);

        return true;
    }

    /**
     * Reset all plugin settings (for complete reset)
     */
    public function reset_all_settings(): bool
    {
        // Delete all options
        delete_option(self::OPTION_NAME);
        delete_option(self::API_KEY_OPTION);
        delete_option(self::GITHUB_DATA_OPTION);
        delete_option('deploy_forge_db_version');

        // Reload settings from defaults after reset
        $this->load_settings();

        return true;
    }

    /**
     * Validate settings
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->get('github_repo_owner'))) {
            $errors[] = __('GitHub repository owner is required.', 'deploy-forge');
        }

        if (empty($this->get('github_repo_name'))) {
            $errors[] = __('GitHub repository name is required.', 'deploy-forge');
        }

        if (empty($this->get('github_branch'))) {
            $errors[] = __('GitHub branch is required.', 'deploy-forge');
        }

        if (!$this->is_github_connected()) {
            $errors[] = __('GitHub connection is required. Please connect your GitHub account.', 'deploy-forge');
        }

        // Validate theme directory exists (uses repo name)
        $theme_path = $this->get_theme_path();
        if (!empty($this->get('github_repo_name')) && !is_dir($theme_path)) {
            $errors[] = sprintf(
                __('Theme directory does not exist: %s', 'deploy-forge'),
                $theme_path
            );
        }

        return $errors;
    }

    /**
     * Check if settings are configured
     */
    public function is_configured(): bool
    {
        return !empty($this->get('github_repo_owner'))
            && !empty($this->get('github_repo_name'))
            && !empty($this->get('github_branch'))
            && $this->is_github_connected();
    }

    /**
     * Get repository full name (owner/repo)
     */
    public function get_repo_full_name(): string
    {
        return $this->get('github_repo_owner') . '/' . $this->get('github_repo_name');
    }

    /**
     * Get theme path (uses repository name)
     */
    public function get_theme_path(): string
    {
        $repo_name = $this->get('github_repo_name');
        return WP_CONTENT_DIR . '/themes/' . $repo_name;
    }

    /**
     * Get backup directory
     */
    public function get_backup_directory(): string
    {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/deploy-forge-backups';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        return $backup_dir;
    }

    /**
     * Generate webhook secret
     */
    public function generate_webhook_secret(): string
    {
        $secret = wp_generate_password(32, false);
        $this->update('webhook_secret', $secret);
        return $secret;
    }

    /**
     * Get webhook URL
     */
    public function get_webhook_url(): string
    {
        return rest_url('deploy-forge/v1/webhook');
    }
}
