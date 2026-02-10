/**
 * Deploy Forge Admin JavaScript
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

/* global jQuery, deployForgeAdmin */

( function( $ ) {
	'use strict';

	/**
	 * Main admin controller object
	 *
	 * Handles all admin page interactions including deployments,
	 * connections, and GitHub integration.
	 *
	 * @since 1.0.0
	 * @type {Object}
	 */
	const GitHubDeployAdmin = {

		/**
		 * Initialize the admin module
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		init: function() {
			this.bindEvents();
			this.initTableFilters();
			this.initRelativeTimestamps();
			this.autoRefresh();
			this.autoCheckChanges();
		},

		/**
		 * Bind all event handlers
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		bindEvents: function() {
			// Deploy Forge connection.
			$( '#connect-btn' ).on( 'click', this.connectToDeployForge.bind( this ) );
			$( '#disconnect-btn' ).on( 'click', this.disconnectFromDeployForge.bind( this ) );

			// Workflow loading.
			$( '#load-workflows-btn' ).on( 'click', this.loadWorkflows.bind( this ) );
			$( '#github_workflow_dropdown' ).on( 'change', this.onWorkflowSelect.bind( this ) );

			// Test connection.
			$( '#test-connection-btn' ).on( 'click', this.testConnection.bind( this ) );

			// Deploy now.
			$( '#deploy-now-btn' ).on( 'click', this.deployNow.bind( this ) );

			// Refresh status.
			$( '#refresh-status-btn' ).on( 'click', this.refreshStatus.bind( this ) );

			// Rollback.
			$( '.rollback-btn' ).on( 'click', this.rollback.bind( this ) );

			// Approve deployment (manual approval).
			$( '.approve-deployment-btn' ).on( 'click', this.approveDeployment.bind( this ) );

			// Cancel deployment.
			$( '.cancel-deployment-btn' ).on( 'click', this.cancelDeployment.bind( this ) );

			// View details.
			$( '.view-details-btn' ).on( 'click', this.viewDetails.bind( this ) );

			// Reset all data.
			$( '#reset-all-data-btn' ).on( 'click', this.resetAllData.bind( this ) );

			// Check changes.
			$( '.check-changes-btn' ).on( 'click', this.checkChanges.bind( this ) );

			// View diff (delegated for dynamically created buttons).
			$( document ).on( 'click', '.view-diff-btn', this.viewFileDiff.bind( this ) );

			// Close modal.
			$( '.deploy-forge-modal-close' ).on( 'click', this.closeModal.bind( this ) );
			$( '.deploy-forge-modal' ).on( 'click', function( e ) {
				if ( e.target === this ) {
					$( this ).hide();
				}
			} );

			// Show workflow button if repo is configured (after page load).
			this.checkWorkflowButtonVisibility();

			// Handle deployment method change.
			$( '#deployment_method' ).on( 'change', this.onDeploymentMethodChange.bind( this ) );
			this.onDeploymentMethodChange(); // Run on page load.
		},

		/**
		 * Initialize table search and filter functionality
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		initTableFilters: function() {
			const $searchInput = $( '#deployment-search' );
			const $statusFilter = $( '#deployment-status-filter' );
			const $table = $( '#deployments-table' );

			if ( ! $table.length ) {
				return; // No table on this page.
			}

			// Search functionality.
			$searchInput.on( 'keyup', () => {
				this.filterTable();
			} );

			// Status filter functionality.
			$statusFilter.on( 'change', () => {
				this.filterTable();
			} );
		},

		/**
		 * Filter deployments table based on search and status
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		filterTable: () => {
			const searchTerm = $( '#deployment-search' ).val().toLowerCase();
			const statusFilter = $( '#deployment-status-filter' ).val().toLowerCase();
			const $rows = $( '#deployments-table tbody tr' );

			$rows.each( function() {
				const $row = $( this );
				const rowText = $row.text().toLowerCase();
				const rowStatus = $row.data( 'status' );

				// Check search match.
				const searchMatch = ! searchTerm || rowText.includes( searchTerm );

				// Check status match.
				const statusMatch = ! statusFilter || rowStatus === statusFilter;

				// Show/hide row.
				if ( searchMatch && statusMatch ) {
					$row.show();
				} else {
					$row.hide();
				}
			} );
		},

		/**
		 * Connect to Deploy Forge platform
		 *
		 * Initiates OAuth flow with the Deploy Forge platform.
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		connectToDeployForge: ( e ) => {
			e.preventDefault();

			const button = $( e.target ).closest( 'button' );
			const spinner = $( '#connect-loading' );

			button.prop( 'disabled', true );
			spinner.addClass( 'is-active' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'deploy_forge_connect',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					if ( response.success && response.data.redirect_url ) {
						// Redirect to Deploy Forge platform.
						window.location.href = response.data.redirect_url;
					} else {
						alert( response.data?.message || 'Failed to initiate connection' );
						button.prop( 'disabled', false );
						spinner.removeClass( 'is-active' );
					}
				},
				error: () => {
					alert( 'An error occurred. Please try again.' );
					button.prop( 'disabled', false );
					spinner.removeClass( 'is-active' );
				}
			} );
		},

		/**
		 * Disconnect from Deploy Forge platform
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		disconnectFromDeployForge: ( e ) => {
			e.preventDefault();

			if ( ! confirm( 'Are you sure you want to disconnect from Deploy Forge? You will need to reconnect to continue using automatic deployments.' ) ) {
				return;
			}

			const button = $( e.target ).closest( 'button' );
			const spinner = $( '#disconnect-loading' );

			button.prop( 'disabled', true );
			spinner.addClass( 'is-active' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'deploy_forge_disconnect',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					if ( response.success ) {
						// Reload the page to show disconnected state.
						window.location.reload();
					} else {
						alert( response.data?.message || 'Failed to disconnect' );
						button.prop( 'disabled', false );
						spinner.removeClass( 'is-active' );
					}
				},
				error: () => {
					alert( 'An error occurred. Please try again.' );
					button.prop( 'disabled', false );
					spinner.removeClass( 'is-active' );
				}
			} );
		},

		/**
		 * Test connection to GitHub
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		testConnection: ( e ) => {
			e.preventDefault();

			const button = $( e.target );
			const originalText = button.text();
			const resultDiv = $( '#connection-result' );

			button.prop( 'disabled', true ).text( deployForgeAdmin.strings.testing );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_test_connection',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					const msg = GitHubDeployAdmin.escapeHtml( response.message || '' );
					if ( response.success ) {
						resultDiv.html( '<div class="notice notice-success"><p>' + msg + '</p></div>' );
					} else {
						resultDiv.html( '<div class="notice notice-error"><p>' + msg + '</p></div>' );
					}
				},
				error: () => {
					resultDiv.html( '<div class="notice notice-error"><p>Connection test failed.</p></div>' );
				},
				complete: () => {
					button.prop( 'disabled', false ).text( originalText );
				}
			} );
		},

		/**
		 * Trigger manual deployment
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		deployNow: ( e ) => {
			e.preventDefault();

			if ( ! confirm( deployForgeAdmin.strings.confirmDeploy ) ) {
				return;
			}

			const button = $( e.target );
			const originalHtml = button.html();

			button.prop( 'disabled', true ).html( '<span class="deploy-forge-loading"></span> ' + deployForgeAdmin.strings.deploying );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_manual_deploy',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					if ( response.success ) {
						alert( response.data.message );
						location.reload();
					} else {
						// Check if error is due to deployment in progress.
						if ( 'deployment_in_progress' === response.data.error_code && response.data.building_deployment ) {
							const buildingDep = response.data.building_deployment;
							const message = response.data.message + '\n\n' +
								'Deployment #' + buildingDep.id + ' (Status: ' + buildingDep.status + ')\n' +
								'Commit: ' + buildingDep.commit_hash.substring( 0, 7 ) + '\n\n' +
								'Would you like to cancel it now?';

							if ( confirm( message ) ) {
								// Cancel the existing deployment.
								GitHubDeployAdmin.cancelExistingDeployment( buildingDep.id, button, originalHtml );
							} else {
								button.prop( 'disabled', false ).html( originalHtml );
							}
						} else {
							alert( response.data.message || 'Deployment failed.' );
							button.prop( 'disabled', false ).html( originalHtml );
						}
					}
				},
				error: () => {
					alert( 'Deployment request failed.' );
					button.prop( 'disabled', false ).html( originalHtml );
				}
			} );
		},

		/**
		 * Cancel an existing deployment
		 *
		 * @since 1.0.0
		 * @param {number} deploymentId  Deployment ID to cancel.
		 * @param {jQuery} button        Button element.
		 * @param {string} originalHtml  Original button HTML.
		 * @return {void}
		 */
		cancelExistingDeployment: ( deploymentId, button, originalHtml ) => {
			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_cancel',
					nonce: deployForgeAdmin.nonce,
					deployment_id: deploymentId
				},
				success: ( response ) => {
					if ( response.success ) {
						alert( 'Previous deployment cancelled. Please click \'Deploy Now\' again to start a new deployment.' );
						location.reload();
					} else {
						alert( 'Failed to cancel existing deployment: ' + ( response.data.message || 'Unknown error' ) );
						button.prop( 'disabled', false ).html( originalHtml );
					}
				},
				error: () => {
					alert( 'Failed to cancel existing deployment.' );
					button.prop( 'disabled', false ).html( originalHtml );
				}
			} );
		},

		/**
		 * Refresh deployment status
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		refreshStatus: ( e ) => {
			e.preventDefault();

			const button = $( e.target );
			const originalHtml = button.html();

			button.prop( 'disabled', true ).html( '<span class="deploy-forge-loading"></span>' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_get_status',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					if ( response.success ) {
						location.reload();
					}
				},
				complete: () => {
					button.prop( 'disabled', false ).html( originalHtml );
				}
			} );
		},

		/**
		 * Rollback to a previous deployment
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		rollback: ( e ) => {
			e.preventDefault();

			if ( ! confirm( deployForgeAdmin.strings.confirmRollback ) ) {
				return;
			}

			const button = $( e.target );
			const deploymentId = button.data( 'deployment-id' );
			const originalText = button.text();

			button.prop( 'disabled', true ).text( 'Rolling back...' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_rollback',
					nonce: deployForgeAdmin.nonce,
					deployment_id: deploymentId
				},
				success: ( response ) => {
					if ( response.success ) {
						alert( response.data.message );
						location.reload();
					} else {
						alert( response.data.message || 'Rollback failed.' );
						button.prop( 'disabled', false ).text( originalText );
					}
				},
				error: () => {
					alert( 'Rollback request failed.' );
					button.prop( 'disabled', false ).text( originalText );
				}
			} );
		},

		/**
		 * Approve a pending deployment
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		approveDeployment: ( e ) => {
			e.preventDefault();

			if ( ! confirm( 'Are you sure you want to deploy this commit?' ) ) {
				return;
			}

			const button = $( e.target ).closest( 'button' );
			const deploymentId = button.data( 'deployment-id' );
			const originalHtml = button.html();

			button.prop( 'disabled', true ).html( '<span class="deploy-forge-loading"></span> Deploying...' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_approve',
					nonce: deployForgeAdmin.nonce,
					deployment_id: deploymentId
				},
				success: ( response ) => {
					if ( response.success ) {
						alert( response.data.message || 'Deployment started successfully!' );
						location.reload();
					} else {
						alert( response.data.message || 'Approval failed.' );
						button.prop( 'disabled', false ).html( originalHtml );
					}
				},
				error: () => {
					alert( 'Approval request failed.' );
					button.prop( 'disabled', false ).html( originalHtml );
				}
			} );
		},

		/**
		 * Cancel a deployment
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		cancelDeployment: ( e ) => {
			e.preventDefault();

			if ( ! confirm( deployForgeAdmin.strings.confirmCancel ) ) {
				return;
			}

			const button = $( e.target ).closest( 'button' );
			const deploymentId = button.data( 'deployment-id' );
			const originalHtml = button.html();

			button.prop( 'disabled', true ).html( '<span class="deploy-forge-loading"></span> ' + deployForgeAdmin.strings.cancelling );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_cancel',
					nonce: deployForgeAdmin.nonce,
					deployment_id: deploymentId
				},
				success: ( response ) => {
					if ( response.success ) {
						alert( response.data.message );
						location.reload();
					} else {
						alert( response.data.message || 'Cancellation failed.' );
						button.prop( 'disabled', false ).html( originalHtml );
					}
				},
				error: () => {
					alert( 'Cancellation request failed.' );
					button.prop( 'disabled', false ).html( originalHtml );
				}
			} );
		},

		/**
		 * View deployment details in modal
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		viewDetails: ( e ) => {
			e.preventDefault();

			const button = $( e.target );
			const deploymentId = button.data( 'deployment-id' );
			const modal = $( '#deployment-details-modal' );
			const content = $( '#deployment-details-content' );

			modal.show();
			content.html( '<p>Loading...</p>' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_get_status',
					nonce: deployForgeAdmin.nonce,
					deployment_id: deploymentId
				},
				success: ( response ) => {
					if ( response.success && response.data.deployment ) {
						const d = response.data.deployment;
						const esc = GitHubDeployAdmin.escapeHtml;
						let html = '<table class="widefat">';
						html += '<tr><th>Commit Hash</th><td><code>' + esc( d.commit_hash ) + '</code></td></tr>';
						html += '<tr><th>Message</th><td>' + esc( d.commit_message || 'N/A' ) + '</td></tr>';
						html += '<tr><th>Author</th><td>' + esc( d.commit_author || 'N/A' ) + '</td></tr>';
						html += '<tr><th>Status</th><td><span class="deployment-status status-' + esc( d.status ) + '">' + esc( d.status ) + '</span></td></tr>';
						html += '<tr><th>Trigger Type</th><td>' + esc( d.trigger_type ) + '</td></tr>';
						html += '<tr><th>Created At</th><td>' + esc( d.created_at ) + '</td></tr>';

						if ( d.deployed_at ) {
							html += '<tr><th>Deployed At</th><td>' + esc( d.deployed_at ) + '</td></tr>';
						}

						if ( d.build_url ) {
							html += '<tr><th>Build URL</th><td><a href="' + esc( d.build_url ) + '" target="_blank">' + esc( d.build_url ) + '</a></td></tr>';
						}

						html += '</table>';

						if ( d.deployment_logs ) {
							html += '<h3>Deployment Logs</h3>';
							html += '<pre>' + esc( d.deployment_logs ) + '</pre>';
						}

						if ( d.error_message ) {
							html += '<h3>Error Message</h3>';
							html += '<pre>' + esc( d.error_message ) + '</pre>';
						}

						content.html( html );
					} else {
						content.html( '<p>Failed to load deployment details.</p>' );
					}
				},
				error: () => {
					content.html( '<p>Error loading deployment details.</p>' );
				}
			} );
		},

		/**
		 * Close modal dialogs
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		closeModal: () => {
			$( '.deploy-forge-modal' ).hide();
		},

		/**
		 * Reset all plugin data
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		resetAllData: ( e ) => {
			e.preventDefault();

			// First confirmation.
			if ( ! confirm(
				'⚠️ WARNING: This will permanently delete ALL plugin data!\n\n' +
				'This includes:\n' +
				'• GitHub connection and credentials\n' +
				'• All deployment history\n' +
				'• All backup files\n' +
				'• All settings\n' +
				'• Backend server data\n\n' +
				'This action CANNOT be undone!\n\n' +
				'Are you sure you want to continue?'
			) ) {
				return;
			}

			// Second confirmation with type-to-confirm.
			const confirmText = prompt(
				'To confirm this destructive action, please type: RESET\n\n' +
				'(Type RESET in all caps to proceed)'
			);

			if ( 'RESET' !== confirmText ) {
				if ( null !== confirmText ) {
					alert( 'Reset cancelled. You must type \'RESET\' exactly to confirm.' );
				}
				return;
			}

			const button = $( e.target ).closest( 'button' );
			const spinner = $( '#reset-loading' );

			button.prop( 'disabled', true );
			spinner.addClass( 'is-active' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'deploy_forge_reset_all_data',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					if ( response.success ) {
						alert(
							'✓ All plugin data has been reset successfully.\n\n' +
							'The page will now reload. You will need to reconnect to GitHub.'
						);
						window.location.reload();
					} else {
						alert( 'Failed to reset plugin data:\n\n' + ( response.data?.message || 'Unknown error' ) );
						button.prop( 'disabled', false );
						spinner.removeClass( 'is-active' );
					}
				},
				error: () => {
					alert( 'An error occurred while resetting plugin data. Please try again.' );
					button.prop( 'disabled', false );
					spinner.removeClass( 'is-active' );
				}
			} );
		},

		/**
		 * Load repositories from GitHub App installation
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		loadInstallationRepos: () => {
			const $loading = $( '#repo-selector-loading' );
			const $list = $( '#repo-selector-list' );
			const $error = $( '#repo-selector-error' );
			const $errorMessage = $( '#repo-selector-error-message' );
			const $select = $( '#repo-select' );

			$loading.show();
			$list.hide();
			$error.hide();

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'deploy_forge_get_installation_repos',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					$loading.hide();

					if ( response.success && response.data.repos ) {
						// Populate dropdown.
						$select.html( '<option value="">-- Select a Repository --</option>' );

						response.data.repos.forEach( ( repo ) => {
							const icon = repo.private ? '🔒 ' : '📖 ';
							$select.append(
								$( '<option></option>' )
									.val( JSON.stringify( {
										owner: repo.owner,
										name: repo.name,
										full_name: repo.full_name,
										default_branch: repo.default_branch
									} ) )
									.text( icon + repo.full_name )
							);
						} );

						$list.show();
					} else {
						console.error( 'Failed to load repos:', response );
						$errorMessage.text( response.data?.message || 'Failed to load repositories' );
						$error.show();
					}
				},
				error: ( xhr, status, error ) => {
					console.error( 'AJAX error loading repos:', xhr, status, error );
					$loading.hide();
					$errorMessage.text( 'An error occurred while loading repositories: ' + error );
					$error.show();
				}
			} );
		},

		/**
		 * Handle repository selection change
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		onRepoSelectChange: function() {
			const $select = $( '#repo-select' );
			const $bindButton = $( '#bind-repo-btn' );

			if ( $select.val() ) {
				$bindButton.prop( 'disabled', false );
			} else {
				$bindButton.prop( 'disabled', true );
			}

			// Update workflow button visibility based on selection.
			this.checkWorkflowButtonVisibility();
		},

		/**
		 * Bind a repository to this site
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		bindRepository: ( e ) => {
			e.preventDefault();

			const $select = $( '#repo-select' );
			const $button = $( '#bind-repo-btn' );
			const $spinner = $( '#bind-loading' );

			if ( ! $select.val() ) {
				return;
			}

			const repoData = JSON.parse( $select.val() );

			if ( ! confirm(
				'Are you sure you want to bind to ' + repoData.full_name + '?\n\n' +
				'This action is permanent and cannot be undone without disconnecting from GitHub.'
			) ) {
				return;
			}

			$button.prop( 'disabled', true );
			$select.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'deploy_forge_bind_repo',
					nonce: deployForgeAdmin.nonce,
					owner: repoData.owner,
					name: repoData.name,
					default_branch: repoData.default_branch
				},
				success: ( response ) => {
					if ( response.success ) {
						alert( response.data.message || 'Repository bound successfully!' );
						// Reload page to show bound state.
						window.location.reload();
					} else {
						alert( response.data?.message || 'Failed to bind repository' );
						$button.prop( 'disabled', false );
						$select.prop( 'disabled', false );
						$spinner.removeClass( 'is-active' );
					}
				},
				error: () => {
					alert( 'An error occurred. Please try again.' );
					$button.prop( 'disabled', false );
					$select.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );
				}
			} );
		},

		/**
		 * Load available workflows from selected repository
		 *
		 * SECURITY: Validates repo data before making AJAX request.
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		loadWorkflows: ( e ) => {
			e.preventDefault();

			const $repoSelect = $( '#repo-select' );
			const $button = $( '#load-workflows-btn' );
			const $spinner = $( '#workflow-loading' );
			const $dropdown = $( '#github_workflow_dropdown' );
			const $manualInput = $( '#github_workflow_name' );
			const $error = $( '#workflow-error' );
			const $count = $( '#workflow-count' );

			// Get repository from either bound repo or selector.
			let owner, repo;

			if ( $repoSelect.length && $repoSelect.val() ) {
				// From repo selector during setup.
				try {
					const repoData = JSON.parse( $repoSelect.val() );
					owner = repoData.owner;
					repo = repoData.name;
				} catch ( err ) {
					$error.text( 'Invalid repository selection' ).show();
					return;
				}
			} else {
				// From bound repository (read from hidden fields or data attributes).
				owner = $( '#github_repo_owner' ).val();
				repo = $( '#github_repo_name' ).val();
			}

			if ( ! owner || ! repo ) {
				$error.text( 'Please select a repository first' ).show();
				return;
			}

			// SECURITY: Client-side validation of repo format.
			if ( ! /^[a-zA-Z0-9_-]+$/.test( owner ) || ! /^[a-zA-Z0-9_.-]+$/.test( repo ) ) {
				$error.text( 'Invalid repository format' ).show();
				return;
			}

			$button.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );
			$error.hide();
			$count.hide();

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'deploy_forge_get_workflows',
					nonce: deployForgeAdmin.nonce,
					owner: owner,
					repo: repo
				},
				success: ( response ) => {
					$spinner.removeClass( 'is-active' );
					$button.prop( 'disabled', false );

					if ( response.success && response.data.workflows ) {
						const workflows = response.data.workflows;

						if ( 0 === workflows.length ) {
							$error.text( 'No workflows found. Make sure your repository has .github/workflows/*.yml files.' ).show();
							return;
						}

						// Populate dropdown.
						$dropdown.empty();
						$dropdown.append( $( '<option></option>' ).val( '' ).text( 'Select a workflow...' ) );

						// Check if current value matches any workflow.
						const currentValue = $manualInput.val();
						let matchFound = false;

						workflows.forEach( ( workflow ) => {
							const isSelected = workflow.filename === currentValue;
							if ( isSelected ) {
								matchFound = true;
							}

							$dropdown.append(
								$( '<option></option>' )
									.val( workflow.filename )
									.text( workflow.name + ' (' + workflow.filename + ')' )
									.prop( 'selected', isSelected )
							);
						} );

						// Add "Or enter manually" option.
						$dropdown.append( $( '<option></option>' ).val( '__manual__' ).text( '✏️ Or enter manually...' ) );

						// Show dropdown, hide manual input initially.
						$dropdown.show().prop( 'name', 'github_workflow_name' );
						$manualInput.hide().removeAttr( 'name' );
						$count.text( '✓ ' + workflows.length + ' workflow(s) found' ).show();
					} else {
						$error.text( response.data?.message || 'Failed to load workflows' ).show();
					}
				},
				error: () => {
					$spinner.removeClass( 'is-active' );
					$button.prop( 'disabled', false );
					$error.text( 'An error occurred while loading workflows' ).show();
				}
			} );
		},

		/**
		 * Handle workflow selection from dropdown
		 *
		 * Allows switching back to manual entry.
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		onWorkflowSelect: () => {
			const $dropdown = $( '#github_workflow_dropdown' );
			const $manualInput = $( '#github_workflow_name' );
			const selectedValue = $dropdown.val();

			if ( '__manual__' === selectedValue ) {
				// User wants to enter manually.
				$dropdown.hide().removeAttr( 'name' );
				$manualInput.show().prop( 'name', 'github_workflow_name' ).focus();
				$( '#workflow-count' ).hide();
			} else if ( selectedValue ) {
				// Valid workflow selected - keep it in sync.
				$manualInput.val( selectedValue );
			}
		},

		/**
		 * Check if workflow button should be visible
		 *
		 * Shows button if repo is bound OR selected from dropdown.
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		checkWorkflowButtonVisibility: () => {
			const $workflowButton = $( '#load-workflows-btn' );
			const $repoOwner = $( '#github_repo_owner' );
			const $repoName = $( '#github_repo_name' );
			const $repoSelect = $( '#repo-select' );

			// Show if repo is bound (fields are populated and readonly).
			const isRepoBound = $repoOwner.length && $repoOwner.val() && $repoOwner.prop( 'readonly' );

			// Show if repo is selected from dropdown.
			const isRepoSelected = $repoSelect.length && $repoSelect.val();

			if ( isRepoBound || isRepoSelected ) {
				$workflowButton.show();
			} else {
				$workflowButton.hide();
			}
		},

		/**
		 * Handle deployment method change
		 *
		 * Shows/hides workflow field based on method selection.
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		onDeploymentMethodChange: () => {
			const $deploymentMethod = $( '#deployment_method' );
			const $workflowRow = $( '#workflow-row' );

			if ( ! $deploymentMethod.length ) {
				return;
			}

			const method = $deploymentMethod.val();

			if ( 'direct_clone' === method ) {
				// Hide workflow field for direct clone.
				$workflowRow.hide();
			} else {
				// Show workflow field for GitHub Actions.
				$workflowRow.show();
			}
		},

		/**
		 * Auto-check file changes on page load
		 *
		 * Checks sessionStorage for cached results (5 min TTL), otherwise
		 * fires AJAX to check for file drift on the active deployment.
		 *
		 * @since 1.0.52
		 * @return {void}
		 */
		autoCheckChanges: () => {
			const activeId = deployForgeAdmin.activeDeploymentId;

			if ( ! activeId || activeId <= 0 ) {
				return;
			}

			const cacheKey = 'df_changes_' + activeId;
			const cached = sessionStorage.getItem( cacheKey );

			if ( cached ) {
				try {
					const data = JSON.parse( cached );
					if ( data.timestamp && ( Date.now() - data.timestamp ) < 300000 ) {
						GitHubDeployAdmin.updateDriftIndicator( activeId, data.changes );
						return;
					}
				} catch ( e ) {
					// Invalid cache, continue to fetch.
				}
			}

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_check_changes',
					nonce: deployForgeAdmin.nonce,
					deployment_id: activeId
				},
				success: ( response ) => {
					if ( response.success && response.data.changes ) {
						const changes = response.data.changes;
						sessionStorage.setItem( cacheKey, JSON.stringify( {
							changes: changes,
							timestamp: Date.now()
						} ) );
						GitHubDeployAdmin.updateDriftIndicator( activeId, changes );
					}
				}
			} );
		},

		/**
		 * Update the drift indicator next to the Active badge
		 *
		 * @since 1.0.52
		 * @param {number} deploymentId The deployment ID.
		 * @param {Object} changes      The change report object.
		 * @return {void}
		 */
		updateDriftIndicator: ( deploymentId, changes ) => {
			const $indicator = $( '#drift-indicator-' + deploymentId );

			if ( ! $indicator.length ) {
				return;
			}

			if ( changes.has_changes ) {
				const total = changes.modified_count + changes.added_count + changes.removed_count;
				$indicator.text( total + ' ' + deployForgeAdmin.strings.changesDetected ).show();
			}
		},

		/**
		 * Check for file changes on a deployment
		 *
		 * @since 1.0.52
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		checkChanges: ( e ) => {
			e.preventDefault();

			const button = $( e.target ).closest( 'button' );
			const deploymentId = button.data( 'deployment-id' );
			const originalText = button.text();

			button.prop( 'disabled', true ).text( deployForgeAdmin.strings.checkingChanges );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_check_changes',
					nonce: deployForgeAdmin.nonce,
					deployment_id: deploymentId,
					force: 1
				},
				success: ( response ) => {
					if ( response.success && response.data.changes ) {
						const changes = response.data.changes;

						// Update sessionStorage cache.
						sessionStorage.setItem( 'df_changes_' + deploymentId, JSON.stringify( {
							changes: changes,
							timestamp: Date.now()
						} ) );

						if ( ! changes.has_changes ) {
							button.text( deployForgeAdmin.strings.noChanges ).addClass( 'button-no-changes' );
							setTimeout( () => {
								button.text( originalText ).removeClass( 'button-no-changes' ).prop( 'disabled', false );
							}, 3000 );
							// Hide drift indicator.
							$( '#drift-indicator-' + deploymentId ).hide();
						} else {
							button.text( originalText ).prop( 'disabled', false );
							GitHubDeployAdmin.updateDriftIndicator( deploymentId, changes );
							GitHubDeployAdmin.showChangesModal( deploymentId, changes );
						}
					} else {
						alert( response.data?.message || 'Failed to check changes.' );
						button.text( originalText ).prop( 'disabled', false );
					}
				},
				error: () => {
					alert( 'Failed to check changes.' );
					button.text( originalText ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Show the file changes modal
		 *
		 * @since 1.0.52
		 * @param {number} deploymentId The deployment ID.
		 * @param {Object} changes      The change report object.
		 * @return {void}
		 */
		showChangesModal: ( deploymentId, changes ) => {
			const modal = $( '#file-changes-modal' );
			const summary = $( '#file-changes-summary' );
			const list = $( '#file-changes-list' );

			// Build summary bar.
			let summaryHtml = '';
			if ( changes.modified_count > 0 ) {
				summaryHtml += '<span class="change-count modified">' + changes.modified_count + ' modified</span>';
			}
			if ( changes.added_count > 0 ) {
				summaryHtml += '<span class="change-count added">' + changes.added_count + ' added</span>';
			}
			if ( changes.removed_count > 0 ) {
				summaryHtml += '<span class="change-count removed">' + changes.removed_count + ' removed</span>';
			}
			summaryHtml += '<span class="change-count total">' + changes.total_files + ' total files tracked</span>';
			summary.html( summaryHtml );

			// Build file list table.
			let tableHtml = '<table class="wp-list-table widefat fixed striped">';
			tableHtml += '<thead><tr><th>File</th><th>Type</th><th>Action</th></tr></thead><tbody>';

			changes.modified.forEach( ( file ) => {
				tableHtml += '<tr class="change-modified">';
				tableHtml += '<td><code>' + GitHubDeployAdmin.escapeHtml( file.path ) + '</code></td>';
				tableHtml += '<td><span class="change-badge badge-modified">Modified</span></td>';
				tableHtml += '<td><button type="button" class="button button-small view-diff-btn" ' +
					'data-deployment-id="' + deploymentId + '" ' +
					'data-file-path="' + GitHubDeployAdmin.escapeHtml( file.path ) + '">View Diff</button></td>';
				tableHtml += '</tr>';
			} );

			changes.added.forEach( ( file ) => {
				tableHtml += '<tr class="change-added">';
				tableHtml += '<td><code>' + GitHubDeployAdmin.escapeHtml( file.path ) + '</code></td>';
				tableHtml += '<td><span class="change-badge badge-added">Added</span></td>';
				tableHtml += '<td>' + GitHubDeployAdmin.formatFileSize( file.size ) + '</td>';
				tableHtml += '</tr>';
			} );

			changes.removed.forEach( ( file ) => {
				tableHtml += '<tr class="change-removed">';
				tableHtml += '<td><code>' + GitHubDeployAdmin.escapeHtml( file.path ) + '</code></td>';
				tableHtml += '<td><span class="change-badge badge-removed">Removed</span></td>';
				tableHtml += '<td>File deleted</td>';
				tableHtml += '</tr>';
			} );

			tableHtml += '</tbody></table>';
			list.html( tableHtml );

			modal.show();
		},

		/**
		 * View file diff in modal
		 *
		 * @since 1.0.52
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		viewFileDiff: ( e ) => {
			e.preventDefault();

			const button = $( e.target ).closest( 'button' );
			const deploymentId = button.data( 'deployment-id' );
			const filePath = button.data( 'file-path' );
			const modal = $( '#file-diff-modal' );
			const title = $( '#file-diff-title' );
			const output = $( '#file-diff-content .file-diff-output' );

			title.text( filePath );
			output.html( deployForgeAdmin.strings.loadingDiff );
			modal.show();

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_get_file_diff',
					nonce: deployForgeAdmin.nonce,
					deployment_id: deploymentId,
					file_path: filePath
				},
				success: ( response ) => {
					if ( response.success && response.data.diff ) {
						output.html( GitHubDeployAdmin.syntaxHighlightDiff( response.data.diff.diff ) );
					} else {
						output.text( response.data?.message || 'Failed to load diff.' );
					}
				},
				error: () => {
					output.text( 'Failed to load diff.' );
				}
			} );
		},

		/**
		 * Apply syntax highlighting to unified diff output
		 *
		 * @since 1.0.52
		 * @param {string} diffText Raw unified diff text.
		 * @return {string} HTML with syntax highlighting classes.
		 */
		syntaxHighlightDiff: ( diffText ) => {
			if ( ! diffText ) {
				return '<span class="diff-context">No differences found.</span>';
			}

			const lines = diffText.split( '\n' );
			let html = '';

			lines.forEach( ( line ) => {
				const escaped = GitHubDeployAdmin.escapeHtml( line );

				if ( line.startsWith( '---' ) || line.startsWith( '+++' ) ) {
					html += '<span class="diff-header">' + escaped + '</span>';
				} else if ( line.startsWith( '@@' ) ) {
					html += '<span class="diff-hunk">' + escaped + '</span>';
				} else if ( line.startsWith( '+' ) ) {
					html += '<span class="diff-add">' + escaped + '</span>';
				} else if ( line.startsWith( '-' ) ) {
					html += '<span class="diff-remove">' + escaped + '</span>';
				} else {
					html += '<span class="diff-context">' + escaped + '</span>';
				}
			} );

			return html;
		},

		/**
		 * Escape HTML entities
		 *
		 * @since 1.0.52
		 * @param {string} text Text to escape.
		 * @return {string} Escaped text.
		 */
		escapeHtml: ( text ) => {
			const div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( text ) );
			return div.innerHTML.replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
		},

		/**
		 * Format file size to human-readable string
		 *
		 * @since 1.0.52
		 * @param {number} bytes File size in bytes.
		 * @return {string} Formatted size string.
		 */
		formatFileSize: ( bytes ) => {
			if ( bytes < 1024 ) {
				return bytes + ' B';
			} else if ( bytes < 1048576 ) {
				return ( bytes / 1024 ).toFixed( 1 ) + ' KB';
			}
			return ( bytes / 1048576 ).toFixed( 1 ) + ' MB';
		},

		/**
		 * Format a Unix timestamp as a relative time string
		 *
		 * @since 1.0.55
		 * @param {number} timestamp Unix timestamp in seconds.
		 * @return {string|null} Relative time string, or null if older than 30 days.
		 */
		formatRelativeTime: ( timestamp ) => {
			const now = Math.floor( Date.now() / 1000 );
			const diff = now - timestamp;

			if ( diff < 60 ) {
				return 'just now';
			} else if ( diff < 3600 ) {
				return Math.floor( diff / 60 ) + 'm ago';
			} else if ( diff < 86400 ) {
				return Math.floor( diff / 3600 ) + 'h ago';
			} else if ( diff < 604800 ) {
				const days = Math.floor( diff / 86400 );
				return days + ( 1 === days ? ' day ago' : ' days ago' );
			} else if ( diff < 2592000 ) {
				const weeks = Math.floor( diff / 604800 );
				return weeks + ( 1 === weeks ? ' week ago' : ' weeks ago' );
			}

			return null;
		},

		/**
		 * Initialize relative timestamps on deployment date cells
		 *
		 * Converts absolute dates to relative times and refreshes every 60 seconds.
		 *
		 * @since 1.0.55
		 * @return {void}
		 */
		initRelativeTimestamps: function() {
			const updateTimestamps = () => {
				$( '.deploy-forge-relative-time[data-timestamp]' ).each( function() {
					const $el = $( this );
					const timestamp = parseInt( $el.data( 'timestamp' ), 10 );

					if ( ! timestamp ) {
						return;
					}

					const relative = GitHubDeployAdmin.formatRelativeTime( timestamp );
					if ( relative ) {
						$el.text( relative );
					}
				} );
			};

			updateTimestamps();
			setInterval( updateTimestamps, 60000 );
		},

		/**
		 * Auto-refresh deployment status
		 *
		 * Refreshes status every 30 seconds when active deployments exist.
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		autoRefresh: () => {
			// Auto-refresh status every 30 seconds if on deployments page.
			if ( $( '.deploy-forge-deployments' ).length > 0 ) {
				setInterval( () => {
					// Check for pending, building, queued, or deploying deployments.
					const activeCount = $( '.deployment-status.status-pending, .deployment-status.status-building, .deployment-status.status-queued, .deployment-status.status-deploying' ).length;
					if ( activeCount > 0 ) {
						// Silently refresh status.
						$.ajax( {
							url: deployForgeAdmin.ajaxUrl,
							type: 'POST',
							data: {
								action: 'deploy_forge_get_status',
								nonce: deployForgeAdmin.nonce
							},
							success: ( response ) => {
								if ( response.success ) {
									// Only reload if status changed.
									location.reload();
								}
							}
						} );
					}
				}, 30000 ); // 30 seconds.
			}
		}
	};

	// Initialize when document is ready.
	$( document ).ready( () => {
		GitHubDeployAdmin.init();
	} );
}( jQuery ) );

/**
 * GitHub Repository Selector
 *
 * Handles repository selection and workflow loading on settings page.
 *
 * @since 1.0.0
 */
