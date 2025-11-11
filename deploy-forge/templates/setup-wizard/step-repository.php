<?php
/**
 * Step 3: Select Repository
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wizard-repository">
    <h2 class="wizard-step-title">
        <?php esc_html_e('Select Your Theme Repository', 'deploy-forge'); ?>
    </h2>

    <p class="wizard-step-description">
        <?php esc_html_e('Choose the GitHub repository that contains your WordPress theme.', 'deploy-forge'); ?>
    </p>

    <div class="wizard-form-group">
        <label class="wizard-form-label" for="repository-select">
            <?php esc_html_e('Repository', 'deploy-forge'); ?>
        </label>
        <select id="repository-select" class="wizard-select">
            <option value=""><?php esc_html_e('Loading repositories...', 'deploy-forge'); ?></option>
        </select>
        <span class="repo-loading wizard-loading" style="display: none;"></span>
        <p class="wizard-form-description">
            <?php esc_html_e('Select the repository containing your WordPress theme code.', 'deploy-forge'); ?>
        </p>
    </div>

    <!-- Bind Repository Button (shown after repo selection) -->
    <div id="bind-repo-section" style="display: none; margin-bottom: 20px;">
        <button type="button" id="bind-repo-btn" class="button button-primary">
            <span class="dashicons dashicons-lock" style="margin-top: 3px;"></span>
            <?php esc_html_e('Bind Repository', 'deploy-forge'); ?>
        </button>
        <span id="bind-loading" class="spinner" style="float: none; margin: 0 0 0 10px;"></span>
        <p class="wizard-form-description" style="color: #d63638; margin-top: 10px;">
            <strong><?php esc_html_e('Warning:', 'deploy-forge'); ?></strong>
            <?php esc_html_e('Once you bind a repository, you cannot change it without restarting the setup wizard.', 'deploy-forge'); ?>
        </p>
    </div>

    <!-- Amber Warning (shown after binding) -->
    <div id="repo-bound-warning" style="display: none; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin-bottom: 20px;">
        <p style="margin: 0; color: #856404;">
            <span class="dashicons dashicons-warning" style="color: #ffc107; vertical-align: middle;"></span>
            <strong><?php esc_html_e('Repository Bound:', 'deploy-forge'); ?></strong>
            <?php esc_html_e('You have bound a repository. If you wish to change it, you need to restart the setup wizard (this will disconnect and start over).', 'deploy-forge'); ?>
        </p>
    </div>

    <!-- Branch Selector (shown only after binding) -->
    <div id="branch-section" style="display: none;">
        <div class="wizard-form-group">
            <label class="wizard-form-label" for="branch-select">
                <?php esc_html_e('Branch', 'deploy-forge'); ?>
            </label>
            <select id="branch-select" class="wizard-select" disabled>
                <option value=""><?php esc_html_e('Loading branches...', 'deploy-forge'); ?></option>
            </select>
            <span class="branch-loading wizard-loading" style="display: none;"></span>
            <p class="wizard-form-description">
                <?php esc_html_e('Choose which branch to monitor for deployments.', 'deploy-forge'); ?>
            </p>
        </div>

        <div class="wizard-deployment-preview" style="display: none;">
            <strong><?php esc_html_e('Theme will be deployed to:', 'deploy-forge'); ?></strong>
            <code></code>
        </div>
    </div>
</div>
