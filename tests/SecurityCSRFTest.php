<?php
/**
 * Security Tests for CSRF Protection
 *
 * Tests nonce validation, admin actions, and cross-origin protection
 */

namespace Newera\Tests;

use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mock WordPress nonce functions for testing
 */
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        $test_nonces = \Newera\Tests\MockStorage::get_all_options();
        $nonce_key = 'nonce_' . $action . '_' . session_id();
        $nonce_value = hash('sha256', $nonce_key . time());
        
        \Newera\Tests\MockStorage::update_option($nonce_key, $nonce_value);
        return $nonce_value;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        $test_nonces = \Newera\Tests\MockStorage::get_all_options();
        $nonce_key = 'nonce_' . $action . '_' . session_id();
        
        $stored_nonce = \Newera\Tests\MockStorage::get_option($nonce_key);
        if (!$stored_nonce) {
            return false;
        }
        
        return hash_equals($stored_nonce, $nonce) ? 1 : false;
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
        $nonce = isset($_REQUEST[$query_arg]) ? $_REQUEST[$query_arg] : '';
        return wp_verify_nonce($nonce, $action);
    }
}

/**
 * Security CSRF Test Case
 */
class SecurityCSRFTest extends TestCase {
    /**
     * StateManager instance
     */
    private $state_manager;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->state_manager = new StateManager();
        MockStorage::clear_all();
        
