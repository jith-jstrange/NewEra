<?php
/**
 * Project Manager for Newera Plugin
 *
 * Provides project CRUD, milestones/deliverables tracking, team assignment,
 * and immutable activity logging.
 */

namespace Newera\Projects;

use Newera\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ProjectManager
 */
class ProjectManager {
    /**
     * @var Logger|null
     */
    private $logger;

    /**
     * @param Logger|null $logger
     */
    public function __construct($logger = null) {
        $this->logger = $logger instanceof Logger ? $logger : (class_exists('\\Newera\\Core\\Logger') ? new Logger() : null);
    }

    /**
     * Register hooks.
     */
    public function init() {
        add_action('init', [$this, 'ensure_roles']);
    }

    /**
     * Ensure client role and capabilities exist.
     */
    public function ensure_roles() {
        if (get_role('newera_client') === null) {
            add_role('newera_client', __('Newera Client', 'newera'), [
                'read' => true,
                'newera_view_projects' => true,
            ]);
        }

        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap('newera_manage_projects')) {
            $admin->add_cap('newera_manage_projects');
        }

        if ($admin && !$admin->has_cap('newera_view_projects')) {
            $admin->add_cap('newera_view_projects');
        }
    }

    /**
     * @return string
     */
    public function get_projects_table() {
        global $wpdb;
        return $wpdb->prefix . 'projects';
    }

    /**
     * @return string
     */
    public function get_activity_logs_table() {
        global $wpdb;
        return $wpdb->prefix . 'activity_logs';
    }

    /**
     * @return string
     */
    public function get_milestones_table() {
        global $wpdb;
        return $wpdb->prefix . 'project_milestones';
    }

    /**
     * @return string
     */
    public function get_deliverables_table() {
        global $wpdb;
        return $wpdb->prefix . 'project_deliverables';
    }

    /**
     * @return string
     */
    public function get_members_table() {
        global $wpdb;
        return $wpdb->prefix . 'project_members';
    }

    /**
     * @return string
     */
    public function get_external_links_table() {
        global $wpdb;
        return $wpdb->prefix . 'project_external_links';
    }

    /**
     * Create a project.
     *
     * @param array $data
     * @param int|null $actor_user_id
     * @param array $context Optional context, e.g. ['source' => 'linear'].
     * @return int|\WP_Error
     */
    public function create_project($data, $actor_user_id = null, $context = []) {
        global $wpdb;

        $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        if ($title === '') {
            return new \WP_Error('newera_project_title_required', __('Project title is required.', 'newera'));
        }

        $insert = [
            'client_id' => isset($data['client_id']) ? (int) $data['client_id'] : 0,
            'title' => $title,
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : null,
            'status' => isset($data['status']) ? sanitize_key($data['status']) : 'pending',
            'progress' => isset($data['progress']) ? max(0, min(100, (int) $data['progress'])) : 0,
            'start_date' => isset($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
            'end_date' => isset($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
        ];

        $result = $wpdb->insert($this->get_projects_table(), $insert);
        if ($result === false) {
            return new \WP_Error('newera_project_create_failed', __('Failed to create project.', 'newera'));
        }

        $project_id = (int) $wpdb->insert_id;

        $this->log_activity(
            'project_created',
            'project',
            $project_id,
            sprintf(__('Project created: %s', 'newera'), $title),
            [
                'data' => $insert,
            ],
            $actor_user_id
        );

        $context = is_array($context) ? $context : [];
        if (!isset($context['source'])) {
            $context['source'] = 'local';
        }

        do_action('newera_project_created', $project_id, $insert, $context);

        return $project_id;
    }

    /**
     * Update a project.
     *
     * @param int $project_id
     * @param array $data
     * @param int|null $actor_user_id
     * @param array $context Optional context, e.g. ['source' => 'linear'].
     * @return bool|\WP_Error
     */
    public function update_project($project_id, $data, $actor_user_id = null, $context = []) {
        global $wpdb;

        $project_id = (int) $project_id;
        $existing = $this->get_project($project_id);
        if (!$existing) {
            return new \WP_Error('newera_project_not_found', __('Project not found.', 'newera'));
        }

        $update = [];

        if (array_key_exists('client_id', $data)) {
            $update['client_id'] = (int) $data['client_id'];
        }

        if (array_key_exists('title', $data)) {
            $update['title'] = sanitize_text_field($data['title']);
        }

        if (array_key_exists('description', $data)) {
            $update['description'] = wp_kses_post($data['description']);
        }

        if (array_key_exists('status', $data)) {
            $update['status'] = sanitize_key($data['status']);
        }

        if (array_key_exists('progress', $data)) {
            $update['progress'] = max(0, min(100, (int) $data['progress']));
        }

        if (array_key_exists('start_date', $data)) {
            $update['start_date'] = sanitize_text_field($data['start_date']);
        }

        if (array_key_exists('end_date', $data)) {
            $update['end_date'] = sanitize_text_field($data['end_date']);
        }

        if (empty($update)) {
            return true;
        }

        $result = $wpdb->update($this->get_projects_table(), $update, ['id' => $project_id]);
        if ($result === false) {
            return new \WP_Error('newera_project_update_failed', __('Failed to update project.', 'newera'));
        }

        $this->log_activity(
            'project_updated',
            'project',
            $project_id,
            sprintf(__('Project updated: %s', 'newera'), $existing->title),
            [
                'changes' => $update,
            ],
            $actor_user_id
        );

        $context = is_array($context) ? $context : [];
        if (!isset($context['source'])) {
            $context['source'] = 'local';
        }

        do_action('newera_project_updated', $project_id, $update, $existing, $context);

        return true;
    }

    /**
     * Soft-delete a project (does not remove immutable activity logs).
     *
     * @param int $project_id
     * @param int|null $actor_user_id
     * @param array $context Optional context, e.g. ['source' => 'linear'].
     * @return bool|\WP_Error
     */
    public function delete_project($project_id, $actor_user_id = null, $context = []) {
        global $wpdb;

        $project_id = (int) $project_id;
        $existing = $this->get_project($project_id);
        if (!$existing) {
            return new \WP_Error('newera_project_not_found', __('Project not found.', 'newera'));
        }

        $result = $wpdb->update(
            $this->get_projects_table(),
            ['deleted_at' => current_time('mysql')],
            ['id' => $project_id]
        );

        if ($result === false) {
            return new \WP_Error('newera_project_delete_failed', __('Failed to delete project.', 'newera'));
        }

        $this->log_activity(
            'project_deleted',
            'project',
            $project_id,
            sprintf(__('Project deleted: %s', 'newera'), $existing->title),
            [],
            $actor_user_id
        );

        $context = is_array($context) ? $context : [];
        if (!isset($context['source'])) {
            $context['source'] = 'local';
        }

        do_action('newera_project_deleted', $project_id, $existing, $context);

        return true;
    }

    /**
     * Get a project.
     *
     * @param int $project_id
     * @return object|null
     */
    public function get_project($project_id) {
        global $wpdb;

        $project_id = (int) $project_id;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->get_projects_table() . ' WHERE id = %d AND deleted_at IS NULL',
                $project_id
            )
        );
    }

    /**
     * List projects visible to a given WP user.
     *
     * @param int $user_id
     * @param array $args
     * @return array
     */
    public function list_projects_for_user($user_id, $args = []) {
        global $wpdb;

        $user_id = (int) $user_id;

        $limit = isset($args['limit']) ? max(1, (int) $args['limit']) : 50;
        $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;

        $projects = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->get_projects_table() . ' WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT %d OFFSET %d',
                $limit,
                $offset
            )
        );

        if (!is_array($projects)) {
            return [];
        }

        $visible = [];
        foreach ($projects as $project) {
            if ($this->user_can_view_project($user_id, $project)) {
                $visible[] = $project;
            }
        }

        return $visible;
    }

    /**
     * Determine whether a user can view a project.
     *
     * @param int $user_id
     * @param object $project
     * @return bool
     */
    public function user_can_view_project($user_id, $project) {
        $user_id = (int) $user_id;

        if (user_can($user_id, 'manage_options') || user_can($user_id, 'newera_manage_projects')) {
            return true;
        }

        if (!user_can($user_id, 'newera_view_projects')) {
            return false;
        }

        // Team member assignment overrides client matching.
        if ($this->is_user_assigned_to_project($project->id, $user_id)) {
            return $this->passes_client_visibility_filters($project);
        }

        $client_id = $this->get_client_id_for_user($user_id);
        if ($client_id && (int) $project->client_id === (int) $client_id) {
            return $this->passes_client_visibility_filters($project);
        }

        return false;
    }

    /**
     * Client visibility filters for integrations.
     *
     * @param object $project
     * @return bool
     */
    private function passes_client_visibility_filters($project) {
        // If the project is linked to Linear, optionally enforce configured visible states.
        $link = $this->get_external_link($project->id, 'linear');
        if (!$link) {
            return true;
        }

        $state_manager = function_exists('newera_get_state_manager') ? newera_get_state_manager() : null;
        if (!$state_manager || !method_exists($state_manager, 'get_state_value')) {
            return true;
        }

        $visible_states = $state_manager->get_state_value('integrations_linear_visible_states', []);
        if (!is_array($visible_states) || empty($visible_states)) {
            return true;
        }

        $metadata = $this->decode_json($link->metadata);
        $state_id = isset($metadata['state_id']) ? (string) $metadata['state_id'] : '';

        if ($state_id === '') {
            return true;
        }

        return in_array($state_id, array_map('strval', $visible_states), true);
    }

    /**
     * Attempt to resolve a client record by WP user email.
     *
     * @param int $user_id
     * @return int|null
     */
    public function get_client_id_for_user($user_id) {
        global $wpdb;

        $user = get_user_by('id', (int) $user_id);
        if (!$user || empty($user->user_email)) {
            return null;
        }

        $clients_table = $wpdb->prefix . 'clients';

        $client_id = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM ' . $clients_table . ' WHERE email = %s AND deleted_at IS NULL',
                $user->user_email
            )
        );

        return $client_id ? (int) $client_id : null;
    }

    /**
     * Assign a WP user to a project.
     *
     * @param int $project_id
     * @param int $user_id
     * @param string $role
     * @param int|null $actor_user_id
     * @return bool|\WP_Error
     */
    public function assign_member($project_id, $user_id, $role = 'member', $actor_user_id = null) {
        global $wpdb;

        $project_id = (int) $project_id;
        $user_id = (int) $user_id;
        $role = sanitize_key($role);

        $result = $wpdb->query(
            $wpdb->prepare(
                'INSERT IGNORE INTO ' . $this->get_members_table() . ' (project_id, user_id, role) VALUES (%d, %d, %s)',
                $project_id,
                $user_id,
                $role
            )
        );

        if ($result === false) {
            return new \WP_Error('newera_project_member_assign_failed', __('Failed to assign team member.', 'newera'));
        }

        $this->log_activity(
            'project_member_assigned',
            'project',
            $project_id,
            __('Team member assigned to project.', 'newera'),
            [
                'member_user_id' => $user_id,
                'role' => $role,
            ],
            $actor_user_id
        );

        return true;
    }

    /**
     * @param int $project_id
     * @param int $user_id
     * @return bool
     */
    public function is_user_assigned_to_project($project_id, $user_id) {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . $this->get_members_table() . ' WHERE project_id = %d AND user_id = %d',
                (int) $project_id,
                (int) $user_id
            )
        );

        return (int) $count > 0;
    }

    /**
     * Create a milestone.
     *
     * @param int $project_id
     * @param array $data
     * @param int|null $actor_user_id
     * @return int|\WP_Error
     */
    public function create_milestone($project_id, $data, $actor_user_id = null) {
        global $wpdb;

        $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        if ($title === '') {
            return new \WP_Error('newera_milestone_title_required', __('Milestone title is required.', 'newera'));
        }

        $insert = [
            'project_id' => (int) $project_id,
            'title' => $title,
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : null,
            'status' => isset($data['status']) ? sanitize_key($data['status']) : 'pending',
            'due_date' => isset($data['due_date']) ? sanitize_text_field($data['due_date']) : null,
        ];

        $result = $wpdb->insert($this->get_milestones_table(), $insert);
        if ($result === false) {
            return new \WP_Error('newera_milestone_create_failed', __('Failed to create milestone.', 'newera'));
        }

        $milestone_id = (int) $wpdb->insert_id;

        $this->log_activity(
            'milestone_created',
            'milestone',
            $milestone_id,
            sprintf(__('Milestone created: %s', 'newera'), $title),
            [
                'project_id' => (int) $project_id,
            ],
            $actor_user_id
        );

        return $milestone_id;
    }

    /**
     * Create a deliverable.
     *
     * @param int $project_id
     * @param array $data
     * @param int|null $actor_user_id
     * @return int|\WP_Error
     */
    public function create_deliverable($project_id, $data, $actor_user_id = null) {
        global $wpdb;

        $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        if ($title === '') {
            return new \WP_Error('newera_deliverable_title_required', __('Deliverable title is required.', 'newera'));
        }

        $insert = [
            'project_id' => (int) $project_id,
            'milestone_id' => isset($data['milestone_id']) ? (int) $data['milestone_id'] : null,
            'title' => $title,
            'description' => isset($data['description']) ? wp_kses_post($data['description']) : null,
            'status' => isset($data['status']) ? sanitize_key($data['status']) : 'pending',
            'due_date' => isset($data['due_date']) ? sanitize_text_field($data['due_date']) : null,
        ];

        $result = $wpdb->insert($this->get_deliverables_table(), $insert);
        if ($result === false) {
            return new \WP_Error('newera_deliverable_create_failed', __('Failed to create deliverable.', 'newera'));
        }

        $deliverable_id = (int) $wpdb->insert_id;

        $this->log_activity(
            'deliverable_created',
            'deliverable',
            $deliverable_id,
            sprintf(__('Deliverable created: %s', 'newera'), $title),
            [
                'project_id' => (int) $project_id,
                'milestone_id' => $insert['milestone_id'],
            ],
            $actor_user_id
        );

        return $deliverable_id;
    }

    /**
     * Immutable activity logging (append-only).
     *
     * @param string $action
     * @param string $entity_type
     * @param int|null $entity_id
     * @param string|null $description
     * @param array $metadata
     * @param int|null $user_id
     * @return int|false
     */
    public function log_activity($action, $entity_type = null, $entity_id = null, $description = null, $metadata = [], $user_id = null) {
        global $wpdb;

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;

        $data = [
            'user_id' => $user_id !== null ? (int) $user_id : (is_user_logged_in() ? get_current_user_id() : null),
            'action' => sanitize_key($action),
            'entity_type' => $entity_type ? sanitize_key($entity_type) : null,
            'entity_id' => $entity_id !== null ? (int) $entity_id : null,
            'description' => $description ? wp_strip_all_tags($description) : null,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'status' => 'success',
            'metadata' => !empty($metadata) ? wp_json_encode($metadata) : null,
        ];

        // Note: activity_logs is treated as immutable by convention.
        $result = $wpdb->insert($this->get_activity_logs_table(), $data);
        if ($result === false) {
            if ($this->logger) {
                $this->logger->warning('Failed to write activity log', [
                    'action' => $action,
                    'entity_type' => $entity_type,
                    'entity_id' => $entity_id,
                ]);
            }
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Get an external link by project+provider.
     *
     * @param int $project_id
     * @param string $provider
     * @return object|null
     */
    public function get_external_link($project_id, $provider) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->get_external_links_table() . ' WHERE project_id = %d AND provider = %s',
                (int) $project_id,
                sanitize_key($provider)
            )
        );
    }

    /**
     * Find a project linked to an external provider.
     *
     * @param string $provider
     * @param string $external_id
     * @return object|null
     */
    public function find_project_by_external_id($provider, $external_id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT project_id FROM ' . $this->get_external_links_table() . ' WHERE provider = %s AND external_id = %s',
                sanitize_key($provider),
                (string) $external_id
            )
        );

        if (!$row || empty($row->project_id)) {
            return null;
        }

        return $this->get_project((int) $row->project_id);
    }

    /**
     * Create or update an external link for a project.
     *
     * @param int $project_id
     * @param string $provider
     * @param string $external_id
     * @param string|null $external_url
     * @param array $metadata
     * @return bool
     */
    public function upsert_external_link($project_id, $provider, $external_id, $external_url = null, $metadata = []) {
        global $wpdb;

        $provider = sanitize_key($provider);
        $external_id = (string) $external_id;

        $existing = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . $this->get_external_links_table() . ' WHERE provider = %s AND external_id = %s',
                $provider,
                $external_id
            )
        );

        $data = [
            'project_id' => (int) $project_id,
            'provider' => $provider,
            'external_id' => $external_id,
            'external_url' => $external_url ? esc_url_raw($external_url) : null,
            'metadata' => !empty($metadata) ? wp_json_encode($metadata) : null,
        ];

        if ($existing) {
            $wpdb->update(
                $this->get_external_links_table(),
                [
                    'project_id' => $data['project_id'],
                    'external_url' => $data['external_url'],
                    'metadata' => $data['metadata'],
                ],
                ['id' => (int) $existing->id]
            );
            return true;
        }

        $result = $wpdb->insert($this->get_external_links_table(), $data);
        return $result !== false;
    }

    /**
     * @param string|null $json
     * @return array
     */
    private function decode_json($json) {
        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
