<?php

/**
 * Admin pages class
 * Handles WordPress admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Admin_Pages extends Deploy_Forge_Ajax_Handler_Base
{

    private Deploy_Forge_Settings $settings;
    private Deploy_Forge_GitHub_API $github_api;
    private Deploy_Forge_Deployment_Manager $deployment_manager;
    private Deploy_Forge_Database $database;
    private Deploy_Forge_Debug_Logger $logger;
    private Deploy_Forge_Connection_Handler $connection_handler;

    public function __construct(Deploy_Forge_Settings $settings, Deploy_Forge_GitHub_API $github_api, Deploy_Forge_Deployment_Manager $deployment_manager, Deploy_Forge_Database $database, Deploy_Forge_Debug_Logger $logger, Deploy_Forge_Connection_Handler $connection_handler)
    {
        $this->settings = $settings;
        $this->github_api = $github_api;
        $this->deployment_manager = $deployment_manager;
        $this->database = $database;
        $this->logger = $logger;
        $this->connection_handler = $connection_handler;

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
        add_action('wp_ajax_deploy_forge_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_deploy_forge_clear_logs', [$this, 'ajax_clear_logs']);

        // Deploy Forge platform connection AJAX handlers
        add_action('wp_ajax_deploy_forge_connect', [$this, 'ajax_connect']);
        add_action('wp_ajax_deploy_forge_disconnect', [$this, 'ajax_disconnect']);
        add_action('wp_ajax_deploy_forge_verify_connection', [$this, 'ajax_verify_connection']);
    }

    /**
     * Override base class log method to use logger instance
     */
    protected function log(string $context, string $message, array $data = []): void
    {
        $this->logger->log($context, $message, $data);
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

        // Enqueue shared AJAX utilities
        wp_enqueue_script(
            'deploy-forge-ajax-utils',
            DEPLOY_FORGE_PLUGIN_URL . 'admin/js/ajax-utilities.js',
            ['jquery'],
            DEPLOY_FORGE_VERSION,
            true
        );

        // Enqueue admin-specific scripts (depends on AJAX utilities)
        wp_enqueue_script(
            'deploy-forge-admin',
            DEPLOY_FORGE_PLUGIN_URL . 'admin/js/admin-scripts.js',
            ['jquery', 'deploy-forge-ajax-utils'],
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
        // Handle callback from Deploy Forge
        if (isset($_GET['action']) && $_GET['action'] === 'df_callback') {
            $connection_token = $_GET['connection_token'] ?? '';
            $returned_nonce = $_GET['nonce'] ?? '';

            if (!empty($connection_token) && !empty($returned_nonce)) {
                $result = $this->connection_handler->handle_callback($connection_token, $returned_nonce);

                if ($result['success']) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid callback parameters.', 'deploy-forge') . '</p></div>';
            }

            // Remove query params from URL to prevent reprocessing
            echo '<script>window.history.replaceState({}, "", "' . esc_url(admin_url('admin.php?page=deploy-forge-settings')) . '");</script>';
        }

        // Save settings if form submitted
        if (isset($_POST['deploy_forge_save_settings'])) {
            // Verify nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'deploy_forge_settings')) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security verification failed. Please try again.', 'deploy-forge') . '</p></div>';
            } else {
                $settings = [
                    'require_manual_approval' => isset($_POST['require_manual_approval']),
                    'create_backups' => isset($_POST['create_backups']),
                    'notification_email' => $_POST['notification_email'] ?? '',
                    'debug_mode' => isset($_POST['debug_mode']),
                ];

                // Merge with existing settings to preserve repo info
                $current = $this->settings->get_all();
                $settings = array_merge($current, $settings);

                $this->settings->save($settings);

                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'deploy-forge') . '</p></div>';
            }
        }

        $current_settings = $this->settings->get_all();
        $webhook_url = $this->settings->get_webhook_url();
        $is_connected = $this->settings->is_connected();
        $connection_data = $this->settings->get_connection_data();
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
        $this->verify_ajax_request('deploy_forge_admin');

        $result = $this->github_api->test_connection();
        $this->handle_api_response($result);
    }

    /**
     * AJAX: Manual deployment
     */
    public function ajax_manual_deploy(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        try {
            $commit_hash = $this->get_post_param('commit_hash');

            if (empty($commit_hash)) {
                // Get latest commit
                $commits = $this->github_api->get_recent_commits(1);
                if (!$commits['success'] || empty($commits['data'])) {
                    $this->send_error(__('Failed to get latest commit', 'deploy-forge'));
                    return;
                }

                // Handle both array and object responses
                $first_commit = is_array($commits['data']) ? $commits['data'][0] : $commits['data'][0];
                $commit_hash = is_object($first_commit) ? $first_commit->sha : $first_commit['sha'];
            }

            // Get commit details
            $commit_result = $this->github_api->get_commit_details($commit_hash);
            if (!$commit_result['success']) {
                $this->send_error(__('Failed to get commit details', 'deploy-forge'));
                return;
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
                $this->send_error(
                    $deployment_result['message'],
                    $deployment_result['error'],
                    [
                        'building_deployment' => [
                            'id' => $deployment_result['building_deployment']->id ?? 0,
                            'commit_hash' => $deployment_result['building_deployment']->commit_hash ?? '',
                            'status' => $deployment_result['building_deployment']->status ?? '',
                            'created_at' => $deployment_result['building_deployment']->created_at ?? '',
                        ],
                    ]
                );
                return;
            }

            $this->log('Admin', 'Deployment result received', [
                'deployment_result' => $deployment_result,
                'type' => gettype($deployment_result),
                'is_truthy' => (bool)$deployment_result,
            ]);

            if ($deployment_result) {
                $this->log('Admin', 'Sending success response', ['deployment_id' => $deployment_result]);
                $this->send_success(
                    ['deployment_id' => $deployment_result],
                    __('Deployment started successfully!', 'deploy-forge')
                );
            } else {
                $this->log('Admin', 'Sending error response - deployment result was falsy');
                $this->send_error(__('Failed to start deployment', 'deploy-forge'));
            }
        } catch (Exception $e) {
            $this->logger->error('Admin', 'Manual deploy exception', ['error' => $e->getMessage()]);
            $this->send_error(sprintf(__('Deployment error: %s', 'deploy-forge'), $e->getMessage()));
        }
    }

    /**
     * AJAX: Get deployment status
     */
    public function ajax_get_status(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $deployment_id = $this->get_post_int('deployment_id');

        if ($deployment_id) {
            $deployment = $this->database->get_deployment($deployment_id);
            $this->send_success(['deployment' => $deployment]);
        } else {
            $stats = $this->database->get_statistics();
            $recent = $this->database->get_recent_deployments(5);
            $this->send_success(['stats' => $stats, 'recent' => $recent]);
        }
    }

    /**
     * AJAX: Rollback deployment
     */
    public function ajax_rollback(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $deployment_id = $this->get_post_int('deployment_id');

        if (!$deployment_id) {
            $this->send_error(__('Invalid deployment ID', 'deploy-forge'));
            return;
        }

        $result = $this->deployment_manager->rollback_deployment($deployment_id);

        if ($result) {
            $this->send_success(null, __('Rollback completed successfully!', 'deploy-forge'));
        } else {
            $this->send_error(__('Rollback failed', 'deploy-forge'));
        }
    }

    /**
     * AJAX: Approve pending deployment (manual approval)
     */
    public function ajax_approve_deployment(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $deployment_id = $this->get_post_int('deployment_id');

        if (!$deployment_id) {
            $this->send_error(__('Invalid deployment ID', 'deploy-forge'));
            return;
        }

        // Get deployment details
        $deployment = $this->database->get_deployment($deployment_id);

        if (!$deployment) {
            $this->send_error(__('Deployment not found', 'deploy-forge'));
            return;
        }

        if ($deployment->status !== 'pending') {
            $this->send_error(__('Only pending deployments can be approved', 'deploy-forge'));
            return;
        }

        // Approve the deployment by triggering the workflow
        $result = $this->deployment_manager->approve_pending_deployment($deployment_id, get_current_user_id());

        if ($result) {
            $this->send_success(null, __('Deployment approved and started successfully!', 'deploy-forge'));
        } else {
            $this->send_error(__('Failed to start deployment', 'deploy-forge'));
        }
    }

    /**
     * AJAX: Cancel deployment
     */
    public function ajax_cancel_deployment(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $deployment_id = $this->get_post_int('deployment_id');

        if (!$deployment_id) {
            $this->send_error(__('Invalid deployment ID', 'deploy-forge'));
            return;
        }

        $result = $this->deployment_manager->cancel_deployment($deployment_id);

        if ($result) {
            $this->send_success(null, __('Deployment cancelled successfully!', 'deploy-forge'));
        } else {
            $this->send_error(__('Failed to cancel deployment. It may have already completed or been cancelled.', 'deploy-forge'));
        }
    }

    /**
     * AJAX: Get recent commits
     */
    public function ajax_get_commits(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $result = $this->github_api->get_recent_commits(10);
        $this->handle_api_response($result);
    }

    /**
     * AJAX: Get user repositories
     */
    public function ajax_get_repos(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $result = $this->github_api->get_user_repositories();

        if ($result['success']) {
            $this->send_success(['repos' => $result['data']]);
        } else {
            $this->send_error($result['message']);
        }
    }

    /**
     * AJAX: Get repository workflows
     * SECURITY: Validates nonce, capability, and sanitizes inputs
     */
    public function ajax_get_workflows(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        // SECURITY: Sanitize and validate input parameters
        $owner = $this->get_post_param('owner');
        $repo = $this->get_post_param('repo');

        if (empty($owner) || empty($repo)) {
            $this->send_error(__('Missing owner or repo parameter', 'deploy-forge'));
            return;
        }

        // Additional validation: Check for valid characters
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $owner) || !preg_match('/^[a-zA-Z0-9_.-]+$/', $repo)) {
            $this->send_error(__('Invalid repository format', 'deploy-forge'));
            return;
        }

        $result = $this->github_api->get_workflows($owner, $repo);

        if ($result['success']) {
            $this->send_success([
                'workflows' => $result['workflows'],
                'total_count' => $result['total_count']
            ]);
        } else {
            $this->send_error($result['message']);
        }
    }

    /**
     * AJAX: Generate webhook secret
     */
    public function ajax_generate_secret(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $secret = $this->settings->generate_webhook_secret();

        if ($secret) {
            $this->send_success(['secret' => $secret]);
        } else {
            $this->send_error(__('Failed to generate secret', 'deploy-forge'));
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
        $this->verify_ajax_request('deploy_forge_admin');

        $lines = $this->get_post_int('lines', 100);
        $logs = $this->logger->get_recent_logs($lines);
        $size = $this->logger->get_log_size();

        $this->send_success([
            'logs' => $logs,
            'size' => $size,
        ]);
    }

    /**
     * AJAX: Clear debug logs
     */
    public function ajax_clear_logs(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $result = $this->logger->clear_logs();

        if ($result) {
            $this->send_success(null, __('Logs cleared successfully', 'deploy-forge'));
        } else {
            $this->send_error(__('Failed to clear logs', 'deploy-forge'));
        }
    }

    /**
     * AJAX: Initiate connection to Deploy Forge
     */
    public function ajax_connect(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $result = $this->connection_handler->initiate_connection();

        if ($result['success']) {
            $this->send_success([
                'redirect_url' => $result['redirect_url'],
            ], __('Redirecting to Deploy Forge...', 'deploy-forge'));
        } else {
            $this->send_error($result['message']);
        }
    }

    /**
     * AJAX: Disconnect from Deploy Forge
     */
    public function ajax_disconnect(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $result = $this->connection_handler->disconnect();

        if ($result['success']) {
            $this->send_success(null, $result['message']);
        } else {
            $this->send_error($result['message']);
        }
    }

    /**
     * AJAX: Verify Deploy Forge connection
     */
    public function ajax_verify_connection(): void
    {
        $this->verify_ajax_request('deploy_forge_admin');

        $result = $this->connection_handler->verify_connection();

        if ($result['success']) {
            $this->send_success([
                'connected' => $result['connected'],
                'site_id' => $result['site_id'] ?? '',
                'domain' => $result['domain'] ?? '',
                'status' => $result['status'] ?? 'active',
            ]);
        } else {
            $this->send_error($result['message']);
        }
    }
}
