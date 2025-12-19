<?php
/**
 * AI Manager - orchestration layer for providers.
 */

namespace Newera\Modules\AI;

use Newera\Core\Logger;
use Newera\Core\StateManager;
use Newera\Modules\AI\Providers\AnthropicProvider;
use Newera\Modules\AI\Providers\OpenAIProvider;

if (!defined('ABSPATH')) {
    exit;
}

class AIManager {
    /**
     * @var StateManager|null
     */
    private $state_manager;

    /**
     * @var Logger|null
     */
    private $logger;

    /**
     * @var AIUsageTracker
     */
    private $usage_tracker;

    /**
     * @var AICommandBus
     */
    private $command_bus;

    /**
     * @var array<string,array{ name: string, factory: callable }>
     */
    private $providers = [];

    /**
     * @param StateManager|null $state_manager
     * @param Logger|null $logger
     * @param AIUsageTracker|null $usage_tracker
     */
    public function __construct($state_manager = null, $logger = null, $usage_tracker = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : null;
        $this->logger = $logger instanceof Logger ? $logger : null;

        if ($this->state_manager === null && function_exists('apply_filters')) {
            $this->state_manager = apply_filters('newera_get_state_manager', null);
        }

        if ($this->logger === null && function_exists('apply_filters')) {
            $this->logger = apply_filters('newera_get_logger', null);
        }

        $this->usage_tracker = $usage_tracker instanceof AIUsageTracker ? $usage_tracker : new AIUsageTracker($this->state_manager, $this->logger);

        $this->command_bus = new AICommandBus();
        $this->register_default_commands();

        $this->register_builtin_providers();

        if (function_exists('apply_filters')) {
            $this->providers = apply_filters('newera_ai_providers', $this->providers);
        }
    }

    /**
     * @return AICommandBus
     */
    public function commands() {
        return $this->command_bus;
    }

    /**
     * @return AIUsageTracker
     */
    public function usage() {
        return $this->usage_tracker;
    }

    /**
     * @param string $provider_id
     * @param string $name
     * @param callable $factory function(array $config): AIProvider
     */
    public function register_provider($provider_id, $name, $factory) {
        $provider_id = sanitize_key($provider_id);
        if ($provider_id === '' || !is_callable($factory)) {
            return;
        }

        $this->providers[$provider_id] = [
            'name' => (string) $name,
            'factory' => $factory,
        ];
    }

    /**
     * @return array
     */
    public function get_registered_providers() {
        $out = [];
        foreach ($this->providers as $id => $info) {
            $out[$id] = [
                'id' => $id,
                'name' => (string) ($info['name'] ?? $id),
            ];
        }
        return $out;
    }

    /**
     * @param string $provider_id
     * @return AIProvider|\WP_Error
     */
    public function create_provider($provider_id) {
        $provider_id = sanitize_key($provider_id);

        if (!isset($this->providers[$provider_id])) {
            return new \WP_Error('newera_ai_unknown_provider', 'Unknown AI provider.', [
                'provider' => $provider_id,
            ]);
        }

        $factory = $this->providers[$provider_id]['factory'];

        $config = $this->get_provider_config($provider_id);

        $provider = call_user_func($factory, $config);
        if (!$provider instanceof AIProvider) {
            return new \WP_Error('newera_ai_provider_factory', 'Invalid provider factory output.', [
                'provider' => $provider_id,
            ]);
        }

        return $provider;
    }

    /**
     * @param string $provider_id
     * @param string|null $api_key
     * @return array|\WP_Error
     */
    public function list_models($provider_id, $api_key = null) {
        $provider = $this->create_provider($provider_id);
        if (is_wp_error($provider)) {
            return $provider;
        }

        $key = $api_key !== null ? (string) $api_key : $this->get_api_key($provider_id);
        $provider->setApiKey($key);

        $models = $provider->listModels();
        if (is_wp_error($models)) {
            $this->log_warning('AI list models failed', [
                'provider' => $provider_id,
                'error' => $models->get_error_message(),
            ]);
        }

        return $models;
    }

    /**
     * @param array $messages
     * @param array $options
     * @return AIResponse|\WP_Error
     */
    public function chat($messages, $options = []) {
        return $this->run_chat(false, $messages, $options, null);
    }

    /**
     * @param array $messages
     * @param array $options
     * @param callable $on_chunk
     * @return AIResponse|\WP_Error
     */
    public function stream_chat($messages, $options, $on_chunk) {
        return $this->run_chat(true, $messages, $options, $on_chunk);
    }

    /**
     * @param string $command_id
     * @param array $payload
     * @return mixed|\WP_Error
     */
    public function execute($command_id, $payload = []) {
        return $this->command_bus->execute($this, $command_id, $payload);
    }

    /**
     * @return array
     */
    public function get_settings() {
        if (!$this->state_manager) {
            return [];
        }

        $modules = $this->state_manager->get_setting('modules', []);
        if (!is_array($modules)) {
            return [];
        }

        $settings = isset($modules['ai']) && is_array($modules['ai']) ? $modules['ai'] : [];

        return $settings;
    }

