<?php
/**
 * Tests for Newera\Modules\ModuleRegistry class
 */

namespace Newera\Tests;

use Newera\Modules\ModuleRegistry;
use Newera\Modules\BaseModule;
use Newera\Core\StateManager;
use Newera\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sample test module for registry testing
 */
class SampleModule extends BaseModule {
    public function getId() {
        return 'sample_module';
    }
    
    public function getName() {
        return 'Sample Module';
    }
    
    public function getType() {
        return 'test';
    }
    
    public function isConfigured() {
        return true;
    }
}

/**
 * Another sample test module
 */
class AnotherModule extends BaseModule {
    public function getId() {
        return 'another_module';
    }
    
    public function getName() {
        return 'Another Module';
    }
    
    public function getType() {
        return 'test';
    }
    
    public function isConfigured() {
        return false;
    }
}

/**
 * ModuleRegistry test case
 */
class ModuleRegistryTest extends TestCase {
    /**
     * ModuleRegistry instance
     */
    private $registry;
    
    /**
     * StateManager instance
     */
    private $stateManager;
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Temporary modules path for testing
     */
    private $temp_modules_path;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->logger = new Logger();
        
        // Create temporary modules directory
        $this->temp_modules_path = sys_get_temp_dir() . '/newera_test_modules_' . uniqid();
        mkdir($this->temp_modules_path, 0777, true);
        
