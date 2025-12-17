<?php
/**
 * Payments Module for Newera Plugin
 *
 * Full Stripe integration with subscriptions, one-time charges, and webhooks.
 */

namespace Newera\Modules\Payments;

use Newera\Modules\BaseModule;
use Newera\Payments\StripeManager;
use Newera\Payments\PlanManager;
use Newera\Payments\SubscriptionRepository;
use Newera\Payments\WebhookEndpoint;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PaymentsModule
 */
class PaymentsModule extends BaseModule {
    /**
     * Stripe Manager instance
     *
     * @var StripeManager
     */
    private $stripe_manager;

    /**
     * Plan Manager instance
     *
     * @var PlanManager
     */
    private $plan_manager;

    /**
     * Subscription Repository instance
     *
     * @var SubscriptionRepository
     */
    private $subscription_repo;

    /**
     * Webhook Endpoint instance
     *
     * @var WebhookEndpoint
     */
    private $webhook_endpoint;

    /**
     * @return string
     */
    public function getId() {
        return 'payments';
    }

    /**
     * @return string
     */
    public function getName() {
        return 'Payments';
    }

    /**
     * @return string
     */
    public function getDescription() {
        return 'Complete Stripe payment integration with subscriptions and webhooks.';
    }

    /**
     * @return string
     */
    public function getType() {
        return 'payments';
    }

    /**
     * @return array
     */
    public function getSettingsSchema() {
        return [
            'credentials' => [
                'stripe_api_key' => [
                    'type' => 'string',
                    'label' => 'Stripe Secret API Key',
                    'required' => true,
                    'secure' => true,
                    'description' => 'Get this from your Stripe Dashboard: https://dashboard.stripe.com/apikeys',
                ],
                'stripe_public_key' => [
                    'type' => 'string',
                    'label' => 'Stripe Publishable Key',
                    'required' => true,
                    'secure' => true,
                    'description' => 'Get this from your Stripe Dashboard: https://dashboard.stripe.com/apikeys',
                ],
                'stripe_webhook_secret' => [
                    'type' => 'string',
                    'label' => 'Stripe Webhook Signing Secret',
                    'required' => false,
                    'secure' => true,
                    'description' => 'Generated automatically when webhook is created in Stripe',
                ],
            ],
            'settings' => [
                'mode' => [
                    'type' => 'string',
                    'label' => 'Environment Mode',
                    'required' => false,
                    'default' => 'test',
                    'options' => ['test', 'live'],
                    'description' => 'Use test mode for development, live mode for production',
                ],
                'auto_register_webhook' => [
                    'type' => 'boolean',
                    'label' => 'Auto-register Webhook',
                    'required' => false,
                    'default' => true,
                    'description' => 'Automatically register webhook endpoint with Stripe',
                ],
            ],
        ];
    }

