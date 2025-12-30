<?php
/**
 * AI Module.
 */

namespace Newera\Modules\AI;

use Newera\Modules\BaseModule;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/AIProvider.php';
require_once __DIR__ . '/AIUsageTracker.php';
require_once __DIR__ . '/AICommandInterface.php';
require_once __DIR__ . '/AICommandBus.php';
require_once __DIR__ . '/AIManager.php';
require_once __DIR__ . '/Providers/OpenAIProvider.php';
require_once __DIR__ . '/Providers/AnthropicProvider.php';
require_once __DIR__ . '/Commands/ChatCommand.php';
require_once __DIR__ . '/Commands/StreamChatCommand.php';

class AIModule extends BaseModule {
    /**
     * @var AIManager|null
     */
    private $ai_manager;

    /**
     * @var AIUsageTracker|null
     */
    private $usage_tracker;

    /**
     * @return string
     */
    public function getId() {
        return 'ai';
    }

    /**
     * @return string
     */
    public function getName() {
        return 'AI';
    }

    /**
     * @return string
     */
    public function getDescription() {
        return 'AI provider integration (OpenAI, Anthropic, and pluggable providers) with usage tracking and quotas.';
    }

    /**
     * @return string
     */
    public function getType() {
        return 'ai';
    }

    /**
     * @return array
     */
    public function getSettingsSchema() {
        return [
            'credentials' => [
                'api_key_openai' => [
                    'type' => 'string',
                    'label' => 'OpenAI API Key',
                    'secure' => true,
                ],
                'api_key_anthropic' => [
                    'type' => 'string',
                    'label' => 'Anthropic API Key',
                    'secure' => true,
                ],
            ],
            'settings' => [
                'provider' => [
                    'type' => 'string',
                    'label' => 'Provider',
                ],
                'model' => [
                    'type' => 'string',
                    'label' => 'Model',
                ],
            ],
        ];
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        $settings = $this->get_module_settings();
        $provider = isset($settings['provider']) ? sanitize_key($settings['provider']) : '';
        $model = isset($settings['model']) ? (string) $settings['model'] : '';

        if ($provider === '' || $model === '') {
            return false;
        }

        return $this->has_credential('api_key_' . $provider);
    }

    /**
     * Boot module.
     */
    public function boot() {
        $this->registerAdminHooks();
        $this->expose_services();
        parent::boot();
    }

    /**
     * Runtime hooks when module is active.
     */
    public function registerHooks() {
        // Placeholder for future REST endpoints/cron, etc.
    }

