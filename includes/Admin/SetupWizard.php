<?php
/**
 * Setup Wizard orchestrator for Newera Plugin
 *
 * Multi-step onboarding wizard (server-rendered) that stores non-sensitive state
 * and lets modules persist secrets via StateManager secure storage.
 */

namespace Newera\Admin;

use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

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
     * @var StateManager
     */
    private $state_manager;

    /**
     * @var array<string,array{title:string,description:string}>
     */
    private $steps;

    /**
     * @param StateManager|null $state_manager
     */
    public function __construct($state_manager = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : new StateManager();

        $this->steps = [
            'intro' => [
                'title' => __('Intro', 'newera'),
                'description' => __('Welcome to Newera. This wizard will guide you through a basic configuration.', 'newera'),
            ],
            'auth' => [
                'title' => __('Authentication', 'newera'),
                'description' => __('Choose authentication providers and store OAuth credentials securely.', 'newera'),
            ],
            'database' => [
                'title' => __('Database', 'newera'),
                'description' => __('Choose WordPress DB or an external database and test connectivity.', 'newera'),
            ],
            'payments' => [
                'title' => __('Payments', 'newera'),
                'description' => __('Configure Stripe and optionally create sample plans.', 'newera'),
            ],
            'ai' => [
                'title' => __('AI', 'newera'),
                'description' => __('Configure your AI provider and store an API key securely.', 'newera'),
            ],
            'integrations' => [
                'title' => __('Integrations', 'newera'),
                'description' => __('Optional: configure Linear and Notion credentials for sync.', 'newera'),
            ],
            'review' => [
                'title' => __('Review & Confirm', 'newera'),
                'description' => __('Review settings, validate required modules, and complete setup.', 'newera'),
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
        add_action('wp_ajax_newera_test_db_connection', [$this, 'ajax_test_db_connection']);
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
        if ($page === '' || strpos($page, 'newera') !== 0) {
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

        if (!$this->is_step_accessible($current_step, $wizard_state) && !$revisit) {
            $current_step = $wizard_state['current_step'] ?? 'intro';
        }

        $step_context = apply_filters('newera_setup_wizard_step_context', [], $current_step, $this->state_manager);

        $template_data = [
            'steps' => $this->steps,
            'wizard_state' => $wizard_state,
            'current_step' => $current_step,
            'revisit' => $revisit,
            'notice' => $notice,
            'wizard_url' => $this->get_wizard_url(),
            'step_context' => is_array($step_context) ? $step_context : [],
        ];

        include NEWERA_PLUGIN_PATH . 'templates/admin/setup-wizard.php';
    }

    /**
     * Handle POST submissions (non-AJAX).
     *
     * @param array $post
     * @return string|\WP_Error
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
     * @param array $post
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
     * AJAX: save a step.
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
     * AJAX: reset wizard.
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
     * AJAX: test database connection.
     */
    public function ajax_test_db_connection() {
        check_ajax_referer('newera_setup_wizard_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'newera'), 403);
        }

        $connection_string = isset($_POST['connection_string']) ? sanitize_text_field(wp_unslash($_POST['connection_string'])) : '';
        if ($connection_string === '') {
            wp_send_json_error(__('Connection string is required.', 'newera'), 400);
        }

        $db_factory = apply_filters('newera_get_db_factory', null);
        if (!$db_factory) {
            wp_send_json_error(__('Database factory not available.', 'newera'), 500);
        }

        $result = $db_factory->test_connection($connection_string);

        if (!empty($result['success'])) {
            wp_send_json_success($result);
        }

        wp_send_json_error($result['message'] ?? __('Connection failed.', 'newera'), 400);
    }

    /**
     * Save step data and return next step.
     *
     * @param string $step
     * @param array $data
     * @return string
     */
    private function save_step($step, $data) {
        $wizard_state = $this->get_wizard_state();

        $sanitized = $this->sanitize_step_data($step, $data);

        do_action('newera_setup_wizard_step_before_store', $step, $sanitized, $this->state_manager);

        $wizard_state['data'][$step] = $this->mask_sensitive_step_data($step, $sanitized);

        if (!in_array($step, $wizard_state['completed_steps'], true)) {
            $wizard_state['completed_steps'][] = $step;
        }

        $wizard_state['current_step'] = $this->get_next_step($step);
        $wizard_state['last_updated'] = current_time('mysql');

        if ($step === 'review') {
            $wizard_state['completed'] = true;
            $wizard_state['completed_at'] = current_time('mysql');
            $wizard_state['current_step'] = 'review';

            $this->trigger_setup_completion($wizard_state);
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
     * @param array $sanitized
     */
    private function apply_step_side_effects($step, $raw_data, $sanitized) {
        if ($step === 'database') {
            $db_type = isset($sanitized['db_type']) ? $sanitized['db_type'] : 'wordpress';

            if ($db_type === 'external' && !empty($sanitized['connection_string'])) {
                $db_factory = apply_filters('newera_get_db_factory', null);
                if ($db_factory) {
                    $db_factory->save_configuration($sanitized);
                    $db_factory->run_external_migrations();
                }
            }
        }

        if ($step === 'payments') {
            $provider = isset($sanitized['provider']) ? $sanitized['provider'] : '';
            if ($provider === 'stripe') {
                $api_key = isset($raw_data['stripe_api_key']) ? sanitize_text_field($raw_data['stripe_api_key']) : '';
                $public_key = isset($raw_data['stripe_public_key']) ? sanitize_text_field($raw_data['stripe_public_key']) : '';
                $webhook_secret = isset($raw_data['stripe_webhook_secret']) ? sanitize_text_field($raw_data['stripe_webhook_secret']) : '';
                $mode = isset($sanitized['stripe_mode']) ? $sanitized['stripe_mode'] : 'test';

                if (class_exists('\\Newera\\Payments\\StripeManager')) {
                    $stripe = new \Newera\Payments\StripeManager($this->state_manager);
                    if ($api_key !== '' && $public_key !== '') {
                        $stripe->set_credentials($api_key, $public_key, $webhook_secret, $mode);
                    }
                }

                $this->enable_module_in_state('payments');

                if (!empty($sanitized['create_sample_plans']) && class_exists('\\Newera\\Payments\\PlanManager')) {
                    $plan_manager = new \Newera\Payments\PlanManager($this->state_manager);
                    $existing = $plan_manager->get_all_plans();

                    if (empty($existing)) {
                        $plan_manager->create_plan([
                            'id' => 'starter',
                            'name' => 'Starter',
                            'description' => 'Sample plan created by Setup Wizard.',
                            'amount' => 499,
                            'currency' => 'inr',
                            'interval' => 'month',
                            'interval_count' => 1,
                        ]);

                        $plan_manager->create_plan([
                            'id' => 'pro',
                            'name' => 'Pro',
                            'description' => 'Sample plan created by Setup Wizard.',
                            'amount' => 1999,
                            'currency' => 'inr',
                            'interval' => 'month',
                            'interval_count' => 1,
                        ]);
                    }
                }
            }
        }

        if ($step === 'ai') {
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
                if ($this->state_manager->hasSecure('ai', $secure_key)) {
                    $this->state_manager->updateSecure('ai', $secure_key, $api_key);
                } else {
                    $this->state_manager->setSecure('ai', $secure_key, $api_key);
                }
            }

            $this->enable_module_in_state('ai');
        }

        if ($step === 'integrations') {
            $this->persist_integrations_credentials($raw_data);
        }
    }

    /**
     * Reset the wizard state.
     */
    private function reset_wizard() {
        $this->state_manager->update_state(self::STATE_KEY, $this->get_default_wizard_state());
    }

    /**
     * @return bool
     */
    private function is_completed() {
        $wizard_state = $this->get_wizard_state();
        return !empty($wizard_state['completed']);
    }

    /**
     * @return array
     */
    private function get_wizard_state() {
        $state = $this->state_manager->get_state_value(self::STATE_KEY, []);

        return array_merge($this->get_default_wizard_state(), is_array($state) ? $state : []);
    }

    /**
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
     * @param string $step
     * @param array $wizard_state
     * @return bool
     */
    private function is_step_accessible($step, $wizard_state) {
        if (!empty($wizard_state['completed'])) {
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
     * @param string $step
     * @return string
     */
    private function get_next_step($step) {
        $step_ids = array_keys($this->steps);
        $idx = array_search($step, $step_ids, true);

        if ($idx === false) {
            return 'intro';
        }

        return isset($step_ids[$idx + 1]) ? $step_ids[$idx + 1] : $step;
    }

    /**
     * @param array $args
     * @return string
     */
    public function get_wizard_url($args = []) {
        $base = admin_url('admin.php?page=' . self::PAGE_SLUG);
        return empty($args) ? $base : add_query_arg($args, $base);
    }

    /**
     * Extract step data from a request.
     *
     * @param array $request
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
     * @param string $step
     * @param array $data
     * @return array
     */
    private function sanitize_step_data($step, $data) {
        $data = is_array($data) ? $data : [];

        switch ($step) {
            case 'intro':
                return [
                    'site_label' => isset($data['site_label']) ? sanitize_text_field($data['site_label']) : '',
                ];

            case 'auth':
                $supported = ['email', 'magic_link', 'google', 'apple', 'github'];

                $enabled = isset($data['providers_enabled']) && is_array($data['providers_enabled']) ? $data['providers_enabled'] : [];
                $enabled = array_values(array_filter(array_map('sanitize_key', $enabled)));
                $enabled = array_values(array_intersect($enabled, $supported));

                $providers = [];
                $raw_providers = isset($data['providers']) && is_array($data['providers']) ? $data['providers'] : [];

                foreach ($supported as $provider) {
                    $p = isset($raw_providers[$provider]) && is_array($raw_providers[$provider]) ? $raw_providers[$provider] : [];

                    $providers[$provider] = [
                        'client_id' => isset($p['client_id']) ? sanitize_text_field($p['client_id']) : '',
                        'client_secret' => isset($p['client_secret']) ? sanitize_text_field($p['client_secret']) : '',
                    ];
                }

                return [
                    'providers_enabled' => $enabled,
                    'providers' => $providers,
                ];

            case 'database':
                return [
                    'db_type' => isset($data['db_type']) ? sanitize_key($data['db_type']) : 'wordpress',
                    'connection_string' => isset($data['connection_string']) ? sanitize_textarea_field($data['connection_string']) : '',
                    'table_prefix' => isset($data['table_prefix']) ? sanitize_text_field($data['table_prefix']) : 'wp_',
                    'persistent' => !empty($data['persistent']),
                ];

            case 'payments':
                return [
                    'provider' => isset($data['provider']) ? sanitize_key($data['provider']) : '',
                    'stripe_mode' => isset($data['stripe_mode']) ? sanitize_key($data['stripe_mode']) : 'test',
                    'create_sample_plans' => !empty($data['create_sample_plans']),
                    'stripe_api_key_set' => !empty($data['stripe_api_key']),
                    'stripe_public_key_set' => !empty($data['stripe_public_key']),
                    'stripe_webhook_secret_set' => !empty($data['stripe_webhook_secret']),
                ];

            case 'ai':
                return [
                    'provider' => isset($data['provider']) ? sanitize_key($data['provider']) : '',
                    'model' => isset($data['model']) ? sanitize_text_field($data['model']) : '',
                    'max_requests_per_minute' => isset($data['max_requests_per_minute']) ? (int) $data['max_requests_per_minute'] : 0,
                    'monthly_token_quota' => isset($data['monthly_token_quota']) ? (int) $data['monthly_token_quota'] : 0,
                    'monthly_cost_quota_usd' => isset($data['monthly_cost_quota_usd']) ? (float) $data['monthly_cost_quota_usd'] : 0,
                    'api_key_set' => !empty($data['api_key']),
                ];

            case 'integrations':
                return [
                    'linear_team_id' => isset($data['linear_team_id']) ? sanitize_text_field($data['linear_team_id']) : '',
                    'notion_projects_database_id' => isset($data['notion_projects_database_id']) ? sanitize_text_field($data['notion_projects_database_id']) : '',
                    'linear_api_key_set' => !empty($data['linear_api_key']),
                    'linear_webhook_secret_set' => !empty($data['linear_webhook_secret']),
                    'notion_api_key_set' => !empty($data['notion_api_key']),
                    'notion_webhook_secret_set' => !empty($data['notion_webhook_secret']),
                ];

            case 'review':
                return [
                    'confirmed' => !empty($data['confirmed']),
                ];

            default:
                $out = [];
                foreach ($data as $key => $value) {
                    $out[$key] = is_string($value) ? sanitize_text_field($value) : $value;
                }
                return $out;
        }
    }

    /**
     * Mask (remove) sensitive data before persisting wizard state.
     *
     * @param string $step
     * @param array $data
     * @return array
     */
    private function mask_sensitive_step_data($step, $data) {
        if (!is_array($data)) {
            return [];
        }

        if ($step === 'auth') {
            $masked = [
                'providers_enabled' => isset($data['providers_enabled']) && is_array($data['providers_enabled']) ? $data['providers_enabled'] : [],
                'providers' => [],
            ];

            if (isset($data['providers']) && is_array($data['providers'])) {
                foreach ($data['providers'] as $provider => $provider_data) {
                    $client_id = isset($provider_data['client_id']) ? (string) $provider_data['client_id'] : '';
                    $client_secret = isset($provider_data['client_secret']) ? (string) $provider_data['client_secret'] : '';

                    $masked['providers'][sanitize_key($provider)] = [
                        'client_id_last4' => $client_id !== '' ? substr($client_id, -4) : '',
                        'client_id_provided' => $client_id !== '',
                        'client_secret_provided' => $client_secret !== '',
                    ];
                }
            }

            return $masked;
        }

        // payments/integrations/ai already contain only boolean flags.
        return $data;
    }

    /**
     * Persist integration credentials from the integrations step.
     *
     * @param array $data Raw request data.
     */
    private function persist_integrations_credentials($data) {
        $data = is_array($data) ? $data : [];

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
     * Trigger setup completion hooks.
     *
     * @param array $wizard_state
     */
    private function trigger_setup_completion($wizard_state) {
        // Flush rewrite rules after enabling webhook endpoints (best-effort).
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }

        do_action('newera_setup_wizard_completed', $wizard_state, $this->state_manager);
    }

    /**
     * Enable a module id within plugin state.
     *
     * @param string $module_id
     */
    private function enable_module_in_state($module_id) {
        $module_id = sanitize_key($module_id);
        if ($module_id === '') {
            return;
        }

        $enabled = $this->state_manager->get_state_value('modules_enabled', []);
        if (!is_array($enabled)) {
            $enabled = [];
        }

        $enabled[$module_id] = true;
        $this->state_manager->update_state('modules_enabled', $enabled);
    }
}
