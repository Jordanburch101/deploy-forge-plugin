<?php
/**
 * Tests for Deploy_Forge_Database class.
 *
 * @package Deploy_Forge
 */

namespace DeployForge\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

// Load the class file.
require_once dirname( __DIR__, 2 ) . '/deploy-forge/includes/class-database.php';

/**
 * Test case for the Database class.
 *
 * Tests CRUD operations and deployment record management.
 */
class DatabaseTest extends TestCase {

	/**
	 * Database instance.
	 *
	 * @var \Deploy_Forge_Database
	 */
	private \Deploy_Forge_Database $database;

	/**
	 * Mock wpdb instance.
	 *
	 * @var Mockery\MockInterface
	 */
	private $mock_wpdb;

	/**
	 * Transient storage for tests.
	 *
	 * @var array
	 */
	private array $transients = array();

	/**
	 * Set up test fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mock wpdb.
		$this->mock_wpdb         = Mockery::mock( 'wpdb' );
		$this->mock_wpdb->prefix = 'wp_';

		$this->mock_wpdb->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );

		// Set global $wpdb.
		$GLOBALS['wpdb'] = $this->mock_wpdb;

		// Set up transient mocks.
		$this->transients = array();

		Functions\when( 'get_transient' )->alias(
			function ( $key ) {
				return $this->transients[ $key ] ?? false;
			}
		);

		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $expiration = 0 ) {
				$this->transients[ $key ] = $value;
				return true;
			}
		);

		Functions\when( 'delete_transient' )->alias(
			function ( $key ) {
				unset( $this->transients[ $key ] );
				return true;
			}
		);

		$this->database = new \Deploy_Forge_Database();
	}

	/**
	 * Test insert_deployment creates record and returns ID.
	 *
	 * @return void
	 */
	public function test_insert_deployment_returns_id(): void {
		$this->mock_wpdb->insert_id = 42;

		$this->mock_wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_github_deployments',
				Mockery::on( function ( $data ) {
					return isset( $data['commit_hash'] )
						&& 'abc123def456' === $data['commit_hash']
						&& 'pending' === $data['status'];
				} ),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$result = $this->database->insert_deployment(
			array(
				'commit_hash'    => 'abc123def456',
				'commit_message' => 'Test commit',
			)
		);

		$this->assertEquals( 42, $result, 'Should return inserted ID' );
	}

	/**
	 * Test insert_deployment uses defaults for missing fields.
	 *
	 * @return void
	 */
	public function test_insert_deployment_uses_defaults(): void {
		$this->mock_wpdb->insert_id = 1;

		$captured_data = null;

		$this->mock_wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_github_deployments',
				Mockery::on( function ( $data ) use ( &$captured_data ) {
					$captured_data = $data;
					return true;
				} ),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$this->database->insert_deployment(
			array( 'commit_hash' => 'abc123' )
		);

		$this->assertEquals( 'pending', $captured_data['status'], 'Default status should be pending' );
		$this->assertEquals( 'manual', $captured_data['trigger_type'], 'Default trigger_type should be manual' );
	}

	/**
	 * Test insert_deployment returns false on failure.
	 *
	 * @return void
	 */
	public function test_insert_deployment_returns_false_on_failure(): void {
		$this->mock_wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( false );

		$result = $this->database->insert_deployment(
			array( 'commit_hash' => 'abc123' )
		);

		$this->assertFalse( $result, 'Should return false on insert failure' );
	}

	/**
	 * Test update_deployment modifies record.
	 *
	 * @return void
	 */
	public function test_update_deployment_modifies_record(): void {
		$this->mock_wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_github_deployments',
				Mockery::on( function ( $data ) {
					return 'success' === $data['status']
						&& isset( $data['updated_at'] );
				} ),
				array( 'id' => 1 ),
				null,
				array( '%d' )
			)
			->andReturn( 1 );

		$result = $this->database->update_deployment( 1, array( 'status' => 'success' ) );

