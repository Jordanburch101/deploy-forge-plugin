<?php
/**
 * Plugin Name: GitHub Auto-Deploy
 * Plugin URI: https://github.com/yourusername/github-auto-deploy
 * Description: Automates theme deployment from GitHub repositories using GitHub Actions
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: github-auto-deploy
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('GITHUB_DEPLOY_VERSION', '1.0.0');
define('GITHUB_DEPLOY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GITHUB_DEPLOY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GITHUB_DEPLOY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class GitHub_Auto_Deploy {

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
        require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-database.php';
        require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-settings.php';
        require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-github-api.php';
        require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-deployment-manager.php';
        require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-webhook-handler.php';

        // Admin classes
        if (is_admin()) {
            require_once GITHUB_DEPLOY_PLUGIN_DIR . 'admin/class-admin-pages.php';
        }

        // Initialize instances
        $this->database = new GitHub_Deploy_Database();
        $this->settings = new GitHub_Deploy_Settings();
        $this->github_api = new GitHub_Deploy_GitHub_API($this->settings);
        $this->deployment_manager = new GitHub_Deploy_Deployment_Manager($this->github_api, $this->database, $this->settings);
        $this->webhook_handler = new GitHub_Deploy_Webhook_Handler($this->deployment_manager, $this->settings);

        if (is_admin()) {
            $this->admin_pages = new GitHub_Deploy_Admin_Pages($this->deployment_manager, $this->database, $this->settings);
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
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
        wp_clear_scheduled_hook('github_deploy_check_build_status');
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'github-auto-deploy',
            false,
            dirname(GITHUB_DEPLOY_PLUGIN_BASENAME) . '/languages'
        );
    }
}

/**
 * Initialize the plugin
 */
function github_auto_deploy(): GitHub_Auto_Deploy {
    return GitHub_Auto_Deploy::get_instance();
}

// Start the plugin
github_auto_deploy();
