<?php
/**
 * Deployments page template
 *
 * Displays the main Deploy Forge page with Deploy Now controls
 * and paginated deployment history.
 *
 * @package Deploy_Forge
 * @since   1.0.47
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap deploy-forge-deployments">
	<h1><?php esc_html_e( 'Deployments', 'deploy-forge' ); ?></h1>

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

	<?php if ( $is_configured ) : ?>
		<!-- Deploy Now Bar -->
		<div class="deploy-forge-header">
			<div class="deploy-forge-header-content">
				<div class="deploy-forge-header-body">
					<div class="deploy-forge-connection-info">
						<p class="status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<a href="<?php echo esc_url( 'https://github.com/' . $repo_name ); ?>" target="_blank" class="deploy-forge-repo-link"><?php echo esc_html( $repo_name ); ?></a>
							<span class="deploy-forge-branch-badge"><?php echo esc_html( $branch ); ?></span>
						</p>
					</div>
					<div class="deploy-forge-header-actions">
						<a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" class="button button-large">
							<?php esc_html_e( 'View in Deploy Forge', 'deploy-forge' ); ?>
							<span class="dashicons dashicons-external" style="margin-top: 4px;"></span>
						</a>
						<button type="button" class="button button-primary button-large" id="deploy-now-btn">
							<?php esc_html_e( 'Deploy Now', 'deploy-forge' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<!-- Deployments Table -->
	<?php if ( empty( $deploy_forge_deployments ) ) : ?>
		<p><?php esc_html_e( 'No deployments found.', 'deploy-forge' ); ?></p>
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
					<th><?php esc_html_e( 'ID', 'deploy-forge' ); ?></th>
					<th><?php esc_html_e( 'Date/Time', 'deploy-forge' ); ?></th>
					<th><?php esc_html_e( 'Commit', 'deploy-forge' ); ?></th>
					<th><?php esc_html_e( 'Message', 'deploy-forge' ); ?></th>
					<th><?php esc_html_e( 'Author', 'deploy-forge' ); ?></th>
					<th><?php esc_html_e( 'Status', 'deploy-forge' ); ?></th>
					<th><?php esc_html_e( 'Trigger', 'deploy-forge' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'deploy-forge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $deploy_forge_deployments as $deploy_forge_deployment ) : ?>
					<tr data-status="<?php echo esc_attr( $deploy_forge_deployment->status ); ?>">
						<td><?php echo esc_html( $deploy_forge_deployment->id ); ?></td>
						<td><?php echo esc_html( mysql2date( 'M j, Y g:i a', $deploy_forge_deployment->created_at ) ); ?></td>
						<td>
							<code><?php echo esc_html( substr( $deploy_forge_deployment->commit_hash, 0, 7 ) ); ?></code>
							<?php if ( $deploy_forge_deployment->build_url ) : ?>
								<a href="<?php echo esc_url( $deploy_forge_deployment->build_url ); ?>" target="_blank" title="<?php esc_attr_e( 'View build on GitHub', 'deploy-forge' ); ?>">
									<span class="dashicons dashicons-external"></span>
								</a>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( wp_trim_words( $deploy_forge_deployment->commit_message, 10 ) ); ?></td>
						<td><?php echo esc_html( $deploy_forge_deployment->commit_author ); ?></td>
						<td>
							<span class="deployment-status status-<?php echo esc_attr( $deploy_forge_deployment->status ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $deploy_forge_deployment->status ) ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( ucfirst( $deploy_forge_deployment->trigger_type ) ); ?></td>
						<td>
							<button type="button" class="button button-small view-details-btn"
								data-deployment-id="<?php echo esc_attr( $deploy_forge_deployment->id ); ?>">
								<?php esc_html_e( 'Details', 'deploy-forge' ); ?>
							</button>
							<?php if ( 'success' === $deploy_forge_deployment->status && ! empty( $deploy_forge_deployment->backup_path ) ) : ?>
								<button type="button" class="button button-small rollback-btn"
									data-deployment-id="<?php echo esc_attr( $deploy_forge_deployment->id ); ?>">
									<?php esc_html_e( 'Rollback', 'deploy-forge' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( 'pending' === $deploy_forge_deployment->status ) : ?>
								<button type="button" class="button button-primary button-small approve-deployment-btn"
									data-deployment-id="<?php echo esc_attr( $deploy_forge_deployment->id ); ?>">
									<?php esc_html_e( 'Deploy', 'deploy-forge' ); ?>
								</button>
								<button type="button" class="button button-small cancel-deployment-btn"
									data-deployment-id="<?php echo esc_attr( $deploy_forge_deployment->id ); ?>">
									<?php esc_html_e( 'Cancel', 'deploy-forge' ); ?>
								</button>
							<?php elseif ( 'building' === $deploy_forge_deployment->status ) : ?>
								<button type="button" class="button button-small cancel-deployment-btn"
									data-deployment-id="<?php echo esc_attr( $deploy_forge_deployment->id ); ?>">
									<?php esc_html_e( 'Cancel', 'deploy-forge' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() returns safe HTML.
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => __( '&laquo;', 'deploy-forge' ),
							'next_text' => __( '&raquo;', 'deploy-forge' ),
							'total'     => $total_pages,
							'current'   => $paged,
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<!-- Deployment Details Modal (styles in shared-styles.css) -->
<div id="deployment-details-modal" class="deploy-forge-modal" style="display: none;">
	<div class="deploy-forge-modal-content">
		<span class="deploy-forge-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Deployment Details', 'deploy-forge' ); ?></h2>
		<div id="deployment-details-content">
			<p><?php esc_html_e( 'Loading...', 'deploy-forge' ); ?></p>
		</div>
	</div>
</div>
