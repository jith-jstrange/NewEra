<?php
/**
 * Streaming chat command.
 */

namespace Newera\Modules\AI\Commands;

use Newera\Modules\AI\AICommandInterface;
use Newera\Modules\AI\AIManager;

if (!defined('ABSPATH')) {
    exit;
}

class StreamChatCommand implements AICommandInterface {
    /**
     * @return string
     */
    public function getId() {
        return 'stream_chat';
    }

    /**
     * @param AIManager $ai
     * @param array $payload
     * @return mixed|\WP_Error
     */
    public function execute($ai, $payload) {
        $messages = isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : [];
        $options = isset($payload['options']) && is_array($payload['options']) ? $payload['options'] : [];
        $on_chunk = isset($payload['on_chunk']) ? $payload['on_chunk'] : null;

        return $ai->stream_chat($messages, $options, $on_chunk);
    }
}
