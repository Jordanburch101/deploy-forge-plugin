<?php
/**
 * PHPUnit bootstrap file for Deploy Forge tests.
 *
 * @package Deploy_Forge
 */

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Brain Monkey.
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// Define WordPress constants needed by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// Define plugin constants.
if ( ! defined( 'DEPLOY_FORGE_VERSION' ) ) {
	define( 'DEPLOY_FORGE_VERSION', '1.0.54' );
}

if ( ! defined( 'DEPLOY_FORGE_PLUGIN_DIR' ) ) {
	define( 'DEPLOY_FORGE_PLUGIN_DIR', dirname( __DIR__ ) . '/deploy-forge/' );
}

if ( ! defined( 'DEPLOY_FORGE_PLUGIN_URL' ) ) {
	define( 'DEPLOY_FORGE_PLUGIN_URL', 'http://example.com/wp-content/plugins/deploy-forge/' );
}

/**
 * Mock WP_REST_Request class for testing.
 *
 * Simulates WordPress WP_REST_Request class behavior.
 */
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = array();
		private string $method = 'GET';
		private string $route = '';
		private array $headers = array();
		private string $body = '';

		public function __construct( $method = 'GET', $route = '' ) {
			$this->method = $method;
			$this->route  = $route;
		}

		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		public function get_param( $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params(): array {
			return $this->params;
		}

		public function set_header( $key, $value ) {
			$this->headers[ strtolower( $key ) ] = $value;
		}

		public function get_header( $key ) {
			return $this->headers[ strtolower( $key ) ] ?? null;
		}

		public function get_headers(): array {
			return $this->headers;
		}

		public function set_body( $body ) {
			$this->body = $body;
		}

		public function get_body(): string {
			return $this->body;
		}

		public function get_json_params(): array {
			$decoded = json_decode( $this->body, true );
			return is_array( $decoded ) ? $decoded : array();
		}

		public function get_method(): string {
			return $this->method;
		}

		public function get_route(): string {
			return $this->route;
		}
	}
}

/**
 * Mock WP_REST_Response class for testing.
 *
 * Simulates WordPress WP_REST_Response class behavior.
 */
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private int $status = 200;
		private array $headers = array();

		public function __construct( $data = null, $status = 200, $headers = array() ) {
			$this->data    = $data;
			$this->status  = $status;
			$this->headers = $headers;
		}

		public function get_data() {
			return $this->data;
		}

		public function set_data( $data ) {
			$this->data = $data;
		}

		public function get_status(): int {
			return $this->status;
		}

		public function set_status( $status ) {
			$this->status = (int) $status;
		}

		public function get_headers(): array {
			return $this->headers;
		}

		public function set_headers( array $headers ) {
			$this->headers = $headers;
		}

		public function header( $key, $value, $replace = true ) {
			if ( $replace || ! isset( $this->headers[ $key ] ) ) {
				$this->headers[ $key ] = $value;
			}
		}
	}
}

/**
 * Mock WP_Error class for testing.
 *
 * Simulates WordPress WP_Error class behavior.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/**
		 * Error codes and messages.
		 *
		 * @var array
		 */
		private array $errors = array();

		/**
		 * Error data.
		 *
		 * @var array
		 */
		private array $error_data = array();

		/**
		 * Constructor.
		 *
		 * @param string|int $code    Error code.
		 * @param string     $message Error message.
		 * @param mixed      $data    Optional error data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->add( $code, $message, $data );
			}
		}

		/**
		 * Add an error.
		 *
		 * @param string|int $code    Error code.
		 * @param string     $message Error message.
		 * @param mixed      $data    Optional error data.
		 * @return void
		 */
		public function add( $code, $message, $data = '' ): void {
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}

		/**
		 * Get error code.
		 *
		 * @return string|int First error code, or empty string if no errors.
		 */
		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return $codes[0] ?? '';
		}

		/**
		 * Get error message.
		 *
		 * @param string|int $code Optional error code.
		 * @return string Error message.
		 */
		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][0] ?? '';
		}

		/**
		 * Get error data.
		 *
		 * @param string|int $code Optional error code.
		 * @return mixed Error data.
		 */
		public function get_error_data( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			return $this->error_data[ $code ] ?? null;
		}

		/**
		 * Get all error codes.
		 *
		 * @return array Error codes.
		 */
		public function get_error_codes(): array {
			return array_keys( $this->errors );
		}

		/**
		 * Get all error messages.
		 *
		 * @param string|int $code Optional error code.
		 * @return array Error messages.
		 */
		public function get_error_messages( $code = '' ): array {
			if ( empty( $code ) ) {
				$all_messages = array();
				foreach ( $this->errors as $messages ) {
					$all_messages = array_merge( $all_messages, $messages );
				}
				return $all_messages;
			}
			return $this->errors[ $code ] ?? array();
		}

		/**
		 * Check if there are errors.
		 *
		 * @return bool True if errors exist.
		 */
		public function has_errors(): bool {
			return ! empty( $this->errors );
		}
	}
}
