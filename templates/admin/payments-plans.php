<?php
/**
 * Plans Tab Template for Payments Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get plans from database
global $wpdb;
$table_name = $wpdb->prefix . 'newera_plans';
$plans = $wpdb->get_results("SELECT * FROM $table_name WHERE active = 1 ORDER BY amount ASC");

if ($_POST && wp_verify_nonce($_POST['plan_nonce'], 'create_plan_action')) {
    // Handle plan creation via AJAX would be better, but let's support form submission too
}
?>

<div class="plans-tab">
    <div class="page-header">
        <h2><?php _e('Subscription Plans', 'newera'); ?></h2>
        <button type="button" class="button button-primary" onclick="openCreatePlanModal()">
            <?php _e('Create New Plan', 'newera'); ?>
        </button>
    </div>

    <?php if (empty($plans)): ?>
        <div class="notice notice-info">
            <p><?php _e('No plans created yet. Create your first plan to get started.', 'newera'); ?></p>
        </div>
    <?php else: ?>
        <div class="plan-grid">
            <?php foreach ($plans as $plan): ?>
                <div class="plan-card">
                    <h3><?php echo esc_html($plan->name); ?></h3>
                    <div class="plan-price">
                        $<?php echo number_format($plan->amount, 2); ?>
                        <small>/<?php echo esc_html($plan->billing_interval); ?></small>
                    </div>
                    <div class="plan-description">
                        <?php echo esc_html($plan->description); ?>
                    </div>
                    <?php if ($plan->trial_period_days): ?>
                        <div class="trial-info">
                            <small><?php printf(__('Includes %d day trial', 'newera'), $plan->trial_period_days); ?></small>
                        </div>
                    <?php endif; ?>
                    <div class="plan-actions">
                        <button type="button" class="button button-small" onclick="editPlan(<?php echo $plan->id; ?>)">
                            <?php _e('Edit', 'newera'); ?>
                        </button>
                        <button type="button" class="button button-small" onclick="deletePlan(<?php echo $plan->id; ?>)">
                            <?php _e('Delete', 'newera'); ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Plan Modal -->
<div id="createPlanModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php _e('Create New Plan', 'newera'); ?></h3>
            <span class="close" onclick="closeModal('createPlanModal')">×</span>
        </div>
        <form id="createPlanForm">
            <?php wp_nonce_field('newera_admin_nonce', 'plan_nonce'); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label for="plan_id"><?php _e('Plan ID', 'newera'); ?></label>
                    <input type="text" id="plan_id" name="plan_id" required 
                           placeholder="e.g., basic_monthly" pattern="[a-z0-9_]+">
                    <small><?php _e('Only lowercase letters, numbers, and underscores', 'newera'); ?></small>
                </div>
                
                <div class="form-group">
                    <label for="plan_name"><?php _e('Plan Name', 'newera'); ?></label>
                    <input type="text" id="plan_name" name="name" required 
                           placeholder="e.g., Basic Plan">
                </div>
                
                <div class="form-group">
                    <label for="plan_description"><?php _e('Description', 'newera'); ?></label>
                    <textarea id="plan_description" name="description" rows="3" 
                              placeholder="Brief description of the plan"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="plan_amount"><?php _e('Price', 'newera'); ?></label>
                        <input type="number" id="plan_amount" name="amount" required 
                               step="0.01" min="0" placeholder="9.99">
                    </div>
                    
                    <div class="form-group">
                        <label for="plan_currency"><?php _e('Currency', 'newera'); ?></label>
                        <select id="plan_currency" name="currency">
                            <option value="usd">USD</option>
                            <option value="eur">EUR</option>
                            <option value="gbp">GBP</option>
                            <option value="cad">CAD</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="billing_interval"><?php _e('Billing Interval', 'newera'); ?></label>
                    <select id="billing_interval" name="billing_interval" required>
                        <option value="month"><?php _e('Monthly', 'newera'); ?></option>
                        <option value="year"><?php _e('Yearly', 'newera'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="trial_days"><?php _e('Trial Period (Days)', 'newera'); ?></label>
                    <input type="number" id="trial_days" name="trial_period_days" 
                           min="0" placeholder="0">
                    <small><?php _e('Leave blank for no trial period', 'newera'); ?></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button" onclick="closeModal('createPlanModal')">
                    <?php _e('Cancel', 'newera'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php _e('Create Plan', 'newera'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 4px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: #000;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.trial-info {
    color: #666;
    font-style: italic;
    margin: 10px 0;
}
</style>

<script>
function openCreatePlanModal() {
    document.getElementById('createPlanModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function editPlan(planId) {
    // Implement plan editing
    alert('Edit plan functionality coming soon');
}

function deletePlan(planId) {
    if (confirm('<?php _e('Are you sure you want to delete this plan?', 'newera'); ?>')) {
        // Implement plan deletion
        alert('Delete plan functionality coming soon');
    }
}

// Form submission handler
document.getElementById('createPlanForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    formData.append('action', 'stripe_create_plan');
    formData.append('nonce', newera_ajax.nonce);
    
    jQuery.ajax({
        url: newera_ajax.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('<?php _e('Plan created successfully!', 'newera'); ?>');
                closeModal('createPlanModal');
                location.reload(); // Refresh to show new plan
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