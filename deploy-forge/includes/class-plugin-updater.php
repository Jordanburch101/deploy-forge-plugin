<?php
/**
 * Self-hosted plugin updater via GitHub Releases.
 *
 * Integrates with WordPress's native update system to check for new
 * releases on the public GitHub repository and allow one-click updates.
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
 * Checks the GitHub Releases API for newer versions of Deploy Forge
 * and injects update data into the WordPress plugin update transient.
 *
 * @since 1.0.46
 */
class Deploy_Forge_Plugin_Updater {

	/**
	 * GitHub API URL for the latest release.
	 *
	 * @since 1.0.46
	 * @var string
	 */
	private const GITHUB_API_URL = 'https://api.github.com/repos/Jordanburch101/deploy-forge-plugin/releases/latest';

	/**
	 * Transient key for caching release data.
	 *
	 * @since 1.0.46
	 * @var string
	 */
	private const TRANSIENT_KEY = 'deploy_forge_plugin_update';

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
	 * Check GitHub for a newer plugin version.
	 *
	 * Hooks into the plugin update transient to inject update data
	 * when a newer release is available on GitHub.
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
				'url'          => $release['html_url'],
				'package'      => $release['download_url'],
				'icons'        => array(),
				'banners'      => array(),
				'tested'       => '6.7',
				'requires'     => '5.8',
				'requires_php' => '8.0',
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the "View details" modal.
	 *
	 * Hooks into plugins_api to return release details from GitHub
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
		$info->tested        = '6.7';
		$info->requires      = '5.8';
		$info->requires_php  = '8.0';
		$info->last_updated  = $release['published_at'];

		$info->sections = array(
			'description' => '<p>Deploy Forge automates theme deployment from GitHub repositories. '
				. 'When you commit to GitHub, the plugin triggers a build and automatically '
				. 'deploys compiled files to your WordPress theme directory.</p>',
			'changelog'   => $this->markdown_to_html( $release['body'] ),
		);

		return $info;
	}

	/**
	 * Clear the update cache after a plugin upgrade.
	 *
	 * Deletes the cached release transient so the next update check
	 * fetches fresh data from GitHub.
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
	 * Get release data from cache or GitHub API.
	 *
	 * Returns cached data if available, otherwise fetches from GitHub
	 * and caches the result. Successful responses are cached for 12 hours,
	 * failed responses for 1 hour to avoid hammering the API.
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

		$response = $this->github_api_request( self::GITHUB_API_URL );

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

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			set_transient( self::TRANSIENT_KEY, 'error', HOUR_IN_SECONDS );
			return false;
		}

		// Find the ZIP asset from the release.
		$download_url = '';
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] )
					&& str_ends_with( $asset['browser_download_url'], '.zip' )
				) {
					$download_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		if ( empty( $download_url ) ) {
			set_transient( self::TRANSIENT_KEY, 'error', HOUR_IN_SECONDS );
			return false;
		}

		$release_data = array(
			'tag_name'     => $data['tag_name'],
			'version'      => $this->parse_version( $data['tag_name'] ),
			'download_url' => $download_url,
			'body'         => $data['body'] ?? '',
			'published_at' => $data['published_at'] ?? '',
			'html_url'     => $data['html_url'] ?? '',
		);

		// Cache successful responses for 12 hours.
		set_transient( self::TRANSIENT_KEY, $release_data, 12 * HOUR_IN_SECONDS );

		return $release_data;
	}

	/**
	 * Make a request to the GitHub API.
	 *
	 * Sends a GET request with the required User-Agent header.
	 *
	 * @since 1.0.46
	 *
	 * @param string $url The API URL to request.
	 * @return array|\WP_Error Response array on success, WP_Error on failure.
	 */
	private function github_api_request( string $url ) {
		return wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'Deploy-Forge/' . DEPLOY_FORGE_VERSION,
				),
			)
		);
	}

	/**
	 * Parse a version string from a Git tag name.
	 *
	 * Strips the leading "v" prefix if present (e.g. "v1.0.46" becomes "1.0.46").
	 *
	 * @since 1.0.46
	 *
	 * @param string $tag_name The Git tag name.
	 * @return string The cleaned version string.
	 */
	private function parse_version( string $tag_name ): string {
		return ltrim( $tag_name, 'v' );
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

		// Line breaks for remaining lines.
		$html = nl2br( $html );

		return $html;
	}
}