    /**
     * @param array $credentials
     * @return array
     */
    public function validateCredentials($credentials) {
        $errors = [];

        if (empty($credentials['stripe_api_key'])) {
            $errors['stripe_api_key'] = 'Stripe API key is required.';
        }

        if (empty($credentials['stripe_public_key'])) {
            $errors['stripe_public_key'] = 'Stripe publishable key is required.';
        }

        // Test the API key if provided
        if (!empty($credentials['stripe_api_key'])) {
            $stripe = new StripeManager($this->state_manager, $this->logger);
            $stripe->set_credentials(
                $credentials['stripe_api_key'],
                $credentials['stripe_public_key'],
                $credentials['stripe_webhook_secret'] ?? '',
                $credentials['mode'] ?? 'test'
            );

            if (!$stripe->test_api_key()) {
                $errors['stripe_api_key'] = 'Failed to connect to Stripe. Please verify your API key.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @param array $credentials
     * @return bool
     */
    public function saveCredentials($credentials) {
        $validation = $this->validateCredentials($credentials);
        if (!$validation['valid']) {
            $this->log_warning('Stripe credentials validation failed', [
                'errors' => $validation['errors'],
            ]);
            return false;
        }

        try {
            $stripe = new StripeManager($this->state_manager, $this->logger);
            $mode = $credentials['mode'] ?? 'test';
            
            $result = $stripe->set_credentials(
                $credentials['stripe_api_key'],
                $credentials['stripe_public_key'],
                $credentials['stripe_webhook_secret'] ?? '',
                $mode
            );

            if (!$result) {
                $this->log_error('Failed to save Stripe credentials');
                return false;
            }

            // Auto-register webhook if enabled
            if (!empty($credentials['auto_register_webhook']) && $credentials['auto_register_webhook']) {
                $webhook_url = WebhookEndpoint::get_webhook_url();
                $webhook_result = $stripe->register_webhook($webhook_url);

                if ($webhook_result && isset($webhook_result['id'])) {
                    $this->log_info('Stripe webhook registered', [
                        'webhook_id' => $webhook_result['id'],
                        'url' => $webhook_url,
                    ]);

                    // Store webhook ID for reference
                    $this->set_credential('webhook_endpoint_id', $webhook_result['id']);
                } else {
                    $this->log_warning('Failed to auto-register webhook with Stripe', [
                        'url' => $webhook_url,
                    ]);
                }
            }

            $this->log_info('Stripe credentials saved successfully', ['mode' => $mode]);
            return true;
        } catch (\Exception $e) {
            $this->log_error('Exception saving credentials: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isConfigured() {
        return $this->has_credential('stripe_api_key') && $this->has_credential('stripe_public_key');
    }

    /**
     * Register hooks only when active.
     */
    public function registerHooks() {
        add_action('init', [$this, 'init_payments']);
        add_action('admin_menu', [$this, 'register_admin_menu'], 15);

        // Initialize managers
        $this->init_managers();

        // Register webhook endpoint
        if ($this->webhook_endpoint) {
            $this->webhook_endpoint->register();
        }
    }

    /**
     * Initialize payment managers
     */
    private function init_managers() {
        try {
            $this->stripe_manager = new StripeManager($this->state_manager, $this->logger);
            $this->plan_manager = new PlanManager($this->state_manager, $this->logger, $this->stripe_manager);
            $this->subscription_repo = new SubscriptionRepository();
            $this->webhook_endpoint = new WebhookEndpoint($this->state_manager, $this->logger);

            // Expose managers via filters for other modules
            add_filter('newera_get_stripe_manager', function() {
                return $this->stripe_manager;
            });

            add_filter('newera_get_plan_manager', function() {
                return $this->plan_manager;
            });

            add_filter('newera_get_subscription_repo', function() {
                return $this->subscription_repo;
            });
        } catch (\Exception $e) {
            $this->log_error('Failed to initialize managers: ' . $e->getMessage());
        }
    }

    /**
     * Initialize payments hook
     */
    public function init_payments() {
        $this->log_info('Payments module initialized', [
            'configured' => $this->isConfigured(),
            'mode' => $this->stripe_manager ? $this->stripe_manager->get_mode() : 'unknown',
        ]);
    }

    /**
     * Register admin menu for plan management
     */
    public function register_admin_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_submenu_page(
            'newera',
            __('Payment Plans', 'newera'),
            __('Payment Plans', 'newera'),
            'manage_options',
            'newera-payment-plans',
            [$this, 'render_plans_page']
        );
    }

    /**
     * Render plans management page
     */
    public function render_plans_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'newera'));
        }

        // Handle form submissions
        $notice = null;

        if (isset($_POST['newera_create_plan'])) {
            $this->handle_create_plan($_POST);
        } elseif (isset($_POST['newera_update_plan'])) {
            $this->handle_update_plan($_POST);
        } elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['plan_id'])) {
            $this->handle_delete_plan($_GET['plan_id']);
        }

        // Get all plans
        if (!$this->plan_manager) {
            $this->init_managers();
        }

        $plans = $this->plan_manager ? $this->plan_manager->get_all_plans() : [];

        include NEWERA_PLUGIN_PATH . 'templates/admin/payment-plans.php';
    }

    /**
     * Handle create plan form
     *
     * @param array $data POST data
     */
    private function handle_create_plan($data) {
        if (!isset($data['nonce']) || !wp_verify_nonce($data['nonce'], 'newera_create_plan')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Security check failed', 'newera');
                echo '</p></div>';
            });
            return;
        }

        $plan = [
            'id' => sanitize_key($_POST['plan_id'] ?? ''),
            'name' => sanitize_text_field($_POST['plan_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['plan_description'] ?? ''),
            'amount' => floatval($_POST['plan_amount'] ?? 0),
            'currency' => sanitize_key($_POST['plan_currency'] ?? 'usd'),
            'interval' => sanitize_key($_POST['plan_interval'] ?? 'month'),
            'interval_count' => intval($_POST['plan_interval_count'] ?? 1),
        ];

        if (!$this->plan_manager) {
            $this->init_managers();
        }

        $result = $this->plan_manager->create_plan($plan);

        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>';
                echo __('Plan created successfully', 'newera');
                echo '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Failed to create plan', 'newera');
                echo '</p></div>';
            });
        }
    }

    /**
     * Handle update plan form
     *
     * @param array $data POST data
     */
    private function handle_update_plan($data) {
        if (!isset($data['nonce']) || !wp_verify_nonce($data['nonce'], 'newera_update_plan')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Security check failed', 'newera');
                echo '</p></div>';
            });
            return;
        }

        $plan_id = sanitize_key($_POST['plan_id'] ?? '');

        if (!$this->plan_manager) {
            $this->init_managers();
        }

        $plan = [
            'name' => sanitize_text_field($_POST['plan_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['plan_description'] ?? ''),
            'amount' => floatval($_POST['plan_amount'] ?? 0),
            'currency' => sanitize_key($_POST['plan_currency'] ?? 'usd'),
            'interval' => sanitize_key($_POST['plan_interval'] ?? 'month'),
            'interval_count' => intval($_POST['plan_interval_count'] ?? 1),
        ];

        $result = $this->plan_manager->update_plan($plan_id, $plan);

        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>';
                echo __('Plan updated successfully', 'newera');
                echo '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Failed to update plan', 'newera');
                echo '</p></div>';
            });
        }
    }

    /**
     * Handle delete plan
     *
     * @param string $plan_id Plan ID
     */
    private function handle_delete_plan($plan_id) {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'newera'));
        }

        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'newera_delete_plan')) {
            wp_die(__('Security check failed', 'newera'));
        }

        if (!$this->plan_manager) {
            $this->init_managers();
        }

        $result = $this->plan_manager->delete_plan($plan_id);

        wp_safe_redirect(admin_url('admin.php?page=newera-payment-plans'));
        exit;
    }
}
