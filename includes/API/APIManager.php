<?php
/**
 * API Manager - Core API orchestration
 *
 * @package Newera\API
 */

namespace Newera\API;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Manager class
 */
class APIManager {
    /**
     * API Version
     */
    const API_VERSION = 'v1';

    /**
     * REST Namespace
     */
    const REST_NAMESPACE = 'newera/' . self::API_VERSION;

    /**
     * Logger instance
     *
     * @var \Newera\Core\Logger
     */
    private $logger;

    /**
     * State Manager instance
     *
     * @var \Newera\Core\StateManager
     */
    private $state_manager;

    /**
     * REST API Controller
     *
     * @var \Newera\API\REST\RESTController
     */
    private $rest_controller;

    /**
     * GraphQL Controller
     *
     * @var \Newera\API\GraphQL\GraphQLController
     */
    private $graphql_controller;

    /**
     * Authentication Manager
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
     * Webhook Manager
     *
     * @var \Newera\API\Webhooks\WebhookManager
     */
    private $webhook_manager;

    /**
     * Initialize API Manager
     */
    public function init() {
        // Initialize core dependencies
        $this->logger = new \Newera\Core\Logger();
        $this->state_manager = new \Newera\Core\StateManager();
        $this->state_manager->init();

        // Initialize API components
        $this->init_auth_manager();
        $this->init_middleware_manager();
        $this->init_rest_controller();
        $this->init_graphql_controller();
        $this->init_webhook_manager();

        // Hook into WordPress
        $this->register_hooks();

        $this->logger->info('API Manager initialized successfully');
    }

    /**
     * Initialize Authentication Manager
     */
    private function init_auth_manager() {
        $this->auth_manager = new \Newera\API\Auth\AuthManager($this->state_manager, $this->logger);
        $this->auth_manager->init();
    }

    /**
     * Initialize Middleware Manager
     */
    private function init_middleware_manager() {
        $this->middleware_manager = new \Newera\API\Middleware\MiddlewareManager($this->state_manager, $this->logger);
        $this->middleware_manager->init();
    }

    /**
     * Initialize REST Controller
     */
    private function init_rest_controller() {
        $this->rest_controller = new \Newera\API\REST\RESTController(
            $this->auth_manager,
            $this->middleware_manager,
            $this->state_manager,
            $this->logger
        );
        $this->rest_controller->init();
    }

    /**
     * Initialize GraphQL Controller
     */
    private function init_graphql_controller() {
        $this->graphql_controller = new \Newera\API\GraphQL\GraphQLController(
            $this->auth_manager,
            $this->middleware_manager,
            $this->state_manager,
            $this->logger
        );
        $this->graphql_controller->init();
    }

    /**
     * Initialize Webhook Manager
     */
    private function init_webhook_manager() {
        $this->webhook_manager = new \Newera\API\Webhooks\WebhookManager(
            $this->state_manager,
            $this->logger
        );
        $this->webhook_manager->init();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Register REST API routes
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Register GraphQL endpoint
        add_action('init', [$this, 'register_graphql_endpoint']);

        // Register CORS middleware
        add_action('rest_pre_serve_request', [$this->middleware_manager, 'handle_cors'], 10, 3);

        // Schedule webhook delivery
        add_action('newera_deliver_webhooks', [$this->webhook_manager, 'process_deliveries']);
        if (!wp_next_scheduled('newera_deliver_webhooks')) {
            wp_schedule_event(time(), 'hourly', 'newera_deliver_webhooks');
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $this->rest_controller->register_routes();
    }

    /**
     * Register GraphQL endpoint
     */
    public function register_graphql_endpoint() {
        $this->graphql_controller->register_endpoint();
    }

    /**
     * Get Authentication Manager
     *
     * @return \Newera\API\Auth\AuthManager
     */
    public function get_auth_manager() {
        return $this->auth_manager;
    }

    /**
     * Get State Manager
     *
     * @return \Newera\Core\StateManager
     */
    public function get_state_manager() {
        return $this->state_manager;
    }

    /**
     * Get Logger
     *
     * @return \Newera\Core\Logger
     */
    public function get_logger() {
        return $this->logger;
    }

    /**
     * Get Webhook Manager
     *
     * @return \Newera\API\Webhooks\WebhookManager
     */
    public function get_webhook_manager() {
        return $this->webhook_manager;
    }

    /**
     * Get API version
     *
     * @return string
     */
    public static function get_api_version() {
        return self::API_VERSION;
    }

    /**
     * Get REST namespace
     *
     * @return string
     */
    public static function get_rest_namespace() {
        return self::REST_NAMESPACE;
    }
}