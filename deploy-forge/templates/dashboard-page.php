<?php
/**
 * Dashboard page template
 *
 * Displays the main Deploy Forge dashboard with deployment statistics,
 * connection status, and recent deployments list.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap deploy-forge-dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( ! $is_configured ) : ?>
		<div class="deploy-forge-welcome-banner">
			<div class="deploy-forge-welcome-content">
				<h2 class="deploy-forge-welcome-title"><?php esc_html_e( 'Welcome to Deploy Forge', 'deploy-forge' ); ?></h2>
				<p class="deploy-forge-welcome-description">
					<?php esc_html_e( 'Connect your WordPress site to the Deploy Forge platform to enable automatic theme deployments from GitHub.', 'deploy-forge' ); ?>
				</p>
				<button type="button" id="connect-btn" class="deploy-forge-welcome-button">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Connect to Deploy Forge', 'deploy-forge' ); ?>
				</button>
				<span id="connect-loading" class="spinner" style="float: none; margin: 0 10px;"></span>
			</div>
		</div>
	<?php endif; ?>

	<!-- Connection & Controls -->
	<div class="deploy-forge-header">
		<div class="deploy-forge-header-content">
			<h2><?php esc_html_e( 'Connection & Controls', 'deploy-forge' ); ?></h2>
			<div class="deploy-forge-header-body">
				<div class="deploy-forge-connection-info">
					<?php if ( $is_configured ) : ?>
						<p class="status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Connected to GitHub', 'deploy-forge' ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Repository:', 'deploy-forge' ); ?></strong>
							<?php echo esc_html( $this->settings->get( 'github_repo_owner' ) . '/' . $this->settings->get( 'github_repo_name' ) ); ?>
						</p>
						<p>
							<strong><?php esc_html_e( 'Branch:', 'deploy-forge' ); ?></strong>
							<?php echo esc_html( $this->settings->get( 'github_branch' ) ); ?>
						</p>
						<?php if ( $this->settings->get( 'workflow_file_name' ) ) : ?>
							<p>
								<strong><?php esc_html_e( 'Workflow:', 'deploy-forge' ); ?></strong>
								<?php echo esc_html( $this->settings->get( 'workflow_file_name' ) ); ?>
							</p>
						<?php endif; ?>
						<?php if ( $stats['last_deployment'] ) : ?>
							<p>
								<strong><?php esc_html_e( 'Last Deployment:', 'deploy-forge' ); ?></strong>
								<?php echo esc_html( mysql2date( 'M j, g:i A', $stats['last_deployment']->deployed_at ) ); ?>
								|
								<strong><?php esc_html_e( 'Commit:', 'deploy-forge' ); ?></strong>
								<code><?php echo esc_html( substr( $stats['last_deployment']->commit_hash, 0, 7 ) ); ?></code>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<p class="status-disconnected">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e( 'Not configured', 'deploy-forge' ); ?>
						</p>
					<?php endif; ?>
				</div>
				<div class="deploy-forge-header-actions">
					<button type="button" class="button button-primary button-large" id="deploy-now-btn" <?php disabled( ! $is_configured ); ?>>
						<?php esc_html_e( 'Deploy Now', 'deploy-forge' ); ?>
					</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Stats -->
	<div class="deploy-forge-stats-section">
		<h2><?php esc_html_e( 'Stats', 'deploy-forge' ); ?></h2>
		<div class="deploy-forge-stats">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=deploy-forge-history' ) ); ?>" class="stat-item stat-clickable">
				<div class="stat-number"><?php echo esc_html( $stats['total'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Total', 'deploy-forge' ); ?></div>
				<div class="stat-action"><?php esc_html_e( 'click to view', 'deploy-forge' ); ?></div>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=deploy-forge-history&status=success' ) ); ?>" class="stat-item stat-clickable stat-success">
				<div class="stat-number"><?php echo esc_html( $stats['success'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Successful', 'deploy-forge' ); ?></div>
				<div class="stat-action"><?php esc_html_e( 'click to view', 'deploy-forge' ); ?></div>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=deploy-forge-history&status=failed' ) ); ?>" class="stat-item stat-clickable stat-failed">
				<div class="stat-number"><?php echo esc_html( $stats['failed'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Failed', 'deploy-forge' ); ?></div>
				<div class="stat-action"><?php esc_html_e( 'view log', 'deploy-forge' ); ?></div>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=deploy-forge-history&status=pending,building' ) ); ?>" class="stat-item stat-clickable stat-pending">
				<div class="stat-number"><?php echo esc_html( $stats['pending'] + $stats['building'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Pending', 'deploy-forge' ); ?></div>
				<div class="stat-action"><?php esc_html_e( 'details', 'deploy-forge' ); ?></div>
			</a>
		</div>
	</div>

	<!-- Latest Deployment Summary -->
	<?php if ( $stats['last_deployment'] ) : ?>
		<div class="deploy-forge-latest-summary">
			<h2><?php esc_html_e( 'Latest Deployment Summary', 'deploy-forge' ); ?></h2>
			<div class="deploy-forge-summary-content">
				<div class="deploy-forge-summary-details">
					<p>
						<strong><?php esc_html_e( 'Commit:', 'deploy-forge' ); ?></strong>
						<code><?php echo esc_html( substr( $stats['last_deployment']->commit_hash, 0, 7 ) ); ?></code>
					</p>
					<p>
						<strong><?php esc_html_e( 'Message:', 'deploy-forge' ); ?></strong>
						<?php echo esc_html( $stats['last_deployment']->commit_message ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Deployed at:', 'deploy-forge' ); ?></strong>
						<?php echo esc_html( mysql2date( 'M j, g:i A', $stats['last_deployment']->deployed_at ) ); ?>
					</p>
				</div>
				<div class="deploy-forge-summary-meta">
					<div class="deploy-forge-summary-status">
						<span class="deployment-status status-<?php echo esc_attr( $stats['last_deployment']->status ); ?>">
							<?php echo esc_html( ucfirst( $stats['last_deployment']->status ) ); ?>
						</span>
					</div>
					<p>
						<strong><?php esc_html_e( 'Triggered by:', 'deploy-forge' ); ?></strong>
						<?php echo esc_html( ucfirst( $stats['last_deployment']->trigger_type ) ); ?>
					</p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Recent Deployments -->
	<div class="deploy-forge-recent">
		<h2><?php esc_html_e( 'Recent Deployments', 'deploy-forge' ); ?></h2>
		<?php if ( empty( $recent_deployments ) ) : ?>
			<p><?php esc_html_e( 'No deployments yet.', 'deploy-forge' ); ?></p>
		<?php else : ?>
			<div class="deploy-forge-table-controls">
				<input type="text" id="deployment-search" class="deploy-forge-search-input" placeholder="<?php esc_attr_e( 'Search deployments', 'deploy-forge' ); ?>" />
				<select id="deployment-status-filter" class="deploy-forge-status-filter">
					<option value=""><?php esc_html_e( 'Filter: Status', 'deploy-forge' ); ?></option>
					<option value="success"><?php esc_html_e( 'Success', 'deploy-forge' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Failed', 'deploy-forge' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'deploy-forge' ); ?></option>
					<option value="building"><?php esc_html_e( 'Building', 'deploy-forge' ); ?></option>
					<option value="rolled_back"><?php esc_html_e( 'Rolled Back', 'deploy-forge' ); ?></option>
					<option value="cancelled"><?php esc_html_e( 'Cancelled', 'deploy-forge' ); ?></option>
				</select>
			</div>
			<table class="wp-list-table widefat fixed striped" id="deployments-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'deploy-forge' ); ?></th>
						<th><?php esc_html_e( 'Commit', 'deploy-forge' ); ?></th>
						<th><?php esc_html_e( 'Message', 'deploy-forge' ); ?></th>
						<th><?php esc_html_e( 'Status', 'deploy-forge' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'deploy-forge' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'deploy-forge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_deployments as $deployment ) : ?>
						<tr data-status="<?php echo esc_attr( $deployment->status ); ?>">
							<td><?php echo esc_html( mysql2date( 'M j, Y', $deployment->created_at ) ); ?></td>
							<td><code><?php echo esc_html( substr( $deployment->commit_hash, 0, 7 ) ); ?></code></td>
							<td><?php echo esc_html( wp_trim_words( $deployment->commit_message, 8 ) ); ?></td>
							<td>
								<span class="deployment-status status-<?php echo esc_attr( $deployment->status ); ?>">
									<?php echo esc_html( strtoupper( $deployment->status ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( ucfirst( $deployment->trigger_type ) ); ?></td>
							<td>
								<?php if ( 'pending' === $deployment->status ) : ?>
									<button type="button" class="button button-primary button-small approve-deployment-btn" data-deployment-id="<?php echo esc_attr( $deployment->id ); ?>">
										<?php esc_html_e( 'Deploy', 'deploy-forge' ); ?>
									</button>
									<button type="button" class="button button-small cancel-deployment-btn" data-deployment-id="<?php echo esc_attr( $deployment->id ); ?>">
										<?php esc_html_e( 'Cancel', 'deploy-forge' ); ?>
									</button>
								<?php elseif ( 'building' === $deployment->status ) : ?>
									<button type="button" class="button button-small cancel-deployment-btn" data-deployment-id="<?php echo esc_attr( $deployment->id ); ?>">
										<?php esc_html_e( 'Cancel', 'deploy-forge' ); ?>
									</button>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
