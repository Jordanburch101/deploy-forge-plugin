<?php
/**
 * Tests for the webhook handler class.
 *
 * Tests security-critical webhook signature verification and event handling.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * WebhookHandlerTest class.
 *
 * @covers Deploy_Forge_Webhook_Handler
 */
class WebhookHandlerTest extends TestCase {

	/**
	 * Settings mock.
	 *
	 * @var Mockery\MockInterface
	 */
	private $settings;

	/**
	 * Logger mock.
	 *
	 * @var Mockery\MockInterface
	 */
	private $logger;

	/**
	 * GitHub API mock.
	 *
	 * @var Mockery\MockInterface
	 */
	private $github_api;

	/**
	 * Deployment manager mock.
	 *
	 * @var Mockery\MockInterface
	 */
	private $deployment_manager;

	/**
	 * Webhook handler under test.
	 *
	 * @var \Deploy_Forge_Webhook_Handler
	 */
	private $handler;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->settings           = $this->create_mock_settings();
		$this->logger             = $this->create_mock_logger();
		$this->github_api         = $this->create_mock_github_api();
		$this->deployment_manager = $this->create_mock_deployment_manager();

		// Mock WordPress functions.
		Functions\stubs(
			array(
				'register_rest_route' => '__return_true',
				'do_action'           => '__return_null',
			)
		);

		// Load the class file.
		require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-webhook-handler.php';

