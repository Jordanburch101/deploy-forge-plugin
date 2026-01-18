<?php
/**
 * Tests for Deploy_Forge_Connection_Handler class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

// Load the class files.
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-connection-handler.php';

/**
 * Test case for the Connection Handler class.
 *
 * Tests OAuth flow security including nonce verification,
 * token exchange, and credential storage.
 */
class ConnectionHandlerTest extends TestCase {

	/**
	 * Connection handler instance.
	 *
	 * @var \Deploy_Forge_Connection_Handler
	 */
	private \Deploy_Forge_Connection_Handler $handler;

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

		$this->mock_settings = $this->create_mock_settings();
		$this->mock_logger   = $this->create_mock_logger();

		// Set up transient mocks with storage.
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

		Functions\when( 'wp_generate_password' )->alias(
			function ( $length = 12, $special_chars = true ) {
				return substr( str_repeat( 'a', $length ), 0, $length );
			}
		);

		$this->handler = new \Deploy_Forge_Connection_Handler(
			$this->mock_settings,
			$this->mock_logger
		);
	}

	/**
	 * Test initiate_connection generates and stores nonce.
	 *
	 * @return void
	 */
	public function test_initiate_connection_generates_nonce(): void {
		$this->setup_http_mocks();

		// Mock successful API response.
		$response = $this->create_http_response(
			200,
			array(
				'success'     => true,
				'redirectUrl' => 'https://deploy-forge.example.com/connect?token=abc123',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		$result = $this->handler->initiate_connection();

		$this->assertTrue( $result['success'], 'Connection initiation should succeed' );
		$this->assertArrayHasKey( 'redirect_url', $result, 'Result should contain redirect URL' );

		// Verify nonce was stored in transient.
		$stored_nonce = $this->transients['deploy_forge_connection_nonce'] ?? null;
		$this->assertNotNull( $stored_nonce, 'Nonce should be stored in transient' );
		$this->assertEquals( 16, strlen( $stored_nonce ), 'Nonce should be 16 characters' );
	}

	/**
	 * Test initiate_connection handles WP_Error response.
	 *
	 * @return void
	 */
	public function test_initiate_connection_handles_wp_error(): void {
		$this->setup_http_mocks();

		$wp_error = $this->create_wp_error( 'http_error', 'Connection timed out' );
		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );

		$result = $this->handler->initiate_connection();

		$this->assertFalse( $result['success'], 'Should fail on WP_Error' );
		$this->assertArrayHasKey( 'message', $result, 'Should include error message' );
		$this->assertEquals( 'Connection timed out', $result['message'] );
	}

	/**
	 * Test initiate_connection handles API error response.
	 *
	 * @return void
	 */
	public function test_initiate_connection_handles_api_error(): void {
		$this->setup_http_mocks();

		$response = $this->create_http_response(
			400,
			array(
				'success' => false,
				'error'   => 'Invalid site URL',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		$result = $this->handler->initiate_connection();

		$this->assertFalse( $result['success'], 'Should fail on API error' );
		$this->assertEquals( 'Invalid site URL', $result['message'] );
	}

	/**
	 * Test handle_callback validates nonce correctly.
	 *
	 * @return void
	 */
	public function test_handle_callback_validates_nonce(): void {
		$this->setup_http_mocks();

		// Store a nonce.
		$nonce = 'valid-test-nonce';
		$this->transients['deploy_forge_connection_nonce'] = $nonce;

		// Mock successful exchange.
		$response = $this->create_http_response(
			200,
			array(
				'success'       => true,
				'apiKey'        => 'test-api-key',
				'webhookSecret' => 'test-webhook-secret',
				'siteId'        => 'site-123',
				'domain'        => 'example.com',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		// Configure settings mock to accept credential storage.
		$this->mock_settings->shouldReceive( 'set_api_key' )->once()->with( 'test-api-key' );
		$this->mock_settings->shouldReceive( 'set_webhook_secret' )->once()->with( 'test-webhook-secret' );
		$this->mock_settings->shouldReceive( 'set_site_id' )->once()->with( 'site-123' );
		$this->mock_settings->shouldReceive( 'set_connection_data' )->once();

		$result = $this->handler->handle_callback( 'connection-token', $nonce );

		$this->assertTrue( $result['success'], 'Should succeed with valid nonce' );

		// Verify nonce was cleared after use.
		$this->assertArrayNotHasKey(
			'deploy_forge_connection_nonce',
			$this->transients,
			'Nonce should be cleared after use'
		);
	}

	/**
	 * Test handle_callback rejects expired nonce (empty transient).
	 *
	 * @return void
	 */
	public function test_handle_callback_rejects_expired_nonce(): void {
		// Don't store any nonce (simulates expiration).
		$result = $this->handler->handle_callback( 'connection-token', 'some-nonce' );

		$this->assertFalse( $result['success'], 'Should reject expired nonce' );
		$this->assertStringContainsString(
			'expired',
			strtolower( $result['message'] ),
			'Error message should mention expiration'
		);
	}

	/**
	 * Test handle_callback rejects invalid nonce.
	 *
	 * @return void
	 */
	public function test_handle_callback_rejects_invalid_nonce(): void {
		// Store a different nonce than what will be returned.
		$this->transients['deploy_forge_connection_nonce'] = 'stored-nonce';

		$result = $this->handler->handle_callback( 'connection-token', 'different-nonce' );

		$this->assertFalse( $result['success'], 'Should reject invalid nonce' );
		$this->assertStringContainsString(
			'invalid',
			strtolower( $result['message'] ),
			'Error message should mention invalid'
		);
	}

	/**
	 * Test exchange_token validates HTTP 200 response.
	 *
	 * @return void
	 */
	public function test_exchange_token_validates_response(): void {
		$this->setup_http_mocks();

		// Store valid nonce.
		$nonce = 'valid-nonce';
		$this->transients['deploy_forge_connection_nonce'] = $nonce;

		// Mock 500 error response.
		$response = $this->create_http_response(
			500,
			array(
				'success' => false,
				'error'   => 'Internal server error',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		$result = $this->handler->handle_callback( 'connection-token', $nonce );

		$this->assertFalse( $result['success'], 'Should fail on non-200 response' );
		$this->assertEquals( 'Internal server error', $result['message'] );
	}

	/**
	 * Test exchange_token handles WP_Error response.
	 *
	 * @return void
	 */
	public function test_exchange_token_handles_wp_error(): void {
		$this->setup_http_mocks();

		// Store valid nonce.
		$nonce = 'valid-nonce';
		$this->transients['deploy_forge_connection_nonce'] = $nonce;

		$wp_error = $this->create_wp_error( 'http_error', 'Network unreachable' );
		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );

		$result = $this->handler->handle_callback( 'connection-token', $nonce );

		$this->assertFalse( $result['success'], 'Should fail on WP_Error' );
		$this->assertEquals( 'Network unreachable', $result['message'] );
	}

	/**
	 * Test exchange_token rejects missing credentials in response.
	 *
	 * @return void
	 */
	public function test_exchange_token_rejects_missing_credentials(): void {
		$this->setup_http_mocks();

		// Store valid nonce.
		$nonce = 'valid-nonce';
		$this->transients['deploy_forge_connection_nonce'] = $nonce;

		// Response missing required fields.
		$response = $this->create_http_response(
			200,
			array(
				'success' => true,
				'apiKey'  => 'test-api-key',
				// Missing webhookSecret and siteId.
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		$result = $this->handler->handle_callback( 'connection-token', $nonce );

		$this->assertFalse( $result['success'], 'Should reject missing credentials' );
		$this->assertStringContainsString(
			'invalid credentials',
			strtolower( $result['message'] ),
			'Error should mention invalid credentials'
		);
	}

	/**
	 * Test exchange_token rejects empty required fields.
	 *
	 * @return void
	 */
	public function test_exchange_token_rejects_empty_credentials(): void {
		$this->setup_http_mocks();

		// Store valid nonce.
		$nonce = 'valid-nonce';
		$this->transients['deploy_forge_connection_nonce'] = $nonce;

		// Response with empty required fields.
		$response = $this->create_http_response(
			200,
			array(
				'success'       => true,
				'apiKey'        => '',
				'webhookSecret' => '',
				'siteId'        => '',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		$result = $this->handler->handle_callback( 'connection-token', $nonce );

		$this->assertFalse( $result['success'], 'Should reject empty credentials' );
	}

	/**
	 * Test exchange_token stores valid credentials correctly.
	 *
	 * @return void
	 */
	public function test_exchange_token_stores_valid_credentials(): void {
		$this->setup_http_mocks();

		// Store valid nonce.
		$nonce = 'valid-nonce';
		$this->transients['deploy_forge_connection_nonce'] = $nonce;

		// Complete successful response.
		$response = $this->create_http_response(
			200,
			array(
				'success'          => true,
				'apiKey'           => 'my-api-key-123',
				'webhookSecret'    => 'my-webhook-secret-456',
				'siteId'           => 'site-789',
				'domain'           => 'mysite.com',
				'installationId'   => 'install-001',
				'repoOwner'        => 'myorg',
				'repoName'         => 'mytheme',
				'repoBranch'       => 'main',
				'deploymentMethod' => 'github_actions',
				'workflowPath'     => '.github/workflows/build.yml',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		// Expect credentials to be stored.
		$this->mock_settings->shouldReceive( 'set_api_key' )
			->once()
			->with( 'my-api-key-123' );

		$this->mock_settings->shouldReceive( 'set_webhook_secret' )
			->once()
			->with( 'my-webhook-secret-456' );

		$this->mock_settings->shouldReceive( 'set_site_id' )
			->once()
			->with( 'site-789' );

		$this->mock_settings->shouldReceive( 'set_connection_data' )
			->once()
			->with( Mockery::on( function ( $data ) {
				return isset( $data['installation_id'] )
					&& 'install-001' === $data['installation_id']
					&& 'myorg' === $data['repo_owner']
					&& 'mytheme' === $data['repo_name']
					&& 'main' === $data['repo_branch']
					&& 'github_actions' === $data['deployment_method'];
			} ) );

		$result = $this->handler->handle_callback( 'connection-token', $nonce );

		$this->assertTrue( $result['success'], 'Should succeed with valid credentials' );
		$this->assertArrayHasKey( 'data', $result, 'Should return connection data' );
		$this->assertEquals( 'site-789', $result['data']['site_id'] );
		$this->assertEquals( 'mysite.com', $result['data']['domain'] );
		$this->assertEquals( 'myorg', $result['data']['repo_owner'] );
		$this->assertEquals( 'mytheme', $result['data']['repo_name'] );
	}

	/**
	 * Test disconnect clears credentials.
	 *
	 * @return void
	 */
	public function test_disconnect_clears_credentials(): void {
		$this->mock_settings->shouldReceive( 'disconnect' )
			->once()
			->andReturn( true );

		$result = $this->handler->disconnect();

		$this->assertTrue( $result['success'], 'Disconnect should succeed' );
		$this->assertStringContainsString( 'disconnected', strtolower( $result['message'] ) );
	}

	/**
	 * Test disconnect handles failure.
	 *
	 * @return void
	 */
	public function test_disconnect_handles_failure(): void {
		$this->mock_settings->shouldReceive( 'disconnect' )
			->once()
			->andReturn( false );

		$result = $this->handler->disconnect();

		$this->assertFalse( $result['success'], 'Disconnect should fail' );
		$this->assertStringContainsString( 'failed', strtolower( $result['message'] ) );
	}

	/**
	 * Test verify_connection returns false when not connected.
	 *
	 * @return void
	 */
	public function test_verify_connection_returns_false_when_not_connected(): void {
		// Override the default mock to return not connected.
		$this->mock_settings = Mockery::mock( 'Deploy_Forge_Settings' );
		$this->mock_settings->shouldReceive( 'is_connected' )->andReturn( false );
		$this->mock_settings->shouldReceive( 'get_backend_url' )
			->andReturn( 'https://deploy-forge.example.com' );

		$handler = new \Deploy_Forge_Connection_Handler(
			$this->mock_settings,
			$this->mock_logger
		);

		$result = $handler->verify_connection();

		$this->assertFalse( $result['success'], 'Should fail when not connected' );
		$this->assertFalse( $result['connected'], 'Connected flag should be false' );
	}

	/**
	 * Test verify_connection makes API call when connected.
	 *
	 * @return void
	 */
	public function test_verify_connection_calls_api_when_connected(): void {
		$this->setup_http_mocks();

		$response = $this->create_http_response(
			200,
			array(
				'success' => true,
				'siteId'  => 'site-123',
				'domain'  => 'example.com',
				'status'  => 'active',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		$result = $this->handler->verify_connection();

		$this->assertTrue( $result['success'], 'Should succeed with valid API response' );
		$this->assertTrue( $result['connected'], 'Connected flag should be true' );
		$this->assertEquals( 'site-123', $result['site_id'] );
		$this->assertEquals( 'example.com', $result['domain'] );
		$this->assertEquals( 'active', $result['status'] );
	}

	/**
	 * Test verify_connection handles API error.
	 *
	 * @return void
	 */
	public function test_verify_connection_handles_api_error(): void {
		$this->setup_http_mocks();

		$response = $this->create_http_response(
			401,
			array(
				'success' => false,
				'error'   => 'Invalid API key',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		$result = $this->handler->verify_connection();

		$this->assertFalse( $result['success'], 'Should fail on API error' );
		$this->assertFalse( $result['connected'], 'Connected flag should be false' );
		$this->assertEquals( 'Invalid API key', $result['message'] );
	}

	/**
	 * Test verify_connection handles WP_Error.
	 *
	 * @return void
	 */
	public function test_verify_connection_handles_wp_error(): void {
		$this->setup_http_mocks();

		$wp_error = $this->create_wp_error( 'http_error', 'Connection refused' );
		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );

		$result = $this->handler->verify_connection();

		$this->assertFalse( $result['success'], 'Should fail on WP_Error' );
		$this->assertFalse( $result['connected'], 'Connected flag should be false' );
		$this->assertEquals( 'Connection refused', $result['message'] );
	}

	/**
	 * Test nonce is only valid once (cannot be reused).
	 *
	 * @return void
	 */
	public function test_nonce_cannot_be_reused(): void {
		$this->setup_http_mocks();

		// Store a nonce.
		$nonce = 'one-time-nonce';
		$this->transients['deploy_forge_connection_nonce'] = $nonce;

		// Mock successful exchange.
		$response = $this->create_http_response(
			200,
			array(
				'success'       => true,
				'apiKey'        => 'test-api-key',
				'webhookSecret' => 'test-webhook-secret',
				'siteId'        => 'site-123',
			)
		);

		Functions\when( 'wp_remote_post' )->justReturn( $response );

		$this->mock_settings->shouldReceive( 'set_api_key' )->once();
		$this->mock_settings->shouldReceive( 'set_webhook_secret' )->once();
		$this->mock_settings->shouldReceive( 'set_site_id' )->once();
		$this->mock_settings->shouldReceive( 'set_connection_data' )->once();

		// First call should succeed.
		$result1 = $this->handler->handle_callback( 'token1', $nonce );
		$this->assertTrue( $result1['success'], 'First callback should succeed' );

		// Second call with same nonce should fail (nonce was deleted).
		$result2 = $this->handler->handle_callback( 'token2', $nonce );
		$this->assertFalse( $result2['success'], 'Second callback should fail (nonce consumed)' );
	}
}