    /**
     * Admin hooks should always be available so the installer can configure AI.
     */
    private function registerAdminHooks() {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'register_admin_page'], 35);

        add_action('wp_ajax_newera_ai_list_models', [$this, 'ajax_list_models']);
        add_action('wp_ajax_newera_ai_reset_usage', [$this, 'ajax_reset_usage']);
    }

    /**
     * Expose manager via filters.
     */
    private function expose_services() {
        add_filter('newera_get_ai_manager', function() {
            return $this->get_ai_manager();
        });

        add_filter('newera_get_ai_usage_tracker', function() {
            return $this->get_usage_tracker();
        });
    }

    /**
     * @return AIManager
     */
    public function get_ai_manager() {
        if (!$this->ai_manager) {
            $this->ai_manager = new AIManager($this->state_manager, $this->logger, $this->get_usage_tracker());
        }

        return $this->ai_manager;
    }

    /**
     * @return AIUsageTracker
     */
    public function get_usage_tracker() {
        if (!$this->usage_tracker) {
            $this->usage_tracker = new AIUsageTracker($this->state_manager, $this->logger);
        }

        return $this->usage_tracker;
    }

    /**
     * Register Newera > AI submenu page.
     */
    public function register_admin_page() {
        add_submenu_page(
            'newera',
            __('AI', 'newera'),
            __('AI', 'newera'),
            'manage_options',
            'newera-ai',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Render and handle AI settings.
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        $notice = null;

        if (isset($_POST['newera_ai_save_settings'])) {
            $nonce = isset($_POST['newera_ai_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['newera_ai_settings_nonce'])) : '';

            if (!wp_verify_nonce($nonce, 'newera_ai_save_settings')) {
                $notice = [
                    'type' => 'error',
                    'message' => __('Security check failed.', 'newera'),
                ];
            } else {
                $notice = $this->handle_admin_save($_POST);
            }
        }

        $ai = $this->get_ai_manager();
        $settings = $ai->get_settings();

        $template_data = [
            'notice' => $notice,
            'providers' => $ai->get_registered_providers(),
            'settings' => $settings,
            'has_openai_key' => $ai->has_api_key('openai'),
            'has_anthropic_key' => $ai->has_api_key('anthropic'),
            'monthly_totals' => $ai->usage()->get_monthly_totals(),
            'recent_events' => array_slice($ai->usage()->get_recent_events(), 0, 20),
        ];

        include NEWERA_PLUGIN_PATH . 'templates/admin/ai.php';
    }

    /**
     * @param array $post
     * @return array
     */
    private function handle_admin_save($post) {
        $ai = $this->get_ai_manager();

        $provider = isset($post['provider']) ? sanitize_key(wp_unslash($post['provider'])) : '';
        $model = isset($post['model']) ? sanitize_text_field(wp_unslash($post['model'])) : '';
        $fallback = isset($post['fallback_provider']) ? sanitize_key(wp_unslash($post['fallback_provider'])) : '';

        $max_rpm = isset($post['max_requests_per_minute']) ? (int) $post['max_requests_per_minute'] : 0;
        $monthly_tokens = isset($post['monthly_token_quota']) ? (int) $post['monthly_token_quota'] : 0;
        $monthly_cost = isset($post['monthly_cost_quota_usd']) ? (float) $post['monthly_cost_quota_usd'] : 0;

        $pricing_in = isset($post['pricing_input_per_1k']) ? (float) $post['pricing_input_per_1k'] : 0;
        $pricing_out = isset($post['pricing_output_per_1k']) ? (float) $post['pricing_output_per_1k'] : 0;

        $provider_config = [
            'provider' => $provider,
            'model' => $model,
            'fallback_provider' => $fallback,
            'policies' => [
                'max_requests_per_minute' => max(0, $max_rpm),
                'monthly_token_quota' => max(0, $monthly_tokens),
                'monthly_cost_quota_usd' => max(0, $monthly_cost),
            ],
        ];

        if ($provider !== '' && $model !== '' && ($pricing_in > 0 || $pricing_out > 0)) {
            $existing = $ai->get_settings();
            $pricing = isset($existing['pricing']) && is_array($existing['pricing']) ? $existing['pricing'] : [];

            if (!isset($pricing[$provider]) || !is_array($pricing[$provider])) {
                $pricing[$provider] = [];
            }

            $pricing[$provider][$model] = [
                'input_per_1k' => max(0, $pricing_in),
                'output_per_1k' => max(0, $pricing_out),
            ];

            $provider_config['pricing'] = $pricing;
        }

        $saved = $ai->update_settings($provider_config);

        if ($provider !== '') {
            $api_key = isset($post['api_key']) ? sanitize_text_field(wp_unslash($post['api_key'])) : '';
            if ($api_key !== '') {
                $ai->set_api_key($provider, $api_key);
            }
        }

        if (isset($post['delete_api_key']) && $provider !== '') {
            $delete_flag = sanitize_text_field(wp_unslash($post['delete_api_key']));
            if ($delete_flag === '1') {
                $ai->delete_api_key($provider);
            }
        }

        if (!$saved) {
            return [
                'type' => 'error',
                'message' => __('Failed to save AI settings. Please ensure StateManager is available.', 'newera'),
            ];
        }

        $this->maybe_enable_module_in_state();

        return [
            'type' => 'success',
            'message' => __('AI settings saved.', 'newera'),
        ];
    }

    /**
     * Ensure module is enabled for the module registry.
     */
    private function maybe_enable_module_in_state() {
        if (!$this->state_manager) {
            return;
        }

        $enabled = $this->state_manager->get_state_value('modules_enabled', []);
        if (!is_array($enabled)) {
            $enabled = [];
        }

        $enabled[$this->getId()] = true;
        $this->state_manager->update_state('modules_enabled', $enabled);
    }

    /**
     * AJAX: list models for provider.
     */
    public function ajax_list_models() {
        check_ajax_referer('newera_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'newera'), 403);
        }

        $provider = isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

        if ($provider === '') {
            wp_send_json_error(__('Provider is required.', 'newera'), 400);
        }

        $models = $this->get_ai_manager()->list_models($provider, $api_key !== '' ? $api_key : null);

        if (is_wp_error($models)) {
            wp_send_json_error($models->get_error_message(), 400);
        }

        wp_send_json_success([
            'models' => $models,
        ]);
    }

    /**
     * AJAX: reset usage.
     */
    public function ajax_reset_usage() {
        check_ajax_referer('newera_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'newera'), 403);
        }

        $ok = $this->get_usage_tracker()->reset_usage();

        if (!$ok) {
            wp_send_json_error(__('Failed to reset usage.', 'newera'), 500);
        }

        wp_send_json_success([
            'message' => __('Usage reset.', 'newera'),
        ]);
    }
}

// Global helper functions in root namespace
if (!function_exists('newera_get_ai_manager')) {
    /**
     * @return \Newera\Modules\AI\AIManager|null
     */
    function newera_get_ai_manager() {
        if (!function_exists('apply_filters')) {
            return null;
        }

        return apply_filters('newera_get_ai_manager', null);
    }
}

if (!function_exists('newera_ai_execute')) {
    /**
     * Execute an internal AI command via the AI command bus.
     *
     * @param string $command_id
     * @param array $payload
     * @return mixed|\WP_Error
     */
    function newera_ai_execute($command_id, $payload = []) {
        $ai = newera_get_ai_manager();
        if (!$ai) {
            return new \WP_Error('newera_ai_unavailable', 'AI manager unavailable.');
        }

        return $ai->execute($command_id, $payload);
    }
}

if (!function_exists('newera_ai_chat')) {
    /**
     * Convenience wrapper for a chat request.
     *
     * @param array $messages
     * @param array $options
     * @return mixed|\WP_Error
     */
    function newera_ai_chat($messages, $options = []) {
        $ai = newera_get_ai_manager();
        if (!$ai) {
            return new \WP_Error('newera_ai_unavailable', 'AI manager unavailable.');
        }

        return $ai->chat($messages, $options);
    }
}
