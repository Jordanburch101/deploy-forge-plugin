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
    <div id="bind-repo-section" style="display: none; margin: 24px 0;">
        <button type="button" id="bind-repo-btn" class="wizard-button wizard-button-primary">
            <span class="dashicons dashicons-lock"></span>
            <?php esc_html_e('Bind Repository', 'deploy-forge'); ?>
        </button>
        <span id="bind-loading" class="wizard-loading" style="display: none; margin-left: 12px; vertical-align: middle;"></span>
        <div style="margin-top: 16px; padding: 12px 16px; background: rgba(255, 0, 0, 0.1); border-left: 4px solid var(--wizard-accent-error, #ff0000); border-radius: 2px; border: 1px solid rgba(255, 0, 0, 0.3); border-left: 4px solid var(--wizard-accent-error, #ff0000);">
            <p style="margin: 0; font-size: 13px; color: var(--wizard-text-secondary, #a1a1a1);">
                <strong style="color: var(--wizard-accent-error, #ff0000);"><?php esc_html_e('Warning:', 'deploy-forge'); ?></strong>
                <?php esc_html_e('Once you bind a repository, you can only change it by restarting repository selection.', 'deploy-forge'); ?>
            </p>
        </div>
    </div>

    <!-- Amber Warning (shown after binding) -->
    <div id="repo-bound-warning" style="display: none; margin: 24px 0; padding: 16px 20px; background: rgba(255, 185, 0, 0.1); border: 1px solid var(--wizard-accent-warning, #ffb900); border-left: 4px solid var(--wizard-accent-warning, #ffb900); border-radius: 2px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
            <p style="margin: 0; font-size: 14px; color: var(--wizard-text-secondary, #a1a1a1); line-height: 1.5; flex: 1;">
                <span class="dashicons dashicons-warning" style="color: var(--wizard-accent-warning, #ffb900); vertical-align: middle; font-size: 18px;"></span>
                <strong style="color: var(--wizard-text-primary, #ffffff);"><?php esc_html_e('Repository Bound:', 'deploy-forge'); ?></strong>
                <?php esc_html_e('You have bound a repository. To change it, click "Restart Wizard" to unbind and select a different repository.', 'deploy-forge'); ?>
            </p>
            <button type="button" id="restart-wizard-btn" class="wizard-button wizard-button-secondary" style="flex-shrink: 0;">
                <span class="dashicons dashicons-update"></span>
                <?php esc_html_e('Restart Wizard', 'deploy-forge'); ?>
            </button>
        </div>
        <span id="restart-loading" class="wizard-loading" style="display: none; margin-top: 12px;"></span>
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
