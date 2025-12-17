<?php
/**
 * Linear Integration Manager
 *
 * Handles workspace connection, issue state visibility mapping, bidirectional sync,
 * webhook processing, and activity feed updates.
 */

namespace Newera\Integrations\Linear;

use Newera\Core\Logger;
use Newera\Core\StateManager;
use Newera\Projects\ProjectManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class LinearManager
 */
class LinearManager {
    const PROVIDER = 'linear';

    const SECURE_NAMESPACE = 'integrations_linear';

    const API_ENDPOINT = 'https://api.linear.app/graphql';

    /**
     * @var StateManager
     */
    private $state_manager;

    /**
     * @var Logger|null
     */
    private $logger;

    /**
     * @var ProjectManager
     */
    private $projects;

    /**
     * Guard to prevent sync loops.
     *
     * @var bool
     */
    private $sync_in_progress = false;

    /**
     * @param StateManager|null $state_manager
     * @param Logger|null $logger
     * @param ProjectManager|null $projects
     */
    public function __construct($state_manager = null, $logger = null, $projects = null) {
        $this->state_manager = $state_manager instanceof StateManager
            ? $state_manager
            : (function_exists('newera_get_state_manager') ? newera_get_state_manager() : new StateManager());

        $this->logger = $logger instanceof Logger
            ? $logger
            : (function_exists('newera_get_logger') ? newera_get_logger() : (class_exists('\\Newera\\Core\\Logger') ? new Logger() : null));

        $this->projects = $projects instanceof ProjectManager
            ? $projects
            : (function_exists('newera_get_project_manager') ? newera_get_project_manager() : new ProjectManager($this->logger));
    }

    /**
     * Register hooks.
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        add_action('newera_project_created', [$this, 'on_project_created'], 10, 3);
        add_action('newera_project_updated', [$this, 'on_project_updated'], 10, 4);

        add_action('newera_linear_sync_now', [$this, 'sync_now']);
        add_action('newera_linear_sync_cron', [$this, 'sync_now']);
    }

    /**
     * @return bool
     */
    public function is_configured() {
        return (bool) $this->get_api_key();
    }

