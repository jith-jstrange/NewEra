<?php
/**
 * OpenAI provider implementation.
 */

namespace Newera\Modules\AI\Providers;

use Newera\Modules\AI\AbstractAIProvider;
use Newera\Modules\AI\AIResponse;

if (!defined('ABSPATH')) {
    exit;
}

class OpenAIProvider extends AbstractAIProvider {
    /**
     * @param string $base_url
     */
    public function __construct($base_url = 'https://api.openai.com/v1') {
        parent::__construct($base_url);
    }

    /**
     * @return string
     */
    public function getId() {
        return 'openai';
    }

    /**
     * @return string
     */
    public function getName() {
        return 'OpenAI';
    }

    /**
     * @return array|\WP_Error
     */
    public function listModels() {
        if (!$this->isConfigured()) {
            return new \WP_Error('newera_ai_not_configured', 'OpenAI API key not configured.');
        }

        $res = $this->request('GET', $this->url('/models'), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
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
            $models[] = [
                'id' => (string) $item['id'],
                'label' => (string) $item['id'],
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
     * @param array $options
     * @return AIResponse|\WP_Error
     */
    public function chat($messages, $options = []) {
        if (!$this->isConfigured()) {
            return new \WP_Error('newera_ai_not_configured', 'OpenAI API key not configured.');
        }

        $payload = [
            'model' => isset($options['model']) ? (string) $options['model'] : '',
            'messages' => is_array($messages) ? $messages : [],
        ];

        if ($payload['model'] === '') {
            return new \WP_Error('newera_ai_invalid_model', 'Model is required.');
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        $res = $this->request('POST', $this->url('/chat/completions'), [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
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
        if (!empty($json['choices'][0]['message']['content'])) {
            $text = (string) $json['choices'][0]['message']['content'];
        }

        $usage = isset($json['usage']) && is_array($json['usage']) ? $json['usage'] : [];

        return new AIResponse($this->getId(), $payload['model'], $text, $usage, $json);
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
            return new \WP_Error('newera_ai_not_configured', 'OpenAI API key not configured.');
        }

        $model = isset($options['model']) ? (string) $options['model'] : '';
        if ($model === '') {
            return new \WP_Error('newera_ai_invalid_model', 'Model is required.');
        }

        $payload = [
            'model' => $model,
            'messages' => is_array($messages) ? $messages : [],
            'stream' => true,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        $url = $this->url('/chat/completions');
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
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
                if ($data === '[DONE]') {
                    continue;
                }

                $json = json_decode($data, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                    continue;
                }

                $delta = '';
                if (!empty($json['choices'][0]['delta']['content'])) {
                    $delta = (string) $json['choices'][0]['delta']['content'];
                }

                if ($delta !== '') {
                    $text .= $delta;
                    call_user_func($on_chunk, $delta, $json);
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
