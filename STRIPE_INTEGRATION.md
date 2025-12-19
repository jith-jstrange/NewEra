# Stripe Payment Integration

This document describes the complete Stripe payment integration for the Newera plugin, including subscriptions, one-time charges, and webhook processing.

## Overview

The Newera plugin includes a comprehensive Stripe payment module that handles:

- **API Key Management**: Secure encrypted storage of Stripe credentials
- **Subscription Management**: Create, update, and cancel subscriptions with Stripe sync
- **One-Time Payments**: Process single charges and payment intents
- **Webhook Processing**: Automatic webhook signature verification and event handling
- **Plan Management**: Create and manage pricing tiers in the admin interface
- **Subscription Tracking**: Database synchronization with the `wp_subscriptions` table
- **Error Handling**: Graceful degradation if Stripe is unreachable
- **Tax & Region Support**: Basic currency and region support

## Architecture

### Core Components

#### 1. StripeManager (`includes/Payments/StripeManager.php`)

The main API client for all Stripe interactions.

**Key Methods:**
- `set_credentials($api_key, $public_key, $webhook_secret, $mode)` - Store Stripe credentials
- `is_configured()` - Check if Stripe is configured
- `test_api_key()` - Validate API key connectivity
- `create_subscription($data)` - Create a subscription
- `update_subscription($subscription_id, $data)` - Update subscription
- `cancel_subscription($subscription_id)` - Cancel a subscription
- `create_charge($data)` - Create a one-time charge
- `create_payment_intent($data)` - Create a payment intent
- `register_webhook($url, $events)` - Register webhook endpoint

**Security:**
- All credentials stored encrypted via StateManager
- No credentials in code or config files
- API keys loaded from secure storage at runtime

#### 2. PlanManager (`includes/Payments/PlanManager.php`)

Manages pricing tiers and plans.

**Key Methods:**
- `create_plan($plan)` - Create a new pricing plan
- `update_plan($plan_id, $data)` - Update existing plan
- `delete_plan($plan_id)` - Archive or delete plan
- `get_all_plans()` - Get all available plans
- `get_plan($plan_id)` - Get specific plan

**Plan Structure:**
```php
$plan = [
    'id' => 'pro',                    // Unique identifier
    'name' => 'Professional',         // Display name
    'description' => 'Pro features',  // Description
    'amount' => 99.99,               // Price
    'currency' => 'usd',             // Currency code
    'interval' => 'month',           // 'day', 'week', 'month', 'year'
    'interval_count' => 1,           // For quarterly: interval='month', interval_count=3
    'stripe_price_id' => 'price_xxx', // Stripe price ID
    'stripe_product_id' => 'prod_xxx', // Stripe product ID
];
```

#### 3. SubscriptionRepository (`includes/Payments/SubscriptionRepository.php`)

Database layer for subscription management.

**Key Methods:**
- `create($data)` - Create subscription record
- `get($id)` - Retrieve subscription by ID
- `get_by_client($client_id, $status)` - Get client subscriptions
- `update($id, $data)` - Update subscription
- `delete($id)` - Soft delete subscription
- `get_by_status($status)` - Get subscriptions by status
- `get_expiring($days)` - Get subscriptions expiring soon

#### 4. WebhookHandler (`includes/Payments/WebhookHandler.php`)

Processes Stripe webhook events.

**Supported Events:**
- `customer.subscription.created` - New subscription created
- `customer.subscription.updated` - Subscription updated
- `customer.subscription.deleted` - Subscription cancelled
- `charge.succeeded` - Payment succeeded
- `charge.failed` - Payment failed
- `invoice.payment_succeeded` - Invoice paid
- `invoice.payment_failed` - Invoice payment failed

#### 5. WebhookEndpoint (`includes/Payments/WebhookEndpoint.php`)

REST endpoint for receiving Stripe webhooks.

**Endpoint:** `POST /wp-json/newera/v1/stripe-webhook`

**Security:**
- Signature verification using HMAC-SHA256
- Timestamp validation (prevents replay attacks)
- Public endpoint (signature verification handles security)

#### 6. PaymentsModule (`modules/Payments/PaymentsModule.php`)

Main module that orchestrates all payment components.

