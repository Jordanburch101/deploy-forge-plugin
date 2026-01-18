<?php
/**
 * GitHub API wrapper class.
 *
 * Handles all GitHub API v3 interactions using wp_remote_request().
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_GitHub_API
 *
 * Provides methods for interacting with the GitHub API through
 * the Deploy Forge backend proxy.
 *
 * @since 1.0.0
 */
class Deploy_Forge_GitHub_API {

	/**
	 * Settings instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Settings
	 */
	private Deploy_Forge_Settings $settings;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Debug_Logger
	 */
	private Deploy_Forge_Debug_Logger $logger;

	/**
	 * GitHub API base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const API_BASE = 'https://api.github.com';

	/**
	 * User agent string for API requests.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const USER_AGENT = 'WordPress-Deploy-Forge/1.0';

	/**
	 * Deploy Forge backend URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const BACKEND_URL = 'https://deploy-forge-website.vercel.app';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Deploy_Forge_Settings     $settings Settings instance.
	 * @param Deploy_Forge_Debug_Logger $logger   Logger instance.
	 */
	public function __construct( Deploy_Forge_Settings $settings, Deploy_Forge_Debug_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Test connection to GitHub API and repository.
	 *
	 * @since 1.0.0
	 *
	 * @return array Result array with success status and message.
	 */
	public function test_connection(): array {
		$response = $this->request( 'GET', "/repos/{$this->settings->get_repo_full_name()}" );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			return array(
				'success' => true,
				'message' => __( 'Successfully connected to repository!', 'deploy-forge' ),
				'data'    => $response['body'],
			);
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to connect to repository.', 'deploy-forge' ),
		);
	}

	/**
	 * Trigger a GitHub Actions workflow.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $workflow_name The workflow filename.
	 * @param string|null $ref           Optional branch or ref to trigger on.
	 * @return array Result array with success status and message.
	 */
	public function trigger_workflow( string $workflow_name, ?string $ref = null ): array {
		if ( ! $ref ) {
			$ref = $this->settings->get( 'github_branch' );
		}

		$endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/workflows/{$workflow_name}/dispatches";

		$response = $this->request(
			'POST',
			$endpoint,
			array(
				'ref' => $ref,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		// GitHub API returns 204 (No Content) or sometimes 200 (OK) on success.
		if ( 204 === $response['status'] || 200 === $response['status'] ) {
			return array(
				'success' => true,
				'message' => __( 'Workflow triggered successfully!', 'deploy-forge' ),
			);
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to trigger workflow.', 'deploy-forge' ),
		);
	}

	/**
	 * Get available workflows for a repository.
	 *
	 * SECURITY: Only returns workflows with workflow_dispatch trigger enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $repo_owner Repository owner.
	 * @param string $repo_name  Repository name.
	 * @return array Result array with success status and workflows.
	 */
	public function get_workflows( string $repo_owner, string $repo_name ): array {
		// Validate repository owner and name to prevent injection.
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $repo_owner ) || ! preg_match( '/^[a-zA-Z0-9_.-]+$/', $repo_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid repository owner or name format.', 'deploy-forge' ),
			);
		}

		$endpoint = "/repos/{$repo_owner}/{$repo_name}/actions/workflows";

		$response = $this->request( 'GET', $endpoint );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 !== $response['status'] ) {
			return array(
				'success' => false,
				'message' => $response['body']['message'] ?? __( 'Failed to fetch workflows.', 'deploy-forge' ),
			);
		}

		// Filter workflows to only include those with workflow_dispatch trigger.
		// SECURITY: This prevents selecting workflows that can't be manually triggered.
		$all_workflows          = $response['body']['workflows'] ?? array();
		$dispatchable_workflows = array();

		$this->logger->log(
			'GitHub_API',
			'Processing workflows',
			array(
				'total_workflows' => count( $all_workflows ),
				'workflows_raw'   => $all_workflows,
			)
		);

