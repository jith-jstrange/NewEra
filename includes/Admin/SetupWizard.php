<?php
/**
 * Setup Wizard orchestrator for Newera Plugin
 *
 * Provides a minimal, multi-step onboarding wizard without requiring React/Vue.
 */

namespace Newera\Admin;

use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Setup Wizard class
 */
class SetupWizard {
    /**
     * Admin page slug.
     */
    const PAGE_SLUG = 'newera-setup-wizard';

    /**
     * State key within StateManager option.
     */
    const STATE_KEY = 'setup_wizard';

    /**
     * StateManager instance.
     *
     * @var StateManager
     */
    private $state_manager;

    /**
     * Wizard steps.
     *
     * @var array
     */
    private $steps;

    /**
     * Constructor.
     *
     * @param StateManager|null $state_manager State manager (optional).
     */
    public function __construct($state_manager = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : new StateManager();

        $this->steps = [
            'intro' => [
                'title' => __('Intro', 'newera'),
                'description' => __('Welcome to Newera. This wizard will guide you through a basic configuration.', 'newera'),
            ],
            'auth' => [
                'title' => __('Auth', 'newera'),
                'description' => __('Placeholder authentication step (e.g. API keys).', 'newera'),
            ],
            'database' => [
                'title' => __('Database', 'newera'),
                'description' => __('Placeholder database configuration step.', 'newera'),
            ],
            'payments' => [
                'title' => __('Payments', 'newera'),
                'description' => __('Placeholder payments provider configuration step.', 'newera'),
            ],
            'ai' => [
                'title' => __('AI', 'newera'),
                'description' => __('Placeholder AI provider configuration step.', 'newera'),
            ],
            'review' => [
                'title' => __('Review', 'newera'),
                'description' => __('Review and complete setup.', 'newera'),
            ],
        ];
    }

    /**
     * Initialize wizard hooks.
     */
    public function init() {
        add_action('admin_menu', [$this, 'register_page'], 30);
        add_action('admin_init', [$this, 'maybe_redirect_to_wizard']);

        add_action('wp_ajax_newera_setup_wizard_save_step', [$this, 'ajax_save_step']);
        add_action('wp_ajax_newera_setup_wizard_reset', [$this, 'ajax_reset']);
    }

