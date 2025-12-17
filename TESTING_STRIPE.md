# Testing Stripe Integration

This guide helps you test the Stripe payment integration in the Newera plugin.

## Prerequisites

1. WordPress 5.0+ installation
2. Newera plugin installed and activated
3. Stripe account (test mode is free)
4. Access to WordPress admin panel with manage_options capability

## Test Scenario 1: Setup & Configuration

### Step 1: Get Test API Keys

1. Go to https://dashboard.stripe.com/login
2. Create a test account or use existing Stripe account in test mode
3. Navigate to Developers > API Keys
4. Note your **Test Mode** keys:
   - Secret Key (starts with `sk_test_`)
   - Publishable Key (starts with `pk_test_`)

### Step 2: Run Setup Wizard

1. Activate the plugin - you should be redirected to setup wizard
2. Go through steps: Intro → Auth → Database → **Payments**
3. On Payments step:
   - Select "Stripe" from dropdown
   - Paste your Test Secret Key
   - Paste your Test Publishable Key
   - Keep "Test Mode (Development)" selected
   - Click Save & Continue
4. Complete setup and finish wizard

### Step 3: Verify Credentials Saved

1. In WordPress admin, go to Newera > Settings
2. Check that Stripe is configured
3. Or check database: `SELECT * FROM wp_options WHERE option_name LIKE '%stripe%'`

## Test Scenario 2: Webhook Registration

### Step 1: Check Webhook Endpoint

1. In WordPress admin, enable debug.log
2. Add to wp-config.php:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

3. Trigger webhook registration by resaving Stripe credentials
4. Check logs for webhook registration success

### Step 2: Verify Webhook in Stripe

1. Go to Stripe Dashboard > Developers > Webhooks
2. You should see your endpoint: `https://yoursite.com/wp-json/newera/v1/stripe-webhook`
3. Check webhook signing secret is stored

### Step 3: Test Webhook Reception

1. In Stripe webhook details, click "Send test event"
2. Select any event (e.g., `customer.subscription.created`)
3. Watch debug.log for webhook event processing
4. Should see log entry: "Webhook event received"

## Test Scenario 3: Plan Management

### Step 1: Create a Plan

1. Go to WordPress admin: Newera > Payment Plans
2. Fill in form:
   - Plan ID: `test-basic`
   - Name: `Test Basic Plan`
   - Amount: `29.99`
   - Currency: `USD`
   - Interval: `Month`
3. Click "Create Plan"
4. Should see success message

### Step 2: Verify Plan in Stripe

1. Go to Stripe Dashboard > Product Catalog > Prices
2. You should see the plan with matching details
3. Note the Stripe Price ID (starts with `price_`)

### Step 3: Update Plan

1. Back in WordPress, click "Edit" on your plan
2. Change amount to `34.99`
3. Click Update Plan
4. Verify change in Stripe Dashboard

### Step 4: Delete Plan

1. Click "Delete" on the plan
2. Confirm deletion
3. Plan should be removed from WordPress
4. (Note: Stripe plan remains, just archived locally)

## Test Scenario 4: Subscription Creation

### Step 1: Create Customer in Stripe

```php
// In WordPress, use this code snippet in Functions or a custom admin page

$stripe = apply_filters('newera_get_stripe_manager', null);

$customer = $stripe->create_customer([
    'email' => 'test@example.com',
    'name' => 'Test Customer',
]);

echo 'Customer ID: ' . $customer['id'];
```

### Step 2: Create Subscription

```php
$stripe = apply_filters('newera_get_stripe_manager', null);

// Use your test plan's Stripe price ID
$subscription = $stripe->create_subscription([
    'customer' => 'cus_xxxxx', // From step 1
    'items' => [
        [
            'price' => 'price_xxxxx', // Your plan's Stripe price ID
        ],
    ],
    'payment_behavior' => 'default_incomplete',
]);

echo 'Subscription: ' . $subscription['id'];
```

### Step 3: Verify in Stripe

1. Go to Stripe Dashboard > Customers
2. Find your test customer
3. See subscription listed with correct plan

## Test Scenario 5: Webhook Event Processing

### Step 1: Create Test Event

1. Go to Stripe Dashboard > Developers > Events
2. In Webhook details, send test event for:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `charge.succeeded`
   - `invoice.payment_succeeded`

### Step 2: Monitor Logs

1. Check WordPress debug.log
2. Look for entries like:
   - "Webhook event received"
   - "Subscription created from webhook"
   - "Charge succeeded from webhook"

### Step 3: Check Custom Hooks

Add test hooks to functions.php:

```php
add_action('newera_subscription_created', function($subscription_id, $stripe_subscription) {
    error_log('HOOK: newera_subscription_created - ' . $subscription_id);
}, 10, 2);

add_action('newera_charge_succeeded', function($charge_id, $charge) {
    error_log('HOOK: newera_charge_succeeded - ' . $charge_id);
}, 10, 2);
```

Trigger events and verify hooks fire.

## Test Scenario 6: One-Time Charge

### Step 1: Use Stripe.js to Get Token

For testing, use Stripe's test card directly:

```php
// Test with Stripe test token
$stripe = apply_filters('newera_get_stripe_manager', null);

// Using payment method approach (recommended)
$charge = $stripe->create_charge([
    'amount' => 2999, // $29.99 in cents
    'currency' => 'usd',
    'source' => 'tok_visa', // Stripe test token
    'description' => 'Test charge',
    'metadata' => [
        'order_id' => '12345',
    ],
]);

echo 'Charge: ' . $charge['id'];
```

### Step 2: Verify in Stripe

1. Go to Stripe Dashboard > Payments
2. You should see the charge
3. Status should be "Succeeded"
4. Amount should be $29.99

## Test Scenario 7: Error Handling

### Step 1: Test with Invalid Key

1. Go to setup wizard (revisit)
2. Change API key to invalid value
3. Try to save
4. Should get error: "Failed to connect to Stripe"

### Step 2: Test Disconnected

1. Disable internet or change site URL
2. Try creating plan
3. Should gracefully handle error
4. Check logs for error message

## Debug Commands

### Check Stripe Manager

```php
$stripe = apply_filters('newera_get_stripe_manager', null);
var_dump($stripe->is_configured()); // Should be true
var_dump($stripe->test_api_key()); // Should be true
var_dump($stripe->get_mode()); // Should be 'test'
var_dump($stripe->get_public_key()); // Should show pk_test_...
```

### Check Plans

```php
$plans = apply_filters('newera_get_plan_manager', null);
var_dump($plans->get_all_plans());
var_dump($plans->get_plan('test-basic'));
```

### Check Subscriptions

```php
$repo = apply_filters('newera_get_subscription_repo', null);
$subscriptions = $repo->get_all_plans(); // Get all
$active = $repo->get_by_status('active'); // Get active
```

## Troubleshooting

### Webhook Not Registering

- Check API key is valid with `test_api_key()`
- Check webhook secret is stored (use custom hook logs)
- Verify site is accessible from Stripe servers
- Try manual webhook creation in Stripe Dashboard

### Plans Not Creating

- Verify Stripe API key is valid
- Check test vs live mode matches
- Look for API errors in debug log
- Verify product creation succeeds

### Subscriptions Not Syncing

- Verify customer exists in Stripe
- Check price_id is valid
- Monitor webhook events
- Add custom action hooks to track

### Signature Verification Fails

- Check webhook secret matches Stripe endpoint
- Verify timestamp is fresh (within 5 minutes)
- Check PHP hash_equals function available (PHP 5.6+)

## Test Cards

For testing payments in test mode, Stripe provides test cards:

- **Success**: 4242 4242 4242 4242
- **Decline**: 4000 0000 0000 0002
- **3D Secure**: 4000 0025 0000 3155

Use any future expiry date (e.g., 12/99) and any 3-digit CVC.

## Performance Testing

### Monitor Query Count

Check number of database queries:

```php
// Add to theme functions.php temporarily
add_action('wp_footer', function() {
    if (defined('SAVEQUERIES') && SAVEQUERIES) {
        global $wpdb;
        echo '<!-- Queries: ' . $wpdb->num_queries . ' -->';
    }
});
```

### Monitor API Calls

Track Stripe API calls via logs:

```php
add_filter('newera_stripe_api_request', function($result, $endpoint, $method) {
    error_log("STRIPE API: $method $endpoint");
    return $result;
}, 10, 3);
```

## Load Testing

For production readiness, test with:
- Multiple concurrent subscription creations
- Rapid webhook delivery (simulate Stripe retry)
- Large subscription lists (pagination)
- Database migration on big tables

## Success Criteria

✅ Setup wizard accepts Stripe credentials
✅ Credentials encrypted and stored securely
✅ Webhook endpoint auto-registered with Stripe
✅ Plans created in WordPress and synced to Stripe
✅ Plans appear in Stripe Dashboard
✅ Webhooks received and processed
✅ Custom hooks fire on events
✅ Subscriptions created in Stripe
✅ One-time charges process
✅ Error handling graceful
✅ No sensitive data in logs
