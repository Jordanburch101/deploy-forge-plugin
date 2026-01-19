<?php
/**
 * Deployment manager class
 *
 * Orchestrates the entire deployment workflow including downloading,
 * extracting, and deploying theme files from GitHub.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 *
 * @phpstan-type ZipArchive \ZipArchive
 * @phpstan-type RecursiveIteratorIterator \RecursiveIteratorIterator
 * @phpstan-type RecursiveDirectoryIterator \RecursiveDirectoryIterator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Deployment_Manager
 *
 * Handles the complete deployment lifecycle including triggering builds,
 * downloading artifacts, creating backups, and deploying theme files.
 *
 * @since 1.0.0
 */
class Deploy_Forge_Deployment_Manager {


	/**
	 * Settings instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Settings
	 */
	private Deploy_Forge_Settings $settings;

	/**
	 * GitHub API instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_GitHub_API
	 */
	private Deploy_Forge_GitHub_API $github_api;

	/**
	 * Database instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Database
	 */
	private Deploy_Forge_Database $database;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Debug_Logger
	 */
	private Deploy_Forge_Debug_Logger $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Deploy_Forge_Settings     $settings   Settings instance.
	 * @param Deploy_Forge_GitHub_API   $github_api GitHub API instance.
	 * @param Deploy_Forge_Database     $database   Database instance.
	 * @param Deploy_Forge_Debug_Logger $logger     Logger instance.
	 */
	public function __construct( Deploy_Forge_Settings $settings, Deploy_Forge_GitHub_API $github_api, Deploy_Forge_Database $database, Deploy_Forge_Debug_Logger $logger ) {
		$this->settings   = $settings;
		$this->github_api = $github_api;
		$this->database   = $database;
		$this->logger     = $logger;
	}

	/**
	 * Initialize WP_Filesystem for file operations.
	 *
	 * @since 1.0.46
	 *
	 * @return \WP_Filesystem_Base|false WP_Filesystem instance or false on failure.
	 */
	private function get_filesystem() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Initialize with direct method to avoid credential prompts in background.
		if ( ! WP_Filesystem( false, false, true ) ) {
			return false;
		}

