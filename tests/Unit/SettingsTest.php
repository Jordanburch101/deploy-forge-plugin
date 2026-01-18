<?php
/**
 * Tests for Deploy_Forge_Settings class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Test class for settings management.
 */
class SettingsTest extends TestCase {

	/**
	 * Set up before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress options functions.
		Functions\stubs(
			array(
				'get_option'    => static function ( $option, $default = false ) {
					// Return defaults for most options.
					$options = array(
						'admin_email' => 'admin@example.com',
					);
					return $options[ $option ] ?? $default;
				},
				'update_option' => '__return_true',
				'delete_option' => '__return_true',
				'current_time'  => static function ( $type ) {
					return '2025-01-15 10:30:00';
				},
			)
		);

		// Load the class under test.
		require_once DEPLOY_FORGE_PLUGIN_DIR . 'includes/class-settings.php';
	}

	/**
	 * Test get method returns setting value.
	 *
	 * @return void
	 */
	public function test_get_returns_setting_value(): void {
		$settings = new \Deploy_Forge_Settings();

		// Test default values.
		$this->assertSame( 'main', $settings->get( 'github_branch' ) );
		$this->assertSame( 'deploy-theme.yml', $settings->get( 'github_workflow_name' ) );
		$this->assertSame( 'github_actions', $settings->get( 'deployment_method' ) );
		$this->assertTrue( $settings->get( 'create_backups' ) );
		$this->assertFalse( $settings->get( 'debug_mode' ) );
	}

	/**
	 * Test get method returns default when key doesn't exist.
	 *
	 * @return void
	 */
	public function test_get_returns_default_for_unknown_key(): void {
		$settings = new \Deploy_Forge_Settings();

		$this->assertNull( $settings->get( 'unknown_key' ) );
		$this->assertSame( 'custom_default', $settings->get( 'unknown_key', 'custom_default' ) );
	}

	/**
	 * Test get_all returns all settings.
	 *
	 * @return void
	 */
	public function test_get_all_returns_all_settings(): void {
		$settings = new \Deploy_Forge_Settings();
		$all      = $settings->get_all();

		$this->assertIsArray( $all );
		$this->assertArrayHasKey( 'github_repo_owner', $all );
		$this->assertArrayHasKey( 'github_repo_name', $all );
		$this->assertArrayHasKey( 'github_branch', $all );
		$this->assertArrayHasKey( 'deployment_method', $all );
		$this->assertArrayHasKey( 'create_backups', $all );
		$this->assertArrayHasKey( 'debug_mode', $all );
	}

	/**
	 * Test set_api_key validates format.
	 *
	 * @return void
	 */
	public function test_set_api_key_validates_format(): void {
		$settings = new \Deploy_Forge_Settings();

		// Valid API key format.
		$this->assertTrue( $settings->set_api_key( 'df_live_a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4' ) );

		// Invalid API key formats.
		$this->assertFalse( $settings->set_api_key( 'invalid_key' ) );
		$this->assertFalse( $settings->set_api_key( 'df_live_tooshort' ) );
		$this->assertFalse( $settings->set_api_key( 'df_test_a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4' ) ); // Wrong prefix.
	}

	/**
	 * Test set_api_key with empty string returns true (deletes option).
	 *
	 * @return void
	 */
	public function test_set_api_key_empty_deletes_option(): void {
		// delete_option is stubbed to return true in setUp.
		$settings = new \Deploy_Forge_Settings();
		$result   = $settings->set_api_key( '' );

		// The method should return true when deleting.
		$this->assertTrue( $result );
	}

	/**
	 * Test set_webhook_secret validates format.
	 *
	 * @return void
	 */
	public function test_set_webhook_secret_validates_format(): void {
		$settings = new \Deploy_Forge_Settings();

		// Valid webhook secret format.
		$this->assertTrue( $settings->set_webhook_secret( 'whsec_a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4' ) );

		// Invalid webhook secret formats.
		$this->assertFalse( $settings->set_webhook_secret( 'invalid_secret' ) );
		$this->assertFalse( $settings->set_webhook_secret( 'whsec_tooshort' ) );
		$this->assertFalse( $settings->set_webhook_secret( 'wh_a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4' ) ); // Wrong prefix.
	}

	/**
	 * Test is_connected returns true when all credentials are set.
	 *
	 * @return void
	 */
	public function test_is_connected_returns_true_when_all_set(): void {
		Functions\stubs(
			array(
				'get_option' => static function ( $option, $default = false ) {
					$options = array(
						'deploy_forge_api_key'        => 'df_live_a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
						'deploy_forge_webhook_secret' => 'whsec_a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4',
						'deploy_forge_site_id'        => 'site_123',
						'admin_email'                 => 'admin@example.com',
					);
					return $options[ $option ] ?? $default;
				},
			)
		);

		$settings = new \Deploy_Forge_Settings();
		$this->assertTrue( $settings->is_connected() );
	}

