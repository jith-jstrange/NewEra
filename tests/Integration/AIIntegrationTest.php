<?php
/**
 * Integration tests for AI workflows
 */

namespace Newera\Tests\Integration;

use Newera\Modules\AI\AIModule;
use Newera\Modules\AI\AIManager;
use Newera\Core\StateManager;
use Newera\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Integration Test
 * 
 * Tests end-to-end AI workflows including:
 * - AI Provider selection & config
 * - APIProvider → OpenAI/Anthropic → response
 * - Rate limiting applied correctly
 * - Cost tracking accumulated
 * - CommandHandler → module execution
 * - Command validation → execution → logging
 */
class AIIntegrationTest extends \Newera\Tests\Integration\IntegrationTestCase {
    
    /**
     * AI Module instance
     *
     * @var AIModule
     */
    private $aiModule;
    
    /**
     * AI Manager instance
     *
     * @var AIManager
     */
    private $aiManager;
    
    /**
     * Mock external AI APIs
     */
    private function mockAIAPIs() {
        // Mock OpenAI API
        if (!class_exists('MockOpenAI')) {
            class MockOpenAI {
                private $api_key = null;
                private $requests = [];
                private $rate_limits = [];
                
                public function setApiKey($key) {
                    $this->api_key = $key;
                    return $key !== null;
                }
                
                public function chat($messages, $model = 'gpt-3.5-turbo', $options = []) {
                    if (!$this->api_key) {
                        throw new \Exception('OpenAI API key not set');
                    }
                    
                    // Check rate limits
                    $this->checkRateLimit();
                    
                    $this->requests[] = [
                        'type' => 'chat',
                        'messages' => $messages,
                        'model' => $model,
                        'options' => $options,
                        'timestamp' => time()
                    ];
                    
                    // Mock response based on model
                    if (strpos($model, 'gpt-4') !== false) {
                        $cost_per_token = 0.00003;
                        $response_text = 'This is a response from GPT-4 with advanced reasoning capabilities.';
                    } else {
                        $cost_per_token = 0.000002;
                        $response_text = 'This is a response from GPT-3.5-turbo for testing purposes.';
                    }
                    
                    return [
                        'id' => 'chatcmpl_' . uniqid(),
                        'object' => 'chat.completion',
                        'created' => time(),
                        'model' => $model,
                        'choices' => [
                            [
                                'index' => 0,
                                'message' => [
                                    'role' => 'assistant',
                                    'content' => $response_text
                                ],
                                'finish_reason' => 'stop'
                            ]
                        ],
                        'usage' => [
                            'prompt_tokens' => 50,
                            'completion_tokens' => 25,
                            'total_tokens' => 75,
                            'cost' => 75 * $cost_per_token
                        ]
                    ];
                }
                
                public function embed($input, $model = 'text-embedding-ada-002') {
                    if (!$this->api_key) {
                        throw new \Exception('OpenAI API key not set');
                    }
                    
                    $this->requests[] = [
                        'type' => 'embed',
                        'input' => $input,
                        'model' => $model,
                        'timestamp' => time()
                    ];
                    
                    return [
                        'object' => 'list',
                        'data' => [
                            [
                                'object' => 'embedding',
                                'embedding' => array_fill(0, 1536, 0.1), // Mock embedding vector
                                'index' => 0
                            ]
                        ],
                        'model' => $model,
                        'usage' => [
                            'prompt_tokens' => 10,
                            'total_tokens' => 10,
                            'cost' => 10 * 0.0000001
                        ]
                    ];
                }
                
                private function checkRateLimit() {
                    // Simple rate limiting check
                    $recent_requests = array_filter($this->requests, function($req) {
                        return $req['timestamp'] > (time() - 60); // Last minute
                    });
                    
                    if (count($recent_requests) > 60) { // 60 requests per minute limit
                        throw new \Exception('Rate limit exceeded');
                    }
                }
                
                public function getRequests() {
                    return $this->requests;
                }
                
                public function reset() {
                    $this->requests = [];
                }
            }
        }
        
        // Mock Anthropic API
        if (!class_exists('MockAnthropic')) {
            class MockAnthropic {
                private $api_key = null;
                private $requests = [];
                
                public function setApiKey($key) {
                    $this->api_key = $key;
                    return $key !== null;
                }
                
                public function messages($messages, $model = 'claude-3-sonnet-20240229', $options = []) {
                    if (!$this->api_key) {
                        throw new \Exception('Anthropic API key not set');
                    }
                    
                    $this->requests[] = [
                        'type' => 'messages',
                        'messages' => $messages,
                        'model' => $model,
                        'options' => $options,
                        'timestamp' => time()
                    ];
                    
                    // Mock Claude response
                    $response_text = 'This is a response from Claude AI with thoughtful analysis and reasoning.';
                    
                    return [
                        'id' => 'msg_' . uniqid(),
                        'type' => 'message',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $response_text
                            ]
                        ],
                        'model' => $model,
                        'stop_reason' => 'end_turn',
                        'usage' => [
                            'input_tokens' => 50,
                            'output_tokens' => 25,
                            'total_tokens' => 75,
                            'cost' => 75 * 0.000015
                        ]
                    ];
                }
                
                public function getRequests() {
                    return $this->requests;
                }
                
                public function reset() {
                    $this->requests = [];
                }
            }
        }
        
        return [
            'openai' => new \MockOpenAI(),
            'anthropic' => new \MockAnthropic()
        ];
    }
    
    /**
     * Mock rate limiting functionality
     */
    private function mockRateLimiting() {
        // Mock WordPress transients for rate limiting
        if (!function_exists('set_transient')) {
            function set_transient($transient, $value, $expiration = 0) {
                return \Newera\Tests\MockTransient::set($transient, $value, $expiration);
            }
        }
        
        if (!function_exists('get_transient')) {
            function get_transient($transient) {
                return \Newera\Tests\MockTransient::get($transient);
            }
        }
        
        if (!function_exists('delete_transient')) {
            function delete_transient($transient) {
                return \Newera\Tests\MockTransient::delete($transient);
            }
        }
    }
    
    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->aiModule = new AIModule($this->stateManager);
        $this->aiManager = new AIManager($this->stateManager);
        
        // Mock external APIs and rate limiting
        $this->mockAIAPIs();
        $this->mockRateLimiting();
        
        // Reset state for clean test
        $this->stateManager->reset_state();
    }
    
    /**
     * Test AI provider selection and configuration
     */
    public function testAIProviderSelectionAndConfig() {
        // Test OpenAI configuration
        $openai_config = [
            'provider' => 'openai',
            'api_key' => 'sk-openai-test-key-12345',
            'model' => 'gpt-4',
            'max_tokens' => 1000,
            'temperature' => 0.7
        ];
        
        $result = $this->aiModule->configureProvider($openai_config);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('openai', $result['provider']);
        
        // Verify OpenAI API key is stored securely
        $stored_api_key = $this->stateManager->getSecure('ai', 'openai_api_key');
        $this->assertEquals('sk-openai-test-key-12345', $stored_api_key);
        
        // Verify non-sensitive settings are stored normally
        $settings = $this->stateManager->get_settings();
        $this->assertEquals('gpt-4', $settings['ai_model']);
        $this->assertEquals(1000, $settings['ai_max_tokens']);
        
        // Test Anthropic configuration
        $anthropic_config = [
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-test-key-67890',
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 2000,
            'temperature' => 0.5
        ];
        
        $result = $this->aiModule->configureProvider($anthropic_config);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('anthropic', $result['provider']);
        
        // Verify Anthropic API key is stored securely
        $stored_anthropic_key = $this->stateManager->getSecure('ai', 'anthropic_api_key');
        $this->assertEquals('sk-ant-test-key-67890', $stored_anthropic_key);
    }
    
    /**
     * Test APIProvider → OpenAI → response flow
     */
    public function testOpenAIResponseFlow() {
        // Configure OpenAI
        $openai_config = [
            'provider' => 'openai',
            'api_key' => 'sk-test-openai-key',
            'model' => 'gpt-3.5-turbo'
        ];
        
        $this->aiModule->configureProvider($openai_config);
        
        // Test chat completion
        $messages = [
            ['role' => 'user', 'content' => 'Hello, this is a test message']
        ];
        
        $response = $this->aiManager->chat($messages);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('content', $response);
        $this->assertArrayHasKey('usage', $response);
        $this->assertArrayHasKey('cost', $response);
        
        // Verify response content
        $this->assertNotEmpty($response['content']);
        $this->assertIsString($response['content']);
        
        // Verify usage tracking
        $this->assertGreaterThan(0, $response['usage']['total_tokens']);
        $this->assertGreaterThan(0, $response['cost']);
        
        // Test with different model
        $response_gpt4 = $this->aiManager->chat($messages, 'gpt-4');
        
        $this->assertTrue($response_gpt4['success']);
        $this->assertArrayHasKey('content', $response_gpt4);
        
        // Verify GPT-4 response cost is higher than GPT-3.5
        $this->assertGreaterThan($response['cost'], $response_gpt4['cost']);
    }
    
    /**
     * Test APIProvider → Anthropic → response flow
     */
    public function testAnthropicResponseFlow() {
        // Configure Anthropic
        $anthropic_config = [
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-test-key',
            'model' => 'claude-3-sonnet-20240229'
        ];
        
        $this->aiModule->configureProvider($anthropic_config);
        
        // Test message completion
        $messages = [
            ['role' => 'user', 'content' => 'Analyze this test prompt for AI processing']
        ];
        
        $response = $this->aiManager->chat($messages);
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('content', $response);
        $this->assertArrayHasKey('usage', $response);
        $this->assertArrayHasKey('cost', $response);
        
        // Verify response content
        $this->assertNotEmpty($response['content']);
        $this->assertStringContains('Claude', $response['content']); // Verify it's Claude response
        
        // Verify usage tracking
        $this->assertGreaterThan(0, $response['usage']['total_tokens']);
        $this->assertGreaterThan(0, $response['cost']);
    }
    
    /**
     * Test rate limiting applied correctly
     */
    public function testRateLimitingAppliedCorrectly() {
        // Configure OpenAI
        $openai_config = [
            'provider' => 'openai',
            'api_key' => 'sk-test-key',
            'model' => 'gpt-3.5-turbo',
            'rate_limit' => [
                'requests_per_minute' => 3, // Low limit for testing
                'tokens_per_hour' => 1000
            ]
        ];
        
        $this->aiModule->configureProvider($openai_config);
        
        $messages = [['role' => 'user', 'content' => 'Test message']];
        
        // Make requests up to the limit
        for ($i = 0; $i < 3; $i++) {
            $response = $this->aiManager->chat($messages);
            $this->assertTrue($response['success'], "Request {$i} should succeed");
        }
        
        // Fourth request should be rate limited
        $rate_limited_response = $this->aiManager->chat($messages);
        $this->assertFalse($rate_limited_response['success']);
        $this->assertEquals('rate_limited', $rate_limited_response['error_code']);
        $this->assertArrayHasKey('retry_after', $rate_limited_response);
        
        // Test token-based rate limiting
        $large_message = [['role' => 'user', 'content' => str_repeat('This is a long message to test token limits. ', 100)]];
        
        $token_response = $this->aiManager->chat($large_message);
        
        if ($token_response['usage']['total_tokens'] > 1000) {
            // Should trigger token-based rate limiting
            $this->assertFalse($token_response['success']);
            $this->assertEquals('token_limit_exceeded', $token_response['error_code']);
        }
    }
    
    /**
     * Test cost tracking accumulation
     */
    public function testCostTrackingAccumulation() {
        // Configure OpenAI with cost tracking
        $openai_config = [
            'provider' => 'openai',
            'api_key' => 'sk-test-key',
            'model' => 'gpt-3.5-turbo',
            'cost_tracking' => true
        ];
        
        $this->aiModule->configureProvider($openai_config);
        
        // Make multiple requests
        $messages = [['role' => 'user', 'content' => 'Cost tracking test']];
        
        $request1 = $this->aiManager->chat($messages);
        $request2 = $this->aiManager->chat($messages);
        $request3 = $this->aiManager->chat($messages);
        
        // Verify each request has cost
        $this->assertGreaterThan(0, $request1['cost']);
        $this->assertGreaterThan(0, $request2['cost']);
        $this->assertGreaterThan(0, $request3['cost']);
        
        // Get accumulated costs
        $cost_summary = $this->aiManager->getCostSummary();
        
        $this->assertArrayHasKey('total_cost', $cost_summary);
        $this->assertArrayHasKey('request_count', $cost_summary);
        $this->assertArrayHasKey('provider_breakdown', $cost_summary);
        
        // Verify accumulated cost equals sum of individual costs
        $expected_total = $request1['cost'] + $request2['cost'] + $request3['cost'];
        $this->assertEquals($expected_total, $cost_summary['total_cost']);
        
        // Verify request count
        $this->assertEquals(3, $cost_summary['request_count']);
        
        // Test cost per provider
        $this->assertArrayHasKey('openai', $cost_summary['provider_breakdown']);
        $this->assertEquals($expected_total, $cost_summary['provider_breakdown']['openai']['total_cost']);
    }
    
    /**
     * Test CommandHandler → module execution
     */
    public function testCommandHandlerModuleExecution() {
        // Register test commands
        $this->aiModule->registerCommand('test_echo', [
            'description' => 'Echo back the input',
            'parameters' => ['input' => 'string'],
            'handler' => function($params) {
                return ['response' => 'Echo: ' . $params['input']];
            }
        ]);
        
        $this->aiModule->registerCommand('test_calculation', [
            'description' => 'Perform a simple calculation',
            'parameters' => ['num1' => 'number', 'num2' => 'number', 'operation' => 'string'],
            'handler' => function($params) {
                switch ($params['operation']) {
                    case 'add':
                        $result = $params['num1'] + $params['num2'];
                        break;
                    case 'multiply':
                        $result = $params['num1'] * $params['num2'];
                        break;
                    default:
                        throw new \Exception('Unsupported operation');
                }
                return ['result' => $result, 'operation' => $params['operation']];
            }
        ]);
        
        // Test echo command
        $echo_result = $this->aiManager->executeCommand('test_echo', ['input' => 'Hello World']);
        
        $this->assertTrue($echo_result['success']);
        $this->assertEquals('Echo: Hello World', $echo_result['response']);
        
        // Test calculation command
        $calc_result = $this->aiManager->executeCommand('test_calculation', [
            'num1' => 10,
            'num2' => 5,
            'operation' => 'multiply'
        ]);
        
        $this->assertTrue($calc_result['success']);
        $this->assertEquals(50, $calc_result['result']);
        
        // Test unsupported command
        $invalid_result = $this->aiManager->executeCommand('nonexistent_command', []);
        
        $this->assertFalse($invalid_result['success']);
        $this->assertEquals('command_not_found', $invalid_result['error_code']);
    }
    
    /**
     * Test command validation → execution → logging
     */
    public function testCommandValidationExecutionLogging() {
        // Register a command with validation
        $this->aiModule->registerCommand('validated_command', [
            'description' => 'Command with validation',
            'parameters' => [
                'email' => 'email',
                'age' => 'number',
                'required_field' => 'string'
            ],
            'required' => ['required_field'],
            'handler' => function($params) {
                return ['processed' => true, 'data' => $params];
            }
        ]);
        
        // Test valid execution
        $valid_params = [
            'email' => 'test@example.com',
            'age' => 25,
            'required_field' => 'test data'
        ];
        
        $valid_result = $this->aiManager->executeCommand('validated_command', $valid_params);
        
        $this->assertTrue($valid_result['success']);
        $this->assertEquals('test@example.com', $valid_result['data']['email']);
        $this->assertEquals(25, $valid_result['data']['age']);
        
        // Test missing required field
        $invalid_params = [
            'email' => 'test@example.com',
            'age' => 25
            // Missing required_field
        ];
        
        $missing_field_result = $this->aiManager->executeCommand('validated_command', $invalid_params);
        
        $this->assertFalse($missing_field_result['success']);
        $this->assertEquals('missing_required_field', $missing_field_result['error_code']);
        $this->assertArrayHasKey('missing_fields', $missing_field_result);
        
        // Test invalid email format
        $invalid_email_params = [
            'email' => 'invalid-email',
            'age' => 25,
            'required_field' => 'test data'
        ];
        
        $invalid_email_result = $this->aiManager->executeCommand('validated_command', $invalid_email_params);
        
        $this->assertFalse($invalid_email_result['success']);
        $this->assertEquals('validation_failed', $invalid_email_result['error_code']);
        $this->assertArrayHasKey('validation_errors', $invalid_email_result);
        
        // Test invalid number
        $invalid_number_params = [
            'email' => 'test@example.com',
            'age' => 'not_a_number',
            'required_field' => 'test data'
        ];
        
        $invalid_number_result = $this->aiManager->executeCommand('validated_command', $invalid_number_params);
        
        $this->assertFalse($invalid_number_result['success']);
        $this->assertEquals('validation_failed', $invalid_number_result['error_code']);
        
        // Verify execution logging
        $execution_logs = $this->aiManager->getExecutionLogs();
        
        $this->assertNotEmpty($execution_logs);
        
        // Find successful execution log
        $successful_log = array_filter($execution_logs, function($log) {
            return $log['command'] === 'validated_command' && $log['success'] === true;
        });
        $this->assertNotEmpty($successful_log);
        
        // Find failed execution logs
        $failed_logs = array_filter($execution_logs, function($log) {
            return $log['command'] === 'validated_command' && $log['success'] === false;
        });
        $this->assertCount(3, $failed_logs); // Three failed attempts above
    }
    
    /**
     * Test embedding generation
     */
    public function testEmbeddingGeneration() {
        // Configure OpenAI for embeddings
        $openai_config = [
            'provider' => 'openai',
            'api_key' => 'sk-test-key',
            'model' => 'text-embedding-ada-002'
        ];
        
        $this->aiModule->configureProvider($openai_config);
        
        // Generate embeddings for test text
        $texts = [
            'First document about artificial intelligence',
            'Second document about machine learning',
            'Third document about natural language processing'
        ];
        
        $embedding_result = $this->aiManager->generateEmbeddings($texts);
        
        $this->assertTrue($embedding_result['success']);
        $this->assertCount(3, $embedding_result['embeddings']);
        
        // Verify each embedding
        foreach ($embedding_result['embeddings'] as $i => $embedding) {
            $this->assertArrayHasKey('index', $embedding);
            $this->assertArrayHasKey('vector', $embedding);
            $this->assertArrayHasKey('usage', $embedding);
            $this->assertEquals($i, $embedding['index']);
            $this->assertCount(1536, $embedding['vector']); // OpenAI embedding size
        }
        
        // Test similarity calculation
        $similarity_result = $this->aiManager->calculateSimilarity(
            $embedding_result['embeddings'][0]['vector'],
            $embedding_result['embeddings'][1]['vector']
        );
        
        $this->assertTrue($similarity_result['success']);
        $this->assertArrayHasKey('similarity', $similarity_result);
        $this->assertGreaterThanOrEqual(-1, $similarity_result['similarity']);
        $this->assertLessThanOrEqual(1, $similarity_result['similarity']);
    }
    
    /**
     * Test AI provider switching
     */
    public function testAIProviderSwitching() {
        // Start with OpenAI
        $openai_config = [
            'provider' => 'openai',
            'api_key' => 'sk-openai-test',
            'model' => 'gpt-3.5-turbo'
        ];
        
        $openai_result = $this->aiModule->configureProvider($openai_config);
        $this->assertTrue($openai_result['success']);
        
        // Make request with OpenAI
        $messages = [['role' => 'user', 'content' => 'Provider test']];
        $openai_response = $this->aiManager->chat($messages);
        $this->assertTrue($openai_response['success']);
        
        // Switch to Anthropic
        $anthropic_config = [
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-test',
            'model' => 'claude-3-sonnet-20240229'
        ];
        
        $anthropic_result = $this->aiModule->configureProvider($anthropic_config);
        $this->assertTrue($anthropic_result['success']);
        
        // Make request with Anthropic
        $anthropic_response = $this->aiManager->chat($messages);
        $this->assertTrue($anthropic_response['success']);
        
        // Verify different responses from different providers
        $this->assertNotEquals($openai_response['content'], $anthropic_response['content']);
        
        // Verify cost tracking for different providers
        $cost_summary = $this->aiManager->getCostSummary();
        $this->assertArrayHasKey('openai', $cost_summary['provider_breakdown']);
        $this->assertArrayHasKey('anthropic', $cost_summary['provider_breakdown']);
        $this->assertGreaterThan(0, $cost_summary['provider_breakdown']['openai']['total_cost']);
        $this->assertGreaterThan(0, $cost_summary['provider_breakdown']['anthropic']['total_cost']);
    }
    
    /**
     * Test AI error handling and fallback
     */
    public function testAIErrorHandlingAndFallback() {
        // Configure OpenAI
        $openai_config = [
            'provider' => 'openai',
            'api_key' => 'sk-test-key',
            'model' => 'gpt-3.5-turbo'
        ];
        
        $this->aiModule->configureProvider($openai_config);
        
        // Test API error handling
        $error_response = $this->aiManager->simulateAPIError('quota_exceeded');
        $this->assertFalse($error_response['success']);
        $this->assertEquals('quota_exceeded', $error_response['error_code']);
        $this->assertArrayHasKey('retry_after', $error_response);
        
        // Test network error handling
        $network_error = $this->aiManager->simulateAPIError('network_error');
        $this->assertFalse($network_error['success']);
        $this->assertEquals('network_error', $network_error['error_code']);
        
        // Test model not found error
        $model_error = $this->aiManager->simulateAPIError('model_not_found');
        $this->assertFalse($model_error['success']);
        $this->assertEquals('model_not_found', $model_error['error_code']);
        
        // Test fallback behavior
        $fallback_response = $this->aiManager->executeWithFallback(function() {
            $messages = [['role' => 'user', 'content' => 'Fallback test']];
            return $this->aiManager->chat($messages);
        });
        
        $this->assertTrue($fallback_response['success']);
        $this->assertArrayHasKey('fallback_used', $fallback_response);
        $this->assertFalse($fallback_response['fallback_used']); // No fallback needed for successful request
    }
}