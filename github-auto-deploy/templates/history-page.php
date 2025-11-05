<?php

/**
 * Deployment history page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap github-deploy-history">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (empty($deployments)): ?>
        <p><?php esc_html_e('No deployments found.', 'github-auto-deploy'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'github-auto-deploy'); ?></th>
                    <th><?php esc_html_e('Date/Time', 'github-auto-deploy'); ?></th>
                    <th><?php esc_html_e('Commit', 'github-auto-deploy'); ?></th>
                    <th><?php esc_html_e('Message', 'github-auto-deploy'); ?></th>
                    <th><?php esc_html_e('Author', 'github-auto-deploy'); ?></th>
                    <th><?php esc_html_e('Status', 'github-auto-deploy'); ?></th>
                    <th><?php esc_html_e('Trigger', 'github-auto-deploy'); ?></th>
                    <th><?php esc_html_e('Actions', 'github-auto-deploy'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deployments as $deployment): ?>
                    <tr>
                        <td><?php echo esc_html($deployment->id); ?></td>
                        <td><?php echo esc_html(mysql2date('M j, Y g:i a', $deployment->created_at)); ?></td>
                        <td>
                            <code><?php echo esc_html(substr($deployment->commit_hash, 0, 7)); ?></code>
                            <?php if ($deployment->build_url): ?>
                                <a href="<?php echo esc_url($deployment->build_url); ?>" target="_blank" title="<?php esc_attr_e('View build on GitHub', 'github-auto-deploy'); ?>">
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(wp_trim_words($deployment->commit_message, 10)); ?></td>
                        <td><?php echo esc_html($deployment->commit_author); ?></td>
                        <td>
                            <span class="deployment-status status-<?php echo esc_attr($deployment->status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $deployment->status))); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(ucfirst($deployment->trigger_type)); ?></td>
                        <td>
                            <button type="button" class="button button-small view-details-btn"
                                data-deployment-id="<?php echo esc_attr($deployment->id); ?>">
                                <?php esc_html_e('Details', 'github-auto-deploy'); ?>
                            </button>
                            <?php if ($deployment->status === 'success' && !empty($deployment->backup_path)): ?>
                                <button type="button" class="button button-small rollback-btn"
                                    data-deployment-id="<?php echo esc_attr($deployment->id); ?>">
                                    <?php esc_html_e('Rollback', 'github-auto-deploy'); ?>
                                </button>
                            <?php endif; ?>
                            <?php if (in_array($deployment->status, ['pending', 'building'])): ?>
                                <button type="button" class="button button-small cancel-deployment-btn"
                                    data-deployment-id="<?php echo esc_attr($deployment->id); ?>">
                                    <?php esc_html_e('Cancel', 'github-auto-deploy'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Deployment Details Modal -->
<div id="deployment-details-modal" class="github-deploy-modal" style="display: none;">
    <div class="github-deploy-modal-content">
        <span class="github-deploy-modal-close">&times;</span>
        <h2><?php esc_html_e('Deployment Details', 'github-auto-deploy'); ?></h2>
        <div id="deployment-details-content">
            <p><?php esc_html_e('Loading...', 'github-auto-deploy'); ?></p>
        </div>
    </div>
</div>

<style>
    .github-deploy-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4);
    }

    .github-deploy-modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 800px;
        border-radius: 4px;
    }

    .github-deploy-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .github-deploy-modal-close:hover,
    .github-deploy-modal-close:focus {
        color: #000;
    }

    #deployment-details-content pre {
        background: #f5f5f5;
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
        max-height: 400px;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        // View details
        $('.view-details-btn').on('click', function() {
            var deploymentId = $(this).data('deployment-id');
            var modal = $('#deployment-details-modal');
            var content = $('#deployment-details-content');

            modal.show();
            content.html('<p><?php esc_html_e('Loading...', 'github-auto-deploy'); ?></p>');

            $.post(ajaxurl, {
                action: 'github_deploy_get_status',
                nonce: '<?php echo esc_js(wp_create_nonce('github_deploy_admin')); ?>',
                deployment_id: deploymentId
            }, function(response) {
                if (response.success) {
                    var d = response.data.deployment;
                    var html = '<table class="widefat">';
                    html += '<tr><th><?php esc_html_e('Commit Hash', 'github-auto-deploy'); ?></th><td><code>' + d.commit_hash + '</code></td></tr>';
                    html += '<tr><th><?php esc_html_e('Message', 'github-auto-deploy'); ?></th><td>' + d.commit_message + '</td></tr>';
                    html += '<tr><th><?php esc_html_e('Author', 'github-auto-deploy'); ?></th><td>' + d.commit_author + '</td></tr>';
                    html += '<tr><th><?php esc_html_e('Status', 'github-auto-deploy'); ?></th><td>' + d.status + '</td></tr>';
                    if (d.build_url) {
                        html += '<tr><th><?php esc_html_e('Build URL', 'github-auto-deploy'); ?></th><td><a href="' + d.build_url + '" target="_blank">' + d.build_url + '</a></td></tr>';
                    }
                    html += '</table>';

                    if (d.deployment_logs) {
                        html += '<h3><?php esc_html_e('Deployment Logs', 'github-auto-deploy'); ?></h3>';
                        html += '<pre>' + d.deployment_logs + '</pre>';
                    }

                    if (d.error_message) {
                        html += '<h3><?php esc_html_e('Error Message', 'github-auto-deploy'); ?></h3>';
                        html += '<pre>' + d.error_message + '</pre>';
                    }

                    content.html(html);
                }
            });
        });

        // Close modal
        $('.github-deploy-modal-close, .github-deploy-modal').on('click', function(e) {
            if (e.target === this) {
                $('#deployment-details-modal').hide();
            }
        });
    });
</script>