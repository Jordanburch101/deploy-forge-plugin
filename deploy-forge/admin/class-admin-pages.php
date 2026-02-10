<?php
/**
 * Admin pages class
 *
 * Handles WordPress admin interface including menu registration,
 * settings pages, and AJAX handlers for the Deploy Forge plugin.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Admin_Pages
 *
 * Main admin controller that manages all WordPress admin pages,
 * menus, and AJAX operations for the Deploy Forge plugin.
 *
 * @since 1.0.0
 */
class Deploy_Forge_Admin_Pages extends Deploy_Forge_Ajax_Handler_Base {

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
	 * Deployment manager instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Deployment_Manager
	 */
	private Deploy_Forge_Deployment_Manager $deployment_manager;

	/**
	 * Database instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Database
	 */
	private Deploy_Forge_Database $database;

	/**
	 * Debug logger instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Debug_Logger
	 */
	private Deploy_Forge_Debug_Logger $logger;

	/**
	 * Connection handler instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Connection_Handler
	 */
	private Deploy_Forge_Connection_Handler $connection_handler;

	/**
	 * Constructor.
	 *
	 * Initialize admin pages with required dependencies and register hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param Deploy_Forge_Settings           $settings           Settings instance.
	 * @param Deploy_Forge_GitHub_API         $github_api         GitHub API instance.
	 * @param Deploy_Forge_Deployment_Manager $deployment_manager Deployment manager instance.
	 * @param Deploy_Forge_Database           $database           Database instance.
	 * @param Deploy_Forge_Debug_Logger       $logger             Debug logger instance.
	 * @param Deploy_Forge_Connection_Handler $connection_handler Connection handler instance.
	 */
	public function __construct(
		Deploy_Forge_Settings $settings,
		Deploy_Forge_GitHub_API $github_api,
		Deploy_Forge_Deployment_Manager $deployment_manager,
		Deploy_Forge_Database $database,
		Deploy_Forge_Debug_Logger $logger,
		Deploy_Forge_Connection_Handler $connection_handler
	) {
		$this->settings           = $settings;
		$this->github_api         = $github_api;
		$this->deployment_manager = $deployment_manager;
		$this->database           = $database;
		$this->logger             = $logger;
		$this->connection_handler = $connection_handler;

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_head', array( $this, 'admin_menu_icon_styles' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_deploy_forge_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_deploy_forge_manual_deploy', array( $this, 'ajax_manual_deploy' ) );
		add_action( 'wp_ajax_deploy_forge_get_status', array( $this, 'ajax_get_status' ) );
		add_action( 'wp_ajax_deploy_forge_rollback', array( $this, 'ajax_rollback' ) );
		add_action( 'wp_ajax_deploy_forge_approve', array( $this, 'ajax_approve_deployment' ) );
		add_action( 'wp_ajax_deploy_forge_cancel', array( $this, 'ajax_cancel_deployment' ) );
		add_action( 'wp_ajax_deploy_forge_get_commits', array( $this, 'ajax_get_commits' ) );
		add_action( 'wp_ajax_deploy_forge_get_repos', array( $this, 'ajax_get_repos' ) );
		add_action( 'wp_ajax_deploy_forge_get_workflows', array( $this, 'ajax_get_workflows' ) );
		add_action( 'wp_ajax_deploy_forge_get_logs', array( $this, 'ajax_get_logs' ) );
		add_action( 'wp_ajax_deploy_forge_clear_logs', array( $this, 'ajax_clear_logs' ) );

		// File change detection AJAX handlers.
		add_action( 'wp_ajax_deploy_forge_check_changes', array( $this, 'ajax_check_changes' ) );
		add_action( 'wp_ajax_deploy_forge_get_file_diff', array( $this, 'ajax_get_file_diff' ) );

