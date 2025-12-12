<?php

/**
 * Settings page template
 * Deploy Forge Platform Integration
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap deploy-forge-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Deploy Forge Connection Status -->
    <div class="deploy-forge-connection-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
        <?php if ($is_connected): ?>
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php esc_html_e('Connected to Deploy Forge', 'deploy-forge'); ?>
            </h2>
            <table class="form-table" style="margin-top: 0;">
                <?php if (!empty($connection_data['domain'])): ?>
                    <tr>
                        <th><?php esc_html_e('Site Domain', 'deploy-forge'); ?></th>
                        <td><strong><?php echo esc_html($connection_data['domain']); ?></strong></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($connection_data['repo_owner']) && !empty($connection_data['repo_name'])): ?>
                    <tr>
                        <th><?php esc_html_e('Repository', 'deploy-forge'); ?></th>
                        <td><code><?php echo esc_html($connection_data['repo_owner'] . '/' . $connection_data['repo_name']); ?></code></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($connection_data['repo_branch'])): ?>
                    <tr>
                        <th><?php esc_html_e('Branch', 'deploy-forge'); ?></th>
                        <td><code><?php echo esc_html($connection_data['repo_branch']); ?></code></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($connection_data['deployment_method'])): ?>
                    <tr>
                        <th><?php esc_html_e('Deployment Method', 'deploy-forge'); ?></th>
                        <td>
                            <?php
                            echo $connection_data['deployment_method'] === 'github_actions'
                                ? esc_html__('GitHub Actions', 'deploy-forge')
                                : esc_html__('Direct Clone', 'deploy-forge');
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($connection_data['workflow_path'])): ?>
                    <tr>
                        <th><?php esc_html_e('Workflow File', 'deploy-forge'); ?></th>
                        <td><code><?php echo esc_html($connection_data['workflow_path']); ?></code></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($connection_data['connected_at'])): ?>
                    <tr>
                        <th><?php esc_html_e('Connected', 'deploy-forge'); ?></th>
                        <td><?php echo esc_html(human_time_diff(strtotime($connection_data['connected_at']), current_time('timestamp')) . ' ago'); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
            <p style="background: #f0f6fc; border-left: 4px solid #0969da; padding: 12px; margin: 16px 0;">
                <strong><?php esc_html_e('Note:', 'deploy-forge'); ?></strong>
                <?php esc_html_e('Repository configuration is managed on the Deploy Forge platform. To change your repository or deployment method, disconnect and reconnect.', 'deploy-forge'); ?>
            </p>
            <p>
                <button type="button" id="disconnect-btn" class="button button-secondary">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e('Disconnect from Deploy Forge', 'deploy-forge'); ?>
                </button>
                <span id="disconnect-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
            </p>
        <?php else: ?>
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php esc_html_e('Connect to Deploy Forge', 'deploy-forge'); ?>
            </h2>
            <p><?php esc_html_e('Connect your WordPress site to the Deploy Forge platform to enable automatic theme deployments from GitHub.', 'deploy-forge'); ?></p>
            <p><?php esc_html_e('The Deploy Forge platform will guide you through connecting your GitHub account and selecting a repository.', 'deploy-forge'); ?></p>
            <p>
                <button type="button" id="connect-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Connect to Deploy Forge', 'deploy-forge'); ?>
                </button>
                <span id="connect-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($is_connected): ?>
        <form method="post" action="">
            <?php wp_nonce_field('deploy_forge_settings'); ?>

            <h2><?php esc_html_e('Deployment Options', 'deploy-forge'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Manual Approval', 'deploy-forge'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="require_manual_approval" value="1" <?php checked($current_settings['require_manual_approval']); ?>>
                            <?php esc_html_e('Require manual approval before deploying', 'deploy-forge'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When checked, new commits will show as pending and require approval. When unchecked, deployments happen automatically.', 'deploy-forge'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Create Backups', 'deploy-forge'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="create_backups" value="1" <?php checked($current_settings['create_backups']); ?>>
                            <?php esc_html_e('Create a backup before each deployment (recommended)', 'deploy-forge'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification_email"><?php esc_html_e('Notification Email', 'deploy-forge'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="notification_email" id="notification_email" value="<?php echo esc_attr($current_settings['notification_email']); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Receive deployment notifications at this email address', 'deploy-forge'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Debug Mode', 'deploy-forge'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="debug_mode" value="1" <?php checked($current_settings['debug_mode']); ?>>
                            <?php esc_html_e('Enable detailed logging for troubleshooting', 'deploy-forge'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Webhook Configuration', 'deploy-forge'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Webhook URL', 'deploy-forge'); ?></th>
                    <td>
                        <input type="text" value="<?php echo esc_attr($webhook_url); ?>" class="large-text code" readonly onclick="this.select()">
                        <p class="description">
                            <?php esc_html_e('This URL is automatically configured by Deploy Forge platform. No manual setup required.', 'deploy-forge'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="deploy_forge_save_settings" class="button button-primary">
                    <?php esc_html_e('Save Settings', 'deploy-forge'); ?>
                </button>
            </p>
        </form>
    <?php else: ?>
        <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
            <h2><?php esc_html_e('Next Steps', 'deploy-forge'); ?></h2>
            <ol>
                <li><?php esc_html_e('Click "Connect to Deploy Forge" above to begin setup', 'deploy-forge'); ?></li>
                <li><?php esc_html_e('Authenticate with GitHub and select your repository', 'deploy-forge'); ?></li>
                <li><?php esc_html_e('Configure your deployment method and workflow', 'deploy-forge'); ?></li>
                <li><?php esc_html_e('Return here to configure deployment options', 'deploy-forge'); ?></li>
            </ol>
        </div>
    <?php endif; ?>
</div>
