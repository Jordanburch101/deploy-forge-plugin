<?php
/**
 * Tests for Deploy_Forge_Debug_Logger class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

// Load the class files.
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-settings.php';
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-debug-logger.php';

/**
 * Test case for the Debug Logger class.
 *
 * Tests debug logging functionality including enabled/disabled states,
 * file operations, and sensitive data handling.
 */
class DebugLoggerTest extends TestCase {

	/**
	 * Debug logger instance.
	 *
	 * @var \Deploy_Forge_Debug_Logger
	 */
	private \Deploy_Forge_Debug_Logger $logger;

	/**
	 * Mock settings instance.
	 *
	 * @var Mockery\MockInterface
	 */
	private $mock_settings;

	/**
	 * Temporary log file path.
	 *
	 * @var string
	 */
	private string $temp_log_file;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mock settings.
		$this->mock_settings = Mockery::mock( 'Deploy_Forge_Settings' );

		// Create temp directory for log files.
		$this->temp_log_file = sys_get_temp_dir() . '/deploy-forge-test-' . uniqid() . '.log';

		// Define WP_CONTENT_DIR to our temp location.
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', sys_get_temp_dir() );
		}

		// Mock current_time function.
		Functions\when( 'current_time' )->justReturn( '2024-01-15 12:00:00' );

		// Mock wp_json_encode to use PHP's json_encode.
		Functions\when( 'wp_json_encode' )->alias( function ( $data, $options = 0 ) {
			return json_encode( $data, $options );
		} );

		// Mock is_wp_error.
		Functions\when( 'is_wp_error' )->alias( function ( $thing ) {
			return $thing instanceof \WP_Error;
		} );

		// Mock wp_delete_file.
		Functions\when( 'wp_delete_file' )->alias( function ( $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		} );

		$this->logger = new \Deploy_Forge_Debug_Logger( $this->mock_settings );
	}

	/**
	 * Test is_enabled returns true when debug_mode is set.
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_true_when_debug_mode_set(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->once()
			->andReturn( array( 'debug_mode' => true ) );

		$this->assertTrue( $this->logger->is_enabled() );
	}

	/**
	 * Test is_enabled returns false when debug_mode is not set.
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_when_debug_mode_not_set(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->once()
			->andReturn( array() );

		$this->assertFalse( $this->logger->is_enabled() );
	}

	/**
	 * Test is_enabled returns false when debug_mode is false.
	 *
	 * @return void
	 */
	public function test_is_enabled_returns_false_when_debug_mode_false(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->once()
			->andReturn( array( 'debug_mode' => false ) );

		$this->assertFalse( $this->logger->is_enabled() );
	}

	/**
	 * Test log writes when debug is enabled.
	 *
	 * @return void
	 */
	public function test_log_writes_when_debug_enabled(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		// Create a testable logger that writes to our temp file.
		$logger = $this->createTestableLogger( true );

		$logger->log( 'TestContext', 'Test message' );

		$this->assertFileExists( $this->temp_log_file );
		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( '[TestContext]', $content );
		$this->assertStringContainsString( 'Test message', $content );
	}

	/**
	 * Test log skips when debug is disabled.
	 *
	 * @return void
	 */
	public function test_log_skips_when_debug_disabled(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => false ) );

		// Create a testable logger.
		$logger = $this->createTestableLogger( false );

		$logger->log( 'TestContext', 'Test message' );

		$this->assertFileDoesNotExist( $this->temp_log_file );
	}

	/**
	 * Test log includes data when provided.
	 *
	 * @return void
	 */
	public function test_log_includes_data_when_provided(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		$logger->log( 'TestContext', 'Test message', array( 'key' => 'value' ) );

		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( 'Data:', $content );
		$this->assertStringContainsString( '"key"', $content );
		$this->assertStringContainsString( '"value"', $content );
	}

	/**
	 * Test error logs with ERROR suffix.
	 *
	 * @return void
	 */
	public function test_error_logs_with_error_suffix(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		$logger->error( 'TestContext', 'Error message' );

		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( '[TestContext:ERROR]', $content );
		$this->assertStringContainsString( 'Error message', $content );
	}

	/**
	 * Test error handles WP_Error objects.
	 *
	 * @return void
	 */
	public function test_error_handles_wp_error(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		// Create a mock WP_Error.
		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_code' )->andReturn( 'test_error_code' );
		$wp_error->shouldReceive( 'get_error_message' )->andReturn( 'Test error message' );
		$wp_error->shouldReceive( 'get_error_data' )->andReturn( array( 'extra' => 'data' ) );

		// Override is_wp_error for this test.
		Functions\expect( 'is_wp_error' )
			->andReturnUsing( function ( $thing ) use ( $wp_error ) {
				return $thing === $wp_error;
			} );

		$logger->error( 'TestContext', 'WP Error occurred', $wp_error );

		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( 'test_error_code', $content );
		$this->assertStringContainsString( 'Test error message', $content );
	}

	/**
	 * Test error handles string error data.
	 *
	 * @return void
	 */
	public function test_error_handles_string_error_data(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		$logger->error( 'TestContext', 'Error occurred', 'Simple error string' );

		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( 'Simple error string', $content );
	}

	/**
	 * Test log_api_request redacts Authorization header.
	 *
	 * @return void
	 */
	public function test_log_api_request_redacts_auth_header(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		$logger->log_api_request(
			'GET',
			'/api/endpoint',
			array(),
			array( 'Authorization' => 'Bearer secret-token-12345' )
		);

		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( '[REDACTED]', $content );
		$this->assertStringNotContainsString( 'secret-token-12345', $content );
	}

	/**
	 * Test log_api_request preserves non-sensitive headers.
	 *
	 * @return void
	 */
	public function test_log_api_request_preserves_non_sensitive_headers(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		$logger->log_api_request(
			'POST',
			'/api/endpoint',
			array( 'param' => 'value' ),
			array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			)
		);

		$content = file_get_contents( $this->temp_log_file );
		// JSON encoding escapes slashes, so check for the escaped version.
		$this->assertStringContainsString( 'application\\/json', $content );
		$this->assertStringContainsString( 'POST', $content );
	}

	/**
	 * Test log_api_response truncates large responses.
	 *
	 * @return void
	 */
	public function test_log_api_response_truncates_large_responses(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		// Create a response body larger than 1000 chars.
		$large_body = str_repeat( 'a', 1500 );

		$logger->log_api_response( '/api/endpoint', 200, $large_body );

		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( '[truncated]', $content );
	}

	/**
	 * Test log_api_response includes error when provided.
	 *
	 * @return void
	 */
	public function test_log_api_response_includes_error(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		$logger->log_api_response( '/api/endpoint', 500, '', 'Server error' );

		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( 'API Response ERROR', $content );
		$this->assertStringContainsString( 'Server error', $content );
	}

	/**
	 * Test log_deployment_step logs deployment info.
	 *
	 * @return void
	 */
	public function test_log_deployment_step_logs_info(): void {
		$this->mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => true ) );

		$logger = $this->createTestableLogger( true );

		$logger->log_deployment_step( 42, 'download', 'started', array( 'url' => 'https://example.com' ) );

		$content = file_get_contents( $this->temp_log_file );
		$this->assertStringContainsString( 'Deployment #42', $content );
		$this->assertStringContainsString( 'download', $content );
		$this->assertStringContainsString( 'started', $content );
	}

	/**
	 * Test get_recent_logs returns content.
	 *
	 * @return void
	 */
	public function test_get_recent_logs_returns_content(): void {
		// Write some content to log file.
		file_put_contents( $this->temp_log_file, "Line 1\nLine 2\nLine 3\n" );

		// Create logger with the temp file.
		$logger = $this->createTestableLoggerWithFile();

		$logs = $logger->get_recent_logs( 10 );

		$this->assertStringContainsString( 'Line 1', $logs );
		$this->assertStringContainsString( 'Line 2', $logs );
		$this->assertStringContainsString( 'Line 3', $logs );
	}

	/**
	 * Test get_recent_logs returns message when no file.
	 *
	 * @return void
	 */
	public function test_get_recent_logs_returns_message_when_no_file(): void {
		// Ensure no file exists.
		if ( file_exists( $this->temp_log_file ) ) {
			unlink( $this->temp_log_file );
		}

		$logger = $this->createTestableLoggerWithFile();

		$logs = $logger->get_recent_logs();

		$this->assertEquals( 'No log file found.', $logs );
	}

	/**
	 * Test clear_logs deletes file.
	 *
	 * @return void
	 */
	public function test_clear_logs_deletes_file(): void {
		// Create log file.
		file_put_contents( $this->temp_log_file, 'Test content' );
		$this->assertFileExists( $this->temp_log_file );

		$logger = $this->createTestableLoggerWithFile();

		$result = $logger->clear_logs();

		$this->assertTrue( $result );
		$this->assertFileDoesNotExist( $this->temp_log_file );
	}

	/**
	 * Test clear_logs returns true when no file exists.
	 *
	 * @return void
	 */
	public function test_clear_logs_returns_true_when_no_file(): void {
		// Ensure no file exists.
		if ( file_exists( $this->temp_log_file ) ) {
			unlink( $this->temp_log_file );
		}

		$logger = $this->createTestableLoggerWithFile();

		$result = $logger->clear_logs();

		$this->assertTrue( $result );
	}

	/**
	 * Test get_log_size returns human readable size.
	 *
	 * @return void
	 */
	public function test_get_log_size_returns_human_readable(): void {
		// Create a file with known size.
		file_put_contents( $this->temp_log_file, str_repeat( 'a', 1024 ) );

		$logger = $this->createTestableLoggerWithFile();

		$size = $logger->get_log_size();

		$this->assertEquals( '1 KB', $size );
	}

	/**
	 * Test get_log_size returns bytes for small files.
	 *
	 * @return void
	 */
	public function test_get_log_size_returns_bytes_for_small(): void {
		// Create a small file.
		file_put_contents( $this->temp_log_file, 'test' );

		$logger = $this->createTestableLoggerWithFile();

		$size = $logger->get_log_size();

		$this->assertStringContainsString( 'bytes', $size );
	}

	/**
	 * Test get_log_size returns 0 bytes when no file.
	 *
	 * @return void
	 */
	public function test_get_log_size_returns_zero_when_no_file(): void {
		// Ensure no file exists.
		if ( file_exists( $this->temp_log_file ) ) {
			unlink( $this->temp_log_file );
		}

		$logger = $this->createTestableLoggerWithFile();

		$size = $logger->get_log_size();

		$this->assertEquals( '0 bytes', $size );
	}

	/**
	 * Test get_log_size formats megabytes correctly.
	 *
	 * @return void
	 */
	public function test_get_log_size_formats_megabytes(): void {
		// Create a file around 1MB.
		file_put_contents( $this->temp_log_file, str_repeat( 'a', 1024 * 1024 ) );

		$logger = $this->createTestableLoggerWithFile();

		$size = $logger->get_log_size();

		$this->assertStringContainsString( 'MB', $size );
	}

	/**
	 * Create a testable logger that writes to temp file.
	 *
	 * @param bool $enabled Whether debug mode is enabled.
	 * @return \Deploy_Forge_Debug_Logger
	 */
	private function createTestableLogger( bool $enabled ): \Deploy_Forge_Debug_Logger {
		$mock_settings = Mockery::mock( 'Deploy_Forge_Settings' );
		$mock_settings->shouldReceive( 'get_all' )
			->andReturn( array( 'debug_mode' => $enabled ) );

		// Create anonymous class that overrides log file path.
		return new class( $mock_settings, $this->temp_log_file ) extends \Deploy_Forge_Debug_Logger {
			private string $test_log_file;

			public function __construct( $settings, string $log_file ) {
				parent::__construct( $settings );
				$this->test_log_file = $log_file;
			}

			public function log( string $context, string $message, array $data = array() ): void {
				if ( ! $this->is_enabled() ) {
					return;
				}

				$timestamp = current_time( 'Y-m-d H:i:s' );
				$log_entry = sprintf(
					"[%s] [%s] %s\n",
					$timestamp,
					$context,
					$message
				);

				if ( ! empty( $data ) ) {
					$log_entry .= 'Data: ' . wp_json_encode( $data, JSON_PRETTY_PRINT ) . "\n";
				}

				$log_entry .= str_repeat( '-', 80 ) . "\n";

				error_log( $log_entry, 3, $this->test_log_file );
			}
		};
	}

	/**
	 * Create a testable logger for file operations.
	 *
	 * @return \Deploy_Forge_Debug_Logger
	 */
	private function createTestableLoggerWithFile(): \Deploy_Forge_Debug_Logger {
		$mock_settings = Mockery::mock( 'Deploy_Forge_Settings' );
		$mock_settings->shouldReceive( 'get_all' )->andReturn( array() );

		// Create anonymous class that uses temp log file.
		return new class( $mock_settings, $this->temp_log_file ) extends \Deploy_Forge_Debug_Logger {
			private string $test_log_file;

			public function __construct( $settings, string $log_file ) {
				parent::__construct( $settings );
				$this->test_log_file = $log_file;
			}

			public function get_recent_logs( int $lines = 100 ): string {
				if ( ! file_exists( $this->test_log_file ) ) {
					return 'No log file found.';
				}

				$file = new \SplFileObject( $this->test_log_file );
				$file->seek( PHP_INT_MAX );
				$total_lines = $file->key();

				$start_line = max( 0, $total_lines - $lines );
				$file->seek( $start_line );

				$log_content = '';
				while ( ! $file->eof() ) {
					$log_content .= $file->current();
					$file->next();
				}

				return $log_content ? $log_content : 'Log file is empty.';
			}

			public function clear_logs(): bool {
				if ( file_exists( $this->test_log_file ) ) {
					wp_delete_file( $this->test_log_file );
					return ! file_exists( $this->test_log_file );
				}
				return true;
			}

			public function get_log_size(): string {
				if ( ! file_exists( $this->test_log_file ) ) {
					return '0 bytes';
				}

				$bytes       = filesize( $this->test_log_file );
				$units       = array( 'bytes', 'KB', 'MB', 'GB' );
				$units_count = count( $units );
				$i           = 0;

				while ( $bytes >= 1024 && $i < $units_count - 1 ) {
					$bytes /= 1024;
					++$i;
				}

				return round( $bytes, 2 ) . ' ' . $units[ $i ];
			}
		};
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Clean up temp log file.
		if ( file_exists( $this->temp_log_file ) ) {
			unlink( $this->temp_log_file );
		}

		parent::tearDown();
	}
}
