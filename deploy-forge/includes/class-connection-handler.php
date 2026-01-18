<?php
/**
 * Deploy Forge connection handler class
 *
 * Manages the connection flow with the Deploy Forge platform including
 * OAuth initiation, token exchange, and connection verification.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Connection_Handler
 *
 * Handles all communication with the Deploy Forge platform for
 * establishing and managing site connections.
 *
 * @since 1.0.0
 */
class Deploy_Forge_Connection_Handler {

	/**
	 * Settings instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Settings
	 */
	private Deploy_Forge_Settings $settings;

	/**
	 * Debug logger instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Debug_Logger
	 */
	private Deploy_Forge_Debug_Logger $logger;

	/**
	 * Constructor.
	 *
	 * Initialize the connection handler with required dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @param Deploy_Forge_Settings     $settings Settings instance.
	 * @param Deploy_Forge_Debug_Logger $logger   Debug logger instance.
	 */
	public function __construct( Deploy_Forge_Settings $settings, Deploy_Forge_Debug_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Initiate connection to Deploy Forge.
	 *
	 * Step 1: Call /connect/init to get redirect URL for OAuth flow.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, redirect_url?: string, message?: string} Connection result.
	 */
	public function initiate_connection(): array {
		$site_url   = home_url();
		$return_url = admin_url( 'admin.php?page=deploy-forge-settings&action=df_callback' );
		$nonce      = wp_generate_password( 16, false );

		// Store nonce temporarily (5 minutes) for verification.
		set_transient( 'deploy_forge_connection_nonce', $nonce, 300 );

		$backend_url = $this->settings->get_backend_url();
		$init_url    = $backend_url . '/api/plugin/connect/init';

		$this->logger->log(
			'Connection',
			'Initiating Deploy Forge connection',
			array(
				'site_url'   => $site_url,
				'return_url' => $return_url,
			)
		);

		$response = wp_remote_post(
			$init_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'siteUrl'   => $site_url,
						'returnUrl' => $return_url,
						'nonce'     => $nonce,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Connection', 'Failed to initiate connection', $response );
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code || ! isset( $body['success'] ) || ! $body['success'] ) {
			$error_message = $body['error'] ?? 'Failed to initiate connection';
			$this->logger->error(
				'Connection',
				'Connection initiation failed',
				array(
					'status' => $status_code,
					'body'   => $body,
				)
			);
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		$this->logger->log( 'Connection', 'Connection initiated successfully' );

		return array(
			'success'      => true,
			'redirect_url' => $body['redirectUrl'],
		);
	}

	/**
	 * Handle callback from Deploy Forge.
	 *
	 * Step 2: Receive connection token and nonce, then exchange for credentials.
	 *
	 * @since 1.0.0
	 *
	 * @param string $connection_token The connection token from Deploy Forge.
	 * @param string $returned_nonce   The nonce returned for verification.
	 * @return array{success: bool, message: string, data?: array} Callback result.
	 */
	public function handle_callback( string $connection_token, string $returned_nonce ): array {
		// Verify nonce.
		$stored_nonce = get_transient( 'deploy_forge_connection_nonce' );

		if ( empty( $stored_nonce ) || $stored_nonce !== $returned_nonce ) {
			$this->logger->error(
				'Connection',
				'Invalid or expired nonce',
				array(
					'has_stored_nonce' => ! empty( $stored_nonce ),
					'nonces_match'     => $stored_nonce === $returned_nonce,
				)
			);
			return array(
				'success' => false,
				'message' => __( 'Invalid or expired connection attempt. Please try again.', 'deploy-forge' ),
			);
		}

		// Clear the nonce.
		delete_transient( 'deploy_forge_connection_nonce' );

		$this->logger->log( 'Connection', 'Callback received, exchanging token for credentials' );

		// Exchange token for credentials.
		return $this->exchange_token( $connection_token );
	}

	/**
	 * Exchange connection token for API credentials.
	 *
	 * Step 3: Call /auth/exchange-token to get API key and webhook secret.
	 *
	 * @since 1.0.0
	 *
	 * @param string $connection_token The connection token to exchange.
	 * @return array{success: bool, message: string, data?: array} Exchange result.
	 */
	private function exchange_token( string $connection_token ): array {
		$backend_url  = $this->settings->get_backend_url();
		$exchange_url = $backend_url . '/api/plugin/auth/exchange-token';

		$response = wp_remote_post(
			$exchange_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'connectionToken' => $connection_token,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Connection', 'Token exchange failed', $response );
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code || ! isset( $body['success'] ) || ! $body['success'] ) {
			$error_message = $body['error'] ?? 'Failed to exchange connection token';
			$this->logger->error(
				'Connection',
				'Token exchange failed',
				array(
					'status' => $status_code,
					'body'   => $body,
				)
			);
			return array(
				'success' => false,
				'message' => $error_message,
			);
		}

		// Extract credentials.
		$api_key           = $body['apiKey'] ?? '';
		$webhook_secret    = $body['webhookSecret'] ?? '';
		$site_id           = $body['siteId'] ?? '';
		$domain            = $body['domain'] ?? '';
		$installation_id   = $body['installationId'] ?? '';
		$repo_owner        = $body['repoOwner'] ?? '';
		$repo_name         = $body['repoName'] ?? '';
		$repo_branch       = $body['repoBranch'] ?? '';
		$deployment_method = $body['deploymentMethod'] ?? 'github_actions';
		$workflow_path     = $body['workflowPath'] ?? '';

		// Validate required fields.
		if ( empty( $api_key ) || empty( $webhook_secret ) || empty( $site_id ) ) {
			$this->logger->error( 'Connection', 'Missing required credentials in response', $body );
			return array(
				'success' => false,
				'message' => __( 'Invalid credentials received from Deploy Forge.', 'deploy-forge' ),
			);
		}

		// Store credentials.
		$this->settings->set_api_key( $api_key );
		$this->settings->set_webhook_secret( $webhook_secret );
		$this->settings->set_site_id( $site_id );

		// Store connection data.
		$this->settings->set_connection_data(
			array(
				'installation_id'   => $installation_id,
				'repo_owner'        => $repo_owner,
				'repo_name'         => $repo_name,
				'repo_branch'       => $repo_branch,
				'deployment_method' => $deployment_method,
				'workflow_path'     => $workflow_path,
				'domain'            => $domain,
				'connected_at'      => current_time( 'mysql' ),
			)
		);

		$this->logger->log(
			'Connection',
			'Successfully connected to Deploy Forge',
			array(
				'site_id' => $site_id,
				'domain'  => $domain,
				'repo'    => $repo_owner . '/' . $repo_name,
			)
		);

		return array(
			'success' => true,
			'message' => __( 'Successfully connected to Deploy Forge!', 'deploy-forge' ),
			'data'    => array(
				'site_id'     => $site_id,
				'domain'      => $domain,
				'repo_owner'  => $repo_owner,
				'repo_name'   => $repo_name,
				'repo_branch' => $repo_branch,
			),
		);
	}

	/**
	 * Disconnect from Deploy Forge.
	 *
	 * Removes all stored credentials and connection data.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string} Disconnect result.
	 */
	public function disconnect(): array {
		$this->logger->log( 'Connection', 'Disconnecting from Deploy Forge' );

		$result = $this->settings->disconnect();

		if ( $result ) {
			$this->logger->log( 'Connection', 'Successfully disconnected' );
			return array(
				'success' => true,
				'message' => __( 'Successfully disconnected from Deploy Forge.', 'deploy-forge' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to disconnect.', 'deploy-forge' ),
		);
	}

	/**
	 * Verify connection status with Deploy Forge API.
	 *
	 * Checks if the current API credentials are still valid.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, connected: bool, message?: string, site_id?: string, domain?: string, status?: string} Verification result.
	 */
	public function verify_connection(): array {
		if ( ! $this->settings->is_connected() ) {
			return array(
				'success'   => false,
				'message'   => __( 'Not connected to Deploy Forge.', 'deploy-forge' ),
				'connected' => false,
			);
		}

		$backend_url = $this->settings->get_backend_url();
		$verify_url  = $backend_url . '/api/plugin/auth/verify';

		$response = wp_remote_post(
			$verify_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => $this->settings->get_api_key(),
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Connection', 'Verification failed', $response );
			return array(
				'success'   => false,
				'message'   => $response->get_error_message(),
				'connected' => false,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $status_code && isset( $body['success'] ) && $body['success'] ) {
			return array(
				'success'   => true,
				'connected' => true,
				'site_id'   => $body['siteId'] ?? '',
				'domain'    => $body['domain'] ?? '',
				'status'    => $body['status'] ?? 'active',
			);
		}

		return array(
			'success'   => false,
			'message'   => $body['error'] ?? 'Connection verification failed',
			'connected' => false,
		);
	}
}
