<?php
/**
 * Settings page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap github-deploy-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- GitHub Connection Status -->
    <div class="github-connection-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0;">
        <?php if ($is_connected): ?>
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                <?php esc_html_e('Connected to GitHub', 'github-auto-deploy'); ?>
            </h2>
            <table class="form-table" style="margin-top: 0;">
                <?php if (!empty($connection_details['account_login'])): ?>
                <tr>
                    <th><?php esc_html_e('Account', 'github-auto-deploy'); ?></th>
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
                    <th><?php esc_html_e('Repository', 'github-auto-deploy'); ?></th>
                    <td><code><?php echo esc_html($connection_details['repo_full_name']); ?></code></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($connection_details['repo_branch'])): ?>
                <tr>
                    <th><?php esc_html_e('Branch', 'github-auto-deploy'); ?></th>
                    <td><code><?php echo esc_html($connection_details['repo_branch']); ?></code></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($connection_details['connected_at'])): ?>
                <tr>
                    <th><?php esc_html_e('Connected', 'github-auto-deploy'); ?></th>
                    <td><?php echo esc_html(human_time_diff(strtotime($connection_details['connected_at']), current_time('timestamp')) . ' ago'); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <p>
                <button type="button" id="disconnect-github-btn" class="button button-secondary">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php esc_html_e('Disconnect from GitHub', 'github-auto-deploy'); ?>
                </button>
                <span id="disconnect-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
            </p>
        <?php else: ?>
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php esc_html_e('Connect to GitHub', 'github-auto-deploy'); ?>
            </h2>
            <p><?php esc_html_e('Connect your WordPress site to GitHub to enable automatic theme deployments.', 'github-auto-deploy'); ?></p>
            <p><?php esc_html_e('You will be redirected to GitHub to install the WordPress Deploy app and select which repository to connect.', 'github-auto-deploy'); ?></p>
            <p>
                <button type="button" id="connect-github-btn" class="button button-primary button-hero">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Connect to GitHub', 'github-auto-deploy'); ?>
                </button>
                <span id="connect-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
            </p>
        <?php endif; ?>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('github_deploy_settings'); ?>

        <h2><?php esc_html_e('Repository Configuration', 'github-auto-deploy'); ?></h2>

        <?php
        $repo_bound = $settings->is_repo_bound();
        $has_repo_selected = !empty($current_settings['github_repo_owner']) && !empty($current_settings['github_repo_name']);
        ?>

        <?php if ($is_connected && !$repo_bound && !$has_repo_selected): ?>
            <!-- Repo Selector (shown when connected but no repo selected yet) -->
            <div id="repo-selector-section" style="background: #f0f6fc; border: 1px solid #0969da; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">
                    <span class="dashicons dashicons-admin-home"></span>
                    <?php esc_html_e('Select Repository', 'github-auto-deploy'); ?>
                </h3>
                <p><?php esc_html_e('Choose which repository to deploy from the list below. Once selected, this cannot be changed without disconnecting from GitHub.', 'github-auto-deploy'); ?></p>

                <div id="repo-selector-loading" style="padding: 20px; text-align: center;">
                    <span class="spinner is-active" style="float: none;"></span>
                    <p><?php esc_html_e('Loading repositories...', 'github-auto-deploy'); ?></p>
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
                        <option value=""><?php esc_html_e('-- Select a Repository --', 'github-auto-deploy'); ?></option>
                    </select>
                    <p>
                        <button type="button" id="bind-repo-btn" class="button button-primary" disabled>
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('Bind Repository', 'github-auto-deploy'); ?>
                        </button>
                        <span id="bind-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
                    </p>
                    <p class="description" style="color: #d63638;">
                        <strong><?php esc_html_e('Warning:', 'github-auto-deploy'); ?></strong>
                        <?php esc_html_e('Once you bind a repository, you cannot change it without disconnecting from GitHub and reconnecting.', 'github-auto-deploy'); ?>
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
                    <strong><?php esc_html_e('Repository Bound:', 'github-auto-deploy'); ?></strong>
                    <?php
                    echo sprintf(
                        esc_html__('This site is bound to %s. To change repository, you must disconnect from GitHub and reconnect.', 'github-auto-deploy'),
                        '<code>' . esc_html($current_settings['github_repo_owner'] . '/' . $current_settings['github_repo_name']) . '</code>'
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="github_repo_owner"><?php esc_html_e('Repository Owner', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="text" id="github_repo_owner" name="github_repo_owner"
                           value="<?php echo esc_attr($current_settings['github_repo_owner']); ?>"
                           class="regular-text"
                           <?php echo ($repo_bound || $has_repo_selected) ? 'readonly' : 'required'; ?>>
                    <p class="description"><?php esc_html_e('GitHub username or organization name', 'github-auto-deploy'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="github_repo_name"><?php esc_html_e('Repository Name', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="text" id="github_repo_name" name="github_repo_name"
                           value="<?php echo esc_attr($current_settings['github_repo_name']); ?>"
                           class="regular-text"
                           <?php echo ($repo_bound || $has_repo_selected) ? 'readonly' : 'required'; ?>>
                    <p class="description"><?php esc_html_e('Repository name (e.g., my-theme)', 'github-auto-deploy'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="github_branch"><?php esc_html_e('Branch', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="text" id="github_branch" name="github_branch"
                           value="<?php echo esc_attr($current_settings['github_branch']); ?>"
                           class="regular-text"
                           <?php echo ($repo_bound || $has_repo_selected) ? 'readonly' : 'required'; ?>>
                    <p class="description"><?php esc_html_e('Branch to monitor (e.g., main, master)', 'github-auto-deploy'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="github_workflow_name"><?php esc_html_e('Workflow File', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <select id="github_workflow_dropdown" name="github_workflow_name" class="regular-text" style="display: none;">
                            <option value=""><?php esc_html_e('Select a workflow...', 'github-auto-deploy'); ?></option>
                        </select>
                        <input type="text" id="github_workflow_name" name="github_workflow_name"
                               value="<?php echo esc_attr($current_settings['github_workflow_name']); ?>"
                               class="regular-text" required
                               placeholder="e.g., deploy-theme.yml">
                        <button type="button" id="load-workflows-btn" class="button button-secondary" style="display: none;">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Load Workflows', 'github-auto-deploy'); ?>
                        </button>
                        <span id="workflow-loading" class="spinner" style="float: none; margin: 0;"></span>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Select from available workflows or enter manually', 'github-auto-deploy'); ?>
                        <span id="workflow-count" style="display: none; margin-left: 10px; color: #2271b1;"></span>
                    </p>
                    <p id="workflow-error" class="description" style="display: none; color: #d63638;"></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php esc_html_e('Target Theme Directory', 'github-auto-deploy'); ?>
                </th>
                <td>
                    <p class="description">
                        <?php esc_html_e('The theme will be deployed to:', 'github-auto-deploy'); ?>
                        <br>
                        <code><?php echo esc_html(WP_CONTENT_DIR . '/themes/' . ($current_settings['github_repo_name'] ?: '[repository-name]')); ?></code>
                        <br>
                        <em><?php esc_html_e('(Automatically uses the repository name)', 'github-auto-deploy'); ?></em>
                    </p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Deployment Options', 'github-auto-deploy'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Auto Deploy', 'github-auto-deploy'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_deploy_enabled" value="1"
                               <?php checked($current_settings['auto_deploy_enabled']); ?>>
                        <?php esc_html_e('Enable automatic deployments on commit', 'github-auto-deploy'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Manual Approval', 'github-auto-deploy'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="require_manual_approval" value="1"
                               <?php checked($current_settings['require_manual_approval']); ?>>
                        <?php esc_html_e('Require manual approval before deploying', 'github-auto-deploy'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Create Backups', 'github-auto-deploy'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="create_backups" value="1"
                               <?php checked($current_settings['create_backups']); ?>>
                        <?php esc_html_e('Create backup before each deployment', 'github-auto-deploy'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="notification_email"><?php esc_html_e('Notification Email', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="email" id="notification_email" name="notification_email"
                           value="<?php echo esc_attr($current_settings['notification_email']); ?>"
                           class="regular-text">
                    <p class="description"><?php esc_html_e('Email address for deployment notifications (optional)', 'github-auto-deploy'); ?></p>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Developer Options', 'github-auto-deploy'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Debug Mode', 'github-auto-deploy'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="debug_mode" value="1"
                               <?php checked($current_settings['debug_mode']); ?>>
                        <?php esc_html_e('Enable debug logging', 'github-auto-deploy'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Logs all API requests, responses, and deployment steps to ', 'github-auto-deploy'); ?>
                        <code>wp-content/github-deploy-debug.log</code>.
                        <?php esc_html_e('View logs in ', 'github-auto-deploy'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-logs')); ?>">
                            <?php esc_html_e('Debug Logs', 'github-auto-deploy'); ?>
                        </a>.
                        <br>
                        <strong><?php esc_html_e('Note:', 'github-auto-deploy'); ?></strong>
                        <?php esc_html_e('Debug logs may contain sensitive information. Disable when not needed.', 'github-auto-deploy'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="github_deploy_save_settings" class="button button-primary">
                <?php esc_html_e('Save Settings', 'github-auto-deploy'); ?>
            </button>
            <button type="button" id="test-connection-btn" class="button">
                <?php esc_html_e('Test Connection', 'github-auto-deploy'); ?>
            </button>
        </p>
    </form>

    <div id="connection-result" style="margin-top: 20px;"></div>

    <!-- Danger Zone -->
    <div class="github-deploy-danger-zone" style="background: #fff; border: 2px solid #d63638; border-radius: 4px; padding: 20px; margin: 30px 0 20px 0;">
        <h2 style="margin-top: 0; color: #d63638;">
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e('Danger Zone', 'github-auto-deploy'); ?>
        </h2>
        <p><?php esc_html_e('These actions are irreversible and will permanently delete data.', 'github-auto-deploy'); ?></p>

        <div style="background: #fcf0f1; border-left: 4px solid #d63638; padding: 12px; margin: 12px 0;">
            <h3 style="margin: 0 0 8px 0;"><?php esc_html_e('Reset All Plugin Data', 'github-auto-deploy'); ?></h3>
            <p style="margin: 0 0 12px 0;">
                <?php esc_html_e('This will permanently delete:', 'github-auto-deploy'); ?>
            </p>
            <ul style="margin: 0 0 12px 20px;">
                <li><?php esc_html_e('GitHub connection and API credentials', 'github-auto-deploy'); ?></li>
                <li><?php esc_html_e('All deployment history and logs', 'github-auto-deploy'); ?></li>
                <li><?php esc_html_e('All theme backup files', 'github-auto-deploy'); ?></li>
                <li><?php esc_html_e('All plugin settings', 'github-auto-deploy'); ?></li>
                <li><?php esc_html_e('Backend server data (webhooks, installation)', 'github-auto-deploy'); ?></li>
            </ul>
            <p style="margin: 0 0 12px 0; color: #d63638;">
                <strong><?php esc_html_e('Warning:', 'github-auto-deploy'); ?></strong>
                <?php esc_html_e('This action cannot be undone. You will need to reconnect to GitHub and reconfigure all settings.', 'github-auto-deploy'); ?>
            </p>
            <button type="button" id="reset-all-data-btn" class="button button-secondary" style="background: #d63638; border-color: #d63638; color: #fff;">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Reset All Plugin Data', 'github-auto-deploy'); ?>
            </button>
            <span id="reset-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
        </div>
    </div>
</div>
