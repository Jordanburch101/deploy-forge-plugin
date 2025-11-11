<?php

/**
 * Debug Logger class
 * Handles opt-in debug logging for troubleshooting
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Debug_Logger
{

    private Deploy_Forge_Settings $settings;
    private string $log_file;

    public function __construct(Deploy_Forge_Settings $settings)
    {
        $this->settings = $settings;
        $this->log_file = WP_CONTENT_DIR . '/deploy-forge-debug.log';
    }

    /**
     * Check if debug mode is enabled
     */
    public function is_enabled(): bool
    {
        $all_settings = $this->settings->get_all();
        return !empty($all_settings['debug_mode']);
    }

    /**
     * Log a debug message
     */
    public function log(string $context, string $message, array $data = []): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            $context,
            $message
        );

        // Add data if provided
        if (!empty($data)) {
            $log_entry .= "Data: " . wp_json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }

        $log_entry .= str_repeat('-', 80) . "\n";

        // Write to log file
        error_log($log_entry, 3, $this->log_file);
    }

    /**
     * Log an error
     */
    public function error(string $context, string $message, $error_data = null): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        $data = [];
        if ($error_data !== null) {
            if (is_wp_error($error_data)) {
                $data['wp_error'] = [
                    'code' => $error_data->get_error_code(),
                    'message' => $error_data->get_error_message(),
                    'data' => $error_data->get_error_data(),
                ];
            } elseif (is_array($error_data) || is_object($error_data)) {
                $data['error'] = $error_data;
            } else {
                $data['error'] = (string)$error_data;
            }
        }

        $this->log($context . ':ERROR', $message, $data);
    }

    /**
     * Log API request
     */
    public function log_api_request(string $method, string $endpoint, array $params = [], array $headers = []): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        // Remove sensitive headers
        $safe_headers = $headers;
        if (isset($safe_headers['Authorization'])) {
            $safe_headers['Authorization'] = 'Bearer [REDACTED]';
        }

        $this->log('GitHub_API', "API Request: $method $endpoint", [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'headers' => $safe_headers,
        ]);
    }

    /**
     * Log API response
     */
    public function log_api_response(string $endpoint, int $status_code, $response_body, ?string $error = null): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        $data = [
            'endpoint' => $endpoint,
            'status_code' => $status_code,
        ];

        if ($error) {
            $data['error'] = $error;
        }

        // Limit response body size
        if (is_string($response_body)) {
            $data['response_body'] = strlen($response_body) > 1000
                ? substr($response_body, 0, 1000) . '... [truncated]'
                : $response_body;
        } elseif (is_array($response_body) || is_object($response_body)) {
            $json = wp_json_encode($response_body);
            $data['response_body'] = strlen($json) > 1000
                ? substr($json, 0, 1000) . '... [truncated]'
                : $response_body;
        }

        $message = $error ? "API Response ERROR" : "API Response SUCCESS";
        $this->log('GitHub_API', $message, $data);
    }

    /**
     * Log deployment step
     */
    public function log_deployment_step(int $deployment_id, string $step, string $status, array $details = []): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        $this->log('Deployment', "Deployment #$deployment_id - $step: $status", $details);
    }

    /**
     * Get recent log entries
     */
    public function get_recent_logs(int $lines = 100): string
    {
        if (!file_exists($this->log_file)) {
            return "No log file found.";
        }

        /**
         * @var \SplFileObject $file
         * @noinspection PhpUndefinedClassInspection
         */
        $file = new SplFileObject($this->log_file);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();

        $start_line = max(0, $total_lines - $lines);
        $file->seek($start_line);

        $log_content = '';
        while (!$file->eof()) {
            $log_content .= $file->current();
            $file->next();
        }

        return $log_content ?: "Log file is empty.";
    }

    /**
     * Clear log file
     */
    public function clear_logs(): bool
    {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true;
    }

    /**
     * Get log file size
     */
    public function get_log_size(): string
    {
        if (!file_exists($this->log_file)) {
            return '0 bytes';
        }

        $bytes = filesize($this->log_file);
        $units = ['bytes', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
