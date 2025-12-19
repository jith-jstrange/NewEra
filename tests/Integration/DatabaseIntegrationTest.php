<?php
/**
 * Integration tests for Database workflows
 */

namespace Newera\Tests\Integration;

use Newera\Modules\Database\DatabaseModule;
use Newera\Core\StateManager;
use Newera\Database\DatabaseAdapter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Integration Test
 * 
 * Tests end-to-end database workflows including:
 * - WordPress DB initialization
 * - External DB adapter → migrations
 * - Query routing through adapter
 * - Fallback when external DB down
 * - Mid-operation DB switching
 * - Data consistency across DBs
 */
class DatabaseIntegrationTest extends \Newera\Tests\TestCase {
    
    /**
     * Database module instance
     *
     * @var DatabaseModule
     */
    private $dbModule;
    
    /**
     * State manager instance
     *
     * @var StateManager
     */
    private $stateManager;
    
    /**
     * Mock WordPress database
     */
    private $mockWPDB;
    
    /**
     * Mock external database connection
     */
    private $mockExternalDB;
    
    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->dbModule = new DatabaseModule($this->stateManager);
        
        // Create mock databases
        $this->mockWPDB = new \Newera\Tests\MockWPDB();
        $this->mockExternalDB = $this->createMockExternalDB();
        
        // Reset state for clean test
        $this->stateManager->reset_state();
        