		return $wp_filesystem;
	}

	/**
	 * Start a new deployment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $commit_hash  The commit hash to deploy.
	 * @param string $trigger_type How the deployment was triggered (manual, webhook, auto).
	 * @param int    $user_id      The user ID who triggered the deployment.
	 * @param array  $commit_data  Additional commit data.
	 * @return int|false|array Deployment ID on success, false on failure, or array with error info.
	 */
	public function start_deployment( string $commit_hash, string $trigger_type = 'manual', int $user_id = 0, array $commit_data = array() ): int|false|array {
		$this->logger->log_deployment_step(
			0,
			'Start Deployment',
			'initiated',
			array(
				'commit_hash'  => $commit_hash,
				'trigger_type' => $trigger_type,
				'user_id'      => $user_id,
				'commit_data'  => $commit_data,
			)
		);

		// Check if there's a deployment currently building.
		$building_deployment = $this->database->get_building_deployment();

		if ( $building_deployment ) {
			$this->logger->log(
				'Deployment',
				'Found existing building deployment',
				array(
					'existing_deployment_id' => $building_deployment->id,
					'existing_status'        => $building_deployment->status,
					'trigger_type'           => $trigger_type,
				)
			);

			// If manual deploy, block and return error with deployment info.
			if ( 'manual' === $trigger_type ) {
				return array(
					'error'               => 'deployment_in_progress',
					'message'             => __( 'A deployment is already in progress. Please cancel it before starting a new one.', 'deploy-forge' ),
					'building_deployment' => $building_deployment,
				);
			}

			// If webhook/auto deploy, cancel existing deployment first.
			if ( in_array( $trigger_type, array( 'webhook', 'auto' ), true ) ) {
				$this->logger->log(
					'Deployment',
					'Auto-cancelling existing deployment',
					array(
						'existing_deployment_id' => $building_deployment->id,
					)
				);

				$cancel_result = $this->cancel_deployment( $building_deployment->id );

				if ( ! $cancel_result ) {
					$this->logger->error( 'Deployment', 'Failed to cancel existing deployment' );
					return false;
				}

				$this->logger->log( 'Deployment', 'Successfully cancelled existing deployment' );
			}
		}

		// Check deployment method - prefer connection data over settings.
		$connection_data   = $this->settings->get_connection_data();
		$deployment_method = ! empty( $connection_data['deployment_method'] )
			? $connection_data['deployment_method']
			: $this->settings->get( 'deployment_method', 'github_actions' );

		// For manual deployments, trigger via Deploy Forge API to create remote record.
		$remote_deployment_id = null;
		if ( 'manual' === $trigger_type ) {
			$this->logger->log_deployment_step(
				0,
				'Remote Trigger',
				'initiating',
				array(
					'deployment_method' => $deployment_method,
				)
			);

			$branch        = $this->settings->get( 'github_branch', 'main' );
			$remote_result = $this->github_api->trigger_remote_deployment( $branch, $commit_hash );

			if ( ! $remote_result['success'] ) {
				$this->logger->error(
					'Deployment',
					'Failed to trigger remote deployment',
					array(
						'error' => $remote_result['message'],
					)
				);
				return array(
					'error'   => 'remote_trigger_failed',
					'message' => $remote_result['message'],
				);
			}

			$remote_deployment_id = $remote_result['deployment']['id'] ?? null;
			$remote_status        = $remote_result['deployment']['status'] ?? 'pending';

			$this->logger->log_deployment_step(
				0,
				'Remote Deployment',
				'created',
				array(
					'remote_deployment_id' => $remote_deployment_id,
					'remote_status'        => $remote_status,
				)
			);
		}

		// Create local deployment record.
		$deployment_id = $this->database->insert_deployment(
			array(
				'commit_hash'          => $commit_hash,
				'commit_message'       => $commit_data['commit_message'] ?? '',
				'commit_author'        => $commit_data['commit_author'] ?? '',
				'commit_date'          => $commit_data['commit_date'] ?? current_time( 'mysql' ),
				'status'               => 'pending',
				'trigger_type'         => $trigger_type,
				'triggered_by_user_id' => $user_id,
				'remote_deployment_id' => $remote_deployment_id,
				'deployment_method'    => $deployment_method,
			)
		);

		if ( ! $deployment_id ) {
			$this->logger->error( 'Deployment', 'Failed to create deployment record in database' );
			return false;
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Database Record',
			'created',
			array(
				'deployment_id'        => $deployment_id,
				'remote_deployment_id' => $remote_deployment_id,
			)
		);

		$this->logger->log_deployment_step(
			$deployment_id,
			'Deployment Method',
			'determined',
			array(
				'deployment_method'    => $deployment_method,
				'from_connection_data' => ! empty( $connection_data['deployment_method'] ),
			)
		);

		if ( 'direct_clone' === $deployment_method ) {
			// Direct clone - skip GitHub Actions, download and deploy immediately.
			$this->logger->log_deployment_step( $deployment_id, 'Direct Clone Mode', 'started' );
			$direct_result = $this->direct_clone_deployment( $deployment_id, $commit_hash );

			if ( ! $direct_result ) {
				$this->logger->error( 'Deployment', "Deployment #$deployment_id direct clone failed" );
				$error_message = __( 'Failed to deploy via direct clone.', 'deploy-forge' );
				$this->database->update_deployment(
					$deployment_id,
					array(
						'status'        => 'failed',
						'error_message' => $error_message,
					)
				);
				$this->report_status_to_backend( $deployment_id, false, $error_message );
				return false;
			}

			return $deployment_id;
		}

		// GitHub Actions workflow (default).
		// For manual deployments, the API already triggered the workflow, so just update local status.
		if ( 'manual' === $trigger_type && $remote_deployment_id ) {
			$this->logger->log_deployment_step(
				$deployment_id,
				'Workflow Triggered',
				'via_api',
				array(
					'remote_deployment_id' => $remote_deployment_id,
				)
			);
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status' => 'building',
				)
			);
			return $deployment_id;
		}

		// For non-manual (webhook) deployments, trigger locally.
		$workflow_result = $this->trigger_github_build( $deployment_id, $commit_hash );

		if ( ! $workflow_result ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id failed to trigger workflow" );
			$error_message = __( 'Failed to trigger GitHub Actions workflow.', 'deploy-forge' );
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => $error_message,
				)
			);
			return false;
		}

		return $deployment_id;
	}

	/**
	 * Trigger GitHub Actions workflow.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $deployment_id The deployment ID.
	 * @param string|null $commit_hash   The commit hash.
	 * @return bool True on success, false on failure.
	 */
	public function trigger_github_build( int $deployment_id, ?string $commit_hash = null ): bool {
		// Get workflow name - prefer connection data over settings.
		$connection_data = $this->settings->get_connection_data();
		$workflow_path   = $connection_data['workflow_path'] ?? '';
		$workflow_name   = ! empty( $workflow_path )
			? basename( $workflow_path )
			: $this->settings->get( 'github_workflow_name' );

		$branch = $this->settings->get( 'github_branch' );

		// Check if workflow is configured.
		if ( empty( $workflow_name ) ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id has no workflow configured" );
			$this->log_deployment( $deployment_id, 'Failed to trigger workflow: No workflow file configured. Please configure a workflow in Deploy Forge settings.' );
			return false;
		}

		$this->logger->log(
			'Deployment',
			'Triggering workflow',
			array(
				'deployment_id' => $deployment_id,
				'workflow_name' => $workflow_name,
				'workflow_path' => $workflow_path,
				'branch'        => $branch,
			)
		);

		// Trigger workflow.
		$result = $this->github_api->trigger_workflow( $workflow_name, $branch );

		if ( ! $result['success'] ) {
			$this->log_deployment( $deployment_id, 'Failed to trigger workflow: ' . $result['message'] );
			return false;
		}

		// Update status to building.
		$this->database->update_deployment(
			$deployment_id,
			array(
				'status'          => 'building',
				'deployment_logs' => 'GitHub Actions workflow triggered successfully.',
			)
		);

		$this->log_deployment( $deployment_id, 'GitHub Actions workflow triggered for commit: ' . $commit_hash );

		return true;
	}

	/**
	 * Deploy directly from repository clone (no GitHub Actions).
	 *
	 * Downloads repository ZIP at specific commit and deploys immediately.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $deployment_id The deployment ID.
	 * @param string $commit_hash   The commit hash.
	 * @return bool True on success, false on failure.
	 */
	private function direct_clone_deployment( int $deployment_id, string $commit_hash ): bool {
		// Create temp directory.
		$temp_dir = $this->get_temp_directory();
		$repo_zip = $temp_dir . '/repo-' . $deployment_id . '.zip';

		$this->logger->log_deployment_step(
			$deployment_id,
			'Direct Clone',
			'started',
			array(
				'commit_hash' => $commit_hash,
				'temp_dir'    => $temp_dir,
				'repo_zip'    => $repo_zip,
			)
		);

		$this->log_deployment( $deployment_id, 'Downloading repository from GitHub (direct clone)...' );

		// Update status to building (downloading).
		$this->database->update_deployment(
			$deployment_id,
			array(
				'status'          => 'building',
				'deployment_logs' => 'Downloading repository via direct clone...',
			)
		);

		// Download repository at specific commit.
		$download_result = $this->github_api->download_repository( $commit_hash, $repo_zip );

		if ( is_wp_error( $download_result ) ) {
			$error_message = $download_result->get_error_message();
			$this->logger->error( 'Deployment', "Deployment #$deployment_id repository download failed", $download_result );
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => $error_message,
				)
			);
			$this->log_deployment( $deployment_id, 'Download failed: ' . $error_message );
			$this->report_status_to_backend( $deployment_id, false, $error_message );
			return false;
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Repository Downloaded',
			'success',
			array(
				'file_size' => file_exists( $repo_zip ) ? filesize( $repo_zip ) : 0,
			)
		);

		$this->log_deployment( $deployment_id, 'Repository downloaded successfully.' );

		// Create backup if enabled.
		if ( $this->settings->get( 'create_backups' ) ) {
			$this->logger->log_deployment_step( $deployment_id, 'Create Backup', 'started' );
			$backup_path = $this->backup_current_theme( $deployment_id );
			if ( $backup_path ) {
				$this->database->update_deployment( $deployment_id, array( 'backup_path' => $backup_path ) );
				$this->log_deployment( $deployment_id, 'Backup created: ' . $backup_path );
				$this->logger->log_deployment_step(
					$deployment_id,
					'Backup Created',
					'success',
					array(
						'backup_path' => $backup_path,
					)
				);
			}
		}

		// Extract and deploy (reuse existing method).
		$this->extract_and_deploy( $deployment_id, $repo_zip );

		return true;
	}

	/**
	 * Check pending deployments (called by cron).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function check_pending_deployments(): void {
		$pending_deployments = $this->database->get_pending_deployments();

		foreach ( $pending_deployments as $deployment ) {
			if ( 'building' === $deployment->status ) {
				$this->check_build_status( $deployment->id );
			}
		}
	}

	/**
	 * Check build status for a deployment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deployment_id The deployment ID.
	 * @return void
	 */
	private function check_build_status( int $deployment_id ): void {
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment || ! $deployment->workflow_run_id ) {
			$this->logger->log_deployment_step(
				$deployment_id,
				'Check Build Status',
				'no_run_id',
				array(
					'has_deployment'  => ! empty( $deployment ),
					'workflow_run_id' => $deployment->workflow_run_id ?? null,
				)
			);
			// Try to find the workflow run for this commit.
			$this->find_workflow_run( $deployment_id );
			return;
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Check Build Status',
			'checking',
			array(
				'workflow_run_id' => $deployment->workflow_run_id,
			)
		);

		$result = $this->github_api->get_workflow_run_status( $deployment->workflow_run_id );

		if ( ! $result['success'] ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id failed to check build status", $result );
			$this->log_deployment( $deployment_id, 'Failed to check build status: ' . $result['message'] );
			return;
		}

		$run_data   = $result['data'];
		$status     = $run_data->status ?? '';
		$conclusion = $run_data->conclusion ?? '';

		$this->logger->log_deployment_step(
			$deployment_id,
			'Build Status Retrieved',
			'checked',
			array(
				'status'     => $status,
				'conclusion' => $conclusion,
			)
		);

		// Update build URL.
		if ( ! empty( $run_data->html_url ) ) {
			$this->database->update_deployment(
				$deployment_id,
				array(
					'build_url' => $run_data->html_url,
				)
			);
		}

		// Check if workflow is completed.
		if ( 'completed' === $status ) {
			if ( 'success' === $conclusion ) {
				$this->logger->log_deployment_step( $deployment_id, 'Build Completed', 'success' );
				$this->process_successful_build( $deployment_id );
			} else {
				$this->logger->error(
					'Deployment',
					"Deployment #$deployment_id build failed",
					array(
						'conclusion' => $conclusion,
					)
				);

				// Translators: %s is the GitHub Actions build conclusion status (e.g., "failure", "cancelled").
				$error_message = sprintf( __( 'Build failed with conclusion: %s', 'deploy-forge' ), $conclusion );

				$this->database->update_deployment(
					$deployment_id,
					array(
						'status'        => 'failed',
						'error_message' => $error_message,
					)
				);

				$this->log_deployment(
					$deployment_id,
					sprintf(
						'GitHub Actions build failed with conclusion: %s. Check the build URL for details.',
						$conclusion
					)
				);

				// Report build failure to platform with context.
				$additional_context = array(
					'failure_point'    => 'github_build',
					'build_conclusion' => $conclusion,
					'build_status'     => $status,
				);
				$this->report_status_to_backend( $deployment_id, false, $error_message, $additional_context );
			}
		}
	}

	/**
	 * Find workflow run for deployment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deployment_id The deployment ID.
	 * @return void
	 */
	private function find_workflow_run( int $deployment_id ): void {
		$deployment  = $this->database->get_deployment( $deployment_id );
		$commit_hash = $deployment->commit_hash;

		$this->logger->log_deployment_step(
			$deployment_id,
			'Find Workflow Run',
			'searching',
			array(
				'commit_hash' => $commit_hash,
			)
		);

		$result = $this->github_api->get_latest_workflow_runs( 10 );

		if ( ! $result['success'] ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id failed to get workflow runs", $result );
			return;
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Workflow Runs Retrieved',
			'success',
			array(
				'total_runs' => count( $result['data'] ),
			)
		);

		// Find workflow run matching this commit.
		foreach ( $result['data'] as $run ) {
			// Handle both array and object.
			$run_id  = is_object( $run ) ? $run->id : $run['id'];
			$run_sha = is_object( $run ) ? $run->head_sha : $run['head_sha'];
			$run_url = is_object( $run ) ? $run->html_url : $run['html_url'];

			$this->logger->log( 'Deployment', "Checking run #{$run_id} with SHA: {$run_sha} vs {$commit_hash}" );

			if ( $run_sha === $commit_hash ) {
				$this->database->update_deployment(
					$deployment_id,
					array(
						'workflow_run_id' => $run_id,
						'build_url'       => $run_url,
					)
				);
				$this->logger->log_deployment_step(
					$deployment_id,
					'Workflow Run Matched',
					'success',
					array(
						'workflow_run_id' => $run_id,
						'build_url'       => $run_url,
					)
				);
				$this->log_deployment( $deployment_id, 'Found workflow run ID: ' . $run_id );

				// Immediately check the status now that we have the run ID.
				$this->check_build_status( $deployment_id );
				return;
			}
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Workflow Run Not Found',
			'waiting',
			array(
				'commit_hash'  => $commit_hash,
				'checked_runs' => count( $result['data'] ),
			)
		);
	}

	/**
	 * Process successful build.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deployment_id The deployment ID.
	 * @return void
	 */
	public function process_successful_build( int $deployment_id ): void {
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id not found in database" );
			return;
		}

		// Skip if already deployed.
		if ( 'success' === $deployment->status ) {
			$this->logger->log( 'Deployment', "Deployment #$deployment_id already completed, skipping" );
			return;
		}

		// Skip if deployment failed (don't retry).
		if ( 'failed' === $deployment->status ) {
			$this->logger->log( 'Deployment', "Deployment #$deployment_id previously failed, skipping" );
			return;
		}

		// Check deployment lock to prevent concurrent processing.
		$locked_deployment = $this->database->get_deployment_lock();
		if ( $locked_deployment && $locked_deployment !== $deployment_id ) {
			$this->logger->log( 'Deployment', "Deployment #$deployment_id skipped - another deployment (#$locked_deployment) is processing" );
			// Reschedule for later.
			wp_schedule_single_event( time() + 60, 'deploy_forge_process_queued_deployment', array( $deployment_id ) );
			return;
		}

		// Set lock for this deployment.
		$this->database->set_deployment_lock( $deployment_id, 300 );

		// Update status from 'queued' to 'deploying'.
		$this->database->update_deployment(
			$deployment_id,
			array(
				'status' => 'deploying',
			)
		);

		$this->logger->log_deployment_step( $deployment_id, 'Process Build Success', 'started' );
		$this->log_deployment( $deployment_id, 'Build completed successfully. Starting deployment...' );

		try {
			// Check if we already have artifact info from the webhook.
			$artifact_id = $deployment->artifact_id ?? null;
			// Direct URL endpoint for downloading artifact from GitHub CDN (bypasses Vercel bandwidth).
			$direct_url_endpoint = $deployment->artifact_download_url ?? null;

			if ( ! empty( $artifact_id ) ) {
				// Use artifact info from webhook.
				$this->logger->log_deployment_step(
					$deployment_id,
					'Using Webhook Artifact',
					'success',
					array(
						'artifact_id'         => $artifact_id,
						'artifact_name'       => $deployment->artifact_name ?? 'unknown',
						'direct_url_endpoint' => $direct_url_endpoint,
					)
				);
			} else {
				// Fallback: Fetch artifacts from GitHub using workflow_run_id.
				$this->logger->log_deployment_step(
					$deployment_id,
					'Fetch Artifacts',
					'started',
					array(
						'workflow_run_id' => $deployment->workflow_run_id,
					)
				);

				if ( empty( $deployment->workflow_run_id ) ) {
					$error_message = __( 'No workflow run ID or artifact information available.', 'deploy-forge' );
					$this->logger->error( 'Deployment', "Deployment #$deployment_id has no workflow_run_id or artifact_id" );
					$this->database->update_deployment(
						$deployment_id,
						array(
							'status'        => 'failed',
							'error_message' => $error_message,
						)
					);
					$this->report_status_to_backend( $deployment_id, false, $error_message );
					$this->database->release_deployment_lock();
					return;
				}

				$artifacts_result = $this->github_api->get_workflow_artifacts( $deployment->workflow_run_id );

				if ( ! $artifacts_result['success'] || empty( $artifacts_result['data'] ) ) {
					$error_message = __( 'No artifacts found for successful build.', 'deploy-forge' );
					$this->logger->error( 'Deployment', "Deployment #$deployment_id no artifacts found", $artifacts_result );

					// Build detailed log message for debugging.
					$api_status = $artifacts_result['success'] ? 'API call succeeded but returned empty artifacts' : 'API call failed';
					$api_error  = $artifacts_result['message'] ?? 'No error message';
					$this->log_deployment(
						$deployment_id,
						sprintf(
							'Artifact check failed for workflow run %d: %s. API response: %s',
							$deployment->workflow_run_id,
							$api_status,
							$api_error
						)
					);

					$this->database->update_deployment(
						$deployment_id,
						array(
							'status'        => 'failed',
							'error_message' => $error_message,
						)
					);

					// Pass additional context about why artifacts weren't found.
					$additional_context = array(
						'failure_point'     => 'artifact_check',
						'api_success'       => $artifacts_result['success'] ?? false,
						'api_message'       => $artifacts_result['message'] ?? null,
						'artifacts_count'   => is_array( $artifacts_result['data'] ?? null ) ? count( $artifacts_result['data'] ) : 0,
						'expected_artifact' => $this->settings->get( 'artifact_name', 'theme-build' ),
					);
					$this->report_status_to_backend( $deployment_id, false, $error_message, $additional_context );
					$this->database->release_deployment_lock();
					return;
				}

				// Get first artifact (assuming single artifact).
				$artifact = $artifacts_result['data'][0];

				// Handle both array and object formats.
				$artifact_name = is_array( $artifact ) ? ( $artifact['name'] ?? 'unknown' ) : ( $artifact->name ?? 'unknown' );
				$artifact_id   = is_array( $artifact ) ? ( $artifact['id'] ?? null ) : ( $artifact->id ?? null );

				$this->logger->log_deployment_step(
					$deployment_id,
					'Artifacts Found',
					'success',
					array(
						'artifact_count' => count( $artifacts_result['data'] ),
						'artifact_name'  => $artifact_name,
						'artifact_id'    => $artifact_id,
					)
				);

				if ( ! $artifact_id ) {
					$error_message = __( 'Artifact ID not found.', 'deploy-forge' );
					$this->logger->error( 'Deployment', "Deployment #$deployment_id artifact has no ID", array( 'artifact' => $artifact ) );
					$this->database->update_deployment(
						$deployment_id,
						array(
							'status'        => 'failed',
							'error_message' => $error_message,
						)
					);
					$this->report_status_to_backend( $deployment_id, false, $error_message );
					$this->database->release_deployment_lock();
					return;
				}
			}

			// Download and deploy.
			$this->download_and_deploy( $deployment_id, $artifact_id, $direct_url_endpoint );
		} catch ( Exception $e ) {
			// Catch any exceptions and update deployment status.
			// Translators: %s is the error message from the exception.
			$error_message = sprintf( __( 'Deployment failed: %s', 'deploy-forge' ), $e->getMessage() );
			$this->logger->error(
				'Deployment',
				"Deployment #$deployment_id threw exception",
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => $error_message,
				)
			);
			$this->report_status_to_backend( $deployment_id, false, $error_message );
			$this->database->release_deployment_lock();
		}
	}

	/**
	 * Download artifact and deploy theme.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $deployment_id       The deployment ID.
	 * @param int|string  $artifact_id         The artifact ID.
	 * @param string|null $direct_url_endpoint Optional URL path for direct download endpoint from webhook
	 *                                         (e.g., /api/plugin/github/artifacts/123/download-url).
	 *                                         This endpoint returns a signed URL for direct download from GitHub CDN.
	 * @return void
	 */
	private function download_and_deploy( int $deployment_id, int|string $artifact_id, ?string $direct_url_endpoint = null ): void {
		// Create temp directory.
		$temp_dir     = $this->get_temp_directory();
		$artifact_zip = $temp_dir . '/artifact-' . $deployment_id . '.zip';

		$this->logger->log_deployment_step(
			$deployment_id,
			'Download Artifact',
			'started',
			array(
				'artifact_id'         => $artifact_id,
				'direct_url_endpoint' => $direct_url_endpoint,
				'temp_dir'            => $temp_dir,
				'artifact_zip'        => $artifact_zip,
			)
		);

		$this->log_deployment( $deployment_id, 'Downloading artifact from GitHub...' );

		// Download artifact - use direct URL endpoint from webhook if provided, otherwise use artifact ID.
		$download_result = $this->github_api->download_artifact( $artifact_id, $artifact_zip, $direct_url_endpoint );

		if ( is_wp_error( $download_result ) ) {
			$error_message = $download_result->get_error_message();
			$error_code    = $download_result->get_error_code();
			$this->logger->error( 'Deployment', "Deployment #$deployment_id artifact download failed", $download_result );
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => $error_message,
				)
			);
			$this->log_deployment(
				$deployment_id,
				sprintf( 'Download failed [%s]: %s (artifact_id: %s)', $error_code, $error_message, $artifact_id )
			);

			$additional_context = array(
				'failure_point'       => 'artifact_download',
				'error_code'          => $error_code,
				'artifact_id'         => $artifact_id,
				'direct_url_endpoint' => $direct_url_endpoint,
			);
			$this->report_status_to_backend( $deployment_id, false, $error_message, $additional_context );
			return;
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Artifact Downloaded',
			'success',
			array(
				'file_size' => file_exists( $artifact_zip ) ? filesize( $artifact_zip ) : 0,
			)
		);

		// Create backup if enabled.
		if ( $this->settings->get( 'create_backups' ) ) {
			$this->logger->log_deployment_step( $deployment_id, 'Create Backup', 'started' );
			$backup_path = $this->backup_current_theme( $deployment_id );
			if ( $backup_path ) {
				$this->database->update_deployment( $deployment_id, array( 'backup_path' => $backup_path ) );
				$this->log_deployment( $deployment_id, 'Backup created: ' . $backup_path );
				$this->logger->log_deployment_step(
					$deployment_id,
					'Backup Created',
					'success',
					array(
						'backup_path' => $backup_path,
					)
				);
			}
		}

		// Extract and deploy.
		$this->extract_and_deploy( $deployment_id, $artifact_zip );
	}

	/**
	 * Extract artifact and deploy to theme directory.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $deployment_id The deployment ID.
	 * @param string $artifact_zip  Path to the artifact ZIP file.
	 * @return void
	 */
	private function extract_and_deploy( int $deployment_id, string $artifact_zip ): void {
		global $wp_filesystem;

		$this->logger->log_deployment_step( $deployment_id, 'Extract and Deploy', 'started' );

		// Check if artifact file exists and is readable.
		if ( ! file_exists( $artifact_zip ) ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id artifact file not found: {$artifact_zip}" );
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => __( 'Artifact file not found.', 'deploy-forge' ),
				)
			);
			return;
		}

		$this->log_deployment( $deployment_id, 'Extracting artifact...' );

		// Extract to temp directory.
		$temp_extract_dir = $this->get_temp_directory() . '/extract-' . $deployment_id;

		// Create extraction directory using WP_Filesystem.
		$wp_filesystem = $this->get_filesystem();
		if ( ! $wp_filesystem ) {
			$this->logger->error( 'Deployment', 'Failed to initialize WP_Filesystem' );
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => __( 'Failed to initialize filesystem.', 'deploy-forge' ),
				)
			);
			return;
		}

		if ( ! is_dir( $temp_extract_dir ) ) {
			if ( ! wp_mkdir_p( $temp_extract_dir ) ) {
				$this->logger->error( 'Deployment', "Failed to create extraction directory: {$temp_extract_dir}" );
				$this->database->update_deployment(
					$deployment_id,
					array(
						'status'        => 'failed',
						'error_message' => __( 'Failed to create extraction directory.', 'deploy-forge' ),
					)
				);
				return;
			}
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Unzip Artifact',
			'started',
			array(
				'source'      => $artifact_zip,
				'destination' => $temp_extract_dir,
				'file_size'   => filesize( $artifact_zip ),
				'file_exists' => file_exists( $artifact_zip ),
			)
		);

		// Increase timeout for large files.
		@set_time_limit( 300 ); // 5 minutes.

		// Use native ZipArchive instead of WP_Filesystem-dependent unzip_file.
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->logger->error( 'Deployment', 'ZipArchive class not available' );
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => __( 'ZipArchive extension not available on server.', 'deploy-forge' ),
				)
			);
			return;
		}

		/**
		 * ZipArchive instance.
		 *
		 * @var \ZipArchive $zip
		 * @noinspection PhpUndefinedClassInspection
		 */
		$zip = new ZipArchive();

		$zip_open_result = $zip->open( $artifact_zip );

		if ( true !== $zip_open_result ) {
			// Map ZipArchive error codes to human-readable messages.
			$zip_errors     = array(
				ZipArchive::ER_EXISTS => 'File already exists',
				ZipArchive::ER_INCONS => 'Inconsistent ZIP archive',
				ZipArchive::ER_INVAL  => 'Invalid argument',
				ZipArchive::ER_MEMORY => 'Memory allocation failure',
				ZipArchive::ER_NOENT  => 'File not found',
				ZipArchive::ER_NOZIP  => 'Not a ZIP archive',
				ZipArchive::ER_OPEN   => 'Cannot open file',
				ZipArchive::ER_READ   => 'Read error',
				ZipArchive::ER_SEEK   => 'Seek error',
			);
			$zip_error_text = $zip_errors[ $zip_open_result ] ?? 'Unknown error';

			// Translators: %d is the ZipArchive error code.
			$error_message = sprintf( __( 'Failed to open ZIP file (error code: %d).', 'deploy-forge' ), $zip_open_result );
			$this->logger->error(
				'Deployment',
				'Failed to open ZIP file',
				array(
					'error_code' => $zip_open_result,
				)
			);

			// Get file info for debugging.
			$file_exists = file_exists( $artifact_zip );
			$file_size   = $file_exists ? filesize( $artifact_zip ) : 0;

			$this->log_deployment(
				$deployment_id,
				sprintf(
					'ZIP open failed: %s (code %d). File exists: %s, Size: %d bytes',
					$zip_error_text,
					$zip_open_result,
					$file_exists ? 'yes' : 'no',
					$file_size
				)
			);

			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => $error_message,
				)
			);

			$additional_context = array(
				'failure_point'   => 'zip_open',
				'zip_error_code'  => $zip_open_result,
				'zip_error_text'  => $zip_error_text,
				'file_exists'     => $file_exists,
				'file_size_bytes' => $file_size,
			);
			$this->report_status_to_backend( $deployment_id, false, $error_message, $additional_context );
			return;
		}

		$extract_result = $zip->extractTo( $temp_extract_dir );
		$zip->close();

		if ( ! $extract_result ) {
			$error_message = __( 'Failed to extract ZIP file.', 'deploy-forge' );
			$this->logger->error( 'Deployment', 'Failed to extract ZIP file' );

			// Check destination directory permissions.
			$dir_exists   = is_dir( $temp_extract_dir );
			$dir_writable = $dir_exists && wp_is_writable( $temp_extract_dir );

			$this->log_deployment(
				$deployment_id,
				sprintf(
					'ZIP extraction failed to %s. Directory exists: %s, Writable: %s',
					$temp_extract_dir,
					$dir_exists ? 'yes' : 'no',
					$dir_writable ? 'yes' : 'no'
				)
			);

			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => $error_message,
				)
			);

			$additional_context = array(
				'failure_point'      => 'zip_extract',
				'extract_dir'        => $temp_extract_dir,
				'dir_exists'         => $dir_exists,
				'dir_writable'       => $dir_writable,
				'disk_free_space_mb' => function_exists( 'disk_free_space' ) ? round( disk_free_space( sys_get_temp_dir() ) / 1024 / 1024, 2 ) : null,
			);
			$this->report_status_to_backend( $deployment_id, false, $error_message, $additional_context );
			return;
		}

		$this->logger->log_deployment_step( $deployment_id, 'Artifact Extracted', 'success' );

		// GitHub Actions artifacts are double-zipped - check if we need to unzip again.
		$extracted_files = scandir( $temp_extract_dir );
		$extracted_files = array_diff( $extracted_files, array( '.', '..' ) );

		$this->logger->log_deployment_step(
			$deployment_id,
			'Check Extracted Files',
			'checking',
			array(
				'file_count' => count( $extracted_files ),
				'files'      => array_values( $extracted_files ),
			)
		);

		// If there's only one file and it's a zip, extract it.
		if ( 1 === count( $extracted_files ) ) {
			$single_file      = reset( $extracted_files );
			$single_file_path = $temp_extract_dir . '/' . $single_file;

			if ( 'zip' === pathinfo( $single_file, PATHINFO_EXTENSION ) ) {
				$this->logger->log_deployment_step(
					$deployment_id,
					'Double-Zipped Detected',
					'extracting_inner_zip',
					array(
						'inner_zip' => $single_file,
					)
				);

				$this->log_deployment( $deployment_id, 'Artifact is double-zipped, extracting inner archive...' );

				$final_extract_dir = $this->get_temp_directory() . '/final-' . $deployment_id;

				// Create final extraction directory.
				if ( ! is_dir( $final_extract_dir ) ) {
					if ( ! wp_mkdir_p( $final_extract_dir ) ) {
						$this->logger->error( 'Deployment', 'Failed to create final extraction directory' );
						$this->database->update_deployment(
							$deployment_id,
							array(
								'status'        => 'failed',
								'error_message' => __( 'Failed to create extraction directory.', 'deploy-forge' ),
							)
						);
						return;
					}
				}

				// Extract inner zip using native ZipArchive.
				$this->logger->log_deployment_step(
					$deployment_id,
					'Opening Inner ZIP',
					'starting',
					array(
						'inner_zip_path' => $single_file_path,
						'inner_zip_size' => file_exists( $single_file_path ) ? filesize( $single_file_path ) : 0,
					)
				);

				/**
				 * Inner ZipArchive instance.
				 *
				 * @var \ZipArchive $inner_zip
				 * @noinspection PhpUndefinedClassInspection
				 */
				$inner_zip      = new ZipArchive();
				$inner_zip_open = $inner_zip->open( $single_file_path );

				$this->logger->log_deployment_step(
					$deployment_id,
					'Inner ZIP Open Result',
					'checked',
					array(
						'result'  => $inner_zip_open,
						'success' => true === $inner_zip_open,
					)
				);

				if ( true !== $inner_zip_open ) {
					$this->logger->error(
						'Deployment',
						'Failed to open inner ZIP file',
						array(
							'error_code' => $inner_zip_open,
							'file_path'  => $single_file_path,
						)
					);
					$this->database->update_deployment(
						$deployment_id,
						array(
							'status'        => 'failed',
							// Translators: %d is the ZipArchive error code.
							'error_message' => sprintf( __( 'Failed to open inner ZIP file (error code: %d).', 'deploy-forge' ), $inner_zip_open ),
						)
					);
					return;
				}

				$this->logger->log_deployment_step(
					$deployment_id,
					'Extracting Inner ZIP',
					'in_progress',
					array(
						'destination' => $final_extract_dir,
					)
				);

				$inner_extract_result = $inner_zip->extractTo( $final_extract_dir );

				$this->logger->log_deployment_step(
					$deployment_id,
					'Inner ZIP Extract Complete',
					'checked',
					array(
						'result' => $inner_extract_result,
					)
				);

				$inner_zip->close();

				if ( ! $inner_extract_result ) {
					$this->logger->error( 'Deployment', 'Failed to extract inner ZIP file' );
					$this->database->update_deployment(
						$deployment_id,
						array(
							'status'        => 'failed',
							'error_message' => __( 'Failed to extract inner ZIP file.', 'deploy-forge' ),
						)
					);
					return;
				}

				$this->logger->log_deployment_step( $deployment_id, 'Cleaning Up Outer Extract', 'starting' );

				// Clean up outer extraction and use inner as source.
				$this->recursive_delete( $temp_extract_dir );
				$temp_extract_dir = $final_extract_dir;

				$this->logger->log_deployment_step( $deployment_id, 'Inner Archive Extracted', 'success' );
			}
		}

		// Get target theme path from settings.
		$target_theme_path = $this->settings->get_theme_path();
		$theme_slug        = basename( $target_theme_path );

		$this->logger->log_deployment_step(
			$deployment_id,
			'Find Theme Directory',
			'started',
			array(
				'extract_dir'       => $temp_extract_dir,
				'target_theme_path' => $target_theme_path,
				'theme_slug'        => $theme_slug,
			)
		);

		// Strategy: Respect the build structure provided by the developer.
		// 1. First, look for a directory matching the theme slug (preserves any intentional folder structure).
		// 2. If not found, search for theme files (style.css/functions.php) - for backwards compatibility.
		// 3. Fallback to first subdirectory (GitHub zipball structure).
		// 4. Final fallback to extract directory itself.

		$source_theme_dir = null;

		// Step 1: Look for directory matching theme slug.
		$extracted_items = scandir( $temp_extract_dir );
		$extracted_items = array_diff( $extracted_items, array( '.', '..' ) );

		foreach ( $extracted_items as $item ) {
			$item_path = $temp_extract_dir . '/' . $item;
			if ( is_dir( $item_path ) && $item === $theme_slug ) {
				$source_theme_dir = $item_path;
				$this->logger->log(
					'Deployment',
					'Found directory matching theme slug',
					array(
						'directory' => $source_theme_dir,
						'method'    => 'theme_slug_match',
					)
				);
				break;
			}
		}

		// Step 2: If no match found, search for theme files (backwards compatibility).
		if ( ! $source_theme_dir ) {
			$source_theme_dir = $this->find_theme_directory( $temp_extract_dir );
			if ( $source_theme_dir ) {
				$this->logger->log(
					'Deployment',
					'Found theme files using search',
					array(
						'directory' => $source_theme_dir,
						'method'    => 'theme_files_search',
					)
				);
			}
		}

		// Step 3: Fallback to first subdirectory (GitHub zipball structure).
		if ( ! $source_theme_dir ) {
			foreach ( $extracted_items as $item ) {
				$item_path = $temp_extract_dir . '/' . $item;
				if ( is_dir( $item_path ) ) {
					$source_theme_dir = $item_path;
					$this->logger->log(
						'Deployment',
						'Using first subdirectory',
						array(
							'directory' => $source_theme_dir,
							'method'    => 'first_subdir',
						)
					);
					break;
				}
			}
		}

		// Step 4: Final fallback to extract directory itself.
		if ( ! $source_theme_dir ) {
			$source_theme_dir = $temp_extract_dir;
			$this->logger->log(
				'Deployment',
				'Using extract directory as source',
				array(
					'directory' => $source_theme_dir,
					'method'    => 'extract_dir_fallback',
				)
			);
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Theme Directory Found',
			'success',
			array(
				'source_theme_dir'  => $source_theme_dir,
				'target_theme_path' => $target_theme_path,
			)
		);

		$this->logger->log_deployment_step(
			$deployment_id,
			'Copy to Themes Directory',
			'started',
			array(
				'source'      => $source_theme_dir,
				'destination' => $target_theme_path,
			)
		);

		$this->log_deployment( $deployment_id, 'Deploying theme files...' );

		// Count files to be copied for logging.
		$file_count = 0;
		$dir_count  = 0;
		if ( is_dir( $source_theme_dir ) ) {
			/**
			 * Recursive iterator.
			 *
			 * @var \RecursiveIteratorIterator $iterator
			 * @noinspection PhpUndefinedClassInspection
			 */
			$iterator = new RecursiveIteratorIterator(
				// phpcs:ignore -- RecursiveDirectoryIterator is a PHP built-in class.
				new RecursiveDirectoryIterator($source_theme_dir, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $item ) {
				if ( $item->isFile() ) {
					++$file_count;
				} else {
					++$dir_count;
				}
			}
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Starting File Copy',
			'in_progress',
			array(
				'file_count'      => $file_count,
				'directory_count' => $dir_count,
			)
		);

		// Remove existing theme directory contents before copying.
		// This ensures a clean deployment.
		if ( is_dir( $target_theme_path ) ) {
			$this->logger->log_deployment_step( $deployment_id, 'Clearing Existing Theme', 'in_progress' );
			$this->recursive_delete( $target_theme_path );
		}

		// Create target theme directory.
		if ( ! is_dir( $target_theme_path ) ) {
			if ( ! wp_mkdir_p( $target_theme_path ) ) {
				$this->logger->error( 'Deployment', "Failed to create theme directory: {$target_theme_path}" );
				$this->database->update_deployment(
					$deployment_id,
					array(
						'status'        => 'failed',
						'error_message' => __( 'Failed to create theme directory.', 'deploy-forge' ),
					)
				);
				return;
			}
		}

		// Copy theme files to target directory.
		// Increase timeout for large theme deployments.
		@set_time_limit( 300 );

		$copy_result = $this->recursive_copy( $source_theme_dir, $target_theme_path );

		if ( ! $copy_result ) {
			$error_message = __( 'Failed to copy files to theme directory.', 'deploy-forge' );
			$this->logger->error( 'Deployment', "Deployment #$deployment_id file copy failed" );

			// Gather directory info for debugging.
			$source_exists   = is_dir( $source_theme_dir );
			$target_exists   = is_dir( $target_theme_path );
			$target_writable = wp_is_writable( dirname( $target_theme_path ) );
			$source_files    = $source_exists ? count( scandir( $source_theme_dir ) ) - 2 : 0;

			$this->log_deployment(
				$deployment_id,
				sprintf(
					'File copy failed from %s to %s. Source exists: %s (%d files), Target dir writable: %s',
					$source_theme_dir,
					$target_theme_path,
					$source_exists ? 'yes' : 'no',
					$source_files,
					$target_writable ? 'yes' : 'no'
				)
			);

			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => $error_message,
				)
			);

			$additional_context = array(
				'failure_point'      => 'file_copy',
				'source_dir'         => $source_theme_dir,
				'target_dir'         => $target_theme_path,
				'source_exists'      => $source_exists,
				'source_file_count'  => $source_files,
				'target_exists'      => $target_exists,
				'target_writable'    => $target_writable,
				'disk_free_space_mb' => function_exists( 'disk_free_space' ) ? round( disk_free_space( dirname( $target_theme_path ) ) / 1024 / 1024, 2 ) : null,
			);
			$this->report_status_to_backend( $deployment_id, false, $error_message, $additional_context );
			return;
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Files Copied',
			'success',
			array(
				'files_copied'       => $file_count,
				'directories_copied' => $dir_count,
			)
		);

		// Clean up temp files.
		wp_delete_file( $artifact_zip );
		$this->recursive_delete( $temp_extract_dir );

		$this->logger->log_deployment_step( $deployment_id, 'Cleanup Complete', 'success' );

		// Update deployment as successful.
		$this->database->update_deployment(
			$deployment_id,
			array(
				'status'      => 'success',
				'deployed_at' => current_time( 'mysql' ),
			)
		);

		// Release deployment lock.
		$this->database->release_deployment_lock();

		$this->logger->log_deployment_step( $deployment_id, 'Deployment Complete', 'SUCCESS!' );
		$this->log_deployment( $deployment_id, 'Deployment completed successfully!' );

		// Report success status back to Deploy Forge API.
		$this->report_status_to_backend( $deployment_id, true );

		// Trigger action hook.
		do_action( 'deploy_forge_completed', $deployment_id );
	}

	/**
	 * Backup current theme.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deployment_id The deployment ID.
	 * @return string|false Path to backup file on success, false on failure.
	 */
	public function backup_current_theme( int $deployment_id ): string|false {
		$theme_path      = $this->settings->get_theme_path();
		$backup_dir      = $this->settings->get_backup_directory();
		$backup_filename = 'backup-' . $deployment_id . '-' . time() . '.zip';
		$backup_path     = $backup_dir . '/' . $backup_filename;

		// Ensure backup directory exists.
		if ( ! is_dir( $backup_dir ) ) {
			if ( ! wp_mkdir_p( $backup_dir ) ) {
				$this->logger->error( 'Deployment', "Failed to create backup directory: {$backup_dir}" );
				return false;
			}
		}

		$this->logger->log(
			'Deployment',
			'Creating backup ZIP',
			array(
				'theme_path'   => $theme_path,
				'backup_path'  => $backup_path,
				'theme_exists' => is_dir( $theme_path ),
			)
		);

		// Skip backup if theme doesn't exist yet.
		if ( ! is_dir( $theme_path ) ) {
			$this->logger->log( 'Deployment', "Skipping backup - theme directory doesn't exist (first deployment)" );
			return false;
		}

		// Create zip of current theme.
		if ( class_exists( 'ZipArchive' ) ) {
			/**
			 * ZipArchive instance.
			 *
			 * @var \ZipArchive $zip
			 * @noinspection PhpUndefinedClassInspection
			 */
			$zip = new ZipArchive();
			// phpcs:ignore -- ZipArchive::open() is a PHP built-in method.
			if (true === $zip->open($backup_path, ZipArchive::CREATE)) {
				$this->logger->log( 'Deployment', 'Adding files to backup ZIP...' );
				$this->add_directory_to_zip( $zip, $theme_path, basename( $theme_path ) );
				$zip->close();

				$backup_size = file_exists( $backup_path ) ? filesize( $backup_path ) : 0;
				$this->logger->log(
					'Deployment',
					'Backup created successfully',
					array(
						'backup_size' => $backup_size,
					)
				);

				return $backup_path;
			} else {
				$this->logger->error( 'Deployment', 'Failed to create backup ZIP file' );
			}
		} else {
			$this->logger->error( 'Deployment', 'ZipArchive class not available' );
		}

		return false;
	}

	/**
	 * Add directory to zip recursively.
	 *
	 * @since 1.0.0
	 *
	 * @param object $zip      ZipArchive instance.
	 * @param string $dir      Directory path.
	 * @param string $zip_path Path in zip.
	 * @return void
	 *
	 * @suppress PhanUndeclaredClassReference
	 */
	private function add_directory_to_zip( $zip, string $dir, string $zip_path ): void {
		/**
		 * Recursive iterator.
		 *
		 * @var \RecursiveIteratorIterator $files
		 * @noinspection PhpUndefinedClassInspection
		 */
		$files = new RecursiveIteratorIterator(
			// phpcs:ignore -- RecursiveDirectoryIterator is a PHP built-in class.
			new RecursiveDirectoryIterator($dir),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $file ) {
			if ( ! $file->isDir() ) {
				$file_path     = $file->getRealPath();
				$relative_path = $zip_path . '/' . substr( $file_path, strlen( $dir ) + 1 );
				$zip->addFile( $file_path, $relative_path );
			}
		}
	}

	/**
	 * Rollback to previous deployment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deployment_id The deployment ID to rollback.
	 * @return bool True on success, false on failure.
	 */
	public function rollback_deployment( int $deployment_id ): bool {
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment || empty( $deployment->backup_path ) ) {
			return false;
		}

		$theme_path       = $this->settings->get_theme_path();
		$temp_extract_dir = $this->get_temp_directory() . '/rollback-' . $deployment_id;

		// Create extraction directory.
		if ( ! is_dir( $temp_extract_dir ) ) {
			if ( ! wp_mkdir_p( $temp_extract_dir ) ) {
				$this->logger->error( 'Deployment', 'Failed to create rollback extraction directory' );
				return false;
			}
		}

		// Extract backup using native ZipArchive.
		/**
		 * ZipArchive instance.
		 *
		 * @var \ZipArchive $zip
		 * @noinspection PhpUndefinedClassInspection
		 */
		$zip = new ZipArchive();
		if ( true !== $zip->open( $deployment->backup_path ) ) {
			$this->logger->error( 'Deployment', 'Failed to open backup ZIP for rollback' );
			return false;
		}

		if ( ! $zip->extractTo( $temp_extract_dir ) ) {
			$zip->close();
			$this->logger->error( 'Deployment', 'Failed to extract backup ZIP for rollback' );
			return false;
		}
		$zip->close();

		// Copy files back using native PHP.
		$copy_result = $this->recursive_copy( $temp_extract_dir, $theme_path );

		if ( ! $copy_result ) {
			$this->logger->error( 'Deployment', 'Failed to copy rollback files' );
			return false;
		}

		// Clean up.
		$this->recursive_delete( $temp_extract_dir );

		// Update deployment status.
		$this->database->update_deployment(
			$deployment_id,
			array(
				'status' => 'rolled_back',
			)
		);

		do_action( 'deploy_forge_rolled_back', $deployment_id );

		return true;
	}

	/**
	 * Approve a pending deployment (manual approval workflow).
	 *
	 * Updates the existing pending deployment and triggers the appropriate deployment method.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deployment_id The deployment ID to approve.
	 * @param int $user_id       The user ID who approved the deployment.
	 * @return bool True on success, false on failure.
	 */
	public function approve_pending_deployment( int $deployment_id, int $user_id ): bool {
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id not found" );
			return false;
		}

		if ( 'pending' !== $deployment->status ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id cannot be approved (status: {$deployment->status})" );
			return false;
		}

		$this->logger->log_deployment_step(
			$deployment_id,
			'Approve Deployment',
			'initiated',
			array(
				'approved_by_user_id' => $user_id,
				'deployment_method'   => $deployment->deployment_method ?? 'github_actions',
			)
		);

		// Update deployment to be triggered by the user who approved it.
		$this->database->update_deployment(
			$deployment_id,
			array(
				'trigger_type'         => 'manual',
				'triggered_by_user_id' => $user_id,
			)
		);

		// Check deployment method - direct_clone doesn't need a workflow.
		$deployment_method = $deployment->deployment_method ?? 'github_actions';

		if ( 'direct_clone' === $deployment_method ) {
			// For direct clone, start the clone deployment immediately.
			$this->logger->log_deployment_step( $deployment_id, 'Direct Clone Approved', 'started' );

			$remote_deployment_id = $deployment->remote_deployment_id ?? '';

			// Process the clone deployment.
			$this->process_clone_deployment( $deployment_id, $remote_deployment_id );

			$this->logger->log_deployment_step( $deployment_id, 'Deployment Approved', 'success' );
			return true;
		}

		// For github_actions method, trigger the workflow.
		$workflow_result = $this->trigger_github_build( $deployment_id, $deployment->commit_hash );

		if ( ! $workflow_result ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id failed to trigger workflow after approval" );
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => __( 'Failed to trigger GitHub Actions workflow after approval.', 'deploy-forge' ),
				)
			);
			return false;
		}

		$this->logger->log_deployment_step( $deployment_id, 'Deployment Approved', 'success' );

		return true;
	}

	/**
	 * Cancel a deployment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deployment_id The deployment ID to cancel.
	 * @return bool True on success, false on failure.
	 */
	public function cancel_deployment( int $deployment_id ): bool {
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id not found" );
			return false;
		}

		// Can only cancel deployments in pending or building status.
		if ( ! in_array( $deployment->status, array( 'pending', 'building' ), true ) ) {
			$this->logger->error( 'Deployment', "Deployment #$deployment_id cannot be cancelled (status: {$deployment->status})" );
			return false;
		}

		$this->logger->log_deployment_step( $deployment_id, 'Cancel Deployment', 'initiated' );

		// If workflow run ID exists, cancel it on GitHub.
		if ( ! empty( $deployment->workflow_run_id ) ) {
			$this->logger->log_deployment_step(
				$deployment_id,
				'Cancel GitHub Workflow',
				'started',
				array(
					'workflow_run_id' => $deployment->workflow_run_id,
				)
			);

			$cancel_result = $this->github_api->cancel_workflow_run( $deployment->workflow_run_id );

			if ( ! $cancel_result['success'] ) {
				$this->logger->error( 'Deployment', "Deployment #$deployment_id failed to cancel workflow run", $cancel_result );
				$this->log_deployment( $deployment_id, 'Failed to cancel GitHub workflow: ' . $cancel_result['message'] );
				// Continue anyway to update database status.
			} else {
				$this->logger->log_deployment_step( $deployment_id, 'GitHub Workflow Cancelled', 'success' );
				$this->log_deployment( $deployment_id, 'GitHub workflow run cancellation requested.' );
			}
		}

		// Update deployment status to cancelled.
		$this->database->update_deployment(
			$deployment_id,
			array(
				'status'        => 'cancelled',
				'error_message' => __( 'Deployment cancelled by user.', 'deploy-forge' ),
			)
		);

		$this->logger->log_deployment_step( $deployment_id, 'Deployment Cancelled', 'success' );
		$this->log_deployment( $deployment_id, 'Deployment cancelled by user.' );

		do_action( 'deploy_forge_cancelled', $deployment_id );

		return true;
	}

	/**
	 * Get temp directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string Path to temp directory.
	 */
	private function get_temp_directory(): string {
		$temp_dir = sys_get_temp_dir() . '/deploy-forge';

		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		return $temp_dir;
	}

	/**
	 * Find the actual theme directory within extracted artifact.
	 *
	 * Handles nested structures like reponame/theme/ or just reponame/.
	 *
	 * @since 1.0.0
	 *
	 * @param string $extract_dir The directory where artifact was extracted.
	 * @return string|false Path to theme directory or false if not found.
	 */
	private function find_theme_directory( string $extract_dir ) {
		// Recursively search for theme files (style.css or functions.php).
		// Max depth of 3 levels to handle structures like:
		// - reponame/style.css
		// - reponame/theme/style.css
		// - reponame/subdir/theme/style.css.

		$max_depth = 3;

		$this->logger->log(
			'Deployment',
			'Searching for theme files in extracted artifact',
			array(
				'search_root' => $extract_dir,
				'max_depth'   => $max_depth,
			)
		);

		return $this->find_theme_directory_recursive( $extract_dir, 0, $max_depth );
	}

	/**
	 * Recursively search for theme directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir           Directory to search.
	 * @param int    $current_depth Current recursion depth.
	 * @param int    $max_depth     Maximum recursion depth.
	 * @return string|false Path to theme directory or false if not found.
	 */
	private function find_theme_directory_recursive( string $dir, int $current_depth, int $max_depth ) {
		if ( $current_depth > $max_depth ) {
			return false;
		}

		// Check if current directory has theme files.
		if ( file_exists( $dir . '/style.css' ) || file_exists( $dir . '/functions.php' ) ) {
			$this->logger->log(
				'Deployment',
				'Found theme files',
				array(
					'directory' => $dir,
					'depth'     => $current_depth,
				)
			);
			return $dir;
		}

		// Search subdirectories.
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$item_path = $dir . '/' . $item;
			if ( is_dir( $item_path ) ) {
				$result = $this->find_theme_directory_recursive( $item_path, $current_depth + 1, $max_depth );
				if ( $result ) {
					return $result;
				}
			}
		}

		return false;
	}

	/**
	 * Report deployment status back to Deploy Forge API.
	 *
	 * This syncs the deployment outcome (success/failure) back to the
	 * Deploy Forge dashboard so users can see real-time status.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $deployment_id    Local deployment ID.
	 * @param bool        $success          Whether deployment succeeded.
	 * @param string|null $error_message    Error message if failed.
	 * @param array|null  $additional_context Extra debugging context for this specific failure.
	 * @return void
	 */
	private function report_status_to_backend( int $deployment_id, bool $success, ?string $error_message = null, ?array $additional_context = null ): void {
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment ) {
			$this->logger->log( 'Deployment', "Cannot report status: deployment #$deployment_id not found" );
			return;
		}

		$remote_deployment_id = $deployment->remote_deployment_id ?? '';

		if ( empty( $remote_deployment_id ) ) {
			$this->logger->log( 'Deployment', "Cannot report status: no remote deployment ID for #$deployment_id" );
			return;
		}

		// Get deployment logs to send back.
		$logs = $deployment->deployment_logs ?? null;

		// Build context from deployment record and additional context.
		$context = $this->build_deployment_context( $deployment, $additional_context );

		$this->logger->log(
			'Deployment',
			'Reporting deployment status to Deploy Forge',
			array(
				'deployment_id'        => $deployment_id,
				'remote_deployment_id' => $remote_deployment_id,
				'success'              => $success,
				'has_context'          => ! empty( $context ),
			)
		);

		$result = $this->github_api->report_deployment_status(
			$remote_deployment_id,
			$success,
			$error_message,
			$logs,
			$context
		);

		if ( $result['success'] ) {
			$this->logger->log(
				'Deployment',
				'Successfully reported status to Deploy Forge',
				array(
					'deployment_id' => $deployment_id,
					'message'       => $result['message'],
				)
			);
		} else {
			$this->logger->error(
				'Deployment',
				'Failed to report status to Deploy Forge',
				array(
					'deployment_id' => $deployment_id,
					'error'         => $result['message'],
				)
			);
		}
	}

	/**
	 * Build debugging context from deployment record.
	 *
	 * Gathers relevant information from the deployment that can help
	 * users debug failures in the Deploy Forge dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @param object     $deployment         The deployment record.
	 * @param array|null $additional_context Extra context specific to this failure.
	 * @return array The combined context array.
	 */
	private function build_deployment_context( object $deployment, ?array $additional_context = null ): array {
		$context = array();

		// Basic deployment info.
		if ( ! empty( $deployment->deployment_method ) ) {
			$context['deployment_method'] = $deployment->deployment_method;
		}
		if ( ! empty( $deployment->trigger_type ) ) {
			$context['trigger_type'] = $deployment->trigger_type;
		}

		// GitHub Actions info.
		if ( ! empty( $deployment->workflow_run_id ) ) {
			$context['workflow_run_id'] = $deployment->workflow_run_id;
		}
		if ( ! empty( $deployment->build_url ) ) {
			$context['build_url'] = $deployment->build_url;
		}

		// Artifact info.
		if ( ! empty( $deployment->artifact_id ) ) {
			$context['artifact_id'] = $deployment->artifact_id;
		}
		if ( ! empty( $deployment->artifact_name ) ) {
			$context['artifact_name'] = $deployment->artifact_name;
		}
		if ( ! empty( $deployment->artifact_size ) ) {
			$context['artifact_size'] = $deployment->artifact_size;
		}

		// Commit info.
		if ( ! empty( $deployment->commit_hash ) ) {
			$context['commit_hash'] = $deployment->commit_hash;
		}

		// Environment info.
		$context['plugin_version'] = defined( 'DEPLOY_FORGE_VERSION' ) ? DEPLOY_FORGE_VERSION : 'unknown';
		$context['php_version']    = PHP_VERSION;
		$context['wp_version']     = get_bloginfo( 'version' );

		// Merge in additional context specific to this failure.
		if ( $additional_context ) {
			$context = array_merge( $context, $additional_context );
		}

		return $context;
	}

	/**
	 * Log deployment message.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $deployment_id The deployment ID.
	 * @param string $message       The message to log.
	 * @return void
	 */
	private function log_deployment( int $deployment_id, string $message ): void {
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment ) {
			return;
		}

		$timestamp = current_time( 'mysql' );
		$log_entry = "[{$timestamp}] {$message}\n";

		$current_logs = $deployment->deployment_logs ?? '';
		$updated_logs = $current_logs . $log_entry;

		$this->database->update_deployment(
			$deployment_id,
			array(
				'deployment_logs' => $updated_logs,
			)
		);
	}

	/**
	 * Recursively copy directory contents (native PHP replacement for copy_dir).
	 *
	 * @since 1.0.0
	 *
	 * @param string $source Source directory.
	 * @param string $dest   Destination directory.
	 * @return bool True on success, false on failure.
	 */
	private function recursive_copy( string $source, string $dest ): bool {
		if ( ! is_dir( $source ) ) {
			return false;
		}

		$wp_filesystem = $this->get_filesystem();
		if ( ! $wp_filesystem ) {
			return false;
		}

		// Create destination if needed.
		if ( ! is_dir( $dest ) ) {
			if ( ! wp_mkdir_p( $dest ) ) {
				return false;
			}
		}

		/**
		 * Recursive iterator.
		 *
		 * @var \RecursiveIteratorIterator $iterator
		 * @noinspection PhpUndefinedClassInspection
		 */
		$iterator = new RecursiveIteratorIterator(
			// phpcs:ignore -- RecursiveDirectoryIterator is a PHP built-in class.
			new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			// Get relative path by removing source prefix from full path.
			$relative_path = str_replace( $source . DIRECTORY_SEPARATOR, '', $item->getPathname() );
			$target        = $dest . DIRECTORY_SEPARATOR . $relative_path;

			if ( $item->isDir() ) {
				if ( ! is_dir( $target ) ) {
					if ( ! wp_mkdir_p( $target ) ) {
						return false;
					}
				}
			} else {
				if ( ! copy( $item->getRealPath(), $target ) ) {
					return false;
				}
				$wp_filesystem->chmod( $target, 0644 );
			}
		}

		return true;
	}

	/**
	 * Recursively delete directory using WP_Filesystem.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Directory to delete.
	 * @return bool True on success, false on failure.
	 */
	private function recursive_delete( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$wp_filesystem = $this->get_filesystem();
		if ( ! $wp_filesystem ) {
			return false;
		}

		return $wp_filesystem->delete( $dir, true );
	}

	/**
	 * Process a direct clone deployment.
	 *
	 * Downloads the repository directly from GitHub (no workflow/artifact).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $deployment_id        The deployment ID.
	 * @param string $remote_deployment_id The remote deployment ID.
	 * @return void
	 */
	public function process_clone_deployment( int $deployment_id, string $remote_deployment_id ): void {
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment ) {
			$this->logger->error( 'Deployment', "Clone deployment #$deployment_id not found in database" );
			return;
		}

		// Skip if already deployed.
		if ( 'success' === $deployment->status ) {
			$this->logger->log( 'Deployment', "Clone deployment #$deployment_id already completed, skipping" );
			return;
		}

		// Skip if deployment failed (don't retry).
		if ( 'failed' === $deployment->status ) {
			$this->logger->log( 'Deployment', "Clone deployment #$deployment_id previously failed, skipping" );
			return;
		}

		// Check deployment lock to prevent concurrent processing.
		$locked_deployment = $this->database->get_deployment_lock();
		if ( $locked_deployment && $locked_deployment !== $deployment_id ) {
			$this->logger->log( 'Deployment', "Clone deployment #$deployment_id skipped - another deployment (#$locked_deployment) is processing" );
			// Reschedule for later.
			wp_schedule_single_event( time() + 60, 'deploy_forge_process_clone_deployment', array( $deployment_id, $remote_deployment_id ) );
			return;
		}

		// Set lock for this deployment.
		$this->database->set_deployment_lock( $deployment_id, 300 );

		// Update status to deploying.
		$this->database->update_deployment(
			$deployment_id,
			array(
				'status' => 'deploying',
			)
		);

		$this->logger->log_deployment_step( $deployment_id, 'Process Clone Deployment', 'started' );
		$this->log_deployment( $deployment_id, 'Starting direct clone deployment...' );

		try {
			// Create temp directory for download.
			$temp_dir = $this->get_temp_directory();
			$repo_zip = $temp_dir . '/repo-' . $deployment_id . '.zip';

			$this->logger->log_deployment_step( $deployment_id, 'Download Repository', 'started' );
			$this->log_deployment( $deployment_id, 'Downloading repository from GitHub...' );

			// Get the branch/ref to download.
			$ref = $this->settings->get( 'github_branch' ) ?: 'main';

			// Download the repository.
			$download_result = $this->github_api->download_repository( $ref, $repo_zip );

			if ( is_wp_error( $download_result ) ) {
				$error_message = $download_result->get_error_message();
				$this->logger->error(
					'Deployment',
					"Clone deployment #$deployment_id download failed",
					array(
						'error' => $error_message,
					)
				);
				$this->database->update_deployment(
					$deployment_id,
					array(
						'status'        => 'failed',
						'error_message' => $error_message,
					)
				);
				$this->log_deployment( $deployment_id, 'Download failed: ' . $error_message );
				$this->report_status_to_backend( $deployment_id, false, $error_message );
				$this->database->release_deployment_lock();
				return;
			}

			$this->logger->log_deployment_step(
				$deployment_id,
				'Repository Downloaded',
				'success',
				array(
					'file_size' => file_exists( $repo_zip ) ? filesize( $repo_zip ) : 0,
				)
			);
			$this->log_deployment( $deployment_id, 'Repository downloaded successfully.' );

			// Create backup if enabled.
			if ( $this->settings->get( 'create_backups' ) ) {
				$this->logger->log_deployment_step( $deployment_id, 'Create Backup', 'started' );
				$backup_path = $this->backup_current_theme( $deployment_id );
				if ( $backup_path ) {
					$this->database->update_deployment( $deployment_id, array( 'backup_path' => $backup_path ) );
					$this->log_deployment( $deployment_id, 'Backup created: ' . $backup_path );
					$this->logger->log_deployment_step(
						$deployment_id,
						'Backup Created',
						'success',
						array(
							'backup_path' => $backup_path,
						)
					);
				}
			}

			// Extract and deploy (reuse existing method).
			$this->extract_and_deploy( $deployment_id, $repo_zip );
		} catch ( Exception $e ) {
			// Translators: %s is the error message from the exception.
			$error_message = sprintf( __( 'Clone deployment failed: %s', 'deploy-forge' ), $e->getMessage() );
			$this->logger->error(
				'Deployment',
				"Clone deployment #$deployment_id threw exception",
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);
			$this->database->update_deployment(
				$deployment_id,
				array(
					'status'        => 'failed',
					'error_message' => $error_message,
				)
			);
			$this->report_status_to_backend( $deployment_id, false, $error_message );
			$this->database->release_deployment_lock();
		}
	}
}
