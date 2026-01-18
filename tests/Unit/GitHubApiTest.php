<?php
/**
 * Tests for Deploy_Forge_GitHub_API class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

// Load the class file.
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-github-api.php';

/**
 * Test case for the GitHub API class.
 *
 * Tests API authentication, request handling, workflow operations,
 * and artifact downloads.
 */
class GitHubApiTest extends TestCase {

	/**
	 * GitHub API instance.
	 *
	 * @var \Deploy_Forge_GitHub_API
	 */
	private \Deploy_Forge_GitHub_API $api;

	/**
	 * Mock settings instance.
	 *
	 * @var Mockery\MockInterface
	 */
	private $mock_settings;

	/**
	 * Mock logger instance.
	 *
	 * @var Mockery\MockInterface
	 */
	private $mock_logger;

	/**
	 * Transient storage.
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

		$this->mock_settings = $this->create_mock_settings();
		$this->mock_logger   = $this->create_mock_logger();

		// Set up transient mocks.
		$this->transients = array();
		$this->setup_transient_storage();

		// Set up HTTP mocks.
		$this->setup_http_mocks();

		// Define MINUTE_IN_SECONDS if not defined.
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$this->api = new \Deploy_Forge_GitHub_API(
			$this->mock_settings,
			$this->mock_logger
		);
	}

	/**
	 * Set up transient storage mocks.
	 *
	 * @return void
	 */
	private function setup_transient_storage(): void {
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
	}

