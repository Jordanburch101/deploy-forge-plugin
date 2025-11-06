<?php

/**
 * GitHub API wrapper class
 * Handles all GitHub API v3 interactions using wp_remote_request()
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_API
{

    private GitHub_Deploy_Settings $settings;
    private GitHub_Deploy_Debug_Logger $logger;
    private const API_BASE = 'https://api.github.com';
    private const USER_AGENT = 'WordPress-GitHub-Deploy/1.0';

    public function __construct(GitHub_Deploy_Settings $settings, GitHub_Deploy_Debug_Logger $logger)
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
            'message' => $response['body']->message ?? __('Failed to get workflow run status.', 'github-auto-deploy'),
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
            'message' => $response['body']->message ?? __('Failed to get workflow runs.', 'github-auto-deploy'),
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
            // $response['body'] can be array or object depending on backend response
            $artifacts = is_array($response['body'])
                ? ($response['body']['artifacts'] ?? [])
                : ($response['body']->artifacts ?? []);

            return [
                'success' => true,
                'data' => $artifacts,
            ];
        }

        $error_message = is_array($response['body'])
            ? ($response['body']['message'] ?? __('Failed to get artifacts.', 'github-auto-deploy'))
            : ($response['body']->message ?? __('Failed to get artifacts.', 'github-auto-deploy'));

        return [
            'success' => false,
            'message' => $error_message,
        ];
    }

    /**
     * Download artifact
     * Note: Artifact downloads still use direct GitHub API because they return binary data
     */
    public function download_artifact(int $artifact_id, string $destination): bool|WP_Error
    {
        $endpoint = "/repos/{$this->settings->get_repo_full_name()}/actions/artifacts/{$artifact_id}/zip";

        $api_key = $this->settings->get_api_key();
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Not connected to GitHub', 'github-auto-deploy'));
        }

        $this->logger->log('GitHub_API', "Downloading artifact #$artifact_id to $destination");

        // For artifact downloads, we need to get the redirect URL through the proxy first
        // Then download directly from the Azure/S3 URL
        $redirect_result = $this->request('GET', $endpoint);

        if (is_wp_error($redirect_result)) {
            $this->logger->error('GitHub_API', "Failed to get artifact download URL", $redirect_result);
            return $redirect_result;
        }

        // The backend should handle the redirect and return the actual download URL
        // For now, we'll use a workaround: make a direct request with installation token
        // This requires the backend to expose a download endpoint or we handle it differently

        // Use backend proxy to get installation token for direct download
        $backend_url = defined('GITHUB_DEPLOY_BACKEND_URL')
            ? constant('GITHUB_DEPLOY_BACKEND_URL')
            : 'https://deploy-forge.vercel.app';

        $token_url = $backend_url . '/api/github/token';

        $token_response = wp_remote_post($token_url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($token_response)) {
            $this->logger->error('GitHub_API', "Failed to get installation token", $token_response);
            return $token_response;
        }

        $token_response_code = wp_remote_retrieve_response_code($token_response);
        $token_response_body = wp_remote_retrieve_body($token_response);
        $token_body = json_decode($token_response_body, true);

        $this->logger->log('GitHub_API', "Token endpoint response", [
            'status' => $token_response_code,
            'body_length' => strlen($token_response_body),
            'has_token' => isset($token_body['token']),
            'response_keys' => $token_body ? array_keys($token_body) : [],
        ]);

        if (!isset($token_body['token'])) {
            $this->logger->error('GitHub_API', "No token in response", [
                'status' => $token_response_code,
                'body' => $token_body
            ]);
            return new WP_Error('no_token', __('Could not get GitHub installation token', 'github-auto-deploy'));
        }

        $installation_token = $token_body['token'];

        $this->logger->log('GitHub_API', "Got installation token, requesting artifact download URL");

        // Make direct GitHub API request to get redirect URL (don't follow redirect)
        $github_api_url = 'https://api.github.com' . $endpoint;

        $redirect_response = wp_remote_get($github_api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $installation_token,
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'WordPress-GitHub-Deploy',
            ],
            'timeout' => 30,
            'redirection' => 0, // Don't follow redirects - we want the Location header
        ]);

        if (is_wp_error($redirect_response)) {
            $this->logger->error('GitHub_API', "Failed to get artifact redirect", $redirect_response);
            return $redirect_response;
        }

        // GitHub returns 302 redirect with Location header pointing to Azure blob storage
        $location = wp_remote_retrieve_header($redirect_response, 'location');

        if (empty($location)) {
            $status = wp_remote_retrieve_response_code($redirect_response);
            $this->logger->error('GitHub_API', "No redirect location in response", [
                'status' => $status,
                'headers' => wp_remote_retrieve_headers($redirect_response)->getAll()
            ]);
            return new WP_Error('no_redirect', __('Could not get artifact download URL', 'github-auto-deploy'));
        }

        $this->logger->log('GitHub_API', "Got download URL, downloading...", [
            'url_length' => strlen($location),
            'is_azure' => strpos($location, 'blob.core.windows.net') !== false,
        ]);

        // Download from the pre-signed URL (no auth needed)
        $download_args = [
            'timeout' => 300,
            'stream' => true,
            'filename' => $destination,
        ];

        $download_response = wp_remote_get($location, $download_args);

        if (is_wp_error($download_response)) {
            $this->logger->error('GitHub_API', "Download failed", $download_response);
            return $download_response;
        }

        $download_status = wp_remote_retrieve_response_code($download_response);

        $this->logger->log('GitHub_API', "Download response", [
            'status_code' => $download_status,
            'file_exists' => file_exists($destination),
            'file_size' => file_exists($destination) ? filesize($destination) : 0,
        ]);

        if ($download_status === 200 && file_exists($destination) && filesize($destination) > 0) {
            $this->logger->log('GitHub_API', "Artifact download successful!");
            return true;
        }

        $this->logger->error('GitHub_API', "Download failed", [
            'status_code' => $download_status,
            'file_exists' => file_exists($destination),
            'file_size' => file_exists($destination) ? filesize($destination) : 0,
        ]);

        return new WP_Error(
            'download_failed',
            sprintf(__('Failed to download artifact. Status: %d', 'github-auto-deploy'), $download_status)
        );
    }

    /**
     * Get recent commits
     */
    public function get_recent_commits(int $limit = 10): array
    {
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
            'message' => $response['body']->message ?? __('Failed to get commit details.', 'github-auto-deploy'),
        ];
    }

    /**
     * Make a request to GitHub API (proxied through backend)
     */
    private function request(string $method, string $endpoint, ?array $data = null): array|WP_Error
    {
        $api_key = $this->settings->get_api_key();

        if (empty($api_key)) {
            $error = new WP_Error('no_api_key', __('Not connected to GitHub. Please connect from settings.', 'github-auto-deploy'));
            $this->logger->error('GitHub_API', 'No API key configured', $error);
            return $error;
        }

        // Get backend URL from constant or use default
        $backend_url = defined('GITHUB_DEPLOY_BACKEND_URL')
            ? constant('GITHUB_DEPLOY_BACKEND_URL')
            : 'https://deploy-forge.vercel.app';

        // Prepare proxy request
        $proxy_url = $backend_url . '/api/github/proxy';

        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key,
            ],
            'body' => wp_json_encode([
                'method' => $method,
                'endpoint' => $endpoint,
                'data' => $data,
            ]),
            'timeout' => 30,
        ];

        // Log request
        $this->logger->log_api_request($method, $endpoint, $data ?: [], ['via_proxy' => true]);

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
                do_action('github_deploy_rate_limit_warning', $rate_limit_remaining);
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
            'message' => __('Failed to get rate limit information.', 'github-auto-deploy'),
        ];
    }

    /**
     * Get user's repositories (for repo selector)
     */
    public function get_user_repositories(int $per_page = 100): array
    {
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
            $formatted_repos = array_map(function ($repo) {
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
    public function get_repository_workflows(string $owner, string $repo): array
    {
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
            $formatted_workflows = array_map(function ($workflow) {
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
                'message' => __('Workflow run cancellation requested successfully!', 'github-auto-deploy'),
            ];
        }

        return [
            'success' => false,
            'message' => $response['body']->message ?? __('Failed to cancel workflow run.', 'github-auto-deploy'),
        ];
    }

    /**
     * Get installation repositories (repos accessible by GitHub App)
     * This fetches only repos that the GitHub App has been granted access to
     */
    public function get_installation_repositories(): array
    {
        $cache_key = 'github_deploy_installation_repos';
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
            'message' => $response['body']->message ?? __('Failed to get installation repositories.', 'github-auto-deploy'),
        ];
    }
}
