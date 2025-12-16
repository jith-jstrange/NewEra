<?php
/**
 * Payments Module (stub)
 *
 * Demonstrates per-module credential storage using StateManager secure storage.
 */

namespace Newera\Modules\Payments;

use Newera\Modules\BaseModule;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PaymentsModule
 */
class PaymentsModule extends BaseModule {
    /**
     * @return string
     */
    public function getId() {
        return 'payments';
    }

    /**
     * @return string
     */
    public function getName() {
        return 'Payments';
    }

    /**
     * @return string
     */
    public function getDescription() {
        return 'Payments module stub (gateway API key storage example).';
    }

    /**
     * @return string
     */
    public function getType() {
        return 'payments';
    }

    /**
     * @return array
     */
    public function getSettingsSchema() {
        return [
            'credentials' => [
                'api_key' => [
                    'type' => 'string',
                    'label' => 'Gateway API Key',
                    'required' => true,
                    'secure' => true,
                ],
            ],
            'settings' => [
                'mode' => [
                    'type' => 'string',
                    'label' => 'Mode',
                    'required' => false,
                    'default' => 'test',
                    'options' => ['test', 'live'],
                ],
            ],
        ];
    }

    /**
     * @param array $credentials
     * @return array
     */
    public function validateCredentials($credentials) {
        $errors = [];

        if (empty($credentials['api_key'])) {
            $errors['api_key'] = 'API key is required.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param array $credentials
     * @return bool
     */
    public function saveCredentials($credentials) {
        $validation = $this->validateCredentials($credentials);
        if (!$validation['valid']) {
            $this->log_warning('Payments credentials validation failed', [
                'errors' => $validation['errors'],
            ]);
            return false;
        }

        return $this->set_credential('api_key', $credentials['api_key']);
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        return $this->has_credential('api_key');
    }

    /**
     * Register hooks only when active.
     */
    public function registerHooks() {
        add_action('init', [$this, 'init_payments']);
    }

    /**
     * Stub hook.
     */
    public function init_payments() {
        $this->log_info('Payments module active');
    }
}
