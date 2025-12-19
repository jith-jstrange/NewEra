<?php
/**
 * Integration tests for Authentication workflows
 */

namespace Newera\Tests\Integration;

use Newera\Modules\Auth\BetterAuthManager;
use Newera\Core\StateManager;
use Newera\Core\Crypto;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authentication Integration Test
 * 
 * Tests end-to-end authentication workflows including:
 * - Better-Auth → WordPress user sync
 * - OAuth provider → redirect URL → session token
 * - Magic Link flow end-to-end
 * - Client creation on first auth
 * - Role assignment (admin vs client)
 */
class AuthenticationIntegrationTest extends \Newera\Tests\TestCase {
    
    /**
     * Better Auth Manager instance
     *
     * @var BetterAuthManager
     */
    private $authManager;
    
    /**
     * State manager instance
     *
     * @var StateManager
     */
    private $stateManager;
    
    /**
     * Mock WordPress user functions
     */
    private function mockWordPressUserFunctions() {
        // Mock wp_insert_user
        if (!function_exists('wp_insert_user')) {
            function wp_insert_user($user_data) {
                return \Newera\Tests\MockWPDB::insert_user($user_data);
            }
        }
        
        // Mock wp_update_user
        if (!function_exists('wp_update_user')) {
            function wp_update_user($user_data) {
                return \Newera\Tests\MockWPDB::update_user($user_data);
            }
        }
        
        // Mock get_user_by
        if (!function_exists('get_user_by')) {
            function get_user_by($field, $value) {
                return \Newera\Tests\MockWPDB::get_user_by($field, $value);
            }
        }
        
        // Mock wp_create_nonce
        if (!function_exists('wp_create_nonce')) {
            function wp_create_nonce($action) {
                return 'test_nonce_' . $action . '_12345';
            }
        }
        
        // Mock wp_verify_nonce
        if (!function_exists('wp_verify_nonce')) {
            function wp_verify_nonce($nonce, $action) {
                return strpos($nonce, 'test_nonce_' . $action) === 0;
            }
        }
        
        // Mock current_user_can
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true; // Mock for testing
            }
        }
    }
    
    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->authManager = new BetterAuthManager($this->stateManager);
        
        // Mock WordPress functions
        $this->mockWordPressUserFunctions();
        
        // Reset state for clean test
        $this->stateManager->reset_state();
    }
    
    /**
     * Test Better-Auth to WordPress user sync
     */
    public function testBetterAuthToWordPressUserSync() {
        // Simulate OAuth provider configuration
        $this->stateManager->update_setting('google_enabled', true);
        $this->stateManager->setSecure('oauth', 'google_client_id', 'test_client_id');
        $this->stateManager->setSecure('oauth', 'google_client_secret', 'test_secret');
        
        // Simulate OAuth callback with user data
        $oauth_user_data = [
            'provider' => 'google',
            'email' => 'testuser@example.com',
            'name' => 'Test User',
            'provider_id' => 'google_12345',
            'avatar' => 'https://example.com/avatar.jpg'
        ];
        
        // Process the OAuth callback
        $result = $this->authManager->handle_oauth_callback($oauth_user_data);
        
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('wordpress_user_id', $result);
        
        // Verify user was created in WordPress
        $wordpress_user = get_user_by('email', 'testuser@example.com');
        $this->assertNotFalse($wordpress_user);
        $this->assertEquals('Test User', $wordpress_user->display_name);
        
        // Verify provider data is stored
        $provider_data = $this->stateManager->getSecure('auth_provider_google', 'user_12345');
        $this->assertEquals('testuser@example.com', $provider_data['email']);
        $this->assertEquals('google_12345', $provider_data['provider_id']);
    }
    
    /**
     * Test OAuth provider redirect URL generation
     */
    public function testOAuthProviderRedirectURL() {
        $provider = 'google';
        $redirect_to = 'https://example.com/dashboard';
        
        // Generate auth URL
        $auth_url = $this->authManager->get_auth_url($provider, $redirect_to);
        
        $this->assertStringContainsString('google', $auth_url);
        $this->assertStringContainsString('client_id=test_client_id', $auth_url);
        $this->assertStringContainsString('redirect_uri=', $auth_url);
        $this->assertStringContainsString('state=', $auth_url);
    }
    
    /**
     * Test session token generation and validation
     */
    public function testSessionTokenGenerationAndValidation() {
        // Generate session token
        $user_data = [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'provider' => 'google'
        ];
        
        $token = $this->authManager->generate_session_token($user_data);
        
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        
        // Validate session token
        $validated_data = $this->authManager->validate_session_token($token);
        
        $this->assertNotFalse($validated_data);
        $this->assertEquals('test@example.com', $validated_data['email']);
        $this->assertEquals('google', $validated_data['provider']);
    }
    
    /**
     * Test Magic Link flow end-to-end
     */
    public function testMagicLinkFlow() {
        $email = 'magic@example.com';
        
        // Request magic link
        $magic_link_request = $this->authManager->request_magic_link($email);
        
        $this->assertTrue($magic_link_request['success']);
        $this->assertArrayHasKey('token', $magic_link_request);
        $this->assertArrayHasKey('expires_at', $magic_link_request);
        
        $token = $magic_link_request['token'];
        
        // Simulate magic link click with token
        $magic_link_verification = $this->authManager->verify_magic_link($token);
        
        $this->assertTrue($magic_link_verification['success']);
        $this->assertArrayHasKey('user_id', $magic_link_verification);
        $this->assertArrayHasKey('wordpress_user_id', $magic_link_verification);
        
        // Verify user was created
        $wordpress_user = get_user_by('email', $email);
        $this->assertNotFalse($wordpress_user);
        $this->assertEquals('magic@example.com', $wordpress_user->user_email);
        
        // Verify token was invalidated after use
        $reused_token_check = $this->authManager->verify_magic_link($token);
        $this->assertFalse($reused_token_check['success']);
    }
    
    /**
     * Test client creation on first authentication
     */
    public function testClientCreationOnFirstAuth() {
        $provider_data = [
            'provider' => 'google',
            'email' => 'newclient@example.com',
            'name' => 'New Client',
            'provider_id' => 'google_67890'
        ];
        
        // First authentication
        $auth_result = $this->authManager->handle_oauth_callback($provider_data);
        
        $this->assertTrue($auth_result['success']);
        
        // Verify WordPress user was created with client role
        $wordpress_user = get_user_by('email', 'newclient@example.com');
        $this->assertNotFalse($wordpress_user);
        $this->assertTrue(user_can($wordpress_user->ID, 'read'));
        $this->assertFalse(user_can($wordpress_user->ID, 'manage_options'));
        
        // Verify client profile was created
        $client_profile = $this->stateManager->getSecure('client_profiles', 'wp_user_' . $wordpress_user->ID);
        $this->assertEquals('google_67890', $client_profile['provider_id']);
        $this->assertEquals('newclient@example.com', $client_profile['email']);
    }
    
    /**
     * Test admin role assignment
     */
    public function testAdminRoleAssignment() {
        // Set up admin configuration
        $this->stateManager->update_setting('admin_emails', ['admin@example.com']);
        
        $provider_data = [
            'provider' => 'google',
            'email' => 'admin@example.com',
            'name' => 'Admin User',
            'provider_id' => 'google_admin123'
        ];
        
        // Authenticate as admin
        $auth_result = $this->authManager->handle_oauth_callback($provider_data);
        
        $this->assertTrue($auth_result['success']);
        
        // Verify admin role assignment
        $wordpress_user = get_user_by('email', 'admin@example.com');
        $this->assertNotFalse($wordpress_user);
        $this->assertTrue(user_can($wordpress_user->ID, 'manage_options'));
        $this->assertTrue(user_can($wordpress_user->ID, 'read'));
    }
    
    /**
     * Test role assignment logic
     */
    public function testRoleAssignmentLogic() {
        // Test admin email assignment
        $this->stateManager->update_setting('admin_emails', ['admin@example.com']);
        
        $admin_user = [
            'email' => 'admin@example.com',
            'provider' => 'google',
            'provider_id' => 'admin_123'
        ];
        
        $admin_result = $this->authManager->handle_oauth_callback($admin_user);
        $this->assertTrue($admin_result['success']);
        
        // Test regular user assignment
        $regular_user = [
            'email' => 'user@example.com',
            'provider' => 'google',
            'provider_id' => 'user_456'
        ];
        
        $user_result = $this->authManager->handle_oauth_callback($regular_user);
        $this->assertTrue($user_result['success']);
        
        // Verify roles are different
        $admin_wp_user = get_user_by('email', 'admin@example.com');
        $user_wp_user = get_user_by('email', 'user@example.com');
        
        $this->assertNotEquals($admin_wp_user->roles, $user_wp_user->roles);
    }
    
    /**
     * Test multiple provider authentication
     */
    public function testMultipleProviderAuthentication() {
        $user_data_google = [
            'provider' => 'google',
            'email' => 'multiprovider@example.com',
            'name' => 'Multi Provider User',
            'provider_id' => 'google_multi123'
        ];
        
        // First authentication with Google
        $google_result = $this->authManager->handle_oauth_callback($user_data_google);
        $this->assertTrue($google_result['success']);
        
        $user_data_github = [
            'provider' => 'github',
            'email' => 'multiprovider@example.com',
            'name' => 'Multi Provider User',
            'provider_id' => 'github_multi456'
        ];
        
        // Same user with GitHub
        $github_result = $this->authManager->handle_oauth_callback($user_data_github);
        $this->assertTrue($github_result['success']);
        
        // Verify same WordPress user was used
        $wordpress_user = get_user_by('email', 'multiprovider@example.com');
        $this->assertNotFalse($wordpress_user);
        
        // Verify both providers are linked
        $google_provider = $this->stateManager->getSecure('auth_provider_google', 'wp_user_' . $wordpress_user->ID);
        $github_provider = $this->stateManager->getSecure('auth_provider_github', 'wp_user_' . $wordpress_user->ID);
        
        $this->assertEquals('google_multi123', $google_provider['provider_id']);
        $this->assertEquals('github_multi456', $github_provider['provider_id']);
    }
    
    /**
     * Test authentication failure handling
     */
    public function testAuthenticationFailureHandling() {
        // Test invalid OAuth callback
        $invalid_callback = [
            'provider' => 'google',
            'email' => '', // Invalid email
            'provider_id' => 'invalid_123'
        ];
        
        $result = $this->authManager->handle_oauth_callback($invalid_callback);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        
        // Test magic link with invalid token
        $invalid_token_result = $this->authManager->verify_magic_link('invalid_token');
        $this->assertFalse($invalid_token_result['success']);
    }
    
    /**
     * Test authentication state persistence
     */
    public function testAuthenticationStatePersistence() {
        $provider_data = [
            'provider' => 'google',
            'email' => 'persistent@example.com',
            'name' => 'Persistent User',
            'provider_id' => 'google_persistent123'
        ];
        
        // Complete authentication
        $auth_result = $this->authManager->handle_oauth_callback($provider_data);
        $this->assertTrue($auth_result['success']);
        
        // Create new auth manager instance
        $new_auth_manager = new BetterAuthManager($this->stateManager);
        
        // Verify state persists
        $wordpress_user = get_user_by('email', 'persistent@example.com');
        $this->assertNotFalse($wordpress_user);
        
        $provider_data_reloaded = $this->stateManager->getSecure('auth_provider_google', 'wp_user_' . $wordpress_user->ID);
        $this->assertEquals('google_persistent123', $provider_data_reloaded['provider_id']);
    }
}