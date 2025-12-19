<?php
/**
 * Authentication Manager
 *
 * Handles JWT authentication and user permission verification
 *
 * @package Newera\API\Auth
 */

namespace Newera\API\Auth;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authentication Manager class
 */
class AuthManager {
    /**
     * JWT secret key option name
     */
    const JWT_SECRET_OPTION = 'newera_jwt_secret';

    /**
     * JWT expiration time (24 hours)
     */
    const JWT_EXPIRATION = 86400;

    /**
     * Rate limiting prefix for authenticated users
     */
    const RATE_LIMIT_PREFIX = 'newera_api_rate_limit_';

    /**
     * State Manager instance
     *
     * @var \Newera\Core\StateManager
     */
    private $state_manager;

    /**
     * Logger instance
     *
     * @var \Newera\Core\Logger
     */
    private $logger;

    /**
     * JWT secret key
     *
     * @var string
     */
    private $jwt_secret;

    /**
     * Constructor
     *
     * @param \Newera\Core\StateManager $state_manager
     * @param \Newera\Core\Logger $logger
     */
    public function __construct($state_manager, $logger) {
        $this->state_manager = $state_manager;
        $this->logger = $logger;
    }

    /**
     * Initialize Authentication Manager
     */
    public function init() {
        $this->jwt_secret = $this->get_or_create_jwt_secret();
    }

    /**
     * Get or create JWT secret key
     *
     * @return string
     */
    private function get_or_create_jwt_secret() {
        $secret = $this->state_manager->get_option(self::JWT_SECRET_OPTION);

        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            $this->state_manager->update_option(self::JWT_SECRET_OPTION, $secret);
            $this->logger->info('Generated new JWT secret key');
        }

