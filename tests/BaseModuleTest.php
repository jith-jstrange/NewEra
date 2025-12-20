<?php
/**
 * Tests for Newera\Modules\BaseModule class
 */

namespace Newera\Tests;

use Newera\Modules\BaseModule;
use Newera\Core\StateManager;
use Newera\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Concrete implementation of BaseModule for testing
 */
class TestModule extends BaseModule {
    public function getId() {
        return 'test_module';
    }
    
    public function getName() {
        return 'Test Module';
    }
    
    public function getType() {
        return 'test';
    }
    
    public function getDescription() {
        return 'Test module for unit testing';
    }
    
    // Expose protected methods for testing
    public function setCredential($key, $value) {
        return parent::set_credential($key, $value);
    }
    
    public function getCredential($key, $default = null) {
        return parent::get_credential($key, $default);
    }
    
    public function hasCredential($key) {
        return parent::has_credential($key);
    }
    
    public function deleteCredential($key) {
        return parent::delete_credential($key);
    }
    
    public function getModuleSettings() {
        return parent::get_module_settings();
    }
    
    public function updateModuleSettings($settings) {
        return parent::update_module_settings($settings);
    }
    
    public function getSetting($key, $default = null) {
        return parent::get_setting($key, $default);
    }
    
    public function updateSetting($key, $value) {
        return parent::update_setting($key, $value);
    }
    
    public function logInfo($message, $context = []) {
        parent::log_info($message, $context);
    }
    
    public function logWarning($message, $context = []) {
        parent::log_warning($message, $context);
    }
    
    public function logError($message, $context = []) {
        parent::log_error($message, $context);
    }
}

/**
 * BaseModule test case
 */
class BaseModuleTest extends TestCase {
    /**
     * Test module instance
     */
    private $module;
    
