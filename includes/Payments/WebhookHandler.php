<?php
/**
 * Webhook Handler for Newera Plugin
 *
 * Processes Stripe webhook events.
 */

namespace Newera\Payments;

use Newera\Core\Logger;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WebhookHandler class
 */
class WebhookHandler {
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
     * SubscriptionRepository instance
     *
     * @var SubscriptionRepository
     */
    private $subscription_repo;

    /**
     * Constructor
     *
     * @param StateManager $state_manager
     * @param Logger $logger
     * @param StripeManager $stripe
     */
    public function __construct($state_manager = null, $logger = null, $stripe = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : new StateManager();
        $this->logger = $logger instanceof Logger ? $logger : new Logger();
        $this->stripe = $stripe instanceof StripeManager ? $stripe : new StripeManager($this->state_manager, $this->logger);
        $this->subscription_repo = new SubscriptionRepository();
    }

    /**
     * Handle webhook request
     *
     * @param array $event Webhook event data
     * @return bool
     */
    public function handle_event($event) {
        try {
            $event_type = isset($event['type']) ? $event['type'] : '';

            if (empty($event_type)) {
                $this->logger->warning('Webhook event missing type');
                return false;
            }

            // Route to specific handler
            switch ($event_type) {
                case 'customer.subscription.created':
                    return $this->handle_subscription_created($event);

                case 'customer.subscription.updated':
                    return $this->handle_subscription_updated($event);

                case 'customer.subscription.deleted':
                    return $this->handle_subscription_deleted($event);

                case 'charge.succeeded':
                    return $this->handle_charge_succeeded($event);

                case 'charge.failed':
                    return $this->handle_charge_failed($event);

                case 'invoice.payment_succeeded':
                    return $this->handle_invoice_payment_succeeded($event);

                case 'invoice.payment_failed':
                    return $this->handle_invoice_payment_failed($event);

                case 'customer.created':
                case 'customer.updated':
                case 'customer.deleted':
                case 'charge.refunded':
                case 'invoice.created':
                case 'invoice.finalized':
                case 'payment_intent.succeeded':
                case 'payment_intent.payment_failed':
                    // Log but don't fail for unhandled events
                    $this->logger->info('Webhook event received', ['type' => $event_type]);
                    return true;

                default:
                    $this->logger->warning('Unknown webhook event type', ['type' => $event_type]);
                    return true;
            }
        } catch (\Exception $e) {
            $this->logger->error('Webhook handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Handle subscription created event
     *
     * @param array $event
     * @return bool
     */
    private function handle_subscription_created($event) {
        try {
            $subscription = isset($event['data']['object']) ? $event['data']['object'] : [];

            if (empty($subscription['id'])) {
                return false;
            }

            $customer_id = $subscription['customer'] ?? '';
            $plan = isset($subscription['items']['data'][0]['price']['id']) ? $subscription['items']['data'][0]['price']['id'] : '';
            $status = $subscription['status'] ?? 'active';

            // Get WordPress client by Stripe customer ID
            $client_id = $this->get_client_by_stripe_id($customer_id);

            if (!$client_id) {
                $this->logger->warning('Subscription webhook: client not found', [
                    'stripe_customer_id' => $customer_id,
                    'stripe_subscription_id' => $subscription['id'],
                ]);
                return false;
            }

            // Create subscription record
            $result = $this->subscription_repo->create([
                'client_id' => $client_id,
                'plan' => $plan,
                'status' => $status,
                'amount' => isset($subscription['items']['data'][0]['price']['unit_amount']) ? 
                    $subscription['items']['data'][0]['price']['unit_amount'] / 100 : 0,
                'billing_cycle' => isset($subscription['items']['data'][0]['price']['recurring']['interval']) ?
                    $subscription['items']['data'][0]['price']['recurring']['interval'] : 'month',
                'start_date' => date('Y-m-d', $subscription['current_period_start'] ?? time()),
                'end_date' => date('Y-m-d', $subscription['current_period_end'] ?? time()),
                'auto_renew' => 1,
            ]);

            if ($result) {
                $this->logger->info('Subscription created from webhook', [
                    'subscription_id' => $result,
                    'stripe_subscription_id' => $subscription['id'],
                ]);

                do_action('newera_subscription_created', $result, $subscription);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle subscription created event', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle subscription updated event
     *
     * @param array $event
     * @return bool
     */
    private function handle_subscription_updated($event) {
        try {
            $subscription = isset($event['data']['object']) ? $event['data']['object'] : [];

            if (empty($subscription['id'])) {
                return false;
            }

            // Find subscription by Stripe ID (would need to store this mapping)
            $stripe_subscription_id = $subscription['id'];
            $status = $subscription['status'] ?? 'active';

            $this->logger->info('Subscription updated from webhook', [
                'stripe_subscription_id' => $stripe_subscription_id,
                'status' => $status,
            ]);

            do_action('newera_subscription_updated', $stripe_subscription_id, $subscription);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle subscription updated event', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle subscription deleted event
     *
     * @param array $event
     * @return bool
     */
    private function handle_subscription_deleted($event) {
        try {
            $subscription = isset($event['data']['object']) ? $event['data']['object'] : [];

            if (empty($subscription['id'])) {
                return false;
            }

            $stripe_subscription_id = $subscription['id'];

            $this->logger->info('Subscription deleted from webhook', [
                'stripe_subscription_id' => $stripe_subscription_id,
            ]);

            do_action('newera_subscription_deleted', $stripe_subscription_id, $subscription);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle subscription deleted event', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle charge succeeded event
     *
     * @param array $event
     * @return bool
     */
    private function handle_charge_succeeded($event) {
        try {
            $charge = isset($event['data']['object']) ? $event['data']['object'] : [];

            if (empty($charge['id'])) {
                return false;
            }

            $this->logger->info('Charge succeeded from webhook', [
                'charge_id' => $charge['id'],
                'amount' => $charge['amount'] ?? 0,
                'currency' => $charge['currency'] ?? 'usd',
            ]);

            do_action('newera_charge_succeeded', $charge['id'], $charge);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle charge succeeded event', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle charge failed event
     *
     * @param array $event
     * @return bool
     */
    private function handle_charge_failed($event) {
        try {
            $charge = isset($event['data']['object']) ? $event['data']['object'] : [];

            if (empty($charge['id'])) {
                return false;
            }

            $this->logger->error('Charge failed from webhook', [
                'charge_id' => $charge['id'],
                'failure_message' => $charge['failure_message'] ?? '',
            ]);

            do_action('newera_charge_failed', $charge['id'], $charge);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle charge failed event', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle invoice payment succeeded event
     *
     * @param array $event
     * @return bool
     */
    private function handle_invoice_payment_succeeded($event) {
        try {
            $invoice = isset($event['data']['object']) ? $event['data']['object'] : [];

            if (empty($invoice['id'])) {
                return false;
            }

            $this->logger->info('Invoice payment succeeded from webhook', [
                'invoice_id' => $invoice['id'],
            ]);

            do_action('newera_invoice_payment_succeeded', $invoice['id'], $invoice);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle invoice payment succeeded event', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle invoice payment failed event
     *
     * @param array $event
     * @return bool
     */
    private function handle_invoice_payment_failed($event) {
        try {
            $invoice = isset($event['data']['object']) ? $event['data']['object'] : [];

            if (empty($invoice['id'])) {
                return false;
            }

            $this->logger->error('Invoice payment failed from webhook', [
                'invoice_id' => $invoice['id'],
            ]);

            do_action('newera_invoice_payment_failed', $invoice['id'], $invoice);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle invoice payment failed event', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get client ID by Stripe customer ID
     *
     * @param string $stripe_customer_id
     * @return int|false
     */
    private function get_client_by_stripe_id($stripe_customer_id) {
        global $wpdb;

        $client_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}clients WHERE stripe_customer_id = %s LIMIT 1",
            $stripe_customer_id
        ));

        return $client_id ? intval($client_id) : false;
    }
}
