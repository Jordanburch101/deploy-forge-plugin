<?php
/**
 * Setup Wizard Container Template
 * Main wrapper for the setup wizard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="github-deploy-wizard-wrap">
    <div class="github-deploy-wizard">

        <!-- Progress Stepper -->
        <div class="wizard-progress">
            <div class="wizard-steps">
                <div class="wizard-step <?php echo $current_step === 1 ? 'active' : ''; ?>">
                    <div class="wizard-step-circle">1</div>
                    <div class="wizard-step-label"><?php esc_html_e('Welcome', 'github-auto-deploy'); ?></div>
                </div>
                <div class="wizard-step <?php echo $current_step === 2 ? 'active' : ''; ?>">
                    <div class="wizard-step-circle">2</div>
                    <div class="wizard-step-label"><?php esc_html_e('Connect', 'github-auto-deploy'); ?></div>
                </div>
                <div class="wizard-step <?php echo $current_step === 3 ? 'active' : ''; ?>">
                    <div class="wizard-step-circle">3</div>
                    <div class="wizard-step-label"><?php esc_html_e('Repository', 'github-auto-deploy'); ?></div>
                </div>
                <div class="wizard-step <?php echo $current_step === 4 ? 'active' : ''; ?>">
                    <div class="wizard-step-circle">4</div>
                    <div class="wizard-step-label"><?php esc_html_e('Method', 'github-auto-deploy'); ?></div>
                </div>
                <div class="wizard-step <?php echo $current_step === 5 ? 'active' : ''; ?>">
                    <div class="wizard-step-circle">5</div>
                    <div class="wizard-step-label"><?php esc_html_e('Options', 'github-auto-deploy'); ?></div>
                </div>
                <div class="wizard-step <?php echo $current_step === 6 ? 'active' : ''; ?>">
                    <div class="wizard-step-circle">6</div>
                    <div class="wizard-step-label"><?php esc_html_e('Review', 'github-auto-deploy'); ?></div>
                </div>
            </div>
        </div>

        <!-- Wizard Content -->
        <div class="wizard-content">

            <!-- Step 1: Welcome -->
            <div class="wizard-step-content <?php echo $current_step === 1 ? 'active' : ''; ?>" data-step="1">
                <?php include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/setup-wizard/step-welcome.php'; ?>
            </div>

            <!-- Step 2: Connect to GitHub -->
            <div class="wizard-step-content <?php echo $current_step === 2 ? 'active' : ''; ?>" data-step="2">
                <?php include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/setup-wizard/step-connect.php'; ?>
            </div>

            <!-- Step 3: Select Repository -->
            <div class="wizard-step-content <?php echo $current_step === 3 ? 'active' : ''; ?>" data-step="3">
                <?php include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/setup-wizard/step-repository.php'; ?>
            </div>

            <!-- Step 4: Deployment Method -->
            <div class="wizard-step-content <?php echo $current_step === 4 ? 'active' : ''; ?>" data-step="4">
                <?php include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/setup-wizard/step-method.php'; ?>
            </div>

            <!-- Step 5: Deployment Options -->
            <div class="wizard-step-content <?php echo $current_step === 5 ? 'active' : ''; ?>" data-step="5">
                <?php include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/setup-wizard/step-options.php'; ?>
            </div>

            <!-- Step 6: Review & Complete -->
            <div class="wizard-step-content <?php echo $current_step === 6 ? 'active' : ''; ?>" data-step="6">
                <?php include GITHUB_DEPLOY_PLUGIN_DIR . 'templates/setup-wizard/step-review.php'; ?>
            </div>

        </div>

        <!-- Wizard Footer -->
        <div class="wizard-footer">
            <div class="wizard-footer-left">
                <button type="button" class="wizard-button wizard-button-link wizard-skip">
                    <?php esc_html_e('Skip Setup', 'github-auto-deploy'); ?>
                </button>
            </div>
            <div class="wizard-footer-right">
                <button type="button" class="wizard-button wizard-button-secondary wizard-back" style="display: none;">
                    <?php esc_html_e('← Back', 'github-auto-deploy'); ?>
                </button>
                <button type="button" class="wizard-button wizard-button-primary wizard-next">
                    <?php esc_html_e('Next →', 'github-auto-deploy'); ?>
                </button>
                <button type="button" class="wizard-button wizard-button-primary wizard-complete" style="display: none;">
                    <?php esc_html_e('Complete Setup', 'github-auto-deploy'); ?>
                </button>
            </div>
        </div>

    </div>
</div>
