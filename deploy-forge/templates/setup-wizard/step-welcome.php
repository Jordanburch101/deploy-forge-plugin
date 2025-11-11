<?php

/**
 * Step 1: Welcome
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wizard-welcome">
    <div class="wizard-welcome-icon">
        <img src="https://bucket.jordanburch.dev/mythic-white-cropped.png" alt="Mythic Logo" style="width: 80px; height: 80px; object-fit: contain;" />
    </div>

    <h1 class="wizard-welcome-title">
        <?php esc_html_e('Welcome to Deploy Forge!', 'deploy-forge'); ?>
    </h1>

    <p class="wizard-welcome-description">
        <?php esc_html_e('This wizard will help you set up automatic deployments from GitHub to WordPress in just a few simple steps.', 'deploy-forge'); ?>
    </p>

    <div class="wizard-features-list">
        <ul>
            <li><?php esc_html_e('Connect to GitHub', 'deploy-forge'); ?></li>
            <li><?php esc_html_e('Select your theme repository', 'deploy-forge'); ?></li>
            <li><?php esc_html_e('Choose deployment method', 'deploy-forge'); ?></li>
            <li><?php esc_html_e('Configure deployment options', 'deploy-forge'); ?></li>
        </ul>
    </div>

    <p class="wizard-time-estimate">
        <strong><?php esc_html_e('Estimated time:', 'deploy-forge'); ?></strong>
        <?php esc_html_e('5 minutes', 'deploy-forge'); ?>
    </p>
</div>