<?php

/**
 * Plugin Name: Deploy Forge
 * Plugin URI: https://github.com/jordanburch101/deploy-forge
 * Description: Automates theme deployment from GitHub repositories via Deploy Forge platform
 * Version: 1.0.2
 * Author: Jordan Burch
 * Author URI: https://jordanburch.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: deploy-forge
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Update URI: https://updates.getdeployforge.com
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DEPLOY_FORGE_VERSION', '1.0.2');
define('DEPLOY_FORGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DEPLOY_FORGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DEPLOY_FORGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Deploy_Forge
{

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Database instance
     */
    public $database;

    /**
     * GitHub API instance
     */
    public $github_api;

    /**
     * Deployment Manager instance
     */
    public $deployment_manager;

    /**
     * Webhook Handler instance
     */
    public $webhook_handler;

    /**
     * Settings instance
     */
    public $settings;

    /**
     * Admin Pages instance
     */
    public $admin_pages;

    /**
     * Update Checker instance
     */
    public $update_checker;

    /**
     * Get singleton instance
     */
    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies(): void
    {
        // Core classes
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-database.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-settings.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-debug-logger.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-connection-handler.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-github-api.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-deployment-manager.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-update-checker.php';

        // Utility classes
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-ajax-handler-base.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-data-formatter.php';

        // Admin classes
        if (is_admin()) {
            require_once DEPLOY_FORGE_PLUGIN_DIR . 'admin/class-admin-pages.php';
        }

        // Initialize instances
        $this->database = new Deploy_Forge_Database();
        $this->settings = new Deploy_Forge_Settings();
        $logger = new Deploy_Forge_Debug_Logger($this->settings);
        $connection_handler = new Deploy_Forge_Connection_Handler($this->settings, $logger);
        $this->github_api = new Deploy_Forge_GitHub_API($this->settings, $logger);
        $this->deployment_manager = new Deploy_Forge_Deployment_Manager($this->settings, $this->github_api, $this->database, $logger);
        $this->webhook_handler = new Deploy_Forge_Webhook_Handler($this->settings, $this->github_api, $logger, $this->deployment_manager);

        if (is_admin()) {
            $this->admin_pages = new Deploy_Forge_Admin_Pages($this->settings, $this->github_api, $this->deployment_manager, $this->database, $logger, $connection_handler);
        }

        // Initialize update checker
        $this->init_update_checker();
    }

    /**
     * Initialize the plugin update checker
     */
    private function init_update_checker(): void
    {
        // API key is optional for now (no licensing system yet)
        // Updates are currently public - no authentication required
        $api_key = '';

        // Initialize update checker
        $this->update_checker = new Deploy_Forge_Update_Checker(
            __FILE__, // Plugin file path
            DEPLOY_FORGE_VERSION, // Current version
            'https://updates.getdeployforge.com', // Update server URL
            $api_key // API key (empty for now - public updates)
        );
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('rest_api_init', [$this->webhook_handler, 'register_routes']);

        // Register WP-Cron handler for async deployment processing
        add_action('deploy_forge_process_queued_deployment', [$this, 'process_queued_deployment']);
        add_action('deploy_forge_process_clone_deployment', [$this, 'process_clone_deployment'], 10, 2);

        // Admin notices for update system
        if (is_admin()) {
            add_action('admin_notices', [$this, 'update_system_notices']);
        }
    }

    /**
     * Plugin activation
     */
    public function activate(): void
    {
        $this->database->create_tables();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void
    {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('deploy_forge_check_build_status');
        wp_clear_scheduled_hook('deploy_forge_process_queued_deployment');
        wp_clear_scheduled_hook('deploy_forge_process_clone_deployment');

        // Release any locks
        $this->database->release_deployment_lock();

        flush_rewrite_rules();
    }

    /**
     * Process queued deployment (WP-Cron callback)
     * Called when webhook uses WP-Cron fallback for async processing
     */
    public function process_queued_deployment(int $deployment_id): void
    {
        // Check if deployment lock exists
        $locked_deployment = $this->database->get_deployment_lock();

        if ($locked_deployment && $locked_deployment !== $deployment_id) {
            // Another deployment is currently processing, reschedule this one
            wp_schedule_single_event(time() + 60, 'deploy_forge_process_queued_deployment', [$deployment_id]);
            return;
        }

        // Set lock for this deployment (5 minute timeout)
        $this->database->set_deployment_lock($deployment_id, 300);

        try {
            // Process the deployment
            $this->deployment_manager->process_successful_build($deployment_id);
        } finally {
            // Always release the lock when done (or on error)
            $this->database->release_deployment_lock();
        }
    }

    /**
     * Process clone deployment (WP-Cron callback)
     * Called when webhook uses WP-Cron fallback for direct clone deployments
     */
    public function process_clone_deployment(int $deployment_id, string $remote_deployment_id): void
    {
        $this->deployment_manager->process_clone_deployment($deployment_id, $remote_deployment_id);
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'deploy-forge',
            false,
            dirname(DEPLOY_FORGE_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Display admin notices about update system status
     */
    public function update_system_notices(): void
    {
        // API key not required for updates currently
        // This method is kept for future use when licensing is implemented
    }
}

/**
 * Initialize the plugin
 */
function deploy_forge(): Deploy_Forge
{
    return Deploy_Forge::get_instance();
}

// Start the plugin
deploy_forge();
