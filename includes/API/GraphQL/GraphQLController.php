<?php
/**
 * GraphQL Controller
 *
 * Handles GraphQL API endpoints and schema resolution
 *
 * @package Newera\API\GraphQL
 */

namespace Newera\API\GraphQL;

if (!defined('ABSPATH')) {
    exit;
}

use GraphQL\GraphQL;
use GraphQL\Error\Error;
use GraphQL\Error\Debug;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * GraphQL Controller class
 */
class GraphQLController {
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
     * Schema
     *
     * @var \GraphQL\Type\Schema
     */
    private $schema;

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
     * Initialize GraphQL Controller
     */
    public function init() {
        $this->build_schema();
    }

    /**
     * Register GraphQL endpoint
     */
    public function register_endpoint() {
        add_action('init', function() {
            add_rewrite_rule('^newera/graphql/?$', 'index.php?newera_graphql=1', 'top');
        });

        add_filter('query_vars', function($vars) {
            $vars[] = 'newera_graphql';
            return $vars;
        });

        add_action('template_redirect', function() {
            if (get_query_var('newera_graphql')) {
                $this->handle_graphql_request();
            }
        });

        // Add query var rewrite
        add_filter('rewrite_rules_array', function($rules) {
            $new_rules = [
                '^newera/graphql/?$' => 'index.php?newera_graphql=1'
            ];
            return $new_rules + $rules;
        });
    }

    /**
     * Handle GraphQL request
     */
    public function handle_graphql_request() {
        // Set content type
        header('Content-Type: application/json');

        // Enable CORS preflight handling
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->middleware_manager->handle_cors_preflight(
                get_http_header('Origin'),
                'POST',
                get_http_header('Access-Control-Request-Headers')
            );
            exit;
        }

        $start_time = microtime(true);

        try {
            // Get request data
            $request_data = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Error('Invalid JSON in request body');
            }

            $query = $request_data['query'] ?? '';
            $variables = $request_data['variables'] ?? [];
            $operation_name = $request_data['operationName'] ?? null;

            // Log request
            $this->middleware_manager->log_request(
                'POST',
                '/graphql',
                array_merge($request_data, ['variables' => '[REDACTED]']),
                null // We'll get user_id from auth
            );

            // Authenticate if required
            $auth_result = $this->authenticate_graphql_request();
            $user_id = null;

            if (is_wp_error($auth_result)) {
                // Allow introspection queries without authentication
                if (!$this->is_introspection_query($query)) {
                    $this->send_graphql_error($auth_result->get_error_message(), 401);
                    return;
                }
            } else {
                $user_id = $auth_result['user']->ID;
            }

            // Check rate limiting
            $rate_limit = $this->middleware_manager->check_namespace_rate_limit('graphql', $user_id);
            
            if (is_wp_error($rate_limit)) {
                $this->send_graphql_error($rate_limit->get_error_message(), 429);
                return;
            }

            // Execute query
            $result = GraphQL::executeQuery(
                $this->schema,
                $query,
                new RootValue($this->auth_manager, $this->state_manager, $this->logger, $user_id),
                null,
                $variables,
                $operation_name
            );

            // Add execution time to result
            $execution_time = microtime(true) - $start_time;
            $result_array = $result->toArray();
            $result_array['extensions']['executionTime'] = $execution_time;

            // Log response
            $this->middleware_manager->log_response(
                wp_generate_uuid4(),
                200,
                $result_array,
                $execution_time
            );

            // Set rate limit headers
            foreach ($this->middleware_manager->get_rate_limit_headers('graphql', $user_id) as $header => $value) {
                header($header . ': ' . $value);
            }

