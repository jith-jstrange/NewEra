<?php
/**
 * Newera AI Settings Page Template
 *
 * @package Newera
 */

if (!defined('ABSPATH')) {
    exit;
}

$notice = $template_data['notice'] ?? null;
$providers = $template_data['providers'] ?? [];
$settings = $template_data['settings'] ?? [];
$monthly_totals = $template_data['monthly_totals'] ?? [];
$recent_events = $template_data['recent_events'] ?? [];

$provider_value = isset($settings['provider']) ? sanitize_key($settings['provider']) : '';
$model_value = isset($settings['model']) ? (string) $settings['model'] : '';
$fallback_value = isset($settings['fallback_provider']) ? sanitize_key($settings['fallback_provider']) : '';

$policies = isset($settings['policies']) && is_array($settings['policies']) ? $settings['policies'] : [];
$max_rpm = isset($policies['max_requests_per_minute']) ? (int) $policies['max_requests_per_minute'] : 0;
$monthly_token_quota = isset($policies['monthly_token_quota']) ? (int) $policies['monthly_token_quota'] : 0;
$monthly_cost_quota = isset($policies['monthly_cost_quota_usd']) ? (float) $policies['monthly_cost_quota_usd'] : 0;

$pricing = isset($settings['pricing']) && is_array($settings['pricing']) ? $settings['pricing'] : [];
$pricing_in = 0;
$pricing_out = 0;
if ($provider_value !== '' && $model_value !== '' && !empty($pricing[$provider_value][$model_value]) && is_array($pricing[$provider_value][$model_value])) {
    $pricing_in = isset($pricing[$provider_value][$model_value]['input_per_1k']) ? (float) $pricing[$provider_value][$model_value]['input_per_1k'] : 0;
    $pricing_out = isset($pricing[$provider_value][$model_value]['output_per_1k']) ? (float) $pricing[$provider_value][$model_value]['output_per_1k'] : 0;
}

$page_url = admin_url('admin.php?page=newera-ai');
?>

