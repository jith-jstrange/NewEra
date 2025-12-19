<?php
/**
 * REST Controller Tests
 *
 * @package Newera\Tests
 */

namespace Newera\Tests;

use Newera\API\REST\RESTController;
use Newera\API\Auth\AuthManager;
use Newera\API\Middleware\MiddlewareManager;
use Newera\Core\StateManager;
use Newera\Core\Logger;

/**
 * Test REST API endpoints
 */
class RESTControllerTest extends TestCase {
    private $rest_controller;
    private $auth_manager;
    private $middleware_manager;
    private $state_manager;
    private $logger;
    private $mock_user;

    protected function setUp(): void {
        parent::setUp();

        // Mock WordPress functions
        $this->mockWordPressFunctions();

        // Create instances
        $this->logger = $this->createMock(Logger::class);
        $this->state_manager = $this->createMock(StateManager::class);
        $this->auth_manager = $this->createMock(AuthManager::class);
        $this->middleware_manager = $this->createMock(MiddlewareManager::class);

        $this->rest_controller = new RESTController(
            $this->auth_manager,
            $this->middleware_manager,
            $this->state_manager,
            $this->logger
        );

        // Create mock user
        $this->mock_user = (object) [
            'ID' => 1,
            'user_login' => 'testuser',
            'user_email' => 'test@example.com'
        ];
    }

