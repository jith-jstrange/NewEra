<?php
/**
 * Plan Manager for Newera Plugin
 *
 * Manages pricing tiers and plans.
 */

namespace Newera\Payments;

use Newera\Core\Logger;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PlanManager class
 */
class PlanManager {
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
    }

    /**
     * Create a plan
     *
     * @param array $plan Plan data
     * @return array|false
     */
    public function create_plan($plan) {
        try {
            // Validate plan data
            $errors = $this->validate_plan($plan);
            if (!empty($errors)) {
                $this->logger->warning('Plan validation failed', ['errors' => $errors]);
                return false;
            }

            // Store plan locally first
            $plans = $this->get_all_plans();
            $plan_id = sanitize_key($plan['id']);

            if (isset($plans[$plan_id])) {
                $this->logger->warning('Plan already exists', ['plan_id' => $plan_id]);
                return false;
            }

            // Prepare Stripe price data
            $stripe_data = $this->prepare_stripe_price_data($plan);

            // Create in Stripe if configured
            if ($this->stripe->is_configured()) {
                $stripe_result = $this->stripe->create_price($stripe_data);
                if (!$stripe_result) {
                    $this->logger->error('Failed to create price in Stripe', ['plan' => $plan]);
                    return false;
                }

                $plan['stripe_price_id'] = $stripe_result['id'];
                $plan['stripe_product_id'] = $stripe_result['product'];
            }

            $plan['created_at'] = current_time('mysql');
            $plan['updated_at'] = current_time('mysql');

            $plans[$plan_id] = $plan;
            $this->save_plans($plans);

            $this->logger->info('Plan created', ['plan_id' => $plan_id]);

            return $plan;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create plan', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get a plan by ID
     *
     * @param string $plan_id Plan ID
     * @return array|null
     */
    public function get_plan($plan_id) {
        $plans = $this->get_all_plans();
        return isset($plans[$plan_id]) ? $plans[$plan_id] : null;
    }

    /**
     * Get all plans
     *
     * @return array
     */
    public function get_all_plans() {
        try {
            $plans = $this->state_manager->get_setting('stripe_plans', []);
            return is_array($plans) ? $plans : [];
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get plans', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Update a plan
     *
     * @param string $plan_id Plan ID
     * @param array $data Update data
     * @return array|false
     */
    public function update_plan($plan_id, $data) {
        try {
            $plan = $this->get_plan($plan_id);
            if (!$plan) {
                $this->logger->warning('Plan not found', ['plan_id' => $plan_id]);
                return false;
            }

            $plan = array_merge($plan, $data);
            $errors = $this->validate_plan($plan);

            if (!empty($errors)) {
                $this->logger->warning('Plan validation failed', ['errors' => $errors]);
                return false;
            }

            $plan['updated_at'] = current_time('mysql');

            $plans = $this->get_all_plans();
            $plans[$plan_id] = $plan;
            $this->save_plans($plans);

            $this->logger->info('Plan updated', ['plan_id' => $plan_id]);

            return $plan;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update plan', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete a plan
     *
     * @param string $plan_id Plan ID
     * @return bool
     */
    public function delete_plan($plan_id) {
        try {
            $plans = $this->get_all_plans();

            if (!isset($plans[$plan_id])) {
                $this->logger->warning('Plan not found', ['plan_id' => $plan_id]);
                return false;
            }

            unset($plans[$plan_id]);
            $this->save_plans($plans);

            $this->logger->info('Plan deleted', ['plan_id' => $plan_id]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete plan', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Validate plan data
     *
     * @param array $plan Plan data
     * @return array Errors
     */
    private function validate_plan($plan) {
        $errors = [];

        if (empty($plan['id'])) {
            $errors['id'] = 'Plan ID is required';
        }

        if (empty($plan['name'])) {
            $errors['name'] = 'Plan name is required';
        }

        if (!isset($plan['amount']) || $plan['amount'] < 0) {
            $errors['amount'] = 'Valid amount is required';
        }

        if (empty($plan['currency'])) {
            $plan['currency'] = 'usd';
        }

        if (empty($plan['interval'])) {
            $errors['interval'] = 'Billing interval is required (month, year, etc)';
        }

        return $errors;
    }

    /**
     * Prepare Stripe price data from plan
     *
     * @param array $plan Plan data
     * @return array
     */
    private function prepare_stripe_price_data($plan) {
        // Convert amount to cents if not already
        $amount = intval($plan['amount'] * 100);

        $data = [
            'unit_amount' => $amount,
            'currency' => strtolower($plan['currency'] ?? 'usd'),
            'recurring' => [
                'interval' => $plan['interval'],
            ],
        ];

        if (isset($plan['interval_count'])) {
            $data['recurring']['interval_count'] = intval($plan['interval_count']);
        }

        if (!empty($plan['stripe_product_id'])) {
            $data['product'] = $plan['stripe_product_id'];
        } else {
            // Create product first
            $product_data = [
                'name' => $plan['name'],
                'type' => 'service',
            ];

            if (!empty($plan['description'])) {
                $product_data['description'] = $plan['description'];
            }

            $product = $this->create_stripe_product($product_data);
            if ($product && isset($product['id'])) {
                $data['product'] = $product['id'];
            }
        }

        return $data;
    }

    /**
     * Create Stripe product via direct API call
     *
     * @param array $data Product data
     * @return array|false
     */
    private function create_stripe_product($data) {
        if (!$this->stripe->is_configured()) {
            return false;
        }

        return $this->stripe->api_request('POST', '/products', $data);
    }

    /**
     * Get Stripe API key from state manager
     *
     * @return string|null
     */
    private function get_stripe_api_key() {
        return $this->state_manager->getSecure('payments', 'stripe_api_key');
    }

    /**
     * Save plans to state manager
     *
     * @param array $plans Plans
     * @return bool
     */
    private function save_plans($plans) {
        return $this->state_manager->update_setting('stripe_plans', $plans);
    }

    /**
     * Get plan by Stripe price ID
     *
     * @param string $stripe_price_id Stripe price ID
     * @return array|null
     */
    public function get_plan_by_stripe_price($stripe_price_id) {
        $plans = $this->get_all_plans();

        foreach ($plans as $plan) {
            if (isset($plan['stripe_price_id']) && $plan['stripe_price_id'] === $stripe_price_id) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Get available plans for display
     *
     * @return array
     */
    public function get_available_plans() {
        $plans = $this->get_all_plans();
        return array_filter($plans, function($plan) {
            return !isset($plan['archived']) || !$plan['archived'];
        });
    }
}
