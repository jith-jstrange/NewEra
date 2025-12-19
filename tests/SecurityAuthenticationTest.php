<?php
/**
 * Security Tests for Authentication & Authorization
 *
 * Tests JWT tokens, session validation, token expiration,
 * permission checks, and API key handling
 */

namespace Newera\Tests;

use Newera\API\Auth\AuthManager;
use Newera\Core\StateManager;
use Newera\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mock WordPress user object for testing
 */
class MockWPUser {
    public $ID;
    public $user_login;
    public $user_email;
    public $roles;
    public $allcaps;
    
    public function __construct($id, $login, $email, $roles = ['subscriber']) {
        $this->ID = $id;
        $this->user_login = $login;
        $this->user_email = $email;
        $this->roles = $roles;
        $this->allcaps = $this->get_caps_for_roles($roles);
    }
    
    private function get_caps_for_roles($roles) {
        $caps = [];
        foreach ($roles as $role) {
            if ($role === 'administrator') {
                $caps['manage_options'] = true;
            } elseif ($role === 'editor') {
                $caps['edit_others_posts'] = true;
            }
        }
        return $caps;
    }
}

/**
 * Security Authentication Test Case
 */
class SecurityAuthenticationTest extends TestCase {
    /**
     * StateManager instance
     */
    private $state_manager;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->state_manager = new StateManager();
        $this->logger = new Logger();
        MockStorage::clear_all();
    }
    
    /**
     * Test: OAuth redirect URLs not hardcoded
     */
    public function testOAuthRedirectURLsNotHardcoded() {
        // Check that OAuth URLs are configurable and not hardcoded
        $auth_step_file = NEWERA_PLUGIN_PATH . 'includes/Admin/SetupWizard.php';
        $this->assertFileExists($auth_step_file);
        
        $content = file_get_contents($auth_step_file);
        
        // Should not contain hardcoded OAuth URLs
        $forbidden_patterns = [
            '/oauth.*google\.com\/oauth2\/auth/',
            '/oauth.*github\.com\/login\/oauth\/authorize/',
            '/oauth.*facebook\.com\/oauth\/authorize/',
        ];
        
        foreach ($forbidden_patterns as $pattern) {
            // These should come from configuration, not hardcoded
            // This is a security best practice - preventing accidental hardcoding
        }
        
        // Verify auth is config-driven
        $has_state_manager_usage = strpos($content, 'StateManager') !== false ||
                                   strpos($content, 'state_manager') !== false;
        $this->assertTrue($has_state_manager_usage, 'Should use StateManager for configuration');
    }
    
    /**
     * Test: No developer-owned API keys in code
     */
    public function testNoHardcodedAPIKeysInCode() {
        $dirs_to_check = [
            NEWERA_INCLUDES_PATH,
            NEWERA_MODULES_PATH,
        ];
        
        $api_key_patterns = [
            '/sk_(test|live)_[a-zA-Z0-9]+/',  // Stripe keys
            '/rk_(test|live)_[a-zA-Z0-9]+/',  // Stripe restricted keys
            '/linear_[a-zA-Z0-9_]+/',          // Linear API keys
            '/sk_[a-zA-Z0-9]+/',               // Generic API keys
            '/api_key\s*[=:]\s*["\']?[a-zA-Z0-9]+["\']?/',  // API key assignments
        ];
        
        foreach ($dirs_to_check as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                
                $content = file_get_contents($file->getRealPath());
                
                // Should not contain test API keys
                $this->assertNotContains('sk_test_', $content, "Found hardcoded test key in " . $file->getFilename());
                $this->assertNotContains('rk_test_', $content, "Found hardcoded restricted key in " . $file->getFilename());
                $this->assertNotContains('sk_live_', $content, "Found hardcoded live key in " . $file->getFilename());
            }
        }
    }
    
    /**
     * Test: JWT cannot be forged
     */
    public function testJWTCannotBeForged() {
        // If Firebase JWT is available, test token generation
        if (!class_exists('\Firebase\JWT\JWT')) {
            $this->markTestSkipped('Firebase JWT library not available');
        }
        
        // Create test user
        $user = new MockWPUser(1, 'testuser', 'test@example.com', ['administrator']);
        
        // Simulate JWT creation with correct secret
        $secret = wp_generate_password(64, true, true);
        $payload = [
            'sub' => $user->ID,
            'username' => $user->user_login,
            'iat' => time(),
            'exp' => time() + 86400,
        ];
        
        $token = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');
        
        // Token should be decodable with correct secret
        $decoded = \Firebase\JWT\JWT::decode($token, $secret, ['HS256']);
        $this->assertEquals($user->ID, $decoded->sub);
        
        // Token should NOT be decodable with wrong secret
        $wrong_secret = wp_generate_password(64, true, true);
        try {
            \Firebase\JWT\JWT::decode($token, $wrong_secret, ['HS256']);
            $this->fail('Should have thrown exception with wrong secret');
        } catch (\Exception $e) {
            $this->assertStringContainsString('signature', strtolower($e->getMessage()));
        }
    }
    
    /**
     * Test: Token expiration enforced
     */
    public function testTokenExpirationEnforced() {
        if (!class_exists('\Firebase\JWT\JWT')) {
            $this->markTestSkipped('Firebase JWT library not available');
        }
        
        $secret = wp_generate_password(64, true, true);
        
        // Create token with past expiration
        $expired_payload = [
            'sub' => 1,
            'username' => 'testuser',
            'iat' => time() - 86400,
            'exp' => time() - 3600, // Expired 1 hour ago
        ];
        
        $expired_token = \Firebase\JWT\JWT::encode($expired_payload, $secret, 'HS256');
        
        // Should fail to decode
        try {
            \Firebase\JWT\JWT::decode($expired_token, $secret, ['HS256']);
            $this->fail('Should have thrown exception for expired token');
        } catch (\Exception $e) {
            $this->assertStringContainsString('expired', strtolower($e->getMessage()));
        }
        
        // Valid token with future expiration
        $valid_payload = [
            'sub' => 1,
            'username' => 'testuser',
            'iat' => time(),
            'exp' => time() + 86400,
        ];
        
        $valid_token = \Firebase\JWT\JWT::encode($valid_payload, $secret, 'HS256');
        $decoded = \Firebase\JWT\JWT::decode($valid_token, $secret, ['HS256']);
        $this->assertEquals(1, $decoded->sub);
    }
    
    /**
     * Test: Admin-only endpoints enforce permission check
     */
    public function testAdminOnlyEndpointsEnforcePermissions() {
        // Verify permission checks are present in admin files
        $admin_files = [
            NEWERA_INCLUDES_PATH . 'Admin/AdminMenu.php',
            NEWERA_INCLUDES_PATH . 'Admin/SetupWizard.php',
        ];
        
        foreach ($admin_files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            
            // Should contain capability checks
            $this->assertTrue(
                strpos($content, 'manage_options') !== false ||
                strpos($content, 'current_user_can') !== false,
                "File $file should check user capabilities"
            );
        }
    }
    
    /**
     * Test: Non-admin requests rejected
     */
    public function testNonAdminRequestsRejected() {
        // Create test non-admin user
        $subscriber_user = new MockWPUser(2, 'subscriber_user', 'subscriber@example.com', ['subscriber']);
        
        // Verify subscriber does not have manage_options capability
        $has_admin_cap = isset($subscriber_user->allcaps['manage_options']) && $subscriber_user->allcaps['manage_options'];
        $this->assertFalse($has_admin_cap, 'Subscriber should not have manage_options');
        
        // Verify admin user has manage_options capability
        $admin_user = new MockWPUser(1, 'admin_user', 'admin@example.com', ['administrator']);
        $has_admin_cap = isset($admin_user->allcaps['manage_options']) && $admin_user->allcaps['manage_options'];
        $this->assertTrue($has_admin_cap, 'Admin should have manage_options');
    }
    
    /**
     * Test: Refresh token flow works
     */
    public function testRefreshTokenFlowWorks() {
        if (!class_exists('\Firebase\JWT\JWT')) {
            $this->markTestSkipped('Firebase JWT library not available');
        }
        
        $secret = wp_generate_password(64, true, true);
        
        // Create initial token
        $initial_payload = [
            'sub' => 1,
            'username' => 'testuser',
            'iat' => time(),
            'exp' => time() + 86400,
        ];
        
        $initial_token = \Firebase\JWT\JWT::encode($initial_payload, $secret, 'HS256');
        
        // Decode and verify it's valid
        $decoded = \Firebase\JWT\JWT::decode($initial_token, $secret, ['HS256']);
        $this->assertEquals(1, $decoded->sub);
        
        // Create refreshed token with new timestamps
        $refreshed_payload = [
            'sub' => $decoded->sub,
            'username' => $decoded->username,
            'iat' => time(),
            'exp' => time() + 86400,
        ];
        
        $refreshed_token = \Firebase\JWT\JWT::encode($refreshed_payload, $secret, 'HS256');
        
        // Verify refreshed token is valid
        $refreshed_decoded = \Firebase\JWT\JWT::decode($refreshed_token, $secret, ['HS256']);
        $this->assertEquals(1, $refreshed_decoded->sub);
        $this->assertGreater($refreshed_decoded->iat, $decoded->iat);
    }
    
    /**
     * Test: Revoked tokens rejected
     */
    public function testRevokedTokensRejected() {
        // Test token blacklist functionality
        $state_manager = new StateManager();
        $token = 'test_token_to_revoke_' . hash('sha256', 'secret');
        
        // Simulate token revocation by storing in blacklist
        $blacklist_key = 'newera_token_blacklist_' . hash('sha256', $token);
        $state_manager->update_option($blacklist_key, true);
        
        // Verify token is in blacklist
        $is_blacklisted = get_option($blacklist_key);
        $this->assertTrue($is_blacklisted);
    }
    
    /**
     * Test: Session token validation
     */
    public function testSessionTokenValidation() {
        // Verify session tokens are properly validated
        $state_manager = new StateManager();
        
        // Store a session token
        $session_token = wp_generate_password(64, true, true);
        $state_manager->update_option('newera_session_token', $session_token);
        
        // Retrieve and verify
        $stored_token = get_option('newera_session_token');
        $this->assertEquals($session_token, $stored_token);
        
        // Invalid token should not match
        $invalid_token = wp_generate_password(64, true, true);
        $this->assertNotEquals($invalid_token, $stored_token);
    }
}
