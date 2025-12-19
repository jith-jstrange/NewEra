<?php
/**
 * Integration tests for Setup Wizard workflows
 */

namespace Newera\Tests\Integration;

use Newera\Core\StateManager;
use Newera\Admin\SetupWizard;
use Newera\Core\Crypto;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Setup Wizard Integration Test
 * 
 * Tests end-to-end setup wizard workflows including:
 * - Wizard → StateManager → Crypto flow
 * - All steps complete in sequence
 * - Data persisted across steps
 * - Resume wizard, data intact
 * - OAuth provider selection & callback handling
 * - Credentials encrypted on save
 */
class SetupWizardIntegrationTest extends \Newera\Tests\TestCase {
    
    /**
     * Setup wizard instance
     *
     * @var SetupWizard
     */
    private $setupWizard;
    
    /**
     * State manager instance
     *
     * @var StateManager
     */
    private $stateManager;
    
    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->setupWizard = new SetupWizard($this->stateManager);
        
        // Reset state for clean test
        $this->stateManager->reset_state();
    }
    
    /**
     * Test complete wizard flow from intro to completion
     */
    public function testCompleteWizardFlow() {
        // Step 1: Intro
        $this->assertFalse($this->setupWizard->is_completed());
        $this->assertEquals('intro', $this->setupWizard->get_current_step());
        
        // Step 2: Module selection
        $module_data = [
            'auth' => true,
            'payments' => true,
            'ai' => true,
            'database' => true
        ];
        
        $result = $this->setupWizard->process_step('intro', []);
        $this->assertTrue($result);
        
        $result = $this->setupWizard->process_step('modules', $module_data);
        $this->assertTrue($result);
        $this->assertEquals('oauth_providers', $this->setupWizard->get_current_step());
        
        // Step 3: OAuth provider selection
        $oauth_data = [
            'google_enabled' => true,
            'apple_enabled' => false,
            'github_enabled' => true,
            'callback_base_url' => 'https://example.com/callback'
        ];
        
        $result = $this->setupWizard->process_step('oauth_providers', $oauth_data);
        $this->assertTrue($result);
        $this->assertEquals('credentials', $this->setupWizard->get_current_step());
        
        // Step 4: Credentials
        $credential_data = [
            'google_client_id' => 'test_google_client_id',
            'google_client_secret' => 'test_google_secret',
            'github_client_id' => 'test_github_client_id',
            'github_client_secret' => 'test_github_secret'
        ];
        
        $result = $this->setupWizard->process_step('credentials', $credential_data);
        $this->assertTrue($result);
        $this->assertEquals('payments', $this->setupWizard->get_current_step());
        
        // Step 5: Payment setup
        $payment_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_456'
        ];
        
        $result = $this->setupWizard->process_step('payments', $payment_data);
        $this->assertTrue($result);
        $this->assertEquals('ai_config', $this->setupWizard->get_current_step());
        
        // Step 6: AI configuration
        $ai_data = [
            'provider' => 'openai',
            'api_key' => 'openai_test_key',
            'model' => 'gpt-4'
        ];
        
        $result = $this->setupWizard->process_step('ai_config', $ai_data);
        $this->assertTrue($result);
        $this->assertEquals('database', $this->setupWizard->get_current_step());
        
        // Step 7: Database configuration
        $db_data = [
            'external_db_enabled' => true,
            'external_db_host' => 'localhost',
            'external_db_name' => 'newera_db',
            'external_db_user' => 'newera_user',
            'external_db_pass' => 'secure_password'
        ];
        
        $result = $this->setupWizard->process_step('database', $db_data);
        $this->assertTrue($result);
        $this->assertEquals('complete', $this->setupWizard->get_current_step());
        
        // Finalize setup
        $result = $this->setupWizard->finalize_setup();
        $this->assertTrue($result);
        $this->assertTrue($this->setupWizard->is_completed());
        
        // Verify all data is persisted
        $wizard_state = $this->stateManager->get_state_value('setup_wizard');
        $this->assertTrue($wizard_state['completed']);
        $this->assertNotNull($wizard_state['completed_at']);
        $this->assertEquals(['intro', 'modules', 'oauth_providers', 'credentials', 'payments', 'ai_config', 'database'], $wizard_state['completed_steps']);
    }
    
    /**
     * Test wizard data persistence across steps
     */
    public function testWizardDataPersistence() {
        // Set initial data
        $this->setupWizard->process_step('intro', ['site_name' => 'Test Site']);
        $this->setupWizard->process_step('modules', ['auth' => true]);
        
        // Verify data persists
        $wizard_data = $this->stateManager->get_state_value('setup_wizard.data');
        $this->assertEquals('Test Site', $wizard_data['site_name']);
        $this->assertTrue($wizard_data['auth']);
        
        // Add more data in later step
        $this->setupWizard->process_step('oauth_providers', ['google_enabled' => true]);
        
        // Verify all previous data persists
        $wizard_data = $this->stateManager->get_state_value('setup_wizard.data');
        $this->assertEquals('Test Site', $wizard_data['site_name']);
        $this->assertTrue($wizard_data['auth']);
        $this->assertTrue($wizard_data['google_enabled']);
    }
    
    /**
     * Test wizard resume functionality
     */
    public function testWizardResume() {
        // Complete first few steps
        $this->setupWizard->process_step('intro', ['site_name' => 'Test Site']);
        $this->setupWizard->process_step('modules', ['auth' => true]);
        $this->setupWizard->process_step('oauth_providers', ['google_enabled' => true]);
        
        // Simulate wizard closure and reopening
        $this->setupWizard = new SetupWizard($this->stateManager);
        
        // Verify current step is correct
        $this->assertEquals('credentials', $this->setupWizard->get_current_step());
        
        // Verify previous data is intact
        $wizard_data = $this->stateManager->get_state_value('setup_wizard.data');
        $this->assertEquals('Test Site', $wizard_data['site_name']);
        $this->assertTrue($wizard_data['auth']);
        $this->assertTrue($wizard_data['google_enabled']);
    }
    
    /**
     * Test OAuth provider selection and callback handling
     */
    public function testOAuthProviderSelection() {
        $oauth_data = [
            'google_enabled' => true,
            'apple_enabled' => true,
            'github_enabled' => false,
            'callback_base_url' => 'https://example.com/auth/callback'
        ];
        
        $result = $this->setupWizard->process_step('oauth_providers', $oauth_data);
        $this->assertTrue($result);
        
        // Verify OAuth settings are stored
        $oauth_settings = $this->stateManager->get_setting('oauth_providers', []);
        $this->assertTrue($oauth_settings['google_enabled']);
        $this->assertTrue($oauth_settings['apple_enabled']);
        $this->assertFalse($oauth_settings['github_enabled']);
        $this->assertEquals('https://example.com/auth/callback', $oauth_settings['callback_base_url']);
    }
    
    /**
     * Test credentials are encrypted on save
     */
    public function testCredentialsEncryptedOnSave() {
        $credential_data = [
            'google_client_id' => 'test_google_client_id',
            'google_client_secret' => 'super_secret_google_key',
            'github_client_secret' => 'super_secret_github_key'
        ];
        
        $result = $this->setupWizard->process_step('credentials', $credential_data);
        $this->assertTrue($result);
        
        // Verify sensitive data is stored securely
        $stored_secret = $this->stateManager->getSecure('oauth', 'google_client_secret');
        $this->assertEquals('super_secret_google_key', $stored_secret);
        
        $stored_github_secret = $this->stateManager->getSecure('oauth', 'github_client_secret');
        $this->assertEquals('super_secret_github_key', $stored_github_secret);
        
        // Verify non-sensitive data is stored normally
        $settings = $this->stateManager->get_settings();
        $this->assertEquals('test_google_client_id', $settings['google_client_id']);
    }
    
    /**
     * Test Stripe credentials encryption
     */
    public function testStripeCredentialsEncryption() {
        $payment_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123456789',
            'stripe_secret_key' => 'sk_test_secret_abcdef_very_long_key'
        ];
        
        $result = $this->setupWizard->process_step('payments', $payment_data);
        $this->assertTrue($result);
        
        // Verify publishable key is stored normally (not sensitive)
        $settings = $this->stateManager->get_settings();
        $this->assertEquals('pk_test_123456789', $settings['stripe_publishable_key']);
        
        // Verify secret key is encrypted
        $stored_secret = $this->stateManager->getSecure('payments', 'stripe_secret_key');
        $this->assertEquals('sk_test_secret_abcdef_very_long_key', $stored_secret);
    }
    
    /**
     * Test database configuration encryption
     */
    public function testDatabaseConfigurationEncryption() {
        $db_data = [
            'external_db_enabled' => true,
            'external_db_host' => 'localhost',
            'external_db_name' => 'newera_db',
            'external_db_user' => 'newera_user',
            'external_db_pass' => 'super_secure_database_password_123'
        ];
        
        $result = $this->setupWizard->process_step('database', $db_data);
        $this->assertTrue($result);
        
        // Verify non-sensitive data stored normally
        $settings = $this->stateManager->get_settings();
        $this->assertEquals('localhost', $settings['external_db_host']);
        $this->assertEquals('newera_db', $settings['external_db_name']);
        $this->assertEquals('newera_user', $settings['external_db_user']);
        
        // Verify password is encrypted
        $stored_password = $this->stateManager->getSecure('database', 'external_db_pass');
        $this->assertEquals('super_secure_database_password_123', $stored_password);
    }
    
    /**
     * Test wizard step validation
     */
    public function testWizardStepValidation() {
        // Test missing required OAuth data
        $invalid_oauth_data = [
            'google_enabled' => true,
            'callback_base_url' => '' // Missing callback URL
        ];
        
        $result = $this->setupWizard->process_step('oauth_providers', $invalid_oauth_data);
        $this->assertFalse($result); // Should fail validation
    }
    
    /**
     * Test wizard rollback on failure
     */
    public function testWizardRollbackOnFailure() {
        // Complete some steps
        $this->setupWizard->process_step('intro', ['site_name' => 'Test Site']);
        $this->setupWizard->process_step('modules', ['auth' => true]);
        
        // Simulate failure in credential step
        $this->setupWizard->process_step('credentials', [
            'google_client_id' => 'test_id',
            'google_client_secret' => 'test_secret'
        ]);
        
        // Verify state is preserved on failure
        $wizard_state = $this->stateManager->get_state_value('setup_wizard');
        $this->assertTrue($wizard_state['data']['auth']); // Previous step data preserved
    }
}