    /**
     * @return string|null
     */
    public function get_api_key() {
        if (!$this->state_manager || !method_exists($this->state_manager, 'getSecure')) {
            return null;
        }

        $value = $this->state_manager->getSecure(self::SECURE_NAMESPACE, 'api_key');
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param string $api_key
     * @return bool
     */
    public function set_api_key($api_key) {
        if (!$this->state_manager || !method_exists($this->state_manager, 'setSecure')) {
            return false;
        }

        $api_key = trim((string) $api_key);
        if ($api_key === '') {
            return false;
        }

        return (bool) $this->state_manager->setSecure(self::SECURE_NAMESPACE, 'api_key', $api_key);
    }

    /**
     * @return string|null
     */
    public function get_webhook_secret() {
        if (!$this->state_manager || !method_exists($this->state_manager, 'getSecure')) {
            return null;
        }

        $value = $this->state_manager->getSecure(self::SECURE_NAMESPACE, 'webhook_secret');
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param string $secret
     * @return bool
     */
    public function set_webhook_secret($secret) {
        if (!$this->state_manager || !method_exists($this->state_manager, 'setSecure')) {
            return false;
        }

        $secret = trim((string) $secret);
        if ($secret === '') {
            return false;
        }

        return (bool) $this->state_manager->setSecure(self::SECURE_NAMESPACE, 'webhook_secret', $secret);
    }

    /**
     * @return string|null
     */
    public function get_team_id() {
        if (!$this->state_manager || !method_exists($this->state_manager, 'get_state_value')) {
            return null;
        }

        $value = $this->state_manager->get_state_value('integrations_linear_team_id', null);
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param string $team_id
     * @return bool
     */
    public function set_team_id($team_id) {
        if (!$this->state_manager || !method_exists($this->state_manager, 'update_state')) {
            return false;
        }

        $team_id = trim((string) $team_id);
        if ($team_id === '') {
            return false;
        }

        $this->state_manager->update_state('integrations_linear_team_id', $team_id);
        return true;
    }

    /**
     * @return string
     */
    public function get_webhook_url() {
        return rest_url('newera/v1/linear/webhook');
    }

    /**
     * Register REST endpoints.
     */
    public function register_rest_routes() {
        register_rest_route('newera/v1', '/linear/webhook', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'handle_webhook'],
        ]);
    }

    /**
     * Handle incoming Linear webhook.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_webhook($request) {
        $secret = $this->get_webhook_secret();
        $body = (string) $request->get_body();

        if ($secret) {
            $provided = $this->get_signature_header();

            if (!$provided || !$this->verify_signature($provided, $body, $secret)) {
                return new \WP_REST_Response(['error' => 'Invalid signature'], 401);
            }
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return new \WP_REST_Response(['error' => 'Invalid payload'], 400);
        }

        $issue_id = null;
        if (isset($payload['data']['id'])) {
            $issue_id = (string) $payload['data']['id'];
        } elseif (isset($payload['data']['issueId'])) {
            $issue_id = (string) $payload['data']['issueId'];
        } elseif (isset($payload['issueId'])) {
            $issue_id = (string) $payload['issueId'];
        }

        if ($issue_id) {
            $this->sync_issue_by_id($issue_id);
        }

        if ($this->projects) {
            $this->projects->log_activity(
                'linear_webhook_received',
                'integration',
                null,
                'Linear webhook received',
                [
                    'payload' => $payload,
                ],
                0
            );
        }

        return new \WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Pull and apply changes from Linear.
     *
     * @return bool
     */
    public function sync_now() {
        if (!$this->is_configured()) {
            return false;
        }

        $since = null;
        if ($this->state_manager && method_exists($this->state_manager, 'get_state_value')) {
            $since = $this->state_manager->get_state_value('integrations_linear_last_sync', null);
        }

        $issues = $this->fetch_issues($since);
        if (is_wp_error($issues)) {
            return false;
        }

        foreach ($issues as $issue) {
            $this->sync_issue_to_project($issue);
        }

        if ($this->state_manager && method_exists($this->state_manager, 'update_state')) {
            $this->state_manager->update_state('integrations_linear_last_sync', current_time('mysql'));
        }

        return true;
    }

    /**
     * @param string|null $since MySQL datetime
     * @return array|\WP_Error
     */
    public function fetch_issues($since = null) {
        $filter = '';
        $variables = [];

        if (is_string($since) && $since !== '') {
            $filter = '(filter: { updatedAt: { gt: $since } })';
            $variables['since'] = $since;
        }

        $query = 'query($since: DateTime) { issues' . $filter . ' { nodes { id title description url updatedAt createdAt state { id name type } } } }';

        $response = $this->graphql($query, $variables);
        if (is_wp_error($response)) {
            return $response;
        }

        $nodes = $response['data']['issues']['nodes'] ?? [];
        return is_array($nodes) ? $nodes : [];
    }

    /**
     * @param string $issue_id
     * @return bool
     */
    public function sync_issue_by_id($issue_id) {
        if (!$this->is_configured()) {
            return false;
        }

        $query = 'query($id: String!) { issue(id: $id) { id title description url updatedAt createdAt state { id name type } comments { nodes { id body createdAt user { id name } } } } }';

        $response = $this->graphql($query, ['id' => (string) $issue_id]);
        if (is_wp_error($response)) {
            return false;
        }

        $issue = $response['data']['issue'] ?? null;
        if (!is_array($issue)) {
            return false;
        }

        $this->sync_issue_to_project($issue);
        $this->sync_issue_comments_to_activity($issue);

        return true;
    }

    /**
     * @param array $issue
     * @return int|false
     */
    public function sync_issue_to_project($issue) {
        if (!$this->projects) {
            return false;
        }

        $issue_id = isset($issue['id']) ? (string) $issue['id'] : '';
        if ($issue_id === '') {
            return false;
        }

        $existing_project = $this->projects->find_project_by_external_id(self::PROVIDER, $issue_id);

        $payload = [
            'title' => isset($issue['title']) ? (string) $issue['title'] : '',
            'description' => isset($issue['description']) ? (string) $issue['description'] : '',
            'status' => isset($issue['state']['name']) ? sanitize_key($issue['state']['name']) : 'pending',
        ];

        $context = ['source' => 'linear', 'linear_issue_id' => $issue_id];

        if ($existing_project) {
            $this->sync_in_progress = true;
            $this->projects->update_project((int) $existing_project->id, $payload, 0, $context);
            $this->sync_in_progress = false;

            $project_id = (int) $existing_project->id;
        } else {
            $this->sync_in_progress = true;
            $created = $this->projects->create_project([
                'client_id' => 0,
                'title' => $payload['title'],
                'description' => $payload['description'],
                'status' => $payload['status'],
            ], 0, $context);
            $this->sync_in_progress = false;

            if (is_wp_error($created)) {
                return false;
            }

            $project_id = (int) $created;
        }

        $this->projects->upsert_external_link(
            $project_id,
            self::PROVIDER,
            $issue_id,
            isset($issue['url']) ? (string) $issue['url'] : null,
            [
                'state_id' => $issue['state']['id'] ?? null,
                'state_name' => $issue['state']['name'] ?? null,
                'updated_at' => $issue['updatedAt'] ?? null,
            ]
        );

        $this->projects->log_activity(
            'linear_issue_synced',
            'project',
            $project_id,
            'Linear issue synced',
            [
                'issue_id' => $issue_id,
                'issue_url' => $issue['url'] ?? null,
            ],
            0
        );

        return $project_id;
    }

    /**
     * Append Linear comments into immutable activity logs.
     *
     * @param array $issue
     */
    private function sync_issue_comments_to_activity($issue) {
        if (!$this->projects) {
            return;
        }

        $issue_id = isset($issue['id']) ? (string) $issue['id'] : '';
        if ($issue_id === '') {
            return;
        }

        $comments = $issue['comments']['nodes'] ?? [];
        if (!is_array($comments) || empty($comments)) {
            return;
        }

        $project = $this->projects->find_project_by_external_id(self::PROVIDER, $issue_id);
        if (!$project) {
            return;
        }

        $state_key = 'integrations_linear_last_comment_id_' . md5($issue_id);
        $last_seen = $this->state_manager && method_exists($this->state_manager, 'get_state_value')
            ? (string) $this->state_manager->get_state_value($state_key, '')
            : '';

        $new_last = $last_seen;
        foreach ($comments as $comment) {
            $comment_id = isset($comment['id']) ? (string) $comment['id'] : '';
            if ($comment_id === '') {
                continue;
            }

            if ($last_seen !== '' && $comment_id === $last_seen) {
                $new_last = $comment_id;
                continue;
            }

            $this->projects->log_activity(
                'linear_comment',
                'project',
                (int) $project->id,
                'Linear comment',
                [
                    'issue_id' => $issue_id,
                    'comment_id' => $comment_id,
                    'body' => $comment['body'] ?? null,
                    'author' => $comment['user']['name'] ?? null,
                    'created_at' => $comment['createdAt'] ?? null,
                ],
                0
            );

            $new_last = $comment_id;
        }

        if ($new_last && $new_last !== $last_seen && $this->state_manager && method_exists($this->state_manager, 'update_state')) {
            $this->state_manager->update_state($state_key, $new_last);
        }
    }

    /**
     * Hook: push local project creation to Linear.
     *
     * @param int $project_id
     * @param array $data
     * @param array $context
     */
    public function on_project_created($project_id, $data, $context = []) {
        if (!$this->is_configured()) {
            return;
        }

        $context = is_array($context) ? $context : [];
        if (($context['source'] ?? 'local') !== 'local') {
            return;
        }

        $team_id = $this->get_team_id();
        if (!$team_id) {
            return;
        }

        // Only create an issue if not already linked.
        $existing = $this->projects->get_external_link((int) $project_id, self::PROVIDER);
        if ($existing) {
            return;
        }

        $this->create_issue_from_project((int) $project_id, $team_id);
    }

    /**
     * Hook: push local project updates to Linear.
     *
     * @param int $project_id
     * @param array $changes
     * @param object $existing
     * @param array $context
     */
    public function on_project_updated($project_id, $changes, $existing, $context = []) {
        if (!$this->is_configured() || $this->sync_in_progress) {
            return;
        }

        $context = is_array($context) ? $context : [];
        if (($context['source'] ?? 'local') !== 'local') {
            return;
        }

        $link = $this->projects->get_external_link((int) $project_id, self::PROVIDER);
        if (!$link || empty($link->external_id)) {
            return;
        }

        $this->update_issue_from_project((string) $link->external_id, $changes);
    }

    /**
     * Create a Linear issue from a local project.
     *
     * @param int $project_id
     * @param string $team_id
     * @return bool
     */
    public function create_issue_from_project($project_id, $team_id) {
        $project = $this->projects->get_project((int) $project_id);
        if (!$project) {
            return false;
        }

        $mutation = 'mutation($input: IssueCreateInput!) { issueCreate(input: $input) { success issue { id url } } }';

        $variables = [
            'input' => [
                'teamId' => (string) $team_id,
                'title' => (string) $project->title,
                'description' => (string) ($project->description ?? ''),
            ],
        ];

        $response = $this->graphql($mutation, $variables);
        if (is_wp_error($response)) {
            return false;
        }

        $issue = $response['data']['issueCreate']['issue'] ?? null;
        if (!is_array($issue) || empty($issue['id'])) {
            return false;
        }

        $this->projects->upsert_external_link(
            (int) $project_id,
            self::PROVIDER,
            (string) $issue['id'],
            isset($issue['url']) ? (string) $issue['url'] : null,
            []
        );

        $this->projects->log_activity(
            'linear_issue_created',
            'project',
            (int) $project_id,
            'Linear issue created from project',
            [
                'issue_id' => (string) $issue['id'],
                'issue_url' => $issue['url'] ?? null,
            ],
            get_current_user_id()
        );

        return true;
    }

    /**
     * Update a Linear issue from project changes.
     *
     * @param string $issue_id
     * @param array $changes
     * @return bool
     */
    public function update_issue_from_project($issue_id, $changes) {
        $input = [
            'id' => (string) $issue_id,
        ];

        if (isset($changes['title'])) {
            $input['title'] = (string) $changes['title'];
        }

        if (isset($changes['description'])) {
            $input['description'] = (string) $changes['description'];
        }

        if (count($input) === 1) {
            return true;
        }

        $mutation = 'mutation($input: IssueUpdateInput!) { issueUpdate(input: $input) { success issue { id } } }';

        $response = $this->graphql($mutation, ['input' => $input]);
        return !is_wp_error($response);
    }

    /**
     * GraphQL request.
     *
     * @param string $query
     * @param array $variables
     * @return array|\WP_Error
     */
    public function graphql($query, $variables = []) {
        $api_key = $this->get_api_key();
        if (!$api_key) {
            return new \WP_Error('newera_linear_not_configured', __('Linear integration not configured.', 'newera'));
        }

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $api_key,
            ],
            'timeout' => 20,
            'body' => wp_json_encode([
                'query' => $query,
                'variables' => (object) $variables,
            ]),
        ];

