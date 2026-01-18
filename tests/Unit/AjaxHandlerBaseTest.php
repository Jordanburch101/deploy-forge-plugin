<?php
/**
 * Tests for Deploy_Forge_Ajax_Handler_Base class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;

// Load the class file.
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-ajax-handler-base.php';

/**
 * Concrete implementation for testing the abstract base class.
 */
class TestableAjaxHandler extends \Deploy_Forge_Ajax_Handler_Base {

	/**
	 * Public wrapper for verify_ajax_request.
	 *
	 * @param string $nonce_action Nonce action.
	 * @param string $capability   User capability.
	 * @return bool Result.
	 */
	public function public_verify_ajax_request( string $nonce_action, string $capability = 'manage_options' ): bool {
		return $this->verify_ajax_request( $nonce_action, $capability );
	}

	/**
	 * Public wrapper for send_success.
	 *
	 * @param mixed  $data    Data.
	 * @param string $message Message.
	 * @return void
	 */
	public function public_send_success( $data = null, string $message = '' ): void {
		$this->send_success( $data, $message );
	}

	/**
	 * Public wrapper for send_error.
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @param mixed  $data    Data.
	 * @return void
	 */
	public function public_send_error( string $message, string $code = '', $data = null ): void {
		$this->send_error( $message, $code, $data );
	}

	/**
	 * Public wrapper for get_post_param.
	 *
	 * @param string $key               Key.
	 * @param mixed  $default_value     Default.
	 * @param string $sanitize_callback Callback.
	 * @return mixed Value.
	 */
	public function public_get_post_param( string $key, $default_value = '', string $sanitize_callback = 'sanitize_text_field' ) {
		return $this->get_post_param( $key, $default_value, $sanitize_callback );
	}

	/**
	 * Public wrapper for get_post_int.
	 *
	 * @param string $key           Key.
	 * @param int    $default_value Default.
	 * @return int Value.
	 */
	public function public_get_post_int( string $key, int $default_value = 0 ): int {
		return $this->get_post_int( $key, $default_value );
	}

	/**
	 * Public wrapper for get_post_bool.
	 *
	 * @param string $key           Key.
	 * @param bool   $default_value Default.
	 * @return bool Value.
	 */
	public function public_get_post_bool( string $key, bool $default_value = false ): bool {
		return $this->get_post_bool( $key, $default_value );
	}

	/**
	 * Public wrapper for validate_required_params.
	 *
	 * @param array $required_params Required parameters.
	 * @return bool Result.
	 */
	public function public_validate_required_params( array $required_params ): bool {
		return $this->validate_required_params( $required_params );
	}

	/**
	 * Public wrapper for handle_api_response.
	 *
	 * @param array $api_response API response.
	 * @return void
	 */
	public function public_handle_api_response( array $api_response ): void {
		$this->handle_api_response( $api_response );
	}
}

/**
 * Test case for the AJAX Handler Base class.
 *
 * Tests CSRF protection, authorization checks, input sanitization,
 * and response formatting.
 */
class AjaxHandlerBaseTest extends TestCase {

