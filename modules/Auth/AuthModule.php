<?php
/**
 * Auth Module (stub)
 *
 * Demonstrates per-module credential storage using StateManager secure storage.
 */

namespace Newera\Modules\Auth;

use Newera\Modules\BaseModule;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AuthModule
 */
class AuthModule extends BaseModule {
    /**
     * @return string
     */
    public function getId() {
        return 'auth';
    }

    /**
     * @return string
     */
    public function getName() {
        return 'Auth';
    }

    /**
     * @return string
     */
    public function getDescription() {
        return 'Authentication module stub (OAuth/API credential storage example).';
    }

    /**
     * @return string
     */
    public function getType() {
        return 'auth';
    }

    /**
     * @return array
     */
    public function getSettingsSchema() {
        return [
            'credentials' => [
                'client_id' => [
                    'type' => 'string',
                    'label' => 'Client ID',
                    'required' => true,
                    'secure' => true,
                ],
                'client_secret' => [
                    'type' => 'string',
                    'label' => 'Client Secret',
                    'required' => true,
                    'secure' => true,
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

        if (empty($credentials['client_id'])) {
            $errors['client_id'] = 'Client ID is required.';
        }

        if (empty($credentials['client_secret'])) {
            $errors['client_secret'] = 'Client Secret is required.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Example: save credentials provided by setup wizard.
     *
     * @param array $credentials
     * @return bool
     */
    public function saveCredentials($credentials) {
        $validation = $this->validateCredentials($credentials);
        if (!$validation['valid']) {
            $this->log_warning('Auth credentials validation failed', [
                'errors' => $validation['errors'],
            ]);
            return false;
        }

        $ok1 = $this->set_credential('client_id', $credentials['client_id']);
        $ok2 = $this->set_credential('client_secret', $credentials['client_secret']);

        return $ok1 && $ok2;
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        return $this->has_credential('client_id') && $this->has_credential('client_secret');
    }

    /**
     * Enable Google Sign-In
     * 
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function enableGoogleSignIn($params) {
        $client_id = $params['client_id'] ?? '';
        $client_secret = $params['client_secret'] ?? '';

        if (empty($client_id) || empty($client_secret)) {
            throw new \Exception('Client ID and Client Secret are required');
        }

        $this->set_credential('google_client_id', $client_id);
        $this->set_credential('google_client_secret', $client_secret);
        $this->set_credential('google_signin_enabled', true);
        
        return ['message' => 'Google Sign-In enabled successfully'];
    }

    /**
     * Register hooks only when active.
     */
    public function registerHooks() {
        add_action('init', [$this, 'init_auth']);
    }

    /**
     * Stub hook.
     */
    public function init_auth() {
        $this->log_info('Auth module active');
    }
}
