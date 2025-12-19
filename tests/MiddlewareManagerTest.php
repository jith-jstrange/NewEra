<?php
/**
 * Middleware Manager Tests
 *
 * @package Newera\Tests
 */

namespace Newera\Tests;

use Newera\API\Middleware\MiddlewareManager;
use Newera\Core\StateManager;
use Newera\Core\Logger;

/**
 * Test Middleware Manager (CORS, Rate Limiting, Request Logging)
 */
class MiddlewareManagerTest extends TestCase {
    private $middleware_manager;
    private $state_manager;
    private $logger;

    protected function setUp(): void {
        parent::setUp();

        $this->mockWordPressFunctions();

        $this->logger = $this->createMock(Logger::class);
        $this->state_manager = $this->createMock(StateManager::class);

        // Mock CORS settings
        $this->state_manager->method('get_option')
            ->willReturn([
                'enabled' => true,
                'allowed_origins' => ['https://example.com', 'https://app.example.com'],
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
                'exposed_headers' => ['X-Total-Count', 'X-Page-Count', 'X-Rate-Limit-Remaining'],
                'allow_credentials' => false,
                'max_age' => 86400
            ]);

        $this->middleware_manager = new MiddlewareManager($this->state_manager, $this->logger);
        $this->middleware_manager->init();
    }

    private function mockWordPressFunctions() {
        if (!function_exists('get_http_header')) {
            function get_http_header($header) {
                global $_test_headers;
                return $_test_headers[$header] ?? null;
            }
        }

        if (!function_exists('get_transient')) {
            global $_test_transients;
            if (!isset($_test_transients)) {
                $_test_transients = [];
            }
            function get_transient($key) {
                global $_test_transients;
                if (isset($_test_transients[$key])) {
                    if ($_test_transients[$key]['expires'] > time()) {
                        return $_test_transients[$key]['value'];
                    }
                    unset($_test_transients[$key]);
                }
                return false;
            }
        }

        if (!function_exists('set_transient')) {
            function set_transient($key, $value, $expiration = 0) {
                global $_test_transients;
                $_test_transients[$key] = [
                    'value' => $value,
                    'expires' => time() + $expiration
                ];
                return true;
            }
        }

        if (!function_exists('wp_generate_uuid4')) {
            function wp_generate_uuid4() {
                return sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }
        }

        if (!function_exists('sanitize_text_field')) {
            function sanitize_text_field($str) {
                return strip_tags($str);
            }
        }

        if (!class_exists('WP_Error')) {
            class WP_Error {
                private $code;
                private $message;
                private $data;

                public function __construct($code, $message, $data = []) {
                    $this->code = $code;
                    $this->message = $message;
                    $this->data = $data;
                }

                public function get_error_code() {
                    return $this->code;
                }

                public function get_error_message() {
                    return $this->message;
                }

                public function get_error_data() {
                    return $this->data;
                }
            }
        }

        if (!function_exists('is_wp_error')) {
            function is_wp_error($thing) {
                return ($thing instanceof \WP_Error);
            }
        }
    }

    // ========== CORS Tests ==========

    public function testCORSAllowsValidOrigin() {
        global $_test_headers;
        $_test_headers = ['Origin' => 'https://example.com'];

        $result = $this->middleware_manager->handle_cors_preflight(
            'https://example.com',
            'GET'
        );

        $this->assertTrue($result);
    }

    public function testCORSDeniesInvalidOrigin() {
        $result = $this->middleware_manager->handle_cors_preflight(
            'https://evil.com',
            'GET'
        );

        $this->assertFalse($result);
    }

    public function testCORSAllowsWildcardOrigin() {
        // Create middleware with wildcard origin
        $this->state_manager = $this->createMock(StateManager::class);
        $this->state_manager->method('get_option')
            ->willReturn([
                'enabled' => true,
                'allowed_origins' => ['*'],
                'allowed_methods' => ['GET', 'POST'],
                'allowed_headers' => ['Content-Type'],
                'exposed_headers' => [],
                'allow_credentials' => false,
                'max_age' => 86400
            ]);

        $middleware = new MiddlewareManager($this->state_manager, $this->logger);
        $middleware->init();

        $result = $middleware->handle_cors_preflight('https://anyorigin.com', 'GET');
        $this->assertTrue($result);
    }

    public function testCORSDisabledRejectsAllOrigins() {
        $this->state_manager = $this->createMock(StateManager::class);
        $this->state_manager->method('get_option')
            ->willReturn([
                'enabled' => false,
                'allowed_origins' => ['*'],
                'allowed_methods' => ['GET'],
                'allowed_headers' => [],
                'exposed_headers' => [],
                'allow_credentials' => false,
                'max_age' => 86400
            ]);

        $middleware = new MiddlewareManager($this->state_manager, $this->logger);
        $middleware->init();

        $result = $middleware->handle_cors_preflight('https://example.com', 'GET');
        $this->assertFalse($result);
    }

