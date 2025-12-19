<?php
/**
 * Auth Module
 *
 * Provides Better-Auth integration foundations and secure credential storage.
 */

namespace Newera\Modules\Auth;

use Newera\Modules\BaseModule;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/BetterAuthManager.php';

/**
 * Class AuthModule
 */
class AuthModule extends BaseModule {
    /**
     * @var BetterAuthManager
     */
    private $better_auth;

    /**
     * @param \Newera\Core\StateManager|null $state_manager
     * @param \Newera\Core\Logger|null $logger
     */
    public function __construct($state_manager = null, $logger = null) {
        parent::__construct($state_manager, $logger);

        $this->better_auth = new BetterAuthManager($this->state_manager, $this->logger);

        // Setup Wizard integration must work even when the module is not yet active.
        add_action('newera_setup_wizard_step_before_store', [$this, 'handle_setup_wizard_step'], 10, 3);
        add_filter('newera_setup_wizard_step_context', [$this, 'filter_setup_wizard_step_context'], 10, 3);
    }

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
        return 'Authentication integration using Better-Auth with multi-provider support.';
    }

    /**
     * @return string
     */
    public function getType() {
        return 'auth';
    }

    /**
     * Consider the module configured when at least one provider is enabled, and
     * every enabled provider has its required credentials.
     *
     * @return bool
     */
    public function isConfigured() {
        $enabled = $this->better_auth->get_enabled_providers();
        if (empty($enabled)) {
            return false;
        }

        foreach ($enabled as $provider) {
            if (!$this->better_auth->is_provider_configured($provider)) {
                return false;
            }
        }

        return true;
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
        add_action('rest_api_init', [$this->better_auth, 'register_rest_routes']);
        add_action('init', [$this, 'init_auth']);
    }

    /**
     * Module init hook.
     */
    public function init_auth() {
        $this->log_info('Auth module active', [
            'enabled_providers' => $this->better_auth->get_enabled_providers(),
        ]);
    }

    /**
     * Setup Wizard handler.
     *
     * @param string $step
     * @param array $data Sanitized data for the step (may include sensitive values).
     * @param \Newera\Core\StateManager $state_manager
     */
    public function handle_setup_wizard_step($step, $data, $state_manager) {
        if ($step !== 'auth' || !is_array($data)) {
            return;
        }

        $providers_enabled = isset($data['providers_enabled']) && is_array($data['providers_enabled']) ? $data['providers_enabled'] : [];
        $providers_enabled = array_values(array_filter(array_map('sanitize_key', $providers_enabled)));

        // Persist enabled providers list (non-sensitive).
        $this->better_auth->set_enabled_providers($providers_enabled);

        // Store credentials securely. Empty values do not overwrite existing secrets.
        if (isset($data['providers']) && is_array($data['providers'])) {
            foreach ($data['providers'] as $provider => $provider_data) {
                if (!is_array($provider_data)) {
                    continue;
                }

                $provider = sanitize_key($provider);
                $client_id = isset($provider_data['client_id']) ? (string) $provider_data['client_id'] : '';
                $client_secret = isset($provider_data['client_secret']) ? (string) $provider_data['client_secret'] : '';

                $this->better_auth->store_provider_credentials($provider, $client_id, $client_secret);
            }
        }

        // Ensure this module is enabled in state so ModuleRegistry can activate it.
        $this->enable_module_in_state();

        $this->log_info('Auth wizard step saved', [
            'providers_enabled' => $providers_enabled,
        ]);
    }

    /**
     * Add step context for the Setup Wizard UI.
     *
     * @param array $context
     * @param string $step
     * @param \Newera\Core\StateManager $state_manager
     * @return array
     */
    public function filter_setup_wizard_step_context($context, $step, $state_manager) {
        if ($step !== 'auth') {
            return $context;
        }

        $providers = [];
        foreach ($this->better_auth->get_supported_providers() as $provider_id => $cfg) {
            $provider_id = sanitize_key($provider_id);

            $providers[$provider_id] = [
                'label' => $cfg['label'] ?? ucfirst($provider_id),
                'type' => $cfg['type'] ?? 'oauth',
                'requires_credentials' => !empty($cfg['requires_credentials']),
                'redirect_uri' => $this->better_auth->get_redirect_uri($provider_id),
                'has_client_id' => $this->state_manager ? $this->state_manager->hasSecure($this->getId(), $provider_id . '_client_id') : false,
                'has_client_secret' => $this->state_manager ? $this->state_manager->hasSecure($this->getId(), $provider_id . '_client_secret') : false,
            ];
        }

        $base_context = is_array($context) ? $context : [];

        return array_merge($base_context, [
            'enabled_providers' => $this->better_auth->get_enabled_providers(),
            'providers' => $providers,
        ]);
    }

    /**
     * Ensure the module is enabled in plugin state.
     */
    private function enable_module_in_state() {
        if (!$this->state_manager) {
            return;
        }

        $enabled = $this->state_manager->get_state_value('modules_enabled', []);

        if (!is_array($enabled)) {
            $enabled = [];
        }

        // Store as associative array for clarity.
        $enabled[$this->getId()] = true;

        $this->state_manager->update_state('modules_enabled', $enabled);
    }
}