		$this->assertTrue( $result, 'Should return true on success' );
	}

	/**
	 * Test update_deployment returns false on failure.
	 *
	 * @return void
	 */
	public function test_update_deployment_returns_false_on_failure(): void {
		$this->mock_wpdb->shouldReceive( 'update' )
			->once()
			->andReturn( false );

		$result = $this->database->update_deployment( 999, array( 'status' => 'failed' ) );

		$this->assertFalse( $result, 'Should return false on update failure' );
	}

	/**
	 * Test get_deployment returns record by ID.
	 *
	 * @return void
	 */
	public function test_get_deployment_returns_record(): void {
		$expected = (object) array(
			'id'            => 1,
			'commit_hash'   => 'abc123',
			'status'        => 'success',
			'trigger_type'  => 'manual',
		);

		$this->mock_wpdb->shouldReceive( 'prepare' )
			->once()
			->andReturn( 'SELECT * FROM wp_github_deployments WHERE id = 1' );

		$this->mock_wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $expected );

		$result = $this->database->get_deployment( 1 );

		$this->assertSame( $expected, $result );
		$this->assertEquals( 'abc123', $result->commit_hash );
	}

	/**
	 * Test get_deployment returns null for non-existent ID.
	 *
	 * @return void
	 */
	public function test_get_deployment_returns_null_for_nonexistent(): void {
		$this->mock_wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->mock_wpdb->shouldReceive( 'get_row' )->andReturn( null );

		$result = $this->database->get_deployment( 999 );

		$this->assertNull( $result );
	}

	/**
	 * Test get_deployment_by_commit finds matching record.
	 *
	 * @return void
	 */
	public function test_get_deployment_by_commit_finds_match(): void {
		$expected = (object) array(
			'id'          => 5,
			'commit_hash' => 'abc123def456789',
			'status'      => 'success',
		);

		$this->mock_wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->mock_wpdb->shouldReceive( 'get_row' )->andReturn( $expected );

		$result = $this->database->get_deployment_by_commit( 'abc123def456789' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test get_recent_deployments returns list.
	 *
	 * @return void
	 */
	public function test_get_recent_deployments_returns_list(): void {
		$expected = array(
			(object) array( 'id' => 3, 'status' => 'success' ),
			(object) array( 'id' => 2, 'status' => 'failed' ),
			(object) array( 'id' => 1, 'status' => 'success' ),
		);

		$this->mock_wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->mock_wpdb->shouldReceive( 'get_results' )->andReturn( $expected );

		$result = $this->database->get_recent_deployments( 3 );

		$this->assertCount( 3, $result );
		$this->assertEquals( 3, $result[0]->id );
	}

	/**
	 * Test get_last_successful_deployment returns most recent success.
	 *
	 * @return void
	 */
	public function test_get_last_successful_deployment_returns_most_recent(): void {
		$expected = (object) array(
			'id'          => 5,
			'commit_hash' => 'latest123',
			'status'      => 'success',
			'deployed_at' => '2024-01-15 12:00:00',
		);

		$this->mock_wpdb->shouldReceive( 'get_row' )->andReturn( $expected );

		$result = $this->database->get_last_successful_deployment();

		$this->assertSame( $expected, $result );
		$this->assertEquals( 'success', $result->status );
	}

	/**
	 * Test get_pending_deployments returns pending and building.
	 *
	 * @return void
	 */
	public function test_get_pending_deployments_returns_active(): void {
		$expected = array(
			(object) array( 'id' => 1, 'status' => 'pending' ),
			(object) array( 'id' => 2, 'status' => 'building' ),
		);

		$this->mock_wpdb->shouldReceive( 'get_results' )->andReturn( $expected );

		$result = $this->database->get_pending_deployments();

		$this->assertCount( 2, $result );
	}

	/**
	 * Test get_building_deployment returns current build.
	 *
	 * @return void
	 */
	public function test_get_building_deployment_returns_current(): void {
		$expected = (object) array(
			'id'     => 3,
			'status' => 'building',
		);

		$this->mock_wpdb->shouldReceive( 'get_row' )->andReturn( $expected );

		$result = $this->database->get_building_deployment();

		$this->assertEquals( 3, $result->id );
		$this->assertEquals( 'building', $result->status );
	}

	/**
	 * Test deployment lock get/set/release.
	 *
	 * @return void
	 */
	public function test_deployment_lock_operations(): void {
		// Initially no lock.
		$this->assertFalse( $this->database->get_deployment_lock() );

		// Set lock.
		$this->assertTrue( $this->database->set_deployment_lock( 42, 300 ) );
		$this->assertEquals( 42, $this->database->get_deployment_lock() );

		// Release lock.
		$this->assertTrue( $this->database->release_deployment_lock() );
		$this->assertFalse( $this->database->get_deployment_lock() );
	}

	/**
	 * Test get_deployment_count returns total.
	 *
	 * @return void
	 */
	public function test_get_deployment_count_returns_total(): void {
		$this->mock_wpdb->shouldReceive( 'get_var' )->andReturn( '15' );

		$result = $this->database->get_deployment_count();

		$this->assertEquals( 15, $result );
	}

	/**
	 * Test get_deployment_count filters by status.
	 *
	 * @return void
	 */
	public function test_get_deployment_count_filters_by_status(): void {
		$this->mock_wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->mock_wpdb->shouldReceive( 'get_var' )->andReturn( '7' );

		$result = $this->database->get_deployment_count( 'success' );

		$this->assertEquals( 7, $result );
	}

	/**
	 * Test search_deployments finds matches.
	 *
	 * @return void
	 */
	public function test_search_deployments_finds_matches(): void {
		$expected = array(
			(object) array( 'id' => 1, 'commit_message' => 'Fix bug in login' ),
			(object) array( 'id' => 3, 'commit_message' => 'Fix another bug' ),
		);

		$this->mock_wpdb->shouldReceive( 'esc_like' )
			->with( 'Fix' )
			->andReturn( 'Fix' );
		$this->mock_wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->mock_wpdb->shouldReceive( 'get_results' )->andReturn( $expected );

		$result = $this->database->search_deployments( 'Fix' );

		$this->assertCount( 2, $result );
	}

	/**
	 * Test delete_old_deployments removes old records.
	 *
	 * @return void
	 */
	public function test_delete_old_deployments_removes_old(): void {
		$this->mock_wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->mock_wpdb->shouldReceive( 'query' )->andReturn( 5 );

		$result = $this->database->delete_old_deployments( 90 );

		$this->assertEquals( 5, $result );
	}

	/**
	 * Test get_statistics returns counts.
	 *
	 * @return void
	 */
	public function test_get_statistics_returns_counts(): void {
		$this->mock_wpdb->shouldReceive( 'get_var' )->andReturn( '10' );
		$this->mock_wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->mock_wpdb->shouldReceive( 'get_row' )->andReturn( null );

		$result = $this->database->get_statistics();

		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'failed', $result );
		$this->assertArrayHasKey( 'pending', $result );
		$this->assertArrayHasKey( 'last_deployment', $result );
	}

	/**
	 * Test get_deployments_by_status filters correctly.
	 *
	 * @return void
	 */
	public function test_get_deployments_by_status_filters(): void {
		$expected = array(
			(object) array( 'id' => 2, 'status' => 'failed' ),
			(object) array( 'id' => 5, 'status' => 'failed' ),
		);

		$this->mock_wpdb->shouldReceive( 'prepare' )->andReturn( '' );
		$this->mock_wpdb->shouldReceive( 'get_results' )->andReturn( $expected );

		$result = $this->database->get_deployments_by_status( 'failed', 10 );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'failed', $result[0]->status );
	}

	/**
	 * Test clear_all_deployments truncates table.
	 *
	 * @return void
	 */
	public function test_clear_all_deployments_truncates(): void {
		$this->mock_wpdb->shouldReceive( 'query' )
			->once()
			->with( 'TRUNCATE TABLE wp_github_deployments' )
			->andReturn( true );

		$result = $this->database->clear_all_deployments();

		$this->assertTrue( $result );
	}

	/**
	 * Test drop_table removes table.
	 *
	 * @return void
	 */
	public function test_drop_table_removes_table(): void {
		$this->mock_wpdb->shouldReceive( 'query' )
			->once()
			->with( 'DROP TABLE IF EXISTS wp_github_deployments' )
			->andReturn( true );

		$result = $this->database->drop_table();

		$this->assertTrue( $result );
	}

	/**
	 * Test get_queued_deployments returns queued only.
	 *
	 * @return void
	 */
	public function test_get_queued_deployments_returns_queued(): void {
		$expected = array(
			(object) array( 'id' => 4, 'status' => 'queued' ),
		);

		$this->mock_wpdb->shouldReceive( 'get_results' )->andReturn( $expected );

		$result = $this->database->get_queued_deployments();

		$this->assertCount( 1, $result );
		$this->assertEquals( 'queued', $result[0]->status );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}
}
