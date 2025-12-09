<?php

/**
 * GitHub API wrapper class
 * Handles all GitHub API v3 interactions using wp_remote_request()
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_GitHub_API
{

    private Deploy_Forge_Settings $settings;
    private Deploy_Forge_Debug_Logger $logger;
    private const API_BASE = 'https://api.github.com';
    private const USER_AGENT = 'WordPress-Deploy-Forge/1.0';
    private const BACKEND_URL = 'https://deploy-forge-website.vercel.app';

    public function __construct(Deploy_Forge_Settings $settings, Deploy_Forge_Debug_Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Test connection to GitHub API and repository
     */
    public function test_connection(): array
    {
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
                'message' => __('Successfully connected to repository!', 'deploy-forge'),
                'data' => $response['body'],
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']['message'] ?? __('Failed to connect to repository.', 'deploy-forge'),
        ];
    }

    /**
     * Trigger a GitHub Actions workflow
     */
    public function trigger_workflow(string $workflow_name, ?string $ref = null): array
    {
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

        // GitHub API returns 204 (No Content) or sometimes 200 (OK) on success
        if ($response['status'] === 204 || $response['status'] === 200) {
            return [
                'success' => true,
                'message' => __('Workflow triggered successfully!', 'deploy-forge'),
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']['message'] ?? __('Failed to trigger workflow.', 'deploy-forge'),
        ];
    }

    /**
     * Get available workflows for a repository
     * SECURITY: Only returns workflows with workflow_dispatch trigger enabled
     */
    public function get_workflows(string $repo_owner, string $repo_name): array
    {
        // Validate repository owner and name to prevent injection
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $repo_owner) || !preg_match('/^[a-zA-Z0-9_.-]+$/', $repo_name)) {
            return [
                'success' => false,
                'message' => __('Invalid repository owner or name format.', 'deploy-forge'),
            ];
        }

        $endpoint = "/repos/{$repo_owner}/{$repo_name}/actions/workflows";

        $response = $this->request('GET', $endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] !== 200) {
            return [
                'success' => false,
                'message' => $response['body']['message'] ?? __('Failed to fetch workflows.', 'deploy-forge'),
            ];
        }

        // Filter workflows to only include those with workflow_dispatch trigger
        // SECURITY: This prevents selecting workflows that can't be manually triggered
        $all_workflows = $response['body']['workflows'] ?? [];
        $dispatchable_workflows = [];

        $this->logger->log('GitHub_API', 'Processing workflows', [
            'total_workflows' => count($all_workflows),
            'workflows_raw' => $all_workflows,
        ]);

        foreach ($all_workflows as $workflow) {
            // Check if workflow state is active
            if (isset($workflow['state']) && $workflow['state'] !== 'active') {
                $this->logger->log('GitHub_API', 'Skipping inactive workflow', [
                    'name' => $workflow['name'] ?? 'unknown',
                    'state' => $workflow['state'] ?? 'unknown',
                ]);
                continue;
            }

            // Extract filename from path (e.g., ".github/workflows/deploy.yml" -> "deploy.yml")
            $filename = basename($workflow['path'] ?? '');

            // Only include .yml and .yaml files
            if (!preg_match('/\.(yml|yaml)$/i', $filename)) {
                $this->logger->log('GitHub_API', 'Skipping non-yml/yaml workflow', [
                    'name' => $workflow['name'] ?? 'unknown',
                    'filename' => $filename,
                ]);
                continue;
            }

            $workflow_data = [
                'name' => sanitize_text_field($workflow['name'] ?? $filename),
                'filename' => sanitize_file_name($filename),
                'path' => sanitize_text_field($workflow['path'] ?? ''),
                'state' => sanitize_text_field($workflow['state'] ?? 'unknown'),
            ];

            $this->logger->log('GitHub_API', 'Adding workflow to result', $workflow_data);

            $dispatchable_workflows[] = $workflow_data;
        }

        $this->logger->log('GitHub_API', 'Workflows processed', [
            'total_filtered' => count($dispatchable_workflows),
            'workflows' => $dispatchable_workflows,
        ]);

        return [
            'success' => true,
            'workflows' => $dispatchable_workflows,
            'total_count' => count($dispatchable_workflows),
        ];
    }

    /**
     * Get workflow run status
     */
    public function get_workflow_run_status(int $run_id): array
    {
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
            'message' => $response['body']['message'] ?? __('Failed to get workflow run status.', 'deploy-forge'),
        ];
    }

    /**
     * Get latest workflow runs
     */
    public function get_latest_workflow_runs(int $limit = 5): array
    {
        $workflow_name = $this->settings->get('github_workflow_name');
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/workflows/{$workflow_name}/runs";

        // Use transient cache
        $cache_key = 'deploy_forge_runs_' . md5($endpoint);
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
            $body = $response['body'];
            $workflow_runs = is_object($body)
                ? ($body->workflow_runs ?? [])
                : ($body['workflow_runs'] ?? []);

            $result = [
                'success' => true,
                'data' => $workflow_runs,
            ];

            set_transient($cache_key, $result, 2 * MINUTE_IN_SECONDS);
            return $result;
        }

        return [
            'success' => false,
            'message' => $response['body']['message'] ?? __('Failed to get workflow runs.', 'deploy-forge'),
        ];
    }

    /**
     * Get artifacts for a workflow run
     */
    public function get_workflow_artifacts(int $run_id): array
    {
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/runs/{$run_id}/artifacts";

        $response = $this->request('GET', $endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            // $response['body'] is always an array (parsed with json_decode($body, true))
            $artifacts = $response['body']['artifacts'] ?? [];

            return [
                'success' => true,
                'data' => $artifacts,
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']['message'] ?? __('Failed to get artifacts.', 'deploy-forge'),
        ];
    }

    /**
     * Download artifact via Deploy Forge backend
     *
     * @param int|string $artifact_id
     * @param string $destination
     * @param string|null $artifact_url Optional URL path from webhook (e.g., /api/plugin/github/artifacts/123/download)
     */
    public function download_artifact(int|string $artifact_id, string $destination, ?string $artifact_url = null): bool|WP_Error
    {
        $api_key = $this->settings->get_api_key();
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Not connected to Deploy Forge', 'deploy-forge'));
        }

        $this->logger->log('GitHub_API', "Downloading artifact #$artifact_id to $destination");

        // Use URL from webhook if provided, otherwise build from artifact ID
        $backend_url = $this->settings->get_backend_url();
        if (!empty($artifact_url)) {
            // artifact_url is a path like "/api/plugin/github/artifacts/123/download"
            $download_url = $backend_url . $artifact_url;
        } else {
            $download_url = $backend_url . '/api/plugin/github/artifacts/' . $artifact_id . '/download';
        }

        $this->logger->log('GitHub_API', "Requesting artifact from Deploy Forge backend", [
            'download_url' => $download_url,
            'using_webhook_url' => !empty($artifact_url),
        ]);

        // Download artifact through Deploy Forge proxy
        $download_args = [
            'headers' => [
                'X-API-Key' => $api_key,
            ],
            'timeout' => 300,
            'stream' => true,
            'filename' => $destination,
        ];

        $download_response = wp_remote_get($download_url, $download_args);

        if (is_wp_error($download_response)) {
            $this->logger->error('GitHub_API', "Artifact download failed", $download_response);
            return $download_response;
        }

        $download_status = wp_remote_retrieve_response_code($download_response);

        $this->logger->log('GitHub_API', "Artifact download response", [
            'status_code' => $download_status,
            'file_exists' => file_exists($destination),
            'file_size' => file_exists($destination) ? filesize($destination) : 0,
        ]);

        if ($download_status === 200 && file_exists($destination) && filesize($destination) > 0) {
            $this->logger->log('GitHub_API', "Artifact download successful!");
            return true;
        }

        $this->logger->error('GitHub_API', "Artifact download failed", [
            'status_code' => $download_status,
            'file_exists' => file_exists($destination),
            'file_size' => file_exists($destination) ? filesize($destination) : 0,
        ]);

        return new WP_Error(
            'download_failed',
            sprintf(__('Failed to download artifact. Status: %d', 'deploy-forge'), $download_status)
        );
    }

    /**
     * Download repository as ZIP archive (for direct clone deployment)
     * Uses Deploy Forge clone token endpoint to get temporary credentials
     */
    public function download_repository(string $ref, string $destination): bool|WP_Error
    {
        $api_key = $this->settings->get_api_key();
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Not connected to Deploy Forge', 'deploy-forge'));
        }

        $this->logger->log('GitHub_API', "Downloading repository at ref: $ref to $destination");

        // Get backend URL
        $backend_url = $this->settings->get_backend_url();

        // Request clone token from Deploy Forge
        $clone_token_url = $backend_url . '/api/plugin/github/clone-token';

        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ],
            'timeout' => 30,
        ];

        $this->logger->log('GitHub_API', "Requesting clone token from Deploy Forge");

        $response = wp_remote_post($clone_token_url, $args);

        if (is_wp_error($response)) {
            $this->logger->error('GitHub_API', "Failed to get clone token", $response);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $parsed_body = json_decode($body, true);

        if ($status_code >= 400 || !isset($parsed_body['success']) || !$parsed_body['success']) {
            $error_message = $parsed_body['error'] ?? 'Failed to get clone credentials';
            $this->logger->error('GitHub_API', "Backend error getting clone token", [
                'status' => $status_code,
                'message' => $error_message,
            ]);
            return new WP_Error('backend_error', $error_message);
        }

        $clone_url = $parsed_body['cloneUrl'] ?? null;
        $repo_ref = $parsed_body['ref'] ?? $ref;

        if (empty($clone_url)) {
            $this->logger->error('GitHub_API', "No clone URL in response", $parsed_body);
            return new WP_Error('no_clone_url', __('Could not get repository clone URL', 'deploy-forge'));
        }

        // Extract owner and repo from clone URL
        // Format: https://x-access-token:TOKEN@github.com/owner/repo.git
        if (preg_match('#github\.com/([^/]+)/([^/]+?)(?:\.git)?$#', $clone_url, $matches)) {
            $owner = $matches[1];
            $repo = $matches[2];

            // Use GitHub's zipball endpoint to download specific ref
            // Replace .git suffix and use API endpoint
            $download_url = "https://api.github.com/repos/{$owner}/{$repo}/zipball/{$repo_ref}";

            // Extract token from clone URL
            if (preg_match('#x-access-token:([^@]+)@#', $clone_url, $token_matches)) {
                $token = $token_matches[1];

                $this->logger->log('GitHub_API', "Downloading repository archive from GitHub...");

                // Download using the token
                $download_args = [
                    'headers' => [
                        'Authorization' => 'token ' . $token,
                        'Accept' => 'application/vnd.github+json',
                    ],
                    'timeout' => 300,
                    'stream' => true,
                    'filename' => $destination,
                ];

                $download_response = wp_remote_get($download_url, $download_args);

                if (is_wp_error($download_response)) {
                    $this->logger->error('GitHub_API', "Repository download failed", $download_response);
                    return $download_response;
                }

                $download_status = wp_remote_retrieve_response_code($download_response);

                $this->logger->log('GitHub_API', "Repository download response", [
                    'status_code' => $download_status,
                    'file_exists' => file_exists($destination),
                    'file_size' => file_exists($destination) ? filesize($destination) : 0,
                ]);

                if ($download_status === 200 && file_exists($destination) && filesize($destination) > 0) {
                    $this->logger->log('GitHub_API', "Repository download successful!");
                    return true;
                }

                $this->logger->error('GitHub_API', "Repository download failed", [
                    'status_code' => $download_status,
                    'file_exists' => file_exists($destination),
                    'file_size' => file_exists($destination) ? filesize($destination) : 0,
                ]);

                return new WP_Error(
                    'download_failed',
                    sprintf(__('Failed to download repository. Status: %d', 'deploy-forge'), $download_status)
                );
            }
        }

        return new WP_Error('invalid_clone_url', __('Invalid clone URL format', 'deploy-forge'));
    }

    /**
     * Get recent commits
     */
    public function get_recent_commits(int $limit = 10): array
    {
        $branch = $this->settings->get('github_branch');
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/commits";

        // Use transient cache
        $cache_key = 'deploy_forge_commits_' . md5($endpoint . $branch);
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
            'message' => $response['body']['message'] ?? __('Failed to get commits.', 'deploy-forge'),
        ];
    }

    /**
     * Get commit details
     */
    public function get_commit_details(string $commit_hash): array
    {
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
            'message' => $response['body']['message'] ?? __('Failed to get commit details.', 'deploy-forge'),
        ];
    }

    /**
     * Make a request to GitHub API (proxied through Deploy Forge backend)
     */
    private function request(string $method, string $endpoint, ?array $data = null): array|WP_Error
    {
        $api_key = $this->settings->get_api_key();

        if (empty($api_key)) {
            $error = new WP_Error('no_api_key', __('Not connected to Deploy Forge. Please connect from settings.', 'deploy-forge'));
            $this->logger->error('GitHub_API', 'No API key configured', $error);
            return $error;
        }

        // Get backend URL
        $backend_url = $this->settings->get_backend_url();

        // Prepare proxy request
        $proxy_url = $backend_url . '/api/plugin/github/proxy';

        // For GET requests, append data as query parameters to the endpoint
        // GET/HEAD/OPTIONS requests cannot have a body
        $request_endpoint = $endpoint;
        $request_data = null;

        if ($method === 'GET' && !empty($data)) {
            // Append query parameters to endpoint
            $query_string = http_build_query($data);
            $request_endpoint = $endpoint . (strpos($endpoint, '?') !== false ? '&' : '?') . $query_string;
        } elseif ($method !== 'GET' && !empty($data)) {
            // For POST/PUT/PATCH/DELETE, include data in body
            $request_data = $data;
        }

        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ],
            'body' => wp_json_encode([
                'method' => $method,
                'endpoint' => $request_endpoint,
                'data' => $request_data,
            ]),
            'timeout' => 30,
        ];

        // Log request
        $this->logger->log_api_request($method, $request_endpoint, $data ?: [], ['via_proxy' => true]);

        $response = wp_remote_post($proxy_url, $args);

        if (is_wp_error($response)) {
            $this->logger->log_api_response($endpoint, 0, null, $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $parsed_body = json_decode($body, true);

        // Check for backend errors
        if ($status_code >= 400 || (isset($parsed_body['error']) && $parsed_body['error'])) {
            $error_message = $parsed_body['message'] ?? 'Unknown error from backend';
            $this->logger->log_api_response($endpoint, $status_code, $parsed_body, $error_message);

            return new WP_Error(
                'backend_error',
                $error_message,
                ['status' => $status_code, 'data' => $parsed_body]
            );
        }

        // Extract GitHub API response from backend response
        $github_status = $parsed_body['status'] ?? 200;
        $github_data = $parsed_body['data'] ?? null;
        $github_headers = $parsed_body['headers'] ?? [];

        // Log response
        $this->logger->log_api_response(
            $endpoint,
            $github_status,
            $github_data,
            $github_status >= 400 ? "HTTP $github_status" : null
        );

        // Check rate limiting (if headers are provided)
        if (isset($github_headers['x-ratelimit-remaining'])) {
            $rate_limit_remaining = (int) $github_headers['x-ratelimit-remaining'];
            if ($rate_limit_remaining < 10) {
                $this->logger->log('GitHub_API', "Rate limit warning: $rate_limit_remaining requests remaining");
                do_action('deploy_forge_rate_limit_warning', $rate_limit_remaining);
            }
        }

        return [
            'status' => $github_status,
            'body' => $github_data, // Keep original type - don't force to object
            'headers' => $github_headers,
        ];
    }

    /**
     * Get rate limit information
     */
    public function get_rate_limit(): array
    {
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
            'message' => __('Failed to get rate limit information.', 'deploy-forge'),
        ];
    }

    /**
     * Get user's repositories (for repo selector)
     */
    public function get_user_repositories(int $per_page = 100): array
    {
        $cache_key = 'deploy_forge_user_repos';
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
            $formatted_repos = array_map(function ($repo) {
                return [
                    'id' => $repo['id'] ?? 0,
                    'full_name' => $repo['full_name'] ?? '',
                    'name' => $repo['name'] ?? '',
                    'owner' => $repo['owner']['login'] ?? '',
                    'private' => $repo['private'] ?? false,
                    'default_branch' => $repo['default_branch'] ?? 'main',
                    'updated_at' => $repo['updated_at'] ?? '',
                    'has_workflows' => $repo['has_actions'] ?? false,
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
            'message' => $response['body']['message'] ?? __('Failed to get repositories.', 'deploy-forge'),
        ];
    }

    /**
     * Get repository workflows (for workflow selector)
     */
    public function get_repository_workflows(string $owner, string $repo): array
    {
        $cache_key = 'deploy_forge_workflows_' . md5($owner . $repo);
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
            $workflows = $response['body']['workflows'] ?? [];

            // Format workflows for dropdown
            $formatted_workflows = array_map(function ($workflow) {
                $path_parts = explode('/', $workflow['path'] ?? '');
                $filename = end($path_parts);

                return [
                    'id' => $workflow['id'] ?? 0,
                    'name' => $workflow['name'] ?? '',
                    'path' => $workflow['path'] ?? '',
                    'filename' => $filename,
                    'state' => $workflow['state'] ?? 'unknown',
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
            'message' => $response['body']['message'] ?? __('Failed to get workflows.', 'deploy-forge'),
        ];
    }

    /**
     * Cancel a workflow run
     */
    public function cancel_workflow_run(int $run_id): array
    {
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/runs/{$run_id}/cancel";

        $response = $this->request('POST', $endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        // GitHub returns 202 Accepted for successful cancellation
        if ($response['status'] === 202) {
            return [
                'success' => true,
                'message' => __('Workflow run cancellation requested successfully!', 'deploy-forge'),
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']['message'] ?? __('Failed to cancel workflow run.', 'deploy-forge'),
        ];
    }

    /**
     * Get installation repositories (repos accessible by GitHub App)
     * This fetches only repos that the GitHub App has been granted access to
     */
    public function get_installation_repositories(): array
    {
        $cache_key = 'deploy_forge_installation_repos';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Use the installation/repositories endpoint
        $response = $this->request('GET', '/installation/repositories');

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            $body = $response['body'];

            // Debug: Log what we got from the backend
            error_log('=== INSTALLATION REPOS DEBUG ===');
            error_log('Body type: ' . gettype($body));
            error_log('Body content: ' . print_r($body, true));

            if (is_object($body) && isset($body->repositories)) {
                error_log('Found repositories in object, count: ' . count($body->repositories));
                if (!empty($body->repositories)) {
                    error_log('First repo: ' . print_r($body->repositories[0], true));
                }
            } else if (is_array($body) && isset($body['repositories'])) {
                error_log('Found repositories in array, count: ' . count($body['repositories']));
                if (!empty($body['repositories'])) {
                    error_log('First repo: ' . print_r($body['repositories'][0], true));
                }
            } else {
                error_log('No repositories found in expected structure');
                error_log('Available keys: ' . print_r(is_object($body) ? get_object_vars($body) : array_keys($body), true));
            }
            error_log('=== END DEBUG ===');

            // Handle both object and array responses
            $repos = is_object($body) && isset($body->repositories)
                ? $body->repositories
                : (is_array($body) && isset($body['repositories']) ? $body['repositories'] : []);

            // Format repos for dropdown
            $formatted_repos = array_map(function ($repo) {
                // Handle both object and array
                $repo_data = is_object($repo) ? (array) $repo : $repo;
                $owner = is_object($repo->owner ?? null) ? (array) $repo->owner : ($repo_data['owner'] ?? []);

                $formatted = [
                    'id' => $repo_data['id'] ?? 0,
                    'full_name' => $repo_data['full_name'] ?? '',
                    'name' => $repo_data['name'] ?? '',
                    'owner' => is_array($owner) ? ($owner['login'] ?? '') : ($owner->login ?? ''),
                    'private' => $repo_data['private'] ?? false,
                    'default_branch' => $repo_data['default_branch'] ?? 'main',
                    'updated_at' => $repo_data['updated_at'] ?? '',
                ];

                // Log first repo for debugging
                static $logged = false;
                if (!$logged) {
                    $this->logger->log('GitHub_API', 'Sample formatted repo', [
                        'raw_repo' => $repo_data,
                        'formatted' => $formatted
                    ]);
                    $logged = true;
                }

                return $formatted;
            }, $repos);

            $result = [
                'success' => true,
                'data' => $formatted_repos,
            ];

            // Cache for 5 minutes
            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
            return $result;
        }

        return [
            'success' => false,
            'message' => $response['body']['message'] ?? __('Failed to get installation repositories.', 'deploy-forge'),
        ];
    }

    /**
     * Get branches for current repository
     *
     * @return array Success status and branch list
     */
    public function get_branches(): array
    {
        $repo_owner = $this->settings->get('github_repo_owner');
        $repo_name = $this->settings->get('github_repo_name');

        if (empty($repo_owner) || empty($repo_name)) {
            return [
                'success' => false,
                'message' => __('Repository not configured', 'deploy-forge'),
            ];
        }

        $this->logger->log('GitHub_API', 'Fetching branches for ' . $repo_owner . '/' . $repo_name);

        // Cache key based on repo
        $cache_key = 'deploy_forge_branches_' . md5($repo_owner . '/' . $repo_name);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $this->logger->log('GitHub_API', 'Returning cached branches');
            return $cached;
        }

        // Get branches from GitHub API
        $endpoint = '/repos/' . rawurlencode($repo_owner) . '/' . rawurlencode($repo_name) . '/branches';
        $response = $this->request('GET', $endpoint);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ($response['status'] === 200) {
            $branches = $response['body'];

            if (is_array($branches)) {
                // Extract just branch names
                $branch_names = array_map(function ($branch) {
                    return is_object($branch) ? $branch->name : $branch['name'];
                }, $branches);

                $result = [
                    'success' => true,
                    'data' => $branch_names,
                ];

                $this->logger->log('GitHub_API', 'Successfully fetched ' . count($branch_names) . ' branches');

                // Cache for 5 minutes
                set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
                return $result;
            }
        }

        return [
            'success' => false,
            'message' => $response['body']['message'] ?? __('Failed to get branches.', 'deploy-forge'),
        ];
    }
}
