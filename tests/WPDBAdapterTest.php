<?php
/**
 * Tests for Newera\Database\WPDBAdapter class
 */

namespace Newera\Tests;

use Newera\Database\WPDBAdapter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WPDBAdapter test case
 */
class WPDBAdapterTest extends TestCase {
    /**
     * WPDBAdapter instance
     */
    private $adapter;
    
    /**
     * Mock WPDB instance
     */
    private $mock_wpdb;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Create mock wpdb
        $this->mock_wpdb = $this->createMockWPDB();
        
        // Set global wpdb
        global $wpdb;
        $wpdb = $this->mock_wpdb;
        
        $this->adapter = new WPDBAdapter();
    }
    
    /**
     * Create a mock wpdb object
     */
    private function createMockWPDB() {
        $mock = new \stdClass();
        $mock->prefix = 'wp_';
        $mock->options = 'wp_options';
        $mock->dbname = 'test_db';
        $mock->dbhost = 'localhost';
        $mock->charset = 'utf8mb4';
        $mock->collate = 'utf8mb4_unicode_ci';
        $mock->insert_id = 0;
        $mock->rows_affected = 0;
        $mock->last_result = [];
        $mock->last_query = '';
        
        return $mock;
    }
    
    /**
     * Test get connection
     */
    public function testGetConnection() {
        $connection = $this->adapter->get_connection();
        $this->assertIsObject($connection);
        $this->assertEquals($this->mock_wpdb, $connection);
    }
    
    /**
     * Test get table prefix
     */
    public function testGetTablePrefix() {
        $prefix = $this->adapter->get_table_prefix();
        $this->assertEquals('wp_', $prefix);
    }
    
    /**
     * Test get charset collate
     */
    public function testGetCharsetCollate() {
        $this->mock_wpdb->get_charset_collate = function() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        };
        
        $charset = $this->adapter->get_charset_collate();
        $this->assertIsString($charset);
    }
    
    /**
     * Test prepare query without arguments
     */
    public function testPrepareWithoutArguments() {
        $query = "SELECT * FROM wp_options WHERE option_name = 'test'";
        $prepared = $this->adapter->prepare($query, []);
        $this->assertEquals($query, $prepared);
    }
    
    /**
     * Test prepare query with arguments
     */
    public function testPrepareWithArguments() {
        $this->mock_wpdb->prepare = function($query, ...$args) {
            return sprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), ...$args);
        };
        
        $query = "SELECT * FROM wp_options WHERE option_name = %s AND option_id = %d";
        $prepared = $this->adapter->prepare($query, ['test', 123]);
        $this->assertStringContainsString("'test'", $prepared);
        $this->assertStringContainsString('123', $prepared);
    }
    
    /**
     * Test query execution
     */
    public function testQuery() {
        $this->mock_wpdb->query = function($query) {
            $this->last_query = $query;
            return 1;
        };
        
        $result = $this->adapter->query("DELETE FROM wp_options WHERE option_name = 'test'");
        $this->assertEquals(1, $result);
    }
    
    /**
     * Test get results
     */
    public function testGetResults() {
        $expected_results = [
            (object) ['id' => 1, 'name' => 'Test 1'],
            (object) ['id' => 2, 'name' => 'Test 2']
        ];
        
        $this->mock_wpdb->get_results = function($query, $output) use ($expected_results) {
            return $expected_results;
        };
        
        $results = $this->adapter->get_results("SELECT * FROM wp_options");
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]->id);
    }
    
    /**
     * Test get row
     */
    public function testGetRow() {
        $expected_row = (object) ['id' => 1, 'name' => 'Test'];
        
        $this->mock_wpdb->get_row = function($query, $output) use ($expected_row) {
            return $expected_row;
        };
        
        $row = $this->adapter->get_row("SELECT * FROM wp_options WHERE id = 1");
        $this->assertIsObject($row);
        $this->assertEquals(1, $row->id);
        $this->assertEquals('Test', $row->name);
    }
    
    /**
     * Test get var
     */
    public function testGetVar() {
        $expected_value = 'test_value';
        
        $this->mock_wpdb->get_var = function($query, $x) use ($expected_value) {
            return $expected_value;
        };
        
        $value = $this->adapter->get_var("SELECT option_value FROM wp_options WHERE option_name = 'test'");
        $this->assertEquals('test_value', $value);
    }
    
    /**
     * Test get col
     */
    public function testGetCol() {
        $expected_col = ['value1', 'value2', 'value3'];
        
        $this->mock_wpdb->get_col = function($query, $x) use ($expected_col) {
            return $expected_col;
        };
        
        $col = $this->adapter->get_col("SELECT option_name FROM wp_options");
        $this->assertIsArray($col);
        $this->assertCount(3, $col);
        $this->assertEquals('value1', $col[0]);
    }
    
    /**
     * Test get col returns empty array on null
     */
    public function testGetColReturnsEmptyArrayOnNull() {
        $this->mock_wpdb->get_col = function($query, $x) {
            return null;
        };
        
        $col = $this->adapter->get_col("SELECT option_name FROM wp_options");
        $this->assertIsArray($col);
        $this->assertEmpty($col);
    }
    
    /**
     * Test insert
     */
    public function testInsert() {
        $this->mock_wpdb->insert = function($table, $data, $format) {
            $this->insert_id = 123;
            return true;
        };
        $this->mock_wpdb->insert_id = 123;
        
        $result = $this->adapter->insert('wp_options', ['option_name' => 'test', 'option_value' => 'value']);
        $this->assertEquals(123, $result);
    }
    
    /**
     * Test insert failure
     */
    public function testInsertFailure() {
        $this->mock_wpdb->insert = function($table, $data, $format) {
            return false;
        };
        
        $result = $this->adapter->insert('wp_options', ['option_name' => 'test']);
        $this->assertFalse($result);
    }
    
    /**
     * Test update
     */
    public function testUpdate() {
        $this->mock_wpdb->update = function($table, $data, $where, $format, $where_format) {
            return 1;
        };
        
        $result = $this->adapter->update(
            'wp_options',
            ['option_value' => 'new_value'],
            ['option_name' => 'test']
        );
        $this->assertEquals(1, $result);
    }
    
    /**
     * Test update failure
     */
    public function testUpdateFailure() {
        $this->mock_wpdb->update = function($table, $data, $where, $format, $where_format) {
            return false;
        };
        
        $result = $this->adapter->update(
            'wp_options',
            ['option_value' => 'new_value'],
            ['option_name' => 'test']
        );
        $this->assertFalse($result);
    }
    
    /**
     * Test delete
     */
    public function testDelete() {
        $this->mock_wpdb->delete = function($table, $where, $where_format) {
            return 1;
        };
        
        $result = $this->adapter->delete('wp_options', ['option_name' => 'test']);
        $this->assertEquals(1, $result);
    }
    
    /**
     * Test delete failure
     */
    public function testDeleteFailure() {
        $this->mock_wpdb->delete = function($table, $where, $where_format) {
            return false;
        };
        
        $result = $this->adapter->delete('wp_options', ['option_name' => 'test']);
        $this->assertFalse($result);
    }
    
    /**
     * Test get insert id
     */
    public function testGetInsertId() {
        $this->mock_wpdb->insert_id = 456;
        $this->assertEquals(456, $this->adapter->get_insert_id());
    }
    
    /**
     * Test get rows affected
     */
    public function testGetRowsAffected() {
        $this->mock_wpdb->rows_affected = 5;
        $this->assertEquals(5, $this->adapter->get_rows_affected());
    }
    
    /**
     * Test begin transaction
     */
    public function testBeginTransaction() {
        $this->mock_wpdb->query = function($query) {
            return $query === 'START TRANSACTION' ? 1 : 0;
        };
        
        $result = $this->adapter->begin_transaction();
        $this->assertTrue($result);
    }
    
    /**
     * Test commit transaction
     */
    public function testCommit() {
        $this->mock_wpdb->query = function($query) {
            return $query === 'COMMIT' ? 1 : 0;
        };
        
        $result = $this->adapter->commit();
        $this->assertTrue($result);
    }
    
    /**
     * Test rollback transaction
     */
    public function testRollback() {
        $this->mock_wpdb->query = function($query) {
            return $query === 'ROLLBACK' ? 1 : 0;
        };
        
        $result = $this->adapter->rollback();
        $this->assertTrue($result);
    }
    
    /**
     * Test connection test success
     */
    public function testConnectionTestSuccess() {
        $this->mock_wpdb->query = function($query) {
            return $query === 'SELECT 1' ? 1 : 0;
        };
        
        $result = $this->adapter->test_connection();
        $this->assertTrue($result);
    }
    
    /**
     * Test connection test failure
     */
    public function testConnectionTestFailure() {
        $this->mock_wpdb->query = function($query) {
            throw new \Exception('Connection failed');
        };
        
        $result = $this->adapter->test_connection();
        $this->assertFalse($result);
    }
    
    /**
     * Test get connection status
     */
    public function testGetConnectionStatus() {
        $this->mock_wpdb->query = function($query) {
            return 1;
        };
        
        $status = $this->adapter->get_connection_status();
        $this->assertIsArray($status);
        $this->assertArrayHasKey('connected', $status);
        $this->assertArrayHasKey('database', $status);
        $this->assertArrayHasKey('host', $status);
        $this->assertArrayHasKey('prefix', $status);
        $this->assertArrayHasKey('charset', $status);
        $this->assertArrayHasKey('collate', $status);
        $this->assertTrue($status['connected']);
        $this->assertEquals('test_db', $status['database']);
        $this->assertEquals('localhost', $status['host']);
    }
    
    /**
     * Test query with prepared statement
     */
    public function testQueryWithPreparedStatement() {
        $this->mock_wpdb->prepare = function($query, ...$args) {
            return sprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), ...$args);
        };
        
        $this->mock_wpdb->query = function($query) {
            $this->last_query = $query;
            return 1;
        };
        
        $result = $this->adapter->query(
            "SELECT * FROM wp_options WHERE option_name = %s",
            ['test_option']
        );
        $this->assertEquals(1, $result);
    }
}