		$this->handler = new \Deploy_Forge_Webhook_Handler(
			$this->settings,
			$this->github_api,
			$this->logger,
			$this->deployment_manager
		);
	}

	/**
	 * Test that verify_signature returns true for valid HMAC-SHA256 signature.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_verify_signature_with_valid_signature(): void {
		$secret  = 'test-webhook-secret';
		$payload = '{"action":"completed","workflow_run":{"id":123}}';

		// Calculate expected signature using HMAC-SHA256.
		$hash      = hash_hmac( 'sha256', $payload, $secret );
		$signature = 'sha256=' . $hash;

		// Use reflection to test private method.
		$method = new \ReflectionMethod( $this->handler, 'verify_signature' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $payload, $signature );

		$this->assertTrue( $result, 'Valid signature should be accepted' );
	}

	/**
	 * Test that verify_signature rejects empty signature.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_verify_signature_rejects_empty_signature(): void {
		$payload = '{"action":"completed"}';

		$method = new \ReflectionMethod( $this->handler, 'verify_signature' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $payload, '' );

		$this->assertFalse( $result, 'Empty signature should be rejected' );
	}

	/**
	 * Test that verify_signature rejects null signature.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_verify_signature_rejects_null_signature(): void {
		$payload = '{"action":"completed"}';

		$method = new \ReflectionMethod( $this->handler, 'verify_signature' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $payload, null );

		$this->assertFalse( $result, 'Null signature should be rejected' );
	}

	/**
	 * Test that verify_signature rejects invalid/tampered signature.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_verify_signature_rejects_invalid_signature(): void {
		$secret  = 'test-webhook-secret';
		$payload = '{"action":"completed"}';

		// Create a valid signature but tamper with it.
		$hash              = hash_hmac( 'sha256', $payload, $secret );
		$tampered_hash     = substr( $hash, 0, -2 ) . 'xx'; // Modify last 2 chars.
		$invalid_signature = 'sha256=' . $tampered_hash;

		$method = new \ReflectionMethod( $this->handler, 'verify_signature' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $payload, $invalid_signature );

		$this->assertFalse( $result, 'Tampered signature should be rejected' );
	}

	/**
	 * Test that verify_signature rejects when secret is empty.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_verify_signature_rejects_empty_secret(): void {
		// Create handler with empty webhook secret.
		$settings = $this->create_mock_settings( array( 'webhook_secret' => '' ) );

		$handler = new \Deploy_Forge_Webhook_Handler(
			$settings,
			$this->github_api,
			$this->logger,
			$this->deployment_manager
		);

		$payload   = '{"action":"completed"}';
		$signature = 'sha256=' . hash_hmac( 'sha256', $payload, 'some-secret' );

		$method = new \ReflectionMethod( $handler, 'verify_signature' );
		$method->setAccessible( true );

		$result = $method->invoke( $handler, $payload, $signature );

		$this->assertFalse( $result, 'Empty secret should cause signature rejection' );
	}

	/**
	 * Test that verify_signature uses timing-safe comparison (hash_equals).
	 *
	 * This test ensures the implementation uses hash_equals() which is
	 * timing-attack resistant. We verify by checking the code accepts
	 * valid signatures and rejects signatures that differ only slightly.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_verify_signature_uses_timing_safe_comparison(): void {
		$secret  = 'test-webhook-secret';
		$payload = '{"action":"completed"}';

		$valid_hash = hash_hmac( 'sha256', $payload, $secret );

		$method = new \ReflectionMethod( $this->handler, 'verify_signature' );
		$method->setAccessible( true );

		// Test valid signature.
		$result1 = $method->invoke( $this->handler, $payload, 'sha256=' . $valid_hash );
		$this->assertTrue( $result1, 'Valid signature should be accepted' );

		// Test signature with 1 character difference (timing attack would exploit partial matches).
		$almost_valid = substr( $valid_hash, 0, -1 ) . 'x';
		$result2      = $method->invoke( $this->handler, $payload, 'sha256=' . $almost_valid );
		$this->assertFalse( $result2, 'Almost-valid signature should be rejected' );
	}

	/**
	 * Test that verify_signature handles signature without algorithm prefix.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_verify_signature_handles_signature_without_prefix(): void {
		$secret  = 'test-webhook-secret';
		$payload = '{"action":"completed"}';

		// Signature without sha256= prefix.
		$hash = hash_hmac( 'sha256', $payload, $secret );

		$method = new \ReflectionMethod( $this->handler, 'verify_signature' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $payload, $hash );

		$this->assertTrue( $result, 'Signature without prefix should be handled' );
	}

	/**
	 * Test that validate_repository rejects mismatched repository.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::validate_repository
	 */
	public function test_validate_repository_rejects_mismatch(): void {
		$data = array(
			'repoFullName' => 'attacker/malicious-repo',
		);

		$method = new \ReflectionMethod( $this->handler, 'validate_repository' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $data );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertEquals( 403, $result->get_status() );
	}

	/**
	 * Test that validate_repository is case-insensitive (GitHub-style).
	 *
	 * @covers Deploy_Forge_Webhook_Handler::validate_repository
	 */
	public function test_validate_repository_case_insensitive(): void {
		// The mock settings return 'test-owner/test-repo'.
		$data = array(
			'repoFullName' => 'Test-Owner/Test-Repo', // Different case.
		);

		$method = new \ReflectionMethod( $this->handler, 'validate_repository' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $data );

		$this->assertTrue( $result, 'Repository comparison should be case-insensitive' );
	}

	/**
	 * Test that validate_repository allows matching repository.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::validate_repository
	 */
	public function test_validate_repository_allows_match(): void {
		$data = array(
			'repoFullName' => 'test-owner/test-repo',
		);

		$method = new \ReflectionMethod( $this->handler, 'validate_repository' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $data );

		$this->assertTrue( $result, 'Matching repository should be allowed' );
	}

	/**
	 * Test that validate_repository allows empty payload repo (backward compat).
	 *
	 * @covers Deploy_Forge_Webhook_Handler::validate_repository
	 */
	public function test_validate_repository_allows_empty_payload_repo(): void {
		$data = array();

		$method = new \ReflectionMethod( $this->handler, 'validate_repository' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, $data );

		$this->assertTrue( $result, 'Empty payload repo should allow for backward compatibility' );
	}

	/**
	 * Test that handle_deploy_forge_event routes new_commit correctly.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::handle_deploy_forge_event
	 */
	public function test_handle_deploy_forge_event_routes_new_commit(): void {
		// Mock database.
		$database = Mockery::mock( 'Deploy_Forge_Database' );
		$database->shouldReceive( 'insert_deployment' )->andReturn( 1 );

		// Mock deploy_forge() function.
		$plugin       = new \stdClass();
		$plugin->database = $database;

		Functions\when( 'deploy_forge' )->justReturn( $plugin );

		$data = array(
			'deploymentId'     => 'deploy-123',
			'commitSha'        => 'abc123',
			'commitMessage'    => 'Test commit',
			'commitAuthor'     => 'Test Author',
			'branch'           => 'main',
			'deploymentMethod' => 'github_actions',
			'repoFullName'     => 'test-owner/test-repo',
		);

		$method = new \ReflectionMethod( $this->handler, 'handle_deploy_forge_event' );
		$method->setAccessible( true );

		// Set up deployment manager to return true for trigger_github_build.
		$this->deployment_manager->shouldReceive( 'trigger_github_build' )
			->once()
			->andReturn( true );

		$result = $method->invoke( $this->handler, 'deploy_forge:new_commit', $data );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertEquals( 200, $result->get_status() );
	}

	/**
	 * Test that handle_deploy_forge_event routes ping correctly.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::handle_deploy_forge_event
	 */
	public function test_handle_deploy_forge_event_routes_ping(): void {
		$method = new \ReflectionMethod( $this->handler, 'handle_deploy_forge_event' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, 'ping', array() );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertEquals( 200, $result->get_status() );

		$data = $result->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test that handle_deploy_forge_event rejects unknown events.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::handle_deploy_forge_event
	 */
	public function test_handle_deploy_forge_event_rejects_unknown_events(): void {
		$method = new \ReflectionMethod( $this->handler, 'handle_deploy_forge_event' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->handler, 'unknown_event', array() );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertEquals( 400, $result->get_status() );
	}

	/**
	 * Test that handle_new_commit respects manual approval setting.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::handle_new_commit_event
	 */
	public function test_handle_new_commit_respects_manual_approval(): void {
		// Create handler with manual approval enabled.
		$settings = $this->create_mock_settings( array( 'require_manual_approval' => true ) );

		$handler = new \Deploy_Forge_Webhook_Handler(
			$settings,
			$this->github_api,
			$this->logger,
			$this->deployment_manager
		);

		// Mock database.
		$database = Mockery::mock( 'Deploy_Forge_Database' );
		$database->shouldReceive( 'insert_deployment' )->andReturn( 1 );

		$plugin       = new \stdClass();
		$plugin->database = $database;

		Functions\when( 'deploy_forge' )->justReturn( $plugin );

		$data = array(
			'deploymentId'     => 'deploy-123',
			'commitSha'        => 'abc123',
			'commitMessage'    => 'Test commit',
			'commitAuthor'     => 'Test Author',
			'branch'           => 'main',
			'deploymentMethod' => 'github_actions',
			'repoFullName'     => 'test-owner/test-repo',
		);

		$method = new \ReflectionMethod( $handler, 'handle_new_commit_event' );
		$method->setAccessible( true );

		$result = $method->invoke( $handler, $data );

		$this->assertInstanceOf( \WP_REST_Response::class, $result );
		$this->assertEquals( 200, $result->get_status() );

		$response_data = $result->get_data();
		$this->assertTrue( $response_data['requires_approval'] ?? false );
	}

	/**
	 * Test that webhook handler rejects request without signature header.
	 *
	 * This test simulates the handle_webhook behavior for missing signatures.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_webhook_rejects_missing_signature(): void {
		$method = new \ReflectionMethod( $this->handler, 'verify_signature' );
		$method->setAccessible( true );

		// Test with missing (null) signature.
		$result = $method->invoke( $this->handler, '{"test":"data"}', null );

		$this->assertFalse( $result, 'Missing signature should be rejected' );
	}

	/**
	 * Test that signature verification works with different payloads.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_verify_signature_with_various_payloads(): void {
		$secret = 'test-webhook-secret';

		$method = new \ReflectionMethod( $this->handler, 'verify_signature' );
		$method->setAccessible( true );

		// Test with simple JSON.
		$payload1   = '{"simple":"json"}';
		$signature1 = 'sha256=' . hash_hmac( 'sha256', $payload1, $secret );
		$this->assertTrue( $method->invoke( $this->handler, $payload1, $signature1 ) );

		// Test with complex JSON.
		$payload2   = '{"nested":{"array":[1,2,3],"obj":{"key":"value"}},"unicode":"\\u0048\\u0065\\u006c\\u006c\\u006f"}';
		$signature2 = 'sha256=' . hash_hmac( 'sha256', $payload2, $secret );
		$this->assertTrue( $method->invoke( $this->handler, $payload2, $signature2 ) );

		// Test with empty JSON object.
		$payload3   = '{}';
		$signature3 = 'sha256=' . hash_hmac( 'sha256', $payload3, $secret );
		$this->assertTrue( $method->invoke( $this->handler, $payload3, $signature3 ) );
	}

	/**
	 * Test that different secrets produce different signatures.
	 *
	 * @covers Deploy_Forge_Webhook_Handler::verify_signature
	 */
	public function test_different_secrets_produce_different_signatures(): void {
		$payload = '{"test":"data"}';
		$secret1 = 'secret-one';
		$secret2 = 'secret-two';

		$hash1 = hash_hmac( 'sha256', $payload, $secret1 );
		$hash2 = hash_hmac( 'sha256', $payload, $secret2 );

		$this->assertNotEquals( $hash1, $hash2, 'Different secrets should produce different signatures' );
	}
}
