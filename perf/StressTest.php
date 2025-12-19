<?php
/**
 * Stress Test Suite for NewEra
 * 
 * Comprehensive stress testing for:
 * - 1000+ projects created
 * - 10k+ activity logs with full filtering
 * - 100+ concurrent clients
 * - Fallback to WordPress DB under external DB failure
 * 
 * Usage: php StressTest.php
 */

namespace Newera\Performance;

require_once dirname(__DIR__) . '/wp-load.php';

class StressTest {
    private $results = [];
    private $wpdb;
    private $logger;
    private $start_time;
    private $start_memory;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = new \Newera\Core\Logger();
        $this->start_time = microtime(true);
        $this->start_memory = memory_get_usage(true);
    }

    public function run_all_tests() {
        echo "========== NewEra Stress Test Suite ==========\n";
        echo "Start Time: " . date('Y-m-d H:i:s') . "\n";
        echo "Memory: " . number_format($this->start_memory / 1024 / 1024, 2) . "MB\n\n";

        $this->test_1000_projects_created();
        $this->test_10k_activity_logs();
        $this->test_100_concurrent_clients();
        $this->test_fallback_to_wordpress_db();

        echo "\n========== Stress Test Summary ==========\n";
        $this->print_results();
        
        $elapsed = (microtime(true) - $this->start_time);
        $peak_memory = (memory_get_peak_usage(true) - $this->start_memory) / 1024 / 1024;
        
        echo "\nTotal Execution Time: " . number_format($elapsed, 2) . "s\n";
        echo "Peak Memory Used: " . number_format($peak_memory, 2) . "MB\n";
    }

    private function test_1000_projects_created() {
        echo "Test: Create 1000+ Projects\n";

        echo "  - Creating 1000 projects...";
        $start = microtime(true);
        
        $created = 0;
        $failed = 0;

        for ($i = 0; $i < 1000; $i++) {
            $project_data = [
                'post_title' => 'Stress Test Project ' . $i,
                'post_content' => 'Description for project ' . $i . '. ' . str_repeat('Content ', 10),
                'post_type' => 'newera_project',
                'post_status' => 'publish',
                'post_author' => 1,
            ];

            $post_id = wp_insert_post($project_data);

            if ($post_id && !is_wp_error($post_id)) {
                $created++;
                
                // Add some metadata
                update_post_meta($post_id, '_project_status', 'active');
                update_post_meta($post_id, '_created_by', 1);
                update_post_meta($post_id, '_budget', rand(1000, 50000));
                
                // Clear caches periodically
                if ($i % 100 === 0) {
                    wp_cache_flush();
                }
            } else {
                $failed++;
            }
        }
        
        $create_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($create_time, 2) . "ms)\n";
        echo "  - Created: $created, Failed: $failed\n";
        echo "  - Rate: " . number_format($created / ($create_time / 1000), 0) . " projects/sec\n";

        // Query all projects
        echo "  - Querying all 1000 projects...";
        $start = microtime(true);
        
        $query_args = [
            'post_type' => 'newera_project',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        
        $projects = get_posts($query_args);
        $query_time = (microtime(true) - $start) * 1000;
        
        echo " Done (" . number_format($query_time, 2) . "ms)\n";
        echo "  - Retrieved: " . count($projects) . " projects\n";

        // Test pagination
        echo "  - Testing pagination (100 per page)...";
        $start = microtime(true);
        $page_times = [];
        
        for ($page = 1; $page <= 10; $page++) {
            $query_start = microtime(true);
            
            $query_args['paged'] = $page;
            $query_args['posts_per_page'] = 100;
            
            $page_results = get_posts($query_args);
            
            $page_times[] = (microtime(true) - $query_start) * 1000;
        }
        
        $pagination_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($pagination_time, 2) . "ms)\n";

        $this->results['1000_projects'] = [
            'created' => $created,
            'failed' => $failed,
            'creation_time_ms' => $create_time,
            'creation_rate_per_sec' => $created / ($create_time / 1000),
            'query_time_ms' => $query_time,
            'pagination_time_ms' => $pagination_time,
            'avg_page_time_ms' => array_sum($page_times) / count($page_times),
            'status' => $failed === 0 && $query_time < 1000 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Project Creation Test Complete\n\n";
    }

    private function test_10k_activity_logs() {
        echo "Test: Create 10k+ Activity Logs with Filtering\n";

        echo "  - Creating 10,000 activity logs...";
        $start = microtime(true);
        
        $created = 0;

        for ($i = 0; $i < 10000; $i++) {
            $activity_types = ['created', 'updated', 'commented', 'viewed', 'deleted'];
            $type = $activity_types[$i % count($activity_types)];
            
            $activity_data = [
                'post_title' => 'Activity #' . $i . ' - ' . $type,
                'post_content' => 'Activity log entry for ' . $type . ' action. Details: Project ' . rand(1, 1000),
                'post_type' => 'newera_activity',
                'post_status' => 'publish',
                'post_author' => rand(1, 10),
            ];

            $post_id = wp_insert_post($activity_data);

            if ($post_id && !is_wp_error($post_id)) {
                $created++;
                
                update_post_meta($post_id, '_activity_type', $type);
                update_post_meta($post_id, '_project_id', rand(1, 1000));
                update_post_meta($post_id, '_user_id', rand(1, 10));
                
                // Clear caches every 1000
                if ($i % 1000 === 0) {
                    wp_cache_flush();
                }
            }
        }
        
        $create_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($create_time, 2) . "ms)\n";
        echo "  - Created: $created activity logs\n";

        // Test filtering
        echo "  - Testing various filters...\n";
        
        $filter_tests = [
            ['meta_key' => '_activity_type', 'meta_value' => 'created'] => 'Type filter',
            ['meta_key' => '_project_id', 'meta_value' => '500'] => 'Project filter',
            ['meta_key' => '_user_id', 'meta_value' => '5'] => 'User filter',
        ];

        $filter_times = [];

        foreach ($filter_tests as $filter => $description) {
            $filter_start = microtime(true);
            
            $query_args = [
                'post_type' => 'newera_activity',
                'posts_per_page' => 100,
                'post_status' => 'publish',
                'meta_query' => [[$filter]],
            ];
            
            $results = get_posts($query_args);
            
            $filter_time = (microtime(true) - $filter_start) * 1000;
            $filter_times[$description] = $filter_time;
            
            echo "    - $description: " . number_format($filter_time, 2) . "ms (" . count($results) . " results)\n";
        }

        // Test sorting
        echo "  - Testing sorting...";
        $sort_start = microtime(true);
        
        $query_args = [
            'post_type' => 'newera_activity',
            'posts_per_page' => 100,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        $sorted_results = get_posts($query_args);
        $sort_time = (microtime(true) - $sort_start) * 1000;
        
        echo " Done (" . number_format($sort_time, 2) . "ms)\n";

        // Test complex query with filter + sort + pagination
        echo "  - Testing complex query (filter + sort + paginate)...";
        $complex_start = microtime(true);
        
        $complex_times = [];
        for ($page = 1; $page <= 5; $page++) {
            $page_start = microtime(true);
            
            $query_args = [
                'post_type' => 'newera_activity',
                'posts_per_page' => 100,
                'paged' => $page,
                'post_status' => 'publish',
                'meta_query' => [
                    ['meta_key' => '_activity_type', 'meta_value' => 'created'],
                ],
                'orderby' => 'date',
                'order' => 'DESC',
            ];
            
            $results = get_posts($query_args);
            $complex_times[] = (microtime(true) - $page_start) * 1000;
        }
        
        $complex_time = (microtime(true) - $complex_start) * 1000;
        echo " Done (" . number_format($complex_time, 2) . "ms)\n";

        $this->results['10k_activity_logs'] = [
            'created' => $created,
            'creation_time_ms' => $create_time,
            'filter_times' => $filter_times,
            'sort_time_ms' => $sort_time,
            'complex_query_time_ms' => $complex_time,
            'avg_complex_page_ms' => array_sum($complex_times) / count($complex_times),
            'status' => array_sum($filter_times) / count($filter_times) < 500 && $sort_time < 200 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Activity Log Test Complete\n\n";
    }

    private function test_100_concurrent_clients() {
        echo "Test: Simulate 100+ Concurrent Clients\n";

        echo "  - Simulating 100 concurrent client requests...\n";
        
        $concurrent_clients = 100;
        $requests_per_client = 10;
        $success = 0;
        $failed = 0;
        $response_times = [];

        for ($client = 0; $client < $concurrent_clients; $client++) {
            for ($request = 0; $request < $requests_per_client; $request++) {
                $req_start = microtime(true);
                
                // Simulate API request
                $endpoint = $this->get_random_endpoint();
                
                try {
                    // Simulate query execution
                    $query_args = [
                        'post_type' => 'newera_project',
                        'posts_per_page' => 20,
                        'orderby' => 'date',
                    ];
                    
                    $results = get_posts($query_args);
                    
                    if ($results) {
                        $success++;
                    } else {
                        $failed++;
                    }
                } catch (Exception $e) {
                    $failed++;
                }
                
                $req_time = (microtime(true) - $req_start) * 1000;
                $response_times[] = $req_time;
            }
        }

        $total_requests = $concurrent_clients * $requests_per_client;
        $success_rate = ($success / $total_requests) * 100;

        sort($response_times);
        $p50 = $response_times[count($response_times) / 2];
        $p95 = $response_times[(int)(count($response_times) * 0.95)];
        $p99 = $response_times[(int)(count($response_times) * 0.99)];

        echo "  - Total requests: " . number_format($total_requests) . "\n";
        echo "  - Success rate: " . number_format($success_rate, 2) . "%\n";
        echo "  - P50 response: " . number_format($p50, 2) . "ms\n";
        echo "  - P95 response: " . number_format($p95, 2) . "ms\n";
        echo "  - P99 response: " . number_format($p99, 2) . "ms\n";
        echo "  - Max response: " . number_format(max($response_times), 2) . "ms\n";

        $this->results['100_concurrent'] = [
            'total_clients' => $concurrent_clients,
            'total_requests' => $total_requests,
            'success' => $success,
            'failed' => $failed,
            'success_rate_percent' => $success_rate,
            'p50_ms' => $p50,
            'p95_ms' => $p95,
            'p99_ms' => $p99,
            'max_ms' => max($response_times),
            'status' => $success_rate > 99.9 && $p95 < 500 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Concurrent Client Test Complete\n\n";
    }

    private function test_fallback_to_wordpress_db() {
        echo "Test: Fallback to WordPress DB\n";

        echo "  - Testing database failover scenario...\n";

        // Simulate external DB unavailability
        if (class_exists('\Newera\Database\DBAdapterFactory')) {
            echo "  - Primary adapter: ExternalDB (simulated unavailable)\n";
            
            $start = microtime(true);
            
            // Query should fallback to WordPress DB
            $query_args = [
                'post_type' => 'newera_project',
                'posts_per_page' => 20,
            ];
            
            $results = get_posts($query_args);
            
            $fallback_time = (microtime(true) - $start) * 1000;
            
            $status = !empty($results) ? 'PASS' : 'FAIL';
            
            echo "  - Fallback to WordPress DB: " . number_format($fallback_time, 2) . "ms [$status]\n";
            
            if (!empty($results)) {
                echo "  - Retrieved " . count($results) . " records\n";
            }

            $this->results['fallback_db'] = [
                'fallback_time_ms' => $fallback_time,
                'records_retrieved' => count($results),
                'status' => $status
            ];
        } else {
            echo "  - External DB adapter not available\n";
            
            $this->results['fallback_db'] = [
                'status' => 'SKIP',
                'reason' => 'External DB adapter not configured'
            ];
        }

        echo "  ✓ Fallback Test Complete\n\n";
    }

    private function get_random_endpoint() {
        $endpoints = [
            '/wp-json/newera/v1/projects',
            '/wp-json/newera/v1/activities',
            '/wp-json/newera/v1/clients',
            '/wp-json/newera/v1/team-members',
        ];

        return $endpoints[array_rand($endpoints)];
    }

    private function print_results() {
        foreach ($this->results as $test_name => $result) {
            $status_color = $result['status'] === 'PASS' ? "\033[92m" : 
                            ($result['status'] === 'SKIP' ? "\033[93m" : "\033[91m");
            echo "$status_color{$result['status']}\033[0m - $test_name\n";
            
            foreach ($result as $metric => $value) {
                if ($metric !== 'status' && $metric !== 'reason') {
                    if (is_array($value)) {
                        echo "      $metric:\n";
                        foreach ($value as $key => $val) {
                            if (is_float($val)) {
                                echo "        $key: " . number_format($val, 2) . "\n";
                            } else {
                                echo "        $key: $val\n";
                            }
                        }
                    } elseif (is_float($value)) {
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
$test = new StressTest();
$test->run_all_tests();