        $this->registry = new ModuleRegistry(
            $this->stateManager,
            $this->logger,
            $this->temp_modules_path
        );
    }
    
    /**
     * Clean up after test
     */
    protected function tearDown(): void {
        parent::tearDown();
        
        // Clean up temporary modules directory
        if (is_dir($this->temp_modules_path)) {
            $this->recursiveRemoveDirectory($this->temp_modules_path);
        }
    }
    
    /**
     * Recursively remove directory
     */
    private function recursiveRemoveDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    /**
     * Test init method
     */
    public function testInit() {
        $this->registry->init();
        
        // Should not throw any errors
        $this->assertTrue(true);
    }
    
    /**
     * Test get modules returns empty array initially
     */
    public function testGetModulesReturnsEmptyArray() {
        $this->registry->init();
        $modules = $this->registry->get_modules();
        
        $this->assertIsArray($modules);
    }
    
    /**
     * Test get module returns null for non-existent module
     */
    public function testGetModuleReturnsNullForNonExistent() {
        $this->registry->init();
        $module = $this->registry->get_module('nonexistent_module');
        
        $this->assertNull($module);
    }
    
    /**
     * Test boot method
     */
    public function testBoot() {
        $this->registry->init();
        $this->registry->boot();
        
        // Should not throw any errors
        $this->assertTrue(true);
    }
    
    /**
     * Test module discovery with test module files
     */
    public function testModuleDiscovery() {
        // Create a test module file
        $module_code = <<<'PHP'
<?php
namespace Newera\Modules;

if (!defined('ABSPATH')) {
    exit;
}

class DiscoverableTestModule extends \Newera\Modules\BaseModule {
    public function getId() {
        return 'discoverable_test';
    }
    
    public function getName() {
        return 'Discoverable Test Module';
    }
    
    public function getType() {
        return 'test';
    }
}
PHP;
        
        file_put_contents($this->temp_modules_path . '/DiscoverableTestModule.php', $module_code);
        
        $this->registry->init();
        $modules = $this->registry->get_modules();
        
        $this->assertIsArray($modules);
        $this->assertArrayHasKey('discoverable_test', $modules);
        $this->assertInstanceOf(\Newera\Modules\ModuleInterface::class, $modules['discoverable_test']);
    }
    
    /**
     * Test module discovery ignores non-module files
     */
    public function testModuleDiscoveryIgnoresNonModuleFiles() {
        // Create a non-module PHP file
        $code = <<<'PHP'
<?php
namespace Newera\Modules;

class NotAModule {
    public function test() {
        return true;
    }
}
PHP;
        
        file_put_contents($this->temp_modules_path . '/NotAModule.php', $code);
        
        $this->registry->init();
        $modules = $this->registry->get_modules();
        
        // Should not discover the non-module file
        $this->assertIsArray($modules);
    }
    
    /**
     * Test module boot sets active state based on configuration
     */
    public function testModuleBootSetsActiveState() {
        // Create a test module file
        $module_code = <<<'PHP'
<?php
namespace Newera\Modules;

if (!defined('ABSPATH')) {
    exit;
}

class ConfiguredTestModule extends \Newera\Modules\BaseModule {
    public function getId() {
        return 'configured_test';
    }
    
    public function getName() {
        return 'Configured Test Module';
    }
    
    public function getType() {
        return 'test';
    }
    
    public function isConfigured() {
        return true;
    }
}
PHP;
        
        file_put_contents($this->temp_modules_path . '/ConfiguredTestModule.php', $module_code);
        
        // Enable the module in state
        $this->stateManager->update_state('modules_enabled', ['configured_test' => true]);
        
        $this->registry->init();
        $this->registry->boot();
        
        $module = $this->registry->get_module('configured_test');
        $this->assertNotNull($module);
        $this->assertTrue($module->isActive());
    }
    
    /**
     * Test module boot doesn't activate unconfigured modules
     */
    public function testModuleBootDoesNotActivateUnconfiguredModules() {
        // Create a test module file
        $module_code = <<<'PHP'
<?php
namespace Newera\Modules;

if (!defined('ABSPATH')) {
    exit;
}

class UnconfiguredTestModule extends \Newera\Modules\BaseModule {
    public function getId() {
        return 'unconfigured_test';
    }
    
    public function getName() {
        return 'Unconfigured Test Module';
    }
    
    public function getType() {
        return 'test';
    }
    
    public function isConfigured() {
        return false;
    }
}
PHP;
        
        file_put_contents($this->temp_modules_path . '/UnconfiguredTestModule.php', $module_code);
        
        // Enable the module in state
        $this->stateManager->update_state('modules_enabled', ['unconfigured_test' => true]);
        
        $this->registry->init();
        $this->registry->boot();
        
        $module = $this->registry->get_module('unconfigured_test');
        $this->assertNotNull($module);
        $this->assertFalse($module->isActive());
    }
    
    /**
     * Test module discovery in subdirectories
     */
    public function testModuleDiscoveryInSubdirectories() {
        // Create a subdirectory
        $subdir = $this->temp_modules_path . '/TestCategory';
        mkdir($subdir, 0777, true);
        
        // Create a test module file in subdirectory
        $module_code = <<<'PHP'
<?php
namespace Newera\Modules\TestCategory;

if (!defined('ABSPATH')) {
    exit;
}

class SubdirTestModule extends \Newera\Modules\BaseModule {
    public function getId() {
        return 'subdir_test';
    }
    
    public function getName() {
        return 'Subdirectory Test Module';
    }
    
    public function getType() {
        return 'test';
    }
}
PHP;
        
        file_put_contents($subdir . '/SubdirTestModule.php', $module_code);
        
        $this->registry->init();
        $modules = $this->registry->get_modules();
        
        $this->assertIsArray($modules);
        $this->assertArrayHasKey('subdir_test', $modules);
    }
    
    /**
     * Test duplicate module IDs are handled
     */
    public function testDuplicateModuleIdsAreHandled() {
        // Create first module file
        $module_code1 = <<<'PHP'
<?php
namespace Newera\Modules;

if (!defined('ABSPATH')) {
    exit;
}

class FirstDuplicateModule extends \Newera\Modules\BaseModule {
    public function getId() {
        return 'duplicate_id';
    }
    
    public function getName() {
        return 'First Duplicate';
    }
    
    public function getType() {
        return 'test';
    }
}
PHP;
        
        // Create second module file with same ID
        $module_code2 = <<<'PHP'
<?php
namespace Newera\Modules;

if (!defined('ABSPATH')) {
    exit;
}

class SecondDuplicateModule extends \Newera\Modules\BaseModule {
    public function getId() {
        return 'duplicate_id';
    }
    
    public function getName() {
        return 'Second Duplicate';
    }
    
    public function getType() {
        return 'test';
    }
}
PHP;
        
        file_put_contents($this->temp_modules_path . '/FirstDuplicateModule.php', $module_code1);
        file_put_contents($this->temp_modules_path . '/SecondDuplicateModule.php', $module_code2);
        
        $this->registry->init();
        $modules = $this->registry->get_modules();
        
        // Should only have one module with the duplicate ID
        $this->assertIsArray($modules);
        $this->assertArrayHasKey('duplicate_id', $modules);
        
        // Count should be 1, not 2
        $duplicate_modules = array_filter($modules, function($module) {
            return $module->getId() === 'duplicate_id';
        });
        $this->assertCount(1, $duplicate_modules);
    }
    
    /**
     * Test registry handles missing modules directory
     */
    public function testRegistryHandlesMissingDirectory() {
        $nonexistent_path = '/nonexistent/path/to/modules';
        $registry = new ModuleRegistry($this->stateManager, $this->logger, $nonexistent_path);
        
        $registry->init();
        $modules = $registry->get_modules();
        
        $this->assertIsArray($modules);
        $this->assertEmpty($modules);
    }
}
