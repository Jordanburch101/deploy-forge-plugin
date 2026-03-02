<?php
/**
 * Self-hosted plugin updater via Cloudflare R2 manifest.
 *
 * Integrates with WordPress's native update system to check for new
 * releases from the R2-hosted manifest and allow one-click updates.
 *
 * @package Deploy_Forge
 * @since   1.0.46
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Plugin_Updater
 *
 * Checks the Cloudflare R2 manifest for newer versions of Deploy Forge
 * and injects update data into the WordPress plugin update transient.
 *
 * @since 1.0.46
 */
class Deploy_Forge_Plugin_Updater {

	/**
	 * Transient key for caching release data.
	 *
	 * @since 1.0.46
	 * @var string
	 */
	private const TRANSIENT_KEY = 'deploy_forge_plugin_update';

	/**
	 * Cache duration for successful responses (6 hours).
	 *
	 * @since 1.0.65
	 * @var int
	 */
	private const CACHE_SUCCESS_SECONDS = 6 * HOUR_IN_SECONDS;

	/**
	 * Register WordPress hooks for the update system.
	 *
	 * @since 1.0.46
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_update_cache' ), 10, 2 );
	}

	/**
	 * Check the R2 manifest for a newer plugin version.
	 *
	 * Hooks into the plugin update transient to inject update data
	 * when a newer release is available.
	 *
	 * @since 1.0.46
	 *
	 * @param object $transient The update_plugins transient data.
	 * @return object Modified transient data.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_release_data();

		if ( false === $release || ! is_array( $release ) ) {
			return $transient;
		}

		$remote_version = $release['version'];

		if ( version_compare( DEPLOY_FORGE_VERSION, $remote_version, '<' ) ) {
			$plugin_basename = DEPLOY_FORGE_PLUGIN_BASENAME;

			$transient->response[ $plugin_basename ] = (object) array(
				'slug'         => 'deploy-forge',
				'plugin'       => $plugin_basename,
				'new_version'  => $remote_version,
				'url'          => 'https://getdeployforge.com',
				'package'      => $release['download_url'],
				'icons'        => array(),
				'banners'      => array(),
				'tested'       => $release['tested'] ?? '6.9',
				'requires'     => $release['requires'] ?? '5.8',
				'requires_php' => $release['requires_php'] ?? '8.0',
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the "View details" modal.
	 *
	 * Hooks into plugins_api to return release details from the manifest
	 * when WordPress requests info about this plugin.
	 *
	 * @since 1.0.46
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin info object, or false to use default behavior.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || 'deploy-forge' !== $args->slug ) {
			return $result;
		}

		$release = $this->get_release_data();

		if ( false === $release || ! is_array( $release ) ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = 'Deploy Forge';
		$info->slug          = 'deploy-forge';
		$info->version       = $release['version'];
		$info->author        = '<a href="https://getdeployforge.com">Deploy Forge</a>';
		$info->homepage      = 'https://getdeployforge.com';
		$info->download_link = $release['download_url'];
		$info->tested        = $release['tested'] ?? '6.9';
		$info->requires      = $release['requires'] ?? '5.8';
		$info->requires_php  = $release['requires_php'] ?? '8.0';
		$info->last_updated  = $release['published_at'] ?? '';

		$info->sections = array(
			'description' => '<p>Deploy Forge automates theme deployment from GitHub repositories. '
				. 'When you commit to GitHub, the plugin triggers a build and automatically '
				. 'deploys compiled files to your WordPress theme directory.</p>',
			'changelog'   => $this->markdown_to_html( $release['changelog'] ?? '' ),
		);

		return $info;
	}

	/**
	 * Clear the update cache after a plugin upgrade.
	 *
	 * Deletes the cached release transient so the next update check
	 * fetches fresh data from the manifest.
	 *
	 * @since 1.0.46
	 *
	 * @param \WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array        $hook_extra Extra arguments passed to hooked filters.
	 * @return void
	 */
	public function clear_update_cache( $upgrader, $hook_extra ) {
		if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			if ( in_array( DEPLOY_FORGE_PLUGIN_BASENAME, $hook_extra['plugins'], true ) ) {
				delete_transient( self::TRANSIENT_KEY );
			}
		}
	}

	/**
	 * Get release data from cache or R2 manifest.
	 *
	 * Returns cached data if available, otherwise fetches from the
	 * R2-hosted manifest and caches the result. Successful responses
	 * are cached for 6 hours, failed responses for 1 hour to avoid
	 * hammering the endpoint.
	 *
	 * @since 1.0.46
	 *
	 * @return array|false Release data array on success, false on failure.
	 */
	private function get_release_data() {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( false !== $cached ) {
			// Sentinel value indicates a cached failure.
			if ( 'error' === $cached ) {
				return false;
			}
			return $cached;
		}

		$response = wp_remote_get(
			DEPLOY_FORGE_UPDATE_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'Deploy-Forge/' . DEPLOY_FORGE_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Cache failures for 1 hour to avoid repeated requests.
			set_transient( self::TRANSIENT_KEY, 'error', HOUR_IN_SECONDS );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			set_transient( self::TRANSIENT_KEY, 'error', HOUR_IN_SECONDS );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			set_transient( self::TRANSIENT_KEY, 'error', HOUR_IN_SECONDS );
			return false;
		}

		// Cache successful responses for 6 hours.
		set_transient( self::TRANSIENT_KEY, $data, self::CACHE_SUCCESS_SECONDS );

		return $data;
	}

	/**
	 * Convert basic Markdown to HTML for changelog display.
	 *
	 * Handles headings, bold, italic, links, lists, inline code, and paragraphs.
	 *
	 * @since 1.0.46
	 *
	 * @param string $markdown The Markdown text to convert.
	 * @return string The resulting HTML.
	 */
	private function markdown_to_html( string $markdown ): string {
		if ( empty( $markdown ) ) {
			return '';
		}

		$html = esc_html( $markdown );

		// Headings (### before ## before #).
		$html = (string) preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
		$html = (string) preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );
		$html = (string) preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $html );

		// Bold and italic.
		$html = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
		$html = (string) preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );

		// Inline code.
		$html = (string) preg_replace( '/`(.+?)`/', '<code>$1</code>', $html );

		// Unordered list items.
		$html = (string) preg_replace( '/^[\-\*] (.+)$/m', '<li>$1</li>', $html );
		$html = (string) preg_replace( '/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html );

		// Remove blank lines (they produce excess <br> tags).
		$html = (string) preg_replace( '/\n{2,}/', "\n", $html );

		// Remove newlines directly after block-level closing tags.
		$html = (string) preg_replace( '/(<\/(?:h[1-6]|ul|li|p)>)\n/', '$1', $html );

		// Convert remaining newlines to <br> for inline text.
		$html = nl2br( $html );

		return $html;
	}
}
