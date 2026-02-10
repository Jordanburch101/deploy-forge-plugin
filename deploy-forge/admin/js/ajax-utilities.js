/**
 * Deploy Forge AJAX Utilities
 *
 * Shared AJAX functionality for admin pages and setup wizard.
 * Eliminates duplicate AJAX patterns and Select2 initialization.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

/* global jQuery, deployForgeAdmin, deployForgeWizard */

( function( $ ) {
	'use strict';

	// Global namespace for Deploy Forge AJAX utilities.
	window.DeployForgeAjax = window.DeployForgeAjax || {};

	/**
	 * Make AJAX request with standardized error handling
	 *
	 * @since 1.0.0
	 * @param {Object}   options           AJAX options.
	 * @param {string}   options.action    WordPress AJAX action.
	 * @param {string}   options.nonce     Security nonce.
	 * @param {Object}   options.data      Additional data to send (optional).
	 * @param {Function} options.onSuccess Success callback (receives response data).
	 * @param {Function} options.onError   Error callback (receives error message) (optional).
	 * @param {jQuery}   options.spinner   Spinner element to show/hide (optional).
	 * @param {jQuery}   options.button    Button to disable during request (optional).
	 * @return {jqXHR} jQuery AJAX object.
	 */
	DeployForgeAjax.request = ( options ) => {
		const ajaxUrl = window.deployForgeAdmin?.ajaxUrl ||
			window.deployForgeWizard?.ajaxUrl ||
			'/wp-admin/admin-ajax.php';

		// Show spinner if provided.
		if ( options.spinner ) {
			options.spinner.addClass( 'is-active' ).show();
		}

		// Disable button if provided.
		if ( options.button ) {
			options.button.prop( 'disabled', true );
		}

		// Build request data.
		const requestData = {
			action: options.action,
			nonce: options.nonce,
			...( options.data || {} )
		};

		return $.post( ajaxUrl, requestData )
			.done( ( response ) => {
				if ( response.success ) {
					if ( 'function' === typeof options.onSuccess ) {
						options.onSuccess( response.data || response );
					}
				} else {
					const errorMessage = response.data?.message || 'An error occurred';
					if ( 'function' === typeof options.onError ) {
						options.onError( errorMessage );
					} else {
						DeployForgeAjax.showError( errorMessage );
					}
				}
			} )
			.fail( ( jqXHR, textStatus, errorThrown ) => {
				const errorMessage = 'Request failed: ' + ( errorThrown || textStatus );
				if ( 'function' === typeof options.onError ) {
					options.onError( errorMessage );
				} else {
					DeployForgeAjax.showError( errorMessage );
				}
			} )
			.always( () => {
				// Hide spinner if provided.
				if ( options.spinner ) {
					options.spinner.removeClass( 'is-active' ).hide();
				}

				// Re-enable button if provided.
				if ( options.button ) {
					options.button.prop( 'disabled', false );
				}
			} );
	};

	/**
	 * Load repositories and populate Select2 dropdown
	 *
	 * @since 1.0.0
	 * @param {jQuery}   selectElement Select element to populate.
	 * @param {string}   action        AJAX action name.
	 * @param {string}   nonce         Security nonce.
	 * @param {Function} onSuccess     Callback after successful load (optional).
	 * @param {Function} onError       Callback on error (optional).
	 * @return {jqXHR} jQuery AJAX object.
	 */
	DeployForgeAjax.loadRepositories = ( selectElement, action, nonce, onSuccess, onError ) => {
		// Clear existing options.
		selectElement.empty().append( '<option value="">Loading repositories...</option>' );

		return DeployForgeAjax.request( {
			action: action,
			nonce: nonce,
			onSuccess: ( data ) => {
				const repos = data.repositories || data.data || data;

				// Clear loading message.
				selectElement.empty();

				if ( ! repos || 0 === repos.length ) {
					selectElement.append( '<option value="">No repositories found</option>' );
					if ( 'function' === typeof onError ) {
						onError( 'No repositories found' );
					}
					return;
				}

				// Add placeholder.
				selectElement.append( '<option value="">-- Select a Repository --</option>' );

				// Add repositories.
				repos.forEach( ( repo ) => {
					const option = $( '<option></option>' )
						.val( repo.full_name || repo.name )
						.text( repo.full_name || repo.name )
						.data( 'repo', repo );

					selectElement.append( option );
				} );

				// Initialize or update Select2.
				if ( ! selectElement.hasClass( 'select2-hidden-accessible' ) ) {
					DeployForgeAjax.initSelect2( selectElement, 'Search repositories...' );
				} else {
					selectElement.trigger( 'change.select2' );
				}

				if ( 'function' === typeof onSuccess ) {
					onSuccess( repos );
				}
			},
			onError: ( errorMessage ) => {
				selectElement.empty().append( '<option value="">Failed to load repositories</option>' );
				if ( 'function' === typeof onError ) {
					onError( errorMessage );
				} else {
					DeployForgeAjax.showError( errorMessage );
				}
			}
		} );
	};

	/**
	 * Load workflows and populate Select2 dropdown
	 *
	 * @since 1.0.0
	 * @param {string}   owner         Repository owner.
	 * @param {string}   repo          Repository name.
	 * @param {jQuery}   selectElement Select element to populate.
	 * @param {string}   action        AJAX action name.
	 * @param {string}   nonce         Security nonce.
	 * @param {Function} onSuccess     Callback after successful load (optional).
	 * @param {Function} onError       Callback on error (optional).
	 * @return {jqXHR} jQuery AJAX object.
	 */
	DeployForgeAjax.loadWorkflows = ( owner, repo, selectElement, action, nonce, onSuccess, onError ) => {
		// Clear existing options.
		selectElement.empty().append( '<option value="">Loading workflows...</option>' );

		return DeployForgeAjax.request( {
			action: action,
			nonce: nonce,
			data: {
				owner: owner,
				repo: repo
			},
			onSuccess: ( data ) => {
				const workflows = data.workflows || data.data || data;

				// Clear loading message.
				selectElement.empty();

				if ( ! workflows || 0 === workflows.length ) {
					selectElement.append( '<option value="">No workflows found</option>' );
					if ( 'function' === typeof onError ) {
						onError( 'No workflows found' );
					}
					return;
				}

				// Add placeholder.
				selectElement.append( '<option value="">-- Select a Workflow --</option>' );

				// Add workflows.
				workflows.forEach( ( workflow ) => {
					const option = $( '<option></option>' )
						.val( workflow.filename || workflow.name )
						.text( workflow.name + ' (' + ( workflow.filename || '' ) + ')' )
						.data( 'workflow', workflow );

					selectElement.append( option );
				} );

				// Initialize or update Select2.
				if ( ! selectElement.hasClass( 'select2-hidden-accessible' ) ) {
					DeployForgeAjax.initSelect2( selectElement, 'Search workflows...' );
				} else {
					selectElement.trigger( 'change.select2' );
				}

				if ( 'function' === typeof onSuccess ) {
					onSuccess( workflows );
				}
			},
			onError: ( errorMessage ) => {
				selectElement.empty().append( '<option value="">Failed to load workflows</option>' );
				if ( 'function' === typeof onError ) {
					onError( errorMessage );
				} else {
					DeployForgeAjax.showError( errorMessage );
				}
			}
		} );
	};

	/**
	 * Load branches and populate Select2 dropdown
	 *
	 * @since 1.0.0
	 * @param {jQuery}   selectElement Select element to populate.
	 * @param {string}   action        AJAX action name.
	 * @param {string}   nonce         Security nonce.
	 * @param {Function} onSuccess     Callback after successful load (optional).
	 * @param {Function} onError       Callback on error (optional).
	 * @return {jqXHR} jQuery AJAX object.
	 */
	DeployForgeAjax.loadBranches = ( selectElement, action, nonce, onSuccess, onError ) => {
		// Clear existing options.
		selectElement.empty().append( '<option value="">Loading branches...</option>' );

		return DeployForgeAjax.request( {
			action: action,
			nonce: nonce,
			onSuccess: ( data ) => {
				const branches = data.branches || data.data || data;

				// Clear loading message.
				selectElement.empty();

				if ( ! branches || 0 === branches.length ) {
					selectElement.append( '<option value="">No branches found</option>' );
					if ( 'function' === typeof onError ) {
						onError( 'No branches found' );
					}
					return;
				}

				// Add placeholder.
				selectElement.append( '<option value="">-- Select a Branch --</option>' );

				// Add branches.
				branches.forEach( ( branch ) => {
					const branchName = 'string' === typeof branch ? branch : branch.name || branch.label;
					const option = $( '<option></option>' )
						.val( branchName )
						.text( branchName );

					selectElement.append( option );
				} );

				// Initialize or update Select2.
				if ( ! selectElement.hasClass( 'select2-hidden-accessible' ) ) {
					DeployForgeAjax.initSelect2( selectElement, 'Search branches...' );
				} else {
					selectElement.trigger( 'change.select2' );
				}

				if ( 'function' === typeof onSuccess ) {
					onSuccess( branches );
				}
			},
			onError: ( errorMessage ) => {
				selectElement.empty().append( '<option value="">Failed to load branches</option>' );
				if ( 'function' === typeof onError ) {
					onError( errorMessage );
				} else {
					DeployForgeAjax.showError( errorMessage );
				}
			}
		} );
	};

	/**
	 * Initialize Select2 on an element
	 *
	 * @since 1.0.0
	 * @param {jQuery} element       Element to initialize Select2 on.
	 * @param {string} placeholder   Placeholder text.
	 * @param {Object} customOptions Additional Select2 options (optional).
	 * @return {boolean} True if initialized, false if Select2 not available.
	 */
	DeployForgeAjax.initSelect2 = ( element, placeholder, customOptions ) => {
		if ( 'undefined' === typeof $.fn.select2 ) {
			console.error( 'Select2 not loaded' );
			return false;
		}

		const defaultOptions = {
			placeholder: placeholder || 'Select an option...',
			width: '100%',
			allowClear: true
		};

		const options = $.extend( {}, defaultOptions, customOptions || {} );

		element.select2( options );

		return true;
	};

	/**
	 * Show error message
	 *
	 * @since 1.0.0
	 * @param {string} message   Error message.
	 * @param {jQuery} container Container to show message in (optional).
	 * @return {void}
	 */
	DeployForgeAjax.showError = ( message, container ) => {
		const errorHtml = '<div class="deploy-forge-message error" style="margin: 15px 0; padding: 10px 15px; border-left: 4px solid #d63638; background: #fcf0f1;">' +
			'<p style="margin: 0;">' + message + '</p>' +
			'</div>';

		if ( container && container.length ) {
			container.html( errorHtml );
		} else {
			// Try to find a common error container.
			const $errorContainer = $( '#deploy-forge-error, .deploy-forge-error-container, .wizard-error-container' ).first();
			if ( $errorContainer.length ) {
				$errorContainer.html( errorHtml ).show();
			} else {
				console.error( 'Deploy Forge Error:', message );
				alert( 'Error: ' + message );
			}
		}
	};

	/**
	 * Show success message
	 *
	 * @since 1.0.0
	 * @param {string} message   Success message.
	 * @param {jQuery} container Container to show message in (optional).
	 * @return {void}
	 */
	DeployForgeAjax.showSuccess = ( message, container ) => {
		const successHtml = '<div class="deploy-forge-message success" style="margin: 15px 0; padding: 10px 15px; border-left: 4px solid #00a32a; background: #f0f6f0;">' +
			'<p style="margin: 0;">' + message + '</p>' +
			'</div>';

		if ( container && container.length ) {
			container.html( successHtml );
		} else {
			// Try to find a common success container.
			const $successContainer = $( '#deploy-forge-success, .deploy-forge-success-container, .wizard-success-container' ).first();
			if ( $successContainer.length ) {
				$successContainer.html( successHtml ).show();
			}
		}
	};

	/**
	 * Clear message
	 *
	 * @since 1.0.0
	 * @param {jQuery} container Container to clear (optional).
	 * @return {void}
	 */
	DeployForgeAjax.clearMessage = ( container ) => {
		if ( container && container.length ) {
			container.empty().hide();
		} else {
			$( '.deploy-forge-message, .wizard-message' ).remove();
		}
	};
}( jQuery ) );
