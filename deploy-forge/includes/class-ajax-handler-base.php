<?php
/**
 * Base AJAX handler class
 *
 * Provides common functionality for AJAX handlers including
 * security verification, response handling, and input sanitization.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Ajax_Handler_Base
 *
 * Abstract base class that provides common AJAX handling functionality
 * to eliminate duplicate security checks and response patterns.
 *
 * @since 1.0.0
 */
abstract class Deploy_Forge_Ajax_Handler_Base {

	/**
	 * Verify AJAX request security (nonce + capability).
	 *
	 * @since 1.0.0
	 *
	 * @param string $nonce_action The nonce action to verify.
	 * @param string $capability   Required user capability (default: 'manage_options').
	 * @return bool True if verified, sends error and exits if not.
	 */
	protected function verify_ajax_request( string $nonce_action, string $capability = 'manage_options' ): bool {
		// Verify nonce.
		check_ajax_referer( $nonce_action, 'nonce' );

		// Verify user capability.
		if ( ! current_user_can( $capability ) ) {
			$this->send_error( __( 'Unauthorized', 'deploy-forge' ) );
			return false;
		}

		return true;
	}

	/**
	 * Send AJAX success response.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $data    Data to send (optional).
	 * @param string $message Success message (optional).
	 * @return void
	 */
	protected function send_success( $data = null, string $message = '' ): void {
		$response = array();

		if ( ! empty( $message ) ) {
			$response['message'] = $message;
		}

		if ( null !== $data ) {
			// If data is already an array with 'message', merge it.
			if ( is_array( $data ) && isset( $data['message'] ) && empty( $message ) ) {
				$response = $data;
			} else {
				$response = array_merge( $response, is_array( $data ) ? $data : array( 'data' => $data ) );
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * Send AJAX error response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Error message.
	 * @param string $code    Error code (optional).
	 * @param mixed  $data    Additional error data (optional).
	 * @return void
	 */
	protected function send_error( string $message, string $code = '', $data = null ): void {
		$response = array( 'message' => $message );

		if ( ! empty( $code ) ) {
			$response['error_code'] = $code;
		}

		if ( null !== $data ) {
			$response = array_merge( $response, is_array( $data ) ? $data : array( 'error_data' => $data ) );
		}

		wp_send_json_error( $response );
	}

	/**
	 * Get and sanitize POST parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key               Parameter key.
	 * @param mixed  $default_value     Default value if not set.
	 * @param string $sanitize_callback Sanitization function (default: sanitize_text_field).
	 * @return mixed Sanitized value.
	 */
	protected function get_post_param( string $key, $default_value = '', string $sanitize_callback = 'sanitize_text_field' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request().
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default_value;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_ajax_request(). Sanitization applied via $sanitize_callback.
		$value = wp_unslash( $_POST[ $key ] );

		// Apply sanitization.
		if ( function_exists( $sanitize_callback ) ) {
			if ( is_array( $value ) ) {
				return array_map( $sanitize_callback, $value );
			}
			return call_user_func( $sanitize_callback, $value );
		}

		return $value;
	}

	/**
	 * Get integer POST parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key           Parameter key.
	 * @param int    $default_value Default value if not set.
	 * @return int Sanitized integer value.
	 */
	protected function get_post_int( string $key, int $default_value = 0 ): int {
		return intval( $this->get_post_param( $key, $default_value, 'intval' ) );
	}

	/**
	 * Get boolean POST parameter.
	 *
	 * Checks for '1', 'true', true, or 'on' values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key           Parameter key.
	 * @param bool   $default_value Default value if not set.
	 * @return bool Boolean value.
	 */
	protected function get_post_bool( string $key, bool $default_value = false ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request().
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default_value;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_ajax_request(). Boolean validation is applied below.
		$value = wp_unslash( $_POST[ $key ] );

		// Handle various truthy values.
		return in_array( $value, array( 1, '1', 'true', true, 'on' ), true );
	}

	/**
	 * Validate required POST parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $required_params Array of required parameter keys.
	 * @return bool True if all present, sends error and exits if not.
	 */
	protected function validate_required_params( array $required_params ): bool {
		$missing = array();

		foreach ( $required_params as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify_ajax_request().
			if ( ! isset( $_POST[ $param ] ) || '' === $_POST[ $param ] ) {
				$missing[] = $param;
			}
		}

		if ( ! empty( $missing ) ) {
			$this->send_error(
				sprintf(
					// Translators: %s is a comma-separated list of missing parameter names.
					__( 'Missing required parameters: %s', 'deploy-forge' ),
					implode( ', ', $missing )
				)
			);
			return false;
		}

		return true;
	}

	/**
	 * Handle API response and send appropriate AJAX response.
	 *
	 * Converts API-style responses to AJAX responses.
	 *
	 * @since 1.0.0
	 *
	 * @param array $api_response API response with 'success' and optional 'message'/'data'.
	 * @return void
	 */
	protected function handle_api_response( array $api_response ): void {
		if ( isset( $api_response['success'] ) && $api_response['success'] ) {
			$this->send_success(
				$api_response['data'] ?? null,
				$api_response['message'] ?? ''
			);
		} else {
			$this->send_error(
				$api_response['message'] ?? __( 'Operation failed', 'deploy-forge' ),
				$api_response['error_code'] ?? '',
				$api_response['data'] ?? null
			);
		}
	}

	/**
	 * Log message (if logger is available).
	 *
	 * Subclasses can override this to use their logger instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context Log context.
	 * @param string $message Log message.
	 * @param array  $data    Additional data.
	 * @return void
	 */
	protected function log( string $context, string $message, array $data = array() ): void {
		// Override in subclass if logger is available.
		// Example: $this->logger->log( $context, $message, $data ).
	}
}
