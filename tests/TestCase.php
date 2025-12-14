<?php
/**
 * Base test case for Newera plugin tests
 */

namespace Newera\Tests;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base test case class
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Set up WordPress constants for testing
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/');
        }
        
        if (!defined('AUTH_KEY')) {
            define('AUTH_KEY', 'test-auth-key-for-crypto-testing-123456789');
        }
        
        if (!defined('SECURE_AUTH_KEY')) {
            define('SECURE_AUTH_KEY', 'test-secure-auth-key-for-crypto-testing-987654321');
        }
        
        if (!defined('LOGGED_IN_KEY')) {
            define('LOGGED_IN_KEY', 'test-logged-in-key-for-crypto-testing-555666777');
        }
        
        if (!defined('NONCE_KEY')) {
            define('NONCE_KEY', 'test-nonce-key-for-crypto-testing-111222333');
        }
        
        if (!defined('AUTH_SALT')) {
            define('AUTH_SALT', 'test-auth-salt-for-crypto-testing-444555666');
        }
        
        if (!defined('SECURE_AUTH_SALT')) {
            define('SECURE_AUTH_SALT', 'test-secure-auth-salt-for-crypto-testing-777888999');
        }
        
        if (!defined('LOGGED_IN_SALT')) {
            define('LOGGED_IN_SALT', 'test-logged-in-salt-for-crypto-testing-000111222');
        }
        
        if (!defined('NONCE_SALT')) {
            define('NONCE_SALT', 'test-nonce-salt-for-crypto-testing-333444555');
        }
        
        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }
    
    /**
     * Mock WordPress functions used in the classes
     */
    private function mockWordPressFunctions() {
        // Mock get_site_option for crypto key storage
        if (!function_exists('get_site_option')) {
            function get_site_option($option_name, $default = false) {
                $test_options = \Newera\Tests\MockStorage::get_site_option($option_name);
                return $test_options !== null ? $test_options : $default;
            }
        }
        
        if (!function_exists('add_site_option')) {
            function add_site_option($option_name, $option_value, $deprecated = '', $autoload = 'yes') {
                return \Newera\Tests\MockStorage::add_site_option($option_name, $option_value);
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
    }
    
    /**
     * Clean up after test
     */
    protected function tearDown(): void {
        parent::tearDown();
        \Newera\Tests\MockStorage::clear_all();
    }
}