    /**
     * StateManager instance
     */
    private $stateManager;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->logger = new Logger();
        $this->module = new TestModule($this->stateManager, $this->logger);
    }
    
    /**
     * Test module ID
     */
    public function testGetId() {
        $this->assertEquals('test_module', $this->module->getId());
    }
    
    /**
     * Test module name
     */
    public function testGetName() {
        $this->assertEquals('Test Module', $this->module->getName());
    }
    
    /**
     * Test module type
     */
    public function testGetType() {
        $this->assertEquals('test', $this->module->getType());
    }
    
    /**
     * Test module description
     */
    public function testGetDescription() {
        $this->assertEquals('Test module for unit testing', $this->module->getDescription());
    }
    
    /**
     * Test default settings schema
     */
    public function testGetSettingsSchema() {
        $schema = $this->module->getSettingsSchema();
        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }
    
    /**
     * Test validate credentials returns valid by default
     */
    public function testValidateCredentialsDefaultBehavior() {
        $result = $this->module->validateCredentials(['api_key' => 'test']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }
    
    /**
     * Test is configured returns false by default
     */
    public function testIsConfiguredDefaultBehavior() {
        $this->assertFalse($this->module->isConfigured());
    }
    
    /**
     * Test set and get active state
     */
    public function testSetAndGetActive() {
        $this->assertFalse($this->module->isActive());
        
        $this->module->setActive(true);
        $this->assertTrue($this->module->isActive());
        
        $this->module->setActive(false);
        $this->assertFalse($this->module->isActive());
    }
    
    /**
     * Test boot method
     */
    public function testBoot() {
        $this->module->setActive(true);
        $this->module->boot();
        
        // Boot should not throw any errors
        $this->assertTrue(true);
    }
    
    /**
     * Test set and get credentials
     */
    public function testSetAndGetCredential() {
        $key = 'api_key';
        $value = 'sk_test_1234567890';
        
        $result = $this->module->setCredential($key, $value);
        $this->assertTrue($result);
        
        $retrieved = $this->module->getCredential($key);
        $this->assertEquals($value, $retrieved);
    }
    
    /**
     * Test get credential with default value
     */
    public function testGetCredentialWithDefault() {
        $default = 'default_value';
        $retrieved = $this->module->getCredential('nonexistent_key', $default);
        $this->assertEquals($default, $retrieved);
    }
    
    /**
     * Test has credential
     */
    public function testHasCredential() {
        $key = 'api_key';
        $value = 'sk_test_1234567890';
        
        $this->assertFalse($this->module->hasCredential($key));
        
        $this->module->setCredential($key, $value);
        $this->assertTrue($this->module->hasCredential($key));
    }
    
    /**
     * Test delete credential
     */
    public function testDeleteCredential() {
        $key = 'api_key';
        $value = 'sk_test_1234567890';
        
        $this->module->setCredential($key, $value);
        $this->assertTrue($this->module->hasCredential($key));
        
        $result = $this->module->deleteCredential($key);
        $this->assertTrue($result);
        
        $this->assertFalse($this->module->hasCredential($key));
    }
    
    /**
     * Test get module settings
     */
    public function testGetModuleSettings() {
        $settings = $this->module->getModuleSettings();
        $this->assertIsArray($settings);
    }
    
    /**
     * Test update module settings
     */
    public function testUpdateModuleSettings() {
        $settings = [
            'enabled' => true,
            'api_version' => '2.0'
        ];
        
        $result = $this->module->updateModuleSettings($settings);
        $this->assertTrue($result);
        
        $retrieved = $this->module->getModuleSettings();
        $this->assertEquals($settings, $retrieved);
    }
    
    /**
     * Test get setting
     */
    public function testGetSetting() {
        $settings = [
            'enabled' => true,
            'api_version' => '2.0'
        ];
        
        $this->module->updateModuleSettings($settings);
        
        $enabled = $this->module->getSetting('enabled');
        $this->assertTrue($enabled);
        
        $version = $this->module->getSetting('api_version');
        $this->assertEquals('2.0', $version);
    }
    
    /**
     * Test get setting with default
     */
    public function testGetSettingWithDefault() {
        $default = 'default_value';
        $value = $this->module->getSetting('nonexistent_key', $default);
        $this->assertEquals($default, $value);
    }
    
    /**
     * Test update setting
     */
    public function testUpdateSetting() {
        $result = $this->module->updateSetting('enabled', true);
        $this->assertTrue($result);
        
        $enabled = $this->module->getSetting('enabled');
        $this->assertTrue($enabled);
    }
    
    /**
     * Test module without state manager
     */
    public function testModuleWithoutStateManager() {
        $module = new TestModule(null, null);
        
        $result = $module->setCredential('test_key', 'test_value');
        $this->assertFalse($result);
        
        $value = $module->getCredential('test_key', 'default');
        $this->assertEquals('default', $value);
        
        $this->assertFalse($module->hasCredential('test_key'));
    }
    
    /**
     * Test logging methods don't throw errors
     */
    public function testLoggingMethods() {
        $this->module->logInfo('Test info message', ['context' => 'test']);
        $this->module->logWarning('Test warning message', ['context' => 'test']);
        $this->module->logError('Test error message', ['context' => 'test']);
        
        // Should not throw any errors
        $this->assertTrue(true);
    }
    
    /**
     * Test credential isolation between modules
     */
    public function testCredentialIsolation() {
        $module1 = new TestModule($this->stateManager, $this->logger);
        
        // Create a second test module with different ID
        $module2 = new class($this->stateManager, $this->logger) extends BaseModule {
            public function getId() {
                return 'different_module';
            }
            public function getName() {
                return 'Different Module';
            }
            public function getType() {
                return 'test';
            }
        };
        
        $key = 'shared_key';
        $value1 = 'module1_value';
        $value2 = 'module2_value';
        
        $module1->setCredential($key, $value1);
        $module2->setCredential($key, $value2);
        
        $retrieved1 = $module1->getCredential($key);
        $retrieved2 = $module2->getCredential($key);
        
        $this->assertEquals($value1, $retrieved1);
        $this->assertEquals($value2, $retrieved2);
        $this->assertNotEquals($retrieved1, $retrieved2);
    }
    
    /**
     * Test update existing credential
     */
    public function testUpdateExistingCredential() {
        $key = 'api_key';
        $original = 'original_value';
        $updated = 'updated_value';
        
        $this->module->setCredential($key, $original);
        $this->assertEquals($original, $this->module->getCredential($key));
        
        $this->module->setCredential($key, $updated);
        $this->assertEquals($updated, $this->module->getCredential($key));
    }
    
    /**
     * Test register hooks does nothing by default
     */
    public function testRegisterHooks() {
        $this->module->registerHooks();
        
        // Should not throw any errors
        $this->assertTrue(true);
    }
}
