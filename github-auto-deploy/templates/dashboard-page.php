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
        <div class="github-deploy-welcome-banner">
            <div class="github-deploy-welcome-content">
                <h2 class="github-deploy-welcome-title"><?php esc_html_e('Welcome to GitHub Auto Deploy', 'github-auto-deploy'); ?></h2>
                <p class="github-deploy-welcome-description">
                    <?php esc_html_e('Get started by running the setup wizard to connect your GitHub repository and configure automatic deployments.', 'github-auto-deploy'); ?>
                </p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-wizard')); ?>" class="github-deploy-welcome-button">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Start Setup Wizard', 'github-auto-deploy'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Connection & Controls -->
    <div class="github-deploy-header">
        <div class="github-deploy-header-content">
            <h2><?php esc_html_e('Connection & Controls', 'github-auto-deploy'); ?></h2>
            <div class="github-deploy-header-body">
                <div class="github-deploy-connection-info">
                    <?php if ($is_configured): ?>
                        <p class="status-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Connected to GitHub', 'github-auto-deploy'); ?>
                        </p>
                        <p>
                            <strong><?php esc_html_e('Branch:', 'github-auto-deploy'); ?></strong>
                            <?php echo esc_html($this->settings->get('github_branch')); ?>
                        </p>
                        <?php if ($stats['last_deployment']): ?>
                            <p>
                                <strong><?php esc_html_e('Last Deployment:', 'github-auto-deploy'); ?></strong>
                                <?php echo esc_html(mysql2date('M j, g:i A', $stats['last_deployment']->deployed_at)); ?>
                                |
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
                <div class="github-deploy-header-actions">
                    <button type="button" class="button button-primary button-large" id="deploy-now-btn" <?php echo !$is_configured ? 'disabled' : ''; ?>>
                        <?php esc_html_e('Deploy Now', 'github-auto-deploy'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="github-deploy-stats-section">
        <h2><?php esc_html_e('Stats', 'github-auto-deploy'); ?></h2>
        <div class="github-deploy-stats">
            <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-history')); ?>" class="stat-item stat-clickable">
                <div class="stat-number"><?php echo esc_html($stats['total']); ?></div>
                <div class="stat-label"><?php esc_html_e('Total', 'github-auto-deploy'); ?></div>
                <div class="stat-action"><?php esc_html_e('click to view', 'github-auto-deploy'); ?></div>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-history&status=success')); ?>" class="stat-item stat-clickable stat-success">
                <div class="stat-number"><?php echo esc_html($stats['success']); ?></div>
                <div class="stat-label"><?php esc_html_e('Successful', 'github-auto-deploy'); ?></div>
                <div class="stat-action"><?php esc_html_e('click to view', 'github-auto-deploy'); ?></div>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-history&status=failed')); ?>" class="stat-item stat-clickable stat-failed">
                <div class="stat-number"><?php echo esc_html($stats['failed']); ?></div>
                <div class="stat-label"><?php esc_html_e('Failed', 'github-auto-deploy'); ?></div>
                <div class="stat-action"><?php esc_html_e('view log', 'github-auto-deploy'); ?></div>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-history&status=pending,building')); ?>" class="stat-item stat-clickable stat-pending">
                <div class="stat-number"><?php echo esc_html($stats['pending'] + $stats['building']); ?></div>
                <div class="stat-label"><?php esc_html_e('Pending', 'github-auto-deploy'); ?></div>
                <div class="stat-action"><?php esc_html_e('details', 'github-auto-deploy'); ?></div>
            </a>
        </div>
    </div>

    <!-- Latest Deployment Summary -->
    <?php if ($stats['last_deployment']): ?>
        <div class="github-deploy-latest-summary">
            <h2><?php esc_html_e('Latest Deployment Summary', 'github-auto-deploy'); ?></h2>
            <div class="github-deploy-summary-content">
                <div class="github-deploy-summary-details">
                    <p>
                        <strong><?php esc_html_e('Commit:', 'github-auto-deploy'); ?></strong>
                        <code><?php echo esc_html(substr($stats['last_deployment']->commit_hash, 0, 7)); ?></code>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Message:', 'github-auto-deploy'); ?></strong>
                        <?php echo esc_html($stats['last_deployment']->commit_message); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Deployed at:', 'github-auto-deploy'); ?></strong>
                        <?php echo esc_html(mysql2date('M j, g:i A', $stats['last_deployment']->deployed_at)); ?>
                    </p>
                </div>
                <div class="github-deploy-summary-meta">
                    <div class="github-deploy-summary-status">
                        <span class="deployment-status status-<?php echo esc_attr($stats['last_deployment']->status); ?>">
                            <?php echo esc_html(ucfirst($stats['last_deployment']->status)); ?>
                        </span>
                    </div>
                    <p>
                        <strong><?php esc_html_e('Triggered by:', 'github-auto-deploy'); ?></strong>
                        <?php echo esc_html(ucfirst($stats['last_deployment']->trigger_type)); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Deployments -->
    <div class="github-deploy-recent">
        <h2><?php esc_html_e('Recent Deployments', 'github-auto-deploy'); ?></h2>
        <?php if (empty($recent_deployments)): ?>
            <p><?php esc_html_e('No deployments yet.', 'github-auto-deploy'); ?></p>
        <?php else: ?>
            <div class="github-deploy-table-controls">
                <input type="text" id="deployment-search" class="github-deploy-search-input" placeholder="<?php esc_attr_e('Search deployments', 'github-auto-deploy'); ?>" />
                <select id="deployment-status-filter" class="github-deploy-status-filter">
                    <option value=""><?php esc_html_e('Filter: Status', 'github-auto-deploy'); ?></option>
                    <option value="success"><?php esc_html_e('Success', 'github-auto-deploy'); ?></option>
                    <option value="failed"><?php esc_html_e('Failed', 'github-auto-deploy'); ?></option>
                    <option value="pending"><?php esc_html_e('Pending', 'github-auto-deploy'); ?></option>
                    <option value="building"><?php esc_html_e('Building', 'github-auto-deploy'); ?></option>
                    <option value="rolled_back"><?php esc_html_e('Rolled Back', 'github-auto-deploy'); ?></option>
                    <option value="cancelled"><?php esc_html_e('Cancelled', 'github-auto-deploy'); ?></option>
                </select>
            </div>
            <table class="wp-list-table widefat fixed striped" id="deployments-table">
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
                        <tr data-status="<?php echo esc_attr($deployment->status); ?>">
                            <td><?php echo esc_html(mysql2date('M j, Y', $deployment->created_at)); ?></td>
                            <td><code><?php echo esc_html(substr($deployment->commit_hash, 0, 7)); ?></code></td>
                            <td><?php echo esc_html(wp_trim_words($deployment->commit_message, 8)); ?></td>
                            <td>
                                <span class="deployment-status status-<?php echo esc_attr($deployment->status); ?>">
                                    <?php echo esc_html(strtoupper($deployment->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ucfirst($deployment->trigger_type)); ?></td>
                            <td>
                                <?php if ($deployment->status === 'pending'): ?>
                                    <button type="button" class="button button-primary button-small approve-deployment-btn" data-deployment-id="<?php echo esc_attr($deployment->id); ?>">
                                        <?php esc_html_e('Deploy', 'github-auto-deploy'); ?>
                                    </button>
                                    <button type="button" class="button button-small cancel-deployment-btn" data-deployment-id="<?php echo esc_attr($deployment->id); ?>">
                                        <?php esc_html_e('Cancel', 'github-auto-deploy'); ?>
                                    </button>
                                <?php elseif ($deployment->status === 'building'): ?>
                                    <button type="button" class="button button-small cancel-deployment-btn" data-deployment-id="<?php echo esc_attr($deployment->id); ?>">
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