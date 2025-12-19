<?php
/**
 * Internal AI command interface for orchestration.
 */

namespace Newera\Modules\AI;

if (!defined('ABSPATH')) {
    exit;
}

interface AICommandInterface {
    /**
     * Command identifier.
     *
     * @return string
     */
    public function getId();

    /**
     * Execute command.
     *
     * @param AIManager $ai
     * @param array $payload
     * @return mixed|\WP_Error
     */
    public function execute($ai, $payload);
}
