<?php
/**
 * API Documentation Generator
 *
 * Generates OpenAPI documentation for the REST API
 *
 * @package Newera\API\Docs
 */

namespace Newera\API\Docs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAPI Documentation Generator class
 */
class OpenAPIGenerator {
    /**
     * Generate OpenAPI specification
     *
     * @return array
     */
    public function generate_specification() {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Newera API',
                'version' => '1.0.0',
                'description' => 'REST API for Newera WordPress plugin',
                'contact' => [
                    'name' => 'API Support',
                    'email' => 'support@example.com'
                ]
            ],
            'servers' => [
                [
                    'url' => get_site_url() . '/wp-json/newera/v1',
                    'description' => 'Production server'
                ]
            ],
            'security' => [
                ['BearerAuth' => []]
            ],
            'components' => [
                'securitySchemes' => [
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ],
                'schemas' => $this->get_schemas(),
                'parameters' => $this->get_common_parameters()
            ],
            'paths' => $this->get_paths()
        ];
    }

    /**
     * Get schema definitions
     *
     * @return array
     */
    private function get_schemas() {
        return [
            'Client' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'name' => ['type' => 'string', 'example' => 'Acme Corp'],
                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'contact@acme.com'],
                    'phone' => ['type' => 'string', 'example' => '+1-555-0123'],
                    'company' => ['type' => 'string', 'example' => 'Acme Corporation'],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['active', 'inactive', 'prospect'],
                        'example' => 'active'
                    ],
                    'notes' => ['type' => 'string', 'example' => 'Important client'],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time']
                ],
                'required' => ['name', 'email', 'status']
            ],
            'Project' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'name' => ['type' => 'string', 'example' => 'Website Redesign'],
                    'description' => ['type' => 'string', 'example' => 'Complete website redesign'],
                    'client_id' => ['type' => 'integer', 'example' => 1],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['planning', 'active', 'paused', 'completed', 'cancelled'],
                        'example' => 'planning'
                    ],
                    'start_date' => ['type' => 'string', 'format' => 'date', 'example' => '2024-01-01'],
                    'end_date' => ['type' => 'string', 'format' => 'date', 'example' => '2024-06-30'],
                    'budget' => ['type' => 'number', 'example' => 15000.00],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time']
                ],
                'required' => ['name', 'client_id', 'status']
            ],
            'Subscription' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'plan_name' => ['type' => 'string', 'example' => 'Pro Plan'],
                    'amount' => ['type' => 'number', 'example' => 99.99],
                    'currency' => ['type' => 'string', 'example' => 'USD'],
                    'billing_cycle' => [
                        'type' => 'string',
                        'enum' => ['monthly', 'yearly'],
                        'example' => 'monthly'
                    ],
                    'status' => [
                        'type' => 'string',
                        'enum' => ['active', 'cancelled', 'past_due'],
                        'example' => 'active'
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time']
                ],
                'required' => ['plan_name', 'amount', 'currency', 'billing_cycle', 'status']
            ],
            'Settings' => [
                'type' => 'object',
                'properties' => [
                    'api_enabled' => ['type' => 'boolean', 'example' => true],
                    'cors_enabled' => ['type' => 'boolean', 'example' => true],
                    'rate_limiting_enabled' => ['type' => 'boolean', 'example' => true],
                    'webhook_delivery_enabled' => ['type' => 'boolean', 'example' => true]
                ]
            ],
            'Webhook' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'url' => ['type' => 'string', 'format' => 'uri', 'example' => 'https://example.com/webhook'],
                    'events' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'example' => ['client.created', 'project.updated']
                    ],
                    'secret' => ['type' => 'string', 'example' => 'webhook_secret_key'],
                    'active' => ['type' => 'boolean', 'example' => true],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time']
                ],
                'required' => ['url', 'events', 'secret', 'active']
            ],
            'Activity' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'example' => 1],
                    'type' => [
                        'type' => 'string',
                        'enum' => ['api_request', 'webhook_delivery', 'authentication', 'error'],
                        'example' => 'api_request'
                    ],
                    'description' => ['type' => 'string', 'example' => 'GET /clients'],
                    'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                    'user_id' => ['type' => 'integer', 'example' => 1]
                ]
            ],
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'example' => 'invalid_request'],
                    'message' => ['type' => 'string', 'example' => 'The request is invalid'],
                    'data' => ['type' => 'object']
                ],
                'required' => ['code', 'message']
            ]
        ];
    }

    /**
     * Get common parameters
     *
     * @return array
     */
    private function get_common_parameters() {
        return [
            'Page' => [
                'name' => 'page',
                'in' => 'query',
                'description' => 'Page number',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]
            ],
            'PerPage' => [
                'name' => 'per_page',
                'in' => 'query',
                'description' => 'Number of items per page',
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 10]
            ],
            'OrderBy' => [
                'name' => 'orderby',
                'in' => 'query',
                'description' => 'Field to order by',
                'schema' => ['type' => 'string', 'enum' => ['id', 'name', 'created_at', 'updated_at'], 'default' => 'id']
            ],
            'Order' => [
                'name' => 'order',
                'in' => 'query',
                'description' => 'Order direction',
                'schema' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc']
            ]
        ];
    }

    /**
     * Get API paths
     *
     * @return array
     */
    private function get_paths() {
        return [
            '/clients' => $this->get_clients_paths(),
            '/clients/{id}' => $this->get_client_detail_paths(),
            '/projects' => $this->get_projects_paths(),
            '/projects/{id}' => $this->get_project_detail_paths(),
            '/subscriptions' => $this->get_subscriptions_paths(),
            '/subscriptions/{id}' => $this->get_subscription_detail_paths(),
            '/settings' => $this->get_settings_paths(),
            '/webhooks' => $this->get_webhooks_paths(),
            '/webhooks/{id}' => $this->get_webhook_detail_paths(),
            '/activity' => $this->get_activity_paths()
        ];
    }

    /**
     * Get clients paths
     *
     * @return array
     */
    private function get_clients_paths() {
        return [
            'get' => [
                'summary' => 'Get clients',
                'description' => 'Retrieve a paginated list of clients',
                'operationId' => 'getClients',
                'parameters' => [
                    ['$ref' => '#/components/parameters/Page'],
                    ['$ref' => '#/components/parameters/PerPage'],
                    ['$ref' => '#/components/parameters/OrderBy'],
                    ['$ref' => '#/components/parameters/Order'],
                    [
                        'name' => 'status',
                        'in' => 'query',
                        'description' => 'Filter by status',
                        'schema' => ['type' => 'string', 'enum' => ['active', 'inactive', 'prospect']]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'headers' => [
                            'X-Total-Count' => ['description' => 'Total number of items'],
                            'X-Page-Count' => ['description' => 'Total number of pages']
                        ],
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Client']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'summary' => 'Create client',
                'description' => 'Create a new client',
                'operationId' => 'createClient',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'phone' => ['type' => 'string'],
                                    'company' => ['type' => 'string'],
                                    'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'prospect']],
                                    'notes' => ['type' => 'string']
                                ],
                                'required' => ['name', 'email']
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Client created successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Client']
                            ]
                        ]
                    ],
                    '400' => [
                        'description' => 'Invalid input',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Error']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get client detail paths
     *
     * @return array
     */
    private function get_client_detail_paths() {
        return [
            'get' => [
                'summary' => 'Get client',
                'description' => 'Retrieve a specific client by ID',
                'operationId' => 'getClient',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Client']
                            ]
                        ]
                    ],
                    '404' => [
                        'description' => 'Client not found',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Error']
                            ]
                        ]
                    ]
                ]
            ],
            'put' => [
                'summary' => 'Update client',
                'description' => 'Update an existing client',
                'operationId' => 'updateClient',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'email' => ['type' => 'string', 'format' => 'email'],
                                    'phone' => ['type' => 'string'],
                                    'company' => ['type' => 'string'],
                                    'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'prospect']],
                                    'notes' => ['type' => 'string']
                                ]
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Client updated successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Client']
                            ]
                        ]
                    ],
                    '400' => [
                        'description' => 'Invalid input',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Error']
                            ]
                        ]
                    ],
                    '404' => [
                        'description' => 'Client not found',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Error']
                            ]
                        ]
                    ]
                ]
            ],
            'delete' => [
                'summary' => 'Delete client',
                'description' => 'Delete a client',
                'operationId' => 'deleteClient',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'responses' => [
                    '204' => ['description' => 'Client deleted successfully'],
                    '404' => [
                        'description' => 'Client not found',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Error']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get projects paths
     *
     * @return array
     */
    private function get_projects_paths() {
        return [
            'get' => [
                'summary' => 'Get projects',
                'description' => 'Retrieve a paginated list of projects',
                'operationId' => 'getProjects',
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Project']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'summary' => 'Create project',
                'description' => 'Create a new project',
                'operationId' => 'createProject',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'client_id' => ['type' => 'integer'],
                                    'status' => ['type' => 'string', 'enum' => ['planning', 'active', 'paused', 'completed', 'cancelled']],
                                    'start_date' => ['type' => 'string', 'format' => 'date'],
                                    'end_date' => ['type' => 'string', 'format' => 'date'],
                                    'budget' => ['type' => 'number']
                                ],
                                'required' => ['name', 'client_id', 'status']
                            ]
                        ]
                    ]
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Project created successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Project']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get project detail paths
     *
     * @return array
     */
    private function get_project_detail_paths() {
        return [
            'get' => [
                'summary' => 'Get project',
                'description' => 'Retrieve a specific project by ID',
                'operationId' => 'getProject',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'integer']
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Project']
                            ]
                        ]
                    ]
                ]
            ],
            'put' => [
                'summary' => 'Update project',
                'description' => 'Update an existing project',
                'operationId' => 'updateProject',
                'responses' => [
                    '200' => [
                        'description' => 'Project updated successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Project']
                            ]
                        ]
                    ]
                ]
            ],
            'delete' => [
                'summary' => 'Delete project',
                'description' => 'Delete a project',
                'operationId' => 'deleteProject',
                'responses' => [
                    '204' => ['description' => 'Project deleted successfully']
                ]
            ]
        ];
    }

    /**
     * Get subscriptions paths
     *
     * @return array
     */
    private function get_subscriptions_paths() {
        return [
            'get' => [
                'summary' => 'Get subscriptions',
                'description' => 'Retrieve a paginated list of subscriptions',
                'operationId' => 'getSubscriptions',
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Subscription']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'summary' => 'Create subscription',
                'description' => 'Create a new subscription',
                'operationId' => 'createSubscription',
                'responses' => [
                    '201' => [
                        'description' => 'Subscription created successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Subscription']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get subscription detail paths
     *
     * @return array
     */
    private function get_subscription_detail_paths() {
        return [
            'get' => [
                'summary' => 'Get subscription',
                'description' => 'Retrieve a specific subscription by ID',
                'operationId' => 'getSubscription',
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Subscription']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get settings paths
     *
     * @return array
     */
    private function get_settings_paths() {
        return [
            'get' => [
                'summary' => 'Get settings',
                'description' => 'Retrieve API settings',
                'operationId' => 'getSettings',
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Settings']
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'summary' => 'Update settings',
                'description' => 'Update API settings',
                'operationId' => 'updateSettings',
                'responses' => [
                    '200' => [
                        'description' => 'Settings updated successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Settings']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get webhooks paths
     *
     * @return array
     */
    private function get_webhooks_paths() {
        return [
            'get' => [
                'summary' => 'Get webhooks',
                'description' => 'Retrieve a paginated list of webhooks',
                'operationId' => 'getWebhooks',
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Webhook']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'post' => [
                'summary' => 'Create webhook',
                'description' => 'Create a new webhook',
                'operationId' => 'createWebhook',
                'responses' => [
                    '201' => [
                        'description' => 'Webhook created successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Webhook']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get webhook detail paths
     *
     * @return array
     */
    private function get_webhook_detail_paths() {
        return [
            'get' => [
                'summary' => 'Get webhook',
                'description' => 'Retrieve a specific webhook by ID',
                'operationId' => 'getWebhook',
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Webhook']
                            ]
                        ]
                    ]
                ]
            ],
            'put' => [
                'summary' => 'Update webhook',
                'description' => 'Update an existing webhook',
                'operationId' => 'updateWebhook',
                'responses' => [
                    '200' => [
                        'description' => 'Webhook updated successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Webhook']
                            ]
                        ]
                    ]
                ]
            ],
            'delete' => [
                'summary' => 'Delete webhook',
                'description' => 'Delete a webhook',
                'operationId' => 'deleteWebhook',
                'responses' => [
                    '204' => ['description' => 'Webhook deleted successfully']
                ]
            ]
        ];
    }

    /**
     * Get activity paths
     *
     * @return array
     */
    private function get_activity_paths() {
        return [
            'get' => [
                'summary' => 'Get activity',
                'description' => 'Retrieve a paginated list of activity logs',
                'operationId' => 'getActivity',
                'parameters' => [
                    [
                        'name' => 'type',
                        'in' => 'query',
                        'description' => 'Filter by activity type',
                        'schema' => ['type' => 'string', 'enum' => ['api_request', 'webhook_delivery', 'authentication', 'error']]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Successful response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'array',
                                    'items' => ['$ref' => '#/components/schemas/Activity']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Get SDL (Schema Definition Language) for GraphQL
     *
     * @return string
     */
    public function get_graphql_sdl() {
        return <<<'SDL'
"""
The root query type
"""
type Query {
  """
  Get clients with pagination and filtering
  """
  clients(
    first: Int = 10
    after: String
    last: Int
    before: String
    filter: ClientFilter
  ): ClientConnection!

  """
  Get a single client by ID
  """
  client(id: ID!): Client

  """
  Get projects with pagination and filtering
  """
  projects(
    first: Int = 10
    after: String
    last: Int
    before: String
    filter: ProjectFilter
  ): ProjectConnection!

  """
  Get a single project by ID
  """
  project(id: ID!): Project

  """
  Get subscriptions with pagination
  """
  subscriptions(
    first: Int = 10
    after: String
    last: Int
    before: String
  ): SubscriptionConnection!

  """
  Get a single subscription by ID
  """
  subscription(id: ID!): Subscription

  """
  Get API settings
  """
  settings: Settings!

  """
  Get webhooks with pagination
  """
  webhooks(
    first: Int = 10
    after: String
    last: Int
    before: String
  ): WebhookConnection!

  """
  Get a single webhook by ID
  """
  webhook(id: ID!): Webhook

  """
  Get activity logs with pagination
  """
  activity(
    first: Int = 10
    after: String
    last: Int
    before: String
    type: ActivityType
  ): ActivityConnection!
}

"""
The root mutation type
"""
type Mutation {
  """
  Create a new client
  """
  createClient(input: CreateClientInput!): CreateClientPayload!

  """
  Update an existing client
  """
  updateClient(input: UpdateClientInput!): UpdateClientPayload!

  """
  Delete a client
  """
  deleteClient(id: ID!): DeleteClientPayload!

  """
  Create a new project
  """
  createProject(input: CreateProjectInput!): CreateProjectPayload!

  """
  Update an existing project
  """
  updateProject(input: UpdateProjectInput!): UpdateProjectPayload!

  """
  Delete a project
  """
  deleteProject(id: ID!): DeleteProjectPayload!

  """
  Create a new subscription
  """
  createSubscription(input: CreateSubscriptionInput!): CreateSubscriptionPayload!

  """
  Update API settings
  """
  updateSettings(input: UpdateSettingsInput!): UpdateSettingsPayload!

  """
  Create a new webhook
  """
  createWebhook(input: CreateWebhookInput!): CreateWebhookPayload!

  """
  Update an existing webhook
  """
  updateWebhook(input: UpdateWebhookInput!): UpdateWebhookPayload!

  """
  Delete a webhook
  """
  deleteWebhook(id: ID!): DeleteWebhookPayload!
}

"""
Client type
"""
type Client {
  id: ID!
  name: String!
  email: String!
  phone: String
  company: String
  status: ClientStatus!
  notes: String
  projects: ProjectConnection!
  createdAt: String!
  updatedAt: String!
}

"""
Connection for Client type
"""
type ClientConnection {
  edges: [ClientEdge!]!
  nodes: [Client!]!
  pageInfo: PageInfo!
  totalCount: Int!
}

"""
Edge for Client type
"""
type ClientEdge {
  cursor: String!
  node: Client!
}

"""
Filter clients
"""
input ClientFilter {
  status: ClientStatus
  search: String
}

"""
Client status enum
"""
enum ClientStatus {
  ACTIVE
  INACTIVE
  PROSPECT
}

"""
Create client input
"""
input CreateClientInput {
  name: String!
  email: String!
  phone: String
  company: String
  status: ClientStatus
  notes: String
}

"""
Create client payload
"""
type CreateClientPayload {
  client: Client
  errors: [String!]
}

"""
Update client input
"""
input UpdateClientInput {
  id: ID!
  name: String
  email: String
  phone: String
  company: String
  status: ClientStatus
  notes: String
}

"""
Update client payload
"""
type UpdateClientPayload {
  client: Client
  errors: [String!]
}

"""
Delete client payload
"""
type DeleteClientPayload {
  deleted: Boolean
  errors: [String!]
}

SDL;
    }

    /**
     * Generate documentation in various formats
     *
     * @param string $format Format to generate (json, html, markdown)
     * @return string
     */
    public function generate_documentation($format = 'json') {
        switch ($format) {
            case 'json':
                return json_encode($this->generate_specification(), JSON_PRETTY_PRINT);
            case 'sdl':
                return $this->get_graphql_sdl();
            case 'markdown':
                return $this->generate_markdown_documentation();
            case 'html':
                return $this->generate_html_documentation();
            default:
                return json_encode($this->generate_specification(), JSON_PRETTY_PRINT);
        }
    }

    /**
     * Generate markdown documentation
     *
     * @return string
     */
    private function generate_markdown_documentation() {
        return '# Newera API Documentation

## Overview

The Newera API provides comprehensive REST and GraphQL endpoints for managing clients, projects, subscriptions, settings, webhooks, and activity logs.

## Authentication

All API requests require authentication using JWT tokens. Include the token in the Authorization header:

```
Authorization: Bearer <your-jwt-token>
```

## Base URLs

- REST API: `{site_url}/wp-json/newera/v1`
- GraphQL: `{site_url}/newera/graphql`

## REST Endpoints

### Clients

- `GET /clients` - List clients
- `POST /clients` - Create client
- `GET /clients/{id}` - Get client
- `PUT /clients/{id}` - Update client
- `DELETE /clients/{id}` - Delete client

### Projects

- `GET /projects` - List projects
- `POST /projects` - Create project
- `GET /projects/{id}` - Get project
- `PUT /projects/{id}` - Update project
- `DELETE /projects/{id}` - Delete project

### Subscriptions

- `GET /subscriptions` - List subscriptions
- `POST /subscriptions` - Create subscription
- `GET /subscriptions/{id}` - Get subscription

### Settings

- `GET /settings` - Get settings
- `POST /settings` - Update settings

### Webhooks

- `GET /webhooks` - List webhooks
- `POST /webhooks` - Create webhook
- `GET /webhooks/{id}` - Get webhook
- `PUT /webhooks/{id}` - Update webhook
- `DELETE /webhooks/{id}` - Delete webhook

### Activity

- `GET /activity` - List activity logs

## GraphQL API

The GraphQL endpoint provides a more flexible way to query and manipulate data. See the schema definition for available types and operations.

## Rate Limiting

The API implements rate limiting to prevent abuse. Current limits:
- General endpoints: 100 requests per hour
- Settings: 50 requests per 5 minutes
- Webhooks: 100 requests per 10 minutes
- Activity: 1000 requests per 30 minutes

Rate limit headers are included in responses:
- `X-Rate-Limit-Limit`: Maximum requests allowed
- `X-Rate-Limit-Remaining`: Requests remaining in current window
- `X-Rate-Limit-Reset`: Timestamp when the limit resets

## Error Handling

Errors are returned with appropriate HTTP status codes and JSON responses:

```json
{
  "code": "error_code",
  "message": "Human-readable error message",
  "data": {}
}
```

## Webhooks

The API supports webhooks for real-time event notifications. Configure webhooks through the API to receive events like:
- `client.created`
- `client.updated`
- `project.created`
- `project.updated`
- `subscription.created`
- `subscription.updated`
- `webhook.delivery_failed`

## Support

For API support, contact support@example.com.
';
    }

    /**
     * Generate HTML documentation
     *
     * @return string
     */
    private function generate_html_documentation() {
        $markdown = $this->generate_markdown_documentation();
        
        // Simple markdown to HTML conversion
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Newera API Documentation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
        h1 { color: #333; border-bottom: 2px solid #333; }
        h2 { color: #666; border-bottom: 1px solid #ddd; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .endpoint { background: #e8f4fd; padding: 10px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>';

        // Convert markdown to basic HTML
        $html .= nl2br(htmlspecialchars($markdown));
        
        $html .= '</body></html>';
        
        return $html;
    }
}