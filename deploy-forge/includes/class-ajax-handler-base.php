<?php

/**
 * Base AJAX Handler class
 * Provides common functionality for AJAX handlers
 * Eliminates duplicate security checks and response patterns
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class Deploy_Forge_Ajax_Handler_Base
{

    /**
     * Verify AJAX request security (nonce + capability)
     *
     * @param string $nonce_action The nonce action to verify
     * @param string $capability Required user capability (default: 'manage_options')
     * @return bool True if verified, sends error and exits if not
     */
    protected function verify_ajax_request(string $nonce_action, string $capability = 'manage_options'): bool
    {
        // Verify nonce
        check_ajax_referer($nonce_action, 'nonce');

        // Verify user capability
        if (!current_user_can($capability)) {
            $this->send_error(__('Unauthorized', 'deploy-forge'));
            return false;
        }

        return true;
    }

    /**
     * Send AJAX success response
     *
     * @param mixed $data Data to send (optional)
     * @param string $message Success message (optional)
     * @return void
     */
    protected function send_success($data = null, string $message = ''): void
    {
        $response = [];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            // If data is already an array with 'message', merge it
            if (is_array($data) && isset($data['message']) && empty($message)) {
                $response = $data;
            } else {
                $response = array_merge($response, is_array($data) ? $data : ['data' => $data]);
            }
        }

        wp_send_json_success($response);
    }

    /**
     * Send AJAX error response
     *
     * @param string $message Error message
     * @param string $code Error code (optional)
     * @param mixed $data Additional error data (optional)
     * @return void
     */
    protected function send_error(string $message, string $code = '', $data = null): void
    {
        $response = ['message' => $message];

        if (!empty($code)) {
            $response['error_code'] = $code;
        }

        if ($data !== null) {
            $response = array_merge($response, is_array($data) ? $data : ['error_data' => $data]);
        }

        wp_send_json_error($response);
    }

    /**
     * Get and sanitize POST parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value if not set
     * @param string $sanitize_callback Sanitization function (default: sanitize_text_field)
     * @return mixed Sanitized value
     */
    protected function get_post_param(string $key, $default = '', string $sanitize_callback = 'sanitize_text_field')
    {
        if (!isset($_POST[$key])) {
            return $default;
        }

        $value = $_POST[$key];

        // Apply sanitization
        if (function_exists($sanitize_callback)) {
            if (is_array($value)) {
                return array_map($sanitize_callback, $value);
            }
            return call_user_func($sanitize_callback, $value);
        }

        return $value;
    }

    /**
     * Get integer POST parameter
     *
     * @param string $key Parameter key
     * @param int $default Default value if not set
     * @return int Sanitized integer value
     */
    protected function get_post_int(string $key, int $default = 0): int
    {
        return intval($this->get_post_param($key, $default, 'intval'));
    }

    /**
     * Get boolean POST parameter (checks for '1', 'true', true, 'on')
     *
     * @param string $key Parameter key
     * @param bool $default Default value if not set
     * @return bool Boolean value
     */
    protected function get_post_bool(string $key, bool $default = false): bool
    {
        if (!isset($_POST[$key])) {
            return $default;
        }

        $value = $_POST[$key];

        // Handle various truthy values
        return in_array($value, [1, '1', 'true', true, 'on'], true);
    }

    /**
     * Validate required POST parameters
     *
     * @param array $required_params Array of required parameter keys
     * @return bool True if all present, sends error and exits if not
     */
    protected function validate_required_params(array $required_params): bool
    {
        $missing = [];

        foreach ($required_params as $param) {
            if (!isset($_POST[$param]) || $_POST[$param] === '') {
                $missing[] = $param;
            }
        }

        if (!empty($missing)) {
            $this->send_error(
                sprintf(
                    __('Missing required parameters: %s', 'deploy-forge'),
                    implode(', ', $missing)
                )
            );
            return false;
        }

        return true;
    }

    /**
     * Handle API response and send appropriate AJAX response
     * Converts API-style responses to AJAX responses
     *
     * @param array $api_response API response with 'success' and optional 'message'/'data'
     * @return void
     */
    protected function handle_api_response(array $api_response): void
    {
        if (isset($api_response['success']) && $api_response['success']) {
            $this->send_success(
                $api_response['data'] ?? null,
                $api_response['message'] ?? ''
            );
        } else {
            $this->send_error(
                $api_response['message'] ?? __('Operation failed', 'deploy-forge'),
                $api_response['error_code'] ?? '',
                $api_response['data'] ?? null
            );
        }
    }

    /**
     * Log message (if logger is available)
     * Subclasses can override this to use their logger instance
     *
     * @param string $context Log context
     * @param string $message Log message
     * @param array $data Additional data
     * @return void
     */
    protected function log(string $context, string $message, array $data = []): void
    {
        // Override in subclass if logger is available
        // Example: $this->logger->log($context, $message, $data);
    }
}
