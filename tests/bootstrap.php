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

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        return null;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        return true;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

// Load the classes to test
require_once __DIR__ . '/../includes/Core/Crypto.php';
require_once __DIR__ . '/../includes/Core/StateManager.php';
require_once __DIR__ . '/../includes/Core/Logger.php';
require_once __DIR__ . '/../includes/Core/Bootstrap.php';
require_once __DIR__ . '/../includes/Database/DBAdapterInterface.php';
require_once __DIR__ . '/../includes/Database/WPDBAdapter.php';
require_once __DIR__ . '/../includes/Modules/ModuleInterface.php';
require_once __DIR__ . '/../includes/Modules/BaseModule.php';
require_once __DIR__ . '/../includes/Modules/ModuleRegistry.php';
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/MockStorage.php';
require_once __DIR__ . '/MockWPDB.php';