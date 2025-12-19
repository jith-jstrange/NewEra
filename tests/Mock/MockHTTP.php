<?php
/**
 * Mock HTTP functions for testing external API calls
 */

namespace Newera\Tests\Mock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MockHTTP class for simulating external API calls
 */
class MockHTTP {
    
    /**
     * Mock responses by service
     */
    private static $responses = [
        'linear' => [],
        'stripe' => [],
        'openai' => [],
        'anthropic' => [],
        'notion' => []
    ];
    
    /**
     * Mock API calls log
     */
    private static $api_calls = [];
    
    /**
     * Mock external API endpoints and responses
     */
    private static function initializeMockResponses() {
        // Linear API mock responses
        self::$responses['linear'] = [
            'https://api.linear.app/graphql' => [
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
                    ],
                    'projects' => [
                        'nodes' => [
                            [
                                'id' => 'proj_123',
                                'name' => 'Mock Linear Project',
                                'description' => 'Test project from Linear'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        
        // Stripe API mock responses
        self::$responses['stripe'] = [
            'https://api.stripe.com/v1/subscriptions' => [
                'id' => 'sub_mock123',
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
            ],
            'https://api.stripe.com/v1/invoices' => [
                'id' => 'in_mock123',
                'customer' => 'cus_mock123',
                'amount_paid' => 2999,
                'currency' => 'usd'
            ]
        ];
        
        // OpenAI API mock responses
        self::$responses['openai'] = [
            'https://api.openai.com/v1/chat/completions' => [
                'id' => 'chatcmpl_mock123',
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
            ]
        ];
        
        // Anthropic API mock responses
        self::$responses['anthropic'] = [
            'https://api.anthropic.com/v1/messages' => [
                'id' => 'msg_mock123',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Mock Claude response for testing'
                    ]
                ]
            ]
        ];
        
        // Notion API mock responses
        self::$responses['notion'] = [
            'https://api.notion.com/v1/databases' => [
                'results' => [
                    [
                        'id' => 'db_mock123',
                        'title' => [{'text' => ['content' => 'Mock Notion Database']}]
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Initialize mock responses
     */
    public static function init() {
        self::initializeMockResponses();
    }
    
    /**
     * Add mock response for service
     */
    public static function addMockResponse($service, $url_pattern, $response_data) {
        if (!isset(self::$responses[$service])) {
            self::$responses[$service] = [];
        }
        self::$responses[$service][$url_pattern] = $response_data;
    }
    
    /**
     * Mock HTTP request
     */
    public static function mockRequest($url, $args = []) {
        // Log the API call
        $service = self::detectService($url);
        self::$api_calls[] = [
            'url' => $url,
            'method' => $args['method'] ?? 'GET',
            'headers' => $args['headers'] ?? [],
            'body' => $args['body'] ?? '',
            'service' => $service,
            'timestamp' => time()
        ];
        
        // Determine response based on URL and service
        $response_data = self::getMockResponse($url, $service);
        
        if ($response_data) {
            return [
                'response' => json_encode($response_data),
                'headers' => ['Content-Type' => 'application/json'],
                'status_code' => 200
            ];
        }
        
        // Return error response if no mock found
        return [
            'response' => json_encode(['error' => ['message' => 'Mock response not found']]),
            'headers' => ['Content-Type' => 'application/json'],
            'status_code' => 404
        ];
    }
    
    /**
     * Detect service from URL
     */
    private static function detectService($url) {
        if (strpos($url, 'linear.app') !== false) {
            return 'linear';
        } elseif (strpos($url, 'stripe.com') !== false) {
            return 'stripe';
        } elseif (strpos($url, 'openai.com') !== false) {
            return 'openai';
        } elseif (strpos($url, 'anthropic.com') !== false) {
            return 'anthropic';
        } elseif (strpos($url, 'notion.com') !== false) {
            return 'notion';
        }
        return 'unknown';
    }
    
    /**
     * Get mock response for URL
     */
    private static function getMockResponse($url, $service) {
        if (!isset(self::$responses[$service])) {
            return null;
        }
        
        foreach (self::$responses[$service] as $pattern => $response) {
            if (strpos($url, $pattern) !== false) {
                return $response;
            }
        }
        
        return null;
    }
    
    /**
     * Get service calls
     */
    public static function getServiceCalls($service = null) {
        if ($service === null) {
            return self::$api_calls;
        }
        
        return array_filter(self::$api_calls, function($call) use ($service) {
            return $call['service'] === $service;
        });
    }
    
    /**
     * Get API calls count
     */
    public static function getAPICallsCount() {
        return count(self::$api_calls);
    }
    
    /**
     * Clear all API calls
     */
    public static function clearAPICalls() {
        self::$api_calls = [];
    }
    
    /**
     * Simulate API error
     */
    public static function simulateAPIError($service, $error_code, $error_message = null) {
        $service_key = $service . '_error_' . $error_code;
        self::$responses[$service_key] = [
            'error' => [
                'code' => $error_code,
                'message' => $error_message ?? "Simulated {$error_code} error"
            ]
        ];
    }
    
    /**
     * Get mock response data
     */
    public static function getMockResponseData($service, $url_pattern) {
        return isset(self::$responses[$service][$url_pattern]) ? self::$responses[$service][$url_pattern] : null;
    }
    
    /**
     * Add error response
     */
    public static function addErrorResponse($service, $url_pattern, $status_code, $error_data) {
        if (!isset(self::$responses[$service])) {
            self::$responses[$service] = [];
        }
        
        self::$responses[$service][$url_pattern . '_error'] = [
            'status_code' => $status_code,
            'error' => $error_data
        ];
    }
    
    /**
     * Clear all mock data
     */
    public static function clearAll() {
        self::$responses = [];
        self::$api_calls = [];
    }
    
    /**
     * Reset mock responses
     */
    public static function resetResponses() {
        self::initializeMockResponses();
    }
    
    /**
     * Simulate rate limiting
     */
    public static function simulateRateLimit($service, $retry_after = 60) {
        self::addErrorResponse($service, 'rate_limit', 429, [
            'error' => [
                'code' => 'rate_limit_exceeded',
                'message' => 'Rate limit exceeded',
                'retry_after' => $retry_after
            ]
        ]);
    }
    
    /**
     * Simulate network error
     */
    public static function simulateNetworkError($service) {
        return [
            'response' => null,
            'headers' => [],
            'status_code' => 0,
            'error' => 'Network error'
        ];
    }
    
    /**
     * Get last API call
     */
    public static function getLastAPICall() {
        return !empty(self::$api_calls) ? end(self::$api_calls) : null;
    }
    
    /**
     * Verify API was called
     */
    public static function verifyAPICalled($service, $url_pattern = null, $method = null) {
        $calls = self::getServiceCalls($service);
        
        foreach ($calls as $call) {
            $matches_url = $url_pattern === null || strpos($call['url'], $url_pattern) !== false;
            $matches_method = $method === null || $call['method'] === $method;
            
            if ($matches_url && $matches_method) {
                return true;
            }
        }
        
        return false;
    }
}

// Initialize mock responses
MockHTTP::init();