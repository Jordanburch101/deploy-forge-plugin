<?php
/**
 * GitHub webhook handler class
 * Handles incoming webhooks from GitHub with signature validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class GitHub_Webhook_Handler {

    private GitHub_Deploy_Settings $settings;
    private GitHub_API $github_api;
    private GitHub_Deploy_Debug_Logger $logger;

    public function __construct(GitHub_Deploy_Settings $settings, GitHub_API $github_api, GitHub_Deploy_Debug_Logger $logger) {
        $this->settings = $settings;
        $this->github_api = $github_api;
        $this->logger = $logger;
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void {
        register_rest_route('github-deploy/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // We validate via signature
        ]);
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
        // Get request data
        $payload = $request->get_body();
        $signature = $request->get_header('x-hub-signature-256');
        $event = $request->get_header('x-github-event');

        // Log webhook receipt
        $this->log_webhook_receipt($event);

        // Verify webhook signature
        if (!$this->verify_signature($payload, $signature)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid webhook signature.', 'github-auto-deploy'),
            ], 401);
        }

        // Parse payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid JSON payload.', 'github-auto-deploy'),
            ], 400);
        }

        // Handle different event types
        switch ($event) {
            case 'push':
                return $this->handle_push_event($data);

            case 'workflow_run':
                return $this->handle_workflow_run_event($data);

            case 'ping':
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('Webhook ping received successfully!', 'github-auto-deploy'),
                ], 200);

            default:
                return new WP_REST_Response([
                    'success' => false,
                    'message' => sprintf(__('Unsupported event type: %s', 'github-auto-deploy'), $event),
                ], 400);
        }
    }

    /**
     * Handle push event
     */
    private function handle_push_event(array $data): WP_REST_Response {
        // Check if auto-deploy is enabled
        if (!$this->settings->get('auto_deploy_enabled')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Auto-deploy is disabled.', 'github-auto-deploy'),
            ], 200);
        }

        // Extract branch from ref (refs/heads/main -> main)
        $ref = $data['ref'] ?? '';
        $branch = str_replace('refs/heads/', '', $ref);
        $configured_branch = $this->settings->get('github_branch');

        // Check if this is the configured branch
        if ($branch !== $configured_branch) {
            return new WP_REST_Response([
                'success' => false,
                'message' => sprintf(
                    __('Ignoring push to branch %s (configured: %s)', 'github-auto-deploy'),
                    $branch,
                    $configured_branch
                ),
            ], 200);
        }

        // Extract commit information
        $head_commit = $data['head_commit'] ?? [];
        $commit_hash = $head_commit['id'] ?? '';
        $commit_message = $head_commit['message'] ?? '';
        $commit_author = $head_commit['author']['name'] ?? '';
        $commit_date = $head_commit['timestamp'] ?? current_time('mysql');

        if (empty($commit_hash)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('No commit hash found in payload.', 'github-auto-deploy'),
            ], 400);
        }

        // Check if manual approval is required
        if ($this->settings->get('require_manual_approval')) {
            // Create pending deployment for manual approval
            $deployment_id = $this->create_pending_deployment($commit_hash, $commit_message, $commit_author, $commit_date);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Deployment pending manual approval.', 'github-auto-deploy'),
                'deployment_id' => $deployment_id,
            ], 200);
        }

        // Trigger automatic deployment
        $deployment_manager = github_auto_deploy()->get_deployment_manager();
        $deployment_id = $deployment_manager->start_deployment($commit_hash, 'webhook', 0, [
            'commit_message' => $commit_message,
            'commit_author' => $commit_author,
            'commit_date' => $commit_date,
        ]);

        if (!$deployment_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Failed to start deployment.', 'github-auto-deploy'),
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Deployment started successfully.', 'github-auto-deploy'),
            'deployment_id' => $deployment_id,
        ], 200);
    }

    /**
     * Handle workflow_run event
     */
    private function handle_workflow_run_event(array $data): WP_REST_Response {
        $action = $data['action'] ?? '';
        $workflow_run = $data['workflow_run'] ?? [];
        $run_id = $workflow_run['id'] ?? 0;
        $status = $workflow_run['status'] ?? '';
        $conclusion = $workflow_run['conclusion'] ?? '';

        // We're interested in completed workflows
        if ($action !== 'completed') {
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Workflow not completed yet.', 'github-auto-deploy'),
            ], 200);
        }

        // Find deployment by workflow run ID
        $deployment_manager = github_auto_deploy()->get_deployment_manager();
        $database = github_auto_deploy()->get_database();

        global $wpdb;
        $table_name = $wpdb->prefix . 'github_deployments';
        $deployment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE workflow_run_id = %d ORDER BY id DESC LIMIT 1", $run_id)
        );

        if (!$deployment) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('No deployment found for this workflow run.', 'github-auto-deploy'),
            ], 404);
        }

        // Update deployment status based on workflow conclusion
        if ($conclusion === 'success') {
            // Workflow succeeded, continue with deployment
            $deployment_manager->process_successful_build($deployment->id);
        } else {
            // Workflow failed
            $database->update_deployment($deployment->id, [
                'status' => 'failed',
                'error_message' => sprintf(__('Workflow failed with conclusion: %s', 'github-auto-deploy'), $conclusion),
            ]);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Workflow run processed.', 'github-auto-deploy'),
        ], 200);
    }

    /**
     * Verify webhook signature
     */
    private function verify_signature(string $payload, ?string $signature): bool {
        if (empty($signature)) {
            return false;
        }

        $secret = $this->settings->get('webhook_secret');

        if (empty($secret)) {
            // If no secret is configured, log warning but allow (for initial setup)
            error_log('GitHub Deploy: Webhook secret not configured. This is insecure!');
            return true;
        }

        // GitHub sends signature as sha256=<hash>
        $hash_algo = 'sha256';
        if (strpos($signature, '=') !== false) {
            list($algo, $hash) = explode('=', $signature, 2);
            $hash_algo = $algo;
        } else {
            $hash = $signature;
        }

        $expected_signature = hash_hmac($hash_algo, $payload, $secret);

        return hash_equals($expected_signature, $hash);
    }

    /**
     * Create pending deployment for manual approval
     */
    private function create_pending_deployment(string $commit_hash, string $commit_message, string $commit_author, string $commit_date): int|false {
        $database = github_auto_deploy()->get_database();

        return $database->insert_deployment([
            'commit_hash' => $commit_hash,
            'commit_message' => $commit_message,
            'commit_author' => $commit_author,
            'commit_date' => $commit_date,
            'status' => 'pending',
            'trigger_type' => 'webhook',
            'triggered_by_user_id' => 0,
        ]);
    }

    /**
     * Log webhook receipt
     */
    private function log_webhook_receipt(string $event): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'GitHub Deploy: Webhook received - Event: %s, Time: %s',
                $event,
                current_time('mysql')
            ));
        }

        do_action('github_deploy_webhook_received', $event);
    }
}
