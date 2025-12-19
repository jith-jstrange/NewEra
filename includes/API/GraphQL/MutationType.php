<?php
/**
 * GraphQL Mutation Type
 *
 * Handles GraphQL Mutation resolvers
 *
 * @package Newera\API\GraphQL
 */

namespace Newera\API\GraphQL;

if (!defined('ABSPATH')) {
    exit;
}

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Mutation Type class
 */
class MutationType extends ObjectType {
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
     * Constructor
     *
     * @param \Newera\Core\StateManager $state_manager
     * @param \Newera\Core\Logger $logger
     */
    public function __construct($state_manager, $logger) {
        $this->state_manager = $state_manager;
        $this->logger = $logger;

        $config = [
            'name' => 'Mutation',
            'fields' => [
                'createClient' => [
                    'type' => new CreateClientPayloadType($this->state_manager),
                    'description' => 'Create a new client',
                    'args' => [
                        'input' => [
                            'type' => new CreateClientInputType(),
                            'description' => 'Client data'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_create_client']
                ],
                'updateClient' => [
                    'type' => new UpdateClientPayloadType($this->state_manager),
                    'description' => 'Update an existing client',
                    'args' => [
                        'input' => [
                            'type' => new UpdateClientInputType(),
                            'description' => 'Client data to update'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_update_client']
                ],
                'deleteClient' => [
                    'type' => new DeleteClientPayloadType(),
                    'description' => 'Delete a client',
                    'args' => [
                        'id' => [
                            'type' => \GraphQL\Type\Definition\NonNullType::getInstance(
                                \GraphQL\Type\Definition\IDType::getInstance()
                            ),
                            'description' => 'Client ID'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_delete_client']
                ],
                'createProject' => [
                    'type' => new CreateProjectPayloadType($this->state_manager),
                    'description' => 'Create a new project',
                    'args' => [
                        'input' => [
                            'type' => new CreateProjectInputType(),
                            'description' => 'Project data'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_create_project']
                ],
                'updateProject' => [
                    'type' => new UpdateProjectPayloadType($this->state_manager),
                    'description' => 'Update an existing project',
                    'args' => [
                        'input' => [
                            'type' => new UpdateProjectInputType(),
                            'description' => 'Project data to update'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_update_project']
                ],
                'deleteProject' => [
                    'type' => new DeleteProjectPayloadType(),
                    'description' => 'Delete a project',
                    'args' => [
                        'id' => [
                            'type' => \GraphQL\Type\Definition\NonNullType::getInstance(
                                \GraphQL\Type\Definition\IDType::getInstance()
                            ),
                            'description' => 'Project ID'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_delete_project']
                ],
                'createSubscription' => [
                    'type' => new CreateSubscriptionPayloadType($this->state_manager),
                    'description' => 'Create a new subscription',
                    'args' => [
                        'input' => [
                            'type' => new CreateSubscriptionInputType(),
                            'description' => 'Subscription data'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_create_subscription']
                ],
                'updateSettings' => [
                    'type' => new UpdateSettingsPayloadType(),
                    'description' => 'Update API settings',
                    'args' => [
                        'input' => [
                            'type' => new UpdateSettingsInputType(),
                            'description' => 'Settings data'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_update_settings']
                ],
                'createWebhook' => [
                    'type' => new CreateWebhookPayloadType($this->state_manager),
                    'description' => 'Create a new webhook',
                    'args' => [
                        'input' => [
                            'type' => new CreateWebhookInputType(),
                            'description' => 'Webhook data'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_create_webhook']
                ],
                'updateWebhook' => [
                    'type' => new UpdateWebhookPayloadType($this->state_manager),
                    'description' => 'Update an existing webhook',
                    'args' => [
                        'input' => [
                            'type' => new UpdateWebhookInputType(),
                            'description' => 'Webhook data to update'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_update_webhook']
                ],
                'deleteWebhook' => [
                    'type' => new DeleteWebhookPayloadType(),
                    'description' => 'Delete a webhook',
                    'args' => [
                        'id' => [
                            'type' => \GraphQL\Type\Definition\IDType>::getInstance()
                        ]
                    ],
                    'resolve' => [$this, 'resolve_delete_webhook']
                ]
            ],
            'resolveField' => [$this, 'resolve_field']
        ];

        parent::__construct($config);
    }

    /**
     * Resolve create client mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_create_client($value, $args, $context, ResolveInfo $info) {
        try {
            $input = $args['input'];
            
            $client_data = [
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'] ?? null,
                'company' => $input['company'] ?? null,
                'status' => $input['status'] ?? 'prospect',
                'notes' => $input['notes'] ?? null,
                'created_at' => current_time('mysql'),
                'created_by' => $context['user_id'] ?? 0
            ];

            $client_id = $this->state_manager->create_item('api_clients', $client_data);

            if (!$client_id) {
                throw new \GraphQL\Error\Error('Failed to create client');
            }

            $client = $this->state_manager->get_item('api_clients', $client_id);

            return [
                'client' => $client,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL create client mutation error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to create client: ' . $e->getMessage());
        }
    }

    /**
     * Resolve update client mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_update_client($value, $args, $context, ResolveInfo $info) {
        try {
            $input = $args['input'];
            
            $client_id = $input['id'];
            
            // Check if client exists
            $existing_client = $this->state_manager->get_item('api_clients', $client_id);
            if (!$existing_client) {
                throw new \GraphQL\Error\Error('Client not found');
            }

            $update_data = array_filter([
                'name' => $input['name'] ?? null,
                'email' => $input['email'] ?? null,
                'phone' => $input['phone'] ?? null,
                'company' => $input['company'] ?? null,
                'status' => $input['status'] ?? null,
                'notes' => $input['notes'] ?? null,
                'updated_at' => current_time('mysql'),
                'updated_by' => $context['user_id'] ?? 0
            ]);

            $success = $this->state_manager->update_item('api_clients', $client_id, $update_data);

            if (!$success) {
                throw new \GraphQL\Error\Error('Failed to update client');
            }

            $client = $this->state_manager->get_item('api_clients', $client_id);

            return [
                'client' => $client,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL update client mutation error', [
                'client_id' => $args['input']['id'] ?? null,
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to update client: ' . $e->getMessage());
        }
    }

    /**
     * Resolve delete client mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_delete_client($value, $args, $context, ResolveInfo $info) {
        try {
            $client_id = $args['id'];
            
            $success = $this->state_manager->delete_item('api_clients', $client_id);

            if (!$success) {
                throw new \GraphQL\Error\Error('Client not found');
            }

            return [
                'deleted' => true,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL delete client mutation error', [
                'client_id' => $args['id'],
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to delete client: ' . $e->getMessage());
        }
    }

    /**
     * Resolve create project mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_create_project($value, $args, $context, ResolveInfo $info) {
        try {
            $input = $args['input'];
            
            $project_data = [
                'name' => $input['name'],
                'description' => $input['description'] ?? null,
                'client_id' => $input['clientId'],
                'status' => $input['status'] ?? 'planning',
                'start_date' => $input['startDate'] ?? null,
                'end_date' => $input['endDate'] ?? null,
                'budget' => $input['budget'] ?? null,
                'created_at' => current_time('mysql'),
                'created_by' => $context['user_id'] ?? 0
            ];

            $project_id = $this->state_manager->create_item('api_projects', $project_data);

            if (!$project_id) {
                throw new \GraphQL\Error\Error('Failed to create project');
            }

            $project = $this->state_manager->get_item('api_projects', $project_id);

            return [
                'project' => $project,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL create project mutation error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to create project: ' . $e->getMessage());
        }
    }

    /**
     * Resolve update project mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_update_project($value, $args, $context, ResolveInfo $info) {
        try {
            $input = $args['input'];
            
            $project_id = $input['id'];
            
            // Check if project exists
            $existing_project = $this->state_manager->get_item('api_projects', $project_id);
            if (!$existing_project) {
                throw new \GraphQL\Error\Error('Project not found');
            }

            $update_data = array_filter([
                'name' => $input['name'] ?? null,
                'description' => $input['description'] ?? null,
                'client_id' => $input['clientId'] ?? null,
                'status' => $input['status'] ?? null,
                'start_date' => $input['startDate'] ?? null,
                'end_date' => $input['endDate'] ?? null,
                'budget' => $input['budget'] ?? null,
                'updated_at' => current_time('mysql'),
                'updated_by' => $context['user_id'] ?? 0
            ]);

            $success = $this->state_manager->update_item('api_projects', $project_id, $update_data);

            if (!$success) {
                throw new \GraphQL\Error\Error('Failed to update project');
            }

            $project = $this->state_manager->get_item('api_projects', $project_id);

            return [
                'project' => $project,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL update project mutation error', [
                'project_id' => $input['id'] ?? null,
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to update project: ' . $e->getMessage());
        }
    }

    /**
     * Resolve delete project mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_delete_project($value, $args, $context, ResolveInfo $info) {
        try {
            $project_id = $args['id'];
            
            $success = $this->state_manager->delete_item('api_projects', $project_id);

            if (!$success) {
                throw new \GraphQL\Error\Error('Project not found');
            }

            return [
                'deleted' => true,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL delete project mutation error', [
                'project_id' => $args['id'],
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to delete project: ' . $e->getMessage());
        }
    }

    /**
     * Resolve create subscription mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_create_subscription($value, $args, $context, ResolveInfo $info) {
        try {
            $input = $args['input'];
            
            $subscription_data = [
                'plan_name' => $input['planName'],
                'amount' => $input['amount'],
                'currency' => $input['currency'] ?? 'USD',
                'billing_cycle' => $input['billingCycle'],
                'status' => $input['status'] ?? 'active',
                'created_at' => current_time('mysql')
            ];

            $subscription_id = $this->state_manager->create_item('api_subscriptions', $subscription_data);

            if (!$subscription_id) {
                throw new \GraphQL\Error\Error('Failed to create subscription');
            }

            $subscription = $this->state_manager->get_item('api_subscriptions', $subscription_id);

            return [
                'subscription' => $subscription,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL create subscription mutation error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to create subscription: ' . $e->getMessage());
        }
    }

    /**
     * Resolve update settings mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_update_settings($value, $args, $context, ResolveInfo $info) {
        try {
            $input = $args['input'];
            
            $settings_data = array_filter([
                'api_enabled' => $input['apiEnabled'] ?? null,
                'cors_enabled' => $input['corsEnabled'] ?? null,
                'rate_limiting_enabled' => $input['rateLimitingEnabled'] ?? null,
                'webhook_delivery_enabled' => $input['webhookDeliveryEnabled'] ?? null
            ]);

            // Update settings through state manager
            foreach ($settings_data as $key => $value) {
                $this->state_manager->update_option('newera_api_' . $key, $value);
            }

            return [
                'settings' => array_merge([
                    'apiEnabled' => true,
                    'corsEnabled' => true,
                    'rateLimitingEnabled' => true,
                    'webhookDeliveryEnabled' => true
                ], $settings_data),
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL update settings mutation error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * Resolve create webhook mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_create_webhook($value, $args, $context, ResolveInfo $info) {
        try {
            $input = $args['input'];
            
            $webhook_data = [
                'url' => $input['url'],
                'events' => $input['events'] ?? [],
                'secret' => $input['secret'] ?? wp_generate_password(32, true, true),
                'active' => $input['active'] ?? true,
                'created_at' => current_time('mysql'),
                'created_by' => $context['user_id'] ?? 0
            ];

            $webhook_id = $this->state_manager->create_item('api_webhooks', $webhook_data);

            if (!$webhook_id) {
                throw new \GraphQL\Error\Error('Failed to create webhook');
            }

            $webhook = $this->state_manager->get_item('api_webhooks', $webhook_id);

            return [
                'webhook' => $webhook,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL create webhook mutation error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to create webhook: ' . $e->getMessage());
        }
    }

    /**
     * Resolve update webhook mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_update_webhook($value, $args, $context, ResolveInfo $info) {
        try {
            $input = $args['input'];
            
            $webhook_id = $input['id'];
            
            // Check if webhook exists
            $existing_webhook = $this->state_manager->get_item('api_webhooks', $webhook_id);
            if (!$existing_webhook) {
                throw new \GraphQL\Error\Error('Webhook not found');
            }

            $update_data = array_filter([
                'url' => $input['url'] ?? null,
                'events' => $input['events'] ?? null,
                'secret' => $input['secret'] ?? null,
                'active' => $input['active'] ?? null,
                'updated_at' => current_time('mysql'),
                'updated_by' => $context['user_id'] ?? 0
            ]);

            $success = $this->state_manager->update_item('api_webhooks', $webhook_id, $update_data);

            if (!$success) {
                throw new \GraphQL\Error\Error('Failed to update webhook');
            }

            $webhook = $this->state_manager->get_item('api_webhooks', $webhook_id);

            return [
                'webhook' => $webhook,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL update webhook mutation error', [
                'webhook_id' => $input['id'] ?? null,
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to update webhook: ' . $e->getMessage());
        }
    }

    /**
     * Resolve delete webhook mutation
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_delete_webhook($value, $args, $context, ResolveInfo $info) {
        try {
            $webhook_id = $args['id'];
            
            $success = $this->state_manager->delete_item('api_webhooks', $webhook_id);

            if (!$success) {
                throw new \GraphQL\Error\Error('Webhook not found');
            }

            return [
                'deleted' => true,
                'errors' => []
            ];

        } catch (\Exception $e) {
            $this->logger->error('GraphQL delete webhook mutation error', [
                'webhook_id' => $args['id'],
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to delete webhook: ' . $e->getMessage());
        }
    }

    /**
     * Resolve field (fallback)
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return mixed
     */
    public function resolve_field($value, $args, $context, ResolveInfo $info) {
        throw new \GraphQL\Error\Error('Field ' . $info->fieldName . ' not found');
    }
}