<?php
/**
 * Admin pages class
 * Handles WordPress admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_Deploy_Admin_Pages {

    private GitHub_Deploy_Settings $settings;
    private GitHub_API $github_api;
    private GitHub_Deployment_Manager $deployment_manager;
    private GitHub_Deploy_Database $database;
    private GitHub_Deploy_Debug_Logger $logger;

    public function __construct(GitHub_Deploy_Settings $settings, GitHub_API $github_api, GitHub_Deployment_Manager $deployment_manager, GitHub_Deploy_Database $database, GitHub_Deploy_Debug_Logger $logger) {
        $this->settings = $settings;
        $this->github_api = $github_api;
        $this->deployment_manager = $deployment_manager;
        $this->database = $database;
        $this->logger = $logger;

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);

        // AJAX handlers
        add_action('wp_ajax_github_deploy_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_github_deploy_manual_deploy', [$this, 'ajax_manual_deploy']);
        add_action('wp_ajax_github_deploy_get_status', [$this, 'ajax_get_status']);
        add_action('wp_ajax_github_deploy_rollback', [$this, 'ajax_rollback']);
        add_action('wp_ajax_github_deploy_cancel', [$this, 'ajax_cancel_deployment']);
        add_action('wp_ajax_github_deploy_get_commits', [$this, 'ajax_get_commits']);
        add_action('wp_ajax_github_deploy_get_repos', [$this, 'ajax_get_repos']);
        add_action('wp_ajax_github_deploy_get_workflows', [$this, 'ajax_get_workflows']);
        add_action('wp_ajax_github_deploy_generate_secret', [$this, 'ajax_generate_secret']);
        add_action('wp_ajax_github_deploy_get_logs', [$this, 'ajax_get_logs']);
        add_action('wp_ajax_github_deploy_clear_logs', [$this, 'ajax_clear_logs']);
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('GitHub Deploy', 'github-auto-deploy'),
            __('GitHub Deploy', 'github-auto-deploy'),
            'manage_options',
            'github-deploy',
            [$this, 'render_dashboard_page'],
            'dashicons-update',
            80
        );

        add_submenu_page(
            'github-deploy',
            __('Dashboard', 'github-auto-deploy'),
            __('Dashboard', 'github-auto-deploy'),
            'manage_options',
            'github-deploy',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'github-deploy',
            __('Settings', 'github-auto-deploy'),
            __('Settings', 'github-auto-deploy'),
            'manage_options',
            'github-deploy-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'github-deploy',
            __('Deployment History', 'github-auto-deploy'),
            __('History', 'github-auto-deploy'),
            'manage_options',
            'github-deploy-history',
            [$this, 'render_history_page']
        );

        add_submenu_page(
            'github-deploy',
            __('Debug Logs', 'github-auto-deploy'),
            __('Debug Logs', 'github-auto-deploy'),
            'manage_options',
            'github-deploy-logs',
            [$this, 'render_logs_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on our plugin pages
        if (strpos($hook, 'github-deploy') === false) {
            return;
        }

        wp_enqueue_style(
            'github-deploy-admin',
            GITHUB_DEPLOY_PLUGIN_URL . 'admin/css/admin-styles.css',
            [],
            GITHUB_DEPLOY_VERSION
        );

        wp_enqueue_script(
            'github-deploy-admin',
            GITHUB_DEPLOY_PLUGIN_URL . 'admin/js/admin-scripts.js',
            ['jquery'],
            GITHUB_DEPLOY_VERSION,
            true
        );

        wp_localize_script('github-deploy-admin', 'githubDeployAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('github_deploy_admin'),
            'strings' => [
                'confirmRollback' => __('Are you sure you want to rollback to this deployment? This will restore the previous theme files.', 'github-auto-deploy'),
                'confirmDeploy' => __('Are you sure you want to start a deployment?', 'github-auto-deploy'),
                'confirmCancel' => __('Are you sure you want to cancel this deployment? The GitHub Actions workflow will be stopped.', 'github-auto-deploy'),
                'deploying' => __('Deploying...', 'github-auto-deploy'),
                'testing' => __('Testing connection...', 'github-auto-deploy'),
                'cancelling' => __('Cancelling...', 'github-auto-deploy'),
            ],
        ]);
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting('github_deploy_settings', 'github_deploy_settings');
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page(): void {
        $stats = $this->database->get_statistics();
        $recent_deployments = $this->database->get_recent_deployments(5);
        $is_configured = $this->settings->is_configured();

        include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/dashboard-page.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        // Save settings if form submitted
        if (isset($_POST['github_deploy_save_settings'])) {
            // Verify nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'github_deploy_settings')) {
                echo '<div class="notice notice-error"><p>' . esc_html__('Security verification failed. Please try again.', 'github-auto-deploy') . '</p></div>';
            } else {
                $settings = [
                    'github_repo_owner' => $_POST['github_repo_owner'] ?? '',
                    'github_repo_name' => $_POST['github_repo_name'] ?? '',
                    'github_branch' => $_POST['github_branch'] ?? 'main',
                    'github_workflow_name' => $_POST['github_workflow_name'] ?? 'build-theme.yml',
                    'target_theme_directory' => $_POST['target_theme_directory'] ?? '',
                    'auto_deploy_enabled' => isset($_POST['auto_deploy_enabled']),
                    'require_manual_approval' => isset($_POST['require_manual_approval']),
                    'create_backups' => isset($_POST['create_backups']),
                    'notification_email' => $_POST['notification_email'] ?? '',
                    'webhook_secret' => $_POST['webhook_secret'] ?? '',
                    'debug_mode' => isset($_POST['debug_mode']),
                ];

                $this->settings->save($settings);

                // Save token separately
                if (!empty($_POST['github_token'])) {
                    $this->settings->set_github_token($_POST['github_token']);
                }

                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully!', 'github-auto-deploy') . '</p></div>';
            }
        }

        $current_settings = $this->settings->get_all();
        $webhook_url = $this->settings->get_webhook_url();

        include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/settings-page.php';
    }

    /**
     * Render history page
     */
    public function render_history_page(): void {
        $per_page = 20;
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($paged - 1) * $per_page;

        $deployments = $this->database->get_recent_deployments($per_page, $offset);
        $total_deployments = $this->database->get_deployment_count();
        $total_pages = ceil($total_deployments / $per_page);

        include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/history-page.php';
    }

    /**
     * AJAX: Test GitHub connection
     */
    public function ajax_test_connection(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
        }

        $result = $this->github_api->test_connection();
        wp_send_json($result);
    }

    /**
     * AJAX: Manual deployment
     */
    public function ajax_manual_deploy(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
        }

        $commit_hash = sanitize_text_field($_POST['commit_hash'] ?? '');

        if (empty($commit_hash)) {
            // Get latest commit
            $commits = $this->github_api->get_recent_commits(1);
            if (!$commits['success'] || empty($commits['data'])) {
                wp_send_json_error(['message' => __('Failed to get latest commit', 'github-auto-deploy')]);
            }
            $commit_hash = $commits['data'][0]->sha;
        }

        // Get commit details
        $commit_result = $this->github_api->get_commit_details($commit_hash);
        if (!$commit_result['success']) {
            wp_send_json_error(['message' => __('Failed to get commit details', 'github-auto-deploy')]);
        }

        $commit_data = $commit_result['data'];
        $deployment_result = $this->deployment_manager->start_deployment($commit_hash, 'manual', get_current_user_id(), [
            'commit_message' => $commit_data->commit->message ?? '',
            'commit_author' => $commit_data->commit->author->name ?? '',
            'commit_date' => $commit_data->commit->author->date ?? current_time('mysql'),
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

        if ($deployment_result) {
            wp_send_json_success([
                'message' => __('Deployment started successfully!', 'github-auto-deploy'),
                'deployment_id' => $deployment_result,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to start deployment', 'github-auto-deploy')]);
        }
    }

    /**
     * AJAX: Get deployment status
     */
    public function ajax_get_status(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
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
    public function ajax_rollback(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
        }

        $deployment_id = intval($_POST['deployment_id'] ?? 0);

        if (!$deployment_id) {
            wp_send_json_error(['message' => __('Invalid deployment ID', 'github-auto-deploy')]);
        }

        $result = $this->deployment_manager->rollback_deployment($deployment_id);

        if ($result) {
            wp_send_json_success(['message' => __('Rollback completed successfully!', 'github-auto-deploy')]);
        } else {
            wp_send_json_error(['message' => __('Rollback failed', 'github-auto-deploy')]);
        }
    }

    /**
     * AJAX: Cancel deployment
     */
    public function ajax_cancel_deployment(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
        }

        $deployment_id = intval($_POST['deployment_id'] ?? 0);

        if (!$deployment_id) {
            wp_send_json_error(['message' => __('Invalid deployment ID', 'github-auto-deploy')]);
        }

        $result = $this->deployment_manager->cancel_deployment($deployment_id);

        if ($result) {
            wp_send_json_success(['message' => __('Deployment cancelled successfully!', 'github-auto-deploy')]);
        } else {
            wp_send_json_error(['message' => __('Failed to cancel deployment. It may have already completed or been cancelled.', 'github-auto-deploy')]);
        }
    }

    /**
     * AJAX: Get recent commits
     */
    public function ajax_get_commits(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
        }

        $result = $this->github_api->get_recent_commits(10);
        wp_send_json($result);
    }

    /**
     * AJAX: Get user repositories
     */
    public function ajax_get_repos(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
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
     */
    public function ajax_get_workflows(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
        }

        $owner = sanitize_text_field($_POST['owner'] ?? '');
        $repo = sanitize_text_field($_POST['repo'] ?? '');

        if (empty($owner) || empty($repo)) {
            wp_send_json_error(['message' => __('Missing owner or repo parameter', 'github-auto-deploy')]);
        }

        $result = $this->github_api->get_repository_workflows($owner, $repo);

        if ($result['success']) {
            wp_send_json_success(['workflows' => $result['data']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * AJAX: Generate webhook secret
     */
    public function ajax_generate_secret(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
        }

        $secret = $this->settings->generate_webhook_secret();

        if ($secret) {
            wp_send_json_success(['secret' => $secret]);
        } else {
            wp_send_json_error(['message' => __('Failed to generate secret', 'github-auto-deploy')]);
        }
    }

    /**
     * Render debug logs page
     */
    public function render_logs_page(): void {
        if (!$this->logger->is_enabled()) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Debug Logs', 'github-auto-deploy') . '</h1>';
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__('Debug mode is not enabled. Enable it in ', 'github-auto-deploy');
            echo '<a href="' . esc_url(admin_url('admin.php?page=github-deploy-settings')) . '">';
            echo esc_html__('Settings', 'github-auto-deploy');
            echo '</a> to start logging.';
            echo '</p></div>';
            echo '</div>';
            return;
        }

        include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/logs-page.php';
    }

    /**
     * AJAX: Get debug logs
     */
    public function ajax_get_logs(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
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
    public function ajax_clear_logs(): void {
        check_ajax_referer('github_deploy_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'github-auto-deploy')]);
        }

        $result = $this->logger->clear_logs();

        if ($result) {
            wp_send_json_success(['message' => __('Logs cleared successfully', 'github-auto-deploy')]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear logs', 'github-auto-deploy')]);
        }
    }
}
