<?php

/**
 * Deploy Forge Connection Handler
 * Manages connection flow with Deploy Forge platform
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Connection_Handler
{

    private Deploy_Forge_Settings $settings;
    private Deploy_Forge_Debug_Logger $logger;

    public function __construct(Deploy_Forge_Settings $settings, Deploy_Forge_Debug_Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Initiate connection to Deploy Forge
     * Step 1: Call /connect/init to get redirect URL
     */
    public function initiate_connection(): array
    {
        $site_url = home_url();
        $return_url = admin_url('admin.php?page=deploy-forge-settings&action=df_callback');
        $nonce = wp_generate_password(16, false);

        // Store nonce temporarily (5 minutes) for verification
        set_transient('deploy_forge_connection_nonce', $nonce, 300);

        $backend_url = $this->settings->get_backend_url();
        $init_url = $backend_url . '/api/plugin/connect/init';

        $this->logger->log('Connection', 'Initiating Deploy Forge connection', [
            'site_url' => $site_url,
            'return_url' => $return_url,
        ]);

        $response = wp_remote_post($init_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'siteUrl' => $site_url,
                'returnUrl' => $return_url,
                'nonce' => $nonce,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Connection', 'Failed to initiate connection', $response);
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || !isset($body['success']) || !$body['success']) {
            $error_message = $body['error'] ?? 'Failed to initiate connection';
            $this->logger->error('Connection', 'Connection initiation failed', [
                'status' => $status_code,
                'body' => $body,
            ]);
            return [
                'success' => false,
                'message' => $error_message,
            ];
        }

        $this->logger->log('Connection', 'Connection initiated successfully');

        return [
            'success' => true,
            'redirect_url' => $body['redirectUrl'],
        ];
    }

    /**
     * Handle callback from Deploy Forge
     * Step 2: Receive connection token and nonce
     */
    public function handle_callback(string $connection_token, string $returned_nonce): array
    {
        // Verify nonce
        $stored_nonce = get_transient('deploy_forge_connection_nonce');

        if (empty($stored_nonce) || $stored_nonce !== $returned_nonce) {
            $this->logger->error('Connection', 'Invalid or expired nonce', [
                'has_stored_nonce' => !empty($stored_nonce),
                'nonces_match' => $stored_nonce === $returned_nonce,
            ]);
            return [
                'success' => false,
                'message' => __('Invalid or expired connection attempt. Please try again.', 'deploy-forge'),
            ];
        }

        // Clear the nonce
        delete_transient('deploy_forge_connection_nonce');

        $this->logger->log('Connection', 'Callback received, exchanging token for credentials');

        // Exchange token for credentials
        return $this->exchange_token($connection_token);
    }

    /**
     * Exchange connection token for API credentials
     * Step 3: Call /auth/exchange-token
     */
    private function exchange_token(string $connection_token): array
    {
        $backend_url = $this->settings->get_backend_url();
        $exchange_url = $backend_url . '/api/plugin/auth/exchange-token';

        $response = wp_remote_post($exchange_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'connectionToken' => $connection_token,
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Connection', 'Token exchange failed', $response);
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200 || !isset($body['success']) || !$body['success']) {
            $error_message = $body['error'] ?? 'Failed to exchange connection token';
            $this->logger->error('Connection', 'Token exchange failed', [
                'status' => $status_code,
                'body' => $body,
            ]);
            return [
                'success' => false,
                'message' => $error_message,
            ];
        }

        // Extract credentials
        $api_key = $body['apiKey'] ?? '';
        $webhook_secret = $body['webhookSecret'] ?? '';
        $site_id = $body['siteId'] ?? '';
        $domain = $body['domain'] ?? '';
        $installation_id = $body['installationId'] ?? '';
        $repo_owner = $body['repoOwner'] ?? '';
        $repo_name = $body['repoName'] ?? '';
        $repo_branch = $body['repoBranch'] ?? '';
        $deployment_method = $body['deploymentMethod'] ?? 'github_actions';
        $workflow_path = $body['workflowPath'] ?? '';

        // Validate required fields
        if (empty($api_key) || empty($webhook_secret) || empty($site_id)) {
            $this->logger->error('Connection', 'Missing required credentials in response', $body);
            return [
                'success' => false,
                'message' => __('Invalid credentials received from Deploy Forge.', 'deploy-forge'),
            ];
        }

        // Store credentials
        $this->settings->set_api_key($api_key);
        $this->settings->set_webhook_secret($webhook_secret);
        $this->settings->set_site_id($site_id);

        // Store connection data
        $this->settings->set_connection_data([
            'installation_id' => $installation_id,
            'repo_owner' => $repo_owner,
            'repo_name' => $repo_name,
            'repo_branch' => $repo_branch,
            'deployment_method' => $deployment_method,
            'workflow_path' => $workflow_path,
            'domain' => $domain,
            'connected_at' => current_time('mysql'),
        ]);

        $this->logger->log('Connection', 'Successfully connected to Deploy Forge', [
            'site_id' => $site_id,
            'domain' => $domain,
            'repo' => $repo_owner . '/' . $repo_name,
        ]);

        return [
            'success' => true,
            'message' => __('Successfully connected to Deploy Forge!', 'deploy-forge'),
            'data' => [
                'site_id' => $site_id,
                'domain' => $domain,
                'repo_owner' => $repo_owner,
                'repo_name' => $repo_name,
                'repo_branch' => $repo_branch,
            ],
        ];
    }

    /**
     * Disconnect from Deploy Forge
     */
    public function disconnect(): array
    {
        $this->logger->log('Connection', 'Disconnecting from Deploy Forge');

        $result = $this->settings->disconnect();

        if ($result) {
            $this->logger->log('Connection', 'Successfully disconnected');
            return [
                'success' => true,
                'message' => __('Successfully disconnected from Deploy Forge.', 'deploy-forge'),
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to disconnect.', 'deploy-forge'),
        ];
    }

    /**
     * Verify connection status with Deploy Forge API
     */
    public function verify_connection(): array
    {
        if (!$this->settings->is_connected()) {
            return [
                'success' => false,
                'message' => __('Not connected to Deploy Forge.', 'deploy-forge'),
                'connected' => false,
            ];
        }

        $backend_url = $this->settings->get_backend_url();
        $verify_url = $backend_url . '/api/plugin/auth/verify';

        $response = wp_remote_post($verify_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->settings->get_api_key(),
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->logger->error('Connection', 'Verification failed', $response);
            return [
                'success' => false,
                'message' => $response->get_error_message(),
                'connected' => false,
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code === 200 && isset($body['success']) && $body['success']) {
            return [
                'success' => true,
                'connected' => true,
                'site_id' => $body['siteId'] ?? '',
                'domain' => $body['domain'] ?? '',
                'status' => $body['status'] ?? 'active',
            ];
        }

        return [
            'success' => false,
            'message' => $body['error'] ?? 'Connection verification failed',
            'connected' => false,
        ];
    }
}
