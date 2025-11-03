<?php

/**
 * Deployment manager class
 * Orchestrates the entire deployment workflow
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_Deployment_Manager
{

    private GitHub_Deploy_Settings $settings;
    private GitHub_API $github_api;
    private GitHub_Deploy_Database $database;
    private GitHub_Deploy_Debug_Logger $logger;

    public function __construct(GitHub_Deploy_Settings $settings, GitHub_API $github_api, GitHub_Deploy_Database $database, GitHub_Deploy_Debug_Logger $logger)
    {
        $this->settings = $settings;
        $this->github_api = $github_api;
        $this->database = $database;
        $this->logger = $logger;
    }

    /**
     * Start a new deployment
     */
    public function start_deployment(string $commit_hash, string $trigger_type = 'manual', int $user_id = 0, array $commit_data = []): int|false
    {
        $this->logger->log_deployment_step(0, 'Start Deployment', 'initiated', [
            'commit_hash' => $commit_hash,
            'trigger_type' => $trigger_type,
            'user_id' => $user_id,
            'commit_data' => $commit_data,
        ]);

        // Create deployment record
        $deployment_id = $this->database->insert_deployment([
            'commit_hash' => $commit_hash,
            'commit_message' => $commit_data['commit_message'] ?? '',
            'commit_author' => $commit_data['commit_author'] ?? '',
            'commit_date' => $commit_data['commit_date'] ?? current_time('mysql'),
            'status' => 'pending',
            'trigger_type' => $trigger_type,
            'triggered_by_user_id' => $user_id,
        ]);

        if (!$deployment_id) {
            $this->logger->error('Deployment', 'Failed to create deployment record in database');
            return false;
        }

        $this->logger->log_deployment_step($deployment_id, 'Database Record', 'created', [
            'deployment_id' => $deployment_id,
        ]);

        // Trigger GitHub Actions workflow
        $workflow_result = $this->trigger_github_build($deployment_id, $commit_hash);

        if (!$workflow_result) {
            $this->logger->error('Deployment', "Deployment #$deployment_id failed to trigger workflow");
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('Failed to trigger GitHub Actions workflow.', 'github-auto-deploy'),
            ]);
            return false;
        }

        return $deployment_id;
    }

    /**
     * Trigger GitHub Actions workflow
     */
    private function trigger_github_build(int $deployment_id, string $commit_hash): bool
    {
        $workflow_name = $this->settings->get('github_workflow_name');
        $branch = $this->settings->get('github_branch');

        // Trigger workflow
        $result = $this->github_api->trigger_workflow($workflow_name, $branch);

        if (!$result['success']) {
            $this->log_deployment($deployment_id, 'Failed to trigger workflow: ' . $result['message']);
            return false;
        }

        // Update status to building
        $this->database->update_deployment($deployment_id, [
            'status' => 'building',
            'deployment_logs' => 'GitHub Actions workflow triggered successfully.',
        ]);

        $this->log_deployment($deployment_id, 'GitHub Actions workflow triggered for commit: ' . $commit_hash);

        return true;
    }

    /**
     * Check pending deployments (called by cron)
     */
    public function check_pending_deployments(): void
    {
        $pending_deployments = $this->database->get_pending_deployments();

        foreach ($pending_deployments as $deployment) {
            if ($deployment->status === 'building') {
                $this->check_build_status($deployment->id);
            }
        }
    }

    /**
     * Check build status for a deployment
     */
    private function check_build_status(int $deployment_id): void
    {
        $deployment = $this->database->get_deployment($deployment_id);

        if (!$deployment || !$deployment->workflow_run_id) {
            $this->logger->log_deployment_step($deployment_id, 'Check Build Status', 'no_run_id', [
                'has_deployment' => !empty($deployment),
                'workflow_run_id' => $deployment->workflow_run_id ?? null,
            ]);
            // Try to find the workflow run for this commit
            $this->find_workflow_run($deployment_id);
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'Check Build Status', 'checking', [
            'workflow_run_id' => $deployment->workflow_run_id,
        ]);

        $result = $this->github_api->get_workflow_run_status($deployment->workflow_run_id);

        if (!$result['success']) {
            $this->logger->error('Deployment', "Deployment #$deployment_id failed to check build status", $result);
            $this->log_deployment($deployment_id, 'Failed to check build status: ' . $result['message']);
            return;
        }

        $run_data = $result['data'];
        $status = $run_data->status ?? '';
        $conclusion = $run_data->conclusion ?? '';

        $this->logger->log_deployment_step($deployment_id, 'Build Status Retrieved', 'checked', [
            'status' => $status,
            'conclusion' => $conclusion,
        ]);

        // Update build URL
        if (!empty($run_data->html_url)) {
            $this->database->update_deployment($deployment_id, [
                'build_url' => $run_data->html_url,
            ]);
        }

        // Check if workflow is completed
        if ($status === 'completed') {
            if ($conclusion === 'success') {
                $this->logger->log_deployment_step($deployment_id, 'Build Completed', 'success');
                $this->process_successful_build($deployment_id);
            } else {
                $this->logger->error('Deployment', "Deployment #$deployment_id build failed", [
                    'conclusion' => $conclusion,
                ]);
                $this->database->update_deployment($deployment_id, [
                    'status' => 'failed',
                    'error_message' => sprintf(__('Build failed with conclusion: %s', 'github-auto-deploy'), $conclusion),
                ]);
                $this->log_deployment($deployment_id, 'Build failed: ' . $conclusion);
            }
        }
    }

    /**
     * Find workflow run for deployment
     */
    private function find_workflow_run(int $deployment_id): void
    {
        $deployment = $this->database->get_deployment($deployment_id);
        $commit_hash = $deployment->commit_hash;

        $this->logger->log_deployment_step($deployment_id, 'Find Workflow Run', 'searching', [
            'commit_hash' => $commit_hash,
        ]);

        $result = $this->github_api->get_latest_workflow_runs(10);

        if (!$result['success']) {
            $this->logger->error('Deployment', "Deployment #$deployment_id failed to get workflow runs", $result);
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'Workflow Runs Retrieved', 'success', [
            'total_runs' => count($result['data']),
        ]);

        // Find workflow run matching this commit
        foreach ($result['data'] as $run) {
            $this->logger->log('Deployment', "Checking run #{$run->id} with SHA: {$run->head_sha} vs {$commit_hash}");

            if ($run->head_sha === $commit_hash) {
                $this->database->update_deployment($deployment_id, [
                    'workflow_run_id' => $run->id,
                    'build_url' => $run->html_url,
                ]);
                $this->logger->log_deployment_step($deployment_id, 'Workflow Run Matched', 'success', [
                    'workflow_run_id' => $run->id,
                    'build_url' => $run->html_url,
                ]);
                $this->log_deployment($deployment_id, 'Found workflow run ID: ' . $run->id);

                // Immediately check the status now that we have the run ID
                $this->check_build_status($deployment_id);
                return;
            }
        }

        $this->logger->log_deployment_step($deployment_id, 'Workflow Run Not Found', 'waiting', [
            'commit_hash' => $commit_hash,
            'checked_runs' => count($result['data']),
        ]);
    }

    /**
     * Process successful build
     */
    public function process_successful_build(int $deployment_id): void
    {
        $deployment = $this->database->get_deployment($deployment_id);

        if (!$deployment) {
            $this->logger->error('Deployment', "Deployment #$deployment_id not found in database");
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'Process Build Success', 'started');
        $this->log_deployment($deployment_id, 'Build completed successfully. Starting deployment...');

        // Get artifacts
        $this->logger->log_deployment_step($deployment_id, 'Fetch Artifacts', 'started', [
            'workflow_run_id' => $deployment->workflow_run_id,
        ]);

        $artifacts_result = $this->github_api->get_workflow_artifacts($deployment->workflow_run_id);

        if (!$artifacts_result['success'] || empty($artifacts_result['data'])) {
            $this->logger->error('Deployment', "Deployment #$deployment_id no artifacts found", $artifacts_result);
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('No artifacts found for successful build.', 'github-auto-deploy'),
            ]);
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'Artifacts Found', 'success', [
            'artifact_count' => count($artifacts_result['data']),
            'artifact_name' => $artifacts_result['data'][0]->name ?? 'unknown',
        ]);

        // Get first artifact (assuming single artifact)
        $artifact = $artifacts_result['data'][0];
        $artifact_id = $artifact->id;

        // Download and deploy
        $this->download_and_deploy($deployment_id, $artifact_id);
    }

    /**
     * Download artifact and deploy theme
     */
    private function download_and_deploy(int $deployment_id, int $artifact_id): void
    {
        // Create temp directory
        $temp_dir = $this->get_temp_directory();
        $artifact_zip = $temp_dir . '/artifact-' . $deployment_id . '.zip';

        $this->logger->log_deployment_step($deployment_id, 'Download Artifact', 'started', [
            'artifact_id' => $artifact_id,
            'temp_dir' => $temp_dir,
            'artifact_zip' => $artifact_zip,
        ]);

        $this->log_deployment($deployment_id, 'Downloading artifact from GitHub...');

        // Download artifact
        $download_result = $this->github_api->download_artifact($artifact_id, $artifact_zip);

        if (is_wp_error($download_result)) {
            $this->logger->error('Deployment', "Deployment #$deployment_id artifact download failed", $download_result);
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => $download_result->get_error_message(),
            ]);
            $this->log_deployment($deployment_id, 'Download failed: ' . $download_result->get_error_message());
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'Artifact Downloaded', 'success', [
            'file_size' => file_exists($artifact_zip) ? filesize($artifact_zip) : 0,
        ]);

        // Create backup if enabled
        if ($this->settings->get('create_backups')) {
            $this->logger->log_deployment_step($deployment_id, 'Create Backup', 'started');
            $backup_path = $this->backup_current_theme($deployment_id);
            if ($backup_path) {
                $this->database->update_deployment($deployment_id, ['backup_path' => $backup_path]);
                $this->log_deployment($deployment_id, 'Backup created: ' . $backup_path);
                $this->logger->log_deployment_step($deployment_id, 'Backup Created', 'success', [
                    'backup_path' => $backup_path,
                ]);
            }
        }

        // Extract and deploy
        $this->extract_and_deploy($deployment_id, $artifact_zip);
    }

    /**
     * Extract artifact and deploy to theme directory
     */
    private function extract_and_deploy(int $deployment_id, string $artifact_zip): void
    {
        global $wp_filesystem;

        $this->logger->log_deployment_step($deployment_id, 'Extract and Deploy', 'started');

        // Check if artifact file exists and is readable
        if (!file_exists($artifact_zip)) {
            $this->logger->error('Deployment', "Deployment #$deployment_id artifact file not found: {$artifact_zip}");
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('Artifact file not found.', 'github-auto-deploy'),
            ]);
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'WP_Filesystem Init', 'starting');

        // Initialize filesystem with direct method to avoid FTP prompts
        if (!WP_Filesystem()) {
            $this->logger->error('Deployment', "Deployment #$deployment_id WP_Filesystem initialization failed");
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('Failed to initialize WP_Filesystem.', 'github-auto-deploy'),
            ]);
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'WP_Filesystem Init', 'success');
        $this->log_deployment($deployment_id, 'Extracting artifact...');

        // Extract to temp directory
        $temp_extract_dir = $this->get_temp_directory() . '/extract-' . $deployment_id;

        $this->logger->log_deployment_step($deployment_id, 'Unzip Artifact', 'started', [
            'source' => $artifact_zip,
            'destination' => $temp_extract_dir,
            'file_size' => filesize($artifact_zip),
            'file_exists' => file_exists($artifact_zip),
        ]);

        // Increase timeout for large files
        @set_time_limit(300); // 5 minutes

        $unzip_result = unzip_file($artifact_zip, $temp_extract_dir);

        if (is_wp_error($unzip_result)) {
            $this->logger->error('Deployment', "Deployment #$deployment_id unzip failed", $unzip_result);
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => $unzip_result->get_error_message(),
            ]);
            $this->log_deployment($deployment_id, 'Extraction failed: ' . $unzip_result->get_error_message());
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'Artifact Extracted', 'success');

        // GitHub Actions artifacts are double-zipped - check if we need to unzip again
        $extracted_files = scandir($temp_extract_dir);
        $extracted_files = array_diff($extracted_files, ['.', '..']);

        $this->logger->log_deployment_step($deployment_id, 'Check Extracted Files', 'checking', [
            'file_count' => count($extracted_files),
            'files' => array_values($extracted_files),
        ]);

        // If there's only one file and it's a zip, extract it
        if (count($extracted_files) === 1) {
            $single_file = reset($extracted_files);
            $single_file_path = $temp_extract_dir . '/' . $single_file;

            if (pathinfo($single_file, PATHINFO_EXTENSION) === 'zip') {
                $this->logger->log_deployment_step($deployment_id, 'Double-Zipped Detected', 'extracting_inner_zip', [
                    'inner_zip' => $single_file,
                ]);

                $this->log_deployment($deployment_id, 'Artifact is double-zipped, extracting inner archive...');

                $final_extract_dir = $this->get_temp_directory() . '/final-' . $deployment_id;
                $inner_unzip_result = unzip_file($single_file_path, $final_extract_dir);

                if (is_wp_error($inner_unzip_result)) {
                    $this->logger->error('Deployment', "Deployment #$deployment_id inner unzip failed", $inner_unzip_result);
                    $this->database->update_deployment($deployment_id, [
                        'status' => 'failed',
                        'error_message' => $inner_unzip_result->get_error_message(),
                    ]);
                    $this->log_deployment($deployment_id, 'Inner extraction failed: ' . $inner_unzip_result->get_error_message());
                    return;
                }

                // Clean up outer extraction and use inner as source
                $wp_filesystem->delete($temp_extract_dir, true);
                $temp_extract_dir = $final_extract_dir;

                $this->logger->log_deployment_step($deployment_id, 'Inner Archive Extracted', 'success');
            }
        }

        // Get theme path
        $theme_path = $this->settings->get_theme_path();

        $this->logger->log_deployment_step($deployment_id, 'Copy to Theme Directory', 'started', [
            'source' => $temp_extract_dir,
            'destination' => $theme_path,
        ]);

        // Ensure theme directory exists
        if (!$wp_filesystem->is_dir($theme_path)) {
            $wp_filesystem->mkdir($theme_path, FS_CHMOD_DIR);
            $this->logger->log('Deployment', "Created theme directory: $theme_path");
        }

        $this->log_deployment($deployment_id, 'Deploying files to theme directory...');

        // Count files to be copied for logging
        $file_count = 0;
        $dir_count = 0;
        if (is_dir($temp_extract_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_extract_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $file_count++;
                } else {
                    $dir_count++;
                }
            }
        }

        $this->logger->log_deployment_step($deployment_id, 'Starting File Copy', 'in_progress', [
            'file_count' => $file_count,
            'directory_count' => $dir_count,
        ]);

        // Copy files from extracted directory to theme directory
        // Increase timeout for large theme deployments
        @set_time_limit(300);

        $copy_result = copy_dir($temp_extract_dir, $theme_path);

        if (is_wp_error($copy_result)) {
            $this->logger->error('Deployment', "Deployment #$deployment_id copy_dir failed", $copy_result);
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => $copy_result->get_error_message(),
            ]);
            $this->log_deployment($deployment_id, 'Deployment failed: ' . $copy_result->get_error_message());
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'Files Copied', 'success', [
            'files_copied' => $file_count,
            'directories_copied' => $dir_count,
        ]);

        // Clean up temp files
        $wp_filesystem->delete($artifact_zip);
        $wp_filesystem->delete($temp_extract_dir, true);

        $this->logger->log_deployment_step($deployment_id, 'Cleanup Complete', 'success');

        // Update deployment as successful
        $this->database->update_deployment($deployment_id, [
            'status' => 'success',
            'deployed_at' => current_time('mysql'),
        ]);

        $this->logger->log_deployment_step($deployment_id, 'Deployment Complete', 'SUCCESS!');
        $this->log_deployment($deployment_id, 'Deployment completed successfully!');

        // Trigger action hook
        do_action('github_deploy_completed', $deployment_id);
    }

    /**
     * Backup current theme
     */
    public function backup_current_theme(int $deployment_id): string|false
    {
        global $wp_filesystem;

        if (!WP_Filesystem()) {
            $this->logger->error('Deployment', "Backup failed - WP_Filesystem init failed");
            return false;
        }

        $theme_path = $this->settings->get_theme_path();
        $backup_dir = $this->settings->get_backup_directory();
        $backup_filename = 'backup-' . $deployment_id . '-' . time() . '.zip';
        $backup_path = $backup_dir . '/' . $backup_filename;

        $this->logger->log('Deployment', "Creating backup ZIP", [
            'theme_path' => $theme_path,
            'backup_path' => $backup_path,
            'theme_exists' => is_dir($theme_path),
        ]);

        // Skip backup if theme doesn't exist yet
        if (!is_dir($theme_path)) {
            $this->logger->log('Deployment', "Skipping backup - theme directory doesn't exist (first deployment)");
            return false;
        }

        // Create zip of current theme
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($backup_path, ZipArchive::CREATE) === true) {
                $this->logger->log('Deployment', "Adding files to backup ZIP...");
                $this->add_directory_to_zip($zip, $theme_path, basename($theme_path));
                $zip->close();

                $backup_size = file_exists($backup_path) ? filesize($backup_path) : 0;
                $this->logger->log('Deployment', "Backup created successfully", [
                    'backup_size' => $backup_size,
                ]);

                return $backup_path;
            } else {
                $this->logger->error('Deployment', "Failed to create backup ZIP file");
            }
        } else {
            $this->logger->error('Deployment', "ZipArchive class not available");
        }

        return false;
    }

    /**
     * Add directory to zip recursively
     */
    private function add_directory_to_zip(ZipArchive $zip, string $dir, string $zip_path): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = $zip_path . '/' . substr($file_path, strlen($dir) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
    }

    /**
     * Rollback to previous deployment
     */
    public function rollback_deployment(int $deployment_id): bool
    {
        $deployment = $this->database->get_deployment($deployment_id);

        if (!$deployment || empty($deployment->backup_path)) {
            return false;
        }

        global $wp_filesystem;

        if (!WP_Filesystem()) {
            return false;
        }

        $theme_path = $this->settings->get_theme_path();
        $temp_extract_dir = $this->get_temp_directory() . '/rollback-' . $deployment_id;

        // Extract backup
        $unzip_result = unzip_file($deployment->backup_path, $temp_extract_dir);

        if (is_wp_error($unzip_result)) {
            return false;
        }

        // Copy files back
        $copy_result = copy_dir($temp_extract_dir, $theme_path);

        if (is_wp_error($copy_result)) {
            return false;
        }

        // Clean up
        $wp_filesystem->delete($temp_extract_dir, true);

        // Update deployment status
        $this->database->update_deployment($deployment_id, [
            'status' => 'rolled_back',
        ]);

        do_action('github_deploy_rolled_back', $deployment_id);

        return true;
    }

    /**
     * Cancel a deployment
     */
    public function cancel_deployment(int $deployment_id): bool
    {
        $deployment = $this->database->get_deployment($deployment_id);

        if (!$deployment) {
            $this->logger->error('Deployment', "Deployment #$deployment_id not found");
            return false;
        }

        // Can only cancel deployments in pending or building status
        if (!in_array($deployment->status, ['pending', 'building'])) {
            $this->logger->error('Deployment', "Deployment #$deployment_id cannot be cancelled (status: {$deployment->status})");
            return false;
        }

        $this->logger->log_deployment_step($deployment_id, 'Cancel Deployment', 'initiated');

        // If workflow run ID exists, cancel it on GitHub
        if (!empty($deployment->workflow_run_id)) {
            $this->logger->log_deployment_step($deployment_id, 'Cancel GitHub Workflow', 'started', [
                'workflow_run_id' => $deployment->workflow_run_id,
            ]);

            $cancel_result = $this->github_api->cancel_workflow_run($deployment->workflow_run_id);

            if (!$cancel_result['success']) {
                $this->logger->error('Deployment', "Deployment #$deployment_id failed to cancel workflow run", $cancel_result);
                $this->log_deployment($deployment_id, 'Failed to cancel GitHub workflow: ' . $cancel_result['message']);
                // Continue anyway to update database status
            } else {
                $this->logger->log_deployment_step($deployment_id, 'GitHub Workflow Cancelled', 'success');
                $this->log_deployment($deployment_id, 'GitHub workflow run cancellation requested.');
            }
        }

        // Update deployment status to cancelled
        $this->database->update_deployment($deployment_id, [
            'status' => 'cancelled',
            'error_message' => __('Deployment cancelled by user.', 'github-auto-deploy'),
        ]);

        $this->logger->log_deployment_step($deployment_id, 'Deployment Cancelled', 'success');
        $this->log_deployment($deployment_id, 'Deployment cancelled by user.');

        do_action('github_deploy_cancelled', $deployment_id);

        return true;
    }

    /**
     * Get temp directory
     */
    private function get_temp_directory(): string
    {
        $temp_dir = sys_get_temp_dir() . '/github-deploy';

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        return $temp_dir;
    }

    /**
     * Log deployment message
     */
    private function log_deployment(int $deployment_id, string $message): void
    {
        $deployment = $this->database->get_deployment($deployment_id);

        if (!$deployment) {
            return;
        }

        $timestamp = current_time('mysql');
        $log_entry = "[{$timestamp}] {$message}\n";

        $current_logs = $deployment->deployment_logs ?? '';
        $updated_logs = $current_logs . $log_entry;

        $this->database->update_deployment($deployment_id, [
            'deployment_logs' => $updated_logs,
        ]);
    }
}
