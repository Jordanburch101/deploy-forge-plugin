<?php

/**
 * Step 5: Deployment Options
 */

if (!defined('ABSPATH')) {
    exit;
}

$webhook_url = home_url('/wp-json/deploy-forge/v1/webhook');
?>

<div class="wizard-options">
    <h2 class="wizard-step-title">
        <?php esc_html_e('Fine-Tune Your Deployment Settings', 'deploy-forge'); ?>
    </h2>

    <p class="wizard-step-description">
        <?php esc_html_e('Configure how deployments should behave.', 'deploy-forge'); ?>
    </p>

    <!-- Auto Deploy -->
    <div class="wizard-toggle-group">
        <div class="wizard-toggle-header">
            <label class="wizard-toggle-label" for="auto-deploy-toggle">
                <?php esc_html_e('Automatic Deployments', 'deploy-forge'); ?>
            </label>
            <label class="wizard-toggle-switch">
                <input type="checkbox" id="auto-deploy-toggle" checked>
                <span class="wizard-toggle-slider"></span>
            </label>
        </div>
        <p class="wizard-toggle-description">
            <?php esc_html_e('Automatically deploy when you push to GitHub.', 'deploy-forge'); ?>
        </p>

        <div class="wizard-toggle-substep">
            <div class="wizard-toggle-header">
                <label class="wizard-toggle-label" for="manual-approval-toggle">
                    <?php esc_html_e('Require Manual Approval', 'deploy-forge'); ?>
                </label>
                <label class="wizard-toggle-switch">
                    <input type="checkbox" id="manual-approval-toggle">
                    <span class="wizard-toggle-slider"></span>
                </label>
            </div>
            <p class="wizard-toggle-description">
                <?php esc_html_e('Create pending deployment that requires manual approval before deploying.', 'deploy-forge'); ?>
            </p>
        </div>
    </div>

    <!-- Backups -->
    <div class="wizard-toggle-group">
        <div class="wizard-toggle-header">
            <label class="wizard-toggle-label" for="create-backups-toggle">
                <?php esc_html_e('Create Backups', 'deploy-forge'); ?>
            </label>
            <label class="wizard-toggle-switch">
                <input type="checkbox" id="create-backups-toggle" checked>
                <span class="wizard-toggle-slider"></span>
            </label>
        </div>
        <p class="wizard-toggle-description">
            <?php esc_html_e('Automatically backup current theme before each deployment (recommended).', 'deploy-forge'); ?>
        </p>
    </div>
</div>