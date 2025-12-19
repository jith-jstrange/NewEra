<?php
/**
 * Bootstrap file for tests
 */

// Set test environment constants
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// WordPress salts for testing
define('AUTH_KEY', 'test-auth-key-for-crypto-testing-123456789');
define('SECURE_AUTH_KEY', 'test-secure-auth-key-for-crypto-testing-987654321');
define('LOGGED_IN_KEY', 'test-logged-in-key-for-crypto-testing-555666777');
define('NONCE_KEY', 'test-nonce-key-for-crypto-testing-111222333');
define('AUTH_SALT', 'test-auth-salt-for-crypto-testing-444555666');
define('SECURE_AUTH_SALT', 'test-secure-auth-salt-for-crypto-testing-777888999');
define('LOGGED_IN_SALT', 'test-logged-in-salt-for-crypto-testing-000111222');
define('NONCE_SALT', 'test-nonce-salt-for-crypto-testing-333444555');

// Plugin constants
define('NEWERA_VERSION', '1.0.0');
define('NEWERA_PLUGIN_FILE', __DIR__ . '/../newera.php');
define('NEWERA_PLUGIN_PATH', __DIR__ . '/../');
define('NEWERA_INCLUDES_PATH', __DIR__ . '/../includes/');
define('NEWERA_MODULES_PATH', __DIR__ . '/../modules/');
define('NEWERA_TEMPLATES_PATH', __DIR__ . '/../templates/');

// WordPress constants for testing
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content');
define('WP_DEBUG_LOG', true);
define('HOUR_IN_SECONDS', 3600);

// Mock WordPress functions used in the classes
if (!function_exists('get_site_option')) {
    function get_site_option($option_name, $default = false) {
        return \Newera\Tests\MockStorage::get_site_option($option_name, $default);
    }
}

if (!function_exists('add_site_option')) {
    function add_site_option($option_name, $option_value, $deprecated = '', $autoload = 'yes') {
        return \Newera\Tests\MockStorage::add_site_option($option_name, $option_value);
    }
}

if (!function_exists('get_option')) {
    function get_option($option_name, $default = false) {
        return \Newera\Tests\MockStorage::get_option($option_name, $default);
    }
}

if (!function_exists('add_option')) {
    function add_option($option_name, $option_value, $deprecated = '', $autoload = 'yes') {
        return \Newera\Tests\MockStorage::add_option($option_name, $option_value, $deprecated, $autoload);
    }
}

if (!function_exists('update_option')) {
    function update_option($option_name, $option_value, $autoload = 'yes') {
        return \Newera\Tests\MockStorage::update_option($option_name, $option_value, $autoload);
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option_name) {
        return \Newera\Tests\MockStorage::delete_option($option_name);
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        $salts = [
            'auth' => 'test-auth-salt-for-crypto-testing-444555666',
            'secure_auth' => 'test-secure-auth-salt-for-crypto-testing-777888999',
            'logged_in' => 'test-logged-in-salt-for-crypto-testing-000111222',
            'nonce' => 'test-nonce-salt-for-crypto-testing-333444555'
        ];
        return isset($salts[$scheme]) ? $salts[$scheme] : $salts['auth'];
    }
}

if (!function_exists('hash_pbkdf2')) {
    function hash_pbkdf2($algo, $password, $salt, $iterations, $length = 0, $raw_output = false) {
        // Simple PBKDF2 implementation for testing
        $hash = hash($algo, $password . $salt);
        for ($i = 1; $i < $iterations; $i++) {
            $hash = hash($algo, $hash . $password . $salt);
        }
        return $raw_output ? hex2bin($hash) : $hash;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = false) {
        return $gmt ? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()-_=+[]{}|;:,.<>?';
        }
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url, $protocols = null) {
        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return '';
        }
        return $url;
    }
}

if (!function_exists('esc_sql')) {
    function esc_sql($data) {
        if (is_array($data)) {
            return array_map(__FUNCTION__, $data);
        }
        return addslashes($data);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($string) {
        return strip_tags($string, '<p><div><span><a><b><i><strong><em>');
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($pathname, $mode = 0777) {
        return @mkdir($pathname, $mode, true);
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url() {
        return 'http://example.com';
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        // Mock function - returns null in tests
        return null;
    }
}

if (!function_exists('user_can')) {
    function user_can($user_id, $capability) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        \Newera\Tests\MockStorage::update_option('transient_' . $transient, [
            'value' => $value,
            'expiration' => time() + $expiration
        ]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        $data = \Newera\Tests\MockStorage::get_option('transient_' . $transient);
        if (!$data || (isset($data['expiration']) && $data['expiration'] < time())) {
            return false;
        }
        return isset($data['value']) ? $data['value'] : false;
    }
}

if (!function_exists('get_http_header')) {
    function get_http_header($header_name) {
        $server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $header_name));
        return isset($_SERVER[$server_key]) ? $_SERVER[$server_key] : null;
    }
}

// Load the classes to test
require_once __DIR__ . '/../includes/Core/Crypto.php';
require_once __DIR__ . '/../includes/Core/StateManager.php';
require_once __DIR__ . '/../includes/Core/Logger.php';
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/MockStorage.php';