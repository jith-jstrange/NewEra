<?php
/**
 * Tests for Newera\Core\StateManager class
 */

namespace Newera\Tests;

use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * StateManager test case
 */
class StateManagerTest extends TestCase {
    /**
     * StateManager instance
     */
    private $stateManager;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->stateManager = new StateManager();
        
        // Mock $wpdb global
        global $wpdb;
        $wpdb = new \stdClass();
        $wpdb->options = 'wp_options';
        $wpdb->prepare = function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", $query), $args);
        };
        $wpdb->get_col = function($query) {
            return \Newera\Tests\MockWPDB::get_col($query);
        };
    }
    
    /**
     * Test crypto availability
     */
    public function testIsCryptoAvailable() {
        $this->assertTrue($this->stateManager->is_crypto_available());
    }
    
    /**
     * Test setSecure and getSecure with string data
     */
    public function testSetSecureAndGetSecureStringData() {
        $module = 'payment_gateway';
        $key = 'stripe_api_key';
        $data = 'sk_test_1234567890abcdef';
        
        // Set secure data
        $result = $this->stateManager->setSecure($module, $key, $data);
        $this->assertTrue($result);
        
        // Get secure data
        $retrieved = $this->stateManager->getSecure($module, $key);
        $this->assertEquals($data, $retrieved);
    }
    
    /**
     * Test setSecure and getSecure with array data
     */
    public function testSetSecureAndGetSecureArrayData() {
        $module = 'api_client';
        $key = 'oauth_credentials';
        $data = [
            'client_id' => 'test_client_123',
            'client_secret' => 'super_secret_456',
            'scope' => ['read', 'write'],
            'expires_at' => 1234567890
        ];
        
        // Set secure data
        $result = $this->stateManager->setSecure($module, $key, $data);
        $this->assertTrue($result);
        
        // Get secure data
        $retrieved = $this->stateManager->getSecure($module, $key);
        $this->assertEquals($data, $retrieved);
    }
    
    /**
     * Test setSecure and getSecure with default value
     */
    public function testGetSecureWithDefaultValue() {
        $module = 'test_module';
        $key = 'nonexistent_key';
        $default = 'default_value';
        
        // Get non-existent secure data
        $retrieved = $this->stateManager->getSecure($module, $key, $default);
        $this->assertEquals($default, $retrieved);
    }
    
    /**
     * Test hasSecure method
     */
    public function testHasSecure() {
        $module = 'test_module';
        $key = 'test_key';
        $data = 'test_data';
        
        // Initially should not exist
        $this->assertFalse($this->stateManager->hasSecure($module, $key));
        
        // Set secure data
        $this->stateManager->setSecure($module, $key, $data);
        
        // Now should exist
        $this->assertTrue($this->stateManager->hasSecure($module, $key));
    }
    
    /**
     * Test deleteSecure method
     */
    public function testDeleteSecure() {
        $module = 'test_module';
        $key = 'test_key';
        $data = 'test_data';
        
        // Set and verify existence
        $this->stateManager->setSecure($module, $key, $data);
        $this->assertTrue($this->stateManager->hasSecure($module, $key));
        
        // Delete the secure data
        $result = $this->stateManager->deleteSecure($module, $key);
        $this->assertTrue($result);
        
        // Verify it's gone
        $this->assertFalse($this->stateManager->hasSecure($module, $key));
    }
    
    /**
     * Test updateSecure method
     */
    public function testUpdateSecure() {
        $module = 'test_module';
        $key = 'test_key';
        $original_data = 'original_data';
        $updated_data = 'updated_data';
        
        // Set original data
        $this->stateManager->setSecure($module, $key, $original_data);
        $this->assertEquals($original_data, $this->stateManager->getSecure($module, $key));
        
        // Update data
        $result = $this->stateManager->updateSecure($module, $key, $updated_data);
        $this->assertTrue($result);
        
        // Verify updated data
        $this->assertEquals($updated_data, $this->stateManager->getSecure($module, $key));
    }
    
    /**
     * Test setBulkSecure and getBulkSecure methods
     */
    public function testBulkSecureOperations() {
        $module = 'test_module';
        $data_array = [
            'key1' => 'data1',
            'key2' => 'data2',
            'key3' => ['nested' => 'data3']
        ];
        
        // Set bulk secure data
        $results = $this->stateManager->setBulkSecure($module, $data_array);
        $this->assertIsArray($results);
        $this->assertTrue($results['key1']);
        $this->assertTrue($results['key2']);
        $this->assertTrue($results['key3']);
        
        // Get bulk secure data
        $keys = array_keys($data_array);
        $retrieved = $this->stateManager->getBulkSecure($module, $keys);
        
        $this->assertEquals($data_array, $retrieved);
    }
    
    /**
     * Test getSecureMetadata method
     */
    public function testGetSecureMetadata() {
        $module = 'test_module';
        $key = 'test_key';
        $data = 'test data for metadata';
        
        // Set secure data
        $this->stateManager->setSecure($module, $key, $data);
        
        // Get metadata
        $metadata = $this->stateManager->getSecureMetadata($module, $key);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('version', $metadata);
        $this->assertArrayHasKey('timestamp', $metadata);
        $this->assertArrayHasKey('iv_length', $metadata);
        $this->assertArrayHasKey('data_size', $metadata);
    }
    
    /**
     * Test multiple modules can store data independently
     */
    public function testMultipleModulesIndependentStorage() {
        $module1 = 'payment_gateway';
        $module2 = 'api_client';
        $key = 'shared_key';
        
        $data1 = 'payment_gateway_secret';
        $data2 = 'api_client_secret';
        
        // Store data in different modules
        $this->stateManager->setSecure($module1, $key, $data1);
        $this->stateManager->setSecure($module2, $key, $data2);
        
        // Retrieve data from each module
        $retrieved1 = $this->stateManager->getSecure($module1, $key);
        $retrieved2 = $this->stateManager->getSecure($module2, $key);
        
        $this->assertEquals($data1, $retrieved1);
        $this->assertEquals($data2, $retrieved2);
        $this->assertNotEquals($retrieved1, $retrieved2);
    }
    
    /**
     * Test getSecureKeys method
     */
    public function testGetSecureKeys() {
        $module = 'test_module';
        
        // Add some secure data
        $this->stateManager->setSecure($module, 'key1', 'data1');
        $this->stateManager->setSecure($module, 'key2', 'data2');
        $this->stateManager->setSecure($module, 'key3', 'data3');
        
        // Get keys (note: the current implementation returns hash-based keys)
        $keys = $this->stateManager->getSecureKeys($module);
        $this->assertIsArray($keys);
        // Since we're using hash-based keys in the current implementation,
        // we just verify we get some keys back
        $this->assertNotEmpty($keys);
    }
    
    /**
     * Test complex data types
     */
    public function testComplexDataTypes() {
        $module = 'test_module';
        $key = 'complex_data';
        
        $complex_data = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14159,
            'boolean' => true,
            'null' => null,
            'array' => ['nested' => 'array'],
            'object' => (object) ['property' => 'value'],
            'datetime' => new \DateTime('2023-01-01 12:00:00')
        ];
        
        // Set and get complex data
        $this->assertTrue($this->stateManager->setSecure($module, $key, $complex_data));
        
        $retrieved = $this->stateManager->getSecure($module, $key);
        
        // Verify structure (DateTime objects are converted to strings)
        $this->assertIsArray($retrieved);
        $this->assertEquals('test string', $retrieved['string']);
        $this->assertEquals(42, $retrieved['integer']);
        $this->assertEquals(3.14159, $retrieved['float']);
        $this->assertTrue($retrieved['boolean']);
        $this->assertNull($retrieved['null']);
        $this->assertEquals(['nested' => 'array'], $retrieved['array']);
        $this->assertIsObject($retrieved['object']);
        $this->assertEquals('value', $retrieved['object']->property);
    }
}