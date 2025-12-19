<?php
/**
 * Integration tests for credential isolation between modules
 */

namespace Newera\Tests\Integration;

use Newera\Core\StateManager;
use Newera\Core\Crypto;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Credential Isolation Integration Test
 * 
 * Tests that each module stores credentials independently and securely:
 * - Each module stores credentials independently
 * - Compromised module doesn't expose others' credentials
 * - Decryption requires correct module namespace
 */
class CredentialIsolationTest extends \Newera\Tests\Integration\IntegrationTestCase {
    
    /**
     * State manager instance
     *
     * @var StateManager
     */
    private $stateManager;
    
    /**
     * Crypto instance
     *
     * @var Crypto
     */
    private $crypto;
    
    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->crypto = new Crypto();
        
        // Reset state for clean test
        $this->stateManager->reset_state();
    }
    
    /**
     * Test each module stores credentials independently
     */
    public function testEachModuleStoresCredentialsIndependently() {
        // Store credentials for different modules
        $auth_credentials = [
            'google_client_id' => 'google_auth_client_123',
            'google_client_secret' => 'google_auth_secret_456',
            'github_client_secret' => 'github_auth_secret_789'
        ];
        
        $payment_credentials = [
            'stripe_publishable_key' => 'pk_auth_test_123',
            'stripe_secret_key' => 'sk_auth_test_456',
            'stripe_webhook_secret' => 'whsec_auth_test_789'
        ];
        
        $ai_credentials = [
            'openai_api_key' => 'sk-openai_auth_123',
            'anthropic_api_key' => 'sk-ant_auth_456'
        ];
        
        $database_credentials = [
            'external_db_host' => 'localhost',
            'external_db_name' => 'auth_test_db',
            'external_db_user' => 'auth_test_user',
            'external_db_pass' => 'auth_test_password'
        ];
        
        $integration_credentials = [
            'linear_api_key' => 'lin_auth_key_123',
            'notion_api_key' => 'secret_auth_notion_456'
        ];
        
        // Store credentials for each module
        foreach ($auth_credentials as $key => $value) {
            if (strpos($key, 'secret') !== false || strpos($key, 'key') !== false) {
                $this->stateManager->setSecure('auth', $key, $value);
            } else {
                $this->stateManager->update_setting($key, $value);
            }
        }
        
        foreach ($payment_credentials as $key => $value) {
            if (strpos($key, 'secret') !== false || strpos($key, 'key') !== false) {
                $this->stateManager->setSecure('payments', $key, $value);
            } else {
                $this->stateManager->update_setting($key, $value);
            }
        }
        
        foreach ($ai_credentials as $key => $value) {
            if (strpos($key, 'secret') !== false || strpos($key, 'key') !== false) {
                $this->stateManager->setSecure('ai', $key, $value);
            } else {
                $this->stateManager->update_setting($key, $value);
            }
        }
        
        foreach ($database_credentials as $key => $value) {
            if (strpos($key, 'pass') !== false || strpos($key, 'secret') !== false) {
                $this->stateManager->setSecure('database', $key, $value);
            } else {
                $this->stateManager->update_setting($key, $value);
            }
        }
        
        foreach ($integration_credentials as $key => $value) {
            if (strpos($key, 'secret') !== false || strpos($key, 'key') !== false) {
                $this->stateManager->setSecure('integrations', $key, $value);
            } else {
                $this->stateManager->update_setting($key, $value);
            }
        }
        
        // Verify each module can only access its own credentials
        $auth_module_credentials = $this->stateManager->getAllSecure('auth');
        $payment_module_credentials = $this->stateManager->getAllSecure('payments');
        $ai_module_credentials = $this->stateManager->getAllSecure('ai');
        $database_module_credentials = $this->stateManager->getAllSecure('database');
        $integration_module_credentials = $this->stateManager->getAllSecure('integrations');
        
        // Auth module should only have auth credentials
        foreach ($auth_module_credentials as $key => $value) {
            $this->assertStringStartsWith('google_client_', $key) || 
                  $this->assertStringStartsWith('github_client_', $key);
        }
        
        // Payment module should only have payment credentials
        foreach ($payment_module_credentials as $key => $value) {
            $this->assertStringStartsWith('stripe_', $key);
        }
        
        // AI module should only have AI credentials
        foreach ($ai_module_credentials as $key => $value) {
            $this->assertStringStartsWith('openai_', $key) || 
                  $this->assertStringStartsWith('anthropic_', $key);
        }
        
        // Database module should only have database credentials
        foreach ($database_module_credentials as $key => $value) {
            $this->assertStringStartsWith('external_db_', $key);
        }
        
        // Integration module should only have integration credentials
        foreach ($integration_module_credentials as $key => $value) {
            $this->assertStringStartsWith('linear_', $key) || 
                  $this->assertStringStartsWith('notion_', $key);
        }
    }
    
    /**
     * Test compromised module doesn't expose others' credentials
     */
    public function testCompromisedModuleDoesNotExposeOthersCredentials() {
        // Store sensitive credentials for all modules
        $module_credentials = [
            'auth' => ['google_secret' => 'auth_very_secret_123'],
            'payments' => ['stripe_secret' => 'payment_very_secret_456'],
            'ai' => ['openai_secret' => 'ai_very_secret_789'],
            'database' => ['db_password' => 'db_very_secret_abc'],
            'integrations' => ['linear_secret' => 'integration_very_secret_def']
        ];
        
        // Store all credentials
        foreach ($module_credentials as $module => $credentials) {
            foreach ($credentials as $key => $value) {
                $this->stateManager->setSecure($module, $key, $value);
            }
        }
        
        // Simulate "compromised" auth module trying to access other modules
        $compromised_access_attempts = [
            'payments:stripe_secret',
            'ai:openai_secret', 
            'database:db_password',
            'integrations:linear_secret'
        ];
        
        foreach ($compromised_access_attempts as $attempt) {
            list($target_module, $target_key) = explode(':', $attempt);
            
            // Auth module should NOT be able to access other modules' credentials
            $unauthorized_access = $this->stateManager->getSecure('auth', $target_key, 'UNAUTHORIZED');
            $this->assertEquals('UNAUTHORIZED', $unauthorized_access, 
                "Auth module unexpectedly accessed {$target_module} credential");
        }
        
        // Verify each module can still access its own credentials
        foreach ($module_credentials as $module => $credentials) {
            foreach ($credentials as $key => $expected_value) {
                $actual_value = $this->stateManager->getSecure($module, $key);
                $this->assertEquals($expected_value, $actual_value, 
                    "Module {$module} cannot access its own credential {$key}");
            }
        }
    }
    
    /**
     * Test decryption requires correct module namespace
     */
    public function testDecryptionRequiresCorrectModuleNamespace() {
        // Store the same credential key in different modules with different values
        $same_key = 'api_secret';
        $module_values = [
            'auth' => 'auth_api_secret_value',
            'payments' => 'payments_api_secret_value',
            'ai' => 'ai_api_secret_value'
        ];
        
        // Store credential with same key in different modules
        foreach ($module_values as $module => $value) {
            $this->stateManager->setSecure($module, $same_key, $value);
        }
        
        // Verify each module gets only its own value
        foreach ($module_values as $module => $expected_value) {
            $retrieved_value = $this->stateManager->getSecure($module, $same_key);
            $this->assertEquals($expected_value, $retrieved_value, 
                "Module {$module} retrieved wrong value for key {$same_key}");
        }
        
        // Verify cross-module access returns different values (or null)
        $auth_value = $this->stateManager->getSecure('auth', $same_key);
        $payments_value = $this->stateManager->getSecure('payments', $same_key);
        $ai_value = $this->stateManager->getSecure('ai', $same_key);
        
        $this->assertNotEquals($auth_value, $payments_value);
        $this->assertNotEquals($payments_value, $ai_value);
        $this->assertNotEquals($auth_value, $ai_value);
    }
    
    /**
     * Test credential storage with different encryption contexts
     */
    public function testCredentialStorageWithDifferentEncryptionContexts() {
        // Test that the same data encrypted for different modules produces different results
        $sensitive_data = 'SuperSecretData123!';
        $module1_data = 'shared_secret_key';
        $module2_data = 'shared_secret_key';
        
        // Store in module 1
        $result1 = $this->stateManager->setSecure('module1', 'shared_key', $module1_data);
        $this->assertTrue($result1);
        
        // Store in module 2  
        $result2 = $this->stateManager->setSecure('module2', 'shared_key', $module2_data);
        $this->assertTrue($result2);
        
        // Verify both modules get the correct data
        $retrieved1 = $this->stateManager->getSecure('module1', 'shared_key');
        $retrieved2 = $this->stateManager->getSecure('module2', 'shared_key');
        
        $this->assertEquals($module1_data, $retrieved1);
        $this->assertEquals($module2_data, $retrieved2);
        
        // Verify the stored encrypted data is different (due to different module contexts)
        $option_name1 = $this->get_encrypted_option_name('module1', 'shared_key');
        $option_name2 = $this->get_encrypted_option_name('module2', 'shared_key');
        
        $encrypted1 = get_option($option_name1);
        $encrypted2 = get_option($option_name2);
        
        $this->assertNotEquals($encrypted1, $encrypted2, 
            "Encrypted data should be different due to different module contexts");
    }
    
    /**
     * Test bulk credential operations maintain isolation
     */
    public function testBulkCredentialOperationsMaintainIsolation() {
        // Set up bulk credentials for each module
        $auth_bulk = ['google_secret' => 'auth_bulk_1', 'github_secret' => 'auth_bulk_2'];
        $payment_bulk = ['stripe_secret' => 'payment_bulk_1', 'paypal_secret' => 'payment_bulk_2'];
        $ai_bulk = ['openai_secret' => 'ai_bulk_1', 'anthropic_secret' => 'ai_bulk_2'];
        
        // Store bulk credentials
        $auth_results = $this->stateManager->setBulkSecure('auth', $auth_bulk);
        $payment_results = $this->stateManager->setBulkSecure('payments', $payment_bulk);
        $ai_results = $this->stateManager->setBulkSecure('ai', $ai_bulk);
        
        // Verify all bulk operations succeeded
        foreach ($auth_results as $result) {
            $this->assertTrue($result);
        }
        foreach ($payment_results as $result) {
            $this->assertTrue($result);
        }
        foreach ($ai_results as $result) {
            $this->assertTrue($result);
        }
        
        // Retrieve bulk credentials for each module
        $auth_retrieved = $this->stateManager->getBulkSecure('auth', array_keys($auth_bulk));
        $payment_retrieved = $this->stateManager->getBulkSecure('payments', array_keys($payment_bulk));
        $ai_retrieved = $this->stateManager->getBulkSecure('ai', array_keys($ai_bulk));
        
        // Verify correct data retrieved for each module
        $this->assertEquals($auth_bulk, $auth_retrieved);
        $this->assertEquals($payment_bulk, $payment_retrieved);
        $this->assertEquals($ai_bulk, $ai_retrieved);
        
        // Verify cross-module retrieval returns null/default
        $auth_github_from_payments = $this->stateManager->getSecure('payments', 'github_secret', 'NOT_FOUND');
        $payment_stripe_from_ai = $this->stateManager->getSecure('ai', 'stripe_secret', 'NOT_FOUND');
        $ai_openai_from_auth = $this->stateManager->getSecure('auth', 'openai_secret', 'NOT_FOUND');
        
        $this->assertEquals('NOT_FOUND', $auth_github_from_payments);
        $this->assertEquals('NOT_FOUND', $payment_stripe_from_ai);
        $this->assertEquals('NOT_FOUND', $ai_openai_from_auth);
    }
    
    /**
     * Test credential metadata isolation
     */
    public function testCredentialMetadataIsolation() {
        $module1_metadata = ['created_by' => 'user_1', 'expires_at' => time() + 3600];
        $module2_metadata = ['created_by' => 'user_2', 'expires_at' => time() + 7200];
        
        // Store credentials with metadata in different modules
        $this->stateManager->setSecure('module1', 'secret_data', 'value1');
        $this->stateManager->setSecure('module2', 'secret_data', 'value2');
        
        // Store metadata separately for each module
        $this->stateManager->updateSecure('module1', 'secret_data_metadata', $module1_metadata);
        $this->stateManager->updateSecure('module2', 'secret_data_metadata', $module2_metadata);
        
        // Verify metadata is isolated
        $module1_meta = $this->stateManager->getSecure('module1', 'secret_data_metadata');
        $module2_meta = $this->stateManager->getSecure('module2', 'secret_data_metadata');
        
        $this->assertEquals($module1_metadata['created_by'], $module1_meta['created_by']);
        $this->assertEquals($module2_metadata['created_by'], $module2_meta['created_by']);
        $this->assertNotEquals($module1_meta['created_by'], $module2_meta['created_by']);
        
        // Verify metadata doesn't leak between modules
        $module1_from_module2 = $this->stateManager->getSecure('module2', 'secret_data_metadata');
        $this->assertEquals($module2_metadata['created_by'], $module1_from_module2['created_by']);
    }
    
    /**
     * Test credential deletion maintains isolation
     */
    public function testCredentialDeletionMaintainsIsolation() {
        // Store credentials in multiple modules
        $modules_and_data = [
            'auth' => ['secret1' => 'auth_value1', 'secret2' => 'auth_value2'],
            'payments' => ['secret1' => 'payment_value1', 'secret2' => 'payment_value2'],
            'ai' => ['secret1' => 'ai_value1', 'secret2' => 'ai_value2']
        ];
        
        // Store all credentials
        foreach ($modules_and_data as $module => $data) {
            foreach ($data as $key => $value) {
                $this->stateManager->setSecure($module, $key, $value);
            }
        }
        
        // Delete specific credentials from auth module
        $this->stateManager->deleteSecure('auth', 'secret1');
        
        // Verify auth module's secret1 is gone
        $deleted_cred = $this->stateManager->getSecure('auth', 'secret1', 'NOT_FOUND');
        $this->assertEquals('NOT_FOUND', $deleted_cred);
        
        // Verify other auth credentials still exist
        $remaining_auth_cred = $this->stateManager->getSecure('auth', 'secret2');
        $this->assertEquals('auth_value2', $remaining_auth_cred);
        
        // Verify other modules' credentials are unaffected
        $payment_cred1 = $this->stateManager->getSecure('payments', 'secret1');
        $payment_cred2 = $this->stateManager->getSecure('payments', 'secret2');
        $ai_cred1 = $this->stateManager->getSecure('ai', 'secret1');
        $ai_cred2 = $this->stateManager->getSecure('ai', 'secret2');
        
        $this->assertEquals('payment_value1', $payment_cred1);
        $this->assertEquals('payment_value2', $payment_cred2);
        $this->assertEquals('ai_value1', $ai_cred1);
        $this->assertEquals('ai_value2', $ai_cred2);
    }
    
    /**
     * Test credential update isolation
     */
    public function testCredentialUpdateIsolation() {
        // Store initial credentials
        $this->stateManager->setSecure('module1', 'shared_key', 'original_value');
        $this->stateManager->setSecure('module2', 'shared_key', 'original_value');
        $this->stateManager->setSecure('module3', 'shared_key', 'original_value');
        
        // Update credential in module1 only
        $this->stateManager->updateSecure('module1', 'shared_key', 'updated_value_module1');
        
        // Verify module1 has updated value
        $module1_value = $this->stateManager->getSecure('module1', 'shared_key');
        $this->assertEquals('updated_value_module1', $module1_value);
        
        // Verify module2 and module3 still have original values
        $module2_value = $this->stateManager->getSecure('module2', 'shared_key');
        $module3_value = $this->stateManager->getSecure('module3', 'shared_key');
        
        $this->assertEquals('original_value', $module2_value);
        $this->assertEquals('original_value', $module3_value);
    }
    
    /**
     * Test credential existence checking maintains isolation
     */
    public function testCredentialExistenceCheckingMaintainsIsolation() {
        // Store credential in auth module only
        $this->stateManager->setSecure('auth', 'unique_credential', 'secret_value');
        
        // Verify auth module reports existence
        $this->assertTrue($this->stateManager->hasSecure('auth', 'unique_credential'));
        
        // Verify other modules don't report existence
        $this->assertFalse($this->stateManager->hasSecure('payments', 'unique_credential'));
        $this->assertFalse($this->stateManager->hasSecure('ai', 'unique_credential'));
        $this->assertFalse($this->stateManager->hasSecure('database', 'unique_credential'));
        
        // Store same key in different modules
        $this->stateManager->setSecure('payments', 'unique_credential', 'payment_secret');
        
        // Now payments should report existence, but with different value
        $this->assertTrue($this->stateManager->hasSecure('payments', 'unique_credential'));
        
        // Verify both modules have their own versions
        $auth_value = $this->stateManager->getSecure('auth', 'unique_credential');
        $payments_value = $this->stateManager->getSecure('payments', 'unique_credential');
        
        $this->assertEquals('secret_value', $auth_value);
        $this->assertEquals('payment_secret', $payments_value);
    }
    
    /**
     * Helper method to get encrypted option name (replicating StateManager logic)
     */
    private function get_encrypted_option_name($module, $key) {
        $hash = hash('sha256', $module . $key . wp_salt('nonce'));
        return 'newera_secure_' . $hash;
    }
    
    /**
     * Test namespace collision prevention
     */
    public function testNamespaceCollisionPrevention() {
        // Attempt to store credentials that might cause namespace collisions
        $potential_collisions = [
            'auth:oauth_google_client_secret',
            'payments:oauth_google_client_secret', // Same key in different module
            'auth:api_key',
            'payments:api_key', // Same key in different module
            'ai:api_key', // Same key in different module
        ];
        
        $expected_values = [
            'auth:oauth_google_client_secret' => 'auth_oauth_secret',
            'payments:oauth_google_client_secret' => 'payment_oauth_secret',
            'auth:api_key' => 'auth_api_key',
            'payments:api_key' => 'payment_api_key',
            'ai:api_key' => 'ai_api_key'
        ];
        
        // Store all potentially colliding credentials
        foreach ($potential_collisions as $collision) {
            list($module, $key) = explode(':', $collision);
            $value = $expected_values[$collision];
            $this->stateManager->setSecure($module, $key, $value);
        }
        
        // Verify each module gets correct value for each key
        foreach ($expected_values as $collision => $expected_value) {
            list($module, $key) = explode(':', $collision);
            $actual_value = $this->stateManager->getSecure($module, $key);
            $this->assertEquals($expected_value, $actual_value, 
                "Module {$module} got wrong value for key {$key}");
        }
        
        // Verify no cross-contamination between modules
        $auth_oauth_from_payments = $this->stateManager->getSecure('payments', 'oauth_google_client_secret');
        $payment_oauth_from_auth = $this->stateManager->getSecure('auth', 'oauth_google_client_secret');
        
        $this->assertEquals('payment_oauth_secret', $auth_oauth_from_payments);
        $this->assertEquals('auth_oauth_secret', $payment_oauth_from_auth);
    }
    
    /**
     * Test credential recovery after partial module compromise
     */
    public function testCredentialRecoveryAfterPartialCompromise() {
        // Store credentials for multiple modules
        $all_credentials = [
            'auth' => ['google_secret' => 'auth_secret_123', 'github_secret' => 'auth_github_456'],
            'payments' => ['stripe_secret' => 'payment_stripe_789', 'paypal_secret' => 'payment_paypal_abc'],
            'ai' => ['openai_secret' => 'ai_openai_def', 'anthropic_secret' => 'ai_anthropic_ghi'],
            'database' => ['db_password' => 'db_password_jkl', 'backup_password' => 'backup_pwd_mno'],
            'integrations' => ['linear_secret' => 'linear_secret_pqr', 'notion_secret' => 'notion_secret_stu']
        ];
        
        foreach ($all_credentials as $module => $credentials) {
            foreach ($credentials as $key => $value) {
                $this->stateManager->setSecure($module, $key, $value);
            }
        }
        
        // Simulate compromise of auth module (delete its credentials)
        foreach ($all_credentials['auth'] as $key => $value) {
            $this->stateManager->deleteSecure('auth', $key);
        }
        
        // Verify auth module credentials are gone
        foreach ($all_credentials['auth'] as $key => $expected_value) {
            $deleted_value = $this->stateManager->getSecure('auth', $key, 'DELETED');
            $this->assertEquals('DELETED', $deleted_value);
        }
        
        // Verify other modules' credentials are intact
        foreach ($all_credentials as $module => $credentials) {
            if ($module === 'auth') continue; // Skip compromised module
            
            foreach ($credentials as $key => $expected_value) {
                $actual_value = $this->stateManager->getSecure($module, $key);
                $this->assertEquals($expected_value, $actual_value, 
                    "Credential {$key} in module {$module} was affected by auth module compromise");
            }
        }
    }
}