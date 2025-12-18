<?php
namespace {
    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request {
            private $method;
            private $route;
            private $params = [];
            
            public function __construct($method, $route) {
                $this->method = $method;
                $this->route = $route;
            }
            
            public function set_body_params($params) {
                $this->params = $params;
            }
            
            public function get_json_params() {
                return $this->params;
            }
        }
    }

    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response {
            private $data;
            private $status;
            
            public function __construct($data, $status = 200) {
                $this->data = $data;
                $this->status = $status;
            }
            
            public function get_data() {
                return $this->data;
            }
        }
    }
    
    if (!function_exists('register_rest_route')) {
        function register_rest_route($namespace, $route, $args) {}
    }

    if (!function_exists('rest_ensure_response')) {
        function rest_ensure_response($response) {
            if ($response instanceof WP_REST_Response) {
                return $response;
            }
            return new WP_REST_Response($response);
        }
    }

    if (!function_exists('current_user_can')) {
        function current_user_can($capability) {
            return true;
        }
    }

    if (!function_exists('get_current_user_id')) {
        function get_current_user_id() {
            return 1;
        }
    }

    if (!function_exists('current_time')) {
        function current_time($type) {
            return date('Y-m-d H:i:s');
        }
    }

    if (!function_exists('wp_upload_dir')) {
        function wp_upload_dir() {
            return ['basedir' => '/tmp/uploads'];
        }
    }

    if (!function_exists('wp_mkdir_p')) {
        function wp_mkdir_p($dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            return true;
        }
    }
    
    if (!function_exists('user_can')) {
        function user_can($user, $cap) { return true; }
    }
}

namespace Newera\Tests {

    use Newera\AI\CommandHandler;
    use Newera\Core\Logger;
    use Newera\Core\StateManager;
    use Newera\Modules\ModuleRegistry;

    if (!defined('ABSPATH')) {
        define('ABSPATH', '/tmp/');
    }

    class AICommandTest extends TestCase {
        private $commandHandler;
        private $stateManager;
        private $logger;
        private $moduleRegistry;
        
        protected function setUp(): void {
            parent::setUp();
            
            // Ensure constants
            if (!defined('NEWERA_PLUGIN_PATH')) {
                define('NEWERA_PLUGIN_PATH', '/home/engine/project/');
            }
            
            // Initialize mocks if not already
            if (!function_exists('get_option')) {
                 // TestCase.php mocks get_site_option but maybe not get_option
                 // StateManager uses get_option for 'newera_plugin_state'.
                 // We need to ensure StateManager works.
                 // StateManagerTest uses MockWPDB.
            }

            $this->stateManager = new StateManager();
            $this->logger = new Logger();
            $this->moduleRegistry = new ModuleRegistry($this->stateManager, $this->logger, NEWERA_PLUGIN_PATH . 'modules');
            
            $this->moduleRegistry->init();
            $this->moduleRegistry->boot();
            
            $this->commandHandler = new CommandHandler($this->stateManager, $this->logger, $this->moduleRegistry);
        }
        
        public function testHandleCommandAuth() {
            $request = new \WP_REST_Request('POST', '/newera/v1/ai-command');
            $request->set_body_params([
                'command' => 'Enable Google Sign-In',
                'module' => 'auth',
                'params' => [
                    'client_id' => 'abc',
                    'client_secret' => '123'
                ]
            ]);
            
            $response = $this->commandHandler->handle_command($request);
            $data = $response->get_data();
            
            $this->assertEquals('success', $data['status']);
            $this->assertEquals('Google Sign-In enabled successfully', $data['result']['message']);
        }
        
        public function testHandleCommandPayments() {
            $request = new \WP_REST_Request('POST', '/newera/v1/ai-command');
            $request->set_body_params([
                'command' => 'Create a plan',
                'module' => 'payments',
                'params' => [
                    'name' => 'Pro Plan',
                    'amount' => 99,
                    'currency' => 'USD'
                ]
            ]);
            
            $response = $this->commandHandler->handle_command($request);
            $data = $response->get_data();
            
            $this->assertEquals('success', $data['status']);
            $this->assertEquals('Plan created successfully', $data['result']['message']);
        }

        public function testHandleCommandAI() {
            $request = new \WP_REST_Request('POST', '/newera/v1/ai-command');
            $request->set_body_params([
                'command' => 'Set rate limit',
                'module' => 'AI',
                'params' => [
                    'limit' => 1000,
                    'window' => 'day'
                ]
            ]);
            
            $response = $this->commandHandler->handle_command($request);
            $data = $response->get_data();
            
            $this->assertEquals('success', $data['status']);
            $this->assertEquals("Rate limit set to 1000 per day", $data['result']['message']);
        }

        public function testHandleCommandInvalidModule() {
            $request = new \WP_REST_Request('POST', '/newera/v1/ai-command');
            $request->set_body_params([
                'command' => 'Do something',
                'module' => 'nonexistent_module'
            ]);
            
            $response = $this->commandHandler->handle_command($request);
            $data = $response->get_data();
            
            $this->assertEquals('error', $data['status']);
            $this->assertStringContainsString('Module \'nonexistent_module\' not found', $data['error']);
        }

        public function testHandleCommandInvalidCommand() {
            $request = new \WP_REST_Request('POST', '/newera/v1/ai-command');
            $request->set_body_params([
                'command' => 'Unknown Command',
                'module' => 'auth'
            ]);
            
            $response = $this->commandHandler->handle_command($request);
            $data = $response->get_data();
            
            $this->assertEquals('error', $data['status']);
            $this->assertStringContainsString('Command \'Unknown Command\' (method: unknownCommand) not supported', $data['error']);
        }

        public function testHandleCommandMissingParams() {
             $request = new \WP_REST_Request('POST', '/newera/v1/ai-command');
             $request->set_body_params([
                 'command' => 'Create a plan',
                 'module' => 'payments',
                 'params' => [
                     'amount' => 99 // Missing name
                 ]
             ]);
             
             $response = $this->commandHandler->handle_command($request);
             $data = $response->get_data();
             
             $this->assertEquals('error', $data['status']);
             $this->assertEquals('Plan name is required', $data['error']);
        }
    }
}