	/**
	 * Test is_connected returns false when any credential is missing.
	 *
	 * @return void
	 */
	public function test_is_connected_returns_false_when_credentials_missing(): void {
		// Default stubs return empty strings for credentials.
		$settings = new \Deploy_Forge_Settings();
		$this->assertFalse( $settings->is_connected() );
	}

	/**
	 * Test get_connection_data returns defaults.
	 *
	 * @return void
	 */
	public function test_get_connection_data_returns_defaults(): void {
		$settings = new \Deploy_Forge_Settings();
		$data     = $settings->get_connection_data();

		$this->assertIsArray( $data );
		$this->assertSame( '', $data['installation_id'] );
		$this->assertSame( '', $data['repo_owner'] );
		$this->assertSame( '', $data['repo_name'] );
		$this->assertSame( 'main', $data['repo_branch'] );
		$this->assertSame( 'github_actions', $data['deployment_method'] );
	}

	/**
	 * Test is_repo_configured returns false when not configured.
	 *
	 * @return void
	 */
	public function test_is_repo_configured_returns_false_when_not_configured(): void {
		$settings = new \Deploy_Forge_Settings();
		$this->assertFalse( $settings->is_repo_configured() );
	}

	/**
	 * Test is_repo_configured returns true when configured.
	 *
	 * @return void
	 */
	public function test_is_repo_configured_returns_true_when_configured(): void {
		Functions\stubs(
			array(
				'get_option' => static function ( $option, $default = false ) {
					if ( 'deploy_forge_connection_data' === $option ) {
						return array(
							'repo_owner' => 'owner',
							'repo_name'  => 'repo',
						);
					}
					return $default;
				},
			)
		);

		$settings = new \Deploy_Forge_Settings();
		$this->assertTrue( $settings->is_repo_configured() );
	}

	/**
	 * Test get_backend_url returns default URL.
	 *
	 * @return void
	 */
	public function test_get_backend_url_returns_default(): void {
		$settings = new \Deploy_Forge_Settings();
		$this->assertSame( 'https://deploy-forge-website.vercel.app', $settings->get_backend_url() );
	}

	/**
	 * Test get_repo_full_name formats correctly.
	 *
	 * @return void
	 */
	public function test_get_repo_full_name_formats_correctly(): void {
		Functions\stubs(
			array(
				'get_option' => static function ( $option, $default = false ) {
					if ( 'deploy_forge_connection_data' === $option ) {
						return array(
							'repo_owner' => 'myowner',
							'repo_name'  => 'myrepo',
						);
					}
					return $default;
				},
			)
		);

		$settings = new \Deploy_Forge_Settings();
		$this->assertSame( 'myowner/myrepo', $settings->get_repo_full_name() );
	}

	/**
	 * Test get_theme_path returns correct path.
	 *
	 * @return void
	 */
	public function test_get_theme_path_returns_correct_path(): void {
		Functions\stubs(
			array(
				'get_option' => static function ( $option, $default = false ) {
					if ( 'deploy_forge_connection_data' === $option ) {
						return array(
							'repo_name' => 'my-theme',
						);
					}
					return $default;
				},
			)
		);

		$settings = new \Deploy_Forge_Settings();
		$this->assertSame( WP_CONTENT_DIR . '/themes/my-theme', $settings->get_theme_path() );
	}

	/**
	 * Test validate returns errors when not connected.
	 *
	 * @return void
	 */
	public function test_validate_returns_errors_when_not_connected(): void {
		$settings = new \Deploy_Forge_Settings();
		$errors   = $settings->validate();

		$this->assertNotEmpty( $errors );
		$this->assertContains( 'Not connected to Deploy Forge. Please connect your site.', $errors );
	}

	/**
	 * Test is_configured returns false when not fully configured.
	 *
	 * @return void
	 */
	public function test_is_configured_returns_false_when_not_configured(): void {
		$settings = new \Deploy_Forge_Settings();
		$this->assertFalse( $settings->is_configured() );
	}

	/**
	 * Test legacy method is_github_connected maps to is_connected.
	 *
	 * @return void
	 */
	public function test_legacy_is_github_connected_maps_to_is_connected(): void {
		$settings = new \Deploy_Forge_Settings();
		$this->assertSame( $settings->is_connected(), $settings->is_github_connected() );
	}

	/**
	 * Test legacy method is_repo_bound maps to is_repo_configured.
	 *
	 * @return void
	 */
	public function test_legacy_is_repo_bound_maps_to_is_repo_configured(): void {
		$settings = new \Deploy_Forge_Settings();
		$this->assertSame( $settings->is_repo_configured(), $settings->is_repo_bound() );
	}
}
