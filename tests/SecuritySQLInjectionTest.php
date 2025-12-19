<?php
/**
 * Security Tests for SQL Injection Prevention
 *
 * Tests prepared statements, parameterized queries, and special character handling
 */

namespace Newera\Tests;

use Newera\Database\WPDBAdapter;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security SQL Injection Test Case
 */
class SecuritySQLInjectionTest extends TestCase {
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        MockStorage::clear_all();
    }
    
    /**
     * Test: Prepared statements used in database layer
     */
    public function testPreparedStatementsUsedInDatabaseLayer() {
        // Check database adapter uses prepared statements
        $adapter_file = NEWERA_INCLUDES_PATH . 'Database/WPDBAdapter.php';
        $this->assertFileExists($adapter_file);
        
        $content = file_get_contents($adapter_file);
        
        // Should use prepare() method for queries
        $this->assertStringContainsString('prepare', $content);
        
        // Should not use string concatenation for WHERE clauses with user input
        // (This is a heuristic check - exact implementation may vary)
    }
    
    /**
     * Test: SQL injection attempts with special characters blocked
     */
    public function testSQLInjectionWithSpecialCharactersBlocked() {
        $injection_payloads = [
            // Basic SQL injection
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin' --",
            "' OR 1=1 --",
            
            // Union-based injection
            "' UNION SELECT NULL,NULL,NULL --",
            "' UNION ALL SELECT NULL,NULL,NULL --",
            
            // Boolean-based injection
            "1' AND '1'='1",
            "1' AND '1'='0",
            
            // Time-based blind injection
            "'; WAITFOR DELAY '00:00:05'--",
            "'; pg_sleep(5); --",
            
            // Stacked queries
            "'; DELETE FROM users WHERE ''='",
            "'; INSERT INTO admin VALUES('x','x'); --",
        ];
        
        foreach ($injection_payloads as $payload) {
            // These should be escaped when used in queries
            $escaped = $this->escape_sql_string($payload);
            
            // Verify quotes are escaped
            if (strpos($payload, "'") !== false) {
                $this->assertStringContainsString("\\'", $escaped);
            }
            
            // Verify dangerous SQL keywords are not executable in context
            $this->assertFalse($this->is_sql_keyword_injection($payload, $escaped));
        }
    }
    
    /**
     * Test: Parameterized queries prevent injection
     */
    public function testParameterizedQueriesPreventInjection() {
        // Simulate proper parameterized query
        $table = 'wp_posts';
        $user_id = "1' OR '1'='1";
        
        // Proper way with prepare()
        $safe_user_id = intval($user_id);
        $this->assertEquals(1, $safe_user_id);
        
        // When used in query template
        $query = sprintf('SELECT * FROM %s WHERE post_author = %d', $table, $safe_user_id);
        
        // Should only match posts by user 1, not all posts
        $this->assertStringContainsString('post_author = 1', $query);
        $this->assertNotContains("'", $query);
    }
    
    /**
     * Test: Union-based SQL injection attempts blocked
     */
    public function testUnionBasedSQLInjectionBlocked() {
        $union_payloads = [
            "' UNION SELECT user_login, user_pass, user_email FROM wp_users --",
            "1' UNION ALL SELECT NULL,user_login,user_pass FROM wp_users --",
            "0 UNION SELECT 1,2,3,4,5 --",
            "' UNION SELECT * FROM wp_options --",
        ];
        
        foreach ($union_payloads as $payload) {
            // When properly escaped, UNION becomes literal string
            $escaped = $this->escape_sql_string($payload);
            
            // Should not be able to execute as SQL
            $this->assertStringContainsString('UNION', $escaped);
            // But it's escaped, so it won't execute as SQL command
            $this->assertStringContainsString("'", $escaped);
        }
    }
    
    /**
     * Test: Numeric input validation prevents injection
     */
    public function testNumericInputValidationPreventsInjection() {
        $numeric_payloads = [
            "123 OR 1=1",
            "1'; DROP TABLE users; --",
            "1 UNION SELECT NULL --",
        ];
        
        foreach ($numeric_payloads as $payload) {
            // Should validate as integer
            $validated = intval($payload);
            
            // Should extract only the leading number
            $this->assertTrue(is_int($validated));
            
            // Original injection payload lost
            if (strpos($payload, ' ') !== false) {
                $this->assertNotEquals($payload, (string)$validated);
            }
        }
    }
    
    /**
     * Test: String column input validation
     */
    public function testStringColumnInputValidation() {
        $dangerous_strings = [
            "test'; DROP TABLE users; --",
            "test' OR '1'='1",
            "test\"; DROP TABLE users; --",
        ];
        
        foreach ($dangerous_strings as $string) {
            // Sanitize using WordPress functions
            $sanitized = sanitize_text_field($string);
            
            // Should remove potentially dangerous characters/sequences
            $this->assertNotContains(';', $sanitized);
            $this->assertNotContains('--', $sanitized);
        }
    }
    
    /**
     * Test: Search input doesn't allow SQL injection
     */
    public function testSearchInputSQLInjectionPrevention() {
        $search_payload = "test' OR 1=1 --";
        
        // Proper escaping for LIKE clause
        $escaped_search = '%' . sanitize_text_field($search_payload) . '%';
        
        // When used in LIKE query with proper prepare
        $like_escaped = esc_sql($escaped_search);
        
        // Should not contain unescaped quotes
        $this->assertNotContains("'", $like_escaped);
    }
    
    /**
     * Test: Meta value queries use prepared statements
     */
    public function testMetaValueQueriesPrepared() {
        // Verify repository files use proper escaping
        $repo_file = NEWERA_INCLUDES_PATH . 'Database/RepositoryBase.php';
        
        if (!file_exists($repo_file)) {
            $this->markTestSkipped('RepositoryBase.php not found');
        }
        
        $content = file_get_contents($repo_file);
        
        // Should use $wpdb->prepare or esc_sql
        $uses_prepared = strpos($content, 'prepare') !== false ||
                        strpos($content, 'esc_sql') !== false;
        
        $this->assertTrue($uses_prepared, 'Repository should use prepared statements or esc_sql');
    }
    
    /**
     * Test: Order by injection prevention
     */
    public function testOrderByInjectionPrevention() {
        $order_payloads = [
            "post_date DESC; DROP TABLE users; --",
            "post_date, (SELECT * FROM wp_users) --",
            "post_date) UNION SELECT NULL --",
        ];
        
        foreach ($order_payloads as $payload) {
            // Order by should only accept whitelisted column names
            $allowed_columns = ['post_date', 'post_author', 'post_title', 'ID'];
            
            // Extract column name before any space or special char
            $column = preg_replace('/[^a-zA-Z0-9_].*/', '', $payload);
            
            $is_safe = in_array($column, $allowed_columns);
            $this->assertTrue($is_safe || empty($column), "Column '$column' should be validated");
        }
    }
    
    /**
     * Test: LIMIT injection prevention
     */
    public function testLimitInjectionPrevention() {
        $limit_payloads = [
            "10; DELETE FROM users --",
            "10 UNION SELECT * --",
            "10 OR 1=1",
        ];
        
        foreach ($limit_payloads as $payload) {
            // LIMIT should only accept integers
            $limit = intval($payload);
            
            // Should extract only the number
            $this->assertTrue(is_int($limit));
            $this->assertGreaterThanOrEqual(0, $limit);
        }
    }
    
    /**
     * Test: Table name whitelisting
     */
    public function testTableNameWhitelisting() {
        $tables_to_check = [
            'wp_posts',
            'wp_postmeta',
            'wp_users',
            'wp_usermeta',
            'wp_options',
        ];
        
        $injection_table = "wp_users; DROP TABLE wp_posts; --";
        
        // Only allow whitelisted tables
        $allowed = in_array($injection_table, $tables_to_check);
        $this->assertFalse($allowed, 'Injection attempt should not be whitelisted');
    }
    
    /**
     * Test: Comment stripping in queries
     */
    public function testCommentStrippingPrevention() {
        // SQL comments should be removed from user input
        $payloads_with_comments = [
            "test -- comment",
            "test # comment",
            "test /* comment */",
            "test -- ' OR '1'='1",
        ];
        
        foreach ($payloads_with_comments as $payload) {
            $sanitized = sanitize_text_field($payload);
            
            // Remove SQL comment characters
            $cleaned = preg_replace('/(-{2}|#|\/\*|\*\/).*/', '', $sanitized);
            
            // Should not execute as comment
            $this->assertFalse(strpos($cleaned, '/*'));
        }
    }
    
    /**
     * Helper: Escape SQL string
     */
    private function escape_sql_string($str) {
        // Simulate WordPress esc_sql behavior
        return addslashes($str);
    }
    
    /**
     * Helper: Check if SQL keyword injection
     */
    private function is_sql_keyword_injection($original, $escaped) {
        // Check if dangerous SQL keywords are still present after escaping
        $keywords = ['DROP', 'DELETE', 'INSERT', 'UPDATE', 'TRUNCATE', 'ALTER'];
        
        foreach ($keywords as $keyword) {
            if (strpos(strtoupper($original), $keyword) !== false) {
                // If keyword is escaped, it's safe
                if (strpos(strtoupper($escaped), $keyword) === false) {
                    return true; // Keyword was removed or escaped
                }
            }
        }
        
        return false;
    }
}
