<?php
/**
 * Middleware Manager
 *
 * Handles CORS, rate limiting, and request/response logging
 *
 * @package Newera\API\Middleware
 */

namespace Newera\API\Middleware;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Middleware Manager class
 */
class MiddlewareManager {
    /**
     * Rate limiting namespace prefix
     */
    const RATE_LIMIT_NAMESPACE_PREFIX = 'newera_api_rate_limit_ns_';

    /**
     * Request logging prefix
     */
    const REQUEST_LOG_PREFIX = 'newera_api_request_';

    /**
     * CORS settings option name
     */
    const CORS_SETTINGS_OPTION = 'newera_api_cors_settings';

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
     * CORS settings
     *
     * @var array
     */
    private $cors_settings;

    /**
     * Rate limit settings
     *
     * @var array
     */
    private $rate_limit_settings;

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
     * Initialize Middleware Manager
     */
    public function init() {
        $this->load_cors_settings();
        $this->load_rate_limit_settings();
    }

    /**
     * Load CORS settings
     */
    private function load_cors_settings() {
        $this->cors_settings = $this->state_manager->get_option(self::CORS_SETTINGS_OPTION, [
            'enabled' => true,
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'exposed_headers' => ['X-Total-Count', 'X-Page-Count', 'X-Rate-Limit-Remaining'],
            'allow_credentials' => false,
            'max_age' => 86400
        ]);
    }

    /**
     * Load rate limit settings
     */
    private function load_rate_limit_settings() {
        $this->rate_limit_settings = [
            'default_limit' => 100,
            'default_window' => 3600, // 1 hour
            'namespace_limits' => [
                'clients' => 500,
                'projects' => 300,
                'subscriptions' => 200,
                'settings' => 50,
                'webhooks' => 100,
                'activity' => 1000
            ]
        ];
    }

    /**
     * Handle CORS preflight request
     *
     * @param string $origin
     * @param string $method
     * @param array $headers
     * @return bool
     */
    public function handle_cors_preflight($origin, $method, $headers = []) {
        if (!$this->cors_settings['enabled']) {
            return false;
        }

        // Check if origin is allowed
        if (!$this->is_origin_allowed($origin)) {
            $this->logger->warning('CORS: Origin not allowed', ['origin' => $origin]);
            return false;
        }

        // Set CORS headers
        $this->set_cors_headers($origin);

        return true;
    }

