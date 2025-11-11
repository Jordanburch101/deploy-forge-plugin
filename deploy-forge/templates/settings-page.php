<?php

/**
 * Settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap deploy-forge-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- GitHub Connection Status -->
    <div class="github-connection-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
        <?php if ($is_connected): ?>
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php esc_html_e('Connected to GitHub', 'deploy-forge'); ?>
            </h2>
            <table class="form-table" style="margin-top: 0;">
                <?php if (!empty($connection_details['account_login'])): ?>
                    <tr>
                        <th><?php esc_html_e('Account', 'deploy-forge'); ?></th>
                        <td>
                            <strong><?php echo esc_html($connection_details['account_login']); ?></strong>
                            <?php if (!empty($connection_details['account_avatar'])): ?>
                                <img src="<?php echo esc_url($connection_details['account_avatar']); ?>"
                                    alt="<?php echo esc_attr($connection_details['account_login']); ?>"
                                    style="width: 24px; height: 24px; border-radius: 50%; vertical-align: middle; margin-left: 8px;">
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($connection_details['repo_full_name'])): ?>
                    <tr>
                        <th><?php esc_html_e('Repository', 'deploy-forge'); ?></th>
                        <td><code><?php echo esc_html($connection_details['repo_full_name']); ?></code></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($connection_details['repo_branch'])): ?>
                    <tr>
                        <th><?php esc_html_e('Branch', 'deploy-forge'); ?></th>
                        <td><code><?php echo esc_html($connection_details['repo_branch']); ?></code></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($connection_details['connected_at'])): ?>
                    <tr>
                        <th><?php esc_html_e('Connected', 'deploy-forge'); ?></th>
                        <td><?php echo esc_html(human_time_diff(strtotime($connection_details['connected_at']), current_time('timestamp')) . ' ago'); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
            <p>
                <button type="button" id="disconnect-github-btn" class="button button-secondary">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e('Disconnect from GitHub', 'deploy-forge'); ?>
                </button>
                <span id="disconnect-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
            </p>
        <?php else: ?>
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php esc_html_e('Connect to GitHub', 'deploy-forge'); ?>
            </h2>
            <p><?php esc_html_e('Connect your WordPress site to GitHub to enable automatic theme deployments.', 'deploy-forge'); ?></p>
            <p><?php esc_html_e('You will be redirected to GitHub to install the WordPress Deploy app and select which repository to connect.', 'deploy-forge'); ?></p>
            <p>
                <button type="button" id="connect-github-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Connect to GitHub', 'deploy-forge'); ?>
                </button>
                <span id="connect-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
            </p>
        <?php endif; ?>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('deploy_forge_settings'); ?>

        <h2><?php esc_html_e('Repository Configuration', 'deploy-forge'); ?></h2>

        <?php
        $repo_bound = $settings->is_repo_bound();
        $has_repo_selected = !empty($current_settings['github_repo_owner']) && !empty($current_settings['github_repo_name']);
        ?>

        <?php if ($is_connected && !$repo_bound && !$has_repo_selected): ?>
            <!-- Repo Selector (shown when connected but no repo selected yet) -->
            <div id="repo-selector-section" style="background: #f0f6fc; border: 1px solid #0969da; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">
                    <span class="dashicons dashicons-admin-home"></span>
                    <?php esc_html_e('Select Repository', 'deploy-forge'); ?>
                </h3>
                <p><?php esc_html_e('Choose which repository to deploy from the list below. Once selected, this cannot be changed without disconnecting from GitHub.', 'deploy-forge'); ?></p>

                <div id="repo-selector-loading" style="padding: 20px; text-align: center;">
                    <span class="spinner is-active" style="float: none;"></span>
                    <p><?php esc_html_e('Loading repositories...', 'deploy-forge'); ?></p>
                    <p style="font-size: 12px; color: #666;">
                        <?php
                        echo 'Debug: Connected=' . ($is_connected ? 'Yes' : 'No') . ', ';
                        echo 'Bound=' . ($repo_bound ? 'Yes' : 'No') . ', ';
                        echo 'Has Repo=' . ($has_repo_selected ? 'Yes' : 'No');
                        ?>
                    </p>
                </div>

                <div id="repo-selector-list" style="display: none;">
                    <select id="repo-select" class="regular-text" style="width: 100%; max-width: 600px; font-size: 14px;">
                        <option value=""><?php esc_html_e('-- Select a Repository --', 'deploy-forge'); ?></option>
                    </select>
                    <p>
                        <button type="button" id="bind-repo-btn" class="button button-primary" disabled>
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('Bind Repository', 'deploy-forge'); ?>
                        </button>
                        <span id="bind-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
                    </p>
                    <p class="description" style="color: #d63638;">
                        <strong><?php esc_html_e('Warning:', 'deploy-forge'); ?></strong>
                        <?php esc_html_e('Once you bind a repository, you cannot change it without disconnecting from GitHub and reconnecting.', 'deploy-forge'); ?>
                    </p>
                </div>

                <div id="repo-selector-error" style="display: none; padding: 12px; background: #fcf0f1; border-left: 4px solid #d63638; margin-top: 12px;">
                    <p id="repo-selector-error-message" style="margin: 0; color: #d63638;"></p>
                </div>
            </div>
        <?php elseif ($is_connected && ($repo_bound || $has_repo_selected)): ?>
            <div style="background: #d5f5e3; border: 1px solid #27ae60; border-radius: 4px; padding: 12px; margin-bottom: 20px;">
                <p style="margin: 0;">
                    <span class="dashicons dashicons-lock" style="color: #27ae60;"></span>
                    <strong><?php esc_html_e('Repository Bound:', 'deploy-forge'); ?></strong>
                    <?php
                    echo sprintf(
                        esc_html__('This site is bound to %s. To change repository, you must disconnect from GitHub and reconnect.', 'deploy-forge'),
                        '<code>' . esc_html($current_settings['github_repo_owner'] . '/' . $current_settings['github_repo_name']) . '</code>'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="github_repo_owner"><?php esc_html_e('Repository Owner', 'deploy-forge'); ?></label>
                </th>
                <td>
                    <input type="text" id="github_repo_owner" name="github_repo_owner"
                        value="<?php echo esc_attr($current_settings['github_repo_owner']); ?>"
                        class="regular-text"
                        <?php echo ($repo_bound || $has_repo_selected) ? 'readonly' : 'required'; ?>>
                    <p class="description"><?php esc_html_e('GitHub username or organization name', 'deploy-forge'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="github_repo_name"><?php esc_html_e('Repository Name', 'deploy-forge'); ?></label>
                </th>
                <td>
                    <input type="text" id="github_repo_name" name="github_repo_name"
                        value="<?php echo esc_attr($current_settings['github_repo_name']); ?>"
                        class="regular-text"
                        <?php echo ($repo_bound || $has_repo_selected) ? 'readonly' : 'required'; ?>>
                    <p class="description"><?php esc_html_e('Repository name (e.g., my-theme)', 'deploy-forge'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="github_branch"><?php esc_html_e('Branch', 'deploy-forge'); ?></label>
                </th>
                <td>
                    <input type="text" id="github_branch" name="github_branch"
                        value="<?php echo esc_attr($current_settings['github_branch']); ?>"
                        class="regular-text"
                        <?php echo ($repo_bound || $has_repo_selected) ? 'readonly' : 'required'; ?>>
                    <p class="description"><?php esc_html_e('Branch to monitor (e.g., main, master)', 'deploy-forge'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="deployment_method"><?php esc_html_e('Deployment Method', 'deploy-forge'); ?></label>
                </th>
                <td>
                    <select id="deployment_method" name="deployment_method" class="regular-text">
                        <option value="github_actions" <?php selected($current_settings['deployment_method'], 'github_actions'); ?>>
                            <?php esc_html_e('GitHub Actions (Build + Deploy)', 'deploy-forge'); ?>
                        </option>
                        <option value="direct_clone" <?php selected($current_settings['deployment_method'], 'direct_clone'); ?>>
                            <?php esc_html_e('Direct Clone (No Build)', 'deploy-forge'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <strong><?php esc_html_e('GitHub Actions:', 'deploy-forge'); ?></strong>
                        <?php esc_html_e('Uses GitHub workflow to build assets (webpack, npm, etc.) before deploying. Ideal for themes with build processes.', 'deploy-forge'); ?>
                        <br>
                        <strong><?php esc_html_e('Direct Clone:', 'deploy-forge'); ?></strong>
                        <?php esc_html_e('Downloads repository directly without building. Perfect for simple themes using plain CSS/JS.', 'deploy-forge'); ?>
                    </p>
                </td>
            </tr>

            <tr id="workflow-row">
                <th scope="row">
                    <label for="github_workflow_name"><?php esc_html_e('Workflow File', 'deploy-forge'); ?></label>
                </th>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <select id="github_workflow_dropdown" name="github_workflow_name" class="regular-text" style="display: none;">
                            <option value=""><?php esc_html_e('Select a workflow...', 'deploy-forge'); ?></option>
                        </select>
                        <input type="text" id="github_workflow_name" name="github_workflow_name"
                            value="<?php echo esc_attr($current_settings['github_workflow_name']); ?>"
                            class="regular-text" required
                            placeholder="e.g., deploy-theme.yml">
                        <button type="button" id="load-workflows-btn" class="button button-secondary" style="display: none;">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Load Workflows', 'deploy-forge'); ?>
                        </button>
                        <span id="workflow-loading" class="spinner" style="float: none; margin: 0;"></span>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Select from available workflows or enter manually', 'deploy-forge'); ?>
                        <span id="workflow-count" style="display: none; margin-left: 10px; color: #2271b1;"></span>
                    </p>
                    <p id="workflow-error" class="description" style="display: none; color: #d63638;"></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Target Theme Directory', 'deploy-forge'); ?>
                </th>
                <td>
                    <p class="description">
                        <?php esc_html_e('The theme will be deployed to:', 'deploy-forge'); ?>
                        <br>
                        <code><?php echo esc_html(WP_CONTENT_DIR . '/themes/' . ($current_settings['github_repo_name'] ?: '[repository-name]')); ?></code>
                        <br>
                        <em><?php esc_html_e('(Automatically uses the repository name)', 'deploy-forge'); ?></em>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Deployment Options', 'deploy-forge'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Auto Deploy', 'deploy-forge'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_deploy_enabled" value="1"
                            <?php checked($current_settings['auto_deploy_enabled']); ?>>
                        <?php esc_html_e('Enable automatic deployments on commit', 'deploy-forge'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Manual Approval', 'deploy-forge'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="require_manual_approval" value="1"
                            <?php checked($current_settings['require_manual_approval']); ?>>
                        <?php esc_html_e('Require manual approval before deploying', 'deploy-forge'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Create Backups', 'deploy-forge'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="create_backups" value="1"
                            <?php checked($current_settings['create_backups']); ?>>
                        <?php esc_html_e('Create backup before each deployment', 'deploy-forge'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="notification_email"><?php esc_html_e('Notification Email', 'deploy-forge'); ?></label>
                </th>
                <td>
                    <input type="email" id="notification_email" name="notification_email"
                        value="<?php echo esc_attr($current_settings['notification_email']); ?>"
                        class="regular-text">
                    <p class="description"><?php esc_html_e('Email address for deployment notifications (optional)', 'deploy-forge'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Developer Options', 'deploy-forge'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Debug Mode', 'deploy-forge'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="debug_mode" value="1"
                            <?php checked($current_settings['debug_mode']); ?>>
                        <?php esc_html_e('Enable debug logging', 'deploy-forge'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Logs all API requests, responses, and deployment steps to ', 'deploy-forge'); ?>
                        <code>wp-content/deploy-forge-debug.log</code>.
                        <?php esc_html_e('View logs in ', 'deploy-forge'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=deploy-forge-logs')); ?>">
                            <?php esc_html_e('Debug Logs', 'deploy-forge'); ?>
                        </a>.
                        <br>
                        <strong><?php esc_html_e('Note:', 'deploy-forge'); ?></strong>
                        <?php esc_html_e('Debug logs may contain sensitive information. Disable when not needed.', 'deploy-forge'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="deploy_forge_save_settings" class="button button-primary">
                <?php esc_html_e('Save Settings', 'deploy-forge'); ?>
            </button>
            <button type="button" id="test-connection-btn" class="button">
                <?php esc_html_e('Test Connection', 'deploy-forge'); ?>
            </button>
        </p>
    </form>

    <div id="connection-result" style="margin-top: 20px;"></div>

    <!-- Danger Zone -->
    <div class="deploy-forge-danger-zone" style="background: #fff; border: 2px solid #d63638; border-radius: 4px; padding: 20px; margin: 30px 0 20px 0;">
        <h2 style="margin-top: 0; color: #d63638;">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e('Danger Zone', 'deploy-forge'); ?>
        </h2>
        <p><?php esc_html_e('These actions are irreversible and will permanently delete data.', 'deploy-forge'); ?></p>

        <div style="background: #fcf0f1; border-left: 4px solid #d63638; padding: 12px; margin: 12px 0;">
            <h3 style="margin: 0 0 8px 0;"><?php esc_html_e('Reset All Plugin Data', 'deploy-forge'); ?></h3>
            <p style="margin: 0 0 12px 0;">
                <?php esc_html_e('This will permanently delete:', 'deploy-forge'); ?>
            </p>
            <ul style="margin: 0 0 12px 20px;">
                <li><?php esc_html_e('GitHub connection and API credentials', 'deploy-forge'); ?></li>
                <li><?php esc_html_e('All deployment history and logs', 'deploy-forge'); ?></li>
                <li><?php esc_html_e('All theme backup files', 'deploy-forge'); ?></li>
                <li><?php esc_html_e('All plugin settings', 'deploy-forge'); ?></li>
                <li><?php esc_html_e('Backend server data (webhooks, installation)', 'deploy-forge'); ?></li>
            </ul>
            <p style="margin: 0 0 12px 0; color: #d63638;">
                <strong><?php esc_html_e('Warning:', 'deploy-forge'); ?></strong>
                <?php esc_html_e('This action cannot be undone. You will need to reconnect to GitHub and reconfigure all settings.', 'deploy-forge'); ?>
            </p>
            <button type="button" id="reset-all-data-btn" class="button button-secondary" style="background: #d63638; border-color: #d63638; color: #fff;">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Reset All Plugin Data', 'deploy-forge'); ?>
            </button>
            <span id="reset-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
        </div>
    </div>
</div>