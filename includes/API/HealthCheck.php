<?php
/**
 * Health Check Endpoint
 * 
 * Provides a simple health check endpoint for monitoring and load balancers
 * Access at: /wp-json/newera/v1/health
 */

namespace Newera\API;

if (!defined('ABSPATH')) {
    exit;
}

class HealthCheck {
    /**
     * Initialize health check endpoint
     */
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        register_rest_route('newera/v1', '/health', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_health_status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('newera/v1', '/health/detailed', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_detailed_health'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
    }

    /**
     * Get basic health status
     */
    public static function get_health_status() {
        global $wpdb;

        $status = 'healthy';
        $checks = [];

        // Database check
        $db_check = $wpdb->query('SELECT 1');
        $checks['database'] = $db_check !== false;
        
        if (!$checks['database']) {
            $status = 'unhealthy';
        }

        // Plugin active check
        $checks['plugin_active'] = is_plugin_active('newera/newera.php');
        
        if (!$checks['plugin_active']) {
            $status = 'degraded';
        }

        // Response
        $response = [
            'status' => $status,
            'timestamp' => current_time('mysql'),
            'checks' => $checks,
        ];

        // Set appropriate HTTP status code
        $http_status = $status === 'healthy' ? 200 : ($status === 'degraded' ? 200 : 503);

        return new \WP_REST_Response($response, $http_status);
    }

    /**
     * Get detailed health information
     */
    public static function get_detailed_health() {
        global $wpdb;

        $health = [
            'status' => 'healthy',
            'timestamp' => current_time('mysql'),
            'version' => NEWERA_VERSION,
            'checks' => [],
            'system' => [],
            'modules' => [],
        ];

        // Database checks
        $health['checks']['database'] = [
            'status' => $wpdb->query('SELECT 1') !== false,
            'type' => DB_HOST,
            'name' => DB_NAME,
        ];

        // WordPress checks
        $health['checks']['wordpress'] = [
            'version' => get_bloginfo('version'),
            'multisite' => is_multisite(),
            'debug_mode' => WP_DEBUG,
        ];

        // PHP checks
        $health['system']['php'] = [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'pdo_pgsql' => extension_loaded('pdo_pgsql'),
                'openssl' => extension_loaded('openssl'),
            ],
        ];

        // Module status
        if (class_exists('\\Newera\\Core\\Bootstrap')) {
            $bootstrap = \Newera\Core\Bootstrap::getInstance();
            $registry = $bootstrap->get_modules_registry();
            
            if ($registry) {
                $modules = $registry->get_modules();
                foreach ($modules as $module_id => $module) {
                    $health['modules'][$module_id] = [
                        'enabled' => $registry->is_module_enabled($module_id),
                        'name' => $module['name'],
                    ];
                }
            }
        }

        // External database check if configured
        if (class_exists('\\Newera\\Database\\DBAdapterFactory')) {
            try {
                $db_factory = apply_filters('newera_get_db_factory', null);
                if ($db_factory) {
                    $health['checks']['external_db'] = [
                        'configured' => true,
                        'fallback_active' => $db_factory->is_fallback_active(),
                        'type' => $db_factory->get_active_type(),
                    ];
                }
            } catch (\Exception $e) {
                $health['checks']['external_db'] = [
                    'configured' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Determine overall health
        $unhealthy = false;
        foreach ($health['checks'] as $check => $data) {
            if (is_array($data) && isset($data['status']) && !$data['status']) {
                $unhealthy = true;
                break;
            }
        }

        $health['status'] = $unhealthy ? 'unhealthy' : 'healthy';

        return new \WP_REST_Response($health, $unhealthy ? 503 : 200);
    }

    /**
     * Check if user has permission to view detailed health
     */
    public static function check_permission() {
        return current_user_can('manage_options');
    }
}

// Initialize health check
HealthCheck::init();
