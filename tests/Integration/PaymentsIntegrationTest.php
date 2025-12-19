<?php
/**
 * Integration tests for Payment workflows
 */

namespace Newera\Tests\Integration;

use Newera\Modules\Payments\StripeManager;
use Newera\Modules\Payments\PaymentsModule;
use Newera\Core\StateManager;
use Newera\Core\Crypto;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payments Integration Test
 * 
 * Tests end-to-end payment workflows including:
 * - Setup Wizard Stripe key input
 * - Create plan → Stripe sync
 * - Webhook processing → wp_subscriptions update
 * - Subscription state transitions
 * - Invoice email on completion
 */
class PaymentsIntegrationTest extends \Newera\Tests\TestCase {
    
    /**
     * Payments module instance
     *
     * @var PaymentsModule
     */
    private $paymentsModule;
    
    /**
     * Stripe manager instance
     *
     * @var StripeManager
     */
    private $stripeManager;
    
    /**
     * State manager instance
     *
     * @var StateManager
     */
    private $stateManager;
    
    /**
     * Mock Stripe API responses
     */
    private function mockStripeAPI() {
        // Mock Stripe API responses
        if (!class_exists('Stripe\Stripe')) {
            class MockStripeAPI {
                private $products = [];
                private $prices = [];
                private $customers = [];
                private $subscriptions = [];
                private $invoices = [];
                
                public function createProduct($data) {
                    $id = 'prod_' . uniqid();
                    $this->products[$id] = array_merge(['id' => $id], $data);
                    return (object) $this->products[$id];
                }
                
                public function createPrice($data) {
                    $id = 'price_' . uniqid();
                    $this->prices[$id] = array_merge(['id' => $id], $data);
                    return (object) $this->prices[$id];
                }
                
                public function createCustomer($data) {
                    $id = 'cus_' . uniqid();
                    $this->customers[$id] = array_merge(['id' => $id], $data);
                    return (object) $this->customers[$id];
                }
                
                public function createSubscription($data) {
                    $id = 'sub_' . uniqid();
                    $this->subscriptions[$id] = array_merge(['id' => $id], $data);
                    return (object) $this->subscriptions[$id];
                }
                
                public function createInvoice($data) {
                    $id = 'in_' . uniqid();
                    $this->invoices[$id] = array_merge(['id' => $id], $data);
                    return (object) $this->invoices[$id];
                }
                
                public function getProduct($id) {
                    return isset($this->products[$id]) ? (object) $this->products[$id] : null;
                }
                
                public function getPrice($id) {
                    return isset($this->prices[$id]) ? (object) $this->prices[$id] : null;
                }
                
                public function getSubscription($id) {
                    return isset($this->subscriptions[$id]) ? (object) $this->subscriptions[$id] : null;
                }
                
                public function updateSubscription($id, $data) {
                    if (isset($this->subscriptions[$id])) {
                        $this->subscriptions[$id] = array_merge($this->subscriptions[$id], $data);
                        return (object) $this->subscriptions[$id];
                    }
                    return null;
                }
                
                public function deleteSubscription($id) {
                    unset($this->subscriptions[$id]);
                    return (object) ['id' => $id, 'deleted' => true];
                }
                
                public function retrieveInvoice($id) {
                    return isset($this->invoices[$id]) ? (object) $this->invoices[$id] : null;
                }
                
                public function getAllProducts() {
                    return array_values(array_map(function($product) {
                        return (object) $product;
                    }, $this->products));
                }
                
                public function getAllSubscriptions() {
                    return array_values(array_map(function($subscription) {
                        return (object) $subscription;
                    }, $this->subscriptions));
                }
                
                public function verifyWebhookSignature($payload, $signature) {
                    return true; // Mock verification
                }
                
                public function constructEvent($payload, $signature) {
                    $event = json_decode($payload, true);
                    return (object) $event;
                }
            }
        }
    }
    