        $response = wp_remote_post(self::API_ENDPOINT, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new \WP_Error('newera_linear_invalid_response', __('Invalid response from Linear.', 'newera'));
        }

        if ($code >= 400) {
            return new \WP_Error('newera_linear_http_error', __('Linear API request failed.', 'newera'), [
                'code' => $code,
                'body' => $decoded,
            ]);
        }

        if (!empty($decoded['errors'])) {
            return new \WP_Error('newera_linear_graphql_error', __('Linear GraphQL error.', 'newera'), [
                'errors' => $decoded['errors'],
            ]);
        }

        return $decoded;
    }

    /**
     * Test connection and return workspace info.
     *
     * @return array|\WP_Error
     */
    public function test_connection() {
        $query = 'query { viewer { id name email organization { id name urlKey } } }';
        $response = $this->graphql($query);
        if (is_wp_error($response)) {
            return $response;
        }

        return $response['data']['viewer'] ?? [];
    }

    /**
     * Fetch teams for UI selection.
     *
     * @return array|\WP_Error
     */
    public function fetch_teams() {
        $query = 'query { teams { nodes { id name key } } }';
        $response = $this->graphql($query);
        if (is_wp_error($response)) {
            return $response;
        }

        $nodes = $response['data']['teams']['nodes'] ?? [];
        return is_array($nodes) ? $nodes : [];
    }

    /**
     * Fetch workflow states.
     *
     * @return array|\WP_Error
     */
    public function fetch_workflow_states() {
        $query = 'query { workflowStates { nodes { id name type } } }';
        $response = $this->graphql($query);
        if (is_wp_error($response)) {
            return $response;
        }

        $nodes = $response['data']['workflowStates']['nodes'] ?? [];
        return is_array($nodes) ? $nodes : [];
    }

    /**
     * @return string|null
     */
    private function get_signature_header() {
        // Try multiple possible signature headers.
        $server = $_SERVER;
        $candidates = [
            'HTTP_LINEAR_SIGNATURE',
            'HTTP_X_LINEAR_SIGNATURE',
            'HTTP_LINEAR_WEBHOOK_SIGNATURE',
            'HTTP_X_LINEAR_WEBHOOK_SIGNATURE',
        ];

        foreach ($candidates as $key) {
            if (!empty($server[$key])) {
                return (string) $server[$key];
            }
        }

        return null;
    }

    /**
     * Verify HMAC signatures.
     *
     * @param string $provided
     * @param string $payload
     * @param string $secret
     * @return bool
     */
    private function verify_signature($provided, $payload, $secret) {
        $provided = trim((string) $provided);
        $provided = preg_replace('/^sha256=/', '', $provided);

        $hex = hash_hmac('sha256', $payload, $secret);
        if ($this->hash_equals_safe($hex, $provided)) {
            return true;
        }

        $base64 = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        if ($this->hash_equals_safe($base64, $provided)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $a
     * @param string $b
     * @return bool
     */
    private function hash_equals_safe($a, $b) {
        if (function_exists('hash_equals')) {
            return hash_equals((string) $a, (string) $b);
        }

        return (string) $a === (string) $b;
    }
}
