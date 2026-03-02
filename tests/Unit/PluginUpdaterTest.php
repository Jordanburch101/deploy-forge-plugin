<?php
/**
 * Tests for Deploy_Forge_Plugin_Updater class (R2 manifest).
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

// Load the class file.
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-plugin-updater.php';

/**
 * Test case for the Plugin Updater class.
 *
 * Tests update checking, plugin info, caching, and cache clearing
 * against the R2-hosted manifest endpoint.
 */
class PluginUpdaterTest extends TestCase {

	/**
	 * Plugin Updater instance.
	 *
	 * @var \Deploy_Forge_Plugin_Updater
	 */
	private \Deploy_Forge_Plugin_Updater $updater;

	/**
	 * Transient storage reference.
	 *
	 * @var array
	 */
	private array $transients;

	/**
	 * Sample manifest data matching the R2 JSON shape.
	 *
	 * @var array
	 */
	private array $manifest;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Define DEPLOY_FORGE_PLUGIN_BASENAME (not in test bootstrap).
		if ( ! defined( 'DEPLOY_FORGE_PLUGIN_BASENAME' ) ) {
			define( 'DEPLOY_FORGE_PLUGIN_BASENAME', 'deploy-forge/deploy-forge.php' );
		}

		$this->manifest = array(
			'name'            => 'Deploy Forge',
			'slug'            => 'deploy-forge',
			'version'         => '2.0.0',
			'download_url'    => 'https://updates.getdeployforge.com/deploy-forge-2.0.0.zip',
			'requires'        => '5.8',
			'requires_php'    => '8.0',
			'tested'          => '6.9',
			'changelog'       => "### 2.0.0\n- Big update",
			'published_at'    => '2026-03-02T12:00:00Z',
			'checksum_sha256' => 'abc123',
		);

		// Set up transient mocks with in-memory storage.
		$this->transients = array();

		Functions\when( 'get_transient' )->alias(
			function ( $key ) {
				return $this->transients[ $key ] ?? false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $expiration = 0 ) {
				$this->transients[ $key ]               = $value;
				$this->transients[ $key . '__expiry' ]   = $expiration;
				return true;
			}
		);

		Functions\when( 'delete_transient' )->alias(
			function ( $key ) {
				unset( $this->transients[ $key ] );
				unset( $this->transients[ $key . '__expiry' ] );
				return true;
			}
		);

		$this->setup_http_mocks();