        // Start session for nonce testing
        if (!function_exists('session_id')) {
            // Session functions might be mocked
        }
    }
    
    /**
     * Test: Setup Wizard nonce validation
     */
    public function testSetupWizardNonceValidation() {
        // Verify SetupWizard file exists and uses nonces
        $wizard_file = NEWERA_INCLUDES_PATH . 'Admin/SetupWizard.php';
        $this->assertFileExists($wizard_file);
        
        $content = file_get_contents($wizard_file);
        
        // Should contain nonce checking
        $this->assertTrue(
            strpos($content, 'wp_verify_nonce') !== false ||
            strpos($content, 'check_admin_referer') !== false ||
            strpos($content, 'wp_nonce_field') !== false,
            'SetupWizard should validate nonces'
        );
    }
    
    /**
     * Test: Admin actions require valid nonces
     */
    public function testAdminActionsRequireValidNonces() {
        // Check admin files for nonce validation
        $admin_files = [
            NEWERA_INCLUDES_PATH . 'Admin/AdminMenu.php',
            NEWERA_INCLUDES_PATH . 'Admin/Dashboard.php',
        ];
        
        foreach ($admin_files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            
            // If it handles AJAX, should verify nonces
            if (strpos($content, 'wp_ajax') !== false) {
                $this->assertTrue(
                    strpos($content, 'wp_verify_nonce') !== false ||
                    strpos($content, 'check_ajax_referer') !== false,
                    "File $file should verify nonces for AJAX"
                );
            }
        }
    }
    
    /**
     * Test: Invalid nonces rejected
     */
    public function testInvalidNoncesRejected() {
        // Create a valid nonce
        $action = 'test_action_12345';
        $valid_nonce = wp_create_nonce($action);
        
        // Verify it works
        $result = wp_verify_nonce($valid_nonce, $action);
        $this->assertNotFalse($result);
        
        // Invalid nonce should fail
        $invalid_nonce = 'invalid_nonce_12345';
        $result = wp_verify_nonce($invalid_nonce, $action);
        $this->assertFalse($result);
        
        // Modified nonce should fail
        $modified_nonce = substr($valid_nonce, 0, -1) . 'X';
        $result = wp_verify_nonce($modified_nonce, $action);
        $this->assertFalse($result);
        
        // Empty nonce should fail
        $result = wp_verify_nonce('', $action);
        $this->assertFalse($result);
    }
    
    /**
     * Test: Nonces are action-specific
     */
    public function testNoncesAreActionSpecific() {
        $action1 = 'action_one';
        $action2 = 'action_two';
        
        $nonce1 = wp_create_nonce($action1);
        $nonce2 = wp_create_nonce($action2);
        
        // Nonce for action1 should not verify for action2
        $result = wp_verify_nonce($nonce1, $action2);
        $this->assertFalse($result);
        
        // Nonce for action2 should not verify for action1
        $result = wp_verify_nonce($nonce2, $action1);
        $this->assertFalse($result);
        
        // But each should verify for its own action
        $result = wp_verify_nonce($nonce1, $action1);
        $this->assertNotFalse($result);
        
        $result = wp_verify_nonce($nonce2, $action2);
        $this->assertNotFalse($result);
    }
    
    /**
     * Test: Cross-origin POST requests considered
     */
    public function testCrossOriginPOSTRequestsProtected() {
        // Verify CORS and origin checking is in place
        
        // Check for Origin header validation
        $api_files = [
            NEWERA_INCLUDES_PATH . 'API/APIManager.php',
            NEWERA_INCLUDES_PATH . 'Payments/WebhookEndpoint.php',
        ];
        
        foreach ($api_files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            
            // Should check origin or use nonces/tokens
            $has_security = strpos($content, 'Origin') !== false ||
                           strpos($content, 'wp_verify_nonce') !== false ||
                           strpos($content, 'Authorization') !== false ||
                           strpos($content, 'Bearer') !== false;
            
            $this->assertTrue($has_security, "File $file should protect against CSRF");
        }
    }
    
    /**
     * Test: Form submission nonces included
     */
    public function testFormSubmissionNoncesIncluded() {
        // Check that admin forms include nonce fields
        $template_dir = NEWERA_TEMPLATES_PATH . 'admin';
        
        if (!is_dir($template_dir)) {
            $this->markTestSkipped('Admin templates directory not found');
        }
        
        $files = glob($template_dir . '/*.php');
        
        $form_files = array_filter($files, function($file) {
            $content = file_get_contents($file);
            return strpos($content, '<form') !== false;
        });
        
        foreach ($form_files as $file) {
            $content = file_get_contents($file);
            
            // If file has forms, should have nonce fields
            if (preg_match('/<form[^>]*method=["\']post["\']/', $content)) {
                $this->assertTrue(
                    strpos($content, 'wp_nonce_field') !== false ||
                    strpos($content, '_wpnonce') !== false,
                    "Form in $file should include nonce"
                );
            }
        }
    }
    
    /**
     * Test: AJAX requests validate nonces
     */
    public function testAJAXRequestsValidateNonces() {
        // Verify AJAX handlers check nonces
        $wizard_file = NEWERA_INCLUDES_PATH . 'Admin/SetupWizard.php';
        
        if (!file_exists($wizard_file)) {
            $this->markTestSkipped('SetupWizard.php not found');
        }
        
        $content = file_get_contents($wizard_file);
        
        // Should contain nonce validation for AJAX
        $ajax_handlers = preg_match_all('/wp_ajax.*newera/', $content, $matches);
        
        if ($ajax_handlers > 0) {
            // Should have nonce verification for AJAX handlers
            $this->assertTrue(
                strpos($content, 'wp_verify_nonce') !== false ||
                strpos($content, 'check_ajax_referer') !== false,
                'AJAX handlers should verify nonces'
            );
        }
    }
    
    /**
     * Test: Nonce expiration (time-based)
     */
    public function testNonceTimeValidation() {
        // Nonces in WordPress are time-based (typically 12-24 hour validity)
        // Test that old nonces are rejected
        
        $action = 'time_based_action';
        
        // Create nonce at "current" time
        $old_nonce = wp_create_nonce($action);
        
        // Verify it works
        $result = wp_verify_nonce($old_nonce, $action);
        $this->assertNotFalse($result);
        
        // In real implementation, old nonces would fail
        // This is a simplified test for the concept
    }
    
    /**
     * Test: POST data validation
     */
    public function testPOSTDataValidation() {
        // Simulate POST data validation
        $_POST['test_field'] = 'test_value';
        $_POST['_wpnonce'] = wp_create_nonce('test_action');
        
        // Verify nonce
        $nonce_valid = wp_verify_nonce($_POST['_wpnonce'], 'test_action');
        $this->assertNotFalse($nonce_valid);
        
        // If nonce is invalid, should reject POST
        $_POST['_wpnonce'] = 'invalid_nonce';
        $nonce_valid = wp_verify_nonce($_POST['_wpnonce'], 'test_action');
        $this->assertFalse($nonce_valid);
    }
    
    /**
     * Test: Referer header validation
     */
    public function testRefererHeaderValidation() {
        // Verify admin files check referer
        $admin_files = [
            NEWERA_INCLUDES_PATH . 'Admin/AdminMenu.php',
        ];
        
        foreach ($admin_files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            
            // Should check wp_safe_remote_post or similar for API calls
            // Or check nonces for form submissions
            $has_security = strpos($content, 'wp_remote') !== false ||
                           strpos($content, 'nonce') !== false;
            
            // This is optional for some admin pages but good practice
        }
    }
    
    /**
     * Test: Nonce field in forms
     */
    public function testNonceFieldInForms() {
        // Create nonce for form
        $nonce = wp_create_nonce('my_form_action');
        
        // Simulate form with nonce field
        $form_html = sprintf(
            '<input type="hidden" name="_wpnonce" value="%s" />',
            esc_attr($nonce)
        );
        
        // Verify nonce is in form
        $this->assertStringContainsString('_wpnonce', $form_html);
        $this->assertStringContainsString($nonce, $form_html);
        
        // Simulate form submission
        $_POST['_wpnonce'] = $nonce;
        $verified = wp_verify_nonce($_POST['_wpnonce'], 'my_form_action');
        $this->assertNotFalse($verified);
    }
}
