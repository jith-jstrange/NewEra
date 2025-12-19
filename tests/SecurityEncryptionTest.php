<?php
/**
 * Security Tests for Encryption
 *
 * Tests encryption of credentials in wp_options, key validation,
 * IV randomization, and plaintext credential protection
 */

namespace Newera\Tests;

use Newera\Core\Crypto;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Encryption Test Case
 */
class SecurityEncryptionTest extends TestCase {
    /**
     * Crypto instance
     */
    private $crypto;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->crypto = new Crypto();
        MockStorage::clear_all();
    }
    
    /**
     * Test: Verify credentials encrypted in wp_options
     */
    public function testCredentialsEncryptedInOptions() {
        $credential_data = [
            'stripe_api_key' => 'sk_test_abc123def456',
            'stripe_secret' => 'rk_test_xyz789',
        ];
        
        // Encrypt and store in options
        $encrypted = $this->crypto->encrypt($credential_data);
        $this->assertIsArray($encrypted);
        $this->assertArrayHasKey('data', $encrypted);
        $this->assertArrayHasKey('iv', $encrypted);
        
        // Store in mock wp_options
        update_option('newera_stripe_credentials', $encrypted);
        
        // Verify encrypted data is not plaintext in storage
        $stored = get_option('newera_stripe_credentials');
        $this->assertIsArray($stored);
        
        // Verify we can decrypt
        $decrypted = $this->crypto->decrypt($stored);
        $this->assertEquals($credential_data, $decrypted);
        
        // Verify base64 encoded data is not readable as plaintext
        $base64_data = $stored['data'];
        $this->assertNotContains('sk_test_abc123def456', $base64_data);
        $this->assertNotContains('rk_test_xyz789', $base64_data);
    }
    
    /**
     * Test: Decrypt only with correct key
     */
    public function testDecryptOnlyWithCorrectKey() {
        $original_data = 'sensitive_api_key_12345';
        $encrypted = $this->crypto->encrypt($original_data);
        
        // Should decrypt successfully with correct key
        $decrypted = $this->crypto->decrypt($encrypted);
        $this->assertEquals($original_data, $decrypted);
        
        // Tampering with IV should fail
        $tampered_iv = $encrypted;
        $tampered_iv['iv'] = base64_encode('wrong_iv_value_123456');
        $result = $this->crypto->decrypt($tampered_iv);
        $this->assertFalse($result);
        
        // Tampering with data should fail
        $tampered_data = $encrypted;
        $tampered_data['data'] = base64_encode('tampered_encrypted_data_value_here');
        $result = $this->crypto->decrypt($tampered_data);
        $this->assertFalse($result);
    }
    
    /**
     * Test: Invalid keys fail gracefully
     */
    public function testInvalidKeysFailGracefully() {
        $encrypted_data = [
            'iv' => 'invalid_base64!@#$',
            'data' => 'invalid_data!@#$'
        ];
        
        // Should return false, not throw exception
        $result = $this->crypto->decrypt($encrypted_data);
        $this->assertFalse($result);
        
        // Missing required fields
        $incomplete = ['iv' => 'dGVzdA=='];
        $result = $this->crypto->decrypt($incomplete);
        $this->assertFalse($result);
        
        // Non-array input
        $result = $this->crypto->decrypt('not_an_array');
        $this->assertFalse($result);
        
        $result = $this->crypto->decrypt(null);
        $this->assertFalse($result);
    }
    
    /**
     * Test: IV randomization on each encryption
     */
    public function testIVRandomizationOnEachEncryption() {
        $data = 'Test data for IV randomization';
        $ivs = [];
        
        // Encrypt the same data 10 times
        for ($i = 0; $i < 10; $i++) {
            $encrypted = $this->crypto->encrypt($data);
            $this->assertArrayHasKey('iv', $encrypted);
            $ivs[] = $encrypted['iv'];
            
            // Verify each encryption can be decrypted
            $decrypted = $this->crypto->decrypt($encrypted);
            $this->assertEquals($data, $decrypted);
        }
        
        // Verify all IVs are different
        $unique_ivs = array_unique($ivs);
        $this->assertCount(10, $unique_ivs, 'All IVs should be unique');
        
        // Verify that encrypted data is different even with same input
        $encrypted1 = $this->crypto->encrypt($data);
        $encrypted2 = $this->crypto->encrypt($data);
        $this->assertNotEquals($encrypted1['data'], $encrypted2['data']);
        $this->assertNotEquals($encrypted1['iv'], $encrypted2['iv']);
    }
    
    /**
     * Test: No plaintext credentials in logs
     */
    public function testNoPlaintextCredentialsInLogs() {
        // Create temporary log file for this test
        $test_log_file = sys_get_temp_dir() . '/newera_test_security.log';
        
        // Encrypt sensitive data
        $sensitive_data = 'sk_live_abc123def456xyz789';
        $encrypted = $this->crypto->encrypt($sensitive_data);
        
        // Log the encrypted data (safe to log)
        $log_message = 'Encrypted credential: ' . json_encode($encrypted);
        file_put_contents($test_log_file, $log_message . "\n");
        
        // Verify plaintext is NOT in logs
        $log_content = file_get_contents($test_log_file);
        $this->assertNotContains('sk_live_abc123def456xyz789', $log_content);
        $this->assertNotContains('sk_live', $log_content);
        
        // Verify encrypted data IS in logs (for debugging)
        $this->assertContains('Encrypted credential:', $log_content);
        $this->assertContains('"iv":', $log_content);
        $this->assertContains('"data":', $log_content);
        
        // Cleanup
        unlink($test_log_file);
    }
    
    /**
     * Test: Encryption metadata preserved
     */
    public function testEncryptionMetadataPreserved() {
        $data = 'test_data_for_metadata';
        $encrypted = $this->crypto->encrypt($data);
        
        // Verify all required metadata fields
        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('data', $encrypted);
        $this->assertArrayHasKey('version', $encrypted);
        $this->assertArrayHasKey('timestamp', $encrypted);
        
        // Get metadata
        $metadata = $this->crypto->get_metadata($encrypted);
        $this->assertIsArray($metadata);
        $this->assertEquals('1.0', $metadata['version']);
        $this->assertIsInt($metadata['timestamp']);
        $this->assertEquals(16, $metadata['iv_length']); // AES-256-CBC IV length
        $this->assertGreaterThan(0, $metadata['data_size']);
    }
    
    /**
     * Test: Multiple encryption algorithms consistency
     */
    public function testEncryptionConsistency() {
        $test_data = [
            'string' => 'test_string_value',
            'number' => 12345,
            'boolean' => true,
            'array' => ['nested' => 'value'],
        ];
        
        for ($i = 0; $i < 5; $i++) {
            $encrypted = $this->crypto->encrypt($test_data);
            $decrypted = $this->crypto->decrypt($encrypted);
            
            // Each round-trip should be consistent
            $this->assertEquals($test_data, $decrypted);
        }
    }
    
    /**
     * Test: Crypto availability check
     */
    public function testCryptoAvailability() {
        $this->assertTrue($this->crypto->is_available());
    }
    
    /**
     * Test: Large credential storage
     */
    public function testLargeCredentialStorage() {
        // Simulate storing large API responses
        $large_credentials = [
            'api_key' => str_repeat('a', 1000),
            'config' => [
                'endpoints' => array_fill(0, 50, 'https://example.com/api/v1/endpoint'),
                'tokens' => array_fill(0, 20, 'token_value_12345678901234567890'),
            ],
            'settings' => array_fill(0, 100, ['key' => 'value']),
        ];
        
        // Should encrypt and decrypt successfully
        $encrypted = $this->crypto->encrypt($large_credentials);
        $this->assertIsArray($encrypted);
        
        $decrypted = $this->crypto->decrypt($encrypted);
        $this->assertEquals($large_credentials, $decrypted);
    }
}
