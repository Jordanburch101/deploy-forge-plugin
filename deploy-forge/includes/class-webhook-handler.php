<?php

/**
 * GitHub webhook handler class
 * Handles incoming webhooks from GitHub with signature validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Deploy_Forge_Webhook_Handler
{

    private Deploy_Forge_Settings $settings;
    private Deploy_Forge_GitHub_API $github_api;
    private Deploy_Forge_Debug_Logger $logger;
    private Deploy_Forge_Deployment_Manager $deployment_manager;

    public function __construct(Deploy_Forge_Settings $settings, Deploy_Forge_GitHub_API $github_api, Deploy_Forge_Debug_Logger $logger, Deploy_Forge_Deployment_Manager $deployment_manager)
    {
        $this->settings = $settings;
        $this->github_api = $github_api;
        $this->logger = $logger;
        $this->deployment_manager = $deployment_manager;
    }

    /**
     * Register REST API routes
     */
    public function register_routes(): void
    {
        register_rest_route('deploy-forge/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true', // We validate via signature
        ]);

        // Diagnostic endpoint to check webhook secret status
        register_rest_route('deploy-forge/v1', '/webhook-status', [
            'methods' => 'GET',
            'callback' => [$this, 'check_webhook_status'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Diagnostic endpoint to check if webhook secret is configured
     */
    public function check_webhook_status(): WP_REST_Response
    {
        $webhook_secret = $this->settings->get_webhook_secret();

        return new WP_REST_Response([
            'configured' => !empty($webhook_secret),
            'length' => strlen($webhook_secret ?? ''),
            'preview' => !empty($webhook_secret) ? substr($webhook_secret, 0, 8) . '...' : 'EMPTY',
        ]);
    }

    /**
     * Handle incoming webhook
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response
    {
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

        // Get webhook secret - ALWAYS required for security
        $webhook_secret = $this->settings->get_webhook_secret();

        // Check for Deploy Forge forwarded webhook header
        $is_forwarded = $request->get_header('x-deploy-forge-forwarded') === 'true';

        // DEBUG: Log webhook info
        $this->logger->log('Webhook', 'Webhook secret and forwarding info', [
            'has_webhook_secret' => !empty($webhook_secret),
            'secret_length' => strlen($webhook_secret ?? ''),
            'is_forwarded' => $is_forwarded,
        ]);

        // ALWAYS require webhook secret - no exceptions
        if (empty($webhook_secret)) {
            $this->logger->error('Webhook', 'Webhook secret not configured - rejecting request');
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Webhook secret must be configured. Please configure webhook secret in plugin settings.', 'deploy-forge'),
            ], 401);
        }

        // Require non-empty payload
        if (empty($payload)) {
            $this->logger->error('Webhook', 'Empty payload received');
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Empty payload received.', 'deploy-forge'),
            ], 400);
        }

        // ALWAYS validate signature - no exceptions
        if (!$this->verify_signature($signature_payload, $signature)) {
            $this->logger->error('Webhook', 'Invalid webhook signature', [
                'content_type' => $content_type,
                'payload_length' => strlen($payload),
                'signature_payload_length' => strlen($signature_payload),
                'has_signature' => !empty($signature),
            ]);
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Invalid webhook signature.', 'deploy-forge'),
            ], 401);
        }

        // Signature validated successfully
        $this->logger->log('Webhook', 'Webhook signature validated successfully');

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
                    'message' => __('Empty payload received. Check webhook Content-Type header.', 'deploy-forge'),
                ], 400);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => sprintf(__('Invalid JSON payload: %s', 'deploy-forge'), json_last_error_msg()),
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
                    'message' => __('Webhook ping received successfully!', 'deploy-forge'),
                ], 200);

            default:
                return new WP_REST_Response([
                    'success' => false,
                    'message' => sprintf(__('Unsupported event type: %s', 'deploy-forge'), $event),
                ], 400);
        }
    }

    /**
     * Handle push event
     */
    private function handle_push_event(array $data): WP_REST_Response
    {
        // Check if auto-deploy is enabled
        if (!$this->settings->get('auto_deploy_enabled')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Auto-deploy is disabled.', 'deploy-forge'),
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
                    __('Ignoring push to branch %s (configured: %s)', 'deploy-forge'),
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
                'message' => __('No commit hash found in payload.', 'deploy-forge'),
            ], 400);
        }

        // Check if manual approval is required
        if ($this->settings->get('require_manual_approval')) {
            // Create pending deployment for manual approval
            $deployment_id = $this->create_pending_deployment($commit_hash, $commit_message, $commit_author, $commit_date);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Deployment pending manual approval.', 'deploy-forge'),
                'deployment_id' => $deployment_id,
            ], 200);
        }

        // Trigger automatic deployment
        $deployment_id = $this->deployment_manager->start_deployment($commit_hash, 'webhook', 0, [
            'commit_message' => $commit_message,
            'commit_author' => $commit_author,
            'commit_date' => $commit_date,
        ]);

        if (!$deployment_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Failed to start deployment.', 'deploy-forge'),
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Deployment started successfully.', 'deploy-forge'),
            'deployment_id' => $deployment_id,
        ], 200);
    }

    /**
     * Handle workflow_run event
     * Called when a GitHub Actions workflow completes
     */
    private function handle_workflow_run_event(array $data): WP_REST_Response
    {
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
                'message' => sprintf(__('Workflow run action "%s" ignored (waiting for completion).', 'deploy-forge'), $action),
            ], 200);
        }

        if (empty($run_id)) {
            $this->logger->error('Webhook', 'No workflow run ID in payload');
            return new WP_REST_Response([
                'success' => false,
                'message' => __('No workflow run ID found in payload.', 'deploy-forge'),
            ], 400);
        }

        // Find deployment by workflow run ID
        $deployment_manager = $this->deployment_manager;
        $database = deploy_forge()->database;

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
                'message' => __('No deployment found for this workflow run.', 'deploy-forge'),
            ], 404);
        }

        $this->logger->log('Webhook', "Found deployment #{$deployment->id} for workflow run #{$run_id}");

        // Update deployment status based on workflow conclusion
        if ($conclusion === 'success') {
            $this->logger->log('Webhook', "Workflow completed successfully, queueing deployment #{$deployment->id}");

            // Update deployment status to queued
            $database->update_deployment($deployment->id, [
                'status' => 'queued',
                'deployment_logs' => 'Workflow completed successfully. Deployment queued for processing...',
            ]);

            // Send immediate HTTP 200 response to GitHub
            $response_data = [
                'success' => true,
                'message' => __('Workflow completed successfully. Deployment queued.', 'deploy-forge'),
                'deployment_id' => $deployment->id,
            ];

            // Try to send early response and process async
            $this->send_early_response_and_process_async($deployment->id, $response_data);

            // This return is a fallback if send_early_response_and_process_async doesn't terminate
            return new WP_REST_Response($response_data, 200);
        } else {
            // Workflow failed or was cancelled
            $this->logger->error('Webhook', "Workflow failed for deployment #{$deployment->id}", [
                'conclusion' => $conclusion,
                'status' => $status,
            ]);

            $database->update_deployment($deployment->id, [
                'status' => 'failed',
                'error_message' => sprintf(__('GitHub Actions workflow failed with conclusion: %s', 'deploy-forge'), $conclusion),
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => sprintf(__('Workflow failed with conclusion: %s', 'deploy-forge'), $conclusion),
                'deployment_id' => $deployment->id,
            ], 200);
        }
    }

    /**
     * Verify webhook signature
     * Note: Secret existence is validated before calling this method
     * Signature verification works the same for both direct GitHub webhooks
     * and Deploy Forge forwarded webhooks
     */
    private function verify_signature(string $payload, ?string $signature): bool
    {
        if (empty($signature)) {
            return false;
        }

        $secret = $this->settings->get_webhook_secret();

        // Secret should always exist at this point (validated in handle_webhook)
        // But double-check for safety
        if (empty($secret)) {
            return false;
        }

        // GitHub/Deploy Forge sends signature as sha256=<hash>
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
    private function create_pending_deployment(string $commit_hash, string $commit_message, string $commit_author, string $commit_date): int|false
    {
        $database = deploy_forge()->database;

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
    private function log_webhook_receipt(string $event): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'GitHub Deploy: Webhook received - Event: %s, Time: %s',
                $event,
                current_time('mysql')
            ));
        }

        do_action('deploy_forge_webhook_received', $event);
    }

    /**
     * Send early HTTP response and process deployment asynchronously
     * This prevents GitHub webhook timeouts by responding immediately
     *
     * Uses hybrid approach:
     * 1. Try fastcgi_finish_request() if available (PHP-FPM)
     * 2. Fallback to WP-Cron for background processing
     */
    private function send_early_response_and_process_async(int $deployment_id, array $response_data): void
    {
        $this->logger->log('Webhook', "Attempting async processing for deployment #{$deployment_id}");

        // Check if we can use fastcgi for immediate async processing
        if (function_exists('fastcgi_finish_request')) {
            $this->logger->log('Webhook', 'Using fastcgi_finish_request for async processing');

            // Send the HTTP response immediately
            status_header(200);
            header('Content-Type: application/json');
            echo wp_json_encode($response_data);

            // Flush output buffers
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();

            // Close connection to client (FastCGI-specific)
            fastcgi_finish_request();

            // Now we can do heavy work without keeping GitHub waiting
            // Allow script to run for up to 5 minutes
            @set_time_limit(300);
            @ignore_user_abort(true);

            // Process the deployment
            $this->logger->log('Webhook', "Processing deployment #{$deployment_id} after early response");
            $this->deployment_manager->process_successful_build($deployment_id);

            // Terminate script
            exit;
        } else {
            // Fallback to WP-Cron
            $this->logger->log('Webhook', 'fastcgi_finish_request not available, using WP-Cron fallback');

            // Schedule the deployment to be processed by WP-Cron
            wp_schedule_single_event(time(), 'deploy_forge_process_queued_deployment', [$deployment_id]);

            // Response will be sent normally by WordPress REST API
            // (The calling function will return WP_REST_Response)
        }
    }
}
