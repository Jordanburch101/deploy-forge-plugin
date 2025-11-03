<?php
/**
 * GitHub API wrapper class
 * Handles all GitHub API v3 interactions using wp_remote_request()
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_API {

    private GitHub_Deploy_Settings $settings;
    private GitHub_Deploy_Debug_Logger $logger;
    private const API_BASE = 'https://api.github.com';
    private const USER_AGENT = 'WordPress-GitHub-Deploy/1.0';

    public function __construct(GitHub_Deploy_Settings $settings, GitHub_Deploy_Debug_Logger $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Test connection to GitHub API and repository
     */
    public function test_connection(): array {
        $response = $this->request('GET', "/repos/{$this->settings->get_repo_full_name()}");

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            return [
                'success' => true,
                'message' => __('Successfully connected to repository!', 'github-auto-deploy'),
                'data' => $response['body'],
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to connect to repository.', 'github-auto-deploy'),
        ];
    }

    /**
     * Trigger a GitHub Actions workflow
     */
    public function trigger_workflow(string $workflow_name, ?string $ref = null): array {
        if (!$ref) {
            $ref = $this->settings->get('github_branch');
        }

        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/workflows/{$workflow_name}/dispatches";

        $response = $this->request('POST', $endpoint, [
            'ref' => $ref,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 204) {
            return [
                'success' => true,
                'message' => __('Workflow triggered successfully!', 'github-auto-deploy'),
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to trigger workflow.', 'github-auto-deploy'),
        ];
    }

    /**
     * Get workflow run status
     */
    public function get_workflow_run_status(int $run_id): array {
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/runs/{$run_id}";

        $response = $this->request('GET', $endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            return [
                'success' => true,
                'data' => $response['body'],
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to get workflow run status.', 'github-auto-deploy'),
        ];
    }

    /**
     * Get latest workflow runs
     */
    public function get_latest_workflow_runs(int $limit = 5): array {
        $workflow_name = $this->settings->get('github_workflow_name');
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/workflows/{$workflow_name}/runs";

        // Use transient cache
        $cache_key = 'github_deploy_runs_' . md5($endpoint);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('GET', $endpoint, ['per_page' => $limit]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            $result = [
                'success' => true,
                'data' => $response['body']->workflow_runs ?? [],
            ];

            set_transient($cache_key, $result, 2 * MINUTE_IN_SECONDS);
            return $result;
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to get workflow runs.', 'github-auto-deploy'),
        ];
    }

    /**
     * Get artifacts for a workflow run
     */
    public function get_workflow_artifacts(int $run_id): array {
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/runs/{$run_id}/artifacts";

        $response = $this->request('GET', $endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            return [
                'success' => true,
                'data' => $response['body']->artifacts ?? [],
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to get artifacts.', 'github-auto-deploy'),
        ];
    }

    /**
     * Download artifact
     */
    public function download_artifact(int $artifact_id, string $destination): bool|WP_Error {
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/artifacts/{$artifact_id}/zip";

        $token = $this->settings->get_github_token();

        $this->logger->log('GitHub_API', "Downloading artifact #$artifact_id to $destination");

        // Step 1: Get the download URL from GitHub (this will return a redirect)
        $args = [
            'timeout' => 30,
            'redirection' => 0, // Don't follow redirects automatically
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => self::USER_AGENT,
            ],
        ];

        $response = wp_remote_get(self::API_BASE . $endpoint, $args);

        if (is_wp_error($response)) {
            $this->logger->error('GitHub_API', "Failed to get artifact download URL", $response);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        $this->logger->log('GitHub_API', "Artifact URL request response", [
            'status_code' => $status_code,
        ]);

        // GitHub returns 302 with Location header pointing to Azure Blob Storage
        if ($status_code === 302 || $status_code === 301) {
            $location = wp_remote_retrieve_header($response, 'location');

            if (!$location) {
                $this->logger->error('GitHub_API', "No redirect location provided");
                return new WP_Error('no_redirect', __('GitHub did not provide download URL', 'github-auto-deploy'));
            }

            $this->logger->log('GitHub_API', "Got Azure download URL, downloading...", [
                'url_length' => strlen($location),
                'is_azure' => strpos($location, 'blob.core.windows.net') !== false,
            ]);

            // Step 2: Download from Azure Blob Storage WITHOUT GitHub auth
            $download_args = [
                'timeout' => 300,
                'stream' => true,
                'filename' => $destination,
                // No Authorization header - Azure URL is pre-signed
            ];

            $download_response = wp_remote_get($location, $download_args);

            if (is_wp_error($download_response)) {
                $this->logger->error('GitHub_API', "Azure download failed", $download_response);
                return $download_response;
            }

            $download_status = wp_remote_retrieve_response_code($download_response);

            $this->logger->log('GitHub_API', "Azure download response", [
                'status_code' => $download_status,
                'file_exists' => file_exists($destination),
                'file_size' => file_exists($destination) ? filesize($destination) : 0,
            ]);

            if ($download_status === 200 && file_exists($destination) && filesize($destination) > 0) {
                $this->logger->log('GitHub_API', "Artifact download successful!");
                return true;
            }

            $this->logger->error('GitHub_API', "Azure download failed", [
                'status_code' => $download_status,
                'file_exists' => file_exists($destination),
                'file_size' => file_exists($destination) ? filesize($destination) : 0,
            ]);

            return new WP_Error(
                'download_failed',
                sprintf(__('Failed to download from Azure. Status: %d', 'github-auto-deploy'), $download_status)
            );
        }

        // If we got 200 directly (shouldn't happen for private repos)
        if ($status_code === 200 && file_exists($destination) && filesize($destination) > 0) {
            $this->logger->log('GitHub_API', "Direct download successful (no redirect)");
            return true;
        }

        $this->logger->error('GitHub_API', "Unexpected response from GitHub", [
            'status_code' => $status_code,
            'expected' => '302 redirect',
        ]);

        return new WP_Error(
            'unexpected_response',
            sprintf(__('Unexpected response from GitHub. Status: %d', 'github-auto-deploy'), $status_code)
        );
    }

    /**
     * Get recent commits
     */
    public function get_recent_commits(int $limit = 10): array {
        $branch = $this->settings->get('github_branch');
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/commits";

        // Use transient cache
        $cache_key = 'github_deploy_commits_' . md5($endpoint . $branch);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('GET', $endpoint, [
            'sha' => $branch,
            'per_page' => $limit,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            $result = [
                'success' => true,
                'data' => $response['body'],
            ];

            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
            return $result;
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to get commits.', 'github-auto-deploy'),
        ];
    }

    /**
     * Get commit details
     */
    public function get_commit_details(string $commit_hash): array {
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/commits/{$commit_hash}";

        $response = $this->request('GET', $endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            return [
                'success' => true,
                'data' => $response['body'],
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to get commit details.', 'github-auto-deploy'),
        ];
    }

    /**
     * Make a request to GitHub API
     */
    private function request(string $method, string $endpoint, ?array $data = null): array|WP_Error {
        $token = $this->settings->get_github_token();

        if (empty($token)) {
            $error = new WP_Error('no_token', __('GitHub token not configured.', 'github-auto-deploy'));
            $this->logger->error('GitHub_API', 'No GitHub token configured', $error);
            return $error;
        }

        $url = self::API_BASE . $endpoint;

        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
            $data = null;
        }

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => self::USER_AGENT,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = wp_json_encode($data);
        }

        // Log request
        $this->logger->log_api_request($method, $endpoint, $data ?: [], $args['headers']);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->logger->log_api_response($endpoint, 0, null, $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log response
        $parsed_body = json_decode($body);
        $this->logger->log_api_response(
            $endpoint,
            $status_code,
            $parsed_body ?: $body,
            $status_code >= 400 ? "HTTP $status_code" : null
        );

        // Check rate limiting
        $rate_limit_remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');
        if ($rate_limit_remaining !== '' && (int) $rate_limit_remaining < 10) {
            $this->logger->log('GitHub_API', "Rate limit warning: $rate_limit_remaining requests remaining");
            do_action('github_deploy_rate_limit_warning', $rate_limit_remaining);
        }

        return [
            'status' => $status_code,
            'body' => json_decode($body),
            'headers' => wp_remote_retrieve_headers($response),
        ];
    }

    /**
     * Get rate limit information
     */
    public function get_rate_limit(): array {
        $response = $this->request('GET', '/rate_limit');

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            return [
                'success' => true,
                'data' => $response['body'],
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to get rate limit information.', 'github-auto-deploy'),
        ];
    }

    /**
     * Get user's repositories (for repo selector)
     */
    public function get_user_repositories(int $per_page = 100): array {
        $cache_key = 'github_deploy_user_repos';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $response = $this->request('GET', '/user/repos', [
            'per_page' => $per_page,
            'sort' => 'updated',
            'affiliation' => 'owner,collaborator,organization_member',
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            $repos = is_array($response['body']) ? $response['body'] : [];

            // Format repos for dropdown
            $formatted_repos = array_map(function($repo) {
                return [
                    'id' => $repo->id ?? 0,
                    'full_name' => $repo->full_name ?? '',
                    'name' => $repo->name ?? '',
                    'owner' => $repo->owner->login ?? '',
                    'private' => $repo->private ?? false,
                    'default_branch' => $repo->default_branch ?? 'main',
                    'updated_at' => $repo->updated_at ?? '',
                    'has_workflows' => $repo->has_actions ?? false,
                ];
            }, $repos);

            $result = [
                'success' => true,
                'data' => $formatted_repos,
            ];

            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
            return $result;
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to get repositories.', 'github-auto-deploy'),
        ];
    }

    /**
     * Get repository workflows (for workflow selector)
     */
    public function get_repository_workflows(string $owner, string $repo): array {
        $cache_key = 'github_deploy_workflows_' . md5($owner . $repo);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $endpoint = "/repos/{$owner}/{$repo}/actions/workflows";
        $response = $this->request('GET', $endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            $workflows = $response['body']->workflows ?? [];

            // Format workflows for dropdown
            $formatted_workflows = array_map(function($workflow) {
                $path_parts = explode('/', $workflow->path ?? '');
                $filename = end($path_parts);

                return [
                    'id' => $workflow->id ?? 0,
                    'name' => $workflow->name ?? '',
                    'path' => $workflow->path ?? '',
                    'filename' => $filename,
                    'state' => $workflow->state ?? 'unknown',
                ];
            }, $workflows);

            $result = [
                'success' => true,
                'data' => $formatted_workflows,
            ];

            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
            return $result;
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to get workflows.', 'github-auto-deploy'),
        ];
    }
}