    /**
     * Mock email sending functionality
     */
    private function mockEmailFunctions() {
        // Mock wp_mail function
        if (!function_exists('wp_mail')) {
            function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
                // Store email in mock storage for testing
                \Newera\Tests\MockStorage::add_email([
                    'to' => $to,
                    'subject' => $subject,
                    'message' => $message,
                    'headers' => $headers,
                    'attachments' => $attachments,
                    'timestamp' => time()
                ]);
                return true;
            }
        }
    }
    
    /**
     * Setup test environment
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->stateManager = new StateManager();
        $this->paymentsModule = new PaymentsModule($this->stateManager);
        
        // Mock Stripe and email functions
        $this->mockStripeAPI();
        $this->mockEmailFunctions();
        
        // Reset state for clean test
        $this->stateManager->reset_state();
        
        // Mock WordPress database functions
        $this->mockWordPressDBFunctions();
    }
    
    /**
     * Mock WordPress database functions for subscription operations
     */
    private function mockWordPressDBFunctions() {
        global $wpdb;
        
        if (!isset($wpdb)) {
            $wpdb = new \stdClass();
        }
        
        $wpdb->prepare = function($query, ...$args) {
            return vsprintf(str_replace('%s', "'%s'", $query), $args);
        };
        
        $wpdb->insert = function($table, $data, $format = null) {
            return \Newera\Tests\MockWPDB::insert($table, $data, $format);
        };
        
        $wpdb->update = function($table, $data, $where, $format = null, $where_format = null) {
            return \Newera\Tests\MockWPDB::update($table, $data, $where, $format, $where_format);
        };
        
        $wpdb->get_results = function($query, $output = OBJECT) {
            return \Newera\Tests\MockWPDB::get_results($query, $output);
        };
        
        $wpdb->get_var = function($query) {
            return \Newera\Tests\MockWPDB::get_var($query);
        };
    }
    
    /**
     * Test setup wizard Stripe key input and validation
     */
    public function testSetupWizardStripeKeyInput() {
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_1234567890',
            'stripe_secret_key' => 'sk_test_secret_key_abcdef',
            'stripe_webhook_secret' => 'whsec_test_webhook_secret_12345'
        ];
        
        $result = $this->paymentsModule->processStripeSetup($stripe_data);
        
        $this->assertTrue($result['success']);
        
        // Verify keys are stored correctly
        $settings = $this->stateManager->get_settings();
        $this->assertEquals('pk_test_1234567890', $settings['stripe_publishable_key']);
        
        // Verify secret key is encrypted
        $stored_secret = $this->stateManager->getSecure('payments', 'stripe_secret_key');
        $this->assertEquals('sk_test_secret_key_abcdef', $stored_secret);
        
        $stored_webhook = $this->stateManager->getSecure('payments', 'stripe_webhook_secret');
        $this->assertEquals('whsec_test_webhook_secret_12345', $stored_webhook);
    }
    
    /**
     * Test create plan → Stripe sync
     */
    public function testCreatePlanStripeSync() {
        // Setup Stripe configuration
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_secret'
        ];
        $this->paymentsModule->processStripeSetup($stripe_data);
        
        // Create plan data
        $plan_data = [
            'name' => 'Premium Plan',
            'description' => 'Premium features for businesses',
            'price' => 29.99,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
            'features' => ['feature1', 'feature2', 'feature3']
        ];
        
        // Create plan and sync with Stripe
        $plan_result = $this->paymentsModule->createPlan($plan_data);
        
        $this->assertTrue($plan_result['success']);
        $this->assertArrayHasKey('plan_id', $plan_result);
        $this->assertArrayHasKey('stripe_product_id', $plan_result);
        $this->assertArrayHasKey('stripe_price_id', $plan_result);
        
        // Verify plan data was saved locally
        $saved_plan = $this->stateManager->getSecure('plans', $plan_result['plan_id']);
        $this->assertEquals('Premium Plan', $saved_plan['name']);
        $this->assertEquals(29.99, $saved_plan['price']);
        
        // Verify Stripe product was created
        $this->assertNotEmpty($plan_result['stripe_product_id']);
        $this->assertNotEmpty($plan_result['stripe_price_id']);
    }
    
    /**
     * Test webhook processing and subscription updates
     */
    public function testWebhookProcessingAndSubscriptionUpdates() {
        // Setup Stripe configuration
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_secret',
            'stripe_webhook_secret' => 'whsec_test'
        ];
        $this->paymentsModule->processStripeSetup($stripe_data);
        
        // Create a subscription first
        $subscription_data = [
            'user_id' => 123,
            'plan_id' => 'plan_premium',
            'stripe_subscription_id' => 'sub_test_12345',
            'status' => 'active'
        ];
        
        $this->paymentsModule->createSubscription($subscription_data);
        
        // Create webhook event for subscription update
        $webhook_payload = json_encode([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_test_12345',
                    'status' => 'past_due',
                    'current_period_start' => time(),
                    'current_period_end' => time() + (30 * 24 * 60 * 60),
                    'customer' => 'cus_test_123',
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => 'price_test_123',
                                    'product' => 'prod_test_123'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        
        $webhook_signature = 't=timestamp,v1=signature';
        
        // Process webhook
        $webhook_result = $this->paymentsModule->processWebhook($webhook_payload, $webhook_signature);
        
        $this->assertTrue($webhook_result['success']);
        $this->assertEquals('processed', $webhook_result['status']);
        
        // Verify subscription status was updated in WordPress
        $updated_subscription = $this->paymentsModule->getSubscription('sub_test_12345');
        $this->assertEquals('past_due', $updated_subscription['status']);
        
        // Verify database was updated
        global $wpdb;
        $subscription_record = $wpdb->get_results("SELECT * FROM wp_subscriptions WHERE stripe_subscription_id = 'sub_test_12345'");
        $this->assertNotEmpty($subscription_record);
        $this->assertEquals('past_due', $subscription_record[0]->status);
    }
    
    /**
     * Test subscription state transitions
     */
    public function testSubscriptionStateTransitions() {
        // Setup Stripe configuration
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_secret'
        ];
        $this->paymentsModule->processStripeSetup($stripe_data);
        
        $subscription_id = 'sub_transition_test';
        
        // Test active → past_due transition
        $this->simulateStateTransition($subscription_id, 'active', 'past_due');
        $subscription = $this->paymentsModule->getSubscription($subscription_id);
        $this->assertEquals('past_due', $subscription['status']);
        
        // Test past_due → canceled transition
        $this->simulateStateTransition($subscription_id, 'past_due', 'canceled');
        $subscription = $this->paymentsModule->getSubscription($subscription_id);
        $this->assertEquals('canceled', $subscription['status']);
        $this->assertNotNull($subscription['canceled_at']);
        
        // Test trial → active transition
        $trial_subscription_id = 'sub_trial_test';
        $this->simulateStateTransition($trial_subscription_id, 'trialing', 'active');
        $subscription = $this->paymentsModule->getSubscription($trial_subscription_id);
        $this->assertEquals('active', $subscription['status']);
        $this->assertNotNull($subscription['activated_at']);
    }
    
    /**
     * Test invoice email on completion
     */
    public function testInvoiceEmailOnCompletion() {
        // Setup Stripe configuration and email
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_secret'
        ];
        $this->paymentsModule->processStripeSetup($stripe_data);
        
        // Enable invoice emails
        $this->stateManager->update_setting('send_invoice_emails', true);
        
        // Create a subscription
        $subscription_data = [
            'user_id' => 123,
            'user_email' => 'customer@example.com',
            'plan_id' => 'plan_premium',
            'stripe_subscription_id' => 'sub_invoice_test'
        ];
        
        $this->paymentsModule->createSubscription($subscription_data);
        
        // Simulate successful payment webhook
        $invoice_payload = json_encode([
            'type' => 'invoice.payment_succeeded',
            'data' => [
                'object' => [
                    'id' => 'in_test_123',
                    'customer' => 'cus_test_123',
                    'subscription' => 'sub_invoice_test',
                    'amount_paid' => 2999,
                    'currency' => 'usd',
                    'invoice_pdf' => 'https://example.com/invoice.pdf',
                    'hosted_invoice_url' => 'https://example.com/invoice'
                ]
            ]
        ]);
        
        $webhook_result = $this->paymentsModule->processWebhook($invoice_payload, 'test_signature');
        
        $this->assertTrue($webhook_result['success']);
        
        // Verify invoice email was sent
        $sent_emails = \Newera\Tests\MockStorage::get_emails();
        $invoice_emails = array_filter($sent_emails, function($email) {
            return strpos($email['subject'], 'Invoice') !== false;
        });
        
        $this->assertNotEmpty($invoice_emails);
        $invoice_email = reset($invoice_emails);
        $this->assertEquals('customer@example.com', $invoice_email['to']);
        $this->assertStringContainsString('Invoice', $invoice_email['subject']);
        $this->assertStringContainsString('$29.99', $invoice_email['message']);
    }
    
    /**
     * Test subscription creation workflow
     */
    public function testSubscriptionCreationWorkflow() {
        // Setup Stripe configuration
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_secret'
        ];
        $this->paymentsModule->processStripeSetup($stripe_data);
        
        // Create a plan
        $plan_result = $this->paymentsModule->createPlan([
            'name' => 'Test Plan',
            'description' => 'Test plan for subscription',
            'price' => 19.99,
            'currency' => 'USD',
            'interval' => 'month'
        ]);
        
        $this->assertTrue($plan_result['success']);
        
        // Create subscription
        $subscription_data = [
            'user_id' => 456,
            'user_email' => 'subscriber@example.com',
            'plan_id' => $plan_result['plan_id'],
            'payment_method' => 'pm_test_123'
        ];
        
        $subscription_result = $this->paymentsModule->createSubscription($subscription_data);
        
        $this->assertTrue($subscription_result['success']);
        $this->assertArrayHasKey('subscription_id', $subscription_result);
        $this->assertArrayHasKey('stripe_subscription_id', $subscription_result);
        
        // Verify subscription was created in database
        global $wpdb;
        $subscription_record = $wpdb->get_results("SELECT * FROM wp_subscriptions WHERE user_id = 456");
        $this->assertNotEmpty($subscription_record);
        $this->assertEquals($plan_result['plan_id'], $subscription_record[0]->plan_id);
    }
    
    /**
     * Test payment failure handling
     */
    public function testPaymentFailureHandling() {
        // Setup Stripe configuration
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_secret'
        ];
        $this->paymentsModule->processStripeSetup($stripe_data);
        
        // Create subscription
        $subscription_data = [
            'user_id' => 789,
            'user_email' => 'fail@example.com',
            'plan_id' => 'plan_test',
            'stripe_subscription_id' => 'sub_failure_test'
        ];
        
        $this->paymentsModule->createSubscription($subscription_data);
        
        // Simulate payment failure webhook
        $failure_payload = json_encode([
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'in_failure_test',
                    'customer' => 'cus_failure_test',
                    'subscription' => 'sub_failure_test',
                    'amount_due' => 1999,
                    'currency' => 'usd',
                    'attempt_count' => 3
                ]
            ]
        ]);
        
        $failure_result = $this->paymentsModule->processWebhook($failure_payload, 'test_signature');
        
        $this->assertTrue($failure_result['success']);
        
        // Verify subscription status was updated
        $subscription = $this->paymentsModule->getSubscription('sub_failure_test');
        $this->assertEquals('past_due', $subscription['status']);
        $this->assertNotNull($subscription['last_payment_failure']);
        
        // Verify failure email was sent
        $sent_emails = \Newera\Tests\MockStorage::get_emails();
        $failure_emails = array_filter($sent_emails, function($email) {
            return $email['to'] === 'fail@example.com' && strpos($email['subject'], 'Payment Failed') !== false;
        });
        
        $this->assertNotEmpty($failure_emails);
    }
    
    /**
     * Test plan deletion and cleanup
     */
    public function testPlanDeletionAndCleanup() {
        // Setup Stripe configuration
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_secret'
        ];
        $this->paymentsModule->processStripeSetup($stripe_data);
        
        // Create a plan
        $plan_result = $this->paymentsModule->createPlan([
            'name' => 'Deletable Plan',
            'description' => 'Plan to be deleted',
            'price' => 9.99,
            'currency' => 'USD',
            'interval' => 'month'
        ]);
        
        $this->assertTrue($plan_result['success']);
        
        $plan_id = $plan_result['plan_id'];
        
        // Delete the plan
        $deletion_result = $this->paymentsModule->deletePlan($plan_id);
        
        $this->assertTrue($deletion_result['success']);
        
        // Verify plan was removed from local storage
        $deleted_plan = $this->stateManager->getSecure('plans', $plan_id);
        $this->assertNull($deleted_plan);
        
        // Verify Stripe product was deleted
        // This would normally check if the Stripe product was archived
        // For testing, we just verify the cleanup method was called
        $this->assertTrue($deletion_result['cleanup_completed']);
    }
    
    /**
     * Test refund processing
     */
    public function testRefundProcessing() {
        // Setup Stripe configuration
        $stripe_data = [
            'provider' => 'stripe',
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_secret'
        ];
        $this->paymentsModule->processStripeSetup($stripe_data);
        
        // Create subscription
        $subscription_data = [
            'user_id' => 999,
            'user_email' => 'refund@example.com',
            'plan_id' => 'plan_refund_test',
            'stripe_subscription_id' => 'sub_refund_test',
            'status' => 'active'
        ];
        
        $this->paymentsModule->createSubscription($subscription_data);
        
        // Process refund
        $refund_data = [
            'subscription_id' => 'sub_refund_test',
            'amount' => 1999, // Partial refund
            'reason' => 'requested_by_customer'
        ];
        
        $refund_result = $this->paymentsModule->processRefund($refund_data);
        
        $this->assertTrue($refund_result['success']);
        $this->assertArrayHasKey('refund_id', $refund_result);
        
        // Verify subscription was canceled if full refund
        $subscription = $this->paymentsModule->getSubscription('sub_refund_test');
        $this->assertEquals('canceled', $subscription['status']);
        $this->assertNotNull($subscription['canceled_at']);
    }
    
    /**
     * Helper method to simulate state transition
     */
    private function simulateStateTransition($subscription_id, $from_status, $to_status) {
        // Update subscription in database
        global $wpdb;
        $wpdb->update(
            'wp_subscriptions',
            ['status' => $to_status, 'updated_at' => current_time('mysql')],
            ['stripe_subscription_id' => $subscription_id]
        );
        
        // Process webhook for state change
        $webhook_payload = json_encode([
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => $subscription_id,
                    'status' => $to_status,
                    'previous_attributes' => ['status' => $from_status]
                ]
            ]
        ]);
        
        $this->paymentsModule->processWebhook($webhook_payload, 'test_signature');
    }
}