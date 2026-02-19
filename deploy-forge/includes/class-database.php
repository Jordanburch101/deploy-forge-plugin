<?php
/**
 * Database management class.
 *
 * Handles creation and management of custom database tables.
 *
 * @package Deploy_Forge
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deploy_Forge_Database
 *
 * Manages deployment database operations including table creation,
 * migrations, and CRUD operations for deployment records.
 *
 * @since 1.0.0
 */
class Deploy_Forge_Database {

	/**
	 * Database table name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $table_name;

	/**
	 * Database charset and collation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $charset_collate;

	/**
	 * Current database schema version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const DB_VERSION = '1.3';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name      = $wpdb->prefix . 'github_deployments';
		$this->charset_collate = $wpdb->get_charset_collate();
	}

	/**
	 * Create database tables on plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function create_tables(): void {
		global $wpdb;

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			commit_hash varchar(40) NOT NULL,
			commit_message text,
			commit_author varchar(255),
			commit_date datetime,
			deployed_at datetime,
			status varchar(20) NOT NULL DEFAULT 'pending',
			build_url varchar(500),
			build_logs longtext,
			deployment_logs longtext,
			trigger_type varchar(20) NOT NULL DEFAULT 'manual',
			triggered_by_user_id bigint(20) unsigned,
			workflow_run_id bigint(20) unsigned,
			artifact_url varchar(500),
			backup_path varchar(500),
			error_message text,
			remote_deployment_id varchar(100),
			deployment_method varchar(50),
			artifact_id varchar(100),
			artifact_name varchar(255),
			artifact_size bigint(20) unsigned,
			artifact_download_url varchar(500),
			file_manifest longtext,
			snapshot_path varchar(500),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY commit_hash (commit_hash),
			KEY status (status),
			KEY deployed_at (deployed_at),
			KEY trigger_type (trigger_type),
			KEY workflow_run_id (workflow_run_id),
			KEY remote_deployment_id (remote_deployment_id)
		) {$this->charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store database version.
		update_option( 'deploy_forge_db_version', self::DB_VERSION );
	}

	/**
	 * Check if database needs upgrade and run migrations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		$current_version = get_option( 'deploy_forge_db_version' );

		// No version stored means tables haven't been created yet (fresh install).
		// Activation hook will call create_tables() which handles the full schema.
		if ( false === $current_version ) {
			return;
		}

		if ( version_compare( $current_version, self::DB_VERSION, '<' ) ) {
			$this->run_migrations( $current_version );
		}
	}

	/**
	 * Run database migrations from current version.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_version The version to migrate from.
	 * @return void
	 */
	private function run_migrations( string $from_version ): void {
		global $wpdb;

		// Migration to 1.1: Add Deploy Forge integration columns.
		if ( version_compare( $from_version, '1.1', '<' ) ) {
			$columns_to_add = array(
				'remote_deployment_id'  => 'varchar(100)',
				'deployment_method'     => 'varchar(50)',
				'artifact_id'           => 'varchar(100)',
				'artifact_name'         => 'varchar(255)',
				'artifact_size'         => 'bigint(20) unsigned',
				'artifact_download_url' => 'varchar(500)',
			);

			foreach ( $columns_to_add as $column => $type ) {
				$column_exists = $wpdb->get_results(
					$wpdb->prepare(
						"SHOW COLUMNS FROM {$this->table_name} LIKE %s",
						$column
					)
				);

				if ( empty( $column_exists ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN {$column} {$type}" );
				}
			}

			// Add index for remote_deployment_id if it doesn't exist.
			$index_exists = $wpdb->get_results(
				"SHOW INDEX FROM {$this->table_name} WHERE Key_name = 'remote_deployment_id'"
			);

			if ( empty( $index_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( "ALTER TABLE {$this->table_name} ADD INDEX remote_deployment_id (remote_deployment_id)" );
			}
		}

		// Migration to 1.3: Add file manifest and snapshot columns.
		// Originally targeted 1.2, but create_tables() set db_version to 1.2
		// before the columns were in the dbDelta schema, so the migration was
		// skipped on existing installs. Bumped to 1.3 to force re-run.
		if ( version_compare( $from_version, '1.3', '<' ) ) {
			$columns_to_add = array(
				'file_manifest' => 'longtext',
				'snapshot_path' => 'varchar(500)',
			);

			foreach ( $columns_to_add as $column => $type ) {
				$column_exists = $wpdb->get_results(
					$wpdb->prepare(
						"SHOW COLUMNS FROM {$this->table_name} LIKE %s",
						$column
					)
				);

				if ( empty( $column_exists ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN {$column} {$type}" );
				}
			}
		}

		// Update version after migrations.
		update_option( 'deploy_forge_db_version', self::DB_VERSION );
	}

	/**
	 * Insert a new deployment record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Deployment data to insert.
	 * @return int|false The inserted ID on success, false on failure.
	 */
	public function insert_deployment( array $data ): int|false {
		global $wpdb;

		$defaults = array(
			'status'       => 'pending',
			'trigger_type' => 'manual',
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		// Build format array dynamically based on data keys.
		$format = $this->get_format_array( $data );

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			$format
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get format array for wpdb based on data keys.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data The data array to get formats for.
	 * @return array Array of format specifiers.
	 */
	private function get_format_array( array $data ): array {
		$int_columns = array(
			'id',
			'triggered_by_user_id',
			'workflow_run_id',
			'artifact_size',
		);

		$format = array();
		foreach ( array_keys( $data ) as $key ) {
			$format[] = in_array( $key, $int_columns, true ) ? '%d' : '%s';
		}

		return $format;
	}

	/**
	 * Update a deployment record.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   The deployment ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_deployment( int $id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->table_name,
			$data,
			array( 'id' => $id ),
			null, // Let wpdb determine format.
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get a deployment by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The deployment ID.
	 * @return object|null The deployment object or null if not found.
	 */
	public function get_deployment( int $id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deployment = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
		);

		return $deployment ?: null;
	}

	/**
	 * Get a deployment by commit hash.
	 *
	 * @since 1.0.0
	 *
	 * @param string $commit_hash The commit hash.
	 * @return object|null The deployment object or null if not found.
	 */
	public function get_deployment_by_commit( string $commit_hash ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deployment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE commit_hash = %s ORDER BY id DESC LIMIT 1",
				$commit_hash
			)
		);

		return $deployment ?: null;
	}

	/**
	 * Get deployments by status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status The deployment status.
	 * @param int    $limit  Maximum number of results.
	 * @return array Array of deployment objects.
	 */
	public function get_deployments_by_status( string $status, int $limit = 10 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY created_at DESC LIMIT %d",
				$status,
				$limit
			)
		);
	}

