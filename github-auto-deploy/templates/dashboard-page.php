<?php

/**
 * Dashboard page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap github-deploy-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (!$is_configured): ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('GitHub Deploy is not fully configured.', 'github-auto-deploy'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-settings')); ?>">
                    <?php esc_html_e('Complete setup', 'github-auto-deploy'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div class="github-deploy-cards">
        <!-- Connection Status Card -->
        <div class="github-deploy-card">
            <h2><?php esc_html_e('Connection Status', 'github-auto-deploy'); ?></h2>
            <?php if ($is_configured): ?>
                <p class="status-connected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Connected to GitHub', 'github-auto-deploy'); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Repository:', 'github-auto-deploy'); ?></strong>
                    <?php echo esc_html($this->settings->get_repo_full_name()); ?>
                </p>
                <p>
                    <strong><?php esc_html_e('Branch:', 'github-auto-deploy'); ?></strong>
                    <?php echo esc_html($this->settings->get('github_branch')); ?>
                </p>
                <?php if ($stats['last_deployment']): ?>
                    <p>
                        <strong><?php esc_html_e('Last Deployment:', 'github-auto-deploy'); ?></strong>
                        <?php echo esc_html(mysql2date('F j, Y g:i a', $stats['last_deployment']->deployed_at)); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Commit:', 'github-auto-deploy'); ?></strong>
                        <code><?php echo esc_html(substr($stats['last_deployment']->commit_hash, 0, 7)); ?></code>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="status-disconnected">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Not configured', 'github-auto-deploy'); ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Statistics Card -->
        <div class="github-deploy-card">
            <h2><?php esc_html_e('Deployment Statistics', 'github-auto-deploy'); ?></h2>
            <div class="github-deploy-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo esc_html($stats['total']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Total', 'github-auto-deploy'); ?></div>
                </div>
                <div class="stat-item stat-success">
                    <div class="stat-number"><?php echo esc_html($stats['success']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Successful', 'github-auto-deploy'); ?></div>
                </div>
                <div class="stat-item stat-failed">
                    <div class="stat-number"><?php echo esc_html($stats['failed']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Failed', 'github-auto-deploy'); ?></div>
                </div>
                <div class="stat-item stat-pending">
                    <div class="stat-number"><?php echo esc_html($stats['pending'] + $stats['building']); ?></div>
                    <div class="stat-label"><?php esc_html_e('Pending', 'github-auto-deploy'); ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="github-deploy-card">
            <h2><?php esc_html_e('Quick Actions', 'github-auto-deploy'); ?></h2>
            <p>
                <button type="button" class="button button-primary button-hero" id="deploy-now-btn" <?php echo !$is_configured ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Deploy Now', 'github-auto-deploy'); ?>
                </button>
            </p>
            <p>
                <button type="button" class="button" id="refresh-status-btn">
                    <span class="dashicons dashicons-update-alt"></span>
                    <?php esc_html_e('Refresh Status', 'github-auto-deploy'); ?>
                </button>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-history')); ?>" class="button">
                    <?php esc_html_e('View Full History', 'github-auto-deploy'); ?>
                </a>
            </p>
        </div>
    </div>

    <!-- Recent Deployments -->
    <div class="github-deploy-recent">
        <h2><?php esc_html_e('Recent Deployments', 'github-auto-deploy'); ?></h2>
        <?php if (empty($recent_deployments)): ?>
            <p><?php esc_html_e('No deployments yet.', 'github-auto-deploy'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'github-auto-deploy'); ?></th>
                        <th><?php esc_html_e('Commit', 'github-auto-deploy'); ?></th>
                        <th><?php esc_html_e('Message', 'github-auto-deploy'); ?></th>
                        <th><?php esc_html_e('Status', 'github-auto-deploy'); ?></th>
                        <th><?php esc_html_e('Trigger', 'github-auto-deploy'); ?></th>
                        <th><?php esc_html_e('Actions', 'github-auto-deploy'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_deployments as $deployment): ?>
                        <tr>
                            <td><?php echo esc_html(mysql2date('M j, Y g:i a', $deployment->created_at)); ?></td>
                            <td><code><?php echo esc_html(substr($deployment->commit_hash, 0, 7)); ?></code></td>
                            <td><?php echo esc_html(wp_trim_words($deployment->commit_message, 10)); ?></td>
                            <td>
                                <span class="deployment-status status-<?php echo esc_attr($deployment->status); ?>">
                                    <?php echo esc_html(ucfirst($deployment->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ucfirst($deployment->trigger_type)); ?></td>
                            <td>
                                <?php if (in_array($deployment->status, ['pending', 'building'])): ?>
                                    <button type="button" class="button button-small cancel-deployment-btn" data-deployment-id="<?php echo esc_attr($deployment->id); ?>">
                                        <span class="dashicons dashicons-no"></span>
                                        <?php esc_html_e('Cancel', 'github-auto-deploy'); ?>
                                    </button>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>