		// Deploy Forge platform connection AJAX handlers.
		add_action( 'wp_ajax_deploy_forge_connect', array( $this, 'ajax_connect' ) );
		add_action( 'wp_ajax_deploy_forge_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_deploy_forge_verify_connection', array( $this, 'ajax_verify_connection' ) );
	}

	/**
	 * Override base class log method to use logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context Log context identifier.
	 * @param string $message Log message.
	 * @param array  $data    Additional data to log.
	 * @return void
	 */
	protected function log( string $context, string $message, array $data = array() ): void {
		$this->logger->log( $context, $message, $data );
	}

	/**
	 * Add admin menu pages.
	 *
	 * Registers the main Deploy Forge menu and all submenu pages
	 * in the WordPress admin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'Deploy Forge', 'deploy-forge' ),
			__( 'Deploy Forge', 'deploy-forge' ),
			'manage_options',
			'deploy-forge',
			array( $this, 'render_deployments_page' ),
			plugins_url( 'admin/images/deploy-forge-menu-icon.png', __DIR__ ),
			80
		);

		add_submenu_page(
			'deploy-forge',
			__( 'Deployments', 'deploy-forge' ),
			__( 'Deployments', 'deploy-forge' ),
			'manage_options',
			'deploy-forge',
			array( $this, 'render_deployments_page' )
		);

		add_submenu_page(
			'deploy-forge',
			__( 'Settings', 'deploy-forge' ),
			__( 'Settings', 'deploy-forge' ),
			'manage_options',
			'deploy-forge-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'deploy-forge',
			__( 'Debug Logs', 'deploy-forge' ),
			__( 'Debug Logs', 'deploy-forge' ),
			'manage_options',
			'deploy-forge-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Output inline styles for admin menu icon.
	 *
	 * This runs on all admin pages to ensure the icon is always fully visible.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function admin_menu_icon_styles(): void {
		?>
		<style>
			#adminmenu .toplevel_page_deploy-forge .wp-menu-image img {
				opacity: 1;
				padding-top: 6px;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue admin assets.
	 *
	 * Loads CSS and JavaScript files needed for the admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load on our plugin pages.
		if ( false === strpos( $hook, 'deploy-forge' ) ) {
			return;
		}

		// Enqueue shared styles first.
		wp_enqueue_style(
			'deploy-forge-shared',
			DEPLOY_FORGE_PLUGIN_URL . 'admin/css/shared-styles.css',
			array(),
			DEPLOY_FORGE_VERSION
		);

		// Enqueue admin-specific styles.
		wp_enqueue_style(
			'deploy-forge-admin',
			DEPLOY_FORGE_PLUGIN_URL . 'admin/css/admin-styles.css',
			array( 'deploy-forge-shared' ),
			DEPLOY_FORGE_VERSION
		);

		// Enqueue shared AJAX utilities.
		wp_enqueue_script(
			'deploy-forge-ajax-utils',
			DEPLOY_FORGE_PLUGIN_URL . 'admin/js/ajax-utilities.js',
			array( 'jquery' ),
			DEPLOY_FORGE_VERSION,
			true
		);

		// Enqueue admin-specific scripts (depends on AJAX utilities).
		wp_enqueue_script(
			'deploy-forge-admin',
			DEPLOY_FORGE_PLUGIN_URL . 'admin/js/admin-scripts.js',
			array( 'jquery', 'deploy-forge-ajax-utils' ),
			DEPLOY_FORGE_VERSION,
			true
		);

		wp_localize_script(
			'deploy-forge-admin',
			'deployForgeAdmin',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'deploy_forge_admin' ),
				'activeDeploymentId' => $this->database->get_active_deployment_id(),
				'strings'            => array(
					'confirmRollback' => __( 'Are you sure you want to rollback to this deployment? This will restore the previous theme files.', 'deploy-forge' ),
					'confirmDeploy'   => __( 'Are you sure you want to start a deployment?', 'deploy-forge' ),
					'confirmCancel'   => __( 'Are you sure you want to cancel this deployment? The GitHub Actions workflow will be stopped.', 'deploy-forge' ),
					'deploying'       => __( 'Deploying...', 'deploy-forge' ),
					'testing'         => __( 'Testing connection...', 'deploy-forge' ),
					'cancelling'      => __( 'Cancelling...', 'deploy-forge' ),
					'checkingChanges' => __( 'Checking...', 'deploy-forge' ),
					'noChanges'       => __( 'No changes', 'deploy-forge' ),
					'changesDetected' => __( 'changes detected', 'deploy-forge' ),
					'loadingDiff'     => __( 'Loading diff...', 'deploy-forge' ),
				),
			)
		);
	}

	/**
	 * Register settings.
	 *
	 * Registers the plugin settings with WordPress Settings API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'deploy_forge_settings',
			'deploy_forge_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @since 1.0.46
	 *
	 * @param array $settings The settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		return array(
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
	}

	/**
	 * Render deployments page.
	 *
	 * Displays the main deployments page with deployment statistics,
	 * Deploy Now button, and paginated deployment history.
	 *
	 * @since 1.0.47
	 *
	 * @return void
	 */
	public function render_deployments_page(): void {
		$is_configured = $this->settings->is_configured();
		$dashboard_url = $this->settings->get_backend_url() . '/dashboard';
		$repo_name     = $this->settings->get_repo_full_name();
		$branch        = $this->settings->get( 'github_branch', 'main' );

		$per_page = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination parameter, no sensitive action.
		$paged                    = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
		$offset                   = ( $paged - 1 ) * $per_page;
		$deploy_forge_deployments = $this->database->get_recent_deployments( $per_page, $offset );
		$total_deployments        = $this->database->get_deployment_count();
		$total_pages              = ceil( $total_deployments / $per_page );
		$active_deployment_id     = $this->database->get_active_deployment_id();

		include DEPLOY_FORGE_PLUGIN_DIR . 'templates/deployments-page.php';
	}

	/**
	 * Render settings page.
	 *
	 * Displays the settings form and handles form submission.
	 * Also processes OAuth callbacks from Deploy Forge platform.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// Handle callback from Deploy Forge.
		if ( isset( $_GET['action'] ) && 'df_callback' === $_GET['action'] ) {
			$connection_token = isset( $_GET['connection_token'] ) ? sanitize_text_field( wp_unslash( $_GET['connection_token'] ) ) : '';
			$returned_nonce   = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

			if ( ! empty( $connection_token ) && ! empty( $returned_nonce ) ) {
				$result = $this->connection_handler->handle_callback( $connection_token, $returned_nonce );

				if ( $result['success'] ) {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $result['message'] ) . '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result['message'] ) . '</p></div>';
				}
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid callback parameters.', 'deploy-forge' ) . '</p></div>';
			}

			// Remove query params from URL to prevent reprocessing.
			echo '<script>window.history.replaceState({}, "", "' . esc_url( admin_url( 'admin.php?page=deploy-forge-settings' ) ) . '");</script>';
		}

		// Save settings if form submitted.
		if ( isset( $_POST['deploy_forge_save_settings'] ) ) {
			// Verify nonce.
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'deploy_forge_settings' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Security verification failed. Please try again.', 'deploy-forge' ) . '</p></div>';
			} else {
				$new_settings = array(
					'require_manual_approval' => isset( $_POST['require_manual_approval'] ),
					'create_backups'          => isset( $_POST['create_backups'] ),
					'debug_mode'              => isset( $_POST['debug_mode'] ),
				);

				// Merge with existing settings to preserve repo info.
				$current      = $this->settings->get_all();
				$new_settings = array_merge( $current, $new_settings );

				$this->settings->save( $new_settings );

				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'deploy-forge' ) . '</p></div>';
			}
		}

		$current_settings = $this->settings->get_all();
		$is_connected     = $this->settings->is_connected();
		$connection_data  = $this->settings->get_connection_data();
		$settings         = $this->settings; // Make settings object available to template.

		include DEPLOY_FORGE_PLUGIN_DIR . 'templates/settings-page.php';
	}


	/**
	 * AJAX: Test GitHub connection.
	 *
	 * Tests the connection to GitHub API using stored credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$result = $this->github_api->test_connection();
		$this->handle_api_response( $result );
	}

	/**
	 * AJAX: Manual deployment.
	 *
	 * Initiates a manual deployment for the specified or latest commit.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_manual_deploy(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		try {
			$commit_hash = $this->get_post_param( 'commit_hash' );

			if ( empty( $commit_hash ) ) {
				// Get latest commit.
				$commits = $this->github_api->get_recent_commits( 1 );
				if ( ! $commits['success'] || empty( $commits['data'] ) ) {
					$this->send_error( __( 'Failed to get latest commit', 'deploy-forge' ) );
					return;
				}

				// Handle both array and object responses.
				$first_commit = is_array( $commits['data'] ) ? $commits['data'][0] : $commits['data'][0];
				$commit_hash  = is_object( $first_commit ) ? $first_commit->sha : $first_commit['sha'];
			}

			// Get commit details.
			$commit_result = $this->github_api->get_commit_details( $commit_hash );
			if ( ! $commit_result['success'] ) {
				$this->send_error( __( 'Failed to get commit details', 'deploy-forge' ) );
				return;
			}

			$commit_data = $commit_result['data'];

			// Handle both array and object responses for commit data.
			if ( is_object( $commit_data ) ) {
				$commit_message = $commit_data->commit->message ?? '';
				$commit_author  = $commit_data->commit->author->name ?? '';
				$commit_date    = $commit_data->commit->author->date ?? current_time( 'mysql' );
			} else {
				$commit_message = $commit_data['commit']['message'] ?? '';
				$commit_author  = $commit_data['commit']['author']['name'] ?? '';
				$commit_date    = $commit_data['commit']['author']['date'] ?? current_time( 'mysql' );
			}

			$deployment_result = $this->deployment_manager->start_deployment(
				$commit_hash,
				'manual',
				get_current_user_id(),
				array(
					'commit_message' => $commit_message,
					'commit_author'  => $commit_author,
					'commit_date'    => $commit_date,
				)
			);

			// Check if result is an array (error) or int (success).
			if ( is_array( $deployment_result ) && isset( $deployment_result['error'] ) ) {
				// Deployment blocked due to existing build.
				$this->send_error(
					$deployment_result['message'],
					$deployment_result['error'],
					array(
						'building_deployment' => array(
							'id'          => $deployment_result['building_deployment']->id ?? 0,
							'commit_hash' => $deployment_result['building_deployment']->commit_hash ?? '',
							'status'      => $deployment_result['building_deployment']->status ?? '',
							'created_at'  => $deployment_result['building_deployment']->created_at ?? '',
						),
					)
				);
				return;
			}

			$this->log(
				'Admin',
				'Deployment result received',
				array(
					'deployment_result' => $deployment_result,
					'type'              => gettype( $deployment_result ),
					'is_truthy'         => (bool) $deployment_result,
				)
			);

			if ( $deployment_result ) {
				$this->log( 'Admin', 'Sending success response', array( 'deployment_id' => $deployment_result ) );
				$this->send_success(
					array( 'deployment_id' => $deployment_result ),
					__( 'Deployment started successfully!', 'deploy-forge' )
				);
			} else {
				$this->log( 'Admin', 'Sending error response - deployment result was falsy' );
				$this->send_error( __( 'Failed to start deployment', 'deploy-forge' ) );
			}
		} catch ( Exception $e ) {
			$this->logger->error( 'Admin', 'Manual deploy exception', array( 'error' => $e->getMessage() ) );
			// Translators: %s is the error message.
			$this->send_error( sprintf( __( 'Deployment error: %s', 'deploy-forge' ), $e->getMessage() ) );
		}
	}

	/**
	 * AJAX: Get deployment status.
	 *
	 * Retrieves status for a specific deployment or general statistics.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_get_status(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$deployment_id = $this->get_post_int( 'deployment_id' );

		if ( $deployment_id ) {
			$deployment = $this->database->get_deployment( $deployment_id );
			$this->send_success( array( 'deployment' => $deployment ) );
		} else {
			$stats  = $this->database->get_statistics();
			$recent = $this->database->get_recent_deployments( 5 );
			$this->send_success(
				array(
					'stats'  => $stats,
					'recent' => $recent,
				)
			);
		}
	}

	/**
	 * AJAX: Rollback deployment.
	 *
	 * Rolls back to a previous deployment's backup.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_rollback(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$deployment_id = $this->get_post_int( 'deployment_id' );

		if ( ! $deployment_id ) {
			$this->send_error( __( 'Invalid deployment ID', 'deploy-forge' ) );
			return;
		}

		$result = $this->deployment_manager->rollback_deployment( $deployment_id );

		if ( $result ) {
			$this->send_success( null, __( 'Rollback completed successfully!', 'deploy-forge' ) );
		} else {
			$this->send_error( __( 'Rollback failed', 'deploy-forge' ) );
		}
	}

	/**
	 * AJAX: Approve pending deployment (manual approval).
	 *
	 * Approves a pending deployment and starts the workflow.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_approve_deployment(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$deployment_id = $this->get_post_int( 'deployment_id' );

		if ( ! $deployment_id ) {
			$this->send_error( __( 'Invalid deployment ID', 'deploy-forge' ) );
			return;
		}

		// Get deployment details.
		$deployment = $this->database->get_deployment( $deployment_id );

		if ( ! $deployment ) {
			$this->send_error( __( 'Deployment not found', 'deploy-forge' ) );
			return;
		}

		if ( 'pending' !== $deployment->status ) {
			$this->send_error( __( 'Only pending deployments can be approved', 'deploy-forge' ) );
			return;
		}

		// Approve the deployment by triggering the workflow.
		$result = $this->deployment_manager->approve_pending_deployment( $deployment_id, get_current_user_id() );

		if ( $result ) {
			$this->send_success( null, __( 'Deployment approved and started successfully!', 'deploy-forge' ) );
		} else {
			$this->send_error( __( 'Failed to start deployment', 'deploy-forge' ) );
		}
	}

	/**
	 * AJAX: Cancel deployment.
	 *
	 * Cancels an in-progress deployment and stops the GitHub Actions workflow.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_cancel_deployment(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$deployment_id = $this->get_post_int( 'deployment_id' );

		if ( ! $deployment_id ) {
			$this->send_error( __( 'Invalid deployment ID', 'deploy-forge' ) );
			return;
		}

		$result = $this->deployment_manager->cancel_deployment( $deployment_id );

		if ( $result ) {
			$this->send_success( null, __( 'Deployment cancelled successfully!', 'deploy-forge' ) );
		} else {
			$this->send_error( __( 'Failed to cancel deployment. It may have already completed or been cancelled.', 'deploy-forge' ) );
		}
	}

	/**
	 * AJAX: Get recent commits.
	 *
	 * Retrieves the most recent commits from the GitHub repository.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_get_commits(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$result = $this->github_api->get_recent_commits( 10 );
		$this->handle_api_response( $result );
	}

	/**
	 * AJAX: Get user repositories.
	 *
	 * Retrieves all repositories accessible to the authenticated user.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_get_repos(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$result = $this->github_api->get_user_repositories();

		if ( $result['success'] ) {
			$this->send_success( array( 'repos' => $result['data'] ) );
		} else {
			$this->send_error( $result['message'] );
		}
	}

	/**
	 * AJAX: Get repository workflows.
	 *
	 * Retrieves all workflows for a specified repository.
	 * SECURITY: Validates nonce, capability, and sanitizes inputs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_get_workflows(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		// SECURITY: Sanitize and validate input parameters.
		$owner = $this->get_post_param( 'owner' );
		$repo  = $this->get_post_param( 'repo' );

		if ( empty( $owner ) || empty( $repo ) ) {
			$this->send_error( __( 'Missing owner or repo parameter', 'deploy-forge' ) );
			return;
		}

		// Additional validation: Check for valid characters.
		if ( ! preg_match( '/^[a-zA-Z0-9_-]+$/', $owner ) || ! preg_match( '/^[a-zA-Z0-9_.-]+$/', $repo ) ) {
			$this->send_error( __( 'Invalid repository format', 'deploy-forge' ) );
			return;
		}

		$result = $this->github_api->get_workflows( $owner, $repo );

		if ( $result['success'] ) {
			$this->send_success(
				array(
					'workflows'   => $result['workflows'],
					'total_count' => $result['total_count'],
				)
			);
		} else {
			$this->send_error( $result['message'] );
		}
	}

	/**
	 * AJAX: Generate webhook secret.
	 *
	 * Generates a new webhook secret for GitHub webhook validation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_generate_secret(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$secret = $this->settings->generate_webhook_secret();

		if ( $secret ) {
			$this->send_success( array( 'secret' => $secret ) );
		} else {
			$this->send_error( __( 'Failed to generate secret', 'deploy-forge' ) );
		}
	}

	/**
	 * Render debug logs page.
	 *
	 * Displays the debug logs viewer or a message if debug mode is disabled.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_logs_page(): void {
		if ( ! $this->logger->is_enabled() ) {
			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Debug Logs', 'deploy-forge' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Debug mode is not enabled. Enable it in ', 'deploy-forge' );
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=deploy-forge-settings' ) ) . '">';
			echo esc_html__( 'Settings', 'deploy-forge' );
			echo '</a> to start logging.';
			echo '</p></div>';
			echo '</div>';
			return;
		}

		include DEPLOY_FORGE_PLUGIN_DIR . 'templates/logs-page.php';
	}

	/**
	 * AJAX: Get debug logs.
	 *
	 * Retrieves recent debug log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_get_logs(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$lines = $this->get_post_int( 'lines', 100 );
		$logs  = $this->logger->get_recent_logs( $lines );
		$size  = $this->logger->get_log_size();

		$this->send_success(
			array(
				'logs' => $logs,
				'size' => $size,
			)
		);
	}

	/**
	 * AJAX: Clear debug logs.
	 *
	 * Clears all debug log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_clear_logs(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$result = $this->logger->clear_logs();

		if ( $result ) {
			$this->send_success( null, __( 'Logs cleared successfully', 'deploy-forge' ) );
		} else {
			$this->send_error( __( 'Failed to clear logs', 'deploy-forge' ) );
		}
	}

	/**
	 * AJAX: Check for file changes on the active deployment.
	 *
	 * Compares the live theme files against the deployed file manifest.
	 *
	 * @since 1.0.52
	 *
	 * @return void
	 */
	public function ajax_check_changes(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$deployment_id = $this->get_post_int( 'deployment_id' );

		if ( ! $deployment_id ) {
			$deployment_id = $this->database->get_active_deployment_id();
		}

		if ( ! $deployment_id ) {
			$this->send_error( __( 'No active deployment found', 'deploy-forge' ) );
			return;
		}

		$force   = $this->get_post_bool( 'force' );
		$changes = $this->deployment_manager->detect_file_changes( $deployment_id, $force );

		if ( false === $changes ) {
			$this->send_error( __( 'Unable to check changes. No file manifest available for this deployment.', 'deploy-forge' ) );
			return;
		}

		$this->send_success( array( 'changes' => $changes ) );
	}

	/**
	 * AJAX: Get unified diff for a specific file.
	 *
	 * Returns the diff between the deployed and current live version of a file.
	 *
	 * @since 1.0.52
	 *
	 * @return void
	 */
	public function ajax_get_file_diff(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$deployment_id = $this->get_post_int( 'deployment_id' );
		$file_path     = $this->get_post_param( 'file_path' );

		if ( ! $deployment_id || empty( $file_path ) ) {
			$this->send_error( __( 'Missing deployment ID or file path', 'deploy-forge' ) );
			return;
		}

		$diff_data = $this->deployment_manager->get_file_diff( $deployment_id, $file_path );

		if ( false === $diff_data ) {
			$this->send_error( __( 'Unable to generate diff for this file', 'deploy-forge' ) );
			return;
		}

		$this->send_success( array( 'diff' => $diff_data ) );
	}

	/**
	 * AJAX: Initiate connection to Deploy Forge.
	 *
	 * Starts the OAuth flow to connect to the Deploy Forge platform.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_connect(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$result = $this->connection_handler->initiate_connection();

		if ( $result['success'] ) {
			$this->send_success(
				array(
					'redirect_url' => $result['redirect_url'],
				),
				__( 'Redirecting to Deploy Forge...', 'deploy-forge' )
			);
		} else {
			$this->send_error( $result['message'] );
		}
	}

	/**
	 * AJAX: Disconnect from Deploy Forge.
	 *
	 * Removes the connection to the Deploy Forge platform.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_disconnect(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$result = $this->connection_handler->disconnect();

		if ( $result['success'] ) {
			$this->send_success( null, $result['message'] );
		} else {
			$this->send_error( $result['message'] );
		}
	}

	/**
	 * AJAX: Verify Deploy Forge connection.
	 *
	 * Verifies the current connection status with the Deploy Forge platform.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_verify_connection(): void {
		$this->verify_ajax_request( 'deploy_forge_admin' );

		$result = $this->connection_handler->verify_connection();

		if ( $result['success'] ) {
			$this->send_success(
				array(
					'connected' => $result['connected'],
					'site_id'   => $result['site_id'] ?? '',
					'domain'    => $result['domain'] ?? '',
					'status'    => $result['status'] ?? 'active',
				)
			);
		} else {
			$this->send_error( $result['message'] );
		}
	}
}
