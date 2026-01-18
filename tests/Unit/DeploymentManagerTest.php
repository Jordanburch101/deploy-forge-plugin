<?php
/**
 * Tests for Deploy_Forge_Deployment_Manager class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

// Load the class file.
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-deployment-manager.php';

/**
 * Test case for the Deployment Manager class.
 *
 * Tests deployment workflow, backup/rollback, and status management.
 */
class DeploymentManagerTest extends TestCase {

	/**
	 * Deployment Manager instance.
	 *
	 * @var \Deploy_Forge_Deployment_Manager
	 */
	private \Deploy_Forge_Deployment_Manager $manager;

	/**
	 * Mock settings instance.
	 *
	 * @var Mockery\MockInterface
	 */
	private $mock_settings;

	/**
	 * Mock GitHub API instance.
	 *
	 * @var Mockery\MockInterface
	 */
	private $mock_github_api;

	/**
	 * Mock database instance.
	 *
	 * @var Mockery\MockInterface
	 */
	private $mock_database;

	/**
	 * Mock logger instance.
	 *
	 * @var Mockery\MockInterface
	 */
	private $mock_logger;

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_settings   = $this->create_mock_settings();
		$this->mock_github_api = $this->create_mock_github_api();
		$this->mock_database   = $this->create_mock_database();
		$this->mock_logger     = $this->create_mock_logger();

		// Set up common function mocks.
		$this->setup_cron_mocks();
		$this->setup_filesystem_mocks();

		// Mock do_action.
		Functions\when( 'do_action' )->justReturn( null );

