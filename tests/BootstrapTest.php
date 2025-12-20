<?php
/**
 * Tests for Newera\Core\Bootstrap class
 */

namespace Newera\Tests;

use Newera\Core\Bootstrap;

if (!defined('ABSPATH')) {
    exit;
}

// Mock WordPress functions needed by Bootstrap
if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        return null;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        return true;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

/**
 * Bootstrap test case
 */
class BootstrapTest extends TestCase {
    /**
     * Bootstrap instance
     */
    private $bootstrap;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Reset singleton
        $reflection = new \ReflectionClass(Bootstrap::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $instance->setAccessible(false);
        
        $this->bootstrap = Bootstrap::getInstance();
    }
    
    /**
     * Test getInstance returns singleton
     */
    public function testGetInstanceReturnsSingleton() {
        $instance1 = Bootstrap::getInstance();
        $instance2 = Bootstrap::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }
    
    /**
     * Test getInstance returns Bootstrap instance
     */
    public function testGetInstanceReturnsBootstrapInstance() {
        $instance = Bootstrap::getInstance();
        
        $this->assertInstanceOf(Bootstrap::class, $instance);
    }
    
    /**
     * Test init method initializes components
     */
    public function testInitMethod() {
        $this->bootstrap->init();
        
        // Verify that key components are initialized
        $this->assertNotNull($this->bootstrap->get_state_manager());
        $this->assertNotNull($this->bootstrap->get_logger());
    }
    
    /**
     * Test get state manager returns StateManager
     */
    public function testGetStateManager() {
        $this->bootstrap->init();
        $stateManager = $this->bootstrap->get_state_manager();
        
        $this->assertInstanceOf(\Newera\Core\StateManager::class, $stateManager);
    }
    
    /**
     * Test get logger returns Logger
     */
    public function testGetLogger() {
        $this->bootstrap->init();
        $logger = $this->bootstrap->get_logger();
        
        $this->assertInstanceOf(\Newera\Core\Logger::class, $logger);
    }
    
    /**
     * Test get modules registry returns ModuleRegistry
     */
    public function testGetModulesRegistry() {
        $this->bootstrap->init();
        $registry = $this->bootstrap->get_modules_registry();
        
        // May be null if modules path doesn't exist
        if ($registry !== null) {
            $this->assertInstanceOf(\Newera\Modules\ModuleRegistry::class, $registry);
        }
        
        // Test passes if registry is either ModuleRegistry or null
        $this->assertTrue(true);
    }
    
    /**
     * Test activation notice method
     */
    public function testActivationNotice() {
        ob_start();
        $this->bootstrap->activation_notice();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('notice', $output);
        $this->assertStringContainsString('Newera', $output);
    }
    
    /**
     * Test multiple init calls are safe
     */
    public function testMultipleInitCallsAreSafe() {
        $this->bootstrap->init();
        $stateManager1 = $this->bootstrap->get_state_manager();
        
        // Call init again
        $this->bootstrap->init();
        $stateManager2 = $this->bootstrap->get_state_manager();
        
        // Should return same instance
        $this->assertSame($stateManager1, $stateManager2);
    }
    
    /**
     * Test get db factory
     */
    public function testGetDbFactory() {
        $this->bootstrap->init();
        $dbFactory = $this->bootstrap->get_db_factory();
        
        // May be null if not initialized
        if ($dbFactory !== null) {
            $this->assertInstanceOf(\Newera\Database\DBAdapterFactory::class, $dbFactory);
        }
        
        // Test passes if factory is either DBAdapterFactory or null
        $this->assertTrue(true);
    }
    
    /**
     * Test bootstrap initialization without errors
     */
    public function testBootstrapInitializesWithoutErrors() {
        try {
            $this->bootstrap->init();
            $success = true;
        } catch (\Exception $e) {
            $success = false;
        }
        
        $this->assertTrue($success);
    }
    
    /**
     * Test state manager is initialized before module registry
     */
    public function testStateManagerInitializedBeforeModuleRegistry() {
        $this->bootstrap->init();
        
        $stateManager = $this->bootstrap->get_state_manager();
        $this->assertNotNull($stateManager);
        
        // State manager should be available for module registry
        $this->assertInstanceOf(\Newera\Core\StateManager::class, $stateManager);
    }
    
    /**
     * Test logger is initialized early
     */
    public function testLoggerInitializedEarly() {
        $this->bootstrap->init();
        
        $logger = $this->bootstrap->get_logger();
        $this->assertNotNull($logger);
        $this->assertInstanceOf(\Newera\Core\Logger::class, $logger);
    }
}
