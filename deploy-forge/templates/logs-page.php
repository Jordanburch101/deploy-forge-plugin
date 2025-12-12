<?php

/**
 * Debug logs page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap deploy-forge-logs">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="debug-logs-controls" style="margin: 20px 0;">
        <button type="button" id="refresh-logs-btn" class="button button-primary">
            <?php esc_html_e('Refresh Logs', 'deploy-forge'); ?>
        </button>
        <button type="button" id="clear-logs-btn" class="button button-secondary">
            <?php esc_html_e('Clear Logs', 'deploy-forge'); ?>
        </button>

        <label style="margin-left: 20px;">
            <?php esc_html_e('Show last:', 'deploy-forge'); ?>
            <select id="log-lines-select">
                <option value="100">100 lines</option>
                <option value="500">500 lines</option>
                <option value="1000">1000 lines</option>
                <option value="5000">5000 lines</option>
            </select>
        </label>

        <span id="log-size" style="margin-left: 20px; color: #666;">
            <?php esc_html_e('Log size:', 'deploy-forge'); ?> <strong><?php echo esc_html($this->logger->get_log_size()); ?></strong>
        </span>

        <span id="log-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
    </div>

    <div class="notice notice-info">
        <p>
            <strong><?php esc_html_e('Debug Logging is Active', 'deploy-forge'); ?></strong><br>
            <?php esc_html_e('All API requests, responses, and deployment steps are being logged.', 'deploy-forge'); ?>
            <?php esc_html_e('Logs are stored at:', 'deploy-forge'); ?>
            <code><?php echo esc_html(WP_CONTENT_DIR . '/deploy-forge-debug.log'); ?></code>
        </p>
    </div>

    <div id="log-output" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.6; overflow-x: auto; max-height: 70vh; overflow-y: auto; white-space: pre-wrap; word-wrap: break-word;">
        <?php echo esc_html($this->logger->get_recent_logs(100)); ?>
    </div>

    <div class="debug-logs-footer" style="margin: 20px 0;">
        <p class="description">
            <?php esc_html_e('Logs automatically refresh when actions are performed.', 'deploy-forge'); ?>
            <?php esc_html_e('You can also manually refresh to see the latest entries.', 'deploy-forge'); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e('Privacy Warning:', 'deploy-forge'); ?></strong>
            <?php esc_html_e('Debug logs may contain sensitive information like API endpoints and parameters. Do not share logs publicly without reviewing them first.', 'deploy-forge'); ?>
        </p>
    </div>
</div>

<script type="text/javascript">
    (function($) {
        'use strict';

        const LogsViewer = {
            init: function() {
                $('#refresh-logs-btn').on('click', this.refreshLogs.bind(this));
                $('#clear-logs-btn').on('click', this.clearLogs.bind(this));
                $('#log-lines-select').on('change', this.refreshLogs.bind(this));
            },

            refreshLogs: function(e) {
                if (e) e.preventDefault();

                const $spinner = $('#log-loading');
                const $output = $('#log-output');
                const lines = $('#log-lines-select').val();

                $spinner.addClass('is-active');

                $.ajax({
                    url: deployForgeAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'deploy_forge_get_logs',
                        nonce: deployForgeAdmin.nonce,
                        lines: lines
                    },
                    success: function(response) {
                        if (response.success) {
                            $output.text(response.data.logs);
                            $('#log-size strong').text(response.data.size);

                            // Scroll to bottom
                            $output.scrollTop($output[0].scrollHeight);
                        } else {
                            alert('Failed to load logs: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Failed to load logs. Please try again.');
                    },
                    complete: function() {
                        $spinner.removeClass('is-active');
                    }
                });
            },

            clearLogs: function(e) {
                e.preventDefault();

                if (!confirm('Are you sure you want to clear all debug logs? This cannot be undone.')) {
                    return;
                }

                const $spinner = $('#log-loading');
                const $output = $('#log-output');

                $spinner.addClass('is-active');

                $.ajax({
                    url: deployForgeAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'deploy_forge_clear_logs',
                        nonce: deployForgeAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $output.text('Logs cleared. New entries will appear here.');
                            $('#log-size strong').text('0 bytes');
                        } else {
                            alert('Failed to clear logs: ' + (response.data.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('Failed to clear logs. Please try again.');
                    },
                    complete: function() {
                        $spinner.removeClass('is-active');
                    }
                });
            }
        };

        $(document).ready(function() {
            LogsViewer.init();
        });

    })(jQuery);
</script>