<?php
/**
 * Settings page template
 *
 * Displays the Deploy Forge settings form including connection status,
 * repository configuration, and deployment options.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap deploy-forge-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<!-- Deploy Forge Connection Status -->
	<div class="deploy-forge-connection-card <?php echo $is_connected ? 'is-connected' : 'is-disconnected'; ?>">
		<?php if ( $is_connected ) : ?>
			<div class="deploy-forge-card-header">
				<span class="deploy-forge-status-indicator status-connected"></span>
				<h2><?php esc_html_e( 'Connected to Deploy Forge', 'deploy-forge' ); ?></h2>
			</div>
			<div class="deploy-forge-connection-details">
				<?php if ( ! empty( $connection_data['domain'] ) ) : ?>
					<div class="deploy-forge-detail-row">
						<span class="deploy-forge-detail-label"><?php esc_html_e( 'Site Domain', 'deploy-forge' ); ?></span>
						<span class="deploy-forge-detail-value"><strong><?php echo esc_html( $connection_data['domain'] ); ?></strong></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $connection_data['repo_owner'] ) && ! empty( $connection_data['repo_name'] ) ) : ?>
					<div class="deploy-forge-detail-row">
						<span class="deploy-forge-detail-label"><?php esc_html_e( 'Repository', 'deploy-forge' ); ?></span>
						<span class="deploy-forge-detail-value">
							<code><?php echo esc_html( $connection_data['repo_owner'] . '/' . $connection_data['repo_name'] ); ?></code>
							<?php if ( ! empty( $connection_data['repo_branch'] ) ) : ?>
								<span class="deploy-forge-branch-badge"><?php echo esc_html( $connection_data['repo_branch'] ); ?></span>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $connection_data['deployment_method'] ) ) : ?>
					<div class="deploy-forge-detail-row">
						<span class="deploy-forge-detail-label"><?php esc_html_e( 'Deployment Method', 'deploy-forge' ); ?></span>
						<span class="deploy-forge-detail-value">
							<?php
							echo 'github_actions' === $connection_data['deployment_method']
								? esc_html__( 'GitHub Actions', 'deploy-forge' )
								: esc_html__( 'Direct Clone', 'deploy-forge' );
							?>
						</span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $connection_data['workflow_path'] ) ) : ?>
					<div class="deploy-forge-detail-row">
						<span class="deploy-forge-detail-label"><?php esc_html_e( 'Workflow File', 'deploy-forge' ); ?></span>
						<span class="deploy-forge-detail-value"><code><?php echo esc_html( $connection_data['workflow_path'] ); ?></code></span>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $connection_data['connected_at'] ) ) : ?>
					<div class="deploy-forge-detail-row">
						<span class="deploy-forge-detail-label"><?php esc_html_e( 'Connected', 'deploy-forge' ); ?></span>
						<?php // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Using for relative time display. ?>
						<span class="deploy-forge-detail-value"><?php echo esc_html( human_time_diff( strtotime( $connection_data['connected_at'] ), current_time( 'timestamp' ) ) . ' ago' ); ?></span>
					</div>
				<?php endif; ?>
			</div>
			<div class="deploy-forge-message info">
				<strong><?php esc_html_e( 'Note:', 'deploy-forge' ); ?></strong>
				<?php esc_html_e( 'Repository configuration is managed on the Deploy Forge platform. To change your repository or deployment method, disconnect and reconnect.', 'deploy-forge' ); ?>
			</div>
			<div class="deploy-forge-card-actions">
				<button type="button" id="disconnect-btn" class="button button-secondary">
					<span class="dashicons dashicons-dismiss"></span>
					<?php esc_html_e( 'Disconnect from Deploy Forge', 'deploy-forge' ); ?>
				</button>
				<span id="disconnect-loading" class="spinner"></span>
			</div>
		<?php else : ?>
			<div class="deploy-forge-card-header">
				<span class="dashicons dashicons-admin-plugins"></span>
				<h2><?php esc_html_e( 'Connect to Deploy Forge', 'deploy-forge' ); ?></h2>
			</div>
			<p class="deploy-forge-card-description">
				<?php esc_html_e( 'Connect your WordPress site to the Deploy Forge platform to enable automatic theme deployments from GitHub.', 'deploy-forge' ); ?>
			</p>
			<p class="deploy-forge-card-description">
				<?php esc_html_e( 'The Deploy Forge platform will guide you through connecting your GitHub account and selecting a repository.', 'deploy-forge' ); ?>
			</p>
			<div class="deploy-forge-card-actions">
				<button type="button" id="connect-btn" class="button button-primary button-hero">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Connect to Deploy Forge', 'deploy-forge' ); ?>
				</button>
				<span id="connect-loading" class="spinner"></span>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $is_connected ) : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'deploy_forge_settings' ); ?>

			<h2><?php esc_html_e( 'Deployment Options', 'deploy-forge' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Manual Approval', 'deploy-forge' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="require_manual_approval" value="1" <?php checked( $current_settings['require_manual_approval'] ); ?>>
							<?php esc_html_e( 'Require manual approval before deploying', 'deploy-forge' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When checked, new commits will show as pending and require approval. When unchecked, deployments happen automatically.', 'deploy-forge' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Create Backups', 'deploy-forge' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="create_backups" value="1" <?php checked( $current_settings['create_backups'] ); ?>>
							<?php esc_html_e( 'Create a backup before each deployment (recommended). Only the 10 most recent backups are kept; older files are automatically deleted.', 'deploy-forge' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Debug Mode', 'deploy-forge' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="debug_mode" value="1" <?php checked( $current_settings['debug_mode'] ); ?>>
							<?php esc_html_e( 'Enable detailed logging for troubleshooting', 'deploy-forge' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Error Reporting', 'deploy-forge' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="error_telemetry" value="1" <?php checked( $current_settings['error_telemetry'] ); ?>>
							<?php esc_html_e( 'Help improve Deploy Forge by sending anonymous error reports', 'deploy-forge' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When enabled, PHP errors originating from the Deploy Forge plugin are sent to our error tracking service. No personal data, site content, or credentials are ever included. Only plugin errors, WordPress version, and PHP version are reported.', 'deploy-forge' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="deploy_forge_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'deploy-forge' ); ?>
				</button>
			</p>
		</form>
	<?php else : ?>
		<div class="deploy-forge-next-steps-card">
			<h2><?php esc_html_e( 'Next Steps', 'deploy-forge' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Click "Connect to Deploy Forge" above to begin setup', 'deploy-forge' ); ?></li>
				<li><?php esc_html_e( 'Authenticate with GitHub and select your repository', 'deploy-forge' ); ?></li>
				<li><?php esc_html_e( 'Configure your deployment method and workflow', 'deploy-forge' ); ?></li>
				<li><?php esc_html_e( 'Return here to configure deployment options', 'deploy-forge' ); ?></li>
			</ol>
		</div>
	<?php endif; ?>
</div>