    /**
     * @param array $settings
     * @return bool
     */
    public function update_settings($settings) {
        if (!$this->state_manager) {
            return false;
        }

        $settings = is_array($settings) ? $settings : [];

        $modules = $this->state_manager->get_setting('modules', []);
        if (!is_array($modules)) {
            $modules = [];
        }

        $current = isset($modules['ai']) && is_array($modules['ai']) ? $modules['ai'] : [];
        $modules['ai'] = array_merge($current, $settings);

        return $this->state_manager->update_setting('modules', $modules);
    }

    /**
     * @param string $provider_id
     * @param string $api_key
     * @return bool
     */
    public function set_api_key($provider_id, $api_key) {
        if (!$this->state_manager) {
            return false;
        }

        $provider_id = sanitize_key($provider_id);
        $key = 'api_key_' . $provider_id;

        if (method_exists($this->state_manager, 'hasSecure') && $this->state_manager->hasSecure('ai', $key)) {
            return $this->state_manager->updateSecure('ai', $key, $api_key);
        }

        return $this->state_manager->setSecure('ai', $key, $api_key);
    }

    /**
     * @param string $provider_id
     * @return bool
     */
    public function has_api_key($provider_id) {
        if (!$this->state_manager) {
            return false;
        }

        $provider_id = sanitize_key($provider_id);
        return $this->state_manager->hasSecure('ai', 'api_key_' . $provider_id);
    }

    /**
     * @param string $provider_id
     * @return string
     */
    public function get_api_key($provider_id) {
        if (!$this->state_manager) {
            return '';
        }

        $provider_id = sanitize_key($provider_id);
        $key = 'api_key_' . $provider_id;

        $value = $this->state_manager->getSecure('ai', $key, '');
        return is_string($value) ? $value : '';
    }

    /**
     * @param string $provider_id
     * @return bool
     */
    public function delete_api_key($provider_id) {
        if (!$this->state_manager) {
            return false;
        }

        $provider_id = sanitize_key($provider_id);
        return $this->state_manager->deleteSecure('ai', 'api_key_' . $provider_id);
    }

    /**
     * @param string $provider
     * @param string $model
     * @param array $usage
     * @return float|null
     */
    public function calculate_cost_usd($provider, $model, $usage) {
        $settings = $this->get_settings();
        $pricing = isset($settings['pricing']) && is_array($settings['pricing']) ? $settings['pricing'] : [];

        if (empty($pricing[$provider]) || empty($pricing[$provider][$model]) || !is_array($pricing[$provider][$model])) {
            return null;
        }

        $rule = $pricing[$provider][$model];
        $in_per_1k = isset($rule['input_per_1k']) ? (float) $rule['input_per_1k'] : 0;
        $out_per_1k = isset($rule['output_per_1k']) ? (float) $rule['output_per_1k'] : 0;

        $prompt_tokens = (int) ($usage['prompt_tokens'] ?? ($usage['input_tokens'] ?? 0));
        $completion_tokens = (int) ($usage['completion_tokens'] ?? ($usage['output_tokens'] ?? 0));

        if ($in_per_1k <= 0 && $out_per_1k <= 0) {
            return null;
        }

        $cost = 0;
        if ($in_per_1k > 0) {
            $cost += ($prompt_tokens / 1000) * $in_per_1k;
        }
        if ($out_per_1k > 0) {
            $cost += ($completion_tokens / 1000) * $out_per_1k;
        }

        return $cost;
    }

    /**
     * @param bool $stream
     * @param array $messages
     * @param array $options
     * @param callable|null $on_chunk
     * @return AIResponse|\WP_Error
     */
    private function run_chat($stream, $messages, $options, $on_chunk) {
        $settings = $this->get_settings();

        $provider_id = isset($options['provider']) ? sanitize_key($options['provider']) : sanitize_key($settings['provider'] ?? '');
        $fallback_provider_id = sanitize_key($settings['fallback_provider'] ?? '');

        if ($provider_id === '') {
            return new \WP_Error('newera_ai_not_configured', 'AI provider not configured.');
        }

        $model = isset($options['model']) ? (string) $options['model'] : (string) ($settings['model'] ?? '');
        if ($model === '') {
            return new \WP_Error('newera_ai_not_configured', 'AI model not configured.');
        }

        $policies = isset($settings['policies']) && is_array($settings['policies']) ? $settings['policies'] : [];

        $policy_ok = $this->usage_tracker->enforce_policies($policies, $provider_id, $model);
        if (is_wp_error($policy_ok)) {
            return $policy_ok;
        }

        $start = microtime(true);

        $response = $this->invoke_provider($provider_id, $model, $messages, $options, $stream, $on_chunk);

        if (is_wp_error($response) && $fallback_provider_id !== '' && $fallback_provider_id !== $provider_id) {
            $this->log_warning('AI provider failed, attempting fallback', [
                'provider' => $provider_id,
                'fallback' => $fallback_provider_id,
                'error' => $response->get_error_message(),
            ]);

            $response = $this->invoke_provider($fallback_provider_id, $model, $messages, $options, $stream, $on_chunk);
        }

        $duration_ms = (int) round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            $this->usage_tracker->record_event([
                'timestamp' => function_exists('current_time') ? current_time('mysql') : gmdate('c'),
                'provider' => $provider_id,
                'model' => $model,
                'command' => $stream ? 'stream_chat' : 'chat',
                'status' => 'error',
                'error' => $response->get_error_message(),
                'duration_ms' => $duration_ms,
            ]);

            return $response;
        }

