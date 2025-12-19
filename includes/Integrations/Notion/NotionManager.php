<?php
/**
 * Notion Integration Manager
 *
 * Handles workspace connection, database mapping, content sync, webhook processing,
 * and bi-directional project property updates.
 */

namespace Newera\Integrations\Notion;

use Newera\Core\Logger;
use Newera\Core\StateManager;
use Newera\Projects\ProjectManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class NotionManager
 */
class NotionManager {
    const PROVIDER = 'notion';

    const SECURE_NAMESPACE = 'integrations_notion';

    const API_BASE = 'https://api.notion.com/v1';

    const NOTION_VERSION = '2022-06-28';

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

        add_action('newera_notion_sync_now', [$this, 'sync_now']);
        add_action('newera_notion_sync_cron', [$this, 'sync_now']);
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
     * Notion database containing rows representing Newera projects.
     *
     * @return string|null
     */
    public function get_projects_database_id() {
        if (!$this->state_manager || !method_exists($this->state_manager, 'get_state_value')) {
            return null;
        }

        $value = $this->state_manager->get_state_value('integrations_notion_projects_database_id', null);
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param string $database_id
     * @return bool
     */
    public function set_projects_database_id($database_id) {
        if (!$this->state_manager || !method_exists($this->state_manager, 'update_state')) {
            return false;
        }

        $database_id = trim((string) $database_id);
        if ($database_id === '') {
            return false;
        }

        $this->state_manager->update_state('integrations_notion_projects_database_id', $database_id);
        return true;
    }

    /**
     * Map Notion databases to specific Newera projects (used for content sync).
     *
     * @return array
     */
    public function get_database_mappings() {
        if (!$this->state_manager || !method_exists($this->state_manager, 'get_state_value')) {
            return [];
        }

        $value = $this->state_manager->get_state_value('integrations_notion_database_mappings', []);
        return is_array($value) ? $value : [];
    }

    /**
     * @param array $mappings database_id => project_id
     * @return bool
     */
    public function set_database_mappings($mappings) {
        if (!$this->state_manager || !method_exists($this->state_manager, 'update_state')) {
            return false;
        }

        $clean = [];
        if (is_array($mappings)) {
            foreach ($mappings as $db_id => $project_id) {
                $db_id = trim((string) $db_id);
                $project_id = (int) $project_id;
                if ($db_id === '' || $project_id <= 0) {
                    continue;
                }
                $clean[$db_id] = $project_id;
            }
        }

        $this->state_manager->update_state('integrations_notion_database_mappings', $clean);
        return true;
    }

    /**
     * @return string
     */
    public function get_webhook_url() {
        return rest_url('newera/v1/notion/webhook');
    }

    /**
     * Register REST endpoints.
     */
    public function register_rest_routes() {
        register_rest_route('newera/v1', '/notion/webhook', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'handle_webhook'],
        ]);
    }

    /**
     * Handle incoming Notion webhook.
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

        // Best-effort sync.
        $this->sync_now();

        if ($this->projects) {
            $this->projects->log_activity(
                'notion_webhook_received',
                'integration',
                null,
                'Notion webhook received',
                [
                    'payload' => $payload,
                ],
                0
            );
        }

        return new \WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Sync Notion content.
     *
     * - If a "projects" database is configured, sync project pages <-> local projects.
     * - If database mappings are configured, pull mapped DB content as deliverables.
     *
     * @return bool
     */
    public function sync_now() {
        if (!$this->is_configured()) {
            return false;
        }

        $this->sync_projects_database();
        $this->sync_mapped_databases();

        if ($this->state_manager && method_exists($this->state_manager, 'update_state')) {
            $this->state_manager->update_state('integrations_notion_last_sync', current_time('mysql'));
        }

        return true;
    }

    /**
     * Fetch databases for mapping UI.
     *
     * @return array|\WP_Error
     */
    public function search_databases() {
        $response = $this->request('POST', '/search', [
            'page_size' => 100,
            'filter' => [
                'property' => 'object',
                'value' => 'database',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $results = $response['results'] ?? [];
        return is_array($results) ? $results : [];
    }

    /**
     * Push local project creation to Notion.
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

        $database_id = $this->get_projects_database_id();
        if (!$database_id) {
            return;
        }

        $existing = $this->projects->get_external_link((int) $project_id, self::PROVIDER);
        if ($existing) {
            return;
        }

        $this->create_page_from_project((int) $project_id, $database_id);
    }

    /**
     * Push local project updates to Notion.
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

        $this->update_page_from_project((string) $link->external_id, $changes);
    }

    /**
     * Create a Notion page (row) for a project.
     *
     * Expected database properties:
     * - Name (title)
     * - Status (select, optional)
     * - Progress (number, optional)
     *
     * @param int $project_id
     * @param string $database_id
     * @return bool
     */
    public function create_page_from_project($project_id, $database_id) {
        $project = $this->projects->get_project((int) $project_id);
        if (!$project) {
            return false;
        }

        $payload = [
            'parent' => [
                'database_id' => (string) $database_id,
            ],
            'properties' => [
                'Name' => [
                    'title' => [
                        [
                            'text' => [
                                'content' => (string) $project->title,
                            ],
                        ],
                    ],
                ],
                'Status' => [
                    'select' => [
                        'name' => (string) ($project->status ?? 'pending'),
                    ],
                ],
                'Progress' => [
                    'number' => isset($project->progress) ? (int) $project->progress : 0,
                ],
            ],
        ];

        $response = $this->request('POST', '/pages', $payload);
        if (is_wp_error($response)) {
            return false;
        }

        $page_id = $response['id'] ?? null;
        if (!is_string($page_id) || $page_id === '') {
            return false;
        }

        $this->projects->upsert_external_link(
            (int) $project_id,
            self::PROVIDER,
            (string) $page_id,
            isset($response['url']) ? (string) $response['url'] : null,
            []
        );

        $this->projects->log_activity(
            'notion_page_created',
            'project',
            (int) $project_id,
            'Notion page created from project',
            [
                'page_id' => $page_id,
                'page_url' => $response['url'] ?? null,
            ],
            get_current_user_id()
        );

        return true;
    }

    /**
     * Update a Notion page based on project changes.
     *
     * @param string $page_id
     * @param array $changes
     * @return bool
     */
    public function update_page_from_project($page_id, $changes) {
        $properties = [];

        if (isset($changes['title'])) {
            $properties['Name'] = [
                'title' => [
                    [
                        'text' => [
                            'content' => (string) $changes['title'],
                        ],
                    ],
                ],
            ];
        }

        if (isset($changes['status'])) {
            $properties['Status'] = [
                'select' => [
                    'name' => (string) $changes['status'],
                ],
            ];
        }

        if (isset($changes['progress'])) {
            $properties['Progress'] = [
                'number' => (int) $changes['progress'],
            ];
        }

        if (empty($properties)) {
            return true;
        }

        $response = $this->request('PATCH', '/pages/' . rawurlencode((string) $page_id), [
            'properties' => $properties,
        ]);

        return !is_wp_error($response);
    }

    /**
     * Sync a configured projects database into local projects.
     */
    private function sync_projects_database() {
        $database_id = $this->get_projects_database_id();
        if (!$database_id) {
            return;
        }

        $result = $this->request('POST', '/databases/' . rawurlencode((string) $database_id) . '/query', [
            'page_size' => 100,
        ]);

        if (is_wp_error($result)) {
            return;
        }

        $pages = $result['results'] ?? [];
        if (!is_array($pages)) {
            return;
        }

        foreach ($pages as $page) {
            $page_id = isset($page['id']) ? (string) $page['id'] : '';
            if ($page_id === '') {
                continue;
            }

            $title = $this->extract_page_title($page);
            if ($title === '') {
                continue;
            }

            $status = $this->extract_select_value($page, 'Status');
            $progress = $this->extract_number_value($page, 'Progress');

            $project = $this->projects->find_project_by_external_id(self::PROVIDER, $page_id);

            $context = ['source' => 'notion', 'notion_page_id' => $page_id];

            if ($project) {
                $this->sync_in_progress = true;
                $this->projects->update_project((int) $project->id, [
                    'title' => $title,
                    'status' => $status !== '' ? $status : ($project->status ?? 'pending'),
                    'progress' => $progress !== null ? $progress : (int) ($project->progress ?? 0),
                ], 0, $context);
                $this->sync_in_progress = false;

                $project_id = (int) $project->id;
            } else {
                $this->sync_in_progress = true;
                $created = $this->projects->create_project([
                    'client_id' => 0,
                    'title' => $title,
                    'status' => $status !== '' ? $status : 'pending',
                    'progress' => $progress !== null ? $progress : 0,
                ], 0, $context);
                $this->sync_in_progress = false;

                if (is_wp_error($created)) {
                    continue;
                }

                $project_id = (int) $created;
            }

            $this->projects->upsert_external_link(
                $project_id,
                self::PROVIDER,
                $page_id,
                isset($page['url']) ? (string) $page['url'] : null,
                [
                    'last_edited_time' => $page['last_edited_time'] ?? null,
                    'database_id' => $database_id,
                ]
            );

            $this->projects->log_activity(
                'notion_project_synced',
                'project',
                $project_id,
                'Notion project row synced',
                [
                    'page_id' => $page_id,
                    'page_url' => $page['url'] ?? null,
                ],
                0
            );
        }
    }

    /**
     * Pull content from mapped databases as deliverables.
     */
    private function sync_mapped_databases() {
        $mappings = $this->get_database_mappings();
        if (empty($mappings)) {
            return;
        }

        foreach ($mappings as $db_id => $project_id) {
            $db_id = (string) $db_id;
            $project_id = (int) $project_id;

            if ($db_id === '' || $project_id <= 0) {
                continue;
            }

            $result = $this->request('POST', '/databases/' . rawurlencode($db_id) . '/query', [
                'page_size' => 100,
            ]);

            if (is_wp_error($result)) {
                continue;
            }

            $pages = $result['results'] ?? [];
            if (!is_array($pages)) {
                continue;
            }

            foreach ($pages as $page) {
                $title = $this->extract_page_title($page);
                if ($title === '') {
                    continue;
                }

                // We intentionally do not de-dupe deliverables without a dedicated link table.
                $this->projects->create_deliverable($project_id, [
                    'title' => $title,
                    'description' => null,
                    'status' => 'pending',
                ], 0);

                $this->projects->log_activity(
                    'notion_deliverable_imported',
                    'project',
                    $project_id,
                    'Notion item imported as deliverable',
                    [
                        'database_id' => $db_id,
                        'page_id' => $page['id'] ?? null,
                        'page_url' => $page['url'] ?? null,
                        'title' => $title,
                    ],
                    0
                );
            }
        }
    }

    /**
     * API request.
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     * @return array|\WP_Error
     */
    public function request($method, $path, $body = null) {
        $token = $this->get_api_key();
        if (!$token) {
            return new \WP_Error('newera_notion_not_configured', __('Notion integration not configured.', 'newera'));
        }

        $url = rtrim(self::API_BASE, '/') . '/' . ltrim($path, '/');

        $args = [
            'method' => strtoupper((string) $method),
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Notion-Version' => self::NOTION_VERSION,
            ],
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return new \WP_Error('newera_notion_invalid_response', __('Invalid response from Notion.', 'newera'));
        }

        if ($code >= 400) {
            return new \WP_Error('newera_notion_http_error', __('Notion API request failed.', 'newera'), [
                'code' => $code,
                'body' => $decoded,
            ]);
        }

        return $decoded;
    }

    /**
     * Test connection.
     *
     * @return array|\WP_Error
     */
    public function test_connection() {
        return $this->request('GET', '/users/me');
    }

    /**
     * @return string|null
     */
    private function get_signature_header() {
        $server = $_SERVER;
        $candidates = [
            'HTTP_NOTION_SIGNATURE',
            'HTTP_X_NOTION_SIGNATURE',
            'HTTP_NOTION_WEBHOOK_SIGNATURE',
        ];

        foreach ($candidates as $key) {
            if (!empty($server[$key])) {
                return (string) $server[$key];
            }
        }

        return null;
    }

    /**
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

    /**
     * @param array $page
     * @return string
     */
    private function extract_page_title($page) {
        if (!is_array($page)) {
            return '';
        }

        $props = $page['properties'] ?? [];
        if (!is_array($props)) {
            return '';
        }

        if (!isset($props['Name']['title']) || !is_array($props['Name']['title'])) {
            return '';
        }

        $parts = [];
        foreach ($props['Name']['title'] as $item) {
            $content = $item['plain_text'] ?? ($item['text']['content'] ?? null);
            if (is_string($content) && $content !== '') {
                $parts[] = $content;
            }
        }

        return trim(implode('', $parts));
    }

    /**
     * @param array $page
     * @param string $property
     * @return string
     */
    private function extract_select_value($page, $property) {
        if (!is_array($page) || empty($page['properties'][$property]['select']['name'])) {
            return '';
        }

        return (string) $page['properties'][$property]['select']['name'];
    }

    /**
     * @param array $page
     * @param string $property
     * @return int|null
     */
    private function extract_number_value($page, $property) {
        if (!is_array($page) || !array_key_exists($property, $page['properties'] ?? [])) {
            return null;
        }

        $value = $page['properties'][$property]['number'] ?? null;
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }
}
