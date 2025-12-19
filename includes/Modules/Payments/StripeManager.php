<?php
/**
 * Stripe Payments Manager for Newera Plugin
 *
 * Handles subscriptions, one-time payments, webhooks, and plan management.
 */

namespace Newera\Modules\Payments;

use Newera\Modules\BaseModule;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Invoice;
use Stripe\Webhook;
use Stripe\Exception\CardException;
use Stripe\Exception\ApiErrorException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class StripeManager
 */
class StripeManager extends BaseModule {

    /**
     * @var string
     */
    private $module_id = 'stripe_payments';

    /**
     * @var \Stripe\StripeClient|null
     */
    private $stripe_client = null;

    /**
     * @var array Webhook endpoints that have been registered
     */
    private $registered_webhooks = [];

    /**
     * Initialize Stripe with API keys
     */
    public function init_stripe() {
        $api_key = $this->get_credential('api_key');
        $api_secret = $this->get_credential('api_secret');

        if (empty($api_key) || empty($api_secret)) {
            $this->log_warning('Stripe API keys not configured', [
                'api_key_exists' => !empty($api_key),
                'api_secret_exists' => !empty($api_secret)
            ]);
            return false;
        }

        try {
            $this->stripe_client = new \Stripe\StripeClient($api_secret);
            $this->log_info('Stripe initialized successfully');
            return true;
        } catch (\Exception $e) {
            $this->log_error('Failed to initialize Stripe', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->module_id;
    }

    /**
     * @return string
     */
    public function getDescription() {
        return 'Stripe payment processing for subscriptions and one-time payments';
    }

    /**
     * @return array
     */
    public function getSettingsSchema() {
        return [
            'api_key' => [
                'type' => 'text',
                'title' => 'Publishable Key',
                'required' => true,
                'description' => 'Your Stripe publishable key'
            ],
            'api_secret' => [
                'type' => 'password',
                'title' => 'Secret Key',
                'required' => true,
                'description' => 'Your Stripe secret key'
            ],
            'webhook_endpoint' => [
                'type' => 'text',
                'title' => 'Webhook Endpoint',
                'readonly' => true,
                'description' => 'URL for Stripe webhooks'
            ],
            'default_currency' => [
                'type' => 'select',
                'title' => 'Default Currency',
                'options' => ['usd', 'eur', 'gbp', 'cad'],
                'default' => 'usd'
            ]
        ];
    }

    /**
     * @param array $credentials
     * @return array
     */
    public function validateCredentials($credentials) {
        $errors = [];
        $valid = true;

        if (empty($credentials['api_key'])) {
            $errors[] = 'Publishable key is required';
            $valid = false;
        }

        if (empty($credentials['api_secret'])) {
            $errors[] = 'Secret key is required';
            $valid = false;
        }

        if ($valid && !empty($credentials['api_key']) && !empty($credentials['api_secret'])) {
            // Test the credentials
            try {
                $temp_client = new \Stripe\StripeClient($credentials['api_secret']);
                $temp_client->accounts->retrieve();
                $this->log_info('Stripe credentials validated successfully');
            } catch (\Exception $e) {
                $valid = false;
                $errors[] = 'Invalid Stripe credentials: ' . $e->getMessage();
                $this->log_error('Stripe credential validation failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        return $this->has_credential('api_key') && $this->has_credential('api_secret');
    }

    /**
     * Set module active state and initialize Stripe
     *
     * @param bool $active
     */
    public function setActive($active) {
        parent::setActive($active);
        
        if ($active) {
            $this->init_stripe();
        }
    }

    /**
     * Register WordPress hooks
     */
    public function registerHooks() {
        // Webhook endpoint
        add_action('init', [$this, 'register_webhook_endpoint']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_stripe_create_subscription', [$this, 'ajax_create_subscription']);
        add_action('wp_ajax_stripe_cancel_subscription', [$this, 'ajax_cancel_subscription']);
        add_action('wp_ajax_stripe_create_plan', [$this, 'ajax_create_plan']);
        add_action('wp_ajax_stripe_update_plan', [$this, 'ajax_update_plan']);
        add_action('wp_ajax_stripe_delete_plan', [$this, 'ajax_delete_plan']);
        add_action('wp_ajax_save_stripe_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_test_stripe_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_register_stripe_webhooks', [$this, 'ajax_register_webhooks']);

        // Process webhooks
        add_action('wp_ajax_newera_stripe_webhook', [$this, 'handle_webhook']);
        add_action('wp_ajax_nopriv_newera_stripe_webhook', [$this, 'handle_webhook']);
    }

    /**
     * Register webhook endpoint
     */
    public function register_webhook_endpoint() {
        add_rewrite_rule(
            '^newera-stripe-webhook/?$',
            'index.php?newera_stripe_webhook=1',
            'top'
        );
        
        // Add query vars for rewrite
        add_filter('query_vars', function($vars) {
            $vars[] = 'newera_stripe_webhook';
            return $vars;
        });
        
        // Handle webhook requests
        add_action('template_redirect', [$this, 'handle_webhook_request']);
    }

    /**
     * Register admin menu for payments
     */
    public function register_admin_menu() {
        add_submenu_page(
            'newera',
            __('Payments', 'newera'),
            __('Payments', 'newera'),
            'manage_options',
            'newera-payments',
            [$this, 'display_payments_page']
        );
    }

    /**
     * Create a new subscription
     *
     * @param array $data Subscription data
     * @return array|WP_Error
     */
    public function create_subscription($data) {
        if (!$this->stripe_client) {
            return new \WP_Error('stripe_not_initialized', 'Stripe is not properly configured');
        }

        try {
            $customer_data = [
                'email' => $data['email'],
                'name' => $data['name'],
                'metadata' => [
                    'wp_user_id' => $data['user_id'],
                    'wp_client_id' => $data['client_id'] ?? ''
                ]
            ];

            // Create or update customer
            $customer = $this->stripe_client->customers->create($customer_data);
            
            // Create subscription
            $subscription_data = [
                'customer' => $customer->id,
                'items' => [
                    ['price' => $data['price_id']]
                ],
                'expand' => ['latest_invoice.payment_intent']
            ];

            // Add trial period if specified
            if (!empty($data['trial_days'])) {
                $subscription_data['trial_period_days'] = intval($data['trial_days']);
            }

            $subscription = $this->stripe_client->subscriptions->create($subscription_data);

            // Store in local database
            $this->store_subscription([
                'client_id' => $data['client_id'] ?? '',
                'stripe_subscription_id' => $subscription->id,
                'stripe_customer_id' => $customer->id,
                'plan' => $data['plan_id'],
                'status' => $subscription->status,
                'amount' => $subscription->items->data[0]->price->unit_amount / 100,
                'billing_cycle' => $subscription->items->data[0]->price->recurring->interval,
                'start_date' => date('Y-m-d', $subscription->current_period_start),
                'end_date' => date('Y-m-d', $subscription->current_period_end)
            ]);

            $this->log_info('Subscription created successfully', [
                'subscription_id' => $subscription->id,
                'customer_id' => $customer->id
            ]);

            return [
                'subscription' => $subscription,
                'customer' => $customer,
                'success' => true
            ];

        } catch (CardException $e) {
            $this->log_error('Payment failed', [
                'error' => $e->getMessage(),
                'type' => $e->getError()->type
            ]);
            return new \WP_Error('payment_failed', $e->getMessage());
        } catch (ApiErrorException $e) {
            $this->log_error('Stripe API error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            return new \WP_Error('stripe_api_error', $e->getMessage());
        } catch (\Exception $e) {
            $this->log_error('Subscription creation failed', [
                'error' => $e->getMessage()
            ]);
            return new \WP_Error('subscription_failed', $e->getMessage());
        }
    }

    /**
     * Cancel a subscription
     *
     * @param string $subscription_id
     * @param bool $cancel_at_period_end
     * @return array|WP_Error
     */
    public function cancel_subscription($subscription_id, $cancel_at_period_end = true) {
        if (!$this->stripe_client) {
            return new \WP_Error('stripe_not_initialized', 'Stripe is not properly configured');
        }

        try {
            if ($cancel_at_period_end) {
                $subscription = $this->stripe_client->subscriptions->cancel($subscription_id, [
                    'invoice_now' => false,
                    'proration_behavior' => 'none'
                ]);
            } else {
                $subscription = $this->stripe_client->subscriptions->retrieve($subscription_id);
                $subscription->cancel();
            }

            // Update local database
            $this->update_subscription_status($subscription_id, 'canceled');

            $this->log_info('Subscription canceled', [
                'subscription_id' => $subscription_id,
                'cancel_at_period_end' => $cancel_at_period_end
            ]);

            return [
                'subscription' => $subscription,
                'success' => true
            ];

        } catch (ApiErrorException $e) {
            $this->log_error('Failed to cancel subscription', [
                'subscription_id' => $subscription_id,
                'error' => $e->getMessage()
            ]);
            return new \WP_Error('cancel_failed', $e->getMessage());
        }
    }

    /**
     * Create a plan (price) in Stripe
     *
     * @param array $data Plan data
     * @return array|WP_Error
     */
    public function create_plan($data) {
        if (!$this->stripe_client) {
            return new \WP_Error('stripe_not_initialized', 'Stripe is not properly configured');
        }

        try {
            $product_data = [
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'metadata' => [
                    'wp_plan_id' => $data['plan_id']
                ]
            ];

            $product = $this->stripe_client->products->create($product_data);

            $price_data = [
                'unit_amount' => intval($data['amount'] * 100), // Convert to cents
                'currency' => $data['currency'],
                'product' => $product->id,
                'recurring' => [
                    'interval' => $data['billing_interval']
                ]
            ];

            // Add trial period if specified
            if (!empty($data['trial_period_days'])) {
                $price_data['recurring']['trial_period_days'] = intval($data['trial_period_days']);
            }

            $price = $this->stripe_client->prices->create($price_data);

            // Store plan locally
            $this->store_plan([
                'plan_id' => $data['plan_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'billing_interval' => $data['billing_interval'],
                'stripe_product_id' => $product->id,
                'stripe_price_id' => $price->id,
                'trial_period_days' => $data['trial_period_days'] ?? null,
                'active' => true
            ]);

            $this->log_info('Plan created successfully', [
                'plan_id' => $data['plan_id'],
                'stripe_price_id' => $price->id
            ]);

            return [
                'product' => $product,
                'price' => $price,
                'success' => true
            ];

        } catch (ApiErrorException $e) {
            $this->log_error('Failed to create plan', [
                'plan_id' => $data['plan_id'],
                'error' => $e->getMessage()
            ]);
            return new \WP_Error('plan_creation_failed', $e->getMessage());
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function handle_webhook() {
        // Verify webhook signature
        $payload = file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        if (empty($sig_header)) {
            http_response_code(400);
            exit('Missing signature');
        }

        try {
            $webhook = $this->stripe_client->webhookEndpoints->retrieve($this->get_webhook_endpoint_id());
            $event = Webhook::constructEvent($payload, $sig_header, $webhook->secret);
        } catch (\Exception $e) {
            $this->log_error('Webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            http_response_code(400);
            exit('Invalid signature');
        }

        // Process the event
        $this->process_webhook_event($event);

        http_response_code(200);
        exit('OK');
    }

    /**
     * Handle webhook requests
     */
    public function handle_webhook_request() {
        if (get_query_var('newera_stripe_webhook')) {
            $this->handle_webhook();
        }
    }

    /**
     * Process webhook events
     *
     * @param \Stripe\Event $event
     */
    private function process_webhook_event($event) {
        $this->log_info('Processing webhook event', [
            'event_type' => $event->type,
            'event_id' => $event->id
        ]);

        switch ($event->type) {
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handle_subscription_updated($event->data->object);
                break;
                
            case 'customer.subscription.deleted':
                $this->handle_subscription_deleted($event->data->object);
                break;
                
            case 'invoice.payment_succeeded':
                $this->handle_payment_succeeded($event->data->object);
                break;
                
            case 'invoice.payment_failed':
                $this->handle_payment_failed($event->data->object);
                break;
                
            case 'customer.subscription.trial_will_end':
                $this->handle_trial_will_end($event->data->object);
                break;
        }
    }

    /**
     * Handle subscription updated events
     */
    private function handle_subscription_updated($subscription) {
        $this->update_subscription_status($subscription->id, $subscription->status, [
            'end_date' => date('Y-m-d', $subscription->current_period_end),
            'amount' => $subscription->items->data[0]->price->unit_amount / 100
        ]);
    }

    /**
     * Handle subscription deleted events
     */
    private function handle_subscription_deleted($subscription) {
        $this->update_subscription_status($subscription->id, 'canceled');
    }

    /**
     * Handle payment succeeded events
     */
    private function handle_payment_succeeded($invoice) {
        $this->log_info('Payment succeeded', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription
        ]);
        
        // Could trigger email notifications here
    }

    /**
     * Handle payment failed events
     */
    private function handle_payment_failed($invoice) {
        $this->log_error('Payment failed', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $invoice->subscription,
            'attempt_count' => $invoice->attempt_count
        ]);
        
        // Could trigger email notifications here
    }

    /**
     * Handle trial will end events
     */
    private function handle_trial_will_end($subscription) {
        $this->log_info('Trial will end', [
            'subscription_id' => $subscription->id,
            'trial_end' => date('Y-m-d', $subscription->trial_end)
        ]);
        
        // Could trigger reminder emails here
    }

    /**
     * Store subscription in local database
     */
    private function store_subscription($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscriptions';
        
        $result = $wpdb->insert($table_name, [
            'client_id' => $data['client_id'],
            'plan' => $data['plan'],
            'status' => $data['status'],
            'amount' => $data['amount'],
            'billing_cycle' => $data['billing_cycle'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'created_at' => current_time('mysql')
        ]);

        if ($result === false) {
            $this->log_error('Failed to store subscription in database', [
                'error' => $wpdb->last_error
            ]);
        }
    }

    /**
     * Update subscription status in local database
     */
    private function update_subscription_status($stripe_subscription_id, $status, $additional_data = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'subscriptions';
        $update_data = array_merge(['status' => $status], $additional_data);
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['stripe_subscription_id' => $stripe_subscription_id]
        );

        if ($result === false) {
            $this->log_error('Failed to update subscription status', [
                'stripe_subscription_id' => $stripe_subscription_id,
                'error' => $wpdb->last_error
            ]);
        }
    }

    /**
     * Store plan in local database
     */
    private function store_plan($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'newera_plans';
        
        // Create table if it doesn't exist
        $this->create_plans_table();
        
        $result = $wpdb->insert($table_name, $data);

        if ($result === false) {
            $this->log_error('Failed to store plan in database', [
                'plan_id' => $data['plan_id'],
                'error' => $wpdb->last_error
            ]);
        }
    }

    /**
     * Create plans table if it doesn't exist
     */
    private function create_plans_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'newera_plans';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            plan_id varchar(100) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            amount decimal(15, 2) NOT NULL,
            currency varchar(3) DEFAULT 'usd',
            billing_interval varchar(50) NOT NULL,
            stripe_product_id varchar(255),
            stripe_price_id varchar(255),
            trial_period_days int(11),
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY plan_id (plan_id),
            KEY active (active)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get webhook endpoint ID from Stripe
     */
    private function get_webhook_endpoint_id() {
        // This would be stored during setup
        return $this->get_credential('webhook_endpoint_id');
    }

    /**
     * Display payments admin page
     */
    public function display_payments_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'newera'));
        }

        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'plans';
        
        include NEWERA_PLUGIN_PATH . 'templates/admin/payments.php';
    }

    /**
     * AJAX handler for creating subscriptions
     */
    public function ajax_create_subscription() {
        check_ajax_referer('newera_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $data = [
            'client_id' => intval($_POST['client_id']),
            'user_id' => get_current_user_id(),
            'email' => sanitize_email($_POST['email']),
            'name' => sanitize_text_field($_POST['name']),
            'plan_id' => sanitize_text_field($_POST['plan_id']),
            'price_id' => sanitize_text_field($_POST['price_id']),
            'trial_days' => intval($_POST['trial_days'] ?? 0)
        ];

        $result = $this->create_subscription($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for canceling subscriptions
     */
    public function ajax_cancel_subscription() {
        check_ajax_referer('newera_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $subscription_id = sanitize_text_field($_POST['subscription_id']);
        $cancel_at_period_end = boolval($_POST['cancel_at_period_end'] ?? true);

        $result = $this->cancel_subscription($subscription_id, $cancel_at_period_end);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for creating plans
     */
    public function ajax_create_plan() {
        check_ajax_referer('newera_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $data = [
            'plan_id' => sanitize_text_field($_POST['plan_id']),
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'amount' => floatval($_POST['amount']),
            'currency' => sanitize_text_field($_POST['currency']),
            'billing_interval' => sanitize_text_field($_POST['billing_interval']),
            'trial_period_days' => intval($_POST['trial_period_days'] ?? 0)
        ];

        $result = $this->create_plan($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for updating plan
     */
    public function ajax_update_plan() {
        check_ajax_referer('newera_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $plan_id = sanitize_text_field($_POST['plan_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'newera_plans';
        
        $update_data = [
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'amount' => floatval($_POST['amount']),
            'billing_interval' => sanitize_text_field($_POST['billing_interval']),
            'trial_period_days' => intval($_POST['trial_period_days'] ?? 0)
        ];

        $result = $wpdb->update($table_name, $update_data, ['plan_id' => $plan_id]);
        
        if ($result === false) {
            wp_send_json_error('Failed to update plan: ' . $wpdb->last_error);
        }

        wp_send_json_success(['message' => 'Plan updated successfully']);
    }

    /**
     * AJAX handler for deleting plan
     */
    public function ajax_delete_plan() {
        check_ajax_referer('newera_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $plan_id = sanitize_text_field($_POST['plan_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'newera_plans';
        
        // Soft delete by setting active = 0
        $result = $wpdb->update($table_name, ['active' => 0], ['plan_id' => $plan_id]);
        
        if ($result === false) {
            wp_send_json_error('Failed to delete plan: ' . $wpdb->last_error);
        }

        wp_send_json_success(['message' => 'Plan deleted successfully']);
    }

    /**
     * AJAX handler for saving Stripe settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('newera_admin_nonce', 'settings_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $credentials = [
            'api_key' => sanitize_text_field($_POST['api_key']),
            'api_secret' => sanitize_text_field($_POST['api_secret'])
        ];

        // Validate credentials
        $validation = $this->validateCredentials($credentials);
        if (!$validation['valid']) {
            wp_send_json_error(implode(', ', $validation['errors']));
        }

        // Store credentials securely
        foreach ($credentials as $key => $value) {
            if (!empty($value)) {
                $this->set_credential($key, $value);
            }
        }

        // Store other settings
        $settings = [
            'default_currency' => sanitize_text_field($_POST['default_currency']),
            'test_mode' => intval($_POST['test_mode'] ?? 0)
        ];
        $this->update_module_settings($settings);

        wp_send_json_success(['message' => 'Settings saved successfully']);
    }

    /**
     * AJAX handler for testing Stripe connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('newera_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $api_secret = sanitize_text_field($_POST['api_secret']);
        
        if (empty($api_secret)) {
            wp_send_json_error('API secret is required');
        }

        try {
            $temp_client = new \Stripe\StripeClient($api_secret);
            $account = $temp_client->accounts->retrieve();
            
            wp_send_json_success([
                'message' => 'Connection successful',
                'account' => [
                    'id' => $account->id,
                    'type' => $account->type,
                    'country' => $account->country,
                    'charges_enabled' => $account->charges_enabled,
                    'payouts_enabled' => $account->payouts_enabled
                ]
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for registering webhooks
     */
    public function ajax_register_webhooks() {
        check_ajax_referer('newera_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        if (!$this->stripe_client) {
            wp_send_json_error('Stripe not initialized');
        }

        try {
            $webhook_url = home_url('/newera-stripe-webhook/');
            
            // List existing webhook endpoints
            $endpoints = $this->stripe_client->webhookEndpoints->all([
                'limit' => 100
            ]);
            
            // Check if endpoint already exists
            foreach ($endpoints->data as $endpoint) {
                if ($endpoint->url === $webhook_url) {
                    $this->set_credential('webhook_endpoint_id', $endpoint->id);
                    wp_send_json_success(['message' => 'Webhook already registered', 'endpoint_id' => $endpoint->id]);
                    return;
                }
            }

            // Create new webhook endpoint
            $webhook_events = [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'invoice.payment_succeeded',
                'invoice.payment_failed',
                'customer.subscription.trial_will_end'
            ];

            $endpoint = $this->stripe_client->webhookEndpoints->create([
                'url' => $webhook_url,
                'enabled_events' => $webhook_events
            ]);

            $this->set_credential('webhook_endpoint_id', $endpoint->id);

            wp_send_json_success([
                'message' => 'Webhook registered successfully',
                'endpoint_id' => $endpoint->id
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to register webhook: ' . $e->getMessage());
        }
    }

    /**
     * Setup wizard completion handler
     * Called when setup wizard is completed to process payment settings
     */
    public function setup_wizard_completed() {
        $wizard_state = $this->state_manager->get_state_value('setup_wizard', []);
        $payments_data = $wizard_state['data']['payments'] ?? [];

        if (($payments_data['provider'] ?? '') === 'stripe') {
            // Validate and store Stripe credentials
            $credentials = [
                'api_key' => $payments_data['stripe_key'] ?? '',
                'api_secret' => $payments_data['stripe_secret'] ?? ''
            ];

            $validation = $this->validateCredentials($credentials);
            if ($validation['valid']) {
                // Store credentials securely
                $this->set_credential('api_key', $credentials['api_key']);
                $this->set_credential('api_secret', $credentials['api_secret']);

                // Store additional settings
                $settings = [
                    'default_currency' => $payments_data['default_currency'] ?? 'usd',
                    'test_mode' => strpos($credentials['api_key'], 'pk_test_') === 0 ? 1 : 0,
                    'provider' => 'stripe'
                ];
                $this->update_module_settings($settings);

                $this->log_info('Stripe configured via setup wizard', [
                    'currency' => $settings['default_currency'],
                    'test_mode' => $settings['test_mode']
                ]);

                // Initialize Stripe and register webhooks
                if ($this->init_stripe()) {
                    $this->register_webhooks_automatically();
                }

                return true;
            } else {
                $this->log_error('Stripe validation failed during setup wizard', [
                    'errors' => $validation['errors']
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Automatically register webhooks during setup
     */
    private function register_webhooks_automatically() {
        try {
            $webhook_url = home_url('/newera-stripe-webhook/');
            
            // List existing webhook endpoints
            $endpoints = $this->stripe_client->webhookEndpoints->all(['limit' => 100]);
            
            // Check if endpoint already exists
            foreach ($endpoints->data as $endpoint) {
                if ($endpoint->url === $webhook_url) {
                    $this->set_credential('webhook_endpoint_id', $endpoint->id);
                    $this->log_info('Webhook already registered during setup', ['endpoint_id' => $endpoint->id]);
                    return;
                }
            }

            // Create new webhook endpoint
            $webhook_events = [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'invoice.payment_succeeded',
                'invoice.payment_failed',
                'customer.subscription.trial_will_end'
            ];

            $endpoint = $this->stripe_client->webhookEndpoints->create([
                'url' => $webhook_url,
                'enabled_events' => $webhook_events
            ]);

            $this->set_credential('webhook_endpoint_id', $endpoint->id);

            $this->log_info('Webhooks registered automatically during setup', [
                'endpoint_id' => $endpoint->id
            ]);

        } catch (\Exception $e) {
            $this->log_error('Failed to register webhooks during setup', [
                'error' => $e->getMessage()
            ]);
        }
    }
}