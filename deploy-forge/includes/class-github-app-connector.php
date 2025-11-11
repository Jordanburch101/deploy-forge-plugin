<?php

/**
 * GitHub App Connector class
 * Handles GitHub App connection flow and OAuth integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_App_Connector
{

    private Deploy_Forge_Settings $settings;
    private Deploy_Forge_Debug_Logger $logger;

    public function __construct(Deploy_Forge_Settings $settings, Deploy_Forge_Debug_Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;

        // Hook to handle OAuth callback
        add_action('admin_init', [$this, 'handle_oauth_callback']);
    }

    /**
     * Get the Connect to GitHub URL
     * 
     * @return string|WP_Error The OAuth initiation URL or error
     */
    public function get_connect_url(): string|WP_Error
    {
        // Get backend URL
        $backend_url = $this->get_backend_url();

        // Determine return URL based on context
        // If we're on the wizard page, return to wizard; otherwise return to settings
        $current_page = $_GET['page'] ?? '';
        if ($current_page === 'deploy-forge-wizard') {
            $return_url = admin_url('admin.php?page=deploy-forge-wizard&step=2');
        } else {
            $return_url = admin_url('admin.php?page=deploy-forge-settings');
        }

        // Generate nonce for security
        $nonce = wp_create_nonce('deploy_forge_oauth');

        // Build OAuth initiation URL
        $connect_url = add_query_arg([
            'site_url' => home_url(),
            'return_url' => $return_url,
            'nonce' => $nonce,
        ], $backend_url . '/api/auth/connect');

        $this->logger->log('GitHub_App_Connector', 'Generated connect URL', [
            'current_page' => $current_page,
            'return_url' => $return_url,
        ]);

        return $connect_url;
    }

    /**
     * Handle OAuth callback from backend
     * Called after GitHub App installation completes
     */
    public function handle_oauth_callback(): void
    {
        // Check if this is an OAuth callback
        if (!isset($_GET['github_connected'])) {
            return;
        }

        $this->logger->log('GitHub_App_Connector', 'OAuth callback detected', [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'github_connected' => $_GET['github_connected'] ?? '',
            'has_nonce' => isset($_GET['nonce'])
        ]);

        // Verify nonce
        $nonce = sanitize_text_field($_GET['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'deploy_forge_oauth')) {
            $this->logger->error('GitHub_App_Connector', 'Invalid nonce in OAuth callback');
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('Security verification failed. Please try connecting again.', 'deploy-forge') .
                    '</p></div>';
            });
            return;
        }

        // Check for error
        if (isset($_GET['github_error'])) {
            $error_message = sanitize_text_field($_GET['error_message'] ?? 'Unknown error');
            $this->logger->error('GitHub_App_Connector', 'OAuth error', ['message' => $error_message]);

            add_action('admin_notices', function () use ($error_message) {
                echo '<div class="notice notice-error"><p>' .
                    sprintf(
                        esc_html__('Failed to connect to GitHub: %s', 'deploy-forge'),
                        esc_html($error_message)
                    ) .
                    '</p></div>';
            });
            return;
        }

        // Extract connection token and repository data
        $connection_token = sanitize_text_field($_GET['connection_token'] ?? '');
        $repo_owner = sanitize_text_field($_GET['repo_owner'] ?? '');
        $repo_name = sanitize_text_field($_GET['repo_name'] ?? '');
        $repo_default_branch = sanitize_text_field($_GET['repo_default_branch'] ?? 'main');

        if (empty($connection_token)) {
            $this->logger->error('GitHub_App_Connector', 'Missing connection token in callback');
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('Failed to connect to GitHub: Missing connection token.', 'deploy-forge') .
                    '</p></div>';
            });
            return;
        }

        // Exchange connection token for credentials via backend API
        $backend_url = defined('DEPLOY_FORGE_BACKEND_URL') ? constant('DEPLOY_FORGE_BACKEND_URL') : 'https://deploy-forge.vercel.app';
        $exchange_url = $backend_url . '/api/auth/exchange-token';

        $this->logger->log('GitHub_App_Connector', 'Calling token exchange endpoint', [
            'url' => $exchange_url,
            'has_token' => !empty($connection_token)
        ]);

        $response = wp_remote_post($exchange_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'connection_token' => $connection_token,
                'nonce' => $nonce
            ]),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('GitHub_App_Connector', 'Token exchange failed', [
                'error' => $response->get_error_message()
            ]);
            add_action('admin_notices', function () use ($response) {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('Failed to connect to GitHub: ', 'deploy-forge') .
                    esc_html($response->get_error_message()) .
                    '</p></div>';
            });
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Log the full response for debugging
        $this->logger->log('GitHub_App_Connector', 'Token exchange response received', [
            'status_code' => wp_remote_retrieve_response_code($response),
            'body_keys' => array_keys($body ?? []),
            'has_success' => isset($body['success']),
            'success_value' => $body['success'] ?? null
        ]);

        if (!isset($body['success']) || !$body['success']) {
            $error_msg = $body['error'] ?? 'Unknown error';
            $this->logger->error('GitHub_App_Connector', 'Token exchange returned error', [
                'error' => $error_msg,
                'full_body' => $body
            ]);
            add_action('admin_notices', function () use ($error_msg) {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('Failed to connect to GitHub: ', 'deploy-forge') .
                    esc_html($error_msg) .
                    '</p></div>';
            });
            return;
        }

        // Extract credentials from exchange response
        $api_key = sanitize_text_field($body['api_key'] ?? '');
        $webhook_secret = sanitize_text_field($body['webhook_secret'] ?? '');
        $installation_id = intval($body['installation_id'] ?? 0);

        // DEBUG: Log what we received
        $this->logger->log('GitHub_App_Connector', 'Extracted credentials from token exchange', [
            'has_api_key' => !empty($api_key),
            'api_key_length' => strlen($api_key ?? ''),
            'has_webhook_secret' => !empty($webhook_secret),
            'webhook_secret_length' => strlen($webhook_secret ?? ''),
            'installation_id' => $installation_id
        ]);

        if (empty($api_key) || empty($installation_id)) {
            $this->logger->error('GitHub_App_Connector', 'Missing credentials in token exchange response');
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('Failed to connect to GitHub: Missing credentials.', 'deploy-forge') .
                    '</p></div>';
            });
            return;
        }

        // Store API key
        $this->settings->set_api_key($api_key);

        // Store webhook secret (used to verify webhooks from backend)
        if (!empty($webhook_secret)) {
            $this->logger->log('GitHub_App_Connector', 'BEFORE saving webhook secret', [
                'webhook_secret_length' => strlen($webhook_secret),
                'webhook_secret_preview' => substr($webhook_secret, 0, 8) . '...'
            ]);

            $save_result = $this->settings->update('webhook_secret', $webhook_secret);

            $this->logger->log('GitHub_App_Connector', 'AFTER saving webhook secret', [
                'save_result' => $save_result,
                'verification_length' => strlen($this->settings->get('webhook_secret') ?? ''),
                'verification_preview' => !empty($this->settings->get('webhook_secret')) ? substr($this->settings->get('webhook_secret'), 0, 8) . '...' : 'EMPTY'
            ]);
        } else {
            $this->logger->error('GitHub_App_Connector', 'No webhook secret received from backend!');
        }

        // Store GitHub connection data
        $github_data = [
            'installation_id' => $installation_id,
            'connected_at' => current_time('mysql'),
        ];

        // If repository was selected during installation, DON'T automatically bind it
        // User must explicitly bind via the UI for security
        if (!empty($repo_owner) && !empty($repo_name)) {
            $github_data['account_login'] = $repo_owner;
            // Store the repo info but don't bind it yet
            // User will see the repo selector and must click "Bind Repository"
        }

        $this->settings->set_github_data($github_data);

        $this->logger->log('GitHub_App_Connector', 'OAuth connection successful', [
            'installation_id' => $installation_id,
            'repo' => $repo_owner . '/' . $repo_name,
        ]);

        // Show success notice
        add_action('admin_notices', function () {
            $message = __('Successfully connected to GitHub!', 'deploy-forge');
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        });

        // Redirect to remove query parameters
        // Backend already sent us to the correct return_url (wizard or settings)
        // Just add connected=1 flag if we're on wizard page
        $current_page = $_GET['page'] ?? '';
        if ($current_page === 'deploy-forge-wizard') {
            $this->logger->log('GitHub_App_Connector', 'On wizard page after OAuth, adding connected flag');
            wp_safe_redirect(admin_url('admin.php?page=deploy-forge-wizard&step=2&connected=1'));
        } else {
            $this->logger->log('GitHub_App_Connector', 'On settings page after OAuth');
            wp_safe_redirect(admin_url('admin.php?page=deploy-forge-settings'));
        }
        exit;
    }

    /**
     * Disconnect from GitHub
     *
     * @return bool True on success
     */
    public function disconnect(): bool
    {
        $this->logger->log('GitHub_App_Connector', 'Disconnecting from GitHub');

        // Notify backend to cleanup installation data (graceful disconnect)
        $this->notify_backend_disconnect();

        // Remove local data
        $this->settings->disconnect_github();

        return true;
    }

    /**
     * Notify backend about disconnect (graceful cleanup)
     */
    private function notify_backend_disconnect(): void
    {
        $api_key = $this->settings->get_api_key();

        // If no API key, nothing to clean up on backend
        if (empty($api_key)) {
            $this->logger->log('GitHub_App_Connector', 'No API key found, skipping backend notification');
            return;
        }

        $backend_url = defined('DEPLOY_FORGE_BACKEND_URL')
            ? constant('DEPLOY_FORGE_BACKEND_URL')
            : 'https://deploy-forge.vercel.app';

        $disconnect_url = $backend_url . '/api/auth/disconnect';

        $this->logger->log('GitHub_App_Connector', 'Notifying backend of disconnect', [
            'url' => $disconnect_url
        ]);

        $response = wp_remote_post($disconnect_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ],
            'body' => json_encode([]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            // Log error but don't fail the disconnect - local cleanup should still happen
            $this->logger->error('GitHub_App_Connector', 'Failed to notify backend of disconnect', [
                'error' => $response->get_error_message()
            ]);
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            $this->logger->log('GitHub_App_Connector', 'Backend notified successfully', [
                'response' => json_decode($body, true)
            ]);
        } else {
            $this->logger->error('GitHub_App_Connector', 'Backend disconnect notification failed', [
                'status' => $status_code,
                'body' => $body
            ]);
        }
    }

    /**
     * Reset all plugin data (DANGER: Cannot be undone!)
     *
     * Deletes:
     * - GitHub connection
     * - All deployment history
     * - All backups
     * - All settings
     * - Backend KV store data
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function reset_all_data()
    {
        $this->logger->log('GitHub_App_Connector', 'RESET: Starting complete plugin data reset');

        try {
            // 1. Notify backend to delete all data (if connected)
            $backend_deleted = $this->notify_backend_reset();

            // 2. Clear deployment history from database
            require_once plugin_dir_path(__FILE__) . 'class-database.php';
            $database = new Deploy_Forge_Database();
            $database->clear_all_deployments();
            $this->logger->log('GitHub_App_Connector', 'RESET: Deployment history cleared');

            // 3. Delete all backup files
            $backups_deleted = $this->delete_all_backups();
            $this->logger->log('GitHub_App_Connector', 'RESET: Backup files deleted', [
                'deleted' => $backups_deleted
            ]);

            // 4. Clear all settings
            $this->settings->reset_all_settings();
            $this->logger->log('GitHub_App_Connector', 'RESET: All settings cleared');

            // 5. Clear debug logs
            $this->logger->clear_logs();
            $this->logger->log('GitHub_App_Connector', 'RESET: Debug logs cleared');

            $this->logger->log('GitHub_App_Connector', 'RESET: Complete plugin reset finished successfully', [
                'backend_deleted' => $backend_deleted,
                'backups_deleted' => $backups_deleted
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('GitHub_App_Connector', 'RESET: Failed to reset plugin data', [
                'error' => $e->getMessage()
            ]);
            return new WP_Error('reset_failed', $e->getMessage());
        }
    }

    /**
     * Notify backend to delete all site data (for reset)
     */
    private function notify_backend_reset(): bool
    {
        $api_key = $this->settings->get_api_key();

        // If no API key, nothing to clean up on backend
        if (empty($api_key)) {
            $this->logger->log('GitHub_App_Connector', 'RESET: No API key found, skipping backend notification');
            return true;
        }

        $backend_url = defined('DEPLOY_FORGE_BACKEND_URL')
            ? constant('DEPLOY_FORGE_BACKEND_URL')
            : 'https://deploy-forge.vercel.app';

        $disconnect_url = $backend_url . '/api/auth/disconnect';

        $this->logger->log('GitHub_App_Connector', 'RESET: Notifying backend to delete data', [
            'url' => $disconnect_url
        ]);

        $response = wp_remote_post($disconnect_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ],
            'body' => json_encode([]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('GitHub_App_Connector', 'RESET: Failed to notify backend', [
                'error' => $response->get_error_message()
            ]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            $this->logger->log('GitHub_App_Connector', 'RESET: Backend data deleted successfully');
            return true;
        } else {
            $this->logger->error('GitHub_App_Connector', 'RESET: Backend deletion failed', [
                'status' => $status_code
            ]);
            return false;
        }
    }

    /**
     * Delete all backup files
     */
    private function delete_all_backups(): int
    {
        $backup_dir = wp_upload_dir()['basedir'] . '/deploy-forge-backups';

        if (!is_dir($backup_dir)) {
            return 0;
        }

        $files = glob($backup_dir . '/*');
        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        // Try to remove the directory itself
        @rmdir($backup_dir);

        return $deleted;
    }

    /**
     * Check if GitHub is connected
     *
     * @return bool True if connected
     */
    public function is_connected(): bool
    {
        return $this->settings->is_github_connected();
    }

    /**
     * Get connection details
     *
     * @return array Connection details (installation_id, account, repo, etc.)
     */
    public function get_connection_details(): array
    {
        if (!$this->is_connected()) {
            return [];
        }

        $github_data = $this->settings->get_github_data();
        $settings = $this->settings->get_all();

        return [
            'connected' => true,
            'installation_id' => $github_data['installation_id'] ?? 0,
            'account_login' => $github_data['account_login'] ?? $settings['github_repo_owner'] ?? '',
            'account_type' => $github_data['account_type'] ?? '',
            'account_avatar' => $github_data['account_avatar'] ?? '',
            'repo_name' => $settings['github_repo_name'] ?? '',
            'repo_full_name' => $settings['github_repo_owner'] . '/' . $settings['github_repo_name'],
            'repo_branch' => $settings['github_branch'] ?? 'main',
            'connected_at' => $github_data['connected_at'] ?? '',
        ];
    }

    /**
     * Get backend URL from constant or use default
     * 
     * @return string Backend URL
     */
    private function get_backend_url(): string
    {
        return defined('DEPLOY_FORGE_BACKEND_URL')
            ? constant('DEPLOY_FORGE_BACKEND_URL')
            : 'https://deploy-forge.vercel.app';
    }
}
