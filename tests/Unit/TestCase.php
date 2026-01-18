<?php
/**
 * Base test case for Deploy Forge unit tests.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case class that sets up Brain Monkey.
 */
abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Set up Brain Monkey before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->setup_common_mocks();
	}

	/**
	 * Set up common WordPress function mocks.
	 *
	 * @return void
	 */
	protected function setup_common_mocks(): void {
		// Translation and escaping functions.
		Monkey\Functions\stubs(
			array(
				'__'                  => static function ( $text ) {
					return $text;
				},
				'esc_html__'          => static function ( $text ) {
					return $text;
				},
				'esc_attr__'          => static function ( $text ) {
					return $text;
				},
				'esc_html'            => static function ( $text ) {
					return $text;
				},
				'esc_attr'            => static function ( $text ) {
					return $text;
				},
				'esc_url'             => static function ( $url ) {
					return $url;
				},
				'wp_json_encode'      => static function ( $data, $flags = 0 ) {
					return json_encode( $data, $flags );
				},
				'sanitize_text_field' => static function ( $str ) {
					return trim( strip_tags( $str ) );
				},
				'sanitize_file_name'  => static function ( $filename ) {
					return preg_replace( '/[^a-zA-Z0-9._-]/', '', $filename );
				},
				'sanitize_email'      => static function ( $email ) {
					return filter_var( $email, FILTER_SANITIZE_EMAIL );
				},
				'absint'              => static function ( $value ) {
					return abs( intval( $value ) );
				},
				'wp_parse_args'       => static function ( $args, $defaults ) {
					return array_merge( $defaults, $args );
				},
				'wp_unslash'          => static function ( $value ) {
					return is_array( $value ) ? array_map( 'stripslashes', $value ) : stripslashes( $value );
				},
				'current_time'        => static function ( $type = 'mysql' ) {
					if ( 'mysql' === $type ) {
						return gmdate( 'Y-m-d H:i:s' );
					}
					return time();
				},
				'home_url'            => static function ( $path = '' ) {
					return 'https://example.com' . $path;
				},
				'admin_url'           => static function ( $path = '' ) {
					return 'https://example.com/wp-admin/' . $path;
				},
				'rest_url'            => static function ( $path = '' ) {
					return 'https://example.com/wp-json/' . $path;
				},
				'plugin_basename'     => static function ( $file ) {
					return basename( dirname( $file ) ) . '/' . basename( $file );
				},
				'is_wp_error'         => static function ( $thing ) {
					return $thing instanceof \WP_Error;
				},
			)
		);
	}

	/**
	 * Set up HTTP-related function mocks.
	 *
	 * @return void
	 */
	protected function setup_http_mocks(): void {
		Functions\stubs(
			array(
				'is_wp_error' => static function ( $thing ) {
					return $thing instanceof \WP_Error;
				},
				'wp_remote_retrieve_response_code' => static function ( $response ) {
					if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
						return $response['response']['code'];
					}
					return 0;
				},
				'wp_remote_retrieve_body' => static function ( $response ) {
					if ( is_array( $response ) && isset( $response['body'] ) ) {
						return $response['body'];
					}
					return '';
				},
				'wp_remote_retrieve_headers' => static function ( $response ) {
					if ( is_array( $response ) && isset( $response['headers'] ) ) {
						return $response['headers'];
					}
					return array();
				},
			)
		);
	}

	/**
	 * Set up transient function mocks with in-memory storage.
	 *
	 * @return array Reference to the transient storage.
	 */
	protected function setup_transient_mocks(): array {
		$transients = array();

		Functions\when( 'get_transient' )->alias(
			function ( $key ) use ( &$transients ) {
				return $transients[ $key ] ?? false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $expiration = 0 ) use ( &$transients ) {
				$transients[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_transient' )->alias(
			function ( $key ) use ( &$transients ) {
				unset( $transients[ $key ] );
				return true;
			}
		);

		return $transients;
	}

	/**
	 * Set up cron function mocks.
	 *
	 * @return void
	 */
	protected function setup_cron_mocks(): void {
		Functions\stubs(
			array(
				'wp_schedule_single_event' => '__return_true',
				'wp_clear_scheduled_hook'  => '__return_true',
			)
		);
	}

	/**
	 * Set up AJAX function mocks.
	 *
	 * @param bool $nonce_valid Whether nonce validation should pass.
	 * @param bool $user_can    Whether capability check should pass.
	 * @return void
	 */
	protected function setup_ajax_mocks( bool $nonce_valid = true, bool $user_can = true ): void {
		Functions\when( 'check_ajax_referer' )->alias(
			function ( $action, $nonce_key = false ) use ( $nonce_valid ) {
				if ( ! $nonce_valid ) {
					throw new \Exception( 'Nonce verification failed' );
				}
				return true;
			}
		);

		Functions\when( 'current_user_can' )->alias(
			function ( $capability ) use ( $user_can ) {
				return $user_can;
			}
		);

		Functions\stubs(
			array(
				'wp_send_json_success' => static function ( $data = null ) {
					throw new JsonSuccessException( $data );
				},
				'wp_send_json_error'   => static function ( $data = null ) {
					throw new JsonErrorException( $data );
				},
			)
		);
	}

	/**
	 * Set up filesystem function mocks.
	 *
	 * @return void
	 */
	protected function setup_filesystem_mocks(): void {
		Functions\stubs(
			array(
				'wp_mkdir_p'     => '__return_true',
				'wp_delete_file' => static function ( $file ) {
					return @unlink( $file );
				},
			)
		);
	}

	/**
	 * Set up option function mocks with in-memory storage.
	 *
	 * @param array $initial_options Initial options to set.
	 * @return array Reference to the options storage.
	 */
	protected function setup_option_mocks( array $initial_options = array() ): array {
		$options = $initial_options;

		Functions\when( 'get_option' )->alias(
			function ( $key, $default = false ) use ( &$options ) {
				return $options[ $key ] ?? $default;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( $key, $value ) use ( &$options ) {
				$options[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_option' )->alias(
			function ( $key ) use ( &$options ) {
				unset( $options[ $key ] );
				return true;
			}
		);

		return $options;
	}

	/**
	 * Create a mock WP_Error object.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 * @return \WP_Error Mock WP_Error object.
	 */
	protected function create_wp_error( string $code = 'error', string $message = 'An error occurred', $data = null ): \WP_Error {
		return new \WP_Error( $code, $message, $data );
	}

	/**
	 * Create a mock HTTP response array.
	 *
	 * @param int    $status_code Response status code.
	 * @param mixed  $body        Response body.
	 * @param array  $headers     Response headers.
	 * @return array Mock HTTP response.
	 */
	protected function create_http_response( int $status_code = 200, $body = '', array $headers = array() ): array {
		if ( is_array( $body ) || is_object( $body ) ) {
			$body = json_encode( $body );
		}

		return array(
			'response' => array(
				'code'    => $status_code,
				'message' => $this->get_status_message( $status_code ),
			),
			'body'     => $body,
			'headers'  => $headers,
		);
	}

	/**
	 * Get HTTP status message for a status code.
	 *
	 * @param int $code Status code.
	 * @return string Status message.
	 */
	private function get_status_message( int $code ): string {
		$messages = array(
			200 => 'OK',
			201 => 'Created',
			204 => 'No Content',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			500 => 'Internal Server Error',
		);

		return $messages[ $code ] ?? 'Unknown';
	}

	/**
	 * Set up wp_remote functions mock.
	 *
	 * @param array|\WP_Error $response Response to return.
	 * @return void
	 */
	protected function mock_wp_remote_response( $response ): void {
		$this->setup_http_mocks();

		Functions\when( 'wp_remote_request' )->justReturn( $response );
		Functions\when( 'wp_remote_get' )->justReturn( $response );
		Functions\when( 'wp_remote_post' )->justReturn( $response );

		if ( is_array( $response ) ) {
			Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( $response['response']['code'] ?? 200 );
			Functions\when( 'wp_remote_retrieve_body' )->justReturn( $response['body'] ?? '' );
			Functions\when( 'wp_remote_retrieve_headers' )->justReturn( $response['headers'] ?? array() );
		} else {
			Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 0 );
			Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );
			Functions\when( 'wp_remote_retrieve_headers' )->justReturn( array() );
		}
	}

	/**
	 * Create a mock Settings object.
	 *
	 * @param array $settings Settings values to return.
	 * @return Mockery\MockInterface Mock Settings object.
	 */
	protected function create_mock_settings( array $settings = array() ): Mockery\MockInterface {
		$mock = Mockery::mock( 'Deploy_Forge_Settings' );

		$defaults = array(
			'api_key'                 => 'test-api-key',
			'webhook_secret'          => 'test-webhook-secret',
			'site_id'                 => 'site-123',
			'github_branch'           => 'main',
			'github_repo_owner'       => 'test-owner',
			'github_repo_name'        => 'test-repo',
			'github_workflow_name'    => 'build.yml',
			'debug_mode'              => false,
			'require_manual_approval' => false,
			'backend_url'             => 'https://deploy-forge.example.com',
		);

		$settings = array_merge( $defaults, $settings );

		$mock->shouldReceive( 'get' )->andReturnUsing(
			function ( $key, $default = null ) use ( $settings ) {
				return $settings[ $key ] ?? $default;
			}
		);

		$mock->shouldReceive( 'get_all' )->andReturn( $settings );
		$mock->shouldReceive( 'get_api_key' )->andReturn( $settings['api_key'] );
		$mock->shouldReceive( 'get_webhook_secret' )->andReturn( $settings['webhook_secret'] );
		$mock->shouldReceive( 'get_site_id' )->andReturn( $settings['site_id'] );
		$mock->shouldReceive( 'get_backend_url' )->andReturn( $settings['backend_url'] );
		$mock->shouldReceive( 'get_repo_full_name' )->andReturn(
			$settings['github_repo_owner'] . '/' . $settings['github_repo_name']
		);
		$mock->shouldReceive( 'is_connected' )->andReturn( ! empty( $settings['api_key'] ) );
		$mock->shouldReceive( 'get_connection_data' )->andReturn(
			array(
				'repo_owner'        => $settings['github_repo_owner'],
				'repo_name'         => $settings['github_repo_name'],
				'repo_branch'       => $settings['github_branch'],
				'deployment_method' => 'github_actions',
				'workflow_path'     => '.github/workflows/build.yml',
			)
		);
		$mock->shouldReceive( 'set_connection_data' )->byDefault()->andReturn( true );

		return $mock;
	}

	/**
	 * Create a mock Logger object.
	 *
	 * @param bool $enabled Whether debug mode is enabled.
	 * @return Mockery\MockInterface Mock Logger object.
	 */
	protected function create_mock_logger( bool $enabled = false ): Mockery\MockInterface {
		$mock = Mockery::mock( 'Deploy_Forge_Debug_Logger' );

		$mock->shouldReceive( 'is_enabled' )->andReturn( $enabled );
		$mock->shouldReceive( 'log' )->andReturnNull();
		$mock->shouldReceive( 'error' )->andReturnNull();
		$mock->shouldReceive( 'log_api_request' )->andReturnNull();
		$mock->shouldReceive( 'log_api_response' )->andReturnNull();
		$mock->shouldReceive( 'log_deployment_step' )->andReturnNull();

		return $mock;
	}

	/**
	 * Create a mock Database object.
	 *
	 * @return Mockery\MockInterface Mock Database object.
	 */
	protected function create_mock_database(): Mockery\MockInterface {
		$mock = Mockery::mock( 'Deploy_Forge_Database' );

		// Use byDefault() so tests can override these expectations.
		$mock->shouldReceive( 'insert_deployment' )->byDefault()->andReturn( 1 );
		$mock->shouldReceive( 'update_deployment' )->byDefault()->andReturn( true );
		$mock->shouldReceive( 'get_deployment' )->byDefault()->andReturn( null );
		$mock->shouldReceive( 'get_building_deployment' )->byDefault()->andReturn( null );
		$mock->shouldReceive( 'get_pending_deployments' )->byDefault()->andReturn( array() );
		$mock->shouldReceive( 'get_queued_deployments' )->byDefault()->andReturn( array() );

		return $mock;
	}

	/**
	 * Create a mock GitHub API object.
	 *
	 * @return Mockery\MockInterface Mock GitHub API object.
	 */
	protected function create_mock_github_api(): Mockery\MockInterface {
		$mock = Mockery::mock( 'Deploy_Forge_GitHub_API' );

		// Use byDefault() so tests can override these expectations.
		$mock->shouldReceive( 'trigger_workflow' )->byDefault()->andReturn( array( 'success' => true ) );
		$mock->shouldReceive( 'get_workflow_run_status' )->byDefault()->andReturn( array( 'success' => true ) );
		$mock->shouldReceive( 'download_artifact' )->byDefault()->andReturn( true );
		$mock->shouldReceive( 'report_deployment_status' )->byDefault()->andReturn( array( 'success' => true ) );
		$mock->shouldReceive( 'cancel_workflow_run' )->byDefault()->andReturn( array( 'success' => true ) );

		return $mock;
	}

	/**
	 * Create a mock Deployment Manager object.
	 *
	 * @return Mockery\MockInterface Mock Deployment Manager object.
	 */
	protected function create_mock_deployment_manager(): Mockery\MockInterface {
		$mock = Mockery::mock( 'Deploy_Forge_Deployment_Manager' );

		// Use byDefault() so tests can override these expectations.
		$mock->shouldReceive( 'start_deployment' )->byDefault()->andReturn( 1 );
		$mock->shouldReceive( 'trigger_github_build' )->byDefault()->andReturn( true );
		$mock->shouldReceive( 'process_successful_build' )->byDefault()->andReturn( true );
		$mock->shouldReceive( 'process_clone_deployment' )->byDefault()->andReturn( true );

		return $mock;
	}

	/**
	 * Tear down Brain Monkey after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}

/**
 * Exception thrown when wp_send_json_success is called.
 */
class JsonSuccessException extends \Exception {

	/**
	 * The data passed to wp_send_json_success.
	 *
	 * @var mixed
	 */
	public $data;

	/**
	 * Constructor.
	 *
	 * @param mixed $data Data passed to wp_send_json_success.
	 */
	public function __construct( $data = null ) {
		$this->data = $data;
		parent::__construct( 'JSON Success' );
	}
}

/**
 * Exception thrown when wp_send_json_error is called.
 */
class JsonErrorException extends \Exception {

	/**
	 * The data passed to wp_send_json_error.
	 *
	 * @var mixed
	 */
	public $data;

	/**
	 * Constructor.
	 *
	 * @param mixed $data Data passed to wp_send_json_error.
	 */
	public function __construct( $data = null ) {
		$this->data = $data;
		parent::__construct( 'JSON Error' );
	}
}
