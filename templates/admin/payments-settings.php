<?php
/**
 * Settings Tab Template for Payments Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$stripe_manager = new \Newera\Modules\Payments\StripeManager();
$is_configured = $stripe_manager->isConfigured();
$settings = $stripe_manager->get_module_settings();
?>

<div class="settings-tab">
    <div class="page-header">
        <h2><?php _e('Stripe Settings', 'newera'); ?></h2>
    </div>

    <?php if (!$is_configured): ?>
        <div class="notice notice-warning">
            <p><?php _e('Stripe is not configured. Please enter your API keys below to enable payment processing.', 'newera'); ?></p>
        </div>
    <?php endif; ?>

    <form id="stripeSettingsForm" method="post">
        <?php wp_nonce_field('newera_admin_nonce', 'settings_nonce'); ?>
        
        <div class="form-section">
            <h3><?php _e('API Configuration', 'newera'); ?></h3>
            
            <div class="form-group">
                <label for="api_key"><?php _e('Stripe Publishable Key', 'newera'); ?></label>
                <input type="text" id="api_key" name="api_key" 
                       value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" 
                       placeholder="pk_live_... or pk_test_...">
                <small><?php _e('Your Stripe publishable key (starts with pk_test_ for testing or pk_live_ for live)', 'newera'); ?></small>
            </div>
            
            <div class="form-group">
                <label for="api_secret"><?php _e('Stripe Secret Key', 'newera'); ?></label>
                <input type="password" id="api_secret" name="api_secret" 
                       value="<?php echo esc_attr($settings['api_secret'] ?? ''); ?>" 
                       placeholder="sk_live_... or sk_test_...">
                <small><?php _e('Your Stripe secret key (starts with sk_test_ for testing or sk_live_ for live)', 'newera'); ?></small>
            </div>
        </div>

        <div class="form-section">
            <h3><?php _e('Payment Settings', 'newera'); ?></h3>
            
            <div class="form-group">
                <label for="default_currency"><?php _e('Default Currency', 'newera'); ?></label>
                <select id="default_currency" name="default_currency">
                    <option value="usd" <?php selected($settings['default_currency'] ?? 'usd', 'usd'); ?>>USD</option>
                    <option value="eur" <?php selected($settings['default_currency'] ?? 'usd', 'eur'); ?>>EUR</option>
                    <option value="gbp" <?php selected($settings['default_currency'] ?? 'usd', 'gbp'); ?>>GBP</option>
                    <option value="cad" <?php selected($settings['default_currency'] ?? 'usd', 'cad'); ?>>CAD</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="test_mode"><?php _e('Test Mode', 'newera'); ?></label>
                <label class="checkbox-label">
                    <input type="checkbox" id="test_mode" name="test_mode" value="1" 
                           <?php checked($settings['test_mode'] ?? 1, 1); ?>>
                    <?php _e('Enable test mode (use test API keys)', 'newera'); ?>
                </label>
            </div>
        </div>

        <div class="form-section">
            <h3><?php _e('Webhook Configuration', 'newera'); ?></h3>
            
            <div class="webhook-info">
                <p><strong><?php _e('Webhook URL:', 'newera'); ?></strong></p>
                <code><?php echo home_url('/newera-stripe-webhook/'); ?></code>
                
                <p><strong><?php _e('Required Webhook Events:', 'newera'); ?></strong></p>
                <ul>
                    <li><code>customer.subscription.created</code></li>
                    <li><code>customer.subscription.updated</code></li>
                    <li><code>customer.subscription.deleted</code></li>
                    <li><code>invoice.payment_succeeded</code></li>
                    <li><code>invoice.payment_failed</code></li>
                    <li><code>customer.subscription.trial_will_end</code></li>
                </ul>
            </div>
            
            <div class="form-group">
                <button type="button" class="button" onclick="registerWebhooks()">
                    <?php _e('Register Webhooks', 'newera'); ?>
                </button>
                <small><?php _e('Click to automatically configure webhooks in your Stripe dashboard', 'newera'); ?></small>
            </div>
        </div>

        <div class="form-section">
            <h3><?php _e('Actions', 'newera'); ?></h3>
            <button type="submit" class="button button-primary">
                <?php _e('Save Settings', 'newera'); ?>
            </button>
            <button type="button" class="button" onclick="testConnection()">
                <?php _e('Test Connection', 'newera'); ?>
            </button>
        </div>
    </form>

    <?php if ($is_configured): ?>
        <div class="connection-status">
            <h3><?php _e('Connection Status', 'newera'); ?></h3>
            <div class="status-item">
                <span class="status-label"><?php _e('Stripe API:', 'newera'); ?></span>
                <span class="status-indicator connected"><?php _e('Connected', 'newera'); ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.form-section {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.form-section h3 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.form-group input,
.form-group select {
    width: 100%;
    max-width: 400px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-group small {
    color: #666;
    font-style: italic;
    display: block;
    margin-top: 3px;
}

.checkbox-label {
    display: flex !important;
    align-items: center;
    font-weight: normal !important;
}

.checkbox-label input {
    width: auto !important;
    margin-right: 8px;
}

.webhook-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin: 10px 0;
}

.webhook-info code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: monospace;
}

.webhook-info ul {
    margin: 10px 0;
    padding-left: 20px;
}

.webhook-info li {
    margin: 5px 0;
}

.connection-status {
    background: #f0f8ff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
    border-left: 4px solid #008000;
}

.status-item {
    display: flex;
    align-items: center;
    margin: 10px 0;
}

.status-label {
    font-weight: bold;
    margin-right: 10px;
    min-width: 120px;
}

.status-indicator {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-indicator.connected {
    background: #d4edda;
    color: #155724;
}

.status-indicator.disconnected {
    background: #f8d7da;
    color: #721c24;
}
</style>

<script>
function testConnection() {
    var formData = new FormData(document.getElementById('stripeSettingsForm'));
    formData.append('action', 'test_stripe_connection');
    formData.append('nonce', newera_ajax.nonce);
    
    jQuery.ajax({
        url: newera_ajax.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('<?php _e('Stripe connection successful!', 'newera'); ?>');
            } else {
                alert('<?php _e('Connection failed:', 'newera'); ?> ' + response.data);
            }
        },
        error: function() {
            alert('<?php _e('An error occurred while testing the connection', 'newera'); ?>');
        }
    });
}

function registerWebhooks() {
    if (!confirm('<?php _e('This will open Stripe in a new tab to configure webhooks. Continue?', 'newera'); ?>')) {
        return;
    }
    
    // Open Stripe webhook configuration
    window.open('https://dashboard.stripe.com/webhooks', '_blank');
}

// Form submission
document.getElementById('stripeSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    formData.append('action', 'save_stripe_settings');
    
    jQuery.ajax({
        url: newera_ajax.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('<?php _e('Settings saved successfully!', 'newera'); ?>');
                location.reload();
            } else {
                alert('<?php _e('Error saving settings:', 'newera'); ?> ' + response.data);
            }
        },
        error: function() {
            alert('<?php _e('An error occurred while saving settings', 'newera'); ?>');
        }
    });
});
</script>