            // Send response
            echo json_encode($result_array, JSON_UNESCAPED_UNICODE);

        } catch (Error $e) {
            $this->logger->error('GraphQL error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->send_graphql_error($e->getMessage(), 500);
        } catch (\Exception $e) {
            $this->logger->error('GraphQL unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->send_graphql_error('Internal server error', 500);
        }

        exit;
    }

    /**
     * Authenticate GraphQL request
     *
     * @return array|\WP_Error
     */
    private function authenticate_graphql_request() {
        return $this->auth_manager->authenticate_request();
    }

    /**
     * Check if query is introspection query
     *
     * @param string $query
     * @return bool
     */
    private function is_introspection_query($query) {
        return stripos($query, '__schema') !== false || stripos($query, '__type') !== false;
    }

    /**
     * Send GraphQL error response
     *
     * @param string $message
     * @param int $status_code
     */
    private function send_graphql_error($message, $status_code = 400) {
        http_response_code($status_code);
        
        echo json_encode([
            'errors' => [
                ['message' => $message]
            ]
        ]);
    }

    /**
     * Build GraphQL schema
     */
    private function build_schema() {
        $this->schema = new \GraphQL\Type\Schema([
            'query' => new QueryType($this->state_manager, $this->logger),
            'mutation' => new MutationType($this->state_manager, $this->logger)
        ]);
    }

    /**
     * Get schema for documentation
     *
     * @return array
     */
    public function get_schema() {
        return [
            'query' => $this->schema->getQueryType()->getName(),
            'mutation' => $this->schema->getMutationType() ? $this->schema->getMutationType()->getName() : null,
            'types' => array_map(function($type) {
                return $type->name;
            }, $this->schema->getTypeMap())
        ];
    }

    /**
     * Generate SDL schema definition
     *
     * @return string
     */
    public function generate_sdl_schema() {
        // This is a simplified SDL generation
        // In a real implementation, you'd want more comprehensive schema introspection
        
        $sdl = '';
        
        // Query type
        $sdl .= "type Query {\n";
        $sdl .= "  clients(first: Int, after: String, last: Int, before: String, filter: ClientFilter): ClientConnection!\n";
        $sdl .= "  client(id: ID!): Client\n";
        $sdl .= "  projects(first: Int, after: String, last: Int, before: String, filter: ProjectFilter): ProjectConnection!\n";
        $sdl .= "  project(id: ID!): Project\n";
        $sdl .= "  subscriptions(first: Int, after: String, last: Int, before: String): SubscriptionConnection!\n";
        $sdl .= "  subscription(id: ID!): Subscription\n";
        $sdl .= "  settings: Settings!\n";
        $sdl .= "  webhooks(first: Int, after: String, last: Int, before: String): WebhookConnection!\n";
        $sdl .= "  webhook(id: ID!): Webhook\n";
        $sdl .= "  activity(first: Int, after: String, last: Int, before: String, type: ActivityType): ActivityConnection!\n";
        $sdl .= "}\n\n";
        
        // Mutation type
        $sdl .= "type Mutation {\n";
        $sdl .= "  createClient(input: CreateClientInput!): CreateClientPayload!\n";
        $sdl .= "  updateClient(input: UpdateClientInput!): UpdateClientPayload!\n";
        $sdl .= "  deleteClient(id: ID!): DeleteClientPayload!\n";
        $sdl .= "  createProject(input: CreateProjectInput!): CreateProjectPayload!\n";
        $sdl .= "  updateProject(input: UpdateProjectInput!): UpdateProjectPayload!\n";
        $sdl .= "  deleteProject(id: ID!): DeleteProjectPayload!\n";
        $sdl .= "  createSubscription(input: CreateSubscriptionInput!): CreateSubscriptionPayload!\n";
        $sdl .= "  updateSettings(input: UpdateSettingsInput!): UpdateSettingsPayload!\n";
        $sdl .= "  createWebhook(input: CreateWebhookInput!): CreateWebhookPayload!\n";
        $sdl .= "  updateWebhook(input: UpdateWebhookInput!): UpdateWebhookPayload!\n";
        $sdl .= "  deleteWebhook(id: ID!): DeleteWebhookPayload!\n";
        $sdl .= "}\n\n";
        
        // Type definitions
        $sdl .= "type Client {\n";
        $sdl .= "  id: ID!\n";
        $sdl .= "  name: String!\n";
        $sdl .= "  email: String!\n";
        $sdl .= "  phone: String\n";
        $sdl .= "  company: String\n";
        $sdl .= "  status: ClientStatus!\n";
        $sdl .= "  notes: String\n";
        $sdl .= "  projects: ProjectConnection!\n";
        $sdl .= "  createdAt: String!\n";
        $sdl .= "  updatedAt: String!\n";
        $sdl .= "}\n\n";
        
        $sdl .= "type ClientConnection {\n";
        $sdl .= "  edges: [ClientEdge!]!\n";
        $sdl .= "  nodes: [Client!]!\n";
        $sdl .= "  pageInfo: PageInfo!\n";
        $sdl .= "  totalCount: Int!\n";
        $sdl .= "}\n\n";
        
        $sdl .= "type ClientEdge {\n";
        $sdl .= "  cursor: String!\n";
        $sdl .= "  node: Client!\n";
        $sdl .= "}\n\n";
        
        // Continue with other types...
        $sdl .= "enum ClientStatus {\n";
        $sdl .= "  ACTIVE\n";
        $sdl .= "  INACTIVE\n";
        $sdl .= "  PROSPECT\n";
        $sdl .= "}\n\n";
        
        return $sdl;
    }
}