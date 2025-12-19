<?php
/**
 * Anthropic provider implementation.
 */

namespace Newera\Modules\AI\Providers;

use Newera\Modules\AI\AbstractAIProvider;
use Newera\Modules\AI\AIResponse;

if (!defined('ABSPATH')) {
    exit;
}

class AnthropicProvider extends AbstractAIProvider {
    /**
     * @var string
     */
    private $version;

    /**
     * @param string $base_url
     * @param string $version
     */
    public function __construct($base_url = 'https://api.anthropic.com/v1', $version = '2023-06-01') {
        parent::__construct($base_url);
        $this->version = (string) $version;
    }

    /**
     * @return string
     */
    public function getId() {
        return 'anthropic';
    }

    /**
     * @return string
     */
    public function getName() {
        return 'Anthropic';
    }

    /**
     * @return array|\WP_Error
     */
    public function listModels() {
        if (!$this->isConfigured()) {
            return new \WP_Error('newera_ai_not_configured', 'Anthropic API key not configured.');
        }

        $res = $this->request('GET', $this->url('/models'), [
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => $this->version,
                'Content-Type' => 'application/json',
            ],
        ]);

        if (is_wp_error($res)) {
            return $res;
        }

        if ($res['code'] < 200 || $res['code'] >= 300) {
            return $this->error_from_api($this->getId(), $res['json'] ? $res['json'] : ['message' => $res['body']]);
        }

        $models = [];
        $data = isset($res['json']['data']) && is_array($res['json']['data']) ? $res['json']['data'] : [];
        foreach ($data as $item) {
            if (!is_array($item) || empty($item['id'])) {
                continue;
            }

            $label = !empty($item['display_name']) ? (string) $item['display_name'] : (string) $item['id'];

            $models[] = [
                'id' => (string) $item['id'],
                'label' => $label,
                'raw' => $item,
            ];
        }

        usort($models, function($a, $b) {
            return strcmp($a['id'], $b['id']);
        });

        return $models;
    }

    /**
     * @param array $messages
     * @return array
     */
    private function split_messages($messages) {
        $system = '';
        $anthropic_messages = [];

        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }

            $role = isset($msg['role']) ? (string) $msg['role'] : 'user';
            $content = isset($msg['content']) ? (string) $msg['content'] : '';

            if ($role === 'system') {
                $system .= ($system !== '' ? "\n" : '') . $content;
                continue;
            }

            if ($role !== 'user' && $role !== 'assistant') {
                $role = 'user';
            }

            $anthropic_messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return [$system, $anthropic_messages];
    }

    /**
     * @param array $messages
     * @param array $options
     * @return AIResponse|\WP_Error
     */
    public function chat($messages, $options = []) {
        if (!$this->isConfigured()) {
            return new \WP_Error('newera_ai_not_configured', 'Anthropic API key not configured.');
        }

        $model = isset($options['model']) ? (string) $options['model'] : '';
        if ($model === '') {
            return new \WP_Error('newera_ai_invalid_model', 'Model is required.');
        }

        list($system, $anthropic_messages) = $this->split_messages(is_array($messages) ? $messages : []);

        $payload = [
            'model' => $model,
            'messages' => $anthropic_messages,
            'max_tokens' => isset($options['max_tokens']) ? (int) $options['max_tokens'] : 1024,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        $res = $this->request('POST', $this->url('/messages'), [
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => $this->version,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($res)) {
            return $res;
        }

        if ($res['code'] < 200 || $res['code'] >= 300) {
            return $this->error_from_api($this->getId(), $res['json'] ? $res['json'] : ['message' => $res['body']]);
        }

        $json = is_array($res['json']) ? $res['json'] : [];

        $text = '';
        if (!empty($json['content'][0]['text'])) {
            $text = (string) $json['content'][0]['text'];
        } elseif (!empty($json['content']) && is_array($json['content'])) {
            foreach ($json['content'] as $block) {
                if (is_array($block) && isset($block['text'])) {
                    $text .= (string) $block['text'];
                }
            }
        }

        $usage = isset($json['usage']) && is_array($json['usage']) ? $json['usage'] : [];

        return new AIResponse($this->getId(), $model, $text, $usage, $json);
    }

    /**
     * @param array $messages
     * @param array $options
     * @param callable $on_chunk
     * @return AIResponse|\WP_Error
     */
    public function streamChat($messages, $options, $on_chunk) {
        if (!is_callable($on_chunk)) {
            return new \WP_Error('newera_ai_invalid_stream_handler', 'Streaming callback is required.');
        }

        if (!function_exists('curl_init')) {
            return $this->chat($messages, $options);
        }

        if (!$this->isConfigured()) {
            return new \WP_Error('newera_ai_not_configured', 'Anthropic API key not configured.');
        }

        $model = isset($options['model']) ? (string) $options['model'] : '';
        if ($model === '') {
            return new \WP_Error('newera_ai_invalid_model', 'Model is required.');
        }

        list($system, $anthropic_messages) = $this->split_messages(is_array($messages) ? $messages : []);

        $payload = [
            'model' => $model,
            'messages' => $anthropic_messages,
            'max_tokens' => isset($options['max_tokens']) ? (int) $options['max_tokens'] : 1024,
            'stream' => true,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        $url = $this->url('/messages');

        $headers = [
            'x-api-key: ' . $this->api_key,
            'anthropic-version: ' . $this->version,
            'Content-Type: application/json',
            'Accept: text/event-stream',
        ];

        $buffer = '';
        $text = '';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, wp_json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$buffer, &$text, $on_chunk) {
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);

                if ($line === '' || strpos($line, 'data:') !== 0) {
                    continue;
                }

                $data = trim(substr($line, 5));
                $json = json_decode($data, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                    continue;
                }

                // Anthropic streaming events use types like content_block_delta.
                if (!empty($json['type']) && $json['type'] === 'content_block_delta' && !empty($json['delta']['text'])) {
                    $delta = (string) $json['delta']['text'];
                    if ($delta !== '') {
                        $text .= $delta;
                        call_user_func($on_chunk, $delta, $json);
                    }
                }
            }

            return strlen($chunk);
        });

        $ok = curl_exec($ch);
        $err = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($ok === false || $http_code < 200 || $http_code >= 300) {
            return new \WP_Error('newera_ai_stream_failed', $err !== '' ? $err : 'Streaming request failed.', [
                'http_code' => $http_code,
            ]);
        }

        return new AIResponse($this->getId(), $model, $text, [], [], null);
    }
}
