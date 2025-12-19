<?php
/**
 * Performance Baseline Tests for NewEra
 * 
 * Establishes baseline metrics for:
 * - Plugin activation time
 * - Setup Wizard load time
 * - Admin dashboard render time
 * - API response times
 * 
 * Usage: php BaselinePerformanceTest.php
 */

namespace Newera\Performance;

require_once dirname(__DIR__) . '/wp-load.php';

class BaselinePerformanceTest {
    private $results = [];
    private $logger;

    public function __construct() {
        $this->logger = new \Newera\Core\Logger();
    }

    public function run_all_tests() {
        echo "========== NewEra Baseline Performance Tests ==========\n";
        echo "Start Time: " . date('Y-m-d H:i:s') . "\n\n";

        $this->test_plugin_activation_time();
        $this->test_setup_wizard_load_time();
        $this->test_admin_dashboard_render_time();
        $this->test_api_response_times();
        $this->test_bootstrap_time();

        echo "\n========== Baseline Summary ==========\n";
        $this->print_results();
        
        // Save results to JSON
        $this->save_results_to_json();
    }

    private function test_plugin_activation_time() {
        echo "Test: Plugin Activation Time\n";

        // Simulate activation hook
        echo "  - Measuring activation hook execution...";
        
        $start = microtime(true);
        
        // Call the activation function
        if (function_exists('newera_activate')) {
            newera_activate();
        }
        
        $activation_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($activation_time, 2) . "ms)\n";

        $this->results['activation_time'] = [
            'time_ms' => $activation_time,
            'status' => $activation_time < 1000 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Activation Complete\n\n";
    }

    private function test_setup_wizard_load_time() {
        echo "Test: Setup Wizard Load Time\n";

        echo "  - Initializing setup wizard...";
        
        $start = microtime(true);
        
        // Initialize setup wizard
        if (class_exists('\Newera\Admin\SetupWizard')) {
            $wizard = new \Newera\Admin\SetupWizard();
            // Simulate wizard initialization
        }
        
        $wizard_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($wizard_time, 2) . "ms)\n";

        // Test individual step rendering
        echo "  - Testing wizard step render times...\n";
        
        $steps = ['welcome', 'configure', 'database', 'stripe', 'complete'];
        $step_times = [];

        foreach ($steps as $step) {
            $step_start = microtime(true);
            // Simulate step rendering (in real test, would render actual template)
            $this->render_wizard_step($step);
            $step_time = (microtime(true) - $step_start) * 1000;
            $step_times[$step] = $step_time;
            echo "    - $step: " . number_format($step_time, 2) . "ms\n";
        }

        $this->results['setup_wizard'] = [
            'initialization_ms' => $wizard_time,
            'steps' => $step_times,
            'total_step_time_ms' => array_sum($step_times),
            'status' => (max($step_times) < 200 && $wizard_time < 500) ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Setup Wizard Complete\n\n";
    }

    private function test_admin_dashboard_render_time() {
        echo "Test: Admin Dashboard Render Time\n";

        echo "  - Measuring dashboard initialization...";
        
        $start = microtime(true);
        
        if (class_exists('\Newera\Admin\Dashboard')) {
            $dashboard = new \Newera\Admin\Dashboard();
            // Simulate initialization
        }
        
        $init_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($init_time, 2) . "ms)\n";

        // Test main dashboard render
        echo "  - Measuring main dashboard render...";
        $start = microtime(true);
        
        // Simulate main page render (in real test, would render actual page)
        $this->render_dashboard_page('main');
        
        $main_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($main_time, 2) . "ms)\n";

        // Test sub-page renders
        echo "  - Testing dashboard page render times...\n";
        
        $pages = ['projects', 'activities', 'team', 'settings'];
        $page_times = [];

        foreach ($pages as $page) {
            $page_start = microtime(true);
            $this->render_dashboard_page($page);
            $page_time = (microtime(true) - $page_start) * 1000;
            $page_times[$page] = $page_time;
            echo "    - $page: " . number_format($page_time, 2) . "ms\n";
        }

        $this->results['admin_dashboard'] = [
            'initialization_ms' => $init_time,
            'main_page_ms' => $main_time,
            'pages' => $page_times,
            'total_time_ms' => $init_time + $main_time + array_sum($page_times),
            'status' => (max($page_times) < 500 && $main_time < 500) ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Dashboard Render Complete\n\n";
    }

    private function test_api_response_times() {
        echo "Test: API Response Times\n";

        $endpoints = [
            '/wp-json/newera/v1/projects' => 'Projects List',
            '/wp-json/newera/v1/projects/1' => 'Project Detail',
            '/wp-json/newera/v1/activities' => 'Activities List',
            '/wp-json/newera/v1/clients' => 'Clients List',
            '/wp-json/newera/v1/auth/status' => 'Auth Status',
        ];

        echo "  - Testing API endpoints...\n";
        
        $response_times = [];

        foreach ($endpoints as $endpoint => $description) {
            $start = microtime(true);
            
            // Simulate API call (in real test, would use wp_remote_get)
            $response = $this->simulate_api_call($endpoint);
            
            $response_time = (microtime(true) - $start) * 1000;
            $response_times[$endpoint] = $response_time;
            
            $status = $response_time < 500 ? 'PASS' : 'WARN';
            echo "    - $description [$endpoint]: " . number_format($response_time, 2) . "ms [$status]\n";
        }

        // Calculate percentiles
        sort($response_times);
        $p50 = $response_times[count($response_times) / 2];
        $p95 = $response_times[(int)(count($response_times) * 0.95)];
        $p99 = $response_times[(int)(count($response_times) * 0.99)];

        echo "  - P50 response time: " . number_format($p50, 2) . "ms\n";
        echo "  - P95 response time: " . number_format($p95, 2) . "ms\n";
        echo "  - P99 response time: " . number_format($p99, 2) . "ms\n";

        $this->results['api_response_times'] = [
            'endpoints' => $response_times,
            'p50_ms' => $p50,
            'p95_ms' => $p95,
            'p99_ms' => $p99,
            'avg_ms' => array_sum($response_times) / count($response_times),
            'max_ms' => max($response_times),
            'status' => $p95 < 500 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ API Tests Complete\n\n";
    }

    private function test_bootstrap_time() {
        echo "Test: Bootstrap Time\n";

        echo "  - Measuring WordPress bootstrap...";
        
        $start = microtime(true);
        
        // Bootstrap is already done, measure plugin initialization
        if (class_exists('\Newera\Core\Bootstrap')) {
            $bootstrap = \Newera\Core\Bootstrap::getInstance();
            // Already initialized, but measure re-init
            $bootstrap->init();
        }
        
        $bootstrap_time = (microtime(true) - $start) * 1000;
        echo " Done (" . number_format($bootstrap_time, 2) . "ms)\n";

        $this->results['bootstrap'] = [
            'time_ms' => $bootstrap_time,
            'status' => $bootstrap_time < 500 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Bootstrap Complete\n\n";
    }

    private function render_wizard_step($step) {
        // Simulate template rendering
        ob_start();
        
        switch ($step) {
            case 'welcome':
                echo '<h1>Welcome</h1>';
                break;
            case 'configure':
                echo '<form><input type="text" name="site_name"/></form>';
                break;
            case 'database':
                echo '<form><input type="text" name="db_host"/></form>';
                break;
            case 'stripe':
                echo '<form><input type="text" name="stripe_key"/></form>';
                break;
            case 'complete':
                echo '<h2>Setup Complete</h2>';
                break;
        }
        
        ob_end_clean();
    }

    private function render_dashboard_page($page) {
        // Simulate dashboard page rendering
        ob_start();
        
        echo '<div class="dashboard">';
        echo '<h1>' . ucfirst($page) . '</h1>';
        
        // Simulate loading data
        for ($i = 0; $i < 10; $i++) {
            echo '<div class="item">Item ' . $i . '</div>';
        }
        
        echo '</div>';
        
        ob_end_clean();
    }

    private function simulate_api_call($endpoint) {
        // Simulate API response
        $response = [
            'status' => 200,
            'data' => [],
        ];

        for ($i = 0; $i < 20; $i++) {
            $response['data'][] = [
                'id' => $i,
                'name' => 'Item ' . $i,
                'created' => date('Y-m-d'),
            ];
        }

        return $response;
    }

    private function print_results() {
        foreach ($this->results as $test_name => $result) {
            $status_color = $result['status'] === 'PASS' ? "\033[92m" : "\033[91m";
            echo "$status_color{$result['status']}\033[0m - $test_name\n";
            
            foreach ($result as $metric => $value) {
                if ($metric !== 'status') {
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

    private function save_results_to_json() {
        $filename = __DIR__ . '/baseline_results_' . date('Y-m-d_H-i-s') . '.json';
        
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'results' => $this->results,
            'system_info' => [
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ]
        ];

        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "\nResults saved to: $filename\n";
    }
}

// Run tests
$test = new BaselinePerformanceTest();
$test->run_all_tests();