	/**
	 * Get recent deployments.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit  Maximum number of results.
	 * @param int $offset Number of results to skip.
	 * @return array Array of deployment objects.
	 */
	public function get_recent_deployments( int $limit = 10, int $offset = 0 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Get the last successful deployment.
	 *
	 * @since 1.0.0
	 *
	 * @return object|null The deployment object or null if not found.
	 */
	public function get_last_successful_deployment(): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deployment = $wpdb->get_row(
			"SELECT * FROM {$this->table_name} WHERE status = 'success' ORDER BY deployed_at DESC LIMIT 1"
		);

		return $deployment ?: null;
	}

	/**
	 * Get pending deployments (for cron processing).
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of deployment objects.
	 */
	public function get_pending_deployments(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			"SELECT * FROM {$this->table_name} WHERE status IN ('pending', 'building', 'queued') ORDER BY created_at ASC"
		);
	}

	/**
	 * Get queued deployments (waiting for async processing).
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of deployment objects.
	 */
	public function get_queued_deployments(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			"SELECT * FROM {$this->table_name} WHERE status = 'queued' ORDER BY created_at ASC"
		);
	}

	/**
	 * Get deployment processing lock status.
	 *
	 * Returns the deployment ID if locked, false if available.
	 *
	 * @since 1.0.0
	 *
	 * @return int|false The locked deployment ID or false if not locked.
	 */
	public function get_deployment_lock(): int|false {
		$lock = get_transient( 'deploy_forge_processing_lock' );
		return $lock ? (int) $lock : false;
	}

	/**
	 * Set deployment processing lock.
	 *
	 * Prevents concurrent deployments.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deployment_id The deployment ID to lock.
	 * @param int $timeout       Lock timeout in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function set_deployment_lock( int $deployment_id, int $timeout = 300 ): bool {
		return set_transient( 'deploy_forge_processing_lock', $deployment_id, $timeout );
	}

	/**
	 * Release deployment processing lock.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function release_deployment_lock(): bool {
		return delete_transient( 'deploy_forge_processing_lock' );
	}

	/**
	 * Get currently building deployment (if any).
	 *
	 * @since 1.0.0
	 *
	 * @return object|null The deployment object or null if not found.
	 */
	public function get_building_deployment(): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deployment = $wpdb->get_row(
			"SELECT * FROM {$this->table_name} WHERE status IN ('pending', 'building') ORDER BY created_at DESC LIMIT 1"
		);

		return $deployment ?: null;
	}

	/**
	 * Delete all deployment records (for reset).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_all_deployments(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );

		return false !== $result;
	}

	/**
	 * Drop the deployments table (for complete uninstall).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function drop_table(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" );

		return false !== $result;
	}

	/**
	 * Get total deployment count.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $status Optional status filter.
	 * @return int The deployment count.
	 */
	public function get_deployment_count( ?string $status = null ): int {
		global $wpdb;

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", $status )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );
	}

	/**
	 * Search deployments.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search The search term.
	 * @param int    $limit  Maximum number of results.
	 * @return array Array of deployment objects.
	 */
	public function search_deployments( string $search, int $limit = 20 ): array {
		global $wpdb;

		$search = '%' . $wpdb->esc_like( $search ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE commit_hash LIKE %s
				OR commit_message LIKE %s
				OR commit_author LIKE %s
				ORDER BY created_at DESC
				LIMIT %d",
				$search,
				$search,
				$search,
				$limit
			)
		);
	}

	/**
	 * Get the active deployment ID.
	 *
	 * @since 1.0.52
	 *
	 * @return int The active deployment ID, or 0 if none.
	 */
	public function get_active_deployment_id(): int {
		return (int) get_option( 'deploy_forge_active_deployment_id', 0 );
	}

	/**
	 * Set the active deployment ID.
	 *
	 * @since 1.0.52
	 *
	 * @param int $id The deployment ID to mark as active.
	 * @return bool True on success, false on failure.
	 */
	public function set_active_deployment_id( int $id ): bool {
		return update_option( 'deploy_forge_active_deployment_id', $id );
	}

	/**
	 * Get the active deployment record.
	 *
	 * @since 1.0.52
	 *
	 * @return object|null The deployment object or null if not found.
	 */
	public function get_active_deployment(): ?object {
		$id = $this->get_active_deployment_id();

		if ( ! $id ) {
			return null;
		}

		return $this->get_deployment( $id );
	}

	/**
	 * Get the previous successful deployment before a given ID.
	 *
	 * @since 1.0.52
	 *
	 * @param int $before_id Find the deployment before this ID.
	 * @return object|null The deployment object or null if not found.
	 */
	public function get_previous_successful_deployment( int $before_id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deployment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id < %d AND status = 'success' ORDER BY id DESC LIMIT 1",
				$before_id
			)
		);

		return $deployment ?: null;
	}

	/**
	 * Get deployments beyond a retention count that have files on disk.
	 *
	 * Returns deployments (ordered newest first) that are beyond the keep
	 * threshold and have a backup_path or snapshot_path set.
	 *
	 * @since 1.0.52
	 *
	 * @param int $keep Number of most-recent deployments to retain files for.
	 * @return array Array of deployment objects with id, backup_path, snapshot_path.
	 */
	public function get_deployments_with_expired_files( int $keep = 10 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, backup_path, snapshot_path FROM {$this->table_name}
				WHERE (backup_path IS NOT NULL AND backup_path != '') OR (snapshot_path IS NOT NULL AND snapshot_path != '')
				ORDER BY id DESC
				LIMIT 999999999 OFFSET %d",
				$keep
			)
		);
	}

	/**
	 * Clear file paths for a deployment after its ZIPs have been deleted.
	 *
	 * @since 1.0.52
	 *
	 * @param int $deployment_id The deployment ID.
	 * @return void
	 */
	public function clear_deployment_file_paths( int $deployment_id ): void {
		$this->update_deployment(
			$deployment_id,
			array(
				'backup_path'   => '',
				'snapshot_path' => '',
			)
		);
	}

	/**
	 * Get old deployments that are beyond the retention period.
	 *
	 * Returns deployments older than the specified number of days,
	 * including their file paths so the caller can clean up files
	 * before the rows are deleted.
	 *
	 * @since 1.0.53
	 *
	 * @param int $days Number of days to keep.
	 * @return array Array of deployment objects with id, backup_path, snapshot_path.
	 */
	public function get_old_deployments( int $days = 90 ): array {
		global $wpdb;

		$date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, backup_path, snapshot_path FROM {$this->table_name} WHERE created_at < %s",
				$date
			)
		);
	}

	/**
	 * Delete old deployments (cleanup).
	 *
	 * @since 1.0.0
	 *
	 * @param int $days Number of days to keep.
	 * @return int Number of rows deleted.
	 */
	public function delete_old_deployments( int $days = 90 ): int {
		global $wpdb;

		$date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$this->table_name} WHERE created_at < %s", $date )
		);
	}

	/**
	 * Get deployment statistics.
	 *
	 * @since 1.0.0
	 *
	 * @return array Statistics array with counts for each status.
	 */
	public function get_statistics(): array {
		return array(
			'total'           => $this->get_deployment_count(),
			'success'         => $this->get_deployment_count( 'success' ),
			'failed'          => $this->get_deployment_count( 'failed' ),
			'pending'         => $this->get_deployment_count( 'pending' ),
			'building'        => $this->get_deployment_count( 'building' ),
			'queued'          => $this->get_deployment_count( 'queued' ),
			'deploying'       => $this->get_deployment_count( 'deploying' ),
			'last_deployment' => $this->get_last_successful_deployment(),
		);
	}
}