		$this->updater = new \Deploy_Forge_Plugin_Updater();
	}

	/**
	 * Helper: mock wp_remote_get to return a successful manifest response.
	 *
	 * @param array $manifest Manifest data to return.
	 * @return void
	 */
	private function mock_manifest_response( array $manifest ): void {
		$response = $this->create_http_response( 200, $manifest );
		Functions\when( 'wp_remote_get' )->justReturn( $response );
	}

	/**
	 * Helper: mock wp_remote_get to return a WP_Error.
	 *
	 * @return void
	 */
	private function mock_wp_error_response(): void {
		$error = $this->create_wp_error( 'http_request_failed', 'Connection timed out' );
		Functions\when( 'wp_remote_get' )->justReturn( $error );
	}

	/**
	 * Helper: create a transient object with `checked` populated.
	 *
	 * @return object Transient with checked data.
	 */
	private function create_update_transient(): object {
		$transient          = new \stdClass();
		$transient->checked = array(
			'deploy-forge/deploy-forge.php' => DEPLOY_FORGE_VERSION,
		);
		$transient->response = array();
		return $transient;
	}

	// ─── check_for_update tests ──────────────────────────────────────

	/**
	 * Test: check_for_update injects update when newer version available.
	 *
	 * @return void
	 */
	public function test_check_for_update_injects_update_when_newer_version(): void {
		$this->mock_manifest_response( $this->manifest );

		$transient = $this->create_update_transient();
		$result    = $this->updater->check_for_update( $transient );

		$basename = DEPLOY_FORGE_PLUGIN_BASENAME;
		$this->assertObjectHasProperty( 'response', $result );
		$this->assertArrayHasKey( $basename, $result->response );

		$update = $result->response[ $basename ];
		$this->assertSame( '2.0.0', $update->new_version );
		$this->assertSame( 'deploy-forge', $update->slug );
		$this->assertSame( $basename, $update->plugin );
		$this->assertSame( $this->manifest['download_url'], $update->package );
		$this->assertSame( 'https://getdeployforge.com', $update->url );
		$this->assertSame( '6.9', $update->tested );
		$this->assertSame( '5.8', $update->requires );
		$this->assertSame( '8.0', $update->requires_php );
	}

	/**
	 * Test: check_for_update skips when current version is latest.
	 *
	 * @return void
	 */
	public function test_check_for_update_skips_when_current_version_is_latest(): void {
		$manifest            = $this->manifest;
		$manifest['version'] = '0.0.1'; // Older than DEPLOY_FORGE_VERSION.
		$this->mock_manifest_response( $manifest );

		$transient = $this->create_update_transient();
		$result    = $this->updater->check_for_update( $transient );

		$this->assertArrayNotHasKey(
			DEPLOY_FORGE_PLUGIN_BASENAME,
			(array) ( $result->response ?? array() )
		);
	}

	/**
	 * Test: check_for_update returns transient unchanged when checked is empty.
	 *
	 * @return void
	 */
	public function test_check_for_update_returns_unchanged_when_checked_empty(): void {
		$transient          = new \stdClass();
		$transient->checked = array();

		$result = $this->updater->check_for_update( $transient );

		$this->assertSame( $transient, $result );
	}

	// ─── Caching tests ───────────────────────────────────────────────

	/**
	 * Test: successful responses are cached for 6 hours.
	 *
	 * @return void
	 */
	public function test_successful_response_cached_for_six_hours(): void {
		$this->mock_manifest_response( $this->manifest );

		$transient = $this->create_update_transient();
		$this->updater->check_for_update( $transient );

		$key = 'deploy_forge_plugin_update';
		$this->assertArrayHasKey( $key, $this->transients );
		$this->assertSame( 21600, $this->transients[ $key . '__expiry' ] );
	}

	/**
	 * Test: failed response (WP_Error) is cached for 1 hour.
	 *
	 * @return void
	 */
	public function test_wp_error_response_cached_for_one_hour(): void {
		$this->mock_wp_error_response();

		$transient = $this->create_update_transient();
		$this->updater->check_for_update( $transient );

		$key = 'deploy_forge_plugin_update';
		$this->assertArrayHasKey( $key, $this->transients );
		$this->assertSame( 'error', $this->transients[ $key ] );
		$this->assertSame( HOUR_IN_SECONDS, $this->transients[ $key . '__expiry' ] );
	}

	/**
	 * Test: non-200 status response is cached as error.
	 *
	 * @return void
	 */
	public function test_non_200_status_cached_as_error(): void {
		$response = $this->create_http_response( 500, 'Server Error' );
		Functions\when( 'wp_remote_get' )->justReturn( $response );

		$transient = $this->create_update_transient();
		$this->updater->check_for_update( $transient );

		$key = 'deploy_forge_plugin_update';
		$this->assertSame( 'error', $this->transients[ $key ] );
		$this->assertSame( HOUR_IN_SECONDS, $this->transients[ $key . '__expiry' ] );
	}

	/**
	 * Test: invalid JSON response is cached as error.
	 *
	 * @return void
	 */
	public function test_invalid_json_cached_as_error(): void {
		$response = $this->create_http_response( 200, 'not valid json{{{' );
		Functions\when( 'wp_remote_get' )->justReturn( $response );

		$transient = $this->create_update_transient();
		$this->updater->check_for_update( $transient );

		$key = 'deploy_forge_plugin_update';
		$this->assertSame( 'error', $this->transients[ $key ] );
	}

	/**
	 * Test: manifest missing version field is cached as error.
	 *
	 * @return void
	 */
	public function test_missing_version_field_cached_as_error(): void {
		$incomplete = $this->manifest;
		unset( $incomplete['version'] );
		$this->mock_manifest_response( $incomplete );

		$transient = $this->create_update_transient();
		$this->updater->check_for_update( $transient );

		$key = 'deploy_forge_plugin_update';
		$this->assertSame( 'error', $this->transients[ $key ] );
	}

	// ─── plugin_info tests ───────────────────────────────────────────

	/**
	 * Test: plugin_info returns details for deploy-forge slug.
	 *
	 * @return void
	 */
	public function test_plugin_info_returns_details_for_deploy_forge(): void {
		$this->mock_manifest_response( $this->manifest );

		$args       = new \stdClass();
		$args->slug = 'deploy-forge';

		$result = $this->updater->plugin_info( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertSame( 'Deploy Forge', $result->name );
		$this->assertSame( 'deploy-forge', $result->slug );
		$this->assertSame( '2.0.0', $result->version );
		$this->assertSame( $this->manifest['download_url'], $result->download_link );
		$this->assertSame( '6.9', $result->tested );
		$this->assertSame( '5.8', $result->requires );
		$this->assertSame( '8.0', $result->requires_php );
		$this->assertSame( '2026-03-02T12:00:00Z', $result->last_updated );
		$this->assertArrayHasKey( 'changelog', $result->sections );
		$this->assertArrayHasKey( 'description', $result->sections );
	}

	/**
	 * Test: plugin_info ignores other slugs.
	 *
	 * @return void
	 */
	public function test_plugin_info_ignores_other_slugs(): void {
		$args       = new \stdClass();
		$args->slug = 'some-other-plugin';

		$result = $this->updater->plugin_info( false, 'plugin_information', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Test: plugin_info ignores non-plugin_information actions.
	 *
	 * @return void
	 */
	public function test_plugin_info_ignores_non_plugin_information_actions(): void {
		$args       = new \stdClass();
		$args->slug = 'deploy-forge';

		$result = $this->updater->plugin_info( false, 'query_plugins', $args );

		$this->assertFalse( $result );
	}

	// ─── clear_update_cache tests ────────────────────────────────────

	/**
	 * Test: clear_update_cache deletes transient after deploy-forge update.
	 *
	 * @return void
	 */
	public function test_clear_update_cache_deletes_transient(): void {
		// Pre-populate the transient.
		$this->transients['deploy_forge_plugin_update'] = $this->manifest;

		$upgrader   = Mockery::mock( 'WP_Upgrader' );
		$hook_extra = array(
			'plugins' => array( DEPLOY_FORGE_PLUGIN_BASENAME ),
		);

		$this->updater->clear_update_cache( $upgrader, $hook_extra );

		$this->assertArrayNotHasKey( 'deploy_forge_plugin_update', $this->transients );
	}

	/**
	 * Test: clear_update_cache ignores other plugins.
	 *
	 * @return void
	 */
	public function test_clear_update_cache_ignores_other_plugins(): void {
		// Pre-populate the transient.
		$this->transients['deploy_forge_plugin_update'] = $this->manifest;

		$upgrader   = Mockery::mock( 'WP_Upgrader' );
		$hook_extra = array(
			'plugins' => array( 'some-other-plugin/other.php' ),
		);

		$this->updater->clear_update_cache( $upgrader, $hook_extra );

		$this->assertArrayHasKey( 'deploy_forge_plugin_update', $this->transients );
	}
}