	/**
	 * Test request adds X-API-Key header.
	 *
	 * @return void
	 */
	public function test_request_adds_auth_header(): void {
		$captured_args = null;

		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status' => 200,
							'data'   => array( 'name' => 'test-repo' ),
						)
					),
				);
			}
		);

		$this->api->test_connection();

		$this->assertNotNull( $captured_args, 'Request should have been made' );
		$this->assertArrayHasKey( 'headers', $captured_args );
		$this->assertArrayHasKey( 'X-API-Key', $captured_args['headers'] );
		$this->assertEquals( 'test-api-key', $captured_args['headers']['X-API-Key'] );
	}

	/**
	 * Test request handles WP_Error response.
	 *
	 * @return void
	 */
	public function test_request_handles_wp_error(): void {
		$wp_error = $this->create_wp_error( 'http_error', 'Connection timed out' );

		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );

		$result = $this->api->test_connection();

		$this->assertFalse( $result['success'], 'Should fail on WP_Error' );
		$this->assertEquals( 'Connection timed out', $result['message'] );
	}

	/**
	 * Test request handles HTTP errors (4xx/5xx).
	 *
	 * @return void
	 */
	public function test_request_handles_http_errors(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 401 ),
				'body'     => json_encode(
					array(
						'error'   => true,
						'message' => 'Invalid API key',
					)
				),
			)
		);

		$result = $this->api->test_connection();

		$this->assertFalse( $result['success'], 'Should fail on HTTP error' );
	}

	/**
	 * Test request returns error when no API key configured.
	 *
	 * @return void
	 */
	public function test_request_fails_without_api_key(): void {
		// Create settings mock with empty API key.
		$settings = Mockery::mock( 'Deploy_Forge_Settings' );
		$settings->shouldReceive( 'get_api_key' )->andReturn( '' );
		$settings->shouldReceive( 'get_backend_url' )->andReturn( 'https://deploy-forge.example.com' );
		$settings->shouldReceive( 'get_repo_full_name' )->andReturn( 'owner/repo' );

		$api = new \Deploy_Forge_GitHub_API( $settings, $this->mock_logger );

		$result = $api->test_connection();

		$this->assertFalse( $result['success'], 'Should fail without API key' );
		$this->assertStringContainsString( 'Not connected', $result['message'] );
	}

	/**
	 * Test get_workflows validates repository format.
	 *
	 * @return void
	 */
	public function test_get_workflows_validates_repo_format(): void {
		// Test invalid owner with special characters.
		$result = $this->api->get_workflows( 'owner/with/slashes', 'repo' );

		$this->assertFalse( $result['success'], 'Should reject invalid owner format' );
		$this->assertStringContainsString( 'Invalid', $result['message'] );
	}

	/**
	 * Test get_workflows rejects injection attempts.
	 *
	 * @return void
	 */
	public function test_get_workflows_rejects_injection(): void {
		// Test with path traversal attempt.
		$result = $this->api->get_workflows( '../etc', 'passwd' );

		$this->assertFalse( $result['success'], 'Should reject path traversal' );
	}

	/**
	 * Test get_workflows filters active workflows only.
	 *
	 * @return void
	 */
	public function test_get_workflows_filters_active_only(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'status' => 200,
						'data'   => array(
							'workflows' => array(
								array(
									'name'  => 'Active Workflow',
									'path'  => '.github/workflows/deploy.yml',
									'state' => 'active',
								),
								array(
									'name'  => 'Inactive Workflow',
									'path'  => '.github/workflows/disabled.yml',
									'state' => 'disabled',
								),
							),
						),
					)
				),
			)
		);

		$result = $this->api->get_workflows( 'test-owner', 'test-repo' );

		$this->assertTrue( $result['success'], 'Should succeed' );
		$this->assertCount( 1, $result['workflows'], 'Should only return active workflows' );
		$this->assertEquals( 'Active Workflow', $result['workflows'][0]['name'] );
	}

	/**
	 * Test get_workflows filters yml/yaml files only.
	 *
	 * @return void
	 */
	public function test_get_workflows_filters_yml_yaml_only(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'status' => 200,
						'data'   => array(
							'workflows' => array(
								array(
									'name'  => 'YML Workflow',
									'path'  => '.github/workflows/deploy.yml',
									'state' => 'active',
								),
								array(
									'name'  => 'YAML Workflow',
									'path'  => '.github/workflows/build.yaml',
									'state' => 'active',
								),
								array(
									'name'  => 'Invalid Extension',
									'path'  => '.github/workflows/test.txt',
									'state' => 'active',
								),
							),
						),
					)
				),
			)
		);

		$result = $this->api->get_workflows( 'test-owner', 'test-repo' );

		$this->assertTrue( $result['success'], 'Should succeed' );
		$this->assertCount( 2, $result['workflows'], 'Should only return yml/yaml files' );
	}

	/**
	 * Test trigger_workflow sends correct payload.
	 *
	 * @return void
	 */
	public function test_trigger_workflow_sends_correct_payload(): void {
		$captured_body = null;

		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status' => 204,
							'data'   => null,
						)
					),
				);
			}
		);

		$result = $this->api->trigger_workflow( 'deploy.yml', 'main' );

		$this->assertTrue( $result['success'], 'Should succeed' );
		$this->assertNotNull( $captured_body, 'Request body should be captured' );
		$this->assertEquals( 'POST', $captured_body['method'] );
		$this->assertStringContainsString( 'deploy.yml', $captured_body['endpoint'] );
		$this->assertEquals( 'main', $captured_body['data']['ref'] );
	}

	/**
	 * Test trigger_workflow uses default branch when none specified.
	 *
	 * @return void
	 */
	public function test_trigger_workflow_uses_default_branch(): void {
		$captured_body = null;

		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status' => 204,
							'data'   => null,
						)
					),
				);
			}
		);

		$this->api->trigger_workflow( 'deploy.yml' );

		$this->assertNotNull( $captured_body );
		$this->assertEquals( 'main', $captured_body['data']['ref'], 'Should use default branch from settings' );
	}

	/**
	 * Test download_artifact handles two-step process (URL + download).
	 *
	 * @return void
	 */
	public function test_download_artifact_handles_two_step(): void {
		$call_count = 0;

		Functions\when( 'wp_remote_get' )->alias(
			function ( $url, $args ) use ( &$call_count ) {
				$call_count++;

				if ( $call_count === 1 ) {
					// Step 1: Return download URL.
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => json_encode(
							array(
								'success'          => true,
								'downloadUrl'      => 'https://github-cdn.example.com/artifact.zip',
								'expiresInSeconds' => 55,
								'artifact'         => array(
									'name'        => 'theme-build',
									'sizeInBytes' => 1024,
								),
							)
						),
					);
				}

				// Step 2: Simulate successful download.
				// Create temp file to simulate download.
				if ( isset( $args['filename'] ) ) {
					file_put_contents( $args['filename'], 'test-content' );
				}

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '',
				);
			}
		);

		$temp_file = sys_get_temp_dir() . '/test-artifact-' . time() . '.zip';

		$result = $this->api->download_artifact( 12345, $temp_file );

		$this->assertTrue( $result, 'Download should succeed' );
		$this->assertEquals( 2, $call_count, 'Should make two HTTP requests' );

		// Cleanup.
		if ( file_exists( $temp_file ) ) {
			unlink( $temp_file );
		}
	}

	/**
	 * Test download_artifact fails when URL request fails.
	 *
	 * @return void
	 */
	public function test_download_artifact_fails_on_url_error(): void {
		$wp_error = $this->create_wp_error( 'http_error', 'Connection failed' );

		Functions\when( 'wp_remote_get' )->justReturn( $wp_error );

		$result = $this->api->download_artifact( 12345, '/tmp/test.zip' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'Connection failed', $result->get_error_message() );
	}

	/**
	 * Test download_artifact validates file exists after download.
	 *
	 * @return void
	 */
	public function test_download_artifact_validates_file(): void {
		$call_count = 0;

		Functions\when( 'wp_remote_get' )->alias(
			function ( $url, $args ) use ( &$call_count ) {
				$call_count++;

				if ( $call_count === 1 ) {
					return array(
						'response' => array( 'code' => 200 ),
						'body'     => json_encode(
							array(
								'success'     => true,
								'downloadUrl' => 'https://cdn.example.com/file.zip',
							)
						),
					);
				}

				// Return 200 but don't create file (simulating empty response).
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '',
				);
			}
		);

		$result = $this->api->download_artifact( 12345, '/tmp/nonexistent-' . time() . '.zip' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'download_failed', $result->get_error_code() );
	}

	/**
	 * Test download_artifact fails without API key.
	 *
	 * @return void
	 */
	public function test_download_artifact_fails_without_api_key(): void {
		$settings = Mockery::mock( 'Deploy_Forge_Settings' );
		$settings->shouldReceive( 'get_api_key' )->andReturn( '' );

		$api = new \Deploy_Forge_GitHub_API( $settings, $this->mock_logger );

		$result = $api->download_artifact( 12345, '/tmp/test.zip' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'no_api_key', $result->get_error_code() );
	}

	/**
	 * Test report_deployment_status sends outcome to backend.
	 *
	 * @return void
	 */
	public function test_report_deployment_status_sends_outcome(): void {
		$captured_body = null;

		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'success' => true,
							'message' => 'Status updated',
						)
					),
				);
			}
		);

		$result = $this->api->report_deployment_status(
			'deploy-123',
			true,
			null,
			'Deployment completed successfully'
		);

		$this->assertTrue( $result['success'], 'Should succeed' );
		$this->assertNotNull( $captured_body );
		$this->assertEquals( 'deploy-123', $captured_body['deploymentId'] );
		$this->assertTrue( $captured_body['success'] );
		$this->assertEquals( 'Deployment completed successfully', $captured_body['logs'] );
	}

	/**
	 * Test report_deployment_status sends failure with error message.
	 *
	 * @return void
	 */
	public function test_report_deployment_status_sends_failure(): void {
		$captured_body = null;

		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( array( 'success' => true ) ),
				);
			}
		);

		$this->api->report_deployment_status(
			'deploy-456',
			false,
			'Extraction failed: corrupt ZIP file'
		);

		$this->assertFalse( $captured_body['success'] );
		$this->assertEquals( 'Extraction failed: corrupt ZIP file', $captured_body['errorMessage'] );
	}

	/**
	 * Test report_deployment_status fails without remote ID.
	 *
	 * @return void
	 */
	public function test_report_deployment_status_fails_without_id(): void {
		$result = $this->api->report_deployment_status( '', true );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'No remote deployment ID', $result['message'] );
	}

	/**
	 * Test get_workflow_run_status returns run data.
	 *
	 * @return void
	 */
	public function test_get_workflow_run_status_returns_data(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'status' => 200,
						'data'   => array(
							'id'         => 123,
							'status'     => 'completed',
							'conclusion' => 'success',
						),
					)
				),
			)
		);

		$result = $this->api->get_workflow_run_status( 123 );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'completed', $result['data']['status'] );
		$this->assertEquals( 'success', $result['data']['conclusion'] );
	}

	/**
	 * Test get_recent_commits uses cache.
	 *
	 * @return void
	 */
	public function test_get_recent_commits_uses_cache(): void {
		// Pre-populate cache.
		$cached_result = array(
			'success' => true,
			'data'    => array(
				array( 'sha' => 'abc123', 'message' => 'Test commit' ),
			),
		);

		$cache_key = 'deploy_forge_commits_' . md5( '/repos/test-owner/test-repo/commits' . 'main' );
		$this->transients[ $cache_key ] = $cached_result;

		// wp_remote_post should NOT be called.
		$called = false;
		Functions\when( 'wp_remote_post' )->alias(
			function () use ( &$called ) {
				$called = true;
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( array( 'status' => 200, 'data' => array() ) ),
				);
			}
		);

		$result = $this->api->get_recent_commits();

		$this->assertFalse( $called, 'Should use cache, not make HTTP request' );
		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'abc123', $result['data'][0]['sha'] );
	}

	/**
	 * Test test_connection returns repository info on success.
	 *
	 * @return void
	 */
	public function test_test_connection_returns_repo_info(): void {
		Functions\when( 'wp_remote_post' )->justReturn(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'status' => 200,
						'data'   => array(
							'name'       => 'test-repo',
							'full_name'  => 'owner/test-repo',
							'private'    => true,
						),
					)
				),
			)
		);

		$result = $this->api->test_connection();

		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertEquals( 'test-repo', $result['data']['name'] );
	}

	/**
	 * Test cancel_workflow_run sends cancel request.
	 *
	 * @return void
	 */
	public function test_cancel_workflow_run_sends_request(): void {
		$captured_body = null;

		Functions\when( 'wp_remote_post' )->alias(
			function ( $url, $args ) use ( &$captured_body ) {
				$captured_body = json_decode( $args['body'], true );
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status' => 202,
							'data'   => null,
						)
					),
				);
			}
		);

		$result = $this->api->cancel_workflow_run( 12345 );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( '12345', $captured_body['endpoint'] );
		$this->assertStringContainsString( 'cancel', $captured_body['endpoint'] );
	}
}
