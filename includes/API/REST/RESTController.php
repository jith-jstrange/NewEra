<?php
/**
 * REST API Controller
 *
 * Handles WordPress REST API endpoints
 *
 * @package Newera\API\REST
 */

namespace Newera\API\REST;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST Controller class
 */
class RESTController {
    /**
     * Auth Manager
     *
     * @var \Newera\API\Auth\AuthManager
     */
    private $auth_manager;

    /**
     * Middleware Manager
     *
     * @var \Newera\API\Middleware\MiddlewareManager
     */
    private $middleware_manager;

    /**
     * State Manager
     *
     * @var \Newera\Core\StateManager
     */
    private $state_manager;

    /**
     * Logger
     *
     * @var \Newera\Core\Logger
     */
    private $logger;

    /**
     * Endpoints registry
     *
     * @var array
     */
    private $endpoints = [];

    /**
     * Constructor
     *
     * @param \Newera\API\Auth\AuthManager $auth_manager
     * @param \Newera\API\Middleware\MiddlewareManager $middleware_manager
     * @param \Newera\Core\StateManager $state_manager
     * @param \Newera\Core\Logger $logger
     */
    public function __construct($auth_manager, $middleware_manager, $state_manager, $logger) {
        $this->auth_manager = $auth_manager;
        $this->middleware_manager = $middleware_manager;
        $this->state_manager = $state_manager;
        $this->logger = $logger;
    }

    /**
     * Initialize REST Controller
     */
    public function init() {
        $this->register_endpoints();
    }

    /**
     * Register API endpoints
     */
    private function register_endpoints() {
        $namespace = \Newera\API\APIManager::get_rest_namespace();

        // Clients endpoints
        $this->register_endpoint($namespace . '/clients', [
            'methods' => 'GET',
            'callback' => [$this, 'get_clients'],
            'permission_callback' => [$this, 'require_authentication'],
            'args' => $this->get_pagination_args()
        ]);

        $this->register_endpoint($namespace . '/clients/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_client'],
            'permission_callback' => [$this, 'require_authentication'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);

        $this->register_endpoint($namespace . '/clients', [
            'methods' => 'POST',
            'callback' => [$this, 'create_client'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['edit_others_posts'],
            'args' => $this->get_client_args()
        ]);

        $this->register_endpoint($namespace . '/clients/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$this, 'update_client'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['edit_others_posts'],
            'args' => $this->get_client_args(true)
        ]);

        $this->register_endpoint($namespace . '/clients/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_client'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['delete_others_posts']
        ]);

        // Projects endpoints
        $this->register_endpoint($namespace . '/projects', [
            'methods' => 'GET',
            'callback' => [$this, 'get_projects'],
            'permission_callback' => [$this, 'require_authentication'],
            'args' => array_merge($this->get_pagination_args(), $this->get_filter_args())
        ]);