    /**
     * Register the wizard page.
     */
    public function register_page() {
        add_submenu_page(
            'newera',
            __('Setup Wizard', 'newera'),
            __('Setup Wizard', 'newera'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Redirect Newera admin pages to the wizard until onboarding is completed.
     */
    public function maybe_redirect_to_wizard() {
        if (!is_admin() || wp_doing_ajax()) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if ($this->is_completed()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

        if ($page === '') {
            return;
        }

        if (strpos($page, 'newera') !== 0) {
            return;
        }

        if ($page === self::PAGE_SLUG) {
            return;
        }

        wp_safe_redirect($this->get_wizard_url());
        exit;
    }

    /**
     * Render wizard page.
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        $notice = null;

        if (isset($_POST['newera_setup_wizard_submit'])) {
            $result = $this->handle_post_submission($_POST);

            if (is_wp_error($result)) {
                $notice = [
                    'type' => 'error',
                    'message' => $result->get_error_message(),
                ];
            } else {
                wp_safe_redirect($result);
                exit;
            }
        }

        if (isset($_POST['newera_setup_wizard_reset'])) {
            $result = $this->handle_post_reset($_POST);

            if (is_wp_error($result)) {
                $notice = [
                    'type' => 'error',
                    'message' => $result->get_error_message(),
                ];
            } else {
                wp_safe_redirect($this->get_wizard_url());
                exit;
            }
        }

        $wizard_state = $this->get_wizard_state();
        $revisit = isset($_GET['revisit']) && $_GET['revisit'] === '1';

        $requested_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : '';
        $current_step = $requested_step !== '' ? $requested_step : ($wizard_state['current_step'] ?? 'intro');

        if (!isset($this->steps[$current_step])) {
            $current_step = $wizard_state['current_step'] ?? 'intro';
        }

        // Enforce sequential access unless revisiting after completion.
        if (!$this->is_step_accessible($current_step, $wizard_state) && !$revisit) {
            $current_step = $wizard_state['current_step'] ?? 'intro';
        }

        $template_data = [
            'steps' => $this->steps,
            'wizard_state' => $wizard_state,
            'current_step' => $current_step,
            'revisit' => $revisit,
            'notice' => $notice,
            'wizard_url' => $this->get_wizard_url(),
        ];

        include NEWERA_PLUGIN_PATH . 'templates/admin/setup-wizard.php';
    }

    /**
     * Handle POST submissions (non-AJAX fallback).
     *
     * @param array $post POST data.
     * @return string|\WP_Error Redirect URL or error.
     */
    private function handle_post_submission($post) {
        $nonce = isset($post['newera_setup_wizard_nonce']) ? $post['newera_setup_wizard_nonce'] : '';

        if (!wp_verify_nonce($nonce, 'newera_setup_wizard_submit')) {
            return new \WP_Error('newera_setup_wizard_nonce', __('Security check failed.', 'newera'));
        }

        $step = isset($post['step']) ? sanitize_key($post['step']) : '';

        if ($step === '' || !isset($this->steps[$step])) {
            return new \WP_Error('newera_setup_wizard_step', __('Invalid wizard step.', 'newera'));
        }

        $data = $this->extract_step_data_from_request($post);
        $next_step = $this->save_step($step, $data);

        $redirect_args = ['step' => $next_step];
        if (isset($_GET['revisit']) && $_GET['revisit'] === '1') {
            $redirect_args['revisit'] = '1';
        }

        return $this->get_wizard_url($redirect_args);
    }

    /**
     * Handle POST reset.
     *
     * @param array $post POST data.
     * @return true|\WP_Error
     */
    private function handle_post_reset($post) {
        $nonce = isset($post['newera_setup_wizard_reset_nonce']) ? $post['newera_setup_wizard_reset_nonce'] : '';

        if (!wp_verify_nonce($nonce, 'newera_setup_wizard_reset')) {
            return new \WP_Error('newera_setup_wizard_reset_nonce', __('Security check failed.', 'newera'));
        }

        $this->reset_wizard();

        return true;
    }

    /**
     * AJAX handler for saving a step.
     */
    public function ajax_save_step() {
        check_ajax_referer('newera_setup_wizard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'newera'), 403);
        }

        $step = isset($_POST['step']) ? sanitize_key(wp_unslash($_POST['step'])) : '';
        if ($step === '' || !isset($this->steps[$step])) {
            wp_send_json_error(__('Invalid wizard step.', 'newera'), 400);
        }

        $data = $this->extract_step_data_from_request($_POST);
        $next_step = $this->save_step($step, $data);

        wp_send_json_success([
            'next_step' => $next_step,
            'redirect_url' => $this->get_wizard_url(['step' => $next_step]),
            'state' => $this->get_wizard_state(),
        ]);
    }

    /**
     * AJAX handler for resetting wizard.
     */
    public function ajax_reset() {
        check_ajax_referer('newera_setup_wizard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'newera'), 403);
        }

        $this->reset_wizard();

        wp_send_json_success([
            'redirect_url' => $this->get_wizard_url(),
            'state' => $this->get_wizard_state(),
        ]);
    }

    /**
     * Save step data and return next step.
     *
     * @param string $step Step id.
     * @param array $data Sanitized data.
     * @return string Next step.
     */
    private function save_step($step, $data) {
        $wizard_state = $this->get_wizard_state();

        if ($step === 'auth') {
            $this->persist_integration_credentials($data);
        }

        $wizard_state['data'][$step] = $this->sanitize_step_data($step, $data);

        if (!in_array($step, $wizard_state['completed_steps'], true)) {
            $wizard_state['completed_steps'][] = $step;
        }

        $next_step = $this->get_next_step($step);
        $wizard_state['current_step'] = $next_step;
        $wizard_state['last_updated'] = current_time('mysql');

        if ($step === 'review') {
            $wizard_state['completed'] = true;
            $wizard_state['completed_at'] = current_time('mysql');
            $wizard_state['current_step'] = 'review';
        }

        $this->state_manager->update_state(self::STATE_KEY, $wizard_state);

        return $wizard_state['current_step'];
    }

    /**
     * Reset the wizard state.
     */
    private function reset_wizard() {
        $this->state_manager->update_state(self::STATE_KEY, $this->get_default_wizard_state());
    }

    /**
     * Check whether wizard is completed.
     *
     * @return bool
     */
    private function is_completed() {
        $wizard_state = $this->get_wizard_state();
        return !empty($wizard_state['completed']);
    }

    /**
     * Get wizard state with defaults.
     *
     * @return array
     */
    private function get_wizard_state() {
        $state = $this->state_manager->get_state_value(self::STATE_KEY, []);

        return array_merge(
            $this->get_default_wizard_state(),
            is_array($state) ? $state : []
        );
    }

    /**
     * Default wizard state.
     *
     * @return array
     */
    private function get_default_wizard_state() {
        return [
            'completed' => false,
            'completed_at' => null,
            'current_step' => 'intro',
            'completed_steps' => [],
            'data' => [],
            'last_updated' => null,
        ];
    }

    /**
     * Determine if a step is accessible based on current progress.
     *
     * @param string $step Step id.
     * @param array $wizard_state Wizard state.
     * @return bool
     */
    private function is_step_accessible($step, $wizard_state) {
        if ($this->is_completed()) {
            return true;
        }

        if ($step === ($wizard_state['current_step'] ?? 'intro')) {
            return true;
        }

        if (in_array($step, $wizard_state['completed_steps'] ?? [], true)) {
            return true;
        }

        $step_ids = array_keys($this->steps);
        $requested_index = array_search($step, $step_ids, true);
        $current_index = array_search($wizard_state['current_step'] ?? 'intro', $step_ids, true);

        if ($requested_index === false || $current_index === false) {
            return false;
        }

        return $requested_index < $current_index;
    }

    /**
     * Get next step id.
     *
     * @param string $step Current step.
     * @return string
     */
    private function get_next_step($step) {
        $step_ids = array_keys($this->steps);
        $idx = array_search($step, $step_ids, true);

        if ($idx === false) {
            return 'intro';
        }

        if (!isset($step_ids[$idx + 1])) {
            return $step;
        }

        return $step_ids[$idx + 1];
    }

    /**
     * Get previous step id.
     *
     * @param string $step Current step.
     * @return string
     */
    public function get_previous_step($step) {
        $step_ids = array_keys($this->steps);
        $idx = array_search($step, $step_ids, true);

        if ($idx === false || $idx === 0) {
            return 'intro';
        }

        return $step_ids[$idx - 1];
    }

    /**
     * Get wizard url.
     *
     * @param array $args Additional query args.
     * @return string
     */
    public function get_wizard_url($args = []) {
        $base = admin_url('admin.php?page=' . self::PAGE_SLUG);

        if (empty($args)) {
            return $base;
        }

        return add_query_arg($args, $base);
    }

    /**
     * Extract step data from a request.
     *
     * @param array $request Raw request array.
     * @return array
     */
    private function extract_step_data_from_request($request) {
        $data = [];

        foreach ($request as $key => $value) {
            if (in_array($key, [
                'action',
                'nonce',
                'newera_setup_wizard_nonce',
                'newera_setup_wizard_submit',
                'newera_setup_wizard_reset',
                'newera_setup_wizard_reset_nonce',
                'step',
            ], true)) {
                continue;
            }

            if (is_array($value)) {
                $data[$key] = array_map('sanitize_text_field', wp_unslash($value));
            } else {
                $data[$key] = sanitize_text_field(wp_unslash($value));
            }
        }

        return $data;
    }

    /**
     * Persist integration credentials from the wizard auth step.
     *
     * Credentials are stored via StateManager secure storage (no central registry).
     *
     * @param array $data Sanitized request data.
     */
    private function persist_integration_credentials($data) {
        if (!is_array($data)) {
            return;
        }

        // Linear
        if (class_exists('\\Newera\\Integrations\\Linear\\LinearManager')) {
            $linear = new \Newera\Integrations\Linear\LinearManager($this->state_manager);

            if (!empty($data['linear_api_key'])) {
                $linear->set_api_key($data['linear_api_key']);
            }

            if (!empty($data['linear_webhook_secret'])) {
                $linear->set_webhook_secret($data['linear_webhook_secret']);
            }

            if (!empty($data['linear_team_id'])) {
                $linear->set_team_id($data['linear_team_id']);
            }
        }

        // Notion
        if (class_exists('\\Newera\\Integrations\\Notion\\NotionManager')) {
            $notion = new \Newera\Integrations\Notion\NotionManager($this->state_manager);

            if (!empty($data['notion_api_key'])) {
                $notion->set_api_key($data['notion_api_key']);
            }

            if (!empty($data['notion_webhook_secret'])) {
                $notion->set_webhook_secret($data['notion_webhook_secret']);
            }

            if (!empty($data['notion_projects_database_id'])) {
                $notion->set_projects_database_id($data['notion_projects_database_id']);
            }
        }
    }

    /**
     * Sanitize step-specific data.
     *
     * @param string $step Step id.
     * @param array $data Data.
     * @return array
     */
    private function sanitize_step_data($step, $data) {
        $sanitized = [];

        switch ($step) {
            case 'auth':
                $linear_configured = false;
                if (class_exists('\\Newera\\Integrations\\Linear\\LinearManager')) {
                    $linear = new \Newera\Integrations\Linear\LinearManager($this->state_manager);
                    $linear_configured = method_exists($linear, 'is_configured') ? (bool) $linear->is_configured() : false;
                }

                $notion_configured = false;
                if (class_exists('\\Newera\\Integrations\\Notion\\NotionManager')) {
                    $notion = new \Newera\Integrations\Notion\NotionManager($this->state_manager);
                    $notion_configured = method_exists($notion, 'is_configured') ? (bool) $notion->is_configured() : false;
                }

                $sanitized['linear_configured'] = $linear_configured;
                $sanitized['linear_team_id'] = isset($data['linear_team_id']) ? sanitize_text_field($data['linear_team_id']) : '';
                $sanitized['notion_configured'] = $notion_configured;
                $sanitized['notion_projects_database_id'] = isset($data['notion_projects_database_id']) ? sanitize_text_field($data['notion_projects_database_id']) : '';
                break;

            case 'database':
                $sanitized['connection_name'] = isset($data['connection_name']) ? sanitize_text_field($data['connection_name']) : '';
                break;

            case 'payments':
                $sanitized['provider'] = isset($data['provider']) ? sanitize_text_field($data['provider']) : '';
                break;

            case 'ai':
                $sanitized['provider'] = isset($data['provider']) ? sanitize_text_field($data['provider']) : '';
                $sanitized['model'] = isset($data['model']) ? sanitize_text_field($data['model']) : '';
                break;

            case 'intro':
            case 'review':
            default:
                foreach ($data as $key => $value) {
                    $sanitized[$key] = is_string($value) ? sanitize_text_field($value) : $value;
                }
                break;
        }

        return $sanitized;
    }
}
