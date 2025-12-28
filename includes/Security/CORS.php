<?php
/**
 * CORS Configuration
 */

namespace Newera\Security;

if (!defined('ABSPATH')) {
    exit;
}

class CORS {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'add_cors_headers']);
        add_action('init', [__CLASS__, 'handle_preflight_request']);
    }

    public static function add_cors_headers() {
        $origin = self::get_allowed_origin();
        
        if ($origin) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
    }

    public static function handle_preflight_request() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $origin = self::get_allowed_origin();
            
            if ($origin) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');
                status_header(200);
                exit;
            }
        }
    }

    private static function get_allowed_origin() {
        $request_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        
        if (!$request_origin) {
            return false;
        }

        $allowed_origins = self::get_allowed_origins();
        
        if (in_array($request_origin, $allowed_origins, true)) {
            return $request_origin;
        }
        
        foreach ($allowed_origins as $allowed_origin) {
            if (self::matches_wildcard($allowed_origin, $request_origin)) {
                return $request_origin;
            }
        }
        
        return false;
    }

    private static function get_allowed_origins() {
        $default_origins = [get_site_url(), get_home_url()];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $default_origins[] = 'http://localhost:3000';
            $default_origins[] = 'http://localhost:8080';
            $default_origins[] = 'http://localhost:8081';
        }

        $custom_origins = apply_filters('newera_cors_allowed_origins', []);
        return array_merge($default_origins, $custom_origins);
    }

    private static function matches_wildcard($pattern, $origin) {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
        return preg_match($regex, $origin) === 1;
    }
}

CORS::init();