        // Mock WordPress database functions
        $this->mockWordPressDBFunctions();
    }
    
    /**
     * Create mock external database
     */
    private function createMockExternalDB() {
        return new class {
            private $connected = false;
            private $tables = [];
            private $data = [];
            
            public function connect($host, $dbname, $user, $pass) {
                $this->connected = true;
                return $this->connected;
            }
            
            public function isConnected() {
                return $this->connected;
            }
            
            public function query($sql) {
                if (!$this->connected) {
                    throw new \Exception('Database connection failed');
                }
                return true;
            }
            
            public function insert($table, $data) {
                if (!$this->connected) {
                    throw new \Exception('Database connection failed');
                }
                $this->data[$table][] = $data;
                return 1;
            }
            
            public function select($table, $conditions = []) {
                if (!$this->connected) {
                    throw new \Exception('Database connection failed');
                }
                return isset($this->data[$table]) ? $this->data[$table] : [];
            }
            
            public function disconnect() {
                $this->connected = false;
            }
            
            public function simulateFailure() {
                $this->connected = false;
            }
            
            public function getTables() {
                return array_keys($this->data);
            }
            
            public function clearAll() {
                $this->data = [];
            }
        };
    }
    
    /**
     * Mock WordPress database functions
     */
    private function mockWordPressDBFunctions() {
        global $wpdb;
        
        if (!isset($wpdb)) {
            $wpdb = new \stdClass();
        }
        
        $wpdb->options = 'wp_options';
        $wpdb->posts = 'wp_posts';
        $wpdb->postmeta = 'wp_postmeta';
        $wpdb->users = 'wp_users';
        $wpdb->usermeta = 'wp_usermeta';
        
        $wpdb->prepare = function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", $query), $args);
        };
        
        $wpdb->get_results = function($query, $output = OBJECT) {
            return \Newera\Tests\MockWPDB::get_results($query, $output);
        };
        
        $wpdb->get_var = function($query) {
            return \Newera\Tests\MockWPDB::get_var($query);
        };
        
        $wpdb->get_col = function($query) {
            return \Newera\Tests\MockWPDB::get_col($query);
        };
        
        $wpdb->insert = function($table, $data, $format = null) {
            return \Newera\Tests\MockWPDB::insert($table, $data, $format);
        };
        
        $wpdb->update = function($table, $data, $where, $format = null, $where_format = null) {
            return \Newera\Tests\MockWPDB::update($table, $data, $where, $format, $where_format);
        };
        
        $wpdb->delete = function($table, $where, $format = null) {
            return \Newera\Tests\MockWPDB::delete($table, $where, $format);
        };
        
        $wpdb->query = function($query) {
            return \Newera\Tests\MockWPDB::query($query);
        };
    }
    
    /**
     * Test WordPress DB initialization
     */
    public function testWordPressDBInitialization() {
        // Initialize database module
        $result = $this->dbModule->initialize();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('wordpress', $result['active_database']);
        
        // Verify WordPress tables are accessible
        $tables = $this->dbModule->getAvailableTables();
        $this->assertContains('wp_options', $tables);
        $this->assertContains('wp_posts', $tables);
        $this->assertContains('wp_users', $tables);
        
        // Test basic WordPress table operations
        $test_data = [
            'option_name' => 'test_option',
            'option_value' => 'test_value',
            'autoload' => 'yes'
        ];
        
        $insert_result = $this->dbModule->insert('wp_options', $test_data);
        $this->assertTrue($insert_result['success']);
        
        // Verify data was inserted
        $retrieved_data = $this->dbModule->select('wp_options', ['option_name' => 'test_option']);
        $this->assertNotEmpty($retrieved_data);
        $this->assertEquals('test_value', $retrieved_data[0]['option_value']);
    }
    
    /**
     * Test external DB adapter and migrations
     */
    public function testExternalDBAdapterAndMigrations() {
        // Configure external database
        $db_config = [
            'host' => 'localhost',
            'database' => 'newera_external',
            'username' => 'newera_user',
            'password' => 'secure_pass',
            'charset' => 'utf8mb4'
        ];
        
        $setup_result = $this->dbModule->setupExternalDatabase($db_config);
        $this->assertTrue($setup_result['success']);
        
        // Test connection
        $connection_result = $this->dbModule->testExternalConnection();
        $this->assertTrue($connection_result['success']);
        
        // Run migrations
        $migration_result = $this->dbModule->runMigrations();
        $this->assertTrue($migration_result['success']);
        $this->assertNotEmpty($migration_result['migrations_run']);
        
        // Verify external tables were created
        $external_tables = $this->mockExternalDB->getTables();
        $this->assertNotEmpty($external_tables);
        
        // Verify tables contain expected structure
        $this->assertContains('newera_projects', $external_tables);
        $this->assertContains('newera_subscriptions', $external_tables);
        $this->assertContains('newera_clients', $external_tables);
    }
    
    /**
     * Test query routing through adapter
     */
    public function testQueryRoutingThroughAdapter() {
        // Setup dual database configuration
        $this->dbModule->initialize();
        $this->dbModule->setupExternalDatabase([
            'host' => 'localhost',
            'database' => 'newera_test',
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);
        
        // Test WordPress query routing
        $wp_query_result = $this->dbModule->select('wp_options', []);
        $this->assertIsArray($wp_query_result);
        
        // Test external DB query routing
        $external_query_result = $this->dbModule->selectExternal('newera_projects', []);
        $this->assertIsArray($external_query_result);
        
        // Test hybrid query (WordPress + External)
        $hybrid_result = $this->dbModule->executeHybridQuery([
            'wordpress' => ['table' => 'wp_users', 'action' => 'select'],
            'external' => ['table' => 'newera_projects', 'action' => 'select']
        ]);
        
        $this->assertTrue($hybrid_result['success']);
        $this->assertArrayHasKey('wordpress_result', $hybrid_result);
        $this->assertArrayHasKey('external_result', $hybrid_result);
    }
    
    /**
     * Test fallback when external DB is down
     */
    public function testFallbackWhenExternalDBDown() {
        // Setup with external database
        $this->dbModule->initialize();
        $this->dbModule->setupExternalDatabase([
            'host' => 'localhost',
            'database' => 'newera_test',
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);
        
        // Simulate external DB failure
        $this->mockExternalDB->simulateFailure();
        
        // Test fallback behavior
        $fallback_result = $this->dbModule->executeWithFallback(function($db) {
            return $db->selectExternal('newera_projects', []);
        });
        
        $this->assertTrue($fallback_result['success']);
        $this->assertEquals('wordpress', $fallback_result['fallback_database']);
        $this->assertArrayHasKey('fallback_data', $fallback_result);
        
        // Verify graceful degradation
        $this->assertNotEmpty($fallback_result['message']);
        $this->assertStringContainsString('fallback', strtolower($fallback_result['message']));
    }
    
    /**
     * Test mid-operation database switching
     */
    public function testMidOperationDatabaseSwitching() {
        // Start with WordPress database
        $this->dbModule->initialize();
        
        // Start a "transaction-like" operation
        $operation_data = [
            'step1' => ['table' => 'wp_options', 'action' => 'insert', 'data' => ['option_name' => 'step1', 'option_value' => 'value1']],
            'step2' => ['table' => 'wp_users', 'action' => 'select', 'conditions' => ['ID' => 1]],
            'step3' => ['table' => 'newera_projects', 'action' => 'insert', 'data' => ['name' => 'Test Project']]
        ];
        
        // Execute with automatic switching based on table prefixes
        $switching_result = $this->dbModule->executeWithDatabaseSwitching($operation_data);
        
        $this->assertTrue($switching_result['success']);
        $this->assertEquals(3, $switching_result['steps_completed']);
        
        // Verify WordPress operations used wp_ tables
        $this->assertEquals('wordpress', $switching_result['database_used_step1']);
        $this->assertEquals('wordpress', $switching_result['database_used_step2']);
        
        // Verify external operations used external tables
        $this->assertEquals('external', $switching_result['database_used_step3']);
    }
    
    /**
     * Test data consistency across databases
     */
    public function testDataConsistencyAcrossDBs() {
        // Setup both databases
        $this->dbModule->initialize();
        $this->dbModule->setupExternalDatabase([
            'host' => 'localhost',
            'database' => 'newera_test',
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);
        
        // Create synchronized user across both databases
        $user_data = [
            'email' => 'consistent@example.com',
            'name' => 'Consistent User',
            'external_id' => 'ext_12345'
        ];
        
        $sync_result = $this->dbModule->synchronizeData('users', $user_data);
        
        $this->assertTrue($sync_result['success']);
        $this->assertArrayHasKey('wordpress_user_id', $sync_result);
        $this->assertArrayHasKey('external_user_id', $sync_result);
        
        // Verify data exists in both databases
        $wp_user = $this->dbModule->select('wp_users', ['user_email' => 'consistent@example.com']);
        $this->assertNotEmpty($wp_user);
        
        $external_user = $this->dbModule->selectExternal('newera_users', ['email' => 'consistent@example.com']);
        $this->assertNotEmpty($external_user);
        
        // Verify data integrity
        $this->assertEquals($wp_user[0]['user_email'], $external_user[0]['email']);
        $this->assertEquals($wp_user[0]['display_name'], $external_user[0]['name']);
    }
    
    /**
     * Test database health checks
     */
    public function testDatabaseHealthChecks() {
        // Test WordPress DB health
        $wp_health = $this->dbModule->checkDatabaseHealth('wordpress');
        $this->assertTrue($wp_health['success']);
        $this->assertTrue($wp_health['accessible']);
        $this->assertArrayHasKey('response_time', $wp_health);
        
        // Test external DB health (when connected)
        $this->dbModule->setupExternalDatabase([
            'host' => 'localhost',
            'database' => 'newera_test',
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);
        
        $external_health = $this->dbModule->checkDatabaseHealth('external');
        $this->assertTrue($external_health['success']);
        $this->assertTrue($external_health['accessible']);
        
        // Test external DB health (when failed)
        $this->mockExternalDB->simulateFailure();
        $failed_health = $this->dbModule->checkDatabaseHealth('external');
        $this->assertFalse($failed_health['success']);
        $this->assertFalse($failed_health['accessible']);
        $this->assertArrayHasKey('error', $failed_health);
    }
    
    /**
     * Test database migration rollback
     */
    public function testDatabaseMigrationRollback() {
        // Run some migrations
        $this->dbModule->setupExternalDatabase([
            'host' => 'localhost',
            'database' => 'newera_test',
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);
        
        $migration_result = $this->dbModule->runMigrations();
        $this->assertTrue($migration_result['success']);
        
        // Rollback migrations
        $rollback_result = $this->dbModule->rollbackMigrations();
        $this->assertTrue($rollback_result['success']);
        $this->assertNotEmpty($rollback_result['rolled_back']);
        
        // Verify tables were removed
        $external_tables = $this->mockExternalDB->getTables();
        $this->assertEmpty($external_tables);
    }
    
    /**
     * Test concurrent database operations
     */
    public function testConcurrentDatabaseOperations() {
        $this->dbModule->initialize();
        
        // Simulate concurrent operations
        $operations = [
            ['table' => 'wp_options', 'action' => 'insert', 'data' => ['option_name' => 'concurrent1', 'option_value' => 'value1']],
            ['table' => 'wp_options', 'action' => 'insert', 'data' => ['option_name' => 'concurrent2', 'option_value' => 'value2']],
            ['table' => 'wp_options', 'action' => 'select', 'conditions' => []],
            ['table' => 'wp_options', 'action' => 'update', 'data' => ['option_value' => 'updated'], 'conditions' => ['option_name' => 'concurrent1']]
        ];
        
        $concurrent_result = $this->dbModule->executeConcurrentOperations($operations);
        
        $this->assertTrue($concurrent_result['success']);
        $this->assertEquals(4, $concurrent_result['operations_completed']);
        
        // Verify all operations completed successfully
        foreach ($concurrent_result['operation_results'] as $result) {
            $this->assertTrue($result['success']);
        }
    }
    
    /**
     * Test database performance monitoring
     */
    public function testDatabasePerformanceMonitoring() {
        $this->dbModule->initialize();
        
        // Execute some operations and monitor performance
        $performance_data = $this->dbModule->executeWithPerformanceMonitoring(function($db) {
            $db->select('wp_options', []);
            $db->select('wp_posts', []);
            return true;
        });
        
        $this->assertTrue($performance_data['success']);
        $this->assertArrayHasKey('execution_time', $performance_data);
        $this->assertArrayHasKey('queries_executed', $performance_data);
        $this->assertGreaterThan(0, $performance_data['execution_time']);
        $this->assertEquals(2, $performance_data['queries_executed']);
    }
    
    /**
     * Clean up after test
     */
    protected function tearDown(): void {
        parent::tearDown();
        
        // Clean up mock databases
        if ($this->mockExternalDB) {
            $this->mockExternalDB->disconnect();
            $this->mockExternalDB->clearAll();
        }
    }
}