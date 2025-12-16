<?php
/**
 * Integrations Module (stub)
 *
 * Demonstrates storing integration secrets per module.
 */

namespace Newera\Modules\Integrations;

use Newera\Modules\BaseModule;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IntegrationsModule
 */
class IntegrationsModule extends BaseModule {
    /**
     * @return string
     */
    public function getId() {
        return 'integrations';
    }

    /**
     * @return string
     */
    public function getName() {
        return 'Integrations';
    }

    /**
     * @return string
     */
    public function getDescription() {
        return 'Integrations module stub (webhook/shared secret storage example).';
    }

    /**
     * @return string
     */
    public function getType() {
        return 'integrations';
    }

    /**
     * @return array
     */
    public function getSettingsSchema() {
        return [
            'credentials' => [
                'webhook_secret' => [
                    'type' => 'string',
                    'label' => 'Webhook Secret',
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

        if (empty($credentials['webhook_secret'])) {
            $errors['webhook_secret'] = 'Webhook secret is required.';
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
            $this->log_warning('Integrations credentials validation failed', [
                'errors' => $validation['errors'],
            ]);
            return false;
        }

        return $this->set_credential('webhook_secret', $credentials['webhook_secret']);
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        return $this->has_credential('webhook_secret');
    }

    /**
     * Register hooks only when active.
     */
    public function registerHooks() {
        add_action('init', [$this, 'init_integrations']);
    }

    /**
     * Stub hook.
     */
    public function init_integrations() {
        $this->log_info('Integrations module active');
    }
}
