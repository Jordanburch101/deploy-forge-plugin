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

    <form method="post" action="">
        <?php wp_nonce_field('github_deploy_settings'); ?>

        <?php
        $has_token = !empty($this->settings->get_github_token());
        ?>

        <h2><?php esc_html_e('GitHub Repository', 'github-auto-deploy'); ?></h2>

        <?php if ($has_token): ?>
            <!-- Repository Selector (shown when token exists) -->
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="repo-selector"><?php esc_html_e('Select Repository', 'github-auto-deploy'); ?></label>
                    </th>
                    <td>
                        <div class="repo-selector-wrapper">
                            <select id="repo-selector" class="regular-text" style="min-width: 400px;">
                                <option value=""><?php esc_html_e('-- Select a repository --', 'github-auto-deploy'); ?></option>
                            </select>
                            <button type="button" id="load-repos-btn" class="button">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e('Load Repositories', 'github-auto-deploy'); ?>
                            </button>
                            <span id="repo-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Click "Load Repositories" to fetch your GitHub repos, then select one from the dropdown.', 'github-auto-deploy'); ?>
                        </p>
                    </td>
                </tr>

                <tr id="workflow-selector-row" style="display: none;">
                    <th scope="row">
                        <label for="workflow-selector"><?php esc_html_e('Select Workflow', 'github-auto-deploy'); ?></label>
                    </th>
                    <td>
                        <div class="workflow-selector-wrapper">
                            <select id="workflow-selector" class="regular-text" style="min-width: 400px;">
                                <option value=""><?php esc_html_e('-- Select a workflow --', 'github-auto-deploy'); ?></option>
                            </select>
                            <span id="workflow-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Select the GitHub Actions workflow file to use for deployments.', 'github-auto-deploy'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <details class="manual-entry-toggle">
                <summary style="cursor: pointer; font-weight: 600; margin: 20px 0;">
                    <?php esc_html_e('Or enter repository details manually', 'github-auto-deploy'); ?>
                </summary>
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="github_repo_owner"><?php esc_html_e('Repository Owner', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="text" id="github_repo_owner" name="github_repo_owner"
                           value="<?php echo esc_attr($current_settings['github_repo_owner']); ?>"
                           class="regular-text" required>
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
                           class="regular-text" required>
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
                           class="regular-text" required>
                    <p class="description"><?php esc_html_e('Branch to monitor (e.g., main, master)', 'github-auto-deploy'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="github_workflow_name"><?php esc_html_e('Workflow File Name', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="text" id="github_workflow_name" name="github_workflow_name"
                           value="<?php echo esc_attr($current_settings['github_workflow_name']); ?>"
                           class="regular-text" required>
                    <p class="description"><?php esc_html_e('GitHub Actions workflow file (e.g., build-theme.yml)', 'github-auto-deploy'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="github_token"><?php esc_html_e('Personal Access Token', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="password" id="github_token" name="github_token"
                           value="" placeholder="<?php echo $this->settings->get_github_token() ? '••••••••••••••••' : ''; ?>"
                           class="regular-text">
                    <p class="description">
                        <?php esc_html_e('GitHub Personal Access Token with repo and actions scopes.', 'github-auto-deploy'); ?>
                        <a href="https://github.com/settings/tokens/new" target="_blank"><?php esc_html_e('Create token', 'github-auto-deploy'); ?></a>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="target_theme_directory"><?php esc_html_e('Target Theme Directory', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="text" id="target_theme_directory" name="target_theme_directory"
                           value="<?php echo esc_attr($current_settings['target_theme_directory']); ?>"
                           class="regular-text" required>
                    <p class="description">
                        <?php esc_html_e('Theme folder name in wp-content/themes/', 'github-auto-deploy'); ?>
                        <br>
                        <strong><?php esc_html_e('Full path:', 'github-auto-deploy'); ?></strong>
                        <code><?php echo esc_html(WP_CONTENT_DIR . '/themes/' . ($current_settings['target_theme_directory'] ?: '[theme-name]')); ?></code>
                    </p>
                </td>
            </tr>
        </table>

        <?php if ($has_token): ?>
            </details>
        <?php endif; ?>

        <h2><?php esc_html_e('Webhook Configuration', 'github-auto-deploy'); ?></h2>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="webhook_url"><?php esc_html_e('Webhook URL', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="text" id="webhook_url" value="<?php echo esc_attr($webhook_url); ?>"
                           class="regular-text" readonly>
                    <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">
                        <?php esc_html_e('Copy', 'github-auto-deploy'); ?>
                    </button>
                    <p class="description"><?php esc_html_e('Add this URL to your GitHub repository webhook settings', 'github-auto-deploy'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="webhook_secret"><?php esc_html_e('Webhook Secret', 'github-auto-deploy'); ?></label>
                </th>
                <td>
                    <input type="text" id="webhook_secret" name="webhook_secret"
                           value="<?php echo esc_attr($current_settings['webhook_secret']); ?>"
                           class="regular-text" readonly>
                    <p class="description">
                        <?php esc_html_e('Use this secret in GitHub webhook configuration for security', 'github-auto-deploy'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"></th>
                <td>
                    <button type="button" id="generate-secret-btn" class="button">
                        <?php esc_html_e('Generate New Secret', 'github-auto-deploy'); ?>
                    </button>
                    <span id="secret-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
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
</div>