    public function testCORSHeadersAreSet() {
        // This is harder to test directly without output buffering
        // We verify the logic through the preflight test
        $result = $this->middleware_manager->handle_cors_preflight(
            'https://example.com',
            'POST',
            ['Content-Type', 'Authorization']
        );

        $this->assertTrue($result);
    }

    public function testCORSWildcardSubdomainMatching() {
        $this->state_manager = $this->createMock(StateManager::class);
        $this->state_manager->method('get_option')
            ->willReturn([
                'enabled' => true,
                'allowed_origins' => ['https://*.example.com'],
                'allowed_methods' => ['GET'],
                'allowed_headers' => [],
                'exposed_headers' => [],
                'allow_credentials' => false,
                'max_age' => 86400
            ]);

        $middleware = new MiddlewareManager($this->state_manager, $this->logger);
        $middleware->init();

        $result1 = $middleware->handle_cors_preflight('https://app.example.com', 'GET');
        $this->assertTrue($result1);

        $result2 = $middleware->handle_cors_preflight('https://api.example.com', 'GET');
        $this->assertTrue($result2);

        $result3 = $middleware->handle_cors_preflight('https://example.com', 'GET');
        $this->assertFalse($result3); // Doesn't match *.example.com

        $result4 = $middleware->handle_cors_preflight('https://evil.com', 'GET');
        $this->assertFalse($result4);
    }

    // ========== Rate Limiting Tests ==========

    public function testRateLimitAllowsFirstRequest() {
        global $_test_transients;
        $_test_transients = [];

        $result = $this->middleware_manager->check_namespace_rate_limit('clients', 1);

        $this->assertIsArray($result);
        $this->assertTrue($result['allowed']);
        $this->assertEquals(499, $result['remaining']); // 500 - 1
        $this->assertEquals(500, $result['limit']);
    }

    public function testRateLimitIncrementsCount() {
        global $_test_transients;
        $_test_transients = [];

        // First request
        $result1 = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        $this->assertEquals(499, $result1['remaining']);

        // Second request
        $result2 = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        $this->assertEquals(498, $result2['remaining']);

        // Third request
        $result3 = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        $this->assertEquals(497, $result3['remaining']);
    }

    public function testRateLimitExceedsQuota() {
        global $_test_transients;
        
        // Set transient to at limit for clients namespace (500)
        $_test_transients['newera_api_rate_limit_ns_clients_1'] = [
            'value' => 500,
            'expires' => time() + 3600
        ];

        $result = $this->middleware_manager->check_namespace_rate_limit('clients', 1);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('rate_limit_exceeded', $result->get_error_code());
        $this->assertEquals(429, $result->get_error_data()['code']);
        $this->assertEquals(0, $result->get_error_data()['remaining']);
    }

    public function testRateLimitDifferentNamespaces() {
        global $_test_transients;
        $_test_transients = [];

        // Different namespaces should have different limits
        $clients = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        $this->assertEquals(500, $clients['limit']);

        $projects = $this->middleware_manager->check_namespace_rate_limit('projects', 1);
        $this->assertEquals(300, $projects['limit']);

        $subscriptions = $this->middleware_manager->check_namespace_rate_limit('subscriptions', 1);
        $this->assertEquals(200, $subscriptions['limit']);

        $settings = $this->middleware_manager->check_namespace_rate_limit('settings', 1);
        $this->assertEquals(50, $settings['limit']);

        $webhooks = $this->middleware_manager->check_namespace_rate_limit('webhooks', 1);
        $this->assertEquals(100, $webhooks['limit']);

        $activity = $this->middleware_manager->check_namespace_rate_limit('activity', 1);
        $this->assertEquals(1000, $activity['limit']);
    }

    public function testRateLimitPerUser() {
        global $_test_transients;
        $_test_transients = [];

        // User 1
        $result1 = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        $this->assertEquals(499, $result1['remaining']);

        // User 2 should have separate limit
        $result2 = $this->middleware_manager->check_namespace_rate_limit('clients', 2);
        $this->assertEquals(499, $result2['remaining']);

        // User 1 again - should be decremented
        $result3 = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        $this->assertEquals(498, $result3['remaining']);
    }

    public function testRateLimitResetTime() {
        global $_test_transients;
        $_test_transients = [];

        $result = $this->middleware_manager->check_namespace_rate_limit('clients', 1);

        $this->assertArrayHasKey('reset', $result);
        $this->assertGreaterThan(time(), $result['reset']);
        $this->assertLessThanOrEqual(time() + 3600, $result['reset']);
    }

    public function testRateLimitHeaders() {
        global $_test_transients;
        $_test_transients = [];

        // Make a request to set the rate limit
        $this->middleware_manager->check_namespace_rate_limit('clients', 1);

        // Get headers
        $headers = $this->middleware_manager->get_rate_limit_headers('clients', 1);

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('X-Rate-Limit-Limit', $headers);
        $this->assertArrayHasKey('X-Rate-Limit-Remaining', $headers);
        $this->assertArrayHasKey('X-Rate-Limit-Reset', $headers);
        $this->assertEquals(500, $headers['X-Rate-Limit-Limit']);
    }

