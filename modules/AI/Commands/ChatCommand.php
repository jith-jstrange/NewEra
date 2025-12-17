<?php
/**
 * Chat command.
 */

namespace Newera\Modules\AI\Commands;

use Newera\Modules\AI\AICommandInterface;
use Newera\Modules\AI\AIManager;

if (!defined('ABSPATH')) {
    exit;
}

class ChatCommand implements AICommandInterface {
    /**
     * @return string
     */
    public function getId() {
        return 'chat';
    }

    /**
     * @param AIManager $ai
     * @param array $payload
     * @return mixed|\WP_Error
     */
    public function execute($ai, $payload) {
        $messages = isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : [];
        $options = isset($payload['options']) && is_array($payload['options']) ? $payload['options'] : [];

        return $ai->chat($messages, $options);
    }
}
