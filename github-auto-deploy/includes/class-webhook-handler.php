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
        
        // Debug endpoint to test webhook reception
        register_rest_route('github-deploy/v1', '/webhook-test', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'test_webhook_reception'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Test webhook reception (debug endpoint)
     */
    public function test_webhook_reception(WP_REST_Request $request): WP_REST_Response {
        $methods = [
            'get_body' => $request->get_body(),
            'get_json_params' => $request->get_json_params(),
            'get_body_params' => $request->get_body_params(),
            'get_params' => $request->get_params(),
        ];
        
        $diagnostics = [
            'request_method' => $request->get_method(),
            'content_type' => $request->get_header('content-type'),
            'headers' => $request->get_headers(),
            'php_input' => file_get_contents('php://input'),
        ];
        
        foreach ($methods as $method => $data) {
            $diagnostics[$method] = [
                'is_empty' => empty($data),
                'length' => is_string($data) ? strlen($data) : (is_array($data) ? count($data) : 0),
                'preview' => is_string($data) ? substr($data, 0, 200) : $data,
            ];
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Debug info',
            'diagnostics' => $diagnostics,
        ], 200);
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response {
        $signature = $request->get_header('x-hub-signature-256');
        $event = $request->get_header('x-github-event');
        $content_type = $request->get_header('content-type');
        
        // Get request data - try multiple methods to get the payload
        // Try php://input first (most reliable with nginx)
        $raw_payload = file_get_contents('php://input');
        
        // If empty, try WordPress methods
        if (empty($raw_payload)) {
            $raw_payload = $request->get_body();
        }
        
        // GitHub can send webhooks in two formats:
        // 1. application/json - raw JSON in body
        // 2. application/x-www-form-urlencoded - JSON in "payload" form field
        
        $payload = '';
        
        if (strpos($content_type, 'application/x-www-form-urlencoded') !== false) {
            // Form-encoded: Extract and decode the "payload" field
            parse_str($raw_payload, $form_data);
            if (isset($form_data['payload'])) {
                $payload = $form_data['payload'];
            } else {
                $payload = $raw_payload;
            }
        } else {
            // JSON format - use as-is
            $payload = $raw_payload;
        }
        
        // Fallback: If still empty, try WordPress parsed methods
        if (empty($payload)) {
            $data = $request->get_json_params();
            if (!empty($data)) {
                $payload = wp_json_encode($data);
            }
        }
        
        if (empty($payload)) {
            $data = $request->get_body_params();
            if (!empty($data)) {
                $payload = wp_json_encode($data);
            }
        }

        // Log webhook receipt with payload info
        $this->logger->log('Webhook', 'Webhook received', [
            'event' => $event,
            'payload_length' => strlen($payload),
            'has_signature' => !empty($signature),
            'content_type' => $request->get_header('content-type'),
        ]);

        // Log webhook receipt
        $this->log_webhook_receipt($event);

        // Verify webhook signature (skip if payload is empty - likely a test)
        // For form-encoded webhooks, GitHub signs the raw form data, not the extracted JSON
        $signature_payload = (strpos($content_type, 'application/x-www-form-urlencoded') !== false) ? $raw_payload : $payload;

        // Check if webhook secret is configured
        $webhook_secret = $this->settings->get('webhook_secret');

        // Only validate signature if a secret is configured
        if (!empty($webhook_secret) && !empty($payload) && !$this->verify_signature($signature_payload, $signature)) {
            $this->logger->error('Webhook', 'Invalid webhook signature', [
                'content_type' => $content_type,
                'payload_length' => strlen($payload),
                'signature_payload_length' => strlen($signature_payload),
            ]);
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid webhook signature.', 'github-auto-deploy'),
            ], 401);
        }

        // If no secret configured, log warning (insecure but allows GitHub App webhooks)
        if (empty($webhook_secret)) {
            $this->logger->log('Webhook', 'Webhook accepted without signature validation (no secret configured)');
        }

        // Parse payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
            $this->logger->error('Webhook', 'JSON decode error', [
                'error' => json_last_error_msg(),
                'payload_length' => strlen($payload),
                'payload_preview' => substr($payload, 0, 500),
                'payload_is_empty' => empty($payload),
            ]);
            
            // If payload is truly empty, return a more helpful error
            if (empty($payload)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Empty payload received. Check webhook Content-Type header.', 'github-auto-deploy'),
                ], 400);
            }
            
            return new WP_REST_Response([
                'success' => false,
                'message' => sprintf(__('Invalid JSON payload: %s', 'github-auto-deploy'), json_last_error_msg()),
            ], 400);
        }

        $this->logger->log('Webhook', 'Payload parsed successfully', [
            'event' => $event,
            'has_action' => isset($data['action']),
            'data_keys' => array_keys($data),
        ]);

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
     * Called when a GitHub Actions workflow completes
     */
    private function handle_workflow_run_event(array $data): WP_REST_Response {
        $action = $data['action'] ?? '';
        $workflow_run = $data['workflow_run'] ?? [];
        $run_id = $workflow_run['id'] ?? 0;
        $status = $workflow_run['status'] ?? '';
        $conclusion = $workflow_run['conclusion'] ?? '';
        $head_sha = $workflow_run['head_sha'] ?? '';
        $html_url = $workflow_run['html_url'] ?? '';

        $this->logger->log('Webhook', 'Workflow run event received', [
            'action' => $action,
            'run_id' => $run_id,
            'status' => $status,
            'conclusion' => $conclusion,
            'head_sha' => $head_sha,
        ]);

        // We're only interested in completed workflows
        if ($action !== 'completed') {
            $this->logger->log('Webhook', "Ignoring workflow_run action: {$action}");
            return new WP_REST_Response([
                'success' => true,
                'message' => sprintf(__('Workflow run action "%s" ignored (waiting for completion).', 'github-auto-deploy'), $action),
            ], 200);
        }

        if (empty($run_id)) {
            $this->logger->error('Webhook', 'No workflow run ID in payload');
            return new WP_REST_Response([
                'success' => false,
                'message' => __('No workflow run ID found in payload.', 'github-auto-deploy'),
            ], 400);
        }

        // Find deployment by workflow run ID
        $deployment_manager = github_auto_deploy()->get_deployment_manager();
        $database = github_auto_deploy()->get_database();

        global $wpdb;
        $table_name = $wpdb->prefix . 'github_deployments';
        
        // First try to find by workflow_run_id
        $deployment = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE workflow_run_id = %d ORDER BY id DESC LIMIT 1", $run_id)
        );

        // If not found by run_id, try to find by commit hash (for deployments where run_id wasn't set yet)
        if (!$deployment && !empty($head_sha)) {
            $this->logger->log('Webhook', "Workflow run ID not found, trying commit hash: {$head_sha}");
            $deployment = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE commit_hash = %s AND status IN ('pending', 'building') ORDER BY id DESC LIMIT 1",
                    $head_sha
                )
            );
            
            // Update the deployment with the workflow run ID
            if ($deployment) {
                $database->update_deployment($deployment->id, [
                    'workflow_run_id' => $run_id,
                    'build_url' => $html_url,
                ]);
                $this->logger->log('Webhook', "Updated deployment #{$deployment->id} with workflow_run_id: {$run_id}");
            }
        }

        if (!$deployment) {
            $this->logger->error('Webhook', 'No deployment found for workflow run', [
                'run_id' => $run_id,
                'head_sha' => $head_sha,
            ]);
            return new WP_REST_Response([
                'success' => false,
                'message' => __('No deployment found for this workflow run.', 'github-auto-deploy'),
            ], 404);
        }

        $this->logger->log('Webhook', "Found deployment #{$deployment->id} for workflow run #{$run_id}");

        // Update deployment status based on workflow conclusion
        if ($conclusion === 'success') {
            $this->logger->log('Webhook', "Workflow completed successfully, processing build for deployment #{$deployment->id}");
            
            // Workflow succeeded, continue with deployment
            $deployment_manager->process_successful_build($deployment->id);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Workflow completed successfully. Starting deployment...', 'github-auto-deploy'),
                'deployment_id' => $deployment->id,
            ], 200);
        } else {
            // Workflow failed or was cancelled
            $this->logger->error('Webhook', "Workflow failed for deployment #{$deployment->id}", [
                'conclusion' => $conclusion,
                'status' => $status,
            ]);
            
            $database->update_deployment($deployment->id, [
                'status' => 'failed',
                'error_message' => sprintf(__('GitHub Actions workflow failed with conclusion: %s', 'github-auto-deploy'), $conclusion),
            ]);
            
            return new WP_REST_Response([
                'success' => true,
                'message' => sprintf(__('Workflow failed with conclusion: %s', 'github-auto-deploy'), $conclusion),
                'deployment_id' => $deployment->id,
            ], 200);
        }
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