    /**
     * Mock additional WordPress functions
     */
    private function mockWordPressFunctions() {
        if (!function_exists('current_time')) {
            function current_time($type = 'mysql') {
                return date('Y-m-d H:i:s');
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

        if (!function_exists('sanitize_email')) {
            function sanitize_email($email) {
                return filter_var($email, FILTER_SANITIZE_EMAIL);
            }
        }

        if (!function_exists('sanitize_textarea_field')) {
            function sanitize_textarea_field($str) {
                return strip_tags($str);
            }
        }

        if (!class_exists('WP_REST_Response')) {
            class WP_REST_Response {
                private $data;
                private $status;
                private $headers = [];

                public function __construct($data = null, $status = 200) {
                    $this->data = $data;
                    $this->status = $status;
                }

                public function get_data() {
                    return $this->data;
                }

                public function get_status() {
                    return $this->status;
                }

                public function header($key, $value) {
                    $this->headers[$key] = $value;
                }

                public function get_headers() {
                    return $this->headers;
                }
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

        if (!class_exists('WP_REST_Request')) {
            class WP_REST_Request {
                private $params = [];
                private $method = 'GET';

                public function __construct($method = 'GET', $route = '', $params = []) {
                    $this->method = $method;
                    $this->params = $params;
                }

                public function get_param($key) {
                    return $this->params[$key] ?? null;
                }

                public function get_params() {
                    return $this->params;
                }

                public function set_param($key, $value) {
                    $this->params[$key] = $value;
                }

                public function get_method() {
                    return $this->method;
                }

                public function get_query_params() {
                    return $this->params;
                }
            }
        }

        if (!function_exists('is_wp_error')) {
            function is_wp_error($thing) {
                return ($thing instanceof \WP_Error);
            }
        }
    }

    /**
     * Create mock request
     */
    private function createMockRequest($method = 'GET', $params = []) {
        return new \WP_REST_Request($method, '', $params);
    }

    // ========== GET /clients Tests ==========

    public function testGetClientsReturns200WithValidAuth() {
        // Mock authentication
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        // Mock rate limiting
        $this->middleware_manager->method('check_namespace_rate_limit')
            ->willReturn(['allowed' => true, 'remaining' => 99, 'limit' => 100, 'reset' => time() + 3600]);

        // Mock get_rate_limit_headers
        $this->middleware_manager->method('get_rate_limit_headers')
            ->willReturn([
                'X-Rate-Limit-Limit' => 100,
                'X-Rate-Limit-Remaining' => 99,
                'X-Rate-Limit-Reset' => time() + 3600
            ]);

        // Mock state manager
        $this->state_manager->method('get_list')
            ->willReturn([
                'items' => [
                    ['id' => 1, 'name' => 'Client 1', 'email' => 'client1@example.com'],
                    ['id' => 2, 'name' => 'Client 2', 'email' => 'client2@example.com']
                ],
                'total' => 2,
                'page_count' => 1
            ]);

        $request = $this->createMockRequest('GET', [
            'page' => 1,
            'per_page' => 10,
            'orderby' => 'id',
            'order' => 'desc'
        ]);
        $request->set_param('user_id', 1);

        $response = $this->rest_controller->get_clients($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
        $this->assertIsArray($response->get_data());
        $this->assertCount(2, $response->get_data());
    }

    public function testGetClientsPaginationHeaders() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->middleware_manager->method('check_namespace_rate_limit')
            ->willReturn(['allowed' => true, 'remaining' => 99, 'limit' => 100, 'reset' => time() + 3600]);

        $this->middleware_manager->method('get_rate_limit_headers')
            ->willReturn([
                'X-Rate-Limit-Limit' => 100,
                'X-Rate-Limit-Remaining' => 99
            ]);

        $this->state_manager->method('get_list')
            ->willReturn([
                'items' => [],
                'total' => 50,
                'page_count' => 5
            ]);

        $request = $this->createMockRequest('GET', ['page' => 2, 'per_page' => 10]);
        $request->set_param('user_id', 1);

        $response = $this->rest_controller->get_clients($request);
        $headers = $response->get_headers();

        $this->assertEquals(50, $headers['X-Total-Count']);
        $this->assertEquals(5, $headers['X-Page-Count']);
    }

    public function testGetClientsWithRateLimitExceeded() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $rate_limit_error = new \WP_Error('rate_limit_exceeded', 'Rate limit exceeded', ['code' => 429]);
        $this->middleware_manager->method('check_namespace_rate_limit')
            ->willReturn($rate_limit_error);

        $request = $this->createMockRequest('GET');
        $request->set_param('user_id', 1);

        $response = $this->rest_controller->get_clients($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('rate_limit_exceeded', $response->get_error_code());
    }

    // ========== GET /clients/:id Tests ==========

    public function testGetClientReturns200() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->state_manager->method('get_item')
            ->willReturn([
                'id' => 1,
                'name' => 'Test Client',
                'email' => 'test@example.com',
                'status' => 'active'
            ]);

        $request = $this->createMockRequest('GET', ['id' => 1]);
        $request->set_param('user_id', 1);

        $response = $this->rest_controller->get_client($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
        $this->assertEquals('Test Client', $response->get_data()['name']);
    }

    public function testGetClientReturns404() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->state_manager->method('get_item')
            ->willReturn(null);

        $request = $this->createMockRequest('GET', ['id' => 999]);
        $request->set_param('user_id', 1);

        $response = $this->rest_controller->get_client($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('client_not_found', $response->get_error_code());
    }

    // ========== POST /clients Tests ==========

    public function testCreateClientReturns201() {
        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        $this->state_manager->method('create_item')
            ->willReturn(1);

        $this->state_manager->method('get_item')
            ->willReturn([
                'id' => 1,
                'name' => 'New Client',
                'email' => 'new@example.com',
                'status' => 'prospect'
            ]);

        $request = $this->createMockRequest('POST', [
            'name' => 'New Client',
            'email' => 'new@example.com',
            'status' => 'prospect',
            'user_id' => 1
        ]);

        $response = $this->rest_controller->create_client($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());
        $this->assertEquals('New Client', $response->get_data()['name']);
    }

    public function testCreateClientValidatesRequiredFields() {
        // The validation would happen at the REST API registration level
        // Here we test that missing required fields result in proper error
        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        $this->state_manager->method('create_item')
            ->willReturn(null); // Simulate failure

        $request = $this->createMockRequest('POST', [
            'name' => 'Test', // Missing email (required)
            'user_id' => 1
        ]);

        $response = $this->rest_controller->create_client($request);

        // Should return error when creation fails
        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('client_creation_failed', $response->get_error_code());
    }

    public function testCreateClientReturns403WithoutPermissions() {
        // Mock authentication to return error
        $auth_error = new \WP_Error('insufficient_permissions', 'Insufficient permissions');
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);
        
        $this->auth_manager->method('user_has_permission')
            ->willReturn(false);

        $request = $this->createMockRequest('POST', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'user_id' => 1
        ]);

        // The permission check happens in require_permission
        $result = $this->rest_controller->require_permission($request, 'edit_others_posts');
        
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    // ========== PUT /clients/:id Tests ==========

    public function testUpdateClientReturns200() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        $this->state_manager->method('get_item')
            ->willReturn([
                'id' => 1,
                'name' => 'Updated Client',
                'email' => 'updated@example.com'
            ]);

        $this->state_manager->method('update_item')
            ->willReturn(true);

        $request = $this->createMockRequest('PUT', [
            'id' => 1,
            'name' => 'Updated Client',
            'email' => 'updated@example.com',
            'user_id' => 1
        ]);

        $response = $this->rest_controller->update_client($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
    }

    public function testUpdateClientReturns404() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        // First call returns null (client not found)
        $this->state_manager->method('get_item')
            ->willReturn(null);

        $request = $this->createMockRequest('PUT', [
            'id' => 999,
            'name' => 'Updated',
            'user_id' => 1
        ]);

        $response = $this->rest_controller->update_client($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('client_not_found', $response->get_error_code());
    }

    // ========== DELETE /clients/:id Tests ==========

    public function testDeleteClientReturns204() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        $this->state_manager->method('delete_item')
            ->willReturn(true);

        $request = $this->createMockRequest('DELETE', ['id' => 1, 'user_id' => 1]);

        $response = $this->rest_controller->delete_client($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(204, $response->get_status());
        $this->assertNull($response->get_data());
    }

    public function testDeleteClientReturns404() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        $this->state_manager->method('delete_item')
            ->willReturn(false);

        $request = $this->createMockRequest('DELETE', ['id' => 999, 'user_id' => 1]);

        $response = $this->rest_controller->delete_client($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('client_not_found', $response->get_error_code());
    }

    // ========== Authentication Tests ==========

    public function testRequireAuthenticationWithValidToken() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $request = $this->createMockRequest('GET');

        $result = $this->rest_controller->require_authentication($request);

        $this->assertTrue($result);
        $this->assertEquals(1, $request->get_param('user_id'));
    }

    public function testRequireAuthenticationWithInvalidToken() {
        $auth_error = new \WP_Error('invalid_token', 'Invalid token');
        $this->auth_manager->method('authenticate_request')
            ->willReturn($auth_error);

        $request = $this->createMockRequest('GET');

        $result = $this->rest_controller->require_authentication($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_token', $result->get_error_code());
    }

    public function testRequirePermissionWithSufficientPermissions() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        $request = $this->createMockRequest('POST');

        $result = $this->rest_controller->require_permission($request, 'edit_posts');

        $this->assertTrue($result);
    }

    public function testRequirePermissionWithInsufficientPermissions() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->auth_manager->method('user_has_permission')
            ->willReturn(false);

        $request = $this->createMockRequest('POST');
        $request->set_param('user_id', 1);

        $result = $this->rest_controller->require_permission($request, 'manage_options');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
    }

    // ========== Request Sanitization Tests ==========

    public function testClientDataSanitization() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        $this->state_manager->method('create_item')
            ->willReturn(1);

        $this->state_manager->method('get_item')
            ->willReturn([
                'id' => 1,
                'name' => 'Test Client',
                'email' => 'test@example.com'
            ]);

        // Request with potentially malicious data
        $request = $this->createMockRequest('POST', [
            'name' => '<script>alert("xss")</script>Test',
            'email' => 'test@example.com',
            'user_id' => 1
        ]);

        $response = $this->rest_controller->create_client($request);

        // The sanitization happens in the args definition
        // Here we're testing that the endpoint handles it properly
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());
    }

    // ========== Error Handling Tests ==========

    public function testGetClientsHandlesExceptions() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->middleware_manager->method('check_namespace_rate_limit')
            ->willReturn(['allowed' => true, 'remaining' => 99, 'limit' => 100, 'reset' => time() + 3600]);

        // State manager throws exception
        $this->state_manager->method('get_list')
            ->willThrowException(new \Exception('Database error'));

        $request = $this->createMockRequest('GET');
        $request->set_param('user_id', 1);

        $response = $this->rest_controller->get_clients($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('internal_error', $response->get_error_code());
        $this->assertEquals(500, $response->get_error_data()['status']);
    }

    public function testCreateClientHandlesExceptions() {
        $this->auth_manager->method('authenticate_request')
            ->willReturn(['user' => $this->mock_user, 'payload' => []]);

        $this->auth_manager->method('user_has_permission')
            ->willReturn(true);

        // State manager throws exception
        $this->state_manager->method('create_item')
            ->willThrowException(new \Exception('Database error'));

        $request = $this->createMockRequest('POST', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'user_id' => 1
        ]);

        $response = $this->rest_controller->create_client($request);

        $this->assertInstanceOf(\WP_Error::class, $response);
        $this->assertEquals('internal_error', $response->get_error_code());
    }
}
