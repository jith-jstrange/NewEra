<?php
/**
 * Better-Auth Manager
 *
 * Provides a WordPress-friendly integration layer and foundations for multi-provider
 * authentication (Email, Magic Link, Google, Apple, GitHub).
 */

namespace Newera\Modules\Auth;

use Newera\Core\Crypto;
use Newera\Core\Logger;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BetterAuthManager
 */
class BetterAuthManager {
    /**
     * REST API namespace.
     */
    const REST_NAMESPACE = 'newera/v1';

    /**
     * Supported auth providers.
     */
    const PROVIDERS = [
        'email' => [
            'label' => 'Email',
            'type' => 'email',
            'requires_credentials' => false,
        ],
        'magic_link' => [
            'label' => 'Magic Link',
            'type' => 'magic_link',
            'requires_credentials' => false,
        ],
        'google' => [
            'label' => 'Google',
            'type' => 'oauth',
            'requires_credentials' => true,
        ],
        'apple' => [
            'label' => 'Apple',
            'type' => 'oauth',
            'requires_credentials' => true,
        ],
        'github' => [
            'label' => 'GitHub',
            'type' => 'oauth',
            'requires_credentials' => true,
        ],
    ];

    /**
     * @var StateManager|null
     */
    private $state_manager;

    /**
     * @var Logger|null
     */
    private $logger;

    /**
     * @var Crypto
     */
    private $crypto;

    /**
     * @param StateManager|null $state_manager
     * @param Logger|null $logger
     */
    public function __construct($state_manager = null, $logger = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : (function_exists('apply_filters') ? apply_filters('newera_get_state_manager', null) : null);
        $this->logger = $logger instanceof Logger ? $logger : (function_exists('apply_filters') ? apply_filters('newera_get_logger', null) : null);
        $this->crypto = new Crypto();
    }

    /**
     * @return array
     */
    public function get_supported_providers() {
        $providers = self::PROVIDERS;

        if (function_exists('apply_filters')) {
            $providers = apply_filters('newera_better_auth_providers', $providers);
        }

        return is_array($providers) ? $providers : self::PROVIDERS;
    }

    /**
     * @return string[]
     */
    public function get_enabled_providers() {
        $settings = $this->get_auth_module_settings();
        $enabled = isset($settings['providers_enabled']) && is_array($settings['providers_enabled']) ? $settings['providers_enabled'] : [];

        $enabled = array_values(array_filter(array_map('sanitize_key', $enabled)));

        return $enabled;
    }

    /**
     * @param string[] $providers
     * @return bool
     */
    public function set_enabled_providers($providers) {
        $providers = is_array($providers) ? $providers : [];
        $providers = array_values(array_filter(array_map('sanitize_key', $providers)));

        $supported = array_keys($this->get_supported_providers());
        $providers = array_values(array_intersect($providers, $supported));

        return $this->update_auth_module_settings([
            'providers_enabled' => $providers,
        ]);
    }

    /**
     * @param string $provider
     * @return string
     */
    public function get_redirect_uri($provider) {
        $provider = sanitize_key($provider);
        $providers = $this->get_supported_providers();

        if (!isset($providers[$provider])) {
            return '';
        }

        if (function_exists('rest_url')) {
            $uri = rest_url(self::REST_NAMESPACE . '/auth/callback/' . $provider);
        } else {
            $uri = home_url('/?newera_auth=callback&provider=' . rawurlencode($provider));
        }

        return esc_url_raw($uri);
    }

    /**
     * Validate a redirect URI against the expected one for this site.
     *
     * @param string $provider
     * @param string $redirect_uri
     * @return bool
     */
    public function validate_redirect_uri($provider, $redirect_uri) {
        $expected = $this->get_redirect_uri($provider);

        if ($expected === '' || $redirect_uri === '') {
            return false;
        }

        $expected_norm = $this->normalize_url_for_comparison($expected);
        $provided_norm = $this->normalize_url_for_comparison($redirect_uri);

        return $expected_norm !== '' && $expected_norm === $provided_norm;
    }

