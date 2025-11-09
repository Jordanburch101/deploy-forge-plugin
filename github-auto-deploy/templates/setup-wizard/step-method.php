<?php
/**
 * Step 4: Deployment Method
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wizard-method">
    <h2 class="wizard-step-title">
        <?php esc_html_e('How Should Deployments Work?', 'github-auto-deploy'); ?>
    </h2>

    <p class="wizard-step-description">
        <?php esc_html_e('Choose how your theme should be built and deployed.', 'github-auto-deploy'); ?>
    </p>

    <div class="wizard-option-cards">
        <!-- GitHub Actions -->
        <div class="wizard-option-card" data-method="github_actions">
            <div class="wizard-option-card-header">
                <div class="wizard-option-icon">⚙️</div>
                <div>
                    <h3 class="wizard-option-title"><?php esc_html_e('GitHub Actions', 'github-auto-deploy'); ?></h3>
                    <p class="wizard-option-subtitle"><?php esc_html_e('Build assets then deploy', 'github-auto-deploy'); ?></p>
                </div>
            </div>

            <p class="wizard-option-description">
                <?php esc_html_e('Uses GitHub workflows to compile/build your theme (webpack, npm, SCSS) before deploying. Best for themes with build processes.', 'github-auto-deploy'); ?>
            </p>

            <div class="wizard-option-badges">
                <span class="wizard-badge wizard-badge-recommended"><?php esc_html_e('Recommended', 'github-auto-deploy'); ?></span>
                <span class="wizard-badge"><?php esc_html_e('2-5 min', 'github-auto-deploy'); ?></span>
            </div>

            <div class="wizard-option-details">
                <div class="wizard-form-group">
                    <label class="wizard-form-label" for="workflow-select">
                        <?php esc_html_e('Select Workflow', 'github-auto-deploy'); ?>
                    </label>
                    <select id="workflow-select" class="wizard-select">
                        <option value=""><?php esc_html_e('Loading workflows...', 'github-auto-deploy'); ?></option>
                    </select>
                    <span class="workflow-loading wizard-loading" style="display: none;"></span>
                    <p class="wizard-no-workflow-message" style="display: none; margin-top: 8px; color: #d63638;">
                        <?php esc_html_e('No workflows found. You\'ll need to create a GitHub Actions workflow file in your repository.', 'github-auto-deploy'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Direct Clone -->
        <div class="wizard-option-card" data-method="direct_clone">
            <div class="wizard-option-card-header">
                <div class="wizard-option-icon">⚡</div>
                <div>
                    <h3 class="wizard-option-title"><?php esc_html_e('Direct Clone', 'github-auto-deploy'); ?></h3>
                    <p class="wizard-option-subtitle"><?php esc_html_e('Deploy immediately', 'github-auto-deploy'); ?></p>
                </div>
            </div>

            <p class="wizard-option-description">
                <?php esc_html_e('Downloads repository directly without building. Perfect for simple themes using plain CSS/JS with no build tools.', 'github-auto-deploy'); ?>
            </p>

            <div class="wizard-option-badges">
                <span class="wizard-badge wizard-badge-fast"><?php esc_html_e('Fastest', 'github-auto-deploy'); ?></span>
                <span class="wizard-badge"><?php esc_html_e('10-30 sec', 'github-auto-deploy'); ?></span>
            </div>

            <div class="wizard-option-details">
                <p style="margin: 0; color: #666; font-size: 14px;">
                    <?php esc_html_e('✓ No GitHub Actions workflow needed', 'github-auto-deploy'); ?><br>
                    <?php esc_html_e('✓ Deploys raw repository files', 'github-auto-deploy'); ?><br>
                    <?php esc_html_e('✓ Ideal for static assets', 'github-auto-deploy'); ?>
                </p>
            </div>
        </div>
    </div>
</div>