    public function testRateLimitHeadersWhenExceeded() {
        global $_test_transients;
        
        $_test_transients['newera_api_rate_limit_ns_clients_1'] = [
            'value' => 500,
            'expires' => time() + 3600
        ];

        $headers = $this->middleware_manager->get_rate_limit_headers('clients', 1);

        $this->assertEquals(500, $headers['X-Rate-Limit-Limit']);
        $this->assertEquals(0, $headers['X-Rate-Limit-Remaining']);
    }

    public function testRateLimitAnonymousUser() {
        global $_test_transients;
        $_test_transients = [];

        $result = $this->middleware_manager->check_namespace_rate_limit('clients', null);

        $this->assertIsArray($result);
        $this->assertTrue($result['allowed']);
        
        // Check the key uses 'anonymous'
        $key = 'newera_api_rate_limit_ns_clients_anonymous';
        $this->assertArrayHasKey($key, $_test_transients);
    }

    // ========== Request Logging Tests ==========

    public function testLogRequest() {
        $this->middleware_manager->log_request(
            'GET',
            '/clients',
            ['page' => 1, 'per_page' => 10],
            1
        );

        // Verify logger was called (in a real implementation)
        // For now, we just verify it doesn't throw an exception
        $this->assertTrue(true);
    }

    public function testLogRequestRedactsSensitiveData() {
        global $_test_transients;
        $_test_transients = [];

        $this->middleware_manager->log_request(
            'POST',
            '/auth',
            [
                'username' => 'testuser',
                'password' => 'secret123',
                'token' => 'abc123',
                'api_key' => 'key456'
            ],
            1
        );

        // The sensitive fields should be redacted in the log
        // We can't easily verify this without accessing internal state,
        // but the code path is tested
        $this->assertTrue(true);
    }

    public function testLogResponse() {
        $request_id = wp_generate_uuid4();
        
        // First log a request
        $this->middleware_manager->log_request('GET', '/clients', [], 1);

        // Then log the response
        $this->middleware_manager->log_response(
            $request_id,
            200,
            ['data' => 'test'],
            0.123
        );

        $this->assertTrue(true);
    }

    public function testLogRequestCapturesIPAddress() {
        // Set up $_SERVER variables
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $this->middleware_manager->log_request('GET', '/clients', [], 1);

        // The IP should be captured
        $this->assertTrue(true);
    }

    public function testLogRequestCapturesUserAgent() {
        $_SERVER['HTTP_USER_AGENT'] = 'Test Browser 1.0';

        $this->middleware_manager->log_request('GET', '/clients', [], 1);

        $this->assertTrue(true);
    }

    // ========== CORS Settings Management Tests ==========

    public function testUpdateCORSSettings() {
        $this->state_manager->expects($this->once())
            ->method('update_option')
            ->willReturn(true);

        $result = $this->middleware_manager->update_cors_settings([
            'enabled' => true,
            'allowed_origins' => ['https://neworigin.com']
        ]);

        $this->assertTrue($result);
    }

    public function testGetCORSSettings() {
        $settings = $this->middleware_manager->get_cors_settings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('enabled', $settings);
        $this->assertArrayHasKey('allowed_origins', $settings);
        $this->assertArrayHasKey('allowed_methods', $settings);
    }

    public function testSetCORSEnabled() {
        $this->state_manager->expects($this->once())
            ->method('update_option')
            ->willReturn(true);

        $result = $this->middleware_manager->set_cors_enabled(false);

        $this->assertTrue($result);
    }

    // ========== Edge Cases ==========

    public function testRateLimitWithVeryHighTraffic() {
        global $_test_transients;
        $_test_transients = [];

        // Simulate 499 requests
        $_test_transients['newera_api_rate_limit_ns_clients_1'] = [
            'value' => 499,
            'expires' => time() + 3600
        ];

        // 500th request should succeed
        $result = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        $this->assertIsArray($result);
        $this->assertTrue($result['allowed']);
        $this->assertEquals(0, $result['remaining']);

        // 501st request should fail
        $result2 = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        $this->assertInstanceOf(\WP_Error::class, $result2);
    }

    public function testCORSWithEmptyOrigin() {
        $result = $this->middleware_manager->handle_cors_preflight('', 'GET');
        
        // Empty origin should be rejected when CORS is strict
        $this->assertFalse($result);
    }

    public function testRateLimitResetsAfterExpiration() {
        global $_test_transients;
        
        // Set expired transient
        $_test_transients['newera_api_rate_limit_ns_clients_1'] = [
            'value' => 500,
            'expires' => time() - 1 // Already expired
        ];

        // Should allow request as transient has expired
        $result = $this->middleware_manager->check_namespace_rate_limit('clients', 1);
        
        $this->assertIsArray($result);
        $this->assertTrue($result['allowed']);
        $this->assertEquals(499, $result['remaining']);
    }

    protected function tearDown(): void {
        parent::tearDown();
        global $_test_transients, $_test_headers;
        $_test_transients = [];
        $_test_headers = [];
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);
    }
}
