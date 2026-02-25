<?php
/**
 * E2E Reset Deploy Forge MU-Plugin
 *
 * Must-use plugin that provides a REST endpoint for Playwright E2E tests
 * to fully reset the Deploy Forge plugin state. This runs as a mu-plugin
 * so it loads even when the main plugin is in a fatal/broken state.
 *
 * STAGING ONLY — Never deploy to production.
 *
 * Usage: POST /wp-json/e2e/v1/reset-deploy-forge
 * Header: X-E2E-Secret: <value of E2E_RESET_SECRET env var>
 *
 * @package Deploy_Forge\E2E
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the E2E reset REST route.
 *
 * @since 1.0.0
 *
 * @return void
 */
function deploy_forge_e2e_register_reset_route(): void {
	register_rest_route(
		'e2e/v1',
		'/reset-deploy-forge',
		array(
			'methods'             => 'POST',
			'callback'            => 'deploy_forge_e2e_handle_reset',
			'permission_callback' => 'deploy_forge_e2e_verify_secret',
		)
	);
}
add_action( 'rest_api_init', 'deploy_forge_e2e_register_reset_route' );

/**
 * Verify the shared secret from the X-E2E-Secret header.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request object.
 * @return bool|WP_Error True if the secret is valid, WP_Error otherwise.
 */
function deploy_forge_e2e_verify_secret( WP_REST_Request $request ) {
	$expected = getenv( 'E2E_RESET_SECRET' );

	// If the env var is not set, reject all requests.
	if ( empty( $expected ) ) {
		return new WP_Error(
			'e2e_reset_not_configured',
			'E2E_RESET_SECRET environment variable is not set.',
			array( 'status' => 500 )
		);
	}

	$provided = $request->get_header( 'X-E2E-Secret' );

	if ( empty( $provided ) || ! hash_equals( $expected, $provided ) ) {
		return new WP_Error(
			'e2e_reset_unauthorized',
			'Invalid or missing X-E2E-Secret header.',
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Handle the reset request: fully remove Deploy Forge state.
 *
 * Each step is wrapped in a try/catch or existence check so the endpoint
 * remains idempotent — it succeeds whether the plugin is installed or not.
 *
 * @since 1.0.0
 *
 * @param WP_REST_Request $request The REST request object.
 * @return WP_REST_Response JSON response with cleanup details.
 */
function deploy_forge_e2e_handle_reset( WP_REST_Request $request ): WP_REST_Response {
	$cleaned = array();

	// ── 1. Deactivate the plugin if active ──────────────────────────────
	$plugin_file = 'deploy-forge/deploy-forge.php';

	if ( is_plugin_active( $plugin_file ) ) {
		deactivate_plugins( $plugin_file, true );
		$cleaned['plugin_deactivated'] = true;
	} else {
		$cleaned['plugin_deactivated'] = false;
	}

	// ── 2. Delete plugin files ──────────────────────────────────────────
	// delete_plugins() lives in wp-admin/includes/plugin.php.
	if ( ! function_exists( 'delete_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! function_exists( 'request_filesystem_credentials' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$plugin_dir = WP_PLUGIN_DIR . '/deploy-forge';
	if ( is_dir( $plugin_dir ) ) {
		$result = delete_plugins( array( $plugin_file ) );
		if ( is_wp_error( $result ) ) {
			$cleaned['plugin_deleted'] = false;
			$cleaned['delete_error']   = $result->get_error_message();
		} else {
			$cleaned['plugin_deleted'] = true;
		}
	} else {
		$cleaned['plugin_deleted'] = false;
		$cleaned['delete_note']    = 'Plugin directory did not exist.';
	}

	// ── 3. Drop the deployments table ───────────────────────────────────
	global $wpdb;

	$table_name = $wpdb->prefix . 'github_deployments';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

	$cleaned['table_dropped'] = $table_name;

	// ── 4. Delete all plugin options ────────────────────────────────────
	$options = array(
		'deploy_forge_settings',
		'deploy_forge_api_key',
		'deploy_forge_webhook_secret',
		'deploy_forge_site_id',
		'deploy_forge_connection_data',
		'deploy_forge_db_version',
		'deploy_forge_active_deployment_id',
	);

	$deleted_options = array();
	foreach ( $options as $option ) {
		$deleted_options[ $option ] = delete_option( $option );
	}
	$cleaned['options_deleted'] = $deleted_options;

	// ── 5. Clear all deploy_forge transients ────────────────────────────
	// Transients are stored as _transient_deploy_forge_* in wp_options.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$transient_count = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_deploy_forge_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_deploy_forge_' ) . '%'
		)
	);

	$cleaned['transients_cleared'] = (int) $transient_count;

	// ── 6. Clear cron hooks ─────────────────────────────────────────────
	$cron_hooks = array(
		'deploy_forge_check_build_status',
		'deploy_forge_process_queued_deployment',
		'deploy_forge_process_clone_deployment',
		'deploy_forge_daily_cleanup',
	);

	$cleared_crons = array();
	foreach ( $cron_hooks as $hook ) {
		$next = wp_next_scheduled( $hook );
		wp_clear_scheduled_hook( $hook );
		$cleared_crons[ $hook ] = ( false !== $next );
	}
	$cleaned['cron_hooks_cleared'] = $cleared_crons;

	// ── 7. Report active theme ──────────────────────────────────────────
	$cleaned['active_theme'] = array(
		'name'      => wp_get_theme()->get( 'Name' ),
		'stylesheet' => get_stylesheet(),
		'template'   => get_template(),
	);

	return new WP_REST_Response(
		array(
			'success' => true,
			'cleaned' => $cleaned,
		),
		200
	);
}
