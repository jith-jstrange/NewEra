<?php
/**
 * GraphQL Query Type
 *
 * Handles GraphQL Query resolvers
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
 * Query Type class
 */
class QueryType extends ObjectType {
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
            'name' => 'Query',
            'fields' => [
                'clients' => [
                    'type' => new ClientConnectionType($this->state_manager),
                    'description' => 'Get clients with pagination and filtering',
                    'args' => [
                        'first' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance(),
                            'description' => 'Number of items to return'
                        ],
                        'after' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance(),
                            'description' => 'Cursor for pagination'
                        ],
                        'last' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance(),
                            'description' => 'Number of items to return from end'
                        ],
                        'before' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance(),
                            'description' => 'Cursor for pagination'
                        ],
                        'filter' => [
                            'type' => new ClientFilterInputType(),
                            'description' => 'Filter clients'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_clients']
                ],
                'client' => [
                    'type' => new ClientType($this->state_manager),
                    'description' => 'Get a single client by ID',
                    'args' => [
                        'id' => [
                            'type' => \GraphQL\Type\Definition\NonNullType::getInstance(
                                \GraphQL\Type\Definition\IDType::getInstance()
                            ),
                            'description' => 'Client ID'
                        ]
                    ],
                    'resolve' => [$this, 'resolve_client']
                ],
                'projects' => [
                    'type' => new ProjectConnectionType($this->state_manager),
                    'description' => 'Get projects with pagination and filtering',
                    'args' => [
                        'first' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance()
                        ],
                        'after' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance()
                        ],
                        'last' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance()
                        ],
                        'before' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance()
                        ],
                        'filter' => [
                            'type' => new ProjectFilterInputType()
                        ]
                    ],
                    'resolve' => [$this, 'resolve_projects']
                ],
                'project' => [
                    'type' => new ProjectType($this->state_manager),
                    'description' => 'Get a single project by ID',
                    'args' => [
                        'id' => [
                            'type' => \GraphQL\Type\Definition\NonNullType::getInstance(
                                \GraphQL\Type\Definition\IDType::getInstance()
                            )
                        ]
                    ],
                    'resolve' => [$this, 'resolve_project']
                ],
                'subscriptions' => [
                    'type' => new SubscriptionConnectionType($this->state_manager),
                    'description' => 'Get subscriptions with pagination',
                    'args' => [
                        'first' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance()
                        ],
                        'after' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance()
                        ],
                        'last' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance()
                        ],
                        'before' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance()
                        ]
                    ],
                    'resolve' => [$this, 'resolve_subscriptions']
                ],
                'subscription' => [
                    'type' => new SubscriptionType($this->state_manager),
                    'description' => 'Get a single subscription by ID',
                    'args' => [
                        'id' => [
                            'type' => \GraphQL\Type\Definition\NonNullType::getInstance(
                                \GraphQL\Type\Definition\IDType::getInstance()
                            )
                        ]
                    ],
                    'resolve' => [$this, 'resolve_subscription']
                ],
                'settings' => [
                    'type' => new SettingsType($this->state_manager),
                    'description' => 'Get API settings',
                    'resolve' => [$this, 'resolve_settings']
                ],
                'webhooks' => [
                    'type' => new WebhookConnectionType($this->state_manager),
                    'description' => 'Get webhooks with pagination',
                    'args' => [
                        'first' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance()
                        ],
                        'after' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance()
                        ],
                        'last' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance()
                        ],
                        'before' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance()
                        ]
                    ],
                    'resolve' => [$this, 'resolve_webhooks']
                ],
                'webhook' => [
                    'type' => new WebhookType($this->state_manager),
                    'description' => 'Get a single webhook by ID',
                    'args' => [
                        'id' => [
                            'type' => \GraphQL\Type\Definition\NonNullType::getInstance(
                                \GraphQL\Type\Definition\IDType::getInstance()
                            )
                        ]
                    ],
                    'resolve' => [$this, 'resolve_webhook']
                ],
                'activity' => [
                    'type' => new ActivityConnectionType(),
                    'description' => 'Get activity logs with pagination',
                    'args' => [
                        'first' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance()
                        ],
                        'after' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance()
                        ],
                        'last' => [
                            'type' => \GraphQL\Type\Definition\IntType::getInstance()
                        ],
                        'before' => [
                            'type' => \GraphQL\Type\Definition\StringType::getInstance()
                        ],
                        'type' => [
                            'type' => new ActivityTypeEnum()
                        ]
                    ],
                    'resolve' => [$this, 'resolve_activity']
                ]
            ],
            'resolveField' => [$this, 'resolve_field']
        ];

        parent::__construct($config);
    }

    /**
     * Resolve clients query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_clients($value, $args, $context, ResolveInfo $info) {
        try {
            $first = $args['first'] ?? 10;
            $after = $args['after'] ?? null;
            $last = $args['last'] ?? null;
            $before = $args['before'] ?? null;
            $filter = $args['filter'] ?? [];

            // Convert GraphQL cursor to pagination offset
            $offset = $after ? $this->cursor_to_offset($after) : 0;
            
            if ($before) {
                $offset = max(0, $this->cursor_to_offset($before) - $first);
            }

            $clients_data = $this->state_manager->get_list('api_clients', [
                'page' => floor($offset / $first) + 1,
                'per_page' => $first,
                'filter' => $filter
            ]);

            return $this->format_connection_response($clients_data, $first);

        } catch (\Exception $e) {
            $this->logger->error('GraphQL clients query error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch clients');
        }
    }

    /**
     * Resolve client query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array|null
     */
    public function resolve_client($value, $args, $context, ResolveInfo $info) {
        try {
            return $this->state_manager->get_item('api_clients', $args['id']);
        } catch (\Exception $e) {
            $this->logger->error('GraphQL client query error', [
                'client_id' => $args['id'],
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch client');
        }
    }

    /**
     * Resolve projects query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_projects($value, $args, $context, ResolveInfo $info) {
        try {
            $first = $args['first'] ?? 10;
            $after = $args['after'] ?? null;
            $filter = $args['filter'] ?? [];

            $offset = $after ? $this->cursor_to_offset($after) : 0;

            $projects_data = $this->state_manager->get_list('api_projects', [
                'page' => floor($offset / $first) + 1,
                'per_page' => $first,
                'filter' => $filter
            ]);

            return $this->format_connection_response($projects_data, $first);

        } catch (\Exception $e) {
            $this->logger->error('GraphQL projects query error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch projects');
        }
    }

    /**
     * Resolve project query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array|null
     */
    public function resolve_project($value, $args, $context, ResolveInfo $info) {
        try {
            return $this->state_manager->get_item('api_projects', $args['id']);
        } catch (\Exception $e) {
            $this->logger->error('GraphQL project query error', [
                'project_id' => $args['id'],
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch project');
        }
    }

    /**
     * Resolve subscriptions query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_subscriptions($value, $args, $context, ResolveInfo $info) {
        try {
            $first = $args['first'] ?? 10;
            $after = $args['after'] ?? null;

            $offset = $after ? $this->cursor_to_offset($after) : 0;

            $subscriptions_data = $this->state_manager->get_list('api_subscriptions', [
                'page' => floor($offset / $first) + 1,
                'per_page' => $first
            ]);

            return $this->format_connection_response($subscriptions_data, $first);

        } catch (\Exception $e) {
            $this->logger->error('GraphQL subscriptions query error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch subscriptions');
        }
    }

    /**
     * Resolve subscription query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array|null
     */
    public function resolve_subscription($value, $args, $context, ResolveInfo $info) {
        try {
            return $this->state_manager->get_item('api_subscriptions', $args['id']);
        } catch (\Exception $e) {
            $this->logger->error('GraphQL subscription query error', [
                'subscription_id' => $args['id'],
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch subscription');
        }
    }

    /**
     * Resolve settings query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_settings($value, $args, $context, ResolveInfo $info) {
        try {
            return [
                'api_enabled' => true,
                'cors_enabled' => true,
                'rate_limiting_enabled' => true,
                'webhook_delivery_enabled' => true
            ];
        } catch (\Exception $e) {
            $this->logger->error('GraphQL settings query error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch settings');
        }
    }

    /**
     * Resolve webhooks query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_webhooks($value, $args, $context, ResolveInfo $info) {
        try {
            $first = $args['first'] ?? 10;
            $after = $args['after'] ?? null;

            $offset = $after ? $this->cursor_to_offset($after) : 0;

            $webhooks_data = $this->state_manager->get_list('api_webhooks', [
                'page' => floor($offset / $first) + 1,
                'per_page' => $first
            ]);

            return $this->format_connection_response($webhooks_data, $first);

        } catch (\Exception $e) {
            $this->logger->error('GraphQL webhooks query error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch webhooks');
        }
    }

    /**
     * Resolve webhook query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array|null
     */
    public function resolve_webhook($value, $args, $context, ResolveInfo $info) {
        try {
            return $this->state_manager->get_item('api_webhooks', $args['id']);
        } catch (\Exception $e) {
            $this->logger->error('GraphQL webhook query error', [
                'webhook_id' => $args['id'],
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch webhook');
        }
    }

    /**
     * Resolve activity query
     *
     * @param mixed $value
     * @param array $args
     * @param mixed $context
     * @param ResolveInfo $info
     * @return array
     */
    public function resolve_activity($value, $args, $context, ResolveInfo $info) {
        try {
            $first = $args['first'] ?? 10;
            $after = $args['after'] ?? null;

            // For now, return mock activity data
            // In a real implementation, this would fetch from activity logs
            $activity_data = [
                'items' => [
                    [
                        'id' => '1',
                        'type' => 'api_request',
                        'description' => 'GET /clients',
                        'timestamp' => current_time('mysql'),
                        'user_id' => 1
                    ]
                ],
                'total' => 1,
                'page_count' => 1
            ];

            return $this->format_connection_response($activity_data, $first);

        } catch (\Exception $e) {
            $this->logger->error('GraphQL activity query error', [
                'error' => $e->getMessage()
            ]);
            throw new \GraphQL\Error\Error('Failed to fetch activity');
        }
    }

    /**
     * Convert cursor to offset
     *
     * @param string $cursor
     * @return int
     */
    private function cursor_to_offset($cursor) {
        // Simple base64 encoding/decoding for cursors
        $decoded = base64_decode($cursor);
        if (preg_match('/^offset:(\d+)$/', $decoded, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    /**
     * Convert offset to cursor
     *
     * @param int $offset
     * @return string
     */
    private function offset_to_cursor($offset) {
        return base64_encode('offset:' . $offset);
    }

    /**
     * Format connection response
     *
     * @param array $data
     * @param int $first
     * @return array
     */
    private function format_connection_response($data, $first) {
        $items = $data['items'] ?? [];
        $total = $data['total'] ?? 0;
        
        $edges = [];
        foreach ($items as $item) {
            $offset = $data['page'] * $data['per_page'] - $data['per_page'] + array_search($item, $items, true);
            $edges[] = [
                'cursor' => $this->offset_to_cursor($offset),
                'node' => $item
            ];
        }

        return [
            'edges' => $edges,
            'nodes' => $items,
            'pageInfo' => [
                'hasNextPage' => ($data['page'] ?? 1) < ($data['page_count'] ?? 1),
                'hasPreviousPage' => ($data['page'] ?? 1) > 1,
                'startCursor' => !empty($edges) ? $edges[0]['cursor'] : null,
                'endCursor' => !empty($edges) ? end($edges)['cursor'] : null
            ],
            'totalCount' => $total
        ];
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