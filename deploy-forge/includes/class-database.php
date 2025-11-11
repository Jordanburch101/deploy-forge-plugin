<?php
/**
 * Database management class
 * Handles creation and management of custom database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Database {

    private string $table_name;
    private string $charset_collate;
    private const DB_VERSION = '1.0';

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'github_deployments';
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Create database tables on plugin activation
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
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY commit_hash (commit_hash),
            KEY status (status),
            KEY deployed_at (deployed_at),
            KEY trigger_type (trigger_type),
            KEY workflow_run_id (workflow_run_id)
        ) {$this->charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Store database version
        update_option('deploy_forge_db_version', self::DB_VERSION);
    }

    /**
     * Insert a new deployment record
     */
    public function insert_deployment(array $data): int|false {
        global $wpdb;

        $defaults = [
            'status' => 'pending',
            'trigger_type' => 'manual',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            [
                '%s', // commit_hash
                '%s', // commit_message
                '%s', // commit_author
                '%s', // commit_date
                '%s', // deployed_at
                '%s', // status
                '%s', // build_url
                '%s', // build_logs
                '%s', // deployment_logs
                '%s', // trigger_type
                '%d', // triggered_by_user_id
                '%d', // workflow_run_id
                '%s', // artifact_url
                '%s', // backup_path
                '%s', // error_message
                '%s', // created_at
                '%s', // updated_at
            ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a deployment record
     */
    public function update_deployment(int $id, array $data): bool {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            $this->table_name,
            $data,
            ['id' => $id],
            null, // Let wpdb determine format
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get a deployment by ID
     */
    public function get_deployment(int $id): ?object {
        global $wpdb;

        $deployment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id)
        );

        return $deployment ?: null;
    }

    /**
     * Get a deployment by commit hash
     */
    public function get_deployment_by_commit(string $commit_hash): ?object {
        global $wpdb;

        $deployment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE commit_hash = %s ORDER BY id DESC LIMIT 1", $commit_hash)
        );

        return $deployment ?: null;
    }

    /**
     * Get deployments by status
     */
    public function get_deployments_by_status(string $status, int $limit = 10): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY created_at DESC LIMIT %d",
                $status,
                $limit
            )
        );
    }

    /**
     * Get recent deployments
     */
    public function get_recent_deployments(int $limit = 10, int $offset = 0): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Get the last successful deployment
     */
    public function get_last_successful_deployment(): ?object {
        global $wpdb;

        $deployment = $wpdb->get_row(
            "SELECT * FROM {$this->table_name} WHERE status = 'success' ORDER BY deployed_at DESC LIMIT 1"
        );

        return $deployment ?: null;
    }

    /**
     * Get pending deployments (for cron processing)
     */
    public function get_pending_deployments(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE status IN ('pending', 'building') ORDER BY created_at ASC"
        );
    }

    /**
     * Get currently building deployment (if any)
     */
    public function get_building_deployment(): ?object {
        global $wpdb;

        $deployment = $wpdb->get_row(
            "SELECT * FROM {$this->table_name} WHERE status IN ('pending', 'building') ORDER BY created_at DESC LIMIT 1"
        );

        return $deployment ?: null;
    }

    /**
     * Delete all deployment records (for reset)
     */
    public function clear_all_deployments(): bool {
        global $wpdb;

        $result = $wpdb->query("TRUNCATE TABLE {$this->table_name}");

        return $result !== false;
    }

    /**
     * Drop the deployments table (for complete uninstall)
     */
    public function drop_table(): bool {
        global $wpdb;

        $result = $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");

        return $result !== false;
    }

    /**
     * Get total deployment count
     */
    public function get_deployment_count(?string $status = null): int {
        global $wpdb;

        if ($status) {
            return (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s", $status)
            );
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    /**
     * Search deployments
     */
    public function search_deployments(string $search, int $limit = 20): array {
        global $wpdb;

        $search = '%' . $wpdb->esc_like($search) . '%';

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
     * Delete old deployments (cleanup)
     */
    public function delete_old_deployments(int $days = 90): int {
        global $wpdb;

        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $wpdb->query(
            $wpdb->prepare("DELETE FROM {$this->table_name} WHERE created_at < %s", $date)
        );
    }

    /**
     * Get deployment statistics
     */
    public function get_statistics(): array {
        global $wpdb;

        return [
            'total' => $this->get_deployment_count(),
            'success' => $this->get_deployment_count('success'),
            'failed' => $this->get_deployment_count('failed'),
            'pending' => $this->get_deployment_count('pending'),
            'building' => $this->get_deployment_count('building'),
            'last_deployment' => $this->get_last_successful_deployment(),
        ];
    }
}
