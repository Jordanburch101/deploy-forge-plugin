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
        <?php esc_html_e('Select Your Theme Repository', 'github-auto-deploy'); ?>
    </h2>

    <p class="wizard-step-description">
        <?php esc_html_e('Choose the GitHub repository that contains your WordPress theme.', 'github-auto-deploy'); ?>
    </p>

    <div class="wizard-form-group">
        <label class="wizard-form-label" for="repository-select">
            <?php esc_html_e('Repository', 'github-auto-deploy'); ?>
        </label>
        <select id="repository-select" class="wizard-select">
            <option value=""><?php esc_html_e('Loading repositories...', 'github-auto-deploy'); ?></option>
        </select>
        <span class="repo-loading wizard-loading" style="display: none;"></span>
        <p class="wizard-form-description">
            <?php esc_html_e('Select the repository containing your WordPress theme code.', 'github-auto-deploy'); ?>
        </p>
    </div>

    <div class="wizard-form-group">
        <label class="wizard-form-label" for="branch-select">
            <?php esc_html_e('Branch', 'github-auto-deploy'); ?>
        </label>
        <select id="branch-select" class="wizard-select" disabled>
            <option value=""><?php esc_html_e('Select a repository first...', 'github-auto-deploy'); ?></option>
        </select>
        <span class="branch-loading wizard-loading" style="display: none;"></span>
        <p class="wizard-form-description">
            <?php esc_html_e('Choose which branch to monitor for deployments.', 'github-auto-deploy'); ?>
        </p>
    </div>

    <div class="wizard-deployment-preview" style="display: none;">
        <strong><?php esc_html_e('Theme will be deployed to:', 'github-auto-deploy'); ?></strong>
        <code></code>
    </div>
</div>
