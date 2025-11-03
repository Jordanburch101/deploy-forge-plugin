<?php
/**
 * Plugin Name: GitHub Auto-Deploy for WordPress
 * Plugin URI: https://github.com/yourusername/github-auto-deploy
 * Description: Automates WordPress theme deployment from GitHub repositories via GitHub Actions webhooks
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

// Require plugin classes
require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-database.php';
require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-settings.php';
require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-debug-logger.php';
require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-github-api.php';
require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-webhook-handler.php';
require_once GITHUB_DEPLOY_PLUGIN_DIR . 'includes/class-deployment-manager.php';
require_once GITHUB_DEPLOY_PLUGIN_DIR . 'admin/class-admin-pages.php';

/**
 * Main plugin class
 */
class GitHub_Auto_Deploy {

    private static ?self $instance = null;
    private GitHub_Deploy_Database $database;
    private GitHub_Deploy_Settings $settings;
    private GitHub_Deploy_Debug_Logger $logger;
    private GitHub_API $github_api;
    private GitHub_Webhook_Handler $webhook_handler;
    private GitHub_Deployment_Manager $deployment_manager;
    private GitHub_Deploy_Admin_Pages $admin_pages;

    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
        $this->setup_hooks();
    }

    private function init(): void {
        $this->database = new GitHub_Deploy_Database();
        $this->settings = new GitHub_Deploy_Settings();
        $this->logger = new GitHub_Deploy_Debug_Logger($this->settings);
        $this->github_api = new GitHub_API($this->settings, $this->logger);
        $this->webhook_handler = new GitHub_Webhook_Handler($this->settings, $this->github_api, $this->logger);
        $this->deployment_manager = new GitHub_Deployment_Manager($this->settings, $this->github_api, $this->database, $this->logger);

        if (is_admin()) {
            $this->admin_pages = new GitHub_Deploy_Admin_Pages($this->settings, $this->github_api, $this->deployment_manager, $this->database, $this->logger);
        }
    }

    private function setup_hooks(): void {
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Plugin loaded
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Initialize webhook handler
        add_action('rest_api_init', [$this->webhook_handler, 'register_routes']);

        // Initialize cron jobs
        add_action('github_deploy_check_builds', [$this->deployment_manager, 'check_pending_deployments']);
    }

    public function activate(): void {
        // Create database tables
        $this->database->create_tables();

        // Schedule cron job for checking build status
        if (!wp_next_scheduled('github_deploy_check_builds')) {
            wp_schedule_event(time(), 'every_minute', 'github_deploy_check_builds');
        }

        // Flush rewrite rules for REST API
        flush_rewrite_rules();
    }

    public function deactivate(): void {
        // Clear scheduled cron jobs
        $timestamp = wp_next_scheduled('github_deploy_check_builds');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'github_deploy_check_builds');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('github-auto-deploy', false, dirname(GITHUB_DEPLOY_PLUGIN_BASENAME) . '/languages');
    }

    // Getter methods for accessing components
    public function get_database(): GitHub_Deploy_Database {
        return $this->database;
    }

    public function get_settings(): GitHub_Deploy_Settings {
        return $this->settings;
    }

    public function get_github_api(): GitHub_API {
        return $this->github_api;
    }

    public function get_deployment_manager(): GitHub_Deployment_Manager {
        return $this->deployment_manager;
    }
}

// Add custom cron interval
add_filter('cron_schedules', function(array $schedules): array {
    $schedules['every_minute'] = [
        'interval' => 60,
        'display'  => __('Every Minute', 'github-auto-deploy')
    ];
    return $schedules;
});

// Initialize the plugin
function github_auto_deploy(): GitHub_Auto_Deploy {
    return GitHub_Auto_Deploy::get_instance();
}

// Start the plugin
github_auto_deploy();
