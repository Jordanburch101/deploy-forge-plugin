<?php
/**
 * Plugin Name: Deploy Forge
 * Plugin URI: https://github.com/jordanburch101/deploy-forge
 * Description: Automates theme deployment from GitHub repositories using GitHub Actions
 * Version: 1.0.0
 * Author: Jordan Burch
 * Author URI: https://jordanburch.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: deploy-forge
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DEPLOY_FORGE_VERSION', '1.0.0');
define('DEPLOY_FORGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DEPLOY_FORGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DEPLOY_FORGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class Deploy_Forge {

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
     * Setup Wizard instance
     */
    public $setup_wizard;

    /**
     * Get singleton instance
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies(): void {
        // Core classes
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-database.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-settings.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-debug-logger.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-github-api.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-deployment-manager.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-github-app-connector.php';

        // Utility classes
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-ajax-handler-base.php';
        require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-data-formatter.php';

        // Admin classes
        if (is_admin()) {
            require_once DEPLOY_FORGE_PLUGIN_DIR . 'admin/class-admin-pages.php';
            require_once DEPLOY_FORGE_PLUGIN_DIR . 'admin/class-setup-wizard.php';
        }

        // Initialize instances
        $this->database = new Deploy_Forge_Database();
        $this->settings = new Deploy_Forge_Settings();
        $logger = new Deploy_Forge_Debug_Logger($this->settings);
        $app_connector = new Deploy_Forge_App_Connector($this->settings, $logger);
        $this->github_api = new Deploy_Forge_GitHub_API($this->settings, $logger);
        $this->deployment_manager = new Deploy_Forge_Deployment_Manager($this->settings, $this->github_api, $this->database, $logger);
        $this->webhook_handler = new Deploy_Forge_Webhook_Handler($this->settings, $this->github_api, $logger, $this->deployment_manager);

        if (is_admin()) {
            $this->admin_pages = new Deploy_Forge_Admin_Pages($this->settings, $this->github_api, $this->deployment_manager, $this->database, $logger, $app_connector);
            $this->setup_wizard = new Deploy_Forge_Setup_Wizard($this->settings, $this->github_api, $this->database, $logger, $app_connector);
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('rest_api_init', [$this->webhook_handler, 'register_routes']);
    }

    /**
     * Plugin activation
     */
    public function activate(): void {
        $this->database->create_tables();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('deploy_forge_check_build_status');
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'deploy-forge',
            false,
            dirname(DEPLOY_FORGE_PLUGIN_BASENAME) . '/languages'
        );
    }
}

/**
 * Initialize the plugin
 */
function deploy_forge(): Deploy_Forge {
    return Deploy_Forge::get_instance();
}

// Start the plugin
deploy_forge();