        return $secret;
    }

    /**
     * Authenticate user with username and password
     *
     * @param string $username
     * @param string $password
     * @return array|\WP_Error
     */
    public function authenticate_user($username, $password) {
        // Verify credentials
        $user = wp_authenticate($username, $password);

        if (is_wp_error($user)) {
            $this->logger->warning('Failed authentication attempt for user: ' . $username, [
                'error' => $user->get_error_message()
            ]);
            return $user;
        }

        // Generate JWT token
        $token = $this->generate_jwt_token($user);

        if (!$token) {
            return new \WP_Error('token_generation_failed', 'Failed to generate authentication token');
        }

        $this->logger->info('User authenticated successfully', [
            'user_id' => $user->ID,
            'username' => $user->user_login
        ]);

        return [
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'roles' => $user->roles,
                'capabilities' => array_keys($user->allcaps)
            ],
            'expires_in' => self::JWT_EXPIRATION
        ];
    }

    /**
     * Generate JWT token
     *
     * @param \WP_User $user
     * @return string|null
     */
    private function generate_jwt_token($user) {
        if (!class_exists('\Firebase\JWT\JWT')) {
            $this->logger->error('Firebase JWT library not loaded');
            return null;
        }

        $now = time();
        $payload = [
            'iss' => get_site_url(),
            'iat' => $now,
            'exp' => $now + self::JWT_EXPIRATION,
            'sub' => $user->ID,
            'username' => $user->user_login,
            'roles' => $user->roles
        ];

        try {
            return \Firebase\JWT\JWT::encode($payload, $this->jwt_secret, 'HS256');
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate JWT token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Verify JWT token
     *
     * @param string $token
     * @return array|\WP_Error
     */
    public function verify_token($token) {
        if (!class_exists('\Firebase\JWT\JWT')) {
            return new \WP_Error('jwt_library_missing', 'JWT library not available');
        }

        try {
            $decoded = \Firebase\JWT\JWT::decode($token, $this->jwt_secret, ['HS256']);
            $payload = (array) $decoded;

            // Get user from payload
            $user = get_user_by('id', $payload['sub']);

            if (!$user) {
                return new \WP_Error('user_not_found', 'User not found');
            }

            // Check rate limiting
            if (!$this->check_rate_limit($user->ID)) {
                return new \WP_Error('rate_limit_exceeded', 'Rate limit exceeded');
            }

            $this->logger->debug('Token verified successfully', [
                'user_id' => $user->ID,
                'username' => $user->user_login
            ]);

            return [
                'user' => $user,
                'payload' => $payload
            ];

        } catch (\Exception $e) {
            $this->logger->warning('Token verification failed', [
                'error' => $e->getMessage()
            ]);

            return new \WP_Error('invalid_token', 'Invalid or expired token');
        }
    }

    /**
     * Authenticate request
     *
     * @return array|\WP_Error
     */
    public function authenticate_request() {
        // Get token from Authorization header
        $token = $this->get_token_from_request();

        if (!$token) {
            return new \WP_Error('no_token', 'Authentication token required');
        }

        return $this->verify_token($token);
    }

    /**
     * Get token from request
     *
     * @return string|null
     */
    private function get_token_from_request() {
        // Check Authorization header
        $auth_header = get_http_header('Authorization');
        
        if ($auth_header && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }

        // Check query parameter as fallback
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : null;

        return $token;
    }

    /**
     * Check user permissions
     *
     * @param int $user_id
     * @param string $capability
     * @param int $object_id Optional object ID for permission checks
     * @return bool
     */
    public function user_has_permission($user_id, $capability, $object_id = null) {
        if ($object_id !== null) {
            return user_can($user_id, $capability, $object_id);
        }

        return user_can($user_id, $capability);
    }

    /**
     * Check rate limiting
     *
     * @param int $user_id
     * @return bool
     */
    private function check_rate_limit($user_id) {
        $rate_limit_key = self::RATE_LIMIT_PREFIX . $user_id;
        $current_count = get_transient($rate_limit_key);

        if ($current_count === false) {
            // First request - set rate limit
            set_transient($rate_limit_key, 1, HOUR_IN_SECONDS);
            return true;
        }

        // Check if limit exceeded
        $limit = $this->get_rate_limit_for_user($user_id);
        
        if ($current_count >= $limit) {
            $this->logger->warning('Rate limit exceeded for user', [
                'user_id' => $user_id,
                'count' => $current_count,
                'limit' => $limit
            ]);
            return false;
        }

        // Increment counter
        set_transient($rate_limit_key, $current_count + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Get rate limit for user
     *
     * @param int $user_id
     * @return int
     */
    private function get_rate_limit_for_user($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return 100; // Default limit
        }

        // Higher limits for administrators
        if (user_can($user_id, 'manage_options')) {
            return 1000;
        }

        // Higher limits for editors
        if (user_can($user_id, 'edit_others_posts')) {
            return 500;
        }

        // Default limit for regular users
        return 100;
    }

    /**
     * Invalidate token (blacklist)
     *
     * @param string $token
     * @return bool
     */
    public function blacklist_token($token) {
        // In WordPress, we can use transients to blacklist tokens
        // This is a simple implementation - production systems might want
        // a more sophisticated blacklist system
        
        $blacklist_key = 'newera_token_blacklist_' . hash('sha256', $token);
        set_transient($blacklist_key, true, self::JWT_EXPIRATION);
        
        $this->logger->info('Token blacklisted', [
            'token_hash' => hash('sha256', $token)
        ]);
        
        return true;
    }

    /**
     * Refresh JWT token
     *
     * @param string $token
     * @return array|\WP_Error
     */
    public function refresh_token($token) {
        // Verify the current token
        $verification = $this->verify_token($token);
        
        if (is_wp_error($verification)) {
            return $verification;
        }

        $user = $verification['user'];
        
        // Generate new token
        $new_token = $this->generate_jwt_token($user);
        
        if (!$new_token) {
            return new \WP_Error('token_refresh_failed', 'Failed to refresh token');
        }

        $this->logger->info('Token refreshed successfully', [
            'user_id' => $user->ID
        ]);

        return [
            'token' => $new_token,
            'expires_in' => self::JWT_EXPIRATION
        ];
    }
}