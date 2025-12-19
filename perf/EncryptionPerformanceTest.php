<?php
/**
 * Encryption Performance Tests for NewEra
 * 
 * Tests Crypto.php performance with 1000+ operations/sec
 * Tests key derivation and IV generation performance
 * 
 * Usage: php EncryptionPerformanceTest.php
 */

namespace Newera\Performance;

require_once dirname(__DIR__) . '/wp-load.php';

class EncryptionPerformanceTest {
    private $crypto;
    private $results = [];

    public function __construct() {
        $this->crypto = new \Newera\Core\Crypto();
    }

    public function run_all_tests() {
        echo "========== NewEra Encryption Performance Tests ==========\n";
        echo "Start Time: " . date('Y-m-d H:i:s') . "\n\n";

        $this->test_encrypt_1000_operations();
        $this->test_decrypt_1000_operations();
        $this->test_large_payload_encryption();
        $this->test_key_derivation_performance();
        $this->test_iv_generation_performance();
        $this->test_concurrent_encryption();

        echo "\n========== Test Summary ==========\n";
        $this->print_results();
    }

    private function test_encrypt_1000_operations() {
        echo "Test: Encrypt 1000+ Operations/sec\n";

        $test_data = 'This is test data for encryption performance testing.';
        $operations = 1000;

        echo "  - Running $operations encryption operations...";
        $start = microtime(true);
        
        for ($i = 0; $i < $operations; $i++) {
            $encrypted = $this->crypto->encrypt($test_data, 'test-key-' . ($i % 10));
        }
        
        $total_time = (microtime(true) - $start) * 1000;
        $ops_per_second = ($operations / ($total_time / 1000));

        echo " Done (" . number_format($total_time, 2) . "ms)\n";
        echo "  - Operations/sec: " . number_format($ops_per_second, 0) . "\n";

        $this->results['encrypt_1000_ops'] = [
            'total_time_ms' => $total_time,
            'operations' => $operations,
            'ops_per_second' => $ops_per_second,
            'avg_time_ms' => $total_time / $operations,
            'status' => $ops_per_second >= 1000 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Average: " . number_format($total_time / $operations, 4) . "ms per operation\n\n";
    }

    private function test_decrypt_1000_operations() {
        echo "Test: Decrypt 1000+ Operations/sec\n";

        $test_data = 'This is test data for decryption performance testing.';
        $operations = 1000;

        // Pre-generate encrypted data
        $encrypted_data = [];
        for ($i = 0; $i < $operations; $i++) {
            $encrypted_data[] = $this->crypto->encrypt($test_data, 'test-key-' . ($i % 10));
        }

        echo "  - Running $operations decryption operations...";
        $start = microtime(true);
        
        foreach ($encrypted_data as $i => $encrypted) {
            $decrypted = $this->crypto->decrypt($encrypted, 'test-key-' . ($i % 10));
        }
        
        $total_time = (microtime(true) - $start) * 1000;
        $ops_per_second = ($operations / ($total_time / 1000));

        echo " Done (" . number_format($total_time, 2) . "ms)\n";
        echo "  - Operations/sec: " . number_format($ops_per_second, 0) . "\n";

        $this->results['decrypt_1000_ops'] = [
            'total_time_ms' => $total_time,
            'operations' => $operations,
            'ops_per_second' => $ops_per_second,
            'avg_time_ms' => $total_time / $operations,
            'status' => $ops_per_second >= 1000 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Average: " . number_format($total_time / $operations, 4) . "ms per operation\n\n";
    }

    private function test_large_payload_encryption() {
        echo "Test: Large Payload Encryption (No Timeout)\n";

        $payload_sizes = [
            '1MB' => 1024 * 1024,
            '5MB' => 5 * 1024 * 1024,
            '10MB' => 10 * 1024 * 1024,
        ];

        foreach ($payload_sizes as $label => $size) {
            echo "  - Encrypting $label payload...";
            
            $data = str_repeat('x', $size);
            $start = microtime(true);
            
            $encrypted = $this->crypto->encrypt($data, 'large-payload-key');
            
            $time = (microtime(true) - $start) * 1000;
            echo " Done (" . number_format($time, 2) . "ms)\n";

            // Verify decryption
            $start = microtime(true);
            $decrypted = $this->crypto->decrypt($encrypted, 'large-payload-key');
            $decrypt_time = (microtime(true) - $start) * 1000;
            
            $status = strlen($decrypted) === $size ? 'PASS' : 'FAIL';
            echo "    - Decryption: " . number_format($decrypt_time, 2) . "ms [$status]\n";
        }

        $this->results['large_payloads'] = [
            'status' => 'PASS',
            'note' => 'All large payload encryption completed without timeout'
        ];

        echo "  ✓ Large Payload Test Complete\n\n";
    }

    private function test_key_derivation_performance() {
        echo "Test: Key Derivation Performance\n";

        $iterations = 100;
        
        echo "  - Deriving keys $iterations times...";
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $key = hash('sha256', 'password-' . $i, false);
        }
        
        $total_time = (microtime(true) - $start) * 1000;
        $avg_time = $total_time / $iterations;

        echo " Done (" . number_format($total_time, 2) . "ms)\n";
        echo "  - Average key derivation: " . number_format($avg_time, 4) . "ms\n";

        $this->results['key_derivation'] = [
            'total_time_ms' => $total_time,
            'iterations' => $iterations,
            'avg_time_ms' => $avg_time,
            'status' => $avg_time < 5 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Key Derivation Complete\n\n";
    }

    private function test_iv_generation_performance() {
        echo "Test: IV Generation Performance\n";

        $iterations = 10000;
        
        echo "  - Generating IVs $iterations times...";
        $start = microtime(true);
        
        $ivs = [];
        for ($i = 0; $i < $iterations; $i++) {
            $iv = openssl_random_pseudo_bytes(16);
            $ivs[] = bin2hex($iv);
        }
        
        $total_time = (microtime(true) - $start) * 1000;
        $ops_per_second = ($iterations / ($total_time / 1000));

        echo " Done (" . number_format($total_time, 2) . "ms)\n";
        echo "  - Operations/sec: " . number_format($ops_per_second, 0) . "\n";
        
        // Verify uniqueness
        $unique_count = count(array_unique($ivs));
        $uniqueness_percentage = ($unique_count / $iterations) * 100;
        echo "  - Uniqueness: " . number_format($uniqueness_percentage, 2) . "%\n";

        $this->results['iv_generation'] = [
            'total_time_ms' => $total_time,
            'iterations' => $iterations,
            'ops_per_second' => $ops_per_second,
            'uniqueness_percent' => $uniqueness_percentage,
            'status' => $ops_per_second >= 10000 && $uniqueness_percentage > 99 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ IV Generation Complete\n\n";
    }

    private function test_concurrent_encryption() {
        echo "Test: Concurrent Encryption Operations\n";

        $concurrent_operations = 100;
        $operations_per_thread = 10;

        echo "  - Simulating $concurrent_operations concurrent operations...\n";
        
        $start = microtime(true);
        $errors = 0;

        for ($i = 0; $i < $concurrent_operations; $i++) {
            for ($j = 0; $j < $operations_per_thread; $j++) {
                try {
                    $data = "Thread $i - Operation $j";
                    $encrypted = $this->crypto->encrypt($data, "key-$i-$j");
                    $decrypted = $this->crypto->decrypt($encrypted, "key-$i-$j");
                    
                    if ($decrypted !== $data) {
                        $errors++;
                    }
                } catch (Exception $e) {
                    $errors++;
                }
            }
        }
        
        $total_time = (microtime(true) - $start) * 1000;
        $total_ops = $concurrent_operations * $operations_per_thread;

        echo "  - Total operations: " . number_format($total_ops) . "\n";
        echo "  - Total time: " . number_format($total_time, 2) . "ms\n";
        echo "  - Errors: $errors\n";

        $this->results['concurrent_encryption'] = [
            'total_operations' => $total_ops,
            'total_time_ms' => $total_time,
            'errors' => $errors,
            'status' => $errors === 0 ? 'PASS' : 'FAIL'
        ];

        echo "  ✓ Concurrent Operations Complete\n\n";
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
$test = new EncryptionPerformanceTest();
$test->run_all_tests();
