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
$step_context = is_array($template_data['step_context'] ?? null) ? $template_data['step_context'] : [];

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
        <?php _e('This is a minimal setup wizard scaffold. Sensitive values (like OAuth client secrets) are stored encrypted and are not kept in the wizard state.', 'newera'); ?>
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
                            <?php
                            $linear_manager = function_exists('newera_get_linear_manager') ? newera_get_linear_manager() : null;
                            $notion_manager = function_exists('newera_get_notion_manager') ? newera_get_notion_manager() : null;

                            $linear_configured = $linear_manager && method_exists($linear_manager, 'is_configured') && $linear_manager->is_configured();
                            $notion_configured = $notion_manager && method_exists($notion_manager, 'is_configured') && $notion_manager->is_configured();

                            $linear_team_id_value = $saved_current['linear_team_id'] ?? ($linear_manager && method_exists($linear_manager, 'get_team_id') ? $linear_manager->get_team_id() : '');
                            $notion_projects_db_value = $saved_current['notion_projects_database_id'] ?? ($notion_manager && method_exists($notion_manager, 'get_projects_database_id') ? $notion_manager->get_projects_database_id() : '');
                            ?>

                            <tr>
                                <th scope="row">
                                    <label for="linear_api_key"><?php _e('Linear API Key', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="linear_api_key" name="linear_api_key" value="" class="regular-text" autocomplete="off" />
                                    <p class="description">
                                        <?php echo $linear_configured ? esc_html__('A key is already stored securely. Leave blank to keep it.', 'newera') : esc_html__('Paste a Linear personal API key from the installer account.', 'newera'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="linear_webhook_secret"><?php _e('Linear Webhook Secret', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="linear_webhook_secret" name="linear_webhook_secret" value="" class="regular-text" autocomplete="off" />
                                    <p class="description"><?php _e('Used to validate incoming Linear webhook signatures.', 'newera'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="linear_team_id"><?php _e('Linear Team ID', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="linear_team_id" name="linear_team_id" value="<?php echo esc_attr($linear_team_id_value); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Required to create issues from new projects. You can also configure this later under Newera → Integrations.', 'newera'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="notion_api_key"><?php _e('Notion API Key', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="notion_api_key" name="notion_api_key" value="" class="regular-text" autocomplete="off" />
                                    <p class="description">
                                        <?php echo $notion_configured ? esc_html__('A key is already stored securely. Leave blank to keep it.', 'newera') : esc_html__('Paste a Notion integration token from the installer account.', 'newera'); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="notion_webhook_secret"><?php _e('Notion Webhook Secret', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="notion_webhook_secret" name="notion_webhook_secret" value="" class="regular-text" autocomplete="off" />
                                    <p class="description"><?php _e('Used to validate incoming webhook requests (if configured).', 'newera'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row">
                                    <label for="notion_projects_database_id"><?php _e('Notion Projects Database ID', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="notion_projects_database_id" name="notion_projects_database_id" value="<?php echo esc_attr($notion_projects_db_value); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Optional. If set, projects will sync to a Notion database (Name/Status/Progress).', 'newera'); ?></p>
                            $auth_providers = isset($step_context['providers']) && is_array($step_context['providers']) ? $step_context['providers'] : [];
                            $enabled_providers = $saved_current['providers_enabled'] ?? ($step_context['enabled_providers'] ?? []);
                            $enabled_providers = is_array($enabled_providers) ? $enabled_providers : [];
                            ?>

                            <tr>
                                <th scope="row">
                                    <?php _e('Providers', 'newera'); ?>
                                </th>
                                <td>
                                    <?php if (!empty($auth_providers)) : ?>
                                        <?php foreach ($auth_providers as $provider_id => $provider_cfg) : ?>
                                            <?php
                                            $provider_id = sanitize_key($provider_id);
                                            $label = $provider_cfg['label'] ?? ucfirst($provider_id);
                                            ?>
                                            <label style="display:block;margin:4px 0;">
                                                <input type="checkbox" name="auth_providers[]" value="<?php echo esc_attr($provider_id); ?>" <?php checked(in_array($provider_id, $enabled_providers, true)); ?> />
                                                <?php echo esc_html($label); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <p class="description">
                                            <?php _e('Auth module not loaded yet. Save and reload this step.', 'newera'); ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="description">
                                        <?php _e('Select the auth providers you want to enable. OAuth secrets are stored encrypted in wp_options via StateManager secure storage.', 'newera'); ?>
                                    </p>
                                </td>
                            </tr>

                            <?php if (!empty($auth_providers)) : ?>
                                <?php foreach ($auth_providers as $provider_id => $provider_cfg) : ?>
                                    <?php
                                    $provider_id = sanitize_key($provider_id);
                                    $requires_credentials = !empty($provider_cfg['requires_credentials']);
                                    $redirect_uri = $provider_cfg['redirect_uri'] ?? '';
                                    $has_client_id = !empty($provider_cfg['has_client_id']);
                                    $has_client_secret = !empty($provider_cfg['has_client_secret']);

                                    if (!$requires_credentials) {
                                        continue;
                                    }
                                    ?>
                                    <tr>
                                        <th scope="row">
                                            <?php echo esc_html($provider_cfg['label'] ?? ucfirst($provider_id)); ?>
                                        </th>
                                        <td>
                                            <?php if ($redirect_uri) : ?>
                                                <p>
                                                    <strong><?php _e('Redirect URL', 'newera'); ?>:</strong>
                                                    <code><?php echo esc_html($redirect_uri); ?></code>
                                                </p>
                                            <?php endif; ?>

                                            <p>
                                                <label for="<?php echo esc_attr($provider_id); ?>_client_id" style="display:block;font-weight:600;">
                                                    <?php _e('Client ID', 'newera'); ?>
                                                    <?php if ($has_client_id) : ?>
                                                        <span class="newera-badge newera-badge-green" style="margin-left:8px;vertical-align:middle;">
                                                            <?php _e('Stored', 'newera'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </label>
                                                <input type="text" id="<?php echo esc_attr($provider_id); ?>_client_id" name="<?php echo esc_attr($provider_id); ?>_client_id" value="" class="regular-text" autocomplete="off" />
                                                <span class="description" style="display:block;">
                                                    <?php _e('Paste your OAuth Client ID. Leave empty to keep the currently stored value.', 'newera'); ?>
                                                </span>
                                            </p>

                                            <p>
                                                <label for="<?php echo esc_attr($provider_id); ?>_client_secret" style="display:block;font-weight:600;">
                                                    <?php _e('Client Secret', 'newera'); ?>
                                                    <?php if ($has_client_secret) : ?>
                                                        <span class="newera-badge newera-badge-green" style="margin-left:8px;vertical-align:middle;">
                                                            <?php _e('Stored', 'newera'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </label>
                                                <input type="password" id="<?php echo esc_attr($provider_id); ?>_client_secret" name="<?php echo esc_attr($provider_id); ?>_client_secret" value="" class="regular-text" autocomplete="new-password" />
                                                <span class="description" style="display:block;">
                                                    <?php _e('Paste your OAuth Client Secret. Leave empty to keep the currently stored value.', 'newera'); ?>
                                                </span>
                                            </p>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php elseif ($current_step === 'database') : ?>
                            <tr>
                                <th scope="row">
                                    <label for="db_type"><?php _e('Database Type', 'newera'); ?></label>
                                </th>
                                <td>
                                    <select id="db_type" name="db_type">
                                        <?php
                                        $db_type_value = $saved_current['db_type'] ?? 'wordpress';
                                        $db_types = [
                                            'wordpress' => __('WordPress Database (Default)', 'newera'),
                                            'external' => __('External Database (PostgreSQL/Neon/Supabase)', 'newera'),
                                        ];
                                        foreach ($db_types as $value => $label) {
                                            printf(
                                                '<option value="%s" %s>%s</option>',
                                                esc_attr($value),
                                                selected($db_type_value, $value, false),
                                                esc_html($label)
                                            );
                                        }
                                        ?>
                                    </select>
                                    <p class="description"><?php _e('Select the database type for your plugin data storage.', 'newera'); ?></p>
                                </td>
                            </tr>
                            <tr id="external_db_options" style="display: <?php echo ($db_type_value === 'external') ? 'table-row' : 'none'; ?>;">
                                <th scope="row">
                                    <label for="connection_string"><?php _e('Connection String', 'newera'); ?></label>
                                </th>
                                <td>
                                    <textarea id="connection_string" name="connection_string" rows="3" class="large-text code"><?php echo esc_textarea($saved_current['connection_string'] ?? ''); ?></textarea>
                                    <p class="description">
                                        <?php _e('Format: postgresql://username:password@host:port/database?sslmode=require', 'newera'); ?><br>
                                        <?php _e('Example: postgresql://user:pass@db.example.com:5432/mydb?sslmode=require', 'newera'); ?>
                                    </p>
                                    <p>
                                        <button type="button" id="test_connection_btn" class="button button-secondary">
                                            <?php _e('Test Connection', 'newera'); ?>
                                        </button>
                                        <span id="connection_test_result" style="margin-left: 10px;"></span>
                                    </p>
                                </td>
                            </tr>
                            <tr id="table_prefix_row" style="display: <?php echo ($db_type_value === 'external') ? 'table-row' : 'none'; ?>;">
                                <th scope="row">
                                    <label for="table_prefix"><?php _e('Table Prefix', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="table_prefix" name="table_prefix" value="<?php echo esc_attr($saved_current['table_prefix'] ?? 'wp_'); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Table prefix for external database tables.', 'newera'); ?></p>
                                </td>
                            </tr>
                            <tr id="persistent_row" style="display: <?php echo ($db_type_value === 'external') ? 'table-row' : 'none'; ?>;">
                                <th scope="row">
                                    <label for="persistent"><?php _e('Persistent Connection', 'newera'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="persistent" name="persistent" value="1" <?php checked(!empty($saved_current['persistent'])); ?> />
                                        <?php _e('Use persistent database connections', 'newera'); ?>
                                    </label>
                                    <p class="description"><?php _e('Persistent connections can improve performance but may consume more server resources.', 'newera'); ?></p>
                                </td>
                            </tr>
                            <?php if (!empty($saved_current['migration_warning'])) : ?>
                            <tr>
                                <td colspan="2">
                                    <div class="notice notice-warning inline">
                                        <p><?php echo esc_html($saved_current['migration_warning']); ?></p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
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
                            <tr>
                                <th scope="row">
                                    <label for="stripe_api_key"><?php _e('Stripe API Key', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="password" id="stripe_api_key" name="stripe_api_key" value="<?php echo esc_attr($saved_current['stripe_api_key'] ?? ''); ?>" class="regular-text" autocomplete="off" />
                                    <p class="description">
                                        <?php _e('Get this from your Stripe Dashboard: ', 'newera'); ?>
                                        <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">https://dashboard.stripe.com/apikeys</a>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="stripe_public_key"><?php _e('Stripe Publishable Key', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="stripe_public_key" name="stripe_public_key" value="<?php echo esc_attr($saved_current['stripe_public_key'] ?? ''); ?>" class="regular-text" />
                                    <p class="description">
                                        <?php _e('Also from your Stripe Dashboard API Keys page', 'newera'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="stripe_mode"><?php _e('Stripe Environment', 'newera'); ?></label>
                                </th>
                                <td>
                                    <select id="stripe_mode" name="stripe_mode">
                                        <option value="test" <?php selected($saved_current['stripe_mode'] ?? 'test', 'test'); ?>>
                                            <?php _e('Test Mode (Development)', 'newera'); ?>
                                        </option>
                                        <option value="live" <?php selected($saved_current['stripe_mode'] ?? 'test', 'live'); ?>>
                                            <?php _e('Live Mode (Production)', 'newera'); ?>
                                        </option>
                                    </select>
                                    <p class="description">
                                        <?php _e('Use test mode to safely test payment processing.', 'newera'); ?>
                                    </p>
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

<script>
jQuery(document).ready(function($) {
    // Database type selector
    $('#db_type').on('change', function() {
        var dbType = $(this).val();
        if (dbType === 'external') {
            $('#external_db_options, #table_prefix_row, #persistent_row').show();
        } else {
            $('#external_db_options, #table_prefix_row, #persistent_row').hide();
        }
    });

    // Test connection button
    $('#test_connection_btn').on('click', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $result = $('#connection_test_result');
        var connectionString = $('#connection_string').val();

        if (!connectionString) {
            $result.html('<span style="color: #dc3232;">⚠ <?php _e('Please enter a connection string', 'newera'); ?></span>');
            return;
        }

        $btn.prop('disabled', true).text('<?php _e('Testing...', 'newera'); ?>');
        $result.html('<span style="color: #999;">⏳ <?php _e('Testing connection...', 'newera'); ?></span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'newera_test_db_connection',
                nonce: '<?php echo wp_create_nonce('newera_setup_wizard_ajax'); ?>',
                connection_string: connectionString
            },
            success: function(response) {
                if (response.success) {
                    var details = response.data.details || {};
                    var message = '✓ <?php _e('Connection successful!', 'newera'); ?>';
                    if (details.driver || details.database) {
                        message += ' (' + (details.driver || '?') + ' / ' + (details.database || '?') + ')';
                    }
                    $result.html('<span style="color: #46b450;">' + message + '</span>');
                } else {
                    $result.html('<span style="color: #dc3232;">✗ ' + (response.data || '<?php _e('Connection failed', 'newera'); ?>') + '</span>');
                }
            },
            error: function(xhr) {
                var message = '<?php _e('Connection failed', 'newera'); ?>';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    message = xhr.responseJSON.data;
                }
                $result.html('<span style="color: #dc3232;">✗ ' + message + '</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).text('<?php _e('Test Connection', 'newera'); ?>');
            }
        });
    });
});
</script>
