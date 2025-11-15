<?php

/**
 * Data Formatter utility class
 * Provides static methods for formatting data structures
 * Eliminates duplicate formatting logic across AJAX handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Data_Formatter
{

    /**
     * Format repository data for Select2 dropdown
     *
     * @param mixed $repo Repository object or array from GitHub API
     * @return array Formatted repository data
     */
    public static function format_repository_for_select($repo): array
    {
        // Handle both object and array
        $repo_data = is_object($repo) ? (array) $repo : $repo;
        $owner = is_object($repo->owner ?? null) ? (array) $repo->owner : ($repo_data['owner'] ?? []);
        $full_name = $repo_data['full_name'] ?? '';

        return [
            'id' => $full_name,  // Select2 uses full_name as ID
            'text' => $full_name,  // Select2 display text
            'full_name' => $full_name,
            'name' => $repo_data['name'] ?? '',
            'owner' => is_array($owner) ? ($owner['login'] ?? '') : ($owner->login ?? ''),
            'private' => $repo_data['private'] ?? false,
            'default_branch' => $repo_data['default_branch'] ?? 'main',
            'description' => $repo_data['description'] ?? '',
        ];
    }

    /**
     * Format workflow data for Select2 dropdown
     *
     * @param mixed $workflow Workflow object or array from GitHub API
     * @return array Formatted workflow data
     */
    public static function format_workflow_for_select($workflow): array
    {
        // Handle both object and array
        $workflow_data = is_object($workflow) ? (array) $workflow : $workflow;

        // Extract filename from path
        $path = $workflow_data['path'] ?? '';
        $filename = basename($path);

        return [
            'id' => $workflow_data['id'] ?? 0,
            'name' => sanitize_text_field($workflow_data['name'] ?? $filename),
            'filename' => sanitize_file_name($filename),
            'path' => sanitize_text_field($path),
            'state' => sanitize_text_field($workflow_data['state'] ?? 'unknown'),
        ];
    }

    /**
     * Format deployment data for display
     *
     * @param object $deployment Deployment object from database
     * @return array Formatted deployment data
     */
    public static function format_deployment_for_display(object $deployment): array
    {
        return [
            'id' => (int) $deployment->id,
            'commit_hash' => esc_html($deployment->commit_hash),
            'commit_hash_short' => esc_html(substr($deployment->commit_hash, 0, 7)),
            'commit_message' => esc_html($deployment->commit_message),
            'commit_author' => esc_html($deployment->commit_author),
            'status' => esc_attr($deployment->status),
            'status_label' => esc_html(ucfirst(str_replace('_', ' ', $deployment->status))),
            'trigger_type' => esc_html(ucfirst($deployment->trigger_type)),
            'created_at' => esc_html($deployment->created_at),
            'deployed_at' => esc_html($deployment->deployed_at ?? $deployment->created_at),
            'build_url' => !empty($deployment->build_url) ? esc_url($deployment->build_url) : '',
            'error_message' => !empty($deployment->error_message) ? esc_html($deployment->error_message) : '',
        ];
    }

    /**
     * Format deployment data for JSON response (admin dashboard/history)
     *
     * @param object $deployment Deployment object from database
     * @return array Formatted deployment for JSON
     */
    public static function format_deployment_for_json(object $deployment): array
    {
        return [
            'id' => (int) $deployment->id,
            'commit_hash' => $deployment->commit_hash,
            'commit_message' => $deployment->commit_message,
            'commit_author' => $deployment->commit_author,
            'status' => $deployment->status,
            'trigger_type' => $deployment->trigger_type,
            'created_at' => $deployment->created_at,
            'deployed_at' => $deployment->deployed_at ?? $deployment->created_at,
            'deployment_logs' => $deployment->deployment_logs ?? '',
            'build_url' => $deployment->build_url ?? '',
            'error_message' => $deployment->error_message ?? '',
            'backup_path' => $deployment->backup_path ?? '',
        ];
    }

    /**
     * Format branch data for Select2 dropdown
     *
     * @param mixed $branch Branch object or array from GitHub API
     * @return array Formatted branch data
     */
    public static function format_branch_for_select($branch): array
    {
        // Handle both object and array
        if (is_string($branch)) {
            return [
                'name' => $branch,
                'label' => $branch,
            ];
        }

        $branch_data = is_object($branch) ? (array) $branch : $branch;

        return [
            'name' => $branch_data['name'] ?? '',
            'label' => $branch_data['name'] ?? '',
            'protected' => $branch_data['protected'] ?? false,
        ];
    }

    /**
     * Format list of repositories for AJAX response
     *
     * @param array $repos Array of repository objects/arrays
     * @return array Array of formatted repositories
     */
    public static function format_repositories(array $repos): array
    {
        return array_map([self::class, 'format_repository_for_select'], $repos);
    }

    /**
     * Format list of workflows for AJAX response
     *
     * @param array $workflows Array of workflow objects/arrays
     * @return array Array of formatted workflows
     */
    public static function format_workflows(array $workflows): array
    {
        return array_map([self::class, 'format_workflow_for_select'], $workflows);
    }

    /**
     * Format list of branches for AJAX response
     *
     * @param array $branches Array of branch objects/arrays
     * @return array Array of formatted branches
     */
    public static function format_branches(array $branches): array
    {
        return array_map([self::class, 'format_branch_for_select'], $branches);
    }

    /**
     * Format list of deployments for AJAX response
     *
     * @param array $deployments Array of deployment objects
     * @return array Array of formatted deployments
     */
    public static function format_deployments(array $deployments): array
    {
        return array_map([self::class, 'format_deployment_for_json'], $deployments);
    }

    /**
     * Format GitHub connection details for display
     *
     * @param array $github_data GitHub data from settings
     * @return array Formatted connection details
     */
    public static function format_github_connection(array $github_data): array
    {
        return [
            'account_login' => esc_html($github_data['account_login'] ?? ''),
            'account_avatar' => !empty($github_data['account_avatar']) ? esc_url($github_data['account_avatar']) : '',
            'repo_full_name' => esc_html($github_data['selected_repo_full_name'] ?? ''),
            'repo_branch' => esc_html($github_data['selected_repo_default_branch'] ?? ''),
            'connected_at' => esc_html($github_data['connected_at'] ?? ''),
        ];
    }

    /**
     * Sanitize repository data from user input
     *
     * @param array $data Repository data to sanitize
     * @return array Sanitized repository data
     */
    public static function sanitize_repository_data(array $data): array
    {
        return [
            'owner' => sanitize_text_field($data['owner'] ?? ''),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'branch' => sanitize_text_field($data['branch'] ?? 'main'),
            'full_name' => sanitize_text_field($data['full_name'] ?? ''),
        ];
    }

    /**
     * Sanitize workflow data from user input
     *
     * @param array $data Workflow data to sanitize
     * @return array Sanitized workflow data
     */
    public static function sanitize_workflow_data(array $data): array
    {
        return [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'filename' => sanitize_file_name($data['filename'] ?? ''),
        ];
    }
}