    /**
     * @param string $provider
     * @return array
     */
    public function get_provider_credentials($provider) {
        $provider = sanitize_key($provider);

        return [
            'client_id' => $this->get_secure_credential($provider . '_client_id'),
            'client_secret' => $this->get_secure_credential($provider . '_client_secret'),
        ];
    }

    /**
     * @param string $provider
     * @param string $client_id
     * @param string $client_secret
     * @return bool
     */
    public function store_provider_credentials($provider, $client_id, $client_secret) {
        $provider = sanitize_key($provider);
        $providers = $this->get_supported_providers();

        if (!isset($providers[$provider])) {
            return false;
        }

        $ok = true;

        if (is_string($client_id) && $client_id !== '') {
            $ok = $this->set_secure_credential($provider . '_client_id', $client_id) && $ok;
        }

        if (is_string($client_secret) && $client_secret !== '') {
            $ok = $this->set_secure_credential($provider . '_client_secret', $client_secret) && $ok;
        }

        return $ok;
    }

    /**
     * Whether this provider is fully configured.
     *
     * @param string $provider
     * @return bool
     */
    public function is_provider_configured($provider) {
        $provider = sanitize_key($provider);
        $providers = $this->get_supported_providers();

        if (!isset($providers[$provider])) {
            return false;
        }

        if (empty($providers[$provider]['requires_credentials'])) {
            return true;
        }

        return $this->has_secure_credential($provider . '_client_id') && $this->has_secure_credential($provider . '_client_secret');
    }

    /**
     * Register REST routes required for the Better-Auth integration.
     */
    public function register_rest_routes() {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::REST_NAMESPACE, '/auth/redirect/(?P<provider>[a-z_\-]+)', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'rest_redirect'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/callback/(?P<provider>[a-z_\-]+)', [
            'methods' => ['GET', 'POST'],
            'permission_callback' => '__return_true',
            'callback' => [$this, 'rest_callback'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/session', [
            'methods' => 'GET',
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'callback' => [$this, 'rest_session'],
        ]);

