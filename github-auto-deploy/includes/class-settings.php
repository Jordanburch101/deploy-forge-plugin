<?php
/**
 * Settings management class
 * Handles plugin options and encrypted token storage
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_Deploy_Settings {

    private const OPTION_NAME = 'github_deploy_settings';
    private const TOKEN_OPTION = 'github_deploy_token_encrypted';
    private array $settings;

    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load settings from database
     */
    private function load_settings(): void {
        $defaults = [
            'github_repo_owner' => '',
            'github_repo_name' => '',
            'github_branch' => 'main',
            'github_workflow_name' => 'build-theme.yml',
            'target_theme_directory' => '',
            'auto_deploy_enabled' => false,
            'require_manual_approval' => false,
            'create_backups' => true,
            'notification_email' => get_option('admin_email'),
            'webhook_secret' => '',
            'debug_mode' => false,
        ];

        $this->settings = wp_parse_args(get_option(self::OPTION_NAME, []), $defaults);
    }

    /**
     * Get a setting value
     */
    public function get(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Get all settings
     */
    public function get_all(): array {
        return $this->settings;
    }

    /**
     * Save settings to database
     */
    public function save(array $settings): bool {
        // Sanitize settings
        $sanitized = [
            'github_repo_owner' => sanitize_text_field($settings['github_repo_owner'] ?? ''),
            'github_repo_name' => sanitize_text_field($settings['github_repo_name'] ?? ''),
            'github_branch' => sanitize_text_field($settings['github_branch'] ?? 'main'),
            'github_workflow_name' => sanitize_text_field($settings['github_workflow_name'] ?? 'build-theme.yml'),
            'target_theme_directory' => sanitize_text_field($settings['target_theme_directory'] ?? ''),
            'auto_deploy_enabled' => (bool) ($settings['auto_deploy_enabled'] ?? false),
            'require_manual_approval' => (bool) ($settings['require_manual_approval'] ?? false),
            'create_backups' => (bool) ($settings['create_backups'] ?? true),
            'notification_email' => sanitize_email($settings['notification_email'] ?? get_option('admin_email')),
            'webhook_secret' => sanitize_text_field($settings['webhook_secret'] ?? ''),
            'debug_mode' => (bool) ($settings['debug_mode'] ?? false),
        ];

        $result = update_option(self::OPTION_NAME, $sanitized);

        if ($result) {
            $this->settings = $sanitized;
        }

        return $result;
    }

    /**
     * Update a single setting
     */
    public function update(string $key, $value): bool {
        $this->settings[$key] = $value;
        return update_option(self::OPTION_NAME, $this->settings);
    }

    /**
     * Get GitHub token (decrypted)
     */
    public function get_github_token(): string {
        $encrypted = get_option(self::TOKEN_OPTION, '');

        if (empty($encrypted)) {
            return '';
        }

        return $this->decrypt_token($encrypted);
    }

    /**
     * Set GitHub token (encrypted)
     */
    public function set_github_token(string $token): bool {
        if (empty($token)) {
            return delete_option(self::TOKEN_OPTION);
        }

        $encrypted = $this->encrypt_token($token);
        return update_option(self::TOKEN_OPTION, $encrypted);
    }

    /**
     * Encrypt token using sodium
     */
    private function encrypt_token(string $token): string {
        if (!function_exists('sodium_crypto_secretbox')) {
            // Fallback to base64 encoding if sodium not available
            return base64_encode($token);
        }

        $key = $this->get_encryption_key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($token, $nonce, $key);

        return base64_encode($nonce . $encrypted);
    }

    /**
     * Decrypt token using sodium
     */
    private function decrypt_token(string $encrypted): string {
        $decoded = base64_decode($encrypted);

        if (!function_exists('sodium_crypto_secretbox_open')) {
            // Fallback for base64 encoded tokens
            return $decoded;
        }

        $key = $this->get_encryption_key();
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Get encryption key derived from WordPress salts
     */
    private function get_encryption_key(): string {
        $salt = wp_salt('auth') . wp_salt('secure_auth');

        if (function_exists('sodium_crypto_generichash')) {
            return sodium_crypto_generichash($salt, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }

        // Fallback to hash for environments without sodium
        return hash('sha256', $salt, true);
    }

    /**
     * Validate settings
     */
    public function validate(): array {
        $errors = [];

        if (empty($this->get('github_repo_owner'))) {
            $errors[] = __('GitHub repository owner is required.', 'github-auto-deploy');
        }

        if (empty($this->get('github_repo_name'))) {
            $errors[] = __('GitHub repository name is required.', 'github-auto-deploy');
        }

        if (empty($this->get('github_branch'))) {
            $errors[] = __('GitHub branch is required.', 'github-auto-deploy');
        }

        if (empty($this->get('target_theme_directory'))) {
            $errors[] = __('Target theme directory is required.', 'github-auto-deploy');
        }

        if (empty($this->get_github_token())) {
            $errors[] = __('GitHub personal access token is required.', 'github-auto-deploy');
        }

        $theme_path = WP_CONTENT_DIR . '/themes/' . $this->get('target_theme_directory');
        if (!empty($this->get('target_theme_directory')) && !is_dir($theme_path)) {
            $errors[] = sprintf(
                __('Theme directory does not exist: %s', 'github-auto-deploy'),
                $theme_path
            );
        }

        return $errors;
    }

    /**
     * Check if settings are configured
     */
    public function is_configured(): bool {
        return !empty($this->get('github_repo_owner'))
            && !empty($this->get('github_repo_name'))
            && !empty($this->get('github_branch'))
            && !empty($this->get('target_theme_directory'))
            && !empty($this->get_github_token());
    }

    /**
     * Get repository full name (owner/repo)
     */
    public function get_repo_full_name(): string {
        return $this->get('github_repo_owner') . '/' . $this->get('github_repo_name');
    }

    /**
     * Get theme path
     */
    public function get_theme_path(): string {
        return WP_CONTENT_DIR . '/themes/' . $this->get('target_theme_directory');
    }

    /**
     * Get backup directory
     */
    public function get_backup_directory(): string {
        $upload_dir = wp_upload_dir();
        $backup_dir = $upload_dir['basedir'] . '/github-deploy-backups';

        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        return $backup_dir;
    }

    /**
     * Generate webhook secret
     */
    public function generate_webhook_secret(): string {
        $secret = wp_generate_password(32, false);
        $this->update('webhook_secret', $secret);
        return $secret;
    }

    /**
     * Get webhook URL
     */
    public function get_webhook_url(): string {
        return rest_url('github-deploy/v1/webhook');
    }
}
