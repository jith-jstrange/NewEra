<?php
/**
 * Stripe Manager class for Newera Plugin
 *
 * Handles all Stripe API interactions including subscriptions, charges, and webhooks.
 */

namespace Newera\Payments;

use Newera\Core\Logger;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * StripeManager class
 */
class StripeManager {
    /**
     * Stripe API base URL
     */
    const STRIPE_API_URL = 'https://api.stripe.com/v1';

    /**
     * Stripe API version
     */
    const STRIPE_API_VERSION = '2023-10-16';

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
     * Stripe API key (secret key)
     *
     * @var string|null
     */
    private $api_key;

    /**
     * Stripe Public Key
     *
     * @var string|null
     */
    private $public_key;

    /**
     * Webhook secret for signature verification
     *
     * @var string|null
     */
    private $webhook_secret;

    /**
     * Mode (test or live)
     *
     * @var string
     */
    private $mode = 'test';

    /**
     * Constructor
     *
     * @param StateManager $state_manager
     * @param Logger $logger
     */
    public function __construct($state_manager = null, $logger = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : new StateManager();
        $this->logger = $logger instanceof Logger ? $logger : new Logger();

        $this->load_credentials();
    }

    /**
     * Load Stripe credentials from secure storage
     */
    private function load_credentials() {
        try {
            $this->api_key = $this->state_manager->getSecure('payments', 'stripe_api_key');
            $this->public_key = $this->state_manager->getSecure('payments', 'stripe_public_key');
            $this->webhook_secret = $this->state_manager->getSecure('payments', 'stripe_webhook_secret');
            $this->mode = $this->state_manager->getSecure('payments', 'stripe_mode', 'test');
        } catch (\Exception $e) {
            $this->logger->warning('Failed to load Stripe credentials', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if Stripe is configured
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Set Stripe API credentials
     *
     * @param string $api_key Secret API key
     * @param string $public_key Public key
     * @param string $webhook_secret Webhook signing secret
     * @param string $mode test or live
     * @return bool
     */
    public function set_credentials($api_key, $public_key, $webhook_secret = '', $mode = 'test') {
        try {
            $this->state_manager->setSecure('payments', 'stripe_api_key', $api_key);
            $this->state_manager->setSecure('payments', 'stripe_public_key', $public_key);
            
            if (!empty($webhook_secret)) {
                $this->state_manager->setSecure('payments', 'stripe_webhook_secret', $webhook_secret);
            }
            
            $this->state_manager->setSecure('payments', 'stripe_mode', $mode);

            $this->api_key = $api_key;
            $this->public_key = $public_key;
            $this->webhook_secret = $webhook_secret;
            $this->mode = $mode;

            $this->logger->info('Stripe credentials set successfully', [
                'mode' => $mode,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to set Stripe credentials', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Make an API request to Stripe
     *
     * @param string $method HTTP method (GET, POST, etc)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false
     */
    public function api_request($method, $endpoint, $data = []) {
        if (!$this->is_configured()) {
            $this->logger->error('Stripe not configured', [
                'endpoint' => $endpoint,
            ]);
            return false;
        }

        if ($this->is_mock_mode()) {
            return $this->mock_api_request($method, $endpoint, $data);
        }

        $base = $this->get_api_base_url();
        $url = rtrim($base, '/') . $endpoint;

        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Stripe-Version' => self::STRIPE_API_VERSION,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => (strpos($url, 'https://') === 0),
        ];

        if (!empty($data) && strtoupper($method) !== 'GET') {
            $args['body'] = http_build_query($data);
        } elseif (!empty($data) && strtoupper($method) === 'GET') {
            $url .= '?' . http_build_query($data);
        }

        try {
            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                throw new \Exception('HTTP Error: ' . $response->get_error_message());
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $decoded = json_decode($body, true);

            if ($status >= 400) {
                throw new \Exception('Stripe API Error (' . $status . '): ' .
                    (isset($decoded['error']['message']) ? $decoded['error']['message'] : 'Unknown error'));
            }

            return $decoded;
        } catch (\Exception $e) {
            $this->logger->error('Stripe API request failed', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * @return string
     */
    private function get_api_base_url() {
        $env = getenv('NEWERA_STRIPE_API_URL');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        return self::STRIPE_API_URL;
    }

    /**
     * @return bool
     */
    private function is_mock_mode() {
        return function_exists('newera_is_e2e_mode') && newera_is_e2e_mode();
    }

    /**
     * Deterministic mock Stripe responses for E2E tests.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return array|false
     */
    private function mock_api_request($method, $endpoint, $data = []) {
        $method = strtoupper((string) $method);
        $endpoint = (string) $endpoint;

        $store = $this->state_manager->get_state_value('mock_stripe', []);
        if (!is_array($store)) {
            $store = [];
        }

        $store += [
            'customers' => [],
            'products' => [],
            'prices' => [],
            'subscriptions' => [],
            'webhook_endpoints' => [],
        ];

        $save = function() use (&$store) {
            $this->state_manager->update_state('mock_stripe', $store);
        };

        if ($endpoint === '/account' && $method === 'GET') {
            return [
                'id' => 'acct_mock',
                'object' => 'account',
                'charges_enabled' => true,
                'details_submitted' => true,
            ];
        }

        if ($endpoint === '/customers' && $method === 'POST') {
            $id = 'cus_' . substr(md5(wp_json_encode($data) . microtime(true)), 0, 10);
            $customer = array_merge([
                'id' => $id,
                'object' => 'customer',
                'email' => $data['email'] ?? null,
                'name' => $data['name'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ], []);

            $store['customers'][$id] = $customer;
            $save();
            return $customer;
        }

        if (preg_match('#^/customers/([^/]+)$#', $endpoint, $m) && $method === 'GET') {
            $id = $m[1];
            return isset($store['customers'][$id]) ? $store['customers'][$id] : false;
        }

        if ($endpoint === '/products' && $method === 'POST') {
            $id = 'prod_' . substr(md5(wp_json_encode($data) . microtime(true)), 0, 10);
            $product = [
                'id' => $id,
                'object' => 'product',
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? null,
            ];
            $store['products'][$id] = $product;
            $save();
            return $product;
        }

        if ($endpoint === '/prices' && $method === 'POST') {
            $id = 'price_' . substr(md5(wp_json_encode($data) . microtime(true)), 0, 10);
            $price = [
                'id' => $id,
                'object' => 'price',
                'unit_amount' => (int) ($data['unit_amount'] ?? 0),
                'currency' => (string) ($data['currency'] ?? 'usd'),
                'recurring' => $data['recurring'] ?? ['interval' => 'month'],
                'product' => $data['product'] ?? null,
            ];
            $store['prices'][$id] = $price;
            $save();
            return $price;
        }

        if ($endpoint === '/subscriptions' && $method === 'POST') {
            $sub_id = 'sub_' . substr(md5(wp_json_encode($data) . microtime(true)), 0, 10);

            $price_id = $data['items'][0]['price'] ?? '';
            $price = $price_id && isset($store['prices'][$price_id]) ? $store['prices'][$price_id] : [
                'id' => $price_id,
                'unit_amount' => 0,
                'currency' => 'usd',
                'recurring' => ['interval' => 'month'],
            ];

            $now = time();
            $period_end = strtotime('+30 days', $now);

            $subscription = [
                'id' => $sub_id,
                'object' => 'subscription',
                'customer' => $data['customer'] ?? null,
                'status' => 'active',
                'current_period_start' => $now,
                'current_period_end' => $period_end,
                'items' => [
                    'data' => [
                        [
                            'price' => $price,
                        ],
                    ],
                ],
            ];

            $store['subscriptions'][$sub_id] = $subscription;
            $save();
            return $subscription;
        }

        if (preg_match('#^/subscriptions/([^/]+)$#', $endpoint, $m)) {
            $sub_id = $m[1];

            if (!isset($store['subscriptions'][$sub_id])) {
                return false;
            }

            if ($method === 'GET') {
                return $store['subscriptions'][$sub_id];
            }

            if ($method === 'DELETE') {
                $store['subscriptions'][$sub_id]['status'] = 'canceled';
                $save();
                return $store['subscriptions'][$sub_id];
            }

            if ($method === 'POST') {
                // Update subscription.
                foreach ($data as $k => $v) {
                    $store['subscriptions'][$sub_id][$k] = $v;
                }
                $save();
                return $store['subscriptions'][$sub_id];
            }
        }

        if ($endpoint === '/webhook_endpoints' && $method === 'POST') {
            $id = 'we_' . substr(md5(wp_json_encode($data) . microtime(true)), 0, 10);
            $webhook = [
                'id' => $id,
                'object' => 'webhook_endpoint',
                'url' => $data['url'] ?? '',
                'enabled_events' => $data['enabled_events'] ?? [],
            ];
            $store['webhook_endpoints'][$id] = $webhook;
            $save();
            return $webhook;
        }

        if ($endpoint === '/webhook_endpoints' && $method === 'GET') {
            return [
                'object' => 'list',
                'data' => array_values($store['webhook_endpoints']),
            ];
        }

        $this->logger->warning('Stripe mock endpoint not implemented', [
            'method' => $method,
            'endpoint' => $endpoint,
        ]);

        return false;
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw webhook payload
     * @param string $signature Stripe signature header
     * @return bool
     */
    public function verify_webhook_signature($payload, $signature) {
        if (empty($this->webhook_secret)) {
            $this->logger->warning('Webhook secret not configured');
            return false;
        }

        try {
            $timestamp_and_signed_content = explode(',', $signature);
            if (count($timestamp_and_signed_content) !== 2) {
                return false;
            }

            list($timestamp, $signed_content) = $timestamp_and_signed_content;
            $timestamp = str_replace('t=', '', $timestamp);

            // Check timestamp to prevent replay attacks (within 5 minutes)
            if (time() - intval($timestamp) > 300) {
                $this->logger->warning('Webhook signature timestamp too old');
                return false;
            }

            $signed_content = str_replace('v1=', '', $signed_content);
            $expected_signature = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhook_secret);

            return hash_equals($expected_signature, $signed_content);
        } catch (\Exception $e) {
            $this->logger->error('Webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create a customer in Stripe
     *
     * @param array $customer_data Customer information
     * @return array|false
     */
    public function create_customer($customer_data) {
        return $this->api_request('POST', '/customers', $customer_data);
    }

    /**
     * Retrieve a customer from Stripe
     *
     * @param string $customer_id Stripe customer ID
     * @return array|false
     */
    public function get_customer($customer_id) {
        return $this->api_request('GET', '/customers/' . $customer_id);
    }

    /**
     * Update a customer in Stripe
     *
     * @param string $customer_id Stripe customer ID
     * @param array $data Update data
     * @return array|false
     */
    public function update_customer($customer_id, $data) {
        return $this->api_request('POST', '/customers/' . $customer_id, $data);
    }

    /**
     * Create a subscription in Stripe
     *
     * @param array $subscription_data Subscription information
     * @return array|false
     */
    public function create_subscription($subscription_data) {
        return $this->api_request('POST', '/subscriptions', $subscription_data);
    }

    /**
     * Retrieve a subscription from Stripe
     *
     * @param string $subscription_id Stripe subscription ID
     * @return array|false
     */
    public function get_subscription($subscription_id) {
        return $this->api_request('GET', '/subscriptions/' . $subscription_id);
    }

    /**
     * Update a subscription in Stripe
     *
     * @param string $subscription_id Stripe subscription ID
     * @param array $data Update data
     * @return array|false
     */
    public function update_subscription($subscription_id, $data) {
        return $this->api_request('POST', '/subscriptions/' . $subscription_id, $data);
    }

    /**
     * Cancel a subscription in Stripe
     *
     * @param string $subscription_id Stripe subscription ID
     * @param array $data Cancel options
     * @return array|false
     */
    public function cancel_subscription($subscription_id, $data = []) {
        return $this->api_request('DELETE', '/subscriptions/' . $subscription_id, $data);
    }

    /**
     * Create a one-time charge
     *
     * @param array $charge_data Charge information
     * @return array|false
     */
    public function create_charge($charge_data) {
        return $this->api_request('POST', '/charges', $charge_data);
    }

    /**
     * Retrieve a charge
     *
     * @param string $charge_id Stripe charge ID
     * @return array|false
     */
    public function get_charge($charge_id) {
        return $this->api_request('GET', '/charges/' . $charge_id);
    }

    /**
     * Refund a charge
     *
     * @param string $charge_id Stripe charge ID
     * @param array $data Refund options
     * @return array|false
     */
    public function refund_charge($charge_id, $data = []) {
        return $this->api_request('POST', '/refunds', array_merge([
            'charge' => $charge_id,
        ], $data));
    }

    /**
     * Create a payment intent
     *
     * @param array $intent_data Intent information
     * @return array|false
     */
    public function create_payment_intent($intent_data) {
        return $this->api_request('POST', '/payment_intents', $intent_data);
    }

    /**
     * Retrieve a payment intent
     *
     * @param string $intent_id Payment intent ID
     * @return array|false
     */
    public function get_payment_intent($intent_id) {
        return $this->api_request('GET', '/payment_intents/' . $intent_id);
    }

    /**
     * Confirm a payment intent
     *
     * @param string $intent_id Payment intent ID
     * @param array $data Confirmation data
     * @return array|false
     */
    public function confirm_payment_intent($intent_id, $data) {
        return $this->api_request('POST', '/payment_intents/' . $intent_id . '/confirm', $data);
    }

    /**
     * Create or retrieve a plan (price)
     *
     * @param array $plan_data Plan/price data
     * @return array|false
     */
    public function create_price($plan_data) {
        return $this->api_request('POST', '/prices', $plan_data);
    }

    /**
     * Get a price
     *
     * @param string $price_id Price ID
     * @return array|false
     */
    public function get_price($price_id) {
        return $this->api_request('GET', '/prices/' . $price_id);
    }

    /**
     * List prices
     *
     * @param array $filters Filter options
     * @return array|false
     */
    public function list_prices($filters = []) {
        return $this->api_request('GET', '/prices', $filters);
    }

    /**
     * Get Stripe public key
     *
     * @return string|null
     */
    public function get_public_key() {
        return $this->public_key;
    }

    /**
     * Get current mode
     *
     * @return string
     */
    public function get_mode() {
        return $this->mode;
    }

    /**
     * Register webhook with Stripe
     *
     * @param string $url Webhook URL
     * @param array $events Events to listen for
     * @return array|false
     */
    public function register_webhook($url, $events = []) {
        if (empty($events)) {
            $events = [
                'customer.created',
                'customer.updated',
                'customer.deleted',
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'charge.succeeded',
                'charge.failed',
                'charge.refunded',
                'invoice.created',
                'invoice.finalized',
                'invoice.payment_succeeded',
                'invoice.payment_failed',
                'payment_intent.succeeded',
                'payment_intent.payment_failed',
            ];
        }

        $data = [
            'url' => $url,
            'enabled_events' => $events,
            'api_version' => self::STRIPE_API_VERSION,
        ];

        return $this->api_request('POST', '/webhook_endpoints', $data);
    }

    /**
     * List webhook endpoints
     *
     * @return array|false
     */
    public function list_webhooks() {
        return $this->api_request('GET', '/webhook_endpoints');
    }

    /**
     * Check API key validity
     *
     * @return bool
     */
    public function test_api_key() {
        $result = $this->api_request('GET', '/account');
        return $result !== false;
    }

    /**
     * Get Stripe account information
     *
     * @return array|false
     */
    public function get_account_info() {
        return $this->api_request('GET', '/account');
    }
}