	/**
	 * Handler instance.
	 *
	 * @var TestableAjaxHandler
	 */
	private TestableAjaxHandler $handler;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->handler = new TestableAjaxHandler();
	}

	/**
	 * Test verify_ajax_request checks nonce.
	 *
	 * @return void
	 */
	public function test_verify_ajax_request_checks_nonce(): void {
		$nonce_checked = false;

		Functions\when( 'check_ajax_referer' )->alias(
			function ( $action, $nonce_key ) use ( &$nonce_checked ) {
				$nonce_checked = true;
				$this->assertEquals( 'test_action', $action, 'Nonce action should match' );
				$this->assertEquals( 'nonce', $nonce_key, 'Nonce key should be "nonce"' );
				return true;
			}
		);

		Functions\when( 'current_user_can' )->justReturn( true );

		$result = $this->handler->public_verify_ajax_request( 'test_action' );

		$this->assertTrue( $nonce_checked, 'Nonce should be checked' );
		$this->assertTrue( $result, 'Verification should pass' );
	}

	/**
	 * Test verify_ajax_request checks user capability.
	 *
	 * @return void
	 */
	public function test_verify_ajax_request_checks_capability(): void {
		Functions\when( 'check_ajax_referer' )->justReturn( true );

		$capability_checked = false;

		Functions\when( 'current_user_can' )->alias(
			function ( $cap ) use ( &$capability_checked ) {
				$capability_checked = true;
				$this->assertEquals( 'manage_options', $cap, 'Should check manage_options by default' );
				return true;
			}
		);

		$result = $this->handler->public_verify_ajax_request( 'test_action' );

		$this->assertTrue( $capability_checked, 'Capability should be checked' );
		$this->assertTrue( $result, 'Verification should pass' );
	}

	/**
	 * Test verify_ajax_request checks custom capability.
	 *
	 * @return void
	 */
	public function test_verify_ajax_request_checks_custom_capability(): void {
		Functions\when( 'check_ajax_referer' )->justReturn( true );

		$checked_capability = '';

		Functions\when( 'current_user_can' )->alias(
			function ( $cap ) use ( &$checked_capability ) {
				$checked_capability = $cap;
				return true;
			}
		);

		$this->handler->public_verify_ajax_request( 'test_action', 'edit_posts' );

		$this->assertEquals( 'edit_posts', $checked_capability, 'Should check custom capability' );
	}

	/**
	 * Test verify_ajax_request rejects unauthorized users.
	 *
	 * @return void
	 */
	public function test_verify_ajax_request_rejects_unauthorized(): void {
		Functions\when( 'check_ajax_referer' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );

		// Set up AJAX mock to capture error.
		$this->setup_ajax_mocks( true, false );

		$this->expectException( JsonErrorException::class );

		$this->handler->public_verify_ajax_request( 'test_action' );
	}

	/**
	 * Test get_post_param sanitizes input with default sanitize_text_field.
	 *
	 * @return void
	 */
	public function test_get_post_param_sanitizes_input(): void {
		$_POST['test_key'] = '  <script>alert("xss")</script>test  ';

		$result = $this->handler->public_get_post_param( 'test_key' );

		// sanitize_text_field strips tags and trims.
		$this->assertEquals( 'alert("xss")test', $result, 'Should sanitize HTML tags' );
	}

	/**
	 * Test get_post_param applies custom sanitizer.
	 *
	 * @return void
	 */
	public function test_get_post_param_applies_custom_sanitizer(): void {
		$_POST['email'] = '  JOHN@EXAMPLE.COM  ';

		$result = $this->handler->public_get_post_param( 'email', '', 'sanitize_email' );

		$this->assertEquals( 'JOHN@EXAMPLE.COM', $result, 'Should apply custom sanitizer' );
	}

	/**
	 * Test get_post_param returns default for missing key.
	 *
	 * @return void
	 */
	public function test_get_post_param_returns_default_for_missing(): void {
		unset( $_POST['nonexistent'] );

		$result = $this->handler->public_get_post_param( 'nonexistent', 'default_value' );

		$this->assertEquals( 'default_value', $result, 'Should return default for missing key' );
	}

	/**
	 * Test get_post_param handles arrays.
	 *
	 * @return void
	 */
	public function test_get_post_param_handles_arrays(): void {
		$_POST['items'] = array( '  item1  ', '<b>item2</b>', '  item3  ' );

		$result = $this->handler->public_get_post_param( 'items' );

		$this->assertIsArray( $result, 'Should return array' );
		$this->assertEquals( 'item1', $result[0], 'First item should be sanitized' );
		$this->assertEquals( 'item2', $result[1], 'Second item should have tags stripped' );
		$this->assertEquals( 'item3', $result[2], 'Third item should be sanitized' );
	}

	/**
	 * Test get_post_int returns integer.
	 *
	 * @return void
	 */
	public function test_get_post_int_returns_integer(): void {
		$_POST['count'] = '42';

		$result = $this->handler->public_get_post_int( 'count' );

		$this->assertSame( 42, $result, 'Should return integer' );
	}

	/**
	 * Test get_post_int handles non-numeric values.
	 *
	 * @return void
	 */
	public function test_get_post_int_handles_non_numeric(): void {
		$_POST['count'] = 'not-a-number';

		$result = $this->handler->public_get_post_int( 'count', 10 );

		$this->assertSame( 0, $result, 'Non-numeric should convert to 0' );
	}

	/**
	 * Test get_post_int returns default for missing key.
	 *
	 * @return void
	 */
	public function test_get_post_int_returns_default(): void {
		unset( $_POST['missing'] );

		$result = $this->handler->public_get_post_int( 'missing', 99 );

		$this->assertSame( 99, $result, 'Should return default for missing key' );
	}

	/**
	 * Test get_post_bool returns true for truthy values.
	 *
	 * @return void
	 */
	public function test_get_post_bool_returns_true_for_truthy(): void {
		// Test various truthy values.
		$truthy_values = array( 1, '1', 'true', true, 'on' );

		foreach ( $truthy_values as $value ) {
			$_POST['enabled'] = $value;
			$result = $this->handler->public_get_post_bool( 'enabled' );
			$this->assertTrue( $result, "Should return true for: " . var_export( $value, true ) );
		}
	}

	/**
	 * Test get_post_bool returns false for falsy values.
	 *
	 * @return void
	 */
	public function test_get_post_bool_returns_false_for_falsy(): void {
		$_POST['enabled'] = '0';

		$result = $this->handler->public_get_post_bool( 'enabled' );

		$this->assertFalse( $result, 'Should return false for "0"' );
	}

	/**
	 * Test get_post_bool returns default for missing key.
	 *
	 * @return void
	 */
	public function test_get_post_bool_returns_default(): void {
		unset( $_POST['missing'] );

		$result = $this->handler->public_get_post_bool( 'missing', true );

		$this->assertTrue( $result, 'Should return default for missing key' );
	}

	/**
	 * Test send_error returns proper JSON structure.
	 *
	 * @return void
	 */
	public function test_send_error_returns_json(): void {
		$this->setup_ajax_mocks();

		try {
			$this->handler->public_send_error( 'Something went wrong', 'error_code_123' );
			$this->fail( 'Expected JsonErrorException' );
		} catch ( JsonErrorException $e ) {
			$response = $e->data;
			$this->assertIsArray( $response, 'Response should be array' );
			$this->assertEquals( 'Something went wrong', $response['message'] );
			$this->assertEquals( 'error_code_123', $response['error_code'] );
		}
	}

	/**
	 * Test send_error includes additional data.
	 *
	 * @return void
	 */
	public function test_send_error_includes_additional_data(): void {
		$this->setup_ajax_mocks();

		try {
			$this->handler->public_send_error(
				'Error occurred',
				'err_001',
				array( 'field' => 'username', 'reason' => 'too short' )
			);
			$this->fail( 'Expected JsonErrorException' );
		} catch ( JsonErrorException $e ) {
			$response = $e->data;
			$this->assertEquals( 'username', $response['field'] );
			$this->assertEquals( 'too short', $response['reason'] );
		}
	}

	/**
	 * Test send_success returns proper JSON structure.
	 *
	 * @return void
	 */
	public function test_send_success_returns_json(): void {
		$this->setup_ajax_mocks();

		try {
			$this->handler->public_send_success(
				array( 'id' => 123, 'name' => 'test' ),
				'Operation successful'
			);
			$this->fail( 'Expected JsonSuccessException' );
		} catch ( JsonSuccessException $e ) {
			$response = $e->data;
			$this->assertIsArray( $response, 'Response should be array' );
			$this->assertEquals( 'Operation successful', $response['message'] );
			$this->assertEquals( 123, $response['id'] );
			$this->assertEquals( 'test', $response['name'] );
		}
	}

	/**
	 * Test send_success handles null data.
	 *
	 * @return void
	 */
	public function test_send_success_handles_null_data(): void {
		$this->setup_ajax_mocks();

		try {
			$this->handler->public_send_success( null, 'Done' );
			$this->fail( 'Expected JsonSuccessException' );
		} catch ( JsonSuccessException $e ) {
			$response = $e->data;
			$this->assertEquals( 'Done', $response['message'] );
		}
	}

	/**
	 * Test send_success handles scalar data.
	 *
	 * @return void
	 */
	public function test_send_success_handles_scalar_data(): void {
		$this->setup_ajax_mocks();

		try {
			$this->handler->public_send_success( 'simple string' );
			$this->fail( 'Expected JsonSuccessException' );
		} catch ( JsonSuccessException $e ) {
			$response = $e->data;
			$this->assertEquals( 'simple string', $response['data'] );
		}
	}

	/**
	 * Test validate_required_params with all present.
	 *
	 * @return void
	 */
	public function test_validate_required_params_succeeds_when_all_present(): void {
		$_POST['param1'] = 'value1';
		$_POST['param2'] = 'value2';

		$result = $this->handler->public_validate_required_params( array( 'param1', 'param2' ) );

		$this->assertTrue( $result, 'Should succeed when all params present' );
	}

	/**
	 * Test validate_required_params with missing params.
	 *
	 * @return void
	 */
	public function test_validate_required_params_fails_when_missing(): void {
		$_POST['param1'] = 'value1';
		unset( $_POST['param2'] );

		$this->setup_ajax_mocks();

		try {
			$this->handler->public_validate_required_params( array( 'param1', 'param2' ) );
			$this->fail( 'Expected JsonErrorException for missing params' );
		} catch ( JsonErrorException $e ) {
			$this->assertStringContainsString( 'param2', $e->data['message'] );
		}
	}

	/**
	 * Test validate_required_params with empty value.
	 *
	 * @return void
	 */
	public function test_validate_required_params_fails_for_empty_value(): void {
		$_POST['param1'] = 'value1';
		$_POST['param2'] = '';

		$this->setup_ajax_mocks();

		try {
			$this->handler->public_validate_required_params( array( 'param1', 'param2' ) );
			$this->fail( 'Expected JsonErrorException for empty value' );
		} catch ( JsonErrorException $e ) {
			$this->assertStringContainsString( 'param2', $e->data['message'] );
		}
	}

	/**
	 * Test handle_api_response with success response.
	 *
	 * @return void
	 */
	public function test_handle_api_response_sends_success(): void {
		$this->setup_ajax_mocks();

		try {
			$this->handler->public_handle_api_response(
				array(
					'success' => true,
					'message' => 'Deployment started',
					'data'    => array( 'deployment_id' => 42 ),
				)
			);
			$this->fail( 'Expected JsonSuccessException' );
		} catch ( JsonSuccessException $e ) {
			$response = $e->data;
			$this->assertEquals( 'Deployment started', $response['message'] );
			$this->assertEquals( 42, $response['deployment_id'] );
		}
	}

	/**
	 * Test handle_api_response with error response.
	 *
	 * @return void
	 */
	public function test_handle_api_response_sends_error(): void {
		$this->setup_ajax_mocks();

		try {
			$this->handler->public_handle_api_response(
				array(
					'success'    => false,
					'message'    => 'Build failed',
					'error_code' => 'BUILD_ERROR',
				)
			);
			$this->fail( 'Expected JsonErrorException' );
		} catch ( JsonErrorException $e ) {
			$response = $e->data;
			$this->assertEquals( 'Build failed', $response['message'] );
			$this->assertEquals( 'BUILD_ERROR', $response['error_code'] );
		}
	}

	/**
	 * Test handle_api_response with minimal error.
	 *
	 * @return void
	 */
	public function test_handle_api_response_handles_minimal_error(): void {
		$this->setup_ajax_mocks();

		try {
			$this->handler->public_handle_api_response(
				array( 'success' => false )
			);
			$this->fail( 'Expected JsonErrorException' );
		} catch ( JsonErrorException $e ) {
			$response = $e->data;
			$this->assertEquals( 'Operation failed', $response['message'] );
		}
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}
}
