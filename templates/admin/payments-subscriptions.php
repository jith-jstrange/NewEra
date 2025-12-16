<?php
/**
 * Subscriptions Tab Template for Payments Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get subscriptions from database
global $wpdb;
$table_name = $wpdb->prefix . 'subscriptions';
$subscriptions = $wpdb->get_results("
    SELECT s.*, c.name as client_name, c.email as client_email 
    FROM $table_name s 
    LEFT JOIN {$wpdb->prefix}newera_clients c ON s.client_id = c.id 
    ORDER BY s.created_at DESC
");
?>

<div class="subscriptions-tab">
    <div class="page-header">
        <h2><?php _e('Active Subscriptions', 'newera'); ?></h2>
        <button type="button" class="button button-primary" onclick="openCreateSubscriptionModal()">
            <?php _e('Create Subscription', 'newera'); ?>
        </button>
    </div>

    <?php if (empty($subscriptions)): ?>
        <div class="notice notice-info">
            <p><?php _e('No subscriptions found.', 'newera'); ?></p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="subscription-table">
                <thead>
                    <tr>
                        <th><?php _e('Client', 'newera'); ?></th>
                        <th><?php _e('Plan', 'newera'); ?></th>
                        <th><?php _e('Amount', 'newera'); ?></th>
                        <th><?php _e('Status', 'newera'); ?></th>
                        <th><?php _e('Start Date', 'newera'); ?></th>
                        <th><?php _e('Next Billing', 'newera'); ?></th>
                        <th><?php _e('Actions', 'newera'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptions as $sub): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($sub->client_name ?: 'Unknown'); ?></strong>
                                <br><small><?php echo esc_html($sub->client_email ?: ''); ?></small>
                            </td>
                            <td><?php echo esc_html($sub->plan); ?></td>
                            <td>$<?php echo number_format($sub->amount, 2); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($sub->status); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $sub->status)); ?>
                                </span>
                            </td>
                            <td><?php echo $sub->start_date ? date('M j, Y', strtotime($sub->start_date)) : '-'; ?></td>
                            <td><?php echo $sub->end_date ? date('M j, Y', strtotime($sub->end_date)) : '-'; ?></td>
                            <td>
                                <?php if ($sub->status === 'active'): ?>
                                    <button type="button" class="button button-small" onclick="cancelSubscription(<?php echo $sub->id; ?>)">
                                        <?php _e('Cancel', 'newera'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Create Subscription Modal -->
<div id="createSubscriptionModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Create New Subscription', 'newera'); ?></h3>
            <span class="close" onclick="closeModal('createSubscriptionModal')">×</span>
        </div>
        <form id="createSubscriptionForm">
            <?php wp_nonce_field('newera_admin_nonce', 'subscription_nonce'); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label for="client_id"><?php _e('Client', 'newera'); ?></label>
                    <select id="client_id" name="client_id" required>
                        <option value=""><?php _e('Select a client', 'newera'); ?></option>
                        <?php
                        // Get clients from database
                        $clients_table = $wpdb->prefix . 'newera_clients';
                        $clients = $wpdb->get_results("SELECT id, name, email FROM $clients_table ORDER BY name ASC");
                        foreach ($clients as $client): ?>
                            <option value="<?php echo $client->id; ?>">
                                <?php echo esc_html($client->name . ' (' . $client->email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="plan_select"><?php _e('Plan', 'newera'); ?></label>
                    <select id="plan_select" name="plan_id" required>
                        <option value=""><?php _e('Select a plan', 'newera'); ?></option>
                        <?php
                        // Get active plans
                        $plans_table = $wpdb->prefix . 'newera_plans';
                        $plans = $wpdb->get_results("SELECT plan_id, name, amount, billing_interval, stripe_price_id FROM $plans_table WHERE active = 1 ORDER BY amount ASC");
                        foreach ($plans as $plan): ?>
                            <option value="<?php echo $plan->plan_id; ?>" data-price-id="<?php echo $plan->stripe_price_id; ?>">
                                <?php echo esc_html($plan->name . ' - $' . $plan->amount . '/' . $plan->billing_interval); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="price_id" name="price_id">
                </div>
                
                <div class="form-group">
                    <label for="customer_email"><?php _e('Customer Email', 'newera'); ?></label>
                    <input type="email" id="customer_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="customer_name"><?php _e('Customer Name', 'newera'); ?></label>
                    <input type="text" id="customer_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="trial_days"><?php _e('Trial Days', 'newera'); ?></label>
                    <input type="number" id="trial_days" name="trial_days" min="0" value="0">
                    <small><?php _e('Leave 0 for no trial period', 'newera'); ?></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button" onclick="closeModal('createSubscriptionModal')">
                    <?php _e('Cancel', 'newera'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php _e('Create Subscription', 'newera'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.table-container {
    overflow-x: auto;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.subscription-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.subscription-table th,
.subscription-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.subscription-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.status-active { 
    color: #008000; 
    font-weight: bold; 
}

.status-canceled { 
    color: #ff0000; 
    font-weight: bold; 
}

.status-past_due { 
    color: #ff8c00; 
    font-weight: bold; 
}

.status-trialing { 
    color: #0066cc; 
    font-weight: bold; 
}
</style>

<script>
function openCreateSubscriptionModal() {
    document.getElementById('createSubscriptionModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function cancelSubscription(subscriptionId) {
    if (confirm('<?php _e('Are you sure you want to cancel this subscription?', 'newera'); ?>')) {
        var formData = new FormData();
        formData.append('action', 'stripe_cancel_subscription');
        formData.append('subscription_id', subscriptionId);
        formData.append('cancel_at_period_end', 'true');
        formData.append('nonce', newera_ajax.nonce);
        
        jQuery.ajax({
            url: newera_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Subscription canceled successfully!', 'newera'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Error:', 'newera'); ?> ' + response.data);
                }
            },
            error: function() {
                alert('<?php _e('An error occurred', 'newera'); ?>');
            }
        });
    }
}

// Update price ID when plan is selected
document.getElementById('plan_select').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var priceId = selectedOption.getAttribute('data-price-id');
    document.getElementById('price_id').value = priceId || '';
});

// Form submission handler
document.getElementById('createSubscriptionForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    formData.append('action', 'stripe_create_subscription');
    
    jQuery.ajax({
        url: newera_ajax.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('<?php _e('Subscription created successfully!', 'newera'); ?>');
                closeModal('createSubscriptionModal');
                location.reload();
            } else {
                alert('<?php _e('Error:', 'newera'); ?> ' + response.data);
            }
        },
        error: function() {
            alert('<?php _e('An error occurred', 'newera'); ?>');
        }
    });
});
</script>