**Features:**
- Integrates all payment classes
- Provides admin menu for plan management
- Handles setup wizard integration
- Exposes managers via WordPress filters

## Setup & Configuration

### Step 1: Get Stripe API Keys

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to API Keys (Developers > API Keys)
3. Get your Secret Key and Publishable Key
4. For webhooks, you'll need the Webhook Signing Secret (created after registering endpoint)

### Step 2: Configure via Setup Wizard

During plugin activation, the setup wizard includes a "Payments" step:

1. Select "Stripe" as the payments provider
2. Enter your Stripe Secret API Key
3. Enter your Stripe Publishable Key
4. Choose Test or Live mode
5. Credentials are automatically encrypted and stored

### Step 3: Auto-Webhook Registration

The plugin automatically registers your webhook endpoint with Stripe when you save credentials. The webhook endpoint is:

```
https://yoursite.com/wp-json/newera/v1/stripe-webhook
```

### Step 4: Create Pricing Plans

1. Go to Newera > Payment Plans in WordPress admin
2. Create new plans with:
   - Plan ID (unique identifier)
   - Plan name
   - Amount and currency
   - Billing interval (daily, weekly, monthly, yearly)
   - Optional interval count for custom periods

Plans are created both locally and in Stripe (if configured).

## Usage Examples

### Creating a Subscription

```php
use Newera\Payments\StripeManager;

$stripe = apply_filters('newera_get_stripe_manager', null);

// Create customer if needed
$customer = $stripe->create_customer([
    'email' => 'user@example.com',
    'name' => 'John Doe',
]);

// Create subscription
$subscription = $stripe->create_subscription([
    'customer' => $customer['id'],
    'items' => [
        [
            'price' => 'price_xxxxx',  // From your plan
        ],
    ],
    'payment_behavior' => 'default_incomplete',
    'expand' => ['latest_invoice.payment_intent'],
]);
```

### Creating a One-Time Charge

```php
$stripe = apply_filters('newera_get_stripe_manager', null);

$charge = $stripe->create_charge([
    'amount' => 5000,  // in cents
    'currency' => 'usd',
    'source' => 'tok_visa',  // from Stripe.js
    'description' => 'One-time purchase',
]);
```

### Managing Subscriptions

```php
$repo = apply_filters('newera_get_subscription_repo', null);

// Get client subscriptions
$subscriptions = $repo->get_by_client($client_id, 'active');

// Update subscription
$repo->update($subscription_id, [
    'status' => 'paused',
    'auto_renew' => 0,
]);

// Cancel subscription
$repo->delete($subscription_id);
```

### Managing Plans

```php
$plans = apply_filters('newera_get_plan_manager', null);

// Create plan
$plan = $plans->create_plan([
    'id' => 'starter',
    'name' => 'Starter Plan',
    'amount' => 29.99,
    'currency' => 'usd',
    'interval' => 'month',
    'description' => 'Perfect for getting started',
]);

// Update plan
$plans->update_plan('starter', [
    'amount' => 34.99,
]);

// Get all plans
$all_plans = $plans->get_all_plans();
```

## Database Schema

### Subscriptions Table (`wp_subscriptions`)

```sql
CREATE TABLE wp_subscriptions (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_id bigint(20) unsigned NOT NULL,
    plan varchar(100) NOT NULL,
    status varchar(50) DEFAULT 'active',
    amount decimal(15, 2),
    billing_cycle varchar(50),
    start_date date,
    end_date date,
    auto_renew tinyint(1) DEFAULT 1,
    stripe_subscription_id varchar(255) NULL UNIQUE,
    stripe_charge_id varchar(255) NULL,
    payment_method varchar(50) DEFAULT 'card',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at datetime NULL,
    PRIMARY KEY (id),
    KEY client_id (client_id),
    KEY status (status),
    KEY plan (plan),
    KEY created_at (created_at)
);
```

### Clients Table (Enhanced)

```sql
ALTER TABLE wp_clients ADD COLUMN stripe_customer_id varchar(255) NULL UNIQUE;
```

## Webhook Processing

Webhooks are automatically validated and processed:

1. **Signature Verification**: HMAC-SHA256 with timestamp check
2. **Event Routing**: Each event type is routed to specific handler
3. **Logging**: All webhook events logged for debugging
4. **Hooks**: Custom actions fired for each event type

