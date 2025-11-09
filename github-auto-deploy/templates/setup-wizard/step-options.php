<?php
/**
 * Step 5: Deployment Options
 */

if (!defined('ABSPATH')) {
    exit;
}

$webhook_url = home_url('/wp-json/github-deploy/v1/webhook');
?>

<div class="wizard-options">
    <h2 class="wizard-step-title">
        <?php esc_html_e('Fine-Tune Your Deployment Settings', 'github-auto-deploy'); ?>
    </h2>

    <p class="wizard-step-description">
        <?php esc_html_e('Configure how deployments should behave.', 'github-auto-deploy'); ?>
    </p>

    <!-- Auto Deploy -->
    <div class="wizard-toggle-group">
        <div class="wizard-toggle-header">
            <label class="wizard-toggle-label" for="auto-deploy-toggle">
                <?php esc_html_e('Automatic Deployments', 'github-auto-deploy'); ?>
            </label>
            <label class="wizard-toggle-switch">
                <input type="checkbox" id="auto-deploy-toggle" checked>
                <span class="wizard-toggle-slider"></span>
            </label>
        </div>
        <p class="wizard-toggle-description">
            <?php esc_html_e('Automatically deploy when you push to GitHub.', 'github-auto-deploy'); ?>
        </p>

        <div class="wizard-toggle-substep">
            <div class="wizard-toggle-header">
                <label class="wizard-toggle-label" for="manual-approval-toggle">
                    <?php esc_html_e('Require Manual Approval', 'github-auto-deploy'); ?>
                </label>
                <label class="wizard-toggle-switch">
                    <input type="checkbox" id="manual-approval-toggle">
                    <span class="wizard-toggle-slider"></span>
                </label>
            </div>
            <p class="wizard-toggle-description">
                <?php esc_html_e('Create pending deployment that requires manual approval before deploying.', 'github-auto-deploy'); ?>
            </p>
        </div>
    </div>

    <!-- Backups -->
    <div class="wizard-toggle-group">
        <div class="wizard-toggle-header">
            <label class="wizard-toggle-label" for="create-backups-toggle">
                <?php esc_html_e('Create Backups', 'github-auto-deploy'); ?>
            </label>
            <label class="wizard-toggle-switch">
                <input type="checkbox" id="create-backups-toggle" checked>
                <span class="wizard-toggle-slider"></span>
            </label>
        </div>
        <p class="wizard-toggle-description">
            <?php esc_html_e('Automatically backup current theme before each deployment (recommended).', 'github-auto-deploy'); ?>
        </p>
    </div>

    <!-- Webhooks -->
    <div class="wizard-toggle-group">
        <div class="wizard-toggle-header">
            <label class="wizard-toggle-label" for="webhook-toggle">
                <?php esc_html_e('Enable GitHub Webhooks', 'github-auto-deploy'); ?>
            </label>
            <label class="wizard-toggle-switch">
                <input type="checkbox" id="webhook-toggle" checked>
                <span class="wizard-toggle-slider"></span>
            </label>
        </div>
        <p class="wizard-toggle-description">
            <?php esc_html_e('Receive instant notifications when you push to GitHub.', 'github-auto-deploy'); ?>
        </p>

        <div class="wizard-toggle-substep">
            <div class="wizard-webhook-details">
                <div class="wizard-webhook-field">
                    <label class="wizard-form-label"><?php esc_html_e('Webhook URL', 'github-auto-deploy'); ?></label>
                    <div class="wizard-webhook-value">
                        <input type="text" readonly value="<?php echo esc_attr($webhook_url); ?>">
                        <button type="button" class="wizard-copy-button"><?php esc_html_e('Copy', 'github-auto-deploy'); ?></button>
                    </div>
                    <p class="wizard-form-description">
                        <?php esc_html_e('Add this URL to your GitHub repository webhook settings.', 'github-auto-deploy'); ?>
                    </p>
                </div>

                <div class="wizard-webhook-field">
                    <label class="wizard-form-label"><?php esc_html_e('Webhook Secret', 'github-auto-deploy'); ?></label>
                    <div class="wizard-webhook-value">
                        <input type="text" id="webhook-secret-input" readonly>
                        <button type="button" class="wizard-copy-button"><?php esc_html_e('Copy', 'github-auto-deploy'); ?></button>
                    </div>
                    <p class="wizard-form-description">
                        <?php esc_html_e('Use this secret to secure your webhook.', 'github-auto-deploy'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
