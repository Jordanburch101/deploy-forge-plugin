<?php
/**
 * Deployment history page template
 *
 * Displays a paginated table of all deployments with details,
 * status, and action buttons for each deployment.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap deploy-forge-history">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( empty( $deploy_forge_deployments ) ) : ?>
		<p><?php esc_html_e( 'No deployments found.', 'deploy-forge' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
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
					<tr>
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

<script>
	jQuery(document).ready(function($) {
		// View details
		$('.view-details-btn').on('click', function() {
			var deploymentId = $(this).data('deployment-id');
			var modal = $('#deployment-details-modal');
			var content = $('#deployment-details-content');

			modal.show();
			content.html('<p><?php echo esc_js( __( 'Loading...', 'deploy-forge' ) ); ?></p>');

			$.post(ajaxurl, {
				action: 'deploy_forge_get_status',
				nonce: '<?php echo esc_js( wp_create_nonce( 'deploy_forge_admin' ) ); ?>',
				deployment_id: deploymentId
			}, function(response) {
				if (response.success) {
					var d = response.data.deployment;
					var html = '<table class="widefat">';
					html += '<tr><th><?php echo esc_js( __( 'Commit Hash', 'deploy-forge' ) ); ?></th><td><code>' + d.commit_hash + '</code></td></tr>';
					html += '<tr><th><?php echo esc_js( __( 'Message', 'deploy-forge' ) ); ?></th><td>' + d.commit_message + '</td></tr>';
					html += '<tr><th><?php echo esc_js( __( 'Author', 'deploy-forge' ) ); ?></th><td>' + d.commit_author + '</td></tr>';
					html += '<tr><th><?php echo esc_js( __( 'Status', 'deploy-forge' ) ); ?></th><td>' + d.status + '</td></tr>';
					if (d.build_url) {
						html += '<tr><th><?php echo esc_js( __( 'Build URL', 'deploy-forge' ) ); ?></th><td><a href="' + d.build_url + '" target="_blank">' + d.build_url + '</a></td></tr>';
					}
					html += '</table>';

					if (d.deployment_logs) {
						html += '<h3><?php echo esc_js( __( 'Deployment Logs', 'deploy-forge' ) ); ?></h3>';
						html += '<pre>' + d.deployment_logs + '</pre>';
					}

					if (d.error_message) {
						html += '<h3><?php echo esc_js( __( 'Error Message', 'deploy-forge' ) ); ?></h3>';
						html += '<pre>' + d.error_message + '</pre>';
					}

					content.html(html);
				}
			});
		});

		// Close modal
		$('.deploy-forge-modal-close, .deploy-forge-modal').on('click', function(e) {
			if (e.target === this) {
				$('#deployment-details-modal').hide();
			}
		});
	});
</script>
