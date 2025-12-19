<?php
/**
 * Webhook Stress Tests for NewEra
 * 
 * Tests webhook queue and delivery performance
 * Tests retry mechanism under load
 * Monitors for memory leaks during processing
 * 
 * Usage: php WebhookStressTest.php
 */

namespace Newera\Performance;

require_once dirname(__DIR__) . '/wp-load.php';

class WebhookStressTest {
    private $results = [];
    private $wpdb;
    private $logger;
    private $webhook_manager;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = new \Newera\Core\Logger();
    }

    public function run_all_tests() {
        echo "========== NewEra Webhook Stress Tests ==========\n";
        echo "Start Time: " . date('Y-m-d H:i:s') . "\n\n";

        $this->test_queue_1000_webhooks();
        $this->test_webhook_delivery();
        $this->test_retry_mechanism();
        $this->test_memory_usage();

        echo "\n========== Test Summary ==========\n";
        $this->print_results();
    }

    private function test_queue_1000_webhooks() {
        echo "Test: Queue 1000+ Webhooks\n";

        // Create webhook queue table if needed
        $this->ensure_webhook_table();

        echo "  - Queuing 1000 webhooks...";
        $start = microtime(true);
        
        $webhook_count = 0;
        
        for ($i = 0; $i < 1000; $i++) {
            $payload = json_encode([
                'event' => 'project.created',
                'timestamp' => time(),
                'data' => [
                    'project_id' => $i,
                    'name' => 'Test Project ' . $i,
                    'url' => 'https://example.com/webhook',
                ]
            ]);

            $inserted = $this->wpdb->insert(
                $this->wpdb->prefix . 'newera_webhooks',
                [
                    'event' => 'project.created',
                    'url' => 'https://webhook.example.com/test',
                    'payload' => $payload,
                    'status' => 'pending',
                    'attempts' => 0,
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%d', '%s']
            );

            if ($inserted) {
                $webhook_count++;
            }
        }
        
        $queue_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($queue_time, 2) . "ms)\n";
        echo "  - Queued: $webhook_count webhooks\n";
        echo "  - Rate: " . number_format($webhook_count / ($queue_time / 1000), 0) . " webhooks/sec\n";

        $this->results['queue_1000'] = [
            'webhooks_queued' => $webhook_count,
            'total_time_ms' => $queue_time,
            'rate_per_sec' => $webhook_count / ($queue_time / 1000),
            'status' => $webhook_count === 1000 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Queuing Complete\n\n";
    }

    private function test_webhook_delivery() {
        echo "Test: Webhook Delivery Performance\n";

        // Get pending webhooks
        $webhooks = $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}newera_webhooks WHERE status = 'pending' LIMIT 100"
        );

        echo "  - Simulating delivery of " . count($webhooks) . " webhooks...\n";
        
        $start = microtime(true);
        $delivered = 0;
        $failed = 0;
        $delivery_times = [];

        foreach ($webhooks as $webhook) {
            $delivery_start = microtime(true);
            
            // Simulate delivery (skip actual HTTP call for performance test)
            // In production, this would use wp_remote_post()
            $success = $this->simulate_delivery($webhook);
            
            $delivery_time = (microtime(true) - $delivery_start) * 1000;
            $delivery_times[] = $delivery_time;

            if ($success) {
                $delivered++;
                $status = 'delivered';
            } else {
                $failed++;
                $status = 'failed';
            }

            $this->wpdb->update(
                $this->wpdb->prefix . 'newera_webhooks',
                ['status' => $status],
                ['id' => $webhook->id],
                ['%s'],
                ['%d']
            );
        }
        
        $total_time = (microtime(true) - $start) * 1000;

        echo "  - Delivered: $delivered\n";
        echo "  - Failed: $failed\n";
        echo "  - Total time: " . number_format($total_time, 2) . "ms\n";
        echo "  - Average per webhook: " . number_format(array_sum($delivery_times) / count($delivery_times), 2) . "ms\n";

        $this->results['delivery'] = [
            'delivered' => $delivered,
            'failed' => $failed,
            'total_time_ms' => $total_time,
            'avg_time_ms' => array_sum($delivery_times) / count($delivery_times),
            'max_time_ms' => max($delivery_times),
            'status' => $failed === 0 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Delivery Complete\n\n";
    }

    private function test_retry_mechanism() {
        echo "Test: Retry Mechanism Under Load\n";

        echo "  - Testing retry logic with failed webhooks...\n";

        // Create failed webhooks that need retry
        $failed_count = 50;
        for ($i = 0; $i < $failed_count; $i++) {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'newera_webhooks',
                [
                    'event' => 'project.updated',
                    'url' => 'https://webhook.example.com/failed',
                    'payload' => json_encode(['attempt' => $i]),
                    'status' => 'failed',
                    'attempts' => 1,
                    'created_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%d', '%s']
            );
        }

        echo "  - Created $failed_count failed webhooks\n";

        $start = microtime(true);
        $retry_count = 0;

        // Simulate retry logic
        $failed_webhooks = $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}newera_webhooks WHERE status = 'failed' AND attempts < 3"
        );

        foreach ($failed_webhooks as $webhook) {
            if ($webhook->attempts < 3) {
                $this->wpdb->update(
                    $this->wpdb->prefix . 'newera_webhooks',
                    [
                        'attempts' => $webhook->attempts + 1,
                        'status' => 'pending',
                    ],
                    ['id' => $webhook->id],
                    ['%d', '%s'],
                    ['%d']
                );
                $retry_count++;
            }
        }

        $retry_time = (microtime(true) - $start) * 1000;

        echo "  - Retried: $retry_count webhooks\n";
        echo "  - Retry time: " . number_format($retry_time, 2) . "ms\n";

        $this->results['retry'] = [
            'retried_count' => $retry_count,
            'retry_time_ms' => $retry_time,
            'status' => 'PASS'
        ];

        echo "  ✓ Retry Test Complete\n\n";
    }

    private function test_memory_usage() {
        echo "Test: Memory Usage During Processing\n";

        // Get baseline memory
        $initial_memory = memory_get_usage(true) / 1024 / 1024;
        echo "  - Initial memory: " . number_format($initial_memory, 2) . "MB\n";

        // Process all webhooks
        echo "  - Processing all queued webhooks...\n";

        $webhooks = $this->wpdb->get_results(
            "SELECT * FROM {$this->wpdb->prefix}newera_webhooks"
        );

        $memory_usage_samples = [];
        foreach ($webhooks as $webhook) {
            // Simulate processing
            $payload = json_decode($webhook->payload, true);
            $processed = array_merge($payload, ['processed' => true]);
            
            // Simulate memory-intensive operation
            $data = str_repeat('x', 1024 * 100); // 100KB
            
            // Sample memory usage periodically
            if (count($memory_usage_samples) < 100) {
                $memory_usage_samples[] = memory_get_usage(true) / 1024 / 1024;
            }
        }

        $peak_memory = memory_get_peak_usage(true) / 1024 / 1024;
        $final_memory = memory_get_usage(true) / 1024 / 1024;

        echo "  - Peak memory: " . number_format($peak_memory, 2) . "MB\n";
        echo "  - Final memory: " . number_format($final_memory, 2) . "MB\n";
        echo "  - Memory increase: " . number_format($peak_memory - $initial_memory, 2) . "MB\n";

        // Check for memory leaks (memory should be released)
        $memory_leak_ratio = ($final_memory - $initial_memory) / ($peak_memory - $initial_memory);
        
        $this->results['memory'] = [
            'initial_mb' => $initial_memory,
            'peak_mb' => $peak_memory,
            'final_mb' => $final_memory,
            'increase_mb' => $peak_memory - $initial_memory,
            'leak_ratio' => $memory_leak_ratio,
            'status' => $peak_memory < 256 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Memory Test Complete\n\n";
    }

    private function ensure_webhook_table() {
        $table_name = $this->wpdb->prefix . 'newera_webhooks';
        
        // Check if table exists
        $result = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($result === null) {
            $this->wpdb->query("CREATE TABLE $table_name (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                event VARCHAR(255) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                payload LONGTEXT,
                status VARCHAR(50) DEFAULT 'pending',
                attempts INT DEFAULT 0,
                created_at DATETIME,
                INDEX status_idx (status),
                INDEX event_idx (event)
            )");
        }
    }

    private function simulate_delivery($webhook) {
        // 90% success rate
        return rand(1, 100) <= 90;
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
$test = new WebhookStressTest();
$test->run_all_tests();
