<?php
/**
 * Tests for Newera\Core\Crypto class
 */

namespace Newera\Tests;

use Newera\Core\Crypto;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crypto test case
 */
class CryptoTest extends TestCase {
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
    }
    
    /**
     * Test crypto availability
     */
    public function testIsAvailable() {
        $this->assertTrue($this->crypto->is_available());
    }
    
    /**
     * Test encryption and decryption round trip with string data
     */
    public function testEncryptDecryptStringData() {
        $original_data = 'This is a secret message that should be encrypted';
        
        // Encrypt the data
        $encrypted = $this->crypto->encrypt($original_data);
        $this->assertIsArray($encrypted);
        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('data', $encrypted);
        $this->assertArrayHasKey('version', $encrypted);
        $this->assertArrayHasKey('timestamp', $encrypted);
        
        // Decrypt the data
        $decrypted = $this->crypto->decrypt($encrypted);
        $this->assertEquals($original_data, $decrypted);
    }
    
    /**
     * Test encryption and decryption round trip with array data
     */
    public function testEncryptDecryptArrayData() {
        $original_data = [
            'api_key' => 'sk_test_1234567890abcdef',
            'api_secret' => 'secret_abcdef1234567890',
            'settings' => [
                'enabled' => true,
                'timeout' => 30
            ]
        ];
        
        // Encrypt the data
        $encrypted = $this->crypto->encrypt($original_data);
        $this->assertIsArray($encrypted);
        
        // Decrypt the data
        $decrypted = $this->crypto->decrypt($encrypted);
        $this->assertEquals($original_data, $decrypted);
        $this->assertArrayHasKey('api_key', $decrypted);
        $this->assertArrayHasKey('api_secret', $decrypted);
        $this->assertArrayHasKey('settings', $decrypted);
    }
    
    /**
     * Test encryption and decryption round trip with numeric data
     */
    public function testEncryptDecryptNumericData() {
        $original_data = 12345;
        
        // Encrypt the data
        $encrypted = $this->crypto->encrypt($original_data);
        $this->assertIsArray($encrypted);
        
        // Decrypt the data
        $decrypted = $this->crypto->decrypt($encrypted);
        $this->assertEquals($original_data, $decrypted);
    }
    
    /**
     * Test encryption and decryption round trip with object data
     */
    public function testEncryptDecryptObjectData() {
        $original_data = (object) [
            'username' => 'testuser',
            'password' => 'secure_password_123',
            'preferences' => (object) [
                'theme' => 'dark',
                'notifications' => true
            ]
        ];
        
        // Encrypt the data
        $encrypted = $this->crypto->encrypt($original_data);
        $this->assertIsArray($encrypted);
        
        // Decrypt the data
        $decrypted = $this->crypto->decrypt($encrypted);
        $this->assertEquals($original_data, $decrypted);
        $this->assertIsObject($decrypted);
        $this->assertEquals('testuser', $decrypted->username);
        $this->assertEquals('secure_password_123', $decrypted->password);
    }
    
    /**
     * Test encryption with empty data returns false
     */
    public function testEncryptEmptyDataReturnsFalse() {
        $this->assertFalse($this->crypto->encrypt(''));
        $this->assertFalse($this->crypto->encrypt(null));
        $this->assertFalse($this->crypto->encrypt(false));
        $this->assertFalse($this->crypto->encrypt([]));
    }
    
    /**
     * Test decryption with invalid data returns false
     */
    public function testDecryptInvalidDataReturnsFalse() {
        // Test with invalid array structure
        $this->assertFalse($this->crypto->decrypt(['invalid' => 'data']));
        $this->assertFalse($this->crypto->decrypt(['iv' => 'invalid']));
        $this->assertFalse($this->crypto->decrypt(['data' => 'invalid']));
        
        // Test with invalid base64 data
        $this->assertFalse($this->crypto->decrypt([
            'iv' => '!@#$%^&*()',
            'data' => '!@#$%^&*()'
        ]));
        
        // Test with non-array data
        $this->assertFalse($this->crypto->decrypt('invalid_string'));
        $this->assertFalse($this->crypto->decrypt(123));
        $this->assertFalse($this->crypto->decrypt(null));
    }
    
    /**
     * Test encryption produces different results each time (different IVs)
     */
    public function testEncryptionProducesDifferentResults() {
        $data = 'This should produce different encrypted results';
        
        $encrypted1 = $this->crypto->encrypt($data);
        $encrypted2 = $this->crypto->encrypt($data);
        
        $this->assertNotEquals($encrypted1['iv'], $encrypted2['iv']);
        $this->assertNotEquals($encrypted1['data'], $encrypted2['data']);
        
        // But both should decrypt to the same data
        $this->assertEquals($data, $this->crypto->decrypt($encrypted1));
        $this->assertEquals($data, $this->crypto->decrypt($encrypted2));
    }
    
    /**
     * Test get metadata
     */
    public function testGetMetadata() {
        $data = 'test data for metadata';
        $encrypted = $this->crypto->encrypt($data);
        
        $metadata = $this->crypto->get_metadata($encrypted);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('version', $metadata);
        $this->assertArrayHasKey('timestamp', $metadata);
        $this->assertArrayHasKey('iv_length', $metadata);
        $this->assertArrayHasKey('data_size', $metadata);
        $this->assertEquals(16, $metadata['iv_length']); // AES-256-CBC IV length
    }
    
    /**
     * Test encryption with special characters and unicode
     */
    public function testEncryptDecryptSpecialCharacters() {
        $original_data = 'Special chars: !@#$%^&*()_+{}|:<>?[]\\;\'",./ and émojis 🚀🎉';
        
        // Encrypt the data
        $encrypted = $this->crypto->encrypt($original_data);
        $this->assertIsArray($encrypted);
        
        // Decrypt the data
        $decrypted = $this->crypto->decrypt($encrypted);
        $this->assertEquals($original_data, $decrypted);
    }
    
    /**
     * Test encryption with large data
     */
    public function testEncryptDecryptLargeData() {
        $original_data = str_repeat('This is a long string to test encryption performance. ', 1000);
        
        // Encrypt the data
        $encrypted = $this->crypto->encrypt($original_data);
        $this->assertIsArray($encrypted);
        
        // Decrypt the data
        $decrypted = $this->crypto->decrypt($encrypted);
        $this->assertEquals($original_data, $decrypted);
    }
    
    /**
     * Test decryption with corrupted encrypted data
     */
    public function testDecryptCorruptedDataReturnsFalse() {
        $original_data = 'test data';
        $encrypted = $this->crypto->encrypt($original_data);
        
        // Corrupt the data
        $corrupted = $encrypted;
        $corrupted['data'] = base64_encode('corrupted_data');
        
        $this->assertFalse($this->crypto->decrypt($corrupted));
    }
}