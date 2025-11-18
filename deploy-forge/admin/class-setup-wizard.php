<?php

/**
 * Setup Wizard class
 * Handles multi-step onboarding wizard for initial plugin configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Setup_Wizard extends Deploy_Forge_Ajax_Handler_Base
{

    private Deploy_Forge_Settings $settings;
    private Deploy_Forge_GitHub_API $github_api;
    private Deploy_Forge_Database $database;
    private Deploy_Forge_Debug_Logger $logger;
    private Deploy_Forge_App_Connector $app_connector;

    public function __construct(
        Deploy_Forge_Settings $settings,
        Deploy_Forge_GitHub_API $github_api,
        Deploy_Forge_Database $database,
        Deploy_Forge_Debug_Logger $logger,
        Deploy_Forge_App_Connector $app_connector
    ) {
        $this->settings = $settings;
        $this->github_api = $github_api;
        $this->database = $database;
        $this->logger = $logger;
        $this->app_connector = $app_connector;

        // Register wizard page
        add_action('admin_menu', [$this, 'add_wizard_page']);

        // Enqueue assets only on wizard page
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_deploy_forge_wizard_get_repos', [$this, 'ajax_get_repos']);
        add_action('wp_ajax_deploy_forge_wizard_get_branches', [$this, 'ajax_get_branches']);
        add_action('wp_ajax_deploy_forge_wizard_get_workflows', [$this, 'ajax_get_workflows']);
        add_action('wp_ajax_deploy_forge_wizard_validate_repo', [$this, 'ajax_validate_repo']);
        add_action('wp_ajax_deploy_forge_wizard_bind_repo', [$this, 'ajax_bind_repo']);
        add_action('wp_ajax_deploy_forge_wizard_save_step', [$this, 'ajax_save_step']);
        add_action('wp_ajax_deploy_forge_wizard_complete', [$this, 'ajax_complete']);
        add_action('wp_ajax_deploy_forge_wizard_skip', [$this, 'ajax_skip']);

        // Redirect to wizard on activation if needed
        add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);
    }

    /**
     * Override base class log method to use logger instance
     */
    protected function log(string $context, string $message, array $data = []): void
    {
        $this->logger->log($context, $message, $data);
    }

    /**
     * Check if wizard should be shown
     */
    public function should_show_wizard(): bool
    {
        // Force show if explicitly requested via URL parameter
        if (isset($_GET['show_wizard']) && $_GET['show_wizard'] == '1') {
            return true;
        }

        // Reset wizard state if requested (for testing/troubleshooting)
        if (isset($_GET['reset_wizard']) && $_GET['reset_wizard'] == '1' && current_user_can('manage_options')) {
            delete_option('deploy_forge_wizard_completed');
            delete_option('deploy_forge_wizard_skipped');
            delete_option('deploy_forge_wizard_completed_at');
            for ($i = 1; $i <= 6; $i++) {
                delete_transient('deploy_forge_wizard_step_' . $i);
            }
            $this->logger->log('Setup_Wizard', 'Wizard state reset via URL parameter');
            wp_safe_redirect(admin_url('admin.php?page=deploy-forge-wizard'));
            exit;
        }

        // Don't show if already completed
        if (get_option('deploy_forge_wizard_completed')) {
            return false;
        }

        // Don't show if skipped
        if (get_option('deploy_forge_wizard_skipped')) {
            return false;
        }

        // Don't show if plugin is already configured
        $api_key = $this->settings->get_api_key();
        $repo_owner = $this->settings->get('github_repo_owner');
        $repo_name = $this->settings->get('github_repo_name');

        if (!empty($api_key) && !empty($repo_owner) && !empty($repo_name)) {
            return false;
        }

        return true;
    }

    /**
     * Check if wizard should be resumed
     */
    public function can_resume_wizard(): bool
    {
        // Check if there's saved progress
        for ($i = 1; $i <= 6; $i++) {
            if (get_transient('deploy_forge_wizard_step_' . $i)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add wizard page to admin menu (hidden)
     */
    public function add_wizard_page(): void
    {
        add_submenu_page(
            null, // No parent = hidden from menu
            __('Setup Wizard', 'deploy-forge'),
            __('Setup Wizard', 'deploy-forge'),
            'manage_options',
            'deploy-forge-wizard',
            [$this, 'render_wizard']
        );
    }

    /**
     * Maybe redirect to wizard on first activation
     */
    public function maybe_redirect_to_wizard(): void
    {
        // Check for activation redirect flag
        if (get_transient('deploy_forge_activation_redirect')) {
            delete_transient('deploy_forge_activation_redirect');

            // Only redirect if wizard should be shown
            if ($this->should_show_wizard()) {
                wp_safe_redirect(admin_url('admin.php?page=deploy-forge-wizard'));
                exit;
            }
        }
    }

    /**
     * Enqueue wizard assets
     */
    public function enqueue_assets($hook): void
    {
        // Only load on wizard page
        if ($hook !== 'admin_page_deploy-forge-wizard') {
            return;
        }

        // Select2 - use unpkg CDN which is more reliable
        wp_enqueue_style(
            'select2',
            'https://unpkg.com/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );

        wp_enqueue_script(
            'select2',
            'https://unpkg.com/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0',
            false  // Load in header, not footer
        );

        // Enqueue shared styles first
        wp_enqueue_style(
            'deploy-forge-shared',
            DEPLOY_FORGE_PLUGIN_URL . 'admin/css/shared-styles.css',
            [],
            DEPLOY_FORGE_VERSION
        );

        // Wizard CSS (depends on shared styles)
        wp_enqueue_style(
            'deploy-forge-wizard',
            DEPLOY_FORGE_PLUGIN_URL . 'admin/css/setup-wizard.css',
            ['deploy-forge-shared', 'select2'],
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

        // Wizard JS (depends on AJAX utilities and Select2)
        wp_enqueue_script(
            'deploy-forge-wizard',
            DEPLOY_FORGE_PLUGIN_URL . 'admin/js/setup-wizard.js',
            ['jquery', 'select2', 'deploy-forge-ajax-utils'],
            DEPLOY_FORGE_VERSION,
            true
        );

        // Localize script
        wp_localize_script('deploy-forge-wizard', 'deployForgeWizard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('deploy_forge_wizard'),
            'currentStep' => isset($_GET['step']) ? intval($_GET['step']) : 1,
            'isConnected' => $this->app_connector->is_connected(),
            'dashboardUrl' => admin_url('admin.php?page=deploy-forge'),
            'strings' => [
                'error' => __('An error occurred. Please try again.', 'deploy-forge'),
                'loading' => __('Loading...', 'deploy-forge'),
                'skipConfirm' => __('Are you sure you want to skip setup? You can configure settings manually later.', 'deploy-forge'),
                'exitConfirm' => __('Exit setup wizard? Your progress will be saved.', 'deploy-forge'),
            ]
        ]);
    }

    /**
     * Render wizard page
     */
    public function render_wizard(): void
    {
        $current_step = isset($_GET['step']) ? intval($_GET['step']) : 1;
        $current_step = max(1, min(6, $current_step)); // Clamp between 1-6

        $is_connected = $this->app_connector->is_connected();
        $just_connected = isset($_GET['connected']) && $_GET['connected'] === '1';

        include DEPLOY_FORGE_PLUGIN_DIR . 'templates/setup-wizard/wizard-container.php';
    }

    /**
     * AJAX: Get repositories
     */
    public function ajax_get_repos(): void
    {
        $this->verify_ajax_request('deploy_forge_wizard');

        $this->log('Setup_Wizard', 'Fetching repositories for wizard');

        $repos = $this->github_api->get_installation_repositories();

        if (!$repos['success']) {
            $this->logger->error('Setup_Wizard', 'Failed to fetch repositories', $repos);
            $this->send_error($repos['message'] ?? __('Failed to fetch repositories', 'deploy-forge'));
            return;
        }

        // Format for Select2 using Data_Formatter
        $formatted = Deploy_Forge_Data_Formatter::format_repositories($repos['data']);

        $this->log('Setup_Wizard', 'Successfully fetched ' . count($formatted) . ' repositories');

        $this->send_success(['repositories' => $formatted]);
    }

    /**
     * AJAX: Get branches for repository
     */
    public function ajax_get_branches(): void
    {
        $this->verify_ajax_request('deploy_forge_wizard');

        $repo_full_name = $this->get_post_param('repo_full_name');

        if (empty($repo_full_name)) {
            $this->send_error(__('Repository name required', 'deploy-forge'));
            return;
        }

        $this->log('Setup_Wizard', 'Fetching branches for ' . $repo_full_name);

        // Temporarily set repo to fetch branches
        list($owner, $name) = explode('/', $repo_full_name);
        $current_owner = $this->settings->get('github_repo_owner');
        $current_name = $this->settings->get('github_repo_name');

        $this->settings->update('github_repo_owner', $owner);
        $this->settings->update('github_repo_name', $name);

        $branches = $this->github_api->get_branches();

        // Restore original settings
        $this->settings->update('github_repo_owner', $current_owner);
        $this->settings->update('github_repo_name', $current_name);

        if (!$branches['success']) {
            $this->logger->error('Setup_Wizard', 'Failed to fetch branches', $branches);
            $this->send_error($branches['message'] ?? __('Failed to fetch branches', 'deploy-forge'));
            return;
        }

        $this->send_success(['branches' => $branches['data']]);
    }

    /**
     * AJAX: Get workflows for repository
     */
    public function ajax_get_workflows(): void
    {
        $this->verify_ajax_request('deploy_forge_wizard');

        $repo_owner = $this->get_post_param('repo_owner');
        $repo_name = $this->get_post_param('repo_name');

        if (empty($repo_owner) || empty($repo_name)) {
            $this->send_error(__('Repository information required', 'deploy-forge'));
            return;
        }

        $this->log('Setup_Wizard', 'Fetching workflows for ' . $repo_owner . '/' . $repo_name);

        $result = $this->github_api->get_workflows($repo_owner, $repo_name);

        if (!$result['success']) {
            $this->logger->error('Setup_Wizard', 'Failed to fetch workflows', $result);
            $this->send_error($result['message'] ?? __('Failed to fetch workflows', 'deploy-forge'));
            return;
        }

        $this->send_success([
            'workflows' => $result['workflows'],
            'total_count' => $result['total_count']
        ]);
    }

    /**
     * AJAX: Validate repository access
     */
    public function ajax_validate_repo(): void
    {
        $this->verify_ajax_request('deploy_forge_wizard');

        $repo_full_name = $this->get_post_param('repo_full_name');

        if (empty($repo_full_name)) {
            $this->send_error(__('Repository name required', 'deploy-forge'));
            return;
        }

        // Try to fetch repo details
        list($owner, $name) = explode('/', $repo_full_name);

        $current_owner = $this->settings->get('github_repo_owner');
        $current_name = $this->settings->get('github_repo_name');

        $this->settings->update('github_repo_owner', $owner);
        $this->settings->update('github_repo_name', $name);

        $result = $this->github_api->test_connection();

        // Restore original settings
        $this->settings->update('github_repo_owner', $current_owner);
        $this->settings->update('github_repo_name', $current_name);

        if (!$result['success']) {
            $this->send_error(__('Cannot access repository', 'deploy-forge'));
            return;
        }

        $this->send_success(null, __('Repository accessible', 'deploy-forge'));
    }

    /**
     * AJAX: Bind repository to backend
     */
    public function ajax_bind_repo(): void
    {
        $this->verify_ajax_request('deploy_forge_wizard');

        $owner = $this->get_post_param('owner');
        $name = $this->get_post_param('name');
        $default_branch = $this->get_post_param('default_branch', 'main');

        if (empty($owner) || empty($name)) {
            $this->send_error(__('Invalid repository data', 'deploy-forge'));
            return;
        }

        $this->log('Setup_Wizard', 'Binding repository', [
            'repo' => $owner . '/' . $name,
            'branch' => $default_branch,
        ]);

        // During wizard, allow re-binding by unbinding first if needed
        if ($this->settings->is_repo_bound()) {
            $this->log('Setup_Wizard', 'Repository already bound, unbinding first to allow wizard re-selection');

            // Get GitHub data and clear bind status
            $github_data = $this->settings->get_github_data();
            $github_data['repo_bound'] = false;
            unset($github_data['selected_repo_name']);
            unset($github_data['selected_repo_full_name']);
            unset($github_data['selected_repo_default_branch']);
            unset($github_data['bound_at']);
            $this->settings->set_github_data($github_data);
        }

        $result = $this->settings->bind_repository($owner, $name, $default_branch);

        if ($result) {
            $this->log('Setup_Wizard', 'Repository bound successfully');

            $this->send_success(
                [
                    'repo' => [
                        'owner' => $owner,
                        'name' => $name,
                        'full_name' => $owner . '/' . $name,
                        'default_branch' => $default_branch,
                    ],
                ],
                sprintf(
                    __('Repository %s bound successfully', 'deploy-forge'),
                    $owner . '/' . $name
                )
            );
        } else {
            $this->logger->error('Setup_Wizard', 'Failed to bind repository');
            $this->send_error(__('Failed to bind repository', 'deploy-forge'));
        }
    }

    /**
     * AJAX: Save step data
     */
    public function ajax_save_step(): void
    {
        $this->verify_ajax_request('deploy_forge_wizard');

        $step = $this->get_post_int('step');
        $data = $_POST['data'] ?? [];

        if ($step < 1 || $step > 6) {
            $this->send_error(__('Invalid step', 'deploy-forge'));
            return;
        }

        // Sanitize step data
        $sanitized = $this->sanitize_step_data($step, $data);

        // Save to transient
        set_transient('deploy_forge_wizard_step_' . $step, $sanitized, HOUR_IN_SECONDS);

        $this->log('Setup_Wizard', 'Saved step ' . $step . ' data', $sanitized);

        $this->send_success();
    }

    /**
     * AJAX: Complete wizard
     */
    public function ajax_complete(): void
    {
        $this->verify_ajax_request('deploy_forge_wizard');

        $this->log('Setup_Wizard', 'Completing wizard');

        // Gather all step data
        $wizard_data = [];
        for ($i = 1; $i <= 6; $i++) {
            $step_data = get_transient('deploy_forge_wizard_step_' . $i);
            if ($step_data) {
                $wizard_data = array_merge($wizard_data, $step_data);
            }
        }

        // Save final settings
        if (!empty($wizard_data)) {
            // IMPORTANT: Merge with existing settings to preserve webhook_secret and other values
            // that were set during OAuth (Step 2) but aren't in the wizard step data
            $current_settings = $this->settings->get_all();
            $final_settings = array_merge($current_settings, $wizard_data);

            $this->settings->save($final_settings);
            $this->log('Setup_Wizard', 'Saved wizard configuration', $final_settings);
        }

        // Mark wizard as completed
        update_option('deploy_forge_wizard_completed', true);
        update_option('deploy_forge_wizard_completed_at', current_time('mysql'));

        // Clean up transients
        for ($i = 1; $i <= 6; $i++) {
            delete_transient('deploy_forge_wizard_step_' . $i);
        }

        $this->log('Setup_Wizard', 'Wizard completed successfully');

        $this->send_success([
            'redirect' => admin_url('admin.php?page=deploy-forge&wizard_completed=1')
        ]);
    }

    /**
     * AJAX: Skip wizard
     */
    public function ajax_skip(): void
    {
        $this->verify_ajax_request('deploy_forge_wizard');

        update_option('deploy_forge_wizard_skipped', true);

        $this->log('Setup_Wizard', 'Wizard skipped by user');

        $this->send_success([
            'redirect' => admin_url('admin.php?page=deploy-forge-settings&wizard_skipped=1')
        ]);
    }

    /**
     * Sanitize step data
     */
    private function sanitize_step_data(int $step, array $data): array
    {
        $sanitized = [];

        switch ($step) {
            case 2: // Connect to GitHub
                // No data to save, connection handled by OAuth
                break;

            case 3: // Select Repository
                if (!empty($data['repo_owner'])) {
                    $sanitized['github_repo_owner'] = sanitize_text_field($data['repo_owner']);
                }
                if (!empty($data['repo_name'])) {
                    $sanitized['github_repo_name'] = sanitize_text_field($data['repo_name']);
                }
                if (!empty($data['branch'])) {
                    $sanitized['github_branch'] = sanitize_text_field($data['branch']);
                }
                break;

            case 4: // Deployment Method
                if (!empty($data['deployment_method'])) {
                    $method = sanitize_text_field($data['deployment_method']);
                    if (in_array($method, ['github_actions', 'direct_clone'])) {
                        $sanitized['deployment_method'] = $method;
                    }
                }
                if (!empty($data['workflow_name'])) {
                    $sanitized['github_workflow_name'] = sanitize_text_field($data['workflow_name']);
                }
                break;

            case 5: // Deployment Options
                $sanitized['auto_deploy_enabled'] = !empty($data['auto_deploy_enabled']);
                $sanitized['require_manual_approval'] = !empty($data['require_manual_approval']);
                $sanitized['create_backups'] = !empty($data['create_backups']);

                if (!empty($data['webhook_enabled'])) {
                    // Generate webhook secret if not exists
                    $current_secret = $this->settings->get('webhook_secret');
                    if (empty($current_secret)) {
                        $sanitized['webhook_secret'] = bin2hex(random_bytes(32));
                    }
                }
                break;
        }

        return $sanitized;
    }
}