		foreach ( $all_workflows as $workflow ) {
			// Check if workflow state is active.
			if ( isset( $workflow['state'] ) && 'active' !== $workflow['state'] ) {
				$this->logger->log(
					'GitHub_API',
					'Skipping inactive workflow',
					array(
						'name'  => $workflow['name'] ?? 'unknown',
						'state' => $workflow['state'] ?? 'unknown',
					)
				);
				continue;
			}

			// Extract filename from path (e.g., ".github/workflows/deploy.yml" -> "deploy.yml").
			$filename = basename( $workflow['path'] ?? '' );

			// Only include .yml and .yaml files.
			if ( ! preg_match( '/\.(yml|yaml)$/i', $filename ) ) {
				$this->logger->log(
					'GitHub_API',
					'Skipping non-yml/yaml workflow',
					array(
						'name'     => $workflow['name'] ?? 'unknown',
						'filename' => $filename,
					)
				);
				continue;
			}

			$workflow_data = array(
				'name'     => sanitize_text_field( $workflow['name'] ?? $filename ),
				'filename' => sanitize_file_name( $filename ),
				'path'     => sanitize_text_field( $workflow['path'] ?? '' ),
				'state'    => sanitize_text_field( $workflow['state'] ?? 'unknown' ),
			);

			$this->logger->log( 'GitHub_API', 'Adding workflow to result', $workflow_data );

			$dispatchable_workflows[] = $workflow_data;
		}

		$this->logger->log(
			'GitHub_API',
			'Workflows processed',
			array(
				'total_filtered' => count( $dispatchable_workflows ),
				'workflows'      => $dispatchable_workflows,
			)
		);

