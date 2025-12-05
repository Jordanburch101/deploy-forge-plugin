<?php

/**
 * Settings management class
 * Handles plugin options and Deploy Forge API credentials
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Settings
{

    private const OPTION_NAME = 'deploy_forge_settings';
    private const API_KEY_OPTION = 'deploy_forge_api_key';
    private const WEBHOOK_SECRET_OPTION = 'deploy_forge_webhook_secret';
    private const SITE_ID_OPTION = 'deploy_forge_site_id';
    private const CONNECTION_DATA_OPTION = 'deploy_forge_connection_data';
    private const BACKEND_URL = 'https://deploy-forge-website.vercel.app';

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
            'debug_mode' => false,
        ];

        $this->settings = wp_parse_args(get_option(self::OPTION_NAME, []), $defaults);
    }

    /**
     * Get a setting value
     */
    public function get(string $key, $default = null)
    {
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

        if ($result) {
            $this->load_settings();
        }

        return $result;
    }

    /**
     * Get Deploy Forge API key
     */
    public function get_api_key(): string
    {
        return get_option(self::API_KEY_OPTION, '');
    }

    /**
     * Set Deploy Forge API key
     */
    public function set_api_key(string $api_key): bool
    {
        if (empty($api_key)) {
            return delete_option(self::API_KEY_OPTION);
        }

        // Validate API key format (df_live_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX)
        if (!preg_match('/^df_live_[a-f0-9]{32}$/i', $api_key)) {
            return false;
        }

        return update_option(self::API_KEY_OPTION, $api_key);
    }

    /**
     * Get webhook secret
     */
    public function get_webhook_secret(): string
    {
        return get_option(self::WEBHOOK_SECRET_OPTION, '');
    }

    /**
     * Set webhook secret
     */
    public function set_webhook_secret(string $secret): bool
    {
        if (empty($secret)) {
            return delete_option(self::WEBHOOK_SECRET_OPTION);
        }

        // Validate webhook secret format (whsec_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX)
        if (!preg_match('/^whsec_[a-f0-9]{32}$/i', $secret)) {
            return false;
        }

        return update_option(self::WEBHOOK_SECRET_OPTION, $secret);
    }

    /**
     * Get site ID
     */
    public function get_site_id(): string
    {
        return get_option(self::SITE_ID_OPTION, '');
    }

    /**
     * Set site ID
     */
    public function set_site_id(string $site_id): bool
    {
        if (empty($site_id)) {
            return delete_option(self::SITE_ID_OPTION);
        }

        return update_option(self::SITE_ID_OPTION, $site_id);
    }

    /**
     * Get connection data (repo info, installation ID, etc.)
     */
    public function get_connection_data(): array
    {
        $defaults = [
            'installation_id' => '',
            'repo_owner' => '',
            'repo_name' => '',
            'repo_branch' => 'main',
            'deployment_method' => 'github_actions',
            'workflow_path' => '',
            'connected_at' => '',
            'domain' => '',
        ];

        return wp_parse_args(get_option(self::CONNECTION_DATA_OPTION, []), $defaults);
    }

    /**
     * Set connection data
     */
    public function set_connection_data(array $data): bool
    {
        $sanitized = [
            'installation_id' => sanitize_text_field($data['installation_id'] ?? ''),
            'repo_owner' => sanitize_text_field($data['repo_owner'] ?? ''),
            'repo_name' => sanitize_text_field($data['repo_name'] ?? ''),
            'repo_branch' => sanitize_text_field($data['repo_branch'] ?? 'main'),
            'deployment_method' => sanitize_text_field($data['deployment_method'] ?? 'github_actions'),
            'workflow_path' => sanitize_text_field($data['workflow_path'] ?? ''),
            'connected_at' => sanitize_text_field($data['connected_at'] ?? current_time('mysql')),
            'domain' => sanitize_text_field($data['domain'] ?? ''),
        ];

        // Also update settings with repo info for backward compatibility
        $current_settings = $this->get_all();
        $current_settings['github_repo_owner'] = $sanitized['repo_owner'];
        $current_settings['github_repo_name'] = $sanitized['repo_name'];
        $current_settings['github_branch'] = $sanitized['repo_branch'];
        $current_settings['deployment_method'] = $sanitized['deployment_method'];
        $current_settings['github_workflow_name'] = basename($sanitized['workflow_path']);
        $this->save($current_settings);

        return update_option(self::CONNECTION_DATA_OPTION, $sanitized);
    }

    /**
     * Check if connected to Deploy Forge
     */
    public function is_connected(): bool
    {
        return !empty($this->get_api_key())
            && !empty($this->get_webhook_secret())
            && !empty($this->get_site_id());
    }

    /**
     * Check if repository is configured
     */
    public function is_repo_configured(): bool
    {
        $data = $this->get_connection_data();
        return !empty($data['repo_owner']) && !empty($data['repo_name']);
    }

    /**
     * Disconnect from Deploy Forge
     * Calls the API to disconnect and clears local credentials
     */
    public function disconnect(): bool
    {
        $api_key = $this->get_api_key();

        // Call API to disconnect if we have an API key
        if (!empty($api_key)) {
            $response = wp_remote_post(self::BACKEND_URL . '/api/plugin/auth/disconnect', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $api_key,
                ],
                'timeout' => 15,
            ]);

            // Log any errors but don't fail the disconnect
            if (is_wp_error($response)) {
                error_log('Deploy Forge: Disconnect API error - ' . $response->get_error_message());
            }
        }

        // Clear all stored credentials and data
        delete_option(self::API_KEY_OPTION);
        delete_option(self::WEBHOOK_SECRET_OPTION);
        delete_option(self::SITE_ID_OPTION);
        delete_option(self::CONNECTION_DATA_OPTION);

        // Clear repo settings
        $current_settings = $this->get_all();
        $current_settings['github_repo_owner'] = '';
        $current_settings['github_repo_name'] = '';
        $current_settings['github_branch'] = 'main';
        $current_settings['github_workflow_name'] = 'deploy-theme.yml';
        $this->save($current_settings);

        return true;
    }

    /**
     * Get Deploy Forge backend URL
     */
    public function get_backend_url(): string
    {
        return defined('DEPLOY_FORGE_BACKEND_URL')
            ? constant('DEPLOY_FORGE_BACKEND_URL')
            : self::BACKEND_URL;
    }

    /**
     * Get repository full name (owner/repo)
     */
    public function get_repo_full_name(): string
    {
        $data = $this->get_connection_data();
        if (!empty($data['repo_owner']) && !empty($data['repo_name'])) {
            return $data['repo_owner'] . '/' . $data['repo_name'];
        }

        // Fallback to settings for backward compatibility
        return $this->get('github_repo_owner') . '/' . $this->get('github_repo_name');
    }

    /**
     * Get theme path (uses repository name)
     */
    public function get_theme_path(): string
    {
        $data = $this->get_connection_data();
        $repo_name = !empty($data['repo_name']) ? $data['repo_name'] : $this->get('github_repo_name');
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
     * Get webhook URL
     */
    public function get_webhook_url(): string
    {
        return rest_url('deploy-forge/v1/webhook');
    }

    /**
     * Validate settings
     */
    public function validate(): array
    {
        $errors = [];

        if (!$this->is_connected()) {
            $errors[] = __('Not connected to Deploy Forge. Please connect your site.', 'deploy-forge');
        }

        if (!$this->is_repo_configured()) {
            $errors[] = __('Repository not configured. Please reconnect to configure your repository.', 'deploy-forge');
        }

        // Validate theme directory exists
        $theme_path = $this->get_theme_path();
        $repo_name = $this->get_connection_data()['repo_name'] ?? $this->get('github_repo_name');
        if (!empty($repo_name) && !is_dir($theme_path)) {
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
        return $this->is_connected() && $this->is_repo_configured();
    }

    /**
     * Reset all plugin settings (for complete reset)
     */
    public function reset_all_settings(): bool
    {
        // Delete all options
        delete_option(self::OPTION_NAME);
        delete_option(self::API_KEY_OPTION);
        delete_option(self::WEBHOOK_SECRET_OPTION);
        delete_option(self::SITE_ID_OPTION);
        delete_option(self::CONNECTION_DATA_OPTION);
        delete_option('deploy_forge_db_version');

        // Reload settings from defaults after reset
        $this->load_settings();

        return true;
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated Use get_webhook_secret() instead
     */
    public function generate_webhook_secret(): string
    {
        // This is now handled by Deploy Forge platform
        return $this->get_webhook_secret();
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated Use is_connected() instead
     */
    public function is_github_connected(): bool
    {
        return $this->is_connected();
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated No longer used - repo binding handled by platform
     */
    public function is_repo_bound(): bool
    {
        return $this->is_repo_configured();
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated No longer used - GitHub data structure changed
     */
    public function get_github_data(): array
    {
        return $this->get_connection_data();
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated No longer used - GitHub data structure changed
     */
    public function set_github_data(array $data): bool
    {
        return $this->set_connection_data($data);
    }

    /**
     * Legacy method for backward compatibility
     * @deprecated Use disconnect() instead
     */
    public function disconnect_github(): bool
    {
        return $this->disconnect();
    }
}
