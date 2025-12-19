<?php
/**
 * Webhook Manager
 *
 * Handles webhook delivery system with retry logic
 *
 * @package Newera\API\Webhooks
 */

namespace Newera\API\Webhooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook Manager class
 */
class WebhookManager {
    /**
     * Webhook delivery option name
     */
    const WEBHOOK_DELIVERIES_OPTION = 'newera_webhook_deliveries';

    /**
     * Webhook option name
     */
    const WEBHOOKS_OPTION = 'newera_webhooks';

    /**
     * Max retry attempts
     */
    const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Retry delays (in seconds)
     */
    const RETRY_DELAYS = [300, 1800, 7200]; // 5 minutes, 30 minutes, 2 hours

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
    }

    /**
     * Initialize Webhook Manager
     */
    public function init() {
        // Hook into WordPress actions to trigger webhooks
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Client events
        add_action('newera_client_created', [$this, 'trigger_webhook'], 10, 2);
        add_action('newera_client_updated', [$this, 'trigger_webhook'], 10, 2);

        // Project events
        add_action('newera_project_created', [$this, 'trigger_webhook'], 10, 2);
        add_action('newera_project_updated', [$this, 'trigger_webhook'], 10, 2);

        // Subscription events
        add_action('newera_subscription_created', [$this, 'trigger_webhook'], 10, 2);
        add_action('newera_subscription_updated', [$this, 'trigger_webhook'], 10, 2);

        // Webhook delivery events
        add_action('newera_webhook_delivery_failed', [$this, 'trigger_webhook'], 10, 2);
    }

    /**
     * Create webhook
     *
     * @param array $webhook_data
     * @return int|\WP_Error
     */
    public function create_webhook($webhook_data) {
        $webhook_id = $this->state_manager->create_item('api_webhooks', array_merge($webhook_data, [
            'created_at' => current_time('mysql'),
            'active' => true
        ]));

        if (!$webhook_id) {
            return new \WP_Error('webhook_creation_failed', 'Failed to create webhook');
        }

        $this->logger->info('Webhook created', [
            'webhook_id' => $webhook_id,
            'url' => $webhook_data['url']
        ]);

        return $webhook_id;
    }

    /**
     * Update webhook
     *
     * @param int $webhook_id
     * @param array $update_data
     * @return bool|\WP_Error
     */
    public function update_webhook($webhook_id, $update_data) {
        $existing_webhook = $this->state_manager->get_item('api_webhooks', $webhook_id);
        
        if (!$existing_webhook) {
            return new \WP_Error('webhook_not_found', 'Webhook not found');
        }

        $update_data['updated_at'] = current_time('mysql');

        $success = $this->state_manager->update_item('api_webhooks', $webhook_id, $update_data);

        if (!$success) {
            return new \WP_Error('webhook_update_failed', 'Failed to update webhook');
        }

        $this->logger->info('Webhook updated', [
            'webhook_id' => $webhook_id,
            'url' => $existing_webhook['url']
        ]);

        return true;
    }

    /**
     * Delete webhook
     *
     * @param int $webhook_id
     * @return bool|\WP_Error
     */
    public function delete_webhook($webhook_id) {
        $existing_webhook = $this->state_manager->get_item('api_webhooks', $webhook_id);
        
        if (!$existing_webhook) {
            return new \WP_Error('webhook_not_found', 'Webhook not found');
        }

        $success = $this->state_manager->delete_item('api_webhooks', $webhook_id);

        if (!$success) {
            return new \WP_Error('webhook_delete_failed', 'Failed to delete webhook');
        }

        $this->logger->info('Webhook deleted', [
            'webhook_id' => $webhook_id,
            'url' => $existing_webhook['url']
        ]);

        return true;
    }

    /**
     * Trigger webhook for event
     *
     * @param string $event Event name
     * @param array $data Event data
     */
    public function trigger_webhook($event, $data) {
        try {
            // Get all active webhooks that should receive this event
            $webhooks = $this->get_active_webhooks_for_event($event);
            
            if (empty($webhooks)) {
                return;
            }

            // Create webhook delivery records
            foreach ($webhooks as $webhook) {
                $this->queue_webhook_delivery($webhook, $event, $data);
            }

            $this->logger->debug('Webhook triggered', [
                'event' => $event,
                'webhooks_count' => count($webhooks),
                'data' => $data
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error triggering webhook', [
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get active webhooks for event
     *
     * @param string $event
     * @return array
     */
    private function get_active_webhooks_for_event($event) {
        $all_webhooks = $this->state_manager->get_list('api_webhooks', ['per_page' => 1000]);
        
        $matching_webhooks = [];
        foreach ($all_webhooks['items'] as $webhook) {
            if ($webhook['active'] && in_array($event, $webhook['events'])) {
                $matching_webhooks[] = $webhook;
            }
        }

        return $matching_webhooks;
    }

    /**
     * Queue webhook delivery
     *
     * @param array $webhook
     * @param string $event
     * @param array $data
     */
    private function queue_webhook_delivery($webhook, $event, $data) {
        $delivery_data = [
            'webhook_id' => $webhook['id'],
            'event' => $event,
            'payload' => [
                'event' => $event,
                'timestamp' => current_time('c'),
                'data' => $data,
                'webhook_id' => $webhook['id']
            ],
            'status' => 'pending',
            'attempt' => 0,
            'next_attempt_at' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ];

        $this->state_manager->create_item('webhook_deliveries', $delivery_data);
    }

    /**
     * Process webhook deliveries
     */
    public function process_deliveries() {
        try {
            // Get pending deliveries that are ready for retry
            $deliveries = $this->get_pending_deliveries();
            
            foreach ($deliveries as $delivery) {
                $this->process_single_delivery($delivery);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook deliveries', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get pending webhook deliveries
     *
     * @return array
     */
    private function get_pending_deliveries() {
        return $this->state_manager->get_list('webhook_deliveries', [
            'per_page' => 50,
            'filter' => [
                'status' => 'pending',
                'next_attempt_lte' => current_time('mysql')
            ]
        ])['items'];
    }

    /**
     * Process single webhook delivery
     *
     * @param array $delivery
     */
    private function process_single_delivery($delivery) {
        try {
            // Get webhook details
            $webhook = $this->state_manager->get_item('api_webhooks', $delivery['webhook_id']);
            
            if (!$webhook || !$webhook['active']) {
                $this->mark_delivery_failed($delivery, 'Webhook no longer active');
                return;
            }

            // Increment attempt counter
            $delivery['attempt']++;
            
            // Deliver webhook
            $result = $this->deliver_webhook($webhook, $delivery['payload']);
            
            if ($result['success']) {
                $this->mark_delivery_success($delivery);
                $this->logger->info('Webhook delivered successfully', [
                    'delivery_id' => $delivery['id'],
                    'webhook_id' => $webhook['id'],
                    'url' => $webhook['url'],
                    'attempt' => $delivery['attempt']
                ]);
            } else {
                $this->handle_delivery_failure($delivery, $webhook, $result['error']);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook delivery', [
                'delivery_id' => $delivery['id'],
                'error' => $e->getMessage()
            ]);
            
            $this->mark_delivery_failed($delivery, $e->getMessage());
        }
    }

    /**
     * Deliver webhook
     *
     * @param array $webhook
     * @param array $payload
     * @return array
     */
    private function deliver_webhook($webhook, $payload) {
        try {
            // Generate signature
            $payload_json = json_encode($payload);
            $signature = hash_hmac('sha256', $payload_json, $webhook['secret']);
            
            // Prepare headers
            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Newera-Webhook/1.0',
                'X-Newera-Event' => $payload['event'],
                'X-Newera-Signature' => 'sha256=' . $signature,
                'X-Newera-Webhook-ID' => $webhook['id']
            ];

            // Make HTTP request
            $response = wp_remote_post($webhook['url'], [
                'headers' => $headers,
                'body' => $payload_json,
                'timeout' => 30,
                'redirection' => 0
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => $response->get_error_message()
                ];
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            // Consider 2xx status codes as success
            if ($status_code >= 200 && $status_code < 300) {
                return [
                    'success' => true,
                    'status_code' => $status_code,
                    'body' => $body
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'HTTP ' . $status_code . ': ' . $body
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle delivery failure
     *
     * @param array $delivery
     * @param array $webhook
     * @param string $error
     */
    private function handle_delivery_failure($delivery, $webhook, $error) {
        if ($delivery['attempt'] >= self::MAX_RETRY_ATTEMPTS) {
            $this->mark_delivery_failed($delivery, 'Max retry attempts exceeded: ' . $error);
            
            // Trigger failure event
            do_action('newera_webhook_delivery_failed', $delivery, $error);
            
            $this->logger->warning('Webhook delivery permanently failed', [
                'delivery_id' => $delivery['id'],
                'webhook_id' => $webhook['id'],
                'url' => $webhook['url'],
                'attempts' => $delivery['attempt'],
                'error' => $error
            ]);
        } else {
            // Schedule retry
            $retry_delay = self::RETRY_DELAYS[min($delivery['attempt'] - 1, count(self::RETRY_DELAYS) - 1)];
            $next_attempt = current_time('mysql', true) + $retry_delay;

            $this->state_manager->update_item('webhook_deliveries', $delivery['id'], [
                'status' => 'pending',
                'next_attempt_at' => date('Y-m-d H:i:s', $next_attempt),
                'last_error' => $error
            ]);

            $this->logger->info('Webhook delivery failed, retry scheduled', [
                'delivery_id' => $delivery['id'],
                'webhook_id' => $webhook['id'],
                'url' => $webhook['url'],
                'attempt' => $delivery['attempt'],
                'next_attempt_at' => $next_attempt,
                'error' => $error
            ]);
        }
    }

    /**
     * Mark delivery as successful
     *
     * @param array $delivery
     */
    private function mark_delivery_success($delivery) {
        $this->state_manager->update_item('webhook_deliveries', $delivery['id'], [
            'status' => 'success',
            'delivered_at' => current_time('mysql'),
            'last_error' => null
        ]);
    }

    /**
     * Mark delivery as failed
     *
     * @param array $delivery
     * @param string $error
     */
    private function mark_delivery_failed($delivery, $error) {
        $this->state_manager->update_item('webhook_deliveries', $delivery['id'], [
            'status' => 'failed',
            'last_error' => $error
        ]);
    }

    /**
     * Get webhook delivery logs
     *
     * @param array $args
     * @return array
     */
    public function get_delivery_logs($args = []) {
        $default_args = [
            'page' => 1,
            'per_page' => 50,
            'status' => null,
            'webhook_id' => null
        ];

        $args = array_merge($default_args, $args);

        $filter = [];
        if ($args['status']) {
            $filter['status'] = $args['status'];
        }
        if ($args['webhook_id']) {
            $filter['webhook_id'] = $args['webhook_id'];
        }

        return $this->state_manager->get_list('webhook_deliveries', [
            'page' => $args['page'],
            'per_page' => $args['per_page'],
            'filter' => $filter
        ]);
    }

    /**
     * Test webhook delivery
     *
     * @param int $webhook_id
     * @param string $event
     * @param array $test_data
     * @return array
     */
    public function test_webhook_delivery($webhook_id, $event = 'test', $test_data = []) {
        $webhook = $this->state_manager->get_item('api_webhooks', $webhook_id);
        
        if (!$webhook) {
            return [
                'success' => false,
                'error' => 'Webhook not found'
            ];
        }

        $payload = [
            'event' => $event,
            'timestamp' => current_time('c'),
            'data' => $test_data ?: [
                'message' => 'This is a test webhook delivery',
                'webhook_id' => $webhook_id
            ],
            'webhook_id' => $webhook_id,
            'test' => true
        ];

        return $this->deliver_webhook($webhook, $payload);
    }
}