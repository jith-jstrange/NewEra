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
                'description' => __('Configure your AI provider (OpenAI/Anthropic), store an API key securely, and select a model.', 'newera'),
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

        $sanitized = $this->sanitize_step_data($step, $data);
        $wizard_state['data'][$step] = $sanitized;

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

        $this->apply_step_side_effects($step, $data, $sanitized);

        return $wizard_state['current_step'];
    }

    /**
     * Apply step side effects (persist module configuration, store secure credentials, etc).
     *
     * @param string $step
     * @param array $raw_data
     * @param array $sanitized_data
     */
    private function apply_step_side_effects($step, $raw_data, $sanitized_data) {
        if ($step !== 'ai') {
            return;
        }

        if (!$this->state_manager) {
            return;
        }

        $provider = isset($raw_data['provider']) ? sanitize_key($raw_data['provider']) : '';
        $model = isset($raw_data['model']) ? sanitize_text_field($raw_data['model']) : '';

        if ($provider === '' || $model === '') {
            return;
        }

        $max_rpm = isset($raw_data['max_requests_per_minute']) ? (int) $raw_data['max_requests_per_minute'] : 0;
        $monthly_tokens = isset($raw_data['monthly_token_quota']) ? (int) $raw_data['monthly_token_quota'] : 0;
        $monthly_cost = isset($raw_data['monthly_cost_quota_usd']) ? (float) $raw_data['monthly_cost_quota_usd'] : 0;

        $modules = $this->state_manager->get_setting('modules', []);
        if (!is_array($modules)) {
            $modules = [];
        }

        $current = isset($modules['ai']) && is_array($modules['ai']) ? $modules['ai'] : [];
        $modules['ai'] = array_merge($current, [
            'provider' => $provider,
            'model' => $model,
            'policies' => [
                'max_requests_per_minute' => max(0, $max_rpm),
                'monthly_token_quota' => max(0, $monthly_tokens),
                'monthly_cost_quota_usd' => max(0, $monthly_cost),
            ],
        ]);

        $this->state_manager->update_setting('modules', $modules);

        $api_key = isset($raw_data['api_key']) ? sanitize_text_field($raw_data['api_key']) : '';
        if ($api_key !== '') {
            $secure_key = 'api_key_' . $provider;

            if (method_exists($this->state_manager, 'hasSecure') && $this->state_manager->hasSecure('ai', $secure_key)) {
                $this->state_manager->updateSecure('ai', $secure_key, $api_key);
            } else {
                $this->state_manager->setSecure('ai', $secure_key, $api_key);
            }
        }

        $enabled = $this->state_manager->get_state_value('modules_enabled', []);
        if (!is_array($enabled)) {
            $enabled = [];
        }
        $enabled['ai'] = true;
        $this->state_manager->update_state('modules_enabled', $enabled);
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
                $sanitized['api_key'] = isset($data['api_key']) ? sanitize_text_field($data['api_key']) : '';
                $sanitized['api_secret'] = isset($data['api_secret']) ? sanitize_text_field($data['api_secret']) : '';
                break;

            case 'database':
                $sanitized['connection_name'] = isset($data['connection_name']) ? sanitize_text_field($data['connection_name']) : '';
                break;

            case 'payments':
                $sanitized['provider'] = isset($data['provider']) ? sanitize_text_field($data['provider']) : '';
                break;

            case 'ai':
                $sanitized['provider'] = isset($data['provider']) ? sanitize_key($data['provider']) : '';
                $sanitized['model'] = isset($data['model']) ? sanitize_text_field($data['model']) : '';

                $sanitized['max_requests_per_minute'] = isset($data['max_requests_per_minute']) ? (int) $data['max_requests_per_minute'] : 0;
                $sanitized['monthly_token_quota'] = isset($data['monthly_token_quota']) ? (int) $data['monthly_token_quota'] : 0;
                $sanitized['monthly_cost_quota_usd'] = isset($data['monthly_cost_quota_usd']) ? (float) $data['monthly_cost_quota_usd'] : 0;

                // Do not store plaintext API keys in wizard state.
                $sanitized['api_key_set'] = !empty($data['api_key']);
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
