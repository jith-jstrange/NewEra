<?php
/**
 * Simple command bus for AI orchestration.
 */

namespace Newera\Modules\AI;

if (!defined('ABSPATH')) {
    exit;
}

class AICommandBus {
    /**
     * @var AICommandInterface[]
     */
    private $commands = [];

    /**
     * @param AICommandInterface $command
     */
    public function register($command) {
        if (!$command instanceof AICommandInterface) {
            return;
        }

        $this->commands[$command->getId()] = $command;
    }

    /**
     * @param string $id
     * @return AICommandInterface|null
     */
    public function get($id) {
        return isset($this->commands[$id]) ? $this->commands[$id] : null;
    }

    /**
     * @param AIManager $ai
     * @param string $command_id
     * @param array $payload
     * @return mixed|\WP_Error
     */
    public function execute($ai, $command_id, $payload = []) {
        $command_id = (string) $command_id;
        $command = $this->get($command_id);

        if (!$command) {
            return new \WP_Error('newera_ai_unknown_command', 'Unknown AI command.', [
                'command' => $command_id,
            ]);
        }

        return $command->execute($ai, is_array($payload) ? $payload : []);
    }
}
