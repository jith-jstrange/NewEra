<?php
/**
 * GraphQL Type Definitions
 *
 * Contains type definitions for GraphQL schema
 *
 * @package Newera\API\GraphQL
 */

namespace Newera\API\GraphQL;

if (!defined('ABSPATH')) {
    exit;
}

use \GraphQL\Type\Definition\ObjectType;
use \GraphQL\Type\Definition\InputObjectType;
use \GraphQL\Type\Definition\EnumType;
use \GraphQL\Type\Definition\InterfaceType;

/**
 * Client Type
 */
class ClientType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'Client',
            'fields' => [
                'id' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(\GraphQL\Type\Definition\IDType::getInstance())],
                'name' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(\GraphQL\Type\Definition\StringType::getInstance())],
                'email' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(\GraphQL\Type\Definition\StringType::getInstance())],
                'phone' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'company' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'status' => ['type' => new ClientStatusEnum()],
                'notes' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'createdAt' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'updatedAt' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Client Connection Type
 */
class ClientConnectionType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'ClientConnection',
            'fields' => [
                'edges' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(new \GraphQL\Type\Definition\ListType(new ClientEdgeType()))],
                'nodes' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(new \GraphQL\Type\Definition\ListType(new ClientType($state_manager)))],
                'pageInfo' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(new PageInfoType())],
                'totalCount' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(\GraphQL\Type\Definition\IntType::getInstance())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Client Edge Type
 */
class ClientEdgeType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'ClientEdge',
            'fields' => [
                'cursor' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(\GraphQL\Type\Definition\StringType::getInstance())],
                'node' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(new ClientType($state_manager))]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Client Filter Input Type
 */
class ClientFilterInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'ClientFilter',
            'fields' => [
                'status' => ['type' => new ClientStatusEnum()],
                'search' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Client Status Enum
 */
class ClientStatusEnum extends EnumType {
    public function __construct() {
        $config = [
            'name' => 'ClientStatus',
            'values' => [
                'ACTIVE' => ['value' => 'active'],
                'INACTIVE' => ['value' => 'inactive'],
                'PROSPECT' => ['value' => 'prospect']
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Create Client Input Type
 */
class CreateClientInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'CreateClientInput',
            'fields' => [
                'name' => ['type' => \GraphQL\Type\Definition\NonNullType::getInstance(\GraphQL\Type\Definition\StringType::getInstance())],
                'email' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'phone' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'company' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'status' => ['type' => new ClientStatusEnum()],
                'notes' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Create Client Payload Type
 */
class CreateClientPayloadType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'CreateClientPayload',
            'fields' => [
                'client' => ['type' => new ClientType($state_manager)],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Update Client Input Type
 */
class UpdateClientInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'UpdateClientInput',
            'fields' => [
                'id' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'name' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'email' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'phone' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'company' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'status' => ['type' => new ClientStatusEnum()],
                'notes' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Update Client Payload Type
 */
class UpdateClientPayloadType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'UpdateClientPayload',
            'fields' => [
                'client' => ['type' => new ClientType($state_manager)],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Delete Client Payload Type
 */
class DeleteClientPayloadType extends ObjectType {
    public function __construct() {
        $config = [
            'name' => 'DeleteClientPayload',
            'fields' => [
                'deleted' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Project Type
 */
class ProjectType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'Project',
            'fields' => [
                'id' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'name' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'description' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'clientId' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'status' => ['type' => new ProjectStatusEnum()],
                'startDate' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'endDate' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'budget' => ['type' => \GraphQL\Type\Definition\FloatType::getInstance()],
                'createdAt' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'updatedAt' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Project Status Enum
 */
class ProjectStatusEnum extends EnumType {
    public function __construct() {
        $config = [
            'name' => 'ProjectStatus',
            'values' => [
                'PLANNING' => ['value' => 'planning'],
                'ACTIVE' => ['value' => 'active'],
                'PAUSED' => ['value' => 'paused'],
                'COMPLETED' => ['value' => 'completed'],
                'CANCELLED' => ['value' => 'cancelled']
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Create Project Input Type
 */
class CreateProjectInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'CreateProjectInput',
            'fields' => [
                'name' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'description' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'clientId' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'status' => ['type' => new ProjectStatusEnum()],
                'startDate' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'endDate' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'budget' => ['type' => \GraphQL\Type\Definition\FloatType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Update Project Input Type
 */
class UpdateProjectInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'UpdateProjectInput',
            'fields' => [
                'id' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'name' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'description' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'clientId' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'status' => ['type' => new ProjectStatusEnum()],
                'startDate' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'endDate' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'budget' => ['type' => \GraphQL\Type\Definition\FloatType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Create Project Payload Type
 */
class CreateProjectPayloadType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'CreateProjectPayload',
            'fields' => [
                'project' => ['type' => new ProjectType($state_manager)],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Update Project Payload Type
 */
class UpdateProjectPayloadType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'UpdateProjectPayload',
            'fields' => [
                'project' => ['type' => new ProjectType($state_manager)],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Delete Project Payload Type
 */
class DeleteProjectPayloadType extends ObjectType {
    public function __construct() {
        $config = [
            'name' => 'DeleteProjectPayload',
            'fields' => [
                'deleted' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Project Connection Type (simplified for now)
 */
class ProjectConnectionType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'ProjectConnection',
            'fields' => [
                'edges' => ['type' => \GraphQL\Type\Definition\ListType::getInstance(new \GraphQL\Type\Definition\StringType())],
                'nodes' => ['type' => \GraphQL\Type\Definition\ListType::getInstance(new \GraphQL\Type\Definition\StringType())],
                'pageInfo' => ['type' => new PageInfoType()],
                'totalCount' => ['type' => \GraphQL\Type\Definition\IntType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Project Filter Input Type (simplified for now)
 */
class ProjectFilterInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'ProjectFilter',
            'fields' => [
                'status' => ['type' => new ProjectStatusEnum()],
                'clientId' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Subscription Type (simplified for now)
 */
class SubscriptionType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'Subscription',
            'fields' => [
                'id' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'planName' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'amount' => ['type' => \GraphQL\Type\Definition\FloatType::getInstance()],
                'currency' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'billingCycle' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'status' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'createdAt' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Subscription Connection Type (simplified for now)
 */
class SubscriptionConnectionType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'SubscriptionConnection',
            'fields' => [
                'edges' => ['type' => \GraphQL\Type\Definition\ListType::getInstance(new \GraphQL\Type\Definition\StringType())],
                'nodes' => ['type' => \GraphQL\Type\Definition\ListType::getInstance(new \GraphQL\Type\Definition\StringType())],
                'pageInfo' => ['type' => new PageInfoType()],
                'totalCount' => ['type' => \GraphQL\Type\Definition\IntType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Create Subscription Input Type (simplified for now)
 */
class CreateSubscriptionInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'CreateSubscriptionInput',
            'fields' => [
                'planName' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'amount' => ['type' => \GraphQL\Type\Definition\FloatType::getInstance()],
                'currency' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'billingCycle' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'status' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Create Subscription Payload Type (simplified for now)
 */
class CreateSubscriptionPayloadType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'CreateSubscriptionPayload',
            'fields' => [
                'subscription' => ['type' => new SubscriptionType($state_manager)],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Settings Type (simplified for now)
 */
class SettingsType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'Settings',
            'fields' => [
                'apiEnabled' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'corsEnabled' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'rateLimitingEnabled' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'webhookDeliveryEnabled' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Update Settings Input Type (simplified for now)
 */
class UpdateSettingsInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'UpdateSettingsInput',
            'fields' => [
                'apiEnabled' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'corsEnabled' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'rateLimitingEnabled' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'webhookDeliveryEnabled' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Update Settings Payload Type (simplified for now)
 */
class UpdateSettingsPayloadType extends ObjectType {
    public function __construct() {
        $config = [
            'name' => 'UpdateSettingsPayload',
            'fields' => [
                'settings' => ['type' => new SettingsType(null)],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Webhook Type (simplified for now)
 */
class WebhookType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'Webhook',
            'fields' => [
                'id' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'url' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'events' => ['type' => new \GraphQL\Type\Definition\ListType(\GraphQL\Type\Definition\StringType::getInstance())],
                'active' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'createdAt' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Webhook Connection Type (simplified for now)
 */
class WebhookConnectionType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'WebhookConnection',
            'fields' => [
                'edges' => ['type' => \GraphQL\Type\Definition\ListType::getInstance(new \GraphQL\Type\Definition\StringType())],
                'nodes' => ['type' => \GraphQL\Type\Definition\ListType::getInstance(new \GraphQL\Type\Definition\StringType())],
                'pageInfo' => ['type' => new PageInfoType()],
                'totalCount' => ['type' => \GraphQL\Type\Definition\IntType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Create Webhook Input Type (simplified for now)
 */
class CreateWebhookInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'CreateWebhookInput',
            'fields' => [
                'url' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'events' => ['type' => new \GraphQL\Type\Definition\ListType(\GraphQL\Type\Definition\StringType::getInstance())],
                'secret' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'active' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Update Webhook Input Type (simplified for now)
 */
class UpdateWebhookInputType extends InputObjectType {
    public function __construct() {
        $config = [
            'name' => 'UpdateWebhookInput',
            'fields' => [
                'id' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'url' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'events' => ['type' => new \GraphQL\Type\Definition\ListType(\GraphQL\Type\Definition\StringType::getInstance())],
                'secret' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'active' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Create Webhook Payload Type (simplified for now)
 */
class CreateWebhookPayloadType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'CreateWebhookPayload',
            'fields' => [
                'webhook' => ['type' => new WebhookType($state_manager)],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Update Webhook Payload Type (simplified for now)
 */
class UpdateWebhookPayloadType extends ObjectType {
    public function __construct($state_manager) {
        $config = [
            'name' => 'UpdateWebhookPayload',
            'fields' => [
                'webhook' => ['type' => new WebhookType($state_manager)],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Delete Webhook Payload Type (simplified for now)
 */
class DeleteWebhookPayloadType extends ObjectType {
    public function __construct() {
        $config = [
            'name' => 'DeleteWebhookPayload',
            'fields' => [
                'deleted' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'errors' => ['type' => new \GraphQL\Type\Definition\ListType(new \GraphQL\Type\Definition\StringType())]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Activity Type (simplified for now)
 */
class ActivityType extends ObjectType {
    public function __construct() {
        $config = [
            'name' => 'Activity',
            'fields' => [
                'id' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()],
                'type' => ['type' => new ActivityTypeEnum()],
                'description' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'timestamp' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'userId' => ['type' => \GraphQL\Type\Definition\IDType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Activity Type Enum
 */
class ActivityTypeEnum extends EnumType {
    public function __construct() {
        $config = [
            'name' => 'ActivityType',
            'values' => [
                'API_REQUEST' => ['value' => 'api_request'],
                'WEBHOOK_DELIVERY' => ['value' => 'webhook_delivery'],
                'AUTHENTICATION' => ['value' => 'authentication'],
                'ERROR' => ['value' => 'error']
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Activity Connection Type (simplified for now)
 */
class ActivityConnectionType extends ObjectType {
    public function __construct() {
        $config = [
            'name' => 'ActivityConnection',
            'fields' => [
                'edges' => ['type' => \GraphQL\Type\Definition\ListType::getInstance(new \GraphQL\Type\Definition\StringType())],
                'nodes' => ['type' => \GraphQL\Type\Definition\ListType::getInstance(new \GraphQL\Type\Definition\StringType())],
                'pageInfo' => ['type' => new PageInfoType()],
                'totalCount' => ['type' => \GraphQL\Type\Definition\IntType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Page Info Type
 */
class PageInfoType extends ObjectType {
    public function __construct() {
        $config = [
            'name' => 'PageInfo',
            'fields' => [
                'hasNextPage' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'hasPreviousPage' => ['type' => \GraphQL\Type\Definition\BooleanType::getInstance()],
                'startCursor' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()],
                'endCursor' => ['type' => \GraphQL\Type\Definition\StringType::getInstance()]
            ]
        ];
        parent::__construct($config);
    }
}

/**
 * Root Value (Context for resolvers)
 */
class RootValue {
    private $auth_manager;
    private $state_manager;
    private $logger;
    private $user_id;

    public function __construct($auth_manager, $state_manager, $logger, $user_id = null) {
        $this->auth_manager = $auth_manager;
        $this->state_manager = $state_manager;
        $this->logger = $logger;
        $this->user_id = $user_id;
    }

    public function getUserId() {
        return $this->user_id;
    }
}