# Stripe Integration Quick Start

## What Was Implemented

Complete Stripe payments integration with:
- Subscriptions (create, update, cancel)
- One-time charges
- Webhook processing (15+ events)
- Plan/tier management
- Secure credential storage
- Admin UI for plan management

## Files Added

### Core Classes (in `includes/Payments/`)
- `StripeManager.php` - Main Stripe API client
- `PlanManager.php` - Pricing tier management
- `SubscriptionRepository.php` - Database operations
- `WebhookHandler.php` - Event processing
- `WebhookEndpoint.php` - REST webhook endpoint

### Module & Admin
- `modules/Payments/PaymentsModule.php` - Enhanced module with Stripe support
- `templates/admin/payment-plans.php` - Plan management UI

### Database
- `database/migrations/20231214000600_add_stripe_columns.php` - Schema updates

### Documentation
- `STRIPE_INTEGRATION.md` - Complete documentation
- `TESTING_STRIPE.md` - Testing guide
- `STRIPE_QUICK_START.md` - This file

## Setup in 3 Minutes

1. **Activate Plugin**
   - Install Newera plugin
   - Activate it
   - Setup wizard appears

2. **Add Stripe Keys**
   - Get keys from https://dashboard.stripe.com/apikeys
   - Go to Payments step in wizard
   - Paste Secret Key and Publishable Key
   - Select Test mode
   - Save & Continue

3. **Create Plans**
   - Go to Newera > Payment Plans
   - Click "Create Plan"
   - Fill details (name, amount, interval)
   - Click "Create Plan"

That's it! Stripe is now configured.

## Quick Usage Examples

### Get the Stripe Manager
```php
$stripe = apply_filters('newera_get_stripe_manager', null);
if ($stripe->is_configured()) {
    // Ready to use
}
```

### Create a Subscription
```php
$stripe = apply_filters('newera_get_stripe_manager', null);

$subscription = $stripe->create_subscription([
    'customer' => 'cus_xxxxx',
    'items' => [
        ['price' => 'price_xxxxx'],
    ],
]);
```

### Create a One-Time Charge
```php
$stripe = apply_filters('newera_get_stripe_manager', null);

$charge = $stripe->create_charge([
    'amount' => 2999,        // $29.99 in cents
    'currency' => 'usd',
    'source' => 'tok_visa',  // Test token
]);
```

### Get All Plans
```php
$plans = apply_filters('newera_get_plan_manager', null);
$all = $plans->get_all_plans();
```

### Get Subscriptions by Status
```php
$repo = apply_filters('newera_get_subscription_repo', null);
$active = $repo->get_by_status('active');
```

## Webhook Events

Automatically processed webhooks trigger custom actions:

```php
// Listen to subscription events
add_action('newera_subscription_created', function($id, $data) {
    // Custom code here
}, 10, 2);

add_action('newera_subscription_updated', function($id, $data) {
    // Custom code here
}, 10, 2);

add_action('newera_subscription_deleted', function($id, $data) {
    // Custom code here
}, 10, 2);

// Listen to charge events
add_action('newera_charge_succeeded', function($id, $data) {
    // Custom code here
}, 10, 2);

add_action('newera_charge_failed', function($id, $data) {
    // Custom code here
}, 10, 2);
```

## Admin Features

### Payment Plans Page
Location: Newera > Payment Plans

- **View**: List all plans with amounts and intervals
- **Create**: New pricing tiers
- **Edit**: Update existing plans
- **Delete**: Archive plans

### Setup Wizard Integration
- Stripe step asks for API keys
- Auto-validates credentials
- Auto-registers webhook endpoint

### Settings
- Credentials encrypted with AES-256-CBC
- Mode selection (test/live)
- Auto-webhook registration option

## Architecture Highlights

### Secure Credential Storage
- Uses existing StateManager
- AES-256-CBC encryption
- No plaintext keys in database

### Graceful Degradation
- Works without Stripe (logs warnings)
- Continues if Stripe unreachable
- Fallback for missing configuration

### WordPress Standards
- Uses wp_remote_* for HTTP
- Follows WP coding standards
- Proper escaping and sanitization
- Capability checks in admin

