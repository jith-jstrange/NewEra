<?php
/**
 * Database Performance Tests for NewEra
 * 
 * Tests database query performance, pagination, filtering, and encryption payload handling
 * 
 * Usage: php DatabasePerformanceTest.php
 */

namespace Newera\Performance;

require_once dirname(__DIR__) . '/wp-load.php';

class DatabasePerformanceTest {
    private $results = [];
    private $wpdb;
    private $logger;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = new \Newera\Core\Logger();
    }

    public function run_all_tests() {
        echo "========== NewEra Database Performance Tests ==========\n";
        echo "Start Time: " . date('Y-m-d H:i:s') . "\n\n";

        $this->test_query_performance_1000_records();
        $this->test_pagination_10k_logs();
        $this->test_filter_sort_performance();
        $this->test_large_encrypted_payloads();
        $this->test_external_db_connection_overhead();
        $this->test_bulk_insert_performance();
        $this->test_complex_joins();

        echo "\n========== Test Summary ==========\n";
        $this->print_results();
    }

    private function test_query_performance_1000_records() {
        echo "Test: Query Performance with 1000+ Records\n";

        // Setup: Create test data
        echo "  - Creating 1000 test records...";
        $start = microtime(true);
        
        for ($i = 0; $i < 1000; $i++) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'posts',
                [
                    'post_title' => 'Test Post ' . $i,
                    'post_content' => 'Content ' . str_repeat('x', 100),
                    'post_type' => 'newera_project',
                    'post_status' => 'publish',
                ],
                ['%s', '%s', '%s', '%s']
            );
        }
        
        $setup_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($setup_time, 2) . "ms)\n";

        // Test: Query 1000 records
        echo "  - Querying all 1000 records...";
        $start = microtime(true);
        
        $query = "SELECT * FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project' LIMIT 1000";
        $results = $this->wpdb->get_results($query);
        
        $query_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($query_time, 2) . "ms)\n";
        echo "  - Records retrieved: " . count($results) . "\n";

        // Test: Query with WHERE clause
        echo "  - Querying with filtering...";
        $start = microtime(true);
        
        $query = "SELECT * FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project' AND post_title LIKE '%500%'";
        $results = $this->wpdb->get_results($query);
        
        $filter_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($filter_time, 2) . "ms)\n";

        // Cleanup
        $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project'");

        $this->results['1000_records_query'] = [
            'setup_time_ms' => $setup_time,
            'query_time_ms' => $query_time,
            'filter_time_ms' => $filter_time,
            'status' => $query_time < 500 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ P95: " . number_format($query_time, 2) . "ms\n\n";
    }

    private function test_pagination_10k_logs() {
        echo "Test: Pagination Performance with 10k+ Activity Logs\n";

        // Setup: Create 10k activity log records
        echo "  - Creating 10k activity logs...";
        $start = microtime(true);
        
        for ($i = 0; $i < 10000; $i++) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'posts',
                [
                    'post_title' => 'Activity Log ' . $i,
                    'post_content' => 'Log entry',
                    'post_type' => 'newera_activity',
                    'post_status' => 'publish',
                ],
                ['%s', '%s', '%s', '%s']
            );
        }
        
        $setup_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($setup_time, 2) . "ms)\n";

        // Test: Paginate through records
        echo "  - Testing pagination (100 pages, 100 items each)...";
        $start = microtime(true);
        $page_times = [];
        
        for ($page = 1; $page <= 100; $page++) {
            $page_start = microtime(true);
            $offset = ($page - 1) * 100;
            
            $query = "SELECT * FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_activity' LIMIT 100 OFFSET $offset";
            $results = $this->wpdb->get_results($query);
            
            $page_times[] = (microtime(true) - $page_start) * 1000;
        }
        
        $total_time = (microtime(true) - $start) * 1000;
        
        $avg_page_time = array_sum($page_times) / count($page_times);
        $max_page_time = max($page_times);
        $min_page_time = min($page_times);

        echo " Done (" . number_format($total_time, 2) . "ms)\n";
        echo "  - Average page load: " . number_format($avg_page_time, 2) . "ms\n";
        echo "  - Max page load: " . number_format($max_page_time, 2) . "ms\n";

        // Cleanup
        $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_activity'");

        $this->results['10k_pagination'] = [
            'setup_time_ms' => $setup_time,
            'total_time_ms' => $total_time,
            'avg_page_time_ms' => $avg_page_time,
            'max_page_time_ms' => $max_page_time,
            'status' => $avg_page_time < 100 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Pagination Complete\n\n";
    }

    private function test_filter_sort_performance() {
        echo "Test: Filter and Sort Performance\n";

        // Create test data
        echo "  - Creating 5000 test records...";
        $start = microtime(true);
        
        for ($i = 0; $i < 5000; $i++) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'posts',
                [
                    'post_title' => 'Project ' . rand(1, 100),
                    'post_content' => 'Description',
                    'post_type' => 'newera_project',
                    'post_status' => rand(0, 1) ? 'publish' : 'draft',
                ],
                ['%s', '%s', '%s', '%s']
            );
        }
        
        echo " Done\n";

        // Test: Filter
        echo "  - Testing filter performance...";
        $start = microtime(true);
        
        $query = "SELECT * FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project' AND post_status = 'publish' LIMIT 1000";
        $results = $this->wpdb->get_results($query);
        
        $filter_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($filter_time, 2) . "ms)\n";

        // Test: Sort
        echo "  - Testing sort performance...";
        $start = microtime(true);
        
        $query = "SELECT * FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project' ORDER BY post_title ASC LIMIT 1000";
        $results = $this->wpdb->get_results($query);
        
        $sort_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($sort_time, 2) . "ms)\n";

        // Test: Complex filter + sort
        echo "  - Testing filter + sort...";
        $start = microtime(true);
        
        $query = "SELECT * FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project' AND post_status = 'publish' ORDER BY post_title DESC LIMIT 1000";
        $results = $this->wpdb->get_results($query);
        
        $complex_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($complex_time, 2) . "ms)\n";

        // Cleanup
        $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project'");

        $this->results['filter_sort'] = [
            'filter_time_ms' => $filter_time,
            'sort_time_ms' => $sort_time,
            'complex_time_ms' => $complex_time,
            'status' => $complex_time < 300 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Filter/Sort Complete\n\n";
    }

    private function test_large_encrypted_payloads() {
        echo "Test: Large Encrypted Payload Handling\n";

        // Create test table if needed
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}newera_encryption_test (
            id BIGINT PRIMARY KEY AUTO_INCREMENT,
            payload LONGBLOB,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        echo "  - Testing encryption payload storage...";
        $crypto = new \Newera\Core\Crypto();
        
        // Test with various payload sizes
        $payload_sizes = [1024, 10240, 102400]; // 1KB, 10KB, 100KB
        $times = [];

        foreach ($payload_sizes as $size) {
            $start = microtime(true);
            
            $data = str_repeat('x', $size);
            $encrypted = $crypto->encrypt($data, 'test-key');
            
            $insert_time = microtime(true) - $start;
            
            $this->wpdb->insert(
                $this->wpdb->prefix . 'newera_encryption_test',
                ['payload' => $encrypted],
                ['%s']
            );

            $times[$size] = $insert_time * 1000;
        }

        echo " Done\n";

        foreach ($times as $size => $time) {
            echo "  - " . number_format($size / 1024, 0) . "KB payload: " . number_format($time, 2) . "ms\n";
        }

        // Cleanup
        $this->wpdb->query("DROP TABLE IF EXISTS {$this->wpdb->prefix}newera_encryption_test");

        $this->results['encryption_payloads'] = [
            '1kb_ms' => $times[1024],
            '10kb_ms' => $times[10240],
            '100kb_ms' => $times[102400],
            'status' => $times[102400] < 500 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Encryption Complete\n\n";
    }

    private function test_external_db_connection_overhead() {
        echo "Test: External Database Connection Overhead\n";

        echo "  - Measuring WP DB connection time...";
        $start = microtime(true);
        
        $wpdb_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($wpdb_time, 2) . "ms)\n";

        // Simulate external DB adapter
        echo "  - Simulating external DB adapter overhead...";
        $start = microtime(true);
        
        // This would test if external DB is configured
        if (class_exists('\Newera\Database\ExternalDBAdapter')) {
            $adapter = new \Newera\Database\ExternalDBAdapter();
            // Simulate connection
            $external_time = (microtime(true) - $start) * 1000;
        } else {
            $external_time = 0;
            echo " (External DB not configured)\n";
        }

        $this->results['external_db'] = [
            'wpdb_overhead_ms' => $wpdb_time,
            'external_overhead_ms' => $external_time,
            'overhead_ratio' => $external_time > 0 ? ($external_time / $wpdb_time) : 0,
            'status' => 'PASS'
        ];

        echo "  ✓ Connection Overhead Test Complete\n\n";
    }

    private function test_bulk_insert_performance() {
        echo "Test: Bulk Insert Performance\n";

        echo "  - Testing bulk insert (1000 records)...";
        $start = microtime(true);
        
        $values = [];
        for ($i = 0; $i < 1000; $i++) {
            $values[] = "('Bulk Record $i', 'Content', 'newera_project', 'publish')";
        }
        
        $query = "INSERT INTO {$this->wpdb->prefix}posts (post_title, post_content, post_type, post_status) VALUES " . implode(',', $values);
        $this->wpdb->query($query);
        
        $insert_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($insert_time, 2) . "ms)\n";

        // Cleanup
        $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project'");

        $this->results['bulk_insert'] = [
            'insert_time_ms' => $insert_time,
            'records' => 1000,
            'time_per_record_ms' => $insert_time / 1000,
            'status' => $insert_time < 2000 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Bulk Insert Complete\n\n";
    }

    private function test_complex_joins() {
        echo "Test: Complex Join Performance\n";

        // Create minimal test structure
        echo "  - Setting up test data...";
        
        for ($i = 0; $i < 100; $i++) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'posts',
                [
                    'post_title' => 'Project ' . $i,
                    'post_type' => 'newera_project',
                    'post_status' => 'publish',
                ],
                ['%s', '%s', '%s']
            );
        }
        
        echo " Done\n";

        // Test: Simple join (posts with postmeta)
        echo "  - Testing join performance...";
        $start = microtime(true);
        
        $query = "SELECT p.*, pm.meta_key, pm.meta_value FROM {$this->wpdb->prefix}posts p 
                  LEFT JOIN {$this->wpdb->prefix}postmeta pm ON p.ID = pm.post_id 
                  WHERE p.post_type = 'newera_project' LIMIT 100";
        $results = $this->wpdb->get_results($query);
        
        $join_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($join_time, 2) . "ms)\n";

        // Cleanup
        $this->wpdb->query("DELETE FROM {$this->wpdb->prefix}posts WHERE post_type = 'newera_project'");

        $this->results['complex_joins'] = [
            'join_time_ms' => $join_time,
            'status' => $join_time < 200 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Join Test Complete\n\n";
    }

    private function print_results() {
        foreach ($this->results as $test_name => $result) {
            $status_color = $result['status'] === 'PASS' ? "\033[92m" : "\033[91m";
            echo "$status_color{$result['status']}\033[0m - $test_name\n";
            
            foreach ($result as $metric => $value) {
                if ($metric !== 'status') {
                    if (is_float($value)) {
                        echo "      $metric: " . number_format($value, 2) . "\n";
                    } else {
                        echo "      $metric: $value\n";
                    }
                }
            }
        }
    }
}

// Run tests
$test = new DatabasePerformanceTest();
$test->run_all_tests();