### Custom Hooks

You can listen to subscription events:

```php
add_action('newera_subscription_created', function($subscription_id, $stripe_subscription) {
    // Custom handling
}, 10, 2);

add_action('newera_subscription_updated', function($stripe_subscription_id, $subscription) {
    // Custom handling
}, 10, 2);

add_action('newera_subscription_deleted', function($stripe_subscription_id, $subscription) {
    // Custom handling
}, 10, 2);

add_action('newera_charge_succeeded', function($charge_id, $charge) {
    // Custom handling
}, 10, 2);

add_action('newera_charge_failed', function($charge_id, $charge) {
    // Custom handling
}, 10, 2);

add_action('newera_invoice_payment_succeeded', function($invoice_id, $invoice) {
    // Custom handling
}, 10, 2);

add_action('newera_invoice_payment_failed', function($invoice_id, $invoice) {
    // Custom handling
}, 10, 2);
```

## Error Handling & Graceful Degradation

The plugin handles errors gracefully:

1. **Missing Credentials**: Checks `is_configured()` before API calls
2. **Network Errors**: Logs and returns false on HTTP errors
3. **API Errors**: Detailed error messages logged
4. **Webhook Failures**: 200 OK always returned to Stripe (idempotent processing)
5. **Timestamp Validation**: Prevents replay attacks on webhooks

### Testing Configuration

Use `test_api_key()` to verify connectivity:

```php
$stripe = apply_filters('newera_get_stripe_manager', null);
if ($stripe->test_api_key()) {
    echo 'Connected to Stripe!';
} else {
    echo 'Failed to connect';
}
```

## Security Considerations

1. **Encryption**: All API keys encrypted with AES-256-CBC
2. **No Logging**: API keys never logged
3. **Signature Verification**: All webhooks verified
4. **Timestamp Validation**: Prevents replay attacks
5. **Credential Source**: Only from setup wizard (installer provided)
6. **Admin Only**: Plan management restricted to admin users
7. **Capability Checks**: All admin actions verified

## Admin Interface

### Payment Plans Page

Navigate to **Newera > Payment Plans** to:

- View all active and archived plans
- Create new pricing tiers
- Edit existing plans
- Delete/archive plans
- See plan Stripe IDs

Each plan shows:
- Plan ID (unique identifier)
- Plan name
- Amount and currency
- Billing cycle
- Active/archived status
- Creation date

## Limitations & Future Enhancements

**Current Limitations:**
- Basic tax support (currency only)
- Manual invoice generation not yet implemented
- No email delivery system built-in
- No charge reconciliation (can be added via webhooks)

**Future Enhancements:**
- Invoice generation and PDF export
- Email notifications for billing events
- Tax calculation integration (TaxJar, Avalara)
- Payment recovery for failed charges
- Subscription analytics dashboard
- Usage-based billing support
- Multiple payment methods (Apple Pay, Google Pay)

## Troubleshooting

### Webhooks Not Processing

1. Check webhook URL in Stripe Dashboard
2. Verify webhook signing secret is stored correctly
3. Check logs: `wp-content/debug.log`
4. Verify Stripe can reach your site (test with curl)

### Credentials Not Saved

1. Check WordPress error logs
2. Verify StateManager is initialized
3. Check encryption is available (OpenSSL)
4. Test with `test_api_key()`

### Plans Not Syncing to Stripe

1. Verify API key is valid
2. Check Stripe mode (test vs live)
3. Review error logs
4. Manually create in Stripe Dashboard as backup

## Support & Documentation

- Stripe API Docs: https://stripe.com/docs/api
- Webhook Guide: https://stripe.com/docs/webhooks
- Testing: Use test API keys and test cards

## Files Created

- `/includes/Payments/StripeManager.php` - Core Stripe API client
- `/includes/Payments/PlanManager.php` - Plan/tier management
- `/includes/Payments/SubscriptionRepository.php` - Database layer
- `/includes/Payments/WebhookHandler.php` - Event processor
- `/includes/Payments/WebhookEndpoint.php` - REST endpoint
- `/modules/Payments/PaymentsModule.php` - Main module
- `/templates/admin/payment-plans.php` - Admin UI
- `/database/migrations/20231214000600_add_stripe_columns.php` - Schema