        $usage = is_array($response->usage) ? $response->usage : [];
        $cost = $this->calculate_cost_usd($response->provider, $response->model, $usage);
        $response->cost_usd = $cost;

        $prompt_tokens = (int) ($usage['prompt_tokens'] ?? ($usage['input_tokens'] ?? 0));
        $completion_tokens = (int) ($usage['completion_tokens'] ?? ($usage['output_tokens'] ?? 0));
        $total_tokens = (int) ($usage['total_tokens'] ?? ($prompt_tokens + $completion_tokens));

        $this->usage_tracker->record_event([
            'timestamp' => function_exists('current_time') ? current_time('mysql') : gmdate('c'),
            'provider' => $response->provider,
            'model' => $response->model,
            'command' => $stream ? 'stream_chat' : 'chat',
            'status' => 'success',
            'prompt_tokens' => $prompt_tokens,
            'completion_tokens' => $completion_tokens,
            'total_tokens' => $total_tokens,
            'cost_usd' => $cost,
            'duration_ms' => $duration_ms,
        ]);

        $post_policy = $this->usage_tracker->enforce_policies($policies, $response->provider, $response->model);
        if (is_wp_error($post_policy)) {
            $this->log_warning('AI quotas reached after request', [
                'provider' => $response->provider,
                'model' => $response->model,
                'error' => $post_policy->get_error_message(),
            ]);
        }

        return $response;
    }

    /**
     * @param string $provider_id
     * @param string $model
     * @param array $messages
     * @param array $options
     * @param bool $stream
     * @param callable|null $on_chunk
     * @return AIResponse|\WP_Error
     */
    private function invoke_provider($provider_id, $model, $messages, $options, $stream, $on_chunk) {
        $provider = $this->create_provider($provider_id);
        if (is_wp_error($provider)) {
            return $provider;
        }

        $api_key = $this->get_api_key($provider_id);
        if ($api_key === '') {
            return new \WP_Error('newera_ai_not_configured', 'API key not configured for selected provider.', [
                'provider' => $provider_id,
            ]);
        }

        $provider->setApiKey($api_key);

        $options = is_array($options) ? $options : [];
        $options['model'] = $model;

        try {
            if ($stream) {
                return $provider->streamChat($messages, $options, $on_chunk);
            }

            return $provider->chat($messages, $options);
        } catch (\Throwable $e) {
            $this->log_error('AI provider exception', [
                'provider' => $provider_id,
                'error' => $e->getMessage(),
            ]);

            return new \WP_Error('newera_ai_provider_exception', 'AI provider error.');
        }
    }

    /**
     * @param string $provider_id
     * @return array
     */
    private function get_provider_config($provider_id) {
        $settings = $this->get_settings();
        $config = [];

        if (!empty($settings['providers']) && is_array($settings['providers']) && !empty($settings['providers'][$provider_id])) {
            $config = is_array($settings['providers'][$provider_id]) ? $settings['providers'][$provider_id] : [];
        }

        return $config;
    }

    private function register_builtin_providers() {
        $this->register_provider('openai', 'OpenAI', function($config) {
            $base_url = isset($config['base_url']) ? (string) $config['base_url'] : 'https://api.openai.com/v1';
            return new OpenAIProvider($base_url);
        });

        $this->register_provider('anthropic', 'Anthropic', function($config) {
            $base_url = isset($config['base_url']) ? (string) $config['base_url'] : 'https://api.anthropic.com/v1';
            $version = isset($config['version']) ? (string) $config['version'] : '2023-06-01';
            return new AnthropicProvider($base_url, $version);
        });
    }

    private function register_default_commands() {
        if (!class_exists('\\Newera\\Modules\\AI\\Commands\\ChatCommand')) {
            return;
        }

        $this->command_bus->register(new \Newera\Modules\AI\Commands\ChatCommand());
        $this->command_bus->register(new \Newera\Modules\AI\Commands\StreamChatCommand());
    }

    /**
     * @param string $message
     * @param array $context
     */
    private function log_warning($message, $context = []) {
        if ($this->logger) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * @param string $message
     * @param array $context
     */
    private function log_error($message, $context = []) {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
}