( function( $ ) {
	'use strict';

	/**
	 * Repository selector controller
	 *
	 * @since 1.0.0
	 * @type {Object}
	 */
	const GitHubRepoSelector = {

		/**
		 * Initialize the repository selector
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		init: function() {
			if ( 0 === $( '.deploy-forge-settings' ).length ) {
				return; // Only run on settings page.
			}

			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		bindEvents: function() {
			$( '#load-repos-btn' ).on( 'click', this.loadRepositories.bind( this ) );
			$( '#repo-selector' ).on( 'change', this.onRepoSelect.bind( this ) );
			$( '#workflow-selector' ).on( 'change', this.onWorkflowSelect.bind( this ) );
		},

		/**
		 * Load repositories from GitHub
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		loadRepositories: ( e ) => {
			if ( e ) {
				e.preventDefault();
			}

			const $button = $( '#load-repos-btn' );
			const $select = $( '#repo-selector' );
			const $spinner = $( '#repo-loading' );

			// Show loading state.
			$button.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );
			$select.html( '<option value="">Loading repositories...</option>' ).prop( 'disabled', true );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_get_repos',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					if ( response.success && response.data.repos ) {
						$select.empty();
						$select.append( '<option value="">-- Select a repository --</option>' );

						response.data.repos.forEach( ( repo ) => {
							const icon = repo.private ? '🔒 ' : '📖 ';
							const workflowBadge = repo.has_workflows ? ' ⚙️' : '';

							$select.append(
								$( '<option></option>' )
									.val( JSON.stringify( {
										owner: repo.owner,
										name: repo.name,
										branch: repo.default_branch
									} ) )
									.text( icon + repo.full_name + workflowBadge )
							);
						} );

						$select.prop( 'disabled', false );

						// Show success message.
						$( '<div class="notice notice-success is-dismissible"><p>Loaded ' + response.data.repos.length + ' repositories!</p></div>' )
							.insertAfter( 'h1' )
							.delay( 3000 )
							.fadeOut();
					} else {
						$select.html( '<option value="">Error loading repositories</option>' );
						alert( response.data?.message || 'Failed to load repositories. Make sure your GitHub token is valid.' );
					}
				},
				error: () => {
					$select.html( '<option value="">Error loading repositories</option>' );
					alert( 'Failed to load repositories. Please check your connection and try again.' );
				},
				complete: () => {
					$button.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );
				}
			} );
		},

		/**
		 * Handle repository selection
		 *
		 * @since 1.0.0
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onRepoSelect: function( e ) {
			const value = $( e.target ).val();

			if ( ! value ) {
				$( '#workflow-selector-row' ).hide();
				return;
			}

			try {
				const repo = JSON.parse( value );

				// Auto-fill manual entry fields.
				$( '#github_repo_owner' ).val( repo.owner );
				$( '#github_repo_name' ).val( repo.name );
				$( '#github_branch' ).val( repo.branch );

				// Load workflows for this repo.
				this.loadWorkflows( repo.owner, repo.name );
			} catch ( err ) {
				console.error( 'Error parsing repo data:', err );
			}
		},

		/**
		 * Load workflows for a repository
		 *
		 * @since 1.0.0
		 * @param {string} owner Repository owner.
		 * @param {string} repo  Repository name.
		 * @return {void}
		 */
		loadWorkflows: ( owner, repo ) => {
			const $row = $( '#workflow-selector-row' );
			const $select = $( '#workflow-selector' );
			const $spinner = $( '#workflow-loading' );

			// Show loading state.
			$row.show();
			$spinner.addClass( 'is-active' );
			$select.html( '<option value="">Loading workflows...</option>' ).prop( 'disabled', true );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_get_workflows',
					nonce: deployForgeAdmin.nonce,
					owner: owner,
					repo: repo
				},
				success: ( response ) => {
					if ( response.success && response.data.workflows ) {
						$select.empty();
						$select.append( '<option value="">-- Select a workflow --</option>' );

						if ( 0 === response.data.workflows.length ) {
							$select.append( '<option value="" disabled>No workflows found</option>' );
						} else {
							response.data.workflows.forEach( ( workflow ) => {
								const stateIcon = 'active' === workflow.state ? '✓ ' : '⚠️ ';

								$select.append(
									$( '<option></option>' )
										.val( workflow.filename )
										.text( stateIcon + workflow.name + ' (' + workflow.filename + ')' )
								);
							} );
						}

						$select.prop( 'disabled', false );
					} else {
						$select.html( '<option value="">No workflows found</option>' );
					}
				},
				error: () => {
					$select.html( '<option value="">Error loading workflows</option>' );
				},
				complete: () => {
					$spinner.removeClass( 'is-active' );
				}
			} );
		},

		/**
		 * Handle workflow selection
		 *
		 * @since 1.0.0
		 * @param {Event} e Change event.
		 * @return {void}
		 */
		onWorkflowSelect: ( e ) => {
			const value = $( e.target ).val();

			if ( value ) {
				$( '#github_workflow_name' ).val( value );
			}
		}
	};

	// Initialize on document ready.
	$( document ).ready( () => {
		GitHubRepoSelector.init();
	} );
}( jQuery ) );

