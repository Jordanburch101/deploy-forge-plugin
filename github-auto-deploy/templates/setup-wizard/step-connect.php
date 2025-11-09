<?php
/**
 * Step 2: Connect to GitHub
 */

if (!defined('ABSPATH')) {
    exit;
}

$connect_url = $this->app_connector->get_connect_url();
?>

<div class="wizard-connect">
    <h2 class="wizard-step-title">
        <?php esc_html_e('Connect Your GitHub Account', 'github-auto-deploy'); ?>
    </h2>

    <p class="wizard-step-description">
        <?php esc_html_e('GitHub Deploy uses a secure GitHub App to access your repositories. Click below to install the app and authorize access.', 'github-auto-deploy'); ?>
    </p>

    <div class="wizard-connect-status">
        <?php if (!$is_connected): ?>
            <a href="<?php echo esc_url($connect_url); ?>" class="wizard-connect-button">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php esc_html_e('Connect to GitHub', 'github-auto-deploy'); ?>
            </a>

            <div class="wizard-help-text">
                <?php esc_html_e('You\'ll be redirected to GitHub to install the app, then automatically returned here.', 'github-auto-deploy'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
