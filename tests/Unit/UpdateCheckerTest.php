<?php
/**
 * Tests for Deploy_Forge_Update_Checker class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

// Load the class file.
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-update-checker.php';

/**
 * Test case for the Update Checker class.
 *
 * Tests plugin update checking functionality including API calls,
 * caching, and version comparison.
 */
class UpdateCheckerTest extends TestCase {

	/**
	 * Update checker instance.
	 *
	 * @var \Deploy_Forge_Update_Checker
	 */
	private \Deploy_Forge_Update_Checker $checker;

	/**
	 * Transient storage for tests.
	 *
	 * @var array
	 */
	private array $transients = array();

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock plugin_basename.
		Functions\when( 'plugin_basename' )->alias( function ( $file ) {
			return basename( dirname( $file ) ) . '/' . basename( $file );
		} );

		// Mock add_filter to prevent actual hook registration.
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );

		// Set up transient mocks.
		$this->transients = array();

		Functions\when( 'get_transient' )->alias(
			function ( $key ) {
				return $this->transients[ $key ] ?? false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $expiration = 0 ) {
				$this->transients[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_transient' )->alias(
			function ( $key ) {
				unset( $this->transients[ $key ] );
				return true;
			}
		);

		// Mock is_wp_error.
		Functions\when( 'is_wp_error' )->alias( function ( $thing ) {
			return $thing instanceof \WP_Error;
		} );

		// Mock translations.
		Functions\when( '__' )->returnArg( 1 );

		// Mock wp_json_encode.
		Functions\when( 'wp_json_encode' )->alias( function ( $data, $options = 0 ) {
			return json_encode( $data, $options );
		} );

		// Create update checker instance.
		$this->checker = new \Deploy_Forge_Update_Checker(
			'/var/www/wp-content/plugins/deploy-forge/deploy-forge.php',
			'1.0.0',
			'https://update.example.com',
			'test-api-key'
		);
	}

	/**
	 * Test constructor sets properties correctly.
	 *
	 * @return void
	 */
	public function test_constructor_sets_properties(): void {
		$this->assertEquals( 'test-api-key', $this->checker->get_api_key() );
		$this->assertEquals( 'https://update.example.com', $this->checker->get_update_server_url() );
	}

	/**
	 * Test set_api_key updates the API key.
	 *
	 * @return void
	 */
	public function test_set_api_key_updates_key(): void {
		$this->checker->set_api_key( 'new-api-key' );
		$this->assertEquals( 'new-api-key', $this->checker->get_api_key() );
	}

	/**
	 * Test set_update_server_url strips trailing slash.
	 *
	 * @return void
	 */
	public function test_set_update_server_url_strips_trailing_slash(): void {
		$this->checker->set_update_server_url( 'https://new-server.com/' );
		$this->assertEquals( 'https://new-server.com', $this->checker->get_update_server_url() );
	}

	/**
	 * Test check_for_update returns transient when not checked.
	 *
	 * @return void
	 */
	public function test_check_for_update_returns_when_not_checked(): void {
		$transient          = new \stdClass();
		$transient->checked = array(); // Empty checked array.

		$result = $this->checker->check_for_update( $transient );

		$this->assertSame( $transient, $result );
	}

	/**
	 * Test check_for_update uses cached response.
	 *
	 * @return void
	 */
	public function test_check_for_update_uses_cached_response(): void {
		// Set up cached response with higher version.
		$cache_key = 'deploy_forge_update_' . md5( 'deploy-forge/deploy-forge.php' );

		$cached               = new \stdClass();
		$cached->new_version  = '2.0.0';
		$cached->slug         = 'deploy-forge';
		$cached->plugin       = 'deploy-forge/deploy-forge.php';

		$this->transients[ $cache_key ] = $cached;

		$transient                     = new \stdClass();
		$transient->checked            = array( 'deploy-forge/deploy-forge.php' => '1.0.0' );
		$transient->response           = array();

		$result = $this->checker->check_for_update( $transient );

		$this->assertArrayHasKey( 'deploy-forge/deploy-forge.php', $result->response );
		$this->assertEquals( '2.0.0', $result->response['deploy-forge/deploy-forge.php']->new_version );
	}

	/**
	 * Test check_for_update fetches from server when no cache.
	 *
	 * @return void
	 */
	public function test_check_for_update_fetches_from_server(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						(object) array(
							'new_version' => '2.0.0',
							'url'         => 'https://example.com',
							'package'     => 'https://example.com/package.zip',
						)
					),
				)
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn(
				json_encode(
					(object) array(
						'new_version' => '2.0.0',
						'url'         => 'https://example.com',
						'package'     => 'https://example.com/package.zip',
					)
				)
			);

		$transient                     = new \stdClass();
		$transient->checked            = array( 'deploy-forge/deploy-forge.php' => '1.0.0' );
		$transient->response           = array();

		$result = $this->checker->check_for_update( $transient );

		$this->assertArrayHasKey( 'deploy-forge/deploy-forge.php', $result->response );
	}

	/**
	 * Test check_for_update adds to no_update when up to date.
	 *
	 * @return void
	 */
	public function test_check_for_update_adds_to_no_update_when_current(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						(object) array(
							'new_version' => '1.0.0', // Same as current.
							'url'         => 'https://example.com',
							'package'     => 'https://example.com/package.zip',
						)
					),
				)
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn(
				json_encode(
					(object) array(
						'new_version' => '1.0.0',
						'url'         => 'https://example.com',
						'package'     => 'https://example.com/package.zip',
					)
				)
			);

		$transient                     = new \stdClass();
		$transient->checked            = array( 'deploy-forge/deploy-forge.php' => '1.0.0' );
		$transient->response           = array();
		$transient->no_update          = array();

		$result = $this->checker->check_for_update( $transient );

		$this->assertArrayHasKey( 'deploy-forge/deploy-forge.php', $result->no_update );
	}

	/**
	 * Test check_for_update handles WP_Error.
	 *
	 * @return void
	 */
	public function test_check_for_update_handles_wp_error(): void {
		$wp_error = new \WP_Error( 'http_request_failed', 'Connection failed' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( $wp_error );

		$transient                     = new \stdClass();
		$transient->checked            = array( 'deploy-forge/deploy-forge.php' => '1.0.0' );
		$transient->response           = array();

		$result = $this->checker->check_for_update( $transient );

		// Should return transient unchanged on error.
		$this->assertEmpty( $result->response );
	}

	/**
	 * Test check_for_update handles HTTP errors.
	 *
	 * @return void
	 */
	public function test_check_for_update_handles_http_error(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 500 ),
					'body'     => 'Server Error',
				)
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 500 );

		$transient                     = new \stdClass();
		$transient->checked            = array( 'deploy-forge/deploy-forge.php' => '1.0.0' );
		$transient->response           = array();

		$result = $this->checker->check_for_update( $transient );

		// Should return transient unchanged on HTTP error.
		$this->assertEmpty( $result->response );
	}

	/**
	 * Test plugin_info returns result for non-matching slug.
	 *
	 * @return void
	 */
	public function test_plugin_info_returns_result_for_non_matching_slug(): void {
		$args       = new \stdClass();
		$args->slug = 'other-plugin';

		$result = $this->checker->plugin_info( false, 'plugin_information', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Test plugin_info returns result for non-plugin_information action.
	 *
	 * @return void
	 */
	public function test_plugin_info_returns_result_for_other_actions(): void {
		$args       = new \stdClass();
		$args->slug = 'deploy-forge';

		$result = $this->checker->plugin_info( false, 'hot_tags', $args );

		$this->assertFalse( $result );
	}

	/**
	 * Test plugin_info fetches and returns data for matching slug.
	 *
	 * @return void
	 */
	public function test_plugin_info_returns_data_for_matching_slug(): void {
		Functions\expect( 'wp_remote_post' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						(object) array(
							'name'          => 'Deploy Forge',
							'version'       => '2.0.0',
							'download_link' => 'https://example.com/download.zip',
							'last_updated'  => '2024-01-15', // Provide so gmdate isn't called.
						)
					),
				)
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn(
				json_encode(
					(object) array(
						'name'          => 'Deploy Forge',
						'version'       => '2.0.0',
						'download_link' => 'https://example.com/download.zip',
						'last_updated'  => '2024-01-15',
					)
				)
			);

		$args       = new \stdClass();
		$args->slug = 'deploy-forge';

		$result = $this->checker->plugin_info( false, 'plugin_information', $args );

		$this->assertIsObject( $result );
		$this->assertEquals( 'Deploy Forge', $result->name );
		$this->assertEquals( '2.0.0', $result->version );
	}

	/**
	 * Test clear_cache deletes transient.
	 *
	 * @return void
	 */
	public function test_clear_cache_deletes_transient(): void {
		$cache_key                      = 'deploy_forge_update_' . md5( 'deploy-forge/deploy-forge.php' );
		$this->transients[ $cache_key ] = (object) array( 'new_version' => '2.0.0' );

		$this->checker->clear_cache();

		$this->assertArrayNotHasKey( $cache_key, $this->transients );
	}

	/**
	 * Test manual_check clears cache and fetches.
	 *
	 * @return void
	 */
	public function test_manual_check_clears_cache_and_fetches(): void {
		$cache_key                      = 'deploy_forge_update_' . md5( 'deploy-forge/deploy-forge.php' );
		$this->transients[ $cache_key ] = (object) array( 'new_version' => '1.5.0' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						(object) array(
							'new_version' => '2.0.0',
							'url'         => 'https://example.com',
							'package'     => 'https://example.com/package.zip',
						)
					),
				)
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn(
				json_encode(
					(object) array(
						'new_version' => '2.0.0',
						'url'         => 'https://example.com',
						'package'     => 'https://example.com/package.zip',
					)
				)
			);

		$result = $this->checker->manual_check();

		// Cache should have been cleared and fetch should return new info.
		$this->assertIsObject( $result );
		$this->assertEquals( '2.0.0', $result->new_version );
	}

	/**
	 * Test enable_auto_update_support returns update for matching plugin.
	 *
	 * @return void
	 */
	public function test_enable_auto_update_support_returns_update_for_plugin(): void {
		$item         = new \stdClass();
		$item->plugin = 'deploy-forge/deploy-forge.php';

		$result = $this->checker->enable_auto_update_support( true, $item );

		$this->assertTrue( $result );
	}

	/**
	 * Test enable_auto_update_support passes through for other plugins.
	 *
	 * @return void
	 */
	public function test_enable_auto_update_support_passes_through_for_others(): void {
		$item         = new \stdClass();
		$item->plugin = 'other-plugin/other-plugin.php';

		$result = $this->checker->enable_auto_update_support( false, $item );

		$this->assertFalse( $result );
	}

	/**
	 * Test check_for_update includes API key header.
	 *
	 * @return void
	 */
	public function test_check_for_update_includes_api_key_header(): void {
		$captured_args = null;

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturnUsing(
				function ( $url, $args ) use ( &$captured_args ) {
					$captured_args = $args;
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => json_encode(
							(object) array(
								'new_version' => '2.0.0',
								'url'         => 'https://example.com',
								'package'     => 'https://example.com/package.zip',
							)
						),
					);
				}
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn(
				json_encode(
					(object) array(
						'new_version' => '2.0.0',
						'url'         => 'https://example.com',
						'package'     => 'https://example.com/package.zip',
					)
				)
			);

		$transient                     = new \stdClass();
		$transient->checked            = array( 'deploy-forge/deploy-forge.php' => '1.0.0' );
		$transient->response           = array();

		$this->checker->check_for_update( $transient );

		$this->assertArrayHasKey( 'X-API-Key', $captured_args['headers'] );
		$this->assertEquals( 'test-api-key', $captured_args['headers']['X-API-Key'] );
	}

	/**
	 * Test check_for_update skips API key when empty.
	 *
	 * @return void
	 */
	public function test_check_for_update_skips_api_key_when_empty(): void {
		// Create checker without API key.
		$checker = new \Deploy_Forge_Update_Checker(
			'/var/www/wp-content/plugins/deploy-forge/deploy-forge.php',
			'1.0.0',
			'https://update.example.com',
			'' // No API key.
		);

		$captured_args = null;

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturnUsing(
				function ( $url, $args ) use ( &$captured_args ) {
					$captured_args = $args;
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => json_encode(
							(object) array(
								'new_version' => '2.0.0',
								'url'         => 'https://example.com',
								'package'     => 'https://example.com/package.zip',
							)
						),
					);
				}
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn(
				json_encode(
					(object) array(
						'new_version' => '2.0.0',
						'url'         => 'https://example.com',
						'package'     => 'https://example.com/package.zip',
					)
				)
			);

		$transient                     = new \stdClass();
		$transient->checked            = array( 'deploy-forge/deploy-forge.php' => '1.0.0' );
		$transient->response           = array();

		$checker->check_for_update( $transient );

		$this->assertArrayNotHasKey( 'X-API-Key', $captured_args['headers'] );
	}

	/**
	 * Test check_for_update handles invalid JSON response.
	 *
	 * @return void
	 */
	public function test_check_for_update_handles_invalid_json(): void {
		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => 'invalid json',
				)
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( 'invalid json' );

		$transient                     = new \stdClass();
		$transient->checked            = array( 'deploy-forge/deploy-forge.php' => '1.0.0' );
		$transient->response           = array();

		$result = $this->checker->check_for_update( $transient );

		// Should return transient unchanged on invalid JSON.
		$this->assertEmpty( $result->response );
	}
}
