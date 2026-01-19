<?php
/**
 * Debug logger class
 *
 * Handles opt-in debug logging for troubleshooting deployment issues.
 * Logs are stored in wp-content/deploy-forge-debug.log.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Debug_Logger
 *
 * Provides debug logging functionality with support for various
 * log levels and structured data logging.
 *
 * @since 1.0.0
 */
class Deploy_Forge_Debug_Logger {

	/**
	 * Settings instance.
	 *
	 * @since 1.0.0
	 * @var Deploy_Forge_Settings
	 */
	private Deploy_Forge_Settings $settings;

	/**
	 * Path to the log file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $log_file;

	/**
	 * Constructor.
	 *
	 * Initialize the logger with settings instance.
	 *
	 * @since 1.0.0
	 *
	 * @param Deploy_Forge_Settings $settings Settings instance.
	 */
	public function __construct( Deploy_Forge_Settings $settings ) {
		$this->settings = $settings;
		$this->log_file = WP_CONTENT_DIR . '/deploy-forge-debug.log';
	}

	/**
	 * Check if debug mode is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if debug mode is enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		$all_settings = $this->settings->get_all();
		return ! empty( $all_settings['debug_mode'] );
	}

	/**
	 * Log a debug message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context The context or category of the log entry.
	 * @param string $message The log message.
	 * @param array  $data    Optional additional data to include in the log.
	 * @return void
	 */
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

		// Add data if provided.
		if ( ! empty( $data ) ) {
			$log_entry .= 'Data: ' . wp_json_encode( $data, JSON_PRETTY_PRINT ) . "\n";
		}

		$log_entry .= str_repeat( '-', 80 ) . "\n";

		// Write to log file - intentional logging to custom log file.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging to custom file.
		error_log( $log_entry, 3, $this->log_file );
	}

	/**
	 * Log an error.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context    The context or category of the error.
	 * @param string $message    The error message.
	 * @param mixed  $error_data Optional error data (can be WP_Error, array, object, or string).
	 * @return void
	 */
	public function error( string $context, string $message, $error_data = null ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$data = array();
		if ( null !== $error_data ) {
			if ( is_wp_error( $error_data ) ) {
				$data['wp_error'] = array(
					'code'    => $error_data->get_error_code(),
					'message' => $error_data->get_error_message(),
					'data'    => $error_data->get_error_data(),
				);
			} elseif ( is_array( $error_data ) || is_object( $error_data ) ) {
				$data['error'] = $error_data;
			} else {
				$data['error'] = (string) $error_data;
			}
		}

		$this->log( $context . ':ERROR', $message, $data );
	}

	/**
	 * Log API request.
	 *
	 * Logs details about an outgoing API request with sensitive data redacted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method   The HTTP method (GET, POST, etc.).
	 * @param string $endpoint The API endpoint URL.
	 * @param array  $params   Optional request parameters.
	 * @param array  $headers  Optional request headers (Authorization will be redacted).
	 * @return void
	 */
	public function log_api_request( string $method, string $endpoint, array $params = array(), array $headers = array() ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Remove sensitive headers.
		$safe_headers = $headers;
		if ( isset( $safe_headers['Authorization'] ) ) {
			$safe_headers['Authorization'] = 'Bearer [REDACTED]';
		}

		$this->log(
			'GitHub_API',
			"API Request: $method $endpoint",
			array(
				'method'   => $method,
				'endpoint' => $endpoint,
				'params'   => $params,
				'headers'  => $safe_headers,
			)
		);
	}

	/**
	 * Log API response.
	 *
	 * Logs details about an API response with large responses truncated.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $endpoint      The API endpoint that was called.
	 * @param int         $status_code   The HTTP response status code.
	 * @param mixed       $response_body The response body.
	 * @param string|null $error         Optional error message.
	 * @return void
	 */
	public function log_api_response( string $endpoint, int $status_code, $response_body, ?string $error = null ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$data = array(
			'endpoint'    => $endpoint,
			'status_code' => $status_code,
		);

		if ( $error ) {
			$data['error'] = $error;
		}

		// Limit response body size.
		if ( is_string( $response_body ) ) {
			$data['response_body'] = strlen( $response_body ) > 1000
				? substr( $response_body, 0, 1000 ) . '... [truncated]'
				: $response_body;
		} elseif ( is_array( $response_body ) || is_object( $response_body ) ) {
			$json                  = wp_json_encode( $response_body );
			$data['response_body'] = strlen( $json ) > 1000
				? substr( $json, 0, 1000 ) . '... [truncated]'
				: $response_body;
		}

		$log_message = $error ? 'API Response ERROR' : 'API Response SUCCESS';
		$this->log( 'GitHub_API', $log_message, $data );
	}

	/**
	 * Log deployment step.
	 *
	 * Logs a specific step in the deployment process.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $deployment_id The deployment ID.
	 * @param string $step          The deployment step name.
	 * @param string $status        The status of the step.
	 * @param array  $details       Optional additional details.
	 * @return void
	 */
	public function log_deployment_step( int $deployment_id, string $step, string $status, array $details = array() ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->log( 'Deployment', "Deployment #$deployment_id - $step: $status", $details );
	}

	/**
	 * Get recent log entries.
	 *
	 * Retrieves the most recent log entries from the log file.
	 *
	 * @since 1.0.0
	 *
	 * @param int $lines Number of lines to retrieve from the end of the file.
	 * @return string The log content or a message if no logs exist.
	 */
	public function get_recent_logs( int $lines = 100 ): string {
		if ( ! file_exists( $this->log_file ) ) {
			return 'No log file found.';
		}

		/**
		 * SplFileObject for reading log file.
		 *
		 * @var \SplFileObject $file
		 */
		$file = new SplFileObject( $this->log_file );
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

	/**
	 * Clear log file.
	 *
	 * Deletes the log file to clear all log entries.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_logs(): bool {
		if ( file_exists( $this->log_file ) ) {
			wp_delete_file( $this->log_file );
			return ! file_exists( $this->log_file );
		}
		return true;
	}

	/**
	 * Get log file size.
	 *
	 * Returns a human-readable size of the log file.
	 *
	 * @since 1.0.0
	 *
	 * @return string Human-readable file size.
	 */
	public function get_log_size(): string {
		if ( ! file_exists( $this->log_file ) ) {
			return '0 bytes';
		}

		$bytes       = filesize( $this->log_file );
		$units       = array( 'bytes', 'KB', 'MB', 'GB' );
		$units_count = count( $units );
		$i           = 0;

		while ( $bytes >= 1024 && $i < $units_count - 1 ) {
			$bytes /= 1024;
			++$i;
		}

		return round( $bytes, 2 ) . ' ' . $units[ $i ];
	}
}
