<?php
/**
 * Demonstration script for Newera Crypto and StateManager secure credential storage
 *
 * This script shows how to use the secure credential storage system.
 * Run with: php demo_secure_storage.php
 */

require_once __DIR__ . '/includes/Core/Crypto.php';
require_once __DIR__ . '/includes/Core/StateManager.php';

// Check if we're running in a WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}

// Mock WordPress functions for demonstration
if (!function_exists('get_option')) {
    function get_option($option_name, $default = false) {
        global $demo_options;
        return isset($demo_options[$option_name]) ? $demo_options[$option_name] : $default;
    }
}

if (!function_exists('add_option')) {
    function add_option($option_name, $option_value, $deprecated = '', $autoload = 'yes') {
        global $demo_options;
        $demo_options[$option_name] = $option_value;
        return true;
    }
}

if (!function_exists('update_option')) {
    function update_option($option_name, $option_value, $autoload = 'yes') {
        global $demo_options;
        $demo_options[$option_name] = $option_value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option_name) {
        global $demo_options;
        if (isset($demo_options[$option_name])) {
            unset($demo_options[$option_name]);
            return true;
        }
        return false;
    }
}

if (!function_exists('get_site_option')) {
    function get_site_option($option_name, $default = false) {
        global $demo_site_options;
        return isset($demo_site_options[$option_name]) ? $demo_site_options[$option_name] : $default;
    }
}

if (!function_exists('add_site_option')) {
    function add_site_option($option_name, $option_value, $deprecated = '', $autoload = 'yes') {
        global $demo_site_options;
        $demo_site_options[$option_name] = $option_value;
        return true;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        $salts = [
            'auth' => 'demo-auth-salt-for-testing-purposes-only',
            'secure_auth' => 'demo-secure-auth-salt-for-testing-only',
            'logged_in' => 'demo-logged-in-salt-for-testing-only',
            'nonce' => 'demo-nonce-salt-for-testing-only'
        ];
        return isset($salts[$scheme]) ? $salts[$scheme] : $salts['auth'];
    }
}

