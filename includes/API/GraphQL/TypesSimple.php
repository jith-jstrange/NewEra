<?php
/**
 * GraphQL Type Definitions
 *
 * Contains basic type definitions for GraphQL schema
 *
 * @package Newera\API\GraphQL
 */

namespace Newera\API\GraphQL;

if (!defined('ABSPATH')) {
    exit;
}

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\EnumType;

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