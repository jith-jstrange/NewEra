<?php
/**
 * Payment Plans Admin Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('Payment Plans', 'newera'); ?></h1>

    <div class="newera-container">
        <!-- Plans List -->
        <div class="newera-plans-section">
            <h2><?php echo esc_html__('Manage Plans', 'newera'); ?></h2>

            <?php if (empty($plans)) : ?>
                <p class="newera-empty-state">
                    <?php echo esc_html__('No payment plans created yet. Create one to get started.', 'newera'); ?>
                </p>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Plan ID', 'newera'); ?></th>
                            <th><?php echo esc_html__('Name', 'newera'); ?></th>
                            <th><?php echo esc_html__('Amount', 'newera'); ?></th>
                            <th><?php echo esc_html__('Billing Cycle', 'newera'); ?></th>
                            <th><?php echo esc_html__('Status', 'newera'); ?></th>
                            <th><?php echo esc_html__('Created', 'newera'); ?></th>
                            <th><?php echo esc_html__('Actions', 'newera'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan_id => $plan) : ?>
                            <tr>
                                <td><?php echo esc_html($plan_id); ?></td>
                                <td><?php echo esc_html($plan['name'] ?? ''); ?></td>
                                <td>
                                    <?php 
                                    $currency = strtoupper($plan['currency'] ?? 'USD');
                                    $amount = isset($plan['amount']) ? number_format($plan['amount'], 2) : '0.00';
                                    echo esc_html($currency . ' ' . $amount); 
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $interval = $plan['interval'] ?? 'month';
                                    $interval_count = $plan['interval_count'] ?? 1;
                                    if ($interval_count > 1) {
                                        echo esc_html("Every {$interval_count} {$interval}s");
                                    } else {
                                        echo esc_html($interval);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span class="newera-badge <?php echo isset($plan['archived']) && $plan['archived'] ? 'archived' : 'active'; ?>">
                                        <?php echo isset($plan['archived']) && $plan['archived'] ? esc_html__('Archived', 'newera') : esc_html__('Active', 'newera'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo isset($plan['created_at']) ? esc_html(wp_date('Y-m-d H:i', strtotime($plan['created_at']))) : '—'; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small newera-edit-plan-btn" data-plan-id="<?php echo esc_attr($plan_id); ?>">
                                        <?php echo esc_html__('Edit', 'newera'); ?>
                                    </button>
                                    <a href="<?php echo esc_url(add_query_arg([
                                        'action' => 'delete',
                                        'plan_id' => $plan_id,
                                        'nonce' => wp_create_nonce('newera_delete_plan'),
                                    ])); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_attr__('Are you sure?', 'newera'); ?>');">
                                        <?php echo esc_html__('Delete', 'newera'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Create/Edit Plan Form -->
        <div class="newera-form-section" id="newera-plan-form-section">
            <h2><?php echo esc_html__('Create New Plan', 'newera'); ?></h2>

            <form method="post" class="newera-plan-form">
                <?php wp_nonce_field('newera_create_plan', 'nonce'); ?>
                <input type="hidden" name="newera_create_plan" value="1">

                <div class="form-group">
                    <label for="plan_id"><?php echo esc_html__('Plan ID', 'newera'); ?></label>
                    <input type="text" id="plan_id" name="plan_id" required placeholder="e.g., basic, pro, enterprise" class="regular-text">
                    <p class="description"><?php echo esc_html__('Unique identifier for this plan (lowercase, no spaces)', 'newera'); ?></p>
                </div>

                <div class="form-group">
                    <label for="plan_name"><?php echo esc_html__('Plan Name', 'newera'); ?></label>
                    <input type="text" id="plan_name" name="plan_name" required placeholder="e.g., Basic Plan" class="regular-text">
                </div>

                <div class="form-group">
                    <label for="plan_description"><?php echo esc_html__('Description', 'newera'); ?></label>
                    <textarea id="plan_description" name="plan_description" rows="3" class="regular-text"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-half">
                        <label for="plan_amount"><?php echo esc_html__('Amount', 'newera'); ?></label>
                        <input type="number" id="plan_amount" name="plan_amount" required step="0.01" min="0" placeholder="0.00" class="regular-text">
                    </div>

                    <div class="form-group form-col-half">
                        <label for="plan_currency"><?php echo esc_html__('Currency', 'newera'); ?></label>
                        <select id="plan_currency" name="plan_currency" class="regular-text">
                            <option value="usd">USD ($)</option>
                            <option value="eur">EUR (€)</option>
                            <option value="gbp">GBP (£)</option>
                            <option value="aud">AUD ($)</option>
                            <option value="cad">CAD ($)</option>
                            <option value="jpy">JPY (¥)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-col-half">
                        <label for="plan_interval"><?php echo esc_html__('Billing Interval', 'newera'); ?></label>
                        <select id="plan_interval" name="plan_interval" class="regular-text">
                            <option value="day">Daily</option>
                            <option value="week">Weekly</option>
                            <option value="month" selected>Monthly</option>
                            <option value="year">Yearly</option>
                        </select>
                    </div>

                    <div class="form-group form-col-half">
                        <label for="plan_interval_count"><?php echo esc_html__('Interval Count', 'newera'); ?></label>
                        <input type="number" id="plan_interval_count" name="plan_interval_count" value="1" min="1" class="regular-text">
                        <p class="description"><?php echo esc_html__('e.g., 3 months for quarterly billing', 'newera'); ?></p>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Create Plan', 'newera'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .newera-container {
            max-width: 1200px;
            margin: 20px 0;
        }

        .newera-plans-section,
        .newera-form-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .newera-empty-state {
            padding: 20px;
            text-align: center;
            color: #999;
            font-style: italic;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            box-sizing: border-box;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-col-half {
            flex: 1;
        }

        .form-col-half .form-group {
            margin-bottom: 0;
        }

        .form-actions {
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .newera-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }

        .newera-badge.active {
            background-color: #d4edda;
            color: #155724;
        }

        .newera-badge.archived {
            background-color: #f8d7da;
            color: #721c24;
        }

        .description {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</div>
