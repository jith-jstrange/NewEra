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