/**
 * Generate Webhook Secret Handler
 *
 * @since 1.0.0
 */
( function( $ ) {
	'use strict';

	/**
	 * Secret generator controller
	 *
	 * @since 1.0.0
	 * @type {Object}
	 */
	const SecretGenerator = {

		/**
		 * Initialize the secret generator
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		init: function() {
			$( '#generate-secret-btn' ).on( 'click', this.generateSecret.bind( this ) );
		},

		/**
		 * Generate a new webhook secret
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 * @return {void}
		 */
		generateSecret: ( e ) => {
			e.preventDefault();

			const $button = $( '#generate-secret-btn' );
			const $spinner = $( '#secret-loading' );

			$button.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );

			$.ajax( {
				url: deployForgeAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'deploy_forge_generate_secret',
					nonce: deployForgeAdmin.nonce
				},
				success: ( response ) => {
					if ( response.success ) {
						$( '#webhook_secret' ).val( response.data.secret );

						// Show success message.
						const $notice = $( '<div class="notice notice-success is-dismissible" style="margin: 15px 0;"><p>Webhook secret generated successfully!</p></div>' );
						$( '.deploy-forge-settings h1' ).after( $notice );

						setTimeout( () => {
							$notice.fadeOut( function() {
								$( this ).remove();
							} );
						}, 3000 );
					} else {
						alert( 'Failed to generate secret: ' + ( response.data.message || 'Unknown error' ) );
					}
				},
				error: () => {
					alert( 'Failed to generate secret. Please try again.' );
				},
				complete: () => {
					$button.prop( 'disabled', false );
					$spinner.removeClass( 'is-active' );
				}
			} );
		}
	};

	$( document ).ready( () => {
		SecretGenerator.init();
	} );
}( jQuery ) );