    /**
     * Handle CORS for actual request
     *
     * @param string $origin
     * @param \WP_REST_Response|\WP_Error $response
     * @param \WP_REST_Request $request
     */
    public function handle_cors($response, $handler, $request) {
        if (!$this->cors_settings['enabled']) {
            return $response;
        }

        $origin = get_http_header('Origin');
        
        if ($origin && $this->is_origin_allowed($origin)) {
            $this->set_cors_headers($origin);
        }

        return $response;
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin
     * @return bool
     */
    private function is_origin_allowed($origin) {
        if (empty($origin)) {
            return true; // No origin header, likely not a CORS request
        }

        $allowed_origins = $this->cors_settings['allowed_origins'];
        
        // Check for wildcard
        if (in_array('*', $allowed_origins)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $allowed_origins)) {
            return true;
        }

        // Check wildcard subdomain matching
        foreach ($allowed_origins as $allowed_origin) {
            if (strpos($allowed_origin, '*') !== false) {
                $pattern = str_replace('*', '.*', $allowed_origin);
                if (preg_match('#^' . $pattern . '$#', $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Set CORS headers
     *
     * @param string $origin
     */
    private function set_cors_headers($origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: ' . implode(', ', $this->cors_settings['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $this->cors_settings['allowed_headers']));
        header('Access-Control-Expose-Headers: ' . implode(', ', $this->cors_settings['exposed_headers']));
        header('Access-Control-Max-Age: ' . $this->cors_settings['max_age']);
        
        if ($this->cors_settings['allow_credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }
    }

    /**
     * Check namespace rate limit
     *
     * @param string $namespace API namespace (e.g., 'clients', 'projects')
     * @param int $user_id User ID
     * @return array|\WP_Error
     */
    public function check_namespace_rate_limit($namespace, $user_id = null) {
        $limit = $this->get_namespace_rate_limit($namespace);
        $window = $this->get_namespace_rate_window($namespace);
        $key = self::RATE_LIMIT_NAMESPACE_PREFIX . $namespace . '_' . ($user_id ?: 'anonymous');

        $current_count = get_transient($key);

        if ($current_count === false) {
            // First request in window
            set_transient($key, 1, $window);
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'limit' => $limit,
                'reset' => time() + $window
            ];
        }

        if ($current_count >= $limit) {
            $this->logger->warning('Namespace rate limit exceeded', [
                'namespace' => $namespace,
                'user_id' => $user_id,
                'count' => $current_count,
                'limit' => $limit
            ]);

            return new \WP_Error('rate_limit_exceeded', 'Rate limit exceeded for namespace: ' . $namespace, [
                'code' => 429,
                'remaining' => 0,
                'limit' => $limit,
                'reset' => time() + $window
            ]);
        }

        // Increment counter
        set_transient($key, $current_count + 1, $window);

        return [
            'allowed' => true,
            'remaining' => $limit - $current_count - 1,
            'limit' => $limit,
            'reset' => time() + $window
        ];
    }

    /**
     * Get rate limit for namespace
     *
     * @param string $namespace
     * @return int
     */
    private function get_namespace_rate_limit($namespace) {
        return isset($this->rate_limit_settings['namespace_limits'][$namespace])
            ? $this->rate_limit_settings['namespace_limits'][$namespace]
            : $this->rate_limit_settings['default_limit'];
    }

    /**
     * Get rate window for namespace
     *
     * @param string $namespace
     * @return int
     */
    private function get_namespace_rate_window($namespace) {
        // Different namespaces can have different rate windows
        $windows = [
            'settings' => 300,    // 5 minutes for settings
            'webhooks' => 600,    // 10 minutes for webhooks
            'activity' => 1800,   // 30 minutes for activity
        ];

        return isset($windows[$namespace]) ? $windows[$namespace] : $this->rate_limit_settings['default_window'];
    }

    /**
     * Log API request
     *
     * @param string $method HTTP method
     * @param string $endpoint Endpoint
     * @param array $request_data Request data
     * @param int|null $user_id User ID
     */
    public function log_request($method, $endpoint, $request_data, $user_id = null) {
        $log_entry = [
            'timestamp' => time(),
            'method' => $method,
            'endpoint' => $endpoint,
            'user_id' => $user_id,
            'ip' => $this->get_client_ip(),
            'user_agent' => get_http_header('User-Agent'),
            'request_id' => wp_generate_uuid4(),
        ];

        // Add request data (sanitized)
        if (!empty($request_data)) {
            $sanitized_data = [];
            foreach ($request_data as $key => $value) {
                // Sanitize sensitive data
                if (in_array($key, ['password', 'token', 'secret', 'key'])) {
                    $sanitized_data[$key] = '[REDACTED]';
                } else {
                    $sanitized_data[$key] = is_string($value) ? sanitize_text_field($value) : $value;
                }
            }
            $log_entry['request_data'] = $sanitized_data;
        }

        // Store in transients (limited storage for performance)
        $log_key = self::REQUEST_LOG_PREFIX . $log_entry['request_id'];
        set_transient($log_key, $log_entry, 3600); // Keep for 1 hour

        // Also log to logger for important events
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $this->logger->info('API Request logged', $log_entry);
        } else {
            $this->logger->debug('API Request logged', $log_entry);
        }
    }

    /**
     * Log API response
     *
     * @param string $request_id Request ID
     * @param int $status_code HTTP status code
     * @param array $response_data Response data
     * @param float $execution_time Execution time in seconds
     */
    public function log_response($request_id, $status_code, $response_data, $execution_time) {
        $log_key = self::REQUEST_LOG_PREFIX . $request_id;
        $request_log = get_transient($log_key);

        if (!$request_log) {
            return; // No corresponding request log found
        }

        $response_entry = [
            'status_code' => $status_code,
            'execution_time' => $execution_time,
            'response_size' => strlen(json_encode($response_data)),
            'timestamp' => time(),
        ];

        // Merge with request log
        $full_log = array_merge($request_log, $response_entry);

        // Store updated log
        set_transient($log_key, $full_log, 3600); // Keep for 1 hour

        // Log to logger
        if ($status_code >= 400) {
            $this->logger->warning('API Error response logged', $full_log);
        } else {
            $this->logger->debug('API Response logged', $full_log);
        }
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        // Check for various proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get rate limit headers for response
     *
     * @param string $namespace
     * @param int $user_id
     * @return array
     */
    public function get_rate_limit_headers($namespace, $user_id = null) {
        $limit_info = $this->check_namespace_rate_limit($namespace, $user_id);
        
        if (is_wp_error($limit_info)) {
            return [
                'X-Rate-Limit-Limit' => $limit_info->get_error_data()['limit'] ?? 0,
                'X-Rate-Limit-Remaining' => 0,
                'X-Rate-Limit-Reset' => $limit_info->get_error_data()['reset'] ?? time()
            ];
        }

        return [
            'X-Rate-Limit-Limit' => $limit_info['limit'],
            'X-Rate-Limit-Remaining' => $limit_info['remaining'],
            'X-Rate-Limit-Reset' => $limit_info['reset']
        ];
    }

    /**
     * Update CORS settings
     *
     * @param array $settings
     * @return bool
     */
    public function update_cors_settings($settings) {
        $this->cors_settings = array_merge($this->cors_settings, $settings);
        return $this->state_manager->update_option(self::CORS_SETTINGS_OPTION, $this->cors_settings);
    }

    /**
     * Get CORS settings
     *
     * @return array
     */
    public function get_cors_settings() {
        return $this->cors_settings;
    }

    /**
     * Enable/disable CORS
     *
     * @param bool $enabled
     * @return bool
     */
    public function set_cors_enabled($enabled) {
        return $this->update_cors_settings(['enabled' => $enabled]);
    }
}