<?php
/**
 * Payments Admin Page Template for Newera Plugin
 *
 * Handles Stripe plan and subscription management.
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'plans';
$stripe_manager = new \Newera\Modules\Payments\StripeManager();
?>

<div class="wrap">
    <h1><?php _e('Payments Management', 'newera'); ?></h1>
    
    <?php if (!$stripe_manager->isConfigured()): ?>
        <div class="notice notice-warning">
            <p><?php _e('Stripe is not configured. Please configure your API keys in the setup wizard.', 'newera'); ?></p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <a href="?page=newera-payments&tab=plans" class="nav-tab <?php echo $current_tab === 'plans' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Plans', 'newera'); ?>
        </a>
        <a href="?page=newera-payments&tab=subscriptions" class="nav-tab <?php echo $current_tab === 'subscriptions' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Subscriptions', 'newera'); ?>
        </a>
        <a href="?page=newera-payments&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Settings', 'newera'); ?>
        </a>
    </nav>

    <div class="tab-content">
        <?php if ($current_tab === 'plans'): ?>
            <?php include 'payments-plans.php'; ?>
        <?php elseif ($current_tab === 'subscriptions'): ?>
            <?php include 'payments-subscriptions.php'; ?>
        <?php elseif ($current_tab === 'settings'): ?>
            <?php include 'payments-settings.php'; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.tab-content {
    margin-top: 20px;
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.plan-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.plan-card {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    background: #fafafa;
}

.plan-card h3 {
    margin-top: 0;
    color: #333;
}

.plan-price {
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
    margin: 10px 0;
}

.plan-description {
    color: #666;
    margin-bottom: 15px;
}

.plan-actions {
    display: flex;
    gap: 10px;
}

.button-small {
    padding: 4px 8px;
    font-size: 12px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.subscription-table {
    width: 100%;
    border-collapse: collapse;
}

.subscription-table th,
.subscription-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.status-active { color: #008000; font-weight: bold; }
.status-canceled { color: #ff0000; font-weight: bold; }
.status-past_due { color: #ff8c00; font-weight: bold; }
</style>