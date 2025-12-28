<?php
/**
 * Rate Limiting for API Endpoints
 */

namespace Newera\Security;

if (!defined('ABSPATH')) {
    exit;
}

class RateLimiter {
    private static $prefix = 'newera_rate_limit_';

    public static function init() {
        add_filter('rest_pre_dispatch', [__CLASS__, 'check_rate_limit'], 10, 3);
    }

    public static function check_rate_limit($result, $server, $request) {
        $route = $request->get_route();
        
        if (strpos($route, '/newera/v1/') !== 0) {
            return $result;
        }

        if (current_user_can('manage_options')) {
            return $result;
        }

        $client_id = self::get_client_identifier();
        
        if (self::is_rate_limited($client_id, $route)) {
            return new \WP_Error(
                'rate_limit_exceeded',
                'Too many requests. Please try again later.',
                ['status' => 429]
            );
        }

        return $result;
    }

    private static function is_rate_limited($client_id, $route) {
        $config = self::get_rate_limit_config($route);
        $transient_key = self::$prefix . md5($client_id . $route);
        $requests = get_transient($transient_key);
        
        if ($requests === false) {
            set_transient($transient_key, 1, $config['window']);
            return false;
        }
        
        if ($requests >= $config['limit']) {
            return true;
        }
        
        set_transient($transient_key, $requests + 1, $config['window']);
        return false;
    }

    private static function get_rate_limit_config($route) {
        $defaults = ['limit' => 60, 'window' => 60];
        $route_limits = apply_filters('newera_rate_limits', [
            '/newera/v1/webhook' => ['limit' => 100, 'window' => 60],
            '/newera/v1/health' => ['limit' => 120, 'window' => 60],
        ]);

        foreach ($route_limits as $pattern => $config) {
            if (strpos($route, $pattern) === 0) {
                return array_merge($defaults, $config);
            }
        }

        return $defaults;
    }

    private static function get_client_identifier() {
        $ip = self::get_client_ip();
        return is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . $ip;
    }

    private static function get_client_ip() {
        $ip = '';
        
        // Check if behind a trusted proxy (configure via filter)
        $trusted_proxies = apply_filters('newera_trusted_proxies', []);
        $is_trusted_proxy = false;
        
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $remote_addr = $_SERVER['REMOTE_ADDR'];
            foreach ($trusted_proxies as $proxy) {
                if (strpos($remote_addr, $proxy) === 0) {
                    $is_trusted_proxy = true;
                    break;
                }
            }
        }
        
        // Use forwarded IP only if from trusted proxy
        if ($is_trusted_proxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Get first IP from chain (original client)
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            // Use direct connection IP
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate and sanitize IP
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        return $ip ? $ip : '0.0.0.0';
    }
}

RateLimiter::init();