		$this->manager = new \Deploy_Forge_Deployment_Manager(
			$this->mock_settings,
			$this->mock_github_api,
			$this->mock_database,
			$this->mock_logger
		);
	}

	/**
	 * Test start_deployment creates database record.
	 *
	 * @return void
	 */
	public function test_start_deployment_creates_record(): void {
		$this->mock_database->shouldReceive( 'get_building_deployment' )->andReturn( null );
		$this->mock_database->shouldReceive( 'insert_deployment' )
			->once()
			->with( Mockery::on( function ( $data ) {
				return isset( $data['commit_hash'] )
					&& 'abc123' === $data['commit_hash']
					&& 'pending' === $data['status']
					&& 'webhook' === $data['trigger_type'];
			} ) )
			->andReturn( 1 );

		$this->mock_github_api->shouldReceive( 'trigger_workflow' )
			->andReturn( array( 'success' => true ) );

		$this->mock_database->shouldReceive( 'update_deployment' )
			->andReturn( true );

		$result = $this->manager->start_deployment( 'abc123', 'webhook', 0 );

		$this->assertEquals( 1, $result, 'Should return deployment ID' );
	}

	/**
	 * Test start_deployment triggers GitHub workflow.
	 *
	 * @return void
	 */
	public function test_start_deployment_triggers_workflow(): void {
		$this->mock_database->shouldReceive( 'get_building_deployment' )->andReturn( null );
		$this->mock_database->shouldReceive( 'insert_deployment' )->andReturn( 1 );
		$this->mock_database->shouldReceive( 'update_deployment' )->andReturn( true );

		$this->mock_github_api->shouldReceive( 'trigger_workflow' )
			->once()
			->with( 'build.yml', 'main' )
			->andReturn( array( 'success' => true ) );

		$result = $this->manager->start_deployment( 'abc123', 'webhook', 0 );

		$this->assertEquals( 1, $result );
	}

	/**
	 * Test start_deployment blocks when deployment in progress.
	 *
	 * @return void
	 */
	public function test_start_deployment_blocks_when_in_progress(): void {
		$existing_deployment = (object) array(
			'id'     => 5,
			'status' => 'building',
		);

		$this->mock_database->shouldReceive( 'get_building_deployment' )
			->andReturn( $existing_deployment );

		$result = $this->manager->start_deployment( 'abc123', 'manual', 1 );

		$this->assertIsArray( $result, 'Should return error array' );
		$this->assertEquals( 'deployment_in_progress', $result['error'] );
		$this->assertSame( $existing_deployment, $result['building_deployment'] );
	}

	/**
	 * Test start_deployment auto-cancels for webhook triggers.
	 *
	 * @return void
	 */
	public function test_start_deployment_auto_cancels_for_webhook(): void {
		$existing_deployment = (object) array(
			'id'              => 5,
			'status'          => 'building',
			'workflow_run_id' => null,
		);

		$this->mock_database->shouldReceive( 'get_building_deployment' )
			->once()
			->andReturn( $existing_deployment );

		// Expect cancel deployment to be called.
		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 5 )
			->andReturn( $existing_deployment );

		$this->mock_database->shouldReceive( 'update_deployment' )
			->andReturn( true );

		$this->mock_database->shouldReceive( 'insert_deployment' )->andReturn( 2 );

		$this->mock_github_api->shouldReceive( 'trigger_workflow' )
			->andReturn( array( 'success' => true ) );

		$result = $this->manager->start_deployment( 'def456', 'webhook', 0 );

		$this->assertEquals( 2, $result, 'Should return new deployment ID' );
	}

	/**
	 * Test trigger_github_build updates status to building.
	 *
	 * @return void
	 */
	public function test_trigger_github_build_updates_status(): void {
		$this->mock_github_api->shouldReceive( 'trigger_workflow' )
			->once()
			->andReturn( array( 'success' => true ) );

		$this->mock_database->shouldReceive( 'update_deployment' )
			->once()
			->with( 1, Mockery::on( function ( $data ) {
				return isset( $data['status'] ) && 'building' === $data['status'];
			} ) )
			->andReturn( true );

		$this->mock_database->shouldReceive( 'get_deployment' )
			->andReturn( (object) array( 'deployment_logs' => '' ) );

		$result = $this->manager->trigger_github_build( 1, 'abc123' );

		$this->assertTrue( $result, 'Should return true on success' );
	}

	/**
	 * Test trigger_github_build fails without workflow configured.
	 *
	 * @return void
	 */
	public function test_trigger_github_build_fails_without_workflow(): void {
		// Create settings mock without workflow configured.
		$settings = Mockery::mock( 'Deploy_Forge_Settings' );
		$settings->shouldReceive( 'get_connection_data' )->andReturn( array() );
		$settings->shouldReceive( 'get' )
			->with( 'github_workflow_name' )
			->andReturn( '' );
		$settings->shouldReceive( 'get' )
			->with( 'github_branch' )
			->andReturn( 'main' );

		$this->mock_database->shouldReceive( 'get_deployment' )
			->andReturn( (object) array( 'deployment_logs' => '' ) );

		$this->mock_database->shouldReceive( 'update_deployment' )->andReturn( true );

		$manager = new \Deploy_Forge_Deployment_Manager(
			$settings,
			$this->mock_github_api,
			$this->mock_database,
			$this->mock_logger
		);

		$result = $manager->trigger_github_build( 1, 'abc123' );

		$this->assertFalse( $result, 'Should fail without workflow' );
	}

	/**
	 * Test cancel_deployment updates status.
	 *
	 * @return void
	 */
	public function test_cancel_deployment_updates_status(): void {
		$deployment = (object) array(
			'id'              => 1,
			'status'          => 'building',
			'workflow_run_id' => 12345,
		);

		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 1 )
			->andReturn( $deployment );

		$this->mock_github_api->shouldReceive( 'cancel_workflow_run' )
			->with( 12345 )
			->andReturn( array( 'success' => true ) );

		$this->mock_database->shouldReceive( 'update_deployment' )
			->once()
			->with( 1, Mockery::on( function ( $data ) {
				return isset( $data['status'] ) && 'cancelled' === $data['status'];
			} ) )
			->andReturn( true );

		$result = $this->manager->cancel_deployment( 1 );

		$this->assertTrue( $result, 'Cancel should succeed' );
	}

	/**
	 * Test cancel_deployment fails for completed deployments.
	 *
	 * @return void
	 */
	public function test_cancel_deployment_fails_for_completed(): void {
		$deployment = (object) array(
			'id'     => 1,
			'status' => 'success',
		);

		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 1 )
			->andReturn( $deployment );

		$result = $this->manager->cancel_deployment( 1 );

		$this->assertFalse( $result, 'Cannot cancel completed deployment' );
	}

	/**
	 * Test cancel_deployment fails for non-existent deployment.
	 *
	 * @return void
	 */
	public function test_cancel_deployment_fails_for_nonexistent(): void {
		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 999 )
			->andReturn( null );

		$result = $this->manager->cancel_deployment( 999 );

		$this->assertFalse( $result, 'Should fail for non-existent deployment' );
	}

	/**
	 * Test approve_pending_deployment triggers workflow.
	 *
	 * @return void
	 */
	public function test_approve_pending_deployment_triggers_workflow(): void {
		$deployment = (object) array(
			'id'                => 1,
			'status'            => 'pending',
			'commit_hash'       => 'abc123',
			'deployment_method' => 'github_actions',
		);

		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 1 )
			->andReturn( $deployment );

		$this->mock_database->shouldReceive( 'update_deployment' )
			->andReturn( true );

		$this->mock_github_api->shouldReceive( 'trigger_workflow' )
			->once()
			->andReturn( array( 'success' => true ) );

		$result = $this->manager->approve_pending_deployment( 1, 1 );

		$this->assertTrue( $result, 'Approval should succeed' );
	}

	/**
	 * Test approve_pending_deployment fails for non-pending status.
	 *
	 * @return void
	 */
	public function test_approve_pending_deployment_fails_for_non_pending(): void {
		$deployment = (object) array(
			'id'     => 1,
			'status' => 'building',
		);

		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 1 )
			->andReturn( $deployment );

		$result = $this->manager->approve_pending_deployment( 1, 1 );

		$this->assertFalse( $result, 'Should fail for non-pending deployment' );
	}

	/**
	 * Test rollback_deployment fails without backup.
	 *
	 * @return void
	 */
	public function test_rollback_deployment_fails_without_backup(): void {
		$deployment = (object) array(
			'id'          => 1,
			'status'      => 'success',
			'backup_path' => null,
		);

		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 1 )
			->andReturn( $deployment );

		$result = $this->manager->rollback_deployment( 1 );

		$this->assertFalse( $result, 'Rollback should fail without backup' );
	}

	/**
	 * Test rollback_deployment fails for non-existent deployment.
	 *
	 * @return void
	 */
	public function test_rollback_deployment_fails_for_nonexistent(): void {
		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 999 )
			->andReturn( null );

		$result = $this->manager->rollback_deployment( 999 );

		$this->assertFalse( $result, 'Should fail for non-existent deployment' );
	}

	/**
	 * Test check_pending_deployments processes building deployments.
	 *
	 * @return void
	 */
	public function test_check_pending_deployments_processes_building(): void {
		$deployment = (object) array(
			'id'              => 1,
			'status'          => 'building',
			'workflow_run_id' => 12345,
			'commit_hash'     => 'abc123',
		);

		$this->mock_database->shouldReceive( 'get_pending_deployments' )
			->once()
			->andReturn( array( $deployment ) );

		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 1 )
			->andReturn( $deployment );

		$this->mock_github_api->shouldReceive( 'get_workflow_run_status' )
			->with( 12345 )
			->andReturn(
				array(
					'success' => true,
					'data'    => (object) array(
						'status'     => 'in_progress',
						'conclusion' => null,
					),
				)
			);

		$this->mock_database->shouldReceive( 'update_deployment' )->andReturn( true );

		// This shouldn't throw - it should process the deployment.
		$this->manager->check_pending_deployments();

		$this->assertTrue( true, 'Should process without errors' );
	}

	/**
	 * Test process_successful_build skips already deployed.
	 *
	 * @return void
	 */
	public function test_process_successful_build_skips_completed(): void {
		$deployment = (object) array(
			'id'     => 1,
			'status' => 'success',
		);

		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 1 )
			->andReturn( $deployment );

		// Should not call any other methods.
		$this->mock_database->shouldNotReceive( 'get_deployment_lock' );

		$this->manager->process_successful_build( 1 );

		$this->assertTrue( true, 'Should skip without error' );
	}

	/**
	 * Test process_successful_build skips failed deployments.
	 *
	 * @return void
	 */
	public function test_process_successful_build_skips_failed(): void {
		$deployment = (object) array(
			'id'     => 1,
			'status' => 'failed',
		);

		$this->mock_database->shouldReceive( 'get_deployment' )
			->with( 1 )
			->andReturn( $deployment );

		$this->mock_database->shouldNotReceive( 'get_deployment_lock' );

		$this->manager->process_successful_build( 1 );

		$this->assertTrue( true, 'Should skip without error' );
	}

	/**
	 * Test backup_current_theme skips when theme doesn't exist.
	 *
	 * @return void
	 */
	public function test_backup_current_theme_handles_first_deploy(): void {
		// Override settings to return non-existent theme path.
		$this->mock_settings->shouldReceive( 'get_theme_path' )
			->andReturn( '/tmp/nonexistent-theme-' . time() );
		$this->mock_settings->shouldReceive( 'get_backup_directory' )
			->andReturn( sys_get_temp_dir() . '/deploy-forge-backups' );

		$result = $this->manager->backup_current_theme( 1 );

		$this->assertFalse( $result, 'Should return false when no existing theme' );
	}

	/**
	 * Test start_deployment handles database insert failure.
	 *
	 * @return void
	 */
	public function test_start_deployment_handles_database_failure(): void {
		$this->mock_database->shouldReceive( 'get_building_deployment' )->andReturn( null );
		$this->mock_database->shouldReceive( 'insert_deployment' )->andReturn( false );

		$result = $this->manager->start_deployment( 'abc123', 'webhook', 0 );

		$this->assertFalse( $result, 'Should return false on database failure' );
	}

	/**
	 * Test start_deployment with direct_clone method.
	 *
	 * @return void
	 */
	public function test_start_deployment_with_direct_clone(): void {
		// Create settings that returns direct_clone method.
		$settings = Mockery::mock( 'Deploy_Forge_Settings' );
		$settings->shouldReceive( 'get_connection_data' )->andReturn(
			array( 'deployment_method' => 'direct_clone' )
		);
		$settings->shouldReceive( 'get' )->andReturn( 'main' );
		$settings->shouldReceive( 'get_api_key' )->andReturn( 'test-key' );
		$settings->shouldReceive( 'get_backend_url' )->andReturn( 'https://example.com' );
		$settings->shouldReceive( 'get_theme_path' )->andReturn( '/tmp/test-theme' );
		$settings->shouldReceive( 'get_backup_directory' )->andReturn( '/tmp/backups' );

		$this->mock_database->shouldReceive( 'get_building_deployment' )->andReturn( null );
		$this->mock_database->shouldReceive( 'insert_deployment' )->andReturn( 1 );
		$this->mock_database->shouldReceive( 'update_deployment' )->andReturn( true );
		$this->mock_database->shouldReceive( 'get_deployment' )
			->andReturn( (object) array( 'deployment_logs' => '' ) );

		// GitHub API should download repository (not trigger workflow).
		$this->mock_github_api->shouldReceive( 'download_repository' )
			->once()
			->andReturn( $this->create_wp_error( 'test', 'Simulated error' ) );

		$this->mock_github_api->shouldReceive( 'report_deployment_status' )
			->andReturn( array( 'success' => true ) );

		$manager = new \Deploy_Forge_Deployment_Manager(
			$settings,
			$this->mock_github_api,
			$this->mock_database,
			$this->mock_logger
		);

		$result = $manager->start_deployment( 'abc123', 'webhook', 0 );

		// Will fail because download fails, but tests the path is taken.
		$this->assertFalse( $result );
	}

	/**
	 * Test manual deployment triggers remote API.
	 *
	 * @return void
	 */
	public function test_manual_deployment_triggers_remote_api(): void {
		$this->mock_database->shouldReceive( 'get_building_deployment' )->andReturn( null );
		$this->mock_database->shouldReceive( 'insert_deployment' )->andReturn( 1 );
		$this->mock_database->shouldReceive( 'update_deployment' )->andReturn( true );

		$this->mock_github_api->shouldReceive( 'trigger_remote_deployment' )
			->once()
			->andReturn(
				array(
					'success'    => true,
					'deployment' => array(
						'id'     => 'remote-123',
						'status' => 'building',
					),
				)
			);

		$result = $this->manager->start_deployment( 'abc123', 'manual', 1 );

		$this->assertEquals( 1, $result );
	}

	/**
	 * Test manual deployment fails if remote trigger fails.
	 *
	 * @return void
	 */
	public function test_manual_deployment_fails_on_remote_error(): void {
		$this->mock_database->shouldReceive( 'get_building_deployment' )->andReturn( null );

		$this->mock_github_api->shouldReceive( 'trigger_remote_deployment' )
			->once()
			->andReturn(
				array(
					'success' => false,
					'message' => 'API error',
				)
			);

		$result = $this->manager->start_deployment( 'abc123', 'manual', 1 );

		$this->assertIsArray( $result );
		$this->assertEquals( 'remote_trigger_failed', $result['error'] );
	}
}
