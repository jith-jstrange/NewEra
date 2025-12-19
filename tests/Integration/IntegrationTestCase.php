<?php
/**
 * Base integration test case for Newera plugin
 */

namespace Newera\Tests\Integration;

use Newera\Tests\TestCase;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration Test Case base class
 */
abstract class IntegrationTestCase extends TestCase {
    
    /**
     * State manager instance
     *
     * @var \Newera\Core\StateManager
     */
    protected $stateManager;
    
    /**
     * Setup integration test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        // Initialize state manager
        $this->stateManager = new \Newera\Core\StateManager();
        $this->stateManager->init();
        
        // Mock external service APIs
        $this->mockExternalAPIs();
        
        // Setup integration test fixtures
        $this->setupIntegrationFixtures();
    }
    
    /**
     * Mock external service APIs for testing
     */
    protected function mockExternalAPIs() {
        // Mock HTTP requests for external APIs
        if (!function_exists('wp_remote_request')) {
            function wp_remote_request($url, $args = []) {
                return \Newera\Tests\MockHTTP::mockRequest($url, $args);
            }
        }
        
        // Mock external database connections
        if (!function_exists('mysqli_connect')) {
            function mysqli_connect($host, $user, $pass, $db) {
                return \Newera\Tests\MockMySQL::connect($host, $user, $pass, $db);
            }
        }
        
        // Mock email sending for external services
        if (!function_exists('wp_mail')) {
            function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
                return \Newera\Tests\MockEmail::send($to, $subject, $message, $headers, $attachments);
            }
        }
    }
    
    /**
     * Setup integration test fixtures
     */
    protected function setupIntegrationFixtures() {
        // Setup common test data
        $this->setupTestUsers();
        $this->setupTestProjects();
        $this->setupTestSubscriptions();
    }
    
    /**
     * Setup test users
     */
    protected function setupTestUsers() {
        // Add test users to mock WordPress
        \Newera\Tests\MockWPDB::add_test_user([
            'ID' => 1,
            'user_login' => 'testadmin',
            'user_email' => 'admin@test.com',
            'user_pass' => wp_hash_password('password'),
            'display_name' => 'Test Admin',
            'role' => 'administrator'
        ]);
        
        \Newera\Tests\MockWPDB::add_test_user([
            'ID' => 2,
            'user_login' => 'testclient',
            'user_email' => 'client@test.com',
            'user_pass' => wp_hash_password('password'),
            'display_name' => 'Test Client',
            'role' => 'subscriber'
        ]);
    }
    
    /**
     * Setup test projects
     */
    protected function setupTestProjects() {
        // This will be overridden by individual test classes
    }
    
    /**
     * Setup test subscriptions
     */
    protected function setupTestSubscriptions() {
        // Add test subscription data
        global $wpdb;
        
        $test_subscription = [
            'user_id' => 2,
            'plan_id' => 'plan_test_premium',
            'stripe_subscription_id' => 'sub_test_12345',
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        \Newera\Tests\MockWPDB::insert('wp_subscriptions', $test_subscription);
    }
    
    /**
     * Create mock WordPress environment
     */
    protected function createMockWordPressEnvironment() {
        // Mock WordPress user functions
        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() {
                return 1; // Mock admin user
            }
        }
        
        if (!function_exists('wp_get_current_user')) {
            function wp_get_current_user() {
                return \Newera\Tests\MockWPDB::get_user_by('ID', get_current_user_id());
            }
        }
        
        if (!function_exists('is_user_logged_in')) {
            function is_user_logged_in() {
                return get_current_user_id() > 0;
            }
        }
        
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                $user = wp_get_current_user();
                if (!$user) return false;
                
                // Admin can do everything, others have limited capabilities
                if (in_array('administrator', $user->roles)) {
                    return true;
                }
                
                $user_capabilities = [
                    'read' => true,
                    'subscriber' => ['read' => true]
                ];
                
                return isset($user_capabilities[$capability]);
            }
        }
    }
    
    /**
     * Mock external service responses
     */
    protected function mockExternalServiceResponses() {
        // Mock Linear API responses
        $this->addMockResponse('linear', 'https://api.linear.app/graphql', [
            'data' => [
                'issues' => [
                    'nodes' => [
                        [
                            'id' => 'issue_123',
                            'title' => 'Mock Linear Issue',
                            'state' => ['name' => 'Todo'],
                            'assignee' => ['name' => 'Test User']
                        ]
                    ]
                ]
            ]
        ]);
        
        // Mock Stripe API responses
        $this->addMockResponse('stripe', 'https://api.stripe.com/v1/', [
            'id' => 'mock_subscription_123',
            'status' => 'active',
            'customer' => 'cus_mock123',
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'id' => 'price_mock123',
                            'product' => 'prod_mock123'
                        ]
                    ]
                ]
            ]
        ]);
        
        // Mock OpenAI API responses
        $this->addMockResponse('openai', 'https://api.openai.com/v1/', [
            'id' => 'mock_chat_completion',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Mock AI response for testing'
                    ],
                    'finish_reason' => 'stop'
                ]
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30
            ]
        ]);
    }
    
    /**
     * Add mock response for external service
     */
    protected function addMockResponse($service, $url_pattern, $response_data) {
        \Newera\Tests\MockHTTP::addMockResponse($service, $url_pattern, $response_data);
    }
    
    /**
     * Reset integration test data
     */
    protected function resetIntegrationTestData() {
        // Clear all test data
        \Newera\Tests\MockStorage::clear_all();
        \Newera\Tests\MockWPDB::clear_all();
        \Newera\Tests\MockHTTP::clear_all();
        
        // Reset state
        if ($this->stateManager) {
            $this->stateManager->reset_state();
        }
    }
    
    /**
     * Clean up after integration test
     */
    protected function tearDown(): void {
        $this->resetIntegrationTestData();
        parent::tearDown();
    }
    
    /**
     * Assert integration workflow completed successfully
     */
    protected function assertIntegrationWorkflowSuccess($result, $context = '') {
        $this->assertTrue($result['success'], "Integration workflow failed: " . ($result['message'] ?? 'Unknown error') . ($context ? " ({$context})" : ''));
    }
    
    /**
     * Assert external API was called with expected parameters
     */
    protected function assertExternalAPICalled($service, $expected_method, $expected_path = null) {
        $calls = \Newera\Tests\MockHTTP::getServiceCalls($service);
        
        $this->assertNotEmpty($calls, "No calls made to {$service} service");
        
        $found_call = false;
        foreach ($calls as $call) {
            if ($call['method'] === $expected_method) {
                if ($expected_path === null || strpos($call['url'], $expected_path) !== false) {
                    $found_call = true;
                    break;
                }
            }
        }
        
        $this->assertTrue($found_call, "Expected {$service} API call with method {$expected_method}" . ($expected_path ? " and path containing {$expected_path}" : '') . " was not found");
    }
    
    /**
     * Assert credential isolation between modules
     */
    protected function assertCredentialIsolation($module1_data, $module2_data, $module1_name, $module2_name) {
        // Verify module1 data is not accessible from module2 namespace
        $module1_from_module2 = $this->stateManager->getSecure($module2_name, array_keys($module1_data)[0], 'NOT_FOUND');
        $this->assertEquals('NOT_FOUND', $module1_from_module2, "Credential from {$module1_name} is accessible from {$module2_name} namespace");
        
        // Verify module2 data is not accessible from module1 namespace
        $module2_from_module1 = $this->stateManager->getSecure($module1_name, array_keys($module2_data)[0], 'NOT_FOUND');
        $this->assertEquals('NOT_FOUND', $module2_from_module1, "Credential from {$module2_name} is accessible from {$module1_name} namespace");
        
        // Verify each module can access its own data
        foreach ($module1_data as $key => $expected_value) {
            $actual_value = $this->stateManager->getSecure($module1_name, $key);
            $this->assertEquals($expected_value, $actual_value, "Credential {$key} from {$module1_name} not accessible in its own namespace");
        }
        
        foreach ($module2_data as $key => $expected_value) {
            $actual_value = $this->stateManager->getSecure($module2_name, $key);
            $this->assertEquals($expected_value, $actual_value, "Credential {$key} from {$module2_name} not accessible in its own namespace");
        }
    }
    
    /**
     * Wait for async operations to complete
     */
    protected function waitForAsyncOperations($timeout_seconds = 5) {
        $start_time = time();
        
        while (time() - $start_time < $timeout_seconds) {
            // Check if any async operations are still running
            $running_operations = \Newera\Tests\MockAsync::getRunningOperations();
            
            if (empty($running_operations)) {
                return; // All operations completed
            }
            
            // Sleep briefly before checking again
            usleep(100000); // 0.1 seconds
        }
        
        // Timeout - some operations may still be running
        $remaining_operations = \Newera\Tests\MockAsync::getRunningOperations();
        $this->assertEmpty($remaining_operations, "Some async operations did not complete within {$timeout_seconds} seconds: " . implode(', ', $remaining_operations));
    }
    
    /**
     * Verify data consistency across multiple databases/systems
     */
    protected function verifyDataConsistency($entity_type, $entity_id, $expected_data, $sources = ['wordpress', 'external']) {
        foreach ($sources as $source) {
            $source_data = $this->getEntityFromSource($entity_type, $entity_id, $source);
            $this->assertNotNull($source_data, "Entity {$entity_id} not found in {$source}");
            
            foreach ($expected_data as $field => $expected_value) {
                $this->assertEquals($expected_value, $source_data[$field], "Field {$field} mismatch in {$source} for entity {$entity_id}");
            }
        }
    }
    
    /**
     * Get entity from specific data source
     */
    protected function getEntityFromSource($entity_type, $entity_id, $source) {
        switch ($source) {
            case 'wordpress':
                return $this->getWordPressEntity($entity_type, $entity_id);
            case 'external':
                return $this->getExternalEntity($entity_type, $entity_id);
            default:
                return null;
        }
    }
    
    /**
     * Get entity from WordPress database
     */
    protected function getWordPressEntity($entity_type, $entity_id) {
        global $wpdb;
        
        switch ($entity_type) {
            case 'user':
                return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID = %d", $entity_id));
            case 'subscription':
                return $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_subscriptions WHERE id = %d OR stripe_subscription_id = %s", $entity_id, $entity_id));
            case 'project':
                return $this->stateManager->getSecure('projects', $entity_id);
            default:
                return null;
        }
    }
    
    /**
     * Get entity from external systems
     */
    protected function getExternalEntity($entity_type, $entity_id) {
        switch ($entity_type) {
            case 'subscription':
                return \Newera\Tests\MockStripe::getSubscription($entity_id);
            case 'project':
                return \Newera\Tests\MockLinear::getProject($entity_id);
            default:
                return null;
        }
    }
}