        register_rest_route(self::REST_NAMESPACE, '/auth/logout', [
            'methods' => 'POST',
            'permission_callback' => function() {
                return is_user_logged_in();
            },
            'callback' => [$this, 'rest_logout'],
        ]);
    }

    /**
     * REST endpoint: returns redirect_uri and a placeholder authorization URL.
     *
     * @param \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function rest_redirect($request) {
        $provider = sanitize_key($request['provider']);
        $providers = $this->get_supported_providers();

        if (!isset($providers[$provider])) {
            return new \WP_Error('newera_auth_provider', __('Unsupported auth provider.', 'newera'), ['status' => 400]);
        }

        $redirect_uri = $this->get_redirect_uri($provider);

        if ($redirect_uri === '') {
            return new \WP_Error('newera_auth_redirect_uri', __('Unable to generate redirect URL.', 'newera'), ['status' => 500]);
        }

        if (!$this->is_provider_configured($provider)) {
            return new \WP_Error('newera_auth_not_configured', __('Provider is not configured on this site.', 'newera'), ['status' => 400]);
        }

        $auth_url = $this->build_authorization_url($provider, $redirect_uri);

        if ($auth_url === '') {
            return new \WP_Error('newera_auth_not_implemented', __('Provider redirect flow is not implemented yet.', 'newera'), ['status' => 501]);
        }

        return [
            'provider' => $provider,
            'redirect_uri' => $redirect_uri,
            'authorization_url' => $auth_url,
        ];
    }

    /**
     * REST endpoint: callback handler.
     *
     * Supports "token" + "email" (JWT handoff) as an integration-friendly baseline.
     *
     * @param \WP_REST_Request $request
     * @return array|\WP_Error
     */
    public function rest_callback($request) {
        $provider = sanitize_key($request['provider']);
        $providers = $this->get_supported_providers();

        if (!isset($providers[$provider])) {
            return new \WP_Error('newera_auth_provider', __('Unsupported auth provider.', 'newera'), ['status' => 400]);
        }

        $token = $request->get_param('token');
        $email = $request->get_param('email');

        if (is_string($token) && $token !== '' && is_string($email) && is_email($email)) {
            $user = $this->get_or_create_user_by_email($email);
            if (is_wp_error($user)) {
                return $user;
            }

            $this->login_user($user->ID);
            $this->store_user_jwt($user->ID, $token);

            return [
                'status' => 'ok',
                'provider' => $provider,
                'user_id' => $user->ID,
                'redirect' => admin_url(),
            ];
        }

        return new \WP_Error(
            'newera_auth_callback_not_implemented',
            __('OAuth callback handling is not implemented yet. Configure Better-Auth to pass token+email to this callback.', 'newera'),
            ['status' => 501]
        );
    }

    /**
     * REST endpoint: session details.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function rest_session($request) {
        $user_id = get_current_user_id();

        $accounts = $this->get_linked_accounts($user_id);
        $jwt = $this->get_user_jwt($user_id);

        return [
            'user_id' => $user_id,
            'accounts' => $accounts,
            'has_jwt' => is_string($jwt) && $jwt !== '',
        ];
    }

    /**
     * REST endpoint: logout.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public function rest_logout($request) {
        $user_id = get_current_user_id();

        $this->delete_user_jwt($user_id);

        if (function_exists('wp_logout')) {
            wp_logout();
        }

        return [
            'status' => 'ok',
        ];
    }

    /**
     * Link an external account to a WordPress user.
     *
     * @param int $user_id
     * @param string $provider
     * @param string $provider_user_id
     * @param array $profile
     * @return bool
     */
    public function link_account($user_id, $provider, $provider_user_id, $profile = []) {
        $user_id = intval($user_id);
        $provider = sanitize_key($provider);

        if ($user_id <= 0 || $provider === '' || $provider_user_id === '') {
            return false;
        }

        $accounts = $this->get_linked_accounts($user_id);

        $accounts[$provider] = [
            'provider' => $provider,
            'provider_user_id' => sanitize_text_field($provider_user_id),
            'profile' => is_array($profile) ? $profile : [],
            'linked_at' => current_time('mysql'),
        ];

        return update_user_meta($user_id, 'newera_better_auth_accounts', $accounts) !== false;
    }

    /**
     * @param int $user_id
     * @return array
     */
    public function get_linked_accounts($user_id) {
        $user_id = intval($user_id);
        $accounts = get_user_meta($user_id, 'newera_better_auth_accounts', true);

        return is_array($accounts) ? $accounts : [];
    }

    /**
     * Store a JWT token for the user (encrypted using Crypto).
     *
     * @param int $user_id
     * @param string $jwt
     * @param int|null $expires_at Unix timestamp (optional)
     * @return bool
     */
    public function store_user_jwt($user_id, $jwt, $expires_at = null) {
        $user_id = intval($user_id);
        if ($user_id <= 0 || !is_string($jwt) || $jwt === '') {
            return false;
        }

        $payload = [
            'jwt' => $jwt,
            'expires_at' => $expires_at ? intval($expires_at) : null,
            'stored_at' => time(),
        ];

        $encrypted = $this->crypto->encrypt($payload);
        if ($encrypted === false) {
            return false;
        }

        return update_user_meta($user_id, 'newera_better_auth_jwt', $encrypted) !== false;
    }

    /**
     * @param int $user_id
     * @return string|null
     */
    public function get_user_jwt($user_id) {
        $user_id = intval($user_id);
        $encrypted = get_user_meta($user_id, 'newera_better_auth_jwt', true);

        if (!is_array($encrypted)) {
            return null;
        }

        $data = $this->crypto->decrypt($encrypted);
        if (!is_array($data) || empty($data['jwt'])) {
            return null;
        }

        return is_string($data['jwt']) ? $data['jwt'] : null;
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public function delete_user_jwt($user_id) {
        $user_id = intval($user_id);
        return delete_user_meta($user_id, 'newera_better_auth_jwt');
    }

    /**
     * @param string $email
     * @return \WP_User|\WP_Error
     */
    private function get_or_create_user_by_email($email) {
        $email = sanitize_email($email);

        if (!is_email($email)) {
            return new \WP_Error('newera_auth_email', __('Invalid email address.', 'newera'), ['status' => 400]);
        }

        $user = get_user_by('email', $email);
        if ($user instanceof \WP_User) {
            return $user;
        }

        $username = sanitize_user(current(explode('@', $email)), true);
        if ($username === '') {
            $username = 'user_' . wp_generate_password(6, false, false);
        }

        // Ensure username unique.
        $base = $username;
        $i = 1;
        while (username_exists($username)) {
            $username = $base . '_' . $i;
            $i++;
        }

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(32, true, true),
            'role' => get_option('default_role', 'subscriber'),
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        return get_user_by('id', $user_id);
    }

    /**
     * @param int $user_id
     */
    private function login_user($user_id) {
        $user_id = intval($user_id);

        if ($user_id <= 0) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        if (function_exists('do_action')) {
            do_action('wp_login', $user->user_login, $user);
        }
    }

    /**
     * @param string $provider
     * @param string $redirect_uri
     * @return string
     */
    private function build_authorization_url($provider, $redirect_uri) {
        $provider = sanitize_key($provider);
        $creds = $this->get_provider_credentials($provider);

        if (empty($creds['client_id'])) {
            return '';
        }

        $state = function_exists('wp_create_nonce') ? wp_create_nonce('newera_auth_' . $provider) : '';

        switch ($provider) {
            case 'google':
                return add_query_arg([
                    'client_id' => $creds['client_id'],
                    'redirect_uri' => $redirect_uri,
                    'response_type' => 'code',
                    'scope' => 'openid email profile',
                    'state' => $state,
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                ], 'https://accounts.google.com/o/oauth2/v2/auth');

            case 'github':
                return add_query_arg([
                    'client_id' => $creds['client_id'],
                    'redirect_uri' => $redirect_uri,
                    'scope' => 'read:user user:email',
                    'state' => $state,
                ], 'https://github.com/login/oauth/authorize');

            case 'apple':
                return add_query_arg([
                    'client_id' => $creds['client_id'],
                    'redirect_uri' => $redirect_uri,
                    'response_type' => 'code',
                    'response_mode' => 'query',
                    'scope' => 'name email',
                    'state' => $state,
                ], 'https://appleid.apple.com/auth/authorize');

            default:
                return '';
        }
    }

    /**
     * @return array
     */
    private function get_auth_module_settings() {
        if (!$this->state_manager) {
            return [];
        }

        $modules = $this->state_manager->get_setting('modules', []);
        if (!is_array($modules) || !isset($modules['auth']) || !is_array($modules['auth'])) {
            return [];
        }

        return $modules['auth'];
    }

    /**
     * @param array $settings
     * @return bool
     */
    private function update_auth_module_settings($settings) {
        if (!$this->state_manager) {
            return false;
        }

        $modules = $this->state_manager->get_setting('modules', []);
        if (!is_array($modules)) {
            $modules = [];
        }

        $current = isset($modules['auth']) && is_array($modules['auth']) ? $modules['auth'] : [];
        $modules['auth'] = array_merge($current, is_array($settings) ? $settings : []);

        return $this->state_manager->update_setting('modules', $modules);
    }

    /**
     * @param string $key
     * @return bool
     */
    private function has_secure_credential($key) {
        if (!$this->state_manager) {
            return false;
        }

        return $this->state_manager->hasSecure('auth', $key);
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private function get_secure_credential($key, $default = null) {
        if (!$this->state_manager) {
            return $default;
        }

        return $this->state_manager->getSecure('auth', $key, $default);
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    private function set_secure_credential($key, $value) {
        if (!$this->state_manager) {
            return false;
        }

        if ($this->state_manager->hasSecure('auth', $key)) {
            return $this->state_manager->updateSecure('auth', $key, $value);
        }

        return $this->state_manager->setSecure('auth', $key, $value);
    }

    /**
     * @param string $url
     * @return string
     */
    private function normalize_url_for_comparison($url) {
        $url = esc_url_raw($url);
        if ($url === '') {
            return '';
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? intval($parts['port']) : null;
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
        $query = isset($parts['query']) ? $parts['query'] : '';

        $norm = $scheme . '://' . $host;
        if ($port && !in_array([$scheme, $port], [['http', 80], ['https', 443]], true)) {
            $norm .= ':' . $port;
        }

        $norm .= $path;

        if ($query !== '') {
            $norm .= '?' . $query;
        }

        return $norm;
    }
}
