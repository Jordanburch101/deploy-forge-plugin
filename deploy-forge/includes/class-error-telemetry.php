<?php
/**
 * Error telemetry class.
 *
 * Initializes Sentry error reporting for the Deploy Forge plugin.
 * Uses a scoped Sentry SDK to avoid namespace conflicts with other plugins.
 * Only captures errors originating from Deploy Forge code.
 *
 * @package Deploy_Forge
 * @since   1.0.58
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Error_Telemetry
 *
 * Handles opt-out error reporting via Sentry.
 *
 * @since 1.0.58
 */
class Deploy_Forge_Error_Telemetry {

	/**
	 * Sentry DSN for the Deploy Forge project.
	 *
	 * @since 1.0.58
	 * @var string
	 */
	private const SENTRY_DSN = 'https://538d988b15fa4dc39b1bb76571c5240b@o4507877259608064.ingest.us.sentry.io/4510861141540864';

	/**
	 * Settings instance.
	 *
	 * @since 1.0.58
	 * @var Deploy_Forge_Settings
	 */
	private Deploy_Forge_Settings $settings;

	/**
	 * Whether Sentry has been initialized.
	 *
	 * @since 1.0.58
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.58
	 *
	 * @param Deploy_Forge_Settings $settings Settings instance.
	 */
	public function __construct( Deploy_Forge_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Initialize Sentry error reporting.
	 *
	 * Checks the opt-out setting, loads the scoped autoloader, and
	 * initializes Sentry with filtering to only capture plugin errors.
	 *
	 * @since 1.0.58
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		// Respect user opt-out.
		if ( ! $this->settings->get( 'error_telemetry', true ) ) {
			return;
		}

		$autoloader = DEPLOY_FORGE_PLUGIN_DIR . 'vendor-prefixed/vendor/autoload.php';

		if ( ! file_exists( $autoloader ) ) {
			return;
		}

		try {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Scoped autoloader path.
			require_once $autoloader;

			\DeployForge\Vendor\Sentry\init(
				array(
					'dsn'                  => self::SENTRY_DSN,
					'environment'          => 'production',
					'release'              => defined( 'DEPLOY_FORGE_VERSION' ) ? DEPLOY_FORGE_VERSION : '0.0.0',
					'sample_rate'          => 1.0,
					'send_default_pii'     => false,
					'default_integrations' => true,
					'before_send'          => function ( \DeployForge\Vendor\Sentry\Event $event, ?\DeployForge\Vendor\Sentry\EventHint $hint ): ?\DeployForge\Vendor\Sentry\Event {
						return $this->filter_event( $event, $hint );
					},
				)
			);

			// Set context tags.
			\DeployForge\Vendor\Sentry\configureScope(
				function ( \DeployForge\Vendor\Sentry\State\Scope $scope ): void {
					$scope->setTag( 'wp_version', get_bloginfo( 'version' ) );
					$scope->setTag( 'php_version', PHP_VERSION );
					$scope->setTag( 'plugin_version', defined( 'DEPLOY_FORGE_VERSION' ) ? DEPLOY_FORGE_VERSION : '0.0.0' );
				}
			);

			$this->initialized = true;
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentionally silent.
			// Sentry init failure must never break the plugin.
		}
	}

	/**
	 * Filter Sentry events to only include Deploy Forge errors.
	 *
	 * @since 1.0.58
	 *
	 * @param \DeployForge\Vendor\Sentry\Event          $event The Sentry event.
	 * @param \DeployForge\Vendor\Sentry\EventHint|null $hint  The event hint with original exception.
	 * @return \DeployForge\Vendor\Sentry\Event|null The event to send, or null to discard.
	 */
	private function filter_event( \DeployForge\Vendor\Sentry\Event $event, ?\DeployForge\Vendor\Sentry\EventHint $hint ): ?\DeployForge\Vendor\Sentry\Event {
		// Check the original exception's file path.
		if ( null !== $hint && null !== $hint->exception ) {
			$file = $hint->exception->getFile();
			if ( $this->is_plugin_file( $file ) ) {
				return $event;
			}
		}

		// Check stack trace frames for plugin origin.
		$exceptions = $event->getExceptions();
		if ( ! empty( $exceptions ) ) {
			foreach ( $exceptions as $exception_data ) {
				$stacktrace = $exception_data->getStacktrace();
				if ( null === $stacktrace ) {
					continue;
				}

				foreach ( $stacktrace->getFrames() as $frame ) {
					$abs_path = $frame->getAbsoluteFilePath();
					if ( null !== $abs_path && $this->is_plugin_file( $abs_path ) ) {
						return $event;
					}
				}
			}
		}

		// Not from our plugin — discard.
		return null;
	}

	/**
	 * Check if a file path belongs to the Deploy Forge plugin.
	 *
	 * @since 1.0.58
	 *
	 * @param string $file_path The absolute file path to check.
	 * @return bool True if the file is part of Deploy Forge.
	 */
	private function is_plugin_file( string $file_path ): bool {
		// Normalize directory separators.
		$normalized = str_replace( '\\', '/', $file_path );

		return false !== strpos( $normalized, '/deploy-forge/' );
	}
}
