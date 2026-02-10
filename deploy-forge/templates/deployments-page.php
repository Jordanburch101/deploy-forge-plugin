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
		<!-- Deploy Header -->
		<div class="deploy-forge-header-bar">
			<div class="deploy-forge-header-left">
				<div class="deploy-forge-header-repo">
					<a href="<?php echo esc_url( 'https://github.com/' . $repo_name ); ?>" target="_blank" class="deploy-forge-repo-link"><?php echo esc_html( $repo_name ); ?></a>
					<span class="deploy-forge-branch-badge"><?php echo esc_html( $branch ); ?></span>
				</div>
				<?php if ( $active_deployment ) : ?>
					<div class="deploy-forge-header-meta">
						<span class="deploy-forge-header-detail">
							<span class="deploy-forge-header-label"><?php esc_html_e( 'Last deploy', 'deploy-forge' ); ?></span>
							<span class="deploy-forge-relative-time"
								data-timestamp="<?php echo esc_attr( mysql2date( 'U', $active_deployment->deployed_at ?? $active_deployment->created_at ) ); ?>"
								title="<?php echo esc_attr( mysql2date( 'M j, Y g:i a', $active_deployment->deployed_at ?? $active_deployment->created_at ) ); ?>">
								<?php echo esc_html( mysql2date( 'M j, Y g:i a', $active_deployment->deployed_at ?? $active_deployment->created_at ) ); ?>
							</span>
						</span>
						<span class="deploy-forge-header-separator"></span>
						<span class="deploy-forge-header-detail">
							<span class="deploy-forge-header-label"><?php esc_html_e( 'Commit', 'deploy-forge' ); ?></span>
							<code class="deploy-forge-header-hash"><?php echo esc_html( substr( $active_deployment->commit_hash, 0, 7 ) ); ?></code>
						</span>
						<span class="deploy-forge-header-separator"></span>
						<span class="deploy-forge-header-detail">
							<span class="deploy-forge-header-label"><?php esc_html_e( 'Deployments', 'deploy-forge' ); ?></span>
							<?php echo esc_html( $total_deployments ); ?>
						</span>
					</div>
				<?php endif; ?>
			</div>
			<div class="deploy-forge-header-actions">
				<a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" class="deploy-forge-header-link">
					<?php esc_html_e( 'View Dashboard', 'deploy-forge' ); ?>
					<span class="dashicons dashicons-external"></span>
				</a>
				<button type="button" class="button button-primary button-large" id="deploy-now-btn">
					<?php esc_html_e( 'Deploy Now', 'deploy-forge' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

	<!-- Deployments Table -->
	<?php if ( empty( $deploy_forge_deployments ) ) : ?>
		<p><?php esc_html_e( 'No deployments found.', 'deploy-forge' ); ?></p>
	<?php else : ?>
		<div class="deploy-forge-table-controls">
			<div class="deploy-forge-search-wrapper">
				<span class="dashicons dashicons-search"></span>
				<input type="text" id="deployment-search" class="deploy-forge-search-input" placeholder="<?php esc_attr_e( 'Search deployments', 'deploy-forge' ); ?>" />
			</div>
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

		<table class="wp-list-table widefat fixed deploy-forge-enhanced-table" id="deployments-table">
			<thead>
				<tr>
					<th style="width: 40%;"><?php esc_html_e( 'Deployment', 'deploy-forge' ); ?></th>
					<th style="width: 15%;"><?php esc_html_e( 'Date', 'deploy-forge' ); ?></th>
					<th class="deploy-forge-author-col" style="width: 12%;"><?php esc_html_e( 'Author', 'deploy-forge' ); ?></th>
					<th style="width: 18%;"><?php esc_html_e( 'Status', 'deploy-forge' ); ?></th>
					<th style="width: 15%;"><?php esc_html_e( 'Actions', 'deploy-forge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $deploy_forge_deployments as $deploy_forge_deployment ) :
					$is_active_row = ( (int) $deploy_forge_deployment->id === $active_deployment_id );
					$row_classes   = 'deploy-forge-row status-row-' . $deploy_forge_deployment->status;
					if ( $is_active_row ) {
						$row_classes .= ' deploy-forge-row-active';
					}
					?>
					<tr class="<?php echo esc_attr( $row_classes ); ?>" data-status="<?php echo esc_attr( $deploy_forge_deployment->status ); ?>">
						<td>
							<div class="deployment-primary"><?php echo esc_html( wp_trim_words( $deploy_forge_deployment->commit_message, 12 ) ); ?></div>
							<div class="deployment-meta">
								<span class="deployment-hash">
									<?php if ( $deploy_forge_deployment->build_url ) : ?>
										<a href="<?php echo esc_url( $deploy_forge_deployment->build_url ); ?>" target="_blank" title="<?php esc_attr_e( 'View build on GitHub', 'deploy-forge' ); ?>">
											<?php echo esc_html( substr( $deploy_forge_deployment->commit_hash, 0, 7 ) ); ?>
											<span class="dashicons dashicons-external"></span>
										</a>
									<?php else : ?>
										<?php echo esc_html( substr( $deploy_forge_deployment->commit_hash, 0, 7 ) ); ?>
									<?php endif; ?>
								</span>
								<span class="deployment-trigger-label"><?php echo esc_html( $deploy_forge_deployment->trigger_type ); ?></span>
							</div>
						</td>
						<td>
							<span class="deploy-forge-relative-time"
								data-timestamp="<?php echo esc_attr( mysql2date( 'U', $deploy_forge_deployment->created_at ) ); ?>"
								title="<?php echo esc_attr( mysql2date( 'M j, Y g:i a', $deploy_forge_deployment->created_at ) ); ?>">
								<?php echo esc_html( mysql2date( 'M j, Y g:i a', $deploy_forge_deployment->created_at ) ); ?>
							</span>
						</td>
						<td class="deploy-forge-author-col deploy-forge-author-cell"><?php echo esc_html( $deploy_forge_deployment->commit_author ); ?></td>
						<td>
							<span class="deployment-status status-<?php echo esc_attr( $deploy_forge_deployment->status ); ?>">
								<span class="status-dot"></span>
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $deploy_forge_deployment->status ) ) ); ?>
							</span>
							<?php if ( $is_active_row ) : ?>
								<span class="deployment-active-badge"><?php esc_html_e( 'Active', 'deploy-forge' ); ?></span>
								<span id="drift-indicator-<?php echo esc_attr( $deploy_forge_deployment->id ); ?>"
									class="deployment-drift-indicator" style="display:none;"></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button button-small view-details-btn"
								data-deployment-id="<?php echo esc_attr( $deploy_forge_deployment->id ); ?>">
								<?php esc_html_e( 'Details', 'deploy-forge' ); ?>
							</button>
							<?php if ( $is_active_row && ! empty( $deploy_forge_deployment->file_manifest ) ) : ?>
								<button type="button" class="button button-small check-changes-btn"
									data-deployment-id="<?php echo esc_attr( $deploy_forge_deployment->id ); ?>">
									<?php esc_html_e( 'Check Changes', 'deploy-forge' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( 'success' === $deploy_forge_deployment->status && ! empty( $deploy_forge_deployment->backup_path ) && ! $is_active_row ) : ?>
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

<!-- File Changes Modal -->
<div id="file-changes-modal" class="deploy-forge-modal" style="display: none;">
	<div class="deploy-forge-modal-content deploy-forge-modal-wide">
		<span class="deploy-forge-modal-close">&times;</span>
		<h2><?php esc_html_e( 'File Changes Detected', 'deploy-forge' ); ?></h2>
		<div id="file-changes-summary" class="file-changes-summary-bar"></div>
		<div id="file-changes-list"></div>
	</div>
</div>

<!-- File Diff Modal -->
<div id="file-diff-modal" class="deploy-forge-modal" style="display: none;">
	<div class="deploy-forge-modal-content deploy-forge-modal-wide">
		<span class="deploy-forge-modal-close">&times;</span>
		<h2 id="file-diff-title"><?php esc_html_e( 'File Diff', 'deploy-forge' ); ?></h2>
		<div id="file-diff-content">
			<pre class="file-diff-output"></pre>
		</div>
	</div>
</div>
