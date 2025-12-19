<?php
/**
 * Newera Setup Wizard template
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
$step_context = is_array($template_data['step_context'] ?? null) ? $template_data['step_context'] : [];

$step_ids = array_keys($steps);
$progress_step = $wizard_state['current_step'] ?? 'intro';
$progress_index = array_search($progress_step, $step_ids, true);
if ($progress_index === false) {
    $progress_index = 0;
}

$saved_current = $wizard_state['data'][$current_step] ?? [];

$auth_providers = isset($step_context['providers']) && is_array($step_context['providers']) ? $step_context['providers'] : [];
$enabled_providers = $saved_current['providers_enabled'] ?? ($step_context['enabled_providers'] ?? []);
$enabled_providers = is_array($enabled_providers) ? $enabled_providers : [];

$db_type_value = $saved_current['db_type'] ?? 'wordpress';
$payments_provider = $saved_current['provider'] ?? '';
$payments_mode = $saved_current['stripe_mode'] ?? 'test';

$integrations_linear_team_id = $saved_current['linear_team_id'] ?? '';
$integrations_notion_db_id = $saved_current['notion_projects_database_id'] ?? '';
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
            <p><?php esc_html_e('Setup is complete. You can revisit the wizard, or go to the plugin dashboard.', 'newera'); ?></p>
        </div>

        <p>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=newera')); ?>">
                <?php esc_html_e('Go to Dashboard', 'newera'); ?>
            </a>
            <a class="button" href="<?php echo esc_url(add_query_arg('revisit', '1', $wizard_url)); ?>">
                <?php esc_html_e('Revisit Wizard', 'newera'); ?>
            </a>
        </p>

        <form method="post" action="<?php echo esc_url($wizard_url); ?>" style="margin-top: 20px;">
            <?php wp_nonce_field('newera_setup_wizard_reset', 'newera_setup_wizard_reset_nonce'); ?>
            <input type="hidden" name="newera_setup_wizard_reset" value="1" />
            <?php submit_button(__('Reset Wizard (for testing)', 'newera'), 'secondary', 'submit', false); ?>
        </form>

        <hr>
        <h2><?php esc_html_e('Saved Wizard Data (non-sensitive)', 'newera'); ?></h2>
        <pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-width:100%;overflow:auto;"><?php echo esc_html(wp_json_encode($wizard_state['data'] ?? [], JSON_PRETTY_PRINT)); ?></pre>

        <?php return; ?>
    <?php endif; ?>

    <p class="description">
        <?php esc_html_e('Sensitive values are stored encrypted and are not persisted in wizard state.', 'newera'); ?>
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
        <h2><?php echo esc_html($steps[$current_step]['title'] ?? ucfirst($current_step)); ?></h2>

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
                            <th scope="row"><label for="site_label"><?php esc_html_e('Site Label', 'newera'); ?></label></th>
                            <td>
                                <input type="text" id="site_label" name="site_label" value="<?php echo esc_attr($saved_current['site_label'] ?? ''); ?>" class="regular-text" />
                                <p class="description"><?php esc_html_e('Optional label used in notifications and UI.', 'newera'); ?></p>
                            </td>
                        </tr>

                    <?php elseif ($current_step === 'auth') : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Providers', 'newera'); ?></th>
                            <td>
                                <?php if (!empty($auth_providers)) : ?>
                                    <?php foreach ($auth_providers as $provider_id => $provider_cfg) : ?>
                                        <?php $provider_id = sanitize_key($provider_id); ?>
                                        <label style="display:block;margin:4px 0;">
                                            <input type="checkbox" name="providers_enabled[]" value="<?php echo esc_attr($provider_id); ?>" <?php checked(in_array($provider_id, $enabled_providers, true)); ?> />
                                            <?php echo esc_html($provider_cfg['label'] ?? ucfirst($provider_id)); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p class="description"><?php esc_html_e('Auth module not loaded yet. Save and reload this step.', 'newera'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php if (!empty($auth_providers)) : ?>
                            <?php foreach ($auth_providers as $provider_id => $provider_cfg) : ?>
                                <?php
                                $provider_id = sanitize_key($provider_id);
                                $requires_credentials = !empty($provider_cfg['requires_credentials']);
                                $redirect_uri = $provider_cfg['redirect_uri'] ?? '';

                                if (!$requires_credentials) {
                                    continue;
                                }
                                ?>
                                <tr>
                                    <th scope="row"><?php echo esc_html($provider_cfg['label'] ?? ucfirst($provider_id)); ?></th>
                                    <td>
                                        <?php if ($redirect_uri) : ?>
                                            <p><strong><?php esc_html_e('Redirect URL', 'newera'); ?>:</strong> <code><?php echo esc_html($redirect_uri); ?></code></p>
                                        <?php endif; ?>

                                        <p>
                                            <label for="<?php echo esc_attr($provider_id); ?>_client_id" style="display:block;font-weight:600;">
                                                <?php esc_html_e('Client ID', 'newera'); ?>
                                            </label>
                                            <input type="text" id="<?php echo esc_attr($provider_id); ?>_client_id" name="providers[<?php echo esc_attr($provider_id); ?>][client_id]" value="" class="regular-text" autocomplete="off" />
                                        </p>

                                        <p>
                                            <label for="<?php echo esc_attr($provider_id); ?>_client_secret" style="display:block;font-weight:600;">
                                                <?php esc_html_e('Client Secret', 'newera'); ?>
                                            </label>
                                            <input type="password" id="<?php echo esc_attr($provider_id); ?>_client_secret" name="providers[<?php echo esc_attr($provider_id); ?>][client_secret]" value="" class="regular-text" autocomplete="new-password" />
                                        </p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    <?php elseif ($current_step === 'database') : ?>
                        <tr>
                            <th scope="row"><label for="db_type"><?php esc_html_e('Database Type', 'newera'); ?></label></th>
                            <td>
                                <select id="db_type" name="db_type">
                                    <option value="wordpress" <?php selected($db_type_value, 'wordpress'); ?>><?php esc_html_e('WordPress Database (Default)', 'newera'); ?></option>
                                    <option value="external" <?php selected($db_type_value, 'external'); ?>><?php esc_html_e('External Database (PostgreSQL/Neon/Supabase)', 'newera'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Select where Newera stores its data.', 'newera'); ?></p>
                            </td>
                        </tr>

                        <tr id="external_db_options" style="display: <?php echo $db_type_value === 'external' ? 'table-row' : 'none'; ?>;">
                            <th scope="row"><label for="connection_string"><?php esc_html_e('Connection String', 'newera'); ?></label></th>
                            <td>
                                <textarea id="connection_string" name="connection_string" rows="3" class="large-text code"><?php echo esc_textarea($saved_current['connection_string'] ?? ''); ?></textarea>
                                <p class="description"><?php esc_html_e('Example: postgresql://user:pass@host:5432/db?sslmode=require', 'newera'); ?></p>
                                <p>
                                    <button type="button" id="test_connection_btn" class="button button-secondary"><?php esc_html_e('Test Connection', 'newera'); ?></button>
                                    <span id="connection_test_result" style="margin-left:10px;"></span>
                                </p>
                            </td>
                        </tr>

                        <tr id="table_prefix_row" style="display: <?php echo $db_type_value === 'external' ? 'table-row' : 'none'; ?>;">
                            <th scope="row"><label for="table_prefix"><?php esc_html_e('Table Prefix', 'newera'); ?></label></th>
                            <td>
                                <input type="text" id="table_prefix" name="table_prefix" value="<?php echo esc_attr($saved_current['table_prefix'] ?? 'wp_'); ?>" class="regular-text" />
                            </td>
                        </tr>

                        <tr id="persistent_row" style="display: <?php echo $db_type_value === 'external' ? 'table-row' : 'none'; ?>;">
                            <th scope="row"><label for="persistent"><?php esc_html_e('Persistent Connection', 'newera'); ?></label></th>
                            <td>
                                <label><input type="checkbox" id="persistent" name="persistent" value="1" <?php checked(!empty($saved_current['persistent'])); ?> /> <?php esc_html_e('Use persistent database connections', 'newera'); ?></label>
                            </td>
                        </tr>

                    <?php elseif ($current_step === 'payments') : ?>
                        <tr>
                            <th scope="row"><label for="provider"><?php esc_html_e('Payments Provider', 'newera'); ?></label></th>
                            <td>
                                <select id="provider" name="provider">
                                    <option value="" <?php selected($payments_provider, ''); ?>><?php esc_html_e('Select…', 'newera'); ?></option>
                                    <option value="stripe" <?php selected($payments_provider, 'stripe'); ?>>Stripe</option>
                                    <option value="manual" <?php selected($payments_provider, 'manual'); ?>><?php esc_html_e('Manual / Offline', 'newera'); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr class="stripe-fields" style="<?php echo $payments_provider === 'stripe' ? '' : 'display:none;'; ?>">
                            <th scope="row"><label for="stripe_api_key"><?php esc_html_e('Stripe Secret API Key', 'newera'); ?></label></th>
                            <td>
                                <input type="password" id="stripe_api_key" name="stripe_api_key" value="" class="regular-text" autocomplete="off" placeholder="sk_test_..." />
                                <p class="description"><?php esc_html_e('Stored encrypted. Leave blank to keep existing.', 'newera'); ?></p>
                            </td>
                        </tr>

                        <tr class="stripe-fields" style="<?php echo $payments_provider === 'stripe' ? '' : 'display:none;'; ?>">
                            <th scope="row"><label for="stripe_public_key"><?php esc_html_e('Stripe Publishable Key', 'newera'); ?></label></th>
                            <td>
                                <input type="text" id="stripe_public_key" name="stripe_public_key" value="" class="regular-text" autocomplete="off" placeholder="pk_test_..." />
                                <p class="description"><?php esc_html_e('Stored encrypted. Leave blank to keep existing.', 'newera'); ?></p>
                            </td>
                        </tr>

                        <tr class="stripe-fields" style="<?php echo $payments_provider === 'stripe' ? '' : 'display:none;'; ?>">
                            <th scope="row"><label for="stripe_webhook_secret"><?php esc_html_e('Stripe Webhook Signing Secret (optional)', 'newera'); ?></label></th>
                            <td>
                                <input type="password" id="stripe_webhook_secret" name="stripe_webhook_secret" value="" class="regular-text" autocomplete="off" placeholder="whsec_..." />
                            </td>
                        </tr>

                        <tr class="stripe-fields" style="<?php echo $payments_provider === 'stripe' ? '' : 'display:none;'; ?>">
                            <th scope="row"><label for="stripe_mode"><?php esc_html_e('Stripe Environment', 'newera'); ?></label></th>
                            <td>
                                <select id="stripe_mode" name="stripe_mode">
                                    <option value="test" <?php selected($payments_mode, 'test'); ?>><?php esc_html_e('Test', 'newera'); ?></option>
                                    <option value="live" <?php selected($payments_mode, 'live'); ?>><?php esc_html_e('Live', 'newera'); ?></option>
                                </select>
                            </td>
                        </tr>

                        <tr class="stripe-fields" style="<?php echo $payments_provider === 'stripe' ? '' : 'display:none;'; ?>">
                            <th scope="row"><?php esc_html_e('Sample Plans', 'newera'); ?></th>
                            <td>
                                <label><input type="checkbox" name="create_sample_plans" value="1" <?php checked(!empty($saved_current['create_sample_plans'] ?? false)); ?> /> <?php esc_html_e('Create sample plans (Starter/Pro)', 'newera'); ?></label>
                            </td>
                        </tr>

                    <?php elseif ($current_step === 'ai') : ?>
                        <?php
                        $provider_value = sanitize_key($saved_current['provider'] ?? '');
                        ?>
                        <tr>
                            <th scope="row"><label for="ai_provider"><?php esc_html_e('AI Provider', 'newera'); ?></label></th>
                            <td>
                                <select id="ai_provider" name="provider">
                                    <option value="" <?php selected($provider_value, ''); ?>><?php esc_html_e('Select…', 'newera'); ?></option>
                                    <option value="openai" <?php selected($provider_value, 'openai'); ?>>OpenAI</option>
                                    <option value="anthropic" <?php selected($provider_value, 'anthropic'); ?>>Anthropic</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_model"><?php esc_html_e('Model', 'newera'); ?></label></th>
                            <td>
                                <input type="text" id="ai_model" name="model" value="<?php echo esc_attr($saved_current['model'] ?? ''); ?>" class="regular-text" placeholder="e.g., gpt-4o-mini" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ai_api_key"><?php esc_html_e('API Key', 'newera'); ?></label></th>
                            <td>
                                <input type="password" id="ai_api_key" name="api_key" value="" class="regular-text" autocomplete="off" />
                                <p class="description"><?php esc_html_e('Stored encrypted. Leave blank to keep existing.', 'newera'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="max_requests_per_minute"><?php esc_html_e('Max requests per minute', 'newera'); ?></label></th>
                            <td><input type="number" id="max_requests_per_minute" name="max_requests_per_minute" value="<?php echo esc_attr($saved_current['max_requests_per_minute'] ?? 0); ?>" class="small-text" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="monthly_token_quota"><?php esc_html_e('Monthly token quota', 'newera'); ?></label></th>
                            <td><input type="number" id="monthly_token_quota" name="monthly_token_quota" value="<?php echo esc_attr($saved_current['monthly_token_quota'] ?? 0); ?>" class="small-text" min="0" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="monthly_cost_quota_usd"><?php esc_html_e('Monthly cost quota (USD)', 'newera'); ?></label></th>
                            <td><input type="number" id="monthly_cost_quota_usd" name="monthly_cost_quota_usd" value="<?php echo esc_attr($saved_current['monthly_cost_quota_usd'] ?? 0); ?>" class="small-text" min="0" step="0.01" /></td>
                        </tr>

                    <?php elseif ($current_step === 'integrations') : ?>
                        <tr>
                            <th scope="row"><label for="linear_api_key"><?php esc_html_e('Linear API Key', 'newera'); ?></label></th>
                            <td><input type="password" id="linear_api_key" name="linear_api_key" value="" class="regular-text" autocomplete="off" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="linear_webhook_secret"><?php esc_html_e('Linear Webhook Secret', 'newera'); ?></label></th>
                            <td><input type="password" id="linear_webhook_secret" name="linear_webhook_secret" value="" class="regular-text" autocomplete="off" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="linear_team_id"><?php esc_html_e('Linear Team ID', 'newera'); ?></label></th>
                            <td><input type="text" id="linear_team_id" name="linear_team_id" value="<?php echo esc_attr($integrations_linear_team_id); ?>" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notion_api_key"><?php esc_html_e('Notion API Key', 'newera'); ?></label></th>
                            <td><input type="password" id="notion_api_key" name="notion_api_key" value="" class="regular-text" autocomplete="off" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notion_webhook_secret"><?php esc_html_e('Notion Webhook Secret', 'newera'); ?></label></th>
                            <td><input type="password" id="notion_webhook_secret" name="notion_webhook_secret" value="" class="regular-text" autocomplete="off" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="notion_projects_database_id"><?php esc_html_e('Notion Projects Database ID', 'newera'); ?></label></th>
                            <td><input type="text" id="notion_projects_database_id" name="notion_projects_database_id" value="<?php echo esc_attr($integrations_notion_db_id); ?>" class="regular-text" /></td>
                        </tr>

                    <?php elseif ($current_step === 'review') : ?>
                        <tr>
                            <th scope="row"><?php esc_html_e('Confirm', 'newera'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="confirmed" value="1" required />
                                    <?php esc_html_e('I confirm the above settings are correct and want to complete setup.', 'newera'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Completing setup enables configured modules. You can change settings later.', 'newera'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <h3><?php esc_html_e('Summary (non-sensitive)', 'newera'); ?></h3>
                                <pre style="background:#f6f7f7;border:1px solid #c3c4c7;padding:12px;max-width:100%;overflow:auto;"><?php echo esc_html(wp_json_encode($wizard_state['data'] ?? [], JSON_PRETTY_PRINT)); ?></pre>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php submit_button($current_step === 'review' ? __('Complete Setup', 'newera') : __('Save & Continue', 'newera')); ?>
        </form>
    </div>
</div>

<script>
(function() {
    var dbType = document.getElementById('db_type');
    if (dbType) {
        dbType.addEventListener('change', function() {
            var show = this.value === 'external';
            document.getElementById('external_db_options').style.display = show ? 'table-row' : 'none';
            document.getElementById('table_prefix_row').style.display = show ? 'table-row' : 'none';
            document.getElementById('persistent_row').style.display = show ? 'table-row' : 'none';
        });
    }

    var provider = document.getElementById('provider');
    function toggleStripeFields() {
        var show = provider && provider.value === 'stripe';
        var rows = document.querySelectorAll('.stripe-fields');
        rows.forEach(function(row) {
            row.style.display = show ? '' : 'none';
        });
    }
    if (provider) {
        provider.addEventListener('change', toggleStripeFields);
        toggleStripeFields();
    }

    var testBtn = document.getElementById('test_connection_btn');
    if (testBtn && window.jQuery) {
        testBtn.addEventListener('click', function() {
            var conn = document.getElementById('connection_string');
            var result = document.getElementById('connection_test_result');
            if (!conn || !result) return;

            result.textContent = '<?php echo esc_js(__('Testing…', 'newera')); ?>';

            window.jQuery.post(ajaxurl, {
                action: 'newera_test_db_connection',
                nonce: '<?php echo esc_js(wp_create_nonce('newera_setup_wizard_ajax')); ?>',
                connection_string: conn.value
            }).done(function(resp) {
                if (resp && resp.success) {
                    result.textContent = '<?php echo esc_js(__('Connected', 'newera')); ?>';
                    result.style.color = 'green';
                } else {
                    result.textContent = (resp && resp.data) ? resp.data : '<?php echo esc_js(__('Failed', 'newera')); ?>';
                    result.style.color = 'red';
                }
            }).fail(function() {
                result.textContent = '<?php echo esc_js(__('Failed', 'newera')); ?>';
                result.style.color = 'red';
            });
        });
    }
})();
</script>