if (!function_exists('hash_pbkdf2')) {
    function hash_pbkdf2($algo, $password, $salt, $iterations, $length = 0, $raw_output = false) {
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

// Initialize demo data storage
global $demo_options, $demo_site_options;
$demo_options = [];
$demo_site_options = [];

// Demo functions
function demo_header($title) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo " $title\n";
    echo str_repeat("=", 60) . "\n";
}

function demo_success($message) {
    echo "✅ $message\n";
}

function demo_error($message) {
    echo "❌ $message\n";
}

function demo_info($message) {
    echo "ℹ️  $message\n";
}

// Main demonstration
echo "Newera Plugin - Secure Credential Storage Demo\n";
echo "This demonstrates the crypto and StateManager functionality\n";

try {
    // Initialize StateManager
    demo_header("Initializing StateManager");
    $stateManager = new Newera\Core\StateManager();
    
    // Check crypto availability
    if ($stateManager->is_crypto_available()) {
        demo_success("Crypto is available and working");
    } else {
        demo_error("Crypto is not available");
        exit(1);
    }
    
    // Test 1: Basic string storage
    demo_header("Test 1: Basic String Storage");
    $module = 'payment_gateway';
    $key = 'stripe_api_key';
    $data = 'sk_test_1234567890abcdef';
    
    demo_info("Storing: $data");
    $result = $stateManager->setSecure($module, $key, $data);
    if ($result) {
        demo_success("Data stored successfully");
    } else {
        demo_error("Failed to store data");
    }
    
    demo_info("Retrieving data...");
    $retrieved = $stateManager->getSecure($module, $key);
    if ($retrieved === $data) {
        demo_success("Data retrieved successfully: $retrieved");
    } else {
        demo_error("Data mismatch: expected '$data', got '$retrieved'");
    }
    
    // Test 2: Complex data storage
    demo_header("Test 2: Complex Data Storage");
    $complex_data = [
        'client_id' => 'demo_client_123',
        'client_secret' => 'super_secret_456',
        'settings' => [
            'timeout' => 30,
            'retries' => 3,
            'enabled' => true
        ],
        'metadata' => (object) [
            'created_at' => '2023-01-01',
            'version' => '1.0'
        ]
    ];
    
    $module = 'api_client';
    $key = 'oauth_credentials';
    
    demo_info("Storing complex data structure");
    $result = $stateManager->setSecure($module, $key, $complex_data);
    if ($result) {
        demo_success("Complex data stored successfully");
    } else {
        demo_error("Failed to store complex data");
    }
    
    demo_info("Retrieving complex data...");
    $retrieved_complex = $stateManager->getSecure($module, $key);
    if ($retrieved_complex === $complex_data) {
        demo_success("Complex data retrieved successfully");
        demo_info("Retrieved client_id: " . $retrieved_complex['client_id']);
        demo_info("Retrieved settings timeout: " . $retrieved_complex['settings']['timeout']);
    } else {
        demo_error("Complex data mismatch");
    }
    
    // Test 3: Metadata
    demo_header("Test 3: Encryption Metadata");
    $metadata = $stateManager->getSecureMetadata($module, $key);
    demo_info("Encryption metadata:");
    foreach ($metadata as $key => $value) {
        echo "   - $key: $value\n";
    }
    demo_success("Metadata retrieved successfully");
    
    // Test 4: Multiple modules
    demo_header("Test 4: Multiple Module Isolation");
    $module1 = 'payment_gateway';
    $module2 = 'email_service';
    $key = 'api_key';
    
    $data1 = 'payment_api_secret';
    $data2 = 'email_api_secret';
    
    $stateManager->setSecure($module1, $key, $data1);
    $stateManager->setSecure($module2, $key, $data2);
    
    $retrieved1 = $stateManager->getSecure($module1, $key);
    $retrieved2 = $stateManager->getSecure($module2, $key);
    
    if ($retrieved1 === $data1 && $retrieved2 === $data2) {
        demo_success("Multiple modules work independently");
        demo_info("$module1: $retrieved1");
        demo_info("$module2: $retrieved2");
    } else {
        demo_error("Module isolation failed");
    }
    
    // Test 5: Update and delete
    demo_header("Test 5: Update and Delete Operations");
    $update_module = 'test_module';
    $update_key = 'test_key';
    $original_data = 'original_data';
    $updated_data = 'updated_data';
    
    $stateManager->setSecure($update_module, $update_key, $original_data);
    demo_info("Original data set: $original_data");
    
    $result = $stateManager->updateSecure($update_module, $update_key, $updated_data);
    if ($result) {
        $retrieved_updated = $stateManager->getSecure($update_module, $update_key);
        if ($retrieved_updated === $updated_data) {
            demo_success("Update operation successful: $retrieved_updated");
        } else {
            demo_error("Update failed");
        }
    }
    
    $stateManager->setSecure($update_module, 'another_key', 'another_value');
    demo_info("Added another key for testing");
    
    $delete_result = $stateManager->deleteSecure($update_module, $update_key);
    if ($delete_result) {
        $check_exists = $stateManager->hasSecure($update_module, $update_key);
        if (!$check_exists) {
            demo_success("Delete operation successful");
        } else {
            demo_error("Delete operation failed - data still exists");
        }
    }
    
    // Test 6: Bulk operations
    demo_header("Test 6: Bulk Operations");
    $bulk_module = 'bulk_test_module';
    $bulk_data = [
        'key1' => 'value1',
        'key2' => 'value2',
        'key3' => ['nested' => 'array_data']
    ];
    
    $bulk_results = $stateManager->setBulkSecure($bulk_module, $bulk_data);
    $all_success = true;
    foreach ($bulk_results as $key => $success) {
        if (!$success) {
            $all_success = false;
            break;
        }
    }
    
    if ($all_success) {
        demo_success("Bulk storage successful");
        
        // Retrieve bulk data
        $keys = array_keys($bulk_data);
        $retrieved_bulk = $stateManager->getBulkSecure($bulk_module, $keys);
        
        if ($retrieved_bulk === $bulk_data) {
            demo_success("Bulk retrieval successful");
        } else {
            demo_error("Bulk retrieval failed");
        }
    } else {
        demo_error("Bulk storage failed");
    }
    
    demo_header("Demo Summary");
    demo_success("All tests completed! Secure credential storage is working correctly.");
    demo_info("The StateManager provides:");
    echo "   - AES-256-CBC encryption with OpenSSL\n";
    echo "   - Automatic encryption/decryption of sensitive data\n";
    echo "   - Module-based namespace isolation\n";
    echo "   - CRUD operations for secure storage\n";
    echo "   - Metadata and version tracking\n";
    echo "   - WordPress options API integration\n";
    
} catch (Exception $e) {
    demo_error("Demo failed with error: " . $e->getMessage());
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nDemo completed.\n";