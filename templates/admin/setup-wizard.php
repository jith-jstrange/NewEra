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
                        <?php elseif ($current_step === 'ai') : ?>
                            <tr>
                                <th scope="row">
                                    <label for="provider"><?php _e('AI Provider', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="provider" name="provider" value="<?php echo esc_attr($saved_current['provider'] ?? ''); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="model"><?php _e('Model', 'newera'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="model" name="model" value="<?php echo esc_attr($saved_current['model'] ?? ''); ?>" class="regular-text" />
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