		return array(
			'success'     => true,
			'workflows'   => $dispatchable_workflows,
			'total_count' => count( $dispatchable_workflows ),
		);
	}

	/**
	 * Get workflow run status.
	 *
	 * @since 1.0.0
	 *
	 * @param int $run_id The workflow run ID.
	 * @return array Result array with success status and data.
	 */
	public function get_workflow_run_status( int $run_id ): array {
		$endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/runs/{$run_id}";

		$response = $this->request( 'GET', $endpoint );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			return array(
				'success' => true,
				'data'    => $response['body'],
			);
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get workflow run status.', 'deploy-forge' ),
		);
	}

	/**
	 * Get latest workflow runs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum number of runs to retrieve.
	 * @return array Result array with success status and data.
	 */
	public function get_latest_workflow_runs( int $limit = 5 ): array {
		$workflow_name = $this->settings->get( 'github_workflow_name' );
		$endpoint      = "/repos/{$this->settings->get_repo_full_name()}/actions/workflows/{$workflow_name}/runs";

		// Use transient cache.
		$cache_key = 'deploy_forge_runs_' . md5( $endpoint );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->request( 'GET', $endpoint, array( 'per_page' => $limit ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			$body          = $response['body'];
			$workflow_runs = is_object( $body )
				? ( $body->workflow_runs ?? array() )
				: ( $body['workflow_runs'] ?? array() );

			$result = array(
				'success' => true,
				'data'    => $workflow_runs,
			);

			set_transient( $cache_key, $result, 2 * MINUTE_IN_SECONDS );
			return $result;
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get workflow runs.', 'deploy-forge' ),
		);
	}

	/**
	 * Get artifacts for a workflow run.
	 *
	 * @since 1.0.0
	 *
	 * @param int $run_id The workflow run ID.
	 * @return array Result array with success status and data.
	 */
	public function get_workflow_artifacts( int $run_id ): array {
		$endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/runs/{$run_id}/artifacts";

		$response = $this->request( 'GET', $endpoint );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			// Response body is always an array (parsed from JSON).
			$artifacts = $response['body']['artifacts'] ?? array();

			return array(
				'success' => true,
				'data'    => $artifacts,
			);
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get artifacts.', 'deploy-forge' ),
		);
	}

	/**
	 * Download artifact via Deploy Forge backend using direct download URL.
	 *
	 * This method uses a two-step process to minimize bandwidth usage:
	 * 1. Request a direct download URL from the backend (short-lived signed URL from GitHub CDN)
	 * 2. Download directly from GitHub CDN, bypassing Vercel bandwidth
	 *
	 * @since 1.0.0
	 *
	 * @param int|string  $artifact_id          The artifact ID.
	 * @param string      $destination          The destination file path.
	 * @param string|null $direct_url_endpoint  Optional URL path for direct download endpoint.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function download_artifact( int|string $artifact_id, string $destination, ?string $direct_url_endpoint = null ): bool|WP_Error {
		$api_key = $this->settings->get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Not connected to Deploy Forge', 'deploy-forge' ) );
		}

		$this->logger->log( 'GitHub_API', "Downloading artifact #$artifact_id to $destination" );

		// Get backend URL.
		$backend_url = $this->settings->get_backend_url();

		// Step 1: Get the direct download URL from Deploy Forge backend.
		// This returns a short-lived signed URL from GitHub CDN.
		if ( ! empty( $direct_url_endpoint ) ) {
			$url_endpoint = $backend_url . $direct_url_endpoint;
		} else {
			$url_endpoint = $backend_url . '/api/plugin/github/artifacts/' . $artifact_id . '/download-url';
		}

		$this->logger->log(
			'GitHub_API',
			'Requesting direct download URL from Deploy Forge backend',
			array(
				'url_endpoint'           => $url_endpoint,
				'using_webhook_endpoint' => ! empty( $direct_url_endpoint ),
			)
		);

		$url_response = wp_remote_get(
			$url_endpoint,
			array(
				'headers' => array(
					'X-API-Key' => $api_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $url_response ) ) {
			$this->logger->error( 'GitHub_API', 'Failed to get direct download URL', $url_response );
			return $url_response;
		}

		$url_status = wp_remote_retrieve_response_code( $url_response );
		$url_body   = wp_remote_retrieve_body( $url_response );
		$url_data   = json_decode( $url_body, true );

		if ( 200 !== $url_status || empty( $url_data['success'] ) || empty( $url_data['downloadUrl'] ) ) {
			$error_message = $url_data['error'] ?? 'Failed to get direct download URL';
			$this->logger->error(
				'GitHub_API',
				'Failed to get direct download URL',
				array(
					'status_code' => $url_status,
					'error'       => $error_message,
				)
			);
			return new WP_Error( 'url_failed', $error_message );
		}

		$direct_download_url = $url_data['downloadUrl'];
		$expires_in          = $url_data['expiresInSeconds'] ?? 55;

		$this->logger->log(
			'GitHub_API',
			'Got direct download URL',
			array(
				'expires_in_seconds' => $expires_in,
				'artifact_name'      => $url_data['artifact']['name'] ?? 'unknown',
				'artifact_size'      => $url_data['artifact']['sizeInBytes'] ?? 0,
			)
		);

		// Step 2: Download directly from GitHub CDN using the signed URL.
		// This bypasses Vercel bandwidth entirely.
		$this->logger->log( 'GitHub_API', 'Downloading artifact directly from GitHub CDN' );

		$download_args = array(
			'timeout'  => 300,
			'stream'   => true,
			'filename' => $destination,
		);

		$download_response = wp_remote_get( $direct_download_url, $download_args );

		if ( is_wp_error( $download_response ) ) {
			$this->logger->error( 'GitHub_API', 'Direct artifact download failed', $download_response );
			return $download_response;
		}

		$download_status = wp_remote_retrieve_response_code( $download_response );

		$this->logger->log(
			'GitHub_API',
			'Direct artifact download response',
			array(
				'status_code' => $download_status,
				'file_exists' => file_exists( $destination ),
				'file_size'   => file_exists( $destination ) ? filesize( $destination ) : 0,
			)
		);

		if ( 200 === $download_status && file_exists( $destination ) && filesize( $destination ) > 0 ) {
			$this->logger->log( 'GitHub_API', 'Artifact download successful (direct from GitHub CDN)!' );
			return true;
		}

		$this->logger->error(
			'GitHub_API',
			'Artifact download failed',
			array(
				'status_code' => $download_status,
				'file_exists' => file_exists( $destination ),
				'file_size'   => file_exists( $destination ) ? filesize( $destination ) : 0,
			)
		);

		return new WP_Error(
			'download_failed',
			// Translators: %d is the HTTP status code returned by the download request.
			sprintf( __( 'Failed to download artifact. Status: %d', 'deploy-forge' ), $download_status )
		);
	}

	/**
	 * Download repository as ZIP archive (for direct clone deployment).
	 *
	 * Uses Deploy Forge clone token endpoint to get temporary credentials.
	 *
	 * @since 1.0.0
	 *
	 * @param string $ref         The branch or ref to download.
	 * @param string $destination The destination file path.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function download_repository( string $ref, string $destination ): bool|WP_Error {
		$api_key = $this->settings->get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Not connected to Deploy Forge', 'deploy-forge' ) );
		}

		$this->logger->log( 'GitHub_API', "Downloading repository at ref: $ref to $destination" );

		// Get backend URL.
		$backend_url = $this->settings->get_backend_url();

		// Request clone token from Deploy Forge.
		$clone_token_url = $backend_url . '/api/plugin/github/clone-token';

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-API-Key'    => $api_key,
			),
			'timeout' => 30,
		);

		$this->logger->log( 'GitHub_API', 'Requesting clone token from Deploy Forge' );

		$response = wp_remote_post( $clone_token_url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'GitHub_API', 'Failed to get clone token', $response );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$parsed_body = json_decode( $body, true );

		if ( $status_code >= 400 || ! isset( $parsed_body['success'] ) || ! $parsed_body['success'] ) {
			$error_message = $parsed_body['error'] ?? 'Failed to get clone credentials';
			$this->logger->error(
				'GitHub_API',
				'Backend error getting clone token',
				array(
					'status'  => $status_code,
					'message' => $error_message,
				)
			);
			return new WP_Error( 'backend_error', $error_message );
		}

		$clone_url = $parsed_body['cloneUrl'] ?? null;
		$repo_ref  = $parsed_body['ref'] ?? $ref;

		if ( empty( $clone_url ) ) {
			$this->logger->error( 'GitHub_API', 'No clone URL in response', $parsed_body );
			return new WP_Error( 'no_clone_url', __( 'Could not get repository clone URL', 'deploy-forge' ) );
		}

		// Extract owner and repo from clone URL.
		// Format: https://x-access-token:TOKEN@github.com/owner/repo.git.
		if ( preg_match( '#github\.com/([^/]+)/([^/]+?)(?:\.git)?$#', $clone_url, $matches ) ) {
			$owner = $matches[1];
			$repo  = $matches[2];

			// Use GitHub's zipball endpoint to download specific ref.
			// Replace .git suffix and use API endpoint.
			$download_url = "https://api.github.com/repos/{$owner}/{$repo}/zipball/{$repo_ref}";

			// Extract token from clone URL.
			if ( preg_match( '#x-access-token:([^@]+)@#', $clone_url, $token_matches ) ) {
				$token = $token_matches[1];

				$this->logger->log( 'GitHub_API', 'Downloading repository archive from GitHub...' );

				// Download using the token.
				$download_args = array(
					'headers'  => array(
						'Authorization' => 'token ' . $token,
						'Accept'        => 'application/vnd.github+json',
					),
					'timeout'  => 300,
					'stream'   => true,
					'filename' => $destination,
				);

				$download_response = wp_remote_get( $download_url, $download_args );

				if ( is_wp_error( $download_response ) ) {
					$this->logger->error( 'GitHub_API', 'Repository download failed', $download_response );
					return $download_response;
				}

				$download_status = wp_remote_retrieve_response_code( $download_response );

				$this->logger->log(
					'GitHub_API',
					'Repository download response',
					array(
						'status_code' => $download_status,
						'file_exists' => file_exists( $destination ),
						'file_size'   => file_exists( $destination ) ? filesize( $destination ) : 0,
					)
				);

				if ( 200 === $download_status && file_exists( $destination ) && filesize( $destination ) > 0 ) {
					$this->logger->log( 'GitHub_API', 'Repository download successful!' );
					return true;
				}

				$this->logger->error(
					'GitHub_API',
					'Repository download failed',
					array(
						'status_code' => $download_status,
						'file_exists' => file_exists( $destination ),
						'file_size'   => file_exists( $destination ) ? filesize( $destination ) : 0,
					)
				);

				return new WP_Error(
					'download_failed',
					// Translators: %d is the HTTP status code returned by the download request.
					sprintf( __( 'Failed to download repository. Status: %d', 'deploy-forge' ), $download_status )
				);
			}
		}

		return new WP_Error( 'invalid_clone_url', __( 'Invalid clone URL format', 'deploy-forge' ) );
	}

	/**
	 * Get recent commits.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Maximum number of commits to retrieve.
	 * @return array Result array with success status and data.
	 */
	public function get_recent_commits( int $limit = 10 ): array {
		$branch   = $this->settings->get( 'github_branch' );
		$endpoint = "/repos/{$this->settings->get_repo_full_name()}/commits";

		// Use transient cache.
		$cache_key = 'deploy_forge_commits_' . md5( $endpoint . $branch );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->request(
			'GET',
			$endpoint,
			array(
				'sha'      => $branch,
				'per_page' => $limit,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			$result = array(
				'success' => true,
				'data'    => $response['body'],
			);

			set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
			return $result;
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get commits.', 'deploy-forge' ),
		);
	}

	/**
	 * Get commit details.
	 *
	 * @since 1.0.0
	 *
	 * @param string $commit_hash The commit SHA.
	 * @return array Result array with success status and data.
	 */
	public function get_commit_details( string $commit_hash ): array {
		$endpoint = "/repos/{$this->settings->get_repo_full_name()}/commits/{$commit_hash}";

		$response = $this->request( 'GET', $endpoint );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			return array(
				'success' => true,
				'data'    => $response['body'],
			);
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get commit details.', 'deploy-forge' ),
		);
	}

	/**
	 * Make a request to GitHub API (proxied through Deploy Forge backend).
	 *
	 * @since 1.0.0
	 *
	 * @param string     $method   HTTP method.
	 * @param string     $endpoint API endpoint.
	 * @param array|null $data     Optional request data.
	 * @return array|WP_Error Response array or WP_Error on failure.
	 */
	private function request( string $method, string $endpoint, ?array $data = null ): array|WP_Error {
		$api_key = $this->settings->get_api_key();

		if ( empty( $api_key ) ) {
			$error = new WP_Error( 'no_api_key', __( 'Not connected to Deploy Forge. Please connect from settings.', 'deploy-forge' ) );
			$this->logger->error( 'GitHub_API', 'No API key configured', $error );
			return $error;
		}

		// Get backend URL.
		$backend_url = $this->settings->get_backend_url();

		// Prepare proxy request.
		$proxy_url = $backend_url . '/api/plugin/github/proxy';

		// For GET requests, append data as query parameters to the endpoint.
		// GET/HEAD/OPTIONS requests cannot have a body.
		$request_endpoint = $endpoint;
		$request_data     = null;

		if ( 'GET' === $method && ! empty( $data ) ) {
			// Append query parameters to endpoint.
			$query_string     = http_build_query( $data );
			$request_endpoint = $endpoint . ( strpos( $endpoint, '?' ) !== false ? '&' : '?' ) . $query_string;
		} elseif ( 'GET' !== $method && ! empty( $data ) ) {
			// For POST/PUT/PATCH/DELETE, include data in body.
			$request_data = $data;
		}

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-API-Key'    => $api_key,
			),
			'body'    => wp_json_encode(
				array(
					'method'   => $method,
					'endpoint' => $request_endpoint,
					'data'     => $request_data,
				)
			),
			'timeout' => 30,
		);

		// Log request.
		$this->logger->log_api_request( $method, $request_endpoint, $data ?: array(), array( 'via_proxy' => true ) );

		$response = wp_remote_post( $proxy_url, $args );

		if ( is_wp_error( $response ) ) {
			$this->logger->log_api_response( $endpoint, 0, null, $response->get_error_message() );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$parsed_body = json_decode( $body, true );

		// Check for backend errors.
		if ( $status_code >= 400 || ( isset( $parsed_body['error'] ) && $parsed_body['error'] ) ) {
			$error_message = $parsed_body['message'] ?? 'Unknown error from backend';
			$this->logger->log_api_response( $endpoint, $status_code, $parsed_body, $error_message );

			return new WP_Error(
				'backend_error',
				$error_message,
				array(
					'status' => $status_code,
					'data'   => $parsed_body,
				)
			);
		}

		// Extract GitHub API response from backend response.
		$github_status  = $parsed_body['status'] ?? 200;
		$github_data    = $parsed_body['data'] ?? null;
		$github_headers = $parsed_body['headers'] ?? array();

		// Log response.
		$this->logger->log_api_response(
			$endpoint,
			$github_status,
			$github_data,
			$github_status >= 400 ? "HTTP $github_status" : null
		);

		// Check rate limiting (if headers are provided).
		if ( isset( $github_headers['x-ratelimit-remaining'] ) ) {
			$rate_limit_remaining = (int) $github_headers['x-ratelimit-remaining'];
			if ( $rate_limit_remaining < 10 ) {
				$this->logger->log( 'GitHub_API', "Rate limit warning: $rate_limit_remaining requests remaining" );
				do_action( 'deploy_forge_rate_limit_warning', $rate_limit_remaining );
			}
		}

		return array(
			'status'  => $github_status,
			'body'    => $github_data, // Keep original type - don't force to object.
			'headers' => $github_headers,
		);
	}

	/**
	 * Get rate limit information.
	 *
	 * @since 1.0.0
	 *
	 * @return array Result array with success status and data.
	 */
	public function get_rate_limit(): array {
		$response = $this->request( 'GET', '/rate_limit' );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			return array(
				'success' => true,
				'data'    => $response['body'],
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to get rate limit information.', 'deploy-forge' ),
		);
	}

	/**
	 * Get user's repositories (for repo selector).
	 *
	 * @since 1.0.0
	 *
	 * @param int $per_page Number of repositories per page.
	 * @return array Result array with success status and data.
	 */
	public function get_user_repositories( int $per_page = 100 ): array {
		$cache_key = 'deploy_forge_user_repos';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->request(
			'GET',
			'/user/repos',
			array(
				'per_page'    => $per_page,
				'sort'        => 'updated',
				'affiliation' => 'owner,collaborator,organization_member',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			$repos = is_array( $response['body'] ) ? $response['body'] : array();

			// Format repos for dropdown.
			$formatted_repos = array_map(
				function ( $repo ) {
					return array(
						'id'             => $repo['id'] ?? 0,
						'full_name'      => $repo['full_name'] ?? '',
						'name'           => $repo['name'] ?? '',
						'owner'          => $repo['owner']['login'] ?? '',
						'private'        => $repo['private'] ?? false,
						'default_branch' => $repo['default_branch'] ?? 'main',
						'updated_at'     => $repo['updated_at'] ?? '',
						'has_workflows'  => $repo['has_actions'] ?? false,
					);
				},
				$repos
			);

			$result = array(
				'success' => true,
				'data'    => $formatted_repos,
			);

			set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
			return $result;
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get repositories.', 'deploy-forge' ),
		);
	}

	/**
	 * Get repository workflows (for workflow selector).
	 *
	 * @since 1.0.0
	 *
	 * @param string $owner Repository owner.
	 * @param string $repo  Repository name.
	 * @return array Result array with success status and data.
	 */
	public function get_repository_workflows( string $owner, string $repo ): array {
		$cache_key = 'deploy_forge_workflows_' . md5( $owner . $repo );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$endpoint = "/repos/{$owner}/{$repo}/actions/workflows";
		$response = $this->request( 'GET', $endpoint );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			$workflows = $response['body']['workflows'] ?? array();

			// Format workflows for dropdown.
			$formatted_workflows = array_map(
				function ( $workflow ) {
					$path_parts = explode( '/', $workflow['path'] ?? '' );
					$filename   = end( $path_parts );

					return array(
						'id'       => $workflow['id'] ?? 0,
						'name'     => $workflow['name'] ?? '',
						'path'     => $workflow['path'] ?? '',
						'filename' => $filename,
						'state'    => $workflow['state'] ?? 'unknown',
					);
				},
				$workflows
			);

			$result = array(
				'success' => true,
				'data'    => $formatted_workflows,
			);

			set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
			return $result;
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get workflows.', 'deploy-forge' ),
		);
	}

	/**
	 * Cancel a workflow run.
	 *
	 * @since 1.0.0
	 *
	 * @param int $run_id The workflow run ID.
	 * @return array Result array with success status and message.
	 */
	public function cancel_workflow_run( int $run_id ): array {
		$endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/runs/{$run_id}/cancel";

		$response = $this->request( 'POST', $endpoint );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		// GitHub returns 202 Accepted for successful cancellation.
		if ( 202 === $response['status'] ) {
			return array(
				'success' => true,
				'message' => __( 'Workflow run cancellation requested successfully!', 'deploy-forge' ),
			);
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to cancel workflow run.', 'deploy-forge' ),
		);
	}

	/**
	 * Get installation repositories (repos accessible by GitHub App).
	 *
	 * This fetches only repos that the GitHub App has been granted access to.
	 *
	 * @since 1.0.0
	 *
	 * @return array Result array with success status and data.
	 */
	public function get_installation_repositories(): array {
		$cache_key = 'deploy_forge_installation_repos';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Use the installation/repositories endpoint.
		$response = $this->request( 'GET', '/installation/repositories' );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			$body = $response['body'];

			// Debug: Log what we got from the backend.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( '=== INSTALLATION REPOS DEBUG ===' );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Body type: ' . gettype( $body ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			error_log( 'Body content: ' . print_r( $body, true ) );

			if ( is_object( $body ) && isset( $body->repositories ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Found repositories in object, count: ' . count( $body->repositories ) );
				if ( ! empty( $body->repositories ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
					error_log( 'First repo: ' . print_r( $body->repositories[0], true ) );
				}
			} elseif ( is_array( $body ) && isset( $body['repositories'] ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Found repositories in array, count: ' . count( $body['repositories'] ) );
				if ( ! empty( $body['repositories'] ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
					error_log( 'First repo: ' . print_r( $body['repositories'][0], true ) );
				}
			} else {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'No repositories found in expected structure' );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
				error_log( 'Available keys: ' . print_r( is_object( $body ) ? get_object_vars( $body ) : array_keys( $body ), true ) );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '=== END DEBUG ===' );

			// Handle both object and array responses.
			$repos = is_object( $body ) && isset( $body->repositories )
				? $body->repositories
				: ( is_array( $body ) && isset( $body['repositories'] ) ? $body['repositories'] : array() );

			// Format repos for dropdown.
			$formatted_repos = array_map(
				function ( $repo ) {
					// Handle both object and array.
					$repo_data = is_object( $repo ) ? (array) $repo : $repo;
					$owner     = is_object( $repo->owner ?? null ) ? (array) $repo->owner : ( $repo_data['owner'] ?? array() );

					$formatted = array(
						'id'             => $repo_data['id'] ?? 0,
						'full_name'      => $repo_data['full_name'] ?? '',
						'name'           => $repo_data['name'] ?? '',
						'owner'          => is_array( $owner ) ? ( $owner['login'] ?? '' ) : ( $owner->login ?? '' ),
						'private'        => $repo_data['private'] ?? false,
						'default_branch' => $repo_data['default_branch'] ?? 'main',
						'updated_at'     => $repo_data['updated_at'] ?? '',
					);

					// Log first repo for debugging.
					static $logged = false;
					if ( ! $logged ) {
						$this->logger->log(
							'GitHub_API',
							'Sample formatted repo',
							array(
								'raw_repo'  => $repo_data,
								'formatted' => $formatted,
							)
						);
						$logged = true;
					}

					return $formatted;
				},
				$repos
			);

			$result = array(
				'success' => true,
				'data'    => $formatted_repos,
			);

			// Cache for 5 minutes.
			set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
			return $result;
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get installation repositories.', 'deploy-forge' ),
		);
	}

	/**
	 * Report deployment status back to Deploy Forge API.
	 *
	 * This allows WordPress to sync deployment outcomes (success/failure)
	 * back to the Deploy Forge dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $remote_deployment_id The Deploy Forge deployment ID.
	 * @param bool        $success              Whether the deployment succeeded.
	 * @param string|null $error_message        Error message if failed.
	 * @param string|null $logs                 Deployment logs.
	 * @param array|null  $context              Additional debugging context.
	 * @return array Result with success status.
	 */
	public function report_deployment_status(
		string $remote_deployment_id,
		bool $success,
		?string $error_message = null,
		?string $logs = null,
		?array $context = null
	): array {
		$api_key = $this->settings->get_api_key();

		if ( empty( $api_key ) ) {
			$this->logger->log( 'GitHub_API', 'Cannot report deployment status: not connected to Deploy Forge' );
			return array(
				'success' => false,
				'message' => __( 'Not connected to Deploy Forge', 'deploy-forge' ),
			);
		}

		if ( empty( $remote_deployment_id ) ) {
			$this->logger->log( 'GitHub_API', 'Cannot report deployment status: no remote deployment ID' );
			return array(
				'success' => false,
				'message' => __( 'No remote deployment ID available', 'deploy-forge' ),
			);
		}

		$backend_url = $this->settings->get_backend_url();
		$endpoint    = $backend_url . '/api/plugin/deployments/complete';

		$body = array(
			'deploymentId' => $remote_deployment_id,
			'success'      => $success,
		);

		if ( $error_message ) {
			$body['errorMessage'] = $error_message;
		}

		if ( $logs ) {
			$body['logs'] = $logs;
		}

		if ( $context ) {
			$body['context'] = $context;
		}

		$this->logger->log(
			'GitHub_API',
			'Reporting deployment status to Deploy Forge',
			array(
				'remote_deployment_id' => $remote_deployment_id,
				'success'              => $success,
				'has_error_message'    => ! empty( $error_message ),
				'has_logs'             => ! empty( $logs ),
				'has_context'          => ! empty( $context ),
			)
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'GitHub_API', 'Failed to report deployment status', $response );
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$parsed_body   = json_decode( $response_body, true );

		$this->logger->log(
			'GitHub_API',
			'Deployment status report response',
			array(
				'status_code' => $status_code,
				'response'    => $parsed_body,
			)
		);

		if ( $status_code >= 400 || empty( $parsed_body['success'] ) ) {
			$error = $parsed_body['error'] ?? 'Unknown error';
			return array(
				'success' => false,
				'message' => $error,
			);
		}

		return array(
			'success' => true,
			'message' => $parsed_body['message'] ?? 'Status reported successfully',
		);
	}

	/**
	 * Trigger a deployment via Deploy Forge API.
	 *
	 * This creates a deployment record on the Deploy Forge website and optionally
	 * triggers the GitHub workflow. Used for manual deployments initiated from WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $ref        Branch or ref to deploy (defaults to configured branch).
	 * @param string|null $commit_sha Specific commit SHA to deploy.
	 * @return array Result with deployment info or error.
	 */
	public function trigger_remote_deployment( ?string $ref = null, ?string $commit_sha = null ): array {
		$api_key = $this->settings->get_api_key();

		if ( empty( $api_key ) ) {
			$this->logger->log( 'GitHub_API', 'Cannot trigger remote deployment: not connected to Deploy Forge' );
			return array(
				'success' => false,
				'message' => __( 'Not connected to Deploy Forge', 'deploy-forge' ),
			);
		}

		$backend_url = $this->settings->get_backend_url();
		$endpoint    = $backend_url . '/api/plugin/deployments/trigger';

		$body = array();

		if ( $ref ) {
			$body['ref'] = $ref;
		}

		if ( $commit_sha ) {
			$body['commitSha'] = $commit_sha;
		}

		$this->logger->log(
			'GitHub_API',
			'Triggering remote deployment via Deploy Forge API',
			array(
				'ref'        => $ref,
				'commit_sha' => $commit_sha,
			)
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-Key'    => $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'GitHub_API', 'Failed to trigger remote deployment', $response );
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$parsed_body   = json_decode( $response_body, true );

		$this->logger->log(
			'GitHub_API',
			'Remote deployment trigger response',
			array(
				'status_code' => $status_code,
				'response'    => $parsed_body,
			)
		);

		if ( $status_code >= 400 || empty( $parsed_body['success'] ) ) {
			$error = $parsed_body['error'] ?? 'Failed to trigger deployment';
			return array(
				'success' => false,
				'message' => $error,
			);
		}

		return array(
			'success'    => true,
			'message'    => $parsed_body['message'] ?? 'Deployment triggered',
			'deployment' => $parsed_body['deployment'] ?? null,
		);
	}

	/**
	 * Get branches for current repository.
	 *
	 * @since 1.0.0
	 *
	 * @return array Success status and branch list.
	 */
	public function get_branches(): array {
		$repo_owner = $this->settings->get( 'github_repo_owner' );
		$repo_name  = $this->settings->get( 'github_repo_name' );

		if ( empty( $repo_owner ) || empty( $repo_name ) ) {
			return array(
				'success' => false,
				'message' => __( 'Repository not configured', 'deploy-forge' ),
			);
		}

		$this->logger->log( 'GitHub_API', 'Fetching branches for ' . $repo_owner . '/' . $repo_name );

		// Cache key based on repo.
		$cache_key = 'deploy_forge_branches_' . md5( $repo_owner . '/' . $repo_name );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			$this->logger->log( 'GitHub_API', 'Returning cached branches' );
			return $cached;
		}

		// Get branches from GitHub API.
		$endpoint = '/repos/' . rawurlencode( $repo_owner ) . '/' . rawurlencode( $repo_name ) . '/branches';
		$response = $this->request( 'GET', $endpoint );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( 200 === $response['status'] ) {
			$branches = $response['body'];

			if ( is_array( $branches ) ) {
				// Extract just branch names.
				$branch_names = array_map(
					function ( $branch ) {
						return is_object( $branch ) ? $branch->name : $branch['name'];
					},
					$branches
				);

				$result = array(
					'success' => true,
					'data'    => $branch_names,
				);

				$this->logger->log( 'GitHub_API', 'Successfully fetched ' . count( $branch_names ) . ' branches' );

				// Cache for 5 minutes.
				set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
				return $result;
			}
		}

		return array(
			'success' => false,
			'message' => $response['body']['message'] ?? __( 'Failed to get branches.', 'deploy-forge' ),
		);
	}
}
