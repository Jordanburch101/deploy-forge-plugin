<?php
/**
 * Update Checker
 *
 * Integrates with the Deploy Forge Update Server to enable automatic plugin updates
 * from the private GitHub repository.
 *
 * @package Deploy_Forge
 * @since 0.5.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Deploy_Forge_Update_Checker
 *
 * Handles WordPress plugin update checks and downloads through the update server.
 */
class Deploy_Forge_Update_Checker {

    /**
     * Plugin slug
     *
     * @var string
     */
    private $plugin_slug;

    /**
     * Plugin basename (e.g., 'deploy-forge/deploy-forge.php')
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Current plugin version
     *
     * @var string
     */
    private $current_version;

    /**
     * Update server URL
     *
     * @var string
     */
    private $update_server_url;

    /**
     * API key for authentication
     *
     * @var string
     */
    private $api_key;

    /**
     * Cache key for update transient
     *
     * @var string
     */
    private $cache_key;

    /**
     * Cache duration (in seconds)
     *
     * @var int
     */
    private $cache_duration = 43200; // 12 hours

    /**
     * Constructor
     *
     * @param string $plugin_file Full path to the plugin main file
     * @param string $current_version Current version of the plugin
     * @param string $update_server_url URL of the update server
     * @param string $api_key API key for authentication
     */
    public function __construct($plugin_file, $current_version, $update_server_url, $api_key = '') {
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        $this->current_version = $current_version;
        $this->update_server_url = rtrim($update_server_url, '/');
        $this->api_key = $api_key;
        $this->cache_key = 'deploy_forge_update_' . md5($this->plugin_basename);

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Check for plugin updates
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));

        // Provide plugin information for the update modal
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);

        // Modify the plugin row to show update information
        add_action("after_plugin_row_{$this->plugin_basename}", array($this, 'show_update_notification'), 10, 2);

        // Clear cache when user manually checks for updates
        add_action('load-update-core.php', array($this, 'clear_cache'));
        add_action('load-plugins.php', array($this, 'clear_cache_on_demand'));
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient Update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Check cache first
        $cached_response = get_transient($this->cache_key);
        if ($cached_response !== false) {
            if (!empty($cached_response) && version_compare($this->current_version, $cached_response->new_version, '<')) {
                $transient->response[$this->plugin_basename] = $cached_response;
            }
            return $transient;
        }

        // Fetch update information from server
        $update_info = $this->fetch_update_info();

        if ($update_info && !is_wp_error($update_info)) {
            // Cache the response
            set_transient($this->cache_key, $update_info, $this->cache_duration);

            // Add to transient if update available
            if (version_compare($this->current_version, $update_info->new_version, '<')) {
                $transient->response[$this->plugin_basename] = $update_info;
            } else {
                $transient->no_update[$this->plugin_basename] = $update_info;
            }
        }

        return $transient;
    }

    /**
     * Fetch update information from the update server
     *
     * @return object|WP_Error Update information or error
     */
    private function fetch_update_info() {
        $url = $this->update_server_url . '/api/updates/check/' . $this->plugin_slug;

        $headers = array();

        // Only send API key if one is configured
        if (!empty($this->api_key)) {
            $headers['X-API-Key'] = $this->api_key;
        }

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => $headers,
        ));

        if (is_wp_error($response)) {
            error_log('Deploy Forge Update Check Error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('Deploy Forge Update Check HTTP Error: ' . $code);
            return new WP_Error('http_error', 'Update server returned error code: ' . $code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data)) {
            return new WP_Error('invalid_response', 'Invalid response from update server');
        }

        // Transform to WordPress update object format
        return $this->transform_update_response($data);
    }

    /**
     * Transform update server response to WordPress update object
     *
     * @param object $data Response data from update server
     * @return object WordPress update object
     */
    private function transform_update_response($data) {
        $update_obj = new stdClass();
        $update_obj->slug = $this->plugin_slug;
        $update_obj->plugin = $this->plugin_basename;
        $update_obj->new_version = $data->new_version;
        $update_obj->url = $data->url;
        $update_obj->package = $data->package;
        $update_obj->tested = $data->tested ?? '';
        $update_obj->requires_php = $data->requires_php ?? '';
        $update_obj->requires = $data->requires ?? '';

        return $update_obj;
    }

    /**
     * Provide plugin information for the WordPress update modal
     *
     * @param false|object|array $result The result object or array
     * @param string $action The type of information being requested
     * @param object $args Plugin API arguments
     * @return false|object Plugin information or false
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        // Fetch detailed plugin information
        $plugin_info = $this->fetch_plugin_info();

        if ($plugin_info && !is_wp_error($plugin_info)) {
            return $plugin_info;
        }

        return $result;
    }

    /**
     * Fetch detailed plugin information from the update server
     *
     * @return object|WP_Error Plugin information or error
     */
    private function fetch_plugin_info() {
        $url = $this->update_server_url . '/api/updates/info';

        $headers = array(
            'Content-Type' => 'application/json',
        );

        // Only send API key if one is configured
        if (!empty($this->api_key)) {
            $headers['X-API-Key'] = $this->api_key;
        }

        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => $headers,
            'body' => wp_json_encode(array(
                'slug' => $this->plugin_slug,
                'action' => 'plugin_information',
            )),
        ));

        if (is_wp_error($response)) {
            error_log('Deploy Forge Plugin Info Error: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('Deploy Forge Plugin Info HTTP Error: ' . $code);
            return new WP_Error('http_error', 'Update server returned error code: ' . $code);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data)) {
            return new WP_Error('invalid_response', 'Invalid response from update server');
        }

        return $this->transform_plugin_info($data);
    }

    /**
     * Transform plugin info response to WordPress format
     *
     * @param object $data Response data from update server
     * @return object WordPress plugin info object
     */
    private function transform_plugin_info($data) {
        $info = new stdClass();
        $info->name = $data->name ?? 'Deploy Forge';
        $info->slug = $this->plugin_slug;
        $info->version = $data->version ?? $this->current_version;
        $info->author = $data->author ?? 'Jordan Burch';
        $info->author_profile = $data->author_profile ?? 'https://jordanburch.dev';
        $info->homepage = $data->homepage ?? 'https://github.com/jordanburch101/deploy-forge';
        $info->requires = $data->requires ?? '5.8';
        $info->tested = $data->tested ?? '6.4';
        $info->requires_php = $data->requires_php ?? '7.4';
        $info->download_link = $data->download_link ?? '';
        $info->last_updated = $data->last_updated ?? date('Y-m-d');
        $info->sections = $data->sections ?? array(
            'description' => 'Automates theme deployment from GitHub repositories using GitHub Actions.',
        );
        $info->banners = $data->banners ?? array();
        $info->icons = $data->icons ?? array();

        return $info;
    }

    /**
     * Show update notification in plugin list
     *
     * @param string $plugin_file Plugin file path
     * @param array $plugin_data Plugin data
     */
    public function show_update_notification($plugin_file, $plugin_data) {
        // Only show if update is available
        $update_cache = get_site_transient('update_plugins');
        if (empty($update_cache->response[$this->plugin_basename])) {
            return;
        }

        $update = $update_cache->response[$this->plugin_basename];

        // Check if user has permission to update plugins
        if (!current_user_can('update_plugins')) {
            return;
        }

        $wp_list_table = _get_list_table('WP_Plugins_List_Table');

        printf(
            '<tr class="plugin-update-tr%s" id="%s" data-slug="%s" data-plugin="%s">' .
            '<td colspan="%s" class="plugin-update colspanchange">' .
            '<div class="update-message notice inline notice-warning notice-alt"><p>',
            $this->is_active() ? ' active' : '',
            esc_attr($this->plugin_slug . '-update'),
            esc_attr($this->plugin_slug),
            esc_attr($plugin_file),
            esc_attr($wp_list_table->get_column_count())
        );

        printf(
            /* translators: %s: plugin version */
            __('A new version %s of Deploy Forge is available.', 'deploy-forge'),
            '<strong>' . esc_html($update->new_version) . '</strong>'
        );

        printf(
            ' <a href="%s" class="update-link" aria-label="%s">%s</a>',
            wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') . $plugin_file, 'upgrade-plugin_' . $plugin_file),
            /* translators: %s: plugin name */
            esc_attr(sprintf(__('Update %s now', 'deploy-forge'), $plugin_data['Name'])),
            __('Update now', 'deploy-forge')
        );

        echo '</p></div></td></tr>';
    }

    /**
     * Check if plugin is active
     *
     * @return bool
     */
    private function is_active() {
        return is_plugin_active($this->plugin_basename);
    }

    /**
     * Clear update cache
     */
    public function clear_cache() {
        delete_transient($this->cache_key);
    }

    /**
     * Clear cache when user clicks "Check Again" on plugins page
     */
    public function clear_cache_on_demand() {
        if (isset($_GET['force-check']) && $_GET['force-check'] === '1') {
            $this->clear_cache();
        }
    }

    /**
     * Set API key
     *
     * @param string $api_key API key for authentication
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Get API key
     *
     * @return string
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Set update server URL
     *
     * @param string $url Update server URL
     */
    public function set_update_server_url($url) {
        $this->update_server_url = rtrim($url, '/');
    }

    /**
     * Get update server URL
     *
     * @return string
     */
    public function get_update_server_url() {
        return $this->update_server_url;
    }

    /**
     * Manually trigger an update check
     *
     * @return object|WP_Error Update information or error
     */
    public function manual_check() {
        $this->clear_cache();
        return $this->fetch_update_info();
    }
}