### Extensibility
- Custom action hooks for events
- Filter-based service exposure
- Module-based architecture

## Database Schema

### wp_subscriptions (Enhanced)
Added columns:
- `stripe_subscription_id` - Stripe subscription ID
- `stripe_charge_id` - Stripe charge ID
- `payment_method` - Payment type (card, etc)

### wp_clients (Enhanced)
Added columns:
- `stripe_customer_id` - Stripe customer ID

## API Reference

### StripeManager Methods

**Configuration**
- `is_configured()` - Check if ready
- `set_credentials($key, $pub, $secret, $mode)` - Store credentials
- `test_api_key()` - Validate connectivity
- `get_mode()` - Get test/live mode
- `get_public_key()` - Get publishable key

**Customers**
- `create_customer($data)` - New customer
- `get_customer($id)` - Get customer
- `update_customer($id, $data)` - Update customer

**Subscriptions**
- `create_subscription($data)` - New subscription
- `get_subscription($id)` - Get subscription
- `update_subscription($id, $data)` - Update subscription
- `cancel_subscription($id, $data)` - Cancel subscription

**Charges**
- `create_charge($data)` - One-time charge
- `get_charge($id)` - Get charge
- `refund_charge($id, $data)` - Refund charge

**Webhooks**
- `register_webhook($url, $events)` - Register endpoint
- `verify_webhook_signature($payload, $sig)` - Verify webhook
- `list_webhooks()` - Get all webhooks

### PlanManager Methods

- `create_plan($plan)` - Create pricing tier
- `update_plan($id, $data)` - Update plan
- `delete_plan($id)` - Delete/archive plan
- `get_plan($id)` - Get single plan
- `get_all_plans()` - Get all plans
- `get_available_plans()` - Get non-archived

### SubscriptionRepository Methods

- `create($data)` - Create record
- `get($id)` - Get by ID
- `get_by_client($id, $status)` - Get client subscriptions
- `get_by_plan($plan, $status)` - Get plan subscriptions
- `get_by_status($status)` - Filter by status
- `update($id, $data)` - Update record
- `delete($id)` - Soft delete
- `get_expiring($days)` - Get expiring soon

## Webhook Endpoint

**URL:** `POST /wp-json/newera/v1/stripe-webhook`

**Security:**
- HMAC-SHA256 signature verification
- Timestamp validation (5 minute window)
- Replay attack prevention

**Supported Events:**
- customer.* (created, updated, deleted)
- customer.subscription.* (created, updated, deleted)
- charge.* (succeeded, failed, refunded)
- invoice.* (created, finalized, payment_succeeded, payment_failed)
- payment_intent.* (succeeded, payment_failed)

## Test Cards

For testing in Stripe test mode:

| Purpose | Card | CVC | Expiry |
|---------|------|-----|--------|
| Success | 4242 4242 4242 4242 | Any | Future |
| Decline | 4000 0000 0000 0002 | Any | Future |
| 3D Secure | 4000 0025 0000 3155 | Any | Future |

## Key Features Checklist

✅ Stripe API integration
✅ Setup wizard configuration
✅ Encrypted credential storage
✅ Plan/tier management
✅ Subscription CRUD
✅ One-time charges
✅ Webhook signature verification
✅ 15+ webhook event types
✅ Subscription tracking (database)
✅ Admin plan UI
✅ Test/live mode support
✅ Graceful error handling
✅ Security best practices
✅ Full documentation
✅ Testing guide

## Next Steps

1. **Install & Activate**
   - Copy to wp-content/plugins/newera
   - Activate in WordPress

2. **Configure Stripe**
   - Get test API keys
   - Run setup wizard
   - Add Stripe credentials

3. **Create Plans**
   - Go to Payment Plans
   - Create first plan
   - Verify in Stripe Dashboard

4. **Test Webhooks**
   - Send test event in Stripe
   - Check WordPress logs
   - Verify event processing

5. **Integrate**
   - Use API in custom code
   - Listen to custom hooks
   - Build on top of module

## Support

- See `STRIPE_INTEGRATION.md` for complete docs
- See `TESTING_STRIPE.md` for testing guide
- Check `wp-content/debug.log` for errors
- Review code comments for specifics
