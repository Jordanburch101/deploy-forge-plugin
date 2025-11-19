<?php

/**
 * Deployment manager class
 * Orchestrates the entire deployment workflow
 * 
 * @phpstan-type ZipArchive \ZipArchive
 * @phpstan-type RecursiveIteratorIterator \RecursiveIteratorIterator
 * @phpstan-type RecursiveDirectoryIterator \RecursiveDirectoryIterator
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Deployment_Manager
{

    private Deploy_Forge_Settings $settings;
    private Deploy_Forge_GitHub_API $github_api;
    private Deploy_Forge_Database $database;
    private Deploy_Forge_Debug_Logger $logger;

    public function __construct(Deploy_Forge_Settings $settings, Deploy_Forge_GitHub_API $github_api, Deploy_Forge_Database $database, Deploy_Forge_Debug_Logger $logger)
    {
        $this->settings = $settings;
        $this->github_api = $github_api;
        $this->database = $database;
        $this->logger = $logger;
    }

    /**
     * Start a new deployment
     */
    public function start_deployment(string $commit_hash, string $trigger_type = 'manual', int $user_id = 0, array $commit_data = []): int|false|array
    {
        $this->logger->log_deployment_step(0, 'Start Deployment', 'initiated', [
            'commit_hash' => $commit_hash,
            'trigger_type' => $trigger_type,
            'user_id' => $user_id,
            'commit_data' => $commit_data,
        ]);

        // Check if there's a deployment currently building
        $building_deployment = $this->database->get_building_deployment();

        if ($building_deployment) {
            $this->logger->log('Deployment', 'Found existing building deployment', [
                'existing_deployment_id' => $building_deployment->id,
                'existing_status' => $building_deployment->status,
                'trigger_type' => $trigger_type,
            ]);

            // If manual deploy, block and return error with deployment info
            if ($trigger_type === 'manual') {
                return [
                    'error' => 'deployment_in_progress',
                    'message' => __('A deployment is already in progress. Please cancel it before starting a new one.', 'deploy-forge'),
                    'building_deployment' => $building_deployment,
                ];
            }

            // If webhook/auto deploy, cancel existing deployment first
            if (in_array($trigger_type, ['webhook', 'auto'])) {
                $this->logger->log('Deployment', 'Auto-cancelling existing deployment', [
                    'existing_deployment_id' => $building_deployment->id,
                ]);

                $cancel_result = $this->cancel_deployment($building_deployment->id);

                if (!$cancel_result) {
                    $this->logger->error('Deployment', 'Failed to cancel existing deployment');
                    return false;
                }

                $this->logger->log('Deployment', 'Successfully cancelled existing deployment');
            }
        }

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

        // Check deployment method
        $deployment_method = $this->settings->get('deployment_method', 'github_actions');

        if ($deployment_method === 'direct_clone') {
            // Direct clone - skip GitHub Actions, download and deploy immediately
            $this->logger->log_deployment_step($deployment_id, 'Direct Clone Mode', 'started');
            $direct_result = $this->direct_clone_deployment($deployment_id, $commit_hash);

            if (!$direct_result) {
                $this->logger->error('Deployment', "Deployment #$deployment_id direct clone failed");
                $this->database->update_deployment($deployment_id, [
                    'status' => 'failed',
                    'error_message' => __('Failed to deploy via direct clone.', 'deploy-forge'),
                ]);
                return false;
            }

            return $deployment_id;
        }

        // GitHub Actions workflow (default)
        $workflow_result = $this->trigger_github_build($deployment_id, $commit_hash);

        if (!$workflow_result) {
            $this->logger->error('Deployment', "Deployment #$deployment_id failed to trigger workflow");
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('Failed to trigger GitHub Actions workflow.', 'deploy-forge'),
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
     * Deploy directly from repository clone (no GitHub Actions)
     * Downloads repository ZIP at specific commit and deploys immediately
     */
    private function direct_clone_deployment(int $deployment_id, string $commit_hash): bool
    {
        // Create temp directory
        $temp_dir = $this->get_temp_directory();
        $repo_zip = $temp_dir . '/repo-' . $deployment_id . '.zip';

        $this->logger->log_deployment_step($deployment_id, 'Direct Clone', 'started', [
            'commit_hash' => $commit_hash,
            'temp_dir' => $temp_dir,
            'repo_zip' => $repo_zip,
        ]);

        $this->log_deployment($deployment_id, 'Downloading repository from GitHub (direct clone)...');

        // Update status to building (downloading)
        $this->database->update_deployment($deployment_id, [
            'status' => 'building',
            'deployment_logs' => 'Downloading repository via direct clone...',
        ]);

        // Download repository at specific commit
        $download_result = $this->github_api->download_repository($commit_hash, $repo_zip);

        if (is_wp_error($download_result)) {
            $this->logger->error('Deployment', "Deployment #$deployment_id repository download failed", $download_result);
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => $download_result->get_error_message(),
            ]);
            $this->log_deployment($deployment_id, 'Download failed: ' . $download_result->get_error_message());
            return false;
        }

        $this->logger->log_deployment_step($deployment_id, 'Repository Downloaded', 'success', [
            'file_size' => file_exists($repo_zip) ? filesize($repo_zip) : 0,
        ]);

        $this->log_deployment($deployment_id, 'Repository downloaded successfully.');

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

        // Extract and deploy (reuse existing method)
        $this->extract_and_deploy($deployment_id, $repo_zip);

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
                    'error_message' => sprintf(__('Build failed with conclusion: %s', 'deploy-forge'), $conclusion),
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
            // Handle both array and object
            $runId = is_object($run) ? $run->id : $run['id'];
            $runSha = is_object($run) ? $run->head_sha : $run['head_sha'];
            $runUrl = is_object($run) ? $run->html_url : $run['html_url'];

            $this->logger->log('Deployment', "Checking run #{$runId} with SHA: {$runSha} vs {$commit_hash}");

            if ($runSha === $commit_hash) {
                $this->database->update_deployment($deployment_id, [
                    'workflow_run_id' => $runId,
                    'build_url' => $runUrl,
                ]);
                $this->logger->log_deployment_step($deployment_id, 'Workflow Run Matched', 'success', [
                    'workflow_run_id' => $runId,
                    'build_url' => $runUrl,
                ]);
                $this->log_deployment($deployment_id, 'Found workflow run ID: ' . $runId);

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

        // Skip if already deployed
        if ($deployment->status === 'success') {
            $this->logger->log('Deployment', "Deployment #$deployment_id already completed, skipping");
            return;
        }

        // Skip if deployment failed (don't retry)
        if ($deployment->status === 'failed') {
            $this->logger->log('Deployment', "Deployment #$deployment_id previously failed, skipping");
            return;
        }

        // Check deployment lock to prevent concurrent processing
        $locked_deployment = $this->database->get_deployment_lock();
        if ($locked_deployment && $locked_deployment !== $deployment_id) {
            $this->logger->log('Deployment', "Deployment #$deployment_id skipped - another deployment (#$locked_deployment) is processing");
            // Reschedule for later
            wp_schedule_single_event(time() + 60, 'deploy_forge_process_queued_deployment', [$deployment_id]);
            return;
        }

        // Set lock for this deployment
        $this->database->set_deployment_lock($deployment_id, 300);

        // Update status from 'queued' to 'deploying'
        $this->database->update_deployment($deployment_id, [
            'status' => 'deploying',
        ]);

        $this->logger->log_deployment_step($deployment_id, 'Process Build Success', 'started');
        $this->log_deployment($deployment_id, 'Build completed successfully. Starting deployment...');

        try {
            // Get artifacts
            $this->logger->log_deployment_step($deployment_id, 'Fetch Artifacts', 'started', [
                'workflow_run_id' => $deployment->workflow_run_id,
            ]);

            $artifacts_result = $this->github_api->get_workflow_artifacts($deployment->workflow_run_id);

            if (!$artifacts_result['success'] || empty($artifacts_result['data'])) {
                $this->logger->error('Deployment', "Deployment #$deployment_id no artifacts found", $artifacts_result);
                $this->database->update_deployment($deployment_id, [
                    'status' => 'failed',
                    'error_message' => __('No artifacts found for successful build.', 'deploy-forge'),
                ]);
                $this->database->release_deployment_lock();
                return;
            }

            // Get first artifact (assuming single artifact)
            $artifact = $artifacts_result['data'][0];

            // Handle both array and object formats
            $artifact_name = is_array($artifact) ? ($artifact['name'] ?? 'unknown') : ($artifact->name ?? 'unknown');
            $artifact_id = is_array($artifact) ? ($artifact['id'] ?? null) : ($artifact->id ?? null);

            $this->logger->log_deployment_step($deployment_id, 'Artifacts Found', 'success', [
                'artifact_count' => count($artifacts_result['data']),
                'artifact_name' => $artifact_name,
                'artifact_id' => $artifact_id,
            ]);

            if (!$artifact_id) {
                $this->logger->error('Deployment', "Deployment #$deployment_id artifact has no ID", ['artifact' => $artifact]);
                $this->database->update_deployment($deployment_id, [
                    'status' => 'failed',
                    'error_message' => __('Artifact ID not found.', 'deploy-forge'),
                ]);
                $this->database->release_deployment_lock();
                return;
            }

            // Download and deploy
            $this->download_and_deploy($deployment_id, $artifact_id);
        } catch (Exception $e) {
            // Catch any exceptions and update deployment status
            $this->logger->error('Deployment', "Deployment #$deployment_id threw exception", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => sprintf(__('Deployment failed: %s', 'deploy-forge'), $e->getMessage()),
            ]);
            $this->database->release_deployment_lock();
        }
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
                'error_message' => __('Artifact file not found.', 'deploy-forge'),
            ]);
            return;
        }

        // Skip WP_Filesystem - it hangs in background processes
        // We'll use native PHP functions instead
        $this->logger->log_deployment_step($deployment_id, 'WP_Filesystem', 'skipped - using native PHP functions');
        $this->log_deployment($deployment_id, 'Extracting artifact...');

        // Extract to temp directory
        $temp_extract_dir = $this->get_temp_directory() . '/extract-' . $deployment_id;

        // Create extraction directory
        if (!is_dir($temp_extract_dir)) {
            if (!mkdir($temp_extract_dir, 0755, true)) {
                $this->logger->error('Deployment', "Failed to create extraction directory: {$temp_extract_dir}");
                $this->database->update_deployment($deployment_id, [
                    'status' => 'failed',
                    'error_message' => __('Failed to create extraction directory.', 'deploy-forge'),
                ]);
                return;
            }
        }

        $this->logger->log_deployment_step($deployment_id, 'Unzip Artifact', 'started', [
            'source' => $artifact_zip,
            'destination' => $temp_extract_dir,
            'file_size' => filesize($artifact_zip),
            'file_exists' => file_exists($artifact_zip),
        ]);

        // Increase timeout for large files
        @set_time_limit(300); // 5 minutes

        // Use native ZipArchive instead of WP_Filesystem-dependent unzip_file
        if (!class_exists('ZipArchive')) {
            $this->logger->error('Deployment', 'ZipArchive class not available');
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('ZipArchive extension not available on server.', 'deploy-forge'),
            ]);
            return;
        }

        /**
         * @var \ZipArchive $zip
         * @noinspection PhpUndefinedClassInspection
         */
        $zip = new ZipArchive();
        $zip_open_result = $zip->open($artifact_zip);

        if ($zip_open_result !== true) {
            $this->logger->error('Deployment', "Failed to open ZIP file", [
                'error_code' => $zip_open_result,
            ]);
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => sprintf(__('Failed to open ZIP file (error code: %d).', 'deploy-forge'), $zip_open_result),
            ]);
            return;
        }

        $extract_result = $zip->extractTo($temp_extract_dir);
        $zip->close();

        if (!$extract_result) {
            $this->logger->error('Deployment', "Failed to extract ZIP file");
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('Failed to extract ZIP file.', 'deploy-forge'),
            ]);
            return;
        }

        $unzip_result = true; // Set for compatibility with rest of code

        if (is_wp_error($unzip_result)) {
            /** @var WP_Error $unzip_result */
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

                // Create final extraction directory
                if (!is_dir($final_extract_dir)) {
                    if (!mkdir($final_extract_dir, 0755, true)) {
                        $this->logger->error('Deployment', "Failed to create final extraction directory");
                        $this->database->update_deployment($deployment_id, [
                            'status' => 'failed',
                            'error_message' => __('Failed to create extraction directory.', 'deploy-forge'),
                        ]);
                        return;
                    }
                }

                // Extract inner zip using native ZipArchive
                $this->logger->log_deployment_step($deployment_id, 'Opening Inner ZIP', 'starting', [
                    'inner_zip_path' => $single_file_path,
                    'inner_zip_size' => file_exists($single_file_path) ? filesize($single_file_path) : 0,
                ]);

                /**
                 * @var \ZipArchive $inner_zip
                 * @noinspection PhpUndefinedClassInspection
                 */
                $inner_zip = new ZipArchive();
                $inner_zip_open = $inner_zip->open($single_file_path);

                $this->logger->log_deployment_step($deployment_id, 'Inner ZIP Open Result', 'checked', [
                    'result' => $inner_zip_open,
                    'success' => $inner_zip_open === true,
                ]);

                if ($inner_zip_open !== true) {
                    $this->logger->error('Deployment', "Failed to open inner ZIP file", [
                        'error_code' => $inner_zip_open,
                        'file_path' => $single_file_path,
                    ]);
                    $this->database->update_deployment($deployment_id, [
                        'status' => 'failed',
                        'error_message' => sprintf(__('Failed to open inner ZIP file (error code: %d).', 'deploy-forge'), $inner_zip_open),
                    ]);
                    return;
                }

                $this->logger->log_deployment_step($deployment_id, 'Extracting Inner ZIP', 'in_progress', [
                    'destination' => $final_extract_dir,
                ]);

                $inner_extract_result = $inner_zip->extractTo($final_extract_dir);

                $this->logger->log_deployment_step($deployment_id, 'Inner ZIP Extract Complete', 'checked', [
                    'result' => $inner_extract_result,
                ]);

                $inner_zip->close();

                if (!$inner_extract_result) {
                    $this->logger->error('Deployment', "Failed to extract inner ZIP file");
                    $this->database->update_deployment($deployment_id, [
                        'status' => 'failed',
                        'error_message' => __('Failed to extract inner ZIP file.', 'deploy-forge'),
                    ]);
                    return;
                }

                $this->logger->log_deployment_step($deployment_id, 'Cleaning Up Outer Extract', 'starting');

                // Clean up outer extraction and use inner as source
                $this->recursive_delete($temp_extract_dir);
                $temp_extract_dir = $final_extract_dir;

                $this->logger->log_deployment_step($deployment_id, 'Inner Archive Extracted', 'success');
            }
        }

        // Extract artifact directly to themes folder (preserves directory structure)
        $themes_base_path = WP_CONTENT_DIR . '/themes';

        $this->logger->log_deployment_step($deployment_id, 'Copy to Themes Directory', 'started', [
            'source' => $temp_extract_dir,
            'destination' => $themes_base_path,
        ]);

        $this->log_deployment($deployment_id, 'Deploying theme files...');

        // Count files to be copied for logging
        $file_count = 0;
        $dir_count = 0;
        if (is_dir($temp_extract_dir)) {
            /**
             * @var \RecursiveIteratorIterator $iterator
             * @noinspection PhpUndefinedClassInspection
             */
            $iterator = new RecursiveIteratorIterator(
                /** @noinspection PhpUndefinedClassInspection */
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

        // Copy entire extracted structure to themes directory
        // This preserves whatever directory structure the artifact has
        // Increase timeout for large theme deployments
        @set_time_limit(300);

        $copy_result = $this->recursive_copy($temp_extract_dir, $themes_base_path);

        if (!$copy_result) {
            $this->logger->error('Deployment', "Deployment #$deployment_id file copy failed");
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('Failed to copy files to theme directory.', 'deploy-forge'),
            ]);
            $this->log_deployment($deployment_id, 'Deployment failed: Unable to copy files.');
            return;
        }

        $this->logger->log_deployment_step($deployment_id, 'Files Copied', 'success', [
            'files_copied' => $file_count,
            'directories_copied' => $dir_count,
        ]);

        // Clean up temp files using native PHP
        @unlink($artifact_zip);
        $this->recursive_delete($temp_extract_dir);

        $this->logger->log_deployment_step($deployment_id, 'Cleanup Complete', 'success');

        // Update deployment as successful
        $this->database->update_deployment($deployment_id, [
            'status' => 'success',
            'deployed_at' => current_time('mysql'),
        ]);

        // Release deployment lock
        $this->database->release_deployment_lock();

        $this->logger->log_deployment_step($deployment_id, 'Deployment Complete', 'SUCCESS!');
        $this->log_deployment($deployment_id, 'Deployment completed successfully!');

        // Trigger action hook
        do_action('deploy_forge_completed', $deployment_id);
    }

    /**
     * Backup current theme
     */
    public function backup_current_theme(int $deployment_id): string|false
    {
        // Skip WP_Filesystem - use native PHP
        $theme_path = $this->settings->get_theme_path();
        $backup_dir = $this->settings->get_backup_directory();
        $backup_filename = 'backup-' . $deployment_id . '-' . time() . '.zip';
        $backup_path = $backup_dir . '/' . $backup_filename;

        // Ensure backup directory exists
        if (!is_dir($backup_dir)) {
            if (!mkdir($backup_dir, 0755, true)) {
                $this->logger->error('Deployment', "Failed to create backup directory: {$backup_dir}");
                return false;
            }
        }

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
            /**
             * @var \ZipArchive $zip
             * @noinspection PhpUndefinedClassInspection
             */
            $zip = new ZipArchive();
            /** @noinspection PhpUndefinedClassInspection */
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
     * @param object $zip ZipArchive instance
     * @param string $dir Directory path
     * @param string $zip_path Path in zip
     * @suppress PhanUndeclaredClassReference
     */
    private function add_directory_to_zip($zip, string $dir, string $zip_path): void
    {
        /**
         * @var \RecursiveIteratorIterator $files
         * @noinspection PhpUndefinedClassInspection
         */
        $files = new RecursiveIteratorIterator(
            /** @noinspection PhpUndefinedClassInspection */
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

        // Skip WP_Filesystem - use native PHP
        $theme_path = $this->settings->get_theme_path();
        $temp_extract_dir = $this->get_temp_directory() . '/rollback-' . $deployment_id;

        // Create extraction directory
        if (!is_dir($temp_extract_dir)) {
            if (!mkdir($temp_extract_dir, 0755, true)) {
                $this->logger->error('Deployment', "Failed to create rollback extraction directory");
                return false;
            }
        }

        // Extract backup using native ZipArchive
        /**
         * @var \ZipArchive $zip
         * @noinspection PhpUndefinedClassInspection
         */
        $zip = new ZipArchive();
        if ($zip->open($deployment->backup_path) !== true) {
            $this->logger->error('Deployment', "Failed to open backup ZIP for rollback");
            return false;
        }

        if (!$zip->extractTo($temp_extract_dir)) {
            $zip->close();
            $this->logger->error('Deployment', "Failed to extract backup ZIP for rollback");
            return false;
        }
        $zip->close();

        // Copy files back using native PHP
        $copy_result = $this->recursive_copy($temp_extract_dir, $theme_path);

        if (!$copy_result) {
            $this->logger->error('Deployment', "Failed to copy rollback files");
            return false;
        }

        // Clean up
        $this->recursive_delete($temp_extract_dir);

        // Update deployment status
        $this->database->update_deployment($deployment_id, [
            'status' => 'rolled_back',
        ]);

        do_action('deploy_forge_rolled_back', $deployment_id);

        return true;
    }

    /**
     * Approve a pending deployment (manual approval workflow)
     * Updates the existing pending deployment and triggers the workflow
     */
    public function approve_pending_deployment(int $deployment_id, int $user_id): bool
    {
        $deployment = $this->database->get_deployment($deployment_id);

        if (!$deployment) {
            $this->logger->error('Deployment', "Deployment #$deployment_id not found");
            return false;
        }

        if ($deployment->status !== 'pending') {
            $this->logger->error('Deployment', "Deployment #$deployment_id cannot be approved (status: {$deployment->status})");
            return false;
        }

        $this->logger->log_deployment_step($deployment_id, 'Approve Deployment', 'initiated', [
            'approved_by_user_id' => $user_id,
        ]);

        // Update deployment to be triggered by the user who approved it
        $this->database->update_deployment($deployment_id, [
            'trigger_type' => 'manual',
            'triggered_by_user_id' => $user_id,
        ]);

        // Trigger GitHub Actions workflow
        $workflow_result = $this->trigger_github_build($deployment_id, $deployment->commit_hash);

        if (!$workflow_result) {
            $this->logger->error('Deployment', "Deployment #$deployment_id failed to trigger workflow after approval");
            $this->database->update_deployment($deployment_id, [
                'status' => 'failed',
                'error_message' => __('Failed to trigger GitHub Actions workflow after approval.', 'deploy-forge'),
            ]);
            return false;
        }

        $this->logger->log_deployment_step($deployment_id, 'Deployment Approved', 'success');

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
            'error_message' => __('Deployment cancelled by user.', 'deploy-forge'),
        ]);

        $this->logger->log_deployment_step($deployment_id, 'Deployment Cancelled', 'success');
        $this->log_deployment($deployment_id, 'Deployment cancelled by user.');

        do_action('deploy_forge_cancelled', $deployment_id);

        return true;
    }

    /**
     * Get temp directory
     */
    private function get_temp_directory(): string
    {
        $temp_dir = sys_get_temp_dir() . '/deploy-forge';

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        return $temp_dir;
    }

    /**
     * Find the actual theme directory within extracted artifact
     * Handles nested structures like reponame/theme/ or just reponame/
     *
     * @param string $extract_dir The directory where artifact was extracted
     * @return string|false Path to theme directory or false if not found
     */
    private function find_theme_directory(string $extract_dir)
    {
        // Recursively search for theme files (style.css or functions.php)
        // Max depth of 3 levels to handle structures like:
        // - reponame/style.css
        // - reponame/theme/style.css
        // - reponame/subdir/theme/style.css

        $max_depth = 3;

        $this->logger->log('Deployment', 'Searching for theme files in extracted artifact', [
            'search_root' => $extract_dir,
            'max_depth' => $max_depth,
        ]);

        return $this->find_theme_directory_recursive($extract_dir, 0, $max_depth);
    }

    /**
     * Recursively search for theme directory
     */
    private function find_theme_directory_recursive(string $dir, int $current_depth, int $max_depth)
    {
        if ($current_depth > $max_depth) {
            return false;
        }

        // Check if current directory has theme files
        if (file_exists($dir . '/style.css') || file_exists($dir . '/functions.php')) {
            $this->logger->log('Deployment', 'Found theme files', [
                'directory' => $dir,
                'depth' => $current_depth,
            ]);
            return $dir;
        }

        // Search subdirectories
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $item_path = $dir . '/' . $item;
            if (is_dir($item_path)) {
                $result = $this->find_theme_directory_recursive($item_path, $current_depth + 1, $max_depth);
                if ($result) {
                    return $result;
                }
            }
        }

        return false;
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

    /**
     * Recursively copy directory contents (native PHP replacement for copy_dir)
     */
    private function recursive_copy(string $source, string $dest): bool
    {
        if (!is_dir($source)) {
            return false;
        }

        // Create destination if needed
        if (!is_dir($dest)) {
            if (!mkdir($dest, 0755, true)) {
                return false;
            }
        }

        /**
         * @var \RecursiveIteratorIterator $iterator
         * @noinspection PhpUndefinedClassInspection
         */
        $iterator = new RecursiveIteratorIterator(
            /** @noinspection PhpUndefinedClassInspection */
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    if (!mkdir($target, 0755, true)) {
                        return false;
                    }
                }
            } else {
                if (!copy($item->getRealPath(), $target)) {
                    return false;
                }
                chmod($target, 0644);
            }
        }

        return true;
    }

    /**
     * Recursively delete directory (native PHP replacement for WP_Filesystem delete)
     */
    private function recursive_delete(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        /**
         * @var \RecursiveIteratorIterator $iterator
         * @noinspection PhpUndefinedClassInspection
         */
        $iterator = new RecursiveIteratorIterator(
            /** @noinspection PhpUndefinedClassInspection */
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        return rmdir($dir);
    }
}
