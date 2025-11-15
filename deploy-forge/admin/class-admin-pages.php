<?php

/**
 * Admin pages class
 * Handles WordPress admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Admin_Pages
{

    private Deploy_Forge_Settings $settings;
    private Deploy_Forge_GitHub_API $github_api;
    private Deploy_Forge_Deployment_Manager $deployment_manager;
    private Deploy_Forge_Database $database;
    private Deploy_Forge_Debug_Logger $logger;
    private Deploy_Forge_App_Connector $app_connector;

    public function __construct(Deploy_Forge_Settings $settings, Deploy_Forge_GitHub_API $github_api, Deploy_Forge_Deployment_Manager $deployment_manager, Deploy_Forge_Database $database, Deploy_Forge_Debug_Logger $logger, Deploy_Forge_App_Connector $app_connector)
    {
        $this->settings = $settings;
        $this->github_api = $github_api;
        $this->deployment_manager = $deployment_manager;
        $this->database = $database;
        $this->logger = $logger;
        $this->app_connector = $app_connector;

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);

        // AJAX handlers
        add_action('wp_ajax_deploy_forge_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_deploy_forge_manual_deploy', [$this, 'ajax_manual_deploy']);
        add_action('wp_ajax_deploy_forge_get_status', [$this, 'ajax_get_status']);
        add_action('wp_ajax_deploy_forge_rollback', [$this, 'ajax_rollback']);
        add_action('wp_ajax_deploy_forge_approve', [$this, 'ajax_approve_deployment']);
        add_action('wp_ajax_deploy_forge_cancel', [$this, 'ajax_cancel_deployment']);
        add_action('wp_ajax_deploy_forge_get_commits', [$this, 'ajax_get_commits']);
        add_action('wp_ajax_deploy_forge_get_repos', [$this, 'ajax_get_repos']);
        add_action('wp_ajax_deploy_forge_get_workflows', [$this, 'ajax_get_workflows']);
        add_action('wp_ajax_deploy_forge_generate_secret', [$this, 'ajax_generate_secret']);
        add_action('wp_ajax_deploy_forge_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_deploy_forge_clear_logs', [$this, 'ajax_clear_logs']);

        // GitHub App connection AJAX handlers
        add_action('wp_ajax_deploy_forge_get_connect_url', [$this, 'ajax_get_connect_url']);
        add_action('wp_ajax_deploy_forge_disconnect', [$this, 'ajax_disconnect_github']);
        add_action('wp_ajax_deploy_forge_get_installation_repos', [$this, 'ajax_get_installation_repos']);
        add_action('wp_ajax_deploy_forge_bind_repo', [$this, 'ajax_bind_repo']);
        add_action('wp_ajax_deploy_forge_reset_all_data', [$this, 'ajax_reset_all_data']);
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu(): void
    {
        add_menu_page(
            __('Deploy Forge', 'deploy-forge'),
            __('Deploy Forge', 'deploy-forge'),
            'manage_options',
            'deploy-forge',
            [$this, 'render_dashboard_page'],
            'dashicons-update',
            80
        );

        add_submenu_page(
            'deploy-forge',
            __('Dashboard', 'deploy-forge'),
            __('Dashboard', 'deploy-forge'),
            'manage_options',
            'deploy-forge',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'deploy-forge',
            __('Settings', 'deploy-forge'),
            __('Settings', 'deploy-forge'),
            'manage_options',
            'deploy-forge-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'deploy-forge',
            __('Deployment History', 'deploy-forge'),
            __('History', 'deploy-forge'),
            'manage_options',
            'deploy-forge-history',
            [$this, 'render_history_page']
        );

        add_submenu_page(
            'deploy-forge',
            __('Debug Logs', 'deploy-forge'),
            __('Debug Logs', 'deploy-forge'),
            'manage_options',
            'deploy-forge-logs',
            [$this, 'render_logs_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void
    {
        // Only load on our plugin pages
        if (strpos($hook, 'deploy-forge') === false) {
            return;
        }

        // Enqueue shared styles first
        wp_enqueue_style(
            'deploy-forge-shared',
            DEPLOY_FORGE_PLUGIN_URL . 'admin/css/shared-styles.css',
            [],
            DEPLOY_FORGE_VERSION
        );

        // Enqueue admin-specific styles
        wp_enqueue_style(
            'deploy-forge-admin',
            DEPLOY_FORGE_PLUGIN_URL . 'admin/css/admin-styles.css',
            ['deploy-forge-shared'],
            DEPLOY_FORGE_VERSION
        );

        wp_enqueue_script(
            'deploy-forge-admin',
            DEPLOY_FORGE_PLUGIN_URL . 'admin/js/admin-scripts.js',
            ['jquery'],
            DEPLOY_FORGE_VERSION,
            true
        );

        wp_localize_script('deploy-forge-admin', 'deployForgeAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('deploy_forge_admin'),
            'strings' => [
                'confirmRollback' => __('Are you sure you want to rollback to this deployment? This will restore the previous theme files.', 'deploy-forge'),
                'confirmDeploy' => __('Are you sure you want to start a deployment?', 'deploy-forge'),
                'confirmCancel' => __('Are you sure you want to cancel this deployment? The GitHub Actions workflow will be stopped.', 'deploy-forge'),
                'deploying' => __('Deploying...', 'deploy-forge'),
                'testing' => __('Testing connection...', 'deploy-forge'),
                'cancelling' => __('Cancelling...', 'deploy-forge'),
            ],
        ]);
    }

    /**
     * Register settings
     */
    public function register_settings(): void
    {
        register_setting('deploy_forge_settings', 'deploy_forge_settings');
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page(): void
    {
        $stats = $this->database->get_statistics();
        $recent_deployments = $this->database->get_recent_deployments(5);
        $is_configured = $this->settings->is_configured();

        include DEPLOY_FORGE_PLUGIN_DIR . 'templates/dashboard-page.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void
    {
        // Save settings if form submitted
        if (isset($_POST['deploy_forge_save_settings'])) {
            // Verify nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'deploy_forge_settings')) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security verification failed. Please try again.', 'deploy-forge') . '</p></div>';
            } else {
                // Get current settings to preserve webhook_secret
                $current = $this->settings->get_all();

                $settings = [
                    'github_repo_owner' => $_POST['github_repo_owner'] ?? '',
                    'github_repo_name' => $_POST['github_repo_name'] ?? '',
                    'github_branch' => $_POST['github_branch'] ?? 'main',
                    'github_workflow_name' => $_POST['github_workflow_name'] ?? 'deploy-theme.yml',
                    'deployment_method' => $_POST['deployment_method'] ?? 'github_actions',
                    'auto_deploy_enabled' => isset($_POST['auto_deploy_enabled']),
                    'require_manual_approval' => isset($_POST['require_manual_approval']),
                    'create_backups' => isset($_POST['create_backups']),
                    'notification_email' => $_POST['notification_email'] ?? '',
                    // CRITICAL: Preserve webhook_secret from current settings (not editable in form)
                    'webhook_secret' => $current['webhook_secret'] ?? '',
                    'debug_mode' => isset($_POST['debug_mode']),
                ];

                $this->settings->save($settings);

                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'deploy-forge') . '</p></div>';
            }
        }

        $current_settings = $this->settings->get_all();
        $webhook_url = $this->settings->get_webhook_url();
        $is_connected = $this->app_connector->is_connected();
        $connection_details = $this->app_connector->get_connection_details();
        $settings = $this->settings; // Make settings object available to template

        include DEPLOY_FORGE_PLUGIN_DIR . 'templates/settings-page.php';
    }

    /**
     * Render history page
     */
    public function render_history_page(): void
    {
        $per_page = 20;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $per_page;

        $deployments = $this->database->get_recent_deployments($per_page, $offset);
        $total_deployments = $this->database->get_deployment_count();
        $total_pages = ceil($total_deployments / $per_page);

        include DEPLOY_FORGE_PLUGIN_DIR . 'templates/history-page.php';
    }

    /**
     * AJAX: Test GitHub connection
     */
    public function ajax_test_connection(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $result = $this->github_api->test_connection();
        wp_send_json($result);
    }

    /**
     * AJAX: Manual deployment
     */
    public function ajax_manual_deploy(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        try {
            $commit_hash = sanitize_text_field($_POST['commit_hash'] ?? '');

            if (empty($commit_hash)) {
                // Get latest commit
                $commits = $this->github_api->get_recent_commits(1);
                if (!$commits['success'] || empty($commits['data'])) {
                    wp_send_json_error(['message' => __('Failed to get latest commit', 'deploy-forge')]);
                }

                // Handle both array and object responses
                $first_commit = is_array($commits['data']) ? $commits['data'][0] : $commits['data'][0];
                $commit_hash = is_object($first_commit) ? $first_commit->sha : $first_commit['sha'];
            }

            // Get commit details
            $commit_result = $this->github_api->get_commit_details($commit_hash);
            if (!$commit_result['success']) {
                wp_send_json_error(['message' => __('Failed to get commit details', 'deploy-forge')]);
            }

            $commit_data = $commit_result['data'];

            // Handle both array and object responses for commit data
            if (is_object($commit_data)) {
                $commit_message = $commit_data->commit->message ?? '';
                $commit_author = $commit_data->commit->author->name ?? '';
                $commit_date = $commit_data->commit->author->date ?? current_time('mysql');
            } else {
                $commit_message = $commit_data['commit']['message'] ?? '';
                $commit_author = $commit_data['commit']['author']['name'] ?? '';
                $commit_date = $commit_data['commit']['author']['date'] ?? current_time('mysql');
            }

            $deployment_result = $this->deployment_manager->start_deployment($commit_hash, 'manual', get_current_user_id(), [
                'commit_message' => $commit_message,
                'commit_author' => $commit_author,
                'commit_date' => $commit_date,
            ]);

            // Check if result is an array (error) or int (success)
            if (is_array($deployment_result) && isset($deployment_result['error'])) {
                // Deployment blocked due to existing build
                wp_send_json_error([
                    'message' => $deployment_result['message'],
                    'error_code' => $deployment_result['error'],
                    'building_deployment' => [
                        'id' => $deployment_result['building_deployment']->id ?? 0,
                        'commit_hash' => $deployment_result['building_deployment']->commit_hash ?? '',
                        'status' => $deployment_result['building_deployment']->status ?? '',
                        'created_at' => $deployment_result['building_deployment']->created_at ?? '',
                    ],
                ]);
            }

            $this->logger->log('Admin', 'Deployment result received', [
                'deployment_result' => $deployment_result,
                'type' => gettype($deployment_result),
                'is_truthy' => (bool)$deployment_result,
            ]);

            if ($deployment_result) {
                $this->logger->log('Admin', 'Sending success response', ['deployment_id' => $deployment_result]);
                wp_send_json_success([
                    'message' => __('Deployment started successfully!', 'deploy-forge'),
                    'deployment_id' => $deployment_result,
                ]);
            } else {
                $this->logger->log('Admin', 'Sending error response - deployment result was falsy');
                wp_send_json_error(['message' => __('Failed to start deployment', 'deploy-forge')]);
            }
        } catch (Exception $e) {
            $this->logger->error('Admin', 'Manual deploy exception', ['error' => $e->getMessage()]);
            wp_send_json_error(['message' => sprintf(__('Deployment error: %s', 'deploy-forge'), $e->getMessage())]);
        }
    }

    /**
     * AJAX: Get deployment status
     */
    public function ajax_get_status(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $deployment_id = intval($_POST['deployment_id'] ?? 0);

        if ($deployment_id) {
            $deployment = $this->database->get_deployment($deployment_id);
            wp_send_json_success(['deployment' => $deployment]);
        } else {
            $stats = $this->database->get_statistics();
            $recent = $this->database->get_recent_deployments(5);
            wp_send_json_success(['stats' => $stats, 'recent' => $recent]);
        }
    }

    /**
     * AJAX: Rollback deployment
     */
    public function ajax_rollback(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $deployment_id = intval($_POST['deployment_id'] ?? 0);

        if (!$deployment_id) {
            wp_send_json_error(['message' => __('Invalid deployment ID', 'deploy-forge')]);
        }

        $result = $this->deployment_manager->rollback_deployment($deployment_id);

        if ($result) {
            wp_send_json_success(['message' => __('Rollback completed successfully!', 'deploy-forge')]);
        } else {
            wp_send_json_error(['message' => __('Rollback failed', 'deploy-forge')]);
        }
    }

    /**
     * AJAX: Approve pending deployment (manual approval)
     */
    public function ajax_approve_deployment(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $deployment_id = intval($_POST['deployment_id'] ?? 0);

        if (!$deployment_id) {
            wp_send_json_error(['message' => __('Invalid deployment ID', 'deploy-forge')]);
        }

        // Get deployment details
        $deployment = $this->database->get_deployment($deployment_id);

        if (!$deployment) {
            wp_send_json_error(['message' => __('Deployment not found', 'deploy-forge')]);
        }

        if ($deployment->status !== 'pending') {
            wp_send_json_error(['message' => __('Only pending deployments can be approved', 'deploy-forge')]);
        }

        // Approve the deployment by triggering the workflow
        $result = $this->deployment_manager->approve_pending_deployment($deployment_id, get_current_user_id());

        if ($result) {
            wp_send_json_success(['message' => __('Deployment approved and started successfully!', 'deploy-forge')]);
        } else {
            wp_send_json_error(['message' => __('Failed to start deployment', 'deploy-forge')]);
        }
    }

    /**
     * AJAX: Cancel deployment
     */
    public function ajax_cancel_deployment(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $deployment_id = intval($_POST['deployment_id'] ?? 0);

        if (!$deployment_id) {
            wp_send_json_error(['message' => __('Invalid deployment ID', 'deploy-forge')]);
        }

        $result = $this->deployment_manager->cancel_deployment($deployment_id);

        if ($result) {
            wp_send_json_success(['message' => __('Deployment cancelled successfully!', 'deploy-forge')]);
        } else {
            wp_send_json_error(['message' => __('Failed to cancel deployment. It may have already completed or been cancelled.', 'deploy-forge')]);
        }
    }

    /**
     * AJAX: Get recent commits
     */
    public function ajax_get_commits(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $result = $this->github_api->get_recent_commits(10);
        wp_send_json($result);
    }

    /**
     * AJAX: Get user repositories
     */
    public function ajax_get_repos(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $result = $this->github_api->get_user_repositories();

        if ($result['success']) {
            wp_send_json_success(['repos' => $result['data']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Get repository workflows
     * SECURITY: Validates nonce, capability, and sanitizes inputs
     */
    public function ajax_get_workflows(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
            return;
        }

        // SECURITY: Sanitize and validate input parameters
        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $repo = sanitize_text_field($_POST['repo'] ?? '');

        if (empty($owner) || empty($repo)) {
            wp_send_json_error(['message' => __('Missing owner or repo parameter', 'deploy-forge')]);
            return;
        }

        // Additional validation: Check for valid characters
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $owner) || !preg_match('/^[a-zA-Z0-9_.-]+$/', $repo)) {
            wp_send_json_error(['message' => __('Invalid repository format', 'deploy-forge')]);
            return;
        }

        $result = $this->github_api->get_workflows($owner, $repo);

        if ($result['success']) {
            wp_send_json_success([
                'workflows' => $result['workflows'],
                'total_count' => $result['total_count']
            ]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Generate webhook secret
     */
    public function ajax_generate_secret(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $secret = $this->settings->generate_webhook_secret();

        if ($secret) {
            wp_send_json_success(['secret' => $secret]);
        } else {
            wp_send_json_error(['message' => __('Failed to generate secret', 'deploy-forge')]);
        }
    }

    /**
     * Render debug logs page
     */
    public function render_logs_page(): void
    {
        if (!$this->logger->is_enabled()) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Debug Logs', 'deploy-forge') . '</h1>';
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Debug mode is not enabled. Enable it in ', 'deploy-forge');
            echo '<a href="' . esc_url(admin_url('admin.php?page=deploy-forge-settings')) . '">';
            echo esc_html__('Settings', 'deploy-forge');
            echo '</a> to start logging.';
            echo '</p></div>';
            echo '</div>';
            return;
        }

        include DEPLOY_FORGE_PLUGIN_DIR . 'templates/logs-page.php';
    }

    /**
     * AJAX: Get debug logs
     */
    public function ajax_get_logs(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $lines = intval($_POST['lines'] ?? 100);
        $logs = $this->logger->get_recent_logs($lines);
        $size = $this->logger->get_log_size();

        wp_send_json_success([
            'logs' => $logs,
            'size' => $size,
        ]);
    }

    /**
     * AJAX: Clear debug logs
     */
    public function ajax_clear_logs(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $result = $this->logger->clear_logs();

        if ($result) {
            wp_send_json_success(['message' => __('Logs cleared successfully', 'deploy-forge')]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear logs', 'deploy-forge')]);
        }
    }

    /**
     * AJAX: Get GitHub App connect URL
     */
    public function ajax_get_connect_url(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $connect_url = $this->app_connector->get_connect_url();

        if (is_wp_error($connect_url)) {
            wp_send_json_error(['message' => $connect_url->get_error_message()]);
        }

        wp_send_json_success(['connect_url' => $connect_url]);
    }

    /**
     * AJAX: Disconnect from GitHub
     */
    public function ajax_disconnect_github(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        $result = $this->app_connector->disconnect();

        if ($result) {
            wp_send_json_success(['message' => __('Disconnected from GitHub successfully', 'deploy-forge')]);
        } else {
            wp_send_json_error(['message' => __('Failed to disconnect', 'deploy-forge')]);
        }
    }

    /**
     * AJAX: Get installation repositories (repos accessible by GitHub App)
     */
    public function ajax_get_installation_repos(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        // Check if already bound
        if ($this->settings->is_repo_bound()) {
            wp_send_json_error(['message' => __('Repository is already bound. Disconnect to change repository.', 'deploy-forge')]);
            return;
        }

        $this->logger->log('Admin', 'Fetching installation repositories');

        $result = $this->github_api->get_installation_repositories();

        $this->logger->log('Admin', 'Installation repositories result', [
            'success' => $result['success'],
            'repo_count' => isset($result['data']) ? count($result['data']) : 0,
            'message' => $result['message'] ?? 'N/A'
        ]);

        if ($result['success']) {
            wp_send_json_success(['repos' => $result['data']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Bind repository (one-time selection, cannot be changed without reconnecting)
     */
    public function ajax_bind_repo(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
        }

        // Check if already bound
        if ($this->settings->is_repo_bound()) {
            wp_send_json_error(['message' => __('Repository is already bound. Disconnect from GitHub to change repository.', 'deploy-forge')]);
            return;
        }

        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $default_branch = sanitize_text_field($_POST['default_branch'] ?? 'main');

        if (empty($owner) || empty($name)) {
            wp_send_json_error(['message' => __('Invalid repository data', 'deploy-forge')]);
            return;
        }

        $result = $this->settings->bind_repository($owner, $name, $default_branch);

        if ($result) {
            $this->logger->log('Admin', 'Repository bound', [
                'repo' => $owner . '/' . $name,
                'branch' => $default_branch,
            ]);

            wp_send_json_success([
                'message' => sprintf(
                    __('Repository %s successfully bound. This cannot be changed without disconnecting from GitHub.', 'deploy-forge'),
                    $owner . '/' . $name
                ),
                'repo' => [
                    'owner' => $owner,
                    'name' => $name,
                    'full_name' => $owner . '/' . $name,
                    'default_branch' => $default_branch,
                ],
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to bind repository', 'deploy-forge')]);
        }
    }

    /**
     * AJAX: Reset all plugin data (DANGER!)
     */
    public function ajax_reset_all_data(): void
    {
        check_ajax_referer('deploy_forge_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'deploy-forge')]);
            return;
        }

        $result = $this->app_connector->reset_all_data();

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message()
            ]);
        } else {
            wp_send_json_success([
                'message' => __('All plugin data has been reset. The page will reload.', 'deploy-forge')
            ]);
        }
    }
}
