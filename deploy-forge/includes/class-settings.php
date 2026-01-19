<?php
/**
 * Settings management class.
 *
 * Handles plugin options and Deploy Forge API credentials.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Settings
 *
 * Manages plugin settings, API credentials, and connection data.
 *
 * @since 1.0.0
 */
class Deploy_Forge_Settings {


	/**
	 * Option name for general settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const OPTION_NAME = 'deploy_forge_settings';

	/**
	 * Option name for API key.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const API_KEY_OPTION = 'deploy_forge_api_key';

	/**
	 * Option name for webhook secret.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const WEBHOOK_SECRET_OPTION = 'deploy_forge_webhook_secret';

	/**
	 * Option name for site ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const SITE_ID_OPTION = 'deploy_forge_site_id';

	/**
	 * Option name for connection data.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const CONNECTION_DATA_OPTION = 'deploy_forge_connection_data';

	/**
	 * Deploy Forge backend URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const BACKEND_URL = 'https://getdeployforge.com';

	/**
	 * Cached settings array.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private array $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_settings();
	}

	/**
	 * Load settings from database.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_settings(): void {
		$defaults = array(
			'github_repo_owner'       => '',
			'github_repo_name'        => '',
			'github_branch'           => 'main',
			'github_workflow_name'    => 'deploy-theme.yml',
			'deployment_method'       => 'github_actions',
			'require_manual_approval' => false,
			'create_backups'          => true,
			'debug_mode'              => false,
		);

		$this->settings = wp_parse_args( get_option( self::OPTION_NAME, array() ), $defaults );
	}

	/**
	 * Get a setting value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed The setting value or default.
	 */
	public function get( string $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Get all settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array All settings.
	 */
	public function get_all(): array {
		return $this->settings;
	}

	/**
	 * Save settings to database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Settings to save.
	 * @return bool True on success, false on failure.
	 */
	public function save( array $settings ): bool {
		// Sanitize settings.
		$sanitized = array(
			'github_repo_owner'       => sanitize_text_field( $settings['github_repo_owner'] ?? '' ),
			'github_repo_name'        => sanitize_text_field( $settings['github_repo_name'] ?? '' ),
			'github_branch'           => sanitize_text_field( $settings['github_branch'] ?? 'main' ),
			'github_workflow_name'    => sanitize_text_field( $settings['github_workflow_name'] ?? 'deploy-theme.yml' ),
			'deployment_method'       => in_array( $settings['deployment_method'] ?? '', array( 'github_actions', 'direct_clone' ), true )
				? $settings['deployment_method']
				: 'github_actions',
			'require_manual_approval' => (bool) ( $settings['require_manual_approval'] ?? true ),
			'create_backups'          => (bool) ( $settings['create_backups'] ?? true ),
			'debug_mode'              => (bool) ( $settings['debug_mode'] ?? false ),
		);

		$result = update_option( self::OPTION_NAME, $sanitized );

		if ( $result ) {
			$this->settings = $sanitized;
		}

		return $result;
	}

	/**
	 * Update a single setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The setting key.
	 * @param mixed  $value The setting value.
	 * @return bool True on success, false on failure.
	 */
	public function update( string $key, $value ): bool {
		$this->settings[ $key ] = $value;
		$result                 = update_option( self::OPTION_NAME, $this->settings );

		if ( $result ) {
			$this->load_settings();
		}

		return $result;
	}

	/**
	 * Get Deploy Forge API key.
	 *
	 * @since 1.0.0
	 *
	 * @return string The API key.
	 */
	public function get_api_key(): string {
		return get_option( self::API_KEY_OPTION, '' );
	}

	/**
	 * Set Deploy Forge API key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key The API key to save.
	 * @return bool True on success, false on failure.
	 */
	public function set_api_key( string $api_key ): bool {
		if ( empty( $api_key ) ) {
			return delete_option( self::API_KEY_OPTION );
		}

		// Validate API key format (df_live_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX).
		if ( ! preg_match( '/^df_live_[a-f0-9]{32}$/i', $api_key ) ) {
			return false;
		}

		return update_option( self::API_KEY_OPTION, $api_key );
	}

	/**
	 * Get webhook secret.
	 *
	 * @since 1.0.0
	 *
	 * @return string The webhook secret.
	 */
	public function get_webhook_secret(): string {
		return get_option( self::WEBHOOK_SECRET_OPTION, '' );
	}

	/**
	 * Set webhook secret.
	 *
	 * @since 1.0.0
	 *
	 * @param string $secret The webhook secret to save.
	 * @return bool True on success, false on failure.
	 */
	public function set_webhook_secret( string $secret ): bool {
		if ( empty( $secret ) ) {
			return delete_option( self::WEBHOOK_SECRET_OPTION );
		}

		// Validate webhook secret format (whsec_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX).
		if ( ! preg_match( '/^whsec_[a-f0-9]{32}$/i', $secret ) ) {
			return false;
		}

		return update_option( self::WEBHOOK_SECRET_OPTION, $secret );
	}

	/**
	 * Get site ID.
	 *
	 * @since 1.0.0
	 *
	 * @return string The site ID.
	 */
	public function get_site_id(): string {
		return get_option( self::SITE_ID_OPTION, '' );
	}

	/**
	 * Set site ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $site_id The site ID to save.
	 * @return bool True on success, false on failure.
	 */
	public function set_site_id( string $site_id ): bool {
		if ( empty( $site_id ) ) {
			return delete_option( self::SITE_ID_OPTION );
		}

		return update_option( self::SITE_ID_OPTION, $site_id );
	}

	/**
	 * Get connection data (repo info, installation ID, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @return array Connection data with defaults.
	 */
	public function get_connection_data(): array {
		$defaults = array(
			'installation_id'   => '',
			'repo_owner'        => '',
			'repo_name'         => '',
			'repo_branch'       => 'main',
			'deployment_method' => 'github_actions',
			'workflow_path'     => '',
			'connected_at'      => '',
			'domain'            => '',
		);

		return wp_parse_args( get_option( self::CONNECTION_DATA_OPTION, array() ), $defaults );
	}

	/**
	 * Set connection data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Connection data to save.
	 * @return bool True on success, false on failure.
	 */
	public function set_connection_data( array $data ): bool {
		$sanitized = array(
			'installation_id'   => sanitize_text_field( $data['installation_id'] ?? '' ),
			'repo_owner'        => sanitize_text_field( $data['repo_owner'] ?? '' ),
			'repo_name'         => sanitize_text_field( $data['repo_name'] ?? '' ),
			'repo_branch'       => sanitize_text_field( $data['repo_branch'] ?? 'main' ),
			'deployment_method' => sanitize_text_field( $data['deployment_method'] ?? 'github_actions' ),
			'workflow_path'     => sanitize_text_field( $data['workflow_path'] ?? '' ),
			'connected_at'      => sanitize_text_field( $data['connected_at'] ?? current_time( 'mysql' ) ),
			'domain'            => sanitize_text_field( $data['domain'] ?? '' ),
		);

		// Also update settings with repo info for backward compatibility.
		$current_settings                         = $this->get_all();
		$current_settings['github_repo_owner']    = $sanitized['repo_owner'];
		$current_settings['github_repo_name']     = $sanitized['repo_name'];
		$current_settings['github_branch']        = $sanitized['repo_branch'];
		$current_settings['deployment_method']    = $sanitized['deployment_method'];
		$current_settings['github_workflow_name'] = basename( $sanitized['workflow_path'] );
		$this->save( $current_settings );

		return update_option( self::CONNECTION_DATA_OPTION, $sanitized );
	}

	/**
	 * Check if connected to Deploy Forge.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if connected, false otherwise.
	 */
	public function is_connected(): bool {
		return ! empty( $this->get_api_key() )
			&& ! empty( $this->get_webhook_secret() )
			&& ! empty( $this->get_site_id() );
	}

	/**
	 * Check if repository is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function is_repo_configured(): bool {
		$data = $this->get_connection_data();
		return ! empty( $data['repo_owner'] ) && ! empty( $data['repo_name'] );
	}

	/**
	 * Disconnect from Deploy Forge.
	 *
	 * Calls the API to disconnect and clears local credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Always returns true.
	 */
	public function disconnect(): bool {
		$api_key = $this->get_api_key();

		// Call API to disconnect if we have an API key.
		if ( ! empty( $api_key ) ) {
			$response = wp_remote_post(
				self::BACKEND_URL . '/api/plugin/auth/disconnect',
				array(
					'headers' => array(
						'Content-Type' => 'application/json',
						'X-API-Key'    => $api_key,
					),
					'timeout' => 15,
				)
			);

			// Log any errors but don't fail the disconnect.
			if ( is_wp_error( $response ) ) {
				error_log( 'Deploy Forge: Disconnect API error - ' . $response->get_error_message() );
			}
		}

		// Clear all stored credentials and data.
		delete_option( self::API_KEY_OPTION );
		delete_option( self::WEBHOOK_SECRET_OPTION );
		delete_option( self::SITE_ID_OPTION );
		delete_option( self::CONNECTION_DATA_OPTION );

		// Clear repo settings.
		$current_settings                         = $this->get_all();
		$current_settings['github_repo_owner']    = '';
		$current_settings['github_repo_name']     = '';
		$current_settings['github_branch']        = 'main';
		$current_settings['github_workflow_name'] = 'deploy-theme.yml';
		$this->save( $current_settings );

		return true;
	}

	/**
	 * Get Deploy Forge backend URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The backend URL.
	 */
	public function get_backend_url(): string {
		return defined( 'DEPLOY_FORGE_BACKEND_URL' )
			? constant( 'DEPLOY_FORGE_BACKEND_URL' )
			: self::BACKEND_URL;
	}

	/**
	 * Get repository full name (owner/repo).
	 *
	 * @since 1.0.0
	 *
	 * @return string The full repository name.
	 */
	public function get_repo_full_name(): string {
		$data = $this->get_connection_data();
		if ( ! empty( $data['repo_owner'] ) && ! empty( $data['repo_name'] ) ) {
			return $data['repo_owner'] . '/' . $data['repo_name'];
		}

		// Fallback to settings for backward compatibility.
		return $this->get( 'github_repo_owner' ) . '/' . $this->get( 'github_repo_name' );
	}

	/**
	 * Get theme path (uses repository name).
	 *
	 * @since 1.0.0
	 *
	 * @return string The theme path.
	 */
	public function get_theme_path(): string {
		$data      = $this->get_connection_data();
		$repo_name = ! empty( $data['repo_name'] ) ? $data['repo_name'] : $this->get( 'github_repo_name' );
		return WP_CONTENT_DIR . '/themes/' . $repo_name;
	}

	/**
	 * Get backup directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The backup directory path.
	 */
	public function get_backup_directory(): string {
		$upload_dir = wp_upload_dir();
		$backup_dir = $upload_dir['basedir'] . '/deploy-forge-backups';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		return $backup_dir;
	}

	/**
	 * Get webhook URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The webhook URL.
	 */
	public function get_webhook_url(): string {
		return rest_url( 'deploy-forge/v1/webhook' );
	}

	/**
	 * Validate settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of error messages.
	 */
	public function validate(): array {
		$errors = array();

		if ( ! $this->is_connected() ) {
			$errors[] = __( 'Not connected to Deploy Forge. Please connect your site.', 'deploy-forge' );
		}

		if ( ! $this->is_repo_configured() ) {
			$errors[] = __( 'Repository not configured. Please reconnect to configure your repository.', 'deploy-forge' );
		}

		// Validate theme directory exists.
		$theme_path = $this->get_theme_path();
		$repo_name  = $this->get_connection_data()['repo_name'] ?? $this->get( 'github_repo_name' );
		if ( ! empty( $repo_name ) && ! is_dir( $theme_path ) ) {
			$errors[] = sprintf(
				/* translators: %s: Theme directory path */
				__( 'Theme directory does not exist: %s', 'deploy-forge' ),
				$theme_path
			);
		}

		return $errors;
	}

	/**
	 * Check if settings are configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if configured, false otherwise.
	 */
	public function is_configured(): bool {
		return $this->is_connected() && $this->is_repo_configured();
	}

	/**
	 * Reset all plugin settings (for complete reset).
	 *
	 * @since 1.0.0
	 *
	 * @return bool Always returns true.
	 */
	public function reset_all_settings(): bool {
		// Delete all options.
		delete_option( self::OPTION_NAME );
		delete_option( self::API_KEY_OPTION );
		delete_option( self::WEBHOOK_SECRET_OPTION );
		delete_option( self::SITE_ID_OPTION );
		delete_option( self::CONNECTION_DATA_OPTION );
		delete_option( 'deploy_forge_db_version' );

		// Reload settings from defaults after reset.
		$this->load_settings();

		return true;
	}

	/**
	 * Legacy method for backward compatibility.
	 *
	 * @since      1.0.0
	 * @deprecated Use get_webhook_secret() instead.
	 *
	 * @return string The webhook secret.
	 */
	public function generate_webhook_secret(): string {
		// This is now handled by Deploy Forge platform.
		return $this->get_webhook_secret();
	}

	/**
	 * Legacy method for backward compatibility.
	 *
	 * @since      1.0.0
	 * @deprecated Use is_connected() instead.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	public function is_github_connected(): bool {
		return $this->is_connected();
	}

	/**
	 * Legacy method for backward compatibility.
	 *
	 * @since      1.0.0
	 * @deprecated No longer used - repo binding handled by platform.
	 *
	 * @return bool True if repo is configured, false otherwise.
	 */
	public function is_repo_bound(): bool {
		return $this->is_repo_configured();
	}

	/**
	 * Legacy method for backward compatibility.
	 *
	 * @since      1.0.0
	 * @deprecated No longer used - GitHub data structure changed.
	 *
	 * @return array Connection data.
	 */
	public function get_github_data(): array {
		return $this->get_connection_data();
	}

	/**
	 * Legacy method for backward compatibility.
	 *
	 * @since      1.0.0
	 * @deprecated No longer used - GitHub data structure changed.
	 *
	 * @param array $data Connection data to save.
	 * @return bool True on success, false on failure.
	 */
	public function set_github_data( array $data ): bool {
		return $this->set_connection_data( $data );
	}

	/**
	 * Legacy method for backward compatibility.
	 *
	 * @since      1.0.0
	 * @deprecated Use disconnect() instead.
	 *
	 * @return bool Always returns true.
	 */
	public function disconnect_github(): bool {
		return $this->disconnect();
	}
}
