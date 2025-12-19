#!/usr/bin/env php
<?php
/**
 * Integration Test Runner for Newera Plugin
 * 
 * Runs all integration tests with proper setup and reporting
 */

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

echo "=== Newera Plugin Integration Test Runner ===\n\n";

// Change to plugin directory
$plugin_dir = dirname(__FILE__, 2);
chdir($plugin_dir);

// Check if vendor/autoload.php exists (Composer dependencies)
$autoload_file = $plugin_dir . '/vendor/autoload.php';
if (file_exists($autoload_file)) {
    require_once $autoload_file;
} else {
    echo "Warning: Composer dependencies not found. Some tests may fail.\n\n";
}

// Set up test environment
define('ABSPATH', $plugin_dir . '/');
define('NEWERA_VERSION', '1.0.0');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', false);

// Mock WordPress functions needed for testing
if (!function_exists('wp_hash_password')) {
    function wp_hash_password($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = false) {
        return $gmt ? gmdate('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        $salts = [
            'auth' => 'test-auth-salt-' . md5('auth'),
            'secure_auth' => 'test-secure-auth-salt-' . md5('secure_auth'),
            'logged_in' => 'test-logged-in-salt-' . md5('logged_in'),
            'nonce' => 'test-nonce-salt-' . md5('nonce')
        ];
        return isset($salts[$scheme]) ? $salts[$scheme] : $salts['auth'];
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        return true;
    }
}

// Load PHPUnit
$phpunit_autoload = $plugin_dir . '/vendor/bin/phpunit';
if (!file_exists($phpunit_autoload)) {
    // Try to find PHPUnit in vendor
    $phpunit_files = [
        $plugin_dir . '/vendor/phpunit/phpunit/phpunit',
        $plugin_dir . '/phpunit',
        '/usr/bin/phpunit'
    ];
    
    $phpunit_path = null;
    foreach ($phpunit_files as $path) {
        if (file_exists($path)) {
            $phpunit_path = $path;
            break;
        }
    }
    
    if ($phpunit_path === null) {
        echo "Error: PHPUnit not found. Please install PHPUnit or run 'composer install'.\n";
        echo "Tests could not be executed.\n";
        exit(1);
    }
} else {
    $phpunit_path = $phpunit_autoload;
}

// Load test configuration
$test_config = [
    'test_files' => [
        'tests/Integration/IntegrationTestCase.php',
        'tests/Integration/SetupWizardIntegrationTest.php',
        'tests/Integration/AuthenticationIntegrationTest.php',
        'tests/Integration/DatabaseIntegrationTest.php',
        'tests/Integration/PaymentsIntegrationTest.php',
        'tests/Integration/ProjectSyncIntegrationTest.php',
        'tests/Integration/AIIntegrationTest.php',
        'tests/Integration/CredentialIsolationTest.php'
    ],
    'bootstrap' => 'tests/bootstrap.php',
    'phpunit_config' => 'phpunit.xml'
];

// Check if test files exist
echo "Checking test files...\n";
foreach ($test_config['test_files'] as $test_file) {
    $full_path = $plugin_dir . '/' . $test_file;
    if (file_exists($full_path)) {
        echo "✓ {$test_file}\n";
    } else {
        echo "✗ {$test_file} (missing)\n";
    }
}

echo "\n";

// Create enhanced bootstrap file for integration tests
$integration_bootstrap = $plugin_dir . '/tests/integration_bootstrap.php';
$bootstrap_content = '<?php
/**
 * Integration Test Bootstrap
 * Sets up the environment for integration tests
 */

// Load main bootstrap
require_once __DIR__ . "/bootstrap.php";

// Load integration test utilities
require_once __DIR__ . "/Mock/MockWPDB.php";
require_once __DIR__ . "/Mock/MockStorage.php";
require_once __DIR__ . "/Mock/MockHTTP.php";

// Set up additional mocks for integration testing
if (!function_exists("wp_remote_request")) {
    function wp_remote_request($url, $args = []) {
        return \Newera\Tests\Mock\MockHTTP::mockRequest($url, $args);
    }
}

if (!function_exists("wp_mail")) {
    function wp_mail($to, $subject, $message, $headers = "", $attachments = []) {
        \Newera\Tests\Mock\MockStorage::add_email([
            "to" => $to,
            "subject" => $subject, 
            "message" => $message,
            "headers" => $headers,
            "attachments" => $attachments,
            "timestamp" => time()
        ]);
        return true;
    }
}

echo "Integration test environment loaded.\n";
';

file_put_contents($integration_bootstrap, $bootstrap_content);

// Create specific integration test configuration
$integration_phpunit_config = $plugin_dir . '/phpunit_integration.xml';
$integration_config_content = '<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.0/phpunit.xsd"
         bootstrap="tests/integration_bootstrap.php"
         colors="true"
         verbose="true"
         convertErrorsToExceptions="true"
         convertNoticesToExceptions="true"
         convertWarningsToExceptions="true"
         stopOnFailure="false">
    
    <testsuites>
        <testsuite name="Integration Tests">
            <directory suffix="IntegrationTest.php">tests/Integration</directory>
        </testsuite>
    </testsuites>
    
    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">includes</directory>
            <directory suffix=".php">modules</directory>
            <exclude>
                <directory>vendor</directory>
                <directory>tests</directory>
            </exclude>
        </whitelist>
    </filter>
</phpunit>
';

file_put_contents($integration_phpunit_config, $integration_config_content);

echo "Running Integration Tests...\n\n";

// Run integration tests
$phpunit_cmd = "{$phpunit_path} -c {$integration_phpunit_config}";
echo "Command: {$phpunit_cmd}\n\n";

passthru($phpunit_cmd, $exit_code);

echo "\n=== Integration Test Summary ===\n";
echo "Exit Code: {$exit_code}\n";

if ($exit_code === 0) {
    echo "✅ All integration tests passed successfully!\n";
    echo "\nIntegration Test Coverage Includes:\n";
    echo "  • Setup Wizard workflows and data persistence\n";
    echo "  • Authentication and OAuth provider integration\n";
    echo "  • Database operations and external DB fallback\n";
    echo "  • Payment processing and webhook handling\n";
    echo "  • Project sync and external service integration\n";
    echo "  • AI provider configuration and cost tracking\n";
    echo "  • Credential isolation between modules\n";
    echo "  • Cross-module data flow verification\n";
} else {
    echo "❌ Some integration tests failed.\n";
    echo "Please check the output above for details.\n";
}

echo "\n=== Test Environment Info ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Plugin Directory: {$plugin_dir}\n";
echo "Test Runner: " . __FILE__ . "\n";

// Clean up temporary files
if (file_exists($integration_bootstrap)) {
    unlink($integration_bootstrap);
}

if (file_exists($integration_phpunit_config)) {
    unlink($integration_phpunit_config);
}

exit($exit_code);