/**
 * Nonce Refresh Handler
 *
 * Prevents "link expired" errors on settings page by warning
 * users when the page has been open for too long.
 *
 * @since 1.0.0
 */
( function( $ ) {
	'use strict';

	/**
	 * Nonce refresh controller
	 *
	 * @since 1.0.0
	 * @type {Object}
	 */
	const NonceRefresh = {

		/**
		 * Warning time in milliseconds (1 hour)
		 *
		 * @since 1.0.0
		 * @type {number}
		 */
		WARNING_TIME: 60 * 60 * 1000,

		/**
		 * Initialize the nonce refresh handler
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		init: function() {
			if ( 0 === $( '.deploy-forge-settings' ).length ) {
				return; // Only run on settings page.
			}

			this.pageLoadTime = Date.now();
			this.checkExpiration();
		},

		/**
		 * Check for page expiration
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		checkExpiration: function() {
			// Check every 5 minutes.
			setInterval( () => {
				const timeElapsed = Date.now() - this.pageLoadTime;

				// After 1 hour, show warning.
				if ( timeElapsed > this.WARNING_TIME ) {
					this.showWarning();
				}
			}, 5 * 60 * 1000 ); // Check every 5 minutes.
		},

		/**
		 * Show expiration warning
		 *
		 * @since 1.0.0
		 * @return {void}
		 */
		showWarning: function() {
			// Only show once.
			if ( this.warningShown ) {
				return;
			}
			this.warningShown = true;

			const $notice = $(
				'<div class="notice notice-warning is-dismissible" style="margin: 15px 0;">' +
				'<p><strong>Notice:</strong> This page has been open for a while. ' +
				'Please <a href="#" id="refresh-settings-page">refresh the page</a> before saving to avoid security errors.</p>' +
				'</div>'
			);

			$( '.deploy-forge-settings h1' ).after( $notice );

			// Handle refresh click.
			$( '#refresh-settings-page' ).on( 'click', ( e ) => {
				e.preventDefault();
				location.reload();
			} );

			// Auto-dismiss after 10 seconds.
			setTimeout( () => {
				$notice.fadeOut( function() {
					$( this ).remove();
				} );
			}, 10000 );
		}
	};

	// Initialize on document ready.
	$( document ).ready( () => {
		NonceRefresh.init();
	} );
}( jQuery ) );
