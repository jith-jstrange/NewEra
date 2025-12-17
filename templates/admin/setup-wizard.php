<?php
/**
 * Newera Plugin Setup Wizard Template
 *
 * @package Newera
 */

if (!defined('ABSPATH')) {
    exit;
}

$steps = $template_data['steps'] ?? [];
$wizard_state = $template_data['wizard_state'] ?? [];
$current_step = $template_data['current_step'] ?? 'intro';
$revisit = !empty($template_data['revisit']);
$notice = $template_data['notice'] ?? null;
$wizard_url = $template_data['wizard_url'] ?? admin_url('admin.php?page=' . \Newera\Admin\SetupWizard::PAGE_SLUG);

$step_ids = array_keys($steps);
$progress_step = $wizard_state['current_step'] ?? 'intro';
$progress_index = array_search($progress_step, $step_ids, true);
if ($progress_index === false) {
    $progress_index = 0;
}

$saved_current = $wizard_state['data'][$current_step] ?? [];
?>

<div class="wrap newera-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php if (is_array($notice) && !empty($notice['message'])) : ?>
        <div class="notice notice-<?php echo esc_attr($notice['type'] ?? 'info'); ?>">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($wizard_state['completed']) && !$revisit) : ?>
        <div class="notice notice-success">
            <p><?php _e('Setup is complete. You can revisit the wizard to adjust placeholder settings, or go to the plugin dashboard.', 'newera'); ?></p>
        </div>

        <p>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=newera')); ?>">
                <?php _e('Go to Dashboard', 'newera'); ?>
            </a>
            <a class="button" href="<?php echo esc_url(add_query_arg('revisit', '1', $wizard_url)); ?>">
                <?php _e('Revisit Wizard', 'newera'); ?>
            </a>
        </p>

        <form method="post" action="<?php echo esc_url($wizard_url); ?>" style="margin-top: 20px;">
            <?php wp_nonce_field('newera_setup_wizard_reset', 'newera_setup_wizard_reset_nonce'); ?>
            <input type="hidden" name="newera_setup_wizard_reset" value="1" />
            <?php submit_button(__('Reset Wizard (for testing)', 'newera'), 'secondary', 'submit', false); ?>
        </form>

        <hr>

        <h2><?php _e('Saved Wizard Data (placeholder)', 'newera'); ?></h2>
        <pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:100%;overflow:auto;"><?php echo esc_html(wp_json_encode($wizard_state['data'] ?? [], JSON_PRETTY_PRINT)); ?></pre>

        <?php return; ?>
    <?php endif; ?>

    <p class="description">
        <?php _e('This is a minimal setup wizard scaffold. Each step saves dummy data into wp_options so the wizard can be resumed.', 'newera'); ?>
    </p>

    <h2 class="nav-tab-wrapper">
        <?php foreach ($steps as $step_id => $step_config) : ?>
            <?php
            $idx = array_search($step_id, $step_ids, true);
            $idx = $idx === false ? 0 : $idx;

            $is_done = in_array($step_id, $wizard_state['completed_steps'] ?? [], true);
            $is_active = $step_id === $current_step;

            $accessible = $revisit || !empty($wizard_state['completed']) || $is_done || $idx <= $progress_index;
            $tab_class = 'nav-tab' . ($is_active ? ' nav-tab-active' : '');
            ?>

            <?php if ($accessible) : ?>
                <a class="<?php echo esc_attr($tab_class); ?>" href="<?php echo esc_url(add_query_arg(['step' => $step_id] + ($revisit ? ['revisit' => '1'] : []), $wizard_url)); ?>">
                    <?php echo esc_html($step_config['title']); ?>
                </a>
            <?php else : ?>
                <span class="<?php echo esc_attr($tab_class); ?>" style="opacity:0.5;cursor:not-allowed;">
                    <?php echo esc_html($step_config['title']); ?>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </h2>

    <div class="newera-section" style="margin-top: 20px;">
        <h2>
            <?php echo esc_html($steps[$current_step]['title'] ?? ucfirst($current_step)); ?>
            <?php if (!empty($wizard_state['completed_steps']) && in_array($current_step, $wizard_state['completed_steps'], true)) : ?>
                <span class="newera-badge newera-badge-green" style="margin-left:10px;vertical-align:middle;">
                    <?php _e('Saved', 'newera'); ?>
                </span>
            <?php endif; ?>
        </h2>

        <div>
            <?php if (!empty($steps[$current_step]['description'])) : ?>
                <p><?php echo esc_html($steps[$current_step]['description']); ?></p>
            <?php endif; ?>

            <?php $form_action = $revisit ? add_query_arg('revisit', '1', $wizard_url) : $wizard_url; ?>
            <form method="post" action="<?php echo esc_url($form_action); ?>">
                <?php wp_nonce_field('newera_setup_wizard_submit', 'newera_setup_wizard_nonce'); ?>
                <input type="hidden" name="newera_setup_wizard_submit" value="1" />
                <input type="hidden" name="step" value="<?php echo esc_attr($current_step); ?>" />

                <table class="form-table">
                    <tbody>
                        <?php if ($current_step === 'intro') : ?>
                            <tr>
                                <th scope="row">
                                    <label for="site_label"><?php _e('Site Label', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="site_label" name="site_label" value="<?php echo esc_attr($saved_current['site_label'] ?? ''); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Dummy field to demonstrate persistence.', 'newera'); ?></p>
                                </td>
                            </tr>
                        <?php elseif ($current_step === 'auth') : ?>
                            <tr>
                                <th scope="row">
                                    <label for="api_key"><?php _e('API Key', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="api_key" name="api_key" value="<?php echo esc_attr($saved_current['api_key'] ?? ''); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Placeholder field. For real credentials, use secure storage later.', 'newera'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="api_secret"><?php _e('API Secret', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="api_secret" name="api_secret" value="<?php echo esc_attr($saved_current['api_secret'] ?? ''); ?>" class="regular-text" autocomplete="off" />
                                </td>
                            </tr>
                        <?php elseif ($current_step === 'database') : ?>
                            <tr>
                                <th scope="row">
                                    <label for="connection_name"><?php _e('Connection Name', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="connection_name" name="connection_name" value="<?php echo esc_attr($saved_current['connection_name'] ?? ''); ?>" class="regular-text" />
                                </td>
                            </tr>
                        <?php elseif ($current_step === 'payments') : ?>
                            <tr>
                                <th scope="row">
                                    <label for="provider"><?php _e('Payments Provider', 'newera'); ?></label>
                                </th>
                                <td>
                                    <select id="provider" name="provider">
                                        <?php
                                        $provider_value = $saved_current['provider'] ?? '';
                                        $providers = [
                                            '' => __('Select…', 'newera'),
                                            'stripe' => 'Stripe',
                                            'paypal' => 'PayPal',
                                            'manual' => __('Manual / Offline', 'newera'),
                                        ];
                                        foreach ($providers as $value => $label) {
                                            printf(
                                                '<option value="%s" %s>%s</option>',
                                                esc_attr($value),
                                                selected($provider_value, $value, false),
                                                esc_html($label)
                                            );
                                        }
                                        ?>
                                    </select>
                                </td>
                            </tr>
                        <?php elseif ($current_step === 'ai') : ?>
                            <?php
                            $ai_manager = function_exists('newera_get_ai_manager') ? newera_get_ai_manager() : null;
                            $provider_value = sanitize_key($saved_current['provider'] ?? '');
                            $api_key_stored = ($ai_manager && $provider_value !== '' && method_exists($ai_manager, 'has_api_key')) ? (bool) $ai_manager->has_api_key($provider_value) : false;
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="ai_provider"><?php _e('AI Provider', 'newera'); ?></label>
                                </th>
                                <td>
                                    <select id="ai_provider" name="provider">
                                        <?php
                                        $providers = [
                                            '' => __('Select…', 'newera'),
                                            'openai' => 'OpenAI',
                                            'anthropic' => 'Anthropic',
                                        ];
                                        foreach ($providers as $value => $label) {
                                            printf(
                                                '<option value="%s" %s>%s</option>',
                                                esc_attr($value),
                                                selected($provider_value, $value, false),
                                                esc_html($label)
                                            );
                                        }
                                        ?>
                                    </select>
                                    <p class="description">
                                        <?php _e('Select a provider, enter an API key, then load models.', 'newera'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ai_api_key"><?php _e('API Key', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="ai_api_key" name="api_key" value="" class="regular-text" autocomplete="off" />
                                    <p class="description">
                                        <?php _e('Stored encrypted. Leave empty to keep existing.', 'newera'); ?>
                                        <?php if ($api_key_stored) : ?>
                                            <strong><?php _e('A key is already stored for this provider.', 'newera'); ?></strong>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ai_model"><?php _e('Model', 'newera'); ?></label>
                                </th>
                                <td>
                                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                        <input type="text" id="ai_model" name="model" value="<?php echo esc_attr($saved_current['model'] ?? ''); ?>" class="regular-text" />
                                        <button type="button" class="button" id="newera-wizard-ai-load-models">
                                            <?php _e('Load Models', 'newera'); ?>
                                        </button>
                                        <select id="newera-wizard-ai-models" style="min-width:260px;">
                                            <option value=""><?php _e('Select from loaded models…', 'newera'); ?></option>
                                            <?php if (!empty($saved_current['model'])) : ?>
                                                <option value="<?php echo esc_attr($saved_current['model']); ?>" selected>
                                                    <?php echo esc_html($saved_current['model']); ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <p class="description">
                                        <?php _e('If model listing fails, paste a model id manually.', 'newera'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ai_max_requests_per_minute"><?php _e('Max Requests / Minute', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="number" min="0" id="ai_max_requests_per_minute" name="max_requests_per_minute" value="<?php echo esc_attr($saved_current['max_requests_per_minute'] ?? 60); ?>" />
                                    <p class="description"><?php _e('Rate limit to avoid runaway usage. Set 0 to disable.', 'newera'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ai_monthly_token_quota"><?php _e('Monthly Token Quota', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="number" min="0" id="ai_monthly_token_quota" name="monthly_token_quota" value="<?php echo esc_attr($saved_current['monthly_token_quota'] ?? 50000); ?>" />
                                    <p class="description"><?php _e('Monthly safety cap. Set 0 to disable.', 'newera'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="ai_monthly_cost_quota_usd"><?php _e('Monthly Cost Quota (USD)', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="number" step="0.01" min="0" id="ai_monthly_cost_quota_usd" name="monthly_cost_quota_usd" value="<?php echo esc_attr($saved_current['monthly_cost_quota_usd'] ?? 0); ?>" />
                                    <p class="description"><?php _e('Optional. Cost tracking requires pricing configured in Newera → AI.', 'newera'); ?></p>
                                </td>
                            </tr>
                        <?php elseif ($current_step === 'review') : ?>
                            <tr>
                                <th scope="row"><?php _e('Summary', 'newera'); ?></th>
                                <td>
                                    <p class="description">
                                        <?php _e('Below is the data saved so far. Submitting this step marks onboarding as completed.', 'newera'); ?>
                                    </p>
                                    <pre style="background:#f6f7f7;border:1px solid #c3c4c7;padding:12px;max-width:100%;overflow:auto;"><?php echo esc_html(wp_json_encode($wizard_state['data'] ?? [], JSON_PRETTY_PRINT)); ?></pre>
                                </td>
                            </tr>
                        <?php else : ?>
                            <tr>
                                <th scope="row"><?php _e('Placeholder', 'newera'); ?></th>
                                <td><?php _e('This step does not have fields yet.', 'newera'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($current_step === 'ai') : ?>
                    <script>
                    (function($) {
                        function loadModels(provider) {
                            $('#newera-wizard-ai-load-models').prop('disabled', true);

                            $.post(newera_ajax.ajax_url, {
                                action: 'newera_ai_list_models',
                                nonce: newera_ajax.nonce,
                                provider: provider,
                                api_key: $('#ai_api_key').val() || ''
                            }).done(function(resp) {
                                if (!resp || !resp.success) {
                                    alert((resp && resp.data) ? resp.data : 'Failed to load models.');
                                    return;
                                }

                                var $select = $('#newera-wizard-ai-models');
                                $select.empty();
                                $select.append($('<option>').val('').text('<?php echo esc_js(__('Select from loaded models…', 'newera')); ?>'));

                                (resp.data.models || []).forEach(function(m) {
                                    $select.append($('<option>').val(m.id).text(m.label || m.id));
                                });
                            }).fail(function() {
                                alert('Failed to load models.');
                            }).always(function() {
                                $('#newera-wizard-ai-load-models').prop('disabled', false);
                            });
                        }

                        $(document).on('click', '#newera-wizard-ai-load-models', function() {
                            var provider = $('#ai_provider').val();
                            if (!provider) {
                                alert('<?php echo esc_js(__('Please select a provider first.', 'newera')); ?>');
                                return;
                            }
                            loadModels(provider);
                        });

                        $(document).on('change', '#newera-wizard-ai-models', function() {
                            var v = $(this).val();
                            if (v) {
                                $('#ai_model').val(v);
                            }
                        });
                    })(jQuery);
                    </script>
                <?php endif; ?>

                <p>
                    <?php
                    $prev_step = $this->get_previous_step($current_step);
                    if ($current_step !== 'intro') :
                        $back_args = ['step' => $prev_step];
                        if ($revisit) {
                            $back_args['revisit'] = '1';
                        }
                        ?>
                        <a class="button" href="<?php echo esc_url(add_query_arg($back_args, $wizard_url)); ?>">
                            <?php _e('Back', 'newera'); ?>
                        </a>
                    <?php endif; ?>

                    <?php
                    $button_text = $current_step === 'review' ? __('Complete Setup', 'newera') : __('Save & Continue', 'newera');
                    submit_button($button_text, 'primary', 'submit', false);
                    ?>
                </p>
            </form>

            <form method="post" action="<?php echo esc_url($wizard_url); ?>" style="margin-top: 10px;">
                <?php wp_nonce_field('newera_setup_wizard_reset', 'newera_setup_wizard_reset_nonce'); ?>
                <input type="hidden" name="newera_setup_wizard_reset" value="1" />
                <?php submit_button(__('Reset Wizard', 'newera'), 'secondary', 'submit', false); ?>
                <span class="description" style="margin-left:8px;">
                    <?php _e('Clears progress and saved step data (for testing).', 'newera'); ?>
                </span>
            </form>

            <hr>

            <h3><?php _e('Progress', 'newera'); ?></h3>
            <p>
                <?php
                $completed_count = is_array($wizard_state['completed_steps'] ?? null) ? count($wizard_state['completed_steps']) : 0;
                printf(
                    /* translators: 1: number of completed steps, 2: total steps */
                    esc_html__('%1$d of %2$d steps saved.', 'newera'),
                    intval($completed_count),
                    intval(count($steps))
                );
                ?>
            </p>
            <p class="description">
                <?php
                if (!empty($wizard_state['last_updated'])) {
                    printf(
                        /* translators: %s: last updated timestamp */
                        esc_html__('Last updated: %s', 'newera'),
                        esc_html($wizard_state['last_updated'])
                    );
                }
                ?>
            </p>
        </div>
    </div>

    <p class="description">
        <?php _e('AJAX endpoint stub: wp_ajax_newera_setup_wizard_save_step', 'newera'); ?>
    </p>
</div>
