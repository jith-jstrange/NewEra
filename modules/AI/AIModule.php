<?php
namespace Newera\Modules\AI;

use Newera\Modules\BaseModule;

if (!defined('ABSPATH')) {
    exit;
}

class AIModule extends BaseModule {
    public function getId() { return 'AI'; }
    public function getName() { return 'AI'; }
    public function getDescription() { return 'AI Module'; }
    public function getType() { return 'ai'; }
    public function getSettingsSchema() { return []; }
    public function validateCredentials($credentials) { return ['valid' => true]; }
    public function saveCredentials($credentials) { return true; }
    public function isConfigured() { return true; }
    public function registerHooks() {}
    
    /**
     * Set rate limit
     * 
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function setRateLimit($params) {
        $limit = $params['limit'] ?? 0;
        $window = $params['window'] ?? 'hour';
        
        if ($limit <= 0) {
            throw new \Exception('Limit must be greater than 0');
        }
        
        $this->update_setting('rate_limit', $limit);
        $this->update_setting('rate_window', $window);
        
        return [
            'message' => "Rate limit set to $limit per $window",
            'limit' => $limit,
            'window' => $window
        ];
    }
}
