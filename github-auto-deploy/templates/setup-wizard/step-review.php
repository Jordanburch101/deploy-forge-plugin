<?php
/**
 * Step 6: Review & Complete
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wizard-review">
    <h2 class="wizard-step-title">
        <?php esc_html_e('Review Your Configuration', 'github-auto-deploy'); ?>
    </h2>

    <p class="wizard-step-description">
        <?php esc_html_e('Please review your settings before completing setup.', 'github-auto-deploy'); ?>
    </p>

    <div class="wizard-review-cards">
        <!-- GitHub Connection -->
        <div class="wizard-review-card">
            <div class="wizard-review-card-header">
                <h3 class="wizard-review-card-title">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('GitHub Connection', 'github-auto-deploy'); ?>
                </h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-wizard&step=2')); ?>" class="wizard-review-edit">
                    <?php esc_html_e('Edit', 'github-auto-deploy'); ?>
                </a>
            </div>
            <div class="wizard-review-content">
                <strong><?php esc_html_e('Status:', 'github-auto-deploy'); ?></strong>
                <?php esc_html_e('Connected to GitHub', 'github-auto-deploy'); ?>
            </div>
        </div>

        <!-- Repository -->
        <div class="wizard-review-card">
            <div class="wizard-review-card-header">
                <h3 class="wizard-review-card-title">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Repository', 'github-auto-deploy'); ?>
                </h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-wizard&step=3')); ?>" class="wizard-review-edit">
                    <?php esc_html_e('Edit', 'github-auto-deploy'); ?>
                </a>
            </div>
            <div class="wizard-review-content">
                <p><strong><?php esc_html_e('Repository:', 'github-auto-deploy'); ?></strong> <span id="review-repo-name">-</span></p>
                <p><strong><?php esc_html_e('Branch:', 'github-auto-deploy'); ?></strong> <span id="review-branch">-</span></p>
            </div>
        </div>

        <!-- Deployment Method -->
        <div class="wizard-review-card">
            <div class="wizard-review-card-header">
                <h3 class="wizard-review-card-title">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Deployment Method', 'github-auto-deploy'); ?>
                </h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-wizard&step=4')); ?>" class="wizard-review-edit">
                    <?php esc_html_e('Edit', 'github-auto-deploy'); ?>
                </a>
            </div>
            <div class="wizard-review-content">
                <p><strong><?php esc_html_e('Method:', 'github-auto-deploy'); ?></strong> <span id="review-method">-</span></p>
                <p id="review-workflow-row"><strong><?php esc_html_e('Workflow:', 'github-auto-deploy'); ?></strong> <span id="review-workflow">-</span></p>
            </div>
        </div>

        <!-- Settings -->
        <div class="wizard-review-card">
            <div class="wizard-review-card-header">
                <h3 class="wizard-review-card-title">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Settings', 'github-auto-deploy'); ?>
                </h3>
                <a href="<?php echo esc_url(admin_url('admin.php?page=github-deploy-wizard&step=5')); ?>" class="wizard-review-edit">
                    <?php esc_html_e('Edit', 'github-auto-deploy'); ?>
                </a>
            </div>
            <div class="wizard-review-content">
                <ul class="wizard-review-list">
                    <li id="review-auto-deploy"><?php esc_html_e('Auto-deploy enabled', 'github-auto-deploy'); ?></li>
                    <li id="review-manual-approval"><?php esc_html_e('Manual approval required', 'github-auto-deploy'); ?></li>
                    <li id="review-backups"><?php esc_html_e('Backups enabled', 'github-auto-deploy'); ?></li>
                    <li id="review-webhooks"><?php esc_html_e('Webhooks configured', 'github-auto-deploy'); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Next Steps -->
    <div class="wizard-next-steps">
        <h3 class="wizard-next-steps-title"><?php esc_html_e('Next Steps', 'github-auto-deploy'); ?></h3>
        <ul>
            <li>
                <?php esc_html_e('Configure your GitHub webhook in repository settings', 'github-auto-deploy'); ?>
                (<a href="https://docs.github.com/en/developers/webhooks-and-events/webhooks/creating-webhooks" target="_blank"><?php esc_html_e('How to', 'github-auto-deploy'); ?></a>)
            </li>
            <li><?php esc_html_e('Test your first deployment from the dashboard', 'github-auto-deploy'); ?></li>
            <li><?php esc_html_e('Review deployment history and logs', 'github-auto-deploy'); ?></li>
        </ul>
    </div>
</div>
