<?php
/**
 * Webhook Endpoint for Newera Plugin
 *
 * REST endpoint for receiving Stripe webhooks.
 */

namespace Newera\Payments;

use Newera\Core\Logger;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WebhookEndpoint class
 */
class WebhookEndpoint {
    /**
     * REST route namespace
     */
    const NAMESPACE = 'newera/v1';

    /**
     * REST route
     */
    const ROUTE = '/stripe-webhook';

    /**
     * StateManager instance
     *
     * @var StateManager
     */
    private $state_manager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * StripeManager instance
     *
     * @var StripeManager
     */
    private $stripe;

    /**
     * WebhookHandler instance
     *
     * @var WebhookHandler
     */
    private $webhook_handler;

    /**
     * Constructor
     *
     * @param StateManager $state_manager
     * @param Logger $logger
     */
    public function __construct($state_manager = null, $logger = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : new StateManager();
        $this->logger = $logger instanceof Logger ? $logger : new Logger();
        $this->stripe = new StripeManager($this->state_manager, $this->logger);
        $this->webhook_handler = new WebhookHandler($this->state_manager, $this->logger, $this->stripe);
    }

    /**
     * Register the webhook endpoint
     */
    public function register() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => '__return_true', // Public endpoint, we verify signature
            ]
        );
    }

    /**
     * Handle webhook request
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handle_webhook($request) {
        try {
            $body = $request->get_body();
            $signature = $request->get_header('stripe_signature');

            if (empty($body) || empty($signature)) {
                $this->logger->warning('Webhook request missing body or signature');
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Missing required headers',
                ], 400);
            }

            // Verify signature
            if (!$this->stripe->verify_webhook_signature($body, $signature)) {
                $this->logger->warning('Webhook signature verification failed');
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Invalid signature',
                ], 401);
            }

            // Parse event
            $event = json_decode($body, true);

            if (!$event || !is_array($event)) {
                $this->logger->warning('Webhook request contains invalid JSON');
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Invalid event data',
                ], 400);
            }

            // Handle the event
            $result = $this->webhook_handler->handle_event($event);

            if (!$result) {
                // Return 200 anyway - Stripe wants to know we received it
                // Even if we couldn't process it
                $this->logger->warning('Webhook event handling returned false', [
                    'type' => $event['type'] ?? 'unknown',
                ]);
            }

            // Always return 200 OK to Stripe
            return new \WP_REST_Response([
                'success' => true,
                'received' => true,
            ], 200);

        } catch (\Exception $e) {
            $this->logger->error('Webhook endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get webhook endpoint URL
     *
     * @return string
     */
    public static function get_webhook_url() {
        return rest_url(self::NAMESPACE . self::ROUTE);
    }
}
