<?php
/**
 * AI Provider interfaces for Newera.
 */

namespace Newera\Modules\AI;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface AIProvider
 */
interface AIProvider {
    /**
     * Provider id (e.g. "openai").
     *
     * @return string
     */
    public function getId();

    /**
     * Human-friendly name.
     *
     * @return string
     */
    public function getName();

    /**
     * Configure provider with an installer-provided API key.
     *
     * @param string $api_key
     */
    public function setApiKey($api_key);

    /**
     * @return bool
     */
    public function isConfigured();

    /**
     * List available models.
     *
     * @return array|\WP_Error Array of models (each item: id, label, raw).
     */
    public function listModels();

    /**
     * Run a chat request.
     *
     * @param array $messages OpenAI-style messages: [ [role=>user|assistant|system, content=>string], ... ]
     * @param array $options Provider-specific options (model, temperature, max_tokens, etc)
     * @return AIResponse|\WP_Error
     */
    public function chat($messages, $options = []);

    /**
     * Stream a chat response.
     *
     * Implementations should invoke $on_chunk with partial text as it arrives.
     *
     * @param array $messages
     * @param array $options
     * @param callable $on_chunk function(string $delta, array $event): void
     * @return AIResponse|\WP_Error
     */
    public function streamChat($messages, $options, $on_chunk);
}

/**
 * Value object for provider responses.
 */
class AIResponse {
    /** @var string */
    public $text;

    /** @var array */
    public $usage;

    /** @var string */
    public $model;

    /** @var string */
    public $provider;

    /** @var float|null */
    public $cost_usd;

    /** @var array */
    public $raw;

    /**
     * @param string $provider
     * @param string $model
     * @param string $text
     * @param array $usage
     * @param array $raw
     * @param float|null $cost_usd
     */
    public function __construct($provider, $model, $text, $usage = [], $raw = [], $cost_usd = null) {
        $this->provider = (string) $provider;
        $this->model = (string) $model;
        $this->text = (string) $text;
        $this->usage = is_array($usage) ? $usage : [];
        $this->raw = is_array($raw) ? $raw : [];
        $this->cost_usd = $cost_usd !== null ? (float) $cost_usd : null;
    }

    /**
     * @return array
     */
    public function toArray() {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'text' => $this->text,
            'usage' => $this->usage,
            'cost_usd' => $this->cost_usd,
            'raw' => $this->raw,
        ];
    }
}

/**
 * Shared HTTP helpers for providers.
 */
abstract class AbstractAIProvider implements AIProvider {
    /**
     * @var string
     */
    protected $api_key = '';

    /**
     * @var string
     */
    protected $base_url = '';

    /**
     * @param string $base_url
     */
    public function __construct($base_url = '') {
        $this->base_url = (string) $base_url;
    }

    /**
     * @param string $api_key
     */
    public function setApiKey($api_key) {
        $this->api_key = trim((string) $api_key);
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        return $this->api_key !== '';
    }

    /**
     * @param string $path
     * @return string
     */
    protected function url($path) {
        return rtrim($this->base_url, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $args
     * @return array|\WP_Error
     */
    protected function request($method, $url, $args = []) {
        if (!function_exists('wp_remote_request')) {
            return new \WP_Error('newera_ai_http', 'WordPress HTTP API unavailable.');
        }

        $defaults = [
            'timeout' => 30,
            'redirection' => 2,
            'headers' => [],
        ];

        $args = array_merge($defaults, is_array($args) ? $args : []);
        $args['method'] = strtoupper($method);

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $json = null;
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }

        return [
            'code' => $code,
            'body' => $body,
            'json' => $json,
            'headers' => wp_remote_retrieve_headers($response),
        ];
    }

    /**
     * @param string $provider
     * @param array $payload
     * @return \WP_Error
     */
    protected function error_from_api($provider, $payload) {
        $message = 'AI provider error.';

        if (is_array($payload)) {
            if (!empty($payload['error']['message'])) {
                $message = (string) $payload['error']['message'];
            } elseif (!empty($payload['message'])) {
                $message = (string) $payload['message'];
            } elseif (!empty($payload['error'])) {
                $message = is_string($payload['error']) ? $payload['error'] : wp_json_encode($payload['error']);
            }
        }

        return new \WP_Error('newera_ai_provider_error', $message, [
            'provider' => $provider,
            'payload' => $payload,
        ]);
    }
}