<div class="wrap newera-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (is_array($notice) && !empty($notice['message'])) : ?>
        <div class="notice notice-<?php echo esc_attr($notice['type'] ?? 'info'); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <div class="newera-section" style="margin-top:20px;">
        <h2><?php _e('Provider Configuration', 'newera'); ?></h2>

        <form method="post" action="<?php echo esc_url($page_url); ?>">
            <?php wp_nonce_field('newera_ai_save_settings', 'newera_ai_settings_nonce'); ?>
            <input type="hidden" name="newera_ai_save_settings" value="1" />

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="provider"><?php _e('Provider', 'newera'); ?></label></th>
                        <td>
                            <select name="provider" id="provider">
                                <option value=""><?php _e('Select…', 'newera'); ?></option>
                                <?php foreach ($providers as $id => $p) : ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($provider_value, $id); ?>>
                                        <?php echo esc_html($p['name'] ?? $id); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php _e('API keys are provided by the installer/admin and stored encrypted using StateManager.', 'newera'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="api_key"><?php _e('API Key', 'newera'); ?></label></th>
                        <td>
                            <input type="password" name="api_key" id="api_key" value="" class="regular-text" autocomplete="off" />
                            <p class="description">
                                <?php _e('Leave empty to keep the existing key.', 'newera'); ?>
                            </p>
                            <label>
                                <input type="checkbox" name="delete_api_key" value="1" />
                                <?php _e('Delete stored key for selected provider', 'newera'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="model"><?php _e('Model', 'newera'); ?></label></th>
                        <td>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                <input type="text" name="model" id="model" value="<?php echo esc_attr($model_value); ?>" class="regular-text" />
                                <button type="button" class="button" id="newera-ai-load-models">
                                    <?php _e('Load Models', 'newera'); ?>
                                </button>
                                <select id="newera-ai-models" style="min-width:260px;">
                                    <option value=""><?php _e('Select from loaded models…', 'newera'); ?></option>
                                    <?php if ($model_value !== '') : ?>
                                        <option value="<?php echo esc_attr($model_value); ?>" selected>
                                            <?php echo esc_html($model_value); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <p class="description">
                                <?php _e('Use “Load Models” to fetch available models using your stored key. If the provider does not support listing models, you can paste a model id manually.', 'newera'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="fallback_provider"><?php _e('Fallback Provider', 'newera'); ?></label></th>
                        <td>
                            <select name="fallback_provider" id="fallback_provider">
                                <option value=""><?php _e('None', 'newera'); ?></option>
                                <?php foreach ($providers as $id => $p) : ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($fallback_value, $id); ?>>
                                        <?php echo esc_html($p['name'] ?? $id); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('If the primary provider errors, Newera will attempt the fallback provider.', 'newera'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:30px;"><?php _e('Usage Policies (Rate Limits & Quotas)', 'newera'); ?></h2>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="max_requests_per_minute"><?php _e('Max Requests / Minute', 'newera'); ?></label></th>
                        <td>
                            <input type="number" min="0" name="max_requests_per_minute" id="max_requests_per_minute" value="<?php echo esc_attr($max_rpm); ?>" />
                            <p class="description"><?php _e('Set 0 to disable request rate limiting.', 'newera'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="monthly_token_quota"><?php _e('Monthly Token Quota', 'newera'); ?></label></th>
                        <td>
                            <input type="number" min="0" name="monthly_token_quota" id="monthly_token_quota" value="<?php echo esc_attr($monthly_token_quota); ?>" />
                            <p class="description"><?php _e('Set 0 to disable token quota. Tokens are provider-reported when available.', 'newera'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="monthly_cost_quota_usd"><?php _e('Monthly Cost Quota (USD)', 'newera'); ?></label></th>
                        <td>
                            <input type="number" step="0.01" min="0" name="monthly_cost_quota_usd" id="monthly_cost_quota_usd" value="<?php echo esc_attr($monthly_cost_quota); ?>" />
                            <p class="description"><?php _e('Cost tracking requires pricing below. Set 0 to disable cost quota.', 'newera'); ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:30px;"><?php _e('Pricing (for Cost Tracking)', 'newera'); ?></h2>

            <p class="description">
                <?php _e('To prevent runaway spending, you can optionally configure per-model pricing. Newera will then estimate cost from token usage and enforce cost quotas.', 'newera'); ?>
            </p>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="pricing_input_per_1k"><?php _e('Input $ / 1K Tokens', 'newera'); ?></label></th>
                        <td>
                            <input type="number" step="0.0001" min="0" name="pricing_input_per_1k" id="pricing_input_per_1k" value="<?php echo esc_attr($pricing_in); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="pricing_output_per_1k"><?php _e('Output $ / 1K Tokens', 'newera'); ?></label></th>
                        <td>
                            <input type="number" step="0.0001" min="0" name="pricing_output_per_1k" id="pricing_output_per_1k" value="<?php echo esc_attr($pricing_out); ?>" />
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(__('Save AI Settings', 'newera'), 'primary'); ?>
        </form>
    </div>

    <div class="newera-section" style="margin-top:30px;">
        <h2><?php _e('Usage (This Month)', 'newera'); ?></h2>

        <table class="widefat striped" style="max-width:900px;">
            <tbody>
                <tr>
                    <th><?php _e('Requests', 'newera'); ?></th>
                    <td><?php echo esc_html((string) ($monthly_totals['requests'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Total Tokens', 'newera'); ?></th>
                    <td><?php echo esc_html((string) ($monthly_totals['tokens_total'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Estimated Cost (USD)', 'newera'); ?></th>
                    <td><?php echo esc_html(number_format((float) ($monthly_totals['cost_usd'] ?? 0), 4)); ?></td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top:10px;">
            <button type="button" class="button" id="newera-ai-reset-usage">
                <?php _e('Reset Usage (Testing)', 'newera'); ?>
            </button>
        </p>

        <h3 style="margin-top:20px;"><?php _e('Recent AI Calls', 'newera'); ?></h3>

        <?php if (empty($recent_events)) : ?>
            <p class="description"><?php _e('No usage recorded yet.', 'newera'); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'newera'); ?></th>
                        <th><?php _e('Provider', 'newera'); ?></th>
                        <th><?php _e('Model', 'newera'); ?></th>
                        <th><?php _e('Status', 'newera'); ?></th>
                        <th><?php _e('Tokens', 'newera'); ?></th>
                        <th><?php _e('Cost', 'newera'); ?></th>
                        <th><?php _e('Duration (ms)', 'newera'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_events as $event) : ?>
                        <tr>
                            <td><?php echo esc_html($event['timestamp'] ?? ''); ?></td>
                            <td><?php echo esc_html($event['provider'] ?? ''); ?></td>
                            <td><?php echo esc_html($event['model'] ?? ''); ?></td>
                            <td><?php echo esc_html($event['status'] ?? ''); ?></td>
                            <td><?php echo esc_html((string) ($event['total_tokens'] ?? '')); ?></td>
                            <td>
                                <?php
                                $c = isset($event['cost_usd']) && $event['cost_usd'] !== null ? (float) $event['cost_usd'] : 0;
                                echo esc_html(number_format($c, 4));
                                ?>
                            </td>
                            <td><?php echo esc_html((string) ($event['duration_ms'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function($) {
    function loadModels(provider) {
        $('#newera-ai-load-models').prop('disabled', true);

        $.post(newera_ajax.ajax_url, {
            action: 'newera_ai_list_models',
            nonce: newera_ajax.nonce,
            provider: provider
        }).done(function(resp) {
            if (!resp || !resp.success) {
                alert((resp && resp.data) ? resp.data : 'Failed to load models.');
                return;
            }

            var $select = $('#newera-ai-models');
            $select.empty();
            $select.append($('<option>').val('').text('<?php echo esc_js(__('Select from loaded models…', 'newera')); ?>'));

            (resp.data.models || []).forEach(function(m) {
                $select.append($('<option>').val(m.id).text(m.label || m.id));
            });
        }).fail(function() {
            alert('Failed to load models.');
        }).always(function() {
            $('#newera-ai-load-models').prop('disabled', false);
        });
    }

    $(document).on('click', '#newera-ai-load-models', function() {
        var provider = $('#provider').val();
        if (!provider) {
            alert('<?php echo esc_js(__('Please select a provider first.', 'newera')); ?>');
            return;
        }
        loadModels(provider);
    });

    $(document).on('change', '#newera-ai-models', function() {
        var v = $(this).val();
        if (v) {
            $('#model').val(v);
        }
    });

    $(document).on('click', '#newera-ai-reset-usage', function() {
        if (!confirm('<?php echo esc_js(__('Reset recorded usage? This is intended for testing.', 'newera')); ?>')) {
            return;
        }

        $.post(newera_ajax.ajax_url, {
            action: 'newera_ai_reset_usage',
            nonce: newera_ajax.nonce
        }).done(function(resp) {
            if (resp && resp.success) {
                window.location.reload();
                return;
            }
            alert((resp && resp.data) ? resp.data : 'Failed to reset usage.');
        }).fail(function() {
            alert('Failed to reset usage.');
        });
    });
})(jQuery);
</script>