        $this->register_endpoint($namespace . '/projects/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_project'],
            'permission_callback' => [$this, 'require_authentication'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true
                ]
            ]
        ]);

        $this->register_endpoint($namespace . '/projects', [
            'methods' => 'POST',
            'callback' => [$this, 'create_project'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['edit_posts'],
            'args' => $this->get_project_args()
        ]);

        $this->register_endpoint($namespace . '/projects/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$this, 'update_project'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['edit_posts'],
            'args' => $this->get_project_args(true)
        ]);

        $this->register_endpoint($namespace . '/projects/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_project'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['delete_posts']
        ]);

        // Subscriptions endpoints
        $this->register_endpoint($namespace . '/subscriptions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_subscriptions'],
            'permission_callback' => [$this, 'require_authentication']
        ]);

        $this->register_endpoint($namespace . '/subscriptions/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_subscription'],
            'permission_callback' => [$this, 'require_authentication']
        ]);

        $this->register_endpoint($namespace . '/subscriptions', [
            'methods' => 'POST',
            'callback' => [$this, 'create_subscription'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['manage_options'],
            'args' => $this->get_subscription_args()
        ]);

        // Settings endpoints
        $this->register_endpoint($namespace . '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['manage_options']
        ]);

        $this->register_endpoint($namespace . '/settings', [
            'methods' => ['POST', 'PUT', 'PATCH'],
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['manage_options'],
            'args' => $this->get_settings_args()
        ]);

        // Webhooks endpoints
        $this->register_endpoint($namespace . '/webhooks', [
            'methods' => 'GET',
            'callback' => [$this, 'get_webhooks'],
            'permission_callback' => [$this, 'require_authentication']
        ]);

        $this->register_endpoint($namespace . '/webhooks/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_webhook'],
            'permission_callback' => [$this, 'require_authentication']
        ]);

        $this->register_endpoint($namespace . '/webhooks', [
            'methods' => 'POST',
            'callback' => [$this, 'create_webhook'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['manage_options'],
            'args' => $this->get_webhook_args()
        ]);

        $this->register_endpoint($namespace . '/webhooks/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$this, 'update_webhook'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['manage_options'],
            'args' => $this->get_webhook_args(true)
        ]);

        $this->register_endpoint($namespace . '/webhooks/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_webhook'],
            'permission_callback' => [$this, 'require_permission'],
            'permission_args' => ['manage_options']
        ]);

        // Activity endpoints
        $this->register_endpoint($namespace . '/activity', [
            'methods' => 'GET',
            'callback' => [$this, 'get_activity'],
            'permission_callback' => [$this, 'require_authentication'],
            'args' => array_merge($this->get_pagination_args(), [
                'type' => [
                    'type' => 'string',
                    'enum' => ['api_request', 'webhook_delivery', 'authentication', 'error']
                ]
            ])
        ]);
    }

    /**
     * Register a REST route
     *
     * @param string $route
     * @param array $args
     */
    private function register_endpoint($route, $args) {
        register_rest_route($route, $args);
        $this->endpoints[$route] = $args;
    }

    /**
     * Get pagination arguments
     *
     * @return array
     */
    private function get_pagination_args() {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ],
            'orderby' => [
                'type' => 'string',
                'default' => 'id',
                'enum' => ['id', 'name', 'created_at', 'updated_at']
            ],
            'order' => [
                'type' => 'string',
                'default' => 'desc',
                'enum' => ['asc', 'desc']
            ]
        ];
    }

    /**
     * Get filter arguments
     *
     * @return array
     */
    private function get_filter_args() {
        return [
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'inactive', 'pending', 'archived']
            ],
            'client_id' => [
                'type' => 'integer'
            ],
            'search' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get client arguments
     *
     * @param bool $update Whether this is for update (optional fields)
     * @return array
     */
    private function get_client_args($update = false) {
        $required = !$update;

        return [
            'name' => [
                'type' => 'string',
                'required' => $required,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'email' => [
                'type' => 'string',
                'format' => 'email',
                'required' => $required,
                'sanitize_callback' => 'sanitize_email'
            ],
            'phone' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'company' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'inactive', 'prospect'],
                'default' => 'prospect'
            ],
            'notes' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field'
            ]
        ];
    }

    /**
     * Get project arguments
     *
     * @param bool $update Whether this is for update
     * @return array
     */
    private function get_project_args($update = false) {
        $required = !$update;

        return [
            'name' => [
                'type' => 'string',
                'required' => $required,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'description' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field'
            ],
            'client_id' => [
                'type' => 'integer',
                'required' => $required
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['planning', 'active', 'paused', 'completed', 'cancelled'],
                'default' => 'planning'
            ],
            'start_date' => [
                'type' => 'string',
                'format' => 'date'
            ],
            'end_date' => [
                'type' => 'string',
                'format' => 'date'
            ],
            'budget' => [
                'type' => 'number'
            ]
        ];
    }

    /**
     * Get subscription arguments
     *
     * @param bool $update Whether this is for update
     * @return array
     */
    private function get_subscription_args($update = false) {
        $required = !$update;

        return [
            'plan_name' => [
                'type' => 'string',
                'required' => $required,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'amount' => [
                'type' => 'number',
                'required' => $required
            ],
            'currency' => [
                'type' => 'string',
                'required' => $required,
                'default' => 'USD'
            ],
            'billing_cycle' => [
                'type' => 'string',
                'enum' => ['monthly', 'yearly'],
                'required' => $required
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['active', 'cancelled', 'past_due'],
                'default' => 'active'
            ]
        ];
    }

    /**
     * Get settings arguments
     *
     * @return array
     */
    private function get_settings_args() {
        return [
            'api_enabled' => [
                'type' => 'boolean',
                'default' => true
            ],
            'cors_enabled' => [
                'type' => 'boolean',
                'default' => true
            ],
            'rate_limiting_enabled' => [
                'type' => 'boolean',
                'default' => true
            ],
            'webhook_delivery_enabled' => [
                'type' => 'boolean',
                'default' => true
            ]
        ];
    }

    /**
     * Get webhook arguments
     *
     * @param bool $update Whether this is for update
     * @return array
     */
    private function get_webhook_args($update = false) {
        $required = !$update;

        return [
            'url' => [
                'type' => 'string',
                'format' => 'uri',
                'required' => $required
            ],
            'events' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['client.created', 'client.updated', 'project.created', 'project.updated', 'subscription.created', 'subscription.updated', 'webhook.delivery_failed']
                ]
            ],
            'secret' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'active' => [
                'type' => 'boolean',
                'default' => true
            ]
        ];
    }

    /**
     * Require authentication for endpoint
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function require_authentication($request) {
        $auth_result = $this->auth_manager->authenticate_request();
        
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }

        // Add user info to request
        $request->set_param('user_id', $auth_result['user']->ID);
        $request->set_param('user_info', $auth_result['user']);

        return true;
    }

    /**
     * Require permission for endpoint
     *
     * @param \WP_REST_Request $request
     * @param string $capability
     * @param int $object_id
     * @return bool|\WP_Error
     */
    public function require_permission($request, $capability, $object_id = null) {
        // First require authentication
        $auth_result = $this->require_authentication($request);
        
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }

        $user_id = $request->get_param('user_id');

        if (!$this->auth_manager->user_has_permission($user_id, $capability, $object_id)) {
            return new \WP_Error('insufficient_permissions', 'Insufficient permissions', [
                'required_capability' => $capability
            ]);
        }

        return true;
    }

    /**
     * Get clients
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_clients($request) {
        $start_time = microtime(true);

        try {
            $this->middleware_manager->log_request(
                'GET',
                '/clients',
                $request->get_query_params(),
                $request->get_param('user_id')
            );

            $namespace = 'clients';
            $rate_limit = $this->middleware_manager->check_namespace_rate_limit($namespace, $request->get_param('user_id'));
            
            if (is_wp_error($rate_limit)) {
                return $rate_limit;
            }

            // Get clients from state manager
            $clients = $this->state_manager->get_list('api_clients', [
                'page' => $request->get_param('page'),
                'per_page' => $request->get_param('per_page'),
                'orderby' => $request->get_param('orderby'),
                'order' => $request->get_param('order')
            ]);

            $execution_time = microtime(true) - $start_time;
            $this->middleware_manager->log_response(
                wp_generate_uuid4(),
                200,
                ['clients' => $clients],
                $execution_time
            );

            $response = new \WP_REST_Response($clients['items'], 200);
            
            // Add pagination headers
            $response->header('X-Total-Count', $clients['total']);
            $response->header('X-Page-Count', $clients['page_count']);

            // Add rate limit headers
            foreach ($this->middleware_manager->get_rate_limit_headers($namespace, $request->get_param('user_id')) as $header => $value) {
                $response->header($header, $value);
            }

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error getting clients', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new \WP_Error('internal_error', 'An internal error occurred', ['status' => 500]);
        }
    }

    /**
     * Get single client
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_client($request) {
        $start_time = microtime(true);

        try {
            $this->middleware_manager->log_request(
                'GET',
                '/clients/' . $request->get_param('id'),
                [],
                $request->get_param('user_id')
            );

            $client_id = $request->get_param('id');
            $client = $this->state_manager->get_item('api_clients', $client_id);

            if (!$client) {
                return new \WP_Error('client_not_found', 'Client not found', ['status' => 404]);
            }

            $execution_time = microtime(true) - $start_time;
            $this->middleware_manager->log_response(
                wp_generate_uuid4(),
                200,
                ['client' => $client],
                $execution_time
            );

            return new \WP_REST_Response($client, 200);

        } catch (\Exception $e) {
            $this->logger->error('Error getting client', [
                'client_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);

            return new \WP_Error('internal_error', 'An internal error occurred', ['status' => 500]);
        }
    }

    /**
     * Create client
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_client($request) {
        $start_time = microtime(true);

        try {
            $this->middleware_manager->log_request(
                'POST',
                '/clients',
                $request->get_params(),
                $request->get_param('user_id')
            );

            $client_data = [
                'name' => $request->get_param('name'),
                'email' => $request->get_param('email'),
                'phone' => $request->get_param('phone'),
                'company' => $request->get_param('company'),
                'status' => $request->get_param('status'),
                'notes' => $request->get_param('notes'),
                'created_at' => current_time('mysql'),
                'created_by' => $request->get_param('user_id')
            ];

            $client_id = $this->state_manager->create_item('api_clients', $client_data);

            if (!$client_id) {
                return new \WP_Error('client_creation_failed', 'Failed to create client', ['status' => 500]);
            }

            $client = $this->state_manager->get_item('api_clients', $client_id);

            $execution_time = microtime(true) - $start_time;
            $this->middleware_manager->log_response(
                wp_generate_uuid4(),
                201,
                ['client' => $client],
                $execution_time
            );

            return new \WP_REST_Response($client, 201);

        } catch (\Exception $e) {
            $this->logger->error('Error creating client', [
                'error' => $e->getMessage()
            ]);

            return new \WP_Error('internal_error', 'An internal error occurred', ['status' => 500]);
        }
    }

    /**
     * Update client
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function update_client($request) {
        $start_time = microtime(true);

        try {
            $this->middleware_manager->log_request(
                $request->get_method(),
                '/clients/' . $request->get_param('id'),
                $request->get_params(),
                $request->get_param('user_id')
            );

            $client_id = $request->get_param('id');
            
            // Check if client exists
            $existing_client = $this->state_manager->get_item('api_clients', $client_id);
            if (!$existing_client) {
                return new \WP_Error('client_not_found', 'Client not found', ['status' => 404]);
            }

            $update_data = array_filter([
                'name' => $request->get_param('name'),
                'email' => $request->get_param('email'),
                'phone' => $request->get_param('phone'),
                'company' => $request->get_param('company'),
                'status' => $request->get_param('status'),
                'notes' => $request->get_param('notes'),
                'updated_at' => current_time('mysql'),
                'updated_by' => $request->get_param('user_id')
            ]);

            $success = $this->state_manager->update_item('api_clients', $client_id, $update_data);

            if (!$success) {
                return new \WP_Error('client_update_failed', 'Failed to update client', ['status' => 500]);
            }

            $client = $this->state_manager->get_item('api_clients', $client_id);

            $execution_time = microtime(true) - $start_time;
            $this->middleware_manager->log_response(
                wp_generate_uuid4(),
                200,
                ['client' => $client],
                $execution_time
            );

            return new \WP_REST_Response($client, 200);

        } catch (\Exception $e) {
            $this->logger->error('Error updating client', [
                'client_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);

            return new \WP_Error('internal_error', 'An internal error occurred', ['status' => 500]);
        }
    }

    /**
     * Delete client
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_client($request) {
        $start_time = microtime(true);

        try {
            $this->middleware_manager->log_request(
                'DELETE',
                '/clients/' . $request->get_param('id'),
                [],
                $request->get_param('user_id')
            );

            $client_id = $request->get_param('id');
            
            $success = $this->state_manager->delete_item('api_clients', $client_id);

            if (!$success) {
                return new \WP_Error('client_not_found', 'Client not found', ['status' => 404]);
            }

            $execution_time = microtime(true) - $start_time;
            $this->middleware_manager->log_response(
                wp_generate_uuid4(),
                204,
                [],
                $execution_time
            );

            return new \WP_REST_Response(null, 204);

        } catch (\Exception $e) {
            $this->logger->error('Error deleting client', [
                'client_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);

            return new \WP_Error('internal_error', 'An internal error occurred', ['status' => 500]);
        }
    }

    // Stub methods for other endpoints (I'll implement these next)
    public function get_projects($request) { return new \WP_REST_Response([], 200); }
    public function get_project($request) { return new \WP_REST_Response([], 200); }
    public function create_project($request) { return new \WP_REST_Response([], 201); }
    public function update_project($request) { return new \WP_REST_Response([], 200); }
    public function delete_project($request) { return new \WP_REST_Response([], 204); }
    public function get_subscriptions($request) { return new \WP_REST_Response([], 200); }
    public function get_subscription($request) { return new \WP_REST_Response([], 200); }
    public function create_subscription($request) { return new \WP_REST_Response([], 201); }
    public function get_settings($request) { return new \WP_REST_Response([], 200); }
    public function update_settings($request) { return new \WP_REST_Response([], 200); }
    public function get_webhooks($request) { return new \WP_REST_Response([], 200); }
    public function get_webhook($request) { return new \WP_REST_Response([], 200); }
    public function create_webhook($request) { return new \WP_REST_Response([], 201); }
    public function update_webhook($request) { return new \WP_REST_Response([], 200); }
    public function delete_webhook($request) { return new \WP_REST_Response([], 204); }
    public function get_activity($request) { return new \WP_REST_Response([], 200); }
}